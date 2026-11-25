<?php
/**
 * The Communication pages are one React screen (packages/modules/src/mail, assets/flux/ via
 * tools/fluxapp/embed-sync.sh flx-mail) on the Flux GraphQL shell (flx_admin_screen): 1.24.0 preloads the
 * first queries of the page, so it opens at once, and the classic view is gone. Send, Queue, Blocklist and
 * Connections are still PHP forms: the page prints the open one hidden ("island") and the screen shows it
 * under the shared header and tabs. Without Flux GraphQL the page says so.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/**
 * The shell pages and the route each one starts on.
 *
 * @return array<string,string> Page slug => route.
 */
function flxi_screen_pages(): array {
	$pages = array(
		'flxi'             => 'templates',
		'flxi-gonder'      => 'send',
		'flxi-kuyruk'      => 'queue',
		'flxi-kayitlar'    => 'logs',
		'flxi-kara-liste'  => 'blocklist',
		'flxi-baglantilar' => 'connections',
		'flxi-ayarlar'     => 'settings',
	);
	if ( ! flxi_system_moved() ) {
		$pages['flxi-sistem'] = 'system';
	}
	return $pages;
}

/**
 * The pages PHP prints (islands) and their classic callbacks.
 *
 * @return array<string,callable-string> Page slug => callback.
 */
function flxi_island_pages(): array {
	return array(
		'flxi-gonder'      => 'flxi_page_send',
		'flxi-kuyruk'      => 'flxi_page_queue',
		'flxi-kara-liste'  => 'flxi_page_blocklist',
		'flxi-baglantilar' => 'flxi_page_connections',
	);
}

