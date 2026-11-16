<?php
/**
 * Flux Wiki GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a GraphQL
 * request. Resolvers call the FLXWK_* services; the Wiki screen (wp-admin and the FluxWP app) and the
 * visitor-facing reads (tree, page, search, ask) use only these fields.
 *
 * Settings are not typed by hand: the `flxwkSettings` group of flx_settings_schema shows up under
 * `flxSettings.wiki` / `flxSettingsUpdate(input: { wiki: … })`.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

/**
 * A wiki page that is also a GraphQL Node (global ID, node(id:) lookup).
 */
final class FLXWK_Page_Node extends FLXWK_Page implements FLXGQL_Node {

	/**
	 * Node of a page value.
	 */
	public static function of( FLXWK_Page $p ): self {
		return new self( $p->id, $p->title, $p->slug, $p->path, $p->status, $p->summary, $p->excerpt, $p->parent_id, $p->menu_order, $p->section, $p->url, $p->markdown_url, $p->modified_gmt, $p->read_minutes, $p->edit_url, $p->can_edit );
	}

	#[\Override]
	public function gql_type(): string {
		return 'FlxWikiPage';
	}

	#[\Override]
	public function gql_id(): int {
		return $this->id;
	}
}

/**
 * Batches page lookups of one request; hides pages the viewer may not read.
 */
final class FLXWK_Page_Loader extends FLXGQL_Loader {

	/**
	 * @param array<int,int> $ids Ids.
	 * @return array<int,FLXWK_Page_Node>
	 */
	#[\Override]
	protected function fetch( array $ids ): array {
		$out = array();
		foreach ( FLXWK_Page_Service::by_ids( array_values( $ids ) ) as $id => $page ) {
			$out[ $id ] = FLXWK_Page_Node::of( $page );
		}
		return $out;
	}

	/**
	 * @param mixed $record Record.
	 */
	#[\Override]
	protected function can_see( $record ): bool {
		return $record instanceof FLXWK_Page && FLXWK_Page_Service::can_see( $record->id );
	}
}

/**
 * Service result → value, or the WP_Error thrown as a typed GraphQL error.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxwk_gql_ok( $r ) {
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
function flxwk_gql_src( $source, string $class ) {
	if ( ! $source instanceof $class ) {
		throw flx_gql_error( 'INTERNAL' );
	}
	return $source;
}

/**
 * Whether the viewer can read the wiki (open to visitors, or the viewer is an editor).
 */
function flxwk_gql_can_read(): bool {
	return (bool) flxwk_visible();
}

/**
 * Tags a public read for the GraphQL cache (cleared by flxwk_bump()).
 */
function flxwk_gql_tag(): void {
	flx_gql_cache_tag( FLXWK_Page_Service::CACHE_TAG );
}

/**
 * Optional string argument; null when absent.
 *
 * @param array<string,mixed> $in  Input.
 * @param string              $key Key.
 */
function flxwk_gql_opt_str( array $in, string $key ): ?string {
	return array_key_exists( $key, $in ) && null !== $in[ $key ] ? flx_gql_str( $in[ $key ] ) : null;
}

flx_gql_enum(
	'FlxWikiPageStatus',
	array(
		'description' => __( 'Publishing state of a wiki page.', 'flx-wiki' ),
		'values'      => array(
			'PUBLISH' => array(
				'value'       => 'publish',
				'description' => __( 'Published.', 'flx-wiki' ),
			),
			'DRAFT'   => array(
				'value'       => 'draft',
				'description' => __( 'Draft; only editors see it.', 'flx-wiki' ),
			),
			'PENDING' => array(
				'value'       => 'pending',
				'description' => __( 'Waiting for review.', 'flx-wiki' ),
			),
			'PRIVATE' => array(
				'value'       => 'private',
				'description' => __( 'Private; only people who may read private pages see it.', 'flx-wiki' ),
			),
		),
	)
);

