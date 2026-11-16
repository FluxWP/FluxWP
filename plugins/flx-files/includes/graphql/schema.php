<?php
/**
 * Flux Files GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a GraphQL
 * request. Resolvers call FLXFM_Files_Service; the Flux Files screen (wp-admin and the FluxWP app) reads
 * and writes only through these fields. Every root field needs `flxfm_manage` (administrators only).
 *
 * Not in GraphQL: the chunked file upload (`flx-files/v1/upload`, resumable multipart, see
 * docs/graphql/ISTISNALAR.md) and the download/preview stream (`admin-post.php?action=flxfm_file`).
 *
 * @package FLX_Files
 */

defined( 'ABSPATH' ) || exit;

/**
 * A list field of a payload array.
 *
 * @param mixed  $p   Payload.
 * @param string $key Field.
 * @return array<mixed>
 */
function flxfm_gql_list( $p, string $key ): array {
	$v = flx_gql_arr( $p )[ $key ] ?? array();
	return is_array( $v ) ? $v : array();
}

/**
 * Service result → value, or the WP_Error as a typed GraphQL error. Too-big / unsupported-file errors
 * (413, 415, 507) are input problems the form shows, not server failures.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxfm_gql_ok( $r ) {
	if ( $r instanceof WP_Error ) {
		$data = $r->get_error_data();
		if ( is_array( $data ) && in_array( flx_gql_int( $data['status'] ?? 0 ), array( 413, 415, 501, 507 ), true ) ) {
			$r = new WP_Error( (string) $r->get_error_code(), $r->get_error_message(), array_merge( $data, array( 'graphql_code' => 'INVALID_INPUT' ) ) );
		}
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
function flxfm_gql_src( $source, string $class ) {
	if ( ! $source instanceof $class ) {
		throw flx_gql_error( 'INTERNAL' );
	}
	return $source;
}

/**
 * Resolver reading one property of the typed parent.
 *
 * @param class-string $class Parent class.
 * @param string       $prop  Property name.
 */
function flxfm_gql_prop( string $class, string $prop ): Closure {
	return static function ( $source ) use ( $class, $prop ) {
		return flxfm_gql_src( $source, $class )->{$prop};
	};
}

/**
 * A field that reads a property.
 *
 * @param class-string $class Parent class.
 * @param string       $prop  Property.
 * @param string       $type  Type notation.
 * @param string       $desc  Description.
 * @return array<string,mixed>
 */
function flxfm_gql_f( string $class, string $prop, string $type, string $desc ): array {
	return array(
		'type'        => $type,
		'description' => $desc,
		'resolve'     => flxfm_gql_prop( $class, $prop ),
	);
}

/**
 * FlxDateTime of a Unix time.
 *
 * @param int $time Unix time.
 * @return array<string,mixed>|null
 */
function flxfm_gql_time( int $time ): ?array {
	return flx_gql_datetime( $time );
}

/**
 * Failures list of a batch.
 *
 * @param list<FLXFM_Failure> $failed Failures.
 * @return list<FLXFM_Failure>
 */
function flxfm_gql_failed( array $failed ): array {
	return $failed;
}

/**
 * Cleaned list of paths from a client.
 *
 * @param mixed $v Input list.
 * @return list<string>
 */
function flxfm_gql_paths( $v ): array {
	return FLXFM_Files_Service::paths( $v );
}

/**
 * Tags the read for the cache; the service purges on every change (flxfm_changed).
 */
function flxfm_gql_tag(): void {
	flx_gql_cache_tag( FLXFM_Files_Service::CACHE_TAG );
}

/* ------------------------------------------------------------------ enums */

flx_gql_enum(
	'FlxFilesKind',
	array(
		'description' => __( 'What a file or folder is, for its icon and preview.', 'flx-files' ),
		'values'      => array(
			'DIR'     => array( 'value' => 'dir', 'description' => __( 'Folder.', 'flx-files' ) ),
			'IMAGE'   => array( 'value' => 'image', 'description' => __( 'Image.', 'flx-files' ) ),
			'VIDEO'   => array( 'value' => 'video', 'description' => __( 'Video.', 'flx-files' ) ),
			'AUDIO'   => array( 'value' => 'audio', 'description' => __( 'Audio.', 'flx-files' ) ),
			'PDF'     => array( 'value' => 'pdf', 'description' => __( 'PDF document.', 'flx-files' ) ),
			'ARCHIVE' => array( 'value' => 'archive', 'description' => __( 'Archive (ZIP, TAR, …).', 'flx-files' ) ),
			'CODE'    => array( 'value' => 'code', 'description' => __( 'Source code or data file.', 'flx-files' ) ),
			'DOC'     => array( 'value' => 'doc', 'description' => __( 'Office document or spreadsheet.', 'flx-files' ) ),
			'TEXT'    => array( 'value' => 'text', 'description' => __( 'Plain text.', 'flx-files' ) ),
			'FONT'    => array( 'value' => 'font', 'description' => __( 'Font.', 'flx-files' ) ),
			'FILE'    => array( 'value' => 'file', 'description' => __( 'Any other file.', 'flx-files' ) ),
		),
	)
);

flx_gql_enum(
	'FlxFilesSort',
	array(
		'description' => __( 'What a folder listing is sorted by (folders always come first).', 'flx-files' ),
		'values'      => array(
			'NAME'     => array( 'value' => 'name', 'description' => __( 'Name.', 'flx-files' ) ),
			'MODIFIED' => array( 'value' => 'modified', 'description' => __( 'Last modified.', 'flx-files' ) ),
			'SIZE'     => array( 'value' => 'size', 'description' => __( 'Size.', 'flx-files' ) ),
			'TYPE'     => array( 'value' => 'type', 'description' => __( 'File extension.', 'flx-files' ) ),
		),
	)
);

flx_gql_enum(
	'FlxFilesOrder',
	array(
		'description' => __( 'Sort direction.', 'flx-files' ),
		'values'      => array(
			'ASC'  => array( 'value' => 'asc', 'description' => __( 'Ascending.', 'flx-files' ) ),
			'DESC' => array( 'value' => 'desc', 'description' => __( 'Descending.', 'flx-files' ) ),
		),
	)
);

flx_gql_enum(
	'FlxFilesVia',
	array(
		'description' => __( 'Where a change came from.', 'flx-files' ),
		'values'      => array(
			'PANEL' => array( 'value' => 'panel', 'description' => __( 'The Flux Files screen.', 'flx-files' ) ),
			'AI'    => array( 'value' => 'ai', 'description' => __( 'The Flux AI chat.', 'flx-files' ) ),
			'MCP'   => array( 'value' => 'mcp', 'description' => __( 'An external MCP client.', 'flx-files' ) ),
			'FRONT' => array( 'value' => 'front', 'description' => __( 'The site (shared folder shortcode).', 'flx-files' ) ),
		),
	)
);

