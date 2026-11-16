# WooCommerce dependency stand-in for packages that declare "Requires Plugins: woocommerce"
# (flx-ads). Sourced by tools/tests/wp-graphql/site.sh before plugins are activated
# ($WP, $site, $root, $url, FIXTURES). An empty plugin in the woocommerce folder satisfies
# that dependency when no WooCommerce zip is given, and always for the schema snapshot
# (FIXTURES=0), so WooCommerce's own types and settings never leak into the snapshot.
# The file is not woocommerce.php, so "woocommerce/woocommerce.php" stays absent from the
# active plugins and fixtures that test for the real plugin keep working.
if grep -lq "Requires Plugins:.*woocommerce" "$root"/plugins/*/*.php 2>/dev/null; then
  if [ "${FIXTURES:-1}" = 0 ] || [ -z "${WOOCOMMERCE_ZIP:-}" ]; then
    mkdir -p "$site/wp-content/plugins/woocommerce"
    printf '<?php\n/**\n * Plugin Name: WooCommerce dependency stand-in\n */\n' > "$site/wp-content/plugins/woocommerce/flxt-standin.php"
    $WP plugin activate woocommerce/flxt-standin.php --path="$site" >/dev/null 2>&1 || echo "WooCommerce stand-in could not be activated" >&2
    WOOCOMMERCE_ZIP=""
  fi
fi
