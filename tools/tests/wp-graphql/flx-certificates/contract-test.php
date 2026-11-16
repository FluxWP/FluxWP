<?php
/**
 * Flux PDF & Certificates GraphQL contract (records, people, public verification).
 *
 *   wp eval-file tools/tests/wp-graphql/flx-certificates/contract-test.php
 */

require dirname( __DIR__ ) . '/lib.php';

echo "Flux PDF & Certificates GraphQL\n";

$GLOBALS['flxs_ops'] = array();
foreach ( (array) json_decode( (string) file_get_contents( FLXS_DIR . 'graphql/persisted-queries.json' ), true ) as $doc ) {
	if ( preg_match( '/^(?:query|mutation) (Flx\w+)/', $doc, $m ) ) {
		$GLOBALS['flxs_ops'][ $m[1] ] = $doc;
	}
}
flxt_same( 20, count( $GLOBALS['flxs_ops'] ), 'certificates ships 20 persisted operations' );

function flxs_t_op( string $name, array $vars = array(), int $user = 0 ): array {
	return flxt_gql( $GLOBALS['flxs_ops'][ $name ], $vars, $user );
}

function flxs_t_payload( array $r, string $field ): array {
	$p = $r['data'][ $field ] ?? null;
	return is_array( $p ) ? $p : array();
}

/** Null stays null. A missing key is the string "absent", so a real null can be asserted. */
function flxs_t_get( array $bag, string $key ) {
	return array_key_exists( $key, $bag ) ? $bag[ $key ] : 'absent';
}

flxs_install_roles();
$settings                    = flxs_settings();
$settings['lookup_enabled']  = 1;
$settings['staff_own_only']  = 0;
update_option( 'flxs_settings', $settings );

