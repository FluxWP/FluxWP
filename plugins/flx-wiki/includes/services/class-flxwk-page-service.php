<?php
/**
 * Wiki pages and sections: read, list, tree, save, trash, preview. GraphQL, MCP and the classic screens
 * call these methods; the permission rules live here, so every entry point behaves the same.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wiki page and section service.
 */
final class FLXWK_Page_Service {

	/** GraphQL cache tag of everything the public wiki reads (pages, tree, search, settings). */
	public const CACHE_TAG = 'flx-wiki:content';

	/** Statuses a page can have. */
	public const STATUSES = array( 'publish', 'draft', 'pending', 'private' );

	/**
	 * Section of a term.
	 */
	public static function section_of_term( WP_Term $t ): FLXWK_Section {
		$link = get_term_link( $t );
		return new FLXWK_Section(
			(int) $t->term_id,
			(string) $t->slug,
			(string) $t->name,
			(string) $t->description,
			(int) get_term_meta( $t->term_id, 'flxwk_order', true ),
			(int) $t->count,
			is_string( $link ) ? $link : ''
		);
	}

	/**
	 * Page value of a post. Does not check visibility: see can_see().
	 */
	public static function page_of_post( WP_Post $p ): FLXWK_Page {
		$term    = flxwk_section_of( $p );
		$can     = current_user_can( 'edit_post', $p->ID );
		$edit    = $can ? get_edit_post_link( $p->ID, 'raw' ) : '';
		$link    = get_permalink( $p );
		$md_url  = flxwk_md_url( $p );
		$content = flxwk_md( $p );
		return new FLXWK_Page(
			(int) $p->ID,
			get_the_title( $p ),
			(string) $p->post_name,
			(string) get_page_uri( $p ),
			(string) $p->post_status,
			(string) $p->post_excerpt,
			(string) flxwk_excerpt( $p, 160 ),
			(int) $p->post_parent,
			(int) $p->menu_order,
			$term instanceof WP_Term ? self::section_of_term( $term ) : null,
			is_string( $link ) ? $link : '',
			is_string( $md_url ) ? $md_url : '',
			(string) $p->post_modified_gmt,
			(int) flxwk_read_minutes( $content ),
			is_string( $edit ) ? $edit : '',
			$can
		);
	}

	/**
	 * The post of a wiki page id, or null.
	 */
	private static function post( int $id ): ?WP_Post {
		$p = $id > 0 ? get_post( $id ) : null;
		return $p instanceof WP_Post && FLXWK_PT === $p->post_type ? $p : null;
	}

	/**
	 * Whether the viewer may see the page: the wiki is open to them and the page is published (and has no
	 * password), or they may read it as an editor.
	 */
	public static function can_see( int $id ): bool {
		return flxwk_visible() && (bool) flxwk_readable( $id );
	}

	/**
	 * A page the viewer may see.
	 */
	public static function get( int $id ): ?FLXWK_Page {
		$p = self::post( $id );
		return $p && self::can_see( $p->ID ) ? self::page_of_post( $p ) : null;
	}

	/**
	 * Pages by id for the node loader (the visibility check is the loader's).
	 *
	 * @param list<int> $ids Post ids.
	 * @return array<int,FLXWK_Page>
	 */
	public static function by_ids( array $ids ): array {
		$out = array();
		foreach ( $ids as $id ) {
			$p = self::post( $id );
			if ( $p ) {
				$out[ $id ] = self::page_of_post( $p );
			}
		}
		return $out;
	}

	/**
	 * A page by path (plugins/ai-enabled), short name or title; the viewer must be allowed to see it.
	 */
	public static function find( string $ref ): ?FLXWK_Page {
		$p = flxwk_resolve( $ref );
		return $p instanceof WP_Post && self::can_see( $p->ID ) ? self::page_of_post( $p ) : null;
	}

	/**
	 * Markdown source of a page the viewer may read.
	 */
	public static function markdown( int $id ): string {
		return self::can_see( $id ) ? (string) flxwk_md( $id ) : '';
	}

	/**
	 * Rendered HTML of a page the viewer may read.
	 */
	public static function html( int $id ): string {
		$p = self::post( $id );
		if ( ! $p || ! self::can_see( $p->ID ) ) {
			return '';
		}
		$r = flxwk_page_html( $p );
		return is_array( $r ) ? (string) ( $r['html'] ?? '' ) : '';
	}

