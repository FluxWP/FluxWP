<?php
/**
 * Flux Donate: small shared helpers.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Custom capability that lets a role see the donation pages (granted to administrators). */
const FLXB_CAP_VIEW = 'flx_bagis_view';

/** Full table name: donors | donations | subscriptions | types | items. */
function flxb_table( $name ) {
	global $wpdb;
	if ( 'donors' === $name ) {
		// 2.22.0: donors are rows of the shared FluxWP people table (flx-brand people.php); donor id = person id.
		if ( function_exists( 'flx_people_ready' ) && did_action( 'after_setup_theme' ) ) {
			flx_people_ready();
		}
		return $wpdb->prefix . 'flx_people';
	}
	return $wpdb->prefix . 'flxb_' . $name;
}

function flxb_can_view() {
	return is_user_logged_in() && ( current_user_can( 'manage_options' ) || current_user_can( FLXB_CAP_VIEW ) );
}

function flxb_can_manage() {
	return is_user_logged_in() && current_user_can( (string) apply_filters( 'flxb_manage_capability', 'manage_options' ) );
}

/* ------------------------------------------------------------------
 * Settings
 * ------------------------------------------------------------------ */

function flxb_settings() {
	$s = get_option( 'flxb_settings', array() );
	return wp_parse_args(
		is_array( $s ) ? $s : array(),
		array(
			'currency'      => 'TRY',
			'donation_forms' => 'payments', // payments = every paid form counts as a donation | list = only the listed forms
			'form_ids'      => array(),
			'org_name'      => '',
			'logo_id'       => 0,
			'primary_color' => '',
			'receipt_text'  => '',
			'footer_text'   => '',
			'module_kurban' => 0, // optional named-gift module (consent text, intentions, one item per unit); off by default
			'module_track'  => 0, // 2.11: item tracking, partners and agreements; off by default
			'module_reports' => 0, // 2.11: JSON reports screen (on with tracking too)
		)
	);
}

function flxb_setting( $key, $default = null ) {
	$s = flxb_settings();
	return array_key_exists( $key, $s ) ? $s[ $key ] : $default;
}

function flxb_default_currency() {
	$c = strtoupper( (string) flxb_setting( 'currency', 'TRY' ) );
	return preg_match( '/^[A-Z]{3}$/', $c ) ? $c : 'TRY';
}

/** Is the optional kurban module switched on (Settings › General)? */
function flxb_kurban_enabled() {
	return (bool) apply_filters( 'flxb_kurban_enabled', ! empty( flxb_setting( 'module_kurban', 0 ) ) );
}

/** 2.11: is item tracking (codes, partners, files, history) switched on? The named-gift module implies it. */
function flxb_track_enabled() {
	return (bool) apply_filters( 'flxb_track_enabled', ! empty( flxb_setting( 'module_track', 0 ) ) || flxb_kurban_enabled() );
}

/**
 * A date from a form, a file or a chat → 'Y-m-d' ('' when it cannot be read). Takes 2026-05-27,
 * 27.05.2026, 27/05/2026, 27-05-26 and Excel day numbers (46169).
 */
function flxb_parse_date( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v ) {
		return '';
	}
	if ( preg_match( '/^\d{5}(\.\d+)?$/', $v ) ) {
		return gmdate( 'Y-m-d', (int) round( ( (float) $v - 25569 ) * 86400 ) );
	}
	if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})/', $v, $m ) ) {
		list( , $y, $mo, $d ) = $m;
	} elseif ( preg_match( '/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})/', $v, $m ) ) {
		list( , $d, $mo, $y ) = $m;
		$y = strlen( $y ) === 2 ? '20' . $y : $y;
	} else {
		return '';
	}
	return checkdate( (int) $mo, (int) $d, (int) $y ) ? sprintf( '%04d-%02d-%02d', $y, $mo, $d ) : '';
}

/**
 * Units an option can be counted in: key => array( singular, plural ). Extend with the
 * `flxb_units` filter.
 */
function flxb_units() {
	return (array) apply_filters(
		'flxb_units',
		array(
			'adet'  => array( __( 'piece', 'flx-donate' ), __( 'pieces', 'flx-donate' ) ),
			'hisse' => array( __( 'share', 'flx-donate' ), __( 'shares', 'flx-donate' ) ),
			'kisi'  => array( __( 'person', 'flx-donate' ), __( 'people', 'flx-donate' ) ),
			'fidan' => array( __( 'sapling', 'flx-donate' ), __( 'saplings', 'flx-donate' ) ),
			'paket' => array( __( 'package', 'flx-donate' ), __( 'packages', 'flx-donate' ) ),
		)
	);
}

