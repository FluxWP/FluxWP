#!/usr/bin/env bash
# Kullanım: bin/package-info.sh plugins/flx-forms
# Çıktı (eval edilebilir): SLUG TYPE MAIN NAME VERSION REQUIRES TESTED REQUIRES_PHP REQUIRES_PLUGINS
set -euo pipefail

dir="${1%/}"
slug="$(basename "$dir")"

case "$dir" in
  themes/*|*/themes/*) type=theme; main="$dir/style.css" ;;
  *)                   type=plugin; main="$dir/$slug.php"
                       if [ ! -f "$main" ]; then
                         main="$(grep -l -m1 -E '^[[:space:]/*#@]*Plugin Name:' "$dir"/*.php 2>/dev/null | head -1 || true)"
                       fi ;;
esac

if [ -z "${main:-}" ] || [ ! -f "$main" ]; then
  echo "echo 'Ana dosya bulunamadı: $dir' >&2; exit 1"
  exit 0
fi

header() {
  # İlk 8 KB içinde "Anahtar: değer" satırı (WordPress get_file_data ile aynı yaklaşım).
  head -c 8192 "$main" | tr -d '\r' | grep -m1 -E "^[[:space:]/*#@]*$1:" | sed -E "s/^[[:space:]/*#@]*$1:[[:space:]]*//; s/[[:space:]]*(\*\/)?[[:space:]]*$//" || true
}

if [ "$type" = theme ]; then name="$(header 'Theme Name')"; else name="$(header 'Plugin Name')"; fi

q() { printf "%q" "$1"; }
echo "SLUG=$(q "$slug")"
echo "TYPE=$(q "$type")"
echo "MAIN=$(q "$main")"
echo "NAME=$(q "$name")"
echo "VERSION=$(q "$(header 'Version')")"
echo "REQUIRES=$(q "$(header 'Requires at least')")"
echo "TESTED=$(q "$(header 'Tested up to')")"
echo "REQUIRES_PHP=$(q "$(header 'Requires PHP')")"
echo "REQUIRES_PLUGINS=$(q "$(header 'Requires Plugins')")"
