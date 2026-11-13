<?php
/**
 * Class to Build the Image Block.
 *
 * @package Flux Block Editor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to Build the Image Block.
 *
 * @category class
 */
class Flx_Blocks_Image_Block extends Flx_Blocks_Abstract_Block {

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
	protected $block_name = 'image';

	/**
	 * Block determines in scripts need to be loaded for block.
	 *
	 * @var string
	 */
	protected $has_script = false;

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
	 * @param array              $attributes      the blocks attributes.
	 * @param Flx_Blocks_CSS $css             the css class for blocks.
	 * @param string             $unique_id       the blocks attr ID.
	 * @param string             $unique_style_id the blocks alternate ID for queries.
	 */
	public function build_css( $attributes, $css, $unique_id, $unique_style_id ) {

		$css->set_style_id( 'fxb-' . $this->block_name . $unique_style_id );

		$key_positions = [ 'top', 'right', 'bottom', 'left' ];
		$css->set_selector( '.wp-block-flx-image.fxb-image' . $unique_id . ':not(.fxb-specificity-added):not(.fxb-extra-specificity-added)' );
		if ( ! empty( $attributes['align'] ) && ( 'left' === $attributes['align'] || 'right' === $attributes['align'] ) ) {
			$css->set_selector( '.wp-block-flx-image.fxb-image' . $unique_id . ':not(.fxb-specificity-added):not(.fxb-extra-specificity-added) > figure' );
		}

		// Margins
		$css->render_measure_output( $attributes, 'marginDesktop', 'margin', array(
			'tablet_key'  => 'marginTablet',
			'mobile_key'  => 'marginMobile',
			'unit_key' => 'marginUnit'
		) );
		$css->set_selector( '.wp-block-flx-image.fxb-image' . $unique_id );
		if ( ! empty( $attributes['zIndex'] ) ) {
			$css->add_property( 'position', 'relative' );
			$css->add_property( 'z-index', $attributes['zIndex'] );
		}
		$align = ( ! empty( $attributes['align'] ) ? $attributes['align'] : '' );
		if ( $align !== 'wide' && $align !== 'full' ) {
			if ( isset( $attributes['imgMaxWidth'] ) && is_numeric( $attributes['imgMaxWidth'] ) ) {
				$css->set_selector( '.fxb-image' . $unique_id . '.fxb-image-is-ratio-size, .fxb-image' . $unique_id . ' .fxb-image-is-ratio-size' );
				$css->add_property( 'max-width', $attributes['imgMaxWidth'] . 'px' );
				$css->add_property( 'width', '100%' );
				$css->set_selector( '.wp-block-flx-column > .fxt-inside-inner-col > .fxb-image' . $unique_id . '.fxb-image-is-ratio-size, .wp-block-flx-column > .fxt-inside-inner-col > .fxb-image' . $unique_id . ' .fxb-image-is-ratio-size' );
				$css->add_property( 'align-self', 'unset' );
			}
		}
		if ( $align === 'center' || $align === 'right' || $align === 'left' ) {
			$css->set_selector( '.fxb-image' . $unique_id . ' figure' );
			if ( isset( $attributes['imgMaxWidth'] ) && is_numeric( $attributes['imgMaxWidth'] ) ) {
				$css->add_property( 'max-width', $attributes['imgMaxWidth'] . 'px' );
				$css->set_selector( '.fxb-image' . $unique_id . ' .image-is-svg, .fxb-image' . $unique_id . ' .image-is-svg img' );
				$css->add_property( 'width', '100%' );
			}
		} else if ( $align !== 'wide' && $align !== 'full' ) {
			$css->set_selector( '.fxb-image' . $unique_id );
			if ( isset( $attributes['imgMaxWidth'] ) && is_numeric( $attributes['imgMaxWidth'] ) ) {
				$css->add_property( 'max-width', $attributes['imgMaxWidth'] . 'px' );
				$css->set_selector( '.image-is-svg.fxb-image' . $unique_id );
				$css->add_property( 'flex', '0 1 100%' );
				$css->set_selector( '.image-is-svg.fxb-image' . $unique_id . ' img' );
				$css->add_property( 'width', '100%' );
			}
		}

		// Tablet and Mobile Max Width.
		foreach ( [ 'Tablet', 'Mobile' ] as $breakpoint ) {
			$css->set_media_state( strtolower( $breakpoint ) );
			if ( isset( $attributes[ 'imgMaxWidth' . $breakpoint ] ) && is_numeric( $attributes[ 'imgMaxWidth' . $breakpoint ] ) ) {
				if ( $align !== 'wide' && $align !== 'full' ) {
					$css->set_selector( '.fxb-image' . $unique_id . '.fxb-image-is-ratio-size, .fxb-image' . $unique_id . ' .fxb-image-is-ratio-size' );
					$css->add_property( 'max-width', $attributes[ 'imgMaxWidth' . $breakpoint ] . 'px' );
					$css->add_property( 'width', '100%' );
				}
				if ( $align === 'center' || $align === 'right' || $align === 'left' ) {
					$css->set_selector( '.fxb-image' . $unique_id . ' figure' );
					$css->add_property( 'max-width', $attributes[ 'imgMaxWidth' . $breakpoint ] . 'px' );
				} else if ( $align !== 'wide' && $align !== 'full' ) {
					$css->set_selector( '.fxb-image' . $unique_id );
					$css->add_property( 'max-width', $attributes[ 'imgMaxWidth' . $breakpoint ] . 'px' );
				}
			}

			$css->set_media_state( 'desktop' );
		}
		$css->set_selector( '.fxb-image' . $unique_id . ':not(.fxb-image-is-ratio-size) .fxb-img, .fxb-image' . $unique_id . '.fxb-image-is-ratio-size' );

		// Padding
		$css->render_measure_output( $attributes, 'paddingDesktop', 'padding', array(
			'tablet_key'  => 'paddingTablet',
			'mobile_key'  => 'paddingMobile',
			'unit_key' => 'paddingUnit'
		) );

		// Overlay.
		$overlay_type = ! empty( $attributes['overlayType'] ) ? $attributes['overlayType'] : 'normal';
		$css->set_selector( '.fxb-image' . $unique_id . ' .fxb-image-has-overlay:after' );
		$opacity = isset( $attributes['overlayOpacity'] ) ? $attributes['overlayOpacity'] : 0.3;

		if ( $css->is_number( $opacity ) ) {
			$css->add_property( 'opacity', $opacity );
		}
		if ( ! empty( $attributes['overlayBlendMode'] ) ) {
			$css->add_property( 'mix-blend-mode', $attributes['overlayBlendMode'] );
		}
		switch ( $overlay_type ) {
			case 'normal':
				if ( ! empty( $attributes['overlay'] ) ) {
					$css->add_property( 'background-color', $css->render_color( $attributes['overlay'] ) );
				}
				break;
			case 'gradient':
				if ( ! empty( $attributes['overlayGradient'] ) ) {
					$css->add_property( 'background-image', $attributes['overlayGradient'] );
				}
				break;
		}

		// Border Radius.
		$css->render_measure_output( $attributes, 'borderRadius', 'border-radius', array( 'unit_key' => 'borderRadiusUnit' ) );

		$css->set_media_state( 'desktop' );
		$css->set_selector( '.fxb-image' . $unique_id . ' img.fxb-img, .fxb-image' . $unique_id . ' .fxb-img img' );
		// Support borders saved pre 3.0
		if ( !empty( $attributes['borderColor'] ) ) {
			$css->add_property( 'border-style', 'solid' );
			$css->add_property( 'border-color', $css->render_color( $attributes['borderColor'] ) );

			// Border widths
			foreach ( [ 'Desktop', 'Tablet', 'Mobile' ] as $breakpoint ) {
				$css->set_media_state( strtolower( $breakpoint ) );

				if ( isset( $attributes[ 'borderWidth' . $breakpoint ] ) && is_array( $attributes[ 'borderWidth' . $breakpoint ] ) ) {

					foreach ( $attributes[ 'borderWidth' . $breakpoint ] as $key => $bDesktop ) {
						if ( is_numeric( $bDesktop ) ) {
							$css->add_property( 'border-' . $key_positions[ $key ] . '-width', $bDesktop . ( ! isset( $attributes['borderWidthUnit'] ) ? 'px' : $attributes['borderWidthUnit'] ) );
						}
					}
				}

				$css->set_media_state( 'desktop' );
			}
		} else {
			$css->render_border_styles( $attributes, 'borderStyle', true );
		}


		// Background Color.
		if ( ! empty( $attributes['backgroundColor'] ) ) {
			$css->add_property( 'background-color', $css->render_color( $attributes['backgroundColor'] ) );
		}
		// Border Radius.
		$css->render_measure_output( $attributes, 'borderRadius', 'border-radius', array( 'unit_key' => 'borderRadiusUnit' ) );

		if ( ! empty( $attributes['maskSvg'] ) && 'none' !== $attributes['maskSvg'] ) {
			if ( 'custom' === $attributes['maskSvg'] ) {
				if ( ! empty( $attributes['maskUrl'] ) ) {
					$mask_size     = ( ! empty( $attributes['maskSize'] ) ? $attributes['maskSize'] : 'auto' );
					$mask_position = ( ! empty( $attributes['maskPosition'] ) ? $attributes['maskPosition'] : 'center center' );
					$mask_repeat   = ( ! empty( $attributes['maskRepeat'] ) ? $attributes['maskRepeat'] : 'no-repeat' );
					$css->add_property( '-webkit-mask-image', 'url(' . $attributes['maskUrl'] . ')' );
					$css->add_property( 'mask-image', 'url(' . $attributes['maskUrl'] . ')' );

					$css->add_property( '-webkit-mask-size', $mask_size );
					$css->add_property( 'mask-size', $mask_size );

					$css->add_property( '-webkit-mask-repeat', $mask_repeat );
					$css->add_property( 'mask-repeat', $mask_repeat );

					$css->add_property( '-webkit-mask-position', $mask_position );
					$css->add_property( 'mask-position', $mask_position );
				}
			} else {
				$mask_base_url = FLXBK_URL . 'includes/assets/images/masks/';
				$css->add_property( '-webkit-mask-image', 'url(' . $mask_base_url . $attributes['maskSvg'] . '.svg)' );
				$css->add_property( 'mask-image', 'url(' . $mask_base_url . $attributes['maskSvg'] . '.svg)' );

				$css->add_property( '-webkit-mask-size', 'auto' );
				$css->add_property( 'mask-size', 'auto' );

				$css->add_property( '-webkit-mask-repeat', 'no-repeat' );
				$css->add_property( 'mask-repeat', 'no-repeat' );

				$css->add_property( '-webkit-mask-position', 'center' );
				$css->add_property( 'mask-position', 'center' );
			}
		}

		// Box shadow, gated on BOTH the stored flag and the value's own axes.
		//
		// The flag is no longer a control -- the editor derives it from the value on every write -- but it
		// still has to be read. Gutenberg omits an attribute equal to its default, and the old toggle
		// defaulted to false, so a block saved with the shadow switched OFF stored no flag at all while
		// keeping whatever values the user had entered. Judging by geometry alone would start rendering
		// those, which is exactly the upgrade-time restyling the design-token work is meant to avoid.
		//
		// The head CSS pass reads raw attributes, and Gutenberg also omits a `boxShadow` equal to its
		// default, so a raised flag with no stored value means the shipped shadow, not "no shadow".
		$box_shadow = isset( $attributes['boxShadow'][0] ) && is_array( $attributes['boxShadow'][0] )
			? $attributes['boxShadow'][0]
			: [
				'color'   => '#000000',
				'opacity' => 0.2,
				'spread'  => 0,
				'blur'    => 14,
				'hOffset' => 0,
				'vOffset' => 0,
				'inset'   => false,
			];

		if ( ! empty( $attributes['displayBoxShadow'] ) && $this->has_visible_shadow( $box_shadow ) ) {
			$css->add_property(
				'box-shadow',
				$css->render_shadow(
					$box_shadow,
					[
						'hOffset' => '0',
						'vOffset' => '0',
						'blur'    => '14',
						'spread'  => '0',
						'color'   => '#000000',
						'opacity' => 0.2,
					]
				)
			);
		}

		// Drop Shadow
		if ( isset( $attributes['displayDropShadow'] ) && true == $attributes['displayDropShadow'] ) {
			if ( isset( $attributes['dropShadow'] ) && is_array( $attributes['dropShadow'] ) && isset( $attributes['dropShadow'][0] ) && is_array( $attributes['dropShadow'][0] ) ) {
				$css->add_property( 'filter', 'drop-shadow(' . ( isset( $attributes['dropShadow'][0]['hOffset'] ) && is_numeric( $attributes['dropShadow'][0]['hOffset'] ) ? $attributes['dropShadow'][0]['hOffset'] : '0' ) . 'px ' . ( isset( $attributes['dropShadow'][0]['vOffset'] ) && is_numeric( $attributes['dropShadow'][0]['vOffset'] ) ? $attributes['dropShadow'][0]['vOffset'] : '0' ) . 'px ' . ( isset( $attributes['dropShadow'][0]['blur'] ) && is_numeric( $attributes['dropShadow'][0]['blur'] ) ? $attributes['dropShadow'][0]['blur'] : '14' ) . 'px ' . $css->render_color( ( isset( $attributes['dropShadow'][0]['color'] ) && ! empty( $attributes['dropShadow'][0]['color'] ) ? $attributes['dropShadow'][0]['color'] : '#000000' ), ( isset( $attributes['dropShadow'][0]['opacity'] ) && is_numeric( $attributes['dropShadow'][0]['opacity'] ) ? $attributes['dropShadow'][0]['opacity'] : 0.2 ) ) . ')' );
			} else {
				$css->add_property( 'filter', 'drop-shadow(0px 0px 14px rgba(0, 0, 0, 0.2) )' );
			}
		}

		// Object position
		if ( isset( $attributes['imagePosition'] ) && $attributes['imagePosition'] ) {
			$css->add_property( 'object-position', $attributes['imagePosition'] );
		}

		// Caption Font
		if ( ! isset( $attributes['showCaption'] ) && isset( $attributes['captionStyles'] ) && is_array( $attributes['captionStyles'] ) && is_array( $attributes['captionStyles'][0] ) ) {
			$caption_font = $attributes['captionStyles'][0];

			$css->set_selector( '.fxb-image' . $unique_id . ' figcaption' );
			if ( isset( $caption_font['color'] ) && ! empty( $caption_font['color'] ) ) {
				$css->add_property( 'color', $css->render_color( $caption_font['color'] ) );
			}
			if ( isset( $caption_font['background'] ) && ! empty( $caption_font['background'] ) ) {
				$css->add_property( 'background', $css->render_color( $caption_font['background'] ) );
			}
			if ( isset( $caption_font['size'] ) && is_array( $caption_font['size'] ) ) {
				$caption_font_unit = !empty( $caption_font['sizeType'] ) ? $caption_font['sizeType'] : 'px';
				if ( ! empty( $caption_font['size'][0] ) ) {
					$css->add_property( 'font-size', $css->get_font_size( $caption_font['size'][0], $caption_font_unit ) );
				}

				if ( ! empty( $caption_font['size'][1] ) ) {
					$css->set_media_state('tablet');
					$css->add_property( 'font-size', $css->get_font_size( $caption_font['size'][1], $caption_font_unit ) );
					$css->set_media_state('desktop');
				}

				if ( ! empty( $caption_font['size'][2] ) ) {
					$css->set_media_state('mobile');
					$css->add_property( 'font-size', $css->get_font_size( $caption_font['size'][2], $caption_font_unit ) );
					$css->set_media_state('desktop');
				}
			}
			if ( isset( $caption_font['lineHeight'] ) && is_array( $caption_font['lineHeight'] ) && ! empty( $caption_font['lineHeight'][0] ) ) {
				$css->add_property( 'line-height', $caption_font['lineHeight'][0] . ( ! isset( $caption_font['lineType'] ) ? 'px' : $caption_font['lineType'] ) );
			}
			if ( ! empty( $caption_font['letterSpacing'] ) ) {
				if ( !is_array($caption_font['letterSpacing']) ) {
					$css->add_property( 'letter-spacing', $caption_font['letterSpacing'] . 'px' );
				} else {
					$css->render_responsive_range($caption_font, 'letterSpacing', 'letter-spacing', 'px');
				}

			}
			if ( isset( $caption_font['textTransform'] ) && ! empty( $caption_font['textTransform'] ) ) {
				$css->add_property( 'text-transform', $caption_font['textTransform'] );
			}
			if ( isset( $caption_font['google']) && ! empty($caption_font['google'] ) ) {
				$google = $caption_font['google'] ? true : false;
				$google = $google && ( isset( $caption_font['loadGoogle'] ) && $caption_font['loadGoogle'] || ! isset( $caption_font['loadGoogle'] ) ) ? true : false;
				$variant = ! empty( $caption_font['variant'] ) ? $caption_font['variant'] : null;
				$css->add_property( 'font-family', $css->render_font_family( $caption_font['family'], $google, $variant ) );
			} elseif ( isset( $caption_font['family'] ) && ! empty( $caption_font['family'] ) ) {
				$css->add_property( 'font-family', $caption_font['family'] );
			}
			if ( isset( $caption_font['style'] ) && ! empty( $caption_font['style'] ) ) {
				$css->add_property( 'font-style', $caption_font['style'] );
			}
			if ( isset( $caption_font['weight'] ) && ! empty( $caption_font['weight'] ) ) {
				$css->add_property( 'font-weight', $caption_font['weight'] );
			}
		}
		if ( ! isset( $attributes['showCaption'] ) && isset( $attributes['captionStyles'] ) && is_array( $attributes['captionStyles'] ) && isset( $attributes['captionStyles'][0] ) && is_array( $attributes['captionStyles'][0] ) && ( ( isset( $attributes['captionStyles'][0]['size'] ) && is_array( $attributes['captionStyles'][0]['size'] ) && isset( $attributes['captionStyles'][0]['size'][1] ) && ! empty( $attributes['captionStyles'][0]['size'][1] ) ) || ( isset( $attributes['captionStyles'][0]['lineHeight'] ) && is_array( $attributes['captionStyles'][0]['lineHeight'] ) && isset( $attributes['captionStyles'][0]['lineHeight'][1] ) && ! empty( $attributes['captionStyles'][0]['lineHeight'][1] ) ) ) ) {
			$css->set_media_state( 'tablet' );
			$css->set_selector( '.fxb-image' . $unique_id . ' figcaption' );
			if ( isset( $attributes['captionStyles'][0]['size'][1] ) && ! empty( $attributes['captionStyles'][0]['size'][1] ) ) {
				$css->add_property( 'font-size', $attributes['captionStyles'][0]['size'][1] . ( ! isset( $attributes['captionStyles'][0]['sizeType'] ) ? 'px' : $attributes['captionStyles'][0]['sizeType'] ) );
			}
			if ( isset( $attributes['captionStyles'][0]['lineHeight'][1] ) && ! empty( $attributes['captionStyles'][0]['lineHeight'][1] ) ) {
				$css->add_property( 'line-height', $attributes['captionStyles'][0]['lineHeight'][1] . ( ! isset( $attributes['captionStyles'][0]['lineType'] ) ? 'px' : $attributes['captionStyles'][0]['lineType'] ) );
			}
			$css->set_media_state( 'desktop' );
		}
		if ( ! isset( $attributes['showCaption'] ) && isset( $attributes['captionStyles'] ) && is_array( $attributes['captionStyles'] ) && isset( $attributes['captionStyles'][0] ) && is_array( $attributes['captionStyles'][0] ) && ( ( isset( $attributes['captionStyles'][0]['size'] ) && is_array( $attributes['captionStyles'][0]['size'] ) && isset( $attributes['captionStyles'][0]['size'][2] ) && ! empty( $attributes['captionStyles'][0]['size'][2] ) ) || ( isset( $attributes['captionStyles'][0]['lineHeight'] ) && is_array( $attributes['captionStyles'][0]['lineHeight'] ) && isset( $attributes['captionStyles'][0]['lineHeight'][2] ) && ! empty( $attributes['captionStyles'][0]['lineHeight'][2] ) ) ) ) {
			$css->set_media_state( 'tablet' );
			$css->set_selector( '.fxb-image' . $unique_id . ' figcaption' );
			if ( isset( $attributes['captionStyles'][0]['size'][2] ) && ! empty( $attributes['captionStyles'][0]['size'][2] ) ) {
				$css->add_property( 'font-size', $attributes['captionStyles'][0]['size'][2] . ( ! isset( $attributes['captionStyles'][0]['sizeType'] ) ? 'px' : $attributes['captionStyles'][0]['sizeType'] ) );
			}
			if ( isset( $attributes['captionStyles'][0]['lineHeight'][2] ) && ! empty( $attributes['captionStyles'][0]['lineHeight'][2] ) ) {
				$css->add_property( 'line-height', $attributes['captionStyles'][0]['lineHeight'][2] . ( ! isset( $attributes['captionStyles'][0]['lineType'] ) ? 'px' : $attributes['captionStyles'][0]['lineType'] ) );
			}
			$css->set_media_state( 'desktop' );
		}

		return $css->css_output();
	}

