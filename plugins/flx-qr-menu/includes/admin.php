<?php
/**
 * Yönetim ekranları: menü listesi, yeni menü (içe aktarma), düzenleme kabuğu (arayüz assets/js/edit.js).
 *
 * @package FLX_QR_Menu
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'flxq_admin_menu' );
add_action( 'admin_head', 'flxq_admin_icon_css' );
add_action( 'admin_enqueue_scripts', 'flxq_admin_assets' );
add_filter( 'admin_body_class', 'flxq_admin_body_class' );
foreach ( array( 'create', 'duplicate', 'trash', 'untrash', 'delete', 'toggle' ) as $flxq_a ) {
	add_action( 'admin_post_flxq_' . $flxq_a, 'flxq_admin_post_' . $flxq_a );
}
unset( $flxq_a );

function flxq_admin_menu() {
	$GLOBALS['flxq_hooks']   = array();
	$GLOBALS['flxq_hooks'][] = add_menu_page(
		__( 'QR Menüler', 'flx-qr-menu' ),
		__( 'QR Menüler', 'flx-qr-menu' ),
		FLXQ_EDIT_CAP,
		'flx-qr-menu',
		'flxq_render_page',
		'none',
		58
	);
	add_submenu_page( 'flx-qr-menu', __( 'QR Menüler', 'flx-qr-menu' ), __( 'Tüm menüler', 'flx-qr-menu' ), FLXQ_EDIT_CAP, 'flx-qr-menu', 'flxq_render_page' );
	add_action( 'load-' . $GLOBALS['flxq_hooks'][0], 'flxq_load_page_guard' );
	$GLOBALS['flxq_hooks'][] = add_submenu_page( 'flx-qr-menu', __( 'QR Menü ayarları', 'flx-qr-menu' ), __( 'Ayarlar', 'flx-qr-menu' ), FLXQ_CAP, 'flx-qr-menu-settings', 'flxq_render_settings' );
}

/** Renkli menü ikonu (WP data-URI SVG'yi tek renge boyar, bu yüzden arka plan resmi). */
function flxq_admin_icon_css() {
	echo '<style>#toplevel_page_flx-qr-menu .wp-menu-image{background:url(' . esc_url( FLXQ_URL . 'assets/icon.svg?ver=' . FLXQ_VERSION ) . ') center/20px 20px no-repeat}#toplevel_page_flx-qr-menu .wp-menu-image:before{content:none}</style>';
}

function flxq_is_screen( $hook = null ) {
	$id = null === $hook ? ( function_exists( 'get_current_screen' ) && get_current_screen() ? get_current_screen()->id : '' ) : $hook;
	return '' !== $id && in_array( $id, isset( $GLOBALS['flxq_hooks'] ) ? (array) $GLOBALS['flxq_hooks'] : array(), true );
}

function flxq_admin_body_class( $classes ) {
	return flxq_is_screen() ? $classes . ' flxq-screen' : $classes;
}

function flxq_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=flx-qr-menu' ) );
}

function flxq_admin_assets( $hook ) {
	if ( ! flxq_is_screen( $hook ) ) {
		return;
	}
	wp_enqueue_style( 'flxq-admin', FLXQ_URL . 'assets/css/admin.css', array(), FLXQ_VERSION );
	$id = isset( $_GET['menu'] ) ? (int) $_GET['menu'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $id || ! flxq_get_post( $id ) || ! flxq_can_edit_menu( $id ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'flxq-qrcode', FLXQ_URL . 'assets/vendor/qrcode-generator/qrcode.js', array(), '1.4.4', true );
	wp_enqueue_script( 'flxq-edit', FLXQ_URL . 'assets/js/edit.js', array( 'flxq-qrcode' ), FLXQ_VERSION, true );
	$managers = array();
	if ( current_user_can( FLXQ_CAP ) ) {
		foreach ( flxq_managers() as $u ) {
			$managers[] = array( 'id' => (int) $u->ID, 'name' => $u->display_name, 'login' => $u->user_login );
		}
	}
	wp_localize_script( 'flxq-edit', 'FLXQ_I18N', flxq_js_strings() );
	wp_add_inline_script(
		'flxq-edit',
		'window.FLXQ_BOOT=' . wp_json_encode(
			array(
				'menu'     => flxq_editor_payload( $id ),
				'restRoot' => esc_url_raw( rest_url() ),
				'ns'       => FLXQ_NS,
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'home'     => home_url(),
				'listUrl'  => flxq_url(),
				'icons'    => FLXQ_ICONS,
				'locale'   => str_replace( '_', '-', get_user_locale() ),
				'managers' => $managers,
				'homeHost' => flxq_home_host(),
				'usersUrl' => current_user_can( 'create_users' ) ? admin_url( 'user-new.php' ) : '',
			)
		) . ';',
		'before'
	);
}

function flxq_notice( $type, $msg ) {
	printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $msg ) );
}

