<?php
/**
 * Diğer eklentiler için PHP API (WooCommerce siparişi olmadan da). Ayrıntı: docs/flx-pos/API.md.
 *
 *     $r = flxpos_charge( array(
 *         'amount'      => 150,
 *         'currency'    => 'TRY',
 *         'description' => 'Bağış #12',
 *         'customer'    => array( 'name' => 'Ada Yılmaz', 'email' => 'ada@example.com', 'phone' => '5…' ),
 *         'back'        => home_url( '/tesekkurler/' ), // sonuç: ?flxpos_status=paid&flxpos_ref=…
 *         'meta'        => array( 'donation' => 12 ),
 *     ) );
 *     if ( ! is_wp_error( $r ) ) { wp_safe_redirect( $r['redirect'] ); exit; }
 *
 *     add_action( 'flxpos_txn_settled', function ( $txn, $status, $result ) { … }, 10, 3 );
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ödeme başlat. Kart gerekiyorsa 'card' (pan, name, month, year, cvv) verilir; yoksa hesap ödeme sayfası
 * modelinde olmalı.
 *
 * @return array{txn:array,redirect:string}|WP_Error
 */
function flxpos_charge( array $args ) {
	$args   = wp_parse_args(
		$args,
		array(
			'amount'      => 0,
			'currency'    => 'TRY',
			'account'     => '',
			'installment' => 1,
			'description' => '',
			'customer'    => array(),
			'items'       => array(),
			'back'        => home_url( '/' ),
			'card'        => null,
			'token'       => '',    // Stripe: tarayıcıda Payment Element'ten üretilen pm_…
			'save'        => false, // Stripe: kartı sonraki çekimler için sakla (aylık bağış)
			'meta'        => array(),
		)
	);
	$amount = round( (float) $args['amount'], 2 );
	$cur    = strtoupper( (string) $args['currency'] );
	if ( $amount <= 0 ) {
		return new WP_Error( 'flxpos_amount', __( 'Tutar sıfırdan büyük olmalı.', 'flx-pos' ) );
	}
	$acc = '' !== (string) $args['account'] ? FLXPOS_Accounts::get( (string) $args['account'] ) : FLXPOS_Accounts::for_card( $cur );
	if ( ! $acc || empty( $acc['enabled'] ) || ! FLXPOS_Accounts::accepts( $acc, $cur ) ) {
		return new WP_Error( 'flxpos_account', __( 'Bu para birimi için POS hesabı yok.', 'flx-pos' ) );
	}
	$card = is_array( $args['card'] ) ? array_merge( array( 'pan' => '', 'name' => '', 'month' => 0, 'year' => 0, 'cvv' => '', 'inst' => 1 ), $args['card'] ) : null;
	if ( FLXPOS_Accounts::takes_card( $acc ) ) {
		if ( ! $card ) {
			return new WP_Error( 'flxpos_card', __( 'Bu hesap kart bilgisi istiyor.', 'flx-pos' ) );
		}
		$card['pan'] = preg_replace( '/\D/', '', (string) $card['pan'] );
		$err         = FLXPOS_Gateways::card_error( $card );
		if ( '' !== $err ) {
			return new WP_Error( 'flxpos_card', $err );
		}
	} else {
		$card = null;
	}
	$back = wp_validate_redirect( (string) $args['back'], home_url( '/' ) );
	$txn  = FLXPOS_Txn::create(
		array(
			'account'     => $acc['id'],
			'provider'    => $acc['provider'],
			'mode'        => $acc['mode'],
			'test'        => (int) $acc['test'],
			'amount'      => $amount,
			'currency'    => $cur,
			'installment' => max( 1, (int) $args['installment'] ),
			'card'        => $card ? substr( $card['pan'], 0, 6 ) . '******' . substr( $card['pan'], -4 ) : '',
			'data'        => array(
				'description' => (string) $args['description'],
				'customer'    => (array) $args['customer'],
				'items'       => (array) $args['items'],
				'back'        => $back,
				'meta'        => (array) $args['meta'],
				'pm_in'       => preg_match( '/^pm_[A-Za-z0-9]{6,}$/', (string) $args['token'] ) ? (string) $args['token'] : '',
				'save'        => ! empty( $args['save'] ) ? 1 : 0,
			),
		)
	);
	if ( ! $txn ) {
		return new WP_Error( 'flxpos_db', __( 'Ödeme kaydı açılamadı.', 'flx-pos' ) );
	}
	$more = array();
	if ( $card ) {
		unset( $card['inst'] );
		$more['v'] = FLXPOS_Vault::put( $card );
	}
	return array( 'txn' => $txn ) + FLXPOS_Flow::start_result( $txn, $acc, $more );
}

/**
 * İade (null = kalan tutarın tamamı). Aynı gün tam tutarda önce iptal denenir.
 *
 * @return true|WP_Error
 */
function flxpos_refund( $txn_id, $amount = null, $reason = '' ) {
	$txn = FLXPOS_Txn::get( (int) $txn_id );
	if ( ! $txn ) {
		return new WP_Error( 'flxpos_txn', __( 'İşlem bulunamadı.', 'flx-pos' ) );
	}
	return FLXPOS_Gateways::refund_txn( $txn, $amount, $reason );
}

