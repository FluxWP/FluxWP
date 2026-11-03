<?php
/**
 * Flux Donate: recurring donations charged through iyzico by Flux Donate itself.
 *
 * iyzico stores the card (cardUserKey + cardToken) at the first payment; Flux Donate keeps both
 * encrypted in its recurring donation (wp_flxb_subscriptions.meta, never shown or exported)
 * and charges them on each due date:
 *
 *  - WP-cron hook `flxb_iyzico_renewals` (hourly; separate from the iyzico Subscription plugin's
 *    iyzico_subscription_* hooks) → Payment create with the stored card, paymentGroup
 *    SUBSCRIPTION. Each renewal is a new donation linked to the recurring donation. No
 *    WooCommerce order is created.
 *  - A failed charge is tried again (default after 1 and 3 days, filter
 *    `flxb_iyzico_retry_delays`); after the last attempt the recurring donation becomes
 *    "past due", an admin notice appears and the donor gets an e-mail.
 *  - A charge whose answer was lost (timeout) is looked up by its conversation id before any
 *    new attempt, so a card is never charged twice for one period.
 *  - Cancel: Donations › Recurring donations, or the donor's own link (signed, in the e-mails).
 *    Canceling deletes the stored card references.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Iyzico_Recurring {

	const HOOK       = 'flxb_iyzico_renewals';
	const NOTICE_OPT = 'flxb_iyzico_failed';
	const BATCH      = 20;

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		add_action( 'admin_post_flxb_iyzico_notice', array( __CLASS__, 'dismiss' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve_html' ), 100, 3 ); // after core's CORS headers
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			update_option( 'flxb_cron_first_seen', time(), false );
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::HOOK );
		}
	}

	/** Attempts per due date (first try included). */
	public static function max_attempts() {
		return 1 + count( self::delays() );
	}

	/** Seconds to wait before each retry. */
	public static function delays() {
		$d = array_values( array_filter( array_map( 'intval', (array) apply_filters( 'flxb_iyzico_retry_delays', array( DAY_IN_SECONDS, 2 * DAY_IN_SECONDS ) ) ) ) );
		return array_slice( $d, 0, 10 );
	}

	/* ------------------------------------------------------------------
	 * First payment
	 * ------------------------------------------------------------------ */

	/**
	 * The first payment of a recurring gift succeeded with a stored card: keep the card and plan
	 * the next charge.
	 *
	 * @param object $row  emsfb_pay_ row (payment_type subscription, status active).
	 * @param string $card_user_key iyzico cardUserKey.
	 * @param string $card_token    iyzico cardToken.
	 * @param int    $donation_id   The first donation (0 when the form is not a donation form).
	 * @param array  $answer        iyzico retrieve answer.
	 */
	public static function start( $row, $card_user_key, $card_token, $donation_id, array $answer ) {
		$ref = (string) $row->subscription_id;
		$m   = json_decode( (string) $row->meta, true );
		$m   = is_array( $m ) ? $m : array();
		$sub = FLXB_Subscriptions::get_by_ref( 'iyzico', $ref );
		$interval = strtolower( (string) $row->interval_unit );
		if ( ! $sub ) {
			/* The form is not counted as a donation form: still keep the recurring gift. */
			$donor = class_exists( 'FLXB_Donors' ) ? FLXB_Donors::upsert( array_merge( (array) ( $m['contact'] ?? array() ), array( 'allow_anonymous' => true ) ) ) : 0;
			FLXB_Subscriptions::upsert(
				array(
					'donor_id'       => $donor,
					'amount'         => (float) $row->amount,
					'currency'       => (string) $row->currency,
					'interval_unit'  => $interval,
					'status'         => 'active',
					'provider'       => 'iyzico',
					'provider_ref'   => $ref,
					'form_id'        => (int) $row->form_id,
					'type_id'        => (int) ( $m['flxb']['type_id'] ?? 0 ),
					'track'          => (string) $row->track,
					'next_charge_at' => FLXB_Subscriptions::next_charge( $interval ),
				)
			);
			$sub = FLXB_Subscriptions::get_by_ref( 'iyzico', $ref );
		}
		if ( ! $sub ) {
			return;
		}
		$d    = $donation_id ? FLXB_Donations::get( $donation_id ) : null;
		$snap = isset( $m['flxb'] ) && is_array( $m['flxb'] ) ? $m['flxb'] : array();
		$info = FLXB_Iyzico_Api::payment_info( $answer );
		FLXB_Subscriptions::update(
			(int) $sub['id'],
			array(
				'card'        => array( 'k' => FLXB_Iyzico_Api::seal( $card_user_key ), 't' => FLXB_Iyzico_Api::seal( $card_token ), 'label' => $info['card'] ),
				'snapshot'    => array(
					'option_key'   => (string) ( $snap['option_key'] ?? ( $d['option_key'] ?? '' ) ),
					'option_label' => (string) ( $snap['option_label'] ?? ( $d['option_label'] ?? '' ) ),
					'quantity'     => (int) ( $snap['quantity'] ?? ( $d['quantity'] ?? 1 ) ),
					'unit_price'   => (float) ( $snap['unit_price'] ?? ( $d['unit_price'] ?? $row->amount ) ),
					'lines'        => isset( $snap['lines'] ) && is_array( $snap['lines'] ) ? $snap['lines'] : array(),
				),
				'buyer'       => array_intersect_key( (array) ( $m['contact'] ?? array() ), array_flip( array( 'name', 'email', 'phone' ) ) ),
				'buyer_ip'    => (string) ( $m['buyer_ip'] ?? '' ),
				'form_name'   => (string) $row->form_name,
				'first_payment' => $info['paymentId'],
				'retry_count' => 0,
				'sandbox'     => ! empty( $m['sandbox'] ),
			),
			array( 'status' => 'active', 'next_charge_at' => FLXB_Subscriptions::next_charge( $interval, current_time( 'mysql', true ) ) )
		);
		$sub = FLXB_Subscriptions::get( (int) $sub['id'] );
		self::mail_donor( $sub, 'started' );
	}

	/* ------------------------------------------------------------------
	 * Renewals
	 * ------------------------------------------------------------------ */

	/** Cron: charge every iyzico recurring gift that is due. */
	public static function run() {
		global $wpdb;
		if ( class_exists( 'FLXB_System_Checks' ) ) {
			FLXB_System_Checks::beat();
		}
		$t   = flxb_table( 'subscriptions' );
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE provider = 'iyzico' AND status = 'active' AND next_charge_at IS NOT NULL AND next_charge_at <= %s ORDER BY next_charge_at ASC LIMIT %d", current_time( 'mysql', true ), self::BATCH ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out = array();
		foreach ( (array) $ids as $id ) {
			$out[ (int) $id ] = self::charge( (int) $id );
		}
		if ( $out ) {
			update_option( 'flxb_iyzico_last_run', array( 'time' => time(), 'result' => $out ), false );
		}
		return $out;
	}

	/**
	 * Charge one due recurring gift.
	 *
	 * @return string paid | retry | past_due | skipped | error
	 */
	public static function charge( $id ) {
		$lock = 'flxb_iyz_renew_' . (int) $id;
		if ( ! add_option( $lock, time(), '', false ) ) {
			if ( time() - (int) get_option( $lock ) < 15 * MINUTE_IN_SECONDS ) {
				return 'skipped';
			}
			update_option( $lock, time(), false );
		}
		try {
			return self::charge_locked( (int) $id );
		} finally {
			delete_option( $lock );
		}
	}

	private static function charge_locked( $id ) {
		$s = FLXB_Subscriptions::get( $id );
		if ( ! $s || 'iyzico' !== $s['provider'] || 'active' !== $s['status'] || empty( $s['next_charge_at'] ) || strtotime( $s['next_charge_at'] . ' UTC' ) > time() ) {
			return 'skipped';
		}
		$m       = FLXB_Subscriptions::meta( $s );
		$period  = ! empty( $m['period_due'] ) ? (string) $m['period_due'] : (string) $s['next_charge_at'];
		$attempt = (int) ( $m['retry_count'] ?? 0 ) + 1;
		$api     = FLXB_Iyzico_Api::make();
		if ( ! $api ) {
			return 'error'; // keys missing: try again next hour, no attempt counted
		}

		/* An earlier attempt whose answer never came: ask iyzico before charging again. */
		if ( ! empty( $m['uncertain'] ) ) {
			try {
				$d = $api->post( '/payment/detail', array( 'locale' => FLXB_Iyzico_Api::locale(), 'conversationId' => (string) $m['uncertain'], 'paymentConversationId' => (string) $m['uncertain'] ) );
			} catch ( FLXB_Iyzico_Error $e ) {
				return 'error';
			}
			if ( FLXB_Iyzico_Api::ok( $d ) && 'SUCCESS' === ( $d['paymentStatus'] ?? '' ) && FLXB_Iyzico_Api::minor( $d['paidPrice'] ?? 0 ) >= FLXB_Iyzico_Api::minor( $s['amount'] ) ) {
				return self::paid( $s, $m, $period, $d );
			}
			FLXB_Subscriptions::update( $id, array( 'uncertain' => null ) );
			$m['uncertain'] = null;
		}

		$k = FLXB_Iyzico_Api::open( $m['card']['k'] ?? '' );
		$t = FLXB_Iyzico_Api::open( $m['card']['t'] ?? '' );
		if ( '' === $k || '' === $t ) {
			return self::failed( $s, $m, $period, self::max_attempts(), __( 'No stored card for this recurring donation.', 'flx-donate' ) );
		}
		$conv  = 'flxbr-' . $id . '-' . gmdate( 'YmdHi', strtotime( $period . ' UTC' ) ) . '-' . $attempt;
		$buyer = (array) ( $m['buyer'] ?? array() );
		list( $b, $addr ) = FLXB_Iyzico_Api::buyer( $buyer, (string) ( $buyer['email'] ?? '' ), (string) ( $m['buyer_ip'] ?? '' ) );
		if ( '' === $b['ip'] ) {
			$b['ip'] = '127.0.0.1';
		}
		$name = '' !== (string) ( $m['form_name'] ?? '' ) ? (string) $m['form_name'] : __( 'Recurring donation', 'flx-donate' );
		$body = array(
			'locale'         => FLXB_Iyzico_Api::locale(),
			'conversationId' => $conv,
			'price'          => FLXB_Iyzico_Api::price( $s['amount'] ),
			'paidPrice'      => FLXB_Iyzico_Api::price( $s['amount'] ),
			'installment'    => 1,
			'paymentChannel' => 'WEB',
			'basketId'       => $conv,
			'paymentGroup'   => 'SUBSCRIPTION',
			'paymentCard'    => array( 'cardUserKey' => $k, 'cardToken' => $t ),
			'buyer'          => $b,
			'billingAddress' => $addr,
			'basketItems'    => FLXB_Iyzico_Api::basket( $s['amount'], (array) ( $m['snapshot']['lines'] ?? array() ), $name, 'flxbs' . $id ),
			'currency'       => strtoupper( (string) $s['currency'] ),
		);
		$body = (array) apply_filters( 'flxb_iyzico_renewal_request', $body, $s );
		$wc = class_exists( 'FLXB_WC_Recurring' ) && FLXB_WC_Recurring::handles( $s );
		if ( $wc ) {
			/* 2.0: the renewal is a WooCommerce order, opened before the charge and reused for retries. */
			FLXB_WC_Recurring::renewal_order( $s, $period );
		}
		FLXB_Subscriptions::update( $id, array( 'uncertain' => $conv, 'period_due' => $period ) );
		try {
			$r = $api->payment_create( $body );
		} catch ( FLXB_Iyzico_Error $e ) {
			return 'error'; // answer lost: 'uncertain' is checked on the next run
		}
		FLXB_Subscriptions::update( $id, array( 'uncertain' => null ) );
		$m = FLXB_Subscriptions::meta( FLXB_Subscriptions::get( $id ) );
		$ok = FLXB_Iyzico_Api::ok( $r )
			&& ( ! isset( $r['paymentStatus'] ) || 'SUCCESS' === $r['paymentStatus'] )
			&& FLXB_Iyzico_Api::minor( $r['paidPrice'] ?? 0 ) >= FLXB_Iyzico_Api::minor( $s['amount'] )
			&& strtoupper( (string) ( $r['currency'] ?? $s['currency'] ) ) === strtoupper( (string) $s['currency'] )
			&& -1 !== (int) ( $r['fraudStatus'] ?? 1 );
		if ( $ok ) {
			return self::paid( $s, $m, $period, $r );
		}
		$err = FLXB_Iyzico_Api::error_text( $r );
		return self::failed( $s, $m, $period, $attempt, '' !== $err ? $err : __( 'The card was not charged.', 'flx-donate' ) );
	}

	private static function paid( array $s, array $m, $period, array $r ) {
		$info = FLXB_Iyzico_Api::payment_info( $r );
		$snap = (array) ( $m['snapshot'] ?? array() );
		$id   = class_exists( 'FLXB_WC_Recurring' ) && FLXB_WC_Recurring::handles( $s ) ? FLXB_WC_Recurring::renewal_paid( $s, $period, $r ) : 0;
		$id   = $id ? $id : FLXB_Donations::record(
			array(
				'provider'        => 'iyzico',
				'provider_ref'    => '' !== $info['paymentId'] ? $info['paymentId'] : 'renewal-' . $s['id'] . '-' . $period,
				'amount'          => (float) $s['amount'],
				'currency'        => (string) $s['currency'],
				'type'            => 'recurring',
				'status'          => 'completed',
				'form_id'         => (int) $s['form_id'],
				'donor_id'        => (int) $s['donor_id'],
				'subscription_id' => (int) $s['id'],
				'type_id'         => (int) $s['type_id'],
				'option_key'      => (string) ( $snap['option_key'] ?? '' ),
				'option_label'    => (string) ( $snap['option_label'] ?? '' ),
				'quantity'        => max( 1, (int) ( $snap['quantity'] ?? 1 ) ),
				'unit_price'      => (float) ( $snap['unit_price'] ?? $s['amount'] ),
				'lines'           => (array) ( $snap['lines'] ?? array() ),
				'source'          => 'renewal',
				'created_at'      => current_time( 'mysql', true ),
				'meta'            => array( 'renewal' => true, 'subscription' => (string) $s['provider_ref'], 'period' => $period, 'iyzico' => $info ),
			)
		);
		$next = FLXB_Subscriptions::next_charge( $s['interval_unit'], $period );
		while ( strtotime( $next . ' UTC' ) <= time() ) {
			$next = FLXB_Subscriptions::next_charge( $s['interval_unit'], $next );
		}
		FLXB_Subscriptions::update(
			(int) $s['id'],
			array( 'retry_count' => 0, 'period_due' => null, 'last_error' => null, 'uncertain' => null, 'last_paid_at' => current_time( 'mysql', true ), 'renewals' => (int) ( $m['renewals'] ?? 0 ) + 1 ),
			array( 'status' => 'active', 'next_charge_at' => $next )
		);
		do_action( 'flxb_iyzico_renewal_paid', (int) $s['id'], $id, $r );
		return 'paid';
	}

	private static function failed( array $s, array $m, $period, $attempt, $error ) {
		$delays = self::delays();
		if ( class_exists( 'FLXB_WC_Recurring' ) && FLXB_WC_Recurring::handles( $s ) ) {
			FLXB_WC_Recurring::renewal_failed( $s, $period, $error, $attempt >= self::max_attempts() );
		}
		if ( $attempt < self::max_attempts() ) {
			FLXB_Subscriptions::update(
				(int) $s['id'],
				array( 'retry_count' => $attempt, 'period_due' => $period, 'last_error' => $error, 'last_failed_at' => current_time( 'mysql', true ) ),
				array( 'next_charge_at' => gmdate( 'Y-m-d H:i:s', time() + $delays[ $attempt - 1 ] ) )
			);
			do_action( 'flxb_iyzico_renewal_failed', (int) $s['id'], $error, false );
			return 'retry';
		}
		FLXB_Subscriptions::update(
			(int) $s['id'],
			array( 'retry_count' => $attempt, 'period_due' => $period, 'last_error' => $error, 'last_failed_at' => current_time( 'mysql', true ) ),
			array( 'status' => 'past_due' )
		);
		$list               = (array) get_option( self::NOTICE_OPT, array() );
		$list[ $s['id'] ]   = time();
		update_option( self::NOTICE_OPT, $list, false );
		self::mail_donor( FLXB_Subscriptions::get( (int) $s['id'] ), 'failed' );
		do_action( 'flxb_iyzico_renewal_failed', (int) $s['id'], $error, true );
		return 'past_due';
	}

	/** Stop a recurring gift and forget its card. */
	public static function cancel( $id, $by = 'admin' ) {
		$s = FLXB_Subscriptions::get( (int) $id );
		if ( ! $s ) {
			return false;
		}
		FLXB_Subscriptions::update( (int) $id, array( 'card' => null, 'uncertain' => null, 'canceled_by' => $by ), array( 'status' => 'canceled', 'canceled_at' => current_time( 'mysql', true ) ) );
		$list = (array) get_option( self::NOTICE_OPT, array() );
		if ( isset( $list[ $id ] ) ) {
			unset( $list[ $id ] );
			update_option( self::NOTICE_OPT, $list, false );
		}
		if ( class_exists( 'FLXB_Payment_Records' ) ) {
			$row = FLXB_Payment_Records::get_by_reference( 'iyzico', (string) $s['provider_ref'], 'subscription_id' );
			if ( $row ) {
				FLXB_Payment_Records::update( $row->id, array( 'status' => 'canceled' ) );
			}
		}
		do_action( 'flxb_iyzico_subscription_canceled', (int) $id, $by );
		return true;
	}

	/* ------------------------------------------------------------------
	 * Donor's cancel link
	 * ------------------------------------------------------------------ */

	public static function cancel_key( array $s ) {
		return substr( hash_hmac( 'sha256', 'flxb-cancel|' . (int) $s['id'] . '|' . (string) $s['created_at'] . '|' . (string) $s['provider_ref'], wp_salt( 'auth' ) ), 0, 32 );
	}

	public static function cancel_url( array $s ) {
		return add_query_arg( array( 's' => (int) $s['id'], 'k' => self::cancel_key( $s ) ), rest_url( 'flxb/v1/iyzico/cancel' ) );
	}

	public static function routes() {
		register_rest_route(
			'flxb/v1',
			'/iyzico/cancel',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'permission_callback' => '__return_true', // the signed key is the permission
				'callback'            => array( __CLASS__, 'donor_cancel' ),
			)
		);
	}

	public static function donor_cancel( WP_REST_Request $r ) {
		$id  = absint( $r->get_param( 's' ) );
		$key = (string) $r->get_param( 'k' );
		$s   = $id ? FLXB_Subscriptions::get( $id ) : null;
		$res = function ( $html, $code = 200 ) {
			return new WP_REST_Response( array( '__flxb_html' => $html ), $code, array( 'Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex' ) );
		};
		if ( ! $s || 'iyzico' !== $s['provider'] || ! hash_equals( self::cancel_key( $s ), $key ) ) {
			return $res( self::page_html( __( 'Link not valid', 'flx-donate' ), '<p class="m">' . esc_html__( 'This link is not valid. Please contact us to cancel your recurring donation.', 'flx-donate' ) . '</p>', false ), 404 );
		}
		$what = esc_html( sprintf( /* translators: 1: amount, 2: interval (Monthly…) */ __( '%1$s · %2$s', 'flx-donate' ), flxb_money( $s['amount'], $s['currency'] ), flxb_interval_label( $s['interval_unit'] ) ) );
		if ( 'canceled' === $s['status'] ) {
			return $res( self::page_html( __( 'Recurring donation canceled', 'flx-donate' ), '<p class="m">' . esc_html__( 'This recurring donation is canceled. No further payments will be taken.', 'flx-donate' ) . '</p><p class="c">' . $what . '</p>', true ) );
		}
		if ( 'POST' === $r->get_method() ) {
			self::cancel( $id, 'donor' );
			return $res( self::page_html( __( 'Recurring donation canceled', 'flx-donate' ), '<p class="m">' . esc_html__( 'Your recurring donation has been canceled. No further payments will be taken. Thank you for your support.', 'flx-donate' ) . '</p><p class="c">' . $what . '</p>', true ) );
		}
		/* GET only asks (mail scanners open links). */
		$form = '<p class="m">' . esc_html__( 'Do you want to cancel this recurring donation? No further payments will be taken.', 'flx-donate' ) . '</p><p class="c">' . $what . '</p>'
			. '<form method="post" action="' . esc_url( self::cancel_url( $s ) ) . '"><button type="submit" class="b">' . esc_html__( 'Cancel my recurring donation', 'flx-donate' ) . '</button></form>';
		return $res( self::page_html( __( 'Cancel recurring donation', 'flx-donate' ), $form, true ) );
	}

	/** Prints the HTML pages of the flxb/v1/iyzico routes as they are. */
	public static function serve_html( $served, $result, $request ) {
		if ( $served || ! $result instanceof WP_HTTP_Response || ! $request instanceof WP_REST_Request || 0 !== strpos( (string) $request->get_route(), '/flxb/v1/iyzico/' ) ) {
			return $served;
		}
		$d = $result->get_data();
		if ( ! is_array( $d ) || ! isset( $d['__flxb_html'] ) ) {
			return $served;
		}
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
		}
		echo $d['__flxb_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in page()
		return true;
	}

	/** Small branded page (callback, cancel). $body is already escaped. */
	public static function page_html( $title, $body, $ok, $script = '' ) {
		$b = flxb_brand();
		$h = '<!doctype html><html lang="' . esc_attr( str_replace( '_', '-', determine_locale() ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html( $title . ' · ' . $b['name'] ) . '</title>';
		$h .= '<style>:root{--b:' . esc_html( $b['primary'] ) . ';color-scheme:light dark}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;background:#f3f5f4;color:#111827;font:16px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}main{width:100%;max-width:480px;padding:28px 24px;background:#fff;border-radius:16px;border-top:6px solid var(--b);box-shadow:0 1px 3px rgba(20,50,74,.08);text-align:center}.h{margin:0 0 12px;font-weight:700}.h img{max-height:48px;max-width:140px;display:block;margin:0 auto 8px}.i{font-size:40px;line-height:1;margin:6px 0 10px;color:' . ( $ok ? 'var(--b)' : '#B3261E' ) . '}h1{margin:0 0 8px;font-size:22px}.m{margin:0 0 10px}.c{margin:0 0 14px;color:#4B5563}.b{display:inline-block;padding:10px 20px;border:0;border-radius:10px;background:var(--b);color:#fff;font:700 15px/1.2 inherit;text-decoration:none;cursor:pointer}@media (prefers-color-scheme:dark){body{background:#0f1a17;color:#e6efe9}main{background:#17241f;box-shadow:none}.c{color:#a9bdb3}}</style></head><body><main>';
		$h .= '<div class="h">' . ( $b['logo'] ? '<img src="' . esc_url( $b['logo'] ) . '" alt="">' : '' ) . esc_html( $b['name'] ) . '</div><div class="i" aria-hidden="true">' . ( $ok ? '&#10003;' : '&#33;' ) . '</div><h1>' . esc_html( $title ) . '</h1>' . $body . '</main>';
		if ( '' !== $script ) {
			$h .= '<script>' . $script . '</script>';
		}
		return $h . '</body></html>';
	}

	/* ------------------------------------------------------------------
	 * E-mails and admin notice
	 * ------------------------------------------------------------------ */

	public static function mail_donor( $s, $what ) {
		if ( ! is_array( $s ) || ! apply_filters( 'flxb_iyzico_donor_emails', true, $what, $s ) ) {
			return false;
		}
		$m     = FLXB_Subscriptions::meta( $s );
		$donor = (int) $s['donor_id'] && class_exists( 'FLXB_Donors' ) ? FLXB_Donors::get( (int) $s['donor_id'] ) : null;
		/* The person who set up this gift first (a donor record may be shared by phone number). */
		$email = is_email( $m['buyer']['email'] ?? '' ) ? (string) $m['buyer']['email'] : ( $donor && is_email( $donor['email'] ) ? $donor['email'] : '' );
		if ( ! is_email( $email ) ) {
			return false;
		}
		$name = '' !== (string) ( $m['buyer']['name'] ?? '' ) ? (string) $m['buyer']['name'] : ( $donor ? (string) $donor['name'] : '' );
		$int  = function_exists( 'mb_strtolower' ) ? mb_strtolower( flxb_interval_label( $s['interval_unit'] ), 'UTF-8' ) : strtolower( flxb_interval_label( $s['interval_unit'] ) );
		$vars = array(
			'ad'         => trim( $name ),
			'tutar'      => flxb_money( $s['amount'], $s['currency'] ),
			'aralik'     => $int,
			'iptal_link' => self::cancel_url( $s ),
		);
		if ( 'started' === $what ) {
			$vars['sonraki'] = flxb_date( $s['next_charge_at'], get_option( 'date_format' ) );
			return FLXB_Mail::send( 'flx-donate/recurring-started', $email, $vars );
		}
		$vars['deneme']     = number_format_i18n( (int) ( $m['retry_count'] ?? self::max_attempts() ) );
		$vars['bagis_link'] = (string) apply_filters( 'flxb_donate_again_url', class_exists( 'FLXB_WC' ) && FLXB_WC::ready() ? FLXB_WC::page_url() : home_url( '/' ), $s );
		return FLXB_Mail::send( 'flx-donate/recurring-failed', $email, $vars );
	}

	public static function admin_notice() {
		$list = (array) get_option( self::NOTICE_OPT, array() );
		if ( ! $list || ! function_exists( 'flxb_can_manage' ) || ! flxb_can_manage() ) {
			return;
		}
		$n   = count( $list );
		$url = admin_url( 'admin.php?page=flxb-subscriptions&status=past_due' );
		$dis = wp_nonce_url( admin_url( 'admin-post.php?action=flxb_iyzico_notice' ), 'flxb_iyzico_notice' );
		echo '<div class="notice notice-error flxb-iyzico-notice"><p><strong>Flux Donate:</strong> ' . esc_html( sprintf( /* translators: %s: number of recurring donations */ _n( '%s recurring donation could not be charged through iyzico after all attempts and is now past due. The donor was e-mailed.', '%s recurring donations could not be charged through iyzico after all attempts and are now past due. The donors were e-mailed.', $n, 'flx-donate' ), number_format_i18n( $n ) ) ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Show them', 'flx-donate' ) . '</a> · <a href="' . esc_url( $dis ) . '">' . esc_html__( 'Dismiss', 'flx-donate' ) . '</a></p></div>';
	}

	public static function dismiss() {
		if ( ! function_exists( 'flxb_can_manage' ) || ! flxb_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-donate' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'flxb_iyzico_notice' );
		delete_option( self::NOTICE_OPT );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=flxb-subscriptions' ) );
		exit;
	}
}
