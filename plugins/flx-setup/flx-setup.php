<?php
/**
 * Plugin Name:       Flux Setup
 * Description:       Ön ayar motoru: bir ön ayar şablonuyla (preset.json) siteyi tek adımda kurar ya da onaylı yamalarla değiştirir. Kurum profili, eklenti ayarları, global stil, şablonlar, header/footer, sayfalar, menüler ve medya. Önizle, uygula, geri al, migration'lar, bu siteden şablon çıkar; Flux AI sohbetinden ve Flux MCP'den onaylı düzenleme.
 * Version:           0.2.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-setup
 * Domain Path:       /languages
 * Update URI:        https://update.yesilturkiyeist.org/flx-setup
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'FLXK_VERSION' ) ) {
	return;
}

define( 'FLXK_VERSION', '0.2.0' );
define( 'FLXK_FORMAT', 'flx-preset/1' );
define( 'FLXK_FILE', __FILE__ );
define( 'FLXK_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXK_URL', plugin_dir_url( __FILE__ ) );

require_once FLXK_DIR . 'includes/helpers.php';
require_once FLXK_DIR . 'includes/profile.php';
require_once FLXK_DIR . 'includes/registry.php';
require_once FLXK_DIR . 'includes/blocks.php';
require_once FLXK_DIR . 'includes/importers.php';
require_once FLXK_DIR . 'includes/engine.php';
require_once FLXK_DIR . 'includes/export.php';
require_once FLXK_DIR . 'includes/patches.php';
require_once FLXK_DIR . 'includes/mcp.php';

if ( is_admin() ) {
	require_once FLXK_DIR . 'includes/admin.php';
}

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-setup', false, dirname( plugin_basename( FLXK_FILE ) ) . '/languages' );
	}
);
