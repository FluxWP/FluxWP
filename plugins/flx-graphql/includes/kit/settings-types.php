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
 * valid GraphQL name), object/list → FlxJSON, others → String.
 * Secrets are never exposed, fail closed (flx_gql_is_secret_key): a field or option whose name looks like a
 * secret, a field with 'secret' => true or type password/secret is left out, and keys that look like secrets
 * are removed from object/list values (and kept as stored when such a value is written).
 * Read and write need `manage_options` (or the entry's 'cap').
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

add_action( 'flx_gql_register_core', 'flx_gql_register_settings_types', 30 );

/**
 * Whether a settings key looks like a secret (API key, token, password …). Fail closed: when in doubt, yes.
 *
 * The key is split into words (snake_case, kebab-case, camelCase, dots): a word such as key, secret, token,
 * password, pass, pwd, pin, auth, private, credential, salt or signature marks it, and so does a
 * glued form anywhere in the key (apikey, accesstoken, clientsecret, privatekey, passwd …).
 * Filter `flx_gql_secret_key` can only add secrets (a true from the kit is never turned off).
 *
 * @param string $key Option or field key.
 */
function flx_gql_is_secret_key( string $key ): bool {
	$norm  = strtolower( (string) preg_replace( '/([a-z0-9])([A-Z])/', '$1_$2', $key ) );
	$words = array_filter( (array) preg_split( '/[^a-z0-9]+/', $norm ) );
	$hit   = (bool) array_intersect(
		$words,
		array( 'key', 'keys', 'secret', 'secrets', 'token', 'tokens', 'password', 'passwords', 'passwd', 'pass', 'passphrase', 'pwd', 'pin', 'auth', 'private', 'credential', 'credentials', 'salt', 'signature', 'bearer', 'dsn' )
	);
	if ( ! $hit ) {
		$hit = (bool) preg_match( '/secret|token|passw|apikey|accesskey|privatekey|secretkey|signingkey|clientkey|licensekey|credential|bearer/', (string) preg_replace( '/[^a-z0-9]/', '', $norm ) );
	}
	/**
	 * Marks more settings keys as secret. Returning false cannot un-hide a key the kit found.
	 *
	 * @param bool   $hit Whether the key is a secret.
	 * @param string $key The key.
	 */
	return $hit || (bool) apply_filters( 'flx_gql_secret_key', false, $key );
}

/**
 * Whether a schema field must stay out of GraphQL: its name looks like a secret, it says 'secret' => true,
 * or its type is password/secret.
 *
 * @param string              $key Field key.
 * @param array<mixed,mixed>  $f   Schema field.
 */
function flx_gql_is_secret_field( string $key, array $f ): bool {
	return ! empty( $f['secret'] ) || in_array( $f['type'] ?? '', array( 'password', 'secret' ), true ) || flx_gql_is_secret_key( $key );
}

/**
 * An object/list setting without the keys that look like secrets (recursively).
 *
 * @param mixed $v Stored value.
 * @return mixed
 */
function flx_gql_settings_redact( $v ) {
	if ( ! is_array( $v ) ) {
		return $v;
	}
	$out = array();
	foreach ( $v as $k => $item ) {
		if ( is_string( $k ) && flx_gql_is_secret_key( $k ) ) {
			continue;
		}
		$out[ $k ] = flx_gql_settings_redact( $item );
	}
	return array_is_list( $v ) ? array_values( $out ) : $out;
}

/**
 * Written object/list value with the stored secret keys put back (the client never saw them).
 * Only string keys at the same path are restored; a list item is matched by its index.
 *
 * @param mixed $new     Written value.
 * @param mixed $current Stored value.
 * @return mixed
 */
function flx_gql_settings_keep_secrets( $new, $current ) {
	if ( ! is_array( $new ) || ! is_array( $current ) ) {
		return $new;
	}
	foreach ( $current as $k => $item ) {
		if ( is_string( $k ) && flx_gql_is_secret_key( $k ) ) {
			if ( ! array_key_exists( $k, $new ) ) {
				$new[ $k ] = $item;
			}
		} elseif ( array_key_exists( $k, $new ) ) {
			$new[ $k ] = flx_gql_settings_keep_secrets( $new[ $k ], $item );
		}
	}
	return $new;
}

/**
 * Normalised settings schema: [ option => { field, type, cap, label, fields: [ key => { gql, type, enum, default, desc } ] } ].
 *
 * @return array<string,FlxGqlSettingsGroup>
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
		if ( ! empty( $entry['secret'] ) || flx_gql_is_secret_key( $option ) ) {
			continue;
		}
		$used[ $base ] = true;
		$fields        = array();
		foreach ( $entry['fields'] as $key => $f ) {
			if ( ! is_string( $key ) || ! is_array( $f ) || flx_gql_is_secret_field( $key, $f ) ) {
				continue;
			}
			$gql = flx_gql_camel( $key );
			if ( '' === $gql || ! preg_match( '/^[a-z][A-Za-z0-9]*$/', $gql ) || isset( $fields[ $gql ] ) ) {
				continue;
			}
			$fields[ $gql ] = array(
				'key'     => $key,
				'type'    => flx_gql_str( $f['type'] ?? 'string', 'string' ),
				'enum'    => isset( $f['enum'] ) && is_array( $f['enum'] ) ? array_values( array_filter( $f['enum'], 'is_scalar' ) ) : array(),
				'default' => $f['default'] ?? null,
				'desc'    => flx_gql_str( $f['desc'] ?? ( $f['label'] ?? '' ) ),
			);
		}
		if ( ! $fields ) {
			continue;
		}
		$out[ $option ] = array(
			'field'  => lcfirst( $base ),
			'type'   => 'FlxSettings' . $base,
			'cap'    => isset( $entry['cap'] ) && is_string( $entry['cap'] ) ? $entry['cap'] : 'manage_options',
			'label'  => flx_gql_str( $entry['label'] ?? $option, $option ),
			'fields' => $fields,
		);
	}
	return $out;
}

/**
 * GraphQL enum values of a schema enum, or null when one is not a valid name.
 *
 * @param array<int,scalar> $values Raw values.
 * @return array<string,scalar>|null [ GRAPHQL_NAME => raw ]
 */
function flx_gql_settings_enum_values( array $values ): ?array {
	$out = array();
	foreach ( $values as $v ) {
		$name = strtoupper( (string) preg_replace( '/[^A-Za-z0-9]+/', '_', (string) $v ) );
		if ( ! preg_match( '/^[A-Z_][A-Z0-9_]*$/', $name ) || isset( $out[ $name ] ) || in_array( $name, array( 'TRUE', 'FALSE', 'NULL' ), true ) ) {
			return null;
		}
		$out[ $name ] = $v;
	}
	return $out ? $out : null;
}

/**
 * GraphQL type of a schema field (registers its enum on the way).
 *
 * @param string               $type_name Parent type name.
 * @param string               $gql       Field name.
 * @param FlxGqlSettingsField  $f         Normalised field.
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
	// Schema snapshot (wp flx-gql schema): each group belongs to the package that declared the option.
	$owners = flx_gql_settings_owners();
	foreach ( $schema as $option => $o ) {
		$owner = $owners[ $option ] ?? '';
		flx_gql_schema_owned_by(
			$owner,
			static function () use ( $o, $owner ) {
				flx_gql_settings_group_types( $o );
				flx_gql_schema_own( 'FlxSettings.' . $o['field'], '' !== $owner ? $owner : null );
				flx_gql_schema_own( 'FlxSettingsUpdateInput.' . $o['field'], '' !== $owner ? $owner : null );
			}
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
					$group = flx_gql_arr( $input[ $o['field'] ] ?? null );
					if ( ! $group ) {
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
					foreach ( $group as $gql => $value ) {
						if ( ! isset( $o['fields'][ $gql ] ) ) {
							continue;
						}
						$f     = $o['fields'][ $gql ];
						$clean = flx_gql_settings_in( $value, $f );
						if ( $clean instanceof WP_Error ) {
							$errors->add( 'invalid', $clean->get_error_message(), array( 'field' => $o['field'] . '.' . $gql, 'status' => 400 ) );
							continue;
						}
						if ( in_array( $f['type'], array( 'object', 'list' ), true ) ) {
							$clean = flx_gql_settings_keep_secrets( $clean, $current[ $f['key'] ] ?? null );
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
				 * @param array $input Mutation input.
				 */
				do_action( 'flx_gql_settings_updated', $input );
				return array();
			},
		)
	);
}

