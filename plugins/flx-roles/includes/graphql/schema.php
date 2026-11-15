<?php
/**
 * Flux Roles GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a
 * GraphQL request. Resolvers call FLXR_Roles_Service; the Roles and access screen (wp-admin and
 * FluxWP app) reads and writes only through these fields.
 *
 * Role and capability keys stay strings: roles are added from the panel and packages add
 * capabilities through the flxr_catalog filter, so neither is a fixed set.
 *
 * @package flx-roles
 */

defined( 'ABSPATH' ) || exit;

/**
 * Service result → value, or the WP_Error as a typed GraphQL error.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxr_gql_ok( $r ) {
	if ( $r instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $r );
	}
	return $r;
}

/**
 * Typed parent value of a field.
 *
 * @template T of object
 * @param mixed           $source Parent.
 * @param class-string<T> $class  Expected class.
 * @return T
 * @throws FLXGQL_Error When the parent has another type.
 */
function flxr_gql_src( $source, string $class ) {
	if ( ! $source instanceof $class ) {
		throw flx_gql_error( 'INTERNAL' );
	}
	return $source;
}

/**
 * The roles, read once per field and tagged for the cache.
 *
 * @return list<FLXR_Role>
 */
function flxr_gql_roles(): array {
	flx_gql_cache_tag( FLXR_Roles_Service::CACHE_TAG );
	return FLXR_Roles_Service::roles();
}

flx_gql_type(
	'FlxRoleCapability',
	array(
		'description' => __( 'One permission of the Roles and access table.', 'flx-roles' ),
		'fields'      => array(
			'key'     => array(
				'type'        => 'String!',
				'description' => __( 'Capability name, e.g. flxg_use.', 'flx-roles' ),
				'resolve'     => static fn( $c ): string => flxr_gql_src( $c, FLXR_Capability::class )->key,
			),
			'label'   => array(
				'type'        => 'String!',
				'description' => __( 'What the permission allows, in the user\'s language.', 'flx-roles' ),
				'resolve'     => static fn( $c ): string => flxr_gql_src( $c, FLXR_Capability::class )->label,
			),
			'locked'  => array(
				'type'        => 'Boolean!',
				'description' => __( 'Always on for every role (cannot be changed).', 'flx-roles' ),
				'resolve'     => static fn( $c ): bool => flxr_gql_src( $c, FLXR_Capability::class )->locked,
			),
			'broad'   => array(
				'type'        => 'Boolean!',
				'description' => __( 'Gives broad access; shown with a warning.', 'flx-roles' ),
				'resolve'     => static fn( $c ): bool => flxr_gql_src( $c, FLXR_Capability::class )->broad,
			),
			'pending' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Saved, but the package enforces it only after an update.', 'flx-roles' ),
				'resolve'     => static fn( $c ): bool => flxr_gql_src( $c, FLXR_Capability::class )->pending,
			),
		),
	)
);

flx_gql_type(
	'FlxRoleCapabilityGroup',
	array(
		'description' => __( 'A package group of the Roles and access table.', 'flx-roles' ),
		'fields'      => array(
			'key'          => array(
				'type'        => 'String!',
				'description' => __( 'Group key.', 'flx-roles' ),
				'resolve'     => static fn( $g ): string => flxr_gql_src( $g, FLXR_Capability_Group::class )->key,
			),
			'label'        => array(
				'type'        => 'String!',
				'description' => __( 'Group name.', 'flx-roles' ),
				'resolve'     => static fn( $g ): string => flxr_gql_src( $g, FLXR_Capability_Group::class )->label,
			),
			'plugin'       => array(
				'type'        => 'String',
				'description' => __( 'Package that enforces the group; empty for General.', 'flx-roles' ),
				'resolve'     => static fn( $g ): ?string => flxr_gql_src( $g, FLXR_Capability_Group::class )->plugin,
			),
			'active'       => array(
				'type'        => 'Boolean!',
				'description' => __( 'Whether the package is installed.', 'flx-roles' ),
				'resolve'     => static fn( $g ): bool => flxr_gql_src( $g, FLXR_Capability_Group::class )->active,
			),
			'capabilities' => array(
				'type'        => '[FlxRoleCapability!]!',
				'description' => __( 'Permissions of the group.', 'flx-roles' ),
				'resolve'     => static fn( $g ): array => flxr_gql_src( $g, FLXR_Capability_Group::class )->capabilities,
			),
		),
	)
);

