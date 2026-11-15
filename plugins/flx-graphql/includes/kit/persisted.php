<?php
/**
 * Persisted queries and the query allowlist.
 *
 * Every FluxWP package ships the queries its screens use, compiled at build time by `bun run codegen`
 * (packages/graphql): `plugins/<slug>/graphql/persisted-queries.json` = { "<sha256>": "<document>" }.
 * The package registers the file:
 *
 *     add_filter( 'flx_gql_persisted_manifests', fn( $m ) => $m + array( 'flx-tasks' => FLXG_DIR . 'graphql/persisted-queries.json' ) );
 *
 * Clients send `queryId=<sha256>` (GET for reads, cacheable) instead of the document.
 *
 * Query mode (Settings › Flux GraphQL):
 *   strict  only documents from the manifests run over HTTP (default; the IDE, when on, still works for administrators)
 *   admins  administrators may also send any document (debugging)
 *   open    anyone may send any document (a public headless API)
 * PHP calls of graphql() are never restricted.
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registered manifests and their merged contents (cached until a manifest file changes).
 *
 * @return array{queries:array<string,string>,allow:array<string,bool>,files:array<string,string>}
 */
function flx_gql_persisted(): array {
	/** @var array{queries:array<string,string>,allow:array<string,bool>,files:array<string,string>}|null $cache */
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	/**
	 * Persisted query manifests of the installed packages: [ slug => absolute path to JSON ].
	 *
	 * @param array<string,string> $manifests Manifests.
	 */
	$filtered = apply_filters( 'flx_gql_persisted_manifests', array() );
	$files    = array();
	$sig      = array();
	foreach ( flx_gql_arr( $filtered ) as $slug => $path ) {
		if ( is_string( $path ) && is_readable( $path ) ) {
			$files[ $slug ] = $path;
			$sig[]          = $slug . ':' . $path . ':' . filemtime( $path ) . ':' . filesize( $path );
		}
	}
	sort( $sig );
	$key    = 'flxgql_pq_' . md5( FLXGQL_VERSION . '|' . implode( '|', $sig ) );
	$stored = get_transient( $key );
	if ( is_array( $stored ) && is_array( $stored['queries'] ?? null ) && is_array( $stored['allow'] ?? null ) ) {
		$cache = array(
			'queries' => array_map( 'flx_gql_str', flx_gql_arr( $stored['queries'] ) ),
			'allow'   => array_map( 'boolval', flx_gql_arr( $stored['allow'] ) ),
			'files'   => $files,
		);
		return $cache;
	}
	$queries = array();
	$allow   = array();
	foreach ( $files as $path ) {
		$json = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		foreach ( is_array( $json ) ? $json : array() as $id => $doc ) {
			if ( ! is_string( $id ) || ! is_string( $doc ) || ! preg_match( '/^[a-f0-9]{64}$/', $id ) ) {
				continue;
			}
			$queries[ $id ]                       = $doc;
			$allow[ flx_gql_document_hash( $doc ) ] = true;
		}
	}
	set_transient(
		$key,
		array(
			'queries' => $queries,
			'allow'   => $allow,
		),
		DAY_IN_SECONDS
	);
	$cache = array(
		'queries' => $queries,
		'allow'   => $allow,
		'files'   => $files,
	);
	return $cache;
}

/**
 * Hash of a document's canonical print (whitespace and comments do not matter).
 *
 * @param string|\GraphQL\Language\AST\DocumentNode $doc Document.
 */
function flx_gql_document_hash( $doc ): string {
	try {
		$ast = is_string( $doc ) ? \GraphQL\Language\Parser::parse( $doc, array( 'noLocation' => true ) ) : $doc;
		return hash( 'sha256', \GraphQL\Language\Printer::doPrint( $ast ) );
	} catch ( \Throwable $e ) {
		return '';
	}
}

/**
 * Whether the current user may send documents that are not in a manifest.
 */
