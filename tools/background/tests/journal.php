<?php
/**
 * Shared log table: one insert per flush, filters, retention, recreate, legacy cleanup, deadline.
 * Run: php tools/background/tests/journal.php
 * Real MariaDB: FLX_BG_MYSQL=user:pass@host/db php tools/background/tests/journal.php
 */
define( 'ABSPATH', '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'OBJECT', 'OBJECT' );
$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );

class Flx_Bg_Test_Wpdb {
	public $prefix = 'wp_';
	public $options = 'wp_options';
	public $store = array();
	public $rows = array();
	public $next_id = 0;
	public $table = false;
	public $last = array();
	public $last_error = '';
	public $queries = array();
	public $inserts = 0;
	public $creates = 0;
	public $legacy_deletes = 0;
	public $autoload = array();
	public $live = false;

	public function esc_like( $text ) { return addcslashes( $text, '_%\\' ); }
	public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
	public function prepare( $query, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) && 1 === count( $args ) ) { $args = $args[0]; }
		$this->last = $args;
		return $query;
	}
	public function log_rows() { return $this->rows; }
	public function query( $sql ) {
		$this->queries[] = $sql;
		$this->last_error = '';
		$args = $this->last;
		if ( false !== strpos( $sql, 'INSERT IGNORE' ) ) {
			if ( array_key_exists( $args[0], $this->store ) ) { return 0; }
			$this->store[ $args[0] ] = $args[1];
			return 1;
		}
		if ( false !== strpos( $sql, 'UPDATE ' ) ) {
			if ( array_key_exists( $args[1], $this->store ) && $this->store[ $args[1] ] === $args[2] ) {
				$this->store[ $args[1] ] = $args[0];
				return 1;
			}
			return 0;
		}
		if ( false !== strpos( $sql, 'CREATE TABLE' ) && false !== strpos( $sql, 'flx_background_log' ) ) {
			$this->table = true;
			$this->creates++;
			return true;
		}
		if ( false !== strpos( $sql, 'DROP TABLE' ) ) {
			$this->table = false;
			$this->rows = array();
			return true;
		}
		if ( false !== strpos( $sql, 'INSERT INTO' ) && false !== strpos( $sql, 'flx_background_log' ) ) {
			if ( ! $this->table ) {
				$this->last_error = "Table 'wp.wp_flx_background_log' doesn't exist";
				return false;
			}
			$this->inserts++;
			$added = 0;
			for ( $i = 0; $i + 4 < count( $args ); $i += 5 ) {
				$this->next_id++;
				$this->rows[] = array( 'id' => $this->next_id, 'plugin' => (string) $args[ $i ], 'event' => (string) $args[ $i + 1 ], 'time' => (int) $args[ $i + 2 ], 'campaign' => (int) $args[ $i + 3 ], 'context' => (string) $args[ $i + 4 ] );
				$added++;
			}
			return $added;
		}
		if ( false !== strpos( $sql, 'DELETE' ) && false !== strpos( $sql, 'LIKE' ) ) {
			$this->legacy_deletes++;
			$marker = (string) ( $args[1] ?? '' );
			$removed = 0;
			foreach ( array_keys( $this->store ) as $name ) {
				if ( 0 === strpos( $name, 'flx_background_event_' ) || ( '' !== $marker && $name === $marker ) ) { unset( $this->store[ $name ] ); $removed++; }
			}
			return $removed;
		}
		if ( false !== strpos( $sql, 'DELETE' ) && false !== strpos( $sql, 'flx_background_log' ) && false !== strpos( $sql, '`time` <' ) ) {
			if ( ! $this->table ) { $this->last_error = "Table 'wp.wp_flx_background_log' doesn't exist"; return false; }
			$cutoff = (int) $args[0];
			$kept = array();
			$removed = 0;
			foreach ( $this->rows as $row ) {
				if ( $removed < 5000 && (int) $row['time'] < $cutoff ) { $removed++; continue; }
				$kept[] = $row;
			}
			$this->rows = $kept;
			return $removed;
		}
		if ( false !== strpos( $sql, 'DELETE' ) && false !== strpos( $sql, 'OFFSET' ) ) {
			if ( ! $this->table ) { $this->last_error = "Table 'wp.wp_flx_background_log' doesn't exist"; return false; }
			$offset = (int) $args[0];
			$ids = array();
			foreach ( $this->rows as $row ) { $ids[] = (int) $row['id']; }
			rsort( $ids );
			if ( ! isset( $ids[ $offset ] ) ) { return 0; }
			$cutoff = $ids[ $offset ];
			$kept = array();
			$removed = 0;
			foreach ( $this->rows as $row ) {
				if ( $removed < 5000 && (int) $row['id'] < $cutoff ) { $removed++; continue; }
				$kept[] = $row;
			}
			$this->rows = $kept;
			return $removed;
		}
		if ( false !== strpos( $sql, 'DELETE' ) && false !== strpos( $sql, 'flx_background_log' ) && false === strpos( $sql, 'WHERE' ) ) {
			$removed = count( $this->rows );
			$this->rows = array();
			return $removed;
		}
		if ( false !== strpos( $sql, 'DELETE' ) && false !== strpos( $sql, 'option_value' ) ) {
			if ( array_key_exists( $args[0], $this->store ) && $this->store[ $args[0] ] === $args[1] ) {
				unset( $this->store[ $args[0] ] );
				return 1;
			}
			return 0;
		}
		return 0;
	}
	public function get_results( $sql, $output = OBJECT ) {
		$this->queries[] = $sql;
		$this->last_error = '';
		if ( false === strpos( $sql, 'flx_background_log' ) ) { return array(); }
		if ( ! $this->table ) {
			$this->last_error = "Table 'wp.wp_flx_background_log' doesn't exist";
			return array();
		}
		$args = $this->last;
		$campaign = 0;
		$plugin = '';
		if ( false !== strpos( $sql, 'campaign = %d' ) && false !== strpos( $sql, 'plugin = %s' ) ) {
			$campaign = (int) $args[0]; $plugin = (string) $args[1]; $limit = (int) $args[2];
		} elseif ( false !== strpos( $sql, 'campaign = %d' ) ) {
			$campaign = (int) $args[0]; $limit = (int) $args[1];
		} elseif ( false !== strpos( $sql, 'plugin = %s' ) ) {
			$plugin = (string) $args[0]; $limit = (int) $args[1];
		} else {
			$limit = (int) ( $args[0] ?? 100 );
		}
		$rows = $this->rows;
		usort( $rows, static function ( $a, $b ) { return $b['id'] <=> $a['id']; } );
		$out = array();
		foreach ( $rows as $row ) {
			if ( $campaign && (int) $row['campaign'] !== $campaign ) { continue; }
			if ( '' !== $plugin && (string) $row['plugin'] !== $plugin ) { continue; }
			$out[] = $row;
			if ( count( $out ) >= $limit ) { break; }
		}
		return $out;
	}
	public function get_var( $sql ) {
		$rows = $this->get_results( $sql, ARRAY_A );
		if ( ! $rows ) { return null; }
		return reset( $rows[0] );
	}
}

