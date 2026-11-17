<?php
/**
 * Plugin Name:       Flux PDF & Certificates
 * Description:       Sertifika şablonları, canlı PDF önizlemeli sertifika oluşturma, /sertifika/{kod} doğrulama sayfası, e-posta / SMS (Netgsm) / WhatsApp (Evolution API) gönderimi, asenkron kuyruk, kişi kaydı ve detaylı loglar.
 * Version:           2.2.0
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Requires Plugins:  flx-graphql
 * Author:            FluxWP
 * Text Domain:       flx-certificates
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).
require_once __DIR__ . '/includes/flx-background.php'; // Portable worker support (tools/background).

define( 'FLXS_VERSION', '2.2.0' );
define( 'FLXS_DB_VERSION', '6' );
define( 'FLXS_FILE', __FILE__ );
define( 'FLXS_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXS_URL', plugin_dir_url( __FILE__ ) );

require_once FLXS_DIR . 'includes/helpers.php';
require_once FLXS_DIR . 'includes/services/class-flxs-records.php';
require_once FLXS_DIR . 'includes/services/class-flxs-certificate-service.php';
require_once FLXS_DIR . 'includes/services/class-flxs-person-service.php';
require_once FLXS_DIR . 'includes/services/class-flxs-public-service.php';
require_once FLXS_DIR . 'includes/install.php';
require_once FLXS_DIR . 'includes/log.php';
require_once FLXS_DIR . 'includes/people.php';
require_once FLXS_DIR . 'includes/people-shared.php';
require_once FLXS_DIR . 'includes/events-shared.php';
require_once FLXS_DIR . 'includes/templates.php';
require_once FLXS_DIR . 'includes/certificates.php';
require_once FLXS_DIR . 'includes/providers.php';
require_once FLXS_DIR . 'includes/providers/email.php';
require_once FLXS_DIR . 'includes/queue.php';
require_once FLXS_DIR . 'includes/stats.php';
require_once FLXS_DIR . 'includes/events.php';
require_once FLXS_DIR . 'includes/site.php';
require_once FLXS_DIR . 'includes/rest.php';
require_once FLXS_DIR . 'includes/app-manifest.php';
require_once FLXS_DIR . 'includes/public.php';
require_once FLXS_DIR . 'includes/render.php';
require_once FLXS_DIR . 'includes/system-checks.php';

if ( is_admin() ) {
	require_once FLXS_DIR . 'includes/admin/admin.php';
}

/*
 * Çeviri: kaynak metinler Türkçe; site/ziyaretçi dili başka olduğunda languages/ içindeki çeviri kullanılır
 * (flx-certificates-en_US.l10n.php). en_GB, en_AU gibi diğer İngilizce yerel ayarları en_US çevirisine düşer.
 */
add_action( 'init', 'flxs_load_textdomain', 1 );
function flxs_load_textdomain() {
	load_plugin_textdomain( 'flx-certificates', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_filter(
	'load_textdomain_mofile',
	function ( $mofile, $domain ) {
		if ( 'flx-certificates' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			$alt = FLXS_DIR . 'languages/flx-certificates-en_US.mo';
			return $alt;
		}
		return $mofile;
	},
	10,
	2
);

/*
 * GraphQL (Flux GraphQL 0.2+). Without the kit the schema is left out and REST keeps working.
 */
add_action(
	'flx_gql_register',
	static function () {
		if ( ! function_exists( 'flx_gql_any_cap' ) ) {
			return;
		}
		require_once FLXS_DIR . 'includes/graphql/schema.php';
	}
);
/*
 * Loader keys must be named before the first request builds its AppContext, which happens before
 * the lazy schema (includes/graphql/schema.php) is loaded. Only the class names are given here.
 */
add_action(
	'flx_gql_loaders',
	static function () {
		if ( ! function_exists( 'flx_gql_node_loader' ) ) {
			return;
		}
		flx_gql_node_loader( 'FlxCertificate', 'FLXS_Certificate_Loader' );
		flx_gql_node_loader( 'FlxCertificatePerson', 'FLXS_Person_Loader' );
		flx_gql_node_loader( 'FlxCertificateDeliveries', 'FLXS_Delivery_Loader' );
	}
);
add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m   = is_array( $m ) ? $m : array();
		$file = FLXS_DIR . 'graphql/persisted-queries.json';
		if ( is_readable( $file ) ) {
			$m['flx-certificates'] = $file;
		}
		return $m;
	}
);
add_filter(
	'flx_gql_capabilities',
	static function ( $caps ) {
		$caps  = is_array( $caps ) ? $caps : array();
		$group = __( 'Certificates', 'flx-certificates' );
		return $caps + array(
			'flxs_use'       => array(
				'label'   => __( 'Creates and sees certificates', 'flx-certificates' ),
				'group'   => $group,
				'package' => 'flx-certificates',
			),
			'flxs_manage'    => array(
				'label'   => __( 'Manages certificates', 'flx-certificates' ),
				'group'   => $group,
				'package' => 'flx-certificates',
			),
			'flxs_bulk'      => array(
				'label'   => __( 'Sends certificates in bulk', 'flx-certificates' ),
				'group'   => $group,
				'package' => 'flx-certificates',
			),
			'flxs_dashboard' => array(
				'label'   => __( 'Sees the certificate dashboard', 'flx-certificates' ),
				'group'   => $group,
				'package' => 'flx-certificates',
			),
			'flxs_own_ref'   => array(
				'label'   => __( 'Sees only certificates they created or referred', 'flx-certificates' ),
				'group'   => $group,
				'package' => 'flx-certificates',
			),
		);
	}
);
add_filter(
	'flx_gql_upload_contexts',
	static function ( $contexts ) {
		$contexts = is_array( $contexts ) ? $contexts : array();
		$contexts['flx-certificates/file'] = array(
			'cap'    => static fn(): bool => flxs_can_use(),
			'mimes'  => array(
				'pdf'      => 'application/pdf',
				'jpg|jpeg' => 'image/jpeg',
			),
			'max_mb' => 15,
		);
		return $contexts;
	}
);
add_filter(
	'flxcrm_gql_can_see_contact',
	static function ( $ok, $contact ) {
		if ( $ok || ! flxs_can_use() ) {
			return $ok;
		}
		$id = 0;
		if ( is_object( $contact ) && isset( $contact->id ) ) {
			$id = (int) $contact->id;
		} elseif ( is_array( $contact ) && isset( $contact['id'] ) ) {
			$id = (int) $contact['id'];
		}
		if ( $id <= 0 ) {
			return $ok;
		}
		if ( flxs_scoped() ) {
			return FLXS_Person_Service::can_see( $id );
		}
		global $wpdb;
		$mine = flxs_people_mine_sql( '' );
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxs_table( 'people' ) . " WHERE id = %d AND {$mine}", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	},
	10,
	2
);

register_activation_hook( __FILE__, 'flxs_activate' );
register_deactivation_hook( __FILE__, 'flxs_deactivate' );
add_action( 'plugins_loaded', 'flxs_maybe_upgrade' );
