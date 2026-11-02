<?php require ( getenv( 'WPENV' ) ?: '/tmp/wpenv' ) . '/site/wp-load.php';
// Flux App: demo oturumlar, API anahtarlari ve bir MCP istemcisi (gercek belirtec degil, yalniz yerel)
global $wpdb; wp_set_current_user( 1 );
$wpdb->query( 'DELETE FROM ' . flxapp_table() );
$u = function ( $l ) { $x = get_user_by( 'login', $l ); return $x ? $x->ID : 1; };
if ( ! array_filter( flxapp_stored_clients(), function ( $c ) { return 'Claude' === $c['name']; } ) ) {
	flxapp_add_client( 'Claude', array( 'https://claude.ai/api/mcp/auth_callback' ), false, true );
}
$claude = '';
foreach ( flxapp_stored_clients() as $c ) { if ( 'Claude' === $c['name'] ) { $claude = $c['id']; } }
$rows = array(
	array( 'refresh', 'admin', FLXAPP_DESKTOP_CLIENT, 'MacBook Air', 3 ),
	array( 'refresh', 'admin', FLXAPP_DESKTOP_CLIENT, 'iPhone 16', 1 ),
	array( 'refresh', 'ayse', FLXAPP_DESKTOP_CLIENT, 'Android', 6 ),
	array( 'refresh', 'mehmet', FLXAPP_DESKTOP_CLIENT, 'Windows', 12 ),
	array( 'refresh', 'admin', $claude, 'Claude', 2 ),
	array( 'key', 'admin', '', 'TV panosu', 9 ),
	array( 'key', 'zeynep', '', 'Rapor betiği', 20 ),
);
foreach ( $rows as $i => $r ) {
	$g = wp_generate_password( 20, false );
	flxapp_issue( $r[0], array( 'user_id' => $u( $r[1] ), 'client_id' => $r[2], 'grant_id' => $g, 'name' => $r[3], 'scope' => '', 'ttl' => 'key' === $r[0] && $i === 6 ? 90 * DAY_IN_SECONDS : 0 ) );
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . flxapp_table() . ' SET created=%d, last_used=%d WHERE grant_id=%s', time() - $r[4] * DAY_IN_SECONDS, time() - $i * 3100, $g ) );
	if ( 'refresh' === $r[0] ) {
		flxapp_issue( 'access', array( 'user_id' => $u( $r[1] ), 'client_id' => $r[2], 'grant_id' => $g, 'name' => $r[3], 'ttl' => 3600 ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . flxapp_table() . " SET last_used=%d WHERE grant_id=%s AND kind='access'", time() - $i * 3100, $g ) );
	}
}
echo "ok\n";
