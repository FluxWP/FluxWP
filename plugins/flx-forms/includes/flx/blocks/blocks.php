<?php
/**
 * Flux Forms block editor: build and edit Flux Forms forms with Gutenberg blocks.
 *
 * One form, two editors. A "Flux Form" block (flxf/form) holds field blocks; when the
 * post is saved the editor turns them into the Flux Forms structure and writes it to the
 * same emsfb_form row the panel edits. Opening a panel-made form in the block turns the
 * structure back into blocks. Submissions, e-mail, tracking codes, automations and
 * payments all run through the normal Flux Forms pipeline, because the front end prints
 * the form with the Flux Forms renderer.
 *
 * REST (flxf/v1):
 *   GET  /block-forms/<id>  structure + hash
 *   POST /block-forms       {id?, name, structure, hash?} create/update; 409 when the
 *                           form changed elsewhere since `hash` was read.
 *
 * @package Flux Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registration, REST and rendering for the form blocks.
 */
class FLXF_Blocks {

	/**
	 * Field block names after `flxf/` (one per Flux Forms element type, see src/types.js).
	 */
	const FIELD_BLOCKS = array(
		'text', 'email', 'tel', 'mobile', 'textarea', 'number', 'password', 'url', 'date', 'range', 'color',
		'select', 'radio', 'checkbox', 'multiselect', 'image-choice', 'checklist', 'choice-quantity', 'yes-no', 'switch', 'terms', 'country', 'state', 'city',
		'file', 'file-drop', 'rating', 'score-5', 'nps', 'matrix', 'signature', 'location', 'audio', 'video', 'screen',
		'pay-checkbox', 'pay-radio', 'pay-select', 'pay-multiselect', 'amount', 'total', 'stripe',
		'heading', 'link', 'html',
		'step', 'raw',
	);

