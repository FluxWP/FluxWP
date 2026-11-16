#!/usr/bin/env bash
# GraphQL test site: WordPress + SQLite + Flux GraphQL (embedded WPGraphQL) + the FluxWP packages.
#
# Usage: tools/tests/wp-graphql/site.sh [site_dir] [port]
#   default site_dir: $TEST_ROOT/gql-site (TEST_ROOT default /tmp/flx-test), port 8890
#
# Environment:
#   WP_CLI             wp-cli command (default: wp)
#   WP_SRC             a WordPress folder to copy (else `wp core download`, en_US: health checks
#                      must not depend on translations)
#   SQLITE_PLUGIN_DIR  sqlite-database-integration folder (else downloaded with wp-cli)
#   PACKAGES           space separated plugin slugs to activate besides flx-graphql
#                      (default: every plugin whose header says "Requires Plugins: … flx-graphql")
#   WOOCOMMERCE_ZIP    optional WooCommerce zip, activated before the packages
#   SQLITE_VERSION     sqlite-database-integration version to download (default 3.1.3)
#   FIXTURES=0         leave the kit test fixtures (mu-plugin) out, e.g. for the schema snapshot
#   SERVE=1            start `php -S` on the port and wait until it answers (PID in $site/.server.pid)
#
# Packages are symlinked from the monorepo, so edits show up without reinstalling.
# The admin user is admin / admin.
set -euo pipefail

root="$(cd "$(dirname "$0")/../../.." && pwd)"
WP="${WP_CLI:-wp} --allow-root --quiet"
TEST_ROOT="${TEST_ROOT:-/tmp/flx-test}"
site="${1:-$TEST_ROOT/gql-site}"
port="${2:-8890}"
url="http://127.0.0.1:$port"

mkdir -p "$TEST_ROOT"
rm -rf "$site"
if [ -n "${WP_SRC:-}" ]; then
  cp -r "$WP_SRC" "$site"
  rm -rf "$site/.git"
else
  $WP core download --path="$site" --locale=en_US >/dev/null
fi

sqlite="${SQLITE_PLUGIN_DIR:-}"
if [ -z "$sqlite" ]; then
  # wp-cli "plugin install" needs a working database, which is what this plugin provides: download it directly.
  tmpzip="$(mktemp)"
  curl -fsSL -o "$tmpzip" "https://downloads.wordpress.org/plugin/sqlite-database-integration.${SQLITE_VERSION:-3.1.3}.zip"
  unzip -qo "$tmpzip" -d "$site/wp-content/plugins"
  rm -f "$tmpzip"
  sqlite="$site/wp-content/plugins/sqlite-database-integration"
else
  cp -rL "$sqlite" "$site/wp-content/plugins/sqlite-database-integration"
  sqlite="$site/wp-content/plugins/sqlite-database-integration"
fi
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$sqlite#" -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
  "$sqlite/db.copy" > "$site/wp-content/db.php"

$WP config create --path="$site" --dbname=wp --dbuser=x --dbpass=x --skip-check >/dev/null
$WP config set WP_DEBUG true --raw --path="$site"
$WP config set WP_DEBUG_LOG true --raw --path="$site"
$WP config set WP_DEBUG_DISPLAY false --raw --path="$site"
$WP config set DISABLE_WP_CRON true --raw --path="$site"
$WP config set WP_HTTP_BLOCK_EXTERNAL true --raw --path="$site"
$WP config set AUTOMATIC_UPDATER_DISABLED true --raw --path="$site"
$WP core install --path="$site" --url="$url" --title="Flux GraphQL Test" --admin_user=admin --admin_password=admin --admin_email=test@example.com --skip-email >/dev/null
$WP rewrite structure '/%postname%/' --path="$site" >/dev/null

# Packages that declare "Requires Plugins: woocommerce" (flx-ads, ...) do not activate without it. When no zip is
# given, a pinned release is downloaded (a failed download only means those packages are skipped).
if [ -z "${WOOCOMMERCE_ZIP:-}" ] && grep -lq "Requires Plugins:.*woocommerce" "$root"/plugins/*/*.php 2>/dev/null; then
  WOOCOMMERCE_ZIP="${TEST_ROOT:-/tmp/flx-test}/woocommerce-9.4.3.zip"
  [ -f "$WOOCOMMERCE_ZIP" ] || curl -fsSL -o "$WOOCOMMERCE_ZIP" "https://downloads.wordpress.org/plugin/woocommerce.9.4.3.zip" || rm -f "$WOOCOMMERCE_ZIP"
fi
if [ -n "${WOOCOMMERCE_ZIP:-}" ] && [ -f "$WOOCOMMERCE_ZIP" ]; then
  $WP plugin install "$WOOCOMMERCE_ZIP" --activate --path="$site" >/dev/null 2>&1 || echo "WooCommerce could not be activated" >&2
fi

# Kit fixtures (test queries, upload context). FIXTURES=0 leaves them out (schema snapshot).
if [ "${FIXTURES:-1}" = 1 ]; then
  mkdir -p "$site/wp-content/mu-plugins"
  cp "$root/tools/tests/wp-graphql/fixtures/mu-fixtures.php" "$root/tools/tests/wp-graphql/fixtures/flx-test-persisted.json" "$site/wp-content/mu-plugins/"
fi

# Link every monorepo plugin; activate flx-graphql and the GraphQL packages.
for dir in "$root"/plugins/*/; do
  slug="$(basename "$dir")"
  ln -s "$root/plugins/$slug" "$site/wp-content/plugins/$slug"
done
packages="${PACKAGES:-}"
if [ -z "$packages" ]; then
  for main in "$root"/plugins/*/*.php; do
    slug="$(basename "$(dirname "$main")")"
    [ "$(basename "$main" .php)" = "$slug" ] || continue
    if head -c 4096 "$main" | grep -qE '^[[:space:]*]*Requires Plugins:.*flx-graphql'; then
      packages="$packages $slug"
    fi
  done
fi
$WP plugin activate flx-graphql --path="$site"
for slug in $packages; do
  [ "$slug" = flx-graphql ] && continue
  $WP plugin activate "$slug" --path="$site" || { echo "::error::$slug could not be activated" >&2; exit 1; }
done
$WP rewrite flush --path="$site" >/dev/null

echo "site=$site url=$url packages=flx-graphql$packages"

if [ "${SERVE:-}" = 1 ]; then
  cat > "$site/.router.php" <<'PHP'
<?php
// php -S router: real files are served as is, everything else goes to WordPress.
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( '/' !== $path && is_file( __DIR__ . $path ) && ! str_ends_with( $path, '.php' ) ) {
	return false;
}
if ( str_ends_with( (string) $path, '.php' ) && is_file( __DIR__ . $path ) ) {
	$_SERVER['SCRIPT_NAME'] = $path;
	require __DIR__ . $path;
	return;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
PHP
  ( cd "$site" && PHP_CLI_SERVER_WORKERS=4 nohup php -S "127.0.0.1:$port" .router.php </dev/null >"$site/.server.log" 2>&1 & echo $! > "$site/.server.pid" )
  for _ in $(seq 1 50); do
    curl -fsS -o /dev/null "$url/wp-login.php" && break
    sleep 0.2
  done
fi
