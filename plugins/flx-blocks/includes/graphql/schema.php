<?php
/**
 * Flux Block Editor GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a
 * GraphQL request. Resolvers call the services in includes/services/.
 *
 * - ContentNode.flxBlocks: a post's blocks rendered as on the site, with the CSS, stylesheets, scripts,
 *   fonts and schema the page needs (headless front ends show a block page one to one with it).
 * - flxBlocksSiteStyles: the site-wide CSS block pages share (load once per front end page).
 * - flxBlocksRender: render any block markup (pattern previews, drafts) for editors.
 * - Design library: patterns of this site and of linked libraries, sources and sharing.
 * - Block Editor dashboard: blocks on/off, editor widths, admin bar.
 *
 * The block editor itself and its internal REST routes are not part of this (docs/graphql/ISTISNALAR.md).
 *
 * @package flx-blocks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Service result → value, or the WP_Error as a typed GraphQL error.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxbk_gql_ok( $r ) {
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
function flxbk_gql_src( $source, string $class ) {
	if ( ! $source instanceof $class ) {
		throw flx_gql_error( 'INTERNAL' );
	}
	return $source;
}

/**
 * WP_Post behind a WPGraphQL content node.
 *
 * @param mixed $source Post model.
 */
function flxbk_gql_post( $source ): ?WP_Post {
	$id = 0;
	if ( is_object( $source ) && isset( $source->databaseId ) && is_numeric( $source->databaseId ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$id = (int) $source->databaseId; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}
	$post = $id > 0 ? get_post( $id ) : null;
	return $post instanceof WP_Post ? $post : null;
}

/**
 * Optional string argument.
 *
 * @param array<mixed> $args Arguments.
 * @param string       $key  Name.
 */
function flxbk_gql_arg( array $args, string $key ): string {
	$v = $args[ $key ] ?? '';
	return is_scalar( $v ) ? (string) $v : '';
}

/**
 * Mutation payload with errors for a WP_Error, or null when it is not an input problem.
 *
 * @param mixed $r Service result.
 * @return array{errors:array<int,array{code:string,message:string,field:?string}>}|null
 */
function flxbk_gql_user_error( $r ): ?array {
	if ( ! $r instanceof WP_Error ) {
		return null;
	}
	$code = flx_gql_code_of( $r );
	if ( 'INVALID_INPUT' !== $code && 'CONFLICT' !== $code ) {
		throw flx_gql_from_wp_error( $r );
	}
	return array( 'errors' => flx_gql_user_errors( $r ) );
}

$flxbk_editor = static fn(): bool => current_user_can( 'edit_posts' );
$flxbk_dash   = static fn(): bool => FLXBK_Dashboard_Service::can_manage();

/* ---------- Rendered content ---------- */

flx_gql_enum(
	'FlxBlocksAssetKind',
	array(
		'description' => __( 'What kind of file an asset is.', 'flx-blocks' ),
		'values'      => array(
			'STYLE'  => array(
				'value'       => 'style',
				'description' => __( 'Stylesheet.', 'flx-blocks' ),
			),
			'SCRIPT' => array(
				'value'       => 'script',
				'description' => __( 'Classic script.', 'flx-blocks' ),
			),
			'MODULE' => array(
				'value'       => 'module',
				'description' => __( 'Script module (type="module", uses the import map in footerHtml).', 'flx-blocks' ),
			),
		),
	)
);

flx_gql_type(
	'FlxBlocksAsset',
	array(
		'description' => __( 'A stylesheet or script rendered blocks need, as WordPress registered it.', 'flx-blocks' ),
		'fields'      => array(
			'kind'         => array(
				'type'        => 'FlxBlocksAssetKind!',
				'description' => __( 'Stylesheet, script or script module.', 'flx-blocks' ),
				'resolve'     => static fn( $a ): string => flxbk_gql_src( $a, FLXBK_Asset::class )->kind,
			),
			'handle'       => array(
				'type'        => 'String!',
				'description' => __( 'Registered handle (module id for modules). Use it to load each file once per page.', 'flx-blocks' ),
				'resolve'     => static fn( $a ): string => flxbk_gql_src( $a, FLXBK_Asset::class )->handle,
			),
			'src'          => array(
				'type'        => 'String',
				'description' => __( 'Absolute URL without the version; null for an inline-only asset.', 'flx-blocks' ),
				'resolve'     => static fn( $a ): ?string => flxbk_gql_src( $a, FLXBK_Asset::class )->src,
			),
			'version'      => array(
				'type'        => 'String',
				'description' => __( 'Version WordPress adds as ?ver=; null when it adds none.', 'flx-blocks' ),
				'resolve'     => static fn( $a ): ?string => flxbk_gql_src( $a, FLXBK_Asset::class )->version,
			),
			'media'        => array(
				'type'        => 'String',
				'description' => __( 'Stylesheet media; null for scripts.', 'flx-blocks' ),
				'resolve'     => static fn( $a ): ?string => flxbk_gql_src( $a, FLXBK_Asset::class )->media,
			),
			'dependencies' => array(
				'type'        => '[String!]!',
				'description' => __( 'Handles this asset needs; they come earlier in the same list.', 'flx-blocks' ),
				'resolve'     => static fn( $a ): array => flxbk_gql_src( $a, FLXBK_Asset::class )->dependencies,
			),
			'inlineBefore' => array(
				'type'        => 'String!',
				'description' => __( 'Inline script to run before the file; empty when none.', 'flx-blocks' ),
				'resolve'     => static fn( $a ): string => flxbk_gql_src( $a, FLXBK_Asset::class )->inline_before,
			),
			'inlineAfter'  => array(
				'type'        => 'String!',
				'description' => __( 'Inline CSS or script to add after the file; empty when none.', 'flx-blocks' ),
				'resolve'     => static fn( $a ): string => flxbk_gql_src( $a, FLXBK_Asset::class )->inline_after,
			),
			'data'         => array(
				'type'        => 'String!',
				'description' => __( 'Localized data script (var x = {...}) to run before the file; empty when none.', 'flx-blocks' ),
				'resolve'     => static fn( $a ): string => flxbk_gql_src( $a, FLXBK_Asset::class )->data,
			),
			'footer'       => array(
				'type'        => 'Boolean!',
				'description' => __( 'Loaded at the end of the body.', 'flx-blocks' ),
				'resolve'     => static fn( $a ): bool => flxbk_gql_src( $a, FLXBK_Asset::class )->footer,
			),
			'strategy'     => array(
				'type'        => 'String!',
				'description' => __( 'Loading strategy: defer, async or empty.', 'flx-blocks' ),
				'resolve'     => static fn( $a ): string => flxbk_gql_src( $a, FLXBK_Asset::class )->strategy,
			),
		),
	)
);

flx_gql_type(
	'FlxBlock',
	array(
		'description' => __( 'One block of the content. The tree is flat: inner blocks point to their parent with parentClientId.', 'flx-blocks' ),
		'fields'      => array(
			'clientId'       => array(
				'type'        => 'String!',
				'description' => __( 'Position path, the same for the same content ("0", "0.2", "0.2.1").', 'flx-blocks' ),
				'resolve'     => static fn( $b ): string => flxbk_gql_src( $b, FLXBK_Block::class )->client_id,
			),
			'parentClientId' => array(
				'type'        => 'String',
				'description' => __( 'Parent block; null for a top-level block.', 'flx-blocks' ),
				'resolve'     => static fn( $b ): ?string => flxbk_gql_src( $b, FLXBK_Block::class )->parent_client_id,
			),
			'order'          => array(
				'type'        => 'Int!',
				'description' => __( 'Position among its siblings, from 0.', 'flx-blocks' ),
				'resolve'     => static fn( $b ): int => flxbk_gql_src( $b, FLXBK_Block::class )->order,
			),
			'depth'          => array(
				'type'        => 'Int!',
				'description' => __( 'Nesting level; 0 for a top-level block.', 'flx-blocks' ),
				'resolve'     => static fn( $b ): int => flxbk_gql_src( $b, FLXBK_Block::class )->depth,
			),
			'name'           => array(
				'type'        => 'String!',
				'description' => __( 'Block name, e.g. flx/rowlayout; core/freeform for loose HTML.', 'flx-blocks' ),
				'resolve'     => static fn( $b ): string => flxbk_gql_src( $b, FLXBK_Block::class )->name,
			),
			// FlxJSON: block attributes are an open map; every block type (and plugin) defines its own.
			'attributes'     => array(
				'type'        => 'FlxJSON!',
				'description' => __( 'Attributes saved in the block comment (each block type has its own).', 'flx-blocks' ),
				// An empty map is sent as {} (not []).
				'resolve'     => static function ( $b ): object {
					return (object) flxbk_gql_src( $b, FLXBK_Block::class )->attributes;
				},
			),
			'innerHtml'      => array(
				'type'        => 'String!',
				'description' => __( 'The block\'s own saved HTML, without its inner blocks.', 'flx-blocks' ),
				'resolve'     => static fn( $b ): string => flxbk_gql_src( $b, FLXBK_Block::class )->inner_html,
			),
			'dynamic'        => array(
				'type'        => 'Boolean!',
				'description' => __( 'Rendered on the server; its saved HTML is not what visitors see.', 'flx-blocks' ),
				'resolve'     => static fn( $b ): bool => flxbk_gql_src( $b, FLXBK_Block::class )->dynamic,
			),
		),
	)
);

flx_gql_type(
	'FlxBlocksContent',
	array(
		'description' => __( 'Block content rendered as on the site, with everything the page needs for it. Either put headHtml in <head> and footerHtml at the end of <body>, or load css, styles, scripts and fonts yourself; not both.', 'flx-blocks' ),
		'fields'      => array(
			'html'       => array(
				'type'        => 'String!',
				'description' => __( 'Rendered HTML (the_content).', 'flx-blocks' ),
				'resolve'     => static fn( $c ): string => flxbk_gql_src( $c, FLXBK_Rendered::class )->html,
			),
			'css'        => array(
				'type'        => 'String!',
				'description' => __( 'CSS generated for these blocks (block styles, custom CSS, layout). Not in styles.', 'flx-blocks' ),
				'resolve'     => static fn( $c ): string => flxbk_gql_src( $c, FLXBK_Rendered::class )->css,
			),
			// A document's own structure: a flat list, not a paginated connection (paging would cut the tree).
			'blocks'     => array(
				'type'        => '[FlxBlock!]!',
				'description' => __( 'Block tree as a flat list in document order.', 'flx-blocks' ),
				'resolve'     => static fn( $c ): array => flxbk_gql_src( $c, FLXBK_Rendered::class )->blocks,
			),
			'styles'     => array(
				'type'        => '[FlxBlocksAsset!]!',
				'description' => __( 'Stylesheets, dependencies first.', 'flx-blocks' ),
				'resolve'     => static fn( $c ): array => flxbk_gql_src( $c, FLXBK_Rendered::class )->styles,
			),
			'scripts'    => array(
				'type'        => '[FlxBlocksAsset!]!',
				'description' => __( 'Scripts and script modules, dependencies first.', 'flx-blocks' ),
				'resolve'     => static fn( $c ): array => flxbk_gql_src( $c, FLXBK_Rendered::class )->scripts,
			),
			'fonts'      => array(
				'type'        => '[String!]!',
				'description' => __( 'Font stylesheet URLs (served from the site when local fonts are on).', 'flx-blocks' ),
				'resolve'     => static fn( $c ): array => flxbk_gql_src( $c, FLXBK_Rendered::class )->fonts,
			),
			'schema'     => array(
				'type'        => '[String!]!',
				'description' => __( 'JSON-LD documents for the page head (e.g. FAQ schema of accordions).', 'flx-blocks' ),
				'resolve'     => static fn( $c ): array => flxbk_gql_src( $c, FLXBK_Rendered::class )->schema,
			),
			'headHtml'   => array(
				'type'        => 'String!',
				'description' => __( 'Ready <head> markup: stylesheets, fonts, css, schema and head scripts, printed by WordPress.', 'flx-blocks' ),
				'resolve'     => static fn( $c ): string => flxbk_gql_src( $c, FLXBK_Rendered::class )->head_html,
			),
			'footerHtml' => array(
				'type'        => 'String!',
				'description' => __( 'Ready end-of-body markup: footer scripts, script modules and their data.', 'flx-blocks' ),
				'resolve'     => static fn( $c ): string => flxbk_gql_src( $c, FLXBK_Rendered::class )->footer_html,
			),
		),
	)
);

flx_gql_type(
	'FlxBlocksSiteStyles',
	array(
		'description' => __( 'Site-wide styles every block page needs once.', 'flx-blocks' ),
		'fields'      => array(
			'css'              => array(
				'type'        => 'String!',
				'description' => __( 'Flux Block Editor variables, theme palette and palette color classes.', 'flx-blocks' ),
				'resolve'     => static fn( $s ): string => flxbk_gql_src( $s, FLXBK_Site_Styles::class )->css,
			),
			'globalStylesheet' => array(
				'type'        => 'String!',
				'description' => __( 'The theme\'s global styles (theme.json presets and styles).', 'flx-blocks' ),
				'resolve'     => static fn( $s ): string => flxbk_gql_src( $s, FLXBK_Site_Styles::class )->global_stylesheet,
			),
			'styles'           => array(
				'type'        => '[FlxBlocksAsset!]!',
				'description' => __( 'Base block library stylesheets.', 'flx-blocks' ),
				'resolve'     => static fn( $s ): array => flxbk_gql_src( $s, FLXBK_Site_Styles::class )->styles,
			),
			'headHtml'         => array(
				'type'        => 'String!',
				'description' => __( 'Ready <head> markup of all of the above.', 'flx-blocks' ),
				'resolve'     => static fn( $s ): string => flxbk_gql_src( $s, FLXBK_Site_Styles::class )->head_html,
			),
		),
	)
);

/*
 * WPGraphQL hands over only posts the viewer may see (drafts and private posts are already left out).
 * The cap requires that post; the resolver also gives null for password protected content (unless the
 * user may edit it), so a front end page does not fail on it.
 */
flx_gql_field(
	'ContentNode',
	'flxBlocks',
	array(
		'type'        => 'FlxBlocksContent',
		'description' => __( 'The blocks of this content rendered as on the site, with the CSS and files the page needs. Null when the content is password protected.', 'flx-blocks' ),
		'cap'         => static fn( $source ): bool => null !== flxbk_gql_post( $source ),
		'resolve'     => static function ( $source ): ?FLXBK_Rendered {
			$post = flxbk_gql_post( $source );
			if ( null === $post || ! FLXBK_Render_Service::can_read( $post ) ) {
				return null;
			}
			flx_gql_cache_tag( array( FLXBK_Render_Service::TAG_POST . $post->ID, FLXBK_Render_Service::TAG_STYLES ) );
			return FLXBK_Render_Service::render_post( $post );
		},
	)
);

// Public: CSS any visitor of the site downloads anyway.
flx_gql_query(
	'flxBlocksSiteStyles',
	array(
		'type'        => 'FlxBlocksSiteStyles!',
		'description' => __( 'Site-wide styles block pages share. Load once per page, before the content styles.', 'flx-blocks' ),
		'cap'         => 'public',
		'resolve'     => static function (): FLXBK_Site_Styles {
			flx_gql_cache_tag( FLXBK_Render_Service::TAG_STYLES );
			return FLXBK_Render_Service::site_styles();
		},
	)
);

flx_gql_query(
	'flxBlocksRender',
	array(
		'type'        => 'FlxBlocksContent!',
		'description' => __( 'Renders block markup (a pattern or an unsaved draft) as the site would.', 'flx-blocks' ),
		'cap'         => $flxbk_editor,
		'args'        => array(
			'content' => array(
				'type'        => 'String!',
				'description' => __( 'Block markup.', 'flx-blocks' ),
			),
			'postId'  => array(
				'type'        => 'Int',
				'description' => __( 'Post the markup belongs to; dynamic blocks read it. The user must be able to edit it.', 'flx-blocks' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): FLXBK_Rendered {
			$content = flxbk_gql_arg( $args, 'content' );
			if ( strlen( $content ) > FLXBK_Render_Service::MAX_MARKUP ) {
				throw flx_gql_error( 'INVALID_INPUT', __( 'The content is too long to preview.', 'flx-blocks' ), 'content' );
			}
			$post_id = (int) flxbk_gql_arg( $args, 'postId' );
			$post    = null;
			if ( $post_id > 0 ) {
				$post = get_post( $post_id );
				if ( ! $post instanceof WP_Post ) {
					throw flx_gql_error( 'NOT_FOUND', '', 'postId' );
				}
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					throw flx_gql_error( 'FORBIDDEN' );
				}
			}
			return FLXBK_Render_Service::render_markup( $content, $post );
		},
	)
);

/* ---------- Design library ---------- */

flx_gql_type(
	'FlxBlocksPatternCategory',
	array(
		'description' => __( 'A design library category.', 'flx-blocks' ),
		'fields'      => array(
			'slug'  => array(
				'type'        => 'String!',
				'description' => __( 'Category slug.', 'flx-blocks' ),
				'resolve'     => static fn( $c ): string => flxbk_gql_src( $c, FLXBK_Pattern_Category::class )->slug,
			),
			'label' => array(
				'type'        => 'String!',
				'description' => __( 'Category name.', 'flx-blocks' ),
				'resolve'     => static fn( $c ): string => flxbk_gql_src( $c, FLXBK_Pattern_Category::class )->label,
			),
			'count' => array(
				'type'        => 'Int!',
				'description' => __( 'Patterns in the category.', 'flx-blocks' ),
				'resolve'     => static fn( $c ): int => flxbk_gql_src( $c, FLXBK_Pattern_Category::class )->count,
			),
		),
	)
);

flx_gql_type(
	'FlxBlocksPattern',
	array(
		'description' => __( 'A design library pattern (a ready section or page).', 'flx-blocks' ),
		'fields'      => array(
			'id'            => array(
				'type'        => 'String!',
				'description' => __( 'Pattern name (e.g. flx-preset/hero) or user/<post id> for My patterns.', 'flx-blocks' ),
				'resolve'     => static fn( $p ): string => flxbk_gql_src( $p, FLXBK_Pattern::class )->id,
			),
			'title'         => array(
				'type'        => 'String!',
				'description' => __( 'Title.', 'flx-blocks' ),
				'resolve'     => static fn( $p ): string => flxbk_gql_src( $p, FLXBK_Pattern::class )->title,
			),
			'description'   => array(
				'type'        => 'String!',
				'description' => __( 'Description; empty when none.', 'flx-blocks' ),
				'resolve'     => static fn( $p ): string => flxbk_gql_src( $p, FLXBK_Pattern::class )->description,
			),
			'categories'    => array(
				'type'        => '[String!]!',
				'description' => __( 'Category slugs.', 'flx-blocks' ),
				'resolve'     => static fn( $p ): array => flxbk_gql_src( $p, FLXBK_Pattern::class )->categories,
			),
			'keywords'      => array(
				'type'        => '[String!]!',
				'description' => __( 'Search keywords.', 'flx-blocks' ),
				'resolve'     => static fn( $p ): array => flxbk_gql_src( $p, FLXBK_Pattern::class )->keywords,
			),
			'viewportWidth' => array(
				'type'        => 'Int!',
				'description' => __( 'Width to preview the pattern at (px).', 'flx-blocks' ),
				'resolve'     => static fn( $p ): int => flxbk_gql_src( $p, FLXBK_Pattern::class )->viewport_width,
			),
			'content'       => array(
				'type'        => 'String!',
				'description' => __( 'Block markup to insert.', 'flx-blocks' ),
				'resolve'     => static fn( $p ): string => flxbk_gql_src( $p, FLXBK_Pattern::class )->content,
			),
			'source'        => array(
				'type'        => 'ID',
				'description' => __( 'Linked library the pattern comes from; null for this site.', 'flx-blocks' ),
				'resolve'     => static fn( $p ): ?string => flxbk_gql_src( $p, FLXBK_Pattern::class )->source,
			),
			'rendered'      => array(
				'type'        => 'FlxBlocksContent!',
				'description' => __( 'The pattern rendered on this site (preview). Ask for it on one pattern at a time.', 'flx-blocks' ),
				'resolve'     => static fn( $p ): FLXBK_Rendered => FLXBK_Render_Service::render_markup( flxbk_gql_src( $p, FLXBK_Pattern::class )->content ),
			),
		),
	)
);
flx_gql_connection( 'FlxBlocksPattern', __( 'Design library patterns.', 'flx-blocks' ) );

flx_gql_type(
	'FlxBlocksLibrary',
	array(
		'description' => __( 'A design library: this site\'s or a linked Flux site\'s.', 'flx-blocks' ),
		'fields'      => array(
			'name'       => array(
				'type'        => 'String!',
				'description' => __( 'Site name.', 'flx-blocks' ),
				'resolve'     => static fn( $l ): string => flxbk_gql_src( $l, FLXBK_Library::class )->name,
			),
			'url'        => array(
				'type'        => 'String!',
				'description' => __( 'Site address.', 'flx-blocks' ),
				'resolve'     => static fn( $l ): string => flxbk_gql_src( $l, FLXBK_Library::class )->url,
			),
			'source'     => array(
				'type'        => 'ID',
				'description' => __( 'Linked library id; null for this site.', 'flx-blocks' ),
				'resolve'     => static fn( $l ): ?string => flxbk_gql_src( $l, FLXBK_Library::class )->source,
			),
			'categories' => array(
				'type'        => '[FlxBlocksPatternCategory!]!',
				'description' => __( 'Categories in use (a short list).', 'flx-blocks' ),
				'resolve'     => static fn( $l ): array => flxbk_gql_src( $l, FLXBK_Library::class )->categories,
			),
			'patterns'   => array(
				'type'        => 'FlxBlocksPatternConnection!',
				'description' => __( 'Patterns, filtered by category and search words.', 'flx-blocks' ),
				'args'        => flx_gql_connection_args(
					array(
						'category' => array(
							'type'        => 'String',
							'description' => __( 'Category slug.', 'flx-blocks' ),
						),
						'search'   => array(
							'type'        => 'String',
							'description' => __( 'Words to look for in the title, description and keywords.', 'flx-blocks' ),
						),
					)
				),
				'resolve'     => static function ( $l, array $args ): array {
					$list = FLXBK_Library_Service::filter( flxbk_gql_src( $l, FLXBK_Library::class ), flxbk_gql_arg( $args, 'category' ), flxbk_gql_arg( $args, 'search' ) );
					return flx_gql_paginate(
						$args,
						static fn( int $offset, int $limit ): array => array(
							'items' => array_slice( $list, $offset, $limit ),
							'total' => count( $list ),
						)
					);
				},
			),
		),
	)
);

flx_gql_type(
	'FlxBlocksLibrarySource',
	array(
		'description' => __( 'A linked design library (another Flux site). Its key never leaves the server.', 'flx-blocks' ),
		'fields'      => array(
			'id'     => array(
				'type'        => 'ID!',
				'description' => __( 'Source id.', 'flx-blocks' ),
				'resolve'     => static fn( $s ): string => flxbk_gql_src( $s, FLXBK_Library_Source::class )->id,
			),
			'name'   => array(
				'type'        => 'String!',
				'description' => __( 'Name.', 'flx-blocks' ),
				'resolve'     => static fn( $s ): string => flxbk_gql_src( $s, FLXBK_Library_Source::class )->name,
			),
			'url'    => array(
				'type'        => 'String!',
				'description' => __( 'Site address.', 'flx-blocks' ),
				'resolve'     => static fn( $s ): string => flxbk_gql_src( $s, FLXBK_Library_Source::class )->url,
			),
			'hasKey' => array(
				'type'        => 'Boolean!',
				'description' => __( 'A library key is saved for it.', 'flx-blocks' ),
				'resolve'     => static fn( $s ): bool => flxbk_gql_src( $s, FLXBK_Library_Source::class )->has_key,
			),
		),
	)
);

flx_gql_type(
	'FlxBlocksLibraryShare',
	array(
		'description' => __( 'Sharing this site\'s design library with other Flux sites.', 'flx-blocks' ),
		'fields'      => array(
			'enabled' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Other sites can add this library.', 'flx-blocks' ),
				'resolve'     => static fn( $s ): bool => flxbk_gql_src( $s, FLXBK_Library_Share::class )->enabled,
			),
			'key'     => array(
				'type'        => 'String!',
				'description' => __( 'Key the other site enters; empty until sharing is turned on.', 'flx-blocks' ),
				'resolve'     => static fn( $s ): string => flxbk_gql_src( $s, FLXBK_Library_Share::class )->key,
			),
			'url'     => array(
				'type'        => 'String!',
				'description' => __( 'Address the other site enters.', 'flx-blocks' ),
				'resolve'     => static fn( $s ): string => flxbk_gql_src( $s, FLXBK_Library_Share::class )->url,
			),
		),
	)
);

flx_gql_query(
	'flxBlocksLibrary',
	array(
		'type'        => 'FlxBlocksLibrary!',
		'description' => __( 'This site\'s design library, or a linked library\'s.', 'flx-blocks' ),
		'cap'         => $flxbk_editor,
		'args'        => array(
			'source'  => array(
				'type'        => 'ID',
				'description' => __( 'Linked library id; leave empty for this site.', 'flx-blocks' ),
			),
			'refresh' => array(
				'type'        => 'Boolean',
				'description' => __( 'Fetch a linked library again instead of using the 30 minute copy.', 'flx-blocks' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): FLXBK_Library {
			flx_gql_cache_tag( FLXBK_Library_Service::TAG );
			$source = flxbk_gql_arg( $args, 'source' );
			return flxbk_gql_ok( FLXBK_Library_Service::get( '' !== $source ? $source : null, ! empty( $args['refresh'] ) ) );
		},
	)
);

flx_gql_query(
	'flxBlocksPattern',
	array(
		'type'        => 'FlxBlocksPattern',
		'description' => __( 'One design library pattern (for its preview).', 'flx-blocks' ),
		'cap'         => $flxbk_editor,
		'args'        => array(
			'id'     => array(
				'type'        => 'String!',
				'description' => __( 'Pattern id.', 'flx-blocks' ),
			),
			'source' => array(
				'type'        => 'ID',
				'description' => __( 'Linked library id; leave empty for this site.', 'flx-blocks' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): ?FLXBK_Pattern {
			flx_gql_cache_tag( FLXBK_Library_Service::TAG );
			$source = flxbk_gql_arg( $args, 'source' );
			$p      = FLXBK_Library_Service::pattern( '' !== $source ? $source : null, flxbk_gql_arg( $args, 'id' ) );
			if ( $p instanceof WP_Error && 'flxbk_pattern' === $p->get_error_code() ) {
				return null;
			}
			return flxbk_gql_ok( $p );
		},
	)
);

flx_gql_query(
	'flxBlocksLibrarySources',
	array(
		'type'        => '[FlxBlocksLibrarySource!]!',
		'description' => __( 'Linked design libraries (a short list).', 'flx-blocks' ),
		'cap'         => $flxbk_editor,
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXBK_Library_Service::TAG );
			return FLXBK_Library_Service::sources();
		},
	)
);

flx_gql_query(
	'flxBlocksLibraryShare',
	array(
		'type'        => 'FlxBlocksLibraryShare!',
		'description' => __( 'Sharing of this site\'s design library.', 'flx-blocks' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): FLXBK_Library_Share => FLXBK_Library_Service::share(),
	)
);

flx_gql_mutation(
	'flxBlocksLibrarySourceAdd',
	array(
		'description' => __( 'Links another Flux site\'s design library. The site is asked once to check the address and key.', 'flx-blocks' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'url'  => array(
				'type'        => 'String!',
				'description' => __( 'Site address.', 'flx-blocks' ),
			),
			'name' => array(
				'type'        => 'String',
				'description' => __( 'Name; leave empty to use the site\'s own name.', 'flx-blocks' ),
			),
			'key'  => array(
				'type'        => 'String',
				'description' => __( 'Library key given by the other site.', 'flx-blocks' ),
			),
		),
		'output'      => array(
			'source'  => array(
				'type'        => 'FlxBlocksLibrarySource',
				'description' => __( 'The added library.', 'flx-blocks' ),
			),
			'sources' => array(
				'type'        => '[FlxBlocksLibrarySource!]!',
				'description' => __( 'All linked libraries.', 'flx-blocks' ),
				'resolve'     => static fn(): array => FLXBK_Library_Service::sources(),
			),
		),
		'resolve'     => static function ( array $input ): array {
			$r = FLXBK_Library_Service::add_source( flxbk_gql_arg( $input, 'url' ), flxbk_gql_arg( $input, 'name' ), flxbk_gql_arg( $input, 'key' ) );
			return flxbk_gql_user_error( $r ) ?? array( 'source' => $r );
		},
	)
);

flx_gql_mutation(
	'flxBlocksLibrarySourceRemove',
	array(
		'description' => __( 'Removes a linked design library.', 'flx-blocks' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Source id.', 'flx-blocks' ),
			),
		),
		'output'      => array(
			'sources' => array(
				'type'        => '[FlxBlocksLibrarySource!]!',
				'description' => __( 'Remaining linked libraries.', 'flx-blocks' ),
				'resolve'     => static fn(): array => FLXBK_Library_Service::sources(),
			),
		),
		'resolve'     => static function ( array $input ): array {
			flxbk_gql_ok( FLXBK_Library_Service::remove_source( flxbk_gql_arg( $input, 'id' ) ) );
			return array();
		},
	)
);

flx_gql_mutation(
	'flxBlocksLibraryShareUpdate',
	array(
		'description' => __( 'Turns sharing of this site\'s design library on or off, or makes a new key.', 'flx-blocks' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'enabled'       => array(
				'type'        => 'Boolean',
				'description' => __( 'Share the library; leave out to keep it as it is.', 'flx-blocks' ),
			),
			'regenerateKey' => array(
				'type'        => 'Boolean',
				'description' => __( 'Make a new key; sites using the old key lose access.', 'flx-blocks' ),
			),
		),
		'output'      => array(
			'share' => array(
				'type'        => 'FlxBlocksLibraryShare!',
				'description' => __( 'Sharing after the change.', 'flx-blocks' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			$enabled = array_key_exists( 'enabled', $input ) && null !== $input['enabled'] ? (bool) $input['enabled'] : null;
			return array( 'share' => FLXBK_Library_Service::update_share( $enabled, ! empty( $input['regenerateKey'] ) ) );
		},
	)
);

/* ---------- Block Editor dashboard ---------- */

flx_gql_enum(
	'FlxBlocksEditorWidthMode',
	array(
		'description' => __( 'Editor width of a page or post.', 'flx-blocks' ),
		'values'      => array(
			'SMALL'      => array(
				'value'       => 'sidebar',
				'description' => __( 'Small template.', 'flx-blocks' ),
			),
			'LARGE'      => array(
				'value'       => 'nosidebar',
				'description' => __( 'Large template.', 'flx-blocks' ),
			),
			'FULL_WIDTH' => array(
				'value'       => 'fullwidth',
				'description' => __( 'Full width.', 'flx-blocks' ),
			),
		),
	)
);

flx_gql_type(
	'FlxBlocksBlockType',
	array(
		'description' => __( 'A block on the Block Editor dashboard.', 'flx-blocks' ),
		'fields'      => array(
			'slug'        => array(
				'type'        => 'String!',
				'description' => __( 'Block name, e.g. flx/rowlayout.', 'flx-blocks' ),
				'resolve'     => static fn( $t ): string => flxbk_gql_src( $t, FLXBK_Block_Type::class )->slug,
			),
			'name'        => array(
				'type'        => 'String!',
				'description' => __( 'Block title.', 'flx-blocks' ),
				'resolve'     => static fn( $t ): string => flxbk_gql_src( $t, FLXBK_Block_Type::class )->name,
			),
			'description' => array(
				'type'        => 'String!',
				'description' => __( 'What the block does.', 'flx-blocks' ),
				'resolve'     => static fn( $t ): string => flxbk_gql_src( $t, FLXBK_Block_Type::class )->description,
			),
			'active'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'The block is offered in the editor.', 'flx-blocks' ),
				'resolve'     => static fn( $t ): bool => flxbk_gql_src( $t, FLXBK_Block_Type::class )->active,
			),
			'link'        => array(
				'type'        => 'String',
				'description' => __( 'Admin page of the block\'s items (e.g. Lottie animations); null when none.', 'flx-blocks' ),
				'resolve'     => static fn( $t ): ?string => flxbk_gql_src( $t, FLXBK_Block_Type::class )->link,
			),
			'linkText'    => array(
				'type'        => 'String',
				'description' => __( 'Text of that link.', 'flx-blocks' ),
				'resolve'     => static fn( $t ): ?string => flxbk_gql_src( $t, FLXBK_Block_Type::class )->link_text,
			),
		),
	)
);

flx_gql_type(
	'FlxBlocksEditorWidth',
	array(
		'description' => __( 'Editor widths (only for themes that use them).', 'flx-blocks' ),
		'fields'      => array(
			'enabled'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'Width can be chosen per page and post.', 'flx-blocks' ),
				'resolve'     => static fn( $w ): bool => flxbk_gql_src( $w, FLXBK_Editor_Width::class )->enabled,
			),
			'small'        => array(
				'type'        => 'Int!',
				'description' => __( 'Small template width (px).', 'flx-blocks' ),
				'resolve'     => static fn( $w ): int => flxbk_gql_src( $w, FLXBK_Editor_Width::class )->small,
			),
			'large'        => array(
				'type'        => 'Int!',
				'description' => __( 'Large template width (px).', 'flx-blocks' ),
				'resolve'     => static fn( $w ): int => flxbk_gql_src( $w, FLXBK_Editor_Width::class )->large,
			),
			'postDefault'  => array(
				'type'        => 'FlxBlocksEditorWidthMode!',
				'description' => __( 'Width of new posts.', 'flx-blocks' ),
				'resolve'     => static fn( $w ): string => flxbk_gql_src( $w, FLXBK_Editor_Width::class )->post_default,
			),
			'pageDefault'  => array(
				'type'        => 'FlxBlocksEditorWidthMode!',
				'description' => __( 'Width of new pages.', 'flx-blocks' ),
				'resolve'     => static fn( $w ): string => flxbk_gql_src( $w, FLXBK_Editor_Width::class )->page_default,
			),
			'contentWidth' => array(
				'type'        => 'Int!',
				'description' => __( 'The theme\'s content width (px); 0 when it sets none.', 'flx-blocks' ),
				'resolve'     => static fn( $w ): int => flxbk_gql_src( $w, FLXBK_Editor_Width::class )->content_width,
			),
		),
	)
);

