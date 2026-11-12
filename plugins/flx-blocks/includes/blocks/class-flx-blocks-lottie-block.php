<?php
/**
 * Class to Build the Lottie Block.
 *
 * @package Flux Block Editor
 */

// cspell:ignore Loittie lottieinteractivity .

use FluxWP\Blocks\Utils\Cast;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to Build the Lottie Block.
 *
 * @category class
 */
class Flx_Blocks_Lottie_Block extends Flx_Blocks_Abstract_Block {

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
	protected $block_name = 'lottie';

	/**
	 * Block determines in scripts need to be loaded for block.
	 *
	 * @var string
	 */
	protected $has_script = true;

	/**
	 * Block determines if style needs to be loaded for block.
	 *
	 * @var string
	 */
	protected $has_style = true;

	/**
	 * Hooks the save-time sanitizing of external animation URLs.
	 *
	 */
	public function __construct() {
		parent::__construct();

		add_filter( 'wp_insert_post_data', [ $this, 'strip_external_urls_on_save' ] );
	}

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
	 * @param Flx_Blocks_CSS $css the css class for blocks.
	 * @param string $unique_id the blocks attr ID.
	 * @param string $unique_style_id the blocks alternate ID for queries.
	 */
	public function build_css( $attributes, $css, $unique_id, $unique_style_id ) {

		$css->set_style_id( 'fxb-' . $this->block_name . $unique_style_id );
		$css->set_selector( '.fxb-lottie-container' . $unique_id );

		/*
		 * Margin
		 */
		$margin_args = array(
			'desktop_key' => 'marginDesktop',
			'tablet_key'  => 'marginTablet',
			'mobile_key'  => 'marginMobile',
			'unit_key'    => 'marginUnit',
		);
		$css->render_measure_output( $attributes, 'margin', 'margin', $margin_args );

		/*
		 * Padding
		 */
		$padding_args = array(
			'desktop_key' => 'paddingDesktop',
			'tablet_key'  => 'paddingTablet',
			'mobile_key'  => 'paddingMobile',
			'unit_key'    => 'paddingUnit',
		);
		$css->render_measure_output( $attributes, 'padding', 'padding', $padding_args );

		$width = ( !isset( $attributes['width'] ) || '0' === $attributes['width'] ) ? 'auto' : $attributes['width'] . 'px';

		if ( isset( $attributes['useRatio'] ) && $attributes['useRatio'] ) {
			$css->add_property( 'max-width', $width );
			$css->add_property( 'margin', '0 auto' );
			$css->set_selector( '.fxb-lottie-container' . $unique_id . ' .fxb-is-ratio-animation' );
			$css->add_property( 'padding-bottom', isset( $attributes['ratio'] ) && is_numeric( $attributes['ratio'] ) ? $attributes['ratio'] . '%' : '100%' );
		}

		if ( ! isset( $attributes['useRatio'] ) || ! $attributes['useRatio'] ) {
			$css->set_selector( '.fxb-lottie-container' . $unique_id . ' dotlottie-player' );
			$css->add_property( 'max-width', $width );
		}
		return $css->css_output();
	}