/**
 * Registers one settings group's object and input types (and the enums of its fields).
 *
 * @param FlxGqlSettingsGroup $o Normalised group.
 */
function flx_gql_settings_group_types( array $o ): void {
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
}

/**
 * Stored value → GraphQL value.
 *
 * @param mixed               $v Stored value.
 * @param FlxGqlSettingsField $f Field.
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
			return flx_gql_settings_redact( $v );
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
 * @param FlxGqlSettingsField $f Field.
 * @return mixed|WP_Error
 */
function flx_gql_settings_in( $v, array $f ) {
	switch ( $f['type'] ) {
		case 'bool':
			return (bool) $v;
		case 'int':
		case 'page':
			return flx_gql_int( $v );
		case 'number':
		case 'float':
			return is_numeric( $v ) ? (float) $v : 0.0;
		case 'object':
		case 'list':
			return is_array( $v ) ? $v : new WP_Error( 'invalid', __( 'A list or object is expected.', 'flx-graphql' ) );
		case 'enum':
			return in_array( $v, $f['enum'], true ) || in_array( flx_gql_str( $v ), array_map( static fn( $e ): string => (string) $e, $f['enum'] ), true ) ? $v : new WP_Error( 'invalid', __( 'This choice is not available.', 'flx-graphql' ) );
		case 'color':
			$v = trim( flx_gql_str( $v ) );
			return '' === $v || preg_match( '/^#[0-9a-fA-F]{3,8}$/', $v ) ? $v : new WP_Error( 'invalid', __( 'Enter a colour like #1F6FEB.', 'flx-graphql' ) );
		case 'url':
			return esc_url_raw( flx_gql_str( $v ) );
		case 'html':
			return wp_kses_post( flx_gql_str( $v ) );
		case 'text':
			return sanitize_textarea_field( flx_gql_str( $v ) );
		default:
			return sanitize_text_field( flx_gql_str( $v ) );
	}
}
