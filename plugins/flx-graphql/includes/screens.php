<?php
/**
 * Flux screens in wp-admin: one shell for every React screen (@fluxwp/modules), with in-place navigation.
 *
 * A package registers its admin page once, on admin_menu after add_menu_page / add_submenu_page:
 *
 *     flx_admin_screen(
 *         'flxseo',
 *         array(
 *             'module'      => 'flxseo-seo',                 // flxapp_modules id (packages/modules screens)
 *             'assets_dir'  => FLXSEO_DIR . 'assets/flux/',  // tools/fluxapp/embed-sync.sh output
 *             'assets_url'  => FLXSEO_URL . 'assets/flux/',
 *             'version'     => FLXSEO_VERSION,
 *             'enabled'     => 'flxseo_flux_ui',             // bool or callable: the new view is on for this user
 *             'options'     => 'flxseo_flux_options',        // callable: module mount options (siteName, colors, classicUrl…)
 *             'start_param' => 'tab',                        // query argument that selects the screen's start page
 *             'preload'     => array( array( 'FlxSeoSettingsScreen' ) ), // first queries: [ name, variables ]
 *         )
 *     );
 *
 * More arguments: 'start' (fixed start page), 'layout' ('inline' | 'full': covers wp-admin, always opens as a
 * page load), 'container_id' (id of the screen element when other code styles it), 'theme'
 * ('admin' follows Flux Admin, 'auto', 'light', 'dark'), 'theme_storage' (localStorage key of the screen's
 * own theme switch), 'scripts' (classic scripts the screen needs, loaded before it mounts), 'needs' (window
 * globals the screen needs; a page without them loads normally), 'enqueue' (callable run on the screen's own
 * page load; may return mount options), 'body_class'. The callables get the page slug.
 *
 * The page callback prints the screen with `if ( flx_admin_screen_render() ) { return; }`.
 *
 * What the shell does:
 *  - First load: enqueues the package's bundle and mounts the screen; runs the screen's first queries in
 *    PHP (graphql()) and prints their results into the page, so the screen paints with data, no spinner.
 *  - Navigation: a click on a link to another registered screen (left menu, Flux Admin bottom bar, links
 *    inside screens) does not reload the page. assets/js/screens.js loads that package's bundle (once),
 *    fetches its first queries in one batch request (already on hover), mounts the screen hidden, swaps it
 *    in when it has painted, updates the address, the title and the menu highlight; back / forward work.
 *    Results are shared page-wide (packages/graphql/src/store.ts), so common queries are not sent again.
 *  - Anything else (a page that is not registered, a link with other arguments, a screen whose needs are
 *    missing on the page) loads as a normal page.
 *
 * Filters: `flx_admin_screens_navigation` (bool, default true) turns in-place navigation off.
 * Rules for packages: docs/graphql/EKRAN-KABUGU.md.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registered screens: page slug => arguments.
 *
 * @return array<string,FlxAdminScreen>
 */
function &flx_admin_screens(): array {
	/** @var array<string,FlxAdminScreen> $screens */
	static $screens = array();
	return $screens;
}

/**
 * Registers a wp-admin page as a Flux screen (see the file header).
 *
 * @param string              $page Page slug (the `page` query argument).
 * @param array<string,mixed> $args Arguments.
 */
