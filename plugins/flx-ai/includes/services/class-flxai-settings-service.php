<?php
/**
 * Flux AI settings: the one place that reads and changes the settings page values, the API keys and the
 * model picker. GraphQL and the Flux App settings bridge write through save(); keys are
 * write-only (a key is described by FLXAI_Secret, never returned). Permission checks stay with the caller
 * (manage_options).
 *
 * Errors are WP_Error with `graphql_code` INVALID_INPUT and the input `field`.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxaiSecretInput array{name: string, value?: string|null, clear?: bool|null}
 */
final class FLXAI_Settings_Service {

	public const CACHE_TAG = 'flx-ai:settings';

	/**
	 * Setting name => [ group, provider label, where to get it ]. Plugins add web search provider keys
	 * through flxai_web_providers.
	 *
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	public static function key_catalog(): array {
		$list = array(
			'key_openrouter' => array( 'provider', 'OpenRouter', '' ),
			'key_anthropic'  => array( 'provider', 'Anthropic (Claude)', '' ),
			'key_fal'        => array( 'media', 'fal.ai', 'fal.ai › Dashboard › API Keys' ),
			'key_evolink'    => array( 'media', 'EvoLink', 'evolink.ai › API Keys' ),
			'key_openai'     => array( 'media', 'OpenAI', 'platform.openai.com › API keys' ),
			'key_gemini'     => array( 'media', 'Google Gemini', 'aistudio.google.com › API keys' ),
			'key_render'     => array( 'render', 'Gotenberg', '' ),
		);
		foreach ( FLXAI_Cast::arr( flxai_web_providers() ) as $p ) {
			$p   = FLXAI_Cast::arr( $p );
			$key = sanitize_key( FLXAI_Cast::str( $p['key'] ?? '' ) );
			if ( 0 === strpos( $key, 'key_' ) && ! isset( $list[ $key ] ) ) {
				$list[ $key ] = array( 'web', FLXAI_Cast::str( $p['label'] ?? $key ), FLXAI_Cast::str( $p['help'] ?? '' ) );
			}
		}
		return $list;
	}

	/**
	 * The API keys, described but never revealed.
	 *
	 * @return list<FLXAI_Secret>
	 */
	public static function secrets(): array {
		$out = array();
		foreach ( self::key_catalog() as $name => $row ) {
			$value  = FLXAI_Cast::str( flxai_setting( $name, '' ) );
			$const  = 'FLXAI_' . strtoupper( $name );
			$out[]  = new FLXAI_Secret(
				$name,
				$row[0],
				$row[1],
				$row[2],
				'' !== $value,
				strlen( $value ) >= 12 ? substr( $value, -4 ) : '',
				defined( $const ) && '' !== FLXAI_Cast::str( constant( $const ) )
			);
		}
		return $out;
	}

