<?php
/**
 * Renders block content outside a theme page (GraphQL, headless front ends, previews) the way the
 * site's own page renders it, and collects everything that page would load for it: the CSS the blocks
 * generate, block-support CSS, the stylesheets / scripts / script modules the blocks enqueue, Google
 * Fonts and the FAQ schema.
 *
 * Every render starts from a clean slate and puts the request's state back afterwards, so rendering
 * several posts in one request gives each one its complete asset list (blocks skip CSS and assets they
 * believe are already on the page).
 *
 * @package flx-blocks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Block render service.
 */
final class FLXBK_Render_Service {

	/** Cache tag prefix of a post's rendered blocks: flx-blocks:post:<id>. */
	const TAG_POST = 'flx-blocks:post:';

	/** Cache tag of the site-wide styles and of every render (design tokens, palette, settings). */
	const TAG_STYLES = 'flx-blocks:styles';

	/** Largest markup flxBlocksRender accepts (bytes). */
	const MAX_MARKUP = 1048576;

	/**
	 * Nesting guard: a render inside a render (a block that renders a post) keeps the outer state.
	 *
	 * @var int
	 */
	private static $depth = 0;

	/**
	 * Whether the current user may see a post's rendered blocks: published and not password protected,
	 * or the user may read it (drafts, private posts, previews).
	 */
	public static function can_read( WP_Post $post ): bool {
		if ( 'publish' === $post->post_status && '' === $post->post_password && is_post_type_viewable( $post->post_type ) ) {
			return true;
		}
		return current_user_can( 'read_post', $post->ID ) && ( '' === $post->post_password || current_user_can( 'edit_post', $post->ID ) );
	}

	/**
	 * A post's blocks rendered with everything they need.
	 */
	public static function render_post( WP_Post $post ): FLXBK_Rendered {
		/**
		 * Markup rendered for a post (default: post_content).
		 *
		 * @param string  $content Block markup.
		 * @param WP_Post $post    Post.
		 */
		$content = (string) apply_filters( 'flxbk_render_post_content', $post->post_content, $post );
		return self::render( $content, $post );
	}

	/**
	 * Any block markup (a pattern preview, an unsaved draft) rendered with everything it needs.
	 *
	 * @param string       $content Block markup.
	 * @param WP_Post|null $context Post the markup belongs to (dynamic blocks read it), if any.
	 */
	public static function render_markup( string $content, ?WP_Post $context = null ): FLXBK_Rendered {
		return self::render( $content, $context );
	}

	/**
	 * Flat block tree of block markup.
	 *
	 * @param string $content Block markup.
	 * @return list<FLXBK_Block>
	 */
	public static function blocks( string $content ): array {
		$out = array();
		self::walk( parse_blocks( $content ), null, 0, $out );
		return $out;
	}

	/**
	 * Site-wide styles: Flux Block Editor variables and palette, theme.json styles, the base block library.
	 */
	public static function site_styles(): FLXBK_Site_Styles {
		$wp_styles = wp_styles();
		$saved     = $wp_styles->queue;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
		$wp_styles->queue = array();
		if ( function_exists( 'flxbk_add_global_gutenberg_styles_frontend' ) ) {
			flxbk_add_global_gutenberg_styles_frontend();
		}
		do_action( 'flxbk_site_styles' );
		$css = self::inline_of( $wp_styles, 'flx-blocks-global-variables' );
		if ( function_exists( 'flxbk_theme_palette_css' ) ) {
			$css .= (string) flxbk_theme_palette_css();
		}
		$css .= self::palette_classes();
		$base = array();
		foreach ( array( 'wp-block-library', 'wp-block-library-theme' ) as $handle ) {
			if ( $wp_styles->query( $handle, 'registered' ) ) {
				$base[] = $handle;
			}
		}
		$assets            = self::style_assets( $wp_styles, $base );
		$wp_styles->queue  = $saved;
		$global_stylesheet = function_exists( 'wp_get_global_stylesheet' ) ? (string) wp_get_global_stylesheet() : '';

		$head = self::print_styles( $wp_styles, $base );
		if ( '' !== $global_stylesheet ) {
			$head .= '<style id="global-styles-inline-css">' . self::style_text( $global_stylesheet ) . "</style>\n";
		}
		if ( '' !== $css ) {
			$head .= '<style id="flx-blocks-global-css">' . self::style_text( $css ) . "</style>\n";
		}
		return new FLXBK_Site_Styles( $css, $global_stylesheet, $assets, $head );
	}

