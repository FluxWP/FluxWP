<?php
/**
 * Plugin Name:       Flux CRM
 * Description:       Kişiler ve pazarlama: bağışçı, müşteri, gönüllü ve aday tek kişi kartında; zaman tüneli, etiketler, KVKK izinleri, RFM skoru ve kayıp riski, dinamik segmentler. Veriyi Flux Donate, Flux Commerce, Flux Chat, Flux Forms ve WordPress kullanıcılarından toplar; gönderim Flux Mail, AI Flux AI üzerinden.
 * Version:           0.13.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  flx-graphql
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flx-crm
 * Domain Path:       /languages
 * Update URI:        https://updater.fluxwp.com/flx-crm
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

if ( defined( 'FLXCRM_VERSION' ) ) {
	return;
}

define( 'FLXCRM_VERSION', '0.13.0' );
define( 'FLXCRM_DB_VERSION', 3 );
define( 'FLXCRM_FILE', __FILE__ );
define( 'FLXCRM_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXCRM_URL', plugin_dir_url( __FILE__ ) );

require_once FLXCRM_DIR . 'includes/helpers.php';
require_once FLXCRM_DIR . 'includes/services/class-flxcrm-dto.php';
require_once FLXCRM_DIR . 'includes/class-flxcrm-install.php';
require_once FLXCRM_DIR . 'includes/class-flxcrm-contacts.php';
require_once FLXCRM_DIR . 'includes/class-flxcrm-events.php';
require_once FLXCRM_DIR . 'includes/class-flxcrm-tags.php';
require_once FLXCRM_DIR . 'includes/class-flxcrm-scoring.php';
require_once FLXCRM_DIR . 'includes/class-flxcrm-segments.php';
require_once FLXCRM_DIR . 'includes/class-flxcrm-sync.php';
require_once FLXCRM_DIR . 'includes/class-flxcrm-journeys.php';
require_once FLXCRM_DIR . 'includes/class-flxcrm-event-messages.php';
require_once FLXCRM_DIR . 'includes/services/class-flxcrm-contact-service.php';
require_once FLXCRM_DIR . 'includes/services/class-flxcrm-segment-service.php';
require_once FLXCRM_DIR . 'includes/services/class-flxcrm-insight-service.php';
require_once FLXCRM_DIR . 'includes/services/class-flxcrm-settings-service.php';
require_once FLXCRM_DIR . 'includes/services/class-flxcrm-journey-service.php';
require_once FLXCRM_DIR . 'includes/services/class-flxcrm-gathering-service.php';
require_once FLXCRM_DIR . 'includes/services/class-flxcrm-section-service.php';
require_once FLXCRM_DIR . 'includes/adapters/donate.php';
require_once FLXCRM_DIR . 'includes/adapters/commerce.php';
require_once FLXCRM_DIR . 'includes/adapters/chat.php';
require_once FLXCRM_DIR . 'includes/adapters/mail.php';
require_once FLXCRM_DIR . 'includes/adapters/forms.php';
require_once FLXCRM_DIR . 'includes/adapters/users.php';
require_once FLXCRM_DIR . 'includes/class-flxcrm-rest.php';
require_once FLXCRM_DIR . 'includes/app-manifest.php';
require_once FLXCRM_DIR . 'includes/integrations.php';
require_once FLXCRM_DIR . 'includes/ai-tools.php';
require_once FLXCRM_DIR . 'includes/person-card.php';
require_once FLXCRM_DIR . 'includes/extend.php';
require_once FLXCRM_DIR . 'includes/system-checks.php';
if ( is_admin() ) {
	require_once FLXCRM_DIR . 'includes/admin/class-flxcrm-admin.php';
}
// The duplicate matching lives in the contact service; the GraphQL schema reads the groups without the admin pages.

/*
 * GraphQL (Flux GraphQL): the schema is loaded only inside a GraphQL request. Without Flux GraphQL 0.2.0+
 * (capability model) the schema is left out; REST, the AI tools and the classic pages keep working.
 */
add_action(
	'flx_gql_register',
	static function () {
		if ( ! function_exists( 'flx_gql_any_cap' ) ) {
			return;
		}
		require_once FLXCRM_DIR . 'includes/graphql/schema.php';
	}
);
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m            = is_array( $m ) ? $m : array();
		$m['flx-crm'] = FLXCRM_DIR . 'graphql/persisted-queries.json';
		return $m;
	}
);
/*
 * Every writer (REST, classic pages, AI tools, sync, package adapters, GraphQL) goes through the data classes, which
 * fire these actions: drop the GraphQL cache of the contact and the lists that count it.
 */
