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
		'message'      => $wc ? sprintf( /* translators: %s: version */ __( 'Etkin, sürüm %s', 'flx-commerce' ), WC()->version ) : __( 'WooCommerce kurulu ya da etkin değil; Flux Commerce çalışmaz.', 'flx-commerce' ),
		'action_url'   => admin_url( 'plugins.php' ),
		'action_label' => __( 'Eklentiler', 'flx-commerce' ),
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
		'label'        => __( 'Havale/EFT', 'flx-commerce' ),
		'status'       => ! $on ? 'info' : ( '' === $iban ? 'error' : 'ok' ),
		'message'      => ! $on
			? ( FLXCM_Bank::deferred() ? __( 'Kapalı; Flux Donate\'in havale yöntemi kullanılıyor.', 'flx-commerce' ) : __( 'Kapalı.', 'flx-commerce' ) )
			: ( '' === $iban ? __( 'Açık ama IBAN yok; ödeme sayfasında görünmez.', 'flx-commerce' ) : FLXCM_Bank::format_iban( $iban ) ),
		'action_url'   => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . FLXCM_Bank::ID ),
		'action_label' => __( 'Ödeme yöntemi', 'flx-commerce' ),
	);
	$built    = (int) get_option( 'flxcm_customers_built' );
	$checks[] = array(
		'id'           => 'flxcm.customers',
		'source'       => $src,
		'group'        => 'Config',
		'label'        => __( 'Müşteri listesi', 'flx-commerce' ),
		'status'       => $built ? 'ok' : 'info',
		'message'      => $built ? sprintf( /* translators: %s: date */ __( 'Son tam tarama: %s', 'flx-commerce' ), wp_date( 'd.m.Y H:i', $built ) ) : __( 'Eski siparişler henüz taranmadı; Müşteriler sayfasında "Yeniden oluştur".', 'flx-commerce' ),
		'action_url'   => flxcm_admin_url( 'customers' ),
		'action_label' => __( 'Müşteriler', 'flx-commerce' ),
	);
	return $checks;
}
