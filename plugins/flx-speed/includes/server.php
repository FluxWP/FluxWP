<?php
/**
 * Sunucu, veritabanı, autoload, cron ve OPcache bilgileri.
 * shell_exec kullanılmaz: /proc dosyaları okunur; okunamazsa (open_basedir, Windows) alan boş döner.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

/** /proc dosyasını sessizce okur. */
function flxp_read_proc( $file ) {
	if ( ! @is_readable( $file ) ) { // phpcs:ignore
		return '';
	}
	$s = @file_get_contents( $file ); // phpcs:ignore
	return is_string( $s ) ? $s : '';
}

/** Çekirdek sayısı. */
function flxp_cpu_cores() {
	static $n = null;
	if ( null !== $n ) {
		return $n;
	}
	$n   = 0;
	$cpu = flxp_read_proc( '/proc/cpuinfo' );
	if ( $cpu ) {
		$n = preg_match_all( '/^processor\s*:/m', $cpu );
	}
	if ( ! $n && getenv( 'NUMBER_OF_PROCESSORS' ) ) {
		$n = (int) getenv( 'NUMBER_OF_PROCESSORS' );
	}
	return $n;
}

/** /proc/meminfo → KB. */
function flxp_meminfo() {
	$out = array();
	foreach ( explode( "\n", flxp_read_proc( '/proc/meminfo' ) ) as $line ) {
		if ( preg_match( '/^(\w+):\s+(\d+)/', $line, $m ) ) {
			$out[ $m[1] ] = (int) $m[2];
		}
	}
	return $out;
}

/**
 * Anlık ve ucuz ölçüler (panodaki canlı kutu Heartbeat ile bunu ister).
 */
function flxp_server_live() {
	$cores = flxp_cpu_cores();
	$load  = function_exists( 'sys_getloadavg' ) ? @sys_getloadavg() : false; // phpcs:ignore
	$mem   = flxp_meminfo();
	$total = $mem['MemTotal'] ?? 0;
	$avail = isset( $mem['MemAvailable'] ) ? $mem['MemAvailable'] : ( ( $mem['MemFree'] ?? 0 ) + ( $mem['Cached'] ?? 0 ) + ( $mem['Buffers'] ?? 0 ) );
	$up    = (float) strtok( flxp_read_proc( '/proc/uptime' ), ' ' );
	$disk  = function_exists( 'disk_total_space' ) ? @disk_total_space( ABSPATH ) : false; // phpcs:ignore
	$free  = function_exists( 'disk_free_space' ) ? @disk_free_space( ABSPATH ) : false; // phpcs:ignore
	return array(
		'cores'        => $cores,
		'load'         => is_array( $load ) ? array_map(
			function ( $v ) {
				return round( (float) $v, 2 );
			},
			array_slice( $load, 0, 3 )
		) : null,
		'load_pct'     => ( is_array( $load ) && $cores ) ? (int) round( $load[0] / $cores * 100 ) : null,
		'ram_total_kb' => $total ? $total : null,
		'ram_used_kb'  => $total ? max( 0, $total - $avail ) : null,
		'ram_pct'      => $total ? (int) round( ( $total - $avail ) / $total * 100 ) : null,
		'uptime_s'     => $up ? (int) $up : null,
		'disk_total'   => $disk ? (float) $disk : null,
		'disk_free'    => false !== $free ? (float) $free : null,
		'disk_pct'     => ( $disk && false !== $free ) ? (int) round( ( $disk - $free ) / $disk * 100 ) : null,
		'php_mem_kb'   => (int) round( memory_get_usage() / 1024 ),
		'time'         => time(),
	);
}

/** PHP boyut ifadesini (128M) bayta çevirir. */
function flxp_bytes( $v ) {
	return function_exists( 'wp_convert_hr_to_bytes' ) ? wp_convert_hr_to_bytes( (string) $v ) : (int) $v;
}

