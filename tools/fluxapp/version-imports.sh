#!/usr/bin/env bash
# Chunks import the entry as "./flux-modules.js" (no query). Browsers and LiteSpeed cache that bare URL
# independently of the entry's ?ver=..., so after a plugin update a chunk can load an OLD flux-modules.js
# and fail with "Importing binding name 'x' is not found" (blank screen). Put a content hash on the import.
#
# Usage: tools/fluxapp/version-imports.sh <assets/flux dir>   (idempotent)
set -euo pipefail
dir="${1:?assets/flux klasörü gerekli}"
entry="$dir/flux-modules.js"
[ -f "$entry" ] || { echo "yok: $entry" >&2; exit 1; }
hash="$(md5sum "$entry" | cut -c1-10)"
for f in "$dir"/flux-*.js; do
	[ "$f" = "$entry" ] && continue
	sed -i -E "s#\./flux-modules\.js(\?v=[0-9a-f]+)?([\"'])#./flux-modules.js?v=${hash}\2#g" "$f"
done
