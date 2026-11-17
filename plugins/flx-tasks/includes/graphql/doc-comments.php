<?php
/**
 * Flux Tasks GraphQL: document page comments and page protection (lock, access). Same service as the
 * REST endpoints the document editor uses (FLXG_Doc_Service, includes/doc-comments.php).
 *
 * @package FLX_Tasks
 */

defined( 'ABSPATH' ) || exit;

flx_gql_type(
	'FlxTaskDocAnchor',
	array(
		'description' => __( 'Where a document comment points: a text range (block + character offsets) or a whole block.', 'flx-tasks' ),
		'fields'      => array(
			'blockId'    => array(
				'type'        => 'String!',
				'description' => __( 'Block where the range starts.', 'flx-tasks' ),
				'resolve'     => static fn( array $a ): string => FLXG_Val::str( $a, 'b' ),
			),
			'offset'     => array(
				'type'        => 'Int!',
				'description' => __( 'Start offset in the block text.', 'flx-tasks' ),
				'resolve'     => static fn( array $a ): int => FLXG_Val::int( $a, 'o' ),
			),
			'endBlockId' => array(
				'type'        => 'String!',
				'description' => __( 'Block where the range ends.', 'flx-tasks' ),
				'resolve'     => static fn( array $a ): string => FLXG_Val::str( $a, 'eb' ),
			),
			'endOffset'  => array(
				'type'        => 'Int!',
				'description' => __( 'End offset in the end block text.', 'flx-tasks' ),
				'resolve'     => static fn( array $a ): int => FLXG_Val::int( $a, 'eo' ),
			),
			'quote'      => array(
				'type'        => 'String!',
				'description' => __( 'The selected text when the comment was written.', 'flx-tasks' ),
				'resolve'     => static fn( array $a ): string => FLXG_Val::str( $a, 'q' ),
			),
			'kind'       => array(
				'type'        => 'String!',
				'description' => __( 'text (a range) or block (an image, file or table).', 'flx-tasks' ),
				'resolve'     => static fn( array $a ): string => FLXG_Val::str( $a, 'kind' ),
			),
			'label'      => array(
				'type'        => 'String!',
				'description' => __( 'Block label, for example the file name.', 'flx-tasks' ),
				'resolve'     => static fn( array $a ): string => FLXG_Val::str( $a, 'label' ),
			),
		),
	)
);

flx_gql_type(
	'FlxTaskDocComment',
	array(
		'description' => __( 'A comment on a document page: a thread (with an anchor) or a reply.', 'flx-tasks' ),
		'fields'      => array(
			'databaseId' => array(
				'type'        => 'Int!',
				'description' => __( 'Comment ID.', 'flx-tasks' ),
				'resolve'     => static fn( array $c ): int => FLXG_Val::int( $c, 'id' ),
			),
			'pageId'     => array(
				'type'        => 'Int!',
				'description' => __( 'Document page.', 'flx-tasks' ),
				'resolve'     => static fn( array $c ): int => FLXG_Val::int( $c, 'page' ),
			),
			'parentId'   => array(
				'type'        => 'Int!',
				'description' => __( 'Thread of a reply; 0 for a thread.', 'flx-tasks' ),
				'resolve'     => static fn( array $c ): int => FLXG_Val::int( $c, 'parent' ),
			),
			'authorId'   => array(
				'type'        => 'Int!',
				'description' => __( 'Author.', 'flx-tasks' ),
				'resolve'     => static fn( array $c ): int => FLXG_Val::int( $c, 'user' ),
			),
			'body'       => array(
				'type'        => 'String!',
				'description' => __( 'Plain text; empty when deleted.', 'flx-tasks' ),
				'resolve'     => static fn( array $c ): string => FLXG_Val::str( $c, 'body' ),
			),
			'anchor'     => array(
				'type'        => 'FlxTaskDocAnchor',
				'description' => __( 'What the thread points at; empty for replies.', 'flx-tasks' ),
				'resolve'     => static fn( array $c ): ?array => is_array( $c['anchor'] ?? null ) ? $c['anchor'] : null,
			),
			'createdAt'  => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'Written at.', 'flx-tasks' ),
				'resolve'     => static fn( array $c ): ?array => flxg_gql_time( FLXG_Val::nstr( $c, 'created' ) ),
			),
			'editedAt'   => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'Last edited at.', 'flx-tasks' ),
				'resolve'     => static fn( array $c ): ?array => flxg_gql_time( FLXG_Val::nstr( $c, 'edited' ) ),
			),
			'resolvedBy' => array(
				'type'        => 'Int',
				'description' => __( 'Who resolved the thread; empty while open.', 'flx-tasks' ),
				'resolve'     => static fn( array $c ): ?int => is_array( $c['resolved'] ?? null ) ? FLXG_Val::int( $c['resolved'], 'by' ) : null,
			),
			'deleted'    => array(
				'type'        => 'Boolean!',
				'description' => __( 'A deleted thread kept for its replies.', 'flx-tasks' ),
				'resolve'     => static fn( array $c ): bool => ! empty( $c['deleted'] ),
			),
			'canEdit'    => array(
				'type'        => 'Boolean!',
				'description' => __( 'The viewer can edit the text.', 'flx-tasks' ),
				'resolve'     => static fn( array $c ): bool => ! empty( $c['canEdit'] ),
			),
			'canDelete'  => array(
				'type'        => 'Boolean!',
				'description' => __( 'The viewer can delete it.', 'flx-tasks' ),
				'resolve'     => static fn( array $c ): bool => ! empty( $c['canDelete'] ),
			),
			'canResolve' => array(
				'type'        => 'Boolean!',
				'description' => __( 'The viewer can resolve or reopen the thread.', 'flx-tasks' ),
				'resolve'     => static fn( array $c ): bool => ! empty( $c['canResolve'] ),
			),
		),
	)
);