/** Sunucu ayrıntısı: canlı ölçüler + PHP + OPcache + kimlik. */
function flxp_server_stats() {
	$opc   = function_exists( 'opcache_get_status' ) ? @opcache_get_status( false ) : false; // phpcs:ignore
	$opcache = null;
	if ( is_array( $opc ) ) {
		$m       = $opc['memory_usage'] ?? array();
		$s       = $opc['opcache_statistics'] ?? array();
		$opcache = array(
			'enabled'       => ! empty( $opc['opcache_enabled'] ),
			'used_mb'       => isset( $m['used_memory'] ) ? round( $m['used_memory'] / 1048576, 1 ) : null,
			'free_mb'       => isset( $m['free_memory'] ) ? round( $m['free_memory'] / 1048576, 1 ) : null,
			'wasted_pct'    => isset( $m['current_wasted_percentage'] ) ? round( $m['current_wasted_percentage'], 1 ) : null,
			'hit_rate'      => isset( $s['opcache_hit_rate'] ) ? round( $s['opcache_hit_rate'], 1 ) : null,
			'cached_files'  => $s['num_cached_scripts'] ?? null,
			'max_files'     => $s['max_cached_keys'] ?? null,
			'restarts'      => ( $s['oom_restarts'] ?? 0 ) + ( $s['hash_restarts'] ?? 0 ),
		);
	}
	$limit = flxp_bytes( ini_get( 'memory_limit' ) );
	return array(
		'live'     => flxp_server_live(),
		'server'   => array(
			'os'        => php_uname( 's' ) . ' ' . php_uname( 'r' ),
			'software'  => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '',
			'hostname'  => function_exists( 'gethostname' ) ? (string) gethostname() : '',
			'ip'        => isset( $_SERVER['SERVER_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) : '',
			'port'      => isset( $_SERVER['SERVER_PORT'] ) ? (int) $_SERVER['SERVER_PORT'] : 0,
			'doc_root'  => isset( $_SERVER['DOCUMENT_ROOT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) ) : '',
			'cloudlinux'=> @is_dir( '/proc/lve' ) || @file_exists( '/etc/cloudlinux-release' ), // phpcs:ignore
		),
		'php'      => array(
			'version'             => PHP_VERSION,
			'sapi'                => PHP_SAPI,
			'memory_limit'        => ini_get( 'memory_limit' ),
			'memory_limit_bytes'  => $limit,
			'wp_memory_limit'     => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '',
			'wp_max_memory_limit' => defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : '',
			'usage_kb'            => (int) round( memory_get_usage() / 1024 ),
			'peak_kb'             => (int) round( memory_get_peak_usage() / 1024 ),
			'max_execution_time'  => ini_get( 'max_execution_time' ),
			'max_input_time'      => ini_get( 'max_input_time' ),
			'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
			'post_max_size'       => ini_get( 'post_max_size' ),
			'max_input_vars'      => ini_get( 'max_input_vars' ),
			'short_open_tag'      => (bool) ini_get( 'short_open_tag' ),
			'allow_url_fopen'     => (bool) ini_get( 'allow_url_fopen' ),
			'disabled_functions'  => (string) ini_get( 'disable_functions' ),
		),
		'opcache'  => $opcache,
		'caches'   => array(
			'object_cache' => wp_using_ext_object_cache(),
			'apcu'         => function_exists( 'apcu_enabled' ) && apcu_enabled(),
			'memcached'    => class_exists( 'Memcached' ),
			'redis'        => class_exists( 'Redis' ),
			'page_cache'   => defined( 'WP_CACHE' ) && WP_CACHE,
			'litespeed'    => defined( 'LSCWP_V' ),
		),
	);
}

/** Veritabanı: değişkenler, durum, tablolar, temizlik adayları. */
function flxp_database( $with_tables = true ) {
	global $wpdb;
	$wpdb->suppress_errors( true );
	$vars = array();
	$want = array( 'version', 'version_comment', 'max_connections', 'max_allowed_packet', 'innodb_buffer_pool_size', 'wait_timeout', 'interactive_timeout', 'slow_query_log', 'long_query_time', 'query_cache_size', 'query_cache_type', 'table_open_cache', 'tmp_table_size', 'max_heap_table_size', 'character_set_server', 'collation_server', 'sql_mode', 'max_user_connections', 'innodb_log_file_size', 'thread_cache_size', 'join_buffer_size', 'sort_buffer_size' );
	foreach ( (array) $wpdb->get_results( "SHOW VARIABLES WHERE Variable_name IN ('" . implode( "','", $want ) . "')", ARRAY_N ) as $r ) { // phpcs:ignore
		$vars[ $r[0] ] = $r[1];
	}
	$status = array();
	$wants  = array( 'Uptime', 'Threads_connected', 'Threads_running', 'Max_used_connections', 'Questions', 'Slow_queries', 'Aborted_connects', 'Aborted_clients', 'Created_tmp_disk_tables', 'Created_tmp_tables', 'Open_tables', 'Opened_tables', 'Innodb_buffer_pool_read_requests', 'Innodb_buffer_pool_reads' );
	foreach ( (array) $wpdb->get_results( "SHOW GLOBAL STATUS WHERE Variable_name IN ('" . implode( "','", $wants ) . "')", ARRAY_N ) as $r ) { // phpcs:ignore
		$status[ $r[0] ] = $r[1];
	}
	$derived = array();
	if ( ! empty( $status['Innodb_buffer_pool_read_requests'] ) ) {
		$derived['buffer_pool_hit_pct'] = round( 100 - ( (float) ( $status['Innodb_buffer_pool_reads'] ?? 0 ) / (float) $status['Innodb_buffer_pool_read_requests'] * 100 ), 2 );
	}
	if ( ! empty( $status['Created_tmp_tables'] ) ) {
		$derived['tmp_disk_pct'] = round( (float) ( $status['Created_tmp_disk_tables'] ?? 0 ) / (float) $status['Created_tmp_tables'] * 100, 1 );
	}
	if ( ! empty( $status['Uptime'] ) && isset( $status['Questions'] ) ) {
		$derived['queries_per_sec'] = round( (float) $status['Questions'] / (float) $status['Uptime'], 2 );
	}

	$tables = array();
	$tot    = array( 'data' => 0, 'index' => 0, 'free' => 0, 'rows' => 0, 'count' => 0 );
	if ( $with_tables ) {
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $wpdb->base_prefix ) . '%' ), ARRAY_A ) as $t ) {
			$row      = array(
				'name'    => $t['Name'],
				'engine'  => (string) $t['Engine'],
				'rows'    => (int) $t['Rows'],
				'data'    => (int) $t['Data_length'],
				'index'   => (int) $t['Index_length'],
				'free'    => (int) $t['Data_free'],
				'collate' => (string) $t['Collation'],
			);
			$tables[] = $row;
			$tot['data']  += $row['data'];
			$tot['index'] += $row['index'];
			$tot['free']  += $row['free'];
			$tot['rows']  += $row['rows'];
			$tot['count']++;
		}
		usort(
			$tables,
			function ( $a, $b ) {
				return ( $b['data'] + $b['index'] ) <=> ( $a['data'] + $a['index'] );
			}
		);
	}

	$now   = time();
	$clean = array(
		'expired_transients' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', $now ) ),
		'transients'         => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_' ) . '%' ) ),
		'revisions'          => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" ), // phpcs:ignore
		'auto_drafts'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" ), // phpcs:ignore
		'trashed_posts'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" ), // phpcs:ignore
		'spam_comments'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" ), // phpcs:ignore
		'trashed_comments'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" ), // phpcs:ignore
	);
	$wpdb->suppress_errors( false );

	return array(
		'variables' => $vars,
		'status'    => $status,
		'derived'   => $derived,
		'totals'    => array(
			'tables'   => $tot['count'],
			'rows'     => $tot['rows'],
			'data_mb'  => round( $tot['data'] / 1048576, 2 ),
			'index_mb' => round( $tot['index'] / 1048576, 2 ),
			'free_mb'  => round( $tot['free'] / 1048576, 2 ),
		),
		'tables'    => $tables,
		'cleanup'   => $clean,
	);
}

