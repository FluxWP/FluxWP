<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Rest\V1;

use FluxWP\Blocks\Design_Tokens\Document\Mutator;
use FluxWP\Blocks\Design_Tokens\Document\Reserved_Namespace;
use FluxWP\Blocks\Design_Tokens\Document\Token_Reference_Policy;
use FluxWP\Blocks\Design_Tokens\Document\User_Primitive_Index;
use FluxWP\Blocks\Design_Tokens\Registry\Token_Registry;
use FluxWP\Blocks\Design_Tokens\Rest\V1\Contracts\Controller;
use FluxWP\Blocks\Design_Tokens\Registry\Contracts\Baseline_Document;
use FluxWP\Blocks\Design_Tokens\Schema\Vocabulary\Layers;
use FluxWP\Blocks\Design_Tokens\Schema\Vocabulary\Token_Type;
use FluxWP\Blocks\Utils\Cast;
use WP_Error;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for user-primitive sub-resources: preview-references, create, delete, and rename.
 *
 */
final class User_Primitives_Controller extends Controller {

	/**
	 * The request parameter that carries the token library slug.
	 *
	 *
	 * @var string
	 */
	private const SLUG_PARAM = 'slug';

	/**
	 * The request parameter that carries the user-primitive canonical dot-path id.
	 *
	 *
	 * @var string
	 */
	private const ID_PARAM = 'id';

	/**
	 * The slug path segment shared by routes that scope to a single document.
	 *
	 *
	 * @var string
	 */
	private const SLUG_ROUTE = '(?P<' . self::SLUG_PARAM . '>[\w-]+)';

	/**
	 * The id path segment for user-primitive sub-resources.
	 * Matches the canonical path format: primitive.<type>.custom.<slug>
	 *
	 *
	 * @var string
	 */
	private const ID_ROUTE = '(?P<' . self::ID_PARAM . '>[\w.-]+)';

	/**
	 * The sub-route segment for user-primitive resources within a document.
	 *
	 *
	 * @var string
	 */
	private const USER_PRIMITIVES_ROUTE = 'user-primitives';

	/**
	 * The sub-route segment for the references preview endpoint.
	 *
	 *
	 * @var string
	 */
	private const REFERENCES_ROUTE = 'references';

	/**
	 * The sub-route segment for the rename endpoint.
	 *
	 *
	 * @var string
	 */
	private const RENAME_ROUTE = 'rename';

	/**
	 * Shared DTCG document write pipeline: load, validate, dry-run, conditional persist.
	 *
	 *
	 * @var Document_Write_Pipeline
	 */
	private Document_Write_Pipeline $pipeline;

	/**
	 * Reads and writes the userPrimitives provenance map in the overrides document.
	 *
	 *
	 * @var User_Primitive_Index
	 */
	private User_Primitive_Index $index;

	/**
	 * Scans documents for alias references to a given primitive id.
	 *
	 *
	 * @var Token_Reference_Policy
	 */
	private Token_Reference_Policy $policy;

	/**
	 * Pure merge / set / remove transforms.
	 *
	 *
	 * @var Mutator
	 */
	private Mutator $mutator;

	/**
	 * Baseline document.
	 *
	 *
	 * @var Baseline_Document
	 */
	private Baseline_Document $baseline;

	/**
	 * Token registry.
	 *
	 *
	 * @var Token_Registry
	 */
	private Token_Registry $registry;

	/**
	 *
	 * @param Document_Write_Pipeline $pipeline The shared DTCG write pipeline.
	 * @param User_Primitive_Index    $index    Reads the userPrimitives provenance map.
	 * @param Token_Reference_Policy  $policy   Scans for alias references to a primitive.
	 * @param Mutator                 $mutator  Pure document transforms.
	 * @param Baseline_Document       $baseline Baseline document.
	 * @param Token_Registry          $registry Token registry.
	 */
	public function __construct(
		Document_Write_Pipeline $pipeline,
		User_Primitive_Index $index,
		Token_Reference_Policy $policy,
		Mutator $mutator,
		Baseline_Document $baseline,
		Token_Registry $registry
	) {
		$this->pipeline  = $pipeline;
		$this->index     = $index;
		$this->policy    = $policy;
		$this->mutator   = $mutator;
		$this->baseline  = $baseline;
		$this->registry  = $registry;
		$this->rest_base = 'documents';
	}

