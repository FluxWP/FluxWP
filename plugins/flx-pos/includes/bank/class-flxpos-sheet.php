<?php
/**
 * Tablo dosyası okuyucu: .xls (Excel 97-2003, BIFF8), .xlsx, .csv ve tablo içeren HTML (.xls adıyla
 * inen internet şubesi çıktıları). Yalnız ilk çalışma sayfası okunur; dış kütüphane gerekmez.
 *
 * Dönüş: satır listesi; her satır 0 tabanlı hücre dizisi (sayı float, tarih hücresi "GG.AA.YYYY" metni,
 * diğerleri metin). Okunamazsa WP_Error.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Sheet {

	const MAX_ROWS = 20000;

	/**
	 * @param string $path Dosya yolu.
	 * @param string $name Özgün dosya adı (uzantı ipucu).
	 * @return array|WP_Error
	 */
	public static function read( $path, $name = '' ) {
		$head = (string) file_get_contents( $path, false, null, 0, 512 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( '' === $head ) {
			return new WP_Error( 'flxpos_sheet', __( 'Dosya boş.', 'flx-pos' ) );
		}
		try {
			if ( 0 === strpos( $head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" ) ) {
				$rows = self::xls( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			} elseif ( 0 === strpos( $head, "PK\x03\x04" ) ) {
				$rows = self::xlsx( $path );
			} elseif ( preg_match( '/^\s*(?:\xEF\xBB\xBF)?\s*<(?:!doctype|html|table|\?xml|meta|head|body)/i', $head ) ) {
				$rows = self::html( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			} else {
				$rows = self::csv( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		} catch ( Throwable $e ) {
			return new WP_Error( 'flxpos_sheet', __( 'Dosya okunamadı; Excel (.xls, .xlsx) ya da CSV olarak yeniden indirin.', 'flx-pos' ) );
		}
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		return array_slice( $rows, 0, self::MAX_ROWS );
	}

	/* ---------------- Yardımcılar ---------------- */

	/** Excel seri tarihini GG.AA.YYYY'ye çevir. */
	public static function serial_date( $v, $mode1904 = false ) {
		$days = (int) floor( (float) $v );
		$base = $mode1904 ? gmmktime( 0, 0, 0, 1, 1, 1904 ) : gmmktime( 0, 0, 0, 12, 30, 1899 );
		return gmdate( 'd.m.Y', $base + $days * DAY_IN_SECONDS );
	}

	/** Biçim kodu tarih mi (yerleşik 14–22, 45–47 ya da d/m/y içeren özel kod). */
	private static function is_date_format( $id, $code ) {
		if ( ( $id >= 14 && $id <= 22 ) || ( $id >= 45 && $id <= 47 ) || ( $id >= 27 && $id <= 36 ) || ( $id >= 50 && $id <= 58 ) ) {
			return true;
		}
		$c = preg_replace( '/"[^"]*"|\[[^\]]*\]|\\\\./', '', (string) $code );
		return '' !== $c && (bool) preg_match( '/[dy]|m{1,4}.*[dy]|[dy].*m/i', $c ) && ! preg_match( '/^[#0.,\s%]+$/', $c );
	}

	/* ---------------- CSV ---------------- */

	private static function csv( $s ) {
		$s = preg_replace( '/^\xEF\xBB\xBF/', '', $s );
		if ( ! preg_match( '//u', $s ) ) {
			$conv = function_exists( 'iconv' ) ? @iconv( 'WINDOWS-1254', 'UTF-8//IGNORE', $s ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$s    = false !== $conv ? $conv : mb_convert_encoding( $s, 'UTF-8', 'ISO-8859-9' );
		}
		$lines = preg_split( '/\r\n|\r|\n/', $s );
		$probe = implode( "\n", array_slice( $lines, 0, 30 ) );
		$best  = ',';
		$max   = -1;
		foreach ( array( ';', ',', "\t", '|' ) as $d ) {
			$n = substr_count( $probe, $d );
			if ( $n > $max ) {
				$max  = $n;
				$best = $d;
			}
		}
		$rows = array();
		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				$rows[] = array();
				continue;
			}
			$rows[] = array_map( static fn( $c ) => trim( (string) $c ), str_getcsv( $line, $best, '"', '' ) );
		}
		return $rows;
	}

	/* ---------------- HTML tablo ---------------- */

	private static function html( $s ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return new WP_Error( 'flxpos_sheet', __( 'Sunucuda DOM uzantısı yok.', 'flx-pos' ) );
		}
		if ( ! preg_match( '//u', $s ) ) {
			$conv = function_exists( 'iconv' ) ? @iconv( 'WINDOWS-1254', 'UTF-8//IGNORE', $s ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$s    = false !== $conv ? $conv : $s;
		}
		$doc  = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="UTF-8">' . $s, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		$rows = array();
		foreach ( $doc->getElementsByTagName( 'tr' ) as $tr ) {
			$row = array();
			foreach ( $tr->childNodes as $td ) {
				if ( XML_ELEMENT_NODE === $td->nodeType && in_array( strtolower( $td->nodeName ), array( 'td', 'th' ), true ) ) {
					$row[] = trim( preg_replace( '/\s+/u', ' ', $td->textContent ) );
					$span  = (int) $td->getAttribute( 'colspan' );
					for ( $i = 1; $i < $span; $i++ ) {
						$row[] = '';
					}
				}
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/* ---------------- XLSX ---------------- */

	private static function xlsx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'flxpos_sheet', __( 'Sunucuda zip uzantısı yok; dosyayı CSV olarak yükleyin.', 'flx-pos' ) );
		}
		$z = new ZipArchive();
		if ( true !== $z->open( $path ) ) {
			return new WP_Error( 'flxpos_sheet', __( 'Excel dosyası açılamadı.', 'flx-pos' ) );
		}
		$xml = static function ( $name ) use ( $z ) {
			$s = $z->getFromName( $name );
			if ( false === $s || '' === $s ) {
				return null;
			}
			$x = simplexml_load_string( $s, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
			return false === $x ? null : $x;
		};
		$shared = array();
		$sst    = $xml( 'xl/sharedStrings.xml' );
		if ( $sst ) {
			foreach ( $sst->si as $si ) {
				$shared[] = self::xlsx_text( $si );
			}
		}
		$date_styles = array();
		$styles      = $xml( 'xl/styles.xml' );
		if ( $styles ) {
			$codes = array();
			if ( isset( $styles->numFmts ) ) {
				foreach ( $styles->numFmts->numFmt as $f ) {
					$codes[ (int) $f['numFmtId'] ] = (string) $f['formatCode'];
				}
			}
			$i = 0;
			if ( isset( $styles->cellXfs ) ) {
				foreach ( $styles->cellXfs->xf as $xf ) {
					$id = (int) $xf['numFmtId'];
					if ( self::is_date_format( $id, $codes[ $id ] ?? '' ) ) {
						$date_styles[ $i ] = true;
					}
					++$i;
				}
			}
		}
		$wb      = $xml( 'xl/workbook.xml' );
		$m1904   = $wb && isset( $wb->workbookPr ) && in_array( (string) $wb->workbookPr['date1904'], array( '1', 'true' ), true );
		$sheet   = 'xl/worksheets/sheet1.xml';
		$rels    = $xml( 'xl/_rels/workbook.xml.rels' );
		if ( $wb && $rels && isset( $wb->sheets->sheet[0] ) ) {
			$rid = (string) $wb->sheets->sheet[0]->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )->id;
			foreach ( $rels->Relationship as $r ) {
				if ( (string) $r['Id'] === $rid ) {
					$t     = ltrim( (string) $r['Target'], '/' );
					$sheet = 0 === strpos( $t, 'xl/' ) ? $t : 'xl/' . $t;
				}
			}
		}
		$ws = $xml( $sheet );
		$z->close();
		if ( ! $ws || ! isset( $ws->sheetData ) ) {
			return new WP_Error( 'flxpos_sheet', __( 'Excel dosyasında sayfa bulunamadı.', 'flx-pos' ) );
		}
		$rows = array();
		$next = 1;
		foreach ( $ws->sheetData->row as $row ) {
			$rn = isset( $row['r'] ) ? (int) $row['r'] : $next;
			while ( $next < $rn && count( $rows ) < self::MAX_ROWS ) {
				$rows[] = array();
				++$next;
			}
			$cells = array();
			$col   = 0;
			foreach ( $row->c as $c ) {
				if ( isset( $c['r'] ) && preg_match( '/^([A-Z]+)/', (string) $c['r'], $m ) ) {
					$col = 0;
					foreach ( str_split( $m[1] ) as $ch ) {
						$col = $col * 26 + ( ord( $ch ) - 64 );
					}
					--$col;
				}
				$t = (string) $c['t'];
				$v = isset( $c->v ) ? (string) $c->v : '';
				if ( 's' === $t ) {
					$val = $shared[ (int) $v ] ?? '';
				} elseif ( 'inlineStr' === $t ) {
					$val = isset( $c->is ) ? self::xlsx_text( $c->is ) : '';
				} elseif ( 'str' === $t || 'e' === $t ) {
					$val = $v;
				} elseif ( 'b' === $t ) {
					$val = '1' === $v ? 'TRUE' : 'FALSE';
				} elseif ( '' === $v ) {
					$val = '';
				} elseif ( isset( $date_styles[ (int) $c['s'] ] ) && is_numeric( $v ) ) {
					$val = self::serial_date( $v, $m1904 );
				} else {
					$val = is_numeric( $v ) ? (float) $v : $v;
				}
				for ( $k = count( $cells ); $k < $col; $k++ ) {
					$cells[ $k ] = '';
				}
				$cells[ $col ] = $val;
				++$col;
			}
			$rows[] = $cells;
			$next   = $rn + 1;
			if ( count( $rows ) >= self::MAX_ROWS ) {
				break;
			}
		}
		return $rows;
	}

	private static function xlsx_text( $si ) {
		if ( isset( $si->t ) && ! isset( $si->r ) ) {
			return (string) $si->t;
		}
		$s = '';
		foreach ( $si->r as $r ) {
			$s .= (string) $r->t;
		}
		return $s;
	}

	/* ---------------- XLS (BIFF8, OLE2) ---------------- */

	private static function u16( $s, $o ) {
		return unpack( 'v', substr( $s, $o, 2 ) )[1];
	}

	private static function u32( $s, $o ) {
		return unpack( 'V', substr( $s, $o, 4 ) )[1];
	}

	/** OLE2 bileşik dosyasından Workbook akışı. */
	private static function ole_stream( $d ) {
		$shift  = self::u16( $d, 0x1E );
		$size   = 1 << $shift;
		$mshift = self::u16( $d, 0x20 );
		$msize  = 1 << $mshift;
		$cutoff = self::u32( $d, 0x38 );
		$sec    = static fn( $id ) => substr( $d, ( $id + 1 ) * $size, $size );
		// FAT sektörleri: başlıktaki 109 DIFAT girdisi + DIFAT zinciri.
		$fat_secs = array();
		for ( $i = 0; $i < 109; $i++ ) {
			$v = self::u32( $d, 0x4C + $i * 4 );
			if ( $v < 0xFFFFFFFA ) {
				$fat_secs[] = $v;
			}
		}
		$dif  = self::u32( $d, 0x44 );
		$left = self::u32( $d, 0x48 );
		while ( $left-- > 0 && $dif < 0xFFFFFFFA ) {
			$b = $sec( $dif );
			for ( $i = 0; $i < $size / 4 - 1; $i++ ) {
				$v = self::u32( $b, $i * 4 );
				if ( $v < 0xFFFFFFFA ) {
					$fat_secs[] = $v;
				}
			}
			$dif = self::u32( $b, $size - 4 );
		}
		$fat = array();
		foreach ( $fat_secs as $fs ) {
			$fat = array_merge( $fat, array_values( unpack( 'V*', $sec( $fs ) ) ) );
		}
		$chain = static function ( $start, $table, $max ) {
			$out  = array();
			$seen = array();
			while ( $start < 0xFFFFFFFA && isset( $table[ $start ] ) && ! isset( $seen[ $start ] ) && count( $out ) < $max ) {
				$seen[ $start ] = true;
				$out[]          = $start;
				$start          = $table[ $start ];
			}
			return $out;
		};
		$read = static function ( $start ) use ( $chain, $fat, $sec, $d, $size ) {
			$s = '';
			foreach ( $chain( $start, $fat, (int) ( strlen( $d ) / $size ) + 1 ) as $id ) {
				$s .= $sec( $id );
			}
			return $s;
		};
		$dir  = $read( self::u32( $d, 0x30 ) );
		$root = null;
		$wb   = null;
		for ( $o = 0; $o + 128 <= strlen( $dir ); $o += 128 ) {
			$nl   = self::u16( $dir, $o + 0x40 );
			$name = $nl > 2 ? mb_convert_encoding( substr( $dir, $o, $nl - 2 ), 'UTF-8', 'UTF-16LE' ) : '';
			$type = ord( $dir[ $o + 0x42 ] );
			$ent  = array(
				'start' => self::u32( $dir, $o + 0x74 ),
				'size'  => self::u32( $dir, $o + 0x78 ),
			);
			if ( 5 === $type ) {
				$root = $ent;
			} elseif ( 2 === $type && in_array( $name, array( 'Workbook', 'Book' ), true ) ) {
				$wb = $ent;
			}
		}
		if ( ! $wb ) {
			throw new RuntimeException( 'no workbook' );
		}
		if ( $wb['size'] >= $cutoff ) {
			return substr( $read( $wb['start'] ), 0, $wb['size'] );
		}
		$mini    = $root ? $read( $root['start'] ) : '';
		$minifat = array_values( (array) unpack( 'V*', $read( self::u32( $d, 0x3C ) ) ?: "\xFE\xFF\xFF\xFF" ) );
		$s       = '';
		foreach ( $chain( $wb['start'], $minifat, (int) ( strlen( $mini ) / $msize ) + 1 ) as $id ) {
			$s .= substr( $mini, $id * $msize, $msize );
		}
		return substr( $s, 0, $wb['size'] );
	}

	/** BIFF8 dizesi (XLUnicodeString, cch 16 bit). Parçalı okuma için konum döner. */
	private static function biff_str( $s, &$o, $cch_bytes = 2 ) {
		$cch = 2 === $cch_bytes ? self::u16( $s, $o ) : ord( $s[ $o ] );
		$o  += $cch_bytes;
		$f   = ord( $s[ $o ] );
		++$o;
		$runs = 0;
		$ext  = 0;
		if ( $f & 0x08 ) {
			$runs = self::u16( $s, $o );
			$o   += 2;
		}
		if ( $f & 0x04 ) {
			$ext = self::u32( $s, $o );
			$o  += 4;
		}
		$len = ( $f & 0x01 ) ? $cch * 2 : $cch;
		$raw = substr( $s, $o, $len );
		$o  += $len + $runs * 4 + $ext;
		return ( $f & 0x01 ) ? mb_convert_encoding( $raw, 'UTF-8', 'UTF-16LE' ) : mb_convert_encoding( $raw, 'UTF-8', 'Windows-1252' );
	}

	/** SST: CONTINUE kayıtlarına bölünmüş dizeleri birleştirerek okur. */
	private static function sst( array $chunks ) {
		$out   = array();
		$ci    = 0;
		$buf   = $chunks[0];
		$o     = 8; // cstTotal, cstUnique
		$total = self::u32( $buf, 4 );
		while ( count( $out ) < $total ) {
			if ( $o >= strlen( $buf ) ) {
				if ( ! isset( $chunks[ ++$ci ] ) ) {
					break;
				}
				$buf = $chunks[ $ci ];
				$o   = 0;
			}
			$cch = self::u16( $buf, $o );
			$f   = ord( $buf[ $o + 2 ] );
			$o  += 3;
			$runs = 0;
			$ext  = 0;
			if ( $f & 0x08 ) {
				$runs = self::u16( $buf, $o );
				$o   += 2;
			}
			if ( $f & 0x04 ) {
				$ext = self::u32( $buf, $o );
				$o  += 4;
			}
			$str  = '';
			$left = $cch;
			$wide = (bool) ( $f & 0x01 );
			while ( $left > 0 ) {
				$avail = strlen( $buf ) - $o;
				$need  = $wide ? $left * 2 : $left;
				$take  = min( $avail, $need );
				$part  = substr( $buf, $o, $take );
				$str  .= $wide ? mb_convert_encoding( $part, 'UTF-8', 'UTF-16LE' ) : mb_convert_encoding( $part, 'UTF-8', 'Windows-1252' );
				$left -= $wide ? intdiv( $take, 2 ) : $take;
				$o    += $take;
				if ( $left > 0 ) {
					if ( ! isset( $chunks[ ++$ci ] ) ) {
						break 2;
					}
					$buf  = $chunks[ $ci ];
					$wide = (bool) ( ord( $buf[0] ) & 0x01 );
					$o    = 1;
				}
			}
			// Biçim ve fonetik verisini atla (CONTINUE sınırını aşabilir).
			$skip = $runs * 4 + $ext;
			while ( $skip > 0 ) {
				$avail = strlen( $buf ) - $o;
				if ( $skip <= $avail ) {
					$o   += $skip;
					$skip = 0;
				} else {
					$skip -= $avail;
					if ( ! isset( $chunks[ ++$ci ] ) ) {
						break 2;
					}
					$buf = $chunks[ $ci ];
					$o   = 0;
				}
			}
			$out[] = $str;
		}
		return $out;
	}

	private static function rk( $v ) {
		if ( $v & 0x02 ) {
			$n = $v >> 2;
			if ( $n & 0x20000000 ) {
				$n -= 0x40000000;
			}
		} else {
			$n = unpack( 'e', "\0\0\0\0" . pack( 'V', $v & 0xFFFFFFFC ) )[1];
		}
		return ( $v & 0x01 ) ? $n / 100 : (float) $n;
	}

	private static function xls( $d ) {
		$s       = self::ole_stream( $d );
		$len     = strlen( $s );
		$o       = 0;
		$sst     = array();
		$formats = array();
		$xfs     = array();
		$m1904   = false;
		$sheet   = null;
		// Kitap genel akışı.
		$sst_chunks = null;
		while ( $o + 4 <= $len ) {
			$type = self::u16( $s, $o );
			$size = self::u16( $s, $o + 2 );
			$data = substr( $s, $o + 4, $size );
			$o   += 4 + $size;
			if ( null !== $sst_chunks && 0x003C !== $type ) {
				$sst        = self::sst( $sst_chunks );
				$sst_chunks = null;
			}
			switch ( $type ) {
				case 0x00FC:
					$sst_chunks = array( $data );
					break;
				case 0x003C:
					if ( null !== $sst_chunks ) {
						$sst_chunks[] = $data;
					}
					break;
				case 0x041E:
					$p                                  = 2;
					$formats[ self::u16( $data, 0 ) ] = self::biff_str( $data, $p );
					break;
				case 0x00E0:
					$xfs[] = self::u16( $data, 2 );
					break;
				case 0x0022:
					$m1904 = 1 === self::u16( $data, 0 );
					break;
				case 0x0085:
					if ( null === $sheet && 0 === ord( $data[5] ) ) {
						$sheet = self::u32( $data, 0 );
					}
					break;
			}
			if ( 0x000A === $type ) {
				break;
			}
		}
		if ( null !== $sst_chunks ) {
			$sst = self::sst( $sst_chunks );
		}
		if ( null === $sheet ) {
			throw new RuntimeException( 'no sheet' );
		}
		$is_date = static function ( $xf ) use ( $xfs, $formats ) {
			$f = $xfs[ $xf ] ?? 0;
			return self::is_date_format( $f, $formats[ $f ] ?? '' );
		};
		$num     = static fn( $xf, $v ) => $is_date( $xf ) ? self::serial_date( $v, $m1904 ) : (float) $v;
		$rows    = array();
		$put     = static function ( $r, $c, $v ) use ( &$rows ) {
			if ( $r < self::MAX_ROWS ) {
				$rows[ $r ][ $c ] = $v;
			}
		};
		$o       = $sheet;
		$pending = null;
		while ( $o + 4 <= $len ) {
			$type = self::u16( $s, $o );
			$size = self::u16( $s, $o + 2 );
			$data = substr( $s, $o + 4, $size );
			$o   += 4 + $size;
			if ( 0x000A === $type ) {
				break;
			}
			switch ( $type ) {
				case 0x00FD: // LABELSST
					$put( self::u16( $data, 0 ), self::u16( $data, 2 ), (string) ( $sst[ self::u32( $data, 6 ) ] ?? '' ) );
					break;
				case 0x0204: // LABEL
					$p = 6;
					$put( self::u16( $data, 0 ), self::u16( $data, 2 ), self::biff_str( $data, $p ) );
					break;
				case 0x0203: // NUMBER
					$put( self::u16( $data, 0 ), self::u16( $data, 2 ), $num( self::u16( $data, 4 ), unpack( 'e', substr( $data, 6, 8 ) )[1] ) );
					break;
				case 0x027E: // RK
					$put( self::u16( $data, 0 ), self::u16( $data, 2 ), $num( self::u16( $data, 4 ), self::rk( self::u32( $data, 6 ) ) ) );
					break;
				case 0x00BD: // MULRK
					$r = self::u16( $data, 0 );
					$c = self::u16( $data, 2 );
					for ( $p = 4; $p + 6 <= $size - 2; $p += 6 ) {
						$put( $r, $c++, $num( self::u16( $data, $p ), self::rk( self::u32( $data, $p + 2 ) ) ) );
					}
					break;
				case 0x0006: // FORMULA
					$r = self::u16( $data, 0 );
					$c = self::u16( $data, 2 );
					if ( "\xFF\xFF" === substr( $data, 12, 2 ) ) {
						$k = ord( $data[6] );
						if ( 0 === $k ) {
							$pending = array( $r, $c );
						} elseif ( 1 === $k ) {
							$put( $r, $c, ord( $data[8] ) ? 'TRUE' : 'FALSE' );
						}
					} else {
						$put( $r, $c, $num( self::u16( $data, 4 ), unpack( 'e', substr( $data, 6, 8 ) )[1] ) );
					}
					break;
				case 0x0207: // STRING (önceki formülün sonucu)
					if ( $pending ) {
						$p = 0;
						$put( $pending[0], $pending[1], self::biff_str( $data, $p ) );
						$pending = null;
					}
					break;
			}
		}
		if ( ! $rows ) {
			return array();
		}
		$out = array();
		$max = max( array_keys( $rows ) );
		for ( $r = 0; $r <= $max; $r++ ) {
			$row = $rows[ $r ] ?? array();
			if ( $row ) {
				$full = array_fill( 0, max( array_keys( $row ) ) + 1, '' );
				foreach ( $row as $c => $v ) {
					$full[ $c ] = $v;
				}
				$row = $full;
			}
			$out[] = $row;
		}
		return $out;
	}
}
