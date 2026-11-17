<?php
/**
 * Flux Donate public GraphQL (part a). Documents are the persisted operations.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-donate/contract-test.php
 *
 * WooCommerce may be the Store API stand-in. The order assertion runs when WC_Order exists
 * (the CI job with FLX_CART_REQUIRE_WC=1).
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux Donate GraphQL (public)\n";

$GLOBALS['flxb_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXB_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxb_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 8, count( $GLOBALS['flxb_ops'] ), 'Flux Donate ships 8 persisted operations' );

/**
 * @param array<string,mixed> $vars Variables.
 */
function flxb_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxb_ops'][ $name ], $vars, $user );
}

add_filter(
	'flxb_kurban_enabled',
	static function () {
		return true;
	}
);
update_option(
	'flxb_proxy_texts',
	array(
		array(
			'version'    => 1,
			'text'       => 'Proxy text for the test.',
			'created_at' => '2026-01-01 00:00:00',
		),
	)
);
$GLOBALS['flxb_codes'] = array();
add_filter(
	'flxb_lookup_send',
	static function ( $via, $type, $value, $code ) {
		unset( $via, $type );
		$GLOBALS['flxb_codes'][ (string) $value ] = (string) $code;
		return 'email';
	},
	10,
	4
);

$open = FLXB_Types::save(
	array(
		'title'          => 'Saplings',
		'slug'           => 'gql-saplings',
		'description'    => 'A sapling donation.',
		'status'         => 'active',
		'currency'       => 'TRY',
		'allow_quantity' => 1,
		'options'        => array(
			array(
				'key'       => 'one',
				'label'     => 'One sapling',
				'price'     => 100,
				'unit'      => 'fidan',
				'active'    => 1,
				'units_per' => 1,
			),
		),
		'meta'           => array(
			'requires_proxy' => 1,
			'certificate'    => 1,
		),
	)
);
flxt_ok( is_int( $open ) && $open > 0, 'open type saved', $open );
$closed = FLXB_Types::save(
	array(
		'title'   => 'Closed cause',
		'slug'    => 'gql-closed',
		'status'  => 'closed',
		'options' => array(
			array(
				'key'    => 'one',
				'label'  => 'One',
				'price'  => 10,
				'active' => 1,
			),
		),
	)
);
$free = FLXB_Types::save(
	array(
		'title'      => 'Free amount',
		'slug'       => 'gql-free',
		'status'     => 'active',
		'allow_free' => 1,
		'min_amount' => 50,
		'currency'   => 'TRY',
	)
);
$archived = FLXB_Types::save(
	array(
		'title'   => 'Archived cause',
		'slug'    => 'gql-archived',
		'status'  => 'archived',
		'options' => array(
			array(
				'key'    => 'one',
				'label'  => 'One',
				'price'  => 10,
				'active' => 1,
			),
		),
	)
);
flxt_ok( is_int( $closed ) && is_int( $free ) && is_int( $archived ), 'other types saved' );

if ( ! class_exists( 'FLXB_WC_Products' ) ) {
	require_once FLXB_DIR . 'includes/wc/class-flxb-wc-products.php';
}
$pid = (int) wp_insert_post(
	array(
		'post_type'   => 'product',
		'post_status' => 'publish',
		'post_title'  => 'Saplings',
	)
);
update_post_meta( $pid, '_price', '100' );
FLXB_WC_Products::set_type_meta( (int) $open, 'wc_product_id', $pid );
flxt_ok( $pid > 0, 'donation product exists' );

