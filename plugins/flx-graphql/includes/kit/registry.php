<?php
/**
 * Registration wrappers over WPGraphQL.
 *
 * A package registers its schema on the `flx_gql_register` action. The action only fires inside a GraphQL
 * request (WPGraphQL's lazy `graphql_register_types`) and only when a supported WPGraphQL is loaded, so a
 * page load never pays for it and a site without Flux GraphQL keeps working on REST.
 *
 *     add_action( 'flx_gql_register', function () {
 *         flx_gql_type( 'FlxTask', array( 'description' => __( 'A task.', 'flx-tasks' ), 'fields' => array(
 *             'title' => array( 'type' => 'String!', 'description' => __( 'Title.', 'flx-tasks' ) ),
 *         ) ) );
 *         flx_gql_query( 'flxTask', array(
 *             'type' => 'FlxTask', 'cap' => 'read', 'args' => array( 'id' => array( 'type' => 'ID!' ) ),
 *             'resolve' => fn( $root, $args, $context ) => FLXG_Task_Service::get( flx_gql_decode_id( $args['id'], 'FlxTask' ) ),
 *         ) );
 *     } );
 *
 * Rules (docs/graphql/KURALLAR.md), enforced here:
 *   - Type names start with "Flx" (PascalCase); root fields and mutations with "flx" (camelCase).
 *   - Every root field, mutation and field added to a non-Flx (WordPress) type has a `cap`; without one the
 *     registration is refused (fail closed). `cap` is a capability, 'public', 'logged_in' or a callable
 *     fn( $source, $args, $context ): bool. `owner` (callable, same signature) grants access to one's own record.
 *   - A resolver may return WP_Error; it becomes an error with a stable code (errors.php).
 *   - Types are written as strings: 'String', 'String!', '[FlxTag!]!'.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fires the package registration action inside WPGraphQL's type registry.
 */
add_action(
	'graphql_register_types',
	static function () {
		if ( ! flx_gql_ready() ) {
			return;
		}
		do_action( 'flx_gql_register_core' );
		/**
		 * Packages register their GraphQL types, queries and mutations here.
		 *
		 * @since 0.1.0
		 */
		do_action( 'flx_gql_register' );
	},
	20
);

/**
 * '[FlxTag!]!' → WPGraphQL type config array.
 *
 * @param string|array<string,mixed> $type Type notation or an already built config.
 * @return string|array<string,mixed>
 */
function flx_gql_parse_type( $type ) {
	if ( ! is_string( $type ) ) {
		return $type;
	}
	$type = trim( $type );
	if ( str_ends_with( $type, '!' ) ) {
		return array( 'non_null' => flx_gql_parse_type( substr( $type, 0, -1 ) ) );
	}
	if ( str_starts_with( $type, '[' ) && str_ends_with( $type, ']' ) ) {
		return array( 'list_of' => flx_gql_parse_type( substr( $type, 1, -1 ) ) );
	}
	return $type;
}

/**
 * Whether the current user passes a `cap` / `owner` pair.
 *
 * @param mixed                $cap     Capability, 'public', 'logged_in' or callable.
 * @param callable|null        $owner   Ownership check.
 * @param mixed                $source  Parent value.
 * @param array<string,mixed>  $args    Field arguments.
 * @param mixed                $context AppContext.
 */
function flx_gql_allowed( $cap, $owner, $source, array $args, $context ): bool {
	if ( 'public' === $cap ) {
		return true;
	}
	$ok = false;
	if ( 'logged_in' === $cap ) {
		$ok = is_user_logged_in();
	} elseif ( is_string( $cap ) && '' !== $cap ) {
		$ok = current_user_can( $cap );
	} elseif ( is_callable( $cap ) ) {
		$ok = (bool) call_user_func( $cap, $source, $args, $context );
	}
	if ( ! $ok && is_callable( $owner ) ) {
		$ok = (bool) call_user_func( $owner, $source, $args, $context );
	}
	return $ok;
}

/**
 * Throws the right error for a refused field.
 *
 * @throws FLXGQL_Error Always.
 */
function flx_gql_deny(): void {
	throw flx_gql_error( is_user_logged_in() ? 'FORBIDDEN' : 'UNAUTHENTICATED' );
}

/**
 * Wraps a resolver: permission, WP_Error → error, Traversable → array.
 *
 * @param callable|null $resolve Original resolver ( $source, $args, $context, $info ).
 * @param mixed         $cap     Permission (null = inherit, no check).
 * @param callable|null $owner   Ownership check.
 * @param string        $name    Field name for messages.
 */
