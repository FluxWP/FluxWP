<?php
/**
 * 2.28.0: per-person donation values for Flux CRM event messages (CRM › Events › Message to the event).
 *
 * Flux CRM 0.7.0 asks every package for personal values through the shared `flx_event_vars` filter
 * ( $out[ key ] = [ label, sample, values => [ person_id => value ] ] ). A donation belongs to an event through its
 * certificates (Flux Certificates puts the event on the certificate). For each person of the event:
 *
 *  - as the donor (donor_id is the shared person id since 2.22.0): the whole line,
 *  - as the person a certificate was made for: their share of the line (the certificate's saplings, or the line
 *    split over its certificates).
 *
 * Keys: {bagis_tutari}, {bagis_adedi}, {bagis_turu}, {birim_fiyat}, {bagis_tarihi} and per donation type of the
 * event {<type>_adedi}, {<type>_tutari} (type slug, e.g. {fidan_adedi}). Only paid donations count. Without
 * Flux Certificates (or an older Flux CRM that never runs the filter) nothing happens.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Event_Vars {

	public static function init() {
		add_filter( 'flx_event_vars', array( __CLASS__, 'vars' ), 20, 3 );
	}

	/**
	 * @param array $out       Values from other packages.
	 * @param int   $event_id  Event.
	 * @param int[] $pids      People the message may go to.
	 * @return array
	 */
	public static function vars( $out, $event_id, $pids ) {
		$out  = is_array( $out ) ? $out : array();
		$pids = array_values( array_unique( array_filter( array_map( 'intval', (array) $pids ) ) ) );
		if ( ! (int) $event_id || ! function_exists( 'flxs_table' ) ) {
			return $out;
		}
		$per = self::collect( (int) $event_id, $pids );
		$cur = flxb_default_currency();

		$amount = array();
		$qty    = array();
		$types  = array();
		$unit   = array();
		$date   = array();
		$by     = array(); // type_id => [ person_id => [ qty, amounts[currency] ] ]
		foreach ( $per as $pid => $lines ) {
			$sum = array();
			$n   = 0;
			$tt  = array();
			$up  = array();
			$at  = '';
			foreach ( $lines as $l ) {
				$sum[ $l['currency'] ] = ( $sum[ $l['currency'] ] ?? 0 ) + $l['amount'];
				$n                    += $l['qty'];
				if ( '' !== $l['type'] ) {
					$tt[ $l['type'] ] = 1;
				}
				if ( $l['unit_price'] > 0 ) {
					$up[ flxb_money( $l['unit_price'], $l['currency'] ) ] = 1;
				}
				$at = max( $at, $l['created_at'] );
				if ( $l['type_id'] ) {
					$b                  = $by[ $l['type_id'] ][ $pid ] ?? array( 0, array() );
					$b[0]              += $l['qty'];
					$b[1][ $l['currency'] ] = ( $b[1][ $l['currency'] ] ?? 0 ) + $l['amount'];
					$by[ $l['type_id'] ][ $pid ] = $b;
				}
			}
			$amount[ $pid ] = self::money_list( $sum );
			$qty[ $pid ]    = number_format_i18n( $n );
			$types[ $pid ]  = implode( ', ', array_keys( $tt ) );
			$unit[ $pid ]   = implode( ', ', array_keys( $up ) );
			$date[ $pid ]   = '' !== $at ? flxb_date( $at, 'date' ) : '';
		}

		$out['bagis_tutari'] = array( 'label' => __( 'Donation amount of the person', 'flx-donate' ), 'sample' => flxb_money( 450, $cur ), 'values' => $amount );
		$out['bagis_adedi']  = array( 'label' => __( 'Donation quantity of the person', 'flx-donate' ), 'sample' => number_format_i18n( 3 ), 'values' => $qty );
		$out['bagis_turu']   = array( 'label' => __( 'Donation type', 'flx-donate' ), 'sample' => __( 'Sapling', 'flx-donate' ), 'values' => $types );
		$out['birim_fiyat']  = array( 'label' => __( 'Unit price', 'flx-donate' ), 'sample' => flxb_money( 150, $cur ), 'values' => $unit );
		$out['bagis_tarihi'] = array( 'label' => __( 'Donation date', 'flx-donate' ), 'sample' => flxb_date( gmdate( 'Y-m-d H:i:s' ), 'date' ), 'values' => $date );

		/* Per donation type of this event: {fidan_adedi}, {fidan_tutari} … */
		foreach ( $by as $type_id => $people ) {
			$t   = FLXB_Types::get( (int) $type_id );
			$key = $t ? self::key( (string) $t['slug'], (int) $type_id ) : '';
			if ( '' === $key || isset( $out[ $key . '_adedi' ] ) ) {
				continue;
			}
			$q = array();
			$a = array();
			foreach ( $people as $pid => $b ) {
				$q[ $pid ] = number_format_i18n( $b[0] );
				$a[ $pid ] = self::money_list( $b[1] );
			}
			$title = (string) $t['title'];
			/* translators: %s: donation type, e.g. Sapling */
			$out[ $key . '_adedi' ] = array( 'label' => sprintf( __( '%s: quantity', 'flx-donate' ), $title ), 'sample' => number_format_i18n( 3 ), 'values' => $q );
			/* translators: %s: donation type, e.g. Sapling */
			$out[ $key . '_tutari' ] = array( 'label' => sprintf( __( '%s: amount', 'flx-donate' ), $title ), 'sample' => flxb_money( 450, $cur ), 'values' => $a );
		}

		/**
		 * Change the donation values of event messages.
		 *
		 * @param array $out      All values (other packages' too).
		 * @param int   $event_id Event.
		 * @param array $per      person_id => lines [ donation_id, type_id, type, qty, amount, currency, unit_price, created_at ].
		 */
		return (array) apply_filters( 'flxb_event_vars', $out, (int) $event_id, $per );
	}

	/**
	 * Paid donation lines of the event per person.
	 *
	 * @return array person_id => [ donation_id => line ]
	 */
	public static function collect( $event_id, array $pids ) {
		global $wpdb;
		if ( ! $pids ) {
			return array();
		}
		$want  = array_fill_keys( $pids, true );
		$certs = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT code, person_id, data FROM ' . flxs_table( 'certificates' ) . " WHERE event_id = %d AND status <> 'revoked'", (int) $event_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( ! $certs ) {
			return array();
		}
		$by_code = array();
		foreach ( $certs as $c ) {
			$by_code[ (string) $c['code'] ] = array(
				'person' => (int) $c['person_id'],
				'units'  => function_exists( 'flxs_cert_fidan' ) ? (int) flxs_cert_fidan( $c['data'] ) : 0,
			);
		}

		/* Donation lines whose (first) certificate is one of the event's. */
		$t    = flxb_table( 'donations' );
		$paid = "'" . implode( "','", array_map( 'esc_sql', FLXB_Donations::PAID ) ) . "'";
		$rows = array();
		foreach ( array_chunk( array_keys( $by_code ), 500 ) as $chunk ) {
			$in = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			// phpcs:ignore WordPress.DB
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, donor_id, amount, currency, type_id, quantity, unit_price, certificate_code, meta, created_at FROM {$t} WHERE status IN ({$paid}) AND certificate_code IN ({$in})", $chunk ), ARRAY_A ) as $r ) {
				$rows[ (int) $r['id'] ] = $r;
			}
		}

		$titles = array();
		$per    = array();
		foreach ( $rows as $id => $r ) {
			$codes = array( (string) $r['certificate_code'] );
			$meta  = json_decode( (string) $r['meta'], true );
			foreach ( (array) ( is_array( $meta ) ? ( $meta['certificates'] ?? array() ) : array() ) as $c ) {
				$codes[] = (string) $c;
			}
			$codes = array_values( array_unique( array_filter( $codes ) ) );
			$qty   = max( 1, (int) $r['quantity'] );
			$total = (float) $r['amount'];
			$price = (float) $r['unit_price'] > 0 ? (float) $r['unit_price'] : $total / $qty;
			$tid   = (int) $r['type_id'];
			if ( $tid && ! isset( $titles[ $tid ] ) ) {
				$tp             = FLXB_Types::get( $tid );
				$titles[ $tid ] = $tp ? (string) $tp['title'] : '';
			}
			$base = array(
				'donation_id' => (int) $id,
				'type_id'     => $tid,
				'type'        => $tid ? $titles[ $tid ] : '',
				'currency'    => strtoupper( (string) $r['currency'] ),
				'unit_price'  => round( $price, 4 ),
				'created_at'  => (string) $r['created_at'],
			);

			/* The donor: the whole line. */
			$donor = (int) $r['donor_id'];
			if ( $donor && isset( $want[ $donor ] ) ) {
				$per[ $donor ][ $id ] = $base + array( 'qty' => $qty, 'amount' => $total );
			}

			/* People the certificates were made for: their share. */
			$in_event = array_values( array_intersect( $codes, array_keys( $by_code ) ) );
			$n_codes  = max( 1, count( $codes ) );
			foreach ( $in_event as $code ) {
				$p = $by_code[ $code ]['person'];
				if ( ! $p || $p === $donor || ! isset( $want[ $p ] ) ) {
					continue;
				}
				$u = $by_code[ $code ]['units'] > 0 ? min( $qty, $by_code[ $code ]['units'] ) : max( 1, (int) round( $qty / $n_codes ) );
				$l = $per[ $p ][ $id ] ?? $base + array( 'qty' => 0, 'amount' => 0.0 );
				$l['qty']    += $u;
				$l['amount'] += round( $price * $u, 2 );
				$per[ $p ][ $id ] = $l;
			}
		}
		return $per;
	}

	/** "450 ₺" or "450 ₺ + $20" when one person gave in several currencies. */
	private static function money_list( array $sum ) {
		$o = array();
		foreach ( $sum as $c => $v ) {
			$o[] = flxb_money( $v, $c );
		}
		return implode( ' + ', $o );
	}

	/** Type slug as a message key: "fidan-bagisi" → "fidan_bagisi". */
	private static function key( $slug, $id ) {
		$k = trim( preg_replace( '/[^a-z0-9]+/', '_', strtolower( remove_accents( $slug ) ) ), '_' );
		return '' !== $k ? $k : 'tur' . (int) $id;
	}
}
