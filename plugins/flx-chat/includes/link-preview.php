<?php
/**
 * Link önizlemesi: adresin og:title / og:description / og:image bilgisi.
 * WhatsApp'taki kartın aynısını mesaj kutusunda ve mesajlarda göstermek için.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

function flxc_rest_link_preview( WP_REST_Request $req ) {
	$url = flxc_lp_clean_url( (string) $req->get_param( 'url' ) );
	if ( ! $url ) {
		return new WP_Error( 'flxc_url', __( 'Geçersiz adres.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	$res = rest_ensure_response( flxc_link_preview( $url, (bool) $req->get_param( 'fresh' ) ) );
	$res->header( 'Cache-Control', 'private, max-age=600' );
	return $res;
}

function flxc_lp_clean_url( $url ) {
	$url = trim( $url );
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		$url = 'https://' . $url;
	}
	$url = esc_url_raw( $url, array( 'http', 'https' ) );
	if ( ! $url || strlen( $url ) > 2000 || ! wp_http_validate_url( $url ) ) {
		return '';
	}
	return $url;
}

/**
 * @param string $url
 * @param bool   $fresh önbelleği atla (önizlemesi henüz hazır olmayan sayfa için)
 * @return array ok, url, title, desc, image, site, domain
 */
function flxc_link_preview( $url, $fresh = false ) {
	$key  = 'flxc_lp_' . md5( $url );
	$hit  = $fresh ? false : get_transient( $key );
	if ( is_array( $hit ) ) {
		return $hit;
	}
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	$out  = array(
		'ok'     => false,
		'url'    => $url,
		'title'  => '',
		'desc'   => '',
		'image'  => '',
		'site'   => '',
		'domain' => preg_replace( '/^www\./i', '', $host ),
	);
	$r = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 6,
			'redirection'         => 4,
			'limit_response_size' => 600 * KB_IN_BYTES,
			// Birçok site OG etiketlerini yalnız bilinen önizleme botlarına tam verir.
			'user-agent'          => 'WhatsApp/2.24 (compatible; FluxWP-Cagri/' . FLXC_VERSION . ')',
			'headers'             => array( 'Accept' => 'text/html,application/xhtml+xml', 'Accept-Language' => str_replace( '_', '-', get_user_locale() ) . ',en;q=0.5' ),
		)
	);
	$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
	if ( $code >= 200 && $code < 300 ) {
		$type = (string) wp_remote_retrieve_header( $r, 'content-type' );
		if ( 0 === strpos( $type, 'image/' ) ) {
			$out['image'] = $url;
			$out['ok']    = true;
		} elseif ( '' === $type || false !== stripos( $type, 'html' ) ) {
			$out = array_merge( $out, flxc_lp_parse( (string) wp_remote_retrieve_body( $r ), $url ) );
			$out['ok'] = '' !== $out['title'] || '' !== $out['image'];
		}
	}
	$out = apply_filters( 'flxc_link_preview', $out, $url );
	// Görseli olmayan sayfa (ör. sertifika görseli ilk açılışta üretilir) kısa süre önbellekte kalır.
	$ttl = $out['ok'] ? ( '' !== $out['image'] ? WEEK_IN_SECONDS : 30 * MINUTE_IN_SECONDS ) : HOUR_IN_SECONDS;
	set_transient( $key, $out, $ttl );
	return $out;
}

function flxc_lp_parse( $html, $base ) {
	$head = $html;
	$pos  = stripos( $html, '</head>' );
	if ( false !== $pos ) {
		$head = substr( $html, 0, $pos );
	}
	if ( ! preg_match( '/<meta[^>]+charset=["\']?\s*utf-?8/i', $head ) && preg_match( '/<meta[^>]+charset=["\']?\s*([a-z0-9_-]+)/i', $head, $cm ) && function_exists( 'mb_convert_encoding' ) ) {
		$conv = @mb_convert_encoding( $head, 'UTF-8', $cm[1] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( $conv ) {
			$head = $conv;
		}
	}
	$meta = array();
	if ( preg_match_all( '/<meta\s[^>]*>/is', $head, $tags ) ) {
		foreach ( $tags[0] as $tag ) {
			$attr = array();
			if ( preg_match_all( '/([a-z:_-]+)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $am, PREG_SET_ORDER ) ) {
				foreach ( $am as $a ) {
					$attr[ strtolower( $a[1] ) ] = isset( $a[5] ) ? $a[5] : ( isset( $a[4] ) && '' !== $a[4] ? $a[4] : $a[3] );
				}
			}
			$name = strtolower( $attr['property'] ?? ( $attr['name'] ?? '' ) );
			if ( '' !== $name && isset( $attr['content'] ) && ! isset( $meta[ $name ] ) ) {
				$meta[ $name ] = html_entity_decode( $attr['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}
	}
	$title = $meta['og:title'] ?? ( $meta['twitter:title'] ?? '' );
	if ( '' === $title && preg_match( '/<title[^>]*>(.*?)<\/title>/is', $head, $tm ) ) {
		$title = html_entity_decode( $tm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
	$image = $meta['og:image:secure_url'] ?? ( $meta['og:image'] ?? ( $meta['og:image:url'] ?? ( $meta['twitter:image'] ?? ( $meta['twitter:image:src'] ?? '' ) ) ) );
	$image = '' !== $image ? flxc_lp_abs( trim( $image ), $base ) : '';
	$clean = function ( $s, $max ) {
		$s = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $s ) ) );
		return mb_strlen( $s ) > $max ? rtrim( mb_substr( $s, 0, $max - 1 ) ) . '…' : $s;
	};
	return array(
		'title' => $clean( $title, 160 ),
		'desc'  => $clean( $meta['og:description'] ?? ( $meta['description'] ?? ( $meta['twitter:description'] ?? '' ) ), 260 ),
		'image' => preg_match( '#^https?://#i', $image ) ? esc_url_raw( $image ) : '',
		'site'  => $clean( $meta['og:site_name'] ?? '', 80 ),
	);
}

function flxc_lp_abs( $src, $base ) {
	if ( preg_match( '#^https?://#i', $src ) ) {
		return $src;
	}
	$b = wp_parse_url( $base );
	if ( empty( $b['host'] ) ) {
		return '';
	}
	$origin = ( $b['scheme'] ?? 'https' ) . '://' . $b['host'] . ( isset( $b['port'] ) ? ':' . $b['port'] : '' );
	if ( 0 === strpos( $src, '//' ) ) {
		return ( $b['scheme'] ?? 'https' ) . ':' . $src;
	}
	if ( 0 === strpos( $src, '/' ) ) {
		return $origin . $src;
	}
	$dir = isset( $b['path'] ) ? preg_replace( '#/[^/]*$#', '/', $b['path'] ) : '/';
	return $origin . $dir . $src;
}
