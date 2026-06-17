# 06 — Persistence stores facts; every view is a re-fold

**Status:** accepted · **Date:** 2026-06-17

## Context

Phase 4 puts the pure domain behind a REST API with durable state. The risk in any such layer is
*drift*: a denormalised table, a cached prediction, and the underlying results sliding out of sync
after an edit. The domain already gives us a way out — `LeagueTable::project` and every predictor
are **pure functions of (teams, played, remaining)** — so the question is what to treat as the
source of truth and what to derive.

## Decision

**Persist only the authoritative facts; derive every view by re-folding them.** A league is its
teams, its seed, a monotonic version, and a row per match (a `Fixture` with an optional result).
The table, the fixtures view, and the predictions are *never* stored as truth — they are projected
from the match facts on each write and cached as one snapshot (ADR-01/03).

- **`LeagueState` is the aggregate; the repository speaks only it.** `LeagueRepository` lives in
  the pure domain and trades in `LeagueState` plus the snapshot array; the Eloquent implementation
  maps rows ⇄ aggregate at the edge (ADR-02). Swapping SQLite for Postgres is a binding change.
- **Editing is correct by construction.** A `PUT /matches/{id}` overwrites one result row and
  re-folds; there is no standings table to keep consistent, so the failure mode does not exist.
  Provenance is a typed `ResultOrigin` (`simulated` / `manual`) bound to the result's presence —
  not a free-floating `is_edited` boolean — so a result without an origin cannot be represented.
- **Deterministic, incremental progression.** `SeasonProgression` plays each week from a source
  seeded purely on `(seed, week)`. Two properties fall out for free: a run is reproducible from
  the seed, and **play-week × N is byte-identical to play-all** — there is no cross-week RNG state
  for incremental play to diverge from. This invariant is asserted as a feature test. Within a week
  the seeded draws are assigned to fixtures *by position*, so that order must be stable across
  reloads: the repository persists an explicit `sequence` and always loads ordered by it, making
  match order a stored contract rather than DB-natural-order luck. The `seed` column is signed —
  `Mt19937` accepts any 64-bit int, so storage matches the domain rather than the validator
  narrowing it.
- **The live predictor is a decorator, not a branch.** `SettledOrSimulated` returns the clincher's
  certainty when the title is mathematically decided and Monte Carlo otherwise. Because predictions
  are recomputed on *write* (ADR-01), the heavy MC can back the *displayed* odds while reads stay
  O(1) — exactly the latitude the read-model decision was meant to buy.
- **The snapshot is the one response shape; no Resource layer.** `SnapshotAssembler` is the single
  authority on the read model. A Laravel API Resource would re-wrap an already-shaped, already-stored
  array on the read path, so it is deliberately omitted. FormRequests still own input validation.

## Consequences

- State drift is impossible by construction; an edit can never leave table and predictions
  disagreeing.
- The cost of a write is one projection + one snapshot persist; reads are a single row fetch.
- `LeagueState`, `ScheduledMatch`, and `SeasonProgression` are the only new domain types, and each
  earns its place: the aggregate the repository reconstitutes, the persisted match unit, and the
  reproducible forward-play. Everything else is wiring at the edge.
- A queued/async predictor or websocket push remains future work behind the same `LeagueService`
  seam — no domain change required.
