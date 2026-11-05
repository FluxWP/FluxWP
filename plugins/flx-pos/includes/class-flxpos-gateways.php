<?php
/**
 * WooCommerce ödeme yöntemleri.
 *
 *  - flxpos_card: tek "Kart ile öde" yöntemi. Siparişin para birimine göre hesap seçilir
 *    (FLXPOS_Accounts::for_card); hesap kartı sitede alıyorsa kart formu, almıyorsa (ödeme sayfası/iFrame)
 *    yalnız açıklama gösterilir.
 *  - flxpos_<hesap>: cüzdan hesapları (Papara, Garanti Pay, GiroGate…) ayrı yöntem olarak.
 *
 * İade: siparişin "İade" düğmesi sağlayıcıya gider; tam tutar ve aynı gün ise önce iptal denenir.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Gateways {

	public static function init() {
		require_once FLXPOS_DIR . 'includes/class-flxpos-wc-gateway.php';
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'register' ) );
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( __CLASS__, 'blocks' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function register( $gateways ) {
		$gateways[] = 'FLXPOS_WC_Card';
		foreach ( array_keys( FLXPOS_Accounts::wallets() ) as $id ) {
			$gateways[] = new FLXPOS_WC_Wallet( $id );
		}
		return $gateways;
	}

	public static function blocks( $registry ) {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}
		require_once FLXPOS_DIR . 'includes/class-flxpos-blocks.php';
		$registry->register( new FLXPOS_Blocks( 'flxpos_card' ) );
		foreach ( array_keys( FLXPOS_Accounts::wallets() ) as $id ) {
			$registry->register( new FLXPOS_Blocks( 'flxpos_' . $id ) );
		}
	}

	public static function assets() {
		if ( ! function_exists( 'is_checkout' ) || ! ( is_checkout() || is_checkout_pay_page() ) ) {
			return;
		}
		wp_enqueue_style( 'flxpos-checkout', FLXPOS_URL . 'assets/checkout.css', array(), FLXPOS_VERSION );
		wp_enqueue_script( 'flxpos-checkout', FLXPOS_URL . 'assets/checkout.js', array(), FLXPOS_VERSION, true );
		wp_localize_script( 'flxpos-checkout', 'FLXPOS', self::js_config() );
	}

	/** Ön yüz ayarları (klasik ve blok). */
	public static function js_config() {
		$cur = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY';
		return array(
			'rest'   => esc_url_raw( rest_url( 'flxpos/v1/' ) ),
			'nonce'  => wp_create_nonce( 'wp_rest' ),
			'brands' => (bool) flxpos_setting( 'show_brands' ),
			'cur'    => $cur,
			'i18n'   => array(
				'name'     => __( 'Kart üzerindeki ad', 'flx-pos' ),
				'number'   => __( 'Kart numarası', 'flx-pos' ),
				'expiry'   => __( 'Son kullanma (AA/YY)', 'flx-pos' ),
				'cvc'      => __( 'Güvenlik kodu (CVC)', 'flx-pos' ),
				'inst'     => __( 'Taksit', 'flx-pos' ),
				'single'   => __( 'Tek çekim', 'flx-pos' ),
				/* translators: 1: taksit sayısı, 2: aylık tutar, 3: toplam */
				'instRow'  => __( '%1$s taksit × %2$s (toplam %3$s)', 'flx-pos' ),
				'secure'   => __( 'Kart bilgileriniz bankanın 3D Secure doğrulamasıyla korunur ve sitede saklanmaz.', 'flx-pos' ),
				'badCard'  => __( 'Kart numarasını kontrol edin.', 'flx-pos' ),
				'badExp'   => __( 'Son kullanma tarihini kontrol edin.', 'flx-pos' ),
				'badCvc'   => __( 'Güvenlik kodunu kontrol edin.', 'flx-pos' ),
				'badName'  => __( 'Kart üzerindeki adı yazın.', 'flx-pos' ),
				'test'     => __( 'Test modu: gerçek para çekilmez.', 'flx-pos' ),
			),
		);
	}

	/* ---------------- Ortak: kart, ödeme, iade ---------------- */

	/** Gönderilen kart alanları. */
	public static function posted_card() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce ödeme formu (nonce WC'de)
		$g   = static fn( $k ) => isset( $_POST[ $k ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) ) : '';
		$exp = preg_replace( '/\s/', '', $g( 'flxpos_exp' ) );
		$mm  = '';
		$yy  = '';
		if ( preg_match( '#^(\d{1,2})\s*/?\s*(\d{2}|\d{4})$#', $exp, $m ) ) {
			$mm = (int) $m[1];
			$yy = 2 === strlen( $m[2] ) ? 2000 + (int) $m[2] : (int) $m[2];
		}
		$card = array(
			'pan'   => preg_replace( '/\D/', '', $g( 'flxpos_pan' ) ),
			'name'  => $g( 'flxpos_name' ),
			'month' => $mm,
			'year'  => $yy,
			'cvv'   => preg_replace( '/\D/', '', $g( 'flxpos_cvc' ) ),
			'inst'  => max( 1, (int) $g( 'flxpos_inst' ) ),
		);
		// phpcs:enable
		return $card;
	}

	public static function luhn( $pan ) {
		$pan = preg_replace( '/\D/', '', (string) $pan );
		if ( strlen( $pan ) < 12 || strlen( $pan ) > 19 ) {
			return false;
		}
		$sum = 0;
		$alt = false;
		for ( $i = strlen( $pan ) - 1; $i >= 0; $i-- ) {
			$n = (int) $pan[ $i ];
			if ( $alt ) {
				$n *= 2;
				if ( $n > 9 ) {
					$n -= 9;
				}
			}
			$sum += $n;
			$alt  = ! $alt;
		}
		return 0 === $sum % 10;
	}

	/** Kart hatası (yoksa ''). */
	public static function card_error( array $c ) {
		if ( ! self::luhn( $c['pan'] ) ) {
			return __( 'Kart numarasını kontrol edin.', 'flx-pos' );
		}
		$now = (int) gmdate( 'Y' ) * 12 + (int) gmdate( 'n' );
		if ( $c['month'] < 1 || $c['month'] > 12 || $c['year'] < 2000 || $c['year'] * 12 + $c['month'] < $now ) {
			return __( 'Son kullanma tarihini kontrol edin.', 'flx-pos' );
		}
		if ( ! preg_match( '/^\d{3,4}$/', $c['cvv'] ) ) {
			return __( 'Güvenlik kodunu kontrol edin.', 'flx-pos' );
		}
		if ( '' === trim( $c['name'] ) ) {
			return __( 'Kart üzerindeki adı yazın.', 'flx-pos' );
		}
		return '';
	}

	/**
	 * Bir sipariş için ödemeyi başlat: işlem kaydı + kasa + köprü adresi.
	 *
	 * @return array|WP_Error WooCommerce process_payment yanıtı.
	 */
	public static function start_order( WC_Order $order, array $account, $card = null ) {
		$amount = round( (float) $order->get_total(), 2 );
		if ( $amount <= 0 ) {
			return new WP_Error( 'flxpos_amount', __( 'Tutar sıfırdan büyük olmalı.', 'flx-pos' ) );
		}
		$inst = 1;
		if ( $card && $card['inst'] > 1 ) {
			$opts = FLXPOS_Installments::options( $account, $amount, $order->get_currency(), substr( $card['pan'], 0, 8 ) );
			if ( ! isset( $opts[ $card['inst'] ] ) ) {
				return new WP_Error( 'flxpos_inst', __( 'Seçilen taksit bu kartta kullanılamıyor.', 'flx-pos' ) );
			}
			$inst = (int) $card['inst'];
			$fee  = round( $opts[ $inst ]['total'] - $amount, 2 );
			if ( $fee > 0 ) {
				self::installment_fee( $order, $fee, $inst );
				$amount = round( (float) $order->get_total(), 2 );
			}
		}
		$txn = FLXPOS_Txn::create(
			array(
				'order_id'    => $order->get_id(),
				'account'     => $account['id'],
				'provider'    => $account['provider'],
				'mode'        => $account['mode'],
				'test'        => (int) $account['test'],
				'amount'      => $amount,
				'currency'    => strtoupper( $order->get_currency() ),
				'installment' => $inst,
				'card'        => $card ? substr( $card['pan'], 0, 6 ) . '******' . substr( $card['pan'], -4 ) : '',
			)
		);
		if ( ! $txn ) {
			return new WP_Error( 'flxpos_db', __( 'Ödeme kaydı açılamadı.', 'flx-pos' ) );
		}
		$order->update_meta_data( '_flxpos_txn', $txn['id'] );
		$order->update_meta_data( '_flxpos_account', $account['id'] );
		$order->save();
		$more = array();
		if ( $card ) {
			unset( $card['inst'] );
			$more['v'] = FLXPOS_Vault::put( $card );
		}
		return array(
			'result'   => 'success',
			'redirect' => FLXPOS_Flow::url( 'bridge', $txn['id'], $more ),
		);
	}

	/** Taksit vade farkını siparişe ücret satırı olarak ekle (önceki denemeninkini değiştirir). */
	private static function installment_fee( WC_Order $order, $fee, $inst ) {
		foreach ( $order->get_fees() as $id => $item ) {
			if ( $item->get_meta( '_flxpos_fee' ) ) {
				$order->remove_item( $id );
			}
		}
		$item = new WC_Order_Item_Fee();
		/* translators: %d: taksit sayısı */
		$item->set_name( sprintf( __( 'Taksit farkı (%d taksit)', 'flx-pos' ), $inst ) );
		$item->set_amount( $fee );
		$item->set_total( $fee );
		$item->set_tax_status( 'none' );
		$item->add_meta_data( '_flxpos_fee', 1, true );
		$order->add_item( $item );
		$order->calculate_totals( false );
		$order->save();
	}

	/**
	 * İade: tam tutar ve aynı gün ise önce iptal; olmazsa iade.
	 *
	 * @return true|WP_Error
	 */
	public static function refund( $order_id, $amount, $reason = '' ) {
		$txn = FLXPOS_Txn::paid_for_order( $order_id );
		if ( ! $txn ) {
			return new WP_Error( 'flxpos_refund', __( 'Bu siparişte başarılı bir kart ödemesi bulunamadı.', 'flx-pos' ) );
		}
		return self::refund_txn( $txn, $amount, $reason );
	}

	/**
	 * Bir işlemi iade et (siparişli ya da siparişsiz).
	 *
	 * @param array      $txn    Ödenmiş işlem.
	 * @param float|null $amount null = kalan tutarın tamamı.
	 * @return true|WP_Error
	 */
	public static function refund_txn( array $txn, $amount, $reason = '' ) {
		if ( ! in_array( $txn['status'], array( FLXPOS_Txn::PAID, FLXPOS_Txn::PARTIAL ), true ) || 'sale' !== ( $txn['kind'] ?? 'sale' ) ) {
			return new WP_Error( 'flxpos_refund', __( 'Bu işlem iade edilemez.', 'flx-pos' ) );
		}
		$order_id = (int) $txn['order_id'];
		$amount = null === $amount ? (float) $txn['amount'] - (float) $txn['refunded'] : (float) $amount;
		if ( $amount <= 0 || $amount > (float) $txn['amount'] - (float) $txn['refunded'] + 0.009 ) {
			return new WP_Error( 'flxpos_refund', __( 'İade tutarı ödenen tutarı aşamaz.', 'flx-pos' ) );
		}
		$drv = FLXPOS_Accounts::driver( $txn['account'] );
		if ( is_wp_error( $drv ) ) {
			return $drv;
		}
		$full  = flxpos_same_amount( $amount, (float) $txn['amount'] ) && (float) $txn['refunded'] <= 0;
		$today = substr( $txn['created'], 0, 10 ) === gmdate( 'Y-m-d', time() ) || substr( get_date_from_gmt( $txn['created'], 'Y-m-d' ), 0, 10 ) === current_time( 'Y-m-d' );
		$r     = null;
		$kind  = 'refund';
		if ( $full && $today ) {
			$r = $drv->cancel( $txn );
			if ( ! is_wp_error( $r ) && ! empty( $r['ok'] ) ) {
				$kind = 'void';
			} else {
				$r = null;
			}
		}
		if ( null === $r ) {
			$r = $drv->refund( $txn, $amount );
		}
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		FLXPOS_Txn::create(
			array(
				'ref'       => substr( $txn['ref'] . 'R' . strtoupper( base_convert( (string) time(), 10, 36 ) ), 0, 32 ),
				'order_id'  => $txn['order_id'],
				'parent_id' => $txn['id'],
				'kind'      => $kind,
				'account'   => $txn['account'],
				'provider'  => $txn['provider'],
				'mode'      => $txn['mode'],
				'test'      => $txn['test'],
				'amount'    => $amount,
				'currency'  => $txn['currency'],
				'status'    => ! empty( $r['ok'] ) ? FLXPOS_Txn::PAID : FLXPOS_Txn::FAILED,
				'bank_ref'  => (string) ( $r['bank_ref'] ?? '' ),
				'message'   => mb_substr( (string) ( $r['message'] ?? $reason ), 0, 250 ),
			)
		);
		if ( empty( $r['ok'] ) ) {
			return new WP_Error( 'flxpos_refund', '' !== (string) ( $r['message'] ?? '' ) ? (string) $r['message'] : __( 'Banka iadeyi onaylamadı.', 'flx-pos' ) );
		}
		$refunded = round( (float) $txn['refunded'] + $amount, 2 );
		$status   = 'void' === $kind ? FLXPOS_Txn::VOIDED : ( flxpos_same_amount( $refunded, $txn['amount'] ) ? FLXPOS_Txn::REFUNDED : FLXPOS_Txn::PARTIAL );
		FLXPOS_Txn::update(
			$txn['id'],
			array(
				'refunded' => $refunded,
				'status'   => $status,
			)
		);
		$o = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
		if ( $o ) {
			$o->add_order_note(
				'void' === $kind
					/* translators: %s: tutar */
					? sprintf( __( 'Kart ödemesi bankada iptal edildi (%s).', 'flx-pos' ), flxpos_money( $amount, $txn['currency'] ) )
					/* translators: %s: tutar */
					: sprintf( __( 'Karta iade edildi: %s.', 'flx-pos' ), flxpos_money( $amount, $txn['currency'] ) )
			);
		}
		do_action( 'flxpos_refunded', $txn, $amount, $kind );
		return true;
	}

	/** Kart formu (klasik ödeme). */
	public static function card_form( array $account, $amount ) {
		$id = 'flxpos';
		echo '<fieldset id="flxpos-card" class="flxpos-card wc-credit-card-form wc-payment-form" data-account="' . esc_attr( $account['id'] ) . '" data-amount="' . esc_attr( flxpos_dec( $amount ) ) . '">';
		echo '<p class="form-row form-row-wide"><label for="' . esc_attr( $id ) . '-name">' . esc_html__( 'Kart üzerindeki ad', 'flx-pos' ) . ' <span class="required">*</span></label><input id="' . esc_attr( $id ) . '-name" name="flxpos_name" class="input-text" autocomplete="cc-name" spellcheck="false" required></p>';
		echo '<p class="form-row form-row-wide flxpos-pan"><label for="' . esc_attr( $id ) . '-pan">' . esc_html__( 'Kart numarası', 'flx-pos' ) . ' <span class="required">*</span></label><input id="' . esc_attr( $id ) . '-pan" name="flxpos_pan" class="input-text" inputmode="numeric" autocomplete="cc-number" placeholder="•••• •••• •••• ••••" maxlength="23" required><span class="flxpos-brand" aria-hidden="true"></span></p>';
		echo '<p class="form-row form-row-first"><label for="' . esc_attr( $id ) . '-exp">' . esc_html__( 'Son kullanma (AA/YY)', 'flx-pos' ) . ' <span class="required">*</span></label><input id="' . esc_attr( $id ) . '-exp" name="flxpos_exp" class="input-text" inputmode="numeric" autocomplete="cc-exp" placeholder="AA / YY" maxlength="7" required></p>';
		echo '<p class="form-row form-row-last"><label for="' . esc_attr( $id ) . '-cvc">' . esc_html__( 'Güvenlik kodu (CVC)', 'flx-pos' ) . ' <span class="required">*</span></label><input id="' . esc_attr( $id ) . '-cvc" name="flxpos_cvc" class="input-text" inputmode="numeric" autocomplete="cc-csc" placeholder="CVC" maxlength="4" required></p>';
		if ( $account['installments'] ) {
			echo '<p class="form-row form-row-wide flxpos-inst" hidden><label for="' . esc_attr( $id ) . '-inst">' . esc_html__( 'Taksit', 'flx-pos' ) . '</label><select id="' . esc_attr( $id ) . '-inst" name="flxpos_inst"><option value="1">' . esc_html__( 'Tek çekim', 'flx-pos' ) . '</option></select></p>';
		}
		echo '<div class="clear"></div><p class="flxpos-secure">' . esc_html__( 'Kart bilgileriniz bankanın 3D Secure doğrulamasıyla korunur ve sitede saklanmaz.', 'flx-pos' ) . '</p>';
		if ( ! empty( $account['test'] ) ) {
			echo '<p class="flxpos-test">' . esc_html__( 'Test modu: gerçek para çekilmez.', 'flx-pos' ) . '</p>';
		}
		echo '</fieldset>';
	}
}
