<?php
/**
 * Class to Build the Single Button Block.
 *
 * @package Flux Block Editor
 */

// cspell:ignore glight glightbox ktblocksvideopop plyr .

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FluxWP\Blocks\Design_Tokens\Database\Active_Token_Library_Store;
use FluxWP\Blocks\Design_Tokens\Resolver\Preset_Resolver;
use FluxWP\Blocks\Site_Styles\Contracts\Cascades_Site_Styles;
use FluxWP\Blocks\Site_Styles\Contracts\Prints_Site_Rules;
use FluxWP\Blocks\Site_Styles\Contracts\Supports_Site_Styles;
use FluxWP\Blocks\Utils\Cast;

/**
 * Class to Build the Single Button.
 *
 * @category class
 */
class Flx_Blocks_Singlebtn_Block extends Flx_Blocks_Abstract_Block implements Supports_Site_Styles, Prints_Site_Rules, Cascades_Site_Styles {

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
	protected $block_name = 'singlebtn';

	/**
	 * Block determines in scripts need to be loaded for block.
	 *
	 * @var string
	 */
	protected $has_style = false;

	/**
	 * Block determines in scripts need to be loaded for block.
	 *
	 * @var string
	 */
	protected $has_script = true;

	/**
	 * The preset slug each value of the older "Button Inherit Styles" attribute maps to, read at render
	 * time when the block carries no `fxbPreset` yet. Fill and empty map to nothing: the default look.
	 *
	 *
	 * @var array<string, string>
	 */
	private const LEGACY_PRESETS = [
		'inherit'           => 'theme-base',
		'inherit-secondary' => 'theme-secondary',
		'outline'           => 'outline',
	];

	/**
	 * The classes each value of the older "Button Inherit Styles" attribute put on the button before
	 * presets existed, so a saved button keeps its look. Read whenever no preset paints the button through
	 * a class: while the token registry is inactive or cannot answer, and on an active registry when the
	 * resolved preset is painted through variables (the default look, a value preset, or a stored slug the
	 * library lacks, which falls back to the default). A value with no entry, such as Fill or empty, gets
	 * the fill class. On an active registry a retired value alone always maps to a class-painted preset,
	 * so a match here comes only from a button that also stores a variable-painted `fxbPreset`.
	 *
	 *
	 * @var array<string, string>
	 */
	private const LEGACY_CLASSES = [
		'inherit'           => 'fxb-btn-global-inherit wp-block-button__link',
		'inherit-secondary' => 'fxb-btn-global-inherit button-style-secondary wp-block-button__link',
		'outline'           => 'fxb-btn-global-outline',
	];

	/**
	 * The slug of the theme's secondary preset.
	 *
	 *
	 * @var string
	 */
	private const THEME_SECONDARY = 'theme-secondary';

	/**
	 * The class a secondary button keeps when its preset falls back to the theme's base preset, so site
	 * CSS written against it keeps applying.
	 *
	 *
	 * @var string
	 */
	private const SECONDARY_CLASS = 'button-style-secondary';

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
	 * The preset slug each value of the older "Button Inherit Styles" attribute maps to.
	 *
	 *
	 * @return array<string, string> inheritStyles value => preset slug.
	 */
	public static function get_legacy_presets(): array {
		return self::LEGACY_PRESETS;
	}

	/**
	 * The classes each value of the older "Button Inherit Styles" attribute painted the button with.
	 *
	 *
	 * @return array<string, string> inheritStyles value => space-separated classes.
	 */
	public static function get_legacy_classes(): array {
		return self::LEGACY_CLASSES;
	}

	/**
	 * Render for block scripts block.
	 *
	 * @param array   $attributes the blocks attributes.
	 * @param boolean $inline true or false based on when called.
	 */
	public function render_scripts( $attributes, $inline = false ) {
		if ( ! empty( $attributes['target'] ) && 'video' === $attributes['target'] ) {
			$this->enqueue_style( 'flxbk-glightbox' );
			$this->enqueue_script( 'flx-blocks-glight-video-init' );
			$gallery_translation_array = [
				'plyr_js'  => FLXBK_URL . 'includes/assets/js/plyr.min.js',
				'plyr_css' => FLXBK_URL . 'includes/assets/css/plyr.min.css',
				'plyr_svg' => FLXBK_URL . 'includes/assets/images/plyr.svg',
			];
			wp_localize_script( 'flx-blocks-glight-video-init', 'flxbk_video_pop', $gallery_translation_array );
		}
	}