class Flx_Bg_Mysql_Wpdb extends Flx_Bg_Test_Wpdb {
	private $mysqli;
	public function __construct( $dsn ) {
		$this->live = true;
		if ( ! preg_match( '#^([^:]+):([^@]*)@([^/]+)/(.+)$#', $dsn, $m ) ) { fwrite( STDERR, "FLX_BG_MYSQL must be user:pass@host/db\n" ); exit( 1 ); }
		mysqli_report( MYSQLI_REPORT_OFF );
		$this->mysqli = new mysqli( $m[3], $m[1], $m[2], $m[4] );
		if ( $this->mysqli->connect_error ) { fwrite( STDERR, $this->mysqli->connect_error . "\n" ); exit( 1 ); }
		$this->mysqli->set_charset( 'utf8mb4' );
		$this->mysqli->query( "CREATE TABLE IF NOT EXISTS {$this->options} (
			option_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			option_name VARCHAR(191) NOT NULL DEFAULT '',
			option_value LONGTEXT NOT NULL,
			autoload VARCHAR(20) NOT NULL DEFAULT 'yes',
			PRIMARY KEY (option_id),
			UNIQUE KEY option_name (option_name)
		) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" );
	}
	public function prepare( $query, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) && 1 === count( $args ) ) { $args = $args[0]; }
		$this->last = $args;
		$i = 0;
		$mysqli = $this->mysqli;
		return preg_replace_callback( '/%[sdf]/', static function ( $match ) use ( $args, &$i, $mysqli ) {
			$value = $args[ $i ] ?? '';
			$i++;
			if ( '%s' === $match[0] ) { return "'" . $mysqli->real_escape_string( (string) $value ) . "'"; }
			if ( '%f' === $match[0] ) { return (string) (float) $value; }
			return (string) (int) $value;
		}, $query );
	}
	public function query( $sql ) {
		$this->queries[] = $sql;
		$this->last_error = '';
		$log_table = $this->prefix . 'flx_background_log';
		if ( false !== stripos( $sql, 'CREATE TABLE' ) && false !== strpos( $sql, $log_table ) ) { $this->creates++; }
		if ( false !== stripos( $sql, 'INSERT INTO ' . $log_table ) && false === stripos( $sql, 'INSERT IGNORE' ) ) { $this->inserts++; }
		if ( false !== stripos( $sql, 'DELETE' ) && false !== stripos( $sql, ' LIKE ' ) ) { $this->legacy_deletes++; }
		$ok = $this->mysqli->query( $sql );
		if ( false === $ok ) { $this->last_error = $this->mysqli->error; return false; }
		if ( $ok instanceof mysqli_result ) { $ok->free(); return true; }
		if ( false !== stripos( $sql, 'INSERT' ) || false !== stripos( $sql, 'DELETE' ) || false !== stripos( $sql, 'UPDATE' ) ) { return $this->mysqli->affected_rows; }
		return true;
	}
	public function get_results( $sql, $output = OBJECT ) {
		$this->queries[] = $sql;
		$this->last_error = '';
		$res = $this->mysqli->query( $sql );
		if ( false === $res ) { $this->last_error = $this->mysqli->error; return array(); }
		if ( true === $res ) { return array(); }
		$rows = $res->fetch_all( MYSQLI_ASSOC );
		$res->free();
		return $rows;
	}
	public function log_rows() {
		$rows = $this->get_results( "SELECT id, plugin, event, `time` AS time, campaign, context FROM {$this->prefix}flx_background_log ORDER BY id ASC", ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}
}

