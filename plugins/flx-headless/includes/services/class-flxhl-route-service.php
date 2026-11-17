<?php
/**
 * flxRoute: what an address on a front end shows. The address is parsed the way WordPress parses a page
 * request (rewrite rules, Flux Lang prefix), queried with WP_Query, and described as a route: kind, status,
 * redirect, language, the WordPress object, the SEO head, language versions and cache tags.
 *
 * The head comes from Flux SEO when it is active (the same tags the theme prints), with every site address
 * pointed at the front end; without it a plain head is built from WordPress.
 *
 * @package FLX_Headless
 */

defined( 'ABSPATH' ) || exit;

final class FLXHL_Route_Service {

	public const KINDS = array( 'front_page', 'posts_page', 'singular', 'term', 'post_type_archive', 'author', 'date', 'search', 'redirect', 'not_found' );

	/**
	 * Resolves an address.
	 *
	 * @param string      $path       Front-end path (/about/, /en/blog/?page=2) or a full address of the front end / WordPress.
	 * @param string|null $lang       Language when the path has no language prefix (a front end per language).
	 * @param string|null $frontend   Front end id; null = the caller's Origin or the default front end.
	 * @param int         $preview_id Draft to preview (only for people who can edit it).
	 */
	public static function resolve( string $path, ?string $lang = null, ?string $frontend = null, int $preview_id = 0 ): FLXHL_Route {
		$fe = FLXHL_Frontend_Service::current( $frontend );

		if ( $preview_id > 0 ) {
			return self::preview( $preview_id, $fe );
		}

		$path = self::normalize( $path, $fe );
		if ( null === $path ) {
			return self::not_found( '/', null, $fe );
		}

		// Language prefix (Flux Lang): /en/about/ → en + /about/.
		$code = null;
		if ( function_exists( 'flxl_url_language' ) && function_exists( 'flxl_url_for_language' ) && function_exists( 'flxl_default_language' ) ) {
			$def      = flxhl_str( flxl_default_language() );
			$prefixed = flxhl_str( flxl_url_language( $path ) );
			$code     = $prefixed;
			if ( $prefixed === $def && null !== $lang && '' !== $lang && function_exists( 'flxl_is_language' ) && flxl_is_language( $lang ) ) {
				$code = $lang;
			}
			$path = flxhl_str( flxl_url_for_language( $path, $def ) );
			if ( $fe && $fe->languages && ! in_array( $code, $fe->languages, true ) ) {
				return self::not_found( $path, $code, $fe );
			}
		}

		$switched = false;
		if ( null !== $code && function_exists( 'flxl_switch_language' ) && function_exists( 'flxl_current_language' ) && flxl_current_language() !== $code ) {
			flxl_switch_language( $code );
			$switched = true;
		}
		try {
			$data = self::build( $path, $code, $fe );
		} finally {
			if ( $switched && function_exists( 'flxl_restore_language' ) ) {
				flxl_restore_language();
			}
		}
		return $data;
	}

	/**
	 * Front-end path with the query (always starting with "/"), or null when the address is on another site.
	 */
	private static function normalize( string $path, ?FLXHL_Frontend $fe ): ?string {
		$path = trim( $path );
		if ( '' === $path ) {
			return '/';
		}
		if ( preg_match( '#^https?://#i', $path ) ) {
			if ( $fe && 0 === stripos( $path, $fe->url ) ) {
				$path = substr( $path, strlen( $fe->url ) );
			} else {
				return FLXHL_Frontend_Service::front_path( $path );
			}
		}
		$path = '/' . ltrim( $path, '/' );
		$frag = strpos( $path, '#' );
		if ( false !== $frag ) {
			$path = substr( $path, 0, $frag );
		}
		return flxhl_path_is_backend( $path ) ? null : $path;
	}

