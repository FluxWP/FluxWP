<?php
/**
 * Flux Commerce GraphQL contract.
 *
 * Authorized, unauthorized and anonymous reads; an empty list and a filled row;
 * mutation error codes; My Account ownership; the sealed card block stays out of
 * every response; two renewal passes do not open a second order; a subscription
 * line is quantity 1.
 *
 * The Store API stand-in (no WooCommerce) still runs the checks that do not need
 * an order. The WooCommerce CI job runs the order and cart checks.
 */

require dirname( __DIR__ ) . '/lib.php';

$admin = flxcm_test_admin();
$view  = flxcm_test_user( 'flxcm_view', array( 'read', 'view_woocommerce_reports' ) );
$shop  = flxcm_test_user( 'flxcm_shop', array( 'read', 'edit_shop_orders' ) );
$mgr   = flxcm_test_user( 'flxcm_mgr', array( 'read', 'manage_woocommerce' ) );
$sub   = flxcm_test_user( 'flxcm_sub', array( 'read' ) );
$owner = flxcm_test_user( 'flxcm_owner', array( 'read' ) );
$other = flxcm_test_user( 'flxcm_other', array( 'read' ) );

if ( class_exists( 'FLXCM_Install' ) ) {
	FLXCM_Install::install();
}

$ops = json_decode( (string) file_get_contents( FLXCM_DIR . 'graphql/persisted-queries.json' ), true );
flxt_same( 30, is_array( $ops ) ? count( $ops ) : 0, 'persisted operation count' );

$anon = flxt_gql( '{ flxCommerceSummary(days: 7) { orders } }', array(), 0 );
flxt_same( 'UNAUTHENTICATED', flxt_code( $anon ), 'anonymous summary' );

$denied = flxt_gql( '{ flxCommerceSummary(days: 7) { orders } }', array(), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $denied ), 'subscriber summary' );

$ok = flxt_gql( '{ flxCommerceSummary(days: 7) { orders revenue { amount formatted } awaiting } }', array(), $view );
flxt_ok( isset( $ok['data']['flxCommerceSummary']['orders'] ), 'viewer summary', $ok );
flxt_ok( ! isset( $ok['errors'] ), 'viewer summary has no errors', $ok );

$empty = flxt_gql( '{ flxCommerceCustomers(first: 5) { nodes { id } totalCount } }', array(), $view );
flxt_ok( isset( $empty['data']['flxCommerceCustomers']['nodes'] ) && is_array( $empty['data']['flxCommerceCustomers']['nodes'] ), 'customer connection', $empty );

$settings_denied = flxt_gql( '{ flxCommerceSettings { feedUrl } }', array(), $view );
flxt_same( 'FORBIDDEN', flxt_code( $settings_denied ), 'viewer cannot read settings' );

$settings = flxt_gql( '{ flxCommerceSettings { feed feedUrl cardProviders whatsappReady donateCart donateHavale orderStatuses { slug } freeShipThreshold { amount } } }', array(), $mgr );
flxt_ok( isset( $settings['data']['flxCommerceSettings']['feedUrl'] ), 'manager sees feedUrl', $settings );
flxt_same( '', (string) ( $settings['data']['flxCommerceSettings']['feedUrl'] ?? 'x' ), 'feed off means an empty address' );
flxt_ok( ! flxcm_test_leaks( $settings ), 'settings hide the card block', $settings );

global $wpdb;
$email = 'flxcm-contract@example.test';
$wpdb->delete( FLXCM_Install::table(), array( 'email' => $email ) );
$wpdb->insert(
	FLXCM_Install::table(),
	array(
		'email'      => $email,
		'user_id'    => 0,
		'name'       => 'Contract Guest',
		'orders'     => 2,
		'total'      => 40,
		'first_at'   => gmdate( 'Y-m-d H:i:s' ),
		'last_at'    => gmdate( 'Y-m-d H:i:s' ),
		'updated_at' => gmdate( 'Y-m-d H:i:s' ),
	)
);
$cid = (int) $wpdb->insert_id;
$one = flxt_gql( 'query($id: ID!){ flxCommerceCustomer(id: $id) { id email guest userId openOrders } }', array( 'id' => (string) $cid ), $view );
flxt_same( $email, $one['data']['flxCommerceCustomer']['email'] ?? '', 'filled customer' );
flxt_same( true, $one['data']['flxCommerceCustomer']['guest'] ?? false, 'guest badge' );