	/**
	 * Renders markup with a clean asset state and collects what it added.
	 *
	 * @param string       $content Block markup.
	 * @param WP_Post|null $post    Context post.
	 */
	private static function render( string $content, ?WP_Post $post ): FLXBK_Rendered {
		$blocks = self::blocks( $content );
		if ( self::$depth > 0 ) {
			// A block that renders other content: the outer render collects the assets.
			return new FLXBK_Rendered( (string) do_blocks( $content ), '', $blocks, array(), array(), array(), array(), '', '' );
		}
		++self::$depth;
		$state = self::stash();
		$prev  = $GLOBALS['post'] ?? null;
		try {
			if ( $post ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- dynamic blocks read the global post; restored below.
				$GLOBALS['post'] = $post;
				setup_postdata( $post );
			}
			// What the page head does first (wp_enqueue_scripts): every block builds its CSS and enqueues its files.
			$frontend = class_exists( 'Flx_Blocks_Frontend' ) ? Flx_Blocks_Frontend::get_instance() : null;
			if ( $frontend instanceof Flx_Blocks_Frontend ) {
				$frontend->frontend_css_parse( parse_blocks( $content ) );
			}
			/** This filter is documented in wp-includes/post-template.php */
			$html = self::text( apply_filters( 'the_content', $content ) );
			$html = str_replace( ']]>', ']]&gt;', $html );

			$collected = self::collect( $state );
		} finally {
			self::restore( $state );
			if ( $post ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- put back.
				$GLOBALS['post'] = $prev;
				if ( $prev instanceof WP_Post ) {
					setup_postdata( $prev );
				} else {
					wp_reset_postdata();
				}
			}
			--self::$depth;
		}

		return new FLXBK_Rendered(
			$html,
			$collected['css'],
			$blocks,
			$collected['styles'],
			$collected['scripts'],
			$collected['fonts'],
			$collected['schema'],
			$collected['head'],
			$collected['footer']
		);
	}

	/**
	 * Saves the request's asset state and empties it.
	 *
	 * @return array{style_queue:list<string>,script_queue:list<string>,css:array<string,string>,custom:array<string,string>,gfonts:array<string,mixed>,footer_gfonts:array<string,mixed>,fe_gfonts:array<string,mixed>,fe_footer_gfonts:array<string,mixed>,faq:mixed,supports:list<string>,modules:list<string>}
	 */
	private static function stash(): array {
		$wp_styles  = wp_styles();
		$wp_scripts = wp_scripts();
		$state      = array(
			'style_queue'      => self::strings( $wp_styles->queue ),
			'script_queue'     => self::strings( $wp_scripts->queue ),
			'css'              => array(),
			'custom'           => array(),
			'gfonts'           => array(),
			'footer_gfonts'    => array(),
			'fe_gfonts'        => array(),
			'fe_footer_gfonts' => array(),
			'faq'              => null,
			'supports'         => self::support_selectors(),
			'modules'          => self::enqueued_modules(),
		);
		$wp_styles->queue  = array();
		$wp_scripts->queue = array();
		if ( class_exists( 'Flx_Blocks_CSS' ) ) {
			$state['css']                  = self::string_map( Flx_Blocks_CSS::$styles );
			$state['custom']               = self::string_map( Flx_Blocks_CSS::$custom_styles );
			Flx_Blocks_CSS::$styles        = array();
			Flx_Blocks_CSS::$custom_styles = array();
		}
		if ( class_exists( 'Flx_Blocks_Google_Fonts' ) ) {
			$state['gfonts']                        = self::map( Flx_Blocks_Google_Fonts::$gfonts );
			$state['footer_gfonts']                 = self::map( Flx_Blocks_Google_Fonts::$footer_gfonts );
			Flx_Blocks_Google_Fonts::$gfonts        = array();
			Flx_Blocks_Google_Fonts::$footer_gfonts = array();
		}
		if ( class_exists( 'Flx_Blocks_Frontend' ) ) {
			$state['fe_gfonts']                 = self::map( Flx_Blocks_Frontend::$gfonts );
			$state['fe_footer_gfonts']          = self::map( Flx_Blocks_Frontend::$footer_gfonts );
			$state['faq']                       = Flx_Blocks_Frontend::$faq_schema;
			Flx_Blocks_Frontend::$gfonts        = array();
			Flx_Blocks_Frontend::$footer_gfonts = array();
			Flx_Blocks_Frontend::$faq_schema    = null;
		}
		return $state;
	}

