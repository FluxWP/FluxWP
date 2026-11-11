<?php
/** Run only on a disposable WordPress test site with Flux Wiki, Flux Lang and Flux Setup active.
 * FLXWK_TEST_SITE=1 php tests/integration.php /path/to/wordpress
 * The Flux AI provider is stubbed: no API requests or credentials used.
 */
if ( '1' !== getenv( 'FLXWK_TEST_SITE' ) || empty( $argv[1] ) ) { exit( "Disposable test site required.\n" ); }
function flxai_ready( $source = '' ) { return true; }
function flxai_chat( array $messages, array $opts = array() ) {
	$GLOBALS['wiki_test_calls'][] = array( $messages, $opts );
	if ( false !== strpos( $messages[0]['content'], 'keyword queries' ) ) {
		return array( 'content' => $GLOBALS['wiki_test_queries'] ?? '["installation setup"]' );
	}
	$data = json_decode( $messages[1]['content'], true );
	$url = $data['pages'][0]['url'];
	return array( 'content' => 'Use [the guide](' . $url . '#install). [Untrusted](https://example.invalid/attack) <script>alert(1)</script> ![tracker](https://example.invalid/pixel)' );
}
$_SERVER['HTTP_HOST'] = '127.0.0.1:8877';
require rtrim( $argv[1], '/' ) . '/wp-load.php';
function check( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } echo "PASS: $message\n"; }
if ( function_exists( 'flxai_options' ) ) { exit( 'Use a site without the real Flux AI plugin active.' ); }
$sections = array(
	array( 'type' => 'wiki_section', 'key' => 'wiki-test-legacy', 'slug' => 'wiki-test-legacy', 'name' => 'Kurulum', 'description' => 'Eski açıklama', 'order' => 2 ),
	array( 'type' => 'wiki_section', 'key' => 'wiki-test-edited', 'slug' => 'wiki-test-edited', 'name' => 'Eklentiler', 'description' => 'Eski açıklama', 'order' => 3 ),
);
$imp = flxwk_preset_importers( array() )['wiki_section'];
$owned = array(); $terms = array(); $ids = array();
$original_settings = get_option( 'flxwk_settings' );
try {
	foreach ( $sections as $item ) {
		$id = $imp['save']( $item, $imp['find']( $item ) ); $terms[] = $id;
		delete_term_meta( $id, '_flxwk_translations' );
		$old = $imp['read']( $id );
		check( ! array_key_exists( 'translations', $old ), 'Legacy section snapshot omits absent translations' );
		$owned[ 'content:wiki_section:' . $item['key'] ] = flxk_hash( $old );
	}
	flxk_save_state( 'wiki-regression', array( 'owned' => $owned ) );
	wp_update_term( $terms[1], FLXWK_TAX, array( 'name' => 'My custom heading' ) );
	foreach ( $sections as &$item ) { $item['translations'] = array( 'en' => array( 'name' => 'Setup', 'description' => 'Installation guides' ), 'tr' => array( 'name' => $item['name'], 'description' => $item['description'] ) ); } unset( $item );
	$patch = array( 'content' => $sections );
	$plan = flxk_plan( 'wiki-regression', $patch );
	check( 'change' === $plan['ops'][0]['status'], 'Untouched legacy section is updated by actual preset engine' );
	check( 'manual' === $plan['ops'][1]['status'], 'Actual manual section edits stay protected' );
	flxk_execute( 'wiki-regression', $patch );
	check( 'Setup' === get_term( $terms[0], FLXWK_TAX )->name, 'English default receives translated heading' );
	check( 'My custom heading' === get_term( $terms[1], FLXWK_TAX )->name, 'Custom heading is preserved after execute' );
	flxl_switch_language( 'tr' ); check( 'Kurulum' === get_term( $terms[0], FLXWK_TAX )->name, 'Turkish heading is localized' ); flxl_restore_language();

	$secret = 'WIKI_PRIVATE_TEST_SENTINEL';
	foreach ( array( 'draft', 'private', 'publish' ) as $status ) {
		$id = flxwk_save_page( array( 'title' => 'Installation secret', 'md' => $secret, 'status' => $status, 'slug' => 'wiki-test-' . $status ) );
		if ( 'publish' === $status ) { wp_update_post( array( 'ID' => $id, 'post_password' => 'test-only-password' ) ); }
		$ids[] = $id;
		check( ! flxwk_public_source( $id ), 'Non-public source excluded: ' . $status );
	}
	foreach ( array( 'en', 'tr' ) as $lang ) {
		flxl_switch_language( $lang );
		foreach ( array( 'kurulum', 'kurlum', 'installation' ) as $q ) {
			$hits = flxwk_ask_search( array( $q ) );
			check( count( $hits ) > 0, "$lang interface retrieves $q" );
		}
		$answer = flxwk_ask( 'kurlum', $ids[2] );
		check( ! is_wp_error( $answer ) && count( $answer['sources'] ) > 0, 'Typo question receives cited source context' );
		check( false === strpos( $answer['html'], 'href="https://example.invalid' ) && false === strpos( $answer['html'], 'src="https://example.invalid' ) && false === strpos( $answer['html'], '<script' ), 'External links, tracking images and scripts are inert' );
		flxl_restore_language();
	}
	$GLOBALS['wiki_test_calls'] = array();
	flxwk_ask( '如何安装FluxWP' );
	check( count( $GLOBALS['wiki_test_calls'] ) === 2, 'Unmatched language uses one query translation and one grounded answer call' );
	foreach ( $GLOBALS['wiki_test_calls'] as $call ) {
		check( empty( $call[1]['tools'] ), 'AI request has no executable tools' );
		check( false === strpos( wp_json_encode( $call ), $secret ), 'Private content never reaches provider context' );
	}
	$GLOBALS['wiki_test_queries'] = '[]'; $GLOBALS['wiki_test_calls'] = array();
	$answer = flxwk_ask( 'zzxxqqvvv987654321' );
	check( empty( $answer['sources'] ) && count( $GLOBALS['wiki_test_calls'] ) === 1, 'No matching evidence returns local refusal without answer-generation call' );

	$_SERVER['REMOTE_ADDR'] = '198.51.100.81'; wp_set_current_user( 0 );
	$key = flxwk_ask_lock(); check( ! is_wp_error( $key ), 'First in-flight request accepted' );
	check( is_wp_error( flxwk_ask_lock() ), 'Second simultaneous request blocked' ); delete_option( $key );
	update_option( 'flxwk_settings', array( 'ask' => 'all', 'ask_limit' => 2 ) );
	$clear = static function () {
		global $wpdb;
		$now  = time();
		$end  = $now - ( $now % HOUR_IN_SECONDS ) + HOUR_IN_SECONDS;
		$name = '_flx_rl_' . sprintf( '%010d', $end ) . '_' . md5( flxwk_ask_identity() );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $name ) );
	};
	$clear();
	check( true === flxwk_rate_hit() && true === flxwk_rate_hit(), 'Configured hourly quota accepted' );
	$over = flxwk_rate_hit(); check( is_wp_error( $over ) && 429 === $over->get_error_data()['status'], 'Hourly quota enforced with 429' );
	$clear();
	update_option( 'flxwk_settings', array( 'ask' => 'users' ) );
	$r = new WP_REST_Request( 'POST', '/flx-wiki/v1/ask' ); $r->set_param( 'q', 'kurulum' );
	check( 403 === rest_do_request( $r )->get_status(), 'Anonymous requests rejected when sign-in required' );
	update_option( 'flxwk_settings', array( 'ask' => 'all' ) );
	$r = new WP_REST_Request( 'POST', '/flx-wiki/v1/ask' ); $r->set_param( 'q', str_repeat( 'a', 1001 ) );
	check( 400 === rest_do_request( $r )->get_status(), 'Oversized questions rejected before generation' );
	check( false === (bool) get_option( 'flxwk_ask_lock_' . flxwk_ask_identity() ), 'In-flight gate leaves no lock after request' );
} finally {
	foreach ( $ids as $id ) { wp_delete_post( $id, true ); }
	foreach ( $terms as $id ) { wp_delete_term( $id, FLXWK_TAX ); }
	flxk_save_state( 'wiki-regression', null );
	if ( false === $original_settings ) { delete_option( 'flxwk_settings' ); } else { update_option( 'flxwk_settings', $original_settings ); }
	flxwk_bump();
}
