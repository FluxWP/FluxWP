<?php
/**
 * Forms › Dashboard, All forms, Responses and Settings are one React screen (packages/modules/src/forms, assets/flux/ via
 * tools/fluxapp/embed-sync.sh flx-forms) on the Flux GraphQL shell (flx_admin_screen). The first queries of the
 * page are preloaded, so it opens with data. The builder, the panel, Payments, Automations, System check and
 * Add-ons are still their own pages: the screen shows their tabs (the ones in this user's Forms menu) and opens
 * them. Without Flux GraphQL the page says so.
 *
 * @package FLX_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * The React pages and the route each one starts on.
 *
 * @return array<string,string>
 */
function flxf_react_pages(): array {
	return array(
		'flxf-dashboard' => 'dashboard',
		'flxf-forms'     => 'forms',
		'flxf-responses' => 'responses',
		'flxf-settings'  => 'settings',
	);
}

/**
 * The other Forms pages: wp-admin slug => screen tab.
 *
 * @return array<string,string>
 */
function flxf_other_pages(): array {
	return array(
		'Emsfb_create'     => 'create',
		'Emsfb'            => 'panel',
		'flxf_payments'    => 'payments',
		'flxf_automations' => 'automations',
		'flxf_system'      => 'system',
		'Emsfb_addon'      => 'addons',
	);
}

/** Registers the pages on the shell (before the menu). */
function flxf_register_screens(): void {
	if ( ! function_exists( 'flx_admin_screen' ) ) {
		return;
	}
	foreach ( flxf_react_pages() as $page => $route ) {
		flx_admin_screen(
			$page,
			array(
				'module'       => 'flxf-forms',
				'assets_dir'   => EMSFB_PLUGIN_DIRECTORY . 'assets/flux/',
				'assets_url'   => EMSFB_PLUGIN_URL . 'assets/flux/',
				'version'      => flxf_app_version(),
				'enabled'      => '__return_true',
				'options'      => 'flxf_screen_options',
				'start'        => $route,
				'container_id' => 'flxf-app',
				'preload'      => 'flxf_screen_preload',
			)
		);
	}
}
add_action( 'admin_menu', 'flxf_register_screens', 5 );

/** Route of the open page: "dashboard", "forms", "responses?form=N" or "responses?entry=N". */
function flxf_flux_start(): string {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'flxf-dashboard';
	$route = flxf_react_pages()[ $page ] ?? 'dashboard';
	if ( 'responses' === $route ) {
		if ( ! empty( $_GET['entry'] ) ) {
			return 'responses?entry=' . absint( $_GET['entry'] );
		}
		if ( ! empty( $_GET['form'] ) ) {
			return 'responses?form=' . absint( $_GET['form'] );
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	return $route;
}

/**
 * Mount options besides what the shell adds.
 *
 * @param string $page Page slug.
 * @return array<string,mixed>
 */
function flxf_screen_options( $page = '' ): array {
	unset( $page );
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	return array(
		'startPage' => flxf_flux_start(),
		'colors'    => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
		'appIcon'   => EMSFB_PLUGIN_URL . 'assets/icon.svg',
	);
}

/**
 * @param mixed $query Query arguments of the page (unused: the start route comes from the address).
 * @return list<array{0:string,1:array<string,mixed>}>
 */
function flxf_screen_preload( $query = array() ): array {
	unset( $query );
	return flxf_preload_for( flxf_flux_start() );
}

/**
 * The first queries of a start route, with the variables the screen sends (same store key).
 *
 * @param string $start Start route.
 * @return list<array{0:string,1:array<string,mixed>}>
 */
function flxf_preload_for( string $start ): array {
	$parts = explode( '?', $start, 2 );
	$args  = array();
	parse_str( $parts[1] ?? '', $args );
	$options = array( 'FlxFormsFormOptions', array() );
	switch ( $parts[0] ) {
		case 'dashboard':
			return array( array( 'FlxFormsDashboard', array() ), $options );
		case 'settings':
			return array( array( 'FlxFormsSettings', array() ) );
		case 'forms':
			return array( array( 'FlxFormsAdminForms', array( 'first' => 50 ) ) );
		case 'responses':
			if ( ! empty( $args['entry'] ) ) {
				return array( array( 'FlxFormsEntry', array( 'id' => absint( $args['entry'] ) ) ) );
			}
			$vars = array( 'first' => 50 );
			if ( ! empty( $args['form'] ) ) {
				$vars['form'] = absint( $args['form'] );
			}
			return array( $options, array( 'FlxFormsEntries', $vars ) );
	}
	return array();
}

/**
 * The other Forms pages this user has in the menu (screen tabs).
 *
 * @return list<string>
 */
function flxf_other_tabs(): array {
	global $submenu;
	$have = array();
	foreach ( (array) ( $submenu['Emsfb'] ?? array() ) as $item ) {
		if ( isset( $item[1], $item[2] ) && current_user_can( (string) $item[1] ) ) {
			$have[ (string) $item[2] ] = true;
		}
	}
	$tabs = array();
	foreach ( flxf_other_pages() as $slug => $tab ) {
		if ( isset( $have[ $slug ] ) ) {
			$tabs[] = $tab;
		}
	}
	return $tabs;
}

/** The menu callback of Dashboard, All forms and Responses: the shell prints the screen. */
function flxf_screen_page(): void {
	if ( ! function_exists( 'flx_admin_screen_page' ) ) {
		echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Something went wrong.', 'flx-forms' ) . ' (Flux GraphQL)</p></div></div>';
		return;
	}
	flx_admin_screen_page();
	echo '<div id="flxf-islands" hidden data-tabs="' . esc_attr( implode( ',', flxf_other_tabs() ) ) . '"></div>';
}
