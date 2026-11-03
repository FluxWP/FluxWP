#!/bin/bash
# Yerel README test sitesini sifirdan kurar: WP 6.8.3 + SQLite + WooCommerce + repodaki tum paketler (symlink).
# Kullanim: WPENV=/tmp/wpenv bash tools/readme-media/setup.sh
set -e
H=$(cd "$(dirname "$0")" && pwd); REPO=$(cd "$H/../.." && pwd); W=${WPENV:-/tmp/wpenv}; S=$W/site
mkdir -p "$W"; cd "$W"
[ -d "$S/wp-includes" ] || git clone --depth 1 --branch 6.8.3 https://github.com/WordPress/WordPress.git "$S"
# SQLite eklentisi (wordpress.org kapali olabilir; npm paketindeki zip kullanilir)
if [ ! -d "$S/wp-content/plugins/sqlite-database-integration" ]; then
  [ -f node_modules/@wp-playground/cli/sqlite-database-integration.zip ] || npm i --silent @wp-playground/cli
  unzip -q node_modules/@wp-playground/cli/sqlite-database-integration.zip -d "$S/wp-content/plugins/"
  # yeni zip surumleri klasoru plugin-sqlite-database-integration adiyla acar
  [ -d "$S/wp-content/plugins/plugin-sqlite-database-integration" ] && mv "$S/wp-content/plugins/plugin-sqlite-database-integration" "$S/wp-content/plugins/sqlite-database-integration"
fi
P=$S/wp-content/plugins/sqlite-database-integration
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$P#" -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#g" "$P/db.copy" > "$S/wp-content/db.php"
# WooCommerce (11.x WP 7 ister)
if [ ! -d "$S/wp-content/plugins/woocommerce" ]; then
  curl -sSL -o wc.zip https://github.com/woocommerce/woocommerce/releases/download/10.4.3/woocommerce.zip && unzip -q wc.zip -d "$S/wp-content/plugins/"
fi
cp "$H/env/wp-config.php" "$S/wp-config.php"
mkdir -p "$S/wp-content/mu-plugins"; cp "$H"/env/mu-plugins/*.php "$S/wp-content/mu-plugins/"
for d in "$REPO"/plugins/*/; do ln -sfn "${d%/}" "$S/wp-content/plugins/$(basename "$d")"; done
for d in "$REPO"/themes/*/; do ln -sfn "${d%/}" "$S/wp-content/themes/$(basename "$d")"; done
python3 "$H/env/demo-certificates/gen.py" "$S/wp-content/uploads/demo-certs"
cd "$H"
php env/install.php || true
bash "$H/serve.sh"
php env/act.php woocommerce flx-admin flx-roles flx-ai flx-mail flx-donate flx-forms flx-certificates flx-chat flx-updater flx-backup flx-speed flx-analytics flx-social flx-seo flx-studio flx-lang flx-app flx-tasks || true
php -r "require '$S/wp-load.php'; switch_theme('fluxwp');"
php seed/users.php; php env/connect-mocks.php; php env/prep.php || true; php seed/seed-site.php; php seed/seed-sosyal.php  # sosyal gorselleri yazilara da kapak olur
echo "Hazir: http://127.0.0.1:8080/wp-admin (admin / admin12345)"
