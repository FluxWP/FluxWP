#!/usr/bin/env bash
# Paketi WordPress'in kurabileceği zip'e çevirir: dist/<slug>.zip (içinde <slug>/ klasörü).
# Kullanım: bin/build.sh plugins/flx-forms
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
dir="${1%/}"
eval "$("$root/bin/package-info.sh" "$dir")"

if [ -z "$VERSION" ]; then
  echo "Sürüm başlığı (Version:) bulunamadı: $MAIN" >&2
  exit 1
fi

out="$root/dist"
stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT
mkdir -p "$out" "$stage/$SLUG"

# Hariç tutulanlar: varsayılanlar + paketin .distignore dosyası.
excludes=(--exclude='.git*' --exclude='.github' --exclude='.distignore' --exclude='.DS_Store'
          --exclude='node_modules' --exclude='tests' --exclude='phpunit.xml*' --exclude='CHANGELOG.md' --exclude='./docs/images'
          --exclude='./docs/previews' --exclude='./docs/*.gif' --exclude='./docs/wizard*.jpg'
          --exclude='docs/wizard*.jpg')
if [ -f "$root/$dir/.distignore" ]; then
  while IFS= read -r line || [ -n "$line" ]; do
    line="${line%%#*}"; line="$(echo "$line" | xargs)"
    [ -n "$line" ] && excludes+=(--exclude="$line")
  done < "$root/$dir/.distignore"
fi

tar -C "$root/$dir" "${excludes[@]}" -cf - . | tar -C "$stage/$SLUG" -xf -
rm -f "$out/$SLUG.zip"
( cd "$stage" && zip -qrX "$out/$SLUG.zip" "$SLUG" )

echo "$out/$SLUG.zip ($NAME $VERSION)"