	/**
	 * Builds CSS for block.
	 *
	 * @param array              $attributes      the blocks attributes.
	 * @param Flx_Blocks_CSS $css             the css object for blocks.
	 * @param string             $unique_id       the blocks attr ID.
	 * @param string             $unique_style_id the blocks alternate ID for queries.
	 */
	public function build_css( $attributes, $css, $unique_id, $unique_style_id ) {
		$css->set_style_id( 'fxb-' . $this->block_name . $unique_style_id );

		$sizes = [ 'Desktop', 'Tablet', 'Mobile' ];
		foreach ( $sizes as $size ) {
			$this->sized_dynamic_styles( $css, $attributes, $unique_id, $size );
		}
		$css->set_media_state( 'desktop' );

		$width_type = ! empty( $attributes['widthType'] ) ? $attributes['widthType'] : 'auto';
		if ( 'fixed' === $width_type ) {
			$css->set_selector( '.wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button, ul.menu .wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button' );
			$css->render_responsive_range( $attributes, 'width', 'width', 'widthUnit' );
		} else {
			$css->set_selector( 'ul.menu .wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button' );
			$css->add_property( 'width', 'initial' );
		}
		// Standard styles.
		$css->set_selector( '.wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button' );
		$bg_type       = ! empty( $attributes['backgroundType'] ) ? $attributes['backgroundType'] : 'normal';
		$bg_hover_type = ! empty( $attributes['backgroundHoverType'] ) ? $attributes['backgroundHoverType'] : 'normal';

		if ( ! empty( $attributes['color'] ) && ( empty( $attributes['textBackgroundType'] ) || $attributes['textBackgroundType'] === 'normal' ) ) {
			$css->add_property( 'color', $css->render_color( $attributes['color'] ) );
		} elseif ( ! empty( $attributes['textBackgroundType'] ) && $attributes['textBackgroundType'] === 'gradient' ) {
			$css->set_selector( '.wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button .fxt-btn-inner-text' );
			$css->add_property( 'background', $attributes['textGradient'] );
			$css->add_property( '-webkit-background-clip', 'text' );
			$css->add_property( '-webkit-text-fill-color', 'transparent' );
			$css->set_selector( '.wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button' );
		}
		if ( 'normal' === $bg_type && ! empty( $attributes['background'] ) ) {
			$css->add_property( 'background', $css->render_color( $attributes['background'] ) . ( 'gradient' === $bg_hover_type ? ' !important' : '' ) );
		}
		if ( 'gradient' === $bg_type && ! empty( $attributes['gradient'] ) ) {
			$css->add_property( 'background', $attributes['gradient'] . ' !important' );
		}
		$css->render_typography( $attributes, 'typography' );
		$css->render_measure_output( $attributes, 'borderRadius', 'border-radius', [ 'unit_key' => 'borderRadiusUnit' ] );
		$this->render_preset_border( $css, $attributes );
		$css->render_border_styles( $attributes, 'borderStyle', true );
		$this->render_preset_spacing( $css, $attributes );
		$css->render_measure_output( $attributes, 'padding', 'padding', [ 'unit_key' => 'paddingUnit' ] );
		$css->render_measure_output( $attributes, 'margin', 'margin', [ 'unit_key' => 'marginUnit' ] );
		$has_preset_shadow = $this->render_preset_shadow( $css, $attributes );
		if ( ! empty( $attributes['displayShadow'] ) && isset( $attributes['shadow'][0] ) && is_array( $attributes['shadow'][0] ) && $this->has_visible_shadow( $attributes['shadow'][0] ) ) {
			$css->add_property( 'box-shadow', $this->render_button_shadow( $css, $attributes['shadow'][0] ) );
		} elseif ( ! $has_preset_shadow && $this->paints_own_shape( $attributes ) ) {
			// Only reset to `none` when nothing else claims this rule's box-shadow — a preset's
			// `var(--fxb-btn-shadow)` above would otherwise be silenced by a trailing `none`, and a
			// theme-painted button keeps the shadow the theme's own rules give it.
			$css->add_property( 'box-shadow', 'none' );
		}
		if ( ! empty( $attributes['textUnderline'] ) ) {
			$css->set_selector( '.wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button:not(.specificity):not(.extra-specificity)' );
			$css->add_property( 'text-decoration', $attributes['textUnderline'] );
		}
		// Icon styles.
		$css->set_selector( '.fxb-btn' . $unique_id . '.fxb-button .fxb-svg-icon-wrap' );
		if ( ! empty( $attributes['iconColor'] ) ) {
			$css->add_property( 'color', $css->render_color( $attributes['iconColor'] ) );
		}
		$css->render_measure_output( $attributes, 'iconPadding', 'padding', [ 'unit_key' => 'iconPaddingUnit' ] );
		$css->render_responsive_range( $attributes, 'iconSize', 'font-size', 'iconSizeUnit' );
		$css->render_responsive_range( $attributes, 'iconSize', '--fxb-button-icon-size', 'iconSizeUnit' );
		// Icon Hover-Focus.
		$css->set_selector( '.fxb-btn' . $unique_id . '.fxb-button:hover .fxb-svg-icon-wrap, .fxb-btn' . $unique_id . '.fxb-button:focus .fxb-svg-icon-wrap' );
		if ( ! empty( $attributes['iconColorHover'] ) ) {
			$css->add_property( 'color', $css->render_color( $attributes['iconColorHover'] ) );
		}
		// Hover-Focus.
		$css->set_selector( '.wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button:hover, .wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button:focus' );
		if ( ! empty( $attributes['colorHover'] ) && ( empty( $attributes['textBackgroundHoverType'] ) || $attributes['textBackgroundHoverType'] === 'normal' ) ) {
			$css->add_property( 'color', $css->render_color( $attributes['colorHover'] ) );
		} elseif ( ! empty( $attributes['textBackgroundHoverType'] ) && $attributes['textBackgroundHoverType'] === 'gradient' ) {
			$css->set_selector( '.wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button:hover .fxt-btn-inner-text, .wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button:focus .fxt-btn-inner-text' );
			$css->add_property( 'background', $attributes['textGradientHover'] );
			$css->add_property( '-webkit-background-clip', 'text' );
			$css->add_property( '-webkit-text-fill-color', 'transparent' );
			$css->set_selector( '.wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button:hover, .wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button:focus' );
		}

		if ( 'gradient' !== $bg_type && 'normal' === $bg_hover_type && ! empty( $attributes['backgroundHover'] ) ) {
			$css->add_property( 'background', $css->render_color( $attributes['backgroundHover'] ) );
		}
		$css->render_measure_output( $attributes, 'borderHoverRadius', 'border-radius' );
		$css->render_border_styles( $attributes, 'borderHoverStyle', true );
		// Visibility-gated: without it this fires on any inset-true item, including the invisible
		// all-zero default the block ships with.
		if ( ! empty( $attributes['displayHoverShadow'] ) && ( 'gradient' === $bg_type || 'gradient' === $bg_hover_type ) && isset( $attributes['shadowHover'][0] ) && is_array( $attributes['shadowHover'][0] ) && $this->has_visible_shadow( $attributes['shadowHover'][0] ) && isset( $attributes['shadowHover'][0]['inset'] ) && true === $attributes['shadowHover'][0]['inset'] ) {
			$css->add_property( 'box-shadow', 'none' );
			$css->set_selector( '.fxb-btn' . $unique_id . '.fxb-button:hover::before' );
		}
		// The hover state follows its own default, never the resting shadow: with no hover shadow of its
		// own the rule points at the preset's hover shadow variable, falling back to `none` when the preset
		// sets none. Emitted for every button that paints its own shape, because a hover rule without a
		// box-shadow would let the resting shadow carry through the cascade into the hover state. A
		// theme-painted button keeps the hover shadow the theme's own rules give it.
		if ( ! empty( $attributes['displayHoverShadow'] ) && isset( $attributes['shadowHover'][0] ) && is_array( $attributes['shadowHover'][0] ) && $this->has_visible_shadow( $attributes['shadowHover'][0] ) ) {
			$css->add_property( 'box-shadow', $this->render_button_shadow( $css, $attributes['shadowHover'][0] ) );
		} elseif ( $this->paints_own_shape( $attributes ) ) {
			$css->add_property( 'box-shadow', 'var(--fxb-btn-shadow-hover, none)' );
		}
		// Hover before.
		if ( 'gradient' === $bg_type && 'normal' === $bg_hover_type && ! empty( $attributes['backgroundHover'] ) ) {
			$css->set_selector( '.fxb-btn' . $unique_id . '.fxb-button::before' );
			$css->add_property( 'background', $css->render_color( $attributes['backgroundHover'] ) );
			$css->add_property( 'transition', 'opacity .3s ease-in-out' );
		}
		if ( 'gradient' === $bg_hover_type && ! empty( $attributes['gradientHover'] ) ) {
			$css->set_selector( '.fxb-btn' . $unique_id . '.fxb-button::before' );
			$css->add_property( 'background', $attributes['gradientHover'] );
			$css->add_property( 'transition', 'opacity .3s ease-in-out' );
		}
		// Only Icon.
		if ( isset( $attributes['onlyIcon'][0] ) && '' !== $attributes['onlyIcon'][0] && true == $attributes['onlyIcon'][0] ) {
			$css->set_selector( '.fxb-btn' . $unique_id . '.fxb-button .fxt-btn-inner-text' );
			$css->add_property( 'display', 'none' );
		}
		if ( isset( $attributes['onlyIcon'][1] ) && '' !== $attributes['onlyIcon'][1] ) {
			$css->set_media_state( 'tablet' );
			$css->set_selector( '.fxb-btn' . $unique_id . '.fxb-button .fxt-btn-inner-text' );
			if ( true == $attributes['onlyIcon'][1] ) {
				$css->add_property( 'display', 'none' );
			} elseif ( false == $attributes['onlyIcon'][1] ) {
				$css->add_property( 'display', 'block' );
			}
		}
		if ( isset( $attributes['onlyText'][0] ) && '' !== $attributes['onlyText'][0] ) {
			$css->set_media_state( 'tablet' );
			$css->set_selector( '.fxb-btn' . $unique_id . '.fxb-button .fxb-svg-icon-wrap' );
			if ( true == $attributes['onlyText'][0] ) {
				$css->add_property( 'display', 'none' );
			}
		}
		if ( isset( $attributes['onlyIcon'][2] ) && '' !== $attributes['onlyIcon'][2] ) {
			$css->set_media_state( 'mobile' );
			$css->set_selector( '.fxb-btn' . $unique_id . '.fxb-button .fxt-btn-inner-text' );
			if ( true == $attributes['onlyIcon'][2] ) {
				$css->add_property( 'display', 'none' );
			}
		}
		if ( isset( $attributes['onlyText'][1] ) && '' !== $attributes['onlyText'][1] ) {
			$css->set_media_state( 'mobile' );
			$css->set_selector( '.fxb-btn' . $unique_id . '.fxb-button .fxb-svg-icon-wrap' );
			if ( true == $attributes['onlyText'][1] ) {
				$css->add_property( 'display', 'none' );
			} elseif ( false == $attributes['onlyText'][1] ) {
				$css->add_property( 'display', 'block' );
			}
		}
		$css->set_media_state( 'desktop' );
		return $css->css_output();
	}

