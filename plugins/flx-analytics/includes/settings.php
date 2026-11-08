<?php
/**
 * Ayarlar, yetkiler, tablolar ve veri dizini.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

/** Varsayılan ayarlar. */
function flxan_defaults() {
	return array(
		'on'            => 1,
		'method'        => 'auto',                 // auto | fast | rest
		'exclude_roles' => array(),                // bu rollerdeki giriş yapmış kişiler hiç sayılmaz (diğerleri rolüyle etiketlenir)
		'exclude_ips'   => '',
		'dnt'           => 0,                      // "Do Not Track" / GPC gönderen tarayıcıları sayma
		'keep_months'   => 0,                      // 0 = sınırsız
		'ev_outbound'   => 1,                      // dış bağlantı tıklamaları
		'ev_download'   => 1,                      // dosya indirmeleri
		'ev_contact'    => 1,                      // telefon, e-posta, WhatsApp tıklamaları
		'ev_search'     => 1,                      // site içi aramalar
		'ev_donation'   => 1,                      // ödenen bağış/sipariş (WooCommerce)
		'posts_column'  => 1,                      // yazı/sayfa listesinde görüntülenme sütunu
		'adminbar'      => 1,                      // ön yüzde yönetim çubuğunda bu sayfanın sayısı
		'tm_hub'        => 0,                      // telemetri merkezi: FluxWP Panel kurulumlarından anonim veri al (includes/telemetry-hub.php)
	);
}

/** Ayar(lar)ı okur. */
function flxan_settings( $key = null ) {
	static $cache = null;
	if ( null === $cache || 'flush' === $key ) {
		$saved = get_option( 'flxan_settings', array() );
		$cache = array_merge( flxan_defaults(), is_array( $saved ) ? $saved : array() );
		if ( 'flush' === $key ) {
			return $cache;
		}
	}
	if ( null === $key ) {
		return $cache;
	}
	return isset( $cache[ $key ] ) ? $cache[ $key ] : null;
}

/** Formdan gelen ayarları temizler. */
function flxan_sanitize_settings( $in ) {
	$d   = flxan_defaults();
	$out = array();
	foreach ( array( 'on', 'dnt', 'ev_outbound', 'ev_download', 'ev_contact', 'ev_search', 'ev_donation', 'posts_column', 'adminbar', 'tm_hub' ) as $k ) {
		$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
	}
	$out['method']        = in_array( $in['method'] ?? '', array( 'auto', 'fast', 'rest' ), true ) ? $in['method'] : $d['method'];
	$roles                = array_keys( wp_roles()->get_names() );
	$out['exclude_roles'] = array_values( array_intersect( $roles, array_map( 'sanitize_key', (array) ( $in['exclude_roles'] ?? array() ) ) ) );
	$ips                  = preg_split( '/[\s,]+/', (string) ( $in['exclude_ips'] ?? '' ) );
	$ips                  = array_filter(
		array_map( 'trim', $ips ),
		function ( $ip ) {
			return '' !== $ip && preg_match( '~^[0-9a-f:.*]+(/\d{1,3})?$~i', $ip );
		}
	);
	$out['exclude_ips']   = implode( "\n", array_slice( array_unique( $ips ), 0, 200 ) );
	$out['keep_months']   = max( 0, min( 120, (int) ( $in['keep_months'] ?? 0 ) ) );
	return $out;
}

/** İstatistikleri görebilir mi? */
function flxan_can_view( $user_id = null ) {
	if ( null === $user_id ) {
		return current_user_can( 'manage_options' ) || current_user_can( 'flxan_view' );
	}
	return user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'flxan_view' );
}

/** Tablo adı: paths, refs, site, pages, referrers, dims, events, seen, recent. */
function flxan_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'flxan_' . $name;
}

/** Veri dizini (kuyruk, günlük tuz, uç ayarı). */
function flxan_data_dir() {
	$up = wp_upload_dir( null, false );
	return untrailingslashit( $up['basedir'] ) . '/flx-analytics';
}

