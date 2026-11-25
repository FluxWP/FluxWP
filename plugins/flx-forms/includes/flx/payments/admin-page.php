<?php
/**
 * Flux Forms: "Payments" admin page (Forms › Payments), provider-neutral.
 *
 * Replaces the Stripe add-on's plain "Stripe Payments" page; the old slug
 * (admin.php?page=Emsfb_stripe) redirects here. 2.8.0: the page is the Forms React
 * screen (flux-ui.php), data over flxFormsPayments (services/class-flxf-tools-service.php).
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 *
 * @package FLX_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXF_Payments_Admin {

	const SLUG     = 'flxf_payments';
	const OLD_SLUG = 'Emsfb_stripe';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 31 );
	}

	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function menu() {
		/* Old links (and the webhook form's redirect) land on the new page. */
		if ( isset( $_GET['page'] ) && self::OLD_SLUG === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			$args = array();
			if ( isset( $_GET['efb_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$args['efb_msg'] = sanitize_key( wp_unslash( $_GET['efb_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}
			wp_safe_redirect( self::url( $args ) );
			exit;
		}
		remove_submenu_page( 'Emsfb', self::OLD_SLUG );
		add_submenu_page(
			'Emsfb',
			__( 'Payments', 'flx-forms' ),
			__( 'Payments', 'flx-forms' ),
			'manage_options',
			self::SLUG,
			'flxf_screen_page'
		);
	}
}

FLXF_Payments_Admin::init();
