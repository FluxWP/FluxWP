<?php
/**
 * Typed values the Flux Wiki services return (GraphQL, REST, MCP, Flux AI and the classic pages read
 * the same shapes).
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

/**
 * A wiki section (top level group of pages).
 */
final class FLXWK_Section {

	/**
	 * @param int    $id          Term ID.
	 * @param string $slug        Short name.
	 * @param string $name        Section name.
	 * @param string $description Description (may be empty).
	 * @param int    $order       Sort order; lower numbers come first.
	 * @param int    $page_count  Published pages in the section.
	 * @param string $url         Section address on the wiki.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $slug,
		public readonly string $name,
		public readonly string $description,
		public readonly int $order,
		public readonly int $page_count,
		public readonly string $url,
	) {}
}

/**
 * A wiki page. The Markdown source and the HTML are read on demand (FLXWK_Page_Service::markdown / html).
 */
class FLXWK_Page {

	/**
	 * @param int               $id           Post ID.
	 * @param string            $title        Title.
	 * @param string            $slug         Short name (last path segment).
	 * @param string            $path         Path below the wiki base, e.g. plugins/ai-enabled.
	 * @param string            $status       publish, draft, pending or private.
	 * @param string            $summary      Hand-written summary (may be empty).
	 * @param string            $excerpt      Summary or the first paragraph.
	 * @param int               $parent_id    Parent page, 0 for a top-level page.
	 * @param int               $menu_order   Sort order inside the parent.
	 * @param FLXWK_Section|null $section     Section of the top-level page.
	 * @param string            $url          Address on the site.
	 * @param string            $markdown_url The .md address; empty for a draft.
	 * @param string            $modified_gmt Last change, GMT "Y-m-d H:i:s".
	 * @param int               $read_minutes Reading time in minutes.
	 * @param string            $edit_url     WordPress edit screen of the page (empty without permission).
	 * @param bool              $can_edit     Whether the viewer may edit the page.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly string $slug,
		public readonly string $path,
		public readonly string $status,
		public readonly string $summary,
		public readonly string $excerpt,
		public readonly int $parent_id,
		public readonly int $menu_order,
		public readonly ?FLXWK_Section $section,
		public readonly string $url,
		public readonly string $markdown_url,
		public readonly string $modified_gmt,
		public readonly int $read_minutes,
		public readonly string $edit_url,
		public readonly bool $can_edit,
	) {}
}

/**
 * One page of the tree with its depth (0 = top level), in reading order.
 */
final class FLXWK_Tree_Entry {

	/**
	 * @param FLXWK_Page $page  The page.
	 * @param int        $depth Depth.
	 */
	public function __construct(
		public readonly FLXWK_Page $page,
		public readonly int $depth,
	) {}
}

/**
 * A section with its pages (section null = pages without a section).
 */
final class FLXWK_Tree_Group {

	/**
	 * @param FLXWK_Section|null  $section Section.
	 * @param list<FLXWK_Tree_Entry> $pages Pages in reading order.
	 */
	public function __construct(
		public readonly ?FLXWK_Section $section,
		public readonly array $pages,
	) {}
}

/**
 * A search hit.
 */
final class FLXWK_Search_Hit {

	/**
	 * @param int    $page_id Page ID.
	 * @param string $title   Page title.
	 * @param string $url     Address (with #heading when a heading matched).
	 * @param string $section Section name (may be empty).
	 * @param string $heading Best matching heading (may be empty).
	 * @param string $snippet Snippet with <mark> around the matches.
	 * @param float  $score   Rank.
	 */
	public function __construct(
		public readonly int $page_id,
		public readonly string $title,
		public readonly string $url,
		public readonly string $section,
		public readonly string $heading,
		public readonly string $snippet,
		public readonly float $score,
	) {}
}

/**
 * A search result.
 */
final class FLXWK_Search_Result {

	/**
	 * @param int                     $total Matching pages.
	 * @param list<FLXWK_Search_Hit>  $hits  Best hits.
	 * @param string|null             $fixed The corrected query when a typo was fixed.
	 */
	public function __construct(
		public readonly int $total,
		public readonly array $hits,
		public readonly ?string $fixed,
	) {}
}

/**
 * A heading of the contents list.
 */
final class FLXWK_Toc_Item {

	/**
	 * @param int    $level Heading level (2 or 3).
	 * @param string $id    Anchor id.
	 * @param string $text  Heading text.
	 */
	public function __construct(
		public readonly int $level,
		public readonly string $id,
		public readonly string $text,
	) {}
}

/**
 * Markdown rendered to HTML.
 */
final class FLXWK_Rendered {

	/**
	 * @param string              $html Sanitized HTML.
	 * @param list<FLXWK_Toc_Item> $toc  Contents list.
	 */
	public function __construct(
		public readonly string $html,
		public readonly array $toc,
	) {}
}

/**
 * An answer of "Ask the wiki".
 */
final class FLXWK_Answer {

	/**
	 * @param string                                    $answer  Markdown answer.
	 * @param string                                    $html    Sanitized HTML answer.
	 * @param list<array{title: string, url: string}>   $sources Pages the answer cites.
	 */
	public function __construct(
		public readonly string $answer,
		public readonly string $html,
		public readonly array $sources,
	) {}
}

/**
 * Counts of a guide import.
 */
final class FLXWK_Import_Result {

	/**
	 * @param int $created Pages created.
	 * @param int $updated Pages updated.
	 * @param int $skipped Pages skipped (edited by hand or up to date).
	 */
	public function __construct(
		public readonly int $created,
		public readonly int $updated,
		public readonly int $skipped,
	) {}
}

/**
 * What the settings screen shows besides the settings: addresses, AI and MCP state, guide counts.
 */
final class FLXWK_Overview {

	/**
	 * @param string       $home_url       Wiki address.
	 * @param string       $site_url       Site address (prefix of the base field).
	 * @param string       $base           Address base, e.g. wiki.
	 * @param string       $llms_url       llms.txt address.
	 * @param string       $llms_full_url  llms-full.txt address.
	 * @param string       $search_api     REST search address.
	 * @param bool         $mcp_enabled    Flux AI MCP is on.
	 * @param list<string> $mcp_tools      MCP tool names the wiki adds.
	 * @param bool         $ai_installed   Flux AI is installed.
	 * @param bool         $ai_ready       Flux AI has a key and the Flux Wiki system is on.
	 * @param int          $guide_count    Bundled guides.
	 * @param int          $published      Published pages.
	 * @param string       $default_title  Wiki name when the setting is empty.
	 * @param string       $default_intro  Intro when the setting is empty.
	 * @param string       $new_page_url   WordPress screen that adds a page.
	 */
	public function __construct(
		public readonly string $home_url,
		public readonly string $site_url,
		public readonly string $base,
		public readonly string $llms_url,
		public readonly string $llms_full_url,
		public readonly string $search_api,
		public readonly bool $mcp_enabled,
		public readonly array $mcp_tools,
		public readonly bool $ai_installed,
		public readonly bool $ai_ready,
		public readonly int $guide_count,
		public readonly int $published,
		public readonly string $default_title,
		public readonly string $default_intro,
		public readonly string $new_page_url,
	) {}
}
