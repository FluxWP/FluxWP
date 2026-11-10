<?php
/** Shared operational log table; one multi-row insert per flush, no message bodies. */
defined( 'ABSPATH' ) || exit;
if ( ! function_exists( 'flx_background_log' ) ) {
	/** @return array<int,array> */
	function &flx_background_log_buffer() {
		static $buffer = array();
		return $buffer;
	}

	function flx_background_log_cap() {
		$limit = (int) apply_filters( 'flx_background_log_limit', 2000 );
		return max( 100, min( 10000, $limit ) );
	}

	function flx_background_log( $event, $context = array() ) {
		static $logging = false;
		static $hooked = false;
		if ( $logging ) {
			return;
		}
		$logging = true;
		try {
			$allowed = array( 'plugin', 'hook', 'job', 'batch', 'campaign', 'item', 'state', 'code', 'attempt', 'count', 'elapsed_ms', 'delivered', 'failed', 'pending', 'page', 'error_class', 'error_file', 'error_line' );
			$clean = array();
			foreach ( array_intersect_key( (array) $context, array_flip( $allowed ) ) as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$clean[ $key ] = is_string( $value ) ? mb_substr( sanitize_text_field( $value ), 0, 160 ) : $value;
				}
			}
			$name = preg_replace( '/[^a-z0-9_.-]/', '', strtolower( (string) $event ) );
			$entry = array( 'event' => mb_substr( (string) $name, 0, 64 ), 'time' => time(), 'context' => $clean );
			if ( strlen( serialize( $entry ) ) > 4096 ) {
				$entry['context'] = array();
			}
			$buffer = &flx_background_log_buffer();
			$buffer[] = $entry;
			if ( ! $hooked ) {
				register_shutdown_function( 'flx_background_log_flush' );
				register_shutdown_function( 'flx_background_log_retain' );
				$hooked = true;
			}
			if ( count( $buffer ) >= 20 ) {
				flx_background_log_flush();
			}
			do_action( 'flx_background_logged', $entry );
		} catch ( Throwable $error ) { /* Operational logging must not stop a delivery. */ }
		finally { $logging = false; }
	}

	/** One multi-row insert. Concurrent flushes append; nothing is rewritten. */
	function flx_background_log_flush() {
		$buffer = &flx_background_log_buffer();
		if ( ! $buffer ) {
			return;
		}
		$pending = $buffer;
		$buffer = array();
		try {
			flx_background_log_write( $pending );
		} catch ( Throwable $error ) { /* Losing a line is acceptable. Stopping delivery is not. */ }
	}

	function flx_background_log_state() {
		$state = get_option( 'flx_background_log_db', array() );
		return is_array( $state ) ? $state : array();
	}

	function flx_background_log_save( $state ) {
		update_option( 'flx_background_log_db', $state, true );
	}

	function flx_background_log_table() {
		global $wpdb;
		return $wpdb->prefix . 'flx_background_log';
	}

	function flx_background_log_missing() {
		global $wpdb;
		$error = strtolower( (string) $wpdb->last_error );
		return false !== strpos( $error, 'exist' ) || false !== strpos( $error, '1146' );
	}

	function flx_background_log_create() {
		global $wpdb;
		$table = flx_background_log_table();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			plugin VARCHAR(40) NOT NULL DEFAULT '',
			event VARCHAR(64) NOT NULL,
			`time` INT UNSIGNED NOT NULL,
			campaign BIGINT UNSIGNED NOT NULL DEFAULT 0,
			context TEXT NOT NULL,
			PRIMARY KEY (id),
			KEY plugin_id (plugin, id),
			KEY campaign_id (campaign, id),
			KEY log_time (`time`)
		) {$charset}";
		return false !== $wpdb->query( $sql );
	}

	/** One bounded delete of the pre-table per-event rows and the old prune marker. */
	function flx_background_log_legacy() {
		global $wpdb;
		$pattern = $wpdb->esc_like( 'flx_background_event_' ) . '%';
		$marker = 'flx_background_' . 'journal_pruned';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name = %s LIMIT 5000", $pattern, $marker ) );
	}

	function flx_background_log_ensure( $rebuild = false ) {
		static $rebuilt = false;
		$state = flx_background_log_state();
		$version_ok = (int) ( $state['version'] ?? 0 ) >= 1;
		if ( $rebuild ) {
			if ( $rebuilt ) {
				return false;
			}
			$rebuilt = true;
			if ( ! flx_background_log_create() ) {
				return false;
			}
			$state['version'] = 1;
		} elseif ( ! $version_ok ) {
			if ( ! flx_background_log_create() ) {
				return false;
			}
			$state['version'] = 1;
		}
		$changed = $rebuild || ! $version_ok;
		if ( empty( $state['legacy'] ) ) {
			flx_background_log_legacy();
			$state['legacy'] = 1;
			$changed = true;
		}
		if ( ! isset( $state['pruned'] ) ) {
			$state['pruned'] = 0;
			$changed = true;
		}
		if ( $changed ) {
			flx_background_log_save( $state );
		}
		return true;
	}

	function flx_background_log_insert( $pending ) {
		global $wpdb;
		$rows = array();
		$values = array();
		foreach ( $pending as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$context = isset( $entry['context'] ) && is_array( $entry['context'] ) ? $entry['context'] : array();
			$json = wp_json_encode( $context );
			if ( ! is_string( $json ) ) {
				$json = '{}';
			}
			$rows[] = '(%s,%s,%d,%d,%s)';
			$values[] = mb_substr( (string) ( $context['plugin'] ?? '' ), 0, 40 );
			$values[] = mb_substr( (string) ( $entry['event'] ?? '' ), 0, 64 );
			$values[] = max( 0, (int) ( $entry['time'] ?? time() ) );
			$values[] = max( 0, (int) ( $context['campaign'] ?? 0 ) );
			$values[] = $json;
		}
		if ( ! $rows ) {
			return true;
		}
		$wpdb->last_error = '';
		$sql = 'INSERT INTO ' . flx_background_log_table() . ' (plugin, event, `time`, campaign, context) VALUES ' . implode( ',', $rows );
		return false !== $wpdb->query( $wpdb->prepare( $sql, ...$values ) );
	}

	function flx_background_log_write( $pending ) {
		if ( ! flx_background_log_ensure() || flx_background_log_insert( $pending ) ) {
			return;
		}
		if ( flx_background_log_missing() && flx_background_log_ensure( true ) ) {
			flx_background_log_insert( $pending );
		}
	}

	/** At most once an hour: drop aged rows, then rows beyond the newest N. Both deletes are bounded. */
	function flx_background_log_retain() {
		global $wpdb;
		$state = flx_background_log_state();
		if ( (int) ( $state['version'] ?? 0 ) < 1 || (int) ( $state['pruned'] ?? 0 ) > time() - HOUR_IN_SECONDS ) {
			return;
		}
		$table = flx_background_log_table();
		$days = max( 1, (int) apply_filters( 'flx_background_log_days', 14 ) );
		$cutoff = time() - $days * DAY_IN_SECONDS;
		$age = static function () use ( $wpdb, $table, $cutoff ) {
			$wpdb->last_error = '';
			return $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE `time` < %d LIMIT 5000", $cutoff ) );
		};
		$deleted = $age();
		if ( false === $deleted && flx_background_log_missing() ) {
			if ( ! flx_background_log_ensure( true ) ) {
				return;
			}
			$age();
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id < (SELECT id FROM (SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET %d) AS flx_bg_keep) LIMIT 5000", flx_background_log_cap() - 1 ) );
		$state = flx_background_log_state();
		$state['pruned'] = time();
		flx_background_log_save( $state );
	}

	function flx_background_events( $limit = 100, $campaign = 0, $plugin = '' ) {
		$want = max( 1, (int) $limit );
		if ( ! $campaign ) {
			$want = min( 200, $want );
		} else {
			$want = min( 2000, $want );
		}
		$plugin = mb_substr( (string) $plugin, 0, 40 );
		$pending = array();
		foreach ( array_reverse( flx_background_log_buffer() ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$context = isset( $entry['context'] ) && is_array( $entry['context'] ) ? $entry['context'] : array();
			if ( $campaign && (int) ( $context['campaign'] ?? 0 ) !== (int) $campaign ) {
				continue;
			}
			if ( '' !== $plugin && (string) ( $context['plugin'] ?? '' ) !== $plugin ) {
				continue;
			}
			if ( ! isset( $entry['id'] ) ) {
				$entry['id'] = 0;
			}
			$pending[] = $entry;
			if ( count( $pending ) >= $want ) {
				return $pending;
			}
		}
		if ( ! flx_background_log_ensure() ) {
			return $pending;
		}
		$rows = flx_background_log_select( $want - count( $pending ), $campaign, $plugin );
		if ( null === $rows && flx_background_log_missing() && flx_background_log_ensure( true ) ) {
			$rows = flx_background_log_select( $want - count( $pending ), $campaign, $plugin );
		}
		if ( ! is_array( $rows ) ) {
			return $pending;
		}
		return array_merge( $pending, $rows );
	}

	/** @return array<int,array>|null Null when the table is missing. */
	function flx_background_log_select( $limit, $campaign, $plugin ) {
		global $wpdb;
		if ( $limit < 1 ) {
			return array();
		}
		$table = flx_background_log_table();
		$where = array();
		$args = array();
		if ( $campaign ) {
			$where[] = 'campaign = %d';
			$args[] = (int) $campaign;
		}
		if ( '' !== $plugin ) {
			$where[] = 'plugin = %s';
			$args[] = $plugin;
		}
		$sql = "SELECT id, event, `time`, context FROM {$table}";
		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql .= ' ORDER BY id DESC LIMIT %d';
		$args[] = (int) $limit;
		$wpdb->last_error = '';
		$fetched = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );
		if ( flx_background_log_missing() ) {
			return null;
		}
		$out = array();
		foreach ( (array) $fetched as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$context = json_decode( (string) ( $row['context'] ?? '' ), true );
			$out[] = array(
				'id' => (int) ( $row['id'] ?? 0 ),
				'event' => (string) ( $row['event'] ?? '' ),
				'time' => (int) ( $row['time'] ?? 0 ),
				'context' => is_array( $context ) ? $context : array(),
			);
		}
		return $out;
	}
}
