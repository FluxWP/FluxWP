<?php
/**
 * Havale/EFT (gateway `flxcm_bank`): sipariş Beklemede açılır, müşteri siparişe özel referans koduyla
 * ödeme yapar ve dekontu sitede yükler; yönetici "Ödemeyi onayla" deyince sipariş İşleniyor'a geçer.
 * Flux Donate'in bağış havalesinden genelleştirildi.
 *
 * Flux Donate kuruluyken onun havale yöntemi açıksa bu yöntem varsayılan olarak gizlenir (iki ayrı
 * "Havale" seçeneği görünmesin); Ayarlar › Havale/EFT'den değişir.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Bank {

	const ID = 'flxcm_bank';

	public static function init() {
		add_action( 'woocommerce_init', array( __CLASS__, 'load_gateway' ) );
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'register' ) );
		add_action( 'woocommerce_thankyou_' . self::ID, array( __CLASS__, 'thankyou' ) );
		add_action( 'woocommerce_view_order', array( __CLASS__, 'view_order' ), 5 );
		add_action( 'woocommerce_email_before_order_table', array( __CLASS__, 'email' ), 5, 3 );
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( __CLASS__, 'blocks' ) );
		add_filter( 'render_block_woocommerce/order-confirmation-status', array( __CLASS__, 'block_confirmation' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ) );
		add_action( 'admin_post_flxcm_confirm', array( __CLASS__, 'confirm' ) );
		add_filter( 'woocommerce_order_actions', array( __CLASS__, 'order_action' ), 10, 2 );
		add_action( 'woocommerce_order_action_flxcm_confirm', array( __CLASS__, 'confirm_action' ) );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'column' ) );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
		add_action( 'flxcm_daily', array( __CLASS__, 'cancel_stale' ) );
	}

	public static function load_gateway() {
		if ( class_exists( 'WC_Payment_Gateway' ) && ! class_exists( 'FLXCM_Gateway_Bank' ) ) {
			require_once FLXCM_DIR . 'includes/gateways/class-flxcm-gateway-bank.php';
		}
	}

	public static function register( $gateways ) {
		self::load_gateway();
		if ( class_exists( 'FLXCM_Gateway_Bank' ) && ! in_array( 'FLXCM_Gateway_Bank', (array) $gateways, true ) ) {
			$gateways[] = 'FLXCM_Gateway_Bank';
		}
		return $gateways;
	}

	public static function blocks( $registry ) {
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) && ! $registry->is_registered( self::ID ) ) {
			require_once FLXCM_DIR . 'includes/gateways/class-flxcm-bank-blocks.php';
			$registry->register( new FLXCM_Bank_Blocks() );
		}
	}

	/** Flux Donate'in havalesi açık ve "ona bırak" seçili mi? */
	public static function deferred() {
		if ( ! flxcm_setting( 'defer_to_donate' ) || ! class_exists( 'FLXB_WC_Havale' ) ) {
			return false;
		}
		$s = get_option( 'woocommerce_flxb_havale_settings', array() );
		return 'no' !== ( is_array( $s ) ? ( $s['enabled'] ?? 'yes' ) : 'yes' );
	}

	public static function default_description() {
		return __( 'After you place the order you will see the IBAN and your own reference code. Once you have paid you can upload the receipt on the site; your order is prepared when the payment is confirmed.', 'flx-commerce' );
	}

	/** Yöntem ayarı; hesap bilgisi boşsa WooCommerce "Doğrudan banka havalesi"nin ilk hesabından gelir. */
	public static function option( $key ) {
		$s   = get_option( 'woocommerce_' . self::ID . '_settings', array() );
		$s   = is_array( $s ) ? $s : array();
		$def = array(
			'holder'     => '',
			'iban'       => '',
			'bank'       => '',
			'ref_format' => '',
			'ref_prefix' => 'SP',
		);
		$v = isset( $s[ $key ] ) ? trim( (string) $s[ $key ] ) : ( $def[ $key ] ?? '' );
		if ( in_array( $key, array( 'holder', 'iban', 'bank' ), true ) && '' === trim( (string) ( $s['iban'] ?? '' ) ) ) {
			$a = self::bacs_account();
			if ( $a && '' === $v ) {
				$v = (string) $a[ $key ];
			}
		}
		return $v;
	}

	/** WooCommerce BACS'ın ilk hesabı { holder, iban, bank } ya da null. Filtre `flxcm_bank_account`. */
	public static function bacs_account() {
		$hit = null;
		foreach ( (array) get_option( 'woocommerce_bacs_accounts', array() ) as $a ) {
			if ( is_array( $a ) && ! empty( $a['iban'] ) ) {
				$hit = array(
					'holder' => trim( (string) ( $a['account_name'] ?? '' ) ),
					'iban'   => strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $a['iban'] ) ),
					'bank'   => trim( (string) ( $a['bank_name'] ?? '' ) ),
				);
				break;
			}
		}
		$hit = apply_filters( 'flxcm_bank_account', $hit );
		return is_array( $hit ) && ! empty( $hit['iban'] ) ? $hit : null;
	}

	public static function format_iban( $iban ) {
		return trim( chunk_split( strtoupper( preg_replace( '/\s+/', '', (string) $iban ) ), 4, ' ' ) );
	}

	/** Siparişin havale açıklaması (bir kez üretilir, siparişte kalır). */
	public static function reference( WC_Order $order ) {
		$ref = (string) $order->get_meta( '_flxcm_ref' );
		if ( '' !== $ref ) {
			return $ref;
		}
		$fmt  = self::option( 'ref_format' );
		$fmt  = '' !== $fmt ? $fmt : '{kod} {ad}';
		$code = preg_replace( '/[^A-Za-z0-9]/', '', (string) self::option( 'ref_prefix' ) ) . $order->get_order_number();
		$ref  = strtr(
			$fmt,
			array(
				'{kod}'     => $code,
				'{siparis}' => $order->get_order_number(),
				'{ad}'      => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'{site}'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			)
		);
		$ref = trim( preg_replace( '/\s+/', ' ', $ref ) );
		$order->update_meta_data( '_flxcm_ref', $ref );
		$order->update_meta_data( '_flxcm_code', $code );
		$order->save_meta_data();
		return $ref;
	}

	/** Havale bilgileri: [ etiket, değer, kopyalanacak, vurgulu ]. */
	public static function rows( WC_Order $order ) {
		$iban = self::option( 'iban' );
		$rows = array(
			array( __( 'Recipient', 'flx-commerce' ), self::option( 'holder' ), self::option( 'holder' ) ),
			array( 'IBAN', self::format_iban( $iban ), strtoupper( preg_replace( '/\s+/', '', $iban ) ) ),
		);
		if ( self::option( 'bank' ) ) {
			$rows[] = array( __( 'Bank', 'flx-commerce' ), self::option( 'bank' ), '' );
		}
		$rows[] = array( __( 'Amount', 'flx-commerce' ), flxcm_money( $order->get_total(), $order->get_currency() ), wc_format_localized_price( $order->get_total() ) );
		$rows[] = array( __( 'Reference', 'flx-commerce' ), self::reference( $order ), self::reference( $order ), true );
		return $rows;
	}

	/** Bu yöntemle ödenmiş ve para bekleniyor mu? */
	public static function awaiting( WC_Order $order ) {
		return self::ID === $order->get_payment_method() && $order->has_status( array( 'on-hold', 'pending' ) );
	}

	public static function enqueue() {
		wp_enqueue_style( 'flxcm-front', FLXCM_URL . 'assets/front.css', array(), FLXCM_VERSION );
		wp_enqueue_script( 'flxcm-front', FLXCM_URL . 'assets/front.js', array(), FLXCM_VERSION, true );
		wp_localize_script(
			'flxcm-front',
			'flxcmFront',
			array(
				'rest'   => esc_url_raw( rest_url( 'flx-commerce/v1/receipt' ) ),
				'copied' => __( 'Copied', 'flx-commerce' ),
				'busy'   => __( 'Uploading…', 'flx-commerce' ),
				'error'  => __( 'The receipt could not be uploaded. Please try again.', 'flx-commerce' ),
				'ok'     => __( 'We have your receipt. We will let you know once the payment is confirmed.', 'flx-commerce' ),
			)
		);
	}

	/**
	 * Havale paneli: adımlar, kopyalanabilir bilgiler, dekont yükleme.
	 *
	 * @param string $context thankyou | view
	 */
	public static function panel( WC_Order $order, $context = 'thankyou' ) {
		self::enqueue();
		ob_start();
		?>
<section class="flxcm-bank flxcm-bank--<?php echo esc_attr( $context ); ?>">
	<header class="flxcm-bank__head">
		<span class="flxcm-bank__icon"><?php echo flxcm_icon( 'bank', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
		<div>
			<h2 class="flxcm-bank__title"><?php esc_html_e( 'Your order is in, we are waiting for your payment', 'flx-commerce' ); ?></h2>
			<p class="flxcm-bank__lead"><?php esc_html_e( 'Make a bank transfer to the account below and write your own reference code in the description.', 'flx-commerce' ); ?></p>
		</div>
	</header>
	<ol class="flxcm-steps">
		<li class="flxcm-step">
			<span class="flxcm-step__no">1</span>
			<div class="flxcm-step__body">
				<p class="flxcm-step__title"><?php esc_html_e( 'Make the bank transfer', 'flx-commerce' ); ?></p>
				<dl class="flxcm-rows">
					<?php foreach ( self::rows( $order ) as $r ) : ?>
					<div class="flxcm-row<?php echo ! empty( $r[3] ) ? ' is-key' : ''; ?>">
						<dt><?php echo esc_html( $r[0] ); ?></dt>
						<dd><span><?php echo esc_html( $r[1] ); ?></span>
							<?php if ( '' !== (string) $r[2] ) : ?>
							<button type="button" class="flxcm-copy" data-flxcm-copy="<?php echo esc_attr( $r[2] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: field label */ __( 'Copy %s', 'flx-commerce' ), $r[0] ) ); ?>"><?php echo flxcm_icon( 'copy', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Copy', 'flx-commerce' ); ?></span></button>
							<?php endif; ?>
						</dd>
					</div>
					<?php endforeach; ?>
				</dl>
				<p class="flxcm-hint"><?php esc_html_e( 'The reference code lets us match your payment to your order.', 'flx-commerce' ); ?></p>
			</div>
		</li>
		<li class="flxcm-step">
			<span class="flxcm-step__no">2</span>
			<div class="flxcm-step__body">
				<p class="flxcm-step__title"><?php esc_html_e( 'Upload your receipt', 'flx-commerce' ); ?></p>
				<?php echo FLXCM_Receipts::uploader( $order ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
		</li>
		<li class="flxcm-step">
			<span class="flxcm-step__no">3</span>
			<div class="flxcm-step__body">
				<p class="flxcm-step__title"><?php esc_html_e( 'We prepare your order', 'flx-commerce' ); ?></p>
				<p class="flxcm-hint"><?php esc_html_e( 'You get an e-mail once the payment is confirmed; you can also follow your order under My account › Orders.', 'flx-commerce' ); ?></p>
			</div>
		</li>
	</ol>
</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function thankyou( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && self::awaiting( $order ) && empty( $GLOBALS['flxcm_ty_done'] ) ) {
			$GLOBALS['flxcm_ty_done'] = true;
			echo self::panel( $order, 'thankyou' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/** Hesabım › Siparişler › sipariş: ödeme bekliyorsa panel. */
	public static function view_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && self::awaiting( $order ) ) {
			echo self::panel( $order, 'view' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/** Blok sipariş onay sayfası: durum mesajının hemen altına panel. */
	public static function block_confirmation( $html ) {
		if ( ! empty( $GLOBALS['flxcm_ty_done'] ) || ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return $html;
		}
		$order = wc_get_order( absint( get_query_var( 'order-received' ) ) );
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $order || ! hash_equals( $order->get_order_key(), $key ) || ! self::awaiting( $order ) ) {
			return $html;
		}
		$GLOBALS['flxcm_ty_done'] = true;
		return $html . self::panel( $order, 'thankyou' );
	}

	/** "Beklemede" e-postasına havale bilgileri ve dekont bağlantısı. */
	public static function email( $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin || ! $order instanceof WC_Order || ! self::awaiting( $order ) ) {
			return;
		}
		$rows = self::rows( $order );
		$link = $order->get_checkout_order_received_url();
		if ( $plain_text ) {
			echo esc_html__( 'Bank transfer details', 'flx-commerce' ) . "\n";
			foreach ( $rows as $r ) {
				echo esc_html( $r[0] . ': ' . $r[1] ) . "\n";
			}
			echo "\n" . esc_html__( 'After paying, upload your receipt:', 'flx-commerce' ) . ' ' . esc_url_raw( $link ) . "\n\n";
			return;
		}
		echo '<h2>' . esc_html__( 'Bank transfer details', 'flx-commerce' ) . '</h2><table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;margin-bottom:16px" border="1">';
		foreach ( $rows as $r ) {
			echo '<tr><th style="text-align:left;width:30%">' . esc_html( $r[0] ) . '</th><td>' . ( ! empty( $r[3] ) ? '<strong>' . esc_html( $r[1] ) . '</strong>' : esc_html( $r[1] ) ) . '</td></tr>';
		}
		echo '</table><p>' . esc_html__( 'Please write the reference above in the transfer description. After paying, upload your receipt:', 'flx-commerce' ) . ' <a href="' . esc_url( $link ) . '">' . esc_html__( 'Upload receipt', 'flx-commerce' ) . '</a></p>';
	}

	/* ---------- Yönetim: onay, sütun, kutu ---------- */

	public static function meta_box() {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box( 'flxcm-bank', __( 'Bank transfer', 'flx-commerce' ), array( __CLASS__, 'render_box' ), $screen, 'side', 'high' );
	}

	public static function render_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order instanceof WP_Post ? $post_or_order->ID : 0 );
		if ( ! $order || self::ID !== $order->get_payment_method() ) {
			echo '<p class="description">' . esc_html__( 'This order was not placed by bank transfer.', 'flx-commerce' ) . '</p>';
			return;
		}
		echo '<p><strong>' . esc_html__( 'Reference', 'flx-commerce' ) . ':</strong> <code>' . esc_html( self::reference( $order ) ) . '</code></p>';
		$list = FLXCM_Receipts::of( $order );
		if ( $list ) {
			echo '<ul style="margin:0 0 10px">';
			foreach ( $list as $i => $r ) {
				echo '<li>' . flxcm_icon( 'file', 14 ) . ' <a href="' . esc_url( FLXCM_Receipts::url( $order, $i, true ) ) . '" target="_blank" rel="noopener">' . esc_html( $r['name'] ) . '</a> <span class="description">' . esc_html( wp_date( 'd.m.Y H:i', (int) $r['at'] ) ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</ul>';
		} else {
			echo '<p class="description">' . esc_html__( 'No receipt uploaded yet.', 'flx-commerce' ) . '</p>';
		}
		if ( self::awaiting( $order ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=flxcm_confirm&order=' . $order->get_id() ), 'flxcm_confirm_' . $order->get_id() );
			echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Confirm payment', 'flx-commerce' ) . '</a></p>';
		} elseif ( $order->get_meta( '_flxcm_confirmed' ) ) {
			$by = get_userdata( (int) $order->get_meta( '_flxcm_confirmed_by' ) );
			/* translators: 1: date, 2: user */
			echo '<p class="description">' . esc_html( sprintf( __( 'Payment confirmed: %1$s · %2$s', 'flx-commerce' ), wp_date( 'd.m.Y H:i', (int) $order->get_meta( '_flxcm_confirmed' ) ), $by ? $by->display_name : '—' ) ) . '</p>';
		}
	}

	/** Ödemeyi onaylar: sipariş ödenmiş sayılır (sanal/indirilebilirde Tamamlandı, diğerlerinde İşleniyor). */
	public static function mark_paid( WC_Order $order ) {
		if ( ! self::awaiting( $order ) ) {
			return false;
		}
		$order->update_meta_data( '_flxcm_confirmed', time() );
		$order->update_meta_data( '_flxcm_confirmed_by', get_current_user_id() );
		$order->save_meta_data();
		$order->payment_complete( (string) $order->get_meta( '_flxcm_code' ) );
		$order->add_order_note( __( 'Bank transfer payment confirmed.', 'flx-commerce' ), 0, true );
		do_action( 'flxcm_bank_confirmed', $order );
		return true;
	}

	public static function confirm() {
		$oid = absint( $_GET['order'] ?? 0 );
		check_admin_referer( 'flxcm_confirm_' . $oid );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-commerce' ), 403 );
		}
		$order = wc_get_order( $oid );
		if ( $order ) {
			self::mark_paid( $order );
		}
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : ( $order ? $order->get_edit_order_url() : admin_url() ) );
		exit;
	}

	public static function order_action( $actions, $order = null ) {
		if ( $order instanceof WC_Order && self::awaiting( $order ) ) {
			$actions['flxcm_confirm'] = __( 'Confirm bank transfer payment', 'flx-commerce' );
		}
		return $actions;
	}

	public static function confirm_action( $order ) {
		if ( $order instanceof WC_Order ) {
			self::mark_paid( $order );
		}
	}

	public static function column( $cols ) {
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'order_status' === $k ) {
				$out['flxcm_bank'] = __( 'Transfer', 'flx-commerce' );
			}
		}
		return isset( $out['flxcm_bank'] ) ? $out : $out + array( 'flxcm_bank' => __( 'Transfer', 'flx-commerce' ) );
	}

	public static function column_value( $col, $order ) {
		if ( 'flxcm_bank' !== $col ) {
			return;
		}
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $order || self::ID !== $order->get_payment_method() ) {
			echo '<span aria-hidden="true">—</span>';
			return;
		}
		if ( self::awaiting( $order ) ) {
			$n = count( FLXCM_Receipts::of( $order ) );
			echo $n
				? '<mark class="order-status status-processing"><span>' . esc_html__( 'Receipt in', 'flx-commerce' ) . '</span></mark>'
				: '<mark class="order-status status-on-hold"><span>' . esc_html__( 'Awaiting payment', 'flx-commerce' ) . '</span></mark>';
			return;
		}
		echo '<mark class="order-status status-completed"><span>' . esc_html__( 'Confirmed', 'flx-commerce' ) . '</span></mark>';
	}

	/** Ayarda gün verildiyse o kadar gün dekontsuz bekleyen havale siparişleri iptal edilir (stok geri döner). */
	public static function cancel_stale() {
		$days = (int) flxcm_setting( 'cancel_after_days' );
		if ( $days < 1 ) {
			return;
		}
		$orders = wc_get_orders(
			array(
				'type'           => 'shop_order',
				'status'         => array( 'wc-on-hold' ),
				'payment_method' => self::ID,
				'date_created'   => '<' . ( time() - $days * DAY_IN_SECONDS ),
				'limit'          => 50,
			)
		);
		foreach ( (array) $orders as $o ) {
			if ( ! FLXCM_Receipts::of( $o ) ) {
				/* translators: %d: days */
				$o->update_status( 'cancelled', sprintf( __( 'Cancelled because no payment arrived within %d days.', 'flx-commerce' ), $days ) );
			}
		}
	}
}