$dsn = getenv( 'FLX_BG_MYSQL' );
$wpdb = $dsn ? new Flx_Bg_Mysql_Wpdb( $dsn ) : new Flx_Bg_Test_Wpdb();

function flx_bg_test_fail( $message ) { fwrite( STDERR, $message . "\n" ); exit( 1 ); }
function add_filter( $hook, $callback ) { global $flx_bg_filters; $flx_bg_filters[ $hook ][] = $callback; }
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { add_filter( $hook, $callback ); }
function apply_filters( $hook, $value ) {
	global $flx_bg_filters;
	foreach ( $flx_bg_filters[ $hook ] ?? array() as $callback ) { $value = $callback( $value ); }
	return $value;
}
function do_action( $hook, ...$args ) {
	global $flx_bg_filters;
	foreach ( $flx_bg_filters[ $hook ] ?? array() as $callback ) { $callback( ...$args ); }
}
function wp_cache_delete( $key, $group = '' ) {}
function sanitize_text_field( $text ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $text ) ) ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function wp_generate_uuid4() { return sprintf( '%04x%04x-%04x-4%03x-%04x-%04x%04x%04x', mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0x0fff ), mt_rand( 0, 0x3fff ) | 0x8000, mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ) ); }
function flx_bg_unpack( $raw ) {
	if ( is_string( $raw ) && 'a:' === substr( $raw, 0, 2 ) ) {
		$decoded = unserialize( $raw );
		if ( is_array( $decoded ) ) { return $decoded; }
	}
	return $raw;
}
function get_option( $name, $default = false ) {
	global $wpdb;
	if ( empty( $wpdb->live ) ) { return array_key_exists( $name, $wpdb->store ) ? $wpdb->store[ $name ] : $default; }
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $name ) );
	if ( null === $raw ) { return $default; }
	return flx_bg_unpack( $raw );
}
function add_option( $name, $value, $deprecated = '', $autoload = 'yes' ) {
	global $wpdb;
	if ( empty( $wpdb->live ) ) {
		if ( array_key_exists( $name, $wpdb->store ) ) { return false; }
		$wpdb->store[ $name ] = $value;
		return true;
	}
	if ( null !== get_option( $name, null ) ) { return false; }
	update_option( $name, $value, false !== $autoload && 'no' !== $autoload );
	return true;
}
function update_option( $name, $value, $autoload = null ) {
	global $wpdb;
	$wpdb->autoload[ $name ] = $autoload;
	if ( empty( $wpdb->live ) ) { $wpdb->store[ $name ] = $value; return true; }
	$stored = is_array( $value ) ? serialize( $value ) : (string) $value;
	$flag = ( false === $autoload ) ? 'no' : 'yes';
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = VALUES(autoload)", $name, $stored, $flag ) );
	return true;
}
function flx_bg_test_locks() {
	global $wpdb;
	if ( ! empty( $wpdb->live ) ) {
		$rows = $wpdb->get_results( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'flx\\_bg\\_lock\\_%'", ARRAY_A );
		$left = array();
		foreach ( (array) $rows as $row ) { $left[] = $row['option_name']; }
		return $left;
	}
	$left = array();
	foreach ( $wpdb->store as $name => $value ) {
		if ( 0 === strpos( $name, 'flx_bg_lock_' ) ) { $left[] = $name; }
	}
	return $left;
}

require __DIR__ . '/../runner.php';

$marker = 'flx_background_' . 'journal_pruned';
add_option( 'flx_background_event_1_a', array( 'event' => 'old' ), '', false );
add_option( 'flx_background_event_2_b', array( 'event' => 'old' ), '', false );
add_option( $marker, 1, '', false );
if ( null !== flx_background_log( 'qa.one', array( 'plugin' => 'flx-mail', 'campaign' => 7, 'count' => 1 ) ) ) { flx_bg_test_fail( 'log() return value changed' ); }
flx_background_log( 'qa.two', array( 'plugin' => 'flx-mail', 'campaign' => 8, 'count' => 2 ) );
flx_background_log( 'qa.three', array( 'plugin' => 'flx-backup', 'campaign' => 7, 'count' => 3 ) );
flx_background_log( 'qa.four', array( 'plugin' => 'flx-certificates', 'campaign' => 9, 'count' => 4 ) );
if ( 0 !== $wpdb->inserts || 0 !== $wpdb->creates ) { flx_bg_test_fail( 'buffered logs touched the table early' ); }
if ( false === get_option( 'flx_background_event_1_a' ) ) { flx_bg_test_fail( 'legacy cleanup ran before first use' ); }
$before = flx_background_events( 10 );
if ( 4 !== count( $before ) || 4 !== (int) $before[0]['context']['count'] || 0 !== (int) $before[0]['id'] ) { flx_bg_test_fail( 'buffer was not visible before flush' ); }
if ( 1 !== $wpdb->creates || 0 !== $wpdb->inserts ) { flx_bg_test_fail( 'first read did not create the table without inserting' ); }
if ( false !== get_option( 'flx_background_event_1_a', false ) || false !== get_option( $marker, false ) ) { flx_bg_test_fail( 'legacy option rows survived first use' ); }
if ( 1 !== (int) $wpdb->legacy_deletes ) { flx_bg_test_fail( 'legacy cleanup did not run once: ' . $wpdb->legacy_deletes ); }
$state = get_option( 'flx_background_log_db' );
if ( 1 !== (int) ( $state['version'] ?? 0 ) || empty( $state['legacy'] ) ) { flx_bg_test_fail( 'schema state was not recorded' ); }
if ( true !== ( $wpdb->autoload['flx_background_log_db'] ?? null ) ) { flx_bg_test_fail( 'schema option was not autoloaded' ); }
flx_background_log_flush();
if ( 1 !== $wpdb->inserts ) { flx_bg_test_fail( 'one request flush wrote ' . $wpdb->inserts . ' inserts' ); }
$inserts = array();
$log_table = $wpdb->prefix . 'flx_background_log';
foreach ( $wpdb->queries as $sql ) { if ( false !== strpos( $sql, 'INSERT INTO ' . $log_table ) ) { $inserts[] = $sql; } }
if ( 1 !== count( $inserts ) || 3 !== substr_count( $inserts[0], '),(' ) ) { flx_bg_test_fail( 'four log calls were not one multi-row INSERT' ); }
$plugins = array();
foreach ( $wpdb->log_rows() as $row ) { $plugins[] = $row['plugin']; }
if ( array( 'flx-mail', 'flx-mail', 'flx-backup', 'flx-certificates' ) !== $plugins ) { flx_bg_test_fail( 'rows lost their plugin id: ' . implode( ',', $plugins ) ); }
$mail = flx_background_events( 10, 0, 'flx-mail' );
$campaign = flx_background_events( 10, 7 );
$both = flx_background_events( 10, 7, 'flx-mail' );
if ( 2 !== count( $mail ) || 2 !== (int) $mail[0]['context']['count'] || 1 !== (int) $mail[1]['context']['count'] ) { flx_bg_test_fail( 'plugin filter is not newest-first' ); }
if ( 2 !== count( $campaign ) || 3 !== (int) $campaign[0]['context']['count'] || 1 !== (int) $campaign[1]['context']['count'] ) { flx_bg_test_fail( 'campaign filter missed a row' ); }
if ( 1 !== count( $both ) || 1 !== (int) $both[0]['context']['count'] ) { flx_bg_test_fail( 'plugin and campaign filters did not combine' ); }
if ( (int) $mail[0]['id'] <= 0 || ! isset( $mail[0]['event'], $mail[0]['time'], $mail[0]['context'] ) ) { flx_bg_test_fail( 'stored row shape changed' ); }
add_option( 'flx_background_event_4_d', array( 'event' => 'later' ), '', false );
flx_background_log( 'qa.one', array( 'plugin' => 'flx-mail', 'count' => 5 ) );
flx_background_log_flush();
if ( 2 !== $wpdb->inserts ) { flx_bg_test_fail( 'second flush should be the only extra insert' ); }
if ( 1 !== (int) $wpdb->legacy_deletes ) { flx_bg_test_fail( 'legacy DELETE ran again' ); }
if ( false === get_option( 'flx_background_event_4_d', false ) ) { flx_bg_test_fail( 'cleanup deleted rows after it was recorded' ); }
$batch = $wpdb->inserts;
for ( $i = 0; $i < 20; ++$i ) { flx_background_log( 'qa.batch', array( 'plugin' => 'flx-mail', 'count' => $i ) ); }
if ( $wpdb->inserts !== $batch + 1 || flx_background_log_buffer() ) { flx_bg_test_fail( 'a 20-entry loop did not flush as one INSERT' ); }

$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . 'flx_background_log' );
$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}flx_background_log (plugin, event, `time`, campaign, context) VALUES (%s,%s,%d,%d,%s)", 'flx-mail', 'qa.old', time() - 20 * DAY_IN_SECONDS, 0, '{"count":-1}' ) );
add_filter( 'flx_background_log_limit', static function () { return 100; } );
for ( $i = 0; $i < 105; ++$i ) { flx_background_log( 'qa.bounded', array( 'plugin' => 'flx-mail', 'count' => $i ) ); }
flx_background_log_flush();
flx_background_log_retain();
$counts = array();
foreach ( $wpdb->log_rows() as $row ) {
	$context = json_decode( (string) $row['context'], true );
	$counts[] = (int) ( $context['count'] ?? -2 );
}
if ( 100 !== count( $counts ) ) { flx_bg_test_fail( 'retention left ' . count( $counts ) . ' rows' ); }
if ( in_array( -1, $counts, true ) || in_array( 4, $counts, true ) || ! in_array( 5, $counts, true ) || ! in_array( 104, $counts, true ) ) { flx_bg_test_fail( 'retention kept the wrong rows' ); }
$listed = flx_background_events( 100, 0, 'flx-mail' );
if ( 104 !== (int) $listed[0]['context']['count'] || 5 !== (int) $listed[99]['context']['count'] ) { flx_bg_test_fail( 'events() is not newest-first after retention' ); }
$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}flx_background_log (plugin, event, `time`, campaign, context) VALUES (%s,%s,%d,%d,%s)", 'flx-mail', 'qa.older', time() - 20 * DAY_IN_SECONDS, 0, '{}' ) );
flx_background_log_retain();
$still = false;
foreach ( $wpdb->log_rows() as $row ) { if ( 'qa.older' === $row['event'] ) { $still = true; } }
if ( ! $still ) { flx_bg_test_fail( 'retention ran again inside the same hour' ); }
$state = get_option( 'flx_background_log_db' ); $state['pruned'] = 0; update_option( 'flx_background_log_db', $state, true );
flx_background_log_retain();
foreach ( $wpdb->log_rows() as $row ) { if ( 'qa.older' === $row['event'] ) { flx_bg_test_fail( 'retention skipped an old row after the hour elapsed' ); } }
if ( (int) get_option( 'flx_background_log_db' )['pruned'] < time() - 5 ) { flx_bg_test_fail( 'prune time was not stored' ); }

