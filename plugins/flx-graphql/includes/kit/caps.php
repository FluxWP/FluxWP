<?php
/**
 * Capability model (kit 0.2).
 *
 * Every root field and mutation already takes a `cap` (registry.php, fail closed). This file adds the
 * shared parts packages need so they do not invent their own:
 *
 *   1. Combinators for `cap`:
 *        'cap' => flx_gql_any_cap( 'flxb_view_own', 'flx_bagis_view' )   // one of them is enough
 *        'cap' => flx_gql_all_caps( 'manage_options', 'promote_users' ) // all of them are needed
 *   2. One capability catalog, `flx_gql_capabilities`: [ cap => { label, group, package } ]. A package
 *      declares the capabilities it checks; Flux Roles feeds its whole Roles and access catalog in.
 *      Labels are in the user's language.
 *   3. `flxViewer`: who is asking and what they may do, so a screen hides what the server would refuse:
 *        query { flxViewer { loggedIn databaseId isAdmin capabilities can(capability: "flxg_use") } }
 *      `capabilities` lists the catalog capabilities the user has. Packages may add fields to
 *      FlxViewer with flx_gql_field( 'FlxViewer', … ) (a cap is still required for those fields).
 *   4. `flxCapabilities`: the catalog itself (signed-in users), with `granted` for the viewer.
 *
 * Client: useFlxViewer() / useFlxCan( cap ) in @fluxwp/graphql/react. The server still checks every
 * field; the viewer only spares the user buttons that would fail.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/**
 * `cap` that passes when the user has at least one of the capabilities.
 *
 * @param string ...$caps Capability names.
 * @return Closure(mixed, array<string,mixed>, mixed): bool
 */
function flx_gql_any_cap( string ...$caps ): Closure {
	return static function () use ( $caps ): bool {
		foreach ( $caps as $cap ) {
			if ( '' !== $cap && current_user_can( $cap ) ) {
				return true;
			}
		}
		return false;
	};
}

/**
 * `cap` that passes only when the user has every capability.
 *
 * @param string ...$caps Capability names (at least one).
 * @return Closure(mixed, array<string,mixed>, mixed): bool
 */
function flx_gql_all_caps( string ...$caps ): Closure {
	return static function () use ( $caps ): bool {
		if ( ! $caps ) {
			return false;
		}
		foreach ( $caps as $cap ) {
			if ( '' === $cap || ! current_user_can( $cap ) ) {
				return false;
			}
		}
		return true;
	};
}

/**
 * The capability catalog: every capability FluxWP packages declare.
 *
 * @return array<string,array{label:string,group:string,package:string}>
 */
function flx_gql_capabilities(): array {
	/**
	 * Capabilities a package checks, for screens and the viewer:
	 *
	 *     add_filter( 'flx_gql_capabilities', fn( $c ) => $c + array(
	 *         'flxg_use' => array( 'label' => __( 'Uses Tasks', 'flx-tasks' ), 'group' => __( 'Tasks', 'flx-tasks' ), 'package' => 'flx-tasks' ),
	 *     ) );
	 *
	 * @since 0.2.0
	 * @param array<string,array<string,string>> $caps Catalog.
	 */
	$raw = apply_filters( 'flx_gql_capabilities', array() );
	$out = array();
	foreach ( flx_gql_arr( $raw ) as $cap => $c ) {
		$cap = (string) $cap;
		if ( '' === $cap || ! preg_match( '/^[a-z0-9_]+$/', $cap ) ) {
			continue;
		}
		$c           = is_array( $c ) ? flx_gql_arr( $c ) : array( 'label' => flx_gql_str( $c ) );
		$out[ $cap ] = array(
			'label'   => flx_gql_str( $c['label'] ?? '', $cap ),
			'group'   => flx_gql_str( $c['group'] ?? '' ),
			'package' => flx_gql_str( $c['package'] ?? '' ),
		);
	}
	return $out;
}

/**
 * Catalog capabilities the current user has.
 *
 * @return list<string>
 */
function flx_gql_viewer_capabilities(): array {
	if ( ! is_user_logged_in() ) {
		return array();
	}
	$out = array();
	foreach ( array_keys( flx_gql_capabilities() ) as $cap ) {
		if ( current_user_can( $cap ) ) {
			$out[] = $cap;
		}
	}
	return $out;
}

