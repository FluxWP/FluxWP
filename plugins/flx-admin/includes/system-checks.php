<?php
/** Ortak sistem kontrolü (`flx_system_checks`) ve izinli düzeltmeler (`flx_system_fixes`). Dış HTTP yok. */
defined( 'ABSPATH' ) || exit;

add_filter( 'flx_system_checks', 'flxa_system_checks' );
add_filter( 'flx_system_fixes', 'flxa_system_fixes' );
add_filter( 'flx_system_checks_versions', 'flxa_system_versions' );

function flxa_system_versions( $v ) {
	$v['flx-admin'] = FLXA_VERSION;
	return $v;
}

function flxa_system_checks( $checks ) {
	$src = 'Flux Admin';
	$url = flxa_settings_url();

	$checks[] = array(
		'id'      => 'flxa.enabled',
		'source'  => $src,
		'group'   => 'Config',
		'label'   => __( 'Admin theme', 'flx-admin' ),
		'status'  => flxa_enabled() ? 'ok' : 'info',
		'message' => flxa_enabled() ? __( 'On. Role-based menu, bottom bar and light/dark theme are active.', 'flx-admin' ) : __( 'Off; the standard WordPress dashboard is shown.', 'flx-admin' ),
		'action_url'   => $url,
		'action_label' => __( 'Settings', 'flx-admin' ),
	);

	if ( flxa_setting( 'pwa' ) ) {
		$ok       = flxa_pwa_possible();
		$checks[] = array(
			'id'      => 'flxa.pwa.https',
			'source'  => $src,
			'group'   => 'Config',
			'label'   => __( 'Install as an app (PWA)', 'flx-admin' ),
			'status'  => $ok ? 'ok' : 'warning',
			'message' => $ok ? __( 'HTTPS is on; the dashboard can be installed as an app on phones and computers.', 'flx-admin' ) : __( 'The site is not HTTPS; browsers do not allow installing a PWA. Set the addresses to https:// in Settings › General.', 'flx-admin' ),
			'action_url'   => $ok ? '' : admin_url( 'options-general.php' ),
			'action_label' => $ok ? '' : __( 'General settings', 'flx-admin' ),
		);
		$checks[] = array(
			'id'      => 'flxa.pwa.icon',
			'source'  => $src,
			'group'   => 'Config',
			'label'   => __( 'App icon', 'flx-admin' ),
			'status'  => get_option( 'site_icon' ) ? 'ok' : 'info',
			'message' => get_option( 'site_icon' ) ? __( 'Using the site icon.', 'flx-admin' ) : __( 'No site icon; using the Flux Admin icon. You can change it in Appearance › Customize › Site Identity.', 'flx-admin' ),
		);
	}

	$menus    = flxa_menus_option();
	$n        = count( (array) ( $menus['roles'] ?? array() ) );
	$cat      = get_option( 'flxa_menu_catalog', array() );
	$checks[] = array(
		'id'      => 'flxa.menu',
		'source'  => $src,
		'group'   => 'Config',
		'label'   => __( 'Role-based menu', 'flx-admin' ),
		'status'  => 'ok',
		'message' => $n
			/* translators: %s: role count */
			? sprintf( _n( '%s role has a custom menu layout; other roles use the default grouped menu.', '%s roles have custom menu layouts; other roles use the default grouped menu.', $n, 'flx-admin' ), number_format_i18n( $n ) )
			: __( 'All roles see the default grouped menu.', 'flx-admin' ),
		'action_url'   => flxa_settings_url( 'menu' ),
		'action_label' => __( 'Edit menu', 'flx-admin' ),
	);
	if ( empty( $cat['items'] ) ) {
		$checks[] = array(
			'id'      => 'flxa.menu.catalog',
			'source'  => $src,
			'group'   => 'Config',
			'label'   => __( 'Menu list', 'flx-admin' ),
			'status'  => 'info',
			'message' => __( 'The menu editor\'s list is built when an administrator opens the dashboard.', 'flx-admin' ),
		);
	}

	$hb       = (int) flxa_setting( 'heartbeat' );
	$checks[] = array(
		'id'      => 'flxa.live',
		'source'  => $src,
		'group'   => 'Config',
		'label'   => __( 'Live counters', 'flx-admin' ),
		'status'  => $hb < 30 ? 'warning' : 'ok',
		/* translators: %s: seconds */
		'message' => $hb < 30 ? sprintf( __( 'Heartbeat is %s s; Hostinger may temporarily block the IP under heavy load. 60 s is recommended.', 'flx-admin' ), $hb ) : sprintf( __( 'Badges refresh every %s s via WordPress Heartbeat (no extra requests).', 'flx-admin' ), $hb ),
		'action_url'   => $hb < 30 ? $url : '',
		'action_label' => $hb < 30 ? __( 'Settings', 'flx-admin' ) : '',
	);
	return $checks;
}

