<?php
/**
 * Plugin Name:       Flux App
 * Description:       Masaüstü uygulaması ve diğer istemciler için giriş: OAuth 2.0 (PKCE), kişisel API anahtarları, rol bazlı menü ve tek tıkla panel oturumu. MCP istemcileri de OAuth ile bağlanır.
 * Version:           0.3.9
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-app
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-app
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXAPP_VERSION' ) ) {
	return;
}

define( 'FLXAPP_VERSION', '0.3.9' );
define( 'FLXAPP_DB_VERSION', 1 );
define( 'FLXAPP_FILE', __FILE__ );
define( 'FLXAPP_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXAPP_URL', plugin_dir_url( __FILE__ ) );

require_once FLXAPP_DIR . 'includes/settings.php';
require_once FLXAPP_DIR . 'includes/tokens.php';
require_once FLXAPP_DIR . 'includes/clients.php';
require_once FLXAPP_DIR . 'includes/oauth.php';
require_once FLXAPP_DIR . 'includes/otp.php';
require_once FLXAPP_DIR . 'includes/menu.php';
require_once FLXAPP_DIR . 'includes/embed.php';
require_once FLXAPP_DIR . 'includes/rest.php';
require_once FLXAPP_DIR . 'includes/dashboard.php';
require_once FLXAPP_DIR . 'includes/system-checks.php';
if ( is_admin() ) {
	require_once FLXAPP_DIR . 'includes/admin.php';
}

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-app', false, dirname( plugin_basename( FLXAPP_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-app' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXAPP_DIR . 'languages/flx-app-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

register_activation_hook( FLXAPP_FILE, 'flxapp_install' );
add_action( 'plugins_loaded', 'flxapp_maybe_upgrade', 5 );

function flxapp_table() {
	global $wpdb;
	return $wpdb->prefix . 'flxapp_tokens';
}

function flxapp_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t  = flxapp_table();
	$cs = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$t} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		kind varchar(10) NOT NULL DEFAULT 'access',
		hash char(64) NOT NULL,
		hint varchar(12) NOT NULL DEFAULT '',
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		client_id varchar(100) NOT NULL DEFAULT '',
		grant_id varchar(32) NOT NULL DEFAULT '',
		name varchar(190) NOT NULL DEFAULT '',
		scope varchar(190) NOT NULL DEFAULT '',
		created int(10) unsigned NOT NULL DEFAULT 0,
		expires int(10) unsigned NOT NULL DEFAULT 0,
		last_used int(10) unsigned NOT NULL DEFAULT 0,
		last_ip varchar(45) NOT NULL DEFAULT '',
		revoked tinyint(1) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		UNIQUE KEY hash (hash),
		KEY user_kind (user_id, kind),
		KEY grant_id (grant_id)
		) {$cs};"
	);
	update_option( 'flxapp_db_version', FLXAPP_DB_VERSION, false );
}

function flxapp_maybe_upgrade() {
	if ( (int) get_option( 'flxapp_db_version', 0 ) < FLXAPP_DB_VERSION ) {
		flxapp_install();
	}
}