	/**
	 * Return dynamically generated HTML for block
	 *
	 *
	 * @param array<string, mixed> $attributes     The block attributes.
	 * @param string               $unique_id      The block unique ID.
	 * @param string               $content        The saved block content.
	 * @param WP_Block             $block_instance The instance of the WP_Block class that represents the block being rendered.
	 *
	 * @return string
	 */
	public function build_html( $attributes, $unique_id, $content, $block_instance ) {
		if ( ! wp_script_is( 'flx-blocks-dotlottie-player-js', 'enqueued' ) ) {
			wp_enqueue_script( 'flx-blocks-dotlottie-player-js' );
		}

		if ( isset( $attributes['uniqueID'] ) ) {
			$unique_id              = Cast::to_string( $attributes['uniqueID'] );
			$player_style_id        = 'fxb-lottie-player' . esc_attr( $unique_id );
			$player_simple_style_id = str_replace( array( '-' ), '', $player_style_id );
			$style_id               = 'fxb-lottie' . esc_attr( $unique_id );

			if ( ! wp_style_is( $style_id, 'enqueued' ) && apply_filters( 'flxbk_render_inline_css', true, 'lottie', $unique_id ) ) {
				// If filter didn't run in header (which would have enqueued the specific css id ) then filter attributes for easier dynamic css.
				$attributes = apply_filters( 'flxbk_lottie_render_block_attributes', $attributes );
			}

			// Include lottie interactive if using scroll animation.
			if ( isset( $attributes['onlyPlayOnScroll'] ) && $attributes['onlyPlayOnScroll'] === true || isset( $attributes['waitUntilInView'] ) && $attributes['waitUntilInView'] === true ) {
				if ( ! wp_script_is( 'flx-blocks-lottieinteractivity-js', 'enqueued' ) ) {
					wp_enqueue_script( 'flx-blocks-lottieinteractivity-js' );
				}

				if ( isset( $attributes['onlyPlayOnScroll'] ) && $attributes['onlyPlayOnScroll'] === true ) {
					$play_type = 'seek';
					$start_frame = ! empty( $attributes['startFrame'] ) ? (float) $attributes['startFrame'] : '0';
					$end_frame   = ! empty( $attributes['endFrame'] ) ? (float) $attributes['endFrame'] : '100';
					$frames      = 'frames: [' . $start_frame . ', ' . $end_frame . ']';
				} else {
					$play_type = 'play';
					$frames    = '';
				}

				$content .= "
				<script>
						
						var waitForLoittieInteractive" . $player_simple_style_id . " = setInterval(function () {
							let lottiePlayer" . $player_simple_style_id . " = document.getElementById( '" . $player_style_id . "');
							
							if (typeof LottieInteractivity !== 'undefined') {					
								lottiePlayer" . $player_simple_style_id . ".addEventListener('ready', () => {
								  LottieInteractivity.create({
									  player: lottiePlayer" . $player_simple_style_id . ".getLottie(),
									  mode: 'scroll',
									    actions: [
									        {
									            visibility: [0,1.0],
												type: '" . $play_type . "',
												$frames
									        }
									    ]
									});
								});
	                            clearInterval(waitForLoittieInteractive" . $player_simple_style_id . ");
							}
						}, 125);

				</script>";

			}

			$playerProps = array();

			if( $attributes['loop'] ){
				$playerProps['loop'] = 'true';

				if( !empty( $attributes['loopLimit'] ) ){
					$playerProps['count'] = ( $attributes['loopLimit'] - 1);
				}
			}

			if( $attributes['playbackSpeed'] ){
				$playerProps['speed'] = $attributes['playbackSpeed'];
			}

			if( $attributes['showControls'] ){
				$playerProps['controls'] = 'true';
			}

			if( $attributes['autoplay'] ){
				$playerProps['autoplay'] = 'true';
			}

			if( $attributes['onlyPlayOnHover'] ){
				$playerProps['hover'] = 'true';
			}

			if( $attributes['bouncePlayback']) {
				$playerProps['mode'] = 'bounce';
			} else {
				$playerProps['mode'] = 'normal';
			}

			if( $attributes['delay'] !== 0){
				$playerProps['intermission'] = 1000 * $attributes['delay'];
			}

			$classes = array(
				'fxb-lottie-container',
				'fxb-lottie-container' . esc_attr( $unique_id ),
			);

			if ( ! empty( $attributes['align'] ) ) {
				$classes[] = 'align' . $attributes['align'];
			}

			// Add custom CSS classes to class string.
			if ( isset( $attributes['className'] ) ) {
				$classes[] = $attributes['className'];
			}

			$content .= '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
				if ( isset( $attributes['useRatio'] ) && $attributes['useRatio'] ) {
					$content .= '<div class="fxb-is-ratio-animation">';
				}
				$content .= '<dotlottie-player ';

					foreach( $playerProps as $key => $value ){
						$content .= esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
					}

				$content .= 'src="' . $this->getAnimationUrl( $attributes ) . '"
							id="fxb-lottie-player' . esc_attr( $unique_id ) .'"
						></dotlottie-player>';
				if ( isset( $attributes['useRatio'] ) && $attributes['useRatio'] ) {
					$content .= '</div>';
				}
			$content .= '</div>';
		}

		return $content;
	}

	/**
	 * Gets the animation URL for the player.
	 *
	 *
	 * @param array{fileSrc: string, fileUrl: string, localFile: array<int, array{value: string}>} $attributes The block attributes.
	 *
	 * @return string The escaped animation URL.
	 */
	public function getAnimationUrl( $attributes ) {
		$url      = '';
		$rest_url = get_rest_url();

		if ( $attributes['fileSrc'] === 'url' ) {
			if ( $this->is_external_url_allowed( $attributes['fileUrl'] ) ) {
				$url = esc_attr( $attributes['fileUrl'] );
			}
		} else {
			$animation_id = $attributes['localFile'][0]['value'] ?? '';

			if ( is_scalar( $animation_id ) && preg_match( '/^[a-zA-Z0-9]+$/', (string) $animation_id ) ) {
				$url = $rest_url . 'fxb-lottieanimation/v1/animations/' . $animation_id . '.json';
			}
		}

		if ( $url === '' || $url === $rest_url . 'fxb-lottieanimation/v1/animations/' ) {
			$url = FLXBK_URL . 'includes/assets/lottie/default.json';
		}

		return $url;
	}

