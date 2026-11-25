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

/** First payload error code of a mutation, or null. */
function flxs_t_err( array $r, string $field ): ?string {
	$code = flxs_t_payload( $r, $field )['errors'][0]['code'] ?? null;
	return is_string( $code ) ? $code : null;
}

/** Queue rows of one certificate, oldest first. */
function flxs_t_msgs( int $cert, string $source = '' ): array {
	global $wpdb;
	$sql = 'SELECT * FROM ' . flxs_table( 'messages' ) . ' WHERE certificate_id = %d' . ( '' !== $source ? ' AND source = %s' : '' ) . ' ORDER BY id';
	return (array) $wpdb->get_results( '' !== $source ? $wpdb->prepare( $sql, $cert, $source ) : $wpdb->prepare( $sql, $cert ), ARRAY_A );
}

/** A certificate and its holder made with the classic functions: [certificate id, person id, code]. */
function flxs_t_make( int $tpl, string $name, string $email ): array {
	$p    = flxs_upsert_person( $name, '', $email );
	$pid  = is_array( $p ) ? (int) ( $p['person']['id'] ?? 0 ) : 0;
	$cert = $pid ? flxs_create_certificate( $tpl, $pid, array( 'ad_soyad' => $name, 'note_field' => 'x' ) ) : null;
	return array( is_array( $cert ) ? (int) $cert['id'] : 0, $pid, is_array( $cert ) ? (string) $cert['code'] : '' );
}

/** POST to the classic REST routes as a user. */
function flxs_t_rest( string $route, array $params, int $user ): WP_REST_Response {
	wp_set_current_user( $user );
	$req = new WP_REST_Request( 'POST', '/flx-certificates/v1' . $route );
	$req->set_body_params( $params );
	return rest_do_request( $req );
}

/** Simulates POST /flx/v1/upload: a file in the private folder and its transient. */
function flxs_t_upload( int $user, string $name, string $mime, string $bytes ): string {
	$id     = wp_generate_password( 20, false );
	$secret = wp_generate_password( 12, false );
	$path   = flx_gql_upload_dir() . '/' . $id . '.' . pathinfo( $name, PATHINFO_EXTENSION );
	file_put_contents( $path, $bytes );
	set_transient( 'flxgql_up_' . $id, array( 'path' => $path, 'name' => $name, 'mime' => $mime, 'size' => (int) filesize( $path ), 'user' => $user, 'context' => 'flx-certificates/file', 'secret' => wp_hash( $secret ), 'created' => time() ), HOUR_IN_SECONDS );
	return $id . '.' . $secret;
}

/** Cache tags purged since the last reset. */
$GLOBALS['flxs_t_purged'] = array();
add_action(
	'flx_gql_purged',
	static function ( $tags ) {
		$GLOBALS['flxs_t_purged'] = array_merge( $GLOBALS['flxs_t_purged'], (array) $tags );
	}
);
function flxs_t_purged( string $tag ): bool {
	return in_array( $tag, $GLOBALS['flxs_t_purged'], true );
}

$orig_recipients = get_option( 'flxs_recipients', array() );

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
$GLOBALS['flxs_t_purged'] = array();
flxt_ok( is_string( flxs_store_certificate_file( $row, 'pdf', $tmp ) ), 'file: PDF stored' );
flxt_ok( flxs_t_purged( 'flx-certificates:verify:' . $row['code'] ), 'purge: a stored file (the public-files path) drops the public answer' );
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

