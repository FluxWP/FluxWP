<?php
/**
 * CSS araçları: yorum/boşluk sıkıştırma, seçici envanteri ve sayfada kullanılmayan kuralları kırpma.
 * WordPress'e bağlı değil (saf fonksiyonlar); testler tools/ altından doğrudan çağırır.
 *
 * Kırpma tutucudur: bir kural yalnız seçici listesindeki HER seçici sayfada olmayan bir sınıf/id
 * istiyorsa düşer. Parantez içindeki (:not, :is, :where, :has) belirteçler hesaba katılmaz, @font-face,
 * @keyframes gibi bloklar olduğu gibi kalır, @media/@supports/@layer/@container içine inilir.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

/**
 * Yorumları siler (dizgilere dokunmaz).
 *
 * @param string $css CSS.
 * @return string
 */
function flxp_css_strip_comments( $css ) {
	$out = '';
	$n   = strlen( $css );
	for ( $i = 0; $i < $n; $i++ ) {
		$ch = $css[ $i ];
		if ( '"' === $ch || "'" === $ch ) {
			$j = flxp_css_string_end( $css, $i );
			$out .= substr( $css, $i, $j - $i + 1 );
			$i    = $j;
			continue;
		}
		if ( '/' === $ch && $i + 1 < $n && '*' === $css[ $i + 1 ] ) {
			$end = strpos( $css, '*/', $i + 2 );
			$i   = false === $end ? $n : $end + 1;
			continue;
		}
		$out .= $ch;
	}
	return $out;
}

/** Dizginin kapanış tırnağının konumu (kaçışlar atlanır). */
function flxp_css_string_end( $css, $i ) {
	$q = $css[ $i ];
	$n = strlen( $css );
	for ( $j = $i + 1; $j < $n; $j++ ) {
		if ( '\\' === $css[ $j ] ) {
			++$j;
			continue;
		}
		if ( $q === $css[ $j ] || "\n" === $css[ $j ] ) {
			return $j;
		}
	}
	return $n - 1;
}

/**
 * Güvenli sıkıştırma: yorumlar, boşluk dizileri, { } ; , > çevresindeki boşluklar ve son ";".
 * + - * / ve : çevresine dokunulmaz (calc() ve "a :hover" anlamı değişmesin).
 *
 * @param string $css CSS.
 * @return string
 */
function flxp_css_minify( $css ) {
	$css = flxp_css_strip_comments( $css );
	$out = '';
	$n   = strlen( $css );
	$ws  = false;
	for ( $i = 0; $i < $n; $i++ ) {
		$ch = $css[ $i ];
		if ( '"' === $ch || "'" === $ch ) {
			if ( $ws && '' !== $out && ! flxp_css_tight( substr( $out, -1 ) ) ) {
				$out .= ' ';
			}
			$ws   = false;
			$j    = flxp_css_string_end( $css, $i );
			$out .= substr( $css, $i, $j - $i + 1 );
			$i    = $j;
			continue;
		}
		if ( ctype_space( $ch ) ) {
			$ws = true;
			continue;
		}
		if ( $ws ) {
			$ws = false;
			$prev = substr( $out, -1 );
			if ( '' !== $out && ! flxp_css_tight( $prev ) && ! flxp_css_tight( $ch ) && '(' !== $prev && ')' !== $ch ) {
				$out .= ' ';
			}
		}
		if ( '}' === $ch && ';' === substr( $out, -1 ) ) {
			$out = substr( $out, 0, -1 );
		}
		$out .= $ch;
	}
	return trim( $out );
}

/** Çevresindeki boşluk atılabilen karakterler. */
function flxp_css_tight( $ch ) {
	return false !== strpos( '{};,>', $ch );
}

/**
 * Belirli bir konumdan başlayarak dengeli kapanış ayracını bulur.
 *
 * @param string $css  CSS (yorumsuz).
 * @param int    $open "{" konumu.
 * @return int "}" konumu (bulunamazsa uzunluk - 1).
 */
