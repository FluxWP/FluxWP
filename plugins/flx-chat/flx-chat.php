<?php
/**
 * Plugin Name:       Flux Chat
 * Description:       WhatsApp (Evolution API) çağrı merkezi: gelen ve giden mesajları canlı görün, yeni sohbet başlatın, hazır cevaplar, etiketler, atama, dahili notlar, otomatik yanıtlar; sohbetten bağış (Flux Donate) ve sertifika (Flux Certificates) kaydı, dekont eşleştirme. OpenRouter ile yapay zekâ cevap önerisi ve otomatik bağış asistanı.
 * Version:           1.50.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  flx-graphql
 * Author:            FluxWP
 * Text Domain:       flx-chat
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

define( 'FLXC_VERSION', '1.50.0' );
define( 'FLXC_DB_VERSION', '10' );
define( 'FLXC_FILE', __FILE__ );
define( 'FLXC_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXC_URL', plugin_dir_url( __FILE__ ) );

require_once FLXC_DIR . 'includes/helpers.php';
require_once FLXC_DIR . 'includes/services/class-flxc-val.php';
require_once FLXC_DIR . 'includes/services/class-flxc-chat-service.php';
require_once FLXC_DIR . 'includes/services/class-flxc-message-service.php';
require_once FLXC_DIR . 'includes/services/class-flxc-desk-service.php';
require_once FLXC_DIR . 'includes/services/class-flxc-settings-service.php';
require_once FLXC_DIR . 'includes/services/class-flxc-report-service.php';
require_once FLXC_DIR . 'includes/install.php';
require_once FLXC_DIR . 'includes/connections.php';
require_once FLXC_DIR . 'includes/evolution.php';
require_once FLXC_DIR . 'includes/wacloud.php';
require_once FLXC_DIR . 'includes/store.php';
require_once FLXC_DIR . 'includes/avatar.php';
require_once FLXC_DIR . 'includes/contact-log.php';
require_once FLXC_DIR . 'includes/ingest.php';
require_once FLXC_DIR . 'includes/media-dedupe.php';
require_once FLXC_DIR . 'includes/send.php';
require_once FLXC_DIR . 'includes/automation.php';
require_once FLXC_DIR . 'includes/rest.php';
require_once FLXC_DIR . 'includes/webhook.php';
require_once FLXC_DIR . 'includes/integrations.php';
require_once FLXC_DIR . 'includes/link-preview.php';
require_once FLXC_DIR . 'includes/ai.php';
require_once FLXC_DIR . 'includes/bot.php';
require_once FLXC_DIR . 'includes/bot-close.php';
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
require_once FLXC_DIR . 'includes/optout.php';
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
	require_once FLXC_DIR . 'includes/flux-ui.php';
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

/*
 * GraphQL (Flux GraphQL). The schema loads only inside a GraphQL request. Without the kit the classic
 * pages and flx-chat/v1 keep working. Registration is skipped when flx-graphql is absent.
 */
add_action(
	'flx_gql_register',
	static function () {
		if ( ! function_exists( 'flx_gql_any_cap' ) ) {
			return;
		}
		require_once FLXC_DIR . 'includes/graphql/schema.php';
		require_once FLXC_DIR . 'includes/graphql/schema-admin.php';
	}
);
add_action(
	'flx_gql_loaders',
	static function () {
		if ( ! function_exists( 'flx_gql_node_loader' ) ) {
			return;
		}
		flx_gql_node_loader( 'FlxChat', 'FLXC_Chat_Loader' );
	}
);
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m               = is_array( $m ) ? $m : array();
		$m['flx-chat']   = FLXC_DIR . 'graphql/persisted-queries.json';
		return $m;
	}
);
add_filter(
	'flx_gql_capabilities',
	static function ( $caps ) {
		$caps   = is_array( $caps ) ? $caps : array();
		$labels = array(
			'flx_reports_manage'  => __( 'Schedules call-center reports', 'flx-chat' ),
			'flx_reports_receive' => __( 'Can receive a call-center report', 'flx-chat' ),
			'flx_reports_view'    => __( 'Can receive the general call-center report', 'flx-chat' ),
		);
		foreach ( $labels as $cap => $label ) {
			$caps[ $cap ] = array(
				'label'   => $label,
				'group'   => 'communication',
				'package' => 'flx-chat',
			);
		}
		return $caps;
	},
	9
);
add_filter(
	'flx_gql_capabilities',
	static function ( $caps ) {
		$caps = is_array( $caps ) ? $caps : array();
		if ( ! isset( $caps['flxc_use'] ) ) {
			$caps['flxc_use'] = array(
				'label'   => __( 'Uses the call center', 'flx-chat' ),
				'group'   => 'communication',
				'package' => 'flx-chat',
			);
		}
		if ( ! isset( $caps['flxc_manage'] ) ) {
			$caps['flxc_manage'] = array(
				'label'   => __( 'Manages the call center', 'flx-chat' ),
				'group'   => 'communication',
				'package' => 'flx-chat',
			);
		}
		return $caps;
	}
);
add_filter(
	'graphql_data_loader_classes',
	static function ( $classes ) {
		$classes                     = is_array( $classes ) ? $classes : array();
		$classes['flxcPhonePerson']  = 'FLXC_Phone_Person_Loader';
		return $classes;
	}
);
add_filter(
	'flx_gql_upload_contexts',
	static function ( $c ) {
		$c                       = is_array( $c ) ? $c : array();
		$c['flx-chat/outbound']  = array(
			'cap'    => static fn(): bool => flxc_can_use(),
			'max_mb' => max( 1, (int) ( (int) apply_filters( 'flxc_max_upload', 16 * MB_IN_BYTES ) / MB_IN_BYTES ) ),
		);
		return $c;
	}
);
/*
 * An agent may load the shared person of a phone only when they can see a chat with that phone.
 * CRM-only fields on FlxContact keep their own flxcrm_view check.
 */
add_filter(
	'flxcrm_gql_can_see_contact',
	static function ( $ok, $contact, $user_id ) {
		if ( $ok ) {
			return true;
		}
		if ( ! is_object( $contact ) || ! isset( $contact->phone ) ) {
			return (bool) $ok;
		}
		$phone = preg_replace( '/\D+/', '', (string) $contact->phone );
		if ( '' === $phone ) {
			return false;
		}
		return FLXC_Chat_Service::phone_visible_to( $phone, (int) $user_id );
	},
	10,
	3
);
