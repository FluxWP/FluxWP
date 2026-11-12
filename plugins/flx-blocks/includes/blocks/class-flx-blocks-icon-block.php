<?php
/**
 * Class to Build the Icon Block.
 *
 * @package Flux Block Editor
 */

use FluxWP\Blocks\Site_Styles\Contracts\Supports_Site_Styles;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to Build the Icon Block.
 *
 * @category class
 */
class Flx_Blocks_Icon_Block extends Flx_Blocks_Abstract_Block implements Supports_Site_Styles {
	/**
	 * Instance of this class
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Block name within this namespace.
	 *
	 * @var string
	 */
	protected $block_name = 'icon';

	/**
	 * Instance Control
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Builds CSS for block.
	 *
	 * @param array $attributes the blocks attributes.
	 * @param string $css the css class for blocks.
	 * @param string $unique_id the blocks attr ID.
	 * @param string $unique_style_id the blocks alternate ID for queries.
	 */
	public function build_css( $attributes, $css, $unique_id, $unique_style_id ) {
		$css->set_style_id( 'fxb-' . $this->block_name . $unique_style_id );

		/*
		 * This if is needed for icons blocks that created before blocks 3.0 that
		 * haven't been updated to use single-icon innerBlocks.
		 */
		if ( ! empty( $attributes['icons'] ) && is_array( $attributes['icons'] ) ) {
			foreach ( $attributes['icons'] as $icon_key => $icon_value ) {
				if ( is_array( $icon_value ) ) {
					$css->set_selector( '.fxt-svg-icons' . $unique_id . ' .fxt-svg-item-' . $icon_key . ' .fxb-svg-icon-wrap' );
					$css->render_color_output( $icon_value, 'color', 'color' );
					$css->render_responsive_size( $icon_value, array(
						'size',
						'tabletSize',
						'mobileSize'
					), 'font-size' );
					if ( isset( $icon_value['style'] ) && 'stacked' === $icon_value['style'] ) {
						$css->render_color_output( $icon_value, 'background', 'background' );
						$css->render_color_output( $icon_value, 'border', 'border-color' );
						$css->render_range( $icon_value, 'borderWidth', 'border-width' );
						$css->render_range( $icon_value, 'borderRadius', 'border-radius' );
						$css->render_measure_output( $icon_value, 'padding', 'padding' );
					}
					$css->render_measure_output( $icon_value, 'margin', 'margin' );
					// Hover.
					$css->set_selector( '.fxt-svg-icons' . $unique_id . ' .fxt-svg-item-' . $icon_key . ':hover .fxb-svg-icon-wrap' );
					$css->render_color_output( $icon_value, 'hColor', 'color' );
					$css->render_color_output( $icon_value, 'hBackground', 'background' );
					$css->render_color_output( $icon_value, 'hBorder', 'border-color' );
				}
			}
		}
		$css->set_selector( '.wp-block-flx-icon.fxt-svg-icons' . $unique_id );
		$align_args = array(
			'desktop_key' => 'textAlignment',
			'tablet_key'  => 'tabletTextAlignment',
			'mobile_key'  => 'mobileTextAlignment',
		);
		$css->render_flex_align( $attributes, 'textAlignment', $align_args );
		if( isset($attributes['wrapIcons'] ) && $attributes['wrapIcons'] ) {
			$css->add_property('flex-wrap', 'wrap');
		}
		$css->render_gap( $attributes );

		return $css->css_output();
	}

	/**
	 * The editor paints the layout inline. The block and vertical alignments are
	 * classes in the saved markup, which no site value can reach, so they stay per icon.
	 *
	 */
	public function site_styles_overlay_attributes(): array {
		return [ 'textAlignment', 'tabletTextAlignment', 'mobileTextAlignment', 'wrapIcons', 'gap', 'gapUnit' ];
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function site_styles_attributes_map(): array {
		return [];
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function site_styles_supports(): array {
		return [];
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function site_styles_selectors(): array {
		return [];
	}
}

Flx_Blocks_Icon_Block::get_instance();