	/**
	 * All settings except the keys.
	 */
	public static function get(): FLXAI_Settings {
		$s      = FLXAI_Cast::arr( flxai_settings() );
		$models = FLXAI_Cast::arr( $s['source_models'] ?? array() );
		$rows   = array();
		foreach ( FLXAI_Cast::arr( flxai_sources() ) as $src => $label ) {
			$src = (string) $src;
			if ( 'flx-ai' === $src ) {
				continue;
			}
			$rows[] = new FLXAI_Source_Model( $src, FLXAI_Cast::str( $label ), FLXAI_Cast::str( $models[ $src ] ?? '' ) );
		}
		return new FLXAI_Settings(
			FLXAI_Cast::str( $s['anthropic_workspace'] ?? '' ),
			FLXAI_Cast::str( $s['model'] ?? '' ),
			FLXAI_Cast::str( $s['chat_model'] ?? '' ),
			FLXAI_Cast::str( $s['title_model'] ?? '' ),
			$rows,
			FLXAI_Cast::str( $s['cache'] ?? '5m' ),
			FLXAI_Cast::str( $s['effort'] ?? 'low' ),
			FLXAI_Cast::bool( $s['thinking'] ?? true ),
			FLXAI_Cast::float( $s['limit_day'] ?? 0 ),
			FLXAI_Cast::float( $s['limit_week'] ?? 0 ),
			FLXAI_Cast::float( $s['limit_month'] ?? 0 ),
			FLXAI_Cast::str( $s['limit_action'] ?? 'block' ),
			FLXAI_Cast::int( $s['limit_warn_pct'] ?? 80, 80 ),
			FLXAI_Cast::bool( $s['limit_notify'] ?? true ),
			FLXAI_Cast::strings( $s['chat_roles'] ?? array() ),
			FLXAI_Cast::bool( $s['chat_admin'] ?? true ),
			FLXAI_Cast::bool( $s['chat_front'] ?? true ),
			FLXAI_Cast::str( $s['chat_mode'] ?? 'bubble' ),
			FLXAI_Cast::int( $s['chat_max_rounds'] ?? 12, 12 ),
			FLXAI_Cast::int( $s['chat_history'] ?? 40, 40 ),
			FLXAI_Cast::str( $s['chat_instructions'] ?? '' ),
			FLXAI_Cast::bool( $s['chat_widgets'] ?? true ),
			FLXAI_Cast::str( $s['media_image_model'] ?? '' ),
			FLXAI_Cast::str( $s['media_video_model'] ?? '' ),
			FLXAI_Cast::int( $s['media_days'] ?? 90, 90 ),
			FLXAI_Cast::bool( $s['image_tool_on'] ?? true ),
			FLXAI_Cast::bool( $s['web_on'] ?? true ),
			FLXAI_Cast::bool( $s['web_fetch_on'] ?? true ),
			FLXAI_Cast::bool( $s['shell_on'] ?? true ),
			FLXAI_Cast::str( $s['web_provider'] ?? 'auto' ),
			FLXAI_Cast::int( $s['web_search_month'] ?? 0 ),
			FLXAI_Cast::float( $s['web_search_cost'] ?? 0 ),
			FLXAI_Cast::str( $s['render_url'] ?? '' ),
			FLXAI_Cast::str( $s['render_user'] ?? '' ),
			FLXAI_Cast::int( $s['log_days'] ?? 0 ),
			FLXAI_Cast::bool( $s['log_bodies'] ?? true ),
			FLXAI_Cast::int( $s['log_body_days'] ?? 0 ),
			FLXAI_Cast::bool( $s['delete_data'] ?? false )
		);
	}

	/**
	 * Choices of the select fields and read-only facts.
	 */
	public static function options(): FLXAI_Settings_Options {
		$roles = array();
		foreach ( wp_roles()->get_names() as $key => $name ) {
			$roles[] = new FLXAI_Option( (string) $key, translate_user_role( (string) $name ) );
		}
		$web = array();
		foreach ( FLXAI_Cast::arr( flxai_web_providers() ) as $id => $p ) {
			$web[] = new FLXAI_Option( (string) $id, FLXAI_Cast::str( FLXAI_Cast::arr( $p )['label'] ?? $id ) );
		}
		$media = FLXAI_Cast::arr( flxai_media_providers() );
		$prov  = array();
		foreach ( FLXAI_Cast::arr( $media['providers'] ?? array() ) as $id => $p ) {
			$prov[] = new FLXAI_Option( (string) $id, FLXAI_Cast::str( FLXAI_Cast::arr( $p )['label'] ?? $id ) );
		}
		$models = array();
		foreach ( FLXAI_Cast::arr( $media['models'] ?? array() ) as $id => $m ) {
			$m        = FLXAI_Cast::arr( $m );
			$models[] = new FLXAI_Media_Model(
				(string) $id,
				FLXAI_Cast::str( $m['label'] ?? $id ),
				FLXAI_Cast::str( $m['kind'] ?? 'image' ),
				FLXAI_Cast::strings( $m['routes'] ?? array() ),
				FLXAI_Cast::str( $m['auto'] ?? '' )
			);
		}
		return new FLXAI_Settings_Options(
			$roles,
			$web,
			$models,
			$prov,
			FLXAI_Cast::str( flxai_model_for( '' ) ),
			FLXAI_Cast::str( flxai_widget_renderer() ),
			FLXAI_Cast::int( flxai_web_month_count() ),
			array() !== FLXAI_Cast::arr( flxai_providers_ready() )
		);
	}

