<?php
/**
 * Ortam bilgisi: PHP, veritabanı, WordPress, sunucu. Gizli değer içermez.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

/** Ortam bilgisini döndürür. */
function flxp_environment() {
	global $wpdb;

	$ext = array();
	foreach ( array( 'curl', 'mysqli', 'mbstring', 'intl', 'json', 'openssl', 'zip', 'gd', 'imagick', 'exif', 'fileinfo', 'sodium', 'imap', 'mailparse', 'bcmath', 'gmp', 'soap', 'sockets', 'igbinary', 'msgpack', 'apcu', 'redis', 'memcached', 'Zend OPcache' ) as $e ) {
		$ext[ $e ] = extension_loaded( $e );
	}

	$opcache = function_exists( 'opcache_get_status' ) ? @opcache_get_status( false ) : false; // phpcs:ignore

	$plugins = array();
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$all = get_plugins();
	foreach ( (array) get_option( 'active_plugins', array() ) as $file ) {
		$plugins[] = array(
			'file'    => $file,
			'name'    => isset( $all[ $file ] ) ? $all[ $file ]['Name'] : $file,
			'version' => isset( $all[ $file ] ) ? $all[ $file ]['Version'] : '',
		);
	}
	$mu = array();
	foreach ( get_mu_plugins() as $file => $p ) {
		$mu[] = array( 'file' => $file, 'name' => $p['Name'], 'version' => $p['Version'] );
	}
	$dropins = array_keys( get_dropins() );

	$consts = array();
	foreach ( array( 'WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'SCRIPT_DEBUG', 'WP_CACHE', 'CONCATENATE_SCRIPTS', 'COMPRESS_SCRIPTS', 'DISABLE_WP_CRON', 'ALTERNATE_WP_CRON', 'WP_CRON_LOCK_TIMEOUT', 'EMPTY_TRASH_DAYS', 'WP_POST_REVISIONS', 'AUTOSAVE_INTERVAL', 'WP_MEMORY_LIMIT', 'WP_MAX_MEMORY_LIMIT', 'WP_ENVIRONMENT_TYPE', 'DISALLOW_FILE_EDIT', 'DISALLOW_FILE_MODS', 'FORCE_SSL_ADMIN', 'WP_AUTO_UPDATE_CORE', 'AUTOMATIC_UPDATER_DISABLED' ) as $c ) {
		$consts[ $c ] = defined( $c ) ? ( is_bool( constant( $c ) ) ? ( constant( $c ) ? 'true' : 'false' ) : (string) constant( $c ) ) : null;
	}
	$consts['SAVEQUERIES'] = FLXP_Collector::$sq_preset ? 'true (wp-config)' : ( defined( 'SAVEQUERIES' ) ? 'true (Flux Speed)' : null );

	$db_server = method_exists( $wpdb, 'db_server_info' ) ? $wpdb->db_server_info() : $wpdb->db_version();
	$db_size   = $wpdb->get_row( $wpdb->prepare( 'SELECT SUM(data_length + index_length) AS size, COUNT(*) AS tables FROM information_schema.TABLES WHERE table_schema = %s', DB_NAME ), ARRAY_A ); // phpcs:ignore
	$autoload  = $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto')" ); // phpcs:ignore

	$cron      = _get_cron_array();
	$cron_late = 0;
	if ( is_array( $cron ) ) {
		foreach ( $cron as $ts => $hooks ) {
			if ( $ts < time() - 10 * MINUTE_IN_SECONDS ) {
				$cron_late += count( $hooks );
			}
		}
	}

	return array(
		'php'       => array(
			'version'             => PHP_VERSION,
			'sapi'                => PHP_SAPI,
			'memory_limit'        => ini_get( 'memory_limit' ),
			'max_execution_time'  => ini_get( 'max_execution_time' ),
			'max_input_vars'      => ini_get( 'max_input_vars' ),
			'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
			'post_max_size'       => ini_get( 'post_max_size' ),
			'display_errors'      => ini_get( 'display_errors' ),
			'error_reporting'     => error_reporting(),
			'opcache'             => is_array( $opcache ) ? ! empty( $opcache['opcache_enabled'] ) : false,
			'extensions'          => $ext,
		),
		'database'  => array(
			'server'          => (string) $db_server,
			'client'          => function_exists( 'mysqli_get_client_info' ) ? mysqli_get_client_info() : '', // phpcs:ignore
			'charset'         => $wpdb->charset,
			'collate'         => $wpdb->collate,
			'prefix'          => $wpdb->prefix,
			'size_mb'         => $db_size ? round( (float) $db_size['size'] / 1048576, 1 ) : null,
			'tables'          => $db_size ? (int) $db_size['tables'] : null,
			'autoload_kb'     => round( (float) $autoload / 1024 ),
		),
		'wordpress' => array(
			'version'        => get_bloginfo( 'version' ),
			'locale'         => get_locale(),
			'timezone'       => wp_timezone_string(),
			'permalinks'     => (string) get_option( 'permalink_structure' ),
			'environment'    => wp_get_environment_type(),
			'multisite'      => is_multisite(),
			'object_cache'   => wp_using_ext_object_cache() ? 'external' : 'none',
			'theme'          => get_stylesheet(),
			'parent_theme'   => get_template() !== get_stylesheet() ? get_template() : '',
			'constants'      => $consts,
			'cron_events'    => is_array( $cron ) ? array_sum( array_map( 'count', $cron ) ) : 0,
			'cron_late'      => $cron_late,
			'plugins'        => $plugins,
			'mu_plugins'     => $mu,
			'dropins'        => $dropins,
		),
		'server'    => array(
			'software' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '',
			'os'       => PHP_OS_FAMILY,
			'https'    => is_ssl(),
			'litespeed'=> isset( $_SERVER['X-LSCACHE'] ) || ( isset( $_SERVER['SERVER_SOFTWARE'] ) && false !== stripos( (string) $_SERVER['SERVER_SOFTWARE'], 'litespeed' ) ), // phpcs:ignore
		),
	);
}
