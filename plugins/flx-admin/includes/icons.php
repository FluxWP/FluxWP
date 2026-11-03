<?php
defined( 'ABSPATH' ) || exit;

/** Çizgi simgeler (24×24, stroke currentColor). */
function flxa_icon_paths() {
	return array(
		'grid'     => '<rect x="3" y="3" width="7" height="9" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/><rect x="3" y="16" width="7" height="5" rx="2"/>',
		'heart'    => '<path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 12 6a5.5 5.5 0 0 0-10 2.5c0 2.3 1.5 4 3 5.5l7 7z"/>',
		'chat'     => '<path d="M21 12a8.5 8.5 0 0 1-12.6 7.4L3 21l1.6-5.2A8.5 8.5 0 1 1 21 12z"/>',
		'check'    => '<path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
		'medal'    => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 8 5-3 5 3-1.5-8"/>',
		'form'     => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h3"/>',
		'pen'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
		'file'     => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/>',
		'image'    => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
		'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'users'    => '<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6"/><path d="M17 11a3 3 0 1 0 0-6"/><path d="M22 21c0-3-2-5-4-5.5"/>',
		'gear'     => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"/>',
		'plug'     => '<path d="M9 2v6M15 2v6"/><path d="M6 8h12v4a6 6 0 0 1-12 0z"/><path d="M12 18v4"/>',
		'brush'    => '<path d="M18.4 2.6a2 2 0 0 1 3 3L12 15l-3-3z"/><path d="M9 12c-2 0-4 1.5-4 4 0 1.5-1 3-3 3 1 1 3 2 5 2 3 0 5-2 5-5"/>',
		'bag'      => '<path d="M6 7h12l1 14H5z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
		'comment'  => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
		'tool'     => '<path d="M14.7 6.3a4 4 0 0 0 5 5L21 13l-8 8-3-3 8-8-1.3-1.3a4 4 0 0 0-5-5L14 5z"/><path d="M3 21l6-6"/>',
		'refresh'  => '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
		'shield'   => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
		'chart'    => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
		'box'      => '<path d="M21 8l-9-5-9 5 9 5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
		'leaf'     => '<path d="M12 22V12"/><path d="M12 12C12 7 8 4 4 4c0 5 3 8 8 8z"/><path d="M12 14c0-4 3-7 8-7 0 4-3 7-8 7z"/>',
		'archive'  => '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9M10 13h4"/>',
		'bolt'     => '<path d="M13 2 3 14h9l-1 8 10-12h-9z"/>',
		'more'     => '<circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>',
		'menu'     => '<path d="M4 7h16M4 12h16M4 17h10"/>',
		'moon'     => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
		'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		'auto'     => '<circle cx="12" cy="12" r="9"/><path d="M12 3v18" /><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/>',
		'plus'     => '<path d="M12 5v14M5 12h14"/>',
		'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
		'pin'      => '<path d="M9 4h6l-1 6 3 3H7l3-3z"/><path d="M12 13v8"/>',
		'sidebar'  => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/><path d="m15 10-2 2 2 2"/>',
		'lock'     => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
		'unlock'   => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.5-2"/>',
		'up'       => '<path d="M12 19V5M6 11l6-6 6 6"/>',
		'down'     => '<path d="M12 5v14M6 13l6 6 6-6"/>',
		'swatch'   => '<circle cx="12" cy="12" r="9"/><circle cx="8" cy="10" r="1.3"/><circle cx="12" cy="7.5" r="1.3"/><circle cx="16" cy="10" r="1.3"/><path d="M12 21a2.5 2.5 0 0 1 0-5h2a3 3 0 0 0 3-3"/>',
		'inbox'    => '<path d="M3 13h5l1.5 3h5L16 13h5"/><path d="M5 5h14l2 8v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6z"/>',
		'chevron'  => '<path d="m9 6 6 6-6 6"/>',
		'drag'     => '<circle cx="9" cy="6" r="1.3"/><circle cx="15" cy="6" r="1.3"/><circle cx="9" cy="12" r="1.3"/><circle cx="15" cy="12" r="1.3"/><circle cx="9" cy="18" r="1.3"/><circle cx="15" cy="18" r="1.3"/>',
		'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		'eye-off'  => '<path d="M3 3l18 18"/><path d="M10.6 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.2M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.6 9.6 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
		'download' => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M5 21h14"/>',
		'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
		'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M19 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5"/>',
		'dot'      => '<circle cx="12" cy="12" r="4"/>',
	);
}