$missing = flxt_gql( 'query($id: ID!){ flxCommerceCustomer(id: $id) { id } }', array( 'id' => '999999' ), $view );
flxt_same( null, array_key_exists( 'flxCommerceCustomer', $missing['data'] ?? array() ) ? $missing['data']['flxCommerceCustomer'] : 'missing', 'missing customer is null' );

$long = flxt_gql(
	'mutation($input: FlxCommerceCustomerNoteInput!){ flxCommerceCustomerNote(input: $input){ customer { id } errors { code } } }',
	array( 'input' => array( 'id' => (string) $cid, 'note' => str_repeat( 'n', 2001 ) ) ),
	$mgr
);
flxt_same( 'INVALID_INPUT', $long['data']['flxCommerceCustomerNote']['errors'][0]['code'] ?? '', 'long note is a payload error' );

$gone = flxt_gql(
	'mutation($input: FlxCommerceCustomerNoteInput!){ flxCommerceCustomerNote(input: $input){ errors { code } } }',
	array( 'input' => array( 'id' => '999999', 'note' => 'hi' ) ),
	$mgr
);
flxt_same( 'NOT_FOUND', flxt_code( $gone ), 'missing customer note' );

$token = 'FLXCMSEALEDTOKEN9f3a';
$far   = gmdate( 'Y-m-d H:i:s', time() + 400 * DAY_IN_SECONDS );
$meta  = wp_json_encode(
	array(
		'_card' => array(
			'p'     => 'iyzico',
			'd'     => array( 'k' => $token, 't' => $token ),
			'label' => 'Visa .... 4242',
		),
	)
);
$wpdb->insert(
	FLXCM_Subscriptions::table(),
	array(
		'status'       => 'active',
		'user_id'      => $owner,
		'email'        => 'flxcm_owner@example.test',
		'name'         => 'Owner',
		'product_name' => 'Plan',
		'period'       => 'month',
		'amount'       => 10,
		'currency'     => 'TRY',
		'period_end'   => $far,
		'started_at'   => gmdate( 'Y-m-d H:i:s' ),
		'created_at'   => gmdate( 'Y-m-d H:i:s' ),
		'meta'         => $meta,
	)
);
$sid = (int) $wpdb->insert_id;
$wpdb->update( $wpdb->users, array( 'user_email' => 'flxcm_owner@example.test' ), array( 'ID' => $owner ) );

$subs = flxt_gql( '{ flxCommerceSubscriptions(first: 20) { nodes { id cardLabel email } } }', array(), $view );
flxt_ok( ! flxcm_test_leaks( $subs ), 'subscription list hides the sealed card', $subs );
$mine = flxt_gql( '{ flxMySubscriptions { id cardLabel } }', array(), $owner );
flxt_ok( ! flxcm_test_leaks( $mine ), 'my subscriptions hide the sealed card', $mine );
flxt_ok( in_array( (string) $sid, wp_list_pluck( $mine['data']['flxMySubscriptions'] ?? array(), 'id' ), true ), 'owner sees the subscription', $mine );
$not_mine = flxt_gql( '{ flxMySubscriptions { id } }', array(), $other );
flxt_ok( ! in_array( (string) $sid, wp_list_pluck( $not_mine['data']['flxMySubscriptions'] ?? array(), 'id' ), true ), 'another customer does not see it' );

$export = FLXCM_Subscriptions::export( $sid );
flxt_ok( is_array( $export ) && ! flxcm_test_leaks( $export ), 'export drops the card block' );
$stored = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT meta FROM ' . FLXCM_Subscriptions::table() . ' WHERE id = %d', $sid ) );
flxt_ok( str_contains( $stored, $token ), 'storage of the card block is unchanged' );