	/**
	 * Form types the blocks may write.
	 */
	const FORM_TYPES = array( 'form', 'survey', 'subscribe', 'payment', 'register', 'login' );

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'block_categories_all', array( __CLASS__, 'category' ), 10, 1 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'hide_legacy_forms' ), 1 );
	}

	/**
	 * Forms are made with Flux Forms: the Flux Block Editor's own form blocks leave the
	 * inserter. Pages that already use them keep working, and the blocks offer
	 * "Move to Flux Forms". Runs right after wp-blocks, before any block registers.
	 */
	public static function hide_legacy_forms() {
		if ( ! apply_filters( 'flxf_hide_legacy_form_blocks', true ) ) {
			return;
		}
		wp_add_inline_script(
			'wp-blocks',
			'wp.hooks.addFilter("blocks.registerBlockType","flxf/hide-legacy-forms",function(s,n){return ["flx/form","flx/advanced-form"].indexOf(n)>-1?Object.assign({},s,{supports:Object.assign({},s.supports||{},{inserter:false})}):s;});'
		);
	}

	/**
	 * Block category.
	 *
	 * @param array $cats Categories.
	 * @return array
	 */
	public static function category( $cats ) {
		$have = wp_list_pluck( $cats, 'slug' );
		$mine = array(
			'flux-forms'          => __( 'Flux Forms', 'flx-forms' ),
			'flux-forms-choice'   => __( 'Flux Forms: choices', 'flx-forms' ),
			'flux-forms-advanced' => __( 'Flux Forms: advanced', 'flx-forms' ),
			'flux-forms-payment'  => __( 'Flux Forms: payment', 'flx-forms' ),
			'flux-forms-content'  => __( 'Flux Forms: content', 'flx-forms' ),
		);
		$add = array();
		foreach ( $mine as $slug => $title ) {
			if ( ! in_array( $slug, $have, true ) ) {
				$add[] = array(
					'slug'  => $slug,
					'title' => $title,
					'icon'  => null,
				);
			}
		}
		return array_merge( $add, $cats );
	}

	/**
	 * Register the editor script and the blocks.
	 */
	public static function register() {
		$dir   = __DIR__ . '/build/';
		$asset = is_readable( $dir . 'index.asset.php' ) ? include $dir . 'index.asset.php' : array(
			'dependencies' => array(),
			'version'      => '0',
		);
		$url   = plugins_url( 'build/', __FILE__ );
		wp_register_script( 'flxf-blocks', $url . 'index.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'flxf-blocks', 'flx-forms', EMSFB_PLUGIN_DIRECTORY . 'languages' );
		if ( is_readable( $dir . 'index.css' ) ) {
			wp_register_style( 'flxf-blocks-editor', $url . 'index.css', array(), $asset['version'] );
		}
		wp_register_style( 'flxf-blocks', plugins_url( 'assets/blocks.css', __FILE__ ), array(), $asset['version'] );
		wp_localize_script(
			'flxf-blocks',
			'flxfBlocks',
			array(
				'builderUrl'   => admin_url( 'admin.php?page=Emsfb_create' ),
				'responsesUrl' => admin_url( 'admin.php?page=flxf-responses' ),
				'canManage'    => self::can_manage(),
				'hasFlxBlocks' => defined( 'FLXBK_VERSION' ),
				'geoUrl'       => EMSFB_PLUGIN_URL . 'vendor/offline/json/',
				'sampleImage'  => EMSFB_PLUGIN_URL . 'includes/admin/assets/image/header.png',
			)
		);

		register_block_type(
			'flxf/form',
			array(
				'api_version'     => 3,
				'editor_script'   => 'flxf-blocks',
				'editor_style'    => 'flxf-blocks-editor',
				'style'           => 'flxf-blocks',
				'render_callback' => array( __CLASS__, 'render_form' ),
				'attributes'      => array(
					'formId'   => array( 'type' => 'number', 'default' => 0 ),
					'formName' => array( 'type' => 'string', 'default' => '' ),
					'hash'     => array( 'type' => 'string', 'default' => '' ),
					'head'     => array( 'type' => 'object', 'default' => array() ),
					'step'     => array( 'type' => 'object', 'default' => array() ),
					'uid'      => array( 'type' => 'string', 'default' => '' ),
					'design'   => array( 'type' => 'object', 'default' => array() ),
					'align'    => array( 'type' => 'string', 'default' => '' ),
				),
				'supports'        => array(
					'html'  => false,
					'align' => array( 'wide', 'full' ),
				),
			)
		);
		foreach ( self::FIELD_BLOCKS as $type ) {
			register_block_type(
				'flxf/' . $type,
				array(
					'api_version'     => 3,
					'editor_script'   => 'flxf-blocks',
					'render_callback' => '__return_empty_string',
					'parent'          => array( 'flxf/form' ),
				)
			);
		}
	}

	/**
	 * The builder (Forms › New form): the block editor's scripts and styles, the Flux Forms blocks and the builder
	 * that the Forms screen mounts (window.flxfBuilder).
	 */
	public static function builder_assets() {
		$dir   = __DIR__ . '/build/';
		$asset = is_readable( $dir . 'builder.asset.php' ) ? include $dir . 'builder.asset.php' : array(
			'dependencies' => array(),
			'version'      => '0',
		);
		$context = new WP_Block_Editor_Context( array( 'name' => 'flxf/builder' ) );
		wp_add_inline_script(
			'wp-blocks',
			'wp.blocks.setCategories(' . wp_json_encode( get_block_categories( $context ) ) . ');wp.blocks.unstable__bootstrapServerSideBlockDefinitions(' . wp_json_encode( get_block_editor_server_block_settings() ) . ');'
		);
		wp_enqueue_script( 'wp-format-library' );
		wp_enqueue_script( 'flxf-blocks' );
		wp_register_script( 'flxf-builder', plugins_url( 'build/builder.js', __FILE__ ), array_merge( (array) $asset['dependencies'], array( 'flxf-blocks' ) ), $asset['version'], true );
		wp_set_script_translations( 'flxf-builder', 'flx-forms', EMSFB_PLUGIN_DIRECTORY . 'languages' );
		wp_enqueue_script( 'flxf-builder' );
		foreach ( array( 'wp-components', 'wp-block-editor', 'wp-block-editor-content', 'wp-edit-blocks', 'wp-format-library', 'flxf-blocks', 'flxf-blocks-editor' ) as $style ) {
			if ( wp_style_is( $style, 'registered' ) ) {
				wp_enqueue_style( $style );
			}
		}
		wp_register_style( 'flxf-builder', plugins_url( 'assets/builder.css', __FILE__ ), array( 'wp-components' ), $asset['version'] );
		wp_enqueue_style( 'flxf-builder' );
	}

	/**
	 * Same rule as the Flux Forms panel.
	 */
	public static function can_manage() {
		return is_user_logged_in() && ( current_user_can( 'manage_options' ) || current_user_can( 'Emsfb' ) );
	}

	/**
	 * REST routes.
	 */
	public static function routes() {
		register_rest_route(
			'flxf/v1',
			'/block-forms/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_get' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
			)
		);
		register_rest_route(
			'flxf/v1',
			'/block-forms',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'rest_list' ),
					'permission_callback' => array( __CLASS__, 'can_read' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'rest_save' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Editors may read forms to place them.
	 */
	public static function can_read() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Read a form row.
	 *
	 * @param int $id Form id.
	 * @return object|null
	 */
	private static function row( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'emsfb_form';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin table.
		return $wpdb->get_row( $wpdb->prepare( "SELECT form_id, form_name, form_type, form_structer FROM `{$table}` WHERE form_id = %d", (int) $id ) );
	}

	/**
	 * Decode a stored structure (stored with escaped quotes, see Emsfb admin).
	 *
	 * @param string $raw Stored value.
	 * @return array
	 */
	public static function decode( $raw ) {
		$data = json_decode( str_replace( '\\"', '"', (string) $raw ), true );
		if ( ! is_array( $data ) ) {
			$data = json_decode( str_replace( '\\', '', (string) $raw ), true );
		}
		return is_array( $data ) ? $data : array();
	}

	/**
	 * GET /block-forms — forms to choose from.
	 */
	public static function rest_list() {
		global $wpdb;
		$table = $wpdb->prefix . 'emsfb_form';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- plugin table.
		$rows = $wpdb->get_results( "SELECT form_id, form_name, form_type FROM `{$table}` ORDER BY form_id DESC LIMIT 500" );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'id'   => (int) $r->form_id,
				'name' => (string) $r->form_name,
				'type' => (string) $r->form_type,
			);
		}
		return rest_ensure_response( $out );
	}

	/**
	 * GET /block-forms/<id>.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function rest_get( $request ) {
		$form = self::get( (int) $request['id'] );
		if ( ! $form ) {
			return new WP_Error( 'flxf_form_missing', __( 'Form not found.', 'flx-forms' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $form );
	}

	/**
	 * POST /block-forms.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function rest_save( $request ) {
		$r = self::save(
			(int) $request->get_param( 'id' ),
			(string) $request->get_param( 'name' ),
			$request->get_param( 'structure' ),
			(string) $request->get_param( 'hash' ),
			(bool) $request->get_param( 'force' )
		);
		return $r instanceof WP_Error ? $r : rest_ensure_response( $r );
	}

	/**
	 * A stored form for an editor: id, name, type, structure and the hash a save sends back.
	 *
	 * @param int $id Form id.
	 * @return array<string,mixed>|null
	 */
	public static function get( $id ) {
		$row = self::row( (int) $id );
		if ( ! $row ) {
			return null;
		}
		return array(
			'id'        => (int) $row->form_id,
			'name'      => (string) $row->form_name,
			'type'      => (string) $row->form_type,
			'structure' => self::decode( $row->form_structer ),
			'hash'      => md5( (string) $row->form_structer ),
		);
	}

	/**
	 * Create or update a form from an editor (blocks or the Forms builder).
	 *
	 * @param int    $id        Form id; 0 creates one.
	 * @param string $name      Form name.
	 * @param mixed  $structure Flux Forms structure (head first).
	 * @param string $hash      Hash the editor read; '' skips the check.
	 * @param bool   $force     Save even when the form changed since.
	 * @return array{id:int,name:string,hash:string}|WP_Error
	 */
	public static function save( $id, $name, $structure, $hash = '', $force = false ) {
		global $wpdb;
		$id   = (int) $id;
		$name = sanitize_text_field( (string) $name );
		if ( '' === $name ) {
			$name = __( 'Form', 'flx-forms' );
		}
		if ( ! is_array( $structure ) || empty( $structure[0]['type'] ) ) {
			return new WP_Error( 'flxf_form_invalid', __( 'The form structure is not valid.', 'flx-forms' ), array( 'status' => 400 ) );
		}
		if ( ! in_array( $structure[0]['type'], self::FORM_TYPES, true ) ) {
			return new WP_Error( 'flxf_form_type', __( 'This form type is not supported.', 'flx-forms' ), array( 'status' => 400 ) );
		}
		$structure[0]['formName'] = $name;
		$efb = function_exists( 'get_efbFunction' ) ? get_efbFunction() : null;
		if ( $efb && method_exists( $efb, 'sanitize_obj_msg_efb' ) ) {
			$structure = $efb->sanitize_obj_msg_efb( $structure );
		}
		$json   = wp_json_encode( $structure, JSON_UNESCAPED_UNICODE );
		$stored = str_replace( '"', '\\"', (string) $json );
		$table  = $wpdb->prefix . 'emsfb_form';

		if ( $id ) {
			$row = self::row( $id );
			if ( ! $row ) {
				return new WP_Error( 'flxf_form_missing', __( 'Form not found.', 'flx-forms' ), array( 'status' => 404 ) );
			}
			if ( '' !== $hash && ! hash_equals( md5( (string) $row->form_structer ), $hash ) && ! $force ) {
				return new WP_Error( 'flxf_form_conflict', __( 'This form was changed somewhere else after you opened it.', 'flx-forms' ), array( 'status' => 409 ) );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- plugin table.
			$wpdb->update(
				$table,
				array(
					'form_structer' => $stored,
					'form_name'     => $name,
					'form_type'     => $structure[0]['type'],
				),
				array( 'form_id' => $id )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- plugin table.
			$wpdb->insert(
				$table,
				array(
					'form_name'        => $name,
					'form_structer'    => $stored,
					'form_email'       => isset( $structure[0]['email'] ) ? sanitize_email( $structure[0]['email'] ) : '',
					'form_created_by'  => (string) get_current_user_id(),
					'form_type'        => $structure[0]['type'],
					'form_create_date' => wp_date( 'Y-m-d H:i:s' ),
				)
			);
			$id = (int) $wpdb->insert_id;
			if ( ! $id ) {
				return new WP_Error( 'flxf_form_insert', __( 'The form could not be saved.', 'flx-forms' ), array( 'status' => 500 ) );
			}
		}
		self::flush( $id );
		/**
		 * A form was saved from the block editor or the Forms builder.
		 *
		 * @param int   $id        Form id.
		 * @param array $structure Structure.
		 */
		do_action( 'flxf_block_form_saved', $id, $structure );
		return array(
			'id'   => $id,
			'name' => $name,
			'hash' => md5( $stored ),
		);
	}

	/**
	 * Drop the caches Flux Forms keeps for a form.
	 *
	 * @param int $id Form id.
	 */
	private static function flush( $id ) {
		foreach ( array( array( 'form_structer', 'form_type' ), array( 'form_structer' ), array( 'form_structer', 'form_type', 'form_name' ) ) as $fields ) {
			wp_cache_delete( 'efb_form_' . $id . '_' . md5( implode( '_', $fields ) ), 'emsfb' );
		}
		delete_transient( 'emsfb_forms_list' );
		delete_transient( 'emsfb_survey_' . (int) $id );
		if ( class_exists( 'FLXF_Form_Service' ) ) {
			FLXF_Form_Service::purge( (int) $id );
		}
	}

	/**
	 * Front end: the Flux Forms renderer plus the block's design settings.
	 *
	 * @param array $attributes Attributes.
	 * @return string
	 */
	public static function render_form( $attributes ) {
		$id = isset( $attributes['formId'] ) ? (int) $attributes['formId'] : 0;
		if ( ! $id ) {
			return '';
		}
		$html = do_shortcode( '[EMS_Form_Builder id=' . $id . ']' );
		$uid  = ! empty( $attributes['uid'] ) ? preg_replace( '/[^a-z0-9]/', '', (string) $attributes['uid'] ) : (string) $id;
		$design = self::design_vars( isset( $attributes['design'] ) ? (array) $attributes['design'] : array() );
		$vars   = $design['css'];
		$cls    = 'wp-block-flxf-form flxf-bform flxf-bform-' . $uid;
		foreach ( $design['set'] as $key ) {
			$cls .= ' flxf-set-' . $key;
		}
		if ( empty( $attributes['design']['showHeader'] ) ) {
			$cls .= ' flxf-bform-noheader';
		}
		if ( ! empty( $attributes['align'] ) ) {
			$cls .= ' align' . sanitize_html_class( $attributes['align'] );
		}
		return sprintf(
			'<div class="%1$s"%2$s>%3$s</div>',
			esc_attr( $cls ),
			$vars ? ' style="' . esc_attr( $vars ) . '"' : '',
			$html
		);
	}

	/**
	 * Design settings → CSS custom properties (consumed by assets/blocks.css).
	 *
	 * Every variable that is set also adds a `flxf-set-<name>` class, so the stylesheet
	 * only overrides Flux Forms's own look where the block chose something.
	 *
	 * @param array $d Design attribute.
	 * @return array { css: string, set: string[] }
	 */
	public static function design_vars( array $d ) {
		$map = array(
			'labelColor'  => '--flxf-b-label',
			'inputText'   => '--flxf-b-input-text',
			'inputBg'     => '--flxf-b-input-bg',
			'inputBorder' => '--flxf-b-input-border',
			'buttonBg'    => '--flxf-b-button-bg',
			'buttonText'  => '--flxf-b-button-text',
			'cardBg'      => '--flxf-b-card-bg',
		);
		$css = '';
		$set = array();
		foreach ( $map as $key => $var ) {
			if ( ! empty( $d[ $key ] ) && preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([0-9., %]+\)|var\(--[a-zA-Z0-9-]+\)|transparent)$/', (string) $d[ $key ] ) ) {
				$css  .= $var . ':' . $d[ $key ] . ';';
				$set[] = substr( $var, 9 );
			}
		}
		foreach ( array( 'radius' => '--flxf-b-radius', 'gap' => '--flxf-b-gap' ) as $key => $var ) {
			if ( isset( $d[ $key ] ) && '' !== $d[ $key ] && is_numeric( $d[ $key ] ) ) {
				$css  .= $var . ':' . max( 0, min( 60, (float) $d[ $key ] ) ) . 'px;';
				$set[] = substr( $var, 9 );
			}
		}
		return array(
			'css' => $css,
			'set' => $set,
		);
	}
}

FLXF_Blocks::init();
