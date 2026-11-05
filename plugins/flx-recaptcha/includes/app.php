<?php
/**
 * Flux App: reCAPTCHA ayarları (ayar köprüsü). Anahtarlar write-only; gizli anahtar şifreli saklanır.
 *
 * @package flx-recaptcha
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param mixed $node
 * @return mixed
 */
function flxrc_app_texts( $node, $domain ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	foreach ( $node as $k => $v ) {
		if ( 'text' === $k && is_string( $v ) && false === strpos( $v, '{{' ) ) {
			$node[ $k ] = translate( $v, $domain ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
		} else {
			$node[ $k ] = flxrc_app_texts( $v, $domain );
		}
	}
	return $node;
}

add_filter(
	'flx_settings_schema',
	function ( $s ) {
		if ( ! isset( $s['flxrc_settings']['fields'] ) || ! is_array( $s['flxrc_settings']['fields'] ) ) {
			return $s;
		}
		$s['flxrc_settings']['fields']['site_key'] = array( 'type' => 'string', 'default' => '', 'label' => 'Site key (write-only)' );
		$s['flxrc_settings']['fields']['secret']   = array( 'type' => 'string', 'default' => '', 'label' => 'Secret key (write-only, stored sealed)' );
		return $s;
	},
	20
);

/** Ayar köprüsü düz metin yazarsa gizli anahtarı mühürler. Zaten mühürlü değer (s1:/p1:) olduğu gibi kalır. */
add_filter(
	'pre_update_option_flxrc_settings',
	function ( $value ) {
		if ( ! is_array( $value ) || ! isset( $value['secret'] ) || ! function_exists( 'flxrc_seal' ) ) {
			return $value;
		}
		$secret = (string) $value['secret'];
		if ( '' === $secret || 0 === strpos( $secret, 's1:' ) || 0 === strpos( $secret, 'p1:' ) ) {
			return $value;
		}
		$value['secret'] = flxrc_seal( $secret );
		return $value;
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! function_exists( 'flxapp_manifest_file' ) ) {
			return;
		}
		add_filter(
			'flxapp_manifest',
			function ( $m ) {
				$loaded          = flxapp_manifest_file(
					FLXRC_DIR . 'app/manifest.json',
					'flx-recaptcha',
					array(
						'version' => FLXRC_VERSION,
						'caps'    => array( 'view' => 'manage_options' ),
					)
				);
				$m['recaptcha'] = flxrc_app_texts( $loaded, 'flx-recaptcha' );
				return $m;
			},
			10,
			2
		);
	},
	20
);
