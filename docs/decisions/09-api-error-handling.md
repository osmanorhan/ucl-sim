# 09 — Errors fail loudly server-side, gently client-side

**Status:** accepted · **Date:** 2026-06-18

## Context

"Fail fast; never hide errors" is a binding principle (CLAUDE.md). It is about *not letting a broken
state masquerade as success* — not about spraying internals at whoever called the API. Two gaps
worked against it:

- With `APP_DEBUG=true`, Laravel's `convertExceptionToArray` renders the full `{exception, file,
  line, trace}` as JSON for *any* exception it isn't told to handle — including `abort()`
  HttpExceptions. Only the two domain exceptions (`LeagueNotFound`, `SeasonComplete`) were rendered
  cleanly; every other failure leaked a stack trace to the SPA and to direct callers.
- On the client, a malformed but `200 OK` body would reach `Zod.parse`, whose `ZodError.message` is a
  verbose multi-line dump — a stack-trace-shaped leak into `store.error`, straight onto the screen.

## Decision

**Surface failure as a clean, actionable message at every boundary; keep the diagnostic detail
server-side.**

- **One catch-all render for `api/*`** (`bootstrap/app.php`) returns trace-free `{message}` JSON
  *regardless of `APP_DEBUG`*: an `HttpExceptionInterface` keeps its status and message; anything
  unexpected becomes a generic `500`. The domain renders (404/409) still win by registration order.
  `ValidationException` is deliberately let through — Laravel's `{message, errors}` 422 is already
  trace-free and the client's `errorMessage` unpacks it.
- **Unexpected failures are reported once, with request context** (`method`, `path`) via a
  `report(...)->stop()` callback — the small monitoring seam. The two domain exceptions are
  `dontReport`ed: they are expected control flow, not incidents, so they never pollute the error log.
- **The client decodes through `safeParse`** (`api/client.ts`): a shape drift on a success response
  throws a fixed "unexpected response" message with the `ZodError` preserved as `cause`, never the
  dump. The fetch-reject path likewise keeps the original error as `cause`.

## Consequences

- A stack trace can no longer reach the SPA or a `curl`, in any environment — proven by a feature
  test that forces `app.debug = true` and asserts the `500` body carries `message` only.
- Errors are still loud where it counts: logged server-side with full trace and request context, and
  the boundary still *rejects* bad state rather than rendering half of it.
- Debug-mode traces are gone from API responses; read them in `storage/logs` (or via `php artisan
  pail`), which is where a server error belongs.
- New monitoring (Sentry, a webhook) drops into the single `report` callback without touching
  controllers or the client.