/** Dizini kurar ve web erişimini kapatır. */
function flxan_ensure_dir() {
	$dir = flxan_data_dir();
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! is_dir( $dir ) ) {
		return false;
	}
	if ( ! is_file( $dir . '/.htaccess' ) ) {
		@file_put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore
	}
	if ( ! is_file( $dir . '/index.php' ) ) {
		@file_put_contents( $dir . '/index.php', "<?php\n// Sessiz.\n" ); // phpcs:ignore
	}
	return is_writable( $dir );
}

/** Uç tarafından okunan ayar dosyası (WordPress'siz çalışan uç bunu okur). */
function flxan_write_config() {
	if ( ! flxan_ensure_dir() ) {
		return false;
	}
	$token = get_option( 'flxan_token' );
	if ( ! $token || ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
		$token = bin2hex( random_bytes( 16 ) );
		update_option( 'flxan_token', $token, false );
	}
	$s   = flxan_settings( 'flush' );
	$cfg = array(
		'on'     => (int) $s['on'],
		'token'  => $token,
		'tz'     => wp_timezone_string(),
		'dnt'    => (int) $s['dnt'],
		'ips'    => array_values( array_filter( explode( "\n", (string) $s['exclude_ips'] ) ) ),
		'max_mb' => 50,
	);
	$php = "<?php\n// Flux Analiz uç ayarı; otomatik üretilir.\nreturn " . var_export( $cfg, true ) . ";\n"; // phpcs:ignore
	$tmp = flxan_data_dir() . '/config.php.' . wp_generate_password( 6, false );
	if ( false === @file_put_contents( $tmp, $php ) ) { // phpcs:ignore
		return false;
	}
	$ok = @rename( $tmp, flxan_data_dir() . '/config.php' ); // phpcs:ignore
	if ( function_exists( 'opcache_invalidate' ) ) {
		@opcache_invalidate( flxan_data_dir() . '/config.php', true ); // phpcs:ignore
	}
	return $ok;
}

/** Hızlı uç bu kurulumda kullanılabilir mi (veri dizini ucun beklediği yerde mi)? */
function flxan_fast_possible() {
	$expected = realpath( dirname( FLXAN_DIR, 2 ) . '/uploads/flx-analytics' );
	$actual   = realpath( flxan_data_dir() );
	return $expected && $actual && $expected === $actual && is_file( FLXAN_DIR . 'endpoint.php' );
}

/** Kullanılan toplama yöntemi: fast | rest. */
function flxan_method() {
	$m = flxan_settings( 'method' );
	if ( 'rest' === $m || ! flxan_fast_possible() ) {
		return 'rest';
	}
	if ( 'auto' === $m && 'fail' === get_option( 'flxan_fast_test', '' ) ) {
		return 'rest';
	}
	return 'fast';
}

/** Toplama adresi. */
function flxan_collect_url( $method = null ) {
	$method = $method ? $method : flxan_method();
	return 'fast' === $method ? FLXAN_URL . 'endpoint.php' : rest_url( 'flxan/v1/c' );
}