function flxa_system_fixes( $fixes ) {
	$save = function ( $key, $value ) {
		$s         = get_option( 'flxa_settings', array() );
		$s         = is_array( $s ) ? $s : array();
		$s[ $key ] = $value;
		update_option( 'flxa_settings', $s );
	};
	$fixes['flxa.enabled'] = array(
		'label'       => __( 'Turn the admin theme on/off', 'flx-admin' ),
		'description' => __( '1 = on, 0 = standard WordPress dashboard.', 'flx-admin' ),
		'source'      => 'Flux Admin',
		'checks'      => array( 'flxa.enabled' ),
		'value'       => array( 'type' => 'enum', 'enum' => array( '1', '0' ) ),
		'current'     => function () {
			return flxa_enabled() ? '1' : '0';
		},
		'run'         => function ( $v ) use ( $save ) {
			$save( 'enabled', '1' === (string) $v ? 1 : 0 );
			return '1' === (string) $v ? __( 'Admin theme turned on.', 'flx-admin' ) : __( 'Admin theme turned off.', 'flx-admin' );
		},
	);
	$fixes['flxa.heartbeat'] = array(
		'label'       => __( 'Live counter interval', 'flx-admin' ),
		'description' => __( 'Seconds (30, 60 or 120).', 'flx-admin' ),
		'source'      => 'Flux Admin',
		'checks'      => array( 'flxa.live' ),
		'value'       => array( 'type' => 'enum', 'enum' => array( '30', '60', '120' ) ),
		'current'     => function () {
			return (string) (int) flxa_setting( 'heartbeat' );
		},
		'run'         => function ( $v ) use ( $save ) {
			if ( ! in_array( (string) $v, array( '30', '60', '120' ), true ) ) {
				throw new Exception( 'Geçersiz değer.' );
			}
			$save( 'heartbeat', (int) $v );
			/* translators: %s: seconds */
			return sprintf( __( 'Interval set to %s s.', 'flx-admin' ), (int) $v );
		},
	);
	$fixes['flxa.pwa'] = array(
		'label'       => __( 'PWA (install as an app)', 'flx-admin' ),
		'description' => __( '1 = on, 0 = off (the registered service worker removes itself).', 'flx-admin' ),
		'source'      => 'Flux Admin',
		'checks'      => array( 'flxa.pwa.https' ),
		'value'       => array( 'type' => 'enum', 'enum' => array( '1', '0' ) ),
		'current'     => function () {
			return flxa_setting( 'pwa' ) ? '1' : '0';
		},
		'run'         => function ( $v ) use ( $save ) {
			$save( 'pwa', '1' === (string) $v ? 1 : 0 );
			return '1' === (string) $v ? __( 'PWA turned on.', 'flx-admin' ) : __( 'PWA turned off.', 'flx-admin' );
		},
	);
	$roles = array_keys( (array) ( flxa_menus_option()['roles'] ?? array() ) );
	if ( $roles ) {
		$fixes['flxa.menu_reset'] = array(
			'label'       => __( 'Reset a role\'s menu to default', 'flx-admin' ),
			'description' => __( 'Role key (e.g. editor).', 'flx-admin' ),
			'source'      => 'Flux Admin',
			'checks'      => array( 'flxa.menu' ),
			'value'       => array( 'type' => 'enum', 'enum' => $roles ),
			'current'     => function () use ( $roles ) {
				return implode( ', ', $roles );
			},
			'run'         => function ( $v ) {
				$o = flxa_menus_option();
				if ( ! isset( $o['roles'][ $v ] ) ) {
					throw new Exception( 'Bu rolün özel menüsü yok.' );
				}
				unset( $o['roles'][ $v ] );
				update_option( 'flxa_menus', $o );
				/* translators: %s: role */
				return sprintf( __( '%s menu reset to default.', 'flx-admin' ), $v );
			},
		);
	}
	return $fixes;
}
