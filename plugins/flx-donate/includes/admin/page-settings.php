<?php
/**
 * Flux Donate: settings tabs (other modules add tabs: flxb_settings_tabs + flxb_settings_tab_<id>).
 * 2.44.0: the screen is React (FLXB_Admin_Settings_Service); the added tabs are printed as islands.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Page_Settings {

	public static function tabs() {
		$tabs = array(
			'general'      => __( 'General', 'flx-donate' ),
			'organisation' => __( 'Organisation', 'flx-donate' ),
			'categories'   => __( 'Categories', 'flx-donate' ),
		);
		if ( flxb_track_enabled() ) {
			$tabs['kurban'] = __( 'Tracking', 'flx-donate' );
		}
		$tabs['tabs']   = __( 'Extra tabs', 'flx-donate' );
		$tabs['import'] = __( 'Import', 'flx-donate' );
		if ( ! class_exists( 'FLXB_System_Checks' ) || ! FLXB_System_Checks::external() ) {
			$tabs['system'] = __( 'System check', 'flx-donate' ); // 2.9.0: with Flux Speed the screen lives there
		}
		return apply_filters( 'flxb_settings_tabs', $tabs );
	}

	/** 2.44.0: the page is the React screen (packages/modules/src/donate/Settings.tsx). */
	public static function render() {
		if ( ! flxb_can_manage() ) {
			FLXB_Admin::deny();
		}
		FLXB_Admin::screen();
	}
}
