<?php
/**
 * Plugin Name:       FluxWP Panel
 * Description:       FluxWP'nin kurulum ve güncelleme eklentisi. İlk açılışta kurulum sihirbazı (tanıtım, eklenti seçimi, görünüm, kurulum); FluxWP tema ve eklentilerini güncelleme sunucusundan (varsayılan updater.fluxwp.com) WordPress'in güncelleme ekranına getirir; ön ayar motoru (preset.json) siteyi tek adımda kurar, onaylı yamalarla değiştirir, geri alır.
 * Version:           1.6.14
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-setup
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-setup
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXK_VERSION' ) ) {
	return;
}

define( 'FLXK_VERSION', '1.6.14' );
define( 'FLXK_FORMAT', 'flx-preset/1' );
define( 'FLXK_FILE', __FILE__ );
define( 'FLXK_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXK_URL', plugin_dir_url( __FILE__ ) );

/* ---------- Ön ayar motoru ---------- */
require_once FLXK_DIR . 'includes/helpers.php';
require_once FLXK_DIR . 'includes/profile.php';
require_once FLXK_DIR . 'includes/consent.php';
require_once FLXK_DIR . 'includes/registry.php';
require_once FLXK_DIR . 'includes/settings-schema.php';
require_once FLXK_DIR . 'includes/blocks.php';
require_once FLXK_DIR . 'includes/importers.php';
require_once FLXK_DIR . 'includes/engine.php';
require_once FLXK_DIR . 'includes/export.php';
require_once FLXK_DIR . 'includes/patches.php';
require_once FLXK_DIR . 'includes/mcp.php';
require_once FLXK_DIR . 'includes/telemetry.php';
require_once FLXK_DIR . 'includes/graphql.php';
require_once FLXK_DIR . 'includes/services/class-flxk-val.php';
require_once FLXK_DIR . 'includes/services/class-flxk-packages-service.php';
require_once FLXK_DIR . 'includes/services/class-flxk-settings-service.php';
require_once FLXK_DIR . 'includes/services/class-flxk-wizard-service.php';
require_once FLXK_DIR . 'includes/services/class-flxk-system-service.php';
require_once FLXK_DIR . 'includes/flux-ui.php';

/*
 * GraphQL (Flux GraphQL 0.2.0+): schema only inside a GraphQL request, persisted operations of the Packages,
 * Settings and wizard screens, and the logo upload context. Without Flux GraphQL the PHP Packages page stays
 * as the recovery path that installs it.
 */
add_action(
	'flx_gql_register',
	static function () {
		if ( ! function_exists( 'flx_gql_all_caps' ) ) {
			return;
		}
		require_once FLXK_DIR . 'includes/graphql/schema.php';
	}
);
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m             = is_array( $m ) ? $m : array();
		$m['flx-setup'] = FLXK_DIR . 'graphql/persisted-queries.json';
		return $m;
	}
);
add_filter(
	'flx_gql_upload_contexts',
	static function ( $c ) {
		$c                       = is_array( $c ) ? $c : array();
		$c['flx-setup/logo']     = array(
			'cap'    => static fn(): bool => current_user_can( 'install_plugins' ) && current_user_can( 'upload_files' ),
			'mimes'  => array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'gif'          => 'image/gif',
				'webp'         => 'image/webp',
			),
			'max_mb' => 5,
		);
		return $c;
	}
);

if ( is_admin() ) {
	require_once FLXK_DIR . 'includes/admin.php';
}
require_once FLXK_DIR . 'includes/google-cards.php'; // Also read by GraphQL (Packages screen cards).

/* ---------- Güncelleyici (eski Flux Updater; sınıf ve ayar öneki flxu_ korunur) ---------- */
require_once FLXK_DIR . 'includes/legacy.php';
require_once FLXK_DIR . 'includes/app.php';

if ( ! flxk_legacy_updater_active() ) {
	define( 'FLXU_VERSION', FLXK_VERSION );
	define( 'FLXU_FILE', FLXK_FILE );
	define( 'FLXU_DIR', FLXK_DIR );
	define( 'FLXU_DEFAULT_SERVER', 'https://updater.fluxwp.com' );

	require_once FLXK_DIR . 'includes/updater/class-flxu-sources.php';
	require_once FLXK_DIR . 'includes/updater/class-flxu-updater.php';
	require_once FLXK_DIR . 'includes/updater/class-flxu-download.php';
	require_once FLXK_DIR . 'includes/updater/class-flxu-signature.php';
	require_once FLXK_DIR . 'includes/updater/class-flxu-guard.php';
	require_once FLXK_DIR . 'includes/updater/class-flxu-previews.php';
	require_once FLXK_DIR . 'includes/updater/class-flxu-admin.php';
	require_once FLXK_DIR . 'includes/updater/class-flxu-wp.php';
	require_once FLXK_DIR . 'includes/updater/class-flxu-translations.php';
	require_once FLXK_DIR . 'includes/updater/class-flxu-icon-cache.php';
	require_once FLXK_DIR . 'includes/updater/class-flxu-installer.php';
	require_once FLXK_DIR . 'includes/updater/class-flxu-ajax.php';
	require_once FLXK_DIR . 'includes/cloud.php';
	require_once FLXK_DIR . 'includes/repair.php';

	FLXU_Updater::instance();
	FLXU_Download::init();
	FLXU_Guard::instance();
	FLXU_Translations::init();
	FLXU_Icon_Cache::init();
	FLXU_Ajax::init();
	FLXK_Cloud::init();

	if ( is_admin() ) {
		FLXU_Admin::instance();
		require_once FLXK_DIR . 'includes/onboarding.php';
	}
}

register_activation_hook( __FILE__, 'flxk_on_activate' );

add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-setup', false, dirname( plugin_basename( FLXK_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	static function ( $mofile, $domain ) {
		if ( 'flx-setup' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXK_DIR . 'languages/flx-setup-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);
