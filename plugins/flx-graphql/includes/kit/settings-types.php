<?php
/**
 * Settings types generated from flx_settings_schema.
 *
 * Every option a package declares in `flx_settings_schema` (the same schema presets and Flux AI write
 * through) becomes a typed GraphQL object without hand-written types:
 *
 *     query { flxSettings { flxbWcLook { formStyle primary } } }
 *     mutation { flxSettingsUpdate( input: { flxbWcLook: { formStyle: STEPS } } ) { settings { flxbWcLook { formStyle } } errors { field message } } }
 *
 * Names: option "flxb_wc_look" → field flxbWcLook, type FlxSettingsFlxbWcLook (input …Input). A schema
 * entry may set 'graphql' => 'DonateLook' for a nicer name (FlxSettingsDonateLook, field donateLook).
 * Field types: bool → Boolean, int/page → Int, number → Float, enum → enum (String when a value is not a
 * valid GraphQL name), object/list → FlxJSON, others → String. Keys that look like secrets are never exposed.
 * Read and write need `manage_options` (or the entry's 'cap').
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

add_action( 'flx_gql_register_core', 'flx_gql_register_settings_types', 30 );

/**
 * Normalised settings schema: [ option => { field, type, cap, label, fields: [ key => { gql, type, enum, default, desc } ] } ].
 *
 * @return array<string,array<string,mixed>>
 */
function flx_gql_settings_schema(): array {
	$raw = apply_filters( 'flx_settings_schema', array() );
	$out = array();
	$used = array();
	foreach ( is_array( $raw ) ? $raw : array() as $option => $entry ) {
		if ( ! is_string( $option ) || ! is_array( $entry ) || empty( $entry['fields'] ) || ! is_array( $entry['fields'] ) ) {
			continue;
		}
		$base = isset( $entry['graphql'] ) && is_string( $entry['graphql'] ) ? flx_gql_pascal( $entry['graphql'] ) : flx_gql_pascal( $option );
		if ( '' === $base || isset( $used[ $base ] ) || ! preg_match( '/^[A-Z]/', $base ) ) {
			continue;
		}
		$used[ $base ] = true;
		$fields        = array();
		foreach ( $entry['fields'] as $key => $f ) {
			if ( ! is_string( $key ) || ! is_array( $f ) || preg_match( '/secret|password|passwd|token|api_?key|private/i', $key ) ) {
				continue;
			}
			$gql = flx_gql_camel( $key );
			if ( '' === $gql || ! preg_match( '/^[a-z][A-Za-z0-9]*$/', $gql ) || isset( $fields[ $gql ] ) ) {
				continue;
			}
			$fields[ $gql ] = array(
				'key'     => $key,
				'type'    => (string) ( $f['type'] ?? 'string' ),
				'enum'    => isset( $f['enum'] ) && is_array( $f['enum'] ) ? array_values( $f['enum'] ) : array(),
				'default' => $f['default'] ?? null,
				'desc'    => (string) ( $f['desc'] ?? ( $f['label'] ?? '' ) ),
			);
		}
		if ( ! $fields ) {
			continue;
		}
		$out[ $option ] = array(
			'field'  => lcfirst( $base ),
			'type'   => 'FlxSettings' . $base,
			'cap'    => isset( $entry['cap'] ) && is_string( $entry['cap'] ) ? $entry['cap'] : 'manage_options',
			'label'  => (string) ( $entry['label'] ?? $option ),
			'fields' => $fields,
		);
	}
	return $out;
}

/**
 * GraphQL enum values of a schema enum, or null when one is not a valid name.
 *
 * @param array<int,mixed> $values Raw values.
 * @return array<string,mixed>|null [ GRAPHQL_NAME => raw ]
 */
