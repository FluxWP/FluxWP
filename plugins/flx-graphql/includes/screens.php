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
 *             'options'     => 'flxseo_flux_options',        // callable: module mount options (siteName, colors…)
 *             'start_param' => 'tab',                        // query argument that selects the screen's start page
 *             'preload'     => array( array( 'FlxSeoSettingsScreen' ) ), // first queries: [ name, variables ]
 *         )
 *     );
 *
 * More arguments: 'wrap_class' (class of the .wrap around an inline screen, for the package's own CSS),
 * 'start' (fixed start page), 'layout' ('inline' | 'full': covers wp-admin, always opens as a
 * page load), 'container_id' (id of the screen element when other code styles it), 'theme'
 * ('admin' follows Flux Admin, 'auto', 'light', 'dark'), 'theme_storage' (localStorage key of the screen's
 * own theme switch), 'scripts' (classic scripts the screen needs, loaded before it mounts), 'needs' (window
 * globals the screen needs; a page without them loads normally), 'enqueue' (callable run on the screen's own
 * page load; may return mount options), 'body_class'. The callables get the page slug.
 *
 * The page callback prints the screen with `flx_admin_screen_page()`. There is no classic page behind it: when
 * the screen cannot open (GraphQL not ready, bundle missing) or its code fails to load, the page says so and
 * offers a reload instead of staying blank.
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
		'wrap_class'    => $str( 'wrap_class' ),
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
	if ( ! is_readable( $s['assets_dir'] . 'chunks.json' ) ) {
		return false;
	}
	$code = flx_admin_screen_is_plugin_bundle( $s )
		? null !== flx_admin_screen_runtime()
		: is_readable( $s['assets_dir'] . 'flux-modules.js' );
	return $code && (bool) flx_admin_screen_arg( $s['enabled'] );
}

/**
 * Whether the screen's assets folder holds a plugin screens bundle (@fluxwp/plugin-kit: flux-screens.js on
 * the shared runtime) instead of a FluxWP modules bundle (flux-modules.js, tools/fluxapp/embed-sync.sh).
 *
 * @param FlxAdminScreen $s Screen.
 */
function flx_admin_screen_is_plugin_bundle( array $s ): bool {
	return is_readable( $s['assets_dir'] . 'flux-screens.js' );
}

/**
 * The shared runtime plugin screens run on (assets/runtime/, apps/wp-embed FLUX_EMBED_RUNTIME=1): React,
 * React Query, i18next and the open @fluxwp packages, loaded once per page. Null when it is not built.
 *
 * @return array{entry:string,css:string,id:string,ver:string}|null
 */
