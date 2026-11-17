<?php
/**
 * Per-system AI settings: the on/off switch, the model of one plugin and the fields it declared with the
 * Flux AI SDK. Permission checks stay with the caller (manage_options). Toggle values travel as "1" / "0",
 * numbers as numeric strings.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

final class FLXAI_System_Settings_Service {

	public const CACHE_TAG = 'flx-ai:system-settings';

	public static function get( string $source ): FLXAI_System_Settings {
		$slug   = sanitize_key( $source );
		$reg    = function_exists( 'flx_ai_registry' ) ? FLXAI_Cast::arr( flx_ai_registry() ) : array();
		$plugin = FLXAI_Cast::arr( FLXAI_Cast::arr( $reg['plugins'] ?? array() )[ $slug ] ?? array() );
		$decl   = FLXAI_Cast::arr( FLXAI_Cast::arr( $reg['settings'] ?? array() )[ $slug ] ?? array() );
		$info   = FLXAI_Cast::arr( flxai_source_info( $slug ) );

		$fields = array();
		foreach ( $decl as $key => $f ) {
			$f    = FLXAI_Cast::arr( $f );
			$type = FLXAI_Cast::str( $f['type'] ?? 'text' );
			$val  = function_exists( 'flx_ai_get' ) ? flx_ai_get( $slug, (string) $key ) : ( $f['default'] ?? '' );
			$opts = array();
			foreach ( FLXAI_Cast::arr( $f['options'] ?? array() ) as $ov => $ol ) {
				$opts[] = new FLXAI_Option( (string) $ov, FLXAI_Cast::str( $ol ) );
			}
			$fields[] = new FLXAI_System_Field(
				(string) $key,
				$type,
				FLXAI_Cast::str( $f['label'] ?? $key ),
				FLXAI_Cast::str( $f['help'] ?? '' ),
				'toggle' === $type ? ( FLXAI_Cast::bool( $val ) ? '1' : '0' ) : FLXAI_Cast::str( $val ),
				$opts,
				isset( $f['min'] ) && is_numeric( $f['min'] ) ? (float) $f['min'] : null,
				isset( $f['max'] ) && is_numeric( $f['max'] ) ? (float) $f['max'] : null
			);
		}

		$tools = array_filter(
			FLXAI_Cast::arr( flxai_tools_rows() ),
			static function ( $r ) use ( $slug ) {
				$r = FLXAI_Cast::arr( $r );
				return FLXAI_Cast::str( $r['source'] ?? '' ) === $slug && empty( $r['dupe'] );
			}
		);
		$on    = 0;
		foreach ( $tools as $r ) {
			if ( empty( FLXAI_Cast::arr( $r )['off'] ) ) {
				++$on;
			}
		}
		$models = FLXAI_Cast::arr( flxai_setting( 'source_models', array() ) );

		return new FLXAI_System_Settings(
			$slug,
			FLXAI_Cast::str( $info['label'] ?? $slug ),
			FLXAI_Cast::str( $plugin['description'] ?? '' ),
			FLXAI_Cast::bool( flxai_source_enabled( $slug ) ),
			FLXAI_Cast::str( $models[ $slug ] ?? '' ),
			FLXAI_Cast::str( flxai_model_for( '' ) ),
			$fields,
			(bool) ( $decl || $plugin ),
			count( $tools ),
			$on,
			defined( 'FLX_AI_SDK_VERSION' ) ? FLXAI_Cast::str( FLX_AI_SDK_VERSION ) : ''
		);
	}

	/**
	 * Saves the switch, the model and the declared fields; what you leave out keeps its value.
	 *
	 * @param array<string,string> $values Field key => value.
	 * @return FLXAI_System_Settings|WP_Error
	 */
	public static function save( string $source, ?bool $on, ?string $model, array $values ) {
		$slug = sanitize_key( $source );
		if ( '' === $slug ) {
			return new WP_Error( 'flxai_system_none', __( 'Unknown system.', 'flx-ai' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'source' ) );
		}
		$decl = function_exists( 'flx_ai_registry' ) ? FLXAI_Cast::arr( FLXAI_Cast::arr( flx_ai_registry() )['settings'] ?? array() )[ $slug ] ?? array() : array();
		$decl = FLXAI_Cast::arr( $decl );
		foreach ( array_keys( $values ) as $k ) {
			if ( ! isset( $decl[ $k ] ) ) {
				return new WP_Error(
					'flxai_system_field',
					/* translators: %s: field key */
					sprintf( __( 'Unknown setting: %s', 'flx-ai' ), $k ),
					array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'values' )
				);
			}
		}
		$patch = array();
		if ( null !== $on ) {
			$off = array_values( array_diff( FLXAI_Cast::strings( flxai_setting( 'sources_off', array() ) ), array( $slug ) ) );
			if ( ! $on ) {
				$off[] = $slug;
			}
			$patch['sources_off'] = array_values( array_unique( $off ) );
		}
		if ( null !== $model ) {
			$models = FLXAI_Cast::arr( flxai_setting( 'source_models', array() ) );
			$m      = sanitize_text_field( $model );
			if ( '' === $m ) {
				unset( $models[ $slug ] );
			} else {
				$models[ $slug ] = $m;
			}
			$patch['source_models'] = $models;
		}
		if ( $patch ) {
			flxai_update_settings( $patch );
		}
		if ( $values && function_exists( 'flx_ai_set' ) ) {
			$vals = array();
			foreach ( $values as $k => $v ) {
				$vals[ $k ] = 'toggle' === FLXAI_Cast::str( FLXAI_Cast::arr( $decl[ $k ] )['type'] ?? '' ) ? ( '1' === $v || 'true' === $v ) : $v;
			}
			flx_ai_set( $slug, $vals );
		}
		self::changed();
		FLXAI_Usage_Service::changed();
		return self::get( $slug );
	}

	public static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE_TAG ) );
		}
	}
}