function flx_gql_free_queries_allowed(): bool {
	$mode = flx_gql_settings()['query_mode'];
	if ( 'open' === $mode ) {
		return true;
	}
	$admin = current_user_can( 'manage_options' );
	return $admin && ( 'admins' === $mode || flx_gql_settings()['ide'] );
}

/**
 * True when every operation of this HTTP request came from a manifest (queryId).
 */
function flx_gql_persisted_only(): bool {
	global $flx_gql_request_persisted;
	return true === $flx_gql_request_persisted;
}

/**
 * queryId → document, for single and batch HTTP requests.
 */
add_filter(
	'graphql_request_data',
	static function ( $data ) {
		global $flx_gql_request_persisted;
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$is_batch = array_is_list( $data ) && $data && is_array( $data[0] );
		$ops      = $is_batch ? $data : array( $data );
		$all      = (bool) $ops;
		$known    = flx_gql_persisted()['queries'];
		foreach ( $ops as $i => $raw ) {
			if ( ! is_array( $raw ) ) {
				$all = false;
				continue;
			}
			$op = flx_gql_arr( $raw );
			$id = $op['queryId'] ?? ( $op['queryid'] ?? null );
			if ( is_string( $id ) && isset( $known[ $id ] ) ) {
				$op['query'] = $known[ $id ];
				unset( $op['queryId'], $op['queryid'] );
			} else {
				$all = false;
				if ( is_string( $id ) && empty( $op['query'] ) ) {
					// Unknown id and no document: say so instead of graphql-php's "persisted queries not supported".
					$op['query'] = 'query FlxPersistedQueryNotFound { __typename }';
					unset( $op['queryId'], $op['queryid'] );
				}
			}
			$ops[ $i ] = $op;
		}
		$flx_gql_request_persisted = $all;
		return $is_batch ? $ops : $ops[0];
	},
	5
);

if ( class_exists( 'GraphQL\Validator\Rules\ValidationRule' ) ) {
	/**
	 * Refuses documents that are not in a manifest (HTTP requests, strict / admins mode).
	 */
	final class FLXGQL_Allowlist_Rule extends \GraphQL\Validator\Rules\ValidationRule {

		/**
		 * Visitor.
		 *
		 * @param \GraphQL\Validator\QueryValidationContext $context Context.
		 * @return array<string,mixed>
		 */
		public function getVisitor( \GraphQL\Validator\QueryValidationContext $context ): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
			return array(
				\GraphQL\Language\AST\NodeKind::DOCUMENT => static function ( \GraphQL\Language\AST\DocumentNode $node ) use ( $context ): void {
					foreach ( $node->definitions as $def ) {
						if ( $def instanceof \GraphQL\Language\AST\OperationDefinitionNode && $def->name && 'FlxPersistedQueryNotFound' === $def->name->value ) {
							$context->reportError( new \GraphQL\Error\Error( __( 'This query is not known to the site. Update the app or the FluxWP packages.', 'flx-graphql' ), null, null, array(), null, null, array( 'code' => 'PERSISTED_QUERY_NOT_FOUND' ) ) );
							return;
						}
					}
					if ( flx_gql_free_queries_allowed() ) {
						return;
					}
					$allow = flx_gql_persisted()['allow'];
					if ( ! isset( $allow[ flx_gql_document_hash( $node ) ] ) ) {
						$context->reportError( new \GraphQL\Error\Error( __( 'Only queries shipped with FluxWP packages may run on this site.', 'flx-graphql' ), null, null, array(), null, null, array( 'code' => 'QUERY_NOT_ALLOWED' ) ) );
					}
				},
			);
		}
	}
}

add_filter(
	'graphql_validation_rules',
	static function ( $rules ) {
		$rules = is_array( $rules ) ? $rules : array();
		if ( class_exists( 'FLXGQL_Allowlist_Rule' ) && flx_gql_is_request() ) {
			$rules['flx_allowlist'] = new FLXGQL_Allowlist_Rule();
		}
		return $rules;
	}
);
