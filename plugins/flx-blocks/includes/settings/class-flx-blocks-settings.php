<?php
/**
 * Build Welcome Page With settings.
 *
 * @package Flux Block Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Build Welcome Page class
 *
 * @category class
 */
class Flx_Blocks_Settings {

	/**
	 * Settings of this class
	 *
	 * @var array
	 */
	public static $settings = [];

	/**
	 * Instance of this class
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Instance of this class
	 *
	 * @var null
	 */
	private static $editor_width = null;

	/**
	 * Used to cache token authorization to prevent multiple
	 * remote requests to the licensing server in the same
	 * request lifecycle.
	 *
	 * @var null|bool
	 */
	private static $authorized_cache = null;

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
	 * Class Constructor.
	 */
	public function __construct() {
		// only load if admin.
		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'add_menu' ] );
			add_action( 'admin_head', [ $this, 'menu_logo' ] );
			add_action( 'network_admin_menu', [ $this, 'add_network_menu' ], 1 );
			add_filter( 'plugin_action_links_flx-blocks/flx-blocks.php', [ $this, 'add_settings_link' ] );
			add_action( 'in_plugin_update_message-flx-blocks/flx-blocks.php', [ $this, 'plugin_update_message' ], 10, 2 );
		}
		add_action( 'wp_ajax_flxbk_activate_deactivate', [ $this, 'ajax_blocks_activate_deactivate' ], 10, 0 );
		add_action( 'wp_ajax_flxbk_save_config', [ $this, 'ajax_blocks_save_config' ], 10, 0 );
		add_action( 'wp_ajax_flxbk_post_default_width', [ $this, 'ajax_default_editor_width' ], 10, 0 );
		add_action( 'enqueue_block_editor_assets', [ $this, 'deregister_blocks' ] );
		add_action( 'admin_init', [ $this, 'activation_redirect' ] );
		add_action( 'admin_init', [ $this, 'load_settings' ] );
		add_action( 'init', [ $this, 'load_api_settings' ] );
		add_action( 'after_setup_theme', [ $this, 'load_color_palette' ], 999 );
		add_filter( 'block_editor_settings_all', [ $this, 'load_color_palette_editor_settings' ], 999 );
		add_filter( 'wp_theme_json_data_theme', [ $this, 'load_color_palette_theme_json' ], 999 );
		add_action( 'init', [ $this, 'init_post_meta' ] );
		add_action( 'admin_head-post.php', [ $this, 'admin_editor_width' ], 100 );
		add_action( 'admin_head-post-new.php', [ $this, 'admin_editor_width' ], 100 );
	}
	/**
	 * Add inline css editor width
	 */
	public function admin_editor_width() {
		if ( apply_filters( 'flxbk_editor_width', $this->show_editor_width() ) ) {
			$editor_widths = get_option( 'fxt_blocks_editor_width', [] );
			if ( ( ! isset( $editor_widths['enable_editor_width'] ) || 'true' === $editor_widths['enable_editor_width'] ) && apply_filters( 'flxbk_editor_width', true ) ) {
				if ( isset( $editor_widths['limited_margins'] ) && 'true' === $editor_widths['limited_margins'] ) {
					$add_size = 10;
				} else {
					$add_size = 30;
				}
				$post_type = get_post_type();
				if ( isset( $editor_widths['page_default'] ) && ! empty( $editor_widths['page_default'] ) && isset( $editor_widths['post_default'] ) && ! empty( $editor_widths['post_default'] ) ) {
					if ( isset( $post_type ) && 'page' === $post_type ) {
						$defualt_size_type = $editor_widths['page_default'];
					} else {
						$defualt_size_type = $editor_widths['post_default'];
					}
				} else {
					$defualt_size_type = 'sidebar';
				}
				if ( isset( $editor_widths['sidebar'] ) && ! empty( $editor_widths['sidebar'] ) ) {
					$sidebar_size = $editor_widths['sidebar'] + $add_size;
				} else {
					$sidebar_size = 750;
				}
				if ( isset( $editor_widths['nosidebar'] ) && ! empty( $editor_widths['nosidebar'] ) ) {
					$nosidebar_size = $editor_widths['nosidebar'] + $add_size;
				} else {
					$nosidebar_size = 1140 + $add_size;
				}
				if ( 'sidebar' == $defualt_size_type ) {
					$default_size = $sidebar_size;
				} elseif ( 'fullwidth' == $defualt_size_type ) {
					$default_size = 'none';
				} else {
					$default_size = $nosidebar_size;
				}
				if ( 'none' === $default_size ) {
					$jssize = 2000;
				} else {
					$jssize = $default_size;
				}
				echo '<style id="fxt-block-editor-width">';
				echo 'body.gutenberg-editor-page.fxt-editor-width-default .editor-post-title__block,
				body.gutenberg-editor-page.fxt-editor-width-default .editor-default-block-appender,
				body.gutenberg-editor-page.fxt-editor-width-default .block-editor-block-list__block,
				body.block-editor-page.fxt-editor-width-default .wp-block {
					max-width: ' . esc_attr( $default_size ) . ( is_numeric( $default_size ) ? 'px' : '' ) . ';
				}';
				echo 'body.gutenberg-editor-page.fxt-editor-width-sidebar .editor-post-title__block,
				body.gutenberg-editor-page.fxt-editor-width-sidebar .editor-default-block-appender,
				body.gutenberg-editor-page.fxt-editor-width-sidebar .block-editor-block-list__block,
				body.block-editor-page.fxt-editor-width-sidebar .wp-block {
					max-width: ' . esc_attr( $sidebar_size ) . 'px;
				}';
				echo 'body.gutenberg-editor-page.fxt-editor-width-nosidebar .editor-post-title__block,
				body.gutenberg-editor-page.fxt-editor-width-nosidebar .editor-default-block-appender,
				body.gutenberg-editor-page.fxt-editor-width-nosidebar .block-editor-block-list__block,
				body.block-editor-page.fxt-editor-width-nosidebar .wp-block {
					max-width: ' . esc_attr( $nosidebar_size ) . 'px;
				}';
				echo 'body.gutenberg-editor-page.fxt-editor-width-fullwidth .editor-post-title__block,
				body.gutenberg-editor-page.fxt-editor-width-fullwidth .editor-default-block-appender,
				body.gutenberg-editor-page.fxt-editor-width-fullwidth .block-editor-block-list__block,
				body.block-editor-page.fxt-editor-width-fullwidth .wp-block {
					max-width: none;
				}';
				echo 'body.gutenberg-editor-page .block-editor-block-list__layout .block-editor-block-list__block[data-align=wide],
				body.block-editor-page .block-editor-block-list__layout .wp-block[data-align=wide] {
					width: auto;
					max-width: ' . esc_attr( $nosidebar_size + 200 ) . 'px;
				}';

				echo 'body.gutenberg-editor-page .block-editor-block-list__layout .block-editor-block-list__block[data-align=full],
				body.block-editor-page .block-editor-block-list__layout .wp-block[data-align=full] {
					max-width: none;
				}';
				echo '</style>';
				echo '<script> var fxt_blocks_sidebar_size = ' . esc_attr( $sidebar_size ) . '; var fxt_blocks_nosidebar_size = ' . esc_attr( $nosidebar_size ) . '; var fxt_blocks_default_size = ' . esc_attr( $jssize ) . ';</script>';
			}
		}
	}
	/**
	 * Register Meta for blocks width
	 */
	public function init_post_meta() {
		if ( apply_filters( 'flxbk_editor_width', $this->show_editor_width() ) ) {
			register_post_meta(
				'',
				'fxt_blocks_editor_width',
				[
					'show_in_rest' => true,
					'single'       => true,
					'type'         => 'string',
				]
			);
		}
	}
	/**
	 * Load Gutenberg Palette in editor.
	 *
	 * @param array $settings The settings.
	 */
	public function load_color_palette_editor_settings( $settings ) {
		if ( flxbk_is_fse_mode() ) {
			return $settings;
		}

		$palette = json_decode( get_option( 'flxbk_colors' ), true );
		if ( isset( $palette['palette'] ) && is_array( $palette['palette'] ) ) {
			$san_palette = [];
			foreach ( $palette['palette'] as $item ) {
				$san_palette[] = [
					'color' => $item['color'],
					'name'  => $item['name'],
					'slug'  => $item['slug'],
				];
			}
			if ( isset( $san_palette[0] ) ) {
				if ( ( isset( $palette['override'] ) && true !== $palette['override'] ) || ! isset( $palette['override'] ) ) {
					$theme_palette = get_theme_support( 'editor-color-palette' );
					if ( isset( $settings['colors'] ) && is_array( $settings['colors'] ) ) {
						$newpalette = array_merge( $settings['colors'], $san_palette );
						$newpalette = array_values( array_map( 'unserialize', array_unique( array_map( 'serialize', $newpalette ) ) ) );
					} else {
						$default_palette = [
							[
								'name'  => __( 'Pale pink', 'flx-blocks' ),
								'slug'  => 'pale-pink',
								'color' => '#f78da7',
							],
							[
								'name'  => __( 'Vivid red', 'flx-blocks' ),
								'slug'  => 'vivid-red',
								'color' => '#cf2e2e',
							],
							[
								'name'  => __( 'Luminous vivid orange', 'flx-blocks' ),
								'slug'  => 'luminous-vivid-orange',
								'color' => '#ff6900',
							],
							[
								'name'  => __( 'Luminous vivid amber', 'flx-blocks' ),
								'slug'  => 'luminous-vivid-amber',
								'color' => '#fcb900',
							],
							[
								'name'  => __( 'Light green cyan', 'flx-blocks' ),
								'slug'  => 'light-green-cyan',
								'color' => '#7bdcb5',
							],
							[
								'name'  => __( 'Vivid green cyan', 'flx-blocks' ),
								'slug'  => 'vivid-green-cyan',
								'color' => '#00d084',
							],
							[
								'name'  => __( 'Pale cyan blue', 'flx-blocks' ),
								'slug'  => 'pale-cyan-blue',
								'color' => '#8ed1fc',
							],
							[
								'name'  => __( 'Vivid cyan blue', 'flx-blocks' ),
								'slug'  => 'vivid-cyan-blue',
								'color' => '#0693e3',
							],
							[
								'name'  => __( 'Very light gray', 'flx-blocks' ),
								'slug'  => 'very-light-gray',
								'color' => '#eeeeee',
							],
							[
								'name'  => __( 'Cyan bluish gray', 'flx-blocks' ),
								'slug'  => 'cyan-bluish-gray',
								'color' => '#abb8c3',
							],
							[
								'name'  => __( 'Very dark gray', 'flx-blocks' ),
								'slug'  => 'very-dark-gray',
								'color' => '#313131',
							],
						];
						$newpalette      = array_values( array_merge( $default_palette, $san_palette ) );
					}
				} else {
					$newpalette = array_values( $san_palette );
				}
				$settings['colors'] = $newpalette;
				if ( function_exists( 'get_block_editor_settings' ) ) {
					if ( isset( $settings['__experimentalFeatures']['color']['palette']['theme'] ) && is_array( $settings['__experimentalFeatures']['color']['palette']['theme'] ) ) {
						$newpalette = array_merge( $settings['__experimentalFeatures']['color']['palette']['theme'], $san_palette );
						$newpalette = array_values( array_map( 'unserialize', array_unique( array_map( 'serialize', $newpalette ) ) ) );
					}
					$settings['__experimentalFeatures']['color']['palette']['theme'] = $newpalette;
				}
			}
		}
		return $settings;
	}
	/**
	 * Load custom colors into theme.json for Site Editor compatibility.
	 *
	 * @param WP_Theme_JSON_Data $theme_json The theme.json data object.
	 * @return WP_Theme_JSON_Data Modified theme.json data.
	 */
	public function load_color_palette_theme_json( $theme_json ) {
		if ( flxbk_is_fse_mode() ) {
			return $theme_json;
		}
		$palette = json_decode( get_option( 'flxbk_colors' ), true );
		if ( isset( $palette['palette'] ) && is_array( $palette['palette'] ) && ! empty( $palette['palette'] ) ) {
			$san_palette = [];
			foreach ( $palette['palette'] as $item ) {
				$san_palette[] = [
					'color' => $item['color'],
					'name'  => $item['name'],
					'slug'  => $item['slug'],
				];
			}
			if ( ! empty( $san_palette ) ) {
				$data = $theme_json->get_data();
				$override = isset( $palette['override'] ) && true === $palette['override'];

				if ( $override ) {
					// Override mode: only show custom colors, replace theme palette.
					$theme_json->update_with(
						[
							'version'  => 2,
							'settings' => [
								'color' => [
									'palette' => [
										'theme' => $san_palette,
									],
								],
							],
						]
					);
				} else {
					// Merge mode: add custom colors to theme palette.
					$existing_theme = isset( $data['settings']['color']['palette']['theme'] ) && ! empty( $data['settings']['color']['palette']['theme'] )
						? $data['settings']['color']['palette']['theme']
						: [];

					// If theme.json has no colors, fall back to colors registered via
					// add_theme_support( 'editor-color-palette' ) — e.g. Blocksy registers
					// its palette this way rather than through theme.json.
					if ( empty( $existing_theme ) ) {
						$theme_support = get_theme_support( 'editor-color-palette' );
						if ( is_array( $theme_support ) && ! empty( $theme_support[0] ) ) {
							$existing_theme = $theme_support[0];
						}
					}

					// Merge custom colors with theme palette.
					$merged_palette = array_merge( $existing_theme, $san_palette );
					$merged_palette = array_values( array_map( 'unserialize', array_unique( array_map( 'serialize', $merged_palette ) ) ) );

					$theme_json->update_with(
						[
							'version'  => 2,
							'settings' => [
								'color' => [
									'palette' => [
										'theme' => $merged_palette,
									],
								],
							],
						]
					);
				}
			}
		}
		return $theme_json;
	}
	/**
	 * Load Gutenberg Palette
	 *
	 */
	public function load_color_palette() {
		$palette = json_decode( get_option( 'flxbk_colors' ) );
		if ( $palette && is_object( $palette ) && isset( $palette->palette ) && is_array( $palette->palette ) ) {
			$san_palette = [];
			foreach ( $palette->palette as $item ) {
				$san_palette[] = [
					'color' => $item->color,
					'name'  => $item->name,
					'slug'  => $item->slug,
				];
			}
			if ( isset( $san_palette[0] ) ) {
				if ( ( isset( $palette->override ) && true !== $palette->override ) || ! isset( $palette->override ) ) {
					$theme_palette = get_theme_support( 'editor-color-palette' );
					if ( is_array( $theme_palette ) ) {
						$new_palette = array_merge( reset( $theme_palette ), $san_palette );
					} else {
						$default_palette = [
							[
								'name'  => __( 'Pale pink', 'flx-blocks' ),
								'slug'  => 'pale-pink',
								'color' => '#f78da7',
							],
							[
								'name'  => __( 'Vivid red', 'flx-blocks' ),
								'slug'  => 'vivid-red',
								'color' => '#cf2e2e',
							],
							[
								'name'  => __( 'Luminous vivid orange', 'flx-blocks' ),
								'slug'  => 'luminous-vivid-orange',
								'color' => '#ff6900',
							],
							[
								'name'  => __( 'Luminous vivid amber', 'flx-blocks' ),
								'slug'  => 'luminous-vivid-amber',
								'color' => '#fcb900',
							],
							[
								'name'  => __( 'Light green cyan', 'flx-blocks' ),
								'slug'  => 'light-green-cyan',
								'color' => '#7bdcb5',
							],
							[
								'name'  => __( 'Vivid green cyan', 'flx-blocks' ),
								'slug'  => 'vivid-green-cyan',
								'color' => '#00d084',
							],
							[
								'name'  => __( 'Pale cyan blue', 'flx-blocks' ),
								'slug'  => 'pale-cyan-blue',
								'color' => '#8ed1fc',
							],
							[
								'name'  => __( 'Vivid cyan blue', 'flx-blocks' ),
								'slug'  => 'vivid-cyan-blue',
								'color' => '#0693e3',
							],
							[
								'name'  => __( 'Very light gray', 'flx-blocks' ),
								'slug'  => 'very-light-gray',
								'color' => '#eeeeee',
							],
							[
								'name'  => __( 'Cyan bluish gray', 'flx-blocks' ),
								'slug'  => 'cyan-bluish-gray',
								'color' => '#abb8c3',
							],
							[
								'name'  => __( 'Very dark gray', 'flx-blocks' ),
								'slug'  => 'very-dark-gray',
								'color' => '#313131',
							],
						];
						$new_palette     = array_merge( $default_palette, $san_palette );
					}
				} else {
					$new_palette = $san_palette;
				}
				if ( ! flxbk_is_fse_mode() ) {
					add_theme_support( 'editor-color-palette', $new_palette );
				}
				add_action( 'wp_head', [ $this, 'print_color_palette_css' ], 8 );
				add_filter( 'block_editor_settings_all', [ $this, 'add_color_palette_css_to_block_editor' ], 999 );
			}
		}
	}

	/**
	 * Prints the stored palette's color classes on the frontend.
	 */
	public function print_color_palette_css() {
		if ( $css = $this->get_color_palette_css() ) {
			printf( '<style id="flxbk_palette_css">%s</style>', $css ); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Adds the stored palette's color classes to the block editor styles.
	 *
	 * @param array{styles?: array<int, array<string, string>>} $settings The block editor settings.
	 *
	 * @return array{styles?: array<int, array<string, string>>}
	 */
	public function add_color_palette_css_to_block_editor(array $settings) {
		if ( $css = $this->get_color_palette_css() ) {
			$settings['styles'][] =[
				'css' => $css,
			];
		}

		return $settings;
	}

	private function get_color_palette_css() {
		$palette = json_decode( get_option( 'flxbk_colors' ) );

		if ( $palette && is_object( $palette ) && isset( $palette->palette ) && is_array( $palette->palette ) ) {
			$san_palette = [];
			foreach ( $palette->palette as $item ) {
				$san_palette[] = [
					'color' => $item->color,
					'name'  => $item->name,
					'slug'  => $item->slug,
				];
			}
			if ( isset( $san_palette[0] ) ) {
				$color_palette_css = '';
				foreach ( $san_palette as $set ) {
					$slug  = $set['slug'];
					$color = $set['color'];
					$color_palette_css .= ':root .has-' . esc_attr( $slug ) . '-color{color:' . esc_attr( $color ) . '}:root .has-' . esc_attr( $slug ) . '-background-color{background-color:' . esc_attr( $color ) . '}';
				}

				return $color_palette_css;
			}
		}

		return null;
	}

	/**
	 * Redirect to the settings page on activation
	 */
	public function activation_redirect() {
		if ( get_option( 'flxbk_redirect_on_activation', false ) ) {
			delete_option( 'flxbk_redirect_on_activation' );
			// @todo add admin notice for optional data share.
		}
	}
	/**
	 * Redirect to the settings page on activation.
	 *
	 * @param string $key setting key.
	 */
	public static function get_data_options( $key ) {
		if ( ! isset( self::$settings[ $key ] ) ) {
			self::$settings[ $key ] = get_option( $key, [] );
		}
		return self::$settings[ $key ];
	}
	/**
	 * Deregister Blocks.
	 */
	public function deregister_blocks() {
		// Scripts.
		$options = get_option( 'fxt_blocks_unregistered_blocks' );
		if ( isset( $options['flx/advnacedgallery'] ) ) {
			unset( $options['flx/advnacedgallery'] );
			update_option( 'fxt_blocks_unregistered_blocks', $options );
		}
		// Don't need to unregister a block that is not loaded.
		if ( ! class_exists( 'woocommerce' ) && is_array( $options ) ) {
			if ( in_array( 'flx/productcarousel', $options, true ) ) {
				foreach ( $options as $key => $block ) {
					if ( $block === 'flx/productcarousel' ) {
						unset( $options[ $key ] );
					}
				}
			}
		}
		if ( $options ) {
			wp_enqueue_script( 'flx-blocks-deregister-js', FLXBK_URL . 'includes/assets/js/admin-blocks-deregister.min.js', [ 'wp-blocks' ], FLXBK_VERSION, true );
			wp_localize_script(
				'flx-blocks-deregister-js',
				'fxt_deregister_params',
				[
					'deregister' => $options,
				]
			);
		}
	}
	/**
	 * Colored menu logo (WordPress paints data-URI SVGs in one color).
	 */
	public function menu_logo() {
		echo '<style>#toplevel_page_flx-blocks .wp-menu-image{background:url(' . esc_url( FLXBK_URL . 'assets/icon.svg?ver=' . FLXBK_VERSION ) . ') center/20px 20px no-repeat}#toplevel_page_flx-blocks .wp-menu-image::before{content:none}</style>';
	}
	/**
	 * Allow settings visibility to be changed.
	 */
	public function settings_user_capabilities() {
		return apply_filters( 'flxbk_admin_settings_capability', 'edit_pages' );
	}
	/**
	 * Add option page menu
	 */
	public function add_network_menu() {
	}
	/**
	 * Add option page menu
	 */
	public function add_menu() {
		$page = add_menu_page( __( 'Flux Block Editor', 'flx-blocks' ), __( 'Block Editor', 'flx-blocks' ), $this->settings_user_capabilities(), 'flx-blocks', [ $this, 'config_page' ], 'none' );
		add_action( 'admin_print_styles-' . $page, [ $this, 'scripts' ] );
	}
	/**
	 * Loads admin style sheets and scripts
	 */
	public function scripts() {
		$texts    = [
			'close'    => __( 'Close', 'flx-blocks' ),
			'save'     => __( 'Save', 'flx-blocks' ),
			'updating' => __( 'Updating', 'flx-blocks' ),
			'updated'  => __( 'Updated', 'flx-blocks' ),
			'config'   => __( 'Config', 'flx-blocks' ),
			'settings' => __( 'Default Settings', 'flx-blocks' ),
		];
		$settings = [
			'flx/spacer'          => [
				'moved' => [
					'type' => 'info',
					'name' => __( 'Setting defaults has moved into Gutenberg, click on the top right corner settings and go to Flux Block Editor Controls', 'flx-blocks' ),
				],
			],
			'flx/rowlayout'       => [
				'moved' => [
					'type' => 'info',
					'name' => __( 'Setting defaults has moved into Gutenberg, click on the top right corner settings and go to Flux Block Editor Controls', 'flx-blocks' ),
				],
			],
			'flx/advancedbtn'     => [
				'moved' => [
					'type' => 'info',
					'name' => __( 'Setting defaults has moved into Gutenberg, click on the top right corner settings and go to Flux Block Editor Controls', 'flx-blocks' ),
				],
			],
			'flx/infobox'         => [
				'moved' => [
					'type' => 'info',
					'name' => __( 'Setting defaults has moved into Gutenberg, click on the top right corner settings and go to Flux Block Editor Controls', 'flx-blocks' ),
				],
			],
			'flx/accordion'       => [
				'moved' => [
					'type' => 'info',
					'name' => __( 'Setting defaults has moved into Gutenberg, click on the top right corner settings and go to Flux Block Editor Controls', 'flx-blocks' ),
				],
			],
			'flx/tabs'            => [
				'moved' => [
					'type' => 'info',
					'name' => __( 'Setting defaults has moved into Gutenberg, click on the top right corner settings and go to Flux Block Editor Controls', 'flx-blocks' ),
				],
			],
			'flx/iconlist'        => [
				'moved' => [
					'type' => 'info',
					'name' => __( 'Setting defaults has moved into Gutenberg, click on the top right corner settings and go to Flux Block Editor Controls', 'flx-blocks' ),
				],
			],
			'flx/testimonials'    => [
				'moved' => [
					'type' => 'info',
					'name' => __( 'Setting defaults has moved into Gutenberg, click on the top right corner settings and go to Flux Block Editor Controls', 'flx-blocks' ),
				],
			],
			'flx/advancedheading' => [
				'moved' => [
					'type' => 'info',
					'name' => __( 'Setting defaults has moved into Gutenberg, click on the top right corner settings and go to Flux Block Editor Controls', 'flx-blocks' ),
				],
			],
			'flx/icon'            => [
				'moved' => [
					'type' => 'info',
					'name' => __( 'Setting defaults has moved into Gutenberg, click on the top right corner settings and go to Flux Block Editor Controls', 'flx-blocks' ),
				],
			],
		];
		wp_enqueue_style( 'flx-blocks-admin-css', FLXBK_URL . 'includes/assets/css/admin-dashboard.min.css', [ 'wp-jquery-ui-dialog', 'wp-color-picker' ], FLXBK_VERSION, 'all' );
		wp_enqueue_script( 'flx-blocks-admin-js', FLXBK_URL . 'includes/assets/js/admin-scripts.min.js', [ 'jquery', 'jquery-ui-dialog', 'wp-color-picker' ], FLXBK_VERSION, true );
		wp_localize_script(
			'flx-blocks-admin-js',
			'fxt_blocks_params',
			[
				'ajaxurl'             => admin_url( 'admin-ajax.php' ),
				'wpnonce'             => wp_create_nonce( 'flx-blocks-manage' ),
				'blockConfig'         => get_option( 'fxt_blocks_config_blocks' ),
				'blockConfigSettings' => $settings,
				'texts'               => $texts,
			]
		);
	}
	/**
	 * Register settings
	 */
	public function load_api_settings() {

		register_setting(
			'flxbk_config_blocks',
			'flxbk_config_blocks',
			[
				'type'              => 'string',
				'description'       => __( 'Config Flux Block Defaults', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_settings_blocks',
			'flxbk_settings_blocks',
			[
				'type'              => 'string',
				'description'       => __( 'Config Flux Block visibility settings', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_settings',
			'flxbk_settings',
			[
				'type'              => 'string',
				'description'       => __( 'Config Flux Block Settings', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_prophecy',
			'flxbk_prophecy',
			[
				'type'              => 'string',
				'description'       => __( 'Config Flux Block Prophecy AI', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_colors',
			'flxbk_colors',
			[
				'type'              => 'string',
				'description'       => __( 'Config Flux Block Editor Color Palette', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_global',
			'flxbk_global',
			[
				'type'              => 'string',
				'description'       => __( 'Config Flux Block Editor Global Settings', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_recaptcha_site_key',
			'flxbk_recaptcha_site_key',
			[
				'type'              => 'string',
				'description'       => __( 'Google reCaptcha Site Key', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_recaptcha_secret_key',
			'flxbk_recaptcha_secret_key',
			[
				'type'              => 'string',
				'description'       => __( 'Google reCaptcha Secret Key', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_recaptcha_language',
			'flxbk_recaptcha_language',
			[
				'type'              => 'string',
				'description'       => __( 'Google reCaptcha Language', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_turnstile_site_key',
			'flxbk_turnstile_site_key',
			[
				'type'              => 'string',
				'description'       => __( 'Cloudflare Turnstile Site Key', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_turnstile_secret_key',
			'flxbk_turnstile_secret_key',
			[
				'type'              => 'string',
				'description'       => __( 'Cloudflare Turnstile Secret Key', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_hcaptcha_site_key',
			'flxbk_hcaptcha_site_key',
			[
				'type'              => 'string',
				'description'       => __( 'hCaptcha Turnstile Site Key', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_hcaptcha_secret_key',
			'flxbk_hcaptcha_secret_key',
			[
				'type'              => 'string',
				'description'       => __( 'hCaptcha Secret Key', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_mailerlite_api',
			'flxbk_mailerlite_api',
			[
				'type'              => 'string',
				'description'       => __( 'MailerLite API Key', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_google_maps_api',
			'flxbk_google_maps_api',
			[
				'type'              => 'string',
				'description'       => __( 'Google Maps API Key', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_cloud',
			'flxbk_cloud',
			[
				'type'              => 'string',
				'description'       => __( 'Config Flux Block Editor Cloud', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_header_notice_dismissed',
			'flxbk_header_notice_dismissed',
			[
				'type'              => 'boolean',
				'description'       => __( 'Is Flux header notice dismissed', 'flx-blocks' ),
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
				'default'           => false,
			]
		);
		register_setting(
			'flxbk_wire_subscribe',
			'flxbk_wire_subscribe',
			[
				'type'              => 'boolean',
				'description'       => __( 'Subscribe to Wireframe library', 'flx-blocks' ),
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
				'default'           => false,
			]
		);
		register_setting(
			'flxbk_convertkit_api',
			'flxbk_convertkit_api',
			[
				'type'              => 'string',
				'description'       => __( 'ConvertKit API Key', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_activecampaign_api_key',
			'flxbk_activecampaign_api_key',
			[
				'type'              => 'string',
				'description'       => __( 'ConvertKit API Key', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_activecampaign_api_base',
			'flxbk_activecampaign_api_base',
			[
				'type'              => 'string',
				'description'       => __( 'ConvertKit API Key', 'flx-blocks' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => true,
				'default'           => '',
			]
		);
		register_setting(
			'flxbk_header_popover_tutorial_complete',
			'flxbk_header_popover_tutorial_complete',
			[
				'type'              => 'boolean',
				'description'       => __( 'Mark the Header popover tutorial Complete', 'flx-blocks' ),
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
				'default'           => false,
			]
		);
	}
	/**
	 * Register settings
	 */
	public function load_settings() {

		register_setting( 'fxt_blocks_editor_width', 'fxt_blocks_editor_width', [ $this, 'validate_options' ] );

		// Sidebar and No sidebar Max widths.
		add_settings_section( 'fxt_blocks_editor_width_sec', '', [ $this, 'maxwidths_callback' ], 'fxt_blocks_editor_width_section' );
		add_settings_field( 'sidebar', __( 'Small Template', 'flx-blocks' ), [ $this, 'sidebar_callback' ], 'fxt_blocks_editor_width_section', 'fxt_blocks_editor_width_sec' );
		add_settings_field( 'nosidebar', __( 'Large Template', 'flx-blocks' ), [ $this, 'nosidebar_callback' ], 'fxt_blocks_editor_width_section', 'fxt_blocks_editor_width_sec' );
		// Defaults for Pages and posts.
		add_settings_field( 'post_default', __( 'Post default', 'flx-blocks' ), [ $this, 'post_default_callback' ], 'fxt_blocks_editor_width_section', 'fxt_blocks_editor_width_sec' );
		add_settings_field( 'page_default', __( 'Page Default', 'flx-blocks' ), [ $this, 'page_default_callback' ], 'fxt_blocks_editor_width_section', 'fxt_blocks_editor_width_sec' );
		// add_settings_field( 'limited_margins', __( 'Enable Less Margin CSS', 'flx-blocks' ), array( $this, 'limited_margins_callback' ), 'fxt_blocks_editor_width_section', 'fxt_blocks_editor_width_sec' );
		add_settings_field( 'enable_editor_width', __( 'Enable Editor Width', 'flx-blocks' ), [ $this, 'enabled_editor_width_callback' ], 'fxt_blocks_editor_width_section', 'fxt_blocks_editor_width_sec' );

		if ( ! defined( 'FLXBK_VERSION' ) ) {
			register_setting( 'flxbk_font_settings', 'flxbk_font_settings', [ $this, 'validate_options' ] );
			add_settings_section( 'fxt_blocks_fonts_sec', '', [ $this, 'fonts_local_callback' ], 'fxt_blocks_fonts_section' );
			add_settings_field( 'load_fonts_local', __( 'Load Google Fonts Locally', 'flx-blocks' ), [ $this, 'load_fonts_local_callback' ], 'fxt_blocks_fonts_section', 'fxt_blocks_fonts_sec' );
		}
		register_setting( 'flxbk_admin_bar_settings', 'flxbk_admin_bar_settings', [ $this, 'validate_options' ] );
		add_settings_section( 'fxt_blocks_admin_bar_sec', '', [ $this, 'admin_bar_callback' ], 'fxt_blocks_admin_bar_section' );
		add_settings_field( 'show_headers_in_admin_bar', __( 'Show Block Headers in Admin Bar', 'flx-blocks' ), [ $this, 'show_headers_in_admin_bar_callback' ], 'fxt_blocks_admin_bar_section', 'fxt_blocks_admin_bar_sec' );
	}
	/**
	 * Outputs Sidebar number field
	 */
	public function enabled_editor_width_callback() {
		$data            = self::get_data_options( 'fxt_blocks_editor_width' );
		$default_enabled = ( $data['enable_editor_width'] ?? 'true' );
		echo '<p>' . esc_html__( 'Allows for changes to the editor width on per page/post basis with preset defaults.', 'flx-blocks' ) . '<p>';
		echo '<select class="fxt-blocks-enable-editor-width fxt-editor-width-defaults-select" name="fxt_blocks_editor_width[enable_editor_width]">';
			echo '<option value="false" ' . ( 'false' === $default_enabled ? 'selected' : '' ) . '>' . esc_html__( 'False', 'flx-blocks' ) . '</option>';
			echo '<option value="true" ' . ( 'true' === $default_enabled ? 'selected' : '' ) . '>' . esc_html__( 'True', 'flx-blocks' ) . '</option>';
		echo '</select>';
	}

	/**
	 * Outputs Limited Margins Field
	 */
	public function limited_margins_callback() {
		$data            = self::get_data_options( 'fxt_blocks_editor_width' );
		$default_limited = ( $data['limited_margins'] ?? 'false' );
		echo '<p>' . esc_html__( 'Experimental editor CSS for less excess margins in Gutenberg in an attempt to get a closer WYSIWYG editing experience.', 'flx-blocks' ) . '<p>';
		echo '<select class="fxt-blocks-limited-margins fxt-editor-width-defaults-select" name="fxt_blocks_editor_width[limited_margins]">';
			echo '<option value="false" ' . ( 'false' === $default_limited ? 'selected' : '' ) . '>' . esc_html__( 'False', 'flx-blocks' ) . '</option>';
			echo '<option value="true" ' . ( 'true' === $default_limited ? 'selected' : '' ) . '>' . esc_html__( 'True', 'flx-blocks' ) . '</option>';
		echo '</select>';
	}

	/**
	 * Outputs Sidebar number field
	 */
	public function sidebar_callback() {
		$data    = self::get_data_options( 'fxt_blocks_editor_width' );
		$default = apply_filters( 'flxbk_default_small_editor_width', '750' );
		echo "<input id='fxt-sidebar-max' name='fxt_blocks_editor_width[sidebar]' size='25' type='number' value='" . ( isset( $data['sidebar'] ) ? esc_attr( $data['sidebar'] ) : esc_attr( $default ) ) . "' />";
		echo '<span class="fxt-sub-input-description">' . esc_html__( 'px', 'flx-blocks' ) . '</span>';
	}

	/**
	 * Outputs no sidebar number field
	 */
	public function nosidebar_callback() {
		$data    = self::get_data_options( 'fxt_blocks_editor_width' );
		$default = apply_filters( 'flxbk_default_large_editor_width', '1140' );
		echo "<input id='fxt-sidebar-max' name='fxt_blocks_editor_width[nosidebar]' size='25' type='number' value='" . ( isset( $data['nosidebar'] ) ? esc_attr( $data['nosidebar'] ) : esc_attr( $default ) ) . "' />";
		echo '<span class="fxt-sub-input-description">' . esc_html__( 'px', 'flx-blocks' ) . '</span>';
	}

	/**
	 * Outputs post default select feild
	 */
	public function post_default_callback() {
		$data              = self::get_data_options( 'fxt_blocks_editor_width' );
		$default_post_type = ( $data['post_default'] ?? 'sidebar' );
		echo '<select class="fxt-blocks-posts-defaults fxt-editor-width-defaults-select" name="fxt_blocks_editor_width[post_default]">';
			echo '<option value="sidebar" ' . ( 'sidebar' === $default_post_type ? 'selected' : '' ) . '>' . esc_html__( 'Small', 'flx-blocks' ) . '</option>';
			echo '<option value="nosidebar" ' . ( 'nosidebar' === $default_post_type ? 'selected' : '' ) . '>' . esc_html__( 'Large', 'flx-blocks' ) . '</option>';
			echo '<option value="fullwidth" ' . ( 'fullwidth' === $default_post_type ? 'selected' : '' ) . '>' . esc_html__( 'Fullwidth', 'flx-blocks' ) . '</option>';
		echo '</select>';
	}

	/**
	 * Outputs post default select feild
	 */
	public function page_default_callback() {
		$data              = self::get_data_options( 'fxt_blocks_editor_width' );
		$default_page_type = ( $data['page_default'] ?? 'sidebar' );
		echo '<select class="fxt-blocks-posts-defaults fxt-editor-width-defaults-select" name="fxt_blocks_editor_width[page_default]">';
			echo '<option value="sidebar" ' . ( 'sidebar' === $default_page_type ? 'selected' : '' ) . '>' . esc_html__( 'Small', 'flx-blocks' ) . '</option>';
			echo '<option value="nosidebar" ' . ( 'nosidebar' === $default_page_type ? 'selected' : '' ) . '>' . esc_html__( 'Large', 'flx-blocks' ) . '</option>';
			echo '<option value="fullwidth" ' . ( 'fullwidth' === $default_page_type ? 'selected' : '' ) . '>' . esc_html__( 'Fullwidth', 'flx-blocks' ) . '</option>';
		echo '</select>';
	}

	/**
	 * Outputs font settings field
	 */
	public function load_fonts_local_callback() {
		$data    = self::get_data_options( 'flxbk_font_settings' );
		$default = ( $data['load_fonts_local'] ?? 'true' );
		echo '<select class="fxt-blocks-limited-margins fxt-editor-width-defaults-select" name="flxbk_font_settings[load_fonts_local]">';
			echo '<option value="false" ' . ( 'false' === $default ? 'selected' : '' ) . '>' . esc_html__( 'False', 'flx-blocks' ) . '</option>';
			echo '<option value="true" ' . ( 'true' === $default ? 'selected' : '' ) . '>' . esc_html__( 'True', 'flx-blocks' ) . '</option>';
		echo '</select>';
	}

	/**
	 * Outputs admin bar settings field
	 */
	public function show_headers_in_admin_bar_callback() {
		$data    = self::get_data_options( 'flxbk_admin_bar_settings' );
		$default = ( $data['show_headers_in_admin_bar'] ?? 'false' );
		echo '<select class="fxt-blocks-limited-margins fxt-editor-width-defaults-select" name="flxbk_admin_bar_settings[show_headers_in_admin_bar]">';
			echo '<option value="false" ' . ( 'false' === $default ? 'selected' : '' ) . '>' . esc_html__( 'False', 'flx-blocks' ) . '</option>';
			echo '<option value="true" ' . ( 'true' === $default ? 'selected' : '' ) . '>' . esc_html__( 'True', 'flx-blocks' ) . '</option>';
		echo '</select>';
	}

	/**
	 * Outputs title for content width.
	 */
	public function maxwidths_callback() {
		// global $content_width;
		// echo '<h5 class="fxt-main-subtitle">' . esc_html__( 'Assign Editor Template Max Widths', 'flx-blocks' ) . '</h5>';
		// echo '<div class="fxt-main-description-notice">' . esc_html__( 'Note: The current active themes "$content_width" is set to:', 'flx-blocks' ) . ' ' . esc_html( $content_width ) . 'px</div>';
	}

	/**
	 * Outputs title for fonts local.
	 */
	public function fonts_local_callback() {
	}

	/**
	 * Outputs title for admin bar.
	 */
	public function admin_bar_callback() {
	}
	/**
	 * Sanitizes and validates all input and output for Dashboard.
	 *
	 * @param array $input settings input.
	 */
	public function validate_options( $input ) {
		return $input;
	}

	/**
	 * Checks for flxbk classic themes when returning defualt.
	 */
	public function show_editor_width() {
		if ( is_null( self::$editor_width ) ) {
			$show                   = false;
			$current_theme          = wp_get_theme();
			$current_theme_name     = $current_theme->get( 'Name' );
			$current_theme_template = $current_theme->get( 'Template' );
			// Check for a flxbk classic theme support.
			if ( 'Pinnacle' == $current_theme_name || 'pinnacle' == $current_theme_template || 'Pinnacle Premium' == $current_theme_name || 'pinnacle_premium' == $current_theme_template || 'Ascend - Premium' == $current_theme_name || 'ascend_premium' == $current_theme_template || 'Ascend' == $current_theme_name || 'ascend' == $current_theme_template || 'Virtue - Premium' == $current_theme_name || 'virtue_premium' == $current_theme_template || 'Virtue' == $current_theme_name || 'virtue' == $current_theme_template ) {
				$show = true;
			}
			self::$editor_width = $show;
		}
		return self::$editor_width;
	}
	/**
	 * Loads config page
	 */
	public function config_page() {
		?>
		<div class="flxbk_dash_head">
			<div class="flxbk_dash_head_container">
				<div class="flxbk_dash_version">
					<span>
						<?php echo esc_html( apply_filters( 'flxbk_brand_name', 'Flux Block Editor' ) ) . ' ' . esc_html( FLXBK_VERSION ); ?>
					</span>
				</div>
			</div>
		</div>
		<div class="wrap flxbk_dash">
			<div class="flxbk_dashboard">
				<h2 class="notices" style="display:none;"></h2>
				<?php settings_errors(); ?>
				<div class="page-grid">
					<div class="flxbk_dashboard_main">
						<h2 class="section-header"><?php echo esc_html( apply_filters( 'flxbk_brand_name', 'Flux Block Editor' ) ); ?></h2>
						<div class="three-col-grid">
							<?php
							$blocks              = $this->blocks_array();
							$unregistered_blocks = get_option( 'fxt_blocks_unregistered_blocks', [] );
							if ( ! is_array( $unregistered_blocks ) ) {
								$unregistered_blocks = [];
							}
							foreach ( $blocks as $block_key => $block ) {
								if ( in_array( $block['slug'], $unregistered_blocks ) ) {
									$btn_enabled_class = 'button-primary fxt-block-inactive';
									$enabled_class     = 'fxt-block-inactive';
								} else {
									$btn_enabled_class = 'fxt-block-active';
									$enabled_class     = 'fxt-block-active';
								}
								echo '<div class="flxbk_item ' . esc_attr( $enabled_class ) . '">';
								echo '<h3>' . esc_html( $block['name'] ) . '</h3>';
								echo '<p>' . wp_kses_post( $block['desc'] ) . '</p>';
								echo '<div class="flxbk_item_footer">';
								if ( in_array( $block['slug'], $unregistered_blocks ) ) {
									$btntitle = __( 'Activate', 'flx-blocks' );
									echo '<a class="fxt_block_button button ' . esc_attr( $btn_enabled_class ) . '" data-inactive-label="' . esc_attr__( 'Activate', 'flx-blocks' ) . '" data-active-label="' . esc_attr__( 'Deactivate', 'flx-blocks' ) . '" data-activating-label="' . esc_attr__( 'Activating…', 'flx-blocks' ) . '" data-activated-label="' . esc_attr__( 'Activated', 'flx-blocks' ) . '"  data-deactivating-label="' . esc_attr__( 'Deactivating…', 'flx-blocks' ) . '"  data-deactivated-label="' . esc_attr__( 'Deactivated', 'flx-blocks' ) . '" data-block-slug="' . esc_attr( $block['slug'] ) . '" href="#">' . esc_html( $btntitle ) . '</a>';
								} else {
									$btntitle = __( 'Deactivate', 'flx-blocks' );
									// echo '<a class="fxt_block_button button ' . esc_attr( $btn_enabled_class ) . '" data-inactive-label="' . esc_attr__( 'Activate', 'flx-blocks' ) . '" data-active-label="' . esc_attr__( 'Deactivate', 'flx-blocks' ) . '" data-activating-label="' . esc_attr__( 'Activating…', 'flx-blocks' ) . '" data-activated-label="' . esc_attr__( 'Activated', 'flx-blocks' ) . '"  data-deactivating-label="' . esc_attr__( 'Deactivating…', 'flx-blocks' ) . '"  data-deactivated-label="' . esc_attr__( 'Deactivated', 'flx-blocks' ) . '" data-block-slug="' . esc_attr( $block['slug'] ) . '" href="#">' . esc_html( $btntitle ) . '</a>';
								}
								if ( ! empty( $block['link'] ) && ! empty( $block['linkText'] ) ) {
									echo '<a class="button" href="' . esc_attr( $block['link'] ) . '">' . esc_html( $block['linkText'] ) . '</a>';
								}

								echo '</div>';
								echo '</div>';
							}
							?>
						</div>
						<div class="fxt-dashboard-spacer"></div>
						<?php if ( apply_filters( 'flxbk_editor_width', $this->show_editor_width() ) ) { ?>
							<h2><?php echo esc_html__( 'Editor Max Widths', 'flx-blocks' ); ?></br><small class="fxt-main-subtitle"><?php echo esc_html__( 'Match the editor width to your sites width.', 'flx-blocks' ); ?></small></h2>
							<?php global $content_width; ?>
							<div class="fxt-main-description-notice"><?php echo esc_html__( 'Note: The current active themes "content_width" is set to:', 'flx-blocks' ) . ' ' . esc_html( $content_width ); ?>px</div>
							<div class="fxt-promo-row-area">
								<?php
								echo '<form action="options.php" method="post">';
									settings_fields( 'fxt_blocks_editor_width' );
									do_settings_sections( 'fxt_blocks_editor_width_section' );
									do_settings_sections( 'fxt_blocks_editor_defaults_section' );
									submit_button( __( 'Save Changes', 'flx-blocks' ) );
								echo '</form>';
								?>
							</div>
							<div class="fxt-dashboard-spacer"></div>
						<?php } ?>
						<?php if ( apply_filters( 'flxbk_show_local_fonts', ! defined( 'FLXBK_VERSION' ) ) ) { ?>
								<h2><?php echo esc_html__( 'Google Fonts', 'flx-blocks' ); ?></h2>
								<div class="fxt-promo-row-area">
								<?php
								echo '<form action="options.php" method="post">';
									settings_fields( 'flxbk_font_settings' );
									do_settings_sections( 'fxt_blocks_fonts_section' );
									submit_button( __( 'Save Changes', 'flx-blocks' ) );
								echo '</form>';
								?>
							</div>
							<div class="fxt-dashboard-spacer"></div>
						<?php } ?>
						<?php if ( apply_filters( 'flxbk_show_admin_bar', true ) ) { ?>
								<h2><?php echo esc_html__( 'Admin Bar', 'flx-blocks' ); ?></h2>
								<div class="fxt-promo-row-area">
								<?php
								echo '<form action="options.php" method="post">';
									settings_fields( 'flxbk_admin_bar_settings' );
									do_settings_sections( 'fxt_blocks_admin_bar_section' );
									submit_button( __( 'Save Changes', 'flx-blocks' ) );
								echo '</form>';
								?>
							</div>
							<div class="fxt-dashboard-spacer"></div>
						<?php } ?>
					</div>
					<div class="side-panel">
						<?php do_action( 'flxbk_dash_side_panel' ); ?>
						<?php do_action( 'flxbk_dash_below_side_panel' ); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get array of Flux Block Editor.
	 */
	public function blocks_array() {
		$blocks = [
			'flx/rowlayout'       => [
				'slug' => 'flx/rowlayout',
				'name' => __( 'Row Layout', 'flx-blocks' ),
				'desc' => __( 'Create rows with nested blocks either in columns or as a container. Give style to your rows with background, overlay, padding, etc.', 'flx-blocks' ),
			],
			'flx/form'            => [
				'slug' => 'flx/form',
				'name' => __( 'Form', 'flx-blocks' ),
				'desc' => __( 'Create a contact form or marketing form and style it within the block editor.', 'flx-blocks' ),
			],
			'flx/advancedgallery' => [
				'slug' => 'flx/advancedgallery',
				'name' => __( 'Advanced Gallery', 'flx-blocks' ),
				'desc' => __( 'Photo galleries, carousels, and sliders! Enable custom links, captions, and more. Plus, you can select image sizes.', 'flx-blocks' ),
			],
			'flx/advancedbtn'     => [
				'slug' => 'flx/advancedbtn',
				'name' => __( 'Advanced Button', 'flx-blocks' ),
				'desc' => __( 'Create an advanced button or a row of buttons. Style each one including hover controls plus you can use an icon and display them side by side', 'flx-blocks' ),
			],
			'flx/lottie'          => [
				'slug'     => 'flx/lottie',
				'name'     => __( 'Lottie Animations', 'flx-blocks' ),
				'desc'     => __( 'Add an extra "wow" factor to your site with engaging Lottie animations.', 'flx-blocks' ),
				'link'     => admin_url( 'edit.php?post_type=flxbk_lottie' ),
				'linkText' => __( 'Manage Lottie Animations', 'flx-blocks' ),
			],
			'flx/vector'          => [
				'slug'     => 'flx/vector',
				'name'     => __( 'Vector Graphics', 'flx-blocks' ),
				'desc'     => __( 'Add custom vector icons and SVGs to enhance your site design.', 'flx-blocks' ),
				'link'     => admin_url( 'edit.php?post_type=flxbk_vector' ),
				'linkText' => __( 'Manage Vector Graphics', 'flx-blocks' ),
			],
			'flx/header'          => [
				'slug'     => 'flx/header',
				'name'     => __( 'Advanced Header', 'flx-blocks' ),
				'desc'     => __( 'Build custom headers in the block editor and full site editing.', 'flx-blocks' ),
				'link'     => admin_url( 'edit.php?post_type=flxbk_header' ),
				'linkText' => __( 'Manage Headers', 'flx-blocks' ),
			],
			'flx/navigation'      => [
				'slug'     => 'flx/navigation',
				'name'     => __( 'Advanced Navigation', 'flx-blocks' ),
				'desc'     => __( 'Build custom navigation menus', 'flx-blocks' ),
				'link'     => admin_url( 'edit.php?post_type=flxbk_navigation' ),
				'linkText' => __( 'Manage Navigation Menus', 'flx-blocks' ),
			],
			'flx/icon'            => [
				'slug' => 'flx/icon',
				'name' => __( 'Icon', 'flx-blocks' ),
				'desc' => __( 'Choose from over 1500+ SVG Icons to add into your page and style the size, colors, background, border, etc.', 'flx-blocks' ),
			],
			'flx/spacer'          => [
				'slug' => 'flx/spacer',
				'name' => __( 'Spacer/Divider', 'flx-blocks' ),
				'desc' => __( 'Easily create a divider and determine the space around it or just create some space in your content.', 'flx-blocks' ),
			],
			'flx/advancedheading' => [
				'slug' => 'flx/advancedheading',
				'name' => __( 'Advanced Text', 'flx-blocks' ),
				'desc' => __( 'Create a heading or paragraph and define sizes for desktop, tablet and mobile along with font family, colors, etc.', 'flx-blocks' ),
			],
			'flx/tabs'            => [
				'slug' => 'flx/tabs',
				'name' => __( 'Tabs', 'flx-blocks' ),
				'desc' => __( 'Create custom vertical or horizontal tabs with advanced styling controls. Each tab content is an empty canvas able to contain any other blocks.', 'flx-blocks' ),
			],
			'flx/infobox'         => [
				'slug' => 'flx/infobox',
				'name' => __( 'Info Box', 'flx-blocks' ),
				'desc' => __( 'Create a box containing an icon or image and, optionally, a title, description, and learn more text. Style static and hover separately.', 'flx-blocks' ),
			],
			'flx/accordion'       => [
				'slug' => 'flx/accordion',
				'name' => __( 'Accordion', 'flx-blocks' ),
				'desc' => __( 'Create beautiful accordions! Each pane can contain any other block, customize title styles, content background, and borders.', 'flx-blocks' ),
			],
			'flx/iconlist'        => [
				'slug' => 'flx/iconlist',
				'name' => __( 'Icon List', 'flx-blocks' ),
				'desc' => __( 'Add beautiful icons to your lists and make them more engaging. Over 1500 icons to choose from and unlimited styles.', 'flx-blocks' ),
			],
			'flx/tableofcontents' => [
				'slug' => 'flx/tableofcontents',
				'name' => __( 'Table of Contents', 'flx-blocks' ),
				'desc' => __( 'Allow your readers to navigate your content easily with a table of contents block. Includes smooth scroll to anchor.', 'flx-blocks' ),
			],
			'flx/posts'           => [
				'slug' => 'flx/posts',
				'name' => __( 'Posts', 'flx-blocks' ),
				'desc' => __( 'Display a clean grid of posts anywhere on your site, great for your home page where you want to tease your blog.', 'flx-blocks' ),
			],
			'flx/countdown'       => [
				'slug' => 'flx/countdown',
				'name' => __( 'Countdown', 'flx-blocks' ),
				'desc' => __( 'Increase your conversions by adding a sense of urgency to your offering. Pro includes evergreen campaigns as well.', 'flx-blocks' ),
			],
			'flx/testimonials'    => [
				'slug' => 'flx/testimonials',
				'name' => __( 'Testimonials', 'flx-blocks' ),
				'desc' => __( 'Create confidence in your brand or product by showing off beautiful unique testimonials, add as a carousel or a grid.', 'flx-blocks' ),
			],
		];

		// for blocks moved from pro to free
		if ( ! defined( 'KBP_VERSION' ) || ( defined( 'KBP_VERSION' ) && version_compare( KBP_VERSION, '2.6.0', '>' ) ) ) {
			$blocks['flx/videopopup'] = [
				'slug'  => 'flx/videopopup',
				'name'  => __( 'Video Popup', 'flx-blocks' ),
				'desc'  => __( 'Beautifully display a thumbnail with overlay and hover effect that links to a video popup on click. Works with local or external videos!', 'flx-blocks' ),
				'image' => FLXBK_URL . 'includes/settings/img/block-video-pop.jpg',
			];
		}

		return apply_filters( 'flxbk_enable_disable_array', $blocks );
	}
	/**
	 * Ajax Save blocks Config
	 */
	public function ajax_blocks_save_config() {
		if ( ! check_ajax_referer( 'flx-blocks-manage', 'wpnonce' ) ) {
			wp_send_json_error( __( 'Invalid nonce', 'flx-blocks' ) );
		}
		if ( ! isset( $_POST['fxt_block'] ) && ! isset( $_POST['config'] ) ) {
			return wp_send_json_error( __( 'Missing Content', 'flx-blocks' ) );
		}
		// Get settings.
		$current_settings = get_option( 'fxt_blocks_config_blocks' );
		$new_settings     = wp_unslash( $_POST['config'] );
		if ( ! is_array( $new_settings ) ) {
			return wp_send_json_error( __( 'Nothing to Save', 'flx-blocks' ) );
		}
		foreach ( $new_settings as $block_key => $settings ) {
			foreach ( $settings as $attribute_key => $value ) {
				if ( is_array( $value ) ) {
					foreach ( $value as $array_attribute_index => $array_value ) {
						if ( is_array( $array_value ) ) {
							foreach ( $array_value as $array_attribute_key => $array_attribute_value ) {
								$array_attribute_value = sanitize_text_field( $array_attribute_value );
								if ( is_numeric( $array_attribute_value ) ) {
									$array_attribute_value = floatval( $array_attribute_value );
								}
								if ( 'true' === $array_attribute_value ) {
									$array_attribute_value = true;
								}
								if ( 'false' === $array_attribute_value ) {
									$array_attribute_value = false;
								}
								$new_settings[ $block_key ][ $attribute_key ][ $array_attribute_index ][ $array_attribute_key ] = $array_attribute_value;
							}
						} else {
							$array_value = sanitize_text_field( $array_value );
							if ( is_numeric( $array_value ) ) {
								$array_value = floatval( $array_value );
							}
							$new_settings[ $block_key ][ $attribute_key ][ $array_attribute_index ] = $array_value;
						}
					}
				} else {
					$value = sanitize_text_field( $value );
					if ( is_numeric( $value ) ) {
						$value = floatval( $value );
					}
					if ( 'true' === $value ) {
						$value = true;
					}
					if ( 'false' === $value ) {
						$value = false;
					}
					$new_settings[ $block_key ][ $attribute_key ] = $value;
				}
			}
		}
		$block = sanitize_text_field( wp_unslash( $_POST['fxt_block'] ) );

		if ( ! is_array( $current_settings ) ) {
			$current_settings = [];
		}
		$current_settings[ $block ] = $new_settings[ $block ];
		update_option( 'fxt_blocks_config_blocks', $current_settings );
		return wp_send_json_success();
	}
	/**
	 * Ajax activate/deactivate blocks
	 */
	public function ajax_blocks_activate_deactivate() {
		if ( ! check_ajax_referer( 'flx-blocks-manage', 'wpnonce' ) ) {
			wp_send_json_error();
		}
		if ( ! isset( $_POST['fxt_block'] ) ) {
			return wp_send_json_error();
		}
		// Get variables.
		$unregistered_blocks = get_option( 'fxt_blocks_unregistered_blocks' );
		$block               = sanitize_text_field( wp_unslash( $_POST['fxt_block'] ) );

		if ( ! is_array( $unregistered_blocks ) ) {
			$unregistered_blocks = [];
		}

		// If current block is in the array - remove it.
		if ( in_array( $block, $unregistered_blocks ) ) {
			$index = array_search( $block, $unregistered_blocks );
			if ( false !== $index ) {
				unset( $unregistered_blocks[ $index ] );
			}
			// if current block is not in the array - add it.
		} else {
			array_push( $unregistered_blocks, $block );
		}

		update_option( 'fxt_blocks_unregistered_blocks', $unregistered_blocks );
		return wp_send_json_success();
	}
	/**
	 * Ajax default posts width
	 */
	public function ajax_default_editor_width() {
		if ( ! check_ajax_referer( 'flx-blocks-manage', 'wpnonce' ) ) {
			wp_send_json_error();
		}
		if ( ! isset( $_POST['post_default'] ) ) {
			return wp_send_json_error();
		}
		// Get variables.
		$editor_widths = get_option( 'fxt_blocks_editor_width' );
		$default       = sanitize_text_field( wp_unslash( $_POST['post_default'] ) );

		if ( ! is_array( $editor_widths ) ) {
			$editor_widths = [];
		}
		$editor_widths['post_default'] = $default;

		update_option( 'fxt_blocks_editor_width', $editor_widths );
		return wp_send_json_success();
	}
	/**
	 * Add settings link
	 */
	public function settings_link() {
		return apply_filters( 'flx-blocks-settings-url', admin_url( 'admin.php?page=flx-blocks' ) );
	}
	/**
	 * Add settings link
	 *
	 * @param array $links plugin activate/deactivate links array.
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . esc_url( $this->settings_link() ) . '">' . __( 'Settings', 'flx-blocks' ) . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}
}
Flx_Blocks_Settings::get_instance();