flx_gql_type(
	'FlxWikiSection',
	array(
		'description' => __( 'A wiki section: a group of top-level pages on the wiki home and in the menu.', 'flx-wiki' ),
		'fields'      => array(
			'databaseId'  => array(
				'type'        => 'Int!',
				'description' => __( 'Section ID.', 'flx-wiki' ),
				'resolve'     => static fn( $s ): int => flxwk_gql_src( $s, FLXWK_Section::class )->id,
			),
			'slug'        => array(
				'type'        => 'String!',
				'description' => __( 'Short name used in addresses and the section filter.', 'flx-wiki' ),
				'resolve'     => static fn( $s ): string => flxwk_gql_src( $s, FLXWK_Section::class )->slug,
			),
			'name'        => array(
				'type'        => 'String!',
				'description' => __( 'Section name.', 'flx-wiki' ),
				'resolve'     => static fn( $s ): string => flxwk_gql_src( $s, FLXWK_Section::class )->name,
			),
			'description' => array(
				'type'        => 'String!',
				'description' => __( 'Short description; empty when none is written.', 'flx-wiki' ),
				'resolve'     => static fn( $s ): string => flxwk_gql_src( $s, FLXWK_Section::class )->description,
			),
			'order'       => array(
				'type'        => 'Int!',
				'description' => __( 'Sort order; lower numbers come first.', 'flx-wiki' ),
				'resolve'     => static fn( $s ): int => flxwk_gql_src( $s, FLXWK_Section::class )->order,
			),
			'pageCount'   => array(
				'type'        => 'Int!',
				'description' => __( 'Published pages in the section.', 'flx-wiki' ),
				'resolve'     => static fn( $s ): int => flxwk_gql_src( $s, FLXWK_Section::class )->page_count,
			),
			'url'         => array(
				'type'        => 'String!',
				'description' => __( 'Section address on the wiki.', 'flx-wiki' ),
				'resolve'     => static fn( $s ): string => flxwk_gql_src( $s, FLXWK_Section::class )->url,
			),
		),
	)
);

flx_gql_type(
	'FlxWikiPage',
	array(
		'description' => __( 'A wiki page.', 'flx-wiki' ),
		'node'        => FLXWK_Page_Loader::class,
		'fields'      => array(
			'title'        => array(
				'type'        => 'String!',
				'description' => __( 'Page title.', 'flx-wiki' ),
				'resolve'     => static fn( $p ): string => flxwk_gql_src( $p, FLXWK_Page::class )->title,
			),
			'slug'         => array(
				'type'        => 'String!',
				'description' => __( 'Short name (last part of the path); empty for a draft without one.', 'flx-wiki' ),
				'resolve'     => static fn( $p ): string => flxwk_gql_src( $p, FLXWK_Page::class )->slug,
			),
			'path'         => array(
				'type'        => 'String!',
				'description' => __( 'Path below the wiki address, e.g. plugins/ai-enabled; empty for a draft without a short name.', 'flx-wiki' ),
				'resolve'     => static fn( $p ): string => flxwk_gql_src( $p, FLXWK_Page::class )->path,
			),
			'status'       => array(
				'type'        => 'FlxWikiPageStatus!',
				'description' => __( 'Publishing state.', 'flx-wiki' ),
				'resolve'     => static fn( $p ): string => flxwk_gql_src( $p, FLXWK_Page::class )->status,
			),
			'summary'      => array(
				'type'        => 'String!',
				'description' => __( 'Hand-written summary (search engine description); empty when the first paragraph is used.', 'flx-wiki' ),
				'resolve'     => static fn( $p ): string => flxwk_gql_src( $p, FLXWK_Page::class )->summary,
			),
			'excerpt'      => array(
				'type'        => 'String!',
				'description' => __( 'The summary, or the first paragraph, shortened.', 'flx-wiki' ),
				'resolve'     => static fn( $p ): string => flxwk_gql_src( $p, FLXWK_Page::class )->excerpt,
			),
			'parentId'     => array(
				'type'        => 'Int',
				'description' => __( 'Parent page; empty for a top-level page.', 'flx-wiki' ),
				'resolve'     => static fn( $p ): ?int => flxwk_gql_src( $p, FLXWK_Page::class )->parent_id > 0 ? flxwk_gql_src( $p, FLXWK_Page::class )->parent_id : null,
			),
			'menuOrder'    => array(
				'type'        => 'Int!',
				'description' => __( 'Sort order inside the parent; lower numbers come first.', 'flx-wiki' ),
				'resolve'     => static fn( $p ): int => flxwk_gql_src( $p, FLXWK_Page::class )->menu_order,
			),
			'section'      => array(
				'type'        => 'FlxWikiSection',
				'description' => __( 'Section of the top-level page this page belongs to; empty when it has none.', 'flx-wiki' ),
				'resolve'     => static fn( $p ): ?FLXWK_Section => flxwk_gql_src( $p, FLXWK_Page::class )->section,
			),
			'url'          => array(
				'type'        => 'String!',
				'description' => __( 'Page address on the site.', 'flx-wiki' ),
				'resolve'     => static fn( $p ): string => flxwk_gql_src( $p, FLXWK_Page::class )->url,
			),
			'markdownUrl'  => array(
				'type'        => 'String',
				'description' => __( 'Address of the page as Markdown (.md); empty for a draft.', 'flx-wiki' ),
				'resolve'     => static fn( $p ): ?string => '' === flxwk_gql_src( $p, FLXWK_Page::class )->markdown_url ? null : flxwk_gql_src( $p, FLXWK_Page::class )->markdown_url,
			),
			'modified'     => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'Last change.', 'flx-wiki' ),
				'resolve'     => static fn( $p ): ?array => flx_gql_datetime( flxwk_gql_src( $p, FLXWK_Page::class )->modified_gmt ),
			),
			'readMinutes'  => array(
				'type'        => 'Int!',
				'description' => __( 'Reading time in minutes.', 'flx-wiki' ),
				'resolve'     => static fn( $p ): int => flxwk_gql_src( $p, FLXWK_Page::class )->read_minutes,
			),
			'editUrl'      => array(
				'type'        => 'String',
				'description' => __( 'WordPress edit screen of the page; empty when the viewer may not edit it.', 'flx-wiki' ),
				'resolve'     => static fn( $p ): ?string => '' === flxwk_gql_src( $p, FLXWK_Page::class )->edit_url ? null : flxwk_gql_src( $p, FLXWK_Page::class )->edit_url,
			),
			'canEdit'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'Whether the viewer may edit the page.', 'flx-wiki' ),
				'resolve'     => static fn( $p ): bool => flxwk_gql_src( $p, FLXWK_Page::class )->can_edit,
			),
			'markdown'     => array(
				'type'        => 'String!',
				'description' => __( 'Markdown source of the page.', 'flx-wiki' ),
				'resolve'     => static fn( $p ): string => FLXWK_Page_Service::markdown( flxwk_gql_src( $p, FLXWK_Page::class )->id ),
			),
			'html'         => array(
				'type'        => 'String!',
				'description' => __( 'The page rendered to sanitized HTML (the same markup the wiki shows).', 'flx-wiki' ),
				'resolve'     => static fn( $p ): string => FLXWK_Page_Service::html( flxwk_gql_src( $p, FLXWK_Page::class )->id ),
			),
		),
	)
);
flx_gql_connection( 'FlxWikiPage', __( 'A page of wiki pages.', 'flx-wiki' ) );

