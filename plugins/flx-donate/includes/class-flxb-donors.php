<?php
/**
 * Flux Donate: donors. One person = one row, matched on the normalised phone number
 * (E.164) or the e-mail address. A name alone never merges two donors.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Donors {

	public static function get( $id ) {
		global $wpdb;
		$t = flxb_table( 'donors' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function find( $phone, $email ) {
		global $wpdb;
		$t = flxb_table( 'donors' );
		if ( '' !== $phone ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE phone = %s ORDER BY id ASC LIMIT 1", $phone ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $row ) {
				return $row;
			}
		}
		if ( '' !== $email ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE email = %s ORDER BY id ASC LIMIT 1", $email ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $row ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Find or create the donor for these contact details and fill in what we did not know yet.
	 *
	 * @param array $d name, email, phone (raw), consent_kvkk, consent_email, consent_sms.
	 * @return int Donor id (0 when there is nothing to identify a person with).
	 */
	public static function upsert( array $d ) {
		global $wpdb;
		$t     = flxb_table( 'donors' );
		$name  = sanitize_text_field( (string) ( $d['name'] ?? '' ) );
		$email = flxb_normalize_email( $d['email'] ?? '' );
		$phone = flxb_normalize_phone( $d['phone'] ?? '' );
		if ( '' === $email && '' === $phone ) {
			/* Without a contact detail we keep one anonymous donor per name-less gift: no merge. */
			if ( empty( $d['allow_anonymous'] ) ) {
				return 0;
			}
		}
		$row = ( '' === $email && '' === $phone ) ? null : self::find( $phone, $email );
		$now = flxb_now();
		if ( ! $row ) {
			$wpdb->insert(
				$t,
				array(
					'name'          => mb_substr( $name, 0, 191 ),
					'email'         => $email,
					'phone'         => $phone,
					'consent_kvkk'  => empty( $d['consent_kvkk'] ) ? 0 : 1,
					'consent_email' => empty( $d['consent_email'] ) ? 0 : 1,
					'consent_sms'   => empty( $d['consent_sms'] ) ? 0 : 1,
					'created_at'    => $now,
					'updated_at'    => $now,
				),
				array( '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
			);
			return (int) $wpdb->insert_id;
		}
		$upd = array();
		if ( '' !== $name && '' === (string) $row['name'] ) {
			$upd['name'] = mb_substr( $name, 0, 191 );
		}
		/* Fill a missing detail only when no other donor already owns it. */
		if ( '' !== $email && '' === (string) $row['email'] && ! self::find( '', $email ) ) {
			$upd['email'] = $email;
		}
		if ( '' !== $phone && '' === (string) $row['phone'] && ! self::find( $phone, '' ) ) {
			$upd['phone'] = $phone;
		}
		foreach ( array( 'consent_kvkk', 'consent_email', 'consent_sms' ) as $k ) {
			if ( ! empty( $d[ $k ] ) && empty( $row[ $k ] ) ) {
				$upd[ $k ] = 1;
			}
		}
		if ( $upd ) {
			$upd['updated_at'] = $now;
			$wpdb->update( $t, $upd, array( 'id' => (int) $row['id'] ) );
		}
		return (int) $row['id'];
	}

	/** Recompute first/last gift, total and count from completed donations. */
	public static function recalc( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( ! $id ) {
			return;
		}
		$dn = flxb_table( 'donations' );
		$r  = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, COALESCE(SUM(amount),0) AS total, MIN(created_at) AS first_at, MAX(created_at) AS last_at FROM {$dn} WHERE donor_id = %d AND status IN ('completed','partially_refunded')", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->update(
			flxb_table( 'donors' ),
			array(
				'donation_count'    => (int) $r['n'],
				'total_amount'      => (float) $r['total'],
				'first_donation_at' => $r['first_at'],
				'last_donation_at'  => $r['last_at'],
				'updated_at'        => flxb_now(),
			),
			array( 'id' => $id )
		);
	}

	/**
	 * @return array{items: array, total: int}
	 */
	public static function query( array $a ) {
		global $wpdb;
		$a     = wp_parse_args( $a, array( 'search' => '', 'per_page' => 25, 'page' => 1, 'orderby' => 'last_donation_at', 'order' => 'DESC', 'kind' => '' ) );
		$t     = flxb_table( 'donors' );
		$s     = flxb_table( 'subscriptions' );
		$where = array( '1=1' );
		$prm   = array();
		if ( '' !== $a['search'] ) {
			$like    = '%' . $wpdb->esc_like( $a['search'] ) . '%';
			$digits  = preg_replace( '/\D+/', '', $a['search'] );
			$where[] = '(d.name LIKE %s OR d.email LIKE %s' . ( strlen( $digits ) >= 3 ? ' OR d.phone LIKE %s' : '' ) . ')';
			array_push( $prm, $like, $like );
			if ( strlen( $digits ) >= 3 ) {
				$prm[] = '%' . $wpdb->esc_like( ltrim( $digits, '0' ) ) . '%';
			}
		}
		if ( 'recurring' === $a['kind'] ) {
			$where[] = "EXISTS (SELECT 1 FROM {$s} s WHERE s.donor_id = d.id AND s.status IN ('active','past_due'))";
		} elseif ( 'consent' === $a['kind'] ) {
			$where[] = '(d.consent_email = 1 OR d.consent_sms = 1)';
		}
		$own = (int) ( $a['ref_user_id'] ?? 0 ); // 2.3.0: only donors of gifts this person referred, with their own figures
		$dn  = flxb_table( 'donations' );
		if ( $own ) {
			$where[] = "EXISTS (SELECT 1 FROM {$dn} r WHERE r.donor_id = d.id AND r.ref_user_id = %d)";
			$prm[]   = $own;
		}
		$cols    = array( 'name', 'total_amount', 'donation_count', 'last_donation_at', 'first_donation_at' );
		$orderby = in_array( $a['orderby'], $cols, true ) ? 'd.' . $a['orderby'] : 'd.last_donation_at';
		$order   = 'ASC' === strtoupper( (string) $a['order'] ) ? 'ASC' : 'DESC';
		$w       = implode( ' AND ', $where );
		$from    = "FROM {$t} d WHERE {$w}";
		$total   = (int) ( $prm ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$from}", $prm ) ) : $wpdb->get_var( "SELECT COUNT(*) {$from}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$limit   = $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $a['per_page'], ( max( 1, (int) $a['page'] ) - 1 ) * (int) $a['per_page'] );
		$sql     = "SELECT d.*, (SELECT COUNT(*) FROM {$s} s WHERE s.donor_id = d.id AND s.status IN ('active','past_due')) AS active_subs {$from} ORDER BY {$orderby} {$order}, d.id DESC" . $limit;
		$items   = $prm ? $wpdb->get_results( $wpdb->prepare( $sql, $prm ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( $own ) {
			foreach ( (array) $items as $k => $row ) {
				$items[ $k ] = array_merge( $row, self::own_figures( (int) $row['id'], $own ) );
			}
		}
		return array( 'items' => (array) $items, 'total' => $total );
	}

	/** 2.3.0: totals of a donor counting only the gifts one referral brought (an order counts once). */
	public static function own_figures( $donor_id, $ref_user_id ) {
		global $wpdb;
		$t = flxb_table( 'donations' );
		$g = FLXB_Donations::GROUP_KEY;
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(x.amount),0) AS total_amount, COUNT(DISTINCT {$g}) AS donation_count, MIN(x.created_at) AS first_donation_at, MAX(x.created_at) AS last_donation_at FROM {$t} x WHERE x.donor_id = %d AND x.ref_user_id = %d AND x.status IN ('completed','partially_refunded')", (int) $donor_id, (int) $ref_user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$s    = flxb_table( 'subscriptions' );
		$subs = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$s} WHERE donor_id = %d AND ref_user_id = %d AND status IN ('active','past_due')", (int) $donor_id, (int) $ref_user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array(
			'active_subs'       => $subs,
			'total_amount'      => (float) ( $r['total_amount'] ?? 0 ),
			'donation_count'    => (int) ( $r['donation_count'] ?? 0 ),
			'first_donation_at' => $r['first_donation_at'] ?? null,
			'last_donation_at'  => $r['last_donation_at'] ?? null,
		);
	}

	/** Certificates of a donor from Flux Certificates (by the codes on their gifts and by person match). */
	public static function certificates( array $donor ) {
		if ( ! flxb_certificates_available() ) {
			return array();
		}
		global $wpdb;
		$out   = array();
		$dn    = flxb_table( 'donations' );
		$codes = $wpdb->get_col( $wpdb->prepare( "SELECT certificate_code FROM {$dn} WHERE donor_id = %d AND certificate_code <> ''", (int) $donor['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $codes as $code ) {
			$c = flxs_get_certificate_by_code( $code );
			if ( $c ) {
				$out[ (int) $c['id'] ] = $c;
			}
		}
		if ( function_exists( 'flxs_query_certificates' ) ) {
			$person = null;
			if ( '' !== (string) $donor['phone'] && function_exists( 'flxs_find_person_by_phone' ) ) {
				$person = flxs_find_person_by_phone( $donor['phone'] );
			}
			if ( ! $person && '' !== (string) $donor['email'] && function_exists( 'flxs_find_person_by_email' ) ) {
				$person = flxs_find_person_by_email( $donor['email'] );
			}
			if ( $person ) {
				$res = flxs_query_certificates( array( 'person_id' => (int) $person['id'], 'per_page' => 50 ) );
				foreach ( (array) ( $res['items'] ?? array() ) as $c ) {
					$out[ (int) $c['id'] ] = $c;
				}
			}
		}
		krsort( $out );
		return array_values( $out );
	}
}