$creates = $wpdb->creates;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'flx_background_log' );
if ( null !== flx_background_log_select( 5, 0, 'flx-mail' ) ) { flx_bg_test_fail( 'select did not notice a missing table' ); }
if ( $wpdb->creates !== $creates ) { flx_bg_test_fail( 'select recreated the table by itself' ); }
flx_background_log( 'qa.rebuild', array( 'plugin' => 'flx-mail', 'count' => 1 ) );
flx_background_log_flush();
$rebuilt = false;
foreach ( $wpdb->log_rows() as $row ) { if ( 'qa.rebuild' === $row['event'] ) { $rebuilt = true; } }
if ( ! $rebuilt || $wpdb->creates !== $creates + 1 ) { flx_bg_test_fail( 'missing table was not recreated once' ); }
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'flx_background_log' );
flx_background_log( 'qa.rebuild2', array( 'plugin' => 'flx-mail', 'count' => 2 ) );
flx_background_log_flush();
foreach ( $wpdb->log_rows() as $row ) { if ( 'qa.rebuild2' === $row['event'] ) { flx_bg_test_fail( 'a second drop in the same request recreated the table' ); } }
if ( $wpdb->creates !== $creates + 1 ) { flx_bg_test_fail( 'create ran more than once after the table was dropped' ); }
foreach ( $wpdb->queries as $sql ) {
	if ( preg_match( '/show\\s+tables|information_schema/i', $sql ) ) { flx_bg_test_fail( 'normal request inspected the schema: ' . $sql ); }
}

