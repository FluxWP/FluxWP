<?php
/**
 * <head> çıktısı: başlık, açıklama, canonical, robots, Open Graph, Twitter, doğrulama.
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function flxseo_clean( $text, $len = 160 ) {
	$text = wp_strip_all_tags( strip_shortcodes( (string) $text ), true );
	$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
	if ( mb_strlen( $text ) <= $len ) {
		return $text;
	}
	$cut   = mb_substr( $text, 0, $len - 1 );
	$space = mb_strrpos( $cut, ' ' );
	return rtrim( $space > $len * 0.6 ? mb_substr( $cut, 0, $space ) : $cut, " ,.;:–-" ) . '…';
}

/**
 * Sayfa için otomatik açıklama: SEO alanı › özet › içeriğin başı.
 */
function flxseo_post_description( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$d = flxseo_get( $post->ID, 'desc' );
	if ( $d ) {
		return flxseo_clean( $d, 320 );
	}
	if ( has_excerpt( $post ) ) {
		return flxseo_clean( $post->post_excerpt );
	}
	// Başlık bloklarını atlayıp paragraflardan özet çıkar.
	$text = '';
	// Kısa paragraflar (etiket, rozet, adres satırı) atlanır.
	if ( preg_match_all( '#<p[^>]*>(.*?)</p>#is', $post->post_content, $m ) ) {
		$long = array_filter( $m[1], fn( $p ) => mb_strlen( trim( wp_strip_all_tags( $p ) ) ) >= 40 );
		$text = implode( ' ', $long ? $long : $m[1] );
	}
	return flxseo_clean( $text ? $text : $post->post_content );
}

function flxseo_description() {
	if ( is_front_page() ) {
		$id = (int) get_option( 'page_on_front' );
		return ( $id && flxseo_get( $id, 'desc' ) ) ? flxseo_clean( flxseo_get( $id, 'desc' ), 320 ) : flxseo_opt( 'home_desc' );
	}
	if ( is_home() ) {
		$id = (int) get_option( 'page_for_posts' );
		return $id ? flxseo_post_description( $id ) : flxseo_opt( 'home_desc' );
	}
	if ( is_singular() ) {
		return flxseo_post_description( get_queried_object() );
	}
	if ( is_category() || is_tag() || is_tax() ) {
		$t = get_queried_object();
		$d = get_term_meta( $t->term_id, '_flxseo_desc', true );
		return flxseo_clean( $d ? $d : ( $t->description ? $t->description : sprintf( '%s: %s içerikleri.', flxseo_opt( 'alt_name' ), $t->name ) ), 320 );
	}
	if ( is_post_type_archive() ) {
		$pto = get_queried_object();
		return flxseo_clean( apply_filters( 'flxseo_archive_desc', $pto && ! empty( $pto->description ) ? $pto->description : sprintf( '%s: %s.', flxseo_opt( 'alt_name' ), post_type_archive_title( '', false ) ), get_query_var( 'post_type' ) ) );
	}
	return flxseo_clean( get_bloginfo( 'description' ) );
}

/**
 * <title>.
 */
add_filter(
	'pre_get_document_title',
	function ( $title ) {
		if ( ! flxseo_output_enabled() ) {
			return $title;
		}
		if ( is_front_page() ) {
			$id = (int) get_option( 'page_on_front' );
			return ( $id && flxseo_get( $id, 'title' ) ) ? flxseo_get( $id, 'title' ) : flxseo_opt( 'home_title' );
		}
		if ( is_singular() && flxseo_get( get_queried_object_id(), 'title' ) ) {
			return flxseo_get( get_queried_object_id(), 'title' );
		}
		if ( ( is_category() || is_tag() || is_tax() ) && get_term_meta( get_queried_object_id(), '_flxseo_title', true ) ) {
			return get_term_meta( get_queried_object_id(), '_flxseo_title', true );
		}
		return $title;
	},
	20
);

add_filter(
	'document_title_separator',
	function ( $sep ) {
		return flxseo_output_enabled() ? flxseo_opt( 'sep' ) : $sep;
	}
);

add_filter(
	'document_title_parts',
	function ( $parts ) {
		if ( flxseo_output_enabled() ) {
			unset( $parts['tagline'] );
			if ( isset( $parts['site'] ) ) {
				$parts['site'] = flxseo_opt( 'alt_name' );
			}
		}
		return $parts;
	}
);

