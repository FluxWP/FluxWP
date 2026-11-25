<?php
/**
 * Flux Forms Automations: the Forms › Automations menu entry (2.8.0: the Forms React screen, data over the
 * flxFormsAutomation* operations in services/class-flxf-automations-service.php) and the "Sapling Donation"
 * template in the builder (Forms › New form).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXF_Automations_Admin {

	const SLUG = 'flxf_automations';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
	}

	public static function menu() {
		add_submenu_page(
			'Emsfb',
			__( 'Automations', 'flx-forms' ),
			__( 'Automations', 'flx-forms' ),
			FLXF_Automations::capability(),
			self::SLUG,
			'flxf_screen_page'
		);
	}

	/**
	 * "Sapling Donation" among the builder's templates (Forms › New form).
	 *
	 * @param mixed $templates Templates.
	 * @return array<int|string,mixed>
	 */
	public static function builder_template( $templates ) {
		$templates   = is_array( $templates ) ? $templates : array();
		$templates[] = array(
			'key'         => 'sapling-donation',
			'title'       => FLXF_Automations::seed_text( 'Sapling Donation' ),
			'description' => __( 'Donation form with sapling packages, card payment and a certificate automation.', 'flx-forms' ),
			'type'        => 'payment',
			'structure'   => FLXF_Automations::donation_form_structure(),
		);
		return $templates;
	}
}
