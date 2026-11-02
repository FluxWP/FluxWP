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
		'label'        => __( 'FluxWP rolleri', 'flx-roles' ),
		'status'       => $miss ? 'error' : 'ok',
		'message'      => $miss
			? sprintf(
				/* translators: %s: role names */
				__( 'Rol eksik: %s. Eklentiyi kapatıp açın.', 'flx-roles' ),
				implode( ', ', $miss )
			)
			: implode( ' · ', $parts ),
		'action_url'   => $url,
		'action_label' => __( 'Roller ve erişim', 'flx-roles' ),
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
		'label'        => __( 'Personel WhatsApp numaraları', 'flx-roles' ),
		'status'       => $nophone ? 'info' : 'ok',
		'message'      => $nophone
			? sprintf(
				/* translators: %s: number of people */
				__( '%s kişinin profilinde WhatsApp numarası yok; asistan bu kişileri tanımaz.', 'flx-roles' ),
				number_format_i18n( $nophone )
			)
			: __( 'Rollerdeki herkesin numarası kayıtlı.', 'flx-roles' ),
		'action_url'   => $url . '#flxr-people',
		'action_label' => __( 'Kişiler', 'flx-roles' ),
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
		'label'        => __( 'Rol yetkilerini uygulayan eklentiler', 'flx-roles' ),
		'status'       => $wait ? 'info' : 'ok',
		'message'      => $wait
			? sprintf(
				/* translators: %s: plugin names */
				__( 'Açık ama henüz uygulanmayan yetkiler var; şu eklentilerin güncellemesi bekleniyor: %s. O zamana kadar bu kişiler ilgili ekranı göremez.', 'flx-roles' ),
				implode( ', ', array_keys( $wait ) )
			)
			: __( 'Açık yetkilerin hepsi uygulanıyor.', 'flx-roles' ),
		'action_url'   => $url,
		'action_label' => __( 'Roller ve erişim', 'flx-roles' ),
	);
	return $checks;
}
