<?php
/** Run with wp eval-file in a disposable WordPress with published pages and categories. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! function_exists( 'flxseo_sitemap_alias' ) ) {
	throw new RuntimeException( 'Requires WordPress and Flux SEO.' );
}
$checks = 0;
$assert = function ( $ok, $message ) use ( &$checks ) {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
	++$checks;
};
$server = wp_sitemaps_get_server();
foreach ( array( 'sitemap.xml', 'sitemap_index.xml' ) as $path ) {
	$assert( home_url( '/wp-sitemap.xml' ) === flxseo_sitemap_alias( $path ), 'Index alias: ' . $path );
}
foreach ( array( 'page' => 'posts', 'category' => 'taxonomies' ) as $type => $name ) {
	$provider = $server->registry->get_provider( $name );
	$assert( $provider->get_max_num_pages( $type ) > 0, 'Fixture needs published ' . $type );
	$assert( $provider->get_sitemap_url( $type, 1 ) === flxseo_sitemap_alias( $type . '-sitemap.xml' ), 'Child alias: ' . $type );
	$page = $provider->get_max_num_pages( $type ) + 1;
	$assert( '' === flxseo_sitemap_alias( $type . '-sitemap' . $page . '.xml' ), 'Out of range stays 404: ' . $type );
}
foreach ( array( 'does-not-exist-sitemap.xml', 'page-sitemap0.xml', 'page-sitemap-1.xml', 'folder/page-sitemap.xml', 'wp-sitemap.xml' ) as $path ) {
	$assert( '' === flxseo_sitemap_alias( $path ), 'Invalid route ignored: ' . $path );
}
$limit = function () { return 1; };
add_filter( 'wp_sitemaps_max_urls', $limit );
$posts = $server->registry->get_provider( 'posts' );
if ( $posts->get_max_num_pages( 'page' ) > 1 ) {
	$assert( $posts->get_sitemap_url( 'page', 2 ) === flxseo_sitemap_alias( 'page-sitemap2.xml' ), 'Paginated alias follows core pagination' );
}
remove_filter( 'wp_sitemaps_max_urls', $limit );
$hide = function ( $types ) { unset( $types['category'] ); return $types; };
add_filter( 'wp_sitemaps_taxonomies', $hide );
$assert( '' === flxseo_sitemap_alias( 'category-sitemap.xml' ), 'Excluded taxonomy stays excluded' );
remove_filter( 'wp_sitemaps_taxonomies', $hide );
$subdirectory = function ( $url, $path ) { return 'https://example.test/subsite/' . ltrim( $path, '/' ); };
add_filter( 'home_url', $subdirectory, 99, 2 );
$assert( 'https://example.test/subsite/wp-sitemap-taxonomies-category-1.xml' === flxseo_sitemap_alias( '/subsite/category-sitemap.xml' ), 'Subdirectory installations resolve relative to the site root' );
remove_filter( 'home_url', $subdirectory, 99 );
add_filter( 'wp_sitemaps_enabled', '__return_false' );
$assert( '' === flxseo_sitemap_alias( 'sitemap.xml' ) && '' === flxseo_sitemap_alias( 'page-sitemap.xml' ), 'Global sitemap switch respected' );
remove_filter( 'wp_sitemaps_enabled', '__return_false' );
WP_CLI::success( $checks . ' sitemap alias assertions passed.' );
