<?php
/**
 * Flux Forms: Automations (Otomasyonlar) loader. Required from includes/flx/flx-fork.php.
 *
 * Flows ("when a form is submitted / a payment is completed → do these steps") stored as a
 * private custom post type. Certificate steps call the Flux Certificates plugin's public PHP API
 * (guarded with function_exists), so that plugin stays untouched.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'FLXF_Automations' ) ) {
	require_once __DIR__ . '/class-flxf-automations.php';
	require_once __DIR__ . '/class-flxf-automations-engine.php';
	FLXF_Automations::init();
	FLXF_Automations_Engine::init();
	if ( is_admin() ) {
		require_once __DIR__ . '/class-flxf-automations-admin.php';
		FLXF_Automations_Admin::init();
	}
}

if ( ! function_exists( 'flxf_automations_form_issues_certificate' ) ) {
	/**
	 * 2.1.0: does an enabled flow make a certificate for this form (trigger on this form or on every
	 * form, with a "create certificate" step)? Flux Donate asks this so a form paid on the WooCommerce
	 * checkout does not get a second certificate from the order.
	 *
	 * @param int $form_id Form id.
	 * @return bool
	 */
	function flxf_automations_form_issues_certificate( $form_id ) {
		$form_id = (int) $form_id;
		foreach ( FLXF_Automations::get_flows( true ) as $flow ) {
			$fid = (int) ( $flow['trigger']['form_id'] ?? 0 );
			if ( $fid && $fid !== $form_id ) {
				continue;
			}
			foreach ( (array) $flow['actions'] as $a ) {
				if ( 'create_certificate' === ( $a['type'] ?? '' ) ) {
					return true;
				}
			}
		}
		return false;
	}
}

