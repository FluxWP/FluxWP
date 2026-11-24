<?php
/**
 * Siparişte ödeme detayları: POS, kart (ilk 6 + son 4), taksit, tutar, banka referansı, onay kodu,
 * test/canlı. Flux Donate kuruluysa bilgiyi onun "Ödeme detayları" kutusuna `flxb_payment_details`
 * filtresiyle verir; değilse aynı kutuyu kendisi gösterir. Kart numarası hiç saklanmaz.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Order_Box {

	public static function init() {
		add_filter( 'flxb_payment_details', array( __CLASS__, 'donate_details' ), 10, 2 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ), 30 );
	}

	/** Siparişin ödenmiş (yoksa son) Flux POS satış işlemi. */
	public static function txn( WC_Order $order ) {
		$rows = FLXPOS_Txn::for_order( $order->get_id() );
		$sale = null;
		foreach ( $rows as $t ) {
			if ( 'sale' !== $t['kind'] ) {
				continue;
			}
			if ( in_array( $t['status'], array( FLXPOS_Txn::PAID, FLXPOS_Txn::PARTIAL, FLXPOS_Txn::REFUNDED ), true ) ) {
				return $t;
			}
			$sale = $sale ? $sale : $t;
		}
		return $sale;
	}

	/**
	 * @return array{method:string,provider:string,card:string,owner:string,installment:int,amount:float,currency:string,bank_ref:string,auth_code:string,txn:string,test:?bool,status:string}|null
	 */
	public static function details( WC_Order $order ) {
		$t = self::txn( $order );
		if ( ! $t ) {
			return null;
		}
		$acc = FLXPOS_Accounts::get( $t['account'] );
		return array(
			'method'      => (string) $order->get_payment_method_title(),
			'provider'    => $acc ? FLXPOS_Accounts::label( $acc ) : (string) $t['provider'],
			'card'        => trim( $t['card_brand'] . ' ' . self::pretty( (string) $t['card'] ) ),
			'owner'       => '',
			'installment' => max( 1, (int) $t['installment'] ),
			'amount'      => (float) $t['amount'],
			'currency'    => (string) $t['currency'],
			'bank_ref'    => (string) $t['bank_ref'],
			'auth_code'   => (string) $t['auth_code'],
			'txn'         => (string) $t['ref'],
			'test'        => (bool) $t['test'],
			'status'      => (string) $t['status'],
		);
	}

	/** "454360******4242" → "4543 60•• •••• 4242". */
	private static function pretty( $mask ) {
		$m = preg_replace( '/[^0-9*]/', '', $mask );
		return '' === $m ? '' : str_replace( '*', '•', trim( implode( ' ', str_split( $m, 4 ) ) ) );
	}

	/** Flux Donate'in kutusuna bilgiyi verir. */
	public static function donate_details( $info, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return $info;
		}
		$d = self::details( $order );
		if ( ! $d ) {
			return $info;
		}
		unset( $d['status'] );
		return array_merge( (array) $info, array_filter( $d, static fn( $v ) => '' !== $v && null !== $v ) );
	}

	public static function meta_boxes() {
		if ( class_exists( 'FLXB_WC_Payinfo' ) ) {
			return; // Flux Donate gösteriyor.
		}
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box( 'flxpos-order', __( 'Ödeme detayları', 'flx-pos' ), array( __CLASS__, 'html' ), $screen, 'normal', 'high' );
	}

	/** "tek çekim" / "3 taksit". */
	public static function installment_label( $n ) {
		/* translators: %d: taksit sayısı */
		return (int) $n > 1 ? sprintf( __( '%d taksit', 'flx-pos' ), (int) $n ) : __( 'tek çekim', 'flx-pos' );
	}

	public static function html( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		$d     = $order ? self::details( $order ) : null;
		if ( ! $d ) {
			echo '<p class="description">' . esc_html__( 'Bu siparişte Flux POS işlemi yok.', 'flx-pos' ) . '</p>';
			return;
		}
		$labels = FLXPOS_Txn::status_labels();
		$line   = array_filter( array( '' !== $d['card'] ? __( 'Kart', 'flx-pos' ) : '', $d['provider'], flxpos_money( $d['amount'], $d['currency'] ), self::installment_label( $d['installment'] ) ) );
		echo '<p style="font-size:15px;font-weight:600;margin:4px 0 10px">' . esc_html( implode( ' · ', $line ) ) . '</p>';
		$rows = array(
			__( 'Durum', 'flx-pos' )          => esc_html( $labels[ $d['status'] ] ?? $d['status'] ),
			__( 'POS', 'flx-pos' )            => esc_html( $d['provider'] ),
			__( 'Kart', 'flx-pos' )           => '' !== $d['card'] ? '<code>' . esc_html( $d['card'] ) . '</code>' : '',
			__( 'Taksit', 'flx-pos' )         => esc_html( self::installment_label( $d['installment'] ) ),
			__( 'Tutar', 'flx-pos' )          => esc_html( flxpos_money( $d['amount'], $d['currency'] ) ),
			__( 'Banka referansı', 'flx-pos' ) => '' !== $d['bank_ref'] ? '<code>' . esc_html( $d['bank_ref'] ) . '</code>' : '',
			__( 'Onay kodu', 'flx-pos' )      => '' !== $d['auth_code'] ? '<code>' . esc_html( $d['auth_code'] ) . '</code>' : '',
			__( 'İşlem no', 'flx-pos' )       => '<code>' . esc_html( $d['txn'] ) . '</code>',
			__( 'Mod', 'flx-pos' )            => $d['test'] ? '<strong>' . esc_html__( 'Test', 'flx-pos' ) . '</strong>' : esc_html__( 'Canlı', 'flx-pos' ),
		);
		echo '<table class="widefat striped" style="border:0"><tbody>';
		foreach ( array_filter( $rows, static fn( $v ): bool => '' !== (string) $v ) as $k => $v ) {
			echo '<tr><th scope="row" style="width:160px">' . esc_html( $k ) . '</th><td>' . $v . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
		}
		echo '</tbody></table><p><a href="' . esc_url( flxpos_admin_url( array( 'tab' => 'txns', 's' => $order->get_id() ) ) ) . '">' . esc_html__( 'Tüm işlemler', 'flx-pos' ) . '</a></p>';
	}
}
