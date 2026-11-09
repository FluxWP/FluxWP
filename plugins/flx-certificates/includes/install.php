<?php
defined( 'ABSPATH' ) || exit;

function flxs_activate() {
	flxs_install_tables();
	flxs_install_roles();
	flxs_install_defaults();
	flxs_register_rewrites();
	flush_rewrite_rules();
	flxs_schedule_cron();
	flxs_log( 'plugin_activated', 'system', 0, 'Eklenti etkinleştirildi' );
}

function flxs_deactivate() {
	wp_clear_scheduled_hook( 'flxs_queue_tick' );
	wp_clear_scheduled_hook( 'flxs_daily' );
	flush_rewrite_rules();
}

function flxs_maybe_upgrade() {
	if ( get_option( 'flxs_db_version' ) !== FLXS_DB_VERSION ) {
		flxs_install_tables();
		flxs_install_roles();
		flxs_install_defaults();
		update_option( 'flxs_flush_rewrite', 1 );
	} elseif ( ! get_option( 'flxs_caps_dashboard' ) ) {
		flxs_install_roles();
	}
	flxs_migrate_templates();
}

/** Hazır şablonlara sonradan gelen düzeltmeler (yalnızca kullanıcı o alanı değiştirmediyse uygulanır). */
function flxs_migrate_templates() {
	global $wpdb;
	$done = (int) get_option( 'flxs_tpl_migration', 0 );
	if ( $done >= 6 ) {
		return;
	}
	if ( $done >= 2 ) {
		if ( ! did_action( 'init' ) ) {
			add_action( 'init', 'flxs_migrate_templates', 20 );
			return;
		}
		require_once FLXS_DIR . 'includes/admin/ecards.php';
		// 3 (1.5.0): 1.4.1'in indirdiği filigranlı e-kartlar temiz görsel ve doğru isim alanlarıyla güncellenir
		// 4 (1.6.1): 16 e-kart şablonlarda hazır gelir, pasif (aktif yapılınca bağış sayfasında seçilebilir)
		if ( $done < 4 ) {
			flxs_ecards_sync( true, $done < 3 );
		}
		// 5-6 (1.6.4): tanıtım cümlesinde İstanbul vurgusu kalkar; imza ve kurum adı "Örnek Vakfı" kalır (1.6.3 geri alınır)
		flxs_migrate_istanbul_texts();
		update_option( 'flxs_tpl_migration', 6, false );
		return;
	}
	// 1: Fidan Bağış Sertifikası'nda QR kod logo ile imza arasına, görünür
	foreach ( flxs_list_templates() as $t ) {
		$c = $t['config'];
		if ( 'fidan-bagis-sertifikasi.pdf' !== ( $c['background']['file'] ?? '' ) ) {
			continue;
		}
		$changed = false;
		// 2: şablona özel "Fidan Bağışı Sertifikan hazırlandı" mesajları (mesajlar boşsa)
		if ( ! array_filter( (array) $c['messages'] ) ) {
			$c['messages'] = flxs_fidan_bagis_messages();
			$changed       = true;
		}
		$qr_moved = false;
		foreach ( $c['fields'] as &$f ) {
			if ( $done < 1 && 'qr' === $f['type'] && empty( $f['draw'] ) && 905 === (int) round( $f['x'] ) && 1330 === (int) round( $f['y'] ) ) {
				$f['draw'] = true;
				$f['x']    = 401;
				$f['y']    = 1245;
				$f['w']    = 180;
				$changed   = true;
				$qr_moved  = true;
			}
		}
		unset( $f );
		if ( $changed ) {
			$wpdb->update( flxs_table( 'templates' ), array( 'config' => flxs_json( $c ), 'updated_at' => flxs_now() ), array( 'id' => $t['id'] ) );
		}
		if ( $qr_moved ) {
			if ( $t['thumb'] ) {
				wp_delete_file( flxs_upload_dir()['dir'] . '/' . $t['thumb'] );
			}
			flxs_install_bundled_thumb( $t['id'], $c['background']['file'] );
		}
	}
	update_option( 'flxs_tpl_migration', 2, false );
	flxs_migrate_templates();
}

