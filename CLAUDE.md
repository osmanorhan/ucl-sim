# CLAUDE.md

Champions League simulation - *miniature platform*: pluggable, comparable scoring/ranking strategies, not a single
"best" algorithm. Full design in [`docs/plan.md`](docs/plan.md). Decisions in
[`docs/decisions/`](docs/decisions/).

**Stack:** PHP 8.5 (≥8.3) / Laravel 13 · Vue 3 + TypeScript · SQLite · Docker · GitHub Actions.

---

## Engineering principles

These are binding. When a change conflicts with one, stop and raise it — don't quietly trade
it away.

**Design before code.** Validate the technical design is correct before writing any. Solve the
problem *behind* the feature, not just the feature. Chop every problem into pieces that are
each easy to understand and solve on their own.

**Write as little code as possible.** Prefer fewer lines over large classes/methods. Code is
written to be *changed and deleted* — optimise for deletability. Every abstraction must earn
its place; cut what doesn't.

**Open for extension, closed for modification.** Always. New behaviour arrives as a new
implementation behind an existing seam, not an edit to working code.

**Patterns/structures over conditionals.** Replace `if/else` branching on type/strategy with
polymorphism, strategy objects, comparators, lookups. Branching logic is a design smell to
resolve, not accumulate.

**Fail fast; never hide errors.** No monkeypatching, no workarounds. If something is broken,
break loudly. No fallbacks unless explicitly requested. Surface errors; never swallow them.

**Handle side effects deliberately.** Keep the core pure (the `Domain` layer imports no
framework). Push I/O, randomness, and persistence to the edges behind interfaces. A side
effect should be visible in the type/seam, never a surprise.

**Simplicity over easiness.** The simple solution beats the convenient one. "It works" and
"working code" are not the bar — correct, clear, and changeable is the bar.

**Code for others.** Including future-me, who has a bad memory and works with other people.
Artifacts must be self-explanatory, debuggable, and deletable. Beauty has a place — readable,
well-shaped code is a deliverable, not a luxury. Nurse the codebase like a garden: leave it
healthier than you found it.

**Know when to trade off.** Strive for high quality; when a tradeoff is genuinely required,
make it consciously and record *why* (see decisions, below).

---

## House style

- **No code comments** explaining *what* — the structure and names carry it. Comments only for
  a non-obvious *why* that the code cannot express, and even then sparingly.
- Small, single-responsibility units. If a method needs a comment to be readable, split it.
- Names state intent. Types and value objects over primitives and arrays.
- The `Domain` layer is pure PHP; importing `Illuminate\*` into it is a build failure
  (enforced by an architecture test, not by discipline).
- Tests assert *invariants* and *exact seeded sequences*, never luck. Stochastic code is
  tested with seeded RNG + distribution tolerances.

---

## Decisions (ADR)

Every non-trivial decision is recorded as a short ADR in `docs/decisions/`, named
`NN-kebab-title.md` (zero-padded, e.g. `00-record-architecture-decisions.md`). When we make a
decision — pick a pattern, cut scope, accept a tradeoff — write the ADR in the same change.
Keep them short: context, decision, consequences. Supersede rather than rewrite.

---

## Working agreement for Claude

- Confirm the design is sound before editing code; if the request fights a principle above,
  say so first.
- Prefer the smallest change that solves the problem behind the request.
- When introducing a pattern/seam, note it; when cutting scope, record the ADR.
- Run the relevant checks (tests, PHPStan, Pint) before claiming something works. Report
  failures honestly with output.
