<?php
/**
 * Flux Donate: Dashboard. The page is the React screen (packages/modules/src/donate/Dashboard.tsx) on the
 * Flux GraphQL shell; its data is FlxDonateDashboard (includes/graphql/admin.php).
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Dashboard {

	public static function render() {
		if ( ! FLXB_Refs::can_dashboard() ) {
			if ( FLXB_Refs::own_only() ) {
				FLXB_Page_Donations::render(); // the menu's top item opens their referrals
				return;
			}
			FLXB_Admin::deny();
		}
		FLXB_Admin::screen();
	}
}
