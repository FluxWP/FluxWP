<?php
/**
 * Plugin Name: Flux Mail
 * Description: Sitenin tüm e-postaları tek yerde: gönderen adı/adresi (bilgi@ / no-reply@), SMTP, logolu ve mobil uyumlu kurumsal şablonlar, WordPress + WooCommerce + FluxWP eklentilerinin e-postalarını görme, düzenleme, açma/kapama, gönderim kayıtları, tekil ve toplu gönderim. WhatsApp (Evolution API) ve SMS (Netgsm) bağlantıları da burada; diğer eklentiler ortak API ile kullanır.
 * Version: 1.24.0
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * Requires Plugins: flx-graphql
 * Author: FluxWP
 * Text Domain: flx-mail
 * Domain Path: /languages
 * License: GPL v2 or later
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).
require_once __DIR__ . '/includes/flx-background.php'; // Portable worker support (tools/background).

define( 'FLXI_VERSION', '1.24.0' );
define( 'FLXI_DB_VERSION', '5' );
define( 'FLXI_FILE', __FILE__ );
define( 'FLXI_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXI_URL', plugin_dir_url( __FILE__ ) );

require_once FLXI_DIR . 'includes/flx-system-checks.php';
require_once FLXI_DIR . 'includes/helpers.php';
require_once FLXI_DIR . 'includes/services/class-flxi-settings-service.php';
require_once FLXI_DIR . 'includes/services/class-flxi-template-service.php';
require_once FLXI_DIR . 'includes/services/class-flxi-log-service.php';
require_once FLXI_DIR . 'includes/services/class-flxi-delivery-service.php';
require_once FLXI_DIR . 'includes/services/class-flxi-system-service.php';
require_once FLXI_DIR . 'includes/install.php';
require_once FLXI_DIR . 'includes/layout.php';
require_once FLXI_DIR . 'includes/templates.php';
require_once FLXI_DIR . 'includes/mailer.php';
require_once FLXI_DIR . 'includes/core-emails.php';
require_once FLXI_DIR . 'includes/woocommerce.php';
require_once FLXI_DIR . 'includes/social-login.php';
require_once FLXI_DIR . 'includes/connections.php';
require_once FLXI_DIR . 'includes/providers/evolution.php';
require_once FLXI_DIR . 'includes/providers/netgsm.php';
require_once FLXI_DIR . 'includes/providers/wacloud.php';
require_once FLXI_DIR . 'includes/whatsapp.php';
require_once FLXI_DIR . 'includes/test-send.php';
require_once FLXI_DIR . 'includes/limits.php';
require_once FLXI_DIR . 'includes/images.php';
require_once FLXI_DIR . 'includes/blocklist.php';
require_once FLXI_DIR . 'includes/optout.php';
require_once FLXI_DIR . 'includes/wa-guard.php';
require_once FLXI_DIR . 'includes/campaigns.php';
require_once FLXI_DIR . 'includes/campaign-control.php';
require_once FLXI_DIR . 'includes/campaign-engagement.php';
require_once FLXI_DIR . 'includes/campaign-origin.php';
require_once FLXI_DIR . 'includes/netgsm-jobs.php';
require_once FLXI_DIR . 'includes/queue-status.php';
require_once FLXI_DIR . 'includes/mailbox/imap.php';
require_once FLXI_DIR . 'includes/mailbox/mime.php';
require_once FLXI_DIR . 'includes/mailbox/store.php';
require_once FLXI_DIR . 'includes/mailbox/sync.php';
require_once FLXI_DIR . 'includes/mailbox/layouts.php';
require_once FLXI_DIR . 'includes/mailbox/compose.php';
require_once FLXI_DIR . 'includes/mailbox/ai.php';
require_once FLXI_DIR . 'includes/mailbox/rest.php';
require_once FLXI_DIR . 'includes/sertifika.php';
require_once FLXI_DIR . 'includes/api.php';
require_once FLXI_DIR . 'includes/pdf.php';
require_once FLXI_DIR . 'includes/rest.php';
require_once FLXI_DIR . 'includes/app-manifest.php';
require_once FLXI_DIR . 'includes/system-checks.php';
require_once FLXI_DIR . 'includes/mcp.php';

if ( is_admin() ) {
	require_once FLXI_DIR . 'includes/admin/admin.php';
}

register_activation_hook( __FILE__, 'flxi_activate' );
register_deactivation_hook( __FILE__, 'flxi_deactivate' );

add_action(
	'init',
	function () {
		// Kaynak dil Türkçe; diğer diller languages/flx-mail-{locale}.l10n.php (WP 6.5+) ya da .mo.
		load_plugin_textdomain( 'flx-mail', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	static function ( $mofile, $domain ) {
		if ( 'flx-mail' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXI_DIR . 'languages/flx-mail-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

add_action( 'plugins_loaded', 'flxi_maybe_upgrade', 5 );

add_action(
	'flx_gql_register',
	static function () {
		if ( ! function_exists( 'flx_gql_query' ) ) {
			return;
		}
		require_once FLXI_DIR . 'includes/graphql/schema.php';
		flxi_gql_register();
	}
);
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m             = is_array( $m ) ? $m : array();
		$m['flx-mail'] = FLXI_DIR . 'graphql/persisted-queries.json';
		return $m;
	}
);
add_filter(
	'flx_gql_capabilities',
	static function ( $caps ) {
		$caps = is_array( $caps ) ? $caps : array();
		return $caps + array(
			'flxi_manage' => array(
				'label'   => __( 'Manages communication', 'flx-mail' ),
				'group'   => __( 'Communication', 'flx-mail' ),
				'package' => 'flx-mail',
			),
		);
	}
);
add_filter(
	'flx_gql_upload_contexts',
	static function ( $contexts ) {
		$contexts                  = is_array( $contexts ) ? $contexts : array();
		$contexts['flx-mail/logo'] = array(
			'cap'    => static fn(): bool => (bool) flxi_can_manage(),
			'mimes'  => array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
			),
			'max_mb' => 5,
		);
		return $contexts;
	}
);