function flx_gql_settings_enum_values( array $values ): ?array {
	$out = array();
	foreach ( $values as $v ) {
		$name = strtoupper( (string) preg_replace( '/[^A-Za-z0-9]+/', '_', (string) $v ) );
		if ( ! is_scalar( $v ) || ! preg_match( '/^[A-Z_][A-Z0-9_]*$/', $name ) || isset( $out[ $name ] ) || in_array( $name, array( 'TRUE', 'FALSE', 'NULL' ), true ) ) {
			return null;
		}
		$out[ $name ] = $v;
	}
	return $out ? $out : null;
}

/**
 * GraphQL type of a schema field (registers its enum on the way).
 *
 * @param string              $type_name Parent type name.
 * @param string              $gql       Field name.
 * @param array<string,mixed> $f         Normalised field.
 */
function flx_gql_settings_field_type( string $type_name, string $gql, array $f ): string {
	switch ( $f['type'] ) {
		case 'bool':
			return 'Boolean';
		case 'int':
		case 'page':
			return 'Int';
		case 'number':
		case 'float':
			return 'Float';
		case 'object':
		case 'list':
			return 'FlxJSON';
		case 'enum':
			$values = flx_gql_settings_enum_values( $f['enum'] );
			if ( null === $values ) {
				return 'String';
			}
			$enum = $type_name . ucfirst( $gql );
			$map  = array();
			foreach ( $values as $name => $raw ) {
				$map[ $name ] = array( 'value' => $raw );
			}
			flx_gql_enum( $enum, array( 'values' => $map ) );
			return $enum;
		default:
			return 'String';
	}
}

/**
 * Registers FlxSettings, the per-option types and flxSettingsUpdate.
 */
function flx_gql_register_settings_types(): void {
	$schema = flx_gql_settings_schema();
	if ( ! $schema ) {
		return;
	}
	$root_fields  = array();
	$input_fields = array();
	foreach ( $schema as $option => $o ) {
		$fields = array();
		$inputs = array();
		foreach ( $o['fields'] as $gql => $f ) {
			$type            = flx_gql_settings_field_type( $o['type'], $gql, $f );
			$key             = $f['key'];
			$default         = $f['default'];
			$fields[ $gql ]  = array(
				'type'        => $type,
				'description' => $f['desc'],
				'resolve'     => static fn( $values ) => is_array( $values ) && array_key_exists( $key, $values ) ? flx_gql_settings_out( $values[ $key ], $f ) : flx_gql_settings_out( $default, $f ),
			);
			$inputs[ $gql ]  = array(
				'type'        => $type,
				'description' => $f['desc'],
			);
		}
		flx_gql_type(
			$o['type'],
			array(
				'description' => $o['label'],
				'fields'      => $fields,
			)
		);
		flx_gql_input(
			$o['type'] . 'Input',
			array(
				/* translators: %s: settings group label. */
				'description' => sprintf( __( 'Changes to %s. Only the fields you send are changed.', 'flx-graphql' ), $o['label'] ),
				'fields'      => $inputs,
			)
		);
		$cap                        = $o['cap'];
		$root_fields[ $o['field'] ] = array(
			'type'        => $o['type'],
			'description' => $o['label'],
			'cap'         => $cap,
			'resolve'     => static function () use ( $option ) {
				$v = get_option( $option, array() );
				return is_array( $v ) ? $v : array();
			},
		);
		$input_fields[ $o['field'] ] = array(
			'type'        => $o['type'] . 'Input',
			'description' => $o['label'],
		);
	}

	flx_gql_type(
		'FlxSettings',
		array(
			'description' => __( 'Panel settings of the installed FluxWP packages (from flx_settings_schema).', 'flx-graphql' ),
			'fields'      => $root_fields,
		)
	);
	flx_gql_query(
		'flxSettings',
		array(
			'type'        => 'FlxSettings!',
			'description' => __( 'Panel settings of the installed FluxWP packages.', 'flx-graphql' ),
			'cap'         => 'manage_options',
			'resolve'     => static fn() => array(),
		)
	);
	flx_gql_mutation(
		'flxSettingsUpdate',
		array(
			'description' => __( 'Changes panel settings. Only the groups and fields you send are written.', 'flx-graphql' ),
			'cap'         => 'manage_options',
			'input'       => $input_fields,
			'output'      => array(
				'settings' => array(
					'type'        => 'FlxSettings',
					'description' => __( 'Settings after the change.', 'flx-graphql' ),
					'resolve'     => static fn() => array(),
				),
			),
			'resolve'     => static function ( array $input ) use ( $schema ) {
				$errors = new WP_Error();
				foreach ( $schema as $option => $o ) {
					if ( empty( $input[ $o['field'] ] ) || ! is_array( $input[ $o['field'] ] ) ) {
						continue;
					}
					if ( ! current_user_can( $o['cap'] ) ) {
						throw flx_gql_error( 'FORBIDDEN' );
					}
					$current = get_option( $option, array() );
					if ( ! is_array( $current ) ) {
						$errors->add( 'invalid', __( 'This setting cannot be changed here.', 'flx-graphql' ), array( 'field' => $o['field'], 'status' => 400 ) );
						continue;
					}
					foreach ( $input[ $o['field'] ] as $gql => $value ) {
						if ( ! isset( $o['fields'][ $gql ] ) ) {
							continue;
						}
						$f     = $o['fields'][ $gql ];
						$clean = flx_gql_settings_in( $value, $f );
						if ( $clean instanceof WP_Error ) {
							$errors->add( 'invalid', $clean->get_error_message(), array( 'field' => $o['field'] . '.' . $gql, 'status' => 400 ) );
							continue;
						}
						$current[ $f['key'] ] = $clean;
					}
					if ( ! $errors->has_errors() ) {
						update_option( $option, $current );
					}
				}
				if ( $errors->has_errors() ) {
					return array( 'errors' => $errors );
				}
				/**
				 * Settings were written through GraphQL.
				 *
				 * @param array<string,mixed> $input Mutation input.
				 */
				do_action( 'flx_gql_settings_updated', $input );
				return array();
			},
		)
	);
}

