<?php
/**
 * Flux App: reCAPTCHA is a native screen (the same React screen as wp-admin, @fluxwp/modules
 * `flxrc-recaptcha`, data over GraphQL). The secret key is write-only and stored sealed; App versions
 * without the screen open the admin page instead.
 *
 * @package flx-recaptcha
 */

defined( 'ABSPATH' ) || exit;

/** Ayar köprüsü düz metin yazarsa gizli anahtarı mühürler. Zaten mühürlü değer (flx1:/s1:/p1:) olduğu gibi kalır. */
add_filter(
	'pre_update_option_flxrc_settings',
	function ( $value ) {
		if ( ! is_array( $value ) || ! isset( $value['secret'] ) || ! function_exists( 'flxrc_seal' ) ) {
			return $value;
		}
		$secret = (string) $value['secret'];
		if ( '' === $secret || 0 === strpos( $secret, 'flx1:' ) || 0 === strpos( $secret, 's1:' ) || 0 === strpos( $secret, 'p1:' ) ) {
			return $value;
		}
		$value['secret'] = flxrc_seal( $secret );
		return $value;
	}
);

add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m                = is_array( $m ) ? $m : array();
		$m['flxrc-recaptcha'] = array(
			'title'   => __( 'reCAPTCHA', 'flx-recaptcha' ),
			'cap'     => 'manage_options',
			'menu'    => 'flx-recaptcha',
			'url'     => admin_url( 'admin.php?page=flx-recaptcha' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'shield',
			'version' => FLXRC_VERSION,
			'order'   => 90,
		);
		return $m;
	}
);
