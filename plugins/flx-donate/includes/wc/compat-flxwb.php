<?php
/**
 * Flux Donate 2.0: three function names of the retired Flux WooCommerce Bağış plugin, kept only because
 * the FluxWP theme (inc/hesap.php, flx_wcb_kart_acik()) checks them to decide whether the
 * donation page shows the card-donation blocks. New code should call flxb_wc_form_available().
 * Remove this file once the theme uses the new function (see diger-sahipler-icin.md).
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'flxwb_render_form' ) ) {
	/** @deprecated 2.0.0 Use the block flx-wc-bagis/secenekler or [flx_bagis_secenekleri]. */
	function flxwb_render_form( $args = array() ) {
		return FLXB_WC_Form::render( (array) $args );
	}
}

if ( ! function_exists( 'flxwb_packages' ) ) {
	/** @deprecated 2.0.0 Non-empty when the "Donation options" block has something to show. */
	function flxwb_packages( $only_active = true ) {
		return flxb_wc_form_available() ? array( 'flxb' => array( 'active' => 1 ) ) : array();
	}
}

if ( ! function_exists( 'flxwb_setting' ) ) {
	/** @deprecated 2.0.0 Only the keys the theme reads (custom_count, general_enabled). */
	function flxwb_setting( $key ) {
		return in_array( $key, array( 'custom_count', 'general_enabled' ), true ) ? ( flxb_wc_form_available() ? 1 : 0 ) : FLXB_WC::setting( $key );
	}
}