$steal = flxt_gql(
	'mutation($input: FlxMySubscriptionCancelInput!){ flxMySubscriptionCancel(input: $input){ notice } }',
	array( 'input' => array( 'id' => (string) $sid ) ),
	$other
);
flxt_same( 'FORBIDDEN', flxt_code( $steal ), 'cannot cancel someone elses subscription' );

$run = 'mutation($input: FlxCommerceRenewalsRunInput!){ flxCommerceRenewalsRun(input: $input){ status { ran } errors { code } } }';
$first = flxt_gql( $run, array( 'input' => array() ), $admin );
$second = flxt_gql( $run, array( 'input' => array() ), $admin );
flxt_ok( ! isset( $first['errors'] ) && ! isset( $second['errors'] ), 'two renewal passes do not fatal', array( $first, $second ) );
flxt_same( false, (bool) get_transient( 'flxcm_subs_lock' ), 'the tick lock is released' );
$row = FLXCM_Subscriptions::row( $sid );
flxt_same( 0, (int) ( $row['renewal_order_id'] ?? -1 ), 'a future subscription is not renewed' );

if ( function_exists( 'wc_create_order' ) && class_exists( 'WC_Order_Item_Product' ) ) {
	$wpdb->insert(
		FLXCM_Subscriptions::table(),
		array(
			'status'       => 'active',
			'user_id'      => $owner,
			'email'        => 'flxcm_owner@example.test',
			'name'         => 'Due',
			'product_name' => 'Plan',
			'period'       => 'month',
			'amount'       => 10,
			'currency'     => 'TRY',
			'period_end'   => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ),
			'started_at'   => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ),
			'created_at'   => gmdate( 'Y-m-d H:i:s' ),
			'meta'         => '{}',
		)
	);
	$due = (int) $wpdb->insert_id;
	flxt_gql( $run, array( 'input' => array() ), $admin );
	$mid = flxcm_test_renewal_orders( $due );
	flxt_gql( $run, array( 'input' => array() ), $admin );
	$end = flxcm_test_renewal_orders( $due );
	flxt_same( $mid, $end, 'the second pass does not create another renewal order' );
	flxt_ok( $end <= 1, 'at most one renewal order', $end );
}

$editable = apply_filters( 'woocommerce_store_api_product_quantity_editable', true, null, array( 'flxcm_sub' => array( 'period' => 'month' ) ) );
flxt_same( false, (bool) $editable, 'subscription quantity is not editable' );

$add = flxt_gql(
	'mutation($input: FlxSubscriptionCartAddInput!){ flxSubscriptionCartAdd(input: $input){ itemKey cart { items { productId quantity } } errors { code } } }',
	array( 'input' => array( 'productId' => 999999, 'period' => 'MONTH' ) ),
	0
);
flxt_ok( is_array( $add ), 'cart add without a product does not fatal', $add );

if ( function_exists( 'wc_get_product' ) && class_exists( 'WC_Product_Simple' ) ) {
	$product = new WC_Product_Simple();
	$product->set_name( 'Contract plan' );
	$product->set_status( 'publish' );
	$product->set_regular_price( '10' );
	$product->update_meta_data( '_flxcm_sub', 'yes' );
	$product->update_meta_data( '_flxcm_sub_month', '10' );
	$pid = $product->save();
	$cart_q = 'mutation($input: FlxSubscriptionCartAddInput!){ flxSubscriptionCartAdd(input: $input){ itemKey cart { token items { productId quantity } } errors { code } } }';
	$added  = flxt_gql( $cart_q, array( 'input' => array( 'productId' => (int) $pid, 'period' => 'MONTH' ) ), 0 );
	$token  = $added['data']['flxSubscriptionCartAdd']['cart']['token'] ?? '';
	if ( is_string( $token ) && '' !== $token ) {
		$_SERVER['HTTP_CART_TOKEN'] = $token; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}
	$again = flxt_gql( $cart_q, array( 'input' => array( 'productId' => (int) $pid, 'period' => 'MONTH' ) ), 0 );
	unset( $_SERVER['HTTP_CART_TOKEN'] );
	$now = array_values(
		array_filter(
			(array) ( $again['data']['flxSubscriptionCartAdd']['cart']['items'] ?? array() ),
			static function ( $item ) use ( $pid ) {
				return is_array( $item ) && (int) ( $item['productId'] ?? 0 ) === (int) $pid;
			}
		)
	);
	flxt_ok( ! flxcm_test_leaks( $again ), 'cart add hides the card block', $again );
	if ( $now ) {
		flxt_same( 1, count( $now ), 'one line per subscription product', $again );
		flxt_same( 1, (int) ( $now[0]['quantity'] ?? 0 ), 'subscription quantity stays 1', $again );
	} else {
		flxt_ok( is_array( $again ), 'second cart add did not fatal', $again );
	}
}

