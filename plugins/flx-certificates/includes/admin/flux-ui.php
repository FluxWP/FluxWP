<?php
/**
 * The Certificates pages are one React screen (packages/modules/src/certificates, assets/flux/ via
 * tools/fluxapp/embed-sync.sh flx-certificates) on the Flux GraphQL shell (flx_admin_screen): 2.3.0 preloads
 * the first queries of the page, so it opens at once, and the classic view is gone. Records, New certificate
 * and People are React; the other pages are still PHP: the page prints the open one hidden ("island") and the
 * screen shows it under the shared header and tabs. Without Flux GraphQL the page says so.
 *
 * @package flx-certificates
 */

defined( 'ABSPATH' ) || exit;

/**
 * The React pages and the route each one starts on.
 *
 * @return array<string,string>
 */
function flxs_react_pages(): array {
	return array(
		'flxs'         => 'records',
		'flxs-yeni'    => 'new',
		'flxs-kisiler' => 'people',
	);
}

/**
 * The PHP pages (islands) this user can open: page slug => [route, classic callback].
 *
 * @return array<string,array{0:string,1:callable-string}>
 */
function flxs_island_pages(): array {
	$out = array();
	if ( flxs_can_dashboard() ) {
		$out['flxs-dashboard'] = array( 'dashboard', 'flxs_page_dashboard' );
	}
	if ( flxs_can_manage() && ! function_exists( 'flxcrm_events_url' ) ) {
		$out['flxs-etkinlikler'] = array( 'events', 'flxs_page_events' );
	}
	$out['flxs-kuyruk'] = array( 'queue', 'flxs_page_queue' );
	if ( flxs_can_bulk() ) {
		$out['flxs-toplu'] = array( 'bulk', 'flxs_page_bulk' );
	}
	if ( flxs_can_manage() ) {
		$out['flxs-sablonlar'] = array( 'templates', 'flxs_page_templates' );
		$out['flxs-kayitlar']  = array( 'logs', 'flxs_page_logs' );
		$out['flxs-ayarlar']   = array( 'settings', 'flxs_page_settings' );
		$out['flxs-sistem']    = array( 'system', 'flxs_page_system' );
	}
	return $out;
}

/** Is the page being printed an island (its classic content goes into the screen)? */
function flxs_is_island(): bool {
	return ! empty( $GLOBALS['flxs_island'] );
}

/** Registers the pages on the shell (before the menu). */
function flxs_register_screens(): void {
	if ( ! function_exists( 'flx_admin_screen' ) ) {
		return;
	}
	$pages = flxs_react_pages();
	foreach ( flxs_island_pages() as $slug => $island ) {
		$pages[ $slug ] = $island[0];
	}
	foreach ( $pages as $page => $route ) {
		flx_admin_screen(
			$page,
			array(
				'module'       => 'flxs-certificates',
				'assets_dir'   => FLXS_DIR . 'assets/flux/',
				'assets_url'   => FLXS_URL . 'assets/flux/',
				'version'      => FLXS_VERSION,
				'enabled'      => '__return_true',
				'options'      => 'flxs_screen_options',
				'start'        => $route,
				'container_id' => 'flxs-app',
				'preload'      => 'flxs_screen_preload',
			)
		);
	}
}
add_action( 'admin_menu', 'flxs_register_screens', 5 );

/** startPage for the React screen. */
function flxs_flux_start(): string {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'flxs';
	if ( isset( flxs_island_pages()[ $page ] ) ) {
		return flxs_island_pages()[ $page ][0];
	}
	if ( 'flxs-yeni' === $page ) {
		return ! empty( $_GET['id'] ) ? 'new:' . absint( $_GET['id'] ) : 'new';
	}
	if ( 'flxs-kisiler' === $page ) {
		if ( ! empty( $_GET['person'] ) ) {
			return 'person:' . absint( $_GET['person'] );
		}
		return ! empty( $_GET['add'] ) ? 'people:add' : 'people';
	}
	if ( ! empty( $_GET['person'] ) ) {
		return 'records:' . absint( $_GET['person'] );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	return 'records';
}

/**
 * Mount options besides what the shell adds.
 *
 * @param string $page Page slug.
 * @return array<string,mixed>
 */
function flxs_screen_options( $page = '' ): array {
	unset( $page );
	$accent = function_exists( 'flxa_accent' ) ? (string) flxa_accent() : '#2c7a64';
	return array(
		'startPage' => flxs_flux_start(),
		'colors'    => array(
			'primary'         => $accent,
			'primaryContrast' => function_exists( 'flxa_on_color' ) ? (string) flxa_on_color( $accent ) : '#ffffff',
		),
		'appIcon'   => FLXS_URL . 'assets/icon.svg',
	);
}

/**
 * @param mixed $query Query arguments of the page (unused: the start route comes from the address).
 * @return list<array{0:string,1:array<string,mixed>}>
 */
function flxs_screen_preload( $query = array() ): array {
	unset( $query );
	return flxs_preload_for( flxs_flux_start() );
}

/**
 * The first queries of a start route, with the variables the screen sends (same store key).
 *
 * @param string $start Start route.
 * @return list<array{0:string,1:array<string,mixed>}>
 */
function flxs_preload_for( string $start ): array {
	if ( 'records' === $start ) {
		return array(
			array(
				'FlxCertificateRecords',
				array(
					'first'  => 20,
					'after'  => null,
					'filter' => (object) array(), // the client keys an empty filter as {}
				),
			),
		);
	}
	if ( 'new' === $start ) {
		return array( array( 'FlxCertificateCreateConfig', array( 'certificateId' => null ) ) );
	}
	if ( 'people' === $start ) {
		return array(
			array(
				'FlxCertificateDirectory',
				array(
					'search' => '',
					'first'  => 30,
					'after'  => null,
				),
			),
		);
	}
	if ( 0 === strpos( $start, 'person:' ) && absint( substr( $start, 7 ) ) ) {
		$id = absint( substr( $start, 7 ) );
		return array(
			array( 'FlxCertificatePerson', array( 'id' => $id ) ),
			array( 'FlxCertificatePersonMessages', array( 'personId' => $id ) ),
		);
	}
	return array();
}

/** The menu callback of every Certificates page: the shell prints the screen (and the open island). */
function flxs_screen_page(): void {
	if ( ! function_exists( 'flx_admin_screen_page' ) ) {
		echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Something went wrong.', 'flx-certificates' ) . ' (Flux GraphQL)</p></div></div>';
		return;
	}
	$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'flxs'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$islands = flxs_island_pages();
	$html    = '';
	if ( isset( $islands[ $page ] ) ) {
		$GLOBALS['flxs_island'] = true;
		ob_start();
		call_user_func( $islands[ $page ][1] );
		$html = trim( (string) ob_get_clean() );
		unset( $GLOBALS['flxs_island'] );
	}
	flx_admin_screen_page();
	$tabs = array();
	foreach ( $islands as $island ) {
		$tabs[] = $island[0];
	}
	// The island tabs this user has (the screen adds them to its tabs) and the open island.
	echo '<div id="flxs-islands" hidden data-tabs="' . esc_attr( (string) wp_json_encode( $tabs ) ) . '">';
	if ( '' !== $html ) {
		echo '<div class="flxs-wrap flxs-island" data-flxs-island="' . esc_attr( $islands[ $page ][0] ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the page's own escaped output
	}
	echo '</div>';
}
