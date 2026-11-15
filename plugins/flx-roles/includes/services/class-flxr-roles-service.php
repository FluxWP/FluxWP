<?php
/**
 * Roles and access: the one place that reads and changes FluxWP roles, their capabilities, their people
 * and the page settings. The classic page (admin-post handlers), GraphQL and Flux AI call this.
 * Permission checks stay with the caller (manage_options; promote_users for people), as before.
 *
 * Errors are WP_Error with `graphql_code` INVALID_INPUT / NOT_FOUND and the input `field`.
 *
 * @package flx-roles
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxrSaveRole array{key: string, capabilities?: list<string>|null, home?: string|null}
 */
final class FLXR_Roles_Service {

	public const CACHE_TAG = 'flx-roles:roles';

	/** Highest number of people a role lists (classic page limit). */
	public const MEMBERS_MAX = 300;

	/**
	 * Role keys in priority order.
	 *
	 * @return list<string>
	 */
	public static function role_keys(): array {
		return array_map( 'strval', array_keys( self::raw_roles() ) );
	}

	/**
	 * Every role Flux Roles manages.
	 *
	 * @return list<FLXR_Role>
	 */
	public static function roles(): array {
		$counts = count_users();
		$avail  = $counts['avail_roles'];
		$out    = array();
		foreach ( self::role_keys() as $key ) {
			$out[] = self::build_role( $key, is_numeric( $avail[ $key ] ?? null ) ? (int) $avail[ $key ] : 0 );
		}
		return $out;
	}

	public static function role( string $key ): ?FLXR_Role {
		if ( ! in_array( $key, self::role_keys(), true ) ) {
			return null;
		}
		foreach ( self::roles() as $r ) {
			if ( $r->key === $key ) {
				return $r;
			}
		}
		return null;
	}

	/**
	 * Capability table groups.
	 *
	 * @return list<FLXR_Capability_Group>
	 */
	public static function groups(): array {
		$out = array();
		foreach ( self::raw_catalog() as $gkey => $g ) {
			$active = ! empty( $g['active'] );
			$caps   = array();
			foreach ( self::group_caps( $g ) as $cap => $c ) {
				$caps[] = new FLXR_Capability(
					$cap,
					self::str( $c['label'] ?? $cap ),
					! empty( $c['locked'] ),
					! empty( $c['warn'] ),
					$active && ! empty( $c['new'] ) && ! flxr_cap_supported( $cap ),
				);
			}
			$plugin = self::str( $g['plugin'] ?? '' );
			$out[]  = new FLXR_Capability_Group( (string) $gkey, self::str( $g['label'] ?? $gkey ), '' !== $plugin ? $plugin : null, $active, $caps );
		}
		return $out;
	}

	/**
	 * Every catalog capability: [ cap => [ label, group label ] ].
	 *
	 * @return array<string,array{label:string,group:string}>
	 */
	public static function capability_labels(): array {
		$out = array();
		foreach ( self::raw_catalog() as $g ) {
			foreach ( self::group_caps( $g ) as $cap => $c ) {
				$out[ $cap ] = array(
					'label' => self::str( $c['label'] ?? $cap ),
					'group' => self::str( $g['label'] ?? '' ),
				);
			}
		}
		return $out;
	}

	public static function config(): FLXR_Config {
		$choices = array();
		foreach ( self::raw_home_choices() as $k => $ch ) {
			$choices[] = new FLXR_Home_Choice( $k, $ch );
		}
		return new FLXR_Config(
			self::referral_days(),
			$choices,
			admin_url( 'user-new.php' ),
			defined( 'FLXA_VERSION' ) ? admin_url( 'options-general.php?page=flx-admin&tab=caps' ) : null,
		);
	}

	/**
	 * People in a role, by display name.
	 *
	 * @return array{items: list<FLXR_Member>, total: int}
	 */
	public static function members( string $role, int $offset, int $limit ): array {
		if ( ! in_array( $role, self::role_keys(), true ) ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}
		$offset = max( 0, min( $offset, self::MEMBERS_MAX ) );
		$limit  = max( 0, min( $limit, self::MEMBERS_MAX - $offset ) );
		$q      = new WP_User_Query(
			array(
				'role'        => $role,
				'orderby'     => 'display_name',
				'order'       => 'ASC',
				'number'      => max( 1, $limit ),
				'offset'      => $offset,
				'count_total' => true,
			)
		);
		$items = array();
		foreach ( $limit > 0 ? (array) $q->get_results() : array() as $u ) {
			if ( $u instanceof WP_User ) {
				$items[] = self::member( $u );
			}
		}
		return array(
			'items' => $items,
			'total' => min( self::MEMBERS_MAX, $q->get_total() ),
		);
	}

