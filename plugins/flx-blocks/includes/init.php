<?php
/**
 * Enqueue admin CSS/JS and edit width functions
 *
 * @package Flux Block Editor
 */

// cspell:ignore editorwidth yourtheme iconset wpmlcore getresponse lotte Sashicons .

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FluxWP\Blocks\App;
use FluxWP\Blocks\Core\Container;


/**
 * Setup the post type options for post blocks.
 *
 * @return array
 */
function flxbk_get_post_types( $other_args = array() ) {
	$args = array(
		'public'       => true,
		'show_in_rest' => true,
	);
	if ( ! empty( $other_args ) ) {
		$args = array_merge( $args, $other_args );
	}
	$post_types = get_post_types( $args, 'objects' );
	$output = array();
	foreach ( $post_types as $post_type ) {
		// if ( 'product' == $post_type->name || 'attachment' == $post_type->name ) {
		// 	continue;
		// }
		if ( 'attachment' == $post_type->name ) {
			continue;
		}
		$output[] = array(
			'value' => $post_type->name,
			'label' => $post_type->label,
		);
	}
	return apply_filters( 'flxbk_post_types', $output );
}

/**
 * Add a filter that matches for custom font filter.
 */
function flxbk_convert_custom_fonts() {
	if ( ! is_admin() ) {
		return;
	}
	$convert_fonts = apply_filters( 'flxbk_add_custom_fonts', [] );
	if ( ! empty( $convert_fonts ) && is_array( $convert_fonts ) ) {
		add_filter(
			'flxbk_custom_fonts',
			function( $custom_fonts ) use( $convert_fonts ) {
				foreach ( $convert_fonts as $font_name => $args ) {
					$weights_arg = [];
					if ( is_array( $args ) && isset( $args['weights'] ) && is_array( $args['weights'] ) ) {
						$weights_arg = $args['weights'];
					}
					$font_slug = ( is_array( $args ) && isset( $args['fallback'] ) && ! empty( $args['fallback'] ) ? '"' . $font_name . '", ' . $args['fallback'] : $font_name );

					$font_entry = [
						'name'    => $font_slug,
						'weights' => $weights_arg,
						'styles'  => [],
					];
					if ( is_array( $args ) && isset( $args['label'] ) && ! empty( $args['label'] ) ) {
						$font_entry['label'] = $args['label'];
					}
					$custom_fonts[ $font_slug ] = $font_entry;
				}
				return $custom_fonts;
			},
			10
		);
	}
}
add_filter( 'init', 'flxbk_convert_custom_fonts', 11 );

/**
 * Callback for the excerpt_length filter used by
 * the Latest Posts block at render time.
 *
 * @return int Returns the global $flxbk_post_block_get_excerpt_length variable
 *             to allow the excerpt_length filter respect the Latest Block setting.
 */
function flxbk_post_block_get_excerpt_length() {
	global $flxbk_post_block_get_excerpt_length;
	return $flxbk_post_block_get_excerpt_length;
}
/**
 * Add global styles into the backend editor.
 *
 */
