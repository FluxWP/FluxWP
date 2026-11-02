#!/usr/bin/env bash
# Ortak modül bundle'ını (apps/wp-embed) bir eklentinin assets/flux/ klasörüne kopyalar.
# Kullanım: tools/fluxapp/embed-sync.sh <eklenti-slug>   ör. flx-chat
set -euo pipefail
slug="${1:?eklenti slug gerekli}"
root="$(cd "$(dirname "$0")/../.." && pwd)"
dest="$root/plugins/$slug/assets/flux"
[ -d "$root/plugins/$slug" ] || { echo "plugins/$slug yok" >&2; exit 1; }
(cd "$root/apps/wp-embed" && bun run build)
mkdir -p "$dest"
cp "$root/apps/wp-embed/dist/flux-modules.js" "$root/apps/wp-embed/dist/flux-modules.css" "$dest/"
echo "kopyalandı: $dest"
