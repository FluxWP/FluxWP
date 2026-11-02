<?php
/**
 * Bloklardaki site içi bağlantılar: İngilizce sayfada "/hakkimizda/" bağlantısı İngilizce karşılığa
 * (yoksa yedek gösterimde /en/hakkimizda/) gider. Menü (core/navigation-link), düğme ve metin
 * bağlantıları için; tema koduna dokunmadan çalışır.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

/**
 * Site içi adresi istek dilindeki karşılığına çevirir.
 */
function flxl_translate_url( $url, $code = '' ) {
	static $memo = array();
	$code = $code ? $code : flxl_current_language();
	$url  = (string) $url;
	$key  = $code . '|' . $url;
	if ( isset( $memo[ $key ] ) ) {
		return $memo[ $key ];
	}
	$out = $url;
	$rel = '/' === substr( $url, 0, 1 ) && '//' !== substr( $url, 0, 2 );
	if ( ! $rel && ! flxl_is_internal( $url ) ) {
		return $memo[ $key ] = $url; // Site dışı.
	}
	$abs = flxl_url_for_language( $url, flxl_default_language() );
	$path = (string) wp_parse_url( $abs, PHP_URL_PATH );
	$base = flxl_root_path();
	if ( '' !== $base && 0 === strpos( $path, $base ) ) {
		$path = substr( $path, strlen( $base ) );
	}
	if ( flxl_path_is_neutral( $path ) ) {
		return $memo[ $key ] = $url;
	}
	$full = $rel ? flxl_root() . substr( $abs, strlen( $base ) ) : $abs;
	$id   = flxl_raw(
		function () use ( $full ) {
			return url_to_postid( $full );
		}
	);
	if ( $id ) {
		$target = flxl_get_translation( $id, $code, true );
		$link   = get_permalink( $target ? $target : $id );
		if ( $link ) {
			$tail = '';
			$q    = wp_parse_url( $url, PHP_URL_QUERY );
			$f    = wp_parse_url( $url, PHP_URL_FRAGMENT );
			if ( $q ) {
				$tail .= '?' . $q;
			}
			if ( $f ) {
				$tail .= '#' . $f;
			}
			$out = $link . $tail;
			if ( $rel ) {
				$out = (string) wp_make_link_relative( $out );
			}
		}
	} else {
		$out = flxl_url_for_language( $url, $code );
	}
	return $memo[ $key ] = $out;
}

/**
 * Menü bağlantıları (attribute içinde).
 */
add_filter(
	'render_block_data',
	function ( $block ) {
		if ( flxl_current_language() === flxl_default_language() || flxl_is_backend() ) {
			return $block;
		}
		if ( in_array( $block['blockName'] ?? '', array( 'core/navigation-link', 'core/navigation-submenu', 'core/home-link' ), true ) && ! empty( $block['attrs']['url'] ) ) {
			$block['attrs']['url'] = flxl_translate_url( $block['attrs']['url'] );
		}
		return $block;
	},
	20
);

/**
 * Yaprak blokların HTML'indeki href'ler.
 */
add_filter(
	'render_block',
	function ( $html, $block ) {
		static $containers = array( 'core/template-part', 'core/post-content', 'core/block', 'core/pattern', 'core/navigation', 'core/query', 'core/post-template' );
		if ( '' === $html || ! empty( $block['innerBlocks'] ) || false === strpos( $html, 'href=' ) || in_array( $block['blockName'] ?? '', $containers, true ) ) {
			return $html;
		}
		if ( flxl_current_language() === flxl_default_language() || flxl_is_backend() ) {
			return $html;
		}
		if ( in_array( $block['blockName'] ?? '', (array) apply_filters( 'flxl_skip_link_blocks', array( 'flx-lang/secici', 'fluxwp/dil-secici' ) ), true ) ) {
			return $html;
		}
		$host = preg_quote( (string) preg_replace( '#^https?:#', '', flxl_root() ), '#' );
		return preg_replace_callback(
			'#<a\s[^>]*>#i',
			function ( $tag ) use ( $host ) {
				// Dil seçici ve hreflang'lı bağlantılar bilerek başka dile gider.
				if ( false !== stripos( $tag[0], 'hreflang=' ) || false !== stripos( $tag[0], 'data-flxl' ) ) {
					return $tag[0];
				}
				return preg_replace_callback(
					'#\bhref=(["\'])((?:https?:' . $host . ')?/[^"\'\s]*)\1#i',
					function ( $m ) {
						if ( 0 === strpos( $m[2], '//' ) && ! flxl_is_internal( $m[2] ) ) {
							return $m[0];
						}
						if ( flxl_url_language( $m[2] ) !== flxl_default_language() ) {
							return $m[0]; // Zaten önekli.
						}
						return 'href=' . $m[1] . esc_url( flxl_translate_url( $m[2] ) ) . $m[1];
					},
					$tag[0]
				);
			},
			$html
		);
	},
	20,
	2
);