	/** Builds the route inside the requested language. */
	private static function build( string $path, ?string $code, ?FLXHL_Frontend $fe ): FLXHL_Route {
		$q = self::query( $path );
		if ( null === $q ) {
			return self::not_found( $path, $code, $fe );
		}
		$obj  = $q->get_queried_object();
		$kind = self::kind( $q );
		$page = max( 1, (int) $q->get( 'paged' ), (int) $q->get( 'page' ) );

		if ( 'singular' === $kind || 'front_page' === $kind || 'posts_page' === $kind ) {
			$post = $obj instanceof WP_Post ? $obj : ( $q->post instanceof WP_Post ? $q->post : null );
			if ( 'posts_page' !== $kind && ( null === $post || ! self::can_see( $post ) ) ) {
				return self::not_found( $path, $code, $fe );
			}
		}
		if ( 'not_found' === $kind ) {
			$to = self::old_slug( $q );
			if ( null !== $to ) {
				return self::redirect( $path, $to, $code, $fe );
			}
			return self::not_found( $path, $code, $fe );
		}
		if ( $page > 1 && in_array( $kind, array( 'posts_page', 'term', 'post_type_archive', 'author', 'date', 'search' ), true ) && $page > (int) $q->max_num_pages ) {
			return self::not_found( $path, $code, $fe );
		}

		// Canonical address: a page asked under another slash / parent path is sent to its own address.
		$canonical = self::canonical_path( $kind, $obj, $q );
		$asked     = (string) wp_parse_url( $path, PHP_URL_PATH );
		if ( null !== $canonical && 1 === $page && false === strpos( $path, '?' ) && $canonical !== $asked && rawurldecode( $canonical ) !== rawurldecode( $asked ) ) {
			return self::redirect( $path, $canonical, $code, $fe );
		}

		$data = array(
			'path'        => $path,
			'kind'        => $kind,
			'status'      => 200,
			'redirect'    => null,
			'lang'        => $code,
			'object_type' => self::object_type( $obj ),
			'object_id'   => self::object_id( $obj ),
			'post_type'   => $obj instanceof WP_Post ? $obj->post_type : ( $obj instanceof WP_Post_Type ? $obj->name : null ),
			'taxonomy'    => $obj instanceof WP_Term ? $obj->taxonomy : null,
			'title'       => self::title( $kind, $obj, $q ),
			'page'        => $page,
			'head'        => self::localize_head( self::with_query( $q, static fn(): array => self::head( $kind, $q, $fe ) ), $code ),
			'alternates'  => self::alternates( $kind, $obj, $path, $code, $fe ),
			'cache_tags'  => self::tags( $kind, $obj, $q ),
			'frontend_id' => $fe ? $fe->id : null,
		);
		/**
		 * Route a front end gets for an address. Packages add cache tags, meta tags or change the head here.
		 *
		 * @param array<string,mixed>  $data Route (see FLXHL_Route::from()).
		 * @param WP_Query             $q    The query the address resolved to.
		 * @param FLXHL_Frontend|null  $fe   Front end.
		 */
		$data = apply_filters( 'flx_headless_route', $data, $q, $fe );
		return FLXHL_Route::from( flxhl_arr( $data ) );
	}

	/**
	 * Parses the path like a page request and runs the main query for it; null when it cannot be parsed.
	 */
	private static function query( string $path ): ?WP_Query {
		$vars = self::vars( flxhl_home_path() . $path );
		if ( null === $vars ) {
			return null;
		}
		if ( '/' === (string) wp_parse_url( $path, PHP_URL_PATH ) ) {
			// The home address: nothing to match in the rewrite rules, only ?s= / ?p= / ?page_id= / ?paged=.
			parse_str( (string) wp_parse_url( $path, PHP_URL_QUERY ), $qs );
			$vars = array();
			foreach ( array( 's', 'p', 'page_id', 'paged', 'cat', 'tag', 'author' ) as $k ) {
				if ( isset( $qs[ $k ] ) && is_scalar( $qs[ $k ] ) && '' !== (string) $qs[ $k ] ) {
					$vars[ $k ] = (string) $qs[ $k ];
				}
			}
		}
		if ( isset( $vars['error'] ) && '404' === flxhl_str( $vars['error'] ) ) {
			$q = new WP_Query();
			$q->set_404();
			$q->query_vars = $vars;
			return $q;
		}
		unset( $vars['uri'] );
		$q = new WP_Query();
		$q->query( $vars );
		if ( ! $q->is_404() && ! $q->have_posts() && $q->is_singular() ) {
			$q->set_404();
		}
		return $q;
	}