/** Kullanıcının değiştirmediği hazır metinleri günceller: katılım cümlesi genel ifade, imzalar kurum adıyla. */
function flxs_migrate_istanbul_texts() {
	$map = array(
		"Merhaba {ad}, {sertifika} hazır. Görüntülemek ve indirmek için: {link}\nÖrnek Vakfı" => "Merhaba {ad}, {sertifika} hazır. Görüntülemek ve indirmek için: {link}\nÖrnek Vakfı",
		"Merhaba {ad} 🌱\n\n*{sertifika}* hazırlandı. Görüntülemek ve PDF olarak indirmek için:\n{link}\n\nTeşekkür ederiz,\nÖrnek Vakfı" => "Merhaba {ad} 🌱\n\n*{sertifika}* hazırlandı. Görüntülemek ve PDF olarak indirmek için:\n{link}\n\nTeşekkür ederiz,\nÖrnek Vakfı",
		"Merhaba {ad} 🌱\n\n*Fidan Bağışı Sertifikan* hazırlandı. Görüntülemek ve PDF olarak indirmek için:\n{link}\n\nTeşekkür ederiz,\nÖrnek Vakfı" => "Merhaba {ad} 🌱\n\n*Fidan Bağışı Sertifikan* hazırlandı. Görüntülemek ve PDF olarak indirmek için:\n{link}\n\nTeşekkür ederiz,\nÖrnek Vakfı",
		"Merhaba {ad}, Fidan Bağışı Sertifikan hazırlandı. Görüntülemek ve indirmek için: {link}\nÖrnek Vakfı" => "Merhaba {ad}, Fidan Bağışı Sertifikan hazırlandı. Görüntülemek ve indirmek için: {link}\nÖrnek Vakfı",
		'Fidan Bağış Sertifikası · Örnek Vakfı' => 'Fidan Bağış Sertifikası · Örnek Vakfı',
		"{tarih} tarihinde düzenlenen {etkinlik} etkinliğine katılarak İstanbul'un yeşil geleceğine katkı sağladığınız için teşekkür ederiz." => '{tarih} tarihinde düzenlenen {etkinlik} etkinliğine katılarak doğanın yeşil geleceğine katkı sağladığınız için teşekkür ederiz.',
	);
	$fix = function ( $v ) use ( $map ) {
		return ( is_string( $v ) && isset( $map[ $v ] ) ) ? $map[ $v ] : $v;
	};

	$set = get_option( 'flxs_settings', array() );
	if ( is_array( $set ) ) {
		$new = $set;
		foreach ( array( 'msg_sms', 'msg_whatsapp' ) as $k ) {
			if ( isset( $new[ $k ] ) ) {
				$new[ $k ] = $fix( $new[ $k ] );
			}
		}
		if ( $new !== $set ) {
			update_option( 'flxs_settings', $new );
		}
	}

	foreach ( flxs_list_templates() as $t ) {
		$c   = $t['config'];
		$old = $c;
		foreach ( array( 'sms', 'whatsapp' ) as $k ) {
			$c['messages'][ $k ] = $fix( $c['messages'][ $k ] ?? '' );
		}
		$c['share']['og_description'] = $fix( $c['share']['og_description'] ?? '' );
		foreach ( $c['fields'] as $i => $fl ) {
			if ( isset( $fl['default'] ) ) {
				$c['fields'][ $i ]['default'] = $fix( $fl['default'] );
			}
		}
		if ( $c !== $old ) {
			flxs_save_template( $t['id'], $t['name'], $c, $t['status'] );
		}
	}
}

