<?php
/**
 * WordPress'in kendi sitemap'i (/wp-sitemap.xml) için düzenlemeler:
 * noindex içerik, mağaza özel sayfaları, yazar ve (ayar açıksa) etiket sitemap'i çıkarılır; lastmod eklenir.
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'wp_sitemaps_add_provider',
	function ( $provider, $name ) {
		return ( 'users' === $name && flxseo_opt( 'noindex_author' ) ) ? false : $provider;
	},
	10,
	2
);

add_filter(
	'wp_sitemaps_post_types',
	function ( $types ) {
		unset( $types['attachment'] );
		return $types;
	}
);

add_filter(
	'wp_sitemaps_taxonomies',
	function ( $tax ) {
		unset( $tax['post_format'], $tax['product_shipping_class'] );
		if ( flxseo_opt( 'noindex_tags' ) ) {
			unset( $tax['post_tag'], $tax['product_tag'] );
		}
		return $tax;
	}
);

add_filter(
	'wp_sitemaps_posts_query_args',
	function ( $args ) {
		$exclude = array();
		if ( flxseo_opt( 'noindex_shop' ) && function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount' ) as $p ) {
				$id = wc_get_page_id( $p );
				if ( $id > 0 ) {
					$exclude[] = $id;
				}
			}
		}
		$args['post__not_in'] = array_merge( $args['post__not_in'] ?? array(), $exclude );
		$args['meta_query']   = array(
			'relation' => 'OR',
			array(
				'key'     => '_flxseo_noindex',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'   => '_flxseo_noindex',
				'value' => '1',
				'compare' => '!=',
			),
		);
		return $args;
	}
);

add_filter(
	'wp_sitemaps_posts_entry',
	function ( $entry, $post ) {
		$entry['lastmod'] = get_post_modified_time( 'c', true, $post );
		return $entry;
	},
	10,
	2
);

/**
 * /sitemap.xml ve /sitemap_index.xml → /wp-sitemap.xml (alışılmış adresler 404 vermesin).
 */
add_action(
	'template_redirect',
	function () {
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( is_404() && in_array( $path, array( 'sitemap.xml', 'sitemap_index.xml' ), true ) ) {
			wp_safe_redirect( home_url( '/wp-sitemap.xml' ), 301 );
			exit;
		}
	},
	1
);

/**
 * robots.txt: yönetim, sepet ve arama sayfaları taranmasın; sitemap satırı WordPress tarafından eklenir.
 */
add_filter(
	'robots_txt',
	function ( $out, $public ) {
		if ( ! $public ) {
			return $out;
		}
		$lines = array( 'Disallow: /?s=', 'Disallow: /search/', 'Disallow: /*?add-to-cart=' );
		foreach ( $lines as $l ) {
			if ( false === strpos( $out, $l ) ) {
				$out = preg_replace( '/(Allow: \/wp-admin\/admin-ajax\.php\n)/', "$1$l\n", $out, 1 );
			}
		}
		// Sitemap satırı yoksa (ör. başka bir eklenti kaldırdıysa) eklenir; Flux Lang'in dil sitemap'leri wp-sitemap.xml dizinindedir.
		if ( false === stripos( $out, 'Sitemap:' ) && wp_sitemaps_get_server()->sitemaps_enabled() ) {
			$out = rtrim( $out ) . "\n\nSitemap: " . home_url( '/wp-sitemap.xml' ) . "\n";
		}
		return $out;
	},
	20,
	2
);

/**
 * XSL görünüm katmanı kaldırılır: Chrome XSLT desteğini kaldırıyor ("This site uses XSLT…" uyarısı)
 * ve arama motorları buna ihtiyaç duymaz. Sitemap düz XML olarak sunulur.
 */
add_filter( 'wp_sitemaps_stylesheet_url', '__return_false' );
add_filter( 'wp_sitemaps_stylesheet_index_url', '__return_false' );
