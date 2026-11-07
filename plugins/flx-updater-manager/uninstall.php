<?php
/**
 * Kaldırma: bağlantı ayarları (sunucu adresi, şifreli yönetici anahtarı) silinir. Sunucudaki hiçbir şeye dokunulmaz.
 *
 * @package FLX_Updater_Manager
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'flxum_settings' );
