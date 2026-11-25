<?php
/**
 * The CRM pages are one React screen (packages/modules/src/crm, assets/flux/ via tools/fluxapp/embed-sync.sh
 * flx-crm) on the Flux GraphQL shell (flx_admin_screen): 0.12.0 preloads the first queries of the page, so it
 * opens at once, and the classic pages are gone. Events and the person-card sections other plugins add are
 * part of this screen. Without Flux GraphQL the page says so (the recovery page is Flux Setup's).
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

/** The screen the current admin page starts on (hash route inside the React screen). */
function flxcrm_flux_start() {
	// phpcs:disable WordPress.Security.NonceVerification
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'flx-crm';
	if ( 'flx-crm-events' === $page ) {
		if ( ! empty( $_GET['add'] ) ) {
			return 'events/new';
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( $id ) {
			$q = array();
			foreach ( array( 'view', 'sub', 'send', 'ch', 'st' ) as $k ) {
				if ( isset( $_GET[ $k ] ) && '' !== (string) $_GET[ $k ] ) {
					$q[ $k ] = sanitize_text_field( wp_unslash( (string) $_GET[ $k ] ) );
				}
			}
			if ( ! empty( $_GET['edit'] ) ) {
				$q['edit'] = '1';
			}
			return 'event/' . $id . ( $q ? '?' . http_build_query( $q ) : '' );
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : '';
		return in_array( $tab, array( 'archive', 'arsiv', 'closed' ), true ) ? 'events?status=closed' : ( 'site' === $tab ? 'events?tab=site' : 'events' );
	}
	if ( 'flx-crm-contacts' === $page ) {
		if ( ! empty( $_GET['id'] ) ) {
			return 'contact/' . absint( $_GET['id'] );
		}
		if ( isset( $_GET['action'] ) && 'new' === $_GET['action'] ) {
			return 'contacts/new';
		}
		if ( isset( $_GET['view'] ) && 'duplicates' === $_GET['view'] ) {
			return 'duplicates';
		}
		return 'contacts';
	}
	// phpcs:enable WordPress.Security.NonceVerification
	$map = array(
		'flx-crm'          => 'overview',
		'flx-crm-segments' => 'segments',
		'flx-crm-journeys' => 'journeys',
		'flx-crm-settings' => 'settings',
		'flx-crm-events'   => 'events',
	);
	return $map[ $page ] ?? 'overview';
}

/** The CRM admin pages and the route each one starts on. */
function flxcrm_screen_pages() {
	return array( 'flx-crm', 'flx-crm-contacts', 'flx-crm-segments', 'flx-crm-events', 'flx-crm-journeys', 'flx-crm-settings' );
}

/** Registers the pages on the shell (before the menu, admin_menu 55). */
function flxcrm_register_screens() {
	if ( ! function_exists( 'flx_admin_screen' ) ) {
		return;
	}
	foreach ( flxcrm_screen_pages() as $page ) {
		flx_admin_screen(
			$page,
			array(
				'module'       => 'crm',
				'assets_dir'   => FLXCRM_DIR . 'assets/flux/',
				'assets_url'   => FLXCRM_URL . 'assets/flux/',
				'version'      => FLXCRM_VERSION,
				'enabled'      => '__return_true',
				'options'      => 'flxcrm_screen_options',
				'start'        => 'overview',
				'container_id' => 'flxcrm-app',
				'preload'      => 'flxcrm_screen_preload',
				'enqueue'      => 'flxcrm_screen_enqueue',
			)
		);
	}
}
add_action( 'admin_menu', 'flxcrm_register_screens', 20 );

/** Event image and message file: the Events tab is on every CRM page. */
function flxcrm_screen_enqueue() {
	wp_enqueue_media();
}

/**
 * Mount options besides what the shell adds.
 *
 * @param string $page Page slug.
 * @return array<string,mixed>
 */
function flxcrm_screen_options( $page = '' ) {
	unset( $page );
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#be185d';
	return array(
		'startPage' => flxcrm_flux_start(),
		'colors'    => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
		'appIcon'   => FLXCRM_Admin::menu_icon_url(),
	);
}

/**
 * The first queries of the start route, with the variables the screen sends (same store key).
 *
 * @param mixed $query Query arguments of the page (unused: the start route comes from the address).
 * @return list<array{0:string,1:array<string,mixed>}>
 */
function flxcrm_screen_preload( $query = array() ) {
	unset( $query );
	return flxcrm_preload_for( flxcrm_flux_start() );
}

/**
 * @param string $start Start route (contacts, contact/12, event/3, …).
 * @return list<array{0:string,1:array<string,mixed>}>
 */
function flxcrm_preload_for( $start ) {
	$out  = array( array( 'FlxCrmShell', array() ) );
	$path = explode( '?', (string) $start, 2 );
	$q    = array();
	parse_str( $path[1] ?? '', $q );
	$part = explode( '/', $path[0], 2 );
	$name = $part[0];
	$id   = $part[1] ?? '';
	if ( 'overview' === $name ) {
		$out[] = array( 'FlxCrmOverview', array() );
	} elseif ( 'contacts' === $name && '' === $id ) {
		$out[] = array(
			'FlxCrmContacts',
			array(
				'filter'  => array( 'status' => 'ACTIVE' ),
				'orderBy' => 'LAST_ACTIVITY',
				'order'   => 'DESC',
				'first'   => 25,
				'after'   => null,
			),
		);
		$out[] = array( 'FlxCrmOverview', array() );
	} elseif ( 'contact' === $name && '' !== $id ) {
		$out[] = array( 'FlxCrmContact', array( 'id' => $id ) );
	} elseif ( 'duplicates' === $name ) {
		$out[] = array( 'FlxCrmDuplicates', array() );
	} elseif ( 'journeys' === $name && '' === $id ) {
		$out[] = array( 'FlxCrmJourneys', array() );
	} elseif ( 'events' === $name && '' === $id ) {
		$out[] = array( 'FlxCrmGatherings', array( 'status' => 'closed' === ( $q['status'] ?? '' ) ? 'CLOSED' : 'OPEN', 'first' => 24 ) );
	} elseif ( 'event' === $name && absint( $id ) && ! $q ) {
		$out[] = array( 'FlxCrmGathering', array( 'id' => absint( $id ) ) );
	} elseif ( 'settings' === $name ) {
		$out[] = array( 'FlxCrmSettings', array() );
	}
	return $out;
}

/** The menu callback: the shell prints the screen. */
function flxcrm_screen_page() {
	if ( ! flxcrm_can() ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'flx-crm' ), 403 );
	}
	if ( function_exists( 'flx_admin_screen_page' ) ) {
		flx_admin_screen_page();
		return;
	}
	echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Something went wrong. Please try again.', 'flx-crm' ) . ' (Flux GraphQL)</p></div></div>';
}
