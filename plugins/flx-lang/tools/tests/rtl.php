<?php
/** Disposable WordPress integration: FLXL_TEST_SITE=1 php rtl.php /var/www/html */
if ( '1' !== getenv( 'FLXL_TEST_SITE' ) || empty( $argv[1] ) ) {
	exit( "Disposable site and WordPress root required.\n" );
}
$_SERVER['HTTP_HOST'] = '127.0.0.1:8877';
require rtrim( $argv[1], '/' ) . '/wp-load.php';
foreach ( array( 'ar' => 'rtl', 'en' => 'ltr', 'tr' => 'ltr' ) as $lang => $direction ) {
	if ( ! flxl_switch_language( $lang ) ) { throw new Exception( 'Enable ar/en/tr in this disposable site.' ); }
	try {
		if ( flxl_language_direction() !== $direction || is_rtl() !== ( 'rtl' === $direction ) ) {
			throw new Exception( 'Core direction mismatch: ' . $lang );
		}
		$attributes = apply_filters( 'language_attributes', 'lang="' . $lang . '" dir="ltr"' );
		if ( 1 !== substr_count( $attributes, 'dir=' ) || false === strpos( $attributes, 'dir="' . $direction . '"' ) ) {
			throw new Exception( 'Duplicate or incorrect HTML direction: ' . $lang );
		}
		$classes = apply_filters( 'body_class', array( 'rtl', 'test-page' ) );
		if ( in_array( 'rtl', $classes, true ) !== ( 'rtl' === $direction ) || ! in_array( 'test-page', $classes, true ) ) {
			throw new Exception( 'Body direction mismatch: ' . $lang );
		}
	} finally {
		flxl_restore_language();
	}
	echo 'PASS: ' . $lang . " core, HTML and body direction\n";
}
if ( is_rtl() ) { throw new Exception( 'Expected LTR default language after restore.' ); }
echo "PASS: language restoration\n";
$settings = get_option( 'flxl_settings' );
$wp_language = get_option( 'WPLANG' );
$state = $GLOBALS['flxl_state'];
try {
	$arabic = $settings; $arabic['default'] = 'ar';
	update_option( 'WPLANG', 'en_US' );
	update_option( 'flxl_settings', $arabic );
	$GLOBALS['flxl_state']['current'] = 'ar';
	flxl_apply_text_direction();
	if ( ! is_rtl() || false === strpos( get_language_attributes(), 'lang="ar"' ) ) {
		throw new Exception( 'Default Arabic must not inherit English WordPress direction/language.' );
	}
	echo "PASS: default Arabic with English WordPress install\n";
} finally {
	update_option( 'flxl_settings', $settings );
	update_option( 'WPLANG', $wp_language );
	$GLOBALS['flxl_state'] = $state;
	flxl_apply_text_direction();
}