function flx_gql_wrap_resolver( ?callable $resolve, $cap, ?callable $owner, string $name ): callable {
	return static function ( $source, $args, $context, $info ) use ( $resolve, $cap, $owner, $name ) {
		$args = flx_gql_arr( $args );
		if ( null !== $cap && ! flx_gql_allowed( $cap, $owner, $source, $args, $context ) ) {
			flx_gql_deny();
		}
		if ( null === $resolve ) {
			// Default resolver: array key or public property with the field name.
			$key = $info instanceof \GraphQL\Type\Definition\ResolveInfo ? $info->fieldName : $name; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( is_array( $source ) ) {
				return $source[ $key ] ?? null;
			}
			return is_object( $source ) && isset( $source->{$key} ) ? $source->{$key} : null;
		}
		$value = call_user_func( $resolve, $source, $args, $context, $info );
		return flx_gql_normalize_value( $value );
	};
}

/**
 * Resolver result → what graphql-php expects.
 *
 * @param mixed $value Value.
 * @return mixed
 * @throws FLXGQL_Error When the value is a WP_Error.
 */
function flx_gql_normalize_value( $value ) {
	if ( $value instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $value );
	}
	if ( $value instanceof FLXGQL_Dto ) {
		return $value->to_graphql();
	}
	if ( $value instanceof Traversable && ! $value instanceof \GraphQL\Deferred ) {
		return iterator_to_array( $value, false );
	}
	return $value;
}

/**
 * A typed value object a service returns. Public readonly properties are read by the default resolver;
 * implement to_graphql() only when the GraphQL shape differs from the properties.
 */
interface FLXGQL_Dto {
	/**
	 * GraphQL shape.
	 *
	 * @return array<string,mixed>|object
	 */
	public function to_graphql();
}

/**
 * A record that implements the Node interface (global ID, node(id:) lookup through its loader).
 */
interface FLXGQL_Node {
	/** GraphQL type name, e.g. FlxTask. */
	public function gql_type(): string;
	/** Database id. */
	public function gql_id(): int;
}

/**
 * Global ID of a record: base64("FlxTask:12") (WPGraphQL / Relay format).
 *
 * @param string $type GraphQL type name.
 * @param int    $id   Database id.
 */
function flx_gql_global_id( string $type, int $id ): string {
	return \GraphQLRelay\Relay::toGlobalId( $type, (string) $id );
}

/**
 * Database id from a global ID of the given type; a plain number is accepted too (databaseId).
 *
 * @param mixed  $id   Global ID or number.
 * @param string $type Expected GraphQL type name.
 * @throws FLXGQL_Error When the ID belongs to another type.
 */
function flx_gql_decode_id( $id, string $type ): int {
	if ( is_int( $id ) || ( is_string( $id ) && ctype_digit( $id ) ) ) {
		return (int) $id;
	}
	$parts = \GraphQLRelay\Relay::fromGlobalId( flx_gql_str( $id ) );
	$raw   = flx_gql_str( $parts['id'] ?? '' );
	if ( flx_gql_str( $parts['type'] ?? '' ) !== $type || ! ctype_digit( $raw ) ) {
		throw flx_gql_error( 'INVALID_INPUT', __( 'The ID is not valid.', 'flx-graphql' ), 'id' );
	}
	return (int) $raw;
}

/**
 * Prepares a field map: type notation, args, resolver wrapper, deprecation.
 *
 * @param array<mixed> $fields     Fields: [ name => { type, description, args, cap, owner, resolve, deprecated, complexity } ].
 * @param string       $owner_type Type the fields belong to (for messages).
 * @param bool         $need_cap   Every field must declare a cap.
 * @return array<string,array<string,mixed>>|null Null when a field is refused.
 */
function flx_gql_prepare_fields( array $fields, string $owner_type, bool $need_cap ): ?array {
	$out = array();
	foreach ( $fields as $name => $f ) {
		$name = (string) $name;
		if ( ! preg_match( '/^[a-z][A-Za-z0-9]*$/', $name ) ) {
			flx_gql_registration_error( $owner_type . '.' . $name, 'Field names are camelCase.' );
			return null;
		}
		$f   = flx_gql_arr( $f );
		$cap = array_key_exists( 'cap', $f ) ? $f['cap'] : null;
		if ( $need_cap && null === $cap ) {
			flx_gql_registration_error( $owner_type . '.' . $name, 'A cap is required (fail closed).' );
			return null;
		}
		$field = array(
			'type'        => flx_gql_type_of( $f['type'] ?? null ),
			'description' => flx_gql_str( $f['description'] ?? '' ),
		);
		if ( ! empty( $f['args'] ) && is_array( $f['args'] ) ) {
			$field['args'] = flx_gql_prepare_args( $f['args'] );
		}
		if ( ! empty( $f['deprecated'] ) ) {
			$field['deprecationReason'] = flx_gql_str( $f['deprecated'] );
		}
		if ( isset( $f['complexity'] ) ) {
			$field['complexity'] = $f['complexity'];
		}
		if ( null !== $cap || isset( $f['resolve'] ) ) {
			$field['resolve'] = flx_gql_wrap_resolver( flx_gql_callable( $f['resolve'] ?? null ), $cap, flx_gql_callable( $f['owner'] ?? null ), $name );
		}
		$out[ $name ] = $field;
	}
	return $out;
}

