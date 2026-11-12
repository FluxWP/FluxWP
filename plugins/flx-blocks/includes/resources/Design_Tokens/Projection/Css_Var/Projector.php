<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Projection\Css_Var;

use FluxWP\Blocks\Design_Tokens\Database\Active_Token_Library_Store;
use FluxWP\Blocks\Design_Tokens\Projection\Contracts\Abstract_Css_Projector;
use FluxWP\Blocks\Design_Tokens\Projection\Media_Queries;
use FluxWP\Blocks\Design_Tokens\Registry\Token_Registry;
use FluxWP\Blocks\Design_Tokens\Resolver\Token_Resolver;
use FluxWP\Blocks\Design_Tokens\Utils\Location;
use Throwable;
use FluxWP\Blocks\Design_Tokens\Resolver\Effective_Version;

/**
 * Projects the resolved token library into the WordPress style pipeline.
 *
 * Reacts to WordPress hooks to inject the --fxb-token--* custom properties into KB's existing inline
 * styles and feeds the legacy color/font-size filters — all gated on Token_Registry::is_active()
 * so a deactivated registry leaves KB's behavior untouched.
 *
 * This is the non-coercive surface: a custom property placed in :root styles nothing until something
 * references it. Only the single active library is emitted — its canonical --fxb-token--<id> custom properties
 * at :root — so block content and the preset/slot bridges (which read the canonical names) resolve against
 * it. Multiple libraries may still be stored; the active-library pointer selects the one library that is emitted, and
 * switching the pointer re-emits with the new library's values. Only the active library's tokens reach the coercive
 * native-block and theme.json styling paths.
 *
 */
final class Projector extends Abstract_Css_Projector {

	/**
	 * @var Token_Registry
	 */
	private Token_Registry $registry;

	/**
	 * @var Token_Resolver
	 */
	private Token_Resolver $resolver;


	/**
	 * Owns the active-library pointer, read at build time so the projection follows the active library.
	 *
	 *
	 * @var Active_Token_Library_Store
	 */
	private Active_Token_Library_Store $active;

	/**
	 * @var Css_Builder
	 */
	private Css_Builder $css_builder;

	/**
	 * @var Legacy_Filter_Bridge
	 */
	private Legacy_Filter_Bridge $bridge;

	/**
	 * Supplies the cache version: the store version, plus the theme Style Guide signature when there is
	 * one, so a Customizer save invalidates this cache even though it bumps no store version.
	 *
	 *
	 * @var Effective_Version
	 */
	private Effective_Version $versions;

	/**
	 *
	 * @param Token_Registry             $registry    The token registry.
	 * @param Token_Resolver             $resolver    The token resolver.
	 * @param Active_Token_Library_Store $active      Owns the active-library pointer.
	 * @param Css_Builder                $css_builder The CSS-variable builder.
	 * @param Legacy_Filter_Bridge       $bridge      Rewrites the legacy palette filter off-theme.
	 * @param Effective_Version          $versions    Supplies the effective cache version for a library.
	 */
	public function __construct(
		Token_Registry $registry,
		Token_Resolver $resolver,
		Active_Token_Library_Store $active,
		Css_Builder $css_builder,
		Legacy_Filter_Bridge $bridge,
		Effective_Version $versions
	) {
		$this->registry    = $registry;
		$this->resolver    = $resolver;
		$this->active      = $active;
		$this->css_builder = $css_builder;
		$this->bridge      = $bridge;
		$this->versions    = $versions;
	}

	/**
	 * Append the projected CSS to the front-end global-variables handle.
	 *
	 *
	 * @return void
	 */
	public function enqueue_front_end(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$css = $this->css();
		if ( $css !== '' ) {
			wp_add_inline_style( 'flx-blocks-global-variables', $css );
		}
	}

	/**
	 * Append the projected CSS to the editor global-styles handle.
	 *
	 * Only runs on requests where the block editor will load its scripts, determined
	 * by matching known editor page slugs. Can be overridden with the
	 * `flxbk_load_editor_token_vars` filter.
	 *
	 *
	 * @return void
	 */
	public function enqueue_editor(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		/**
		 * Whether to append the token CSS to the editor global-styles handle.
		 *
		 * @param bool $load True on known block-editor page slugs.
		 */
		if ( ! apply_filters( 'flxbk_load_editor_token_vars', Location::is_block_editor() ) ) {
			return;
		}

		$css = $this->css();
		if ( $css !== '' ) {
			wp_add_inline_style( 'flx-blocks-global-editor-styles', $css );
		}
	}

	/**
	 *
	 * @param array<string,string> $colors
	 *
	 * @return array<string,string>
	 */
	public function filter_global_colors( array $colors ): array {
		if ( ! $this->is_active() ) {
			return $colors;
		}

		return $this->bridge->global_colors( $colors );
	}

	/**
	 * Build the projected CSS for the single active token library, using the per-request memo and object cache
	 * so repeated calls within the same request are free.
	 *
	 * The active library is resolved to its canonical `--fxb-token--*` maps, then the builder emits the one `:root`
	 * block (canonical token layer, slot bridges, and responsive redeclarations). An active library whose stored
	 * document cannot be resolved (e.g. an alias cycle introduced by a direct DB write that bypassed the REST
	 * validation gate) yields an empty string rather than a fatal, so the page falls back to KB's existing
	 * variables without crashing.
	 *
	 *
	 * @return string
	 */
	public function css(): string {
		try {
			$active   = $this->active->get();
			$resolved = $this->resolver->resolve( $active );
			$version  = $this->versions->for_slug( $active );
		} catch ( Throwable $e ) {
			return '';
		}

		return $this->css_builder->css_for_version( $resolved, $active, $version, Media_Queries::all() );
	}

	/**
	 * Whether token projection is active.
	 *
	 *
	 * @return bool
	 */
	private function is_active(): bool {
		return $this->registry->is_active();
	}
}