function flxp_css_block_end( $css, $open ) {
	$n     = strlen( $css );
	$depth = 0;
	for ( $i = $open; $i < $n; $i++ ) {
		$ch = $css[ $i ];
		if ( '"' === $ch || "'" === $ch ) {
			$i = flxp_css_string_end( $css, $i );
			continue;
		}
		if ( '{' === $ch ) {
			++$depth;
		} elseif ( '}' === $ch ) {
			--$depth;
			if ( 0 === $depth ) {
				return $i;
			}
		}
	}
	return $n - 1;
}

/**
 * CSS'i üst düzey parçalara böler: array( 'prelude' => …, 'body' => … | null ).
 * body null ise ";" ile biten deyimdir (@import, @charset, @layer a,b;).
 *
 * @param string $css Yorumsuz CSS.
 * @return array
 */
function flxp_css_parse( $css ) {
	$out   = array();
	$n     = strlen( $css );
	$start = 0;
	$paren = 0;
	for ( $i = 0; $i < $n; $i++ ) {
		$ch = $css[ $i ];
		if ( '"' === $ch || "'" === $ch ) {
			$i = flxp_css_string_end( $css, $i );
			continue;
		}
		if ( '(' === $ch ) {
			++$paren;
		} elseif ( ')' === $ch ) {
			$paren = max( 0, $paren - 1 );
		} elseif ( ';' === $ch && 0 === $paren ) {
			$pre = trim( substr( $css, $start, $i - $start ) );
			if ( '' !== $pre ) {
				$out[] = array(
					'prelude' => $pre,
					'body'    => null,
				);
			}
			$start = $i + 1;
		} elseif ( '{' === $ch && 0 === $paren ) {
			$end   = flxp_css_block_end( $css, $i );
			$out[] = array(
				'prelude' => trim( substr( $css, $start, $i - $start ) ),
				'body'    => substr( $css, $i + 1, $end - $i - 1 ),
			);
			$i     = $end;
			$start = $end + 1;
		} elseif ( '}' === $ch ) {
			$start = $i + 1; // Fazladan kapanış: yok say.
		}
	}
	return $out;
}

/** İçine inilen grup kuralları. */
function flxp_css_is_group( $prelude ) {
	return (bool) preg_match( '/^@(?:-[a-z]+-)?(?:media|supports|layer|container|document|scope|starting-style)\b/i', $prelude );
}

/**
 * Bir seçicideki (parantez dışı) sınıf ve id belirteçleri: array( '.ad', '#kimlik' ).
 *
 * @param string $sel Tek seçici.
 * @return array
 */
function flxp_css_selector_tokens( $sel ) {
	// Parantez içi ve [öznitelik] bölümleri hesaba katılmaz.
	$flat  = '';
	$depth = 0;
	$br    = 0;
	$n     = strlen( $sel );
	for ( $i = 0; $i < $n; $i++ ) {
		$ch = $sel[ $i ];
		if ( '\\' === $ch && $i + 1 < $n ) {
			if ( 0 === $depth && 0 === $br ) {
				$flat .= $ch . $sel[ $i + 1 ];
			}
			++$i;
			continue;
		}
		if ( '"' === $ch || "'" === $ch ) {
			$i = flxp_css_string_end( $sel, $i );
			continue;
		}
		if ( '(' === $ch ) {
			++$depth;
			continue;
		}
		if ( ')' === $ch ) {
			$depth = max( 0, $depth - 1 );
			continue;
		}
		if ( '[' === $ch ) {
			++$br;
			continue;
		}
		if ( ']' === $ch ) {
			$br = max( 0, $br - 1 );
			continue;
		}
		if ( 0 === $depth && 0 === $br ) {
			$flat .= $ch;
		}
	}
	if ( ! preg_match_all( '/([.#])((?:[A-Za-z0-9_\-]|\\\\.|[^\x00-\x7F])+)/', $flat, $m, PREG_SET_ORDER ) ) {
		return array();
	}
	$out = array();
	foreach ( $m as $x ) {
		$out[] = $x[1] . flxp_css_unescape( $x[2] );
	}
	return $out;
}

