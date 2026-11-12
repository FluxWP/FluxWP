<?php
/**
 * Flux Ads: tablolar, yetkiler, zamanlanmış iş.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

final class FLXADS_Install {

	public static function maybe_upgrade() {
		if ( (int) get_option( 'flxads_db_version' ) !== FLXADS_DB_VERSION ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$cs = $wpdb->get_charset_collate();
		$c  = flxads_table( 'conversions' );
		$o  = flxads_table( 'orders' );
		$sp = flxads_table( 'spend' );
		dbDelta(
			"CREATE TABLE $c (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				platform varchar(12) NOT NULL DEFAULT '',
				event_id varchar(64) NOT NULL DEFAULT '',
				event_name varchar(40) NOT NULL DEFAULT '',
				value decimal(14,2) NOT NULL DEFAULT 0,
				currency char(3) NOT NULL DEFAULT '',
				status varchar(10) NOT NULL DEFAULT 'pending',
				attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
				last_error varchar(255) NOT NULL DEFAULT '',
				test tinyint(1) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				next_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				sent_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY platform_event (platform,event_id),
				KEY status_next (status,next_at),
				KEY order_id (order_id)
			) $cs;"
		);
		dbDelta(
			"CREATE TABLE $o (
				order_id bigint(20) unsigned NOT NULL,
				paid_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				total decimal(14,2) NOT NULL DEFAULT 0,
				currency char(3) NOT NULL DEFAULT '',
				is_donation tinyint(1) NOT NULL DEFAULT 0,
				person_id bigint(20) unsigned NOT NULL DEFAULT 0,
				new_person tinyint(1) NOT NULL DEFAULT 0,
				channel varchar(12) NOT NULL DEFAULT '',
				source varchar(100) NOT NULL DEFAULT '',
				medium varchar(100) NOT NULL DEFAULT '',
				campaign varchar(150) NOT NULL DEFAULT '',
				content varchar(150) NOT NULL DEFAULT '',
				term varchar(100) NOT NULL DEFAULT '',
				click varchar(12) NOT NULL DEFAULT '',
				ft_channel varchar(12) NOT NULL DEFAULT '',
				ft_campaign varchar(150) NOT NULL DEFAULT '',
				landing varchar(255) NOT NULL DEFAULT '',
				status varchar(12) NOT NULL DEFAULT 'paid',
				PRIMARY KEY  (order_id),
				KEY paid_at (paid_at),
				KEY channel (channel),
				KEY person_id (person_id)
			) $cs;"
		);
		dbDelta(
			"CREATE TABLE $sp (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				platform varchar(12) NOT NULL DEFAULT '',
				day date NOT NULL DEFAULT '0000-00-00',
				ad_key varchar(40) NOT NULL DEFAULT '',
				campaign_id varchar(40) NOT NULL DEFAULT '',
				campaign varchar(150) NOT NULL DEFAULT '',
				adset varchar(150) NOT NULL DEFAULT '',
				ad varchar(150) NOT NULL DEFAULT '',
				spend decimal(14,2) NOT NULL DEFAULT 0,
				currency char(3) NOT NULL DEFAULT '',
				impressions int(10) unsigned NOT NULL DEFAULT 0,
				clicks int(10) unsigned NOT NULL DEFAULT 0,
				manual tinyint(1) NOT NULL DEFAULT 0,
				updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				UNIQUE KEY platform_day_ad (platform,day,ad_key),
				KEY day (day)
			) $cs;"
		);
		foreach ( array( 'administrator' => array( 'flxads_view', 'flxads_manage' ), 'shop_manager' => array( 'flxads_view' ) ) as $role => $caps ) {
			$r = get_role( $role );
			if ( $r ) {
				foreach ( $caps as $cap ) {
					$r->add_cap( $cap );
				}
			}
		}
		if ( ! wp_next_scheduled( 'flxads_retry' ) ) {
			wp_schedule_event( time() + 600, 'hourly', 'flxads_retry' );
		}
		update_option( 'flxads_db_version', FLXADS_DB_VERSION, false );
	}
}