	/**
	 * Saves the changed settings; what the input leaves out stays as it is. Nothing is written when any
	 * value is invalid.
	 *
	 * Input keys are setting names (model, limit_day, chat_roles …); `source_models` is source => model;
	 * `secrets` is a list of { name, value?, clear? } (a key is written when `value` is not empty,
	 * removed when `clear` is true).
	 *
	 * @param array<string,mixed> $in Changes.
	 * @return FLXAI_Settings|WP_Error
	 */
	public static function save( array $in ) {
		$new = array();

		foreach ( array( 'model', 'chat_model', 'title_model', 'render_user' ) as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$new[ $k ] = sanitize_text_field( FLXAI_Cast::str( $in[ $k ] ) );
			}
		}
		if ( array_key_exists( 'anthropic_workspace', $in ) ) {
			$new['anthropic_workspace'] = (string) preg_replace( '/[^a-zA-Z0-9_-]/', '', FLXAI_Cast::str( $in['anthropic_workspace'] ) );
		}
		if ( array_key_exists( 'source_models', $in ) ) {
			$map = array();
			foreach ( FLXAI_Cast::arr( $in['source_models'] ) as $src => $m ) {
				$m = sanitize_text_field( FLXAI_Cast::str( $m ) );
				if ( '' !== $m ) {
					$map[ sanitize_key( (string) $src ) ] = $m;
				}
			}
			$new['source_models'] = $map;
		}
		if ( array_key_exists( 'cache', $in ) ) {
			$v = FLXAI_Cast::str( $in['cache'] );
			if ( ! in_array( $v, array( 'off', '5m', '1h' ), true ) ) {
				return self::invalid( 'cache', __( 'The prompt cache must be off, 5 minutes or 1 hour.', 'flx-ai' ) );
			}
			$new['cache'] = $v;
		}
		if ( array_key_exists( 'effort', $in ) ) {
			$v = FLXAI_Cast::str( $in['effort'] );
			if ( ! in_array( $v, array( 'low', 'medium', 'high' ), true ) ) {
				return self::invalid( 'effort', __( 'Effort must be low, medium or high.', 'flx-ai' ) );
			}
			$new['effort'] = $v;
		}
		foreach ( array( 'thinking', 'limit_notify', 'chat_admin', 'chat_front', 'chat_widgets', 'web_on', 'web_fetch_on', 'image_tool_on', 'shell_on', 'log_bodies', 'delete_data' ) as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$new[ $k ] = FLXAI_Cast::bool( $in[ $k ] ) ? 1 : 0;
			}
		}
		foreach ( array( 'limit_day', 'limit_week', 'limit_month' ) as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$new[ $k ] = max( 0.0, round( FLXAI_Cast::float( is_string( $in[ $k ] ) ? str_replace( ',', '.', $in[ $k ] ) : $in[ $k ] ), 2 ) );
			}
		}
		if ( array_key_exists( 'limit_action', $in ) ) {
			$new['limit_action'] = 'warn' === FLXAI_Cast::str( $in['limit_action'] ) ? 'warn' : 'block';
		}
		if ( array_key_exists( 'limit_warn_pct', $in ) ) {
			$new['limit_warn_pct'] = max( 10, min( 99, FLXAI_Cast::int( $in['limit_warn_pct'], 80 ) ) );
		}
		if ( array_key_exists( 'chat_roles', $in ) ) {
			$roles             = array_map( 'sanitize_key', FLXAI_Cast::strings( $in['chat_roles'] ) );
			$new['chat_roles'] = array_values( array_intersect( $roles, array_map( 'strval', array_keys( wp_roles()->get_names() ) ) ) );
		}
		if ( array_key_exists( 'chat_mode', $in ) ) {
			$v                 = FLXAI_Cast::str( $in['chat_mode'] );
			$new['chat_mode'] = in_array( $v, array( 'dock', 'page' ), true ) ? $v : 'bubble';
		}
		if ( array_key_exists( 'chat_max_rounds', $in ) ) {
			$new['chat_max_rounds'] = max( 2, min( 30, FLXAI_Cast::int( $in['chat_max_rounds'], 12 ) ) );
		}
		if ( array_key_exists( 'chat_history', $in ) ) {
			$new['chat_history'] = max( 6, min( 200, FLXAI_Cast::int( $in['chat_history'], 40 ) ) );
		}
		if ( array_key_exists( 'chat_instructions', $in ) ) {
			$new['chat_instructions'] = sanitize_textarea_field( FLXAI_Cast::str( $in['chat_instructions'] ) );
		}
		if ( array_key_exists( 'render_url', $in ) ) {
			$new['render_url'] = esc_url_raw( trim( FLXAI_Cast::str( $in['render_url'] ) ), array( 'http', 'https' ) );
		}
		$catalog = FLXAI_Cast::arr( flxai_media_catalog() );
		foreach ( array(
			'media_image_model' => 'image',
			'media_video_model' => 'video',
		) as $k => $kind ) {
			if ( ! array_key_exists( $k, $in ) ) {
				continue;
			}
			$m = sanitize_text_field( FLXAI_Cast::str( $in[ $k ] ) );
			if ( ! isset( $catalog[ $m ] ) || FLXAI_Cast::str( FLXAI_Cast::arr( $catalog[ $m ] )['kind'] ?? '' ) !== $kind ) {
				return self::invalid( $k, __( 'Choose a model from the list.', 'flx-ai' ) );
			}
			$new[ $k ] = $m;
		}
		if ( array_key_exists( 'media_days', $in ) ) {
			$new['media_days'] = max( 7, min( 1825, FLXAI_Cast::int( $in['media_days'], 90 ) ) );
		}
		if ( array_key_exists( 'web_provider', $in ) ) {
			$w = sanitize_key( FLXAI_Cast::str( $in['web_provider'], 'auto' ) );
			if ( 'auto' !== $w && ! isset( FLXAI_Cast::arr( flxai_web_providers() )[ $w ] ) ) {
				return self::invalid( 'web_provider', __( 'Choose a search provider from the list.', 'flx-ai' ) );
			}
			$new['web_provider'] = $w;
		}
		if ( array_key_exists( 'web_search_month', $in ) ) {
			$new['web_search_month'] = max( 0, min( 1000000, FLXAI_Cast::int( $in['web_search_month'] ) ) );
		}
		if ( array_key_exists( 'web_search_cost', $in ) ) {
			$new['web_search_cost'] = max( 0.0, min( 1000.0, round( FLXAI_Cast::float( is_string( $in['web_search_cost'] ) ? str_replace( ',', '.', $in['web_search_cost'] ) : $in['web_search_cost'] ), 4 ) ) );
		}
		foreach ( array( 'log_days', 'log_body_days' ) as $k ) {
			if ( array_key_exists( $k, $in ) ) {
				$new[ $k ] = max( 0, min( 3650, FLXAI_Cast::int( $in[ $k ] ) ) );
			}
		}

		// API keys: written when a value comes in, removed on clear; everything else is left alone.
		$known = array_keys( self::key_catalog() );
		foreach ( FLXAI_Cast::arr( $in['secrets'] ?? array() ) as $row ) {
			$row  = FLXAI_Cast::arr( $row );
			$name = sanitize_key( FLXAI_Cast::str( $row['name'] ?? '' ) );
			if ( ! in_array( $name, $known, true ) ) {
				return self::invalid( 'secrets', __( 'This key is not one Flux AI uses.', 'flx-ai' ) );
			}
			$value = trim( FLXAI_Cast::str( $row['value'] ?? '' ) );
			if ( FLXAI_Cast::bool( $row['clear'] ?? false ) ) {
				$new[ $name ] = '';
			} elseif ( '' !== $value ) {
				$new[ $name ] = sanitize_text_field( $value );
			}
		}

		if ( ! $new ) {
			return self::get();
		}
		$before = array( flxai_setting( 'key_openrouter' ), flxai_setting( 'key_anthropic' ), flxai_setting( 'anthropic_workspace' ) );
		flxai_update_settings( $new );
		if ( array( flxai_setting( 'key_openrouter' ), flxai_setting( 'key_anthropic' ), flxai_setting( 'anthropic_workspace' ) ) !== $before ) {
			flxai_models_flush();
		}
		self::changed();
		return self::get();
	}

	/**
	 * Searches the model catalog (the picker). With no key saved the public OpenRouter list is used.
	 *
	 * @param string $search Part of an id, name or family; empty = the first models.
	 * @param int    $limit  1–200.
	 * @param bool   $fresh  Reload the lists from the providers first.
	 * @return list<FLXAI_Model>|WP_Error
	 */
	public static function models( string $search = '', int $limit = 50, bool $fresh = false ) {
		$all = flxai_models( $fresh );
		if ( $all instanceof WP_Error ) {
			$all->add_data( array( 'graphql_code' => 'INTERNAL' ) );
			return $all;
		}
		$q   = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $search ) ) : strtolower( trim( $search ) );
		$out = array();
		foreach ( FLXAI_Cast::arr( $all ) as $row ) {
			$m    = FLXAI_Cast::arr( $row );
			$id   = FLXAI_Cast::str( $m['id'] ?? '' );
			$name = FLXAI_Cast::str( $m['name'] ?? $id );
			$fam  = FLXAI_Cast::str( $m['family'] ?? '' );
			if ( '' === $id ) {
				continue;
			}
			if ( '' !== $q && false === strpos( strtolower( $id . ' ' . $name . ' ' . $fam ), $q ) ) {
				continue;
			}
			$out[] = new FLXAI_Model(
				$id,
				$name,
				$fam,
				FLXAI_Cast::str( $m['provider'] ?? 'openrouter' ),
				FLXAI_Cast::int( $m['ctx'] ?? 0 ),
				FLXAI_Cast::float( $m['in'] ?? 0 ),
				FLXAI_Cast::float( $m['out'] ?? 0 ),
				FLXAI_Cast::bool( $m['tools'] ?? false ),
				FLXAI_Cast::bool( $m['vision'] ?? false ),
				FLXAI_Cast::bool( $m['thinking'] ?? false )
			);
			if ( count( $out ) >= max( 1, min( 200, $limit ) ) ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Sends a short test message to a model (not counted against the spending limit).
	 *
	 * @param string $model Model id; empty = the default model.
	 * @return FLXAI_Test_Result|WP_Error
	 */
	public static function test_model( string $model ) {
		$t = microtime( true );
		$r = flxai_chat(
			array(
				array(
					'role'    => 'system',
					'content' => 'Kısa cevap ver.',
				),
				array(
					'role'    => 'user',
					'content' => 'Merhaba! Tek cümleyle kendini tanıt.',
				),
			),
			array(
				'source'       => 'flx-ai',
				'purpose'      => 'test',
				'model'        => sanitize_text_field( $model ),
				'max_tokens'   => 80,
				'timeout'      => 30,
				'ignore_limit' => true,
			)
		);
		if ( $r instanceof WP_Error ) {
			$r->add_data( array( 'graphql_code' => 'INVALID_INPUT' ) );
			return $r;
		}
		$r = FLXAI_Cast::arr( $r );
		return new FLXAI_Test_Result( trim( FLXAI_Cast::str( $r['content'] ?? '' ) ), (int) ( ( microtime( true ) - $t ) * 1000 ), '', '' );
	}

	/**
	 * Draws a sample image with the visual renderer. The address, user and password may be the ones in the
	 * form and not saved yet; an empty password falls back to the saved one. The password is never returned.
	 *
	 * @return FLXAI_Test_Result|WP_Error
	 */
	public static function test_render( string $url, string $user, string $pass ) {
		$url  = esc_url_raw( trim( $url ), array( 'http', 'https' ) );
		$user = sanitize_text_field( $user );
		$code = '<div style="padding:16px;border-radius:12px;background:var(--accent);color:#fff;font:600 16px/1.4 sans-serif">🌳 Flux AI · ' . esc_html__( 'Image renderer works', 'flx-ai' ) . '</div>';
		$svg  = '<svg viewBox="0 0 320 60" xmlns="http://www.w3.org/2000/svg"><rect width="320" height="60" rx="12" fill="#2f7d4f"/><text x="20" y="37" font-family="sans-serif" font-size="16" fill="#fff">Flux AI · ' . esc_html__( 'Image renderer works', 'flx-ai' ) . '</text></svg>';
		$t    = microtime( true );
		if ( '' !== $url ) {
			$res = flxai_render_gotenberg(
				flxai_widget_document( $code, false, 360 ),
				360,
				array(
					'url'  => $url,
					'user' => $user,
					'pass' => $pass,
				)
			);
		} elseif ( 'imagick' === flxai_widget_renderer() ) {
			$res = flxai_render_imagick( $svg, 360, false );
		} else {
			$res = new WP_Error( 'flxai_render_unavailable', __( 'Enter the Gotenberg address first.', 'flx-ai' ) );
		}
		if ( $res instanceof WP_Error ) {
			$res->add_data( array( 'graphql_code' => 'INVALID_INPUT' ) );
			return $res;
		}
		$res = FLXAI_Cast::arr( $res );
		$png = FLXAI_Cast::str( $res['data'] ?? '' );
		return new FLXAI_Test_Result( '', (int) ( ( microtime( true ) - $t ) * 1000 ), FLXAI_Cast::str( $res['via'] ?? '' ), 'data:image/png;base64,' . base64_encode( $png ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * @param string $field Input field.
	 */
	private static function invalid( string $field, string $message ): WP_Error {
		return new WP_Error(
			'flxai_invalid',
			$message,
			array(
				'graphql_code' => 'INVALID_INPUT',
				'field'        => $field,
			)
		);
	}

	/** Drops the cached reads (GraphQL tag). */
	public static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE_TAG, FLXAI_Usage_Service::CACHE_TAG ) );
		}
	}
}