/** Tablolar + yönetici yetkisi + veri dizini + zamanlayıcı. */
function flxan_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c = $wpdb->get_charset_collate();
	$a = 'CHARACTER SET ascii COLLATE ascii_bin'; // yalnız tüm metin sütunları ASCII olan tablolarda (wpdb karışık karakter setinde UTF-8 veriyi reddeder)
	// seg = giriş yapmış kişinin rolü ('' = dışarıdan), rc = referans kodu (?r=…). Özet tablolarda ikisi de anahtarda.
	$sr = "seg varchar(20) NOT NULL DEFAULT '',
			rc varchar(32) NOT NULL DEFAULT '',";
	$quiet = $wpdb->suppress_errors( true ); // dbDelta eski tablolarda birincil anahtarı ekleyemez; flxan_fix_keys düzeltir
	dbDelta(
		'CREATE TABLE ' . flxan_table( 'paths' ) . " (
			id int(10) unsigned NOT NULL AUTO_INCREMENT,
			path varchar(255) {$a} NOT NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			is404 tinyint(1) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY path (path),
			KEY post_id (post_id)
		) {$c};
		CREATE TABLE " . flxan_table( 'refs' ) . " (
			id int(10) unsigned NOT NULL AUTO_INCREMENT,
			host varchar(190) {$a} NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY host (host)
		) {$c};
		CREATE TABLE " . flxan_table( 'site' ) . " (
			d date NOT NULL,
			{$sr}
			visitors int(10) unsigned NOT NULL DEFAULT 0,
			pageviews int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (d,seg,rc)
		) {$c};
		CREATE TABLE " . flxan_table( 'pages' ) . " (
			d date NOT NULL,
			path_id int(10) unsigned NOT NULL,
			{$sr}
			visitors int(10) unsigned NOT NULL DEFAULT 0,
			pageviews int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (d,path_id,seg,rc),
			KEY path_id (path_id)
		) {$c};
		CREATE TABLE " . flxan_table( 'referrers' ) . " (
			d date NOT NULL,
			ref_id int(10) unsigned NOT NULL,
			{$sr}
			visitors int(10) unsigned NOT NULL DEFAULT 0,
			pageviews int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (d,ref_id,seg,rc),
			KEY ref_id (ref_id)
		) {$c};
		CREATE TABLE " . flxan_table( 'dims' ) . " (
			d date NOT NULL,
			kind varchar(12) NOT NULL,
			val varchar(100) NOT NULL,
			{$sr}
			visitors int(10) unsigned NOT NULL DEFAULT 0,
			pageviews int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (d,kind,val,seg,rc)
		) {$c};
		CREATE TABLE " . flxan_table( 'events' ) . " (
			d date NOT NULL,
			name varchar(40) NOT NULL,
			param varchar(190) NOT NULL DEFAULT '',
			{$sr}
			hits int(10) unsigned NOT NULL DEFAULT 0,
			uniques int(10) unsigned NOT NULL DEFAULT 0,
			amount decimal(14,2) NOT NULL DEFAULT 0,
			PRIMARY KEY  (d,name,param,seg,rc)
		) {$c};
		CREATE TABLE " . flxan_table( 'visits' ) . " (
			d date NOT NULL,
			h char(16) NOT NULL,
			{$sr}
			channel varchar(12) NOT NULL DEFAULT '',
			ref_id int(10) unsigned NOT NULL DEFAULT 0,
			entry_id int(10) unsigned NOT NULL DEFAULT 0,
			country char(2) NOT NULL DEFAULT '',
			pages smallint(5) unsigned NOT NULL DEFAULT 0,
			first_ts int(10) unsigned NOT NULL DEFAULT 0,
			last_ts int(10) unsigned NOT NULL DEFAULT 0,
			seq varchar(120) NOT NULL DEFAULT '',
			exit_id int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (d,h),
			KEY d_rc (d,rc)
		) {$c};
		CREATE TABLE " . flxan_table( 'fsteps' ) . " (
			d date NOT NULL,
			h char(16) NOT NULL,
			f tinyint(3) unsigned NOT NULL,
			step tinyint(3) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (d,h,f),
			KEY f_d (f,d)
		) {$c};
		CREATE TABLE " . flxan_table( 'seen' ) . " (
			d date NOT NULL,
			h char(16) {$a} NOT NULL,
			k char(8) {$a} NOT NULL DEFAULT '',
			PRIMARY KEY  (d,h,k)
		) {$c};
		CREATE TABLE " . flxan_table( 'recent' ) . " (
			ts int(10) unsigned NOT NULL,
			path_id int(10) unsigned NOT NULL DEFAULT 0,
			ref_id int(10) unsigned NOT NULL DEFAULT 0,
			h char(16) NOT NULL DEFAULT '',
			{$sr}
			country char(2) NOT NULL DEFAULT '',
			KEY ts (ts)
		) {$c};"
	);
	$role = get_role( 'administrator' );
	if ( $role && ! $role->has_cap( 'flxan_view' ) ) {
		$role->add_cap( 'flxan_view' );
	}
	if ( ! get_option( 'flxan_first_day' ) ) {
		add_option( 'flxan_first_day', wp_date( 'Y-m-d' ), '', false );
	}
	$wpdb->suppress_errors( $quiet );
	flxan_fix_keys();
	flxan_write_config();
	if ( ! wp_next_scheduled( 'flxan_aggregate' ) ) {
		wp_schedule_event( time() + 60, 'flxan_5min', 'flxan_aggregate' );
	}
	if ( ! wp_next_scheduled( 'flxan_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'flxan_daily' );
	}
	update_option( 'flxan_db_version', FLXAN_DB_VERSION, false );
	update_option( 'flxan_version', FLXAN_VERSION, false );
}