flx_gql_type(
	'FlxBlocksSettings',
	array(
		'description' => __( 'Block Editor dashboard settings.', 'flx-blocks' ),
		'fields'      => array(
			'showHeadersInAdminBar' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Show the page\'s header blocks in the admin bar.', 'flx-blocks' ),
				'resolve'     => static fn( $s ): bool => flxbk_gql_src( $s, FLXBK_Settings::class )->show_headers_in_admin_bar,
			),
			'showAdminBarSetting'   => array(
				'type'        => 'Boolean!',
				'description' => __( 'The admin bar setting is offered (filter flxbk_show_admin_bar).', 'flx-blocks' ),
				'resolve'     => static fn( $s ): bool => flxbk_gql_src( $s, FLXBK_Settings::class )->show_admin_bar_setting,
			),
			'editorWidth'           => array(
				'type'        => 'FlxBlocksEditorWidth',
				'description' => __( 'Editor widths; null when the theme does not use them.', 'flx-blocks' ),
				'resolve'     => static fn( $s ): ?FLXBK_Editor_Width => flxbk_gql_src( $s, FLXBK_Settings::class )->editor_width,
			),
			'styleLibraryUrl'       => array(
				'type'        => 'String!',
				'description' => __( 'Admin address of the Style Library.', 'flx-blocks' ),
				'resolve'     => static fn( $s ): string => flxbk_gql_src( $s, FLXBK_Settings::class )->style_library_url,
			),
		),
	)
);

