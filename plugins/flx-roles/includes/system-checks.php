<?php
/** Ortak sistem kontrolü (`flx_system_checks`). Dış HTTP yok. */

defined( 'ABSPATH' ) || exit;

add_filter( 'flx_system_checks', 'flxr_system_checks' );
add_filter(
	'flx_system_checks_versions',
	function ( $v ) {
		$v['flx-roles'] = FLXR_VERSION;
		return $v;
	}
);

function flxr_system_checks( $checks ) {
	$checks = is_array( $checks ) ? $checks : array();
	$src    = __( 'Flux Roles', 'flx-roles' );
	$url    = admin_url( 'users.php?page=flx-roles' );
	$counts = count_users();
	$parts  = array();
	$miss   = array();
	foreach ( flxr_roles() as $rk => $r ) {
		if ( ! get_role( $rk ) ) {
			$miss[] = $r['label'];
		}
		$parts[] = $r['label'] . ': ' . number_format_i18n( (int) ( $counts['avail_roles'][ $rk ] ?? 0 ) );
	}
	$checks[] = array(
		'id'           => 'flxr.roles',
		'source'       => $src,
		'group'        => 'Security',
		'label'        => __( 'FluxWP roles', 'flx-roles' ),
		'status'       => $miss ? 'error' : 'ok',
		'message'      => $miss
			? sprintf(
				/* translators: %s: role names */
				__( 'Missing role: %s. Deactivate and reactivate the plugin.', 'flx-roles' ),
				implode( ', ', $miss )
			)
			: implode( ' · ', $parts ),
		'action_url'   => $url,
		'action_label' => __( 'Roles and access', 'flx-roles' ),
	);

	// WhatsApp asistanı için numarası olmayan kişiler.
	$nophone = 0;
	foreach ( array_keys( flxr_roles() ) as $rk ) {
		foreach ( get_users( array( 'role' => $rk, 'fields' => 'ID', 'number' => 500 ) ) as $uid ) {
			$nophone += '' === flxr_user_phone( $uid ) ? 1 : 0;
		}
	}
	$checks[] = array(
		'id'           => 'flxr.phone',
		'source'       => $src,
		'group'        => 'Config',
		'label'        => __( 'Staff WhatsApp numbers', 'flx-roles' ),
		'status'       => $nophone ? 'info' : 'ok',
		'message'      => $nophone
			? sprintf(
				/* translators: %s: number of people */
				__( '%s people have no WhatsApp number on their profile; the assistant won\'t recognize them.', 'flx-roles' ),
				number_format_i18n( $nophone )
			)
			: __( 'Everyone in the roles has a number.', 'flx-roles' ),
		'action_url'   => $url . '#flxr-people',
		'action_label' => __( 'People', 'flx-roles' ),
	);

	// Açık olup henüz uygulanmayan yetkiler.
	$wait = array();
	foreach ( flxr_catalog() as $g ) {
		if ( empty( $g['active'] ) ) {
			continue;
		}
		foreach ( $g['caps'] as $cap => $c ) {
			if ( empty( $c['new'] ) || flxr_cap_supported( $cap ) ) {
				continue;
			}
			foreach ( array_keys( flxr_roles() ) as $rk ) {
				if ( flxr_role_has( $rk, $cap ) ) {
					$wait[ $g['plugin'] ] = true;
				}
			}
		}
	}
	$checks[] = array(
		'id'           => 'flxr.support',
		'source'       => $src,
		'group'        => 'Security',
		'label'        => __( 'Plugins enforcing role permissions', 'flx-roles' ),
		'status'       => $wait ? 'info' : 'ok',
		'message'      => $wait
			? sprintf(
				/* translators: %s: plugin names */
				__( 'Some enabled permissions aren\'t enforced yet; waiting for updates of: %s. Until then these people can\'t see the related screen.', 'flx-roles' ),
				implode( ', ', array_keys( $wait ) )
			)
			: __( 'All enabled permissions are enforced.', 'flx-roles' ),
		'action_url'   => $url,
		'action_label' => __( 'Roles and access', 'flx-roles' ),
	);
	return $checks;
}
