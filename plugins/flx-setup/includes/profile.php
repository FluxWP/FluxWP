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
			'name'        => __( 'Kısa ad', 'flx-setup' ),
			'legal_name'  => __( 'Resmî ad', 'flx-setup' ),
			'tagline'     => __( 'Slogan', 'flx-setup' ),
			'about'       => __( 'Kısa tanıtım', 'flx-setup' ),
			'signature'   => __( 'Mesaj imzası', 'flx-setup' ),
			'phone'       => __( 'Telefon', 'flx-setup' ),
			'whatsapp'    => __( 'WhatsApp destek hattı', 'flx-setup' ),
			'email'       => __( 'E-posta', 'flx-setup' ),
			'address'     => __( 'Adres', 'flx-setup' ),
			'map_url'     => __( 'Harita linki', 'flx-setup' ),
			'iban'        => __( 'IBAN', 'flx-setup' ),
			'bank_name'   => __( 'Banka', 'flx-setup' ),
			'bank_holder' => __( 'Hesap sahibi', 'flx-setup' ),
			'tax_office'  => __( 'Vergi dairesi', 'flx-setup' ),
			'tax_no'      => __( 'Vergi no', 'flx-setup' ),
			'founded'     => __( 'Kuruluş yılı', 'flx-setup' ),
			'logo'        => __( 'Logo (medya ID)', 'flx-setup' ),
			'logo_light'  => __( 'Açık logo (medya ID)', 'flx-setup' ),
			'icon'        => __( 'Site simgesi (medya ID)', 'flx-setup' ),
			'colors'      => __( 'Renkler', 'flx-setup' ),
			'social'      => __( 'Sosyal medya', 'flx-setup' ),
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
