<?php
/**
 * Flux Donate 2.15: the donor chooses the currency (e.g. TRY, EUR, USD).
 *
 * No exchange rates: every option has a hand-set price per currency (FLXB_Types::option_price()),
 * an option without a price in the chosen currency is not offered in it. The choice is kept in the
 * `flxb_currency` cookie (set from ?flxb_currency=EUR, see flxb_set_currency_url()) and becomes the
 * WooCommerce currency on the front end only (woocommerce_currency), so the cart, the checkout and
 * the order are in that currency. Admin screens, cron, webhooks and other REST calls keep the store
 * currency; orders always keep their own currency.
 *
 * Settings: FLXB_WC setting `currencies` (extra codes). The store currency is always offered first.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_WC_Currency {

	const COOKIE = 'flxb_currency';
	const PARAM  = 'flxb_currency';

	/** @var string|null Chosen in this request (cookie, link or a cart line that needs it). */
	private static $current = null;

	/** @var bool Guard: reading the store currency without our own filter. */
	private static $raw = false;

	public static function init() {
		add_filter( 'woocommerce_currency', array( __CLASS__, 'filter' ), 50 );
		add_action( 'init', array( __CLASS__, 'from_request' ), 1 );
		add_action( 'send_headers', array( __CLASS__, 'vary' ) );
		add_shortcode( 'flx_bagis_para_birimi', array( __CLASS__, 'shortcode' ) );
	}

	/** Codes donors can choose from the settings form (labels with symbols). */
	public static function known() {
		return array(
			'TRY' => __( 'Turkish lira (₺)', 'flx-donate' ),
			'EUR' => __( 'Euro (€)', 'flx-donate' ),
			'USD' => __( 'US dollar ($)', 'flx-donate' ),
			'GBP' => __( 'Pound sterling (£)', 'flx-donate' ),
		);
	}

	/** Store currency (WooCommerce › Settings › General), never changed by the donor's choice. */
	public static function base() {
		self::$raw = true;
		$c         = function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : (string) get_option( 'woocommerce_currency', 'TRY' );
		self::$raw = false;
		$c         = strtoupper( $c );
		return preg_match( '/^[A-Z]{3}$/', $c ) ? $c : 'TRY';
	}

	/** Codes on offer: the store currency first, then the extra ones from the settings (filter flxb_currencies). */
	public static function codes() {
		$base  = self::base();
		$codes = array( $base );
		foreach ( (array) FLXB_WC::setting( 'currencies' ) as $c ) {
			$c = strtoupper( (string) $c );
			if ( preg_match( '/^[A-Z]{3}$/', $c ) && ! in_array( $c, $codes, true ) ) {
				$codes[] = $c;
			}
		}
		$codes = array_values( array_unique( array_filter( array_map( 'strtoupper', (array) apply_filters( 'flxb_currency_codes', $codes ) ), static function ( $c ) {
			return (bool) preg_match( '/^[A-Z]{3}$/', $c );
		} ) ) );
		return $codes ? $codes : array( $base );
	}

	/** Does this request follow the donor's choice? Only front-end pages, wc-ajax and our own / Store API REST routes. */
	public static function front() {
		/* is_admin() is true for admin-ajax.php too (admin tools, the block editor); wc-ajax is front end. */
		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return false;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rest = isset( $_GET['rest_route'] ) ? (string) wp_unslash( $_GET['rest_route'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || '' !== $rest || false !== strpos( $uri, '/' . rest_get_url_prefix() . '/' ) ) {
			return (bool) preg_match( '#/(wc/store|flxb-wc)/#', $uri . ' ' . $rest . '/' );
		}
		return true;
	}

	/** The donor's currency in this request (store currency when only one is on offer or outside the front end). */
	public static function current() {
		if ( null !== self::$current ) {
			return self::$current;
		}
		$base  = self::base();
		$codes = self::codes();
		if ( count( $codes ) < 2 || ! self::front() ) {
			return $base;
		}
		$c = isset( $_COOKIE[ self::COOKIE ] ) ? strtoupper( sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) ) : '';
		$c = in_array( $c, $codes, true ) ? $c : $base;
		$c = strtoupper( (string) apply_filters( 'flxb_current_currency', $c, $codes ) );
		return in_array( $c, $codes, true ) ? $c : $base;
	}

	public static function filter( $currency ) {
		if ( self::$raw || ! FLXB_WC::ready() ) {
			return $currency;
		}
		$cur = self::current();
		return '' !== $cur ? $cur : $currency;
	}

	/** Remember a choice (cookie for a year) and use it from now on in this request. @return bool */
	public static function set( $code ) {
		$code = strtoupper( (string) $code );
		if ( ! in_array( $code, self::codes(), true ) ) {
			return false;
		}
		self::$current = $code;
		$_COOKIE[ self::COOKIE ] = $code;
		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE,
				$code,
				array(
					'expires'  => time() + YEAR_IN_SECONDS,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly' => false,
					'samesite' => 'Lax',
				)
			);
		}
		/* No cart recalculation here: the cart is not loaded from the session yet (it would be saved empty). */
		return true;
	}

	/** ?flxb_currency=EUR on any front-end page: remember it and go back to the same address without it. */
	public static function from_request() {
		if ( ! isset( $_GET[ self::PARAM ] ) || is_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$code = strtoupper( sanitize_text_field( wp_unslash( $_GET[ self::PARAM ] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		self::set( $code );
		if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
			return;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		nocache_headers();
		wp_safe_redirect( remove_query_arg( self::PARAM, home_url( $uri ) ) );
		exit;
	}

	/** Page caches (LiteSpeed and others) keep one copy per currency. */
	public static function vary() {
		if ( headers_sent() || count( self::codes() ) < 2 || is_admin() ) {
			return;
		}
		header( 'X-LiteSpeed-Vary: cookie=' . self::COOKIE, false );
		header( 'Vary: Cookie', false );
	}

	/** Link that switches to a currency on the given (default: current) address. */
	public static function url( $code, $url = '' ) {
		if ( '' === (string) $url ) {
			$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$url = home_url( $uri );
		}
		return add_query_arg( self::PARAM, rawurlencode( strtoupper( (string) $code ) ), $url );
	}

	/** Public shape: [ CODE => { code, symbol, label, current, url } ] in offer order. */
	public static function all() {
		$known = self::known();
		$cur   = self::current();
		$out   = array();
		foreach ( self::codes() as $c ) {
			$sym       = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol( $c ), ENT_QUOTES, 'UTF-8' ) : flxb_currency_symbol( $c );
			$out[ $c ] = array(
				'code'    => $c,
				'symbol'  => $sym,
				'label'   => $known[ $c ] ?? $c,
				'current' => $c === $cur,
				'url'     => self::url( $c ),
			);
		}
		return $out;
	}

	/** Preset amounts of a type's free amount in a currency (meta presets_by_currency for other currencies). */
	public static function presets( array $t, $currency ) {
		$cur = strtoupper( (string) $currency );
		if ( strtoupper( (string) $t['currency'] ) === $cur ) {
			$list = (array) FLXB_Types::meta( $t, 'presets', array() );
		} else {
			$all  = (array) FLXB_Types::meta( $t, 'presets_by_currency', array() );
			$list = (array) ( $all[ $cur ] ?? array() );
		}
		return array_values( array_filter( array_map( 'floatval', $list ) ) );
	}

	/** Short name shown on the switcher ("TL" for TRY, else the code). */
	public static function short( $code ) {
		return 'TRY' === $code ? 'TL' : $code;
	}

	/**
	 * Currency switcher: a segmented row of links (no JavaScript, works with page caches).
	 *
	 * @param bool $compact Small pill row without the label (header, shortcode).
	 */
	public static function switcher( $compact = false ) {
		$all = self::all();
		if ( count( $all ) < 2 ) {
			return '';
		}
		if ( 'menu' === $compact ) {
			/* 2.22.0: a drop-down of links (no JavaScript, works with page caches); sits beside the amount heading. */
			$now  = $all[ self::current() ] ?? reset( $all );
			$html = '<details class="flxwb-curdd"><summary aria-label="' . esc_attr__( 'Currency', 'flx-donate' ) . '"><span class="flxwb-cur__sym" aria-hidden="true">' . esc_html( $now['symbol'] ) . '</span>' . esc_html( self::short( $now['code'] ) ) . '<span class="flxwb-curdd__chev" aria-hidden="true"></span></summary><span class="flxwb-curdd__list">';
			foreach ( $all as $c ) {
				$html .= '<a class="flxwb-curdd__opt' . ( $c['current'] ? ' is-on' : '' ) . '" href="' . esc_url( $c['url'] ) . '" rel="nofollow"' . ( $c['current'] ? ' aria-current="true"' : '' ) . '><span class="flxwb-cur__sym" aria-hidden="true">' . esc_html( $c['symbol'] ) . '</span><span>' . esc_html( self::short( $c['code'] ) ) . '</span><small>' . esc_html( $c['label'] ) . '</small></a>';
			}
			return $html . '</span></details>';
		}
		$html = '<nav class="flxwb-cur' . ( $compact ? ' flxwb-cur--compact' : '' ) . '" aria-label="' . esc_attr__( 'Currency', 'flx-donate' ) . '">';
		if ( ! $compact ) {
			$html .= '<span class="flxwb-cur__label">' . esc_html__( 'Currency', 'flx-donate' ) . '</span>';
		}
		$html .= '<span class="flxwb-cur__opts">';
		foreach ( $all as $c ) {
			$html .= '<a class="flxwb-cur__opt' . ( $c['current'] ? ' is-on' : '' ) . '" href="' . esc_url( $c['url'] ) . '" rel="nofollow"' . ( $c['current'] ? ' aria-current="true"' : '' ) . ' title="' . esc_attr( $c['label'] ) . '"><span class="flxwb-cur__sym" aria-hidden="true">' . esc_html( $c['symbol'] ) . '</span>' . esc_html( self::short( $c['code'] ) ) . '</a>';
		}
		return $html . '</span></nav>';
	}

	/** [flx_bagis_para_birimi]: the compact switcher. */
	public static function shortcode() {
		if ( class_exists( 'FLXB_WC_Form' ) ) {
			wp_enqueue_style( 'flxwb-front', FLXB_URL . 'assets/wc/front.css', array(), FLXB_VERSION );
		}
		return self::switcher( true );
	}
}