add_action( 'flx_gql_register_core', 'flx_gql_register_cap_types', 10 );

/**
 * Registers FlxViewer, FlxCapability, flxViewer and flxCapabilities.
 */
function flx_gql_register_cap_types(): void {
	flx_gql_type(
		'FlxViewer',
		array(
			'description' => __( 'The user making the request and what they may do.', 'flx-graphql' ),
			'fields'      => array(
				'loggedIn'     => array(
					'type'        => 'Boolean!',
					'description' => __( 'Whether the request is signed in.', 'flx-graphql' ),
					'resolve'     => static fn(): bool => is_user_logged_in(),
				),
				'databaseId'   => array(
					'type'        => 'Int',
					'description' => __( 'User ID; empty for a visitor.', 'flx-graphql' ),
					'resolve'     => static fn(): ?int => is_user_logged_in() ? get_current_user_id() : null,
				),
				'name'         => array(
					'type'        => 'String',
					'description' => __( 'Display name; empty for a visitor.', 'flx-graphql' ),
					'resolve'     => static fn(): ?string => is_user_logged_in() ? wp_get_current_user()->display_name : null,
				),
				'isAdmin'      => array(
					'type'        => 'Boolean!',
					'description' => __( 'Whether the user manages the site (manage_options).', 'flx-graphql' ),
					'resolve'     => static fn(): bool => current_user_can( 'manage_options' ),
				),
				'capabilities' => array(
					'type'        => '[String!]!',
					'description' => __( 'Capabilities from the FluxWP catalog that the user has.', 'flx-graphql' ),
					'resolve'     => static fn(): array => flx_gql_viewer_capabilities(),
				),
				'can'          => array(
					'type'        => 'Boolean!',
					'description' => __( 'Whether the user has this capability.', 'flx-graphql' ),
					'args'        => array(
						'capability' => array(
							'type'        => 'String!',
							'description' => __( 'Capability name, e.g. flxg_use.', 'flx-graphql' ),
						),
					),
					'resolve'     => static function ( $source, array $args ): bool {
						$cap = flx_gql_str( $args['capability'] ?? '' );
						return '' !== $cap && is_user_logged_in() && current_user_can( $cap );
					},
				),
			),
		)
	);

	flx_gql_type(
		'FlxCapability',
		array(
			'description' => __( 'A capability a FluxWP package checks.', 'flx-graphql' ),
			'fields'      => array(
				'key'     => array(
					'type'        => 'String!',
					'description' => __( 'Capability name, e.g. flxg_use.', 'flx-graphql' ),
				),
				'label'   => array(
					'type'        => 'String!',
					'description' => __( 'What the capability allows, in the user\'s language.', 'flx-graphql' ),
				),
				'group'   => array(
					'type'        => 'String!',
					'description' => __( 'Group label (usually the package); empty when not given.', 'flx-graphql' ),
				),
				'package' => array(
					'type'        => 'String!',
					'description' => __( 'Package slug that declares it; empty when not given.', 'flx-graphql' ),
				),
				'granted' => array(
					'type'        => 'Boolean!',
					'description' => __( 'Whether the current user has it.', 'flx-graphql' ),
				),
			),
		)
	);

	flx_gql_query(
		'flxViewer',
		array(
			'type'        => 'FlxViewer!',
			'description' => __( 'The user making the request. A visitor gets loggedIn false and no capabilities.', 'flx-graphql' ),
			'cap'         => 'public',
			'resolve'     => static fn(): array => array(),
		)
	);

	flx_gql_query(
		'flxCapabilities',
		array(
			'type'        => '[FlxCapability!]!',
			'description' => __( 'The FluxWP capability catalog, with what the current user has.', 'flx-graphql' ),
			'cap'         => 'logged_in',
			'resolve'     => static function (): array {
				$out = array();
				foreach ( flx_gql_capabilities() as $cap => $c ) {
					$out[] = array(
						'key'     => $cap,
						'label'   => $c['label'],
						'group'   => $c['group'],
						'package' => $c['package'],
						'granted' => current_user_can( $cap ),
					);
				}
				return $out;
			},
		)
	);
}
