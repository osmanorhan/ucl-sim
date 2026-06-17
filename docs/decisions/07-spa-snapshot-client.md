# 07 — The SPA is a thin renderer of the server snapshot

**Status:** accepted · **Date:** 2026-06-17

## Context

Phase 5 puts a Vue 3 + TypeScript SPA in front of the API. The temptation in any rich client is to
re-implement domain logic — fold standings locally for snappy edits, predict derived state, reconcile
caches. That re-creates on the client exactly the drift the re-fold persistence (ADR-06) was designed
to make impossible, and splits one source of truth into two.

## Decision

**The client renders the server's versioned snapshot and folds nothing.** Every mutating call returns
the whole `{version, league, table, fixtures, predictions}` (ADR-03); the Pinia store swaps it in
atomically. The standings, odds, and week states shown are always the server's, never recomputed.

- **The boundary is parsed, not trusted.** `src/api/client.ts` runs every response through a Zod
  schema (`LeagueSnapshotSchema` et al.) and every request body before it is sent. A backend shape
  drift — including a `match.origin` outside `simulated | manual` — fails loudly at the edge instead
  of surfacing as `undefined` deep in a component. This is fail-fast carried into TypeScript.
- **A version guard makes out-of-order responses safe.** `applySnapshot` rejects a snapshot whose
  version is older than the one in hand for the same league. Concurrent writes (an edit racing a
  play-week) converge on the newest server state regardless of arrival order — the client complement
  to the server's monotonic version.
- **Editing is a correction, not a creation.** The score editor renders only for *played* matches; a
  scheduled fixture is read-only. A manual result (`ResultOrigin::Manual`) thus always overwrites a
  recorded one, never fabricates a result for a future week out of order — keeping the states the UI
  can produce a subset of the states normal play produces.
- **Layering mirrors the backend's seams.** `api → store → view-model → view → components`
  (atoms/molecules/organisms). The active predictor's key is display-only; strategy selection stays
  backend configuration (ADR-01/05), so the UI exposes the seam without owning it.

## Consequences

- No client-side drift is possible: there is no second fold to keep consistent with the server's.
- The cost is a full snapshot over the wire per mutation — already the API's contract, and cheap at
  this scale. Reads stay a single fetch.
- Vitest pins the pure seams (version guard, week-state machine, boundary schemas); component
  rendering is left to the type-checker and the small surface area rather than a heavy DOM suite.
- A future websocket/push channel drops in behind the same store action — apply a pushed snapshot
  through the same version guard, no view change.