	/**
	 * Puts the saved state back. What this render added to the queues is dropped again, so the next
	 * render (or the page itself) starts where it was.
	 *
	 * @param array{style_queue:list<string>,script_queue:list<string>,css:array<string,string>,custom:array<string,string>,gfonts:array<string,mixed>,footer_gfonts:array<string,mixed>,fe_gfonts:array<string,mixed>,fe_footer_gfonts:array<string,mixed>,faq:mixed,supports:list<string>,modules:list<string>} $state Saved state.
	 */
	private static function restore( array $state ): void {
		wp_styles()->queue  = $state['style_queue'];
		wp_scripts()->queue = $state['script_queue'];
		if ( class_exists( 'Flx_Blocks_CSS' ) ) {
			Flx_Blocks_CSS::$styles        = $state['css'];
			Flx_Blocks_CSS::$custom_styles = $state['custom'];
		}
		if ( class_exists( 'Flx_Blocks_Google_Fonts' ) ) {
			Flx_Blocks_Google_Fonts::$gfonts        = $state['gfonts'];
			Flx_Blocks_Google_Fonts::$footer_gfonts = $state['footer_gfonts'];
		}
		if ( class_exists( 'Flx_Blocks_Frontend' ) ) {
			Flx_Blocks_Frontend::$gfonts        = $state['fe_gfonts'];
			Flx_Blocks_Frontend::$footer_gfonts = $state['fe_footer_gfonts'];
			Flx_Blocks_Frontend::$faq_schema    = is_string( $state['faq'] ) ? $state['faq'] : null;
		}
	}

	/**
	 * What the render added.
	 *
	 * @param array{style_queue:list<string>,script_queue:list<string>,css:array<string,string>,custom:array<string,string>,gfonts:array<string,mixed>,footer_gfonts:array<string,mixed>,fe_gfonts:array<string,mixed>,fe_footer_gfonts:array<string,mixed>,faq:mixed,supports:list<string>,modules:list<string>} $state State before the render.
	 * @return array{css:string,styles:list<FLXBK_Asset>,scripts:list<FLXBK_Asset>,fonts:list<string>,schema:list<string>,head:string,footer:string}
	 */
	private static function collect( array $state ): array {
		$wp_styles  = wp_styles();
		$wp_scripts = wp_scripts();

		// Generated block CSS (what Flx_Blocks_CSS prints as flxbk_css / flxbk_custom_css) and block-support CSS.
		$block_css  = class_exists( 'Flx_Blocks_CSS' ) ? implode( '', self::string_map( Flx_Blocks_CSS::$styles ) ) : '';
		$custom_css = class_exists( 'Flx_Blocks_CSS' ) ? implode( '', self::string_map( Flx_Blocks_CSS::$custom_styles ) ) : '';
		$css        = $block_css . self::support_css( $state['supports'] ) . $custom_css;

		// Inline-only handles the blocks enqueue (e.g. a heading's font CSS) also hold CSS.
		$style_handles  = self::strings( $wp_styles->queue );
		$script_handles = self::strings( $wp_scripts->queue );
		$styles         = self::style_assets( $wp_styles, $style_handles );
		$scripts        = self::script_assets( $wp_scripts, $script_handles );
		$modules        = array_values( array_diff( self::enqueued_modules(), $state['modules'] ) );
		foreach ( $modules as $id ) {
			$scripts[] = self::module_asset( $id );
		}

		$fonts  = self::font_urls();
		$schema = array();
		$faq = class_exists( 'Flx_Blocks_Frontend' ) ? Flx_Blocks_Frontend::$faq_schema : null;
		if ( is_string( $faq ) && '' !== $faq ) {
			if ( preg_match_all( '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', self::text( apply_filters( 'flxbk_faq_schema', $faq ) ), $m ) ) {
				foreach ( $m[1] as $doc ) {
					$schema[] = trim( $doc );
				}
			}
		}

		// Ready markup, printed by WordPress itself so tags, inline code and filters match the page.
		$head = self::print_styles( $wp_styles, $style_handles );
		foreach ( $fonts as $url ) {
			$head .= '<link rel="stylesheet" href="' . esc_url( $url ) . "\">\n";
		}
		if ( '' !== $css ) {
			$head .= '<style id="flxbk-css">' . self::style_text( $css ) . "</style>\n";
		}
		foreach ( $schema as $doc ) {
			$head .= '<script type="application/ld+json">' . $doc . "</script>\n";
		}
		$printed = self::print_scripts( $wp_scripts, $script_handles );
		$head   .= $printed['head'];
		$footer  = $printed['footer'] . self::print_modules( $modules );

		return array(
			'css'     => $css,
			'styles'  => $styles,
			'scripts' => $scripts,
			'fonts'   => $fonts,
			'schema'  => $schema,
			'head'    => $head,
			'footer'  => $footer,
		);
	}

