<?php
/**
 * Ön yüz sayacı: küçük bir script. Sayfa önbellekte olsa da çalışır; sayfaya özel bilgi yazı kimliği,
 * 404/arama durumu, ödeme sayfası işareti ve (giriş yapmış kişide) rolüdür.
 * Çerez yalnız ?r=kod ile gelen ziyaretçiye konur (flxan_r, 30 gün, birinci taraf): referansın sonraki
 * sayfalarda ve ödemede de tanınması için.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

/** Bu ziyaret sayılmayacak mı? (giriş yapmış ve dışlanan roldeki kişi, önizleme…) */
function flxan_is_excluded_request() {
	if ( ! flxan_settings( 'on' ) || is_admin() || is_feed() || is_robots() || is_trackback() || is_preview() || is_customize_preview() ) {
		return true;
	}
	if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['fl_builder'] ) || isset( $_GET['et_fb'] ) ) { // phpcs:ignore
		return true;
	}
	if ( is_user_logged_in() ) {
		$roles = (array) wp_get_current_user()->roles;
		if ( array_intersect( $roles, (array) flxan_settings( 'exclude_roles' ) ) ) {
			return true;
		}
	}
	return (bool) apply_filters( 'flxan_exclude_request', false );
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( flxan_is_excluded_request() ) {
			return;
		}
		$s   = flxan_settings();
		$cfg = array(
			'u'  => flxan_collect_url(),
			't'  => flxan_collect_token(),
			'i'  => is_singular() ? (int) get_queried_object_id() : 0,
			'dnt'=> (int) $s['dnt'],
			'eo' => (int) $s['ev_outbound'],
			'ed' => (int) $s['ev_download'],
			'ec' => (int) $s['ev_contact'],
			'ev' => 1,
			'rd' => (int) apply_filters( 'flxan_ref_cookie_days', 30 ),
			'cp' => COOKIEPATH ? COOKIEPATH : '/',
		);
		if ( is_user_logged_in() ) {
			$roles = array_values( (array) wp_get_current_user()->roles );
			if ( $roles ) {
				$cfg['g'] = substr( sanitize_key( $roles[0] ), 0, 20 );
			}
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$co = (string) wp_parse_url( wc_get_checkout_url(), PHP_URL_PATH );
			if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
				// Sipariş numarası ve anahtar yola girmesin: tek yol.
				$cfg['p'] = trailingslashit( $co ) . 'order-received/';
			} else {
				$cfg['co'] = 1;
			}
		}
		if ( is_404() ) {
			$cfg['x'] = 1;
		}
		if ( is_search() && $s['ev_search'] ) {
			$q = trim( (string) get_search_query( false ) );
			if ( '' !== $q ) {
				$cfg['s'] = function_exists( 'mb_strtolower' ) ? mb_substr( mb_strtolower( $q, 'UTF-8' ), 0, 100, 'UTF-8' ) : strtolower( substr( $q, 0, 100 ) );
			}
		}
		wp_enqueue_script( 'flxan-tracker', FLXAN_URL . 'assets/tracker.js', array(), FLXAN_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_add_inline_script( 'flxan-tracker', 'window.flxanCfg=' . wp_json_encode( $cfg ) . ';', 'before' );
	},
	20
);

/** LiteSpeed/önbellek eklentileri sayacı birleştirmesin ya da geciktirmesin. */
add_filter(
	'script_loader_tag',
	function ( $tag, $handle ) {
		if ( 'flxan-tracker' === $handle && false === strpos( $tag, 'data-no-optimize' ) ) {
			$tag = str_replace( '<script ', '<script data-no-optimize="1" data-no-minify="1" ', $tag );
		}
		return $tag;
	},
	10,
	2
);
add_filter(
	'litespeed_optimize_js_excludes',
	function ( $list ) {
		$list   = (array) $list;
		$list[] = 'flx-analytics/assets/tracker.js';
		$list[] = 'flxanCfg';
		return $list;
	}
);
