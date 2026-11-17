#!/usr/bin/env bash
# Builds the shared plugin runtime (apps/wp-embed, FLUX_EMBED_RUNTIME=1) into Flux GraphQL's assets/runtime/.
# Plugin screens built with @fluxwp/plugin-kit (flux-screens.js) run on it; see docs/graphql/EKLENTI-EKRANLARI.md.
#   tools/fluxapp/runtime-sync.sh
set -euo pipefail
root="$(cd "$(dirname "$0")/../.." && pwd)"
dest="$root/plugins/flx-graphql/assets/runtime"
(cd "$root/apps/wp-embed" && FLUX_EMBED_RUNTIME=1 bun run build)
(cd "$root/packages/plugin-kit" && bun scripts/runtime-exports.ts)
mkdir -p "$dest"
find "$dest" -maxdepth 1 -type f -name 'flux-runtime*' -delete
cp "$root/apps/wp-embed/dist-runtime/"flux-runtime* "$dest/"
echo "runtime kopyalandı: $dest ($(ls "$dest" | wc -l) dosya)"
