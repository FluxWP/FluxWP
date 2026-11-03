<?php
defined( 'ABSPATH' ) || exit;

/** Varsayılan ayarlar. Hepsi Ayarlar › Yönetim teması ekranından değişir. */
function flxa_defaults() {
	return array(
		'enabled'          => 1,
		'brand'            => '',
		'subtitle'         => '',
		'logo_id'          => 0,
		'accent'           => '',         // boş: sitenin tema paletindeki ana renk (flxa_theme_primary)
		'mode'             => 'auto',   // auto | light | dark (kullanıcı kendi seçimini yapabilir)
		'dark_exclude'     => 'block-editor-page',
		'line_icons'       => 1,        // WordPress çekirdek menülerinde çizgi simgeler
		'logo_style'       => 'gradient', // FluxWP paket logoları: gradient | pastel
		'bottom_bar'       => 1,
		'app_switcher'     => 1,        // WhatsApp / Görevler ekranlarında küçük geçiş düğmesi (telefonda)
		'app_rail'         => 1,        // WhatsApp / Görevler ekranında sol menü ikon şeridi olarak kalır
		'fast_nav'         => 1,        // menü bağlantılarında önden yükleme + yumuşak sayfa geçişi
		'theme'            => array(),  // renk, yazı tipi, köşe, yoğunluk (flxa_theme_defaults)
		'palette'          => 1,
		'dash_header'      => 1,
		'dash_stats'       => array( 'donations_today', 'donations_month', 'pending', 'whatsapp', 'tasks', 'system' ),
		'quick_links'      => '',
		'login'            => 1,
		'pwa'              => 1,
		'pwa_name'         => '',
		'pwa_short'        => '',
		'heartbeat'        => 60,
	);
}

function flxa_settings() {
	static $s = null;
	if ( null === $s ) {
		$saved = get_option( 'flxa_settings', array() );
		$s     = wp_parse_args( is_array( $saved ) ? $saved : array(), flxa_defaults() );
	}
	return $s;
}

function flxa_setting( $key ) {
	$s = flxa_settings();
	return $s[ $key ] ?? null;
}

function flxa_enabled() {
	return (bool) flxa_setting( 'enabled' );
}

function flxa_settings_url( $tab = '' ) {
	$url = admin_url( 'options-general.php?page=flx-admin' );
	return $tab ? add_query_arg( 'tab', $tab, $url ) : $url;
}

function flxa_brand() {
	$b = trim( (string) flxa_setting( 'brand' ) );
	return '' !== $b ? $b : (string) get_bloginfo( 'name' );
}

function flxa_subtitle() {
	$s = trim( (string) flxa_setting( 'subtitle' ) );
	return '' !== $s ? $s : __( 'Yönetim', 'flx-admin' );
}

/**
 * Vurgu rengi: ayarlarda seçilen, yoksa sitenin tema paletindeki ana renk (FluxWP temasında indigo,
 * ön ayar ya da Site Editor değiştirdiyse o), o da yoksa FluxWP indigo.
 */
function flxa_accent() {
	$c = sanitize_hex_color( (string) flxa_setting( 'accent' ) );
	return $c ? $c : flxa_theme_primary();
}

/**
 * Koyu temadaki vurgu: koyu bir vurgu (ör. antrasit) koyu zeminde kaybolur. Ayar boşsa temanın koyu mod rengi
 * (theme.json settings.custom.dark.primary), değilse çok koyu renkler açılır.
 */
function flxa_accent_dark() {
	$acc = flxa_accent();
	if ( '' === sanitize_hex_color( (string) flxa_setting( 'accent' ) ) && function_exists( 'wp_get_global_settings' ) ) {
		// Yol yoksa wp_get_global_settings() tüm ayar dizisini döndürür; yalnız metin değeri al.
		$d = wp_get_global_settings( array( 'custom', 'dark', 'primary' ) );
		$d = is_string( $d ) ? sanitize_hex_color( $d ) : '';
		if ( $d ) {
			return $d;
		}
	}
	$h = ltrim( $acc, '#' );
	if ( 6 !== strlen( $h ) ) {
		return $acc;
	}
	$rgb = array_map( 'hexdec', str_split( $h, 2 ) );
	$lum = ( 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2] ) / 255;
	if ( $lum >= 0.3 ) {
		return $acc;
	}
	$out = '#';
	foreach ( $rgb as $c ) {
		$out .= str_pad( dechex( (int) round( $c * 0.45 + 255 * 0.55 ) ), 2, '0', STR_PAD_LEFT );
	}
	return $out;
}

/** Tema paletindeki "primary" rengi (Site Editor › Stiller'deki değişiklik dahil). */
function flxa_theme_primary() {
	static $c = null;
	if ( null !== $c ) {
		return $c;
	}
	$c = '';
	if ( function_exists( 'wp_get_global_settings' ) ) {
		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		foreach ( array( 'custom', 'theme' ) as $origin ) {
			foreach ( (array) ( $palette[ $origin ] ?? array() ) as $item ) {
				if ( '' === $c && 'primary' === ( $item['slug'] ?? '' ) ) {
					$c = (string) sanitize_hex_color( $item['color'] ?? '' );
				}
			}
		}
	}
	$c = (string) apply_filters( 'flxa_default_accent', $c ? $c : '#1F2937' );
	return $c;
}