flx_gql_type(
	'FlxWikiTreeEntry',
	array(
		'description' => __( 'One page of the tree with its depth.', 'flx-wiki' ),
		'fields'      => array(
			'page'  => array(
				'type'        => 'FlxWikiPage!',
				'description' => __( 'The page.', 'flx-wiki' ),
				'resolve'     => static fn( $e ): FLXWK_Page_Node => FLXWK_Page_Node::of( flxwk_gql_src( $e, FLXWK_Tree_Entry::class )->page ),
			),
			'depth' => array(
				'type'        => 'Int!',
				'description' => __( 'Depth in the tree: 0 for a top-level page.', 'flx-wiki' ),
				'resolve'     => static fn( $e ): int => flxwk_gql_src( $e, FLXWK_Tree_Entry::class )->depth,
			),
		),
	)
);

flx_gql_type(
	'FlxWikiTreeGroup',
	array(
		'description' => __( 'A section with its pages in reading order.', 'flx-wiki' ),
		'fields'      => array(
			'section' => array(
				'type'        => 'FlxWikiSection',
				'description' => __( 'The section; empty for the pages that have none.', 'flx-wiki' ),
				'resolve'     => static fn( $g ): ?FLXWK_Section => flxwk_gql_src( $g, FLXWK_Tree_Group::class )->section,
			),
			'pages'   => array(
				'type'        => '[FlxWikiTreeEntry!]!',
				'description' => __( 'Published pages: each parent comes before its children.', 'flx-wiki' ),
				'resolve'     => static fn( $g ): array => flxwk_gql_src( $g, FLXWK_Tree_Group::class )->pages,
			),
		),
	)
);

flx_gql_type(
	'FlxWikiSearchHit',
	array(
		'description' => __( 'A wiki search hit.', 'flx-wiki' ),
		'fields'      => array(
			'pageId'  => array(
				'type'        => 'Int!',
				'description' => __( 'ID of the page that matched.', 'flx-wiki' ),
				'resolve'     => static fn( $h ): int => flxwk_gql_src( $h, FLXWK_Search_Hit::class )->page_id,
			),
			'title'   => array(
				'type'        => 'String!',
				'description' => __( 'Page title.', 'flx-wiki' ),
				'resolve'     => static fn( $h ): string => flxwk_gql_src( $h, FLXWK_Search_Hit::class )->title,
			),
			'url'     => array(
				'type'        => 'String!',
				'description' => __( 'Address of the page, with #heading when a heading matched.', 'flx-wiki' ),
				'resolve'     => static fn( $h ): string => flxwk_gql_src( $h, FLXWK_Search_Hit::class )->url,
			),
			'section' => array(
				'type'        => 'String!',
				'description' => __( 'Section name; empty when the page has none.', 'flx-wiki' ),
				'resolve'     => static fn( $h ): string => flxwk_gql_src( $h, FLXWK_Search_Hit::class )->section,
			),
			'heading' => array(
				'type'        => 'String!',
				'description' => __( 'Best matching heading; empty when the match is in the introduction.', 'flx-wiki' ),
				'resolve'     => static fn( $h ): string => flxwk_gql_src( $h, FLXWK_Search_Hit::class )->heading,
			),
			'snippet' => array(
				'type'        => 'String!',
				'description' => __( 'Excerpt around the match. Text is escaped; matches are wrapped in <mark>.', 'flx-wiki' ),
				'resolve'     => static fn( $h ): string => flxwk_gql_src( $h, FLXWK_Search_Hit::class )->snippet,
			),
			'score'   => array(
				'type'        => 'Float!',
				'description' => __( 'Rank; higher is better.', 'flx-wiki' ),
				'resolve'     => static fn( $h ): float => flxwk_gql_src( $h, FLXWK_Search_Hit::class )->score,
			),
		),
	)
);

