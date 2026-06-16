# 00 — Record architecture decisions

**Status:** accepted · **Date:** 2026-06-16

## Context

The project is built to be modified for years by people (and a future self with a bad memory).
Decisions that shape the system — patterns chosen, scope deliberately cut, tradeoffs accepted —
are invisible in the final code unless captured. Reconstructing *why* later is expensive and
error-prone, and leads to re-litigating settled questions or reverting deliberate cuts.

## Decision

Record every non-trivial decision as a short ADR in `docs/decisions/`, named
`NN-kebab-title.md` (zero-padded sequence starting at `00`). Each ADR carries **Status**,
**Date**, **Context**, **Decision**, **Consequences**. Write the ADR in the same change that
makes the decision. Keep them short. To change a past decision, add a new ADR and mark the old
one `superseded by NN`; never silently rewrite history.

## Consequences

- The "why" survives independently of the code and of memory.
- Reviewers and future contributors get the rationale without archaeology.
- A small, standing discipline: one short file per decision, no heavyweight process.
- `docs/plan.md` holds the living design; ADRs hold the point-in-time decisions behind it.
