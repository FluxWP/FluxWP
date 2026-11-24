<?php
/**
 * Flux Donate: Donations › Add donation (the manager records a gift received outside the payment provider) and
 * the staff donation request. Both are the React screen (packages/modules/src/donate/Add.tsx) on
 * FLXB_Admin_Add_Service.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Add {

	public static function render() {
		if ( ! FLXB_Refs::can_create() ) {
			FLXB_Admin::deny();
		}
		FLXB_Admin::screen(); // 2.42.0: the React screen.
	}
}
