<?php
defined( 'ABSPATH' ) || exit;

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
 * The Tasks pages are one React screen (assets/flux/, tools/fluxapp/embed-sync.sh flx-tasks) opened through Flux
 * GraphQL's screen shell. There is no classic view; the plugin needs Flux GraphQL.
 */
function flxg_flux_available() {
	return file_exists( FLXG_DIR . 'assets/flux/flux-modules.js' ) && function_exists( 'flx_gql_ready' ) && function_exists( 'flx_admin_screen' ) && flx_gql_ready();
}

/** Mount options of the React screen (Flux GraphQL's screen shell adds rest, nonce, locale, admin URL). */
function flxg_flux_options() {
	return array(
		'siteName'   => flxg_brand_name(),
		'appIcon'    => function_exists( 'flxa_logo_file_url' ) ? flxa_logo_file_url( 'flx-tasks.svg' ) : plugins_url( 'assets/icon.svg', FLXG_FILE ),
		'accent'     => flxg_color( flxg_setting( 'accent' ), function_exists( 'flxa_accent' ) ? flxa_accent() : '#1F2937' ),
		'aiEmbed'    => flxg_flux_ai_possible(),
	);
}

/** The assistant panel can open (Flux AI's shared chat). */
function flxg_flux_ai_possible() {
	return current_user_can( 'flxg_ai' ) && function_exists( 'flxai_chat_embed_enqueue' );
}

/** First load: the assistant panel (Flux AI's shared chat) and the model picker. */
function flxg_flux_enqueue() {
	$ai_embed = flxg_flux_ai_possible() && flxai_chat_embed_enqueue();
	if ( function_exists( 'flxai_enqueue_model_picker' ) && current_user_can( 'manage_options' ) ) {
		flxai_enqueue_model_picker();
	}
	return array( 'aiEmbed' => ! empty( $ai_embed ) );
}

/*
 * The three Tasks pages open through Flux GraphQL's screen shell (includes/screens.php there): full layout over
 * wp-admin, first queries preloaded, and other Flux screens open in place from links.
 */
add_action(
	'admin_menu',
	static function () {
		if ( ! function_exists( 'flx_admin_screen' ) ) {
			return;
		}
		$base = array(
			'module'        => 'flxg-tasks',
			'assets_dir'    => FLXG_DIR . 'assets/flux/',
			'assets_url'    => FLXG_URL . 'assets/flux/',
			'version'       => FLXG_VERSION,
			'options'       => 'flxg_flux_options',
			'enqueue'       => 'flxg_flux_enqueue',
			'layout'        => 'full',
			'container_id'  => 'flxg-app', // Flux Admin's app mode places this element next to its rail.
			'theme'         => 'auto',
			'theme_storage' => 'flxg-theme',
			'body_class'    => 'flxg-page',
			// The assistant panel needs Flux AI's chat on the page; without it the page loads normally.
			'needs'         => static function () {
				return flxg_flux_ai_possible() ? array( 'FlxAIChat' ) : array();
			},
		);
		flx_admin_screen( 'flx-tasks', $base );
		flx_admin_screen( 'flx-tasks-pano', array( 'start' => 'dashboard' ) + $base );
		flx_admin_screen( 'flx-tasks-ayarlar', array( 'start' => 'settings' ) + $base );
	},
	20
);

/* The page body: the screen shell prints the React screen; without Flux GraphQL there is nothing to show. */
function flxg_render_app() {
	if ( flxg_flux_available() && flx_admin_screen_render() ) {
		flxg_print_preload();
		return;
	}
	echo '<div class="wrap"><h1>' . esc_html__( 'Tasks', 'flx-tasks' ) . '</h1><div class="notice notice-error"><p>' . esc_html__( 'Flux Tasks needs the Flux GraphQL plugin. Install and activate it to open this screen.', 'flx-tasks' ) . '</p></div></div>';
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

/*
 * First open without waiting on the network: the data the screen asks for first (boot, and the project or list
 * the user had open last) is printed into the page, so the screen draws in full on its first render. Without
 * it the screen sent /boot and then the scope's /lists + /tasks one after another, each a full WordPress
 * start on the server (1–2 s apiece on shared hosting). The screen reads #flxg-pre once (TasksScreen.tsx).
 */
function flxg_print_preload() {
	$start = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'flx-tasks'; // phpcs:ignore WordPress.Security.NonceVerification
	$pre   = array( 'boot' => flxg_rest_boot() );
	if ( 'flx-tasks' === $start ) {
		$scope = flxg_preload_scope( flxg_last_scope() );
		if ( $scope ) {
			$pre['scope'] = $scope;
		}
	}
	$json = wp_json_encode( $pre, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( is_string( $json ) ) {
		echo '<script type="application/json" id="flxg-pre">' . $json . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON, < and & hex-escaped.
	}
}

/**
 * The same answers the screen would get from /lists/{id} (or /spaces/{id}, /home) and /tasks for a scope.
 *
 * @param string $scope 'list:5', 'space:2' or 'home'.
 * @return array|null { type, id, meta, tasks } or null when the scope is gone or not visible.
 */
function flxg_preload_scope( $scope ) {
	list( $type, $id ) = array_pad( explode( ':', $scope ), 2, 0 );
	$id                = (int) $id;
	$q                 = new WP_REST_Request( 'GET' );
	if ( 'list' === $type ) {
		$q->set_url_params( array( 'id' => $id ) );
		$meta = flxg_rest_list_get( $q );
		$t    = array( 'list' => $id );
	} elseif ( 'space' === $type ) {
		$q->set_url_params( array( 'id' => $id ) );
		$meta = flxg_rest_space_get( $q );
		$t    = array( 'space' => $id );
	} else {
		$type = 'home';
		$id   = 0;
		$meta = flxg_rest_home();
		$t    = array( 'mine' => 1 );
	}
	if ( is_wp_error( $meta ) ) {
		return 'home' === $type ? null : flxg_preload_scope( 'home' );
	}
	$tq = new WP_REST_Request( 'GET' );
	$tq->set_query_params( $t );
	$tasks = flxg_rest_tasks( $tq );
	if ( is_wp_error( $tasks ) ) {
		return null;
	}
	return array(
		'type'  => $type,
		'id'    => $id,
		'meta'  => $meta,
		'tasks' => $tasks,
	);
}
