<?php
/**
 * Mağaza › Abonelikler.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Page_Subscriptions {

	public static function init() {
		add_action( 'admin_post_flxcm_sub', array( __CLASS__, 'action' ) );
	}

	private static function action_url( $id, $do ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=flxcm_sub&do=' . $do . '&id=' . (int) $id ), 'flxcm_sub_' . $do . '_' . (int) $id );
	}

	public static function render() {
		if ( ! current_user_can( FLXCM_Admin::CAP_VIEW ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-commerce' ), 403 );
		}
		if ( FLXCM_Admin::render_flux() ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification
		$args = array(
			'status'   => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'all',
			's'        => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'paged'    => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
			'per_page' => 25,
		);
		$done = isset( $_GET['done'] ) ? sanitize_key( $_GET['done'] ) : '';
		$err  = isset( $_GET['err'] ) ? sanitize_text_field( wp_unslash( $_GET['err'] ) ) : '';
		// phpcs:enable
		$c   = FLXCM_Subscriptions::counts();
		$q   = FLXCM_Subscriptions::query( $args );
		$can = current_user_can( FLXCM_Admin::CAP_MANAGE );

		FLXCM_Admin::open( __( 'Subscriptions', 'flx-commerce' ), __( 'Monthly and yearly payments. Renewal orders are created before each period ends; a stored card is charged automatically, otherwise the customer pays by bank transfer or through the payment link.', 'flx-commerce' ) );
		if ( $done ) {
			$msgs = array(
				'renewal' => __( 'Renewal order created and the customer was notified.', 'flx-commerce' ),
				'cancel'  => __( 'Renewal turned off; the subscription stays valid until the end of the period.', 'flx-commerce' ),
				'end'     => __( 'Subscription ended.', 'flx-commerce' ),
				'resume'  => __( 'Renewal is back on.', 'flx-commerce' ),
			);
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msgs[ $done ] ?? '' ) . '</p></div>';
		}
		if ( '' !== $err ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $err ) . '</p></div>';
		}

		echo '<div class="flxcm-kpis">';
		FLXCM_Admin::kpi( 'refresh', __( 'Active', 'flx-commerce' ), number_format_i18n( $c['active'] ) );
		FLXCM_Admin::kpi( 'coins', __( 'Monthly recurring revenue', 'flx-commerce' ), flxcm_money_short( $c['mrr'] ), null, __( 'Yearly plans count as 1/12', 'flx-commerce' ) );
		FLXCM_Admin::kpi( 'calendar', __( 'Renewing in 30 days', 'flx-commerce' ), number_format_i18n( $c['due'] ) );
		FLXCM_Admin::kpi( 'alert', __( 'Payment overdue', 'flx-commerce' ), number_format_i18n( $c['past_due'] ) );
		echo '</div>';

		$segs = array(
			'all'       => __( 'All', 'flx-commerce' ),
			'active'    => __( 'Active', 'flx-commerce' ),
			'past_due'  => __( 'Payment overdue', 'flx-commerce' ),
			'cancelled' => __( 'Cancelled', 'flx-commerce' ),
			'pending'   => __( 'Awaiting first payment', 'flx-commerce' ),
			'expired'   => __( 'Ended', 'flx-commerce' ),
		);
		echo '<div class="flxcm-toolbar"><div class="flxcm-chips">';
		foreach ( $segs as $k => $label ) {
			$on = $k === $args['status'];
			echo '<a class="flxcm-chip' . ( $on ? ' is-on' : '' ) . '" href="' . esc_url( flxcm_admin_url( 'subscriptions', array( 'status' => $k, 's' => $args['s'] ) ) ) . '"' . ( $on ? ' aria-current="true"' : '' ) . '>' . esc_html( $label ) . ' <span>' . esc_html( number_format_i18n( (int) ( $c[ $k ] ?? 0 ) ) ) . '</span></a>';
		}
		echo '</div><form class="flxcm-search" method="get"><input type="hidden" name="page" value="flx-commerce-subscriptions"><input type="hidden" name="status" value="' . esc_attr( $args['status'] ) . '">';
		echo '<label class="screen-reader-text" for="flxcm-s">' . esc_html__( 'Search subscriptions', 'flx-commerce' ) . '</label>';
		echo '<span class="flxcm-search__box">' . flxcm_icon( 'search', 16 ) . '<input id="flxcm-s" type="search" name="s" value="' . esc_attr( $args['s'] ) . '" placeholder="' . esc_attr__( 'Name, e-mail, product, no.', 'flx-commerce' ) . '"></span></form></div>'; // phpcs:ignore WordPress.Security.EscapeOutput

		if ( ! $q['rows'] ) {
			$empty = '' !== $args['s'] ? __( 'No subscription matches the search.', 'flx-commerce' ) : __( 'No subscriptions yet. Tick "Subscription" on a simple product and give it a monthly or yearly price.', 'flx-commerce' );
			echo '<div class="flxcm-card"><p class="flxcm-empty">' . esc_html( $empty ) . '</p></div>';
			FLXCM_Admin::close();
			return;
		}

		echo '<div class="flxcm-card flxcm-card--flush"><table class="flxcm-table flxcm-table--subs"><thead><tr>';
		echo '<th>' . esc_html__( 'Customer', 'flx-commerce' ) . '</th><th>' . esc_html__( 'Product', 'flx-commerce' ) . '</th><th class="num">' . esc_html__( 'Amount', 'flx-commerce' ) . '</th><th>' . esc_html__( 'Status', 'flx-commerce' ) . '</th><th>' . esc_html__( 'Renewal / end', 'flx-commerce' ) . '</th><th>' . esc_html__( 'Orders', 'flx-commerce' ) . '</th>';
		if ( $can ) {
			echo '<th><span class="screen-reader-text">' . esc_html__( 'Actions', 'flx-commerce' ) . '</span></th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $q['rows'] as $r ) {
			$name = '' !== trim( (string) $r['name'] ) ? $r['name'] : $r['email'];
			$init = function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( trim( (string) $name ), 0, 1 ) ) : strtoupper( substr( (string) $name, 0, 1 ) );
			echo '<tr><td data-label="' . esc_attr__( 'Customer', 'flx-commerce' ) . '"><div class="flxcm-who"><span class="flxcm-avatar" aria-hidden="true">' . esc_html( $init ) . '</span><span><a class="flxcm-who__name" href="' . esc_url( flxcm_admin_url( 'customers', array( 'email' => $r['email'] ) ) ) . '"><strong>' . esc_html( $name ) . '</strong></a><small>#' . (int) $r['id'] . ' · <a href="mailto:' . esc_attr( $r['email'] ) . '">' . esc_html( $r['email'] ) . '</a></small></span></div></td>';
			$plink = (int) $r['product_id'] ? get_edit_post_link( (int) $r['product_id'] ) : '';
			echo '<td data-label="' . esc_attr__( 'Product', 'flx-commerce' ) . '">' . ( $plink ? '<a href="' . esc_url( $plink ) . '">' . esc_html( $r['product_name'] ) . '</a>' : esc_html( $r['product_name'] ) ) . '<small class="flxcm-muted"> · ' . esc_html( FLXCM_Subscriptions::period_label( $r['period'] ) ) . '</small></td>';
			echo '<td class="num" data-label="' . esc_attr__( 'Amount', 'flx-commerce' ) . '"><strong>' . esc_html( flxcm_money( $r['amount'], $r['currency'] ) ) . '</strong></td>';
			echo '<td data-label="' . esc_attr__( 'Status', 'flx-commerce' ) . '"><span class="flxcm-badge ' . esc_attr( FLXCM_Sub_Account::status_class( $r['status'] ) ) . '">' . esc_html( FLXCM_Sub_Account::status_label( $r['status'] ) ) . '</span></td>';
			$card = FLXCM_Sub_Cards::label( $r );
			$card = '' !== $card && in_array( $r['status'], array( 'active', 'past_due' ), true ) ? '<br><small class="flxcm-muted">' . esc_html(
				/* translators: %s: card */
				sprintf( __( 'Auto: %s', 'flx-commerce' ), $card )
			) . '</small>' : '';
			echo '<td data-label="' . esc_attr__( 'Renewal / end', 'flx-commerce' ) . '">' . esc_html( FLXCM_Sub_Account::when( $r ) ) . $card . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			$ol = array();
			foreach ( array( 'parent_order_id' => __( 'first', 'flx-commerce' ), 'renewal_order_id' => __( 'awaiting', 'flx-commerce' ) ) as $col => $lbl ) {
				$o = (int) $r[ $col ] ? wc_get_order( (int) $r[ $col ] ) : null;
				if ( $o ) {
					$ol[] = '<a href="' . esc_url( $o->get_edit_order_url() ) . '">#' . esc_html( $o->get_order_number() ) . '</a> <small class="flxcm-muted">' . esc_html( $lbl ) . '</small>';
				}
			}
			if ( (int) $r['renewals'] ) {
				/* translators: %d: renewal count */
				$ol[] = '<small class="flxcm-muted">' . esc_html( sprintf( _n( '%d renewal', '%d renewals', (int) $r['renewals'], 'flx-commerce' ), (int) $r['renewals'] ) ) . '</small>';
			}
			echo '<td data-label="' . esc_attr__( 'Orders', 'flx-commerce' ) . '">' . ( $ol ? implode( '<br>', $ol ) : '—' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( $can ) {
				$acts = array();
				if ( in_array( $r['status'], array( 'active', 'past_due' ), true ) ) {
					if ( ! (int) $r['renewal_order_id'] ) {
						$acts[] = '<a class="button button-small" href="' . esc_url( self::action_url( $r['id'], 'renewal' ) ) . '">' . esc_html__( 'Create renewal order', 'flx-commerce' ) . '</a>';
					}
					$acts[] = '<a class="button button-small" href="' . esc_url( self::action_url( $r['id'], 'cancel' ) ) . '">' . esc_html__( 'Cancel at period end', 'flx-commerce' ) . '</a>';
				}
				if ( 'cancelled' === $r['status'] ) {
					$acts[] = '<a class="button button-small" href="' . esc_url( self::action_url( $r['id'], 'resume' ) ) . '">' . esc_html__( 'Turn renewal back on', 'flx-commerce' ) . '</a>';
				}
				if ( 'expired' !== $r['status'] ) {
					$acts[] = '<a class="button button-small button-link-delete" href="' . esc_url( self::action_url( $r['id'], 'end' ) ) . '" onclick="return confirm(\'' . esc_js( __( 'End this subscription now? Access closes immediately.', 'flx-commerce' ) ) . '\')">' . esc_html__( 'End now', 'flx-commerce' ) . '</a>';
				}
				echo '<td class="flxcm-actions" data-label="' . esc_attr__( 'Actions', 'flx-commerce' ) . '">' . implode( ' ', $acts ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		if ( $q['pages'] > 1 ) {
			echo '<div class="flxcm-pager">' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $q['paged'], 'total' => $q['pages'], 'prev_text' => '‹', 'next_text' => '›' ) ) ) . '</div>';
		}
		FLXCM_Admin::close();
	}

	public static function action() {
		$id = absint( $_GET['id'] ?? 0 );
		$do = sanitize_key( wp_unslash( $_GET['do'] ?? '' ) );
		check_admin_referer( 'flxcm_sub_' . $do . '_' . $id );
		if ( ! current_user_can( FLXCM_Admin::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-commerce' ), 403 );
		}
		$act  = FLXCM_Subscription_Service::admin_act( $id, $do );
		$args = array();
		if ( '' !== $act['err'] ) {
			$args['err'] = $act['err'];
		} elseif ( '' !== $act['done'] ) {
			$args['done'] = $act['done'];
		}
		$back = wp_get_referer();
		wp_safe_redirect( add_query_arg( $args, remove_query_arg( array( 'done', 'err' ), $back ? $back : flxcm_admin_url( 'subscriptions' ) ) ) );
		exit;
	}
}
