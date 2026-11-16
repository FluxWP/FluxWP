<?php
/**
 * MCP (Flux AI ortak sunucusu): wiki araçları, kaynakları ve istemi. Aynı araçlar Flux AI sohbetinde de görünür.
 *
 * @package FLX_Wiki
 */

defined( 'ABSPATH' ) || exit;

/** MCP araç sonucunda sayfa özeti. */
function flxwk_page_brief( $p ) {
	$sec = flxwk_section_of( $p );
	return array(
		'id'       => $p->ID,
		'title'    => get_the_title( $p ),
		'slug'     => get_page_uri( $p ),
		'url'      => get_permalink( $p ),
		'markdown' => flxwk_md_url( $p ),
		'section'  => $sec ? $sec->name : '',
		'parent'   => (int) $p->post_parent,
		'status'   => $p->post_status,
		'excerpt'  => flxwk_excerpt( $p, 200 ),
		'modified' => get_post_modified_time( 'c', true, $p ),
	);
}

add_filter(
	'flx_mcp_tools',
	function ( $tools ) {
		$obj = function ( $props, $req = array() ) {
			$s = array( 'type' => 'object', 'properties' => $props ? $props : new stdClass() );
			if ( $req ) {
				$s['required'] = $req;
			}
			return $s;
		};

		$tools['flxwk_search'] = array(
			'title'       => __( 'Search the wiki', 'flx-wiki' ),
			'description' => 'Searches the developer wiki (FluxWP plugin/theme development, AI-enabled plugins, shared APIs). Turkish characters are folded (i=ı, s=ş …). Returns ranked pages with the best matching heading and a snippet. Read a result with flxwk_get_page.',
			'readonly'    => true,
			'cap'         => 'read',
			'source'      => 'flx-wiki',
			'schema'      => $obj(
				array(
					'query'   => array( 'type' => 'string', 'description' => 'Words to search for.' ),
					'section' => array( 'type' => 'string', 'description' => 'Optional section slug to limit the search.' ),
					'limit'   => array( 'type' => 'integer', 'description' => 'Max results (default 8, max 30).' ),
				),
				array( 'query' )
			),
			'run'         => function ( $a ) {
				$r = flxwk_search( (string) ( $a['query'] ?? '' ), array( 'limit' => min( 30, (int) ( $a['limit'] ?? 8 ) ), 'section' => sanitize_title( (string) ( $a['section'] ?? '' ) ) ) );
				foreach ( $r['results'] as &$it ) {
					$it['snippet'] = wp_strip_all_tags( $it['snippet'] );
					$it['slug']    = get_page_uri( $it['id'] );
				}
				return $r;
			},
		);

		$tools['flxwk_get_page'] = array(
			'title'       => __( 'Read wiki page', 'flx-wiki' ),
			'description' => 'Returns one wiki page as Markdown (title, source URL, section, last update and full body). Identify the page by slug/path (e.g. "plugins/ai-enabled"), title, id or URL.',
			'readonly'    => true,
			'cap'         => 'read',
			'source'      => 'flx-wiki',
			'schema'      => $obj( array( 'page' => array( 'type' => 'string', 'description' => 'Slug, path, title, id or URL.' ) ), array( 'page' ) ),
			'run'         => function ( $a ) {
				$p = flxwk_resolve( (string) ( $a['page'] ?? '' ) );
				if ( ! $p || ! flxwk_readable( $p ) ) {
					throw new Exception( 'Wiki page not found. Use flxwk_search or flxwk_list_pages.' );
				}
				return array_merge( flxwk_page_brief( $p ), array( 'content' => flxwk_page_markdown( $p ) ) );
			},
		);

		$tools['flxwk_list_pages'] = array(
			'title'       => __( 'List wiki pages', 'flx-wiki' ),
			'description' => 'Lists the wiki as a tree grouped by section (title, slug, URL, excerpt). Use it to discover what the wiki covers.',
			'readonly'    => true,
			'cap'         => 'read',
			'source'      => 'flx-wiki',
			'schema'      => $obj( array( 'section' => array( 'type' => 'string', 'description' => 'Optional section slug.' ) ) ),
			'run'         => function ( $a ) {
				$want = sanitize_title( (string) ( $a['section'] ?? '' ) );
				$map  = function ( $nodes ) use ( &$map ) {
					$out = array();
					foreach ( $nodes as $n ) {
						$b             = flxwk_page_brief( $n['post'] );
						unset( $b['status'], $b['parent'] );
						$b['children'] = $map( $n['children'] );
						$out[]         = $b;
					}
					return $out;
				};
				$out = array();
				foreach ( flxwk_tree() as $g ) {
					if ( $want && ( ! $g['term'] || $g['term']->slug !== $want ) ) {
						continue;
					}
					$out[] = array(
						'section' => $g['term'] ? $g['term']->name : '',
						'slug'    => $g['term'] ? $g['term']->slug : '',
						'pages'   => $map( $g['items'] ),
					);
				}
				return array( 'wiki' => flxwk_home_url(), 'llms_txt' => home_url( '/' . flxwk_base() . '/llms.txt' ), 'sections' => $out );
			},
		);

		$tools['flxwk_save_page'] = array(
			'title'       => __( 'Write wiki page', 'flx-wiki' ),
			'description' => 'Creates or updates a wiki page from Markdown. Without "id" a new page is created as a draft unless status=publish. Use [[slug]] or [[slug|label]] for links to other wiki pages and > [!NOTE] / [!TIP] / [!WARNING] callouts. Do not repeat the title as an H1.',
			'readonly'    => false,
			'cap'         => 'edit_posts',
			'source'      => 'flx-wiki',
			'schema'      => $obj(
				array(
					'id'         => array( 'type' => 'integer', 'description' => 'Existing page id to update.' ),
					'title'      => array( 'type' => 'string' ),
					'markdown'   => array( 'type' => 'string', 'description' => 'Full page body in Markdown.' ),
					'slug'       => array( 'type' => 'string' ),
					'parent'     => array( 'type' => 'string', 'description' => 'Parent page slug/path, id or empty for top level.' ),
					'section'    => array( 'type' => 'string', 'description' => 'Section name or slug (created if missing). Only top-level pages need it.' ),
					'excerpt'    => array( 'type' => 'string', 'description' => 'One-sentence summary (SEO description).' ),
					'status'     => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'pending', 'private' ) ),
					'menu_order' => array( 'type' => 'integer' ),
				)
			),
			'run'         => function ( $a ) {
				$args = array();
				foreach ( array( 'id', 'title', 'slug', 'parent', 'section', 'excerpt', 'status', 'menu_order' ) as $k ) {
					if ( isset( $a[ $k ] ) ) {
						$args[ $k ] = $a[ $k ];
					}
				}
				if ( isset( $a['markdown'] ) ) {
					$args['md'] = (string) $a['markdown'];
				}
				if ( ! empty( $args['id'] ) && ! current_user_can( 'edit_post', (int) $args['id'] ) ) {
					throw new Exception( 'You cannot edit this page.' );
				}
				if ( isset( $args['status'] ) && 'publish' === $args['status'] && ! current_user_can( 'publish_posts' ) ) {
					$args['status'] = 'pending';
				}
				$id = flxwk_save_page( $args );
				if ( is_wp_error( $id ) ) {
					throw new Exception( $id->get_error_message() );
				}
				return array_merge( flxwk_page_brief( get_post( $id ) ), array( 'edit' => get_edit_post_link( $id, 'raw' ) ) );
			},
		);
		return $tools;
	}
);

