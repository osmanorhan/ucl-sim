# 10 — League writes use optimistic concurrency

**Status:** accepted · **Date:** 2026-06-18

## Context

The API returns monotonic snapshot versions, and the SPA discards older responses. The database
write path did not enforce the same rule: `save` overwrote by id, so two writers loaded from the
same version could silently lose one update.

## Decision

League creation and mutation are separate repository operations. Creation inserts a new aggregate.
Mutation updates the league row only when the stored version is exactly the aggregate's previous
version; otherwise it fails with a stale-state conflict.

## Consequences

- The version column is now the write contract, not only a client cache hint.
- Lost updates fail loudly as `409 Conflict`.
- SQLite remains acceptable for the single-machine demo; moving to Postgres keeps the same seam.
