#!/usr/bin/env bash
# Runs every Flux GraphQL test: polyfills (plain PHP), then a test site (site.sh, fixtures on) with
# the kit contract tests and the HTTP tests. Exit code is non-zero when any test fails.
#
# Usage: tools/tests/wp-graphql/run.sh [port]   (environment: see site.sh)
set -uo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
port="${1:-8890}"
site="${TEST_ROOT:-/tmp/flx-test}/gql-site"
WP="${WP_CLI:-wp} --allow-root"
fail=0

php "$here/polyfills-test.php" || fail=1

FIXTURES=1 SERVE=1 bash "$here/site.sh" "$site" "$port" || exit 1
trap 'kill "$(cat "$site/.server.pid" 2>/dev/null)" 2>/dev/null || true' EXIT

$WP eval-file "$here/kit-test.php" --path="$site" || fail=1
# Package contract tests: tools/tests/wp-graphql/<slug>/contract-test.php
for t in "$here"/*/contract-test.php; do
  [ -f "$t" ] || continue
  # flx-lang scripts its own Flux AI stand-in (flxai_* functions): load the real flx-ai out of that run.
  skip=""; [ "$(basename "$(dirname "$t")")" = "flx-lang" ] && skip="--skip-plugins=flx-ai"
  $WP eval-file "$t" --path="$site" $skip || fail=1
done
bash "$here/http-test.sh" "$site" "http://127.0.0.1:$port" || fail=1
# Package HTTP tests: tools/tests/wp-graphql/<slug>/http-test.sh <site> <url>
for t in "$here"/*/http-test.sh; do
  [ -f "$t" ] && { bash "$t" "$site" "http://127.0.0.1:$port" || fail=1; }
done

if [ -s "$site/wp-content/debug.log" ] && grep -qE 'PHP (Fatal|Parse) error' "$site/wp-content/debug.log"; then
  echo "::error::PHP fatal in debug.log"; grep -E 'PHP (Fatal|Parse) error' "$site/wp-content/debug.log" | head -5; fail=1
fi
[ "$fail" = 0 ] && echo "All Flux GraphQL tests passed." || echo "Flux GraphQL tests failed."
exit "$fail"
