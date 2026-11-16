<?php
/**
 * MCP connections: add, change and remove external MCP servers, sign in with OAuth, refresh the tool
 * list and set per-tool permissions. Permission checks stay with the caller (manage_options); a
 * personal connection of another user can be seen but not changed. Secrets (key, token, OAuth client
 * secret) are encrypted at rest and never returned.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

final class FLXAI_Connections_Service {

	public const CACHE_TAG = 'flx-ai:connections';

	public static function screen(): FLXAI_Connections_Screen {
		$list = array();
		foreach ( FLXAI_Cast::arr( flxai_mcpc_visible() ) as $s ) {
			$list[] = self::map( FLXAI_Cast::arr( $s ) );
		}
		$roles = array();
		foreach ( wp_roles()->get_names() as $key => $name ) {
			if ( 'administrator' !== $key ) {
				$roles[] = new FLXAI_Option( (string) $key, translate_user_role( (string) $name ) );
			}
		}
		return new FLXAI_Connections_Screen( $list, $roles, FLXAI_Cast::str( flxai_mcpc_oauth_callback() ) );
	}

	/** @return FLXAI_Connection|null */
	public static function get( string $id ) {
		$s = '' !== $id ? FLXAI_Cast::arr( flxai_mcpc_get( $id ) ) : array();
		return $s ? self::map( $s ) : null;
	}

	/**
	 * Creates a connection, or changes one when `$id` is given. Fields left out of `$in` keep their value;
	 * a secret left out or empty keeps the saved one. Saving a connection that is on loads its tools
	 * (unless it still needs an OAuth sign-in); the result carries the outcome in `error`.
	 *
	 * @param array<mixed> $in Fields: name, url, auth, header, secret, scope, roles, enabled, client_id, client_secret.
	 * @return FLXAI_Connection|WP_Error
	 */
	public static function save( array $in, string $id = '' ) {
		$s = null;
		if ( '' !== $id ) {
			$s = self::own( $id );
			if ( $s instanceof WP_Error ) {
				return $s;
			}
		}
		$new = null === $s;
		$s   = $s ?? FLXAI_Cast::arr( flxai_mcpc_defaults() );

		$url = $new || array_key_exists( 'url', $in ) ? esc_url_raw( trim( FLXAI_Cast::str( $in['url'] ?? '' ) ), array( 'http', 'https' ) ) : FLXAI_Cast::str( $s['url'] );
		if ( ! flxai_mcpc_url_ok( $url ) ) {
			return new WP_Error(
				'flxai_mcpc_url',
				__( 'Invalid or blocked address. Use a public https address.', 'flx-ai' ),
				array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'url' )
			);
		}
		if ( $new ) {
			$s['id']      = FLXAI_Cast::str( flxai_mcpc_slug( FLXAI_Cast::str( $in['name'] ?? '' ) ) );
			$s['owner']   = get_current_user_id();
			$s['created'] = time();
		}
		$changed_url = $s['url'] !== $url;
		if ( $new || array_key_exists( 'name', $in ) ) {
			$name      = sanitize_text_field( FLXAI_Cast::str( $in['name'] ?? '' ) );
			$s['name'] = '' !== $name ? $name : (string) wp_parse_url( $url, PHP_URL_HOST );
		}
		$s['url'] = $url;
		if ( array_key_exists( 'auth', $in ) ) {
			$a         = FLXAI_Cast::str( $in['auth'] );
			$s['auth'] = in_array( $a, array( 'none', 'bearer', 'header', 'oauth' ), true ) ? $a : 'none';
		}
		if ( array_key_exists( 'header', $in ) ) {
			$s['header'] = (string) preg_replace( '/[^A-Za-z0-9\-]/', '', FLXAI_Cast::str( $in['header'] ) );
		}
		if ( array_key_exists( 'scope', $in ) ) {
			$s['scope'] = 'shared' === FLXAI_Cast::str( $in['scope'] ) ? 'shared' : 'personal';
		}
		if ( array_key_exists( 'roles', $in ) ) {
			$s['roles'] = array_values( array_intersect( array_map( 'sanitize_key', FLXAI_Cast::strings( $in['roles'] ) ), array_map( 'strval', array_keys( wp_roles()->get_names() ) ) ) );
		}
		if ( array_key_exists( 'enabled', $in ) ) {
			$s['enabled'] = empty( $in['enabled'] ) ? 0 : 1;
		}
		$secret = trim( FLXAI_Cast::str( $in['secret'] ?? '' ) );
		if ( '' !== $secret ) {
			$s['secret'] = flxai_encrypt( $secret );
		}
		$o = FLXAI_Cast::arr( $s['oauth'] );
		if ( $changed_url ) {
			$o = array();
		}
		$cid = sanitize_text_field( FLXAI_Cast::str( $in['client_id'] ?? '' ) );
		if ( '' !== $cid && $cid !== FLXAI_Cast::str( $o['client_id'] ?? '' ) ) {
			$o = array( 'client_id' => $cid );
		}
		$csec = trim( FLXAI_Cast::str( $in['client_secret'] ?? '' ) );
		if ( '' !== $csec ) {
			$o['client_secret'] = flxai_encrypt( $csec );
		}
		$s['oauth'] = $o;
		flxai_mcpc_put( $s );
		$sid = FLXAI_Cast::str( $s['id'] );
		delete_transient( 'flxai_mcpc_sess_' . $sid );
		if ( ! ( 'oauth' === $s['auth'] && empty( $o['access'] ) ) && $s['enabled'] ) {
			flxai_mcpc_refresh( $sid );
		}
		self::changed();
		$out = self::get( $sid );
		return $out ?? new WP_Error( 'flxai_mcpc_none', __( 'Connection not found.', 'flx-ai' ), array( 'graphql_code' => 'NOT_FOUND' ) );
	}

	/** @return true|WP_Error */
	public static function delete( string $id ) {
		$s = self::own( $id );
		if ( $s instanceof WP_Error ) {
			return $s;
		}
		flxai_mcpc_delete( $id );
		if ( function_exists( 'flxai_chat_registry' ) ) {
			flxai_chat_registry( true );
		}
		self::changed();
		return true;
	}

	/**
	 * Reloads the tool list; a failure is stored in the connection's `error`.
	 *
	 * @return FLXAI_Connection|WP_Error
	 */
	public static function refresh( string $id ) {
		$s = self::own( $id );
		if ( $s instanceof WP_Error ) {
			return $s;
		}
		flxai_mcpc_refresh( $id );
		self::changed();
		return self::get( $id ) ?? self::missing();
	}

	/**
	 * Address of the provider's sign-in page (OAuth connections only).
	 *
	 * @return string|WP_Error
	 */
	public static function sign_in_url( string $id ) {
		$s = self::own( $id );
		if ( $s instanceof WP_Error ) {
			return $s;
		}
		if ( 'oauth' !== FLXAI_Cast::str( $s['auth'] ) ) {
			return new WP_Error(
				'flxai_mcpc_auth',
				__( 'This connection does not sign in with OAuth.', 'flx-ai' ),
				array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'id' )
			);
		}
		$u = flxai_mcpc_oauth_start( $id );
		if ( $u instanceof WP_Error ) {
			return new WP_Error( $u->get_error_code(), $u->get_error_message(), array( 'graphql_code' => 'INVALID_INPUT' ) );
		}
		self::changed();
		return $u;
	}

	/** @return FLXAI_Connection|WP_Error */
	public static function sign_out( string $id ) {
		$s = self::own( $id );
		if ( $s instanceof WP_Error ) {
			return $s;
		}
		$s['oauth'] = array_diff_key( FLXAI_Cast::arr( $s['oauth'] ), array_flip( array( 'access', 'refresh', 'expires' ) ) );
		flxai_mcpc_put( $s );
		delete_transient( 'flxai_mcpc_sess_' . $id );
		self::changed();
		return self::get( $id ) ?? self::missing();
	}

	/**
	 * Replaces the per-tool permissions. Tools left out go back to "ask".
	 *
	 * @param array<string,string> $perms Tool => allow|ask|off.
	 * @return FLXAI_Connection|WP_Error
	 */
	public static function set_permissions( string $id, array $perms ) {
		$s = self::own( $id );
		if ( $s instanceof WP_Error ) {
			return $s;
		}
		$tools = FLXAI_Cast::arr( $s['tools'] );
		$clean = array();
		foreach ( $perms as $tool => $p ) {
			$tool = (string) $tool;
			if ( ! isset( $tools[ $tool ] ) || ! in_array( $p, array( 'allow', 'ask', 'off' ), true ) ) {
				return new WP_Error(
					'flxai_mcpc_perm',
					/* translators: %s: tool name */
					sprintf( __( 'Unknown tool or permission: %s', 'flx-ai' ), $tool ),
					array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'permissions' )
				);
			}
			if ( 'allow' === $p && empty( FLXAI_Cast::arr( $tools[ $tool ] )['readonly'] ) ) {
				return new WP_Error(
					'flxai_mcpc_perm_ro',
					/* translators: %s: tool name */
					sprintf( __( 'Only read-only tools can run without asking: %s', 'flx-ai' ), $tool ),
					array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'permissions' )
				);
			}
			$clean[ $tool ] = $p;
		}
		$s['perms'] = $clean;
		flxai_mcpc_put( $s );
		self::changed();
		return self::get( $id ) ?? self::missing();
	}

	/**
	 * A connection the signed-in user may change.
	 *
	 * @return array<mixed>|WP_Error
	 */
	private static function own( string $id ) {
		$s = '' !== $id ? FLXAI_Cast::arr( flxai_mcpc_get( sanitize_key( $id ) ) ) : array();
		if ( ! $s ) {
			return self::missing();
		}
		if ( 'personal' === $s['scope'] && FLXAI_Cast::int( $s['owner'] ) !== get_current_user_id() ) {
			return new WP_Error(
				'flxai_mcpc_other',
				__( 'This is another user\'s personal connection.', 'flx-ai' ),
				array( 'graphql_code' => 'FORBIDDEN', 'field' => 'id' )
			);
		}
		return $s;
	}

	private static function missing(): WP_Error {
		return new WP_Error(
			'flxai_mcpc_none',
			__( 'Connection not found.', 'flx-ai' ),
			array( 'graphql_code' => 'NOT_FOUND', 'field' => 'id' )
		);
	}

	/** @param array<mixed> $s Stored record. */
	private static function map( array $s ): FLXAI_Connection {
		$s     = $s + FLXAI_Cast::arr( flxai_mcpc_defaults() );
		$o     = FLXAI_Cast::arr( $s['oauth'] );
		$info  = FLXAI_Cast::arr( $s['info'] );
		$owner = get_userdata( FLXAI_Cast::int( $s['owner'] ) );
		$tools = array();
		$on    = 0;
		foreach ( FLXAI_Cast::arr( $s['tools'] ) as $name => $t ) {
			$t    = FLXAI_Cast::arr( $t );
			$perm = FLXAI_Cast::str( flxai_mcpc_perm( $s, (string) $name ) );
			if ( 'off' !== $perm ) {
				++$on;
			}
			$tools[] = new FLXAI_Connection_Tool(
				(string) $name,
				FLXAI_Cast::str( flxai_mcpc_tool_name( FLXAI_Cast::str( $s['id'] ), (string) $name ) ),
				FLXAI_Cast::str( $t['title'] ?? $name ),
				FLXAI_Cast::str( $t['description'] ?? '' ),
				! empty( $t['readonly'] ),
				! empty( $t['destructive'] ),
				$perm
			);
		}
		$signed = ! empty( $o['access'] );
		$err    = FLXAI_Cast::str( $s['error'] );
		if ( empty( $s['enabled'] ) ) {
			$status = 'off';
		} elseif ( '' !== $err ) {
			$status = 'error';
		} elseif ( 'oauth' === $s['auth'] && ! $signed ) {
			$status = 'signin';
		} else {
			$status = 'connected';
		}
		$mine = 'shared' === $s['scope'] || FLXAI_Cast::int( $s['owner'] ) === get_current_user_id();
		return new FLXAI_Connection(
			FLXAI_Cast::str( $s['id'] ),
			FLXAI_Cast::str( $s['name'] ),
			FLXAI_Cast::str( $s['url'] ),
			FLXAI_Cast::str( $s['auth'] ),
			FLXAI_Cast::str( $s['header'] ),
			'shared' === $s['scope'] ? 'shared' : 'personal',
			FLXAI_Cast::int( $s['owner'] ),
			$owner ? $owner->user_login : '',
			FLXAI_Cast::strings( $s['roles'] ),
			! empty( $s['enabled'] ),
			$mine,
			'' !== FLXAI_Cast::str( $s['secret'] ),
			empty( $o['registered'] ) ? FLXAI_Cast::str( $o['client_id'] ?? '' ) : '',
			! empty( $o['client_secret'] ) && empty( $o['registered'] ),
			$signed,
			FLXAI_Cast::str( $info['name'] ?? '' ),
			FLXAI_Cast::str( $info['version'] ?? '' ),
			FLXAI_Cast::str( $info['protocol'] ?? '' ),
			FLXAI_Cast::ts( $s['checked'] ),
			$err,
			$status,
			'mcp_' . FLXAI_Cast::str( $s['id'] ) . '_*',
			$on,
			$tools
		);
	}

	public static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE_TAG ) );
		}
	}
}