function flxbk_add_global_gutenberg_inline_styles() {
	global $content_width;
	// The fluid font-size scale is owned by the design-tokens baseline; retrieve it so the shipped clamp()
	// values are not duplicated here. Empty when token projection is unavailable, leaving the filter intact.
	$font_sizes = flxbk_variable_font_size_scale();
	$font_sizes = apply_filters( 'flxbk_variable_font_sizes', $font_sizes );
	$css = ':root {';
	foreach ( $font_sizes as $key => $value ) {
		$css .= '--global-fxb-font-size-' . $key . ':' . $value . ';';
	}
	$css .= '}';
	if ( isset( $content_width ) ) {
		if ( class_exists( 'Flux\Theme' ) && flxbk_is_fse_mode() ) {
			// Global Styles sets the width without the edge padding, which the Flux content width includes.
			$global_styles_content_width = 'calc(var(--wp--style--global--content-size) + 2 * var(--global-content-edge-padding))';

			$css .= '.fxb-header-container, .wp-block-flx-navigation-link { --global-content-width:' . $global_styles_content_width . ';}';
			$css .= '.editor-styles-wrapper{ --fxb-global-content-width:' . $global_styles_content_width . ';}';
			$css .= '.wp-block-flx-rowlayout > .fxb-theme-content-width {
				max-width:' . $global_styles_content_width . ';
			}';
		} elseif ( class_exists( 'Flux\Theme' ) ) {
			$css .= '.fxb-header-container, .wp-block-flx-navigation-link { --global-content-width:' . \Flux\flxbk()->sub_option( 'content_width', 'size' ) . \Flux\flxbk()->sub_option( 'content_width', 'unit' ) . ';}';
			$css .= '.editor-styles-wrapper{ --fxb-global-content-width:' . \Flux\flxbk()->sub_option( 'content_width', 'size' ) . \Flux\flxbk()->sub_option( 'content_width', 'unit' ) . ';}';
			$css .= '.wp-block-flx-rowlayout > .fxb-theme-content-width {
				max-width:' . \Flux\flxbk()->sub_option( 'content_width', 'size' ) . \Flux\flxbk()->sub_option( 'content_width', 'unit' ) . ';
			}';
		} else {
			$css .= '.fxb-header-container, .wp-block-flx-navigation-link { --global-content-width: ' . absint( $content_width ) . 'px;}';
			$css .= '.editor-styles-wrapper{ --fxb-global-content-width:' . absint( $content_width ) . 'px;}';
			$css .= '.wp-block-flx-rowlayout > .fxb-theme-content-width {
				max-width:' . esc_attr( $content_width ) . 'px;
			}';
		}
	} else {
		$css .= '.editor-styles-wrapper{ --fxb-global-content-width:var(--wp--style--global--content-size);}';
		$css .= '.wp-block-flx-rowlayout > .fxb-theme-content-width {
			max-width:var(--wp--style--global--content-size);
		}';
		$css .= '.fxb-header-container { --global-content-width: var(--wp--style--global--wide-size, var(--wp--style--global--content-size)) }';
	}
	// The spacing scale is owned by the design-tokens baseline; retrieve it so the shipped literals are not
	// duplicated here. Empty when token projection is unavailable, leaving blocks on their own
	// var(--global-fxb-spacing-*, <rem>) fallbacks.
	$spacing_css = '';
	foreach ( flxbk_variable_spacing_scale() as $spacing_slug => $spacing_value ) {
		$spacing_css .= '--global-fxb-spacing-' . $spacing_slug . ':' . $spacing_value . ';';
	}
	$css .= ':root {' . $spacing_css . '
		--global-row-edge-sm: 15px;
		--global-row-edge-theme: var(--global-content-edge-padding);
		--global-fxb-gutter-sm: 1rem;
		--global-fxb-gutter-md: 2rem;
		--global-fxb-gutter-lg: 3rem;
		--global-fxb-gutter-xl: 5rem;
		--global-fxb-editor-admin-sidebar: 0px;
		--global-fxb-editor-sidebar: 0px;
		--global-fxb-editor-sidebar-secondary: 0px;
		--global-fxb-editor-full-width: calc( 100vw - ( var(--global-fxb-editor-sidebar) +  var(--global-fxb-editor-sidebar-secondary) +  var(--global-fxb-editor-admin-sidebar) ) );
	}';
	// When not using an Iframe we need to add the sidebar width.
	$css .= '.interface-interface-skeleton:has(.interface-interface-skeleton__sidebar:not(:empty)) .interface-interface-skeleton__content {
		--global-fxb-editor-sidebar: 281px;
		--global-fxb-editor-full-width: calc( 100vw - ( var(--global-fxb-editor-sidebar) +  var(--global-fxb-editor-sidebar-secondary) +  var(--global-fxb-editor-admin-sidebar) ) );
	}';
	// When not using an Iframe we need to add the secondary sidebar width.
	$css .= '.interface-interface-skeleton .interface-interface-skeleton__secondary-sidebar ~ .interface-interface-skeleton__content {
		--global-fxb-editor-sidebar-secondary: 351px;
		--global-fxb-editor-full-width: calc( 100vw - ( var(--global-fxb-editor-sidebar) +  var(--global-fxb-editor-sidebar-secondary) +  var(--global-fxb-editor-admin-sidebar) ) );
	}';
	$css .= 'body.block-editor-page:not(.is-fullscreen-mode) .interface-interface-skeleton .interface-interface-skeleton__content {
		--global-fxb-editor-admin-sidebar: 160px;
		--global-fxb-editor-full-width: calc( 100vw - ( var(--global-fxb-editor-sidebar) +  var(--global-fxb-editor-sidebar-secondary) +  var(--global-fxb-editor-admin-sidebar) ) );
	}';
	$css .= ':root .post-content-style-boxed {
		--global-row-edge-theme: calc( var(--global-content-edge-padding) + 2rem);
	}';
	if ( ! class_exists( 'Flux\Theme' ) ) {
		$global_colors = array(
			'--global-palette1' => '#3182CE',
			'--global-palette2' => '#2B6CB0',
			'--global-palette3' => '#1A202C',
			'--global-palette4' => '#2D3748',
			'--global-palette5' => '#4A5568',
			'--global-palette6' => '#718096',
			'--global-palette7' => '#EDF2F7',
			'--global-palette8' => '#F7FAFC',
			'--global-palette9' => '#ffffff',
		);
		$global_colors = apply_filters( 'flxbk_pattern_global_colors', $global_colors );
		$css .= ':root {';
		foreach ( $global_colors as $key => $value ) {
			$css .= esc_attr( $key ) . ': ' . esc_attr( $value ) . ';';
		}
		$css .= '}';
	}
	wp_register_style( 'flx-blocks-global-editor-styles', false );
	wp_add_inline_style( 'flx-blocks-global-editor-styles', $css );
}
add_action( 'admin_init', 'flxbk_add_global_gutenberg_inline_styles', 1 );
/**
 * Connects Block styles to core block style so it loads in full size editing context.
 * This is a workaround so dynamic css can be loaded in Iframe and FSE mode.
 */