	public static function member( WP_User $u ): FLXR_Member {
		$phone = self::str( flxr_user_phone( $u->ID ) );
		$link  = user_can( $u, FLXR_CAP_REF ) ? self::str( flxr_ref_link( $u->ID ) ) : '';
		return new FLXR_Member( (int) $u->ID, $u->display_name, $u->user_email, $phone, self::str( get_edit_user_link( $u->ID ) ), $link );
	}

	/**
	 * Saves the table and settings in one go. Only the roles given change; for each one, `capabilities`
	 * is the full list of catalog capabilities that are on (locked ones are left alone), `home` the screen
	 * after login. `referral_days` (1–365) is left alone when null. Nothing is written when any part is wrong.
	 *
	 * @param list<FlxrSaveRole> $roles         Roles.
	 * @param int|null           $referral_days Referral link lifetime.
	 * @return true|WP_Error
	 */
	public static function save( array $roles, ?int $referral_days = null ) {
		$keys    = self::role_keys();
		$caps    = self::capability_labels();
		$choices = self::raw_home_choices();
		foreach ( $roles as $i => $r ) {
			if ( ! in_array( $r['key'], $keys, true ) ) {
				return self::invalid( __( 'Unknown role.', 'flx-roles' ), 'roles.' . $i . '.key' );
			}
			foreach ( $r['capabilities'] ?? array() as $cap ) {
				if ( ! isset( $caps[ $cap ] ) ) {
					/* translators: %s: capability name */
					return self::invalid( sprintf( __( 'Unknown permission: %s.', 'flx-roles' ), $cap ), 'roles.' . $i . '.capabilities' );
				}
			}
			if ( isset( $r['home'] ) && ! isset( $choices[ $r['home'] ] ) ) {
				return self::invalid( __( 'This screen is not available.', 'flx-roles' ), 'roles.' . $i . '.home' );
			}
		}
		if ( null !== $referral_days && ( $referral_days < 1 || $referral_days > 365 ) ) {
			return self::invalid( __( 'Enter 1 to 365 days.', 'flx-roles' ), 'referralDays' );
		}

		$locked = array();
		foreach ( self::raw_catalog() as $g ) {
			foreach ( self::group_caps( $g ) as $cap => $c ) {
				if ( ! empty( $c['locked'] ) ) {
					$locked[ $cap ] = true;
				}
			}
		}
		$settings = (array) flxr_settings();
		$home     = is_array( $settings['home'] ?? null ) ? $settings['home'] : array();
		foreach ( $roles as $r ) {
			if ( isset( $r['capabilities'] ) ) {
				foreach ( array_keys( $caps ) as $cap ) {
					if ( ! isset( $locked[ $cap ] ) ) {
						flxr_set_role_cap( $r['key'], $cap, in_array( $cap, $r['capabilities'], true ) );
					}
				}
			}
			if ( isset( $r['home'] ) ) {
				$home[ $r['key'] ] = $r['home'];
			}
		}
		$settings['home'] = $home;
		if ( null !== $referral_days ) {
			$settings['ref_days'] = $referral_days;
		}
		update_option( 'flxr_settings', $settings, false );
		self::changed();
		return true;
	}

	/**
	 * Adds a role from the panel (starts with admin panel access only).
	 *
	 * @return FLXR_Role|WP_Error
	 */
	public static function create_role( string $name ) {
		$key = flxr_add_custom_role( $name );
		if ( $key instanceof WP_Error ) {
			return self::invalid( $key->get_error_message(), 'name' );
		}
		self::changed();
		return self::role( self::str( $key ) ) ?? self::not_found();
	}

	/**
	 * Deletes a role added from the panel; its people move to the default role.
	 *
	 * @return true|WP_Error
	 */
	public static function delete_role( string $key ) {
		if ( ! self::is_custom( $key ) ) {
			return self::invalid( __( 'Only roles added from the panel can be deleted.', 'flx-roles' ), 'key' );
		}
		flxr_delete_custom_role( $key );
		self::changed();
		return true;
	}

	/**
	 * Back to the default permissions.
	 *
	 * @return FLXR_Role|WP_Error
	 */
	public static function reset_role( string $key ) {
		if ( ! in_array( $key, self::role_keys(), true ) ) {
			return self::invalid( __( 'Unknown role.', 'flx-roles' ), 'key' );
		}
		flxr_reset_role( $key );
		self::changed();
		return self::role( $key ) ?? self::not_found();
	}

