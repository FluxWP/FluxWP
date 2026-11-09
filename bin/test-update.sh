#!/usr/bin/env bash
# Paket güncelleme testi: temiz bir WordPress'te
#   1) önceki sürüm (varsa) kurulur, etkinleştirilir, sayfalar açılır,
#   2) yeni zip WordPress'in kendi güncelleyicisiyle üstüne kurulur (migration'lar çalışır),
#   3) ön yüz, giriş, admin ve paketin admin sayfaları açılır; HTTP 500 / PHP fatal varsa test düşer,
#   4) yeni sürüm sıfırdan kurulup etkinleştirilerek aynı kontroller tekrarlanır.
#
# Kullanım: bin/test-update.sh plugins/flx-forms dist/flx-forms.zip [onceki.zip]
#
# Ortam:
#   WP_CLI       wp-cli komutu (varsayılan: wp)
#   TEST_ROOT    çalışma klasörü (varsayılan: /tmp/flx-test)
#   DB_MODE      mysql (CI) | sqlite (yerel; SQLITE_PLUGIN_DIR gerekli)
#   DB_USER/DB_PASS/DB_HOST  mysql bilgileri (varsayılan root/root/127.0.0.1)
#   WP_VERSION   varsayılan latest
set -uo pipefail

dir="${1%/}"; new_zip="$2"; prev_zip="${3:-}"
here="$(cd "$(dirname "$0")" && pwd)"
source "$here/lib/smoke-response.sh"
eval "$("$here/package-info.sh" "$dir")"

WP="${WP_CLI:-wp} --allow-root --quiet"
ROOT="${TEST_ROOT:-/tmp/flx-test}"
DB_MODE="${DB_MODE:-mysql}"
CACHE="$ROOT/cache"
mkdir -p "$CACHE"
fail=0

log()  { echo "  · $*"; }
err()  { echo "::error title=$SLUG güncelleme testi::$*"; fail=1; }
warn() { echo "::warning title=$SLUG güncelleme testi::$*"; }

requires_plugins="$(head -c 8192 "$MAIN" | tr -d '\r' | grep -m1 -E '^[[:space:]/*#@]*Requires Plugins:' | sed -E 's/^[^:]*:[[:space:]]*//; s/[[:space:]]*(\*\/)?[[:space:]]*$//' | tr ',' ' ' || true)"

download_core() {
  if [ ! -d "$CACHE/wordpress" ]; then
    $WP core download --path="$CACHE/wordpress" --version="${WP_VERSION:-latest}" --locale=tr_TR >/dev/null 2>&1 \
      || $WP core download --path="$CACHE/wordpress" --version="${WP_VERSION:-latest}" >/dev/null
  fi
  for dep in $requires_plugins; do
    if [ -d "$here/../plugins/$dep" ]; then
      # Monorepo'daki bağımlılık (ör. flx-setup): wordpress.org'da yok, buradan paketlenir.
      "$here/build.sh" "plugins/$dep" >/dev/null && cp "$here/../dist/$dep.zip" "$CACHE/$dep.zip" || warn "$dep paketlenemedi"
    elif [ ! -f "$CACHE/$dep.zip" ]; then
      curl -fsSL -o "$CACHE/$dep.zip" "https://downloads.wordpress.org/plugin/$dep.latest-stable.zip" || warn "$dep indirilemedi"
    fi
  done
}

