<?php
/**
 * Settings › Admin theme › Permissions: the role × permission table. Changes here are real WordPress
 * permissions (add_cap / remove_cap), not menu hiding. The Administrator column cannot change. The first
 * change backs up every role (`flxa_caps_backup`); "Restore backup" returns to that state.
 * The classic page (admin-post handlers), GraphQL and Flux AI call this; manage_options stays with the caller.
 *
 * @package flx-admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxaCapRole array{key: string, label: string, users: int, is_admin: bool, granted: list<string>}
 * @phpstan-type FlxaCapRow array{key: string, label: string}
 * @phpstan-type FlxaCapGroup array{key: string, label: string, caps: list<FlxaCapRow>}
 */
final class FLXA_Caps_Service {

	public const CACHE_TAG = 'flx-admin:caps';

	/**
	 * Permission groups of the table.
	 *
	 * @return list<FlxaCapGroup>
	 */
	public static function groups(): array {
		$out = array();
		foreach ( flxa_caps_rows() as $key => $g ) {
			$caps = array();
			foreach ( FLXA_Util::arr( $g['caps'] ?? array() ) as $cap => $label ) {
				$caps[] = array(
					'key'   => (string) $cap,
					'label' => FLXA_Util::str( $label ),
				);
			}
			$out[] = array(
				'key'   => (string) $key,
				'label' => FLXA_Util::str( $g['label'] ?? '' ),
				'caps'  => $caps,
			);
		}
		return $out;
	}

	/**
	 * Every permission the table shows.
	 *
	 * @return list<string>
	 */
	public static function shown(): array {
		$out = array();
		foreach ( self::groups() as $g ) {
			foreach ( $g['caps'] as $c ) {
				$out[] = $c['key'];
			}
		}
		return $out;
	}

	/**
	 * Roles with the shown permissions each has.
	 *
	 * @return list<FlxaCapRole>
	 */
	public static function roles(): array {
		$counts = count_users();
		$avail  = $counts['avail_roles'];
		$shown  = self::shown();
		$out    = array();
		foreach ( wp_roles()->roles as $key => $r ) {
			$have = FLXA_Util::arr( $r['capabilities'] ?? array() );
			$on   = array();
			foreach ( $shown as $cap ) {
				if ( ! empty( $have[ $cap ] ) ) {
					$on[] = $cap;
				}
			}
			$out[] = array(
				'key'      => (string) $key,
				'label'    => (string) translate_user_role( FLXA_Util::str( $r['name'] ?? $key ) ),
				'users'    => FLXA_Util::int( $avail[ $key ] ?? 0 ),
				'is_admin' => 'administrator' === $key,
				'granted'  => $on,
			);
		}
		return $out;
	}

	/**
	 * Time of the backup taken before the first change; null without a backup.
	 */
	public static function backup_time(): ?int {
		$b = get_option( 'flxa_caps_backup' );
		return is_array( $b ) ? FLXA_Util::int( $b['_time'] ?? 0 ) : null;
	}

	/**
	 * Turns permissions on for one role among `$shown` (the others of `$shown` are turned off).
	 * The first change backs everything up. The Administrator role is skipped.
	 *
	 * @param string       $role  Role key.
	 * @param list<string> $on    Permissions that should be on.
	 * @param list<string> $shown Permissions this call may change.
	 */
	public static function apply( string $role, array $on, array $shown ): void {
		self::backup();
		$obj = 'administrator' === $role ? null : get_role( $role );
		if ( ! $obj ) {
			return;
		}
		foreach ( $shown as $cap ) {
			$want = in_array( $cap, $on, true );
			$has  = ! empty( $obj->capabilities[ $cap ] );
			if ( $want && ! $has ) {
				$obj->add_cap( $cap );
			} elseif ( ! $want && $has ) {
				$obj->remove_cap( $cap );
			}
		}
		FLXA_Util::purge( array( self::CACHE_TAG ) );
	}

	/**
	 * Saves the table for some roles; roles you leave out stay as they are.
	 *
	 * @param list<array{role: string, capabilities: list<string>}> $roles Role rows.
	 * @return list<FlxaCapRole>|WP_Error
	 */
	public static function save( array $roles ) {
		$shown = self::shown();
		$known = wp_roles()->roles;
		foreach ( $roles as $i => $r ) {
			if ( ! isset( $known[ $r['role'] ] ) ) {
				return FLXA_Util::error( 'flxa_caps_role', __( 'This role does not exist.', 'flx-admin' ), 'INVALID_INPUT', 'roles.' . $i . '.role' );
			}
			foreach ( $r['capabilities'] as $cap ) {
				if ( ! in_array( $cap, $shown, true ) ) {
					return FLXA_Util::error( 'flxa_caps_cap', __( 'This permission is not in the table.', 'flx-admin' ), 'INVALID_INPUT', 'roles.' . $i . '.capabilities' );
				}
			}
		}
		foreach ( $roles as $r ) {
			self::apply( $r['role'], $r['capabilities'], $shown );
		}
		return self::roles();
	}

	/**
	 * Returns every role to the backup taken before the first change.
	 *
	 * @return list<FlxaCapRole>|WP_Error
	 */
	public static function restore() {
		$b = get_option( 'flxa_caps_backup' );
		if ( ! is_array( $b ) ) {
			return FLXA_Util::error( 'flxa_caps_nobackup', __( 'There is no backup to restore.', 'flx-admin' ), 'NOT_FOUND' );
		}
		foreach ( $b as $rk => $caps ) {
			$role = '_time' === $rk ? null : get_role( (string) $rk );
			if ( ! $role || 'administrator' === $rk ) {
				continue;
			}
			$caps = FLXA_Util::arr( $caps );
			foreach ( array_keys( $role->capabilities ) as $cap ) {
				if ( ! isset( $caps[ $cap ] ) ) {
					$role->remove_cap( (string) $cap );
				}
			}
			foreach ( $caps as $cap => $on ) {
				$role->add_cap( $cap, (bool) $on );
			}
		}
		delete_option( 'flxa_caps_backup' );
		FLXA_Util::purge( array( self::CACHE_TAG ) );
		return self::roles();
	}

	/**
	 * Backs every role up once (before the first change).
	 */
	private static function backup(): void {
		if ( is_array( get_option( 'flxa_caps_backup' ) ) ) {
			return;
		}
		$b = array( '_time' => time() );
		foreach ( wp_roles()->roles as $rk => $r ) {
			$b[ $rk ] = (array) ( $r['capabilities'] ?? array() );
		}
		update_option( 'flxa_caps_backup', $b, false );
	}
}
