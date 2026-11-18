<?php
/**
 * System check rows (flx_system_checks). Cheap: options, constants and files only.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rows of Flux GraphQL.
 *
 * @return array<int,array<string,string>>
 */
function flx_gql_check_rows(): array {
	$source = __( 'Flux GraphQL', 'flx-graphql' );
	$page   = admin_url( 'options-general.php?page=flx-graphql' );
	$s      = flx_gql_state();
	$rows   = array();

	$row = array(
		'id'           => 'flxgql.wpgraphql',
		'source'       => $source,
		'group'        => 'Plugins',
		'label'        => __( 'GraphQL server', 'flx-graphql' ),
		'action_url'   => $page,
		'action_label' => __( 'Flux GraphQL settings', 'flx-graphql' ),
	);
	if ( 'embedded' === $s['source'] && $s['ok'] ) {
		$row['status'] = 'ok';
		/* translators: %s: WPGraphQL version. */
		$row['message'] = sprintf( __( 'Built-in WPGraphQL %s is running.', 'flx-graphql' ), $s['version'] );
	} elseif ( 'external' === $s['source'] && $s['ok'] ) {
		$row['status'] = 'warning';
		/* translators: 1: WPGraphQL version, 2: built-in version. */
		$row['message'] = sprintf( __( 'A separately installed WPGraphQL %1$s is running instead of the built-in %2$s. It works, but its updates are not tested with FluxWP. Use the built-in copy unless another plugin needs the separate one.', 'flx-graphql' ), $s['version'], flx_gql_embedded_version() );
	} elseif ( 'external' === $s['source'] ) {
		$row['status'] = 'error';
		/* translators: 1: WPGraphQL version, 2: lowest supported, 3: first unsupported. */
		$row['message'] = sprintf( __( 'A separately installed WPGraphQL %1$s is active. FluxWP needs %2$s or newer and below %3$s, so FluxWP screens cannot open. Deactivate it to use the built-in copy.', 'flx-graphql' ), '' !== $s['version'] ? $s['version'] : '?', FLXGQL_WPGQL_MIN, FLXGQL_WPGQL_MAX );
	} else {
		$row['status']  = 'error';
		$row['message'] = __( 'The built-in WPGraphQL files are missing. Reinstall Flux GraphQL from FluxWP Panel › Packages.', 'flx-graphql' );
	}
	$rows[] = $row;

	$mode   = flx_gql_settings()['query_mode'];
	$rows[] = array(
		'id'           => 'flxgql.queries',
		'source'       => $source,
		'group'        => 'Security',
		'label'        => __( 'GraphQL queries', 'flx-graphql' ),
		'status'       => 'open' === $mode ? 'warning' : 'ok',
		'message'      => 'open' === $mode
			? __( 'Anyone may send any GraphQL query. Keep this only for a public headless front end; depth and complexity limits still apply.', 'flx-graphql' )
			/* translators: %d: number of known queries. */
			: sprintf( _n( 'Only queries shipped with FluxWP packages run (%d known).', 'Only queries shipped with FluxWP packages run (%d known).', count( flx_gql_persisted()['queries'] ), 'flx-graphql' ), count( flx_gql_persisted()['queries'] ) ),
		'action_url'   => $page,
		'action_label' => __( 'Settings', 'flx-graphql' ),
	);

	$errors = flx_gql_registration_errors();
	if ( $errors ) {
		$list   = implode( ', ', array_slice( array_keys( $errors ), 0, 5 ) );
		$rows[] = array(
			'id'      => 'flxgql.registry',
			'source'  => $source,
			'group'   => 'Plugins',
			'label'   => __( 'GraphQL types', 'flx-graphql' ),
			'status'  => 'warning',
			/* translators: %s: type or field names. */
			'message' => sprintf( __( 'Some GraphQL fields were refused because they break the FluxWP rules: %s. Update the package that adds them.', 'flx-graphql' ), $list ),
		);
	}
	return $rows;
}

add_filter(
	'flx_system_checks',
	static function ( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		return array_merge( $checks, flx_gql_check_rows() );
	}
);

/**
 * Admin notice on the plugins screen when an unsupported separate WPGraphQL blocks the built-in copy.
 */
add_action(
	'admin_notices',
	static function () {
		$s = flx_gql_state();
		if ( 'external' !== $s['source'] || $s['ok'] || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=flxgql_use_embedded' ), 'flxgql_use_embedded' );
		echo '<div class="notice notice-error"><p>';
		/* translators: %s: WPGraphQL version. */
		echo esc_html( sprintf( __( 'Flux GraphQL: the separately installed WPGraphQL %s is not supported, so FluxWP GraphQL screens are off.', 'flx-graphql' ), '' !== $s['version'] ? $s['version'] : '?' ) );
		echo ' <a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Use the Flux GraphQL version', 'flx-graphql' ) . '</a>';
		echo '</p></div>';
	}
);
