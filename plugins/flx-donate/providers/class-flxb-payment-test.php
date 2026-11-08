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
		return __( '\\nTest provider (development only)\\n', 'flx-donate' );
	}

	public function settings_fields() {
		return array(
			array(
				'key'     => 'flxfTestPayOutcome',
				'label'   => __( '[[\'Test outcome\\n', 'flx-donate' ),
				'type'    => 'select',
				'options' => array(
					'approve' => __( '\\nApprove every payment\\n', 'flx-donate' ),
					'decline' => __( '\\nDecline every payment\\n', 'flx-donate' ),
				),
				'help'    => __( '\\nNo money is taken. Development only.\\n', 'flx-donate' ),
			),
		);
	}

	public function front_assets() {
		return array(
			'scripts' => array(
				'flxf-pay-test' => array(
					'src'  => FLXB_URL . 'providers/assets/flxf-pay-test.js',
					'ver'  => flxb_payments_asset_ver( 'flxf-pay-test.js' ),
					'data' => array( 'flxfPayTestI18n' => array( 'note' => __( '\\nTEST MODE: no card is charged.\\n', 'flx-donate' ) ) ),
				),
			),
		);
	}

	public function create_payment( $amount, $currency, array $meta ) {
		if ( 'decline' === $this->setting( 'flxfTestPayOutcome', 'approve' ) ) {
			return FLXB_Payment_Result::failed( __( '\\nTest provider: the payment was declined.\\n', 'flx-donate' ) );
		}
		return FLXB_Payment_Result::completed( 'test_' . strtolower( wp_generate_password( 16, false, false ) ), array( 'record' => array( 'meta' => array( 'test' => true ) ) ) );
	}
}
