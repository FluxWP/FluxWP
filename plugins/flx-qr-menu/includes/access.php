<?php
/**
 * Yetkiler: menü yöneticisi rolü, menü başına erişim, yöneticinin sade paneli ve kendi medyası.
 *
 * - flxq_manage_menus: tüm menüler, anahtar, ayarlar (manage_options sahibine otomatik).
 * - flxq_edit_menus: yalnız açtığı ya da kendisine atanan menüler (Menü yöneticisi rolü).
 * - flxq_create_menus / flxq_set_address: kullanıcı başına verilir (Ayarlar › Yetkiler ya da profil).
 *
 * @package FLX_QR_Menu
 */

defined( 'ABSPATH' ) || exit;

/** Kullanıcı başına verilebilen yetkiler. */
function flxq_user_caps() {
	return array(
		'flxq_create_menus' => __( 'Menü açabilir', 'flx-qr-menu' ),
		'flxq_set_address'  => __( 'Adres/alt alan adı değiştirebilir', 'flx-qr-menu' ),
	);
}

function flxq_is_admin_user( $uid = 0 ) {
	return $uid ? user_can( (int) $uid, FLXQ_CAP ) : current_user_can( FLXQ_CAP );
}

/** @return int[] Menüyü düzenleyebilen (atanmış) kullanıcılar. */
function flxq_menu_editors( $id ) {
	$ids = get_post_meta( (int) $id, '_flxq_editors', true );
	return array_values( array_unique( array_filter( array_map( 'intval', is_array( $ids ) ? $ids : array() ) ) ) );
}

/** Kullanıcı bu menüyü görüp düzenleyebilir mi? Yönetici her menüyü; menü yöneticisi açtığı ya da atandığı menüyü. */
function flxq_can_edit_menu( $id, $uid = 0 ) {
	$uid = $uid ? (int) $uid : get_current_user_id();
	if ( ! $uid ) {
		return false;
	}
	if ( user_can( $uid, FLXQ_CAP ) ) {
		return true;
	}
	if ( ! user_can( $uid, FLXQ_EDIT_CAP ) ) {
		return false;
	}
	$post = flxq_get_post( $id );
	if ( ! $post ) {
		return false;
	}
	return (int) $post->post_author === $uid || in_array( $uid, flxq_menu_editors( $id ), true );
}

function flxq_can_create() {
	return current_user_can( 'flxq_create_menus' ) && current_user_can( FLXQ_EDIT_CAP );
}

function flxq_can_set_address( $id = 0 ) {
	return current_user_can( 'flxq_set_address' ) && ( ! $id || flxq_can_edit_menu( $id ) );
}

/** Çöpe atma / geri alma: yönetici her menüyü, menü yöneticisi yalnız kendi açtığını. */
function flxq_can_trash( $id ) {
	if ( current_user_can( FLXQ_CAP ) ) {
		return true;
	}
	$post = flxq_get_post( $id );
	return $post && current_user_can( FLXQ_EDIT_CAP ) && (int) $post->post_author === get_current_user_id();
}

/** @return int[] Geçerli kullanıcının görebildiği menüler. */
function flxq_visible_menu_ids( $status = 'publish' ) {
	$ids = flxq_menu_ids( $status );
	if ( current_user_can( FLXQ_CAP ) ) {
		return $ids;
	}
	return array_values( array_filter( $ids, 'flxq_can_edit_menu' ) );
}

/** Menü yöneticisi rolündeki kullanıcılar (Yetkiler tablosu, Düzenleyenler listesi). @return WP_User[] */
function flxq_managers() {
	return get_users(
		array(
			'role__in' => array( FLXQ_ROLE ),
			'orderby'  => 'display_name',
			'number'   => 200,
		)
	);
}

function flxq_set_user_caps( $uid, array $caps ) {
	$user = get_userdata( (int) $uid );
	if ( ! $user ) {
		return;
	}
	foreach ( array_keys( flxq_user_caps() ) as $cap ) {
		$on  = ! empty( $caps[ $cap ] );
		$has = ! empty( $user->caps[ $cap ] );
		if ( $on && ! $has ) {
			$user->add_cap( $cap );
		} elseif ( ! $on && isset( $user->caps[ $cap ] ) ) {
			$user->remove_cap( $cap );
		}
	}
}

/** Paneli sade görmesi gereken kullanıcı: menü yöneticisi (yazı düzenleyemeyen, tüm menüleri yönetemeyen). */
function flxq_is_restricted_user() {
	return is_user_logged_in() && current_user_can( FLXQ_EDIT_CAP ) && ! current_user_can( FLXQ_CAP ) && ! current_user_can( 'edit_posts' );
}

/* ---------- Menü yöneticisinin paneli ---------- */

add_action( 'admin_menu', 'flxq_restricted_menu', 999 );
add_action( 'admin_bar_menu', 'flxq_restricted_admin_bar', 999 );
add_filter( 'login_redirect', 'flxq_login_redirect', 20, 3 );
add_action( 'wp_dashboard_setup', 'flxq_dashboard_widget' );
// WooCommerce, edit_posts yetkisi olmayanı wp-admin'den "Hesabım"a gönderir ve yönetim çubuğunu gizler:
// menü yöneticisi panele girebilsin.
add_filter( 'woocommerce_prevent_admin_access', 'flxq_wc_allow_admin', 20 );
add_filter( 'woocommerce_disable_admin_bar', 'flxq_wc_allow_admin', 20 );
add_filter( 'woocommerce_login_redirect', 'flxq_wc_login_redirect', 20, 2 );