// Sending. E-mail goes through the built-in wp_mail connection, so every call below writes a real queue row.
wp_set_current_user( $admin );
[ $send_id, $send_person, $send_code ] = flxs_t_make( $tpl, 'Sendee ' . $stamp, 'sendee-' . $stamp . '@example.test' );
flxt_ok( $send_id > 0, 'send: certificate' );
flxt_same( 0, count( flxs_t_msgs( $send_id ) ), 'send: nothing is queued before a send' );
$r = flxs_t_op( 'FlxCertificateSend', array( 'input' => array( 'id' => $send_id, 'channels' => array( 'EMAIL' ) ) ), $admin );
flxt_same( true, flxs_t_payload( $r, 'flxCertificateSend' )['results'][0]['ok'] ?? null, 'send: e-mail accepted' );
$rows = flxs_t_msgs( $send_id, 'manual' );
flxt_same( 1, count( $rows ), 'send: exactly one queue row' );
flxt_same( 'sendee-' . $stamp . '@example.test', $rows[0]['recipient'] ?? null, 'send: the row goes to the holder' );
$dl = flxs_t_op( 'FlxCertificateOne', array( 'id' => $send_id ), $admin )['data']['flxCertificate']['deliveries'] ?? array();
flxt_same( 1, array_sum( array_map( static fn( $d ): int => (int) $d['sent'] + (int) $d['failed'] + (int) $d['waiting'], $dl ) ), 'send: deliveries count that row' );
$r = flxs_t_op( 'FlxCertificateSend', array( 'input' => array( 'id' => $send_id, 'channels' => array() ) ), $admin );
flxt_same( 'INVALID_INPUT', flxs_t_err( $r, 'flxCertificateSend' ), 'send: no channel is refused' );
flxs_set_certificate_status( $send_id, 'revoked' );
$r = flxs_t_op( 'FlxCertificateSend', array( 'input' => array( 'id' => $send_id, 'channels' => array( 'EMAIL' ) ) ), $admin );
flxt_same( 'CONFLICT', flxs_t_err( $r, 'flxCertificateSend' ), 'send: a revoked certificate is refused' );
flxs_set_certificate_status( $send_id, 'active' );
flxt_same( 1, count( flxs_t_msgs( $send_id ) ), 'send: refusals queue nothing' );

// Send to someone else. One shared body serves REST and GraphQL.
$st      = static fn( array $input, int $user ): array => flxs_t_op( 'FlxCertificateSendTo', array( 'input' => array_merge( array( 'id' => $send_id, 'channel' => 'EMAIL' ), $input ) ), $user );
$to_mail = 'fwd-' . $stamp . '@example.test';
$r       = $st( array( 'to' => '   ', 'save' => true ), $admin );
flxt_same( 'INVALID_INPUT', flxs_t_err( $r, 'flxCertificateSendTo' ), 'send to: an empty address is refused' );
flxt_same( 'Enter an e-mail address.', flxs_t_payload( $r, 'flxCertificateSendTo' )['errors'][0]['message'] ?? null, 'send to: the refusal is in English' );
flxt_same( 0, count( flxs_t_msgs( $send_id, 'forward' ) ), 'send to: an empty address does not fall back to the holder' );
flxt_same( $orig_recipients, get_option( 'flxs_recipients', array() ), 'send to: an empty address saves no recipient' );
$r = $st( array( 'to' => 'not-an-address', 'save' => true ), $admin );
flxt_same( 'INVALID_INPUT', flxs_t_err( $r, 'flxCertificateSendTo' ), 'send to: a bad address is refused' );
flxt_same( 0, count( flxs_t_msgs( $send_id, 'forward' ) ), 'send to: a bad address queues nothing' );
flxt_same( $orig_recipients, get_option( 'flxs_recipients', array() ), 'send to: a failed send does not remember the recipient' );
$r = $st( array( 'to' => '  ' . strtoupper( $to_mail ) . '  ', 'name' => ' <b>Fwd</b> ', 'subject' => ' <i>Hello</i> ', 'body' => "<b>Line</b> one\r\nline two", 'save' => true ), $admin );
flxt_same( array(), flxs_t_payload( $r, 'flxCertificateSendTo' )['errors'] ?? null, 'send to: no payload errors' );
$rows = flxs_t_msgs( $send_id, 'forward' );
flxt_same( 1, count( $rows ), 'send to: exactly one queue row' );
flxt_same( strtolower( $to_mail ), $rows[0]['recipient'] ?? null, 'send to: the address is trimmed' );
flxt_same( 'Hello', $rows[0]['subject'] ?? null, 'send to: the subject is sanitised' );
flxt_ok( false === strpos( (string) ( $rows[0]['body'] ?? '' ), '<b>' ) && false !== strpos( (string) ( $rows[0]['body'] ?? '' ), 'Line one' ), 'send to: the body is sanitised' );
$kept = array_values( array_filter( flxs_get_recipients(), static fn( $x ): bool => ( $x['email'] ?? '' ) === strtolower( $to_mail ) ) );
flxt_same( 1, count( $kept ), 'send to: the recipient is remembered once after a success' );
flxt_same( 'Fwd', $kept[0]['name'] ?? null, 'send to: the remembered name is sanitised' );

