<?php
/**
 * MCP (Flux AI ortak sunucusu) ve Flux AI sohbeti için dosya araçları.
 * Okuma araçları serbest; yazma/silme araçları sohbette onay kartı ister (readonly=false).
 * Dış MCP istemcileri varsayılan olarak yalnız okur (Ayarlar › "MCP değişiklik yapabilir").
 * Gizli dosyalar okunmaz; çekirdek ve korumalı dosyalar değiştirilmez; kod dosyaları ayarla açılır; silme çöpe gider.
 *
 * @package FLX_Files
 */

defined( 'ABSPATH' ) || exit;

/** Yazma araçlarının adları. */
function flxfm_write_tools() {
	return array( 'flxfm_write', 'flxfm_replace', 'flxfm_mkdir', 'flxfm_move', 'flxfm_copy', 'flxfm_delete', 'flxfm_restore' );
}

/** Aracı AI/MCP kaynağıyla çalıştırır; hata Exception olur. */
function flxfm_tool_call( $write, callable $fn ) {
	$via = flxfm_is_mcp_request() ? 'mcp' : 'ai';
	if ( $write && 'mcp' === $via && ! flxfm_opt( 'mcp_write' ) ) {
		throw new Exception( 'File changes over MCP are turned off. An administrator can allow them in Flux Files › Settings.' );
	}
	$prev                 = $GLOBALS['flxfm_via'] ?? null;
	$GLOBALS['flxfm_via'] = $via;
	try {
		$r = $fn();
	} finally {
		if ( null === $prev ) {
			unset( $GLOBALS['flxfm_via'] );
		} else {
			$GLOBALS['flxfm_via'] = $prev;
		}
	}
	if ( is_wp_error( $r ) ) {
		throw new Exception( $r->get_error_message() );
	}
	return $r;
}

/** Araç sonucunda öğe özeti. */
function flxfm_tool_item( $it ) {
	$out = array(
		'name'     => $it['name'],
		'path'     => $it['path'],
		'type'     => $it['type'],
		'size'     => $it['size'],
		'modified' => $it['mtime'] ? gmdate( 'c', $it['mtime'] ) : null,
	);
	if ( isset( $it['count'] ) ) {
		$out['items'] = $it['count'];
	}
	foreach ( array( 'locked', 'sensitive', 'core' ) as $k ) {
		if ( ! empty( $it[ $k ] ) ) {
			$out[ $k ] = true;
		}
	}
	if ( ! empty( $it['matches'] ) ) {
		$out['matches'] = $it['matches'];
	}
	return $out;
}

/* Dış MCP'de yazma kapalıysa yazma araçları listede de görünmez. */
add_filter(
	'flx_mcp_tool_allowed',
	static function ( $allowed, $name ) {
		if ( $allowed && in_array( $name, flxfm_write_tools(), true ) && flxfm_is_mcp_request() && ! flxfm_opt( 'mcp_write' ) ) {
			return false;
		}
		return $allowed;
	},
	10,
	2
);

