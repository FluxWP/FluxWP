<?php
/**
 * Ortak Sistem Kontrolü: Flux Speed'ın satırları (flx_system_checks), yapay zekâ düzeltmeleri
 * (flx_system_fixes) ve ekranın yeni yeri (Performans › Sistem Kontrolü). Kontroller ucuz, dış HTTP yok.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		$src    = __( 'Flux Performance', 'flx-speed' );
		$page   = admin_url( 'admin.php?page=flx-speed&tab=records' );
		$s      = flxp_settings();

		if ( (int) get_option( 'flxp_db_version', 0 ) < 1 ) {
			$checks[] = array(
				'id'      => 'flxp.table',
				'source'  => $src,
				'group'   => 'Plugins',
				'label'   => __( 'Records table', 'flx-speed' ),
				'status'  => 'error',
				'message' => __( 'The table is not installed. Deactivate and reactivate the plugin.', 'flx-speed' ),
			);
			return $checks;
		}

		$checks[] = array(
			'id'           => 'flxp.record',
			'source'       => $src,
			'group'        => 'Plugins',
			'label'        => __( 'Request recording', 'flx-speed' ),
			'status'       => $s['record'] ? 'ok' : 'info',
			'message'      => $s['record']
				/* translators: 1: örnekleme yüzdesi, 2: yavaş eşiği ms */
				? sprintf( __( 'On: %1$d%% of requests are sampled; requests over %2$d ms and failing requests are recorded.', 'flx-speed' ), (int) $s['sample'], (int) $s['slow_ms'] )
				: __( 'Off: only the live panel works; AI cannot read history.', 'flx-speed' ),
			'action_url'   => admin_url( 'admin.php?page=flx-speed&tab=settings' ),
			'action_label' => __( 'Settings', 'flx-speed' ),
		);

		$fatal  = FLXP_Store::count_flag( 'fatal', 24 );
		$errors = FLXP_Store::count_flag( 'error', 24 ) + $fatal + FLXP_Store::count_flag( '5xx', 24 );
		$checks[] = array(
			'id'           => 'flxp.errors_24h',
			'source'       => $src,
			'group'        => 'Plugins',
			'label'        => __( 'Failing requests in the last 24 hours', 'flx-speed' ),
			'status'       => $fatal ? 'error' : ( $errors ? 'warning' : 'ok' ),
			'message'      => $errors
				/* translators: 1: hatalı istek sayısı, 2: ölümcül hata sayısı */
				? sprintf( __( '%1$d requests with PHP/server errors, %2$d fatal. See Records.', 'flx-speed' ), $errors, $fatal )
				: __( 'No recorded errors.', 'flx-speed' ),
			'action_url'   => add_query_arg( array( 'hours' => 24, 'flag' => $fatal ? 'fatal' : 'error' ), $page ),
			'action_label' => __( 'Records', 'flx-speed' ),
		);

		$slow     = FLXP_Store::count_flag( 'slow', 24 );
		$checks[] = array(
			'id'           => 'flxp.slow_24h',
			'source'       => $src,
			'group'        => 'Plugins',
			'label'        => __( 'Slow requests in the last 24 hours', 'flx-speed' ),
			'status'       => $slow > 20 ? 'warning' : ( $slow ? 'info' : 'ok' ),
			/* translators: 1: sayı, 2: eşik ms */
			'message'      => sprintf( __( '%1$d requests exceeded %2$d ms.', 'flx-speed' ), $slow, (int) $s['slow_ms'] ),
			'action_url'   => add_query_arg( array( 'hours' => 24, 'flag' => 'slow', 'order' => 'slow' ), $page ),
			'action_label' => __( 'Records', 'flx-speed' ),
		);

		$http     = FLXP_Store::count_flag( 'http_fail', 24 );
		$checks[] = array(
			'id'           => 'flxp.http_24h',
			'source'       => $src,
			'group'        => 'Plugins',
			'label'        => __( 'Failed HTTP call', 'flx-speed' ),
			'status'       => $http ? 'warning' : 'ok',
			/* translators: %d: sayı */
			'message'      => $http ? sprintf( __( '%d recorded requests had external service errors in the last 24 hours.', 'flx-speed' ), $http ) : __( 'No recorded external service errors.', 'flx-speed' ),
			'action_url'   => add_query_arg( array( 'hours' => 24, 'flag' => 'http_fail' ), $page ),
			'action_label' => __( 'Records', 'flx-speed' ),
		);

		if ( FLXP_Collector::$sq_preset ) {
			$checks[] = array(
				'id'      => 'flxp.savequeries',
				'source'  => $src,
				'group'   => 'Config',
				'label'   => 'SAVEQUERIES',
				'status'  => 'warning',
				'message' => __( 'SAVEQUERIES is on in wp-config.php: every request keeps all queries in memory. Flux Performance turns it on itself when needed; remove it from wp-config.', 'flx-speed' ),
			);
		}

		$tools    = function_exists( 'flxseo_mcp_tools' ) ? flxseo_mcp_tools() : array();
		$checks[] = array(
			'id'      => 'flxp.mcp',
			'source'  => $src,
			'group'   => 'Plugins',
			'label'   => __( 'AI tools (flxp_*)', 'flx-speed' ),
			'status'  => isset( $tools['flxp_summary'] ) ? 'ok' : 'info',
			'message' => isset( $tools['flxp_summary'] )
				? __( 'MCP and Flux AI chat can read the records.', 'flx-speed' )
				: __( 'Flux AI & MCP is not active or MCP is off; tools are not visible.', 'flx-speed' ),
		);
		// Tema kendi cron satırlarını (theme.cron.*) veriyorsa aynı şeyi iki kez gösterme.
		$own = array_filter(
			flxp_server_checks(),
			function ( $c ) {
				return ! ( 'flxp.cron' === $c['id'] && function_exists( 'flx_sk_item' ) );
			}
		);
		return array_merge( $checks, array_values( $own ) );
	}
);