flx_gql_type(
	'FlxRoleMember',
	array(
		'description' => __( 'A person in a role.', 'flx-roles' ),
		'fields'      => array(
			'databaseId'   => array(
				'type'        => 'Int!',
				'description' => __( 'User ID.', 'flx-roles' ),
				'resolve'     => static fn( $m ): int => flxr_gql_src( $m, FLXR_Member::class )->user_id,
			),
			'name'         => array(
				'type'        => 'String!',
				'description' => __( 'Display name.', 'flx-roles' ),
				'resolve'     => static fn( $m ): string => flxr_gql_src( $m, FLXR_Member::class )->name,
			),
			'email'        => array(
				'type'        => 'String!',
				'description' => __( 'E-mail address.', 'flx-roles' ),
				'resolve'     => static fn( $m ): string => flxr_gql_src( $m, FLXR_Member::class )->email,
			),
			'phone'        => array(
				'type'        => 'FlxPhone',
				'description' => __( 'WhatsApp number; empty when none is saved on the profile.', 'flx-roles' ),
				'resolve'     => static function ( $m ): ?array {
					$p = flxr_gql_src( $m, FLXR_Member::class )->phone;
					return '' === $p ? null : flx_gql_phone( '+' . $p );
				},
			),
			'editUrl'      => array(
				'type'        => 'String!',
				'description' => __( 'Profile edit page.', 'flx-roles' ),
				'resolve'     => static fn( $m ): string => flxr_gql_src( $m, FLXR_Member::class )->edit_url,
			),
			'referralLink' => array(
				'type'        => 'String',
				'description' => __( 'Personal referral link; empty when the person cannot be a referrer.', 'flx-roles' ),
				'resolve'     => static function ( $m ): ?string {
					$l = flxr_gql_src( $m, FLXR_Member::class )->referral_link;
					return '' === $l ? null : $l;
				},
			),
		),
	)
);
flx_gql_connection( 'FlxRoleMember', __( 'A page of the people in a role.', 'flx-roles' ) );

flx_gql_type(
	'FlxRole',
	array(
		'description' => __( 'A FluxWP role: Director, Manager, Staff or a role added from the panel.', 'flx-roles' ),
		'fields'      => array(
			'key'          => array(
				'type'        => 'String!',
				'description' => __( 'Role key, e.g. flx_personel.', 'flx-roles' ),
				'resolve'     => static fn( $r ): string => flxr_gql_src( $r, FLXR_Role::class )->key,
			),
			'label'        => array(
				'type'        => 'String!',
				'description' => __( 'Role name in the user\'s language.', 'flx-roles' ),
				'resolve'     => static fn( $r ): string => flxr_gql_src( $r, FLXR_Role::class )->label,
			),
			'description'  => array(
				'type'        => 'String!',
				'description' => __( 'What the role is for.', 'flx-roles' ),
				'resolve'     => static fn( $r ): string => flxr_gql_src( $r, FLXR_Role::class )->description,
			),
			'custom'       => array(
				'type'        => 'Boolean!',
				'description' => __( 'Added from the panel; can be deleted.', 'flx-roles' ),
				'resolve'     => static fn( $r ): bool => flxr_gql_src( $r, FLXR_Role::class )->custom,
			),
			'memberCount'  => array(
				'type'        => 'Int!',
				'description' => __( 'Number of people with the role.', 'flx-roles' ),
				'resolve'     => static fn( $r ): int => flxr_gql_src( $r, FLXR_Role::class )->member_count,
			),
			'capabilities' => array(
				'type'        => '[String!]!',
				'description' => __( 'Table permissions that are on for the role.', 'flx-roles' ),
				'resolve'     => static fn( $r ): array => flxr_gql_src( $r, FLXR_Role::class )->capabilities,
			),
			'home'         => array(
				'type'        => 'String!',
				'description' => __( 'Screen after login (a key of flxRolesConfig.homeChoices).', 'flx-roles' ),
				'resolve'     => static fn( $r ): string => flxr_gql_src( $r, FLXR_Role::class )->home,
			),
			'members'      => array(
				'type'        => 'FlxRoleMemberConnection!',
				/* translators: %d: largest number of people listed */
				'description' => sprintf( __( 'People in the role by name (the first %d).', 'flx-roles' ), FLXR_Roles_Service::MEMBERS_MAX ),
				'args'        => flx_gql_connection_args(),
				'resolve'     => static function ( $r, array $args ): array {
					$key = flxr_gql_src( $r, FLXR_Role::class )->key;
					return flx_gql_paginate( $args, static fn( int $offset, int $limit ): array => FLXR_Roles_Service::members( $key, $offset, $limit ) );
				},
			),
		),
	)
);

