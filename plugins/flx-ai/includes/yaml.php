<?php
/**
 * Güvenli YAML alt kümesi (0.17.0): SKILL.md ön bölümü için. Kod çalıştırmaz, nesne/etiket (!!) tanımaz.
 *
 * Desteklenen: `anahtar: değer` (tireli anahtar dahil), iç içe eşlemeler (girinti), `- öğe` listeleri,
 * akış listeleri `[a, b]`, çift/tek tırnaklı metin, `|` ve `>` blok metinleri (`-` / `+` ile), `#` yorumları.
 * Sayı ve true/false metin olarak döner; skill alanları zaten metin.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param string $text YAML.
 * @return array|WP_Error
 */
function flxai_yaml_parse( $text ) {
	$lines = explode( "\n", str_replace( array( "\r\n", "\r", "\t" ), array( "\n", "\n", '  ' ), (string) $text ) );
	$i     = 0;
	try {
		$out = flxai_yaml_block( $lines, $i, 0 );
	} catch ( Exception $e ) {
		return new WP_Error( 'flxai_yaml', $e->getMessage() );
	}
	return is_array( $out ) ? $out : array();
}

/** Satırın girintisi; boş/yorum satırı için -1. */
function flxai_yaml_indent( $line ) {
	if ( '' === trim( $line ) || 0 === strpos( ltrim( $line ), '#' ) ) {
		return -1;
	}
	return strlen( $line ) - strlen( ltrim( $line, ' ' ) );
}

/** $indent girintisindeki eşleme ya da liste bloğu. */
function flxai_yaml_block( array &$lines, &$i, $indent ) {
	$map  = array();
	$list = null;
	$n    = count( $lines );
	while ( $i < $n ) {
		$ind = flxai_yaml_indent( $lines[ $i ] );
		if ( $ind < 0 ) {
			$i++;
			continue;
		}
		if ( $ind < $indent ) {
			break;
		}
		if ( $ind > $indent ) {
			/* translators: %d: line number */
			throw new Exception( sprintf( __( 'Unexpected indentation on line %d of the header.', 'flx-ai' ), $i + 1 ) );
		}
		$line = substr( $lines[ $i ], $ind );
		if ( preg_match( '/^-(\s+(.*))?$/', $line, $m ) ) {
			if ( $map ) {
				/* translators: %d: line number */
				throw new Exception( sprintf( __( 'A list item cannot follow a key on line %d of the header.', 'flx-ai' ), $i + 1 ) );
			}
			$list = is_array( $list ) ? $list : array();
			$v    = isset( $m[2] ) ? trim( $m[2] ) : '';
			$i++;
			if ( '' === $v ) {
				$list[] = flxai_yaml_block( $lines, $i, $ind + 2 );
			} elseif ( preg_match( '/^([A-Za-z0-9_.\-]+):(\s+.*)?$/', $v ) ) {
				// "- anahtar: değer" biçimi: öğe bir eşleme.
				array_splice( $lines, $i, 0, array( str_repeat( ' ', $ind + 2 ) . $v ) );
				$list[] = flxai_yaml_block( $lines, $i, $ind + 2 );
			} else {
				$list[] = flxai_yaml_scalar( $v, $lines, $i, $ind );
			}
			continue;
		}
		if ( ! preg_match( '/^([A-Za-z0-9_.\-]+|"[^"]*"|\'[^\']*\')\s*:(?:\s+(.*))?$/', $line, $m ) ) {
			/* translators: %d: line number */
			throw new Exception( sprintf( __( 'Line %d of the header is not "key: value".', 'flx-ai' ), $i + 1 ) );
		}
		if ( is_array( $list ) ) {
			/* translators: %d: line number */
			throw new Exception( sprintf( __( 'A key cannot follow a list item on line %d of the header.', 'flx-ai' ), $i + 1 ) );
		}
		$key = trim( $m[1], '"\'' );
		$v   = isset( $m[2] ) ? trim( $m[2] ) : '';
		$i++;
		if ( '' === $v || '#' === substr( $v, 0, 1 ) ) {
			// İç içe blok (eşleme ya da liste), yoksa boş.
			$j = $i;
			while ( $j < $n && flxai_yaml_indent( $lines[ $j ] ) < 0 ) {
				$j++;
			}
			$ni = $j < $n ? flxai_yaml_indent( $lines[ $j ] ) : -1;
			if ( $ni > $indent || ( $ni === $indent && 0 === strpos( ltrim( $lines[ $j ] ), '-' ) && preg_match( '/^-(\s|$)/', ltrim( $lines[ $j ] ) ) ) ) {
				$i          = $j;
				$map[ $key ] = flxai_yaml_block( $lines, $i, $ni );
			} else {
				$map[ $key ] = '';
			}
			continue;
		}
		$map[ $key ] = flxai_yaml_scalar( $v, $lines, $i, $ind );
	}
	return is_array( $list ) ? $list : $map;
}