function flxseo_canonical() {
	if ( is_404() || is_search() ) {
		return '';
	}
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( is_singular() ) {
		$url = wp_get_canonical_url( get_queried_object_id() );
		return $url ? $url : '';
	} elseif ( is_home() ) {
		$id  = (int) get_option( 'page_for_posts' );
		$url = $id ? get_permalink( $id ) : home_url( '/' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$url = get_term_link( get_queried_object() );
	} elseif ( is_post_type_archive() ) {
		$url = get_post_type_archive_link( get_query_var( 'post_type' ) );
	} else {
		return '';
	}
	if ( is_wp_error( $url ) || ! $url ) {
		return '';
	}
	return $paged > 1 ? get_pagenum_link( $paged ) : $url;
}

function flxseo_is_shop_private() {
	if ( ! function_exists( 'is_cart' ) ) {
		return false;
	}
	return is_cart() || is_checkout() || is_account_page();
}

function flxseo_noindex() {
	if ( is_singular() && flxseo_get( get_queried_object_id(), 'noindex' ) ) {
		return true;
	}
	if ( flxseo_opt( 'noindex_search' ) && is_search() ) {
		return true;
	}
	if ( flxseo_opt( 'noindex_shop' ) && flxseo_is_shop_private() ) {
		return true;
	}
	if ( flxseo_opt( 'noindex_tags' ) && is_tag() ) {
		return true;
	}
	return is_404();
}

add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( ! flxseo_output_enabled() ) {
			return $robots;
		}
		if ( flxseo_noindex() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		} elseif ( get_option( 'blog_public' ) ) {
			$robots['max-image-preview'] = 'large';
			$robots['max-snippet']       = '-1';
			$robots['max-video-preview'] = '-1';
		}
		return $robots;
	}
);

/**
 * Sayfanın paylaşım görseli: SEO görseli › öne çıkan › içerikteki ilk görsel › varsayılan.
 *
 * @return array url, width, height, alt
 */
function flxseo_image( $post = null ) {
	$post = $post ? get_post( $post ) : ( is_singular() ? get_queried_object() : null );
	if ( $post ) {
		$custom = flxseo_get( $post->ID, 'image' );
		if ( $custom ) {
			return array( 'url' => $custom, 'width' => 0, 'height' => 0, 'alt' => '' );
		}
		$id = get_post_thumbnail_id( $post );
		if ( ! $id && preg_match( '/wp-image-(\d+)/', $post->post_content, $m ) ) {
			$id = (int) $m[1];
		}
		if ( $id ) {
			$src = wp_get_attachment_image_src( $id, 'large' );
			if ( $src ) {
				return array( 'id' => (int) $id, 'url' => $src[0], 'width' => $src[1], 'height' => $src[2], 'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ) );
			}
		}
		if ( preg_match( '/<img[^>]+src="([^"]+)"/i', $post->post_content, $m ) ) {
			return array( 'url' => $m[1], 'width' => 0, 'height' => 0, 'alt' => '' );
		}
	}
	return array( 'url' => flxseo_default_image(), 'width' => 1200, 'height' => 630, 'alt' => flxseo_opt( 'site_name' ) );
}

function flxseo_og_locale() {
	$l = determine_locale();
	return false === strpos( $l, '_' ) ? $l . '_' . strtoupper( $l ) : $l;
}

/**
 * Meta etiketleri.
 */
function flxseo_head() {
	$v = array(
		'google-site-verification' => flxseo_opt( 'verify_google' ),
		'msvalidate.01'            => flxseo_opt( 'verify_bing' ),
		'yandex-verification'      => flxseo_opt( 'verify_yandex' ),
		'naver-site-verification'  => flxseo_opt( 'verify_naver' ),
		'seznam-wmt'               => flxseo_opt( 'verify_seznam' ),
		'baidu-site-verification'  => flxseo_opt( 'verify_baidu' ),
	);
	foreach ( $v as $name => $content ) {
		// Kaydedilirken sanitize çalışmadıysa (ör. yönetim dışından yazıldıysa) tam etiketten content alınır.
		if ( $content && preg_match( '/content=["\']([^"\']+)["\']/', $content, $m ) ) {
			$content = $m[1];
		}
		if ( $content ) {
			printf( '<meta name="%s" content="%s">' . "\n", esc_attr( $name ), esc_attr( $content ) );
		}
	}
	if ( ! flxseo_output_enabled() ) {
		return;
	}
	$desc  = flxseo_description();
	$canon = flxseo_canonical();
	$title = wp_get_document_title();
	$img   = flxseo_og_image( flxseo_image() );
	$type  = is_singular( flxseo_article_types() ) ? 'article' : 'website';

	echo "<!-- Flux SEO -->\n";
	if ( $desc && ! is_404() ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
	}
	printf( '<meta name="author" content="%s">' . "\n", esc_attr( flxseo_opt( 'org_name' ) ? flxseo_opt( 'org_name' ) : flxseo_opt( 'site_name' ) ) );
	printf( '<meta name="publisher" content="%s">' . "\n", esc_attr( flxseo_opt( 'org_name' ) ? flxseo_opt( 'org_name' ) : flxseo_opt( 'site_name' ) ) );
	if ( $canon ) {
		printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $canon ) );
	}
	$og = array(
		'og:locale'      => flxseo_og_locale(),
		'og:type'        => $type,
		'og:site_name'   => flxseo_opt( 'site_name' ),
		'og:title'       => is_front_page() ? $title : ( is_singular() ? single_post_title( '', false ) : $title ),
		'og:description' => $desc,
		'og:url'         => $canon ? $canon : home_url( add_query_arg( array() ) ),
		'og:image'       => $img['url'],
	);
	if ( 0 === strpos( $img['url'], 'https://' ) ) {
		$og['og:image:secure_url'] = $img['url'];
	}
	if ( ! empty( $img['type'] ) ) {
		$og['og:image:type'] = $img['type'];
	}
	if ( $img['width'] ) {
		$og['og:image:width']  = $img['width'];
		$og['og:image:height'] = $img['height'];
	}
	if ( $img['alt'] ) {
		$og['og:image:alt'] = $img['alt'];
	}
	if ( 'article' === $type ) {
		$og['article:published_time'] = get_the_date( 'c', get_queried_object() );
		$og['article:modified_time']  = get_the_modified_date( 'c', get_queried_object() );
	}
	/** Open Graph etiketleri: array( 'og:title' => '...', ... ). Boş değer yazılmaz. */
	$og = (array) apply_filters( 'flxseo_og', $og );
	foreach ( $og as $p => $c ) {
		if ( '' !== (string) $c ) {
			printf( '<meta property="%s" content="%s">' . "\n", esc_attr( $p ), esc_attr( $c ) );
		}
	}
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	if ( flxseo_opt( 'twitter' ) ) {
		printf( '<meta name="twitter:site" content="%s">' . "\n", esc_attr( '@' . ltrim( flxseo_opt( 'twitter' ), '@' ) ) );
	}
	echo "<!-- /Flux SEO -->\n";
}
add_action( 'wp_head', 'flxseo_head', 3 );