/**
 * dbDelta birincil anahtarı değiştiremez: 0.1.0'dan gelen özet tablolarda anahtara seg ve rc eklenir.
 * Eski satırlar seg = '' ve rc = '' (dışarıdan, referanssız) olarak kalır.
 */
function flxan_fix_keys() {
	global $wpdb;
	$want = array(
		'site'      => array( 'd', 'seg', 'rc' ),
		'pages'     => array( 'd', 'path_id', 'seg', 'rc' ),
		'referrers' => array( 'd', 'ref_id', 'seg', 'rc' ),
		'dims'      => array( 'd', 'kind', 'val', 'seg', 'rc' ),
		'events'    => array( 'd', 'name', 'param', 'seg', 'rc' ),
	);
	foreach ( $want as $t => $cols ) {
		$table = flxan_table( $t );
		$rows  = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'PRIMARY'", ARRAY_A ); // phpcs:ignore
		if ( ! $rows ) {
			continue;
		}
		usort(
			$rows,
			function ( $a, $b ) {
				return (int) $a['Seq_in_index'] <=> (int) $b['Seq_in_index'];
			}
		);
		if ( wp_list_pluck( $rows, 'Column_name' ) !== $cols ) {
			$wpdb->query( "ALTER TABLE {$table} DROP PRIMARY KEY, ADD PRIMARY KEY (" . implode( ',', $cols ) . ')' ); // phpcs:ignore
		}
	}
}

/** Tablolar bu sürümün şemasında mı? (sistem kontrolü) */
function flxan_schema_ok() {
	global $wpdb;
	foreach ( array( 'site', 'pages', 'dims', 'events', 'recent' ) as $t ) {
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . flxan_table( $t ) . ' LIKE %s', 'seg' ) ) ) { // phpcs:ignore
			return false;
		}
	}
	foreach ( array( 'visits', 'fsteps' ) as $t ) {
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', flxan_table( $t ) ) ) ) {
			return false;
		}
	}
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . flxan_table( 'visits' ) . ' LIKE %s', 'seq' ) ); // phpcs:ignore
}

function flxan_deactivate() {
	wp_clear_scheduled_hook( 'flxan_aggregate' );
	wp_clear_scheduled_hook( 'flxan_daily' );
}

/** Aktivasyon kancası güncellemede çalışmaz: şema ve sürüme bakılır. */
function flxan_maybe_upgrade() {
	if ( (int) get_option( 'flxan_db_version', 0 ) < FLXAN_DB_VERSION ) {
		flxan_install(); // 0.1.0 → 0.2.x: yeni sütunlar, visits/fsteps tabloları, yeni birincil anahtarlar
	} elseif ( FLXAN_VERSION !== get_option( 'flxan_version' ) || ! is_file( flxan_data_dir() . '/config.php' ) ) {
		flxan_write_config();
		update_option( 'flxan_version', FLXAN_VERSION, false );
	}
}

/** Saat dilimi değişince uç ayarı da güncellenir. */
foreach ( array( 'timezone_string', 'gmt_offset' ) as $flxan_opt ) {
	add_action( 'update_option_' . $flxan_opt, 'flxan_write_config' );
}
unset( $flxan_opt );

add_filter(
	'cron_schedules',
	function ( $s ) {
		$s['flxan_5min'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minutes', 'flx-analytics' ),
		);
		return $s;
	}
);

/*
 * Referans bağlantıları: https://site/?r=kod. Kod → ad (ör. "ahmet-ig" → "Ahmet'in Instagram'ı").
 * Ad sonradan verilebilir; raporlar geriye dönük yeni adı gösterir.
 */

/** Kod → ad. */
function flxan_ref_names() {
	$n = get_option( 'flxan_ref_names', array() );
	return is_array( $n ) ? $n : array();
}

