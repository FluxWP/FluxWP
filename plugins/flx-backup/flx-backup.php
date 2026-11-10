<?php
/**
 * Plugin Name:       Flux Backup
 * Description:       Her eklenti/tema güncellemesinden önce otomatik yedek alır, zamanlanmış tam yedekler, kota ve saklama süresi yönetimi, grafikli depolama paneli ve seçerek geri yükleme. Hostinger paylaşımlı barındırma ile uyumludur.
 * Version:           1.4.6
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-backup
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-backup
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).
require_once __DIR__ . '/includes/flx-background.php'; // Portable worker support (tools/background).

if ( defined( 'FLXY_VERSION' ) ) {
	return;
}

define( 'FLXY_VERSION', '1.4.6' );
define( 'FLXY_FILE', __FILE__ );
define( 'FLXY_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXY_URL', plugin_dir_url( __FILE__ ) );

require_once FLXY_DIR . 'includes/class-flxy-settings.php';
require_once FLXY_DIR . 'includes/class-flxy-storage.php';
require_once FLXY_DIR . 'includes/class-flxy-db.php';
require_once FLXY_DIR . 'includes/class-flxy-zipwriter.php';
require_once FLXY_DIR . 'includes/class-flxy-backup.php';
require_once FLXY_DIR . 'includes/class-flxy-breakdown.php';
require_once FLXY_DIR . 'includes/class-flxy-upload.php';
require_once FLXY_DIR . 'includes/class-flxy-restore.php';
require_once FLXY_DIR . 'includes/class-flxy-runner.php';
require_once FLXY_DIR . 'includes/class-flxy-indicator.php';
require_once FLXY_DIR . 'includes/class-flxy-pre-update.php';
require_once FLXY_DIR . 'includes/class-flxy-cron.php';
require_once FLXY_DIR . 'includes/class-flxy-checks.php';
require_once FLXY_DIR . 'includes/api.php';
require_once FLXY_DIR . 'includes/app.php';

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'flx-backup', false, dirname( plugin_basename( FLXY_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	static function ( $mofile, $domain ) {
		if ( 'flx-backup' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXY_DIR . 'languages/flx-backup-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

register_activation_hook( FLXY_FILE, array( 'FLXY_Cron', 'activate' ) );
register_deactivation_hook( FLXY_FILE, array( 'FLXY_Cron', 'deactivate' ) );

FLXY_Runner::init();
FLXY_Indicator::init();
FLXY_Upload::init();
FLXY_Pre_Update::init();
FLXY_Cron::init();
FLXY_Checks::init();

if ( is_admin() ) {
	require_once FLXY_DIR . 'includes/class-flxy-admin.php';
	FLXY_Admin::instance();
}
