<?php
/**
 * Sistem kontrolü: FluxWP eklenti/tema ortak sözleşmesi (filter `flx_system_checks`).
 * Her kontrol ucuzdur, dış HTTP isteği yapmaz.
 *
 * @package FLX_Gorev
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'flx_system_checks', 'flxg_system_checks_filter' );

function flxg_system_checks_filter( $checks ) {
	return array_merge( is_array( $checks ) ? $checks : array(), flxg_system_checks() );
}

/** @return array[] {id, source, group, label, status ok|warning|error|info, message, action_url?, action_label?} */
function flxg_system_checks() {
	global $wpdb;
	$src      = 'Flux Tasks';
	$settings = admin_url( 'admin.php?page=flx-tasks-ayarlar' );
	$out      = array();
	$add      = function ( $id, $group, $label, $status, $message, $url = '', $url_label = '' ) use ( &$out, $src ) {
		$c = array(
			'id'      => 'flx-tasks/' . $id,
			'source'  => $src,
			'group'   => $group,
			'label'   => $label,
			'status'  => $status,
			'message' => $message,
		);
		if ( $url ) {
			$c['action_url']   = $url;
			$c['action_label'] = $url_label ? $url_label : __( 'Open', 'flx-tasks' );
		}
		$out[] = $c;
	};
	$g_sys = __( 'Setup', 'flx-tasks' );

	// Sürümler.
	$wp_ok  = version_compare( get_bloginfo( 'version' ), '6.5', '>=' );
	$php_ok = version_compare( PHP_VERSION, '7.4', '>=' );
	/* translators: 1: WordPress version, 2: PHP version */
	$add( 'versions', $g_sys, __( 'WordPress and PHP version', 'flx-tasks' ), $wp_ok && $php_ok ? 'ok' : 'error', sprintf( __( 'WordPress %1$s, PHP %2$s. Required: WordPress 6.5+, PHP 7.4+.', 'flx-tasks' ), get_bloginfo( 'version' ), PHP_VERSION ) );

	// Tablolar.
	$need    = array( 'spaces', 'lists', 'tasks', 'comments', 'files', 'activity', 'notifications' );
	$missing = array();
	foreach ( $need as $t ) {
		$name = flxg_table( $t );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ) !== $name ) { // phpcs:ignore
			$missing[] = $name;
		}
	}
	if ( $missing ) {
		/* translators: %s: table names */
		$add( 'tables', $g_sys, __( 'Database tables', 'flx-tasks' ), 'error', sprintf( __( 'Missing table: %s. Deactivate and reactivate the plugin.', 'flx-tasks' ), implode( ', ', $missing ) ), admin_url( 'plugins.php' ), __( 'Plugins', 'flx-tasks' ) );
	} else {
		/* translators: %s: db version */
		$add( 'tables', $g_sys, __( 'Database tables', 'flx-tasks' ), get_option( 'flxg_db_version' ) === FLXG_DB_VERSION ? 'ok' : 'warning', sprintf( __( 'Tables are ready (schema %s).', 'flx-tasks' ), (string) get_option( 'flxg_db_version' ) ) );
	}

	// Dosya klasörü.
	$up  = wp_upload_dir( null, false );
	$dir = trailingslashit( $up['basedir'] ) . 'flx-tasks';
	if ( ! is_dir( $dir ) || ! wp_is_writable( $dir ) ) {
		$add( 'uploads', $g_sys, __( 'File folder', 'flx-tasks' ), 'error', __( 'wp-content/uploads/flx-tasks is missing or not writable. Files and images cannot be uploaded; check folder permissions (755).', 'flx-tasks' ) );
	} elseif ( ! file_exists( $dir . '/.htaccess' ) ) {
		$add( 'uploads', $g_sys, __( 'File folder', 'flx-tasks' ), 'warning', __( 'The folder is writable but the protection file (.htaccess) is missing; files can be opened by direct URL. Reactivate the plugin.', 'flx-tasks' ) );
	} else {
		$add( 'uploads', $g_sys, __( 'File folder', 'flx-tasks' ), 'ok', __( 'Writable and closed to direct access.', 'flx-tasks' ) );
	}

	// Yükleme sınırı.
	$set_mb = (int) flxg_setting( 'max_upload_mb' );
	$php_b  = (int) wp_max_upload_size();
	/* translators: 1: effective limit, 2: server limit */
	$add( 'upload_limit', $g_sys, __( 'Upload limit', 'flx-tasks' ), $set_mb * MB_IN_BYTES > $php_b ? 'warning' : 'ok', sprintf( __( 'Effective limit %1$s (server limit %2$s).', 'flx-tasks' ), size_format( flxg_max_upload_bytes() ), size_format( $php_b ) ) . ( $set_mb * MB_IN_BYTES > $php_b ? ' ' . __( 'The setting is larger than the server limit; upload_max_filesize and post_max_size can be raised in the hosting panel.', 'flx-tasks' ) : '' ), $settings, __( 'Settings', 'flx-tasks' ) );

	// Zamanlanmış işler (WP-Cron).
	$g_cron = __( 'Scheduled jobs', 'flx-tasks' );
	$next   = wp_next_scheduled( 'flxg_daily' );
	if ( ! $next ) {
		$add( 'cron', $g_cron, __( 'Daily task reminders', 'flx-tasks' ), 'error', __( 'Not scheduled: overdue/upcoming task notifications and old temporary file cleanup will not run. Opening the Tasks page once reschedules it.', 'flx-tasks' ), admin_url( 'admin.php?page=flx-tasks' ), __( 'Tasks', 'flx-tasks' ) );
	} elseif ( $next < time() - 2 * HOUR_IN_SECONDS ) {
		/* translators: %s: relative time, e.g. "3 saat önce" */
		$add( 'cron', $g_cron, __( 'Daily task reminders', 'flx-tasks' ), 'warning', sprintf( __( 'The scheduled job is late (due %s). WP-Cron may not be triggering (if the site gets few visits, a real cron in the hosting panel is recommended).', 'flx-tasks' ), flx_time_ago( $next ) ) );
	} else {
		/* translators: %s: date */
		$add( 'cron', $g_cron, __( 'Daily task reminders', 'flx-tasks' ), 'ok', sprintf( __( 'Next run: %s.', 'flx-tasks' ), flx_date( $next, 'datetime' ) ) );
	}
	if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
		$add( 'cron_mode', $g_cron, __( 'WP-Cron mode', 'flx-tasks' ), 'info', __( 'DISABLE_WP_CRON is on: scheduled jobs only run if a server cron calls wp-cron.php. This cron must be set up in the hosting panel.', 'flx-tasks' ) );
	}

	// Canlı güncelleme ve önbellek.
	$g_live = __( 'Live updates', 'flx-tasks' );
	/* translators: %d: seconds */
	$add( 'poll', $g_live, __( 'Update interval', 'flx-tasks' ), 'info', sprintf( __( '10 s while on screen, %d s after 2 min idle; background tabs send no requests.', 'flx-tasks' ), max( 15, (int) flxg_setting( 'poll_interval' ) ) ), $settings, __( 'Settings', 'flx-tasks' ) );
	$add( 'cache', $g_live, __( 'API cache', 'flx-tasks' ), 'ok', defined( 'LSCWP_V' ) ? __( 'LiteSpeed Cache is active; task API responses are marked so they are not cached.', 'flx-tasks' ) : __( 'Task API responses are not cached.', 'flx-tasks' ) );

	// Bildirimler.
	$g_n = __( 'Notifications', 'flx-tasks' );
	$add( 'email', $g_n, __( 'Email notifications', 'flx-tasks' ), 'info', flxg_setting( 'email_notify' ) ? __( 'On. Emails are sent with WordPress\'s mail setup (an SMTP plugin is recommended).', 'flx-tasks' ) : __( 'Off; notifications are in-app only.', 'flx-tasks' ), $settings, __( 'Settings', 'flx-tasks' ) );

	// Yapay zekâ ve MCP.
	$g_ai = __( 'AI and MCP', 'flx-tasks' );
	$ai_url = admin_url( flxg_ai_hub() ? 'admin.php?page=flx-ai-settings' : 'plugins.php' );
	if ( flxg_ai_ready() ) {
		/* translators: %s: model id */
		$add( 'ai', $g_ai, __( 'AI', 'flx-tasks' ), 'ok', sprintf( __( 'Via Flux AI, model: %s.', 'flx-tasks' ), flxg_ai_model() ), $ai_url, __( 'Flux AI', 'flx-tasks' ) );
	} else {
		$add( 'ai', $g_ai, __( 'AI', 'flx-tasks' ), 'warning', flxg_ai_off_message(), $ai_url, flxg_ai_hub() ? __( 'Flux AI', 'flx-tasks' ) : __( 'Plugins', 'flx-tasks' ) );
	}
	if ( flxg_ai_hub() ) {
		$add( 'mcp', $g_ai, __( 'MCP', 'flx-tasks' ), 'info', __( 'Flux Tasks tools are available through Flux AI & MCP.', 'flx-tasks' ), admin_url( 'admin.php?page=flx-ai-mcp' ), __( 'Flux AI & MCP', 'flx-tasks' ) );
	} else {
		$add( 'mcp', $g_ai, __( 'MCP', 'flx-tasks' ), 'info', __( 'Flux Tasks tools require Flux AI & MCP.', 'flx-tasks' ), admin_url( 'plugins.php' ), __( 'Plugins', 'flx-tasks' ) );
	}

	return $out;
}
