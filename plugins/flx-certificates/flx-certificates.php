<?php
/**
 * Plugin Name:       Flux Certificates
 * Description:       Sertifika şablonları, canlı PDF önizlemeli sertifika oluşturma, /sertifika/{kod} doğrulama sayfası, e-posta / SMS (Netgsm) / WhatsApp (Evolution API) gönderimi, asenkron kuyruk, kişi kaydı ve detaylı loglar.
 * Version:           1.9.15
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * Text Domain:       flx-certificates
 * License:           GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).
require_once __DIR__ . '/includes/flx-background.php'; // Portable worker support (tools/background).

define( 'FLXS_VERSION', '1.9.15' );
define( 'FLXS_DB_VERSION', '5' );
define( 'FLXS_FILE', __FILE__ );
define( 'FLXS_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLXS_URL', plugin_dir_url( __FILE__ ) );

require_once FLXS_DIR . 'includes/helpers.php';
require_once FLXS_DIR . 'includes/install.php';
require_once FLXS_DIR . 'includes/log.php';
require_once FLXS_DIR . 'includes/people.php';
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

register_activation_hook( __FILE__, 'flxs_activate' );
register_deactivation_hook( __FILE__, 'flxs_deactivate' );
add_action( 'plugins_loaded', 'flxs_maybe_upgrade' );
