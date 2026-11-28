<?php
/**
 * List registered Flux screens and their menu routes on a disposable WordPress site.
 *
 * Usage: php inventory.php /absolute/wordpress/root
 */
define( 'WP_ADMIN', true );
$site = $argv[1] ?? '';
if ( ! is_file( $site . '/wp-load.php' ) ) {
	fwrite( STDERR, "Pass the WordPress fixture root directory.\n" );
	exit( 1 );
}
require $site . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
wp_set_current_user( 1 );
set_current_screen( 'dashboard' );
do_action( 'admin_menu', '' );

if ( ! function_exists( 'flx_admin_screens' ) ) {
	fwrite( STDERR, "Activate Flux GraphQL and the plugins being audited.\n" );
	exit( 1 );
}

$screens = array();
foreach ( flx_admin_screens() as $page => $args ) {
	$screens[] = array(
		'page'        => $page,
		'module'      => $args['module'],
		'layout'      => $args['layout'],
		'start'       => $args['start'],
		'start_param' => $args['start_param'],
		'url'         => menu_page_url( $page, false ),
	);
}

$pages = array();
$urls = array();
foreach ( array_merge( array_values( $submenu ), array( $menu ) ) as $rows ) {
	foreach ( $rows as $row ) {
		$slug = $row[2] ?? '';
		if ( preg_match( '/^(flx[-_a-z]|Emsfb|emsfb)/', $slug ) && ! str_contains( $slug, '://' ) && ! str_contains( $slug, '.php' ) ) {
			$pages[ $slug ] = wp_strip_all_tags( $row[0] );
			$urls[ $slug ] = menu_page_url( $slug, false );
		}
	}
}

echo wp_json_encode(
	array(
		'screens' => $screens,
		'pages'   => $pages,
		'urls'    => $urls,
		'plugins' => get_option( 'active_plugins' ),
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
), PHP_EOL;