function flx_admin_screen( string $page, array $args ): void {
	$str = static function ( string $key, string $default = '' ) use ( $args ): string {
		return flx_gql_str( $args[ $key ] ?? $default, $default );
	};
	if ( '' === $page || '' === $str( 'module' ) || '' === $str( 'assets_dir' ) || '' === $str( 'assets_url' ) ) {
		return;
	}
	$screens          = &flx_admin_screens();
	$screens[ $page ] = array(
		'module'        => $str( 'module' ),
		'assets_dir'    => trailingslashit( $str( 'assets_dir' ) ),
		'assets_url'    => trailingslashit( $str( 'assets_url' ) ),
		'version'       => $str( 'version' ),
		'enabled'       => $args['enabled'] ?? true,
		'options'       => $args['options'] ?? null,
		'start'         => $str( 'start' ),
		'start_param'   => $str( 'start_param' ),
		'layout'        => 'full' === $str( 'layout' ) ? 'full' : 'inline',
		'theme'         => $str( 'theme', 'admin' ),
		'theme_storage' => $str( 'theme_storage' ),
		'preload'       => $args['preload'] ?? array(),
		'needs'         => $args['needs'] ?? array(),
		'scripts'       => $args['scripts'] ?? array(),
		'enqueue'       => $args['enqueue'] ?? null,
		'body_class'    => $str( 'body_class' ),
		'container_id'  => $str( 'container_id' ),
	);
}

/**
 * Calls a bool-or-callable argument.
 *
 * @param mixed $value Value.
 * @param mixed ...$args Arguments for a callable.
 * @return mixed
 */
function flx_admin_screen_arg( $value, ...$args ) {
	return is_callable( $value ) ? call_user_func_array( $value, $args ) : $value;
}

/**
 * The page slug of the current request when it is a registered, enabled Flux screen.
 */
