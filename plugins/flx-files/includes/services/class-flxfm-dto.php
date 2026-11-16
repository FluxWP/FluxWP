<?php
/**
 * Typed values the Flux Files service returns. GraphQL reads the typed properties; the REST endpoint
 * (`flx-files/v1/op`) and the MCP tools keep their JSON shape through `$rest`, the array the file system
 * layer built, so those responses stay byte for byte the same.
 *
 * @package FLX_Files
 */

defined( 'ABSPATH' ) || exit;

/**
 * A line of a content search hit.
 */
final class FLXFM_Match {

	/**
	 * @param int    $line Line number (1-based).
	 * @param string $text Line text (trimmed, at most 200 characters).
	 */
	public function __construct(
		public readonly int $line,
		public readonly string $text,
	) {}
}

/**
 * A file or a folder.
 */
final class FLXFM_Item {

	/**
	 * @param string                 $name      File or folder name.
	 * @param string                 $path      Path from the site root ('' = the root).
	 * @param bool                   $is_dir    Folder.
	 * @param string                 $kind      dir|image|video|audio|pdf|archive|code|doc|text|font|file.
	 * @param string                 $ext       Lower case extension (files).
	 * @param float|null             $size      Bytes (files); null for folders.
	 * @param int                    $mtime     Modified (Unix time).
	 * @param string                 $perms     Octal permissions, e.g. 0644.
	 * @param bool                   $writable  The server can write it.
	 * @param bool                   $link      Symbolic link.
	 * @param bool                   $locked    Protected: cannot be moved, renamed or deleted.
	 * @param bool                   $sensitive Holds secrets: never shared with AI.
	 * @param bool                   $core      WordPress core file.
	 * @param bool                   $shared    Folder shared on the site with [flx_files].
	 * @param int|null               $count     Items inside (folders of a listing); null when unknown.
	 * @param list<FLXFM_Match>      $matches   Content search hits.
	 * @param array<string,mixed>    $rest      The legacy array (REST and MCP shape).
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $path,
		public readonly bool $is_dir,
		public readonly string $kind,
		public readonly string $ext,
		public readonly ?float $size,
		public readonly int $mtime,
		public readonly string $perms,
		public readonly bool $writable,
		public readonly bool $link,
		public readonly bool $locked,
		public readonly bool $sensitive,
		public readonly bool $core,
		public readonly bool $shared,
		public readonly ?int $count,
		public readonly array $matches,
		public readonly array $rest,
	) {}

	/**
	 * From the array FLXFM_FS::item() builds.
	 *
	 * @param array<string,mixed> $a Legacy array.
	 */
	public static function from_array( array $a ): self {
		$matches = array();
		if ( isset( $a['matches'] ) && is_array( $a['matches'] ) ) {
			foreach ( $a['matches'] as $m ) {
				$m         = flx_gql_arr( $m );
				$matches[] = new FLXFM_Match( flx_gql_int( $m['line'] ?? 0 ), flx_gql_str( $m['text'] ?? '' ) );
			}
		}
		return new self(
			flx_gql_str( $a['name'] ?? '' ),
			flx_gql_str( $a['path'] ?? '' ),
			'dir' === ( $a['type'] ?? '' ),
			flx_gql_str( $a['kind'] ?? '', 'file' ),
			flx_gql_str( $a['ext'] ?? '' ),
			isset( $a['size'] ) && is_numeric( $a['size'] ) ? (float) $a['size'] : null,
			flx_gql_int( $a['mtime'] ?? 0 ),
			flx_gql_str( $a['perms'] ?? '' ),
			! empty( $a['writable'] ),
			! empty( $a['link'] ),
			! empty( $a['locked'] ),
			! empty( $a['sensitive'] ),
			! empty( $a['core'] ),
			! empty( $a['shared'] ),
			isset( $a['count'] ) && is_numeric( $a['count'] ) ? (int) $a['count'] : null,
			$matches,
			$a,
		);
	}

	/**
	 * Legacy shape.
	 *
	 * @return array<string,mixed>
	 */
	public function to_rest(): array {
		return $this->rest;
	}
}

/**
 * A folder: its own entry and where "up" leads.
 */
final class FLXFM_Folder {

	/**
	 * @param string    $path   Path from the site root.
	 * @param FLXFM_Item $dir    The folder itself.
	 * @param string|null $parent Parent path; null for the root.
	 */
	public function __construct(
		public readonly string $path,
		public readonly FLXFM_Item $dir,
		public readonly ?string $parent,
	) {}
}

/**
 * A page of a folder's entries (one more than asked when a next page exists).
 */
final class FLXFM_Item_Page {

