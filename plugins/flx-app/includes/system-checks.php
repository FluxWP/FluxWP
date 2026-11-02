<?php
/** Ortak sistem kontrolü (`flx_system_checks`). Dış HTTP yok. */

defined( 'ABSPATH' ) || exit;

add_filter( 'flx_system_checks', 'flxapp_system_checks' );
add_filter(
	'flx_system_checks_versions',
	function ( $v ) {
		$v['flx-app'] = FLXAPP_VERSION;
		return $v;
	}
);

function flxapp_system_checks( $checks ) {
	global $wpdb;
	$checks = is_array( $checks ) ? $checks : array();
	$src    = __( 'Flux App', 'flx-app' );
	$url    = admin_url( 'users.php?page=flx-app' );
	$t      = flxapp_table();
	$ok     = $t === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ); // phpcs:ignore WordPress.DB

	$checks[] = array(
		'id'           => 'flxapp.table',
		'source'       => $src,
		'group'        => 'Security',
		'label'        => __( 'Belirteç tablosu', 'flx-app' ),
		'status'       => $ok ? 'ok' : 'error',
		'message'      => $ok ? __( 'Hazır.', 'flx-app' ) : __( 'Tablo yok. Eklentiyi kapatıp açın.', 'flx-app' ),
		'action_url'   => $url,
		'action_label' => __( 'Uygulama erişimi', 'flx-app' ),
	);
	$https    = 0 === strpos( home_url(), 'https://' );
	$checks[] = array(
		'id'      => 'flxapp.https',
		'source'  => $src,
		'group'   => 'Security',
		'label'   => __( 'HTTPS', 'flx-app' ),
		'status'  => $https ? 'ok' : 'warning',
		'message' => $https ? __( 'Site adresi https; uygulama girişi güvenli.', 'flx-app' ) : __( 'Site adresi https değil; OAuth belirteçleri şifresiz gider. Canlıda https kullanın.', 'flx-app' ),
	);
	if ( $ok ) {
		$now      = time();
		$sessions = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT grant_id) FROM {$t} WHERE kind = 'refresh' AND revoked = 0 AND expires > %d", $now ) ); // phpcs:ignore WordPress.DB
		$keys     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE kind = 'key' AND revoked = 0 AND ( expires = 0 OR expires > %d )", $now ) ); // phpcs:ignore WordPress.DB
		$checks[] = array(
			'id'           => 'flxapp.active',
			'source'       => $src,
			'group'        => 'Security',
			'label'        => __( 'Etkin erişimler', 'flx-app' ),
			'status'       => 'info',
			'message'      => sprintf(
				/* translators: 1: app sessions, 2: API keys */
				__( 'Uygulama oturumu: %1$s · API anahtarı: %2$s', 'flx-app' ),
				number_format_i18n( $sessions ),
				number_format_i18n( $keys )
			),
			'action_url'   => add_query_arg( 'tab', 'all', $url ),
			'action_label' => __( 'Tüm erişimler', 'flx-app' ),
		);
	}
	return $checks;
}
