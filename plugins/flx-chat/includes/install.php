<?php
defined( 'ABSPATH' ) || exit;

function flxc_activate() {
	flxc_install_tables();
	flxc_install_roles();
	flxc_install_defaults();
	flxc_media_dir();
}

function flxc_deactivate() {
	wp_clear_scheduled_hook( 'flxc_cleanup' );
	wp_clear_scheduled_hook( 'flxc_jobs_tick' );
	wp_clear_scheduled_hook( 'flxc_rem_tick' );
}

function flxc_maybe_upgrade() {
	// 1.5.1: bazı @lid sohbetlerde telefon alanında gizli kimliğin rakamları duruyordu; cevaplar o "numaraya" gidip düşüyordu.
	if ( ! get_option( 'flxc_mig_lid151' ) ) {
		update_option( 'flxc_mig_lid151', 1, false );
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT id, jid, phone FROM ' . flxc_table( 'chats' ) . " WHERE jid LIKE '%@lid' AND phone <> ''", ARRAY_A ); // phpcs:ignore
		foreach ( (array) $rows as $r ) {
			if ( (string) $r['phone'] === flxc_lid_digits( $r['jid'] ) ) {
				flxc_update_chat( (int) $r['id'], array( 'phone' => '' ) );
			}
		}
	}
	if ( get_option( 'flxc_db_version' ) !== FLXC_DB_VERSION ) {
		flxc_install_tables();
		flxc_install_roles();
		flxc_install_defaults();
		// 1.1: 1.0, gizli numaralı (@lid) sohbetlerde kimliği telefon sanıyordu; cevaplar yanlış numaraya gidiyordu.
		global $wpdb;
		$wpdb->query( 'UPDATE ' . flxc_table( 'chats' ) . " SET phone = '' WHERE jid LIKE '%@lid'" ); // phpcs:ignore
		// 1.1: eski varsayılan 3 sn Hostinger'da hız sınırına takılıyordu.
		$s = get_option( 'flxc_settings' );
		if ( is_array( $s ) && isset( $s['poll_interval'] ) && (int) $s['poll_interval'] < 5 ) {
			$s['poll_interval'] = 10;
			update_option( 'flxc_settings', $s, false );
		}
	}
}