flx_gql_type(
	'FlxWikiSearchResult',
	array(
		'description' => __( 'Result of a wiki search.', 'flx-wiki' ),
		'fields'      => array(
			'total' => array(
				'type'        => 'Int!',
				'description' => __( 'Pages that match.', 'flx-wiki' ),
				'resolve'     => static fn( $r ): int => flxwk_gql_src( $r, FLXWK_Search_Result::class )->total,
			),
			'hits'  => array(
				'type'        => '[FlxWikiSearchHit!]!',
				'description' => __( 'Best matches first.', 'flx-wiki' ),
				'resolve'     => static fn( $r ): array => flxwk_gql_src( $r, FLXWK_Search_Result::class )->hits,
			),
			'fixed' => array(
				'type'        => 'String',
				'description' => __( 'The corrected words when a typo was fixed (the hits are for these words); empty otherwise.', 'flx-wiki' ),
				'resolve'     => static fn( $r ): ?string => flxwk_gql_src( $r, FLXWK_Search_Result::class )->fixed,
			),
		),
	)
);

flx_gql_type(
	'FlxWikiTocItem',
	array(
		'description' => __( 'A heading of the contents list.', 'flx-wiki' ),
		'fields'      => array(
			'level' => array(
				'type'        => 'Int!',
				'description' => __( 'Heading level (2 or 3).', 'flx-wiki' ),
				'resolve'     => static fn( $i ): int => flxwk_gql_src( $i, FLXWK_Toc_Item::class )->level,
			),
			'id'    => array(
				'type'        => 'String!',
				'description' => __( 'Anchor id of the heading.', 'flx-wiki' ),
				'resolve'     => static fn( $i ): string => flxwk_gql_src( $i, FLXWK_Toc_Item::class )->id,
			),
			'text'  => array(
				'type'        => 'String!',
				'description' => __( 'Heading text.', 'flx-wiki' ),
				'resolve'     => static fn( $i ): string => flxwk_gql_src( $i, FLXWK_Toc_Item::class )->text,
			),
		),
	)
);

flx_gql_type(
	'FlxWikiRendered',
	array(
		'description' => __( 'Markdown rendered like the wiki renders a page.', 'flx-wiki' ),
		'fields'      => array(
			'html' => array(
				'type'        => 'String!',
				'description' => __( 'Sanitized HTML.', 'flx-wiki' ),
				'resolve'     => static fn( $r ): string => flxwk_gql_src( $r, FLXWK_Rendered::class )->html,
			),
			'toc'  => array(
				'type'        => '[FlxWikiTocItem!]!',
				'description' => __( 'Contents list (level 2 and 3 headings).', 'flx-wiki' ),
				'resolve'     => static fn( $r ): array => flxwk_gql_src( $r, FLXWK_Rendered::class )->toc,
			),
		),
	)
);

flx_gql_type(
	'FlxWikiAnswerSource',
	array(
		'description' => __( 'A wiki page an answer cites.', 'flx-wiki' ),
		'fields'      => array(
			'title' => array(
				'type'        => 'String!',
				'description' => __( 'Page title.', 'flx-wiki' ),
			),
			'url'   => array(
				'type'        => 'String!',
				'description' => __( 'Page address.', 'flx-wiki' ),
			),
		),
	)
);

flx_gql_type(
	'FlxWikiAnswer',
	array(
		'description' => __( 'An answer of "Ask the wiki".', 'flx-wiki' ),
		'fields'      => array(
			'answer'  => array(
				'type'        => 'String!',
				'description' => __( 'The answer in Markdown.', 'flx-wiki' ),
				'resolve'     => static fn( $a ): string => flxwk_gql_src( $a, FLXWK_Answer::class )->answer,
			),
			'html'    => array(
				'type'        => 'String!',
				'description' => __( 'The answer as sanitized HTML; only links to the cited wiki pages stay clickable.', 'flx-wiki' ),
				'resolve'     => static fn( $a ): string => flxwk_gql_src( $a, FLXWK_Answer::class )->html,
			),
			'sources' => array(
				'type'        => '[FlxWikiAnswerSource!]!',
				'description' => __( 'Pages the answer is based on.', 'flx-wiki' ),
				'resolve'     => static fn( $a ): array => flxwk_gql_src( $a, FLXWK_Answer::class )->sources,
			),
		),
	)
);