	/**
	 * Build up the dynamic styles for a size.
	 *
	 * @param Flx_Blocks_CSS   $css        The CSS builder instance.
	 * @param array<string, mixed> $attributes The block attributes.
	 * @param string               $unique_id  The block's unique id.
	 * @param string               $size       The responsive size.
	 *
	 * @return void
	 */
	public function sized_dynamic_styles( $css, $attributes, $unique_id, $size = 'Desktop' ) {
		$sized_attributes         = $css->get_sized_attributes_auto( $attributes, $size, false );
		$sized_attributes_inherit = $css->get_sized_attributes_auto( $attributes, $size );

		$css->set_media_state( strtolower( $size ) );

		// Standard transparent styles.
		$css->set_selector( '.header-' . strtolower( $size ) . '-transparent .wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button' );
		$bg_type_transparent       = ! empty( $attributes['backgroundTransparentType'] ) ? $attributes['backgroundTransparentType'] : 'normal';
		$bg_hover_type_transparent = ! empty( $attributes['backgroundTransparentHoverType'] ) ? $attributes['backgroundTransparentHoverType'] : 'normal';
		if ( ! empty( $attributes['colorTransparent'] ) ) {
			$css->add_property( 'color', $css->render_color( Cast::to_string( $attributes['colorTransparent'] ) ) );
		}
		if ( 'normal' === $bg_type_transparent && ! empty( $attributes['backgroundTransparent'] ) ) {
			$css->add_property( 'background', $css->render_color( Cast::to_string( $attributes['backgroundTransparent'] ) ) . ( 'gradient' === $bg_hover_type_transparent ? ' !important' : '' ) );
		}
		if ( 'gradient' === $bg_type_transparent && ! empty( $attributes['gradientTransparent'] ) ) {
			$css->add_property( 'background', $attributes['gradientTransparent'] . ' !important' );
		}
		$css->render_measure_output( $attributes, 'borderTransparentRadius', 'border-radius', [ 'unit_key' => 'borderTransparentRadiusUnit' ] );
		$css->render_border_styles( $attributes, 'borderTransparentStyle', true );
		// No `none` fallback: this selector outranks the base rule, which must carry through instead.
		if ( ! empty( $attributes['displayShadowTransparent'] ) && isset( $attributes['shadowTransparent'] ) && is_array( $attributes['shadowTransparent'] ) && isset( $attributes['shadowTransparent'][0] ) && is_array( $attributes['shadowTransparent'][0] ) && $this->has_visible_shadow( $attributes['shadowTransparent'][0] ) ) {
			$css->add_property( 'box-shadow', $this->render_button_shadow( $css, $attributes['shadowTransparent'][0] ) );
		}

		// Hover transparent styles.
		$css->set_selector( '.header-' . strtolower( $size ) . '-transparent .wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button:hover' );
		if ( ! empty( $attributes['colorTransparentHover'] ) ) {
			$css->add_property( 'color', $css->render_color( Cast::to_string( $attributes['colorTransparentHover'] ) ) );
		}
		if ( 'gradient' !== $bg_type_transparent && 'normal' === $bg_hover_type_transparent && ! empty( $attributes['backgroundTransparentHover'] ) ) {
			$css->add_property( 'background', $css->render_color( Cast::to_string( $attributes['backgroundTransparentHover'] ) ) );
		}
		$css->render_measure_output( $attributes, 'borderTransparentHoverRadius', 'border-radius' );
		$css->render_border_styles( $attributes, 'borderTransparentHoverStyle', true );
		// See the base hover state above: this reset needs the same visibility gate.
		if ( ! empty( $attributes['displayHoverShadowTransparent'] ) && ( 'gradient' === $bg_type_transparent || 'gradient' === $bg_hover_type_transparent ) && isset( $attributes['shadowTransparentHover'] ) && is_array( $attributes['shadowTransparentHover'] ) && isset( $attributes['shadowTransparentHover'][0] ) && is_array( $attributes['shadowTransparentHover'][0] ) && $this->has_visible_shadow( $attributes['shadowTransparentHover'][0] ) && isset( $attributes['shadowTransparentHover'][0]['inset'] ) && true === $attributes['shadowTransparentHover'][0]['inset'] ) {
			$css->add_property( 'box-shadow', 'none' );
			$css->set_selector( '.fxb-btn' . $unique_id . '.fxb-button:hover::before' );
		}
		// No `none` fallback on hover: an unset hover shadow must let the base state's shadow
		// carry through the `:hover` rule via the normal cascade.
		if ( ! empty( $attributes['displayHoverShadowTransparent'] ) && isset( $attributes['shadowTransparentHover'] ) && is_array( $attributes['shadowTransparentHover'] ) && isset( $attributes['shadowTransparentHover'][0] ) && is_array( $attributes['shadowTransparentHover'][0] ) && $this->has_visible_shadow( $attributes['shadowTransparentHover'][0] ) ) {
			$css->add_property( 'box-shadow', $this->render_button_shadow( $css, $attributes['shadowTransparentHover'][0] ) );
		}

		// Standard sticky styles.
		$css->set_selector( '.item-is-stuck .wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button' );
		$bg_type_sticky       = ! empty( $attributes['backgroundStickyType'] ) ? $attributes['backgroundStickyType'] : 'normal';
		$bg_hover_type_sticky = ! empty( $attributes['backgroundStickyHoverType'] ) ? $attributes['backgroundStickyHoverType'] : 'normal';
		if ( ! empty( $attributes['colorSticky'] ) ) {
			$css->add_property( 'color', $css->render_color( Cast::to_string( $attributes['colorSticky'] ) ) );
		}
		if ( 'normal' === $bg_type_sticky && ! empty( $attributes['backgroundSticky'] ) ) {
			$css->add_property( 'background', $css->render_color( Cast::to_string( $attributes['backgroundSticky'] ) ) . ( 'gradient' === $bg_hover_type_sticky ? ' !important' : '' ) );
		}
		if ( 'gradient' === $bg_type_sticky && ! empty( $attributes['gradientSticky'] ) ) {
			$css->add_property( 'background', $attributes['gradientSticky'] . ' !important' );
		}
		$css->render_measure_output( $attributes, 'borderStickyRadius', 'border-radius', [ 'unit_key' => 'borderStickyRadiusUnit' ] );
		$css->render_border_styles( $attributes, 'borderStickyStyle', true );
		// No `none` fallback: this selector outranks the base rule, which must carry through instead.
		if ( ! empty( $attributes['displayShadowSticky'] ) && isset( $attributes['shadowSticky'] ) && is_array( $attributes['shadowSticky'] ) && isset( $attributes['shadowSticky'][0] ) && is_array( $attributes['shadowSticky'][0] ) && $this->has_visible_shadow( $attributes['shadowSticky'][0] ) ) {
			$css->add_property( 'box-shadow', $this->render_button_shadow( $css, $attributes['shadowSticky'][0] ) );
		}

		// Hover sticky styles.
		$css->set_selector( '.item-is-stuck .wp-block-flx-advancedbtn .fxb-btn' . $unique_id . '.fxb-button:hover' );
		if ( ! empty( $attributes['colorStickyHover'] ) ) {
			$css->add_property( 'color', $css->render_color( Cast::to_string( $attributes['colorStickyHover'] ) ) );
		}
		if ( 'gradient' !== $bg_type_sticky && 'normal' === $bg_hover_type_sticky && ! empty( $attributes['backgroundStickyHover'] ) ) {
			$css->add_property( 'background', $css->render_color( Cast::to_string( $attributes['backgroundStickyHover'] ) ) );
		}
		$css->render_measure_output( $attributes, 'borderStickyHoverRadius', 'border-radius' );
		$css->render_border_styles( $attributes, 'borderStickyHoverStyle', true );
		// See the base hover state above: this reset needs the same visibility gate.
		if ( ! empty( $attributes['displayHoverShadowSticky'] ) && ( 'gradient' === $bg_type_sticky || 'gradient' === $bg_hover_type_sticky ) && isset( $attributes['shadowStickyHover'] ) && is_array( $attributes['shadowStickyHover'] ) && isset( $attributes['shadowStickyHover'][0] ) && is_array( $attributes['shadowStickyHover'][0] ) && $this->has_visible_shadow( $attributes['shadowStickyHover'][0] ) && isset( $attributes['shadowStickyHover'][0]['inset'] ) && true === $attributes['shadowStickyHover'][0]['inset'] ) {
			$css->add_property( 'box-shadow', 'none' );
			$css->set_selector( '.fxb-btn' . $unique_id . '.fxb-button:hover::before' );
		}
		// No `none` fallback on hover: an unset hover shadow must let the base state's shadow
		// carry through the `:hover` rule via the normal cascade.
		if ( ! empty( $attributes['displayHoverShadowSticky'] ) && isset( $attributes['shadowStickyHover'] ) && is_array( $attributes['shadowStickyHover'] ) && isset( $attributes['shadowStickyHover'][0] ) && is_array( $attributes['shadowStickyHover'][0] ) && $this->has_visible_shadow( $attributes['shadowStickyHover'][0] ) ) {
			$css->add_property( 'box-shadow', $this->render_button_shadow( $css, $attributes['shadowStickyHover'][0] ) );
		}
	}