flx_gql_type(
	'FlxTaskDocPageProtection',
	array(
		'description' => __( 'Who can open a document page and whether it is locked.', 'flx-tasks' ),
		'fields'      => array(
			'pageId'       => array(
				'type'        => 'Int!',
				'description' => __( 'Document page.', 'flx-tasks' ),
				'resolve'     => static fn( array $p ): int => FLXG_Val::int( $p, 'id' ),
			),
			'role'         => array(
				'type'        => 'String!',
				'description' => __( 'The viewer\'s role on the page: view, comment, edit or admin.', 'flx-tasks' ),
				'resolve'     => static fn( array $p ): string => FLXG_Val::str( $p, 'role' ),
			),
			'restricted'   => array(
				'type'        => 'Boolean!',
				'description' => __( 'Only the listed people (and project admins) can open the page.', 'flx-tasks' ),
				'resolve'     => static fn( array $p ): bool => ! empty( $p['restricted'] ),
			),
			'lockedBy'     => array(
				'type'        => 'Int',
				'description' => __( 'Who locked the page; empty when it is not locked.', 'flx-tasks' ),
				'resolve'     => static fn( array $p ): ?int => is_array( $p['locked'] ?? null ) ? FLXG_Val::int( $p['locked'], 'by' ) : null,
			),
			'commentCount' => array(
				'type'        => 'Int!',
				'description' => __( 'Open comment threads.', 'flx-tasks' ),
				'resolve'     => static fn( array $p ): int => FLXG_Val::int( $p, 'open' ),
			),
		),
	)
);

/**
 * Page protection fields for the viewer (null without access).
 *
 * @return array<string,mixed>|null
 */
function flxg_gql_doc_page( int $page_id ): ?array {
	$uid = get_current_user_id();
	$p   = FLXG_Doc_Service::page( $page_id );
	if ( ! $p || ! FLXG_Doc_Service::can( $page_id, 'view', $uid ) ) {
		return null;
	}
	$open = 0;
	$all  = FLXG_Doc_Service::comments( $page_id, $uid );
	if ( is_array( $all ) ) {
		foreach ( $all['comments'] as $c ) {
			$open += ! $c['parent'] && ! $c['resolved'] && ! $c['deleted'] ? 1 : 0;
		}
	}
	return array( 'id' => $page_id, 'open' => $open ) + FLXG_Doc_Service::state( $p, $uid );
}

flx_gql_query(
	'flxTaskDocComments',
	array(
		'type'        => '[FlxTaskDocComment!]!',
		'description' => __( 'Comments of a document page (threads, then replies, oldest first).', 'flx-tasks' ),
		'cap'         => 'flxg_use',
		'args'        => array(
			'pageId' => array(
				'type'        => 'Int!',
				'description' => __( 'Document page.', 'flx-tasks' ),
			),
		),
		'resolve'     => static fn( $root, array $args ): array => flxg_gql_ok( FLXG_Doc_Service::comments( flx_gql_int( $args['pageId'] ?? 0 ), get_current_user_id() ) )['comments'],
	)
);

flx_gql_query(
	'flxTaskDocPage',
	array(
		'type'        => 'FlxTaskDocPageProtection',
		'description' => __( 'Lock and access state of a document page; empty without access.', 'flx-tasks' ),
		'cap'         => 'flxg_use',
		'args'        => array(
			'pageId' => array(
				'type'        => 'Int!',
				'description' => __( 'Document page.', 'flx-tasks' ),
			),
		),
		'resolve'     => static fn( $root, array $args ): ?array => flxg_gql_doc_page( flx_gql_int( $args['pageId'] ?? 0 ) ),
	)
);

