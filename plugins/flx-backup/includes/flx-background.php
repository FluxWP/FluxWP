<?php
/** Portable background worker adapter 1.0.0; bundled into each consuming plugin. */
defined( 'ABSPATH' ) || exit;
if ( ! function_exists( 'flx_background_worker_active' ) ) {
	/** The Docker CLI worker already exports this operational marker. */
	function flx_background_worker_context() {
		return defined( 'WP_CLI' ) && WP_CLI && 'worker' === getenv( 'WP_CRON_MODE' );
	}

	function flx_background_worker_heartbeat() {
		if ( ! flx_background_worker_context() ) {
			return;
		}
		$now = time();
		$lease = get_option( 'flx_background_worker', array() );
		if ( is_array( $lease ) && (int) ( $lease['seen_at'] ?? 0 ) > $now - 15 && (int) ( $lease['expires_at'] ?? 0 ) > $now ) {
			return;
		}
		$interval = max( 5, min( 3600, (int) ( getenv( 'WP_CRON_INTERVAL' ) ?: 60 ) ) );
		update_option( 'flx_background_worker', array( 'seen_at' => $now, 'expires_at' => $now + max( 300, 2 * $interval + 120 ) ), false );
	}
	add_action( 'init', 'flx_background_worker_heartbeat', 99 );

	/** A stale or absent heartbeat always restores ordinary WordPress execution. */
	function flx_background_worker_active() {
		$lease = get_option( 'flx_background_worker', array() );
		$active = flx_background_worker_context() || ( is_array( $lease ) && (int) ( $lease['expires_at'] ?? 0 ) > time() );
		return (bool) apply_filters( 'flx_background_worker_active', $active );
	}

	function flx_background_should_defer() {
		flx_background_worker_heartbeat();
		return ! ( defined( 'WP_CLI' ) && WP_CLI ) && flx_background_worker_active();
	}

	/** Keep native cron as the durable handoff and as the no-worker fallback. */
	function flx_background_schedule( $hook, $args = array(), $delay = 1 ) {
		$when = time() + max( 1, (int) $delay );
		$next = wp_next_scheduled( $hook, $args );
		if ( $next && $next <= $when ) {
			return true;
		}
		if ( $next ) {
			wp_unschedule_event( $next, $hook, $args );
		}
		return wp_schedule_single_event( $when, $hook, $args );
	}

	/** An owner-bound option lease; compare-and-swap prevents stale-lock races. */
	function flx_background_lock( $name, $ttl ) {
		global $wpdb;
		$key = 'flx_bg_lock_' . md5( $name );
		$token = wp_generate_uuid4();
		$value = wp_json_encode( array( 'token' => $token, 'expires_at' => time() + max( 1, (int) $ttl ) ) );
		if ( add_option( $key, $value, '', false ) ) {
			return $token;
		}
		wp_cache_delete( $key, 'options' );
		$old = get_option( $key, '' );
		$data = json_decode( (string) $old, true );
		if ( ! is_array( $data ) || (int) ( $data['expires_at'] ?? 0 ) > time() ) {
			return false;
		}
		$claimed = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, $key, $old ) );
		wp_cache_delete( $key, 'options' );
		return 1 === (int) $claimed ? $token : false;
	}

	function flx_background_unlock( $name, $token ) {
		global $wpdb;
		$key = 'flx_bg_lock_' . md5( $name );
		wp_cache_delete( $key, 'options' );
		$value = get_option( $key, '' );
		$data = json_decode( (string) $value, true );
		if ( $token && is_array( $data ) && ( $data['token'] ?? '' ) === $token ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, $value ) );
			wp_cache_delete( $key, 'options' );
		}
	}
}
