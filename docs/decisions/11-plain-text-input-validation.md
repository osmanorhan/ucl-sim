# 11 — Free-text inputs are constrained to a plain-text allowlist

**Status:** accepted · **Date:** 2026-06-18

## Context

The league create endpoint accepts three operator-supplied strings — the league `name` and each
team's `id` and `name` — that were validated only for type and length. Anything within 255
characters was persisted verbatim and later echoed back in the snapshot the SPA renders, so
`<script>…`, raw markup, and stray control characters could ride straight through the API. Length
limits are not an input contract; "fail fast at the boundary" (CLAUDE.md) wants the *shape* of the
text pinned down where it enters, not patched where it leaves.

## Decision

**Reject anything that isn't plain human-readable text, with an allowlist applied at the request
boundary.**

- A reusable `App\Rules\PlainText` rule (`ValidationRule`) permits Unicode letters, digits,
  combining marks, whitespace and a small punctuation set (`. , ' & ( ) -`) and fails everything
  else. The allowlist is expressed as one anchored `\p{…}` pattern — a seam, not an `if` ladder —
  so accented and multi-script names ("Bayern München", "Borussia M'gladbach") pass while angle
  brackets and slashes cannot.
- `name` and `teams.*.name` carry the `PlainText` rule. `teams.*.id` is an identifier, not prose,
  so it uses the stricter built-in `alpha_dash:ascii`.
- New free-text fields opt in by adding `new PlainText` to their rules — no edits to the rule
  itself (open for extension).

## Consequences

- Markup and control characters are rejected with a `422` before persistence; proven by feature
  tests covering an HTML league name, a marked-up team name, and an accented/punctuated name that
  must still be accepted.
- The allowlist is deliberately conservative; a future field needing characters outside the set
  (e.g. `/` or `:`) widens the pattern in one place, recorded as a superseding note here.
- Validation only guards the write path. Output encoding remains the renderer's job — this decision
  removes the dangerous input, it does not replace contextual escaping in the SPA.
