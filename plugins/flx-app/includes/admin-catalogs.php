<?php
/**
 * Catalogs the Application screens read (GraphQL admin service): key permissions, desktop downloads,
 * modules, menu targets and colour names. Loaded in wp-admin and inside a GraphQL request.
 *
 * @package flx-app
 */

defined( 'ABSPATH' ) || exit;

/** @return array<string, string> */
function flxapp_key_cap_choices() {
	return array(
		'read'           => __( 'All users', 'flx-app' ),
		'edit_posts'     => __( 'Users who can edit content', 'flx-app' ),
		'manage_options' => __( 'Administrators only', 'flx-app' ),
	);
}

/**
 * Masaüstü uygulamasının indirme adresi. Güncelleme sunucusunun kalıcı bağlantısı son sürüme yönlendirir.
 * Sunucu Flux Setup'tan (yoksa updater.fluxwp.com), marka `flxapp_desktop_brand` filtresinden.
 *
 * @param string $os macos | windows | windows-msi
 */
/** @return string */
function flxapp_desktop_download_url( $os ) {
	$server = class_exists( 'FLXU_Updater' ) ? FLXU_Updater::server() : 'https://updater.fluxwp.com';
	$brand  = sanitize_key( (string) apply_filters( 'flxapp_desktop_brand', 'flux' ) );
	$url    = untrailingslashit( $server ) . '/v1/fluxapp/' . $brand . '/download/' . $os;
	return (string) apply_filters( 'flxapp_desktop_download_url', $url, $os, $brand );
}

/** Modüller + manifestler, kimliğe göre: [ title, menu, cap, version, kind: manifest|native|web, errors ]. */
/**
 * Modules + manifests by id.
 *
 * @return array<string, array{title: string, menu: string, cap: string, version: string, kind: string, screens?: int, errors: list<string>}>
 */
function flxapp_admin_modules() {
	$out = array();
	foreach ( (array) apply_filters( 'flxapp_modules', array() ) as $id => $m ) {
		$id         = sanitize_key( $id );
		$out[ $id ] = array(
			'title'   => (string) ( $m['title'] ?? $id ),
			'menu'    => (string) ( $m['menu'] ?? '' ),
			'cap'     => (string) ( $m['cap'] ?? '' ),
			'version' => (string) ( $m['version'] ?? '' ),
			'kind'    => 'native',
			'errors'  => array(),
		);
	}
	foreach ( flxapp_manifests_raw() as $id => $m ) {
		$out[ $id ] = array(
			'title'   => (string) ( $m['title'] ?? $id ),
			'menu'    => (string) ( $m['menu'] ?? ( $out[ $id ]['menu'] ?? '' ) ),
			'cap'     => is_string( $m['caps']['view'] ?? null ) ? $m['caps']['view'] : ( $out[ $id ]['cap'] ?? '' ),
			'version' => (string) ( $m['version'] ?? '' ),
			'kind'    => 'manifest',
			'screens' => count( (array) ( $m['screens'] ?? array() ) ),
			'errors'  => flxapp_manifest_validate( $m ),
		);
	}
	return $out;
}

/** Seçim kutuları için hedef kataloğu: grup => [ hedef => ad ]. */
/** @return array<string, array<string, string>> */
function flxapp_target_catalog() {
	$out = array(
		__( 'Application', 'flx-app' ) => array(
			'more'          => __( 'Menu (all items)', 'flx-app' ),
			'search'        => __( 'Search', 'flx-app' ),
			'notifications' => __( 'Notifications', 'flx-app' ),
			'accounts'      => __( 'Accounts', 'flx-app' ),
			'settings'      => __( 'App settings', 'flx-app' ),
		),
	);
	$scr = array();
	foreach ( flxapp_manifests_raw() as $id => $m ) {
		foreach ( (array) ( $m['screens'] ?? array() ) as $sid => $s ) {
			if ( 'detail' !== ( $s['type'] ?? '' ) ) {
				$scr[ 'screen:' . $id . '/' . $sid ] = (string) ( $m['title'] ?? $id ) . ' › ' . (string) ( $s['title'] ?? $sid );
			}
		}
	}
	$out[ __( 'Screens', 'flx-app' ) ] = $scr;
	$mods = array();
	foreach ( (array) apply_filters( 'flxapp_modules', array() ) as $id => $m ) {
		$mods[ 'module:' . sanitize_key( $id ) ] = (string) ( $m['title'] ?? $id );
	}
	if ( $mods ) {
		$out[ __( 'Native modules', 'flx-app' ) ] = $mods;
	}
	$menu = array();
	foreach ( flxapp_user_menu( get_current_user_id() ) as $m ) {
		$menu[ 'menu:' . $m['slug'] ] = $m['title'];
	}
	if ( $menu ) {
		$out[ __( 'Admin menu', 'flx-app' ) ] = $menu;
	}
	return $out;
}

/** @return array<string, string> */
function flxapp_color_labels() {
	return array(
		'primary' => __( 'Primary', 'flx-app' ),
		'accent'  => __( 'Accent', 'flx-app' ),
		'bg'      => __( 'Background', 'flx-app' ),
		'surface' => __( 'Surface', 'flx-app' ),
		'text'    => __( 'Text', 'flx-app' ),
	);
}
