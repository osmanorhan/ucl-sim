# Champions League API

Laravel 13 API for the four-team Champions League simulation.

The API owns league rules, persistence, simulation, prediction, and evaluation. Clients receive
server-built snapshots and should not recompute standings, fixtures, or odds.

## Architecture

- `app/Domain` contains pure PHP league, ranking, scheduling, simulation, prediction, evaluation,
  and random logic. It must not import Illuminate classes.
- `app/Application` coordinates use cases and snapshot assembly.
- `app/Infrastructure` adapts the domain to Eloquent persistence and strategy registration.
- `app/Http` contains controllers and request validation.
- `app/Models` contains Eloquent records for leagues, teams, and matches.

Facts are the source of truth. Match results are stored; table, fixtures, predictions, and the
versioned snapshot are projected from those facts.

## API

All routes are under `/api`.

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/leagues` | Create a league from `name`, `seed`, and exactly four teams. |
| `GET` | `/leagues/{id}` | Return the full snapshot. |
| `GET` | `/leagues/{id}/table` | Return projected standings. |
| `GET` | `/leagues/{id}/fixtures` | Return fixtures and results. |
| `GET` | `/leagues/{id}/predictions` | Return championship odds; `409` until four weeks are complete. |
| `GET` | `/leagues/{id}/evaluation` | Return strategy scorecards. |
| `POST` | `/leagues/{id}/play-week` | Simulate the next week. |
| `POST` | `/leagues/{id}/play-all` | Simulate the rest of the season. |
| `PUT` | `/matches/{id}` | Correct a played match result. |

State-changing endpoints return one atomic snapshot:

```json
{ "version": 1, "league": {}, "table": [], "fixtures": [], "predictions": null }
```

## Setup

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

The local API is served at `http://127.0.0.1:8000/api`.

Useful config:

- `LEAGUE_PREDICTION_ITERATIONS` controls live Monte Carlo prediction work.
- `LEAGUE_EVALUATION_SCENARIOS` and `LEAGUE_EVALUATION_DRAWS` control evaluation cost.

## Test

```sh
composer test
composer test:coverage
composer stan
composer lint
composer test -- --group=arch
```

Pest covers domain rules, API flows, deterministic simulation, edits, prediction gating, and
evaluation. The arch test enforces the pure-domain boundary.
