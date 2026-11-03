<?php
/**
 * Kurulum: müşteri özeti tablosu (wp_flxcm_customers) ve sürüm yükseltmeleri.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Install {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'flxcm_customers';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t       = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(190) NOT NULL DEFAULT '',
			phone varchar(40) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			orders int(10) unsigned NOT NULL DEFAULT 0,
			total decimal(18,2) NOT NULL DEFAULT 0,
			first_order bigint(20) unsigned NOT NULL DEFAULT 0,
			last_order bigint(20) unsigned NOT NULL DEFAULT 0,
			first_at datetime NULL DEFAULT NULL,
			last_at datetime NULL DEFAULT NULL,
			note text NULL,
			updated_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY user_id (user_id),
			KEY last_at (last_at),
			KEY total (total)
			) {$charset};"
		);
		update_option( 'flxcm_db_version', FLXCM_DB_VERSION, false );
		if ( ! get_option( 'flxcm_installed_at' ) ) {
			update_option( 'flxcm_installed_at', time(), false );
		}
		if ( ! wp_next_scheduled( 'flxcm_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'flxcm_daily' );
		}
	}

	public static function maybe_upgrade() {
		if ( (int) get_option( 'flxcm_db_version' ) < FLXCM_DB_VERSION ) {
			self::install();
		}
	}
}

register_deactivation_hook(
	FLXCM_FILE,
	function () {
		wp_clear_scheduled_hook( 'flxcm_daily' );
	}
);