foreach ( array( 'flxcrm_contact_created', 'flxcrm_contact_updated', 'flxcrm_contact_deleting', 'flxcrm_consent_changed', 'flxcrm_tag_added', 'flxcrm_tag_removed', 'flxcrm_role_added', 'flxcrm_segment_changed' ) as $flxcrm_hook ) {
	add_action(
		$flxcrm_hook,
		static function ( $id = 0 ) {
			flxcrm_purge( array( 'flx-crm:contacts', 'flx-crm:overview', 'flx-crm:meta', 'flx-crm:contact:' . (int) $id ) );
		}
	);
}
add_action(
	'flxcrm_contact_merged',
	static function ( $keep = 0, $dup = 0 ) {
		flxcrm_purge( array( 'flx-crm:contacts', 'flx-crm:overview', 'flx-crm:contact:' . (int) $keep, 'flx-crm:contact:' . (int) $dup ) );
	},
	10,
	2
);
add_action(
	'flxcrm_event',
	static function ( $event_id = 0, $contact_id = 0 ) {
		flxcrm_purge( array( 'flx-crm:contacts', 'flx-crm:overview', 'flx-crm:contact:' . (int) $contact_id ) );
	},
	10,
	2
);
unset( $flxcrm_hook );
add_filter(
	'flx_gql_upload_contexts',
	static function ( $c ) {
		$c                        = is_array( $c ) ? $c : array();
		$cap                      = static function () {
			return flxcrm_can( true ) && current_user_can( 'upload_files' );
		};
		$c['flx-crm/event-image'] = array(
			'cap'   => $cap,
			'mimes' => array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'gif'          => 'image/gif',
				'webp'         => 'image/webp',
			),
		);
		$c['flx-crm/event-file']  = array( 'cap' => $cap );
		return $c;
	}
);
add_filter(
	'flx_gql_capabilities',
	static function ( $caps ) {
		$caps = is_array( $caps ) ? $caps : array();
		if ( ! isset( $caps[ FLXCRM_CAP_VIEW ] ) ) {
			$caps[ FLXCRM_CAP_VIEW ] = array( 'label' => __( 'Sees contacts, segments and the CRM overview', 'flx-crm' ), 'group' => 'crm', 'package' => 'flx-crm' );
		}
		if ( ! isset( $caps[ FLXCRM_CAP_MANAGE ] ) ) {
			$caps[ FLXCRM_CAP_MANAGE ] = array( 'label' => __( 'Edits contacts, segments, journeys and CRM settings', 'flx-crm' ), 'group' => 'crm', 'package' => 'flx-crm' );
		}
		return $caps;
	}
);

/*
 * Çeviri: kaynak metinler Türkçe; site/kullanıcı dili İngilizce olduğunda languages/ içindeki çeviri kullanılır.
 * en_GB, en_AU gibi diğer İngilizce yerel ayarları en_US çevirisine düşer.
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'flx-crm', false, dirname( plugin_basename( FLXCRM_FILE ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-crm' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLXCRM_DIR . 'languages/flx-crm-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

register_activation_hook( FLXCRM_FILE, array( 'FLXCRM_Install', 'install' ) );
register_deactivation_hook( FLXCRM_FILE, array( 'FLXCRM_Install', 'deactivate' ) );
// Ortak kişi tablosu (flx-brand people.php, after_setup_theme 0'da yüklenir): CRM kendi sütunlarını ekler,
// 0.2.x'in flxcrm_contacts tablosunu oraya taşır ve kişi API'sini (flx_person_*) kendi kurallarıyla yürütür.
add_filter( 'flx_people_columns', array( 'FLXCRM_Install', 'people_columns' ) );
add_filter( 'flx_people_keys', array( 'FLXCRM_Install', 'people_keys' ) );
add_action( 'flx_people_before_install', array( 'FLXCRM_Install', 'migrate_people' ) );
add_action( 'after_setup_theme', array( 'FLXCRM_Install', 'maybe_upgrade' ), 1 );

add_action(
	'plugins_loaded',
	function () {
		FLXCRM_Scoring::init();
		FLXCRM_Sync::init();
		FLXCRM_Journeys::init();
		FLXCRM_Adapter_Donate::init();
		FLXCRM_Adapter_Commerce::init();
		FLXCRM_Adapter_Chat::init();
		FLXCRM_Adapter_Forms::init();
		FLXCRM_Adapter_Users::init();
		FLXCRM_Adapter_Mail::init();
		FLXCRM_REST::init();
		if ( is_admin() ) {
			FLXCRM_Admin::init();
		}
		do_action( 'flxcrm_loaded' );
	},
	15
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXCRM_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( flxcrm_admin_url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'flx-crm' ) . '</a>' );
		return $links;
	}
);

/** Güncellemeler ekranında paket logosu. */
add_filter(
	'flxu_package_icons',
	function ( $icons, $slug ) {
		if ( 'flx-crm' === $slug && ! $icons ) {
			$url   = FLXCRM_URL . 'assets/icon.svg?ver=' . FLXCRM_VERSION;
			$icons = array( 'svg' => $url, '1x' => $url, '2x' => $url );
		}
		return $icons;
	},
	10,
	2
);

/* Flux Admin: sayfa başlığı (logo + açıklama), menü logosu ve "Büyüme" grubunda ilk sıra. */
add_filter(
	'flxa_page_heads',
	function ( $map ) {
		$map['flx-crm'] = 'flx-crm';
		return $map;
	}
);
add_filter(
	'flxa_package_heads',
	function ( $heads ) {
		$heads['flx-crm'] = array( 'Flux CRM', __( 'Contacts, roles, segments and marketing', 'flx-crm' ) );
		return $heads;
	}
);
add_filter(
	'flxa_menu_logos',
	function ( $m ) {
		$m['flx-crm'] = 'flx-crm.svg';
		return $m;
	}
);
add_filter(
	'flxa_default_groups',
	function ( $groups ) {
		if ( isset( $groups['growth'][1] ) && is_array( $groups['growth'][1] ) && ! in_array( 'flx-crm', $groups['growth'][1], true ) ) {
			array_unshift( $groups['growth'][1], 'flx-crm' );
		}
		return $groups;
	}
);
