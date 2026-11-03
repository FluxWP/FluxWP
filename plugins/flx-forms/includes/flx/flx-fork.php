<?php
/**
 * Flux Forms fork katmanı.
 *
 * Flux Forms 4.2.0 (GPLv2+, WhiteStudio) üzerine FluxWP için yapılan değişikliklerin
 * çekirdeğe en az dokunarak uygulandığı yer:
 * - Tüm özellikler açık (lisans/pro kontrolü yok).
 * - Üretici sunucularına (whitestudio.team, easyformbuilder.ir) hiçbir istek gitmez: telemetri,
 *   lisans doğrulama, eklenti indirme, inceleme/geri bildirim istekleri engellenir.
 * - Eklentiler (Stripe, koşullu mantık) eklentinin içinde gelir; indirme yapılmaz.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FLXF_FORK_VERSION', '2.1.7' );

/* Ülke/il/ilçe listeleri eklentinin içinden yüklenir (ziyaretçi tarayıcısı üçüncü taraf CDN'e gitmez). */
if ( ! defined( 'CDN_ZONE_AREA' ) ) {
	define( 'CDN_ZONE_AREA', EMSFB_PLUGIN_URL . 'vendor/offline/' );
}
if ( ! defined( 'EFB_Path_IR' ) ) {
	define( 'EFB_Path_IR', false );
}

/** Eklentiyle birlikte gelen (vendor/ içinde dosyası olan) eklentiler. */
function flxf_fork_bundled_addons() {
	return array( 'AdnSPF', 'AdnSMF', 'AdnOF' );
}

/* ---------- Pro: her zaman açık ---------- */
add_filter( 'pre_option_emsfb_pro', function () { return 1; } );
add_filter( 'pre_option_emsfb_pro_activeCode', function () { return 'flx-forms'; } );

/* ---------- Üretici sunucularına giden her isteği engelle ---------- */
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		foreach ( array( 'whitestudio.team', 'easyformbuilder.ir', 'easyformbuilder.com' ) as $blocked ) {
			if ( $host === $blocked || substr( $host, -strlen( '.' . $blocked ) ) === '.' . $blocked ) {
				return new WP_Error( 'flxf_blocked', 'Flux Forms: outbound request to the original vendor is disabled.' );
			}
		}
		return $pre;
	},
	1,
	3
);

/* ---------- Lisans / CDN yoklama cron'ları çalışmasın ---------- */
add_action(
	'init',
	function () {
		foreach ( array( 'emsfb_revalidate_license', 'emsfb_refresh_ir_cdn_status', 'emsfb_addon_recovery_event' ) as $hook ) {
			if ( wp_next_scheduled( $hook ) ) {
				wp_clear_scheduled_hook( $hook );
			}
		}
	},
	99
);

/* ---------- WordPress.org güncellemesi fork'u ezmesin ---------- */
/* Yalnız wordpress.org'dan gelen kayıt silinir; Flux Updater'nin kaydı kalır. */
add_filter(
	'site_transient_update_plugins',
	function ( $value ) {
		if ( ! is_object( $value ) || ! isset( $value->response ) || ! is_array( $value->response ) ) {
			return $value;
		}
		$file = plugin_basename( EMSFB_PLUGIN_FILE );
		if ( isset( $value->response[ $file ] ) ) {
			$item = (object) $value->response[ $file ];
			$src  = ( isset( $item->id ) ? $item->id : '' ) . ' ' . ( isset( $item->url ) ? $item->url : '' ) . ' ' . ( isset( $item->package ) ? $item->package : '' );
			if ( false !== strpos( $src, 'w.org/' ) || false !== strpos( $src, 'wordpress.org' ) ) {
				unset( $value->response[ $file ] );
			}
		}
		return $value;
	}
);

/* Kurulum sihirbazı (plan seçimi + üretici sunucusu üzerinden e-posta testi) gösterilmez. */
add_filter( 'pre_option_emsfb_onboarding_pending', function () { return 0; } );

/**
 * Client IP for throttles and the stored submission `ip` column (security audit L1).
 *
 * REMOTE_ADDR is the only value the client cannot forge. Forwarded headers
 * (CF-Connecting-IP, X-Forwarded-For, X-Real-IP) are honoured only when the
 * site declares it sits behind a trusted proxy:
 *     add_filter( 'flxf_trusted_proxy', '__return_true' );
 * (the filter also receives REMOTE_ADDR, so it can check a proxy allow-list).
 */
