<?php
/**
 * Panel erişimi: Personel ve İdareci wp-admin'e girer (WooCommerce müşteri gibi Hesabım'a atmasın),
 * girişten sonra seçilen ekrana gider.
 */

defined( 'ABSPATH' ) || exit;

/** Kullanıcının Flux Roles rolü ('' = yok). Birden fazlaysa flxr_roles() sırasındaki ilki (Yönetici > İdareci > Personel > eklenenler). */
function flxr_user_role( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	if ( ! $user instanceof WP_User || ! $user->exists() ) {
		return '';
	}
	$roles = (array) $user->roles;
	foreach ( array_keys( flxr_roles() ) as $rk ) {
		if ( in_array( $rk, $roles, true ) ) {
			return $rk;
		}
	}
	return '';
}

add_filter(
	'woocommerce_prevent_admin_access',
	function ( $prevent ) {
		return flxr_user_role() ? false : $prevent;
	},
	99
);
add_filter(
	'woocommerce_disable_admin_bar',
	function ( $disable ) {
		return flxr_user_role() ? false : $disable;
	},
	99
);

/** Açılış ekranı seçenekleri: anahtar => array( etiket, adres, gereken yetki ). */
function flxr_home_choices() {
	return array(
		'auto'      => array( __( 'Automatic (first available: Tasks, Certificates, Donations)', 'flx-roles' ), '', '' ),
		'gorev'     => array( __( 'Tasks', 'flx-roles' ), 'admin.php?page=flx-tasks', 'flxg_use' ),
		'sertifika' => array( __( 'Certificates', 'flx-roles' ), 'admin.php?page=flxs', 'flxs_use' ),
		'bagis'     => array( __( 'Donations', 'flx-roles' ), 'admin.php?page=flxb-dashboard', '' ),
		'dashboard' => array( __( 'Dashboard (admin home)', 'flx-roles' ), 'index.php', 'read' ),
		'profile'   => array( __( 'Profile', 'flx-roles' ), 'profile.php', 'read' ),
	);
}

/** Kullanıcının açılış adresi. */
function flxr_home_url( $user ) {
	$role = flxr_user_role( $user );
	if ( ! $role ) {
		return '';
	}
	$choices = flxr_home_choices();
	$home    = (array) flxr_setting( 'home' );
	$want    = isset( $home[ $role ] ) && isset( $choices[ $home[ $role ] ] ) ? $home[ $role ] : 'auto';
	$order   = 'auto' === $want ? array( 'gorev', 'sertifika', 'bagis', 'profile' ) : array( $want, 'profile' );
	foreach ( $order as $k ) {
		if ( flxr_user_can_home( $user, $k ) ) {
			return admin_url( $choices[ $k ][1] );
		}
	}
	return admin_url( 'profile.php' );
}

function flxr_user_can_home( $user, $key ) {
	switch ( $key ) {
		case 'gorev':
			return defined( 'FLXG_VERSION' ) && user_can( $user, 'flxg_use' );
		case 'sertifika':
			return defined( 'FLXS_VERSION' ) && user_can( $user, 'flxs_use' );
		case 'bagis':
			return defined( 'FLXB_VERSION' ) && ( user_can( $user, 'flxb_view_own' ) || user_can( $user, 'flxb_dashboard' ) || user_can( $user, 'flx_bagis_view' ) )
				&& ( flxr_cap_supported( 'flxb_view_own' ) || user_can( $user, 'flx_bagis_view' ) );
		default:
			return user_can( $user, 'read' );
	}
}

add_filter(
	'login_redirect',
	function ( $to, $requested, $user ) {
		if ( ! $user instanceof WP_User || ! flxr_user_role( $user ) ) {
			return $to;
		}
		// Kullanıcı belirli bir panel sayfasına gitmek istiyorsa ona dokunma.
		if ( $requested && false === strpos( $requested, 'wp-admin/profile.php' ) && false !== strpos( $requested, 'wp-admin/' ) && ! preg_match( '#wp-admin/?(index\.php)?$#', $requested ) ) {
			return $to;
		}
		$home = flxr_home_url( $user );
		return $home ? $home : $to;
	},
	99,
	3
);

/* Panel ana sayfası (Başlangıç) seçilmediyse oraya gelen Personel/İdareci açılış ekranına gider. */
add_action(
	'admin_init',
	function () {
		global $pagenow;
		if ( 'index.php' !== $pagenow || wp_doing_ajax() || ! empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$user = wp_get_current_user();
		$role = flxr_user_role( $user );
		if ( ! $role || $user->has_cap( 'edit_posts' ) ) {
			return;
		}
		$home = (array) flxr_setting( 'home' );
		if ( 'dashboard' === ( $home[ $role ] ?? 'auto' ) ) {
			return;
		}
		$url = flxr_home_url( $user );
		if ( $url && false === strpos( $url, 'index.php' ) ) {
			wp_safe_redirect( $url );
			exit;
		}
	},
	0
);
