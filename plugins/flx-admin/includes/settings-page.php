<?php
/** Ayarlar › Yönetim teması: Genel, Görünüm, Menü (rol bazlı), Yetkiler, Başlangıç, PWA, Sistem kontrolü. */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'flxa_settings_menu' );
add_action( 'admin_post_flxa_save', 'flxa_settings_save' );
add_action( 'admin_enqueue_scripts', 'flxa_settings_assets' );
add_action( 'load-settings_page_flx-admin', 'flxa_system_tab_redirect' );

/** Flux Speed kuruluysa Sistem kontrolü orada (Performans › Sistem Kontrolü); buradaki sekme gizlenir, eski adres oraya gider. */
function flxa_system_moved() {
	return function_exists( 'flxp_system_checks_url' );
}

function flxa_system_tab_redirect() {
	if ( flxa_system_moved() && 'system' === sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		wp_safe_redirect( flxp_system_checks_url() );
		exit;
	}
}

function flxa_settings_menu() {
	add_options_page( __( 'Yönetim teması', 'flx-admin' ), __( 'Yönetim teması', 'flx-admin' ), 'manage_options', 'flx-admin', 'flxa_settings_render' );
}

function flxa_settings_tabs() {
	$tabs = array(
		'general'   => __( 'Genel', 'flx-admin' ),
		'look'      => __( 'Görünüm', 'flx-admin' ),
		'menu'      => __( 'Menü ve alt bar', 'flx-admin' ),
		'caps'      => __( 'Yetkiler', 'flx-admin' ),
		'dashboard' => __( 'Başlangıç', 'flx-admin' ),
		'pwa'       => __( 'Uygulama (PWA)', 'flx-admin' ),
		'system'    => __( 'Sistem kontrolü', 'flx-admin' ),
	);
	if ( flxa_system_moved() ) {
		unset( $tabs['system'] );
	}
	return $tabs;
}