	/**
	 * Register routes for user-primitive sub-resources.
	 *
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/' . self::SLUG_ROUTE . '/' . self::USER_PRIMITIVES_ROUTE . '/' . self::ID_ROUTE . '/' . self::REFERENCES_ROUTE,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_references' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
					'args'                => $this->get_references_params(),
				],
				'schema' => [ $this, 'get_references_schema' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/' . self::SLUG_ROUTE . '/' . self::USER_PRIMITIVES_ROUTE,
			[
				[
					// Creating a single primitive is one addressed write — POST and PUT are identical here.
					'methods'             => [ WP_REST_Server::CREATABLE, 'PUT' ],
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
					'args'                => $this->get_create_params(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/' . self::SLUG_ROUTE . '/' . self::USER_PRIMITIVES_ROUTE . '/' . self::ID_ROUTE,
			[
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'delete_item_permissions_check' ],
					'args'                => $this->get_delete_params(),
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/' . self::SLUG_ROUTE . '/' . self::USER_PRIMITIVES_ROUTE . '/' . self::ID_ROUTE . '/' . self::RENAME_ROUTE,
			[
				[
					// Renaming is one addressed write — POST and PUT are identical here.
					'methods'             => [ WP_REST_Server::CREATABLE, 'PUT' ],
					'callback'            => [ $this, 'rename_item' ],
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
					'args'                => $this->get_rename_params(),
				],
			]
		);
	}

	/**
	 * Return the set of alias references to a user primitive and whether deletion is safe.
	 *
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_references( $request ) {
		$slug  = Cast::to_string( $request->get_param( self::SLUG_PARAM ) );
		$id    = Cast::to_string( $request->get_param( self::ID_PARAM ) );
		$error = $this->pipeline->guard_slug( $slug ) ?? $this->validate_canonical_id( $id );

		if ( $error instanceof WP_Error ) {
			return $error;
		}

		$stored = $this->pipeline->load_document( $slug );

		if ( ! $this->index->has( $stored, $id ) ) {
			return new WP_Error(
				'rest_design_tokens_not_found',
				__( 'That custom token does not exist.', 'flx-blocks' ),
				[
					'status' => WP_Http::NOT_FOUND,
					'id'     => $id,
				]
			);
		}

		$references  = $this->policy->find( $stored, $id );
		$all_support = $this->policy->all_supported( $references );
		$ref_payload = [];

		foreach ( $references as $ref ) {
			$ref_payload[] = [
				'kind'      => $ref->kind,
				'path'      => $ref->path,
				'supported' => $ref->supported,
				'action'    => $ref->supported ? 'revert_to_baseline' : 'unsupported',
			];
		}

		return new WP_REST_Response(
			[
				'id'         => $id,
				'label'      => $this->index->label_for( $stored, $id ) ?? '',
				'version'    => $this->pipeline->get_version( $slug ),
				'deletable'  => $all_support,
				'references' => $ref_payload,
			],
			WP_Http::OK
		);
	}

	/**
	 * Create a new user-defined primitive.
	 *
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$slug    = Cast::to_string( $request->get_param( self::SLUG_PARAM ) );
		$version = Cast::to_string( $request->get_param( 'version' ) );

		$error = $this->pipeline->guard_slug( $slug )
			?? $this->pipeline->guard_version( $slug, $version );

		if ( $error instanceof WP_Error ) {
			return $error;
		}

		$slug_input = Cast::to_string( $request->get_param( 'id' ) );

		if ( ! Reserved_Namespace::is_valid_slug( $slug_input ) ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'The id must be a lowercase kebab-case slug with no dots (e.g. my-color).', 'flx-blocks' ),
				[
					'status' => WP_Http::BAD_REQUEST,
					'id'     => $slug_input,
				]
			);
		}

		$type  = Cast::to_string( $request->get_param( '$type' ) );
		$value = $request->get_param( '$value' );
		$label = $this->sanitize_label( Cast::to_string( $request->get_param( 'label' ) ), $slug_input );

		$group_key = Cast::to_string( $request->get_param( 'group' ) );

		if ( $group_key !== '' && ! Reserved_Namespace::is_valid_slug( $group_key ) ) {
			return new WP_Error(
				'rest_fxb_invalid_group',
				__( 'The group must be a lowercase kebab-case key.', 'flx-blocks' ),
				[
					'status' => WP_Http::BAD_REQUEST,
					'group'  => $group_key,
				]
			);
		}

		if ( $group_key !== '' && $this->registry->group_label_for( $group_key ) === null ) {
			return new WP_Error(
				'rest_fxb_unknown_group',
				__( 'That group is not declared for user-created primitives.', 'flx-blocks' ),
				[
					'status' => WP_Http::UNPROCESSABLE_ENTITY,
					'group'  => $group_key,
				]
			);
		}

		if ( ! Reserved_Namespace::is_supported_type( $type ) ) {
			return new WP_Error(
				'rest_design_tokens_type_not_supported',
				__( 'That $type does not support user-created primitives in this version.', 'flx-blocks' ),
				[
					'status' => WP_Http::UNPROCESSABLE_ENTITY,
					'$type'  => $type,
				]
			);
		}

		$canonical = Reserved_Namespace::canonical( $type, $slug_input );
		$stored    = $this->pipeline->load_document( $slug );

		if ( $this->baseline->has( $canonical ) ) {
			return new WP_Error(
				'rest_design_tokens_id_conflict',
				__( 'That id is used by a system token.', 'flx-blocks' ),
				[
					'status' => WP_Http::CONFLICT,
					'id'     => $slug_input,
				]
			);
		}

		if ( $this->index->has( $stored, $canonical ) ) {
			return new WP_Error(
				'rest_design_tokens_id_conflict',
				__( 'A custom token with that id already exists.', 'flx-blocks' ),
				[
					'status' => WP_Http::CONFLICT,
					'id'     => $slug_input,
				]
			);
		}

		$existing = $this->registry->get( $canonical );

		if ( $existing !== null && ! $existing->is_user_created() ) {
			return new WP_Error(
				'rest_design_tokens_id_conflict',
				__( 'That id is reserved by the system.', 'flx-blocks' ),
				[
					'status' => WP_Http::CONFLICT,
					'id'     => $slug_input,
				]
			);
		}

		$leaf      = [
			'$type'  => $type,
			'$value' => $value,
		];
		$candidate = $this->mutator->set( $stored, $canonical, $leaf );
		$candidate = $this->index->add( $candidate, $canonical, $label, $group_key );

		return $this->pipeline->validate_and_save( $candidate, $slug, '', $version, WP_Http::CREATED );
	}

	/**
	 * Delete a user-defined primitive and revert any supported references to it,
	 * whether those references live in the semantic layer or are primitive-layer
	 * direct `$value` aliases.
	 *
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$slug    = Cast::to_string( $request->get_param( self::SLUG_PARAM ) );
		$id      = Cast::to_string( $request->get_param( self::ID_PARAM ) );
		$version = Cast::to_string( $request->get_param( 'version' ) );

		$error = $this->pipeline->guard_slug( $slug )
			?? $this->pipeline->guard_version( $slug, $version )
			?? $this->validate_canonical_id( $id );

		if ( $error instanceof WP_Error ) {
			return $error;
		}

		$existing  = $this->registry->get( $id );
		$is_system = $this->baseline->has( $id ) || ( $existing !== null && ! $existing->is_user_created() );

		if ( $is_system ) {
			return new WP_Error(
				'rest_design_tokens_locked',
				__( 'System primitives cannot be deleted.', 'flx-blocks' ),
				[
					'status' => WP_Http::FORBIDDEN,
					'id'     => $id,
				]
			);
		}

		$stored = $this->pipeline->load_document( $slug );

		if ( ! $this->index->has( $stored, $id ) ) {
			return new WP_Error(
				'rest_design_tokens_not_found',
				__( 'That custom token does not exist.', 'flx-blocks' ),
				[
					'status' => WP_Http::NOT_FOUND,
					'id'     => $id,
				]
			);
		}

		$references = $this->policy->find( $stored, $id );

		if ( ! $this->policy->all_supported( $references ) ) {
			$unsupported = array_filter( $references, fn( $r ) => ! $r->supported );

			return new WP_Error(
				'rest_design_tokens_unsupported_references',
				__( 'This primitive has references that cannot be automatically resolved. Remove them manually before deleting.', 'flx-blocks' ),
				[
					'status'     => WP_Http::UNPROCESSABLE_ENTITY,
					'id'         => $id,
					'references' => array_values(
						array_map(
							fn( $r ) => [
								'kind' => $r->kind,
								'path' => $r->path,
							],
							$unsupported 
						) 
					),
				]
			);
		}

		$candidate = $this->mutator->remove( $stored, $id );
		$candidate = $this->index->remove( $candidate, $id );

		$reverted = [];

		foreach ( $references as $ref ) {
			$candidate  = $this->mutator->remove( $candidate, $ref->path );
			$reverted[] = $ref->path;
		}

		$response = $this->pipeline->validate_and_save( $candidate, $slug, '', $version );

		if ( $response instanceof WP_REST_Response && ! empty( $reverted ) ) {
			/** @var array<string, mixed> $data */
			$data                  = $response->get_data();
			$data['revertedPaths'] = $reverted;
			$response->set_data( $data );
		}

		return $response;
	}

	/**
	 * Rename a user-defined primitive and rewrite direct alias references in the primitive and
	 * semantic layers.
	 *
	 * Rejects the rename when an unsupported reference exists (e.g. a composite field or an
	 * extension preset alias) — the cascade cannot rewrite those, so proceeding would
	 * leave them silently pointing at an id that no longer exists.
	 *
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function rename_item( $request ) {
		$slug     = Cast::to_string( $request->get_param( self::SLUG_PARAM ) );
		$old_id   = Cast::to_string( $request->get_param( self::ID_PARAM ) );
		$new_slug = Cast::to_string( $request->get_param( 'new_id' ) );
		$version  = Cast::to_string( $request->get_param( 'version' ) );

		$error = $this->pipeline->guard_slug( $slug )
			?? $this->pipeline->guard_version( $slug, $version )
			?? $this->validate_canonical_id( $old_id );

		if ( $error instanceof WP_Error ) {
			return $error;
		}

		$stored = $this->pipeline->load_document( $slug );

		if ( ! $this->index->has( $stored, $old_id ) ) {
			return new WP_Error(
				'rest_design_tokens_not_found',
				__( 'That custom token does not exist.', 'flx-blocks' ),
				[
					'status' => WP_Http::NOT_FOUND,
					'id'     => $old_id,
				]
			);
		}

		$references = $this->policy->find( $stored, $old_id );

		if ( ! $this->policy->all_supported( $references ) ) {
			$unsupported = array_filter( $references, fn( $r ) => ! $r->supported );

			return new WP_Error(
				'rest_design_tokens_unsupported_references',
				__( 'This primitive has references that cannot be automatically resolved. Remove them manually before renaming.', 'flx-blocks' ),
				[
					'status'     => WP_Http::UNPROCESSABLE_ENTITY,
					'id'         => $old_id,
					'references' => array_values(
						array_map(
							fn( $r ) => [
								'kind' => $r->kind,
								'path' => $r->path,
							],
							$unsupported
						)
					),
				]
			);
		}

		$old_leaf = $this->pipeline->node_at( $stored, $old_id );
		$type     = is_array( $old_leaf ) ? ( $old_leaf[ Token_Type::get_type_key() ] ?? null ) : null;

		if ( ! is_string( $type ) ) {
			return new WP_Error(
				'rest_design_tokens_corrupt',
				__( 'The stored definition has no $type and cannot be renamed.', 'flx-blocks' ),
				[
					'status' => WP_Http::INTERNAL_SERVER_ERROR,
					'id'     => $old_id,
				]
			);
		}

		$new_id    = Reserved_Namespace::canonical( $type, $new_slug );
		$new_label = $this->sanitize_label( Cast::to_string( $request->get_param( 'label' ) ), $new_slug );

		if ( $this->baseline->has( $new_id ) || $this->index->has( $stored, $new_id ) ) {
			return new WP_Error(
				'rest_design_tokens_id_conflict',
				__( 'The new id is already in use.', 'flx-blocks' ),
				[
					'status' => WP_Http::CONFLICT,
					'new_id' => $new_slug,
				]
			);
		}

		$existing = $this->registry->get( $new_id );

		if ( $existing !== null && ! $existing->is_user_created() ) {
			return new WP_Error(
				'rest_design_tokens_id_conflict',
				__( 'The new id is reserved by the system.', 'flx-blocks' ),
				[
					'status' => WP_Http::CONFLICT,
					'new_id' => $new_slug,
				]
			);
		}

		/** @var array<string, mixed> $old_leaf */
		$candidate = $this->mutator->remove( $stored, $old_id );
		$candidate = $this->mutator->set( $candidate, $new_id, $old_leaf );
		$candidate = $this->index->rename( $candidate, $old_id, $new_id, $new_label );

		$old_alias = '{' . $old_id . '}';
		$new_alias = '{' . $new_id . '}';
		$rewritten = [];
		$candidate = $this->rewrite_aliases( $candidate, $old_alias, $new_alias, $rewritten );

		// The gate above already required every reference to be rewritable, and rewrite_aliases()
		// just rewrote them, so skip the pipeline's own unsupported-reference check: it would
		// otherwise reject the primitive-layer alias this cascade just finished rewriting.
		$response = $this->pipeline->validate_and_save( $candidate, $slug, '', $version, WP_Http::OK, true );

		if ( $response instanceof WP_REST_Response && ! empty( $rewritten ) ) {
			/** @var array<string, mixed> $data */
			$data                   = $response->get_data();
			$data['rewrittenPaths'] = $rewritten;
			$response->set_data( $data );
		}

		return $response;
	}

	/**
	 * The JSON Schema for the references preview response.
	 *
	 *
	 * @return array<string, mixed>
	 */
	public function get_references_schema(): array {
		return $this->add_additional_fields_schema(
			[
				'$schema'    => 'http://json-schema.org/draft-07/schema#',
				'title'      => 'design-token-user-primitive-references',
				'type'       => 'object',
				'properties' => [
					'id'         => [
						'description' => __( 'The canonical dot-path id of the user primitive.', 'flx-blocks' ),
						'type'        => 'string',
						'context'     => [ 'view' ],
						'readonly'    => true,
					],
					'label'      => [
						'description' => __( 'The human-readable label stored in the provenance map.', 'flx-blocks' ),
						'type'        => 'string',
						'context'     => [ 'view' ],
						'readonly'    => true,
					],
					'version'    => [
						'description' => __( 'The cache-busting version hash for the token library.', 'flx-blocks' ),
						'type'        => 'string',
						'context'     => [ 'view' ],
						'readonly'    => true,
					],
					'deletable'  => [
						'description' => __( 'Whether the primitive can be safely deleted (all references are supported).', 'flx-blocks' ),
						'type'        => 'boolean',
						'context'     => [ 'view' ],
						'readonly'    => true,
					],
					'references' => [
						'description' => __( 'All alias references to this primitive found in the document.', 'flx-blocks' ),
						'type'        => 'array',
						'context'     => [ 'view' ],
						'readonly'    => true,
						'items'       => [
							'type'       => 'object',
							'properties' => [
								'kind'      => [ 'type' => 'string' ],
								'path'      => [ 'type' => 'string' ],
								'supported' => [ 'type' => 'boolean' ],
								'action'    => [ 'type' => 'string' ],
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Sanitize and derive a display label from user input or the slug.
	 * Returns a non-empty string of at most 60 characters.
	 *
	 *
	 * @param string $input Raw label from the request.
	 * @param string $slug  Fallback slug to derive from when input is empty.
	 *
	 * @return string
	 */
	private function sanitize_label( string $input, string $slug ): string {
		$label = sanitize_text_field( $input );
		$label = $label !== '' ? $label : ucwords( str_replace( '-', ' ', $slug ) );

		return mb_substr( $label, 0, 60 );
	}

	/**
	 * Replace all direct `$value` alias strings that match `$old_alias` with `$new_alias`
	 * in the primitive and semantic layers of `$document`, the same layers scanned by
	 * `Token_Reference_Policy`. Collects dot-paths of rewritten tokens.
	 *
	 *
	 * @param array<string, mixed> $document
	 * @param string               $old_alias
	 * @param string               $new_alias
	 * @param string[]             $rewritten
	 *
	 * @return array<string, mixed>
	 */
	private function rewrite_aliases( array $document, string $old_alias, string $new_alias, array &$rewritten ): array {
		foreach ( Layers::token_layers() as $layer ) {
			$layer_tree = $document[ $layer ] ?? null;

			if ( ! is_array( $layer_tree ) ) {
				continue;
			}

			$document[ $layer ] = $this->rewrite_node( $layer_tree, $layer, $old_alias, $new_alias, $rewritten );
		}

		return $document;
	}

	/**
	 *
	 * @param array<string, mixed> $node
	 * @param string               $prefix
	 * @param string               $old_alias
	 * @param string               $new_alias
	 * @param string[]             $rewritten
	 *
	 * @return array<string, mixed>
	 */
	private function rewrite_node( array $node, string $prefix, string $old_alias, string $new_alias, array &$rewritten ): array {
		foreach ( $node as $key => $child ) {
			if ( is_string( $key ) && strncmp( $key, '$', 1 ) === 0 ) {
				continue;
			}

			if ( ! is_array( $child ) ) {
				continue;
			}

			$path = $prefix . '.' . $key;

			if ( array_key_exists( '$value', $child ) && $child['$value'] === $old_alias ) {
				$child['$value'] = $new_alias;
				$node[ $key ]    = $child;
				$rewritten[]     = $path;
			} elseif ( ! array_key_exists( '$value', $child ) ) {
				$node[ $key ] = $this->rewrite_node( $child, $path, $old_alias, $new_alias, $rewritten );
			}
		}

		return $node;
	}

	/**
	 * Validate that the supplied id is in the allowed user-primitive namespace.
	 *
	 *
	 * @param string $id
	 *
	 * @return WP_Error|null
	 */
	private function validate_canonical_id( string $id ): ?WP_Error {
		if ( Reserved_Namespace::is_reserved_id( $id ) ) {
			return null;
		}

		return new WP_Error(
			'rest_invalid_param',
			__( 'The id must be a canonical custom primitive path (primitive.<type>.custom.<slug>).', 'flx-blocks' ),
			[
				'status' => WP_Http::BAD_REQUEST,
				'id'     => $id,
			]
		);
	}

	/**
	 * Route arguments for the references endpoint.
	 *
	 *
	 * @return array<string, mixed>
	 */
	private function get_references_params(): array {
		return [
			self::SLUG_PARAM => [
				'description'       => __( 'The token library slug.', 'flx-blocks' ),
				'type'              => 'string',
				'required'          => true,
				'pattern'           => '^[\w-]+$',
				'sanitize_callback' => 'sanitize_key',
			],
			self::ID_PARAM   => [
				'description' => __( 'The canonical dot-path id of the user primitive.', 'flx-blocks' ),
				'type'        => 'string',
				'required'    => true,
				'pattern'     => '^[\w.-]+$',
			],
		];
	}

	/**
	 * Route arguments for the create endpoint.
	 *
	 *
	 * @return array<string, mixed>
	 */
	private function get_create_params(): array {
		return [
			self::SLUG_PARAM => [
				'description'       => __( 'The token library slug.', 'flx-blocks' ),
				'type'              => 'string',
				'required'          => true,
				'pattern'           => '^[\w-]+$',
				'sanitize_callback' => 'sanitize_key',
			],
			'version'        => [
				'description' => __( 'The version token the client last read.', 'flx-blocks' ),
				'type'        => 'string',
				'required'    => true,
			],
			'id'             => [
				'description' => __( 'The terminal slug for the new primitive (kebab-case, no dots).', 'flx-blocks' ),
				'type'        => 'string',
				'required'    => true,
				'pattern'     => Reserved_Namespace::get_slug_pattern(),
			],
			'$type'          => [
				'description' => __( 'The DTCG $type for the new primitive.', 'flx-blocks' ),
				'type'        => 'string',
				'required'    => true,
			],
			'$value'         => [
				'description' => __( 'The DTCG $value for the new primitive.', 'flx-blocks' ),
				'required'    => true,
			],
			'label'          => [
				'description' => __( 'Optional human-readable label.', 'flx-blocks' ),
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
			],
			'group'          => [
				'description' => __( 'Optional stable machine key for the UI group this primitive should join.', 'flx-blocks' ),
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
			],
		];
	}

	/**
	 * Route arguments for the delete endpoint.
	 *
	 *
	 * @return array<string, mixed>
	 */
	private function get_delete_params(): array {
		return [
			self::SLUG_PARAM => [
				'description'       => __( 'The token library slug.', 'flx-blocks' ),
				'type'              => 'string',
				'required'          => true,
				'pattern'           => '^[\w-]+$',
				'sanitize_callback' => 'sanitize_key',
			],
			self::ID_PARAM   => [
				'description' => __( 'The canonical dot-path id of the user primitive to delete.', 'flx-blocks' ),
				'type'        => 'string',
				'required'    => true,
				'pattern'     => '^[\w.-]+$',
			],
			'version'        => [
				'description' => __( 'The version token the client last read.', 'flx-blocks' ),
				'type'        => 'string',
				'required'    => true,
			],
		];
	}

	/**
	 * Route arguments for the rename endpoint.
	 *
	 *
	 * @return array<string, mixed>
	 */
	private function get_rename_params(): array {
		return [
			self::SLUG_PARAM => [
				'description'       => __( 'The token library slug.', 'flx-blocks' ),
				'type'              => 'string',
				'required'          => true,
				'pattern'           => '^[\w-]+$',
				'sanitize_callback' => 'sanitize_key',
			],
			self::ID_PARAM   => [
				'description' => __( 'The canonical dot-path id of the user primitive to rename.', 'flx-blocks' ),
				'type'        => 'string',
				'required'    => true,
				'pattern'     => '^[\w.-]+$',
			],
			'new_id'         => [
				'description' => __( 'The new terminal slug (kebab-case, no dots).', 'flx-blocks' ),
				'type'        => 'string',
				'required'    => true,
				'pattern'     => Reserved_Namespace::get_slug_pattern(),
			],
			'version'        => [
				'description' => __( 'The version token the client last read.', 'flx-blocks' ),
				'type'        => 'string',
				'required'    => true,
			],
			'label'          => [
				'description' => __( 'Optional new human-readable label.', 'flx-blocks' ),
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
			],
		];
	}
}