// Anonymous type read. No internal form id, no donor row.
$GLOBALS['flxb_type_public_n'] = 0;
add_filter(
	'flxb_type_public',
	static function ( $out ) {
		++$GLOBALS['flxb_type_public_n'];
		return $out;
	}
);
$r = flxb_t_op( 'FlxDonationTypes', array( 'first' => 50 ) );
flxt_ok( $GLOBALS['flxb_type_public_n'] > 0, 'types: flxb_type_public ran' );
flxt_same( null, $r['errors'] ?? null, 'types: visitor reads' );
$nodes = $r['data']['flxDonationTypes']['nodes'] ?? array();
$ids   = array_map( 'intval', array_column( $nodes, 'databaseId' ) );
flxt_ok( in_array( (int) $open, $ids, true ), 'types: open type is listed' );
flxt_ok( in_array( (int) $closed, $ids, true ), 'types: a closed type is still listed' );
flxt_ok( ! in_array( (int) $archived, $ids, true ), 'types: archived type is left out' );
$mine = null;
foreach ( $nodes as $n ) {
	if ( (int) ( $n['databaseId'] ?? 0 ) === (int) $open ) {
		$mine = $n;
	}
}
flxt_same( 'Saplings', $mine['title'] ?? null, 'types: title' );
flxt_same( 'OPEN', $mine['state'] ?? null, 'types: open' );
flxt_same( 'one', $mine['options'][0]['key'] ?? null, 'types: option key' );
flxt_same( 100.0, $mine['options'][0]['price']['amount'] ?? null, 'types: price' );
flxt_same( 'TRY', $mine['options'][0]['price']['currency'] ?? null, 'types: price currency' );
$classic_type = flxb_get_type( (int) $open );
flxt_same( is_array( $classic_type ) ? $classic_type['title'] : null, $mine['title'] ?? null, 'types: classic reader sees the saved title' );
flxt_same( is_array( $classic_type ) ? $classic_type['options'][0]['price'] : null, $mine['options'][0]['price']['amount'] ?? null, 'types: classic price matches GraphQL' );
flxt_same( null, $mine['imageUrl'], 'types: no image is null' );
flxt_same( null, $mine['progress'], 'types: no goal is null' );
$blob = (string) wp_json_encode( $r );
flxt_ok( false === strpos( $blob, 'form_ids' ) && false === strpos( $blob, 'cardToken' ) && false === strpos( $blob, 'cvc' ) && false === strpos( $blob, 'secret' ), 'types: no form ids, card or secret' );

$r = flxb_t_op( 'FlxDonationType', array( 'slug' => 'gql-archived' ) );
flxt_same( null, $r['data']['flxDonationType'], 'type: archived is null' );
$r = flxb_t_op( 'FlxDonationType', array( 'id' => (int) $closed ) );
flxt_same( 'CLOSED', $r['data']['flxDonationType']['state'] ?? null, 'type: closed state' );

// IBAN, one source.
update_option(
	'woocommerce_bacs_accounts',
	array(
		array(
			'account_name' => 'Flux Association',
			'iban'         => 'TR33 0006 1005 1978 6457 8413 26',
			'bank_name'    => 'Test Bank',
		),
	)
);
if ( class_exists( 'FLXB_WC_Havale' ) ) {
	update_option(
		'woocommerce_flxb_havale_settings',
		array(
			'holder' => 'Flux Association',
			'iban'   => 'TR330006100519786457841326',
			'bank'   => 'Test Bank',
		)
	);
}
$r = flxb_t_op( 'FlxDonationBankAccount' );
flxt_same( null, $r['errors'] ?? null, 'iban: visitor reads' );
flxt_same( 'TR330006100519786457841326', $r['data']['flxDonationBankAccount']['iban'] ?? null, 'iban: from the bank transfer settings' );
flxt_same( 'Flux Association', $r['data']['flxDonationBankAccount']['holder'] ?? null, 'iban: holder' );

// The type prices are in TRY. A fresh WooCommerce shop is USD, and the classic line refuses an option in another currency.
update_option( 'woocommerce_currency', 'TRY' );
if ( class_exists( 'FLXB_WC' ) ) {
	FLXB_WC::save_settings( array( 'currencies' => array( 'TRY', 'USD', 'EUR' ) ) );
}