function flxbk_update_global_gutenberg_inline_styles_dependencies() {
	$wp_styles = wp_styles();
	$style     = $wp_styles->query( 'wp-block-library', 'registered' );
	if ( ! $style ) {
		return;
	}
	if (
		wp_style_is( 'flx-blocks-global-editor-styles', 'registered' ) &&
		! in_array( 'flx-blocks-global-editor-styles', $style->deps, true )
	) {
		$style->deps[] = 'flx-blocks-global-editor-styles';
	}
}
add_action( 'admin_init', 'flxbk_update_global_gutenberg_inline_styles_dependencies', 2 );
/**
 * Add global styles into the frontend.
 */
function flxbk_add_global_gutenberg_styles_frontend() {
	// The fluid font-size scale is owned by the design-tokens baseline; retrieve it so the shipped clamp()
	// values are not duplicated here. Empty when token projection is unavailable, leaving the filter intact.
	$font_sizes = flxbk_variable_font_size_scale();
	$font_sizes = apply_filters( 'flxbk_variable_font_sizes', $font_sizes );
	$css = ':root {';
	foreach ( $font_sizes as $key => $value ) {
		$css .= '--global-fxb-font-size-' . $key . ':' . $value . ';';
	}
	$css .= '}';
	if ( ! class_exists( 'Flux\Theme' ) ) {
		$global_colors = array(
			'--global-palette1' => '#3182CE',
			'--global-palette2' => '#2B6CB0',
			'--global-palette3' => '#1A202C',
			'--global-palette4' => '#2D3748',
			'--global-palette5' => '#4A5568',
			'--global-palette6' => '#718096',
			'--global-palette7' => '#EDF2F7',
			'--global-palette8' => '#F7FAFC',
			'--global-palette9' => '#ffffff',
		);
		$global_colors = apply_filters( 'flxbk_pattern_global_colors', $global_colors );
		$css .= ':root {';
		foreach ( $global_colors as $key => $value ) {
			$css .= esc_attr( $key ) . ': ' . esc_attr( $value ) . ';';
		}
		$css .= '}';
	}

	// This is a temp fix for restored316 upgrade issue.
	if ( get_option( 'stylesheet' ) === 'restored316-journey' ) {
		$css .= '.fxt-blocks-carousel-init[data-slider-dots=true].is-overflow {';
		$css .= 'margin-bottom:0px';
		$css .= '}';
	}
	wp_register_style( 'flx-blocks-global-variables', false );
	wp_enqueue_style( 'flx-blocks-global-variables' );
	wp_add_inline_style( 'flx-blocks-global-variables', $css );
}
add_action( 'wp_enqueue_scripts', 'flxbk_add_global_gutenberg_styles_frontend', 90 );


