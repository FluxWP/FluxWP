<?php
/**
 * Donors and recurring donations for the admin screen (GraphQL part b3).
 *
 * The wp-admin screen (packages/modules/src/donate) calls this class; it holds no screen code. Rights are the
 * classic ones:
 * - donor list and card: flx_bagis_view, or flxb_donors_own (staff: only the donors of the gifts they
 *   referred, with only those gifts' figures, no recurring donations, certificates or notes);
 * - donor save (consent, note): flxb_can_manage();
 * - recurring donations: flx_bagis_view; cancel: flxb_can_manage() and Flux Donate takes the payments.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxbDonorFilter array{search: string, kind: string, orderby: string}
 * @phpstan-type FlxbSubFilter array{search: string, status: string}
 */
final class FLXB_Admin_People_Service {

	const DONOR_ORDERS = array( 'last_donation_at', 'total_amount', 'donation_count', 'first_donation_at', 'name' );

	/** Gifts and certificates shown on the donor card (as the classic card). */
	const CARD_GIFTS = 25;
	const CARD_CERTS = 12;

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	private static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/**
	 * A database row with string keys, or null.
	 *
	 * @param mixed $v Value.
	 * @return array<string,mixed>|null
	 */
	private static function row( $v ): ?array {
		if ( ! is_array( $v ) ) {
			return null;
		}
		$out = array();
		foreach ( $v as $k => $x ) {
			$out[ (string) $k ] = $x;
		}
		return $out;
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function s( $v ): string {
		return is_scalar( $v ) ? (string) $v : '';
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function i( $v ): int {
		return is_numeric( $v ) ? (int) $v : 0;
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function f( $v ): float {
		return is_numeric( $v ) ? (float) $v : 0.0;
	}

	private static function date( string $mysql ): string {
		return '' !== $mysql ? self::s( flxb_date( $mysql, 'date' ) ) : '';
	}

	private static function tone( string $tone ): string {
		return in_array( $tone, array( 'ok', 'pending', 'failed', 'muted', 'receipt' ), true ) ? $tone : 'muted';
	}

	/** Staff who see only their own donors: their user id, else 0. */
	public static function own(): int {
		return FLXB_Refs::donors_own() ? get_current_user_id() : 0;
	}

	public static function can_donors(): bool {
		return (bool) FLXB_Refs::can_donors();
	}

	public static function can_subscriptions(): bool {
		return (bool) flxb_can_view();
	}

	public static function can_manage(): bool {
		return (bool) flxb_can_manage();
	}

	/** Recurring donations can be canceled from here when Flux Donate takes the payments. */
	public static function can_cancel(): bool {
		return function_exists( 'flxb_owns_payments' ) && flxb_owns_payments() && self::can_manage();
	}

	/**
	 * Donor name as the classic screens show it: name, else e-mail, else phone, else "Anonymous".
	 *
	 * @param array<string,mixed> $r      Row.
	 * @param string              $prefix Column prefix.
	 */
	private static function name( array $r, string $prefix ): string {
		foreach ( array( 'name', 'email' ) as $k ) {
			$v = self::s( $r[ $prefix . $k ] ?? '' );
			if ( '' !== $v ) {
				return $v;
			}
		}
		$phone = self::s( $r[ $prefix . 'phone' ] ?? '' );
		return '' !== $phone ? self::s( flxb_format_phone( $phone ) ) : __( 'Anonymous', 'flx-donate' );
	}

	/* ------------------------------------------------------------------
	 * Donors
	 * ------------------------------------------------------------------ */

	/**
	 * GraphQL filter input → the classic list arguments.
	 *
	 * @param array<string,mixed> $in search, kind, orderBy.
	 * @return FlxbDonorFilter
	 */
	public static function donor_filter( array $in ): array {
		$kind  = sanitize_key( self::s( $in['kind'] ?? '' ) );
		$order = sanitize_key( self::s( $in['orderBy'] ?? '' ) );
		return array(
			'search'  => sanitize_text_field( self::s( $in['search'] ?? '' ) ),
			'kind'    => in_array( $kind, array( 'recurring', 'consent' ), true ) ? $kind : '',
			'orderby' => in_array( $order, self::DONOR_ORDERS, true ) ? $order : 'last_donation_at',
		);
	}

	/**
	 * One page of the donor list.
	 *
	 * @param FlxbDonorFilter $f      Filter.
	 * @param int             $offset Rows to skip.
	 * @param int             $limit  Rows to return.
	 * @return array{items: list<array<string,mixed>>, total: int}
	 */
	public static function donors( array $f, int $offset, int $limit ): array {
		$res   = self::arr(
			FLXB_Donors::query(
				array(
					'search'      => $f['search'],
					'kind'        => $f['kind'],
					'orderby'     => $f['orderby'],
					'per_page'    => max( 1, $limit ),
					'offset'      => max( 0, $offset ),
					'ref_user_id' => self::own(),
				)
			)
		);
		$items = array();
		foreach ( self::arr( $res['items'] ?? null ) as $r ) {
			$r = self::row( $r );
			if ( $r ) {
				$items[] = self::donor_row( $r );
			}
		}
		return array(
			'items' => $items,
			'total' => self::i( $res['total'] ?? 0 ),
		);
	}

	/**
	 * @param array<string,mixed> $d Donor row (with active_subs).
	 * @return array<string,mixed>
	 */
	private static function donor_row( array $d ): array {
		$id    = self::i( $d['id'] ?? 0 );
		$name  = self::name( $d, '' );
		$email = self::s( $d['email'] ?? '' );
		$phone = self::s( $d['phone'] ?? '' );
		$last  = self::s( $d['last_donation_at'] ?? '' );
		return array(
			'databaseId' => $id,
			'name'       => $name,
			'email'      => '' !== $email && $email !== $name ? $email : '',
			'phone'      => '' !== $phone ? self::s( flxb_format_phone( $phone ) ) : '',
			'totalLabel' => self::s( flxb_money( self::f( $d['total_amount'] ?? 0 ) ) ),
			'gifts'      => self::i( $d['donation_count'] ?? 0 ),
			'lastGift'   => '' !== $last ? flx_gql_datetime( $last ) : null,
			'recurring'  => self::i( $d['active_subs'] ?? 0 ) > 0,
			'url'        => self::s( FLXB_Admin_Url::donor( $id ) ),
		);
	}

	/**
	 * The donor card.
	 *
	 * @return array<string,mixed>|null Null: not found, or not one of the staff member's donors.
	 */
	public static function donor( int $id ): ?array {
		$own = self::own();
		$d   = self::row( FLXB_Donors::get( $id ) );
		if ( ! $d ) {
			return null;
		}
		if ( $own ) {
			if ( ! FLXB_Refs::is_own_donor( $id, $own ) ) {
				return null;
			}
			$d = self::row( array_merge( $d, self::arr( FLXB_Donors::own_figures( $id, $own ) ) ) ) ?? $d;
		}
		$name  = self::name( $d, '' );
		$count = self::i( $d['donation_count'] ?? 0 );
		$total = self::f( $d['total_amount'] ?? 0 );
		$first = self::s( $d['first_donation_at'] ?? '' );
		$last  = self::s( $d['last_donation_at'] ?? '' );
		$email = self::s( $d['email'] ?? '' );
		$phone = self::s( $d['phone'] ?? '' );

		$f = FLXB_Admin_Donations_Service::filter( array( 'donorId' => $id ) );
		if ( $own ) {
			$f['ref_user_id'] = (string) $own;
		}
		$gifts = FLXB_Admin_Donations_Service::page( $f, 0, self::CARD_GIFTS );

		$subs = array();
		if ( ! $own ) {
			$res = self::arr( FLXB_Subscriptions::query( array( 'donor_id' => $id, 'per_page' => 50 ) ) );
			foreach ( self::arr( $res['items'] ?? null ) as $s ) {
				$s = self::row( $s );
				if ( $s ) {
					$subs[] = self::sub_row( $s );
				}
			}
		}

		$certs_on = ! $own && flxb_certificates_available();
		$certs    = array();
		$all      = $certs_on ? array_values( self::arr( FLXB_Donors::certificates( $d ) ) ) : array();
		foreach ( array_slice( $all, 0, self::CARD_CERTS ) as $c ) {
			$c = self::row( $c );
			if ( ! $c ) {
				continue;
			}
			$number  = self::s( $c['number'] ?? '' );
			$created = self::s( $c['created_at'] ?? '' );
			$certs[] = array(
				'number'    => '' !== $number ? $number : self::s( $c['code'] ?? '' ),
				'dateLabel' => '' !== $created ? self::s( flxb_date( get_gmt_from_date( $created ), 'date' ) ) : '',
				'url'       => function_exists( 'flxs_certificate_url' ) ? self::s( flxs_certificate_url( $c ) ) : '',
			);
		}
		$created = self::s( $d['created_at'] ?? '' );
		return array(
			'databaseId'        => $id,
			'name'              => $name,
			'initial'           => mb_strtoupper( mb_substr( $name, 0, 1 ) ),
			'email'             => $email,
			'phone'             => $phone,
			'phoneLabel'        => '' !== $phone ? self::s( flxb_format_phone( $phone ) ) : '',
			'sinceLabel'        => self::date( '' !== $first ? $first : $created ),
			'totalLabel'        => self::s( flxb_money( $total ) ),
			'giftCount'         => $count,
			'averageLabel'      => self::s( flxb_money( $count ? $total / $count : 0 ) ),
			'lastGiftLabel'     => '' !== $last ? self::date( $last ) : '',
			'ownOnly'           => $own > 0,
			'gifts'             => $gifts['items'],
			'giftsTotal'        => $gifts['total'],
			'giftsUrl'          => self::s( FLXB_Admin_Url::page( 'flxb-donations', $own ? array() : array( 'donor_id' => $id ) ) ),
			'subscriptions'     => $subs,
			'certificatesOn'    => $certs_on,
			'certificates'      => $certs,
			'moreCertificates'  => max( 0, count( $all ) - self::CARD_CERTS ),
			'consentKvkk'       => 1 === self::i( $d['consent_kvkk'] ?? 0 ),
			'consentEmail'      => 1 === self::i( $d['consent_email'] ?? 0 ),
			'consentSms'        => 1 === self::i( $d['consent_sms'] ?? 0 ),
			'note'              => $own ? '' : self::s( $d['note'] ?? '' ),
			'canEdit'           => ! $own && self::can_manage(),
			'canAdd'            => self::can_manage(),
			'addUrl'            => self::s( FLXB_Admin_Url::page( 'flxb-add', array( 'donor' => $id ) ) ),
			'listUrl'           => self::s( FLXB_Admin_Url::page( 'flxb-donors' ) ),
		);
	}

	/**
	 * Saves the donor's consent switches and note (the classic card's form).
	 *
	 * @return array{message: string}|WP_Error
	 */
	public static function save_donor( int $id, bool $kvkk, bool $email, bool $sms, string $note ) {
		global $wpdb;
		/** @var wpdb $wpdb */
		$before = self::row( FLXB_Donors::get( $id ) );
		if ( ! $before || self::own() ) {
			return self::err( 'INVALID_INPUT', __( 'Donor not found.', 'flx-donate' ) );
		}
		$row = array(
			'consent_kvkk'  => $kvkk ? 1 : 0,
			'consent_email' => $email ? 1 : 0,
			'consent_sms'   => $sms ? 1 : 0,
			'note'          => sanitize_textarea_field( $note ),
			'updated_at'    => flxb_now(),
		);
		$wpdb->update( self::s( flxb_table( 'donors' ) ), $row, array( 'id' => $id ) );
		do_action( 'flx_person_updated', $id, $row, $before );
		return array( 'message' => __( 'Saved.', 'flx-donate' ) );
	}

	/* ------------------------------------------------------------------
	 * Recurring donations
	 * ------------------------------------------------------------------ */

	/**
	 * @param array<string,mixed> $in search, status.
	 * @return FlxbSubFilter
	 */
	public static function sub_filter( array $in ): array {
		$st = sanitize_key( self::s( $in['status'] ?? '' ) );
		return array(
			'search' => sanitize_text_field( self::s( $in['search'] ?? '' ) ),
			'status' => in_array( $st, FLXB_Subscriptions::STATUSES, true ) ? $st : '',
		);
	}

	/**
	 * One page of recurring donations (active first).
	 *
	 * @param FlxbSubFilter $f      Filter.
	 * @param int           $offset Rows to skip.
	 * @param int           $limit  Rows to return.
	 * @return array{items: list<array<string,mixed>>, total: int}
	 */
	public static function subscriptions( array $f, int $offset, int $limit ): array {
		$res   = self::arr(
			FLXB_Subscriptions::query(
				array(
					'status'   => $f['status'],
					'search'   => $f['search'],
					'per_page' => max( 1, $limit ),
					'offset'   => max( 0, $offset ),
				)
			)
		);
		$items = array();
		foreach ( self::arr( $res['items'] ?? null ) as $s ) {
			$s = self::row( $s );
			if ( $s ) {
				$items[] = self::sub_row( $s );
			}
		}
		return array(
			'items' => $items,
			'total' => self::i( $res['total'] ?? 0 ),
		);
	}

	/**
	 * KPIs above the list.
	 *
	 * @return array<string,mixed>
	 */
	public static function sub_totals(): array {
		$t = self::arr( FLXB_Subscriptions::totals() );
		return array(
			'mrrLabel' => self::s( flxb_money( self::f( $t['mrr'] ?? 0 ) ) ),
			'active'   => self::i( $t['active'] ?? 0 ),
			'pastDue'  => self::i( $t['past_due'] ?? 0 ),
			'canceled' => self::i( $t['canceled'] ?? 0 ),
		);
	}

	/**
	 * One recurring donation as the classic table shows it.
	 *
	 * @param array<string,mixed> $s Row (with donor_name / donor_email / donor_phone).
	 * @return array<string,mixed>
	 */
	private static function sub_row( array $s ): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$id     = self::i( $s['id'] ?? 0 );
		$donor  = self::i( $s['donor_id'] ?? 0 );
		$status = self::s( $s['status'] ?? '' );
		$dn     = flxb_table( 'donations' );
		$gifts  = self::i( $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE subscription_id = %d AND status IN (\'completed\',\'partially_refunded\')', $dn, $id ) ) );
		$why    = '';
		if ( 'iyzico' === self::s( $s['provider'] ?? '' ) && 'canceled' !== $status ) {
			$sm    = self::arr( FLXB_Subscriptions::meta( $s ) );
			$err   = self::s( $sm['last_error'] ?? '' );
			$tries = self::i( $sm['retry_count'] ?? 0 );
			if ( '' !== $err && $tries > 0 ) {
				/* translators: 1: attempts made, 2: attempts allowed, 3: error from iyzico */
				$why = sprintf( __( 'Attempt %1$s of %2$s failed: %3$s', 'flx-donate' ), number_format_i18n( $tries ), number_format_i18n( self::max_attempts() ), $err );
			}
			$card = self::s( self::arr( $sm['card'] ?? null )['label'] ?? '' );
			if ( '' !== $card ) {
				$why = trim( $card . ( '' !== $why ? ' · ' . $why : '' ) );
			}
		}
		$canceled = self::s( $s['canceled_at'] ?? '' );
		$next     = 'canceled' === $status
			/* translators: %s: date */
			? ( '' !== $canceled ? sprintf( __( 'canceled %s', 'flx-donate' ), self::date( $canceled ) ) : '' )
			: self::date( self::s( $s['next_charge_at'] ?? '' ) );
		$label    = self::arr( flxb_status_label( $status ) );
		$provider = function_exists( 'flxf_payment_provider' ) ? flxf_payment_provider( self::s( $s['provider'] ?? '' ) ) : null;
		$started  = self::s( $s['started_at'] ?? '' );
		return array(
			'databaseId'    => $id,
			'donorId'       => $donor,
			'donorName'     => $donor ? self::name( $s, 'donor_' ) : __( 'Anonymous', 'flx-donate' ),
			'donorUrl'      => $donor ? self::s( FLXB_Admin_Url::donor( $donor ) ) : '',
			'reference'     => self::s( $s['provider_ref'] ?? '' ),
			'amountLabel'   => self::s( flxb_money( self::f( $s['amount'] ?? 0 ), self::s( $s['currency'] ?? '' ) ) ),
			'intervalLabel' => self::s( flxb_interval_label( self::s( $s['interval_unit'] ?? '' ) ) ),
			'status'        => in_array( $status, FLXB_Subscriptions::STATUSES, true ) ? $status : 'canceled',
			'statusLabel'   => self::s( $label[1] ?? $status ),
			'tone'          => self::tone( self::s( $label[0] ?? '' ) ),
			'note'          => $why,
			'startedLabel'  => self::date( $started ),
			'nextLabel'     => $next,
			'gifts'         => $gifts,
			'giftsUrl'      => self::s( FLXB_Admin_Url::page( 'flxb-donations', array( 'donor_id' => $donor, 'type' => 'recurring' ) ) ),
			'canCancel'     => self::can_cancel() && is_object( $provider ) && method_exists( $provider, 'cancel_subscription' ) && in_array( $status, array( 'active', 'past_due' ), true ),
		);
	}

	/** Charge attempts iyzico renewals make (FLXB_Iyzico_Recurring, loaded with the payment layer). */
	private static function max_attempts(): int {
		$cb = apply_filters( 'flxb_iyzico_max_attempts_getter', 'FLXB_Iyzico_Recurring::max_attempts' );
		return is_callable( $cb ) ? self::i( call_user_func( $cb ) ) : 0;
	}

	private static function err( string $code, string $msg ): WP_Error {
		return new WP_Error( 'flxb_people', $msg, array( 'graphql_code' => $code ) );
	}

	/**
	 * Cancels a recurring donation at its provider (the classic flxb_sub_cancel handler).
	 *
	 * @return array{message: string}|WP_Error
	 */
	public static function cancel( int $id ) {
		$s = self::row( FLXB_Subscriptions::get( $id ) );
		if ( ! $s ) {
			return self::err( 'INVALID_INPUT', __( 'Recurring donation not found.', 'flx-donate' ) );
		}
		$ref      = self::s( $s['provider_ref'] ?? '' );
		$gw       = self::s( $s['provider'] ?? '' );
		$provider = function_exists( 'flxf_payment_provider' ) ? flxf_payment_provider( $gw ) : null;
		if ( ! is_object( $provider ) || ! method_exists( $provider, 'cancel_subscription' ) || ! in_array( self::s( $s['status'] ?? '' ), array( 'active', 'past_due' ), true ) ) {
			return self::err( 'CONFLICT', __( 'This recurring donation cannot be canceled here.', 'flx-donate' ) );
		}
		$res = $provider->cancel_subscription( $ref );
		if ( ! is_object( $res ) || ! method_exists( $res, 'is_paid' ) || ! $res->is_paid() ) {
			$msg = is_object( $res ) && property_exists( $res, 'message' ) ? self::s( $res->message ) : '';
			return self::err( 'CONFLICT', '' !== $msg ? $msg : __( 'The action could not be completed.', 'flx-donate' ) );
		}
		FLXB_Subscriptions::set_status( $gw, $ref, 'canceled', array( 'canceled_at' => flxb_now() ) );
		if ( class_exists( 'FLXB_Payment_Records' ) ) {
			$rec = FLXB_Payment_Records::get_by_reference( $gw, $ref, 'subscription_id' );
			if ( is_object( $rec ) && isset( $rec->id ) ) {
				FLXB_Payment_Records::update( $rec->id, array( 'status' => 'canceled' ) );
			}
		}
		return array( 'message' => __( 'The recurring donation was canceled. No further charges will be made.', 'flx-donate' ) );
	}
}