$res = flxs_t_rest( '/certificates/' . $send_id . '/message', array( 'channel' => 'email', 'to' => '' ), $admin );
flxt_same( array( 400, array( 'ok' => false, 'message' => 'E-posta adresi girin.' ) ), array( $res->get_status(), $res->get_data() ), 'rest forward: an empty e-mail answers as before' );
$res = flxs_t_rest( '/certificates/' . $send_id . '/message', array( 'channel' => 'sms', 'to' => ' ' ), $admin );
flxt_same( array( 400, array( 'ok' => false, 'message' => 'Telefon numarası girin.' ) ), array( $res->get_status(), $res->get_data() ), 'rest forward: an empty phone answers as before' );
$res = flxs_t_rest( '/certificates/' . $send_id . '/message', array( 'channel' => 'fax', 'to' => 'x' ), $admin );
flxt_same( array( 400, array( 'ok' => false, 'message' => 'Kanal geçersiz.' ) ), array( $res->get_status(), $res->get_data() ), 'rest forward: a bad channel answers as before' );
$res = flxs_t_rest( '/certificates/99999999/message', array( 'channel' => 'email', 'to' => 'x@example.test' ), $admin );
flxt_same( array( 404, array( 'ok' => false, 'message' => 'Sertifika bulunamadı.' ) ), array( $res->get_status(), $res->get_data() ), 'rest forward: a missing certificate answers as before' );
flxt_same( 1, count( flxs_t_msgs( $send_id, 'forward' ) ), 'rest forward: refusals queue nothing' );
$res  = flxs_t_rest( '/certificates/' . $send_id . '/message', array( 'channel' => 'email', 'to' => 'rest-' . $stamp . '@example.test', 'save' => 1 ), $admin );
$rows = flxs_t_msgs( $send_id, 'forward' );
flxt_same( 2, count( $rows ), 'rest forward: one more queue row' );
flxt_same( array( 200, array( 'ok' => true, 'message_id' => (int) ( $rows[1]['id'] ?? 0 ) ) ), array( $res->get_status(), $res->get_data() ), 'rest forward: the success answer is unchanged' );

// Bulk. Managers get the classic bulk page for a send; the other actions run per row.
[ $bulk_a, $bulk_a_person, $bulk_a_code ] = flxs_t_make( $tpl, 'BulkA ' . $stamp, 'bulka-' . $stamp . '@example.test' );
[ $bulk_b, $bulk_b_person, $bulk_b_code ] = flxs_t_make( $tpl, 'BulkB ' . $stamp, 'bulkb-' . $stamp . '@example.test' );
flxt_ok( $bulk_a > 0 && $bulk_b > 0, 'bulk: two certificates' );
$bulk = static fn( array $input, int $user ): array => flxs_t_op( 'FlxCertificateBulk', array( 'input' => $input ), $user );
$r    = $bulk( array( 'ids' => array(), 'action' => 'REVOKE' ), $admin );
flxt_same( 'INVALID_INPUT', flxs_t_err( $r, 'flxCertificateBulk' ), 'bulk: no ids is refused' );
$GLOBALS['flxs_t_purged'] = array();
$r = $bulk( array( 'ids' => array( $bulk_a, $bulk_b ), 'action' => 'REVOKE' ), $admin );
flxt_same( 2, flxs_t_payload( $r, 'flxCertificateBulk' )['updated'] ?? null, 'bulk: revoke counts both rows' );
flxt_same( array( 'revoked', 'revoked' ), array( flxs_get_certificate( $bulk_a )['status'] ?? null, flxs_get_certificate( $bulk_b )['status'] ?? null ), 'bulk: the classic reader sees the revoked rows' );
flxt_ok( flxs_t_purged( 'flx-certificates:verify:' . $bulk_a_code ), 'purge: a revoke drops the public answer' );
$r = $bulk( array( 'ids' => array( $bulk_a, $bulk_b ), 'action' => 'RESTORE' ), $admin );
flxt_same( 2, flxs_t_payload( $r, 'flxCertificateBulk' )['updated'] ?? null, 'bulk: restore counts both rows' );
$r = $bulk( array( 'ids' => array( $bulk_a, $bulk_b ), 'action' => 'REFERRER', 'referrerId' => $admin ), $admin );
flxt_same( 2, flxs_t_payload( $r, 'flxCertificateBulk' )['updated'] ?? null, 'bulk: referrer counts both rows' );
flxt_same( $admin, (int) ( flxs_get_certificate( $bulk_b )['ref_user_id'] ?? 0 ), 'bulk: the classic reader sees the referrer' );
$r = $bulk( array( 'ids' => array( $bulk_a ), 'action' => 'SEND_EMAIL' ), $admin );
flxt_same( 0, flxs_t_payload( $r, 'flxCertificateBulk' )['queued'] ?? null, 'bulk: a manager send queues nothing here' );
flxt_ok( false !== strpos( (string) ( flxs_t_payload( $r, 'flxCertificateBulk' )['redirect'] ?? '' ), 'flxs-toplu' ) && false !== strpos( (string) ( flxs_t_payload( $r, 'flxCertificateBulk' )['redirect'] ?? '' ), 'channel=email' ), 'bulk: a manager send returns the classic bulk page' );
flxt_same( 0, count( flxs_t_msgs( $bulk_a ) ), 'bulk: no queue row before the bulk page confirms' );
$GLOBALS['flxs_t_purged'] = array();
$res = flxs_t_rest( '/bulk', array( 'spec' => array( 'mode' => 'certificates', 'channel' => 'email', 'certificate_ids' => array( $bulk_a ), 'subject' => 'Bulk', 'body' => 'Link {link}' ) ), $admin );
$out = (array) $res->get_data();
flxt_same( array( 200, true, 1 ), array( $res->get_status(), $out['ok'] ?? null, $out['queued'] ?? null ), 'rest bulk: one message queued' );
flxt_same( 1, count( flxs_t_msgs( $bulk_a, 'bulk' ) ), 'rest bulk: one queue row' );
flxt_ok( flxs_t_purged( 'flx-certificates:certificate:' . $bulk_a ), 'purge: a bulk send drops the certificate answer (delivery counts)' );
$r = $bulk( array( 'ids' => array( $bulk_b ), 'action' => 'DELETE' ), $admin );
flxt_same( 1, flxs_t_payload( $r, 'flxCertificateBulk' )['updated'] ?? null, 'bulk: delete counts the row' );
flxt_same( null, flxs_get_certificate( $bulk_b ), 'bulk: the classic reader no longer finds it' );
flxt_ok( flxs_t_purged( 'flx-certificates:verify:' . $bulk_b_code ), 'purge: a delete drops the public answer' );

