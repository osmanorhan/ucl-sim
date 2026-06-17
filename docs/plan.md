# Champions League Simulation — Implementation Plan

> **Status:** design locked, pre-implementation
> **Stack:** PHP 8.5 (≥8.3) / Laravel 13, Vue 3 + TypeScript, SQLite, Docker, GitHub Actions
> **Spec:** see [`requirements.md`](requirements.md)

---

## 0. Framing — what this submission is actually demonstrating

The surface task is a 4-team league that simulates matches from team power, shows a
Premier-League-ruled table, predicts championship odds in the final 3 weeks, plays a whole
season automatically, and lets you edit past results and recompute.

At that scale nobody wins by owning the single best
ranking model — they win by owning the *system* that lets them run many models, measure which
is better / safer / faster, and roll back in seconds. This codebase is built to read that way.

| Football case | What it stands in for |
|---|---|
| `MatchSimulator` (Poisson) | candidate scoring model |
| `ChampionshipPredictor` (Monte Carlo) | ranking strategy → top-N |
| team `power` → goal rate λ | item/user features → relevance score |
| "plug & play algorithms" | model/strategy **registry**, hot-swappable |
| "which is better / safer / faster" | offline eval + champion/challenger |
| seeded PRNG → reproducible | replayable scoring, deterministic experiments |
| immutable results + projection | event log + materialized view (backfill/replay) |
| property/invariant tests | guardrail metrics on output |
| prediction confidence interval | uncertainty-aware ranking (explore/exploit) |

**Guiding principle (non-negotiable):** *in a real environment no single algorithm always
wins.* Therefore the centerpiece is not the Poisson math — it is the **seam** that makes
algorithms interchangeable and **comparable**, plus the restraint to keep everything else
minimal.

---

## 1. Problem structure (fixed facts that drive the design)

- **4 teams**, double round-robin → each plays **6 matches**, **12 matches total**, over
  **6 weeks**, **2 matches per week**.
- A trailing team can earn at most **6 points** in the final 2 weeks → "9 points ahead with
  2 weeks left = clinched" is a *combinatorial fact*, not a sampled estimate. The predictor
  must handle the decided regime exactly and the undecided regime by sampling.
- Predictions begin **"entering the last 3 weeks" → weeks 4, 5, 6.**
- Tiebreakers (Premier League order): **Points → Goal Difference → Goals Scored → Head-to-Head.**

### 1.1 Scope & spec coverage

**Binding scope is Part 1, not the FAQ.** Part 1 states *"There will be four teams in the
league."* The FAQ (Part 2) describes the **real** UEFA tournament as background — its
"32 teams / 8 groups of four / seeding pots / same-nation" material explains what a
league/group/fixture *is*; it is not the build target. FAQ #2 itself confirms our model: a
single group is *"four teams … the other three … home and away in a round-robin"* — exactly our
double round-robin (6 games/team, 12 total, 6 weeks).

**Deliberately out of scope (background only), with the generalising seam noted:**

- 32 teams / 8 groups → we build **one** such group; the scheduler already handles any even N,
  so a multi-group tournament is a *container* (`Group` owns teams + scheduler + table +
  predictor; tournament = collection of groups) — not a redesign.