flx_gql_type(
	'FlxRoleHomeChoice',
	array(
		'description' => __( 'A screen a role can open after login.', 'flx-roles' ),
		'fields'      => array(
			'key'   => array(
				'type'        => 'String!',
				'description' => __( 'Choice key (auto, gorev, profile …).', 'flx-roles' ),
				'resolve'     => static fn( $c ): string => flxr_gql_src( $c, FLXR_Home_Choice::class )->key,
			),
			'label' => array(
				'type'        => 'String!',
				'description' => __( 'Screen name.', 'flx-roles' ),
				'resolve'     => static fn( $c ): string => flxr_gql_src( $c, FLXR_Home_Choice::class )->label,
			),
		),
	)
);

flx_gql_type(
	'FlxRolesConfig',
	array(
		'description' => __( 'Settings and links of the Roles and access page.', 'flx-roles' ),
		'fields'      => array(
			'referralDays'   => array(
				'type'        => 'Int!',
				'description' => __( 'Referral link lifetime in days (1–365).', 'flx-roles' ),
				'resolve'     => static fn( $c ): int => flxr_gql_src( $c, FLXR_Config::class )->referral_days,
			),
			'homeChoices'    => array(
				'type'        => '[FlxRoleHomeChoice!]!',
				'description' => __( 'Screens a role can open after login.', 'flx-roles' ),
				'resolve'     => static fn( $c ): array => flxr_gql_src( $c, FLXR_Config::class )->home_choices,
			),
			'newUserUrl'     => array(
				'type'        => 'String!',
				'description' => __( 'Page that creates a user.', 'flx-roles' ),
				'resolve'     => static fn( $c ): string => flxr_gql_src( $c, FLXR_Config::class )->new_user_url,
			),
			'permissionsUrl' => array(
				'type'        => 'String',
				'description' => __( 'Flux Admin permissions tab; empty without Flux Admin.', 'flx-roles' ),
				'resolve'     => static fn( $c ): ?string => flxr_gql_src( $c, FLXR_Config::class )->permissions_url,
			),
		),
	)
);

flx_gql_query(
	'flxRoles',
	array(
		'type'        => '[FlxRole!]!',
		'description' => __( 'Roles Flux Roles manages, broadest first.', 'flx-roles' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): array => flxr_gql_roles(),
	)
);