// Files the kit uploaded. Another user cannot reach the certificate; a taken upload cannot be used twice.
$pdf_up = flxs_t_upload( $admin, 'cert.pdf', 'application/pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n" );
$GLOBALS['flxs_t_purged'] = array();
$r = flxs_t_op( 'FlxCertificateAttachFiles', array( 'input' => array( 'id' => $bulk_a, 'pdfUploadId' => $pdf_up ) ), $admin );
flxt_same( array(), flxs_t_payload( $r, 'flxCertificateAttachFiles' )['errors'] ?? null, 'attach: a PDF is accepted' );
flxt_ok( is_string( flxs_t_payload( $r, 'flxCertificateAttachFiles' )['certificate']['pdfUrl'] ?? null ), 'attach: the node has a PDF URL' );
$stored_pdf = flxs_certificate_file_path( flxs_get_certificate( $bulk_a ), 'pdf' );
flxt_ok( is_string( $stored_pdf ) && file_exists( $stored_pdf ), 'attach: the classic reader finds the file on disk' );
flxt_ok( flxs_t_purged( 'flx-certificates:verify:' . $bulk_a_code ), 'purge: an attached file drops the public answer' );
$r = flxs_t_op( 'FlxCertificateAttachFiles', array( 'input' => array( 'id' => $bulk_a, 'pdfUploadId' => $pdf_up ) ), $admin );
flxt_ok( null !== flxt_code( $r ) || '' !== (string) flxs_t_err( $r, 'flxCertificateAttachFiles' ), 'attach: an upload cannot be taken twice' );
$r = flxs_t_op( 'FlxCertificateAttachFiles', array( 'input' => array( 'id' => $bulk_a, 'pdfUploadId' => flxs_t_upload( $admin, 'fake.pdf', 'application/pdf', 'not a pdf' ) ) ), $admin );
flxt_same( 'INVALID_INPUT', flxs_t_err( $r, 'flxCertificateAttachFiles' ), 'attach: a file that is not a PDF is refused' );
flxt_same( 'That is not a valid PDF.', flxs_t_payload( $r, 'flxCertificateAttachFiles' )['errors'][0]['message'] ?? null, 'attach: the refusal is in English' );
$r = flxs_t_op( 'FlxCertificateAttachFiles', array( 'input' => array( 'id' => $bulk_a, 'imageUploadId' => flxs_t_upload( $admin, 'cert.jpg', 'image/jpeg', "\xFF\xD8\xFF\xD9" ), 'width' => 12, 'height' => 8 ) ), $admin );
flxt_ok( is_string( flxs_t_payload( $r, 'flxCertificateAttachFiles' )['certificate']['imageUrl'] ?? null ), 'attach: a JPEG is accepted' );
flxt_same( array( 12, 8 ), array( (int) ( flxs_get_certificate( $bulk_a )['image_w'] ?? 0 ), (int) ( flxs_get_certificate( $bulk_a )['image_h'] ?? 0 ) ), 'attach: the classic reader sees the image size' );
$r = flxs_t_op( 'FlxCertificateAttachFiles', array( 'input' => array( 'id' => $bulk_a ) ), $admin );
flxt_same( 'INVALID_INPUT', flxs_t_err( $r, 'flxCertificateAttachFiles' ), 'attach: no upload id is refused' );
$r = flxs_t_op( 'FlxCertificateAttachFiles', array( 'input' => array( 'id' => 99999999, 'pdfUploadId' => 'x' ) ), $admin );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'attach: a missing certificate is NOT_FOUND' );