	/**
	 * Build HTML for dynamic blocks
	 *
	 * @param $attributes
	 * @param $unique_id
	 * @param $content
	 * @param WP_Block $block_instance The instance of the WP_Block class that represents the block being rendered.
	 *
	 * @return mixed
	 */
	public function build_html( $attributes, $unique_id, $content, $block_instance ) {
		$update_alt_globally = isset( $attributes['globalAlt'] ) && true === $attributes['globalAlt'] ? true : false;
		if ( apply_filters( 'flxbk_update_alt_text_globally', $update_alt_globally, $attributes ) && ! empty( $attributes['id'] ) ) {
			// Check if we can get the alt text.
			$alt = get_post_meta( $attributes['id'], '_wp_attachment_image_alt', true );
			if ( ! empty( $alt ) ) {
				$content = str_replace( 'alt=""', 'alt="' . esc_attr( $alt ) . '"', $content );
			}
		}
		if ( strpos( $content, 'fxb-tooltip-hidden-content') !== false ) {
			$this->enqueue_script( 'flx-blocks-tippy' );
		}

		if( !empty( $attributes['urlSticky']) ) {
			$this->enqueue_script('flx-blocks-header-sticky-image');
		}
		return $this->add_loading_attributes( $this->add_image_dimensions( $attributes, $content ) );
	}

