# Champions League Web

Vue 3 + TypeScript SPA for the league simulation API.

The client renders API snapshots:

```ts
{ version, league, table, fixtures, predictions }
```

It does not compute standings, fixtures, predictions, or season state. Mutations apply the returned
snapshot atomically through Pinia after Zod validation.

## Routes

- `/leagues/create` creates a fixed four-team league.
- `/leagues/:id/simulation` shows fixtures, standings, predictions, match correction, and strategy
  evaluation.

Route definitions live in `src/router.ts`. Views use API and view-model modules rather than owning
transport details.

## Setup

```sh
pnpm install
pnpm dev
```

By default, the SPA calls `/api/*` on the same origin. In development, Vite proxies `/api` to
`http://127.0.0.1:8000`.

Point the dev proxy at another API:

```sh
VITE_API_PROXY_TARGET=http://127.0.0.1:9000 pnpm dev
```

Build against a direct API origin:

```sh
VITE_API_BASE_URL=https://example.com pnpm build
```

## Test

```sh
pnpm test
pnpm test:coverage
pnpm test:e2e
```

Vitest covers domain helpers, store behavior, view models, and Zod API schemas. Playwright builds
and previews the app, then runs browser flows against mocked API responses in `e2e/fixtures.ts`.

## Build

```sh
pnpm build
pnpm preview
```
