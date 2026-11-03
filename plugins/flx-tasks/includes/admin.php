<?php
defined( 'ABSPATH' ) || exit;

require_once FLXG_DIR . 'includes/js-strings.php';

add_action( 'admin_menu', 'flxg_admin_menu' );
function flxg_admin_menu() {
	$count = current_user_can( 'flxg_use' ) ? flxg_unread_count( get_current_user_id() ) : 0;
	$badge = $count ? ' <span class="awaiting-mod count-' . (int) $count . '"><span class="pending-count">' . esc_html( number_format_i18n( $count ) ) . '</span></span>' : '';
	add_menu_page( __( 'Görevler', 'flx-tasks' ), __( 'Görevler', 'flx-tasks' ) . $badge, 'flxg_use', 'flx-tasks', 'flxg_render_app', 'none', 4 );
	add_submenu_page( 'flx-tasks', __( 'Görevler', 'flx-tasks' ), __( 'Görevler', 'flx-tasks' ), 'flxg_use', 'flx-tasks', 'flxg_render_app' );
	add_submenu_page( 'flx-tasks', __( 'Kullanım panosu', 'flx-tasks' ), __( 'Kullanım panosu', 'flx-tasks' ), 'flxg_manage', 'flx-tasks-pano', 'flxg_render_app' );
	add_submenu_page( 'flx-tasks', __( 'Görev Ayarları', 'flx-tasks' ), __( 'Ayarlar', 'flx-tasks' ), 'flxg_manage', 'flx-tasks-ayarlar', 'flxg_render_app' );
}

function flxg_is_app_screen() {
	return is_admin() && isset( $_GET['page'] ) && in_array( $_GET['page'], array( 'flx-tasks', 'flx-tasks-pano', 'flx-tasks-ayarlar' ), true ); // phpcs:ignore
}

add_filter(
	'admin_body_class',
	function ( $c ) {
		return flxg_is_app_screen() ? $c . ' flxg-page' : $c;
	}
);

add_action(
	'admin_init',
	function () {
		if ( flxg_is_app_screen() ) {
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		}
	}
);

