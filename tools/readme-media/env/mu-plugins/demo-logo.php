<?php
/**
 * Test sitesi: kurum logosu yoksa ön yüzde FluxWP logosu (temanın demo ön ayarındaki dosyalar).
 */
add_filter(
	'flx_logo_files',
	static function ( $f ) {
		if ( ! empty( $f['light'] ) ) {
			return $f;
		}
		$u = get_template_directory_uri() . '/preset/assets/';
		return array( 'light' => $u . 'logo.svg', 'dark' => $u . 'logo-beyaz.svg', 'icon' => $u . 'ikon.svg', 'alt' => 'FluxWP' ) + (array) $f;
	}
);