add_action(
	'wp',
	function () {
		if ( flxseo_output_enabled() ) {
			remove_action( 'wp_head', 'rel_canonical' );
		}
	}
);

/**
 * Yazar arşivleri: tek yazarlı dernek sitesinde ince içerik; ana sayfaya yönlenir.
 */
add_action(
	'template_redirect',
	function () {
		if ( flxseo_opt( 'noindex_author' ) && is_author() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}
);

/**
 * Alt metni boş görseller: medya kütüphanesindeki alt metin, yoksa görsel başlığı.
 */
add_filter(
	'render_block_core/image',
	function ( $html, $block ) {
		if ( ! flxseo_opt( 'auto_alt' ) || false === strpos( $html, 'alt=""' ) ) {
			return $html;
		}
		$id  = (int) ( $block['attrs']['id'] ?? 0 );
		$alt = $id ? get_post_meta( $id, '_wp_attachment_image_alt', true ) : '';
		if ( ! $alt && $id ) {
			$alt = get_the_title( $id );
		}
		return $alt ? preg_replace( '/alt=""/', 'alt="' . esc_attr( $alt ) . '"', $html, 1 ) : $html;
	},
	10,
	2
);

/**
 * Güvenlik başlıkları: HSTS (yalnızca HTTPS), MIME sniffing koruması, referrer politikası.
 * Sunucu zaten gönderiyorsa tekrar eklenmez.
 */
add_action(
	'send_headers',
	function () {
		if ( headers_sent() ) {
			return;
		}
		$sent = array_map( 'strtolower', array_map( fn( $h ) => strtok( $h, ':' ), headers_list() ) );
		$add  = array(
			'x-content-type-options' => 'nosniff',
			'referrer-policy'        => 'strict-origin-when-cross-origin',
		);
		if ( is_ssl() ) {
			$add['strict-transport-security'] = flxseo_hsts_value();
		}
		foreach ( $add as $name => $value ) {
			if ( ! in_array( $name, $sent, true ) ) {
				header( ucwords( $name, '-' ) . ': ' . $value );
			}
		}
	}
);

/**
 * RSS/Atom beslemeleri (/feed/, /yazi/feed/, /category/x/feed/): okuyucular için açık kalır,
 * arama motoruna "dizine ekleme, linkleri izle" denir (Search Console'da "Tarandı - dizine eklenmedi" çıkmasın).
 */
add_action(
	'template_redirect',
	function () {
		if ( is_feed() && ! headers_sent() && get_option( 'blog_public' ) ) {
			header( 'X-Robots-Tag: noindex, follow', true );
		}
	},
	1
);