/**
 * Argument / input field map.
 *
 * @param array<mixed> $args Arguments: [ name => type notation | { type, description, default, deprecated } ].
 * @return array<string,array<string,mixed>>
 */
function flx_gql_prepare_args( array $args ): array {
	$out = array();
	foreach ( $args as $name => $a ) {
		$name  = (string) $name;
		$a     = is_string( $a ) ? array( 'type' => $a ) : flx_gql_arr( $a );
		$entry = array(
			'type'        => flx_gql_type_of( $a['type'] ?? null ),
			'description' => flx_gql_str( $a['description'] ?? '' ),
		);
		if ( array_key_exists( 'default', $a ) ) {
			$entry['defaultValue'] = $a['default'];
		}
		if ( ! empty( $a['deprecated'] ) ) {
			$entry['deprecationReason'] = flx_gql_str( $a['deprecated'] );
		}
		$out[ $name ] = $entry;
	}
	return $out;
}

/**
 * Type notation from a config value ('[X!]!' or a WPGraphQL type array; default String).
 *
 * @param mixed $t Notation.
 * @return array<string,mixed>|string
 */
function flx_gql_type_of( $t ) {
	return flx_gql_parse_type( is_array( $t ) ? flx_gql_arr( $t ) : flx_gql_str( $t, 'String' ) );
}

/**
 * A callable config value, or null.
 *
 * @param mixed $c Value.
 */
function flx_gql_callable( $c ): ?callable {
	return is_callable( $c ) ? $c : null;
}

/**
 * Checks a type name: "Flx" + PascalCase.
 *
 * @param string $name Type name.
 */
function flx_gql_check_type_name( string $name ): bool {
	if ( ! preg_match( '/^Flx[A-Z][A-Za-z0-9]*$/', $name ) ) {
		flx_gql_registration_error( $name, 'Type names start with "Flx" and are PascalCase.' );
		return false;
	}
	return true;
}

/**
 * Registers an object type.
 *
 * @param string              $name   "Flx…" type name.
 * @param array<string,mixed> $config description, fields, interfaces (e.g. ['Node']), node (loader class: implements Node).
 */
function flx_gql_type( string $name, array $config ): bool {
	if ( ! flx_gql_check_type_name( $name ) ) {
		return false;
	}
	$fields = flx_gql_prepare_fields( flx_gql_arr( $config['fields'] ?? array() ), $name, false );
	if ( null === $fields ) {
		return false;
	}
	$interfaces = array_map( 'flx_gql_str', (array) ( $config['interfaces'] ?? array() ) );
	if ( ! empty( $config['node'] ) ) {
		$interfaces[] = 'Node';
		$fields       = array(
			'id'         => array(
				'type'        => array( 'non_null' => 'ID' ),
				'description' => __( 'Global ID.', 'flx-graphql' ),
				'resolve'     => static fn( $source ) => $source instanceof FLXGQL_Node ? flx_gql_global_id( $source->gql_type(), $source->gql_id() ) : null,
			),
			'databaseId' => array(
				'type'        => array( 'non_null' => 'Int' ),
				'description' => __( 'Database ID.', 'flx-graphql' ),
				'resolve'     => static fn( $source ) => $source instanceof FLXGQL_Node ? $source->gql_id() : null,
			),
		) + $fields;
		flx_gql_node_loader( $name, flx_gql_str( $config['node'] ) );
	}
	register_graphql_object_type(
		$name,
		array(
			'description' => flx_gql_str( $config['description'] ?? '' ),
			'interfaces'  => array_values( array_unique( $interfaces ) ),
			'fields'      => $fields,
		)
	);
	return true;
}

/**
 * Registers the loader used by node(id:) for a Node type.
 *
 * @param string $type  GraphQL type name (global ID prefix).
 * @param string $class FLXGQL_Loader subclass.
 */
