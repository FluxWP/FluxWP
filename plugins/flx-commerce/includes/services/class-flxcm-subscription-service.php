<?php
/**
 * Subscription reads and the decisions behind the admin list and the My Account endpoint.
 *
 * The mapped array is an allow-list. Subscription meta, including the provider card block
 * stored under `_card` (`d` = sealed cardUserKey + cardToken), is never copied out.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Subscription_Service {

	/**
	 * GraphQL subscription. `cardLabel` is the brand and last four digits only.
	 *
	 * @param array<string,mixed> $row Export row from FLXCM_Subscriptions::export().
	 * @return array<string,mixed>
	 */
	public static function map( array $row ): array {
		return array_merge(
			array(
				'id'             => FLXCM_Val::str( FLXCM_Val::int( $row['id'] ) ),
				'status'         => FLXCM_Val::str($row['status']),
				'hasAccess'      => (bool) $row['has_access'],
				'autoRenew'      => (bool) $row['auto_renew'],
				'userId'         => FLXCM_Val::int($row['user_id']),
				'email'          => FLXCM_Val::str($row['email']),
				'name'           => FLXCM_Val::str($row['name']),
				'productId'      => FLXCM_Val::int($row['product_id']),
				'productName'    => FLXCM_Val::str($row['product_name']),
				'period'         => FLXCM_Val::str($row['period']),
				'amount'         => flxcm_gql_money( $row['amount'], FLXCM_Val::str($row['currency']) ),
				'paymentMethod'  => FLXCM_Val::str($row['payment_method']),
				'parentOrderId'  => FLXCM_Val::int($row['parent_order_id']),
				'lastOrderId'    => FLXCM_Val::int($row['last_order_id']),
				'renewalOrderId' => FLXCM_Val::int($row['renewal_order_id']),
				'renewals'       => FLXCM_Val::int($row['renewals']),
				'startedAt'      => flxcm_gql_time( FLXCM_Val::int($row['started_at']) ),
				'periodEnd'      => flxcm_gql_time( FLXCM_Val::int($row['period_end']) ),
				'cancelledAt'    => flxcm_gql_time( FLXCM_Val::int($row['cancelled_at']) ),
				'endedAt'        => flxcm_gql_time( FLXCM_Val::int($row['ended_at']) ),
				'endReason'      => FLXCM_Val::str($row['end_reason']),
				'cardLabel'      => FLXCM_Val::str($row['card']),
				'autoCharge'     => (bool) $row['auto_charge'],
				'createdAt'      => flxcm_gql_time( FLXCM_Val::int($row['created_at']) ),
			),
			self::account_fields( $row )
		);
	}

	/**
	 * Fields the My Account card computes (next charge, pay link, renew-now, permalink).
	 * An order edit URL is returned only to someone who can open it.
	 *
	 * @param array<string,mixed> $row Export row. Times are Unix timestamps.
	 * @return array{productUrl:string,nextChargeAt:array{iso:string,timestamp:int,label:string,relative:string}|null,renewNow:bool,payUrl:string,lastOrderUrl:string}
	 */
	private static function account_fields( array $row ): array {
		$product_id = FLXCM_Val::int($row['product_id']);
		$product    = '';
		if ( $product_id > 0 && 'publish' === get_post_status( $product_id ) ) {
			$link = get_permalink( $product_id );
			$product = is_string( $link ) ? $link : '';
		}
		$end    = FLXCM_Val::int($row['period_end']);
		$status = FLXCM_Val::str($row['status']);
		$live   = in_array( $status, array( 'active', 'past_due' ), true );
		$renew  = null;
		$due    = false;
		$pay    = '';
		$next   = null;
		$last   = '';
		if ( function_exists( 'wc_get_order' ) ) {
			$order = FLXCM_Val::int($row['renewal_order_id']) ? flxcm_order( $row['renewal_order_id'] ) : null;
			$due   = $order instanceof WC_Order && $order->has_status( array( 'pending', 'on-hold', 'failed' ) ) && ! $order->is_paid();
			if ( $due && class_exists( 'FLXCM_Sub_Cards' ) && FLXCM_Sub_Cards::auto_pending( $order ) ) {
				$next = flxcm_gql_time( max( $end, FLXCM_Val::int($order->get_meta( '_flxcm_charge_next' )) ) );
			} elseif ( $due ) {
				$pay = FLXCM_Val::str(FLXCM_Subscriptions::pay_url( $order ));
			}
			$last_id = FLXCM_Val::int($row['last_order_id']);
			$last_o  = $last_id ? flxcm_order( $last_id ) : null;
			if ( $last_o instanceof WC_Order ) {
				if ( current_user_can( 'edit_shop_orders' ) ) {
					$last = FLXCM_Val::str($last_o->get_edit_order_url());
				} elseif ( self::viewer_owns( $row ) ) {
					$last = FLXCM_Val::str($last_o->get_view_order_url());
				}
			}
		}
		$window = $end - max( 0, FLXCM_Val::int(flxcm_setting( 'sub_renew_days' )) ) * DAY_IN_SECONDS;
		return array(
			'productUrl'   => $product,
			'nextChargeAt' => $next,
			'renewNow'     => ! $due && $live && $end > 0 && time() >= $window,
			'payUrl'       => $pay,
			'lastOrderUrl' => $last,
		);
	}

	/**
	 * @param array<string,mixed> $row Export row.
	 */
	private static function viewer_owns( array $row ): bool {
		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return false;
		}
		if ( FLXCM_Val::int($row['user_id']) === $uid ) {
			return true;
		}
		$user = get_userdata( $uid );
		return $user instanceof WP_User && '' !== $user->user_email && strtolower( $user->user_email ) === strtolower( FLXCM_Val::str($row['email']) );
	}

	/**
	 * @param array<string,mixed>|null $export Export row or null.
	 * @return array<string,mixed>|null
	 */
	public static function map_or_null( $export ): ?array {
		return is_array( $export ) ? self::map( $export ) : null;
	}

	/**
	 * @param array<string,mixed> $args status, search.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public static function page( array $args, int $offset, int $limit ): array {
		$status = sanitize_key( FLXCM_Val::str(( $args['status'] ?? '' )) );
		$q      = FLXCM_Subscriptions::query(
			array(
				'status'   => '' !== $status && 'all' !== $status ? $status : '',
				's'        => sanitize_text_field( FLXCM_Val::str(( $args['search'] ?? '' )) ),
				'per_page' => $limit,
				'offset'   => $offset,
			)
		);
		$items = array();
		foreach ( $q['rows'] as $row ) {
			$export = FLXCM_Subscriptions::export( $row );
			if ( is_array( $export ) ) {
				$items[] = self::map( $export );
			}
		}
		return array(
			'items' => $items,
			'total' => FLXCM_Val::int($q['total']),
		);
	}

	/**
	 * Counts. MRR needs WooCommerce's currency; without it the money figure is zero and the counts still come from the table.
	 *
	 * @return array{all:int,pending:int,active:int,pastDue:int,cancelled:int,expired:int,mrr:array{amount:float,currency:string,formatted:string},due:int}
	 */
	public static function counts(): array {
		if ( ! function_exists( 'get_woocommerce_currency' ) ) {
			global $wpdb;
			$t   = FLXCM_Val::str( FLXCM_Subscriptions::table() );
			$out = array_fill_keys( FLXCM_Subscriptions::STATUSES, 0 );
			$rows = ( $wpdb instanceof wpdb ) ? $wpdb->get_results( "SELECT status, COUNT(*) n FROM {$t} GROUP BY status", ARRAY_A ) : array(); // phpcs:ignore WordPress.DB
			foreach ( FLXCM_Val::arr( $rows ) as $r ) {
				$r = FLXCM_Val::map( $r );
				$status = FLXCM_Val::str( $r['status'] ?? '' );
				if ( isset( $out[ $status ] ) ) {
					$out[ $status ] = FLXCM_Val::int( $r['n'] ?? 0 );
				}
			}
			$all = array_sum( $out );
			return array(
				'all'       => $all,
				'pending'   => FLXCM_Val::int($out['pending']),
				'active'    => FLXCM_Val::int($out['active']),
				'pastDue'   => FLXCM_Val::int($out['past_due']),
				'cancelled' => FLXCM_Val::int($out['cancelled']),
				'expired'   => FLXCM_Val::int($out['expired']),
				'mrr'       => flxcm_gql_money( 0 ),
				'due'       => 0,
			);
		}
		$c = FLXCM_Subscriptions::counts();
		return array(
			'all'       => FLXCM_Val::int($c['all']),
			'pending'   => FLXCM_Val::int($c['pending']),
			'active'    => FLXCM_Val::int($c['active']),
			'pastDue'   => FLXCM_Val::int($c['past_due']),
			'cancelled' => FLXCM_Val::int($c['cancelled']),
			'expired'   => FLXCM_Val::int($c['expired']),
			'mrr'       => flxcm_gql_money( $c['mrr'] ),
			'due'       => FLXCM_Val::int($c['due']),
		);
	}

	/**
	 * Admin row action. The page still redirects; this only decides.
	 *
	 * @return array{err:string,done:string}
	 */
	/**
	 * Classic admin-post decision. Only a failed renewal sets an error; the other
	 * actions redirect as "done" even when the row was already in that state.
	 *
	 * @return array{err:string,done:string}
	 */
	public static function admin_act( int $id, string $do ): array {
		$err = '';
		switch ( $do ) {
			case 'renewal':
				if ( ! flxcm_wc_ready() ) {
					$err = __( 'WooCommerce is required.', 'flx-commerce' );
					break;
				}
				$r = FLXCM_Subscriptions::create_renewal( $id, true );
				if ( is_wp_error( $r ) ) {
					$err = $r->get_error_message();
				}
				break;
			case 'cancel':
				FLXCM_Subscriptions::cancel( $id, false, 'admin' );
				break;
			case 'end':
				FLXCM_Subscriptions::cancel( $id, true, 'admin' );
				break;
			case 'resume':
				FLXCM_Subscriptions::resume( $id );
				break;
		}
		return array(
			'err'  => $err,
			'done' => '' === $err ? $do : '',
		);
	}

	/**
	 * GraphQL admin action. Missing rows are NOT_FOUND; a no-op is CONFLICT.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function change( int $id, string $do ) {
		$before = FLXCM_Subscriptions::export( $id );
		if ( ! is_array( $before ) ) {
			return new WP_Error( 'flxcm_missing', __( 'Subscription not found.', 'flx-commerce' ), array( 'status' => 404 ) );
		}
		if ( 'renewal' === $do ) {
			if ( ! flxcm_wc_ready() ) {
				return new WP_Error( 'flxcm_wc', __( 'WooCommerce is required.', 'flx-commerce' ), array( 'status' => 400 ) );
			}
			$r = FLXCM_Subscriptions::create_renewal( $id, true );
			if ( is_wp_error( $r ) ) {
				return new WP_Error( 'flxcm_state', $r->get_error_message(), array( 'status' => 409 ) );
			}
		} elseif ( 'cancel' === $do ) {
			if ( ! FLXCM_Subscriptions::cancel( $id, false, 'admin' ) ) {
				return new WP_Error( 'flxcm_state', __( 'This subscription cannot be changed.', 'flx-commerce' ), array( 'status' => 409 ) );
			}
		} elseif ( 'end' === $do ) {
			if ( ! FLXCM_Subscriptions::cancel( $id, true, 'admin' ) ) {
				return new WP_Error( 'flxcm_state', __( 'This subscription cannot be changed.', 'flx-commerce' ), array( 'status' => 409 ) );
			}
		} elseif ( 'resume' === $do ) {
			if ( ! FLXCM_Subscriptions::resume( $id ) ) {
				return new WP_Error( 'flxcm_state', __( 'This subscription cannot be changed.', 'flx-commerce' ), array( 'status' => 409 ) );
			}
		} else {
			return new WP_Error( 'flxcm_input', __( 'Unknown action.', 'flx-commerce' ), array( 'status' => 400 ) );
		}
		$after = FLXCM_Subscriptions::export( $id );
		return array( 'subscription' => is_array( $after ) ? self::map( FLXCM_Val::map( $after ) ) : self::map( FLXCM_Val::map( $before ) ) );
	}

	/**
	 * Subscriptions the user may act on (same set as the account page).
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function mine( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		$out = array();
		foreach ( FLXCM_Val::arr( flxcm_user_subscriptions( $user_id ) ) as $row ) {
			if ( is_array( $row ) ) {
				$out[] = self::map( FLXCM_Val::map( $row ) );
			}
		}
		return $out;
	}

	/**
	 * Whether this user owns the subscription (user id or the account e-mail).
	 */
	public static function owns( int $id, int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		$row = FLXCM_Subscriptions::export( $id );
		if ( ! is_array( $row ) ) {
			return false;
		}
		if ( FLXCM_Val::int($row['user_id']) === $user_id ) {
			return true;
		}
		$user = get_userdata( $user_id );
		return $user instanceof WP_User && '' !== $user->user_email && strtolower( $user->user_email ) === strtolower( FLXCM_Val::str($row['email']) );
	}

	/**
	 * My Account action. Notices are the same strings the account page printed.
	 *
	 * @return array{code:string,type:string,notice:string,redirect:string,subscription:array<string,mixed>|null}
	 */
	public static function account_act( int $id, string $do, int $user_id ): array {
		$fail = static function ( string $code, string $notice ): array {
			return array(
				'code'         => $code,
				'type'         => 'error',
				'notice'       => $notice,
				'redirect'     => '',
				'subscription' => null,
			);
		};
		if ( ! is_array( FLXCM_Subscriptions::export( $id ) ) ) {
			return $fail( 'NOT_FOUND', __( 'Subscription not found.', 'flx-commerce' ) );
		}
		$allowed = false;
		foreach ( self::mine( $user_id ) as $mine ) {
			if ( FLXCM_Val::str($mine['id']) === FLXCM_Val::str($id) ) {
				$allowed = true;
			}
		}
		if ( ! $allowed ) {
			return $fail( 'FORBIDDEN', __( 'You are not allowed to do this.', 'flx-commerce' ) );
		}
		if ( 'cancel' === $do ) {
			$sub = FLXCM_Subscriptions::cancel( $id, false, 'customer' );
			if ( ! is_array( $sub ) ) {
				return $fail( 'CONFLICT', __( 'This subscription cannot be changed.', 'flx-commerce' ) );
			}
			$notice = 'expired' === $sub['status']
				? __( 'Your subscription has ended.', 'flx-commerce' )
				/* translators: %s: date */
				: sprintf( __( 'Renewal is off. Your subscription stays valid until %s.', 'flx-commerce' ), FLXCM_Val::str( flxcm_date( FLXCM_Val::str( $sub['period_end'] ?? '' ) ) ) );
			return array(
				'code'         => 'OK',
				'type'         => 'success',
				'notice'       => $notice,
				'redirect'     => '',
				'subscription' => self::map( FLXCM_Val::map( $sub ) ),
			);
		}
		if ( 'forget_card' === $do ) {
			FLXCM_Sub_Cards::forget( $id );
			$row = FLXCM_Subscriptions::row( $id );
			$o   = ( flxcm_wc_ready() && $row && FLXCM_Val::int($row['renewal_order_id']) ) ? flxcm_order( $row['renewal_order_id'] ) : null;
			if ( $o && ! $o->is_paid() && $o->get_meta( '_flxcm_card_auto' ) ) {
				$o->delete_meta_data( '_flxcm_card_auto' );
				$o->add_order_note( __( 'The customer removed the stored card; this renewal is paid with the payment link.', 'flx-commerce' ) );
				$o->save();
			}
			$export = FLXCM_Subscriptions::export( $id );
			return array(
				'code'         => 'OK',
				'type'         => 'success',
				'notice'       => __( 'The card was removed. We will e-mail you a payment link before each renewal.', 'flx-commerce' ),
				'redirect'     => '',
				'subscription' => self::map( FLXCM_Val::map( $export ) ),
			);
		}
		if ( 'renew' === $do ) {
			if ( ! flxcm_wc_ready() ) {
				return $fail( 'INVALID_INPUT', __( 'WooCommerce is required.', 'flx-commerce' ) );
			}
			$o = FLXCM_Subscriptions::create_renewal( $id, true );
			if ( $o instanceof WC_Order ) {
				$export = FLXCM_Subscriptions::export( $id );
				return array(
					'code'         => 'OK',
					'type'         => 'success',
					'notice'       => '',
					'redirect'     => FLXCM_Val::str( FLXCM_Subscriptions::pay_url( $o ) ),
					'subscription' => self::map( FLXCM_Val::map( $export ) ),
				);
			}
			return $fail( 'CONFLICT', flxcm_renewal_error( $o ) );
		}
		if ( 'resume' === $do ) {
			$sub = FLXCM_Subscriptions::resume( $id );
			if ( ! is_array( $sub ) ) {
				return $fail( 'CONFLICT', __( 'This subscription cannot be changed.', 'flx-commerce' ) );
			}
			return array(
				'code'         => 'OK',
				'type'         => 'success',
				'notice'       => __( 'Renewal is back on.', 'flx-commerce' ),
				'redirect'     => '',
				'subscription' => self::map( FLXCM_Val::map( $sub ) ),
			);
		}
		return $fail( 'INVALID_INPUT', __( 'Unknown action.', 'flx-commerce' ) );
	}

	/**
	 * One bounded renewal pass (the hourly tick). The shutdown pump and cron stay in place.
	 *
	 * @return array{ran:bool,at:array{iso:string,timestamp:int,label:string,relative:string}|null}
	 */
	public static function renewals_run(): array {
		FLXCM_Subscriptions::tick();
		$at = FLXCM_Val::int(get_option( FLXCM_Subscriptions::HEARTBEAT, 0 ));
		flxcm_gql_purge( array( 'flx-commerce:subscriptions' ) );
		return array(
			'ran' => $at > 0,
			'at'  => $at > 0 ? flxcm_gql_time( $at ) : null,
		);
	}

	/**
	 * @return array{ran:bool,at:array{iso:string,timestamp:int,label:string,relative:string}|null}
	 */
	public static function renewal_status(): array {
		$at = FLXCM_Val::int(get_option( FLXCM_Subscriptions::HEARTBEAT, 0 ));
		return array(
			'ran' => $at > 0,
			'at'  => $at > 0 ? flxcm_gql_time( $at ) : null,
		);
	}
}