flx_gql_query(
	'flxBlocksBlockTypes',
	array(
		'type'        => '[FlxBlocksBlockType!]!',
		'description' => __( 'Blocks of the Block Editor dashboard (a short list).', 'flx-blocks' ),
		'cap'         => $flxbk_dash,
		'resolve'     => static fn(): array => FLXBK_Dashboard_Service::block_types(),
	)
);

flx_gql_query(
	'flxBlocksSettings',
	array(
		'type'        => 'FlxBlocksSettings!',
		'description' => __( 'Block Editor dashboard settings.', 'flx-blocks' ),
		'cap'         => $flxbk_dash,
		'resolve'     => static fn(): FLXBK_Settings => FLXBK_Dashboard_Service::settings(),
	)
);

flx_gql_mutation(
	'flxBlocksBlockTypeSetActive',
	array(
		'description' => __( 'Turns a block on or off in the editor. Content that already uses it keeps working.', 'flx-blocks' ),
		'cap'         => $flxbk_dash,
		'input'       => array(
			'slug'   => array(
				'type'        => 'String!',
				'description' => __( 'Block name.', 'flx-blocks' ),
			),
			'active' => array(
				'type'        => 'Boolean!',
				'description' => __( 'On.', 'flx-blocks' ),
			),
		),
		'output'      => array(
			'blockType' => array(
				'type'        => 'FlxBlocksBlockType',
				'description' => __( 'The block after the change.', 'flx-blocks' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			$r = FLXBK_Dashboard_Service::set_active( flxbk_gql_arg( $input, 'slug' ), ! empty( $input['active'] ) );
			return flxbk_gql_user_error( $r ) ?? array( 'blockType' => $r );
		},
	)
);

flx_gql_input(
	'FlxBlocksEditorWidthInput',
	array(
		'description' => __( 'Editor widths; a field left out stays as it is.', 'flx-blocks' ),
		'fields'      => array(
			'enabled'     => array(
				'type'        => 'Boolean',
				'description' => __( 'Width can be chosen per page and post.', 'flx-blocks' ),
			),
			'small'       => array(
				'type'        => 'Int',
				'description' => __( 'Small template width (px, 200–4000).', 'flx-blocks' ),
			),
			'large'       => array(
				'type'        => 'Int',
				'description' => __( 'Large template width (px, 200–4000).', 'flx-blocks' ),
			),
			'postDefault' => array(
				'type'        => 'FlxBlocksEditorWidthMode',
				'description' => __( 'Width of new posts.', 'flx-blocks' ),
			),
			'pageDefault' => array(
				'type'        => 'FlxBlocksEditorWidthMode',
				'description' => __( 'Width of new pages.', 'flx-blocks' ),
			),
		),
	)
);

flx_gql_mutation(
	'flxBlocksSettingsUpdate',
	array(
		'description' => __( 'Saves Block Editor dashboard settings; a field left out stays as it is.', 'flx-blocks' ),
		'cap'         => $flxbk_dash,
		'input'       => array(
			'showHeadersInAdminBar' => array(
				'type'        => 'Boolean',
				'description' => __( 'Show the page\'s header blocks in the admin bar.', 'flx-blocks' ),
			),
			'editorWidth'           => array(
				'type'        => 'FlxBlocksEditorWidthInput',
				'description' => __( 'Editor widths.', 'flx-blocks' ),
			),
		),
		'output'      => array(
			'settings' => array(
				'type'        => 'FlxBlocksSettings',
				'description' => __( 'Settings after the change.', 'flx-blocks' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			$bar   = array_key_exists( 'showHeadersInAdminBar', $input ) && null !== $input['showHeadersInAdminBar'] ? (bool) $input['showHeadersInAdminBar'] : null;
			$width = null;
			if ( isset( $input['editorWidth'] ) && is_array( $input['editorWidth'] ) ) {
				$w     = $input['editorWidth'];
				$width = array(
					'enabled'     => isset( $w['enabled'] ) ? (bool) $w['enabled'] : null,
					'small'       => isset( $w['small'] ) && is_numeric( $w['small'] ) ? (int) $w['small'] : null,
					'large'       => isset( $w['large'] ) && is_numeric( $w['large'] ) ? (int) $w['large'] : null,
					'postDefault' => isset( $w['postDefault'] ) && is_string( $w['postDefault'] ) ? $w['postDefault'] : null,
					'pageDefault' => isset( $w['pageDefault'] ) && is_string( $w['pageDefault'] ) ? $w['pageDefault'] : null,
				);
			}
			$r = FLXBK_Dashboard_Service::update_settings( $bar, $width );
			return flxbk_gql_user_error( $r ) ?? array( 'settings' => $r );
		},
	)
);