function flxq_render_page() {
	if ( ! current_user_can( FLXQ_EDIT_CAP ) ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok.', 'flx-qr-menu' ), 403 );
	}
	// phpcs:disable WordPress.Security.NonceVerification
	$id     = isset( $_GET['menu'] ) ? (int) $_GET['menu'] : 0;
	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
	// phpcs:enable
	if ( $id && flxq_get_post( $id ) && ! flxq_can_edit_menu( $id ) ) {
		wp_die( esc_html__( 'Bu menüye erişim yetkiniz yok.', 'flx-qr-menu' ), 403 );
	}
	if ( $id && flxq_get_post( $id ) ) {
		flxq_render_edit( $id );
	} elseif ( 'new' === $action && flxq_can_create() ) {
		flxq_render_new();
	} else {
		flxq_render_list();
	}
}

/** Başkasının menüsü: çıktı başlamadan 403 (ekranda yalnız ileti değil, HTTP durumu da 403). */
function flxq_load_page_guard() {
	$id = isset( $_GET['menu'] ) ? (int) $_GET['menu'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
	if ( $id && flxq_get_post( $id ) && ! flxq_can_edit_menu( $id ) ) {
		wp_die( esc_html__( 'Bu menüye erişim yetkiniz yok.', 'flx-qr-menu' ), '', array( 'response' => 403, 'back_link' => true ) );
	}
}

function flxq_render_flash() {
	$map = array(
		'created'   => array( 'success', __( 'Menü oluşturuldu.', 'flx-qr-menu' ) ),
		'copied'    => array( 'success', __( 'Menü kopyalandı.', 'flx-qr-menu' ) ),
		'trashed'   => array( 'success', __( 'Menü çöp kutusuna taşındı. Bağlı siteler artık veri alamaz.', 'flx-qr-menu' ) ),
		'untrashed' => array( 'success', __( 'Menü geri alındı.', 'flx-qr-menu' ) ),
		'deleted'   => array( 'success', __( 'Menü ve geçmişi kalıcı olarak silindi.', 'flx-qr-menu' ) ),
		'toggled'   => array( 'success', __( 'Menü durumu değişti.', 'flx-qr-menu' ) ),
		'saved'     => array( 'success', __( 'Ayarlar kaydedildi.', 'flx-qr-menu' ) ),
		'nomenu'    => array( 'warning', __( 'Ana sayfa için bir menü seçin.', 'flx-qr-menu' ) ),
	);
	$m = isset( $_GET['flxq_msg'] ) ? sanitize_key( $_GET['flxq_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( isset( $map[ $m ] ) ) {
		flxq_notice( $map[ $m ][0], $map[ $m ][1] );
	}
	$err = isset( $_GET['flxq_err'] ) ? sanitize_text_field( wp_unslash( $_GET['flxq_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' !== $err ) {
		flxq_notice( 'error', $err );
	}
}

function flxq_post_button( $action, $id, $label, $class = 'button', $confirm = '' ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxq-inline-form"<?php echo $confirm ? ' onsubmit="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ')"' : ''; ?>>
		<input type="hidden" name="action" value="flxq_<?php echo esc_attr( $action ); ?>">
		<input type="hidden" name="menu" value="<?php echo (int) $id; ?>">
		<?php wp_nonce_field( 'flxq_' . $action . '_' . (int) $id ); ?>
		<button type="submit" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
}

function flxq_ago( $ts ) {
	if ( ! $ts ) {
		return '';
	}
	/* translators: %s: human readable time difference, e.g. "5 mins" */
	return sprintf( __( '%s önce', 'flx-qr-menu' ), human_time_diff( (int) $ts ) );
}

function flxq_render_list() {
	$ids   = flxq_visible_menu_ids();
	$trash = array_values( array_filter( flxq_visible_menu_ids( 'trash' ), 'flxq_can_trash' ) );
	$admin = current_user_can( FLXQ_CAP );
	$new   = flxq_can_create();
	?>
	<div class="wrap flxq flxq-list">
		<div class="flxq-head">
			<h1 class="flxq-title"><img src="<?php echo esc_url( FLXQ_URL . 'assets/icon.svg' ); ?>" alt="" width="32" height="32"> <?php esc_html_e( 'QR Menüler', 'flx-qr-menu' ); ?></h1>
			<?php if ( $new ) : ?>
				<a class="flxq-btn flxq-btn-primary" href="<?php echo esc_url( flxq_url( array( 'action' => 'new' ) ) ); ?>">＋ <?php esc_html_e( 'Yeni menü', 'flx-qr-menu' ); ?></a>
			<?php endif; ?>
		</div>
		<hr class="wp-header-end">
		<?php flxq_render_flash(); ?>
		<?php if ( ! $ids ) : ?>
			<div class="flxq-empty">
				<p><strong><?php esc_html_e( 'Henüz menü yok.', 'flx-qr-menu' ); ?></strong></p>
				<?php if ( $new ) : ?>
					<p><?php esc_html_e( 'Bir menü oluşturun ve Paylaş sekmesinden bu sitedeki adresini (ör. /menu/) belirleyin. Ayrı bir menü sitesi bağlamak için Bağlantı sekmesinden anahtar alın.', 'flx-qr-menu' ); ?></p>
					<a class="flxq-btn flxq-btn-primary" href="<?php echo esc_url( flxq_url( array( 'action' => 'new' ) ) ); ?>"><?php esc_html_e( 'İlk menüyü oluştur', 'flx-qr-menu' ); ?></a>
				<?php else : ?>
					<p><?php esc_html_e( 'Size atanmış bir menü yok. Site yöneticisinden bir menüye atanmayı ya da menü açma yetkisi isteyin.', 'flx-qr-menu' ); ?></p>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<div class="flxq-cards">
				<?php
				foreach ( $ids as $id ) :
					$i   = flxq_menu_info( $id );
					$url = flxq_url( array( 'menu' => $id ) );
					?>
					<div class="flxq-card<?php echo $i['enabled'] ? '' : ' is-off'; ?>">
						<div class="flxq-card-top">
							<a class="flxq-card-name" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $i['name'] ); ?></a>
							<span class="flxq-pill <?php echo $i['enabled'] ? 'is-on' : 'is-off'; ?>"><?php echo $i['enabled'] ? esc_html__( 'Yayında', 'flx-qr-menu' ) : esc_html__( 'Kapalı', 'flx-qr-menu' ); ?></span>
						</div>
						<dl class="flxq-facts">
							<div><dt><?php esc_html_e( 'Sürüm', 'flx-qr-menu' ); ?></dt><dd>v<?php echo (int) $i['version']; ?></dd></div>
							<div><dt><?php esc_html_e( 'Ürün', 'flx-qr-menu' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $i['stats']['items'] ) ); ?>
								<?php if ( $i['stats']['soldout'] ) : ?>
									<span class="flxq-tag is-warn">
									<?php
									/* translators: %s: number of sold out items */
									echo esc_html( sprintf( __( '%s tükendi', 'flx-qr-menu' ), number_format_i18n( $i['stats']['soldout'] ) ) );
									?>
									</span>
								<?php endif; ?>
							</dd></div>
							<div><dt><?php esc_html_e( 'Son değişiklik', 'flx-qr-menu' ); ?></dt><dd><?php echo esc_html( flxq_ago( $i['updated'] ) ); ?><?php echo $i['updated_name'] ? ' · ' . esc_html( $i['updated_name'] ) : ''; ?></dd></div>
							<div><dt><?php esc_html_e( 'Adres', 'flx-qr-menu' ); ?></dt><dd class="flxq-addr">
								<?php
								if ( $i['urls'] ) {
									foreach ( $i['urls'] as $u ) {
										echo '<a href="' . esc_url( $u['url'] ) . '" target="_blank" rel="noopener">' . esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $u['url'] ) ) ) . '</a> ';
									}
								} else {
									echo '<span class="flxq-muted">' . esc_html__( 'Yok', 'flx-qr-menu' ) . '</span>';
								}
								?>
							</dd></div>
							<?php if ( $admin ) : ?>
							<div><dt><?php esc_html_e( 'Siteler', 'flx-qr-menu' ); ?></dt><dd>
								<?php
								if ( ! $i['key']['has'] ) {
									echo '<span class="flxq-tag is-warn">' . esc_html__( 'Anahtar yok', 'flx-qr-menu' ) . '</span>';
								} elseif ( ! $i['sites'] ) {
									esc_html_e( 'Bağlı site yok', 'flx-qr-menu' );
								} else {
									/* translators: 1: number of sites, 2: time ago */
									echo esc_html( sprintf( __( '%1$s · son %2$s', 'flx-qr-menu' ), number_format_i18n( count( $i['sites'] ) ), flxq_ago( $i['last_seen'] ) ) );
								}
								?>
							</dd></div>
							<?php endif; ?>
						</dl>
						<div class="flxq-card-actions">
							<a class="flxq-btn flxq-btn-primary" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Düzenle', 'flx-qr-menu' ); ?></a>
							<?php
							flxq_post_button( 'toggle', $id, $i['enabled'] ? __( 'Yayından kaldır', 'flx-qr-menu' ) : __( 'Yayına al', 'flx-qr-menu' ), 'flxq-btn' );
							if ( $new ) {
								flxq_post_button( 'duplicate', $id, __( 'Kopyala', 'flx-qr-menu' ), 'flxq-btn' );
							}
							if ( flxq_can_trash( $id ) ) {
								flxq_post_button( 'trash', $id, __( 'Çöpe at', 'flx-qr-menu' ), 'flxq-btn flxq-btn-danger', __( 'Menü çöp kutusuna taşınsın mı? Adresi kapanır ve bağlı menü siteleri veri alamaz.', 'flx-qr-menu' ) );
							}
							?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( $trash ) : ?>
			<details class="flxq-trash">
				<summary>
				<?php
				/* translators: %s: number of trashed menus */
				echo esc_html( sprintf( __( 'Çöp kutusu (%s)', 'flx-qr-menu' ), number_format_i18n( count( $trash ) ) ) );
				?>
				</summary>
				<ul>
					<?php foreach ( $trash as $id ) : ?>
						<li><span><?php echo esc_html( get_the_title( $id ) ); ?></span>
							<?php
							flxq_post_button( 'untrash', $id, __( 'Geri al', 'flx-qr-menu' ), 'flxq-btn' );
							if ( $admin ) {
								flxq_post_button( 'delete', $id, __( 'Kalıcı sil', 'flx-qr-menu' ), 'flxq-btn flxq-btn-danger', __( 'Menü ve tüm geçmişi kalıcı olarak silinsin mi?', 'flx-qr-menu' ) );
							}
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			</details>
		<?php endif; ?>

	</div>
	<?php
}