function flx_gql_node_loader( string $type, string $class ): void {
	global $flx_gql_node_loaders;
	$flx_gql_node_loaders          = is_array( $flx_gql_node_loaders ) ? $flx_gql_node_loaders : array();
	$flx_gql_node_loaders[ $type ] = $class;
}

add_filter(
	'graphql_data_loader_classes',
	static function ( $classes ) {
		global $flx_gql_node_loaders;
		return is_array( $flx_gql_node_loaders ) ? array_merge( (array) $classes, $flx_gql_node_loaders ) : $classes;
	}
);
add_filter(
	'graphql_resolve_node_type',
	static function ( $type, $node ) {
		return $node instanceof FLXGQL_Node ? $node->gql_type() : $type;
	},
	10,
	2
);

/**
 * Registers an interface.
 *
 * @param string              $name   "Flx…" name.
 * @param array<string,mixed> $config description, fields, resolveType (callable value → type name).
 */
function flx_gql_interface( string $name, array $config ): bool {
	if ( ! flx_gql_check_type_name( $name ) ) {
		return false;
	}
	$fields = flx_gql_prepare_fields( flx_gql_arr( $config['fields'] ?? array() ), $name, false );
	if ( null === $fields ) {
		return false;
	}
	$args = array(
		'description' => flx_gql_str( $config['description'] ?? '' ),
		'fields'      => $fields,
	);
	if ( isset( $config['resolveType'] ) && is_callable( $config['resolveType'] ) ) {
		$resolve             = $config['resolveType'];
		$args['resolveType'] = static function ( $value ) use ( $resolve ) {
			$type = call_user_func( $resolve, $value );
			return is_string( $type ) && function_exists( 'graphql' ) ? \WPGraphQL::get_type_registry()->get_type( $type ) : $type;
		};
	}
	register_graphql_interface_type( $name, $args );
	return true;
}

/**
 * Registers an input type.
 *
 * @param string              $name   "Flx…Input" name.
 * @param array<string,mixed> $config description, fields.
 */
function flx_gql_input( string $name, array $config ): bool {
	if ( ! flx_gql_check_type_name( $name ) ) {
		return false;
	}
	register_graphql_input_type(
		$name,
		array(
			'description' => flx_gql_str( $config['description'] ?? '' ),
			'fields'      => flx_gql_prepare_args( (array) ( $config['fields'] ?? array() ) ),
		)
	);
	return true;
}

/**
 * Registers an enum. Values are UPPER_SNAKE; each maps to the PHP value the service uses.
 *
 * @param string              $name   "Flx…" name.
 * @param array<string,mixed> $config description, values: [ 'OPEN' => [ 'value' => 'open', 'description' => … ] ].
 */
function flx_gql_enum( string $name, array $config ): bool {
	if ( ! flx_gql_check_type_name( $name ) ) {
		return false;
	}
	$values = array();
	foreach ( flx_gql_arr( $config['values'] ?? array() ) as $key => $v ) {
		if ( ! preg_match( '/^[A-Z][A-Z0-9_]*$/', $key ) ) {
			flx_gql_registration_error( $name . '.' . $key, 'Enum values are UPPER_SNAKE.' );
			return false;
		}
		$v     = is_array( $v ) ? flx_gql_arr( $v ) : array( 'value' => $v );
		$entry = array(
			'value'       => $v['value'] ?? strtolower( $key ),
			'description' => flx_gql_str( $v['description'] ?? '' ),
		);
		$why   = flx_gql_str( $v['deprecated'] ?? '' );
		if ( '' !== $why ) {
			$entry['deprecationReason'] = $why;
		}
		$values[ $key ] = $entry;
	}
	register_graphql_enum_type(
		$name,
		array(
			'description' => flx_gql_str( $config['description'] ?? '' ),
			'values'      => $values,
		)
	);
	return true;
}

/**
 * Registers a root query field.
 *
 * @param string              $name   "flx…" field name.
 * @param array<string,mixed> $config type, description, args, cap (required), owner, resolve.
 */
function flx_gql_query( string $name, array $config ): bool {
	if ( ! preg_match( '/^flx[A-Z][A-Za-z0-9]*$/', $name ) ) {
		flx_gql_registration_error( 'RootQuery.' . $name, 'Root fields start with "flx" and are camelCase.' );
		return false;
	}
	$fields = flx_gql_prepare_fields( array( $name => $config ), 'RootQuery', true );
	if ( null === $fields ) {
		return false;
	}
	register_graphql_field( 'RootQuery', $name, $fields[ $name ] );
	return true;
}