/** CSS kaçışlarını çözer (\: → :, \31 0 → 10). */
function flxp_css_unescape( $s ) {
	if ( false === strpos( $s, '\\' ) ) {
		return $s;
	}
	return preg_replace_callback(
		'/\\\\(?:([0-9a-fA-F]{1,6})\s?|(.))/su',
		function ( $m ) {
			if ( isset( $m[1] ) && '' !== $m[1] ) {
				$cp = hexdec( $m[1] );
				return function_exists( 'mb_chr' ) ? (string) mb_chr( $cp, 'UTF-8' ) : chr( $cp & 0x7F );
			}
			return $m[2];
		},
		$s
	);
}

/** Seçici listesini üst düzey virgüllerden böler. */
function flxp_css_split_selectors( $prelude ) {
	$out   = array();
	$cur   = '';
	$depth = 0;
	$n     = strlen( $prelude );
	for ( $i = 0; $i < $n; $i++ ) {
		$ch = $prelude[ $i ];
		if ( '\\' === $ch && $i + 1 < $n ) {
			$cur .= $ch . $prelude[ ++$i ];
			continue;
		}
		if ( '"' === $ch || "'" === $ch ) {
			$j    = flxp_css_string_end( $prelude, $i );
			$cur .= substr( $prelude, $i, $j - $i + 1 );
			$i    = $j;
			continue;
		}
		if ( '(' === $ch || '[' === $ch ) {
			++$depth;
		} elseif ( ')' === $ch || ']' === $ch ) {
			$depth = max( 0, $depth - 1 );
		} elseif ( ',' === $ch && 0 === $depth ) {
			$out[] = trim( $cur );
			$cur   = '';
			continue;
		}
		$cur .= $ch;
	}
	if ( '' !== trim( $cur ) ) {
		$out[] = trim( $cur );
	}
	return $out;
}

/**
 * Dosyadaki tüm sınıf/id belirteçleri (envanter). Anahtar = belirteç.
 *
 * @param string $css CSS.
 * @return array
 */
function flxp_css_inventory( $css ) {
	$set = array();
	$walk = function ( $nodes ) use ( &$walk, &$set ) {
		foreach ( $nodes as $node ) {
			if ( null === $node['body'] || '' === $node['prelude'] ) {
				continue;
			}
			if ( '@' === $node['prelude'][0] ) {
				if ( flxp_css_is_group( $node['prelude'] ) ) {
					$walk( flxp_css_parse( $node['body'] ) );
				}
				continue;
			}
			foreach ( flxp_css_split_selectors( $node['prelude'] ) as $sel ) {
				foreach ( flxp_css_selector_tokens( $sel ) as $t ) {
					$set[ $t ] = true;
				}
			}
		}
	};
	$walk( flxp_css_parse( flxp_css_strip_comments( $css ) ) );
	return $set;
}

/**
 * Kullanılmayan kuralları atar ve sıkıştırır.
 *
 * @param string   $css  CSS.
 * @param array    $keep Sayfada bulunan belirteçler (anahtar = '.ad' / '#id').
 * @param callable $safe İsteğe bağlı: belirteç her zaman korunsun mu (joker listesi).
 * @return string
 */
function flxp_css_trim( $css, array $keep, $safe = null ) {
	$ok = function ( $sel ) use ( $keep, $safe ) {
		foreach ( flxp_css_selector_tokens( $sel ) as $t ) {
			if ( ! isset( $keep[ $t ] ) && ! ( $safe && $safe( $t ) ) ) {
				return false;
			}
		}
		return true;
	};
	$emit = function ( $nodes ) use ( &$emit, $ok ) {
		$out = '';
		foreach ( $nodes as $node ) {
			$pre = $node['prelude'];
			if ( null === $node['body'] ) {
				$out .= $pre . ';';
				continue;
			}
			if ( '' !== $pre && '@' === $pre[0] ) {
				if ( flxp_css_is_group( $pre ) ) {
					$inner = $emit( flxp_css_parse( $node['body'] ) );
					if ( '' !== trim( $inner ) ) {
						$out .= $pre . '{' . $inner . '}';
					}
				} else {
					$out .= $pre . '{' . $node['body'] . '}';
				}
				continue;
			}
			$sels = array_values( array_filter( flxp_css_split_selectors( $pre ), $ok ) );
			if ( $sels ) {
				$out .= implode( ',', $sels ) . '{' . $node['body'] . '}';
			}
		}
		return $out;
	};
	return flxp_css_minify( $emit( flxp_css_parse( flxp_css_strip_comments( $css ) ) ) );
}

