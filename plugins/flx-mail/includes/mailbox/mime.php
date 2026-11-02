<?php
/**
 * MIME yardımcıları: başlık çözme, adres listesi, gövde kod çözme, BODYSTRUCTURE okuma.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Sunucuda PHP mailparse uzantısı var mı (varsa başlık ve adresler onunla ayrıştırılır). */
function flxi_mime_has_mailparse() {
	return (bool) apply_filters( 'flxi_use_mailparse', function_exists( 'mailparse_msg_create' ) && function_exists( 'mailparse_rfc822_parse_addresses' ) );
}

/** Ham başlık bloğunu küçük harfli anahtarlara ayırır (aynı ad birden çoksa ilki; received hariç). */
function flxi_mime_headers( $raw ) {
	if ( flxi_mime_has_mailparse() ) {
		$msg = mailparse_msg_create();
		try {
			mailparse_msg_parse( $msg, rtrim( (string) $raw, "\r\n" ) . "\r\n\r\n" );
			$d = mailparse_msg_get_part_data( $msg );
		} catch ( Throwable $e ) {
			$d = null;
		}
		mailparse_msg_free( $msg );
		if ( is_array( $d ) && ! empty( $d['headers'] ) ) {
			$out = array();
			foreach ( $d['headers'] as $k => $v ) {
				$out[ strtolower( $k ) ] = trim( (string) ( is_array( $v ) ? reset( $v ) : $v ) );
			}
			return $out;
		}
	}
	$raw = preg_replace( "/\r?\n[ \t]+/", ' ', (string) $raw );
	$out = array();
	foreach ( preg_split( "/\r?\n/", $raw ) as $line ) {
		if ( ! preg_match( '/^([!-9;-~]+):\s*(.*)$/', $line, $m ) ) {
			continue;
		}
		$k = strtolower( $m[1] );
		if ( ! isset( $out[ $k ] ) ) {
			$out[ $k ] = trim( $m[2] );
		}
	}
	return $out;
}

/** =?utf-8?B?…?= kodlu başlığı UTF-8 metne çevirir. */
function flxi_mime_decode( $s ) {
	$s = (string) $s;
	if ( false === strpos( $s, '=?' ) ) {
		return flxi_mime_to_utf8( $s, '' );
	}
	// Bitişik kodlanmış parçalar arasındaki boşluk atılır (RFC 2047).
	$s = preg_replace( '/(\?=)\s+(=\?)/', '$1$2', $s );
	return preg_replace_callback(
		'/=\?([^?]+)\?([QqBb])\?([^?]*)\?=/',
		function ( $m ) {
			$cs   = preg_replace( '/\*.*$/', '', $m[1] );
			$data = 'B' === strtoupper( $m[2] ) ? base64_decode( $m[3] ) : quoted_printable_decode( str_replace( '_', ' ', $m[3] ) );
			return flxi_mime_to_utf8( $data, $cs );
		},
		$s
	);
}

/** Verilen karakter kümesinden UTF-8'e (Türkçe kodlamalar dahil). */
function flxi_mime_to_utf8( $s, $charset ) {
	$s  = (string) $s;
	$cs = strtolower( trim( (string) $charset, " \"'" ) );
	if ( '' === $cs || in_array( $cs, array( 'utf-8', 'utf8', 'us-ascii', 'ascii' ), true ) ) {
		return mb_check_encoding( $s, 'UTF-8' ) ? $s : mb_convert_encoding( $s, 'UTF-8', 'Windows-1254' );
	}
	$map = array( 'iso-8859-9' => 'ISO-8859-9', 'latin5' => 'ISO-8859-9', 'windows-1254' => 'Windows-1254', 'cp1254' => 'Windows-1254', 'windows-1252' => 'Windows-1252', 'iso-8859-1' => 'ISO-8859-1', 'ks_c_5601-1987' => 'CP949' );
	$to  = $map[ $cs ] ?? $charset;
	if ( function_exists( 'iconv' ) ) {
		$r = @iconv( $to, 'UTF-8//IGNORE', $s ); // phpcs:ignore
		if ( false !== $r && '' !== $r ) {
			return $r;
		}
	}
	$r = @mb_convert_encoding( $s, 'UTF-8', $to ); // phpcs:ignore
	return false === $r ? $s : $r;
}