function flxs_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();

	$sql = array();
	$sql[] = 'CREATE TABLE ' . flxs_table( 'people' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(190) NOT NULL DEFAULT '',
		phone varchar(20) DEFAULT NULL,
		email varchar(190) DEFAULT NULL,
		note text,
		created_by bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY phone (phone),
		KEY email (email)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxs_table( 'templates' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(190) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'active',
		config longtext,
		thumb varchar(255) DEFAULT NULL,
		sort int(11) NOT NULL DEFAULT 0,
		created_by bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxs_table( 'certificates' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		code varchar(40) NOT NULL,
		number varchar(60) NOT NULL DEFAULT '',
		template_id bigint(20) unsigned NOT NULL DEFAULT 0,
		person_id bigint(20) unsigned NOT NULL DEFAULT 0,
		title varchar(255) NOT NULL DEFAULT '',
		data longtext,
		status varchar(20) NOT NULL DEFAULT 'active',
		pdf_file varchar(255) DEFAULT NULL,
		image_file varchar(255) DEFAULT NULL,
		image_w int(11) NOT NULL DEFAULT 0,
		image_h int(11) NOT NULL DEFAULT 0,
		views int(11) NOT NULL DEFAULT 0,
		ref_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		event_id bigint(20) unsigned NOT NULL DEFAULT 0,
		created_by bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY code (code),
		KEY ref_user_id (ref_user_id),
		KEY event_id (event_id),
		KEY person_id (person_id),
		KEY template_id (template_id),
		KEY created_by (created_by)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxs_table( 'batches' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(190) NOT NULL DEFAULT '',
		channel varchar(20) NOT NULL DEFAULT '',
		source varchar(40) NOT NULL DEFAULT '',
		total int(11) NOT NULL DEFAULT 0,
		meta longtext,
		created_by bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxs_table( 'messages' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		batch_id bigint(20) unsigned NOT NULL DEFAULT 0,
		certificate_id bigint(20) unsigned NOT NULL DEFAULT 0,
		person_id bigint(20) unsigned NOT NULL DEFAULT 0,
		channel varchar(20) NOT NULL DEFAULT '',
		provider_id bigint(20) unsigned NOT NULL DEFAULT 0,
		recipient varchar(190) NOT NULL DEFAULT '',
		recipient_name varchar(190) NOT NULL DEFAULT '',
		subject varchar(255) NOT NULL DEFAULT '',
		body longtext,
		status varchar(20) NOT NULL DEFAULT 'queued',
		attempts smallint(5) NOT NULL DEFAULT 0,
		max_attempts smallint(5) NOT NULL DEFAULT 3,
		scheduled_at datetime NOT NULL,
		locked_at datetime DEFAULT NULL,
		sent_at datetime DEFAULT NULL,
		last_error text,
		response longtext,
		external_id varchar(190) NOT NULL DEFAULT '',
		source varchar(40) NOT NULL DEFAULT '',
		created_by bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY status_sched (status,scheduled_at),
		KEY batch_id (batch_id),
		KEY certificate_id (certificate_id),
		KEY person_id (person_id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxs_table( 'providers' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		type varchar(30) NOT NULL DEFAULT '',
		channel varchar(20) NOT NULL DEFAULT '',
		name varchar(190) NOT NULL DEFAULT '',
		config longtext,
		active tinyint(1) NOT NULL DEFAULT 1,
		created_by bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxs_table( 'logs' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		created_at datetime NOT NULL,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		action varchar(60) NOT NULL DEFAULT '',
		object_type varchar(30) NOT NULL DEFAULT '',
		object_id bigint(20) unsigned NOT NULL DEFAULT 0,
		message text,
		context longtext,
		ip varchar(45) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY action (action),
		KEY object (object_type,object_id),
		KEY created_at (created_at)
	) $c;";

	$sql[] = 'CREATE TABLE ' . flxs_table( 'events' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(190) NOT NULL DEFAULT '',
		event_date date DEFAULT NULL,
		target int(11) NOT NULL DEFAULT 0,
		base_count int(11) NOT NULL DEFAULT 0,
		event_time varchar(20) NOT NULL DEFAULT '',
		summary text,
		image_id bigint(20) unsigned NOT NULL DEFAULT 0,
		post_id bigint(20) unsigned NOT NULL DEFAULT 0,
		loc_name varchar(190) NOT NULL DEFAULT '',
		loc_address text,
		loc_map varchar(500) NOT NULL DEFAULT '',
		loc_announce date DEFAULT NULL,
		loc_hits_qr int(11) NOT NULL DEFAULT 0,
		loc_hits_web int(11) NOT NULL DEFAULT 0,
		note text,
		status varchar(20) NOT NULL DEFAULT 'open',
		created_by bigint(20) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		closed_at datetime DEFAULT NULL,
		PRIMARY KEY  (id),
		KEY status (status)
	) $c;";

	foreach ( $sql as $q ) {
		dbDelta( $q );
	}
	update_option( 'flxs_db_version', FLXS_DB_VERSION );
}

function flxs_install_roles() {
	$role = get_role( 'flxs_personel' );
	if ( ! $role ) {
		$role = add_role(
			'flxs_personel',
			'Sertifika Personeli',
			array( 'read' => true, 'flxs_use' => true )
		);
	}
	if ( $role ) {
		$role->add_cap( 'read' );
		$role->add_cap( 'flxs_use' );
		// 1.7.0: Dashboard ayrı yetki (flxs_dashboard); mevcut personel görmeye devam eder, sonra panelden kaldırılabilir
		if ( ! get_option( 'flxs_caps_dashboard' ) ) {
			$role->add_cap( 'flxs_dashboard' );
			update_option( 'flxs_caps_dashboard', 1, false );
		}
		if ( flxs_setting( 'staff_bulk' ) ) {
			$role->add_cap( 'flxs_bulk' );
		} else {
			$role->remove_cap( 'flxs_bulk' );
		}
	}
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( array( 'flxs_use', 'flxs_manage', 'flxs_bulk', 'flxs_dashboard' ) as $cap ) {
			$admin->add_cap( $cap );
		}
	}
}

function flxs_install_defaults() {
	if ( false === get_option( 'flxs_settings', false ) ) {
		add_option( 'flxs_settings', flxs_default_settings(), '', false );
	}
	global $wpdb;
	$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxs_table( 'templates' ) );
	if ( 0 === $count && ! get_option( 'flxs_defaults_installed' ) ) {
		foreach ( flxs_default_templates() as $i => $t ) {
			$id = flxs_save_template( 0, $t['name'], $t['config'], 'active', $i );
			if ( $id && ! is_wp_error( $id ) ) {
				flxs_install_bundled_thumb( $id, $t['config']['background']['file'] ?? '' );
			}
		}
		update_option( 'flxs_defaults_installed', 1, false );
	}
	flxs_upload_dir();
}

/** Hazır şablonların önizleme görselini (assets/backgrounds/*.jpg) uploads'a kopyalar */
function flxs_install_bundled_thumb( $template_id, $bg_file ) {
	global $wpdb;
	$src = flxs_bundled_path( preg_replace( '/\.pdf$/i', '.jpg', basename( (string) $bg_file ) ) );
	if ( ! $bg_file || '' === $src ) {
		return;
	}
	$up   = flxs_upload_dir();
	$name = 'sablon/' . (int) $template_id . '-' . strtolower( wp_generate_password( 6, false ) ) . '.jpg';
	wp_mkdir_p( $up['dir'] . '/sablon' );
	if ( copy( $src, $up['dir'] . '/' . $name ) ) {
		$wpdb->update( flxs_table( 'templates' ), array( 'thumb' => $name ), array( 'id' => (int) $template_id ) );
	}
}

function flxs_schedule_cron() {
	if ( ! wp_next_scheduled( 'flxs_queue_tick' ) ) {
		wp_schedule_event( time() + 60, 'flxs_minute', 'flxs_queue_tick' );
	}
	if ( ! wp_next_scheduled( 'flxs_daily' ) ) {
		wp_schedule_event( time() + 3600, 'daily', 'flxs_daily' );
	}
}

add_filter(
	'cron_schedules',
	function ( $s ) {
		$s['flxs_minute'] = array( 'interval' => 60, 'display' => 'Her dakika (Sertifika kuyruğu)' );
		return $s;
	}
);

add_action(
	'init',
	function () {
		flxs_schedule_cron();
		if ( get_option( 'flxs_flush_rewrite' ) ) {
			delete_option( 'flxs_flush_rewrite' );
			flush_rewrite_rules();
		}
	},
	99
);

/* Personel girişte doğrudan Sertifikalar ekranına gitsin, Başlangıç'ı görmesin. */
add_filter(
	'login_redirect',
	function ( $redirect, $requested, $user ) {
		if ( $user instanceof WP_User && in_array( 'flxs_personel', (array) $user->roles, true ) && ! $user->has_cap( 'edit_posts' ) ) {
			return admin_url( 'admin.php?page=flxs-yeni' );
		}
		return $redirect;
	},
	10,
	3
);

/*
 * WooCommerce uyumu: WooCommerce, edit_posts yetkisi olmayan kullanıcıları wp-admin'den "Hesabım" sayfasına atar
 * ve admin barı gizler. Sertifika personeli panele girebilmeli.
 */
function flxs_is_staff_user( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	return $user instanceof WP_User && $user->exists() && $user->has_cap( 'flxs_use' );
}
add_filter(
	'woocommerce_prevent_admin_access',
	function ( $prevent ) {
		return flxs_is_staff_user() ? false : $prevent;
	},
	99
);
add_filter(
	'woocommerce_disable_admin_bar',
	function ( $disable ) {
		return flxs_is_staff_user() ? false : $disable;
	},
	99
);
add_filter(
	'woocommerce_login_redirect',
	function ( $redirect, $user ) {
		if ( flxs_is_staff_user( $user ) && ! $user->has_cap( 'edit_posts' ) ) {
			return admin_url( 'admin.php?page=flxs-yeni' );
		}
		return $redirect;
	},
	99,
	2
);
