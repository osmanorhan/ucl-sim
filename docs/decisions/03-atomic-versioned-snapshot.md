# 03 — Cascade mutations return one atomic, versioned snapshot

**Status:** accepted · **Date:** 2026-06-16

## Context

Editing a past result invalidates the table, the fixtures, and the predictions for every
subsequent week. If the SPA refetches those independently after a mutation, it shows a
fractured reality (table updates while the prediction panel still shows stale odds), and the
separate responses are subject to out-of-order races.

## Decision

- State-changing endpoints (`play-week`, `play-all`, edit) return a **single consistent
  snapshot** `{ version, table, fixtures, predictions }` computed from one server state.
- The Pinia store applies the snapshot in **one atomic `$patch`**. Components are **pure
  derivations** of the store — they never fetch derived data themselves.
- The store holds a **monotonic `version`** and **discards any response older than the current
  version**, eliminating out-of-order write races.
- UI is **pessimistic**: a unified pending state during the in-flight mutation. Cascaded
  predictions are not guessed optimistically.

## Consequences

- State drift is impossible by construction, not by careful sequencing.
- One round trip per mutation; the snapshot is exactly the read model from ADR 01.
- The `version` is the shared key tying the write (ADR 01) to the client cache.
