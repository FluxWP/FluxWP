<?php
/**
 * Typed values the Flux Block Editor services return (GraphQL, REST and the admin screen read these).
 *
 * @package flx-blocks
 */

defined( 'ABSPATH' ) || exit;

/**
 * One block of a parsed block tree. The tree is flat: a block points to its parent by clientId.
 */
final class FLXBK_Block {
	/**
	 * @param string              $client_id        Position path, stable for the same content ("0", "0.2", "0.2.1").
	 * @param string|null         $parent_client_id Parent's clientId; null for a top-level block.
	 * @param int                 $order            Position among its siblings, from 0.
	 * @param int                 $depth            0 for a top-level block.
	 * @param string              $name             Block name ("flx/rowlayout"); "core/freeform" for loose HTML.
	 * @param array<string,mixed> $attributes       Attributes as saved in the block comment.
	 * @param string              $inner_html       The block's own saved HTML, without inner blocks.
	 * @param bool                $dynamic          Rendered on the server (render callback).
	 */
	public function __construct(
		public readonly string $client_id,
		public readonly ?string $parent_client_id,
		public readonly int $order,
		public readonly int $depth,
		public readonly string $name,
		public readonly array $attributes,
		public readonly string $inner_html,
		public readonly bool $dynamic,
	) {}
}

/**
 * A stylesheet, script or script module the rendered content needs.
 */
final class FLXBK_Asset {
	/**
	 * @param string       $kind          style | script | module.
	 * @param string       $handle        Registered handle (module id for modules).
	 * @param string|null  $src           Absolute URL; null for an inline-only asset.
	 * @param string|null  $version       Version query value.
	 * @param string|null  $media         Stylesheet media; null for scripts.
	 * @param list<string> $dependencies  Handles this one needs (they are in the same list, earlier).
	 * @param string       $inline_before Inline code printed before the file (scripts) or empty.
	 * @param string       $inline_after  Inline CSS / JS printed after the file.
	 * @param string       $data          Localized data script (wp_localize_script) or empty.
	 * @param bool         $footer        Printed in the footer (scripts).
	 * @param string       $strategy      defer | async | empty.
	 */
	public function __construct(
		public readonly string $kind,
		public readonly string $handle,
		public readonly ?string $src,
		public readonly ?string $version,
		public readonly ?string $media,
		public readonly array $dependencies,
		public readonly string $inline_before,
		public readonly string $inline_after,
		public readonly string $data,
		public readonly bool $footer,
		public readonly string $strategy,
	) {}
}

/**
 * Block content rendered the way the site's own page renders it, plus everything the page needs for it.
 */
final class FLXBK_Rendered {
	/**
	 * @param string             $html        Rendered HTML (the_content).
	 * @param string             $css         CSS generated for these blocks (block styles, custom CSS, block supports).
	 * @param list<FLXBK_Block>  $blocks      Flat block tree.
	 * @param list<FLXBK_Asset>  $styles      Stylesheets, dependencies first.
	 * @param list<FLXBK_Asset>  $scripts     Scripts and script modules, dependencies first.
	 * @param list<string>       $fonts       Font stylesheet URLs (Google Fonts, served locally when the setting is on).
	 * @param list<string>       $schema      JSON-LD documents (FAQ schema of accordions).
	 * @param string             $head_html   Ready <head> markup: styles, fonts, CSS, schema, head scripts.
	 * @param string             $footer_html Ready end-of-body markup: footer scripts, script modules.
	 */
	public function __construct(
		public readonly string $html,
		public readonly string $css,
		public readonly array $blocks,
		public readonly array $styles,
		public readonly array $scripts,
		public readonly array $fonts,
		public readonly array $schema,
		public readonly string $head_html,
		public readonly string $footer_html,
	) {}
}

/**
 * Site-wide styles every block page needs once (design variables, palette, theme.json styles).
 */
final class FLXBK_Site_Styles {
	/**
	 * @param string            $css               Flux Block Editor global CSS (font size scale, palette, color classes).
	 * @param string            $global_stylesheet theme.json global styles (wp_get_global_stylesheet).
	 * @param list<FLXBK_Asset> $styles            Base block stylesheets.
	 * @param string            $head_html         Ready <head> markup of all of the above.
	 */
	public function __construct(
		public readonly string $css,
		public readonly string $global_stylesheet,
		public readonly array $styles,
		public readonly string $head_html,
	) {}
}

/**
 * A pattern category of the design library.
 */
final class FLXBK_Pattern_Category {
	public function __construct(
		public readonly string $slug,
		public readonly string $label,
		public readonly int $count,
	) {}
}

/**
 * A design library pattern (docs/flx-blocks/LIBRARY.md item).
 */
final class FLXBK_Pattern {
	/**
	 * @param string       $id             Pattern name ("flx-preset/hero") or "user/<post id>".
	 * @param string       $title          Title.
	 * @param string       $description    Description.
	 * @param list<string> $categories     Category slugs.
	 * @param list<string> $keywords       Search keywords.
	 * @param int          $viewport_width Preview width in px.
	 * @param string       $content        Block markup.
	 * @param string|null  $source         Linked library id; null for this site.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $title,
		public readonly string $description,
		public readonly array $categories,
		public readonly array $keywords,
		public readonly int $viewport_width,
		public readonly string $content,
		public readonly ?string $source,
	) {}
}

/**
 * A design library catalogue: this site's or a linked library's.
 */
final class FLXBK_Library {
	/**
	 * @param string                       $name       Site name.
	 * @param string                       $url        Site address.
	 * @param string|null                  $source     Linked library id; null for this site.
	 * @param list<FLXBK_Pattern_Category> $categories Categories in use.
	 * @param list<FLXBK_Pattern>          $patterns   Patterns.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $url,
		public readonly ?string $source,
		public readonly array $categories,
		public readonly array $patterns,
	) {}
}

/**
 * A linked library (another Flux site). The key never leaves the server.
 */
final class FLXBK_Library_Source {
	public function __construct(
		public readonly string $id,
		public readonly string $name,
		public readonly string $url,
		public readonly bool $has_key,
	) {}
}

/**
 * This site's library sharing.
 */
final class FLXBK_Library_Share {
	public function __construct(
		public readonly bool $enabled,
		public readonly string $key,
		public readonly string $url,
	) {}
}

/**
 * A block on the Block Editor dashboard.
 */
final class FLXBK_Block_Type {
	public function __construct(
		public readonly string $slug,
		public readonly string $name,
		public readonly string $description,
		public readonly bool $active,
		public readonly ?string $link,
		public readonly ?string $link_text,
	) {}
}

/**
 * Editor width settings (shown only for themes that support them).
 */
final class FLXBK_Editor_Width {
	/**
	 * @param bool   $enabled       Per page/post width control on.
	 * @param int    $small         Small template width (px).
	 * @param int    $large         Large template width (px).
	 * @param string $post_default  sidebar | nosidebar | fullwidth.
	 * @param string $page_default  sidebar | nosidebar | fullwidth.
	 * @param int    $content_width The theme's content_width.
	 */
	public function __construct(
		public readonly bool $enabled,
		public readonly int $small,
		public readonly int $large,
		public readonly string $post_default,
		public readonly string $page_default,
		public readonly int $content_width,
	) {}
}

/**
 * Block Editor dashboard settings.
 */
final class FLXBK_Settings {
	public function __construct(
		public readonly bool $show_headers_in_admin_bar,
		public readonly bool $show_admin_bar_setting,
		public readonly ?FLXBK_Editor_Width $editor_width,
		public readonly string $style_library_url,
	) {}
}