/**
 * Logo: ayarlardan yüklenen görsel (Ayarlar › Yönetim teması › Logo seç), yoksa kurumun yaprak ikonu.
 * Site simgesi bilerek kullanılmaz: çoğu sitede yazılı tam logodur, dar alanda okunmaz.
 * Varsayılan `flxa_logo_url` filtresiyle değiştirilebilir.
 */
function flxa_logo_url( $size = 96 ) {
	$id = (int) flxa_setting( 'logo_id' );
	if ( $id ) {
		$u = wp_get_attachment_image_url( $id, array( $size, $size ) );
		if ( $u ) {
			return $u;
		}
	}
	return (string) apply_filters( 'flxa_logo_url', FLXA_URL . 'assets/brand-icon.svg?ver=' . FLXA_VERSION, $size );
}

/** Kullanıcının tema tercihi: auto | light | dark. */
function flxa_user_mode( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$m       = $user_id ? (string) get_user_meta( $user_id, 'flxa_mode', true ) : '';
	if ( ! in_array( $m, array( 'auto', 'light', 'dark' ), true ) ) {
		$m = (string) flxa_setting( 'mode' );
	}
	return in_array( $m, array( 'auto', 'light', 'dark' ), true ) ? $m : 'auto';
}

/** Tam ekran uygulama sayfaları (kendi arayüzleri var; tema dokunmaz). */
function flxa_app_screen() {
	if ( ! is_admin() || empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return '';
	}
	$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$map  = (array) apply_filters(
		'flxa_app_screens',
		array(
			'flx-chat'         => 'whatsapp',
			'flx-tasks'         => 'tasks',
			'flx-tasks-pano'    => 'tasks',
			'flx-tasks-ayarlar' => 'tasks',
		)
	);
	return isset( $map[ $page ] ) ? (string) $map[ $page ] : '';
}

/**
 * Uygulama geçişi (sol üst sekmeler, mobil alt bar, uygulama ekranındaki geçiş düğmesi).
 * Filtre `flxa_apps`: id => {label, url, cap, icon, badge_key}.
 */
function flxa_apps() {
	$apps = array(
		'panel' => array(
			'label' => __( 'Panel', 'flx-admin' ),
			'url'   => admin_url(),
			'cap'   => 'read',
			'icon'  => 'grid',
		),
	);
	if ( defined( 'FLXC_VERSION' ) ) {
		$apps['whatsapp'] = array(
			'label'     => __( 'WhatsApp', 'flx-admin' ),
			'url'       => admin_url( 'admin.php?page=flx-chat' ),
			'cap'       => 'flxc_use',
			'icon'      => 'chat',
			'badge_key' => 'whatsapp',
		);
	}
	if ( defined( 'FLXG_VERSION' ) ) {
		$apps['tasks'] = array(
			'label'     => __( 'Görevler', 'flx-admin' ),
			'url'       => admin_url( 'admin.php?page=flx-tasks' ),
			'cap'       => 'flxg_use',
			'icon'      => 'check',
			'badge_key' => 'tasks',
		);
	}
	$apps = (array) apply_filters( 'flxa_apps', $apps );
	$out  = array();
	foreach ( $apps as $id => $a ) {
		if ( empty( $a['url'] ) || empty( $a['label'] ) ) {
			continue;
		}
		if ( ! empty( $a['cap'] ) && ! current_user_can( $a['cap'] ) && ! current_user_can( 'manage_options' ) ) {
			continue;
		}
		$out[ $id ] = wp_parse_args( $a, array( 'icon' => 'dot', 'badge_key' => '' ) );
	}
	return $out;
}

/**
 * Canlı sayaçlar (menü rozetleri, alt bar, uygulama rozeti). Ucuz sorgular; istek başına bir kez.
 * Filtre `flxa_counts`.
 */
function flxa_counts() {
	static $c = null;
	if ( null !== $c ) {
		return $c;
	}
	$c = array();
	try {
		if ( function_exists( 'flxc_unread_total' ) && function_exists( 'flxc_can_use' ) && flxc_can_use() ) {
			$c['whatsapp'] = (int) flxc_unread_total();
		}
		if ( function_exists( 'flxg_unread_count' ) && current_user_can( 'flxg_use' ) ) {
			$c['tasks'] = (int) flxg_unread_count( get_current_user_id() );
		}
		if ( function_exists( 'flxb_table' ) && flxa_can_view_donations() ) {
			$c['pending'] = flxa_pending_donations();
		}
		if ( current_user_can( 'update_plugins' ) || current_user_can( 'update_core' ) || current_user_can( 'update_themes' ) ) {
			if ( ! function_exists( 'wp_get_update_data' ) ) {
				require_once ABSPATH . 'wp-includes/update.php';
			}
			$u            = wp_get_update_data();
			$c['updates'] = (int) ( $u['counts']['total'] ?? 0 );
		}
	} catch ( Throwable $e ) {
		// Bir eklentinin hatası paneli düşürmesin.
	}
	$c = (array) apply_filters( 'flxa_counts', $c );
	return $c;
}

