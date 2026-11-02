<?php
// Yalniz yerel README ekranlari: Flux TV Kumandasi icin koprunun bagli gorunmesi (gercek TV yok).
add_filter( 'pre_option_flxtv_bridge', function ( $v ) {
	if ( ! get_option( 'readme_demo_tv' ) ) { return $v; }
	return array( 'seen' => time(), 'state' => array( 'name' => 'Salon TV', 'host' => '192.168.1.104', 'mac' => '', 'paired' => true, 'connected' => true, 'powered' => true, 'app' => 'com.google.android.youtube.tv', 'pairing' => '', 'unpaired' => false, 'error' => '', 'version' => '0.1.2', 'local_url' => 'http://192.168.1.237:8765/?k=demo1234', 'volume' => array( 'level' => 18, 'maximum' => 100, 'muted' => false ) ) );
} );
add_filter( 'pre_option_flxtv_bridge_ips', function ( $v ) {
	if ( ! get_option( 'readme_demo_tv' ) ) { return $v; }
	return array( 'v4' => array( 'k' => 'v4:127.0.0.1', 'ts' => time() ) );
} );
