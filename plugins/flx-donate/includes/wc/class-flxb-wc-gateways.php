<?php
/**
 * Flux Donate 2.13.0: any WooCommerce payment plugin (virtual POS) on the donation checkout.
 *
 *  - Every gateway WooCommerce knows is listed on Donations › Settings › WooCommerce with what it can
 *    do on our checkout: classic / block checkout, payment links without a cart, no address, refunds
 *    from the site, saved cards, installments, monthly donations, server-side payment check.
 *  - Capabilities come from WooCommerce itself (supports(), process_refund, blocks registry) and from
 *    adapters. An adapter is a small description (and fixes) for one plugin; Flux Donate ships one for
 *    EsnekPOS. Others register with the `flxb_gateway_adapters` filter:
 *
 *        add_filter( 'flxb_gateway_adapters', function ( $a ) {
 *            $a['my_pos'] = array(
 *                'label' => 'My POS',
 *                'logo'  => 'https://…/logo.svg',        // footer payment logos (theme FluxWP)
 *                'caps'  => array( 'installments' => array( 'page', 'On the provider\'s page' ) ),
 *            );
 *            return $a;
 *        } );
 *
 *    A cap is array( state, note ): state yes | no | page (done on the provider's page) | flux
 *    (Flux Donate does it) | unknown.
 *  - One card method (setting card_method, 2.13.0): when a third-party card POS is enabled (e.g.
 *    EsnekPOS) it is the only card method for one-time donations; iyzico (ours and the official
 *    plugin) and other card POSes are hidden, and the theme's iyzico logo band is no longer a
 *    default logo. Monthly lines still use Flux Donate iyzico (card storage). card_method: auto |
 *    iyzico | <gateway id> | all.
 *  - Block checkout: a gateway without its own blocks integration that takes the card on its own page
 *    (has_fields = false) is added to the checkout block by Flux Donate (setting block_fallback).
 *    One with its own card fields cannot be shown there; the table says so.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Gateways {

	/** Gateways added to the checkout block by us (id => title). */
	private static $fallback = array();

	public static function init() {
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( __CLASS__, 'blocks' ), 99 );
		add_filter( 'flx_pay_logos_builtin', array( __CLASS__, 'pay_logos' ), 20 );
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'one_card' ), 60 );
	}

	/** Card methods: iyzico (ours, official) and third-party POSes. */
	private static function is_card( $id ) {
		return in_array( $id, array( FLXB_WC_Iyzico::ID, FLXB_WC_Iyzico::OFFICIAL ), true ) || ( ! self::own( $id ) && ! self::offline( $id ) );
	}

	/**
	 * The card method donors see: 'iyzico', a gateway id, or 'all' (every enabled card method).
	 * Automatic: the first enabled third-party POS, else iyzico.
	 */
	public static function card_method() {
		$set   = (string) FLXB_WC::setting( 'card_method' );
		$third = self::third_party_enabled();
		if ( 'all' === $set || 'iyzico' === $set ) {
			return $set;
		}
		if ( '' !== $set && 'auto' !== $set && isset( $third[ $set ] ) ) {
			return $set;
		}
		return $third ? (string) array_key_first( $third ) : 'iyzico';
	}

	/** One card method at the checkout (one-time donations). */
	public static function one_card( $gateways ) {
		if ( ! is_array( $gateways ) || ( is_admin() && ! wp_doing_ajax() ) || FLXB_WC_Iyzico::context_monthly() ) {
			return $gateways;
		}
		$keep = self::card_method();
		if ( 'all' === $keep ) {
			return $gateways;
		}
		global $wp;
		$own = '';
		if ( ! empty( $wp->query_vars['order-pay'] ) ) {
			$o   = wc_get_order( absint( $wp->query_vars['order-pay'] ) );
			$own = $o ? (string) $o->get_payment_method() : ''; // an existing order keeps its own method
		}
		foreach ( array_keys( $gateways ) as $id ) {
			if ( ! self::is_card( $id ) || $id === $own ) {
				continue;
			}
			$iyz = in_array( $id, array( FLXB_WC_Iyzico::ID, FLXB_WC_Iyzico::OFFICIAL ), true );
			if ( 'iyzico' === $keep ? ! $iyz : $id !== $keep ) {
				unset( $gateways[ $id ] );
			}
		}
		return $gateways;
	}

	/** Is an enabled card method hidden at the checkout by the one-card rule? */
	public static function hidden_by_card_method( $id ) {
		$keep = self::card_method();
		if ( 'all' === $keep || ! self::is_card( $id ) ) {
			return false;
		}
		$iyz = in_array( $id, array( FLXB_WC_Iyzico::ID, FLXB_WC_Iyzico::OFFICIAL ), true );
		return 'iyzico' === $keep ? ! $iyz : $id !== $keep;
	}

	/** Capability keys and their labels (table columns). */
	public static function cap_labels() {
		return array(
			'blocks'       => __( '\\nBlock checkout\\n', 'flx-donate' ),
			'no_cart'      => __( '\\nPayment link\\n', 'flx-donate' ),
			'no_address'   => __( '\\nNo address\\n', 'flx-donate' ),
			'verified'     => __( '\\nServer check\\n', 'flx-donate' ),
			'installments' => __( '\\nInstallments\\n', 'flx-donate' ),
			'tokenization' => __( '\\nSaved cards\\n', 'flx-donate' ),
			'refunds'      => __( '\\nRefund from site\\n', 'flx-donate' ),
			'monthly'      => __( 'Monthly', 'flx-donate' ),
		);
	}

	/** Column help (title attribute and the legend). */
	public static function cap_help() {
		return array(
			'blocks'       => __( '\\nShown on a checkout page built with the Checkout block. The classic checkout ([woocommerce_checkout]) shows every method.\\n', 'flx-donate' ),
			'no_cart'      => __( '\\nPays an existing order from its payment link (call center, "pay again"), without a cart.\\n', 'flx-donate' ),
			'no_address'   => __( '[["Works on our checkout that asks no address: Flux Donate fills the organisation\'s city and address in.\\n', 'flx-donate' ),
			'verified'     => __( '\\nThe payment is confirmed with the provider from the server before the order is marked paid.\\n', 'flx-donate' ),
			'installments' => __( '\\nThe donor can pay in installments.\\n', 'flx-donate' ),
			'tokenization' => __( '\\nThe donor can save a card for the next donation.\\n', 'flx-donate' ),
			'refunds'      => __( '\\nThe Refund button on the order sends the refund to the provider.\\n', 'flx-donate' ),
			'monthly'      => __( '\\nMonthly donations charge a stored card every month. Only Flux Donate iyzico does this.\\n', 'flx-donate' ),
		);
	}

	/** @return array<string,array> gateway id => adapter. */
	public static function adapters() {
		return (array) apply_filters( 'flxb_gateway_adapters', array() );
	}

	/** @return WC_Payment_Gateway[] */
	public static function all() {
		return function_exists( 'WC' ) && WC()->payment_gateways() ? (array) WC()->payment_gateways()->payment_gateways() : array();
	}

	/** Our own methods (bank transfer, iyzico): not "third-party". */
	public static function own( $id ) {
		return in_array( $id, array( FLXB_WC_Havale::ID, FLXB_WC_Iyzico::ID ), true );
	}

	/** WooCommerce's offline methods (bank transfer, cheque, cash on delivery). */
	public static function offline( $id ) {
		return in_array( $id, array( 'bacs', 'cheque', 'cod' ), true );
	}

	/** Enabled third-party card / POS methods (not ours, not offline), id => title. */
	public static function third_party_enabled() {
		$out = array();
		foreach ( self::all() as $id => $gw ) {
			if ( 'yes' === $gw->enabled && ! self::own( $id ) && ! self::offline( $id ) && FLXB_WC_Iyzico::OFFICIAL !== $id ) {
				$out[ $id ] = wp_strip_all_tags( (string) $gw->get_method_title() );
			}
		}
		return $out;
	}

	/** Does the gateway class implement a method itself (not WC_Payment_Gateway's empty default)? */
	private static function overrides( $gw, $method ) {
		if ( ! method_exists( $gw, $method ) ) {
			return false;
		}
		try {
			$m = new ReflectionMethod( $gw, $method );
			return ! in_array( $m->getDeclaringClass()->getName(), array( 'WC_Payment_Gateway', 'WC_Settings_API' ), true );
		} catch ( ReflectionException $e ) {
			return false;
		}
	}

	/** Ids registered in the checkout block (by their plugin or by us). */
	public static function block_ids() {
		$ids = array();
		try {
			if ( class_exists( '\Automattic\WooCommerce\Blocks\Package' ) ) {
				$reg = \Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry::class );
				$ids = array_keys( (array) $reg->get_all_registered() );
			}
		} catch ( Throwable $e ) {
			$ids = array();
		}
		return $ids;
	}

	/** Gateways the checkout block gets from Flux Donate. */
	public static function fallback_ids() {
		return array_keys( self::$fallback );
	}

	/** Can the gateway be added to the checkout block by us? */
	public static function can_fallback( $gw ) {
		return $gw instanceof WC_Payment_Gateway && ! $gw->has_fields && ! self::own( $gw->id );
	}

	/** Add the remaining redirect gateways to the checkout block. */
	public static function blocks( $registry ) {
		if ( ! FLXB_WC::setting( 'block_fallback' ) || ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}
		require_once FLXB_DIR . 'includes/wc/gateways/class-flxb-wc-generic-blocks.php';
		foreach ( self::all() as $id => $gw ) {
			if ( 'yes' !== $gw->enabled || ! self::can_fallback( $gw ) || $registry->is_registered( $id ) ) {
				continue;
			}
			$registry->register( new FLXB_WC_Generic_Blocks( $gw ) );
			self::$fallback[ $id ] = $gw->get_title();
		}
	}

	/**
	 * What a gateway can do on our checkout.
	 *
	 * @return array<string,array{0:string,1:string}> cap => [ state, note ].
	 */
	public static function caps( WC_Payment_Gateway $gw, $block_ids = null ) {
		$id        = $gw->id;
		$block_ids = is_array( $block_ids ) ? $block_ids : self::block_ids();
		$ours      = in_array( $id, self::fallback_ids(), true );
		$caps      = array();

		if ( in_array( $id, $block_ids, true ) ) {
			$caps['blocks'] = $ours ? array( 'flux', __( '\\nAdded by Flux Donate (the plugin has no block support).\\n', 'flx-donate' ) ) : array( 'yes', '' );
		} elseif ( self::can_fallback( $gw ) ) {
			$caps['blocks'] = FLXB_WC::setting( 'block_fallback' )
				? array( 'flux', __( '\\nAdded by Flux Donate once the method is enabled.\\n', 'flx-donate' ) )
				: array( 'no', __( '\\nTurn on "Show other payment plugins on the checkout block".\', \'en\']]', 'flx-donate' ) );
		} else {
			$caps['blocks'] = array( 'no', __( '\\nThe plugin shows its own card fields only on the classic checkout. Use the classic checkout page or ask the provider for block support.\\n', 'flx-donate' ) );
		}
		$caps['no_cart']      = array( 'unknown', __( '\\nMost plugins work; try a payment link once.\\n', 'flx-donate' ) );
		$caps['no_address']   = array( 'flux', '' );
		$caps['verified']     = array( 'unknown', __( '\\nDepends on the plugin; ask the provider.\\n', 'flx-donate' ) );
		$caps['installments'] = $gw->has_fields
			? array( 'unknown', '' )
			: array( 'page', __( '\\nIf the provider offers it, on its payment page.\\n', 'flx-donate' ) );
		$caps['tokenization'] = $gw->supports( 'tokenization' ) ? array( 'yes', '' ) : array( 'no', '' );
		$caps['refunds']      = $gw->supports( 'refunds' ) && self::overrides( $gw, 'process_refund' )
			? array( 'yes', '' )
			: array( 'no', __( '\\nRefund in the provider\'s panel, then mark the order refunded.\\n', 'flx-donate' ) );
		$caps['monthly']      = array( 'no', '' );

		if ( FLXB_WC_Iyzico::ID === $id ) {
			$caps = array_merge(
				$caps,
				array(
					'no_cart'      => array( 'yes', '' ),
					'verified'     => array( 'yes', '' ),
					'installments' => FLXB_WC_Iyzico_Card::on() ? array( 'yes', '' ) : array( 'page', '' ),
					'tokenization' => FLXB_WC_Iyzico_Card::on() ? array( 'yes', '' ) : array( 'no', '' ),
					'monthly'      => array( 'yes', '' ),
				)
			);
		} elseif ( FLXB_WC_Havale::ID === $id || self::offline( $id ) ) {
			$caps = array_merge(
				$caps,
				array(
					'no_cart'      => array( 'yes', '' ),
					'verified'     => array( 'page', __( '[[\'Confirmed by hand after the receipt.\\n', 'flx-donate' ) ),
					'installments' => array( 'no', '' ),
				)
			);
		}

		$a = self::adapters()[ $id ] ?? null;
		if ( is_array( $a ) && ! empty( $a['caps'] ) ) {
			foreach ( (array) $a['caps'] as $k => $v ) {
				if ( isset( $caps[ $k ] ) && 'blocks' !== $k ) {
					$caps[ $k ] = array( (string) ( is_array( $v ) ? ( $v[0] ?? 'unknown' ) : $v ), (string) ( is_array( $v ) ? ( $v[1] ?? '' ) : '' ) );
				}
			}
		}
		return (array) apply_filters( 'flxb_gateway_caps', $caps, $gw );
	}

	/** Problems worth a warning for an enabled gateway (system check). */
	public static function problems( WC_Payment_Gateway $gw, array $caps, $block_checkout ) {
		$out = array();
		if ( $block_checkout && 'no' === $caps['blocks'][0] ) {
			$out[] = __( '\\nnot shown on the block checkout page\\n', 'flx-donate' );
		}
		return $out;
	}

	/** Is the WooCommerce checkout page built with the Checkout block? */
	public static function block_checkout() {
		$id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'checkout' ) : 0;
		return $id > 0 && has_block( 'woocommerce/checkout', $id );
	}

	/** Footer payment logos: adapter logos (theme FluxWP, Tema Ayarları). */
	public static function pay_logos( $list ) {
		$gws = self::all();
		$cm  = self::card_method();
		if ( isset( $list['iyzico'] ) && ! in_array( $cm, array( 'iyzico', 'all' ), true ) ) {
			$list['iyzico']['default'] = false; // another POS takes the cards: no iyzico band by default
		}
		foreach ( self::adapters() as $id => $a ) {
			if ( ! isset( $gws[ $id ] ) || empty( $a['logo'] ) || isset( $list[ 'gw-' . $id ] ) ) {
				continue;
			}
			$list[ 'gw-' . $id ] = array(
				'label'   => (string) ( $a['label'] ?? $gws[ $id ]->get_method_title() ),
				'url'     => (string) $a['logo'],
				'default' => 'yes' === $gws[ $id ]->enabled && ( 'all' === $cm || $id === $cm ),
				'chip'    => true,
			);
		}
		return $list;
	}

	/* ---------- Settings tab ---------- */

	private static function state_html( array $c ) {
		$map = array(
			'yes'     => array( '✓', 'ok', __( 'Yes', 'flx-donate' ) ),
			'flux'    => array( '✓', 'ok', __( '\\nYes, by Flux Donate\\n', 'flx-donate' ) ),
			'page'    => array( '↗', 'recurring', __( '\\nOn the provider\\\'s page\\n', 'flx-donate' ) ),
			'no'      => array( '✕', 'failed', __( 'No', 'flx-donate' ) ),
			'unknown' => array( '?', 'pending', __( 'Unknown', 'flx-donate' ) ),
		);
		$m   = $map[ $c[0] ] ?? $map['unknown'];
		$tip = $m[2] . ( '' !== $c[1] ? ': ' . $c[1] : '' );
		return '<span class="flxb-pill flxb-pill--' . esc_attr( $m[1] ) . ' flxb-gwcap" title="' . esc_attr( $tip ) . '"><span aria-hidden="true">' . esc_html( $m[0] ) . '</span><span class="screen-reader-text">' . esc_html( $tip ) . '</span></span>';
	}

	/** The "Payment methods" card. */
	public static function render() {
		$gws    = self::all();
		$labels = self::cap_labels();
		$help   = self::cap_help();
		$bids   = self::block_ids();
		$block  = self::block_checkout();
		echo '<section class="flxb-card flxb-gw"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Payment methods', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( '\\nEvery WooCommerce payment plugin (virtual POS) works on the donation checkout: install it, enter its settings in WooCommerce › Settings › Payments and enable it. This table shows what each one can do here.\\n', 'flx-donate' ) . '</p></div>';
		echo '<div class="flxb-gw__links"><a class="flxb-btn flxb-btn--sm" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '">' . esc_html__( '\\nWooCommerce payments\\n', 'flx-donate' ) . '</a>';
		if ( function_exists( 'flx_pay_logos_admin' ) ) {
			echo ' <a class="flxb-btn flxb-btn--sm" href="' . esc_url( admin_url( 'themes.php?page=flx-ayarlar#flx-odeme-logolari' ) ) . '">' . esc_html__( '\\nPayment logos on the site\\n', 'flx-donate' ) . '</a>';
		}
		echo '</div></div>';
		/* translators: %s: classic or block */
		echo '<p class="flxb-help">' . esc_html( sprintf( __( '\\nCheckout page:%s.\\n', 'flx-donate' ), $block ? __( '\\nCheckout block\\n', 'flx-donate' ) : __( '\\nclassic\\n', 'flx-donate' ) ) ) . '</p>';
		echo '<div class="flxb-tablewrap"><table class="flxb-table flxb-gw__table"><thead><tr><th scope="col">' . esc_html__( 'Method', 'flx-donate' ) . '</th>';
		foreach ( $labels as $k => $l ) {
			echo '<th scope="col" title="' . esc_attr( $help[ $k ] ) . '">' . esc_html( $l ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $gws as $id => $gw ) {
			$caps = self::caps( $gw, $bids );
			$on   = 'yes' === $gw->enabled;
			$name = wp_strip_all_tags( (string) ( $gw->get_method_title() ? $gw->get_method_title() : $gw->get_title() ) );
			echo '<tr class="' . ( $on ? '' : 'is-off' ) . '"><th scope="row"><a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $id ) ) . '">' . esc_html( $name ) . '</a> <span class="flxb-pill flxb-pill--' . ( $on ? 'ok' : 'muted' ) . '">' . esc_html( $on ? __( 'On', 'flx-donate' ) : __( 'Off', 'flx-donate' ) ) . '</span>';
			if ( $on && self::hidden_by_card_method( $id ) ) {
				echo ' <span class="flxb-pill flxb-pill--pending" title="' . esc_attr__( '\\nOnly one card method is shown to donors. Change it under Cart and checkout › Card payment method.\\n', 'flx-donate' ) . '">' . esc_html__( '\\nHidden at checkout\\n', 'flx-donate' ) . '</span>';
			}
			$a = self::adapters()[ $id ] ?? null;
			if ( is_array( $a ) && ! empty( $a['note'] ) ) {
				echo '<br><small>' . esc_html( (string) $a['note'] ) . '</small>';
			}
			echo '</th>';
			foreach ( array_keys( $labels ) as $k ) {
				echo '<td data-label="' . esc_attr( $labels[ $k ] ) . '">' . self::state_html( $caps[ $k ] ?? array( 'unknown', '' ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		echo '<p class="flxb-help flxb-gw__legend">' . self::state_html( array( 'yes', '' ) ) . ' ' . esc_html__( 'yes', 'flx-donate' ) . ' &nbsp; ' . self::state_html( array( 'page', '' ) ) . ' ' . esc_html__( '\\non the provider\'s page\\n', 'flx-donate' ) . ' &nbsp; ' . self::state_html( array( 'no', '' ) ) . ' ' . esc_html__( 'no', 'flx-donate' ) . ' &nbsp; ' . self::state_html( array( 'unknown', '' ) ) . ' ' . esc_html__( '\\nunknown, test it\\n', 'flx-donate' ) . '. ' . esc_html__( '\\nPoint at a mark for details.\\n', 'flx-donate' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</section>';
	}
}