add_filter(
	'flx_mcp_tools',
	static function ( $tools ) {
		$obj  = static function ( $props, $req = array() ) {
			$s = array( 'type' => 'object', 'properties' => $props ? $props : new stdClass() );
			if ( $req ) {
				$s['required'] = $req;
			}
			return $s;
		};
		$path = array( 'type' => 'string', 'description' => 'Path relative to the WordPress root, e.g. "wp-content/themes/fluxwp/style.css". Empty = root.' );
		$base = array(
			'cap'    => 'flxfm_manage',
			'source' => 'flx-files',
		);

		$tools['flxfm_list'] = $base + array(
			'title'       => __( 'List files', 'flx-files' ),
			'description' => 'Lists a folder of the WordPress site (name, path, type, size, modified, item count). Paths are relative to the WordPress root. Protected items are flagged (locked = cannot move/delete, sensitive = cannot be read by AI, core = WordPress core).',
			'readonly'    => true,
			'log'         => false,
			'schema'      => $obj( array( 'path' => $path ) ),
			'run'         => static function ( $a ) {
				return flxfm_tool_call(
					false,
					static function () use ( $a ) {
						$r = FLXFM_FS::list_dir( (string) ( $a['path'] ?? '' ), array( 'hidden' => true ) );
						if ( is_wp_error( $r ) ) {
							return $r;
						}
						return array( 'path' => $r['path'], 'items' => array_map( 'flxfm_tool_item', $r['items'] ) );
					}
				);
			},
		);

		$tools['flxfm_read'] = $base + array(
			'title'       => __( 'Read file', 'flx-files' ),
			'description' => 'Returns the text of a file (max 3 MB; binary files are refused). Use from_line/lines to read part of a long file. Files with secrets (wp-config.php, .env, keys) are never returned.',
			'readonly'    => true,
			'schema'      => $obj(
				array(
					'path'      => $path,
					'from_line' => array( 'type' => 'integer', 'description' => 'First line (1-based). Default 1.' ),
					'lines'     => array( 'type' => 'integer', 'description' => 'Number of lines. Default all (up to 2000).' ),
				),
				array( 'path' )
			),
			'run'         => static function ( $a ) {
				return flxfm_tool_call(
					false,
					static function () use ( $a ) {
						$rel = flxfm_clean( (string) ( $a['path'] ?? '' ) );
						if ( is_wp_error( $rel ) ) {
							return $rel;
						}
						if ( flxfm_is_sensitive( $rel ) ) {
							return FLXFM_FS::err( 'ai_protected', 'This file may contain passwords or keys and is never shared with AI.', 403 );
						}
						$r = FLXFM_FS::read( $rel );
						if ( is_wp_error( $r ) ) {
							return $r;
						}
						$all   = preg_split( '/\R/', $r['content'] );
						$from  = max( 1, (int) ( $a['from_line'] ?? 1 ) );
						$count = max( 1, min( 2000, (int) ( $a['lines'] ?? 2000 ) ) );
						$part  = array_slice( $all, $from - 1, $count );
						return array(
							'path'        => $r['path'],
							'size'        => $r['size'],
							'modified'    => gmdate( 'c', $r['mtime'] ),
							'total_lines' => count( $all ),
							'from_line'   => $from,
							'to_line'     => $from + count( $part ) - 1,
							'content'     => implode( "\n", $part ),
						);
					}
				);
			},
		);

		$tools['flxfm_search'] = $base + array(
			'title'       => __( 'Search files', 'flx-files' ),
			'description' => 'Finds files and folders by name under a folder; with content=true also searches inside text/code files (< 2 MB) and returns up to 3 matching lines per file. Skips node_modules, .git and cache folders.',
			'readonly'    => true,
			'schema'      => $obj(
				array(
					'query'   => array( 'type' => 'string' ),
					'path'    => $path,
					'content' => array( 'type' => 'boolean', 'description' => 'Also search file contents.' ),
					'limit'   => array( 'type' => 'integer', 'description' => 'Max results (default 50, max 200).' ),
				),
				array( 'query' )
			),
			'run'         => static function ( $a ) {
				return flxfm_tool_call(
					false,
					static function () use ( $a ) {
						$r = FLXFM_FS::search(
							(string) ( $a['path'] ?? '' ),
							(string) ( $a['query'] ?? '' ),
							array(
								'content' => ! empty( $a['content'] ),
								'limit'   => min( 200, (int) ( $a['limit'] ?? 50 ) ),
								'hidden'  => true,
							)
						);
						if ( is_wp_error( $r ) ) {
							return $r;
						}
						return array( 'results' => array_map( 'flxfm_tool_item', $r['results'] ), 'partial' => $r['partial'] );
					}
				);
			},
		);

		$tools['flxfm_info'] = $base + array(
			'title'       => __( 'File info', 'flx-files' ),
			'description' => 'Details of one file or folder: size (folder total), file count, permissions, protection flags, saved versions and recent activity.',
			'readonly'    => true,
			'schema'      => $obj( array( 'path' => $path ), array( 'path' ) ),
			'run'         => static function ( $a ) {
				return flxfm_tool_call(
					false,
					static function () use ( $a ) {
						$r = FLXFM_FS::resolve( (string) ( $a['path'] ?? '' ) );
						if ( is_wp_error( $r ) ) {
							return $r;
						}
						$it          = FLXFM_FS::item( $r['abs'], $r['rel'] );
						$out         = flxfm_tool_item( $it );
						$out['perms'] = $it['perms'];
						$sz          = FLXFM_FS::dir_size( $r['rel'] );
						if ( 'dir' === $it['type'] && ! is_wp_error( $sz ) ) {
							$out['size']  = $sz['size'];
							$out['files'] = $sz['files'];
						}
						$out['versions'] = count( flxfm_versions( $r['rel'] ) );
						$log             = flxfm_log_list( array( 'path' => $r['rel'], 'limit' => 10 ) );
						$out['activity'] = array_map(
							static function ( $e ) {
								return array( 'when' => gmdate( 'c', $e['t'] ), 'user' => $e['user'], 'via' => $e['via'], 'action' => $e['a'], 'path' => $e['p'], 'to' => $e['to'] );
							},
							$log['rows']
						);
						return $out;
					}
				);
			},
		);

		$tools['flxfm_activity'] = $base + array(
			'title'       => __( 'File activity', 'flx-files' ),
			'description' => 'Recent file changes on the site made through Flux Files, the AI chat or MCP: who, when, from where (panel, ai, mcp) and what (create, edit, upload, move, trash, restore…).',
			'readonly'    => true,
			'schema'      => $obj(
				array(
					'path'  => array( 'type' => 'string', 'description' => 'Only this file/folder (optional).' ),
					'limit' => array( 'type' => 'integer', 'description' => 'Default 30, max 200.' ),
				)
			),
			'run'         => static function ( $a ) {
				$log = flxfm_log_list( array( 'path' => (string) ( $a['path'] ?? '' ), 'limit' => min( 200, (int) ( $a['limit'] ?? 30 ) ) ) );
				return array(
					'total'  => $log['total'],
					'events' => array_map(
						static function ( $e ) {
							return array( 'when' => gmdate( 'c', $e['t'] ), 'user' => $e['user'], 'via' => $e['via'], 'action' => $e['a'], 'path' => $e['p'], 'to' => $e['to'] );
						},
						$log['rows']
					),
				);
			},
		);

		$tools['flxfm_trash_list'] = $base + array(
			'title'       => __( 'Files in trash', 'flx-files' ),
			'description' => 'Lists deleted files and folders waiting in the Flux Files trash (id, original path, deleted at, by whom). Restore one with flxfm_restore.',
			'readonly'    => true,
			'schema'      => $obj( array() ),
			'run'         => static function () {
				return array(
					'items' => array_map(
						static function ( $t ) {
							return array( 'id' => $t['id'], 'path' => $t['path'], 'type' => $t['type'], 'deleted' => gmdate( 'c', (int) $t['t'] ), 'by' => $t['user'], 'via' => $t['via'] );
						},
						flxfm_trash_list()
					),
				);
			},
		);

		/* ------------------------------------------------------ Yazma araçları */

		$tools['flxfm_write'] = $base + array(
			'title'       => __( 'Write file', 'flx-files' ),
			'description' => 'Creates a file or replaces its whole content. The previous content is kept as a version and can be restored from Flux Files. For small edits prefer flxfm_replace. Code files (.php, .js, .css, .html …) need the "AI may write code files" setting; WordPress core, wp-config and other protected files are refused.',
			'readonly'    => false,
			'schema'      => $obj(
				array(
					'path'        => $path,
					'content'     => array( 'type' => 'string' ),
					'create_only' => array( 'type' => 'boolean', 'description' => 'Fail if the file exists.' ),
				),
				array( 'path', 'content' )
			),
			'run'         => static function ( $a ) {
				return flxfm_tool_call(
					true,
					static function () use ( $a ) {
						$r = FLXFM_FS::write( (string) ( $a['path'] ?? '' ), (string) ( $a['content'] ?? '' ), array( 'create_only' => ! empty( $a['create_only'] ) ) );
						return is_wp_error( $r ) ? $r : array( 'saved' => flxfm_tool_item( $r ) );
					}
				);
			},
		);

		$tools['flxfm_replace'] = $base + array(
			'title'       => __( 'Edit file (find and replace)', 'flx-files' ),
			'description' => 'Replaces exact text in a file. "find" must occur exactly once unless all=true. The previous content is kept as a version. Same protections as flxfm_write.',
			'readonly'    => false,
			'schema'      => $obj(
				array(
					'path'    => $path,
					'find'    => array( 'type' => 'string', 'description' => 'Exact text to replace (include enough context to be unique).' ),
					'replace' => array( 'type' => 'string' ),
					'all'     => array( 'type' => 'boolean', 'description' => 'Replace every occurrence.' ),
				),
				array( 'path', 'find', 'replace' )
			),
			'run'         => static function ( $a ) {
				return flxfm_tool_call(
					true,
					static function () use ( $a ) {
						$rel = flxfm_clean( (string) ( $a['path'] ?? '' ) );
						if ( is_wp_error( $rel ) ) {
							return $rel;
						}
						if ( flxfm_is_sensitive( $rel ) ) {
							return FLXFM_FS::err( 'ai_protected', 'This file may contain passwords or keys and is never shared with AI.', 403 );
						}
						$f = FLXFM_FS::read( $rel );
						if ( is_wp_error( $f ) ) {
							return $f;
						}
						$find = (string) ( $a['find'] ?? '' );
						if ( '' === $find ) {
							return FLXFM_FS::err( 'find', '"find" is empty.' );
						}
						$n = substr_count( $f['content'], $find );
						if ( 0 === $n ) {
							return FLXFM_FS::err( 'find', '"find" was not found in the file.' );
						}
						if ( $n > 1 && empty( $a['all'] ) ) {
							return FLXFM_FS::err( 'find', sprintf( '"find" occurs %d times; add more context or set all=true.', $n ) );
						}
						$new = empty( $a['all'] ) ? implode( (string) $a['replace'], explode( $find, $f['content'], 2 ) ) : str_replace( $find, (string) $a['replace'], $f['content'] );
						$r   = FLXFM_FS::write( $rel, $new, array( 'expect_hash' => $f['hash'] ) );
						return is_wp_error( $r ) ? $r : array( 'saved' => flxfm_tool_item( $r ), 'replaced' => empty( $a['all'] ) ? 1 : $n );
					}
				);
			},
		);

		$tools['flxfm_mkdir'] = $base + array(
			'title'       => __( 'Create folder', 'flx-files' ),
			'description' => 'Creates a folder (the parent folder must exist).',
			'readonly'    => false,
			'schema'      => $obj( array( 'path' => $path ), array( 'path' ) ),
			'run'         => static function ( $a ) {
				return flxfm_tool_call(
					true,
					static function () use ( $a ) {
						$r = FLXFM_FS::mkdir( (string) ( $a['path'] ?? '' ) );
						return is_wp_error( $r ) ? $r : array( 'created' => flxfm_tool_item( $r ) );
					}
				);
			},
		);

		$tools['flxfm_move'] = $base + array(
			'title'       => __( 'Move or rename', 'flx-files' ),
			'description' => 'Moves or renames a file or folder. "to" is the full new path. Protected and core paths cannot be moved.',
			'readonly'    => false,
			'schema'      => $obj(
				array(
					'from' => $path,
					'to'   => array( 'type' => 'string', 'description' => 'New path relative to the WordPress root.' ),
				),
				array( 'from', 'to' )
			),
			'run'         => static function ( $a ) {
				return flxfm_tool_call(
					true,
					static function () use ( $a ) {
						$r = FLXFM_FS::move( (string) ( $a['from'] ?? '' ), (string) ( $a['to'] ?? '' ) );
						return is_wp_error( $r ) ? $r : array( 'moved' => flxfm_tool_item( $r ) );
					}
				);
			},
		);

		$tools['flxfm_copy'] = $base + array(
			'title'       => __( 'Copy', 'flx-files' ),
			'description' => 'Copies a file or folder to a new path. If the target exists a number is added to the name.',
			'readonly'    => false,
			'schema'      => $obj(
				array(
					'from' => $path,
					'to'   => array( 'type' => 'string' ),
				),
				array( 'from', 'to' )
			),
			'run'         => static function ( $a ) {
				return flxfm_tool_call(
					true,
					static function () use ( $a ) {
						$r = FLXFM_FS::copy( (string) ( $a['from'] ?? '' ), (string) ( $a['to'] ?? '' ) );
						return is_wp_error( $r ) ? $r : array( 'copied' => flxfm_tool_item( $r ) );
					}
				);
			},
		);

		$tools['flxfm_delete'] = $base + array(
			'title'       => __( 'Delete (to trash)', 'flx-files' ),
			'description' => 'Moves a file or folder to the Flux Files trash (restorable with flxfm_restore until the trash is emptied). Protected and core paths cannot be deleted; AI can delete folders with fewer than 2,000 files.',
			'readonly'    => false,
			'schema'      => $obj( array( 'path' => $path ), array( 'path' ) ),
			'run'         => static function ( $a ) {
				return flxfm_tool_call(
					true,
					static function () use ( $a ) {
						return FLXFM_FS::trash( (string) ( $a['path'] ?? '' ) );
					}
				);
			},
		);

		$tools['flxfm_restore'] = $base + array(
			'title'       => __( 'Restore from trash', 'flx-files' ),
			'description' => 'Restores a trashed item to its original path (a number is added if the path is taken). Get ids with flxfm_trash_list.',
			'readonly'    => false,
			'schema'      => $obj( array( 'id' => array( 'type' => 'string' ) ), array( 'id' ) ),
			'run'         => static function ( $a ) {
				return flxfm_tool_call(
					true,
					static function () use ( $a ) {
						$r = flxfm_trash_restore( (string) ( $a['id'] ?? '' ) );
						return is_wp_error( $r ) ? $r : array( 'restored' => flxfm_tool_item( $r ) );
					}
				);
			},
		);
		return $tools;
	}
);

add_filter(
	'flx_mcp_instructions',
	static function ( $lines ) {
		$lines[] = 'Site files: flxfm_list, flxfm_search and flxfm_read browse the WordPress files (paths relative to the WordPress root). Prefer flxfm_replace for small edits; every overwrite keeps a version and deletes go to the trash. wp-config.php and other secret files are never readable.';
		return $lines;
	}
);
