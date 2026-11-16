<?php
/**
 * Side menu: the role layouts of Settings › Admin theme › Menu and bottom bar, and the personal
 * pin / move lists of each user. The classic page, the right-click menu (admin-ajax), GraphQL and
 * Flux AI call this. Permission checks stay with the caller (manage_options for layouts; any signed-in
 * user for their own lists).
 *
 * Stored layout (option `flxa_menus`): { roles: { <role|_default>: { entries: [ { t:'g', id, label, c } |
 * { t:'i', slug, label?, hidden?, blocked?, sub_hidden?, pf_off? } ], bottom: [ slug × 4 ] } } }.
 * Here every entry has the same shape (`type` group|item).
 *
 * @package flx-admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxaSub array{slug: string, label: string, cap: string}
 * @phpstan-type FlxaMenuItem array{slug: string, label: string, cap: string, icon: string, url: string, sub: list<FlxaSub>}
 * @phpstan-type FlxaEntry array{type: string, label: string, collapsed: bool, slug: string, hidden: bool, blocked: bool, sub_hidden: list<string>, platforms_off: list<string>}
 * @phpstan-type FlxaLayout array{role: string, custom: bool, entries: list<FlxaEntry>, bottom: list<string>, access: list<string>}
 * @phpstan-type FlxaMenuRole array{key: string, label: string, users: int}
 * @phpstan-type FlxaUserMenu array{pins: list<string>, away: list<string>, sub_away: list<string>, sub_keep: list<string>}
 */
final class FLXA_Menu_Service {

	public const CACHE_TAG = 'flx-admin:menu';

	/**
	 * Menu items the administrator sees (snapshot taken on admin pages).
	 *
	 * @return list<FlxaMenuItem>
	 */
	public static function catalog(): array {
		$out = array();
		foreach ( flxa_menu_catalog() as $raw ) {
			$it   = FLXA_Util::arr( $raw );
			$subs = array();
			foreach ( is_array( $it['sub'] ?? null ) ? $it['sub'] : array() as $sraw ) {
				$s      = FLXA_Util::arr( $sraw );
				$subs[] = array(
					'slug'  => FLXA_Util::str( $s['slug'] ?? '' ),
					'label' => FLXA_Util::str( $s['label'] ?? '' ),
					'cap'   => FLXA_Util::str( $s['cap'] ?? '' ),
				);
			}
			$out[] = array(
				'slug'  => FLXA_Util::str( $it['slug'] ?? '' ),
				'label' => FLXA_Util::str( $it['label'] ?? '' ),
				'cap'   => FLXA_Util::str( $it['cap'] ?? '' ),
				'icon'  => FLXA_Util::str( $it['icon'] ?? '' ),
				'url'   => FLXA_Util::str( $it['url'] ?? '' ),
				'sub'   => $subs,
			);
		}
		return $out;
	}