	/**
	 * Query variables of a path, as WordPress parses a page request (rewrite rules, ?p=, ?s=).
	 *
	 * @return array<string,mixed>|null
	 */
	public static function vars( string $uri ): ?array {
		if ( class_exists( 'FLXHL_Uri_Parser' ) || self::load_parser() ) {
			return FLXHL_Uri_Parser::vars( $uri );
		}
		// Without WPGraphQL (never in a GraphQL request): ?p= / url_to_postid only.
		$id = url_to_postid( home_url( $uri ) );
		return $id ? array( 'p' => $id, 'post_type' => 'any' ) : array( 'error' => '404' );
	}

	private static function load_parser(): bool {
		if ( ! class_exists( '\WPGraphQL\Data\NodeResolver' ) ) {
			return false;
		}
		require_once FLXHL_DIR . 'includes/class-flxhl-uri-parser.php';
		return class_exists( 'FLXHL_Uri_Parser' );
	}

	private static function kind( WP_Query $q ): string {
		// WordPress itself answers 404 for these (WP::handle_404); the route runs its own query, so check here.
		if ( $q->is_404() || ( $q->is_paged() && ! $q->have_posts() ) ) {
			return 'not_found';
		}
		$obj = $q->get_queried_object();
		return match ( true ) {
			$q->is_front_page() && ( $q->is_page() || $q->is_home() ) => 'front_page',
			$q->is_home()              => 'posts_page',
			$q->is_singular()          => 'singular',
			$q->is_category() || $q->is_tag() || $q->is_tax() => $obj instanceof WP_Term ? 'term' : 'not_found',
			$q->is_post_type_archive() => 'post_type_archive',
			$q->is_author()            => $obj instanceof WP_User ? 'author' : 'not_found',
			$q->is_date()              => 'date',
			$q->is_search()            => 'search',
			default                    => 'not_found',
		};
	}

	/** Published and public, or the visitor may read it (logged-in editors, private pages they can see). */
	public static function can_see( WP_Post $post ): bool {
		if ( is_post_publicly_viewable( $post ) ) {
			return true;
		}
		return current_user_can( 'read_post', $post->ID );
	}

