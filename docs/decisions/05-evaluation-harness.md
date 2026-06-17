# 05 — Evaluation harness: proper-scoring comparison over owned ground truth

## Context

Phase 3 is the differentiator: the thing that makes this a platform for *comparing* predictors
rather than an app with one. We have two champion predictors (`MonteCarloPredictor`,
`DeterministicClincher`) and need to answer "better / safer / faster" with numbers, reproducibly.

Because the league is a simulation, we own ground truth — but "truth" can mean two things, and
the choice shapes the whole harness.

## Decision

**Score against realised outcomes with proper scoring rules, not against a peeked-at true
distribution.** The harness rolls the *reference model* to season end many times to get realised
champions, then grades each predictor's probability vector against those one-hot outcomes with
**Brier** and **log-loss**. Both are *proper*: expected score is minimised by reporting the true
distribution, so a predictor cannot win by being over- or under-confident — and we never have to
hand it a privileged answer key. This mirrors how a real recommender is evaluated: against what
actually happened, never against an oracle.

Supporting choices:

- **Common random numbers.** Ground-truth champions are drawn once per scenario and every
  predictor is graded against the *same* realised seasons. The comparison is paired, so outcome
  noise cancels in the differences between predictors.
- **Decorrelated seeds.** The forecast seed and the ground-truth seed differ by a fixed offset,
  so a Monte Carlo predictor is never graded against the very draws it sampled.
- **Ground truth = the reference model.** Realised champions come from the same `GoalModel` the
  MC predictor samples, so MC-at-large-N is the gold standard and the harness measures how close
  the cheap predictors get. Making ground truth *differ* from a predictor's model is how one would
  test misspecification — a future knob, not built now.
- **The metric is a strategy.** `ScoringRule` (with `BrierScore`, `LogLoss`) is itself a seam —
  adding a metric is a new implementation, consistent with the "no single algorithm" thesis.
- **Predictors arrive labelled** (`array<string, ChampionPredictor>`), not via a `key()` on the
  interface. Registry identity is a Phase-4 concern; keeping it out of the domain now is the
  smaller change.
- **`ChampionSampler` + `ChampionSamplerFactory`** are extracted as the single home of
  compile→sample→extend→rank→champion. Both `MonteCarloPredictor` and the harness's ground truth
  draw from it, so the "compile once, sample many" machinery lives in exactly one place.
- **Randomness moves to `predict(... , RandomSource)`.** Holding the RNG in the constructor made
  it impossible to re-run a predictor from a fresh seed (the engine is stateful), which the
  determinism check needs. Per-call randomness puts seeding in the caller's hands, makes a run
  reproducible from the source it is handed, and matches the seam the plan always described.
  Deterministic predictors accept and ignore it.

## Consequences

- The scorecard reports **Brier, log-loss, mean latency, determinism** per predictor and sorts by
  Brier. A real simulation beats the `PointsHeuristicPredictor` baseline on a proper rule; the
  clincher scores a perfect 0 once a title is settled and is mediocre while undecided — a result
  the scorecard surfaces for free.
- Latency is read from the monotonic clock (`hrtime`) directly inside the harness — a contained,
  deliberate exception, since measuring its own runtime is the harness's job. A `Clock` seam is
  future work only if the timing ever needs faking; it does not affect prediction determinism.
- Log-loss floors probability at `EPSILON` so a ruled-out-then-realised outcome is a large but
  finite penalty — the honest reading, not a swallowed error.
- The harness is pure domain (no Illuminate, no DB); it is unit-tested with small iteration and
  draw counts and runs in milliseconds.
