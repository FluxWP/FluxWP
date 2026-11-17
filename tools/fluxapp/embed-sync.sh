#!/usr/bin/env bash
# Ortak modül bundle'ını (apps/wp-embed) bir eklentinin assets/flux/ klasörüne kopyalar.
#
# Varsayılan — parça derlemesi (Vite FLUX_EMBED_CHUNKS=1):
#   tools/fluxapp/embed-sync.sh <eklenti-slug>          ör. flx-chat (tema: themes/fluxwp)
#   Çıktı: flux-modules.js (ES giriş), flux-<modül>-<hash>.js parçaları,
#   flux-modules.css ve chunks.json. Manifest: {"mode":"chunks","entry":"flux-modules.js","files":[...]}.
#   Hedefteki eski flux-*.js dosyaları silinir. Parça adları hash taşır; sürüm sorgusu yalnız girişe konur.
#
# Eski tek dosya — IIFE, window.FluxModules (Vite FLUX_EMBED_CHUNKS=0):
#   tools/fluxapp/embed-sync.sh <eklenti-slug> --single
#   FLUX_EMBED_CHUNKS=0 tools/fluxapp/embed-sync.sh <eklenti-slug>
#   Çıktı: flux-modules.js + flux-modules.css. chunks.json yazılmaz; varsa silinir.
#
# --no-build: apps/wp-embed/dist'teki son derlemeyi kopyalar (birden çok paketi aynı derlemeyle eşitlemek için).
set -euo pipefail

slug=""
single=0
build=1
for arg in "$@"; do
	case "$arg" in
		--single) single=1 ;;
		--no-build) build=0 ;;
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
# "themes/<slug>" bir temanın assets/flux/ klasörüne yazar; yalın slug eklentidir.
base="plugins"
case "$slug" in themes/*) base="themes"; slug="${slug#themes/}" ;; esac
if [ "${FLUX_EMBED_CHUNKS:-1}" = "0" ]; then
	single=1
fi

root="$(cd "$(dirname "$0")/../.." && pwd)"
dest="$root/$base/$slug/assets/flux"
dist="$root/apps/wp-embed/dist"
[ -d "$root/$base/$slug" ] || { echo "$base/$slug yok" >&2; exit 1; }

if [ "$build" = 0 ]; then
	:
elif [ "$single" = 1 ]; then
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

shopt -s nullglob
mains=( "$dist"/flux-modules-*.js )
if [ "${#mains[@]}" -ne 1 ] || [ ! -f "$dist/flux-modules.css" ]; then
	echo "parça çıktısı eksik: $dist" >&2
	exit 1
fi
main="$(basename "${mains[0]}")"
shopt -s nullglob
chunks=( "$dist"/flux-*.js )
if [ "${#chunks[@]}" -eq 0 ]; then
	echo "parça çıktısı eksik: $dist" >&2
	exit 1
fi
cp "${chunks[@]}" "$dist/flux-modules.css" "$dest/"
# Giriş adı hash taşır (parçalar onu bu adla import eder). flux-modules.js yalnız onu yeniden dışa verir:
# sayfa flux-modules.js?ver=… yüklese de modül tek kez çalışır (?ver'li ve ?ver'siz iki ayrı modül olurdu).
printf 'export * from "./%s";\n' "$main" > "$dest/flux-modules.js"

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
# Ekran başına parça listesi (modulepreload, Flux GraphQL ekran kabuğu).
node "$root/tools/fluxapp/embed-preload.mjs" "$dist" "$dest/chunks.json"
echo "parçalar kopyalandı: $dest"
