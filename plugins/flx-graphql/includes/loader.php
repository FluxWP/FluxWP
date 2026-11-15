<?php
/**
 * Loads WPGraphQL: the embedded, pinned copy, or a separately installed one in the supported range.
 *
 * Two copies of WPGraphQL in one request define the same classes and functions twice (fatal), so only
 * one ever loads:
 *   - No separate WPGraphQL active → the embedded copy (vendor/wp-graphql) loads now.
 *   - A separate WPGraphQL is active → the embedded copy stays off. When that copy is inside
 *     [FLXGQL_WPGQL_MIN, FLXGQL_WPGQL_MAX) it is used; otherwise the kit registers nothing, the system
 *     check shows an error and the panel offers "Use the Flux GraphQL version" (deactivates the other one).
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin basename of a separately activated WPGraphQL (site or network), or ''.
 */
function flx_gql_external_plugin(): string {
	$active = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}
	foreach ( $active as $basename ) {
		if ( is_string( $basename ) && 'wp-graphql.php' === basename( $basename ) && 0 !== strpos( $basename, 'flx-graphql/' ) ) {
			return $basename;
		}
	}
	return '';
}

/**
 * Pinned embedded version (vendor/WPGRAPHQL_VERSION).
 */
function flx_gql_embedded_version(): string {
	static $v = null;
	if ( null === $v ) {
		$file = FLXGQL_DIR . 'vendor/WPGRAPHQL_VERSION';
		$v    = is_readable( $file ) ? trim( (string) file_get_contents( $file ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
	return $v;
}

/**
 * Loading state, decided once per request.
 *
 * @return array{source:string,external:string,version:string,ok:bool}
 *   source: embedded|external|none, external: basename of the other plugin, ok: kit may register types.
 */
function flx_gql_state(): array {
	global $flx_gql_state;
	if ( is_array( $flx_gql_state ) ) {
		if ( 'external' === $flx_gql_state['source'] && '' === $flx_gql_state['version'] && defined( 'WPGRAPHQL_VERSION' ) ) {
			$flx_gql_state['version'] = (string) WPGRAPHQL_VERSION;
			$flx_gql_state['ok']      = flx_gql_version_supported( $flx_gql_state['version'] );
		}
		return $flx_gql_state;
	}
	return array( 'source' => 'none', 'external' => '', 'version' => '', 'ok' => false );
}

/**
 * Whether a WPGraphQL version is inside the supported range.
 *
 * @param string $version Version string.
 */
function flx_gql_version_supported( string $version ): bool {
	return '' !== $version && version_compare( $version, FLXGQL_WPGQL_MIN, '>=' ) && version_compare( $version, FLXGQL_WPGQL_MAX, '<' );
}

/**
 * True when GraphQL types can be registered (WPGraphQL loaded and supported).
 */
function flx_gql_ready(): bool {
	$s = flx_gql_state();
	return $s['ok'] && function_exists( 'register_graphql_object_type' );
}

/**
 * Runs while plugins load: picks the copy and loads the embedded one when it is the choice.
 */
function flx_gql_boot_wpgraphql(): void {
	global $flx_gql_state;
	$external = flx_gql_external_plugin();
	if ( '' !== $external || class_exists( 'WPGraphQL', false ) ) {
		// The other copy loads after us (or already did, e.g. as a Composer dependency); its version is read later.
		$flx_gql_state = array(
			'source'   => 'external',
			'external' => $external,
			'version'  => defined( 'WPGRAPHQL_VERSION' ) ? (string) WPGRAPHQL_VERSION : '',
			'ok'       => defined( 'WPGRAPHQL_VERSION' ) && flx_gql_version_supported( (string) WPGRAPHQL_VERSION ),
		);
		return;
	}
	$main = FLXGQL_DIR . 'vendor/wp-graphql/wp-graphql.php';
	if ( ! is_readable( $main ) ) {
		$flx_gql_state = array( 'source' => 'none', 'external' => '', 'version' => '', 'ok' => false );
		return;
	}
	require_once $main;
	$version       = defined( 'WPGRAPHQL_VERSION' ) ? (string) WPGRAPHQL_VERSION : flx_gql_embedded_version();
	$flx_gql_state = array(
		'source'   => 'embedded',
		'external' => '',
		'version'  => $version,
		'ok'       => flx_gql_version_supported( $version ),
	);
	flx_gql_embedded_hooks();
}

/**
 * Hooks only for the embedded copy: our panel owns its settings, menus and telemetry.
 */
function flx_gql_embedded_hooks(): void {
	// WPGraphQL reads its own settings page; the embedded copy takes ours instead.
	add_filter(
		'graphql_get_setting_section_fields',
		static function ( $fields, $section ) {
			if ( 'graphql_general_settings' !== $section ) {
				return $fields;
			}
			$fields = is_array( $fields ) ? $fields : array();
			$s      = flx_gql_settings();
			return array_merge(
				$fields,
				array(
					'graphql_endpoint'                     => 'graphql',
					'graphiql_enabled'                     => $s['ide'] ? 'on' : 'off',
					'show_graphiql_link_in_admin_bar'      => 'off',
					'public_introspection_enabled'         => $s['introspection'] ? 'on' : 'off',
					'debug_mode_enabled'                   => $s['debug'] ? 'on' : 'off',
					'query_depth_enabled'                  => 'on',
					'query_depth_max'                      => (string) $s['max_depth'],
					'batch_queries_enabled'                => 'on',
					'batch_limit'                          => (string) $s['batch_limit'],
					'restrict_endpoint_to_logged_in_users' => 'off',
					'tracing_enabled'                      => 'off',
					'query_logs_enabled'                   => 'off',
					'query_analyzer_enabled'               => 'off',
				)
			);
		},
		10,
		2
	);
	// Debug output only reaches administrators.
	add_filter(
		'graphql_debug_enabled',
		static function ( $enabled ) {
			return $enabled && current_user_can( 'manage_options' );
		}
	);
	// WPGraphQL's own menus (GraphQL › Settings / Extensions / IDE) are replaced by Settings › Flux GraphQL.
	add_action(
		'admin_menu',
		static function () {
			remove_menu_page( 'graphiql-ide' );
			remove_menu_page( 'graphql-settings' );
		},
		999
	);
	// Rewrite rule for /graphql once after activation or an embedded-version change.
	add_action(
		'init',
		static function () {
			$want = FLXGQL_VERSION . '|' . flx_gql_embedded_version();
			if ( get_option( 'flxgql_rewrite' ) !== $want ) {
				flush_rewrite_rules( false );
				update_option( 'flxgql_rewrite', $want, false );
			}
		},
		99
	);
}

/**
 * Activation: WPGraphQL's own activation hook is bound to its file, so it never fires for the embedded copy.
 */
function flx_gql_activate(): void {
	delete_option( 'flxgql_rewrite' );
	if ( function_exists( 'graphql_activation_callback' ) && 'embedded' === flx_gql_state()['source'] ) {
		graphql_activation_callback();
	}
}

/**
 * Deactivation: the /graphql rule goes away on the next flush.
 */
function flx_gql_deactivate(): void {
	delete_option( 'flxgql_rewrite' );
	flush_rewrite_rules( false );
}

/**
 * Admin action: deactivate the separately installed WPGraphQL so the embedded copy loads.
 */
add_action(
	'admin_post_flxgql_use_embedded',
	static function () {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage plugins.', 'flx-graphql' ), 403 );
		}
		check_admin_referer( 'flxgql_use_embedded' );
		$external = flx_gql_external_plugin();
		if ( '' !== $external ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			deactivate_plugins( $external, false, is_plugin_active_for_network( $external ) );
		}
		delete_option( 'flxgql_rewrite' );
		wp_safe_redirect( add_query_arg( 'flxgql_msg', 'embedded', admin_url( 'options-general.php?page=flx-graphql' ) ) );
		exit;
	}
);
