<?php
/**
 * Plugin Name:       Flux Tasks
 * Description:       ClickUp benzeri görev takibi: projeler, klasörler, listeler; liste, pano (kanban) ve takvim görünümleri, kayıtlı filtreli görünümler, özel alanlar, yorum ve dosya ekleri, bildirimler, liste yetkileri, yönetici panosu, MCP sunucusu ve OpenRouter ile yapay zekâ.
 * Version:           0.8.2
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            FluxWP
 * Text Domain:       flx-tasks
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'FLXG_VERSION', '0.8.2' );
define( 'FLXG_DB_VERSION', '3' );
define( 'FLXG_FILE', __FILE__ );
define( 'FLXG_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXG_URL', plugin_dir_url( __FILE__ ) );

require_once FLXG_DIR . 'includes/helpers.php';
require_once FLXG_DIR . 'includes/install.php';
require_once FLXG_DIR . 'includes/perms.php';
require_once FLXG_DIR . 'includes/store.php';
require_once FLXG_DIR . 'includes/files.php';
require_once FLXG_DIR . 'includes/docs.php';
require_once FLXG_DIR . 'includes/notify.php';
require_once FLXG_DIR . 'includes/tools.php';
require_once FLXG_DIR . 'includes/doc-ai.php';
require_once FLXG_DIR . 'includes/ai.php';
require_once FLXG_DIR . 'includes/mcp.php';
require_once FLXG_DIR . 'includes/dashboard.php';
require_once FLXG_DIR . 'includes/checks.php';
require_once FLXG_DIR . 'includes/rest.php';
require_once FLXG_DIR . 'includes/app.php';

if ( is_admin() ) {
	require_once FLXG_DIR . 'includes/admin.php';
}

/*
 * Çeviri: kaynak metinler Türkçe; site/kullanıcı dili başka olduğunda languages/ içindeki çeviri kullanılır.
 * en_GB, en_AU gibi diğer İngilizce yerel ayarları en_US çevirisine düşer.
 */
add_action( 'init', 'flxg_load_textdomain', 1 );
function flxg_load_textdomain() {
	load_plugin_textdomain( 'flx-tasks', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-tasks' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXG_DIR . 'languages/flx-tasks-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

register_activation_hook( __FILE__, 'flxg_activate' );
register_deactivation_hook( __FILE__, 'flxg_deactivate' );
add_action( 'plugins_loaded', 'flxg_maybe_upgrade' );