function flxa_icon( $key, $size = 20, $extra = '' ) {
	$p = flxa_icon_paths();
	$d = $p[ $key ] ?? $p['dot'];
	return '<svg class="flxa-ico" aria-hidden="true" focusable="false" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $extra . '>' . $d . '</svg>';
}

/** CSS mask için data URI. */
function flxa_icon_data_uri( $key ) {
	$p = flxa_icon_paths();
	$d = $p[ $key ] ?? $p['dot'];
	$s = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . str_replace( 'currentColor', '#000', $d ) . '</svg>';
	return 'data:image/svg+xml,' . rawurlencode( $s );
}

/**
 * Menü kısa adı → simge. Sol menüde yalnız WordPress / WooCommerce menülerine uygulanır (FluxWP eklentilerinin
 * renkli logoları korunur); alt barda ve komut paletinde hepsi için kullanılır. Filtre `flxa_menu_icons`.
 */
function flxa_menu_icon_map() {
	return (array) apply_filters(
		'flxa_menu_icons',
		array(
			'index.php'                      => 'grid',
			'edit.php'                       => 'pen',
			'upload.php'                     => 'image',
			'edit.php?post_type=page'        => 'file',
			'edit-comments.php'              => 'comment',
			'themes.php'                     => 'brush',
			'plugins.php'                    => 'plug',
			'users.php'                      => 'users',
			'profile.php'                    => 'users',
			'tools.php'                      => 'tool',
			'options-general.php'            => 'gear',
			'woocommerce'                    => 'bag',
			'edit.php?post_type=product'     => 'box',
			'wc-admin&path=/analytics/overview' => 'chart',
			'woocommerce-marketing'          => 'bolt',
			'edit.php?post_type=proje'       => 'leaf',
			'edit.php?post_type=etkinlik'    => 'calendar',
			'litespeed'                      => 'bolt',
			// FluxWP paketleri (alt bar / palet; sol menüde kendi logoları kalır).
			'flxb-dashboard'                  => 'heart',
			'flx-chat'                       => 'chat',
			'flx-tasks'                       => 'check',
			'flxs'                            => 'medal',
			'Emsfb'                          => 'form',
			'flxi'                            => 'mail',
			'flxseo'                          => 'search',
			'flx-updater'                => 'refresh',
			'flx-backup'                       => 'archive',
		)
	);
}

function flxa_icon_for_slug( $slug ) {
	$m = flxa_menu_icon_map();
	return $m[ $slug ] ?? '';
}

/** Bu menü öğesi bir FluxWP paketine mi ait (renkli logosu korunur). */
function flxa_is_flx_slug( $slug ) {
	return in_array( $slug, array( 'flxb-dashboard', 'flx-chat', 'flx-tasks', 'flxs', 'Emsfb', 'flxi', 'flxseo', 'flx-updater', 'flx-backup', 'flx-wc-bagis' ), true )
		|| 0 === strpos( $slug, 'flx-' ) || 0 === strpos( $slug, 'flx_' );
}

/** FluxWP paketlerinin menü kısa adı → ortak renkli logo (assets/logos/). Filtre `flxa_menu_logos`. */
function flxa_logo_for_slug( $slug ) {
	$m = (array) apply_filters(
		'flxa_menu_logos',
		array(
			'flxb-dashboard'  => 'flx-donate.svg',
			'flx-chat'        => 'flx-chat.svg',
			'flx-tasks'       => 'flx-tasks.svg',
			'flxs'            => 'flx-certificates.svg',
			'Emsfb'           => 'flx-forms.svg',
			'flxi'            => 'flx-mail.svg',
			'flxseo'          => 'flx-seo.svg',
			'flx-updater'     => 'flx-updater.svg',
			'flx-backup'      => 'flx-backup.svg',
			'flx-ai'          => 'flx-ai.svg',
			'flx-analytics'   => 'flx-analytics.svg',
			'flxapp-settings' => 'flx-app.svg',
			'flx-qr-menu'     => 'flx-qr-menu.svg',
			'flx-social'      => 'flx-social.svg',
			'flx-speed'       => 'flx-speed.svg',
			'flx-studio'      => 'flx-studio.svg',
			'flx-staff'       => 'flx-staff.svg',
			'flx-tv-remote'   => 'flx-tv-remote.svg',
			'flx-tv-board'    => 'flx-tv-board.svg',
		)
	);
	return isset( $m[ $slug ] ) ? sanitize_file_name( (string) $m[ $slug ] ) : '';
}