# fresh_site <ad> <port>: temiz kurulum, $SITE ve $URL ayarlanır.
fresh_site() {
  SITE="$ROOT/$1"; URL="http://127.0.0.1:$2"
  rm -rf "$SITE"; cp -r "$CACHE/wordpress" "$SITE"
  local dbname="flx_test_${1//-/_}"
  if [ "$DB_MODE" = sqlite ]; then
    cp -r "$SQLITE_PLUGIN_DIR" "$SITE/wp-content/plugins/sqlite-database-integration"
    sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$SITE/wp-content/plugins/sqlite-database-integration#" \
        -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
        "$SITE/wp-content/plugins/sqlite-database-integration/db.copy" > "$SITE/wp-content/db.php"
    $WP config create --path="$SITE" --dbname=wp --dbuser=x --dbpass=x --skip-check >/dev/null
  else
    $WP config create --path="$SITE" --dbname="$dbname" --dbuser="${DB_USER:-root}" --dbpass="${DB_PASS:-root}" --dbhost="${DB_HOST:-127.0.0.1}" --skip-check >/dev/null
    $WP db drop --yes --path="$SITE" >/dev/null 2>&1 || true
    $WP db create --path="$SITE" >/dev/null
  fi
  $WP config set WP_DEBUG true --raw --path="$SITE"
  $WP config set WP_DEBUG_LOG true --raw --path="$SITE"
  $WP config set WP_DEBUG_DISPLAY false --raw --path="$SITE"
  $WP config set FS_METHOD direct --path="$SITE"
  $WP config set DISABLE_WP_CRON true --raw --path="$SITE"
  $WP config set AUTOMATIC_UPDATER_DISABLED true --raw --path="$SITE"
  # Dış istekleri kapat: test, ağdan bağımsız ve hızlı olsun.
  $WP config set WP_HTTP_BLOCK_EXTERNAL true --raw --path="$SITE"
  $WP core install --path="$SITE" --url="$URL" --title="Flux Test" --admin_user=admin --admin_password=admin --admin_email=test@example.com --skip-email >/dev/null
  for dep in $requires_plugins; do
    [ -f "$CACHE/$dep.zip" ] && $WP plugin install "$CACHE/$dep.zip" --activate --path="$SITE" >/dev/null 2>&1 || warn "$dep kurulamadı"
  done
  # WooCommerce'in uzak öneri/eklenti mağazası istekleri (dış istek kapalıyken gürültü yapar).
  $WP option update woocommerce_allow_marketplace_suggestions no --path="$SITE" >/dev/null 2>&1 || true
  $WP option update woocommerce_show_marketplace_suggestions no --path="$SITE" >/dev/null 2>&1 || true
  PHP_CLI_SERVER_WORKERS=4 php -S "127.0.0.1:$2" -t "$SITE" >"$SITE.server.log" 2>&1 &
  SERVER_PID=$!
  sleep 1
}

stop_site() { kill "$SERVER_PID" 2>/dev/null; wait "$SERVER_PID" 2>/dev/null; }

install_pkg() { # install_pkg <zip>  (üstüne kurar: WordPress güncelleyicisinin aynısı)
  if [ "$TYPE" = theme ]; then
    $WP theme install "$1" --force --activate --path="$SITE" 2>&1 | grep -E "^(Success|Error|Warning: The)" | tail -1
  else
    $WP plugin install "$1" --force --activate --path="$SITE" 2>&1 | grep -E "^(Success|Error|Warning: The)" | tail -1
  fi
}

check_page() { # check_page <yol> [cookie]
  local out code
  out="$(mktemp)"
  code="$(curl -s -o "$out" -w '%{http_code}' --max-time 60 ${2:+-H "Cookie: $2"} "$URL$1")"
  if flx_test_response_failed "$code" "$out"; then
    # Karar smoke() sonunda: hata paketten mi, bağımlılıktan (ör. WooCommerce) mı?
    BAD_PAGES="$BAD_PAGES $1→$code"
    log "$1 → $code (hata)"
  else
    log "$1 → $code"
  fi
  PAGE="$out"
}

# debug.log'daki fatal bloklarını ayırır: bağımlılığın kendi kodunda kalan (yığında paket yok) → DEP_FATALS,
# geri kalan → OWN_FATALS. Bağımlılık = "Requires Plugins" eklentileri.
classify_fatals() {
  OWN_FATALS=""; DEP_FATALS=""
  local f="$SITE/wp-content/debug.log"
  [ -f "$f" ] || return 0
  local blocks
  blocks="$(awk '/^\[[^]]+\] PHP (Fatal|Parse) error/{if(b!="")print b; b=$0; next} /^\[/{if(b!=""){print b; b=""}; next} {if(b!="")b=b" "$0} END{if(b!="")print b}' "$f")"
  while IFS= read -r blk; do
    [ -n "$blk" ] || continue
    local dep_hit=0
    for dep in $requires_plugins; do
      case "$blk" in *"/plugins/$dep/"*) dep_hit=1 ;; esac
    done
    case "$blk" in *"/$SLUG/"*) dep_hit=0 ;; esac
    if [ "$dep_hit" = 1 ]; then DEP_FATALS="$DEP_FATALS${blk:0:300}"$'\n'; else OWN_FATALS="$OWN_FATALS${blk:0:400}"$'\n'; fi
  done <<< "$blocks"
}

