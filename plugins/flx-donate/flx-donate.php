<?php
/**
 * Plugin Name:       Flux Donate
 * Description:       Donations on WooCommerce: donation types and sapling packages sold as hidden products, cart and checkout (iyzico, bank transfer with receipt upload), monthly donations, online and printed certificates, donors, campaigns and a donation dashboard. Includes Flux WooCommerce Bağış.
 * Version:           2.15.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 * WC tested up to:   11.2
 * Author:            Flux Donate contributors
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flx-donate
 * Domain Path:       /languages
 *
 * Flux Donate is free software: you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation, either version 2 of
 * the License, or (at your option) any later version. It is distributed WITHOUT ANY WARRANTY;
 * see the GNU General Public License for more details.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

define( 'FLXB_VERSION', '2.15.0' );
define( 'FLXB_FILE', __FILE__ );
define( 'FLXB_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXB_URL', plugin_dir_url( __FILE__ ) );

require_once FLXB_DIR . 'includes/helpers.php';
require_once FLXB_DIR . 'includes/class-flxb-install.php';
require_once FLXB_DIR . 'includes/class-flxb-donors.php';
require_once FLXB_DIR . 'includes/class-flxb-types.php';
require_once FLXB_DIR . 'includes/class-flxb-subscriptions.php';
require_once FLXB_DIR . 'includes/class-flxb-donations.php';
require_once FLXB_DIR . 'includes/class-flxb-refs.php';
FLXB_Refs::init();
require_once FLXB_DIR . 'includes/class-flxb-flxform-bridge.php';
require_once FLXB_DIR . 'includes/class-flxb-stats.php';
require_once FLXB_DIR . 'includes/class-flxb-shortcode.php';
require_once FLXB_DIR . 'includes/class-flxb-items.php';
require_once FLXB_DIR . 'includes/class-flxb-partners.php';
require_once FLXB_DIR . 'includes/class-flxb-track.php';
require_once FLXB_DIR . 'includes/class-flxb-reports.php';
require_once FLXB_DIR . 'includes/class-flxb-detail-tabs.php';
require_once FLXB_DIR . 'includes/class-flxb-import.php';
require_once FLXB_DIR . 'includes/class-flxb-preset.php';
FLXB_Track::init();
FLXB_Preset::init();
require_once FLXB_DIR . 'includes/class-flxb-form-sync.php';
require_once FLXB_DIR . 'includes/class-flxb-preselect.php';
require_once FLXB_DIR . 'includes/class-flxb-rest.php';
require_once FLXB_DIR . 'includes/class-flxb-app.php';
require_once FLXB_DIR . 'includes/class-flxb-app-review.php';
require_once FLXB_DIR . 'includes/class-flxb-app-admin.php';
require_once FLXB_DIR . 'includes/app-manifest.php';
require_once FLXB_DIR . 'includes/api.php';
/* E-mails: templates registered with Flux Mail (flxi_email_types), plain wp_mail without it. */
require_once FLXB_DIR . 'includes/class-flxb-mail.php';
FLXB_Mail::init();
/* 2.0: WooCommerce layer (cart, checkout, gateways, ledger, certificates). Boots on plugins_loaded:15. */
require_once FLXB_DIR . 'includes/wc/class-flxb-wc.php';
FLXB_WC::init();
/* iyzico renewals run from cron whether or not the payment layer is loaded in that request. */
require_once FLXB_DIR . 'providers/class-flxb-iyzico-api.php';
require_once FLXB_DIR . 'providers/class-flxb-iyzico-recurring.php';
FLXB_Iyzico_Recurring::init();
require_once FLXB_DIR . 'providers/class-flxb-iyzico-notify.php';
FLXB_Iyzico_Notify::init();
/* System check (Donations › Settings › System check); the shared screen is in flx-system-checks.php. */
require_once FLXB_DIR . 'includes/flx-system-checks.php';
require_once FLXB_DIR . 'includes/class-flxb-system-checks.php';
add_action( 'plugins_loaded', array( 'FLXB_System_Checks', 'init' ), 20 );
/* Safe fixes for Flux SEO's system_fix tool (filter flx_system_fixes). */
require_once FLXB_DIR . 'includes/class-flxb-system-fixes.php';
FLXB_System_Fixes::init();

register_activation_hook( __FILE__, array( 'FLXB_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'FLXB_Install', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'FLXB_Install', 'maybe_upgrade' ), 5 );
add_action( 'plugins_loaded', array( 'FLXB_Install', 'purge_on_update' ), 6 );
add_action( 'init', 'flxb_load_textdomain', 0 );

function flxb_load_textdomain() {
	load_plugin_textdomain( 'flx-donate', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_filter(
	'load_textdomain_mofile',
	static function ( $mofile, $domain ) {
		if ( 'flx-donate' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXB_DIR . 'languages/flx-donate-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

/*
 * Payment providers. Flux Donate owns Flux Forms's payment layer when both are active: Flux Forms asks
 * `flxf_payments_delegate` (on plugins_loaded, priority 1) and then skips its built-in layer.
 * The layer is loaded only when Flux Forms is present and has not loaded its own (older Flux Forms).
 */
function flxb_payments_boot() {
	if ( defined( 'FLXB_PAYMENTS_OWNER' ) || ! defined( 'EMSFB_PLUGIN_DIRECTORY' ) || function_exists( 'flxf_payment_providers' ) || ! apply_filters( 'flxb_own_payments', true ) ) {
		return;
	}
	define( 'FLXB_PAYMENTS_OWNER', true );
	require_once FLXB_DIR . 'providers/payments.php';
}
add_action( 'plugins_loaded', 'flxb_payments_boot', 0 );
add_filter(
	'flxf_payments_delegate',
	function () {
		return defined( 'FLXB_PAYMENTS_OWNER' );
	}
);

/** Is Flux Donate taking payments (provider layer loaded)? */
function flxb_owns_payments() {
	return defined( 'FLXB_PAYMENTS_OWNER' );
}

FLXB_FLXForm_Bridge::init();
FLXB_Shortcode::init();
FLXB_Form_Sync::init();
FLXB_Preselect::init();
FLXB_Rest::init();
FLXB_App::init();
FLXB_App_Review::init();
FLXB_App_Admin::init();

if ( is_admin() ) {
	require_once FLXB_DIR . 'includes/admin/class-flxb-admin.php';
	FLXB_Admin::init();
}