/**
 * Sunucu ve veritabanı sağlığı: autoload, cron, disk, yük, OPcache, süresi dolmuş geçici veriler.
 * Özet'teki "Öneriler" ve Başlangıç widget'ı da bunları kullanır.
 */
function flxp_server_checks() {
	global $wpdb;
	$src  = __( 'Flux Performance', 'flx-speed' );
	$url  = function ( $tab ) {
		return admin_url( 'admin.php?page=flx-speed&tab=' . $tab );
	};
	$out  = array();
	$live = flxp_server_live();

	$auto  = $wpdb->get_row( "SELECT COUNT(*) AS n, SUM(LENGTH(option_value)) AS b FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto')", ARRAY_A ); // phpcs:ignore
	$kb    = (int) round( (float) ( $auto['b'] ?? 0 ) / 1024 );
	$out[] = array(
		'id'           => 'flxp.autoload',
		'source'       => $src,
		'group'        => 'Performance',
		'label'        => __( 'Autoloaded options', 'flx-speed' ),
		'status'       => $kb > 1600 ? 'error' : ( $kb > 800 ? 'warning' : 'ok' ),
		/* translators: 1: KB, 2: adet */
		'message'      => sprintf( __( '%1$d KB (%2$d options) is loaded on every request.', 'flx-speed' ), $kb, (int) ( $auto['n'] ?? 0 ) ) . ( $kb > 800 ? ' ' . __( 'Under 800 KB is recommended; check the largest rows.', 'flx-speed' ) : '' ),
		'action_url'   => $url( 'autoload' ),
		'action_label' => 'Autoload',
	);

	$cron  = flxp_cron();
	$stale = ! $cron['last_seen'] || $cron['last_seen'] < time() - HOUR_IN_SECONDS;
	$msg   = $cron['last_seen']
		/* translators: %s: süre */
		? sprintf( __( 'Last run %s.', 'flx-speed' ), flx_time_ago( $cron['last_seen'] ) )
		: __( 'Cron has not been seen running yet.', 'flx-speed' );
	if ( $cron['overdue'] ) {
		/* translators: %d: adet */
		$msg .= ' ' . sprintf( __( '%d events overdue.', 'flx-speed' ), $cron['overdue'] );
	}
	$out[] = array(
		'id'           => 'flxp.cron',
		'source'       => $src,
		'group'        => 'Cron',
		'label'        => __( 'Scheduled events are running', 'flx-speed' ),
		'status'       => ( $stale && $cron['overdue'] ) ? 'error' : ( ( $stale || $cron['overdue'] > 5 ) ? 'warning' : 'ok' ),
		'message'      => $msg,
		'action_url'   => $url( 'cron' ),
		'action_label' => 'Cron',
	);

	if ( null !== $live['disk_pct'] ) {
		$out[] = array(
			'id'           => 'flxp.disk',
			'source'       => $src,
			'group'        => 'Performance',
			'label'        => __( 'Disk space', 'flx-speed' ),
			'status'       => $live['disk_pct'] >= 95 ? 'error' : ( $live['disk_pct'] >= 90 ? 'warning' : 'ok' ),
			/* translators: 1: yüzde, 2: boş alan */
			'message'      => sprintf( __( '%1$d%% used, %2$s free.', 'flx-speed' ), $live['disk_pct'], size_format( (float) $live['disk_free'], 1 ) ) . ( $live['disk_shared'] ? ' ' . __( '(account usage / quota from Settings)', 'flx-speed' ) : '' ),
			'action_url'   => $url( 'server' ),
			'action_label' => __( 'Server', 'flx-speed' ),
		);
	}

	if ( null !== $live['load_pct'] ) {
		$out[] = array(
			'id'           => 'flxp.load',
			'source'       => $src,
			'group'        => 'Performance',
			'label'        => __( 'CPU load', 'flx-speed' ),
			'status'       => $live['load_pct'] >= 150 ? 'warning' : ( $live['load_pct'] >= 100 ? 'info' : 'ok' ),
			'message'      => 'account' === $live['scope']
				/* translators: 1: yüzde, 2: çekirdek sınırı */
				? sprintf( __( '%1$d%% of the account CPU limit in use (%2$s cores).', 'flx-speed' ), $live['load_pct'], number_format_i18n( $live['cores'], 1 ) )
				/* translators: 1: yüzde, 2: çekirdek sayısı */
				: sprintf( __( '%1$d%% over the last minute (%2$d cores).', 'flx-speed' ), $live['load_pct'], $live['cores'] ),
			'action_url'   => $url( 'server' ),
			'action_label' => __( 'Server', 'flx-speed' ),
		);
	}

	$opc = function_exists( 'opcache_get_status' ) ? @opcache_get_status( false ) : false; // phpcs:ignore
	$on  = is_array( $opc ) && ! empty( $opc['opcache_enabled'] );
	$out[] = array(
		'id'           => 'flxp.opcache',
		'source'       => $src,
		'group'        => 'Performance',
		'label'        => 'OPcache',
		'status'       => $on ? 'ok' : ( false === $opc && ini_get( 'opcache.enable' ) ? 'info' : 'warning' ),
		'message'      => $on
			? __( 'On: compiled PHP files are kept in memory.', 'flx-speed' )
			: ( false === $opc && ini_get( 'opcache.enable' ) ? __( 'Looks enabled but its status cannot be read (restricted by the server).', 'flx-speed' ) : __( 'Off: PHP files are compiled again on every request. Turn it on in Hostinger › PHP configuration.', 'flx-speed' ) ),
		'action_url'   => $url( 'server' ),
		'action_label' => __( 'Server', 'flx-speed' ),
	);

	$exp   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', time() ) );
	$out[] = array(
		'id'           => 'flxp.transients',
		'source'       => $src,
		'group'        => 'Performance',
		'label'        => __( 'Expired transients', 'flx-speed' ),
		'status'       => $exp > 500 ? 'warning' : 'ok',
		/* translators: %d: adet */
		'message'      => $exp ? sprintf( __( '%d expired transients are still in the table.', 'flx-speed' ), $exp ) : __( 'None.', 'flx-speed' ),
		'action_url'   => $url( 'database' ),
		'action_label' => __( 'Database', 'flx-speed' ),
	);
	return $out;
}