// Cart: hooks, one line, three names, one certificate recipient each.
$GLOBALS['flxb_line_hooks'] = 0;
$GLOBALS['flxb_recorded']   = 0;
$GLOBALS['flxb_last_line']  = null;
add_filter(
	'flxb_wc_line',
	static function ( $line ) {
		++$GLOBALS['flxb_line_hooks'];
		$GLOBALS['flxb_last_line'] = $line;
		return $line;
	}
);
add_action(
	'flxb_donation_recorded',
	static function () {
		++$GLOBALS['flxb_recorded'];
	}
);
$names = array( 'Ada', 'Bea', 'Cem' );
$r     = flxb_t_op(
	'FlxDonationCartAdd',
	array(
		'input' => array(
			'typeId'       => (int) $open,
			'option'       => 'one',
			'units'        => 3,
			'currency'     => 'TRY',
			'names'        => $names,
			'proxyConsent' => true,
		),
	)
);
flxt_same( null, $r['errors'] ?? null, 'cart: three names, no GraphQL error' );
flxt_same( array(), $r['data']['flxDonationCartAdd']['errors'] ?? null, 'cart: three names accepted' );
$items = $r['data']['flxDonationCartAdd']['cart']['items'] ?? array();
flxt_same( 1, count( $items ), 'cart: one line for three names' );
flxt_same( 'donation', $items[0]['kind'] ?? null, 'cart: kind donation' );
flxt_same( $names, $items[0]['flxDonation']['names'] ?? null, 'cart: the three names' );
flxt_same( $names, is_array( $GLOBALS['flxb_last_line'] ) ? ( $GLOBALS['flxb_last_line']['beneficiaries'] ?? null ) : null, 'cart: classic hook reads the names GraphQL wrote' );
flxt_same( null, $items[0]['flxDonation']['amount'] ?? null, 'cart: an option line has no free amount' );
flxt_same( 3, $items[0]['flxDonation']['units'] ?? null, 'cart: three units' );
flxt_same( 1, $GLOBALS['flxb_line_hooks'], 'cart: flxb_wc_line fired once' );
flxt_same( 0, $GLOBALS['flxb_recorded'], 'cart: flxb_donation_recorded does not fire on add' );
$cart_blob = (string) wp_json_encode( $r );
flxt_ok( false === strpos( $cart_blob, 'cardToken' ) && false === strpos( $cart_blob, 'cvc' ) && false === strpos( $cart_blob, 'cardUserKey' ), 'cart: no card data' );
$again = FLXB_WC_Cart::build_line( FLXB_Cart_Service::classic_input( array( 'typeId' => (int) $open, 'option' => 'one', 'units' => 3, 'currency' => 'TRY', 'names' => $names, 'proxyConsent' => true ) ) );
flxt_ok( is_array( $again ) && ( $again['beneficiaries'] ?? null ) === ( $GLOBALS['flxb_last_line']['beneficiaries'] ?? null ), 'cart: classic build_line keeps the same names' );
if ( ! class_exists( 'FLXB_WC_Certificates' ) ) {
	require_once FLXB_DIR . 'includes/wc/class-flxb-wc-certificates.php';
}
$stub = new class() {
	/**
	 * @param string $key Meta key.
	 */
	public function get_meta( $key ) {
		unset( $key );
		return '';
	}
};
$recs = FLXB_WC_Certificates::recipients( is_array( $GLOBALS['flxb_last_line'] ) ? $GLOBALS['flxb_last_line'] : array(), $stub, 'Donor', 3 );
flxt_same( array( 'Ada', 'Bea', 'Cem' ), array_column( $recs, 'name' ), 'cart: one certificate name each' );
if ( class_exists( 'WC_Order' ) && class_exists( 'WC_Order_Item_Product' ) && function_exists( 'wc_create_order' ) && is_array( $GLOBALS['flxb_last_line'] ) ) {
	$order = wc_create_order();
	$item  = new WC_Order_Item_Product();
	$item->set_product_id( $pid );
	$item->set_quantity( 1 );
	FLXB_WC_Cart::write_line_meta( $item, $GLOBALS['flxb_last_line'], 1 );
	$order->add_item( $item );
	$order->save();
	flxt_same( 1, count( $order->get_items() ), 'order: one line' );
	$meta = (string) $item->get_meta( 'flxb_names' );
	flxt_ok( false !== strpos( $meta, 'Ada' ) && false !== strpos( $meta, 'Bea' ) && false !== strpos( $meta, 'Cem' ), 'order: the three names are on that line', $meta );
} else {
	echo "  ok   order: skipped (WooCommerce order classes are not loaded)\n";
	++$GLOBALS['flxt_pass'];
}
if ( function_exists( 'WC' ) && is_object( WC() ) && WC()->cart ) {
	if ( class_exists( 'FLXGQL_Cart' ) ) {
		FLXGQL_Cart::clear();
	}
	$key = FLXB_WC_Cart::add(
		array(
			'type_id'        => (int) $open,
			'option'         => 'one',
			'units'          => 1,
			'proxy_consent'  => '1',
			'beneficiaries'  => array( 'Deniz' ),
		)
	);
	flxt_ok( is_string( $key ) && '' !== $key, 'classic add returns a cart key', $key );
	$back = flxt_gql( 'query { flxCart { items { key kind quantityLimits { editable } flxDonation { names units option } } } }' );
	flxt_same( null, $back['errors'] ?? null, 'classic line is readable from GraphQL' );
	$found = null;
	foreach ( $back['data']['flxCart']['items'] ?? array() as $item ) {
		if ( ( $item['key'] ?? '' ) === $key ) {
			$found = $item;
		}
	}
	flxt_same( array( 'Deniz' ), $found['flxDonation']['names'] ?? null, 'classic writer, GraphQL reader: the name' );
	flxt_same( 'donation', $found['kind'] ?? null, 'classic writer, GraphQL reader: kind' );
	flxt_same( false, $found['quantityLimits']['editable'] ?? null, 'donation line quantity is not editable in Store API' );
} else {
	echo "  ok   classic cart add skipped (WooCommerce cart is not loaded)\n";
	++$GLOBALS['flxt_pass'];
}

