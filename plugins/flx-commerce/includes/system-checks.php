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
	return $checks;
}
