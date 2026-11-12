<?php

/**
 * Class managing the header CPT registration.
 */
class Flx_Blocks_Header_CPT_Controller {

	/**
	 * Instance Control
	 *
	 * @var null
	 */
	private static $instance = null;

	/**
	 * Post type.
	 *
	 * @var string
	 */
	private $post_type = 'flxbk_header';

	/**
	 * Instance Control.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor function.
	 */
	public function __construct() {
		// Register the post type.
		add_action( 'init', array( $this, 'register_post_type' ), 2 );
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_filter( 'user_has_cap', array( $this, 'filter_post_type_user_caps' ) );

		// Define the form post gutenberg template.
		add_action( 'init', array( $this, 'form_gutenberg_template' ) );
		add_filter( 'flxbk_post_layout', array( $this, 'header_single_layout' ), 99 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'script_enqueue' ) );
		add_action( 'enqueue_block_assets', array( $this, 'title_styles_enqueue' ) );

		// Add a post display state for the current header (if choosen in theme).
		add_filter( 'display_post_states', array( $this, 'add_display_post_states' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $this, 'top_headers_admin_bar' ), 100 );
		add_action( 'admin_bar_menu', array( $this, 'add_new_header_to_admin_bar' ), 100 );

		if( is_admin() && class_exists( 'Flx_Blocks_Duplicate_Post' ) ) {
			new Flx_Blocks_Duplicate_Post( $this->post_type );
		}
		if( is_admin() && class_exists( 'Flx_Blocks_Cpt_Import_Export' ) ) {
			new Flx_Blocks_Cpt_Import_Export( $this->post_type );
		}
	}
	/**
	 * Enqueue Script for Meta options
	 */
	public function script_enqueue() {
		wp_enqueue_script(
			'fxb-header-visual-builder',
			FLXBK_URL . 'build/header-visual-builder.js',
			array('flxbk-components', 'flxbk-helpers', 'flxbk-icons', 'lodash', 'react', 'react-dom', 'wp-api-fetch', 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-core-data', 'wp-data', 'wp-element', 'wp-i18n', 'wp-primitives', 'wp-url'),
			FLXBK_VERSION
		);
	}
	/**
	 * Enqueue Title Styles
	 */
	public function title_styles_enqueue() {

		$post_type = get_post_type();
		if ( $this->post_type !== $post_type ) {
			return;
		}
		$output = '.post-type-flxbk_header.block-editor-page .editor-styles-wrapper .editor-post-title__block .editor-post-title__input, .post-type-flxbk_header .edit-post-visual-editor__post-title-wrapper {
	font-size: 1.5em;
    line-height: 1;
    padding-left: 0.5em;
    padding-right: 0.5em;
    border: 1px solid var(--wp-admin-theme-color);
	margin-top: 0 !important;
	padding-top: 24px;
    padding-bottom: 20px;
    margin-bottom: 20px;
	font-size: 1em;
	position: relative;
}
.post-type-flxbk_header .editor-styles-wrapper .edit-post-visual-editor__post-title-wrapper:not(.specificity)  {
	padding-top: 24px !important;
    padding-bottom: 20px !important;
    margin-bottom: 20px !important;
    margin-top: 0 !important;
	font-size: 1em;
	position: relative;
}
.post-type-flxbk_header .editor-styles-wrapper .edit-post-visual-editor__post-title-wrapper .editor-post-title:before, .post-type-flxbk_header .edit-post-visual-editor__post-title-wrapper:before {
    content: "Title";
    position: absolute;
    top: 0px;
    left: 0;
    font-size: 12px;
    font-weight: normal;
    line-height: 1;
    background: var(--wp-admin-theme-color);
    padding: 4px 6px;
    color: white;
    text-transform: uppercase;
}
	/* Iframe CSS */
.post-type-flxbk_header .edit-post-visual-editor__post-title-wrapper .editor-post-title {
	font-size: 1.2em;
    font-weight: 500;
    line-height: 1;
	margin: 0;
}
.post-type-flxbk_header .editor-styles-wrapper .edit-post-visual-editor__post-title-wrapper .editor-post-title {
	font-size: 1.2em;

    font-weight: 500;
    line-height: 1;
}
.post-type-flxbk_header .editor-styles-wrapper {
	padding:8px;
	margin: 0;
}
	.post-type-flxbk_header .is-root-container {
		padding: 0 !important;
	}
.post-type-flxbk_header .is-root-container > .wp-block {max-width: none;} .post-type-flxbk_header .is-root-container > .wp-block.wp-block-flx-header.wp-block-flx-header.wp-block-flx-header.wp-block-flx-header.wp-block-flx-header.wp-block-flx-header:not(.specificity) {
    max-width: none !important;
    margin-left: unset !important;
    margin-right: unset !important;
}.post-type-flxbk_header .editor-styles-wrapper .is-root-container > .wp-block {max-width: none;}
.post-type-flxbk_header .editor-styles-wrapper .has-global-padding {
	padding: 0;
}
:where(.post-type-flxbk_header) :where(.wp-block) {max-width: none;} :where(.post-type-flxbk_header) :where(.editor-styles-wrapper) :where(.wp-block) {max-width: none;}';
		wp_register_style( 'flxbk_header_css', false );
		wp_enqueue_style( 'flxbk_header_css' );
		wp_add_inline_style( 'flxbk_header_css', $output );
	}
	/**
	 * Renders the header single template on the front end.
	 *
	 * @param array $layout the layout array.
	 */
	public function header_single_layout( $layout ) {
		global $post;
		if ( is_singular( $this->post_type ) || ( is_admin() && is_object( $post ) && $this->post_type === $post->post_type ) ) {
			$layout = wp_parse_args(
				array(
					'layout'           => 'fullwidth',
					'boxed'            => 'unboxed',
					'feature'          => 'hide',
					'feature_position' => 'above',
					'comments'         => 'hide',
					'navigation'       => 'hide',
					'title'            => 'hide',
					'transparent'      => 'disable',
					'sidebar'          => 'disable',
					'vpadding'         => 'hide',
					'footer'           => 'disable',
					'header'           => 'disable',
					'content'          => 'enable',
				),
				$layout
			);
		}

		return $layout;
	}

	/**
	 * Add filters for element content output.
	 */
	public function form_gutenberg_template() {
		$post_type_object = get_post_type_object( $this->post_type );
		$post_type_object->template = array(
			array(
				'flx/header',
			),
		);
		$post_type_object->template_lock = 'all';
	}

	/**
	 * Registers the header post type.
	 */
	public function register_post_type() {

		register_post_type(
			$this->post_type,
			array(
				'labels'                => array(
					'name'                  => _x( 'Flux Header', 'post type general name', 'flx-blocks' ),
					'singular_name'         => _x( 'Flux Header', 'post type singular name', 'flx-blocks' ),
					'add_new'               => __( 'Add New Flux Header', 'flx-blocks' ),
					'add_new_item'          => __( 'Add New Flux Header', 'flx-blocks' ),
					'new_item'              => __( 'New Flux Header', 'flx-blocks' ),
					'edit_item'             => __( 'Edit Flux Header', 'flx-blocks' ),
					'view_item'             => __( 'View Flux Header', 'flx-blocks' ),
					'all_items'             => __( 'Headers', 'flx-blocks' ),
					'search_items'          => __( 'Search Flux Headers', 'flx-blocks' ),
					'menu_name'             => _x( 'Headers', 'admin menu', 'flx-blocks' ),
					'parent_item_colon'     => __( 'Parent Flux Header:', 'flx-blocks' ),
					'not_found'             => __( 'No Flux Header found.', 'flx-blocks' ),
					'not_found_in_trash'    => __( 'No Flux Header found in Trash.', 'flx-blocks' ),
					'archives'              => __( 'Flux Header archives', 'flx-blocks' ),
					'insert_into_item'      => __( 'Insert into Flux Header', 'flx-blocks' ),
					'uploaded_to_this_item' => __( 'Uploaded to this Flux Header', 'flx-blocks' ),
					'filter_items_list'     => __( 'Filter Flux Header list', 'flx-blocks' ),
					'items_list_navigation' => __( 'Flux Headers list navigation', 'flx-blocks' ),
					'items_list'            => __( 'Flux Headers list', 'flx-blocks' ),
				),
				'description'           => __( 'Flux Headers that can be inserted into your site.', 'flx-blocks' ),
				'public'                => false,
				'has_archive'           => false,
				'show_ui'               => true,
				'show_in_menu'          => 'flx-blocks',
				'show_in_admin_bar'     => false,
				'show_in_rest'          => true,
				'rewrite'               => false,
				'map_meta_cap'          => true,
				'capabilities'          => array(
					'edit_others_posts'      => 'edit_theme_options',
					'delete_posts'           => 'edit_theme_options',
					'publish_posts'          => 'edit_theme_options',
					'create_posts'           => 'edit_theme_options',
					'read_private_posts'     => 'edit_theme_options',
					'delete_private_posts'   => 'edit_theme_options',
					'delete_published_posts' => 'edit_theme_options',
					'delete_others_posts'    => 'edit_theme_options',
					'edit_private_posts'     => 'edit_theme_options',
					'edit_published_posts'   => 'edit_theme_options',
					'edit_posts'             => 'edit_theme_options',
				),
				'rest_base'             => $this->post_type,
				'rest_controller_class' => Flx_Blocks_Header_CPT_Rest_Controller::class,
				'supports'              => array(
					'title',
					'editor',
					'revisions',
					'custom-fields'
				),
			)
		);
	}

	/**
	 * Check that user can edit these.
	 */
	public function meta_auth_callback() {
		return current_user_can( 'edit_flxbk_header' );
	}

	/**
	 * Filters the capabilities of a user to conditionally grant them capabilities for managing headers.
	 *
	 * Any user who can 'edit_others_pages' will have access to manage headers.
	 *
	 * @param array $allcaps A user's capabilities.
	 *
	 * @return array Filtered $allcaps.
	 */
	public function filter_post_type_user_caps( $allcaps ) {
		if ( isset( $allcaps['edit_others_pages'] ) ) {
			$allcaps['edit_flxbk_header']             = $allcaps['edit_others_pages'];
			$allcaps['edit_others_flxbk_header']      = $allcaps['edit_others_pages'];
			$allcaps['edit_published_flxbk_header']   = $allcaps['edit_others_pages'];
			$allcaps['edit_private_flxbk_header']     = $allcaps['edit_others_pages'];
			$allcaps['delete_flxbk_header']           = $allcaps['edit_others_pages'];
			$allcaps['delete_others_flxbk_header']    = $allcaps['edit_others_pages'];
			$allcaps['delete_published_flxbk_header'] = $allcaps['edit_others_pages'];
			$allcaps['delete_private_flxbk_header']   = $allcaps['edit_others_pages'];
			$allcaps['publish_flxbk_header']          = $allcaps['edit_others_pages'];
			$allcaps['read_private_flxbk_header']     = $allcaps['edit_others_pages'];
		}

		return $allcaps;
	}

	public function register_meta() {
		$register_meta = array(
			array(
				'key'     => '_flxbk_header_description',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key' => '_flxbk_header_typography',
				'type'          => 'object',
				'default'       => array(
					'color'         => '',
					'size'          => array( '', '', '' ),
					'sizeType'      => 'px',
					'lineHeight'    => array( '', '', '' ),
					'lineType'      => '',
					'letterSpacing' => array( '', '', '' ),
					'letterType'    => 'px',
					'textTransform' => '',
					'family'        => '',
					'google'        => false,
					'style'         => '',
					'weight'        => '',
					'variant'       => '',
					'subset'        => '',
					'loadGoogle'    => true,
				),
				'properties' => array(
					'color'         => array( 'type' => 'string' ),
					'size'          => array( 'type' => 'array' ),
					'sizeType'      => array( 'type' => 'string' ),
					'lineHeight'    => array( 'type' => 'array' ),
					'lineType'      => array( 'type' => 'string' ),
					'letterSpacing' => array( 'type' => 'array' ),
					'letterType'    => array( 'type' => 'string' ),
					'textTransform' => array( 'type' => 'string' ),
					'family'        => array( 'type' => 'string' ),
					'google'        => array( 'type' => 'boolean' ),
					'style'         => array( 'type' => 'string' ),
					'weight'        => array( 'type' => 'string' ),
					'variant'       => array( 'type' => 'string' ),
					'subset'        => array( 'type' => 'string' ),
					'loadGoogle'    => array( 'type' => 'boolean' ),
				),
			),
			array(
				'key' => '_flxbk_header_border',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top'    => array( '', 'solid', '' ),
						'right'  => array( '', 'solid', '' ),
						'bottom' => array( '', 'solid', '' ),
						'left'   => array( '', 'solid', '' ),
						'unit'   => 'px',
					),
				),
				'properties' => array(
					'top'    => array( 'type' => 'array' ),
					'right'  => array( 'type' => 'array' ),
					'bottom' => array( 'type' => 'array' ),
					'left'   => array( 'type' => 'array' ),
					'unit'   => array( 'type' => 'string' ),
				),
			),
			array(
				'key' => '_flxbk_header_borderTablet',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top'    => array( '', 'solid', '' ),
						'right'  => array( '', 'solid', '' ),
						'bottom' => array( '', 'solid', '' ),
						'left'   => array( '', 'solid', '' ),
						'unit'   => 'px',
					),
				),
				'properties' => array(
					'top'    => array( 'type' => 'array' ),
					'right'  => array( 'type' => 'array' ),
					'bottom' => array( 'type' => 'array' ),
					'left'   => array( 'type' => 'array' ),
					'unit'   => array( 'type' => 'string' ),
				),
			),
			array(
				'key' => '_flxbk_header_borderMobile',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top'    => array( '', 'solid', '' ),
						'right'  => array( '', 'solid', '' ),
						'bottom' => array( '', 'solid', '' ),
						'left'   => array( '', 'solid', '' ),
						'unit'   => 'px',
					),
				),
				'properties' => array(
					'top'    => array( 'type' => 'array' ),
					'right'  => array( 'type' => 'array' ),
					'bottom' => array( 'type' => 'array' ),
					'left'   => array( 'type' => 'array' ),
					'unit'   => array( 'type' => 'string' ),
				),
			),
			array(
				'key'           => '_flxbk_header_borderRadius',
				'default'       => array( 0, 0, 0, 0 ),
				'type'          => 'array',
				'children_type' => 'integer',
			),
			array(
				'key'           => '_flxbk_header_borderRadiusTablet',
				'default'       => array( 0, 0, 0, 0 ),
				'type'          => 'array',
				'children_type' => 'integer',
			),
			array(
				'key'           => '_flxbk_header_borderRadiusMobile',
				'default'       => array( 0, 0, 0, 0 ),
				'type'          => 'array',
				'children_type' => 'integer',
			),
			array(
				'key'     => '_flxbk_header_borderRadiusUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key' => '_flxbk_header_borderTransparent',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top'    => array( '', 'solid', '' ),
						'right'  => array( '', 'solid', '' ),
						'bottom' => array( '', 'solid', '' ),
						'left'   => array( '', 'solid', '' ),
						'unit'   => 'px',
					),
				),
				'properties' => array(
					'top'    => array( 'type' => 'array' ),
					'right'  => array( 'type' => 'array' ),
					'bottom' => array( 'type' => 'array' ),
					'left'   => array( 'type' => 'array' ),
					'unit'   => array( 'type' => 'string' ),
				),
			),
			array(
				'key' => '_flxbk_header_borderTransparentTablet',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top'    => array( '', 'solid', '' ),
						'right'  => array( '', 'solid', '' ),
						'bottom' => array( '', 'solid', '' ),
						'left'   => array( '', 'solid', '' ),
						'unit'   => 'px',
					),
				),
				'properties' => array(
					'top'    => array( 'type' => 'array' ),
					'right'  => array( 'type' => 'array' ),
					'bottom' => array( 'type' => 'array' ),
					'left'   => array( 'type' => 'array' ),
					'unit'   => array( 'type' => 'string' ),
				),
			),
			array(
				'key' => '_flxbk_header_borderTransparentMobile',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top'    => array( '', 'solid', '' ),
						'right'  => array( '', 'solid', '' ),
						'bottom' => array( '', 'solid', '' ),
						'left'   => array( '', 'solid', '' ),
						'unit'   => 'px',
					),
				),
				'properties' => array(
					'top'    => array( 'type' => 'array' ),
					'right'  => array( 'type' => 'array' ),
					'bottom' => array( 'type' => 'array' ),
					'left'   => array( 'type' => 'array' ),
					'unit'   => array( 'type' => 'string' ),
				),
			),
			array(
				'key'           => '_flxbk_header_borderRadiusTransparent',
				'default'       => array( 0, 0, 0, 0 ),
				'type'          => 'array',
				'children_type' => 'integer',
			),
			array(
				'key'           => '_flxbk_header_borderRadiusTransparentTablet',
				'default'       => array( 0, 0, 0, 0 ),
				'type'          => 'array',
				'children_type' => 'integer',
			),
			array(
				'key'           => '_flxbk_header_borderRadiusTransparentMobile',
				'default'       => array( 0, 0, 0, 0 ),
				'type'          => 'array',
				'children_type' => 'integer',
			),
			array(
				'key'     => '_flxbk_header_borderRadiusTransparentUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key' => '_flxbk_header_borderSticky',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top'    => array( '', 'solid', '' ),
						'right'  => array( '', 'solid', '' ),
						'bottom' => array( '', 'solid', '' ),
						'left'   => array( '', 'solid', '' ),
						'unit'   => 'px',
					),
				),
				'properties' => array(
					'top'    => array( 'type' => 'array' ),
					'right'  => array( 'type' => 'array' ),
					'bottom' => array( 'type' => 'array' ),
					'left'   => array( 'type' => 'array' ),
					'unit'   => array( 'type' => 'string' ),
				),
			),
			array(
				'key' => '_flxbk_header_borderStickyTablet',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top'    => array( '', 'solid', '' ),
						'right'  => array( '', 'solid', '' ),
						'bottom' => array( '', 'solid', '' ),
						'left'   => array( '', 'solid', '' ),
						'unit'   => 'px',
					),
				),
				'properties' => array(
					'top'    => array( 'type' => 'array' ),
					'right'  => array( 'type' => 'array' ),
					'bottom' => array( 'type' => 'array' ),
					'left'   => array( 'type' => 'array' ),
					'unit'   => array( 'type' => 'string' ),
				),
			),
			array(
				'key' => '_flxbk_header_borderStickyMobile',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top'    => array( '', 'solid', '' ),
						'right'  => array( '', 'solid', '' ),
						'bottom' => array( '', 'solid', '' ),
						'left'   => array( '', 'solid', '' ),
						'unit'   => 'px',
					),
				),
				'properties' => array(
					'top'    => array( 'type' => 'array' ),
					'right'  => array( 'type' => 'array' ),
					'bottom' => array( 'type' => 'array' ),
					'left'   => array( 'type' => 'array' ),
					'unit'   => array( 'type' => 'string' ),
				),
			),
			array(
				'key'           => '_flxbk_header_borderRadiusSticky',
				'default'       => array( 0, 0, 0, 0 ),
				'type'          => 'array',
				'children_type' => 'integer',
			),
			array(
				'key'           => '_flxbk_header_borderRadiusStickyTablet',
				'default'       => array( 0, 0, 0, 0 ),
				'type'          => 'array',
				'children_type' => 'integer',
			),
			array(
				'key'           => '_flxbk_header_borderRadiusStickyMobile',
				'default'       => array( 0, 0, 0, 0 ),
				'type'          => 'array',
				'children_type' => 'integer',
			),
			array(
				'key'     => '_flxbk_header_borderRadiusStickyUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key' => '_flxbk_header_background',
				'type'          => 'object',
				'default'       => array(
					'color'      => '',
					'image'      => '',
					'imageID'    => '',
					'position'   => 'center center',
					'size'       => 'cover',
					'repeat'     => 'no-repeat',
					'attachment' => 'scroll',
					'type'       => 'normal',
					'gradient'   => '',
				),
				'properties' => array(
					'color'      => array( 'type' => 'string' ),
					'image'      => array( 'type' => 'string' ),
					'imageID'    => array( 'type' => 'string' ),
					'position'   => array( 'type' => 'string' ),
					'size'       => array( 'type' => 'string' ),
					'repeat'     => array( 'type' => 'string' ),
					'attachment' => array( 'type' => 'string' ),
					'type'       => array( 'type' => 'string' ),
					'gradient'   => array( 'type' => 'string' ),
				),
			),
			array(
				'key' => '_flxbk_header_backgroundTransparent',
				'type'          => 'object',
				'default'       => array(
					'color'      => '',
					'image'      => '',
					'imageID'    => '',
					'position'   => 'center center',
					'size'       => 'cover',
					'repeat'     => 'no-repeat',
					'attachment' => 'scroll',
					'type'       => 'normal',
					'gradient'   => '',
				),
				'properties' => array(
					'color'      => array( 'type' => 'string' ),
					'image'      => array( 'type' => 'string' ),
					'imageID'    => array( 'type' => 'string' ),
					'position'   => array( 'type' => 'string' ),
					'size'       => array( 'type' => 'string' ),
					'repeat'     => array( 'type' => 'string' ),
					'attachment' => array( 'type' => 'string' ),
					'type'       => array( 'type' => 'string' ),
					'gradient'   => array( 'type' => 'string' ),
				),
			),
			array(
				'key' => '_flxbk_header_backgroundSticky',
				'type'          => 'object',
				'default'       => array(
					'color'      => '',
					'image'      => '',
					'imageID'    => '',
					'position'   => 'center center',
					'size'       => 'cover',
					'repeat'     => 'no-repeat',
					'attachment' => 'scroll',
					'type'       => 'normal',
					'gradient'   => '',
				),
				'properties' => array(
					'color'      => array( 'type' => 'string' ),
					'image'      => array( 'type' => 'string' ),
					'imageID'    => array( 'type' => 'string' ),
					'position'   => array( 'type' => 'string' ),
					'size'       => array( 'type' => 'string' ),
					'repeat'     => array( 'type' => 'string' ),
					'attachment' => array( 'type' => 'string' ),
					'type'       => array( 'type' => 'string' ),
					'gradient'   => array( 'type' => 'string' ),
				),
			),
			array(
				'key'     => '_flxbk_header_anchor',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_header_className',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key'           => '_flxbk_header_padding',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_header_tabletPadding',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_header_mobilePadding',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'     => '_flxbk_header_paddingUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key'           => '_flxbk_header_margin',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_header_tabletMargin',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_header_mobileMargin',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'     => '_flxbk_header_marginUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_header_linkColor',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_header_linkHoverColor',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key'           => '_flxbk_header_height',
				'default'       => array( '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'     => '_flxbk_header_heightUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key'           => '_flxbk_header_width',
				'default'       => array( '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'     => '_flxbk_header_widthUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_header_isSticky',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_header_isStickyTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_header_isStickyMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_header_isTransparent',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_header_isTransparentTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_header_isTransparentMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_header_autoTransparentSpacing',
				'default' => false,
				'type'    => 'boolean',
			),
			array(
				'key'     => '_flxbk_header_stickySection',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_header_stickySectionTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_header_stickySectionMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_header_shrinkMain',
				'default' => false,
				'type'    => 'boolean',
			),
			array(
				'key'     => '_flxbk_header_shrinkMainHeight',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_header_shrinkMainHeightTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_header_shrinkMainHeightMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_header_revealScrollUp',
				'default' => false,
				'type'    => 'boolean',
			),
			array(
				'key'     => '_flxbk_header_inheritPostTransparent',
				'default' => true,
				'type'    => 'boolean',
			),
			array(
				'key'     => '_flxbk_header_disableTransparentOverrides',
				'type'    => 'array',
				'properties' => array(),
				'children_type' => 'string',
				'default' => array(),
			),
			array(
				'key' => '_flxbk_header_shadow',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'enable' => false,
						'color' => '#000000',
						'opacity' => 0.2,
						'spread' => 0,
						'blur' => 2,
						'hOffset' => 0,
						'vOffset' => 1,
						'inset' => false,
					),
				),
				'properties' => array(
					'enable'    => array( 'type' => 'boolean' ),
					'color'    => array( 'type' => 'string' ),
					'opacity'  => array( 'type' => 'number' ),
					'spread'  => array( 'type' => 'number' ),
					'blur'  => array( 'type' => 'number' ),
					'hOffset'  => array( 'type' => 'number' ),
					'vOffset'  => array( 'type' => 'number' ),
					'inset'    => array( 'type' => 'boolean' ),
				),
			),
			array(
				'key'     => '_flxbk_header_headerTag',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_header_mobileBreakpoint',
				'type' => 'number',
				'default' => 0,
			),
			array(
				'key'     => '_flxbk_header_pro_backdropFilterType',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_header_pro_backdropFilterSize',
				'type' => 'number',
				'default' => 1,
			),
			array(
				'key'     => '_flxbk_header_pro_backdropFilterString',
				'type' => 'string',
				'default' => '',
			),
		);

		foreach ( $register_meta as $meta ) {

			if ( 'string' === $meta['type'] ) {
				$show_in_rest = true;
			} elseif ( $meta['type'] === 'array' ) {
				$show_in_rest = array(
					'schema' => array(
						'type'  => $meta['type'],
						'items' => array(
							'type' => $meta['children_type']
						),
					),
				);

				if ( ! empty( $meta['properties'] ) ) {
					$show_in_rest = array_merge_recursive( $show_in_rest, array(
						'schema' => array(
							'items' => array(
								'properties' => $meta['properties']
							)
						)
					) );
				}
			} elseif ( $meta['type'] === 'object' ) {
				$show_in_rest = array(
					'schema' => array(
						'type'       => $meta['type'],
						'properties' => $meta['properties']
					),
				);
			}

			register_post_meta(
				$this->post_type,
				$meta['key'],
				array(
					'single'        => true,
					'auth_callback' => array( $this, 'meta_auth_callback' ),
					'type'          => $meta['type'],
					'default'       => isset( $meta['default'] ) ? $meta['default'] : '',
					'show_in_rest'  => $show_in_rest,
				)
			);
		}

	}

	/**
	 * Add a post display state for special pages in the page list table.
	 *
	 * @param array   $post_states An array of post display states.
	 * @param WP_Post $post        The current post object.
	 */
	public function add_display_post_states( $post_states, $post ) {
		if ( class_exists( 'Flux\Theme' ) ) {
			if(Flux\flxbk()->option( 'blocks_header' )) {
				$header_id = intval(Flux\flxbk()->option( 'blocks_header_id' ));
				if ( $header_id === $post->ID ) {
					$post_states['fxb_post_for_header'] = __( 'Theme Header', 'flx-blocks' );
				}
			}
		}

		return $post_states;
	}

	/**
	 * Adds a "Flux Header" link to the WordPress admin bar "Headers" menu.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
	 */
	public function top_headers_admin_bar( $wp_admin_bar ) {
		$admin_bar_settings = get_option( 'flxbk_admin_bar_settings' );
		$show_headers_in_admin_bar = isset( $admin_bar_settings['show_headers_in_admin_bar'] ) && $admin_bar_settings['show_headers_in_admin_bar'] === 'true' ? true : false;

		if ( ! current_user_can( 'edit_posts' ) || ! $show_headers_in_admin_bar ) {
			return;
		}

		$wp_admin_bar->add_node( array(
			'id'    => 'flxbk-top-headers',
			'title' => __( 'Headers', 'flx-blocks' ),
			'href'  => admin_url( 'edit.php?post_type=flxbk_header' ),
		) );

		// Add the current header from theme customizer (first node)
		if ( class_exists( 'Flux\Theme' ) ) {
			if ( Flux\flxbk()->option( 'blocks_header' ) ) {
				$header_id = intval( Flux\flxbk()->option( 'blocks_header_id' ) );
				if ( $header_id ) {
					$header_post = get_post( $header_id );
					if ( $header_post && 'flxbk_header' === $header_post->post_type ) {
						// translators: %s is the header title
						$wp_admin_bar->add_node( array(
							'id'     => 'flxbk-header-current',
							'title'  => sprintf( __( '%s (Theme)', 'flx-blocks' ), $header_post->post_title ),
							'parent' => 'flxbk-top-headers',
							'href'   => get_edit_post_link( $header_id ),
						) );
					}
				}
			}
		}

		// Add the 5 most recent header posts
		$recent_headers = get_posts( array(
			'post_type'      => 'flxbk_header',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		foreach ( $recent_headers as $header ) {
			$wp_admin_bar->add_node( array(
				'id'     => 'flxbk-header-recent-' . $header->ID,
				'title'  => $header->post_title,
				'parent' => 'flxbk-top-headers',
				'href'   => get_edit_post_link( $header->ID ),
			) );
		}

		$wp_admin_bar->add_node( array(
			'id'     => 'flxbk-header-add-new',
			'title'  => __( 'Add New', 'flx-blocks' ),
			'parent' => 'flxbk-top-headers',
			'href'   => admin_url( 'post-new.php?post_type=flxbk_header' ),
		) );
	}

	/**
	 * Adds a "Flux Header" link to the WordPress admin bar "New" dropdown menu.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
	 */
	public function add_new_header_to_admin_bar( $wp_admin_bar ) {
		$admin_bar_settings = get_option( 'flxbk_admin_bar_settings' );
		$show_headers_in_admin_bar = isset( $admin_bar_settings['show_headers_in_admin_bar'] ) && $admin_bar_settings['show_headers_in_admin_bar'] === 'true' ? true : false;

		// Only show for users who can create headers
		if ( ! current_user_can( 'edit_theme_options' ) || ! $show_headers_in_admin_bar ) {
			return;
		}

		$wp_admin_bar->add_node( array(
			'id'     => 'new-flxbk-header',
			'title'  => __( 'Header', 'flx-blocks' ),
			'parent' => 'new-content',
			'href'   => admin_url( 'post-new.php?post_type=flxbk_header' ),
		) );
	}
}

Flx_Blocks_Header_CPT_Controller::get_instance();
