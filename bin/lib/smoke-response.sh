#!/usr/bin/env bash
# Return success only for an HTTP/server error or a rendered fatal diagnostic.
# Ordinary prose mentioning "critical error" must not fail a release.
flx_test_response_failed() {
  local code="$1" body="$2"
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
