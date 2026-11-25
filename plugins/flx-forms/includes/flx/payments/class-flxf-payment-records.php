<?php
/**
 * Flux Forms: payment records ({$wpdb->prefix}emsfb_pay_). One row per payment a form started; `gateway` holds the
 * provider id ("woocommerce" for the WooCommerce checkout), `transaction_id` the provider's reference, `track` the entry's confirmation code.
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 *
 * @package FLX_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXF_Payment_Records {

	const DB_VERSION        = '1.0.0';
	const DB_VERSION_OPTION = 'emsfb_pay_table_version';

	/** Columns callers may write. */
	const COLUMNS = array(
		'form_id'         => '%d',
		'track'           => '%s',
		'gateway'         => '%s',
		'payment_type'    => '%s',
		'transaction_id'  => '%s',
		'capture_id'      => '%s',
		'subscription_id' => '%s',
		'plan_id'         => '%s',
		'product_id'      => '%s',
		'interval_unit'   => '%s',
		'amount'          => '%f',
		'currency'        => '%s',
		'status'          => '%s',
		'payer_email'     => '%s',
		'uid'             => '%s',
		'ip'              => '%s',
		'form_name'       => '%s',
		'meta'            => '%s',
	);

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'emsfb_pay_';
	}

	public static function maybe_install_table() {
		flxf_payment_install_table();
	}

	/** Keep only known columns and build the matching formats. */
	private static function prepare_row( array $data ) {
		$row     = array();
		$formats = array();
		foreach ( self::COLUMNS as $col => $fmt ) {
			if ( ! array_key_exists( $col, $data ) ) {
				continue;
			}
			$v = $data[ $col ];
			if ( 'meta' === $col && ! is_string( $v ) ) {
				$v = wp_json_encode( $v );
			}
			if ( '%d' === $fmt ) {
				$v = (int) $v;
			} elseif ( '%f' === $fmt ) {
				$v = (float) $v;
			} else {
				$v = sanitize_text_field( (string) $v );
				if ( 'currency' === $col ) {
					$v = strtoupper( $v );
				}
			}
			$row[ $col ] = $v;
			$formats[]   = $fmt;
		}
		return array( $row, $formats );
	}

	/** @return int|false Insert id. */
	public static function insert( array $data ) {
		global $wpdb;
		self::maybe_install_table();
		if ( ! isset( $data['status'] ) ) {
			$data['status'] = 'pending';
		}
		list( $row, $formats ) = self::prepare_row( $data );
		$now               = current_time( 'mysql', true );
		$row['created_at'] = $now;
		$row['updated_at'] = $now;
		$formats[]         = '%s';
		$formats[]         = '%s';
		return $wpdb->insert( self::table_name(), $row, $formats ) ? (int) $wpdb->insert_id : false;
	}

	/** @return int|false Rows updated. */
	public static function update( $id, array $data ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		self::maybe_install_table();
		list( $row, $formats ) = self::prepare_row( $data );
		if ( empty( $row ) ) {
			return 0;
		}
		$row['updated_at'] = current_time( 'mysql', true );
		$formats[]         = '%s';
		return $wpdb->update( self::table_name(), $row, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/** Latest record for a confirmation code, optionally of one provider. */
	public static function get_by_track( $track, $gateway = '' ) {
		global $wpdb;
		if ( '' === (string) $track ) {
			return null;
		}
		self::maybe_install_table();
		$t = self::table_name();
		if ( '' !== $gateway ) {
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE track = %s AND gateway = %s ORDER BY id DESC LIMIT 1", (string) $track, (string) $gateway ) ); // phpcs:ignore WordPress.DB
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE track = %s ORDER BY id DESC LIMIT 1", (string) $track ) ); // phpcs:ignore WordPress.DB
	}

	public static function get_by_reference( $gateway, $reference ) {
		global $wpdb;
		if ( '' === (string) $reference ) {
			return null;
		}
		self::maybe_install_table();
		$t = self::table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE transaction_id = %s AND gateway = %s ORDER BY id DESC LIMIT 1", (string) $reference, (string) $gateway ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Latest records, newest first.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function latest( $limit = 50 ) {
		global $wpdb;
		self::maybe_install_table();
		$t    = self::table_name();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} ORDER BY id DESC LIMIT %d", max( 1, (int) $limit ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? $rows : array();
	}
}
