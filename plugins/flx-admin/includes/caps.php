<?php
/**
 * Ayarlar › Yönetim teması › Yetkiler: rol × yetki tablosunun satırları (bilinen yetkiler ve adları).
 * Tablo React ekranında (CapsTable); kaydetme ve yedekten geri yükleme FLXA_Caps_Service'te.
 */
defined( 'ABSPATH' ) || exit;

/** Bilinen yetkilerin okunur adları, gruplara ayrılmış. */
function flxa_caps_known() {
	return array(
		'content' => array(
			'label' => __( 'Content', 'flx-admin' ),
			'caps'  => array(
				'read'                 => __( 'Can access the dashboard', 'flx-admin' ),
				'edit_posts'           => __( 'Can write posts', 'flx-admin' ),
				'publish_posts'        => __( 'Can publish posts', 'flx-admin' ),
				'edit_others_posts'    => __( 'Can edit others\' posts', 'flx-admin' ),
				'delete_posts'         => __( 'Can delete posts', 'flx-admin' ),
				'delete_others_posts'  => __( 'Can delete others\' posts', 'flx-admin' ),
				'edit_pages'           => __( 'Can edit pages', 'flx-admin' ),
				'publish_pages'        => __( 'Can publish pages', 'flx-admin' ),
				'edit_others_pages'    => __( 'Can edit others\' pages', 'flx-admin' ),
				'delete_pages'         => __( 'Can delete pages', 'flx-admin' ),
				'upload_files'         => __( 'Can upload files', 'flx-admin' ),
				'manage_categories'    => __( 'Can manage categories', 'flx-admin' ),
				'moderate_comments'    => __( 'Can moderate comments', 'flx-admin' ),
				'unfiltered_html'      => __( 'Can post unfiltered HTML', 'flx-admin' ),
			),
		),
		'site'    => array(
			'label' => __( 'Site', 'flx-admin' ),
			'caps'  => array(
				'manage_options'     => __( 'Can change site settings', 'flx-admin' ),
				'edit_theme_options' => __( 'Can edit menus and appearance', 'flx-admin' ),
				'list_users'         => __( 'Can see users', 'flx-admin' ),
				'create_users'       => __( 'Can add users', 'flx-admin' ),
				'edit_users'         => __( 'Can edit users', 'flx-admin' ),
				'delete_users'       => __( 'Can delete users', 'flx-admin' ),
				'promote_users'      => __( 'Can change roles', 'flx-admin' ),
				'activate_plugins'   => __( 'Can activate/deactivate plugins', 'flx-admin' ),
				'install_plugins'    => __( 'Can install plugins', 'flx-admin' ),
				'update_plugins'     => __( 'Can update plugins', 'flx-admin' ),
				'update_core'        => __( 'Can update WordPress', 'flx-admin' ),
				'export'             => __( 'Can export', 'flx-admin' ),
				'import'             => __( 'Can import', 'flx-admin' ),
			),
		),
		'woo'     => array(
			'label' => 'WooCommerce',
			'caps'  => array(
				'manage_woocommerce'       => __( 'Can manage WooCommerce', 'flx-admin' ),
				'view_woocommerce_reports' => __( 'Can see reports', 'flx-admin' ),
				'edit_shop_orders'         => __( 'Can edit orders', 'flx-admin' ),
				'edit_products'            => __( 'Can edit products', 'flx-admin' ),
			),
		),
	);
}

/**
 * Tablodaki satırlar: bilinenler (sitede var olanlar) + FluxWP eklentilerinin yetkileri otomatik.
 *
 * @return array<string, array{label: string, caps: array<string, string>}>
 */
function flxa_caps_rows() {
	$all = array();
	foreach ( wp_roles()->roles as $r ) {
		foreach ( (array) ( $r['capabilities'] ?? array() ) as $cap => $on ) {
			$all[ $cap ] = true;
		}
	}
	$groups = array();
	$seen   = array();
	foreach ( flxa_caps_known() as $gk => $g ) {
		$rows = array();
		foreach ( $g['caps'] as $cap => $label ) {
			if ( isset( $all[ $cap ] ) ) {
				$rows[ $cap ] = $label;
				$seen[ $cap ] = true;
			}
		}
		if ( $rows ) {
			$groups[ $gk ] = array( 'label' => $g['label'], 'caps' => $rows );
		}
	}
	$flx = array();
	foreach ( array_keys( $all ) as $cap ) {
		if ( ! isset( $seen[ $cap ] ) && preg_match( '/^(flx[a-z]*_|flx_)/', $cap ) ) {
			$flx[ $cap ] = '';
		}
	}
	ksort( $flx );
	/** Filtre: eklentiler kendi yetkilerine okunur ad verebilir ( 'flxg_manage' => 'Görevleri yönetir' ). */
	$names = (array) apply_filters( 'flxa_cap_labels', array() );
	foreach ( $flx as $cap => $l ) {
		$flx[ $cap ] = isset( $names[ $cap ] ) ? (string) $names[ $cap ] : '';
	}
	if ( $flx ) {
		$groups['flx'] = array( 'label' => __( 'FluxWP plugins', 'flx-admin' ), 'caps' => $flx );
	}
	return $groups;
}
