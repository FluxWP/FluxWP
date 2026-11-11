<?php
/**
 * Plugin Name:         Flux Forms
 * Plugin URI:          https://example.org
 * Description:         Sürükle-bırak form oluşturucu: çok adımlı formlar, Stripe ödemeleri, koşullu mantık, dashboard, otomasyonlar ve Flux Certificates entegrasyonu. Easy Form Builder (WhiteStudio, GPL) tabanlı fork.
 * Version:             2.1.22
 * Requires at least:   6.4
 * Requires PHP:        7.4
 * Author:              FluxWP
 * Author URI:          https://example.org
 * Text Domain:         flx-forms
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Domain Path:         /languages
 *
 * Based on Easy Form Builder 4.2.0 by WhiteStudio (https://whitestudio.team), GPL v2 or later.
 */

if (!defined('ABSPATH')) {
    die("Direct access of plugin files is not allowed.");
}

// Another copy of the EFB code (YT Form, Easy Form Builder) declares the same
// classes and functions; loading both is a fatal error. See the file for details.
require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx/efb-conflict.php';
$flxf_efb_conflicts = flxf_efb_conflicting_plugins(__FILE__);
if ($flxf_efb_conflicts) {
    flxf_efb_handle_conflict(__FILE__, $flxf_efb_conflicts);
    return;
}
unset($flxf_efb_conflicts);
add_action('admin_notices', static function () {
    flxf_efb_conflict_notice(__FILE__);
});

// Everything else lives in flx-forms-load.php: PHP declares the top-level
// functions of a file while compiling it, so they cannot sit behind the guard
// above in this same file.
define('FLXF_PLUGIN_FILE', __FILE__);
require __DIR__ . '/flx-forms-load.php';