/** Kodun ekranda görünen adı. */
function flxan_ref_label( $code ) {
	$n = flxan_ref_names();
	return isset( $n[ $code ] ) && '' !== $n[ $code ] ? $n[ $code ] : $code;
}

/** Referans adlarını kim düzenler (varsayılan: yöneticiler ve flxan_view + edit_posts). */
function flxan_can_manage_refs() {
	return (bool) apply_filters( 'flxan_can_manage_refs', current_user_can( 'manage_options' ) || ( flxan_can_view() && current_user_can( 'edit_posts' ) ) );
}

/** Kod ekler / adını değiştirir; name null ise siler. Kaydedilen kodu döndürür ('' = geçersiz). */
function flxan_ref_save( $code, $name ) {
	$code = flxan_clean_ref( $code );
	if ( '' === $code ) {
		return '';
	}
	$all = flxan_ref_names();
	if ( null === $name ) {
		unset( $all[ $code ] );
	} else {
		$all[ $code ] = mb_substr( sanitize_text_field( (string) $name ), 0, 80 );
	}
	if ( count( $all ) > 500 ) {
		return '';
	}
	update_option( 'flxan_ref_names', $all, false );
	return $code;
}

/** Kodu temizler (küçük harf, a-z0-9_-, en fazla 32). */
function flxan_clean_ref( $code ) {
	$code = strtolower( remove_accents( trim( (string) $code ) ) );
	$code = preg_replace( '/[^a-z0-9_-]+/', '-', $code );
	return substr( trim( $code, '-' ), 0, 32 );
}

/*
 * Huniler: adımlar sırayla; bir ziyaretçinin gün içinde ulaştığı EN DERİN adım kaydedilir.
 * Adım türü: any (siteye giriş), path (yol öneki), event (olay adı, isteğe bağlı ayrıntı).
 */

/** Varsayılan huni: siteye giriş → bağış sayfası → sepete ekledi → ödeme sayfası → forma başladı → "Ödeme yap" → sipariş oluştu → tamamlandı. */
function flxan_default_funnels() {
	$steps   = array( array( 'label' => __( 'Site entry', 'flx-analytics' ), 'type' => 'any', 'value' => '' ) );
	$bagis   = get_page_by_path( 'bagis' );
	$steps[] = array( 'label' => __( 'Donation page', 'flx-analytics' ), 'type' => 'path', 'value' => $bagis ? (string) wp_parse_url( get_permalink( $bagis ), PHP_URL_PATH ) : '/bagis/' );
	$steps[] = array( 'label' => __( 'Added to cart', 'flx-analytics' ), 'type' => 'event', 'value' => 'add_to_cart' );
	$co      = function_exists( 'wc_get_checkout_url' ) ? (string) wp_parse_url( wc_get_checkout_url(), PHP_URL_PATH ) : '/odeme/';
	$steps[] = array( 'label' => __( 'Checkout page', 'flx-analytics' ), 'type' => 'path', 'value' => $co );
	$steps[] = array( 'label' => __( 'Started filling the form', 'flx-analytics' ), 'type' => 'event', 'value' => 'checkout:form' );
	$steps[] = array( 'label' => __( 'Clicked "Place order"', 'flx-analytics' ), 'type' => 'event', 'value' => 'checkout:submit' );
	$steps[] = array( 'label' => __( 'Order created', 'flx-analytics' ), 'type' => 'event', 'value' => 'order' );
	$steps[] = array( 'label' => __( 'Donation completed', 'flx-analytics' ), 'type' => 'path', 'value' => trailingslashit( $co ) . 'order-received/' );
	return array(
		array(
			'id'    => 1,
			'name'  => __( 'Donation flow', 'flx-analytics' ),
			'steps' => $steps,
		),
	);
}

/** Tanımlı huniler. */
function flxan_funnels() {
	$f = get_option( 'flxan_funnels', null );
	return is_array( $f ) && $f ? $f : flxan_default_funnels();
}