/** "Ad <a@b>, c@d" → [ [ 'name' =>, 'email' => ] ]. */
function flxi_mime_addresses( $s ) {
	$s = (string) $s;
	// Bazı programlar adresi de kodlar (=?utf-8?b?…?= içinde): önce çöz.
	if ( false !== strpos( $s, '=?' ) && false === strpos( preg_replace( '/=\?[^?]+\?[QqBb]\?[^?]*\?=/', '', $s ), '@' ) ) {
		$s = flxi_mime_decode( $s );
	}
	if ( flxi_mime_has_mailparse() && '' !== trim( $s ) ) {
		$list = mailparse_rfc822_parse_addresses( $s );
		if ( is_array( $list ) && $list ) {
			$out = array();
			foreach ( $list as $p ) {
				$email = strtolower( trim( (string) ( $p['address'] ?? '' ) ) );
				if ( ! empty( $p['is_group'] ) || ! is_email( $email ) ) {
					continue;
				}
				$name  = trim( flxi_mime_decode( (string) ( $p['display'] ?? '' ) ), " \"'" );
				$out[] = array( 'name' => strtolower( $name ) === $email ? '' : $name, 'email' => $email );
			}
			if ( $out ) {
				return $out;
			}
		}
	}
	$out   = array();
	$parts = array();
	$buf   = '';
	$q     = false;
	$a     = 0;
	for ( $i = 0, $n = strlen( $s ); $i < $n; $i++ ) {
		$c = $s[ $i ];
		if ( '"' === $c && ( 0 === $i || '\\' !== $s[ $i - 1 ] ) ) {
			$q = ! $q;
		} elseif ( '<' === $c && ! $q ) {
			++$a;
		} elseif ( '>' === $c && ! $q ) {
			--$a;
		} elseif ( ( ',' === $c || ';' === $c ) && ! $q && $a <= 0 ) {
			$parts[] = $buf;
			$buf     = '';
			continue;
		}
		$buf .= $c;
	}
	$parts[] = $buf;
	foreach ( $parts as $p ) {
		$p = trim( $p );
		if ( '' === $p ) {
			continue;
		}
		if ( preg_match( '/^(.*)<([^>]+)>\s*$/s', $p, $m ) ) {
			$name  = trim( flxi_mime_decode( trim( $m[1] ) ), " \"'" );
			$email = trim( $m[2] );
		} else {
			$name  = '';
			$email = trim( $p, " <>\"'" );
		}
		$email = strtolower( $email );
		if ( is_email( $email ) ) {
			$out[] = array( 'name' => stripslashes( $name ), 'email' => $email );
		}
	}
	return $out;
}

function flxi_mime_addr_text( array $list ) {
	return implode(
		', ',
		array_map(
			function ( $a ) {
				return $a['name'] ? $a['name'] . ' <' . $a['email'] . '>' : $a['email'];
			},
			$list
		)
	);
}

/** Aktarım kodlamasını çözer. */
function flxi_mime_body_decode( $data, $encoding ) {
	$e = strtolower( (string) $encoding );
	if ( 'base64' === $e ) {
		return base64_decode( preg_replace( '/[^A-Za-z0-9+\/=]/', '', (string) $data ) );
	}
	if ( 'quoted-printable' === $e ) {
		return quoted_printable_decode( (string) $data );
	}
	return (string) $data;
}

/** Message-ID biçimini sadeleştirir (<…> içi, küçük harf). */
function flxi_mime_msgid( $s ) {
	return preg_match( '/<([^>]+)>/', (string) $s, $m ) ? strtolower( trim( $m[1] ) ) : strtolower( trim( (string) $s, " <>\t" ) );
}

function flxi_mime_msgids( $s ) {
	preg_match_all( '/<([^>]+)>/', (string) $s, $m );
	return array_map( 'strtolower', $m[1] );
}

/** BODYSTRUCTURE parametre listesi → küçük harfli dizi (RFC 2231 name*=utf-8''… dahil). */
function flxi_mime_params( $list ) {
	$out = array();
	if ( ! is_array( $list ) ) {
		return $out;
	}
	for ( $i = 0; $i + 1 < count( $list ); $i += 2 ) {
		$k = strtolower( (string) $list[ $i ] );
		$v = (string) $list[ $i + 1 ];
		if ( preg_match( '/^(.+?)\*(\d+)?\*?$/', $k, $m ) ) {
			$base = $m[1];
			if ( preg_match( "/^([^']*)'[^']*'(.*)$/", $v, $mm ) ) {
				$v = flxi_mime_to_utf8( rawurldecode( $mm[2] ), $mm[1] );
			} elseif ( '*' === substr( $k, -1 ) ) {
				$v = rawurldecode( $v );
			}
			$out[ $base ] = ( isset( $m[2] ) && '' !== $m[2] && '0' !== $m[2] && isset( $out[ $base ] ) ) ? $out[ $base ] . $v : $v;
			continue;
		}
		$out[ $k ] = flxi_mime_decode( $v );
	}
	return $out;
}

