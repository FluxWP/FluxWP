#!/usr/bin/env bash
# Whole-page mode over HTTP (GET <page>?flxhl_page=<front end id>): links move to the front end only after
# every page optimizer has run. An optimizer stand-in (like LiteSpeed's, which combines and minifies only files
# on the site's own address) opens its buffer on after_setup_theme and counts the local /wp-content/ and
# /wp-includes/ addresses it sees.
# Usage: tools/tests/wp-graphql/flx-headless/http-test.sh <site_dir> <url>
set -uo pipefail
site="$1"; url="$2"
WP="${WP_CLI:-wp} --allow-root --path=$site"
body="$(mktemp)"
mu="$site/wp-content/mu-plugins/flxt-headless-optimizer.php"
pass=0; fail=0
ok()  { pass=$((pass+1)); echo "  ok   $1"; }
bad() { fail=$((fail+1)); echo "  FAIL $1"; }

echo "Flux Headless whole-page mode over HTTP"
mkdir -p "$(dirname "$mu")"
cat > "$mu" <<'PHP'
<?php
// Test stand-in for a page optimizer: sees the page before it is sent and counts this site's own file addresses.
add_action(
	'after_setup_theme',
	static function () {
		ob_start(
			static function ( string $html ): string {
				$home = untrailingslashit( home_url() );
				$n    = substr_count( $html, $home . '/wp-content/' ) + substr_count( $html, $home . '/wp-includes/' );
				return false === stripos( $html, '</html>' ) ? $html : $html . '<!-- flxt-optimizer local=' . $n . ' -->';
			}
		);
	}
);
PHP
id="$($WP eval '$r = FLXHL_Frontend_Service::save( array( "name" => "Page mode test", "url" => "https://front.example.com" ) ); if ( is_wp_error( $r ) ) { exit( 1 ); } foreach ( FLXHL_Frontend_Service::frontends() as $f ) { if ( "https://front.example.com" === $f->url ) { echo $f->id; } }' 2>/dev/null)"
trap 'rm -f "$body" "$mu"; [ -n "$id" ] && $WP eval "FLXHL_Frontend_Service::delete( \"$id\" );" >/dev/null 2>&1' EXIT
[ -n "$id" ] && ok "fixture: front end $id" || { bad "front end could not be created"; echo "$pass passed, $fail failed"; exit 1; }

curl -s -o "$body" "$url/?flxhl_page=$id"
local_n="$(grep -o 'flxt-optimizer local=[0-9]*' "$body" | tail -1 | cut -d= -f2)"
[ "${local_n:-0}" -gt 0 ] && ok "page mode: the optimizer sees local file addresses ($local_n)" || bad "page mode: the optimizer saw no local file address (links were rewritten before it ran)"
grep -q 'https://front.example.com/wp-' "$body" && ok "page mode: files are on the front end in the sent page" || bad "page mode: no front-end file address in the sent page"
grep -Eq "${url}/wp-(content|includes)/" "$body" && bad "page mode: a WordPress file address is left in the sent page" || ok "page mode: no WordPress file address left"

curl -s -o "$body" "$url/"
grep -q 'front.example.com' "$body" && bad "plain request: rewritten although it is not a whole-page request" || ok "plain request: left as it is"

echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
