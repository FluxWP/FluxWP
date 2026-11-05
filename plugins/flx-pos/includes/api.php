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
	return array(
		'txn'      => $txn,
		'redirect' => FLXPOS_Flow::url( 'bridge', $txn['id'], $more ),
	);
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

/** İşlem (referansla). */
function flxpos_txn( $ref ) {
	return FLXPOS_Txn::by_ref( (string) $ref );
}
