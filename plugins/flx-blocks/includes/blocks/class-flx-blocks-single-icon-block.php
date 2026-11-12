<?php
/**
 * Class to Build the Single Icon Block.
 *
 * @package Flux Block Editor
 */

use FluxWP\Blocks\Site_Styles\Contracts\Scopes_Site_Styles;
use FluxWP\Blocks\Site_Styles\Contracts\Supports_Site_Styles;
use FluxWP\Blocks\Utils\Cast;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to Build the Single Icon Block.
 *
 * @category class
 */
class Flx_Blocks_Single_Icon_Block extends Flx_Blocks_Abstract_Block implements Supports_Site_Styles, Scopes_Site_Styles {
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
	protected $block_name = 'single-icon';

	/**
	 * Block determines if styles need to be loaded for block.
	 *
	 * @var string
	 */
	protected $has_style = false;

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
		if ( isset( $attributes ) && is_array( $attributes ) ) {
			$css->set_selector( '.fxt-svg-item-' . $unique_id . ' .fxb-svg-icon-wrap, .fxt-svg-style-stacked.fxt-svg-item-' . $unique_id . ' .fxb-svg-icon-wrap' );
			$css->render_color_output( $attributes, 'color', 'color' );

			$css->render_responsive_size(
				$attributes,
				array(
					'size',
					'tabletSize',
					'mobileSize',
				),
				'font-size'
			);

			$css->render_measure_output( $attributes, 'margin', 'margin', array( 'unit_key' => 'marginUnit' ) );

			if ( isset( $attributes['style'] ) && 'stacked' === $attributes['style'] ) {
				$css->render_color_output( $attributes, 'background', 'background' );
				$css->render_color_output( $attributes, 'border', 'border-color' );
				$css->render_range( $attributes, 'borderWidth', 'border-width' );
				$css->render_range( $attributes, 'borderRadius', 'border-radius', '%' );
				$css->render_measure_output( $attributes, 'padding', 'padding', array( 'unit_key' => 'paddingUnit' ) );
				$css->set_selector( '.fxt-svg-item-' . $unique_id . ':hover .fxb-svg-icon-wrap' );
				$css->render_color_output( $attributes, 'hBackground', 'background' );
				$css->render_color_output( $attributes, 'hBorder', 'border-color' );
			}

			// Hover.
			$css->set_selector( '.fxt-svg-item-' . $unique_id . ':hover .fxb-svg-icon-wrap' );
			$css->render_color_output( $attributes, 'hColor', 'color' );
		}

		return $css->css_output();
	}
	/**
	 * Return dynamically generated HTML for block
	 *
	 * @param $attributes
	 * @param $unique_id
	 * @param $content
	 * @param WP_Block $block_instance The instance of the WP_Block class that represents the block being rendered.
	 *
	 * @return mixed
	 */
	public function build_html( $attributes, $unique_id, $content, $block_instance ) {
		if ( strpos( $content, 'fxb-tooltip-hidden-content') !== false ) {
			$this->enqueue_script( 'flx-blocks-tippy' );
		}

		return $content;
	}
	/**
	 * Registers scripts and styles.
	 */
	public function register_scripts() {

		// Skip calling parent because this block does not have a dedicated CSS or JS file.

		// If in the backend, bail out.
		if ( is_admin() ) {
			return;
		}
		if ( apply_filters( 'flxbk_check_if_rest', false ) && flxbk_is_rest() ) {
			return;
		}
		wp_register_script( 'flx-blocks-popper', FLXBK_URL . 'includes/assets/js/popper.min.js', array(), FLXBK_VERSION, true );
		wp_register_script( 'flx-blocks-tippy', FLXBK_URL . 'includes/assets/js/fxb-tippy.min.js', array( 'flx-blocks-popper' ), FLXBK_VERSION, true );
	}

	/**
	 * The editor paints every style inline, so every Flux-only value goes through the overlay.
	 * The stroke width is in the saved markup, which no site value can reach, so it stays per icon.
	 *
	 */
	public function site_styles_overlay_attributes(): array {
		return array_merge( $this->site_styles_icon_attributes(), $this->site_styles_box_attributes() );
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function site_styles_attributes_map(): array {
		return [ 'color' => 'color.text' ];
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function site_styles_supports(): array {
		return [
			'color' => [
				'text'                            => true,
				'background'                      => false,
				'gradients'                       => false,
				'__experimentalSkipSerialization' => true,
			],
		];
	}

	/**
	 * Core's default root, the block wrapper on the front end and in the editor. The icon
	 * inherits the color: its token chain falls back to `currentColor`.
	 *
	 */
	public function site_styles_selectors(): array {
		return [];
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function site_styles_scope_attribute(): string {
		return 'style';
	}

	/**
	 * The icon's own style, from its `style` attribute.
	 *
	 *
	 * @param array<string, mixed> $attributes The instance's attributes.
	 *
	 * @return string|null The style, or null when the instance has none.
	 */
	public function site_styles_scope_style( array $attributes ): ?string {
		return isset( $attributes['style'] ) ? Cast::to_string( $attributes['style'] ) : null;
	}

	/**
	 * Only Stacked icons render the box, so Default icons take the icon values only.
	 *
	 */
	public function site_styles_scoped_attributes(): array {
		return [ 'default' => $this->site_styles_icon_attributes() ];
	}

	/**
	 * The site attributes every icon style renders.
	 *
	 *
	 * @return list<string> Attribute names.
	 */
	private function site_styles_icon_attributes(): array {
		return [ 'size', 'tabletSize', 'mobileSize', 'hColor', 'margin', 'tabletMargin', 'mobileMargin', 'marginUnit' ];
	}

	/**
	 * The site attributes only the Stacked style renders: the box around the icon.
	 *
	 *
	 * @return list<string> Attribute names.
	 */
	private function site_styles_box_attributes(): array {
		return [ 'background', 'hBackground', 'border', 'hBorder', 'borderRadius', 'borderWidth', 'padding', 'tabletPadding', 'mobilePadding', 'paddingUnit' ];
	}
}

Flx_Blocks_Single_Icon_Block::get_instance();
