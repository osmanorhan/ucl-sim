# 01 — Predictions are a precomputed, versioned read model

**Status:** accepted · **Date:** 2026-06-16 · supersedes the "pull-on-read" stance in `plan.md` §5

## Context

The predictor is a **pluggable strategy**, not a fixed centerpiece — Monte Carlo is just one
implementation (and the heaviest: N≈10⁴ trials). The read path must not inherit the cost of
*whichever* strategy is plugged in. Reads are frequent; state changes only on a few explicit
writes. Per-strategy latency is not an architectural special case — it is a benchmark dimension
the EvaluationHarness already measures (`meanLatencyMs`), so a heavy strategy reveals itself in
its own scorecard rather than dictating the design.

## Decision

Treat predictions as a **read model recomputed on state change, not on read** — decoupling read
latency from the plugged strategy's cost. The active predictor is configurable; the harness
scorecard tells us which strategies are inline-safe (e.g. clincher + heuristic) versus
better run offline (heavy Monte Carlo).

- State-changing writes (`play-week`, `play-all`, edit) recompute predictions **once** and
  persist them inside a versioned league snapshot (see ADR 03). `GET` serves the snapshot —
  O(1), no computation.
- `MonteCarloPredictor` runs an **allocation-light numeric kernel**: goals/points accumulate
  in primitive int arrays, no per-trial `MatchResult`/`Standing` instantiation. It shares the
  *same* `GoalModel` as the real simulation, so fidelity is preserved. PHP 8.3 JIT applies to
  the numeric loop.
- Trial count `N` is explicit and configurable. If a run exceeds its time budget it **fails
  fast** — we never silently reduce `N` or fall back to a cheaper heuristic (honours the
  no-fallbacks principle).
- `PredictionService` is the single seam. It is called inline today; making it a queued job
  later requires no change to domain code.

## Consequences

- Read latency is constant; compute is amortised onto infrequent writes.
- The snapshot doubles as the SPA payload (ADR 03) and carries the read-model's version key.
- The async/queued + websocket variant remains future work behind the same seam.