/** Ayar metni ↔ huniler. Biçim: "# Ad" satırı, sonra her adım "Etiket | /yol/" ya da "Etiket | olay:ad[:ayrıntı]" ya da "Etiket | *". */
function flxan_funnels_to_text( array $funnels ) {
	$out = array();
	foreach ( $funnels as $f ) {
		$lines = array( '# ' . $f['name'] );
		foreach ( $f['steps'] as $st ) {
			$v       = 'any' === $st['type'] ? '*' : ( 'event' === $st['type'] ? 'olay:' . $st['value'] : $st['value'] );
			$lines[] = $st['label'] . ' | ' . $v;
		}
		$out[] = implode( "\n", $lines );
	}
	return implode( "\n\n", $out );
}

function flxan_funnels_from_text( $text ) {
	$funnels = array();
	$cur     = null;
	foreach ( preg_split( '/\R/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		if ( '#' === $line[0] ) {
			if ( $cur && $cur['steps'] ) {
				$funnels[] = $cur;
			}
			$cur = array( 'id' => 0, 'name' => sanitize_text_field( ltrim( $line, "# \t" ) ), 'steps' => array() );
			continue;
		}
		if ( ! $cur ) {
			$cur = array( 'id' => 0, 'name' => __( 'Funnel', 'flx-analytics' ), 'steps' => array() );
		}
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		$label = sanitize_text_field( $parts[0] );
		$val   = isset( $parts[1] ) ? $parts[1] : '';
		if ( '*' === $val || '' === $val ) {
			$st = array( 'label' => $label, 'type' => 'any', 'value' => '' );
		} elseif ( preg_match( '/^(olay|event):\s*([a-z0-9_-]{1,40})(?::(.{1,190}))?$/i', $val, $m ) ) {
			$st = array( 'label' => $label, 'type' => 'event', 'value' => strtolower( $m[2] ) . ( isset( $m[3] ) && '' !== $m[3] ? ':' . $m[3] : '' ) );
		} else {
			$path = (string) wp_parse_url( $val, PHP_URL_PATH );
			$st   = array( 'label' => $label, 'type' => 'path', 'value' => '/' . ltrim( '' !== $path ? $path : $val, '/' ) );
		}
		if ( count( $cur['steps'] ) < 10 ) {
			$cur['steps'][] = $st;
		}
	}
	if ( $cur && $cur['steps'] ) {
		$funnels[] = $cur;
	}
	$funnels = array_slice( $funnels, 0, 5 );
	foreach ( $funnels as $i => &$f ) {
		$f['id'] = $i + 1;
	}
	return $funnels;
}

/** Huni düzenleyicisinden gelen yapıyı temizler (en fazla 5 huni × 10 adım). */
function flxan_funnels_sanitize( $in ) {
	$out = array();
	foreach ( array_slice( (array) $in, 0, 5 ) as $f ) {
		if ( ! is_array( $f ) ) {
			continue;
		}
		$steps = array();
		foreach ( array_slice( (array) ( $f['steps'] ?? array() ), 0, 10 ) as $st ) {
			if ( ! is_array( $st ) ) {
				continue;
			}
			$type  = in_array( $st['type'] ?? '', array( 'any', 'path', 'event' ), true ) ? $st['type'] : 'path';
			$label = sanitize_text_field( (string) ( $st['label'] ?? '' ) );
			$val   = trim( (string) ( $st['value'] ?? '' ) );
			if ( 'any' === $type ) {
				$val = '';
			} elseif ( 'event' === $type ) {
				if ( ! preg_match( '/^([a-z0-9_-]{1,40})(?::(.{1,190}))?$/i', $val, $m ) ) {
					continue;
				}
				$val = strtolower( $m[1] ) . ( isset( $m[2] ) && '' !== $m[2] ? ':' . sanitize_text_field( $m[2] ) : '' );
			} else {
				$path = (string) wp_parse_url( $val, PHP_URL_PATH );
				$val  = '/' . ltrim( '' !== $path ? $path : $val, '/' );
				if ( '/' === $val && '' === $label ) {
					continue;
				}
			}
			$steps[] = array(
				'label' => '' !== $label ? $label : ( 'any' === $type ? __( 'Site entry', 'flx-analytics' ) : $val ),
				'type'  => $type,
				'value' => $val,
			);
		}
		if ( $steps ) {
			$out[] = array(
				'id'    => count( $out ) + 1,
				'name'  => sanitize_text_field( (string) ( $f['name'] ?? '' ) ) ?: __( 'Funnel', 'flx-analytics' ),
				'steps' => $steps,
			);
		}
	}
	return $out;
}