/** "2 shares" for a unit key; a key outside flxb_units() is its own label ("koli"); '' (no unit set) gives ''. */
function flxb_unit_label( $unit, $count = 1 ) {
	$unit = (string) $unit;
	if ( '' === $unit ) {
		return '';
	}
	$u = flxb_units();
	$l = $u[ $unit ] ?? array( $unit, $unit );
	$l = is_array( $l ) ? $l : array( (string) $l, (string) $l );
	return 1 === (int) $count ? (string) $l[0] : (string) ( $l[1] ?? $l[0] );
}

/* ------------------------------------------------------------------
 * Contact details
 * ------------------------------------------------------------------ */

/**
 * Phone number in E.164 ("+905321234567"). Turkish numbers written the local way
 * (0532 123 45 67, 532 123 45 67, 90 532…) become +90…; '' when it is not a phone number.
 */
function flxb_normalize_phone( $raw ) {
	return flx_phone_normalize( $raw, 'tr' );
}

/** Phone as the shared people table stores it: E.164 digits without "+" (905321234567). */
function flxb_person_phone( $raw ) {
	return ltrim( flxb_normalize_phone( $raw ), '+' );
}

/** +905321234567 (or 905321234567 from the shared people table) → +90 532 123 45 67 */
function flxb_format_phone( $e164 ) {
	$e164 = (string) $e164;
	if ( preg_match( '/^\d{8,15}$/', $e164 ) ) {
		$e164 = '+' . $e164;
	}
	if ( preg_match( '/^\+90(\d{3})(\d{3})(\d{2})(\d{2})$/', $e164, $m ) ) {
		return '+90 ' . $m[1] . ' ' . $m[2] . ' ' . $m[3] . ' ' . $m[4];
	}
	return $e164;
}

function flxb_normalize_email( $raw ) {
	$e = strtolower( trim( (string) $raw ) );
	$e = sanitize_email( $e );
	return is_email( $e ) ? $e : '';
}

/* ------------------------------------------------------------------
 * Money, dates
 * ------------------------------------------------------------------ */

function flxb_currency_symbol( $currency ) {
	$map = array( 'TRY' => '₺', 'USD' => '$', 'EUR' => '€', 'GBP' => '£' );
	$c   = strtoupper( (string) $currency );
	return $map[ $c ] ?? $c;
}

/** 1.250,00 ₺ (number_format_i18n follows the site language). */
function flxb_money( $amount, $currency = '', $decimals = null ) {
	$currency = '' === (string) $currency ? flxb_default_currency() : strtoupper( (string) $currency );
	$amount   = (float) $amount;
	if ( null === $decimals ) {
		$decimals = abs( $amount - round( $amount ) ) < 0.005 ? 0 : 2;
	}
	$num = number_format_i18n( $amount, $decimals );
	$sym = flxb_currency_symbol( $currency );
	return 'TRY' === $currency ? $num . ' ' . $sym : ( 3 === strlen( $sym ) ? $num . ' ' . $sym : $sym . $num );
}

/** UTC MySQL datetime → site-local formatted string. */
function flxb_date( $utc, $format = '' ) {
	if ( empty( $utc ) || '0000-00-00 00:00:00' === $utc ) {
		return '';
	}
	$ts = strtotime( $utc . ' UTC' );
	if ( ! $ts ) {
		return '';
	}
	if ( '' === $format ) {
		// Turkish: 16.10.2026 14:24; English: Oct 16, 2026 14:24.
		$format = 'tr' === flx_lang() ? 'd.m.Y H:i' : 'datetime';
	}
	// Shared FluxWP helper: month/day names in the user's language (date | short | datetime | time | PHP format).
	return flx_date( $ts, $format );
}

function flxb_now() {
	return current_time( 'mysql', true );
}

/** Site-local Y-m-d (start/end of day) → UTC MySQL datetime. */
function flxb_local_day_to_utc( $ymd, $end = false ) {
	try {
		$d = new DateTimeImmutable( $ymd . ( $end ? ' 23:59:59' : ' 00:00:00' ), wp_timezone() );
		return $d->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	} catch ( \Exception $e ) {
		return $ymd . ( $end ? ' 23:59:59' : ' 00:00:00' );
	}
}

/* ------------------------------------------------------------------
 * Labels
 * ------------------------------------------------------------------ */