function flx_admin_screen_runtime(): ?array {
	$dir = FLXGQL_DIR . 'assets/runtime/';
	if ( ! is_readable( $dir . 'flux-runtime.js' ) ) {
		return null;
	}
	$ver = FLXGQL_VERSION . '-' . (int) @filemtime( $dir . 'flux-runtime.js' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	return array(
		'entry' => add_query_arg( 'ver', $ver, FLXGQL_URL . 'assets/runtime/flux-runtime.js' ),
		'css'   => add_query_arg( 'ver', $ver, FLXGQL_URL . 'assets/runtime/flux-runtime.css' ),
		'id'    => 'flx-runtime',
		'ver'   => $ver,
	);
}

/**
 * Bundle of a screen: entry and stylesheet URLs, and an id that is the same for byte-identical bundles
 * (two packages built from the same commit share one runtime on the page).
 *
 * @param FlxAdminScreen $s Screen.
 * @return array{entry:string,css:string,id:string,ver:string,screens?:string,screensCss?:string,screensId?:string}
 */
function flx_admin_screen_bundle( array $s ): array {
	$dir   = $s['assets_dir'];
	$js    = $dir . 'flux-modules.js';
	if ( flx_admin_screen_is_plugin_bundle( $s ) ) {
		$js = $dir . 'flux-screens.js';
	}
	$sig   = (int) @filemtime( $js ) . ':' . (int) @filesize( $js ) . ':' . (int) @filemtime( $dir . 'chunks.json' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$key   = 'flxgql_scr_' . md5( $dir . '|' . $sig );
	$id    = get_transient( $key );
	if ( ! is_string( $id ) || '' === $id ) {
		$id = substr( md5( (string) @md5_file( $js ) . (string) @md5_file( $dir . 'chunks.json' ) ), 0, 12 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		set_transient( $key, $id, WEEK_IN_SECONDS );
	}
	$ver = ( '' !== $s['version'] ? $s['version'] . '-' : '' ) . $id;
	$rt  = flx_admin_screen_is_plugin_bundle( $s ) ? flx_admin_screen_runtime() : null;
	if ( $rt ) {
		// Plugin screens: the runtime is the shared bundle (one id for every plugin); the plugin's own entry
		// registers its screens with it.
		return array(
			'entry'      => $rt['entry'],
			'css'        => $rt['css'],
			'id'         => $rt['id'],
			'ver'        => $ver,
			'screens'    => add_query_arg( 'ver', $ver, $s['assets_url'] . 'flux-screens.js' ),
			'screensCss' => is_readable( $dir . 'flux-screens.css' ) ? add_query_arg( 'ver', $ver, $s['assets_url'] . 'flux-screens.css' ) : '',
			'screensId'  => 'p' . $id,
		);
	}
	return array(
		'entry' => add_query_arg( 'ver', $ver, $s['assets_url'] . 'flux-modules.js' ),
		'css'   => add_query_arg( 'ver', $ver, $s['assets_url'] . 'flux-modules.css' ),
		'id'    => $id,
		'ver'   => $ver,
	);
}

/**
 * Chunks a screen's code needs besides the entry (chunks.json "preload", tools/fluxapp/embed-preload.mjs):
 * printed as <link rel="modulepreload"> so they download together with the entry, not one after another.
 *
 * @param FlxAdminScreen $s Screen.
 * @return list<string> File names in the bundle folder.
 */
function flx_admin_screen_chunks( array $s ): array {
	$raw = @file_get_contents( $s['assets_dir'] . 'chunks.json' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	$cfg = is_string( $raw ) ? json_decode( $raw, true ) : null;
	$pre = is_array( $cfg ) && isset( $cfg['preload'] ) && is_array( $cfg['preload'] ) ? $cfg['preload'] : array();
	$scr = isset( $pre['screens'] ) && is_array( $pre['screens'] ) ? $pre['screens'] : array();
	$out = array_merge( flx_admin_screen_strings( $pre['entry'] ?? null ), flx_admin_screen_strings( $scr[ $s['module'] ] ?? null ) );
	return array_values( array_filter( array_unique( $out ), static fn ( $f ) => (bool) preg_match( '/^[\w.-]+\.js$/', $f ) ) );
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
		$key   = $ids[ $name ] . ':' . flx_admin_screen_stable_vars( $vars );
		$out[ $key ] = array(
			'key'  => $key,
			'id'   => $ids[ $name ],
			'name' => $name,
			'vars' => $vars,
		);
	}
	// What this user's screen asked for first last time (learned in the browser, see flx_admin_screen_learn).
	foreach ( flx_admin_screen_learned( flx_admin_screen_learn_key( $s, $query ) ) as $item ) {
		$out[ $item['key'] ] ??= $item;
	}
	return array_slice( array_values( $out ), 0, FLX_ADMIN_SCREEN_LEARN_MAX );
}

/** Queries kept per screen and start page. */
const FLX_ADMIN_SCREEN_LEARN_MAX = 10;

/** User meta: [ "<module>|<start>" => [ "<persisted id>:<variables>", … ] ]. */
const FLX_ADMIN_SCREEN_LEARN_META = 'flx_gql_screen_queries';

/**
 * Key of a screen's learned queries: module and start page.
 *
 * @param FlxAdminScreen       $s     Screen.
 * @param array<string,string> $query Query arguments of the page.
 */
function flx_admin_screen_learn_key( array $s, array $query ): string {
	return $s['module'] . '|' . flx_admin_screen_start( $s, $query );
}

/**
 * A store key ("<persisted id>:<variables JSON>") as a preload item; null when the id is not a known query.
 *
 * @param string $key Store key.
 * @return FlxAdminScreenPreload|null
 */
function flx_admin_screen_item_from_key( string $key ): ?array {
	/** @var array<string,string>|null $names */
	static $names = null;
	$names ??= array_flip( flx_admin_screen_operation_ids() );
	$at = strpos( $key, ':' );
	if ( false === $at || strlen( $key ) > 2100 ) {
		return null;
	}
	$id = substr( $key, 0, $at );
	if ( ! isset( $names[ $id ] ) ) {
		return null;
	}
	$raw  = substr( $key, $at + 1 );
	$vars = json_decode( $raw, true );
	if ( ! is_array( $vars ) ) {
		return null;
	}
	$vars = flx_gql_arr( $vars );
	return array(
		'key'  => $id . ':' . flx_admin_screen_stable_vars( $vars ),
		'id'   => $id,
		'name' => $names[ $id ],
		'vars' => array() !== $vars ? $vars : null,
	);
}

/**
 * The current user's learned first queries of a screen.
 *
 * @param string $key Learn key (flx_admin_screen_learn_key).
 * @return list<FlxAdminScreenPreload>
 */
function flx_admin_screen_learned( string $key ): array {
	$all = get_user_meta( get_current_user_id(), FLX_ADMIN_SCREEN_LEARN_META, true );
	$out = array();
	foreach ( is_array( $all ) && isset( $all[ $key ] ) && is_array( $all[ $key ] ) ? $all[ $key ] : array() as $k ) {
		$item = flx_admin_screen_item_from_key( flx_gql_str( $k ) );
		if ( $item ) {
			$out[] = $item;
		}
	}
	return $out;
}

/**
 * Keeps the first queries a screen sent for this user (admin-ajax `flx_screen_learn`, sent by the bundle a
 * moment after the screen mounted, only when they differ from what was preloaded). Per user: what one user
 * sends only changes that user's own preload, and every query runs with that user's rights anyway.
 *
 * @param string       $key  Learn key.
 * @param list<string> $keys Store keys.
 */
function flx_admin_screen_learn( string $key, array $keys ): bool {
	if ( ! preg_match( '/^[a-z0-9_-]{1,64}\|[a-z0-9_-]{0,64}$/', $key ) ) {
		return false;
	}
	$clean = array();
	foreach ( $keys as $k ) {
		$item = flx_admin_screen_item_from_key( $k );
		if ( $item && ! in_array( $item['key'], $clean, true ) ) {
			$clean[] = $item['key'];
		}
	}
	$clean = array_slice( $clean, 0, FLX_ADMIN_SCREEN_LEARN_MAX );
	$user  = get_current_user_id();
	$all   = get_user_meta( $user, FLX_ADMIN_SCREEN_LEARN_META, true );
	$all   = is_array( $all ) ? $all : array();
	unset( $all[ $key ] );
	if ( $clean ) {
		$all[ $key ] = $clean;
	}
	$all = array_slice( $all, -60, null, true ); // The most recently learned screens.
	return (bool) update_user_meta( $user, FLX_ADMIN_SCREEN_LEARN_META, $all );
}

add_action(
	'wp_ajax_flx_screen_learn',
	static function () {
		if ( ! check_ajax_referer( 'wp_rest', '_wpnonce', false ) ) {
			wp_send_json_error( null, 403 );
		}
		$keys = isset( $_POST['keys'] ) && is_array( $_POST['keys'] ) ? array_map( 'flx_gql_str', wp_unslash( $_POST['keys'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each key is parsed and validated (known persisted query id, JSON variables).
		flx_admin_screen_learn( sanitize_text_field( flx_gql_str( wp_unslash( $_POST['key'] ?? '' ) ) ), array_values( $keys ) );
		wp_send_json_success();
	}
);

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
	// Classic page head logo (Flux Admin package logo, accent tile): the React header shows the same one.
	if ( function_exists( 'flxa_page_head_info' ) && function_exists( 'flxa_enabled' ) && flxa_enabled() ) {
		$head = flx_gql_arr( flxa_page_head_info( $page ) );
		if ( ! empty( $head['logo'] ) ) {
			$o['appIcon'] = flx_gql_str( $head['logo'] );
		}
		if ( ! empty( $head['desc'] ) ) {
			$o['appDesc'] = flx_gql_str( $head['desc'] );
		}
	}
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
		$modules = array( $handle );
		if ( isset( $b['screens'] ) ) {
			// Shared runtime first (module scripts run in document order), then the plugin's screens.
			wp_enqueue_style( $handle, $b['css'], array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the URL carries its version.
			wp_enqueue_script( $handle, $b['entry'], array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			if ( '' !== ( $b['screensCss'] ?? '' ) ) {
				wp_enqueue_style( $handle . '-p', $b['screensCss'], array( $handle ), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			}
			wp_enqueue_script( $handle . '-p', $b['screens'], array( $handle ), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			$modules[] = $handle . '-p';
		} else {
			wp_enqueue_style( $handle, $s['assets_url'] . 'flux-modules.css', array(), $b['ver'] );
			wp_enqueue_script( $handle, $s['assets_url'] . 'flux-modules.js', array(), $b['ver'], true );
		}
		foreach ( flx_admin_screen_scripts( $s, $page ) as $i => $src ) {
			wp_enqueue_script( $handle . '-s' . $i, $src, array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the URL carries its version.
		}
		wp_enqueue_script( 'flx-screens', FLXGQL_URL . 'assets/js/screens.js', array(), FLXGQL_VERSION, true );
		// The entry and the screen's chunks start downloading with the page head, all at once.
		$chunks = flx_admin_screen_chunks( $s );
		add_action(
			'admin_head',
			static function () use ( $s, $b, $chunks ) {
				echo '<link rel="modulepreload" href="' . esc_url( $b['entry'] ) . '">' . "\n";
				foreach ( $chunks as $f ) {
					echo '<link rel="modulepreload" href="' . esc_url( $s['assets_url'] . $f ) . '">' . "\n";
				}
			},
			1
		);
		wp_enqueue_style( 'flx-screens', FLXGQL_URL . 'assets/css/screens.css', array(), FLXGQL_VERSION );
		add_filter(
			'script_loader_tag',
			static function ( $tag, $h ) use ( $modules ) {
				$tag = flx_gql_str( $tag );
				if ( ! in_array( $h, $modules, true ) ) {
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
 * Call it from the page callback; false when the page is not a usable Flux screen (flx_admin_screen_page()
 * prints the "could not be loaded" notice then).
 */
function flx_admin_screen_render(): bool {
	$page = flx_admin_screen_current();
	if ( '' === $page ) {
		return false;
	}
	$s     = flx_admin_screens()[ $page ];
	$query = flx_admin_screen_query();
	$opts  = flx_admin_screen_options( $page, $s, flx_admin_screen_extra() );
	// A package may pick a deeper start page itself (e.g. one record: ?view=…); else start / start_param.
	$opts['startPage'] = '' !== flx_gql_str( $opts['startPage'] ?? '' ) ? flx_gql_str( $opts['startPage'] ) : flx_admin_screen_start( $s, $query );
	$opts['learn']     = array(
		'url'   => admin_url( 'admin-ajax.php' ),
		'key'   => flx_admin_screen_learn_key( $s, $query ),
		'known' => array_column( flx_admin_screen_preload_items( $s, $query ), 'key' ),
	);
	$full  = 'full' === $s['layout'];
	$id    = '' !== $s['container_id'] ? sanitize_html_class( $s['container_id'] ) : 'flx-screen-' . sanitize_html_class( $page );

	if ( $full ) {
		echo '<script>document.documentElement.classList.add("flx-screen-full");</script>';
	}
	flx_admin_screen_print_preload( $s, $query );
	echo $full ? '' : '<div class="wrap flx-screen-wrap' . ( '' !== $s['wrap_class'] ? ' ' . esc_attr( $s['wrap_class'] ) : '' ) . '">';
	echo '<div id="' . esc_attr( $id ) . '" class="flx-screen" data-layout="' . esc_attr( $full ? 'full' : 'inline' ) . '" tabindex="-1"><noscript>' . esc_html__( 'This screen needs JavaScript.', 'flx-graphql' ) . '</noscript></div>';
	flx_admin_screen_failsafe( $id );
	echo $full ? '' : '</div>';
	// Paint in the right theme before the bundle runs (no light flash on a dark page).
	echo '<script>(function(d){var e=d.getElementById(' . wp_json_encode( $id ) . '),t=' . wp_json_encode( flx_gql_str( $opts['theme'] ?? 'admin' ) ) . ',k=' . wp_json_encode( $s['theme_storage'] ) . ',m;if(!e)return;if(k){try{m=localStorage.getItem(k)}catch(x){}t=m||"auto"}if(t==="admin")t=d.documentElement.classList.contains("flxa-dark")?"dark":"light";if(t==="auto")t=window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";e.dataset.theme=t})(document);</script>';
	// A plugin screens bundle mounts from the shared runtime once it registered (runtime.tsx mountPending);
	// FluxModules on the page then belongs to another (FluxWP) bundle and must not take the screen.
	$own = flx_admin_screen_is_plugin_bundle( $s ) ? 'return;' : '';
	echo '<script>(function(w){var s=w.FluxModulesOptions||(w.FluxModulesOptions={});s.id=' . wp_json_encode( $id ) . ';s.opts=' . wp_json_encode( $opts ) . ';function go(){' . $own . 'if(s.done||!w.FluxModules)return;var el=document.getElementById(s.id);if(!el)return;s.done=true;try{w.FluxModules.mount(el,s.opts);}catch(e){el.textContent=String(e&&e.message||e);}}if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",go);go();})(window);</script>';
	if ( ! $full ) {
		flx_admin_screen_print_routes( $page, $id );
	}
	return true;
}

/** Seconds the page spends at most on preloading a screen's queries. */
const FLX_ADMIN_SCREEN_PRELOAD_BUDGET = 0.8;

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
	$t0      = microtime( true );
	foreach ( flx_admin_screen_preload_items( $s, $query ) as $p ) {
		// A slow query is left to the browser: the page itself must not wait long.
		if ( microtime( true ) - $t0 > FLX_ADMIN_SCREEN_PRELOAD_BUDGET ) {
			break;
		}
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
		$b     = flx_admin_screen_bundle( $s );
		$items = flx_admin_screen_preload_items( $s, array() );
		$opts  = flx_admin_screen_options( $page, $s, $page === $current ? flx_admin_screen_extra() : array() );
		$opts['learn'] = array(
			'url'   => admin_url( 'admin-ajax.php' ),
			'key'   => flx_admin_screen_learn_key( $s, array() ),
			'known' => array_column( $items, 'key' ),
		);
		// Arguments of the menu address itself besides page (edit.php?post_type=…&page=…).
		wp_parse_str( (string) wp_parse_url( $m['url'], PHP_URL_QUERY ), $args );
		unset( $args['page'] );
		$routes[ $page ] = array(
			'file'       => $m['file'],
			'args'       => (object) array_map( 'flx_gql_str', $args ),
			'module'     => $s['module'],
			'bundle'     => array_merge(
				array(
					'entry' => $b['entry'],
					'css'   => $b['css'],
					'id'    => $b['id'],
				),
				isset( $b['screens'] ) ? array(
					'screens'    => $b['screens'],
					'screensCss' => $b['screensCss'] ?? '',
					'screensId'  => $b['screensId'] ?? '',
				) : array()
			),
			'opts'       => $opts,
			'start'      => $s['start'],
			'startParam' => $s['start_param'],
			'themeStorage' => $s['theme_storage'],
			'preload'    => $items,
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

/**
 * Prints the current Flux screen, or the "could not be loaded" notice when it cannot open here.
 * The page callback of every Flux screen: there is no classic page to fall back to.
 */
function flx_admin_screen_page(): void {
	if ( ! flx_admin_screen_render() ) {
		flx_admin_screen_unavailable();
	}
}

/**
 * Why the screen of this page cannot open, for the notice (empty: nothing known).
 */
function flx_admin_screen_unavailable_reason(): string {
	if ( ! flx_gql_ready() ) {
		$s = flx_gql_state();
		if ( 'external' === ( $s['source'] ?? '' ) ) {
			return __( 'A separately installed WPGraphQL plugin is active and FluxWP cannot use it. Deactivate it under Plugins; FluxWP has its own copy.', 'flx-graphql' );
		}
		return __( 'Flux GraphQL is not ready on this site. Check Flux GraphQL under Plugins.', 'flx-graphql' );
	}
	return __( 'The screen files of this plugin are missing. Update or reinstall the plugin.', 'flx-graphql' );
}

/**
 * The notice a Flux screen shows instead of a blank page: what happened and a reload button.
 *
 * @param string $reason  Extra sentence (why); empty: the cache hint.
 * @param bool   $visible False: printed hidden, flx_admin_screen_failsafe() shows it when the screen fails.
 * @param string $id      Element id.
 */
function flx_admin_screen_fail_html( string $reason = '', bool $visible = true, string $id = '' ): string {
	if ( '' === $reason ) {
		$reason = __( 'If the plugin was just updated, the browser or site cache may still hold old files. Hard-reload the page (Ctrl/Cmd + Shift + R); if it persists, purge the site cache.', 'flx-graphql' );
	}
	$btn = 'padding:8px 14px;border-radius:8px;border:1px solid currentColor;background:transparent;color:inherit;cursor:pointer;font:inherit';
	return '<div class="flx-screen-fail" role="alert"' . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ( $visible ? '' : ' hidden' )
		. ' style="max-width:520px;margin:48px auto;padding:24px;text-align:center">'
		. '<h2 style="margin:0 0 8px;font-size:18px;font-weight:600">' . esc_html__( 'This screen could not be loaded', 'flx-graphql' ) . '</h2>'
		. '<p style="margin:0 0 16px;opacity:.8">' . esc_html( $reason ) . '</p>'
		. '<button type="button" style="' . esc_attr( $btn ) . '" onclick="location.reload()">' . esc_html__( 'Reload page', 'flx-graphql' ) . '</button>'
		. '</div>';
}

/**
 * Prints the notice for a Flux screen that cannot open on this page.
 *
 * @param string $reason Why; empty: flx_admin_screen_unavailable_reason().
 */
function flx_admin_screen_unavailable( string $reason = '' ): void {
	echo '<div class="wrap">' . flx_admin_screen_fail_html( '' !== $reason ? $reason : flx_admin_screen_unavailable_reason() ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in flx_admin_screen_fail_html().
}

/**
 * Watches a screen container: when the bundle (or one of its chunks) fails to load, or nothing mounts, the
 * hidden notice next to it appears. A screen that mounted and then fails shows the same notice from React
 * (apps/wp-embed ScreenGuard). Also for packages that mount their screen themselves.
 *
 * @param string $container Id of the screen element.
 */
function flx_admin_screen_failsafe( string $container ): void {
	$fail = $container . '-fail';
	echo flx_admin_screen_fail_html( '', false, $fail ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in flx_admin_screen_fail_html().
	echo '<script>(function(w,d){var c=' . wp_json_encode( $container ) . ',f=' . wp_json_encode( $fail ) . ';function show(){var e=d.getElementById(f),el=d.getElementById(c);if(!e||!e.hidden||!el||el.querySelector("*:not(noscript)"))return;el.textContent="";el.style.display="none";e.hidden=false;}function bad(t){return t&&t.tagName==="SCRIPT"&&/\/assets\/(flux|runtime)\//.test(t.src||"");}w.addEventListener("error",function(e){if(bad(e.target))show();},true);w.addEventListener("unhandledrejection",function(e){var m=String(e.reason&&e.reason.message||e.reason||"");if(/dynamically imported module|Importing a module script failed|error loading dynamically/i.test(m))show();});w.addEventListener("load",function(){setTimeout(show,6000);});})(window,document);</script>';
}
