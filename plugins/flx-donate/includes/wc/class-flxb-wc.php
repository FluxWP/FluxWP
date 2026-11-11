<?php
/**
 * Flux Donate 2.0: WooCommerce layer. Donations are paid through WooCommerce (cart, classic and block
 * checkout, gateways); donation types stay the source of truth for prices and options and every
 * paid order line is written to the donation ledger (wp_flxb_donations).
 *
 * This file boots the layer and keeps the shared settings (option `flxb_wc`). Flux Donate 2.0 replaces
 * Flux WooCommerce Bağış (flx-wc-bagis): remove that plugin before activating 2.0 (fresh install, no
 * data is taken over).
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_WC {

	const OPTION     = 'flxb_wc';
	const MIN_WC     = '8.0';

	/** @var array|null */
	private static $settings = null;

	public static function init() {
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_compat' ) );
		add_action( 'plugins_loaded', array( __CLASS__, 'boot' ), 15 );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush_cache' ) );
		add_filter( 'flx_sk_required_plugins', array( __CLASS__, 'theme_required_plugins' ) );
		/* Registered even without WooCommerce, so saving a type never drops these keys. */
		add_filter( 'flxb_type_meta_clean', array( __CLASS__, 'clean_type_meta' ), 10, 2 );
	}

	/**
	 * Type meta keys of the WooCommerce layer (type editor card "WooCommerce" and the API):
	 * in_form (bool, shown in the "Donation options" block), certificate (bool), cert_template (int),
	 * custom_option (option key), presets (amounts), page_url (site path or external URL) and page_id (post id;
	 * both set from a page address). wc_product_id is written by Flux Donate itself only.
	 */
	public static function clean_type_meta( $out, $m ) {
		$out = (array) $out;
		$m   = (array) $m;
		if ( array_key_exists( 'in_form', $m ) ) {
			$out['in_form'] = ! empty( $m['in_form'] );
		}
		if ( array_key_exists( 'certificate', $m ) ) {
			$out['certificate'] = ! empty( $m['certificate'] );
		}
		if ( array_key_exists( 'cert_template', $m ) ) {
			$out['cert_template'] = absint( $m['cert_template'] );
		}
		if ( array_key_exists( 'custom_option', $m ) ) {
			$out['custom_option'] = sanitize_key( (string) $m['custom_option'] );
		}
		if ( array_key_exists( 'presets', $m ) ) {
			$out['presets'] = self::clean_amounts( $m['presets'] );
		}
		/* 2.15: per-currency preset amounts and minimums for currencies other than the type's own. */
		if ( array_key_exists( 'presets_by_currency', $m ) ) {
			$out['presets_by_currency'] = array();
			foreach ( (array) $m['presets_by_currency'] as $code => $list ) {
				$code = strtoupper( sanitize_text_field( (string) $code ) );
				$vals = self::clean_amounts( $list );
				if ( preg_match( '/^[A-Z]{3}$/', $code ) && $vals ) {
					$out['presets_by_currency'][ $code ] = $vals;
				}
			}
		}
		if ( array_key_exists( 'min_by_currency', $m ) ) {
			$out['min_by_currency'] = FLXB_Types::clean_prices( (array) $m['min_by_currency'] );
		}
		if ( array_key_exists( 'page_url', $m ) ) {
			/* 2.0.1: kept as a post id and a site-relative path, so moving the site does not break it. */
			list( $out['page_url'], $out['page_id'] ) = self::store_page_url( (string) $m['page_url'] );
		}
		if ( array_key_exists( 'page_id', $m ) && ! array_key_exists( 'page_url', $m ) ) {
			$out['page_id'] = absint( $m['page_id'] );
		}
		return $out;
	}

	/**
	 * A page address for type meta: same-site addresses become a path ("/kurban/") plus the post id
	 * when WordPress knows the page; other sites stay absolute.
	 *
	 * @return array { 0: string path or URL, 1: int post id }
	 */
	public static function store_page_url( $url ) {
		$url = trim( $url );
		if ( '' === $url ) {
			return array( '', 0 );
		}
		$home = untrailingslashit( preg_replace( '#^https?://#i', '', home_url() ) );
		$bare = preg_replace( '#^https?://#i', '', $url );
		if ( '/' === $url[0] && '/' !== ( $url[1] ?? '' ) ) {
			$path = $url;
		} elseif ( preg_match( '#^https?://#i', $url ) && ( 0 === stripos( $bare . '/', $home . '/' ) ) ) {
			$path = substr( $bare, strlen( $home ) );
			$path = '' === $path ? '/' : $path;
		} else {
			return array( esc_url_raw( $url ), 0 );
		}
		$path = '/' . ltrim( preg_replace( '/[\s"\'<>\\\\]+/', '', wp_strip_all_tags( $path ) ), '/' );
		$id   = url_to_postid( home_url( $path ) );
		return array( $path, (int) $id );
	}

	/**
	 * Page of a type (product page redirect, XML link, flxb_donate_url): the stored post's permalink,
	 * else the stored path on this site, else the stored URL; '' = none (callers use page_url()).
	 * Filter `flxb_type_page_url`.
	 */
	public static function type_page_url( array $t ) {
		$id  = (int) FLXB_Types::meta( $t, 'page_id', 0 );
		$raw = (string) FLXB_Types::meta( $t, 'page_url', '' );
		$url = '';
		if ( $id && 'publish' === get_post_status( $id ) ) {
			$url = (string) get_permalink( $id );
		} elseif ( '' !== $raw ) {
			$url = '/' === $raw[0] ? home_url( $raw ) : $raw;
		}
		return (string) apply_filters( 'flxb_type_page_url', $url, $t );
	}

	/** HPOS and cart/checkout blocks compatibility. */
	public static function declare_compat() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', FLXB_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', FLXB_FILE, true );
		}
	}

	/** Is WooCommerce loaded (and new enough)? */
	public static function active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && defined( 'WC_VERSION' ) && version_compare( WC_VERSION, self::MIN_WC, '>=' );
	}

	public static function boot() {
		if ( ! self::active() ) {
			return;
		}
		$dir = FLXB_DIR . 'includes/wc/';
		foreach ( array( 'currency', 'products', 'cart', 'form', 'havale', 'iyzico', 'iyzico-card', 'receipts', 'certificates', 'notify', 'account', 'ledger', 'recurring', 'feed', 'callcenter', 'flxform', 'admin', 'checks', 'addresses', 'contact', 'designs', 'terms', 'gateways', 'esnekpos', 'esnekpos-direct', 'look', 'fee', 'checkout-layout' ) as $f ) {
			require_once $dir . 'class-flxb-wc-' . $f . '.php';
		}
		if ( ! defined( 'FLXWB_VERSION' ) ) {
			require_once $dir . 'compat-flxwb.php'; // Not while the retired plugin is still active: it declares these names itself.
		}
		FLXB_WC_Currency::init();
		FLXB_WC_Products::init();
		FLXB_WC_Cart::init();
		FLXB_WC_Form::init();
		FLXB_WC_Havale::init();
		FLXB_WC_Iyzico::init();
		FLXB_WC_Iyzico_Card::init();
		FLXB_WC_Receipts::init();
		FLXB_WC_Certificates::init();
		FLXB_WC_Notify::init();
		FLXB_WC_Account::init();
		FLXB_WC_Ledger::init();
		FLXB_WC_Recurring::init();
		FLXB_WC_Feed::init();
		FLXB_WC_Callcenter::init();
		FLXB_WC_FLXForm::init();
		FLXB_WC_Admin::init();
		FLXB_WC_Checks::init();
		FLXB_WC_Addresses::init();
		FLXB_WC_Contact::init();
		FLXB_WC_Designs::init();
		FLXB_WC_Terms::init();
		FLXB_WC_Gateways::init();
		FLXB_WC_Esnekpos::init();
		FLXB_WC_Esnekpos_Direct::init();
		FLXB_WC_Look::init();
		FLXB_WC_Fee::init();
		FLXB_WC_Checkout_Layout::init();
		define( 'FLXB_WC_READY', true );
		do_action( 'flxb_wc_loaded' );
	}

	/** Can donors choose "online + printed certificate" (setting print_mode = on)? */
	public static function print_on() {
		return 'on' === self::setting( 'print_mode' );
	}

	/** Is the printed certificate shown as "Coming soon" (disabled)? */
	public static function print_soon() {
		return 'soon' === self::setting( 'print_mode' );
	}

	/** Is the WooCommerce layer running in this request? */
	public static function ready() {
		return defined( 'FLXB_WC_READY' );
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	public static function defaults() {
		return array(
			'template_id'     => 0,       // 0 = "Fidan Bağış Sertifikası" template, else the first active one.
			'event_id'        => 0,
			'cert_channels'   => array( 'whatsapp', 'email' ),
			'print_mode'      => 'soon',  // "Online + printed certificate": soon (shown disabled, "Coming soon"), on, off (hidden).
			'print_fee'       => 0,       // Per line; 0 = free.
			'auto_complete'   => 1,       // Online certificate only: paid order goes straight to Completed.
			'simple_address'  => 1,       // Donation-only cart: no address fields at the checkout (2.0.2; filled in for the payment provider).
			'after_add'       => 'checkout', // checkout (2.0.2 default: skip the cart) | cart | stay
			'page_id'         => 0,       // Page with the donation options.
			'notify_channels' => array( 'whatsapp' ),
			'notify_wa'       => '',
			'notify_sms'      => '',
			'receipt_max_mb'  => 8,
			'receipt_notify'  => '',
			'forms_checkout'  => 1,       // Flux Forms payment forms pay through the WooCommerce checkout.
			'card_gateway'    => 'auto',  // Before 2.1.0; replaced by card_ui.
			'card_ui'         => 'fields', // 2.1.0: fields (our card fields) | form (iyzico payment page) | official (official iyzico plugin for one-time).
			'card_installments' => 12,   // 2.1.0: most installments offered with our card fields (1 = single payment only).
			'support_wa'      => '', // 2.0.4: shown on the checkout and the transfer panel; empty = hidden.
			'card_method'     => 'auto', // 2.13.0: auto (a third-party POS if enabled, else iyzico) | iyzico | <gateway id> | all.
			'block_fallback'  => 1,  // 2.13.0: payment plugins without checkout block support are added to the block (FLXB_WC_Gateways).
			'currencies'      => array(), // 2.15.0: extra currencies donors can choose (the store currency is always offered).
		);
	}

	/** Support WhatsApp number as typed and its wa.me link (2.0.4); empty when not set. */
	public static function support_wa() {
		$num    = trim( (string) self::settings()['support_wa'] );
		$digits = preg_replace( '/\D+/', '', $num );
		if ( strlen( $digits ) < 10 ) {
			return array( '', '' );
		}
		if ( 0 === strpos( $digits, '0' ) ) {
			$digits = '9' . $digits; // 0530… → 90530…
		}
		return array( $num, 'https://wa.me/' . $digits );
	}

	/** "Trouble? Write to us on WhatsApp" line; '' when no number is set. */
	public static function support_html() {
		list( $num, $url ) = self::support_wa();
		if ( '' === $url ) {
			return '';
		}
		return '<p class="flxwb-support">' . self::icon( 'whatsapp' ) . '<span>' . esc_html__( 'Any trouble? Write to us on WhatsApp:', 'flx-donate' ) . ' <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $num ) . '</a></span></p>';
	}

	public static function settings() {
		if ( null === self::$settings ) {
			$s              = get_option( self::OPTION, array() );
			self::$settings = wp_parse_args( is_array( $s ) ? $s : array(), self::defaults() );
		}
		return self::$settings;
	}

	public static function setting( $key ) {
		$s = self::settings();
		return $s[ $key ] ?? null;
	}

	public static function flush_cache() {
		self::$settings = null;
	}

	public static function save_settings( array $s ) {
		update_option( self::OPTION, array_merge( self::settings(), $s ), false );
		self::$settings = null;
	}

	/* ------------------------------------------------------------------
	 * Shared helpers
	 * ------------------------------------------------------------------ */

	/** Plain-text money (WhatsApp/SMS, JS). */
	/** Up to 8 positive amounts from a list or a "250 500 1000" string. */
	public static function clean_amounts( $raw ) {
		$raw  = is_array( $raw ) ? $raw : preg_split( '/[\s;|]+/', (string) $raw );
		$vals = array();
		foreach ( (array) $raw as $v ) {
			$a = is_numeric( $v ) ? (float) $v : self::parse_amount( $v );
			if ( $a > 0 ) {
				$vals[] = round( $a, 2 );
			}
		}
		return array_slice( array_values( array_unique( $vals ) ), 0, 8 );
	}

	public static function money_text( $amount, $currency = '' ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'currency' => $currency ) ) ), ENT_QUOTES, 'UTF-8' );
	}

	/** "1.250,50" / "1250.5" / "1 250" → 1250.5 */
	public static function parse_amount( $raw ) {
		$s = preg_replace( '/[^\d,.]/', '', (string) $raw );
		if ( '' === $s ) {
			return 0.0;
		}
		$comma = strrpos( $s, ',' );
		$dot   = strrpos( $s, '.' );
		if ( false !== $comma && ( false === $dot || $comma > $dot ) ) {
			$s = str_replace( ',', '.', str_replace( '.', '', $s ) );
		} elseif ( false !== $dot && false === $comma && preg_match( '/\.\d{3}$/', $s ) ) {
			$s = str_replace( '.', '', $s );
		} else {
			$s = str_replace( ',', '', $s );
		}
		return (float) $s;
	}

	/** Badges for options (key => label). Filter: `flxb_option_badges`. */
	public static function badges() {
		return (array) apply_filters(
			'flxb_option_badges',
			array(
				''          => __( 'No badge', 'flx-donate' ),
				'avantajli' => __( 'Best value', 'flx-donate' ),
				'populer'   => __( 'Most popular', 'flx-donate' ),
				'aile'      => __( 'Family pack', 'flx-donate' ),
			)
		);
	}

	public static function badge_label( $key ) {
		$b = self::badges();
		return '' === (string) $key ? '' : (string) ( $b[ $key ] ?? $key );
	}

	/** Address of the donation page (setting, then /bagis/, then home). */
	public static function page_url() {
		$id = (int) self::setting( 'page_id' );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			return get_permalink( $id );
		}
		$p = get_page_by_path( 'bagis' );
		return $p ? get_permalink( $p ) : home_url( '/' );
	}

	/** Flux Certificates functions used for certificates. */
	public static function certificates_available() {
		return function_exists( 'flxs_create_certificate' ) && function_exists( 'flxs_upsert_person' ) && function_exists( 'flxs_get_template' );
	}

	/** Certificate template for a type (type meta, then setting, then "Fidan Bağış Sertifikası"). */
	public static function template_id( $type = null ) {
		$id = $type ? (int) FLXB_Types::meta( $type, 'cert_template', 0 ) : 0;
		$id = $id ? $id : (int) self::setting( 'template_id' );
		if ( $id || ! function_exists( 'flxs_list_templates' ) ) {
			return $id;
		}
		$first = 0;
		foreach ( (array) flxs_list_templates( true ) as $t ) {
			$first = $first ? $first : (int) $t['id'];
			if ( 'Fidan Bağış Sertifikası' === $t['name'] ) {
				return (int) $t['id'];
			}
		}
		return $first;
	}

	/** "15 saplings" for a unit (falls back to a plain number). */
	public static function units_text( $n, $unit = 'fidan' ) {
		$n = (int) $n;
		if ( '' !== (string) $unit && function_exists( 'flxb_unit_label' ) ) {
			return number_format_i18n( $n ) . ' ' . flxb_unit_label( $unit, $n );
		}
		return number_format_i18n( $n );
	}

	/** Default texts of the pending-transfer message (site language). */
	public static function default_notify_text( $channel ) {
		if ( 'sms' === $channel ) {
			/* translators: SMS template. Placeholders: {ad} {tutar} {siparis} {iban} {aciklama} {link} */
			return __( 'Hello {ad}, your donation of {tutar} is registered. IBAN: {iban} Reference: {aciklama} Upload your receipt: {link}', 'flx-donate' );
		}
		/* translators: WhatsApp template. Placeholders: {ad} {tutar} {siparis} {alici} {iban} {aciklama} {link} {site} */
		return __( 'Hello {ad} 🌱

Your donation of *{tutar}* is registered (No: {siparis}).

Bank transfer details:
Recipient: {alici}
IBAN: {iban}
Reference: *{aciklama}*

After the transfer you can upload your receipt here:
{link}

Thank you,
{site}', 'flx-donate' );
	}

	public static function notify_text( $channel ) {
		$t = trim( (string) self::setting( 'sms' === $channel ? 'notify_sms' : 'notify_wa' ) );
		return '' !== $t ? $t : self::default_notify_text( $channel );
	}

	/** Inline SVG icons used on the front end. */
	public static function icon( $name ) {
		$p = array(
			'sprout' => '<path d="M12 21v-9"/><path d="M12 12C7 12 5 9 5 5c5 0 7 3 7 7z"/><path d="M12 10c4 0 7-2 7-6-4 0-7 2-7 6z"/>',
			'heart'  => '<path d="M12 20s-7.5-4.6-7.5-10.2A4.2 4.2 0 0 1 12 7.3a4.2 4.2 0 0 1 7.5 2.5C19.5 15.4 12 20 12 20z"/>',
			'globe'  => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>',
			'print'  => '<path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M7 14h10v7H7z"/>',
			'cart'   => '<path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6.2"/><circle cx="10" cy="20.5" r="1.2"/><circle cx="17" cy="20.5" r="1.2"/>',
			'lock'   => '<rect x="5" y="10.5" width="14" height="10" rx="2.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/><path d="M12 14.5v2.5"/>',
			'card'   => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="M3 10h18M7 15h4"/>',
			'bank'   => '<path d="M3 10 12 4l9 6"/><path d="M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 20h18"/>',
			'check'  => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
			'minus'  => '<path d="M6 12h12"/>',
			'plus'   => '<path d="M12 6v12M6 12h12"/>',
			'upload' => '<path d="M12 16V4m0 0-4.5 4.5M12 4l4.5 4.5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
			'whatsapp' => '<path d="M4.5 19.5l1.2-3.6a8 8 0 1 1 2.4 2.4z"/><path d="M9.2 8.6c-.3 2.9 3.3 6.5 6.2 6.2l.9-1.5-2-1-.9.7a4 4 0 0 1-2.4-2.4l.7-.9-1-2z"/>',
			'copy'   => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/>',
			'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
			'file'   => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
			'award'  => '<circle cx="12" cy="9" r="6"/><path d="M8.5 13.9 7 22l5-3 5 3-1.5-8.1"/>',
			'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>',
			'gift'   => '<rect x="3" y="8" width="18" height="5" rx="1"/><path d="M5 13v8h14v-8M12 8v13M12 8c-1.5-3-5-3.5-5-1s3 1 5 1c2 0 5 1.5 5-1s-3.5-2-5 1z"/>',
			'wa'     => '<path d="M4 20l1.3-4A8 8 0 1 1 8 18.8z"/><path d="M9 9.5c.3 2 2.5 4.2 4.5 4.5l1-1.2 2 1-.5 1.5c-3.5.5-8-4-7.5-7.5L10 7.3l1 2z"/>',
			'mail'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
			'repeat' => '<path d="M4 12a8 8 0 0 1 13.7-5.6L20 8.7M20 4v4.7h-4.7M20 12a8 8 0 0 1-13.7 5.6L4 15.3M4 20v-4.7h4.7"/>',
			/* 2.16.0: animals for sacrifice types (type meta "icon"). */
			'sheep'  => '<g transform="translate(-2.4 -3) scale(1.2)"><path d="M8.6 8.4a2.4 2.4 0 0 1 3.9-1.2 2.4 2.4 0 0 1 3.8.4 2.3 2.3 0 0 1 2.6 2.6 2.2 2.2 0 0 1-.3 3.8 2.3 2.3 0 0 1-3 2 2.4 2.4 0 0 1-3.8.3 2.4 2.4 0 0 1-3.6-.9 2.2 2.2 0 0 1-1-2.7"/><path d="M7.2 9.2c-1.6 0-2.7 1.1-2.7 2.5 0 1.2.9 2.1 2.1 2.1h.6c1 0 1.7-.8 1.7-1.8V11c0-1-.7-1.8-1.7-1.8z"/><path d="M10.5 16.6v2.6M13.5 16.8v2.4M16 16.4v2.8"/></g>',
			'cow'    => '<g transform="translate(-1.04 -2.13) scale(1.04)"><path d="M4 9h11c1.7 0 3 1.3 3 3v3.5H6.5C5.1 15.5 4 14.4 4 13z"/><path d="M15.5 9V7.5h4l1.5 2.5v2.5h-3"/><path d="M16 7.5l-1-2M20 7.5l1-2"/><path d="M6.5 15.5V19M9 15.5V19M14 15.5V19M16.5 15.5V19"/><path d="M4 10l-1.5 3"/></g>',
			'camel'  => '<g transform="translate(-1.04 -2.13) scale(1.04)"><path d="M3.5 15c0-2.5 1.6-4.2 3.5-4.2.8-2.1 3.2-2.1 4 0 .8-2.1 3.2-2.1 4 0 1.2 0 2 .8 2 2v2.2z"/><path d="M17 13l1.5-6h2.5l.5 1.5h-1.5"/><path d="M5.5 15v4.5M8.5 15v4.5M13.5 15v4.5M16 15v4.5"/></g>',
		);
		$p = (array) apply_filters( 'flxb_icons', $p );
		return isset( $p[ $name ] ) ? '<svg class="flxwb-ic" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p[ $name ] . '</svg>' : '';
	}

	/** Theme System check: flx-wc-bagis is part of Flux Donate now. */
	public static function theme_required_plugins( $list ) {
		if ( is_array( $list ) ) {
			unset( $list['flx-wc-bagis'] );
			if ( isset( $list['flx-donate'] ) && is_array( $list['flx-donate'] ) ) {
				$list['flx-donate']['min'] = '2.0.0';
			}
		}
		return $list;
	}
}
