<?php
defined( 'ABSPATH' ) || exit;

require_once FLXG_DIR . 'includes/js-strings.php';

add_action( 'admin_menu', 'flxg_admin_menu' );
function flxg_admin_menu() {
	$count = current_user_can( 'flxg_use' ) ? flxg_unread_count( get_current_user_id() ) : 0;
	$badge = $count ? ' <span class="awaiting-mod count-' . (int) $count . '"><span class="pending-count">' . esc_html( number_format_i18n( $count ) ) . '</span></span>' : '';
	add_menu_page( __( 'Tasks', 'flx-tasks' ), __( 'Tasks', 'flx-tasks' ) . $badge, 'flxg_use', 'flx-tasks', 'flxg_render_app', 'none', 4 );
	add_submenu_page( 'flx-tasks', __( 'Tasks', 'flx-tasks' ), __( 'Tasks', 'flx-tasks' ), 'flxg_use', 'flx-tasks', 'flxg_render_app' );
	add_submenu_page( 'flx-tasks', __( 'Usage dashboard', 'flx-tasks' ), __( 'Usage dashboard', 'flx-tasks' ), 'flxg_manage', 'flx-tasks-pano', 'flxg_render_app' );
	add_submenu_page( 'flx-tasks', __( 'Task Settings', 'flx-tasks' ), __( 'Settings', 'flx-tasks' ), 'flxg_manage', 'flx-tasks-ayarlar', 'flxg_render_app' );
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

/*
 * New view (0.9.0, GraphQL): the Tasks pages open the same React screen as FluxApp
 * (assets/flux/, tools/fluxapp/embed-sync.sh flx-tasks). It is the default and has every feature of the
 * classic app (documents, usage dashboard, settings, AI panel); a user can still switch to the classic
 * view from the profile page. Needs Flux GraphQL.
 */
function flxg_flux_available() {
	return file_exists( FLXG_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && flx_gql_ready();
}

function flxg_flux_ui() {
	return flxg_flux_available() && flxg_is_app_screen() && 'classic' !== get_user_meta( get_current_user_id(), 'flxg_ui', true );
}

/** Link that switches the current user between the views. */
function flxg_ui_switch_url( $ui ) {
	return wp_nonce_url( admin_url( 'admin.php?page=flx-tasks&flxg_ui=' . ( 'flux' === $ui ? 'flux' : 'classic' ) ), 'flxg_ui' );
}

add_action(
	'admin_init',
	function () {
		if ( ! isset( $_GET['flxg_ui'] ) || ! current_user_can( 'flxg_use' ) ) { // phpcs:ignore
			return;
		}
		check_admin_referer( 'flxg_ui' );
		if ( 'classic' === $_GET['flxg_ui'] ) { // phpcs:ignore
			update_user_meta( get_current_user_id(), 'flxg_ui', 'classic' );
		} else {
			delete_user_meta( get_current_user_id(), 'flxg_ui' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=flx-tasks' ) );
		exit;
	}
);

/** The chunked bundle loads as an ES module (WP 6.5 script modules are not needed for one handle). */
function flxg_flux_module_tag( $tag, $handle ) {
	if ( 'flxg-flux' !== $handle ) {
		return $tag;
	}
	return (string) preg_replace_callback(
		'/<script\b[^>]*\ssrc=[^>]*>/',
		static function ( $m ) {
			$open = (string) preg_replace( '/\stype=("|\')[^"\']*\1/', '', $m[0] );
			return (string) preg_replace( '/^<script\b/', '<script type="module"', $open );
		},
		$tag,
		1
	);
}

function flxg_flux_assets() {
	$ver   = FLXG_VERSION . '-' . (int) filemtime( FLXG_DIR . 'assets/flux/flux-modules.js' );
	$page  = sanitize_key( $_GET['page'] ?? '' ); // phpcs:ignore
	$start = 'flx-tasks-pano' === $page ? 'dashboard' : ( 'flx-tasks-ayarlar' === $page ? 'settings' : '' );
	// Asistan paneli ve model seçici: klasik görünümle aynı Flux AI parçaları.
	$ai_embed = current_user_can( 'flxg_ai' ) && function_exists( 'flxai_chat_embed_enqueue' ) && flxai_chat_embed_enqueue();
	if ( function_exists( 'flxai_enqueue_model_picker' ) && current_user_can( 'manage_options' ) ) {
		flxai_enqueue_model_picker();
	}
	$opts = array(
		'module'     => 'flxg-tasks',
		'rest'       => rest_url(),
		'nonce'      => wp_create_nonce( 'wp_rest' ),
		'locale'     => get_user_locale(),
		'siteName'   => flxg_brand_name(),
		'adminUrl'   => admin_url(),
		'appIcon'    => function_exists( 'flxa_logo_file_url' ) ? flxa_logo_file_url( 'flx-tasks.svg' ) : plugins_url( 'assets/icon.svg', FLXG_FILE ),
		'accent'     => flxg_color( flxg_setting( 'accent' ), function_exists( 'flxa_accent' ) ? flxa_accent() : '#1F2937' ),
		'aiEmbed'    => ! empty( $ai_embed ),
		'startPage'  => $start,
		'classicUrl' => flxg_ui_switch_url( 'classic' ),
	);
	wp_enqueue_style( 'flxg-flux', FLXG_URL . 'assets/flux/flux-modules.css', array(), $ver );
	wp_enqueue_script( 'flxg-flux', FLXG_URL . 'assets/flux/flux-modules.js', array(), $ver, true );
	if ( is_readable( FLXG_DIR . 'assets/flux/chunks.json' ) ) {
		add_filter( 'script_loader_tag', 'flxg_flux_module_tag', 10, 2 );
		wp_add_inline_script(
			'flxg-flux',
			'(function(){var slot=window.FluxModulesOptions||(window.FluxModulesOptions={});slot.id="flxg-app";slot.opts=' . wp_json_encode( $opts ) . ';function go(){if(slot.done||!window.FluxModules)return;var el=document.getElementById(slot.id);if(!el)return;slot.done=true;try{window.FluxModules.mount(el,slot.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})();',
			'before'
		);
		return;
	}
	wp_add_inline_script( 'flxg-flux', '(function(){var el=document.getElementById("flxg-app");if(el&&window.FluxModules){try{window.FluxModules.mount(el,' . wp_json_encode( $opts ) . ');}catch(e){el.textContent=String(e&&e.message||e);}}})();' );
}

add_action( 'admin_enqueue_scripts', 'flxg_admin_assets' );
function flxg_admin_assets() {
	if ( ! flxg_is_app_screen() ) {
		return;
	}
	if ( flxg_flux_ui() ) {
		flxg_flux_assets();
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
	// Asistan paneli: Flux AI'ın ortak sohbeti (dosya ekleme, araç adımları, onay kartları, çalışma alanı).
	$ai_embed = current_user_can( 'flxg_ai' ) && function_exists( 'flxai_chat_embed_enqueue' ) && flxai_chat_embed_enqueue();
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
			// Başlıkta site logosu değil eklentinin kendi simgesi (site logosu sol şeritte zaten var). Flux Admin varsa seçili logo stili.
			'appIcon'  => function_exists( 'flxa_logo_file_url' ) ? flxa_logo_file_url( 'flx-tasks.svg' ) : plugins_url( 'assets/icon.svg', FLXG_FILE ),
			'aiEmbed'  => ! empty( $ai_embed ),
			'fluxUrl'  => flxg_flux_available() ? flxg_ui_switch_url( 'flux' ) : '',
			'i18n'     => flxg_js_strings(),
		)
	);
}

/* Uygulama tam ekran: WP menüsü ve çubuğu üstünde kendi arayüzü. */
function flxg_render_app() {
	if ( flxg_flux_ui() ) {
		// Klasik görünümle aynı tam ekran (app.css yüklenmez; kabuk kuralları burada).
		echo '<style>html.flxg-lock,html.flxg-lock body{overflow:hidden!important;height:100%}html.flxg-lock #wpadminbar,html.flxg-lock #adminmenumain,html.flxg-lock #wpfooter,html.flxg-lock #screen-meta-links,html.flxg-lock .notice,html.flxg-lock .update-nag,html.flxg-lock #wpbody-content>.error,html.flxg-lock #wpbody-content>.updated{display:none!important}html.flxg-lock #wpcontent,html.flxg-lock #wpbody-content{margin:0!important;padding:0!important}#flxg-app{position:fixed;inset:0;z-index:100000;overflow:hidden;background:#f6f7f9}#flxg-app[data-theme=dark]{background:#121417}</style>';
		echo '<script>document.documentElement.classList.add("flxg-lock");</script>';
		echo '<div id="flxg-app"><noscript>' . esc_html__( 'JavaScript is required for Tasks.', 'flx-tasks' ) . '</noscript></div>';
		echo "<script>(function(d,w){var m;try{m=localStorage.getItem('flxg-theme')}catch(e){}m=m||'auto';var k=m==='dark'||(m==='auto'&&!!w.matchMedia&&w.matchMedia('(prefers-color-scheme: dark)').matches);d.getElementById('flxg-app').dataset.theme=k?'dark':'light'})(document,window)</script>";
		return;
	}
	echo '<script>document.documentElement.classList.add("flxg-lock");</script>';
	echo '<div id="flxg-app" class="flxg"><noscript>' . esc_html__( 'JavaScript is required for Tasks.', 'flx-tasks' ) . '</noscript></div>';
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
		echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr__( 'Tasks', 'flx-tasks' ) . '">' . "\n";
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
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=flx-tasks-ayarlar' ) ) . '">' . esc_html__( 'Settings', 'flx-tasks' ) . '</a>' );
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=flx-tasks' ) ) . '">' . esc_html__( 'Tasks', 'flx-tasks' ) . '</a>' );
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
				'meta'  => array( 'title' => __( 'Task notifications', 'flx-tasks' ) ),
			)
		);
	},
	80
);
