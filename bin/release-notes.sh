#!/usr/bin/env bash
# Release notu: paketin CHANGELOG.md'sindeki "## <sürüm>" bölümü + flx-meta satırı.
# Kullanım: bin/release-notes.sh plugins/flx-forms
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
dir="${1%/}"
eval "$("$root/bin/package-info.sh" "$dir")"

notes=""
if [ -f "$root/$dir/CHANGELOG.md" ]; then
  notes="$(awk -v v="$VERSION" '
    /^##[[:space:]]/ { if (found) exit; h=$0; sub(/^##[[:space:]]*\[?v?/, "", h); split(h, a, /[][ ]/); if (a[1]==v) { found=1; next } }
    found { print }
  ' "$root/$dir/CHANGELOG.md")"
fi
if [ -z "$(echo "$notes" | tr -d '[:space:]')" ]; then
  notes="$(git -C "$root" log -1 --format=%s -- "$dir" 2>/dev/null || true)"
fi

meta="$(php -r '$rp = array_values(array_filter(array_map("trim", explode(",", strtolower($argv[6]))))); echo json_encode(array("type"=>$argv[1],"name"=>$argv[2],"requires"=>$argv[3],"tested"=>$argv[4],"requires_php"=>$argv[5],"requires_plugins"=>$rp), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);' \
  "$TYPE" "$NAME" "$REQUIRES" "$TESTED" "$REQUIRES_PHP" "${REQUIRES_PLUGINS:-}")"

printf '%s\n\n<!-- flx-meta %s -->\n' "$notes" "$meta"