/**
 * Göreli url() adreslerini kaynak dosyanın adresine göre mutlak yapar (CSS başka yere taşınınca).
 *
 * @param string $css  CSS.
 * @param string $base Kaynak dosyanın adresi (https://…/style.css).
 * @return string
 */
function flxp_css_absolute_urls( $css, $base ) {
	$dir = preg_replace( '#[^/]*$#', '', strtok( $base, '?#' ) );
	if ( ! preg_match( '#^(https?:)?//[^/]+#i', $dir, $origin ) ) {
		return $css;
	}
	$resolve = function ( $u ) use ( $dir, $origin ) {
		$u = trim( $u );
		if ( '' === $u || preg_match( '#^(?:[a-z][a-z0-9+.\-]*:|//|\#)#i', $u ) ) {
			return $u;
		}
		if ( '/' === $u[0] ) {
			return $origin[0] . $u;
		}
		$path = $dir . $u;
		// ./ ve ../ parçalarını çöz.
		$parts = explode( '/', preg_replace( '#^(https?:)?//[^/]+#i', '', $path ) );
		$stack = array();
		foreach ( $parts as $i => $p ) {
			if ( '..' === $p ) {
				if ( count( $stack ) > 1 ) {
					array_pop( $stack );
				}
			} elseif ( '.' !== $p && ( '' !== $p || 0 === $i || count( $parts ) - 1 === $i ) ) {
				$stack[] = $p;
			}
		}
		return $origin[0] . implode( '/', $stack );
	};
	$css = preg_replace_callback(
		'/url\(\s*(["\']?)([^"\')]*)\1\s*\)/i',
		function ( $m ) use ( $resolve ) {
			return 'url(' . $m[1] . $resolve( $m[2] ) . $m[1] . ')';
		},
		$css
	);
	return preg_replace_callback(
		'/@import\s+(["\'])([^"\']+)\1/i',
		function ( $m ) use ( $resolve ) {
			return '@import ' . $m[1] . $resolve( $m[2] ) . $m[1];
		},
		$css
	);
}

/**
 * Bileşik olmayan (GPU'da çalışmayan) özellikleri canlandıran @keyframes blokları.
 *
 * @param string $css CSS.
 * @return array array( array( 'name' => …, 'props' => array( … ) ) )
 */
function flxp_css_noncomposited_keyframes( $css ) {
	$safe = array( 'transform', 'opacity', 'translate', 'rotate', 'scale', 'filter', 'backdrop-filter', 'offset-distance', 'offset-path', 'offset-rotate', 'visibility', 'animation-timing-function', 'z-index' );
	$out  = array();
	$walk = function ( $nodes ) use ( &$walk, &$out, $safe ) {
		foreach ( $nodes as $node ) {
			if ( null === $node['body'] ) {
				continue;
			}
			if ( preg_match( '/^@(?:-[a-z]+-)?keyframes\s+(.+)$/i', $node['prelude'], $m ) ) {
				$props = array();
				foreach ( flxp_css_parse( $node['body'] ) as $frame ) {
					if ( null === $frame['body'] ) {
						continue;
					}
					foreach ( explode( ';', $frame['body'] ) as $decl ) {
						$p = strtolower( trim( strtok( $decl, ':' ) ) );
						if ( '' !== $p && false !== strpos( $decl, ':' ) && 0 !== strpos( $p, '--' ) && ! in_array( $p, $safe, true ) ) {
							$props[ $p ] = true;
						}
					}
				}
				if ( $props ) {
					$out[] = array(
						'name'  => trim( $m[1], " \t\"'" ),
						'props' => array_keys( $props ),
					);
				}
			} elseif ( flxp_css_is_group( $node['prelude'] ) ) {
				$walk( flxp_css_parse( $node['body'] ) );
			}
		}
	};
	$walk( flxp_css_parse( flxp_css_strip_comments( $css ) ) );
	return $out;
}
