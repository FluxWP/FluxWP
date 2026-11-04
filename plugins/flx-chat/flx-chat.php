<?php
/**
 * Plugin Name:       Flux Chat
 * Description:       WhatsApp (Evolution API) çağrı merkezi: gelen ve giden mesajları canlı görün, yeni sohbet başlatın, hazır cevaplar, etiketler, atama, dahili notlar, otomatik yanıtlar; sohbetten bağış (Flux Donate) ve sertifika (Flux Certificates) kaydı, dekont eşleştirme. OpenRouter ile yapay zekâ cevap önerisi ve otomatik bağış asistanı.
 * Version:           1.35.7
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * Text Domain:       flx-chat
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

define( 'FLXC_VERSION', '1.35.7' );
define( 'FLXC_DB_VERSION', '9' );
define( 'FLXC_FILE', __FILE__ );
define( 'FLXC_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXC_URL', plugin_dir_url( __FILE__ ) );

require_once FLXC_DIR . 'includes/helpers.php';
require_once FLXC_DIR . 'includes/install.php';
require_once FLXC_DIR . 'includes/connections.php';
require_once FLXC_DIR . 'includes/evolution.php';
require_once FLXC_DIR . 'includes/wacloud.php';
require_once FLXC_DIR . 'includes/store.php';
require_once FLXC_DIR . 'includes/ingest.php';
require_once FLXC_DIR . 'includes/send.php';
require_once FLXC_DIR . 'includes/automation.php';
require_once FLXC_DIR . 'includes/rest.php';
require_once FLXC_DIR . 'includes/webhook.php';
require_once FLXC_DIR . 'includes/integrations.php';
require_once FLXC_DIR . 'includes/link-preview.php';
require_once FLXC_DIR . 'includes/ai.php';
require_once FLXC_DIR . 'includes/bot.php';
require_once FLXC_DIR . 'includes/receipt-ai.php';
require_once FLXC_DIR . 'includes/dekont.php';
require_once FLXC_DIR . 'includes/bot-stats.php';
require_once FLXC_DIR . 'includes/handoff-notify.php';
require_once FLXC_DIR . 'includes/export.php';
require_once FLXC_DIR . 'includes/memory.php';
require_once FLXC_DIR . 'includes/jobs.php';
require_once FLXC_DIR . 'includes/blocklist.php';
require_once FLXC_DIR . 'includes/site-search.php';
require_once FLXC_DIR . 'includes/mirror.php';
require_once FLXC_DIR . 'includes/bot-account.php';
require_once FLXC_DIR . 'includes/verify.php';
require_once FLXC_DIR . 'includes/reminders.php';
require_once FLXC_DIR . 'includes/bot-studio.php';
require_once FLXC_DIR . 'includes/bot-info.php';
require_once FLXC_DIR . 'includes/staff.php';
require_once FLXC_DIR . 'includes/beneficiary.php';
require_once FLXC_DIR . 'includes/widgets.php';
require_once FLXC_DIR . 'includes/voice.php';
require_once FLXC_DIR . 'includes/reports.php';
require_once FLXC_DIR . 'includes/bot-editor.php';
require_once FLXC_DIR . 'includes/bench.php';
require_once FLXC_DIR . 'includes/system-checks.php';
require_once FLXC_DIR . 'includes/app.php';

if ( is_admin() ) {
	require_once FLXC_DIR . 'includes/admin.php';
	require_once FLXC_DIR . 'includes/settings-page.php';
}

/*
 * Çeviri: kaynak metinler Türkçe; site/kullanıcı dili başka olduğunda languages/ içindeki çeviri kullanılır.
 * en_GB, en_AU gibi diğer İngilizce yerel ayarları en_US çevirisine düşer.
 */
add_action( 'init', 'flxc_load_textdomain', 1 );
function flxc_load_textdomain() {
	load_plugin_textdomain( 'flx-chat', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-chat' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXC_DIR . 'languages/flx-chat-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

register_activation_hook( __FILE__, 'flxc_activate' );
register_deactivation_hook( __FILE__, 'flxc_deactivate' );
add_action( 'plugins_loaded', 'flxc_maybe_upgrade' );