	/**
	 * Removes external animation URLs from Lottie blocks in content saved by a user without `unfiltered_html`.
	 *
	 *
	 * @param array<string, mixed> $data The slashed post data being saved.
	 *
	 * @return array<string, mixed> The post data.
	 */
	public function strip_external_urls_on_save( $data ) {
		if ( current_user_can( 'unfiltered_html' ) || empty( $data['post_content'] ) || ! is_string( $data['post_content'] ) ) {
			return $data;
		}

		if ( false === strpos( $data['post_content'], 'wp:flx/lottie' ) ) {
			return $data;
		}

		$changed = false;
		$blocks  = $this->strip_external_urls_from_blocks( parse_blocks( wp_unslash( $data['post_content'] ) ), $changed );

		if ( $changed ) {
			// The blocks come from parse_blocks() and only had an attribute removed, so they keep the shape serialize_blocks() expects.
			$data['post_content'] = wp_slash( serialize_blocks( $blocks ) ); // @phpstan-ignore argument.type
		}

		return $data;
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

		wp_register_script( 'flx-blocks-lottieinteractivity-js', FLXBK_URL . 'includes/assets/js/lottie-interactivity.min.js', array(), FLXBK_VERSION, true );
		wp_register_script( 'flx-blocks-dotlottie-player-js', FLXBK_URL . 'includes/assets/js/lottie-dotlottie.min.js', array(), FLXBK_VERSION, true );
	}

	/**
	 * Whether an animation URL may be rendered for the current post.
	 *
	 *
	 * @param string $file_url The animation URL from the block attributes.
	 *
	 * @return bool
	 */
	private function is_external_url_allowed( $file_url ) {
		$post_id = (int) get_the_ID();

		if ( ! $post_id ) {
			return true;
		}

		$author_id = (int) get_post_field( 'post_author', $post_id );
		if ( $author_id && user_can( $author_id, 'unfiltered_html' ) ) {
			return true;
		}

		return $this->is_site_animation_url( $file_url );
	}

	/**
	 * Whether a URL points at this site's animations REST route.
	 *
	 * Media files and other site URLs skip the animation sanitizer, so only the route that
	 * sanitizes on serve is accepted. Browsers read a backslash as a slash, which PHP does not,
	 * so URLs containing one (or whitespace and control characters) are rejected.
	 *
	 *
	 * @param mixed $file_url The animation URL.
	 *
	 * @return bool
	 */
	private function is_site_animation_url( $file_url ) {
		if ( ! is_string( $file_url ) || preg_match( '/[\\\\\s\x00-\x1f\x7f]/', $file_url ) ) {
			return false;
		}

		$route = preg_replace( '#^https?:#i', '', get_rest_url( null, 'fxb-lottieanimation/v1/animations/' ) );

		return (bool) preg_match( '#^https?:' . preg_quote( (string) $route, '#' ) . '[a-zA-Z0-9]+(\.json)?(\?[^\#]*)?$#i', $file_url );
	}

	/**
	 * Recursively removes external URLs from Lottie blocks.
	 *
	 *
	 * @param array<int|string, mixed> $blocks  The parsed blocks.
	 * @param bool                     $changed Set to true when a block was changed.
	 *
	 * @return array<int|string, mixed> The parsed blocks.
	 */
	private function strip_external_urls_from_blocks( array $blocks, bool &$changed ) {
		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( 'flx/lottie' === ( $block['blockName'] ?? '' ) && is_array( $block['attrs'] ?? null ) && isset( $block['attrs']['fileUrl'] ) && ! $this->is_site_animation_url( $block['attrs']['fileUrl'] ) ) {
				unset( $block['attrs']['fileUrl'] );
				$changed = true;
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->strip_external_urls_from_blocks( $block['innerBlocks'], $changed );
			}

			$blocks[ $index ] = $block;
		}

		return $blocks;
	}
}

Flx_Blocks_Lottie_Block::get_instance();