flx_gql_type(
	'FlxWikiImportResult',
	array(
		'description' => __( 'Counts of a bundled guide import.', 'flx-wiki' ),
		'fields'      => array(
			'created' => array(
				'type'        => 'Int!',
				'description' => __( 'Pages created.', 'flx-wiki' ),
				'resolve'     => static fn( $r ): int => flxwk_gql_src( $r, FLXWK_Import_Result::class )->created,
			),
			'updated' => array(
				'type'        => 'Int!',
				'description' => __( 'Pages updated.', 'flx-wiki' ),
				'resolve'     => static fn( $r ): int => flxwk_gql_src( $r, FLXWK_Import_Result::class )->updated,
			),
			'skipped' => array(
				'type'        => 'Int!',
				'description' => __( 'Pages left as they are (edited by hand or already up to date).', 'flx-wiki' ),
				'resolve'     => static fn( $r ): int => flxwk_gql_src( $r, FLXWK_Import_Result::class )->skipped,
			),
		),
	)
);

flx_gql_type(
	'FlxWikiOverview',
	array(
		'description' => __( 'Addresses, AI state and counts shown next to the wiki settings.', 'flx-wiki' ),
		'fields'      => array(
			'homeUrl'      => array(
				'type'        => 'String!',
				'description' => __( 'Wiki address.', 'flx-wiki' ),
				'resolve'     => static fn( $o ): string => flxwk_gql_src( $o, FLXWK_Overview::class )->home_url,
			),
			'siteUrl'      => array(
				'type'        => 'String!',
				'description' => __( 'Site address; the wiki base comes after it.', 'flx-wiki' ),
				'resolve'     => static fn( $o ): string => flxwk_gql_src( $o, FLXWK_Overview::class )->site_url,
			),
			'base'         => array(
				'type'        => 'String!',
				'description' => __( 'Address base of the wiki, e.g. wiki.', 'flx-wiki' ),
				'resolve'     => static fn( $o ): string => flxwk_gql_src( $o, FLXWK_Overview::class )->base,
			),
			'llmsUrl'      => array(
				'type'        => 'String!',
				'description' => __( 'llms.txt address.', 'flx-wiki' ),
				'resolve'     => static fn( $o ): string => flxwk_gql_src( $o, FLXWK_Overview::class )->llms_url,
			),
			'llmsFullUrl'  => array(
				'type'        => 'String!',
				'description' => __( 'llms-full.txt address.', 'flx-wiki' ),
				'resolve'     => static fn( $o ): string => flxwk_gql_src( $o, FLXWK_Overview::class )->llms_full_url,
			),
			'searchApi'    => array(
				'type'        => 'String!',
				'description' => __( 'REST search address.', 'flx-wiki' ),
				'resolve'     => static fn( $o ): string => flxwk_gql_src( $o, FLXWK_Overview::class )->search_api,
			),
			'mcpEnabled'   => array(
				'type'        => 'Boolean!',
				'description' => __( 'Flux AI MCP is on.', 'flx-wiki' ),
				'resolve'     => static fn( $o ): bool => flxwk_gql_src( $o, FLXWK_Overview::class )->mcp_enabled,
			),
			'mcpTools'     => array(
				'type'        => '[String!]!',
				'description' => __( 'MCP tools the wiki adds.', 'flx-wiki' ),
				'resolve'     => static fn( $o ): array => flxwk_gql_src( $o, FLXWK_Overview::class )->mcp_tools,
			),
			'aiInstalled'  => array(
				'type'        => 'Boolean!',
				'description' => __( 'Flux AI is installed.', 'flx-wiki' ),
				'resolve'     => static fn( $o ): bool => flxwk_gql_src( $o, FLXWK_Overview::class )->ai_installed,
			),
			'aiReady'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'Flux AI has a key and the Flux Wiki system is on.', 'flx-wiki' ),
				'resolve'     => static fn( $o ): bool => flxwk_gql_src( $o, FLXWK_Overview::class )->ai_ready,
			),
			'guideCount'   => array(
				'type'        => 'Int!',
				'description' => __( 'Bundled guides.', 'flx-wiki' ),
				'resolve'     => static fn( $o ): int => flxwk_gql_src( $o, FLXWK_Overview::class )->guide_count,
			),
			'publishedCount' => array(
				'type'        => 'Int!',
				'description' => __( 'Published pages.', 'flx-wiki' ),
				'resolve'     => static fn( $o ): int => flxwk_gql_src( $o, FLXWK_Overview::class )->published,
			),
			'defaultTitle' => array(
				'type'        => 'String!',
				'description' => __( 'Wiki name used when the setting is empty.', 'flx-wiki' ),
				'resolve'     => static fn( $o ): string => flxwk_gql_src( $o, FLXWK_Overview::class )->default_title,
			),
			'defaultIntro' => array(
				'type'        => 'String!',
				'description' => __( 'Intro used when the setting is empty.', 'flx-wiki' ),
				'resolve'     => static fn( $o ): string => flxwk_gql_src( $o, FLXWK_Overview::class )->default_intro,
			),
			'newPageUrl'   => array(
				'type'        => 'String!',
				'description' => __( 'WordPress screen that adds a page.', 'flx-wiki' ),
				'resolve'     => static fn( $o ): string => flxwk_gql_src( $o, FLXWK_Overview::class )->new_page_url,
			),
		),
	)
);

