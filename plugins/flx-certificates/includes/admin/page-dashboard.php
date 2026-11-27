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
		'locale'    => str_replace( '_', '-', get_user_locale() ),
		'strings'   => flxs_dashboard_strings(),
	);
	flxs_admin_header(
		__( 'Dashboard', 'flx-certificates' ),
		array( array( 'url' => $data['newUrl'], 'label' => __( 'New certificate', 'flx-certificates' ), 'class' => 'button-primary' ) ),
		flxs_dashboard_own_only() ? __( 'Only certificates that you created.', 'flx-certificates' ) : __( 'Sapling donations, certificates and deliveries.', 'flx-certificates' )
	);
	echo '<script type="application/json" id="flxs-dash-data">' . wp_json_encode( $data, JSON_HEX_TAG ) . '</script>';
	echo '<div id="flxs-dash" class="flxs-dash"><div class="flxs-dash__loading">' . esc_html__( 'Loading…', 'flx-certificates' ) . '</div></div>';
	flxs_admin_footer();
}

/** Labels shared by filters, chart accessibility text and CSV exports. */
function flxs_dashboard_strings(): array {
	return array(
		'%1$s: %2$s saplings, %3$s certificates' => __( '%1$s: %2$s saplings, %3$s certificates', 'flx-certificates' ),
		'%2$s saplings during %1$s' => __( '%2$s saplings during %1$s', 'flx-certificates' ),
		'%s failed' => __( '%s failed', 'flx-certificates' ),
		'%s first-time recipients' => __( '%s first-time recipients', 'flx-certificates' ),
		'%s sent' => __( '%s sent', 'flx-certificates' ),
		'%s waiting' => __( '%s waiting', 'flx-certificates' ),
		'30 days' => __( '30 days', 'flx-certificates' ),
		'7 days' => __( '7 days', 'flx-certificates' ),
		'90 days' => __( '90 days', 'flx-certificates' ),
		'All' => __( 'All', 'flx-certificates' ),
		'All events' => __( 'All events', 'flx-certificates' ),
		'All staff' => __( 'All staff', 'flx-certificates' ),
		'All templates' => __( 'All templates', 'flx-certificates' ),
		'Archive' => __( 'Archive', 'flx-certificates' ),
		'Average saplings' => __( 'Average saplings', 'flx-certificates' ),
		'Avg.' => __( 'Avg.', 'flx-certificates' ),
		'Busiest day' => __( 'Busiest day', 'flx-certificates' ),
		'Busiest month' => __( 'Busiest month', 'flx-certificates' ),
		'Cancelled' => __( 'Cancelled', 'flx-certificates' ),
		'Certificates' => __( 'Certificates', 'flx-certificates' ),
		'Channel and status' => __( 'Channel and status', 'flx-certificates' ),
		'Chart' => __( 'Chart', 'flx-certificates' ),
		'Compared with the previous %s days' => __( 'Compared with the previous %s days', 'flx-certificates' ),
		'Could not load data:' => __( 'Could not load data:', 'flx-certificates' ),
		'Cumulative' => __( 'Cumulative', 'flx-certificates' ),
		'Daily certificates' => __( 'Daily certificates', 'flx-certificates' ),
		'Daily saplings' => __( 'Daily saplings', 'flx-certificates' ),
		'Daily saplings chart' => __( 'Daily saplings chart', 'flx-certificates' ),
		'Day' => __( 'Day', 'flx-certificates' ),
		'Days of the week' => __( 'Days of the week', 'flx-certificates' ),
		'Deliveries' => __( 'Deliveries', 'flx-certificates' ),
		'Delivery' => __( 'Delivery', 'flx-certificates' ),
		'End date' => __( 'End date', 'flx-certificates' ),
		'Event' => __( 'Event', 'flx-certificates' ),
		'Events' => __( 'Events', 'flx-certificates' ),
		'Excluding %s revoked' => __( 'Excluding %s revoked', 'flx-certificates' ),
		'Failed' => __( 'Failed', 'flx-certificates' ),
		'Month' => __( 'Month', 'flx-certificates' ),
		'Monthly certificates' => __( 'Monthly certificates', 'flx-certificates' ),
		'Monthly saplings' => __( 'Monthly saplings', 'flx-certificates' ),
		'No certificates in this period.' => __( 'No certificates in this period.', 'flx-certificates' ),
		'No data.' => __( 'No data.', 'flx-certificates' ),
		'No deliveries in this period.' => __( 'No deliveries in this period.', 'flx-certificates' ),
		'No new people' => __( 'No new people', 'flx-certificates' ),
		'No people registered in this period.' => __( 'No people registered in this period.', 'flx-certificates' ),
		'People' => __( 'People', 'flx-certificates' ),
		'Per certificate' => __( 'Per certificate', 'flx-certificates' ),
		'Phone' => __( 'Phone', 'flx-certificates' ),
		'Referring staff' => __( 'Referring staff', 'flx-certificates' ),
		'Same as the previous period' => __( 'Same as the previous period', 'flx-certificates' ),
		'Sapling goal' => __( 'Sapling goal', 'flx-certificates' ),
		'Saplings' => __( 'Saplings', 'flx-certificates' ),
		'Saplings by day of the week' => __( 'Saplings by day of the week', 'flx-certificates' ),
		'Saplings collected' => __( 'Saplings collected', 'flx-certificates' ),
		'Saplings gifted to date' => __( 'Saplings gifted to date', 'flx-certificates' ),
		'Saplings · certificates' => __( 'Saplings · certificates', 'flx-certificates' ),
		'Sent' => __( 'Sent', 'flx-certificates' ),
		'Start date' => __( 'Start date', 'flx-certificates' ),
		'Table' => __( 'Table', 'flx-certificates' ),
		'Template' => __( 'Template', 'flx-certificates' ),
		'Templates' => __( 'Templates', 'flx-certificates' ),
		'This month' => __( 'This month', 'flx-certificates' ),
		'This year' => __( 'This year', 'flx-certificates' ),
		'Today' => __( 'Today', 'flx-certificates' ),
		'Total' => __( 'Total', 'flx-certificates' ),
		'Top 10' => __( 'Top 10', 'flx-certificates' ),
		'Top sapling donors' => __( 'Top sapling donors', 'flx-certificates' ),
		'Total saplings' => __( 'Total saplings', 'flx-certificates' ),
		'Waiting' => __( 'Waiting', 'flx-certificates' ),
		'Without a referrer' => __( 'Without a referrer', 'flx-certificates' ),
		'Without an event' => __( 'Without an event', 'flx-certificates' ),
		'archived' => __( 'archived', 'flx-certificates' ),
		'new' => __( 'new', 'flx-certificates' ),
	);
}