flx_gql_enum(
	'FlxFilesLogAction',
	array(
		'description' => __( 'What an Activity row records.', 'flx-files' ),
		'values'      => array(
			'CREATE'   => array( 'value' => 'create', 'description' => __( 'A file was created.', 'flx-files' ) ),
			'EDIT'     => array( 'value' => 'edit', 'description' => __( 'A file was changed.', 'flx-files' ) ),
			'MKDIR'    => array( 'value' => 'mkdir', 'description' => __( 'A folder was created.', 'flx-files' ) ),
			'RENAME'   => array( 'value' => 'rename', 'description' => __( 'Renamed.', 'flx-files' ) ),
			'MOVE'     => array( 'value' => 'move', 'description' => __( 'Moved.', 'flx-files' ) ),
			'COPY'     => array( 'value' => 'copy', 'description' => __( 'Copied.', 'flx-files' ) ),
			'TRASH'    => array( 'value' => 'trash', 'description' => __( 'Moved to the trash.', 'flx-files' ) ),
			'RESTORE'  => array( 'value' => 'restore', 'description' => __( 'Restored from the trash.', 'flx-files' ) ),
			'PURGE'    => array( 'value' => 'purge', 'description' => __( 'Deleted for good.', 'flx-files' ) ),
			'CHMOD'    => array( 'value' => 'chmod', 'description' => __( 'Permissions changed.', 'flx-files' ) ),
			'ZIP'      => array( 'value' => 'zip', 'description' => __( 'Archived.', 'flx-files' ) ),
			'UNZIP'    => array( 'value' => 'unzip', 'description' => __( 'Extracted.', 'flx-files' ) ),
			'UPLOAD'   => array( 'value' => 'upload', 'description' => __( 'Uploaded.', 'flx-files' ) ),
			'REVERT'   => array( 'value' => 'revert', 'description' => __( 'An earlier version was restored.', 'flx-files' ) ),
			'SETTINGS' => array( 'value' => 'settings', 'description' => __( 'Settings changed.', 'flx-files' ) ),
			'SHARE'    => array( 'value' => 'share', 'description' => __( 'A folder was shared on the site.', 'flx-files' ) ),
			'UNSHARE'  => array( 'value' => 'unshare', 'description' => __( 'Sharing stopped.', 'flx-files' ) ),
		),
	)
);

/* ------------------------------------------------------------------ types */

flx_gql_type(
	'FlxFilesMatch',
	array(
		'description' => __( 'A line of a file that holds the searched words.', 'flx-files' ),
		'fields'      => array(
			'line' => flxfm_gql_f( FLXFM_Match::class, 'line', 'Int!', __( 'Line number (1-based).', 'flx-files' ) ),
			'text' => flxfm_gql_f( FLXFM_Match::class, 'text', 'String!', __( 'The line, trimmed to 200 characters.', 'flx-files' ) ),
		),
	)
);

flx_gql_type(
	'FlxFilesItem',
	array(
		'description' => __( 'A file or a folder of the site.', 'flx-files' ),
		'fields'      => array(
			'name'        => flxfm_gql_f( FLXFM_Item::class, 'name', 'String!', __( 'File or folder name.', 'flx-files' ) ),
			'path'        => flxfm_gql_f( FLXFM_Item::class, 'path', 'String!', __( 'Path from the site folder; empty for the site folder itself.', 'flx-files' ) ),
			'isDir'       => flxfm_gql_f( FLXFM_Item::class, 'is_dir', 'Boolean!', __( 'It is a folder.', 'flx-files' ) ),
			'kind'        => flxfm_gql_f( FLXFM_Item::class, 'kind', 'FlxFilesKind!', __( 'What it is, for the icon and preview.', 'flx-files' ) ),
			'extension'   => flxfm_gql_f( FLXFM_Item::class, 'ext', 'String!', __( 'Lower case extension without the dot; empty for folders and files without one.', 'flx-files' ) ),
			'size'        => flxfm_gql_f( FLXFM_Item::class, 'size', 'Float', __( 'Size in bytes. Empty for folders (use flxFilesSize).', 'flx-files' ) ),
			'modified'    => array(
				'type'        => 'FlxDateTime!',
				'description' => __( 'When it was last changed.', 'flx-files' ),
				'resolve'     => static fn( $i ): ?array => flxfm_gql_time( flxfm_gql_src( $i, FLXFM_Item::class )->mtime ),
			),
			'permissions' => flxfm_gql_f( FLXFM_Item::class, 'perms', 'String!', __( 'Octal permissions, e.g. 0644.', 'flx-files' ) ),
			'writable'    => flxfm_gql_f( FLXFM_Item::class, 'writable', 'Boolean!', __( 'The server can write it.', 'flx-files' ) ),
			'isLink'      => flxfm_gql_f( FLXFM_Item::class, 'link', 'Boolean!', __( 'It is a symbolic link.', 'flx-files' ) ),
			'locked'      => flxfm_gql_f( FLXFM_Item::class, 'locked', 'Boolean!', __( 'Protected: it cannot be moved, renamed or deleted.', 'flx-files' ) ),
			'sensitive'   => flxfm_gql_f( FLXFM_Item::class, 'sensitive', 'Boolean!', __( 'It holds passwords or keys and is never shared with AI.', 'flx-files' ) ),
			'core'        => flxfm_gql_f( FLXFM_Item::class, 'core', 'Boolean!', __( 'It is a WordPress core file.', 'flx-files' ) ),
			'shared'      => flxfm_gql_f( FLXFM_Item::class, 'shared', 'Boolean!', __( 'The folder is shared on the site with the [flx_files] shortcode.', 'flx-files' ) ),
			'itemCount'   => flxfm_gql_f( FLXFM_Item::class, 'count', 'Int', __( 'Entries inside a folder of a listing. Empty when not asked for or unreadable.', 'flx-files' ) ),
			'matches'     => flxfm_gql_f( FLXFM_Item::class, 'matches', '[FlxFilesMatch!]!', __( 'Lines that matched a content search.', 'flx-files' ) ),
		),
	)
);
flx_gql_connection( 'FlxFilesItem', __( 'A page of the entries of a folder.', 'flx-files' ) );

