<?php
/**
 * Flux App application service: the one typed entry the GraphQL schema (and the REST callbacks of the session,
 * logout, preferences and sync endpoints) read and write through. The builders it wraps (`flxapp_rest_me`,
 * `flxapp_config`, `flxapp_user_manifest`, `flxapp_bootstrap`, `flxapp_rest_dashboard`) stay the single source of
 * the documents, so REST and GraphQL cannot drift apart.
 *
 * Shapes use the GraphQL field names (camelCase) so the schema reads them as they are. Failures are WP_Error
 * with `graphql_code` and the REST `status`.
 *
 * @package flx-app
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxappRoleRow array{id: string, name: string}
 * @phpstan-type FlxappUserRow array{databaseId: int, login: string, name: string, email: string, avatar: string, roles: list<FlxappRoleRow>, locale: string, isAdmin: bool, profileUrl: string}
 * @phpstan-type FlxappSiteRow array{name: string, url: string, adminUrl: string, restUrl: string, icon: string, locale: string, timezone: string}
 * @phpstan-type FlxappAuthRow array{kind: string, client: string|null, scope: string|null, expires: int|null}
 * @phpstan-type FlxappSubRow array{slug: string, title: string, url: string, badge: string|null}
 * @phpstan-type FlxappMenuRow array{id: string, slug: string, title: string, url: string, icon: string, iconUrl: string|null, badge: string|null, items: list<FlxappSubRow>}
 * @phpstan-type FlxappModuleRow array{id: string, title: string, menu: string|null, api: string|null, icon: string|null, version: string|null, order: int|null, url: string|null, hasManifest: bool, extra: object}
 * @phpstan-type FlxappCountRow array{key: string, value: int}
 * @phpstan-type FlxappHashesRow array{me: string, config: string, manifest: string}
 * @phpstan-type FlxappGroupRow array{id: string, label: string, collapsed: bool, items: list<string>}
 * @phpstan-type FlxappLayoutRow array{pins: list<string>, groups: list<FlxappGroupRow>}
 * @phpstan-type FlxappMeRow array{user: FlxappUserRow, site: FlxappSiteRow, auth: FlxappAuthRow, menu: list<FlxappMenuRow>, modules: list<FlxappModuleRow>, counts: list<FlxappCountRow>, bottom: list<string>, webSession: bool, caps: list<array{key: string, granted: bool}>, versions: list<array{slug: string, version: string}>, layout: FlxappLayoutRow|null, hashes: FlxappHashesRow}
 * @phpstan-type FlxappEventRow array{event: string, title: string, body: string, target: string, params: array<string,mixed>, at: int}
 * @phpstan-type FlxappSyncRow array{counts: list<FlxappCountRow>, hashes: FlxappHashesRow, events: list<FlxappEventRow>, serverTime: int}
 * @phpstan-type FlxappDocument array{hash: string, notModified: bool, document: object|array<mixed>|null}
 * @phpstan-type FlxappManifestModule array{id: string, document: object|array<mixed>}
 * @phpstan-type FlxappManifestRow array{schema: string, hash: string, notModified: bool, platform: string|null, modules: list<FlxappManifestModule>}
 * @phpstan-type FlxappDashItem array{title: string, sub: string|null, meta: string|null, url: string|null}
 * @phpstan-type FlxappDashCard array{id: string, title: string, value: string, sub: string|null, icon: string|null, tone: string|null, url: string|null, open: string|null, order: int|null}
 * @phpstan-type FlxappDashList array{id: string, title: string, icon: string|null, url: string|null, open: string|null, order: int|null, items: list<FlxappDashItem>}
 * @phpstan-type FlxappDashboardRow array{cards: list<FlxappDashCard>, lists: list<FlxappDashList>}
 * @phpstan-type FlxappPushEvent array{id: string, title: string, source: string, default: bool, on: bool}
 * @phpstan-type FlxappPrefsRow array{order: list<string>, hidden: list<string>, pinned: list<string>, forceWeb: list<string>, tabs: list<string>, cardOrder: list<string>, cardHidden: list<string>, push: list<array{event: string, on: bool}>, events: list<FlxappPushEvent>, updated: int}
 */
