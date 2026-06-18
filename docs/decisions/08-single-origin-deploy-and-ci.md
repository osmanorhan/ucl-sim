# 08 — One same-origin container, shipped by CI to Fly

**Status:** accepted · **Date:** 2026-06-17

## Context

Phase 6 needs a public URL and a pipeline that proves green before shipping. SQLite's single-writer
model (ADR-02/06) already says the API is one machine with one disk, not a scaled-out tier.

## Decision

**The SPA and API ship as one same-origin container, gated by CI.** The Vite build is copied into
Laravel's `public/`; one FrankenPHP process serves assets directly and routes the rest through
`index.php`.

- **Same origin removes CORS.** The client is built with `VITE_API_BASE_URL=""` and calls `/api/*`
  relative — no preflight, no second hostname. Same-origin is also the *default*: `client.ts` falls
  back to `""` when the var is unset, so a forgotten env in a preview/deploy variant degrades to the
  correct production behaviour, never to a hard-coded `localhost`. Dev keeps zero-config by proxying
  `/api` from the Vite server to the local API (`VITE_API_PROXY_TARGET`, default `127.0.0.1:8000`),
  so the SPA speaks same-origin everywhere and only an explicit cross-origin build opts out.
- **Laravel owns SPA routing via one fallback** (`routes/web.php`): `/` and non-`api/*` paths return
  `index.html`; unknown `/api/*` still 404s as JSON.
- **Environment-agnostic image.** Only build-inlined (`VITE_API_BASE_URL`) and the listen contract
  (`SERVER_NAME`) are baked; `APP_ENV`, `DB_DATABASE`, and the `array`/`sync` drivers come from the
  runtime (`fly.toml [env]`, compose) — one artifact, config at the edges (12-factor).
- **No committed secret.** The entrypoint fails fast if `APP_KEY` is missing in production (from
  `fly secrets`) and mints an ephemeral one only for `local`/`testing`.
- **SQLite on a Fly volume; one machine.** Boot-time `migrate --force` makes redeploys
  self-applying; `array`/`sync` drivers keep the league domain the only writer (ADR-02).
- **CI gates the artifact, not just the code.** `ci.yml` runs API (`lint`/`stan`/`test`), web
  (`vitest`/`build`/Playwright E2E), and an **image** job that builds the Dockerfile, boots it, and
  runs `infra/smoke.sh` (health, SPA, fallback, assets, `/api`). `deploy` needs all three and only
  fires on push to `main`.

## Consequences

- The live link is the same image developers run via `make up`.
- A web-only change rebuilds the whole image — fine at this size; the two-service split stays the
  documented escape hatch.
- Deploy needs a `FLY_API_TOKEN` secret + one-time `fly launch`/volume; until then `deploy` is the
  only unproven link — the test/build/image jobs stand alone.