function flxq_wc_allow_admin( $block ) {
	return current_user_can( FLXQ_EDIT_CAP ) ? false : $block;
}

/** WooCommerce'in "Hesabım" girişinden gelen menü yöneticisi de QR Menüler'e gider. */
function flxq_wc_login_redirect( $redirect, $user ) {
	if ( $user instanceof WP_User && $user->has_cap( FLXQ_EDIT_CAP ) && ! $user->has_cap( FLXQ_CAP ) && ! $user->has_cap( 'edit_posts' ) ) {
		return admin_url( 'admin.php?page=flx-qr-menu' );
	}
	return $redirect;
}

/** Yalnız Panel, Medya, QR Menüler ve Profil kalır. */
function flxq_restricted_menu() {
	global $menu;
	if ( ! flxq_is_restricted_user() || ! is_array( $menu ) ) {
		return;
	}
	$keep = array( 'index.php', 'upload.php', 'flx-qr-menu', 'profile.php' );
	foreach ( $menu as $item ) {
		if ( isset( $item[2] ) && ! in_array( $item[2], $keep, true ) ) {
			remove_menu_page( $item[2] );
		}
	}
}

function flxq_restricted_admin_bar( $bar ) {
	if ( ! flxq_is_restricted_user() ) {
		return;
	}
	foreach ( array( 'wp-logo', 'comments', 'new-content', 'updates', 'customize', 'search' ) as $node ) {
		$bar->remove_node( $node );
	}
}

/** Girişte doğrudan QR Menüler açılır. */
function flxq_login_redirect( $redirect_to, $requested, $user ) {
	if ( ! $user instanceof WP_User || ! $user->has_cap( FLXQ_EDIT_CAP ) || $user->has_cap( FLXQ_CAP ) || $user->has_cap( 'edit_posts' ) ) {
		return $redirect_to;
	}
	if ( '' === (string) $requested || admin_url() === $requested || admin_url( '/' ) === $requested ) {
		return admin_url( 'admin.php?page=flx-qr-menu' );
	}
	return $redirect_to;
}

/** Menü yöneticisinin panosunda kendi menüleri. */
function flxq_dashboard_widget() {
	if ( ! flxq_is_restricted_user() ) {
		return;
	}
	wp_add_dashboard_widget(
		'flxq_my_menus',
		__( 'QR Menülerim', 'flx-qr-menu' ),
		function () {
			$ids = flxq_visible_menu_ids();
			if ( ! $ids ) {
				echo '<p>' . esc_html__( 'Size atanmış menü yok.', 'flx-qr-menu' ) . '</p>';
			} else {
				echo '<ul>';
				foreach ( $ids as $id ) {
					echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=flx-qr-menu&menu=' . $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a></li>';
				}
				echo '</ul>';
			}
			echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=flx-qr-menu' ) ) . '">' . esc_html__( 'QR Menüler', 'flx-qr-menu' ) . '</a></p>';
		}
	);
}

/* ---------- Medya: menü yöneticisi yalnız kendi yüklediklerini görür ---------- */

function flxq_media_restricted() {
	return current_user_can( FLXQ_EDIT_CAP ) && ! current_user_can( FLXQ_CAP ) && ! current_user_can( 'edit_others_posts' );
}

add_filter(
	'ajax_query_attachments_args',
	function ( $q ) {
		if ( flxq_media_restricted() ) {
			$q['author'] = get_current_user_id();
		}
		return $q;
	}
);

add_action(
	'pre_get_posts',
	function ( $q ) {
		global $pagenow;
		if ( is_admin() && $q->is_main_query() && 'upload.php' === $pagenow && flxq_media_restricted() ) {
			$q->set( 'author', get_current_user_id() );
		}
	}
);

/* ---------- Profil: yönetici, menü yöneticisinin yetkilerini buradan da açar ---------- */

add_action( 'edit_user_profile', 'flxq_profile_fields' );
add_action( 'edit_user_profile_update', 'flxq_profile_save' );

function flxq_profile_fields( $user ) {
	if ( ! current_user_can( FLXQ_CAP ) || ! $user->has_cap( FLXQ_EDIT_CAP ) || $user->has_cap( FLXQ_CAP ) ) {
		return;
	}
	?>
	<h2><?php esc_html_e( 'QR Menü yetkileri', 'flx-qr-menu' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Menü yöneticisi', 'flx-qr-menu' ); ?></th>
			<td>
				<input type="hidden" name="flxq_caps_form" value="1">
				<?php foreach ( flxq_user_caps() as $cap => $label ) : ?>
					<label style="display:block;margin-bottom:6px"><input type="checkbox" name="flxq_caps[<?php echo esc_attr( $cap ); ?>]" value="1" <?php checked( ! empty( $user->caps[ $cap ] ) ); ?>> <?php echo esc_html( $label ); ?></label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Menü yöneticisi yalnız kendi açtığı ve kendisine atanan menüleri görür. Menüye atama, menünün Paylaş sekmesinden yapılır.', 'flx-qr-menu' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

function flxq_profile_save( $uid ) {
	// Çekirdek, bu kancadan önce update-user_{id} nonce'unu doğrular.
	if ( ! current_user_can( FLXQ_CAP ) || ! current_user_can( 'edit_user', $uid ) || empty( $_POST['flxq_caps_form'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$caps = isset( $_POST['flxq_caps'] ) && is_array( $_POST['flxq_caps'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['flxq_caps'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
	flxq_set_user_caps( $uid, $caps );
}