flx_gql_query(
	'flxRole',
	array(
		'type'        => 'FlxRole',
		'description' => __( 'One role; empty when the key is unknown.', 'flx-roles' ),
		'cap'         => 'manage_options',
		'args'        => array(
			'key' => array(
				'type'        => 'String!',
				'description' => __( 'Role key.', 'flx-roles' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): ?FLXR_Role {
			flx_gql_cache_tag( FLXR_Roles_Service::CACHE_TAG );
			return FLXR_Roles_Service::role( flx_gql_str( $args['key'] ?? '' ) );
		},
	)
);

flx_gql_query(
	'flxRoleCapabilityGroups',
	array(
		'type'        => '[FlxRoleCapabilityGroup!]!',
		'description' => __( 'The Roles and access table: permissions by package.', 'flx-roles' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXR_Roles_Service::CACHE_TAG );
			return FLXR_Roles_Service::groups();
		},
	)
);

flx_gql_query(
	'flxRolesConfig',
	array(
		'type'        => 'FlxRolesConfig!',
		'description' => __( 'Settings and links of the Roles and access page.', 'flx-roles' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): FLXR_Config {
			flx_gql_cache_tag( FLXR_Roles_Service::CACHE_TAG );
			return FLXR_Roles_Service::config();
		},
	)
);

/*
 * The viewer's own role and referral link (FluxWP app, staff screens). Visitors get null.
 */
flx_gql_field(
	'FlxViewer',
	'roleKey',
	array(
		'type'        => 'String',
		'description' => __( 'The viewer\'s FluxWP role key; empty without one.', 'flx-roles' ),
		'cap'         => 'public',
		'resolve'     => static function (): ?string {
			$r = is_user_logged_in() ? flxr_user_role() : '';
			return is_string( $r ) && '' !== $r ? $r : null;
		},
	)
);
flx_gql_field(
	'FlxViewer',
	'roleLabel',
	array(
		'type'        => 'String',
		'description' => __( 'Name of the viewer\'s FluxWP role; empty without one.', 'flx-roles' ),
		'cap'         => 'public',
		'resolve'     => static function (): ?string {
			$r    = is_user_logged_in() ? flxr_user_role() : '';
			$role = is_string( $r ) && '' !== $r ? FLXR_Roles_Service::role( $r ) : null;
			return $role ? $role->label : null;
		},
	)
);
flx_gql_field(
	'FlxViewer',
	'referralLink',
	array(
		'type'        => 'String',
		'description' => __( 'The viewer\'s personal referral link; empty when they cannot be a referrer.', 'flx-roles' ),
		'cap'         => 'public',
		'resolve'     => static function (): ?string {
			if ( ! is_user_logged_in() || ! current_user_can( FLXR_CAP_REF ) ) {
				return null;
			}
			$l = flxr_ref_link( get_current_user_id() );
			return is_string( $l ) && '' !== $l ? $l : null;
		},
	)
);

flx_gql_input(
	'FlxRolesSaveRoleInput',
	array(
		'description' => __( 'Changes to one role. Fields you leave out stay as they are.', 'flx-roles' ),
		'fields'      => array(
			'key'          => array(
				'type'        => 'String!',
				'description' => __( 'Role key.', 'flx-roles' ),
			),
			'capabilities' => array(
				'type'        => '[String!]',
				'description' => __( 'Every table permission that should be on; the others are turned off (locked ones stay).', 'flx-roles' ),
			),
			'home'         => array(
				'type'        => 'String',
				'description' => __( 'Screen after login (a key of flxRolesConfig.homeChoices).', 'flx-roles' ),
			),
		),
	)
);

/**
 * Typed role rows of flxRolesSave.
 *
 * @param mixed $raw Input list.
 * @return list<array{key: string, capabilities?: list<string>|null, home?: string|null}>
 */
function flxr_gql_save_roles( $raw ): array {
	$out = array();
	foreach ( is_array( $raw ) ? $raw : array() as $r ) {
		$r   = flx_gql_arr( $r );
		$row = array( 'key' => flx_gql_str( $r['key'] ?? '' ) );
		if ( isset( $r['capabilities'] ) && is_array( $r['capabilities'] ) ) {
			$row['capabilities'] = array_values( array_map( 'flx_gql_str', $r['capabilities'] ) );
		}
		if ( isset( $r['home'] ) ) {
			$row['home'] = flx_gql_str( $r['home'] );
		}
		$out[] = $row;
	}
	return $out;
}

flx_gql_mutation(
	'flxRolesSave',
	array(
		'description' => __( 'Saves the Roles and access table and settings in one go. Nothing is written when any part is invalid.', 'flx-roles' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'roles'        => array(
				'type'        => '[FlxRolesSaveRoleInput!]',
				'description' => __( 'Roles to change; the others stay as they are.', 'flx-roles' ),
			),
			'referralDays' => array(
				'type'        => 'Int',
				'description' => __( 'Referral link lifetime in days (1–365).', 'flx-roles' ),
			),
		),
		'output'      => array(
			'roles'  => array(
				'type'        => '[FlxRole!]!',
				'description' => __( 'Every role after the change.', 'flx-roles' ),
				'resolve'     => static fn(): array => flxr_gql_roles(),
			),
			'config' => array(
				'type'        => 'FlxRolesConfig!',
				'description' => __( 'Page settings after the change.', 'flx-roles' ),
				'resolve'     => static fn(): FLXR_Config => FLXR_Roles_Service::config(),
			),
		),
		'resolve'     => static function ( array $input ) {
			$days = array_key_exists( 'referralDays', $input ) && null !== $input['referralDays'] ? flx_gql_int( $input['referralDays'] ) : null;
			$r    = FLXR_Roles_Service::save( flxr_gql_save_roles( $input['roles'] ?? array() ), $days );
			return $r instanceof WP_Error ? $r : array();
		},
	)
);

flx_gql_mutation(
	'flxRoleCreate',
	array(
		'description' => __( 'Adds a role from the panel; it starts with admin panel access only.', 'flx-roles' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'name' => array(
				'type'        => 'String!',
				'description' => __( 'Role name (1–40 characters), e.g. Volunteer.', 'flx-roles' ),
			),
		),
		'output'      => array(
			'role' => array(
				'type'        => 'FlxRole',
				'description' => __( 'The new role.', 'flx-roles' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXR_Roles_Service::create_role( flx_gql_str( $input['name'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'role' => $r );
		},
	)
);

flx_gql_mutation(
	'flxRoleDelete',
	array(
		'description' => __( 'Deletes a role added from the panel; its people move to the default role.', 'flx-roles' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'key' => array(
				'type'        => 'String!',
				'description' => __( 'Role key.', 'flx-roles' ),
			),
		),
		'output'      => array(
			'deletedKey' => array(
				'type'        => 'String',
				'description' => __( 'Key of the deleted role.', 'flx-roles' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$key = flx_gql_str( $input['key'] ?? '' );
			$r   = FLXR_Roles_Service::delete_role( $key );
			return $r instanceof WP_Error ? $r : array( 'deletedKey' => $key );
		},
	)
);

flx_gql_mutation(
	'flxRoleReset',
	array(
		'description' => __( 'Puts a role\'s table permissions back to the defaults.', 'flx-roles' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'key' => array(
				'type'        => 'String!',
				'description' => __( 'Role key.', 'flx-roles' ),
			),
		),
		'output'      => array(
			'role' => array(
				'type'        => 'FlxRole',
				'description' => __( 'The role after the reset.', 'flx-roles' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXR_Roles_Service::reset_role( flx_gql_str( $input['key'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'role' => $r );
		},
	)
);

flx_gql_mutation(
	'flxRoleMemberAdd',
	array(
		'description' => __( 'Gives a user the role. Their other FluxWP role and Subscriber/Customer are removed; other roles stay.', 'flx-roles' ),
		'cap'         => flx_gql_all_caps( 'manage_options', 'promote_users' ),
		'input'       => array(
			'role'  => array(
				'type'        => 'String!',
				'description' => __( 'Role key.', 'flx-roles' ),
			),
			'login' => array(
				'type'        => 'String!',
				'description' => __( 'Username or email of an existing user.', 'flx-roles' ),
			),
		),
		'output'      => array(
			'member' => array(
				'type'        => 'FlxRoleMember',
				'description' => __( 'The person added.', 'flx-roles' ),
			),
			'roles'  => array(
				'type'        => '[FlxRole!]!',
				'description' => __( 'Every role after the change (counts).', 'flx-roles' ),
				'resolve'     => static fn(): array => flxr_gql_roles(),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXR_Roles_Service::add_member( flx_gql_str( $input['role'] ?? '' ), flx_gql_str( $input['login'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'member' => $r );
		},
	)
);

flx_gql_mutation(
	'flxRoleMemberRemove',
	array(
		'description' => __( 'Takes the role from a user; with no role left they get the default role.', 'flx-roles' ),
		'cap'         => flx_gql_all_caps( 'manage_options', 'promote_users' ),
		'input'       => array(
			'role'   => array(
				'type'        => 'String!',
				'description' => __( 'Role key.', 'flx-roles' ),
			),
			'userId' => array(
				'type'        => 'Int!',
				'description' => __( 'User ID.', 'flx-roles' ),
			),
		),
		'output'      => array(
			'removedUserId' => array(
				'type'        => 'Int',
				'description' => __( 'User the role was taken from.', 'flx-roles' ),
			),
			'roles'         => array(
				'type'        => '[FlxRole!]!',
				'description' => __( 'Every role after the change (counts).', 'flx-roles' ),
				'resolve'     => static fn(): array => flxr_gql_roles(),
			),
		),
		'resolve'     => static function ( array $input ) {
			$id = flx_gql_int( $input['userId'] ?? 0 );
			$r  = FLXR_Roles_Service::remove_member( flx_gql_str( $input['role'] ?? '' ), $id );
			return $r instanceof WP_Error ? $r : array( 'removedUserId' => $id );
		},
	)
);
