<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Site_Styles;

use FluxWP\Blocks\Site_Styles\Contracts\Cascades_Site_Styles;
use FluxWP\Blocks\Site_Styles\Contracts\Groups_Site_Styles;
use FluxWP\Blocks\Site_Styles\Contracts\Legacy_Group_Attributes;
use FluxWP\Blocks\Site_Styles\Contracts\Prints_Site_Rules;
use FluxWP\Blocks\Site_Styles\Contracts\Scopes_Site_Styles;

/**
 * Gives the editor scripts the site-level styles state, as
 * `window.flxbkSiteStyles`, before the early block filters run: the FSE
 * mode flag and, in FSE mode, the supported blocks' entries. The entries leave
 * out `selectors`: the editor gets them from core's server-side block
 * definitions, which Flux's JS registration doesn't override.
 *
 */
final class Editor_Params {

	/**
	 * The script that registers the early `blocks.registerBlockType` filters.
	 *
	 */
	public const EARLY_FILTERS_HANDLE = 'flx-blocks-early-filters-js';

	/**
	 * The editor plugins script, which hosts the Flux panel. It runs before
	 * the early filters script.
	 *
	 */
	public const PLUGIN_HANDLE = 'flx-blocks-plugin-js';

	/**
	 * The supported blocks.
	 *
	 *
	 * @var Supported_Blocks
	 */
	private Supported_Blocks $blocks;

	/**
	 *
	 * @param Supported_Blocks $blocks The supported blocks.
	 */
	public function __construct( Supported_Blocks $blocks ) {
		$this->blocks = $blocks;
	}

	/**
	 * Adds the data before each script that reads it, whichever runs first.
	 * Runs after the scripts are enqueued.
	 *
	 */
	public function add_script_data(): void {
		$is_fse_mode = flxbk_is_fse_mode();
		$blocks      = [];

		if ( $is_fse_mode ) {
			foreach ( $this->blocks->all() as $name => $block ) {
				$blocks[ $name ] = [
					'slug'          => $block->get_slug(),
					'exclude'       => Supported_Blocks::excluded_attributes( $block ),
					'overlay'       => $block->site_styles_overlay_attributes(),
					'attributesMap' => $block->site_styles_attributes_map(),
					'supports'      => $block->site_styles_supports(),
				];

				if ( $block instanceof Scopes_Site_Styles ) {
					$blocks[ $name ]['scope'] = [
						'attribute'  => $block->site_styles_scope_attribute(),
						'attributes' => $block->site_styles_scoped_attributes(),
					];
				}

				if ( $block instanceof Prints_Site_Rules ) {
					$blocks[ $name ]['siteRules'] = true;
				}

				if ( $block instanceof Groups_Site_Styles ) {
					$blocks[ $name ]['groups'] = $block->site_styles_attribute_groups();
				}

				if ( $block instanceof Legacy_Group_Attributes ) {
					$blocks[ $name ]['legacyGroups'] = $block->site_styles_legacy_group_attributes();
				}

				if ( $block instanceof Cascades_Site_Styles ) {
					$blocks[ $name ]['cascades'] = $block->site_styles_cascades();
				}
			}
		}

		$data = wp_json_encode(
			[
				'isFseMode' => $is_fse_mode,
				// An object in JSON even when empty.
				'blocks'    => (object) $blocks,
			]
		);

		if ( false === $data ) {
			return;
		}

		foreach ( [ self::PLUGIN_HANDLE, self::EARLY_FILTERS_HANDLE ] as $handle ) {
			wp_add_inline_script( $handle, 'window.flxbkSiteStyles = window.flxbkSiteStyles || ' . $data . ';', 'before' );
		}
	}
}
