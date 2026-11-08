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
		'label'        => __( 'Token table', 'flx-app' ),
		'status'       => $ok ? 'ok' : 'error',
		'message'      => $ok ? __( 'Ready.', 'flx-app' ) : __( 'Table missing. Deactivate and reactivate the plugin.', 'flx-app' ),
		'action_url'   => $url,
		'action_label' => __( 'App access', 'flx-app' ),
	);
	$https    = 0 === strpos( home_url(), 'https://' );
	$checks[] = array(
		'id'      => 'flxapp.https',
		'source'  => $src,
		'group'   => 'Security',
		'label'   => __( 'HTTPS', 'flx-app' ),
		'status'  => $https ? 'ok' : 'warning',
		'message' => $https ? __( 'The site address is https; app sign-in is secure.', 'flx-app' ) : __( 'The site address is not https; OAuth tokens travel unencrypted. Use https in production.', 'flx-app' ),
	);
	if ( $ok ) {
		$now      = time();
		$sessions = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT grant_id) FROM {$t} WHERE kind = 'refresh' AND revoked = 0 AND expires > %d", $now ) ); // phpcs:ignore WordPress.DB
		$keys     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE kind = 'key' AND revoked = 0 AND ( expires = 0 OR expires > %d )", $now ) ); // phpcs:ignore WordPress.DB
		$checks[] = array(
			'id'           => 'flxapp.active',
			'source'       => $src,
			'group'        => 'Security',
			'label'        => __( 'Active access', 'flx-app' ),
			'status'       => 'info',
			'message'      => sprintf(
				/* translators: 1: app sessions, 2: API keys */
				__( 'App sessions: %1$s · API keys: %2$s', 'flx-app' ),
				number_format_i18n( $sessions ),
				number_format_i18n( $keys )
			),
			'action_url'   => add_query_arg( 'tab', 'all', $url ),
			'action_label' => __( 'All access', 'flx-app' ),
		);
		$devices  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . flxapp_devices_table() ); // phpcs:ignore WordPress.DB
		$last     = (array) get_option( 'flxapp_push_last', array() );
		$push_ok  = empty( $last['at'] ) || ! empty( $last['sent'] ) || empty( $last['failed'] );
		$checks[] = array(
			'id'           => 'flxapp.push',
			'source'       => $src,
			'group'        => 'Config',
			'label'        => __( 'Push notifications', 'flx-app' ),
			'status'       => ! flxapp_opt( 'push_enabled' ) ? 'info' : ( $push_ok ? 'ok' : 'warning' ),
			'message'      => ! flxapp_opt( 'push_enabled' ) ? __( 'Off.', 'flx-app' ) : ( empty( $last['at'] )
				/* translators: %s: device count */
				? sprintf( __( 'Nothing sent yet. Registered devices: %s', 'flx-app' ), number_format_i18n( $devices ) )
				/* translators: 1: time ago, 2: sent, 3: failed, 4: error */
				: sprintf( __( 'Last send %1$s: %2$s sent, %3$s failed. %4$s', 'flx-app' ), flx_time_ago( (int) $last['at'] ), number_format_i18n( (int) ( $last['sent'] ?? 0 ) ), number_format_i18n( (int) ( $last['failed'] ?? 0 ) ), (string) ( $last['error'] ?? '' ) ) ),
			'action_url'   => admin_url( 'admin.php?page=flxapp-settings&tab=mobile' ),
			'action_label' => __( 'Applications', 'flx-app' ),
		);
	}
	$errors = function_exists( 'flxapp_manifest_errors' ) ? flxapp_manifest_errors() : array();
	$checks[] = array(
		'id'           => 'flxapp.manifest',
		'source'       => $src,
		'group'        => 'Config',
		'label'        => __( 'App screens (manifest)', 'flx-app' ),
		'status'       => $errors ? 'warning' : 'ok',
		'message'      => $errors
			/* translators: %s: module ids with errors */
			? sprintf( __( 'Modules with schema errors: %s. They may open in the web view in the app.', 'flx-app' ), implode( ', ', array_keys( $errors ) ) )
			/* translators: %s: module count */
			: sprintf( __( '%s modules valid.', 'flx-app' ), number_format_i18n( count( flxapp_manifests_raw() ) ) ),
		'action_url'   => admin_url( 'admin.php?page=flxapp-settings&tab=modules' ),
		'action_label' => __( 'Modules', 'flx-app' ),
	);
	$brands   = count( flxapp_brand_clients() );
	$checks[] = array(
		'id'           => 'flxapp.clients',
		'source'       => $src,
		'group'        => 'Security',
		'label'        => __( 'App clients', 'flx-app' ),
		'status'       => flxapp_opt( 'generic_app' ) || $brands ? 'ok' : 'warning',
		'message'      => sprintf(
			/* translators: 1: on/off, 2: brand client count */
			__( 'Main FluxWP app: %1$s · branded apps: %2$s', 'flx-app' ),
			flxapp_opt( 'generic_app' ) ? __( 'on', 'flx-app' ) : __( 'off', 'flx-app' ),
			number_format_i18n( $brands )
		) . ( flxapp_opt( 'dev_redirects' ) ? ' · ' . __( 'Developer mode is on (exp:// redirects); turn it off on the live site.', 'flx-app' ) : '' ),
		'action_url'   => admin_url( 'admin.php?page=flxapp-settings&tab=mobile' ),
		'action_label' => __( 'Applications', 'flx-app' ),
	);
	return $checks;
}
