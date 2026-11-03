<?php
/**
 * Test sitesi: Flux Certificates hazır şablonları için logosuz demo arka planlar (env/demo-certificates/gen.py üretir).
 */
add_filter( 'flxs_bundled_dirs', function ( $dirs ) {
	$up = wp_upload_dir( null, false );
	$dirs[ $up['basedir'] . '/demo-certs/' ] = $up['baseurl'] . '/demo-certs/';
	return $dirs;
} );