/** Kısa ad, yol, başlık, kimlik ya da adresle sayfa. */
function flxwk_resolve( $ref ) {
	$ref = trim( (string) $ref );
	if ( preg_match( '#^https?://#', $ref ) ) {
		$path = (string) wp_parse_url( $ref, PHP_URL_PATH );
		$base = '/' . flxwk_base() . '/';
		$at   = strpos( $path, $base );
		$ref  = false === $at ? $path : substr( $path, $at + strlen( $base ) );
		$ref  = preg_replace( '/\.md$/', '', trim( $ref, '/' ) );
	}
	return flxwk_find_page( $ref );
}

add_filter(
	'flx_mcp_resources',
	function ( $res ) {
		$res['flxwiki://llms.txt']      = array(
			'name'        => 'wiki-index',
			'title'       => flxwk_title() . ' – llms.txt',
			'description' => 'Index of the developer wiki with Markdown links.',
			'mimeType'    => 'text/markdown',
			'read'        => 'flxwk_index_markdown',
			'cap'         => 'read',
		);
		$res['flxwiki://llms-full.txt'] = array(
			'name'        => 'wiki-full',
			'title'       => flxwk_title() . ' – llms-full.txt',
			'description' => 'The whole developer wiki in one Markdown document.',
			'mimeType'    => 'text/markdown',
			'read'        => 'flxwk_full_markdown',
			'cap'         => 'read',
		);
		return $res;
	}
);

add_filter(
	'flx_mcp_prompts',
	function ( $p ) {
		$p['flxwk_build_plugin'] = array(
			'title'       => __( 'Write a FluxWP plugin (with the wiki guides)', 'flx-wiki' ),
			'description' => 'Starts a FluxWP plugin/theme task with the relevant wiki guides attached.',
			'arguments'   => array(
				array( 'name' => 'task', 'description' => 'What to build, e.g. "a plugin that adds an AI summary button to orders".', 'required' => true ),
			),
			'text'        => function ( $a ) {
				$task  = (string) ( $a['task'] ?? '' );
				$pages = array();
				foreach ( flxwk_search( $task . ' eklenti plugin', array( 'limit' => 3 ) )['results'] as $r ) {
					$pages[] = flxwk_page_markdown( $r['id'] );
				}
				return "Task: {$task}\n\nFollow the FluxWP conventions in these wiki pages (call flxwk_search / flxwk_get_page for more):\n\n" . implode( "\n\n---\n\n", $pages );
			},
		);
		return $p;
	}
);

add_filter(
	'flx_mcp_instructions',
	function ( $lines ) {
		$lines[] = 'Developer wiki: flxwk_search and flxwk_get_page answer questions about writing FluxWP plugins/themes and the shared APIs (Flux AI, MCP, settings schema, brand). Prefer them over guessing.';
		return $lines;
	}
);
