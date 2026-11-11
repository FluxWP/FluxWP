<?php
/**
 * Flux Donate 2.16.0: appearance of the donation form and the checkout's payment options.
 *
 * Donations › Settings › Appearance (option flxb_look): form layout (classic | steps | panel | cards),
 * payment options layout (theme | cards | list | tabs), colours, corner radius, section order and headings.
 * Presets write the same option (flx_settings_schema), the site owner can change it later.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_WC_Look {

	const OPTION = 'flxb_look';

	/** Sections of the form that can be reordered (the type choice is always first, the total always last). */
	const SECTIONS = array( 'options', 'who', 'cert', 'design', 'monthly' );

	private static $cache = null;

	public static function init() {
		add_filter( 'flxb_settings_tabs', array( __CLASS__, 'tab' ) );
		add_action( 'flxb_settings_tab_look', array( __CLASS__, 'render_tab' ) );
		add_action( 'admin_post_flxb_look_save', array( __CLASS__, 'save' ) );
		add_filter( 'flx_settings_schema', array( __CLASS__, 'schema' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'checkout_assets' ), 30 );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
	}

	public static function defaults() {
		return array(
			'form_style'   => 'classic', // classic | steps | panel | cards
			'pay_style'    => 'theme',   // theme (WooCommerce / theme look) | cards | list | tabs
			'primary'      => '',        // #RRGGBB; empty = theme colour
			'accent'       => '',        // #RRGGBB; empty = theme colour
			'radius'       => '',        // px 0–28; empty = theme
			'order'        => self::SECTIONS,
			'type_label'   => '',        // heading over the type choice (steps / cards); empty = "Donation type"
			'option_label' => '',        // heading over the options; empty = built-in
			'share_phone'  => 1,         // proxy types: a phone field on every share card
			'opt_view'     => 'list',    // list | select (one row: drop-down + quantity) | smart (list until a pick, then one row)
			'fee_on'       => 0,         // "I will cover the transaction fee" box on the form
			'fee_checked'  => 0,         // the box is ticked by default
			'fee_rates'    => array(),   // [ gateway id => [ 'pct' => 2.9, 'fixed' => 0.25 ] ]
			'checkout'     => 'compact', // 2.18.0: compact (two columns, cards, pay button in the summary) | theme
			'signup'       => 1,         // 2.18.0: guests can tick "Create an account" at the checkout
		);
	}

	public static function flush() {
		self::$cache = null;
	}

	public static function get( $key = null ) {
		if ( null === self::$cache ) {
			$s           = get_option( self::OPTION, array() );
			self::$cache = self::clean( is_array( $s ) ? $s : array() );
		}
		return null === $key ? self::$cache : ( self::$cache[ $key ] ?? null );
	}

	/**
	 * A heading text for the site (type_label / option_label). Empty = built-in text.
	 * Filter flxb_look_label lets a preset or a translation plugin give it in the visitor's language.
	 */
	public static function label( $key ) {
		return (string) apply_filters( 'flxb_look_label', (string) self::get( $key ), $key, determine_locale() );
	}

	public static function form_styles() {
		return array(
			'classic' => array( __( 'Classic', 'flx-donate' ), __( 'One card: type tabs on top, the choices under them, the total at the bottom.', 'flx-donate' ) ),
			'steps'   => array( __( 'A · Steps + summary', 'flx-donate' ), __( 'Numbered steps on the left, a summary with the total and the button on the right.', 'flx-donate' ) ),
			'panel'   => array( __( 'B · Two panels', 'flx-donate' ), __( 'Types as a list on the left, the details on the right; a dark total bar.', 'flx-donate' ) ),
			'cards'   => array( __( 'C · Picture cards', 'flx-donate' ), __( 'Large cards with the type picture or icon; the details open below.', 'flx-donate' ) ),
		);
	}

	public static function opt_views() {
		return array(
			'list'   => array( __( 'List', 'flx-donate' ), __( 'All options one under the other.', 'flx-donate' ) ),
			'select' => array( __( 'Drop-down', 'flx-donate' ), __( 'One row: the option in a drop-down menu and the quantity beside it.', 'flx-donate' ) ),
			'smart'  => array( __( 'List, then one row', 'flx-donate' ), __( 'The list first; after a pick it folds into one row (drop-down + quantity) and the rest of the form opens.', 'flx-donate' ) ),
		);
	}

	public static function pay_styles() {
		return array(
			'theme' => array( __( 'Theme', 'flx-donate' ), __( 'The look of WooCommerce and the theme.', 'flx-donate' ) ),
			'cards' => array( __( 'A · Side-by-side cards', 'flx-donate' ), __( 'Methods as cards with icons; the details of the picked one below.', 'flx-donate' ) ),
			'list'  => array( __( 'B · Plain list', 'flx-donate' ), __( 'Rows one under the other; the picked row opens.', 'flx-donate' ) ),
			'tabs'  => array( __( 'C · Segmented tabs', 'flx-donate' ), __( 'One row of tab buttons; the details of the picked one below.', 'flx-donate' ) ),
		);
	}

	public static function checkout_styles() {
		return array(
			'compact' => array( __( 'Two columns', 'flx-donate' ), __( 'Contact and payment as cards on the left; the summary, the terms box and the pay button on the right. On phones one column with a pay bar at the bottom.', 'flx-donate' ) ),
			'theme'   => array( __( 'Theme', 'flx-donate' ), __( 'The look of WooCommerce and the theme.', 'flx-donate' ) ),
		);
	}

	public static function section_labels() {
		return array(
			'options' => __( 'Options and amount', 'flx-donate' ),
			'who'     => __( 'On whose behalf', 'flx-donate' ),
			'cert'    => __( 'Certificate', 'flx-donate' ),
			'design'  => __( 'Certificate design', 'flx-donate' ),
			'monthly' => __( 'Repeat every month', 'flx-donate' ),
		);
	}

	/** Sanitised settings (also used for preset values). */
	public static function clean( array $in ) {
		$d   = self::defaults();
		$out = array();
		$out['form_style'] = isset( $in['form_style'], self::form_styles()[ $in['form_style'] ] ) ? (string) $in['form_style'] : $d['form_style'];
		$out['pay_style']  = isset( $in['pay_style'], self::pay_styles()[ $in['pay_style'] ] ) ? (string) $in['pay_style'] : $d['pay_style'];
		foreach ( array( 'primary', 'accent' ) as $k ) {
			$c         = trim( (string) ( $in[ $k ] ?? '' ) );
			$out[ $k ] = preg_match( '/^#[0-9a-fA-F]{6}$/', $c ) ? strtoupper( $c ) : '';
		}
		$r             = trim( (string) ( $in['radius'] ?? '' ) );
		$out['radius'] = '' === $r || ! is_numeric( $r ) ? '' : (string) max( 0, min( 28, (int) $r ) );
		$order         = array();
		foreach ( (array) ( $in['order'] ?? array() ) as $k ) {
			$k = sanitize_key( (string) $k );
			if ( in_array( $k, self::SECTIONS, true ) && ! in_array( $k, $order, true ) ) {
				$order[] = $k;
			}
		}
		foreach ( self::SECTIONS as $k ) {
			if ( ! in_array( $k, $order, true ) ) {
				$order[] = $k; // New or missing sections keep their default place at the end.
			}
		}
		$out['order']        = $order;
		$out['type_label']   = mb_substr( sanitize_text_field( (string) ( $in['type_label'] ?? '' ) ), 0, 60 );
		$out['option_label'] = mb_substr( sanitize_text_field( (string) ( $in['option_label'] ?? '' ) ), 0, 60 );
		$out['share_phone']  = array_key_exists( 'share_phone', $in ) ? ( empty( $in['share_phone'] ) ? 0 : 1 ) : $d['share_phone'];
		$out['opt_view']     = isset( $in['opt_view'], self::opt_views()[ $in['opt_view'] ] ) ? (string) $in['opt_view'] : $d['opt_view'];
		$out['fee_on']       = empty( $in['fee_on'] ) ? 0 : 1;
		$out['fee_checked']  = empty( $in['fee_checked'] ) ? 0 : 1;
		$out['checkout']     = isset( $in['checkout'], self::checkout_styles()[ $in['checkout'] ] ) ? (string) $in['checkout'] : $d['checkout'];
		$out['signup']       = array_key_exists( 'signup', $in ) ? ( empty( $in['signup'] ) ? 0 : 1 ) : $d['signup'];
		$out['fee_rates']    = array();
		foreach ( (array) ( $in['fee_rates'] ?? array() ) as $gw => $r ) {
			$gw  = sanitize_key( (string) $gw );
			$r   = (array) $r;
			$pct = round( max( 0, min( 20, (float) str_replace( ',', '.', (string) ( $r['pct'] ?? 0 ) ) ) ), 2 );
			$fix = round( max( 0, min( 100, (float) str_replace( ',', '.', (string) ( $r['fixed'] ?? 0 ) ) ) ), 2 );
			if ( '' !== $gw && ( $pct > 0 || $fix > 0 ) ) {
				$out['fee_rates'][ $gw ] = array( 'pct' => $pct, 'fixed' => $fix );
			}
		}
		return $out;
	}

	/* ---------- Front end ---------- */

	/** Classes for the form wrapper. */
	public static function form_class() {
		return 'flxwb--' . self::get( 'form_style' );
	}

	/** CSS variables (inline style on the form wrapper and the checkout body). */
	public static function vars() {
		$s   = self::get();
		$css = '';
		if ( '' !== $s['primary'] ) {
			$css .= '--w-primary:' . $s['primary'] . ';--w-primary-dark:' . $s['primary'] . ';--flxb-l-p:' . $s['primary'] . ';';
		}
		if ( '' !== $s['accent'] ) {
			$css .= '--w-accent:' . $s['accent'] . ';--flxb-l-a:' . $s['accent'] . ';';
		}
		if ( '' !== $s['radius'] ) {
			$r    = (int) $s['radius'];
			$css .= '--w-radius:' . $r . 'px;--w-r1:' . round( $r * .6 ) . 'px;--w-r2:' . round( $r * .75 ) . 'px;--w-r3:' . round( $r * .875 ) . 'px;--flxb-l-r:' . $r . 'px;';
		}
		return $css;
	}

	public static function enqueue_form() {
		wp_enqueue_style( 'flxwb-look', FLXB_URL . 'assets/wc/look.css', array( 'flxwb-front' ), FLXB_VERSION );
	}

	/** Checkout: payment layout class. */
	public static function body_class( $c ) {
		$p = self::get( 'pay_style' );
		if ( 'theme' !== $p && function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url() ) {
			$c[] = 'flxb-pay';
			$c[] = 'flxb-pay-' . $p;
		}
		return $c;
	}

	/**
	 * Gateway icons on the payment cards: [ icon => [ gateway ids ] ]; methods not listed get the card icon.
	 * Filter: flxb_pay_icons.
	 */
	public static function pay_icons() {
		return (array) apply_filters(
			'flxb_pay_icons',
			array(
				'bank'   => array( 'flxb_havale', 'bacs', 'flxb_bank' ),
				'paypal' => array( 'ppcp-gateway', 'paypal', 'ppec_paypal', 'ppcp' ),
				'cash'   => array( 'cod', 'cheque' ),
			)
		);
	}

	public static function checkout_assets() {
		if ( 'theme' === self::get( 'pay_style' ) || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() ) {
			return;
		}
		wp_enqueue_style( 'flxb-look-pay', FLXB_URL . 'assets/wc/look-pay.css', array(), FLXB_VERSION );
		$css = 'body.flxb-pay{' . self::vars() . '}';
		foreach ( self::pay_icons() as $icon => $ids ) {
			$sel = array();
			foreach ( (array) $ids as $id ) {
				$sel[] = 'body.flxb-pay label[for="radio-control-wc-payment-method-options-' . sanitize_key( (string) $id ) . '"]';
			}
			if ( $sel ) {
				$css .= implode( ',', $sel ) . '{--flxb-pi:var(--flxb-pi-' . sanitize_key( $icon ) . ',var(--flxb-pi-card))}';
			}
		}
		wp_add_inline_style( 'flxb-look-pay', $css );
		wp_enqueue_script( 'flxb-look-pay', FLXB_URL . 'assets/wc/look-pay.js', array(), FLXB_VERSION, true );
	}

	/* ---------- Settings › Appearance ---------- */

	public static function tab( $tabs ) {
		$out = array();
		foreach ( (array) $tabs as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'general' === $k ) {
				$out['look'] = __( 'Appearance', 'flx-donate' );
			}
		}
		if ( ! isset( $out['look'] ) ) {
			$out['look'] = __( 'Appearance', 'flx-donate' );
		}
		return $out;
	}

	/** Preset / AI schema (Flux Setup). */
	public static function schema( $s ) {
		$s[ self::OPTION ] = array(
			'label'  => 'Flux Donate · Appearance',
			'fields' => array(
				'form_style'   => array( 'type' => 'enum', 'enum' => array_keys( self::form_styles() ), 'default' => 'classic', 'desc' => 'Donation form layout: classic one card, steps (numbered steps + summary), panel (type list left, details right), cards (picture cards).' ),
				'pay_style'    => array( 'type' => 'enum', 'enum' => array_keys( self::pay_styles() ), 'default' => 'theme', 'desc' => 'Checkout payment options: theme (WooCommerce look), cards (side by side), list (plain rows), tabs (segmented buttons).' ),
				'primary'      => array( 'type' => 'color', 'default' => '', 'desc' => 'Main colour #RRGGBB of the form and payment options; empty = theme colour.' ),
				'accent'       => array( 'type' => 'color', 'default' => '', 'desc' => 'Accent colour #RRGGBB (buttons in some layouts, highlights); empty = theme colour.' ),
				'radius'       => array( 'type' => 'string', 'default' => '', 'desc' => 'Corner radius in px (0-28); empty = theme.' ),
				'order'        => array( 'type' => 'list', 'default' => self::SECTIONS, 'desc' => 'Order of the form sections: options, who, cert, design, monthly.' ),
				'type_label'   => array( 'type' => 'string', 'default' => '', 'desc' => 'Heading over the donation type choice (steps / cards layouts).' ),
				'option_label' => array( 'type' => 'string', 'default' => '', 'desc' => 'Heading over the options of a type; empty = built-in text.' ),
				'share_phone'  => array( 'type' => 'bool', 'default' => true, 'desc' => 'Proxy (sacrifice) types: a phone field on every share card.' ),
				'opt_view'     => array( 'type' => 'enum', 'enum' => array_keys( self::opt_views() ), 'default' => 'list', 'desc' => 'Options of a type: list (one under the other), select (one row: drop-down + quantity), smart (list until a pick, then one row; the rest of the form opens after the pick).' ),
				'fee_on'       => array( 'type' => 'bool', 'default' => false, 'desc' => 'Show the "I will cover the transaction fee" box on the donation form.' ),
				'fee_checked'  => array( 'type' => 'bool', 'default' => false, 'desc' => 'The transaction fee box is ticked by default.' ),
				'checkout'     => array( 'type' => 'enum', 'enum' => array_keys( self::checkout_styles() ), 'default' => 'compact', 'desc' => 'Checkout page layout: compact (two columns, sections as cards, terms + pay button in the summary) or theme.' ),
				'signup'       => array( 'type' => 'bool', 'default' => true, 'desc' => 'Guests can tick "Create an account" at the checkout (the password is e-mailed).' ),
				'fee_rates'    => array( 'type' => 'object', 'default' => array(), 'desc' => 'Transaction fee per payment method: { "gateway id": { "pct": 2.9, "fixed": 0.25 } } (fixed in the store currency).' ),
			),
		);
		return $s;
	}

	private static function thumb( $kind, $key ) {
		$file = 'assets/wc/img/look/' . $kind . '-' . $key . '.png';
		return file_exists( FLXB_DIR . $file ) ? '<img src="' . esc_url( FLXB_URL . $file ) . '" alt="" loading="lazy">' : '<span class="flxb-look__ph"></span>';
	}

	public static function render_tab() {
		$s    = self::get();
		$page = (int) FLXB_WC::setting( 'page_id' );
		echo '<form class="flxb-form flxb-look" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxb_look_save">';
		wp_nonce_field( 'flxb_look_save' );

		echo '<section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Donation form', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'How the "Donation options" block looks on the site. Prices, options and names are set under Donation types.', 'flx-donate' ) . '</p></div>';
		if ( $page && get_permalink( $page ) ) {
			echo '<a class="flxb-btn flxb-btn--sm" href="' . esc_url( get_permalink( $page ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View the page', 'flx-donate' ) . '</a>';
		}
		echo '</div><div class="flxb-look__grid">';
		foreach ( self::form_styles() as $k => $v ) {
			echo '<label class="flxb-look__opt"><input type="radio" name="form_style" value="' . esc_attr( $k ) . '"' . checked( $s['form_style'], $k, false ) . '>' . self::thumb( 'form', $k ) . '<strong>' . esc_html( $v[0] ) . '</strong><span>' . esc_html( $v[1] ) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';

		echo '<div class="flxb-fields-2">';
		echo '<div class="flxb-field"><label for="flxb-look-tl">' . esc_html__( 'Heading over the types', 'flx-donate' ) . '</label><input id="flxb-look-tl" type="text" name="type_label" maxlength="60" value="' . esc_attr( $s['type_label'] ) . '" placeholder="' . esc_attr__( 'Donation type', 'flx-donate' ) . '"><p class="flxb-help">' . esc_html__( 'Shown in the A and C layouts.', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-field"><label for="flxb-look-ol">' . esc_html__( 'Heading over the options', 'flx-donate' ) . '</label><input id="flxb-look-ol" type="text" name="option_label" maxlength="60" value="' . esc_attr( $s['option_label'] ) . '" placeholder="' . esc_attr__( 'Choose an option', 'flx-donate' ) . '"></div>';
		echo '</div>';

		echo '<div class="flxb-field"><span class="flxb-label">' . esc_html__( 'How the options are shown', 'flx-donate' ) . '</span><div class="flxb-look__grid flxb-look__grid--text">';
		foreach ( self::opt_views() as $k => $v ) {
			echo '<label class="flxb-look__opt"><input type="radio" name="opt_view" value="' . esc_attr( $k ) . '"' . checked( $s['opt_view'], $k, false ) . '><strong>' . esc_html( $v[0] ) . '</strong><span>' . esc_html( $v[1] ) . '</span></label>';
		}
		echo '</div></div>';

		echo '<div class="flxb-field"><span class="flxb-label">' . esc_html__( 'Order of the sections', 'flx-donate' ) . '</span><ol class="flxb-look__order" data-flxb-look-order>';
		$labels = self::section_labels();
		foreach ( $s['order'] as $k ) {
			echo '<li><input type="hidden" name="order[]" value="' . esc_attr( $k ) . '"><span>' . esc_html( $labels[ $k ] ) . '</span><button type="button" class="flxb-iconbtn" data-up aria-label="' . esc_attr__( 'Move up', 'flx-donate' ) . '">↑</button><button type="button" class="flxb-iconbtn" data-down aria-label="' . esc_attr__( 'Move down', 'flx-donate' ) . '">↓</button></li>';
		}
		echo '</ol><p class="flxb-help">' . esc_html__( 'The type choice is always first and the total always last. Sections a type does not use are skipped.', 'flx-donate' ) . '</p></div>';
		echo '<label class="flxb-check"><input type="checkbox" name="share_phone" value="1"' . checked( (int) $s['share_phone'], 1, false ) . '> ' . esc_html__( 'Ask a phone number for every share (types with a proxy). The video or certificate of that share can be sent there too.', 'flx-donate' ) . '</label>';
		echo '</section>';

		echo '<section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Payment options at the checkout', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'How card, PayPal, bank transfer and the other methods are shown. The methods themselves are turned on under WooCommerce › Settings › Payments.', 'flx-donate' ) . '</p></div></div><div class="flxb-look__grid">';
		foreach ( self::pay_styles() as $k => $v ) {
			echo '<label class="flxb-look__opt"><input type="radio" name="pay_style" value="' . esc_attr( $k ) . '"' . checked( $s['pay_style'], $k, false ) . '>' . self::thumb( 'pay', $k ) . '<strong>' . esc_html( $v[0] ) . '</strong><span>' . esc_html( $v[1] ) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
		echo '<div class="flxb-field"><span class="flxb-label">' . esc_html__( 'Checkout page layout', 'flx-donate' ) . '</span><div class="flxb-look__grid flxb-look__grid--text">';
		foreach ( self::checkout_styles() as $k => $v ) {
			echo '<label class="flxb-look__opt"><input type="radio" name="checkout" value="' . esc_attr( $k ) . '"' . checked( $s['checkout'], $k, false ) . '><strong>' . esc_html( $v[0] ) . '</strong><span>' . esc_html( $v[1] ) . '</span></label>';
		}
		echo '</div></div>';
		echo '<label class="flxb-check"><input type="checkbox" name="signup" value="1"' . checked( (int) $s['signup'], 1, false ) . '> ' . esc_html__( 'Guests can create an account at the checkout with the details they enter (the password is e-mailed to them)', 'flx-donate' ) . '</label>';
		echo '</section>';

		echo '<section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Colours and corners', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Used by the form and the payment options. Empty fields follow the theme.', 'flx-donate' ) . '</p></div></div><div class="flxb-fields-2">';
		foreach ( array( 'primary' => __( 'Main colour', 'flx-donate' ), 'accent' => __( 'Accent colour', 'flx-donate' ) ) as $k => $l ) {
			echo '<div class="flxb-field"><label for="flxb-look-' . esc_attr( $k ) . '">' . esc_html( $l ) . '</label><span class="flxb-look__color"><input type="color" value="' . esc_attr( '' !== $s[ $k ] ? $s[ $k ] : '#1F2937' ) . '" data-flxb-color="flxb-look-' . esc_attr( $k ) . '" aria-hidden="true" tabindex="-1"><input id="flxb-look-' . esc_attr( $k ) . '" type="text" name="' . esc_attr( $k ) . '" value="' . esc_attr( $s[ $k ] ) . '" maxlength="7" placeholder="' . esc_attr__( 'Theme', 'flx-donate' ) . '" pattern="#[0-9a-fA-F]{6}"></span></div>';
		}
		echo '<div class="flxb-field"><label for="flxb-look-r">' . esc_html__( 'Corner radius (px)', 'flx-donate' ) . '</label><input id="flxb-look-r" type="number" name="radius" min="0" max="28" step="1" style="width:110px" value="' . esc_attr( $s['radius'] ) . '" placeholder="' . esc_attr__( 'Theme', 'flx-donate' ) . '"><p class="flxb-help">' . esc_html__( '0 = square, 4 = plain corporate, 16 = round.', 'flx-donate' ) . '</p></div>';
		echo '</div></section>';

		echo '<section class="flxb-card"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Transaction fee', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Donors can choose to also pay the fee of the payment method, so the whole donation reaches you. The fee is added at the checkout for the method they pick.', 'flx-donate' ) . '</p></div></div>';
		echo '<label class="flxb-check"><input type="checkbox" name="fee_on" value="1"' . checked( (int) $s['fee_on'], 1, false ) . '> ' . esc_html__( 'Show "I will cover the transaction fee" on the donation form', 'flx-donate' ) . '</label>';
		echo '<label class="flxb-check"><input type="checkbox" name="fee_checked" value="1"' . checked( (int) $s['fee_checked'], 1, false ) . '> ' . esc_html__( 'Ticked by default', 'flx-donate' ) . '</label>';
		echo '<table class="flxb-look__fees"><thead><tr><th>' . esc_html__( 'Payment method', 'flx-donate' ) . '</th><th>%</th><th>' . esc_html__( 'Fixed', 'flx-donate' ) . ' (' . esc_html( html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ) . ')</th></tr></thead><tbody>';
		foreach ( FLXB_WC_Fee::gateways() as $id => $title ) {
			$r = $s['fee_rates'][ $id ] ?? array( 'pct' => '', 'fixed' => '' );
			echo '<tr><td>' . esc_html( $title ) . ' <code>' . esc_html( $id ) . '</code></td><td><input type="number" step="0.01" min="0" max="20" name="fee_rates[' . esc_attr( $id ) . '][pct]" value="' . esc_attr( (string) $r['pct'] ) . '" placeholder="0"></td><td><input type="number" step="0.01" min="0" max="100" name="fee_rates[' . esc_attr( $id ) . '][fixed]" value="' . esc_attr( (string) $r['fixed'] ) . '" placeholder="0"></td></tr>';
		}
		echo '</tbody></table><p class="flxb-help">' . esc_html__( 'Empty or 0 = no fee for that method (for example bank transfer). Fee = donation × % + fixed.', 'flx-donate' ) . '</p></section>';

		echo '<div class="flxb-formactions"><button type="submit" class="flxb-btn flxb-btn--primary">' . esc_html__( 'Save settings', 'flx-donate' ) . '</button></div></form>';
		?>
<style>
.flxb-look .flxb-card{margin-bottom:16px}
.flxb-look__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:14px;margin:6px 0 18px}
.flxb-look__opt{display:flex;flex-direction:column;gap:6px;padding:10px;border:1px solid var(--flxb-line,#dcdcde);border-radius:10px;background:#fff;cursor:pointer}
.flxb-look__opt:has(input:checked){border-color:var(--flxb-primary,#2271b1);box-shadow:0 0 0 2px var(--flxb-primary,#2271b1)}
.flxb-look__opt input{position:absolute;opacity:0}
.flxb-look__opt img,.flxb-look__ph{display:block;width:100%;aspect-ratio:16/10;object-fit:cover;object-position:top;border-radius:6px;border:1px solid #f0f0f1;background:#f6f7f7}
.flxb-look__opt span{color:#646970;font-size:12.5px;line-height:1.45}
.flxb-look__order{margin:6px 0 0;padding:0;list-style:none;max-width:420px;counter-reset:o}
.flxb-look__order li{display:flex;align-items:center;gap:8px;padding:8px 10px;border:1px solid #dcdcde;border-radius:8px;margin-bottom:6px;background:#fff}
.flxb-look__order li span{flex:1}
.flxb-look__order li span::before{counter-increment:o;content:counter(o) ". ";color:#646970}
.flxb-look__color{display:flex;gap:8px;align-items:center}
.flxb-look__grid--text{margin:6px 0 4px}
.flxb-look .flxb-check{display:flex;gap:8px;align-items:center;margin:6px 0}
.flxb-look__grid--text strong{font-size:13.5px}
.flxb-look__fees{border-collapse:collapse;margin:10px 0 4px;min-width:min(520px,100%)}
.flxb-look__fees th,.flxb-look__fees td{text-align:left;padding:7px 10px;border-bottom:1px solid #f0f0f1;font-size:13px}
.flxb-look__fees input{width:96px}
.flxb-look__fees code{font-size:11px;color:#8c8f94;background:none;padding:0}
.flxb-look__color input[type=color]{width:42px;height:36px;padding:2px;border:1px solid #dcdcde;border-radius:6px;background:#fff}
.flxb-look__color input[type=text]{width:120px}
</style>
<script>
(function(){
	document.querySelectorAll('[data-flxb-look-order]').forEach(function(ol){
		ol.addEventListener('click',function(e){
			var b=e.target.closest('button');if(!b)return;var li=b.closest('li');
			if(b.hasAttribute('data-up')&&li.previousElementSibling)li.parentNode.insertBefore(li,li.previousElementSibling);
			if(b.hasAttribute('data-down')&&li.nextElementSibling)li.parentNode.insertBefore(li.nextElementSibling,li);
			b.focus();
		});
	});
	document.querySelectorAll('[data-flxb-color]').forEach(function(c){
		var t=document.getElementById(c.getAttribute('data-flxb-color'));if(!t)return;
		c.addEventListener('input',function(){t.value=c.value.toUpperCase();});
		t.addEventListener('input',function(){if(/^#[0-9a-f]{6}$/i.test(t.value))c.value=t.value;});
	});
})();
</script>
		<?php
	}

	public static function save() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		check_admin_referer( 'flxb_look_save' );
		$in = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cleaned in clean()
		$in = is_array( $in ) ? $in : array();
		$in['share_phone'] = ! empty( $in['share_phone'] ) ? 1 : 0;
		$in['fee_on']      = ! empty( $in['fee_on'] ) ? 1 : 0;
		$in['fee_checked'] = ! empty( $in['fee_checked'] ) ? 1 : 0;
		$in['signup']      = ! empty( $in['signup'] ) ? 1 : 0;
		update_option( self::OPTION, self::clean( $in ), false );
		self::flush();
		FLXB_Admin::redirect( 'flxb-settings', array( 'tab' => 'look', 'flxb_msg' => 'saved' ) );
	}
}
