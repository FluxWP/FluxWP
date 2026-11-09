<?php
/** FLX_TEST_SITE=1 php dashboard-permission.php /var/www/html */
if ( '1' !== getenv( 'FLX_TEST_SITE' ) || empty( $argv[1] ) ) { exit( "Disposable WordPress site required.\n" ); }
require rtrim( $argv[1], '/' ) . '/wp-load.php';
function dashboard_check( bool $value, string $message ): void { if ( ! $value ) { throw new RuntimeException( $message ); } echo "PASS $message\n"; }
wp_set_current_user( 0 );
$request = new WP_REST_Request( 'GET', '/flxan/v1/dashboard' );
$response = rest_do_request( $request );
dashboard_check( 401 === $response->get_status() || 403 === $response->get_status(), 'Anonymous visitors cannot read dashboard analytics' );
wp_set_current_user( 1 );
$response = rest_do_request( $request );
dashboard_check( 200 === $response->get_status(), 'An authorized dashboard REST request checks the current user without treating the request as a user object' );
