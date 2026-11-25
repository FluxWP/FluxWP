<?php
/**
 * Flux Forms: payment pieces other plugins still call by their old names. Always loaded (also when Flux Donate owns
 * the payment layer): Flux Donate's form payments call \Emsfb\StripeHandlerEfb (rows check, form structure, server
 * price) and \Emsfb\StripePayment (the {prefix}emsfb_pay_ table), which lived in the removed Stripe add-on. These
 * classes are thin and take no payment themselves; Flux Forms takes payments only on the WooCommerce checkout.
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 *
 * @package FLX_Form
 */

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	require_once __DIR__ . '/class-flxf-payment-core.php';

	if ( ! function_exists( 'flxf_payment_install_table' ) ) {
		/** Create or upgrade {prefix}emsfb_pay_ (once per table version). */
		function flxf_payment_install_table() {
			if ( '1.0.0' === get_option( 'emsfb_pay_table_version' ) ) {
				return;
			}
			global $wpdb;
			$table   = $wpdb->prefix . 'emsfb_pay_';
			$charset = $wpdb->get_charset_collate();
			$sql     = "CREATE TABLE {$table} (
			  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			  form_id bigint(20) unsigned NOT NULL DEFAULT 0,
			  track varchar(64) NOT NULL DEFAULT '',
			  gateway varchar(32) NOT NULL DEFAULT '',
			  payment_type varchar(32) NOT NULL DEFAULT 'one-time',
			  transaction_id varchar(191) NOT NULL DEFAULT '',
			  capture_id varchar(191) NOT NULL DEFAULT '',
			  subscription_id varchar(191) NOT NULL DEFAULT '',
			  plan_id varchar(191) NOT NULL DEFAULT '',
			  product_id varchar(191) NOT NULL DEFAULT '',
			  interval_unit varchar(16) NOT NULL DEFAULT '',
			  amount decimal(20,4) NOT NULL DEFAULT 0,
			  currency varchar(8) NOT NULL DEFAULT '',
			  status varchar(32) NOT NULL DEFAULT 'pending',
			  payer_email varchar(191) NOT NULL DEFAULT '',
			  uid varchar(191) NOT NULL DEFAULT '',
			  ip varchar(64) NOT NULL DEFAULT '',
			  form_name varchar(255) NOT NULL DEFAULT '',
			  meta longtext NULL,
			  created_at datetime NULL DEFAULT NULL,
			  updated_at datetime NULL DEFAULT NULL,
			  PRIMARY KEY  (id),
			  KEY transaction_id (transaction_id),
			  KEY track (track),
			  KEY subscription_id (subscription_id),
			  KEY form_id (form_id)
			) {$charset};";
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
			dbDelta( $sql );
			update_option( 'emsfb_pay_table_version', '1.0.0', false );
		}
	}
}

namespace Emsfb {

	if ( ! class_exists( '\Emsfb\StripeHandlerEfb', false ) ) {
		/** Old name of the form payment checks (Flux Donate calls it). */
		class StripeHandlerEfb {

			/** @return array<int,mixed>|null */
			public static function load_form_structure( $form_id ) {
				return \FLXF_Payment_Core::load_form_structure( $form_id );
			}

			/** The form has the card payment box. */
			public static function is_stripe_form( $fs ) {
				return \FLXF_Payment_Core::has_pay_box( $fs );
			}

			/** @return array<string,mixed> */
			public static function server_amount( $public, $rows, $fs, $quiet = false, $form_id = 0 ) {
				return \FLXF_Payment_Core::server_amount( $public, $rows, $fs, $quiet, $form_id );
			}

			/**
			 * @param mixed  $params        Request JSON params.
			 * @param bool   $require_token Stripe subscriptions need a card token.
			 * @param string $provider      Provider id.
			 * @return true|string
			 */
			public static function validate_card_add( $params, $require_token = true, $provider = 'stripe' ) {
				if ( 'stripe' === $provider ) {
					$check = \FLXF_Payment_Core::validate( $params, null, true );
					if ( true !== $check || ! $require_token || ! is_array( $params ) ) {
						return $check;
					}
					$fs     = \FLXF_Payment_Core::load_form_structure( isset( $params['id'] ) && is_scalar( $params['id'] ) ? absint( $params['id'] ) : 0 );
					$method = is_array( $fs ) && isset( $fs[0]['paymentmethod'] ) ? (string) $fs[0]['paymentmethod'] : 'charge';
					$token  = isset( $params['token'] ) && is_string( $params['token'] ) ? $params['token'] : '';
					if ( 'charge' !== $method && ! preg_match( '/^(tok|pm|src|card)_[A-Za-z0-9_]+$/', $token ) ) {
						return __( 'Invalid payment data. Please refresh the page and try again.', 'flx-forms' );
					}
					return true;
				}
				$p = function_exists( 'flxf_payment_provider' ) ? flxf_payment_provider( (string) $provider ) : null;
				return \FLXF_Payment_Core::validate( $params, $p );
			}
		}
	}

	if ( ! class_exists( '\Emsfb\StripePayment', false ) ) {
		/** Old name of the payment table store (Flux Donate calls it). */
		class StripePayment {

			public static function maybe_install_table() {
				\flxf_payment_install_table();
			}

			/** @return int|false */
			public static function insert_payment( $data ) {
				global $wpdb;
				self::maybe_install_table();
				$data               = is_array( $data ) ? $data : array();
				$now                = current_time( 'mysql', true );
				$data['created_at'] = $now;
				$data['updated_at'] = $now;
				return $wpdb->insert( $wpdb->prefix . 'emsfb_pay_', $data ) ? (int) $wpdb->insert_id : false;
			}

			/** @return int|false */
			public static function update_payment( $id, $data ) {
				global $wpdb;
				$data               = is_array( $data ) ? $data : array();
				$data['updated_at'] = current_time( 'mysql', true );
				return $wpdb->update( $wpdb->prefix . 'emsfb_pay_', $data, array( 'id' => (int) $id ) );
			}
		}
	}
}