	/**
	 * Roles of the editor ("All other roles" first).
	 *
	 * @return list<FlxaMenuRole>
	 */
	public static function roles(): array {
		$counts = count_users();
		$avail  = $counts['avail_roles'];
		$out    = array(
			array(
				'key'   => '_default',
				'label' => __( 'All other roles', 'flx-admin' ),
				'users' => 0,
			),
		);
		foreach ( flxa_roles() as $key => $label ) {
			$out[] = array(
				'key'   => (string) $key,
				'label' => (string) $label,
				'users' => FLXA_Util::int( $avail[ $key ] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * Platforms an item can be hidden on (the app package registers them).
	 *
	 * @return list<array{key: string, label: string}>
	 */
	public static function platforms(): array {
		$out = array();
		foreach ( flxa_menu_platforms() as $key => $label ) {
			$out[] = array(
				'key'   => (string) $key,
				'label' => (string) $label,
			);
		}
		return $out;
	}

	/**
	 * Menus that cannot be blocked (no lock-out).
	 *
	 * @return list<string>
	 */
	public static function unblockable(): array {
		return flxa_unblockable();
	}

	/**
	 * Layout of every role: the saved one, or the default layout (`custom` false).
	 *
	 * @return list<FlxaLayout>
	 */
	public static function layouts(): array {
		$saved = FLXA_Util::arr( flxa_menus_option()['roles'] ?? array() );
		$out   = array();
		foreach ( self::roles() as $role ) {
			$key    = $role['key'];
			$cfg    = FLXA_Util::arr( $saved[ $key ] ?? array() );
			$custom = ! empty( $cfg['entries'] ) && is_array( $cfg['entries'] );
			$out[]  = array(
				'role'    => $key,
				'custom'  => $custom,
				'entries' => self::normalize( $custom ? self::raw_list( $cfg['entries'] ) : self::raw_list( self::default_entries() ) ),
				'bottom'  => self::clean_bottom( ! empty( $cfg['bottom'] ) ? FLXA_Util::strings( $cfg['bottom'] ) : self::default_bottom() ),
				'access'  => self::access( $key ),
			);
		}
		return $out;
	}

	/**
	 * The default layout and bottom bar ("reset to default").
	 *
	 * @return array{entries: list<FlxaEntry>, bottom: list<string>}
	 */
	public static function defaults(): array {
		return array(
			'entries' => self::normalize( self::raw_list( self::default_entries() ) ),
			'bottom'  => self::clean_bottom( self::default_bottom() ),
		);
	}

	/**
	 * Saves layouts for some roles and resets others to default; roles you leave out stay as they are.
	 *
	 * @param list<array{role: string, entries: list<array<string,mixed>>, bottom?: list<string>}> $layouts Layouts to save.
	 * @param list<string>                                                                          $reset   Roles that go back to the default.
	 * @return list<FlxaLayout>|WP_Error
	 */
	public static function save( array $layouts, array $reset = array() ) {
		$valid = array_column( self::roles(), 'key' );
		$raw   = FLXA_Util::arr( flxa_menus_option()['roles'] ?? array() );
		foreach ( $reset as $i => $role ) {
			if ( ! in_array( $role, $valid, true ) ) {
				return FLXA_Util::error( 'flxa_menu_role', __( 'This role does not exist.', 'flx-admin' ), 'INVALID_INPUT', 'reset.' . $i );
			}
			unset( $raw[ $role ] );
		}
		foreach ( $layouts as $i => $l ) {
			if ( ! in_array( $l['role'], $valid, true ) ) {
				return FLXA_Util::error( 'flxa_menu_role', __( 'This role does not exist.', 'flx-admin' ), 'INVALID_INPUT', 'layouts.' . $i . '.role' );
			}
			if ( ! $l['entries'] ) {
				return FLXA_Util::error( 'flxa_menu_empty', __( 'A layout needs at least one menu item.', 'flx-admin' ), 'INVALID_INPUT', 'layouts.' . $i . '.entries' );
			}
			$raw[ $l['role'] ] = array(
				'entries' => array_map( array( self::class, 'to_raw' ), $l['entries'] ),
				'bottom'  => $l['bottom'] ?? array(),
			);
		}
		self::save_raw( $raw );
		return self::layouts();
	}

	/**
	 * Saves the stored shape as is (cleaned): the classic editor posts every custom role at once.
	 *
	 * @param array<string,mixed> $raw { role: { entries, bottom } }.
	 */
	public static function save_raw( array $raw ): void {
		update_option( 'flxa_menus', array( 'roles' => self::sanitize( $raw ) ), true );
		FLXA_Util::purge( array( self::CACHE_TAG ) );
	}

	/**
	 * Cleans a layout from the editor: known roles, items that are in the menu, short names.
	 *
	 * @param array<string,mixed> $raw { role: { entries, bottom } }.
	 * @return array<string,array{entries: list<array<string,mixed>>, bottom: list<string>}>
	 */
	public static function sanitize( array $raw ): array {
		$roles = array_merge( array( '_default' ), array_keys( flxa_roles() ) );
		$known = array();
		foreach ( self::catalog() as $it ) {
			$known[ $it['slug'] ] = array_column( $it['sub'], 'slug' );
		}
		$platforms = array_keys( flxa_menu_platforms() );
		$out       = array();
		foreach ( $raw as $role => $cfg ) {
			$cfg = FLXA_Util::arr( $cfg );
			if ( ! in_array( $role, $roles, true ) || empty( $cfg['entries'] ) || ! is_array( $cfg['entries'] ) ) {
				continue;
			}
			$entries = array();
			$seen    = array();
			$gn      = 0;
			foreach ( array_slice( $cfg['entries'], 0, 300 ) as $e ) {
				if ( ! is_array( $e ) ) {
					continue;
				}
				$e = FLXA_Util::arr( $e );
				if ( 'g' === ( $e['t'] ?? '' ) ) {
					$entries[] = array(
						't'     => 'g',
						'id'    => 'g' . ( ++$gn ),
						'label' => mb_substr( sanitize_text_field( FLXA_Util::str( $e['label'] ?? '' ) ), 0, 40 ),
						'c'     => empty( $e['c'] ) ? 0 : 1,
					);
					continue;
				}
				$slug = FLXA_Util::str( $e['slug'] ?? '' );
				if ( ! isset( $known[ $slug ] ) || isset( $seen[ $slug ] ) ) {
					continue;
				}
				$seen[ $slug ] = true;
				$item          = array(
					't'    => 'i',
					'slug' => $slug,
				);
				$label         = mb_substr( sanitize_text_field( FLXA_Util::str( $e['label'] ?? '' ) ), 0, 40 );
				if ( '' !== $label ) {
					$item['label'] = $label;
				}
				if ( ! empty( $e['hidden'] ) ) {
					$item['hidden'] = 1;
				}
				if ( ! empty( $e['blocked'] ) && 'administrator' !== $role && ! in_array( $slug, self::unblockable(), true ) ) {
					$item['blocked'] = 1;
				}
				$pf_off = array_values( array_intersect( $platforms, FLXA_Util::strings( $e['pf_off'] ?? array() ) ) );
				if ( $pf_off ) {
					$item['pf_off'] = $pf_off;
				}
				$subs = array_values( array_intersect( $known[ $slug ], FLXA_Util::strings( $e['sub_hidden'] ?? array() ) ) );
				if ( $subs ) {
					$item['sub_hidden'] = $subs;
				}
				$entries[] = $item;
			}
			$bottom = array_slice( array_values( array_unique( array_intersect( FLXA_Util::strings( $cfg['bottom'] ?? array() ), array_keys( $known ) ) ) ), 0, 4 );
			if ( $entries ) {
				$out[ (string) $role ] = array(
					'entries' => $entries,
					'bottom'  => $bottom,
				);
			}
		}
		return $out;
	}

	/**
	 * Slugs of the catalog this role can open (the editor greys out the others).
	 *
	 * @return list<string>
	 */
	public static function access( string $role ): array {
		$obj = '_default' === $role ? null : get_role( $role );
		$out = array();
		foreach ( self::catalog() as $it ) {
			$ok = '_default' === $role || ( $obj && ( $obj->has_cap( $it['cap'] ) || $obj->has_cap( 'manage_options' ) ) );
			if ( ! $ok && $obj ) {
				foreach ( $it['sub'] as $s ) {
					if ( $obj->has_cap( $s['cap'] ) ) {
						$ok = true;
						break;
					}
				}
			}
			if ( $ok ) {
				$out[] = $it['slug'];
			}
		}
		return $out;
	}

	/**
	 * Default entries (stored shape) for the menu the administrator sees.
	 *
	 * @return array<mixed>
	 */
	private static function default_entries(): array {
		return flxa_default_entries( array_column( self::catalog(), 'slug' ) );
	}

	/**
	 * @return list<string>
	 */
	private static function default_bottom(): array {
		return array_slice( array_map( 'strval', flxa_default_bottom() ), 0, 4 );
	}

	/**
	 * @param array<mixed> $list Stored entries.
	 * @return list<array<string,mixed>>
	 */
	private static function raw_list( array $list ): array {
		$out = array();
		foreach ( $list as $e ) {
			if ( is_array( $e ) ) {
				$out[] = FLXA_Util::arr( $e );
			}
		}
		return $out;
	}

	/**
	 * Bottom bar: only slugs of the menu, at most 4.
	 *
	 * @param list<string> $slugs Slugs.
	 * @return list<string>
	 */
	private static function clean_bottom( array $slugs ): array {
		$known = array_column( self::catalog(), 'slug' );
		return array_slice( array_values( array_unique( array_intersect( $slugs, $known ) ) ), 0, 4 );
	}

	/**
	 * Stored entries → uniform entries, reconciled with the menu: items that left the menu are dropped,
	 * new ones land in an "Other" group (collapsed).
	 *
	 * @param list<array<string,mixed>> $entries Stored entries.
	 * @return list<FlxaEntry>
	 */
	private static function normalize( array $entries ): array {
		$known = array_column( self::catalog(), 'slug' );
		$seen  = array();
		$out   = array();
		foreach ( $entries as $e ) {
			if ( 'g' === ( $e['t'] ?? '' ) ) {
				$out[] = array(
					'type'          => 'group',
					'label'         => FLXA_Util::str( $e['label'] ?? '' ),
					'collapsed'     => ! empty( $e['c'] ),
					'slug'          => '',
					'hidden'        => false,
					'blocked'       => false,
					'sub_hidden'    => array(),
					'platforms_off' => array(),
				);
				continue;
			}
			$slug = FLXA_Util::str( $e['slug'] ?? '' );
			if ( ! in_array( $slug, $known, true ) || isset( $seen[ $slug ] ) ) {
				continue;
			}
			$seen[ $slug ] = true;
			$out[]         = array(
				'type'          => 'item',
				'label'         => FLXA_Util::str( $e['label'] ?? '' ),
				'collapsed'     => false,
				'slug'          => $slug,
				'hidden'        => ! empty( $e['hidden'] ),
				'blocked'       => ! empty( $e['blocked'] ),
				'sub_hidden'    => FLXA_Util::strings( $e['sub_hidden'] ?? array() ),
				'platforms_off' => FLXA_Util::strings( $e['pf_off'] ?? array() ),
			);
		}
		$fresh = array_values( array_diff( $known, array_keys( $seen ) ) );
		if ( $fresh ) {
			$out[] = array(
				'type'          => 'group',
				'label'         => __( 'Other', 'flx-admin' ),
				'collapsed'     => true,
				'slug'          => '',
				'hidden'        => false,
				'blocked'       => false,
				'sub_hidden'    => array(),
				'platforms_off' => array(),
			);
			foreach ( $fresh as $slug ) {
				$out[] = array(
					'type'          => 'item',
					'label'         => '',
					'collapsed'     => false,
					'slug'          => $slug,
					'hidden'        => false,
					'blocked'       => false,
					'sub_hidden'    => array(),
					'platforms_off' => array(),
				);
			}
		}
		return $out;
	}

	/**
	 * Uniform entry → stored entry.
	 *
	 * @param array<string,mixed> $e Entry (type group|item).
	 * @return array<string,mixed>
	 */
	private static function to_raw( array $e ): array {
		if ( 'group' === ( $e['type'] ?? '' ) ) {
			return array(
				't'     => 'g',
				'label' => FLXA_Util::str( $e['label'] ?? '' ),
				'c'     => empty( $e['collapsed'] ) ? 0 : 1,
			);
		}
		return array(
			't'          => 'i',
			'slug'       => FLXA_Util::str( $e['slug'] ?? '' ),
			'label'      => FLXA_Util::str( $e['label'] ?? '' ),
			'hidden'     => empty( $e['hidden'] ) ? 0 : 1,
			'blocked'    => empty( $e['blocked'] ) ? 0 : 1,
			'sub_hidden' => FLXA_Util::strings( $e['sub_hidden'] ?? array() ),
			'pf_off'     => FLXA_Util::strings( $e['platforms_off'] ?? array() ),
		);
	}

	/* ───────────────────────── Personal lists (right-click menu) ───────────────────────── */

	/**
	 * A user's pins (top), "Other" moves and moved submenu items.
	 *
	 * @return FlxaUserMenu
	 */
	public static function user_menu( int $user_id = 0 ): array {
		$user_id = $user_id ? $user_id : get_current_user_id();
		return array(
			'pins'     => flxa_user_pins( $user_id ),
			'away'     => flxa_user_list( 'flxa_away', $user_id ),
			'sub_away' => flxa_user_list( 'flxa_sub_away', $user_id ),
			'sub_keep' => flxa_user_list( 'flxa_sub_keep', $user_id ),
		);
	}

	/**
	 * One change of the right-click menu: pin, unpin, away (move to Other), back, subaway, subback, order.
	 *
	 * @param string       $op    Operation.
	 * @param string       $slug  Menu or submenu link (not for `order`).
	 * @param list<string> $order Pinned slugs in the new order (`order` only).
	 * @return FlxaUserMenu|WP_Error
	 */
	public static function user_set( string $op, string $slug = '', array $order = array() ) {
		$uid  = get_current_user_id();
		$slug = substr( wp_strip_all_tags( $slug ), 0, 200 );
		$pins = flxa_user_pins( $uid );
		$away = flxa_user_list( 'flxa_away', $uid );
		if ( 'order' === $op ) {
			$pins = array_merge( array_intersect( $order, $pins ), array_diff( $pins, $order ) );
		} elseif ( '' === $slug ) {
			return FLXA_Util::error( 'flxa_menu_slug', __( 'Choose a menu item.', 'flx-admin' ), 'INVALID_INPUT', 'slug' );
		} elseif ( 'pin' === $op ) {
			$pins[] = $slug;
			$away   = array_values( array_diff( $away, array( $slug ) ) );
		} elseif ( 'unpin' === $op ) {
			$pins = array_values( array_diff( $pins, array( $slug ) ) );
		} elseif ( 'away' === $op ) {
			$away[] = $slug;
			$pins   = array_values( array_diff( $pins, array( $slug ) ) );
		} elseif ( 'back' === $op ) {
			$away = array_values( array_diff( $away, array( $slug ) ) );
		} elseif ( 'subaway' === $op || 'subback' === $op ) {
			// Submenu item (by its link): move under "Other" / take it back. Items that fell into "Other" automatically are kept in `flxa_sub_keep`.
			$sa = flxa_user_list( 'flxa_sub_away', $uid );
			$sk = flxa_user_list( 'flxa_sub_keep', $uid );
			if ( 'subaway' === $op ) {
				$sa[] = $slug;
				$sk   = array_values( array_diff( $sk, array( $slug ) ) );
			} else {
				$sa   = array_values( array_diff( $sa, array( $slug ) ) );
				$sk[] = $slug;
			}
			update_user_meta( $uid, 'flxa_sub_away', array_slice( array_values( array_unique( $sa ) ), -150 ) );
			update_user_meta( $uid, 'flxa_sub_keep', array_slice( array_values( array_unique( $sk ) ), -150 ) );
			return self::user_menu( $uid );
		} else {
			return FLXA_Util::error( 'flxa_menu_op', __( 'Unknown menu action.', 'flx-admin' ), 'INVALID_INPUT', 'op' );
		}
		update_user_meta( $uid, 'flxa_pins', array_slice( array_values( array_unique( $pins ) ), 0, 30 ) );
		update_user_meta( $uid, 'flxa_away', array_slice( array_values( array_unique( $away ) ), 0, 80 ) );
		return self::user_menu( $uid );
	}
}