function flx_admin_screen_current(): string {
	if ( ! is_admin() || ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return '';
	}
	$page    = sanitize_key( flx_gql_str( wp_unslash( $_GET['page'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$screens = flx_admin_screens();
	return isset( $screens[ $page ] ) && flx_admin_screen_usable( $page ) ? $page : '';
}

/**
 * Whether a registered screen can open for this user (bundle present, GraphQL ready, new view on).
 *
 * @param string $page Page slug.
 */
function flx_admin_screen_usable( string $page ): bool {
	$s = flx_admin_screens()[ $page ] ?? null;
	if ( ! $s || ! flx_gql_ready() ) {
		return false;
	}
	return is_readable( $s['assets_dir'] . 'flux-modules.js' ) && is_readable( $s['assets_dir'] . 'chunks.json' ) && (bool) flx_admin_screen_arg( $s['enabled'] );
}

/**
 * Bundle of a screen: entry and stylesheet URLs, and an id that is the same for byte-identical bundles
 * (two packages built from the same commit share one runtime on the page).
 *
 * @param FlxAdminScreen $s Screen.
 * @return array{entry:string,css:string,id:string,ver:string}
 */
function flx_admin_screen_bundle( array $s ): array {
	$dir   = $s['assets_dir'];
	$js    = $dir . 'flux-modules.js';
	$sig   = (int) @filemtime( $js ) . ':' . (int) @filesize( $js ) . ':' . (int) @filemtime( $dir . 'chunks.json' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$key   = 'flxgql_scr_' . md5( $dir . '|' . $sig );
	$id    = get_transient( $key );
	if ( ! is_string( $id ) || '' === $id ) {
		$id = substr( md5( (string) @md5_file( $js ) . (string) @md5_file( $dir . 'chunks.json' ) ), 0, 12 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		set_transient( $key, $id, WEEK_IN_SECONDS );
	}
	$ver = ( '' !== $s['version'] ? $s['version'] . '-' : '' ) . $id;
	return array(
		'entry' => add_query_arg( 'ver', $ver, $s['assets_url'] . 'flux-modules.js' ),
		'css'   => add_query_arg( 'ver', $ver, $s['assets_url'] . 'flux-modules.css' ),
		'id'    => $id,
		'ver'   => $ver,
	);
}

/**
 * Where a page sits in the admin menu: file (admin.php, users.php…), URL, capability, title, hook suffix.
 *
 * @param string $page Page slug.
 * @return array{file:string,url:string,cap:string,title:string,hook:string}|null
 */
function flx_admin_screen_menu_item( string $page ): ?array {
	global $menu, $submenu;
	$parent = '';
	$item   = null;
	foreach ( is_array( $submenu ) ? $submenu : array() as $p => $items ) {
		foreach ( is_array( $items ) ? $items : array() as $it ) {
			if ( is_array( $it ) && $page === flx_gql_str( $it[2] ?? '' ) ) {
				$parent = flx_gql_str( $p );
				$item   = $it;
				break 2;
			}
		}
	}
	if ( null === $item ) {
		foreach ( is_array( $menu ) ? $menu : array() as $it ) {
			if ( is_array( $it ) && $page === flx_gql_str( $it[2] ?? '' ) ) {
				$item = $it;
				break;
			}
		}
	}
	if ( null === $item ) {
		return null;
	}
	$url  = menu_page_url( $page, false );
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$file = basename( $path );
	return array(
		'file'  => '' !== $file ? $file : 'admin.php',
		'url'   => $url,
		'cap'   => flx_gql_str( $item[1] ?? '', 'manage_options' ),
		'title' => wp_strip_all_tags( flx_gql_str( $item[3] ?? ( $item[0] ?? '' ) ) ),
		'hook'  => (string) get_plugin_page_hookname( $page, $parent ),
	);
}

/**
 * Operation name → persisted id (queries only), from the registered manifests.
 *
 * @return array<string,string>
 */
function flx_admin_screen_operation_ids(): array {
	/** @var array<string,string>|null $ids */
	static $ids = null;
	if ( null !== $ids ) {
		return $ids;
	}
	$ids = array();
	foreach ( flx_gql_persisted()['queries'] as $id => $doc ) {
		if ( preg_match( '/^\s*query\s+([A-Za-z_][A-Za-z0-9_]*)/', $doc, $m ) ) {
			$ids[ $m[1] ] = $id;
		}
	}
	return $ids;
}

/**
 * Variables as the client keys them (packages/graphql/src/store.ts stableVariables): object keys sorted,
 * empty = {}.
 *
 * @param mixed $vars Variables.
 */
function flx_admin_screen_stable_vars( $vars ): string {
	if ( ! is_array( $vars ) || array() === $vars ) {
		return '{}';
	}
	$sort = static function ( $v ) use ( &$sort ) {
		if ( ! is_array( $v ) ) {
			return $v;
		}
		if ( array_is_list( $v ) ) {
			return array_map( $sort, $v );
		}
		ksort( $v, SORT_STRING );
		return array_map( $sort, $v );
	};
	$out = wp_json_encode( $sort( $vars ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return is_string( $out ) ? $out : '{}';
}

/**
 * A screen's first queries: [ [ key, id, name, variables ] ].
 *
 * @param FlxAdminScreen       $s     Screen.
 * @param array<string,string> $query Query arguments of the page.
 * @return list<FlxAdminScreenPreload>
 */
function flx_admin_screen_preload_items( array $s, array $query ): array {
	$list = flx_admin_screen_arg( $s['preload'], $query );
	$ids  = flx_admin_screen_operation_ids();
	$out  = array();
	foreach ( is_array( $list ) ? $list : array() as $p ) {
		$p    = is_array( $p ) ? array_values( $p ) : array( $p );
		$name = flx_gql_str( $p[0] ?? '' );
		if ( ! isset( $ids[ $name ] ) ) {
			continue;
		}
		$vars  = flx_gql_arr( $p[1] ?? null );
		$vars  = array() !== $vars ? $vars : null;
		$out[] = array(
			'key'  => $ids[ $name ] . ':' . flx_admin_screen_stable_vars( $vars ),
			'id'   => $ids[ $name ],
			'name' => $name,
			'vars' => $vars,
		);
	}
	return $out;
}

/**
 * Mount options of a screen (the bundle's FluxModules.mount options).
 *
 * @param string              $page  Page slug.
 * @param FlxAdminScreen      $s     Screen.
 * @param array<string,mixed> $extra Options that override (the current page's enqueue result).
 * @return array<string,mixed>
 */
function flx_admin_screen_options( string $page, array $s, array $extra = array() ): array {
	$o = array_merge(
		array(
			'rest'     => rest_url(),
			'nonce'    => flx_admin_screen_nonce(),
			'locale'   => get_user_locale(),
			'siteName' => get_bloginfo( 'name' ),
			'adminUrl' => admin_url(),
			'theme'    => $s['theme'],
		),
		flx_gql_arr( flx_admin_screen_arg( $s['options'], $page ) ),
		$extra
	);
	$o['module'] = $s['module'];
	return $o;
}

/**
 * Classic scripts a screen needs before it mounts (URLs with their version).
 *
 * @param FlxAdminScreen $s    Screen.
 * @param string         $page Page slug.
 * @return list<string>
 */
function flx_admin_screen_scripts( array $s, string $page ): array {
	return flx_admin_screen_strings( flx_admin_screen_arg( $s['scripts'], $page ) );
}

/**
 * A list of non-empty strings from a mixed value.
 *
 * @param mixed $v Value.
 * @return list<string>
 */
function flx_admin_screen_strings( $v ): array {
	$out = array();
	foreach ( is_array( $v ) ? $v : array() as $x ) {
		$x = flx_gql_str( $x );
		if ( '' !== $x ) {
			$out[] = $x;
		}
	}
	return $out;
}

/** One REST nonce per page. */
function flx_admin_screen_nonce(): string {
	/** @var string|null $nonce */
	static $nonce = null;
	$nonce ??= wp_create_nonce( 'wp_rest' );
	return $nonce;
}

/**
 * Query arguments of this request (strings only).
 *
 * @return array<string,string>
 */
function flx_admin_screen_query(): array {
	$q = array();
	foreach ( wp_unslash( $_GET ) as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification
		if ( is_string( $k ) && is_string( $v ) ) {
			$q[ sanitize_key( $k ) ] = sanitize_text_field( $v );
		}
	}
	return $q;
}

/**
 * Start page of a screen for a set of query arguments.
 *
 * @param FlxAdminScreen       $s     Screen.
 * @param array<string,string> $query Query arguments.
 */
function flx_admin_screen_start( array $s, array $query ): string {
	$p = $s['start_param'];
	if ( '' !== $p && isset( $query[ $p ] ) && '' !== $query[ $p ] ) {
		return sanitize_key( $query[ $p ] );
	}
	return $s['start'];
}

/**
 * Options the current page's enqueue callback returned (e.g. aiEmbed).
 *
 * @return array<string,mixed>
 */
function &flx_admin_screen_extra(): array {
	/** @var array<string,mixed> $extra */
	static $extra = array();
	return $extra;
}

add_action(
	'admin_enqueue_scripts',
	static function () {
		$page = flx_admin_screen_current();
		if ( '' === $page ) {
			return;
		}
		$s      = flx_admin_screens()[ $page ];
		$b      = flx_admin_screen_bundle( $s );
		$handle = 'flx-screen-' . $b['id'];
		$extra  = &flx_admin_screen_extra();
		$more   = flx_admin_screen_arg( $s['enqueue'], $page );
		if ( is_array( $more ) ) {
			$extra = flx_gql_arr( $more );
		}
		wp_enqueue_style( $handle, $s['assets_url'] . 'flux-modules.css', array(), $b['ver'] );
		wp_enqueue_script( $handle, $s['assets_url'] . 'flux-modules.js', array(), $b['ver'], true );
		foreach ( flx_admin_screen_scripts( $s, $page ) as $i => $src ) {
			wp_enqueue_script( $handle . '-s' . $i, $src, array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the URL carries its version.
		}
		wp_enqueue_script( 'flx-screens', FLXGQL_URL . 'assets/js/screens.js', array(), FLXGQL_VERSION, true );
		wp_enqueue_style( 'flx-screens', FLXGQL_URL . 'assets/css/screens.css', array(), FLXGQL_VERSION );
		add_filter(
			'script_loader_tag',
			static function ( $tag, $h ) use ( $handle ) {
				$tag = flx_gql_str( $tag );
				if ( $h !== $handle ) {
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
			},
			10,
			2
		);
	}
);

add_filter(
	'admin_body_class',
	static function ( $classes ) {
		$classes = flx_gql_str( $classes );
		$page    = flx_admin_screen_current();
		if ( '' === $page ) {
			return $classes;
		}
		$s = flx_admin_screens()[ $page ];
		return $classes . ' flx-screen-page' . ( '' !== $s['body_class'] ? ' ' . $s['body_class'] : '' );
	}
);

/**
 * Prints the current screen: container, preloaded results, mount options and the navigator's routes.
 * Call it from the page callback; false when the page is not a usable Flux screen (print the classic page).
 */
function flx_admin_screen_render(): bool {
	$page = flx_admin_screen_current();
	if ( '' === $page ) {
		return false;
	}
	$s     = flx_admin_screens()[ $page ];
	$query = flx_admin_screen_query();
	$opts  = flx_admin_screen_options( $page, $s, flx_admin_screen_extra() );
	$opts['startPage'] = flx_admin_screen_start( $s, $query );
	$full  = 'full' === $s['layout'];
	$id    = '' !== $s['container_id'] ? sanitize_html_class( $s['container_id'] ) : 'flx-screen-' . sanitize_html_class( $page );

	if ( $full ) {
		echo '<script>document.documentElement.classList.add("flx-screen-full");</script>';
	}
	flx_admin_screen_print_preload( $s, $query );
	echo $full ? '' : '<div class="wrap flx-screen-wrap">';
	echo '<div id="' . esc_attr( $id ) . '" class="flx-screen" data-layout="' . esc_attr( $full ? 'full' : 'inline' ) . '" tabindex="-1"><noscript>' . esc_html__( 'This screen needs JavaScript.', 'flx-graphql' ) . '</noscript></div>';
	echo $full ? '' : '</div>';
	// Paint in the right theme before the bundle runs (no light flash on a dark page).
	echo '<script>(function(d){var e=d.getElementById(' . wp_json_encode( $id ) . '),t=' . wp_json_encode( flx_gql_str( $opts['theme'] ?? 'admin' ) ) . ',k=' . wp_json_encode( $s['theme_storage'] ) . ',m;if(!e)return;if(k){try{m=localStorage.getItem(k)}catch(x){}t=m||"auto"}if(t==="admin")t=d.documentElement.classList.contains("flxa-dark")?"dark":"light";if(t==="auto")t=window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";e.dataset.theme=t})(document);</script>';
	echo '<script>(function(w){var s=w.FluxModulesOptions||(w.FluxModulesOptions={});s.id=' . wp_json_encode( $id ) . ';s.opts=' . wp_json_encode( $opts ) . ';function go(){if(s.done||!w.FluxModules)return;var el=document.getElementById(s.id);if(!el)return;s.done=true;try{w.FluxModules.mount(el,s.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})(window);</script>';
	if ( ! $full ) {
		flx_admin_screen_print_routes( $page, $id );
	}
	return true;
}

/**
 * Runs the screen's first queries here and prints their results (store.ts reads them).
 *
 * @param FlxAdminScreen       $s     Screen.
 * @param array<string,string> $query Query arguments.
 */
function flx_admin_screen_print_preload( array $s, array $query ): void {
	if ( ! function_exists( 'graphql' ) ) {
		return;
	}
	$docs    = flx_gql_persisted()['queries'];
	$entries = array();
	foreach ( flx_admin_screen_preload_items( $s, $query ) as $p ) {
		try {
			$res = graphql(
				array(
					'query'          => $docs[ $p['id'] ],
					'variables'      => $p['vars'] ?? array(),
					'operation_name' => $p['name'],
				)
			);
		} catch ( \Throwable $e ) {
			continue;
		}
		// Only clean results: a partial answer is left to the client, which reports the error as usual.
		if ( is_array( $res ) && empty( $res['errors'] ) && isset( $res['data'] ) && is_array( $res['data'] ) ) {
			$entries[ $p['key'] ] = $res['data'];
		}
	}
	if ( ! $entries ) {
		return;
	}
	$json = wp_json_encode(
		array(
			'at'      => (int) floor( microtime( true ) * 1000 ),
			'entries' => $entries,
		),
		JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);
	if ( is_string( $json ) ) {
		echo '<script type="application/json" id="flx-gql-preload">' . $json . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON, < and & hex-escaped.
	}
}

/**
 * The navigator's configuration: every registered screen this user can open, keyed by page slug.
 *
 * @param string $current Current page slug.
 * @param string $el      Id of the current container.
 */
function flx_admin_screen_print_routes( string $current, string $el ): void {
	$routes = array();
	foreach ( flx_admin_screens() as $page => $s ) {
		$m = flx_admin_screen_menu_item( $page );
		// Full-layout screens (Tasks…) share wp-admin's chrome with Flux Admin's app mode: they load as pages.
		if ( 'full' === $s['layout'] || ! $m || ! current_user_can( $m['cap'] ) || ! flx_admin_screen_usable( $page ) ) {
			continue;
		}
		$b                = flx_admin_screen_bundle( $s );
		$routes[ $page ] = array(
			'file'       => $m['file'],
			'module'     => $s['module'],
			'bundle'     => array(
				'entry' => $b['entry'],
				'css'   => $b['css'],
				'id'    => $b['id'],
			),
			'opts'       => flx_admin_screen_options( $page, $s, $page === $current ? flx_admin_screen_extra() : array() ),
			'start'      => $s['start'],
			'startParam' => $s['start_param'],
			'themeStorage' => $s['theme_storage'],
			'preload'    => flx_admin_screen_preload_items( $s, array() ),
			'needs'      => flx_admin_screen_strings( flx_admin_screen_arg( $s['needs'], $page ) ),
			'scripts'    => flx_admin_screen_scripts( $s, $page ),
			/* translators: Admin screen title. 1: Admin screen name, 2: Site title. */
			'title'      => html_entity_decode( sprintf( __( '%1$s &lsaquo; %2$s &#8212; WordPress' ), $m['title'], get_bloginfo( 'name' ) ), ENT_QUOTES, 'UTF-8' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- WordPress's own admin title.
			'hook'       => $m['hook'],
			'bodyClass'  => $s['body_class'],
		);
		/**
		 * One route of the navigator. Packages that decorate a screen's page (Flux Admin's package head)
		 * add what they need here and redo it on the `flx-screens:navigate` DOM event (detail: page, route).
		 *
		 * @param array<string,mixed> $route Route.
		 * @param string              $page  Page slug.
		 */
		$routes[ $page ] = flx_gql_arr( apply_filters( 'flx_admin_screen_route', $routes[ $page ], $page ) );
	}
	/**
	 * In-place navigation between Flux screens (false: every link loads the page).
	 *
	 * @param bool $on On.
	 */
	$on  = (bool) apply_filters( 'flx_admin_screens_navigation', true );
	$cfg = array(
		'on'      => $on,
		'current' => $current,
		'el'      => $el,
		'admin'   => admin_url(),
		'graphql' => function_exists( 'graphql_get_endpoint_url' ) ? (string) graphql_get_endpoint_url() : trailingslashit( home_url() ) . 'graphql',
		'nonce'   => flx_admin_screen_nonce(),
		'routes'  => (object) $routes,
		'i18n'    => array(
			'loading' => __( 'Loading the screen…', 'flx-graphql' ),
		),
	);
	echo '<script>window.FlxScreensConfig=' . wp_json_encode( $cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ';</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
