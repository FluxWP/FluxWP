<?php
/** Portable background worker library 1.2.0; bundled into each consuming plugin. */
defined( 'ABSPATH' ) || exit;
if ( ! function_exists( 'flx_background_worker_active' ) ) {
	/** Capability marker: old-first bundles safely retain serial execution. */
	function flx_background_scheduler_version() { return '1.2.0'; }
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
		$wake = '1' === getenv( 'FLX_BACKGROUND_WAKE' );
		if ( is_array( $lease ) && (bool) ( $lease['wake'] ?? false ) === $wake && (int) ( $lease['seen_at'] ?? 0 ) > $now - 15 && (int) ( $lease['expires_at'] ?? 0 ) > $now ) {
			return;
		}
		$interval = max( 5, min( 3600, (int) ( getenv( 'WP_CRON_INTERVAL' ) ?: 60 ) ) );
		update_option( 'flx_background_worker', array( 'seen_at' => $now, 'expires_at' => $now + max( 300, 2 * $interval + 120 ), 'wake' => $wake ), false );
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
		$owner = flx_background_cron_lock();
		if ( ! $owner ) { return false; }
		try {
		wp_cache_delete( 'cron', 'options' );
		wp_cache_delete( 'alloptions', 'options' ); // Core cron may be autoloaded in each child.
		$when = time() + max( 1, (int) $delay );
		$next = wp_next_scheduled( $hook, $args );
		if ( $next && $next <= $when ) {
			flx_background_wake( $next );
			return true;
		}
		if ( $next ) {
			wp_unschedule_event( $next, $hook, $args );
		}
		$result = wp_schedule_single_event( $when, $hook, $args );
		if ( $result ) { flx_background_wake( $when ); }
		return $result;
		} finally { flx_background_unlock( 'cron-schedule', $owner ); }
	}

	/** An owner-bound option lease; compare-and-swap prevents stale-lock races. */
	function flx_background_lock( $name, $ttl ) {
		global $wpdb;
		$key = 'flx_bg_lock_' . md5( $name );
		$token = wp_generate_uuid4();
		$value = wp_json_encode( array( 'token' => $token, 'expires_at' => time() + max( 1, (int) $ttl ) ) );
		// add_option() can upsert after its optimistic cache check. An INSERT
		// IGNORE against the unique option_name index has exactly one winner.
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, $value ) );
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		if ( 1 === (int) $inserted ) {
			return $token;
		}
		wp_cache_delete( $key, 'options' );
		$old = get_option( $key, '' );
		$data = json_decode( (string) $old, true );
		if ( is_array( $data ) && (int) ( $data['expires_at'] ?? 0 ) > time() ) {
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

// Additive APIs have their own guard: an older bundle may have loaded first.
if ( ! function_exists( 'flx_background_run' ) ) {
	/**
	 * Execute one bounded batch using the shared worker/lease policy.
	 *
	 * The consumer owns its durable items, budget and retry policy. A callback
	 * exception propagates after releasing the lease; it is never treated as success.
	 * Options: ttl, hook, args, delay, on_defer and on_locked (optional callbacks).
	 */
	function flx_background_run( $name, callable $callback, $options = array() ) {
		if ( ! empty( $options['hook'] ) && FLX_Background_Pool::dispatch( $options['hook'], $options['args'] ?? array() ) ) {
			return array( 'processed' => 0, 'deferred' => true, 'parallel' => true );
		}
		$handoff = static function () use ( $options ) {
			if ( ! empty( $options['hook'] ) ) {
				flx_background_schedule( $options['hook'], $options['args'] ?? array(), $options['delay'] ?? 30 );
			}
		};
		if ( flx_background_should_defer() ) {
			$handoff();
			return isset( $options['on_defer'] ) ? call_user_func( $options['on_defer'] ) : array( 'processed' => 0, 'deferred' => true );
		}
		$token = flx_background_lock( $name, $options['ttl'] ?? 120 );
		if ( ! $token ) {
			$handoff();
			return isset( $options['on_locked'] ) ? call_user_func( $options['on_locked'] ) : array( 'processed' => 0, 'locked' => true );
		}
		try {
			return $callback();
		} finally {
			flx_background_unlock( $name, $token );
		}
	}
}
if ( ! function_exists( 'flx_background_cron_lock' ) ) {
	/** Empty due-time signals only; the cron event remains the durable source. */
	function flx_background_wake( $when ) {
		if ( ! flx_background_worker_active() ) { return; }
		$lease = get_option( 'flx_background_worker', array() );
		if ( ! ( $lease['wake'] ?? false ) && '1' !== getenv( 'FLX_BACKGROUND_WAKE' ) ) { return; }
		$directory = WP_CONTENT_DIR . '/.flx-background-wake';
		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) { return; }
		@touch( $directory . '/' . max( 1, (int) $when ) );
	}
	function flx_background_cron_lock() {
		$until = microtime( true ) + 2;
		do { $owner = flx_background_lock( 'cron-schedule', 15 ); if ( $owner ) { return $owner; } usleep( 5000 ); } while ( microtime( true ) < $until );
		return false;
	}
	function flx_background_unschedule( $hook, $args ) {
		$owner = flx_background_cron_lock(); if ( ! $owner ) { return false; }
		try { wp_cache_delete( 'cron', 'options' ); wp_cache_delete( 'alloptions', 'options' ); return wp_clear_scheduled_hook( $hook, $args ); }
		finally { flx_background_unlock( 'cron-schedule', $owner ); }
	}
}
require_once __DIR__ . '/flx-background-parallel.php';
