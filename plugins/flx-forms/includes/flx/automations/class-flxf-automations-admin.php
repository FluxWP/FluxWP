<?php
/**
 * Flux Forms Automations: the Forms › Automations menu entry (2.8.0: the Forms React screen, data over the
 * flxFormsAutomation* operations in services/class-flxf-automations-service.php) and the "Sapling Donation"
 * template card on the form builder's Create screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXF_Automations_Admin {

	const SLUG = 'flxf_automations';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
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

	private static function ver( $file ) {
		$p = __DIR__ . '/assets/' . $file;
		return is_readable( $p ) ? (string) filemtime( $p ) : '1';
	}

	public static function assets( $hook ) {
		if ( false !== strpos( (string) $hook, 'Emsfb_create' ) ) {
			/* EFB enqueues its builder scripts while rendering the page, so join in the footer. */
			add_action( 'admin_footer', array( __CLASS__, 'builder_template_assets' ), 1 );
		}
	}

	/** "Sapling Donation" card on EFB's Create screen (added from outside admin-efb.js). */
	public static function builder_template_assets() {
		if ( ! wp_script_is( 'Emsfb-admin-js', 'registered' ) && ! wp_script_is( 'Emsfb-admin-js', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_script( 'flxf-automations-form-template', plugins_url( 'assets/form-template.js', __FILE__ ), array( 'Emsfb-admin-js', 'efb-forms-js' ), self::ver( 'form-template.js' ), true );
		wp_localize_script(
			'flxf-automations-form-template',
			'FLXF_FORM_TPL',
			array(
				'title'     => FLXF_Automations::seed_text( 'Sapling Donation' ),
				'desc'      => __( 'Donation form with sapling packages, card payment and a certificate automation.', 'flx-forms' ),
				'structure' => FLXF_Automations::donation_form_structure(),
			)
		);
	}
}
