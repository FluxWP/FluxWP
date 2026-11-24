<?php
/**
 * 2.34.0: the language of a donation, and messages to its owner in that language.
 *
 * A gift keeps the language it was given in (meta `lang`, a short code: en, tr, lv…). On the web it is the
 * visitor's language (Flux Lang, otherwise the WordPress locale); a WooCommerce order keeps it as `_flxb_lang`
 * until the ledger writes the donation; flxb_create_order() and FLXB_Donations::record() take `lang`.
 * Everything sent later to the owner (the file caption, tracking messages) runs inside flxb_in_lang().
 *
 * Without Flux Lang only the WordPress locale is switched; a site with one language behaves as before.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_Lang {

	const ORDER_META = '_flxb_lang';

	public static function init() {
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'stamp_order' ), 5 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'stamp_order' ), 5 );
		add_filter( 'flxb_wc_ledger_row', array( __CLASS__, 'ledger_row' ), 5, 2 );
	}

	/** Clean a language code ("lv", "pt-br"); '' when it is not one. */
	public static function clean( $code ) {
		$code = strtolower( str_replace( '_', '-', trim( (string) $code ) ) );
		return preg_match( '/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $code ) ? $code : '';
	}

	/** The site's own language (Flux Lang default, otherwise the WordPress locale). */
	public static function site() {
		$c = function_exists( 'flxl_default_language' ) ? self::clean( flxl_default_language() ) : '';
		return '' !== $c ? $c : self::of_locale( get_locale() );
	}

	/** The visitor's language now (Flux Lang, otherwise the request locale). */
	public static function current() {
		$c = function_exists( 'flxl_current_language' ) ? self::clean( flxl_current_language() ) : '';
		$c = '' !== $c ? $c : self::of_locale( function_exists( 'determine_locale' ) ? determine_locale() : get_locale() );
		return (string) apply_filters( 'flxb_current_lang', $c );
	}

	/** "tr_TR" → "tr", "pt_BR" → "pt-br" (only when the short code would be ambiguous it stays long). */
	public static function of_locale( $locale ) {
		$locale = (string) $locale;
		$short  = strtolower( substr( $locale, 0, 2 ) );
		return self::clean( in_array( $locale, array( 'pt_BR', 'zh_TW' ), true ) ? $locale : $short );
	}

	/** WordPress locale of a language code. */
	public static function locale( $code ) {
		$code = self::clean( $code );
		if ( '' === $code ) {
			return get_locale();
		}
		if ( function_exists( 'flxl_is_language' ) && function_exists( 'flxl_language_locale' ) && flxl_is_language( $code ) ) {
			return (string) flxl_language_locale( $code );
		}
		$map = array( 'tr' => 'tr_TR', 'en' => 'en_US', 'de' => 'de_DE', 'fr' => 'fr_FR', 'es' => 'es_ES', 'ru' => 'ru_RU', 'lv' => 'lv', 'lt' => 'lt_LT', 'ar' => 'ar', 'ja' => 'ja', 'nl' => 'nl_NL', 'it' => 'it_IT', 'pt-br' => 'pt_BR', 'zh' => 'zh_CN' );
		return (string) apply_filters( 'flxb_lang_locale', $map[ $code ] ?? $code, $code );
	}

	/** Language of a donation row (meta.lang, else the site's). */
	public static function of_donation( $d ) {
		$d = is_array( $d ) ? $d : FLXB_Donations::get( (int) $d );
		$c = $d ? self::clean( FLXB_Donations::meta( $d )['lang'] ?? '' ) : '';
		return (string) apply_filters( 'flxb_donation_lang', '' !== $c ? $c : self::site(), $d );
	}

	/** Language of a tracked item (its donation's). */
	public static function of_item( array $it ) {
		return self::of_donation( (int) ( $it['donation_id'] ?? 0 ) );
	}

	/**
	 * Run $fn with WordPress (and Flux Lang) switched to a language; returns what $fn returns.
	 * Texts with __() and Flux Lang texts come out in that language.
	 */
	public static function run( $code, callable $fn ) {
		$code = self::clean( $code );
		if ( '' === $code ) {
			return $fn( $code );
		}
		$flxl = function_exists( 'flxl_switch_language' ) && flxl_switch_language( $code );
		$wp   = ! $flxl && switch_to_locale( self::locale( $code ) );
		do_action( 'flxb_lang_switched', $code );
		try {
			return $fn( $code );
		} finally {
			if ( $flxl && function_exists( 'flxl_restore_language' ) ) {
				flxl_restore_language();
			} elseif ( $wp ) {
				restore_previous_locale();
			}
			do_action( 'flxb_lang_restored', $code );
		}
	}

	/** Checkout on the web: keep the visitor's language on the order. */
	public static function stamp_order( $order ) {
		if ( ! $order instanceof WC_Order || ( is_admin() && ! wp_doing_ajax() ) ) {
			return;
		}
		if ( '' === (string) $order->get_meta( self::ORDER_META ) ) {
			$order->update_meta_data( self::ORDER_META, self::current() );
		}
	}

	/** Ledger: the order's language goes to the donation row. */
	public static function ledger_row( $d, $order ) {
		if ( $order instanceof WC_Order && empty( $d['meta']['lang'] ) ) {
			$c = self::clean( $order->get_meta( self::ORDER_META ) );
			if ( '' !== $c ) {
				$d['meta']['lang'] = $c;
			}
		}
		return $d;
	}
}

/** Language code of a donation (row or id). */
function flxb_donation_lang( $donation ) {
	return FLXB_Lang::of_donation( $donation );
}

/** Language code of a tracked item. */
function flxb_item_lang( array $it ) {
	return FLXB_Lang::of_item( $it );
}

/** Run a callable in a language (code like "lv"); see FLXB_Lang::run(). */
function flxb_in_lang( $code, callable $fn ) {
	return FLXB_Lang::run( $code, $fn );
}
