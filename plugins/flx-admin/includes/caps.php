<?php
/**
 * Ayarlar › Yönetim teması › Yetkiler: rol × yetki tablosu. Menüde gizlemekten farklı olarak burada
 * yapılan değişiklik WordPress yetkisini gerçekten değiştirir (add_cap/remove_cap).
 * Yönetici (administrator) sütunu salt okunur. İlk değişiklikten önce tüm rollerin yetkileri
 * `flxa_caps_backup` seçeneğine yedeklenir; "Yedeği geri yükle" ile ilk hâline dönülür.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_flxa_caps_restore', 'flxa_caps_restore' );

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

/** Tablodaki satırlar: bilinenler (sitede var olanlar) + FluxWP eklentilerinin yetkileri otomatik. */
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

function flxa_caps_render() {
	$roles  = wp_roles()->roles;
	$groups = flxa_caps_rows();
	$counts = count_users();
	$users  = (array) ( $counts['avail_roles'] ?? array() );
	$backup = get_option( 'flxa_caps_backup' );

	echo '<p class="flxa-lead">' . esc_html__( 'Changes here are real permissions: a disabled action is not just hidden from the menu, it can\'t be done even by URL. The Administrator role can\'t be changed.', 'flx-admin' ) . '</p>';
	if ( is_array( $backup ) ) {
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=flxa_caps_restore' ), 'flxa_caps_restore' );
		echo '<div class="notice notice-info inline flxa-caps-backup"><p>' . esc_html(
			sprintf(
				/* translators: %s: date */
				__( 'Permissions before the first change are backed up (%s).', 'flx-admin' ),
				flx_date( (int) ( $backup['_time'] ?? 0 ), 'datetime' )
			)
		) . ' <a href="' . esc_url( $url ) . '" onclick="return confirm(this.dataset.c)" data-c="' . esc_attr__( 'Restore all roles to the backup?', 'flx-admin' ) . '">' . esc_html__( 'Restore backup', 'flx-admin' ) . '</a></p></div>';
	}
	echo '<div class="flxa-card flxa-caps-wrap"><div class="flxa-caps-scroll"><table class="flxa-caps"><thead><tr><th class="flxa-caps-name"><input type="search" class="flxa-caps-filter" placeholder="' . esc_attr__( 'Search permissions…', 'flx-admin' ) . '" data-flxa-caps-filter></th>';
	foreach ( $roles as $rk => $r ) {
		echo '<th class="flxa-caps-role' . ( 'administrator' === $rk ? ' is-admin' : '' ) . '"><span>' . esc_html( translate_user_role( $r['name'] ) ) . '</span><small>' . (int) ( $users[ $rk ] ?? 0 ) . ' ' . esc_html__( 'users', 'flx-admin' ) . '</small></th>';
	}
	echo '</tr></thead>';
	foreach ( $groups as $g ) {
		echo '<tbody><tr class="flxa-caps-group"><th colspan="' . ( count( $roles ) + 1 ) . '">' . esc_html( $g['label'] ) . '</th></tr>';
		foreach ( $g['caps'] as $cap => $label ) {
			echo '<tr data-cap="' . esc_attr( $cap . ' ' . strtolower( $label ) ) . '"><th class="flxa-caps-name" scope="row">' . ( '' !== $label ? esc_html( $label ) . ' ' : '' ) . '<code>' . esc_html( $cap ) . '</code></th>';
			foreach ( $roles as $rk => $r ) {
				$on = ! empty( $r['capabilities'][ $cap ] );
				if ( 'administrator' === $rk ) {
					echo '<td class="is-admin"><input type="checkbox" disabled ' . checked( $on, true, false ) . ' aria-label="' . esc_attr( $cap ) . '"></td>';
				} else {
					echo '<td><input type="checkbox" name="caps[' . esc_attr( $rk ) . '][' . esc_attr( $cap ) . ']" value="1" ' . checked( $on, true, false ) . ' aria-label="' . esc_attr( translate_user_role( $r['name'] ) . ': ' . $cap ) . '"></td>';
				}
			}
			echo '</tr>';
		}
		echo '</tbody>';
	}
	echo '</table></div></div>';
	echo '<input type="hidden" name="caps_shown" value="' . esc_attr( implode( ',', array_merge( ...array_values( array_map( static fn( $g ) => array_keys( $g['caps'] ), $groups ) ) ) ) ) . '">';
	echo '<script>(function(){var f=document.querySelector("[data-flxa-caps-filter]");if(!f)return;f.addEventListener("input",function(){var q=f.value.toLowerCase().trim();document.querySelectorAll(".flxa-caps tr[data-cap]").forEach(function(tr){tr.hidden=q&&tr.getAttribute("data-cap").indexOf(q)<0;});});})();</script>';
}

/** Kaydet: yalnız tabloda gösterilen yetkiler, yönetici dışındaki roller. */
function flxa_caps_save() {
	$shown = array_filter( array_map( 'sanitize_key', explode( ',', (string) wp_unslash( $_POST['caps_shown'] ?? '' ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$in    = isset( $_POST['caps'] ) && is_array( $_POST['caps'] ) ? wp_unslash( $_POST['caps'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( ! $shown ) {
		return;
	}
	if ( ! is_array( get_option( 'flxa_caps_backup' ) ) ) {
		$b = array( '_time' => time() );
		foreach ( wp_roles()->roles as $rk => $r ) {
			$b[ $rk ] = (array) ( $r['capabilities'] ?? array() );
		}
		update_option( 'flxa_caps_backup', $b, false );
	}
	foreach ( wp_roles()->roles as $rk => $r ) {
		if ( 'administrator' === $rk ) {
			continue;
		}
		$role = get_role( $rk );
		if ( ! $role ) {
			continue;
		}
		foreach ( $shown as $cap ) {
			$want = ! empty( $in[ $rk ][ $cap ] );
			$has  = ! empty( $role->capabilities[ $cap ] );
			if ( $want && ! $has ) {
				$role->add_cap( $cap );
			} elseif ( ! $want && $has ) {
				$role->remove_cap( $cap );
			}
		}
	}
}

function flxa_caps_restore() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission.', 'flx-admin' ), 403 );
	}
	check_admin_referer( 'flxa_caps_restore' );
	$b = get_option( 'flxa_caps_backup' );
	if ( is_array( $b ) ) {
		foreach ( $b as $rk => $caps ) {
			$role = '_time' === $rk ? null : get_role( $rk );
			if ( ! $role || 'administrator' === $rk ) {
				continue;
			}
			foreach ( array_keys( $role->capabilities ) as $cap ) {
				if ( ! isset( $caps[ $cap ] ) ) {
					$role->remove_cap( $cap );
				}
			}
			foreach ( (array) $caps as $cap => $on ) {
				$role->add_cap( $cap, (bool) $on );
			}
		}
		delete_option( 'flxa_caps_backup' );
	}
	wp_safe_redirect( add_query_arg( array( 'tab' => 'caps', 'updated' => 1 ), flxa_settings_url() ) );
	exit;
}