/** Yapay zekânın (Flux AI & MCP system_fix) güvenle çalıştırabileceği temizlik. */
add_filter(
	'flx_system_fixes',
	function ( $fixes ) {
		$fixes = is_array( $fixes ) ? $fixes : array();
		$fixes['flxp_expired_transients'] = array(
			'label'       => __( 'Delete expired transients', 'flx-speed' ),
			'description' => 'Süresi dolmuş transient satırlarını siler (delete_expired_transients). Geri alınması gerekmez; eklentiler gerekirse yeniden oluşturur.',
			'checks'      => array( 'flxp.transients' ),
			'source'      => 'Flux Speed',
			'run'         => function () {
				/* translators: %d: adet */
				return sprintf( __( '%d transients deleted.', 'flx-speed' ), (int) flxp_cleanup( 'expired_transients' ) );
			},
		);
		return $fixes;
	}
);

/*
 * Sistem Kontrolü ekranı artık burada: ortak uyarı (Başlangıç + üst çubuk) bu sayfaya bağlanır.
 * Tema 20 öncelikle kendi ekranını verir; 30 ile Flux Speed kazanır.
 */
function flxp_system_checks_url() {
	return admin_url( 'admin.php?page=flx-speed&tab=system' );
}
add_filter( 'flx_system_checks_page_url', 'flxp_system_checks_url', 30 );
add_filter(
	'flx_system_checks_versions',
	function ( $v ) {
		$v                  = (array) $v;
		$v['flx-speed'] = FLXP_VERSION;
		return $v;
	}
);
add_filter(
	'flx_system_checks_labels',
	function ( $l ) {
		return array_merge(
			(array) $l,
			array(
				'title'       => __( 'System check', 'flx-speed' ),
				'intro'       => __( 'Each line shows something the site needs and whether it is in place. Fix the errors first; warnings are worth a look.', 'flx-speed' ),
				'ok'          => __( 'OK', 'flx-speed' ),
				'warning'     => __( 'Warning', 'flx-speed' ),
				'error'       => __( 'Errors', 'flx-speed' ),
				'info'        => __( 'Info', 'flx-speed' ),
				/* translators: 1: tamam, 2: uyarı, 3: hata sayısı */
				'summary'     => __( '%1$d OK · %2$d warnings · %3$d errors', 'flx-speed' ),
				'recheck'     => __( 'Check again', 'flx-speed' ),
				'filter_hint' => __( 'Show only these (click again to show all)', 'flx-speed' ),
				/* translators: %s: saat */
				'checked'     => __( 'Checked: %s', 'flx-speed' ),
				'col_status'  => __( 'Status', 'flx-speed' ),
				'col_check'   => __( 'Check', 'flx-speed' ),
				'col_detail'  => __( 'Details', 'flx-speed' ),
				'col_source'  => __( 'Source', 'flx-speed' ),
				'col_action'  => __( 'Action', 'flx-speed' ),
				'none'        => __( 'No checks are registered.', 'flx-speed' ),
				/* translators: %d: hata sayısı */
				'notice'      => __( 'System check found %d problem(s) that can stop donations or automations.', 'flx-speed' ),
				'notice_link' => __( 'Open system check', 'flx-speed' ),
				'dismiss'     => __( 'Hide until the next update', 'flx-speed' ),
				/* translators: %d: hata sayısı */
				'bar'         => __( 'System: %d error(s)', 'flx-speed' ),
				/* translators: %s: hata mesajı */
				'broken'      => __( 'This check could not run: %s', 'flx-speed' ),
				'groups'      => array(
					'Cron'        => __( 'Scheduled tasks (cron)', 'flx-speed' ),
					'Payments'    => __( 'Payments', 'flx-speed' ),
					'Plugins'     => __( 'Plugins', 'flx-speed' ),
					'Config'      => __( 'Settings', 'flx-speed' ),
					'Email'       => __( 'E-mail', 'flx-speed' ),
					'Security'    => __( 'Security', 'flx-speed' ),
					'Forms'       => __( 'Forms', 'flx-speed' ),
					'Performance' => __( 'Performance', 'flx-speed' ),
					'Other'       => __( 'Other', 'flx-speed' ),
				),
			)
		);
	},
	20
);
if ( is_admin() ) {
	add_action( 'init', 'flx_system_checks_boot' );
}