flx_gql_type(
	'FlxFilesFolder',
	array(
		'description' => __( 'A folder and where "up" leads. Its entries come page by page.', 'flx-files' ),
		'fields'      => array(
			'path'   => flxfm_gql_f( FLXFM_Folder::class, 'path', 'String!', __( 'Path from the site folder.', 'flx-files' ) ),
			'dir'    => flxfm_gql_f( FLXFM_Folder::class, 'dir', 'FlxFilesItem!', __( 'The folder itself.', 'flx-files' ) ),
			'parent' => flxfm_gql_f( FLXFM_Folder::class, 'parent', 'String', __( 'Path of the parent folder. Empty for the site folder.', 'flx-files' ) ),
			'items'  => array(
				'type'        => 'FlxFilesItemConnection!',
				'description' => __( 'Entries of the folder, folders first.', 'flx-files' ),
				'args'        => flx_gql_connection_args(
					array(
						'sort'       => array( 'type' => 'FlxFilesSort', 'description' => __( 'Sort key (default NAME).', 'flx-files' ) ),
						'order'      => array( 'type' => 'FlxFilesOrder', 'description' => __( 'Sort direction (default ASC).', 'flx-files' ) ),
						'hidden'     => array( 'type' => 'Boolean', 'description' => __( 'Show files that start with a dot (default: the setting).', 'flx-files' ) ),
						'foldersOnly' => array( 'type' => 'Boolean', 'description' => __( 'Only folders (the folder picker and the tree).', 'flx-files' ) ),
						'countItems' => array( 'type' => 'Boolean', 'description' => __( 'Count the entries inside each folder of the page.', 'flx-files' ) ),
					)
				),
				'resolve'     => static function ( $src, array $args ): array {
					$f = flxfm_gql_src( $src, FLXFM_Folder::class );
					flxfm_gql_tag();
					$hidden = array_key_exists( 'hidden', $args ) && null !== $args['hidden'] ? (bool) $args['hidden'] : ! empty( flxfm_opt( 'show_hidden' ) );
					$sort   = flx_gql_str( $args['sort'] ?? '', 'name' );
					$desc   = 'desc' === flx_gql_str( $args['order'] ?? '', 'asc' );
					$dirs   = ! empty( $args['foldersOnly'] );
					$count  = ! empty( $args['countItems'] );
					return flx_gql_paginate(
						flx_gql_arr( $args ),
						static function ( int $offset, int $limit ) use ( $f, $hidden, $sort, $desc, $dirs, $count ): array {
							$page = flxfm_gql_ok( FLXFM_Files_Service::items_page( $f->path, $hidden, $sort, $desc, $dirs, $count, $offset, $limit ) );
							return array( 'items' => $page->items, 'total' => $page->total );
						}
					);
				},
			),
		),
	)
);

flx_gql_type(
	'FlxFilesFile',
	array(
		'description' => __( 'A text file with its content.', 'flx-files' ),
		'fields'      => array(
			'item'    => flxfm_gql_f( FLXFM_File::class, 'item', 'FlxFilesItem!', __( 'The file.', 'flx-files' ) ),
			'hash'    => flxfm_gql_f( FLXFM_File::class, 'hash', 'String!', __( 'Fingerprint of the content. Send it back when saving so a change made on the server in between is noticed.', 'flx-files' ) ),
			'content' => flxfm_gql_f( FLXFM_File::class, 'content', 'String', __( 'The text. Empty in the response of a save.', 'flx-files' ) ),
		),
	)
);

flx_gql_type(
	'FlxFilesFailure',
	array(
		'description' => __( 'An entry an action on several entries could not process.', 'flx-files' ),
		'fields'      => array(
			'path'    => flxfm_gql_f( FLXFM_Failure::class, 'path', 'String!', __( 'The path (or trash id) it was given as.', 'flx-files' ) ),
			'message' => flxfm_gql_f( FLXFM_Failure::class, 'message', 'String!', __( 'Why, in the user\'s language.', 'flx-files' ) ),
		),
	)
);

flx_gql_type(
	'FlxFilesTrashed',
	array(
		'description' => __( 'An entry that went to the trash.', 'flx-files' ),
		'fields'      => array(
			'trashId' => flxfm_gql_f( FLXFM_Trashed::class, 'trash_id', 'String!', __( 'Id in the trash.', 'flx-files' ) ),
			'path'    => flxfm_gql_f( FLXFM_Trashed::class, 'path', 'String!', __( 'Where it was.', 'flx-files' ) ),
		),
	)
);

flx_gql_type(
	'FlxFilesSize',
	array(
		'description' => __( 'The size of a file or of everything inside a folder.', 'flx-files' ),
		'fields'      => array(
			'bytes'   => flxfm_gql_f( FLXFM_Size::class, 'size', 'Float!', __( 'Total bytes.', 'flx-files' ) ),
			'files'   => flxfm_gql_f( FLXFM_Size::class, 'files', 'Int!', __( 'Files counted.', 'flx-files' ) ),
			'folders' => flxfm_gql_f( FLXFM_Size::class, 'dirs', 'Int!', __( 'Folders counted.', 'flx-files' ) ),
			'partial' => flxfm_gql_f( FLXFM_Size::class, 'partial', 'Boolean!', __( 'Counting stopped early because the folder is very large.', 'flx-files' ) ),
		),
	)
);

flx_gql_type(
	'FlxFilesSearchResult',
	array(
		'description' => __( 'Entries whose name (or text) holds the searched words.', 'flx-files' ),
		'fields'      => array(
			'results' => flxfm_gql_f( FLXFM_Search::class, 'results', '[FlxFilesItem!]!', __( 'Hits, at most 200.', 'flx-files' ) ),
			'partial' => flxfm_gql_f( FLXFM_Search::class, 'partial', 'Boolean!', __( 'More hits exist; search in a smaller folder.', 'flx-files' ) ),
			'query'   => flxfm_gql_f( FLXFM_Search::class, 'query', 'String!', __( 'The words searched.', 'flx-files' ) ),
		),
	)
);

flx_gql_type(
	'FlxFilesDisk',
	array(
		'description' => __( 'Disk space the hosting account uses.', 'flx-files' ),
		'fields'      => array(
			'usedBytes'  => flxfm_gql_f( FLXFM_Disk::class, 'used', 'Float', __( 'Bytes used. Empty while the first measurement runs.', 'flx-files' ) ),
			'totalBytes' => flxfm_gql_f( FLXFM_Disk::class, 'total', 'Float', __( 'Disk quota in bytes. Empty when no quota is set in the settings.', 'flx-files' ) ),
			'scanning'   => flxfm_gql_f( FLXFM_Disk::class, 'scanning', 'Boolean!', __( 'A measurement is in progress; ask again in a few seconds.', 'flx-files' ) ),
			'fileCount'  => flxfm_gql_f( FLXFM_Disk::class, 'files', 'Int', __( 'Files counted by the last measurement.', 'flx-files' ) ),
			'measured'   => flxfm_gql_f( FLXFM_Disk::class, 'ago', 'String!', __( 'When it was measured, in words ("2 hours ago"). Empty before the first measurement ends.', 'flx-files' ) ),
		),
	)
);

flx_gql_type(
	'FlxFilesVersion',
	array(
		'description' => __( 'An earlier version of a file, kept when it was overwritten.', 'flx-files' ),
		'fields'      => array(
			'id'    => flxfm_gql_f( FLXFM_Version::class, 'id', 'String!', __( 'Version id.', 'flx-files' ) ),
			'saved' => array(
				'type'        => 'FlxDateTime!',
				'description' => __( 'When this version was replaced by a newer one.', 'flx-files' ),
				'resolve'     => static fn( $v ): ?array => flxfm_gql_time( flxfm_gql_src( $v, FLXFM_Version::class )->time ),
			),
			'user'  => flxfm_gql_f( FLXFM_Version::class, 'user', 'String!', __( 'Who made the change.', 'flx-files' ) ),
			'via'   => flxfm_gql_f( FLXFM_Version::class, 'via', 'FlxFilesVia!', __( 'Where the change came from.', 'flx-files' ) ),
			'size'  => flxfm_gql_f( FLXFM_Version::class, 'size', 'Int!', __( 'Bytes.', 'flx-files' ) ),
		),
	)
);

