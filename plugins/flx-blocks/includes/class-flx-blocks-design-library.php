<?php
/**
 * Design library: pattern catalogue for the editor's "Design Library" button.
 *
 * Sources:
 *  - this site: every registered block pattern (theme, presets, plugins) plus unsynced
 *    user patterns, extended through the `flx_blocks_pattern_sources` filter;
 *  - linked libraries: other Flux sites added by URL (+ optional key). Their catalogue is
 *    fetched server side, so the editor never talks to a third party directly.
 *
 * A site shares its own catalogue at GET /wp-json/flx-blocks/v1/library once sharing is
 * switched on; outside visitors must send the key in the X-Flx-Library-Key header.
 * Format: docs/LIBRARY.md.
 *
 * @package Flux Block Editor
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST routes and storage for the design library.
 */
class Flx_Blocks_Design_Library {

	const NS          = 'flx-blocks/v1';
	const OPT_SOURCES = 'flxbk_library_sources';
	const OPT_SHARE   = 'flxbk_library_share';
	const FORMAT      = 1;

	/**
	 * Hook in.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public static function routes() {
		register_rest_route(
			self::NS,
			'/library',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_catalogue' ),
				'permission_callback' => array( __CLASS__, 'can_read_catalogue' ),
			)
		);
		register_rest_route(
			self::NS,
			'/library/sources',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'rest_sources' ),
					'permission_callback' => array( __CLASS__, 'can_edit' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'rest_sources_update' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/library/remote',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_remote' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => array(
					'source'  => array( 'type' => 'string', 'required' => true ),
					'refresh' => array( 'type' => 'boolean', 'default' => false ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/library/share',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'rest_share' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'rest_share_update' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Editors may read and use the library.
	 */
	public static function can_edit() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Only administrators change sources and sharing.
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * The catalogue is readable by editors, or by anyone holding the share key.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function can_read_catalogue( $request ) {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}
		$share = self::share_settings();
		if ( empty( $share['enabled'] ) ) {
			return new WP_Error( 'flxbk_library_closed', __( 'This site does not share its design library.', 'flx-blocks' ), array( 'status' => 403 ) );
		}
		$sent = (string) $request->get_header( 'x_flx_library_key' );
		if ( '' !== $sent && hash_equals( $share['key'], $sent ) ) {
			return true;
		}
		return new WP_Error( 'flxbk_library_key', __( 'The library key is missing or wrong.', 'flx-blocks' ), array( 'status' => 401 ) );
	}

	/**
	 * GET /library — this site's catalogue.
	 */
	public static function rest_catalogue() {
		$response = rest_ensure_response( self::catalogue() );
		$response->header( 'Cache-Control', 'no-store, max-age=0' );
		$response->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
		return $response;
	}

	/**
	 * Build this site's catalogue.
	 *
	 * @return array{format:int,name:string,url:string,categories:array,items:array}
	 */
	public static function catalogue() {
		$items      = array();
		$used_cats  = array();
		$registry   = class_exists( 'WP_Block_Patterns_Registry' ) ? WP_Block_Patterns_Registry::get_instance()->get_all_registered() : array();
		$skip_core  = (bool) apply_filters( 'flxbk_library_skip_core_patterns', true );
		foreach ( $registry as $pattern ) {
			$name = isset( $pattern['name'] ) ? (string) $pattern['name'] : '';
			if ( '' === $name || empty( $pattern['content'] ) ) {
				continue;
			}
			if ( $skip_core && 0 === strpos( $name, 'core/' ) ) {
				continue;
			}
			if ( isset( $pattern['inserter'] ) && false === $pattern['inserter'] ) {
				continue;
			}
			$cats = isset( $pattern['categories'] ) ? array_values( array_map( 'strval', (array) $pattern['categories'] ) ) : array();
			if ( ! $cats ) {
				$cats = array( 'uncategorized' );
			}
			$items[] = array(
				'id'            => $name,
				'title'         => isset( $pattern['title'] ) ? (string) $pattern['title'] : $name,
				'description'   => isset( $pattern['description'] ) ? (string) $pattern['description'] : '',
				'categories'    => $cats,
				'keywords'      => isset( $pattern['keywords'] ) ? array_values( array_map( 'strval', (array) $pattern['keywords'] ) ) : array(),
				'viewportWidth' => isset( $pattern['viewportWidth'] ) ? (int) $pattern['viewportWidth'] : 1200,
				'content'       => (string) $pattern['content'],
			);
			$used_cats = array_merge( $used_cats, $cats );
		}

		// Unsynced user patterns ("My patterns").
		$user_patterns = get_posts(
			array(
				'post_type'      => 'wp_block',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, editor-only listing.
				'meta_query'     => array(
					array(
						'key'   => 'wp_pattern_sync_status',
						'value' => 'unsynced',
					),
				),
			)
		);
		foreach ( $user_patterns as $post ) {
			$items[]     = array(
				'id'            => 'user/' . $post->ID,
				'title'         => get_the_title( $post ),
				'description'   => '',
				'categories'    => array( 'flx-mine' ),
				'keywords'      => array(),
				'viewportWidth' => 1200,
				'content'       => (string) $post->post_content,
			);
			$used_cats[] = 'flx-mine';
		}

		/**
		 * Add or change catalogue items (theme/preset sections, AI-made sections...).
		 *
		 * @param array $items Items in the docs/LIBRARY.md format.
		 */
		$items = (array) apply_filters( 'flx_blocks_pattern_sources', $items );
		foreach ( $items as $item ) {
			if ( ! empty( $item['categories'] ) ) {
				$used_cats = array_merge( $used_cats, (array) $item['categories'] );
			}
		}

		$items = self::tame_navigation( $items );

		$labels = array(
			'uncategorized' => __( 'Other', 'flx-blocks' ),
			'flx-mine'      => __( 'My patterns', 'flx-blocks' ),
		);
		if ( class_exists( 'WP_Block_Pattern_Categories_Registry' ) ) {
			foreach ( WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered() as $cat ) {
				if ( ! empty( $cat['name'] ) ) {
					$labels[ $cat['name'] ] = isset( $cat['label'] ) ? (string) $cat['label'] : $cat['name'];
				}
			}
		}
		$categories = array();
		foreach ( array_unique( $used_cats ) as $slug ) {
			$categories[] = array(
				'slug'  => (string) $slug,
				'label' => isset( $labels[ $slug ] ) ? $labels[ $slug ] : ucwords( str_replace( array( '-', '_', '/' ), ' ', (string) $slug ) ),
			);
		}

		return array(
			'format'     => self::FORMAT,
			'name'       => get_bloginfo( 'name' ),
			'url'        => home_url( '/' ),
			'categories' => $categories,
			'items'      => array_values( $items ),
		);
	}

	/**
	 * Navigation blocks with no menu (no `ref`, no links) fall back to listing every page of the
	 * site, which floods header/footer patterns (WooCommerce and others) in the preview and after
	 * insertion. Give them the site's own menu instead: top-level items of the header navigation
	 * (submenus become plain links), at most a handful.
	 *
	 * @param array $items Catalogue items.
	 * @return array
	 */
	private static function tame_navigation( array $items ) {
		$links = null;
		foreach ( $items as $i => $item ) {
			$content = isset( $item['content'] ) ? (string) $item['content'] : '';
			if ( false === strpos( $content, '<!-- wp:navigation' ) || ! function_exists( 'parse_blocks' ) ) {
				continue;
			}
			if ( null === $links ) {
				$links = self::menu_links();
			}
			$changed = false;
			$blocks  = self::fill_navigation( parse_blocks( $content ), $links, $changed );
			if ( $changed ) {
				$items[ $i ]['content'] = serialize_blocks( $blocks );
			}
		}
		return $items;
	}

	/**
	 * Walk blocks and give empty navigation blocks the menu links.
	 *
	 * @param array $blocks  Parsed blocks.
	 * @param array $links   navigation-link blocks.
	 * @param bool  $changed Set when a block was filled.
	 * @return array
	 */
	private static function fill_navigation( array $blocks, array $links, &$changed ) {
		foreach ( $blocks as $k => $block ) {
			if ( 'core/navigation' === $block['blockName'] ) {
				if ( empty( $block['attrs']['ref'] ) && empty( $block['innerBlocks'] ) && $links ) {
					$blocks[ $k ]['innerBlocks']  = $links;
					$blocks[ $k ]['innerHTML']    = '';
					$blocks[ $k ]['innerContent'] = array_fill( 0, count( $links ), null );
					$changed                      = true;
				}
				continue;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$blocks[ $k ]['innerBlocks'] = self::fill_navigation( $block['innerBlocks'], $links, $changed );
			}
		}
		return $blocks;
	}

	/**
	 * Top-level links of the site's main menu: the header template part's navigation (inline links
	 * or its wp_navigation menu), else the newest published menu, else a home link.
	 *
	 * @return array navigation-link blocks.
	 */
	private static function menu_links() {
		$max   = (int) apply_filters( 'flxbk_library_menu_items', 6 );
		$nav   = null;
		$parts = function_exists( 'get_block_template' ) ? get_block_template( get_stylesheet() . '//header', 'wp_template_part' ) : null;
		if ( $parts && ! empty( $parts->content ) ) {
			$nav = self::find_navigation( parse_blocks( $parts->content ) );
		}
		$inner = array();
		if ( $nav && ! empty( $nav['innerBlocks'] ) ) {
			$inner = $nav['innerBlocks'];
		} else {
			$ref  = $nav && ! empty( $nav['attrs']['ref'] ) ? (int) $nav['attrs']['ref'] : 0;
			$post = $ref ? get_post( $ref ) : null;
			if ( ! $post || 'wp_navigation' !== $post->post_type ) {
				$menus = get_posts(
					array(
						'post_type'      => 'wp_navigation',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'no_found_rows'  => true,
					)
				);
				$post  = $menus ? $menus[0] : null;
			}
			if ( $post ) {
				$inner = parse_blocks( $post->post_content );
			}
		}
		$links = array();
		foreach ( $inner as $block ) {
			if ( ! in_array( $block['blockName'], array( 'core/navigation-link', 'core/navigation-submenu' ), true ) ) {
				continue;
			}
			$attrs   = isset( $block['attrs'] ) ? (array) $block['attrs'] : array();
			$links[] = self::link_block( array_intersect_key( $attrs, array_flip( array( 'label', 'url', 'kind', 'type', 'id', 'opensInNewTab' ) ) ) );
			if ( count( $links ) >= $max ) {
				break;
			}
		}
		if ( ! $links ) {
			$links[] = self::link_block(
				array(
					'label' => __( 'Home', 'flx-blocks' ),
					'url'   => home_url( '/' ),
					'kind'  => 'custom',
				)
			);
		}
		/**
		 * Links given to menu-less navigation blocks in Design Library patterns.
		 *
		 * @param array $links Parsed core/navigation-link blocks.
		 */
		return (array) apply_filters( 'flxbk_library_menu_links', $links );
	}

	/**
	 * First navigation block in a block tree.
	 *
	 * @param array $blocks Parsed blocks.
	 * @return array|null
	 */
	private static function find_navigation( array $blocks ) {
		foreach ( $blocks as $block ) {
			if ( 'core/navigation' === $block['blockName'] ) {
				return $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = self::find_navigation( $block['innerBlocks'] );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * A parsed core/navigation-link block.
	 *
	 * @param array $attrs Attributes.
	 * @return array
	 */
	private static function link_block( array $attrs ) {
		return array(
			'blockName'    => 'core/navigation-link',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * Linked libraries. Keys never leave the server.
	 *
	 * @param bool $with_keys Include keys (server use only).
	 * @return array<int,array{id:string,name:string,url:string,key?:string,has_key?:bool}>
	 */
	public static function sources( $with_keys = false ) {
		$list = get_option( self::OPT_SOURCES, array() );
		$out  = array();
		foreach ( is_array( $list ) ? $list : array() as $row ) {
			if ( empty( $row['id'] ) || empty( $row['url'] ) ) {
				continue;
			}
			$item = array(
				'id'   => (string) $row['id'],
				'name' => isset( $row['name'] ) ? (string) $row['name'] : (string) $row['url'],
				'url'  => (string) $row['url'],
			);
			if ( $with_keys ) {
				$item['key'] = isset( $row['key'] ) ? (string) $row['key'] : '';
			} else {
				$item['has_key'] = ! empty( $row['key'] );
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * GET /library/sources.
	 */
	public static function rest_sources() {
		return rest_ensure_response(
			array(
				'sources'   => self::sources(),
				'canManage' => current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * POST /library/sources — {action: add|remove, name, url, key, id}.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function rest_sources_update( $request ) {
		$action = (string) $request->get_param( 'action' );
		$list   = self::sources( true );
		if ( 'remove' === $action ) {
			$id   = (string) $request->get_param( 'id' );
			$list = array_values(
				array_filter(
					$list,
					static function ( $row ) use ( $id ) {
						return $row['id'] !== $id;
					}
				)
			);
			delete_transient( 'flxbk_lib_' . md5( $id ) );
		} elseif ( 'add' === $action ) {
			$url = esc_url_raw( trim( (string) $request->get_param( 'url' ) ), array( 'https', 'http' ) );
			if ( ! $url || ! wp_http_validate_url( $url ) ) {
				return new WP_Error( 'flxbk_library_url', __( 'Enter a valid site address.', 'flx-blocks' ), array( 'status' => 400 ) );
			}
			$url  = trailingslashit( preg_replace( '#/wp-json/.*$#', '', $url ) );
			$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
			$key  = sanitize_text_field( (string) $request->get_param( 'key' ) );
			$row  = array(
				'id'   => substr( md5( $url . wp_rand() ), 0, 12 ),
				'name' => '' !== $name ? $name : wp_parse_url( $url, PHP_URL_HOST ),
				'url'  => $url,
				'key'  => $key,
			);
			$test = self::fetch_remote( $row, true );
			if ( is_wp_error( $test ) ) {
				return $test;
			}
			if ( '' === $name && ! empty( $test['name'] ) ) {
				$row['name'] = sanitize_text_field( $test['name'] );
			}
			$list[] = $row;
		} else {
			return new WP_Error( 'flxbk_library_action', __( 'Unknown action.', 'flx-blocks' ), array( 'status' => 400 ) );
		}
		update_option( self::OPT_SOURCES, $list, false );
		return self::rest_sources();
	}

	/**
	 * GET /library/remote?source=ID — a linked library's catalogue, cached for 30 minutes.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function rest_remote( $request ) {
		$id = (string) $request->get_param( 'source' );
		foreach ( self::sources( true ) as $row ) {
			if ( $row['id'] === $id ) {
				$data = self::fetch_remote( $row, (bool) $request->get_param( 'refresh' ) );
				return is_wp_error( $data ) ? $data : rest_ensure_response( $data );
			}
		}
		return new WP_Error( 'flxbk_library_source', __( 'Library not found.', 'flx-blocks' ), array( 'status' => 404 ) );
	}

	/**
	 * Fetch and validate a linked library.
	 *
	 * @param array $row     Source row with key.
	 * @param bool  $refresh Skip the cache.
	 * @return array|WP_Error
	 */
	private static function fetch_remote( array $row, $refresh = false ) {
		$cache_key = 'flxbk_lib_' . md5( $row['id'] );
		if ( ! $refresh ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		$headers = array( 'Accept' => 'application/json' );
		if ( ! empty( $row['key'] ) ) {
			$headers['X-Flx-Library-Key'] = $row['key'];
		}
		$res = wp_safe_remote_get(
			$row['url'] . 'wp-json/' . self::NS . '/library',
			array(
				'timeout' => 15,
				'headers' => $headers,
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'flxbk_library_unreachable', __( 'The library could not be reached.', 'flx-blocks' ), array( 'status' => 502 ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'flxbk_library_denied', __( 'The library refused access. Check the key, and that sharing is on at the other site.', 'flx-blocks' ), array( 'status' => 403 ) );
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code || ! is_array( $data ) || empty( $data['format'] ) || ! isset( $data['items'] ) || ! is_array( $data['items'] ) ) {
			return new WP_Error( 'flxbk_library_invalid', __( 'This address is not a Flux design library.', 'flx-blocks' ), array( 'status' => 502 ) );
		}
		$items = array();
		foreach ( $data['items'] as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) || empty( $item['content'] ) || ! is_string( $item['content'] ) ) {
				continue;
			}
			$items[] = array(
				'id'            => sanitize_text_field( (string) $item['id'] ),
				'title'         => sanitize_text_field( isset( $item['title'] ) ? (string) $item['title'] : (string) $item['id'] ),
				'description'   => sanitize_text_field( isset( $item['description'] ) ? (string) $item['description'] : '' ),
				'categories'    => array_values( array_map( 'sanitize_key', isset( $item['categories'] ) ? (array) $item['categories'] : array() ) ),
				'keywords'      => array_values( array_map( 'sanitize_text_field', isset( $item['keywords'] ) ? (array) $item['keywords'] : array() ) ),
				'viewportWidth' => isset( $item['viewportWidth'] ) ? max( 320, min( 2400, (int) $item['viewportWidth'] ) ) : 1200,
				// Block markup; it passes through the usual kses filters when the post is saved.
				'content'       => (string) $item['content'],
			);
		}
		$categories = array();
		foreach ( isset( $data['categories'] ) ? (array) $data['categories'] : array() as $cat ) {
			if ( is_array( $cat ) && ! empty( $cat['slug'] ) ) {
				$categories[] = array(
					'slug'  => sanitize_key( $cat['slug'] ),
					'label' => sanitize_text_field( isset( $cat['label'] ) ? (string) $cat['label'] : $cat['slug'] ),
				);
			}
		}
		$out = array(
			'format'     => (int) $data['format'],
			'name'       => sanitize_text_field( isset( $data['name'] ) ? (string) $data['name'] : $row['name'] ),
			'url'        => $row['url'],
			'categories' => $categories,
			'items'      => $items,
		);
		set_transient( $cache_key, $out, 30 * MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * Sharing settings.
	 *
	 * @return array{enabled:bool,key:string}
	 */
	public static function share_settings() {
		$s = get_option( self::OPT_SHARE, array() );
		return array(
			'enabled' => ! empty( $s['enabled'] ),
			'key'     => isset( $s['key'] ) ? (string) $s['key'] : '',
		);
	}

	/**
	 * GET /library/share.
	 */
	public static function rest_share() {
		$s        = self::share_settings();
		$s['url'] = home_url( '/' );
		return rest_ensure_response( $s );
	}

	/**
	 * POST /library/share — {enabled, regenerate}.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public static function rest_share_update( $request ) {
		$s = self::share_settings();
		if ( null !== $request->get_param( 'enabled' ) ) {
			$s['enabled'] = (bool) $request->get_param( 'enabled' );
		}
		if ( $request->get_param( 'regenerate' ) || ( $s['enabled'] && '' === $s['key'] ) ) {
			$s['key'] = wp_generate_password( 32, false );
		}
		update_option( self::OPT_SHARE, $s, false );
		return self::rest_share();
	}
}

Flx_Blocks_Design_Library::init();
