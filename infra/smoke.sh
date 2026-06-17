#!/bin/sh
# Smoke-test the production image: assert every serving class the single-origin
# container is responsible for. Run against an already-booted container.
#   infra/smoke.sh [base-url]   (default http://localhost:8080)
set -eu

BASE="${1:-http://localhost:8080}"
fail=0

assert_code() {
  name="$1"; want="$2"; url="$3"; shift 3
  got="$(curl -s -o /dev/null -w '%{http_code}' "$@" "$url" || echo 000)"
  if [ "$got" = "$want" ]; then
    echo "ok   $name -> $got"
  else
    echo "FAIL $name -> $got (want $want)  $url"; fail=1
  fi
}

assert_body() {
  name="$1"; needle="$2"; url="$3"; shift 3
  if curl -s "$@" "$url" | grep -q "$needle"; then
    echo "ok   $name"
  else
    echo "FAIL $name (missing '$needle')  $url"; fail=1
  fi
}

# Health (Fly's check) and the API surface.
assert_code "health /up"          200 "$BASE/up"
assert_code "create league"       201 "$BASE/api/leagues" \
  -X POST -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"name":"CL","seed":42,"teams":[{"id":"a","name":"Alpha","power":90},{"id":"b","name":"Bravo","power":65},{"id":"c","name":"Cosmos","power":45},{"id":"d","name":"Delta","power":30}]}'
assert_code "api unknown -> 404"  404 "$BASE/api/nope" -H 'Accept: application/json'

# Same-origin SPA: root and deep links must resolve to the app, not the API.
assert_body "spa root is the app"  '<div id="app"' "$BASE/"
assert_body "deep-link fallback"   '<div id="app"' "$BASE/leagues/x/simulation"

# A hashed asset must serve statically (FrankenPHP serves it before PHP).
asset="$(curl -s "$BASE/" | grep -o '/assets/[^\"]*\.js' | head -1)"
if [ -n "$asset" ]; then
  assert_code "static asset"       200 "$BASE$asset"
else
  echo "FAIL static asset -> none found in /"; fail=1
fi

exit "$fail"