flx_gql_type(
	'FlxFilesTrashItem',
	array(
		'description' => __( 'An entry in the trash.', 'flx-files' ),
		'fields'      => array(
			'id'      => flxfm_gql_f( FLXFM_Trash_Item::class, 'id', 'String!', __( 'Trash id.', 'flx-files' ) ),
			'name'    => flxfm_gql_f( FLXFM_Trash_Item::class, 'name', 'String!', __( 'Name.', 'flx-files' ) ),
			'path'    => flxfm_gql_f( FLXFM_Trash_Item::class, 'path', 'String!', __( 'Where it was.', 'flx-files' ) ),
			'isDir'   => flxfm_gql_f( FLXFM_Trash_Item::class, 'is_dir', 'Boolean!', __( 'It is a folder.', 'flx-files' ) ),
			'kind'    => flxfm_gql_f( FLXFM_Trash_Item::class, 'kind', 'FlxFilesKind!', __( 'What it is.', 'flx-files' ) ),
			'size'    => flxfm_gql_f( FLXFM_Trash_Item::class, 'size', 'Float', __( 'Bytes (files).', 'flx-files' ) ),
			'deleted' => array(
				'type'        => 'FlxDateTime!',
				'description' => __( 'When it was deleted.', 'flx-files' ),
				'resolve'     => static fn( $i ): ?array => flxfm_gql_time( flxfm_gql_src( $i, FLXFM_Trash_Item::class )->time ),
			),
			'user'    => flxfm_gql_f( FLXFM_Trash_Item::class, 'user', 'String!', __( 'Who deleted it.', 'flx-files' ) ),
			'via'     => flxfm_gql_f( FLXFM_Trash_Item::class, 'via', 'FlxFilesVia!', __( 'Where the deletion came from.', 'flx-files' ) ),
			'until'   => flxfm_gql_f( FLXFM_Trash_Item::class, 'until', 'String!', __( 'Date it is deleted for good. Empty when the trash keeps entries until it is emptied.', 'flx-files' ) ),
		),
	)
);
flx_gql_connection( 'FlxFilesTrashItem', __( 'A page of the entries in the trash.', 'flx-files' ) );

flx_gql_type(
	'FlxFilesTrash',
	array(
		'description' => __( 'The trash: how long it keeps entries and what is in it.', 'flx-files' ),
		'fields'      => array(
			'days'  => flxfm_gql_f( FLXFM_Trash::class, 'days', 'Int!', __( 'Days an entry is kept. 0 keeps entries until the trash is emptied.', 'flx-files' ) ),
			'items' => array(
				'type'        => 'FlxFilesTrashItemConnection!',
				'description' => __( 'Entries, newest first.', 'flx-files' ),
				'args'        => flx_gql_connection_args(),
				'resolve'     => static function ( $src, array $args ): array {
					$t = flxfm_gql_src( $src, FLXFM_Trash::class );
					flxfm_gql_tag();
					return flx_gql_paginate(
						flx_gql_arr( $args ),
						static fn( int $offset, int $limit ): array => array(
							'items' => array_slice( $t->items, $offset, $limit ),
							'total' => count( $t->items ),
						)
					);
				},
			),
		),
	)
);

flx_gql_type(
	'FlxFilesLogEntry',
	array(
		'description' => __( 'One row of the Activity log.', 'flx-files' ),
		'fields'      => array(
			'when'   => array(
				'type'        => 'FlxDateTime!',
				'description' => __( 'When it happened.', 'flx-files' ),
				'resolve'     => static fn( $e ): ?array => flxfm_gql_time( flxfm_gql_src( $e, FLXFM_Log_Entry::class )->time ),
			),
			'userId' => flxfm_gql_f( FLXFM_Log_Entry::class, 'user_id', 'Int!', __( 'User ID. 0 when there is none.', 'flx-files' ) ),
			'user'   => flxfm_gql_f( FLXFM_Log_Entry::class, 'user', 'String!', __( 'Display name of the user.', 'flx-files' ) ),
			'via'    => flxfm_gql_f( FLXFM_Log_Entry::class, 'via', 'FlxFilesVia', __( 'Where the change came from. Empty for a source this version does not know.', 'flx-files' ) ),
			'action' => flxfm_gql_f( FLXFM_Log_Entry::class, 'action', 'FlxFilesLogAction', __( 'What happened. Empty for an action this version does not know.', 'flx-files' ) ),
			'path'   => flxfm_gql_f( FLXFM_Log_Entry::class, 'path', 'String!', __( 'The path the action was about (or a short text for a bulk purge).', 'flx-files' ) ),
			'to'     => flxfm_gql_f( FLXFM_Log_Entry::class, 'to', 'String!', __( 'The target of a move or copy, the new permissions, or the folder an archive was extracted to.', 'flx-files' ) ),
		),
	)
);
flx_gql_connection( 'FlxFilesLogEntry', __( 'A page of Activity rows.', 'flx-files' ) );

flx_gql_type(
	'FlxFilesSettings',
	array(
		'description' => __( 'Flux Files settings.', 'flx-files' ),
		'fields'      => array(
			'showHidden' => flxfm_gql_f( FLXFM_Settings::class, 'show_hidden', 'Boolean!', __( 'Show files that start with a dot, such as .htaccess.', 'flx-files' ) ),
			'trashDays'  => flxfm_gql_f( FLXFM_Settings::class, 'trash_days', 'Int!', __( 'Days the trash keeps entries (0–365). 0 keeps them until the trash is emptied.', 'flx-files' ) ),
			'versions'   => flxfm_gql_f( FLXFM_Settings::class, 'versions', 'Int!', __( 'Earlier versions kept per file (0–50). 0 turns versions off.', 'flx-files' ) ),
			'quotaGb'    => flxfm_gql_f( FLXFM_Settings::class, 'quota_gb', 'Float!', __( 'Disk space of the hosting plan in GB. 0 shows the usage only.', 'flx-files' ) ),
			'mcpWrite'   => flxfm_gql_f( FLXFM_Settings::class, 'mcp_write', 'Boolean!', __( 'External MCP clients may change files (otherwise they only read).', 'flx-files' ) ),
			'aiCode'     => flxfm_gql_f( FLXFM_Settings::class, 'ai_code', 'Boolean!', __( 'AI and MCP may change code files (.php, .js, .css, .htaccess …).', 'flx-files' ) ),
			'shared'     => flxfm_gql_f( FLXFM_Settings::class, 'shared', '[String!]!', __( 'Folders inside the uploads folder shared on the site with the [flx_files] shortcode.', 'flx-files' ) ),
		),
	)
);

