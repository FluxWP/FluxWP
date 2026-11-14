<?php
/** Ortak sistem kontrolü (`flx_system_checks`). Dış HTTP yok. */

defined( 'ABSPATH' ) || exit;

add_filter( 'flx_system_checks', 'flxcm_system_checks' );
add_filter(
	'flx_system_checks_versions',
	function ( $v ) {
		$v['flx-commerce'] = FLXCM_VERSION;
		return $v;
	}
);

function flxcm_system_checks( $checks ) {
	$checks = is_array( $checks ) ? $checks : array();
	$src    = __( 'Flux Commerce', 'flx-commerce' );
	$wc     = flxcm_wc_ready();
	$checks[] = array(
		'id'           => 'flxcm.woocommerce',
		'source'       => $src,
		'group'        => 'Config',
		'label'        => __( 'WooCommerce', 'flx-commerce' ),
		'status'       => $wc ? 'ok' : 'error',
		'message'      => $wc ? sprintf( /* translators: %s: version */ __( 'Active, version %s', 'flx-commerce' ), WC()->version ) : __( 'WooCommerce is not installed or not active; Flux Commerce does not work.', 'flx-commerce' ),
		'action_url'   => admin_url( 'plugins.php' ),
		'action_label' => __( 'Plugins', 'flx-commerce' ),
	);
	if ( ! $wc ) {
		return $checks;
	}
	$gw   = get_option( 'woocommerce_flxcm_bank_settings', array() );
	$on   = is_array( $gw ) && 'yes' === ( $gw['enabled'] ?? 'no' );
	$iban = FLXCM_Bank::option( 'iban' );
	$checks[] = array(
		'id'           => 'flxcm.bank',
		'source'       => $src,
		'group'        => 'Config',
		'label'        => __( 'Bank transfer', 'flx-commerce' ),
		'status'       => ! $on ? 'info' : ( '' === $iban ? 'error' : 'ok' ),
		'message'      => ! $on
			? ( FLXCM_Bank::deferred() ? __( 'Off; Flux Donate\'s bank transfer method is used.', 'flx-commerce' ) : __( 'Off.', 'flx-commerce' ) )
			: ( '' === $iban ? __( 'On but no IBAN; it does not show at checkout.', 'flx-commerce' ) : FLXCM_Bank::format_iban( $iban ) ),
		'action_url'   => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . FLXCM_Bank::ID ),
		'action_label' => __( 'Payment method', 'flx-commerce' ),
	);
	$built    = (int) get_option( 'flxcm_customers_built' );
	$checks[] = array(
		'id'           => 'flxcm.customers',
		'source'       => $src,
		'group'        => 'Config',
		'label'        => __( 'Customer list', 'flx-commerce' ),
		'status'       => $built ? 'ok' : 'info',
		'message'      => $built ? sprintf( /* translators: %s: date */ __( 'Last full scan: %s', 'flx-commerce' ), wp_date( 'd.m.Y H:i', $built ) ) : __( 'Older orders are not scanned yet; press "Rebuild" on the Customers page.', 'flx-commerce' ),
		'action_url'   => flxcm_admin_url( 'customers' ),
		'action_label' => __( 'Customers', 'flx-commerce' ),
	);
	global $wpdb;
	$subs = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . FLXCM_Subscriptions::table() . " WHERE status IN ('active','past_due','cancelled')" ); // phpcs:ignore WordPress.DB
	if ( $subs ) {
		$last     = (int) get_option( FLXCM_Subscriptions::HEARTBEAT );
		$late     = ! $last || time() - $last > 6 * HOUR_IN_SECONDS;
		$checks[] = array(
			'id'           => 'flxcm.subscriptions',
			'source'       => $src,
			'group'        => 'Cron',
			'label'        => __( 'Subscription renewals', 'flx-commerce' ),
			'status'       => $late ? 'warning' : 'ok',
			'message'      => $late
				? __( 'The renewal job has not run for more than 6 hours. Check the server cron (wp-cron.php); renewal orders and reminders wait until it runs.', 'flx-commerce' )
				/* translators: 1: subscription count, 2: time ago */
				: sprintf( __( '%1$s open subscriptions. Last run: %2$s', 'flx-commerce' ), number_format_i18n( $subs ), flxcm_ago( $last ) ),
			'action_url'   => flxcm_admin_url( 'subscriptions' ),
			'action_label' => __( 'Subscriptions', 'flx-commerce' ),
		);
	}
	return $checks;
}