function flxc_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c   = $wpdb->get_charset_collate();
	$sql = array();

	$sql[] = 'CREATE TABLE ' . flxc_table( 'chats' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		conn varchar(40) NOT NULL DEFAULT '',
		jid varchar(120) NOT NULL DEFAULT '',
		phone varchar(32) NOT NULL DEFAULT '',
		name varchar(190) NOT NULL DEFAULT '',
		push_name varchar(190) NOT NULL DEFAULT '',
		avatar varchar(1000) NOT NULL DEFAULT '',
		avatar_at bigint(20) NOT NULL DEFAULT 0,
		labels varchar(255) NOT NULL DEFAULT '',
		notes text,
		status varchar(20) NOT NULL DEFAULT 'open',
		assigned_to bigint(20) unsigned NOT NULL DEFAULT 0,
		unread int(11) NOT NULL DEFAULT 0,
		pinned tinyint(1) NOT NULL DEFAULT 0,
		last_msg_id bigint(20) unsigned NOT NULL DEFAULT 0,
		last_ts bigint(20) NOT NULL DEFAULT 0,
		last_in_ts bigint(20) NOT NULL DEFAULT 0,
		last_auto_ts bigint(20) NOT NULL DEFAULT 0,
		mem_ts bigint(20) NOT NULL DEFAULT 0,
		rev bigint(20) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		meta longtext,
		PRIMARY KEY  (id),
		UNIQUE KEY conn_jid (conn,jid),
		KEY last_ts (last_ts),
		KEY rev (rev),
		KEY phone (phone)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxc_table( 'messages' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		chat_id bigint(20) unsigned NOT NULL DEFAULT 0,
		conn varchar(40) NOT NULL DEFAULT '',
		wa_id varchar(120) NOT NULL DEFAULT '',
		direction varchar(10) NOT NULL DEFAULT 'in',
		type varchar(20) NOT NULL DEFAULT 'text',
		body longtext,
		media_path varchar(255) NOT NULL DEFAULT '',
		media_mime varchar(100) NOT NULL DEFAULT '',
		media_name varchar(255) NOT NULL DEFAULT '',
		media_size bigint(20) NOT NULL DEFAULT 0,
		quoted_id bigint(20) unsigned NOT NULL DEFAULT 0,
		quoted_wa varchar(120) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT '',
		error text,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		source varchar(20) NOT NULL DEFAULT '',
		ts bigint(20) NOT NULL DEFAULT 0,
		rev bigint(20) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		meta longtext,
		PRIMARY KEY  (id),
		KEY chat_ts (chat_id,ts),
		KEY wa_id (wa_id),
		KEY rev (rev)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxc_table( 'replies' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		shortcut varchar(60) NOT NULL DEFAULT '',
		title varchar(190) NOT NULL DEFAULT '',
		body text,
		category varchar(100) NOT NULL DEFAULT '',
		sort int(11) NOT NULL DEFAULT 0,
		uses int(11) NOT NULL DEFAULT 0,
		created_by bigint(20) unsigned NOT NULL DEFAULT 0,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY shortcut (shortcut)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxc_table( 'receipts' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		message_id bigint(20) unsigned NOT NULL DEFAULT 0,
		chat_id bigint(20) unsigned NOT NULL DEFAULT 0,
		kind varchar(20) NOT NULL DEFAULT '',
		ref_id bigint(20) unsigned NOT NULL DEFAULT 0,
		note varchar(190) NOT NULL DEFAULT '',
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY message_id (message_id),
		KEY ref (kind,ref_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxc_table( 'bench_runs' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		created_at datetime NOT NULL,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		model varchar(190) NOT NULL DEFAULT '',
		judge_model varchar(190) NOT NULL DEFAULT '',
		prompt_label varchar(190) NOT NULL DEFAULT '',
		prompt_text longtext,
		status varchar(20) NOT NULL DEFAULT 'running',
		limit_usd double NOT NULL DEFAULT 0,
		cost double NOT NULL DEFAULT 0,
		tokens_in bigint(20) NOT NULL DEFAULT 0,
		tokens_out bigint(20) NOT NULL DEFAULT 0,
		total int(11) NOT NULL DEFAULT 0,
		passed int(11) NOT NULL DEFAULT 0,
		failed int(11) NOT NULL DEFAULT 0,
		locked_until bigint(20) NOT NULL DEFAULT 0,
		beat bigint(20) NOT NULL DEFAULT 0,
		meta longtext,
		PRIMARY KEY  (id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxc_table( 'bench_results' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		run_id bigint(20) unsigned NOT NULL DEFAULT 0,
		scenario_id varchar(40) NOT NULL DEFAULT '',
		grp varchar(60) NOT NULL DEFAULT '',
		title varchar(190) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'running',
		hclass varchar(4) NOT NULL DEFAULT '',
		cost double NOT NULL DEFAULT 0,
		tokens_in bigint(20) NOT NULL DEFAULT 0,
		tokens_out bigint(20) NOT NULL DEFAULT 0,
		ms int(11) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		data longtext,
		PRIMARY KEY  (id),
		KEY run_id (run_id)
	) $c;";

	// 1.7.0: kişi hafızası (kalıcı bilgiler + konuşma özetleri) ve arka plan işleri.
	$sql[] = 'CREATE TABLE ' . flxc_table( 'memory' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		who varchar(64) NOT NULL DEFAULT '',
		chat_id bigint(20) unsigned NOT NULL DEFAULT 0,
		k varchar(120) NOT NULL DEFAULT '',
		v text,
		kind varchar(20) NOT NULL DEFAULT 'fact',
		source varchar(20) NOT NULL DEFAULT 'bot',
		created_at bigint(20) NOT NULL DEFAULT 0,
		updated_at bigint(20) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY who (who),
		KEY chat_id (chat_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxc_table( 'jobs' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		kind varchar(20) NOT NULL DEFAULT '',
		chat_id bigint(20) unsigned NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'queued',
		attempts int(11) NOT NULL DEFAULT 0,
		locked_until bigint(20) NOT NULL DEFAULT 0,
		created_at bigint(20) NOT NULL DEFAULT 0,
		started_at bigint(20) NOT NULL DEFAULT 0,
		finished_at bigint(20) NOT NULL DEFAULT 0,
		ms int(11) NOT NULL DEFAULT 0,
		cost double NOT NULL DEFAULT 0,
		tokens_in int(11) NOT NULL DEFAULT 0,
		tokens_out int(11) NOT NULL DEFAULT 0,
		note varchar(255) NOT NULL DEFAULT '',
		ref bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY status (status),
		KEY chat_id (chat_id)
	) $c;";

	// 1.8.0: kişinin kurduğu hatırlatıcılar ("her ay bana fidan dikimini hatırlat").
	$sql[] = 'CREATE TABLE ' . flxc_table( 'reminders' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		chat_id bigint(20) unsigned NOT NULL DEFAULT 0,
		title varchar(160) NOT NULL DEFAULT '',
		task text,
		freq varchar(10) NOT NULL DEFAULT 'monthly',
		dom tinyint(3) unsigned NOT NULL DEFAULT 1,
		dow tinyint(3) unsigned NOT NULL DEFAULT 1,
		mon tinyint(3) unsigned NOT NULL DEFAULT 1,
		once_date varchar(10) NOT NULL DEFAULT '',
		at_min smallint(5) unsigned NOT NULL DEFAULT 540,
		donation text,
		next_at bigint(20) NOT NULL DEFAULT 0,
		status varchar(10) NOT NULL DEFAULT 'active',
		runs int(11) NOT NULL DEFAULT 0,
		last_at bigint(20) NOT NULL DEFAULT 0,
		last_note varchar(255) NOT NULL DEFAULT '',
		source varchar(20) NOT NULL DEFAULT 'bot',
		created_at bigint(20) NOT NULL DEFAULT 0,
		updated_at bigint(20) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY chat_id (chat_id),
		KEY due (status, next_at)
	) $c;";

	// 1.10.0: AI ile düzenle oturumları (sonradan açılır, yeniden adlandırılır, arşivlenir).
	$sql[] = 'CREATE TABLE ' . flxc_table( 'editor_sessions' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		title varchar(190) NOT NULL DEFAULT '',
		status varchar(10) NOT NULL DEFAULT 'active',
		data longtext,
		created_at bigint(20) NOT NULL DEFAULT 0,
		updated_at bigint(20) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY status (status, updated_at)
	) $c;";

	// 1.7.0: "bana mesaj atmayın" diyen kişiler; hiçbir kanaldan ileti gitmez.
	$sql[] = 'CREATE TABLE ' . flxc_table( 'blocklist' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		phone varchar(32) NOT NULL DEFAULT '',
		email varchar(190) NOT NULL DEFAULT '',
		jid varchar(120) NOT NULL DEFAULT '',
		reason varchar(255) NOT NULL DEFAULT '',
		source varchar(20) NOT NULL DEFAULT 'agent',
		chat_id bigint(20) unsigned NOT NULL DEFAULT 0,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at bigint(20) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY phone (phone),
		KEY email (email),
		KEY jid (jid)
	) $c;";

	// 1.15.0: dekont defteri (gönderen + dekont no ile; aynı dekont ikinci kez gelince uyarı).
	$sql[] = 'CREATE TABLE ' . flxc_table( 'dekonts' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		payer varchar(190) NOT NULL DEFAULT '',
		payer_key varchar(120) NOT NULL DEFAULT '',
		dekont_no varchar(80) NOT NULL DEFAULT '',
		no_key varchar(80) NOT NULL DEFAULT '',
		amount decimal(14,2) NULL DEFAULT NULL,
		currency varchar(8) NOT NULL DEFAULT '',
		rdate varchar(20) NOT NULL DEFAULT '',
		recipient varchar(190) NOT NULL DEFAULT '',
		iban varchar(40) NOT NULL DEFAULT '',
		source varchar(20) NOT NULL DEFAULT 'whatsapp',
		chat_id bigint(20) unsigned NOT NULL DEFAULT 0,
		message_id bigint(20) unsigned NOT NULL DEFAULT 0,
		donation_id bigint(20) unsigned NOT NULL DEFAULT 0,
		verdict varchar(20) NOT NULL DEFAULT '',
		file_hash varchar(40) NOT NULL DEFAULT '',
		created_at bigint(20) NOT NULL DEFAULT 0,
		PRIMARY KEY  (id),
		KEY no_key (no_key),
		KEY file_hash (file_hash),
		KEY message_id (message_id),
		KEY donation_id (donation_id)
	) $c;";

	foreach ( $sql as $q ) {
		dbDelta( $q );
	}
	update_option( 'flxc_db_version', FLXC_DB_VERSION );
}

function flxc_install_roles() {
	$role = get_role( 'flxc_agent' );
	if ( ! $role ) {
		$role = add_role( 'flxc_agent', __( 'Çağrı Merkezi Temsilcisi', 'flx-chat' ), array( 'read' => true, 'flxc_use' => true ) );
	}
	if ( $role ) {
		$role->add_cap( 'read' );
		$role->add_cap( 'flxc_use' );
	}
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( 'flxc_use' );
		$admin->add_cap( 'flxc_manage' );
	}
}

function flxc_install_defaults() {
	if ( false === get_option( 'flxc_settings', false ) ) {
		$s           = flxc_default_settings();
		$s['labels'] = flxc_default_labels();
		/* translators: %s: site name */
		$s['welcome_text'] = sprintf( __( 'Merhaba! Mesajınız %s ekibine ulaştı. Temsilcimiz en kısa sürede size dönecek.', 'flx-chat' ), get_bloginfo( 'name' ) );
		$s['away_text']    = __( 'Merhaba, şu an mesai saatleri dışındayız. Mesajınızı aldık; ilk iş gününde size dönüş yapacağız.', 'flx-chat' );
		// İlk bağlantı olarak Flux Mail'deki (yoksa Flux Certificates'daki) WhatsApp bağlantısını öner.
		$flxi = flxc_flxi_whatsapp_choices();
		if ( $flxi ) {
			$s['connections'][] = flxc_new_connection( array( 'source' => 'flxi', 'provider_id' => (int) $flxi[0]['id'], 'label' => $flxi[0]['name'] ) );
		} else {
			foreach ( flxc_flxs_evolution_providers() as $p ) {
				$s['connections'][] = flxc_new_connection( array( 'source' => 'flxs', 'provider_id' => (int) $p['id'], 'label' => $p['name'] ) );
				break;
			}
		}
		add_option( 'flxc_settings', $s, '', false );
	}
	global $wpdb;
	if ( ! get_option( 'flxc_replies_seeded' ) ) {
		$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxc_table( 'replies' ) );
		if ( 0 === $count ) {
			foreach ( flxc_default_replies() as $i => $r ) {
				$wpdb->insert(
					flxc_table( 'replies' ),
					array(
						'shortcut'   => $r[0],
						'title'      => $r[1],
						'body'       => $r[2],
						'category'   => $r[3],
						'sort'       => $i,
						'updated_at' => flxc_now(),
					)
				);
			}
		}
		update_option( 'flxc_replies_seeded', 1, false );
	}
}

/** Örnek hazır cevaplar (site dilinde). Değişkenler: {first_name} {name} {phone} {agent} {site} {date} */
function flxc_default_replies() {
	return array(
		array( 'merhaba', __( 'Karşılama', 'flx-chat' ), __( 'Merhaba {first_name}, ben {agent}. Size nasıl yardımcı olabilirim?', 'flx-chat' ), __( 'Genel', 'flx-chat' ) ),
		array( 'bekle', __( 'Kontrol ediyorum', 'flx-chat' ), __( 'Hemen kontrol edip size dönüyorum, lütfen birkaç dakika bekleyin.', 'flx-chat' ), __( 'Genel', 'flx-chat' ) ),
		array( 'alindi', __( 'Talep alındı', 'flx-chat' ), __( 'Talebinizi aldık, en kısa sürede size dönüş yapacağız. 🙏', 'flx-chat' ), __( 'Genel', 'flx-chat' ) ),
		array( 'mesai', __( 'Mesai saatleri', 'flx-chat' ), __( 'Mesai saatlerimiz hafta içi 09:00 – 18:00 arasıdır.', 'flx-chat' ), __( 'Bilgi', 'flx-chat' ) ),
		array( 'site', __( 'Web sitesi', 'flx-chat' ), __( 'Tüm detaylara web sitemizden ulaşabilirsiniz: {site}', 'flx-chat' ), __( 'Bilgi', 'flx-chat' ) ),
		array( 'tesekkur', __( 'Teşekkür', 'flx-chat' ), __( 'Bize ulaştığınız için teşekkür ederiz {first_name}, iyi günler dileriz! 🌱', 'flx-chat' ), __( 'Kapanış', 'flx-chat' ) ),
	);
}

/* WooCommerce, temsilcileri wp-admin yerine Hesabım'a atmasın. */
function flxc_is_agent_user() {
	return is_user_logged_in() && current_user_can( 'flxc_use' );
}
add_filter(
	'woocommerce_prevent_admin_access',
	function ( $prevent ) {
		return flxc_is_agent_user() ? false : $prevent;
	},
	99
);
add_filter(
	'woocommerce_disable_admin_bar',
	function ( $disable ) {
		return flxc_is_agent_user() ? false : $disable;
	},
	99
);
add_filter(
	'woocommerce_login_redirect',
	function ( $redirect, $user ) {
		if ( $user instanceof WP_User && $user->has_cap( 'flxc_use' ) && ! $user->has_cap( 'manage_options' ) && ! $user->has_cap( 'flxs_use' ) ) {
			return admin_url( 'admin.php?page=flx-chat' );
		}
		return $redirect;
	},
	99,
	2
);
add_filter(
	'login_redirect',
	function ( $redirect, $requested, $user ) {
		if ( $user instanceof WP_User && $user->has_cap( 'flxc_use' ) && ! $user->has_cap( 'edit_posts' ) && ! $user->has_cap( 'flxs_use' ) && ( ! $requested || admin_url() === $requested ) ) {
			return admin_url( 'admin.php?page=flx-chat' );
		}
		return $redirect;
	},
	99,
	3
);
