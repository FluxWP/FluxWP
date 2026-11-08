<?php
/**
 * Sistem kontrolü (filter `flx_system_checks`). Dış HTTP isteği yapmaz.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		return array_merge( is_array( $checks ) ? $checks : array(), flxsm_system_checks() );
	}
);

add_filter(
	'flx_system_checks_versions',
	function ( $v ) {
		$v                = (array) $v;
		$v['flx-social'] = FLXSM_VERSION;
		return $v;
	}
);

function flxsm_system_checks() {
	global $wpdb;
	$out = array();
	$url = flxsm_app_url( '/settings' );
	$add = function ( $id, $label, $status, $message, $action = '', $action_label = '' ) use ( &$out ) {
		$c = array(
			'id'      => 'flx-social/' . $id,
			'source'  => 'Flux Social',
			'group'   => __( 'Social media', 'flx-social' ),
			'label'   => $label,
			'status'  => $status,
			'message' => $message,
		);
		if ( $action ) {
			$c['action_url']   = $action;
			$c['action_label'] = $action_label ? $action_label : __( 'Open', 'flx-social' );
		}
		$out[] = $c;
	};

	$missing = array();
	foreach ( array( 'accounts', 'posts', 'targets', 'metrics', 'log' ) as $t ) {
		$name = flxsm_table( $t );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ) !== $name ) { // phpcs:ignore WordPress.DB
			$missing[] = $name;
		}
	}
	/* translators: %s: table names */
	$add( 'tables', __( 'Database tables', 'flx-social' ), $missing ? 'error' : 'ok', $missing ? sprintf( __( 'Missing table: %s. Deactivate and reactivate the plugin.', 'flx-social' ), implode( ', ', $missing ) ) : __( 'Tables are ready.', 'flx-social' ) );

	$sodium = function_exists( 'sodium_crypto_secretbox' );
	$add( 'crypto', __( 'Key encryption', 'flx-social' ), $sodium ? 'ok' : 'warning', $sodium ? __( 'Keys and tokens are stored encrypted (sodium).', 'flx-social' ) : __( 'The PHP sodium extension is off; keys are stored with a weaker method. Turn on sodium under Hostinger › PHP extensions.', 'flx-social' ) );

	$ready = array();
	$none  = array();
	foreach ( flxsm_apps_public() as $a ) {
		if ( 'whatsapp' === $a['id'] ) {
			continue;
		}
		if ( $a['ready'] ) {
			$ready[] = $a['label'];
		} else {
			$none[] = $a['label'];
		}
	}
	/* translators: %s: app names */
	$add( 'apps', __( 'Network apps', 'flx-social' ), $ready ? 'ok' : 'warning', $ready ? sprintf( __( 'Keys entered: %s.', 'flx-social' ), implode( ', ', $ready ) ) : __( 'No network app keys entered. Social Media › Settings › Apps.', 'flx-social' ), $url, __( 'Settings', 'flx-social' ) );

	$accs = flxsm_accounts();
	$bad  = array_filter( $accs, fn( $a ) => 'active' !== $a['status'] );
	if ( ! $accs ) {
		$add( 'accounts', __( 'Connected accounts', 'flx-social' ), 'info', __( 'No accounts connected yet.', 'flx-social' ), flxsm_app_url( '/accounts' ), __( 'Accounts', 'flx-social' ) );
	} elseif ( $bad ) {
		/* translators: %s: account names */
		$add( 'accounts', __( 'Connected accounts', 'flx-social' ), 'warning', sprintf( __( 'Accounts that need reconnecting: %s.', 'flx-social' ), implode( ', ', array_map( fn( $a ) => $a['network'] . ' › ' . $a['name'], $bad ) ) ), flxsm_app_url( '/accounts' ), __( 'Accounts', 'flx-social' ) );
	} else {
		/* translators: %d: count */
		$add( 'accounts', __( 'Connected accounts', 'flx-social' ), 'ok', sprintf( __( '%d accounts connected and active.', 'flx-social' ), count( $accs ) ) );
	}

	$beat  = (array) get_option( 'flxsm_last_tick', array() );
	$ext   = (int) ( $beat['external'] ?? 0 );
	$any   = (int) ( $beat['any'] ?? 0 );
	$due   = flxsm_due_count( -300 );
	$lerr  = (array) get_option( 'flxsm_loopback_err', array() );
	if ( $ext > time() - 10 * MINUTE_IN_SECONDS ) {
		$add( 'tick', __( 'Scheduled posts', 'flx-social' ), 'ok', __( 'External per-minute trigger is running.', 'flx-social' ) );
	} elseif ( $due ) {
		/* translators: %d: count */
		$add( 'tick', __( 'Scheduled posts', 'flx-social' ), 'error', sprintf( __( '%d posts have been waiting for more than 5 minutes. Add the trigger URL from Settings › General to cron-job.org (every minute).', 'flx-social' ), $due ), $url, __( 'Settings', 'flx-social' ) );
	} else {
		$msg = $any ? __( 'Posts are processed via WP-Cron and background requests; to publish on time, adding the trigger URL from Settings › General to cron-job.org (every minute) is recommended.', 'flx-social' ) : __( 'The trigger has never run. Add the trigger URL from Settings › General to cron-job.org (every minute).', 'flx-social' );
		if ( ! empty( $lerr['at'] ) && $lerr['at'] > time() - DAY_IN_SECONDS ) {
			$msg .= ' ' . __( 'Background request error:', 'flx-social' ) . ' ' . $lerr['msg'];
		}
		$add( 'tick', __( 'Scheduled posts', 'flx-social' ), 'warning', $msg, $url, __( 'Settings', 'flx-social' ) );
	}

	$failed = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxsm_table( 'targets' ) . " WHERE status = 'failed' AND updated > %d", time() - 7 * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DB
	/* translators: %d: count */
	$add( 'failed', __( 'Failed shares', 'flx-social' ), $failed ? 'warning' : 'ok', $failed ? sprintf( __( '%d shares failed in the last 7 days. Posts › Failed.', 'flx-social' ), $failed ) : __( 'No failed shares in the last 7 days.', 'flx-social' ), $failed ? flxsm_app_url( '/posts?status=failed' ) : '' );

	$https = 0 === strpos( home_url(), 'https:' );
	$add( 'https', __( 'Media URLs', 'flx-social' ), $https ? 'ok' : 'error', $https ? __( 'Instagram, TikTok and Facebook fetch images from the site\'s HTTPS address.', 'flx-social' ) : __( 'The site is not HTTPS; Instagram/TikTok/Facebook cannot fetch images and videos.', 'flx-social' ) );

	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'flxsm';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	$ok = is_dir( $dir ) && wp_is_writable( $dir );
	$add( 'uploads', __( 'Files folder', 'flx-social' ), $ok ? 'ok' : 'warning', $ok ? __( 'wp-content/uploads/flxsm is writable (JPEG copies for Instagram).', 'flx-social' ) : __( 'wp-content/uploads/flxsm is not writable; PNG/WebP images cannot be sent to Instagram.', 'flx-social' ) );

	$add( 'ai', __( 'AI writing help', 'flx-social' ), flxsm_ai_ready() ? 'ok' : 'info', flxsm_ai_ready() ? __( 'Flux AI ready: writing, shortening, adapting to networks.', 'flx-social' ) : __( 'Flux AI & MCP is not installed or has no key; AI buttons are hidden, sharing still works.', 'flx-social' ), admin_url( 'admin.php?page=flx-ai-settings' ), 'Flux AI' );
	return $out;
}