// Deliveries load for the whole page at once.
$GLOBALS['flxs_t_delivery_queries'] = array();
$count_deliveries                   = static function ( $sql ) {
	if ( false !== strpos( (string) $sql, 'GROUP BY certificate_id, channel, status' ) ) {
		$GLOBALS['flxs_t_delivery_queries'][] = (string) $sql;
	}
	return $sql;
};
add_filter( 'query', $count_deliveries );
$page = flxs_t_op( 'FlxCertificateRecords', array( 'first' => 10, 'filter' => array( 'search' => $stamp ) ), $admin );
remove_filter( 'query', $count_deliveries );
flxt_ok( count( $page['data']['flxCertificates']['nodes'] ?? array() ) >= 2, 'records: a page with several certificates', $page );
flxt_ok( 1 === count( $GLOBALS['flxs_t_delivery_queries'] ), 'records: deliveries are one query for the page, not one per row', $GLOBALS['flxs_t_delivery_queries'] );

// The Excel link works from a browser: a plain ampersand and a nonce the export accepts.
$page = flxs_t_op( 'FlxCertificateRecords', array( 'first' => 1, 'filter' => array( 'search' => 'a b&c', 'status' => 'ACTIVE' ) ), $admin );
$url  = (string) ( $page['data']['flxCertificateRecordsMeta']['exportUrl'] ?? '' );
flxt_ok( false !== strpos( $url, '&_wpnonce=' ), 'export url: carries a plain &_wpnonce=', $url );
flxt_ok( false === strpos( $url, '&amp;' ) && false === strpos( $url, '#038' ) && false === strpos( $url, 'amp;' ), 'export url: no HTML-escaped ampersand', $url );
$q = array();
wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
wp_set_current_user( $admin );
flxt_ok( false !== wp_verify_nonce( (string) ( $q['_wpnonce'] ?? '' ), 'flxs_cert_export' ), 'export url: the nonce is the one the export checks' );
flxt_same( array( 'flxs_cert_export', 'a b&c', 'ACTIVE' ), array( $q['action'] ?? null, $q['s'] ?? null, strtoupper( (string) ( $q['status'] ?? '' ) ) ), 'export url: the filters survive' );

// 2.3.0: the shell preloads the first queries of the start route (same variables as the screen).
require_once FLXS_DIR . 'includes/admin/flux-ui.php';
flxt_same( '{"first":20,"after":null,"filter":{}}', wp_json_encode( flxs_preload_for( 'records' )[0][1] ), 'preload: records keyed like the client (empty filter is {})' );
flxt_same( array( array( 'FlxCertificateCreateConfig', array( 'certificateId' => null ) ) ), flxs_preload_for( 'new' ), 'preload: new certificate' );
flxt_same( array( 'FlxCertificatePerson', array( 'id' => 5 ) ), flxs_preload_for( 'person:5' )[0], 'preload: a person' );
flxt_same( array(), flxs_preload_for( 'queue' ), 'preload: an island page has no query' );
$pre = graphql( array( 'query' => 'query($first:Int!,$after:String,$filter:FlxCertificateFilterInput){ flxCertificates(first:$first,after:$after,filter:$filter){ totalCount } }', 'variables' => flxs_preload_for( 'records' )[0][1] ) );
flxt_same( null, $pre['errors'] ?? null, 'preload: the records variables run' );

// Merging two people (Flux CRM) moves certificates; the cached answers of the moved ones go.
[ $keep_cert, $keep_person ] = flxs_t_make( $tpl, 'Keep ' . $stamp, 'keep-' . $stamp . '@example.test' );
[ $dup_cert, $dup_person, $dup_code ] = flxs_t_make( $tpl, 'Dup ' . $stamp, 'dup-' . $stamp . '@example.test' );
$GLOBALS['flxs_t_purged'] = array();
do_action( 'flx_person_merged', $keep_person, $dup_person );
flxt_same( $keep_person, (int) ( flxs_get_certificate( $dup_cert )['person_id'] ?? 0 ), 'merge: the classic handler moved the certificate' );
flxt_ok( flxs_t_purged( 'flx-certificates:verify:' . $dup_code ) && flxs_t_purged( 'flx-certificates:person:' . $dup_person ), 'purge: a merge drops the answers of the moved certificate and both people' );

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