function flxq_render_new() {
	$sample = FLXQ_DIR . 'assets/sample-menu.json';
	?>
	<div class="wrap flxq flxq-new">
		<div class="flxq-head">
			<h1 class="flxq-title"><a href="<?php echo esc_url( flxq_url() ); ?>" class="flxq-back" aria-label="<?php esc_attr_e( 'Menülere dön', 'flx-qr-menu' ); ?>">←</a> <?php esc_html_e( 'Yeni menü', 'flx-qr-menu' ); ?></h1>
		</div>
		<hr class="wp-header-end">
		<?php flxq_render_flash(); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="flxq-panel flxq-form">
			<input type="hidden" name="action" value="flxq_create">
			<?php wp_nonce_field( 'flxq_create' ); ?>
			<label class="flxq-field"><span><?php esc_html_e( 'Menü adı', 'flx-qr-menu' ); ?></span>
				<input class="flxq-input" type="text" name="name" required maxlength="120" placeholder="<?php esc_attr_e( 'ör. Yenikapı Tanıtım Günleri', 'flx-qr-menu' ); ?>">
			</label>
			<fieldset class="flxq-field">
				<legend><?php esc_html_e( 'Başlangıç', 'flx-qr-menu' ); ?></legend>
				<label class="flxq-radio"><input type="radio" name="start" value="empty" checked> <?php esc_html_e( 'Boş menü', 'flx-qr-menu' ); ?></label>
				<?php if ( is_readable( $sample ) ) : ?>
					<label class="flxq-radio"><input type="radio" name="start" value="sample"> <?php esc_html_e( 'Örnek menü (Yenikapı Tanıtım Günleri)', 'flx-qr-menu' ); ?></label>
				<?php endif; ?>
				<label class="flxq-radio"><input type="radio" name="start" value="import"> <?php esc_html_e( 'JSON içe aktar', 'flx-qr-menu' ); ?></label>
			</fieldset>
			<label class="flxq-field flxq-import"><span><?php esc_html_e( 'Menü JSON\'u', 'flx-qr-menu' ); ?></span>
				<textarea class="flxq-input flxq-code" name="json" rows="12" spellcheck="false" placeholder='{"etkinlik":{…},"kategoriler":[…]}'></textarea>
				<small><?php esc_html_e( 'Menü sitesinin JSON\'unu, eski index.html dosyasının tamamını (içindeki menu-data bölümü alınır) ya da veri ucunun yanıtını yapıştırabilirsiniz.', 'flx-qr-menu' ); ?></small>
			</label>
			<p><button type="submit" class="flxq-btn flxq-btn-primary"><?php esc_html_e( 'Menüyü oluştur', 'flx-qr-menu' ); ?></button></p>
		</form>
		<script>
		(function(){var f=document.querySelector('.flxq-form'),t=f.querySelector('.flxq-import');function u(){var v=(f.querySelector('input[name=start]:checked')||{}).value;t.style.display=v==='import'?'':'none';}f.addEventListener('change',u);u();})();
		</script>
	</div>
	<?php
}

