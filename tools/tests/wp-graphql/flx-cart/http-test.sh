#!/usr/bin/env bash
# Cart bridge (flxCart*) over HTTP, with the persisted operations the clients send
# (plugins/flx-graphql/graphql/persisted-queries.json). Runs against real WooCommerce when it is active
# on the test site (CI: WOOCOMMERCE_ZIP), otherwise against the Store API stand-in
# (fixtures/mu-fake-store-api.php). FLX_CART_REQUIRE_WC=1 fails when WooCommerce is missing.
# Usage: tools/tests/wp-graphql/flx-cart/http-test.sh <site_dir> <url>
set -uo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
root="$(cd "$here/../../../.." && pwd)"
site="$1"; url="$2"
WP="${WP_CLI:-wp} --allow-root --path=$site"
body="$(mktemp)"; hdr="$(mktemp)"
trap 'rm -f "$body" "$hdr"' EXIT
pass=0; fail=0
ok()  { pass=$((pass+1)); echo "  ok   $1"; }
bad() { fail=$((fail+1)); echo "  FAIL $1"; [ -n "${2:-}" ] && echo "       $(php -r '$j = json_decode( file_get_contents( $argv[1] ), true ); $c = is_array( $j ) ? ( array_values( (array) ( $j["data"] ?? array() ) )[0] ?? null ) : null; $c = is_array( $c ) && isset( $c["cart"] ) ? array( "totals" => $c["cart"]["totals"]["total"] ?? null, "items" => array_map( fn( $i ) => array( $i["kind"] ?? "", $i["quantity"] ?? 0, $i["unitPrice"]["amount"] ?? null, $i["quantityLimits"] ?? null ), (array) ( $c["cart"]["items"] ?? array() ) ), "coupons" => $c["cart"]["coupons"] ?? null, "errors" => $c["errors"] ?? null ) : $j; echo substr( (string) json_encode( $c ), 0, 1500 );' "$2" 2>/dev/null || head -c 600 "$2")"; }
# json <file> <php expression on $j>
json() { php -r '$j = json_decode( file_get_contents( $argv[1] ), true ); $v = '"$2"'; echo is_scalar( $v ) || null === $v ? var_export( $v, true ) : json_encode( $v );' "$1"; }
qid() { php -r 'foreach ( json_decode( file_get_contents( $argv[1] ), true ) as $k => $d ) { if ( preg_match( "/^(?:query|mutation) " . $argv[2] . "\\b/", $d ) ) { echo $k; } }' "$root/plugins/flx-graphql/graphql/persisted-queries.json" "$1"; }
# op <name> <variables json> [extra curl args…]   (POST, persisted)
op() { local name="$1" vars="$2"; shift 2; curl -s -D "$hdr" -o "$body" -X POST "$url/graphql" -H 'Content-Type: application/json' "$@" -d '{"queryId":"'"$(qid "$name")"'","variables":'"$vars"'}'; }

echo "Flux GraphQL cart bridge over HTTP"
$WP option update flxgql_settings '{"query_mode":"strict"}' --format=json >/dev/null 2>&1 || true
real="$($WP eval 'echo class_exists( "WC_Product_Simple" ) ? 1 : 0;' 2>/dev/null)"
if [ "$real" = 1 ]; then echo "  (WooCommerce $($WP eval 'echo WC()->version;' 2>/dev/null) Store API)"; else echo "  (Store API stand-in: WooCommerce is not installed)"; fi
if [ "${FLX_CART_REQUIRE_WC:-0}" = 1 ] && [ "$real" != 1 ]; then bad "WooCommerce is required here (FLX_CART_REQUIRE_WC=1) but not active"; echo "$pass passed, $fail failed"; exit 1; fi

