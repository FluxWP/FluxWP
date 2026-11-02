<?php
/**
 * Flux Forms: payment records for every provider.
 *
 * Uses the table Stripe payments already live in ({$wpdb->prefix}emsfb_pay_, owned by
 * vendor/stripe/class-Emsfb-stripe-payment.php); the `gateway` column holds the provider id.
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 *
 * @package FLX_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXF_Payment_Records {

	private static function store() {
		if ( ! class_exists( '\Emsfb\StripePayment' ) ) {
			$f = EMSFB_PLUGIN_DIRECTORY . 'vendor/stripe/class-Emsfb-stripe-payment.php';
			if ( is_readable( $f ) ) {
				require_once $f;
			}
		}
		return class_exists( '\Emsfb\StripePayment' );
	}

	/** @return int|false Insert id. */
	public static function insert( array $data ) {
		return self::store() ? \Emsfb\StripePayment::insert_payment( $data ) : false;
	}

	/** @return int|false */
	public static function update( $id, array $data ) {
		return self::store() ? \Emsfb\StripePayment::update_payment( $id, $data ) : false;
	}

	/** Latest record for a confirmation code, optionally of one provider. */
	public static function get_by_track( $track, $gateway = '' ) {
		global $wpdb;
		if ( ! self::store() || '' === (string) $track ) {
			return null;
		}
		\Emsfb\StripePayment::maybe_install_table();
		$t = \Emsfb\StripePayment::table_name();
		if ( '' !== $gateway ) {
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE track = %s AND gateway = %s ORDER BY id DESC LIMIT 1", (string) $track, (string) $gateway ) );
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE track = %s ORDER BY id DESC LIMIT 1", (string) $track ) );
	}

	public static function get_by_reference( $gateway, $reference ) {
		global $wpdb;
		if ( ! self::store() || '' === (string) $reference ) {
			return null;
		}
		$t = \Emsfb\StripePayment::table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE transaction_id = %s AND gateway = %s ORDER BY id DESC LIMIT 1", (string) $reference, (string) $gateway ) );
	}
}