flx_gql_type(
	'FlxFilesConfig',
	array(
		'description' => __( 'What the Flux Files screen needs to know about this site.', 'flx-files' ),
		'fields'      => array(
			'writable'    => flxfm_gql_f( FLXFM_Config::class, 'writable', 'Boolean!', __( 'File changes are allowed. False when the site sets DISALLOW_FILE_MODS: the screen is read-only.', 'flx-files' ) ),
			'aiReady'     => flxfm_gql_f( FLXFM_Config::class, 'ai_ready', 'Boolean!', __( 'Flux AI can explain and edit files.', 'flx-files' ) ),
			'rootName'    => flxfm_gql_f( FLXFM_Config::class, 'root_name', 'String!', __( 'Name of the site folder.', 'flx-files' ) ),
			'rootPath'    => flxfm_gql_f( FLXFM_Config::class, 'root_path', 'String!', __( 'Full server path of the site folder.', 'flx-files' ) ),
			'host'        => flxfm_gql_f( FLXFM_Config::class, 'host', 'String!', __( 'Site host name.', 'flx-files' ) ),
			'homeUrl'     => flxfm_gql_f( FLXFM_Config::class, 'home_url', 'String!', __( 'Site address with a trailing slash.', 'flx-files' ) ),
			'uploadsPath' => flxfm_gql_f( FLXFM_Config::class, 'uploads_path', 'String!', __( 'Uploads folder, from the site folder.', 'flx-files' ) ),
			'uploadsUrl'  => flxfm_gql_f( FLXFM_Config::class, 'uploads_url', 'String!', __( 'Uploads address, without a trailing slash.', 'flx-files' ) ),
			'chunkSize'   => flxfm_gql_f( FLXFM_Config::class, 'chunk_size', 'Int!', __( 'Size in bytes of one part of a chunked upload.', 'flx-files' ) ),
			'fileUrl'     => flxfm_gql_f( FLXFM_Config::class, 'file_url', 'String!', __( 'Download and preview address; add &path=… (and &inline=1 to show it in the browser).', 'flx-files' ) ),
		),
	)
);

/* ------------------------------------------------------------------ queries */

flx_gql_query(
	'flxFilesConfig',
	array(
		'description' => __( 'What the Flux Files screen needs to know about this site.', 'flx-files' ),
		'type'        => 'FlxFilesConfig!',
		'cap'         => 'flxfm_manage',
		'resolve'     => static fn(): FLXFM_Config => FLXFM_Files_Service::config(),
	)
);

flx_gql_query(
	'flxFilesSettings',
	array(
		'description' => __( 'Flux Files settings.', 'flx-files' ),
		'type'        => 'FlxFilesSettings!',
		'cap'         => 'flxfm_manage',
		'resolve'     => static function (): FLXFM_Settings {
			flxfm_gql_tag();
			return FLXFM_Files_Service::settings();
		},
	)
);

flx_gql_query(
	'flxFilesFolder',
	array(
		'description' => __( 'A folder of the site and, page by page, its entries.', 'flx-files' ),
		'type'        => 'FlxFilesFolder!',
		'cap'         => 'flxfm_manage',
		'args'        => array(
			'path' => array( 'type' => 'String', 'description' => __( 'Path from the site folder. Empty for the site folder itself.', 'flx-files' ) ),
		),
		'resolve'     => static function ( $root, array $args ): FLXFM_Folder {
			flxfm_gql_tag();
			return flxfm_gql_ok( FLXFM_Files_Service::folder( flx_gql_str( $args['path'] ?? '' ) ) );
		},
	)
);

flx_gql_query(
	'flxFilesFile',
	array(
		'description' => __( 'Opens a text file for editing. Large (over 3 MB) and binary files are refused.', 'flx-files' ),
		'type'        => 'FlxFilesFile!',
		'cap'         => 'flxfm_manage',
		'args'        => array(
			'path' => array( 'type' => 'String!', 'description' => __( 'Path from the site folder.', 'flx-files' ) ),
		),
		'resolve'     => static fn( $root, array $args ): FLXFM_File => flxfm_gql_ok( FLXFM_Files_Service::read( flx_gql_str( $args['path'] ?? '' ) ) ),
	)
);

flx_gql_query(
	'flxFilesSearch',
	array(
		'description' => __( 'Searches a folder (and everything below it) by name, and optionally inside text and code files.', 'flx-files' ),
		'type'        => 'FlxFilesSearchResult!',
		'cap'         => 'flxfm_manage',
		'args'        => array(
			'path'    => array( 'type' => 'String', 'description' => __( 'Folder to search in. Empty for the site folder.', 'flx-files' ) ),
			'query'   => array( 'type' => 'String!', 'description' => __( 'At least 2 characters.', 'flx-files' ) ),
			'content' => array( 'type' => 'Boolean', 'description' => __( 'Also search inside text and code files (up to 2 MB each).', 'flx-files' ) ),
		),
		'resolve'     => static fn( $root, array $args ): FLXFM_Search => flxfm_gql_ok( FLXFM_Files_Service::search( flx_gql_str( $args['path'] ?? '' ), flx_gql_str( $args['query'] ?? '' ), ! empty( $args['content'] ) ) ),
	)
);

flx_gql_query(
	'flxFilesSize',
	array(
		'description' => __( 'The size of a file or of everything inside a folder.', 'flx-files' ),
		'type'        => 'FlxFilesSize!',
		'cap'         => 'flxfm_manage',
		'args'        => array(
			'path' => array( 'type' => 'String!', 'description' => __( 'Path from the site folder.', 'flx-files' ) ),
		),
		'resolve'     => static fn( $root, array $args ): FLXFM_Size => flxfm_gql_ok( FLXFM_Files_Service::size( flx_gql_str( $args['path'] ?? '' ) ) ),
	)
);

flx_gql_query(
	'flxFilesDisk',
	array(
		'description' => __( 'Disk space the hosting account uses. Measuring runs in steps: while `scanning` is true, ask again in a few seconds.', 'flx-files' ),
		'type'        => 'FlxFilesDisk!',
		'cap'         => 'flxfm_manage',
		'args'        => array(
			'refresh' => array( 'type' => 'Boolean', 'description' => __( 'Start a new measurement.', 'flx-files' ) ),
		),
		'resolve'     => static fn( $root, array $args ): FLXFM_Disk => FLXFM_Files_Service::disk( ! empty( $args['refresh'] ) ),
	)
);

flx_gql_query(
	'flxFilesVersions',
	array(
		'description' => __( 'Earlier versions of a file, newest first (at most the number set in the settings).', 'flx-files' ),
		'type'        => '[FlxFilesVersion!]!',
		'cap'         => 'flxfm_manage',
		'args'        => array(
			'path' => array( 'type' => 'String!', 'description' => __( 'Path from the site folder.', 'flx-files' ) ),
		),
		'resolve'     => static fn( $root, array $args ): array => FLXFM_Files_Service::versions( flx_gql_str( $args['path'] ?? '' ) ),
	)
);

