<?php
/**
 * Kurulum: tablolar, zamanlanmış görevler.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

function flxi_activate() {
	flxi_install_tables();
	flxi_kick_queue();
	flxi_netgsm_resume();
	$role = get_role( 'administrator' );
	if ( $role ) {
		$role->add_cap( 'flxi_manage' );
	}
	// 1.14.0: WooCommerce e-posta başlığına eklentiyle gelen FluxWP logosu yazılmışsa sitenin kendi logosuna çevir (yoksa boş: WooCommerce site adını yazar).
	if ( ! get_option( 'flxi_wc_logo_1140' ) ) {
		add_action(
			'init',
			function () {
				$h = get_option( 'woocommerce_email_header_image' );
				if ( is_string( $h ) && false !== strpos( $h, '/assets/img/logo.png' ) && false !== strpos( $h, 'flx-mail' ) ) {
					update_option( 'woocommerce_email_header_image', flxi_brand()['logo'] );
				}
				update_option( 'flxi_wc_logo_1140', 1, false );
			},
			20
		);
	}
	if ( ! wp_next_scheduled( 'flxi_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'flxi_daily' );
	}
}

function flxi_deactivate() {
	wp_clear_scheduled_hook( 'flxi_daily' );
	wp_clear_scheduled_hook( 'flxi_run_queue' );
	wp_clear_scheduled_hook( 'flxi_netgsm_poll' );
	wp_clear_scheduled_hook( 'flxi_mailbox_sync' );
}

function flxi_maybe_upgrade() {
	if ( get_option( 'flxi_db_version' ) !== FLXI_DB_VERSION ) {
		flxi_install_tables();
	}
	// 1.1.1: eski varsayılan slogan (yalnız İstanbul) → Türkiye; kullanıcı değiştirdiyse dokunma.
	$opt = get_option( 'flxi_settings' );
	if ( is_array( $opt ) && isset( $opt['tagline'] ) && 'Daha yeşil bir İstanbul mümkün' === $opt['tagline'] ) {
		$opt['tagline'] = 'Daha yeşil bir Türkiye mümkün';
		update_option( 'flxi_settings', $opt );
	}
	// 1.6.0: varsayılan alt çizim Tepeler oldu; 1.5.0 varsayılanı (orman) kayıtlıysa bir kez çevir.
	if ( is_array( $opt ) && ! get_option( 'flxi_art_160' ) ) {
		if ( isset( $opt['footer_art'] ) && 'orman' === $opt['footer_art'] ) {
			$opt['footer_art'] = 'tepeler';
			update_option( 'flxi_settings', $opt );
		}
		update_option( 'flxi_art_160', 1, false );
	}
	// 1.14.0: WooCommerce e-posta başlığına eklentiyle gelen FluxWP logosu yazılmışsa sitenin kendi logosuna çevir (yoksa boş: WooCommerce site adını yazar).
	if ( ! get_option( 'flxi_wc_logo_1140' ) ) {
		add_action(
			'init',
			function () {
				$h = get_option( 'woocommerce_email_header_image' );
				if ( is_string( $h ) && false !== strpos( $h, '/assets/img/logo.png' ) && false !== strpos( $h, 'flx-mail' ) ) {
					update_option( 'woocommerce_email_header_image', flxi_brand()['logo'] );
				}
				update_option( 'flxi_wc_logo_1140', 1, false );
			},
			20
		);
	}
	if ( ! wp_next_scheduled( 'flxi_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'flxi_daily' );
	}
}

function flxi_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();

	dbDelta(
		'CREATE TABLE ' . flxi_table( 'log' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			channel varchar(12) NOT NULL DEFAULT 'email',
			template varchar(160) NOT NULL DEFAULT '',
			source varchar(80) NOT NULL DEFAULT '',
			recipient text NOT NULL,
			subject text NOT NULL,
			status varchar(12) NOT NULL DEFAULT 'sending',
			error text NULL,
			mailer varchar(30) NOT NULL DEFAULT '',
			sender varchar(190) NOT NULL DEFAULT '',
			body longtext NULL,
			headers text NULL,
			campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY status (status),
			KEY template (template(60)),
			KEY campaign_id (campaign_id)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . flxi_table( 'connections' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			channel varchar(12) NOT NULL,
			type varchar(30) NOT NULL,
			name varchar(190) NOT NULL DEFAULT '',
			config longtext NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			is_default tinyint(1) NOT NULL DEFAULT 0,
			origin varchar(60) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY channel (channel)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . flxi_table( 'campaigns' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			channel varchar(12) NOT NULL DEFAULT 'email',
			data longtext NULL,
			status varchar(12) NOT NULL DEFAULT 'queued',
			total int(11) NOT NULL DEFAULT 0,
			sent int(11) NOT NULL DEFAULT 0,
			failed int(11) NOT NULL DEFAULT 0,
			skipped int(11) NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			finished_at datetime NULL,
			PRIMARY KEY  (id)
		) $c;"
	);
	dbDelta(
		'CREATE TABLE ' . flxi_table( 'queue' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			channel varchar(12) NOT NULL DEFAULT 'email',
			recipient varchar(190) NOT NULL,
			name varchar(190) NOT NULL DEFAULT '',
			vars text NULL,
			status varchar(12) NOT NULL DEFAULT 'queued',
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			error text NULL,
			scheduled_at datetime NOT NULL,
			sent_at datetime NULL,
			log_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY campaign_status (campaign_id,status),
			KEY status_time (status,scheduled_at)
		) $c;"
	);
	// 1.17.0: tanıtım mesajlarından çıkanlar (IPTAL), kanal başına.
	dbDelta(
		'CREATE TABLE ' . flxi_table( 'optouts' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			channel varchar(12) NOT NULL DEFAULT 'whatsapp',
			recipient varchar(190) NOT NULL,
			keyword varchar(40) NOT NULL DEFAULT '',
			source varchar(40) NOT NULL DEFAULT '',
			message varchar(255) NOT NULL DEFAULT '',
			chat_id bigint(20) unsigned NOT NULL DEFAULT 0,
			log_id bigint(20) unsigned NOT NULL DEFAULT 0,
			campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
			note varchar(255) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY channel_recipient (channel,recipient)
		) $c;"
	);
	flxi_mailbox_install_tables( $c );
	update_option( 'flxi_db_version', FLXI_DB_VERSION, false );
}

/** Günlük bakım: eski kayıtları siler. */
add_action(
	'flxi_daily',
	function () {
		global $wpdb;
		$days = max( 1, (int) flxi_setting( 'log_days' ) );
		$cut  = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . flxi_table( 'log' ) . ' WHERE created_at < %s LIMIT 5000', $cut ) ); // phpcs:ignore
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . flxi_table( 'queue' ) . " WHERE status IN ('sent','delivered','skipped','failed','cancelled') AND scheduled_at < %s LIMIT 5000", $cut ) ); // phpcs:ignore
	}
);