// Another person's phone or e-mail is not a way in: nothing is written and nothing comes back.
wp_set_current_user( $admin );
flxs_upsert_person( 'Beta ' . $stamp, '0533 222 33 44', '', $b_person );
$wpdb->update( flxs_table( 'people' ), array( 'note' => 'secret-note-' . $stamp ), array( 'id' => $b_person ) );
$beta_before = flxs_get_person( $b_person );
$certs_now   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxs_table( 'certificates' ) );
foreach ( array( 'phone' => '0533 222 33 44', 'email' => 'beta-' . $stamp . '@example.test' ) as $kind => $value ) {
	$probe = flxs_t_op( 'FlxCertificatePersonSave', array( 'input' => array( 'name' => 'Probe ' . $kind, $kind => $value, 'note' => 'overwritten' ) ), $staff_a );
	flxt_same( 'CONFLICT', flxs_t_err( $probe, 'flxCertificatePersonSave' ), 'own_ref: a person saved with another person’s ' . $kind . ' is refused' );
	flxt_same( null, flxs_t_get( flxs_t_payload( $probe, 'flxCertificatePersonSave' ), 'person' ), 'own_ref: that save returns no person (' . $kind . ')' );
	$probe_json = (string) wp_json_encode( $probe );
	flxt_ok( false === strpos( $probe_json, 'Beta ' ) && false === strpos( $probe_json, 'secret-note' ) && false === strpos( $probe_json, 'beta-' . $stamp ), 'own_ref: that save leaks nothing (' . $kind . ')' );
	$probe = flxs_t_op( 'FlxCertificateCreate', array( 'input' => array( 'templateId' => $tpl, 'person' => array( 'name' => 'Probe ' . $kind, $kind => $value ), 'values' => wp_json_encode( array( 'note_field' => 'x' ) ) ) ), $staff_a );
	flxt_same( 'CONFLICT', flxs_t_err( $probe, 'flxCertificateCreate' ), 'own_ref: a certificate for another person’s ' . $kind . ' is refused' );
	flxt_same( null, flxs_t_get( flxs_t_payload( $probe, 'flxCertificateCreate' ), 'person' ), 'own_ref: that certificate returns no person (' . $kind . ')' );
}
flxt_same( $beta_before, flxs_get_person( $b_person ), 'own_ref: the other person is untouched by both' );
flxt_same( $certs_now, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxs_table( 'certificates' ) ), 'own_ref: no certificate was created for the other person' );
$own = flxs_t_op( 'FlxCertificatePersonSave', array( 'input' => array( 'name' => 'Alpha ' . $stamp, 'email' => 'alpha-' . $stamp . '@example.test' ) ), $staff_a );
flxt_same( $a_person, flxs_t_payload( $own, 'flxCertificatePersonSave' )['person']['databaseId'] ?? null, 'own_ref: a save that matches their own person returns it' );
$own = flxs_t_op( 'FlxCertificateCreate', array( 'input' => array( 'templateId' => $tpl, 'person' => array( 'id' => $a_person, 'name' => 'Alpha ' . $stamp ), 'values' => wp_json_encode( array( 'note_field' => 'second' ) ) ) ), $staff_a );
flxt_same( $a_person, flxs_t_payload( $own, 'flxCertificateCreate' )['person']['databaseId'] ?? null, 'own_ref: a certificate for their own person returns the person' );
$second_cert = (int) ( flxs_t_payload( $own, 'flxCertificateCreate' )['certificate']['databaseId'] ?? 0 );
if ( $second_cert ) {
	flxs_delete_certificate( $second_cert ); // The counts below expect one certificate for staff A.
}