	/**
	 * Pages for the editors' list.
	 *
	 * @param array{search?: string, statuses?: list<string>, section?: string} $filter Filters.
	 * @return array{items: list<FLXWK_Page>, total: int}
	 */
	public static function page_list( array $filter, int $offset, int $limit ): array {
		$statuses = array_values( array_intersect( $filter['statuses'] ?? array(), self::STATUSES ) );
		$args     = array(
			'post_type'           => FLXWK_PT,
			'post_status'         => $statuses ? $statuses : self::STATUSES,
			'posts_per_page'      => max( 1, $limit ),
			'offset'              => max( 0, $offset ),
			'orderby'             => array( 'modified' => 'DESC', 'ID' => 'DESC' ),
			'ignore_sticky_posts' => true,
		);
		$search   = trim( $filter['search'] ?? '' );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		$section = sanitize_title( $filter['section'] ?? '' );
		if ( '' !== $section ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => FLXWK_TAX,
					'field'    => 'slug',
					'terms'    => $section,
				),
			);
		}
		$q     = new WP_Query( $args );
		$items = array();
		foreach ( $q->posts as $p ) {
			if ( $p instanceof WP_Post ) {
				$items[] = self::page_of_post( $p );
			}
		}
		return array(
			'items' => $items,
			'total' => (int) $q->found_posts,
		);
	}

	/**
	 * Sections in menu order.
	 *
	 * @return list<FLXWK_Section>
	 */
	public static function sections(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => FLXWK_TAX,
				'hide_empty' => false,
			)
		);
		$out   = array();
		foreach ( is_array( $terms ) ? $terms : array() as $t ) {
			if ( $t instanceof WP_Term ) {
				$out[] = self::section_of_term( $t );
			}
		}
		usort( $out, static fn( FLXWK_Section $a, FLXWK_Section $b ): int => $a->order === $b->order ? strcmp( $a->name, $b->name ) : $a->order <=> $b->order );
		return $out;
	}

	/**
	 * The public tree: sections with their published pages in reading order (the sidebar of the wiki).
	 *
	 * @return list<FLXWK_Tree_Group>
	 */
	public static function tree(): array {
		$groups = array();
		foreach ( (array) flxwk_tree() as $g ) {
			if ( ! is_array( $g ) ) {
				continue;
			}
			$entries = array();
			$walk    = static function ( array $nodes, int $depth ) use ( &$walk, &$entries ): void {
				foreach ( $nodes as $n ) {
					if ( ! is_array( $n ) || ! ( $n['post'] ?? null ) instanceof WP_Post ) {
						continue;
					}
					$entries[] = new FLXWK_Tree_Entry( self::page_of_post( $n['post'] ), $depth );
					$walk( is_array( $n['children'] ?? null ) ? $n['children'] : array(), $depth + 1 );
				}
			};
			$walk( is_array( $g['items'] ?? null ) ? $g['items'] : array(), 0 );
			$term     = $g['term'] ?? null;
			$groups[] = new FLXWK_Tree_Group( $term instanceof WP_Term ? self::section_of_term( $term ) : null, $entries );
		}
		return $groups;
	}

	/**
	 * Creates or updates a page from Markdown. Same rules for GraphQL and MCP.
	 *
	 * Keys: id, title, markdown, slug, parent (page id, 0 = top level), section (name or slug, '' = none),
	 * summary, status (publish|draft|pending|private), menu_order. A missing key leaves the value as it is.
	 *
	 * @param array{id?: int, title?: string, markdown?: string, slug?: string, parent?: int|string, section?: string, summary?: string, status?: string, menu_order?: int} $in Input.
	 * @return FLXWK_Page|WP_Error
	 */
	public static function save( array $in ) {
		$id = (int) ( $in['id'] ?? 0 );
		if ( $id > 0 ) {
			if ( ! self::post( $id ) ) {
				return new WP_Error( 'flxwk_not_found', __( 'Wiki page not found.', 'flx-wiki' ), array( 'graphql_code' => 'NOT_FOUND', 'field' => 'id' ) );
			}
			if ( ! current_user_can( 'edit_post', $id ) ) {
				return new WP_Error( 'flxwk_forbidden', __( 'You cannot edit this page.', 'flx-wiki' ), array( 'graphql_code' => 'FORBIDDEN' ) );
			}
		} elseif ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'flxwk_forbidden', __( 'You cannot edit this page.', 'flx-wiki' ), array( 'graphql_code' => 'FORBIDDEN' ) );
		}
		$args = array();
		foreach ( array( 'id', 'title', 'slug', 'parent', 'section', 'status', 'menu_order' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$args[ $k ] = $in[ $k ];
			}
		}
		if ( isset( $in['summary'] ) ) {
			$args['excerpt'] = $in['summary'];
		}
		if ( isset( $in['markdown'] ) ) {
			$args['md'] = $in['markdown'];
		}
		if ( 0 === $id && ( ! isset( $args['title'] ) || '' === trim( (string) $args['title'] ) ) ) {
			return new WP_Error( 'flxwk_title', __( 'A title is required.', 'flx-wiki' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'title' ) );
		}
		if ( isset( $args['parent'] ) && (int) $args['parent'] > 0 && ctype_digit( (string) $args['parent'] ) ) {
			$parent = self::post( (int) $args['parent'] );
			if ( ! $parent ) {
				return new WP_Error( 'flxwk_parent', __( 'The parent page was not found.', 'flx-wiki' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'parent' ) );
			}
			if ( $id > 0 && ( $parent->ID === $id || in_array( $id, get_post_ancestors( $parent ), true ) ) ) {
				return new WP_Error( 'flxwk_parent_loop', __( 'A page cannot be placed under itself.', 'flx-wiki' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'parent' ) );
			}
		}
		if ( isset( $args['status'] ) && ! in_array( $args['status'], self::STATUSES, true ) ) {
			return new WP_Error( 'flxwk_status', __( 'This status is not available.', 'flx-wiki' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'status' ) );
		}
		if ( isset( $args['status'] ) && 'publish' === $args['status'] && ! current_user_can( 'publish_posts' ) ) {
			$args['status'] = 'pending';
		}
		$saved = flxwk_save_page( $args );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$p = self::post( (int) $saved );
		return $p ? self::page_of_post( $p ) : new WP_Error( 'flxwk_not_found', __( 'Wiki page not found.', 'flx-wiki' ), array( 'graphql_code' => 'NOT_FOUND' ) );
	}

	/**
	 * Moves a page to the trash.
	 *
	 * @return true|WP_Error
	 */
	public static function trash( int $id ) {
		if ( ! self::post( $id ) ) {
			return new WP_Error( 'flxwk_not_found', __( 'Wiki page not found.', 'flx-wiki' ), array( 'graphql_code' => 'NOT_FOUND', 'field' => 'id' ) );
		}
		if ( ! current_user_can( 'delete_post', $id ) ) {
			return new WP_Error( 'flxwk_forbidden', __( 'You cannot delete this page.', 'flx-wiki' ), array( 'graphql_code' => 'FORBIDDEN' ) );
		}
		if ( ! wp_trash_post( $id ) ) {
			return new WP_Error( 'flxwk_trash', __( 'The page could not be deleted.', 'flx-wiki' ), array( 'graphql_code' => 'INTERNAL' ) );
		}
		flxwk_bump();
		return true;
	}

	/**
	 * Creates or renames a section and sets its order.
	 *
	 * @param int      $id    Term id, 0 to create.
	 * @param string   $name  Section name.
	 * @param int|null $order Sort order (null: keep, 0 for a new section).
	 * @return FLXWK_Section|WP_Error
	 */
	public static function section_save( int $id, string $name, ?int $order ) {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return new WP_Error( 'flxwk_forbidden', __( 'You cannot manage sections.', 'flx-wiki' ), array( 'graphql_code' => 'FORBIDDEN' ) );
		}
		$name = trim( sanitize_text_field( $name ) );
		if ( '' === $name ) {
			return new WP_Error( 'flxwk_section_name', __( 'A section name is required.', 'flx-wiki' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'name' ) );
		}
		if ( $id > 0 ) {
			$t = get_term( $id, FLXWK_TAX );
			if ( ! $t instanceof WP_Term ) {
				return new WP_Error( 'flxwk_not_found', __( 'Section not found.', 'flx-wiki' ), array( 'graphql_code' => 'NOT_FOUND', 'field' => 'id' ) );
			}
			$r = wp_update_term( $id, FLXWK_TAX, array( 'name' => $name ) );
		} else {
			$r = wp_insert_term( $name, FLXWK_TAX );
		}
		if ( is_wp_error( $r ) ) {
			return new WP_Error( 'flxwk_section_exists', $r->get_error_message(), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'name' ) );
		}
		$term_id = (int) $r['term_id'];
		if ( null !== $order ) {
			update_term_meta( $term_id, 'flxwk_order', $order );
		}
		flxwk_bump();
		$t = get_term( $term_id, FLXWK_TAX );
		return $t instanceof WP_Term ? self::section_of_term( $t ) : new WP_Error( 'flxwk_not_found', __( 'Section not found.', 'flx-wiki' ), array( 'graphql_code' => 'NOT_FOUND' ) );
	}

	/**
	 * Deletes a section; its pages stay and move to "no section".
	 *
	 * @return true|WP_Error
	 */
	public static function section_delete( int $id ) {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return new WP_Error( 'flxwk_forbidden', __( 'You cannot manage sections.', 'flx-wiki' ), array( 'graphql_code' => 'FORBIDDEN' ) );
		}
		if ( ! get_term( $id, FLXWK_TAX ) instanceof WP_Term ) {
			return new WP_Error( 'flxwk_not_found', __( 'Section not found.', 'flx-wiki' ), array( 'graphql_code' => 'NOT_FOUND', 'field' => 'id' ) );
		}
		$r = wp_delete_term( $id, FLXWK_TAX );
		if ( true !== $r ) {
			return new WP_Error( 'flxwk_section_delete', __( 'The section could not be deleted.', 'flx-wiki' ), array( 'graphql_code' => 'INTERNAL' ) );
		}
		flxwk_bump();
		return true;
	}

	/**
	 * Renders Markdown for the editor preview.
	 */
	public static function preview( string $markdown ): FLXWK_Rendered {
		$r   = flxwk_render( $markdown );
		$toc = array();
		foreach ( (array) ( $r['toc'] ?? array() ) as $item ) {
			if ( is_array( $item ) ) {
				$toc[] = new FLXWK_Toc_Item( (int) ( $item['level'] ?? 2 ), (string) ( $item['id'] ?? '' ), (string) ( $item['text'] ?? '' ) );
			}
		}
		return new FLXWK_Rendered( (string) ( $r['html'] ?? '' ), $toc );
	}
}
