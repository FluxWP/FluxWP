<?php
/**
 * Plugin Name:       Flux AI & MCP
 * Description:       Tüm FluxWP eklentileri için ortak yapay zekâ merkezi: OpenRouter ve Anthropic anahtarları, model kataloğu ve fiyatlar, prompt önbelleği, araç kaydı, ortak MCP sunucusu, kullanım raporları, faturalandırma ve harcama sınırı; yetkili kullanıcılar için admin ve sitede ortak AI sohbeti.
 * Version:           0.24.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  flx-graphql
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-ai
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-ai
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).
require_once __DIR__ . '/includes/flx-ai-sdk/loader.php'; // Flux AI SDK (tools/ai-sdk).
if ( defined( 'FLXAI_VERSION' ) ) {
	return;
}

define( 'FLXAI_VERSION', '0.24.0' );
define( 'FLXAI_DB_VERSION', '7' );
define( 'FLXAI_CAP_MCP', 'flxai_mcp' );
define( 'FLXAI_FILE', __FILE__ );
define( 'FLXAI_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXAI_URL', plugin_dir_url( __FILE__ ) );

require_once FLXAI_DIR . 'includes/helpers.php';
require_once FLXAI_DIR . 'includes/install.php';
require_once FLXAI_DIR . 'includes/models.php';
require_once FLXAI_DIR . 'includes/providers.php';
require_once FLXAI_DIR . 'includes/budget.php';
require_once FLXAI_DIR . 'includes/api.php';
require_once FLXAI_DIR . 'includes/systems.php';
require_once FLXAI_DIR . 'includes/registry.php';
require_once FLXAI_DIR . 'includes/mcp.php';
require_once FLXAI_DIR . 'includes/oauth.php';
require_once FLXAI_DIR . 'includes/server-card.php';
require_once FLXAI_DIR . 'includes/tools.php';
require_once FLXAI_DIR . 'includes/web.php';
require_once FLXAI_DIR . 'includes/shell.php';
require_once FLXAI_DIR . 'includes/mcp-client.php';
require_once FLXAI_DIR . 'includes/chats.php';
require_once FLXAI_DIR . 'includes/attachments.php';
require_once FLXAI_DIR . 'includes/widgets.php';
require_once FLXAI_DIR . 'includes/media.php';
require_once FLXAI_DIR . 'includes/preview.php';
require_once FLXAI_DIR . 'includes/design.php';
require_once FLXAI_DIR . 'includes/image-tools.php';
require_once FLXAI_DIR . 'includes/agent.php';
require_once FLXAI_DIR . 'includes/tasks.php';
require_once FLXAI_DIR . 'includes/workspace.php';
require_once FLXAI_DIR . 'includes/skills.php';
require_once FLXAI_DIR . 'includes/notify.php';
require_once FLXAI_DIR . 'includes/visual-report.php';
require_once FLXAI_DIR . 'includes/approvals.php';
require_once FLXAI_DIR . 'includes/reports.php';
require_once FLXAI_DIR . 'includes/rest.php';
require_once FLXAI_DIR . 'includes/app.php';
require_once FLXAI_DIR . 'includes/app-modules.php';
require_once FLXAI_DIR . 'includes/dock.php';
require_once FLXAI_DIR . 'includes/checks.php';
require_once FLXAI_DIR . 'includes/js-strings.php';
require_once FLXAI_DIR . 'includes/services/class-flxai-cast.php';
require_once FLXAI_DIR . 'includes/services/class-flxai-usage-dto.php';
require_once FLXAI_DIR . 'includes/services/class-flxai-usage-service.php';
require_once FLXAI_DIR . 'includes/services/class-flxai-settings-dto.php';
require_once FLXAI_DIR . 'includes/services/class-flxai-settings-service.php';
require_once FLXAI_DIR . 'includes/services/class-flxai-tools-dto.php';
require_once FLXAI_DIR . 'includes/services/class-flxai-tools-service.php';
require_once FLXAI_DIR . 'includes/services/class-flxai-approvals-dto.php';
require_once FLXAI_DIR . 'includes/services/class-flxai-approvals-service.php';

if ( is_admin() ) {
	require_once FLXAI_DIR . 'includes/admin-flux.php';
	require_once FLXAI_DIR . 'includes/admin.php';
	require_once FLXAI_DIR . 'includes/admin-mcp.php';
	require_once FLXAI_DIR . 'includes/admin-connections.php';
	require_once FLXAI_DIR . 'includes/admin-tools.php';
	require_once FLXAI_DIR . 'includes/admin-systems.php';
	require_once FLXAI_DIR . 'includes/admin-tasks.php';
	require_once FLXAI_DIR . 'includes/admin-skills.php';
	require_once FLXAI_DIR . 'includes/admin-approvals.php';
}

/*
 * GraphQL (Flux GraphQL): the schema is built only inside a GraphQL request; the persisted operations the
 * Flux AI screens send. Without Flux GraphQL (0.2.0+, capability model) the schema is left out and the
 * classic pages and REST routes keep working. MCP, OAuth and the chat stream stay on their own protocols.
 */
add_action(
	'flx_gql_register',
	static function () {
		if ( ! function_exists( 'flx_gql_all_caps' ) ) {
			return;
		}
		require_once FLXAI_DIR . 'includes/graphql/schema.php';
	}
);
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m           = is_array( $m ) ? $m : array();
		$m['flx-ai'] = FLXAI_DIR . 'graphql/persisted-queries.json';
		return $m;
	}
);
/*
 * Flux AI has its own settings type with API keys held back (flxAiSettings). The generic settings types
 * would list the key_* fields, which the kit's secret filter does not recognise, so the Flux AI entry is
 * left out of the generated types.
 */
add_action(
	'flx_gql_register_core',
	static function () {
		add_filter(
			'flx_settings_schema',
			static function ( $schema ) {
				if ( is_array( $schema ) ) {
					unset( $schema['flxai_settings'] );
				}
				return $schema;
			},
			99
		);
	},
	1
);

/*
 * Çeviri: kaynak metinler Türkçe; en_GB, en_AU gibi İngilizce yerel ayarlar en_US çevirisine düşer.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-ai', false, dirname( plugin_basename( FLXAI_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-ai' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXAI_DIR . 'languages/flx-ai-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);
add_filter(
	'load_translation_file',
	function ( $file, $domain ) {
		if ( 'flx-ai' === $domain && ! is_readable( $file ) && preg_match( '/-en_[A-Z]{2}\.(l10n\.php|mo)$/', $file, $m ) ) {
			return FLXAI_DIR . 'languages/flx-ai-en_US.' . $m[1];
		}
		return $file;
	},
	10,
	2
);

register_activation_hook( __FILE__, 'flxai_install' );
add_action( 'plugins_loaded', 'flxai_maybe_upgrade' );