/** Tek değer: tırnaklı, akış listesi, blok metin ya da düz metin. $i blok metinde ilerler. */
function flxai_yaml_scalar( $v, array $lines, &$i, $indent ) {
	if ( preg_match( '/^([|>])([+-]?)\s*(#.*)?$/', $v, $m ) ) {
		$buf = array();
		$n   = count( $lines );
		$min = null;
		while ( $i < $n ) {
			$l = $lines[ $i ];
			if ( '' === trim( $l ) ) {
				$buf[] = '';
				$i++;
				continue;
			}
			$ind = strlen( $l ) - strlen( ltrim( $l, ' ' ) );
			if ( $ind <= $indent ) {
				break;
			}
			$min   = null === $min ? $ind : min( $min, $ind );
			$buf[] = $l;
			$i++;
		}
		$buf = array_map( fn( $l ) => '' === trim( $l ) ? '' : substr( $l, (int) $min ), $buf );
		$trail = '';
		while ( $buf && '' === end( $buf ) ) {
			array_pop( $buf );
			$trail .= "\n";
		}
		if ( '|' === $m[1] ) {
			$s = implode( "\n", $buf );
		} else {
			$s = '';
			foreach ( $buf as $k => $l ) {
				$s .= ( 0 === $k ? '' : ( '' === $l || '' === $buf[ $k - 1 ] ? "\n" : ' ' ) ) . $l;
			}
		}
		return '-' === $m[2] ? $s : $s . ( '+' === $m[2] ? $trail : "\n" );
	}
	if ( '"' === $v[0] ) {
		if ( ! preg_match( '/^"((?:[^"\\\\]|\\\\.)*)"\s*(#.*)?$/s', $v, $m ) ) {
			throw new Exception( __( 'A quoted value in the header is not closed.', 'flx-ai' ) );
		}
		return preg_replace_callback(
			'/\\\\(u[0-9a-fA-F]{4}|.)/',
			function ( $e ) {
				$c = $e[1];
				if ( 'u' === $c[0] && 5 === strlen( $c ) ) {
					return mb_convert_encoding( pack( 'n', hexdec( substr( $c, 1 ) ) ), 'UTF-8', 'UTF-16BE' );
				}
				return array( 'n' => "\n", 't' => "\t", '"' => '"', '\\' => '\\', '/' => '/', '0' => '' )[ $c ] ?? $c;
			},
			$m[1]
		);
	}
	if ( "'" === $v[0] ) {
		if ( ! preg_match( "/^'((?:[^']|'')*)'\s*(#.*)?$/s", $v, $m ) ) {
			throw new Exception( __( 'A quoted value in the header is not closed.', 'flx-ai' ) );
		}
		return str_replace( "''", "'", $m[1] );
	}
	if ( '[' === $v[0] ) {
		$inner = trim( preg_replace( '/\]\s*(#.*)?$/', '', substr( $v, 1 ) ) );
		if ( '' === $inner ) {
			return array();
		}
		$items = str_getcsv( $inner, ',', '"', '\\' );
		return array_values( array_filter( array_map( fn( $x ) => trim( trim( (string) $x ), "'\"" ), $items ), 'strlen' ) );
	}
	// Düz metin: " #" sonrası yorum.
	$v = preg_replace( '/\s+#.*$/', '', $v );
	return in_array( $v, array( '~', 'null' ), true ) ? '' : $v;
}

/**
 * Basit YAML yazımı (SKILL.md ön bölümü): metin, liste ve iç içe eşleme.
 *
 * @param array $data   Anahtar => değer.
 * @param int   $indent Girinti.
 */
function flxai_yaml_dump( array $data, $indent = 0 ) {
	$pad = str_repeat( ' ', $indent );
	$out = '';
	foreach ( $data as $k => $v ) {
		if ( is_array( $v ) ) {
			if ( ! $v ) {
				continue;
			}
			$out .= $pad . $k . ":\n";
			if ( array_keys( $v ) === range( 0, count( $v ) - 1 ) ) {
				foreach ( $v as $item ) {
					$out .= $pad . '  - ' . flxai_yaml_quote( (string) $item ) . "\n";
				}
			} else {
				$out .= flxai_yaml_dump( $v, $indent + 2 );
			}
			continue;
		}
		$v = (string) $v;
		if ( '' === $v ) {
			continue;
		}
		$out .= $pad . $k . ': ' . flxai_yaml_quote( $v ) . "\n";
	}
	return $out;
}

/** Gerekirse çift tırnaklı yazar. */
function flxai_yaml_quote( $v ) {
	$v = (string) $v;
	if ( '' === $v || preg_match( '/^[\s\-?:,\[\]{}#&*!|>\'"%@`]|[:#]\s|:$|\s$|[\n\t"\\\\]/u', $v ) || in_array( strtolower( $v ), array( 'true', 'false', 'yes', 'no', 'null', '~', 'on', 'off' ), true ) || is_numeric( $v ) ) {
		return '"' . str_replace( array( '\\', '"', "\n", "\t" ), array( '\\\\', '\\"', '\\n', '\\t' ), $v ) . '"';
	}
	return $v;
}
