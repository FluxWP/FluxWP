<?php
/**
 * Forms › Dashboard, All forms, Responses, Settings, Payments, Automations, System check and Add-ons are one
 * React screen (packages/modules/src/forms, assets/flux/ via tools/fluxapp/embed-sync.sh flx-forms) on the Flux
 * GraphQL shell (flx_admin_screen). The first queries of the page are preloaded, so it opens with data. Forms ›
 * New form (Emsfb_create, ?form=N to edit) is the builder: the same screen, plus the block editor and the Flux
 * Forms blocks (blocks/src/builder.js), which only that page loads. The screen shows only the tabs of this user's
 * Forms menu (#flxf-islands data-tabs). Without Flux GraphQL the page says so.
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
		'flxf-dashboard'   => 'dashboard',
		'flxf-forms'       => 'forms',
		'flxf-responses'   => 'responses',
		'flxf-settings'    => 'settings',
		'flxf_payments'    => 'payments',
		'flxf_automations' => 'automations',
		'flxf_system'      => 'system',
		'Emsfb_addon'      => 'addons',
		'Emsfb_create'     => 'create',
	);
}

/**
 * The other Forms pages: wp-admin slug => screen tab.
 *
 * @return array<string,string>
 */
function flxf_other_pages(): array {
	return array();
}

/** Registers the pages on the shell (before the menu). */
function flxf_register_screens(): void {
	if ( ! function_exists( 'flx_admin_screen' ) ) {
		return;
	}
	foreach ( flxf_react_pages() as $page => $route ) {
		// The shell looks the page up by sanitize_key() (lower case): "Emsfb_addon" is registered as "emsfb_addon".
		flx_admin_screen(
			strtolower( $page ),
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

/** Route of the open page: "dashboard", "forms", "responses?form=N", "responses?entry=N", "automations?flow=N|new", "automations?tab=runs" … */
function flxf_flux_start(): string {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'flxf-dashboard';
	$route = array_change_key_case( flxf_react_pages() )[ $page ] ?? 'dashboard';
	if ( 'automations' === $route ) {
		$flow = isset( $_GET['flow'] ) ? sanitize_key( wp_unslash( $_GET['flow'] ) ) : '';
		if ( 'new' === $flow ) {
			return 'automations?flow=new';
		}
		if ( absint( $flow ) > 0 ) {
			return 'automations?flow=' . absint( $flow );
		}
		if ( isset( $_GET['tab'] ) && 'runs' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ) {
			return 'automations?tab=runs';
		}
	}
	if ( 'create' === $route ) {
		return ! empty( $_GET['form'] ) ? 'create?form=' . absint( $_GET['form'] ) : 'create';
	}
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
		case 'payments':
			return array( array( 'FlxFormsPayments', array() ) );
		case 'create':
			return ! empty( $args['form'] ) ? array( array( 'FlxFormsBuilderForm', array( 'id' => absint( $args['form'] ) ) ) ) : array();
		case 'system':
			return array( array( 'FlxFormsSystem', array() ) );
		case 'addons':
			return array( array( 'FlxFormsAddons', array() ) );
		case 'automations':
			if ( ! empty( $args['flow'] ) ) {
				$id = 'new' === $args['flow'] ? 0 : absint( $args['flow'] );
				return array( array( 'FlxFormsAutomationEditor', array( 'id' => $id ) ) );
			}
			if ( 'runs' === ( $args['tab'] ?? '' ) ) {
				return array( array( 'FlxFormsAutomationRuns', array() ), array( 'FlxFormsAutomations', array() ) );
			}
			return array( array( 'FlxFormsAutomations', array() ) );
	}
	return array();
}

/**
 * The Forms pages that are there only for some users or setups and that this user has in the menu (screen tabs):
 * the builder, the panel, Payments, Automations, System check and Add-ons.
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
	$pages = flxf_other_pages() + array_diff_key( flxf_react_pages(), array_flip( array( 'flxf-dashboard', 'flxf-forms', 'flxf-responses', 'flxf-settings' ) ) );
	foreach ( $pages as $slug => $tab ) {
		if ( isset( $have[ $slug ] ) ) {
			$tabs[] = $tab;
		}
	}
	return $tabs;
}

/** The menu callback of every React page: the shell prints the screen. */
function flxf_screen_page(): void {
	if ( ! function_exists( 'flx_admin_screen_page' ) ) {
		echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Something went wrong.', 'flx-forms' ) . ' (Flux GraphQL)</p></div></div>';
		return;
	}
	flx_admin_screen_page();
	echo '<div id="flxf-islands" hidden data-tabs="' . esc_attr( implode( ',', flxf_other_tabs() ) ) . '"></div>';
}

/**
 * The builder (Forms › New form, ?form=N to edit): the block editor and the Flux Forms blocks, which the builder
 * screen mounts (includes/flx/blocks/src/builder.js).
 *
 * @param string $hook Admin page hook.
 */
function flxf_builder_assets( $hook ): void {
	unset( $hook );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! isset( $_GET['page'] ) || 'Emsfb_create' !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) || ! class_exists( 'FLXF_Blocks' ) ) {
		return;
	}
	FLXF_Blocks::builder_assets();
}
add_action( 'admin_enqueue_scripts', 'flxf_builder_assets', 20 );

/** Old panel addresses (admin.php?page=Emsfb…): the builder for "edit form", Responses, Settings, else the forms list. */
function flxf_panel_redirect(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$state = isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : '';
	$id    = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	// phpcs:enable
	if ( 'edit-form' === $state && $id ) {
		$to = admin_url( 'admin.php?page=Emsfb_create&form=' . $id );
	} elseif ( 'show-messages' === $state && $id ) {
		$to = admin_url( 'admin.php?page=flxf-responses&form=' . $id );
	} elseif ( 'setting' === $state ) {
		$to = admin_url( 'admin.php?page=flxf-settings' );
	} elseif ( 'create' === $state ) {
		$to = admin_url( 'admin.php?page=Emsfb_create' );
	} else {
		$to = admin_url( 'admin.php?page=flxf-forms' );
	}
	wp_safe_redirect( $to );
	exit;
}
add_action( 'load-toplevel_page_Emsfb', 'flxf_panel_redirect' );

/** WordPress lists the top-level page as the first submenu item when it has no submenu of its own: not needed (it redirects). */
function flxf_hide_panel_item(): void {
	remove_submenu_page( 'Emsfb', 'Emsfb' );
}
add_action( 'admin_menu', 'flxf_hide_panel_item', 999 );
