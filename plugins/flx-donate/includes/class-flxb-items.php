<?php
/**
 * Flux Donate: donation line items. One row per option line of a gift (option, quantity, unit price
 * snapshot). For types that track units one by one (kurban module: proxy required) a line is split
 * into one row per unit, each with the name of the person it is given on behalf of.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Items {

	public static function for_donation( $donation_id ) {
		global $wpdb;
		$t = flxb_table( 'items' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE donation_id = %d ORDER BY id ASC", (int) $donation_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_for_donation( $donation_id ) {
		global $wpdb;
		$t = flxb_table( 'items' );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE donation_id = %d", (int) $donation_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Create the items of a donation once (no-op when it already has items).
	 *
	 * @param array $donation      Donation row.
	 * @param array $lines         [ { key, label, units, unit_price, amount, unit, units_per } ]; empty = one line from the donation snapshot.
	 * @param array $beneficiaries Names on whose behalf the units are given (proxy types).
	 * @return int Rows created.
	 */
	public static function create( array $donation, array $lines = array(), array $beneficiaries = array(), array $phones = array() ) {
		global $wpdb;
		$id = (int) $donation['id'];
		if ( ! $id || self::count_for_donation( $id ) ) {
			return 0;
		}
		$type = (int) $donation['type_id'] ? FLXB_Types::get( (int) $donation['type_id'] ) : null;
		if ( ! $lines ) {
			$opt   = $type && '' !== (string) $donation['option_key'] ? FLXB_Types::option( $type, (string) $donation['option_key'] ) : null;
			$lines = array(
				array(
					'key'        => (string) $donation['option_key'],
					'label'      => (string) $donation['option_label'],
					'units'      => max( 1, (int) $donation['quantity'] ),
					'unit_price' => (float) $donation['unit_price'],
					'amount'     => (float) $donation['amount'],
					'unit'       => $opt ? $opt['unit'] : '',
					'units_per'  => $opt ? (int) $opt['units_per'] : 1,
				),
			);
		}
		$split     = $type && FLXB_Types::tracks_items( $type ); // 2.11: tracked types split too
		$named     = $type && FLXB_Types::requires_proxy( $type );
		$intention = $type ? (string) FLXB_Types::meta( $type, 'intention', '' ) : '';
		$meat      = $type && FLXB_Types::meta( $type, 'meat_to_needy', 'adak' === $intention );
		$donor     = (int) $donation['donor_id'] ? FLXB_Donors::get( (int) $donation['donor_id'] ) : null;
		$fallback  = $donor ? (string) $donor['name'] : '';
		$now       = flxb_now();
		$n         = 0;
		$b         = 0;
		foreach ( $lines as $l ) {
			$units = max( 1, (int) ( $l['units'] ?? 1 ) );
			$rows  = $split && '' !== (string) ( $l['key'] ?? '' ) ? $units : 1;
			for ( $i = 0; $i < $rows; $i++ ) {
				$qty  = $split && $rows > 1 ? 1 : $units;
				$name  = $split ? ( $beneficiaries[ $b ] ?? $fallback ) : '';
				$phone = $split ? preg_replace( '/[^0-9+]/', '', (string) ( $phones[ $b ] ?? '' ) ) : ''; // 2.16.0
				if ( $split ) {
					++$b;
				}
				$wpdb->insert(
					flxb_table( 'items' ),
					array(
						'donation_id'      => $id,
						'type_id'          => (int) $donation['type_id'],
						'option_key'       => sanitize_key( (string) ( $l['key'] ?? '' ) ),
						'option_label'     => mb_substr( sanitize_text_field( (string) ( $l['label'] ?? '' ) ), 0, 191 ),
						'unit'             => sanitize_key( (string) ( $l['unit'] ?? '' ) ),
						'quantity'         => $qty,
						'unit_price'       => round( (float) ( $l['unit_price'] ?? 0 ), 4 ),
						'amount'           => round( $split && $rows > 1 ? (float) ( $l['unit_price'] ?? 0 ) : (float) ( $l['amount'] ?? 0 ), 4 ),
						'currency'         => strtoupper( (string) $donation['currency'] ),
						'beneficiary_name' => mb_substr( sanitize_text_field( $name ), 0, 191 ),
						'beneficiary_phone' => mb_substr( $phone, 0, 32 ),
						'intention'        => $named ? $intention : '',
						'meat_to_needy'    => $named && $meat ? 1 : 0,
						'status'           => 'pending',
						'meta'             => (int) ( $l['units_per'] ?? 1 ) > 1 ? wp_json_encode( array( 'units_per' => (int) $l['units_per'] ) ) : null,
						'created_at'       => $now,
						'updated_at'       => $now,
					)
				);
				if ( $split && $wpdb->insert_id ) {
					FLXB_Track::on_created( (int) $wpdb->insert_id ); // 2.10.0: 7-digit code + history
				}
				++$n;
			}
		}
		return $n;
	}

	/** Correct the names of a donation's items (admin); every change is logged on the donation. */
	public static function update_names( $donation_id, array $names ) {
		global $wpdb;
		$changes = array();
		foreach ( self::for_donation( $donation_id ) as $it ) {
			if ( ! array_key_exists( (int) $it['id'], $names ) ) {
				continue;
			}
			$new = mb_substr( sanitize_text_field( (string) $names[ (int) $it['id'] ] ), 0, 191 );
			if ( $new !== (string) $it['beneficiary_name'] ) {
				$wpdb->update( flxb_table( 'items' ), array( 'beneficiary_name' => $new, 'updated_at' => flxb_now() ), array( 'id' => (int) $it['id'] ) );
				$changes[] = array( 'item' => (int) $it['id'], 'from' => (string) $it['beneficiary_name'], 'to' => $new );
			}
		}
		if ( $changes ) {
			FLXB_Donations::add_audit( $donation_id, 'beneficiaries', $changes );
		}
		return count( $changes );
	}

	/** Items for donations recorded before 1.1 (one line each, from the donation snapshot). */
	public static function backfill() {
		global $wpdb;
		$dn    = flxb_table( 'donations' );
		$it    = flxb_table( 'items' );
		$after = 0;
		do {
			$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT x.* FROM {$dn} x LEFT JOIN {$it} i ON i.donation_id = x.id WHERE i.id IS NULL AND x.id > %d ORDER BY x.id ASC LIMIT 200", $after ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $rows as $d ) {
				$after = (int) $d['id'];
				self::create( $d );
			}
		} while ( count( $rows ) === 200 );
	}

	/** "2 × One share · Ali Veli" lines for lists and the CSV export. */
	public static function summary( array $items, $currency = '' ) {
		$out = array();
		foreach ( $items as $it ) {
			$label = '' !== (string) $it['option_label'] ? (string) $it['option_label'] : __( 'Donation', 'flx-donate' );
			$s     = number_format_i18n( (int) $it['quantity'] ) . ' × ' . $label . ' (' . flxb_money( $it['unit_price'], '' !== $currency ? $currency : $it['currency'] ) . ')';
			if ( '' !== (string) $it['beneficiary_name'] ) {
				$s .= ' · ' . $it['beneficiary_name'];
			}
			$out[] = $s;
		}
		return $out;
	}
}