final class FLXAPP_Service {

	/** Highest number of events a sync returns (the stored list is capped the same). */
	public const EVENTS_MAX = FLXAPP_EVENTS_MAX;

	/* ───────── small coercions ───────── */

	/**
	 * Any builder result as plain arrays: `(object)` casts of the REST builders (empty maps) become arrays.
	 *
	 * @param mixed $v Value.
	 * @return array<string,mixed>
	 */
	private static function plain( $v ): array {
		$j = wp_json_encode( $v );
		return flx_gql_arr( false === $j ? array() : json_decode( $j, true ) );
	}

	/**
	 * An open document for a FlxJSON field: what REST sends, byte for byte (an empty map stays `{}`).
	 *
	 * @param mixed $v Value.
	 * @return object|array<mixed>
	 */
	private static function doc( $v ) {
		$j   = wp_json_encode( $v );
		$out = false === $j ? null : json_decode( $j );
		return is_object( $out ) || is_array( $out ) ? $out : new stdClass();
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function nstr( $v ): ?string {
		return is_scalar( $v ) && '' !== (string) $v ? (string) $v : null;
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function nint( $v ): ?int {
		return is_numeric( $v ) ? (int) $v : null;
	}

	/**
	 * @param mixed $v Value.
	 * @return list<string>
	 */
	private static function strings( $v ): array {
		$out = array();
		if ( is_array( $v ) ) {
			foreach ( $v as $x ) {
				if ( is_scalar( $x ) ) {
					$out[] = (string) $x;
				}
			}
		}
		return $out;
	}

	/**
	 * @param mixed $v Value.
	 * @return list<array<string,mixed>>
	 */
	private static function rows( $v ): array {
		$out = array();
		if ( is_array( $v ) ) {
			foreach ( $v as $x ) {
				if ( is_array( $x ) ) {
					$out[] = flx_gql_arr( $x );
				}
			}
		}
		return $out;
	}

	/**
	 * A map of counters as rows.
	 *
	 * @param array<mixed> $map Name → number.
	 * @return list<FlxappCountRow>
	 */
	private static function count_rows( array $map ): array {
		$out = array();
		foreach ( $map as $k => $v ) {
			$out[] = array(
				'key'   => (string) $k,
				'value' => flx_gql_int( $v ),
			);
		}
		return $out;
	}

	/**
	 * @param mixed $h Hashes.
	 * @return FlxappHashesRow
	 */
	private static function hashes( $h ): array {
		$h = flx_gql_arr( $h );
		return array(
			'me'       => flx_gql_str( $h['me'] ?? '' ),
			'config'   => flx_gql_str( $h['config'] ?? '' ),
			'manifest' => flx_gql_str( $h['manifest'] ?? '' ),
		);
	}

	/**
	 * The requester's platform for config and manifest (`mobile` / `desktop`), '' when unknown.
	 */
	public static function platform(): string {
		return flxapp_request_platform();
	}

	/* ───────── me / counts / sync ───────── */

	/**
	 * Counters of the signed-in user (badges).
	 *
	 * @return list<FlxappCountRow>
	 */
	public static function counts(): array {
		return self::count_rows( flxapp_counts() );
	}

	/**
	 * The signed-in user, site, menu, modules and layout (what REST `GET /me` returns).
	 *
	 * @return FlxappMeRow
	 */
	public static function me(): array {
		$raw  = self::plain( flxapp_rest_me_full() );
		$user = flx_gql_arr( $raw['user'] ?? array() );
		$site = flx_gql_arr( $raw['site'] ?? array() );
		$auth = flx_gql_arr( $raw['auth'] ?? array() );

		$roles = array();
		foreach ( self::rows( $user['roles'] ?? array() ) as $r ) {
			$roles[] = array(
				'id'   => flx_gql_str( $r['id'] ?? '' ),
				'name' => flx_gql_str( $r['name'] ?? '' ),
			);
		}

		$menu = array();
		foreach ( self::rows( $raw['menu'] ?? array() ) as $m ) {
			$subs = array();
			foreach ( self::rows( $m['items'] ?? array() ) as $s ) {
				$subs[] = array(
					'slug'  => flx_gql_str( $s['slug'] ?? '' ),
					'title' => flx_gql_str( $s['title'] ?? '' ),
					'url'   => flx_gql_str( $s['url'] ?? '' ),
					'badge' => self::nstr( $s['badge'] ?? null ),
				);
			}
			$menu[] = array(
				'id'      => flx_gql_str( $m['id'] ?? '' ),
				'slug'    => flx_gql_str( $m['slug'] ?? '' ),
				'title'   => flx_gql_str( $m['title'] ?? '' ),
				'url'     => flx_gql_str( $m['url'] ?? '' ),
				'icon'    => flx_gql_str( $m['icon'] ?? '' ),
				'iconUrl' => self::nstr( $m['icon_url'] ?? null ),
				'badge'   => self::nstr( $m['badge'] ?? null ),
				'items'   => $subs,
			);
		}

		$known   = array( 'title', 'menu', 'api', 'icon', 'version', 'order', 'url', 'manifest', 'cap', 'id' );
		$modules = array();
		foreach ( flx_gql_arr( $raw['modules'] ?? array() ) as $id => $m ) {
			$m         = flx_gql_arr( $m );
			$modules[] = array(
				'id'          => (string) $id,
				'title'       => flx_gql_str( $m['title'] ?? '' ),
				'menu'        => self::nstr( $m['menu'] ?? null ),
				'api'         => self::nstr( $m['api'] ?? null ),
				'icon'        => self::nstr( $m['icon'] ?? null ),
				'version'     => self::nstr( $m['version'] ?? null ),
				'order'       => self::nint( $m['order'] ?? null ),
				'url'         => self::nstr( $m['url'] ?? null ),
				'hasManifest' => ! empty( $m['manifest'] ),
				'extra'       => (object) array_diff_key( $m, array_flip( $known ) ),
			);
		}

		$caps = array();
		foreach ( flx_gql_arr( $raw['caps'] ?? array() ) as $cap => $granted ) {
			$caps[] = array(
				'key'     => (string) $cap,
				'granted' => (bool) $granted,
			);
		}
		$versions = array();
		foreach ( flx_gql_arr( $raw['versions'] ?? array() ) as $slug => $version ) {
			$versions[] = array(
				'slug'    => (string) $slug,
				'version' => flx_gql_str( $version ),
			);
		}

		$layout = null;
		if ( isset( $raw['layout'] ) && is_array( $raw['layout'] ) ) {
			$groups = array();
			foreach ( self::rows( $raw['layout']['groups'] ?? array() ) as $g ) {
				$groups[] = array(
					'id'        => flx_gql_str( $g['id'] ?? '' ),
					'label'     => flx_gql_str( $g['label'] ?? '' ),
					'collapsed' => ! empty( $g['collapsed'] ),
					'items'     => self::strings( $g['items'] ?? array() ),
				);
			}
			$layout = array(
				'pins'   => self::strings( $raw['layout']['pins'] ?? array() ),
				'groups' => $groups,
			);
		}

		$kind = flx_gql_str( $auth['kind'] ?? 'cookie', 'cookie' );
		return array(
			'user'       => array(
				'databaseId' => flx_gql_int( $user['id'] ?? 0 ),
				'login'      => flx_gql_str( $user['login'] ?? '' ),
				'name'       => flx_gql_str( $user['name'] ?? '' ),
				'email'      => flx_gql_str( $user['email'] ?? '' ),
				'avatar'     => flx_gql_str( $user['avatar'] ?? '' ),
				'roles'      => $roles,
				'locale'     => flx_gql_str( $user['locale'] ?? '' ),
				'isAdmin'    => ! empty( $user['is_admin'] ),
				'profileUrl' => flx_gql_str( $user['profile_url'] ?? '' ),
			),
			'site'       => array(
				'name'     => flx_gql_str( $site['name'] ?? '' ),
				'url'      => flx_gql_str( $site['url'] ?? '' ),
				'adminUrl' => flx_gql_str( $site['admin_url'] ?? '' ),
				'restUrl'  => flx_gql_str( $site['rest_url'] ?? '' ),
				'icon'     => flx_gql_str( $site['icon'] ?? '' ),
				'locale'   => flx_gql_str( $site['locale'] ?? '' ),
				'timezone' => flx_gql_str( $site['timezone'] ?? '' ),
			),
			'auth'       => array(
				'kind'    => $kind,
				'client'  => self::nstr( $auth['client'] ?? null ),
				'scope'   => self::nstr( $auth['scope'] ?? null ),
				'expires' => self::nint( $auth['expires'] ?? null ),
			),
			'menu'       => $menu,
			'modules'    => $modules,
			'counts'     => self::count_rows( flx_gql_arr( $raw['counts'] ?? array() ) ),
			'bottom'     => self::strings( $raw['bottom'] ?? array() ),
			'webSession' => ! empty( $raw['web_session'] ),
			'caps'       => $caps,
			'versions'   => $versions,
			'layout'     => $layout,
			'hashes'     => self::hashes( $raw['hashes'] ?? array() ),
		);
	}

	/**
	 * Light poll: counters, document hashes and the events since a time (REST `GET /sync`).
	 *
	 * @param int $since Unix time of the last poll.
	 * @return FlxappSyncRow
	 */
	public static function sync( int $since ): array {
		$user     = wp_get_current_user();
		$platform = flxapp_request_platform();
		$me       = flxapp_rest_me();
		$events   = array();
		foreach ( (array) flxapp_events_since( $user->ID, max( 0, $since ) ) as $e ) {
			$e        = flx_gql_arr( $e );
			$events[] = array(
				'event'  => flx_gql_str( $e['event'] ?? '' ),
				'title'  => flx_gql_str( $e['title'] ?? '' ),
				'body'   => flx_gql_str( $e['body'] ?? '' ),
				'target' => flx_gql_str( $e['target'] ?? '' ),
				'params' => self::doc( $e['params'] ?? new stdClass() ),
				'at'     => flx_gql_int( $e['at'] ?? 0 ),
			);
		}
		return array(
			'counts'     => self::count_rows( flxapp_counts() ),
			'hashes'     => array(
				'me'       => substr( md5( (string) wp_json_encode( $me ) ), 0, 12 ),
				'config'   => flx_gql_str( flxapp_config_hash( $user, $platform ) ),
				'manifest' => flx_gql_str( flxapp_manifest_hash( $user, $platform ) ),
			),
			'events'     => $events,
			'serverTime' => time(),
		);
	}

	/* ───────── documents: bootstrap, config, manifest ───────── */

	/**
	 * Public discovery document (`flx-app-bootstrap/1`). Open, filter-extensible; no secrets.
	 *
	 * @return object|array<mixed>
	 */
	public static function bootstrap() {
		return self::doc( flxapp_bootstrap() );
	}

	/**
	 * Resolved app look (`flx-app-config/1`). `$if_hash` equal to the current hash → not modified, no document.
	 *
	 * @param string $if_hash Hash the client already has ('' = none).
	 * @return FlxappDocument
	 */
	public static function config( string $if_hash ): array {
		$cfg         = flx_gql_arr( flxapp_config() );
		$hash        = substr( md5( (string) wp_json_encode( $cfg ) ), 0, 12 );
		$cfg['hash'] = $hash;
		$same        = '' !== $if_hash && $if_hash === $hash;
		return array(
			'hash'        => $hash,
			'notModified' => $same,
			'document'    => $same ? null : self::doc( $cfg ),
		);
	}

	/**
	 * Screens of the packages the user may open (`flx-app-manifest/1`). Same ETag rule as config.
	 *
	 * @param string $if_hash Hash the client already has ('' = none).
	 * @return FlxappManifestRow
	 */
	public static function manifest( string $if_hash ): array {
		$platform = flxapp_request_platform();
		$hash     = flx_gql_str( flxapp_manifest_hash( null, $platform ) );
		$same     = '' !== $if_hash && $if_hash === $hash;
		$modules  = array();
		if ( ! $same ) {
			foreach ( flx_gql_arr( flxapp_user_manifest( null, $platform ) ) as $id => $m ) {
				$modules[] = array(
					'id'       => (string) $id,
					'document' => self::doc( $m ),
				);
			}
		}
		return array(
			'schema'      => FLXAPP_MANIFEST_SCHEMA,
			'hash'        => $hash,
			'notModified' => $same,
			'platform'    => '' !== $platform ? $platform : null,
			'modules'     => $modules,
		);
	}

	/* ───────── dashboard ───────── */

	/**
	 * Home screen cards and lists (REST `GET /dashboard`).
	 *
	 * @return FlxappDashboardRow
	 */
	public static function dashboard(): array {
		$d     = self::plain( flxapp_rest_dashboard() );
		$cards = array();
		foreach ( self::rows( $d['cards'] ?? array() ) as $c ) {
			$cards[] = array(
				'id'    => flx_gql_str( $c['id'] ?? '' ),
				'title' => flx_gql_str( $c['title'] ?? '' ),
				'value' => flx_gql_str( $c['value'] ?? '' ),
				'sub'   => self::nstr( $c['sub'] ?? null ),
				'icon'  => self::nstr( $c['icon'] ?? null ),
				'tone'  => self::nstr( $c['tone'] ?? null ),
				'url'   => self::nstr( $c['url'] ?? null ),
				'open'  => self::nstr( $c['open'] ?? null ),
				'order' => self::nint( $c['order'] ?? null ),
			);
		}
		$lists = array();
		foreach ( self::rows( $d['lists'] ?? array() ) as $l ) {
			$items = array();
			foreach ( self::rows( $l['items'] ?? array() ) as $it ) {
				$items[] = array(
					'title' => flx_gql_str( $it['title'] ?? '' ),
					'sub'   => self::nstr( $it['sub'] ?? null ),
					'meta'  => self::nstr( $it['meta'] ?? null ),
					'url'   => self::nstr( $it['url'] ?? null ),
				);
			}
			$lists[] = array(
				'id'    => flx_gql_str( $l['id'] ?? '' ),
				'title' => flx_gql_str( $l['title'] ?? '' ),
				'icon'  => self::nstr( $l['icon'] ?? null ),
				'url'   => self::nstr( $l['url'] ?? null ),
				'open'  => self::nstr( $l['open'] ?? null ),
				'order' => self::nint( $l['order'] ?? null ),
				'items' => $items,
			);
		}
		return array(
			'cards' => $cards,
			'lists' => $lists,
		);
	}

	/* ───────── preferences ───────── */

	/**
	 * The user's preferences, shared across devices.
	 *
	 * @param int $user_id User.
	 * @return FlxappPrefsRow
	 */
	public static function prefs( int $user_id ): array {
		$p      = self::plain( flxapp_prefs_out( $user_id ) );
		$push   = array();
		foreach ( flx_gql_arr( $p['push'] ?? array() ) as $ev => $on ) {
			$push[] = array(
				'event' => (string) $ev,
				'on'    => (bool) $on,
			);
		}
		$events = array();
		foreach ( self::rows( $p['events'] ?? array() ) as $e ) {
			$events[] = array(
				'id'      => flx_gql_str( $e['id'] ?? '' ),
				'title'   => flx_gql_str( $e['title'] ?? '' ),
				'source'  => flx_gql_str( $e['source'] ?? '' ),
				'default' => ! empty( $e['default'] ),
				'on'      => ! empty( $e['on'] ),
			);
		}
		return array(
			'order'      => self::strings( $p['order'] ?? array() ),
			'hidden'     => self::strings( $p['hidden'] ?? array() ),
			'pinned'     => self::strings( $p['pinned'] ?? array() ),
			'forceWeb'   => self::strings( $p['forceWeb'] ?? array() ),
			'tabs'       => self::strings( $p['tabs'] ?? array() ),
			'cardOrder'  => self::strings( $p['cardOrder'] ?? array() ),
			'cardHidden' => self::strings( $p['cardHidden'] ?? array() ),
			'push'       => $push,
			'events'     => $events,
			'updated'    => flx_gql_int( $p['updated'] ?? 0 ),
		);
	}

	/**
	 * Partial update: only the keys present in `$in` change (REST `POST /prefs`).
	 *
	 * @param int                 $user_id User.
	 * @param array<string,mixed> $in      Lists by name; `push` is event → on.
	 * @return FlxappPrefsRow
	 */
	public static function prefs_update( int $user_id, array $in ): array {
		update_user_meta( $user_id, 'flxapp_prefs', flxapp_prefs_sanitize( $in, flxapp_user_prefs( $user_id ) ) );
		return self::prefs( $user_id );
	}

	/* ───────── session ───────── */

	/**
	 * One-time panel sign-in address (60 s) for the app's web view.
	 *
	 * @param array<string,mixed> $in `path`, `url`, `front` (bool), `embed` (bool|null).
	 * @return array{url: string, expiresIn: int}|WP_Error
	 */
	public static function session_create( array $in ) {
		if ( ! flxapp_opt( 'web_session' ) ) {
			return new WP_Error( 'flxapp_web_session_off', __( 'Admin sessions are turned off in settings.', 'flx-app' ), array( 'status' => 403, 'graphql_code' => 'FORBIDDEN' ) );
		}
		if ( ! flxapp_rate( 'session', 30, MINUTE_IN_SECONDS ) ) {
			return new WP_Error( 'flxapp_rate', __( 'Too many requests. Please wait a moment.', 'flx-app' ), array( 'status' => 429, 'graphql_code' => 'RATE_LIMITED' ) );
		}
		$embed = array_key_exists( 'embed', $in ) && null !== $in['embed'] ? rest_sanitize_boolean( $in['embed'] ) : true;
		$path  = flx_gql_str( $in['path'] ?? '' );
		$front = flx_gql_str( $in['url'] ?? '' );
		if ( '' === $front && ! empty( $in['front'] ) && rest_sanitize_boolean( $in['front'] ) ) {
			$front = '/' . ltrim( $path, '/' );
			$path  = '';
		}
		$bad = new WP_Error( 'flxapp_bad_path', __( 'Invalid admin path.', 'flx-app' ), array( 'status' => 400, 'graphql_code' => 'INVALID_INPUT', 'field' => 'path' ) );
		if ( '' !== $front && '' === flxapp_clean_front_url( $front ) ) {
			return $bad;
		}
		$tok = flxapp_current_token();
		$url = flxapp_sso_url(
			get_current_user_id(),
			$path,
			array(
				'url'   => $front,
				'embed' => $embed,
				'grant' => $tok ? flx_gql_str( $tok['grant_id'] ?? '' ) : '',
			)
		);
		if ( '' === flx_gql_str( $url ) ) {
			return $bad;
		}
		return array(
			'url'       => flx_gql_str( $url ),
			'expiresIn' => 60,
		);
	}

	/**
	 * Ends the web view cookie sessions this app session opened.
	 */
	public static function session_end(): int {
		$tok = flxapp_current_token();
		return flx_gql_int( flxapp_end_web_sessions( get_current_user_id(), $tok ? flx_gql_str( $tok['grant_id'] ?? '' ) : '' ) );
	}

	/**
	 * Closes this app session (grant): its tokens, devices and web sessions.
	 */
	public static function logout(): void {
		$tok = flxapp_current_token();
		if ( $tok ) {
			if ( '' !== flx_gql_str( $tok['grant_id'] ?? '' ) ) {
				flxapp_revoke_grant( flx_gql_str( $tok['grant_id'] ) );
			} elseif ( 'access' === ( $tok['kind'] ?? '' ) ) {
				flxapp_revoke_id( flx_gql_int( $tok['id'] ?? 0 ) );
			}
		}
	}
}
