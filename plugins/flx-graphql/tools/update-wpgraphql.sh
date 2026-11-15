#!/usr/bin/env bash
# Pins the embedded WPGraphQL copy: plugins/flx-graphql/vendor/wp-graphql/ + vendor/WPGRAPHQL_VERSION.
#
# Usage: plugins/flx-graphql/tools/update-wpgraphql.sh [version]   (default: the pinned version)
#        plugins/flx-graphql/tools/update-wpgraphql.sh --latest    (newest wp-graphql/vX.Y.Z tag)
#
# Needs git, composer, node + npm (the GraphiQL IDE assets are built with wp-scripts).
# The Appsero usage-tracking client is removed: the embedded copy never phones home.
# Release zips carry the vendored copy, so a site never downloads WPGraphQL on its own.
set -euo pipefail

here="$(cd "$(dirname "$0")/.." && pwd)"
repo="https://github.com/wp-graphql/wp-graphql"
pinned="$(cat "$here/vendor/WPGRAPHQL_VERSION" 2>/dev/null || true)"
want="${1:-$pinned}"

if [ "$want" = "--latest" ]; then
  want="$(git ls-remote --tags "$repo" | grep -o 'refs/tags/wp-graphql/v[0-9]*\.[0-9]*\.[0-9]*$' | sed 's#.*/v##' | sort -V | tail -1)"
fi
[ -n "$want" ] || { echo "No version given and none pinned." >&2; exit 1; }

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
echo "Fetching WPGraphQL $want"
git clone -q --depth 1 --branch "wp-graphql/v$want" "$repo" "$work/src"

plugin="$work/src/plugins/wp-graphql"
(
  cd "$plugin"
  composer install --no-dev --no-interaction --no-progress >/dev/null
  # Drop the Appsero client from the installed set, then rebuild the autoloader without it.
  rm -rf vendor/appsero
  php -r '$f="vendor/composer/installed.json"; $j=json_decode(file_get_contents($f), true);
    $j["packages"]=array_values(array_filter($j["packages"], fn($p) => $p["name"] !== "appsero/client"));
    file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));'
  composer dump-autoload --no-dev --optimize --no-interaction >/dev/null
  ! grep -q -i appsero vendor/composer/autoload_classmap.php
)
(
  cd "$work/src"
  npm ci --ignore-scripts --no-audit --no-fund >/dev/null
  cd "$plugin" && npx --no-install wp-scripts build >/dev/null
)

dest="$here/vendor/wp-graphql"
rm -rf "$dest"
mkdir -p "$dest"
excludes=()
while IFS= read -r line || [ -n "$line" ]; do
  line="${line%%#*}"; line="$(echo "$line" | xargs)"
  [ -n "$line" ] && excludes+=(--exclude="${line#/}")
done < "$plugin/.distignore"
tar -C "$plugin" "${excludes[@]}" --exclude='.distignore' -cf - . | tar -C "$dest" -xf -

# Library test suites, docs and examples are not needed at runtime.
find "$dest/vendor" -depth -type d \( -name tests -o -name docs -o -name examples -o -name benchmarks -o -name .github -o -name .git \) -exec rm -rf {} +
rm -f "$dest"/vendor/webonyx/graphql-php/{Makefile,mkdocs.yml,phpbench.json,rector.php,renovate.json,generate-class-reference.php}
cp "$work/src/LICENSE" "$dest/LICENSE"
find "$dest/vendor" -type f \( -name '*.md' -o -name 'phpunit*' -o -name 'phpstan*' -o -name '.gitattributes' -o -name '.gitignore' \) ! -name 'LICENSE*' -delete

echo "$want" > "$here/vendor/WPGRAPHQL_VERSION"
echo "WPGraphQL $want → $dest ($(du -sh "$dest" | cut -f1))"
