#!/usr/bin/env bash
# Eksik release imzalarını tamamlar: her paketin son KEEP (5) sürümünde <slug>.zip var, <slug>.zip.sig yoksa
# zip indirilir, bin/sign-package.php ile imzalanır ve release'e eklenir. Zip'e dokunulmaz.
# Release workflow'u her çalışmada sonda çağırır (SIGN_OPTIONAL=1: secret yoksa uyarı); "Release imzalarını
# tamamla" workflow'u elle (tek paket ya da daha çok sürüm için).
#
#   GH_TOKEN=… GITHUB_REPOSITORY=FluxWP/fluxwp FLX_RELEASE_SIGNING_KEY=… [KEEP=5] [ONLY=flx-ai] bin/sign-missing.sh
set -euo pipefail
cd "$(dirname "$0")/.."
KEEP="${KEEP:-5}"
case "$KEEP" in ''|*[!0-9]*) KEEP=5 ;; esac
ONLY="${ONLY:-}"
if [ -z "${FLX_RELEASE_SIGNING_KEY:-}" ]; then
  if [ "${SIGN_OPTIONAL:-}" = 1 ]; then
    echo "::warning::FLX_RELEASE_SIGNING_KEY yok, eski sürümler imzalanmadı"; exit 0
  fi
  echo "::error::FLX_RELEASE_SIGNING_KEY secret'ı yok"; exit 1
fi

# slug \t sürüm \t tag \t imzalı(0/1)  — yalnız <slug>.zip'i olan, taslak olmayan release'ler.
rows="$(gh api "repos/$GITHUB_REPOSITORY/releases?per_page=100" --paginate \
  --jq '.[] | select(.draft | not) | [.tag_name, ([.assets[].name] | join("/"))] | @tsv' | while IFS=$'\t' read -r tag assets; do
    [[ "$tag" =~ ^([a-z0-9][a-z0-9-]*)-v([0-9][0-9A-Za-z.+-]*)$ ]] || continue
    slug="${BASH_REMATCH[1]}"; ver="${BASH_REMATCH[2]}"
    [ -n "$ONLY" ] && [ "$slug" != "$ONLY" ] && continue
    case "/$assets/" in */"$slug.zip"/*) ;; *) continue ;; esac
    has=0; case "/$assets/" in */"$slug.zip.sig"/*) has=1 ;; esac
    printf '%s\t%s\t%s\t%s\n' "$slug" "$ver" "$tag" "$has"
  done)"

todo="$(printf '%s\n' "$rows" | sed '/^$/d' | sort -t$'\t' -k1,1 -k2,2V | awk -F'\t' -v keep="$KEEP" '
  { n[$1]++; line[$1, n[$1]] = $0 }
  END { for (s in n) for (i = n[s] - keep + 1; i <= n[s]; i++) if (i >= 1) { split(line[s, i], f, "\t"); if (f[4] == "0") print f[1] "\t" f[2] "\t" f[3] } }')"

signed=0; failed=""
while IFS=$'\t' read -r slug ver tag; do
  [ -n "$slug" ] || continue
  dir="$(mktemp -d)"
  if gh release download "$tag" -p "$slug.zip" -D "$dir" --clobber >/dev/null \
    && php bin/sign-package.php "$dir/$slug.zip" "$slug" "$ver" >/dev/null \
    && gh release upload "$tag" "$dir/$slug.zip.sig" --clobber >/dev/null; then
    signed=$((signed + 1)); echo "imzalandı: $tag"
  else
    failed="$failed $tag"
  fi
  rm -rf "$dir"
done <<< "$todo"
echo "::notice::Eksik imza: $signed sürüm imzalandı"
if [ -n "$failed" ]; then echo "::error::İmzalanamayan:$failed"; exit 1; fi
