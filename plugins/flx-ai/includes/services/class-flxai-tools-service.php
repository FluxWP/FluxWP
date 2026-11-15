<?php
/**
 * Tools: the registry rows with their 30-day use, switching tools on and off, and choosing which
 * plugin wins a name clash. Permission checks stay with the caller (manage_options).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

final class FLXAI_Tools_Service {

	public const CACHE_TAG = 'flx-ai:tools';

	/**
	 * Every tool, clashing duplicates included.
	 *
	 * @return list<FLXAI_Tool>
	 */
	public static function all(): array {
		$stats = array();
		foreach ( FLXAI_Usage_Service::report( 30, '' )->by_tool as $s ) {
			$stats[ $s->key ] = $s;
		}
		$out = array();
		foreach ( flxai_tools_rows() as $r ) {
			$r  = FLXAI_Cast::arr( $r );
			$n  = FLXAI_Cast::str( $r['name'] ?? '' );
			$st = $stats[ $n ] ?? null;
			$out[] = new FLXAI_Tool(
				$n,
				FLXAI_Cast::str( $r['title'] ?? '' ),
				FLXAI_Cast::str( $r['description'] ?? '' ),
				FLXAI_Cast::str( $r['source'] ?? '' ),
				FLXAI_Cast::str( $r['label'] ?? '' ),
				FLXAI_Cast::bool( $r['readonly'] ?? false ),
				FLXAI_Cast::bool( $r['approval'] ?? false ),
				FLXAI_Cast::str( $r['cap'] ?? '' ),
				FLXAI_Cast::bool( $r['mcp'] ?? false ),
				FLXAI_Cast::bool( $r['chat'] ?? false ),
				FLXAI_Cast::bool( $r['off'] ?? false ),
				FLXAI_Cast::strings( $r['aliases'] ?? array() ),
				FLXAI_Cast::bool( $r['dupe'] ?? false ),
				FLXAI_Cast::strings( $r['rivals'] ?? array() ),
				FLXAI_Cast::bool( $r['prefixed'] ?? false ),
				null === $st ? 0 : $st->calls,
				null === $st ? 0 : $st->errors,
				null === $st ? 0 : $st->avg_ms
			);
		}
		return $out;
	}

	/**
	 * Switches tools on or off; tools you leave out keep their state.
	 *
	 * @param array<string,bool> $states Tool name (or old alias) => on.
	 * @return list<FLXAI_Tool>|WP_Error
	 */
	public static function set_on( array $states ) {
		$known = array();
		foreach ( self::all() as $t ) {
			$known[ $t->name ] = true;
		}
		$off = flxai_tools_off();
		foreach ( $states as $name => $on ) {
			$name = flxai_tool_resolve( (string) $name );
			if ( ! isset( $known[ $name ] ) ) {
				return new WP_Error(
					'flxai_unknown_tool',
					/* translators: %s: tool name */
					sprintf( __( 'Unknown tool: %s', 'flx-ai' ), $name ),
					array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'tools' )
				);
			}
			$off = $on ? array_values( array_diff( $off, array( $name ) ) ) : array_values( array_unique( array_merge( $off, array( $name ) ) ) );
		}
		flxai_update_settings( array( 'tools_off' => $off ) );
		self::changed();
		return self::all();
	}

	/**
	 * Picks which plugin provides a tool whose name more than one plugin registers.
	 *
	 * @return list<FLXAI_Tool>|WP_Error
	 */
	public static function pick( string $name, string $source ) {
		$source = sanitize_key( $source );
		$ok     = false;
		foreach ( self::all() as $t ) {
			if ( $t->name === $name && $t->dupe && $t->source === $source ) {
				$ok = true;
			}
		}
		if ( ! $ok ) {
			return new WP_Error(
				'flxai_not_a_rival',
				__( 'That plugin does not offer this tool under the same name.', 'flx-ai' ),
				array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'source' )
			);
		}
		$picks          = FLXAI_Cast::arr( flxai_setting( 'tool_pick', array() ) );
		$picks[ $name ] = $source;
		flxai_update_settings( array( 'tool_pick' => $picks ) );
		self::changed();
		return self::all();
	}

	public static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE_TAG ) );
		}
	}
}
