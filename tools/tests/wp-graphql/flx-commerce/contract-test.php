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

set_transient( 'flxcm_free_min', 'stale', MINUTE_IN_SECONDS );
FLXCM_Settings_Service::save( FLXCM_Settings_Service::from_request( array( 'low_stock' => '21' ) ) );
$from_classic = flxt_gql( '{ flxCommerceSettings { lowStock } }', array(), $mgr );
flxt_same( 21, (int) ( $from_classic['data']['flxCommerceSettings']['lowStock'] ?? -1 ), 'graphql reads the classic settings save' );
$from_gql = flxt_gql(
	'mutation($input: FlxCommerceSettingsSaveInput!){ flxCommerceSettingsSave(input: $input){ settings { lowStock } errors { code } } }',
	array( 'input' => array( 'lowStock' => 14 ) ),
	$mgr
);
flxt_same( 14, (int) ( flxcm_settings()['low_stock'] ?? -1 ), 'classic reader sees the graphql settings save' );
flxt_same( 14, (int) ( $from_gql['data']['flxCommerceSettingsSave']['settings']['lowStock'] ?? -1 ), 'graphql settings payload' );
flxt_ok( 'stale' !== get_transient( 'flxcm_free_min' ), 'settings save drops the stale free-shipping cache' );

// Bank settings go through the gateway's WooCommerce Settings API (WooCommerce > Payments), not a direct option write.
if ( flxcm_wc_ready() && class_exists( 'FLXCM_Gateway_Bank' ) ) {
	$gateway = WC()->payment_gateways()->payment_gateways()[ FLXCM_Bank::ID ] ?? null;
	flxt_ok( $gateway instanceof WC_Payment_Gateway, 'bank gateway is registered' );
	if ( $gateway instanceof WC_Payment_Gateway ) {
		$filtered = 0;
		add_filter(
			'woocommerce_settings_api_sanitized_fields_' . FLXCM_Bank::ID,
			static function ( $fields ) use ( &$filtered ) {
				++$filtered;
				return $fields;
			}
		);
		$multi = "Line one\nLine two <b>bold</b>";
		$gateway->set_post_data(
			array(
				'woocommerce_flxcm_bank_enabled'     => '1',
				'woocommerce_flxcm_bank_title'       => 'Classic Bank',
				'woocommerce_flxcm_bank_description' => wp_slash( $multi ),
				'woocommerce_flxcm_bank_holder'      => '',
				'woocommerce_flxcm_bank_iban'        => 'TR000000000000000000000001',
				'woocommerce_flxcm_bank_bank'        => '',
				'woocommerce_flxcm_bank_ref_format'  => '',
				'woocommerce_flxcm_bank_ref_prefix'  => 'SP',
			)
		);
		do_action( 'woocommerce_update_options_payment_gateways_' . FLXCM_Bank::ID );
		$gateway->set_post_data( array() );
		flxt_same( 1, $filtered, 'classic WooCommerce save ran the sanitized-fields filter' );

		$bank_from_classic = flxt_gql( '{ flxCommerceBankSettings { enabled title iban description } }', array(), $mgr );
		flxt_same( 'Classic Bank', $bank_from_classic['data']['flxCommerceBankSettings']['title'] ?? '', 'graphql reads the classic bank settings save' );
		flxt_same( true, $bank_from_classic['data']['flxCommerceBankSettings']['enabled'] ?? false, 'graphql reads the classic bank enabled flag' );
		flxt_same( $multi, $bank_from_classic['data']['flxCommerceBankSettings']['description'] ?? '', 'graphql reads the multi-line description' );

		$toggled = flxt_gql(
			'mutation($input: FlxCommerceBankSettingsSaveInput!){ flxCommerceBankSettingsSave(input: $input){ settings { enabled description } errors { code } } }',
			array( 'input' => array( 'enabled' => false ) ),
			$mgr
		);
		flxt_same( false, $toggled['data']['flxCommerceBankSettingsSave']['settings']['enabled'] ?? true, 'graphql toggles the bank method off' );
		flxt_same( $multi, $toggled['data']['flxCommerceBankSettingsSave']['settings']['description'] ?? '', 'toggling enabled keeps the description line breaks and markup' );
		flxt_same( 2, $filtered, 'graphql bank save ran the sanitized-fields filter' );
		$stored_bank = get_option( FLXCM_Settings_Service::BANK_OPTION, array() );
		flxt_same( $multi, is_array( $stored_bank ) ? (string) ( $stored_bank['description'] ?? '' ) : '', 'stored description is untouched' );
		flxt_same( 'no', is_array( $stored_bank ) ? (string) ( $stored_bank['enabled'] ?? '' ) : '', 'stored enabled flag follows the toggle' );
		flxt_same( 'TR000000000000000000000001', is_array( $stored_bank ) ? (string) ( $stored_bank['iban'] ?? '' ) : '', 'toggling enabled keeps the IBAN' );
		global $wpdb;
		$autoload = (string) $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", FLXCM_Settings_Service::BANK_OPTION ) );
		flxt_ok( in_array( $autoload, array( 'yes', 'on', 'auto-on', 'auto' ), true ), 'bank option keeps the WooCommerce autoload', $autoload );

		$bank_from_gql = flxt_gql(
			'mutation($input: FlxCommerceBankSettingsSaveInput!){ flxCommerceBankSettingsSave(input: $input){ settings { title iban } errors { code } } }',
			array( 'input' => array( 'title' => 'GQL Bank', 'iban' => 'TR000000000000000000000002' ) ),
			$mgr
		);
		$stored_bank = get_option( FLXCM_Settings_Service::BANK_OPTION, array() );
		flxt_same( 'GQL Bank', is_array( $stored_bank ) ? (string) ( $stored_bank['title'] ?? '' ) : '', 'classic option sees the graphql bank settings save' );
		flxt_same( 'TR000000000000000000000002', $bank_from_gql['data']['flxCommerceBankSettingsSave']['settings']['iban'] ?? '', 'graphql bank settings payload' );
	}
} else {
	$no_wc = flxt_gql(
		'mutation($input: FlxCommerceBankSettingsSaveInput!){ flxCommerceBankSettingsSave(input: $input){ settings { title } errors { code } } }',
		array( 'input' => array( 'title' => 'No gateway' ) ),
		$mgr
	);
	flxt_same( 'INVALID_INPUT', $no_wc['data']['flxCommerceBankSettingsSave']['errors'][0]['code'] ?? '', 'bank settings need the WooCommerce gateway' );
	flxt_ok( false === get_option( FLXCM_Settings_Service::BANK_OPTION, false ), 'no direct bank option write without the gateway' );
}