/**
 * Paket logosu stili: `gradient` (varsayılan) ya da `pastel`. Ayarlar › Yönetim teması › Görünüm.
 * Flux Updater ve Flux App de buna bakar. Filtre `flx_logo_style`.
 */
function flxa_logo_style() {
	$v = (string) apply_filters( 'flx_logo_style', (string) flxa_setting( 'logo_style' ) );
	return 'pastel' === $v ? 'pastel' : 'gradient';
}

/** assets/logos/ içindeki bir logonun adresi, seçili stile göre (pastel/ alt klasörü). */
function flxa_logo_file_url( $file ) {
	$file = sanitize_file_name( (string) $file );
	if ( '' === $file ) {
		return '';
	}
	$dir = 'pastel' === flxa_logo_style() && is_readable( FLXA_DIR . 'assets/logos/pastel/' . $file ) ? 'assets/logos/pastel/' : 'assets/logos/';
	return FLXA_URL . $dir . $file . '?ver=' . FLXA_VERSION;
}

/** Menü simgesi yok mu (boş, WordPress'in varsayılan dişlisi, 'none' / 'div' yer tutucusu). 'none' kendi CSS'iyle çizilebildiği için JS ayrıca bakar. */
function flxa_menu_icon_missing( $img ) {
	return '' === $img || 'dashicons-admin-generic' === $img;
}

/** Simgesiz eklentiler için addan / kısa addan çizgi simge tahmini. */
function flxa_guess_icon( $slug, $label ) {
	$h = strtolower( remove_accents( $slug . ' ' . $label ) );
	$rules = array(
		'search'   => array( 'seo', 'yoast', 'rank', 'search', 'arama' ),
		'form'     => array( 'form', 'contact', 'survey', 'anket' ),
		'mail'     => array( 'mail', 'smtp', 'newsletter', 'bulten', 'posta' ),
		'bolt'     => array( 'cache', 'speed', 'perf', 'optimi', 'litespeed', 'rocket', 'hiz' ),
		'shield'   => array( 'secur', 'wordfence', 'firewall', 'guvenlik', 'captcha', 'spam', 'akismet' ),
		'archive'  => array( 'backup', 'yedek', 'migrat', 'duplicat', 'export', 'import' ),
		'chart'    => array( 'analytic', 'stat', 'insight', 'report', 'rapor', 'site kit', 'jetpack' ),
		'bag'      => array( 'shop', 'store', 'cart', 'order', 'siparis', 'payment', 'odeme', 'iyzico', 'pay' ),
		'calendar' => array( 'event', 'calendar', 'booking', 'etkinlik', 'takvim', 'randevu' ),
		'image'    => array( 'gallery', 'slider', 'media', 'galeri', 'image', 'photo' ),
		'brush'    => array( 'elementor', 'builder', 'theme', 'design', 'tema', 'customiz', 'blocks' ),
		'users'    => array( 'member', 'user', 'uye', 'kullanici', 'role' ),
		'comment'  => array( 'chat', 'comment', 'yorum', 'whatsapp', 'support', 'destek' ),
		'file'     => array( 'page', 'sayfa', 'doc', 'pdf' ),
		'gear'     => array( 'setting', 'ayar', 'option', 'config' ),
	);
	foreach ( $rules as $icon => $words ) {
		foreach ( $words as $w ) {
			if ( false !== strpos( $h, $w ) ) {
				return $icon;
			}
		}
	}
	return '';
}

/** Ad baş harfi (Türkçe büyük harf). */
function flxa_initial( $label ) {
	$l = trim( wp_strip_all_tags( (string) $label ) );
	if ( '' === $l ) {
		return '•';
	}
	$c = mb_substr( $l, 0, 1 );
	return 'i' === $c ? 'İ' : mb_strtoupper( $c );
}
