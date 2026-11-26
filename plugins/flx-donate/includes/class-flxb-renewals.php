<?php
/**
 * Flux Donate: monthly donations charged on each due date with the card stored by Flux POS.
 *
 * Flux Donate takes no card itself. The first order is paid on the WooCommerce checkout with Flux POS's
 * "Pay by card" method; an order with monthly lines asks Flux POS to store the card (order meta `_flxpos_save`).
 * FLXB_WC_Recurring turns each paid monthly line into a recurring donation (provider `flxpos`, card =
 * {account, customer, payment_method, label}: ids of the card kept at the payment provider, never card data).
 *
 *  - WP-cron hook `flxb_renewals` (hourly) → a pending WooCommerce renewal order is opened (reused for
 *    retries) and charged with flxpos_charge_saved_order(); Flux POS pays the order, the ledger records it.
 *  - A failed charge is tried again (default after 1 and 3 days, filter `flxb_renewal_retry_delays`); after
 *    the last attempt the recurring donation becomes "past due", an admin notice appears and the donor gets
 *    an e-mail.
 *  - A charge whose answer did not come yet (Flux POS transaction pending) is not repeated: the next run asks
 *    again, so a card is never charged twice for one period.
 *  - Cancel: Donations › Recurring donations, or the donor's own signed link (in the e-mails).
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_Renewals {

	const PROVIDER   = 'flxpos';
	const GATEWAY    = 'flxpos_card';
	const HOOK       = 'flxb_renewals';
	const NOTICE_OPT = 'flxb_renewals_failed';
	const LAST_OPT   = 'flxb_renewals_last_run';
	const BATCH      = 20;

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		add_action( 'admin_post_flxb_renewals_notice', array( __CLASS__, 'dismiss' ) );
		add_action( 'admin_post_flxb_sub_cancel', array( __CLASS__, 'admin_cancel' ) );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serve_html' ), 100, 3 ); // after core's CORS headers
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			update_option( 'flxb_cron_first_seen', time(), false );
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::HOOK );
		}
	}

	/** Can a monthly gift be taken in this currency now (Flux POS has a card account that stores cards)? */
	public static function ready( $currency = '' ) {
		return function_exists( 'flxpos_can_save_card' ) && flxpos_can_save_card( (string) $currency );
	}

	/** Attempts per due date (first try included). */
	public static function max_attempts() {
		return 1 + count( self::delays() );
	}

	/** Seconds to wait before each retry. */
	public static function delays() {
		$d = array_values( array_filter( array_map( 'intval', (array) apply_filters( 'flxb_renewal_retry_delays', array( DAY_IN_SECONDS, 2 * DAY_IN_SECONDS ) ) ) ) );
		return array_slice( $d, 0, 10 );
	}

	/* ------------------------------------------------------------------
	 * Renewals
	 * ------------------------------------------------------------------ */

	/** Cron: charge every recurring donation that is due. */
	public static function run() {
		global $wpdb;
		if ( class_exists( 'FLXB_System_Checks' ) ) {
			FLXB_System_Checks::beat();
		}
		$t   = flxb_table( 'subscriptions' );
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE provider = %s AND status = 'active' AND next_charge_at IS NOT NULL AND next_charge_at <= %s ORDER BY next_charge_at ASC LIMIT %d", self::PROVIDER, current_time( 'mysql', true ), self::BATCH ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out = array();
		foreach ( (array) $ids as $id ) {
			$out[ (int) $id ] = self::charge( (int) $id );
		}
		if ( $out ) {
			update_option( self::LAST_OPT, array( 'time' => time(), 'result' => $out ), false );
		}
		return $out;
	}

	/**
	 * Charge one due recurring donation.
	 *
	 * @return string paid | retry | past_due | skipped | error
	 */
	public static function charge( $id ) {
		$lock = 'flxb_renew_' . (int) $id;
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
		if ( ! $s || self::PROVIDER !== $s['provider'] || 'active' !== $s['status'] || empty( $s['next_charge_at'] ) || strtotime( $s['next_charge_at'] . ' UTC' ) > time() ) {
			return 'skipped';
		}
		if ( ! class_exists( 'FLXB_WC_Recurring' ) || ! FLXB_WC_Recurring::handles( $s ) || ! function_exists( 'flxpos_charge_saved_order' ) ) {
			return 'error'; // WooCommerce or Flux POS is off in this request: try again next hour, no attempt counted
		}
		$m       = FLXB_Subscriptions::meta( $s );
		$period  = ! empty( $m['period_due'] ) ? (string) $m['period_due'] : (string) $s['next_charge_at'];
		$attempt = (int) ( $m['retry_count'] ?? 0 ) + 1;
		$card    = (array) ( $m['card'] ?? array() );
		if ( empty( $card['customer'] ) || empty( $card['payment_method'] ) ) {
			return self::failed( $s, $m, $period, self::max_attempts(), __( 'No stored card for this recurring donation.', 'flx-donate' ) );
		}
		/* The renewal is a WooCommerce order, opened before the charge and reused for retries. */
		$order = FLXB_WC_Recurring::renewal_order( $s, $period );
		if ( ! $order ) {
			return 'error';
		}
		FLXB_Subscriptions::update( $id, array( 'period_due' => $period ) );
		$name = '' !== (string) ( $m['form_name'] ?? '' ) ? (string) $m['form_name'] : __( 'Recurring donation', 'flx-donate' );
		$r    = (array) flxpos_charge_saved_order( $order, $card, $name );
		$res  = (string) $r['result'];
		if ( 'paid' === $res ) {
			return self::paid( $s, $m, $period );
		}
		if ( 'uncertain' === $res ) {
			return 'error'; // answer not in yet: asked again on the next run, nothing charged twice
		}
		$err = (string) ( $r['error'] ?? '' );
		return self::failed( $s, $m, $period, $attempt, '' !== $err ? $err : __( 'The card was not charged.', 'flx-donate' ) );
	}

	private static function paid( array $s, array $m, $period ) {
		$donation = FLXB_WC_Recurring::renewal_paid( $s, $period );
		$next     = FLXB_Subscriptions::next_charge( $s['interval_unit'], $period );
		while ( strtotime( $next . ' UTC' ) <= time() ) {
			$next = FLXB_Subscriptions::next_charge( $s['interval_unit'], $next );
		}
		FLXB_Subscriptions::update(
			(int) $s['id'],
			array( 'retry_count' => 0, 'period_due' => null, 'last_error' => null, 'last_paid_at' => current_time( 'mysql', true ), 'renewals' => (int) ( $m['renewals'] ?? 0 ) + 1 ),
			array( 'status' => 'active', 'next_charge_at' => $next )
		);
		do_action( 'flxb_renewal_paid', (int) $s['id'], $donation );
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
			do_action( 'flxb_renewal_failed', (int) $s['id'], $error, false );
			return 'retry';
		}
		FLXB_Subscriptions::update(
			(int) $s['id'],
			array( 'retry_count' => $attempt, 'period_due' => $period, 'last_error' => $error, 'last_failed_at' => current_time( 'mysql', true ) ),
			array( 'status' => 'past_due' )
		);
		$list             = (array) get_option( self::NOTICE_OPT, array() );
		$list[ $s['id'] ] = time();
		update_option( self::NOTICE_OPT, $list, false );
		self::mail_donor( FLXB_Subscriptions::get( (int) $s['id'] ), 'failed' );
		do_action( 'flxb_renewal_failed', (int) $s['id'], $error, true );
		return 'past_due';
	}

	/** Stop a recurring donation and forget its card. */
	public static function cancel( $id, $by = 'admin' ) {
		$s = FLXB_Subscriptions::get( (int) $id );
		if ( ! $s ) {
			return false;
		}
		FLXB_Subscriptions::update( (int) $id, array( 'card' => null, 'canceled_by' => $by ), array( 'status' => 'canceled', 'canceled_at' => current_time( 'mysql', true ) ) );
		$list = (array) get_option( self::NOTICE_OPT, array() );
		if ( isset( $list[ $id ] ) ) {
			unset( $list[ $id ] );
			update_option( self::NOTICE_OPT, $list, false );
		}
		do_action( 'flxb_subscription_canceled', (int) $id, $by );
		return true;
	}

	/** Cancel button of the recurring donations table (Flux CRM person card). */
	public static function admin_cancel() {
		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked below
		if ( ! function_exists( 'flxb_can_manage' ) || ! flxb_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-donate' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'flxb_sub_cancel_' . $id );
		$s    = FLXB_Subscriptions::get( $id );
		$ok   = $s && self::PROVIDER === $s['provider'] && in_array( $s['status'], array( 'active', 'past_due' ), true ) && self::cancel( $id, 'admin' );
		$back = isset( $_POST['back'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( (string) $_POST['back'] ) ), '' ) : '';
		$back = '' !== $back ? $back : FLXB_Admin_Url::page( 'flxb-subscriptions' );
		wp_safe_redirect( add_query_arg( 'flxb_msg', $ok ? 'canceled' : 'error', $back ) );
		exit;
	}

	/* ------------------------------------------------------------------
	 * Donor's cancel link
	 * ------------------------------------------------------------------ */

	public static function cancel_key( array $s ) {
		return substr( hash_hmac( 'sha256', 'flxb-cancel|' . (int) $s['id'] . '|' . (string) $s['created_at'] . '|' . (string) $s['provider_ref'], wp_salt( 'auth' ) ), 0, 32 );
	}

	public static function cancel_url( array $s ) {
		return add_query_arg( array( 's' => (int) $s['id'], 'k' => self::cancel_key( $s ) ), rest_url( 'flxb/v1/recurring/cancel' ) );
	}

	public static function routes() {
		register_rest_route(
			'flxb/v1',
			'/recurring/cancel',
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
		if ( ! $s || ! hash_equals( self::cancel_key( $s ), $key ) ) {
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

	/** Prints the HTML page of the flxb/v1/recurring route as it is. */
	public static function serve_html( $served, $result, $request ) {
		if ( $served || ! $result instanceof WP_HTTP_Response || ! $request instanceof WP_REST_Request || 0 !== strpos( (string) $request->get_route(), '/flxb/v1/recurring/' ) ) {
			return $served;
		}
		$d = $result->get_data();
		if ( ! is_array( $d ) || ! isset( $d['__flxb_html'] ) ) {
			return $served;
		}
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
		}
		echo $d['__flxb_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in page_html()
		return true;
	}

	/** Small branded page. $body is already escaped. */
	public static function page_html( $title, $body, $ok ) {
		$b = flxb_brand();
		$h = '<!doctype html><html lang="' . esc_attr( str_replace( '_', '-', determine_locale() ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html( $title . ' · ' . $b['name'] ) . '</title>';
		$h .= '<style>:root{--b:' . esc_html( $b['primary'] ) . ';color-scheme:light dark}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;background:#f4f4f5;color:#18181b;font:16px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}main{width:100%;max-width:480px;padding:28px 24px;background:#fff;border-radius:16px;border-top:6px solid var(--b);box-shadow:0 1px 3px rgba(0,0,0,.08);text-align:center}.h{margin:0 0 12px;font-weight:700}.h img{max-height:48px;max-width:140px;display:block;margin:0 auto 8px}.i{font-size:40px;line-height:1;margin:6px 0 10px;color:' . ( $ok ? 'var(--b)' : '#B3261E' ) . '}h1{margin:0 0 8px;font-size:22px}.m{margin:0 0 10px}.c{margin:0 0 14px;color:#52525b}.b{display:inline-block;padding:10px 20px;border:0;border-radius:10px;background:var(--b);color:#fff;font:700 15px/1.2 inherit;text-decoration:none;cursor:pointer}@media (prefers-color-scheme:dark){body{background:#18181b;color:#e4e4e7}main{background:#27272a;box-shadow:none}.c{color:#a1a1aa}}</style></head><body><main>';
		$h .= '<div class="h">' . ( $b['logo'] ? '<img src="' . esc_url( $b['logo'] ) . '" alt="">' : '' ) . esc_html( $b['name'] ) . '</div><div class="i" aria-hidden="true">' . ( $ok ? '&#10003;' : '&#33;' ) . '</div><h1>' . esc_html( $title ) . '</h1>' . $body . '</main>';
		return $h . '</body></html>';
	}

	/* ------------------------------------------------------------------
	 * E-mails and admin notice
	 * ------------------------------------------------------------------ */

	public static function mail_donor( $s, $what ) {
		if ( ! is_array( $s ) || ! apply_filters( 'flxb_renewal_donor_emails', true, $what, $s ) ) {
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
			$vars['sonraki'] = flxb_date( $s['next_charge_at'], 'date' );
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
		$url = FLXB_Admin_Url::page( 'flxb-subscriptions', array( 'status' => 'past_due' ) );
		$dis = wp_nonce_url( admin_url( 'admin-post.php?action=flxb_renewals_notice' ), 'flxb_renewals_notice' );
		echo '<div class="notice notice-error flxb-renewals-notice"><p><strong>Flux Donate:</strong> ' . esc_html( sprintf( /* translators: %s: number of recurring donations */ _n( '%s recurring donation could not be charged after all attempts and is now past due. The donor was e-mailed.', '%s recurring donations could not be charged after all attempts and are now past due. The donors were e-mailed.', $n, 'flx-donate' ), number_format_i18n( $n ) ) ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Show them', 'flx-donate' ) . '</a> · <a href="' . esc_url( $dis ) . '">' . esc_html__( 'Dismiss', 'flx-donate' ) . '</a></p></div>';
	}

	public static function dismiss() {
		if ( ! function_exists( 'flxb_can_manage' ) || ! flxb_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-donate' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'flxb_renewals_notice' );
		delete_option( self::NOTICE_OPT );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : FLXB_Admin_Url::page( 'flxb-subscriptions' ) );
		exit;
	}
}