flx_gql_mutation(
	'flxTaskDocCommentCreate',
	array(
		'description' => __( 'Starts a comment thread on a text range or block of a document page, or replies to a thread.', 'flx-tasks' ),
		'cap'         => 'flxg_use',
		'input'       => array(
			'pageId'     => array(
				'type'        => 'Int!',
				'description' => __( 'Document page.', 'flx-tasks' ),
			),
			'body'       => array(
				'type'        => 'String!',
				'description' => __( 'Comment text.', 'flx-tasks' ),
			),
			'parentId'   => array(
				'type'        => 'Int',
				'description' => __( 'Thread to reply to.', 'flx-tasks' ),
			),
			'blockId'    => array(
				'type'        => 'String',
				'description' => __( 'New thread: block where the range starts.', 'flx-tasks' ),
			),
			'offset'     => array(
				'type'        => 'Int',
				'description' => __( 'New thread: start offset.', 'flx-tasks' ),
			),
			'endBlockId' => array(
				'type'        => 'String',
				'description' => __( 'New thread: block where the range ends.', 'flx-tasks' ),
			),
			'endOffset'  => array(
				'type'        => 'Int',
				'description' => __( 'New thread: end offset.', 'flx-tasks' ),
			),
			'quote'      => array(
				'type'        => 'String',
				'description' => __( 'New thread: the selected text.', 'flx-tasks' ),
			),
			'kind'       => array(
				'type'        => 'String',
				'description' => __( 'New thread: text or block.', 'flx-tasks' ),
			),
			'label'      => array(
				'type'        => 'String',
				'description' => __( 'New thread: block label (file name).', 'flx-tasks' ),
			),
		),
		'output'      => array(
			'comment' => array(
				'type'        => 'FlxTaskDocComment',
				'description' => __( 'The new comment.', 'flx-tasks' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			$anchor = array(
				'b'     => $input['blockId'] ?? '',
				'o'     => $input['offset'] ?? 0,
				'eb'    => $input['endBlockId'] ?? '',
				'eo'    => $input['endOffset'] ?? 0,
				'q'     => $input['quote'] ?? '',
				'kind'  => $input['kind'] ?? 'text',
				'label' => $input['label'] ?? '',
			);
			return array( 'comment' => flxg_gql_ok( FLXG_Doc_Service::create( flx_gql_int( $input['pageId'] ?? 0 ), flx_gql_int( $input['parentId'] ?? 0 ), $anchor, flx_gql_str( $input['body'] ?? '' ), get_current_user_id() ) ) );
		},
	)
);

flx_gql_mutation(
	'flxTaskDocCommentUpdate',
	array(
		'description' => __( 'Edits one\'s own document comment, or resolves / reopens a thread.', 'flx-tasks' ),
		'cap'         => 'flxg_use',
		'input'       => array(
			'id'       => array(
				'type'        => 'Int!',
				'description' => __( 'Comment ID.', 'flx-tasks' ),
			),
			'body'     => array(
				'type'        => 'String',
				'description' => __( 'New text.', 'flx-tasks' ),
			),
			'resolved' => array(
				'type'        => 'Boolean',
				'description' => __( 'Resolve (true) or reopen (false) the thread.', 'flx-tasks' ),
			),
		),
		'output'      => array(
			'comment' => array(
				'type'        => 'FlxTaskDocComment',
				'description' => __( 'The comment.', 'flx-tasks' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			$id  = flx_gql_int( $input['id'] ?? 0 );
			$uid = get_current_user_id();
			$c   = null;
			if ( isset( $input['body'] ) ) {
				$c = flxg_gql_ok( FLXG_Doc_Service::update( $id, flx_gql_str( $input['body'] ), $uid ) );
			}
			if ( isset( $input['resolved'] ) ) {
				$c = flxg_gql_ok( FLXG_Doc_Service::resolve( $id, (bool) $input['resolved'], $uid ) );
			}
			return array( 'comment' => $c );
		},
	)
);

flx_gql_mutation(
	'flxTaskDocCommentDelete',
	array(
		'description' => __( 'Deletes a document comment (the author or a project admin).', 'flx-tasks' ),
		'cap'         => 'flxg_use',
		'input'       => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Comment ID.', 'flx-tasks' ),
			),
		),
		'output'      => array(
			'deletedId' => array(
				'type'        => 'Int',
				'description' => __( 'The deleted comment.', 'flx-tasks' ),
			),
		),
		'resolve'     => static fn( array $input ): array => array( 'deletedId' => flxg_gql_ok( FLXG_Doc_Service::delete( flx_gql_int( $input['id'] ?? 0 ), get_current_user_id() ) )['id'] ),
	)
);

flx_gql_mutation(
	'flxTaskDocPageLock',
	array(
		'description' => __( 'Locks a document page against edits, or unlocks it (people who can edit the page).', 'flx-tasks' ),
		'cap'         => 'flxg_use',
		'input'       => array(
			'pageId' => array(
				'type'        => 'Int!',
				'description' => __( 'Document page.', 'flx-tasks' ),
			),
			'locked' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Lock (true) or unlock (false).', 'flx-tasks' ),
			),
		),
		'output'      => array(
			'page' => array(
				'type'        => 'FlxTaskDocPageProtection',
				'description' => __( 'The page after the change.', 'flx-tasks' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			$id = flx_gql_int( $input['pageId'] ?? 0 );
			flxg_gql_ok( FLXG_Doc_Service::lock( $id, ! empty( $input['locked'] ), get_current_user_id() ) );
			return array( 'page' => flxg_gql_doc_page( $id ) );
		},
	)
);
