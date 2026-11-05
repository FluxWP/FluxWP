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
	}

	/** Capability keys and their labels (table columns). */
	public static function cap_labels() {
		return array(
			'blocks'       => __( 'Block checkout', 'flx-donate' ),
			'no_cart'      => __( 'Payment link', 'flx-donate' ),
			'no_address'   => __( 'No address', 'flx-donate' ),
			'verified'     => __( 'Server check', 'flx-donate' ),
			'installments' => __( 'Installments', 'flx-donate' ),
			'tokenization' => __( 'Saved cards', 'flx-donate' ),
			'refunds'      => __( 'Refund from site', 'flx-donate' ),
			'monthly'      => __( 'Monthly', 'flx-donate' ),
		);
	}

	/** Column help (title attribute and the legend). */
	public static function cap_help() {
		return array(
			'blocks'       => __( 'Shown on a checkout page built with the Checkout block. The classic checkout ([woocommerce_checkout]) shows every method.', 'flx-donate' ),
			'no_cart'      => __( 'Pays an existing order from its payment link (call center, "pay again"), without a cart.', 'flx-donate' ),
			'no_address'   => __( 'Works on our checkout that asks no address: Flux Donate fills the organisation\'s city and address in.', 'flx-donate' ),
			'verified'     => __( 'The payment is confirmed with the provider from the server before the order is marked paid.', 'flx-donate' ),
			'installments' => __( 'The donor can pay in installments.', 'flx-donate' ),
			'tokenization' => __( 'The donor can save a card for the next donation.', 'flx-donate' ),
			'refunds'      => __( 'The Refund button on the order sends the refund to the provider.', 'flx-donate' ),
			'monthly'      => __( 'Monthly donations charge a stored card every month. Only Flux Donate iyzico does this.', 'flx-donate' ),
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
			$caps['blocks'] = $ours ? array( 'flux', __( 'Added by Flux Donate (the plugin has no block support).', 'flx-donate' ) ) : array( 'yes', '' );
		} elseif ( self::can_fallback( $gw ) ) {
			$caps['blocks'] = FLXB_WC::setting( 'block_fallback' )
				? array( 'flux', __( 'Added by Flux Donate once the method is enabled.', 'flx-donate' ) )
				: array( 'no', __( 'Turn on "Show other payment plugins on the checkout block".', 'flx-donate' ) );
		} else {
			$caps['blocks'] = array( 'no', __( 'The plugin shows its own card fields only on the classic checkout. Use the classic checkout page or ask the provider for block support.', 'flx-donate' ) );
		}
		$caps['no_cart']      = array( 'unknown', __( 'Most plugins work; try a payment link once.', 'flx-donate' ) );
		$caps['no_address']   = array( 'flux', '' );
		$caps['verified']     = array( 'unknown', __( 'Depends on the plugin; ask the provider.', 'flx-donate' ) );
		$caps['installments'] = $gw->has_fields
			? array( 'unknown', '' )
			: array( 'page', __( 'If the provider offers it, on its payment page.', 'flx-donate' ) );
		$caps['tokenization'] = $gw->supports( 'tokenization' ) ? array( 'yes', '' ) : array( 'no', '' );
		$caps['refunds']      = $gw->supports( 'refunds' ) && self::overrides( $gw, 'process_refund' )
			? array( 'yes', '' )
			: array( 'no', __( 'Refund in the provider\'s panel, then mark the order refunded.', 'flx-donate' ) );
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
					'verified'     => array( 'page', __( 'Confirmed by hand after the receipt.', 'flx-donate' ) ),
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
			$out[] = __( 'not shown on the block checkout page', 'flx-donate' );
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
		foreach ( self::adapters() as $id => $a ) {
			if ( ! isset( $gws[ $id ] ) || empty( $a['logo'] ) || isset( $list[ 'gw-' . $id ] ) ) {
				continue;
			}
			$list[ 'gw-' . $id ] = array(
				'label'   => (string) ( $a['label'] ?? $gws[ $id ]->get_method_title() ),
				'url'     => (string) $a['logo'],
				'default' => 'yes' === $gws[ $id ]->enabled,
				'chip'    => true,
			);
		}
		return $list;
	}

	/* ---------- Settings tab ---------- */

	private static function state_html( array $c ) {
		$map = array(
			'yes'     => array( '✓', 'ok', __( 'Yes', 'flx-donate' ) ),
			'flux'    => array( '✓', 'ok', __( 'Yes, by Flux Donate', 'flx-donate' ) ),
			'page'    => array( '↗', 'recurring', __( 'On the provider\'s page', 'flx-donate' ) ),
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
		echo '<section class="flxb-card flxb-gw"><div class="flxb-card__head"><div><h2 class="flxb-card__title">' . esc_html__( 'Payment methods', 'flx-donate' ) . '</h2><p class="flxb-card__hint">' . esc_html__( 'Every WooCommerce payment plugin (virtual POS) works on the donation checkout: install it, enter its settings in WooCommerce › Settings › Payments and enable it. This table shows what each one can do here.', 'flx-donate' ) . '</p></div>';
		echo '<a class="flxb-btn flxb-btn--sm" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '">' . esc_html__( 'WooCommerce payments', 'flx-donate' ) . '</a></div>';
		/* translators: %s: classic or block */
		echo '<p class="flxb-help">' . esc_html( sprintf( __( 'Checkout page: %s.', 'flx-donate' ), $block ? __( 'Checkout block', 'flx-donate' ) : __( 'classic', 'flx-donate' ) ) ) . '</p>';
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
		echo '<p class="flxb-help flxb-gw__legend">' . self::state_html( array( 'yes', '' ) ) . ' ' . esc_html__( 'yes', 'flx-donate' ) . ' &nbsp; ' . self::state_html( array( 'page', '' ) ) . ' ' . esc_html__( 'on the provider\'s page', 'flx-donate' ) . ' &nbsp; ' . self::state_html( array( 'no', '' ) ) . ' ' . esc_html__( 'no', 'flx-donate' ) . ' &nbsp; ' . self::state_html( array( 'unknown', '' ) ) . ' ' . esc_html__( 'unknown, test it', 'flx-donate' ) . '. ' . esc_html__( 'Point at a mark for details.', 'flx-donate' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</section>';
	}
}