// A certificate outside the viewer's records adds nothing to the create screen.
$tpl_row = flxs_get_template( $tpl );
flxs_save_template( $tpl, (string) $tpl_row['name'], (array) $tpl_row['config'], 'inactive' );
$has_tpl = static function ( int $user, int $cert ) use ( $tpl ): bool {
	$r = flxs_t_op( 'FlxCertificateCreateConfig', array( 'certificateId' => $cert ), $user );
	return in_array( $tpl, array_column( (array) ( $r['data']['flxCertificateCreateConfig']['templates'] ?? array() ), 'id' ), true );
};
flxt_same( true, $has_tpl( $admin, $b_id ), 'create config: an administrator editing a certificate keeps its inactive template' );
flxt_same( true, $has_tpl( $staff_a, $a_id ), 'create config: staff editing their own certificate keeps its inactive template' );
flxt_same( false, $has_tpl( $staff_a, $b_id ), 'create config: another user’s certificate id adds nothing' );
flxt_same( false, $has_tpl( $staff_a, 0 ), 'create config: an inactive template is not offered for a new one' );
flxs_save_template( $tpl, (string) $tpl_row['name'], (array) $tpl_row['config'], 'active' );

// Sending: another user's certificate is not found; a bulk send without bulk permission queues only their own.
$r = flxs_t_op( 'FlxCertificateSendTo', array( 'input' => array( 'id' => $b_id, 'channel' => 'EMAIL', 'to' => 'x-' . $stamp . '@example.test' ) ), $staff_a );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'send to: staff A cannot forward staff B’s certificate' );
$r = flxs_t_op( 'FlxCertificateSend', array( 'input' => array( 'id' => $b_id, 'channels' => array( 'EMAIL' ) ) ), $staff_a );
flxt_same( 'NOT_FOUND', flxt_code( $r ), 'send: staff A cannot send staff B’s certificate' );
flxt_same( 0, count( flxs_t_msgs( $b_id ) ), 'send: nothing was queued for the other certificate' );
$r = flxs_t_op( 'FlxCertificateBulk', array( 'input' => array( 'ids' => array( $a_id, $b_id ), 'action' => 'SEND_EMAIL' ) ), $staff_a );
flxt_same( 1, flxs_t_payload( $r, 'flxCertificateBulk' )['queued'] ?? null, 'bulk: a send without bulk permission queues the viewer’s own row only' );
flxt_ok( false !== strpos( (string) ( flxs_t_payload( $r, 'flxCertificateBulk' )['redirect'] ?? '' ), 'flxs-kuyruk' ), 'bulk: that send points at the queue' );
flxt_same( array( 1, 0 ), array( count( flxs_t_msgs( $a_id, 'manual' ) ), count( flxs_t_msgs( $b_id ) ) ), 'bulk: one queue row for staff A, none for staff B' );
$r = flxs_t_op( 'FlxCertificateBulk', array( 'input' => array( 'ids' => array( $a_id, $b_id ), 'action' => 'REVOKE' ) ), $staff_a );
flxt_same( 1, flxs_t_payload( $r, 'flxCertificateBulk' )['updated'] ?? null, 'bulk: a revoke changes only the viewer’s own row' );
flxt_same( array( 'revoked', 'active' ), array( flxs_get_certificate( $a_id )['status'] ?? null, flxs_get_certificate( $b_id )['status'] ?? null ), 'bulk: the other certificate stays active' );
flxs_t_op( 'FlxCertificateBulk', array( 'input' => array( 'ids' => array( $a_id ), 'action' => 'RESTORE' ) ), $staff_a );
$res = flxs_t_rest( '/certificates/' . $b_id . '/message', array( 'channel' => 'email', 'to' => 'x@example.test' ), $staff_a );
flxt_same( array( 404, array( 'ok' => false, 'message' => 'Sertifika bulunamadı.' ) ), array( $res->get_status(), $res->get_data() ), 'rest forward: staff A gets the classic 404 for staff B’s certificate' );
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
$probe = flxs_t_op( 'FlxCertificatePersonSave', array( 'input' => array( 'name' => 'Probe', 'email' => 'beta-' . $stamp . '@example.test', 'note' => 'overwritten' ) ), $staff_a );
flxt_same( 'CONFLICT', flxs_t_err( $probe, 'flxCertificatePersonSave' ), 'own_only: a person saved with another person’s e-mail is refused' );
flxt_same( null, flxs_t_get( flxs_t_payload( $probe, 'flxCertificatePersonSave' ), 'person' ), 'own_only: that save returns no person' );
flxt_same( $beta_before, flxs_get_person( $b_person ), 'own_only: the other person is untouched' );
$own = flxs_t_op( 'FlxCertificatePersonSave', array( 'input' => array( 'name' => 'Alpha ' . $stamp, 'email' => 'alpha-' . $stamp . '@example.test' ) ), $staff_a );
flxt_same( $a_person, flxs_t_payload( $own, 'flxCertificatePersonSave' )['person']['databaseId'] ?? null, 'own_only: a save that matches their own person returns it' );