// Donate-only keys keep their value when Flux Donate is not active (same guard as the classic form).
$donate = FLXCM_Settings_Service::from_input( array( 'excludeDonations' => true, 'deferToDonate' => true, 'hideCompany' => true ) );
flxt_same( class_exists( 'FLXB_WC_Cart' ), array_key_exists( 'exclude_donations', $donate ), 'excludeDonations needs Flux Donate' );
flxt_same( class_exists( 'FLXB_WC_Havale' ), array_key_exists( 'defer_to_donate', $donate ), 'deferToDonate needs Flux Donate' );
flxt_same( 1, $donate['hide_company'] ?? 0, 'other boolean keys still pass' );

// 0.7.0: the shell preloads the first queries of the start route (same variables as the screen).
if ( ! class_exists( 'FLXCM_Admin' ) ) {
	require_once FLXCM_DIR . 'includes/admin/class-flxcm-admin.php';
}
flxt_same( array( 'FlxCommerceSummary', array( 'days' => 30 ) ), FLXCM_Admin::preload_for( 'overview' )[0], 'preload: overview summary' );
flxt_same( array( 'FlxCommerceReportProducts', array( 'first' => 6, 'preset' => 'D30', 'from' => null, 'to' => null ) ), FLXCM_Admin::preload_for( 'overview' )[1], 'preload: overview products' );
flxt_same( array( 'FlxCommerceCustomer', array( 'id' => '12' ) ), FLXCM_Admin::preload_for( 'customer:12' )[0], 'preload: a customer' );
flxt_same( 5, count( FLXCM_Admin::preload_for( 'reports' ) ), 'preload: the report queries' );
flxt_same( array(), FLXCM_Admin::preload_for( 'reports?preset=d7' ), 'preload: a filtered route is left to the screen' );

