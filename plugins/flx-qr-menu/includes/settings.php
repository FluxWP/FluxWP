<?php
/**
 * QR Menüler › Ayarlar: ana sayfa, menü yöneticilerinin yetkileri, kaldırma seçeneği (yalnız flxq_manage_menus).
 *
 * @package FLX_QR_Menu
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_flxq_settings', 'flxq_admin_post_settings' );

function flxq_settings_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=flx-qr-menu-settings' ) );
}

function flxq_render_settings() {
	if ( ! current_user_can( FLXQ_CAP ) ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok.', 'flx-qr-menu' ), 403 );
	}
	$s     = flxq_settings();
	$menus = flxq_menu_ids();
	$users = flxq_managers();
	$modes = array(
		'blank'    => array( __( 'Temanın ana sayfası', 'flx-qr-menu' ), __( 'Flux Menu Theme temasında boş beyaz sayfa.', 'flx-qr-menu' ) ),
		'redirect' => array( __( 'Seçili menüye yönlendir', 'flx-qr-menu' ), __( 'Ana sayfayı açan, menünün adresine (ör. /menu/) geçici yönlendirmeyle (302) gider.', 'flx-qr-menu' ) ),
		'menu'     => array( __( 'Seçili menüyü ana sayfada göster', 'flx-qr-menu' ), __( 'Menü doğrudan ana sayfada açılır; teşekkür sayfası /tesekkur/ olur.', 'flx-qr-menu' ) ),
	);
	?>
	<div class="wrap flxq flxq-settings-page">
		<div class="flxq-head">
			<h1 class="flxq-title"><img src="<?php echo esc_url( FLXQ_URL . 'assets/icon.svg' ); ?>" alt="" width="32" height="32"> <?php esc_html_e( 'QR Menü ayarları', 'flx-qr-menu' ); ?></h1>
		</div>
		<hr class="wp-header-end">
		<?php flxq_render_flash(); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="flxq_settings">
			<?php wp_nonce_field( 'flxq_settings' ); ?>

			<div class="flxq-panel">
				<h2><?php esc_html_e( 'Ana sayfa', 'flx-qr-menu' ); ?></h2>
				<fieldset class="flxq-field">
					<legend class="screen-reader-text"><?php esc_html_e( 'Ana sayfa', 'flx-qr-menu' ); ?></legend>
					<?php foreach ( $modes as $k => $m ) : ?>
						<label class="flxq-radio flxq-radio-block"><input type="radio" name="front_mode" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['front_mode'], $k ); ?>> <strong><?php echo esc_html( $m[0] ); ?></strong><br><small class="flxq-muted"><?php echo esc_html( $m[1] ); ?></small></label>
					<?php endforeach; ?>
				</fieldset>
				<label class="flxq-field"><span><?php esc_html_e( 'Menü', 'flx-qr-menu' ); ?></span>
					<select class="flxq-input" name="front_menu">
						<option value="0"><?php esc_html_e( '— Seçin —', 'flx-qr-menu' ); ?></option>
						<?php foreach ( $menus as $id ) : ?>
							<?php
							$urls = wp_list_pluck(
								array_filter(
									flxq_menu_urls( $id ),
									function ( $u ) {
										return 'front' !== $u['type'];
									}
								),
								'url'
							);
							?>
							<option value="<?php echo (int) $id; ?>" <?php selected( $s['front_menu'], $id ); ?>><?php echo esc_html( get_the_title( $id ) . ( $urls ? ' · ' . preg_replace( '#^https?://#', '', implode( ', ', $urls ) ) : '' ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<small><?php esc_html_e( 'Yönlendirme için menünün bir adresi (Paylaş sekmesi) olmalı; yoksa temanın ana sayfası açılır.', 'flx-qr-menu' ); ?></small>
				</label>
			</div>

			<div class="flxq-panel">
				<h2><?php esc_html_e( 'Yetkiler', 'flx-qr-menu' ); ?></h2>
				<p class="flxq-muted"><?php esc_html_e( 'Menü yöneticisi rolündeki kullanıcılar /wp-admin üzerinden giriş yapar ve yalnız kendi açtığı ya da kendisine atanan menüleri görür. Menüye atama, menünün Paylaş sekmesinden yapılır.', 'flx-qr-menu' ); ?></p>
				<?php if ( ! $users ) : ?>
					<p><?php esc_html_e( 'Henüz menü yöneticisi yok.', 'flx-qr-menu' ); ?></p>
				<?php else : ?>
					<table class="flxq-table flxq-perms">
						<thead><tr>
							<th><?php esc_html_e( 'Kullanıcı', 'flx-qr-menu' ); ?></th>
							<?php foreach ( flxq_user_caps() as $label ) : ?>
								<th><?php echo esc_html( $label ); ?></th>
							<?php endforeach; ?>
							<th><?php esc_html_e( 'Menüler', 'flx-qr-menu' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $users as $u ) : ?>
							<?php
							$mine = array();
							foreach ( $menus as $id ) {
								if ( flxq_can_edit_menu( $id, $u->ID ) ) {
									$mine[] = get_the_title( $id );
								}
							}
							?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Kullanıcı', 'flx-qr-menu' ); ?>"><a href="<?php echo esc_url( get_edit_user_link( $u->ID ) ); ?>"><?php echo esc_html( $u->display_name ); ?></a><br><small class="flxq-muted"><?php echo esc_html( $u->user_login ); ?></small>
									<input type="hidden" name="flxq_perm_users[]" value="<?php echo (int) $u->ID; ?>"></td>
								<?php foreach ( flxq_user_caps() as $cap => $label ) : ?>
									<td data-label="<?php echo esc_attr( $label ); ?>"><label><input type="checkbox" name="flxq_perm[<?php echo (int) $u->ID; ?>][<?php echo esc_attr( $cap ); ?>]" value="1" <?php checked( ! empty( $u->caps[ $cap ] ) ); ?>> <span class="screen-reader-text"><?php echo esc_html( $label ); ?></span></label></td>
								<?php endforeach; ?>
								<td data-label="<?php esc_attr_e( 'Menüler', 'flx-qr-menu' ); ?>"><?php echo $mine ? esc_html( implode( ', ', $mine ) ) : '<span class="flxq-muted">—</span>'; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<?php if ( current_user_can( 'create_users' ) ) : ?>
					<p><a class="flxq-btn" href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>">＋ <?php esc_html_e( 'Yeni kullanıcı ekle (rol: Menü yöneticisi)', 'flx-qr-menu' ); ?></a></p>
				<?php endif; ?>
			</div>

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<div class="flxq-panel">
					<h2><?php esc_html_e( 'Kaldırma', 'flx-qr-menu' ); ?></h2>
					<label><input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( $s['delete_on_uninstall'] ); ?>> <?php esc_html_e( 'Eklenti silinince menüleri ve geçmişi de sil', 'flx-qr-menu' ); ?></label>
				</div>
			<?php endif; ?>

			<p><button type="submit" class="flxq-btn flxq-btn-primary"><?php esc_html_e( 'Kaydet', 'flx-qr-menu' ); ?></button></p>
		</form>
	</div>
	<?php
}

function flxq_admin_post_settings() {
	if ( ! current_user_can( FLXQ_CAP ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-qr-menu' ), 403 );
	}
	check_admin_referer( 'flxq_settings' );
	// phpcs:disable WordPress.Security.NonceVerification
	$old  = flxq_settings();
	$mode = isset( $_POST['front_mode'] ) ? sanitize_key( $_POST['front_mode'] ) : 'blank';
	$menu = isset( $_POST['front_menu'] ) ? (int) $_POST['front_menu'] : 0;
	$new  = array(
		'delete_on_uninstall' => current_user_can( 'manage_options' ) ? ! empty( $_POST['delete_on_uninstall'] ) : $old['delete_on_uninstall'],
		'front_mode'          => in_array( $mode, array( 'blank', 'redirect', 'menu' ), true ) ? $mode : 'blank',
		'front_menu'          => $menu && flxq_get_post( $menu ) ? $menu : 0,
	);
	update_option( 'flxq_settings', $new, false );

	$perm = isset( $_POST['flxq_perm'] ) && is_array( $_POST['flxq_perm'] ) ? wp_unslash( $_POST['flxq_perm'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- anahtarlar aşağıda doğrulanır.
	foreach ( isset( $_POST['flxq_perm_users'] ) ? array_map( 'intval', (array) $_POST['flxq_perm_users'] ) : array() as $uid ) {
		$u = get_userdata( $uid );
		if ( ! $u || ! in_array( FLXQ_ROLE, (array) $u->roles, true ) || ! current_user_can( 'promote_user', $uid ) ) {
			continue;
		}
		flxq_set_user_caps( $uid, isset( $perm[ $uid ] ) && is_array( $perm[ $uid ] ) ? $perm[ $uid ] : array() );
	}
	// phpcs:enable
	wp_safe_redirect( flxq_settings_url( array( 'flxq_msg' => 'blank' !== $new['front_mode'] && ! $new['front_menu'] ? 'nomenu' : 'saved' ) ) );
	exit;
}