	/**
	 * Images saved with only a URL (patterns, presets) have no width/height,
	 * srcset or lazy loading. Resolve the attachment (or the local file) and
	 * add the missing attributes so WordPress can optimise the tag.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block HTML.
	 * @return string
	 */
	public function add_image_dimensions( $attributes, $content ) {
		if ( ! apply_filters( 'flxbk_image_add_dimensions', true, $attributes ) || false === strpos( $content, '<img' ) ) {
			return $content;
		}
		if ( ! preg_match( '/<img\b[^>]*>/i', $content, $m ) ) {
			return $content;
		}
		$tag = $m[0];
		if ( preg_match( '/\swidth=["\']?\d/i', $tag ) || preg_match( '/\sheight=["\']?\d/i', $tag ) ) {
			return $content;
		}
		if ( ! preg_match( '/\ssrc=["\']([^"\']+)["\']/i', $tag, $src ) ) {
			return $content;
		}
		$url  = html_entity_decode( $src[1], ENT_QUOTES );
		$info = $this->image_info( $url, empty( $attributes['id'] ) ? 0 : (int) $attributes['id'] );
		if ( empty( $info['width'] ) || empty( $info['height'] ) ) {
			return $content;
		}
		$new = $tag;
		if ( ! empty( $info['id'] ) && ! preg_match( '/wp-image-\d+/', $new ) ) {
			if ( preg_match( '/\sclass=(["\'])/i', $new ) ) {
				$new = preg_replace( '/\sclass=(["\'])/i', ' class=$1wp-image-' . (int) $info['id'] . ' ', $new, 1 );
			} else {
				$new = preg_replace( '/^<img\b/i', '<img class="wp-image-' . (int) $info['id'] . '"', $new, 1 );
			}
		}
		$extra = ' width="' . (int) $info['width'] . '" height="' . (int) $info['height'] . '"';
		// Outside a ratio box the browser must keep the aspect ratio when max-width shrinks the image.
		if ( empty( $attributes['useRatio'] ) && ! preg_match( '/\sstyle=/i', $new ) ) {
			$extra .= ' style="height:auto"';
		}
		$new = preg_replace( '/^<img\b/i', '<img' . $extra, $new, 1 );
		$pos = strpos( $content, $tag );
		return substr_replace( $content, $new, $pos, strlen( $tag ) );
	}

