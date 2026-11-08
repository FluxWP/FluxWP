<?php
/**
 * Flux Forms: test payment provider — DEVELOPMENT ONLY.
 *
 * Takes no money. It exists to exercise the generic payment flow and as a
 * reference implementation. It is NOT registered unless a developer opts in:
 *
 *     add_filter( 'flxf_payments_enable_test_provider', '__return_true' );
 *
 * (or define( 'FLXF_PAYMENTS_TEST_PROVIDER', true ) in wp-config.php). Never enable it on a live site.
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 *
 * @package FLX_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXF_Payment_Test extends FLXF_Payment_Provider_Base {

	public function id() {
		return 'flxf_test';
	}

	public function label() {
		return __( '\\nTest provider (development only)\\n', 'flx-forms' );
	}

	public function settings_fields() {
		return array(
			array(
				'key'     => 'flxfTestPayOutcome',
				'label'   => __( '[[\'Test outcome\\n', 'flx-forms' ),
				'type'    => 'select',
				'options' => array(
					'approve' => __( '\\nApprove every payment\\n', 'flx-forms' ),
					'decline' => __( '\\nDecline every payment\\n', 'flx-forms' ),
				),
				'help'    => __( '\\nNo money is taken. Development only.\\n', 'flx-forms' ),
			),
		);
	}

	public function front_assets() {
		return array(
			'scripts' => array(
				'flxf-pay-test' => array(
					'src' => plugins_url( 'assets/flxf-pay-test.js', dirname( __FILE__ ) ),
					'ver' => flxf_payments_asset_ver( 'flxf-pay-test.js' ),
					'data' => array( 'flxfPayTestI18n' => array( 'note' => __( '\\nTEST MODE: no card is charged.\\n', 'flx-forms' ) ) ),
				),
			),
		);
	}

	public function create_payment( $amount, $currency, array $meta ) {
		if ( 'decline' === $this->setting( 'flxfTestPayOutcome', 'approve' ) ) {
			return FLXF_Payment_Result::failed( __( '\\nTest provider: the payment was declined.\\n', 'flx-forms' ) );
		}
		return FLXF_Payment_Result::completed( 'test_' . strtolower( wp_generate_password( 16, false, false ) ), array(
			'record' => array( 'meta' => array( 'test' => true, 'reference' => $meta['reference'] ?? '' ) ),
		) );
	}
}

add_filter(
	'flxf_payment_providers',
	function ( $providers ) {
		$on = ( defined( 'FLXF_PAYMENTS_TEST_PROVIDER' ) && FLXF_PAYMENTS_TEST_PROVIDER ) || apply_filters( 'flxf_payments_enable_test_provider', false );
		if ( $on ) {
			$providers['flxf_test'] = 'FLXF_Payment_Test';
		}
		return $providers;
	},
	100
);
