<?php
/** Standalone contract tests; no network and no real WordPress database. */
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );
$options = array();
$general = false;
$logged_in = true;
$scheduled = array();
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function get_option( $n, $d = false ) { global $options; return $options[ $n ] ?? $d; }
function add_option( $n, $v, ...$a ) { global $options; if ( array_key_exists( $n, $options ) ) { return false; } $options[ $n ] = $v; return true; }
function delete_option( $n ) { global $options; unset( $options[ $n ] ); }
function wp_cache_delete( ...$a ) {}
function wp_salt( $s ) { return 'test-only-local-site-salt'; }
function flxk_telemetry_enabled() { global $general; return $general; }
function is_user_logged_in() { global $logged_in; return $logged_in; }
function get_current_user_id() { return 42; }
function is_wp_error( $r ) { return false; }
function wp_next_scheduled( $h ) { global $scheduled; return $scheduled[ $h ] ?? false; }
function wp_schedule_event( $t, $r, $h ) { global $scheduled; $scheduled[ $h ] = $t; }
function wp_schedule_single_event( $t, $h ) { global $scheduled; $scheduled[ $h ] = $t; }
function flxan_settings( $k ) { return false; }
class UsageTestDb {
	public $options = 'wp_options';
	public $fail = false;
	public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
	public function query( $q ) { global $options; if ( $this->fail ) { return false; } $name = $q[1][0]; $options[ $name ] = (int) $options[ $name ] + 1; return 1; }
}
$wpdb = new UsageTestDb();
require __DIR__ . '/../../plugins/flx-app/includes/usage.php';
require __DIR__ . '/../../plugins/flx-analytics/includes/telemetry-hub.php';
function check( $value, $message ) { if ( ! $value ) { throw new RuntimeException( $message ); } }
$name = FLXAPP_USAGE_PREFIX . gmdate( 'Ymd' ) . '_ios';
flxapp_usage_count( 42, 'ios' );
check( ! isset( $options[ $name ] ), 'General opt-out must not create a count' );
$general = true;
flxapp_usage_count( 42, 'ios' );
check( ! isset( $options[ $name ] ), 'Existing general consent must not imply the new site consent' );
$options['flxapp_usage_share'] = '1';
flxapp_usage_count( 42, 'ios' );
flxapp_usage_count( 42, 'ios' );
check( 1 === $options[ $name ], 'Repeated requests must count one account per platform/day' );
flxapp_usage_count( 43, 'ios' );
check( 2 === $options[ $name ], 'Different accounts must contribute separately' );
flxapp_usage_count( 0, 'ios' );
flxapp_usage_count( 44, 'desktop' );
check( 2 === $options[ $name ], 'Unknown surfaces and unauthenticated IDs must not count' );
$wpdb->fail = true;
flxapp_usage_count( 99, 'ios' );
$wpdb->fail = false;
flxapp_usage_count( 99, 'ios' );
check( 3 === $options[ $name ], 'A failed database write must be retryable' );
class UsageRequest {
	public $consent = '0';
	public function get_header( $key ) { return 'x-fluxwp-usage' === $key ? $this->consent : 'ios'; }
	public function get_route() { return '/flxapp/v1/me'; }
}
class UsageResponse {
	public $status = 200;
	public function get_status() { return $this->status; }
}
$request = new UsageRequest();
$response = new UsageResponse();
flxapp_usage_observe( $response, null, $request );
check( 3 === $options[ $name ], 'Device opt-out must not count' );
$request->consent = '1';
$logged_in = false;
flxapp_usage_observe( $response, null, $request );
check( 3 === $options[ $name ], 'Unauthenticated requests must not count' );
$logged_in = true;
$response->status = 500;
flxapp_usage_observe( $response, null, $request );
check( 3 === $options[ $name ], 'Failed requests must not count' );
$yesterday = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
$options[ FLXAPP_USAGE_PREFIX . str_replace( '-', '', $yesterday ) . '_ios' ] = 5;
$options[ FLXAPP_USAGE_PREFIX . str_replace( '-', '', $yesterday ) . '_android' ] = 4;
check( array( $yesterday => array( 'ios' => 5 ) ) === flxapp_usage_report(), 'Report must exclude today and small cohorts' );
$payload = flxapp_usage_payload( array( 'v' => 1 ) );
check( array( 'v', 'app_usage' ) === array_keys( $payload ), 'No identity, location, URL or content keys may be added' );
$invalid = array( $yesterday => array( 'ios' => 5, 'android' => 4, 'url' => 'https://private.invalid', 'user' => 42 ), gmdate( 'Y-m-d' ) => array( 'ios' => 100 ) );
check( array( $yesterday => array( 'ios' => 5 ) ) === FLXAN_Telemetry_Hub::app_usage( $invalid ), 'Hub must whitelist dates/platforms/counts and reject identifiers' );
check( array() === FLXAN_Telemetry_Hub::app_usage( array( $yesterday => 'malformed' ) ), 'Malformed nested data must be harmless' );
check( array() === FLXAN_Telemetry_Hub::app_usage( array( $yesterday => array( 'ios' => 5.5, 'android' => 1000001 ) ) ), 'Hub must reject floats and excessive counts' );
$general = false;
check( array() === flxapp_usage_report(), 'Withdrawing site consent must stop reports immediately' );
echo "Mobile usage privacy contracts: OK\n";