/* ---------------------------------------------------------------- */
/* Queries                                                            */
/* ---------------------------------------------------------------- */

flx_gql_query(
	'flxWikiOverview',
	array(
		'type'        => 'FlxWikiOverview!',
		'description' => __( 'Addresses, AI state and counts of the wiki settings page.', 'flx-wiki' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): FLXWK_Overview => ( new FLXWK_Admin_Service() )->overview(),
	)
);

flx_gql_query(
	'flxWikiPages',
	array(
		'type'        => 'FlxWikiPageConnection!',
		'description' => __( 'Wiki pages of any status for editors, most recently changed first.', 'flx-wiki' ),
		'cap'         => 'edit_posts',
		'args'        => flx_gql_connection_args(
			array(
				'search'   => array(
					'type'        => 'String',
					'description' => __( 'Words to look for in titles and content.', 'flx-wiki' ),
				),
				'statuses' => array(
					'type'        => '[FlxWikiPageStatus!]',
					'description' => __( 'Only pages in these states; all states when empty.', 'flx-wiki' ),
				),
				'section'  => array(
					'type'        => 'String',
					'description' => __( 'Section short name.', 'flx-wiki' ),
				),
			)
		),
		/** @param array<string,mixed> $args */
		'resolve'     => static function ( $root, array $args ): array {
			$filter = array(
				'search'   => flx_gql_str( $args['search'] ?? '' ),
				'section'  => flx_gql_str( $args['section'] ?? '' ),
				'statuses' => array_values( array_map( 'flx_gql_str', flx_gql_arr( $args['statuses'] ?? array() ) ) ),
			);
			return flx_gql_paginate(
				$args,
				static function ( int $offset, int $limit ) use ( $filter ): array {
					$r = FLXWK_Page_Service::page_list( $filter, $offset, $limit );
					return array(
						'items' => array_map( array( 'FLXWK_Page_Node', 'of' ), $r['items'] ),
						'total' => $r['total'],
					);
				}
			);
		},
	)
);

flx_gql_query(
	'flxWikiPage',
	array(
		'type'        => 'FlxWikiPage',
		'description' => __( 'One wiki page by ID or path; empty when it does not exist or the viewer may not read it.', 'flx-wiki' ),
		'cap'         => static fn(): bool => flxwk_gql_can_read(),
		'args'        => array(
			'id'   => array(
				'type'        => 'ID',
				'description' => __( 'Global ID or database ID.', 'flx-wiki' ),
			),
			'path' => array(
				'type'        => 'String',
				'description' => __( 'Path below the wiki address (plugins/ai-enabled), short name or title.', 'flx-wiki' ),
			),
		),
		/** @param array<string,mixed> $args */
		'resolve'     => static function ( $root, array $args ): ?FLXWK_Page_Node {
			flxwk_gql_tag();
			$page = null;
			if ( isset( $args['id'] ) && '' !== $args['id'] ) {
				$page = FLXWK_Page_Service::get( flx_gql_decode_id( $args['id'], 'FlxWikiPage' ) );
			} elseif ( isset( $args['path'] ) && '' !== $args['path'] ) {
				$page = FLXWK_Page_Service::find( flx_gql_str( $args['path'] ) );
			}
			return $page ? FLXWK_Page_Node::of( $page ) : null;
		},
	)
);

flx_gql_query(
	'flxWikiTree',
	array(
		'type'        => '[FlxWikiTreeGroup!]!',
		'description' => __( 'The published wiki as sections with their pages in reading order (the wiki menu).', 'flx-wiki' ),
		'cap'         => static fn(): bool => flxwk_gql_can_read(),
		'resolve'     => static function (): array {
			flxwk_gql_tag();
			return FLXWK_Page_Service::tree();
		},
	)
);

flx_gql_query(
	'flxWikiSections',
	array(
		'type'        => '[FlxWikiSection!]!',
		'description' => __( 'Wiki sections in menu order.', 'flx-wiki' ),
		'cap'         => static fn(): bool => flxwk_gql_can_read(),
		'resolve'     => static function (): array {
			flxwk_gql_tag();
			return FLXWK_Page_Service::sections();
		},
	)
);