assert_active() { # paket etkin mi (etkinleştirmede fatal olursa WordPress etkinleştirmez)
  if [ "$TYPE" = theme ]; then
    [ "$($WP option get stylesheet --path="$SITE" 2>/dev/null)" = "$SLUG" ] || err "$1: tema etkinleştirilemedi"
  else
    $WP plugin is-active "$SLUG" --path="$SITE" >/dev/null 2>&1 || err "$1: eklenti etkinleştirilemedi"
  fi
}

smoke() { # smoke <aşama adı>
  STAGE="$1"; BAD_PAGES=""
  : > "$SITE/wp-content/debug.log" 2>/dev/null || true
  check_page "/"
  check_page "/wp-login.php"
  local cookie
  cookie="$($WP eval 'echo AUTH_COOKIE . "=" . wp_generate_auth_cookie( 1, time() + 3600, "auth" ) . "; " . LOGGED_IN_COOKIE . "=" . wp_generate_auth_cookie( 1, time() + 3600, "logged_in" );' --path="$SITE")"
  check_page "/wp-admin/" "$cookie"
  local pages
  pages="$(grep -oE 'admin\.php\?page=[A-Za-z0-9_-]+' "$PAGE" | sort -u | head -25)"
  check_page "/wp-admin/plugins.php" "$cookie"
  for p in $pages; do check_page "/wp-admin/$p" "$cookie"; done
  classify_fatals
  if [ -n "$OWN_FATALS" ]; then
    err "$STAGE: $(printf '%s' "$OWN_FATALS" | head -1)${BAD_PAGES:+ | sayfalar:$BAD_PAGES}"
  elif [ -n "$BAD_PAGES" ] && [ -z "$DEP_FATALS" ]; then
    err "$STAGE: hata veren sayfalar:$BAD_PAGES"
  elif [ -n "$DEP_FATALS" ]; then
    warn "$STAGE: bağımlılıkta hata (paketten değil), sayfalar:${BAD_PAGES:- yok} | $(printf '%s' "$DEP_FATALS" | head -1)"
  fi
  if [ -f "$SITE/wp-content/debug.log" ]; then
    local n
    n="$(grep -E 'PHP (Warning|Notice|Deprecated)' "$SITE/wp-content/debug.log" | grep -c "/$SLUG/" || true)"
    [ "$n" -gt 0 ] && warn "$STAGE: paketten $n PHP uyarısı (ilki: $(grep -E 'PHP (Warning|Notice|Deprecated)' "$SITE/wp-content/debug.log" | grep -m1 "/$SLUG/" | cut -c1-300))"
  fi
}

echo "== $SLUG $VERSION =="
download_core

# PHP sözdizimi
while IFS= read -r -d '' f; do
  php -l "$f" >/dev/null 2>&1 || err "Sözdizimi hatası: $(php -l "$f" 2>&1 | head -1)"
done < <(find "$dir" -name '*.php' -not -path '*/vendor/*' -not -path '*/node_modules/*' -print0)

port=$(( 8800 + RANDOM % 900 ))

if [ -n "$prev_zip" ] && [ -f "$prev_zip" ]; then
  echo "-- güncelleme: önceki sürüm → $VERSION"
  fresh_site "$SLUG-update" "$port"
  log "önceki sürüm: $(install_pkg "$prev_zip")"
  smoke "önceki sürüm"
  if [ "$fail" = 1 ]; then
    warn "Önceki sürüm bu ortamda zaten hata veriyor; güncelleme sonucu yine de kontrol ediliyor"
    fail=0
  fi
  log "güncelleme: $(install_pkg "$new_zip")"
  assert_active "güncelleme sonrası"
  smoke "güncelleme sonrası"
  stop_site
fi

echo "-- temiz kurulum: $VERSION"
fresh_site "$SLUG-fresh" "$(( port + 1 ))"
log "kurulum: $(install_pkg "$new_zip")"
assert_active "temiz kurulum"
smoke "temiz kurulum"
stop_site

if [ "$fail" = 1 ]; then
  echo "✗ $SLUG $VERSION testten geçmedi"
  exit 1
fi
echo "✓ $SLUG $VERSION testten geçti"
