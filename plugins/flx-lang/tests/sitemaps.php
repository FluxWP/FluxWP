<?php
/** Run with wp eval-file in a disposable WordPress with Flux Lang, Flux SEO and Flux Wiki. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! function_exists( 'flxseo_opt' ) || ! post_type_exists( 'flx_wiki' ) ) {
	throw new RuntimeException( 'Requires a disposable WordPress with Flux Lang, Flux SEO and Flux Wiki.' );
}

$saved = array();
foreach ( array( 'flxl_settings', 'show_on_front', 'page_on_front', 'woocommerce_cart_page_id' ) as $option ) {
	$saved[ $option ] = get_option( $option );
}
$ids = array();
$shared = array();
$excluded = 0;
$mu = WPMU_PLUGIN_DIR . '/flxl-sitemap-qa.php';
if ( file_exists( $mu ) || get_option( '_flxl_sitemap_test_fixture' ) ) {
	throw new RuntimeException( 'A sitemap fixture already exists; clean it up before running.' );
}
$checks = 0;
$assert = function ( $ok, $message ) use ( &$checks ) {
	if ( ! $ok ) {
		throw new RuntimeException( $message );
	}
	++$checks;
};
$create = function ( $type, $name, $status = 'publish' ) use ( &$ids ) {
	$id = wp_insert_post( array( 'post_type' => $type, 'post_title' => 'Sitemap QA ' . $name, 'post_name' => 'sitemap-qa-' . $name, 'post_status' => $status ), true );
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( $id->get_error_message() );
	}
	$ids[] = $id;
	return $id;
};

try {
	$codes = array( 'en', 'tr', 'de', 'es', 'fr', 'pt-br', 'ja', 'zh', 'ar', 'ru' );
	$settings = flxl_settings();
	$settings['default'] = 'en';
	$settings['languages'] = array();
	foreach ( $codes as $code ) {
		$settings['languages'][ $code ] = flxl_catalog()[ $code ] + array( 'active' => 1 );
	}
	$settings['post_types'] = array( 'post', 'page', 'flx_wiki' );
	update_option( 'flxl_settings', $settings );
	flxl_ensure_terms();
	$home = $create( 'page', 'home' );
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $home );
	$good = array( $home, $create( 'page', 'one' ), $create( 'page', 'two' ), $create( 'page', 'three' ) );
	$hidden = $create( 'page', 'noindex' );
	update_post_meta( $hidden, '_flxseo_noindex', '1' );
	$locked = $create( 'page', 'password' );
	wp_update_post( array( 'ID' => $locked, 'post_password' => 'fixture-only' ) );
	$excluded = $create( 'page', 'excluded' );
	$draft = $create( 'page', 'draft', 'draft' );
	$private = $create( 'page', 'private', 'private' );
	$trash = $create( 'page', 'deleted', 'trash' );
	$physical_en = $create( 'page', 'physical-en' );
	$physical_ar = $create( 'page', 'physical-ar' );
	flxl_link_translations( array( 'en' => $physical_en, 'ar' => $physical_ar ) );
	$shared = array_merge( array_slice( $good, 1 ), array( $hidden, $locked, $excluded, $draft, $private, $trash, $physical_en ) );
	$translations = array();

	foreach ( array( 'post', 'flx_wiki' ) as $type ) {
		$map = array();
		foreach ( $codes as $code ) {
			$map[ $code ] = $create( $type, $type . '-' . $code );
		}
		flxl_link_translations( $map );
		$translations[ $type ] = $map;
	}

	update_option( '_flxl_sitemap_test_fixture', compact( 'ids', 'shared', 'excluded', 'good', 'codes', 'settings', 'translations' ), false );
	file_put_contents( $mu, '<?php add_filter( "flxl_shared_post_ids", function ( $ids ) { $fixture = get_option( "_flxl_sitemap_test_fixture", array() ); return array_merge( $ids, $fixture["shared"] ?? array() ); } );' );
	$result = WP_CLI::runcommand( 'eval-file ' . escapeshellarg( FLXL_DIR . 'tests/sitemaps-verify.php' ) . ' --allow-root', array( 'launch' => true, 'return' => 'all', 'exit_error' => false ) );
	$assert( 0 === $result->return_code, $result->stderr . $result->stdout );
	WP_CLI::log( trim( $result->stdout ) );
} finally {
	if ( file_exists( $mu ) ) { unlink( $mu ); }
	delete_option( '_flxl_sitemap_test_fixture' );
	foreach ( $ids as $id ) { wp_delete_post( $id, true ); }
	foreach ( $saved as $option => $value ) {
		false === $value ? delete_option( $option ) : update_option( $option, $value );
	}
	flxl_index_flush();
}