/**
 * BODYSTRUCTURE'ı düz parça listesine çevirir.
 *
 * @return array [ [ section, type ('text/html'), params, id, encoding, size, disposition, filename ] ]
 */
function flxi_mime_parts( $bs, $prefix = '' ) {
	$out = array();
	if ( ! is_array( $bs ) || ! $bs ) {
		return $out;
	}
	if ( is_array( $bs[0] ) ) {
		// Çok parçalı: önce alt parçalar, sonra alt tür.
		$n = 0;
		foreach ( $bs as $child ) {
			if ( ! is_array( $child ) ) {
				break;
			}
			++$n;
			$out = array_merge( $out, flxi_mime_parts( $child, ( '' === $prefix ? '' : $prefix . '.' ) . $n ) );
		}
		return $out;
	}
	$section = '' === $prefix ? '1' : $prefix;
	$type    = strtolower( (string) $bs[0] . '/' . (string) $bs[1] );
	$params  = flxi_mime_params( $bs[2] ?? null );
	$idx     = 'text' === strtolower( (string) $bs[0] ) ? 9 : ( 'message/rfc822' === $type ? 11 : 8 );
	$disp    = isset( $bs[ $idx ] ) && is_array( $bs[ $idx ] ) ? $bs[ $idx ] : null;
	$dparams = $disp ? flxi_mime_params( $disp[1] ?? null ) : array();
	$name    = $dparams['filename'] ?? ( $params['name'] ?? '' );
	$out[]   = array(
		'section'     => $section,
		'type'        => $type,
		'params'      => $params,
		'id'          => trim( (string) ( $bs[3] ?? '' ), '<> ' ),
		'encoding'    => strtolower( (string) ( $bs[5] ?? '7bit' ) ),
		'size'        => (int) ( $bs[6] ?? 0 ),
		'disposition' => $disp ? strtolower( (string) $disp[0] ) : '',
		'filename'    => sanitize_file_name( (string) $name ) ? (string) $name : '',
	);
	return $out;
}

/** Gösterilecek metin parçaları ve ekler. */
function flxi_mime_pick( array $parts ) {
	$text = null;
	$html = null;
	$att  = array();
	foreach ( $parts as $p ) {
		$is_att = 'attachment' === $p['disposition'] || ( '' !== $p['filename'] && 0 !== strpos( $p['type'], 'text/' ) ) || 'message/rfc822' === $p['type'];
		if ( ! $is_att && 'text/plain' === $p['type'] && null === $text ) {
			$text = $p;
		} elseif ( ! $is_att && 'text/html' === $p['type'] && null === $html ) {
			$html = $p;
		} elseif ( $is_att || ( 0 !== strpos( $p['type'], 'multipart/' ) && ! in_array( $p['type'], array( 'text/plain', 'text/html' ), true ) ) ) {
			$att[] = array(
				'section' => $p['section'],
				'name'    => $p['filename'] ? $p['filename'] : ( 'message/rfc822' === $p['type'] ? 'ileti.eml' : 'ek-' . $p['section'] ),
				'type'    => $p['type'],
				'size'    => $p['size'],
				'cid'     => $p['id'],
				'inline'  => 'attachment' !== $p['disposition'] && '' !== $p['id'],
			);
		}
	}
	return array( $text, $html, $att );
}

/** HTML'den kısa önizleme metni. */
function flxi_mime_snippet( $text, $html ) {
	$t = '' !== trim( (string) $text ) ? (string) $text : wp_strip_all_tags( preg_replace( '#<(style|script|head)[^>]*>.*?</\1>#is', '', (string) $html ) );
	// Alıntılanan eski yazışmayı önizlemeden çıkar.
	$t = preg_replace( '/^>.*$/m', '', $t );
	$t = html_entity_decode( $t, ENT_QUOTES, 'UTF-8' );
	return mb_substr( trim( preg_replace( '/\s+/u', ' ', $t ) ), 0, 240 );
}
