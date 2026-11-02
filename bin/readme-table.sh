#!/usr/bin/env bash
# README.md'deki paket tablosunu tag'lerden yeniden yazar (<!-- paketler:start --> … <!-- paketler:end -->).
# Kullanım: bin/readme-table.sh [README.md]   (önce: git fetch --tags)
# Release workflow'u her çalışmada çağırır; elle düzenlemeye gerek yok.
set -euo pipefail

readme="${1:-README.md}"
# Dil: 2. argüman ya da dosya adı (*.tr.md -> tr, diğerleri en).
lang="${2:-}"; if [ -z "$lang" ]; then case "$readme" in *.tr.md) lang=tr;; *) lang=en;; esac; fi
repo="${GITHUB_REPOSITORY:-FluxWP/fluxwp}"
base="https://github.com/$repo/releases"

rows=""
cells=()
for dir in plugins/*/ themes/*/; do
  dir="${dir%/}"
  [ -d "$dir" ] || continue
  eval "$(bin/package-info.sh "$dir")"
  stable="$(git tag -l "$SLUG-v*" | sed "s/^$SLUG-v//" | grep -v -- '-' | sort -V | tail -1 || true)"
  newest="$(git tag -l "$SLUG-v*" | sed "s/^$SLUG-v//" | sort -V | tail -1 || true)"

  name="${NAME:-$SLUG}"
  if [ "$lang" = en ]; then
    if [ "$TYPE" = theme ]; then kind="Theme"; else kind="Plugin"; fi
    en_name="$(awk -F'\t' -v s="$SLUG" '$1==s{print $2; exit}' bin/readme-desc.en.tsv 2>/dev/null || true)"
    [ -n "$en_name" ] && name="$en_name"
    desc="$(awk -F'\t' -v s="$SLUG" '$1==s{print $3; exit}' bin/readme-desc.en.tsv 2>/dev/null || true)"
    link="$dir"
  else
    if [ "$TYPE" = theme ]; then kind="Tema"; else kind="Eklenti"; fi
    desc="$(awk -F'\t' -v s="$SLUG" '$1==s{print $2; exit}' bin/readme-desc.tsv 2>/dev/null || true)"
    link="$dir"; [ -f "$dir/README.tr.md" ] && link="$dir/README.tr.md"
  fi
  if [ -z "$desc" ]; then
    desc="$(head -c 8192 "$MAIN" | tr -d '\r' | grep -m1 -E '^[[:space:]/*#@]*Description:' | sed -E 's/^[[:space:]/*#@]*Description:[[:space:]]*//; s/([.!?])[[:space:]].*/\1/' || true)"
  fi
  desc="${desc//|/\\|}"

  if [ -n "$stable" ]; then
    tag="$SLUG-v$stable"
    date="$(git log -1 --format=%cs "$tag" 2>/dev/null || true)"
    ver="[$stable]($base/tag/$tag)"
    dl="[$SLUG.zip]($base/download/$tag/$SLUG.zip)"
  else
    date=""; ver="—"; dl="—"
  fi
  if [ -n "$newest" ] && [ "$newest" != "$stable" ]; then
    ver="$ver (beta: [$newest]($base/tag/$SLUG-v$newest))"
  fi
  # Galeri: paketin README'si ve küçük GIF'i (docs/thumb.gif) varsa üstteki galeride gösterilir.
  # 4 sütun: Ad | GIF | Ad | GIF (satır başına 2 paket).
  if [ -f "$dir/README.md" ]; then
    if [ -f "$dir/docs/thumb.gif" ]; then
      cells+=("<td width=\"18%\"><a href=\"$link\"><b>$name</b></a></td><td align=\"center\" width=\"32%\"><a href=\"$link\"><img src=\"$dir/docs/thumb.gif\" width=\"260\" alt=\"$name\"></a></td>")
    fi
  fi
  name="[$name]($link)"
  rows+="| $name | $desc | $kind | $ver | $date | $dl |"$'\n'
done

gallery=""
if [ ${#cells[@]} -gt 0 ]; then
  if [ "$lang" = en ]; then h1="Package"; h2="Preview"; else h1="Paket"; h2="Tanıtım"; fi
  gallery="<table><tr><th>$h1</th><th>$h2</th><th>$h1</th><th>$h2</th></tr>"
  for i in "${!cells[@]}"; do
    [ $((i % 2)) -eq 0 ] && gallery+="<tr>"
    gallery+="${cells[$i]}"
    [ $((i % 2)) -eq 1 ] && gallery+="</tr>"
  done
  [ $((${#cells[@]} % 2)) -ne 0 ] && gallery+="<td></td><td></td></tr>"
  gallery+="</table>"$'\n\n'
fi

if [ "$lang" = en ]; then
table="${gallery}| Package | Description | Type | Latest | Date | Download |
|---|---|---|---|---|---|
${rows}
All versions: [Releases]($base). This table is updated by the release workflow."
else
table="${gallery}| Paket | Açıklama | Tür | Son sürüm | Tarih | İndir |
|---|---|---|---|---|---|
${rows}
Tüm sürümler: [Releases]($base). Bu tablo release workflow'u tarafından otomatik güncellenir."
fi

python3 - "$readme" "$table" <<'PY'
import sys, re
path, table = sys.argv[1], sys.argv[2].rstrip("\n")
s = open(path, encoding="utf-8").read()
start, end = "<!-- paketler:start -->", "<!-- paketler:end -->"
block = f"{start}\n{table}\n{end}"
if start in s and end in s:
    s = re.sub(re.escape(start) + r".*?" + re.escape(end), lambda m: block, s, count=1, flags=re.S)
else:
    # İlk "## " başlığından önce ekle.
    i = s.find("\n## ")
    section = f"\n## Paketler ve son sürümler\n\n{block}\n"
    s = s + section if i < 0 else s[:i] + "\n" + section + s[i:]
open(path, "w", encoding="utf-8").write(s)
PY