// Non-proxy multi-certificate: N saplings to different names = 1 cart line, N certificates.
$cert_type = FLXB_Types::save(
	array(
		'title'          => 'Sapling Certificate',
		'slug'           => 'gql-sapling-cert',
		'status'         => 'active',
		'currency'       => 'TRY',
		'allow_quantity' => 1,
		'options'        => array(
			array(
				'key'       => 'one',
				'label'     => 'One sapling',
				'price'     => 100,
				'unit'      => 'fidan',
				'active'    => 1,
				'units_per' => 1,
			),
		),
		'meta'           => array(
			'certificate' => 1,
		),
	)
);
$cert_pid = (int) wp_insert_post(
	array(
		'post_type'   => 'product',
		'post_status' => 'publish',
		'post_title'  => 'Sapling Certificate',
	)
);
update_post_meta( $cert_pid, '_price', '100' );
FLXB_WC_Products::set_type_meta( (int) $cert_type, 'wc_product_id', $cert_pid );
if ( class_exists( 'FLXGQL_Cart' ) ) {
	FLXGQL_Cart::clear();
}
// The classic contact gate: a certificate for someone else needs a WhatsApp number or an e-mail address.
$GLOBALS['flxb_last_line'] = null;
$no_contact                = array(
	'typeId'   => (int) $cert_type,
	'option'   => 'one',
	'currency' => 'TRY',
	'names'    => $names,
);
$r                         = flxb_t_op( 'FlxDonationCartAdd', array( 'input' => $no_contact ) );
$classic_need              = FLXB_WC_Form::contact_missing( FLXB_WC_Form::input( array( 'flxb_type' => (int) $cert_type, 'for_other' => '1', 'ben_name' => 'Ada' ) ) );
flxt_ok( '' !== $classic_need, 'cart: the classic form refuses a certificate without contact', $classic_need );
flxt_same( $classic_need, $r['data']['flxDonationCartAdd']['errors'][0]['message'] ?? null, 'cart: no contact → the classic refusal message' );
flxt_same( array( 'INVALID_INPUT', 'contacts' ), array( $r['data']['flxDonationCartAdd']['errors'][0]['code'] ?? null, $r['data']['flxDonationCartAdd']['errors'][0]['field'] ?? null ), 'cart: the refusal points at contacts' );
flxt_same( null, $GLOBALS['flxb_last_line'], 'cart: a refused line is not added' );
$r = flxb_t_op( 'FlxDonationCartAdd', array( 'input' => $no_contact + array( 'contacts' => array( array( 'email' => 'not-an-address' ) ) ) ) );
$classic_mail = FLXB_WC_Form::contact_missing( FLXB_WC_Form::input( array( 'flxb_type' => (int) $cert_type, 'for_other' => '1', 'ben_name' => 'Ada', 'ben_email' => 'not-an-address' ) ) );
flxt_same( __( 'Please enter a valid e-mail address.', 'flx-donate' ), $classic_mail, 'cart: the classic form calls that e-mail wrong' );
flxt_same( $classic_mail, $r['data']['flxDonationCartAdd']['errors'][0]['message'] ?? null, 'cart: a wrong e-mail is refused like the classic form' );
$r = flxb_t_op( 'FlxDonationCartAdd', array( 'input' => $no_contact + array( 'contacts' => array( array( 'phone' => '123' ) ) ) ) );
$classic_tel = FLXB_WC_Form::contact_missing( FLXB_WC_Form::input( array( 'flxb_type' => (int) $cert_type, 'for_other' => '1', 'ben_name' => 'Ada', 'ben_phone' => '123' ) ) );
flxt_ok( '' !== $classic_tel, 'cart: the classic form refuses a short phone number', $classic_tel );
flxt_same( $classic_tel, $r['data']['flxDonationCartAdd']['errors'][0]['message'] ?? null, 'cart: a short phone number is refused like the classic form' );
$r = flxb_t_op(
	'FlxDonationCartAdd',
	array(
		'input' => $no_contact + array(
			'feeCover' => true,
			'contacts' => array(
				array( 'email' => 'ada.gql@example.com' ),
				array( 'phone' => '05321234567' ),
				array( 'email' => 'cem.gql@example.com' ),
			),
		),
	)
);
flxt_same( array(), $r['data']['flxDonationCartAdd']['errors'] ?? null, 'cart: non-proxy multi-name accepted' );
$cert_items = $r['data']['flxDonationCartAdd']['cart']['items'] ?? array();
flxt_same( 1, count( $cert_items ), 'cart: non-proxy multi-name is one line' );
flxt_same( 3, $cert_items[0]['flxDonation']['units'] ?? null, 'cart: non-proxy multi-name defaults units to name count' );
flxt_same( $names, $cert_items[0]['flxDonation']['names'] ?? null, 'cart: non-proxy multi-name keeps all names' );
$kept = is_array( $GLOBALS['flxb_last_line'] ) ? $GLOBALS['flxb_last_line'] : array();
flxt_same( 'ada.gql@example.com', $kept['ben_email'] ?? null, 'cart: the first contact is on the line (classic ben_email)' );
flxt_same( array( FLXB_WC_Form::phone_value( '05321234567' ), 'cem.gql@example.com' ), array( $kept['people'][0]['phone'] ?? null, $kept['people'][1]['email'] ?? null ), 'cart: the other contacts are on the people rows' );
if ( class_exists( 'FLXB_WC_Fee', false ) && FLXB_WC_Fee::on() && function_exists( 'WC' ) && WC()->session ) {
	flxt_same( 1, (int) WC()->session->get( FLXB_WC_Fee::SESSION ), 'cart: the fee choice is remembered like the form does' );
}
$cert_recs = FLXB_WC_Certificates::recipients_with_contacts( is_array( $GLOBALS['flxb_last_line'] ) ? $GLOBALS['flxb_last_line'] : array(), $stub, 'Donor', 3, '', '', '', '' );
flxt_same( array( 'Ada', 'Bea', 'Cem' ), array_column( $cert_recs, 'name' ), 'cart: non-proxy multi-name issues one certificate per name' );
flxt_same( array( 1, 1, 1 ), array_column( $cert_recs, 'count' ), 'cart: each certificate gets 1 sapling' );