flx_gql_query(
	'flxWikiSearch',
	array(
		'type'        => 'FlxWikiSearchResult!',
		'description' => __( 'Searches the wiki. Turkish letters are folded (ı=i, ş=s), simple typos are corrected.', 'flx-wiki' ),
		'cap'         => static fn(): bool => flxwk_gql_can_read(),
		'args'        => array(
			'query'   => array(
				'type'        => 'String!',
				'description' => __( 'Words to search for.', 'flx-wiki' ),
			),
			'section' => array(
				'type'        => 'String',
				'description' => __( 'Section short name to limit the search.', 'flx-wiki' ),
			),
			'limit'   => array(
				'type'        => 'Int',
				'description' => __( 'Best hits to return (1–50, default 10).', 'flx-wiki' ),
			),
		),
		/** @param array<string,mixed> $args */
		'resolve'     => static function ( $root, array $args ): FLXWK_Search_Result {
			flxwk_gql_tag();
			return FLXWK_Search_Service::search( flx_gql_str( $args['query'] ?? '' ), flx_gql_str( $args['section'] ?? '' ), flx_gql_int( $args['limit'] ?? 10, 10 ) );
		},
	)
);

/* ---------------------------------------------------------------- */
/* Mutations                                                          */
/* ---------------------------------------------------------------- */

flx_gql_mutation(
	'flxWikiPageSave',
	array(
		'description' => __( 'Creates a page (without id) or updates one. Fields you leave out stay as they are; a new page is a draft unless a status is given. Editors who cannot publish send a published page for review instead.', 'flx-wiki' ),
		'cap'         => 'edit_posts',
		'input'       => array(
			'id'        => array(
				'type'        => 'ID',
				'description' => __( 'Page to update (global ID or database ID); leave out to create a page.', 'flx-wiki' ),
			),
			'title'     => array(
				'type'        => 'String',
				'description' => __( 'Page title; required for a new page.', 'flx-wiki' ),
			),
			'markdown'  => array(
				'type'        => 'String',
				'description' => __( 'Page body in Markdown. Do not repeat the title as a heading. [[slug]] links to a wiki page, > [!NOTE] makes a callout.', 'flx-wiki' ),
			),
			'slug'      => array(
				'type'        => 'String',
				'description' => __( 'Short name used in the address.', 'flx-wiki' ),
			),
			'parentId'  => array(
				'type'        => 'Int',
				'description' => __( 'Parent page; 0 makes it a top-level page.', 'flx-wiki' ),
			),
			'section'   => array(
				'type'        => 'String',
				'description' => __( 'Section name or short name (created when missing); empty removes the page from its section. Only top-level pages need one.', 'flx-wiki' ),
			),
			'summary'   => array(
				'type'        => 'String',
				'description' => __( 'One-sentence summary used as the search engine description.', 'flx-wiki' ),
			),
			'status'    => array(
				'type'        => 'FlxWikiPageStatus',
				'description' => __( 'Publishing state.', 'flx-wiki' ),
			),
			'menuOrder' => array(
				'type'        => 'Int',
				'description' => __( 'Sort order inside the parent; lower numbers come first.', 'flx-wiki' ),
			),
		),
		'output'      => array(
			'page' => array(
				'type'        => 'FlxWikiPage',
				'description' => __( 'The saved page.', 'flx-wiki' ),
			),
		),
		/** @param array<string,mixed> $input */
		'resolve'     => static function ( array $input ) {
			$in = array();
			if ( isset( $input['id'] ) && '' !== $input['id'] ) {
				$in['id'] = flx_gql_decode_id( $input['id'], 'FlxWikiPage' );
			}
			foreach ( array(
				'title'   => 'title',
				'markdown' => 'markdown',
				'slug'    => 'slug',
				'section' => 'section',
				'summary' => 'summary',
				'status'  => 'status',
			) as $key => $to ) {
				$v = flxwk_gql_opt_str( $input, $key );
				if ( null !== $v ) {
					$in[ $to ] = $v;
				}
			}
			if ( array_key_exists( 'parentId', $input ) && null !== $input['parentId'] ) {
				$in['parent'] = max( 0, flx_gql_int( $input['parentId'] ) );
			}
			if ( array_key_exists( 'menuOrder', $input ) && null !== $input['menuOrder'] ) {
				$in['menu_order'] = flx_gql_int( $input['menuOrder'] );
			}
			$page = FLXWK_Page_Service::save( $in );
			return $page instanceof WP_Error ? $page : array( 'page' => FLXWK_Page_Node::of( $page ) );
		},
	)
);

flx_gql_mutation(
	'flxWikiPageTrash',
	array(
		'description' => __( 'Moves a page to the trash (restorable from the WordPress trash).', 'flx-wiki' ),
		'cap'         => 'edit_posts',
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Page ID (global ID or database ID).', 'flx-wiki' ),
			),
		),
		'output'      => array(
			'trashedId' => array(
				'type'        => 'Int',
				'description' => __( 'Database ID of the page that was moved to the trash.', 'flx-wiki' ),
			),
		),
		/** @param array<string,mixed> $input */
		'resolve'     => static function ( array $input ) {
			$id = flx_gql_decode_id( $input['id'] ?? 0, 'FlxWikiPage' );
			$r  = FLXWK_Page_Service::trash( $id );
			return $r instanceof WP_Error ? $r : array( 'trashedId' => $id );
		},
	)
);