	/**
	 * Build HTML for dynamic blocks
	 *
	 * @param array<string, mixed> $attributes The block attributes.
	 * @param string               $unique_id The block's unique id.
	 * @param string               $content The block inner content.
	 * @param WP_Block             $block_instance The instance of the WP_Block class that represents the block being rendered.
	 *
	 * @return mixed
	 */
	public function build_html( $attributes, $unique_id, $content, $block_instance ) {
		if ( ! empty( $attributes['tooltip'] ) ) {
			$this->enqueue_script( 'flx-blocks-tippy' );
		}

		$legacy   = Cast::to_string( $attributes['inheritStyles'] ?? '' );
		$resolved = $this->resolved_preset( $attributes );

		$classes   = [ 'fxb-button', 'fxt-button', 'button', 'fxb-btn' . $unique_id ];
		$classes[] = ! empty( $attributes['sizePreset'] ) ? 'fxt-btn-size-' . $attributes['sizePreset'] : 'fxt-btn-size-standard';
		$classes[] = ! empty( $attributes['widthType'] ) ? 'fxt-btn-width-type-' . $attributes['widthType'] : 'fxt-btn-width-type-auto';

		if ( $resolved !== null && $resolved['class'] !== '' ) {
			// The preset's classes paint the button; the mode classes would fight them for the same properties.
			$classes[] = $resolved['class'];

			if ( $this->stored_preset( $attributes ) === self::THEME_SECONDARY && $resolved['slug'] !== self::THEME_SECONDARY ) {
				// A secondary button on a theme with no secondary style keeps the class it always carried.
				$classes[] = self::SECONDARY_CLASS;
			}
		} else {
			$classes[] = self::LEGACY_CLASSES[ $legacy ] ?? 'fxb-btn-global-fill';
		}

		$classes[] = ! empty( $attributes['text'] ) ? 'fxt-btn-has-text-true' : 'fxt-btn-has-text-false';
		$classes[] = ! empty( $attributes['icon'] ) ? 'fxt-btn-has-svg-true' : 'fxt-btn-has-svg-false';
		$classes[] = ! empty( $attributes['iconReveal'] ) && ! empty( $attributes['icon'] ) ? 'icon-reveal' : '';

		if ( ! empty( $attributes['target'] ) && 'video' === $attributes['target'] ) {
			$classes[] = 'ktblocksvideopop';
		}
		$wrapper_args = [ 'class' => implode( ' ', array_unique( array_filter( explode( ' ', implode( ' ', $classes ) ) ) ) ) ];
		if ( ! empty( $attributes['anchor'] ) ) {
			$wrapper_args['id'] = Cast::to_string( $attributes['anchor'] );
		}
		if ( ! empty( $attributes['label'] ) ) {
			$wrapper_args['aria-label'] = Cast::to_string( $attributes['label'] );
		}
		if ( ! empty( $attributes['link'] ) ) {
			$wrapper_args['href'] = esc_url( do_shortcode( Cast::to_string( $attributes['link'] ) ) );
			$rel_add              = '';
			if ( isset( $attributes['download'] ) && $attributes['download'] ) {
				$wrapper_args['download'] = '';
			}
			if ( ! empty( $attributes['target'] ) && '_blank' === $attributes['target'] ) {
				$wrapper_args['target'] = '_blank';
				$rel_add                = 'noreferrer noopener';
			}
			if ( isset( $attributes['noFollow'] ) && $attributes['noFollow'] ) {
				$rel_add .= ' nofollow';
			}
			if ( isset( $attributes['sponsored'] ) && $attributes['sponsored'] ) {
				$rel_add .= ' sponsored';
			}
			if ( ! empty( $rel_add ) ) {
				$wrapper_args['rel'] = $rel_add;
			}
		}
		if ( ! empty( $attributes['isSubmit'] ) ) {
			$wrapper_args['type'] = 'submit';
		}
		if ( ! empty( $attributes['tooltip'] ) ) {
			$wrapper_args['data-fxb-tooltip-content'] = esc_attr( Cast::to_string( $attributes['tooltip'] ) );
			if ( ! empty( $attributes['tooltipPlacement'] ) ) {
				$wrapper_args['data-tooltip-placement'] = esc_attr( Cast::to_string( $attributes['tooltipPlacement'] ) );
			}
		}
		if ( isset( $attributes['buttonRole'] ) && $attributes['buttonRole'] ) {
			$wrapper_args['role'] = 'button';
		}
		if ( ! empty( $attributes['link'] ) && apply_filters( 'flxbk_button_auto_apply_role', true ) ) {
			// check if the link is a download link.
			if ( $attributes['link'] === '#' && empty( $wrapper_args['target'] ) ) {
				$wrapper_args['role'] = 'button';
			}
		}
		$wrapper_attributes = get_block_wrapper_attributes( $wrapper_args );

		$text     = ! empty( $attributes['text'] ) ? '<span class="fxt-btn-inner-text">' . $attributes['text'] . '</span>' : '';
		$svg_icon = '';
		if ( ! empty( $attributes['icon'] ) ) {
			$type         = substr( Cast::to_string( $attributes['icon'] ), 0, 2 );
			$line_icon    = ( ! empty( $type ) && 'fe' == $type ? true : false );
			$fill         = ( $line_icon ? 'none' : 'currentColor' );
			$stroke_width = false;

			if ( $line_icon ) {
				$stroke_width = 2;
			}
			$title    = ( ! empty( $attributes['iconTitle'] ) ? $attributes['iconTitle'] : '' );
			$hidden   = ( empty( $title ) ? true : false );
			$svg_icon = Flx_Blocks_Svg_Render::render( $attributes['icon'], $fill, $stroke_width, $title, $hidden );
		}
		$icon_left  = ! empty( $svg_icon ) && ! empty( $attributes['iconSide'] ) && 'left' === $attributes['iconSide'] ? '<span class="fxb-svg-icon-wrap fxb-svg-icon-' . esc_attr( Cast::to_string( $attributes['icon'] ) ) . ' fxt-btn-icon-side-left">' . $svg_icon . '</span>' : '';
		$icon_right = ! empty( $svg_icon ) && ! empty( $attributes['iconSide'] ) && 'right' === $attributes['iconSide'] ? '<span class="fxb-svg-icon-wrap fxb-svg-icon-' . esc_attr( Cast::to_string( $attributes['icon'] ) ) . ' fxt-btn-icon-side-right">' . $svg_icon . '</span>' : '';
		$html_tag   = ! empty( $attributes['link'] ) ? 'a' : 'span';

		// Try to Detect if this is a show more button and make it a button.
		if ( isset( $attributes['lock'] ) && $attributes['lock'] && is_array( $attributes['lock'] ) && isset( $attributes['lock']['remove'] ) && $attributes['lock']['remove'] && isset( $attributes['lock']['move'] ) && $attributes['lock']['move'] && empty( $attributes['link'] ) ) {
			$html_tag = 'button';
		}

		$content = sprintf( '<%1$s %2$s>%3$s%4$s%5$s</%1$s>', $html_tag, $wrapper_attributes, $icon_left, $text, $icon_right );

		/*  Wrap in div is AOS is enabled. AOS transitions will interfere with hover transitions. */
		$aos_args = [];
		$aos_args = flxbk_apply_aos_wrapper_args( $attributes, $aos_args );
		if ( ! empty( $aos_args ) ) {
			$aos_classes           = [ 'fxb-blocks-button-aos' ];
			$aos_classes[]         = ! empty( $attributes['widthType'] ) ? 'fxb-btn-width-type-' . $attributes['widthType'] : 'fxb-btn-width-type-auto';
			$aos_args['class']     = implode( ' ', $aos_classes );
			$normalized_attributes = [];
			foreach ( $aos_args as $key => $value ) {
				$normalized_attributes[] = $key . '="' . esc_attr( $value ) . '"';
			}
			$content = sprintf( '<div %1$s>%2$s</div>', implode( ' ', $normalized_attributes ), $content );
		}

		return $content;
	}