// Single line, invalid amount, closed type.
if ( class_exists( 'FLXGQL_Cart' ) ) {
	FLXGQL_Cart::clear();
}
$r = flxb_t_op(
	'FlxDonationCartAdd',
	array(
		'input' => array(
			'typeId'       => (int) $open,
			'option'       => 'one',
			'units'        => 1,
			'currency'     => 'TRY',
			'proxyConsent' => true,
		),
	)
);
flxt_same( array(), $r['data']['flxDonationCartAdd']['errors'] ?? null, 'cart: one sapling' );
flxt_same( 1, count( $r['data']['flxDonationCartAdd']['cart']['items'] ?? array() ), 'cart: single line' );
flxt_same( array(), $r['data']['flxDonationCartAdd']['cart']['items'][0]['flxDonation']['names'] ?? null, 'cart: no extra names' );
$r = flxb_t_op(
	'FlxDonationCartAdd',
	array(
		'input' => array(
			'typeId'   => (int) $free,
			'amount'   => '1',
			'currency' => 'TRY',
		),
	)
);
flxt_ok( ! empty( $r['data']['flxDonationCartAdd']['errors'] ), 'cart: amount below the minimum is a payload error' );
flxt_same( 'amount', $r['data']['flxDonationCartAdd']['errors'][0]['field'] ?? null, 'cart: the error points at amount' );
$r = flxb_t_op(
	'FlxDonationCartAdd',
	array(
		'input' => array(
			'typeId' => (int) $closed,
			'option' => 'one',
		),
	)
);
flxt_ok( ! empty( $r['data']['flxDonationCartAdd']['errors'] ), 'cart: a closed type is refused' );
if ( ! class_exists( 'FLXB_WC_Currency', false ) ) {
	require_once FLXB_DIR . 'includes/wc/class-flxb-wc-currency.php'; // Not booted without WooCommerce; the cart service uses it when it is there.
}
$r = flxb_t_op( 'FlxDonationCartAdd', array( 'input' => array( 'typeId' => (int) $open, 'option' => 'one', 'currency' => 'ZZZ', 'proxyConsent' => true ) ) );
flxt_same( array( 'currency', sprintf( __( 'Donations in %s cannot be paid here at the moment.', 'flx-donate' ), 'ZZZ' ) ), array( $r['data']['flxDonationCartAdd']['errors'][0]['field'] ?? null, $r['data']['flxDonationCartAdd']['errors'][0]['message'] ?? null ), 'cart: a currency that cannot be set is refused like the classic cart' );

