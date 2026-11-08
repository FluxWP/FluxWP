<?php
/**
 * Flux Forms: convenient base for payment providers. Extend it and implement
 * id(), label() and create_payment(); override the rest as needed.
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 *
 * @package FLX_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class FLXF_Payment_Provider_Base implements FLXF_Payment_Provider {

	/** Default: one-time charges in any ISO currency. */
	public function supports( $currency, $method ) {
		return 'charge' === $method && (bool) preg_match( '/^[A-Z]{3}$/', strtoupper( (string) $currency ) );
	}

	public function settings_fields() {
		return array();
	}

	/** Default: every field marked 'required' has a value. */
	public function is_configured() {
		foreach ( $this->settings_fields() as $f ) {
			if ( ! empty( $f['required'] ) && '' === $this->setting( $f['key'] ) ) {
				return false;
			}
		}
		return true;
	}

	public function front_assets() {
		return array();
	}

	/** Default: trust only a record that a webhook or create_payment() already marked completed. */
	public function verify( $record, array $params = array() ) {
		if ( is_object( $record ) && 'completed' === $record->status ) {
			return FLXF_Payment_Result::completed( (string) $record->transaction_id );
		}
		return FLXF_Payment_Result::failed( __( '\\nThe payment could not be verified yet.\\n', 'flx-forms' ) );
	}

	public function handle_webhook( $request ) {
		return new WP_REST_Response( array( 'error' => 'webhook_not_supported' ), 501 );
	}

	public function refund( $record, $amount = null ) {
		return FLXF_Payment_Result::failed( __( '\\nThis payment provider does not support refunds from the site.\\n', 'flx-forms' ) );
	}

	/** Read one of this provider's stored settings ('' when missing). */
	protected function setting( $key, $default = '' ) {
		return flxf_payment_setting( $key, $default );
	}
}
