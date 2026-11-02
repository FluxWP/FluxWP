<?php
/**
 * Kişisel referans linki.
 *
 * flx_referans yetkisi olan her kullanıcının rastgele bir kodu vardır (user meta flxr_ref_code).
 * Siteye ?ref=<kod> ile gelen ziyaretçiye çerez (flx_ref) yazılır; süre Ayarlar'dan (varsayılan 30 gün), son link geçerli.
 * WooCommerce siparişi oluşurken referans kişi siparişe _flx_ref_user meta'sı olarak yazılır.
 * Bağışı referansa bağlamak Flux Donate'ın işidir: flxr_order_ref_user( $order ) ya da flxr_current_ref_user().
 */

defined( 'ABSPATH' ) || exit;

const FLXR_REF_PARAM  = 'ref';
const FLXR_REF_COOKIE = 'flx_ref';
const FLXR_ORDER_META = '_flx_ref_user';

/** Kullanıcının referans kodu; yoksa üretilir. */
function flxr_ref_code( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return '';
	}
	$code = (string) get_user_meta( $user_id, 'flxr_ref_code', true );
	if ( '' === $code ) {
		do {
			$code = strtolower( wp_generate_password( 8, false, false ) );
		} while ( flxr_user_by_ref_code( $code, false ) );
		update_user_meta( $user_id, 'flxr_ref_code', $code );
	}
	return $code;
}

/** Koddan kullanıcı. $check: kullanıcının hâlâ referans olabilme yetkisi var mı. */
function flxr_user_by_ref_code( $code, $check = true ) {
	$code = strtolower( preg_replace( '/[^A-Za-z0-9]/', '', (string) $code ) );
	if ( '' === $code || strlen( $code ) > 32 ) {
		return 0;
	}
	$ids = get_users(
		array(
			'meta_key'   => 'flxr_ref_code', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => $code, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'     => 'ID',
			'number'     => 1,
		)
	);
	$id = $ids ? (int) $ids[0] : 0;
	if ( $id && $check && ! user_can( $id, FLXR_CAP_REF ) ) {
		return 0;
	}
	return $id;
}

/** Kişisel referans linki (varsayılan: ana sayfa; bağış sayfası gibi başka bir adres verilebilir). */
function flxr_ref_link( $user_id, $url = '' ) {
	$code = flxr_ref_code( $user_id );
	return $code ? add_query_arg( FLXR_REF_PARAM, $code, $url ? $url : home_url( '/' ) ) : '';
}

/** Referans olabilen kullanıcılar (flx_referans yetkisi). */
function flxr_ref_users() {
	return get_users(
		array(
			'capability' => FLXR_CAP_REF,
			'fields'     => array( 'ID', 'display_name' ),
			'orderby'    => 'display_name',
			'number'     => 500,
		)
	);
}

/**
 * Bu ziyaretçinin referans kişisi (0 = yok). Önce adresteki ?ref=, sonra çerez.
 * Filtre flx_current_ref_user ile başka kaynak (ör. Çağrı Merkezi linki) eklenebilir.
 */
function flxr_current_ref_user() {
	$code = '';
	if ( isset( $_GET[ FLXR_REF_PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$code = sanitize_text_field( wp_unslash( $_GET[ FLXR_REF_PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	} elseif ( isset( $_COOKIE[ FLXR_REF_COOKIE ] ) ) {
		$code = sanitize_text_field( wp_unslash( $_COOKIE[ FLXR_REF_COOKIE ] ) );
	}
	$id = $code ? flxr_user_by_ref_code( $code ) : 0;
	return (int) apply_filters( 'flx_current_ref_user', $id );
}

/** Siparişe yazılmış referans kişi (0 = yok). */
function flxr_order_ref_user( $order ) {
	$order = is_numeric( $order ) && function_exists( 'wc_get_order' ) ? wc_get_order( (int) $order ) : $order;
	return is_object( $order ) && method_exists( $order, 'get_meta' ) ? (int) $order->get_meta( FLXR_ORDER_META ) : 0;
}

/* ?ref= ile gelince çerez yaz; bu sayfa önbelleğe alınmasın (LiteSpeed). */
add_action(
	'template_redirect',
	function () {
		if ( ! isset( $_GET[ FLXR_REF_PARAM ] ) || is_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$code = sanitize_text_field( wp_unslash( $_GET[ FLXR_REF_PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! flxr_user_by_ref_code( $code ) ) {
			return;
		}
		$code = strtolower( preg_replace( '/[^A-Za-z0-9]/', '', $code ) );
		$days = max( 1, min( 365, (int) flxr_setting( 'ref_days' ) ) );
		if ( ! headers_sent() ) {
			setcookie( FLXR_REF_COOKIE, $code, time() + $days * DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		}
		$_COOKIE[ FLXR_REF_COOKIE ] = $code;
		do_action( 'litespeed_control_set_nocache', 'flx-roles referral' );
		nocache_headers();
	},
	1
);

/** Sipariş oluşurken (klasik ve blok checkout) referans kişiyi siparişe yaz. */
function flxr_order_set_ref( $order ) {
	if ( ! is_object( $order ) || $order->get_meta( FLXR_ORDER_META ) ) {
		return;
	}
	$ref = flxr_current_ref_user();
	if ( $ref ) {
		$order->update_meta_data( FLXR_ORDER_META, $ref );
	}
}
add_action( 'woocommerce_checkout_create_order', 'flxr_order_set_ref', 5 );
add_action(
	'woocommerce_store_api_checkout_update_order_meta',
	function ( $order ) {
		flxr_order_set_ref( $order );
	},
	5
);

/* Profil: kendi referans linki (ve yöneticiye başkasının linki). */
function flxr_profile_section( $user ) {
	if ( ! $user instanceof WP_User || ! user_can( $user, FLXR_CAP_REF ) ) {
		return;
	}
	$link = flxr_ref_link( $user->ID );
	echo '<h2>' . esc_html__( 'Referans linki', 'flx-roles' ) . '</h2>';
	echo '<table class="form-table" role="presentation"><tr><th scope="row">' . esc_html__( 'Kişisel link', 'flx-roles' ) . '</th><td>';
	echo '<input type="text" class="large-text code" readonly value="' . esc_attr( $link ) . '" onclick="this.select()"> ';
	echo '<p class="description">' . esc_html__( 'Bu linkle siteye gelenlerin bağışlarında referans olarak siz görünürsünüz.', 'flx-roles' ) . ' ' . esc_html(
		sprintf(
			/* translators: %s: referral parameter, e.g. ?ref=abc123 */
			__( 'Başka bir sayfayı paylaşacaksanız adresin sonuna %s ekleyin.', 'flx-roles' ),
			'?' . FLXR_REF_PARAM . '=' . flxr_ref_code( $user->ID )
		)
	) . '</p>';
	echo '</td></tr></table>';
}
add_action( 'show_user_profile', 'flxr_profile_section' );
add_action( 'edit_user_profile', 'flxr_profile_section' );