/**
 * Add class to match editor width.
 *
 * @param string $classes string of body classes.
 */
function flxbk_admin_body_class( $classes ) {
	$screen = get_current_screen();
	if ( 'post' == $screen->base ) {
		global $post;
		$editorwidth = get_post_meta( $post->ID, 'fxt_blocks_editor_width', true );
		if ( isset( $editorwidth ) && ! empty( $editorwidth ) && 'default' !== $editorwidth ) {
			$classes .= ' fxt-editor-width-' . esc_attr( $editorwidth );
		} else {
			$classes .= ' fxt-editor-width-default';
		}
	}
	return $classes;
}
add_filter( 'admin_body_class', 'flxbk_admin_body_class' );

/**
 * Add block category for Flux Block Editor.
 *
 * @param array  $categories the array of block categories.
 * @param object $post the post object.
 */
function flxbk_block_category( $categories, $post ) {
	return array_merge(
		array(
			array(
				'slug'  => 'flx-blocks',
				'title' => __( 'Flux Block Editor', 'flx-blocks' ),
			),
		),
		$categories
	);
}

/**
 * Add old categories filter if needed.
 */
function flxbk_check_for_old_wp_block_category_filter() {
	if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
		add_filter( 'block_categories', 'flxbk_block_category', 10, 2 );
	}
}
add_action( 'init', 'flxbk_check_for_old_wp_block_category_filter' );
/**
 * Add block category for Flux Block Editor.
 *
 * @param array  $categories the array of block categories.
 * @param WP_Block_Editor_Context $block_editor_context The current block editor context.
 */
function flxbk_block_category_all( $categories, $block_editor_context ) {
	return array_merge(
		array(
			array(
				'slug'  => 'flx-blocks',
				'title' => __( 'Flux Block Editor', 'flx-blocks' ),
			),
		),
		$categories
	);
}
add_filter( 'block_categories_all', 'flxbk_block_category_all', 10, 2 );

/**
 * Get other templates (e.g. product attributes) passing attributes and including the file.
 *
 * @param string $template_name Template name.
 * @param array  $args          Arguments. (default: array).
 * @param string $template_path Template path. (default: '').
 * @param string $default_path  Default path. (default: '').
 */