/**
 * Adds a field to an existing type. On a WordPress type (Post, User …) the name must be "flx…" and a cap is required.
 *
 * @param string              $type   Existing type.
 * @param string              $name   Field name.
 * @param array<string,mixed> $config Field config (as in flx_gql_type fields).
 */
function flx_gql_field( string $type, string $name, array $config ): bool {
	$ours = str_starts_with( $type, 'Flx' );
	if ( ! $ours && ! preg_match( '/^flx[A-Z][A-Za-z0-9]*$/', $name ) ) {
		flx_gql_registration_error( $type . '.' . $name, 'Fields on WordPress types start with "flx".' );
		return false;
	}
	$fields = flx_gql_prepare_fields( array( $name => $config ), $type, ! $ours );
	if ( null === $fields ) {
		return false;
	}
	register_graphql_field( $type, $name, $fields[ $name ] );
	return true;
}

/**
 * Registers a mutation. Input is one `input` argument; the payload always has `errors: [FlxUserError!]!`.
 *
 * The resolver gets ( array $input, AppContext $context, ResolveInfo $info ) and returns the payload array
 * (without `errors`), a WP_Error, or throws FLXGQL_Error. INVALID_INPUT and CONFLICT become payload errors
 * (the form shows them); other codes stay top-level errors.
 *
 * @param string              $name   "flx…" verb-first name: flxTaskUpdate.
 * @param array<string,mixed> $config description, input (fields), output (fields), cap (required), owner, resolve.
 */
function flx_gql_mutation( string $name, array $config ): bool {
	if ( ! preg_match( '/^flx[A-Z][A-Za-z0-9]*$/', $name ) ) {
		flx_gql_registration_error( 'RootMutation.' . $name, 'Mutation names start with "flx" and are camelCase.' );
		return false;
	}
	if ( ! array_key_exists( 'cap', $config ) || null === $config['cap'] ) {
		flx_gql_registration_error( 'RootMutation.' . $name, 'A cap is required (fail closed).' );
		return false;
	}
	$output = flx_gql_prepare_fields( (array) ( $config['output'] ?? array() ), $name . 'Payload', false );
	if ( null === $output ) {
		return false;
	}
	$output['errors'] = array(
		'type'        => array( 'non_null' => array( 'list_of' => array( 'non_null' => 'FlxUserError' ) ) ),
		'description' => __( 'Input problems to show next to the form fields. Empty when the mutation succeeded.', 'flx-graphql' ),
		'resolve'     => static fn( $payload ) => is_array( $payload ) ? ( $payload['errors'] ?? array() ) : array(),
	);
	$cap     = $config['cap'];
	$owner   = flx_gql_callable( $config['owner'] ?? null );
	$resolve = flx_gql_callable( $config['resolve'] ?? null );

	register_graphql_mutation(
		$name,
		array(
			'description'         => flx_gql_str( $config['description'] ?? '' ),
			'inputFields'         => flx_gql_prepare_args( (array) ( $config['input'] ?? array() ) ),
			'outputFields'        => $output,
			'mutateAndGetPayload' => static function ( $input, $context, $info ) use ( $cap, $owner, $resolve ) {
				$input = flx_gql_arr( $input );
				if ( ! flx_gql_allowed( $cap, $owner, null, $input, $context ) ) {
					flx_gql_deny();
				}
				if ( null === $resolve ) {
					return array( 'errors' => array() );
				}
				try {
					$payload = call_user_func( $resolve, $input, $context, $info );
				} catch ( FLXGQL_Error $e ) {
					if ( in_array( $e->flx_code, array( 'INVALID_INPUT', 'CONFLICT' ), true ) ) {
						return array( 'errors' => flx_gql_user_errors( $e ) );
					}
					throw $e;
				}
				if ( $payload instanceof WP_Error ) {
					$code = flx_gql_code_of( $payload );
					if ( in_array( $code, array( 'INVALID_INPUT', 'CONFLICT' ), true ) ) {
						return array( 'errors' => flx_gql_user_errors( $payload ) );
					}
					throw flx_gql_from_wp_error( $payload );
				}
				$payload = flx_gql_normalize_value( $payload );
				$payload = is_array( $payload ) ? $payload : (array) $payload;
				if ( isset( $payload['errors'] ) && ( $payload['errors'] instanceof WP_Error || is_array( $payload['errors'] ) ) ) {
					$payload['errors'] = flx_gql_user_errors( $payload['errors'] );
				} else {
					$payload['errors'] = array();
				}
				return $payload;
			},
		)
	);
	return true;
}
