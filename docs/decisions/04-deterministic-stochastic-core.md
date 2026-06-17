# 04 — Stochastic core: seeded engine, ratio goal model, comparable predictors

**Status:** accepted · **Date:** 2026-06-16

The match engine and championship prediction (Phase 2) are stochastic, but the `Domain` layer
must stay pure, deterministic under a seed, and free of hard-wired algorithms. Three coupled
choices follow.

## Randomness — one seeded seam

All randomness enters through a single `RandomSource` interface (`nextFloat(): float` in
`[0, 1)`); every stochastic unit takes it as a constructor dependency, so the side effect is
named in the type. `SeededRandomSource` wraps PHP's `Random\Randomizer` over a seeded
`Random\Engine\Mt19937` — an engine **object that owns its state**, so there is no global
`mt_srand`: same seed ⇒ same sequence, two sources never interfere. We deliberately do *not*
hand-roll a PRNG; the engine-object API (PHP 8.2+) removes the only reason to. `Random\*` is PHP
core, not `Illuminate\*`, so domain purity holds.

## Goals — power-ratio Poisson

`GoalModel` is a seam; `PoissonGoalModel` sets `λ_home = base · (P_home/P_away) · homeAdv` and
`λ_away = base · (P_away/P_home)`, with `base`/`homeAdv` as constructor parameters. Goals are
Knuth-sampled from `PoissonDistribution` (O(λ), cheap at football scorelines). Every spec
requirement is emergent — stronger team usually wins, upsets via the Poisson tail. **Bounded
input:** the ratio is unbounded, so powers are seeded in a sane band (~40–90); a 2× gap already
gives a strong favourite at λ ≤ ~3.5, while the spec's literal 100-vs-10 (λ≈17) is represented
as 90-vs-45. Honouring arbitrary ranges (`base·exp(k·Δ)`) is a drop-in `GoalModel` swap.

## Prediction — competing strategies, not one algorithm

`ChampionPredictor` (`predict(teams, played, remaining): ChampionProbabilities`) is the seam;
randomness, when needed, is a constructor dependency so the signature stays uniform.
`MonteCarloPredictor` completes the remaining fixtures `iterations` times via `SeasonSimulator`,
ranks each finished season with the league's own `Ranking`, and counts titles — reproducible
from the injected source. `DeterministicClincher` runs zero simulation: a team is a contender
iff its maximum attainable points reach the best a rival already holds, and the title splits
uniformly over contenders — so a clinched leader collapses to 1.0 and eliminated teams to 0 as
an *emergent* case, not a branch. The two agree where the season is settled (asserted as a
cross-check), giving a fast exact oracle for the tail and a sanity bound on the simulator.

## Consequences

- Simulations and predictions replay bit-for-bit from a seed; tests assert seeded means/ranges
  and invariants, never luck. The edit-results feature recomputes stably.
- Every algorithm (engine, goal model, predictor) sits behind a seam the EvaluationHarness can
  score head-to-head — the recommendation-platform thesis applied to the match engine. New
  variants are additive; no consumer changes.
- `ChampionProbabilities` is a self-validating value object; sum-to-one holds by construction
  and is covered by test, not brittle float equality.