function flxbk_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
	$cache_key = sanitize_key( implode( '-', array( 'template', $template_name, $template_path, $default_path, FLXBK_VERSION ) ) );
	$template  = (string) wp_cache_get( $cache_key, 'flx-blocks' );

	if ( ! $template ) {
		$template = flxbk_locate_template( $template_name, $template_path, $default_path );
		wp_cache_set( $cache_key, $template, 'flx-blocks' );
	}

	// Allow 3rd party plugin filter template file from their plugin.
	$filter_template = apply_filters( 'flxbk_get_template', $template, $template_name, $args, $template_path, $default_path );

	if ( $filter_template !== $template ) {
		if ( ! file_exists( $filter_template ) ) {
			return;
		}
		$template = $filter_template;
	}

	$action_args = array(
		'template_name' => $template_name,
		'template_path' => $template_path,
		'located'       => $template,
		'args'          => $args,
	);

	if ( ! empty( $args ) && is_array( $args ) ) {
		if ( isset( $args['action_args'] ) ) {
			unset( $args['action_args'] );
		}
		extract( $args ); // @codingStandardsIgnoreLine
	}

	do_action( 'flxbk_before_template_part', $action_args['template_name'], $action_args['template_path'], $action_args['located'], $action_args['args'] );

	include $action_args['located'];

	do_action( 'flxbk_after_template_part', $action_args['template_name'], $action_args['template_path'], $action_args['located'], $action_args['args'] );
}
/**
 * Like flxbk_get_template, but returns the HTML instead of outputting.
 *
 * @see flxbk_get_template
 * @param string $template_name Template name.
 * @param array  $args          Arguments. (default: array).
 * @param string $template_path Template path. (default: '').
 * @param string $default_path  Default path. (default: '').
 *
 * @return string
 */
function flxbk_get_template_html( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
	ob_start();
	flxbk_get_template( $template_name, $args, $template_path, $default_path );
	return ob_get_clean();
}
/**
 * Locate a template and return the path for inclusion.
 *
 * This is the load order:
 *
 * yourtheme/$template_path/$template_name
 * yourtheme/$template_name
 * $default_path/$template_name
 *
 * @param string $template_name Template name.
 * @param string $template_path Template path. (default: '').
 * @param string $default_path  Default path. (default: '').
 * @return string
 */
function flxbk_locate_template( $template_name, $template_path = '', $default_path = '' ) {
	if ( ! $template_path ) {
		$template_path = apply_filters( 'flxbk_template_path', 'flx-blocks/' );
	}

	if ( ! $default_path ) {
		$default_path = FLXBK_PATH . 'includes/templates/';
	}

	// Look within passed path within the theme - this is priority.
	$template = locate_template(
		array(
			trailingslashit( $template_path ) . $template_name,
			$template_name,
		)
	);
	// Check depreciated path template.
	if ( ! $template ) {
		$template_path = 'flxblocks/';
		$template = locate_template(
			array(
				trailingslashit( $template_path ) . $template_name,
				$template_name,
			)
		);
	}
	// Get default template/.
	if ( ! $template ) {
		$template = $default_path . $template_name;
	}

	// Return what we found.
	return apply_filters( 'flxbk_locate_template', $template, $template_name, $template_path );
}
/**
 * Print an SVG Icon
 *
 * @param string $icon the icon name.
 * @param string $icon_title the icon title for screen readers.
 * @param bool   $base if the baseline class should be added.
 */