	/**
	 * @param list<FLXFM_Item> $items Entries of the page.
	 * @param int              $total All entries of the folder (after the hidden/folder filters).
	 */
	public function __construct(
		public readonly array $items,
		public readonly int $total,
	) {}
}

/**
 * The legacy `list` response: a whole folder at once (REST and the classic client).
 */
final class FLXFM_Listing {

	/**
	 * @param array<string,mixed> $rest The legacy array (path, dir, items, parent, disk?).
	 */
	public function __construct( public readonly array $rest ) {}

	/**
	 * Legacy shape.
	 *
	 * @return array<string,mixed>
	 */
	public function to_rest(): array {
		return $this->rest;
	}
}

/**
 * A text file with its content.
 */
final class FLXFM_File {

	/**
	 * @param FLXFM_Item  $item    The file.
	 * @param string      $hash    md5 of the content (conflict check on save).
	 * @param string|null $content Text; null when the response carries none (a save).
	 */
	public function __construct(
		public readonly FLXFM_Item $item,
		public readonly string $hash,
		public readonly ?string $content,
	) {}

	/**
	 * Legacy shape: the item array plus content/hash.
	 *
	 * @return array<string,mixed>
	 */
	public function to_rest(): array {
		return null === $this->content
			? array_merge( $this->item->rest, array( 'hash' => $this->hash ) )
			: array_merge( $this->item->rest, array( 'content' => $this->content, 'hash' => $this->hash ) );
	}
}

/**
 * One entry that could not be processed in a batch.
 */
final class FLXFM_Failure {

	/**
	 * @param string $path    Path or id the entry was given as.
	 * @param string $message Why, in the user's language.
	 */
	public function __construct(
		public readonly string $path,
		public readonly string $message,
	) {}
}

/**
 * Result of an action on several entries (move, copy, duplicate, restore).
 */
final class FLXFM_Item_Batch {

	/**
	 * @param list<FLXFM_Item>    $done   Resulting entries, in input order of the successes.
	 * @param list<FLXFM_Failure> $failed Entries that failed.
	 */
	public function __construct(
		public readonly array $done,
		public readonly array $failed,
	) {}

	/**
	 * Legacy shape.
	 *
	 * @return array{done: list<array<string,mixed>>, errors: list<array{path: string, message: string}>}
	 */
	public function to_rest(): array {
		return array(
			'done'   => array_map( static fn( FLXFM_Item $i ): array => $i->rest, $this->done ),
			'errors' => array_map( static fn( FLXFM_Failure $f ): array => array( 'path' => $f->path, 'message' => $f->message ), $this->failed ),
		);
	}
}

/**
 * An entry that went to the trash.
 */
final class FLXFM_Trashed {

	/**
	 * @param string $trash_id Id in the trash.
	 * @param string $path     Where it was.
	 */
	public function __construct(
		public readonly string $trash_id,
		public readonly string $path,
	) {}
}

/**
 * Result of moving several entries to the trash.
 */
final class FLXFM_Trash_Batch {

	/**
	 * @param list<FLXFM_Trashed> $done   Trashed entries.
	 * @param list<FLXFM_Failure> $failed Entries that failed.
	 */
	public function __construct(
		public readonly array $done,
		public readonly array $failed,
	) {}

	/**
	 * Legacy shape.
	 *
	 * @return array{done: list<array{trash_id: string, path: string}>, errors: list<array{path: string, message: string}>}
	 */
	public function to_rest(): array {
		return array(
			'done'   => array_map( static fn( FLXFM_Trashed $t ): array => array( 'trash_id' => $t->trash_id, 'path' => $t->path ), $this->done ),
			'errors' => array_map( static fn( FLXFM_Failure $f ): array => array( 'path' => $f->path, 'message' => $f->message ), $this->failed ),
		);
	}
}

/**
 * Result of deleting trash entries for good.
 */
final class FLXFM_Purge_Result {

	/**
	 * @param int                 $removed Entries deleted.
	 * @param list<FLXFM_Failure> $failed  Entries that failed.
	 */
	public function __construct(
		public readonly int $removed,
		public readonly array $failed,
	) {}

	/**
	 * Legacy shape of purging the whole trash.
	 *
	 * @return array{removed: int}
	 */
	public function to_rest_all(): array {
		return array( 'removed' => $this->removed );
	}

	/**
	 * Legacy shape of purging chosen entries.
	 *
	 * @return array{done: list<true>, errors: list<array{path: string, message: string}>}
	 */
	public function to_rest(): array {
		return array(
			'done'   => $this->removed > 0 ? array_fill( 0, $this->removed, true ) : array(),
			'errors' => array_map( static fn( FLXFM_Failure $f ): array => array( 'path' => $f->path, 'message' => $f->message ), $this->failed ),
		);
	}
}

/**
 * Folder size.
 */
final class FLXFM_Size {