$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true );
if ( abs( flx_bg_deadline() - ( $_SERVER['REQUEST_TIME_FLOAT'] + 45 ) ) > 0.001 ) { flx_bg_test_fail( 'deadline is not request start + 45s' ); }
if ( flx_bg_time_left() < 40 ) { flx_bg_test_fail( 'fresh request has no time left' ); }
$ran = false;
$ok = flx_background_run( 'qa-open', static function () use ( &$ran ) { $ran = true; return array( 'processed' => 3 ); } );
if ( ! $ran || 3 !== (int) $ok['processed'] ) { flx_bg_test_fail( 'runner skipped a batch that still had time' ); }
if ( flx_bg_test_locks() ) { flx_bg_test_fail( 'open batch left its lock' ); }

$_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 50;
if ( 0.0 !== (float) flx_bg_time_left() ) { flx_bg_test_fail( 'expired request still has time' ); }
$ran = false;
$late = flx_background_run( 'qa-late', static function () use ( &$ran ) { $ran = true; return array( 'processed' => 1 ); } );
if ( $ran ) { flx_bg_test_fail( 'runner started work past the deadline' ); }
if ( empty( $late['deferred'] ) || 0 !== (int) $late['processed'] ) { flx_bg_test_fail( 'late batch did not defer: ' . json_encode( $late ) ); }
if ( flx_bg_test_locks() ) { flx_bg_test_fail( 'late batch left its lock' ); }

echo "journal tests passed\n";