foreach ( array( 'view_woocommerce_reports', 'manage_woocommerce', 'edit_shop_orders' ) as $cap ) {
	flxt_ok( isset( flx_gql_capabilities()[ $cap ] ), 'catalog keeps ' . $cap );
}
$viewer = flxt_gql( '{ flxViewer { capabilities } }', array(), $mgr );
$viewer_caps = $viewer['data']['flxViewer']['capabilities'] ?? array();
flxt_ok( in_array( 'manage_woocommerce', $viewer_caps, true ), 'viewer lists manage_woocommerce', $viewer );
flxt_ok( ! in_array( 'edit_shop_orders', $viewer_caps, true ), 'viewer omits a cap the user does not have', $viewer );

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

$noted = flxt_gql(
	'mutation($input: FlxCommerceCustomerNoteInput!){ flxCommerceCustomerNote(input: $input){ customer { note } errors { code } } }',
	array( 'input' => array( 'id' => (string) $cid, 'note' => 'Round trip' ) ),
	$mgr
);
$stored_customer = FLXCM_Customers::get( $cid );
flxt_same( 'Round trip', is_array( $stored_customer ) ? (string) $stored_customer['note'] : '', 'classic reader sees the graphql note' );
flxt_same( 'Round trip', $noted['data']['flxCommerceCustomerNote']['customer']['note'] ?? '', 'graphql note payload' );
FLXCM_Customer_Service::save_note( $cid, 'Classic note', true );
$reread = flxt_gql( 'query($id: ID!){ flxCommerceCustomer(id: $id) { note } }', array( 'id' => (string) $cid ), $view );
flxt_same( 'Classic note', $reread['data']['flxCommerceCustomer']['note'] ?? '', 'graphql reads the classic note' );

$tz = get_option( 'timezone_string' );
update_option( 'timezone_string', 'Europe/Istanbul' );
$gmt = '2020-06-01 15:04:05';
$wpdb->update( FLXCM_Install::table(), array( 'first_at' => $gmt ), array( 'id' => $cid ) );
flxcm_gql_purge( array( 'flx-commerce:customer:' . $cid, 'flx-commerce:customers' ) );
$when = flxt_gql( 'query($id: ID!){ flxCommerceCustomer(id: $id) { firstAt { timestamp iso } } }', array( 'id' => (string) $cid ), $view );
flxt_same( strtotime( $gmt . ' UTC' ), (int) ( $when['data']['flxCommerceCustomer']['firstAt']['timestamp'] ?? 0 ), 'stored gmt time is not shifted by the site timezone' );
flxt_same( '2020-06-01T15:04:05Z', (string) ( $when['data']['flxCommerceCustomer']['firstAt']['iso'] ?? '' ), 'customer time iso is utc' );
update_option( 'timezone_string', $tz );

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

$cancelled_hooks = 0;
add_action(
	'flxcm_subscription_cancelled',
	static function () use ( &$cancelled_hooks ) {
		++$cancelled_hooks;
	},
	10,
	2
);
$cancelled = flxt_gql(
	'mutation($input: FlxCommerceSubscriptionCancelInput!){ flxCommerceSubscriptionCancel(input: $input){ subscription { status } errors { code } } }',
	array( 'input' => array( 'id' => (string) $sid ) ),
	$mgr
);
flxt_same( 1, $cancelled_hooks, 'cancel runs the classic hook once' );
$after_cancel = FLXCM_Subscriptions::export( $sid );
flxt_same( 'cancelled', is_array( $after_cancel ) ? (string) $after_cancel['status'] : '', 'classic export reads the graphql cancel' );
flxt_same( 'cancelled', $cancelled['data']['flxCommerceSubscriptionCancel']['subscription']['status'] ?? '', 'graphql cancel payload' );

