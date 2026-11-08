<?php
/**
 * Ajan uyumu ve güvenlik başlıkları:
 *  - /llms.txt kendiliğinden üretilir (kökte gerçek dosya varsa sunucu onu verir, bu kod çalışmaz).
 *  - WebMCP: arama formuna toolname/tooldescription (tarayıcıdaki ajanlar formu araç olarak görür).
 *  - İsteğe bağlı başlıklar: X-Frame-Options + frame-ancestors, COOP, temel CSP, HSTS includeSubDomains/preload.
 *  - Tek tık düzeltmeler `flx_system_fixes` (MCP flxseo_system_fix ve Site denetimi düğmeleri).
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------------------
 * llms.txt
 * ------------------------------------------------------------------------- */

/** llms.txt içeriği (12 saat önbellek; içerik ya da ayar değişince silinir). */
function flxseo_llms_txt( $fresh = false ) {
	$cached = $fresh ? false : get_transient( 'flxseo_llms' );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}
	$skip  = array_filter( array( (int) get_option( 'woocommerce_cart_page_id' ), (int) get_option( 'woocommerce_checkout_page_id' ), (int) get_option( 'woocommerce_myaccount_page_id' ) ) );
	$line  = function ( $title, $url, $desc = '' ) {
		$title = trim( preg_replace( '/[\[\]\r\n]+/', ' ', wp_strip_all_tags( html_entity_decode( (string) $title, ENT_QUOTES ) ) ) );
		$desc  = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( (string) $desc, ENT_QUOTES ) ) ) );
		return '- [' . ( '' !== $title ? $title : $url ) . '](' . esc_url_raw( $url ) . ')' . ( '' !== $desc ? ': ' . mb_substr( $desc, 0, 160 ) : '' );
	};
	$usable = function ( $p ) use ( $skip ) {
		return $p && 'publish' === $p->post_status && '' === $p->post_password && ! flxseo_get( $p->ID, 'noindex' ) && ! in_array( (int) $p->ID, $skip, true );
	};

	$out   = array( '# ' . flxseo_opt( 'site_name' ), '' );
	$desc  = trim( (string) flxseo_opt( 'home_desc' ) );
	$out[] = '> ' . ( '' !== $desc ? $desc : home_url( '/' ) );
	$out[] = '';
	$intro = trim( (string) flxseo_opt( 'llms_intro' ) );
	if ( '' !== $intro ) {
		$out[] = $intro;
		$out[] = '';
	}

	// Ana sayfalar: önce menü, yoksa üst düzey sayfalar.
	$pages = array();
	$seen  = array( untrailingslashit( home_url( '/' ) ) => true );
	foreach ( function_exists( 'flxseo_mcp_nav_links' ) ? flxseo_mcp_nav_links() : array() as $l ) {
		$url = (string) ( $l['url'] ?? '' );
		if ( '' === $url || '#' === $url[0] ) {
			continue;
		}
		$abs = 0 === strpos( $url, '/' ) ? home_url( $url ) : $url;
		$key = untrailingslashit( strtok( $abs, '#' ) );
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$pid          = url_to_postid( $abs );
		$p            = $pid ? get_post( $pid ) : null;
		if ( $pid && ! $usable( $p ) ) {
			continue;
		}
		$pages[] = $line( $l['label'] ?? '', $abs, $p ? flxseo_post_description( $p ) : '' );
	}
	if ( count( $pages ) < 3 ) {
		foreach ( get_pages( array( 'parent' => 0, 'sort_column' => 'menu_order,post_title', 'number' => 30 ) ) as $p ) {
			$key = untrailingslashit( get_permalink( $p ) );
			if ( isset( $seen[ $key ] ) || ! $usable( $p ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$pages[]      = $line( get_the_title( $p ), get_permalink( $p ), flxseo_post_description( $p ) );
		}
	}
	if ( $pages ) {
		$out[] = '## ' . __( 'Pages', 'flx-seo' );
		$out[] = '';
		$out   = array_merge( $out, array_slice( $pages, 0, 30 ) );
		$out[] = '';
	}

	$sections = array(
		'post'    => __( 'Recent posts', 'flx-seo' ),
		'product' => __( 'Products', 'flx-seo' ),
	);
	foreach ( flxseo_post_types() as $t ) {
		if ( ! isset( $sections[ $t ] ) && 'page' !== $t ) {
			$obj              = get_post_type_object( $t );
			$sections[ $t ]   = $obj ? $obj->labels->name : $t;
		}
	}
	foreach ( $sections as $type => $heading ) {
		if ( ! post_type_exists( $type ) || ! in_array( $type, flxseo_post_types(), true ) ) {
			continue;
		}
		$posts = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => 'post' === $type ? 15 : 10,
				'has_password'   => false,
				'no_found_rows'  => true,
			)
		);
		$rows = array();
		foreach ( $posts as $p ) {
			if ( $usable( $p ) ) {
				$rows[] = $line( get_the_title( $p ), get_permalink( $p ), flxseo_post_description( $p ) );
			}
		}
		if ( $rows ) {
			$out[] = '## ' . $heading;
			$out[] = '';
			$out   = array_merge( $out, $rows );
			$out[] = '';
		}
	}

	$out[] = '## ' . __( 'Optional', 'flx-seo' );
	$out[] = '';
	if ( function_exists( 'wp_sitemaps_get_server' ) && wp_sitemaps_get_server()->sitemaps_enabled() ) {
		$out[] = $line( __( 'Sitemap', 'flx-seo' ), home_url( '/wp-sitemap.xml' ), __( 'List of all public pages', 'flx-seo' ) );
	}
	$out[] = $line( __( 'RSS feed', 'flx-seo' ), get_feed_link(), __( 'Recent posts', 'flx-seo' ) );
	$text  = (string) apply_filters( 'flxseo_llms_txt', implode( "\n", $out ) . "\n" );
	set_transient( 'flxseo_llms', $text, 12 * HOUR_IN_SECONDS );
	return $text;
}