/** The current admin page slug. */
function flxi_current_page(): string {
	return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

/** Is the page being printed an island (its classic content goes into the screen)? */
function flxi_is_island(): bool {
	return ! empty( $GLOBALS['flxi_island'] );
}

/** Registers the pages on the shell (before the menu). */
function flxi_register_screens(): void {
	if ( ! function_exists( 'flx_admin_screen' ) ) {
		return;
	}
	foreach ( flxi_screen_pages() as $page => $route ) {
		flx_admin_screen(
			$page,
			array(
				'module'       => 'flxi-mail',
				'assets_dir'   => FLXI_DIR . 'assets/flux/',
				'assets_url'   => FLXI_URL . 'assets/flux/',
				'version'      => FLXI_VERSION,
				'enabled'      => '__return_true',
				'options'      => 'flxi_screen_options',
				'start'        => $route,
				'container_id' => 'flxi-app',
				'preload'      => 'flxi_screen_preload',
				'enqueue'      => 'flxi_screen_enqueue',
			)
		);
	}
}
add_action( 'admin_menu', 'flxi_register_screens', 5 );

/** Logo picker of Settings › Brand; the classic editor for the email content (@fluxwp/ui WpEditor). */
function flxi_screen_enqueue(): void {
	wp_enqueue_media();
	if ( 'flxi' === flxi_current_page() && function_exists( 'wp_enqueue_editor' ) ) {
		wp_enqueue_editor();
	}
}

/** The route the current page starts on (template key, log filters). */
function flxi_flux_start(): string {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$start = flxi_screen_pages()[ flxi_current_page() ] ?? 'templates';
	$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
	if ( '' !== $key && 'templates' === $start ) {
		return 'edit:' . $key;
	}
	if ( 'logs' === $start ) {
		$q_args = array();
		$st     = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$tpl    = isset( $_GET['template'] ) ? sanitize_text_field( wp_unslash( $_GET['template'] ) ) : '';
		$camp   = isset( $_GET['campaign'] ) ? absint( wp_unslash( $_GET['campaign'] ) ) : 0;
		if ( '' !== $st ) {
			$q_args['status'] = $st;
		}
		if ( '' !== $tpl ) {
			$q_args['template'] = $tpl;
		}
		if ( $camp > 0 ) {
			$q_args['campaign'] = (string) $camp;
		}
		if ( $q_args ) {
			return 'logs?' . http_build_query( $q_args );
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	return $start;
}

/**
 * Mount options besides what the shell adds.
 *
 * @param string $page Page slug.
 * @return array<string,mixed>
 */
function flxi_screen_options( $page = '' ): array {
	unset( $page );
	$accent  = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	$islands = array();
	foreach ( array_keys( flxi_island_pages() ) as $slug ) {
		$islands[ flxi_screen_pages()[ $slug ] ] = flxi_admin_url( $slug );
	}
	return array(
		'startPage' => flxi_flux_start(),
		'colors'    => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
		'appIcon'   => FLXI_URL . 'assets/icon.svg',
		'islands'   => $islands,
	);
}

/**
 * The first queries of the start route, with the variables the screen sends (same store key).
 *
 * @param mixed $query Query arguments of the page (unused: the start route comes from the address).
 * @return list<array{0:string,1:array<string,mixed>}>
 */
function flxi_screen_preload( $query = array() ): array {
	unset( $query );
	return flxi_preload_for( flxi_flux_start() );
}

/**
 * @param string $start Start route (templates, edit:key, logs?status=failed, settings …).
 * @return list<array{0:string,1:array<string,mixed>}>
 */
function flxi_preload_for( string $start ): array {
	if ( 0 === strpos( $start, 'edit:' ) ) {
		return array( array( 'FlxMailTemplate', array( 'key' => substr( $start, 5 ) ) ) );
	}
	$path = explode( '?', $start, 2 );
	$q    = array();
	parse_str( $path[1] ?? '', $q );
	switch ( $path[0] ) {
		case 'templates':
			return array( array( 'FlxMailTemplates', array( 'first' => 50 ) ) );
		case 'logs':
			$vars = array( 'first' => 50 );
			foreach ( array( 'status', 'template' ) as $k ) {
				if ( isset( $q[ $k ] ) && is_string( $q[ $k ] ) && '' !== $q[ $k ] ) {
					$vars[ $k ] = $q[ $k ];
				}
			}
			if ( isset( $q['campaign'] ) && is_string( $q['campaign'] ) && absint( $q['campaign'] ) ) {
				$vars['campaign'] = absint( $q['campaign'] );
			}
			return array( array( 'FlxMailLogs', $vars ) );
		case 'settings':
			return array( array( 'FlxMailSettings', array() ) );
		case 'system':
			return array( array( 'FlxMailSystemChecks', array() ) );
	}
	return array();
}

/** The menu callback: the shell prints the screen (and the open island). */
function flxi_screen_page(): void {
	if ( ! flxi_can_manage() ) {
		wp_die( esc_html__( 'You do not have permission.', 'flx-mail' ), '', array( 'response' => 403 ) );
	}
	if ( ! function_exists( 'flx_admin_screen_page' ) ) {
		echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Error', 'flx-mail' ) . ': Flux GraphQL</p></div></div>';
		return;
	}
	$page = flxi_current_page();
	$cb   = flxi_island_pages()[ $page ] ?? '';
	$html = '';
	if ( '' !== $cb ) {
		$GLOBALS['flxi_island'] = true;
		ob_start();
		call_user_func( $cb );
		$html = trim( (string) ob_get_clean() );
		unset( $GLOBALS['flxi_island'] );
	}
	flx_admin_screen_page();
	if ( '' !== $html ) {
		echo '<div id="flxi-islands" hidden><div class="flxi-wrap flxi-island" data-flxi-island="' . esc_attr( flxi_screen_pages()[ $page ] ) . '">' . $html . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the page's own escaped output
	}
}

add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m              = is_array( $m ) ? $m : array();
		$m['flxi-mail'] = array(
			'title'   => __( 'Communication', 'flx-mail' ),
			'cap'     => 'flxi_manage',
			'menu'    => 'flxi',
			'url'     => admin_url( 'admin.php?page=flxi' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'mail',
			'version' => FLXI_VERSION,
		);
		return $m;
	}
);

add_filter(
	'flxa_app_screens',
	static function ( $map ) {
		$map = is_array( $map ) ? $map : array();
		foreach ( array_keys( array_diff_key( flxi_screen_pages(), flxi_island_pages() ) ) as $slug ) {
			$map[ $slug ] = 'mail';
		}
		return $map;
	}
);