	/**
	 * @param float $size    Bytes.
	 * @param int   $files   Files.
	 * @param int   $dirs    Folders.
	 * @param bool  $partial Counting stopped early (very large folder).
	 * @param array<string,mixed> $rest Legacy array (REST).
	 */
	public function __construct(
		public readonly float $size,
		public readonly int $files,
		public readonly int $dirs,
		public readonly bool $partial,
		public readonly array $rest = array(),
	) {}

	/**
	 * Legacy shape.
	 *
	 * @return array<string,mixed>
	 */
	public function to_rest(): array {
		return $this->rest;
	}
}

/**
 * Search result.
 */
final class FLXFM_Search {

	/**
	 * @param list<FLXFM_Item> $results Hits.
	 * @param bool             $partial More hits exist (the search stopped early).
	 * @param string           $query   The words searched.
	 */
	public function __construct(
		public readonly array $results,
		public readonly bool $partial,
		public readonly string $query,
	) {}

	/**
	 * Legacy shape.
	 *
	 * @return array{results: list<array<string,mixed>>, partial: bool, query: string}
	 */
	public function to_rest(): array {
		return array(
			'results' => array_map( static fn( FLXFM_Item $i ): array => $i->rest, $this->results ),
			'partial' => $this->partial,
			'query'   => $this->query,
		);
	}
}

/**
 * Hosting account storage.
 */
final class FLXFM_Disk {

	/**
	 * @param float|null $used     Bytes used; null while the first measurement runs.
	 * @param float|null $total    Quota in bytes; null when none is set.
	 * @param bool       $scanning A measurement is in progress.
	 * @param int|null   $files    Files counted.
	 * @param string     $ago      When it was measured, in words.
	 */
	public function __construct(
		public readonly ?float $used,
		public readonly ?float $total,
		public readonly bool $scanning,
		public readonly ?int $files,
		public readonly string $ago,
	) {}

	/**
	 * Legacy shape.
	 *
	 * @return array{used: float|null, total: float|null, scanning: bool, files: int|null, ago: string}
	 */
	public function to_rest(): array {
		return array(
			'used'     => $this->used,
			'total'    => $this->total,
			'scanning' => $this->scanning,
			'files'    => $this->files,
			'ago'      => $this->ago,
		);
	}
}

/**
 * An earlier version of a file.
 */
final class FLXFM_Version {

	/**
	 * @param string $id   Version id.
	 * @param int    $time Saved at (Unix time).
	 * @param string $user Who saved it.
	 * @param string $via  panel|ai|mcp|front.
	 * @param int    $size Bytes.
	 */
	public function __construct(
		public readonly string $id,
		public readonly int $time,
		public readonly string $user,
		public readonly string $via,
		public readonly int $size,
	) {}
}

/**
 * An entry in the trash.
 */
final class FLXFM_Trash_Item {

	/**
	 * @param string     $id     Trash id.
	 * @param string     $name   Name.
	 * @param string     $path   Where it was.
	 * @param bool       $is_dir Folder.
	 * @param string     $kind   Kind (see FLXFM_Item).
	 * @param float|null $size   Bytes (files).
	 * @param int        $time   Deleted at (Unix time).
	 * @param string     $user   Who deleted it.
	 * @param string     $via    panel|ai|mcp|front.
	 * @param string     $until  Date it is deleted for good ('' = never).
	 * @param array<string,mixed> $rest Legacy array.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $name,
		public readonly string $path,
		public readonly bool $is_dir,
		public readonly string $kind,
		public readonly ?float $size,
		public readonly int $time,
		public readonly string $user,
		public readonly string $via,
		public readonly string $until,
		public readonly array $rest,
	) {}
}

/**
 * The trash: its entries and the retention.
 */
final class FLXFM_Trash {

	/**
	 * @param list<FLXFM_Trash_Item> $items Entries, newest first.
	 * @param int                    $days  Days an entry is kept (0 = until emptied).
	 */
	public function __construct(
		public readonly array $items,
		public readonly int $days,
	) {}

	/**
	 * Legacy shape.
	 *
	 * @return array{items: list<array<string,mixed>>, days: int}
	 */
	public function to_rest(): array {
		return array( 'items' => array_map( static fn( FLXFM_Trash_Item $i ): array => $i->rest, $this->items ), 'days' => $this->days );
	}
}

/**
 * One Activity row.
 */
final class FLXFM_Log_Entry {

