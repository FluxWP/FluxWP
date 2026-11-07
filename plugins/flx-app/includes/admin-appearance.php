<?php
/**
 * Uygulama › Görünüm (tema, mobil ve masaüstü kabuğu, ekranlar, iki platform önizlemesi) ve
 * Uygulama › Uygulamalar (ana uygulama, marka istemcileri, mağaza linkleri, push, geliştirici modu) sekmeleri.
 * Cihaz listesi (Kullanıcılar › Uygulama erişimi ve Tüm erişimler) de burada.
 */

defined( 'ABSPATH' ) || exit;

/** Seçim kutuları için hedef kataloğu: grup => [ hedef => ad ]. */
function flxapp_target_catalog() {
	$out = array(
		__( 'Uygulama', 'flx-app' ) => array(
			'more'          => __( 'Menü (tüm öğeler)', 'flx-app' ),
			'search'        => __( 'Arama', 'flx-app' ),
			'notifications' => __( 'Bildirimler', 'flx-app' ),
			'accounts'      => __( 'Hesaplar', 'flx-app' ),
			'settings'      => __( 'Uygulama ayarları', 'flx-app' ),
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
	$out[ __( 'Ekranlar', 'flx-app' ) ] = $scr;
	$mods = array();
	foreach ( (array) apply_filters( 'flxapp_modules', array() ) as $id => $m ) {
		$mods[ 'module:' . sanitize_key( $id ) ] = (string) ( $m['title'] ?? $id );
	}
	if ( $mods ) {
		$out[ __( 'Yerel modüller', 'flx-app' ) ] = $mods;
	}
	$menu = array();
	foreach ( flxapp_user_menu( get_current_user_id() ) as $m ) {
		$menu[ 'menu:' . $m['slug'] ] = $m['title'];
	}
	if ( $menu ) {
		$out[ __( 'Panel menüsü', 'flx-app' ) ] = $menu;
	}
	return $out;
}

function flxapp_target_select( $name, $value, $empty = '' ) {
	$h = '<select name="' . esc_attr( $name ) . '" class="flxapp-select">';
	$h .= '<option value="">' . esc_html( '' !== $empty ? $empty : '—' ) . '</option>';
	foreach ( flxapp_target_catalog() as $group => $items ) {
		$h .= '<optgroup label="' . esc_attr( $group ) . '">';
		foreach ( $items as $k => $label ) {
			$h .= '<option value="' . esc_attr( $k ) . '"' . selected( $value, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$h .= '</optgroup>';
	}
	return $h . '</select>';
}

function flxapp_color_labels() {
	return array(
		'primary' => __( 'Ana renk', 'flx-app' ),
		'accent'  => __( 'Vurgu', 'flx-app' ),
		'bg'      => __( 'Zemin', 'flx-app' ),
		'surface' => __( 'Kart', 'flx-app' ),
		'text'    => __( 'Yazı', 'flx-app' ),
	);
}

/** Görünüm sekmesi. */
function flxapp_render_appearance_tab() {
	$o     = flxapp_app_option();
	$theme = flxapp_theme();
	$mob   = (array) ( $o['shell']['mobile'] ?? array() );
	$desk  = (array) ( $o['shell']['desktop'] ?? array() );
	$user  = wp_get_current_user();
	$cfgm  = flxapp_config( $user, 'mobile' );
	$cfgd  = flxapp_config( $user, 'desktop' );
	echo '<p class="flxapp-lead">' . esc_html__( 'Masaüstü ve mobil uygulamanın görünümü. Renkler boş bırakılırsa tema (theme.json) ve Flux Admin renginden gelir. Menüler kullanıcının rolüne göre süzülür: göremediği öğe uygulamada da çıkmaz.', 'flx-app' ) . '</p>';
	echo '<div class="flxapp-appearance"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxapp-ap-form"><input type="hidden" name="action" value="flxapp_appearance">';
	wp_nonce_field( 'flxapp_appearance' );

	// Tema.
	echo '<section class="flxapp-card"><h2>' . esc_html__( 'Tema', 'flx-app' ) . '</h2>';
	echo '<p class="flxapp-row"><label>' . esc_html__( 'Mod', 'flx-app' ) . ' <select name="theme[mode]">';
	foreach ( array( 'system' => __( 'Cihaza göre', 'flx-app' ), 'light' => __( 'Açık', 'flx-app' ), 'dark' => __( 'Koyu', 'flx-app' ) ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $theme['mode'], $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></label>';
	echo '<label>' . esc_html__( 'Köşe', 'flx-app' ) . ' <select name="theme[radius]"><option value="">' . esc_html__( 'Temadan', 'flx-app' ) . '</option>';
	foreach ( array( 'none' => __( 'Keskin', 'flx-app' ), 'small' => __( 'Az', 'flx-app' ), 'medium' => __( 'Orta', 'flx-app' ), 'large' => __( 'Yuvarlak', 'flx-app' ) ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $o['theme']['radius'] ?? '', $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></label>';
	echo '<label>' . esc_html__( 'Yoğunluk', 'flx-app' ) . ' <select name="theme[density]"><option value="">' . esc_html__( 'Temadan', 'flx-app' ) . '</option>';
	foreach ( array( 'comfortable' => __( 'Rahat', 'flx-app' ), 'compact' => __( 'Sıkı', 'flx-app' ) ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $o['theme']['density'] ?? '', $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></label>';
	foreach ( array( 'body' => __( 'Yazı tipi', 'flx-app' ), 'heading' => __( 'Başlık yazı tipi', 'flx-app' ) ) as $fk => $fl ) {
		echo '<label>' . esc_html( $fl ) . ' <select name="theme[font][' . esc_attr( $fk ) . ']"><option value="">' . esc_html( sprintf( /* translators: %s: font name */ __( 'Temadan (%s)', 'flx-app' ), $theme['font'][ $fk ] ) ) . '</option>';
		foreach ( flxapp_fonts() as $f ) {
			echo '<option' . selected( $o['theme']['font'][ $fk ] ?? '', $f, false ) . '>' . esc_html( $f ) . '</option>';
		}
		echo '</select></label>';
	}
	echo '</p><table class="flxapp-colors"><thead><tr><th></th>';
	foreach ( flxapp_color_labels() as $l ) {
		echo '<th>' . esc_html( $l ) . '</th>';
	}
	echo '</tr></thead><tbody>';
	foreach ( array( 'light' => __( 'Açık', 'flx-app' ), 'dark' => __( 'Koyu', 'flx-app' ) ) as $m => $ml ) {
		echo '<tr><th>' . esc_html( $ml ) . '</th>';
		foreach ( flxapp_theme_color_keys() as $k ) {
			$set = (string) ( $o['theme'][ $m ][ $k ] ?? '' );
			echo '<td><span class="flxapp-swatch" style="background:' . esc_attr( $theme[ $m ][ $k ] ) . '"></span><input type="text" class="flxapp-hex" name="theme[' . esc_attr( $m ) . '][' . esc_attr( $k ) . ']" value="' . esc_attr( $set ) . '" placeholder="' . esc_attr( $theme[ $m ][ $k ] ) . '" maxlength="7" pattern="#[0-9A-Fa-f]{6}" aria-label="' . esc_attr( $ml . ' ' . flxapp_color_labels()[ $k ] ) . '"></td>';
		}
		echo '</tr>';
	}
	echo '</tbody></table></section>';

	// Mobil.
	$tabs = array_values( (array) ( $mob['tabs'] ?? array() ) );
	echo '<section class="flxapp-card"><h2>' . esc_html__( 'Mobil', 'flx-app' ) . '</h2>';
	echo '<p class="flxapp-row"><label>' . esc_html__( 'Gezinme', 'flx-app' ) . ' <select name="mobile[nav]">';
	foreach ( array( 'tabs+drawer' => __( 'Alt bar + yan menü', 'flx-app' ), 'tabs' => __( 'Yalnız alt bar', 'flx-app' ), 'drawer' => __( 'Yalnız yan menü', 'flx-app' ) ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $mob['nav'] ?? 'tabs+drawer', $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></label><label>' . esc_html__( 'Açılış ekranı', 'flx-app' ) . ' ' . flxapp_target_select( 'mobile[home]', (string) ( $mob['home'] ?? '' ), __( 'Başlangıç', 'flx-app' ) ) . '</label></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<h3>' . esc_html__( 'Alt bar (en çok 5)', 'flx-app' ) . '</h3><p class="description">' . esc_html__( 'Boş bırakılırsa Flux Admin mobil alt bar ayarı kullanılır. Kullanıcının göremediği sekme gizlenir; "Menü" her zaman erişilebilir kalır.', 'flx-app' ) . '</p><table class="flxapp-tabs">';
	for ( $i = 0; $i < 5; $i++ ) {
		$t = (array) ( $tabs[ $i ] ?? array() );
		echo '<tr><th>' . (int) ( $i + 1 ) . '</th><td>' . flxapp_target_select( 'mobile[tabs][' . $i . '][target]', (string) ( $t['target'] ?? '' ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<td><input type="text" name="mobile[tabs][' . (int) $i . '][label]" value="' . esc_attr( (string) ( $t['label'] ?? '' ) ) . '" placeholder="' . esc_attr__( 'Etiket', 'flx-app' ) . '" class="regular-text flxapp-short"></td>';
		echo '<td><input type="text" name="mobile[tabs][' . (int) $i . '][icon]" value="' . esc_attr( (string) ( $t['icon'] ?? '' ) ) . '" placeholder="' . esc_attr__( 'İkon (home, chat…)', 'flx-app' ) . '" class="flxapp-short"></td>';
		echo '<td><input type="text" name="mobile[tabs][' . (int) $i . '][badge]" value="' . esc_attr( (string) ( $t['badge'] ?? '' ) ) . '" placeholder="' . esc_attr__( 'Sayaç', 'flx-app' ) . '" class="flxapp-short"></td></tr>';
	}
	echo '</table><p class="flxapp-row">';
	echo '<label class="flxapp-check"><input type="checkbox" name="mobile[drawer][enabled]" value="1"' . checked( ! isset( $mob['drawer']['enabled'] ) || ! empty( $mob['drawer']['enabled'] ), true, false ) . '> ' . esc_html__( 'Yan menü (rol menüsü grupları)', 'flx-app' ) . '</label>';
	echo '<label class="flxapp-check"><input type="checkbox" name="mobile[topbar][show_logo]" value="1"' . checked( ! isset( $mob['topbar']['show_logo'] ) || ! empty( $mob['topbar']['show_logo'] ), true, false ) . '> ' . esc_html__( 'Üst barda logo', 'flx-app' ) . '</label>';
	echo '<label class="flxapp-check"><input type="checkbox" name="mobile[topbar][site_switcher]" value="1"' . checked( ! isset( $mob['topbar']['site_switcher'] ) || ! empty( $mob['topbar']['site_switcher'] ), true, false ) . '> ' . esc_html__( 'Site değiştirici', 'flx-app' ) . '</label>';
	echo '<label>' . esc_html__( 'Üst bar', 'flx-app' ) . ' <select name="mobile[topbar][style]">';
	foreach ( array( 'large' => __( 'Büyük başlık', 'flx-app' ), 'compact' => __( 'Sade', 'flx-app' ) ) as $k => $l ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $mob['topbar']['style'] ?? 'large', $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	echo '</select></label></p></section>';

	// Masaüstü.
	$cmds = (array) ( $desk['commands'] ?? array() );
	echo '<section class="flxapp-card"><h2>' . esc_html__( 'Masaüstü', 'flx-app' ) . '</h2>';
	echo '<p class="flxapp-row"><label>' . esc_html__( 'Açılış ekranı', 'flx-app' ) . ' ' . flxapp_target_select( 'desktop[home]', (string) ( $desk['home'] ?? '' ), __( 'Başlangıç', 'flx-app' ) ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<label class="flxapp-check"><input type="checkbox" name="desktop[topbar][site_switcher]" value="1"' . checked( ! isset( $desk['topbar']['site_switcher'] ) || ! empty( $desk['topbar']['site_switcher'] ), true, false ) . '> ' . esc_html__( 'Site değiştirici', 'flx-app' ) . '</label></p>';
	echo '<p class="description">' . esc_html__( 'Kenar çubuğu, rolün Flux Admin menü düzenini (sabitlenenler ve gruplar) izler. Komut paletinde (⌘K) her zaman görünecek kısayollar:', 'flx-app' ) . '</p><p class="flxapp-row">';
	for ( $i = 0; $i < 4; $i++ ) {
		echo flxapp_target_select( 'desktop[commands][' . $i . ']', (string) ( $cmds[ $i ] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</p></section>';

	// Ekranlar.
	$off = (array) flxapp_opt( 'screens_off' );
	echo '<section class="flxapp-card"><h2>' . esc_html__( 'Ekranlar', 'flx-app' ) . '</h2><p class="description">' . esc_html__( 'Kapatılan ekran uygulamada yerel olarak açılmaz; o sayfa web görünümünde açılır.', 'flx-app' ) . '</p><div class="flxapp-screens">';
	foreach ( flxapp_screen_catalog() as $k => $l ) {
		echo '<label class="flxapp-check"><input type="checkbox" name="screens_on[]" value="' . esc_attr( $k ) . '"' . checked( ! in_array( $k, $off, true ), true, false ) . '> ' . esc_html( $l ) . '</label>';
	}
	echo '<input type="hidden" name="screens_all" value="' . esc_attr( implode( ',', array_keys( flxapp_screen_catalog() ) ) ) . '"></div></section>';
	submit_button( __( 'Kaydet', 'flx-app' ) );
	echo '</form>';

	// Önizleme.
	echo '<aside class="flxapp-preview"><h2>' . esc_html__( 'Önizleme', 'flx-app' ) . '</h2>';
	flxapp_render_preview( $cfgm, $cfgd );
	echo '</aside></div>';
}

/** Target için okunur ad. */
function flxapp_target_label( $target ) {
	foreach ( flxapp_target_catalog() as $items ) {
		if ( isset( $items[ $target ] ) ) {
			$l = $items[ $target ];
			return false !== strpos( $l, ' › ' ) ? substr( $l, strrpos( $l, ' › ' ) + strlen( ' › ' ) ) : $l;
		}
	}
	return $target;
}

/** İki platform önizlemesi (yöneticinin kendi rolüyle, çözümlenmiş config'ten). */
function flxapp_render_preview( array $m, array $d ) {
	$t     = $m['theme'];
	$style = function ( $mode ) use ( $t ) {
		$c = $t[ $mode ];
		return '--p:' . $c['primary'] . ';--op:' . $c['onPrimary'] . ';--bg:' . $c['bg'] . ';--sf:' . $c['surface'] . ';--tx:' . $c['text'] . ';--ac:' . $c['accent'];
	};
	$logo  = (string) ( $m['brand']['icon'] ? $m['brand']['icon'] : $m['brand']['logo'] );
	foreach ( array( 'light', 'dark' ) as $mode ) {
		echo '<div class="flxapp-phone is-' . esc_attr( $mode ) . '" style="' . esc_attr( $style( $mode ) ) . '"><div class="flxapp-phone-top">';
		if ( $logo && ! empty( $m['shell']['topbar']['show_logo'] ) ) {
			echo '<img src="' . esc_url( $logo ) . '" alt="" width="20" height="20">';
		}
		echo '<strong>' . esc_html( $m['brand']['name'] ) . '</strong></div><div class="flxapp-phone-body"><div class="flxapp-ph-card"></div><div class="flxapp-ph-card is-half"></div><div class="flxapp-ph-card is-half"></div><div class="flxapp-ph-row"></div><div class="flxapp-ph-row"></div></div><div class="flxapp-phone-tabs">';
		foreach ( (array) $m['shell']['tabs'] as $i => $tab ) {
			echo '<span class="' . ( 0 === $i ? 'is-on' : '' ) . '"><i></i>' . esc_html( ! empty( $tab['label'] ) ? $tab['label'] : flxapp_target_label( $tab['target'] ) ) . '</span>';
		}
		echo '</div></div>';
	}
	$sb = $d['shell']['sidebar'];
	echo '<div class="flxapp-desk" style="' . esc_attr( $style( 'light' ) ) . '"><div class="flxapp-desk-side"><strong>' . esc_html( $d['brand']['name'] ) . '</strong>';
	foreach ( array_slice( (array) $sb['pins'], 0, 3 ) as $p ) {
		echo '<span class="is-pin">' . esc_html( flxapp_target_label( $p ) ) . '</span>';
	}
	foreach ( array_slice( (array) $sb['groups'], 0, 3 ) as $g ) {
		if ( '' !== $g['label'] ) {
			echo '<em>' . esc_html( $g['label'] ) . '</em>';
		}
		foreach ( array_slice( (array) $g['items'], 0, 4 ) as $it ) {
			echo '<span>' . esc_html( flxapp_target_label( $it ) ) . '</span>';
		}
	}
	echo '</div><div class="flxapp-desk-main"><div class="flxapp-desk-top">⌘K</div><div class="flxapp-ph-card"></div><div class="flxapp-ph-row"></div><div class="flxapp-ph-row"></div><div class="flxapp-ph-row"></div></div></div>';
	echo '<p class="description">' . esc_html__( 'Önizleme sizin rolünüzle; diğer roller kendi menülerini görür.', 'flx-app' ) . '</p>';
}

function flxapp_handle_appearance() {
	check_admin_referer( 'flxapp_appearance' );
	if ( ! current_user_can( 'manage_options' ) ) {
		flxapp_deny();
	}
	$p   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$cur = flxapp_app_option();
	$mob = (array) ( $p['mobile'] ?? array() );
	$mob['tabs']                  = array_values( array_filter( (array) ( $mob['tabs'] ?? array() ), fn( $t ) => '' !== (string) ( $t['target'] ?? '' ) ) );
	$mob['drawer']['enabled']     = ! empty( $mob['drawer']['enabled'] );
	$mob['topbar']['show_logo']   = ! empty( $mob['topbar']['show_logo'] );
	$mob['topbar']['site_switcher'] = ! empty( $mob['topbar']['site_switcher'] );
	$mob['drawer']['groups']      = $cur['shell']['mobile']['drawer']['groups'] ?? null;
	$mob['drawer']['pins']        = $cur['shell']['mobile']['drawer']['pins'] ?? array();
	$mob['topbar']['actions']     = $cur['shell']['mobile']['topbar']['actions'] ?? null;
	$desk                         = (array) ( $p['desktop'] ?? array() );
	$desk['commands']             = array_values( array_filter( (array) ( $desk['commands'] ?? array() ) ) );
	$desk['topbar']['site_switcher'] = ! empty( $desk['topbar']['site_switcher'] );
	$desk['sidebar']              = $cur['shell']['desktop']['sidebar'] ?? array();
	$desk['topbar']['actions']    = $cur['shell']['desktop']['topbar']['actions'] ?? null;
	$new = flxapp_app_sanitize(
		array(
			'theme'    => (array) ( $p['theme'] ?? array() ),
			'shell'    => array( 'mobile' => $mob, 'desktop' => $desk ),
			'features' => $cur['features'] ?? array(),
			'links'    => $cur['links'] ?? array(),
			'brand'    => $cur['brand'] ?? array(),
		)
	);
	update_option( 'flxapp_app', $new, false );
	$all = array_filter( explode( ',', (string) ( $p['screens_all'] ?? '' ) ) );
	$on  = array_map( 'strval', (array) ( $p['screens_on'] ?? array() ) );
	$s   = flxapp_settings();
	$s['screens_off'] = array_values( array_unique( array_merge( array_diff( (array) $s['screens_off'], $all ), array_diff( $all, $on ) ) ) );
	update_option( 'flxapp_settings', $s, false );
	wp_safe_redirect( flxapp_admin_url( array( 'tab' => 'appearance', 'flxapp_msg' => 'saved' ) ) );
	exit;
}

/* ───────── Uygulamalar sekmesi ───────── */

function flxapp_render_mobile_tab( array $s ) {
	$last    = (array) get_option( 'flxapp_push_last', array() );
	$devices = flxapp_user_devices( 0 );
	$push    = count( array_filter( $devices, fn( $d ) => '' !== (string) $d['token_hash'] ) );
	echo '<p class="flxapp-lead">' . esc_html__( 'Bu siteye hangi uygulamaların bağlanabileceği, mağaza bağlantıları ve bildirimler.', 'flx-app' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxapp_mobile">';
	wp_nonce_field( 'flxapp_mobile' );
	echo '<table class="form-table">';
	echo '<tr><th>' . esc_html__( 'Ana FluxWP uygulaması', 'flx-app' ) . '</th><td><label><input type="checkbox" name="generic_app" value="1"' . checked( $s['generic_app'], 1, false ) . '> ' . esc_html__( 'Masaüstü ve mobil FluxWP uygulaması bağlanabilsin (flx-masaustu, flx-mobil)', 'flx-app' ) . '</label><p class="description">' . esc_html__( 'Kapatılırsa yalnız aşağıdaki markalı uygulamalar bağlanır; ana uygulamanın açık oturumları bir sonraki yenilemede kapanır.', 'flx-app' ) . '</p></td></tr>';
	echo '<tr><th>' . esc_html__( 'Mağaza bağlantıları', 'flx-app' ) . '</th><td><p><input type="url" class="regular-text" name="store_ios" value="' . esc_attr( (string) $s['store_ios'] ) . '" placeholder="https://apps.apple.com/…"> App Store</p><p><input type="url" class="regular-text" name="store_android" value="' . esc_attr( (string) $s['store_android'] ) . '" placeholder="https://play.google.com/…"> Google Play</p></td></tr>';
	echo '<tr><th>' . esc_html__( 'Bildirimler', 'flx-app' ) . '</th><td><label><input type="checkbox" name="push_enabled" value="1"' . checked( $s['push_enabled'], 1, false ) . '> ' . esc_html__( 'Mobil cihazlara push bildirimi gönder (Expo)', 'flx-app' ) . '</label><br><label><input type="checkbox" name="push_hide" value="1"' . checked( $s['push_hide'], 1, false ) . '> ' . esc_html__( 'Kilit ekranında içeriği gizle (yalnız bildirim türü görünür)', 'flx-app' ) . '</label>';
	echo '<p><input type="password" class="regular-text" name="push_expo_token" value="" autocomplete="new-password" placeholder="' . esc_attr( flxapp_secret_has( 'push_expo_token' ) ? __( 'Kayıtlı (değiştirmek için yazın)', 'flx-app' ) : __( 'Expo erişim belirteci (isteğe bağlı)', 'flx-app' ) ) . '"> <label><input type="checkbox" name="push_expo_token_clear" value="1"> ' . esc_html__( 'Sil', 'flx-app' ) . '</label></p>';
	echo '<p class="description">' . esc_html__( 'Yalnız Expo hesabında gelişmiş push güvenliği açıksa gerekir. Masaüstü uygulaması push almaz; bildirimleri açıkken eşitleme ile gösterir.', 'flx-app' ) . '</p>';
	/* translators: 1: device count, 2: devices with push */
	echo '<p>' . esc_html( sprintf( __( 'Cihaz: %1$d · bildirim açık: %2$d', 'flx-app' ), count( $devices ), $push ) );
	if ( ! empty( $last['at'] ) ) {
		/* translators: 1: time ago, 2: sent, 3: failed */
		echo ' · ' . esc_html( sprintf( __( 'Son gönderim %1$s: %2$d gönderildi, %3$d hata', 'flx-app' ), flx_time_ago( (int) $last['at'] ), (int) ( $last['sent'] ?? 0 ), (int) ( $last['failed'] ?? 0 ) ) );
		if ( ! empty( $last['error'] ) ) {
			echo ' <span class="flxapp-bad">' . esc_html( (string) $last['error'] ) . '</span>';
		}
	}
	echo '</p></td></tr>';
	echo '<tr><th>' . esc_html__( 'Web görünümü', 'flx-app' ) . '</th><td><label><input type="checkbox" name="embed_front" value="1"' . checked( $s['embed_front'], 1, false ) . '> ' . esc_html__( 'Uygulamada açılan site sayfalarında yönetim çubuğunu gizle', 'flx-app' ) . '</label></td></tr>';
	echo '<tr><th>' . esc_html__( 'Geliştirici modu', 'flx-app' ) . '</th><td><label><input type="checkbox" name="dev_redirects" value="1"' . checked( $s['dev_redirects'], 1, false ) . '> ' . esc_html__( 'Expo geliştirme dönüş adreslerine (exp://…/--/oauth/callback) izin ver', 'flx-app' ) . '</label><p class="description">' . esc_html__( 'Yalnız test sitelerinde açın.', 'flx-app' ) . '</p></td></tr>';
	echo '</table>';

	echo '<h2>' . esc_html__( 'Markalı uygulamalar', 'flx-app' ) . '</h2><p class="description">' . esc_html__( 'Kurumun kendi adıyla yayımlanan uygulaması bu siteye yalnız burada tanımlıysa bağlanır. Genelde ön ayar (preset) doldurur.', 'flx-app' ) . '</p>';
	echo '<table class="widefat striped flxapp-table flxapp-clients"><thead><tr><th>client_id</th><th>' . esc_html__( 'Ad', 'flx-app' ) . '</th><th>' . esc_html__( 'Platform', 'flx-app' ) . '</th><th>' . esc_html__( 'Dönüş adresleri (satır başına bir)', 'flx-app' ) . '</th></tr></thead><tbody>';
	$rows   = array_values( (array) $s['app_clients'] );
	$rows[] = array();
	foreach ( $rows as $i => $c ) {
		echo '<tr><td data-label="client_id"><input type="text" name="app_clients[' . (int) $i . '][id]" value="' . esc_attr( (string) ( $c['id'] ?? '' ) ) . '" placeholder="flx-mobil-marka" class="flxapp-short"></td>';
		echo '<td data-label="' . esc_attr__( 'Ad', 'flx-app' ) . '"><input type="text" name="app_clients[' . (int) $i . '][name]" value="' . esc_attr( (string) ( $c['name'] ?? '' ) ) . '" class="flxapp-short"></td>';
		echo '<td data-label="' . esc_attr__( 'Platform', 'flx-app' ) . '"><select name="app_clients[' . (int) $i . '][platform]">';
		foreach ( array( 'mobile' => __( 'Mobil', 'flx-app' ), 'desktop' => __( 'Masaüstü', 'flx-app' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $c['platform'] ?? 'mobile', $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></td><td data-label="' . esc_attr__( 'Dönüş adresleri', 'flx-app' ) . '"><textarea name="app_clients[' . (int) $i . '][redirect_uris]" rows="2" class="large-text" placeholder="marka://oauth/callback">' . esc_textarea( implode( "\n", (array) ( $c['redirect_uris'] ?? array() ) ) ) . '</textarea></td></tr>';
	}
	echo '</tbody></table>';
	submit_button( __( 'Kaydet', 'flx-app' ) );
	echo '</form>';

	echo '<h2>' . esc_html__( 'Bağlantı bilgileri', 'flx-app' ) . '</h2><table class="form-table flxapp-meta">';
	echo '<tr><th>bootstrap</th><td><code>' . esc_html( rest_url( 'flxapp/v1/app/bootstrap' ) ) . '</code></td></tr>';
	echo '<tr><th>flx-mobil</th><td><code>fluxwp://oauth/callback</code></td></tr>';
	echo '<tr><th>flx-masaustu</th><td><code>http://127.0.0.1:&lt;port&gt;/callback</code> · <code>fluxapp://oauth/callback</code></td></tr>';
	echo '<tr><th>' . esc_html__( 'Etkin ön ayar', 'flx-app' ) . '</th><td><code>' . esc_html( flxapp_site_preset() ? flxapp_site_preset() : '—' ) . '</code></td></tr></table>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxapp_push_test">';
	wp_nonce_field( 'flxapp_push_test' );
	echo '<p><button class="button">' . esc_html__( 'Kendime deneme bildirimi gönder', 'flx-app' ) . '</button></p></form>';
}

function flxapp_handle_mobile() {
	check_admin_referer( 'flxapp_mobile' );
	if ( ! current_user_can( 'manage_options' ) ) {
		flxapp_deny();
	}
	$p       = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$clients = array();
	foreach ( (array) ( $p['app_clients'] ?? array() ) as $c ) {
		$id = sanitize_key( (string) ( $c['id'] ?? '' ) );
		if ( '' === $id ) {
			continue;
		}
		$clients[] = array(
			'id'            => $id,
			'name'          => sanitize_text_field( (string) ( $c['name'] ?? $id ) ),
			'platform'      => 'desktop' === ( $c['platform'] ?? '' ) ? 'desktop' : 'mobile',
			'redirect_uris' => array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', (string) ( $c['redirect_uris'] ?? '' ) ) ), 'flxapp_brand_redirect_ok' ) ),
		);
	}
	$s = flxapp_settings();
	foreach ( array( 'generic_app', 'push_enabled', 'push_hide', 'embed_front', 'dev_redirects' ) as $k ) {
		$s[ $k ] = empty( $p[ $k ] ) ? 0 : 1;
	}
	$s['store_ios']     = esc_url_raw( (string) ( $p['store_ios'] ?? '' ), array( 'https' ) );
	$s['store_android'] = esc_url_raw( (string) ( $p['store_android'] ?? '' ), array( 'https' ) );
	$s['app_clients']   = $clients;
	update_option( 'flxapp_settings', $s, false );
	if ( ! empty( $p['push_expo_token_clear'] ) ) {
		flxapp_secret_put( 'push_expo_token', '' );
	} elseif ( '' !== trim( (string) ( $p['push_expo_token'] ?? '' ) ) ) {
		flxapp_secret_put( 'push_expo_token', preg_replace( '/[^A-Za-z0-9_\-.]/', '', (string) $p['push_expo_token'] ) );
	}
	wp_safe_redirect( flxapp_admin_url( array( 'tab' => 'mobile', 'flxapp_msg' => 'saved' ) ) );
	exit;
}

function flxapp_handle_push_test() {
	check_admin_referer( 'flxapp_push_test' );
	if ( ! current_user_can( 'manage_options' ) ) {
		flxapp_deny();
	}
	$r = flxapp_push(
		get_current_user_id(),
		array(
			'title'  => get_bloginfo( 'name' ),
			'body'   => __( 'Deneme bildirimi: bu cihaz bildirim alıyor.', 'flx-app' ),
			'target' => 'notifications',
			'now'    => true,
			'test'   => true,
			'public' => true,
		)
	);
	set_transient( 'flxapp_push_test_' . get_current_user_id(), $r, MINUTE_IN_SECONDS );
	wp_safe_redirect( flxapp_admin_url( array( 'tab' => 'mobile', 'flxapp_msg' => 'push' ) ) );
	exit;
}

/* ───────── Cihazlar ───────── */

function flxapp_render_devices( array $rows, $show_user ) {
	if ( ! $rows ) {
		echo '<p class="flxapp-empty">' . esc_html__( 'Uygulamada oturum açılmış cihaz yok.', 'flx-app' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped flxapp-table"><thead><tr>';
	if ( $show_user ) {
		echo '<th>' . esc_html__( 'Kullanıcı', 'flx-app' ) . '</th>';
	}
	echo '<th>' . esc_html__( 'Cihaz', 'flx-app' ) . '</th><th>' . esc_html__( 'Uygulama', 'flx-app' ) . '</th><th>' . esc_html__( 'Bildirim', 'flx-app' ) . '</th><th>' . esc_html__( 'Son görülme', 'flx-app' ) . '</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$o = flxapp_device_out( $r );
		echo '<tr>';
		if ( $show_user ) {
			$u = get_userdata( (int) $r['user_id'] );
			echo '<td data-label="' . esc_attr__( 'Kullanıcı', 'flx-app' ) . '">' . esc_html( $u ? $u->display_name : '#' . $r['user_id'] ) . '</td>';
		}
		$name = '' !== $o['name'] ? $o['name'] : ( '' !== $o['model'] ? $o['model'] : __( 'Adsız cihaz', 'flx-app' ) );
		echo '<td data-label="' . esc_attr__( 'Cihaz', 'flx-app' ) . '"><strong>' . esc_html( $name ) . '</strong> <span class="flxapp-muted">' . esc_html( trim( $o['platform_label'] . ' ' . $o['model'] ) ) . '</span></td>';
		echo '<td data-label="' . esc_attr__( 'Uygulama', 'flx-app' ) . '">' . esc_html( trim( $o['app'] . ' ' . $o['app_version'] ) ) . ( '' !== $o['brand'] ? ' <code>' . esc_html( $o['brand'] ) . '</code>' : '' ) . '</td>';
		echo '<td data-label="' . esc_attr__( 'Bildirim', 'flx-app' ) . '"><span class="flxapp-pf' . ( $o['push'] ? ' is-on' : '' ) . '">' . ( $o['push'] ? esc_html__( 'Açık', 'flx-app' ) : esc_html__( 'Kapalı', 'flx-app' ) ) . '</span></td>';
		echo '<td data-label="' . esc_attr__( 'Son görülme', 'flx-app' ) . '">' . esc_html( $o['last_seen_label'] ) . '</td>';
		echo '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Bu cihazdaki oturum kapatılsın mı?', 'flx-app' ) ) . '\')"><input type="hidden" name="action" value="flxapp_device"><input type="hidden" name="id" value="' . (int) $r['id'] . '">';
		wp_nonce_field( 'flxapp_device' );
		echo '<button class="button button-link-delete">' . esc_html__( 'Oturumu kapat', 'flx-app' ) . '</button></form></td></tr>';
	}
	echo '</tbody></table>';
}

function flxapp_handle_device() {
	check_admin_referer( 'flxapp_device' );
	$row = flxapp_device_get( absint( $_POST['id'] ?? 0 ) );
	if ( ! $row || ( (int) $row['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) ) {
		flxapp_deny();
	}
	flxapp_device_revoke( $row );
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : flxapp_admin_url() );
	exit;
}