// Store API quantity: fixed exactly where the classic cart shows a fixed 1.
$qk = FLXB_WC_Cart::KEY;
flxt_same( true, FLXB_WC_Cart::quantity_editable( true, null, array( $qk => array( 'option' => 'one', 'units' => 1 ) ) ), 'quantity: a single option line stays editable' );
flxt_same( false, FLXB_WC_Cart::quantity_editable( true, null, array( $qk => array( 'option' => '', 'units' => 1 ) ) ), 'quantity: a free amount is fixed' );
flxt_same( false, FLXB_WC_Cart::quantity_editable( true, null, array( $qk => array( 'option' => 'one', 'units' => 3 ) ) ), 'quantity: a custom count is fixed' );
flxt_same( false, FLXB_WC_Cart::quantity_editable( true, null, array( $qk => array( 'option' => 'one', 'units' => 1, 'monthly' => 1 ) ) ), 'quantity: a monthly line is fixed' );
flxt_same( true, FLXB_WC_Cart::quantity_editable( true, null, array( 'quantity' => 2 ) ), 'quantity: other products are left alone' );

// My donations: owner only.
$owner = (int) wp_insert_user(
	array(
		'user_login' => 'flxb_owner_' . wp_rand(),
		'user_pass'  => wp_generate_password(),
		'user_email' => 'owner.gql@example.com',
		'role'       => 'subscriber',
	)
);
$other = (int) wp_insert_user(
	array(
		'user_login' => 'flxb_other_' . wp_rand(),
		'user_pass'  => wp_generate_password(),
		'user_email' => 'other.gql@example.com',
		'role'       => 'subscriber',
	)
);
update_option( 'timezone_string', 'Europe/Istanbul' );
$did = FLXB_Donations::record(
	array(
		'provider'     => 'test',
		'provider_ref' => 'gql-' . wp_rand(),
		'amount'       => 100,
		'currency'     => 'TRY',
		'status'       => 'completed',
		'type_id'      => (int) $open,
		'created_at'   => '2026-06-15 12:00:00',
		'donor'        => array(
			'name'  => 'Owner',
			'email' => 'owner.gql@example.com',
		),
	)
);
flxt_ok( $did > 0, 'a donation was recorded', $did );
$r = flxb_t_op( 'FlxMyDonations' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'my donations: visitor → UNAUTHENTICATED' );
$r = flxb_t_op( 'FlxMyDonations', array(), $other );
flxt_same( null, $r['errors'] ?? null, 'my donations: another account reads' );
flxt_same( array(), $r['data']['flxMyDonations']['nodes'] ?? null, 'my donations: another account sees none' );
$r = flxb_t_op( 'FlxMyDonations', array(), $owner );
$got = array_map( 'intval', array_column( $r['data']['flxMyDonations']['nodes'] ?? array(), 'databaseId' ) );
flxt_ok( in_array( (int) $did, $got, true ), 'my donations: the owner sees their donation' );
$mine_gift = null;
foreach ( $r['data']['flxMyDonations']['nodes'] ?? array() as $node ) {
	if ( (int) ( $node['databaseId'] ?? 0 ) === (int) $did ) {
		$mine_gift = $node;
	}
}
flxt_same( '2026-06-15T12:00:00Z', $mine_gift['date']['iso'] ?? null, 'my donations: stored UTC stays UTC in Istanbul' );
flxt_same( strtotime( '2026-06-15 12:00:00 UTC' ), $mine_gift['date']['timestamp'] ?? null, 'my donations: timestamp' );
flxt_same( 100.0, $mine_gift['amount']['amount'] ?? null, 'my donations: amount' );
flxt_same( 'TRY', $mine_gift['amount']['currency'] ?? null, 'my donations: currency' );
$classic_gifts = FLXB_App::gifts( $owner );
$classic_gift  = null;
foreach ( $classic_gifts as $group ) {
	if ( (int) ( $group[0]['id'] ?? 0 ) === (int) $did ) {
		$classic_gift = FLXB_App::gift_out( $group );
	}
}
flxt_same( is_array( $classic_gift ) ? $classic_gift['title'] : null, $mine_gift['title'] ?? null, 'my donations: classic gift_out title' );
flxt_same( is_array( $classic_gift ) ? $classic_gift['amount'] : null, $mine_gift['amount']['amount'] ?? null, 'my donations: classic gift_out amount' );
$owner_blob = (string) wp_json_encode( $r );
flxt_ok( false === strpos( $owner_blob, 'cardToken' ), 'my donations: no card data' );
flxt_ok( false === strpos( $owner_blob, 'owner.gql@example.com' ), 'my donations: the address is not in the payload' );