	/**
	 * Registers scripts and styles.
	 */
	public function register_scripts() {
		parent::register_scripts();
		// If in the backend, bail out.
		if ( is_admin() ) {
			return;
		}
		if ( apply_filters( 'flxbk_check_if_rest', false ) && flxbk_is_rest() ) {
			return;
		}
		wp_register_style( 'flxbk-glightbox', FLXBK_URL . 'includes/assets/css/fxb-glightbox.min.css', [], FLXBK_VERSION );
		wp_register_script( 'flxbk-glightbox', FLXBK_URL . 'includes/assets/js/glightbox.min.js', [], FLXBK_VERSION, true );
		wp_register_script( 'flx-blocks-glight-video-init', FLXBK_URL . 'includes/assets/js/fxb-glight-video-init.min.js', [ 'flxbk-glightbox' ], FLXBK_VERSION, true );
		wp_register_script( 'flx-blocks-popper', FLXBK_URL . 'includes/assets/js/popper.min.js', [], FLXBK_VERSION, true );
		wp_register_script( 'flx-blocks-tippy', FLXBK_URL . 'includes/assets/js/fxb-tippy.min.js', [ 'flx-blocks-popper' ], FLXBK_VERSION, true );
	}

	/**
	 * The size preset and width type set classes on the markup, and the width
	 * renders only with a fixed width type; the editor sets the icon size and
	 * icon padding inline. Padding, margin and the shadows are here because a
	 * design token preset points them at its variables in the button's own rule,
	 * which outranks a site rule: as attributes they land after that bridge in
	 * the same rule and win it. As for every overlay value, a button can't pick
	 * the block's default over a site value, as it can't tell it from none.
	 *
	 */
	public function site_styles_overlay_attributes(): array {
		$cascaded = [];

		foreach ( $this->site_styles_cascades() as $cascade ) {
			$cascaded = array_merge( $cascaded, isset( $cascade['unit'] ) ? [ $cascade['unit'] ] : [], $cascade['values'] );
		}

		return array_merge(
			[
				'sizePreset',
				'widthType',
				'width',
				'widthUnit',
				'iconSize',
				'iconSizeUnit',
				'iconPadding',
				'tabletIconPadding',
				'mobileIconPadding',
				'iconPaddingUnit',
				'displayShadow',
				'shadow',
				'displayHoverShadow',
				'shadowHover',
			],
			$cascaded
		);
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function site_styles_cascades(): array {
		return [
			[
				'unit'   => 'paddingUnit',
				'values' => [ 'padding', 'tabletPadding', 'mobilePadding' ],
			],
			[
				'unit'   => 'marginUnit',
				'values' => [ 'margin', 'tabletMargin', 'mobileMargin' ],
			],
		];
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function site_styles_attributes_map(): array {
		return [
			'background' => 'color.background',
			'color'      => 'color.text',
		];
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function site_styles_supports(): array {
		return [
			'color' => [
				'background'                      => true,
				'text'                            => true,
				'gradients'                       => false,
				'__experimentalSkipSerialization' => true,
			],
		];
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function site_styles_selectors(): array {
		/*
		 * The button element, which the editor wraps and the front end doesn't. Only Fill
		 * buttons: core's colors would otherwise reach the Outline and theme styles.
		 */
		return [ 'root' => '.wp-block-flx-singlebtn.fxt-button.fxb-btn-global-fill, .wp-block-flx-singlebtn .fxt-button.fxb-btn-global-fill' ];
	}

	/**
	 * @inheritDoc
	 *
	 */
	public function site_styles_scope_attribute(): string {
		return 'inheritStyles';
	}

	/**
	 * The style the button renders as. A class-painted preset (Outline, or a theme style) sets it, since
	 * presets replace `inheritStyles`; otherwise it's the button's `inheritStyles`, as in `build_html()`.
	 *
	 *
	 * @param array<string, mixed> $attributes The instance's attributes.
	 *
	 * @return string|null
	 */
	public function site_styles_scope_style( array $attributes ): ?string {
		$resolved = $this->resolved_preset( $attributes );
		$classes  = null === $resolved ? [] : preg_split( '/\s+/', $resolved['class'], -1, PREG_SPLIT_NO_EMPTY );

		if ( ! $classes ) {
			return isset( $attributes['inheritStyles'] ) ? Cast::to_string( $attributes['inheritStyles'] ) : null;
		}

		if ( in_array( 'fxb-btn-global-outline', $classes, true ) ) {
			return 'outline';
		}

		// Any other class-painted preset is a theme style.
		if ( in_array( self::SECONDARY_CLASS, $classes, true ) || self::THEME_SECONDARY === $this->stored_preset( $attributes ) ) {
			return 'inherit-secondary';
		}

		return 'inherit';
	}

	/**
	 * Site values describe Fill buttons. Outline takes only the shape, so it
	 * keeps its transparent background and theme outline colors; the theme
	 * button styles take none.
	 *
	 */
	public function site_styles_scoped_attributes(): array {
		$shape = [
			'typography',
			'sizePreset',
			'widthType',
			'width',
			'widthUnit',
			'gap',
			'padding',
			'tabletPadding',
			'mobilePadding',
			'paddingUnit',
			'margin',
			'tabletMargin',
			'mobileMargin',
			'marginUnit',
			'borderRadius',
			'tabletBorderRadius',
			'mobileBorderRadius',
			'borderRadiusUnit',
			'borderHoverRadius',
			'tabletBorderHoverRadius',
			'mobileBorderHoverRadius',
			'borderHoverRadiusUnit',
			'iconSize',
			'iconSizeUnit',
			'iconPadding',
			'tabletIconPadding',
			'mobileIconPadding',
			'iconPaddingUnit',
		];

		return [
			'outline'           => $shape,
			'inherit'           => [],
			'inherit-secondary' => [],
		];
	}

	/**
	 * The button's `fxb-btn-global-<style>` class.
	 *
	 *
	 * @param string $style The style, e.g. `fill`.
	 *
	 * @return string Class name.
	 */
	public function site_styles_scope_class( string $style ): string {
		return 'fxb-btn-global-' . sanitize_key( $style );
	}

	/**
	 * The preset slug this button asks for: its `fxbPreset`, or the preset its older "Button Inherit Styles"
	 * value maps to, so a saved button renders the same preset with no re-save.
	 *
	 *
	 * @param array<string, mixed> $attributes The block attributes.
	 *
	 * @return string The stored slug, or '' for the default look.
	 */
	protected function stored_preset( array $attributes ): string {
		$stored = parent::stored_preset( $attributes );

		if ( $stored !== '' ) {
			return $stored;
		}

		return self::LEGACY_PRESETS[ Cast::to_string( $attributes['inheritStyles'] ?? '' ) ] ?? '';
	}

	/**
	 * Point padding and margin at their preset variables, but only for a property the active preset
	 * actually resolves.
	 *
	 * The gate is the whole point. `padding: var(--fxb-btn-padding)` is not inert when the variable is
	 * undefined — it is invalid at computed-value time, which resets padding to `0` rather than letting
	 * the button's size class supply it. Emitting only when the property resolves is what keeps a button
	 * whose preset sets no spacing looking exactly as it does today.
	 *
	 * Emitted before the attribute output so an explicit per-block value, which lands later in the same
	 * rule, still wins. Responsiveness needs nothing here: a per-breakpoint override redeclares the
	 * canonical preset variable inside a media query, and this bridge follows it.
	 *
	 *
	 * @param Flx_Blocks_CSS   $css        The css object.
	 * @param array<string, mixed> $attributes The block attributes.
	 *
	 * @return void
	 */
	private function render_preset_spacing( Flx_Blocks_CSS $css, array $attributes ): void {
		$resolved = $this->resolved_preset( $attributes );

		if ( $resolved === null || $resolved['class'] !== '' ) {
			return;
		}

		$values = $this->preset_values( $resolved['slug'] );

		if ( $values === null ) {
			return;
		}

		if ( isset( $values['button-padding'] ) ) {
			$css->add_property( 'padding', 'var(--fxb-btn-padding)' );
		}

		if ( isset( $values['button-margin'] ) ) {
			$css->add_property( 'margin', 'var(--fxb-btn-margin)' );
		}
	}

	/**
	 * Point border width/style/color at their preset variables, but only for a property the active
	 * preset actually resolves.
	 *
	 * The gate is the whole point, exactly as in `render_preset_spacing()` — a `var()` pointed at an
	 * undefined custom property is invalid at computed-value time, so emitting unconditionally would
	 * blank out the border on a button whose preset sets none of it.
	 *
	 * Emitted before `render_border_styles()`'s explicit per-side output so an explicit per-block
	 * border, which lands later in the same rule, still wins.
	 *
	 * The default preset's border is emitted only for a property the library overrides, since the
	 * shipped values equal the button's own stylesheet: an untouched button keeps its border where the
	 * theme's cascade put it, exactly as it did before presets existed.
	 *
	 *
	 * @param Flx_Blocks_CSS   $css        The css object.
	 * @param array<string, mixed> $attributes The block attributes.
	 *
	 * @return void
	 */
	private function render_preset_border( Flx_Blocks_CSS $css, array $attributes ): void {
		$resolved = $this->resolved_preset( $attributes );

		if ( $resolved === null || $resolved['class'] !== '' ) {
			return;
		}

		$values = $this->preset_values( $resolved['slug'], $resolved['is_default'] );

		if ( $values === null ) {
			return;
		}

		if ( isset( $values['button-border-width'] ) ) {
			$css->add_property( 'border-width', 'var(--fxb-btn-border-width)' );
		}

		if ( isset( $values['button-border-style'] ) ) {
			$css->add_property( 'border-style', 'var(--fxb-btn-border-style)' );
		}

		if ( isset( $values['button-border-color'] ) ) {
			$css->add_property( 'border-color', 'var(--fxb-btn-border-color)' );
		}
	}

	/**
	 * Point the button's box-shadow at its preset variable, but only when the active preset actually
	 * resolves one.
	 *
	 * The gate is the whole point, exactly as in `render_preset_spacing()` — a `var()` pointed at an
	 * undefined custom property is invalid at computed-value time, so emitting unconditionally would
	 * blank out the shadow on a button whose preset sets none.
	 *
	 * Emitted before the explicit `shadow` output below, and the builder appends declarations, so a
	 * visible per-block shadow lands later in the same rule and wins. The caller uses the returned
	 * flag for the other half of that contract: when the block's own shadow is invisible it must
	 * skip its `box-shadow: none` reset, or the trailing `none` would silence the preset's
	 * `var(--fxb-btn-shadow)` here.
	 *
	 * Known, accepted limitation: the shadow control's "None" pick and an untouched shadow attribute
	 * serialize to the exact same value (both resolve to the invisible all-zero composite), by design
	 * — that is what lets "Default" and "None" read identically in the control. One consequence is
	 * that a button whose preset resolves a shadow cannot have that shadow turned off from this block's
	 * own shadow control: "None" is indistinguishable from "never set", so this method's gate always
	 * wins. Distinguishing them would need a real "explicitly none" marker outside the shadow value
	 * itself, which is a design change, not a bug fix.
	 *
	 *
	 * @param Flx_Blocks_CSS   $css        The css object.
	 * @param array<string, mixed> $attributes The block attributes.
	 *
	 * @return bool Whether a preset box-shadow declaration was emitted.
	 */
	private function render_preset_shadow( Flx_Blocks_CSS $css, array $attributes ): bool {
		$resolved = $this->resolved_preset( $attributes );

		if ( $resolved === null || $resolved['class'] !== '' ) {
			return false;
		}

		$values = $this->preset_values( $resolved['slug'] );

		if ( $values === null || ! isset( $values['button-shadow'] ) ) {
			return false;
		}

		$css->add_property( 'box-shadow', 'var(--fxb-btn-shadow)' );

		return true;
	}

	/**
	 * The values a button preset resolves to, or null when the token services cannot answer. For the
	 * default preset only the properties the library overrides are kept, since the shipped values equal
	 * the button's own stylesheet: an untouched button keeps its look where the theme's cascade put it.
	 *
	 *
	 * @param string $preset     The resolved preset slug.
	 * @param bool   $is_default Whether the slug is the block's default preset.
	 *
	 * @return array<string, mixed>|null property => value.
	 */
	private function preset_values( string $preset, bool $is_default = false ): ?array {
		try {
			$library  = flxbk()->get( Active_Token_Library_Store::class );
			$resolver = flxbk()->get( Preset_Resolver::class );

			// The container is typed `mixed`, and this runs on every button render, so the services are
			// checked rather than assumed — a misconfigured container degrades to today's look.
			if ( ! $library instanceof Active_Token_Library_Store || ! $resolver instanceof Preset_Resolver ) {
				return null;
			}

			$slug   = $library->get();
			$values = $resolver->resolve( 'flx/singlebtn', $preset, $slug );

			if ( $is_default ) {
				$values = array_intersect_key( $values, $resolver->overridden_default_properties( 'flx/singlebtn', $slug ) );
			}

			return $values;
		} catch ( Throwable $e ) {
			// This runs in the render path, so a broken token graph must not take the page down with it —
			// the button simply keeps the look it has today.
			return null;
		}
	}

	/**
	 * Route a stored button box-shadow object through the alias-aware render_shadow().
	 *
	 * Applies this block's historic per-leg defaults so a literal shadow renders byte-identically
	 * to the former inline builder. A `{dot.alias}` on any numeric leg resolves to its token var()
	 * instead of freezing to a literal, and a `shadowToken` binding on the item resolves the whole
	 * shorthand to its token var().
	 *
	 *
	 * @param Flx_Blocks_CSS $css    The active CSS builder.
	 * @param mixed              $shadow The stored shadow object ( e.g. $attributes['shadow'][0] ).
	 *
	 * @return string The rendered box-shadow declaration.
	 */
	private function render_button_shadow( $css, $shadow ): string {
		return (string) $css->render_shadow(
			is_array( $shadow ) ? $shadow : [],
			[
				'hOffset' => '0',
				'vOffset' => '0',
				'blur'    => '14',
				'spread'  => '0',
				'color'   => '#000000',
				'opacity' => 0.2,
			]
		);
	}

	/**
	 * Whether the button's shape (padding, margin, border, shadow) is the plugin's own. A button on a
	 * class-painted preset takes those from the theme's rules or from the outline stylesheet, and the
	 * preset bridges must not outrank them. While the token registry is off the older style attribute
	 * decides, as it did before presets existed.
	 *
	 *
	 * @param array<string, mixed> $attributes The block attributes.
	 *
	 * @return bool
	 */
	private function paints_own_shape( array $attributes ): bool {
		$resolved = $this->resolved_preset( $attributes );

		if ( $resolved !== null ) {
			return $resolved['class'] === '';
		}

		$mode = Cast::to_string( $attributes['inheritStyles'] ?? '' );

		return $mode === '' || $mode === 'fill';
	}
}

Flx_Blocks_Singlebtn_Block::get_instance();
