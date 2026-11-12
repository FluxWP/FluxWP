<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Site_Styles\Contracts;

/**
 * A block that takes site-level styles in the Flux theme's FSE mode. The
 * block's class describes what the module needs to store, render and show its
 * site-level values.
 *
 */
interface Supports_Site_Styles {

	/**
	 * The block type name, e.g. `flx/singlebtn`. `Flx_Blocks_Abstract_Block` provides it.
	 *
	 *
	 * @return string The block type name.
	 */
	public function get_name(): string;

	/**
	 * The block name without its namespace, e.g. `singlebtn`: the key under
	 * `settings.custom.flxbk` and in the block's render-attributes filter.
	 * `Flx_Blocks_Abstract_Block` provides it.
	 *
	 *
	 * @return string The block slug.
	 */
	public function get_slug(): string;

	/**
	 * Site values that must reach the instance's attributes: settings that change
	 * the markup's classes, settings the block's editor applies as inline styles,
	 * and settings the block's own rule already points at a variable, which
	 * outranks a site rule. No site rule can reach any of them. Every other value
	 * renders through CSS. An instance can't pick the block's default over a
	 * site value, as it can't tell it from no value.
	 *
	 *
	 * @return list<string> Attribute names.
	 */
	public function site_styles_overlay_attributes(): array;

	/**
	 * Attributes that core's Styles screen also edits, stored at a path in the
	 * block's core style so both surfaces edit one value. Core's global
	 * stylesheet renders them, devices included. Only `color.*` paths are
	 * supported for now.
	 *
	 *
	 * @return array<string, string> Attribute name => path in the block's core style, e.g. `color.background`.
	 */
	public function site_styles_attributes_map(): array;

	/**
	 * The core supports the block type gets in FSE mode.
	 *
	 *
	 * @return array<string, mixed> Block supports, as in `block.json`.
	 */
	public function site_styles_supports(): array;

	/**
	 * The core selectors the block type gets in FSE mode.
	 *
	 *
	 * @return array<string, string> Block selectors, as in `block.json`.
	 */
	public function site_styles_selectors(): array;
}