$admin = 1;
$sub   = (int) wp_insert_user( array( 'user_login' => 'flxs_sub_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$stamp = (string) wp_rand( 100000, 999999 );
$tpl   = (int) flxs_save_template(
	0,
	'Contract ' . $stamp,
	array(
		'title'  => 'Contract certificate',
		'fields' => array(
			array( 'key' => 'ad_soyad', 'type' => 'text', 'label' => 'Name', 'required' => true ),
			array( 'key' => 'note_field', 'type' => 'text', 'label' => 'Note', 'required' => true ),
		),
	)
);
flxt_ok( $tpl > 0, 'template: seeded' );

$GLOBALS['flxs_t_created'] = 0;
add_action(
	'flxs_certificate_created',
	static function () {
		$GLOBALS['flxs_t_created']++;
	}
);
$cap_user = (int) wp_insert_user( array( 'user_login' => 'flxs_caps_' . $stamp, 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$cap_obj  = new WP_User( $cap_user );
foreach ( array( 'flxs_use', 'flxs_manage', 'flxs_bulk', 'flxs_dashboard', 'flxs_own_ref' ) as $cap ) {
	$cap_obj->add_cap( $cap );
}
$viewer = flxt_gql( '{ flxViewer { capabilities } }', array(), $cap_user );
$have   = $viewer['data']['flxViewer']['capabilities'] ?? array();
foreach ( array( 'flxs_use', 'flxs_manage', 'flxs_bulk', 'flxs_dashboard', 'flxs_own_ref' ) as $cap ) {
	flxt_ok( is_array( $have ) && in_array( $cap, $have, true ), 'viewer: ' . $cap . ' is listed' );
}
$classic_id = 0;
$written_id = 0;

$r = flxs_t_op( 'FlxCertificateRecords', array( 'first' => 1 ) );
flxt_same( 'UNAUTHENTICATED', flxt_code( $r ), 'records: visitor → UNAUTHENTICATED' );
$r = flxs_t_op( 'FlxCertificateRecords', array( 'first' => 1 ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'records: subscriber → FORBIDDEN' );
$r = flxs_t_op( 'FlxCertificateCreate', array( 'input' => array( 'templateId' => $tpl, 'person' => array( 'name' => 'X' ), 'values' => '{}' ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'create: subscriber → FORBIDDEN' );
$r = flxs_t_op( 'FlxCertificateVerify', array( 'code' => 'no-such-' . $stamp ) );
flxt_same( null, flxs_t_get( is_array( $r['data'] ?? null ) ? $r['data'] : array(), 'flxCertificateVerify' ), 'verify: unknown code is null' );
flxt_same( null, $r['errors'] ?? null, 'verify: unknown code is not an error' );

$bad = flxt_gql( '{ flxCertificateVerify(code: "x") { phone email note databaseId person { id } } }' );
flxt_ok( ! empty( $bad['errors'] ), 'verify: phone, e-mail, note and ids are not fields' );
flxt_ok( empty( $bad['data']['flxCertificateVerify'] ), 'verify: a forbidden selection returns no certificate' );

$empty = flxs_t_op( 'FlxCertificateRecords', array( 'first' => 1, 'filter' => array( 'search' => 'nobody-' . $stamp ) ), $admin );
flxt_same( null, $empty['errors'] ?? null, 'records: administrator reads' );
flxt_same( 0, $empty['data']['flxCertificates']['totalCount'] ?? null, 'records: empty search is zero' );
flxt_same( true, $empty['data']['flxCertificateRecordsMeta']['canManage'] ?? null, 'records: administrator manages' );
flxt_same( false, $empty['data']['flxCertificateRecordsMeta']['scoped'] ?? null, 'records: administrator is not scoped' );
flxt_ok( false !== strpos( (string) ( $empty['data']['flxCertificateRecordsMeta']['exportUrl'] ?? '' ), 'flxs_cert_export' ), 'records: excel link' );

$r = flxs_t_op( 'FlxCertificateCreate', array( 'input' => array( 'templateId' => $tpl, 'person' => array( 'name' => '' ), 'values' => '{}' ) ), $admin );
flxt_same( null, $r['errors'] ?? null, 'create: empty name is not a top-level error' );
flxt_same( 'INVALID_INPUT', flxs_t_payload( $r, 'flxCertificateCreate' )['errors'][0]['code'] ?? null, 'create: empty name → INVALID_INPUT' );
flxt_same( 'name', flxs_t_payload( $r, 'flxCertificateCreate' )['errors'][0]['field'] ?? null, 'create: empty name points at name' );

$r = flxs_t_op(
	'FlxCertificateCreate',
	array(
		'input' => array(
			'templateId' => $tpl,
			'person'     => array( 'name' => 'Filled ' . $stamp, 'email' => 'filled-' . $stamp . '@example.test' ),
			'values'     => wp_json_encode( array( 'ad_soyad' => 'Filled ' . $stamp ) ),
		),
	),
	$admin
);
flxt_same( null, $r['errors'] ?? null, 'create: missing required field is not a top-level error' );
flxt_same( 'INVALID_INPUT', flxs_t_payload( $r, 'flxCertificateCreate' )['errors'][0]['code'] ?? null, 'create: missing required field → INVALID_INPUT' );

$r = flxs_t_op(
	'FlxCertificateCreate',
	array(
		'input' => array(
			'templateId' => $tpl,
			'person'     => array( 'name' => 'Holder ' . $stamp, 'email' => 'holder-' . $stamp . '@example.test', 'phone' => '0532 111 22 33' ),
			'values'     => wp_json_encode( array( 'ad_soyad' => 'Holder ' . $stamp, 'note_field' => 'kept' ) ),
			'referrerId' => 0,
			'eventId'    => 0,
		),
	),
	$admin
);
flxt_same( array(), flxs_t_payload( $r, 'flxCertificateCreate' )['errors'] ?? null, 'create: no payload errors' );
$cert = flxs_t_payload( $r, 'flxCertificateCreate' )['certificate'] ?? array();
$id   = (int) ( $cert['databaseId'] ?? 0 );
flxt_ok( $id > 0, 'create: certificate id' );
flxt_same( 'ACTIVE', $cert['status'] ?? null, 'create: status' );
flxt_same( 'Holder ' . $stamp, $cert['holderName'] ?? null, 'create: holder' );
flxt_same( null, flxs_t_get( $cert, 'pdfUrl' ), 'create: no PDF until a file is stored' );
flxt_same( 'holder-' . $stamp . '@example.test', $cert['person']['email'] ?? null, 'create: the staff row includes the e-mail' );
$holder_id = (int) ( $cert['person']['databaseId'] ?? 0 );

$r = flxs_t_op(
	'FlxCertificateUpdate',
	array(
		'input' => array(
			'id'     => $id,
			'person' => array( 'name' => 'Holder ' . $stamp, 'email' => 'holder-' . $stamp . '@example.test' ),
			'values' => wp_json_encode( array( 'ad_soyad' => 'Holder ' . $stamp, 'note_field' => 'edited' ) ),
		),
	),
	$admin
);
flxt_same( array(), flxs_t_payload( $r, 'flxCertificateUpdate' )['errors'] ?? null, 'update: no payload errors' );
flxt_ok( false !== strpos( (string) ( flxs_t_payload( $r, 'flxCertificateUpdate' )['certificate']['values'] ?? '' ), 'edited' ), 'update: values stored' );
$stored = flxs_get_certificate( $id );
$bag    = is_array( $stored ) && is_array( $stored['data'] ?? null ) ? $stored['data'] : array();
flxt_same( 'active', is_array( $stored ) ? ( $stored['status'] ?? null ) : null, 'round-trip: classic reader sees the GraphQL status' );
flxt_same( 'edited', $bag['values']['note_field'] ?? null, 'round-trip: classic reader sees the GraphQL values' );
flxt_same( 'Holder ' . $stamp, flxs_get_person( $holder_id )['name'] ?? null, 'round-trip: classic reader sees the GraphQL person' );
global $wpdb;
flxt_same( 1, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxs_table( 'logs' ) . ' WHERE action = %s AND object_id = %d', 'certificate_created', $id ) ), 'create: the classic log is written once' );
flxt_same( 1, (int) $GLOBALS['flxs_t_created'], 'create: flxs_certificate_created runs once' );

$made_person = flxs_upsert_person( 'Written ' . $stamp, '', 'written-' . $stamp . '@example.test' );
$written_id  = is_array( $made_person ) ? (int) ( $made_person['person']['id'] ?? 0 ) : 0;
$made        = $written_id ? flxs_create_certificate( $tpl, $written_id, array( 'ad_soyad' => 'Written ' . $stamp, 'note_field' => 'classic' ) ) : null;
$classic_id  = is_array( $made ) ? (int) ( $made['id'] ?? 0 ) : 0;
$via         = flxs_t_op( 'FlxCertificateOne', array( 'id' => $classic_id ), $admin );
flxt_same( 'ACTIVE', $via['data']['flxCertificate']['status'] ?? null, 'round-trip: GraphQL reads the classic status' );
flxt_same( 'Written ' . $stamp, $via['data']['flxCertificate']['holderName'] ?? null, 'round-trip: GraphQL reads the classic holder' );
flxt_ok( false !== strpos( (string) ( $via['data']['flxCertificate']['values'] ?? '' ), 'classic' ), 'round-trip: GraphQL reads the classic values' );
$seen_person = flxs_t_op( 'FlxCertificatePerson', array( 'id' => $written_id ), $admin );
flxt_same( 'Written ' . $stamp, $seen_person['data']['flxCertificatePerson']['name'] ?? null, 'round-trip: GraphQL reads the classic person' );

$r = flxs_t_op( 'FlxCertificateSetReferrer', array( 'input' => array( 'id' => $id, 'referrerId' => $admin ) ), $admin );
flxt_same( $admin, flxs_t_payload( $r, 'flxCertificateSetReferrer' )['certificate']['referrerId'] ?? null, 'referrer: set' );
$r = flxs_t_op( 'FlxCertificateSetReferrer', array( 'input' => array( 'id' => $id, 'referrerId' => 0 ) ), $admin );
flxt_same( 0, flxs_t_payload( $r, 'flxCertificateSetReferrer' )['certificate']['referrerId'] ?? null, 'referrer: cleared' );

$listed = flxs_t_op( 'FlxCertificateRecords', array( 'first' => 5, 'filter' => array( 'search' => 'Holder ' . $stamp, 'status' => 'ACTIVE', 'eventId' => 0, 'referrerId' => 0, 'templateId' => $tpl ) ), $admin );
flxt_same( 1, $listed['data']['flxCertificates']['totalCount'] ?? null, 'records: filters find the certificate' );
flxt_same( $id, $listed['data']['flxCertificates']['nodes'][0]['databaseId'] ?? null, 'records: the row is the one just created' );

$row = flxs_get_certificate( $id );
$tmp = wp_tempnam( 'flxs' );
file_put_contents( $tmp, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n" );
flxt_ok( is_string( flxs_store_certificate_file( $row, 'pdf', $tmp ) ), 'file: PDF stored' );
file_put_contents( $tmp, "\xFF\xD8\xFF\xD9" );
flxt_ok( is_string( flxs_store_certificate_file( flxs_get_certificate( $id ), 'image', $tmp, 12, 8 ) ), 'file: JPEG stored' );
@unlink( $tmp );

global $wpdb;
$wpdb->update( flxs_table( 'certificates' ), array( 'views' => 7 ), array( 'id' => $id ) );
$code = (string) flxs_get_certificate( $id )['code'];
$r    = flxs_t_op( 'FlxCertificateVerify', array( 'code' => $code ) );
$pub  = $r['data']['flxCertificateVerify'] ?? array();
flxt_same( 'ACTIVE', $pub['status'] ?? null, 'verify: active' );
flxt_same( 'Holder ' . $stamp, $pub['holderName'] ?? null, 'verify: holder name' );
flxt_same( $code, $pub['code'] ?? null, 'verify: code' );
flxt_ok( is_string( $pub['pdfUrl'] ?? null ) && '' !== $pub['pdfUrl'], 'verify: PDF URL while the file exists' );
flxt_ok( is_string( $pub['imageUrl'] ?? null ) && '' !== $pub['imageUrl'], 'verify: image URL while the file exists' );
flxt_same( 12, $pub['imageWidth'] ?? null, 'verify: image width' );
$encoded = (string) wp_json_encode( $pub );
foreach ( array( 'holder-' . $stamp . '@example.test', '5321112233', 'note_field', 'edited' ) as $secret ) {
	flxt_ok( false === strpos( $encoded, $secret ), 'verify hides ' . $secret );
}
flxt_same( 7, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT views FROM ' . flxs_table( 'certificates' ) . ' WHERE id = %d', $id ) ), 'verify: does not increment views' );

$r = flxs_t_op( 'FlxCertificateSetStatus', array( 'input' => array( 'id' => $id, 'status' => 'REVOKED' ) ), $admin );
flxt_same( 'REVOKED', flxs_t_payload( $r, 'flxCertificateSetStatus' )['certificate']['status'] ?? null, 'status: revoked' );
$revoked_node = flxs_t_payload( $r, 'flxCertificateSetStatus' )['certificate'] ?? array();
flxt_same( null, flxs_t_get( is_array( $revoked_node ) ? $revoked_node : array(), 'pdfUrl' ), 'status: revoked node has no PDF URL' );
flxt_same( null, flxs_t_get( is_array( $revoked_node ) ? $revoked_node : array(), 'imageUrl' ), 'status: revoked node has no image URL' );
$pub = flxs_t_op( 'FlxCertificateVerify', array( 'code' => $code ) )['data']['flxCertificateVerify'] ?? array();
flxt_same( 'REVOKED', $pub['status'] ?? null, 'verify: revoked' );
flxt_same( null, flxs_t_get( $pub, 'pdfUrl' ), 'verify: revoked has no PDF URL' );
flxt_same( null, flxs_t_get( $pub, 'imageUrl' ), 'verify: revoked has no image URL' );
$pdf_path = flxs_certificate_file_path( flxs_get_certificate( $id ), 'pdf' );
flxt_ok( is_string( $pdf_path ) && file_exists( $pdf_path ), 'revoke leaves the static PDF on disk' );

$r = flxs_t_op( 'FlxCertificateSetStatus', array( 'input' => array( 'id' => $id, 'status' => 'ACTIVE' ) ), $admin );
flxt_same( 'ACTIVE', flxs_t_payload( $r, 'flxCertificateSetStatus' )['certificate']['status'] ?? null, 'status: restored' );
flxt_ok( is_string( flxs_t_payload( $r, 'flxCertificateSetStatus' )['certificate']['pdfUrl'] ?? null ), 'status: restored PDF URL returns' );

$r = flxs_t_op( 'FlxCertificateDelete', array( 'input' => array( 'id' => $id ) ), $sub );
flxt_same( 'FORBIDDEN', flxt_code( $r ), 'delete: subscriber → FORBIDDEN' );
$r = flxs_t_op( 'FlxCertificateDelete', array( 'input' => array( 'id' => $id ) ), $admin );
flxt_same( true, flxs_t_payload( $r, 'flxCertificateDelete' )['deleted'] ?? null, 'delete: administrator deletes' );
$gone_cert = flxs_t_op( 'FlxCertificateOne', array( 'id' => $id ), $admin );
flxt_same( null, flxs_t_get( is_array( $gone_cert['data'] ?? null ) ? $gone_cert['data'] : array(), 'flxCertificate' ), 'delete: the node is gone' );

$old_tz  = (string) get_option( 'timezone_string' );
$old_off = get_option( 'gmt_offset' );
update_option( 'timezone_string', 'Europe/Istanbul' );
update_option( 'gmt_offset', '3' );
$back = flxs_t_op(
	'FlxCertificateCreate',
	array(
		'input' => array(
			'templateId' => $tpl,
			'person'     => array( 'name' => 'Back ' . $stamp, 'email' => 'back-' . $stamp . '@example.test' ),
			'values'     => wp_json_encode( array( 'note_field' => 'past' ) ),
			'issued'     => '2020-06-01',
		),
	),
	$admin
);
flxt_same( true, flxs_t_payload( $back, 'flxCertificateCreate' )['certificate']['backdated'] ?? null, 'create: a manager may backdate' );
$back_id     = (int) ( flxs_t_payload( $back, 'flxCertificateCreate' )['certificate']['databaseId'] ?? 0 );
$back_person = (int) ( flxs_t_payload( $back, 'flxCertificateCreate' )['person']['databaseId'] ?? 0 );
$back_row    = flxs_get_certificate( $back_id );
flxt_same( get_gmt_from_date( '2020-06-01 12:00:00' ), is_array( $back_row ) ? ( $back_row['created_at'] ?? null ) : null, 'round-trip: classic stores local noon as GMT' );
$back_code = is_array( $back_row ) ? (string) ( $back_row['code'] ?? '' ) : '';
$back_pub  = flxs_t_op( 'FlxCertificateVerify', array( 'code' => $back_code ) );
flxt_same( '2020-06-01T09:00:00Z', $back_pub['data']['flxCertificateVerify']['issuedOn']['iso'] ?? null, 'verify: issuedOn is that instant in Istanbul' );
update_option( 'timezone_string', $old_tz );
update_option( 'gmt_offset', $old_off );

$spare = flxs_t_op( 'FlxCertificatePersonSave', array( 'input' => array( 'name' => '' ) ), $admin );
flxt_same( null, $spare['errors'] ?? null, 'person save: empty name is not a top-level error' );
flxt_same( 'INVALID_INPUT', flxs_t_payload( $spare, 'flxCertificatePersonSave' )['errors'][0]['code'] ?? null, 'person save: empty name → INVALID_INPUT' );
$spare = flxs_t_op( 'FlxCertificatePersonSave', array( 'input' => array( 'name' => 'Spare ' . $stamp, 'email' => 'spare-' . $stamp . '@example.test', 'note' => 'desk' ) ), $admin );
$spare_id = (int) ( flxs_t_payload( $spare, 'flxCertificatePersonSave' )['person']['databaseId'] ?? 0 );
flxt_ok( $spare_id > 0, 'person save: id' );
flxt_same( 'desk', flxs_t_payload( $spare, 'flxCertificatePersonSave' )['person']['note'] ?? null, 'person save: note' );
flxt_same( 'Spare ' . $stamp, flxs_get_person( $spare_id )['name'] ?? null, 'round-trip: classic reader sees the GraphQL person name' );
flxt_same( 'desk', flxs_get_person( $spare_id )['note'] ?? null, 'round-trip: classic reader sees the GraphQL note' );
$gone = flxs_t_op( 'FlxCertificatePersonDelete', array( 'input' => array( 'id' => $spare_id ) ), $admin );
flxt_same( true, flxs_t_payload( $gone, 'flxCertificatePersonDelete' )['deleted'] ?? null, 'person delete: a person with no certificate' );

// Two staff members. own_ref first, then staff_own_only on the same rows.
$mk = static function ( string $login ): int {
	$id = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'flxs_personel' ) );
	$u  = new WP_User( $id );
	$u->add_cap( 'flxs_own_ref' );
	return $id;
};
$staff_a = $mk( 'flxs_a_' . $stamp );
$staff_b = $mk( 'flxs_b_' . $stamp );
flxt_ok( $staff_a > 0 && $staff_b > 0, 'staff: two users' );

$make = static function ( int $user, string $who ) use ( $tpl, $stamp ): array {
	return flxs_t_op(
		'FlxCertificateCreate',
		array(
			'input' => array(
				'templateId' => $tpl,
				'person'     => array( 'name' => $who . ' ' . $stamp, 'email' => strtolower( $who ) . '-' . $stamp . '@example.test' ),
				'values'     => wp_json_encode( array( 'note_field' => $who ) ),
			),
		),
		$user
	);
};
$a_row = flxs_t_payload( $make( $staff_a, 'Alpha' ), 'flxCertificateCreate' );
$b_row = flxs_t_payload( $make( $staff_b, 'Beta' ), 'flxCertificateCreate' );
$a_id  = (int) ( $a_row['certificate']['databaseId'] ?? 0 );
$b_id  = (int) ( $b_row['certificate']['databaseId'] ?? 0 );
$a_person = (int) ( $a_row['person']['databaseId'] ?? 0 );
$b_person = (int) ( $b_row['person']['databaseId'] ?? 0 );
flxt_ok( $a_id > 0 && $b_id > 0 && $a_person > 0 && $b_person > 0, 'staff: each created a certificate' );

$see = static function ( int $user, string $name ): int {
	$r = flxs_t_op( 'FlxCertificateRecords', array( 'first' => 5, 'filter' => array( 'search' => $name ) ), $user );
	return (int) ( $r['data']['flxCertificates']['totalCount'] ?? -1 );
};
flxt_same( 1, $see( $staff_a, 'Alpha ' . $stamp ), 'own_ref: staff A sees their certificate' );
flxt_same( 0, $see( $staff_a, 'Beta ' . $stamp ), 'own_ref: staff A does not see staff B in the list' );
flxt_same( 0, $see( $staff_b, 'Alpha ' . $stamp ), 'own_ref: staff B does not see staff A in the list' );
$hidden = flxs_t_op( 'FlxCertificateOne', array( 'id' => $b_id ), $staff_a );
flxt_same( null, flxs_t_get( is_array( $hidden['data'] ?? null ) ? $hidden['data'] : array(), 'flxCertificate' ), 'own_ref: staff A node lookup of B is null' );
flxt_same( $a_id, flxs_t_op( 'FlxCertificateOne', array( 'id' => $a_id ), $staff_a )['data']['flxCertificate']['databaseId'] ?? null, 'own_ref: staff A node lookup of their own' );
$person_b = flxs_t_op( 'FlxCertificatePerson', array( 'id' => $b_person ), $staff_a );
flxt_same( null, flxs_t_get( is_array( $person_b['data'] ?? null ) ? $person_b['data'] : array(), 'flxCertificatePerson' ), 'own_ref: staff A cannot open staff B’s person' );
flxt_same( 0, $person_b['data']['flxCertificates']['totalCount'] ?? null, 'own_ref: staff A’s person filter does not count B’s certificates' );
$dir = flxs_t_op( 'FlxCertificateDirectory', array( 'first' => 5, 'search' => 'Beta ' . $stamp ), $staff_a );
flxt_same( 0, $dir['data']['flxCertificateDirectory']['totalCount'] ?? null, 'own_ref: directory hides the other person' );
$dir = flxs_t_op( 'FlxCertificateDirectory', array( 'first' => 5, 'search' => 'Alpha ' . $stamp ), $staff_a );
flxt_same( 1, $dir['data']['flxCertificateDirectory']['totalCount'] ?? null, 'own_ref: directory counts their own person' );
flxt_same( 1, $dir['data']['flxCertificateDirectory']['nodes'][0]['certificateCount'] ?? null, 'own_ref: certificate count is scoped' );
$picked = (string) wp_json_encode( flxs_t_op( 'FlxCertificatePeople', array( 'search' => 'Beta ' . $stamp ), $staff_a ) );
flxt_ok( false === strpos( $picked, 'Beta ' . $stamp ), 'own_ref: the picker hides the other person' );
$edit = flxs_t_op( 'FlxCertificatePersonSave', array( 'input' => array( 'id' => $b_person, 'name' => 'Taken ' . $stamp ) ), $staff_a );
flxt_same( 'NOT_FOUND', flxt_code( $edit ), 'own_ref: staff A cannot edit staff B’s person' );
$edit = flxs_t_op( 'FlxCertificateSetStatus', array( 'input' => array( 'id' => $b_id, 'status' => 'REVOKED' ) ), $staff_a );
flxt_same( 'NOT_FOUND', flxt_code( $edit ), 'own_ref: staff A cannot change staff B’s certificate' );
flxt_same( true, flxs_t_op( 'FlxCertificateRecords', array( 'first' => 1 ), $staff_a )['data']['flxCertificateRecordsMeta']['scoped'] ?? null, 'own_ref: meta says the viewer is scoped' );

$by = flxs_t_op( 'FlxCertificateRecords', array( 'first' => 5, 'filter' => array( 'search' => 'Alpha ' . $stamp, 'createdBy' => $staff_b ) ), $staff_a );
flxt_same( 1, $by['data']['flxCertificates']['totalCount'] ?? null, 'own_ref: a createdBy filter cannot hide the viewer’s own row' );
$by = flxs_t_op( 'FlxCertificateRecords', array( 'first' => 5, 'filter' => array( 'search' => 'Alpha ' . $stamp, 'createdBy' => $staff_a ) ), $admin );
flxt_same( 1, $by['data']['flxCertificates']['totalCount'] ?? null, 'records: an unscoped viewer can filter by creator' );

$early = flxs_t_op( 'FlxCertificateCreate', array( 'input' => array( 'templateId' => $tpl, 'person' => array( 'name' => 'Early ' . $stamp, 'email' => 'early-' . $stamp . '@example.test' ), 'values' => wp_json_encode( array( 'note_field' => 'x' ) ), 'issued' => '2020-01-02' ) ), $staff_a );
flxt_same( 'FORBIDDEN', flxt_code( $early ), 'create: staff cannot backdate' );

// staff_own_only, with the own_ref capability taken away. Classic people SQL ignores this; GraphQL must not.
foreach ( array( $staff_a, $staff_b ) as $uid ) {
	( new WP_User( $uid ) )->remove_cap( 'flxs_own_ref' );
}
$settings['staff_own_only'] = 1;
update_option( 'flxs_settings', $settings );
flxt_same( 1, $see( $staff_a, 'Alpha ' . $stamp ), 'own_only: staff A still sees their certificate' );
flxt_same( 0, $see( $staff_a, 'Beta ' . $stamp ), 'own_only: staff A does not see staff B’s certificate' );
$hidden = flxs_t_op( 'FlxCertificateOne', array( 'id' => $b_id ), $staff_a );
flxt_same( null, flxs_t_get( is_array( $hidden['data'] ?? null ) ? $hidden['data'] : array(), 'flxCertificate' ), 'own_only: node lookup of the other certificate is null' );
$hidden_person = flxs_t_op( 'FlxCertificatePerson', array( 'id' => $b_person ), $staff_a );
flxt_same( null, flxs_t_get( is_array( $hidden_person['data'] ?? null ) ? $hidden_person['data'] : array(), 'flxCertificatePerson' ), 'own_only: the other person is null' );
flxt_same( 0, flxs_t_op( 'FlxCertificateDirectory', array( 'first' => 5, 'search' => 'Beta ' . $stamp ), $staff_a )['data']['flxCertificateDirectory']['totalCount'] ?? null, 'own_only: directory hides the other person' );
flxt_same( 1, flxs_t_op( 'FlxCertificateDirectory', array( 'first' => 5, 'search' => 'Alpha ' . $stamp ), $staff_a )['data']['flxCertificateDirectory']['nodes'][0]['certificateCount'] ?? null, 'own_only: the certificate count stays scoped' );
$edit = flxs_t_op( 'FlxCertificatePersonSave', array( 'input' => array( 'id' => $b_person, 'name' => 'Taken ' . $stamp ) ), $staff_a );
flxt_same( 'NOT_FOUND', flxt_code( $edit ), 'own_only: staff A cannot edit staff B’s person' );
flxt_same( 'Beta ' . $stamp, flxs_get_person( $b_person )['name'] ?? null, 'own_only: the other person’s name is unchanged' );

$settings['staff_own_only'] = 0;
update_option( 'flxs_settings', $settings );

// Lookup. The honeypot does not spend a rate-limit slot. Ten accepted calls, then RATE_LIMITED.
$sentence = flxs_lookup_message();
$pot      = flxs_t_op( 'FlxCertificateLookup', array( 'input' => array( 'contact' => 'holder-' . $stamp . '@example.test', 'website' => 'https://spam.test' ) ) );
flxt_same( null, $pot['errors'] ?? null, 'lookup: honeypot is not an error' );
flxt_same( true, flxs_t_payload( $pot, 'flxCertificateLookup' )['ok'] ?? null, 'lookup: honeypot ok' );
flxt_same( $sentence, flxs_t_payload( $pot, 'flxCertificateLookup' )['message'] ?? null, 'lookup: honeypot uses the public sentence' );
flxt_ok( false === strpos( (string) wp_json_encode( $pot['data']['flxCertificateLookup'] ?? array() ), $code ), 'lookup: honeypot returns no certificate' );

$found = flxs_t_op( 'FlxCertificateLookup', array( 'input' => array( 'contact' => 'alpha-' . $stamp . '@example.test', 'website' => '' ) ) );
flxt_same( array(), flxs_t_payload( $found, 'flxCertificateLookup' )['errors'] ?? null, 'lookup: a real address has no payload errors' );
flxt_same( $sentence, flxs_t_payload( $found, 'flxCertificateLookup' )['message'] ?? null, 'lookup: a real address gets the same sentence' );
$found_json = (string) wp_json_encode( $found['data']['flxCertificateLookup'] ?? array() );
flxt_ok( false === strpos( $found_json, (string) ( $a_row['certificate']['code'] ?? 'no-code' ) ), 'lookup: the response has no certificate code' );
flxt_ok( ! isset( $found['data']['flxCertificateLookup']['certificate'] ), 'lookup: the response has no certificate field' );

for ( $n = 0; $n < 9; $n++ ) {
	$bad = flxs_t_op( 'FlxCertificateLookup', array( 'input' => array( 'contact' => 'not-an-address' ) ) );
	flxt_same( null, $bad['errors'] ?? null, 'lookup: bad contact ' . ( $n + 1 ) . ' is not a top-level error' );
	flxt_same( 'INVALID_INPUT', flxs_t_payload( $bad, 'flxCertificateLookup' )['errors'][0]['code'] ?? null, 'lookup: bad contact ' . ( $n + 1 ) . ' → INVALID_INPUT' );
}
$limited = flxs_t_op( 'FlxCertificateLookup', array( 'input' => array( 'contact' => 'not-an-address' ) ) );
flxt_same( 'RATE_LIMITED', flxt_code( $limited ), 'lookup: the 11th call from one address is RATE_LIMITED' );

// People live in the shared table Flux CRM lists. Leave it as the test found it.
foreach ( array( $id, $back_id, $a_id, $b_id, $classic_id ) as $cid ) {
	if ( $cid ) {
		flxs_delete_certificate( $cid );
	}
}
foreach ( array( $holder_id, $back_person, $a_person, $b_person, $written_id ) as $pid ) {
	if ( $pid ) {
		flxs_delete_person( $pid );
	}
}
if ( $cap_user ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $cap_user );
}

flxt_done();
