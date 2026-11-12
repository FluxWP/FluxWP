#!/usr/bin/env bash
set -euo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
source "$here/../lib/smoke-response.sh"
fixture_dir="$(mktemp -d)"
trap 'rm -rf "$fixture_dir"' EXIT
checks=0

check_response() {
  local name="$1" expected="$2" code="$3" html="$4" actual=healthy
  printf '%s\n' "$html" > "$fixture_dir/response.html"
  if flx_test_response_failed "$code" "$fixture_dir/response.html" "${5:-}"; then actual=failed; fi
  if [ "$actual" != "$expected" ]; then
    printf 'FAIL: %s (expected %s, got %s)\n' "$name" "$expected" "$actual" >&2
    exit 1
  fi
  checks=$((checks + 1))
}

check_response 'Flux Setup rollback description' healthy 200 \
  '<p class="description">Only HTTP 500 (critical error) triggers a rollback; network or firewall errors do not.</p>'
check_response 'Generic English/Turkish help text' healthy 200 \
  '<p>A critical error or kritik bir hata can require troubleshooting.</p>'
check_response 'PHP diagnostic terms in help text' healthy 200 \
  '<p>PHP can display a Fatal error: or Parse error: message.</p>'
check_response 'Quoted diagnostic in inline code' healthy 200 \
  '<p>For example: <code>Fatal error: Uncaught Error</code>.</p>'
check_response 'Ordinary warning' healthy 200 'Warning: a non-fatal notice'
check_response 'HTTP 500 without an error phrase' failed 500 '<html>Server unavailable</html>'
check_response 'HTTP 503 without a body' failed 503 ''
check_response 'Menu parent without a screen' healthy 500 \
  '<body id="error-page"><div class="wp-die-message">Cannot load woocommerce.</div></body>'
check_response 'Menu parent without a screen, Turkish core' healthy 500 \
  '<body id="error-page"><div class="wp-die-message">woocommerce yüklenemiyor.</div></body>' woocommerce
check_response 'Translated die naming another page' failed 500 \
  '<body id="error-page"><div class="wp-die-message">wc-admin yüklenemiyor.</div></body>' woocommerce
check_response 'Translated die without a slug argument' failed 500 \
  '<body id="error-page"><div class="wp-die-message">woocommerce yüklenemiyor.</div></body>'
check_response 'Translated critical error on a page' failed 500 \
  '<div class="wp-die-message"><p>Sitenizde ciddi bir sorun çıktı.</p></div>' woocommerce
check_response 'Cannot load text beside a real fatal' failed 500 \
  '<div class="wp-die-message"><p>There has been a critical error on this website.</p></div><p>Cannot load woocommerce.</p>'
check_response 'HTTP 599' failed 599 'Proxy failure'
check_response 'Redirect with ordinary prose' healthy 302 'critical error documentation'
check_response 'WordPress critical page' failed 200 \
  '<div class="wp-die-message"><p>There has been a critical error on this website.</p></div>'
check_response 'WordPress recovery mode page' failed 200 \
  '<p>There has been a critical error on this website, putting it in recovery mode.</p>'
check_response 'Current Turkish WordPress critical page' failed 200 \
  '<p>Sitenizde ciddi bir sorun çıktı.</p>'
check_response 'Current Turkish protected endpoint' failed 200 \
  '<p>Bu sitede ciddi bir sorun çıktı. Lütfen yönergeler için site yöneticinizin gelen kutusuna bakın.</p>'
check_response 'Legacy Turkish critical page' failed 200 \
  '<p>Web sitenizde kritik bir hata oluştu.</p>'
check_response 'Plain PHP fatal' failed 200 'Fatal error: Uncaught Error: Call to undefined function example()'
check_response 'PHP-prefixed fatal' failed 200 'PHP Fatal error: Uncaught TypeError: example'
check_response 'Plain PHP parse error' failed 200 'Parse error: syntax error, unexpected token'
check_response 'HTML PHP fatal' failed 200 \
  '<html>Partial page<br /><b>Fatal error</b>: Uncaught Error: example<br /></html>'
check_response 'HTML PHP parse error' failed 200 \
  '<BR><b>Parse error</b>: syntax error in example.php'

# The detector must consume the response even when an error is near the beginning.
{
  printf '%s\n' 'Fatal error: Uncaught Error: example'
  for ((i = 0; i < 5000; i++)); do printf '%s\n' '<p>Additional page output after the diagnostic.</p>'; done
} > "$fixture_dir/large.html"
if ! flx_test_response_failed 200 "$fixture_dir/large.html"; then
  printf 'FAIL: large error response under pipefail\n' >&2
  exit 1
fi
checks=$((checks + 1))
printf 'Passed %s smoke-response regression checks.\n' "$checks"
