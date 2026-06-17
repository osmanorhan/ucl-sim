# Champions League Simulation

A four-team Champions League group simulated under Premier League rules. Scoring and championship-prediction strategies
are pluggable behind seams, and an **evaluation harness** grades them against each other
(better / safer / faster) with proper scoring rules. The thesis: no single algorithm always wins,
so the interesting artifact is the harness that compares them.

Design rationale lives in [`docs/plan.md`](docs/plan.md); every non-trivial decision is an ADR in
[`docs/decisions/`](docs/decisions/). Engineering principles are in [`CLAUDE.md`](CLAUDE.md).

**Stack:** PHP 8.5 (≥8.3) / Laravel 13 (API-only) · Vue 3 + TypeScript (SPA) · SQLite · Pest 4 ·
PHPStan max · Pint.

---

## Architecture

A pure domain core with side effects pushed to the edges:

```
app/Domain/          pure PHP, zero Illuminate imports (enforced by an arch test)
  Team/ League/ Ranking/ Scheduling/ Simulation/ Prediction/ Evaluation/ Random/ Persistence/
app/Application/      use-cases: LeagueService, SnapshotAssembler, StrategyEvaluator
app/Infrastructure/  Eloquent repository, StrategyRegistry
app/Http/            controllers, FormRequests
app/Models/          Eloquent models (League, Team, MatchRecord)
```

Load-bearing seams:

- **`ChampionPredictor`** — `MonteCarloPredictor`, `DeterministicClincher`,
  `PointsHeuristicPredictor` (baseline), and `SettledOrSimulated` (the live decorator: exact when
  the title is decided, simulated otherwise).
- **`EvaluationHarness`** — scores predictors against *realised* outcomes (Brier + log-loss) under
  common random numbers; this is the differentiator (ADR-05).
- **`LeagueRepository`** — persistence behind an interface; SQLite today, Postgres is a drop-in
  (ADR-02).

Two invariants worth calling out:

- **Facts are the source of truth; views are re-folded.** Only match results are stored. The table,
  fixtures, and predictions are pure projections recomputed on each write into one versioned
  snapshot, so an edit can never leave them disagreeing (ADR-01/03/06).
- **`play-week × N ≡ play-all`.** Each week is simulated from a source seeded purely on
  `(seed, week)`, so incremental and one-shot play are byte-identical and reproducible from the
  league seed (ADR-06).

---

## Getting started

```bash
make install                      # composer (api) + pnpm (web) deps

cd apps/api
cp .env.example .env              # first run only
php artisan key:generate
php artisan migrate
php artisan serve                 # http://localhost:8000  (API under /api)
```

---

## API surface

State-changing endpoints return one atomic, versioned snapshot
`{ version, league, table, fixtures, predictions }` from a single server state (ADR-03).

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/api/leagues` | create a league (`name`, `seed`, even list of `teams`) → snapshot |
| `GET`  | `/api/leagues/{id}` | full snapshot |
| `GET`  | `/api/leagues/{id}/table` | projected standings |
| `GET`  | `/api/leagues/{id}/fixtures` | fixtures grouped by week (+ results) |
| `GET`  | `/api/leagues/{id}/predictions` | championship odds — `409` until week ≥ 4 |
| `GET`  | `/api/leagues/{id}/evaluation` | strategy scorecards from the harness |
| `POST` | `/api/leagues/{id}/play-week` | simulate the next week → snapshot |
| `POST` | `/api/leagues/{id}/play-all` | simulate to season end → snapshot |
| `PUT`  | `/api/matches/{id}` | edit a result (`homeGoals`, `awayGoals`) → snapshot |

A ready-to-run **Bruno** collection for all of these lives in [`bruno/`](bruno/) — open the folder
in [Bruno](https://www.usebruno.com), pick the **Local** environment, and run the requests top to
bottom (the create request captures `leagueId`/`matchId` for the rest).

---

## Testing

```bash
make test          # api (domain + feature) and web
make stan          # PHPStan, level max
make arch          # domain-purity fitness test
make lint          # Pint (format check)
```

- **Domain / unit** — Pest, deterministic via seeded RNG; exact-sequence and distribution assertions.
- **Feature / API** — `apps/api/tests/Feature/LeagueApiTest.php`: create, play-week, the
  `play-week ≡ play-all` invariant, edit re-fold, prediction gating, evaluation ranking, 404/409,
  and validation.
- **Architecture** — `app/Domain` may not import `Illuminate\*`; enforced in CI, not by discipline.

---

## Project layout

```
apps/api/   Laravel 13 API (Composer)
apps/web/   Vue 3 + Vite SPA (pnpm)
bruno/      Bruno API collection
docs/       plan.md + decisions/ (ADRs)
```
