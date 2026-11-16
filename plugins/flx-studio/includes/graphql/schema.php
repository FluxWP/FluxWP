<?php
/**
 * Flux Studio GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a
 * GraphQL request and only when Flux GraphQL is active. Resolvers call FLXST_Service; REST
 * (flx-studio/v1) calls the same service, so both return the same data under the same access rules.
 *
 * Generation keeps the queue design: flxStudioGenerate only queues items (state QUEUED) and sends them
 * to Flux AI after the response; the screen then follows the jobs with flxStudioPoll (a mutation:
 * it writes finished files into the items) until they are DONE, FAILED or CANCELLED.
 *
 * Files (reference photos, the area-edit mask) come through POST /wp-json/flx/v1/upload
 * (contexts flx-studio/reference and flx-studio/mask); the mutation takes the upload over.
 *
 * @package flx-studio
 */

defined( 'ABSPATH' ) || exit;

/**
 * Item record that is also a GraphQL Node (global ID, node(id:) lookup).
 */
final class FLXST_Item_Node extends FLXST_Item implements FLXGQL_Node {

	/**
	 * The same item as a Node.
	 *
	 * @param FLXST_Item $item Item.
	 */
	public static function of( FLXST_Item $item ): self {
		return new self(
			$item->id,
			$item->kind,
			$item->state,
			$item->job,
			$item->batch,
			$item->parent_id,
			$item->folder_id,
			$item->title,
			$item->prompt,
			$item->model,
			$item->provider,
			$item->params,
			$item->url,
			$item->thumb,
			$item->mime,
			$item->width,
			$item->height,
			$item->bytes,
			$item->cost,
			$item->error,
			$item->favorite,
			$item->attachment_id,
			$item->owner_id,
			$item->owner_name,
			$item->created,
			$item->updated
		);
	}

	#[\Override]
	public function gql_type(): string {
		return 'FlxStudioItem';
	}

	#[\Override]
	public function gql_id(): int {
		return $this->id;
	}
}

/**
 * Batches item lookups of one request; hides items the user may not see.
 */
final class FLXST_Item_Loader extends FLXGQL_Loader {

	/**
	 * @param array<int,int> $ids Ids.
	 * @return array<int,FLXST_Item>
	 */
	#[\Override]
	protected function fetch( array $ids ): array {
		$out = array();
		foreach ( FLXST_Service::items_by_ids( array_values( $ids ) ) as $id => $item ) {
			$out[ $id ] = FLXST_Item_Node::of( $item );
		}
		return $out;
	}

	/**
	 * @param mixed $record Record.
	 */
	#[\Override]
	protected function can_see( $record ): bool {
		return $record instanceof FLXST_Item;
	}
}

/**
 * Service result → value, or the WP_Error as a typed GraphQL error.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxst_gql_ok( $r ) {
	if ( $r instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $r );
	}
	return $r;
}

/**
 * Typed parent value of a field.
 *
 * @template T of object
 * @param mixed           $source Parent.
 * @param class-string<T> $class  Expected class.
 * @return T
 * @throws FLXGQL_Error When the parent has another type.
 */
function flxst_gql_src( $source, string $class ) {
	if ( ! $source instanceof $class ) {
		throw flx_gql_error( 'INTERNAL' );
	}
	return $source;
}

/**
 * Items as Nodes (global ID, databaseId).
 *
 * @param list<FLXST_Item> $items Items.
 * @return list<FLXST_Item_Node>
 */
function flxst_gql_nodes( array $items ): array {
	return array_map( array( 'FLXST_Item_Node', 'of' ), $items );
}

/**
 * Resolver that reads one public property of the parent.
 *
 * @param class-string $class Parent class.
 * @param string       $prop  Property.
 */
function flxst_gql_prop( string $class, string $prop ): Closure {
	return static fn( $source ) => flxst_gql_src( $source, $class )->{$prop};
}

/**
 * Resolver that reads a property holding 0 for "none" and gives null for it.
 *
 * @param class-string $class Parent class.
 * @param string       $prop  Property.
 */
function flxst_gql_id_prop( string $class, string $prop ): Closure {
	return static function ( $source ) use ( $class, $prop ): ?int {
		$v = flxst_gql_src( $source, $class )->{$prop};
		return is_int( $v ) && $v > 0 ? $v : null;
	};
}

/**
 * Resolver that reads a string property and gives null for an empty one.
 *
 * @param class-string $class Parent class.
 * @param string       $prop  Property.
 */
function flxst_gql_text_prop( string $class, string $prop ): Closure {
	return static function ( $source ) use ( $class, $prop ): ?string {
		$v = flxst_gql_src( $source, $class )->{$prop};
		return is_string( $v ) && '' !== $v ? $v : null;
	};
}

/**
 * Resolver for a Unix time property.
 *
 * @param class-string $class Parent class.
 * @param string       $prop  Property.
 */
function flxst_gql_time_prop( string $class, string $prop ): Closure {
	return static function ( $source ) use ( $class, $prop ): ?array {
		$v = flxst_gql_src( $source, $class )->{$prop};
		return is_int( $v ) && $v > 0 ? flx_gql_datetime( $v ) : null;
	};
}

/**
 * Capability of every Studio read and of the user's own writes: Studio users and administrators.
 */
function flxst_gql_use(): Closure {
	return flx_gql_any_cap( 'flxst_use', 'flxst_manage' );
}

/**
 * List of ids from an input value.
 *
 * @param mixed $raw Input list.
 * @return list<int>
 */
function flxst_gql_ids( $raw ): array {
	$out = array();
	foreach ( is_array( $raw ) ? $raw : array() as $v ) {
		$id = flx_gql_int( $v );
		if ( $id > 0 ) {
			$out[] = $id;
		}
	}
	return $out;
}

/**
 * References of an input value as the service reads them.
 *
 * @param mixed $raw Input list ({type, id, role}).
 * @return list<array{type: string, id: int, role: string}>
 */
function flxst_gql_refs( $raw ): array {
	$out = array();
	foreach ( is_array( $raw ) ? $raw : array() as $r ) {
		$r     = flx_gql_arr( $r );
		$out[] = array(
			'type' => flx_gql_str( $r['type'] ?? '' ),
			'id'   => flx_gql_int( $r['id'] ?? 0 ),
			'role' => flx_gql_str( $r['role'] ?? '' ),
		);
	}
	return $out;
}

/**
 * Takes an upload over and returns the stored file.
 *
 * @param mixed  $upload_id Upload ID.
 * @param string $context   Upload context.
 * @return array<string,mixed>
 * @throws FLXGQL_Error When the upload is missing or invalid.
 */
function flxst_gql_take_upload( $upload_id, string $context ): array {
	$up = flx_gql_upload_take( flx_gql_str( $upload_id ), $context );
	if ( $up instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $up );
	}
	return $up;
}

/* ------------------------------------------------------------------ Enums */

flx_gql_enum(
	'FlxStudioKind',
	array(
		'description' => __( 'What a generation makes.', 'flx-studio' ),
		'values'      => array(
			'IMAGE' => array( 'value' => 'image', 'description' => __( 'An image.', 'flx-studio' ) ),
			'VIDEO' => array( 'value' => 'video', 'description' => __( 'A video.', 'flx-studio' ) ),
		),
	)
);

flx_gql_enum(
	'FlxStudioItemState',
	array(
		'description' => __( 'Where a generation is.', 'flx-studio' ),
		'values'      => array(
			'QUEUED'    => array( 'value' => 'queued', 'description' => __( 'Waiting to be sent to Flux AI.', 'flx-studio' ) ),
			'RUNNING'   => array( 'value' => 'running', 'description' => __( 'Being made; follow it with flxStudioPoll.', 'flx-studio' ) ),
			'DONE'      => array( 'value' => 'done', 'description' => __( 'Finished; the file is ready.', 'flx-studio' ) ),
			'FAILED'    => array( 'value' => 'failed', 'description' => __( 'Failed; see the error.', 'flx-studio' ) ),
			'CANCELLED' => array( 'value' => 'cancelled', 'description' => __( 'Cancelled by the user.', 'flx-studio' ) ),
		),
	)
);

flx_gql_enum(
	'FlxStudioRefType',
	array(
		'description' => __( 'What a reference points to.', 'flx-studio' ),
		'values'      => array(
			'ITEM'   => array( 'value' => 'item', 'description' => __( 'A gallery item.', 'flx-studio' ) ),
			'REF'    => array( 'value' => 'ref', 'description' => __( 'A saved reference image.', 'flx-studio' ) ),
			'PERSON' => array( 'value' => 'person', 'description' => __( 'A person (their photos are used).', 'flx-studio' ) ),
		),
	)
);

flx_gql_enum(
	'FlxStudioEnhanceMode',
	array(
		'description' => __( 'What the prompt is written for.', 'flx-studio' ),
		'values'      => array(
			'IMAGE' => array( 'value' => 'image', 'description' => __( 'Image generation.', 'flx-studio' ) ),
			'VIDEO' => array( 'value' => 'video', 'description' => __( 'Video generation.', 'flx-studio' ) ),
			'EDIT'  => array( 'value' => 'edit', 'description' => __( 'Area edit of an image.', 'flx-studio' ) ),
		),
	)
);

flx_gql_enum(
	'FlxStudioBrainstormStyle',
	array(
		'description' => __( 'How Brainstorm writes the ideas.', 'flx-studio' ),
		'values'      => array(
			'SIMPLE'  => array( 'value' => 'simple', 'description' => __( 'Plain prompts.', 'flx-studio' ) ),
			'YOUTUBE' => array( 'value' => 'youtube', 'description' => __( 'Thumbnail-style prompts.', 'flx-studio' ) ),
		),
	)
);

/* ------------------------------------------------------------------ Gallery */