/** Option adından sahibini tahmin eder (FluxWP paketleri + yaygın eklentiler). */
function flxp_option_owner( $name ) {
	$map = array(
		'_transient_'       => 'Transient',
		'_site_transient_'  => 'Transient',
		'woocommerce_'      => 'WooCommerce',
		'wc_'               => 'WooCommerce',
		'litespeed'         => 'LiteSpeed Cache',
		'flxb_'              => 'Flux Donate',
		'flxf_'              => 'Flux Forms',
		'emsfb'             => 'Flux Forms',
		'flxs_'              => 'Flux Certificates',
		'flxi_'              => 'Flux Mail',
		'flxc_'              => 'Flux Chat',
		'flxg_'              => 'Flux Tasks',
		'flxu_'              => 'Flux Updater',
		'flxy_'              => 'Flux Backup',
		'flxseo_'            => 'Flux SEO & MCP',
		'flxa_'              => 'Flux Admin',
		'flxai_'             => 'Flux AI',
		'flxl_'              => 'Flux Lang',
		'flxp_'              => 'Flux Speed',
		'flx_'               => 'FluxWP',
		'theme_mods_'       => 'Tema',
		'widget_'           => 'Widget',
		'elementor'         => 'Elementor',
		'wpseo'             => 'Yoast SEO',
		'rank_math'         => 'Rank Math',
		'jetpack'           => 'Jetpack',
	);
	foreach ( $map as $p => $label ) {
		if ( 0 === strpos( $name, $p ) ) {
			return $label;
		}
	}
	return '';
}

