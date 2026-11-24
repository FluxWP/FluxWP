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
flxt_same( 69, count( $GLOBALS['flxb_ops'] ), 'Flux Donate ships 69 persisted operations' );

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
	flxt_same( true, $found['quantityLimits']['editable'] ?? null, 'a single option line keeps the Store API quantity as the classic cart does' );
	$free_key = FLXB_WC_Cart::add(
		array(
			'type_id' => (int) $free,
			'option'  => '',
			'amount'  => '500',
		)
	);
	flxt_ok( is_string( $free_key ) && '' !== $free_key, 'classic add of a free amount returns a cart key', $free_key );
	$back = flxt_gql( 'query { flxCart { items { key quantityLimits { editable } } } }' );
	$free_item = null;
	foreach ( $back['data']['flxCart']['items'] ?? array() as $item ) {
		if ( ( $item['key'] ?? '' ) === $free_key ) {
			$free_item = $item;
		}
	}
	flxt_same( false, $free_item['quantityLimits']['editable'] ?? null, 'a free-amount line quantity is not editable in Store API' );
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


// Admin screens (2.37.0): dashboard, list (own-only scoping), bulk rights.
echo "Flux Donate GraphQL (admin)\n";
$admin = (int) wp_insert_user(
	array(
		'user_login' => 'flxb_admin_' . wp_rand(),
		'user_pass'  => wp_generate_password(),
		'user_email' => 'admin' . wp_rand() . '.gql@example.com',
		'role'       => 'administrator',
	)
);
$staff_a = (int) wp_insert_user( array( 'user_login' => 'flxb_sa_' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'sa' . wp_rand() . '.gql@example.com', 'role' => 'subscriber' ) );
$staff_b = (int) wp_insert_user( array( 'user_login' => 'flxb_sb_' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'sb' . wp_rand() . '.gql@example.com', 'role' => 'subscriber' ) );
foreach ( array( $staff_a, $staff_b ) as $u ) {
	$wu = new WP_User( $u );
	$wu->add_cap( FLXB_Refs::CAP_REF );
	$wu->add_cap( FLXB_Refs::CAP_OWN );
}
global $wpdb;
$ref_a = (int) FLXB_Donations::record( array( 'provider' => 'test', 'provider_ref' => 'gql-ra-' . wp_rand(), 'amount' => 40, 'currency' => 'TRY', 'status' => 'completed', 'type_id' => (int) $open, 'donor' => array( 'name' => 'Ref A', 'email' => 'refa.gql@example.com' ) ) );
$ref_b = (int) FLXB_Donations::record( array( 'provider' => 'test', 'provider_ref' => 'gql-rb-' . wp_rand(), 'amount' => 60, 'currency' => 'TRY', 'status' => 'completed', 'type_id' => (int) $open, 'donor' => array( 'name' => 'Ref B', 'email' => 'refb.gql@example.com' ) ) );
$wpdb->update( flxb_table( 'donations' ), array( 'ref_user_id' => $staff_a ), array( 'id' => $ref_a ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->update( flxb_table( 'donations' ), array( 'ref_user_id' => $staff_b ), array( 'id' => $ref_b ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$r = flxb_t_op( 'FlxDonateDashboard', array( 'range' => 'DAYS_30' ) );
flxt_ok( null !== flxt_code( $r ), 'dashboard: visitor is refused' );
$r = flxb_t_op( 'FlxDonateDashboard', array( 'range' => 'DAYS_30' ), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'dashboard: own-only staff without flxb_dashboard is refused' );
$r = flxb_t_op( 'FlxDonateDashboard', array( 'range' => 'DAYS_90' ), $admin );
flxt_same( null, $r['errors'] ?? null, 'dashboard: an administrator reads' );
$dash = $r['data']['flxDonationDashboard'] ?? array();
flxt_same( true, $dash['hasAny'] ?? null, 'dashboard: hasAny' );
flxt_ok( isset( $dash['now']['total'], $dash['series'] ) && count( $dash['series'] ) >= 90, 'dashboard: totals and one point per day' );
$r = flxb_t_op( 'FlxDonateDashboard', array( 'range' => 'CUSTOM', 'from' => '2026-06-01', 'to' => '2026-06-30' ), $admin );
flxt_same( '2026-06-01', $r['data']['flxDonationDashboard']['from'] ?? null, 'dashboard: custom range from' );
flxt_same( 30, count( $r['data']['flxDonationDashboard']['series'] ?? array() ), 'dashboard: custom range days' );

$r = flxb_t_op( 'FlxDonateDonations', array( 'first' => 25, 'after' => null ) );
flxt_ok( null !== flxt_code( $r ), 'list: visitor is refused' );
$r = flxb_t_op( 'FlxDonateDonations', array( 'first' => 25, 'after' => null ), $admin );
flxt_same( null, $r['errors'] ?? null, 'list: an administrator reads' );
$ids = array_map( 'intval', array_column( $r['data']['flxDonationList']['nodes'] ?? array(), 'databaseId' ) );
flxt_ok( in_array( $ref_a, $ids, true ) && in_array( $ref_b, $ids, true ), 'list: the administrator sees every donation' );
flxt_same( true, $r['data']['flxDonationListInfo']['canManage'] ?? null, 'list: the administrator manages' );
$r = flxb_t_op( 'FlxDonateDonations', array( 'first' => 25, 'after' => null ), $staff_a );
flxt_same( null, $r['errors'] ?? null, 'list: own-only staff reads' );
$ids = array_map( 'intval', array_column( $r['data']['flxDonationList']['nodes'] ?? array(), 'databaseId' ) );
flxt_same( array( $ref_a ), $ids, 'list: own-only staff sees only their referrals' );
flxt_same( true, $r['data']['flxDonationListInfo']['ownOnly'] ?? null, 'list: ownOnly' );
$r = flxb_t_op( 'FlxDonateDonations', array( 'first' => 25, 'after' => null, 'filter' => array( 'referralId' => $staff_b ) ), $staff_a );
$ids = array_map( 'intval', array_column( $r['data']['flxDonationList']['nodes'] ?? array(), 'databaseId' ) );
flxt_same( array( $ref_a ), $ids, 'list: own-only staff cannot widen the referral filter' );
$r = flxb_t_op( 'FlxDonateDonations', array( 'first' => 1, 'after' => null, 'filter' => array( 'search' => 'refb.gql@example.com' ) ), $admin );
flxt_same( array( $ref_b ), array_map( 'intval', array_column( $r['data']['flxDonationList']['nodes'] ?? array(), 'databaseId' ) ), 'list: search by e-mail' );
$r = flxb_t_op( 'FlxDonateDonations', array( 'first' => 1, 'after' => null ), $admin );
$total = (int) ( $r['data']['flxDonationList']['totalCount'] ?? 0 );
$first = (int) ( $r['data']['flxDonationList']['nodes'][0]['databaseId'] ?? 0 );
flxt_ok( $total >= 3 && true === ( $r['data']['flxDonationList']['pageInfo']['hasNextPage'] ?? null ), 'list: first=1 has a next page' );
$r = flxb_t_op( 'FlxDonateDonations', array( 'first' => 1, 'after' => base64_encode( 'flxo:0' ) ), $admin ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
$second = (int) ( $r['data']['flxDonationList']['nodes'][0]['databaseId'] ?? 0 );
flxt_ok( $second > 0 && $second !== $first, 'list: the offset cursor opens the next row' );

$r = flxb_t_op( 'FlxDonationsBulk', array( 'ids' => array( $ref_a ), 'action' => 'TRASH' ), $staff_a );
flxt_ok( null !== flxt_code( $r ) || ! empty( $r['data']['flxDonationsBulk']['errors'] ), 'bulk: own-only staff is refused' );
$r = flxb_t_op( 'FlxDonationsBulk', array( 'ids' => array( $ref_a ), 'action' => 'TRASH' ), $admin );
flxt_same( null, $r['errors'] ?? null, 'bulk: an administrator moves to the trash' );
flxt_same( 1, $r['data']['flxDonationsBulk']['done'] ?? null, 'bulk: one donation moved' );
$r = flxb_t_op( 'FlxDonateDonations', array( 'first' => 25, 'after' => null, 'filter' => array( 'status' => 'TRASH' ) ), $admin );
flxt_ok( in_array( $ref_a, array_map( 'intval', array_column( $r['data']['flxDonationList']['nodes'] ?? array(), 'databaseId' ) ), true ), 'bulk: the donation is in the trash list' );
$r = flxb_t_op( 'FlxDonationsBulk', array( 'ids' => array( $ref_a ), 'action' => 'RESTORE' ), $admin );
flxt_same( 1, $r['data']['flxDonationsBulk']['done'] ?? null, 'bulk: restore' );

// Donation page (2.38.0): read rights, own-only scoping, actions.
$r = flxb_t_op( 'FlxDonateDetail', array( 'id' => $ref_b ) );
flxt_ok( null !== flxt_code( $r ), 'detail: visitor is refused' );
$r = flxb_t_op( 'FlxDonateDetail', array( 'id' => $ref_b ), $admin );
flxt_same( null, $r['errors'] ?? null, 'detail: an administrator reads' );
$dv = $r['data']['flxDonationDetail'] ?? array();
flxt_same( $ref_b, (int) ( $dv['databaseId'] ?? 0 ), 'detail: the donation' );
flxt_ok( count( $dv['lines']['rows'] ?? array() ) >= 1 && count( $dv['hero'] ?? array() ) >= 4, 'detail: items and summary' );
flxt_ok( ! empty( $dv['manage']['statuses'] ) && null !== ( $dv['referral'] ?? null ), 'detail: the administrator manages and sees the referral' );
flxt_same( array(), $dv['islands'] ?? null, 'detail: no islands outside the page' );
flxt_same( false, strpos( (string) ( $dv['receiptUrl'] ?? '' ), '&amp;' ), 'detail: receipt link is not HTML-escaped' );
$r = flxb_t_op( 'FlxDonateDetail', array( 'id' => $ref_b ), $staff_a );
flxt_ok( array_key_exists( 'flxDonationDetail', (array) ( $r['data'] ?? array() ) ) && null === $r['data']['flxDonationDetail'], 'detail: own-only staff cannot open another referral' );
$r = flxb_t_op( 'FlxDonateDetail', array( 'id' => $ref_a ), $staff_a );
$dv = $r['data']['flxDonationDetail'] ?? array();
flxt_same( $ref_a, (int) ( $dv['databaseId'] ?? 0 ), 'detail: own-only staff opens their referral' );
flxt_ok( array_key_exists( 'manage', $dv ) && null === $dv['manage'] && null === $dv['referral'], 'detail: own-only staff gets no actions or referral picker' );
$r = flxb_t_op( 'FlxDonationSetStatus', array( 'id' => $ref_a, 'status' => 'pending' ), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'status: own-only staff is refused' );
$r = flxb_t_op( 'FlxDonationSetStatus', array( 'id' => $ref_b, 'status' => 'nope' ), $admin );
flxt_ok( ! empty( $r['data']['flxDonationSetStatus']['errors'] ) || null !== flxt_code( $r ), 'status: an unknown status is refused' );
$r = flxb_t_op( 'FlxDonationSetStatus', array( 'id' => $ref_b, 'status' => 'pending' ), $admin );
flxt_same( null, $r['errors'] ?? null, 'status: an administrator changes it' );
flxt_same( 'pending', (string) ( FLXB_Donations::get( $ref_b )['status'] ?? '' ), 'status: saved' );
flxb_t_op( 'FlxDonationSetStatus', array( 'id' => $ref_b, 'status' => 'completed' ), $admin );
$r = flxb_t_op( 'FlxDonationSetReferral', array( 'id' => $ref_b, 'referralId' => $staff_a ), $admin );
flxt_same( null, $r['errors'] ?? null, 'referral: an administrator changes it' );
flxt_same( $staff_a, (int) ( FLXB_Donations::get( $ref_b )['ref_user_id'] ?? 0 ), 'referral: saved' );
$wpdb->update( flxb_table( 'donations' ), array( 'ref_user_id' => $staff_b ), array( 'id' => $ref_b ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$r = flxb_t_op( 'FlxDonationApprove', array( 'id' => $ref_b ), $admin );
flxt_ok( ! empty( $r['data']['flxDonationApprove']['errors'] ) || null !== flxt_code( $r ), 'approve: a paid gift is not awaiting approval' );
$r = flxb_t_op( 'FlxDonationNoteAdd', array( 'id' => $ref_b, 'text' => 'x' ), $admin );
flxt_ok( ! empty( $r['data']['flxDonationNoteAdd']['errors'] ) || null !== flxt_code( $r ), 'note: a gift without an order has no notes' );
$r = flxb_t_op( 'FlxDonationLayoutSave', array( 'main' => array( 'payment', 'lines' ), 'side' => array( 'donor' ), 'closed' => array( 'history' ) ), $admin );
flxt_same( true, $r['data']['flxDonationLayoutSave']['ok'] ?? null, 'layout: saved' );
$r = flxb_t_op( 'FlxDonateDetail', array( 'id' => $ref_b ), $admin );
flxt_same( array( 'main' => array( 'payment', 'lines' ), 'side' => array( 'donor' ), 'closed' => array( 'history' ) ), $r['data']['flxDonationDetail']['layout'] ?? null, 'layout: read back' );

// Donors and recurring donations (2.39.0): rights, own-only scoping, card, save, cancel.
$wu = new WP_User( $staff_a );
$wu->add_cap( FLXB_Refs::CAP_DONORS );
$donor_a = (int) ( FLXB_Donations::get( $ref_a )['donor_id'] ?? 0 );
$donor_b = (int) ( FLXB_Donations::get( $ref_b )['donor_id'] ?? 0 );
flxt_ok( $donor_a > 0 && $donor_b > 0 && $donor_a !== $donor_b, 'donors: the two referral gifts have their own donors' );
$r = flxb_t_op( 'FlxDonateDonors', array( 'first' => 25, 'after' => null ) );
flxt_ok( null !== flxt_code( $r ), 'donors: visitor is refused' );
$r = flxb_t_op( 'FlxDonateDonors', array( 'first' => 100, 'after' => null ), $admin );
flxt_same( null, $r['errors'] ?? null, 'donors: an administrator reads' );
$ids = array_map( 'intval', array_column( $r['data']['flxDonorList']['nodes'] ?? array(), 'databaseId' ) );
flxt_ok( in_array( $donor_a, $ids, true ) && in_array( $donor_b, $ids, true ), 'donors: the administrator sees every donor' );
flxt_same( false, $r['data']['flxDonorListInfo']['ownOnly'] ?? null, 'donors: not own-only' );
$r = flxb_t_op( 'FlxDonateDonors', array( 'first' => 100, 'after' => null ), $staff_a );
flxt_same( null, $r['errors'] ?? null, 'donors: own-only staff reads' );
flxt_same( array( $donor_a ), array_map( 'intval', array_column( $r['data']['flxDonorList']['nodes'] ?? array(), 'databaseId' ) ), 'donors: own-only staff sees only their donors' );
flxt_same( true, $r['data']['flxDonorListInfo']['ownOnly'] ?? null, 'donors: ownOnly' );
$r = flxb_t_op( 'FlxDonateDonors', array( 'first' => 5, 'after' => null, 'filter' => array( 'search' => 'refb.gql@example.com' ) ), $admin );
flxt_same( array( $donor_b ), array_map( 'intval', array_column( $r['data']['flxDonorList']['nodes'] ?? array(), 'databaseId' ) ), 'donors: search by e-mail' );
$r = flxb_t_op( 'FlxDonateDonors', array( 'first' => 1, 'after' => null, 'filter' => array( 'orderBy' => 'NAME' ) ), $admin );
$first = (int) ( $r['data']['flxDonorList']['nodes'][0]['databaseId'] ?? 0 );
flxt_same( true, $r['data']['flxDonorList']['pageInfo']['hasNextPage'] ?? null, 'donors: first=1 has a next page' );
$r = flxb_t_op( 'FlxDonateDonors', array( 'first' => 1, 'after' => base64_encode( 'flxo:0' ), 'filter' => array( 'orderBy' => 'NAME' ) ), $admin ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
$second = (int) ( $r['data']['flxDonorList']['nodes'][0]['databaseId'] ?? 0 );
flxt_ok( $second > 0 && $second !== $first, 'donors: the offset cursor opens the next row' );

$r = flxb_t_op( 'FlxDonateDonor', array( 'id' => $donor_b ), $admin );
flxt_same( null, $r['errors'] ?? null, 'donor: an administrator reads the card' );
$dc = $r['data']['flxDonor'] ?? array();
flxt_same( $donor_b, (int) ( $dc['databaseId'] ?? 0 ), 'donor: the card' );
flxt_ok( in_array( $ref_b, array_map( 'intval', array_column( $dc['gifts'] ?? array(), 'databaseId' ) ), true ), 'donor: the gift is on the card' );
flxt_same( true, $dc['canEdit'] ?? null, 'donor: the administrator edits' );
$r = flxb_t_op( 'FlxDonateDonor', array( 'id' => $donor_b ), $staff_a );
flxt_ok( array_key_exists( 'flxDonor', (array) ( $r['data'] ?? array() ) ) && null === $r['data']['flxDonor'], 'donor: own-only staff cannot open another donor' );
$r = flxb_t_op( 'FlxDonateDonor', array( 'id' => $donor_a ), $staff_a );
$dc = $r['data']['flxDonor'] ?? array();
flxt_same( true, $dc['ownOnly'] ?? null, 'donor: own-only card' );
flxt_ok( false === ( $dc['canEdit'] ?? null ) && array() === ( $dc['subscriptions'] ?? null ) && '' === ( $dc['note'] ?? null ), 'donor: own-only staff gets no edit, recurring donations or note' );
$save = array( 'id' => $donor_b, 'consentKvkk' => true, 'consentEmail' => false, 'consentSms' => true, 'note' => 'GraphQL note' );
$r = flxb_t_op( 'FlxDonorSave', $save, $staff_a );
flxt_ok( null !== flxt_code( $r ), 'donor save: own-only staff is refused' );
$r = flxb_t_op( 'FlxDonorSave', $save, $admin );
flxt_same( null, $r['errors'] ?? null, 'donor save: an administrator saves' );
$row = FLXB_Donors::get( $donor_b );
flxt_same( array( 1, 0, 1, 'GraphQL note' ), array( (int) $row['consent_kvkk'], (int) $row['consent_email'], (int) $row['consent_sms'], (string) $row['note'] ), 'donor save: saved' );
$r = flxb_t_op( 'FlxDonorSave', array( 'id' => 99999999 ) + $save, $admin );
flxt_ok( ! empty( $r['data']['flxDonorSave']['errors'] ), 'donor save: an unknown donor is refused' );

$sub = (int) FLXB_Subscriptions::upsert( array( 'provider' => 'test', 'provider_ref' => 'gql-sub-' . wp_rand(), 'donor_id' => $donor_b, 'amount' => 50, 'currency' => 'TRY', 'interval_unit' => 'month', 'status' => 'active', 'next_charge_at' => '2026-12-24 10:00:00' ) );
$r = flxb_t_op( 'FlxDonateSubscriptions', array( 'first' => 25, 'after' => null ) );
flxt_ok( null !== flxt_code( $r ), 'subscriptions: visitor is refused' );
$r = flxb_t_op( 'FlxDonateSubscriptions', array( 'first' => 25, 'after' => null ), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'subscriptions: own-only staff is refused' );
$r = flxb_t_op( 'FlxDonateSubscriptions', array( 'first' => 25, 'after' => null, 'filter' => array( 'status' => 'ACTIVE' ) ), $admin );
flxt_same( null, $r['errors'] ?? null, 'subscriptions: an administrator reads' );
$node = null;
foreach ( (array) ( $r['data']['flxSubscriptionList']['nodes'] ?? array() ) as $n ) {
	if ( $sub === (int) $n['databaseId'] ) {
		$node = $n;
	}
}
flxt_ok( is_array( $node ) && 'ACTIVE' === $node['status'] && $donor_b === (int) $node['donorId'] && '' !== $node['nextLabel'], 'subscriptions: the recurring donation, active, with the next charge' );
flxt_same( false, $node['canCancel'] ?? null, 'subscriptions: a provider without cancel cannot be canceled here' );
flxt_ok( (int) ( $r['data']['flxSubscriptionTotals']['active'] ?? 0 ) >= 1, 'subscriptions: active count' );
$r = flxb_t_op( 'FlxDonateDonor', array( 'id' => $donor_b ), $admin );
flxt_ok( in_array( $sub, array_map( 'intval', array_column( $r['data']['flxDonor']['subscriptions'] ?? array(), 'databaseId' ) ), true ), 'donor: the recurring donation is on the card' );
$r = flxb_t_op( 'FlxSubscriptionCancel', array( 'id' => $sub ), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'cancel: own-only staff is refused' );
if ( FLXB_Admin_People_Service::can_cancel() ) {
	$r = flxb_t_op( 'FlxSubscriptionCancel', array( 'id' => $sub ), $admin );
	flxt_ok( ! empty( $r['data']['flxSubscriptionCancel']['errors'] ), 'cancel: a provider without cancel is refused' );
}

// Donation types list (2.40.0): rights, the views, status, order and sample sets.
$r = flxb_t_op( 'FlxDonateTypes', array( 'archived' => false ) );
flxt_ok( null !== flxt_code( $r ), 'types: visitor is refused' );
$r = flxb_t_op( 'FlxDonateTypes', array( 'archived' => false ), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'types: staff without the view right is refused' );
$r = flxb_t_op( 'FlxDonateTypes', array( 'archived' => false ), $admin );
flxt_same( null, $r['errors'] ?? null, 'types: an administrator reads' );
$tl  = $r['data']['flxDonationTypeAdminList'] ?? array();
$tid = array_map( 'intval', array_column( $tl['items'] ?? array(), 'databaseId' ) );
flxt_ok( in_array( (int) $open, $tid, true ) && in_array( (int) $closed, $tid, true ) && ! in_array( (int) $archived, $tid, true ), 'types: open and closed ones, not the archive' );
flxt_ok( true === ( $tl['canManage'] ?? null ) && '' !== ( $tl['newUrl'] ?? '' ) && ! empty( $tl['samples'] ), 'types: the administrator manages and gets the sample sets' );
$row = array_values( array_filter( $tl['items'] ?? array(), static fn( $i ) => (int) $open === (int) $i['databaseId'] ) )[0] ?? array();
flxt_ok( 'ACTIVE' === ( $row['status'] ?? '' ) && 'OK' === ( $row['tone'] ?? '' ) && str_contains( (string) ( $row['shortcode'] ?? '' ), 'id=' . (int) $open ), 'types: the open row' );
$r = flxb_t_op( 'FlxDonateTypes', array( 'archived' => true ), $admin );
$tl = $r['data']['flxDonationTypeAdminList'] ?? array();
flxt_ok( array( (int) $archived ) === array_values( array_intersect( array( (int) $open, (int) $closed, (int) $archived ), array_map( 'intval', array_column( $tl['items'] ?? array(), 'databaseId' ) ) ) ) && array() === ( $tl['samples'] ?? null ), 'types: the archive, without sample sets' );

$r = flxb_t_op( 'FlxDonationTypeSetStatus', array( 'id' => (int) $closed, 'status' => 'ACTIVE' ), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'type status: staff is refused' );
$r = flxb_t_op( 'FlxDonationTypeSetStatus', array( 'id' => (int) $closed, 'status' => 'ACTIVE' ), $admin );
flxt_ok( null === ( $r['errors'] ?? null ) && empty( $r['data']['flxDonationTypeSetStatus']['errors'] ), 'type status: an administrator opens' );
flxt_same( 'active', FLXB_Types::get( (int) $closed )['status'] ?? '', 'type status: saved' );
flxb_t_op( 'FlxDonationTypeSetStatus', array( 'id' => (int) $closed, 'status' => 'CLOSED' ), $admin );
flxt_same( 'closed', FLXB_Types::get( (int) $closed )['status'] ?? '', 'type status: closed again' );
$r = flxb_t_op( 'FlxDonationTypeSetStatus', array( 'id' => 99999999, 'status' => 'CLOSED' ), $admin );
flxt_ok( ! empty( $r['data']['flxDonationTypeSetStatus']['errors'] ), 'type status: an unknown type is refused' );

$order = static fn(): array => array_map( 'intval', wp_list_pluck( FLXB_Types::all( 'all' ), 'id' ) );
$before = $order();
$pos    = array_search( (int) $closed, $before, true );
$r      = flxb_t_op( 'FlxDonationTypeMove', array( 'id' => (int) $closed, 'up' => true ), $admin );
flxt_ok( null === ( $r['errors'] ?? null ) && empty( $r['data']['flxDonationTypeMove']['errors'] ), 'type move: an administrator moves' );
flxt_same( false === $pos || 0 === $pos ? $pos : $pos - 1, array_search( (int) $closed, $order(), true ), 'type move: one place up' );
flxb_t_op( 'FlxDonationTypeMove', array( 'id' => (int) $closed, 'up' => false ), $admin );
flxt_same( $before, $order(), 'type move: and back down' );

$r = flxb_t_op( 'FlxDonationTypeSamples', array( 'key' => 'no-such-set' ), $admin );
flxt_ok( ! empty( $r['data']['flxDonationTypeSamples']['errors'] ), 'type samples: an unknown set is refused' );
$r = flxb_t_op( 'FlxDonationTypeSamples', array( 'key' => 'trees' ), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'type samples: staff is refused' );

// Type editor (2.41.0): rights, the fields, save with the cards' fields (extra), delete.
$r = flxb_t_op( 'FlxDonateTypeForm', array( 'id' => (int) $open ), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'type form: staff is refused' );
$r = flxb_t_op( 'FlxDonateTypeForm', array( 'id' => (int) $open ), $admin );
flxt_same( null, $r['errors'] ?? null, 'type form: an administrator reads' );
$tf = $r['data']['flxDonationTypeForm'] ?? array();
flxt_ok( 'Saplings' === ( $tf['title'] ?? '' ) && 'one' === ( $tf['options'][0]['key'] ?? '' ) && 100.0 === (float) ( $tf['options'][0]['price'] ?? 0 ), 'type form: title and options' );
flxt_same( array(), $tf['islands'] ?? null, 'type form: no islands over plain GraphQL' );
$r = flxb_t_op( 'FlxDonateTypeForm', array( 'id' => 0 ), $admin );
flxt_ok( 0 === ( $r['data']['flxDonationTypeForm']['databaseId'] ?? -1 ) && 'ACTIVE' === ( $r['data']['flxDonationTypeForm']['status'] ?? '' ), 'type form: a new type' );
$r = flxb_t_op( 'FlxDonateTypeForm', array( 'id' => 99999999 ), $admin );
flxt_ok( array_key_exists( 'flxDonationTypeForm', (array) ( $r['data'] ?? array() ) ) && null === $r['data']['flxDonationTypeForm'], 'type form: an unknown type is null' );

$tin = array(
	'id'             => 0,
	'title'          => 'GQL editor type',
	'slug'           => '',
	'category'       => '',
	'currency'       => 'TRY',
	'description'    => 'From the editor.',
	'subtitle'       => 'Sub',
	'icon'           => 'gift',
	'imageId'        => 0,
	'options'        => array( array( 'key' => '', 'label' => 'Big one', 'price' => 75.5, 'unit' => 'adet', 'unitsPer' => 2, 'badge' => '', 'active' => true, 'prices' => array() ) ),
	'allowFree'      => false,
	'minAmount'      => 0,
	'minByCurrency'  => array(),
	'allowQuantity'  => true,
	'maxQuantity'    => 3,
	'allowRecurring' => false,
	'formIds'        => array(),
	'lockPreselect'  => true,
	'status'         => 'CLOSED',
	'startDate'      => '2026-01-01',
	'endDate'        => '2026-12-31',
	'goal'           => 900,
	'extra'          => 'meta%5Bmeat_to_needy%5D=1',
);
$r = flxb_t_op( 'FlxDonationTypeSave', array( 'input' => $tin ), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'type save: staff is refused' );
$r   = flxb_t_op( 'FlxDonationTypeSave', array( 'input' => $tin ), $admin );
$nid = (int) ( $r['data']['flxDonationTypeSave']['databaseId'] ?? 0 );
flxt_ok( $nid > 0 && empty( $r['data']['flxDonationTypeSave']['errors'] ), 'type save: a new type' );
$nt = FLXB_Types::get( $nid );
flxt_same( array( 'GQL editor type', 'closed', 'big-one', 75.5, 2, 3, '900', '2026-01-01', 'Sub', true, true ), array( $nt['title'] ?? '', $nt['status'] ?? '', $nt['options'][0]['key'] ?? '', (float) ( $nt['options'][0]['price'] ?? 0 ), (int) ( $nt['options'][0]['units_per'] ?? 0 ), (int) ( $nt['max_quantity'] ?? 0 ), (string) (float) ( $nt['goal_amount'] ?? 0 ), $nt['start_date'] ?? '', $nt['meta']['subtitle'] ?? '', $nt['meta']['lock_preselect'] ?? null, $nt['meta']['meat_to_needy'] ?? null ), 'type save: the fields and the cards\' meta are saved' );
$r = flxb_t_op( 'FlxDonationTypeSave', array( 'input' => array( 'id' => $nid, 'endDate' => '2025-01-01', 'extra' => '' ) + $tin ), $admin );
flxt_ok( ! empty( $r['data']['flxDonationTypeSave']['errors'] ), 'type save: an end before the start is refused' );
$r = flxb_t_op( 'FlxDonationTypeSave', array( 'input' => array( 'id' => $nid, 'title' => 'GQL editor type 2', 'extra' => '' ) + $tin ), $admin );
flxt_same( $nid, (int) ( $r['data']['flxDonationTypeSave']['databaseId'] ?? 0 ), 'type save: saves the same type' );
$nt = FLXB_Types::get( $nid );
flxt_same( array( 'GQL editor type 2', true ), array( $nt['title'] ?? '', $nt['meta']['meat_to_needy'] ?? null ), 'type save: without extra the cards\' meta is kept' );
$r = flxb_t_op( 'FlxDonationTypeDelete', array( 'id' => $nid ), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'type delete: staff is refused' );
$r = flxb_t_op( 'FlxDonationTypeDelete', array( 'id' => (int) $open ), $admin );
flxt_ok( ! empty( $r['data']['flxDonationTypeDelete']['errors'] ) && FLXB_Types::get( (int) $open ), 'type delete: a type with gifts is refused' );
$r = flxb_t_op( 'FlxDonationTypeDelete', array( 'id' => $nid ), $admin );
flxt_ok( empty( $r['data']['flxDonationTypeDelete']['errors'] ) && ! FLXB_Types::get( $nid ), 'type delete: deleted' );

// Add donation (2.42.0): rights, the form, validation and a recorded gift; the staff request form.
$r = flxb_t_op( 'FlxDonateAdd', array( 'donorId' => 0 ), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'add form: staff without the request right is refused' );
$r  = flxb_t_op( 'FlxDonateAdd', array( 'donorId' => 0 ), $admin );
$af = $r['data']['flxDonationAddForm'] ?? array();
flxt_same( null, $r['errors'] ?? null, 'add form: an administrator reads' );
$at = array_values( array_filter( $af['types'] ?? array(), static fn( $t ) => (int) $open === (int) $t['databaseId'] ) )[0] ?? array();
flxt_ok( false === ( $af['request'] ?? null ) && true === ( $at['proxy'] ?? null ) && 'one' === ( $at['options'][0]['key'] ?? '' ) && 1 === ( $af['proxyVersion'] ?? 0 ) && 3 === count( $af['methods'] ?? array() ), 'add form: types, options, proxy text and methods' );
$ain = array(
	'typeId'    => 0,
	'optionKey' => '',
	'quantity'  => 1,
	'amount'    => 250.5,
	'currency'  => 'EUR',
	'donor'     => array( 'name' => 'Add Donor', 'phone' => '', 'email' => 'add' . wp_rand() . '.gql@example.com', 'consentKvkk' => true ),
	'method'    => 'cash',
	'paid'      => false,
	'date'      => '2026-03-04T10:20',
	'reference' => 'R-1',
	'note'      => 'From the screen',
);
$r = flxb_t_op( 'FlxDonationAdd', array( 'input' => $ain ), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'add: staff is refused' );
$r = flxb_t_op( 'FlxDonationAdd', array( 'input' => array( 'amount' => 0 ) + $ain ), $admin );
flxt_ok( ! empty( $r['data']['flxDonationAdd']['errors'] ) && null === $r['data']['flxDonationAdd']['databaseId'], 'add: no amount is refused' );
$r = flxb_t_op( 'FlxDonationAdd', array( 'input' => array( 'typeId' => (int) $open, 'optionKey' => 'one', 'quantity' => 2 ) + $ain ), $admin );
flxt_ok( ! empty( $r['data']['flxDonationAdd']['errors'] ), 'add: a proxy type without the consent is refused' );
$r = flxb_t_op( 'FlxDonationAdd', array( 'input' => array( 'typeId' => (int) $open, 'optionKey' => 'one', 'quantity' => 2, 'proxyConsent' => true, 'unitPrice' => 80 ) + $ain ), $admin );
flxt_ok( ! empty( $r['data']['flxDonationAdd']['errors'] ), 'add: a different unit price without a reason is refused' );
$r   = flxb_t_op( 'FlxDonationAdd', array( 'input' => $ain ), $admin );
$aid = (int) ( $r['data']['flxDonationAdd']['databaseId'] ?? 0 );
flxt_ok( $aid > 0 && empty( $r['data']['flxDonationAdd']['errors'] ) && false !== strpos( (string) ( $r['data']['flxDonationAdd']['url'] ?? '' ), 'donation=' . $aid ), 'add: a free amount is recorded' );
$ag = (array) FLXB_Donations::get( $aid );
$am = FLXB_Donations::meta( $ag );
flxt_same( array( 250.5, 'EUR', 'pending', 'cash', 'R-1', 'From the screen' ), array( (float) ( $ag['amount'] ?? 0 ), $ag['currency'] ?? '', $ag['status'] ?? '', $am['method'] ?? '', $am['reference'] ?? '', $am['note'] ?? '' ), 'add: amount, currency, status and meta' );
$ad = FLXB_Donors::get( (int) ( $ag['donor_id'] ?? 0 ) );
$r  = flxb_t_op( 'FlxDonateAdd', array( 'donorId' => (int) ( $ag['donor_id'] ?? 0 ) ), $admin );
flxt_ok( 'Add Donor' === ( $r['data']['flxDonationAddForm']['donor']['name'] ?? '' ) && true === ( $r['data']['flxDonationAddForm']['donor']['consentKvkk'] ?? null ) && is_array( $ad ), 'add form: ?donor= prefills the donor' );
FLXB_Donations::delete_rows( array( $aid ) );
$wu = new WP_User( $staff_b );
$wu->add_cap( FLXB_Refs::CAP_CREATE );
$r = flxb_t_op( 'FlxDonateAdd', array( 'donorId' => 0 ), $staff_b );
flxt_ok( true === ( $r['data']['flxDonationAddForm']['request'] ?? null ) && array() === ( $r['data']['flxDonationAddForm']['accounts'] ?? null ), 'add form: staff with the request right get the request form' );
$r = flxb_t_op( 'FlxDonationAdd', array( 'input' => $ain ), $staff_b );
flxt_ok( null !== flxt_code( $r ), 'add: staff with the request right cannot record a gift' );
$r = flxb_t_op( 'FlxDonationRequest', array( 'input' => array( 'typeId' => 0, 'optionKey' => '', 'quantity' => 1, 'donor' => array( 'name' => 'X', 'phone' => '', 'email' => '' ) ) ), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'request: staff without the right is refused' );
$r = flxb_t_op( 'FlxDonationRequest', array( 'input' => array( 'typeId' => 0, 'optionKey' => '', 'quantity' => 1, 'donor' => array( 'name' => 'X', 'phone' => '', 'email' => '' ) ) ), $staff_b );
flxt_ok( ! empty( $r['data']['flxDonationRequest']['errors'] ) && null === $r['data']['flxDonationRequest']['payUrl'], 'request: a request without a type is refused' );
$wu->remove_cap( FLXB_Refs::CAP_CREATE );

// Partners (2.43.0): rights, the list, the editor, agreements, payments and the settlement.
$pin = array(
	'id'          => 0,
	'name'        => 'GQL partner',
	'pkey'        => 'gql-partner',
	'group'       => 'North',
	'isService'   => true,
	'isSource'    => false,
	'typeIds'     => array( (int) $open ),
	'contactName' => 'Ali',
	'phone'       => '+905321112233',
	'email'       => 'partner.gql@example.com',
	'currency'    => 'TRY',
	'active'      => true,
	'note'        => '',
);
$r = flxb_t_op( 'FlxDonatePartners', array(), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'partners: staff is refused' );
$r = flxb_t_op( 'FlxDonationPartnerSave', array( 'input' => $pin ), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'partner save: staff is refused' );
$r = flxb_t_op( 'FlxDonationPartnerSave', array( 'input' => array( 'name' => '' ) + $pin ), $admin );
flxt_ok( ! empty( $r['data']['flxDonationPartnerSave']['errors'] ), 'partner save: a name is needed' );
$r   = flxb_t_op( 'FlxDonationPartnerSave', array( 'input' => $pin ), $admin );
$pid = (int) ( $r['data']['flxDonationPartnerSave']['databaseId'] ?? 0 );
flxt_ok( $pid > 0 && empty( $r['data']['flxDonationPartnerSave']['errors'] ), 'partner save: a new partner' );
$r   = flxb_t_op( 'FlxDonatePartners', array(), $admin );
$row = array_values( array_filter( $r['data']['flxDonationPartners']['items'] ?? array(), static fn( $i ) => $pid === (int) $i['databaseId'] ) )[0] ?? array();
flxt_ok( 'GQL partner' === ( $row['name'] ?? '' ) && 'North' === ( $row['group'] ?? '' ) && 'Saplings' === ( $row['types'] ?? '' ) && true === ( $row['active'] ?? null ), 'partners: the row' );
$r = flxb_t_op( 'FlxDonationPartnerPriceAdd', array( 'input' => array( 'partnerId' => $pid, 'typeOption' => (int) $open . '|one', 'price' => '0', 'currency' => 'TRY', 'validFrom' => '2026-01-01', 'note' => '' ) ), $admin );
flxt_ok( ! empty( $r['data']['flxDonationPartnerPriceAdd']['errors'] ), 'agreement: a price is needed' );
$r = flxb_t_op( 'FlxDonationPartnerPriceAdd', array( 'input' => array( 'partnerId' => $pid, 'typeOption' => (int) $open . '|one', 'price' => '12,5', 'currency' => 'TRY', 'validFrom' => '2026-01-01', 'note' => 'n' ) ), $admin );
flxt_ok( empty( $r['data']['flxDonationPartnerPriceAdd']['errors'] ), 'agreement: added' );
$r = flxb_t_op( 'FlxDonationPartnerPaymentAdd', array( 'input' => array( 'partnerId' => $pid, 'received' => false, 'amount' => '40', 'currency' => 'TRY', 'date' => wp_date( 'Y-m-d' ), 'note' => 'cash' ) ), $admin );
flxt_ok( empty( $r['data']['flxDonationPartnerPaymentAdd']['errors'] ), 'payment: added' );
$r  = flxb_t_op( 'FlxDonatePartner', array( 'id' => $pid ), $admin );
$pf = $r['data']['flxDonationPartner'] ?? array();
flxt_ok( array( (int) $open ) === ( $pf['typeIds'] ?? null ) && 'One sapling' === ( $pf['agreements'][0]['option'] ?? '' ) && true === ( $pf['agreements'][0]['active'] ?? null ) && 1 === count( $pf['payments'] ?? array() ), 'partner editor: fields, agreement and payment' );
$aid = (int) ( $pf['agreements'][0]['databaseId'] ?? 0 );
flxb_t_op( 'FlxDonationPartnerPriceToggle', array( 'input' => array( 'id' => $aid ) ), $admin );
flxt_same( 'off', FLXB_Partners::price_state( $pid ), 'agreement: switched off' );
$r = flxb_t_op( 'FlxDonatePartnerSettlement', array( 'kind' => 'SERVICE', 'period' => '', 'partnerId' => 0 ), $admin );
$st = $r['data']['flxDonationPartnerSettlement'] ?? array();
$sr = array_values( array_filter( $st['rows'] ?? array(), static fn( $i ) => $pid === (int) $i['partnerId'] ) )[0] ?? array();
flxt_ok( wp_date( 'Y-m' ) === ( $st['period'] ?? '' ) && 12 === count( $st['months'] ?? array() ) && false !== strpos( (string) ( $sr['moved'] ?? '' ), '40' ), 'settlement: this month, 12 month cards, the payment' );
$r = flxb_t_op( 'FlxDonationPartnerPriceDelete', array( 'input' => array( 'id' => $aid ) ), $admin );
$p2 = (array) ( flxb_t_op( 'FlxDonatePartner', array( 'id' => $pid ), $admin )['data']['flxDonationPartner'] ?? array() );
flxt_ok( array() === ( $p2['agreements'] ?? null ), 'agreement: deleted' );
flxb_t_op( 'FlxDonationPartnerPaymentDelete', array( 'input' => array( 'id' => (int) ( $p2['payments'][0]['databaseId'] ?? 0 ) ) ), $admin );
flxt_same( array(), FLXB_Partners::payments( $pid ), 'payment: deleted' );
$r = flxb_t_op( 'FlxDonatePartner', array( 'id' => 99999999 ), $admin );
flxt_ok( array_key_exists( 'flxDonationPartner', (array) ( $r['data'] ?? array() ) ) && null === $r['data']['flxDonationPartner'], 'partner editor: an unknown partner is null' );
global $wpdb;
$wpdb->delete( flxb_table( 'partners' ), array( 'id' => $pid ) );
FLXB_Partners::flush();

if ( ! class_exists( 'FLXB_Admin' ) ) {
	require_once FLXB_DIR . 'includes/admin/class-flxb-admin.php';
}
// Preload: the PHP filter is the one the screen sends (same store key).
flxt_same( array( 'search' => 'x', 'status' => 'RECEIPT', 'kind' => 'ONE_TIME', 'donationTypeId' => 0, 'referralId' => 3 ), FLXB_Admin::flux_list_filter( array( 's' => ' x ', 'status' => 'receipt', 'type' => 'one_time', 'dtype' => '0', 'ref' => '3', 'form' => 'a', 'from' => '2026-1-1' ) ), 'preload: classic arguments → filter' );
flxt_same( array( array( 'FlxDonateDonations', array( 'first' => 25, 'after' => null ) ) ), FLXB_Admin::preload_list( array() ), 'preload: no filter → no filter variable' );

flxt_same( array( array( 'FlxDonateDetail', array( 'id' => 12 ) ) ), FLXB_Admin::preload_list( array( 'donation' => '12' ) ), 'preload: the donation page' );
flxt_same( array( array( 'FlxDonateDonors', array( 'first' => 25, 'after' => null ) ) ), FLXB_Admin::preload_donors( array( 'orderby' => 'last_donation_at' ) ), 'preload: donors, default sort → no filter' );
flxt_same( array( array( 'FlxDonateDonors', array( 'first' => 25, 'after' => base64_encode( 'flxo:24' ), 'filter' => array( 'search' => 'x', 'kind' => 'CONSENT', 'orderBy' => 'TOTAL' ) ) ) ), FLXB_Admin::preload_donors( array( 's' => ' x ', 'kind' => 'consent', 'orderby' => 'total_amount', 'paged' => '2' ) ), 'preload: donors with the address filter' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
flxt_same( array( array( 'FlxDonateDonor', array( 'id' => 7 ) ) ), FLXB_Admin::preload_donors( array( 'donor' => '7' ) ), 'preload: the donor card' );
flxt_same( array( array( 'FlxDonateSubscriptions', array( 'first' => 25, 'after' => null, 'filter' => array( 'status' => 'PAST_DUE' ) ) ) ), FLXB_Admin::preload_subscriptions( array( 'status' => 'past_due', 's' => '' ) ), 'preload: recurring donations' );
flxt_same( array( array( 'FlxDonateTypes', array( 'archived' => false ) ) ), FLXB_Admin::preload_types( array() ), 'preload: donation types' );
flxt_same( array( array( 'FlxDonateTypes', array( 'archived' => true ) ) ), FLXB_Admin::preload_types( array( 'view' => 'archived' ) ), 'preload: the type archive' );
if ( function_exists( 'wp_set_current_user' ) ) {
	wp_set_current_user( $admin );
	flxt_same( array( array( 'FlxDonateTypeForm', array( 'id' => 0 ) ) ), FLXB_Admin::preload_types( array( 'edit' => 'new' ) ), 'preload: a new type' );
	flxt_same( array( array( 'FlxDonateTypeForm', array( 'id' => 5 ) ) ), FLXB_Admin::preload_types( array( 'edit' => '5' ) ), 'preload: the type editor' );
	flxt_same( array( array( 'FlxDonateAdd', array( 'donorId' => 0 ) ) ), FLXB_Admin::preload_add( array() ), 'preload: add donation' );
	flxt_same( array( array( 'FlxDonateAdd', array( 'donorId' => 9 ) ) ), FLXB_Admin::preload_add( array( 'donor' => '9' ) ), 'preload: add donation for a donor' );
	flxt_same( array( array( 'FlxDonatePartners', array() ) ), FLXB_Admin::preload_partners( array() ), 'preload: partners' );
	flxt_same( array( array( 'FlxDonatePartner', array( 'id' => 4 ) ) ), FLXB_Admin::preload_partners( array( 'id' => '4' ) ), 'preload: a partner' );
	flxt_same( array( array( 'FlxDonatePartner', array( 'id' => 0 ) ) ), FLXB_Admin::preload_partners( array( 'tab' => 'edit' ) ), 'preload: a new partner' );
	flxt_same( array( array( 'FlxDonatePartnerSettlement', array( 'kind' => 'SOURCE', 'period' => '2026-01-01..2026-02-01', 'partnerId' => 3 ) ) ), FLXB_Admin::preload_partners( array( 'tab' => 'settle', 'kind' => 'source', 'from' => '2026-01-01', 'to' => '2026-02-01', 'partner' => '3' ) ), 'preload: the settlement of a range' );
	flxt_same( array( array( 'FlxDonatePartnerSettlement', array( 'kind' => 'SERVICE', 'period' => '', 'partnerId' => 0 ) ) ), FLXB_Admin::preload_partners( array( 'tab' => 'settle' ) ), 'preload: the settlement of this month' );
	// 2.44.0: Tracking, Import, Reports and Settings.
	flxt_same( array( array( 'FlxDonateTrack', array( 'page' => 1 ) ) ), FLXB_Admin::preload_track( array() ), 'preload: tracking' );
	flxt_same( array( array( 'FlxDonateTrack', array( 'page' => 2, 'filter' => array( 'place' => 'North', 'typeId' => 3, 'paid' => '1', 'due' => true, 'search' => 'x' ) ) ) ), FLXB_Admin::preload_track( array( 'place' => ' North ', 'type' => '3', 'paid' => '1', 'due' => '1', 's' => 'x', 'paged' => '2', 'status' => 'nope' ) ), 'preload: tracking with the address filter' );
	flxt_same( array( array( 'FlxDonateTrackItem', array( 'id' => 8 ) ) ), FLXB_Admin::preload_track( array( 'item' => '8' ) ), 'preload: a tracked item' );
	flxt_same( array( array( 'FlxDonateImport', array( 'id' => '' ) ) ), FLXB_Admin::preload_import( array() ), 'preload: import upload' );
	flxt_same( array( array( 'FlxDonateImport', array( 'id' => 'abc' ) ) ), FLXB_Admin::preload_import( array( 'import' => 'abc' ) ), 'preload: an import' );
	flxt_same( array( array( 'FlxDonateReport', array( 'id' => 'by-type', 'period' => 'custom', 'from' => '2026-01-01', 'to' => '' ) ) ), FLXB_Admin::preload_reports( array( 'r' => 'by-type', 'period' => 'custom', 'from' => '2026-01-01', 'to' => 'x' ) ), 'preload: a report of custom dates' );
	flxt_same( array( array( 'FlxDonateReport', array( 'id' => '', 'period' => 'this_year', 'from' => '', 'to' => '' ) ) ), FLXB_Admin::preload_reports( array( 'period' => 'this_year', 'from' => '2026-01-01' ) ), 'preload: a period drops the dates' );
	flxt_same( array( array( 'FlxDonateReportSource', array( 'id' => '' ) ) ), FLXB_Admin::preload_reports( array( 'new' => '1' ) ), 'preload: a new report' );
	flxt_same( array( array( 'FlxDonateSettings', array() ) ), FLXB_Admin::preload_settings(), 'preload: settings' );
	wp_set_current_user( 0 );
}

// 2.44.0: Settings, Reports, Tracking and Import (managers only).
$r = flxb_t_op( 'FlxDonateSettings', array(), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'settings: staff is refused' );
$r = flxb_t_op( 'FlxDonationSettingsGeneral', array( 'input' => array( 'currency' => 'EUR', 'donationForms' => 'list', 'formIds' => array( 3, 0 ), 'moduleTrack' => true, 'moduleReports' => true, 'moduleKurban' => false ) ), $staff_a );
flxt_ok( null !== flxt_code( $r ) && 'TRY' === flxb_default_currency(), 'settings general: staff is refused' );
$old_settings = get_option( 'flxb_settings' );
$old_cats     = get_option( 'flxb_categories' );
$old_early    = get_option( 'flxb_track_early_days' );
$old_labels   = get_option( 'flxb_track_labels' );
$r = flxb_t_op( 'FlxDonationSettingsGeneral', array( 'input' => array( 'currency' => 'EUR', 'donationForms' => 'list', 'formIds' => array( 3, 0 ), 'moduleTrack' => true, 'moduleReports' => true, 'moduleKurban' => false ) ), $admin );
$st = flxb_settings();
flxt_ok( 'Saved.' === ( $r['data']['flxDonationSettingsGeneral']['message'] ?? '' ) && 'EUR' === $st['currency'] && 'list' === $st['donation_forms'] && array( 3 ) === $st['form_ids'] && 1 === $st['module_track'], 'settings general: saved' );
$r = flxb_t_op( 'FlxDonationSettingsOrganisation', array( 'input' => array( 'orgName' => 'Org', 'logoId' => 0, 'primaryColor' => '#12ab34', 'receiptText' => 'Thanks', 'footerText' => 'Addr' ) ), $admin );
$r = flxb_t_op( 'FlxDonateSettings', array(), $admin );
$sv = $r['data']['flxDonationSettings'] ?? array();
flxt_ok( 'EUR' === ( $sv['currency'] ?? '' ) && 'Org' === ( $sv['orgName'] ?? '' ) && '#12AB34' === ( $sv['primaryColor'] ?? '' ) && in_array( 'general', array_column( $sv['tabs'] ?? array(), 'id' ), true ), 'settings: read back' );
$r = flxb_t_op( 'FlxDonationSettingsCategories', array( 'input' => array( 'categories' => array( array( 'key' => '', 'label' => 'Ramazan Özel' ) ) ) ), $admin );
flxt_ok( isset( flxb_categories()['ramazan-ozel'] ), 'settings categories: key from the name' );
$r = flxb_t_op( 'FlxDonationSettingsTrack', array( 'input' => array( 'earlyDays' => 99, 'labels' => array( array( 'key' => 'menu', 'value' => 'Takip' ) ), 'proxyText' => '' ) ), $admin );
flxt_ok( 30 === (int) get_option( 'flxb_track_early_days' ) && 'Takip' === ( get_option( 'flxb_track_labels' )['menu'] ?? '' ), 'settings tracking: early days capped, words saved' );
$r = flxb_t_op( 'FlxDonationSettingsTabs', array( 'input' => array( 'json' => '{nope' ) ), $admin );
flxt_ok( ! empty( $r['data']['flxDonationSettingsTabs']['errors'] ), 'settings tabs: bad JSON is an error' );
update_option( 'flxb_settings', $old_settings );
update_option( 'flxb_categories', $old_cats );
update_option( 'flxb_track_early_days', $old_early );
update_option( 'flxb_track_labels', $old_labels );
$r = flxb_t_op( 'FlxDonateReport', array( 'id' => '', 'period' => '', 'from' => '', 'to' => '' ), $admin );
flxt_ok( '' !== ( $r['data']['flxDonationReport']['id'] ?? '' ) && ! empty( $r['data']['flxDonationReport']['reports'] ), 'reports: the first report' );
$r = flxb_t_op( 'FlxDonateReportSource', array( 'id' => '' ), $staff_a );
flxt_ok( null !== flxt_code( $r ), 'report editor: staff is refused' );
$r = flxb_t_op( 'FlxDonationReportSave', array( 'input' => array( 'json' => '{"id":"gql-r","title":"R","source":"donations","group":["type"],"metrics":["count"]}', 'old' => '' ) ), $admin );
flxt_same( 'gql-r', $r['data']['flxDonationReportSave']['id'] ?? '', 'report save: a new report' );
flxb_t_op( 'FlxDonationReportDelete', array( 'input' => array( 'id' => 'gql-r' ) ), $admin );
flxt_ok( null === FLXB_Reports::get( 'gql-r' ), 'report delete: gone' );
$r = flxb_t_op( 'FlxDonateImport', array( 'id' => '' ), $admin );
flxt_same( 'upload', $r['data']['flxDonationImport']['state'] ?? '', 'import: the upload page' );
$r = flxb_t_op( 'FlxDonateTrack', array( 'page' => 1 ), $admin );
flxt_ok( isset( $r['data']['flxDonationTrack']['items'], $r['data']['flxDonationTrack']['tabs'] ), 'tracking: the list' );

flxt_done();
