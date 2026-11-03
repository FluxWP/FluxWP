<?php
defined( 'ABSPATH' ) || exit;

require_once FLXC_DIR . 'includes/js-strings.php';
require_once FLXC_DIR . 'includes/studio-strings.php';
require_once FLXC_DIR . 'includes/mp-strings.php';

/** 1.17.0: ortak model seçici (ayarlar ve asistan stüdyosu). */
function flxc_register_model_picker() {
	if ( wp_script_is( 'flxc-mp', 'registered' ) ) {
		return;
	}
	wp_register_style( 'flxc-mp', FLXC_URL . 'assets/css/model-picker.css', array(), FLXC_VERSION );
	wp_register_script( 'flxc-mp', FLXC_URL . 'assets/js/model-picker.js', array(), FLXC_VERSION, true );
	wp_localize_script(
		'flxc-mp',
		'FLXC_MP',
		array(
			'rest'  => trailingslashit( rest_url( 'flx-chat/v1' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'i18n'  => flxc_mp_strings(),
		)
	);
}

add_action( 'admin_menu', 'flxc_admin_menu' );
function flxc_admin_menu() {
	$count = flxc_can_use() ? flxc_unread_total() : 0;
	$badge = $count ? ' <span class="awaiting-mod count-' . (int) $count . '"><span class="pending-count">' . (int) number_format_i18n( $count ) . '</span></span>' : '';
	add_menu_page(
		__( 'Çağrı Merkezi', 'flx-chat' ),
		__( 'Çağrı Merkezi', 'flx-chat' ) . $badge,
		'flxc_use',
		'flx-chat',
		'flxc_render_app',
		'none', // renkli logo admin_head'deki CSS ile çizilir (WordPress menü ikonunu tek renge boyar)
		3
	);
	add_submenu_page( 'flx-chat', __( 'Sohbetler', 'flx-chat' ), __( 'Sohbetler', 'flx-chat' ), 'flxc_use', 'flx-chat', 'flxc_render_app' );
	add_submenu_page( 'flx-chat', __( 'AI asistan', 'flx-chat' ), __( 'AI asistan', 'flx-chat' ), 'flxc_manage', 'flx-chat-asistan', 'flxc_render_studio' );
	add_submenu_page( 'flx-chat', __( 'Çağrı Merkezi Ayarları', 'flx-chat' ), __( 'Ayarlar', 'flx-chat' ), 'flxc_manage', 'flx-chat-ayarlar', 'flxc_render_settings' );
}

/* Yöneticide flxc_use yetkisi olmasa da menüyü görsün (eklenti etkinleştirilmeden önceki roller için). */
add_filter(
	'user_has_cap',
	function ( $all ) {
		if ( ! empty( $all['manage_options'] ) ) {
			$all['flxc_use']    = true;
			$all['flxc_manage'] = true;
		}
		return $all;
	}
);

add_filter(
	'admin_body_class',
	function ( $c ) {
		return flxc_is_app_screen() ? $c . ' flxc-page' : $c;
	}
);

function flxc_is_app_screen() {
	return is_admin() && isset( $_GET['page'] ) && 'flx-chat' === $_GET['page']; // phpcs:ignore
}

/**
 * Yeni arayüz (deneysel, 1.35.0): Sohbetler sayfası eski app.js yerine FluxWP ile ortak React ekranını
 * (assets/flux/, tools/fluxapp/embed-sync.sh üretir) açar. Dosyalar yoksa eski arayüz.
 */
function flxc_flux_ui() {
	return (bool) flxc_setting( 'ui_flux' ) && file_exists( FLXC_DIR . 'assets/flux/flux-modules.js' ) && (bool) apply_filters( 'flxc_flux_ui', true );
}

/* WP emoji betiği dinamik mesajlardaki emojileri <img>'ye çeviriyor, bazı ağlarda kırık simge çıkıyordu. Uygulamada sistem emojisi kullanılır. */
add_action(
	'admin_init',
	function () {
		$page = isset( $_GET['page'] ) ? (string) $_GET['page'] : ''; // phpcs:ignore
		if ( 0 === strpos( $page, 'flx-chat' ) ) {
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		}
	}
);

add_action( 'admin_enqueue_scripts', 'flxc_admin_assets' );
function flxc_admin_assets() {
	if ( ! flxc_is_app_screen() ) {
		return;
	}
	if ( flxc_flux_ui() ) {
		$ver = FLXC_VERSION . '-' . (int) filemtime( FLXC_DIR . 'assets/flux/flux-modules.js' );
		wp_enqueue_style( 'flxc-flux', FLXC_URL . 'assets/flux/flux-modules.css', array(), $ver );
		wp_enqueue_script( 'flxc-flux', FLXC_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
		// Renkler uygulamayla aynı olsun diye marka (brand.json) renkleri kullanılır.
		$opts = array(
			'module' => 'flxc-inbox',
			'rest'   => rest_url(),
			'nonce'  => wp_create_nonce( 'wp_rest' ),
			'locale' => get_user_locale(),
		);
		wp_add_inline_script(
			'flxc-flux',
			'(function(){var el=document.getElementById("flxc-flux");if(el&&window.FluxModules){try{window.FluxModules.mount(el,' . wp_json_encode( $opts ) . ');}catch(e){el.textContent=String(e&&e.message||e);}}})();'
		);
		return;
	}
	wp_enqueue_style( 'flxc-app', FLXC_URL . 'assets/css/app.css', array(), FLXC_VERSION );
	wp_enqueue_script( 'flxc-app', FLXC_URL . 'assets/js/app.js', array(), FLXC_VERSION, true );
	$accent = sanitize_hex_color( (string) flxc_setting( 'accent' ) );
	wp_localize_script(
		'flxc-app',
		'FLXC_BOOT',
		array(
			'rest'        => trailingslashit( rest_url( 'flx-chat/v1' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'locale'      => str_replace( '_', '-', get_user_locale() ),
			'brand'       => flxc_brand_name(),
			'accent'      => $accent ? $accent : '#00a884',
			'poll'        => max( 3, (int) flxc_setting( 'poll_interval' ) ),
			'adminUrl'    => admin_url(),
			'settingsUrl' => admin_url( 'admin.php?page=flx-chat-ayarlar' ),
			'exportUrl'   => wp_nonce_url( admin_url( 'admin-post.php?action=flxc_export' ), 'flxc_export' ),
			'icon'        => get_site_icon_url( 128 ),
			'i18n'        => flxc_js_strings(),
		)
	);
}

/* Uygulama tam ekran: WP menüsü/çubuğu üstünde kendi arayüzü. */
function flxc_render_app() {
	if ( flxc_flux_ui() ) {
		// WP menüsü ve üst çubuk kalır; ekran içerik alanının kalanını kaplar.
		echo '<style>#wpcontent{padding-left:0}#wpbody-content{padding-bottom:0}#wpfooter{display:none}#flxc-flux{height:calc(100vh - var(--wp-admin--admin-bar--height,32px));min-height:480px}</style>';
		echo '<div id="flxc-flux"><noscript>' . esc_html__( 'Çağrı Merkezi için JavaScript gerekli.', 'flx-chat' ) . '</noscript></div>';
		return;
	}
	echo '<script>document.documentElement.classList.add("flxc-lock");</script>';
	echo '<div id="flxc-app" class="flxc" data-theme="auto"><noscript>' . esc_html__( 'Çağrı Merkezi için JavaScript gerekli.', 'flx-chat' ) . '</noscript></div>';
	// Kayıtlı tema (açık / koyu / otomatik) ilk boyamadan önce; app.js yüklenene kadar yanlış tema görünmesin.
	echo "<script>(function(d,w){var m;try{m=localStorage.getItem('flxc_theme')}catch(e){}m=m||'auto';d.getElementById('flxc-app').setAttribute('data-theme',m);d.documentElement.classList.toggle('flxc-dark-doc',m==='dark'||(m==='auto'&&!!w.matchMedia&&w.matchMedia('(prefers-color-scheme: dark)').matches))})(document,window)</script>";
}

/* Uygulama ekranında tarayıcı çubuğu rengi ve ana ekrana ekleme. */
add_action(
	'admin_head',
	function () {
		if ( ! flxc_is_app_screen() ) {
			return;
		}
		echo '<meta name="theme-color" content="#f0f2f5" media="(prefers-color-scheme: light)">' . "\n";
		echo '<meta name="theme-color" content="#202c33" media="(prefers-color-scheme: dark)">' . "\n";
		echo '<meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr__( 'Çağrı Merkezi', 'flx-chat' ) . '">' . "\n";
		if ( ! flxc_flux_ui() ) {
			echo '<style>html.wp-toolbar{padding-top:0!important}</style>' . "\n";
		}
	}
);

/* Viewport: wp-admin mobilde zaten width=device-width verir; iOS çentik alanı için viewport-fit ekle. */
add_filter(
	'admin_viewport_meta',
	function ( $v ) {
		return flxc_is_app_screen() ? 'width=device-width,initial-scale=1.0,viewport-fit=cover' : $v;
	}
);

/* Eklentiler listesinde kısa yollar. */
add_filter(
	'plugin_action_links_' . plugin_basename( FLXC_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=flx-chat-ayarlar' ) ) . '">' . esc_html__( 'Ayarlar', 'flx-chat' ) . '</a>' );
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=flx-chat' ) ) . '">' . esc_html__( 'Sohbetler', 'flx-chat' ) . '</a>' );
		return $links;
	}
);

/* Ortak FluxWP logosu (assets/icon.svg) menüde renkli görünsün. */
add_action(
	'admin_head',
	function () {
		$url = esc_url( FLXC_URL . 'assets/icon.svg?v=' . FLXC_VERSION );
		echo '<style>#toplevel_page_flx-chat .wp-menu-image{background:url(' . $url . ') center/20px 20px no-repeat}#toplevel_page_flx-chat .wp-menu-image:before{content:none}</style>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
);

/* AI asistan: asistanın sohbetleri, talimat sürümleri, belgeler, düzenleyici ve test sohbeti. */
function flxc_render_studio() {
	if ( ! flxc_can_manage() ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok.', 'flx-chat' ) );
	}
	?>
	<div class="wrap flxbs">
		<header class="flxbs-head">
			<div>
				<h1><?php esc_html_e( 'AI asistan', 'flx-chat' ); ?></h1>
				<p><?php esc_html_e( 'Asistanın yazıştığı sohbetleri izleyin, talimatını ve belgelerini düzenleyin, değişiklikleri yayına almadan önce test edin.', 'flx-chat' ); ?></p>
			</div>
			<a class="flxbs-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=flx-chat-ayarlar&tab=ai' ) ); ?>"><?php esc_html_e( 'Asistan ayarları', 'flx-chat' ); ?> →</a>
		</header>
		<div id="flxbs-app"><noscript><?php esc_html_e( 'Bu sayfa için JavaScript gerekli.', 'flx-chat' ); ?></noscript></div>
	</div>
	<?php
}

add_action(
	'admin_enqueue_scripts',
	function () {
		if ( ! isset( $_GET['page'] ) || 'flx-chat-asistan' !== $_GET['page'] ) { // phpcs:ignore
			return;
		}
		flxc_register_model_picker();
		wp_enqueue_style( 'flxc-studio', FLXC_URL . 'assets/css/studio.css', array( 'flxc-mp' ), FLXC_VERSION );
		wp_enqueue_script( 'flxc-bench', FLXC_URL . 'assets/js/bench.js', array( 'flxc-mp' ), FLXC_VERSION, true );
		wp_enqueue_script( 'flxc-studio', FLXC_URL . 'assets/js/studio.js', array( 'flxc-bench' ), FLXC_VERSION, true );
		wp_localize_script(
			'flxc-studio',
			'FLXC_STUDIO',
			array(
				'rest'        => trailingslashit( rest_url( 'flx-chat/v1' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'locale'      => str_replace( '_', '-', get_user_locale() ),
				'settingsUrl' => admin_url( 'admin.php?page=flx-chat-ayarlar' ),
				'i18n'        => flxc_studio_strings(),
			)
		);
	}
);