function flxf_client_ip() {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';
	$ip     = $remote;
	if ( $remote !== '' && apply_filters( 'flxf_trusted_proxy', false, $remote ) ) {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ) as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}
			$parts     = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
			$candidate = trim( $parts[0] );
			if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
				$ip = $candidate;
				break;
			}
		}
	}
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		$ip = '0.0.0.0';
	}
	return $ip;
}

/**
 * Public form skin that follows the site theme (colours, fonts, dark mode).
 * EFB enqueues its styles while the shortcode renders, so check at both points.
 */
function flxf_fork_enqueue_theme_style() {
	if ( is_admin() || ! apply_filters( 'flxf_theme_style', true ) ) {
		return;
	}
	if ( ! wp_style_is( 'Emsfb-style-css', 'enqueued' ) || wp_style_is( 'flxf-theme', 'enqueued' ) ) {
		return;
	}
	$deps = array( 'Emsfb-style-css' );
	foreach ( array( 'Emsfb-bootstrap-css', 'Emsfb-bootstrap-select-css', 'Emsfb-response-viewer-css' ) as $h ) {
		if ( wp_style_is( $h, 'registered' ) ) {
			$deps[] = $h;
		}
	}
	$file = __DIR__ . '/assets/flx-theme.css';
	wp_enqueue_style( 'flxf-theme', plugins_url( 'assets/flx-theme.css', __FILE__ ), $deps, (string) filemtime( $file ) );
}
add_action( 'wp_enqueue_scripts', 'flxf_fork_enqueue_theme_style', 999 );

/**
 * Dark mode of the form skin: 'class' (default: follow the theme's body.flx-dark), 'auto' (also
 * follow the visitor's prefers-color-scheme) or 'off' (always light).
 * Change with: add_filter( 'flxf_front_dark_mode', fn() => 'auto' );
 */
function flxf_front_dark_mode() {
	$m = (string) apply_filters( 'flxf_front_dark_mode', 'class' );
	return in_array( $m, array( 'class', 'auto', 'off' ), true ) ? $m : 'class';
}
add_filter(
	'body_class',
	function ( $classes ) {
		$m = flxf_front_dark_mode();
		if ( 'class' !== $m ) {
			$classes[] = 'flxf-dark-' . $m;
		}
		return $classes;
	}
);
add_action( 'wp_footer', 'flxf_fork_enqueue_theme_style', 5 );

/* Dashboard admin page (KPIs, charts, recent responses, CSV export). */
require_once __DIR__ . '/dashboard/dashboard.php';

/* Automations: flows after form submit / payment (certificate via Flux Certificates, e-mail, webhook). */
require_once __DIR__ . '/automations/automations.php';
/* 2.1.0: form e-mails as editable Flux Mail templates (falls back to Flux Forms's own sender). */
require_once __DIR__ . '/class-flxf-iletisim.php';
FLXF_Iletisim::init();
require_once __DIR__ . '/builder/builder.php';

/**
 * Translations ship with the plugin (languages/flx-forms-tr_TR.l10n.php/.mo). English is the source.
 */
function flxf_fork_load_textdomain() {
	load_plugin_textdomain( 'flx-forms', false, dirname( plugin_basename( EMSFB_PLUGIN_FILE ) ) . '/languages' );
}
add_action( 'init', 'flxf_fork_load_textdomain', 0 );

/* System check: shared `flx_system_checks` screen (Flux Donate hosts it; without Flux Donate: Forms › System check). */
require_once __DIR__ . '/system-checks/flx-system-checks.php';
require_once __DIR__ . '/system-checks/class-flxf-system-checks.php';
add_action( 'plugins_loaded', array( 'FLXF_System_Checks', 'init' ), 20 );

/* ---------- Flux Forms: payment quote (server-authoritative amount) — begin ---------- */
require_once __DIR__ . '/payment-quote.php';
/* ---------- Flux Forms: payment quote — end ---------- */

/* ---------- Flux Forms: provider-agnostic payments (contract, registry, generic REST, Payments page) — begin ---------- */
/* Loaded once every plugin is in: when Flux Donate is active it owns the payment layer
 * (same function, class and route names) and this built-in one stays off. */
function flxf_fork_load_payments() {
	if ( apply_filters( 'flxf_payments_delegate', false ) || function_exists( 'flxf_payment_providers' ) ) {
		return;
	}
	require_once __DIR__ . '/payments/payments.php';
}
add_action( 'plugins_loaded', 'flxf_fork_load_payments', 1 );
/* ---------- Flux Forms: provider-agnostic payments — end ---------- */