/**
 * Kayıtlı kartla, ödeyen sitede olmadan çekim (aylık bağış, abonelik). Şimdilik yalnız Stripe.
 * Kart ilk ödemede 'save' => true ile saklanır; settled işlemin data'sında stripe_customer ve stripe_pm olur.
 *
 *     $r = flxpos_charge_saved( array(
 *         'account' => $txn['account'], 'customer' => $txn['data']['stripe_customer'],
 *         'payment_method' => $txn['data']['stripe_pm'], 'amount' => 150, 'currency' => 'USD',
 *         'description' => 'Aylık bağış · Aralık', 'meta' => array( 'donation' => 12 ),
 *     ) );
 *
 * Banka 3D Secure isterse işlem başarısız olur ve data.needs_auth = 1: ödeyene bağlantı gönderilip
 * normal bir ödeme (flxpos_charge) alınır.
 *
 * @return array|WP_Error Sonuçlanmış işlem kaydı (status paid|failed|pending).
 */
function flxpos_charge_saved( array $args ) {
	$args   = wp_parse_args(
		$args,
		array(
			'account'        => '',
			'customer'       => '',
			'payment_method' => '',
			'amount'         => 0,
			'currency'       => 'USD',
			'description'    => '',
			'customer_info'  => array(),
			'order_id'       => 0,
			'meta'           => array(),
		)
	);
	$amount = round( (float) $args['amount'], 2 );
	$cur    = strtoupper( (string) $args['currency'] );
	if ( $amount <= 0 ) {
		return new WP_Error( 'flxpos_amount', __( 'Tutar sıfırdan büyük olmalı.', 'flx-pos' ) );
	}
	$acc = FLXPOS_Accounts::get( (string) $args['account'] );
	if ( ! $acc || empty( $acc['enabled'] ) || ! FLXPOS_Accounts::accepts( $acc, $cur ) ) {
		return new WP_Error( 'flxpos_account', __( 'Bu para birimi için POS hesabı yok.', 'flx-pos' ) );
	}
	if ( ! preg_match( '/^cus_[A-Za-z0-9]+$/', (string) $args['customer'] ) || ! preg_match( '/^pm_[A-Za-z0-9]+$/', (string) $args['payment_method'] ) ) {
		return new WP_Error( 'flxpos_saved', __( 'Kayıtlı kart bilgisi eksik.', 'flx-pos' ) );
	}
	$drv = FLXPOS_Accounts::driver( $acc['id'] );
	if ( is_wp_error( $drv ) ) {
		return $drv;
	}
	if ( ! method_exists( $drv, 'charge_saved' ) ) {
		return new WP_Error( 'flxpos_unsupported', __( 'Bu sağlayıcıda kayıtlı kartla çekim yok.', 'flx-pos' ) );
	}
	$txn = FLXPOS_Txn::create(
		array(
			'order_id' => (int) $args['order_id'],
			'account'  => $acc['id'],
			'provider' => $acc['provider'],
			'mode'     => 'saved',
			'test'     => (int) $acc['test'],
			'amount'   => $amount,
			'currency' => $cur,
			'ip'       => '',
			'data'     => array(
				'description' => (string) $args['description'],
				'customer'    => (array) $args['customer_info'],
				'meta'        => (array) $args['meta'],
				'off_session' => 1,
			),
		)
	);
	if ( ! $txn ) {
		return new WP_Error( 'flxpos_db', __( 'Ödeme kaydı açılamadı.', 'flx-pos' ) );
	}
	$res = $drv->charge_saved( FLXPOS_Flow::payment( $txn ), (string) $args['customer'], (string) $args['payment_method'] );
	FLXPOS_Flow::complete( $txn, is_array( $res ) ? $res : array( 'status' => 'failed' ) );
	return FLXPOS_Txn::get( $txn['id'] );
}

/** İşlem (referansla). */
function flxpos_txn( $ref ) {
	return FLXPOS_Txn::by_ref( (string) $ref );
}

/**
 * Bu para biriminde kartı saklayıp sonraki ayları ödeyen sitede olmadan çekebilen kartlı hesap var mı?
 * (aylık bağış, abonelik). Bugün yalnız Stripe hesabı.
 */
function flxpos_can_save_card( $currency = '' ) {
	$cur = strtoupper( '' !== (string) $currency ? (string) $currency : ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY' ) );
	return FLXPOS_Saved_Cards::account_saves( FLXPOS_Accounts::for_card( $cur ) );
}

/**
 * Siparişin ödemesinde saklanan kart. Kartı saklatmak için sipariş ödenmeden önce `_flxpos_save` = yes yazılır.
 *
 * @param WC_Order|int $order Sipariş.
 * @return array{account:string,customer:string,payment_method:string,label:string}|null
 */
function flxpos_saved_card( $order ) {
	return FLXPOS_Saved_Cards::of_order( $order );
}

/**
 * Kayıtlı kartı bir WooCommerce siparişine çek; ödenirse Flux POS siparişi öder. Önceki çekimin sonucu
 * beklemedeyse yeniden çekmez (uncertain döner).
 *
 * @return array{result:string, txn?:string, error?:string} result paid|failed|uncertain
 */
function flxpos_charge_saved_order( WC_Order $order, array $card, $description = '' ) {
	return FLXPOS_Saved_Cards::charge_order( $order, $card, $description );
}

/* Temanın ve Flux Donate'in "Ödemeler … ile güvenle alınır" rozeti: kart hesabının sağlayıcı adı. */
add_filter(
	'flx_secure_badge_provider',
	static function ( $name ) {
		if ( '' !== (string) $name || ! function_exists( 'get_woocommerce_currency' ) ) {
			return $name;
		}
		$acc = FLXPOS_Accounts::for_card( get_woocommerce_currency() );
		$p   = $acc && ! empty( $acc['enabled'] ) ? FLXPOS_Providers::get( $acc['provider'] ) : null;
		return $p ? (string) $p['name'] : $name;
	}
);