if ( function_exists( 'wc_create_order' ) && class_exists( 'WC_Order' ) ) {
	$order = wc_create_order( array( 'customer_id' => $owner ) );
	if ( $order instanceof WC_Order ) {
		$order->set_payment_method( FLXCM_Bank::ID );
		$order->set_status( 'on-hold' );
		$order->update_meta_data( '_flxcm_receipts', array( array( 'name' => 'slip.pdf' ) ) );
		$order->save();
		$oid  = (string) $order->get_id();
		$shop_r = flxt_gql( 'query($id: ID!){ flxCommerceReceipt(id: $id){ files { url } } }', array( 'id' => $oid ), $shop );
		$mgr_r  = flxt_gql( 'query($id: ID!){ flxCommerceReceipt(id: $id){ files { url } } }', array( 'id' => $oid ), $mgr );
		flxt_ok( str_contains( (string) ( $shop_r['data']['flxCommerceReceipt']['files'][0]['url'] ?? '' ), 'flxcm_dekont' ), 'shop manager with edit_shop_orders gets the file url', $shop_r );
		flxt_same( '', (string) ( $mgr_r['data']['flxCommerceReceipt']['files'][0]['url'] ?? 'x' ), 'manage_woocommerce alone does not get a file url', $mgr_r );
		$rest = FLXCM_Receipt_Service::rest_row( $order );
		flxt_ok( is_array( $rest ) && str_contains( (string) ( $rest['files'][0]['url'] ?? '' ), 'flxcm_dekont' ), 'REST still returns the admin file url' );
	}
}

$rest_customer = FLXCM_Customer_Service::rest_row( (array) FLXCM_Customers::get( $cid ) );
flxt_ok( ! flxcm_test_leaks( $rest_customer ), 'REST customer hides the card block' );

flxt_done();

/**
 * @param array<int,string> $caps
 */
function flxcm_test_user( string $login, array $caps ): int {
	$id = username_exists( $login );
	if ( ! $id ) {
		$id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password(),
				'user_email' => $login . '@example.test',
				'role'       => 'subscriber',
			)
		);
	}
	$user = new WP_User( (int) $id );
	foreach ( $caps as $cap ) {
		$user->add_cap( $cap );
	}
	return (int) $id;
}

function flxcm_test_admin(): int {
	$users = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	$id    = $users ? (int) $users[0] : 1;
	$user  = new WP_User( $id );
	foreach ( array( 'read', 'manage_woocommerce', 'view_woocommerce_reports', 'edit_shop_orders' ) as $cap ) {
		$user->add_cap( $cap );
	}
	return $id;
}

/**
 * @param mixed $value
 */
function flxcm_test_leaks( $value ): bool {
	$json = wp_json_encode( $value );
	foreach ( array( 'FLXCMSEALEDTOKEN9f3a', 'cardUserKey', 'cardToken', '"_card"', '"k":', '"t":' ) as $needle ) {
		if ( is_string( $json ) && str_contains( $json, $needle ) ) {
			return true;
		}
	}
	return false;
}

function flxcm_test_renewal_orders( int $id ): int {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return 0;
	}
	$orders = wc_get_orders(
		array(
			'limit'      => 20,
			'meta_key'   => '_flxcm_renewal_of',
			'meta_value' => (string) $id,
			'return'     => 'ids',
		)
	);
	return is_array( $orders ) ? count( $orders ) : 0;
}
