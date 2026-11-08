<?php
/** Ortak sistem kontrolü (`flx_system_checks`). Dış HTTP yok. */

defined( 'ABSPATH' ) || exit;

add_filter( 'flx_system_checks', 'flxcrm_system_checks' );
add_filter(
	'flx_system_checks_versions',
	function ( $v ) {
		$v['flx-crm'] = FLXCRM_VERSION;
		return $v;
	}
);

function flxcrm_system_checks( $checks ) {
	$checks = is_array( $checks ) ? $checks : array();
	$src    = __( 'Flux CRM', 'flx-crm' );

	$sources = FLXCRM_Sync::sources();
	$names   = array();
	foreach ( $sources as $s ) {
		$names[] = $s[0];
	}
	$checks[] = array(
		'id'           => 'flxcrm.sources',
		'source'       => $src,
		'group'        => 'Config',
		'label'        => __( 'Contact sources', 'flx-crm' ),
		'status'       => $names ? 'ok' : 'warn',
		'message'      => $names ? implode( ', ', $names ) : __( 'No source brings in contacts (Flux Donate, WooCommerce, Flux Chat and Flux Forms are off or not installed). Contacts can only be added by hand.', 'flx-crm' ),
		'action_url'   => flxcrm_admin_url( 'settings' ),
		'action_label' => __( 'Settings', 'flx-crm' ),
	);

	$synced   = (int) get_option( 'flxcrm_synced_at' );
	$checks[] = array(
		'id'           => 'flxcrm.sync',
		'source'       => $src,
		'group'        => 'Config',
		'label'        => __( 'First sync', 'flx-crm' ),
		'status'       => $synced ? 'ok' : 'info',
		'message'      => $synced ? sprintf( /* translators: %s: date */ __( 'Last full sync: %s', 'flx-crm' ), wp_date( 'd.m.Y H:i', $synced ) ) : __( 'Past donations, orders and chats have not been imported yet. CRM › Settings › "Sync everything".', 'flx-crm' ),
		'action_url'   => flxcrm_admin_url( 'settings' ),
		'action_label' => __( 'Sync', 'flx-crm' ),
	);

	$scored   = (int) get_option( 'flxcrm_scored_at' );
	$late     = $scored && $scored < time() - 3 * DAY_IN_SECONDS;
	$checks[] = array(
		'id'      => 'flxcrm.scoring',
		'source'  => $src,
		'group'   => 'Cron',
		'label'   => __( 'RFM scoring', 'flx-crm' ),
		'status'  => ! $scored ? 'info' : ( $late ? 'warn' : 'ok' ),
		'message' => ! $scored ? __( 'Not calculated yet.', 'flx-crm' ) : ( $late ? sprintf( /* translators: %s: date */ __( 'Last calculated %s. The daily job (WP-Cron) may not be running; add a server cron or recalculate by hand.', 'flx-crm' ), wp_date( 'd.m.Y H:i', $scored ) ) : sprintf( /* translators: %s: date */ __( 'Last calculated: %s', 'flx-crm' ), wp_date( 'd.m.Y H:i', $scored ) ) ),
	);

	$fx = FLXCRM_Events::unconverted();
	if ( $fx ) {
		$checks[] = array(
			'id'      => 'flxcrm.currency',
			'source'  => $src,
			'group'   => 'Config',
			'label'   => __( 'Foreign currency payments', 'flx-crm' ),
			'status'  => 'warn',
			/* translators: 1: count, 2: currency */
			'message' => sprintf( __( '%1$s payments are in a currency other than %2$s and have no exchange rate; they count towards frequency but not towards total value. Use the `flxcrm_fx_rate` filter to supply a rate.', 'flx-crm' ), number_format_i18n( $fx ), flxcrm_currency() ),
		);
	}
	return $checks;
}