/**
 * Stored value → GraphQL value.
 *
 * @param mixed               $v Stored value.
 * @param array<string,mixed> $f Field.
 * @return mixed
 */
function flx_gql_settings_out( $v, array $f ) {
	switch ( $f['type'] ) {
		case 'bool':
			return null === $v ? null : (bool) $v;
		case 'int':
		case 'page':
			return is_numeric( $v ) ? (int) $v : null;
		case 'number':
		case 'float':
			return is_numeric( $v ) ? (float) $v : null;
		case 'object':
		case 'list':
			return $v;
		case 'enum':
			return null === $v || '' === $v ? null : $v;
		default:
			return is_scalar( $v ) ? (string) $v : null;
	}
}

/**
 * GraphQL input value → stored value, or WP_Error.
 *
 * @param mixed               $v Input.
 * @param array<string,mixed> $f Field.
 * @return mixed|WP_Error
 */
function flx_gql_settings_in( $v, array $f ) {
	switch ( $f['type'] ) {
		case 'bool':
			return (bool) $v;
		case 'int':
		case 'page':
			return (int) $v;
		case 'number':
		case 'float':
			return (float) $v;
		case 'object':
		case 'list':
			return is_array( $v ) ? $v : new WP_Error( 'invalid', __( 'A list or object is expected.', 'flx-graphql' ) );
		case 'enum':
			return in_array( $v, $f['enum'], true ) || in_array( (string) $v, array_map( 'strval', $f['enum'] ), true ) ? $v : new WP_Error( 'invalid', __( 'This choice is not available.', 'flx-graphql' ) );
		case 'color':
			$v = trim( (string) $v );
			return '' === $v || preg_match( '/^#[0-9a-fA-F]{3,8}$/', $v ) ? $v : new WP_Error( 'invalid', __( 'Enter a colour like #1F6FEB.', 'flx-graphql' ) );
		case 'url':
			return esc_url_raw( (string) $v );
		case 'html':
			return wp_kses_post( (string) $v );
		case 'text':
			return sanitize_textarea_field( (string) $v );
		default:
			return sanitize_text_field( (string) $v );
	}
}