function flxq_render_edit( $id ) {
	$post = flxq_get_post( $id );
	?>
	<div class="wrap flxq flxq-edit" id="flxq-app" data-menu="<?php echo (int) $id; ?>">
		<div class="flxq-layout">
		<aside class="flxq-preview" id="flxq-preview" aria-label="<?php esc_attr_e( 'Canlı önizleme', 'flx-qr-menu' ); ?>">
			<div class="flxq-preview-bar">
				<span class="flxq-preview-title"><?php esc_html_e( 'Canlı önizleme', 'flx-qr-menu' ); ?></span>
				<span class="flxq-preview-status" id="flxq-preview-status" role="status" aria-live="polite"></span>
				<span class="flxq-grow"></span>
				<a class="flxq-preview-open" id="flxq-preview-open" href="#" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Yeni sekmede aç', 'flx-qr-menu' ); ?></a>
				<button type="button" class="flxq-icon-btn flxq-preview-close" id="flxq-preview-close" aria-label="<?php esc_attr_e( 'Önizlemeyi kapat', 'flx-qr-menu' ); ?>">✕</button>
			</div>
			<div class="flxq-phone-wrap" id="flxq-phone-wrap">
				<div class="flxq-phone" id="flxq-phone">
					<div class="flxq-phone-screen" id="flxq-phone-screen"></div>
				</div>
			</div>
			<p class="flxq-preview-note flxq-muted"><?php esc_html_e( 'Kaydedilmemiş değişiklikler de görünür. Ziyaretçiler kaydettikten sonra görür.', 'flx-qr-menu' ); ?></p>
		</aside>
		<div class="flxq-main">
		<div class="flxq-head">
			<h1 class="flxq-title"><a href="<?php echo esc_url( flxq_url() ); ?>" class="flxq-back" aria-label="<?php esc_attr_e( 'Menülere dön', 'flx-qr-menu' ); ?>">←</a>
				<input type="text" class="flxq-input flxq-name" id="flxq-name" value="<?php echo esc_attr( $post->post_title ); ?>" aria-label="<?php esc_attr_e( 'Menü adı', 'flx-qr-menu' ); ?>" maxlength="120">
			</h1>
			<span class="flxq-pill" id="flxq-state"></span>
			<button type="button" class="flxq-btn flxq-preview-toggle" id="flxq-preview-toggle" aria-controls="flxq-preview" aria-expanded="false"><?php esc_html_e( 'Önizleme', 'flx-qr-menu' ); ?></button>
		</div>
		<hr class="wp-header-end">
		<?php if ( 'trash' === $post->post_status ) : ?>
			<?php flxq_notice( 'warning', __( 'Bu menü çöp kutusunda. Menü listesinden geri alın.', 'flx-qr-menu' ) ); ?>
		<?php endif; ?>
		<nav class="flxq-tabs" role="tablist">
			<button type="button" role="tab" data-tab="menu" class="is-active"><?php esc_html_e( 'Menü', 'flx-qr-menu' ); ?></button>
			<button type="button" role="tab" data-tab="settings"><?php esc_html_e( 'Ayarlar', 'flx-qr-menu' ); ?></button>
			<button type="button" role="tab" data-tab="share"><?php esc_html_e( 'Paylaş', 'flx-qr-menu' ); ?></button>
			<?php if ( current_user_can( FLXQ_CAP ) ) : ?>
				<button type="button" role="tab" data-tab="connect"><?php esc_html_e( 'Bağlantı', 'flx-qr-menu' ); ?></button>
			<?php endif; ?>
			<button type="button" role="tab" data-tab="history"><?php esc_html_e( 'Geçmiş', 'flx-qr-menu' ); ?></button>
			<button type="button" role="tab" data-tab="json">JSON</button>
		</nav>
		<div class="flxq-tabpanel" data-panel="menu"></div>
		<div class="flxq-tabpanel" data-panel="settings" hidden></div>
		<div class="flxq-tabpanel" data-panel="share" hidden></div>
		<div class="flxq-tabpanel" data-panel="connect" hidden></div>
		<div class="flxq-tabpanel" data-panel="history" hidden></div>
		<div class="flxq-tabpanel" data-panel="json" hidden></div>
		<div class="flxq-savebar" id="flxq-savebar">
			<div class="flxq-savebar-in">
				<span class="flxq-save-status" id="flxq-save-status"></span>
				<button type="button" class="flxq-btn" id="flxq-revert" disabled><?php esc_html_e( 'Vazgeç', 'flx-qr-menu' ); ?></button>
				<button type="button" class="flxq-btn flxq-btn-primary" id="flxq-save" disabled><?php esc_html_e( 'Kaydet', 'flx-qr-menu' ); ?></button>
			</div>
			<div class="flxq-conflict" id="flxq-conflict" hidden></div>
		</div>
		</div>
		</div>
		<noscript><?php esc_html_e( 'Menü düzenleyicisi için JavaScript gerekli.', 'flx-qr-menu' ); ?></noscript>
	</div>
	<?php
}

