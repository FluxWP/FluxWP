#!/usr/bin/env bash
# Flux Donate GraphQL over HTTP: cache headers of the personal read (the denial included) and of the
# public types list, with the persisted operations the clients send.
# Usage: tools/tests/wp-graphql/flx-donate/http-test.sh <site_dir> <url>
set -uo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
root="$(cd "$here/../../../.." && pwd)"
site="$1"; url="$2"
WP="${WP_CLI:-wp} --allow-root --path=$site"
body="$(mktemp)"; hdr="$(mktemp)"
trap 'rm -f "$body" "$hdr"' EXIT
pass=0; fail=0
ok()  { pass=$((pass+1)); echo "  ok   $1"; }
bad() { fail=$((fail+1)); echo "  FAIL $1"; [ -n "${2:-}" ] && echo "       $(head -c 600 "$2")"; }
qid() { php -r 'foreach ( json_decode( file_get_contents( $argv[1] ), true ) as $k => $d ) { if ( preg_match( "/^(?:query|mutation) " . $argv[2] . "\\b/", $d ) ) { echo $k; } }' "$root/plugins/flx-donate/graphql/persisted-queries.json" "$1"; }

echo "Flux Donate GraphQL over HTTP"
$WP option update flxgql_settings '{"query_mode":"strict"}' --format=json >/dev/null 2>&1 || true

# The personal read is never stored; the kit's cap check throws before the resolver runs.
curl -s -D "$hdr" -o "$body" "$url/graphql?queryId=$(qid FlxMyDonations)"
grep -q UNAUTHENTICATED "$body" && ok "my donations: an anonymous GET is denied" || bad "my donations: anonymous GET is denied" "$body"
grep -qi '^cache-control: no-store, private' "$hdr" && ok "my donations: the denial is never cached" || bad "my donations: denial Cache-Control" "$hdr"
grep -qi '^x-litespeed-cache-control: no-cache' "$hdr" && ok "my donations: LiteSpeed does not store the denial" || bad "my donations: denial LiteSpeed header" "$hdr"
! grep -qi '^x-litespeed-tag:' "$hdr" && ok "my donations: no cache tag on the denial" || bad "my donations: cache tag on the denial" "$hdr"

# The public types list stays cacheable and carries the tag the writers purge.
curl -s -D "$hdr" -o "$body" "$url/graphql?queryId=$(qid FlxDonationTypes)"
grep -qi '^cache-control: public, max-age=' "$hdr" && ok "types: the public list is cacheable" || bad "types: Cache-Control public" "$hdr"
grep -qi '^x-graphql-keys:.*flx-donate:types' "$hdr" && ok "types: the response carries flx-donate:types" || bad "types: X-GraphQL-Keys" "$hdr"

echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