function flxa_can_view_donations() {
	return current_user_can( 'manage_options' ) || ( defined( 'FLXB_CAP_VIEW' ) && current_user_can( FLXB_CAP_VIEW ) );
}

/** Onay bekleyen bağış (havale/dekont) sayısı. 60 sn önbellek. */
function flxa_pending_donations() {
	$n = get_transient( 'flxa_pending_donations' );
	if ( false !== $n ) {
		return (int) $n;
	}
	if ( function_exists( 'flxb_pending_count' ) ) {
		// Flux Donate 2.0.9+: bir WooCommerce siparişi (sepet) tek bağış sayılır.
		$n = (int) flxb_pending_count();
	} else {
		global $wpdb;
		$t = flxb_table( 'donations' );
		// Tablo yoksa (eklenti yeni kuruluyor) sessizce 0.
		$wpdb->suppress_errors( true );
		$n = $wpdb->get_var( "SELECT SUM(CASE WHEN wc_order_id = 0 THEN 1 ELSE 0 END) + COUNT(DISTINCT CASE WHEN wc_order_id > 0 THEN wc_order_id END) FROM {$t} WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( null === $n && $wpdb->last_error ) {
			$n = $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$wpdb->suppress_errors( false );
		$n = (int) $n;
	}
	set_transient( 'flxa_pending_donations', $n, 60 );
	return $n;
}

/** Bu ekranda koyu tema kapalı mı (ayardaki gövde sınıfı / ekran kimliği listesi). */
function flxa_dark_excluded( $body_classes = '' ) {
	$list = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) flxa_setting( 'dark_exclude' ) ) ) );
	// 1.1.0 varsayılanı WooCommerce ekranlarını açık bırakıyordu; artık koyu tema orada da çalışıyor.
	if ( array( 'block-editor-page', 'woocommerce-admin-page' ) === array_values( $list ) ) {
		$list = array( 'block-editor-page' );
	}
	if ( ! $list ) {
		return false;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$id     = $screen ? (string) $screen->id : '';
	$hay    = ' ' . $body_classes . ' ';
	foreach ( $list as $p ) {
		if ( $id && ( $p === $id || ( substr( $p, -1 ) === '*' && 0 === strpos( $id, rtrim( $p, '*' ) ) ) ) ) {
			return true;
		}
		if ( false !== strpos( $hay, ' ' . $p . ' ' ) ) {
			return true;
		}
	}
	return false;
}

/** Ekranı tanıtan sözcükler (koyu tema hariç tutma listesi için; <head>'de gövde sınıfı henüz yok). */
function flxa_screen_tokens() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen ) {
		return '';
	}
	$t = array( $screen->id, $screen->base );
	if ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
		$t[] = 'block-editor-page';
	}
	if ( class_exists( 'Automattic\WooCommerce\Admin\PageController' ) && method_exists( 'Automattic\WooCommerce\Admin\PageController', 'is_admin_or_embed_page' ) ) {
		try {
			if ( \Automattic\WooCommerce\Admin\PageController::is_admin_or_embed_page() ) {
				$t[] = 'woocommerce-admin-page';
			}
		} catch ( Throwable $e ) {
			unset( $e );
		}
	}
	return implode( ' ', array_filter( $t ) );
}

/** Rol adları (çevrilmiş). */
function flxa_roles() {
	$out = array();
	foreach ( wp_roles()->roles as $key => $r ) {
		$out[ $key ] = translate_user_role( $r['name'] );
	}
	return $out;
}

/** Bir menü bağlantısının adresi (WordPress'in kendi kuralı). */
function flxa_menu_url( $slug, $parent = '' ) {
	if ( preg_match( '#^https?://#', $slug ) ) {
		return $slug;
	}
	if ( false !== strpos( $slug, '.php' ) ) {
		return admin_url( $slug );
	}
	if ( $parent && false !== strpos( $parent, '.php' ) && false === strpos( $parent, '?' ) ) {
		return add_query_arg( 'page', $slug, admin_url( $parent ) );
	}
	return admin_url( 'admin.php?page=' . $slug );
}

/** Menü etiketinden rozet HTML'ini ve etiketi ayırır. */
function flxa_split_label( $label ) {
	$label = (string) $label;
	$pos   = strpos( $label, '<' );
	if ( false === $pos ) {
		return array( trim( $label ), '' );
	}
	return array( trim( wp_strip_all_tags( substr( $label, 0, $pos ) ) ), substr( $label, $pos ) );
}

/** Tutar: "₺4.350" biçimi (yerel ayara göre). */
function flxa_money( $amount, $currency = 'TRY' ) {
	if ( function_exists( 'flxb_money' ) ) {
		return flxb_money( $amount, $currency, 0 );
	}
	$sym = array( 'TRY' => '₺', 'USD' => '$', 'EUR' => '€', 'GBP' => '£' );
	return ( $sym[ $currency ] ?? $currency . ' ' ) . number_format_i18n( (float) $amount, 0 );
}
