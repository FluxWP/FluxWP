<?php
/**
 * WhatsApp & SMS connections, WA management, server settings and WhatsApp Cloud setup.
 *
 * Classic admin-post handlers, REST callbacks and GraphQL resolvers all call this service
 * so validation, encryption, copyFrom, cache purges and hooks run identically once.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/**
 * Flux Mail connections service.
 *
 * @phpstan-type FlxiConnSecret array{set:bool,mask:string,fromConfig:bool}
 * @phpstan-type FlxiConnLastTest array{ok:bool,error:string,at:array<string,mixed>|null}
 * @phpstan-type FlxiWaStateView array{state:string,me:string,name:string,quality:string,at:array<string,mixed>|null,error:string}
 * @phpstan-type FlxiConnView array<string,mixed>
 * @phpstan-type FlxiWacloudSetupView array{webhookUrl:string,webhookVerifyValue:string,signed:bool,hasWaba:bool,openChats:int,error:string,phone:array<string,string>|null,lastVerifyAt:array<string,mixed>|null,lastEventAt:array<string,mixed>|null,badSignatureAt:array<string,mixed>|null,noSecretAt:array<string,mixed>|null}
 */
class FLXI_Connection_Service {

	/**
	 * GraphQL name => array(stored config key, kind).
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function config_map(): array {
		return array(
			'baseUrl'     => array( 'base_url', 'string' ),
			'instance'    => array( 'instance', 'string' ),
			'version'     => array( 'version', 'string' ),
			'linkPreview' => array( 'link_preview', 'bool' ),
			'typing'      => array( 'typing', 'int' ),
			'delay'       => array( 'delay', 'int' ),
			'delayMax'    => array( 'delay_max', 'int' ),
			'batchSize'   => array( 'batch_size', 'int' ),
			'batchPause'  => array( 'batch_pause', 'int' ),
			'hourlyLimit' => array( 'hourly_limit', 'int' ),
			'dailyLimit'  => array( 'daily_limit', 'int' ),
			'warmup'      => array( 'warmup', 'bool' ),
			'quietFrom'   => array( 'quiet_from', 'string' ),
			'quietTo'     => array( 'quiet_to', 'string' ),
			'skipNoWa'    => array( 'skip_no_wa', 'bool' ),
			'failStop'    => array( 'fail_stop', 'int' ),
			'typingAuto'  => array( 'typing_auto', 'bool' ),
			'phoneId'     => array( 'phone_id', 'string' ),
			'wabaId'      => array( 'waba_id', 'string' ),
			'graph'       => array( 'graph', 'string' ),
			'username'    => array( 'username', 'string' ),
			'header'      => array( 'header', 'string' ),
			'iysfilter'   => array( 'iysfilter', 'string' ),
			'encoding'    => array( 'encoding', 'string' ),
			'partnercode' => array( 'partnercode', 'string' ),
		);
	}

	/**
	 * GraphQL secret name => stored config key.
	 *
	 * @return array<string,string>
	 */
	public static function secret_map(): array {
		return array(
			'apiKey'    => 'api_key',
			'token'     => 'token',
			'appSecret' => 'app_secret',
			'password'  => 'password',
		);
	}

	/**
	 * Map a stored config key back to the GraphQL field name for INVALID_INPUT errors.
	 */
	public static function field_name( string $stored_key ): string {
		foreach ( self::config_map() as $gql => $row ) {
			if ( $row[0] === $stored_key ) {
				return $gql;
			}
		}
		foreach ( self::secret_map() as $gql => $key ) {
			if ( $key === $stored_key ) {
				return $gql;
			}
		}
		return $stored_key;
	}

	/**
	 * Tags the GraphQL response with the connections cache tag.
	 */
	public static function tag(): void {
		if ( function_exists( 'flx_gql_cache_tag' ) ) {
			flx_gql_cache_tag( 'flx-mail:connections' );
		}
	}

