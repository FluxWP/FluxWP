<?php
/** Internal second process: the MU fixture registers shared IDs before WordPress caches them. */
$fixture = get_option( '_flxl_sitemap_test_fixture' );
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! is_array( $fixture ) ) {
	throw new RuntimeException( 'Run tests/sitemaps.php in a disposable WordPress.' );
}
$ids = $fixture['ids'];
$excluded = $fixture['excluded'];
$good = $fixture['good'];
$codes = $fixture['codes'];
$settings = $fixture['settings'];
$translations = $fixture['translations'];
$checks = 0;
$assert = function ( $ok, $message ) use ( &$checks ) {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
	++$checks;
};
add_filter( 'wp_sitemaps_posts_query_args', function ( $args ) use ( $ids, $excluded ) {
	$args['post__in'] = $ids;
	$args['post__not_in'][] = $excluded;
	return $args;
}, 100 );
add_filter( 'wp_sitemaps_max_urls', function () { return 2; } );

$native = new WP_Sitemaps_Posts();
$provider = wp_sitemaps_get_server()->registry->get_provider( 'posts' );
$assert( $provider instanceof FLXL_Posts_Sitemaps, 'Language subtypes registered in the core posts provider' );
$assert( ! wp_sitemaps_get_server()->registry->get_provider( 'flxlang' ), 'The old provider is absent from the sitemap index' );
$assert( ! wp_sitemaps_get_server()->registry->get_provider( 'translations' ), 'No separate translations provider is registered' );
$assert( ! isset( $provider->get_object_subtypes()['page-en'] ), 'Default URLs stay in core provider' );
foreach ( array_slice( $codes, 1 ) as $code ) {
	$subtype = 'page-' . $code;
	$assert( 2 === $provider->get_max_num_pages( $subtype ), $code . ' uses exact core pagination' );
	$entries = array_merge( $provider->get_url_list( 1, $subtype ), $provider->get_url_list( 2, $subtype ) );
	$actual = array_column( $entries, 'loc' );
	$expected = array_map( function ( $id ) use ( $code ) { return flxl_url_for_language( get_permalink( $id ), $code ); }, $good );
	$assert( $expected === $actual, $code . ' includes all shared pages and home, excludes unpublished/noindex/password/excluded/physical records' );
	$assert( ! $provider->get_url_list( 3, $subtype ), $code . ' has no duplicate overflow page' );
	$assert( ! empty( $entries[0]['lastmod'] ), $code . ' retains lastmod' );
	$assert( false !== strpos( $provider->get_sitemap_url( $subtype, 1 ), 'wp-sitemap-posts-' ), $code . ' uses the standard posts sitemap name' );
	$assert( $provider->get_sitemap_url( $subtype, 1 ) === flxl_legacy_sitemap_url( 'flxlang', $subtype, 1 ), $code . ' legacy link resolves to the new first page' );
	$assert( $provider->get_sitemap_url( $subtype, 2 ) === flxl_legacy_sitemap_url( 'flxlang', $subtype, 2 ), $code . ' legacy pagination is preserved' );
	$assert( '' === flxl_legacy_sitemap_url( 'flxlang', $subtype, 3 ), $code . ' out-of-range legacy pages remain missing' );
}
$assert( '' === flxl_legacy_sitemap_url( 'posts', 'page-ar', 1 ) && '' === flxl_legacy_sitemap_url( 'flxlang', 'page-invalid', 1 ) && '' === flxl_legacy_sitemap_url( 'flxlang', 'page-ar', 0 ), 'Other providers and invalid legacy requests are ignored' );
$assert( ! $provider->get_url_list( 1, 'page-invalid' ) && ! $provider->get_url_list( 0, 'page-ar' ), 'Invalid language/page rejected' );
foreach ( $translations as $type => $map ) {
	$urls = array();
	for ( $page = 1; $page <= $native->get_max_num_pages( $type ); ++$page ) {
		$urls = array_merge( $urls, array_column( $native->get_url_list( $page, $type ), 'loc' ) );
	}
	foreach ( $map as $code => $id ) {
		$assert( in_array( get_permalink( $id ), $urls, true ), $type . ': physical translation included in ' . $code );
	}
	$assert( 0 === $provider->get_max_num_pages( $type . '-ar' ), $type . ': no invented fallback translations' );
	$assert( $native->get_max_num_pages( $type ) === $provider->get_max_num_pages( $type ), $type . ': native page count is unchanged' );
	$assert( $native->get_url_list( 1, $type ) === $provider->get_url_list( 1, $type ), $type . ': native entries are unchanged' );
}
$assert( $native->get_url_list( 1, 'page' ) === $provider->get_url_list( 1, 'page' ), 'Default page sitemap entries are unchanged' );
$assert( $native->get_max_num_pages( 'page' ) === $provider->get_max_num_pages( 'page' ), 'Default page sitemap pagination is unchanged' );
class FLXL_Sitemap_Native_Test extends WP_Sitemaps_Posts {
	public function get_object_subtypes() {
		return array( 'page' => get_post_type_object( 'page' ), 'page-ar' => get_post_type_object( 'page' ) );
	}
	public function get_url_list( $page_num, $object_subtype = '' ) {
		return array( array( 'loc' => 'https://example.test/native/' . $object_subtype . '/' . $page_num ) );
	}
	public function get_max_num_pages( $object_subtype = '' ) { return 3; }
}
$custom_native = new FLXL_Sitemap_Native_Test();
$custom = new FLXL_Posts_Sitemaps( $custom_native );
$assert( $custom_native->get_url_list( 2, 'page' ) === $custom->get_url_list( 2, 'page' ) && 3 === $custom->get_max_num_pages( 'page' ), 'Custom native provider counts and entries are delegated unchanged' );
$assert( ! $custom->is_translation_subtype( 'page-ar' ) && $custom_native->get_url_list( 1, 'page-ar' ) === $custom->get_url_list( 1, 'page-ar' ), 'Real post type names take precedence over language name collisions' );
$assert( ! isset( $custom->get_object_subtypes()['flx_wiki-ar'] ), 'Language subtypes do not expose types removed by the native provider' );
$settings['languages']['nl'] = flxl_catalog()['nl'] + array( 'active' => 1 );
$settings['languages']['fr']['active'] = 0;
update_option( 'flxl_settings', $settings );
$assert( 2 === $provider->get_max_num_pages( 'page-nl' ), 'New active languages work without a code change' );
$assert( 0 === $provider->get_max_num_pages( 'page-fr' ), 'Disabled languages omitted' );
$assert( '' === flxl_legacy_sitemap_url( 'flxlang', 'page-fr', 1 ), 'Disabled languages have no legacy redirect' );
$hide_pages = function ( $types ) { unset( $types['page'] ); return $types; };
add_filter( 'wp_sitemaps_post_types', $hide_pages );
$assert( 0 === $provider->get_max_num_pages( 'page-ar' ), 'Native post type exclusions respected' );
$assert( '' === flxl_legacy_sitemap_url( 'flxlang', 'page-ar', 1 ), 'Excluded post types have no legacy redirect' );
remove_filter( 'wp_sitemaps_post_types', $hide_pages );
add_filter( 'wp_sitemaps_enabled', '__return_false' );
$assert( '' === flxl_legacy_sitemap_url( 'flxlang', 'page-ar', 1 ), 'Global sitemap switch disables legacy redirects' );
remove_filter( 'wp_sitemaps_enabled', '__return_false' );
$settings['default'] = 'ar';
update_option( 'flxl_settings', $settings );
$assert( 2 === $provider->get_max_num_pages( 'page-en' ), 'English is included when another language is the default' );
$assert( 0 === $provider->get_max_num_pages( 'page-ar' ), 'The configured default is never duplicated' );
foreach ( array( 'de', 'ja' ) as $request_language ) {
	$GLOBALS['flxl_state']['current'] = $request_language;
	$post = get_post( $good[1] );
	$entry = apply_filters( 'wp_sitemaps_posts_entry', array( 'loc' => get_permalink( $post ) ), $post, 'page' );
	$assert( flxl_url_for_language( get_permalink( $post ), 'ar' ) === $entry['loc'], 'Default shared URLs are stable on ' . $request_language . ' requests' );
}
WP_CLI::success( $checks . ' multilingual sitemap assertions passed (all ten languages, posts and wiki).' );