// Lookup: same answer, then the session, then the rate limit.
wp_set_current_user( 0 );
$known = flxb_t_op( 'FlxDonationLookupStart', array( 'input' => array( 'login' => 'owner.gql@example.com' ) ) );
$miss  = flxb_t_op( 'FlxDonationLookupStart', array( 'input' => array( 'login' => 'nobody.gql@example.com' ) ) );
flxt_same( null, $known['errors'] ?? null, 'lookup start: known address' );
flxt_same( null, $miss['errors'] ?? null, 'lookup start: unknown address' );
flxt_same( $known['data']['flxDonationLookupStart'] ?? null, $miss['data']['flxDonationLookupStart'] ?? null, 'lookup start: the same answer either way' );
flxt_same( true, $known['data']['flxDonationLookupStart']['ok'] ?? null, 'lookup start: ok' );
$code_key = 'flxb_lkc_' . FLXB_Lookup::h( 'owner.gql@example.com' );
$code_ttl = (int) get_option( '_transient_timeout_' . $code_key );
flxt_ok( abs( $code_ttl - ( time() + FLXB_Lookup::TTL ) ) < 30, 'lookup start: the code uses the classic ttl', $code_ttl );
$bad = flxb_t_op( 'FlxDonationLookupVerify', array( 'input' => array( 'login' => 'nobody.gql@example.com', 'code' => '123456' ) ) );
flxt_ok( ! empty( $bad['data']['flxDonationLookupVerify']['errors'] ), 'lookup verify: unknown address fails' );
$code = (string) ( $GLOBALS['flxb_codes']['owner.gql@example.com'] ?? '' );
flxt_ok( 6 === strlen( $code ), 'lookup: a code was issued only for the known address' );
$ok = flxb_t_op( 'FlxDonationLookupVerify', array( 'input' => array( 'login' => 'owner.gql@example.com', 'code' => $code ) ) );
flxt_same( true, $ok['data']['flxDonationLookupVerify']['ok'] ?? null, 'lookup verify: the code opens the session' );
flxt_ok( isset( $_COOKIE[ FLXB_Lookup::COOKIE ] ) && '' !== (string) $_COOKIE[ FLXB_Lookup::COOKIE ], 'lookup verify: the classic cookie is set' );
$session_key = 'flxb_lks_' . FLXB_Lookup::h( (string) $_COOKIE[ FLXB_Lookup::COOKIE ] );
$session_ttl = (int) get_option( '_transient_timeout_' . $session_key );
flxt_ok( abs( $session_ttl - ( time() + FLXB_Lookup::SESSION ) ) < 30, 'lookup verify: the session uses the classic ttl', $session_ttl );
$r = flxb_t_op( 'FlxMyDonations' );
$got = array_map( 'intval', array_column( $r['data']['flxMyDonations']['nodes'] ?? array(), 'databaseId' ) );
flxt_ok( in_array( (int) $did, $got, true ), 'lookup session sees that donor\'s donations' );
$lookup_opt = get_option( FLXB_Lookup::OPTION, false );
update_option( FLXB_Lookup::OPTION, array( 'on' => 0 ) );
$r = flxb_t_op( 'FlxMyDonations' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'my donations: a lookup session is ignored while the lookup is off' );
$off = flxb_t_op( 'FlxDonationLookupVerify', array( 'input' => array( 'login' => 'owner.gql@example.com', 'code' => '123456' ) ) );
flxt_ok( null !== flxt_code( $off ) || ! empty( $off['data']['flxDonationLookupVerify']['errors'] ), 'lookup verify: refused while the lookup is off' );
flxt_same( __( 'Donation lookup is not available.', 'flx-donate' ), $off['errors'][0]['message'] ?? ( $off['data']['flxDonationLookupVerify']['errors'][0]['message'] ?? null ), 'lookup verify: the same message as lookup start' );
if ( false === $lookup_opt ) {
	delete_option( FLXB_Lookup::OPTION );
} else {
	update_option( FLXB_Lookup::OPTION, $lookup_opt );
}
$end = flxb_t_op( 'FlxDonationLookupEnd', array( 'input' => array() ) );
flxt_same( true, $end['data']['flxDonationLookupEnd']['ok'] ?? null, 'lookup end' );
$r = flxb_t_op( 'FlxMyDonations' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'my donations: the session is gone' );

