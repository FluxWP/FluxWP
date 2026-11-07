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
		$devices  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . flxapp_devices_table() ); // phpcs:ignore WordPress.DB
		$last     = (array) get_option( 'flxapp_push_last', array() );
		$push_ok  = empty( $last['at'] ) || ! empty( $last['sent'] ) || empty( $last['failed'] );
		$checks[] = array(
			'id'           => 'flxapp.push',
			'source'       => $src,
			'group'        => 'Config',
			'label'        => __( 'Push bildirimleri', 'flx-app' ),
			'status'       => ! flxapp_opt( 'push_enabled' ) ? 'info' : ( $push_ok ? 'ok' : 'warning' ),
			'message'      => ! flxapp_opt( 'push_enabled' ) ? __( 'Kapalı.', 'flx-app' ) : ( empty( $last['at'] )
				/* translators: %s: device count */
				? sprintf( __( 'Henüz gönderim yok. Kayıtlı cihaz: %s', 'flx-app' ), number_format_i18n( $devices ) )
				/* translators: 1: time ago, 2: sent, 3: failed, 4: error */
				: sprintf( __( 'Son gönderim %1$s: %2$s gönderildi, %3$s hata. %4$s', 'flx-app' ), flx_time_ago( (int) $last['at'] ), number_format_i18n( (int) ( $last['sent'] ?? 0 ) ), number_format_i18n( (int) ( $last['failed'] ?? 0 ) ), (string) ( $last['error'] ?? '' ) ) ),
			'action_url'   => admin_url( 'admin.php?page=flxapp-settings&tab=mobile' ),
			'action_label' => __( 'Uygulamalar', 'flx-app' ),
		);
	}
	$errors = function_exists( 'flxapp_manifest_errors' ) ? flxapp_manifest_errors() : array();
	$checks[] = array(
		'id'           => 'flxapp.manifest',
		'source'       => $src,
		'group'        => 'Config',
		'label'        => __( 'Uygulama ekranları (manifest)', 'flx-app' ),
		'status'       => $errors ? 'warning' : 'ok',
		'message'      => $errors
			/* translators: %s: module ids with errors */
			? sprintf( __( 'Şema hatası olan modüller: %s. Bu modüller uygulamada web görünümünde açılabilir.', 'flx-app' ), implode( ', ', array_keys( $errors ) ) )
			/* translators: %s: module count */
			: sprintf( __( '%s modül geçerli.', 'flx-app' ), number_format_i18n( count( flxapp_manifests_raw() ) ) ),
		'action_url'   => admin_url( 'admin.php?page=flxapp-settings&tab=modules' ),
		'action_label' => __( 'Modüller', 'flx-app' ),
	);
	$brands   = count( flxapp_brand_clients() );
	$checks[] = array(
		'id'           => 'flxapp.clients',
		'source'       => $src,
		'group'        => 'Security',
		'label'        => __( 'Uygulama istemcileri', 'flx-app' ),
		'status'       => flxapp_opt( 'generic_app' ) || $brands ? 'ok' : 'warning',
		'message'      => sprintf(
			/* translators: 1: on/off, 2: brand client count */
			__( 'Ana FluxWP uygulaması: %1$s · markalı uygulama: %2$s', 'flx-app' ),
			flxapp_opt( 'generic_app' ) ? __( 'açık', 'flx-app' ) : __( 'kapalı', 'flx-app' ),
			number_format_i18n( $brands )
		) . ( flxapp_opt( 'dev_redirects' ) ? ' · ' . __( 'Geliştirici modu açık (exp:// dönüşleri); canlıda kapatın.', 'flx-app' ) : '' ),
		'action_url'   => admin_url( 'admin.php?page=flxapp-settings&tab=mobile' ),
		'action_label' => __( 'Uygulamalar', 'flx-app' ),
	);
	return $checks;
}
