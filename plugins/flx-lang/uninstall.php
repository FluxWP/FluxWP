<?php
/**
 * Kaldırma: ayarlar ve dizin silinir. İçeriklerin dil sürümleri (ayrı sayfa/yazılar) silinmez;
 * dil terimleri ve çeviri bağları bırakılır (eklenti yeniden kurulursa aynen döner).
 *
 * @package flx-lang
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'flxl_settings' );
delete_option( 'flxl_index' );
delete_option( 'flxl_geo_seen' );
