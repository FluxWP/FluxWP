<?php
/**
 * Flux Donate 2.0: bank transfer gateway for donations (gateway id `flxb_havale`).
 *
 * The order is opened On hold (pre-registration). The donor gets an order-specific reference
 * (e.g. "Fidan bağışı YT1234 Ayşe Yılmaz") and can upload the bank receipt on the site. When the
 * admin confirms the payment, the order is completed and the certificate is issued.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_WC_Havale {

	const ID = 'flxb_havale';

	public static function init() {
		add_action( 'woocommerce_init', array( __CLASS__, 'load_gateway' ) );
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'register' ) );
		add_action( 'woocommerce_thankyou_' . self::ID, array( __CLASS__, 'thankyou' ) );
		add_action( 'woocommerce_email_before_order_table', array( __CLASS__, 'email' ), 5, 3 );
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( __CLASS__, 'blocks' ) );
		add_filter( 'render_block_woocommerce/order-confirmation-status', array( __CLASS__, 'block_confirmation' ) );
	}

	public static function load_gateway() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) || class_exists( 'FLXB_WC_Gateway_Havale' ) ) {
			return;
		}
		require_once FLXB_DIR . 'includes/wc/gateways/class-flxb-wc-gateway-havale.php';
	}

	public static function register( $gateways ) {
		self::load_gateway();
		if ( class_exists( 'FLXB_WC_Gateway_Havale' ) && ! in_array( 'FLXB_WC_Gateway_Havale', (array) $gateways, true ) ) {
			$gateways[] = 'FLXB_WC_Gateway_Havale';
		}
		return $gateways;
	}

	public static function blocks( $registry ) {
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) && ! $registry->is_registered( self::ID ) ) {
			require_once FLXB_DIR . 'includes/wc/gateways/class-flxb-wc-havale-blocks.php';
			$registry->register( new FLXB_WC_Havale_Blocks() );
		}
	}

	public static function default_description() {
		return __( '\\nAfter you place the order you will see the IBAN and your own reference code. Once you have paid you can upload the receipt on the site; your certificate is sent when the payment is confirmed.\\n', 'flx-donate' );
	}

	/** Gateway settings (read even when the method is off). */
	public static function option( $key ) {
		$s   = get_option( 'woocommerce_' . self::ID . '_settings', array() );
		$def = array(
			'holder'     => '',
			'iban'       => '',
			'bank'       => '',
			'ref_format' => '',
			'ref_prefix' => 'FluxWP',
		);
		$v = is_array( $s ) && isset( $s[ $key ] ) ? $s[ $key ] : ( $def[ $key ] ?? '' );
		$v = is_string( $v ) ? trim( $v ) : $v;
		if ( in_array( $key, array( 'holder', 'iban', 'bank' ), true ) && '' === trim( (string) ( is_array( $s ) ? ( $s['iban'] ?? '' ) : '' ) ) ) {
			/* 2.0.1: the gateway has no IBAN of its own: use the organisation's account (flxb_bank_accounts). */
			$a = self::fallback_account();
			if ( $a && '' === (string) $v ) {
				$v = (string) ( $a[ $key ] ?? '' );
			}
		} elseif ( 'holder' === $key && '' === (string) $v ) {
			/* IBAN entered, holder empty: the holder of the organisation's account with that IBAN. */
			$a = self::fallback_account( (string) $s['iban'] );
			$v = $a ? (string) $a['holder'] : '';
		}
		return $v;
	}

	/**
	 * First account from `flxb_bank_accounts` in the shop currency (accounts without a currency count as
	 * TRY), or the one with the given IBAN, normalised to { holder, iban, bank }; filter
	 * `flxb_havale_account`. Null when there is none.
	 */
	public static function fallback_account( $want_iban = '' ) {
		$want = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $want_iban ) );
		$cur = function_exists( 'get_woocommerce_currency' ) ? strtoupper( get_woocommerce_currency() ) : 'TRY';
		$hit = null;
		foreach ( (array) flxb_bank_accounts() as $a ) {
			if ( ! is_array( $a ) ) {
				continue;
			}
			$iban = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $a['iban'] ?? '' ) ) );
			$pb   = strtoupper( trim( (string) ( $a['currency'] ?? '' ) ) );
			if ( '' === $iban || ( '' !== $want ? $iban !== $want : ( '' !== $pb ? $pb : 'TRY' ) !== $cur ) ) {
				continue;
			}
			$hit = array(
				'holder' => trim( (string) ( $a['holder'] ?? ( $a['owner'] ?? ( $a['name'] ?? '' ) ) ) ),
				'iban'   => $iban,
				'bank'   => trim( (string) ( $a['bank'] ?? ( $a['label'] ?? '' ) ) ),
			);
			break;
		}
		$hit = apply_filters( 'flxb_havale_account', $hit, $want );
		return is_array( $hit ) && ! empty( $hit['iban'] ) ? $hit : null;
	}

	/** Does any donation line of the order get a certificate? */
	public static function order_has_certificate( WC_Order $order ) {
		foreach ( FLXB_WC_Cart::order_lines( $order ) as $item ) {
			if ( '' !== (string) $item->get_meta( 'flxb_cert' ) ) {
				return true;
			}
		}
		return false;
	}

	public static function format_iban( $iban ) {
		$c = strtoupper( preg_replace( '/\s+/', '', (string) $iban ) );
		return trim( chunk_split( $c, 4, ' ' ) );
	}

	/** The order's transfer reference (made once, kept on the order). */
	public static function reference( WC_Order $order ) {
		$ref = (string) $order->get_meta( '_flxb_ref' );
		if ( '' !== $ref ) {
			return $ref;
		}
		$fmt  = self::option( 'ref_format' );
		$fmt  = '' !== $fmt ? $fmt : '{tur} {kod} {ad}';
		$code = preg_replace( '/[^A-Za-z0-9]/', '', (string) self::option( 'ref_prefix' ) ) . $order->get_order_number();
		$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$ref  = strtr(
			$fmt,
			array(
				'{tur}'     => FLXB_WC_Cart::order_units( $order ) > 0 ? __( 'Sapling donation', 'flx-donate' ) : __( 'Donation', 'flx-donate' ),
				'{kod}'     => $code,
				'{siparis}' => $order->get_order_number(),
				'{ad}'      => $name,
			)
		);
		$ref = trim( preg_replace( '/\s+/', ' ', $ref ) );
		$order->update_meta_data( '_flxb_ref', $ref );
		$order->update_meta_data( '_flxb_code', $code );
		$order->save_meta_data();
		return $ref;
	}

	/** Transfer details: label, value, value to copy, highlighted. */
	public static function bank_rows( WC_Order $order ) {
		$rows = array(
			array( __( 'Recipient', 'flx-donate' ), self::option( 'holder' ), self::option( 'holder' ) ),
			array( 'IBAN', self::format_iban( self::option( 'iban' ) ), strtoupper( preg_replace( '/\s+/', '', self::option( 'iban' ) ) ) ),
		);
		if ( self::option( 'bank' ) ) {
			$rows[] = array( __( 'Bank', 'flx-donate' ), self::option( 'bank' ), '' );
		}
		$rows[] = array( __( 'Amount', 'flx-donate' ), FLXB_WC::money_text( $order->get_total(), $order->get_currency() ), wc_format_localized_price( $order->get_total() ) );
		$rows[] = array( __( 'Reference', 'flx-donate' ), self::reference( $order ), self::reference( $order ), true );
		return $rows;
	}

	/** Paid by bank transfer and still waiting for the money? */
	public static function awaiting( WC_Order $order ) {
		return self::ID === $order->get_payment_method() && $order->has_status( array( 'on-hold', 'pending' ) );
	}

	/**
	 * Transfer panel: steps, copyable details, receipt upload.
	 *
	 * @param string $context thankyou | account | view
	 */
	public static function panel( WC_Order $order, $context = 'thankyou' ) {
		FLXB_WC_Form::enqueue();
		ob_start();
		?>
<section class="flxwb-havale flxwb-havale--<?php echo esc_attr( $context ); ?>" data-flxwb-order="<?php echo esc_attr( $order->get_id() ); ?>">
	<?php if ( 'account' !== $context ) : ?>
	<header class="flxwb-havale__head">
		<span class="flxwb-havale__icon"><?php echo FLXB_WC::icon( 'bank' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<div>
			<h2 class="flxwb-havale__title"><?php esc_html_e( '\\nYour pre-registration is in\\n', 'flx-donate' ); ?></h2>
			<p class="flxwb-havale__lead"><?php esc_html_e( '\\nTo complete your donation, make a bank transfer to the account below. Please write your own reference code in the description.\\n', 'flx-donate' ); ?></p>
		</div>
	</header>
	<?php endif; ?>
	<ol class="flxwb-steps">
		<li class="flxwb-step">
			<span class="flxwb-step__no">1</span>
			<div class="flxwb-step__body">
				<p class="flxwb-step__title"><?php esc_html_e( 'Make the bank transfer', 'flx-donate' ); ?></p>
				<dl class="flxwb-bank">
					<?php foreach ( self::bank_rows( $order ) as $r ) : ?>
					<div class="flxwb-bank__row<?php echo ! empty( $r[3] ) ? ' is-key' : ''; ?>">
						<dt><?php echo esc_html( $r[0] ); ?>
							<?php if ( '' !== (string) $r[2] ) : ?>
							<button type="button" class="flxwb-copy" data-flxwb-copy="<?php echo esc_attr( $r[2] ); ?>"><?php echo FLXB_WC::icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Copy', 'flx-donate' ); ?></span></button>
							<?php endif; ?>
						</dt>
						<dd><?php echo esc_html( $r[1] ); ?></dd>
					</div>
					<?php endforeach; ?>
				</dl>
				<p class="flxwb-hint"><?php esc_html_e( '\\nThe reference code lets us match your payment to your donation.\\n', 'flx-donate' ); ?></p>
			</div>
		</li>
		<li class="flxwb-step">
			<span class="flxwb-step__no">2</span>
			<div class="flxwb-step__body">
				<p class="flxwb-step__title"><?php esc_html_e( 'Upload your receipt', 'flx-donate' ); ?></p>
				<?php echo FLXB_WC_Receipts::uploader( $order ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
		</li>
		<li class="flxwb-step">
			<span class="flxwb-step__no">3</span>
			<div class="flxwb-step__body">
				<?php if ( self::order_has_certificate( $order ) ) : ?>
				<p class="flxwb-step__title"><?php esc_html_e( '[[\'Receive your certificate\\n', 'flx-donate' ); ?></p>
				<p class="flxwb-hint"><?php esc_html_e( '\\nOnce the payment is confirmed your certificate is sent by WhatsApp and e-mail; it also appears under My account › Orders.\\n', 'flx-donate' ); ?></p>
				<?php else : ?>
				<p class="flxwb-step__title"><?php esc_html_e( '\\nWe confirm your donation\\n', 'flx-donate' ); ?></p>
				<p class="flxwb-hint"><?php esc_html_e( '\\nOnce the payment is confirmed the order is completed and you get an e-mail; it also appears under My account › Orders.\\n', 'flx-donate' ); ?></p>
				<?php endif; ?>
			</div>
		</li>
	</ol>
	<?php echo FLXB_WC::support_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php if ( 'thankyou' === $context && is_user_logged_in() ) : ?>
	<p class="flxwb-havale__foot"><a href="<?php echo esc_url( wc_get_account_endpoint_url( FLXB_WC_Account::ENDPOINT ) ); ?>"><?php echo FLXB_WC::icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( '\\nMy pending donations\\n', 'flx-donate' ); ?></a></p>
	<?php endif; ?>
</section>
		<?php
		return ob_get_clean();
	}

	/* Thank-you page (classic and block confirmation call woocommerce_thankyou_{method}). */
	public static function thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && self::awaiting( $order ) && empty( $GLOBALS['flxb_ty_done'] ) ) {
			$GLOBALS['flxb_ty_done'] = true;
			echo self::panel( $order, 'thankyou' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/* E-mail: transfer details and the receipt link in the "On hold" e-mail. */
	public static function email( $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin || ! $order instanceof WC_Order || ! self::awaiting( $order ) ) {
			return;
		}
		$rows = self::bank_rows( $order );
		$link = $order->get_checkout_order_received_url();
		if ( $plain_text ) {
			echo esc_html__( 'Bank transfer details', 'flx-donate' ) . "\n";
			foreach ( $rows as $r ) {
				echo esc_html( $r[0] . ': ' . $r[1] ) . "\n";
			}
			echo "\n" . esc_html__( 'After paying, upload your receipt:', 'flx-donate' ) . ' ' . esc_url_raw( $link ) . "\n\n";
			return;
		}
		echo '<h2>' . esc_html__( 'Bank transfer details', 'flx-donate' ) . '</h2><table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;margin-bottom:16px" border="1">';
		foreach ( $rows as $r ) {
			echo '<tr><th style="text-align:left;width:30%">' . esc_html( $r[0] ) . '</th><td>' . ( ! empty( $r[3] ) ? '<strong>' . esc_html( $r[1] ) . '</strong>' : esc_html( $r[1] ) ) . '</td></tr>';
		}
		echo '</table><p>' . esc_html__( 'Please write the reference above in the transfer description. After paying, upload your receipt:', 'flx-donate' ) . ' <a href="' . esc_url( $link ) . '">' . esc_html__( 'Upload receipt', 'flx-donate' ) . '</a></p>';
	}

	/**
	 * Block order confirmation: the transfer panel or the certificates right under the status
	 * message (the classic page uses the woocommerce_thankyou hooks).
	 */
	public static function block_confirmation( $html ) {
		if ( ! empty( $GLOBALS['flxb_ty_done'] ) || ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return $html;
		}
		$order = wc_get_order( absint( get_query_var( 'order-received' ) ) );
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) || ( ! FLXB_WC_Cart::order_lines( $order ) && self::ID !== $order->get_payment_method() ) ) {
			return $html;
		}
		$extra = '';
		if ( self::awaiting( $order ) ) {
			$extra = self::panel( $order, 'thankyou' );
		} elseif ( FLXB_WC_Certificates::order_paid( $order ) && FLXB_WC_Cart::order_lines( $order ) ) {
			FLXB_WC_Certificates::issue( $order );
			$extra = FLXB_WC_Certificates::cards_html( $order, 'thankyou' );
			if ( '' === $extra && FLXB_WC_Certificates::expects( $order ) ) {
				$extra = FLXB_WC_Certificates::pending_html();
			}
		}
		if ( '' !== $extra ) {
			$GLOBALS['flxb_ty_done'] = true;
		}
		return $html . $extra;
	}
}