- Seeding pots / "no team from the same association" / Tue–Wed split (FAQ #4) → draw-time
  constraints, irrelevant to a fixed 4-team group; would live in a separate `DrawStrategy`.

**FAQ → where we cover it:**

| FAQ | Topic | Coverage |
|---|---|---|
| 1 | 3-1-0 scoring; wins valued over draws | `MatchOutcome` enum + PL ranking chain |
| 2 | Group of four, 6 games home & away | `BergerRoundRobinScheduler` (12 fixtures, 6 weeks) |
| 3 | What the UCL is | context only |
| 4 | Seeding pots / draw constraints | out of scope; seam = `DrawStrategy` |
| 5 | Week-by-week sim; power; upsets possible | Poisson `MatchSimulator` (Phase 2) |
| 6 | Power + home/away + supporter/keeper factors | `GoalModel` multiplicative terms (Phase 2) |
| 7 | Design need not match | n/a |
| 8 | Championship % in last 3 weeks; clinched = 100% | MC predictor + `DeterministicClincher`, weeks 4–6 |

---

## 2. Architecture

### 2.1 Layers

```
┌──────────────────────────────────────────────────────────────┐
│ Delivery        Vue 3 SPA  ·  REST controllers + API Resources │
├──────────────────────────────────────────────────────────────┤
│ Application     LeagueService · SimulationService · EvalHarness│   orchestration, no domain rules
├──────────────────────────────────────────────────────────────┤
│ Domain (pure)   Entities · Value Objects · Strategy interfaces │   zero Illuminate imports
│                 LeagueTable projection · Comparators · RNG     │
├──────────────────────────────────────────────────────────────┤
│ Infrastructure  Eloquent repositories · StrategyRegistry       │   adapters behind interfaces
└──────────────────────────────────────────────────────────────┘
```

The **Domain layer is framework-agnostic pure PHP** — it is the algorithmic core, 100%
unit-tested, and importing anything from `Illuminate\*` into it is a build failure (enforced;
see §7.4). Laravel is a delivery and persistence detail.

### 2.2 Directory layout

```
/                          git root (monorepo)
  apps/
    api/                     Laravel 13 — Composer
      app/
        Domain/              pure PHP, zero Illuminate imports
          Team/              Team.php  PowerRating.php
          League/            Fixture.php  MatchResult.php  MatchOutcome.php  LeagueState.php
                             LeagueTable.php  Standing.php  TeamRecord.php
          Ranking/           Ranking.php  Comparator.php  MetricComparator.php  Direction.php
                             CompositeComparator.php  PremierLeagueRanking.php
          Support/           Guard.php
          Scheduling/        FixtureScheduler.php  BergerRoundRobinScheduler.php
          Simulation/        MatchSimulator.php  PoissonSimulator.php
                             EloSimulator.php  NaiveWeightedSimulator.php
                             GoalModel.php  PowerExponentialGoalModel.php
          Prediction/        ChampionshipPredictor.php  MonteCarloPredictor.php
                             DeterministicClincher.php  PointsHeuristicPredictor.php
                             PredictionResult.php
          Random/            RandomSource.php  SeededRandomSource.php
          Evaluation/        EvaluationHarness.php  StrategyScorecard.php
        Application/         LeagueService.php  SimulationService.php  PredictionService.php
        Infrastructure/      Persistence/ (Eloquent repos)  Registry/StrategyRegistry.php
        Http/                Controllers/  Resources/  Requests/
        Models/              Eloquent models (Team, MatchRecord, League)
      tests/                 Pest (Unit/ Feature/ Arch/)
    web/                     Vue 3 + Vite SPA — pnpm
      src/
        components/          LeagueTable.vue  WeekFixtures.vue  MatchCard.vue
                             PredictionPanel.vue  LeagueControls.vue
        stores/              Pinia league store (atomic snapshot apply, ADR 03)
        composables/         API client
        types/               generated from PHP DTOs/Resources (no hand-sync)
  docs/
  Makefile  docker-compose.yml
```

### 2.3 The strategy seam (the spine)

Two interfaces carry the whole "no single algorithm wins" thesis:

```php
interface MatchSimulator {
    public function simulate(Team $home, Team $away, RandomSource $rng): MatchResult;
    public function key(): string;          // registry id, e.g. "poisson"
}

interface ChampionshipPredictor {
    /** @return PredictionResult  team => probability (+ confidence interval) */
    public function predict(LeagueState $state, RandomSource $rng): PredictionResult;
    public function key(): string;
}
```

- Implementations are **plug-ins**, registered via container tags in a `StrategyRegistry`.
  Adding one = implement interface + tag. That single fact is the DX story.
- Baselines exist *on purpose* (`NaiveWeightedSimulator`, `PointsHeuristicPredictor`) so the
  good strategies have something to be measured against.

### 2.4 The evaluation harness (the differentiator)

```php
final readonly class EvaluationHarness {
    /** Grade each predictor on the same seeded scenarios against simulated ground truth. */
    public function compare(array $predictors, array $scenarios, int $groundTruthDraws): array;
    // $predictors: label => ChampionPredictor; returns StrategyScorecard[] sorted by Brier
    // scorecard: label, brier, logLoss, meanLatencyMs, deterministic(bool)
}
```

Because this is a simulation we **own the ground truth** — but we score against *realised
outcomes*, not a peeked-at true distribution (ADR-05). The reference model is rolled to season
end many times to draw realised champions; each predictor's estimate is graded against those
same outcomes with **Brier / log-loss** (proper rules — truth-telling minimises expected loss),
under **common random numbers** (paired, so outcome noise cancels), alongside **latency** and a
**determinism** check (same seed → identical output). Predictors arrive **labelled** rather than
carrying a registry `key()` — identity is deferred to the Phase-4 registry. Randomness is supplied
per `predict()` call, not held, so a run is reproducible from the source it is handed. This is
literally "analyse which one is better, safer, faster" and is what makes this a platform.

**Latency is a per-strategy benchmark, not an architectural special case.** Each strategy owns
its scorecard; from measured `meanLatencyMs` the harness classifies it **inline-safe vs
needs-async** against a latency budget. The active predictor on the live path is configurable —
a cheap default (`DeterministicClincher` + `PointsHeuristicPredictor`) serves inline, while a
heavy Monte Carlo is one plug-in that earns the hot path only if its scorecard says it's fast
enough. No algorithm dictates the design; the harness decides deployment fitness.

---

## 3. The four primitives (each minimal, each earns its place)

### 3.1 Seeded `RandomSource`
Single injected interface; `SeededRandomSource` wraps a Mersenne-Twister seeded per league/run.
The seed is **persisted** with the league and **logged** on every run. Buys: reproducible
matches, non-flaky tests asserting *exact* sequences, deterministic recompute, and a
"replay this league" demo.

```php
interface RandomSource {
    public function nextFloat(): float;          // [0,1)
    public function poisson(float $lambda): int; // Knuth / transformed-rejection
}
```

### 3.2 Immutable results + pure projection — *lightweight*, not full event sourcing
Source of truth is an ordered list of `MatchResult` events. The table is a pure fold:

```
LeagueTable::project(Team[] $teams, MatchResult[] $results): Standing[]
```

Editing week-2 score = replace one event + re-fold. **No point arithmetic, no mutable
standings, no add/subtract bugs.** This is the materialized-view-over-event-log pattern with
the benefit (replay/backfill) and *without* the ceremony (no event store, aggregates,
snapshots). Stated explicitly in the README as a deliberate scope cut.

### 3.3 Two-phase ranking — ordinal chain + relational mini-league
A `Ranking` strategy orders the table in two phases, because the criteria are two different
kinds: *ordinal* (a function of one team) and *relational* (a function of the tied set).

```php
interface Comparator { public function compare(Standing $a, Standing $b): int; }
interface Ranking    { public function rank(array $standings, array $results): array; }
```

1. **Ordinal pass** — sort by a parameterised comparator chain (points → GD → goals), each a
   `MetricComparator(extractor, Direction)`. Adding a rule = one declarative line. This is the
   standard total-order chain pattern.
2. **Relational pass** — head-to-head is **not** a pairwise comparator (that is non-transitive
   for 3+ tied teams and makes `usort` undefined). Instead, teams level on every ordinal metric
   are grouped, and each group is re-ranked by a **mini-league** of only the matches its members
   played against each other — which reuses the ordinal sort over the sub-results. Transitive,
   correct for cyclic ties, recursion-safe.

### 3.4 Property-based + distribution tests as guardrails
Invariants, not example rows:
- `Σ points == 3·decisiveMatches + 2·draws`
- total goals conserved across results ↔ standings
- prediction probabilities sum to 1 (± ε)
- clinched team → predictor returns 1.0 exactly (clincher path, no sampling)
- empirical win-rate over N simulations ≈ model probability within a **binomial tolerance**

This is how a stochastic system is tested without flakiness, and it mirrors guardrail metrics
on a recommender's output.

---

## 4. Algorithms

### 4.1 Match model — Poisson goals from team power
Single `power` scalar per team. A `GoalModel` maps it to expected goals (implemented as the
**power-ratio** model, ADR-04):

```
λ_home = base · (power_home / power_away) · homeAdvantage
λ_away = base · (power_away / power_home)
HomeGoals ~ Poisson(λ_home),  AwayGoals ~ Poisson(λ_away)
```

Every spec requirement falls out as an *emergent property*, not a special case: stronger team
usually wins, upsets happen with small probability (Poisson tail), home advantage and
"goalkeeper / supporter" effects are just extra multiplicative terms on λ. The single-scalar
choice is deliberate (§6); a richer attack/defense model is a *new `GoalModel`*, not a rewrite.

**Bounded-input assumption.** The ratio is multiplicative and unbounded, so powers are seeded
in a sane relative band (~40–90): a 2× gap already gives a strong favourite while keeping λ
football-realistic (≤ ~3.5). An extreme literal gap like the spec's 100-vs-10 would blow λ up
and is represented instead as e.g. 90-vs-45. A model that ingests *any* range (e.g.
`base · exp(k·Δpower)`) is a drop-in `GoalModel` swap if ever needed — the seam already exists.

### 4.2 Championship prediction — clincher + Monte Carlo
- `DeterministicClincher` runs first: pure combinatorics over remaining fixtures → returns
  teams mathematically clinched (1.0) or eliminated (0.0). Exactly answers the spec's
  "9 points ahead" example with zero sampling noise.
- `MonteCarloPredictor` simulates the remaining fixtures N≈10⁴ times via the *same*
  `MatchSimulator`, completes the table each run (composite comparator handles shared-first),
  tallies titles. Output: probability **+ Wilson confidence interval** (shows *how sure*).
- A `HybridPredictor` may compose the two (clincher short-circuit, MC for the rest) — optional.
- **Off the read path.** Predictions are recomputed on the state-changing *write* and served
  from the snapshot (ADR 01); `GET` is O(1). The MC kernel is allocation-light (int arrays, no
  per-trial value objects) over the same `GoalModel`, JIT-friendly. If a run exceeds its time
  budget it **fails fast** — never silently reduces `N`. See `docs/decisions/01-…`.

---

## 5. Deliberately NOT building (judgment, with each cut justified by a cheap-later seam)

- **No attack/defense split** — single power scalar; richer is a new `GoalModel`.
- **No CQRS / event store / snapshots** — lightweight projection covers the requirement.
- **No MLE/Bayesian self-calibration in core** — it is a `GoalModel` swap; documented as
  future work so it advertises extensibility without risking the core.
- **No Observer/event-listener for table recompute** — the projection makes "recalculate" a
  pure read; an event there would re-introduce mutable derived state (a regression). *If* an
  edit later needs fan-out (async prediction recompute, cache warm, realtime push), that is a
  **pull-vs-push** decision. *Resolved (ADR 01): the Monte Carlo cost tips this to
  **precompute-on-write** — the write recomputes predictions into a versioned snapshot; the
  queued + websocket variant stays future work behind the same `PredictionService` seam.*

---

## 6. Decisions locked

| Decision | Choice | Rationale |
|---|---|---|
| Team strength | single `power` scalar | matches spec wording; richer = pluggable GoalModel |
| Power source | seeded defaults, editable | reproducible demo + reviewer can tweak strengths and watch odds move |
| Power → λ | power-ratio model, sane band | interpretable one-liner (ADR-04); seeds kept ~40–90 so λ stays realistic |
| Calibration | out of core, documented | advertises extensibility, protects the core |
| Frontend | Vue 3 `<script setup>` + TypeScript | type-safe FE↔BE contracts reinforce rigor |
| Persistence | SQLite | zero DB ops, reproducible, fine for a demo |
| Deploy | Fly.io + Docker | Docker-native, free tier, simple custom domain |

---

## 7. Quality & testing strategy (SOTA)

1. **Unit / domain** — Pest, deterministic via seeded RNG; exact-sequence assertions.
2. **Property-based** — invariants from §3.4 (Pest datasets / hand-rolled generators).
3. **Distribution** — empirical vs theoretical within binomial tolerance.
4. **Architecture fitness** — Pest arch test (or Deptrac): `app/Domain` may not import
   `Illuminate\*`. Purity boundary enforced by CI, not discipline.
5. **Static analysis** — PHPStan/Larastan **level max**; Pint formatting.
6. **Mutation** — Infection on the domain layer to prove the suite has teeth.
7. **Feature/API** — Laravel feature tests over the REST endpoints.
8. **Frontend** — Vitest + Vue Test Utils (components/store), Playwright E2E for
   play-week / play-all / edit-recompute / prediction flows.

---

## 8. API surface (REST)

```
POST   /api/leagues                 create league (teams + seed)
GET    /api/leagues/{id}            league meta + current week
GET    /api/leagues/{id}/table      projected standings
GET    /api/leagues/{id}/fixtures   fixtures grouped by week (+ results)
POST   /api/leagues/{id}/play-week  simulate next week  → returns snapshot
POST   /api/leagues/{id}/play-all   simulate to season end → returns snapshot
PUT    /api/matches/{id}            edit a result → returns snapshot
GET    /api/leagues/{id}/predictions     championship odds (week ≥ 4)
GET    /api/leagues/{id}/evaluation      strategy scorecards (harness)
```

State-changing endpoints return one **versioned snapshot** `{ version, table, fixtures,
predictions }` from a single server state (ADR 03) — the client never stitches together
separate refetches. API Resources keep response shapes explicit and typed; FormRequests
validate input.

---

## 9. Frontend (component design)

- `LeagueTable.vue` — sortable standings, derived from API projection.
- `WeekFixtures.vue` / `MatchCard.vue` — per-week results; `MatchCard` supports inline edit.
- `PredictionPanel.vue` — appears week ≥ 4; probability bars + confidence interval.
- `LeagueControls.vue` — Play Week / Play All Season / reset (seeded).
- **Pinia** store for league state; **composables** for the API client; shared **TS types**
  generated from / mirrored against API Resources.
- **No drift by construction (ADR 03):** mutations return a versioned snapshot; the store
  applies it in one atomic `$patch` and discards out-of-order (stale-`version`) responses;
  components are pure derivations and never refetch derived data. UI is pessimistic (unified
  pending state) — cascaded predictions are not guessed optimistically.

---

## 10. Deployment & CI/CD

- **Docker** multi-stage (PHP-FPM + built Vite assets); `docker-compose` for local dev with a
  seeded demo league.
- **GitHub Actions**: `Pint → PHPStan(max) → Pest → Infection → Vitest → Playwright → build →
  deploy`. Branch protected on green.
- **Fly.io** deploy with SQLite volume; seed logged so the live demo is reproducible.
- **SQLite hardening (ADR 02):** WAL journal mode + `busy_timeout=5000` on the connection;
  tests use isolated per-worker DBs (`:memory:` / paratest); the EvaluationHarness is pure
  domain and touches no DB. Persistence behind a repository interface → Postgres is a drop-in.
- `make` targets: `make test`, `make stan`, `make eval`, `make up`.

---

## 11. Phased plan (each phase ships green tests)

| Phase | Deliverable | Acceptance |
|---|---|---|
| **1. Pure domain core** | VOs, `CompositeComparator`, `LeagueTable` projection, `BergerRoundRobinScheduler` | property tests green; 12-match double round-robin generated correctly; arch test enforces purity |
| **2. Strategy seam** | `MatchSimulator`/`ChampionshipPredictor` interfaces, `PoissonSimulator`, `MonteCarloPredictor`, `DeterministicClincher`, `SeededRandomSource` | exact-sequence tests; distribution test within tolerance; clincher returns exact 1.0/0.0 |
| **3. Evaluation harness** ⭐ | `EvaluationHarness` + `StrategyScorecard` + `ScoringRule`/`BrierScore`/`LogLoss`, shared `ChampionSampler`, `PointsHeuristicPredictor` baseline | ✅ scorecard ranks MC above the points-heuristic baseline on Brier & log-loss; clincher scores 0 once settled; determinism check passes (ADR-05) |
| **4. Laravel delivery** | migrations (`teams`, `matches`, `leagues`), repositories, registry wiring, REST + feature tests | all endpoints green; edit-recompute correct via re-fold |
| **5. Vue SPA** | the 5 components, Pinia store, inline edit, prediction panel (week ≥ 4) | Playwright E2E green; edits reflect immediately |
| **6. CI/CD + deploy** | Docker, Actions pipeline, live link | pipeline green end-to-end; public URL works |

⭐ Phase 3 is the differentiator — it is what turns "a football app" into "a platform that
compares algorithms." Do not skip it.

---

## 12. Future work (documented seams, not built)

- MLE / Bayesian (Dixon–Coles) self-calibration of team strengths from played results — a
  `GoalModel` swap.
- Dixon–Coles low-score correlation correction (τ for 0-0/1-0/0-1/1-1).
- Queued/async prediction recompute + websocket push (moving the ADR-01 precompute off the
  request thread) when write latency or realtime needs justify it — same `PredictionService` seam.
- Online champion/challenger split + guardrail auto-rollback — the direct analogue of the
  unicorn's experimentation layer.
