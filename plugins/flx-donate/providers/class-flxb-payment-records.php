<?php
/**
 * Flux Donate: payment records of every provider.
 *
 * Payments live in Flux Forms's table {prefix}emsfb_pay_ (the response viewer and the final
 * submit read it); `gateway` holds the provider id. Flux Forms's store class is used when it is
 * loaded, otherwise the rows are written directly with the same columns.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Payment_Records {

	const COLUMNS = array( 'form_id', 'track', 'gateway', 'payment_type', 'transaction_id', 'capture_id', 'subscription_id', 'plan_id', 'product_id', 'interval_unit', 'amount', 'currency', 'status', 'payer_email', 'uid', 'ip', 'form_name', 'meta' );

	private static function store() {
		if ( ! class_exists( '\Emsfb\StripePayment' ) && defined( 'EMSFB_PLUGIN_DIRECTORY' ) ) {
			$f = EMSFB_PLUGIN_DIRECTORY . 'vendor/stripe/class-Emsfb-stripe-payment.php';
			if ( is_readable( $f ) ) {
				require_once $f;
			}
		}
		if ( class_exists( '\Emsfb\StripePayment' ) ) {
			\Emsfb\StripePayment::maybe_install_table();
			return true;
		}
		return false;
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'emsfb_pay_';
	}

	private static function clean( array $data ) {
		$data = array_intersect_key( $data, array_flip( self::COLUMNS ) );
		if ( isset( $data['meta'] ) && ! is_string( $data['meta'] ) ) {
			$data['meta'] = wp_json_encode( $data['meta'] );
		}
		return $data;
	}

	/** @return int|false Insert id. */
	public static function insert( array $data ) {
		global $wpdb;
		$data = self::clean( $data );
		if ( self::store() ) {
			return \Emsfb\StripePayment::insert_payment( $data );
		}
		$now                = current_time( 'mysql', true );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		return $wpdb->insert( self::table(), $data ) ? (int) $wpdb->insert_id : false;
	}

	/** @return int|false */
	public static function update( $id, array $data ) {
		global $wpdb;
		$data = self::clean( $data );
		if ( self::store() ) {
			return \Emsfb\StripePayment::update_payment( (int) $id, $data );
		}
		$data['updated_at'] = current_time( 'mysql', true );
		return $wpdb->update( self::table(), $data, array( 'id' => (int) $id ) );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** Latest record for a confirmation code, optionally of one provider. */
	public static function get_by_track( $track, $gateway = '' ) {
		global $wpdb;
		if ( '' === (string) $track ) {
			return null;
		}
		$t = self::table();
		if ( '' !== $gateway ) {
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE track = %s AND gateway = %s ORDER BY id DESC LIMIT 1", (string) $track, (string) $gateway ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE track = %s ORDER BY id DESC LIMIT 1", (string) $track ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function get_by_reference( $gateway, $reference, $column = 'transaction_id' ) {
		global $wpdb;
		if ( '' === (string) $reference || ! in_array( $column, array( 'transaction_id', 'subscription_id' ), true ) ) {
			return null;
		}
		$t = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE {$column} = %s AND gateway = %s ORDER BY id DESC LIMIT 1", (string) $reference, (string) $gateway ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function recent( $limit = 50, $offset = 0 ) {
		global $wpdb;
		self::store();
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d OFFSET %d', (int) $limit, (int) $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}

if ( ! class_exists( 'FLXF_Payment_Records', false ) ) {
	class_alias( 'FLXB_Payment_Records', 'FLXF_Payment_Records' );
}