/* ---------- admin-post işlemleri ---------- */

/**
 * Yetki + nonce + menü kontrolü.
 *
 * @param string        $action İşlem adı.
 * @param bool          $with_id Menü kimliği gerekli mi.
 * @param callable|null $can     Menüye özel ek yetki kontrolü (kimlik alır).
 */
function flxq_admin_guard( $action, $with_id = true, $can = null ) {
	if ( ! current_user_can( FLXQ_EDIT_CAP ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-qr-menu' ), 403 );
	}
	$id = $with_id && isset( $_POST['menu'] ) ? (int) $_POST['menu'] : 0; // phpcs:ignore WordPress.Security.NonceVerification
	check_admin_referer( 'flxq_' . $action . ( $with_id ? '_' . $id : '' ) );
	if ( $with_id && ! flxq_get_post( $id ) ) {
		wp_die( esc_html__( 'Menü bulunamadı.', 'flx-qr-menu' ), 404 );
	}
	if ( ( $with_id && ! flxq_can_edit_menu( $id ) ) || ( $can && ! call_user_func( $can, $id ) ) ) {
		wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-qr-menu' ), 403 );
	}
	return $id;
}

function flxq_redirect( $args ) {
	wp_safe_redirect( flxq_url( $args ) );
	exit;
}

/**
 * Yapıştırılan metinden menü verisini çıkarır: düz JSON, eski index.html (script#menu-data) ya da feed yanıtı.
 *
 * @return object|WP_Error
 */
function flxq_parse_import( $text ) {
	$text = trim( (string) $text );
	if ( preg_match( '#<script[^>]*id=["\']menu-data["\'][^>]*>(.*?)</script>#is', $text, $m ) ) {
		$text = trim( $m[1] );
	}
	$data = json_decode( $text );
	if ( null === $data || JSON_ERROR_NONE !== json_last_error() ) {
		/* translators: %s: JSON error message */
		return new WP_Error( 'flxq_json', sprintf( __( 'JSON okunamadı: %s', 'flx-qr-menu' ), json_last_error_msg() ) );
	}
	if ( is_object( $data ) && isset( $data->data ) && is_object( $data->data ) && isset( $data->data->kategoriler ) ) {
		$data = $data->data;
	}
	return $data;
}

function flxq_admin_post_create() {
	flxq_admin_guard( 'create', false, 'flxq_can_create' );
	// phpcs:disable WordPress.Security.NonceVerification
	$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$start = isset( $_POST['start'] ) ? sanitize_key( $_POST['start'] ) : 'empty';
	$json  = isset( $_POST['json'] ) ? wp_unslash( $_POST['json'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- JSON, flxq_normalize ile doğrulanır.
	// phpcs:enable
	$action = 'create';
	if ( 'import' === $start ) {
		$data   = flxq_parse_import( $json );
		$action = 'import';
	} elseif ( 'sample' === $start ) {
		$data   = json_decode( (string) file_get_contents( FLXQ_DIR . 'assets/sample-menu.json' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$action = 'import';
		// Örnek menünün görselleri eklentiyle gelir: medya kütüphanesine eklenir (daha önce eklendiyse aynısı kullanılır).
		if ( is_object( $data ) ) {
			$data = flxq_localize_bundled_images( $data );
		}
	} else {
		$data = (object) array(
			'etkinlik'    => (object) array( 'baslik' => $name, 'slogan' => '', 'gunler' => array() ),
			'genelBilgi'  => array(),
			'kategoriler' => array(
				(object) array( 'id' => 'ana', 'baslik' => __( 'Ana Yemekler', 'flx-qr-menu' ), 'ikon' => 'yemek', 'urunler' => array() ),
			),
		);
	}
	if ( ! is_wp_error( $data ) ) {
		$id = flxq_create_menu( $name, $data, $action );
	}
	if ( is_wp_error( $data ) || is_wp_error( $id ) ) {
		$e = is_wp_error( $data ) ? $data : $id;
		flxq_redirect( array( 'action' => 'new', 'flxq_err' => rawurlencode( $e->get_error_message() ) ) );
	}
	flxq_redirect( array( 'menu' => $id, 'flxq_msg' => 'created' ) );
}

function flxq_admin_post_duplicate() {
	$id  = flxq_admin_guard( 'duplicate', true, 'flxq_can_create' );
	$new = flxq_duplicate_menu( $id );
	if ( is_wp_error( $new ) ) {
		flxq_redirect( array( 'flxq_err' => rawurlencode( $new->get_error_message() ) ) );
	}
	flxq_redirect( array( 'flxq_msg' => 'copied' ) );
}

function flxq_admin_post_trash() {
	$id = flxq_admin_guard( 'trash', true, 'flxq_can_trash' );
	wp_trash_post( $id );
	flxq_redirect( array( 'flxq_msg' => 'trashed' ) );
}

function flxq_admin_post_untrash() {
	$id = flxq_admin_guard( 'untrash', true, 'flxq_can_trash' );
	wp_untrash_post( $id );
	// WP 5.6+ geri alınan yazıyı taslak yapar; menü yayında kalmalı.
	wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
	flxq_redirect( array( 'flxq_msg' => 'untrashed' ) );
}

function flxq_admin_post_delete() {
	$id = flxq_admin_guard(
		'delete',
		true,
		function () {
			return current_user_can( FLXQ_CAP );
		}
	);
	flxq_delete_menu( $id );
	flxq_redirect( array( 'flxq_msg' => 'deleted' ) );
}

function flxq_admin_post_toggle() {
	$id = flxq_admin_guard( 'toggle' );
	flxq_set_enabled( $id, ! flxq_menu_enabled( $id ) );
	flxq_redirect( array( 'flxq_msg' => 'toggled' ) );
}
