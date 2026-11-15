#!/usr/bin/env bash
# HTTP behaviour of Flux GraphQL on a running test site (site.sh with SERVE=1, fixtures on):
# allowlist, persisted GET + cache headers, CORS, upload endpoint.
# Usage: tools/tests/wp-graphql/http-test.sh <site_dir> <url>
set -uo pipefail
site="$1"; url="$2"
WP="${WP_CLI:-wp} --allow-root --path=$site"
jar="$(mktemp)"; body="$(mktemp)"; hdr="$(mktemp)"
trap 'rm -f "$jar" "$body" "$hdr"' EXIT
pass=0; fail=0
ok()   { pass=$((pass+1)); echo "  ok   $1"; }
bad()  { fail=$((fail+1)); echo "  FAIL $1"; [ -n "${2:-}" ] && echo "       $(head -c 400 "$2")"; }
has()  { grep -q -- "$2" "$1"; }
pq="ffddb6ac5762b8d7ceee75ad414d56112547858efbd5c677d267a7c8c50a11fd"

echo "Flux GraphQL over HTTP"
$WP option update flxgql_settings '{"query_mode":"strict"}' --format=json >/dev/null 2>&1 || true

curl -s -o "$body" -X POST "$url/graphql" -H 'Content-Type: application/json' -d '{"query":"{ flxTestEcho(text:\"x\") }"}'
has "$body" QUERY_NOT_ALLOWED && ok "visitor: free query refused in strict mode" || bad "visitor: free query refused" "$body"

curl -s -D "$hdr" -o "$body" "$url/graphql?queryId=$pq"
has "$body" '"flxTestEcho":"pong"' && ok "visitor: persisted GET by queryId" || bad "persisted GET" "$body"
grep -qi '^cache-control: public, max-age=300' "$hdr" && ok "persisted public GET is cacheable" || bad "cache-control public" "$hdr"
grep -qi '^x-litespeed-cache-control: public' "$hdr" && ok "LiteSpeed cache header" || bad "x-litespeed-cache-control" "$hdr"

curl -s -o "$body" "$url/graphql?queryId=$(printf 'a%.0s' $(seq 1 64))"
has "$body" PERSISTED_QUERY_NOT_FOUND && ok "unknown queryId → PERSISTED_QUERY_NOT_FOUND" || bad "unknown queryId" "$body"

curl -s -o "$body" -X POST "$url/graphql" -H 'Content-Type: application/json' -d '[{"queryId":"'"$pq"'"},{"query":"{ flxTestEcho(text:\"y\") }"}]'
has "$body" '"pong"' && has "$body" QUERY_NOT_ALLOWED && ok "batch: persisted op runs, free op refused" || bad "batch" "$body"

# Sign in (cookie + REST nonce).
curl -s -c "$jar" -b "$jar" -o /dev/null -d 'log=admin&pwd=admin&testcookie=1' -b 'wordpress_test_cookie=WP%20Cookie%20check' "$url/wp-login.php"
nonce="$(curl -s -b "$jar" "$url/wp-admin/admin-ajax.php?action=rest-nonce")"
[ -n "$nonce" ] && [ "$nonce" != 0 ] && ok "admin signed in" || bad "admin sign-in"

curl -s -b "$jar" -o "$body" -X POST "$url/graphql" -H "X-WP-Nonce: $nonce" -H 'Content-Type: application/json' -d '{"query":"{ flxTestAdminOnly }"}'
has "$body" QUERY_NOT_ALLOWED && ok "admin: free query refused in strict mode (IDE off)" || bad "admin strict" "$body"
$WP option update flxgql_settings '{"query_mode":"admins"}' --format=json >/dev/null 2>&1 || true
curl -s -b "$jar" -D "$hdr" -o "$body" -X POST "$url/graphql" -H "X-WP-Nonce: $nonce" -H 'Content-Type: application/json' -d '{"query":"{ flxTestAdminOnly }"}'
has "$body" '"secret"' && ok "admin: free query allowed in admins mode" || bad "admin free query" "$body"
grep -qi '^cache-control:.*no-cache' "$hdr" && ok "signed-in response is not cacheable" || bad "no-cache for signed in" "$hdr"
curl -s -o "$body" -X POST "$url/graphql" -H 'Content-Type: application/json' -d '{"query":"{ flxTestEcho(text:\"x\") }"}'
has "$body" QUERY_NOT_ALLOWED && ok "visitor: still refused in admins mode" || bad "visitor in admins mode" "$body"
$WP option update flxgql_settings '{"query_mode":"strict"}' --format=json >/dev/null 2>&1 || true

