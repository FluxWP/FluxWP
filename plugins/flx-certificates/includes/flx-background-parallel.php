<?php
/** Bounded subprocess pool for registered FluxWP worker cron hooks. */
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'FLX_Background_Pool', false ) ) {
	final class FLX_Background_Pool {
		private static $hooks = array();
		private static $running = array();
		private static $pending = array();
		private static $executing = false;
		public static function register( $hook, $plugin = 'shared' ) { self::$hooks[ $hook ] = sanitize_key( $plugin ); }
		public static function plugin_for( $hook ) { return self::$hooks[ $hook ] ?? 'shared'; }
		private static function configuration( $name, $default ) { $value = getenv( $name ); return false === $value || '' === $value ? $default : (int) $value; }
		public static function limit() { return max( 1, min( 64, (int) apply_filters( 'flx_background_concurrency', self::configuration( 'WP_BACKGROUND_MAX_PROCESSES', 9 ) ) ) ); }
		public static function plugin_limit( $plugin ) { return max( 1, min( 16, (int) apply_filters( 'flx_background_plugin_concurrency', self::configuration( 'WP_BACKGROUND_WORKERS_PER_PLUGIN', 3 ), $plugin ) ) ); }
		private static function active( $plugin ) { return count( array_filter( self::$running, static function ( $entry ) use ( $plugin ) { return $entry['plugin'] === $plugin; } ) ); }
		private static function executable() { $path = $_SERVER['argv'][0] ?? ''; return $path && is_file( $path ) ? realpath( $path ) : false; }
		private static function key( $id ) { return 'flx_bg_task_' . $id; }
		private static function fresh( $id ) { wp_cache_delete( self::key( $id ), 'options' ); return get_option( self::key( $id ), array() ); }
		private static function cleanup( $id ) {
			flx_background_unschedule( 'flx_background_watchdog', array( $id ) );
			delete_option( self::key( $id ) );
		}
		public static function dispatch( $hook, $args = array() ) {
			if ( ! function_exists( 'flx_background_scheduler_version' ) || self::$executing || ! flx_background_worker_context() || ! wp_doing_cron() || getenv( 'FLX_BACKGROUND_CHILD' ) || ! isset( self::$hooks[ $hook ] ) || self::limit() < 2 || ! function_exists( 'proc_open' ) || ! class_exists( 'WP_CLI' ) || ! self::executable() ) { return false; }
			$id = wp_generate_uuid4();
			add_option( self::key( $id ), array( 'hook' => $hook, 'args' => $args, 'plugin' => self::$hooks[ $hook ], 'attempts' => 0, 'status' => 'queued' ), '', false );
			if ( ! flx_background_schedule( 'flx_background_watchdog', array( $id ), 210 ) ) { self::cleanup( $id ); return false; }
			self::$pending[] = array( 'id' => $id, 'plugin' => self::$hooks[ $hook ] );
			flx_background_log( 'job.queued', array( 'plugin' => self::$hooks[ $hook ], 'hook' => $hook, 'job' => $id ) );
			return true;
		}
		private static function submit( $id ) {
			if ( flx_bg_time_left() < 5 ) { return false; }
			// A separate hook avoids blocking or deleting the package's next batch.
			$command = array( PHP_BINARY, self::executable(), '--path=' . untrailingslashit( ABSPATH ), '--url=' . home_url(), 'flx', 'background-task', $id, '--quiet' );
			if ( function_exists( 'posix_geteuid' ) && 0 === posix_geteuid() ) { $command[] = '--allow-root'; }
			$previous = getenv( 'FLX_BACKGROUND_CHILD' ); $previous_job = getenv( 'FLX_BACKGROUND_JOB_ID' ); putenv( 'FLX_BACKGROUND_CHILD=1' ); putenv( 'FLX_BACKGROUND_JOB_ID=' . $id );
			try { $process = proc_open( $command, array( 0 => array( 'file', '/dev/null', 'r' ), 1 => array( 'file', '/dev/null', 'w' ), 2 => array( 'file', '/dev/null', 'w' ) ), $pipes, ABSPATH ); }
			finally {
				false === $previous ? putenv( 'FLX_BACKGROUND_CHILD' ) : putenv( 'FLX_BACKGROUND_CHILD=' . $previous );
				false === $previous_job ? putenv( 'FLX_BACKGROUND_JOB_ID' ) : putenv( 'FLX_BACKGROUND_JOB_ID=' . $previous_job );
			}
			if ( ! is_resource( $process ) ) { return false; }
			$task = self::fresh( $id );
			self::$running[ $id ] = array( 'process' => $process, 'plugin' => self::$hooks[ $task['hook'] ], 'started' => microtime( true ), 'timeout' => max( 1, min( 120, (int) apply_filters( 'flx_background_timeout', 120 ) ) ) );
			return true;
		}
		public static function reap( $wait = false ) {
			do {
				$finished = false;
				foreach ( self::$running as $id => $entry ) {
					$status = proc_get_status( $entry['process'] );
					if ( $status['running'] && microtime( true ) - $entry['started'] > $entry['timeout'] ) {
						proc_terminate( $entry['process'], 9 );
						flx_background_log( 'job.timeout', array( 'plugin' => $entry['plugin'], 'job' => $id, 'code' => 'wall_timeout' ) );
						$status['running'] = false;
					}
					if ( $status['running'] ) { continue; }
					proc_close( $entry['process'] ); unset( self::$running[ $id ] ); $finished = true;
					$task = self::fresh( $id );
					if ( 'done' === ( $task['status'] ?? '' ) ) { self::cleanup( $id ); }
					else {
						flx_background_log( 'job.stopped', array( 'plugin' => $entry['plugin'], 'job' => $id, 'state' => $task['status'] ?? 'unknown', 'code' => (int) ( $status['exitcode'] ?? -1 ) ) );
						if ( class_exists( 'WP_CLI' ) ) { WP_CLI::warning( 'A background batch stopped; its watchdog will retry within the configured attempt limit.' ); }
					}
				}
				if ( $wait && ! $finished && self::$running ) {
					if ( flx_bg_time_left() < 1 ) { return; }
					usleep( 50000 );
				}
			} while ( $wait && ! $finished && self::$running );
		}
		public static function finish() {
			// Start children after the coordinator has finished consuming cron events.
			// Only our serialized scheduling helpers mutate cron during this phase.
			while ( self::$pending || self::$running ) {
				if ( flx_bg_time_left() < 5 ) {
					if ( self::$running && flx_bg_time_left() >= 1 ) { self::reap( true ); }
					break;
				}
				foreach ( self::$pending as $index => $entry ) {
					if ( flx_bg_time_left() < 5 || count( self::$running ) >= self::limit() ) { break; }
					if ( self::active( $entry['plugin'] ) >= self::plugin_limit( $entry['plugin'] ) ) { continue; }
					unset( self::$pending[ $index ] ); $id = $entry['id'];
					if ( ! self::submit( $id ) && self::run( $id ) ) { self::cleanup( $id ); }
				}
				if ( flx_bg_time_left() < 1 ) { break; }
				self::reap( true );
			}
		}
		public static function run( $id ) {
			if ( ! preg_match( '/^[a-f0-9-]{36}$/D', (string) $id ) ) { return false; }
			$task = self::fresh( $id );
			if ( ! $task || ! isset( self::$hooks[ $task['hook'] ?? '' ] ) ) { return false; }
			if ( 'done' === ( $task['status'] ?? '' ) ) { return true; }
			$owner = flx_background_lock( 'dispatch-' . $id, 180 );
			if ( ! $owner ) { return false; }
			$plugin = self::$hooks[ $task['hook'] ]; $plugin_owner = false; $plugin_slot = 0;
			for ( $i = 0; $i < self::plugin_limit( $plugin ); ++$i ) {
				$plugin_owner = flx_background_lock( 'plugin-slot-' . $plugin . '-' . $i, 180 );
				if ( $plugin_owner ) { $plugin_slot = $i; break; }
			}
			if ( ! $plugin_owner ) { flx_background_unlock( 'dispatch-' . $id, $owner ); return false; }
			$slot_owner = false; $slot = 0;
			for ( $i = 0; $i < self::limit(); ++$i ) {
				$slot_owner = flx_background_lock( 'process-slot-' . $i, 180 );
				if ( $slot_owner ) { $slot = $i; break; }
			}
			if ( ! $slot_owner ) { flx_background_unlock( 'plugin-slot-' . $plugin . '-' . $plugin_slot, $plugin_owner ); flx_background_unlock( 'dispatch-' . $id, $owner ); return false; }
			try {
				if ( flx_bg_time_left() < 5 ) { return false; }
				$task = self::fresh( $id );
				if ( 'done' === ( $task['status'] ?? '' ) ) { return true; }
				if ( (int) $task['attempts'] >= 3 ) { return false; }
				$task['attempts']++; $task['status'] = 'running';
				update_option( self::key( $id ), $task, false );
				$started = microtime( true );
				flx_background_log( 'job.started', array( 'plugin' => $plugin, 'hook' => $task['hook'], 'job' => $id, 'attempt' => $task['attempts'] ) );
				if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( max( 1, min( 120, (int) flx_bg_time_left() ) ) ); }
				self::$executing = true;
				do_action_ref_array( $task['hook'], $task['args'] );
				$task['status'] = 'done'; update_option( self::key( $id ), $task, false );
				flx_background_log( 'job.finished', array( 'plugin' => $plugin, 'hook' => $task['hook'], 'job' => $id, 'elapsed_ms' => (int) ( 1000 * ( microtime( true ) - $started ) ) ) );
				return true;
			} catch ( Throwable $error ) {
				$task['status'] = 'failed'; update_option( self::key( $id ), $task, false );
				flx_background_log( 'job.failed', array( 'plugin' => $plugin, 'hook' => $task['hook'], 'job' => $id, 'error_class' => get_class( $error ), 'error_file' => basename( $error->getFile() ), 'error_line' => $error->getLine() ) );
				return false;
			} finally {
				self::$executing = false;
				flx_background_unlock( 'process-slot-' . $slot, $slot_owner );
				flx_background_unlock( 'plugin-slot-' . $plugin . '-' . $plugin_slot, $plugin_owner );
				flx_background_unlock( 'dispatch-' . $id, $owner );
			}
		}
		public static function watchdog( $id ) {
			$task = self::fresh( $id );
			if ( ! $task ) { return; }
			if ( 'done' === ( $task['status'] ?? '' ) || (int) $task['attempts'] >= 3 || ! isset( self::$hooks[ $task['hook'] ?? '' ] ) ) {
				if ( 'done' !== ( $task['status'] ?? '' ) ) { do_action( 'flx_background_failed', $task['hook'], $id ); }
				if ( 'done' !== ( $task['status'] ?? '' ) ) { flx_background_log( 'job.exhausted', array( 'plugin' => self::$hooks[ $task['hook'] ] ?? 'shared', 'hook' => $task['hook'], 'job' => $id, 'attempt' => $task['attempts'] ) ); }
				self::cleanup( $id ); return;
			}
			if ( flx_background_should_defer() ) { flx_background_schedule( 'flx_background_watchdog', array( $id ), 60 ); return; }
			if ( flx_bg_time_left() < 5 ) { flx_background_schedule( 'flx_background_watchdog', array( $id ), 60 ); return; }
			flx_background_log( 'job.retry', array( 'plugin' => self::$hooks[ $task['hook'] ], 'hook' => $task['hook'], 'job' => $id, 'attempt' => (int) $task['attempts'] + 1 ) );
			// A retry can run synchronously; only an ordinary cron worker creates a pool.
			if ( self::run( $id ) ) { self::cleanup( $id ); }
			else { flx_background_schedule( 'flx_background_watchdog', array( $id ), 210 ); }
		}
	}
	add_action( 'flx_background_watchdog', array( 'FLX_Background_Pool', 'watchdog' ) );
	register_shutdown_function( array( 'FLX_Background_Pool', 'finish' ) );
	if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
		WP_CLI::add_command( 'flx background-task', static function ( $args ) {
			if ( ! flx_background_worker_context() || ! getenv( 'FLX_BACKGROUND_CHILD' ) ) { WP_CLI::error( 'This command is reserved for the local background dispatcher.' ); }
			defined( 'DOING_CRON' ) || define( 'DOING_CRON', true );
			if ( ! FLX_Background_Pool::run( $args[0] ) ) { WP_CLI::halt( 1 ); }
		}, array( 'synopsis' => '<id>' ) );
	}
}
