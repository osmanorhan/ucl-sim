# Champions League Web

Vue 3 + TypeScript SPA for the league simulation API.

The app consumes the API's versioned snapshot:

```ts
{ version, league, table, fixtures, predictions }
```

The client does not fold standings or predict derived state. Mutations apply the returned
snapshot atomically through the Pinia store.

## Routes

- `/leagues/create` creates a league. Teams can be added or removed (any even count ≥ 2); the
  payload schema mirrors the API's even-count and unique-id rules.
- `/leagues/:id/simulation` runs simulation, edits results, shows predictions, and runs evaluation.
  Only *played* matches expose a score editor — a manual result is a correction of a recorded
  one, never a way to fabricate a fixture out of order.

Route resolution is isolated in `src/router.ts`; route views use view-model composables and do
not own API details.

## Run

```sh
pnpm install
pnpm dev
```

By default the SPA calls `http://127.0.0.1:8000`.

Override with:

```sh
VITE_API_BASE_URL=http://127.0.0.1:8000 pnpm dev
```

## Test

```sh
pnpm test       # Vitest — pure seams
pnpm test:e2e   # Playwright — flows against a mocked API
```

Vitest covers the pure seams: the store's version-monotonicity guard, the week-state machine,
and the Zod boundary schemas (including rejection of an unknown match origin).

Playwright drives the built app against a mocked API (`e2e/fixtures.ts`) so the snapshots stay
deterministic and no PHP backend is needed. The mocked responses still flow through the app's
real Zod parse, so a mock that drifts from the contract fails like the real thing would. It
covers the happy path (create → play → odds at week ≥ 4 → benchmark) and the edge cases:
editing only corrects a played match, a server error surfaces as a toast without crashing, a
contract-breaking response fails at the boundary, and an odd squad is blocked before any
request is sent.

## Build

```sh
pnpm build
```
