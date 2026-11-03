<?php
/**
 * Plugin Name:       Flux Updater
 * Description:       FluxWP tema ve eklentilerini güncelleme sunucusundan (varsayılan updater.fluxwp.com) WordPress'in normal güncelleme ekranına getirir. Public paketler anahtarsız, private paketler site anahtarıyla.
 * Version:           1.6.2
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-updater
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-updater
 */

defined( 'ABSPATH' ) || exit;

define( 'FLXU_VERSION', '1.6.2' );
define( 'FLXU_FILE', __FILE__ );
define( 'FLXU_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXU_DEFAULT_SERVER', 'https://updater.fluxwp.com' );

require_once FLXU_DIR . 'includes/class-flxu-sources.php';
require_once FLXU_DIR . 'includes/class-flxu-updater.php';
require_once FLXU_DIR . 'includes/class-flxu-guard.php';
require_once FLXU_DIR . 'includes/class-flxu-admin.php';
require_once FLXU_DIR . 'includes/class-flxu-installer.php';
require_once FLXU_DIR . 'includes/class-flxu-ajax.php';

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'flx-updater', false, dirname( plugin_basename( FLXU_FILE ) ) . '/languages' );
	}
);

FLXU_Updater::instance();
FLXU_Guard::instance();
FLXU_Ajax::init();

if ( is_admin() ) {
	FLXU_Admin::instance();
}
