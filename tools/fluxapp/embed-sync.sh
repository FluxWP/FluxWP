#!/usr/bin/env bash
# Ortak modül bundle'ını (apps/wp-embed) bir eklentinin assets/flux/ klasörüne kopyalar.
#
# Varsayılan — parça derlemesi (Vite FLUX_EMBED_CHUNKS=1):
#   tools/fluxapp/embed-sync.sh <eklenti-slug>          ör. flx-chat
#   Çıktı: flux-modules.js (ES giriş), flux-<modül>-<hash>.js parçaları,
#   flux-modules.css ve chunks.json. Manifest: {"mode":"chunks","entry":"flux-modules.js","files":[...]}.
#   Hedefteki eski flux-*.js dosyaları silinir. Parça adları hash taşır; sürüm sorgusu yalnız girişe konur.
#
# Eski tek dosya — IIFE, window.FluxModules (Vite FLUX_EMBED_CHUNKS=0):
#   tools/fluxapp/embed-sync.sh <eklenti-slug> --single
#   FLUX_EMBED_CHUNKS=0 tools/fluxapp/embed-sync.sh <eklenti-slug>
#   Çıktı: flux-modules.js + flux-modules.css. chunks.json yazılmaz; varsa silinir.
set -euo pipefail

slug=""
single=0
for arg in "$@"; do
	case "$arg" in
		--single) single=1 ;;
		-*) echo "bilinmeyen seçenek: $arg" >&2; exit 1 ;;
		*)
			if [ -n "$slug" ]; then
				echo "tek slug verin" >&2
				exit 1
			fi
			slug="$arg"
			;;
	esac
done
slug="${slug:?eklenti slug gerekli}"
if [ "${FLUX_EMBED_CHUNKS:-1}" = "0" ]; then
	single=1
fi

root="$(cd "$(dirname "$0")/../.." && pwd)"
dest="$root/plugins/$slug/assets/flux"
dist="$root/apps/wp-embed/dist"
[ -d "$root/plugins/$slug" ] || { echo "plugins/$slug yok" >&2; exit 1; }

if [ "$single" = 1 ]; then
	(cd "$root/apps/wp-embed" && FLUX_EMBED_CHUNKS=0 bun run build)
else
	(cd "$root/apps/wp-embed" && FLUX_EMBED_CHUNKS=1 bun run build)
fi

mkdir -p "$dest"
find "$dest" -maxdepth 1 -type f -name 'flux-*.js' -delete
rm -f "$dest/chunks.json"

if [ "$single" = 1 ]; then
	cp "$dist/flux-modules.js" "$dist/flux-modules.css" "$dest/"
	echo "tek dosya kopyalandı: $dest"
	exit 0
fi

if [ ! -f "$dist/flux-modules.js" ] || [ ! -f "$dist/flux-modules.css" ]; then
	echo "parça çıktısı eksik: $dist" >&2
	exit 1
fi
shopt -s nullglob
chunks=( "$dist"/flux-*.js )
if [ "${#chunks[@]}" -eq 0 ]; then
	echo "parça çıktısı eksik: $dist" >&2
	exit 1
fi
cp "${chunks[@]}" "$dist/flux-modules.css" "$dest/"

{
	printf '{\n  "mode": "chunks",\n  "entry": "flux-modules.js",\n  "files": [\n'
	first=1
	while IFS= read -r name; do
		if [ "$first" = 1 ]; then
			first=0
		else
			printf ',\n'
		fi
		printf '    "%s"' "$name"
	done < <(find "$dest" -maxdepth 1 -type f \( -name 'flux-*.js' -o -name 'flux-modules.css' \) -exec basename {} \; | LC_ALL=C sort)
	printf '\n  ]\n}\n'
} > "$dest/chunks.json"
echo "parçalar kopyalandı: $dest"