function flxbk_print_icon( $icon = 'arrow-right-alt', $icon_title = '', $base = true ) {
	echo flxbk_get_icon( $icon, $icon_title, $base ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
/**
 * Get an SVG Icon
 *
 * @param string $icon the icon name.
 * @param string $icon_title the icon title for screen readers.
 * @param bool   $base if the baseline class should be added.
 */
function flxbk_get_icon( $icon = 'arrow-right-alt', $icon_title = '', $base = true, $aria = false ) {
	$display_title = apply_filters( 'flxbk_svg_icons_have_title', true );
	$output = '<span class="flxbk-svg-iconset' . ( $base ? ' svg-baseline' : '' ) . '">';
	switch ( $icon ) {
		case 'arrow-right-alt':
			$output .= '<svg' . ( ! $aria ? ' aria-hidden="true"' : '' ) . ' class="flxbk-svg-icon flxbk-arrow-right-alt-svg" fill="currentColor" version="1.1" xmlns="http://www.w3.org/2000/svg" width="27" height="28" viewBox="0 0 27 28">';
			if ( $display_title ) {
				$output .= '<title>' . ( ! empty( $icon_title ) ? $icon_title : esc_html__( 'Continue', 'flx-blocks' ) ) . '</title>';
			}
			$output .= '<path d="M27 13.953c0 0.141-0.063 0.281-0.156 0.375l-6 5.531c-0.156 0.141-0.359 0.172-0.547 0.094-0.172-0.078-0.297-0.25-0.297-0.453v-3.5h-19.5c-0.281 0-0.5-0.219-0.5-0.5v-3c0-0.281 0.219-0.5 0.5-0.5h19.5v-3.5c0-0.203 0.109-0.375 0.297-0.453s0.391-0.047 0.547 0.078l6 5.469c0.094 0.094 0.156 0.219 0.156 0.359v0z"></path>
			</svg>';
			break;
		default:
			$output .= '';
			break;
	}
	$output .= '</span>';

	$output = apply_filters( 'flxbk_svg_icon', $output, $icon, $icon_title, $base );

	return $output;
}
/**
 * Convert the post ID in Flux Form's block.
 *
 * @see https://onthegosystems.myjetbrains.com/youtrack/issue/wpmlcore-7207
 *
 * @param array $block the filtered block.
 */
function wpmlcore_7207_fix_flxbk_form_block( array $block ) {
	if ( 'flx/form' === $block['blockName'] && class_exists( 'WPML\PB\Gutenberg\ConvertIdsInBlock\Composite' ) && class_exists( 'WPML\PB\Gutenberg\ConvertIdsInBlock\TagAttributes' ) && class_exists( 'WPML\PB\Gutenberg\ConvertIdsInBlock\BlockAttributes' ) ) {
		$slug      = get_post_type();
		$converter = new WPML\PB\Gutenberg\ConvertIdsInBlock\Composite(
			array(
				new WPML\PB\Gutenberg\ConvertIdsInBlock\TagAttributes(
					array(
						array(
							'xpath' => '//*[@name="_fxb_form_post_id"]/@value',
							'slug' => $slug,
							'type' => 'post',
						),
					)
				),
				new WPML\PB\Gutenberg\ConvertIdsInBlock\BlockAttributes(
					array(
						array(
							'name' => 'postID',
							'slug' => $slug,
							'type' => 'post',
						),
					)
				),
			)
		);
		return $converter->convert( $block );
	}
	return $block;
}
add_filter( 'render_block_data', 'wpmlcore_7207_fix_flxbk_form_block' );

/**
 * Setup the post select API endpoint.
 *
 * @return void
 */
function flxbk_register_api_endpoints() {
	$mailerlite_controller = new Flxbk_MailerLite_REST_Controller();
	$mailerlite_controller->register_routes();
	$getresponse_controller = new Flxbk_GetResponse_REST_Controller();
	$getresponse_controller->register_routes();
	$fluentcrm_controller = new Flxbk_FluentCRM_REST_Controller();
	$fluentcrm_controller->register_routes();
	$posts_controller = new Flx_Blocks_Post_Rest_Controller();
	$posts_controller->register_routes();
	$lottieanimation_controller_get = new Flxbk_LottieAnimation_get_REST_Controller();
	$lottieanimation_controller_get->register_routes();
	$lottieanimation_controller_upload = new Flxbk_LottieAnimation_post_REST_Controller();
	$lottieanimation_controller_upload->register_routes();
	$vector_controller_upload = new Flxbk_Vector_post_REST_Controller();
	$vector_controller_upload->register_routes();
}
add_action( 'rest_api_init', 'flxbk_register_api_endpoints' );

/**
 * Register the lotte post type.
 */
function flxbk_register_lottie_custom_post_type() {
	register_post_type(
		'flxbk_lottie',
		array(
			'label'        => _x( 'Lottie Animations', 'Lottie animation', 'flx-blocks' ),
			'description'  => __( 'Lottie Animations imported in Flux', 'flx-blocks' ),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => false,
			'show_in_rest' => true,
			'rewrite'      => false,
			'capabilities' => array(
				'read'                   => 'edit_theme_options',
				'create_posts'           => 'edit_theme_options',
				'edit_posts'             => 'edit_theme_options',
				'edit_published_posts'   => 'edit_theme_options',
				'delete_published_posts' => 'edit_theme_options',
				'edit_others_posts'      => 'edit_theme_options',
				'delete_others_posts'    => 'edit_theme_options',
			),
			'map_meta_cap' => true,
			'supports'     => array(
				'title',
				'editor',
				'revisions',
			),
		)
	);
}

add_action( 'init', 'flxbk_register_lottie_custom_post_type' );

/**
 * Register the vector post type.
 */
function flxbk_register_vector_custom_post_type() {
	register_post_type(
		'flxbk_vector',
		array(
			'label'        => _x( 'Vector SVGs', 'post type for flxbk vector svg block', 'flx-blocks' ),
			'description'  => __( 'Vector SVGs imported in Flux', 'flx-blocks' ),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => false,
			'show_in_rest' => true,
			'rewrite'      => false,
			'capabilities' => array(
				'read'                   => 'edit_theme_options',
				'create_posts'           => 'edit_theme_options',
				'edit_posts'             => 'edit_theme_options',
				'edit_published_posts'   => 'edit_theme_options',
				'delete_published_posts' => 'edit_theme_options',
				'edit_others_posts'      => 'edit_theme_options',
				'delete_others_posts'    => 'edit_theme_options',
			),
			'map_meta_cap' => true,
			'supports'     => array(
				'title',
				'editor',
				'revisions',
			),
		)
	);
}

add_action( 'init', 'flxbk_register_vector_custom_post_type' );

/* Sashicons are not enqueue by default when iFraming in block editor
   https://github.com/WordPress/gutenberg/issues/53528
*/
add_action('enqueue_block_assets', function (): void {
	if( is_admin() ) {
		wp_enqueue_style( 'dashicons' );
	}
});


/**
 * Filter core to remove loading = lazy if class is present.
 */
function flxbk_skip_lazy_load( $value, $image, $context ) {
	if ( 'the_content' === $context ) {
		if ( false !== strpos( $image, 'fxb-skip-lazy' ) ) {
			return false; // Set to false so lazy loading attribute is omitted.
		}
	}
	return $value;
}
add_filter( 'wp_img_tag_add_loading_attr', 'flxbk_skip_lazy_load', 10, 3 );


/**
 * Filter to remove block rendering when events builds their custom excerpts.
 *
 * @param bool   $enabled Whether build css or not.
 * @param string $name The block name.
 * @param string $unique_id The block unique id.
 */
function flxbk_events_custom_excerpt_stop_style_output( $enabled, $name, $unique_id ) {
	return false;
}
/**
 * Filter to remove block rendering when events builds their custom excerpts.
 *
 * @param bool $remove_blocks Whether to remove blocks or not.
 * @param WP_Post $post The post object.
 */
function flxbk_events_custom_excerpt_fix( $remove_blocks, $post ) {
	if ( $remove_blocks && ! is_singular() ) {
		add_filter( 'flxbk_render_inline_css', 'flxbk_events_custom_excerpt_stop_style_output', 10, 3 );
	}
	return $remove_blocks;
}

add_filter( 'tribe_events_excerpt_blocks_removal', 'flxbk_events_custom_excerpt_fix', 99, 2 );

/**
 * Remove Filter to remove block rendering when events builds their custom excerpts.
 *
 * @param bool $remove_blocks Whether to remove blocks or not.
 * @param WP_Post $post The post object.
 */
function flxbk_events_custom_excerpt_remove_fix( $html, $post ) {
	if ( ! is_singular() ) {
		remove_filter( 'flxbk_render_inline_css', 'flxbk_events_custom_excerpt_stop_style_output', 10, 3 );
	}
	return $html;
}
add_filter( 'tribe_events_get_the_excerpt', 'flxbk_events_custom_excerpt_remove_fix', 10, 2 );
/**
 * The Flux Block Editor Application Container.
 *
 * @see flxbk_init()
 *
 * @note flxbk_init() must be called before this one.
 *
 * @return Container
 * @throws InvalidArgumentException
 */
function flxbk(): Container {
	return App::instance()->container();
}
