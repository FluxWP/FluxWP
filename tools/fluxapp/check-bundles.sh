#!/usr/bin/env bash
# PR check: the embedded React bundle of a plugin must be rebuilt when that plugin's screens change.
#
# A plugin is checked when the PR changes both
#   - its screen source packages/modules/src/<module>/ (module = slug without "flx-"), and
#   - the plugin itself (plugins/<slug>/ has a bundle: assets/flux/chunks.json).
# The bundle is rebuilt with tools/fluxapp/embed-sync.sh and compared with the committed one.
# Other plugins' bundles are not compared (each is synced by its own package PR).
#
# Usage: tools/fluxapp/check-bundles.sh <base-ref>        (e.g. origin/main)
set -euo pipefail
base="${1:?base ref gerekli (ör. origin/main)}"
root="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$root"

changed="$(git diff --name-only "$base"...HEAD)"
slugs=""
for dir in $(echo "$changed" | grep -E '^plugins/[^/]+/' | cut -d/ -f2 | sort -u); do
	[ -f "plugins/$dir/assets/flux/chunks.json" ] || continue
	module="${dir#flx-}"
	if echo "$changed" | grep -q "^packages/modules/src/$module/"; then
		slugs="$slugs $dir"
	fi
done
# Every committed bundle must link (imports resolve to exports), whatever the PR touched.
echo "== tüm gömülü paketlerde bağlantı kontrolü"
node tools/fluxapp/verify-bundle.mjs || exit 1

if [ -z "$slugs" ]; then
	echo "Yeniden derlenecek gömülü paket yok."
	exit 0
fi

fail=0
for slug in $slugs; do
	echo "== $slug: paket yeniden derleniyor"
	bash tools/fluxapp/embed-sync.sh "$slug" >/dev/null
	if [ -n "$(git status --porcelain -- "plugins/$slug/assets/flux")" ]; then
		echo "::error title=Embedded bundle out of date::plugins/$slug/assets/flux does not match packages/modules. Run 'bash tools/fluxapp/embed-sync.sh $slug' and commit the result."
		git status --short -- "plugins/$slug/assets/flux" | head -20
		fail=1
	elif ! node tools/fluxapp/verify-bundle.mjs --strict "plugins/$slug/assets/flux"; then
		fail=1
	else
		echo "$slug: güncel"
	fi
done
exit $fail
