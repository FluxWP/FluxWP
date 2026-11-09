<?php
/**
 * Flux Donate: test payment provider. DEVELOPMENT ONLY, takes no money.
 *
 * Registered only when a developer opts in:
 *     add_filter( 'flxf_payments_enable_test_provider', '__return_true' );
 * or define( 'FLXF_PAYMENTS_TEST_PROVIDER', true ) in wp-config.php. Never on a live site.
 * The id stays "flxf_test" so records made under Flux Forms keep their provider.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXB_Payment_Test extends FLXB_Payment_Provider_Base {

	public function id() {
		return 'flxf_test';
	}

	public function label() {
		return __( 'Test provider (development only)', 'flx-donate' );
	}

	public function settings_fields() {
		return array(
			array(
				'key'     => 'flxfTestPayOutcome',
				'label'   => __( 'Test outcome', 'flx-donate' ),
				'type'    => 'select',
				'options' => array(
					'approve' => __( 'Approve every payment', 'flx-donate' ),
					'decline' => __( 'Decline every payment', 'flx-donate' ),
				),
				'help'    => __( 'No money is taken. Development only.', 'flx-donate' ),
			),
		);
	}

	public function front_assets() {
		return array(
			'scripts' => array(
				'flxf-pay-test' => array(
					'src'  => FLXB_URL . 'providers/assets/flxf-pay-test.js',
					'ver'  => flxb_payments_asset_ver( 'flxf-pay-test.js' ),
					'data' => array( 'flxfPayTestI18n' => array( 'note' => __( 'TEST MODE: no card is charged.', 'flx-donate' ) ) ),
				),
			),
		);
	}

	public function create_payment( $amount, $currency, array $meta ) {
		if ( 'decline' === $this->setting( 'flxfTestPayOutcome', 'approve' ) ) {
			return FLXB_Payment_Result::failed( __( 'Test provider: the payment was declined.', 'flx-donate' ) );
		}
		return FLXB_Payment_Result::completed( 'test_' . strtolower( wp_generate_password( 16, false, false ) ), array( 'record' => array( 'meta' => array( 'test' => true ) ) ) );
	}
}