/** Düzenleyicide seçilebilecek olaylar: hazır olaylar + sitede görülen olaylar. */
function flxan_funnel_event_options() {
	global $wpdb;
	$labels = FLXAN_Stats::event_labels();
	$out    = array(
		'checkout:form'   => __( 'First touch on checkout form', 'flx-analytics' ),
		'checkout:submit' => __( '"Pay" click', 'flx-analytics' ),
		'add_to_cart'     => __( 'Add to cart (any product)', 'flx-analytics' ),
		'payment'         => __( 'Payment method chosen', 'flx-analytics' ),
		'order'           => __( 'Order created (before payment)', 'flx-analytics' ),
		'donation'        => __( 'Paid donation / order', 'flx-analytics' ),
		'remove_from_cart' => __( 'Remove from cart', 'flx-analytics' ),
		'coupon'          => __( 'Coupon used', 'flx-analytics' ),
	);
	$rows   = (array) $wpdb->get_results( 'SELECT name, SUM(hits) h FROM ' . flxan_table( 'events' ) . ' GROUP BY name ORDER BY h DESC LIMIT 30', ARRAY_A ); // phpcs:ignore
	foreach ( $rows as $r ) {
		if ( ! isset( $out[ $r['name'] ] ) ) {
			$out[ $r['name'] ] = $labels[ $r['name'] ] ?? $r['name'];
		}
	}
	foreach ( $labels as $k => $v ) {
		if ( ! isset( $out[ $k ] ) ) {
			$out[ $k ] = $v;
		}
	}
	return $out;
}

/** Düzenleyicide önerilecek sayfalar: en çok görüntülenenler + yayımlanmış sayfalar. */
function flxan_funnel_page_options() {
	global $wpdb;
	$out  = array();
	$rows = (array) $wpdb->get_results( 'SELECT p.path, p.post_id, SUM(s.pageviews) pv FROM ' . flxan_table( 'pages' ) . ' s JOIN ' . flxan_table( 'paths' ) . ' p ON p.id = s.path_id WHERE p.is404 = 0 AND s.d >= ' . "'" . esc_sql( gmdate( 'Y-m-d', time() - 90 * DAY_IN_SECONDS ) ) . "'" . ' GROUP BY s.path_id ORDER BY pv DESC LIMIT 120', ARRAY_A ); // phpcs:ignore
	foreach ( $rows as $r ) {
		$out[ rawurldecode( $r['path'] ) ] = FLXAN_Stats::path_title( $r['path'], (int) $r['post_id'] );
	}
	foreach ( get_pages( array( 'number' => 150, 'sort_column' => 'menu_order,post_title' ) ) as $pg ) {
		$path = rawurldecode( (string) wp_parse_url( get_permalink( $pg ), PHP_URL_PATH ) );
		if ( '' !== $path && ! isset( $out[ $path ] ) ) {
			$out[ $path ] = wp_strip_all_tags( get_the_title( $pg ) );
		}
	}
	$list = array();
	foreach ( $out as $path => $title ) {
		$list[] = array( 'path' => $path, 'title' => $title );
	}
	return $list;
}

/** Bir sayfa görüntülemesi / olay bu adımı karşılıyor mu? */
function flxan_step_match( array $st, $kind, $path, $name = '', $param = '' ) {
	if ( 'any' === $st['type'] ) {
		return 'p' === $kind;
	}
	if ( 'path' === $st['type'] ) {
		if ( 'p' !== $kind ) {
			return false;
		}
		$want = strtolower( rawurldecode( $st['value'] ) );
		return '' !== $want && 0 === strpos( strtolower( rawurldecode( $path ) ), $want );
	}
	if ( 'e' !== $kind ) {
		return false;
	}
	$v = explode( ':', $st['value'], 2 );
	return $v[0] === $name && ( ! isset( $v[1] ) || '' === $v[1] || $v[1] === $param );
}