function flxa_settings_tab() {
	$t = sanitize_key( wp_unslash( $_GET['tab'] ?? 'general' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	return array_key_exists( $t, flxa_settings_tabs() ) ? $t : 'general';
}

function flxa_settings_assets( $hook ) {
	if ( 'settings_page_flx-admin' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'flxa-settings', FLXA_URL . 'assets/css/settings.css', array(), FLXA_VERSION );
	$tab = flxa_settings_tab();
	if ( 'general' === $tab ) {
		wp_enqueue_media();
		wp_enqueue_script( 'flxa-settings', FLXA_URL . 'assets/js/settings.js', array( 'jquery' ), FLXA_VERSION, true );
		wp_localize_script( 'flxa-settings', 'FLXA_SET', array( 'pick' => __( 'Logo seç', 'flx-admin' ) ) );
	}
	if ( 'menu' === $tab ) {
		wp_enqueue_script( 'flxa-menu-editor', FLXA_URL . 'assets/js/menu-editor.js', array(), FLXA_VERSION, true );
		wp_localize_script( 'flxa-menu-editor', 'FLXA_ME', flxa_menu_editor_data() );
	}
}

/** Menü düzenleyicinin verisi. */
function flxa_menu_editor_data() {
	$catalog = flxa_menu_catalog();
	$slugs   = wp_list_pluck( $catalog, 'slug' );
	$roles   = array( '_default' => __( 'Diğer tüm roller', 'flx-admin' ) ) + flxa_roles();
	$access  = array();
	foreach ( array_keys( $roles ) as $r ) {
		$obj = '_default' === $r ? null : get_role( $r );
		foreach ( $catalog as $it ) {
			$ok = '_default' === $r || ( $obj && ( $obj->has_cap( $it['cap'] ) || $obj->has_cap( 'manage_options' ) ) );
			if ( ! $ok && $obj ) {
				foreach ( $it['sub'] as $s ) {
					if ( $obj->has_cap( $s['cap'] ) ) {
						$ok = true;
						break;
					}
				}
			}
			$access[ $r ][ $it['slug'] ] = $ok;
		}
	}
	$counts = count_users();
	$icons  = array();
	foreach ( $catalog as $it ) {
		$icons[ $it['slug'] ] = flxa_icon( $it['icon'] ? $it['icon'] : 'dot', 16 );
	}
	return array(
		'catalog'  => $catalog,
		'roles'    => $roles,
		'users'    => (array) ( $counts['avail_roles'] ?? array() ),
		'access'   => $access,
		'saved'    => (array) ( flxa_menus_option()['roles'] ?? array() ),
		'defaults' => flxa_default_entries( $slugs ),
		'defBottom'=> array_slice( flxa_default_bottom(), 0, 4 ),
		'capsUrl'  => flxa_settings_url( 'caps' ),
		'unblockable' => flxa_unblockable(),
		'icons'    => $icons,
		// Platform seçimi (web / mobil / masaüstü): uygulama paketi (Flux App) kaydeder; yoksa düğmeler çıkmaz.
		'platforms'=> flxa_menu_platforms(),
		'ui'       => array(
			'drag'      => flxa_icon( 'drag', 16 ),
			'eye'       => flxa_icon( 'eye', 16 ),
			'eyeOff'    => flxa_icon( 'eye-off', 16 ),
			'chevron'   => flxa_icon( 'chevron', 14 ),
			'x'         => flxa_icon( 'x', 14 ),
			'lock'      => flxa_icon( 'lock', 16 ),
			'up'        => '↑',
			'down'      => '↓',
		),
		'i18n'     => array(
			'custom'      => __( 'Özel düzen', 'flx-admin' ),
			'inherit'     => __( 'Varsayılan düzen', 'flx-admin' ),
			'group'       => __( 'Grup', 'flx-admin' ),
			'groupName'   => __( 'Grup adı', 'flx-admin' ),
			'newGroup'    => __( 'Yeni grup', 'flx-admin' ),
			'addGroup'    => __( '+ Grup ekle', 'flx-admin' ),
			'removeGroup' => __( 'Grubu kaldır', 'flx-admin' ),
			'rename'      => __( 'Menü adı (boş = özgün ad)', 'flx-admin' ),
			'show'        => __( 'Görünür', 'flx-admin' ),
			'hide'        => __( 'Gizli', 'flx-admin' ),
			'noAccess'    => __( 'Bu rol erişemez', 'flx-admin' ),
			'subs'        => __( 'Alt menüler', 'flx-admin' ),
			'moveUp'      => __( 'Yukarı taşı', 'flx-admin' ),
			'moveDown'    => __( 'Aşağı taşı', 'flx-admin' ),
			'reset'       => __( 'Bu rolü varsayılana döndür', 'flx-admin' ),
			'copyFrom'    => __( 'Başka rolden kopyala…', 'flx-admin' ),
			'preview'     => __( 'Önizleme', 'flx-admin' ),
			'bottom'      => __( 'Mobil alt bar', 'flx-admin' ),
			'bottomHelp'  => __( 'En fazla 4 kısayol; beşinci düğme "Daha" yan paneli açar.', 'flx-admin' ),
			'none'        => __( '— boş —', 'flx-admin' ),
			'users'       => __( 'kişi', 'flx-admin' ),
			'other'       => __( 'Yeni kurulan eklentilerin menüleri otomatik "Diğer" grubuna düşer.', 'flx-admin' ),
			'unsaved'     => __( 'Kaydedilmemiş değişiklik var.', 'flx-admin' ),
			'block'       => __( 'Erişimi engelle', 'flx-admin' ),
			'blocked'     => __( 'Erişim engelli: menüde yok, adresle açılırsa "yetkiniz yok" görür', 'flx-admin' ),
			'collapsed'   => __( 'Kapalı başlasın', 'flx-admin' ),
			'capsLink'    => __( 'Yetkilerin tamamı: Yetkiler sekmesi', 'flx-admin' ),
			'adminNote'   => __( 'Yönetici rolünde erişim engellenemez.', 'flx-admin' ),
			'platforms'   => __( 'Nerede görünsün', 'flx-admin' ),
			/* translators: %s: platform name */
			'platformOff' => __( '%s: gösterilmez', 'flx-admin' ),
		),
	);
}

function flxa_settings_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tab  = flxa_settings_tab();
	$s    = flxa_settings();
	$base = flxa_settings_url();
	echo '<div class="wrap flxa-settings">';
	echo '<h1 class="flxa-settings-title"><img src="' . esc_url( FLXA_URL . 'assets/icon.svg' ) . '" alt="" width="32" height="32"> ' . esc_html__( 'Yönetim teması', 'flx-admin' ) . ' <small>Flux Admin ' . esc_html( FLXA_VERSION ) . '</small></h1>';
	if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Kaydedildi.', 'flx-admin' ) . '</p></div>';
	}
	echo '<nav class="nav-tab-wrapper flxa-tabs">';
	foreach ( flxa_settings_tabs() as $k => $l ) {
		echo '<a class="nav-tab' . ( $k === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', $k, $base ) ) . '">' . esc_html( $l ) . '</a>';
	}
	echo '</nav>';

	if ( 'system' === $tab ) {
		if ( function_exists( 'flx_system_checks_render' ) ) {
			flx_system_checks_render( array( 'recheck_url' => flxa_settings_url( 'system' ) ) );
		} else {
			flxa_render_own_checks();
		}
		echo '</div>';
		return;
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxa-form" id="flxa-form">';
	wp_nonce_field( 'flxa_save' );
	echo '<input type="hidden" name="action" value="flxa_save"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';

	if ( 'general' === $tab ) {
		$logo = (int) $s['logo_id'];
		?>
		<div class="flxa-card">
			<h2><?php esc_html_e( 'Marka', 'flx-admin' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="flxa-enabled"><?php esc_html_e( 'Yönetim teması', 'flx-admin' ); ?></label></th>
					<td><label><input type="checkbox" id="flxa-enabled" name="s[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Açık (kapatınca WordPress\'in standart paneli döner)', 'flx-admin' ); ?></label></td></tr>
				<tr><th scope="row"><label for="flxa-brand"><?php esc_html_e( 'Ad', 'flx-admin' ); ?></label></th>
					<td><input type="text" class="regular-text" id="flxa-brand" name="s[brand]" value="<?php echo esc_attr( $s['brand'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></td></tr>
				<tr><th scope="row"><label for="flxa-subtitle"><?php esc_html_e( 'Alt satır', 'flx-admin' ); ?></label></th>
					<td><input type="text" class="regular-text" id="flxa-subtitle" name="s[subtitle]" value="<?php echo esc_attr( $s['subtitle'] ); ?>" placeholder="<?php esc_attr_e( 'Yönetim', 'flx-admin' ); ?>"></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Logo', 'flx-admin' ); ?></th>
					<td class="flxa-logo-field"><img src="<?php echo esc_url( flxa_logo_url( 96 ) ); ?>" alt="" width="48" height="48" data-flxa-logo-preview>
						<input type="hidden" name="s[logo_id]" value="<?php echo (int) $logo; ?>" data-flxa-logo-id>
						<button type="button" class="button" data-flxa-logo-pick><?php esc_html_e( 'Logo seç', 'flx-admin' ); ?></button>
						<button type="button" class="button-link" data-flxa-logo-clear<?php echo $logo ? '' : ' hidden'; ?>><?php esc_html_e( 'Kaldır', 'flx-admin' ); ?></button>
						<p class="description"><?php esc_html_e( 'Boşsa site simgesi, o da yoksa Flux Admin simgesi kullanılır.', 'flx-admin' ); ?></p></td></tr>
			</table>
		</div>
		<div class="flxa-card">
			<h2><?php esc_html_e( 'Seçenekler', 'flx-admin' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Özellikler', 'flx-admin' ); ?></th>
					<td><fieldset>
						<label><input type="checkbox" name="s[bottom_bar]" value="1" <?php checked( $s['bottom_bar'] ); ?>> <?php esc_html_e( 'Mobilde alt bar', 'flx-admin' ); ?></label><br>
						<label><input type="checkbox" name="s[app_rail]" value="1" <?php checked( $s['app_rail'] ); ?>> <?php esc_html_e( 'WhatsApp ve Görevler sol menünün hemen sağında açılsın (menü simgelere daralır)', 'flx-admin' ); ?></label><br>
						<label><input type="checkbox" name="s[app_switcher]" value="1" <?php checked( $s['app_switcher'] ); ?>> <?php esc_html_e( 'Telefonda WhatsApp ve Görevler ekranlarında uygulama geçiş düğmesi', 'flx-admin' ); ?></label><br>
						<label><input type="checkbox" name="s[fast_nav]" value="1" <?php checked( $s['fast_nav'] ); ?>> <?php esc_html_e( 'Hızlı geçiş: tıklanan menü sayfası önden hazırlanır, geçiş yumuşak olur', 'flx-admin' ); ?></label><br>
						<label><input type="checkbox" name="s[palette]" value="1" <?php checked( $s['palette'] ); ?>> <?php esc_html_e( 'Komut paleti (Ctrl+K / ⌘K)', 'flx-admin' ); ?></label><br>
						<label><input type="checkbox" name="s[login]" value="1" <?php checked( $s['login'] ); ?>> <?php esc_html_e( 'Giriş ekranını da tasarla', 'flx-admin' ); ?></label>
					</fieldset></td></tr>
				<tr><th scope="row"><label for="flxa-hb"><?php esc_html_e( 'Canlı sayaç aralığı', 'flx-admin' ); ?></label></th>
					<td><select id="flxa-hb" name="s[heartbeat]">
						<?php foreach ( array( 30, 60, 120 ) as $n ) : ?>
							<option value="<?php echo (int) $n; ?>" <?php selected( (int) $s['heartbeat'], $n ); ?>><?php echo esc_html( sprintf( /* translators: %d: seconds */ __( '%d sn', 'flx-admin' ), $n ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Rozetler (WhatsApp, görev, bekleyen bağış, güncelleme) WordPress Heartbeat isteğine eklenir; ayrı istek açılmaz. Hostinger için 60 sn önerilir.', 'flx-admin' ); ?></p></td></tr>
			</table>
		</div>
		<?php
	} elseif ( 'look' === $tab ) {
		flxa_settings_look( $s );
	} elseif ( 'menu' === $tab ) {
		echo '<p class="flxa-lead">' . esc_html__( 'Her rol kendi sol menüsünü ve mobil alt barını görür. Sürükleyip sırala, yeniden adlandır, gizle, grupla. Gizlemek yetkiyi değiştirmez; yalnız menüden kaldırır.', 'flx-admin' ) . '</p>';
		if ( ! flxa_menu_catalog() ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Menü listesi bir sonraki sayfa açılışında oluşur. Sayfayı yenileyin.', 'flx-admin' ) . '</p></div>';
		}
		echo '<div id="flxa-menu-editor" class="flxa-me"></div>';
		echo '<p class="description">' . esc_html__( '"Erişimi engelle" o menüyü bu rol için gerçekten kapatır: menüde görünmez, adresle açılırsa "yetkiniz yok" sayfası çıkar. Ayrıntılı yetkiler için Yetkiler sekmesi.', 'flx-admin' ) . '</p>';
		echo '<input type="hidden" name="menus" id="flxa-menus-json" value="">';
	} elseif ( 'caps' === $tab ) {
		flxa_caps_render();
	} elseif ( 'dashboard' === $tab ) {
		$labels = array(
			'donations_today' => __( 'Bugünkü bağış', 'flx-admin' ),
			'donations_month' => __( 'Bu ay', 'flx-admin' ),
			'pending'         => __( 'Onay bekleyen bağış', 'flx-admin' ),
			'whatsapp'        => __( 'Okunmamış WhatsApp', 'flx-admin' ),
			'tasks'           => __( 'Açık görevlerim', 'flx-admin' ),
			'system'          => __( 'Sistem kontrolü', 'flx-admin' ),
		);
		?>
		<div class="flxa-card">
			<h2><?php esc_html_e( 'Karşılama ve özet', 'flx-admin' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Üst bölüm', 'flx-admin' ); ?></th>
					<td><label><input type="checkbox" name="s[dash_header]" value="1" <?php checked( $s['dash_header'] ); ?>> <?php esc_html_e( 'Karşılama, tarih ve özet kutucukları', 'flx-admin' ); ?></label></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Özet kutucukları', 'flx-admin' ); ?></th>
					<td><fieldset>
					<?php foreach ( $labels as $k => $l ) : ?>
						<label><input type="checkbox" name="s[dash_stats][]" value="<?php echo esc_attr( $k ); ?>" <?php checked( in_array( $k, (array) $s['dash_stats'], true ) ); ?>> <?php echo esc_html( $l ); ?></label><br>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Kişi yalnız yetkisi olan kutucukları görür. Eklentiler `flxa_dashboard_stats` filtresiyle kutucuk ekleyebilir.', 'flx-admin' ); ?></p>
					</fieldset></td></tr>
				<tr><th scope="row"><label for="flxa-ql"><?php esc_html_e( 'Ek hızlı işlemler', 'flx-admin' ); ?></label></th>
					<td><textarea id="flxa-ql" name="s[quick_links]" rows="4" class="large-text code" placeholder="<?php esc_attr_e( "Etkinlikler|edit.php?post_type=etkinlik\nCanlı site|https://", 'flx-admin' ); ?>"><?php echo esc_textarea( $s['quick_links'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Satır başına "Etiket|Adres". Adres wp-admin\'e göre veya tam https:// olabilir.', 'flx-admin' ); ?></p></td></tr>
			</table>
			<p class="description"><?php esc_html_e( 'Modüllerin sırası ve görünürlüğü kişiye özeldir: Başlangıç\'ta "Modülleri düzenle" ile açılır, kutular sürüklenerek taşınır.', 'flx-admin' ); ?></p>
		</div>
		<?php
	} elseif ( 'pwa' === $tab ) {
		?>
		<div class="flxa-card">
			<h2><?php esc_html_e( 'Uygulama olarak yükleme', 'flx-admin' ); ?></h2>
			<?php if ( ! flxa_pwa_possible() ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Site HTTPS değil; tarayıcılar yüklemeye izin vermez.', 'flx-admin' ); ?></p></div>
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'PWA', 'flx-admin' ); ?></th>
					<td><label><input type="checkbox" name="s[pwa]" value="1" <?php checked( $s['pwa'] ); ?>> <?php esc_html_e( 'Panel telefona / bilgisayara uygulama gibi yüklenebilsin', 'flx-admin' ); ?></label>
					<p class="description"><?php esc_html_e( 'Tam ekran açılır, uygulama simgesinde okunmamış sayısı görünür, bağlantı kopunca çevrimdışı ekranı çıkar. Panel sayfaları önbelleğe alınmaz.', 'flx-admin' ); ?></p></td></tr>
				<tr><th scope="row"><label for="flxa-pn"><?php esc_html_e( 'Uygulama adı', 'flx-admin' ); ?></label></th>
					<td><input type="text" class="regular-text" id="flxa-pn" name="s[pwa_name]" value="<?php echo esc_attr( $s['pwa_name'] ); ?>" placeholder="<?php echo esc_attr( flxa_brand() ); ?>"></td></tr>
				<tr><th scope="row"><label for="flxa-ps"><?php esc_html_e( 'Kısa ad (simge altı)', 'flx-admin' ); ?></label></th>
					<td><input type="text" class="regular-text" id="flxa-ps" name="s[pwa_short]" maxlength="20" value="<?php echo esc_attr( $s['pwa_short'] ); ?>" placeholder="<?php echo esc_attr( flxa_pwa_short() ); ?>"></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Simge', 'flx-admin' ); ?></th>
					<td><img src="<?php echo esc_url( flxa_pwa_icons()[0]['src'] ); ?>" alt="" width="64" height="64" style="border-radius:14px">
					<p class="description"><?php esc_html_e( 'Site simgesi (Görünüm › Özelleştir › Site kimliği) varsa o kullanılır.', 'flx-admin' ); ?></p></td></tr>
			</table>
			<h3><?php esc_html_e( 'Nasıl yüklenir?', 'flx-admin' ); ?></h3>
			<ul class="flxa-howto">
				<li><strong>Android / Chrome:</strong> <?php esc_html_e( 'Başlangıç\'taki "Yükle" düğmesi veya menü › Uygulamayı yükle.', 'flx-admin' ); ?></li>
				<li><strong>iPhone / Safari:</strong> <?php esc_html_e( 'Paylaş › Ana Ekrana Ekle.', 'flx-admin' ); ?></li>
				<li><strong><?php esc_html_e( 'Bilgisayar', 'flx-admin' ); ?>:</strong> <?php esc_html_e( 'Adres çubuğundaki yükle simgesi.', 'flx-admin' ); ?></li>
			</ul>
		</div>
		<?php
	}
	submit_button( __( 'Kaydet', 'flx-admin' ) );
	echo '</form></div>';
}

function flxa_render_own_checks() {
	$rows = flxa_system_checks( array() );
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Kontrol', 'flx-admin' ) . '</th><th>' . esc_html__( 'Durum', 'flx-admin' ) . '</th><th>' . esc_html__( 'Açıklama', 'flx-admin' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><td>' . esc_html( $r['label'] ) . '</td><td>' . esc_html( $r['status'] ) . '</td><td>' . esc_html( $r['message'] );
		if ( ! empty( $r['action_url'] ) ) {
			echo ' <a href="' . esc_url( $r['action_url'] ) . '">' . esc_html( $r['action_label'] ) . '</a>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

function flxa_settings_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Yetkiniz yok.', 'flx-admin' ), 403 );
	}
	check_admin_referer( 'flxa_save' );
	$tab = sanitize_key( wp_unslash( $_POST['tab'] ?? 'general' ) );
	$in  = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$old = get_option( 'flxa_settings', array() );
	$s   = is_array( $old ) ? $old : array();

	if ( 'general' === $tab ) {
		foreach ( array( 'enabled', 'bottom_bar', 'app_rail', 'app_switcher', 'fast_nav', 'palette', 'login' ) as $k ) {
			$s[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		$s['brand']        = sanitize_text_field( (string) ( $in['brand'] ?? '' ) );
		$s['subtitle']     = sanitize_text_field( (string) ( $in['subtitle'] ?? '' ) );
		$s['logo_id']      = absint( $in['logo_id'] ?? 0 );
		$hb                = (int) ( $in['heartbeat'] ?? 60 );
		$s['heartbeat']    = in_array( $hb, array( 30, 60, 120 ), true ) ? $hb : 60;
	} elseif ( 'look' === $tab ) {
		$s['line_icons']   = empty( $in['line_icons'] ) ? 0 : 1;
		$s['logo_style']   = 'pastel' === ( $in['logo_style'] ?? '' ) ? 'pastel' : 'gradient';
		$c                 = sanitize_hex_color( (string) ( $in['accent'] ?? '' ) );
		// Temanın rengiyle aynıysa saklanmaz: tema/ön ayar rengi değişince yönetim paneli de değişir.
		$s['accent']       = ( $c && strtolower( $c ) !== strtolower( flxa_theme_primary() ) ) ? $c : '';
		$m                 = (string) ( $in['mode'] ?? 'auto' );
		$s['mode']         = in_array( $m, array( 'auto', 'light', 'dark' ), true ) ? $m : 'auto';
		$s['dark_exclude'] = implode( "\n", array_filter( array_map( 'sanitize_text_field', preg_split( '/\r\n|\r|\n/', (string) ( $in['dark_exclude'] ?? '' ) ) ) ) );
		$s['theme']        = flxa_theme_sanitize( is_array( $in['theme'] ?? null ) ? $in['theme'] : array() );
	} elseif ( 'caps' === $tab ) {
		flxa_caps_save();
	} elseif ( 'dashboard' === $tab ) {
		$s['dash_header'] = empty( $in['dash_header'] ) ? 0 : 1;
		$allowed          = array( 'donations_today', 'donations_month', 'pending', 'whatsapp', 'tasks', 'system' );
		$s['dash_stats']  = array_values( array_intersect( $allowed, array_map( 'strval', (array) ( $in['dash_stats'] ?? array() ) ) ) );
		$lines            = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) ( $in['quick_links'] ?? '' ) ) as $line ) {
			$p = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 === count( $p ) && '' !== $p[0] && '' !== $p[1] ) {
				$url     = preg_match( '#^https?://#i', $p[1] ) ? esc_url_raw( $p[1] ) : ltrim( preg_replace( '#[^A-Za-z0-9._\-/?=&%\#]#', '', $p[1] ), '/' );
				$lines[] = sanitize_text_field( $p[0] ) . '|' . $url;
			}
		}
		$s['quick_links'] = implode( "\n", $lines );
	} elseif ( 'pwa' === $tab ) {
		$s['pwa']       = empty( $in['pwa'] ) ? 0 : 1;
		$s['pwa_name']  = sanitize_text_field( (string) ( $in['pwa_name'] ?? '' ) );
		$s['pwa_short'] = mb_substr( sanitize_text_field( (string) ( $in['pwa_short'] ?? '' ) ), 0, 20 );
	} elseif ( 'menu' === $tab ) {
		$raw = json_decode( (string) wp_unslash( $_POST['menus'] ?? '' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_array( $raw ) ) {
			update_option( 'flxa_menus', array( 'roles' => flxa_sanitize_menus( $raw ) ), true );
		}
	}
	if ( ! in_array( $tab, array( 'menu', 'caps' ), true ) ) {
		update_option( 'flxa_settings', $s, true );
	}
	delete_transient( 'flxa_syscheck' );
	wp_safe_redirect( add_query_arg( array( 'tab' => $tab, 'updated' => 1 ), flxa_settings_url() ) );
	exit;
}

/** Menü düzenleyiciden gelen veriyi temizler: yalnız bilinen roller ve menüde olan kısa adlar. */
function flxa_sanitize_menus( array $raw ) {
	$roles = array_merge( array( '_default' ), array_keys( flxa_roles() ) );
	$known = array();
	foreach ( flxa_menu_catalog() as $it ) {
		$known[ $it['slug'] ] = wp_list_pluck( $it['sub'], 'slug' );
	}
	$out = array();
	foreach ( $raw as $role => $cfg ) {
		if ( ! in_array( $role, $roles, true ) || ! is_array( $cfg ) || empty( $cfg['entries'] ) || ! is_array( $cfg['entries'] ) ) {
			continue;
		}
		$entries = array();
		$seen    = array();
		$gn      = 0;
		foreach ( array_slice( $cfg['entries'], 0, 300 ) as $e ) {
			if ( ! is_array( $e ) ) {
				continue;
			}
			if ( 'g' === ( $e['t'] ?? '' ) ) {
				$label = mb_substr( sanitize_text_field( (string) ( $e['label'] ?? '' ) ), 0, 40 );
				$entries[] = array(
					't'     => 'g',
					'id'    => 'g' . ( ++$gn ),
					'label' => $label,
					'c'     => empty( $e['c'] ) ? 0 : 1,
				);
				continue;
			}
			$slug = (string) ( $e['slug'] ?? '' );
			if ( ! isset( $known[ $slug ] ) || isset( $seen[ $slug ] ) ) {
				continue;
			}
			$seen[ $slug ] = true;
			$item          = array( 't' => 'i', 'slug' => $slug );
			$label         = mb_substr( sanitize_text_field( (string) ( $e['label'] ?? '' ) ), 0, 40 );
			if ( '' !== $label ) {
				$item['label'] = $label;
			}
			if ( ! empty( $e['hidden'] ) ) {
				$item['hidden'] = 1;
			}
			if ( ! empty( $e['blocked'] ) && 'administrator' !== $role && ! in_array( $slug, flxa_unblockable(), true ) ) {
				$item['blocked'] = 1;
			}
			$pf_off = array_values( array_intersect( array_keys( flxa_menu_platforms() ), array_map( 'strval', (array) ( $e['pf_off'] ?? array() ) ) ) );
			if ( $pf_off ) {
				$item['pf_off'] = $pf_off;
			}
			$subs = array_values( array_intersect( $known[ $slug ], array_map( 'strval', (array) ( $e['sub_hidden'] ?? array() ) ) ) );
			if ( $subs ) {
				$item['sub_hidden'] = $subs;
			}
			$entries[] = $item;
		}
		$bottom = array_slice( array_values( array_unique( array_intersect( array_map( 'strval', (array) ( $cfg['bottom'] ?? array() ) ), array_keys( $known ) ) ) ), 0, 4 );
		if ( $entries ) {
			$out[ $role ] = array(
				'entries' => $entries,
				'bottom'  => $bottom,
			);
		}
	}
	return $out;
}
