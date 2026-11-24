<?php
/**
 * Flux POS GraphQL contract tests. The documents are the persisted operations the POS screen sends
 * (plugins/flx-pos/graphql/persisted-queries.json). No provider is called: account tests and refunds
 * would need a live test account.
 *
 *   wp eval-file tools/tests/wp-graphql/flx-pos/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux POS GraphQL\n";

$GLOBALS['flxpos_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXPOS_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:#[^\n]*\n)*(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxpos_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 9, count( $GLOBALS['flxpos_ops'] ), 'Flux POS ships 9 persisted operations' );

function flxpos_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxpos_ops'][ $name ], $vars, $user );
}

$admin = (int) get_user_by( 'login', 'admin' )->ID;
$sub   = (int) wp_insert_user( array( 'user_login' => 'flxpos_sub', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
delete_option( 'flxpos_accounts' );

// Access.
$r = flxpos_t_op( 'FlxPosAccounts' );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'accounts: visitor → UNAUTHENTICATED' );
$r = flxpos_t_op( 'FlxPosAccounts', array(), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'accounts: subscriber → FORBIDDEN' );
$r = flxpos_t_op( 'FlxPosMethodsUpdate', array( 'input' => array( 'order' => array() ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'methods update: subscriber → FORBIDDEN' );

// Catalog.
$r   = flxpos_t_op( 'FlxPosAccounts', array(), $admin );
$ids = array_column( (array) ( $r['data']['flxPosProviders'] ?? array() ), 'id' );
flxt_ok( empty( $r['errors'] ), 'accounts: admin reads', $r['errors'] ?? null );
flxt_ok( in_array( 'esnekpos', $ids, true ) && in_array( 'iyzico', $ids, true ), 'catalog has EsnekPOS and iyzico' );
flxt_same( array(), $r['data']['flxPosAccounts'] ?? null, 'no account yet' );

// Add an EsnekPOS account: the secret is write-only.
$r = flxpos_t_op(
	'FlxPosAccountSave',
	array(
		'input' => array(
			'provider' => 'esnekpos',
			'label'    => 'Test EsnekPOS',
			'enabled'  => true,
			'test'     => true,
			'creds'    => array(
				array( 'key' => 'merchant', 'value' => 'TEST1234' ),
				array( 'key' => 'merchant_key', 'value' => 'SECRET-ABCD-9876' ),
			),
		),
	),
	$admin
);
$acc = $r['data']['flxPosAccountSave']['account'] ?? null;
flxt_ok( is_array( $acc ) && '' !== $acc['id'], 'save: account added', $r );
$creds = array_column( (array) ( $acc['creds'] ?? array() ), null, 'key' );
flxt_same( 'TEST1234', $creds['merchant']['value'] ?? null, 'save: plain field comes back' );
flxt_same( '', $creds['merchant_key']['value'] ?? null, 'save: secret is not returned' );
flxt_same( true, $creds['merchant_key']['isSet'] ?? null, 'save: secret is marked saved' );
flxt_same( '••••9876', $creds['merchant_key']['mask'] ?? null, 'save: secret mask shows the last 4' );
flxt_ok( ! str_contains( wp_json_encode( $r ), 'SECRET-ABCD' ), 'save: the secret appears nowhere in the answer' );
flxt_ok( ! str_contains( (string) wp_json_encode( get_option( 'flxpos_accounts' ) ), 'SECRET-ABCD' ), 'save: the secret is stored encrypted' );

// Edit with an empty secret keeps it.
$r     = flxpos_t_op(
	'FlxPosAccountSave',
	array(
		'input' => array(
			'id'       => $acc['id'],
			'provider' => 'esnekpos',
			'label'    => 'EsnekPOS',
			'enabled'  => true,
			'test'     => true,
			'creds'    => array(
				array( 'key' => 'merchant', 'value' => 'TEST1234' ),
				array( 'key' => 'merchant_key', 'value' => '' ),
			),
			'installments' => array( array( 'count' => 3, 'rate' => 4.5 ) ),
		),
	),
	$admin
);
$acc2  = $r['data']['flxPosAccountSave']['account'] ?? array();
$creds = array_column( (array) ( $acc2['creds'] ?? array() ), null, 'key' );
flxt_same( true, $creds['merchant_key']['isSet'] ?? null, 'edit: empty secret keeps the saved one' );
flxt_same( 'EsnekPOS', $acc2['label'] ?? null, 'edit: label changed' );
flxt_same( array( array( 'count' => 3, 'rate' => 4.5 ) ), $acc2['installments'] ?? null, 'edit: installment table saved' );
flxt_same( 1, count( $r['data']['flxPosAccountSave']['accounts'] ?? array() ), 'edit: still one account' );

// Unknown provider is an input error.
$r = flxpos_t_op( 'FlxPosAccountSave', array( 'input' => array( 'provider' => 'nope' ) ), $admin );
flxt_same( 'provider', $r['data']['flxPosAccountSave']['errors'][0]['field'] ?? null, 'save: unknown provider → field error' );

// Settings round trip.
$r = flxpos_t_op( 'FlxPosSettingsUpdate', array( 'input' => array( 'cardTitle' => 'Kartla öde', 'cardAccount' => $acc['id'], 'showBrands' => false ) ), $admin );
$s = $r['data']['flxPosSettingsUpdate']['settings'] ?? array();
flxt_same( 'Kartla öde', $s['cardTitle'] ?? null, 'settings: title saved' );
flxt_same( $acc['id'], $s['cardAccount'] ?? null, 'settings: default account saved' );
flxt_same( false, $s['showBrands'] ?? null, 'settings: brands off' );

// Methods and transactions read.
$r = flxpos_t_op( 'FlxPosMethods', array(), $admin );
flxt_ok( empty( $r['errors'] ) && is_array( $r['data']['flxPosMethods']['methods'] ?? null ), 'methods: admin reads', $r['errors'] ?? null );
$r = flxpos_t_op( 'FlxPosTxns', array( 'first' => 10 ), $admin );
flxt_ok( empty( $r['errors'] ) && array() === ( $r['data']['flxPosTxns']['nodes'] ?? null ), 'txns: empty list', $r['errors'] ?? null );

// Delete.
$r = flxpos_t_op( 'FlxPosAccountDelete', array( 'input' => array( 'id' => $acc['id'] ) ), $admin );
flxt_same( array(), $r['data']['flxPosAccountDelete']['accounts'] ?? null, 'delete: account removed' );
$r = flxpos_t_op( 'FlxPosAccountDelete', array( 'input' => array( 'id' => 'missing' ) ), $admin );
flxt_ok( ! empty( $r['data']['flxPosAccountDelete']['errors'] ) || ! empty( $r['errors'] ), 'delete: unknown id → error' );

wp_delete_user( $sub );
flxt_done();
