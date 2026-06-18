# Architecture Diagrams

This document expands the README architecture summary with the decision-level flows behind the app.

## Mutation and Snapshot Flow

```mermaid
sequenceDiagram
  participant UI as Vue / Pinia
  participant API as Laravel API
  participant App as LeagueService
  participant Domain as Pure domain
  participant Repo as Repository
  participant DB as SQLite

  UI->>API: play-week / play-all / edit result
  API->>App: validated command
  App->>Repo: load LeagueState
  Repo->>DB: read league, teams, ordered matches
  DB-->>Repo: persisted facts
  Repo-->>App: LeagueState
  App->>Domain: schedule / simulate / rank / predict
  Domain-->>App: derived table, fixtures, odds
  App->>Repo: save facts + versioned snapshot
  Repo->>DB: one transaction
  App-->>API: snapshot
  API-->>UI: { version, league, table, fixtures, predictions }
  UI->>UI: discard older version; atomically apply newest snapshot
```

Mutations return one snapshot from one server state. The SPA applies it atomically and ignores older
versions, so table, fixtures, and predictions cannot drift.

## Strategy Seams

`ChampionPredictor` is the one seam that matters: every strategy is a single class behind it, and
the system depends on the interface, never a concrete predictor. It is consumed two ways.

```mermaid
flowchart LR
  Seam[[ChampionPredictor]]

  Settled[SettledOrSimulated] -. implements .-> Seam
  Clincher[DeterministicClincher] -. implements .-> Seam
  MonteCarlo[MonteCarloPredictor] -. implements .-> Seam
  Heuristic[PointsHeuristicPredictor] -. implements .-> Seam

  Live[Live read path] -->|bound to| Settled
  Settled -->|1 · exact, when clinched| Clincher
  Settled -->|2 · else, sample| MonteCarlo

  Eval[Evaluation endpoint] -->|enumerates whole field| Registry[StrategyRegistry]
  Registry --> MonteCarlo
  Registry --> Clincher
  Registry --> Heuristic
```

The live read path binds the interface to `SettledOrSimulated` — a *selecting strategy*: a
predicate-guarded chain of responsibility (not a decorator) that asks the exact
`DeterministicClincher` first and falls through to `MonteCarloPredictor` only when no team is
*mathematically* certain. That trigger is a property of the standings, not the calendar, so a title
can short-circuit to an exact `1.0` several weeks early — and at season end, where nothing is left
to sample, the answer is always exact. Because it implements `ChampionPredictor` itself, the chain
is just another strategy and drops into the binding transparently.

The evaluation endpoint instead enumerates the whole field through `StrategyRegistry` — adding a
strategy is a new class plus one line there. `PointsHeuristicPredictor` is a deliberately weak
baseline for that bake-off, not a hidden production fallback.

The simulated predictor is itself assembled from smaller swappable seams — not a monolith:

```mermaid
flowchart LR
  MonteCarlo[MonteCarloPredictor] --> Factory[ChampionSamplerFactory]
  Factory --> SeasonSim[SeasonSimulator]
  Factory --> Table[LeagueTable]
  Factory --> Ranking[[Ranking]]
  SeasonSim --> MatchSim[[MatchSimulator]]
  MatchSim --> GoalModel[[GoalModel]]

  Clincher[DeterministicClincher] --> Table
  Heuristic[PointsHeuristicPredictor] --> Table

  Ranking -. bound to .-> PL[PremierLeagueRanking]
  MatchSim -. bound to .-> PMS[PoissonMatchSimulator]
  GoalModel -. bound to .-> PGM[PoissonGoalModel]
```

`ChampionSamplerFactory` compiles a reusable sampler once, composing the `Ranking`, `LeagueTable`,
and the `SeasonSimulator` → `MatchSimulator` → `GoalModel` chain — each an interface (Poisson and
Premier League are today's bindings). Randomness is intentionally absent: a `RandomSource` is passed
in at `predict()` time, never wired as a structural dependency.

## Predictor Decision Flow

```mermaid
flowchart TD
  Input[teams + played matches + remaining fixtures + seed] --> Live[SettledOrSimulated]
  Live --> Clincher[DeterministicClincher]
  Clincher --> Ceiling[Compute max attainable points per team]
  Ceiling --> Certain{single team at 1.0?}
  Certain -- yes --> Exact[Return exact certainty]
  Certain -- no --> MonteCarlo[MonteCarloPredictor]
  MonteCarlo --> Compile[Compile champion sampler once]
  Compile --> Draw[Draw remaining season N times]
  Draw --> Rank[Rank each completed season]
  Rank --> Count[Count champions]
  Count --> Odds[Return normalized champion probabilities]
```

The live strategy uses exact math when the league is settled and sampling only when uncertainty
remains. Monte Carlo completes the remaining season repeatedly, ranks each sampled season, and
normalizes champion counts into probabilities.

## Evaluation Harness

```mermaid
flowchart TD
  State[Current LeagueState] --> Scenarios[Decorrelated seeded scenarios]
  Registry[StrategyRegistry all predictors] --> Compare[EvaluationHarness.compare]
  Scenarios --> Truth[Ground-truth champion draws]
  Truth --> Compare
  Compare --> Forecast[Run each predictor per scenario]
  Forecast --> Brier[Brier score]
  Forecast --> LogLoss[Log-loss]
  Forecast --> Latency[mean latency ms]
  Forecast --> Determinism[same seed gives same output]
  Brier --> Scorecard[StrategyScorecard]
  LogLoss --> Scorecard
  Latency --> Scorecard
  Determinism --> Scorecard
  Scorecard --> Sort[Sort best by lowest Brier]
```

The harness scores predictors against realised outcomes from the reference sampler. Common random
numbers make the comparison paired; proper scoring rules reward honest probabilities; latency and
determinism make "better / safer / faster" visible.

## Persistence Model

```mermaid
erDiagram
  leagues ||--o{ teams : owns
  leagues ||--o{ matches : schedules
  teams ||--o{ matches : home_team
  teams ||--o{ matches : away_team

  leagues {
    uuid id PK
    string name
    bigint seed
    int version
    json snapshot
  }
  teams {
    uuid id PK
    uuid league_id FK
    string team_id
    string name
    float power
  }
  matches {
    uuid id PK
    uuid league_id FK
    int sequence
    int week
    string home_team_id
    string away_team_id
    int home_goals
    int away_goals
    string origin
  }
```

The stored facts are league identity, roster, seed, fixture order, and optional results. Tables,
fixtures view, predictions, and scorecards are projections; edits overwrite one result and re-fold.
