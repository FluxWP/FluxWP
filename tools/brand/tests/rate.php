<?php
/**
 * Standalone checks for flx_client_ip and flx_rate_limit. No WordPress.
 * php tools/brand/tests/rate.php
 */

define( 'ABSPATH', __DIR__ );

function add_action() {}
function add_filter() {}
function add_shortcode() {}

function apply_filters( $tag, $value ) {
	global $flx_test_filters;
	if ( isset( $flx_test_filters[ $tag ] ) ) {
		return $flx_test_filters[ $tag ]( $value );
	}
	return $value;
}

function wp_rand( $min = 0, $max = 0 ) { // phpcs:ignore
	return 2;
}

/** In-memory options table: insert-or-increment, select, delete. */
class Flx_Test_Wpdb {
	public $options = 'wp_options';
	public $rows    = array();

	public function esc_like( $text ) {
		return addcslashes( (string) $text, '_%\\' );
	}

	public function prepare( $sql, ...$args ) {
		$i = 0;
		return preg_replace_callback(
			'/%[sdf]/',
			static function ( $m ) use ( &$i, $args ) {
				$v = $args[ $i++ ];
				if ( 'd' === $m[0][1] ) {
					return (string) (int) $v;
				}
				if ( 'f' === $m[0][1] ) {
					return (string) (float) $v;
				}
				return "'" . str_replace( "'", "''", (string) $v ) . "'";
			},
			$sql
		);
	}

	public function query( $sql ) {
		if ( preg_match( "/INSERT INTO {$this->options} \\(option_name, option_value, autoload\\) VALUES \\('([^']*)', '1', 'off'\\) ON DUPLICATE KEY UPDATE option_value = option_value \\+ 1/", $sql, $m ) ) {
			$name = $m[1];
			$this->rows[ $name ] = isset( $this->rows[ $name ] ) ? (string) ( (int) $this->rows[ $name ] + 1 ) : '1';
			return 1;
		}
		if ( preg_match( "/DELETE FROM {$this->options} WHERE option_name LIKE '([^']*)' AND option_name < '([^']*)'/", $sql, $m ) ) {
			$like = str_replace( array( '\\_', '\\%', '\\\\' ), array( '_', '%', '\\' ), $m[1] );
			$cut  = $m[2];
			foreach ( array_keys( $this->rows ) as $name ) {
				if ( fnmatch( str_replace( '%', '*', $like ), $name ) && strcmp( $name, $cut ) < 0 ) {
					unset( $this->rows[ $name ] );
				}
			}
			return 1;
		}
		return 0;
	}

	public function get_var( $sql ) {
		if ( preg_match( "/SELECT option_value FROM {$this->options} WHERE option_name = '([^']*)'/", $sql, $m ) ) {
			return $this->rows[ $m[1] ] ?? null;
		}
		return null;
	}
}

$wpdb = new Flx_Test_Wpdb();

require dirname( __DIR__ ) . '/flx-brand/core.php';

$failed = 0;

function rate_assert( $label, $expected, $got ) {
	global $failed;
	if ( $expected === $got ) {
		echo "ok  $label\n";
		return;
	}
	++$failed;
	fwrite( STDERR, "FAIL $label\n expected: " . var_export( $expected, true ) . "\n got:      " . var_export( $got, true ) . "\n" );
}

function rate_server( array $server ) {
	foreach ( array( 'REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP' ) as $key ) {
		unset( $_SERVER[ $key ] );
	}
	foreach ( $server as $key => $value ) {
		$_SERVER[ $key ] = $value;
	}
}

rate_server( array( 'REMOTE_ADDR' => '8.8.8.8', 'HTTP_X_FORWARDED_FOR' => '1.1.1.1' ) );
rate_assert( 'public peer ignores forged forwarded', '8.8.8.8', flx_client_ip() );

rate_server( array( 'REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8, 1.1.1.1' ) );
rate_assert( 'private peer uses rightmost public', '1.1.1.1', flx_client_ip() );

rate_server( array( 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_FORWARDED_FOR' => '9.9.9.9' ) );
rate_assert( 'loopback peer uses forwarded', '9.9.9.9', flx_client_ip() );

rate_server(
	array(
		'REMOTE_ADDR'           => '104.16.1.1',
		'HTTP_CF_CONNECTING_IP' => '9.9.9.9',
		'HTTP_X_FORWARDED_FOR'  => '1.1.1.1',
	)
);
rate_assert( 'cloudflare peer uses connecting ip', '9.9.9.9', flx_client_ip() );

rate_server( array( 'REMOTE_ADDR' => 'not-an-ip', 'HTTP_X_FORWARDED_FOR' => '1.1.1.1' ) );
rate_assert( 'garbage is empty', '', flx_client_ip() );

$GLOBALS['flx_test_filters'] = array(
	'flx_client_ip' => static function ( $ip ) {
		return 'filtered';
	},
);
rate_server( array( 'REMOTE_ADDR' => '8.8.8.8' ) );
rate_assert( 'filter', 'filtered', flx_client_ip() );
$GLOBALS['flx_test_filters'] = array();

rate_assert( 'first hit', true, flx_rate_limit( 'alpha', 2, 60 ) );
rate_assert( 'second hit', true, flx_rate_limit( 'alpha', 2, 60 ) );
rate_assert( 'over limit', false, flx_rate_limit( 'alpha', 2, 60 ) );
rate_assert( 'other key', true, flx_rate_limit( 'beta', 2, 60 ) );

$before = count( $wpdb->rows );
rate_assert( 'max zero', true, flx_rate_limit( 'gamma', 0, 60 ) );
rate_assert( 'no row for zero', $before, count( $wpdb->rows ) );

$window_key = 'delta-' . bin2hex( random_bytes( 4 ) );
rate_assert( 'window first', true, flx_rate_limit( $window_key, 1, 1 ) );
rate_assert( 'window blocked', false, flx_rate_limit( $window_key, 1, 1 ) );
sleep( 2 );
rate_assert( 'new window', true, flx_rate_limit( $window_key, 1, 1 ) );

$wpdb->rows = array();
flx_rate_limit( 'same', 5, 60 );
flx_rate_limit( 'same', 5, 3600 );
$suffixes = array();
foreach ( array_keys( $wpdb->rows ) as $name ) {
	$suffixes[ substr( (string) $name, -32 ) ] = true;
}
rate_assert( 'window is part of the counter name', 2, count( $suffixes ) );

if ( $failed ) {
	fwrite( STDERR, "$failed failed\n" );
	exit( 1 );
}
echo "rate.php ok\n";
