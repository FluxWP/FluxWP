<?php
/* Dashboard: fidan, sertifika, kişi ve gönderim istatistikleri (veri REST /stats'tan, grafikler dashboard.js) */
defined( 'ABSPATH' ) || exit;

function flxs_page_dashboard() {
	global $wpdb;
	$tpls = $wpdb->get_results( 'SELECT id, name FROM ' . flxs_table( 'templates' ) . ' ORDER BY name', ARRAY_A ); // phpcs:ignore
	$data = array(
		'refs'      => array_map( function ( $u ) { return array( 'id' => (int) $u->ID, 'name' => $u->display_name ); }, flxs_users_with_cap() ),
		'templates' => array_map( function ( $t ) { return array( 'id' => (int) $t['id'], 'name' => $t['name'] ); }, (array) $tpls ),
		'listUrl'   => admin_url( 'admin.php?page=flxs' ),
		'newUrl'    => admin_url( 'admin.php?page=flxs-yeni' ),
		'ownOnly'   => flxs_dashboard_own_only(),
		'events'    => flxs_events_for_js(),
	);
	flxs_admin_header(
		'Dashboard',
		array( array( 'url' => $data['newUrl'], 'label' => 'Yeni sertifika', 'class' => 'button-primary' ) ),
		flxs_dashboard_own_only() ? 'Yalnızca sizin oluşturduğunuz sertifikalar.' : 'Fidan bağışları, sertifikalar ve gönderimler.'
	);
	echo '<script type="application/json" id="flxs-dash-data">' . wp_json_encode( $data ) . '</script>';
	echo '<div id="flxs-dash" class="flxs-dash"><div class="flxs-dash__loading">Yükleniyor…</div></div>';
	flxs_admin_footer();
}
