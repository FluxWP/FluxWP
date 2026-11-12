#!/usr/bin/env bash
# Return success only for an HTTP/server error or a rendered fatal diagnostic.
# Ordinary prose mentioning "critical error" must not fail a release.
flx_test_response_failed() {
  local code="$1" body="$2"
  # A menu parent that has no screen of its own (admin.php?page=woocommerce) answers
  # with wp_die( 'Cannot load <slug>.' ) and HTTP 500. No code failed there.
  if [ "$code" = 500 ] && grep -qE '<div class="wp-die-message">(<p>)?Cannot load [A-Za-z0-9_-]+\.(</p>)?</div>' "$body"; then
    return 1
  fi
  # The same wp_die in a translated core ("woocommerce yüklenemiyor."): a one-line die message
  # that names the requested page slug (third argument) and nothing else.
  local slug="${3:-}"
  if [ "$code" = 500 ] && [ -n "$slug" ] && [[ "$slug" =~ ^[A-Za-z0-9_-]+$ ]] \
    && grep -qE "<div class=\"wp-die-message\">(<p>)?[^<]{0,40}\b${slug}\b[^<]{0,40}(</p>)?</div>" "$body"; then
    return 1
  fi
  case "$code" in 5[0-9][0-9]) return 0 ;; esac

  # PHP's HTML diagnostics use <br /> and <b>; preserve the diagnostic's line start.
  # Do not use grep -q on this pipe: an early exit can SIGPIPE awk under pipefail.
  awk '{
    gsub(/<[bB][rR][[:space:]]*\/?>/, "\n");
    gsub(/<\/?[bB]>/, "");
    print;
  }' "$body" | grep -iE \
    'There has been a critical error on this website[.,]|(Bu sitede|Sitenizde) ciddi bir sorun çıktı[.,]|(Bu web sitesinde|Web sitenizde|Sitenizde) kritik bir hata (oluştu|meydana geldi)|^[[:space:]]*(PHP[[:space:]]+)?(Fatal|Parse)[[:space:]]+error[[:space:]]*:' \
    >/dev/null
}
