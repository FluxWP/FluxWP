<?php
/**
 * Tutucu JavaScript sıkıştırma: yorumları ve satır başı/sonu boşluklarını atar, satır sonlarını korur
 * (otomatik noktalı virgül davranışı değişmesin). Dizgi, şablon dizgisi ve düzenli ifade içine dokunmaz.
 * Doğrulama: ayraç/köşeli ayraç/parantez sayıları değişmişse ya da kazanç yoksa null döner ve
 * çağıran asıl dosyayı kullanır. WordPress'e bağlı değil.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param string $js JavaScript.
 * @return string|null Sıkıştırılmış kod; güvenli değilse null.
 */
function flxp_js_minify( $js ) {
	$n    = strlen( $js );
	$out  = '';
	$prev = ''; // Son anlamlı (boşluk olmayan) karakter.
	$word = ''; // Son sözcük (regex mi bölme mi kararı için).
	$tpl  = array(); // Şablon dizgisi içindeki ${ derinlikleri.
	$i    = 0;
	$kw   = array( 'return', 'typeof', 'instanceof', 'in', 'of', 'new', 'delete', 'void', 'throw', 'case', 'do', 'else', 'yield', 'await' );
	while ( $i < $n ) {
		$ch = $js[ $i ];
		$nx = $i + 1 < $n ? $js[ $i + 1 ] : '';

		// Şablon dizgisi gövdesi.
		if ( '`' === $ch || ( '}' === $ch && $tpl && 0 === end( $tpl ) ) ) {
			if ( '}' === $ch ) {
				array_pop( $tpl );
			}
			$j = $i + 1;
			while ( $j < $n ) {
				if ( '\\' === $js[ $j ] ) {
					$j += 2;
					continue;
				}
				if ( '`' === $js[ $j ] ) {
					break;
				}
				if ( '$' === $js[ $j ] && $j + 1 < $n && '{' === $js[ $j + 1 ] ) {
					$tpl[] = 0;
					++$j;
					break;
				}
				++$j;
			}
			$out .= substr( $js, $i, $j - $i + 1 );
			$prev = '`' === ( $js[ $j ] ?? '' ) ? '`' : '{';
			$word = '';
			$i    = $j + 1;
			continue;
		}
		if ( $tpl ) {
			if ( '{' === $ch ) {
				$tpl[ count( $tpl ) - 1 ]++;
			} elseif ( '}' === $ch ) {
				$tpl[ count( $tpl ) - 1 ]--;
			}
		}

		if ( '"' === $ch || "'" === $ch ) {
			$j = $i + 1;
			while ( $j < $n && $js[ $j ] !== $ch ) {
				if ( '\\' === $js[ $j ] ) {
					++$j;
				} elseif ( "\n" === $js[ $j ] ) {
					return null; // Kapanmamış dizgi: sözdizimini anlayamadık.
				}
				++$j;
			}
			$out .= substr( $js, $i, $j - $i + 1 );
			$prev = $ch;
			$word = '';
			$i    = $j + 1;
			continue;
		}

		if ( '/' === $ch && '/' === $nx ) {
			$e = strpos( $js, "\n", $i );
			$i = false === $e ? $n : $e;
			continue;
		}
		if ( '/' === $ch && '*' === $nx ) {
			$e = strpos( $js, '*/', $i + 2 );
			if ( false === $e ) {
				return null;
			}
			$chunk = substr( $js, $i, $e + 2 - $i );
			// /*! lisans */ yorumları korunur; satır içeren yorum satır sonu olarak kalır (ASI).
			if ( '!' === ( $js[ $i + 2 ] ?? '' ) ) {
				$out .= $chunk;
			} elseif ( false !== strpos( $chunk, "\n" ) ) {
				$out .= "\n";
			} else {
				$out .= ' ';
			}
			$i = $e + 2;
			continue;
		}
		if ( '/' === $ch ) {
			$regex = '' === $prev || false !== strpos( '(,=:[!&|?{};+-*%<>~^', $prev ) || in_array( $word, $kw, true );
			if ( $regex ) {
				$j     = $i + 1;
				$class = false;
				while ( $j < $n ) {
					$c = $js[ $j ];
					if ( '\\' === $c ) {
						$j += 2;
						continue;
					}
					if ( "\n" === $c ) {
						return null;
					}
					if ( '[' === $c ) {
						$class = true;
					} elseif ( ']' === $c ) {
						$class = false;
					} elseif ( '/' === $c && ! $class ) {
						break;
					}
					++$j;
				}
				++$j;
				while ( $j < $n && ctype_alpha( $js[ $j ] ) ) {
					++$j;
				}
				$out .= substr( $js, $i, $j - $i );
				$prev = '/';
				$word = 'x';
				$i    = $j;
				continue;
			}
		}

		if ( "\n" === $ch || "\r" === $ch ) {
			$out = rtrim( $out, " \t" );
			if ( '' !== $out && "\n" !== substr( $out, -1 ) ) {
				$out .= "\n";
			}
			++$i;
			continue;
		}
		if ( ' ' === $ch || "\t" === $ch ) {
			$last = substr( $out, -1 );
			if ( '' !== $out && "\n" !== $last && ' ' !== $last ) {
				$out .= ' ';
			}
			++$i;
			continue;
		}

		$out .= $ch;
		if ( ctype_alnum( $ch ) || '_' === $ch || '$' === $ch ) {
			$word = ( ctype_alnum( $prev ) || '_' === $prev || '$' === $prev ) ? $word . $ch : $ch;
		} else {
			$word = '';
		}
		$prev = $ch;
		++$i;
	}
	if ( $tpl ) {
		return null;
	}
	$out = trim( preg_replace( '/[ \t]+\n/', "\n", $out ) );
	// Doğrulama: kaynakta dengeli olan ayraç çiftleri çıktıda da dengeli olmalı; yoksa bir yerde kod yutulmuştur.
	foreach ( array( '{}', '()', '[]' ) as $p ) {
		$src = substr_count( $js, $p[0] ) - substr_count( $js, $p[1] );
		$dst = substr_count( $out, $p[0] ) - substr_count( $out, $p[1] );
		if ( 0 === $src && 0 !== $dst ) {
			return null;
		}
	}
	return strlen( $out ) < strlen( $js ) ? $out : null;
}
