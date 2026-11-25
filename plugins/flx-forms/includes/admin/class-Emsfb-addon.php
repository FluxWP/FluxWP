<?php
namespace Emsfb;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Forms › Add-ons menu entry. 2.8.0: the page is the Forms React screen (includes/flx/flux-ui.php); the add-on
 * list goes over flxFormsAddons (services/class-flxf-tools-service.php); the bundled add-ons are always on.
 */
class Addon {
	public $setting_name;
	public function __construct() {
		$this->setting_name = 'Emsfb_addon';
		if ( ! get_option( $this->setting_name ) ) {
			update_option( $this->setting_name, array( 'rest_api_status' => 1 ) );
		}
		add_action( 'admin_menu', array( $this, 'add_addon_menu' ), 11 );
	}
	public function add_addon_menu() {
		add_submenu_page( 'Emsfb', esc_html__( 'Add-ons', 'flx-forms' ), esc_html__( 'Add-ons', 'flx-forms' ), 'Emsfb_addon', 'Emsfb_addon', 'flxf_screen_page' );
	}
}
new Addon();
