# 02 — SQLite for the demo, hardened for concurrency; Postgres a drop-in

**Status:** accepted · **Date:** 2026-06-16

## Context

SQLite was chosen for zero-ops portability and a reproducible demo. Its file-level locking can
raise `database is locked` (`SQLITE_BUSY`) under concurrent writes — parallel UI requests, or
the EvaluationHarness running in CI.

## Decision

- Enable **WAL journal mode** and **`busy_timeout=5000`** on the sqlite connection. WAL lets
  readers proceed during a write; the busy timeout makes a contended write wait rather than
  throw immediately.
- The **EvaluationHarness is pure domain** — it operates on in-memory `LeagueState` and touches
  no database — so it cannot contend regardless of concurrency.
- Tests use **isolated per-worker databases** (paratest workers / `:memory:`), never a shared
  file, so the suite parallelises without lock contention.
- Persistence stays behind a **repository interface**. Moving to Postgres for real concurrency
  is a config + driver change, not a code change.

## Consequences

- The single-writer demo is safe; CI parallelism is a non-issue by construction.
- The scale path (Postgres) is open without rework.
- Connection pragmas live in config, not scattered through code.
