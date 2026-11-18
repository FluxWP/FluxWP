<?php
/**
 * Plugin Name:       Flux Tasks
 * Description:       ClickUp benzeri görev takibi: projeler, klasörler, listeler; liste, pano (kanban) ve takvim görünümleri, kayıtlı filtreli görünümler, özel alanlar, yorum ve dosya ekleri, bildirimler, liste yetkileri, yönetici panosu ve Flux AI & MCP üzerinden yapay zekâ.
 * Version:           0.18.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  flx-graphql
 * Author:            FluxWP
 * Text Domain:       flx-tasks
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

define( 'FLXG_VERSION', '0.18.1' );
define( 'FLXG_DB_VERSION', '6' );
define( 'FLXG_FILE', __FILE__ );
define( 'FLXG_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXG_URL', plugin_dir_url( __FILE__ ) );

require_once FLXG_DIR . 'includes/helpers.php';
require_once FLXG_DIR . 'includes/install.php';
require_once FLXG_DIR . 'includes/perms.php';
require_once FLXG_DIR . 'includes/store.php';
require_once FLXG_DIR . 'includes/services/class-flxg-task.php';
require_once FLXG_DIR . 'includes/services/class-flxg-task-service.php';
require_once FLXG_DIR . 'includes/services/class-flxg-doc-service.php';
require_once FLXG_DIR . 'includes/files.php';
require_once FLXG_DIR . 'includes/docs.php';
require_once FLXG_DIR . 'includes/doc-comments.php';
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
 * GraphQL (Flux GraphQL): schema only inside a GraphQL request; the persisted queries the screens send.
 * Without Flux GraphQL the action never fires and the REST API keeps working.
 */
add_action(
	'flx_gql_register',
	static function () {
		require_once FLXG_DIR . 'includes/graphql/schema.php';
	}
);
add_action(
	'flx_gql_loaders',
	static function () {
		flx_gql_node_loader( 'FlxTask', 'FLXG_Task_Loader' );
	}
);
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m              = is_array( $m ) ? $m : array();
		$m['flx-tasks'] = FLXG_DIR . 'graphql/persisted-queries.json';
		return $m;
	}
);

/*
 * Çeviri: kaynak metinler Türkçe; site/kullanıcı dili başka olduğunda languages/ içindeki çeviri kullanılır.
 * en_GB, en_AU gibi diğer İngilizce yerel ayarları en_US çevirisine düşer.
 */
/*
 * REST and GraphQL requests (the React screen) are not "admin" requests, so WordPress picks the SITE language for
 * them even when the person chose another one in their profile. Strings built in PHP (status names, messages, mail
 * previews) then stayed in the site language while the page itself was in the profile language.
 */
add_filter(
	'plugin_locale',
	static function ( $locale, $domain ) {
		if ( 'flx-tasks' !== $domain || is_admin() || ! is_user_logged_in() ) {
			return $locale;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( wp_is_json_request() || isset( $_GET['rest_route'] ) || false !== strpos( $uri, '/wp-json/' ) || false !== strpos( $uri, '/graphql' ) ) {
			return get_user_locale();
		}
		return $locale;
	},
	10,
	2
);
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