$settings['staff_own_only'] = 0;
update_option( 'flxs_settings', $settings );

// Lookup. The honeypot does not spend a rate-limit slot. Ten accepted calls, then RATE_LIMITED.
$sentence = flxs_lookup_message();
$pot      = flxs_t_op( 'FlxCertificateLookup', array( 'input' => array( 'contact' => 'holder-' . $stamp . '@example.test', 'website' => 'https://spam.test' ) ) );
flxt_same( null, $pot['errors'] ?? null, 'lookup: honeypot is not an error' );
flxt_same( true, flxs_t_payload( $pot, 'flxCertificateLookup' )['ok'] ?? null, 'lookup: honeypot ok' );
flxt_same( $sentence, flxs_t_payload( $pot, 'flxCertificateLookup' )['message'] ?? null, 'lookup: honeypot uses the public sentence' );
flxt_ok( false === strpos( (string) wp_json_encode( $pot['data']['flxCertificateLookup'] ?? array() ), $code ), 'lookup: honeypot returns no certificate' );

$lookup_rows = static function () use ( $wpdb, $a_person ): int {
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxs_table( 'messages' ) . ' WHERE source = %s AND person_id = %d', 'lookup', $a_person ) );
};
flxt_same( 0, $lookup_rows(), 'lookup: nothing is queued before a lookup' );
$found = flxs_t_op( 'FlxCertificateLookup', array( 'input' => array( 'contact' => 'alpha-' . $stamp . '@example.test', 'website' => '' ) ) );
flxt_same( array(), flxs_t_payload( $found, 'flxCertificateLookup' )['errors'] ?? null, 'lookup: a real address has no payload errors' );
flxt_same( 1, $lookup_rows(), 'lookup: the link is queued once, to the address on file' );
$queued = (array) $wpdb->get_row( $wpdb->prepare( 'SELECT recipient FROM ' . flxs_table( 'messages' ) . ' WHERE source = %s AND person_id = %d', 'lookup', $a_person ), ARRAY_A );
flxt_same( 'alpha-' . $stamp . '@example.test', $queued['recipient'] ?? null, 'lookup: the queued message goes to the address on file' );
$again = flxs_t_op( 'FlxCertificateLookup', array( 'input' => array( 'contact' => 'alpha-' . $stamp . '@example.test', 'website' => '' ) ) );
flxt_same( $sentence, flxs_t_payload( $again, 'flxCertificateLookup' )['message'] ?? null, 'lookup: a second call gets the same sentence' );
flxt_same( 1, $lookup_rows(), 'lookup: a second call from the same address queues nothing more' );
flxt_same( $sentence, flxs_t_payload( $found, 'flxCertificateLookup' )['message'] ?? null, 'lookup: a real address gets the same sentence' );
$found_json = (string) wp_json_encode( $found['data']['flxCertificateLookup'] ?? array() );
flxt_ok( false === strpos( $found_json, (string) ( $a_row['certificate']['code'] ?? 'no-code' ) ), 'lookup: the response has no certificate code' );
flxt_ok( ! isset( $found['data']['flxCertificateLookup']['certificate'] ), 'lookup: the response has no certificate field' );

for ( $n = 0; $n < 8; $n++ ) { // The two calls above and these eight are the ten the IP limit allows.
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
// Whatever else carries this run's stamp: queue rows, certificates, people, remembered recipients.
wp_set_current_user( $admin );
$swept_people = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxs_table( 'people' ) . ' WHERE name LIKE %s OR email LIKE %s', '%' . $stamp . '%', '%' . $stamp . '%' ) ) );
$swept_certs  = $swept_people ? array_map( 'intval', (array) $wpdb->get_col( 'SELECT id FROM ' . flxs_table( 'certificates' ) . ' WHERE person_id IN (' . implode( ',', $swept_people ) . ')' ) ) : array();
foreach ( $swept_certs as $cid ) {
	flxs_delete_certificate( $cid );
}
foreach ( $swept_people as $pid ) {
	flxs_delete_person( $pid );
}
$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . flxs_table( 'messages' ) . ' WHERE recipient LIKE %s OR recipient_name LIKE %s', '%' . $stamp . '%', '%' . $stamp . '%' ) );
foreach ( array( $send_id, $bulk_a, $bulk_b ) as $cid ) {
	$wpdb->delete( flxs_table( 'messages' ), array( 'certificate_id' => $cid ) );
	flxs_delete_certificate( $cid );
}
update_option( 'flxs_recipients', $orig_recipients, false );
if ( $cap_user ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $cap_user );
}

flxt_done();
