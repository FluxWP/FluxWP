<?php
/**
 * Document page protection (access, lock) and page comments in one place. REST (includes/doc-comments.php,
 * includes/docs.php) and GraphQL (includes/graphql/) both call it.
 *
 * Page role: the document's (project's) role, narrowed by every "restricted" page on the way up (the page
 * itself and its parents): there the user needs a member row, and the lower of the two roles wins. Project
 * admins always keep admin. A locked page keeps its roles; it only refuses edits until someone who can
 * edit unlocks it. Comments work on locked pages.
 *
 * @package FLX_Tasks
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxgDocAnchor array{b: string, o: int, eb: string, eo: int, q: string, kind: string, label: string}
 * @phpstan-type FlxgDocComment array{id: int, page: int, parent: int, user: int, body: string, anchor: FlxgDocAnchor|null, created: string|null, edited: string|null, resolved: array{by: int, at: string|null}|null, deleted: bool, canEdit: bool, canDelete: bool, canResolve: bool}
 */
final class FLXG_Doc_Service {

	/** @var array<int,array<mixed>|null> */
	private static array $pages = array();

	/** @var array<int,array<int,string>> */
	private static array $members = array();

	private static function err( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/** Table name. */
	private static function t( string $name ): string {
		$t = flxg_table( $name );
		return is_string( $t ) ? $t : '';
	}

	/** MySQL time as ISO 8601 (null when empty). */
	private static function iso( string $mysql ): ?string {
		$t = flxg_iso( $mysql );
		return is_string( $t ) && '' !== $t ? $t : null;
	}

	/** The user's document role ('' = no access). */
	private static function drole( int $doc_id, int $uid ): string {
		$r = flxg_doc_role( $doc_id, $uid );
		return is_string( $r ) ? $r : '';
	}

	/** Forget cached rows (after a write). */
	public static function flush( int $page_id = 0 ): void {
		if ( $page_id ) {
			unset( self::$pages[ $page_id ], self::$members[ $page_id ] );
			return;
		}
		self::$pages   = array();
		self::$members = array();
	}

	/**
	 * Raw page row (cached for the request).
	 *
	 * @return array<mixed>|null
	 */
	public static function page( int $id ): ?array {
		if ( $id <= 0 ) {
			return null;
		}
		if ( ! array_key_exists( $id, self::$pages ) ) {
			$row                = flxg_get_page( $id );
			self::$pages[ $id ] = is_array( $row ) ? $row : null;
		}
		return self::$pages[ $id ];
	}

	/**
	 * Member roles of a restricted page: user id => role.
	 *
	 * @return array<int,string>
	 */
	public static function members( int $page_id ): array {
		if ( ! isset( self::$members[ $page_id ] ) ) {
			global $wpdb;
			/** @var \wpdb $wpdb */
			$out  = array();
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT user_id, role FROM %i WHERE page_id = %d', self::t( 'doc_page_members' ), $page_id ), ARRAY_A ); // phpcs:ignore
			foreach ( is_array( $rows ) ? $rows : array() as $r ) {
				$r = FLXG_Val::arr( $r );
				$out[ FLXG_Val::int( $r, 'user_id' ) ] = FLXG_Val::str( $r, 'role' );
			}
			self::$members[ $page_id ] = $out;
		}
		return self::$members[ $page_id ];
	}

	private static function lower( string $a, string $b ): string {
		return flxg_role_rank( $a ) <= flxg_role_rank( $b ) ? $a : $b;
	}

	/** The user's role on a page ('' = no access). */
	public static function role( int $page_id, int $uid ): string {
		$p = self::page( $page_id );
		if ( ! $p ) {
			return '';
		}
		$role = self::drole( FLXG_Val::int( $p, 'doc_id' ), $uid );
		if ( '' === $role || 'admin' === $role ) {
			return $role;
		}
		for ( $i = 0; $p && $i < 25; $i++ ) {
			if ( 'restricted' === FLXG_Val::str( $p, 'access' ) ) {
				$m    = self::members( FLXG_Val::int( $p, 'id' ) );
				$role = isset( $m[ $uid ] ) ? self::lower( $role, $m[ $uid ] ) : '';
				if ( '' === $role ) {
					return '';
				}
			}
			$p = self::page( FLXG_Val::int( $p, 'parent_id' ) );
		}
		return $role;
	}

	public static function can( int $page_id, string $need, int $uid ): bool {
		return flxg_role_rank( self::role( $page_id, $uid ) ) >= flxg_role_rank( $need );
	}

	/** Page is locked against edits. */
	public static function locked( int $page_id ): bool {
		$p = self::page( $page_id );
		return $p && FLXG_Val::int( $p, 'locked_by' ) > 0;
	}

	/**
	 * Edit gate for content, title, move and delete: role edit and not locked.
	 *
	 * @return true|WP_Error
	 */
	public static function can_write( int $page_id, int $uid ) {
		if ( ! self::can( $page_id, 'edit', $uid ) ) {
			return self::err( 'flxg_forbidden', __( 'You are not allowed to edit this page.', 'flx-tasks' ), 403 );
		}
		if ( self::locked( $page_id ) ) {
			return self::err( 'flxg_locked', __( 'This page is locked. Unlock it to make changes.', 'flx-tasks' ), 423 );
		}
		return true;
	}

	/**
	 * Protection fields every page row carries.
	 *
	 * @param array<mixed> $p Raw page row.
	 * @return array{role: string, locked: array{by: int, at: string|null}|null, restricted: bool, cmRev: int}
	 */
	public static function state( array $p, int $uid ): array {
		$by = FLXG_Val::int( $p, 'locked_by' );
		$at = FLXG_Val::nstr( $p, 'locked_at' );
		return array(
			'role'       => self::role( FLXG_Val::int( $p, 'id' ), $uid ),
			'locked'     => $by ? array( 'by' => $by, 'at' => $at ? self::iso( $at ) : null ) : null,
			'restricted' => 'restricted' === FLXG_Val::str( $p, 'access' ),
			'cmRev'      => FLXG_Val::int( $p, 'cm_rev' ),
		);
	}

	/**
	 * Lock or unlock a page.
	 *
	 * @return array{locked: array{by: int, at: string|null}|null}|WP_Error
	 */
	public static function lock( int $page_id, bool $lock, int $uid ) {
		global $wpdb;
		/** @var \wpdb $wpdb */
		if ( ! self::can( $page_id, 'edit', $uid ) ) {
			return self::err( 'flxg_forbidden', __( 'Only people who can edit the page can lock or unlock it.', 'flx-tasks' ), 403 );
		}
		$wpdb->update(
			self::t( 'doc_pages' ),
			$lock ? array( 'locked_by' => $uid, 'locked_at' => flxg_now() ) : array( 'locked_by' => 0, 'locked_at' => null ),
			array( 'id' => $page_id )
		);
		self::flush( $page_id );
		$p = self::page( $page_id );
		if ( $p ) {
			flxg_bump_doc_tree( FLXG_Val::int( $p, 'doc_id' ) );
		}
		return array( 'locked' => $p ? self::state( $p, $uid )['locked'] : null );
	}

	/**
	 * Access settings of a page (needs edit).
	 *
	 * @return array{access: string, members: list<array{user: int, role: string}>, inherited: bool, candidates: list<int>, admins: list<int>}|WP_Error
	 */
	public static function access( int $page_id, int $uid ) {
		$p = self::page( $page_id );
		if ( ! $p || ! self::can( $page_id, 'edit', $uid ) ) {
			return self::err( 'flxg_forbidden', __( 'Only people who can edit the page can change who has access.', 'flx-tasks' ), 403 );
		}
		$members = array();
		foreach ( self::members( $page_id ) as $u => $r ) {
			$members[] = array( 'user' => $u, 'role' => $r );
		}
		// A parent page is restricted: this page cannot be wider than that.
		$inherited = false;
		for ( $q = self::page( FLXG_Val::int( $p, 'parent_id' ) ), $i = 0; $q && $i < 25; $q = self::page( FLXG_Val::int( $q, 'parent_id' ) ), $i++ ) {
			$inherited = $inherited || 'restricted' === FLXG_Val::str( $q, 'access' );
		}
		// People who can open the document (and so can be given access); project admins always have it.
		$doc        = FLXG_Val::int( $p, 'doc_id' );
		$candidates = array();
		$admins     = array();
		foreach ( array_keys( FLXG_Val::arr( flxg_users() ) ) as $u ) {
			$r = self::drole( $doc, (int) $u );
			if ( '' !== $r ) {
				$candidates[] = (int) $u;
				if ( 'admin' === $r ) {
					$admins[] = (int) $u;
				}
			}
		}
		return array(
			'access'     => 'restricted' === FLXG_Val::str( $p, 'access' ) ? 'restricted' : 'doc',
			'members'    => $members,
			'inherited'  => $inherited,
			'candidates' => $candidates,
			'admins'     => $admins,
		);
	}

	/**
	 * Change who can open the page. 'doc': everyone with access to the document; 'restricted': project admins
	 * plus the listed people. The person who restricts the page always stays in with edit.
	 *
	 * @param list<array{user?: mixed, role?: mixed}> $members Members.
	 * @return array{access: string, members: list<array{user: int, role: string}>, inherited: bool, candidates: list<int>, admins: list<int>}|WP_Error
	 */
	public static function set_access( int $page_id, string $access, array $members, int $uid ) {
		global $wpdb;
		/** @var \wpdb $wpdb */
		$p = self::page( $page_id );
		if ( ! $p || ! self::can( $page_id, 'edit', $uid ) ) {
			return self::err( 'flxg_forbidden', __( 'Only people who can edit the page can change who has access.', 'flx-tasks' ), 403 );
		}
		$doc    = FLXG_Val::int( $p, 'doc_id' );
		$access = 'restricted' === $access ? 'restricted' : '';
		$wpdb->update( self::t( 'doc_pages' ), array( 'access' => $access ), array( 'id' => $page_id ) );
		$wpdb->delete( self::t( 'doc_page_members' ), array( 'page_id' => $page_id ) );
		if ( 'restricted' === $access ) {
			$keep = array( $uid => 'edit' );
			foreach ( $members as $m ) {
				$u    = is_numeric( $m['user'] ?? null ) ? (int) $m['user'] : 0;
				$role = is_string( $m['role'] ?? null ) ? $m['role'] : 'view';
				if ( $u > 0 && $u !== $uid && in_array( $role, array( 'view', 'comment', 'edit' ), true ) && '' !== self::drole( $doc, $u ) ) {
					$keep[ $u ] = $role;
				}
			}
			foreach ( array_slice( $keep, 0, 300, true ) as $u => $role ) {
				$wpdb->insert( self::t( 'doc_page_members' ), array( 'page_id' => $page_id, 'user_id' => $u, 'role' => $role ) );
			}
		}
		self::flush();
		flxg_bump_doc_tree( $doc );
		return self::access( $page_id, $uid );
	}

	/**
	 * Pages of a document the user can open, with their protection fields.
	 *
	 * @param list<array<mixed>> $rows flxg_page_row() rows.
	 * @return list<array<mixed>>
	 */
	public static function visible( array $rows, int $uid ): array {
		$out = array();
		foreach ( $rows as $r ) {
			$raw = self::page( FLXG_Val::int( $r, 'id' ) );
			if ( ! $raw ) {
				continue;
			}
			$st = self::state( $raw, $uid );
			if ( '' === $st['role'] ) {
				continue;
			}
			$out[] = $r + $st;
		}
		return $out;
	}

	/* =====================================================================
	 * Comments
	 * ================================================================== */

	/**
	 * Clean anchor: start block + offset, end block + offset (text characters), quoted text; or a whole block
	 * (image, file, table) with a label.
	 *
	 * @param mixed $a Input.
	 * @return FlxgDocAnchor|null
	 */
	public static function anchor( $a ): ?array {
		if ( ! is_array( $a ) ) {
			return null;
		}
		$id = static fn( $v ): string => is_string( $v ) ? substr( (string) preg_replace( '/[^a-z0-9]/i', '', $v ), 0, 24 ) : '';
		$b  = $id( $a['b'] ?? '' );
		if ( '' === $b ) {
			return null;
		}
		$eb = $id( $a['eb'] ?? '' );
		return array(
			'b'     => $b,
			'o'     => max( 0, is_numeric( $a['o'] ?? null ) ? (int) $a['o'] : 0 ),
			'eb'    => '' !== $eb ? $eb : $b,
			'eo'    => max( 0, is_numeric( $a['eo'] ?? null ) ? (int) $a['eo'] : 0 ),
			'q'     => mb_substr( sanitize_textarea_field( is_string( $a['q'] ?? null ) ? $a['q'] : '' ), 0, 500 ),
			'kind'  => 'block' === ( $a['kind'] ?? '' ) ? 'block' : 'text',
			'label' => mb_substr( sanitize_text_field( is_string( $a['label'] ?? null ) ? $a['label'] : '' ), 0, 120 ),
		);
	}

	/**
	 * @param array<mixed> $r Raw comment row.
	 * @return FlxgDocComment
	 */
	private static function out( array $r, string $role, int $uid ): array {
		$author  = FLXG_Val::int( $r, 'user_id' );
		$deleted = FLXG_Val::int( $r, 'deleted' ) > 0;
		$by      = FLXG_Val::int( $r, 'resolved_by' );
		$anchor  = FLXG_Val::int( $r, 'parent_id' ) ? null : self::anchor( flxg_json( FLXG_Val::str( $r, 'anchor' ), array() ) );
		$at      = FLXG_Val::nstr( $r, 'resolved_at' );
		$ed      = FLXG_Val::nstr( $r, 'edited_at' );
		return array(
			'id'         => FLXG_Val::int( $r, 'id' ),
			'page'       => FLXG_Val::int( $r, 'page_id' ),
			'parent'     => FLXG_Val::int( $r, 'parent_id' ),
			'user'       => $author,
			'body'       => $deleted ? '' : FLXG_Val::str( $r, 'body' ),
			'anchor'     => $anchor,
			'created'    => self::iso( FLXG_Val::str( $r, 'created_at' ) ),
			'edited'     => $ed ? self::iso( $ed ) : null,
			'resolved'   => $by ? array( 'by' => $by, 'at' => $at ? self::iso( $at ) : null ) : null,
			'deleted'    => $deleted,
			'canEdit'    => ! $deleted && $author === $uid && flxg_role_rank( $role ) >= 2,
			'canDelete'  => ! $deleted && ( ( $author === $uid && flxg_role_rank( $role ) >= 2 ) || 'admin' === $role ),
			'canResolve' => ! FLXG_Val::int( $r, 'parent_id' ) && ( flxg_role_rank( $role ) >= 3 || ( $author === $uid && flxg_role_rank( $role ) >= 2 ) ),
		);
	}

	/**
	 * Raw comment row.
	 *
	 * @return array<mixed>|null
	 */
	private static function row( int $id ): ?array {
		global $wpdb;
		/** @var \wpdb $wpdb */
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::t( 'doc_comments' ), $id ), ARRAY_A ); // phpcs:ignore
		return is_array( $r ) ? $r : null;
	}

	private static function bump( int $page_id ): void {
		global $wpdb;
		/** @var \wpdb $wpdb */
		$wpdb->query( (string) $wpdb->prepare( 'UPDATE %i SET cm_rev = cm_rev + 1 WHERE id = %d', self::t( 'doc_pages' ), $page_id ) ); // phpcs:ignore
		self::flush( $page_id );
	}

	/** Comment revision of a page: changes whenever a comment is added, edited, resolved or deleted. */
	public static function rev( int $page_id ): int {
		$p = self::page( $page_id );
		return $p ? FLXG_Val::int( $p, 'cm_rev' ) : 0;
	}

	/**
	 * All comments of a page, threads first then replies, oldest first.
	 *
	 * @return array{comments: list<FlxgDocComment>, rev: int, role: string}|WP_Error
	 */
	public static function comments( int $page_id, int $uid ) {
		global $wpdb;
		/** @var \wpdb $wpdb */
		$role = self::role( $page_id, $uid );
		if ( '' === $role ) {
			return self::err( 'flxg_404', __( 'Page not found or you have no access.', 'flx-tasks' ), 404 );
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE page_id = %d ORDER BY parent_id > 0, id LIMIT 2000', self::t( 'doc_comments' ), $page_id ), ARRAY_A ); // phpcs:ignore
		$out  = array();
		foreach ( is_array( $rows ) ? $rows : array() as $r ) {
			$out[] = self::out( FLXG_Val::arr( $r ), $role, $uid );
		}
		return array( 'comments' => $out, 'rev' => self::rev( $page_id ), 'role' => $role );
	}

	/**
	 * New thread (anchor) or reply (parent).
	 *
	 * @param mixed $anchor Anchor input (threads only).
	 * @return FlxgDocComment|WP_Error
	 */
	public static function create( int $page_id, int $parent, $anchor, string $body, int $uid ) {
		global $wpdb;
		/** @var \wpdb $wpdb */
		$p    = self::page( $page_id );
		$role = self::role( $page_id, $uid );
		if ( ! $p || flxg_role_rank( $role ) < 2 ) {
			return self::err( 'flxg_forbidden', __( 'You are not allowed to comment on this page.', 'flx-tasks' ), 403 );
		}
		$body = trim( mb_substr( sanitize_textarea_field( $body ), 0, 5000 ) );
		if ( '' === $body ) {
			return self::err( 'flxg_empty', __( 'Write a comment first.', 'flx-tasks' ) );
		}
		$a = null;
		if ( $parent ) {
			$root = self::row( $parent );
			if ( ! $root || FLXG_Val::int( $root, 'page_id' ) !== $page_id || FLXG_Val::int( $root, 'parent_id' ) ) {
				return self::err( 'flxg_404', __( 'Comment not found.', 'flx-tasks' ), 404 );
			}
		} else {
			$a = self::anchor( $anchor );
			if ( ! $a ) {
				return self::err( 'flxg_anchor', __( 'Select some text or a block to comment on.', 'flx-tasks' ) );
			}
		}
		$wpdb->insert(
			self::t( 'doc_comments' ),
			array(
				'page_id'    => $page_id,
				'doc_id'     => FLXG_Val::int( $p, 'doc_id' ),
				'parent_id'  => $parent,
				'user_id'    => $uid,
				'anchor'     => $a ? (string) wp_json_encode( $a ) : '',
				'body'       => $body,
				'created_at' => flxg_now(),
			)
		);
		$id = (int) $wpdb->insert_id;
		if ( $parent ) {
			// A reply reopens a resolved thread.
			$wpdb->update( self::t( 'doc_comments' ), array( 'resolved_by' => 0, 'resolved_at' => null ), array( 'id' => $parent ) );
		}
		self::bump( $page_id );
		$r = self::row( $id );
		if ( ! $r ) {
			return self::err( 'flxg_db', __( 'The comment could not be saved.', 'flx-tasks' ), 500 );
		}
		/**
		 * A comment was added to a document page.
		 *
		 * @param int $id      Comment id.
		 * @param int $page_id Page id.
		 * @param int $parent  Thread (0 = new thread).
		 * @param int $uid     Author.
		 */
		do_action( 'flxg_doc_comment_added', $id, $page_id, $parent, $uid );
		return self::out( $r, $role, $uid );
	}

	/**
	 * Comment row + the user's page role, or an error.
	 *
	 * @return array{0: array<mixed>, 1: string}|WP_Error
	 */
	private static function own( int $id, int $uid ) {
		$r = self::row( $id );
		if ( ! $r ) {
			return self::err( 'flxg_404', __( 'Comment not found.', 'flx-tasks' ), 404 );
		}
		$role = self::role( FLXG_Val::int( $r, 'page_id' ), $uid );
		if ( '' === $role ) {
			return self::err( 'flxg_404', __( 'Comment not found.', 'flx-tasks' ), 404 );
		}
		return array( $r, $role );
	}

	/**
	 * Edit the text of one's own comment.
	 *
	 * @return FlxgDocComment|WP_Error
	 */
	public static function update( int $id, string $body, int $uid ) {
		global $wpdb;
		/** @var \wpdb $wpdb */
		$o = self::own( $id, $uid );
		if ( $o instanceof WP_Error ) {
			return $o;
		}
		list( $r, $role ) = $o;
		if ( ! self::out( $r, $role, $uid )['canEdit'] ) {
			return self::err( 'flxg_forbidden', __( 'Only the author can edit a comment.', 'flx-tasks' ), 403 );
		}
		$body = trim( mb_substr( sanitize_textarea_field( $body ), 0, 5000 ) );
		if ( '' === $body ) {
			return self::err( 'flxg_empty', __( 'Write a comment first.', 'flx-tasks' ) );
		}
		$wpdb->update( self::t( 'doc_comments' ), array( 'body' => $body, 'edited_at' => flxg_now() ), array( 'id' => $id ) );
		self::bump( FLXG_Val::int( $r, 'page_id' ) );
		$r = self::row( $id );
		return $r ? self::out( $r, $role, $uid ) : self::err( 'flxg_404', __( 'Comment not found.', 'flx-tasks' ), 404 );
	}

	/**
	 * Delete a comment (author, or a project admin). A thread with replies keeps its place as "deleted"
	 * so the replies stay readable; the last reply of a deleted thread takes the thread with it.
	 *
	 * @return array{id: int, page: int}|WP_Error
	 */
	public static function delete( int $id, int $uid ) {
		global $wpdb;
		/** @var \wpdb $wpdb */
		$o = self::own( $id, $uid );
		if ( $o instanceof WP_Error ) {
			return $o;
		}
		list( $r, $role ) = $o;
		if ( ! self::out( $r, $role, $uid )['canDelete'] ) {
			return self::err( 'flxg_forbidden', __( 'Only the author or a project admin can delete a comment.', 'flx-tasks' ), 403 );
		}
		$t      = self::t( 'doc_comments' );
		$page   = FLXG_Val::int( $r, 'page_id' );
		$parent = FLXG_Val::int( $r, 'parent_id' );
		if ( ! $parent ) {
			$replies = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE parent_id = %d', $t, $id ) ); // phpcs:ignore
			if ( $replies ) {
				$wpdb->update( $t, array( 'deleted' => 1, 'body' => '' ), array( 'id' => $id ) );
			} else {
				$wpdb->delete( $t, array( 'id' => $id ) );
			}
		} else {
			$wpdb->delete( $t, array( 'id' => $id ) );
			$root = self::row( $parent );
			if ( $root && FLXG_Val::int( $root, 'deleted' ) && ! (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE parent_id = %d', $t, $parent ) ) ) { // phpcs:ignore
				$wpdb->delete( $t, array( 'id' => $parent ) );
			}
		}
		self::bump( $page );
		return array( 'id' => $id, 'page' => $page );
	}

	/**
	 * Resolve or reopen a thread.
	 *
	 * @return FlxgDocComment|WP_Error
	 */
	public static function resolve( int $id, bool $resolved, int $uid ) {
		global $wpdb;
		/** @var \wpdb $wpdb */
		$o = self::own( $id, $uid );
		if ( $o instanceof WP_Error ) {
			return $o;
		}
		list( $r, $role ) = $o;
		if ( ! self::out( $r, $role, $uid )['canResolve'] ) {
			return self::err( 'flxg_forbidden', __( 'You are not allowed to resolve this comment.', 'flx-tasks' ), 403 );
		}
		$wpdb->update( self::t( 'doc_comments' ), $resolved ? array( 'resolved_by' => $uid, 'resolved_at' => flxg_now() ) : array( 'resolved_by' => 0, 'resolved_at' => null ), array( 'id' => $id ) );
		self::bump( FLXG_Val::int( $r, 'page_id' ) );
		$r = self::row( $id );
		return $r ? self::out( $r, $role, $uid ) : self::err( 'flxg_404', __( 'Comment not found.', 'flx-tasks' ), 404 );
	}

	/** Page removed: its comments and member rows go too. */
	public static function purge( int $page_id ): void {
		global $wpdb;
		/** @var \wpdb $wpdb */
		$wpdb->delete( self::t( 'doc_comments' ), array( 'page_id' => $page_id ) );
		$wpdb->delete( self::t( 'doc_page_members' ), array( 'page_id' => $page_id ) );
		self::flush( $page_id );
	}
}