/** Autoload yüklenen seçenekler: toplam ve en büyükler. */
function flxp_autoload( $limit = 30 ) {
	global $wpdb;
	$in    = "('yes','on','auto-on','auto')";
	$total = $wpdb->get_row( "SELECT COUNT(*) AS n, SUM(LENGTH(option_value)) AS bytes FROM {$wpdb->options} WHERE autoload IN {$in}", ARRAY_A ); // phpcs:ignore
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, LENGTH(option_value) AS bytes, autoload FROM {$wpdb->options} WHERE autoload IN {$in} ORDER BY bytes DESC LIMIT %d", max( 1, (int) $limit ) ), ARRAY_A ); // phpcs:ignore
	$out   = array();
	foreach ( (array) $rows as $r ) {
		$out[] = array(
			'name'  => $r['option_name'],
			'kb'    => round( (int) $r['bytes'] / 1024, 1 ),
			'owner' => flxp_option_owner( $r['option_name'] ),
		);
	}
	return array(
		'count'    => (int) ( $total['n'] ?? 0 ),
		'total_kb' => round( (float) ( $total['bytes'] ?? 0 ) / 1024, 1 ),
		'largest'  => $out,
	);
}

/** Zamanlanmış görevler. */
function flxp_cron() {
	$cron  = _get_cron_array();
	$now   = time();
	$items = array();
	$late  = 0;
	if ( is_array( $cron ) ) {
		foreach ( $cron as $ts => $hooks ) {
			foreach ( (array) $hooks as $hook => $events ) {
				foreach ( (array) $events as $e ) {
					$overdue = $ts < $now - 10 * MINUTE_IN_SECONDS;
					$late   += $overdue ? 1 : 0;
					$items[] = array(
						'hook'     => (string) $hook,
						'next'     => (int) $ts,
						'in_s'     => (int) $ts - $now,
						'schedule' => empty( $e['schedule'] ) ? '' : (string) $e['schedule'],
						'interval' => isset( $e['interval'] ) ? (int) $e['interval'] : 0,
						'args'     => count( (array) ( $e['args'] ?? array() ) ),
						'overdue'  => $overdue,
					);
				}
			}
		}
	}
	return array(
		'disabled'  => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
		'last_seen' => max( (int) get_option( 'flxp_cron_seen', 0 ), (int) get_option( 'flx_cron_last', 0 ) ),
		'events'    => count( $items ),
		'overdue'   => $late,
		'items'     => $items,
	);
}

/** Güvenli temizlik işleri: anahtar → etiket. İçerik silen (revizyon, çöp yazı) iş yok. */
function flxp_cleanup_jobs() {
	return array(
		'expired_transients' => __( 'Süresi dolmuş geçici veriler', 'flx-speed' ),
		'auto_drafts'        => __( '7 günden eski otomatik taslaklar', 'flx-speed' ),
		'spam_comments'      => __( 'İstenmeyen yorumlar', 'flx-speed' ),
		'trashed_comments'   => __( 'Çöpteki yorumlar', 'flx-speed' ),
		'optimize'           => __( 'Boş alanı geri kazan (OPTIMIZE)', 'flx-speed' ),
	);
}

/**
 * Temizlik işini çalıştırır, silinen satır sayısını döner.
 *
 * @param string $job flxp_cleanup_jobs() anahtarı.
 * @return int|WP_Error
 */
function flxp_cleanup( $job ) {
	global $wpdb;
	$n = 0;
	switch ( $job ) {
		case 'expired_transients':
			$before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) );
			delete_expired_transients( true );
			$n = $before;
			break;
		case 'auto_drafts':
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_date < %s LIMIT 500", gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) );
			foreach ( $ids as $id ) {
				$n += wp_delete_post( (int) $id, true ) ? 1 : 0;
			}
			break;
		case 'spam_comments':
		case 'trashed_comments':
			$st  = 'spam_comments' === $job ? 'spam' : 'trash';
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = %s LIMIT 1000", $st ) );
			foreach ( $ids as $id ) {
				$n += wp_delete_comment( (int) $id, true ) ? 1 : 0;
			}
			break;
		case 'optimize':
			// Yalnız boş alanı 1 MB'tan büyük kendi tablolarımız; büyük tabloda kilit uzamasın diye en fazla 10.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $wpdb->base_prefix ) . '%' ), ARRAY_A );
			foreach ( (array) $rows as $t ) {
				if ( $n < 10 && (int) $t['Data_free'] > 1048576 ) {
					$wpdb->query( 'OPTIMIZE TABLE `' . esc_sql( $t['Name'] ) . '`' ); // phpcs:ignore
					++$n;
				}
			}
			break;
		default:
			return new WP_Error( 'flxp_job', __( 'Bilinmeyen temizlik işi.', 'flx-speed' ) );
	}
	return $n;
}

/** Cron gerçekten çalışıyor mu: her cron isteğinde zamanı yazar (en fazla dakikada bir). */
add_action(
	'wp_loaded',
	function () {
		if ( wp_doing_cron() && (int) get_option( 'flxp_cron_seen', 0 ) < time() - 60 ) {
			update_option( 'flxp_cron_seen', time(), false );
		}
	}
);
