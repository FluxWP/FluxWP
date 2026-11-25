<?php
/**
 * Forms › New form: the builder, on the Forms React screen (includes/flx/flux-ui.php, blocks/src/builder.js).
 *
 * @package FLX_Forms
 */

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Create {
	public $setting_name = 'Emsfb_create';

	public function __construct() {
		if ( false === get_option( $this->setting_name ) ) {
			update_option( $this->setting_name, array( 'rest_api_status' => 1 ) );
		}
		if ( false === get_option( 'emsfb_pro' ) ) {
			add_option( 'emsfb_pro', -1 );
		}
		add_action( 'admin_menu', array( $this, 'add_Create_menu' ), 11 );
	}

	public function add_Create_menu() {
		add_submenu_page( 'Emsfb', esc_html__( 'New form', 'flx-forms' ), esc_html__( 'New form', 'flx-forms' ), 'Emsfb_create', 'Emsfb_create', 'flxf_screen_page' );
	}
}

new Create();