# Products and a coupon (WooCommerce objects, or posts for the stand-in).
read -r p1 p2 < <($WP eval '
$mk = static function ( string $name, string $price ): int {
	if ( class_exists( "WC_Product_Simple" ) ) {
		$p = new WC_Product_Simple();
		$p->set_name( $name ); $p->set_regular_price( $price ); $p->set_status( "publish" ); $p->set_virtual( true );
		return (int) $p->save();
	}
	$id = (int) wp_insert_post( array( "post_type" => "product", "post_status" => "publish", "post_title" => $name ) );
	update_post_meta( $id, "_price", $price );
	return $id;
};
if ( class_exists( "WC_Coupon" ) ) {
	$c = new WC_Coupon(); $c->set_code( "flx10" ); $c->set_discount_type( "fixed_cart" ); $c->set_amount( 10 ); $c->save();
} else {
	$id = (int) wp_insert_post( array( "post_type" => "shop_coupon", "post_status" => "publish", "post_title" => "flx10", "post_name" => "flx10" ) );
	update_post_meta( $id, "coupon_amount", "10" );
}
echo $mk( "Sapling", "150" ) . " " . $mk( "Notebook", "40" );
' 2>/dev/null)
[ -n "$p1" ] && [ -n "$p2" ] && ok "fixtures: products $p1, $p2 and coupon flx10" || { bad "fixtures could not be created"; echo "$pass passed, $fail failed"; exit 1; }

# 1. A fresh visitor (no cookie, no token) reads the cart: empty, a Cart-Token comes back, never cached.
curl -s -D "$hdr" -o "$body" "$url/graphql?queryId=$(qid FlxCart)"
[ "$(json "$body" '$j["data"]["flxCart"]["itemsCount"] ?? null')" = 0 ] && ok "flxCart: empty cart for a fresh visitor" || bad "empty cart" "$body"
grep -qi '^cache-control: no-store' "$hdr" && ok "flxCart: Cache-Control no-store (persisted GET is not cached)" || bad "no-store" "$hdr"
grep -qi '^x-litespeed-cache-control: no-cache' "$hdr" && ok "flxCart: LiteSpeed no-cache" || bad "litespeed no-cache" "$hdr"
token="$(grep -i '^cart-token:' "$hdr" | head -1 | cut -d' ' -f2 | tr -d '\r')"
[ -n "$token" ] && [ "'$token'" = "$(json "$body" '$j["data"]["flxCart"]["token"] ?? null')" ] && ok "flxCart: Cart-Token header = FlxCart.token" || bad "cart token" "$hdr"
grep -qi '^access-control-expose-headers:.*cart-token' "$hdr" && ok "flxCart: Cart-Token is exposed to browsers (CORS)" || bad "expose headers" "$hdr"
ckout="$(json "$body" '$j["data"]["flxCart"]["checkout"]["endpoint"] ?? ""')"
[[ "$ckout" == *"wc/store/v1/checkout"* ]] && ok "flxCart: checkout.endpoint is the Store API checkout" || bad "checkout endpoint" "$body"

T=(-H "Cart-Token: $token")
# 2. Add, add again (same line grows), add a second product.
op FlxCartItemAdd '{"input":{"productId":'"$p1"',"quantity":2}}' "${T[@]}"
key1="$(json "$body" '$j["data"]["flxCartItemAdd"]["itemKey"] ?? ""' | tr -d "'")"
[ "$(json "$body" '$j["data"]["flxCartItemAdd"]["cart"]["items"][0]["quantity"] ?? null')" = 2 ] && [ -n "$key1" ] && ok "flxCartItemAdd: 2 × product, itemKey returned" || bad "add item" "$body"
[ "$(json "$body" '(float) ( $j["data"]["flxCartItemAdd"]["cart"]["totals"]["total"]["amount"] ?? 0 )')" = 300.0 ] && ok "flxCartItemAdd: total 300 (minor units converted)" || bad "total 300" "$body"
[ "$(json "$body" '$j["data"]["flxCartItemAdd"]["cart"]["items"][0]["kind"] ?? null')" = "'product'" ] && ok "cart line kind: product" || bad "kind product" "$body"
op FlxCartItemAdd '{"input":{"productId":'"$p2"'}}' "${T[@]}"
key2="$(json "$body" '$j["data"]["flxCartItemAdd"]["itemKey"] ?? ""' | tr -d "'")"
[ "$(json "$body" '$j["data"]["flxCartItemAdd"]["cart"]["itemsCount"] ?? null')" = 3 ] && ok "flxCartItemAdd: second product, 3 units" || bad "second product" "$body"

# 3. The token keeps the session across requests (GET with the token).
curl -s -D "$hdr" -o "$body" "$url/graphql?queryId=$(qid FlxCart)" "${T[@]}"
[ "$(json "$body" 'count( $j["data"]["flxCart"]["items"] ?? array() )')" = 2 ] && ok "Cart-Token: the same cart on the next request" || bad "token session" "$body"
# Without the token: another (empty) cart.
curl -s -o "$body" "$url/graphql?queryId=$(qid FlxCart)"
[ "$(json "$body" '$j["data"]["flxCart"]["itemsCount"] ?? null')" = 0 ] && ok "no token: not the other visitor's cart" || bad "isolation" "$body"

# 4. Update, coupon, remove.
op FlxCartItemUpdate '{"input":{"key":"'"$key1"'","quantity":1}}' "${T[@]}"
[ "$(json "$body" '(float) ( $j["data"]["flxCartItemUpdate"]["cart"]["totals"]["total"]["amount"] ?? 0 )')" = 190.0 ] && ok "flxCartItemUpdate: quantity 1, total 190" || bad "update" "$body"
op FlxCartCouponApply '{"input":{"code":"FLX10"}}' "${T[@]}"
[ "$(json "$body" '(float) ( $j["data"]["flxCartCouponApply"]["cart"]["totals"]["total"]["amount"] ?? 0 )')" = 180.0 ] && [ "$(json "$body" '$j["data"]["flxCartCouponApply"]["cart"]["coupons"][0]["code"] ?? null')" = "'flx10'" ] && ok "flxCartCouponApply: flx10 (case-insensitive), total 180" || bad "coupon apply" "$body"
op FlxCartCouponApply '{"input":{"code":"nope"}}' "${T[@]}"
[ "$(json "$body" '$j["data"]["flxCartCouponApply"]["errors"][0]["field"] ?? null')" = "'code'" ] && [ "$(json "$body" 'array_key_exists( "cart", $j["data"]["flxCartCouponApply"] ?? array() ) ? $j["data"]["flxCartCouponApply"]["cart"] : "x"')" = NULL ] && ok "flxCartCouponApply: unknown coupon → payload error on field code, cart null" || bad "coupon error" "$body"
op FlxCartCouponRemove '{"input":{"code":"flx10"}}' "${T[@]}"
[ "$(json "$body" 'count( $j["data"]["flxCartCouponRemove"]["cart"]["coupons"] ?? array( 1 ) )')" = 0 ] && ok "flxCartCouponRemove" || bad "coupon remove" "$body"
op FlxCartItemRemove '{"input":{"key":"'"$key2"'"}}' "${T[@]}"
[ "$(json "$body" 'count( $j["data"]["flxCartItemRemove"]["cart"]["items"] ?? array() )')" = 1 ] && ok "flxCartItemRemove" || bad "remove" "$body"
op FlxCartItemRemove '{"input":{"key":"'"$key2"'"}}' "${T[@]}"
[ "$(json "$body" '$j["data"]["flxCartItemRemove"]["errors"][0]["code"] ?? null')" = "'CONFLICT'" ] && ok "flxCartItemRemove: gone line → CONFLICT payload error" || bad "remove twice" "$body"
op FlxCartItemAdd '{"input":{"productId":999999}}' "${T[@]}"
[ "$(json "$body" '$j["data"]["flxCartItemAdd"]["errors"][0]["field"] ?? null')" = "'productId'" ] && ok "flxCartItemAdd: unknown product → payload error on productId" || bad "bad product" "$body"
op FlxCartItemAdd '{"input":{"productId":'"$p1"',"quantity":0}}' "${T[@]}"
[ "$(json "$body" '$j["data"]["flxCartItemAdd"]["errors"][0]["field"] ?? null')" = "'quantity'" ] && ok "flxCartItemAdd: quantity 0 → payload error on quantity" || bad "quantity 0" "$body"

# 5. Address.
op FlxCartCustomerUpdate '{"input":{"billingAddress":{"firstName":"Ayşe","email":"ayse@example.com","country":"TR","state":"TR34","city":"İstanbul","postcode":"34000"}}}' "${T[@]}"
[ "$(json "$body" '$j["data"]["flxCartCustomerUpdate"]["cart"]["billingAddress"]["firstName"] ?? null')" = "'Ayşe'" ] && [ "$(json "$body" 'array_key_exists( "email", $j["data"]["flxCartCustomerUpdate"]["cart"]["shippingAddress"] ?? array() ) ? $j["data"]["flxCartCustomerUpdate"]["cart"]["shippingAddress"]["email"] : "x"')" = NULL ] && ok "flxCartCustomerUpdate: billing address kept, shipping e-mail null" || bad "customer update" "$body"

# 6. Package extension point: a line with its own cart item data (as a donation), fixed quantity, own kind.
curl -s -o "$body" -X POST "$url/graphql" -H 'Content-Type: application/json' "${T[@]}" -d '{"queryId":"0affdcc6732e05c0ec0f9e99b2e0ec65d6acd8ce242154747d8346285cd74197","variables":{"input":{"productId":'"$p2"',"note":"For Zeynep"}}}'
line="$(json "$body" 'array_values( array_filter( $j["data"]["flxTestCartNoteAdd"]["cart"]["items"] ?? array(), fn( $i ) => "test-note" === $i["kind"] ) )[0] ?? null')"
[[ "$line" == *'"Note"'* && "$line" == *'For Zeynep'* ]] && ok "extension: FLXGQL_Cart::add_item() stores cart item data, itemData shows it" || bad "extension item data" "$body"
[[ "$line" == *'"editable":false'* ]] && ok "extension: fixed line is not editable (quantityLimits.editable)" || bad "extension editable" "$body"
[ -n "$(json "$body" '$j["data"]["flxTestCartNoteAdd"]["itemKey"] ?? ""' | tr -d "'")" ] && ok "extension: itemKey of the package line" || bad "extension key" "$body"

# 7. Sessions and CSRF.
op FlxCartItemAdd '{"input":{"productId":'"$p1"'}}' -H 'Cart-Token: aaa.bbb.ccc'
[ "$(json "$body" '$j["errors"][0]["extensions"]["code"] ?? null')" = "'UNAUTHENTICATED'" ] && ok "forged Cart-Token → UNAUTHENTICATED" || bad "forged token" "$body"
op FlxCartItemAdd '{"input":{"productId":'"$p1"'}}' -H 'Cookie: wp_woocommerce_session_fake=t_x'
[ "$(json "$body" '$j["errors"][0]["extensions"]["code"] ?? null')" = "'FORBIDDEN'" ] && ok "cookie session without Nonce → FORBIDDEN (CSRF)" || bad "cookie without nonce" "$body"
op FlxCartItemAdd '{"input":{"productId":'"$p1"'}}'
new="$(grep -i '^cart-token:' "$hdr" | head -1 | cut -d' ' -f2 | tr -d '\r')"
[ "$(json "$body" '$j["data"]["flxCartItemAdd"]["cart"]["itemsCount"] ?? null')" = 1 ] && [ -n "$new" ] && [ "$new" != "$token" ] && ok "fresh visitor (no cookie, no token): new cart + its own Cart-Token" || bad "fresh visitor add" "$body"

# 8. Clear.
op FlxCartClear '{"input":{}}' "${T[@]}"
[ "$(json "$body" '$j["data"]["flxCartClear"]["cart"]["itemsCount"] ?? null')" = 0 ] && ok "flxCartClear" || bad "clear" "$body"

# 9. No card data anywhere: the schema has no card fields; the debug log does not mention the cart token.
grep -qiE 'card(number|_number|Number)|cvv|cvc' "$root/packages/graphql/schema/schema.graphql" && bad "schema has card fields" || ok "schema: no card number / CVC fields"
[ -f "$site/wp-content/debug.log" ] && grep -q -- "$token" "$site/wp-content/debug.log" && bad "debug.log contains a cart token" || ok "debug.log: no cart token"

echo "$pass passed, $fail failed"
[ "$fail" = 0 ]