	/** Address of a post renamed since (WordPress' _wp_old_slug), as a front-end path. */
	private static function old_slug( WP_Query $q ): ?string {
		$name = flxhl_str( $q->get( 'name' ) );
		if ( '' === $name ) {
			$name = flxhl_str( $q->get( 'pagename' ) );
			$name = '' === $name ? '' : basename( $name );
		}
		if ( '' === $name ) {
			return null;
		}
		$type  = flxhl_str( $q->get( 'post_type' ) );
		$found = get_posts(
			array(
				'post_type'        => '' === $type || 'any' === $type ? array_values( get_post_types( array( 'public' => true ) ) ) : $type,
				'post_status'      => 'publish',
				'meta_key'         => '_wp_old_slug', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => sanitize_title( $name ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'posts_per_page'   => 1,
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);
		$post  = $found[0] ?? null;
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		$link = get_permalink( $post );
		return is_string( $link ) ? FLXHL_Frontend_Service::front_path( $link ) : null;
	}

	/** Own address (path) of the queried object, or null when the kind has none to compare. */
	private static function canonical_path( string $kind, ?object $obj, WP_Query $q ): ?string {
		$link = '';
		if ( 'singular' === $kind && $obj instanceof WP_Post && 'publish' === $obj->post_status ) {
			$link = flxhl_str( get_permalink( $obj ) );
		} elseif ( 'term' === $kind && $obj instanceof WP_Term ) {
			$t    = get_term_link( $obj );
			$link = is_string( $t ) ? $t : '';
		}
		unset( $q );
		if ( '' === $link ) {
			return null;
		}
		$p = FLXHL_Frontend_Service::front_path( $link );
		if ( null === $p ) {
			return null;
		}
		$p = (string) wp_parse_url( $p, PHP_URL_PATH );
		// Compared with the asked path, which has no language prefix (Flux Lang); redirect() adds it back.
		if ( function_exists( 'flxl_url_for_language' ) && function_exists( 'flxl_default_language' ) ) {
			$p = flxhl_str( flxl_url_for_language( $p, flxhl_str( flxl_default_language() ) ) );
		}
		return $p;
	}

	private static function object_type( ?object $obj ): string {
		return match ( true ) {
			$obj instanceof WP_Post      => 'post',
			$obj instanceof WP_Term      => 'term',
			$obj instanceof WP_User      => 'user',
			$obj instanceof WP_Post_Type => 'post_type',
			default                      => '',
		};
	}

	private static function object_id( ?object $obj ): int {
		return match ( true ) {
			$obj instanceof WP_Post => $obj->ID,
			$obj instanceof WP_Term => $obj->term_id,
			$obj instanceof WP_User => $obj->ID,
			default                 => 0,
		};
	}

	private static function title( string $kind, ?object $obj, WP_Query $q ): string {
		return match ( true ) {
			$obj instanceof WP_Post      => html_entity_decode( get_the_title( $obj ), ENT_QUOTES, 'UTF-8' ),
			$obj instanceof WP_Term      => $obj->name,
			$obj instanceof WP_User      => $obj->display_name,
			$obj instanceof WP_Post_Type => flxhl_str( $obj->labels->name ?? $obj->label ),
			'search' === $kind           => flxhl_str( $q->get( 's' ) ),
			'front_page' === $kind       => flxhl_str( get_bloginfo( 'name' ) ),
			default                      => '',
		};
	}

	/**
	 * Runs $fn with the query as WordPress' main query, so is_front_page(), is_singular() and the SEO
	 * functions answer for this address.
	 *
	 * @template T
	 * @param callable():T $fn
	 * @return T
	 */
	public static function with_query( WP_Query $q, callable $fn ) {
		$saved = array( $GLOBALS['wp_query'] ?? null, $GLOBALS['wp_the_query'] ?? null, $GLOBALS['post'] ?? null );
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['wp_query']     = $q;
		$GLOBALS['wp_the_query'] = $q;
		if ( $q->post instanceof WP_Post ) {
			$GLOBALS['post'] = $q->post;
			setup_postdata( $q->post );
		}
		try {
			return $fn();
		} finally {
			$GLOBALS['wp_query']     = $saved[0];
			$GLOBALS['wp_the_query'] = $saved[1];
			$GLOBALS['post']         = $saved[2];
			// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
			if ( $saved[2] instanceof WP_Post ) {
				setup_postdata( $saved[2] );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Head                                                                */
	/* ------------------------------------------------------------------ */

	/**
	 * Head of the current (swapped-in) query.
	 *
	 * @return array<string,mixed>
	 */
	private static function head( string $kind, WP_Query $q, ?FLXHL_Frontend $fe ): array {
		$title  = html_entity_decode( wp_get_document_title(), ENT_QUOTES, 'UTF-8' );
		$robots = self::robots();
		$meta   = array();
		$canon  = null;
		$desc   = '';

		if ( function_exists( 'flxseo_head' ) ) {
			ob_start();
			flxseo_head();
			$html = (string) ob_get_clean();
			if ( preg_match_all( '#<meta\s+(name|property)="([^"]+)"\s+content="([^"]*)"\s*/?>#i', $html, $m, PREG_SET_ORDER ) ) {
				foreach ( $m as $tag ) {
					$content = html_entity_decode( $tag[3], ENT_QUOTES, 'UTF-8' );
					if ( 'name' === strtolower( $tag[1] ) && 'description' === $tag[2] ) {
						$desc = $content;
						continue;
					}
					$meta[] = array(
						'name'     => 'name' === strtolower( $tag[1] ) ? $tag[2] : '',
						'property' => 'property' === strtolower( $tag[1] ) ? $tag[2] : '',
						'content'  => self::front_value( $tag[2], $content, $fe ),
					);
				}
			}
			if ( preg_match( '#<link\s+rel="canonical"\s+href="([^"]+)"#i', $html, $c ) ) {
				$canon = html_entity_decode( $c[1], ENT_QUOTES, 'UTF-8' );
			}
		} else {
			$canon = self::plain_canonical( $kind, $q );
			$desc  = self::plain_description( $kind, $q );
			$meta  = self::plain_meta( $kind, $q, $title, $desc, $canon, $fe );
		}

		$json_ld = array();
		if ( function_exists( 'flxseo_schema_graph' ) && ( ! function_exists( 'flxseo_output_enabled' ) || flxseo_output_enabled() ) ) {
			$graph = array(
				'@context' => 'https://schema.org',
				'@graph'   => apply_filters( 'flxseo_schema_graph', flxseo_schema_graph() ),
			);
			$json  = wp_json_encode( self::front_tree( $graph, $fe ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( is_string( $json ) ) {
				$json_ld[] = $json;
			}
		}

		$image = null;
		foreach ( $meta as $row ) {
			if ( 'og:image' === $row['property'] && '' !== $row['content'] ) {
				$image = $row['content'];
			}
		}
		$noindex = in_array( 'noindex', $robots, true ) || 'search' === $kind;
		return array(
			'title'       => $title,
			'description' => $desc,
			'canonical'   => null === $canon || '' === $canon ? null : FLXHL_Frontend_Service::to_front( $canon, $fe ),
			'robots'      => $robots,
			'noindex'     => $noindex,
			'image'       => $image,
			'locale'      => str_replace( '_', '-', determine_locale() ),
			'meta'        => $meta,
			'json_ld'     => $json_ld,
		);
	}

	/**
	 * Canonical and og:url in the address' language (Flux Lang prefix), whatever the SEO plugin built them from.
	 *
	 * @param array<string,mixed> $head
	 * @return array<string,mixed>
	 */
	private static function localize_head( array $head, ?string $code ): array {
		if ( null === $code || ! function_exists( 'flxl_url_for_language' ) ) {
			return $head;
		}
		if ( is_string( $head['canonical'] ?? null ) ) {
			$head['canonical'] = self::lang_url( $head['canonical'], $code );
		}
		$meta = array();
		foreach ( flxhl_arr( $head['meta'] ?? array() ) as $m ) {
			$m = flxhl_arr( $m );
			if ( 'og:url' === ( $m['property'] ?? '' ) ) {
				$m['content'] = self::lang_url( flxhl_str( $m['content'] ?? '' ), $code );
			}
			$meta[] = $m;
		}
		$head['meta'] = $meta;
		return $head;
	}

	/** A front-end or WordPress address with the language prefix of $code. */
	private static function lang_url( string $url, string $code ): string {
		$p = wp_parse_url( $url );
		if ( ! is_array( $p ) || empty( $p['host'] ) || ! function_exists( 'flxl_url_for_language' ) ) {
			return $url;
		}
		$origin = flxhl_origin( $url );
		$path   = flxhl_str( flxl_url_for_language( substr( $url, strlen( $origin ) ), $code ) );
		return $origin . $path;
	}

	/** Robots directives WordPress (and Flux SEO through wp_robots) give the page. @return list<string> */
	private static function robots(): array {
		$r   = apply_filters( 'wp_robots', array() );
		$out = array();
		foreach ( flxhl_arr( $r ) as $k => $v ) {
			if ( true === $v ) {
				$out[] = (string) $k;
			} elseif ( is_scalar( $v ) && '' !== (string) $v && false !== $v ) {
				$out[] = $k . ':' . $v;
			}
		}
		if ( ! in_array( 'noindex', $out, true ) && ! in_array( 'index', $out, true ) ) {
			array_unshift( $out, 'index', 'follow' );
		}
		return array_values( array_unique( $out ) );
	}

	/** og:url and other URL-valued tags point at the front end. */
	private static function front_value( string $key, string $value, ?FLXHL_Frontend $fe ): string {
		return in_array( $key, array( 'og:url', 'twitter:url' ), true ) ? FLXHL_Frontend_Service::to_front( $value, $fe ) : $value;
	}

	/**
	 * Every page address inside a JSON-LD tree pointed at the front end (files under wp-content stay).
	 *
	 * @param mixed $node
	 * @return mixed
	 */
	private static function front_tree( $node, ?FLXHL_Frontend $fe ) {
		if ( is_array( $node ) ) {
			foreach ( $node as $k => $v ) {
				$node[ $k ] = self::front_tree( $v, $fe );
			}
			return $node;
		}
		if ( is_string( $node ) && preg_match( '#^https?://#', $node ) ) {
			$hash = strpos( $node, '#' );
			$base = false === $hash ? $node : substr( $node, 0, $hash );
			$tail = false === $hash ? '' : substr( $node, $hash );
			return FLXHL_Frontend_Service::to_front( $base, $fe ) . $tail;
		}
		return $node;
	}

	private static function plain_canonical( string $kind, WP_Query $q ): ?string {
		$obj = $q->get_queried_object();
		$url = match ( true ) {
			'front_page' === $kind => flxhl_home() . '/',
			$obj instanceof WP_Post => wp_get_canonical_url( $obj ),
			$obj instanceof WP_Term => get_term_link( $obj ),
			$obj instanceof WP_Post_Type => get_post_type_archive_link( $obj->name ),
			$obj instanceof WP_User => get_author_posts_url( $obj->ID ),
			default => '',
		};
		return is_string( $url ) && '' !== $url ? $url : null;
	}

	private static function plain_description( string $kind, WP_Query $q ): string {
		$obj = $q->get_queried_object();
		$txt = match ( true ) {
			$obj instanceof WP_Post => has_excerpt( $obj ) ? $obj->post_excerpt : $obj->post_content,
			$obj instanceof WP_Term => $obj->description,
			'front_page' === $kind => flxhl_str( get_bloginfo( 'description' ) ),
			default => '',
		};
		$txt = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( $txt ), true ) ) );
		return mb_strlen( $txt ) > 160 ? rtrim( mb_substr( $txt, 0, 159 ) ) . '…' : $txt;
	}

	/** @return list<array{name: string, property: string, content: string}> */
	private static function plain_meta( string $kind, WP_Query $q, string $title, string $desc, ?string $canon, ?FLXHL_Frontend $fe ): array {
		$obj  = $q->get_queried_object();
		$img  = $obj instanceof WP_Post ? get_the_post_thumbnail_url( $obj, 'large' ) : false;
		$rows = array(
			'og:locale'      => str_replace( '-', '_', determine_locale() ),
			'og:type'        => $obj instanceof WP_Post && 'post' === $obj->post_type ? 'article' : 'website',
			'og:site_name'   => flxhl_str( get_bloginfo( 'name' ) ),
			'og:title'       => $title,
			'og:description' => $desc,
			'og:url'         => null === $canon ? '' : FLXHL_Frontend_Service::to_front( $canon, $fe ),
			'og:image'       => is_string( $img ) ? $img : '',
		);
		unset( $kind );
		$out = array();
		foreach ( $rows as $p => $c ) {
			if ( '' !== $c ) {
				$out[] = array( 'name' => '', 'property' => $p, 'content' => $c );
			}
		}
		$out[] = array( 'name' => 'twitter:card', 'property' => '', 'content' => 'summary_large_image' );
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Languages and cache                                                 */
	/* ------------------------------------------------------------------ */

	/** @return list<array{lang: string, hreflang: string, url: string, path: string}> */
	private static function alternates( string $kind, ?object $obj, string $path, ?string $code, ?FLXHL_Frontend $fe ): array {
		if ( null === $code || ! function_exists( 'flxl_languages' ) || ! function_exists( 'flxl_url_for_language' ) || ! function_exists( 'flxl_hreflang' ) ) {
			return array();
		}
		$out  = array();
		$only = $fe ? $fe->languages : array();
		if ( 'singular' === $kind && $obj instanceof WP_Post && class_exists( 'FLXL_Language_Service' ) ) {
			foreach ( FLXL_Language_Service::translations( $obj->ID ) as $t ) {
				if ( null === $t->url || 'publish' !== $t->status || ( $only && ! in_array( $t->lang, $only, true ) ) ) {
					continue;
				}
				$p = FLXHL_Frontend_Service::front_path( $t->url );
				if ( null !== $p ) {
					$out[] = array( 'lang' => $t->lang, 'hreflang' => flxhl_str( flxl_hreflang( $t->lang ) ), 'url' => $fe ? $fe->url . $p : $t->url, 'path' => $p );
				}
			}
			return $out;
		}
		foreach ( array_keys( flxhl_arr( flxl_languages() ) ) as $l ) {
			$l = (string) $l;
			if ( $only && ! in_array( $l, $only, true ) ) {
				continue;
			}
			$p     = flxhl_str( flxl_url_for_language( $path, $l ) );
			$out[] = array( 'lang' => $l, 'hreflang' => flxhl_str( flxl_hreflang( $l ) ), 'url' => $fe ? $fe->url . $p : flxhl_home() . $p, 'path' => $p );
		}
		return $out;
	}

	/** @return list<string> */
	private static function tags( string $kind, ?object $obj, WP_Query $q ): array {
		$tags = array( 'flx-headless:routes' );
		if ( $obj instanceof WP_Post ) {
			$tags[] = 'flx-headless:post:' . $obj->ID;
		}
		if ( $obj instanceof WP_Term ) {
			$tags[] = 'flx-headless:term:' . $obj->term_id;
		}
		if ( $obj instanceof WP_User ) {
			$tags[] = 'flx-headless:author:' . $obj->ID;
		}
		if ( in_array( $kind, array( 'posts_page', 'post_type_archive', 'date', 'search', 'term', 'author' ), true ) || ( 'front_page' === $kind && ! $obj instanceof WP_Post ) ) {
			$type   = flxhl_str( is_array( $q->get( 'post_type' ) ) ? '' : $q->get( 'post_type' ) );
			$tags[] = 'flx-headless:archive:' . ( '' === $type || 'any' === $type ? 'post' : sanitize_key( $type ) );
		}
		return $tags;
	}

	/* ------------------------------------------------------------------ */
	/* Special routes                                                      */
	/* ------------------------------------------------------------------ */

	private static function not_found( string $path, ?string $code, ?FLXHL_Frontend $fe ): FLXHL_Route {
		$q = new WP_Query();
		$q->set_404();
		$head = self::with_query( $q, static fn(): array => array( 'title' => html_entity_decode( wp_get_document_title(), ENT_QUOTES, 'UTF-8' ), 'robots' => array( 'noindex', 'follow' ), 'noindex' => true, 'locale' => str_replace( '_', '-', determine_locale() ) ) );
		return FLXHL_Route::from(
			array(
				'path'        => $path,
				'kind'        => 'not_found',
				'status'      => 404,
				'lang'        => $code,
				'head'        => $head,
				'cache_tags'  => array( 'flx-headless:routes' ),
				'frontend_id' => $fe ? $fe->id : null,
			)
		);
	}

	private static function redirect( string $path, string $to, ?string $code, ?FLXHL_Frontend $fe ): FLXHL_Route {
		if ( null !== $code && function_exists( 'flxl_url_for_language' ) ) {
			$to = flxhl_str( flxl_url_for_language( $to, $code ) );
		}
		return FLXHL_Route::from(
			array(
				'path'        => $path,
				'kind'        => 'redirect',
				'status'      => 301,
				'redirect'    => $to,
				'lang'        => $code,
				'head'        => array( 'robots' => array( 'noindex' ), 'noindex' => true ),
				'cache_tags'  => array( 'flx-headless:routes' ),
				'frontend_id' => $fe ? $fe->id : null,
			)
		);
	}

	/** A draft or scheduled post as its editor sees it. Never cached; noindex. */
	private static function preview( int $id, ?FLXHL_Frontend $fe ): FLXHL_Route {
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
			return self::not_found( '/', null, $fe );
		}
		$q = new WP_Query();
		$q->query(
			array(
				'p'           => $post->ID,
				'post_type'   => $post->post_type,
				'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			)
		);
		$code = function_exists( 'flxl_post_language' ) ? flxhl_str( flxl_post_language( $post->ID ) ) : '';
		$head = self::with_query( $q, static fn(): array => self::head( 'singular', $q, $fe ) );
		$head['robots']  = array( 'noindex', 'nofollow' );
		$head['noindex'] = true;
		$link            = get_permalink( $post );
		return FLXHL_Route::from(
			array(
				'path'        => is_string( $link ) ? flxhl_str( FLXHL_Frontend_Service::front_path( $link ) ) : '/',
				'kind'        => 'singular',
				'status'      => 200,
				'lang'        => '' === $code ? null : $code,
				'object_type' => 'post',
				'object_id'   => $post->ID,
				'post_type'   => $post->post_type,
				'title'       => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				'head'        => $head,
				'cache_tags'  => array(),
				'frontend_id' => $fe ? $fe->id : null,
			)
		);
	}
}