function flxb_status_label( $status ) {
	$map = array(
		'completed'          => array( 'ok', __( 'Completed', 'flx-donate' ) ),
		'pending'            => array( 'pending', __( 'Pending', 'flx-donate' ) ),
		'failed'             => array( 'failed', __( 'Failed', 'flx-donate' ) ),
		'canceled'           => array( 'failed', __( 'Canceled', 'flx-donate' ) ),
		'refunded'           => array( 'muted', __( 'Refunded', 'flx-donate' ) ),
		'partially_refunded' => array( 'muted', __( 'Partly refunded', 'flx-donate' ) ),
		'active'             => array( 'ok', __( 'Active', 'flx-donate' ) ),
		'past_due'           => array( 'pending', __( 'Past due', 'flx-donate' ) ),
		'trash'              => array( 'muted', __( 'In the trash', 'flx-donate' ) ),
	);
	$s = (string) $status;
	return $map[ $s ] ?? array( 'muted', $s );
}

/**
 * 2.20.0: status of one gift as people should read it: a pending gift says what it waits for
 * ("Receipt awaiting approval" / "Awaiting payment"). For other plugins too (Flux Chat).
 *
 * @return array { tone, label, stage ('receipt' | 'payment' | '') }
 */
function flxb_donation_state( array $d ) {
	if ( ! class_exists( 'FLXB_Approval' ) ) {
		require_once FLXB_DIR . 'includes/admin/class-flxb-approval.php';
	}
	$stage = FLXB_Approval::stage( $d );
	if ( 'receipt' === $stage ) {
		return array( 'receipt', __( 'Receipt awaiting approval', 'flx-donate' ), 'receipt' );
	}
	if ( 'payment' === $stage ) {
		return array( 'pending', __( 'Awaiting payment', 'flx-donate' ), 'payment' );
	}
	$l = flxb_status_label( $d['status'] ?? '' );
	return array( $l[0], $l[1], '' );
}

function flxb_type_label( $type ) {
	return 'recurring' === $type ? __( 'Recurring', 'flx-donate' ) : __( 'One-time', 'flx-donate' );
}

function flxb_interval_label( $interval ) {
	$map = array(
		'day'   => __( 'Daily', 'flx-donate' ),
		'week'  => __( 'Weekly', 'flx-donate' ),
		'month' => __( 'Monthly', 'flx-donate' ),
		'year'  => __( 'Yearly', 'flx-donate' ),
	);
	return $map[ (string) $interval ] ?? (string) $interval;
}

function flxb_provider_label( $id ) {
	if ( function_exists( 'flxf_payment_provider' ) ) {
		$p = flxf_payment_provider( $id );
		if ( $p ) {
			return $p->label();
		}
	}
	$map = array( 'woocommerce' => 'WooCommerce', 'stripe' => 'Stripe', 'iyzico' => 'iyzico', 'paypal' => 'PayPal', 'manual' => __( 'Manual', 'flx-donate' ), 'flxf_test' => __( 'Test provider', 'flx-donate' ), 'bank_transfer' => __( 'Bank transfer', 'flx-donate' ), 'cash' => __( 'Cash', 'flx-donate' ), 'other' => __( 'Other', 'flx-donate' ) );
	return $map[ (string) $id ] ?? (string) $id;
}

/** Title of a Flux Forms form (cached). */
function flxb_form_title( $form_id ) {
	static $cache = null;
	global $wpdb;
	$form_id = (int) $form_id;
	if ( ! $form_id ) {
		return '';
	}
	if ( null === $cache ) {
		$cache = array();
		$t     = $wpdb->prefix . 'emsfb_form';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) ) === $t ) {
			foreach ( (array) $wpdb->get_results( "SELECT form_id, form_name FROM `{$t}`", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$cache[ (int) $r['form_id'] ] = wp_strip_all_tags( (string) $r['form_name'] );
			}
		}
	}
	return isset( $cache[ $form_id ] ) && '' !== $cache[ $form_id ] ? $cache[ $form_id ] : '#' . $form_id;
}

/** Every Flux Forms form: id => title. */
function flxb_forms() {
	global $wpdb;
	$t = $wpdb->prefix . 'emsfb_form';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) ) !== $t ) {
		return array();
	}
	$out = array();
	foreach ( (array) $wpdb->get_results( "SELECT form_id, form_name, form_type FROM `{$t}` ORDER BY form_id ASC", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out[ (int) $r['form_id'] ] = array(
			'title' => '' !== trim( wp_strip_all_tags( (string) $r['form_name'] ) ) ? wp_strip_all_tags( (string) $r['form_name'] ) : '#' . (int) $r['form_id'],
			'type'  => (string) $r['form_type'],
		);
	}
	return $out;
}

