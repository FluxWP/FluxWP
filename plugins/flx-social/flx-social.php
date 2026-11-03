<?php
/**
 * Plugin Name:       Flux Social
 * Description:       Sosyal medya yönetimi sitenin içinde: hesap bağlama (anahtarlar panelden), hesap başına içerik, zamanlama, ağda düzenleme/silme, takvim, pano, Flux AI yazım yardımı ve MCP araçları. Facebook, Instagram, YouTube, X, LinkedIn, TikTok, Pinterest, Google İşletme, WhatsApp.
 * Version:           0.1.2
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-social
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-social
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'FLXSM_VERSION' ) ) {
	return;
}

define( 'FLXSM_VERSION', '0.1.2' );
define( 'FLXSM_DB_VERSION', 1 );
define( 'FLXSM_FILE', __FILE__ );
define( 'FLXSM_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXSM_URL', plugin_dir_url( __FILE__ ) );
define( 'FLXSM_NS', 'flx-social/v1' );

require_once FLXSM_DIR . 'includes/install.php';
require_once FLXSM_DIR . 'includes/util.php';
require_once FLXSM_DIR . 'includes/store.php';
require_once FLXSM_DIR . 'includes/providers/class-provider.php';
require_once FLXSM_DIR . 'includes/providers/meta.php';
require_once FLXSM_DIR . 'includes/providers/google.php';
require_once FLXSM_DIR . 'includes/providers/x.php';
require_once FLXSM_DIR . 'includes/providers/linkedin.php';
require_once FLXSM_DIR . 'includes/providers/tiktok.php';
require_once FLXSM_DIR . 'includes/providers/pinterest.php';
require_once FLXSM_DIR . 'includes/providers/whatsapp.php';
require_once FLXSM_DIR . 'includes/providers.php';
require_once FLXSM_DIR . 'includes/oauth.php';
require_once FLXSM_DIR . 'includes/engine.php';
require_once FLXSM_DIR . 'includes/stats.php';
require_once FLXSM_DIR . 'includes/ai.php';
require_once FLXSM_DIR . 'includes/rest.php';
require_once FLXSM_DIR . 'includes/mcp.php';
require_once FLXSM_DIR . 'includes/autopost.php';
require_once FLXSM_DIR . 'includes/checks.php';
require_once FLXSM_DIR . 'includes/integrations.php';
if ( is_admin() ) {
	require_once FLXSM_DIR . 'includes/admin.php';
}

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-social', false, dirname( plugin_basename( FLXSM_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-social' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXSM_DIR . 'languages/flx-social-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

register_activation_hook( FLXSM_FILE, 'flxsm_install' );
register_deactivation_hook( FLXSM_FILE, 'flxsm_deactivate' );
add_action( 'plugins_loaded', 'flxsm_maybe_upgrade', 5 );
