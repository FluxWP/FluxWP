<?php
/** Disposable WP integration: FLX_TEST_SITE=1 php preset-meta.php /var/www/html */
if ( '1' !== getenv( 'FLX_TEST_SITE' ) || empty( $argv[1] ) ) { exit( "Disposable WordPress site required.\n" ); }
$_SERVER['HTTP_HOST'] = '127.0.0.1:8893';
require rtrim( $argv[1], '/' ) . '/wp-load.php';
function meta_check( $condition, $message ) { if ( ! $condition ) { throw new Exception( $message ); } echo 'PASS ' . $message . PHP_EOL; }
$item = array( 'key' => 'preset-meta-qa-' . wp_generate_uuid4(), 'title' => 'Preset metadata QA', 'currency' => 'TRY', 'options' => array( array( 'key' => 'share', 'label' => 'Share', 'price' => 100, 'active' => true, 'units_per' => 2, 'badge' => 'popular' ) ), 'meta' => array( 'in_form' => true, 'icon' => 'sheep', 'subtitle' => 'Sheep or goat', 'requires_proxy' => true, 'track_items' => true, 'min_by_currency' => array( 'EUR' => 10 ), 'i18n' => array( 'tr' => array( 'title' => 'Tür testi', 'subtitle' => 'Alt başlık' ) ), 'qa_extension' => 'retained' ) );
$clean = static function ( $out, $raw ) { if ( isset( $raw['qa_extension'] ) ) { $out['qa_extension'] = sanitize_text_field( $raw['qa_extension'] ); } return $out; };
add_filter( 'flxb_type_meta_clean', $clean, 99, 2 );
$id = 0;
try {
	$id = FLXB_Preset::type_save( $item, 0 );
	meta_check( ! is_wp_error( $id ) && $id > 0, 'A real donation-type fixture is saved' );
	$wanted = FLXB_Preset::type_data( $item );
	$read = FLXB_Preset::type_read( $id );
	meta_check( flxk_hash( $wanted ) === flxk_hash( $read ), 'Donation preset metadata round-trips without a false diff' );
	meta_check( isset( $read['meta']['icon'], $read['meta']['subtitle'], $read['meta']['in_form'], $read['meta']['qa_extension'] ), 'Core and extension-owned card fields survive comparison' );
	meta_check( ! isset( $read['meta']['wc_product_id'] ), 'Runtime WooCommerce product IDs stay outside preset comparison' );
	FLXB_Preset::type_save( $item, $id );
	meta_check( flxk_hash( $wanted ) === flxk_hash( FLXB_Preset::type_read( $id ) ), 'Repeated apply is idempotent and retains option prices/units' );
} finally {
	remove_filter( 'flxb_type_meta_clean', $clean, 99 );
	if ( $id && ! is_wp_error( $id ) ) { FLXB_Types::delete( $id ); }
}
