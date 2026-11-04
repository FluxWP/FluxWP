<?php
/**
 * Tablolar, yetkiler ve zamanlanmış işler.
 *
 * contacts       Kişi kartı (tek kayıt; kaynak veriyi kopyalamaz, özetler).
 * links          Kişi ↔ kaynak kaydı (donate:donor:12, commerce:email:x, chat:chat:5, users:user:3). Mükerrer önler.
 * events         Zaman tüneli. `uniq` doluysa aynı olay ikinci kez yazılmaz (kaynaktan tekrar gelse de).
 * tags, contact_tags
 * segments       Kayıtlı dinamik segment kuralları.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Install {

	public static function maybe_upgrade() {
		if ( (int) get_option( 'flxcrm_db_version' ) < FLXCRM_DB_VERSION ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();

		dbDelta(
			'CREATE TABLE ' . flxcrm_table( 'contacts' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			phone varchar(20) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			roles varchar(255) NOT NULL DEFAULT '',
			status varchar(16) NOT NULL DEFAULT 'active',
			owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
			company varchar(190) NOT NULL DEFAULT '',
			city varchar(100) NOT NULL DEFAULT '',
			country varchar(2) NOT NULL DEFAULT '',
			source varchar(24) NOT NULL DEFAULT '',
			utm_source varchar(100) NOT NULL DEFAULT '',
			utm_campaign varchar(100) NOT NULL DEFAULT '',
			consent_email tinyint(1) NOT NULL DEFAULT 0,
			consent_sms tinyint(1) NOT NULL DEFAULT 0,
			consent_whatsapp tinyint(1) NOT NULL DEFAULT 0,
			consent_ads tinyint(1) NOT NULL DEFAULT 0,
			consent_at datetime NULL DEFAULT NULL,
			ltv decimal(18,2) NOT NULL DEFAULT 0,
			donations int(10) unsigned NOT NULL DEFAULT 0,
			orders int(10) unsigned NOT NULL DEFAULT 0,
			paid_days int(10) unsigned NOT NULL DEFAULT 0,
			first_paid_at datetime NULL DEFAULT NULL,
			last_paid_at datetime NULL DEFAULT NULL,
			last_activity_at datetime NULL DEFAULT NULL,
			avg_gap_days int(10) unsigned NOT NULL DEFAULT 0,
			r_score tinyint(1) unsigned NOT NULL DEFAULT 0,
			f_score tinyint(1) unsigned NOT NULL DEFAULT 0,
			m_score tinyint(1) unsigned NOT NULL DEFAULT 0,
			segment varchar(16) NOT NULL DEFAULT '',
			churn tinyint(3) unsigned NOT NULL DEFAULT 0,
			scored_at datetime NULL DEFAULT NULL,
			note text NULL,
			meta longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY phone (phone),
			KEY user_id (user_id),
			KEY segment (segment),
			KEY last_activity_at (last_activity_at),
			KEY last_paid_at (last_paid_at),
			KEY ltv (ltv),
			KEY created_at (created_at)
			) $c;"
		);
		dbDelta(
			'CREATE TABLE ' . flxcrm_table( 'links' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source varchar(24) NOT NULL DEFAULT '',
			source_id varchar(100) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_ref (source,source_id),
			KEY contact_id (contact_id)
			) $c;"
		);
		dbDelta(
			'CREATE TABLE ' . flxcrm_table( 'events' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(32) NOT NULL DEFAULT '',
			source varchar(24) NOT NULL DEFAULT '',
			uniq varchar(120) NULL DEFAULT NULL,
			title varchar(255) NOT NULL DEFAULT '',
			amount decimal(18,2) NOT NULL DEFAULT 0,
			currency varchar(8) NOT NULL DEFAULT '',
			base_amount decimal(18,2) NULL DEFAULT NULL,
			paid tinyint(1) NOT NULL DEFAULT 0,
			url varchar(500) NOT NULL DEFAULT '',
			data longtext NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq (uniq),
			KEY contact_at (contact_id,at),
			KEY type_at (type,at),
			KEY paid (paid)
			) $c;"
		);
		dbDelta(
			'CREATE TABLE ' . flxcrm_table( 'tags' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL DEFAULT '',
			slug varchar(100) NOT NULL DEFAULT '',
			color varchar(9) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
			) $c;"
		);
		dbDelta(
			'CREATE TABLE ' . flxcrm_table( 'contact_tags' ) . " (
			contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
			tag_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (contact_id,tag_id),
			KEY tag_id (tag_id)
			) $c;"
		);
		dbDelta(
			'CREATE TABLE ' . flxcrm_table( 'segments' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			description varchar(255) NOT NULL DEFAULT '',
			rules longtext NULL,
			count int(10) unsigned NOT NULL DEFAULT 0,
			counted_at datetime NULL DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id)
			) $c;"
		);

		self::caps();
		update_option( 'flxcrm_db_version', FLXCRM_DB_VERSION, false );
		if ( ! get_option( 'flxcrm_installed_at' ) ) {
			update_option( 'flxcrm_installed_at', time(), false );
		}
		if ( ! wp_next_scheduled( 'flxcrm_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'flxcrm_daily' );
		}
	}

	/** Yönetici rolüne CRM yetkileri. Diğer roller Flux Roles'tan açılır. */
	public static function caps() {
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( FLXCRM_CAP_VIEW );
			$role->add_cap( FLXCRM_CAP_MANAGE );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'flxcrm_daily' );
	}
}
