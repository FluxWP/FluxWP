<?php
/** Shared pages have one record, but an indexable URL in every active language. */
defined( 'ABSPATH' ) || exit;

add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	if ( 'posts' !== $name || ! $provider instanceof WP_Sitemaps_Provider || $provider instanceof FLXL_Posts_Sitemaps ) {
		return $provider;
	}
	require_once __DIR__ . '/class-flxl-sitemaps.php';
	require_once __DIR__ . '/class-flxl-posts-sitemaps.php';
	return new FLXL_Posts_Sitemaps( $provider );
}, 20, 2 );

/** Keep previously submitted language sitemaps usable without listing the old name. */
function flxl_legacy_sitemap_url( $name, $subtype, $page ) {
	if ( 'flxlang' !== $name || $page < 1 ) {
		return '';
	}
	$server = wp_sitemaps_get_server();
	if ( ! $server->sitemaps_enabled() ) {
		return '';
	}
	$provider = $server->registry->get_provider( 'posts' );
	if ( ! $provider instanceof FLXL_Posts_Sitemaps || ! $provider->is_translation_subtype( $subtype ) || $page > $provider->get_max_num_pages( $subtype ) ) {
		return '';
	}
	return $provider->get_sitemap_url( $subtype, $page );
}

// Core renders sitemaps at priority 10, including 404s for unregistered providers.
add_action( 'template_redirect', function () {
	$target = flxl_legacy_sitemap_url( get_query_var( 'sitemap' ), get_query_var( 'sitemap-subtype' ), (int) get_query_var( 'paged' ) );
	if ( $target ) {
		wp_safe_redirect( $target, 301 );
		exit;
	}
}, 0 );