# CORS.
curl -s -D "$hdr" -o /dev/null "$url/graphql?queryId=$pq" -H 'Origin: https://evil.example'
grep -qi '^access-control-allow-origin' "$hdr" && bad "foreign origin gets no ACAO" "$hdr" || ok "foreign origin gets no Access-Control-Allow-Origin"
curl -s -D "$hdr" -o /dev/null "$url/graphql?queryId=$pq" -H "Origin: $url"
grep -qi "^access-control-allow-origin: $url" "$hdr" && ok "own origin is echoed" || bad "own origin" "$hdr"
$WP option update flxgql_settings '{"query_mode":"strict","cors_origins":"https://front.example"}' --format=json >/dev/null 2>&1 || true
curl -s -D "$hdr" -o /dev/null "$url/graphql?queryId=$pq" -H 'Origin: https://front.example'
grep -qi '^access-control-allow-origin: https://front.example' "$hdr" && ok "configured front-end origin is allowed" || bad "configured origin" "$hdr"
$WP option update flxgql_settings '{"query_mode":"strict"}' --format=json >/dev/null 2>&1 || true

# Upload endpoint.
tmp="$(mktemp --suffix=.txt)"; echo "hello" > "$tmp"
code="$(curl -s -o "$body" -w '%{http_code}' -F "context=flx-test/file" -F "file=@$tmp;type=text/plain" "$url/wp-json/flx/v1/upload")"
[ "$code" = 401 ] && ok "upload: visitor refused (401)" || bad "upload visitor $code" "$body"
code="$(curl -s -b "$jar" -o "$body" -w '%{http_code}' -H "X-WP-Nonce: $nonce" -F "context=flx-test/file" -F "file=@$tmp;type=text/plain" "$url/wp-json/flx/v1/upload")"
[ "$code" = 201 ] && has "$body" uploadId && ok "upload: admin gets an uploadId" || bad "upload admin $code" "$body"
upid="$(php -r '$j=json_decode(file_get_contents($argv[1]),true); echo $j["uploadId"] ?? "";' "$body")"
code="$(curl -s -b "$jar" -o "$body" -w '%{http_code}' -H "X-WP-Nonce: $nonce" -F "context=nope" -F "file=@$tmp" "$url/wp-json/flx/v1/upload")"
[ "$code" = 400 ] && ok "upload: unknown context refused" || bad "upload unknown context $code" "$body"
printf '<?php echo 1;' > "${tmp%.txt}.php"
code="$(curl -s -b "$jar" -o "$body" -w '%{http_code}' -H "X-WP-Nonce: $nonce" -F "context=flx-test/file" -F "file=@${tmp%.txt}.php" "$url/wp-json/flx/v1/upload")"
[ "$code" = 400 ] && ok "upload: disallowed type refused" || bad "upload type $code" "$body"
taken="$(FLXT_UPID="$upid" $WP eval '$u=get_users(array("login"=>"admin"))[0]; wp_set_current_user($u->ID); $a=flx_gql_upload_take(getenv("FLXT_UPID"), "flx-test/file"); $b=flx_gql_upload_take(getenv("FLXT_UPID"), "flx-test/file"); echo is_wp_error($a) ? "err" : (file_get_contents($a["path"]) === "hello\n" ? "ok" : "bad"); echo is_wp_error($b) ? "-gone" : "-again";' 2>/dev/null)"
[ "$taken" = "ok-gone" ] && ok "upload: take() once, then gone" || bad "upload take ($taken)"
tmpdir="$($WP eval 'echo wp_upload_dir()["basedir"];' 2>/dev/null)/flx-uploads"
[ -f "$tmpdir/.htaccess" ] && ok "upload folder is private (.htaccess)" || bad "upload folder .htaccess"
rm -f "$tmp" "${tmp%.txt}.php"

echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
