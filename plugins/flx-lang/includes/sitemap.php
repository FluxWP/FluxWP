<?php
/** Shared pages have one record, but an indexable URL in every active language. */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_sitemaps_init', function ( $server ) {
	require_once __DIR__ . '/class-flxl-sitemaps.php';
	$server->registry->add_provider( 'flxlang', new FLXL_Sitemaps() );
} );
