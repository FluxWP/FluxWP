<?php
/**
 * Hesabım › Aboneliklerim: liste, sonraki yenileme, ödeme bağlantısı, iptal / yeniden aç.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Sub_Account {

	const ENDPOINT = 'subscriptions';

	public static function init() {
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu' ), 20 );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render' ) );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( __CLASS__, 'title' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'action' ), 30 );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
	}

	public static function query_vars( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	/** Yeni uç noktanın kalıcı bağlantısı için sürüm değişince bir kez rewrite yenilenir. */
	public static function maybe_flush() {
		if ( get_option( 'flxcm_rewrite_ver' ) !== FLXCM_VERSION ) {
			update_option( 'flxcm_rewrite_ver', FLXCM_VERSION, false );
			flush_rewrite_rules( false );
		}
	}

	public static function mine( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}
		$u = get_userdata( $user_id );
		$q = FLXCM_Subscriptions::query( array( 'user_id' => $user_id, 'email' => $u ? $u->user_email : '', 'per_page' => 100 ) );
		return $q['rows'];
	}

	public static function menu( $items ) {
		if ( ! self::mine() ) {
			return $items;
		}
		$out = array();
		foreach ( $items as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'orders' === $k ) {
				$out[ self::ENDPOINT ] = __( 'Subscriptions', 'flx-commerce' );
			}
		}
		if ( ! isset( $out[ self::ENDPOINT ] ) ) {
			$out[ self::ENDPOINT ] = __( 'Subscriptions', 'flx-commerce' );
		}
		return $out;
	}

	public static function title() {
		return __( 'Subscriptions', 'flx-commerce' );
	}

	public static function status_label( $status ) {
		$map = array(
			'pending'   => __( 'Awaiting first payment', 'flx-commerce' ),
			'active'    => __( 'Active', 'flx-commerce' ),
			'past_due'  => __( 'Payment overdue', 'flx-commerce' ),
			'cancelled' => __( 'Cancelled', 'flx-commerce' ),
			'expired'   => __( 'Ended', 'flx-commerce' ),
		);
		return $map[ $status ] ?? $status;
	}

	public static function status_class( $status ) {
		$map = array(
			'active'    => 'is-ok',
			'pending'   => 'is-wait',
			'past_due'  => 'is-bad',
			'cancelled' => 'is-wait',
		);
		return $map[ $status ] ?? '';
	}

	/** Bitiş/yenileme satırı metni. */
	public static function when( array $row ) {
		$end = $row['period_end'] ? (int) strtotime( $row['period_end'] . ' UTC' ) : 0;
		switch ( $row['status'] ) {
			case 'active':
				/* translators: %s: date */
				return sprintf( __( 'Renews on %s', 'flx-commerce' ), flxcm_date( $end ) );
			case 'past_due':
				/* translators: %s: date */
				return sprintf( __( 'Was due on %s', 'flx-commerce' ), flxcm_date( $end ) );
			case 'cancelled':
				/* translators: %s: date */
				return sprintf( __( 'Valid until %s', 'flx-commerce' ), flxcm_date( $end ) );
			case 'expired':
				/* translators: %s: date */
				return $row['ended_at'] ? sprintf( __( 'Ended on %s', 'flx-commerce' ), flxcm_date( $row['ended_at'] ) ) : '—';
		}
		return '—';
	}

	private static function action_url( $id, $do ) {
		return wp_nonce_url( add_query_arg( array( 'flxcm_sub' => (int) $id, 'flxcm_do' => $do ), wc_get_account_endpoint_url( self::ENDPOINT ) ), 'flxcm_sub_' . $do . '_' . (int) $id );
	}

	public static function render() {
		wp_enqueue_style( 'flxcm-front', FLXCM_URL . 'assets/front.css', array(), FLXCM_VERSION );
		$rows = self::mine();
		if ( ! $rows ) {
			echo '<p class="flxcm-subs-empty">' . esc_html__( 'You have no subscriptions yet.', 'flx-commerce' ) . '</p>';
			return;
		}
		echo '<div class="flxcm-subs">';
		foreach ( $rows as $r ) {
			$renew = (int) $r['renewal_order_id'] ? wc_get_order( (int) $r['renewal_order_id'] ) : null;
			$due   = $renew && $renew->has_status( array( 'pending', 'on-hold', 'failed' ) ) && ! $renew->is_paid();
			$auto  = $due && FLXCM_Sub_Cards::auto_pending( $renew );
			$card  = FLXCM_Sub_Cards::label( $r );
			$live  = in_array( $r['status'], array( 'active', 'past_due' ), true );
			echo '<article class="flxcm-sub flxcm-sub--' . esc_attr( $r['status'] ) . '">';
			echo '<header class="flxcm-sub__head"><div><h3 class="flxcm-sub__name">' . esc_html( $r['product_name'] ) . '</h3>';
			echo '<span class="flxcm-sub__price">' . wp_kses_post( wc_price( $r['amount'], array( 'currency' => $r['currency'] ) ) ) . ' / ' . esc_html( FLXCM_Subscriptions::period_label( $r['period'], false ) ) . '</span></div>';
			echo '<span class="flxcm-sub__badge ' . esc_attr( self::status_class( $r['status'] ) ) . '">' . esc_html( self::status_label( $r['status'] ) ) . '</span></header>';
			echo '<p class="flxcm-sub__when">' . esc_html( self::when( $r ) ) . '</p>';
			if ( $card && $live ) {
				echo '<p class="flxcm-sub__card">' . esc_html(
					sprintf(
						/* translators: %s: card */
						__( 'Renews automatically from your card %s.', 'flx-commerce' ),
						$card
					)
				) . '</p>';
			}
			if ( $auto ) {
				echo '<div class="flxcm-sub__due flxcm-sub__due--auto"><span>' . esc_html(
					sprintf(
						/* translators: 1: amount, 2: date */
						__( '%1$s will be charged to your card on %2$s.', 'flx-commerce' ),
						wp_strip_all_tags( flxcm_money( $renew->get_total(), $renew->get_currency() ) ),
						flxcm_date( max( (int) strtotime( $r['period_end'] . ' UTC' ), (int) $renew->get_meta( '_flxcm_charge_next' ) ) )
					)
				) . '</span></div>';
			} elseif ( $due ) {
				$bank = FLXCM_Bank::ID === $renew->get_payment_method();
				echo '<div class="flxcm-sub__due"><span>' . esc_html(
					sprintf(
						/* translators: 1: order number, 2: amount */
						__( 'Renewal order #%1$s is waiting for payment: %2$s', 'flx-commerce' ),
						$renew->get_order_number(),
						wp_strip_all_tags( flxcm_money( $renew->get_total(), $renew->get_currency() ) )
					)
				) . '</span><a class="button flxcm-sub__pay" href="' . esc_url( FLXCM_Subscriptions::pay_url( $renew ) ) . '">' . esc_html( $bank ? __( 'Bank details and receipt', 'flx-commerce' ) : __( 'Pay now', 'flx-commerce' ) ) . '</a></div>';
			}
			$links = array();
			if ( (int) $r['last_order_id'] && ( $o = wc_get_order( (int) $r['last_order_id'] ) ) ) {
				/* translators: %s: order number */
				$links[] = '<a href="' . esc_url( $o->get_view_order_url() ) . '">' . esc_html( sprintf( __( 'Last order #%s', 'flx-commerce' ), $o->get_order_number() ) ) . '</a>';
			}
			$window = (int) strtotime( $r['period_end'] . ' UTC' ) - max( 0, (int) flxcm_setting( 'sub_renew_days' ) ) * DAY_IN_SECONDS;
			if ( ! $due && in_array( $r['status'], array( 'active', 'past_due' ), true ) && time() >= $window ) {
				// Yenileme siparişi iptal olduysa (ör. süresinde ödenmedi) müşteri yenisini kendisi açabilir.
				$links[] = '<a href="' . esc_url( self::action_url( $r['id'], 'renew' ) ) . '">' . esc_html__( 'Renew now', 'flx-commerce' ) . '</a>';
			}
			if ( $card && $live ) {
				$fmsg    = esc_js( __( 'Remove the card? Renewals will then be paid with the payment link we e-mail you.', 'flx-commerce' ) );
				$links[] = '<a href="' . esc_url( self::action_url( $r['id'], 'forget_card' ) ) . '" onclick="return confirm(\'' . $fmsg . '\')">' . esc_html__( 'Remove card', 'flx-commerce' ) . '</a>';
			}
			if ( in_array( $r['status'], array( 'active', 'past_due' ), true ) ) {
				$msg     = esc_js( __( 'Turn off renewal? The subscription stays valid until the end of the current period.', 'flx-commerce' ) );
				$links[] = '<a class="flxcm-sub__cancel" href="' . esc_url( self::action_url( $r['id'], 'cancel' ) ) . '" onclick="return confirm(\'' . $msg . '\')">' . esc_html__( 'Cancel subscription', 'flx-commerce' ) . '</a>';
			} elseif ( 'cancelled' === $r['status'] && strtotime( $r['period_end'] . ' UTC' ) > time() ) {
				$links[] = '<a href="' . esc_url( self::action_url( $r['id'], 'resume' ) ) . '">' . esc_html__( 'Turn renewal back on', 'flx-commerce' ) . '</a>';
			} elseif ( 'expired' === $r['status'] && (int) $r['product_id'] && get_post_status( (int) $r['product_id'] ) === 'publish' ) {
				$links[] = '<a href="' . esc_url( add_query_arg( 'flxcm_period', $r['period'], get_permalink( (int) $r['product_id'] ) ) ) . '">' . esc_html__( 'Start again', 'flx-commerce' ) . '</a>';
			}
			if ( $links ) {
				echo '<footer class="flxcm-sub__foot">' . implode( '<span aria-hidden="true">·</span>', $links ) . '</footer>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</article>';
		}
		echo '</div>';
	}

	/** İptal / yeniden açma (nonce + sahiplik). */
	public static function action() {
		if ( empty( $_GET['flxcm_sub'] ) || empty( $_GET['flxcm_do'] ) || ! is_user_logged_in() ) {
			return;
		}
		$id = absint( $_GET['flxcm_sub'] );
		$do = sanitize_key( wp_unslash( $_GET['flxcm_do'] ) );
		if ( ! in_array( $do, array( 'cancel', 'resume', 'renew', 'forget_card' ), true ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'flxcm_sub_' . $do . '_' . $id ) ) {
			return;
		}
		$res = FLXCM_Subscription_Service::account_act( $id, $do, get_current_user_id() );
		if ( '' !== $res['redirect'] ) {
			wp_safe_redirect( $res['redirect'] );
			exit;
		}
		$show = 'OK' === $res['code'] || 'FORBIDDEN' === $res['code'] || ( 'renew' === $do && 'CONFLICT' === $res['code'] );
		if ( $show && '' !== $res['notice'] ) {
			wc_add_notice( $res['notice'], 'error' === $res['type'] ? 'error' : 'notice' );
		}
		wp_safe_redirect( wc_get_account_endpoint_url( self::ENDPOINT ) );
		exit;
	}
}