/** Flux Certificates is active (public API). */
function flxb_certificates_available() {
	return function_exists( 'flxs_get_certificate_by_code' ) && function_exists( 'flxs_certificate_url' );
}

/* ------------------------------------------------------------------
 * Categories and brand (everything organisation-specific comes from settings)
 * ------------------------------------------------------------------ */

/** Donation type categories: slug => label (editable in Settings). */
function flxb_categories() {
	$c = get_option( 'flxb_categories', null );
	if ( ! is_array( $c ) ) {
		$c = array( 'general' => __( 'General', 'flx-donate' ) );
	}
	return $c;
}

/** Colour from settings, else the theme's theme.json palette, else a neutral default. */
function flxb_theme_color( $slug ) {
	if ( function_exists( 'wp_get_global_settings' ) ) {
		foreach ( (array) wp_get_global_settings( array( 'color', 'palette', 'theme' ) ) as $c ) {
			if ( isset( $c['slug'], $c['color'] ) && $slug === $c['slug'] && preg_match( '/^#[0-9a-fA-F]{3,8}$/', $c['color'] ) ) {
				return $c['color'];
			}
		}
	}
	return '';
}

/**
 * Organisation brand: name, logo (URL), primary / dark colour, receipt and footer texts.
 *
 * @return array
 */
function flxb_brand() {
	$s       = flxb_settings();
	$primary = preg_match( '/^#[0-9a-fA-F]{6}$/', (string) ( $s['primary_color'] ?? '' ) ) ? $s['primary_color'] : flxb_theme_color( 'primary' );
	$primary = '' !== $primary ? $primary : '#1F2937';
	$dark    = flxb_theme_color( 'primary-dark' );
	if ( '' === $dark || ! empty( $s['primary_color'] ) ) {
		$dark = flxb_shade( $primary, -0.25 );
	}
	$logo = ! empty( $s['logo_id'] ) ? wp_get_attachment_image_url( (int) $s['logo_id'], 'medium' ) : '';
	if ( ! $logo && function_exists( 'get_custom_logo' ) && get_theme_mod( 'custom_logo' ) ) {
		$logo = wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium' );
	}
	return array(
		'name'         => '' !== trim( (string) ( $s['org_name'] ?? '' ) ) ? (string) $s['org_name'] : get_bloginfo( 'name' ),
		'logo'         => $logo ? (string) $logo : '',
		'primary'      => $primary,
		'primary_dark' => $dark,
		'receipt_text' => (string) ( $s['receipt_text'] ?? '' ),
		'footer_text'  => (string) ( $s['footer_text'] ?? '' ),
	);
}

/** Lighten (+) or darken (-) a #rrggbb colour. */
function flxb_shade( $hex, $f ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) ) {
		return '#' . $hex;
	}
	$out = '#';
	foreach ( str_split( $hex, 2 ) as $c ) {
		$v    = hexdec( $c );
		$v    = $f < 0 ? $v * ( 1 + $f ) : $v + ( 255 - $v ) * $f;
		$out .= str_pad( dechex( (int) round( max( 0, min( 255, $v ) ) ) ), 2, '0', STR_PAD_LEFT );
	}
	return $out;
}

/** CSS custom properties for the brand colours (admin pages and the front shortcode). */
function flxb_brand_css( $selector ) {
	$b = flxb_brand();
	return $selector . '{--flxb-brand:' . $b['primary'] . ';--flxb-brand-dark:' . $b['primary_dark'] . ';}';
}

/**
 * Dark mode of the front styles: 'class' (default: follow the theme's body.flx-dark), 'auto' (also
 * follow the visitor's prefers-color-scheme) or 'off' (always light).
 * Change with: add_filter( 'flxb_front_dark_mode', fn() => 'auto' );
 */
function flxb_front_dark_mode() {
	$m = (string) apply_filters( 'flxb_front_dark_mode', 'class' );
	return in_array( $m, array( 'class', 'auto', 'off' ), true ) ? $m : 'class';
}
add_filter(
	'body_class',
	function ( $classes ) {
		$m = flxb_front_dark_mode();
		if ( 'class' !== $m ) {
			$classes[] = 'flxb-dark-' . $m;
		}
		return $classes;
	}
);
