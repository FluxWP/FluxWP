<?php
/**
 * Flux Files service: the one place the screen, REST (`flx-files/v1/op`), GraphQL and the AI explain/edit
 * calls read and change files. File system work stays in FLXFM_FS (it also serves the MCP tools); this
 * class turns its arrays into typed values and carries the batch and listing logic the REST switch had.
 *
 * Permission (`flxfm_manage`) stays with the caller, as before. Errors are WP_Error (code flxfm_*, HTTP
 * status in the data), which GraphQL maps to its error codes.
 *
 * @package FLX_Files
 */

defined( 'ABSPATH' ) || exit;

final class FLXFM_Files_Service {

	public const CACHE_TAG = 'flx-files:fs';

	/** Sort keys of a folder page. */
	public const SORTS = array( 'name', 'modified', 'size', 'type' );

	/* ------------------------------------------------------------------ typed helpers */

	/**
	 * @param mixed $r Result of FLXFM_FS (array item or WP_Error).
	 */
	private static function item( $r ): FLXFM_Item|WP_Error {
		return $r instanceof WP_Error ? $r : FLXFM_Item::from_array( flx_gql_arr( $r ) );
	}

	/**
	 * A WP_Error built like FLXFM_FS::err().
	 */
	private static function err( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( 'flxfm_' . $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Cleaned relative path as a string ('' when invalid; callers validate first).
	 */
	private static function clean_str( string $path ): string {
		$p = flxfm_clean( $path );
		return is_string( $p ) ? $p : '';
	}

	/**
	 * A number or numeric string as float (anything else: 0).
	 *
	 * @param mixed $v Value.
	 */
	private static function num( $v ): float {
		return is_numeric( $v ) ? (float) $v : 0.0;
	}

	/**
	 * Stored versions of a file.
	 *
	 * @return array<int|string,mixed>
	 */
	private static function versions_list( string $path ): array {
		$v = flxfm_versions( $path );
		return is_array( $v ) ? $v : array();
	}

	/**
	 * Cleaned relative path or the error.
	 */
	private static function clean( string $path ): string|WP_Error {
		$p = flxfm_clean( $path );
		return $p instanceof WP_Error ? $p : (string) $p;
	}

	/**
	 * @param mixed $paths Path list as sent by a client.
	 * @return list<string>
	 */
	public static function paths( $paths ): array {
		$out = array();
		foreach ( (array) $paths as $p ) {
			if ( is_string( $p ) ) {
				$out[] = $p;
			}
		}
		return array_slice( $out, 0, 1000 );
	}

	/* ------------------------------------------------------------------ site and settings */

	public static function config(): FLXFM_Config {
		$up   = wp_get_upload_dir();
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$max  = (int) wp_max_upload_size();
		$max  = $max > 0 ? $max : 2097152;
		return new FLXFM_Config(
			(bool) flxfm_writable_mode(),
			(bool) flxfm_ai_ready(),
			basename( flx_gql_str( flxfm_root() ) ),
			flx_gql_str( flxfm_root() ),
			'' !== $host ? $host : get_bloginfo( 'name' ),
			trailingslashit( site_url() ),
			flx_gql_str( flxfm_uploads_rel() ),
			untrailingslashit( flx_gql_str( $up['baseurl'] ?? '' ) ),
			(int) max( 262144, min( 8388608, $max - 65536 ) ),
			add_query_arg(
				array(
					'action'   => 'flxfm_file',
					'_wpnonce' => wp_create_nonce( 'flxfm_file' ),
				),
				admin_url( 'admin-post.php' )
			),
		);
	}

	public static function settings(): FLXFM_Settings {
		$s      = flx_gql_arr( flxfm_settings() );
		$shared = array();
		foreach ( is_array( $s['shared'] ?? null ) ? $s['shared'] : array() as $rel ) {
			$shared[] = flx_gql_str( $rel );
		}
		return new FLXFM_Settings(
			! empty( $s['show_hidden'] ),
			flx_gql_int( $s['trash_days'] ?? 30 ),
			flx_gql_int( $s['versions'] ?? 10 ),
			self::num( $s['quota_gb'] ?? 0 ),
			! empty( $s['mcp_write'] ),
			! empty( $s['ai_code'] ),
			$shared,
		);
	}

	/**
	 * Saves (part of) the settings. Keys: show_hidden, trash_days, versions, quota_gb, mcp_write, ai_code, shared.
	 *
	 * @param array<string,mixed> $in Values to change.
	 */
	public static function update_settings( array $in ): FLXFM_Settings {
		flxfm_save_settings( $in );
		flxfm_log( 'settings', '' );
		return self::settings();
	}

	/* ------------------------------------------------------------------ browsing */

	/**
	 * A folder and its parent.
	 */
	public static function folder( string $path ): FLXFM_Folder|WP_Error {
		$r = FLXFM_FS::resolve( $path );
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		$rel = flx_gql_str( $r['rel'] ?? '' );
		$abs = flx_gql_str( $r['abs'] ?? '' );
		if ( ! is_dir( $abs ) ) {
			return self::err( 'notdir', __( 'This is not a folder.', 'flx-files' ) );
		}
		$dir = self::item( FLXFM_FS::item( $abs, $rel ) );
		if ( $dir instanceof WP_Error ) {
			return $dir;
		}
		return new FLXFM_Folder( $rel, $dir, '' === $rel ? null : ( '.' === dirname( $rel ) ? '' : dirname( $rel ) ) );
	}

	/**
	 * One page of a folder's entries. Folders come first; the sort runs on the names (and, for the other
	 * keys, on one stat per entry), so only the page's entries are built in full.
	 *
	 * @param string $sort   name|modified|size|type.
	 * @param bool   $desc   Descending.
	 * @param bool   $count  Count the items inside each folder of the page.
	 * @param int    $offset First entry.
	 * @param int    $limit  Entries to return.
	 */
	public static function items_page( string $path, bool $hidden, string $sort, bool $desc, bool $dirs_only, bool $count, int $offset, int $limit ): FLXFM_Item_Page|WP_Error {
		$r = FLXFM_FS::resolve( $path );
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		$rel = flx_gql_str( $r['rel'] ?? '' );
		$abs = flx_gql_str( $r['abs'] ?? '' );
		if ( ! is_dir( $abs ) ) {
			return self::err( 'notdir', __( 'This is not a folder.', 'flx-files' ) );
		}
		$names = @scandir( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false === $names ) {
			return self::err( 'read', __( 'The folder could not be read (permissions).', 'flx-files' ), 403 );
		}
		$sort    = in_array( $sort, self::SORTS, true ) ? $sort : 'name';
		$entries = array();
		foreach ( $names as $n ) {
			if ( '.' === $n || '..' === $n || ( ! $hidden && '.' === $n[0] ) ) {
				continue;
			}
			$full = $abs . '/' . $n;
			$dir  = is_dir( $full );
			if ( $dirs_only && ! $dir ) {
				continue;
			}
			$entries[] = array( 'n' => $n, 'dir' => $dir, 'k' => 0.0 );
		}
		if ( 'modified' === $sort || 'size' === $sort ) {
			foreach ( $entries as $i => $e ) {
				$st = @stat( $abs . '/' . $e['n'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( $st ) {
					$entries[ $i ]['k'] = 'modified' === $sort ? (float) $st['mtime'] : ( $e['dir'] ? 0.0 : (float) $st['size'] );
				}
			}
		}
		$dir_sign = $desc ? -1 : 1;
		usort(
			$entries,
			static function ( array $a, array $b ) use ( $sort, $dir_sign ): int {
				if ( $a['dir'] !== $b['dir'] ) {
					return $a['dir'] ? -1 : 1;
				}
				$cmp = 0;
				if ( 'type' === $sort ) {
					$cmp = strcmp( strtolower( pathinfo( $a['n'], PATHINFO_EXTENSION ) ), strtolower( pathinfo( $b['n'], PATHINFO_EXTENSION ) ) );
				} elseif ( 'name' !== $sort ) {
					$cmp = $a['k'] <=> $b['k'];
				}
				if ( 0 === $cmp ) {
					$cmp = strnatcasecmp( $a['n'], $b['n'] );
				}
				return $dir_sign * $cmp;
			}
		);
		$total = count( $entries );
		$items = array();
		foreach ( array_slice( $entries, max( 0, $offset ), max( 1, $limit ) ) as $e ) {
			$crel = '' === $rel ? $e['n'] : $rel . '/' . $e['n'];
			$arr  = flx_gql_arr( FLXFM_FS::item( $abs . '/' . $e['n'], $crel ) );
			if ( $e['dir'] && $count ) {
				$sub          = @scandir( $abs . '/' . $e['n'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$arr['count'] = false === $sub ? null : count( $sub ) - 2;
			}
			$items[] = FLXFM_Item::from_array( flx_gql_arr( $arr ) );
		}
		return new FLXFM_Item_Page( $items, $total );
	}

	/**
	 * Whole folder at once: the legacy `list` operation (REST).
	 *
	 * @param bool|null $hidden Show dotfiles (null: the setting).
	 * @param bool      $disk   Add the storage block.
	 */
	public static function listing( string $path, ?bool $hidden, bool $disk ): FLXFM_Listing|WP_Error {
		$show = null === $hidden ? ! empty( flxfm_opt( 'show_hidden' ) ) : $hidden;
		$r    = FLXFM_FS::list_dir( $path, array( 'hidden' => $show ) );
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		$r = flx_gql_arr( $r );
		if ( $disk ) {
			$r['disk'] = FLXFM_FS::disk();
		}
		return new FLXFM_Listing( $r );
	}

	/**
	 * Children for the file tree: the legacy `tree` operation (REST).
	 *
	 * @return array{path: string, items: list<array<string,mixed>>}|WP_Error
	 */
	public static function tree( string $path ): array|WP_Error {
		$r = FLXFM_FS::list_dir( $path, array( 'hidden' => ! empty( flxfm_opt( 'show_hidden' ) ), 'no_count' => true ) );
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		$r     = flx_gql_arr( $r );
		$items = array();
		foreach ( is_array( $r['items'] ?? null ) ? $r['items'] : array() as $it ) {
			$items[] = array_intersect_key( flx_gql_arr( $it ), array_flip( array( 'name', 'path', 'type', 'kind', 'ext', 'locked', 'sensitive' ) ) );
		}
		return array( 'path' => flx_gql_str( $r['path'] ?? '' ), 'items' => $items );
	}

	/**
	 * Opens a text file.
	 */
	public static function read( string $path ): FLXFM_File|WP_Error {
		$r = FLXFM_FS::read( $path );
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		$a = flx_gql_arr( $r );
		$c = flx_gql_str( $a['content'] ?? '' );
		unset( $a['content'], $a['hash'] );
		return new FLXFM_File( FLXFM_Item::from_array( $a ), md5( $c ), $c );
	}

	public static function size( string $path ): FLXFM_Size|WP_Error {
		$r = FLXFM_FS::dir_size( $path );
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		$a = flx_gql_arr( $r );
		return new FLXFM_Size( self::num( $a['size'] ?? 0 ), flx_gql_int( $a['files'] ?? 0 ), flx_gql_int( $a['dirs'] ?? 0 ), ! empty( $a['partial'] ), $a );
	}

	/**
	 * Name (and optionally content) search under a folder.
	 */
	public static function search( string $path, string $query, bool $content ): FLXFM_Search|WP_Error {
		$r = FLXFM_FS::search( $path, $query, array( 'content' => $content, 'hidden' => ! empty( flxfm_opt( 'show_hidden' ) ) ) );
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		$a     = flx_gql_arr( $r );
		$items = array();
		foreach ( is_array( $a['results'] ?? null ) ? $a['results'] : array() as $it ) {
			$items[] = FLXFM_Item::from_array( flx_gql_arr( $it ) );
		}
		return new FLXFM_Search( $items, ! empty( $a['partial'] ), flx_gql_str( $a['query'] ?? $query ) );
	}

	public static function disk( bool $force ): FLXFM_Disk {
		$a = flx_gql_arr( FLXFM_FS::disk( $force ) );
		return new FLXFM_Disk(
			is_numeric( $a['used'] ?? null ) ? (float) $a['used'] : null,
			is_numeric( $a['total'] ?? null ) ? (float) $a['total'] : null,
			! empty( $a['scanning'] ),
			is_numeric( $a['files'] ?? null ) ? (int) $a['files'] : null,
			flx_gql_str( $a['ago'] ?? '' ),
		);
	}

	/* ------------------------------------------------------------------ changes */

	/**
	 * Saves a file (creates it when new). A hash of the content the editor opened guards against a change
	 * on the server in between.
	 */
	public static function write( string $path, string $content, string $hash, bool $create_only ): FLXFM_File|WP_Error {
		$r = FLXFM_FS::write( $path, $content, array( 'expect_hash' => $hash, 'create_only' => $create_only ) );
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		$a = flx_gql_arr( $r );
		$h = flx_gql_str( $a['hash'] ?? '' );
		return new FLXFM_File( FLXFM_Item::from_array( array_diff_key( $a, array( 'hash' => 1 ) ) ), $h, null );
	}

	public static function mkdir( string $path ): FLXFM_Item|WP_Error {
		return self::item( FLXFM_FS::mkdir( $path ) );
	}

	/**
	 * New name inside the same folder.
	 */
	public static function rename( string $path, string $name ): FLXFM_Item|WP_Error {
		if ( ! flxfm_valid_name( $name ) ) {
			return self::err( 'name', __( 'Invalid name.', 'flx-files' ) );
		}
		$dir = dirname( self::clean_str( $path ) );
		return self::item( FLXFM_FS::move( $path, ( '.' === $dir ? '' : $dir . '/' ) . $name ) );
	}

	/**
	 * Moves or copies entries into a folder.
	 *
	 * @param string       $op        move|copy.
	 * @param list<string> $paths     Entries.
	 * @param string       $dest      Target folder.
	 * @param bool         $overwrite Replace entries that exist (the replaced ones go to the trash).
	 */
	public static function transfer( string $op, array $paths, string $dest, bool $overwrite ): FLXFM_Item_Batch|WP_Error {
		$dest = self::clean( $dest );
		if ( $dest instanceof WP_Error ) {
			return $dest;
		}
		return self::each_item(
			$paths,
			static function ( string $src ) use ( $op, $dest, $overwrite ) {
				$to = ( '' === $dest ? '' : $dest . '/' ) . basename( self::clean_str( $src ) );
				return 'move' === $op ? FLXFM_FS::move( $src, $to, $overwrite ) : FLXFM_FS::copy( $src, $to, $overwrite );
			}
		);
	}

	/**
	 * Copies each entry next to itself.
	 *
	 * @param list<string> $paths Entries.
	 */
	public static function duplicate( array $paths ): FLXFM_Item_Batch {
		return self::each_item(
			$paths,
			static fn( string $src ) => FLXFM_FS::copy( $src, $src )
		);
	}

	/**
	 * Moves entries to the trash.
	 *
	 * @param list<string> $paths Entries.
	 */
	public static function trash( array $paths ): FLXFM_Trash_Batch {
		$done   = array();
		$failed = array();
		foreach ( $paths as $p ) {
			$r = FLXFM_FS::trash( $p );
			if ( $r instanceof WP_Error ) {
				$failed[] = new FLXFM_Failure( $p, $r->get_error_message() );
				continue;
			}
			$a      = flx_gql_arr( $r );
			$done[] = new FLXFM_Trashed( flx_gql_str( $a['trash_id'] ?? '' ), flx_gql_str( $a['path'] ?? '' ) );
		}
		return new FLXFM_Trash_Batch( $done, $failed );
	}

	/**
	 * @param list<string>                                  $inputs Entries.
	 * @param callable(string): (array<mixed>|WP_Error|true) $fn     Action on one entry.
	 */
	private static function each_item( array $inputs, callable $fn ): FLXFM_Item_Batch {
		$done   = array();
		$failed = array();
		foreach ( $inputs as $p ) {
			$r = $fn( $p );
			if ( $r instanceof WP_Error ) {
				$failed[] = new FLXFM_Failure( $p, $r->get_error_message() );
			} elseif ( is_array( $r ) ) {
				$done[] = FLXFM_Item::from_array( flx_gql_arr( $r ) );
			}
		}
		return new FLXFM_Item_Batch( $done, $failed );
	}

	public static function chmod( string $path, string $mode ): FLXFM_Item|WP_Error {
		return self::item( FLXFM_FS::chmod( $path, $mode ) );
	}

	/**
	 * Puts entries in a ZIP file next to the first one (or in `$dir`).
	 *
	 * @param list<string> $paths Entries.
	 * @param string       $name  File name ('' = from the entries).
	 * @param string       $dir   Folder the ZIP is created in.
	 */
	public static function zip( array $paths, string $name, string $dir ): FLXFM_Item|WP_Error {
		if ( '' === $name ) {
			$name = 1 === count( $paths ) ? basename( self::clean_str( $paths[0] ) ) . '.zip' : 'archive.zip';
		}
		if ( ! flxfm_valid_name( $name ) ) {
			return self::err( 'name', __( 'Invalid name.', 'flx-files' ) );
		}
		if ( ! preg_match( '/\.zip$/i', $name ) ) {
			$name .= '.zip';
		}
		$dir = self::clean( $dir );
		if ( $dir instanceof WP_Error ) {
			return $dir;
		}
		$r = FLXFM_FS::zip( $paths, ( '' === $dir ? '' : $dir . '/' ) . $name );
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		return self::item( flx_gql_arr( $r )['item'] ?? array() );
	}

	/**
	 * Extracts a ZIP into a new folder next to it (or `$dest`).
	 */
	public static function unzip( string $path, ?string $dest ): FLXFM_Unzip_Result|WP_Error {
		$r = FLXFM_FS::unzip( $path, $dest );
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		$a = flx_gql_arr( $r );
		return new FLXFM_Unzip_Result( flx_gql_str( $a['path'] ?? '' ), flx_gql_int( $a['files'] ?? 0 ) );
	}

	/* ------------------------------------------------------------------ versions */

	/**
	 * Earlier versions of a file, newest first.
	 *
	 * @return list<FLXFM_Version>
	 */
	public static function versions( string $path ): array {
		$out = array();
		foreach ( self::versions_list( self::clean_str( $path ) ) as $v ) {
			$v     = flx_gql_arr( $v );
			$out[] = new FLXFM_Version(
				flx_gql_str( $v['id'] ?? '' ),
				flx_gql_int( $v['t'] ?? 0 ),
				flx_gql_str( $v['user'] ?? '' ),
				flx_gql_str( $v['via'] ?? '' ),
				flx_gql_int( $v['size'] ?? 0 )
			);
		}
		return $out;
	}

	/**
	 * Raw legacy rows (REST).
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function versions_rest( string $path ): array {
		return array_map( 'flx_gql_arr', array_values( self::versions_list( self::clean_str( $path ) ) ) );
	}

	public static function version_content( string $path, string $id ): string|WP_Error {
		$c = flxfm_version_read( self::clean_str( $path ), $id );
		return $c instanceof WP_Error ? $c : flx_gql_str( $c );
	}

	/**
	 * Puts a version back (the current content becomes a version).
	 */
	public static function revert( string $path, string $id ): FLXFM_File|WP_Error {
		$c = self::version_content( $path, $id );
		if ( $c instanceof WP_Error ) {
			return $c;
		}
		$r = FLXFM_FS::write( $path, $c );
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		$a = flx_gql_arr( $r );
		flxfm_log( 'revert', flx_gql_str( $a['path'] ?? $path ) );
		return new FLXFM_File( FLXFM_Item::from_array( array_diff_key( $a, array( 'hash' => 1 ) ) ), flx_gql_str( $a['hash'] ?? '' ), null );
	}

	/* ------------------------------------------------------------------ trash */

	public static function trash_list(): FLXFM_Trash {
		$items = array();
		$list = flxfm_trash_list();
		foreach ( is_array( $list ) ? $list : array() as $m ) {
			$m       = flx_gql_arr( $m );
			$items[] = new FLXFM_Trash_Item(
				flx_gql_str( $m['id'] ?? '' ),
				flx_gql_str( $m['name'] ?? '' ),
				flx_gql_str( $m['path'] ?? '' ),
				'dir' === ( $m['type'] ?? '' ),
				flx_gql_str( $m['kind'] ?? '', 'file' ),
				isset( $m['size'] ) && is_numeric( $m['size'] ) ? (float) $m['size'] : null,
				flx_gql_int( $m['t'] ?? 0 ),
				flx_gql_str( $m['user'] ?? '' ),
				flx_gql_str( $m['via'] ?? '' ),
				flx_gql_str( $m['until'] ?? '' ),
				$m
			);
		}
		return new FLXFM_Trash( $items, flx_gql_int( flxfm_opt( 'trash_days' ) ) );
	}

	/**
	 * Puts trash entries back where they were.
	 *
	 * @param list<string> $ids Trash ids.
	 */
	public static function restore( array $ids ): FLXFM_Item_Batch {
		return self::each_item( $ids, static fn( string $id ) => flxfm_trash_restore( $id ) );
	}

	/**
	 * Deletes trash entries for good.
	 *
	 * @param list<string> $ids Trash ids.
	 * @param bool         $all Everything in the trash.
	 */
	public static function purge( array $ids, bool $all ): FLXFM_Purge_Result {
		if ( $all ) {
			return new FLXFM_Purge_Result( flx_gql_int( flxfm_trash_purge( true ) ), array() );
		}
		$n      = 0;
		$failed = array();
		foreach ( $ids as $id ) {
			$r = flxfm_trash_delete( $id );
			if ( $r instanceof WP_Error ) {
				$failed[] = new FLXFM_Failure( $id, $r->get_error_message() );
			} else {
				++$n;
			}
		}
		return new FLXFM_Purge_Result( $n, $failed );
	}

	/* ------------------------------------------------------------------ activity */

	/**
	 * Activity rows, newest first.
	 *
	 * @param string $path   Only rows about this path (or inside it).
	 * @param string $via    Only rows from panel|ai|mcp|front.
	 * @param int    $offset First row.
	 * @param int    $limit  Rows.
	 */
	public static function log( string $path, string $via, int $offset, int $limit ): FLXFM_Log_Page {
		$a    = flx_gql_arr( flxfm_log_list( array( 'path' => $path, 'via' => $via, 'offset' => $offset, 'limit' => $limit ) ) );
		$rows = array();
		foreach ( is_array( $a['rows'] ?? null ) ? $a['rows'] : array() as $e ) {
			$e      = flx_gql_arr( $e );
			$rows[] = new FLXFM_Log_Entry(
				flx_gql_int( $e['t'] ?? 0 ),
				flx_gql_int( $e['u'] ?? 0 ),
				flx_gql_str( $e['user'] ?? '' ),
				flx_gql_str( $e['via'] ?? '' ),
				flx_gql_str( $e['a'] ?? '' ),
				flx_gql_str( $e['p'] ?? '' ),
				flx_gql_str( $e['to'] ?? '' )
			);
		}
		return new FLXFM_Log_Page( $rows, flx_gql_int( $a['total'] ?? 0 ), $a );
	}

	/* ------------------------------------------------------------------ sharing */

	/**
	 * Shares (or stops sharing) an uploads folder on the site with the [flx_files] shortcode.
	 */
	public static function share( string $path, bool $on ): FLXFM_Share_Result|WP_Error {
		$rel = self::clean( $path );
		if ( $rel instanceof WP_Error ) {
			return $rel;
		}
		if ( $on && ! flxfm_shareable( $rel ) ) {
			return self::err( 'share', __( 'Only folders inside the uploads folder can be shared on the site.', 'flx-files' ) );
		}
		$shared = array();
		foreach ( (array) flxfm_opt( 'shared' ) as $s ) {
			$shared[] = flx_gql_str( $s );
		}
		$list = array_values( array_diff( $shared, array( $rel ) ) );
		if ( $on ) {
			$list[] = $rel;
		}
		flxfm_save_settings( array( 'shared' => $list ) );
		flxfm_log( $on ? 'share' : 'unshare', $rel );
		return new FLXFM_Share_Result( $on, flx_gql_str( flxfm_shortcode_for( $rel ) ) );
	}

	/* ------------------------------------------------------------------ AI */

	/**
	 * Flux AI explains a file (or the selection).
	 */
	public static function ai_explain( string $path, ?string $content, string $selection ): FLXFM_Ai_Answer|WP_Error {
		$p = array( 'selection' => $selection );
		if ( null !== $content ) {
			$p['content'] = $content;
		}
		$r = flxfm_ai_run( 'ai_explain', $path, $p );
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		return new FLXFM_Ai_Answer( flx_gql_str( flx_gql_arr( $r )['answer'] ?? '' ) );
	}

	/**
	 * Flux AI proposes a change. Nothing is saved; the screen shows the difference.
	 */
	public static function ai_edit( string $path, ?string $content, string $prompt ): FLXFM_Ai_Edit|WP_Error {
		$p = array( 'prompt' => $prompt );
		if ( null !== $content ) {
			$p['content'] = $content;
		}
		$r = flxfm_ai_run( 'ai_edit', $path, $p );
		if ( $r instanceof WP_Error ) {
			return $r;
		}
		$a = flx_gql_arr( $r );
		return new FLXFM_Ai_Edit( isset( $a['content'] ) ? flx_gql_str( $a['content'] ) : null, flx_gql_str( $a['note'] ?? '' ) );
	}
}