	/**
	 * Block tree → flat list.
	 *
	 * @param array<mixed>      $blocks Parsed blocks.
	 * @param string|null       $parent Parent clientId.
	 * @param int               $depth  Depth.
	 * @param list<FLXBK_Block> $out    Collected blocks.
	 */
	private static function walk( array $blocks, ?string $parent, int $depth, array &$out ): void {
		$registry = WP_Block_Type_Registry::get_instance();
		$order    = 0;
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			$name = isset( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : '';
			$html = isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ? $block['innerHTML'] : '';
			if ( '' === $name ) {
				if ( '' === trim( $html ) ) {
					continue; // Whitespace between blocks.
				}
				$name = 'core/freeform';
			}
			$id    = null === $parent ? (string) $order : $parent . '.' . $order;
			$type  = $registry->get_registered( $name );
			$out[] = new FLXBK_Block(
				$id,
				$parent,
				$order,
				$depth,
				$name,
				isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? self::map( $block['attrs'] ) : array(),
				$html,
				$type instanceof WP_Block_Type && $type->is_dynamic()
			);
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::walk( $block['innerBlocks'], $id, $depth + 1, $out );
			}
			++$order;
		}
	}

	/**
	 * Stylesheets with their dependencies, dependencies first.
	 *
	 * @param WP_Styles    $wp_styles Styles registry.
	 * @param list<string> $handles   Enqueued handles.
	 * @return list<FLXBK_Asset>
	 */
	private static function style_assets( WP_Styles $wp_styles, array $handles ): array {
		$out = array();
		foreach ( self::ordered( $wp_styles, $handles ) as $handle ) {
			$dep = $wp_styles->registered[ $handle ] ?? null;
			if ( ! $dep instanceof _WP_Dependency ) {
				continue;
			}
			$after = $wp_styles->get_data( $handle, 'after' );
			$out[] = new FLXBK_Asset(
				'style',
				$handle,
				self::src_url( $wp_styles, $dep->src ),
				self::version( $wp_styles, $dep->ver ),
				is_string( $dep->args ) && '' !== $dep->args ? $dep->args : 'all',
				self::strings( $dep->deps ),
				'',
				is_array( $after ) ? implode( "\n", self::strings( $after ) ) : '',
				'',
				false,
				''
			);
		}
		return $out;
	}

	/**
	 * Scripts with their dependencies, dependencies first.
	 *
	 * @param WP_Scripts   $wp_scripts Scripts registry.
	 * @param list<string> $handles    Enqueued handles.
	 * @return list<FLXBK_Asset>
	 */
	private static function script_assets( WP_Scripts $wp_scripts, array $handles ): array {
		$out = array();
		foreach ( self::ordered( $wp_scripts, $handles ) as $handle ) {
			$dep = $wp_scripts->registered[ $handle ] ?? null;
			if ( ! $dep instanceof _WP_Dependency ) {
				continue;
			}
			$before   = $wp_scripts->get_data( $handle, 'before' );
			$after    = $wp_scripts->get_data( $handle, 'after' );
			$data     = $wp_scripts->get_data( $handle, 'data' );
			$strategy = $wp_scripts->get_data( $handle, 'strategy' );
			$group    = $wp_scripts->get_data( $handle, 'group' );
			$out[]    = new FLXBK_Asset(
				'script',
				$handle,
				self::src_url( $wp_scripts, $dep->src ),
				self::version( $wp_scripts, $dep->ver ),
				null,
				self::strings( $dep->deps ),
				is_array( $before ) ? implode( "\n", self::strings( $before ) ) : '',
				is_array( $after ) ? implode( "\n", self::strings( $after ) ) : '',
				is_string( $data ) ? $data : '',
				is_numeric( $group ) && (int) $group > 0,
				is_string( $strategy ) && in_array( $strategy, array( 'defer', 'async' ), true ) ? $strategy : ''
			);
		}
		return $out;
	}

	/**
	 * A script module the content enqueued.
	 *
	 * @param string $id Module id.
	 */
	private static function module_asset( string $id ): FLXBK_Asset {
		$reg  = self::registered_modules();
		$row  = $reg[ $id ] ?? array();
		$src  = isset( $row['src'] ) && is_string( $row['src'] ) ? $row['src'] : '';
		$deps = array();
		foreach ( isset( $row['dependencies'] ) && is_array( $row['dependencies'] ) ? $row['dependencies'] : array() as $d ) {
			if ( is_array( $d ) && isset( $d['id'] ) && is_string( $d['id'] ) ) {
				$deps[] = $d['id'];
			}
		}
		$ver = $row['version'] ?? null;
		return new FLXBK_Asset( 'module', $id, '' !== $src ? $src : null, is_string( $ver ) ? $ver : null, null, $deps, '', '', '', true, '' );
	}

	/**
	 * Handles plus their dependencies in load order.
	 *
	 * @param WP_Dependencies $deps    Registry.
	 * @param list<string>    $handles Handles.
	 * @return list<string>
	 */
	private static function ordered( WP_Dependencies $deps, array $handles ): array {
		$out  = array();
		$seen = array();
		$add  = static function ( string $h ) use ( &$add, &$out, &$seen, $deps ): void {
			if ( isset( $seen[ $h ] ) ) {
				return;
			}
			$seen[ $h ] = true;
			$reg        = $deps->registered[ $h ] ?? null;
			if ( ! $reg instanceof _WP_Dependency ) {
				return;
			}
			foreach ( self::strings( $reg->deps ) as $d ) {
				$add( $d );
			}
			$out[] = $h;
		};
		foreach ( $handles as $h ) {
			$add( $h );
		}
		return $out;
	}

	/**
	 * Prints stylesheets the WordPress way into a string.
	 *
	 * @param WP_Styles    $wp_styles Registry.
	 * @param list<string> $handles   Handles.
	 */
	private static function print_styles( WP_Styles $wp_styles, array $handles ): string {
		if ( ! $handles ) {
			return '';
		}
		$copy           = clone $wp_styles;
		$copy->done     = array();
		$copy->to_do    = array();
		$copy->do_concat = false;
		ob_start();
		$copy->do_items( $handles );
		return (string) ob_get_clean();
	}

	/**
	 * Prints scripts the WordPress way into head and footer strings.
	 *
	 * @param WP_Scripts   $wp_scripts Registry.
	 * @param list<string> $handles    Handles.
	 * @return array{head:string,footer:string}
	 */
	private static function print_scripts( WP_Scripts $wp_scripts, array $handles ): array {
		if ( ! $handles ) {
			return array(
				'head'   => '',
				'footer' => '',
			);
		}
		$copy            = clone $wp_scripts;
		$copy->done      = array();
		$copy->to_do     = array();
		$copy->in_footer = array();
		$copy->do_concat = false;
		ob_start();
		$copy->do_items( $handles, 0 );
		$head = (string) ob_get_clean();
		ob_start();
		$copy->do_items( self::strings( $copy->in_footer ), 1 );
		$footer = (string) ob_get_clean();
		return array(
			'head'   => $head,
			'footer' => $footer,
		);
	}

	/**
	 * Script module tags (import map, preloads, modules) for the modules the content enqueued.
	 *
	 * @param list<string> $modules Module ids.
	 */
	private static function print_modules( array $modules ): string {
		if ( ! $modules || ! function_exists( 'wp_script_modules' ) ) {
			return '';
		}
		$api = wp_script_modules();
		ob_start();
		foreach ( array( 'print_import_map', 'print_enqueued_script_modules', 'print_script_module_preloads', 'print_script_module_data' ) as $method ) {
			if ( method_exists( $api, $method ) ) {
				$api->$method();
			}
		}
		if ( function_exists( 'wp_interactivity' ) ) {
			$ia = wp_interactivity();
			$ia->print_client_interactivity_data();
		}
		return (string) ob_get_clean();
	}

	/**
	 * Google Fonts the render asked for, as stylesheet URLs (local copies when that setting is on).
	 *
	 * @return list<string>
	 */
	private static function font_urls(): array {
		if ( ! class_exists( 'Flx_Blocks_Frontend' ) ) {
			return array();
		}
		$fe = Flx_Blocks_Frontend::get_instance();
		if ( ! $fe instanceof Flx_Blocks_Frontend ) {
			return array();
		}
		$gf = class_exists( 'Flx_Blocks_Google_Fonts' ) ? Flx_Blocks_Google_Fonts::get_instance() : null;
		if ( $gf instanceof Flx_Blocks_Google_Fonts ) {
			// Moves the fonts the CSS collected over to the front end list, as wp_head / wp_footer do.
			$gf->frontend_gfonts();
			$gf->frontend_footer_gfonts();
		}
		$urls = array();
		foreach ( array( Flx_Blocks_Frontend::$gfonts, Flx_Blocks_Frontend::$footer_gfonts ) as $list ) {
			if ( empty( $list ) || ! apply_filters( 'flxbk_print_google_fonts', true ) ) {
				continue;
			}
			ob_start();
			$fe->print_gfonts( $list );
			$tag = (string) ob_get_clean();
			if ( preg_match( '/href="([^"]+)"/', $tag, $m ) ) {
				$urls[] = html_entity_decode( $m[1], ENT_QUOTES );
			}
		}
		return array_values( array_unique( $urls ) );
	}

	/**
	 * A filter result as text.
	 *
	 * @param mixed $value Filtered value.
	 */
	private static function text( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Selectors in the block-supports style store.
	 *
	 * @return list<string>
	 */
	private static function support_selectors(): array {
		if ( ! class_exists( 'WP_Style_Engine_CSS_Rules_Store' ) ) {
			return array();
		}
		$store = WP_Style_Engine_CSS_Rules_Store::get_store( 'block-supports' );
		return $store ? self::strings( array_keys( $store->get_all_rules() ) ) : array();
	}

	/**
	 * Block-support CSS (layout, spacing, elements) added after the given selectors.
	 *
	 * @param list<string> $before Selectors present before the render.
	 */
	private static function support_css( array $before ): string {
		if ( ! class_exists( 'WP_Style_Engine_CSS_Rules_Store' ) || ! class_exists( 'WP_Style_Engine_Processor' ) ) {
			return '';
		}
		$store = WP_Style_Engine_CSS_Rules_Store::get_store( 'block-supports' );
		if ( ! $store ) {
			return '';
		}
		$rules = $store->get_all_rules();
		$new   = array_diff_key( $rules, array_flip( $before ) );
		if ( ! $new ) {
			return '';
		}
		$processor = new WP_Style_Engine_Processor();
		$processor->add_rules( array_values( $new ) );
		return (string) $processor->get_css( array( 'optimize' => true ) );
	}

	/**
	 * Script modules marked for enqueue (WP 6.5+). The API keeps them private, so this reads them through
	 * reflection and returns an empty list when the structure is not the expected one.
	 *
	 * @return list<string>
	 */
	private static function enqueued_modules(): array {
		$out = array();
		foreach ( self::registered_modules() as $id => $row ) {
			if ( ! empty( $row['enqueue'] ) ) {
				$out[] = (string) $id;
			}
		}
		if ( function_exists( 'wp_script_modules' ) ) {
			$queue = self::private_prop( wp_script_modules(), 'queue' );
			foreach ( is_array( $queue ) ? $queue : array() as $id ) {
				if ( is_string( $id ) ) {
					$out[] = $id;
				}
			}
			$early = self::private_prop( wp_script_modules(), 'enqueued_before_registered' );
			foreach ( is_array( $early ) ? array_keys( $early ) : array() as $id ) {
				$out[] = (string) $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Registered script modules.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function registered_modules(): array {
		if ( ! function_exists( 'wp_script_modules' ) ) {
			return array();
		}
		$reg = self::private_prop( wp_script_modules(), 'registered' );
		$out = array();
		foreach ( is_array( $reg ) ? $reg : array() as $id => $row ) {
			if ( is_array( $row ) ) {
				$out[ (string) $id ] = self::map( $row );
			}
		}
		return $out;
	}

	/**
	 * A private property, or null.
	 *
	 * @param object $obj  Object.
	 * @param string $name Property.
	 * @return mixed
	 */
	private static function private_prop( object $obj, string $name ) {
		try {
			$ref = new ReflectionProperty( $obj, $name );
		} catch ( ReflectionException $e ) {
			return null;
		}
		$ref->setAccessible( true );
		return $ref->getValue( $obj );
	}

	/**
	 * Colour classes of the palette saved in the editor (what flx-blocks prints as #flxbk_palette_css).
	 */
	private static function palette_classes(): string {
		$raw     = get_option( 'flxbk_colors' );
		$palette = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $palette ) || empty( $palette['palette'] ) || ! is_array( $palette['palette'] ) ) {
			return '';
		}
		$css = '';
		foreach ( $palette['palette'] as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['slug'], $item['color'] ) || ! is_string( $item['slug'] ) || ! is_string( $item['color'] ) ) {
				continue;
			}
			$slug = esc_attr( $item['slug'] );
			$col  = esc_attr( $item['color'] );
			$css .= ':root .has-' . $slug . '-color{color:' . $col . '}:root .has-' . $slug . '-background-color{background-color:' . $col . '}';
		}
		return $css;
	}

	/**
	 * Inline CSS of an inline-only handle.
	 *
	 * @param WP_Styles $wp_styles Registry.
	 * @param string    $handle    Handle.
	 */
	private static function inline_of( WP_Styles $wp_styles, string $handle ): string {
		$after = $wp_styles->get_data( $handle, 'after' );
		return is_array( $after ) ? implode( '', self::strings( $after ) ) : '';
	}

	/**
	 * Absolute URL of a registered src.
	 *
	 * @param WP_Dependencies $deps Registry.
	 * @param mixed           $src  src.
	 */
	private static function src_url( WP_Dependencies $deps, $src ): ?string {
		if ( ! is_string( $src ) || '' === $src ) {
			return null;
		}
		if ( ! preg_match( '|^(https?:)?//|', $src ) ) {
			$base = property_exists( $deps, 'base_url' ) && is_string( $deps->base_url ) ? $deps->base_url : '';
			$src  = $base . $src;
		}
		return $src;
	}

	/**
	 * Version a registered asset prints (false = the WordPress version, null = none).
	 *
	 * @param WP_Dependencies $deps Registry.
	 * @param mixed           $ver  ver.
	 */
	private static function version( WP_Dependencies $deps, $ver ): ?string {
		if ( null === $ver ) {
			return null;
		}
		if ( false === $ver ) {
			$def = property_exists( $deps, 'default_version' ) ? $deps->default_version : '';
			return is_string( $def ) && '' !== $def ? $def : get_bloginfo( 'version' );
		}
		return is_scalar( $ver ) ? (string) $ver : null;
	}

	/**
	 * CSS safe inside a <style> element.
	 *
	 * @param string $css CSS.
	 */
	private static function style_text( string $css ): string {
		return str_ireplace( '</style', '<\/style', $css );
	}

	/**
	 * Strings of a list.
	 *
	 * @param mixed $list Value.
	 * @return list<string>
	 */
	private static function strings( $list ): array {
		$out = array();
		foreach ( is_array( $list ) ? $list : array() as $v ) {
			if ( is_string( $v ) ) {
				$out[] = $v;
			}
		}
		return $out;
	}

	/**
	 * String values by string key.
	 *
	 * @param mixed $map Value.
	 * @return array<string,string>
	 */
	private static function string_map( $map ): array {
		$out = array();
		foreach ( is_array( $map ) ? $map : array() as $k => $v ) {
			if ( is_string( $v ) ) {
				$out[ (string) $k ] = $v;
			}
		}
		return $out;
	}

	/**
	 * Array with string keys.
	 *
	 * @param mixed $map Value.
	 * @return array<string,mixed>
	 */
	private static function map( $map ): array {
		$out = array();
		foreach ( is_array( $map ) ? $map : array() as $k => $v ) {
			$out[ (string) $k ] = $v;
		}
		return $out;
	}
}