flx_gql_type(
	'FlxStudioItemRef',
	array(
		'description' => __( 'A reference a generation was made with.', 'flx-studio' ),
		'fields'      => array(
			'type'   => array(
				'type'        => 'FlxStudioRefType!',
				'description' => __( 'Item, saved reference or person.', 'flx-studio' ),
				'resolve'     => static fn( $r ): string => flxst_gql_src( $r, FLXST_Item_Ref::class )->type,
			),
			'id'     => array(
				'type'        => 'Int!',
				'description' => __( 'Record ID of the item, reference or person.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item_Ref::class, 'id' ),
			),
			'role'   => array(
				'type'        => 'String',
				'description' => __( 'Role (identity, style, edit …); empty for a general reference.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Item_Ref::class, 'role' ),
			),
			'name'   => array(
				'type'        => 'String',
				'description' => __( 'Person name; empty for images.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Item_Ref::class, 'name' ),
			),
			'thumb'  => array(
				'type'        => 'String',
				'description' => __( 'Small image address.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Item_Ref::class, 'thumb' ),
			),
			'photos' => array(
				'type'        => 'Int!',
				'description' => __( 'Photos used (people only).', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item_Ref::class, 'photos' ),
			),
		),
	)
);

flx_gql_type(
	'FlxStudioItemParams',
	array(
		'description' => __( 'What a generation was asked for.', 'flx-studio' ),
		'fields'      => array(
			'aspect'     => array(
				'type'        => 'String',
				'description' => __( 'Aspect ratio, e.g. 16:9.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Item_Params::class, 'aspect' ),
			),
			'resolution' => array(
				'type'        => 'String',
				'description' => __( 'Resolution, e.g. 2K.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Item_Params::class, 'resolution' ),
			),
			'quality'    => array(
				'type'        => 'String',
				'description' => __( 'Quality level.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Item_Params::class, 'quality' ),
			),
			'count'      => array(
				'type'        => 'Int!',
				'description' => __( 'Images asked for.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item_Params::class, 'n' ),
			),
			'duration'   => array(
				'type'        => 'Int',
				'description' => __( 'Video length in seconds; empty for the model default and for images.', 'flx-studio' ),
				'resolve'     => flxst_gql_id_prop( FLXST_Item_Params::class, 'duration' ),
			),
			'audio'      => array(
				'type'        => 'Boolean',
				'description' => __( 'Video with sound; empty for images.', 'flx-studio' ),
				'resolve'     => static fn( $p ): ?bool => flxst_gql_src( $p, FLXST_Item_Params::class )->audio,
			),
			'progress'   => array(
				'type'        => 'Float!',
				'description' => __( 'Progress from 0 to 1 while running.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item_Params::class, 'progress' ),
			),
			'refs'       => array(
				'type'        => '[FlxStudioItemRef!]!',
				'description' => __( 'References used.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item_Params::class, 'refs' ),
			),
			'draft'      => array(
				'type'        => 'String',
				'description' => __( 'First draft before Enhance.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Item_Params::class, 'draft' ),
			),
			'edit'       => array(
				'type'        => 'Boolean!',
				'description' => __( 'An area edit (masked).', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item_Params::class, 'edit' ),
			),
			'enhanced'   => array(
				'type'        => 'Boolean!',
				'description' => __( 'The prompt came from Enhance.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item_Params::class, 'enhanced' ),
			),
			'idea'       => array(
				'type'        => 'String',
				'description' => __( 'Brainstorm idea title.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Item_Params::class, 'idea' ),
			),
			'protected'  => array(
				'type'        => 'Boolean!',
				'description' => __( 'The provider marked the file protected.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item_Params::class, 'protected' ),
			),
		),
	)
);

flx_gql_type(
	'FlxStudioItem',
	array(
		'description' => __( 'One generation (image or video) of the gallery.', 'flx-studio' ),
		'node'        => FLXST_Item_Loader::class,
		'fields'      => array(
			'kind'         => array(
				'type'        => 'FlxStudioKind!',
				'description' => __( 'Image or video.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item::class, 'kind' ),
			),
			'state'        => array(
				'type'        => 'FlxStudioItemState!',
				'description' => __( 'Where the generation is.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item::class, 'state' ),
			),
			'job'          => array(
				'type'        => 'String',
				'description' => __( 'Flux AI job; flxd_… until sent, then flxm_…. Pass it to flxStudioPoll while running.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Item::class, 'job' ),
			),
			'batch'        => array(
				'type'        => 'String',
				'description' => __( 'Brainstorm batch the item belongs to.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Item::class, 'batch' ),
			),
			'parentId'     => array(
				'type'        => 'Int',
				'description' => __( 'Source item of an area edit.', 'flx-studio' ),
				'resolve'     => flxst_gql_id_prop( FLXST_Item::class, 'parent_id' ),
			),
			'folderId'     => array(
				'type'        => 'Int',
				'description' => __( 'Folder; empty for none.', 'flx-studio' ),
				'resolve'     => flxst_gql_id_prop( FLXST_Item::class, 'folder_id' ),
			),
			'title'        => array(
				'type'        => 'String!',
				'description' => __( 'Title.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item::class, 'title' ),
			),
			'prompt'       => array(
				'type'        => 'String!',
				'description' => __( 'Prompt.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item::class, 'prompt' ),
			),
			'model'        => array(
				'type'        => 'String!',
				'description' => __( 'Model ID.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item::class, 'model' ),
			),
			'provider'     => array(
				'type'        => 'String',
				'description' => __( 'Provider that made it.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Item::class, 'provider' ),
			),
			'params'       => array(
				'type'        => 'FlxStudioItemParams!',
				'description' => __( 'What the generation was asked for.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item::class, 'params' ),
			),
			'url'          => array(
				'type'        => 'String',
				'description' => __( 'File address; empty until done.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Item::class, 'url' ),
			),
			'thumb'        => array(
				'type'        => 'String',
				'description' => __( 'Small image (falls back to the file).', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Item::class, 'thumb' ),
			),
			'mime'         => array(
				'type'        => 'String',
				'description' => __( 'MIME type of the file.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Item::class, 'mime' ),
			),
			'width'        => array(
				'type'        => 'Int!',
				'description' => __( 'Width in pixels.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item::class, 'width' ),
			),
			'height'       => array(
				'type'        => 'Int!',
				'description' => __( 'Height in pixels.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item::class, 'height' ),
			),
			'bytes'        => array(
				'type'        => 'Int!',
				'description' => __( 'File size in bytes.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item::class, 'bytes' ),
			),
			'cost'         => array(
				'type'        => 'Float!',
				'description' => __( 'Cost in USD.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item::class, 'cost' ),
			),
			'error'        => array(
				'type'        => 'String',
				'description' => __( 'Why it failed.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Item::class, 'error' ),
			),
			'favorite'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'Starred.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item::class, 'favorite' ),
			),
			'attachmentId' => array(
				'type'        => 'Int',
				'description' => __( 'Media Library copy; empty when none.', 'flx-studio' ),
				'resolve'     => flxst_gql_id_prop( FLXST_Item::class, 'attachment_id' ),
			),
			'ownerId'      => array(
				'type'        => 'Int!',
				'description' => __( 'User who made it.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item::class, 'owner_id' ),
			),
			'ownerName'    => array(
				'type'        => 'String!',
				'description' => __( 'Display name of the user who made it.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Item::class, 'owner_name' ),
			),
			'createdAt'    => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'When it was queued.', 'flx-studio' ),
				'resolve'     => flxst_gql_time_prop( FLXST_Item::class, 'created' ),
			),
			'updatedAt'    => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'Last change.', 'flx-studio' ),
				'resolve'     => flxst_gql_time_prop( FLXST_Item::class, 'updated' ),
			),
		),
	)
);
flx_gql_connection( 'FlxStudioItem', __( 'A page of the gallery.', 'flx-studio' ) );

flx_gql_type(
	'FlxStudioFolder',
	array(
		'description' => __( 'A gallery folder.', 'flx-studio' ),
		'fields'      => array(
			'databaseId' => array(
				'type'        => 'Int!',
				'description' => __( 'Folder ID.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Folder::class, 'id' ),
			),
			'parentId'   => array(
				'type'        => 'Int',
				'description' => __( 'Parent folder; empty for the top level.', 'flx-studio' ),
				'resolve'     => flxst_gql_id_prop( FLXST_Folder::class, 'parent_id' ),
			),
			'name'       => array(
				'type'        => 'String!',
				'description' => __( 'Name.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Folder::class, 'name' ),
			),
			'shared'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'Visible to the whole team.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Folder::class, 'shared' ),
			),
			'ownerId'    => array(
				'type'        => 'Int!',
				'description' => __( 'Owner user.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Folder::class, 'owner_id' ),
			),
			'createdAt'  => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'When the folder was made.', 'flx-studio' ),
				'resolve'     => flxst_gql_time_prop( FLXST_Folder::class, 'created' ),
			),
		),
	)
);

/* ------------------------------------------------------------------ People, references, templates */

flx_gql_type(
	'FlxStudioRef',
	array(
		'description' => __( 'A saved reference image or a photo of a person.', 'flx-studio' ),
		'fields'      => array(
			'databaseId' => array(
				'type'        => 'Int!',
				'description' => __( 'Reference ID.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Ref::class, 'id' ),
			),
			'personId'   => array(
				'type'        => 'Int',
				'description' => __( 'Person it belongs to; empty for a saved reference.', 'flx-studio' ),
				'resolve'     => flxst_gql_id_prop( FLXST_Ref::class, 'person_id' ),
			),
			'name'       => array(
				'type'        => 'String!',
				'description' => __( 'Name.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Ref::class, 'name' ),
			),
			'role'       => array(
				'type'        => 'String',
				'description' => __( 'Role (identity, style, product, background, composition, first_frame, last_frame); empty for general.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Ref::class, 'role' ),
			),
			'url'        => array(
				'type'        => 'String!',
				'description' => __( 'File address.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Ref::class, 'url' ),
			),
			'thumb'      => array(
				'type'        => 'String!',
				'description' => __( 'Small image (falls back to the file).', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Ref::class, 'thumb' ),
			),
			'mime'       => array(
				'type'        => 'String',
				'description' => __( 'MIME type.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Ref::class, 'mime' ),
			),
			'width'      => array(
				'type'        => 'Int!',
				'description' => __( 'Width in pixels.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Ref::class, 'width' ),
			),
			'height'     => array(
				'type'        => 'Int!',
				'description' => __( 'Height in pixels.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Ref::class, 'height' ),
			),
			'saved'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'Kept in the saved references list.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Ref::class, 'saved' ),
			),
		),
	)
);

flx_gql_type(
	'FlxStudioPerson',
	array(
		'description' => __( 'A person whose photos are used as identity references.', 'flx-studio' ),
		'fields'      => array(
			'databaseId' => array(
				'type'        => 'Int!',
				'description' => __( 'Person ID.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Person::class, 'id' ),
			),
			'name'       => array(
				'type'        => 'String!',
				'description' => __( 'Name.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Person::class, 'name' ),
			),
			'ownerId'    => array(
				'type'        => 'Int!',
				'description' => __( 'Owner user.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Person::class, 'owner_id' ),
			),
			'photos'     => array(
				'type'        => '[FlxStudioRef!]!',
				'description' => __( 'Photos.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Person::class, 'photos' ),
			),
		),
	)
);

flx_gql_type(
	'FlxStudioTemplateParams',
	array(
		'description' => __( 'Generation settings a template keeps.', 'flx-studio' ),
		'fields'      => array(
			'kind'       => array(
				'type'        => 'FlxStudioKind',
				'description' => __( 'Image or video.', 'flx-studio' ),
				'resolve'     => static function ( $p ): ?string {
					$k = flx_gql_arr( $p )['kind'] ?? '';
					return is_string( $k ) && in_array( $k, array( 'image', 'video' ), true ) ? $k : null;
				},
			),
			'model'      => array(
				'type'        => 'String',
				'description' => __( 'Model ID.', 'flx-studio' ),
				'resolve'     => static fn( $p ): ?string => '' !== flx_gql_str( flx_gql_arr( $p )['model'] ?? '' ) ? flx_gql_str( flx_gql_arr( $p )['model'] ?? '' ) : null,
			),
			'aspect'     => array(
				'type'        => 'String',
				'description' => __( 'Aspect ratio.', 'flx-studio' ),
				'resolve'     => static fn( $p ): ?string => '' !== flx_gql_str( flx_gql_arr( $p )['aspect'] ?? '' ) ? flx_gql_str( flx_gql_arr( $p )['aspect'] ?? '' ) : null,
			),
			'resolution' => array(
				'type'        => 'String',
				'description' => __( 'Resolution.', 'flx-studio' ),
				'resolve'     => static fn( $p ): ?string => '' !== flx_gql_str( flx_gql_arr( $p )['resolution'] ?? '' ) ? flx_gql_str( flx_gql_arr( $p )['resolution'] ?? '' ) : null,
			),
			'quality'    => array(
				'type'        => 'String',
				'description' => __( 'Quality level.', 'flx-studio' ),
				'resolve'     => static fn( $p ): ?string => '' !== flx_gql_str( flx_gql_arr( $p )['quality'] ?? '' ) ? flx_gql_str( flx_gql_arr( $p )['quality'] ?? '' ) : null,
			),
			'size'       => array(
				'type'        => 'String',
				'description' => __( 'Exact size, e.g. 1024x1536.', 'flx-studio' ),
				'resolve'     => static fn( $p ): ?string => '' !== flx_gql_str( flx_gql_arr( $p )['size'] ?? '' ) ? flx_gql_str( flx_gql_arr( $p )['size'] ?? '' ) : null,
			),
			'count'      => array(
				'type'        => 'Int',
				'description' => __( 'Images per request.', 'flx-studio' ),
				'resolve'     => static fn( $p ): ?int => isset( flx_gql_arr( $p )['n'] ) ? flx_gql_int( flx_gql_arr( $p )['n'] ) : null,
			),
			'duration'   => array(
				'type'        => 'Int',
				'description' => __( 'Video seconds.', 'flx-studio' ),
				'resolve'     => static fn( $p ): ?int => isset( flx_gql_arr( $p )['duration'] ) ? flx_gql_int( flx_gql_arr( $p )['duration'] ) : null,
			),
			'audio'      => array(
				'type'        => 'Boolean',
				'description' => __( 'Video with sound.', 'flx-studio' ),
				'resolve'     => static fn( $p ): ?bool => array_key_exists( 'audio', flx_gql_arr( $p ) ) ? (bool) flx_gql_arr( $p )['audio'] : null,
			),
			'mode'       => array(
				'type'        => 'String',
				'description' => __( 'Video mode (auto, text, image …).', 'flx-studio' ),
				'resolve'     => static fn( $p ): ?string => '' !== flx_gql_str( flx_gql_arr( $p )['mode'] ?? '' ) ? flx_gql_str( flx_gql_arr( $p )['mode'] ?? '' ) : null,
			),
			'refs'       => array(
				'type'        => '[FlxStudioItemRef!]!',
				'description' => __( 'References the template adds.', 'flx-studio' ),
				'resolve'     => static function ( $p ): array {
					$out = array();
					$raw = flx_gql_arr( $p )['refs'] ?? array();
					foreach ( is_array( $raw ) ? $raw : array() as $r ) {
						$out[] = FLXST_Item_Ref::from_array( flx_gql_arr( $r ) );
					}
					return $out;
				},
			),
		),
	)
);

flx_gql_type(
	'FlxStudioTemplate',
	array(
		'description' => __( 'A saved set of generation settings.', 'flx-studio' ),
		'fields'      => array(
			'databaseId' => array(
				'type'        => 'Int!',
				'description' => __( 'Template ID.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Template::class, 'id' ),
			),
			'name'       => array(
				'type'        => 'String!',
				'description' => __( 'Name.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Template::class, 'name' ),
			),
			'prompt'     => array(
				'type'        => 'String!',
				'description' => __( 'Prompt.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Template::class, 'prompt' ),
			),
			'params'     => array(
				'type'        => 'FlxStudioTemplateParams!',
				'description' => __( 'Kept settings.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Template::class, 'params' ),
			),
			'shared'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'Visible to the whole team.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Template::class, 'shared' ),
			),
			'ownerId'    => array(
				'type'        => 'Int!',
				'description' => __( 'Owner user.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Template::class, 'owner_id' ),
			),
		),
	)
);

/* ------------------------------------------------------------------ Catalog, settings, workspace */

flx_gql_type(
	'FlxStudioPrice',
	array(
		'description' => __( 'Price of a model at one resolution.', 'flx-studio' ),
		'fields'      => array(
			'resolution' => array(
				'type'        => 'String!',
				'description' => __( 'Resolution key.', 'flx-studio' ),
				'resolve'     => static fn( $p ): string => flx_gql_str( flx_gql_arr( $p )['resolution'] ?? '' ),
			),
			'usd'        => array(
				'type'        => 'Float!',
				'description' => __( 'USD per image, or per second for video.', 'flx-studio' ),
				'resolve'     => static function ( $p ): float {
					$usd = flx_gql_arr( $p )['usd'] ?? 0;
					return is_numeric( $usd ) ? (float) $usd : 0.0;
				},
			),
		),
	)
);

flx_gql_type(
	'FlxStudioModel',
	array(
		'description' => __( 'An image or video model of the Flux AI catalog.', 'flx-studio' ),
		'fields'      => array(
			'id'          => array(
				'type'        => 'String!',
				'description' => __( 'Model ID.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Model::class, 'id' ),
			),
			'label'       => array(
				'type'        => 'String!',
				'description' => __( 'Name.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Model::class, 'label' ),
			),
			'kind'        => array(
				'type'        => 'FlxStudioKind!',
				'description' => __( 'Image or video.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Model::class, 'kind' ),
			),
			'routes'      => array(
				'type'        => '[String!]!',
				'description' => __( 'Providers that can serve the model.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Model::class, 'routes' ),
			),
			'providers'   => array(
				'type'        => '[String!]!',
				'description' => __( 'Providers with a key entered.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Model::class, 'providers' ),
			),
			'auto'        => array(
				'type'        => 'String',
				'description' => __( 'Provider used for Auto.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Model::class, 'auto' ),
			),
			'aspects'     => array(
				'type'        => '[String!]!',
				'description' => __( 'Aspect ratios.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Model::class, 'aspects' ),
			),
			'resolutions' => array(
				'type'        => '[String!]!',
				'description' => __( 'Resolutions.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Model::class, 'resolutions' ),
			),
			'qualities'   => array(
				'type'        => '[String!]!',
				'description' => __( 'Quality levels.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Model::class, 'qualities' ),
			),
			'durationMin' => array(
				'type'        => 'Int',
				'description' => __( 'Shortest video in seconds; empty for images.', 'flx-studio' ),
				'resolve'     => static fn( $m ): ?int => flxst_gql_src( $m, FLXST_Model::class )->duration[0] ?? null,
			),
			'durationMax' => array(
				'type'        => 'Int',
				'description' => __( 'Longest video in seconds; empty for images.', 'flx-studio' ),
				'resolve'     => static fn( $m ): ?int => flxst_gql_src( $m, FLXST_Model::class )->duration[1] ?? null,
			),
			'durationDefault' => array(
				'type'        => 'Int',
				'description' => __( 'Default video length in seconds; empty for images.', 'flx-studio' ),
				'resolve'     => static fn( $m ): ?int => flxst_gql_src( $m, FLXST_Model::class )->duration[2] ?? null,
			),
			'maxRefs'     => array(
				'type'        => 'Int!',
				'description' => __( 'Most references one request takes.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Model::class, 'max_refs' ),
			),
			'maxCount'    => array(
				'type'        => 'Int!',
				'description' => __( 'Most images per request.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Model::class, 'max_n' ),
			),
			'mask'        => array(
				'type'        => 'Boolean!',
				'description' => __( 'Area editing is supported.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Model::class, 'mask' ),
			),
			'prices'      => array(
				'type'        => '[FlxStudioPrice!]!',
				'description' => __( 'Price by resolution.', 'flx-studio' ),
				'resolve'     => static function ( $m ): array {
					$out = array();
					foreach ( flxst_gql_src( $m, FLXST_Model::class )->price as $res => $usd ) {
						$out[] = array( 'resolution' => (string) $res, 'usd' => $usd );
					}
					return $out;
				},
			),
		),
	)
);

flx_gql_type(
	'FlxStudioProvider',
	array(
		'description' => __( 'A generation provider (fal.ai, EvoLink …).', 'flx-studio' ),
		'fields'      => array(
			'id'    => array(
				'type'        => 'String!',
				'description' => __( 'Provider ID.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Provider::class, 'id' ),
			),
			'label' => array(
				'type'        => 'String!',
				'description' => __( 'Name.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Provider::class, 'label' ),
			),
			'ready' => array(
				'type'        => 'Boolean!',
				'description' => __( 'A key is entered in Flux AI.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Provider::class, 'ready' ),
			),
		),
	)
);

flx_gql_type(
	'FlxStudioCatalog',
	array(
		'description' => __( 'Models and providers of Flux AI (no keys).', 'flx-studio' ),
		'fields'      => array(
			'models'       => array(
				'type'        => '[FlxStudioModel!]!',
				'description' => __( 'Image and video models.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Catalog::class, 'models' ),
			),
			'providers'    => array(
				'type'        => '[FlxStudioProvider!]!',
				'description' => __( 'Providers.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Catalog::class, 'providers' ),
			),
			'defaultImage' => array(
				'type'        => 'String',
				'description' => __( 'Default image model of Flux AI.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Catalog::class, 'default_image' ),
			),
			'defaultVideo' => array(
				'type'        => 'String',
				'description' => __( 'Default video model of Flux AI.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Catalog::class, 'default_video' ),
			),
		),
	)
);

flx_gql_type(
	'FlxStudioSettings',
	array(
		'description' => __( 'Studio settings. API keys and the spending limit are in Flux AI.', 'flx-studio' ),
		'fields'      => array(
			'imageModel'   => array(
				'type'        => 'String',
				'description' => __( 'Image model the screen starts with.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Settings::class, 'image_model' ),
			),
			'videoModel'   => array(
				'type'        => 'String',
				'description' => __( 'Video model the screen starts with.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Settings::class, 'video_model' ),
			),
			'textModel'    => array(
				'type'        => 'String',
				'description' => __( 'Model for Enhance and Brainstorm; empty uses the Flux AI default. Studio administrators only.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Settings::class, 'text_model' ),
			),
			'maxCount'     => array(
				'type'        => 'Int!',
				'description' => __( 'Most images per request (1–8).', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Settings::class, 'max_n' ),
			),
			'maxBatch'     => array(
				'type'        => 'Int!',
				'description' => __( 'Most ideas per Brainstorm (1–40).', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Settings::class, 'max_batch' ),
			),
			'sharedPeople' => array(
				'type'        => 'Boolean!',
				'description' => __( 'People are shared with every Studio user.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Settings::class, 'shared_people' ),
			),
		),
	)
);

flx_gql_type(
	'FlxStudioBudget',
	array(
		'description' => __( 'One Flux AI spending limit.', 'flx-studio' ),
		'fields'      => array(
			'period' => array(
				'type'        => 'String!',
				'description' => __( 'Period key (daily …).', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Budget::class, 'period' ),
			),
			'label'  => array(
				'type'        => 'String!',
				'description' => __( 'Period name.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Budget::class, 'label' ),
			),
			'limit'  => array(
				'type'        => 'Float!',
				'description' => __( 'Limit in USD; 0 is no limit.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Budget::class, 'limit' ),
			),
			'spent'  => array(
				'type'        => 'Float!',
				'description' => __( 'Spent in USD.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Budget::class, 'spent' ),
			),
			'pct'    => array(
				'type'        => 'Float!',
				'description' => __( 'Spent against the limit, in percent.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Budget::class, 'pct' ),
			),
			'over'   => array(
				'type'        => 'Boolean!',
				'description' => __( 'The limit is reached; generating is paused.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Budget::class, 'over' ),
			),
		),
	)
);

flx_gql_type(
	'FlxStudioWorkspace',
	array(
		'description' => __( 'Everything the Studio screen needs when it opens.', 'flx-studio' ),
		'fields'      => array(
			'version'    => array(
				'type'        => 'String!',
				'description' => __( 'Plugin version.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Workspace::class, 'version' ),
			),
			'userId'     => array(
				'type'        => 'Int!',
				'description' => __( 'The signed-in user.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Workspace::class, 'user_id' ),
			),
			'userName'   => array(
				'type'        => 'String!',
				'description' => __( 'Display name of the signed-in user.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Workspace::class, 'user_name' ),
			),
			'canManage'  => array(
				'type'        => 'Boolean!',
				'description' => __( 'Sees every gallery, the dashboard and the settings.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Workspace::class, 'can_manage' ),
			),
			'canLibrary' => array(
				'type'        => 'Boolean!',
				'description' => __( 'May add files to the Media Library.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Workspace::class, 'can_library' ),
			),
			'aiMedia'    => array(
				'type'        => 'Boolean!',
				'description' => __( 'Flux AI image and video backend is installed and on.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Workspace::class, 'ai_media' ),
			),
			'aiText'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'A Flux AI text model is ready (Enhance, Brainstorm).', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Workspace::class, 'ai_text' ),
			),
			'setupUrl'   => array(
				'type'        => 'String',
				'description' => __( 'Flux AI settings page; empty unless the user is an administrator.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Workspace::class, 'setup_url' ),
			),
			'catalog'    => array(
				'type'        => 'FlxStudioCatalog!',
				'description' => __( 'Models and providers.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Workspace::class, 'catalog' ),
			),
			'settings'   => array(
				'type'        => 'FlxStudioSettings!',
				'description' => __( 'Studio settings.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Workspace::class, 'settings' ),
			),
			'folders'    => array(
				'type'        => '[FlxStudioFolder!]!',
				'description' => __( 'Folders the user sees.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Workspace::class, 'folders' ),
			),
			'people'     => array(
				'type'        => '[FlxStudioPerson!]!',
				'description' => __( 'People the user sees, with their photos.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Workspace::class, 'people' ),
			),
			'refs'       => array(
				'type'        => '[FlxStudioRef!]!',
				'description' => __( 'Saved references.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Workspace::class, 'refs' ),
			),
			'templates'  => array(
				'type'        => '[FlxStudioTemplate!]!',
				'description' => __( 'Templates of the user and the shared ones.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Workspace::class, 'templates' ),
			),
			'budget'     => array(
				'type'        => '[FlxStudioBudget!]!',
				'description' => __( 'Flux AI spending limits.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Workspace::class, 'budget' ),
			),
		),
	)
);

/* ------------------------------------------------------------------ Dashboard */

flx_gql_type(
	'FlxStudioStatsDay',
	array(
		'description' => __( 'Dashboard: one day.', 'flx-studio' ),
		'fields'      => array(
			'date'   => array(
				'type'        => 'String!',
				'description' => __( 'Day (YYYY-MM-DD).', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_Day::class, 'date' ),
			),
			'image'  => array(
				'type'        => 'Int!',
				'description' => __( 'Images made.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_Day::class, 'image' ),
			),
			'video'  => array(
				'type'        => 'Int!',
				'description' => __( 'Videos made.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_Day::class, 'video' ),
			),
			'failed' => array(
				'type'        => 'Int!',
				'description' => __( 'Failures.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_Day::class, 'failed' ),
			),
			'cost'   => array(
				'type'        => 'Float!',
				'description' => __( 'Cost in USD.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_Day::class, 'cost' ),
			),
		),
	)
);

flx_gql_type(
	'FlxStudioStatsRow',
	array(
		'description' => __( 'Dashboard: a model or provider.', 'flx-studio' ),
		'fields'      => array(
			'id'     => array(
				'type'        => 'String!',
				'description' => __( 'Model or provider ID.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_Row::class, 'id' ),
			),
			'label'  => array(
				'type'        => 'String!',
				'description' => __( 'Name.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_Row::class, 'label' ),
			),
			'kind'   => array(
				'type'        => 'FlxStudioKind',
				'description' => __( 'Image or video; empty for providers.', 'flx-studio' ),
				'resolve'     => static function ( $r ): ?string {
					$k = flxst_gql_src( $r, FLXST_Stats_Row::class )->kind;
					return '' !== $k ? $k : null;
				},
			),
			'done'   => array(
				'type'        => 'Int!',
				'description' => __( 'Made.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_Row::class, 'done' ),
			),
			'failed' => array(
				'type'        => 'Int!',
				'description' => __( 'Failed.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_Row::class, 'failed' ),
			),
			'cost'   => array(
				'type'        => 'Float!',
				'description' => __( 'Cost in USD.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_Row::class, 'cost' ),
			),
		),
	)
);

flx_gql_type(
	'FlxStudioStatsUser',
	array(
		'description' => __( 'Dashboard: one person\'s usage.', 'flx-studio' ),
		'fields'      => array(
			'userId' => array(
				'type'        => 'Int!',
				'description' => __( 'User ID.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_User::class, 'id' ),
			),
			'name'   => array(
				'type'        => 'String!',
				'description' => __( 'Display name.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_User::class, 'name' ),
			),
			'avatar' => array(
				'type'        => 'String',
				'description' => __( 'Avatar address.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Stats_User::class, 'avatar' ),
			),
			'image'  => array(
				'type'        => 'Int!',
				'description' => __( 'Images made.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_User::class, 'image' ),
			),
			'video'  => array(
				'type'        => 'Int!',
				'description' => __( 'Videos made.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_User::class, 'video' ),
			),
			'cost'   => array(
				'type'        => 'Float!',
				'description' => __( 'Cost in USD.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_User::class, 'cost' ),
			),
			'lastAt' => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'Last generation.', 'flx-studio' ),
				'resolve'     => flxst_gql_time_prop( FLXST_Stats_User::class, 'last' ),
			),
		),
	)
);

flx_gql_type(
	'FlxStudioStatsError',
	array(
		'description' => __( 'Dashboard: a recent failure.', 'flx-studio' ),
		'fields'      => array(
			'itemId'    => array(
				'type'        => 'Int!',
				'description' => __( 'Item ID.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_Error::class, 'id' ),
			),
			'model'     => array(
				'type'        => 'String!',
				'description' => __( 'Model name.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_Error::class, 'model' ),
			),
			'provider'  => array(
				'type'        => 'String',
				'description' => __( 'Provider name.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Stats_Error::class, 'provider' ),
			),
			'error'     => array(
				'type'        => 'String!',
				'description' => __( 'Message.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats_Error::class, 'error' ),
			),
			'createdAt' => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'When it failed.', 'flx-studio' ),
				'resolve'     => flxst_gql_time_prop( FLXST_Stats_Error::class, 'created' ),
			),
		),
	)
);

flx_gql_type(
	'FlxStudioStats',
	array(
		'description' => __( 'Usage and spending dashboard for Studio administrators.', 'flx-studio' ),
		'fields'      => array(
			'days'       => array(
				'type'        => 'Int!',
				'description' => __( 'Days covered.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'days' ),
			),
			'image'      => array(
				'type'        => 'Int!',
				'description' => __( 'Images made.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'image' ),
			),
			'video'      => array(
				'type'        => 'Int!',
				'description' => __( 'Videos made.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'video' ),
			),
			'failed'     => array(
				'type'        => 'Int!',
				'description' => __( 'Failures.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'failed' ),
			),
			'cancelled'  => array(
				'type'        => 'Int!',
				'description' => __( 'Cancelled.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'cancelled' ),
			),
			'pending'    => array(
				'type'        => 'Int!',
				'description' => __( 'Waiting or running.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'pending' ),
			),
			'users'      => array(
				'type'        => 'Int!',
				'description' => __( 'People who generated.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'users' ),
			),
			'bytes'      => array(
				'type'        => 'Float!',
				'description' => __( 'Storage used, in bytes.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'bytes' ),
			),
			'spendTotal' => array(
				'type'        => 'Float!',
				'description' => __( 'Spent in USD.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'spend_total' ),
			),
			'spendMedia' => array(
				'type'        => 'Float!',
				'description' => __( 'Spent on images and videos, in USD.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'spend_media' ),
			),
			'spendText'  => array(
				'type'        => 'Float!',
				'description' => __( 'Spent on text (Enhance, Brainstorm), in USD.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'spend_text' ),
			),
			'spendEstimated' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Some amounts are estimates.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'spend_est' ),
			),
			'spendFrom'  => array(
				'type'        => 'String!',
				'description' => __( 'Where the amounts come from: flx-ai or flx-studio.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'spend_from' ),
			),
			'daily'      => array(
				'type'        => '[FlxStudioStatsDay!]!',
				'description' => __( 'Per day.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'daily' ),
			),
			'models'     => array(
				'type'        => '[FlxStudioStatsRow!]!',
				'description' => __( 'Per model.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'models' ),
			),
			'providers'  => array(
				'type'        => '[FlxStudioStatsRow!]!',
				'description' => __( 'Per provider.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'providers' ),
			),
			'people'     => array(
				'type'        => '[FlxStudioStatsUser!]!',
				'description' => __( 'Per person.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'people' ),
			),
			'errors'     => array(
				'type'        => '[FlxStudioStatsError!]!',
				'description' => __( 'Recent failures.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'errors' ),
			),
			'budget'     => array(
				'type'        => '[FlxStudioBudget!]!',
				'description' => __( 'Flux AI spending limits.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Stats::class, 'budget' ),
			),
			'logUrl'     => array(
				'type'        => 'String',
				'description' => __( 'Flux AI log page; empty without access.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Stats::class, 'log_url' ),
			),
		),
	)
);

/* ------------------------------------------------------------------ Results of actions */

flx_gql_type(
	'FlxStudioIdea',
	array(
		'description' => __( 'One Brainstorm idea.', 'flx-studio' ),
		'fields'      => array(
			'title'  => array(
				'type'        => 'String!',
				'description' => __( 'Short title.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Idea::class, 'title' ),
			),
			'prompt' => array(
				'type'        => 'String!',
				'description' => __( 'Production prompt.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Idea::class, 'prompt' ),
			),
		),
	)
);

flx_gql_type(
	'FlxStudioLibraryResult',
	array(
		'description' => __( 'An item added to the Media Library.', 'flx-studio' ),
		'fields'      => array(
			'attachmentId' => array(
				'type'        => 'Int!',
				'description' => __( 'Attachment ID.', 'flx-studio' ),
				'resolve'     => flxst_gql_prop( FLXST_Library_Result::class, 'attachment' ),
			),
			'editUrl'      => array(
				'type'        => 'String',
				'description' => __( 'Edit screen of the attachment.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Library_Result::class, 'edit_url' ),
			),
			'url'          => array(
				'type'        => 'String',
				'description' => __( 'File address.', 'flx-studio' ),
				'resolve'     => flxst_gql_text_prop( FLXST_Library_Result::class, 'url' ),
			),
		),
	)
);

/* ------------------------------------------------------------------ Queries */

flx_gql_query(
	'flxStudio',
	array(
		'type'        => 'FlxStudioWorkspace!',
		'description' => __( 'Everything the Studio screen needs when it opens: models, settings, folders, people, references, templates and spending limits.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'resolve'     => static function (): FLXST_Workspace {
			flx_gql_cache_tag( array( FLXST_Service::TAG_FOLDERS, FLXST_Service::TAG_PEOPLE, FLXST_Service::TAG_REFS, FLXST_Service::TAG_TEMPLATES, FLXST_Service::TAG_SETTINGS ) );
			return FLXST_Service::workspace();
		},
	)
);

flx_gql_query(
	'flxStudioBudget',
	array(
		'type'        => '[FlxStudioBudget!]!',
		'description' => __( 'Flux AI spending limits, counted fresh.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'resolve'     => static fn(): array => FLXST_Service::budget( true ),
	)
);

flx_gql_query(
	'flxStudioItems',
	array(
		'type'        => 'FlxStudioItemConnection!',
		'description' => __( 'The gallery, newest first: the user\'s own items and those in shared folders (every item for Studio administrators with allOwners).', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'args'        => flx_gql_connection_args(
			array(
				'folderId'  => array(
					'type'        => 'Int',
					'description' => __( 'Only this folder (0 is the items outside any folder); leave out for every folder.', 'flx-studio' ),
				),
				'kind'      => array(
					'type'        => 'FlxStudioKind',
					'description' => __( 'Only images or only videos.', 'flx-studio' ),
				),
				'favorite'  => array(
					'type'        => 'Boolean',
					'description' => __( 'Only starred items.', 'flx-studio' ),
				),
				'batch'     => array(
					'type'        => 'String',
					'description' => __( 'Only one Brainstorm batch.', 'flx-studio' ),
				),
				'search'    => array(
					'type'        => 'String',
					'description' => __( 'Words in the prompt, title or model.', 'flx-studio' ),
				),
				'allOwners' => array(
					'type'        => 'Boolean',
					'description' => __( 'Studio administrators: every user\'s items.', 'flx-studio' ),
				),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			flx_gql_cache_tag( FLXST_Service::TAG_ITEMS );
			$filter = array(
				'kind'       => flx_gql_str( $args['kind'] ?? '' ),
				'favorite'   => ! empty( $args['favorite'] ),
				'batch'      => flx_gql_str( $args['batch'] ?? '' ),
				'search'     => flx_gql_str( $args['search'] ?? '' ),
				'all_owners' => ! empty( $args['allOwners'] ),
			);
			if ( isset( $args['folderId'] ) ) {
				$filter['folder'] = flx_gql_int( $args['folderId'] );
			}
			return flx_gql_paginate( flx_gql_arr( $args ), static fn( int $offset, int $limit ): array => flxst_gql_nodes( FLXST_Service::items( $filter, $offset, $limit ) ) );
		},
	)
);

flx_gql_query(
	'flxStudioItem',
	array(
		'type'        => 'FlxStudioItem',
		'description' => __( 'One gallery item; empty when it is not found or the user may not see it.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'args'        => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Item ID.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): ?FLXST_Item_Node {
			flx_gql_cache_tag( FLXST_Service::TAG_ITEMS );
			$i = FLXST_Service::item( flx_gql_int( $args['id'] ?? 0 ) );
			return $i ? FLXST_Item_Node::of( $i ) : null;
		},
	)
);

flx_gql_query(
	'flxStudioStats',
	array(
		'type'        => 'FlxStudioStats!',
		'description' => __( 'Usage and spending dashboard.', 'flx-studio' ),
		'cap'         => 'flxst_manage',
		'args'        => array(
			'days' => array(
				'type'        => 'Int',
				'description' => __( 'Days to cover (1–365, default 30).', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): FLXST_Stats {
			flx_gql_cache_tag( array( FLXST_Service::TAG_STATS, FLXST_Service::TAG_ITEMS ) );
			return FLXST_Service::stats( max( 1, min( 365, flx_gql_int( $args['days'] ?? 30, 30 ) ) ) );
		},
	)
);

/* ------------------------------------------------------------------ Mutations: gallery */

flx_gql_input(
	'FlxStudioRefInput',
	array(
		'description' => __( 'A reference to generate with.', 'flx-studio' ),
		'fields'      => array(
			'type' => array(
				'type'        => 'FlxStudioRefType!',
				'description' => __( 'Item, saved reference or person.', 'flx-studio' ),
			),
			'id'   => array(
				'type'        => 'Int!',
				'description' => __( 'Record ID.', 'flx-studio' ),
			),
			'role' => array(
				'type'        => 'String',
				'description' => __( 'Role (identity, style, product, background, composition, first_frame, last_frame); empty for general.', 'flx-studio' ),
			),
		),
	)
);

flx_gql_mutation(
	'flxStudioItemUpdate',
	array(
		'description' => __( 'Stars an item, renames it or moves it to a folder. Fields you leave out stay as they are.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'id'       => array(
				'type'        => 'Int!',
				'description' => __( 'Item ID.', 'flx-studio' ),
			),
			'favorite' => array(
				'type'        => 'Boolean',
				'description' => __( 'Starred.', 'flx-studio' ),
			),
			'title'    => array(
				'type'        => 'String',
				'description' => __( 'Title (up to 190 characters).', 'flx-studio' ),
			),
			'folderId' => array(
				'type'        => 'Int',
				'description' => __( 'Folder to move to; 0 for none. A folder the user cannot write to leaves the item where it is.', 'flx-studio' ),
			),
		),
		'output'      => array(
			'item' => array(
				'type'        => 'FlxStudioItem',
				'description' => __( 'The item after the change.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$change = array();
			if ( isset( $input['favorite'] ) ) {
				$change['favorite'] = (bool) $input['favorite'];
			}
			if ( isset( $input['title'] ) ) {
				$change['title'] = flx_gql_str( $input['title'] );
			}
			if ( isset( $input['folderId'] ) ) {
				$change['folder'] = max( 0, flx_gql_int( $input['folderId'] ) );
			}
			$r = FLXST_Service::item_update( flx_gql_int( $input['id'] ?? 0 ), $change );
			return $r instanceof WP_Error ? $r : array( 'item' => FLXST_Item_Node::of( $r ) );
		},
	)
);

flx_gql_mutation(
	'flxStudioItemsDelete',
	array(
		'description' => __( 'Deletes items and their files; running ones are cancelled at the provider. Items that are not the user\'s own are skipped.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'ids' => array(
				'type'        => '[Int!]!',
				'description' => __( 'Item IDs (at most 500 are looked at).', 'flx-studio' ),
			),
		),
		'output'      => array(
			'deletedIds' => array(
				'type'        => '[Int!]',
				'description' => __( 'Items that were deleted.', 'flx-studio' ),
			),
		),
		'resolve'     => static fn( array $input ): array => array( 'deletedIds' => FLXST_Service::items_delete( flxst_gql_ids( $input['ids'] ?? array() ) ) ),
	)
);

flx_gql_mutation(
	'flxStudioItemsMove',
	array(
		'description' => __( 'Moves items to a folder. Items that are not the user\'s own are skipped.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'ids'      => array(
				'type'        => '[Int!]!',
				'description' => __( 'Item IDs.', 'flx-studio' ),
			),
			'folderId' => array(
				'type'        => 'Int!',
				'description' => __( 'Target folder; 0 for none.', 'flx-studio' ),
			),
		),
		'output'      => array(
			'movedIds' => array(
				'type'        => '[Int!]',
				'description' => __( 'Items that were moved.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXST_Service::items_move( flxst_gql_ids( $input['ids'] ?? array() ), max( 0, flx_gql_int( $input['folderId'] ?? 0 ) ) );
			return $r instanceof WP_Error ? $r : array( 'movedIds' => $r );
		},
	)
);

flx_gql_mutation(
	'flxStudioItemToLibrary',
	array(
		'description' => __( 'Copies a finished item into the WordPress Media Library.', 'flx-studio' ),
		'cap'         => flx_gql_all_caps( 'flxst_use', 'upload_files' ),
		'input'       => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Item ID.', 'flx-studio' ),
			),
		),
		'output'      => array(
			'library' => array(
				'type'        => 'FlxStudioLibraryResult',
				'description' => __( 'The attachment that was made.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXST_Service::item_to_library( flx_gql_int( $input['id'] ?? 0 ) );
			return $r instanceof WP_Error ? $r : array( 'library' => $r );
		},
	)
);

/* ------------------------------------------------------------------ Mutations: generation */

flx_gql_mutation(
	'flxStudioGenerate',
	array(
		'description' => __( 'Queues a generation: one item per output, sent to Flux AI after the response. Follow the items with flxStudioPoll until they finish. For an area edit give parentId (the image) and maskUploadId.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'kind'         => array(
				'type'        => 'FlxStudioKind',
				'description' => __( 'Image or video; the model decides when left out.', 'flx-studio' ),
			),
			'model'        => array(
				'type'        => 'String',
				'description' => __( 'Model ID; empty uses the Studio default.', 'flx-studio' ),
			),
			'provider'     => array(
				'type'        => 'String',
				'description' => __( 'Provider ID, or auto.', 'flx-studio' ),
			),
			'prompt'       => array(
				'type'        => 'String!',
				'description' => __( 'What to generate.', 'flx-studio' ),
			),
			'count'        => array(
				'type'        => 'Int',
				'description' => __( 'Images to make (video is always one); limited by the model and the Studio setting.', 'flx-studio' ),
			),
			'aspect'       => array(
				'type'        => 'String',
				'description' => __( 'Aspect ratio.', 'flx-studio' ),
			),
			'resolution'   => array(
				'type'        => 'String',
				'description' => __( 'Resolution.', 'flx-studio' ),
			),
			'quality'      => array(
				'type'        => 'String',
				'description' => __( 'Quality level.', 'flx-studio' ),
			),
			'size'         => array(
				'type'        => 'String',
				'description' => __( 'Exact size such as 1024x1536.', 'flx-studio' ),
			),
			'duration'     => array(
				'type'        => 'Int',
				'description' => __( 'Video length in seconds.', 'flx-studio' ),
			),
			'audio'        => array(
				'type'        => 'Boolean',
				'description' => __( 'Video with sound (default on).', 'flx-studio' ),
			),
			'mode'         => array(
				'type'        => 'String',
				'description' => __( 'Video mode (auto, text, image …).', 'flx-studio' ),
			),
			'refs'         => array(
				'type'        => '[FlxStudioRefInput!]',
				'description' => __( 'References to generate with.', 'flx-studio' ),
			),
			'folderId'     => array(
				'type'        => 'Int',
				'description' => __( 'Folder for the results; 0 or a folder the user cannot write to means none.', 'flx-studio' ),
			),
			'title'        => array(
				'type'        => 'String',
				'description' => __( 'Title for the results.', 'flx-studio' ),
			),
			'batch'        => array(
				'type'        => 'String',
				'description' => __( 'Brainstorm batch the results belong to.', 'flx-studio' ),
			),
			'idea'         => array(
				'type'        => 'String',
				'description' => __( 'Brainstorm idea title.', 'flx-studio' ),
			),
			'enhanced'     => array(
				'type'        => 'Boolean',
				'description' => __( 'The prompt came from Enhance.', 'flx-studio' ),
			),
			'draft'        => array(
				'type'        => 'String',
				'description' => __( 'First draft before Enhance.', 'flx-studio' ),
			),
			'parentId'     => array(
				'type'        => 'Int',
				'description' => __( 'Image to edit (area edit).', 'flx-studio' ),
			),
			'maskUploadId' => array(
				'type'        => 'String',
				'description' => __( 'Upload ID of the mask PNG (context flx-studio/mask); transparent pixels are changed. Needs parentId.', 'flx-studio' ),
			),
			'layers'       => array(
				'type'        => '[String!]',
				'description' => __( 'Tools used in the mask editor (kept with the item).', 'flx-studio' ),
			),
		),
		'output'      => array(
			'items' => array(
				'type'        => '[FlxStudioItem!]',
				'description' => __( 'The queued items (state QUEUED).', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$in = array(
				'kind'       => flx_gql_str( $input['kind'] ?? '' ),
				'model'      => flx_gql_str( $input['model'] ?? '' ),
				'provider'   => flx_gql_str( $input['provider'] ?? 'auto' ),
				'prompt'     => flx_gql_str( $input['prompt'] ?? '' ),
				'n'          => flx_gql_int( $input['count'] ?? 1, 1 ),
				'aspect'     => flx_gql_str( $input['aspect'] ?? '' ),
				'resolution' => flx_gql_str( $input['resolution'] ?? '' ),
				'quality'    => flx_gql_str( $input['quality'] ?? '' ),
				'size'       => flx_gql_str( $input['size'] ?? '' ),
				'duration'   => flx_gql_int( $input['duration'] ?? 0 ),
				'mode'       => flx_gql_str( $input['mode'] ?? 'auto' ),
				'refs'       => flxst_gql_refs( $input['refs'] ?? array() ),
				'folder'     => flx_gql_int( $input['folderId'] ?? 0 ),
				'title'      => flx_gql_str( $input['title'] ?? '' ),
				'batch'      => flx_gql_str( $input['batch'] ?? '' ),
				'idea'       => flx_gql_str( $input['idea'] ?? '' ),
				'enhanced'   => ! empty( $input['enhanced'] ),
				'draft'      => flx_gql_str( $input['draft'] ?? '' ),
				'parent'     => flx_gql_int( $input['parentId'] ?? 0 ),
				'layers'     => array_values( array_map( 'flx_gql_str', is_array( $input['layers'] ?? null ) ? $input['layers'] : array() ) ),
			);
			if ( isset( $input['audio'] ) ) {
				$in['audio'] = (bool) $input['audio'];
			}
			if ( ! empty( $input['maskUploadId'] ) ) {
				$up   = flxst_gql_take_upload( $input['maskUploadId'], 'flx-studio/mask' );
				$data = file_get_contents( flx_gql_str( $up['path'] ?? '' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( false === $data || '' === $data ) {
					throw flx_gql_error( 'INVALID_INPUT', __( 'The mask is empty.', 'flx-studio' ), 'maskUploadId' );
				}
				$in['mask'] = 'data:image/png;base64,' . base64_encode( $data ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			}
			$r = FLXST_Service::generate( $in );
			return $r instanceof WP_Error ? $r : array( 'items' => flxst_gql_nodes( $r ) );
		},
	)
);

flx_gql_mutation(
	'flxStudioPoll',
	array(
		'description' => __( 'Follows running jobs (at most 8): asks Flux AI and writes finished files into the items. A mutation because it changes items; the screen calls it every few seconds while something runs.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'jobs' => array(
				'type'        => '[String!]!',
				'description' => __( 'Job IDs of the items (flxd_… or flxm_…).', 'flx-studio' ),
			),
		),
		'output'      => array(
			'items' => array(
				'type'        => '[FlxStudioItem!]',
				'description' => __( 'Items of the jobs after the refresh.', 'flx-studio' ),
			),
		),
		'resolve'     => static fn( array $input ): array => array( 'items' => flxst_gql_nodes( FLXST_Service::poll( array_values( array_map( 'flx_gql_str', is_array( $input['jobs'] ?? null ) ? $input['jobs'] : array() ) ) ) ) ),
	)
);

flx_gql_mutation(
	'flxStudioCancel',
	array(
		'description' => __( 'Cancels a job: one not yet sent is dropped, a running one is stopped at the provider.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'job' => array(
				'type'        => 'String!',
				'description' => __( 'Job ID of the item.', 'flx-studio' ),
			),
		),
		'output'      => array(
			'items' => array(
				'type'        => '[FlxStudioItem!]',
				'description' => __( 'Items of the job after the cancel.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXST_Service::cancel( flx_gql_str( $input['job'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'items' => flxst_gql_nodes( $r ) );
		},
	)
);

flx_gql_mutation(
	'flxStudioEnhance',
	array(
		'description' => __( 'Turns a short draft into a detailed prompt with the Flux AI text model. Uses Flux AI\'s budget.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'prompt'     => array(
				'type'        => 'String!',
				'description' => __( 'Draft.', 'flx-studio' ),
			),
			'mode'       => array(
				'type'        => 'FlxStudioEnhanceMode',
				'description' => __( 'What the prompt is for (default image).', 'flx-studio' ),
			),
			'refs'       => array(
				'type'        => '[FlxStudioRefInput!]',
				'description' => __( 'References the prompt should take into account.', 'flx-studio' ),
			),
			'modelLabel' => array(
				'type'        => 'String',
				'description' => __( 'Name of the model the prompt is for.', 'flx-studio' ),
			),
		),
		'output'      => array(
			'prompt' => array(
				'type'        => 'String',
				'description' => __( 'The detailed prompt.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXST_Service::enhance( flx_gql_str( $input['prompt'] ?? '' ), flx_gql_str( $input['mode'] ?? 'image' ), flxst_gql_refs( $input['refs'] ?? array() ), flx_gql_str( $input['modelLabel'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'prompt' => $r );
		},
	)
);

flx_gql_mutation(
	'flxStudioBrainstorm',
	array(
		'description' => __( 'Writes several different prompts from one idea with the Flux AI text model. Uses Flux AI\'s budget.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'brief' => array(
				'type'        => 'String!',
				'description' => __( 'The idea.', 'flx-studio' ),
			),
			'count' => array(
				'type'        => 'Int',
				'description' => __( 'Ideas wanted; limited by the Studio setting.', 'flx-studio' ),
			),
			'style' => array(
				'type'        => 'FlxStudioBrainstormStyle',
				'description' => __( 'How the ideas are written (default simple).', 'flx-studio' ),
			),
			'refs'  => array(
				'type'        => '[FlxStudioRefInput!]',
				'description' => __( 'References the ideas should take into account.', 'flx-studio' ),
			),
			'kind'  => array(
				'type'        => 'FlxStudioKind',
				'description' => __( 'Ideas for images or videos (default image).', 'flx-studio' ),
			),
		),
		'output'      => array(
			'ideas' => array(
				'type'        => '[FlxStudioIdea!]',
				'description' => __( 'The ideas.', 'flx-studio' ),
			),
			'batch' => array(
				'type'        => 'String',
				'description' => __( 'Batch ID to pass to flxStudioGenerate so the results group together.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXST_Service::brainstorm( flx_gql_str( $input['brief'] ?? '' ), flx_gql_int( $input['count'] ?? 0 ), flx_gql_str( $input['style'] ?? 'simple' ), flxst_gql_refs( $input['refs'] ?? array() ), flx_gql_str( $input['kind'] ?? 'image' ) );
			return $r;
		},
	)
);

/* ------------------------------------------------------------------ Mutations: folders */

flx_gql_mutation(
	'flxStudioFolderCreate',
	array(
		'description' => __( 'Adds a folder. A parent the user cannot write to becomes the top level.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'name'     => array(
				'type'        => 'String!',
				'description' => __( 'Name (up to 120 characters).', 'flx-studio' ),
			),
			'parentId' => array(
				'type'        => 'Int',
				'description' => __( 'Parent folder; empty for the top level.', 'flx-studio' ),
			),
			'shared'   => array(
				'type'        => 'Boolean',
				'description' => __( 'Visible to the whole team.', 'flx-studio' ),
			),
		),
		'output'      => array(
			'folder' => array(
				'type'        => 'FlxStudioFolder',
				'description' => __( 'The new folder.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXST_Service::folder_create( flx_gql_str( $input['name'] ?? '' ), flx_gql_int( $input['parentId'] ?? 0 ), ! empty( $input['shared'] ) );
			return $r instanceof WP_Error ? $r : array( 'folder' => $r );
		},
	)
);

flx_gql_mutation(
	'flxStudioFolderUpdate',
	array(
		'description' => __( 'Renames, shares or moves a folder (the owner or a Studio administrator). Fields you leave out stay as they are.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'id'       => array(
				'type'        => 'Int!',
				'description' => __( 'Folder ID.', 'flx-studio' ),
			),
			'name'     => array(
				'type'        => 'String',
				'description' => __( 'New name; an empty name changes nothing.', 'flx-studio' ),
			),
			'shared'   => array(
				'type'        => 'Boolean',
				'description' => __( 'Visible to the whole team.', 'flx-studio' ),
			),
			'parentId' => array(
				'type'        => 'Int',
				'description' => __( 'New parent; 0 for the top level. A folder cannot move into itself.', 'flx-studio' ),
			),
		),
		'output'      => array(
			'folder' => array(
				'type'        => 'FlxStudioFolder',
				'description' => __( 'The folder after the change.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXST_Service::folder_update(
				flx_gql_int( $input['id'] ?? 0 ),
				isset( $input['name'] ) ? flx_gql_str( $input['name'] ) : null,
				isset( $input['shared'] ) ? (bool) $input['shared'] : null,
				isset( $input['parentId'] ) ? max( 0, flx_gql_int( $input['parentId'] ) ) : null
			);
			return $r instanceof WP_Error ? $r : array( 'folder' => $r );
		},
	)
);

flx_gql_mutation(
	'flxStudioFolderDelete',
	array(
		'description' => __( 'Deletes a folder; its items and subfolders move up one level (no file is deleted).', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Folder ID.', 'flx-studio' ),
			),
		),
		'output'      => array(
			'deletedId' => array(
				'type'        => 'Int',
				'description' => __( 'Folder that was deleted.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$id = flx_gql_int( $input['id'] ?? 0 );
			$r  = FLXST_Service::folder_delete( $id );
			return $r instanceof WP_Error ? $r : array( 'deletedId' => $id );
		},
	)
);

/* ------------------------------------------------------------------ Mutations: people and references */

flx_gql_mutation(
	'flxStudioPersonCreate',
	array(
		'description' => __( 'Adds a person whose photos can be used as identity references.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'name' => array(
				'type'        => 'String!',
				'description' => __( 'Name (up to 120 characters).', 'flx-studio' ),
			),
		),
		'output'      => array(
			'person' => array(
				'type'        => 'FlxStudioPerson',
				'description' => __( 'The new person.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXST_Service::person_create( flx_gql_str( $input['name'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'person' => $r );
		},
	)
);

flx_gql_mutation(
	'flxStudioPersonRename',
	array(
		'description' => __( 'Renames a person (the owner or a Studio administrator). An empty name changes nothing.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'id'   => array(
				'type'        => 'Int!',
				'description' => __( 'Person ID.', 'flx-studio' ),
			),
			'name' => array(
				'type'        => 'String!',
				'description' => __( 'New name.', 'flx-studio' ),
			),
		),
		'output'      => array(
			'person' => array(
				'type'        => 'FlxStudioPerson',
				'description' => __( 'The person after the change.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXST_Service::person_rename( flx_gql_int( $input['id'] ?? 0 ), flx_gql_str( $input['name'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'person' => $r );
		},
	)
);

flx_gql_mutation(
	'flxStudioPersonDelete',
	array(
		'description' => __( 'Deletes a person and their photos; earlier generations stay.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Person ID.', 'flx-studio' ),
			),
		),
		'output'      => array(
			'deletedId' => array(
				'type'        => 'Int',
				'description' => __( 'Person that was deleted.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$id = flx_gql_int( $input['id'] ?? 0 );
			$r  = FLXST_Service::person_delete( $id );
			return $r instanceof WP_Error ? $r : array( 'deletedId' => $id );
		},
	)
);

flx_gql_mutation(
	'flxStudioRefCreate',
	array(
		'description' => __( 'Saves an uploaded image as a reference or as a photo of a person. Upload it first with POST /wp-json/flx/v1/upload (context flx-studio/reference).', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'uploadId' => array(
				'type'        => 'String!',
				'description' => __( 'Upload ID.', 'flx-studio' ),
			),
			'personId' => array(
				'type'        => 'Int',
				'description' => __( 'Person the photo belongs to; empty for a plain reference.', 'flx-studio' ),
			),
			'name'     => array(
				'type'        => 'String',
				'description' => __( 'Name (up to 190 characters).', 'flx-studio' ),
			),
			'role'     => array(
				'type'        => 'String',
				'description' => __( 'Role (identity, style, product, background, composition, first_frame, last_frame); empty for general.', 'flx-studio' ),
			),
			'saved'    => array(
				'type'        => 'Boolean',
				'description' => __( 'Keep in the saved references list (always for a person\'s photo).', 'flx-studio' ),
			),
		),
		'output'      => array(
			'ref' => array(
				'type'        => 'FlxStudioRef',
				'description' => __( 'The new reference.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$person = max( 0, flx_gql_int( $input['personId'] ?? 0 ) );
			if ( ! FLXST_Service::person_usable( $person ) ) {
				throw flx_gql_error( 'NOT_FOUND' );
			}
			$up   = flxst_gql_take_upload( $input['uploadId'] ?? '', 'flx-studio/reference' );
			$stored = flxst_store_file( array( 'tmp' => flx_gql_str( $up['path'] ?? '' ), 'name' => flx_gql_str( $up['name'] ?? 'reference' ) ), 'refs', FLXST_IMAGE_MIMES, 25 );
			if ( $stored instanceof WP_Error ) {
				return $stored;
			}
			$f    = flx_gql_arr( $stored );
			$file = array(
				'path'   => flx_gql_str( $f['path'] ?? '' ),
				'url'    => flx_gql_str( $f['url'] ?? '' ),
				'mime'   => flx_gql_str( $f['mime'] ?? '' ),
				'width'  => flx_gql_int( $f['width'] ?? 0 ),
				'height' => flx_gql_int( $f['height'] ?? 0 ),
				'bytes'  => flx_gql_int( $f['bytes'] ?? 0 ),
			);
			$r = FLXST_Service::ref_create( $file, $person, flx_gql_str( $input['name'] ?? '' ), flx_gql_str( $input['role'] ?? '' ), ! empty( $input['saved'] ) );
			return $r instanceof WP_Error ? $r : array( 'ref' => $r );
		},
	)
);

flx_gql_mutation(
	'flxStudioRefUpdate',
	array(
		'description' => __( 'Renames a reference, sets its role, or keeps / drops it in the saved list. Fields you leave out stay as they are.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'id'    => array(
				'type'        => 'Int!',
				'description' => __( 'Reference ID.', 'flx-studio' ),
			),
			'name'  => array(
				'type'        => 'String',
				'description' => __( 'Name.', 'flx-studio' ),
			),
			'role'  => array(
				'type'        => 'String',
				'description' => __( 'Role key; empty for general.', 'flx-studio' ),
			),
			'saved' => array(
				'type'        => 'Boolean',
				'description' => __( 'Kept in the saved references list.', 'flx-studio' ),
			),
		),
		'output'      => array(
			'ref' => array(
				'type'        => 'FlxStudioRef',
				'description' => __( 'The reference after the change.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXST_Service::ref_update(
				flx_gql_int( $input['id'] ?? 0 ),
				isset( $input['name'] ) ? flx_gql_str( $input['name'] ) : null,
				isset( $input['role'] ) ? flx_gql_str( $input['role'] ) : null,
				isset( $input['saved'] ) ? (bool) $input['saved'] : null
			);
			return $r instanceof WP_Error ? $r : array( 'ref' => $r );
		},
	)
);

flx_gql_mutation(
	'flxStudioRefDelete',
	array(
		'description' => __( 'Deletes a reference or a person\'s photo and its files.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Reference ID.', 'flx-studio' ),
			),
		),
		'output'      => array(
			'deletedId' => array(
				'type'        => 'Int',
				'description' => __( 'Reference that was deleted.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$id = flx_gql_int( $input['id'] ?? 0 );
			$r  = FLXST_Service::ref_delete( $id );
			return $r instanceof WP_Error ? $r : array( 'deletedId' => $id );
		},
	)
);

/* ------------------------------------------------------------------ Mutations: templates, settings */

flx_gql_input(
	'FlxStudioTemplateParamsInput',
	array(
		'description' => __( 'Generation settings a template keeps. Unknown settings are not kept.', 'flx-studio' ),
		'fields'      => array(
			'kind'       => array(
				'type'        => 'FlxStudioKind',
				'description' => __( 'Image or video.', 'flx-studio' ),
			),
			'model'      => array(
				'type'        => 'String',
				'description' => __( 'Model ID.', 'flx-studio' ),
			),
			'aspect'     => array(
				'type'        => 'String',
				'description' => __( 'Aspect ratio.', 'flx-studio' ),
			),
			'resolution' => array(
				'type'        => 'String',
				'description' => __( 'Resolution.', 'flx-studio' ),
			),
			'quality'    => array(
				'type'        => 'String',
				'description' => __( 'Quality level.', 'flx-studio' ),
			),
			'size'       => array(
				'type'        => 'String',
				'description' => __( 'Exact size.', 'flx-studio' ),
			),
			'count'      => array(
				'type'        => 'Int',
				'description' => __( 'Images per request.', 'flx-studio' ),
			),
			'duration'   => array(
				'type'        => 'Int',
				'description' => __( 'Video seconds.', 'flx-studio' ),
			),
			'audio'      => array(
				'type'        => 'Boolean',
				'description' => __( 'Video with sound.', 'flx-studio' ),
			),
			'mode'       => array(
				'type'        => 'String',
				'description' => __( 'Video mode.', 'flx-studio' ),
			),
			'refs'       => array(
				'type'        => '[FlxStudioRefInput!]',
				'description' => __( 'References the template adds.', 'flx-studio' ),
			),
		),
	)
);

flx_gql_mutation(
	'flxStudioTemplateCreate',
	array(
		'description' => __( 'Saves the current generation settings as a template.', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'name'   => array(
				'type'        => 'String!',
				'description' => __( 'Name (up to 120 characters).', 'flx-studio' ),
			),
			'prompt' => array(
				'type'        => 'String',
				'description' => __( 'Prompt.', 'flx-studio' ),
			),
			'params' => array(
				'type'        => 'FlxStudioTemplateParamsInput',
				'description' => __( 'Settings to keep.', 'flx-studio' ),
			),
			'shared' => array(
				'type'        => 'Boolean',
				'description' => __( 'Visible to the whole team.', 'flx-studio' ),
			),
		),
		'output'      => array(
			'templates' => array(
				'type'        => '[FlxStudioTemplate!]',
				'description' => __( 'All templates after the change.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$p      = flx_gql_arr( $input['params'] ?? array() );
			$params = array();
			foreach ( array( 'kind', 'model', 'aspect', 'resolution', 'quality', 'size', 'mode' ) as $k ) {
				if ( isset( $p[ $k ] ) ) {
					$params[ $k ] = flx_gql_str( $p[ $k ] );
				}
			}
			foreach ( array( 'count' => 'n', 'duration' => 'duration' ) as $from => $to ) {
				if ( isset( $p[ $from ] ) ) {
					$params[ $to ] = flx_gql_int( $p[ $from ] );
				}
			}
			if ( isset( $p['audio'] ) ) {
				$params['audio'] = (bool) $p['audio'];
			}
			if ( isset( $p['refs'] ) ) {
				$params['refs'] = flxst_gql_refs( $p['refs'] );
			}
			$r = FLXST_Service::template_create( flx_gql_str( $input['name'] ?? '' ), flx_gql_str( $input['prompt'] ?? '' ), $params, ! empty( $input['shared'] ) );
			return $r instanceof WP_Error ? $r : array( 'templates' => $r );
		},
	)
);

flx_gql_mutation(
	'flxStudioTemplateDelete',
	array(
		'description' => __( 'Deletes a template (the owner or a Studio administrator).', 'flx-studio' ),
		'cap'         => flxst_gql_use(),
		'input'       => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Template ID.', 'flx-studio' ),
			),
		),
		'output'      => array(
			'templates' => array(
				'type'        => '[FlxStudioTemplate!]',
				'description' => __( 'All templates after the change.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXST_Service::template_delete( flx_gql_int( $input['id'] ?? 0 ) );
			return $r instanceof WP_Error ? $r : array( 'templates' => $r );
		},
	)
);

flx_gql_mutation(
	'flxStudioSettingsUpdate',
	array(
		'description' => __( 'Saves the Studio settings (Studio administrators). Fields you leave out stay as they are; values are clamped. API keys and the spending limit are in Flux AI.', 'flx-studio' ),
		'cap'         => 'flxst_manage',
		'input'       => array(
			'imageModel'   => array(
				'type'        => 'String',
				'description' => __( 'Image model the screen starts with; empty uses the Flux AI default.', 'flx-studio' ),
			),
			'videoModel'   => array(
				'type'        => 'String',
				'description' => __( 'Video model the screen starts with; empty uses the Flux AI default.', 'flx-studio' ),
			),
			'textModel'    => array(
				'type'        => 'String',
				'description' => __( 'Model for Enhance and Brainstorm; empty uses the Flux AI default.', 'flx-studio' ),
			),
			'maxCount'     => array(
				'type'        => 'Int',
				'description' => __( 'Most images per request (1–8).', 'flx-studio' ),
			),
			'maxBatch'     => array(
				'type'        => 'Int',
				'description' => __( 'Most ideas per Brainstorm (1–40).', 'flx-studio' ),
			),
			'sharedPeople' => array(
				'type'        => 'Boolean',
				'description' => __( 'Share people with every Studio user.', 'flx-studio' ),
			),
		),
		'output'      => array(
			'settings' => array(
				'type'        => 'FlxStudioSettings',
				'description' => __( 'Settings after the change.', 'flx-studio' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$map = array(
				'imageModel'   => 'image_model',
				'videoModel'   => 'video_model',
				'textModel'    => 'text_model',
				'maxCount'     => 'max_n',
				'maxBatch'     => 'max_batch',
				'sharedPeople' => 'shared_people',
			);
			$in  = array();
			foreach ( $map as $from => $to ) {
				if ( array_key_exists( $from, $input ) && null !== $input[ $from ] ) {
					$in[ $to ] = $input[ $from ];
				}
			}
			return array( 'settings' => FLXST_Service::settings_update( $in ) );
		},
	)
);