flx_gql_mutation(
	'flxWikiSectionSave',
	array(
		'description' => __( 'Creates a section (without id) or renames one and sets its order.', 'flx-wiki' ),
		'cap'         => 'manage_categories',
		'input'       => array(
			'id'    => array(
				'type'        => 'Int',
				'description' => __( 'Section to change; leave out to create one.', 'flx-wiki' ),
			),
			'name'  => array(
				'type'        => 'String!',
				'description' => __( 'Section name.', 'flx-wiki' ),
			),
			'order' => array(
				'type'        => 'Int',
				'description' => __( 'Sort order; lower numbers come first. Left as it is when omitted.', 'flx-wiki' ),
			),
		),
		'output'      => array(
			'section' => array(
				'type'        => 'FlxWikiSection',
				'description' => __( 'The saved section.', 'flx-wiki' ),
			),
		),
		/** @param array<string,mixed> $input */
		'resolve'     => static function ( array $input ) {
			$order = array_key_exists( 'order', $input ) && null !== $input['order'] ? flx_gql_int( $input['order'] ) : null;
			$s     = FLXWK_Page_Service::section_save( flx_gql_int( $input['id'] ?? 0 ), flx_gql_str( $input['name'] ?? '' ), $order );
			return $s instanceof WP_Error ? $s : array( 'section' => $s );
		},
	)
);

flx_gql_mutation(
	'flxWikiSectionDelete',
	array(
		'description' => __( 'Deletes a section. Its pages stay and move to "no section".', 'flx-wiki' ),
		'cap'         => 'manage_categories',
		'input'       => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Section ID.', 'flx-wiki' ),
			),
		),
		'output'      => array(
			'deletedId' => array(
				'type'        => 'Int',
				'description' => __( 'ID of the section that was deleted.', 'flx-wiki' ),
			),
		),
		/** @param array<string,mixed> $input */
		'resolve'     => static function ( array $input ) {
			$id = flx_gql_int( $input['id'] ?? 0 );
			$r  = FLXWK_Page_Service::section_delete( $id );
			return $r instanceof WP_Error ? $r : array( 'deletedId' => $id );
		},
	)
);

flx_gql_mutation(
	'flxWikiPreview',
	array(
		'description' => __( 'Renders Markdown the way the wiki shows it (editor preview). A mutation only because the text can be long; nothing is saved.', 'flx-wiki' ),
		'cap'         => 'edit_posts',
		'input'       => array(
			'markdown' => array(
				'type'        => 'String!',
				'description' => __( 'Markdown to render.', 'flx-wiki' ),
			),
		),
		'output'      => array(
			'rendered' => array(
				'type'        => 'FlxWikiRendered',
				'description' => __( 'The rendered page.', 'flx-wiki' ),
			),
		),
		'resolve'     => /** @param array<string,mixed> $input */ static fn( array $input ): array => array( 'rendered' => FLXWK_Page_Service::preview( flx_gql_str( $input['markdown'] ?? '' ) ) ),
	)
);

flx_gql_mutation(
	'flxWikiAsk',
	array(
		'description' => __( 'Asks the wiki a question; the answer comes from the wiki pages only (Flux AI). Counts towards the hourly question limit.', 'flx-wiki' ),
		'cap'         => static fn(): bool => FLXWK_Ask_Service::allowed(),
		'input'       => array(
			'question' => array(
				'type'        => 'String!',
				'description' => __( 'The question (3–1000 characters).', 'flx-wiki' ),
			),
			'pageId'   => array(
				'type'        => 'Int',
				'description' => __( 'Page the visitor is reading; its content is used first.', 'flx-wiki' ),
			),
		),
		'output'      => array(
			'answer' => array(
				'type'        => 'FlxWikiAnswer',
				'description' => __( 'The answer.', 'flx-wiki' ),
			),
		),
		/** @param array<string,mixed> $input */
		'resolve'     => static function ( array $input ) {
			$a = FLXWK_Ask_Service::ask( flx_gql_str( $input['question'] ?? '' ), flx_gql_int( $input['pageId'] ?? 0 ) );
			return $a instanceof WP_Error ? $a : array( 'answer' => $a );
		},
	)
);

flx_gql_mutation(
	'flxWikiGuidesImport',
	array(
		'description' => __( 'Imports the bundled guides: missing pages are added, pages nobody edited are updated.', 'flx-wiki' ),
		'cap'         => 'manage_options',
		'output'      => array(
			'result' => array(
				'type'        => 'FlxWikiImportResult',
				'description' => __( 'What was created, updated and skipped.', 'flx-wiki' ),
			),
		),
		'resolve'     => static fn(): array => array( 'result' => ( new FLXWK_Admin_Service() )->import_guides() ),
	)
);