	/**
	 * @param int    $time    When (Unix time).
	 * @param int    $user_id User id (0 = none).
	 * @param string $user    Display name.
	 * @param string $via     panel|ai|mcp|front.
	 * @param string $action  create|edit|mkdir|rename|move|copy|trash|restore|purge|chmod|zip|unzip|upload|revert|settings|share|unshare.
	 * @param string $path    Path (or text) the action was about.
	 * @param string $to      Target (moves, copies, permissions).
	 */
	public function __construct(
		public readonly int $time,
		public readonly int $user_id,
		public readonly string $user,
		public readonly string $via,
		public readonly string $action,
		public readonly string $path,
		public readonly string $to,
	) {}
}

/**
 * A page of the Activity log.
 */
final class FLXFM_Log_Page {

	/**
	 * @param list<FLXFM_Log_Entry> $rows  Rows of the page.
	 * @param int                   $total All rows matching the filter.
	 * @param array<string,mixed>   $rest  Legacy array (rows, total, page, pages).
	 */
	public function __construct(
		public readonly array $rows,
		public readonly int $total,
		public readonly array $rest = array(),
	) {}
}

/**
 * Flux Files settings.
 */
final class FLXFM_Settings {

	/**
	 * @param bool         $show_hidden Show dotfiles.
	 * @param int          $trash_days  Days the trash keeps entries (0 = until emptied).
	 * @param int          $versions    Earlier versions kept per file.
	 * @param float        $quota_gb    Hosting disk quota in GB (0 = usage only).
	 * @param bool         $mcp_write   External MCP clients may change files.
	 * @param bool         $ai_code     AI may write code files.
	 * @param list<string> $shared      Folders shared on the site.
	 */
	public function __construct(
		public readonly bool $show_hidden,
		public readonly int $trash_days,
		public readonly int $versions,
		public readonly float $quota_gb,
		public readonly bool $mcp_write,
		public readonly bool $ai_code,
		public readonly array $shared,
	) {}

}

/**
 * What the screen needs to know about this site.
 */
final class FLXFM_Config {

	/**
	 * @param bool   $writable     File changes are allowed (DISALLOW_FILE_MODS is off).
	 * @param bool   $ai_ready     Flux AI can explain and edit files.
	 * @param string $root_name    Name of the site folder.
	 * @param string $root_path    Full server path of the site folder.
	 * @param string $host         Site host name.
	 * @param string $home_url     Site address with a trailing slash.
	 * @param string $uploads_path Uploads folder, from the site root.
	 * @param string $uploads_url  Uploads address (no trailing slash).
	 * @param int    $chunk_size   Upload part size in bytes.
	 * @param string $file_url     Download / preview address base (admin-post, with a nonce).
	 */
	public function __construct(
		public readonly bool $writable,
		public readonly bool $ai_ready,
		public readonly string $root_name,
		public readonly string $root_path,
		public readonly string $host,
		public readonly string $home_url,
		public readonly string $uploads_path,
		public readonly string $uploads_url,
		public readonly int $chunk_size,
		public readonly string $file_url,
	) {}
}

/**
 * Where a shared folder shows up.
 */
final class FLXFM_Share_Result {

	/**
	 * @param bool   $shared    The folder is shared now.
	 * @param string $shortcode Shortcode that shows the folder on a page.
	 */
	public function __construct(
		public readonly bool $shared,
		public readonly string $shortcode,
	) {}

	/**
	 * Legacy shape.
	 *
	 * @return array{shared: bool, shortcode: string}
	 */
	public function to_rest(): array {
		return array( 'shared' => $this->shared, 'shortcode' => $this->shortcode );
	}
}

/**
 * Result of extracting an archive.
 */
final class FLXFM_Unzip_Result {

	/**
	 * @param string $path  Folder the files went to.
	 * @param int    $files Files extracted.
	 */
	public function __construct(
		public readonly string $path,
		public readonly int $files,
	) {}

	/**
	 * Legacy shape.
	 *
	 * @return array{path: string, files: int}
	 */
	public function to_rest(): array {
		return array( 'path' => $this->path, 'files' => $this->files );
	}
}

/**
 * AI answer to "explain this file".
 */
final class FLXFM_Ai_Answer {

	/**
	 * @param string $answer Markdown answer.
	 */
	public function __construct( public readonly string $answer ) {}

	/**
	 * Legacy shape.
	 *
	 * @return array{answer: string}
	 */
	public function to_rest(): array {
		return array( 'answer' => $this->answer );
	}
}

/**
 * AI suggestion for "edit this file". Nothing is saved.
 */
final class FLXFM_Ai_Edit {

	/**
	 * @param string|null $content The complete updated file; null when the AI proposed no change.
	 * @param string      $note    A short sentence about the change (or why none was made).
	 */
	public function __construct(
		public readonly ?string $content,
		public readonly string $note,
	) {}

	/**
	 * Legacy shape.
	 *
	 * @return array{content: string|null, note: string}
	 */
	public function to_rest(): array {
		return array( 'content' => $this->content, 'note' => $this->note );
	}
}
