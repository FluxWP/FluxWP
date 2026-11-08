<?php
/**
 * Ortak site profili (kurum kimliği): option flx_profile.
 *
 * Paketler kurum adı, imza, telefon, WhatsApp, e-posta, adres, IBAN gibi değerleri koda yazmak yerine
 * buradan okur: function_exists( 'flx_profile' ) ? flx_profile( 'name' ) : get_bloginfo( 'name' ).
 */

defined( 'ABSPATH' ) || exit;

/** Profil alanları ve açıklamaları (panelde ve şemada). */
function flxk_profile_fields() {
	return (array) apply_filters(
		'flx_profile_fields',
		array(
			'name'        => __( 'Short name', 'flx-setup' ),
			'legal_name'  => __( 'Legal name', 'flx-setup' ),
			'tagline'     => __( 'Tagline', 'flx-setup' ),
			'about'       => __( 'Short introduction', 'flx-setup' ),
			'signature'   => __( 'Message signature', 'flx-setup' ),
			'phone'       => __( 'Phone', 'flx-setup' ),
			'whatsapp'    => __( 'WhatsApp support line', 'flx-setup' ),
			'email'       => __( 'Email', 'flx-setup' ),
			'address'     => __( 'Address', 'flx-setup' ),
			'map_url'     => __( 'Map link', 'flx-setup' ),
			'iban'        => __( 'IBAN', 'flx-setup' ),
			'bank_name'   => __( 'Bank', 'flx-setup' ),
			'bank_holder' => __( 'Account holder', 'flx-setup' ),
			'tax_office'  => __( 'Tax office', 'flx-setup' ),
			'tax_no'      => __( 'Tax number', 'flx-setup' ),
			'founded'     => __( 'Year founded', 'flx-setup' ),
			'logo'        => __( 'Logo (media ID)', 'flx-setup' ),
			'logo_light'  => __( 'Light logo (media ID)', 'flx-setup' ),
			'icon'        => __( 'Site icon (media ID)', 'flx-setup' ),
			'colors'      => __( 'Colours', 'flx-setup' ),
			'social'      => __( 'Social media', 'flx-setup' ),
		)
	);
}

function flxk_profile_all() {
	$p = get_option( 'flx_profile', array() );
	return is_array( $p ) ? $p : array();
}

if ( ! function_exists( 'flx_profile' ) ) {
	/**
	 * Profil değeri. "social.instagram" gibi yol da olur. Boşsa makul varsayılan döner.
	 *
	 * @param string $key
	 * @param mixed  $default
	 */
	function flx_profile( $key = '', $default = null ) {
		$p = flxk_profile_all();
		if ( '' === $key ) {
			return $p;
		}
		$v = flxk_path_get( $p, $key );
		if ( flxk_is_empty( $v ) ) {
			if ( null !== $default ) {
				return $default;
			}
			switch ( $key ) {
				case 'name':
				case 'legal_name':
				case 'signature':
					return 'name' === $key ? get_bloginfo( 'name' ) : flx_profile( 'name' );
				case 'tagline':
					return get_bloginfo( 'description' );
				case 'email':
					return get_option( 'admin_email' );
			}
			return '';
		}
		return apply_filters( 'flx_profile_value', $v, $key );
	}
}

/** Sitenin alan adı (www'suz): {domain} yer tutucusu. */
function flxk_domain() {
	$h = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	return preg_replace( '/^www\./', '', $h );
}