	/**
	 * Purges the connections cache tag after writes.
	 */
	public static function purge(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( 'flx-mail:connections' );
		}
	}

	/**
	 * Formats a timestamp, millisecond epoch, MySQL GMT datetime or ISO string as FlxDateTime.
	 *
	 * @param mixed $val Time value.
	 * @return array<string,mixed>|null
	 */
	public static function dt( mixed $val ): ?array {
		if ( ! function_exists( 'flx_gql_datetime' ) ) {
			return null;
		}
		if ( null === $val || '' === $val || 0 === $val || '0' === $val || '0000-00-00 00:00:00' === $val ) {
			return null;
		}
		if ( is_numeric( $val ) ) {
			$num = (int) $val;
			if ( $num <= 0 ) {
				return null;
			}
			if ( $num > 20000000000 ) {
				$num = (int) floor( $num / 1000 );
			}
			return flx_gql_datetime( $num );
		}
		$raw = trim( flxi_str( $val ) );
		if ( '' === $raw ) {
			return null;
		}
		if ( str_contains( $raw, 'T' ) || str_ends_with( $raw, 'Z' ) || 1 === preg_match( '/[+-]\d{2}:?\d{2}$/', $raw ) ) {
			$ts = strtotime( $raw );
			return false !== $ts && $ts > 0 ? flx_gql_datetime( $ts ) : null;
		}
		return flx_gql_datetime( $raw );
	}

	/**
	 * Form field definitions of one connection type, keyed with GraphQL input names.
	 * The classic form renders the same definitions, so both screens show the same fields.
	 *
	 * @param array<string,mixed> $def One `flxi_connection_types` entry.
	 * @return list<array{name:string,kind:string,label:string,help:string,required:bool,placeholder:string,defaultValue:string,options:list<array{key:string,label:string}>}>
	 */
	private static function type_fields( array $def ): array {
		$out = array();
		foreach ( flxi_rows( $def['fields'] ?? array() ) as $f ) {
			$key  = flxi_str( $f['key'] ?? '' );
			$opts = array();
			$raw  = $f['options'] ?? array();
			foreach ( is_array( $raw ) ? $raw : array() as $ov => $ol ) {
				$opts[] = array(
					'key'   => (string) $ov,
					'label' => flxi_str( $ol ),
				);
			}
			$out[] = array(
				'name'         => '' !== $key ? self::field_name( $key ) : '',
				'kind'         => flxi_str( $f['type'] ?? 'text' ),
				'label'        => flxi_str( $f['label'] ?? '' ),
				'help'         => flxi_str( $f['help'] ?? '' ),
				'required'     => ! empty( $f['required'] ),
				'placeholder'  => flxi_str( $f['placeholder'] ?? '' ),
				'defaultValue' => flxi_str( $f['default'] ?? '' ),
				'options'      => $opts,
			);
		}
		return $out;
	}

	/**
	 * Metadata the React Connections screen needs (types and their fields, sample files, CRM/Certificates flag).
	 *
	 * @return array{flxsActive:bool,sampleImageUrl:string,sampleAudioUrl:string,types:list<array<string,mixed>>}
	 */
	public static function meta(): array {
		$types = array();
		foreach ( flxi_arr( flxi_connection_types() ) as $key => $def ) {
			$def     = flxi_arr( $def );
			$types[] = array(
				'type'      => flxi_str( $key ),
				'channel'   => flxi_str( $def['channel'] ?? 'whatsapp' ),
				'label'     => flxi_str( $def['label'] ?? $key ),
				'help'      => flxi_str( $def['help'] ?? '' ),
				'scenarios' => self::strings( flxi_test_scenarios( flxi_str( $key ) ) ),
				'evolution' => ! empty( $def['evo'] ),
				'cloud'     => ! empty( $def['cloud'] ),
				'fields'    => self::type_fields( $def ),
			);
		}
		return array(
			'flxsActive'     => function_exists( 'flxi_flxs_active' ) && (bool) flxi_flxs_active(),
			'sampleImageUrl' => FLXI_URL . 'assets/test/ornek.jpg',
			'sampleAudioUrl' => FLXI_URL . 'assets/test/ornek.ogg',
			'types'          => $types,
		);
	}

	/**
	 * A list of strings from any value.
	 *
	 * @param mixed $value Value.
	 * @return list<string>
	 */
	private static function strings( $value ): array {
		$out = array();
		foreach ( is_array( $value ) ? $value : array() as $item ) {
			$out[] = flxi_str( $item );
		}
		return $out;
	}

	/**
	 * A legacy result handed on unchanged: arrays keep their keys, a WP_Error stays a WP_Error.
	 *
	 * @param mixed $value Value from a legacy flxi_* function.
	 * @return array<mixed>|WP_Error
	 */
	private static function same( $value ) {
		return $value instanceof WP_Error ? $value : (array) $value;
	}

	/**
	 * Stored test results, keyed by connection id (integer keys, so not flxi_arr()).
	 *
	 * @return array<int|string,mixed>
	 */
	private static function tests(): array {
		$tests = get_option( 'flxi_conn_tests', array() );
		return is_array( $tests ) ? $tests : array();
	}

	/**
	 * Lists saved connections formatted for GraphQL.
	 *
	 * @return list<FlxiConnView>
	 */
	public static function list( string $channel = '', bool $only_active = false ): array {
		self::tag();
		$tests = self::tests();
		$out   = array();
		foreach ( flxi_rows( flxi_list_connections( $channel, $only_active ) ) as $row ) {
			$out[] = self::format( $row, $tests );
		}
		return $out;
	}

	/**
	 * Returns one connection formatted for GraphQL, or null if not found.
	 *
	 * @return FlxiConnView|null
	 */
	public static function one( int $id ): ?array {
		self::tag();
		if ( $id <= 0 ) {
			return null;
		}
		$row = flxi_get_connection( $id );
		if ( ! is_array( $row ) ) {
			return null;
		}
		$tests = self::tests();
		return self::format( flxi_arr( $row ), $tests );
	}

	/**
	 * Formats one DB connection row for GraphQL, masking all secrets and excluding webhookVerifyValue.
	 *
	 * @param array<string,mixed> $row   Prepared connection row.
	 * @param array<int|string,mixed> $tests Stored `flxi_conn_tests` option.
	 * @return FlxiConnView
	 */
	private static function format( array $row, array $tests ): array {
		$id     = flxi_int( $row['id'] ?? 0 );
		$type   = flxi_str( $row['type'] ?? '' );
		$cfg    = flxi_arr( $row['config'] ?? array() );
		$defs   = flxi_arr( flxi_connection_types() );
		$def    = flxi_arr( $defs[ $type ] ?? array() );
		$f_defs = array();
		foreach ( flxi_rows( $def['fields'] ?? array() ) as $f ) {
			$fk = flxi_str( $f['key'] ?? '' );
			if ( '' !== $fk ) {
				$f_defs[ $fk ] = $f;
			}
		}

		$out = array(
			'databaseId'       => $id,
			'name'             => flxi_str( $row['name'] ?? '' ),
			'type'             => $type,
			'typeLabel'        => flxi_str( $def['label'] ?? $type ),
			'channel'          => flxi_str( $row['channel'] ?? 'whatsapp' ),
			'active'           => ! empty( $row['active'] ),
			'isDefault'        => ! empty( $row['is_default'] ),
			'origin'           => flxi_str( $row['origin'] ?? '' ),
			'scenarios'        => self::strings( flxi_test_scenarios( $type ) ),
			'appSecretMissing' => 'wacloud' === $type && '' === flxi_str( $cfg['app_secret'] ?? '' ),
			'createdAt'        => self::dt( $row['created_at'] ?? null ),
			'updatedAt'        => self::dt( $row['updated_at'] ?? null ),
		);

		foreach ( self::config_map() as $gql => $meta ) {
			$k    = $meta[0];
			$kind = $meta[1];
			$has  = array_key_exists( $k, $cfg );
			$raw  = $has ? $cfg[ $k ] : ( $f_defs[ $k ]['default'] ?? ( 'int' === $kind ? 0 : ( 'bool' === $kind ? false : '' ) ) );
			if ( 'bool' === $kind ) {
				$out[ $gql ] = ! empty( $raw );
			} elseif ( 'int' === $kind ) {
				$out[ $gql ] = flxi_int( $raw );
			} else {
				$out[ $gql ] = flxi_str( $raw );
			}
		}

		foreach ( self::secret_map() as $gql => $k ) {
			$plain       = flxi_str( $cfg[ $k ] ?? '' );
			$out[ $gql ] = array(
				'set'        => '' !== $plain,
				'mask'       => '' !== $plain ? flxi_str( flxi_mask( $plain ) ) : '',
				'fromConfig' => false,
			);
		}

		$t = $tests[ $id ] ?? ( $tests[ (string) $id ] ?? null );
		if ( is_array( $t ) ) {
			$out['lastTest'] = array(
				'ok'    => ! empty( $t['ok'] ),
				'error' => flxi_str( $t['error'] ?? '' ),
				'at'    => self::dt( $t['at'] ?? null ),
			);
		} else {
			$out['lastTest'] = null;
		}

		$out['waState'] = flxi_is_wa_type( $type ) ? self::format_wa_state( flxi_arr( flxi_wa_cached_state( $id ) ) ) : null;
		return $out;
	}

	/**
	 * Formats a WA connection state array for GraphQL.
	 *
	 * @param array<string,mixed> $st Raw state array.
	 * @return FlxiWaStateView
	 */
	public static function format_wa_state( array $st ): array {
		return array(
			'state'   => flxi_str( $st['state'] ?? '' ),
			'me'      => flxi_str( $st['me'] ?? '' ),
			'name'    => flxi_str( $st['name'] ?? '' ),
			'quality' => flxi_str( $st['quality'] ?? '' ),
			'at'      => self::dt( $st['at'] ?? null ),
			'error'   => flxi_str( $st['error'] ?? '' ),
		);
	}

	/**
	 * Saves a connection from the classic form (admin-post). Same copy-from rule and
	 * the same stored result as before the service existed.
	 *
	 * @param int                 $id        Connection ID (0 = create).
	 * @param string              $type      Connection type key.
	 * @param string              $name      Already sanitized name.
	 * @param array<string,mixed> $cfg       Posted cfg[] map (stored keys).
	 * @param bool                $active    Active flag.
	 * @param int                 $copy_from Existing connection whose server details a new record reuses.
	 * @return int|WP_Error Saved connection ID or WP_Error.
	 */
	public static function save_form( int $id, string $type, string $name, array $cfg, bool $active, int $copy_from = 0 ) {
		$from = $id > 0 ? null : flxi_get_connection( $copy_from );
		if ( is_array( $from ) && flxi_str( $from['type'] ?? '' ) === $type ) {
			$from_cfg        = flxi_arr( $from['config'] ?? array() );
			$cfg['base_url'] = $from_cfg['base_url'] ?? '';
			$cfg['api_key']  = $from_cfg['api_key'] ?? '';
		}
		$res = flxi_save_connection( $id, $type, $name, $cfg, $active );
		if ( ! is_wp_error( $res ) ) {
			self::purge();
		}
		return $res;
	}

	/**
	 * Saves a connection from the GraphQL input. Omitted fields keep the stored value,
	 * an empty secret keeps it, "__clear__" removes it.
	 *
	 * @param int                 $id        Connection ID (0 = create).
	 * @param string              $type      Connection type key (create only; updates keep the stored type).
	 * @param array<string,mixed> $input     GraphQL input map.
	 * @param int                 $copy_from Existing connection ID to copy server details from (create only).
	 * @return int|WP_Error Saved connection ID or WP_Error.
	 */
	public static function save_input( int $id, string $type, array $input, int $copy_from = 0 ) {
		$old = $id > 0 ? flxi_get_connection( $id ) : null;
		if ( $id > 0 && ! is_array( $old ) ) {
			return new WP_Error( 'flxi_not_found', __( 'Connection not found.', 'flx-mail' ), array( 'status' => 404 ) );
		}
		if ( is_array( $old ) ) {
			$type = flxi_str( $old['type'] ?? $type );
		}
		$type  = sanitize_key( $type );
		$types = flxi_arr( flxi_connection_types() );
		if ( ! isset( $types[ $type ] ) ) {
			return new WP_Error( 'flxi_type', __( 'Unknown connection type.', 'flx-mail' ), array( 'status' => 400, 'field' => 'type' ) );
		}
		$def   = flxi_arr( $types[ $type ] );
		$old_c = is_array( $old ) ? flxi_arr( $old['config'] ?? array() ) : array();
		$name  = array_key_exists( 'name', $input ) && null !== $input['name']
			? sanitize_text_field( flxi_str( $input['name'] ) )
			: ( is_array( $old ) ? flxi_str( $old['name'] ?? '' ) : '' );
		$active = array_key_exists( 'active', $input ) && null !== $input['active']
			? ! empty( $input['active'] )
			: ( is_array( $old ) ? ! empty( $old['active'] ) : true );
		$is_default = array_key_exists( 'isDefault', $input ) && null !== $input['isDefault']
			? ! empty( $input['isDefault'] )
			: null;

		$cfg   = array();
		$clear = array();
		foreach ( flxi_rows( $def['fields'] ?? array() ) as $f ) {
			$k    = flxi_str( $f['key'] ?? '' );
			$kind = flxi_str( $f['type'] ?? '' );
			if ( '' === $k || 'heading' === $kind ) {
				continue;
			}
			$gql = self::field_name( $k );
			$set = array_key_exists( $gql, $input ) && null !== $input[ $gql ];
			if ( 'password' === $kind ) {
				if ( $set ) {
					$v = flxi_str( $input[ $gql ] );
					if ( FLXI_Settings_Service::CLEAR === trim( $v ) ) {
						$clear[] = $k;
					} else {
						$cfg[ $k ] = $v;
					}
				}
				continue;
			}
			if ( $set ) {
				$cfg[ $k ] = 'checkbox' === $kind ? ( ! empty( $input[ $gql ] ) ? 1 : 0 ) : ( is_scalar( $input[ $gql ] ) ? $input[ $gql ] : '' );
			} elseif ( array_key_exists( $k, $old_c ) ) {
				$cfg[ $k ] = $old_c[ $k ];
			} elseif ( 'checkbox' === $kind ) {
				$cfg[ $k ] = ! empty( $f['default'] ) ? 1 : 0;
			}
		}

		if ( 0 === $id && $copy_from > 0 ) {
			$from = flxi_get_connection( $copy_from );
			if ( ! is_array( $from ) || flxi_str( $from['type'] ?? '' ) !== $type ) {
				return new WP_Error( 'flxi_copy_from', __( 'Cannot copy server details from that connection.', 'flx-mail' ), array( 'status' => 400, 'field' => 'copyFrom' ) );
			}
			$from_cfg        = flxi_arr( $from['config'] ?? array() );
			$cfg['base_url'] = $from_cfg['base_url'] ?? '';
			$cfg['api_key']  = $from_cfg['api_key'] ?? '';
		}

		$res = flxi_save_connection( $id, $type, $name, $cfg, $active, $is_default, $clear );
		if ( is_wp_error( $res ) ) {
			return self::map_error( $res );
		}
		self::purge();
		return $res;
	}

	/**
	 * Rewrites a stored config key in a WP_Error field to the GraphQL input name.
	 */
	private static function map_error( WP_Error $err ): WP_Error {
		$data = $err->get_error_data();
		if ( is_array( $data ) && isset( $data['field'] ) && is_string( $data['field'] ) ) {
			$data['field'] = self::field_name( $data['field'] );
			$data['status'] = 400;
			return new WP_Error( $err->get_error_code(), $err->get_error_message(), $data );
		}
		return $err;
	}

	/**
	 * Deletes a connection (optionally deleting the remote Evolution/Flux WPAPI instance first).
	 *
	 * @return true|WP_Error
	 */
	public static function delete( int $id, bool $remote = false, bool $require_exists = true ) {
		if ( $require_exists ) {
			$conn = flxi_get_connection( $id );
			if ( ! is_array( $conn ) ) {
				return new WP_Error( 'flxi_not_found', __( 'Connection not found.', 'flx-mail' ), array( 'status' => 404 ) );
			}
		}
		if ( $remote ) {
			$r = flxi_wa_delete_remote( $id );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		flxi_delete_connection( $id );
		self::purge();
		return true;
	}

	/**
	 * Marks a connection as the default for its channel.
	 *
	 * @return true|WP_Error
	 */
	public static function set_default( int $id, bool $require_exists = true ) {
		if ( $require_exists ) {
			$conn = flxi_get_connection( $id );
			if ( ! is_array( $conn ) ) {
				return new WP_Error( 'flxi_not_found', __( 'Connection not found.', 'flx-mail' ), array( 'status' => 404 ) );
			}
		}
		flxi_set_default_connection( $id );
		self::purge();
		return true;
	}

	/**
	 * Runs a connection check or scenario test and returns REST-compatible output.
	 *
	 * @return array<string,mixed>
	 */
	public static function test_rest( WP_REST_Request $r ): array {
		$scenario = sanitize_key( flxi_str( $r['scenario'] ) );
		$id       = flxi_int( $r['id'] );
		$to       = sanitize_text_field( flxi_str( $r['to'] ) );
		if ( '' !== $scenario && '0' !== $scenario ) {
			$tpl = flxi_arr( $r['template'] );
			$out = flxi_arr(
				flxi_test_scenario(
					$id,
					$scenario,
					$to,
					array(
						'text'     => sanitize_textarea_field( flxi_str( $r['text'] ) ),
						'caption'  => sanitize_text_field( flxi_str( $r['caption'] ) ),
						'template' => array(
							'name'   => sanitize_text_field( flxi_str( $tpl['name'] ?? '' ) ),
							'lang'   => sanitize_text_field( flxi_str( $tpl['lang'] ?? 'tr' ) ),
							'params' => array_map( 'sanitize_text_field', self::strings( $tpl['params'] ?? array() ) ),
						),
					)
				)
			);
			self::purge();
			return $out;
		}
		$out = flxi_arr( flxi_test_connection( $id, $to ) );
		self::purge();
		return $out;
	}

	/**
	 * Runs a connection check or scenario test from GraphQL input.
	 *
	 * @param array<string,mixed> $input GraphQL mutation input.
	 * @return array{ok:bool,error:string}
	 */
	public static function test( array $input ): array {
		$id       = flxi_int( $input['id'] ?? 0 );
		$to       = sanitize_text_field( flxi_str( $input['to'] ?? '' ) );
		$scenario = sanitize_key( flxi_str( $input['scenario'] ?? '' ) );
		$args     = array(
			'text'    => sanitize_textarea_field( flxi_str( $input['text'] ?? '' ) ),
			'caption' => sanitize_text_field( flxi_str( $input['caption'] ?? '' ) ),
		);
		$tpl = flxi_arr( $input['template'] ?? array() );
		if ( $tpl ) {
			$args['template'] = array(
				'name'   => sanitize_text_field( flxi_str( $tpl['name'] ?? '' ) ),
				'lang'   => sanitize_text_field( flxi_str( $tpl['lang'] ?? 'tr' ) ),
				'params' => array_map( 'sanitize_text_field', self::strings( $tpl['params'] ?? array() ) ),
			);
		}
		$res = '' !== $scenario
			? flxi_test_scenario( $id, $scenario, $to, $args )
			: flxi_test_connection( $id, $to );
		self::purge();
		$res = flxi_arr( $res );
		return array(
			'ok'    => ! empty( $res['ok'] ),
			'error' => flxi_str( $res['error'] ?? '' ),
		);
	}

	/**
	 * Raw WA state for REST parity.
	 *
	 * @return array<string,mixed>
	 */
	public static function wa_state_raw( int $id, bool $fresh = false ): array {
		$out = flxi_arr( flxi_wa_state( $id, $fresh ) );
		if ( $fresh ) {
			self::purge();
		}
		return $out;
	}

	/**
	 * GraphQL WA state.
	 *
	 * @return FlxiWaStateView
	 */
	public static function wa_state( int $id, bool $fresh = false ): array {
		self::tag();
		return self::format_wa_state( self::wa_state_raw( $id, $fresh ) );
	}

	/**
	 * Raw WA connect for REST parity.
	 *
	 * @return array<mixed>|WP_Error
	 */
	public static function wa_connect_raw( int $id, string $number = '' ) {
		$res = self::same( flxi_wa_connect( $id, $number ) );
		if ( ! is_wp_error( $res ) ) {
			self::purge();
		}
		return $res;
	}

	/**
	 * GraphQL WA connect (QR / pairing code).
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function wa_connect( int $id, string $number = '' ) {
		$res = self::wa_connect_raw( $id, $number );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$r = flxi_arr( $res );
		return array(
			'state'   => flxi_str( $r['state'] ?? 'connecting' ),
			'qr'      => flxi_str( $r['qr'] ?? '' ),
			'pairing' => flxi_str( $r['pairing'] ?? '' ),
			'me'      => flxi_str( $r['me'] ?? '' ),
			'name'    => flxi_str( $r['name'] ?? '' ),
			'error'   => flxi_str( $r['error'] ?? '' ),
			'at'      => self::dt( $r['at'] ?? null ),
		);
	}

	/**
	 * Raw WA restart for REST parity.
	 *
	 * @return array<mixed>|WP_Error
	 */
	public static function wa_restart_raw( int $id ) {
		$res = self::same( flxi_wa_restart( $id ) );
		if ( ! is_wp_error( $res ) ) {
			self::purge();
		}
		return $res;
	}

	/**
	 * GraphQL WA restart.
	 *
	 * @return FlxiWaStateView|WP_Error
	 */
	public static function wa_restart( int $id ) {
		$res = self::wa_restart_raw( $id );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return self::format_wa_state( flxi_arr( $res ) );
	}

	/**
	 * Raw WA logout for REST parity.
	 *
	 * @return array<mixed>|WP_Error
	 */
	public static function wa_logout_raw( int $id ) {
		$res = self::same( flxi_wa_logout( $id ) );
		if ( ! is_wp_error( $res ) ) {
			self::purge();
		}
		return $res;
	}

	/**
	 * GraphQL WA logout.
	 *
	 * @return array{ok:bool,state:FlxiWaStateView}|WP_Error
	 */
	public static function wa_logout( int $id ) {
		$res = self::wa_logout_raw( $id );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return array(
			'ok'    => true,
			'state' => self::format_wa_state( flxi_arr( $res ) ),
		);
	}

	/**
	 * Evolution / Flux WPAPI instances on the same server.
	 *
	 * @return list<array{name:string,state:string,me:string,used:int}>|WP_Error
	 */
	public static function wa_instances( int $id ) {
		/** @var list<array{name:string,state:string,me:string,used:int}>|WP_Error $res */
		$res = flxi_wa_server_instances( $id );
		return $res;
	}

	/**
	 * WhatsApp groups for an Evolution / Flux WPAPI connection.
	 *
	 * @return list<array{id:string,name:string,size:int,announce:bool}>|WP_Error
	 */
	public static function wa_groups( int $id, bool $fresh = false ) {
		/** @var list<array{id:string,name:string,size:int,announce:bool}>|WP_Error $res */
		$res = flxi_whatsapp_groups( $id, $fresh );
		return $res;
	}

	/**
	 * Raw Flux WPAPI server info for REST parity.
	 *
	 * @return array<mixed>|WP_Error
	 */
	public static function wa_server_get_raw( int $id ) {
		return self::same( flxi_wa_server_get( $id ) );
	}

	/**
	 * GraphQL Flux WPAPI server info.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function wa_server_get( int $id ) {
		$res = self::wa_server_get_raw( $id );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		return self::format_wa_server( flxi_arr( $res ) );
	}

	/**
	 * Raw Flux WPAPI server update / DB test for REST parity.
	 *
	 * @param array<string,mixed> $in Request payload.
	 * @return array<mixed>|WP_Error
	 */
	public static function wa_server_save_raw( int $id, array $in ) {
		return self::same( flxi_wa_server_save( $id, $in ) );
	}

	/**
	 * GraphQL Flux WPAPI server update / DB test.
	 *
	 * @param array<string,mixed> $in GraphQL mutation input.
	 * @return array{ok:bool,error:string,server:array<string,mixed>|null}|WP_Error
	 */
	public static function wa_server_save( int $id, array $in ) {
		$payload = array();
		if ( array_key_exists( 'publicUrl', $in ) && null !== $in['publicUrl'] ) {
			$payload['publicUrl'] = flxi_str( $in['publicUrl'] );
		}
		if ( array_key_exists( 'keepAlive', $in ) && null !== $in['keepAlive'] ) {
			$payload['keepAlive'] = ! empty( $in['keepAlive'] );
		}
		if ( isset( $in['db'] ) && is_array( $in['db'] ) ) {
			$d  = flxi_arr( $in['db'] );
			$db = array(
				'driver'   => 'mysql' === strtolower( flxi_str( $d['driver'] ?? '' ) ) ? 'mysql' : 'file',
				'host'     => flxi_str( $d['host'] ?? 'localhost' ),
				'port'     => flxi_int( $d['port'] ?? 3306, 3306 ),
				'database' => flxi_str( $d['database'] ?? '' ),
				'user'     => flxi_str( $d['user'] ?? '' ),
				'prefix'   => flxi_str( $d['prefix'] ?? 'flxw_' ),
			);
			$pass = flxi_str( $d['password'] ?? '' );
			if ( '' !== $pass && FLXI_Settings_Service::CLEAR !== $pass ) {
				$db['password'] = $pass;
			}
			$payload['db'] = $db;
		}
		$test = ! empty( $in['test'] );
		if ( $test ) {
			$payload['test'] = true;
		}

		$res = self::wa_server_save_raw( $id, $payload );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$r = flxi_arr( $res );
		if ( $test ) {
			return array(
				'ok'     => ! empty( $r['ok'] ),
				'error'  => flxi_str( $r['error'] ?? '' ),
				'server' => null,
			);
		}
		return array(
			'ok'     => true,
			'error'  => '',
			'server' => self::format_wa_server( $r ),
		);
	}

	/**
	 * Formats clean bridge server info for GraphQL. Password is never present.
	 *
	 * @param array<string,mixed> $j Cleaned server payload.
	 * @return array<string,mixed>
	 */
	private static function format_wa_server( array $j ): array {
		$db   = flxi_arr( $j['db'] ?? array() );
		$lock = flxi_arr( $j['locked'] ?? array() );
		$ka   = flxi_arr( $j['keepAliveState'] ?? array() );
		return array(
			'version'        => flxi_str( $j['version'] ?? '' ),
			'baileys'        => flxi_str( $j['baileys'] ?? '' ),
			'node'           => flxi_str( $j['node'] ?? '' ),
			'store'          => flxi_str( $j['store'] ?? '' ),
			'storeError'     => flxi_str( $j['storeError'] ?? '' ),
			'lastKeepAlive'  => self::dt( $j['lastKeepAlive'] ?? null ),
			'keepAliveOk'    => ! array_key_exists( 'ok', $ka ) || ! empty( $ka['ok'] ),
			'keepAliveError' => flxi_str( $ka['error'] ?? '' ),
			'ffmpeg'         => ! empty( $j['ffmpeg'] ),
			'publicUrl'      => flxi_str( $j['publicUrl'] ?? '' ),
			'keepAlive'      => ! empty( $j['keepAlive'] ),
			'dbLocked'       => ! empty( $lock['db'] ),
			'db'             => array(
				'driver'      => 'mysql' === flxi_str( $db['driver'] ?? '' ) ? 'mysql' : 'file',
				'host'        => flxi_str( $db['host'] ?? 'localhost' ),
				'port'        => max( 1, flxi_int( $db['port'] ?? 3306, 3306 ) ),
				'database'    => flxi_str( $db['database'] ?? '' ),
				'user'        => flxi_str( $db['user'] ?? '' ),
				'passwordSet' => ! empty( $db['passwordSet'] ),
				'prefix'      => flxi_str( $db['prefix'] ?? 'flxw_' ),
			),
		);
	}

	/**
	 * Raw WhatsApp Cloud setup for REST parity.
	 *
	 * @return array<mixed>|WP_Error
	 */
	public static function wacloud_setup_raw( int $id ) {
		$had_tok = '' !== flxi_wacloud_verify_token( $id );
		$res     = self::same( flxi_wacloud_setup( $id ) );
		if ( ! is_wp_error( $res ) && ! $had_tok ) {
			self::purge();
		}
		return $res;
	}

	/**
	 * GraphQL WhatsApp Cloud setup; returns null when the connection does not exist or is not wacloud.
	 *
	 * @return FlxiWacloudSetupView|null
	 */
	public static function wacloud_setup( int $id ): ?array {
		$res = self::wacloud_setup_raw( $id );
		if ( is_wp_error( $res ) ) {
			return null;
		}
		$r    = flxi_arr( $res );
		$hook = flxi_arr( $r['hook'] ?? array() );
		$info = is_array( $r['info'] ?? null ) ? flxi_arr( $r['info'] ) : null;
		return array(
			'webhookUrl'         => flxi_str( $r['webhook'] ?? '' ),
			'webhookVerifyValue' => flxi_str( $r['verify'] ?? '' ),
			'signed'             => ! empty( $r['signed'] ),
			'hasWaba'            => ! empty( $r['has_waba'] ),
			'openChats'          => flxi_int( $r['open_chats'] ?? 0 ),
			'error'              => flxi_str( $r['error'] ?? '' ),
			'phone'              => null === $info ? null : array(
				'displayPhoneNumber'     => flxi_str( $info['display_phone_number'] ?? '' ),
				'verifiedName'           => flxi_str( $info['verified_name'] ?? '' ),
				'qualityRating'          => flxi_str( $info['quality_rating'] ?? '' ),
				'nameStatus'             => flxi_str( $info['name_status'] ?? '' ),
				'codeVerificationStatus' => flxi_str( $info['code_verification_status'] ?? '' ),
				'platformType'           => flxi_str( $info['platform_type'] ?? '' ),
			),
			'lastVerifyAt'       => self::dt( $hook['verify'] ?? null ),
			'lastEventAt'        => self::dt( $hook['event'] ?? null ),
			'badSignatureAt'     => self::dt( $hook['bad_signature'] ?? null ),
			'noSecretAt'         => self::dt( $hook['no_secret'] ?? null ),
		);
	}

	/**
	 * Approved/pending templates for a WhatsApp Cloud connection.
	 *
	 * @return list<array{name:string,lang:string,status:string,category:string,body:string,params:int}>|WP_Error
	 */
	public static function wacloud_templates( int $id ) {
		/** @var list<array{name:string,lang:string,status:string,category:string,body:string,params:int}>|WP_Error $res */
		$res = flxi_wacloud_templates( $id );
		return $res;
	}
}