flx_gql_query(
	'flxFilesVersionContent',
	array(
		'description' => __( 'The text of an earlier version.', 'flx-files' ),
		'type'        => 'String!',
		'cap'         => 'flxfm_manage',
		'args'        => array(
			'path'      => array( 'type' => 'String!', 'description' => __( 'Path from the site folder.', 'flx-files' ) ),
			'versionId' => array( 'type' => 'String!', 'description' => __( 'Version id (flxFilesVersions).', 'flx-files' ) ),
		),
		'resolve'     => static fn( $root, array $args ): string => flxfm_gql_ok( FLXFM_Files_Service::version_content( flx_gql_str( $args['path'] ?? '' ), flx_gql_str( $args['versionId'] ?? '' ) ) ),
	)
);

flx_gql_query(
	'flxFilesTrash',
	array(
		'description' => __( 'The trash.', 'flx-files' ),
		'type'        => 'FlxFilesTrash!',
		'cap'         => 'flxfm_manage',
		'resolve'     => static function (): FLXFM_Trash {
			flxfm_gql_tag();
			return FLXFM_Files_Service::trash_list();
		},
	)
);

flx_gql_query(
	'flxFilesLog',
	array(
		'description' => __( 'The Activity log: who changed what, from where. Newest first.', 'flx-files' ),
		'type'        => 'FlxFilesLogEntryConnection!',
		'cap'         => 'flxfm_manage',
		'args'        => flx_gql_connection_args(
			array(
				'path' => array( 'type' => 'String', 'description' => __( 'Only rows about this path (or inside it).', 'flx-files' ) ),
				'via'  => array( 'type' => 'FlxFilesVia', 'description' => __( 'Only rows from this source.', 'flx-files' ) ),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			flxfm_gql_tag();
			$path = flx_gql_str( $args['path'] ?? '' );
			$via  = flx_gql_str( $args['via'] ?? '' );
			return flx_gql_paginate(
				flx_gql_arr( $args ),
				static function ( int $offset, int $limit ) use ( $path, $via ): array {
					$page = FLXFM_Files_Service::log( $path, $via, $offset, $limit );
					return array( 'items' => $page->rows, 'total' => $page->total );
				}
			);
		},
	)
);

/* ------------------------------------------------------------------ mutations */

flx_gql_mutation(
	'flxFilesWrite',
	array(
		'description' => __( 'Saves a text file (creates it when new). The previous content is kept as a version.', 'flx-files' ),
		'cap'         => 'flxfm_manage',
		'input'       => array(
			'path'       => array( 'type' => 'String!', 'description' => __( 'Path from the site folder.', 'flx-files' ) ),
			'content'    => array( 'type' => 'String!', 'description' => __( 'The new content.', 'flx-files' ) ),
			'hash'       => array( 'type' => 'String', 'description' => __( 'Fingerprint of the content that was opened (FlxFilesFile.hash). When the file changed on the server since, the save is refused (CONFLICT).', 'flx-files' ) ),
			'createOnly' => array( 'type' => 'Boolean', 'description' => __( 'Refuse when a file with this name already exists.', 'flx-files' ) ),
		),
		'output'      => array(
			'file' => array( 'type' => 'FlxFilesFile', 'description' => __( 'The saved file (no content).', 'flx-files' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXFM_Files_Service::write( flx_gql_str( $input['path'] ?? '' ), flx_gql_str( $input['content'] ?? '' ), flx_gql_str( $input['hash'] ?? '' ), ! empty( $input['createOnly'] ) );
			return $r instanceof WP_Error ? flxfm_gql_ok( $r ) : array( 'file' => $r );
		},
	)
);

flx_gql_mutation(
	'flxFilesFolderCreate',
	array(
		'description' => __( 'Creates a folder.', 'flx-files' ),
		'cap'         => 'flxfm_manage',
		'input'       => array(
			'path' => array( 'type' => 'String!', 'description' => __( 'Path of the new folder from the site folder.', 'flx-files' ) ),
		),
		'output'      => array(
			'item' => array( 'type' => 'FlxFilesItem', 'description' => __( 'The new folder.', 'flx-files' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXFM_Files_Service::mkdir( flx_gql_str( $input['path'] ?? '' ) );
			return $r instanceof WP_Error ? flxfm_gql_ok( $r ) : array( 'item' => $r );
		},
	)
);

flx_gql_mutation(
	'flxFilesRename',
	array(
		'description' => __( 'Renames a file or folder (it stays in its folder).', 'flx-files' ),
		'cap'         => 'flxfm_manage',
		'input'       => array(
			'path' => array( 'type' => 'String!', 'description' => __( 'Path from the site folder.', 'flx-files' ) ),
			'name' => array( 'type' => 'String!', 'description' => __( 'New name (no slashes).', 'flx-files' ) ),
		),
		'output'      => array(
			'item' => array( 'type' => 'FlxFilesItem', 'description' => __( 'The renamed entry.', 'flx-files' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXFM_Files_Service::rename( flx_gql_str( $input['path'] ?? '' ), flx_gql_str( $input['name'] ?? '' ) );
			return $r instanceof WP_Error ? flxfm_gql_ok( $r ) : array( 'item' => $r );
		},
	)
);

/**
 * Output fields of an action on several entries.
 *
 * @return array<string,array<string,mixed>>
 */
function flxfm_gql_batch_output(): array {
	return array(
		'items'  => array(
			'type'        => '[FlxFilesItem!]!',
			'description' => __( 'The entries that were processed, as they are now.', 'flx-files' ),
			'resolve'     => static fn( $p ): array => flxfm_gql_list( $p, 'items' ),
		),
		'failed' => array(
			'type'        => '[FlxFilesFailure!]!',
			'description' => __( 'Entries that could not be processed (the rest went through).', 'flx-files' ),
			'resolve'     => static fn( $p ): array => flxfm_gql_list( $p, 'failed' ),
		),
	);
}

foreach ( array( 'Move' => 'move', 'Copy' => 'copy' ) as $flxfm_label => $flxfm_op ) {
	flx_gql_mutation(
		'flxFiles' . $flxfm_label,
		array(
			'description' => 'move' === $flxfm_op
				? __( 'Moves entries into a folder.', 'flx-files' )
				: __( 'Copies entries into a folder.', 'flx-files' ),
			'cap'         => 'flxfm_manage',
			'input'       => array(
				'paths'       => array( 'type' => '[String!]!', 'description' => __( 'Entries to process (at most 1000).', 'flx-files' ) ),
				'destination' => array( 'type' => 'String!', 'description' => __( 'Target folder. Empty for the site folder.', 'flx-files' ) ),
				'overwrite'   => array( 'type' => 'Boolean', 'description' => __( 'Replace entries that already exist (the replaced ones go to the trash).', 'flx-files' ) ),
			),
			'output'      => flxfm_gql_batch_output(),
			'resolve'     => static function ( array $input ) use ( $flxfm_op ) {
				$r = FLXFM_Files_Service::transfer( $flxfm_op, flxfm_gql_paths( $input['paths'] ?? array() ), flx_gql_str( $input['destination'] ?? '' ), ! empty( $input['overwrite'] ) );
				$r = flxfm_gql_ok( $r );
				return array( 'items' => $r->done, 'failed' => $r->failed );
			},
		)
	);
}

flx_gql_mutation(
	'flxFilesDuplicate',
	array(
		'description' => __( 'Copies each entry next to itself ("name (2)").', 'flx-files' ),
		'cap'         => 'flxfm_manage',
		'input'       => array(
			'paths' => array( 'type' => '[String!]!', 'description' => __( 'Entries to duplicate (at most 1000).', 'flx-files' ) ),
		),
		'output'      => flxfm_gql_batch_output(),
		'resolve'     => static function ( array $input ) {
			$r = FLXFM_Files_Service::duplicate( flxfm_gql_paths( $input['paths'] ?? array() ) );
			return array( 'items' => $r->done, 'failed' => $r->failed );
		},
	)
);

flx_gql_mutation(
	'flxFilesTrash',
	array(
		'description' => __( 'Moves entries to the trash (nothing is deleted for good).', 'flx-files' ),
		'cap'         => 'flxfm_manage',
		'input'       => array(
			'paths' => array( 'type' => '[String!]!', 'description' => __( 'Entries to delete (at most 1000).', 'flx-files' ) ),
		),
		'output'      => array(
			'trashed' => array(
				'type'        => '[FlxFilesTrashed!]!',
				'description' => __( 'Entries that are in the trash now.', 'flx-files' ),
				'resolve'     => static fn( $p ): array => flxfm_gql_list( $p, 'trashed' ),
			),
			'failed'  => array(
				'type'        => '[FlxFilesFailure!]!',
				'description' => __( 'Entries that could not be deleted (the rest went through).', 'flx-files' ),
				'resolve'     => static fn( $p ): array => flxfm_gql_list( $p, 'failed' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXFM_Files_Service::trash( flxfm_gql_paths( $input['paths'] ?? array() ) );
			return array( 'trashed' => $r->done, 'failed' => $r->failed );
		},
	)
);

flx_gql_mutation(
	'flxFilesChmod',
	array(
		'description' => __( 'Changes the permissions of a file or folder. World-writable modes (777 …) are refused.', 'flx-files' ),
		'cap'         => 'flxfm_manage',
		'input'       => array(
			'path' => array( 'type' => 'String!', 'description' => __( 'Path from the site folder.', 'flx-files' ) ),
			'mode' => array( 'type' => 'String!', 'description' => __( 'Three octal digits, e.g. 644 or 755.', 'flx-files' ) ),
		),
		'output'      => array(
			'item' => array( 'type' => 'FlxFilesItem', 'description' => __( 'The entry with its new permissions.', 'flx-files' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXFM_Files_Service::chmod( flx_gql_str( $input['path'] ?? '' ), flx_gql_str( $input['mode'] ?? '' ) );
			return $r instanceof WP_Error ? flxfm_gql_ok( $r ) : array( 'item' => $r );
		},
	)
);

flx_gql_mutation(
	'flxFilesZip',
	array(
		'description' => __( 'Puts entries in a new ZIP file.', 'flx-files' ),
		'cap'         => 'flxfm_manage',
		'input'       => array(
			'paths' => array( 'type' => '[String!]!', 'description' => __( 'Entries to archive.', 'flx-files' ) ),
			'name'  => array( 'type' => 'String', 'description' => __( 'File name of the archive (".zip" is added). Empty: named after the entry.', 'flx-files' ) ),
			'folder' => array( 'type' => 'String', 'description' => __( 'Folder to create the archive in. Empty for the site folder.', 'flx-files' ) ),
		),
		'output'      => array(
			'item' => array( 'type' => 'FlxFilesItem', 'description' => __( 'The archive.', 'flx-files' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXFM_Files_Service::zip( flxfm_gql_paths( $input['paths'] ?? array() ), flx_gql_str( $input['name'] ?? '' ), flx_gql_str( $input['folder'] ?? '' ) );
			return $r instanceof WP_Error ? flxfm_gql_ok( $r ) : array( 'item' => $r );
		},
	)
);

flx_gql_mutation(
	'flxFilesUnzip',
	array(
		'description' => __( 'Extracts a ZIP file into a new folder next to it.', 'flx-files' ),
		'cap'         => 'flxfm_manage',
		'input'       => array(
			'path'        => array( 'type' => 'String!', 'description' => __( 'The ZIP file.', 'flx-files' ) ),
			'destination' => array( 'type' => 'String', 'description' => __( 'Folder to extract into. Empty: a new folder named after the archive.', 'flx-files' ) ),
		),
		'output'      => array(
			'path'      => array( 'type' => 'String', 'description' => __( 'Folder the files went to.', 'flx-files' ) ),
			'fileCount' => array( 'type' => 'Int', 'description' => __( 'Files extracted.', 'flx-files' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$dest = isset( $input['destination'] ) && '' !== $input['destination'] ? flx_gql_str( $input['destination'] ) : null;
			$r    = flxfm_gql_ok( FLXFM_Files_Service::unzip( flx_gql_str( $input['path'] ?? '' ), $dest ) );
			return array( 'path' => $r->path, 'fileCount' => $r->files );
		},
	)
);

flx_gql_mutation(
	'flxFilesRevert',
	array(
		'description' => __( 'Puts an earlier version of a file back. The current content is kept as a new version.', 'flx-files' ),
		'cap'         => 'flxfm_manage',
		'input'       => array(
			'path'      => array( 'type' => 'String!', 'description' => __( 'Path from the site folder.', 'flx-files' ) ),
			'versionId' => array( 'type' => 'String!', 'description' => __( 'Version id (flxFilesVersions).', 'flx-files' ) ),
		),
		'output'      => array(
			'file' => array( 'type' => 'FlxFilesFile', 'description' => __( 'The file after the restore (no content).', 'flx-files' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXFM_Files_Service::revert( flx_gql_str( $input['path'] ?? '' ), flx_gql_str( $input['versionId'] ?? '' ) );
			return $r instanceof WP_Error ? flxfm_gql_ok( $r ) : array( 'file' => $r );
		},
	)
);

flx_gql_mutation(
	'flxFilesTrashRestore',
	array(
		'description' => __( 'Puts entries back where they were (a number is added to the name when the place is taken).', 'flx-files' ),
		'cap'         => 'flxfm_manage',
		'input'       => array(
			'ids' => array( 'type' => '[String!]!', 'description' => __( 'Trash ids (flxFilesTrash).', 'flx-files' ) ),
		),
		'output'      => flxfm_gql_batch_output(),
		'resolve'     => static function ( array $input ) {
			$r = FLXFM_Files_Service::restore( flxfm_gql_paths( $input['ids'] ?? array() ) );
			return array( 'items' => $r->done, 'failed' => $r->failed );
		},
	)
);

flx_gql_mutation(
	'flxFilesTrashPurge',
	array(
		'description' => __( 'Deletes trash entries for good. This cannot be undone.', 'flx-files' ),
		'cap'         => 'flxfm_manage',
		'input'       => array(
			'ids' => array( 'type' => '[String!]', 'description' => __( 'Trash ids to delete.', 'flx-files' ) ),
			'all' => array( 'type' => 'Boolean', 'description' => __( 'Empty the whole trash (ids are ignored).', 'flx-files' ) ),
		),
		'output'      => array(
			'removed' => array( 'type' => 'Int', 'description' => __( 'Entries deleted.', 'flx-files' ) ),
			'failed'  => array(
				'type'        => '[FlxFilesFailure!]!',
				'description' => __( 'Entries that could not be deleted.', 'flx-files' ),
				'resolve'     => static fn( $p ): array => flxfm_gql_list( $p, 'failed' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXFM_Files_Service::purge( flxfm_gql_paths( $input['ids'] ?? array() ), ! empty( $input['all'] ) );
			return array( 'removed' => $r->removed, 'failed' => $r->failed );
		},
	)
);

flx_gql_mutation(
	'flxFilesShare',
	array(
		'description' => __( 'Shares an uploads folder on the site (or stops sharing). Visitors browse it where the shortcode is added.', 'flx-files' ),
		'cap'         => 'flxfm_manage',
		'input'       => array(
			'path' => array( 'type' => 'String!', 'description' => __( 'A folder inside the uploads folder.', 'flx-files' ) ),
			'on'   => array( 'type' => 'Boolean!', 'description' => __( 'Share (true) or stop sharing (false).', 'flx-files' ) ),
		),
		'output'      => array(
			'shared'    => array( 'type' => 'Boolean', 'description' => __( 'The folder is shared now.', 'flx-files' ) ),
			'shortcode' => array( 'type' => 'String', 'description' => __( 'Shortcode that shows the folder on a page.', 'flx-files' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = flxfm_gql_ok( FLXFM_Files_Service::share( flx_gql_str( $input['path'] ?? '' ), ! empty( $input['on'] ) ) );
			return array( 'shared' => $r->shared, 'shortcode' => $r->shortcode );
		},
	)
);

flx_gql_input(
	'FlxFilesSettingsInput',
	array(
		'description' => __( 'Settings to change. A field left out stays as it is.', 'flx-files' ),
		'fields'      => array(
			'showHidden' => array( 'type' => 'Boolean', 'description' => __( 'Show files that start with a dot.', 'flx-files' ) ),
			'trashDays'  => array( 'type' => 'Int', 'description' => __( 'Days the trash keeps entries (0–365).', 'flx-files' ) ),
			'versions'   => array( 'type' => 'Int', 'description' => __( 'Earlier versions kept per file (0–50).', 'flx-files' ) ),
			'quotaGb'    => array( 'type' => 'Float', 'description' => __( 'Hosting disk quota in GB.', 'flx-files' ) ),
			'mcpWrite'   => array( 'type' => 'Boolean', 'description' => __( 'External MCP clients may change files.', 'flx-files' ) ),
			'aiCode'     => array( 'type' => 'Boolean', 'description' => __( 'AI and MCP may change code files.', 'flx-files' ) ),
			'shared'     => array( 'type' => '[String!]', 'description' => __( 'Shared folders (replaces the list).', 'flx-files' ) ),
		),
	)
);

flx_gql_mutation(
	'flxFilesSettingsUpdate',
	array(
		'description' => __( 'Changes Flux Files settings.', 'flx-files' ),
		'cap'         => 'flxfm_manage',
		'input'       => array(
			'settings' => array( 'type' => 'FlxFilesSettingsInput!', 'description' => __( 'Settings to change.', 'flx-files' ) ),
		),
		'output'      => array(
			'settings' => array( 'type' => 'FlxFilesSettings', 'description' => __( 'The settings after the change.', 'flx-files' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$in  = flx_gql_arr( $input['settings'] ?? array() );
			$map = array(
				'showHidden' => 'show_hidden',
				'trashDays'  => 'trash_days',
				'versions'   => 'versions',
				'quotaGb'    => 'quota_gb',
				'mcpWrite'   => 'mcp_write',
				'aiCode'     => 'ai_code',
				'shared'     => 'shared',
			);
			$set = array();
			foreach ( $map as $gql => $key ) {
				if ( array_key_exists( $gql, $in ) && null !== $in[ $gql ] ) {
					$set[ $key ] = $in[ $gql ];
				}
			}
			return array( 'settings' => FLXFM_Files_Service::update_settings( $set ) );
		},
	)
);

flx_gql_mutation(
	'flxFilesAiExplain',
	array(
		'description' => __( 'Flux AI explains a file (or the selected part). Files that hold secrets are never sent.', 'flx-files' ),
		'cap'         => 'flxfm_manage',
		'input'       => array(
			'path'      => array( 'type' => 'String!', 'description' => __( 'Path from the site folder.', 'flx-files' ) ),
			'content'   => array( 'type' => 'String', 'description' => __( 'The text as it is in the editor. Empty: the file on disk.', 'flx-files' ) ),
			'selection' => array( 'type' => 'String', 'description' => __( 'Part of the text to explain.', 'flx-files' ) ),
		),
		'output'      => array(
			'answer' => array( 'type' => 'String', 'description' => __( 'The explanation, in short Markdown.', 'flx-files' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$content = isset( $input['content'] ) ? flx_gql_str( $input['content'] ) : null;
			$r       = flxfm_gql_ok( FLXFM_Files_Service::ai_explain( flx_gql_str( $input['path'] ?? '' ), $content, flx_gql_str( $input['selection'] ?? '' ) ) );
			return array( 'answer' => $r->answer );
		},
	)
);

flx_gql_mutation(
	'flxFilesAiEdit',
	array(
		'description' => __( 'Flux AI proposes a change to a file. Nothing is saved: the screen shows the difference and the user saves.', 'flx-files' ),
		'cap'         => 'flxfm_manage',
		'input'       => array(
			'path'    => array( 'type' => 'String!', 'description' => __( 'Path from the site folder.', 'flx-files' ) ),
			'content' => array( 'type' => 'String', 'description' => __( 'The text as it is in the editor. Empty: the file on disk.', 'flx-files' ) ),
			'prompt'  => array( 'type' => 'String!', 'description' => __( 'What should change.', 'flx-files' ) ),
		),
		'output'      => array(
			'content' => array( 'type' => 'String', 'description' => __( 'The complete updated file. Empty when the AI proposed no change.', 'flx-files' ) ),
			'note'    => array( 'type' => 'String', 'description' => __( 'A short sentence about the change (or why there is none).', 'flx-files' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$content = isset( $input['content'] ) ? flx_gql_str( $input['content'] ) : null;
			$r       = flxfm_gql_ok( FLXFM_Files_Service::ai_edit( flx_gql_str( $input['path'] ?? '' ), $content, flx_gql_str( $input['prompt'] ?? '' ) ) );
			return array( 'content' => $r->content, 'note' => $r->note );
		},
	)
);