	/**
	 * Inside post content WordPress adds loading/decoding/fetchpriority itself.
	 * Blocks rendered straight from a block template (patterns in a template)
	 * get nothing from core, so do the same here: the first images on the
	 * page stay eager, later ones are lazy-loaded.
	 *
	 * @param string $content Block HTML.
	 * @return string
	 */
	public function add_loading_attributes( $content ) {
		if ( is_admin() || is_feed() || doing_filter( 'the_content' ) || doing_filter( 'widget_block_content' ) || ! function_exists( 'wp_increase_content_media_count' ) ) {
			return $content;
		}
		if ( ! preg_match( '/<img\b[^>]*>/i', $content, $m ) ) {
			return $content;
		}
		$tag = $m[0];
		if ( preg_match( '/\sloading=/i', $tag ) || ! preg_match( '/\swidth=["\']?(\d+)/i', $tag, $w ) || ! preg_match( '/\sheight=["\']?(\d+)/i', $tag, $h ) ) {
			return $content;
		}
		if ( ! apply_filters( 'flxbk_image_loading_optimization', true, $tag ) ) {
			return $content;
		}
		$extra = preg_match( '/\sdecoding=/i', $tag ) ? '' : ' decoding="async"';
		if ( wp_increase_content_media_count( 0 ) < wp_omit_loading_attr_threshold() ) {
			/** This filter is documented in wp-includes/media.php */
			if ( (int) $w[1] * (int) $h[1] >= (int) apply_filters( 'wp_min_priority_img_pixels', 50000 ) ) {
				wp_increase_content_media_count();
			}
		} elseif ( wp_lazy_loading_enabled( 'img', 'flx_image' ) ) {
			$extra .= ' loading="lazy"';
		}
		if ( '' === $extra ) {
			return $content;
		}
		$new = preg_replace( '/^<img\b/i', '<img' . $extra, $tag, 1 );
		return substr_replace( $content, $new, strpos( $content, $tag ), strlen( $tag ) );
	}

