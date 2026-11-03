<?php
/**
 * Plugin Name:       Flux Studio
 * Description:       Yapay zekâ ile görsel ve video stüdyosu: üretim, referanslar ve kişiler, alan düzenleme, Geliştir ve Beyin fırtınası, klasörlü galeri. Üretim Flux AI üzerinden (fal.ai, EvoLink, OpenAI, Gemini, OpenRouter).
 * Version:           0.3.1
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-studio
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-studio
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'FLXST_VERSION' ) ) {
	return;
}

define( 'FLXST_VERSION', '0.3.1' );
define( 'FLXST_DB_VERSION', 1 );
define( 'FLXST_FILE', __FILE__ );
define( 'FLXST_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXST_URL', plugin_dir_url( __FILE__ ) );
define( 'FLXST_NS', 'flx-studio/v1' );

require_once FLXST_DIR . 'includes/install.php';
require_once FLXST_DIR . 'includes/store.php';
require_once FLXST_DIR . 'includes/media.php';
require_once FLXST_DIR . 'includes/ai.php';
require_once FLXST_DIR . 'includes/rest.php';
require_once FLXST_DIR . 'includes/stats.php';
require_once FLXST_DIR . 'includes/checks.php';
require_once FLXST_DIR . 'includes/integrations.php';
if ( is_admin() ) {
	require_once FLXST_DIR . 'includes/admin.php';
}

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-studio', false, dirname( plugin_basename( FLXST_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-studio' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXST_DIR . 'languages/flx-studio-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

register_activation_hook( FLXST_FILE, 'flxst_install' );
add_action( 'plugins_loaded', 'flxst_maybe_upgrade', 5 );