	/**
	 * Gives a user (username or e-mail) the role. Other FluxWP roles and subscriber/customer are removed;
	 * other roles (e.g. Call Center agent) stay.
	 *
	 * @return FLXR_Member|WP_Error
	 */
	public static function add_member( string $role, string $who ) {
		if ( ! in_array( $role, self::role_keys(), true ) ) {
			return self::invalid( __( 'Unknown role.', 'flx-roles' ), 'role' );
		}
		$who = trim( $who );
		$u   = is_email( $who ) ? get_user_by( 'email', $who ) : get_user_by( 'login', $who );
		if ( ! $u instanceof WP_User ) {
			return self::invalid( __( 'No user with this username or email. Create the user first.', 'flx-roles' ), 'login' );
		}
		if ( user_can( $u, 'manage_options' ) ) {
			return self::invalid( __( 'Administrators can\'t get this role; they already see everything.', 'flx-roles' ), 'login' );
		}
		foreach ( self::role_keys() as $other ) {
			if ( $other !== $role ) {
				$u->remove_role( $other );
			}
		}
		foreach ( array( 'subscriber', 'customer' ) as $low ) {
			$u->remove_role( $low );
		}
		$u->add_role( $role );
		self::changed();
		return self::member( $u );
	}

	/**
	 * Takes the role from a user; with no role left they get the default role.
	 *
	 * @return true|WP_Error
	 */
	public static function remove_member( string $role, int $user_id ) {
		if ( ! in_array( $role, self::role_keys(), true ) ) {
			return self::invalid( __( 'Unknown role.', 'flx-roles' ), 'role' );
		}
		$u = get_userdata( $user_id );
		if ( ! $u instanceof WP_User ) {
			return self::not_found();
		}
		$u->remove_role( $role );
		if ( ! $u->roles ) {
			$u->add_role( self::str( get_option( 'default_role', 'subscriber' ) ) );
		}
		self::changed();
		return true;
	}

	public static function referral_days(): int {
		$v = flxr_setting( 'ref_days' );
		return max( 1, min( 365, is_numeric( $v ) ? (int) $v : 30 ) );
	}

	public static function is_custom( string $key ): bool {
		$r = self::raw_roles()[ $key ] ?? null;
		return is_array( $r ) && ! empty( $r['custom'] );
	}

	/* ------------------------------------------------------------------ internals */

	private static function build_role( string $key, int $count ): FLXR_Role {
		$r    = self::raw_roles()[ $key ] ?? array();
		$on   = array();
		foreach ( array_keys( self::capability_labels() ) as $cap ) {
			if ( flxr_role_has( $key, $cap ) ) {
				$on[] = $cap;
			}
		}
		$settings = (array) flxr_settings();
		$home     = is_array( $settings['home'] ?? null ) ? $settings['home'] : array();
		$want     = self::str( $home[ $key ] ?? 'auto' );
		return new FLXR_Role(
			$key,
			self::str( $r['label'] ?? $key ),
			self::str( $r['desc'] ?? '' ),
			! empty( $r['custom'] ),
			$count,
			$on,
			isset( self::raw_home_choices()[ $want ] ) ? $want : 'auto',
		);
	}

	/**
	 * flxr_roles() with array rows.
	 *
	 * @return array<string,array<mixed>>
	 */
	private static function raw_roles(): array {
		$out = array();
		foreach ( (array) flxr_roles() as $k => $r ) {
			if ( is_array( $r ) ) {
				$out[ (string) $k ] = $r;
			}
		}
		return $out;
	}

	/**
	 * flxr_catalog() with array rows.
	 *
	 * @return array<string,array<mixed>>
	 */
	private static function raw_catalog(): array {
		$out = array();
		foreach ( (array) flxr_catalog() as $k => $g ) {
			if ( is_array( $g ) ) {
				$out[ (string) $k ] = $g;
			}
		}
		return $out;
	}

	/**
	 * Capabilities of a catalog group.
	 *
	 * @param array<mixed> $g Group.
	 * @return array<string,array<mixed>>
	 */
	private static function group_caps( array $g ): array {
		$out = array();
		foreach ( is_array( $g['caps'] ?? null ) ? $g['caps'] : array() as $cap => $c ) {
			$out[ (string) $cap ] = is_array( $c ) ? $c : array();
		}
		return $out;
	}

	/**
	 * Home choices: [ key => label ].
	 *
	 * @return array<string,string>
	 */
	private static function raw_home_choices(): array {
		$out = array();
		foreach ( (array) flxr_home_choices() as $k => $ch ) {
			$out[ (string) $k ] = is_array( $ch ) ? self::str( $ch[0] ?? $k ) : (string) $k;
		}
		return $out;
	}

	private static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE_TAG ) );
		}
	}

	private static function invalid( string $message, string $field ): WP_Error {
		return new WP_Error(
			'flxr_invalid',
			$message,
			array(
				'graphql_code' => 'INVALID_INPUT',
				'field'        => $field,
				'status'       => 400,
			)
		);
	}

	private static function not_found(): WP_Error {
		return new WP_Error(
			'flxr_not_found',
			__( 'Not found.', 'flx-roles' ),
			array(
				'graphql_code' => 'NOT_FOUND',
				'status'       => 404,
			)
		);
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function str( $v ): string {
		return is_scalar( $v ) ? (string) $v : '';
	}
}