	/**
	 * Width/height (and attachment ID) for an image URL, cached.
	 *
	 * @param string $url Image URL.
	 * @param int    $id  Attachment ID from the block, if any.
	 * @return array{id:int,width:int,height:int}
	 */
	public function image_info( $url, $id = 0 ) {
		static $memo = array();
		$key = md5( $id . '|' . $url );
		if ( isset( $memo[ $key ] ) ) {
			return $memo[ $key ];
		}
		$cached = get_transient( 'flxbk_img_' . $key );
		if ( is_array( $cached ) ) {
			return $memo[ $key ] = $cached;
		}
		$info = array( 'id' => 0, 'width' => 0, 'height' => 0 );
		if ( ! $id ) {
			$id = (int) attachment_url_to_postid( $url );
			if ( ! $id ) {
				// Sized URL (photo-1024x768.jpg): look up the original file.
				$full = preg_replace( '/-\d+x\d+(\.[a-z0-9]+)(\?.*)?$/i', '$1', $url );
				if ( $full !== $url ) {
					$id = (int) attachment_url_to_postid( $full );
				}
			}
		}
		if ( $id && wp_attachment_is_image( $id ) ) {
			$meta = wp_get_attachment_metadata( $id );
			$dims = is_array( $meta ) ? wp_image_src_get_dimensions( $url, $meta, $id ) : false;
			if ( $dims ) {
				$info = array( 'id' => $id, 'width' => (int) $dims[0], 'height' => (int) $dims[1] );
			}
		}
		if ( ! $info['width'] ) {
			$file = $this->local_path( $url );
			if ( $file ) {
				$size = function_exists( 'wp_getimagesize' ) ? wp_getimagesize( $file ) : @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( $size && $size[0] && $size[1] ) {
					$info = array( 'id' => 0, 'width' => (int) $size[0], 'height' => (int) $size[1] );
				} elseif ( preg_match( '/\.svg$/i', $file ) ) {
					$svg = (string) file_get_contents( $file, false, null, 0, 4096 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					if ( preg_match( '/viewBox=["\']\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)/i', $svg, $vb ) ) {
						$info = array( 'id' => 0, 'width' => (int) round( (float) $vb[1] ), 'height' => (int) round( (float) $vb[2] ) );
					}
				}
			}
		}
		set_transient( 'flxbk_img_' . $key, $info, $info['width'] ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );
		return $memo[ $key ] = $info;
	}

	/**
	 * Map a URL on this site to a readable local file path.
	 *
	 * @param string $url URL.
	 * @return string Empty when not local.
	 */
	private function local_path( $url ) {
		$path = strtok( $url, '?#' );
		$maps = array(
			content_url() => WP_CONTENT_DIR,
			site_url()    => ABSPATH,
		);
		foreach ( $maps as $base => $dir ) {
			foreach ( array( $base, set_url_scheme( $base, 'http' ), set_url_scheme( $base, 'https' ), wp_make_link_relative( $base ) ) as $prefix ) {
				if ( '' !== $prefix && 0 === strpos( $path, $prefix ) ) {
					$rel  = rawurldecode( substr( $path, strlen( $prefix ) ) );
					$file = wp_normalize_path( untrailingslashit( $dir ) . '/' . ltrim( $rel, '/' ) );
					if ( false === strpos( $rel, '..' ) && preg_match( '/\.(jpe?g|png|gif|webp|avif|svg)$/i', $file ) && is_readable( $file ) ) {
						return $file;
					}
				}
			}
		}
		return '';
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
		wp_register_script( 'flx-blocks-header-sticky-image', FLXBK_URL . 'includes/assets/js/fxb-header-sticky-image.min.js', array(), FLXBK_VERSION, true );
		wp_register_script( 'flx-blocks-popper', FLXBK_URL . 'includes/assets/js/popper.min.js', array(), FLXBK_VERSION, true );
		wp_register_script( 'flx-blocks-tippy', FLXBK_URL . 'includes/assets/js/fxb-tippy.min.js', array( 'flx-blocks-popper' ), FLXBK_VERSION, true );
	}
}

Flx_Blocks_Image_Block::get_instance();