function flxseo_llms_flush() {
	delete_transient( 'flxseo_llms' );
}
add_action( 'save_post', 'flxseo_llms_flush' );
add_action( 'deleted_post', 'flxseo_llms_flush' );
add_action( 'wp_update_nav_menu', 'flxseo_llms_flush' );
add_action( 'update_option_flxseo', 'flxseo_llms_flush' );

add_action(
	'parse_request',
	function () {
		$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$base = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( untrailingslashit( $base ) . '/llms.txt' !== $path || ! flxseo_opt( 'llms' ) ) {
			return;
		}
		if ( ! get_option( 'blog_public' ) ) {
			status_header( 404 );
			exit;
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		header( 'X-Robots-Tag: noindex' );
		echo flxseo_llms_txt(); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	},
	0
);

/* ---------------------------------------------------------------------------
 * WebMCP (bildirim tabanlı): arama formu tarayıcı ajanlarına araç olarak tanıtılır.
 * ------------------------------------------------------------------------- */

add_filter(
	'get_search_form',
	function ( $form ) {
		if ( ! flxseo_opt( 'webmcp' ) || ! is_string( $form ) || false !== stripos( $form, 'toolname=' ) ) {
			return $form;
		}
		$attrs = sprintf( ' toolname="search_site" tooldescription="%s"', esc_attr__( 'Searches the site and opens the results page.', 'flx-seo' ) );
		$form  = preg_replace( '/<form\b/i', '<form' . $attrs, $form, 1 );
		return preg_replace( '/(<input\b[^>]*name=["\']s["\'])/i', '$1 toolparamdescription="' . esc_attr__( 'Words to search for', 'flx-seo' ) . '"', $form, 1 );
	},
	20
);

/* Blok temalarındaki arama bloğu (core/search) get_search_form kullanmaz. */
add_filter(
	'render_block_core/search',
	function ( $html ) {
		if ( ! flxseo_opt( 'webmcp' ) || false !== stripos( $html, 'toolname=' ) ) {
			return $html;
		}
		return preg_replace( '/<form\b/i', '<form toolname="search_site" tooldescription="' . esc_attr__( 'Searches the site and opens the results page.', 'flx-seo' ) . '"', $html, 1 );
	}
);

/* ---------------------------------------------------------------------------
 * Güvenlik başlıkları
 * ------------------------------------------------------------------------- */

function flxseo_hsts_value() {
	$v = 'max-age=31536000';
	if ( flxseo_opt( 'hsts_sub' ) || flxseo_opt( 'hsts_preload' ) ) {
		$v .= '; includeSubDomains';
	}
	if ( flxseo_opt( 'hsts_preload' ) ) {
		$v .= '; preload';
	}
	return $v;
}

add_action(
	'send_headers',
	function () {
		if ( headers_sent() || is_admin() || is_customize_preview() ) {
			return;
		}
		$sent = array_map( 'strtolower', array_map( fn( $h ) => strtok( $h, ':' ), headers_list() ) );
		$add  = array();
		if ( flxseo_opt( 'hdr_frame' ) ) {
			$add['x-frame-options'] = 'SAMEORIGIN';
		}
		if ( flxseo_opt( 'hdr_coop' ) ) {
			$add['cross-origin-opener-policy'] = 'same-origin-allow-popups';
		}
		if ( flxseo_opt( 'hdr_csp' ) ) {
			$csp = "object-src 'none'; base-uri 'self'";
			if ( flxseo_opt( 'hdr_frame' ) ) {
				$csp .= "; frame-ancestors 'self'";
			}
			if ( is_ssl() ) {
				$csp .= '; upgrade-insecure-requests';
			}
			$add['content-security-policy'] = (string) apply_filters( 'flxseo_csp', $csp );
		}
		foreach ( $add as $name => $value ) {
			if ( '' !== $value && ! in_array( $name, $sent, true ) ) {
				header( ucwords( $name, '-' ) . ': ' . $value );
			}
		}
	},
	11
);

/* Güvenlik başlıkları açıkken WordPress sürümü sayfada gösterilmez. */
add_action(
	'init',
	function () {
		if ( flxseo_opt( 'hdr_csp' ) || flxseo_opt( 'hdr_frame' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}
	}
);

/* ---------------------------------------------------------------------------
 * Tek tık düzeltmeler (Site denetimi düğmeleri ve MCP flxseo_system_fix)
 * ------------------------------------------------------------------------- */

/** Düzeltme kimliği → açtığı ayarlar. */
function flxseo_fix_map() {
	return array(
		'flxseo_llms'    => array( 'llms' ),
		'flxseo_webmcp'  => array( 'webmcp' ),
		'flxseo_headers' => array( 'hdr_frame', 'hdr_coop', 'hdr_csp' ),
		'flxseo_hsts'    => array( 'hsts_sub' ),
		'flxseo_indexnow' => array( 'indexnow' ),
	);
}

function flxseo_fix_is_on( $fix_id ) {
	$keys = flxseo_fix_map()[ $fix_id ] ?? array();
	if ( ! $keys ) {
		// Başka paketin düzeltmesi (ör. Flux Speed): kendi "current" denetimi.
		$f = flxseo_system_fixes()[ $fix_id ] ?? null;
		return $f && ! empty( $f['current'] ) && is_callable( $f['current'] ) && (bool) call_user_func( $f['current'] );
	}
	foreach ( $keys as $k ) {
		if ( ! flxseo_opt( $k ) ) {
			return false;
		}
	}
	return true;
}

/** Düzeltmeyi uygular; açılan ayarların listesini döner. Flux SEO dışındaki düzeltmeler flx_system_fixes üzerinden çalışır. */
function flxseo_fix_apply( $fix_id ) {
	$keys = flxseo_fix_map()[ $fix_id ] ?? null;
	if ( ! $keys ) {
		$f = flxseo_system_fixes()[ $fix_id ] ?? null;
		if ( ! $f ) {
			throw new Exception( __( 'Unknown fix.', 'flx-seo' ) );
		}
		call_user_func( $f['run'], null );
		return array( $fix_id );
	}
	$o = (array) get_option( 'flxseo', array() );
	foreach ( $keys as $k ) {
		$o[ $k ] = 1;
	}
	update_option( 'flxseo', $o );
	flxseo_llms_flush();
	return $keys;
}

add_filter(
	'flx_system_fixes',
	function ( $fixes ) {
		$labels = array(
			'flxseo_llms'    => array( __( 'Turn on llms.txt', 'flx-seo' ), __( 'Flux SEO builds /llms.txt from the site name, description, menu and recent content.', 'flx-seo' ) ),
			'flxseo_webmcp'  => array( __( 'Turn on the WebMCP search tool', 'flx-seo' ), __( 'Adds toolname/tooldescription to the search form so browser agents see it as a tool.', 'flx-seo' ) ),
			'flxseo_headers' => array( __( 'Turn on security headers', 'flx-seo' ), __( 'X-Frame-Options: SAMEORIGIN, COOP same-origin-allow-popups and a basic CSP (object-src \'none\'; base-uri \'self\'; frame-ancestors \'self\'). Do not turn on if the site is embedded in other sites with an iframe.', 'flx-seo' ) ),
			'flxseo_hsts'    => array( __( 'HSTS includeSubDomains', 'flx-seo' ), __( 'If every subdomain is HTTPS, HSTS also covers subdomains.', 'flx-seo' ) ),
			'flxseo_indexnow' => array( __( 'Turn on IndexNow', 'flx-seo' ), __( 'New and updated pages are sent instantly to Bing, Yandex, Seznam and Naver; the whole site is submitted once automatically.', 'flx-seo' ) ),
		);
		foreach ( $labels as $id => $l ) {
			$fixes[ $id ] = array(
				'label'       => $l[0],
				'description' => $l[1],
				'checks'      => array( 'flxseo.audit' ),
				'source'      => 'Flux SEO',
				'value'       => null,
				'current'     => fn() => flxseo_fix_is_on( $id ),
				'run'         => function () use ( $id ) {
					flxseo_fix_apply( $id );
					return __( 'Turned on. Run the site audit again.', 'flx-seo' );
				},
			);
		}
		return $fixes;
	}
);

/* Ön ayar şeması (Flux Setup): flxseo option alanları. */
add_filter(
	'flx_settings_schema',
	function ( $s ) {
		$fields = array();
		foreach ( flxseo_defaults() as $k => $v ) {
			$fields[ $k ] = is_int( $v ) ? array( 'type' => 'bool', 'default' => $v ) : array( 'type' => in_array( $k, array( 'home_desc', 'same_as', 'llms_intro' ), true ) ? 'text' : 'string', 'default' => $v );
		}
		$fields['org_type']['type'] = 'enum';
		$fields['org_type']['enum'] = array( 'NGO', 'Organization' );
		$fields['audit_freq']       = array( 'type' => 'enum', 'enum' => array( 'daily', 'weekly', 'off' ), 'default' => 'daily' );
		$fields['audit_day']        = array( 'type' => 'enum', 'enum' => array( '1', '2', '3', '4', '5', '6', '7' ), 'default' => '1' );
		$fields['audit_hour']       = array( 'type' => 'enum', 'enum' => array_map( 'strval', range( 0, 23 ) ), 'default' => '3' );
		$s['flxseo'] = array(
			'label'  => 'Flux SEO',
			'fields' => $fields,
		);
		return $s;
	}
);