add_action( 'admin_enqueue_scripts', 'flxg_admin_assets' );
function flxg_admin_assets() {
	if ( ! flxg_is_app_screen() ) {
		return;
	}
	$page  = sanitize_key( $_GET['page'] ?? '' ); // phpcs:ignore
	$start = 'flx-tasks-pano' === $page ? 'dashboard' : ( 'flx-tasks-ayarlar' === $page ? 'settings' : '' );
	wp_enqueue_style( 'flxg-app', FLXG_URL . 'assets/css/app.css', array(), FLXG_VERSION );
	wp_enqueue_script( 'flxg-core', FLXG_URL . 'assets/js/core.js', array(), FLXG_VERSION, true );
	wp_enqueue_script( 'flxg-state', FLXG_URL . 'assets/js/state.js', array( 'flxg-core' ), FLXG_VERSION, true );
	wp_enqueue_script( 'flxg-views', FLXG_URL . 'assets/js/views.js', array( 'flxg-state' ), FLXG_VERSION, true );
	wp_enqueue_script( 'flxg-task', FLXG_URL . 'assets/js/task.js', array( 'flxg-views' ), FLXG_VERSION, true );
	wp_enqueue_script( 'flxg-docs', FLXG_URL . 'assets/js/docs.js', array( 'flxg-task' ), FLXG_VERSION, true );
	wp_enqueue_script( 'flxg-app', FLXG_URL . 'assets/js/pages.js', array( 'flxg-docs' ), FLXG_VERSION, true );
	if ( function_exists( 'flxai_enqueue_model_picker' ) && current_user_can( 'manage_options' ) ) {
		flxai_enqueue_model_picker(); // Ayarlar'da Flux AI'nin aranabilir model listesi.
	}
	wp_localize_script(
		'flxg-core',
		'FLXG_BOOT',
		array(
			'rest'     => trailingslashit( rest_url( FLXG_NS ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'locale'   => str_replace( '_', '-', get_user_locale() ),
			'brand'    => flxg_brand_name(),
			'accent'   => flxg_color( flxg_setting( 'accent' ), function_exists( 'flxa_accent' ) ? flxa_accent() : '#1F2937' ),
			'adminUrl' => admin_url(),
			'appUrl'   => admin_url( 'admin.php?page=flx-tasks' ),
			'start'    => $start,
			'icon'     => get_site_icon_url( 128 ) ? get_site_icon_url( 128 ) : plugins_url( 'assets/icon.svg', FLXG_FILE ),
			'i18n'     => flxg_js_strings(),
		)
	);
}

/* Uygulama tam ekran: WP menüsü ve çubuğu üstünde kendi arayüzü. */
function flxg_render_app() {
	echo '<script>document.documentElement.classList.add("flxg-lock");</script>';
	echo '<div id="flxg-app" class="flxg"><noscript>' . esc_html__( 'Görevler için JavaScript gerekli.', 'flx-tasks' ) . '</noscript></div>';
	// Tema div'in hemen ardından, ilk boyamadan önce uygulanır (pages.js yüklenene kadar açık tema görünmesin).
	echo "<script>(function(d,w){var m;try{m=localStorage.getItem('flxg-theme')}catch(e){}m=m||'auto';var k=m==='dark'||(m==='auto'&&!!w.matchMedia&&w.matchMedia('(prefers-color-scheme: dark)').matches);d.getElementById('flxg-app').dataset.theme=k?'dark':'light';d.documentElement.classList.toggle('flxg-dark',k)})(document,window)</script>";
}

// Menü ikonu: ortak FluxWP logosu (renkli; WP menü ikonunu tek renge boyadığı için CSS ile).
add_action(
	'admin_head',
	function () {
		echo '<style>#toplevel_page_flx-tasks .wp-menu-image{background:url(' . esc_url( FLXG_URL . 'assets/icon.svg?v=' . FLXG_VERSION ) . ') center/20px 20px no-repeat}#toplevel_page_flx-tasks .wp-menu-image::before{content:none}</style>' . "\n";
	}
);

add_action(
	'admin_head',
	function () {
		if ( ! flxg_is_app_screen() ) {
			return;
		}
		echo '<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">' . "\n";
		echo '<meta name="theme-color" content="#16191d" media="(prefers-color-scheme: dark)">' . "\n";
		echo '<meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr__( 'Görevler', 'flx-tasks' ) . '">' . "\n";
		echo '<style>html.wp-toolbar{padding-top:0!important}</style>' . "\n";
	}
);

add_filter(
	'admin_viewport_meta',
	function ( $v ) {
		return flxg_is_app_screen() ? 'width=device-width,initial-scale=1.0,viewport-fit=cover' : $v;
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXG_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=flx-tasks-ayarlar' ) ) . '">' . esc_html__( 'Ayarlar', 'flx-tasks' ) . '</a>' );
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=flx-tasks' ) ) . '">' . esc_html__( 'Görevler', 'flx-tasks' ) . '</a>' );
		return $links;
	}
);

/* Üst çubukta okunmamış bildirim sayısı. */
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! is_user_logged_in() || ! current_user_can( 'flxg_use' ) || flxg_is_app_screen() ) {
			return;
		}
		$n = flxg_unread_count( get_current_user_id() );
		$bar->add_node(
			array(
				'id'    => 'flxg-notifs',
				'title' => '<span class="ab-icon dashicons dashicons-yes-alt" style="top:2px"></span>' . ( $n ? '<span class="ab-label">' . esc_html( number_format_i18n( $n ) ) . '</span>' : '' ),
				'href'  => admin_url( 'admin.php?page=flx-tasks#/inbox' ),
				'meta'  => array( 'title' => __( 'Görev bildirimleri', 'flx-tasks' ) ),
			)
		);
	},
	80
);