$classic_resume = FLXCM_Subscription_Service::admin_act( $sid, 'resume' );
flxt_same( 'resume', $classic_resume['done'] ?? '', 'classic admin_act resumes subscription' );
$after_resume = flxt_gql( 'query($id: ID!){ flxCommerceSubscription(id: $id) { status autoRenew } }', array( 'id' => (string) $sid ), $view );
flxt_same( 'active', $after_resume['data']['flxCommerceSubscription']['status'] ?? '', 'graphql reads the classic subscription resume' );
flxt_same( true, $after_resume['data']['flxCommerceSubscription']['autoRenew'] ?? false, 'graphql reads autoRenew true after classic resume' );

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

		$approved = flxt_gql(
			'mutation($input: FlxCommerceReceiptApproveInput!){ flxCommerceReceiptApprove(input: $input){ receipt { status } errors { code } } }',
			array( 'input' => array( 'id' => $oid ) ),
			$shop
		);
		$reloaded        = wc_get_order( (int) $oid );
		$approved_status = (string) ( $approved['data']['flxCommerceReceiptApprove']['receipt']['status'] ?? '' );
		flxt_ok( in_array( $approved_status, array( 'processing', 'completed' ), true ), 'graphql approve transitions to a paid status', $approved );
		flxt_ok(
			$reloaded instanceof WC_Order && $reloaded->is_paid() && ! FLXCM_Bank::awaiting( $reloaded ) && '' !== (string) $reloaded->get_meta( '_flxcm_confirmed' ) && $approved_status === $reloaded->get_status(),
			'classic order reflects graphql receipt approval',
			$reloaded instanceof WC_Order ? $reloaded->get_status() : null
		);

		$order2 = wc_create_order( array( 'customer_id' => $owner ) );
		if ( $order2 instanceof WC_Order ) {
			$order2->set_payment_method( FLXCM_Bank::ID );
			$order2->set_status( 'on-hold' );
			$order2->save();
			FLXCM_Bank::mark_paid( $order2 );
			$reloaded2 = wc_get_order( $order2->get_id() );
			$reread2   = flxt_gql( 'query($id: ID!){ flxCommerceReceipt(id: $id){ status } }', array( 'id' => (string) $order2->get_id() ), $shop );
			$status2   = (string) ( $reread2['data']['flxCommerceReceipt']['status'] ?? '' );
			flxt_ok(
				in_array( $status2, array( 'processing', 'completed' ), true ) && $reloaded2 instanceof WC_Order && $status2 === $reloaded2->get_status(),
				'graphql reads classic FLXCM_Bank::mark_paid status',
				$reread2
			);
		}

		$written = wc_create_order();
		if ( $written instanceof WC_Order ) {
			$written->set_billing_email( 'flxcm-written@example.test' );
			$written->set_billing_first_name( 'Written' );
			$written->set_billing_last_name( 'By order' );
			$written->set_status( 'processing' );
			$written->set_total( '18' );
			$written->save();
			FLXCM_Customers::recompute( 'flxcm-written@example.test' );
			$wrote = FLXCM_Customers::by_email( 'flxcm-written@example.test' );
			flxt_ok( is_array( $wrote ), 'classic recompute wrote the customer from the order', $wrote );
			if ( is_array( $wrote ) ) {
				$via = flxt_gql( 'query($id: ID!){ flxCommerceCustomer(id: $id) { email orders guest } }', array( 'id' => (string) $wrote['id'] ), $view );
				flxt_same( 'flxcm-written@example.test', $via['data']['flxCommerceCustomer']['email'] ?? '', 'graphql reads the customer the order writer stored' );
				flxt_same( (int) $wrote['orders'], (int) ( $via['data']['flxCommerceCustomer']['orders'] ?? -1 ), 'graphql order count matches the classic row' );
				flxt_same( 0 === (int) $wrote['user_id'], (bool) ( $via['data']['flxCommerceCustomer']['guest'] ?? false ), 'graphql guest flag matches the classic row' );
			}
		}
	}
}

