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
#                      (default: every plugin whose header says "Requires Plugins: … flx-graphql",
#                      and every plugin whose main file hooks flx_gql_register, like flx-setup)
#   THEME              theme slug to activate (default fluxwp; empty = keep the default theme)
#   WOOCOMMERCE_ZIP    optional WooCommerce zip (path or URL), activated before the packages; without it
#                      the cart bridge tests run against a Store API stand-in (fixtures/mu-fake-store-api.php)
#   SQLITE_VERSION     sqlite-database-integration version to download (default 3.1.3)
#   FIXTURES=0         leave the kit test fixtures (mu-plugin) out, e.g. for the schema snapshot
#   SERVE=1            start `php -S` on the port and wait until it answers (PID in $site/.server.pid)
#
# Package hooks, so a package can add its own setup without editing this file:
#   tools/tests/wp-graphql/<slug>/deps.sh   sourced before activation (Requires Plugins stand-ins)
#   tools/tests/wp-graphql/<slug>/site.sh   sourced after the packages are activated
# Both see $WP, $site, $root, $url and FIXTURES.
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

# Dependency stand-ins (for example WooCommerce for flx-ads) before activation, and before the
# optional WooCommerce zip below so a hook can clear WOOCOMMERCE_ZIP for the schema snapshot.
for hook in "$root"/tools/tests/wp-graphql/*/deps.sh; do
  # shellcheck source=/dev/null
  [ -f "$hook" ] && . "$hook"
done
wc_zip="${WOOCOMMERCE_ZIP:-}"
if [[ "$wc_zip" == https://* ]]; then
  # WP_HTTP_BLOCK_EXTERNAL is on, so wp-cli cannot download it: fetch the zip here.
  tmpwc="$(mktemp --suffix=.zip)"
  curl -fsSL -o "$tmpwc" "$wc_zip" || echo "WooCommerce could not be downloaded" >&2
  wc_zip="$tmpwc"
fi
if [ -n "$wc_zip" ] && [ -s "$wc_zip" ]; then
  $WP plugin install "$wc_zip" --activate --path="$site" >/dev/null || echo "WooCommerce could not be activated" >&2
fi

# Kit fixtures (test queries, upload context). FIXTURES=0 leaves them out (schema snapshot).
if [ "${FIXTURES:-1}" = 1 ]; then
  mkdir -p "$site/wp-content/mu-plugins"
  cp "$root/tools/tests/wp-graphql/fixtures/mu-fixtures.php" "$root/tools/tests/wp-graphql/fixtures/flx-test-persisted.json" "$site/wp-content/mu-plugins/"
  # Store API stand-in for the cart bridge tests; it switches itself off when WooCommerce is active.
  cp -r "$root/tools/tests/wp-graphql/fixtures/mu-fake-store-api.php" "$root/tools/tests/wp-graphql/fixtures/flxt-fake-store-api" "$root/tools/tests/wp-graphql/fixtures/mu-cart-fixtures.php" "$root/tools/tests/wp-graphql/fixtures/flx-test-cart-persisted.json" "$site/wp-content/mu-plugins/"
fi

# Link every monorepo plugin; activate flx-graphql and the GraphQL packages.
for dir in "$root"/plugins/*/; do
  slug="$(basename "$dir")"
  ln -s "$root/plugins/$slug" "$site/wp-content/plugins/$slug"
done
# Themes are linked too; THEME (default fluxwp) is activated after the plugins.
for dir in "$root"/themes/*/; do
  ln -s "$root/themes/$(basename "$dir")" "$site/wp-content/themes/$(basename "$dir")"
done
packages="${PACKAGES:-}"
if [ -z "$packages" ]; then
  for main in "$root"/plugins/*/*.php; do
    slug="$(basename "$(dirname "$main")")"
    [ "$(basename "$main" .php)" = "$slug" ] || continue
    # Packages that need Flux GraphQL, and packages that work without it but register a schema when it is there.
    if head -c 4096 "$main" | grep -qE '^[[:space:]*]*Requires Plugins:.*flx-graphql' || grep -q "'flx_gql_register'" "$main"; then
      packages="$packages $slug"
    fi
  done
fi
$WP plugin activate flx-graphql --path="$site"
for slug in $packages; do
  [ "$slug" = flx-graphql ] && continue
  $WP plugin activate "$slug" --path="$site" || { echo "::error::$slug could not be activated" >&2; exit 1; }
done
if [ -d "$root/themes/${THEME:-fluxwp}" ]; then
  $WP theme activate "${THEME:-fluxwp}" --path="$site" || { echo "::error::theme ${THEME:-fluxwp} could not be activated" >&2; exit 1; }
fi
for hook in "$root"/tools/tests/wp-graphql/*/site.sh; do
  # shellcheck source=/dev/null
  [ -f "$hook" ] && . "$hook"
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