$limited = null;
for ( $i = 0; $i < 4; $i++ ) {
	$limited = flxb_t_op( 'FlxDonationLookupStart', array( 'input' => array( 'login' => 'rate.gql@example.com' ) ) );
}
flxt_same( 'RATE_LIMITED', flxt_code( is_array( $limited ) ? $limited : array() ), 'lookup start: rate limit' );

// The public types tag is purged when a type or a gift counted in it changes.
$GLOBALS['flxb_purged'] = array();
add_action(
	'flx_gql_purged',
	static function ( $tags ) {
		$GLOBALS['flxb_purged'] = array_merge( $GLOBALS['flxb_purged'], (array) $tags );
	}
);
$purge_opts = array( array( 'key' => 'p', 'label' => 'P', 'price' => 10, 'unit' => 'fidan', 'active' => 1, 'units_per' => 1 ) );
$purge_type = FLXB_Types::save( array( 'title' => 'Purge type', 'status' => 'active', 'currency' => 'TRY', 'options' => $purge_opts ) );
flxt_ok( is_int( $purge_type ) && $purge_type > 0 && in_array( FLXB_Type_Public_Service::TAG, $GLOBALS['flxb_purged'], true ), 'cache: creating a type purges flx-donate:types' );
$GLOBALS['flxb_purged'] = array();
FLXB_Types::save( array( 'title' => 'Purge type 2', 'status' => 'active', 'currency' => 'TRY', 'options' => $purge_opts ), (int) $purge_type );
flxt_ok( in_array( FLXB_Type_Public_Service::TAG, $GLOBALS['flxb_purged'], true ), 'cache: saving a type purges flx-donate:types' );
$GLOBALS['flxb_purged'] = array();
FLXB_Donations::record(
	array(
		'provider'     => 'test',
		'provider_ref' => 'gql-purge-' . wp_rand(),
		'amount'       => 10,
		'currency'     => 'TRY',
		'status'       => 'completed',
		'type_id'      => (int) $open,
		'donor'        => array( 'name' => 'Purge', 'email' => 'purge.gql@example.com' ),
	)
);
flxt_ok( in_array( FLXB_Type_Public_Service::TAG, $GLOBALS['flxb_purged'], true ), 'cache: a recorded donation purges flx-donate:types (progress)' );
$GLOBALS['flxb_purged'] = array();
$GLOBALS['flxb_purged'] = array();
FLXB_Types::delete( (int) $purge_type );
flxt_ok( in_array( FLXB_Type_Public_Service::TAG, $GLOBALS['flxb_purged'], true ), 'cache: deleting a type purges flx-donate:types' );

flxt_done();