$rest_customer = FLXCM_Customer_Service::rest_row( (array) FLXCM_Customers::get( $cid ) );
flxt_ok( ! flxcm_test_leaks( $rest_customer ), 'REST customer hides the card block' );

// My Account: the same ownership set as the classic page (any status), the classic notices.
$gone_at = gmdate( 'Y-m-d H:i:s', time() - 5 * DAY_IN_SECONDS );
$wpdb->insert(
	FLXCM_Subscriptions::table(),
	array(
		'status'       => 'expired',
		'user_id'      => $owner,
		'email'        => 'flxcm_owner@example.test',
		'name'         => 'Owner',
		'product_name' => 'Old plan',
		'period'       => 'month',
		'amount'       => 10,
		'currency'     => 'TRY',
		'period_end'   => $gone_at,
		'started_at'   => $gone_at,
		'created_at'   => $gone_at,
	)
);
$eid = (int) $wpdb->insert_id;
$own_expired = FLXCM_Subscription_Service::account_act( $eid, 'resume', $owner );
flxt_ok( 'FORBIDDEN' !== $own_expired['code'] && 'NOT_FOUND' !== $own_expired['code'], 'owner may act on an expired subscription like the classic page', $own_expired );
$other_expired = FLXCM_Subscription_Service::account_act( $eid, 'resume', $other );
flxt_same( 'FORBIDDEN', $other_expired['code'], 'another customer cannot act on it' );
$missing = FLXCM_Subscription_Service::account_act( 99999999, 'cancel', $owner );
flxt_same( 'NOT_FOUND', $missing['code'], 'service keeps NOT_FOUND for GraphQL' );
$not_allowed = array( 'message' => 'You are not allowed to do this.', 'type' => 'error' );
flxt_same( $not_allowed, FLXCM_Sub_Account::notice_for( 'cancel', $missing ), 'classic page shows "not allowed" for a missing id' );
flxt_same( $not_allowed, FLXCM_Sub_Account::notice_for( 'cancel', $other_expired ), 'classic page shows "not allowed" for a foreign id' );
flxt_same( array( 'message' => 'Renewal is back on.', 'type' => 'success' ), FLXCM_Sub_Account::notice_for( 'resume', array( 'code' => 'OK', 'type' => 'success', 'notice' => 'Renewal is back on.' ) ), 'success notices keep the default woocommerce type' );
flxt_same( null, FLXCM_Sub_Account::notice_for( 'cancel', array( 'code' => 'CONFLICT', 'type' => 'error', 'notice' => 'x' ) ), 'a failed cancel stays silent like the classic page' );
flxt_same( array( 'message' => 'Nope', 'type' => 'error' ), FLXCM_Sub_Account::notice_for( 'renew', array( 'code' => 'CONFLICT', 'type' => 'error', 'notice' => 'Nope' ) ), 'a failed renewal shows its error' );

// Receipt pages come straight from the query window.
if ( function_exists( 'wc_create_order' ) && class_exists( 'WC_Order' ) ) {
	for ( $n = 0; $n < 3; $n++ ) {
		$paged = wc_create_order();
		if ( $paged instanceof WC_Order ) {
			$paged->set_payment_method( FLXCM_Bank::ID );
			$paged->set_status( 'on-hold' );
			$paged->save();
		}
	}
	$first  = FLXCM_Receipt_Service::page( 0, 2 );
	$second = FLXCM_Receipt_Service::page( 1, 1 );
	flxt_same( 2, count( $first['items'] ), 'receipt page returns only the requested window' );
	flxt_same( 1, count( $second['items'] ), 'receipt page honours the offset' );
	flxt_same( $first['items'][1]['id'] ?? 'a', $second['items'][0]['id'] ?? 'b', 'offset pages line up' );
	flxt_ok( $first['total'] >= 3, 'receipt total counts every waiting order', $first['total'] );
}

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
