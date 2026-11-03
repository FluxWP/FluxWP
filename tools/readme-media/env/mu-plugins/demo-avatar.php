<?php
/**
 * Test sitesi: Gravatar yerine yerel harf avatari (cloud'da gravatar kapali, cekimlerde kirik resim gorunmesin).
 */
add_filter( 'pre_get_avatar_data', function ( $args, $id_or_email ) {
	$name = '';
	if ( is_numeric( $id_or_email ) ) {
		$u = get_userdata( (int) $id_or_email );
	} elseif ( $id_or_email instanceof WP_User ) {
		$u = $id_or_email;
	} elseif ( $id_or_email instanceof WP_Comment ) {
		$name = $id_or_email->comment_author;
		$u    = null;
	} elseif ( is_string( $id_or_email ) ) {
		$u = get_user_by( 'email', $id_or_email );
		if ( ! $u ) {
			$name = $id_or_email;
		}
	} else {
		$u = null;
	}
	if ( ! empty( $u ) ) {
		$name = $u->display_name;
	}
	$l       = mb_strtoupper( mb_substr( trim( $name ) ?: 'A', 0, 1 ) );
	$colors  = array( '#4f46e5', '#0f766e', '#b45309', '#be185d', '#1d4ed8', '#15803d' );
	$c       = $colors[ abs( crc32( $name ) ) % count( $colors ) ];
	$svg     = '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96"><rect width="96" height="96" fill="' . $c . '"/><text x="48" y="62" font-family="Arial,sans-serif" font-size="44" fill="#fff" text-anchor="middle">' . esc_html( $l ) . '</text></svg>';
	// data: adresi esc_url ile silinir; dosyaya yazilip uploads adresi verilir
	$up = wp_upload_dir( null, false );
	$fn = 'demo-avatar/' . md5( $svg ) . '.svg';
	if ( ! file_exists( $up['basedir'] . '/' . $fn ) ) {
		wp_mkdir_p( $up['basedir'] . '/demo-avatar' );
		file_put_contents( $up['basedir'] . '/' . $fn, $svg );
	}
	$args['url']          = $up['baseurl'] . '/' . $fn;
	$args['found_avatar'] = true;
	return $args;
}, 10, 2 );
