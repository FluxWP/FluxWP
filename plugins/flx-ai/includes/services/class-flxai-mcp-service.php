<?php
/**
 * MCP server page: switch the server and OAuth on or off, create and revoke keys, read the activity.
 * Permission checks stay with the caller (manage_options). A key's secret is returned once on creation;
 * only its hash is stored.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

final class FLXAI_Mcp_Service {

	public const CACHE_TAG = 'flx-ai:mcp';

	public static function overview(): FLXAI_Mcp_Overview {
		$on    = flxai_mcp_enabled();
		$set   = get_option( 'flxai_settings', array() );
		$oauth = ! is_array( $set ) || ! array_key_exists( 'mcp_oauth', $set ) || ! empty( $set['mcp_oauth'] );
		$tools = FLXAI_Cast::arr( flxai_mcp_tools() );

		$choices = array();
		foreach ( $tools as $name => $t ) {
			$t         = FLXAI_Cast::arr( $t );
			$choices[] = new FLXAI_Mcp_Tool_Choice( (string) $name, FLXAI_Cast::str( $t['title'] ?? '' ), ! empty( $t['readonly'] ) );
		}

		$keys = array();
		foreach ( FLXAI_Cast::arr( flxai_mcp_keys() ) as $id => $k ) {
			$k      = FLXAI_Cast::arr( $k );
			$uid    = FLXAI_Cast::int( $k['user'] ?? 0 );
			$u      = $uid > 0 ? get_userdata( $uid ) : false;
			$keys[] = new FLXAI_Mcp_Key(
				(string) $id,
				FLXAI_Cast::str( $k['name'] ?? '' ),
				'oauth' === ( $k['type'] ?? '' ) ? 'oauth' : 'key',
				FLXAI_Cast::str( $k['hint'] ?? '' ),
				$uid,
				$u ? $u->user_login : '',
				$u && user_can( $u, FLXAI_CAP_MCP ),
				FLXAI_Cast::strings( $k['tools'] ?? array() ),
				FLXAI_Cast::int( $k['created'] ?? 0 ),
				FLXAI_Cast::ts( $k['last_used'] ?? 0 )
			);
		}

		$log = array();
		foreach ( array_slice( FLXAI_Cast::arr( get_option( 'flxai_mcp_log', array() ) ), 0, 20 ) as $l ) {
			$l   = FLXAI_Cast::arr( $l );
			$uid = FLXAI_Cast::int( $l['u'] ?? 0 );
			$u   = $uid > 0 ? get_userdata( $uid ) : false;
			$log[] = new FLXAI_Mcp_Log_Entry(
				FLXAI_Cast::int( $l['t'] ?? 0 ),
				$u ? $u->user_login : '',
				FLXAI_Cast::str( $l['tool'] ?? '' ),
				FLXAI_Cast::str( $l['on'] ?? '' ),
				! empty( $l['ok'] )
			);
		}

		return new FLXAI_Mcp_Overview(
			new FLXAI_Mcp_Server(
				$on,
				$oauth,
				$on && flxai_oauth_enabled(),
				flxai_mcp_legacy(),
				flxai_mcp_url(),
				flxai_mcp_rest_url(),
				count( $tools ),
				function_exists( 'flxr_admin_url' ) ? FLXAI_Cast::str( flxr_admin_url() ) : ''
			),
			$keys,
			$choices,
			$log
		);
	}

	public static function set_server( bool $enabled, bool $oauth ): FLXAI_Mcp_Overview {
		flxai_update_settings(
			array(
				'mcp_enabled' => $enabled ? 1 : 0,
				'mcp_oauth'   => $oauth ? 1 : 0,
			)
		);
		self::changed();
		return self::overview();
	}

	/**
	 * Creates a key. With tools given the key sees and runs only those.
	 *
	 * @param list<string> $tools Tool names; empty for no limit.
	 */
	public static function create_key( string $name, array $tools ): FLXAI_Mcp_New_Key {
		$keys        = FLXAI_Cast::arr( flxai_mcp_keys() );
		$key         = 'flxmcp_' . wp_generate_password( 40, false, false );
		$id          = wp_generate_uuid4();
		$name        = sanitize_text_field( $name );
		$keys[ $id ] = array(
			'name'      => '' !== $name ? $name : __( 'AI', 'flx-ai' ),
			'hash'      => hash( 'sha256', $key ),
			'user'      => get_current_user_id(),
			'created'   => time(),
			'last_used' => 0,
			'hint'      => substr( $key, -4 ),
		);
		$known       = array_map( 'sanitize_key', array_map( 'strval', array_keys( FLXAI_Cast::arr( flxai_mcp_tools() ) ) ) );
		$limit       = array_values( array_intersect( array_map( 'sanitize_key', $tools ), $known ) );
		if ( $limit ) {
			$keys[ $id ]['tools'] = $limit;
		}
		update_option( 'flxai_mcp_keys', $keys, false );
		self::changed();
		return new FLXAI_Mcp_New_Key( $key, self::overview() );
	}

	/** @return FLXAI_Mcp_Overview|WP_Error */
	public static function revoke_key( string $id ) {
		$keys = FLXAI_Cast::arr( flxai_mcp_keys() );
		if ( ! isset( $keys[ $id ] ) ) {
			return new WP_Error(
				'flxai_key_none',
				__( 'Key not found.', 'flx-ai' ),
				array( 'graphql_code' => 'NOT_FOUND', 'field' => 'id' )
			);
		}
		unset( $keys[ $id ] );
		update_option( 'flxai_mcp_keys', $keys, false );
		self::changed();
		return self::overview();
	}

	public static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE_TAG ) );
		}
	}
}
