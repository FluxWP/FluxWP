<?php
/**
 * Class managing the navigation CPT registration.
 */
class Flx_Blocks_Navigation_CPT_Controller {

	private $post_type = 'flxbk_navigation';
	/**
	 * Instance Control
	 *
	 * @var null
	 */
	private static $instance = null;
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
		// Register the meta settings for from post.
		add_action( 'init', array( $this, 'register_meta' ), 20 );
		add_filter( 'user_has_cap', array( $this, 'filter_post_type_user_caps' ) );

		// Define the form post gutenberg template.
		add_action( 'init', array( $this, 'form_gutenberg_template' ) );
		add_filter( 'flxbk_post_layout', array( $this, 'navigation_single_layout' ), 99 );
		add_action( 'enqueue_block_assets', array( $this, 'script_enqueue' ) );

		if( is_admin() && class_exists( 'Flx_Blocks_Duplicate_Post' ) ) {
			new Flx_Blocks_Duplicate_Post( $this->post_type );
		}
		if( is_admin() && class_exists( 'Flx_Blocks_Cpt_Import_Export' ) ) {
			new Flx_Blocks_Cpt_Import_Export( $this->post_type );
		}
	}
	/**
	 * Renders the navigation single template on the front end.
	 *
	 * @param array $layout the layout array.
	 */
	public function navigation_single_layout( $layout ) {
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
	 * Enqueue Script for Meta options
	 */
	public function script_enqueue() {
		$post_type = get_post_type();
		if ( $this->post_type !== $post_type ) {
			return;
		}
		$output = '.post-type-flxbk_navigation.block-editor-page .editor-styles-wrapper .editor-post-title__block .editor-post-title__input, .post-type-flxbk_navigation .edit-post-visual-editor__post-title-wrapper {
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
.post-type-flxbk_navigation .editor-styles-wrapper .edit-post-visual-editor__post-title-wrapper:not(.specificity)  {
	padding-top: 24px !important;
    padding-bottom: 20px !important;
    margin-bottom: 20px !important;
    margin-top: 0 !important;
	font-size: 1em;
	position: relative;
}
.post-type-flxbk_navigation .editor-styles-wrapper .edit-post-visual-editor__post-title-wrapper .editor-post-title:before, .post-type-flxbk_navigation .edit-post-visual-editor__post-title-wrapper:before {
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
.post-type-flxbk_navigation .edit-post-visual-editor__post-title-wrapper .editor-post-title {
	font-size: 1.2em;
    font-weight: 500;
    line-height: 1;
	margin: 0;
}
.post-type-flxbk_navigation .editor-styles-wrapper .edit-post-visual-editor__post-title-wrapper .editor-post-title {
	font-size: 1.2em;

    font-weight: 500;
    line-height: 1;
}
.post-type-flxbk_navigation .editor-styles-wrapper {
	padding:8px;
	margin: 0;
}
.post-type-flxbk_navigation .wp-block {max-width: none;} .post-type-flxbk_navigation .editor-styles-wrapper .wp-block {max-width: none;}';
		wp_register_style( 'flxbk_navigation_css', false );
		wp_enqueue_style( 'flxbk_navigation_css' );
		wp_add_inline_style( 'flxbk_navigation_css', $output );
	}
	/**
	 * Add filters for element content output.
	 */
	public function form_gutenberg_template() {
		$post_type_object = get_post_type_object( $this->post_type );
		$post_type_object->template = array(
			array(
				'flx/navigation',
			),
		);
		$post_type_object->template_lock = 'insert';
	}


	/**
	 * Registers the form post type.
	 */
	public function register_post_type() {
		$navigation_post_edit_link = 'site-editor.php?' . build_query(
				array(
					'postId'   => '%s',
					'postType' => $this->post_type,
					'canvas'   => 'edit',
				)
			);

		register_post_type(
			$this->post_type,
			array(
				'labels'                => array(
					'name'                  => _x( 'Flux Navigation', 'post type general name', 'flx-blocks' ),
					'singular_name'         => _x( 'Flux Navigation', 'post type singular name', 'flx-blocks' ),
					'add_new'               => __( 'Add New Flux Navigation', 'flx-blocks' ),
					'add_new_item'          => __( 'Add New Flux Navigation', 'flx-blocks' ),
					'new_item'              => __( 'New Flux Navigation', 'flx-blocks' ),
					'edit_item'             => __( 'Edit Flux Navigation', 'flx-blocks' ),
					'view_item'             => __( 'View Flux Navigation', 'flx-blocks' ),
					'all_items'             => __( 'Navigations', 'flx-blocks' ),
					'search_items'          => __( 'Search Flux Navigations', 'flx-blocks' ),
					'parent_item_colon'     => __( 'Parent Flux Navigation:', 'flx-blocks' ),
					'not_found'             => __( 'No Flux Navigation found.', 'flx-blocks' ),
					'not_found_in_trash'    => __( 'No Flux Navigation found in Trash.', 'flx-blocks' ),
					'archives'              => __( 'Flux Navigation archives', 'flx-blocks' ),
					'insert_into_item'      => __( 'Insert into Flux Navigation', 'flx-blocks' ),
					'uploaded_to_this_item' => __( 'Uploaded to this Flux Navigation', 'flx-blocks' ),
					'filter_items_list'     => __( 'Filter Flux Navigation list', 'flx-blocks' ),
					'items_list_navigation' => __( 'Flux Navigation list navigation', 'flx-blocks' ),
					'items_list'            => __( 'Flux Navigation list', 'flx-blocks' ),
				),
				'description'           => __( 'Flux Navigations that can be inserted into your site.', 'flx-blocks' ),
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
				'rest_controller_class' => Flx_Blocks_Navigation_CPT_Rest_Controller::class,
				'supports'              => array(
					'title',
					'editor',
					'revisions',
					'custom-fields',
				),
			)
		);
	}

	/**
	 * Check that user can edit these.
	 */
	public function meta_auth_callback() {
		return current_user_can( 'edit_flxbk_navigation' );
	}


	/**
	 * Filters the capabilities of a user to conditionally grant them capabilities for managing navigations.
	 *
	 * Any user who can 'edit_others_pages' will have access to manage navigations.
	 *
	 * @param array $allcaps A user's capabilities.
	 * @return array Filtered $allcaps.
	 */
	public function filter_post_type_user_caps( $allcaps ) {
		if ( isset( $allcaps['edit_others_pages'] ) ) {
			$allcaps['edit_flxbk_navigation']             = $allcaps['edit_others_pages'];
			$allcaps['edit_others_flxbk_navigation']      = $allcaps['edit_others_pages'];
			$allcaps['edit_published_flxbk_navigation']   = $allcaps['edit_others_pages'];
			$allcaps['edit_private_flxbk_navigation']     = $allcaps['edit_others_pages'];
			$allcaps['delete_flxbk_navigation']           = $allcaps['edit_others_pages'];
			$allcaps['delete_others_flxbk_navigation']    = $allcaps['edit_others_pages'];
			$allcaps['delete_published_flxbk_navigation'] = $allcaps['edit_others_pages'];
			$allcaps['delete_private_flxbk_navigation']   = $allcaps['edit_others_pages'];
			$allcaps['publish_flxbk_navigation']          = $allcaps['edit_others_pages'];
			$allcaps['read_private_flxbk_navigation']     = $allcaps['edit_others_pages'];
		}
		return $allcaps;
	}

	public function register_meta() {
		$register_meta = array(
			array(
				'key'     => '_flxbk_navigation_description',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_anchor',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_className',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_padding',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_tabletPadding',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_mobilePadding',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_paddingUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_margin',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_tabletMargin',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_mobileMargin',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_marginUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_paddingDropdown',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_tabletPaddingDropdown',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_mobilePaddingDropdown',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_paddingDropdownUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_marginDropdown',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_tabletMarginDropdown',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_mobileMarginDropdown',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_marginDropdownUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_paddingLink',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_tabletPaddingLink',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_mobilePaddingLink',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_paddingLinkUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_marginLink',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_tabletMarginLink',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_mobileMarginLink',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_marginLinkUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_paddingDropdownLink',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_tabletPaddingDropdownLink',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_mobilePaddingDropdownLink',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_paddingDropdownLinkUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_marginDropdownLink',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_tabletMarginDropdownLink',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_mobileMarginDropdownLink',
				'default'       => array( '', '', '', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_marginDropdownLinkUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_orientation',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_orientationTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_orientationMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_spacing',
				'type' => 'array',
				'default' => array( '', '', '', '' ),
				'children_type' => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_spacingTablet',
				'type' => 'array',
				'default' => array( '', '', '', '' ),
				'children_type' => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_spacingMobile',
				'type' => 'array',
				'default' => array( '', '', '', '' ),
				'children_type' => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_style',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_styleTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_styleMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_spacingUnit',
				'type' => 'string',
				'default' => 'em',
			),
			array(
				'key'     => '_flxbk_navigation_horizontalLayout',
				'type' => 'string',
				'default' => 'standard',
			),
			array(
				'key'     => '_flxbk_navigation_horizontalLayoutTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_horizontalLayoutMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_horizontalGrid',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_horizontalGridTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_horizontalGridMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_stretchFill',
				'type' => 'string',
				'default' => 'standard',
			),
			array(
				'key'     => '_flxbk_navigation_stretchFillTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_stretchFillMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_parentActive',
				'type' => 'boolean',
				'default' => false,
			),
			array(
				'key'     => '_flxbk_navigation_parentActiveTablet',
				'type' => 'boolean',
				'default' => false,
			),
			array(
				'key'     => '_flxbk_navigation_parentActiveMobile',
				'type' => 'boolean',
				'default' => false,
			),
			array(
				'key'     => '_flxbk_navigation_collapseSubMenus',
				'type' => 'boolean',
				'default' => true,
			),
			array(
				'key'     => '_flxbk_navigation_collapseSubMenusTablet',
				'type' => 'boolean',
				'default' => true,
			),
			array(
				'key'     => '_flxbk_navigation_collapseSubMenusMobile',
				'type' => 'boolean',
				'default' => true,
			),
			array(
				'key'     => '_flxbk_navigation_parentTogglesMenus',
				'type' => 'boolean',
				'default' => false,
			),
			array(
				'key'     => '_flxbk_navigation_parentTogglesMenusTablet',
				'type' => 'boolean',
				'default' => false,
			),
			array(
				'key'     => '_flxbk_navigation_parentTogglesMenusMobile',
				'type' => 'boolean',
				'default' => false,
			),
			array(
				'key'     => '_flxbk_navigation_linkColor',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorHover',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorActive',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorHoverTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorActiveTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorHoverMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorActiveMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_background',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundHover',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundActive',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundHoverTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundActiveTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundHoverMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundActiveMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorDropdown',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorDropdownHover',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorDropdownActive',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorDropdownTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorDropdownHoverTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorDropdownActiveTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorDropdownMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorDropdownHoverMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorDropdownActiveMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundDropdown',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundDropdownHover',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundDropdownActive',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundDropdownTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundDropdownHoverTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundDropdownActiveTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundDropdownMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundDropdownHoverMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundDropdownActiveMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorTransparent',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorTransparentHover',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorTransparentActive',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorTransparentTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorTransparentHoverTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorTransparentActiveTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorTransparentMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorTransparentHoverMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorTransparentActiveMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundTransparent',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundTransparentHover',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundTransparentActive',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundTransparentTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundTransparentHoverTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundTransparentActiveTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundTransparentMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundTransparentHoverMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundTransparentActiveMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorSticky',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorStickyHover',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorStickyActive',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorStickyTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorStickyHoverTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorStickyActiveTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorStickyMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorStickyHoverMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_linkColorStickyActiveMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundSticky',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundStickyHover',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundStickyActive',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundStickyTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundStickyHoverTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundStickyActiveTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundStickyMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundStickyHoverMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundStickyActiveMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_descriptionColor',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_descriptionColorHover',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_descriptionColorActive',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_descriptionColorTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_descriptionColorHoverTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_descriptionColorActiveTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_descriptionColorMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_descriptionColorHoverMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_descriptionColorActiveMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionColor',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionColorHover',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionColorActive',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionColorTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionColorHoverTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionColorActiveTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionColorMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionColorHoverMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionColorActiveMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_divider',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top' => array( '', '', '' ),
						'right' => array( '', '', '' ),
						'bottom' => array( '', '', '' ),
						'left' => array( '', '', '' ),
						'unit' => 'px',
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
				'key'     => '_flxbk_navigation_dividerTablet',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top' => array( '', '', '' ),
						'right' => array( '', '', '' ),
						'bottom' => array( '', '', '' ),
						'left' => array( '', '', '' ),
						'unit' => 'px',
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
				'key'     => '_flxbk_navigation_dividerMobile',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top' => array( '', '', '' ),
						'right' => array( '', '', '' ),
						'bottom' => array( '', '', '' ),
						'left' => array( '', '', '' ),
						'unit' => 'px',
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
				'key'     => '_flxbk_navigation_dropdownDivider',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top' => array( '', '', '' ),
						'right' => array( '', '', '' ),
						'bottom' => array( '', '', '' ),
						'left' => array( '', '', '' ),
						'unit' => 'px',
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
				'key'     => '_flxbk_navigation_dropdownDividerTablet',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top' => array( '', '', '' ),
						'right' => array( '', '', '' ),
						'bottom' => array( '', '', '' ),
						'left' => array( '', '', '' ),
						'unit' => 'px',
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
				'key'     => '_flxbk_navigation_dropdownDividerMobile',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top' => array( '', '', '' ),
						'right' => array( '', '', '' ),
						'bottom' => array( '', '', '' ),
						'left' => array( '', '', '' ),
						'unit' => 'px',
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
				'key'     => '_flxbk_navigation_transparentDivider',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top' => array( '', '', '' ),
						'right' => array( '', '', '' ),
						'bottom' => array( '', '', '' ),
						'left' => array( '', '', '' ),
						'unit' => 'px',
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
				'key'     => '_flxbk_navigation_transparentDividerTablet',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top' => array( '', '', '' ),
						'right' => array( '', '', '' ),
						'bottom' => array( '', '', '' ),
						'left' => array( '', '', '' ),
						'unit' => 'px',
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
				'key'     => '_flxbk_navigation_transparentDividerMobile',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top' => array( '', '', '' ),
						'right' => array( '', '', '' ),
						'bottom' => array( '', '', '' ),
						'left' => array( '', '', '' ),
						'unit' => 'px',
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
				'key'     => '_flxbk_navigation_stickyDivider',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top' => array( '', '', '' ),
						'right' => array( '', '', '' ),
						'bottom' => array( '', '', '' ),
						'left' => array( '', '', '' ),
						'unit' => 'px',
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
				'key'     => '_flxbk_navigation_stickyDividerTablet',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top' => array( '', '', '' ),
						'right' => array( '', '', '' ),
						'bottom' => array( '', '', '' ),
						'left' => array( '', '', '' ),
						'unit' => 'px',
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
				'key'     => '_flxbk_navigation_stickyDividerMobile',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'top' => array( '', '', '' ),
						'right' => array( '', '', '' ),
						'bottom' => array( '', '', '' ),
						'left' => array( '', '', '' ),
						'unit' => 'px',
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
				'key'     => '_flxbk_navigation_typography',
				'default'       => array(
					array(
						'size'    => array( '', '', '' ),
						'sizeType'  => 'px',
						'lineHeight' => array( '', '', '' ),
						'lineType'   => '',
						'letterSpacing'   => array( '', '', '' ),
						'letterType' => 'px',
						'textTransform' => '',
						'family' => '',
						'google' => false,
						'style' => '',
						'weight' => '',
						'variant' => '',
						'subset' => '',
						'loadGoogle' => true,
					),
				),
				'type'          => 'array',
				'children_type' => 'object',
				'properties' => array(
					'size'    => array( 'type' => 'array' ),
					'sizeType'  => array( 'type' => 'string' ),
					'lineHeight' => array( 'type' => 'array' ),
					'lineType'   => array( 'type' => 'string' ),
					'letterSpacing'   => array( 'type' => 'array' ),
					'letterType'   => array( 'type' => 'string' ),
					'textTransform'   => array( 'type' => 'string' ),
					'family'   => array( 'type' => 'string' ),
					'google'   => array( 'type' => 'boolean' ),
					'style'   => array( 'type' => 'string' ),
					'weight'   => array( 'type' => 'string' ),
					'variant'   => array( 'type' => 'string' ),
					'subset'   => array( 'type' => 'string' ),
					'loadGoogle'   => array( 'type' => 'boolean' ),
				),
			),
			array(
				'key'     => '_flxbk_navigation_dropdownTypography',
				'default'       => array(
					array(
						'size'    => array( '', '', '' ),
						'sizeType'  => 'px',
						'lineHeight' => array( '', '', '' ),
						'lineType'   => '',
						'letterSpacing'   => array( '', '', '' ),
						'letterType' => 'px',
						'textTransform' => '',
						'family' => '',
						'google' => false,
						'style' => '',
						'weight' => '',
						'variant' => '',
						'subset' => '',
						'loadGoogle' => true,
					),
				),
				'type'          => 'array',
				'children_type' => 'object',
				'properties' => array(
					'size'    => array( 'type' => 'array' ),
					'sizeType'  => array( 'type' => 'string' ),
					'lineHeight' => array( 'type' => 'array' ),
					'lineType'   => array( 'type' => 'string' ),
					'letterSpacing'   => array( 'type' => 'array' ),
					'letterType'   => array( 'type' => 'string' ),
					'textTransform'   => array( 'type' => 'string' ),
					'family'   => array( 'type' => 'string' ),
					'google'   => array( 'type' => 'boolean' ),
					'style'   => array( 'type' => 'string' ),
					'weight'   => array( 'type' => 'string' ),
					'variant'     => array( 'type' => 'string' ),
					'subset'       => array( 'type' => 'string' ),
					'loadGoogle' => array( 'type' => 'boolean' ),
				),
			),
			array(
				'key'     => '_flxbk_navigation_descriptionTypography',
				'default'       => array(
					array(
						'size'    => array( '', '', '' ),
						'sizeType'  => 'px',
						'lineHeight' => array( '', '', '' ),
						'lineType'   => '',
						'letterSpacing'   => array( '', '', '' ),
						'letterType' => 'px',
						'textTransform' => '',
						'family' => '',
						'google' => false,
						'style' => '',
						'weight' => '',
						'variant' => '',
						'subset' => '',
						'loadGoogle' => true,
					),
				),
				'type'          => 'array',
				'children_type' => 'object',
				'properties' => array(
					'size'    => array( 'type' => 'array' ),
					'sizeType'  => array( 'type' => 'string' ),
					'lineHeight' => array( 'type' => 'array' ),
					'lineType'   => array( 'type' => 'string' ),
					'letterSpacing'   => array( 'type' => 'array' ),
					'letterType'   => array( 'type' => 'string' ),
					'textTransform'   => array( 'type' => 'string' ),
					'family'   => array( 'type' => 'string' ),
					'google'   => array( 'type' => 'boolean' ),
					'style'   => array( 'type' => 'string' ),
					'weight'   => array( 'type' => 'string' ),
					'variant'     => array( 'type' => 'string' ),
					'subset'       => array( 'type' => 'string' ),
					'loadGoogle' => array( 'type' => 'boolean' ),
				),
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionTypography',
				'default'       => array(
					array(
						'size'    => array( '', '', '' ),
						'sizeType'  => 'px',
						'lineHeight' => array( '', '', '' ),
						'lineType'   => '',
						'letterSpacing'   => array( '', '', '' ),
						'letterType' => 'px',
						'textTransform' => '',
						'family' => '',
						'google' => false,
						'style' => '',
						'weight' => '',
						'variant' => '',
						'subset' => '',
						'loadGoogle' => true,
					),
				),
				'type'          => 'array',
				'children_type' => 'object',
				'properties' => array(
					'size'    => array( 'type' => 'array' ),
					'sizeType'  => array( 'type' => 'string' ),
					'lineHeight' => array( 'type' => 'array' ),
					'lineType'   => array( 'type' => 'string' ),
					'letterSpacing'   => array( 'type' => 'array' ),
					'letterType'   => array( 'type' => 'string' ),
					'textTransform'   => array( 'type' => 'string' ),
					'family'   => array( 'type' => 'string' ),
					'google'   => array( 'type' => 'boolean' ),
					'style'   => array( 'type' => 'string' ),
					'weight'   => array( 'type' => 'string' ),
					'variant'     => array( 'type' => 'string' ),
					'subset'       => array( 'type' => 'string' ),
					'loadGoogle' => array( 'type' => 'boolean' ),
				),
			),
			array(
				'key'     => '_flxbk_navigation_dropdownWidth',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownWidthTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownWidthMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownWidthUnit',
				'type' => 'string',
				'default' => 'px',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownVerticalSpacing',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownVerticalSpacingTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownVerticalSpacingMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownVerticalSpacingUnit',
				'type' => 'string',
				'default' => 'em',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownHorizontalAlignment',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownHorizontalAlignmentTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownHorizontalAlignmentMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_descriptionSpacing',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_descriptionSpacingTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_descriptionSpacingMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_descriptionSpacingUnit',
				'type' => 'string',
				'default' => 'px',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionSpacing',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionSpacingTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionSpacingMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionSpacingUnit',
				'type' => 'string',
				'default' => 'px',
			),
			array(
				'key'     => '_flxbk_navigation_descriptionPositioning',
				'type' => 'string',
				'default' => 'normal',
			),
			array(
				'key'     => '_flxbk_navigation_descriptionPositioningTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_descriptionPositioningMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionPositioning',
				'type' => 'string',
				'default' => 'normal',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionPositioningTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownDescriptionPositioningMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key' => '_flxbk_navigation_dropdownShadow',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'enable' => false,
						'color' => '#000000',
						'opacity' => 0.1,
						'spread' => 0,
						'blur' => 13,
						'hOffset' => 0,
						'vOffset' => 2,
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
				'key'     => '_flxbk_navigation_dropdownReveal',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownRevealTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownRevealMobile',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key' => '_flxbk_navigation_dropdownBorder',
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
				'key' => '_flxbk_navigation_dropdownBorderTablet',
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
				'key' => '_flxbk_navigation_dropdownBorderMobile',
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
				'key'           => '_flxbk_navigation_dropdownBorderRadius',
				'default'       => array( 0, 0, 0, 0 ),
				'type'          => 'array',
				'children_type' => 'integer',
			),
			array(
				'key'           => '_flxbk_navigation_dropdownBorderRadiusTablet',
				'default'       => array( 0, 0, 0, 0 ),
				'type'          => 'array',
				'children_type' => 'integer',
			),
			array(
				'key'           => '_flxbk_navigation_dropdownBorderRadiusMobile',
				'default'       => array( 0, 0, 0, 0 ),
				'type'          => 'array',
				'children_type' => 'integer',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownBorderRadiusUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundType',
				'default' => 'normal',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundTypeHover',
				'default' => 'normal',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundTypeActive',
				'default' => 'normal',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundGradient',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundGradientHover',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_backgroundGradientActive',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key' => '_flxbk_navigation_border',
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
				'key' => '_flxbk_navigation_borderTablet',
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
				'key' => '_flxbk_navigation_borderMobile',
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
				'key' => '_flxbk_navigation_borderHover',
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
				'key' => '_flxbk_navigation_borderHoverTablet',
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
				'key' => '_flxbk_navigation_borderHoverMobile',
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
				'key' => '_flxbk_navigation_borderActive',
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
				'key' => '_flxbk_navigation_borderActiveTablet',
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
				'key' => '_flxbk_navigation_borderActiveMobile',
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
				'key'           => '_flxbk_navigation_borderRadius',
				'default'       => array( '', '','', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_borderRadiusTablet',
				'default'       => array( '', '','', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_borderRadiusMobile',
				'default'       => array( '', '','', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_borderRadiusHover',
				'default'       => array( '', '','', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_borderRadiusHoverTablet',
				'default'       => array( '', '','', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_borderRadiusHoverMobile',
				'default'       => array( '', '','', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_borderRadiusActive',
				'default'       => array( '', '','', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_borderRadiusActiveTablet',
				'default'       => array( '', '','', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'           => '_flxbk_navigation_borderRadiusActiveMobile',
				'default'       => array( '', '','', '' ),
				'type'          => 'array',
				'children_type' => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_borderRadiusUnit',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_borderRadiusUnitHover',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_borderRadiusUnitActive',
				'default' => 'px',
				'type'    => 'string',
			),
			array(
				'key' => '_flxbk_navigation_shadow',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'enable' => false,
						'color' => '#000000',
						'opacity' => 0.2,
						'spread' => 0,
						'blur' => 2,
						'hOffset' => 1,
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
				'key' => '_flxbk_navigation_shadowHover',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'enable' => false,
						'color' => '#000000',
						'opacity' => 0.2,
						'spread' => 0,
						'blur' => 2,
						'hOffset' => 1,
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
				'key' => '_flxbk_navigation_shadowActive',
				'type' => 'array',
				'children_type' => 'object',
				'default' => array(
					array(
						'enable' => false,
						'color' => '#000000',
						'opacity' => 0.2,
						'spread' => 0,
						'blur' => 2,
						'hOffset' => 1,
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
				'key'     => '_flxbk_navigation_linkHorizontalAlignment',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_linkHorizontalAlignmentTablet',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_linkHorizontalAlignmentMobile',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownLinkHorizontalAlignment',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownLinkHorizontalAlignmentTablet',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_dropdownLinkHorizontalAlignmentMobile',
				'default' => '',
				'type'    => 'string',
			),
			array(
				'key'     => '_flxbk_navigation_enableScrollSpy',
				'type' => 'boolean',
				'default' => false,
			),
			array(
				'key'     => '_flxbk_navigation_scrollSpyOffsetManual',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_scrollSpyOffset',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_scrollSpyOffsetTablet',
				'type' => 'string',
				'default' => '',
			),
			array(
				'key'     => '_flxbk_navigation_scrollSpyOffsetMobile',
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
							'type' => isset($meta['children_type']) ? $meta['children_type'] : ''
						),
					),
				);

				if( !empty( $meta['properties']) ) {
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
					'default'       => $meta['default'],
					'show_in_rest'  => $show_in_rest,
				)
			);
		}

	}
}

Flx_Blocks_Navigation_CPT_Controller::get_instance();
