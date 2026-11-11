<?php
/**
 * Flux Donate 2.11: Excel / CSV import of donations and tracked records (moved here from Qurban and
 * made generic).
 *
 * Flow: upload → the header row and columns are recognised → values that repeat (donation type,
 * partner, brought in by, status, referral, paid) are matched once, the person corrects the matches
 * on the preview → rows are written in small steps (AJAX; Hostinger stops PHP after ~60 s). Every
 * row becomes one WooCommerce order through flxb_create_order(); the same row is never imported twice
 * (items.import_key).
 *
 * An upload is kept in option flxb_import_<id> (not autoloaded); the last 10 are kept.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Import {

	const STEP = 12;

	public static function enabled() {
		return (bool) apply_filters( 'flxb_import_enabled', flxb_track_enabled() );
	}

	/* ---------- Reading ---------- */

	/**
	 * xlsx → [ sheet name => rows ]. Dates stay Excel serial numbers.
	 *
	 * @return array|WP_Error
	 */
	public static function read_xlsx( $path, $max_rows = 20000 ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'flxb_import', __( 'The server has no zip extension; save the file as CSV and upload that.', 'flx-donate' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'flxb_import', __( 'The file could not be opened. Upload Excel (.xlsx) or CSV.', 'flx-donate' ) );
		}
		$prev   = libxml_use_internal_errors( true );
		$xml    = function ( $name ) use ( $zip ) {
			$s = $zip->getFromName( $name );
			return false === $s ? null : simplexml_load_string( $s, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT );
		};
		$shared = array();
		$ss     = $xml( 'xl/sharedStrings.xml' );
		if ( $ss ) {
			foreach ( $ss->si as $si ) {
				if ( isset( $si->t ) ) {
					$shared[] = (string) $si->t;
				} else {
					$t = '';
					foreach ( $si->r as $r ) {
						$t .= (string) $r->t;
					}
					$shared[] = $t;
				}
			}
		}
		$sheets = array();
		$wb     = $xml( 'xl/workbook.xml' );
		$rels   = $xml( 'xl/_rels/workbook.xml.rels' );
		$target = array();
		if ( $rels ) {
			foreach ( $rels->Relationship as $r ) {
				$target[ (string) $r['Id'] ] = ltrim( preg_replace( '#^/?xl/#', '', (string) $r['Target'] ), '/' );
			}
		}
		if ( $wb ) {
			foreach ( $wb->sheets->sheet as $sh ) {
				$rid = (string) $sh->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )['id'];
				if ( isset( $target[ $rid ] ) && 'hidden' !== (string) $sh['state'] ) {
					$sheets[ (string) $sh['name'] ] = 'xl/' . $target[ $rid ];
				}
			}
		}
		$out = array();
		foreach ( $sheets as $name => $file ) {
			$x = $xml( $file );
			if ( ! $x || ! isset( $x->sheetData ) ) {
				continue;
			}
			$rows = array();
			foreach ( $x->sheetData->row as $row ) {
				$cells = array();
				foreach ( $row->c as $c ) {
					$col = self::col( preg_replace( '/\d+/', '', (string) $c['r'] ) );
					$t   = (string) $c['t'];
					if ( 's' === $t ) {
						$v = $shared[ (int) $c->v ] ?? '';
					} elseif ( 'inlineStr' === $t ) {
						$v = (string) $c->is->t;
					} else {
						$v = isset( $c->v ) ? (string) $c->v : '';
					}
					$cells[ $col ] = $v;
				}
				if ( ! $cells || '' === trim( implode( '', $cells ) ) ) {
					continue;
				}
				$line = array_fill( 0, max( array_keys( $cells ) ) + 1, '' );
				foreach ( $cells as $i => $v ) {
					$line[ $i ] = $v;
				}
				$rows[] = $line;
				if ( count( $rows ) >= $max_rows ) {
					break;
				}
			}
			$out[ $name ] = $rows;
		}
		libxml_use_internal_errors( $prev );
		$zip->close();
		return $out ? $out : new WP_Error( 'flxb_import', __( 'No sheet to read was found in the file.', 'flx-donate' ) );
	}

	/** "AB" → 27 (0 based). */
	private static function col( $letters ) {
		$n = 0;
		foreach ( str_split( strtoupper( (string) $letters ) ) as $ch ) {
			$n = $n * 26 + ( ord( $ch ) - 64 );
		}
		return max( 0, $n - 1 );
	}

	public static function read_csv( $path, $max_rows = 20000 ) {
		$raw = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
		if ( ! mb_check_encoding( $raw, 'UTF-8' ) ) {
			$raw = mb_convert_encoding( $raw, 'UTF-8', 'Windows-1254' );
		}
		$first = (string) strtok( $raw, "\n" );
		$delim = substr_count( $first, ';' ) > substr_count( $first, ',' ) ? ';' : ( substr_count( $first, "\t" ) > substr_count( $first, ',' ) ? "\t" : ',' );
		$rows  = array();
		$fh    = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $fh, $raw ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		rewind( $fh );
		while ( ( $r = fgetcsv( $fh, 0, $delim ) ) !== false && count( $rows ) < $max_rows ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
			if ( '' !== trim( implode( '', $r ) ) ) {
				$rows[] = $r;
			}
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return array( 'CSV' => $rows );
	}

	/* ---------- Fields and columns ---------- */

	/** field => [label, header aliases (folded)]. Extend with flxb_import_fields. */
	public static function fields() {
		return (array) apply_filters(
			'flxb_import_fields',
			array(
				'created'     => array( __( 'Date', 'flx-donate' ), array( 'tarih', 'zaman damgasi', 'kayit tarihi', 'date', 'created', 'timestamp' ) ),
				'donor'       => array( __( 'Donor', 'flx-donate' ), array( 'bagisci', 'bagis yapan', 'donor', 'ad soyad', 'adi soyadi', 'isim', 'name' ) ),
				'donor_phone' => array( __( 'Donor phone', 'flx-donate' ), array( 'telefon', 'tel', 'gsm', 'cep', 'phone', 'bagisci telefon' ) ),
				'email'       => array( __( 'E-mail', 'flx-donate' ), array( 'e posta', 'eposta', 'email', 'e mail', 'mail' ) ),
				'name'        => array( __( 'On behalf of', 'flx-donate' ), array( 'kimin adina', 'adina', 'hisse sahibi', 'kurban sahibi', 'sahibi', 'on behalf', 'beneficiary' ) ),
				'phone'       => array( __( 'Phone (on behalf of)', 'flx-donate' ), array( 'adina telefon', 'sahip telefon', 'sahibi telefon', 'beneficiary phone' ) ),
				'type'        => array( __( 'Donation type', 'flx-donate' ), array( 'bagis turu', 'turu', 'tur', 'type', 'urun', 'proje', 'kurban turu', 'kurbanin turu' ) ),
				'option'      => array( __( 'Option', 'flx-donate' ), array( 'secenek', 'paket', 'option' ) ),
				'quantity'    => array( __( 'Quantity', 'flx-donate' ), array( 'adet', 'sayi', 'quantity', 'qty', 'hisse sayisi' ) ),
				'amount'      => array( __( 'Amount', 'flx-donate' ), array( 'tutar', 'ucret', 'fiyat', 'amount', 'price', 'toplam', 'bagis tutari' ) ),
				'currency'    => array( __( 'Currency', 'flx-donate' ), array( 'para birimi', 'doviz', 'currency' ) ),
				'paid'        => array( __( 'Payment', 'flx-donate' ), array( 'odeme', 'odendi', 'tahsil', 'tahsilat', 'payment', 'paid' ) ),
				'status'      => array( __( 'Status', 'flx-donate' ), array( 'durum', 'status', 'asama' ) ),
				'partner'     => array( FLXB_Track::label( 'partner' ), array( 'kesim yeri', 'dikim alani', 'dikim yeri', 'is ortagi', 'partner', 'kesimhane', 'uygulama yeri' ) ),
				'source'      => array( __( 'Brought in by', 'flx-donate' ), array( 'kurum', 'kaynak', 'sube', 'getiren', 'source' ) ),
				'target'      => array( __( 'Target date', 'flx-donate' ), array( 'istenen tarih', 'hedef tarih', 'gerceklesmesi istenen', 'kesim tarihi', 'kesilmesi istenen', 'dikim tarihi', 'target' ) ),
				'ref'         => array( __( 'Referral', 'flx-donate' ), array( 'referans', 'personel', 'takip eden', 'temsilci', 'referral' ) ),
				'note'        => array( __( 'Note', 'flx-donate' ), array( 'not', 'aciklama', 'note' ) ),
			)
		);
	}

	/** Field of a header cell: the longest matching alias wins ("kesim yeri" over "kesim tarihi"). */
	public static function field_for( $header ) {
		$h    = flxb_fold( $header );
		$best = array( '', 0 );
		if ( '' === $h ) {
			return '';
		}
		foreach ( self::fields() as $f => $def ) {
			foreach ( $def[1] as $alias ) {
				if ( ( $h === $alias || preg_match( '/(^| )' . preg_quote( $alias, '/' ) . '( |$)/', $h ) ) && strlen( $alias ) > $best[1] ) {
					$best = array( $f, strlen( $alias ) );
				}
			}
		}
		return $best[0];
	}

	/** Best sheet and header row: the row (of the first 10) with most known columns. */
	public static function detect( array $sheets ) {
		$best = array( '', 0, array(), 0 );
		foreach ( $sheets as $name => $rows ) {
			foreach ( array_slice( $rows, 0, 10, true ) as $i => $row ) {
				$map  = array();
				$used = array();
				foreach ( $row as $c => $v ) {
					$f = self::field_for( $v );
					if ( $f && ! isset( $used[ $f ] ) ) {
						$map[ $c ]  = $f;
						$used[ $f ] = 1;
					}
				}
				if ( count( $map ) > $best[3] ) {
					$best = array( $name, $i, $map, count( $map ) );
				}
			}
		}
		return $best;
	}

	/* ---------- Upload record ---------- */

	public static function get( $id ) {
		$id = preg_replace( '/[^a-z0-9]/', '', (string) $id );
		$d  = $id ? get_option( 'flxb_import_' . $id ) : null;
		return is_array( $d ) ? $d + array( 'id' => $id ) : null;
	}

	public static function save( array $d ) {
		update_option( 'flxb_import_' . $d['id'], $d, false );
		$list = (array) get_option( 'flxb_imports', array() );
		if ( ! in_array( $d['id'], $list, true ) ) {
			array_unshift( $list, $d['id'] );
			foreach ( array_slice( $list, 10 ) as $old ) {
				delete_option( 'flxb_import_' . $old );
			}
			update_option( 'flxb_imports', array_slice( $list, 0, 10 ), false );
		}
	}

	public static function delete( $id ) {
		delete_option( 'flxb_import_' . $id );
		update_option( 'flxb_imports', array_values( array_diff( (array) get_option( 'flxb_imports', array() ), array( $id ) ) ), false );
	}

	public static function all() {
		$out = array();
		foreach ( (array) get_option( 'flxb_imports', array() ) as $id ) {
			$d = self::get( $id );
			if ( $d ) {
				$out[] = $d;
			}
		}
		return $out;
	}

	/** @return array|WP_Error new upload */
	public static function from_file( $path, $filename ) {
		$ext    = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		$sheets = in_array( $ext, array( 'csv', 'txt', 'tsv' ), true ) ? self::read_csv( $path ) : self::read_xlsx( $path );
		if ( is_wp_error( $sheets ) ) {
			return $sheets;
		}
		list( $sheet, $hrow, $map ) = self::detect( $sheets );
		if ( '' === $sheet || count( $map ) < 2 ) {
			return new WP_Error( 'flxb_import', __( 'The header row was not recognised. The first row should name the columns, e.g. "Donor", "Phone", "Donation type", "Amount".', 'flx-donate' ) );
		}
		$types = FLXB_Types::all();
		$d     = array(
			'id'         => strtolower( wp_generate_password( 10, false ) ),
			'file'       => sanitize_file_name( (string) $filename ),
			'sheet'      => $sheet,
			'headers'    => array_map( 'strval', $sheets[ $sheet ][ $hrow ] ),
			'map'        => $map,
			'rows'       => array_values( array_slice( $sheets[ $sheet ], $hrow + 1 ) ),
			'res'        => array(),
			'opts'       => array( 'paid' => 1, 'keep_date' => 1, 'type' => $types ? (int) $types[0]['id'] . '|' : '' ),
			'state'      => 'preview',
			'pos'        => 0,
			'result'     => array( 'created' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => array() ),
			'created_by' => get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
		);
		self::save( $d );
		return $d;
	}

	public static function cell( array $imp, array $row, $field ) {
		$col = array_search( $field, $imp['map'], true );
		return false === $col ? '' : trim( (string) ( $row[ $col ] ?? '' ) );
	}

	/** Excel serial / "17.08.2026 01:29" / ISO → local "Y-m-d H:i:s"; '' when empty or unreadable. */
	public static function datetime( $v ) {
		$v = trim( (string) $v );
		if ( '' === $v ) {
			return '';
		}
		if ( is_numeric( $v ) && (float) $v > 20000 && (float) $v < 80000 ) {
			return gmdate( 'Y-m-d H:i:s', (int) round( ( (float) $v - 25569 ) * 86400 ) );
		}
		if ( preg_match( '/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?/', $v, $m ) && checkdate( (int) $m[2], (int) $m[1], (int) $m[3] ) ) {
			return sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $m[3], $m[2], $m[1], $m[4] ?? 0, $m[5] ?? 0, $m[6] ?? 0 );
		}
		$t = strtotime( $v );
		return $t ? gmdate( 'Y-m-d H:i:s', $t ) : '';
	}

	/** "1.250,50" / "1,250.50" / "1250" → 1250.5 */
	public static function number( $v ) {
		$v = preg_replace( '/[^\d.,\-]/', '', (string) $v );
		if ( '' === $v ) {
			return 0.0;
		}
		$c = strrpos( $v, ',' );
		$p = strrpos( $v, '.' );
		if ( false !== $c && ( false === $p || $c > $p ) ) {
			$v = str_replace( array( '.', ',' ), array( '', '.' ), $v );
		} else {
			$v = str_replace( ',', '', $v );
		}
		return (float) $v;
	}

	/* ---------- Matching (preview and run share it) ---------- */

	/** "type_id|option_key" choices for a select. */
	public static function type_choices() {
		$out = array();
		foreach ( FLXB_Types::all() as $t ) {
			$out[ (int) $t['id'] . '|' ] = $t['title'];
			foreach ( (array) FLXB_Types::clean_options( $t['options'] ) as $o ) {
				$out[ (int) $t['id'] . '|' . $o['key'] ] = $t['title'] . ' › ' . $o['label'];
			}
		}
		return $out;
	}

	private static function guess_type( $type_raw, $opt_raw, $fallback ) {
		$tk = flxb_fold( $type_raw );
		$ok = flxb_fold( $opt_raw );
		foreach ( FLXB_Types::all() as $t ) {
			$opts = (array) FLXB_Types::clean_options( $t['options'] );
			if ( '' !== $tk && ( flxb_fold( $t['title'] ) === $tk || $t['slug'] === sanitize_title( $type_raw ) ) ) {
				foreach ( $opts as $o ) {
					if ( '' !== $ok && flxb_fold( $o['label'] ) === $ok ) {
						return (int) $t['id'] . '|' . $o['key'];
					}
				}
				return (int) $t['id'] . '|';
			}
			foreach ( $opts as $o ) {
				if ( '' !== $tk && flxb_fold( $o['label'] ) === $tk ) {
					return (int) $t['id'] . '|' . $o['key'];
				}
			}
		}
		return '' === $tk && '' === $ok ? $fallback : '';
	}

	private static function guess_status( $v, $tid ) {
		$k = flxb_fold( $v );
		if ( '' === $k ) {
			return 'pending';
		}
		foreach ( FLXB_Track::statuses( $tid ) as $st => $label ) {
			if ( flxb_fold( $label ) === $k ) {
				return $st;
			}
		}
		$needles = (array) apply_filters(
			'flxb_import_status_words',
			array( 'iletil' => 'delivered', 'teslim' => 'delivered', 'deliver' => 'delivered', 'tamam' => 'done', 'kesil' => 'done', 'dikil' => 'done', 'done' => 'done', 'iptal' => 'canceled', 'cancel' => 'canceled', 'gonderil' => 'sent', 'vekalet' => 'sent', 'sent' => 'sent', 'onay' => 'approved', 'approv' => 'approved' )
		);
		foreach ( $needles as $needle => $st ) {
			if ( false !== strpos( $k, $needle ) ) {
				return $st;
			}
		}
		return 'pending';
	}

	private static function guess_paid( $v, $default ) {
		$k = flxb_fold( $v );
		if ( '' === $k ) {
			return $default;
		}
		foreach ( array( 'odenmedi', 'bekl', 'hayir', 'yok', 'no', 'unpaid', 'pending', 'borc' ) as $n ) {
			if ( false !== strpos( $k, $n ) ) {
				return 0;
			}
		}
		foreach ( array( 'odendi', 'evet', 'tahsil', 'tamam', 'yes', 'paid', 'nakit', 'havale', 'kart', 'eft' ) as $n ) {
			if ( false !== strpos( $k, $n ) ) {
				return 1;
			}
		}
		return '1' === $k ? 1 : ( '0' === $k ? 0 : $default );
	}

	private static function users() {
		$out = array();
		foreach ( get_users( array( 'fields' => array( 'ID', 'display_name' ), 'number' => 500 ) ) as $u ) {
			$out[ (int) $u->ID ] = $u->display_name;
		}
		return $out;
	}

	/**
	 * Preview data: value groups with their matches (the person's choices in $imp['res'] win) and every
	 * row resolved, with errors and warnings.
	 */
	public static function analyze( array $imp ) {
		global $wpdb;
		$res     = (array) $imp['res'];
		$def_pay = (int) ! empty( $imp['opts']['paid'] );
		$groups  = array();
		foreach ( array( 'type', 'partner', 'source', 'status', 'ref', 'paid' ) as $f ) {
			$groups[ $f ] = array();
		}
		foreach ( $imp['rows'] as $row ) {
			foreach ( array_keys( $groups ) as $f ) {
				$v = 'type' === $f ? self::cell( $imp, $row, 'type' ) . ' / ' . self::cell( $imp, $row, 'option' ) : self::cell( $imp, $row, $f );
				$k = flxb_fold( $v );
				if ( ! isset( $groups[ $f ][ $k ] ) ) {
					$groups[ $f ][ $k ] = array( 'raw' => trim( $v, ' /' ), 'n' => 0, 'row' => $row );
				}
				++$groups[ $f ][ $k ]['n'];
			}
		}
		foreach ( $groups['type'] as $k => &$g ) {
			$g['to'] = (string) ( $res['type'][ $k ] ?? self::guess_type( self::cell( $imp, $g['row'], 'type' ), self::cell( $imp, $g['row'], 'option' ), (string) ( $imp['opts']['type'] ?? '' ) ) );
		}
		unset( $g );
		foreach ( array( 'partner' => 'service', 'source' => 'source' ) as $f => $kind ) {
			foreach ( $groups[ $f ] as $k => &$g ) {
				$p       = FLXB_Partners::find( $g['raw'], $kind );
				$g['to'] = (string) ( $res[ $f ][ $k ] ?? ( '' === $k ? '0' : ( $p ? (string) $p['id'] : 'new' ) ) );
			}
			unset( $g );
		}
		foreach ( $groups['status'] as $k => &$g ) {
			$g['to'] = (string) ( $res['status'][ $k ] ?? self::guess_status( $g['raw'], 0 ) );
		}
		unset( $g );
		$users = self::users();
		foreach ( $groups['ref'] as $k => &$g ) {
			$match = 0;
			foreach ( $users as $uid => $name ) {
				if ( '' !== $k && flxb_fold( $name ) === $k ) {
					$match = $uid;
				}
			}
			$g['to'] = (string) ( $res['ref'][ $k ] ?? $match );
		}
		unset( $g );
		foreach ( $groups['paid'] as $k => &$g ) {
			$g['to'] = (string) ( $res['paid'][ $k ] ?? self::guess_paid( $g['raw'], $def_pay ) );
		}
		unset( $g );
		foreach ( $groups as &$gr ) {
			uasort( $gr, function ( $a, $b ) { return $b['n'] <=> $a['n']; } );
		}
		unset( $gr );

		$keys = array();
		foreach ( $imp['rows'] as $i => $row ) {
			$keys[ $i ] = self::row_key( $imp, $row );
		}
		$existing = array();
		foreach ( array_chunk( array_values( $keys ), 500 ) as $chunk ) {
			$in = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT import_key FROM ' . flxb_table( 'items' ) . " WHERE import_key IN ($in)", $chunk ) ) as $kk ) { // phpcs:ignore WordPress.DB
				$existing[ $kk ] = 1;
			}
		}
		$rows  = array();
		$stats = array( 'total' => 0, 'ok' => 0, 'dup' => 0, 'error' => 0, 'warn' => 0 );
		$seen  = array();
		foreach ( $imp['rows'] as $i => $row ) {
			++$stats['total'];
			$tk  = flxb_fold( self::cell( $imp, $row, 'type' ) . ' / ' . self::cell( $imp, $row, 'option' ) );
			$to  = explode( '|', (string) $groups['type'][ $tk ]['to'] . '|' );
			$tid = (int) $to[0];
			$t   = $tid ? FLXB_Types::get( $tid ) : null;
			$opt = (string) $to[1];
			if ( $t && '' === $opt ) {
				$o   = (array) FLXB_Types::clean_options( $t['options'] );
				$opt = $o ? (string) $o[0]['key'] : '';
			}
			$qty = (int) self::number( self::cell( $imp, $row, 'quantity' ) );
			$amt = self::cell( $imp, $row, 'amount' );
			$r   = array(
				'n'           => $i + 1,
				'key'         => $keys[ $i ],
				'donor'       => self::cell( $imp, $row, 'donor' ),
				'donor_phone' => self::cell( $imp, $row, 'donor_phone' ),
				'email'       => sanitize_email( self::cell( $imp, $row, 'email' ) ),
				'name'        => self::cell( $imp, $row, 'name' ),
				'phone'       => self::cell( $imp, $row, 'phone' ),
				'note'        => self::cell( $imp, $row, 'note' ),
				'type_id'     => $tid,
				'option'      => $opt,
				'qty'         => max( 1, min( 500, $qty ) ),
				'amount'      => '' === $amt ? null : self::number( $amt ),
				'currency'    => strtoupper( preg_replace( '/[^A-Za-z]/', '', self::cell( $imp, $row, 'currency' ) ) ),
				'partner'     => $groups['partner'][ flxb_fold( self::cell( $imp, $row, 'partner' ) ) ]['to'],
				'partner_raw' => self::cell( $imp, $row, 'partner' ),
				'source'      => $groups['source'][ flxb_fold( self::cell( $imp, $row, 'source' ) ) ]['to'],
				'source_raw'  => self::cell( $imp, $row, 'source' ),
				'status'      => $groups['status'][ flxb_fold( self::cell( $imp, $row, 'status' ) ) ]['to'],
				'ref'         => (int) $groups['ref'][ flxb_fold( self::cell( $imp, $row, 'ref' ) ) ]['to'],
				'paid'        => (int) $groups['paid'][ flxb_fold( self::cell( $imp, $row, 'paid' ) ) ]['to'],
				'created'     => self::datetime( self::cell( $imp, $row, 'created' ) ),
				'target'      => flxb_parse_date( self::cell( $imp, $row, 'target' ) ),
				'errors'      => array(),
				'warns'       => array(),
			);
			if ( 3 !== strlen( $r['currency'] ) ) {
				$r['currency'] = '';
			}
			if ( '' === $r['donor'] && '' === $r['name'] ) {
				$r['errors'][] = __( 'No donor or name', 'flx-donate' );
			}
			if ( ! $t ) {
				$r['errors'][] = __( 'No donation type', 'flx-donate' );
			}
			if ( null !== $r['amount'] && $r['amount'] <= 0 ) {
				$r['errors'][] = __( 'Amount is not a number', 'flx-donate' );
			}
			if ( $t && FLXB_Types::tracks_items( $t ) ) {
				if ( in_array( $r['status'], array( 'sent', 'done', 'delivered' ), true ) && '0' === (string) $r['partner'] ) {
					/* translators: %s: what a partner is called */
					$r['warns'][] = sprintf( __( 'No %s: opened as approved', 'flx-donate' ), FLXB_Track::label( 'partner', $tid ) );
					$r['status']  = 'approved';
				}
				if ( in_array( $r['status'], array( 'sent', 'done', 'delivered' ), true ) && ! $r['paid'] ) {
					$r['warns'][] = __( 'Not paid: opened as approved', 'flx-donate' );
					$r['status']  = 'approved';
				}
			} elseif ( $t && 'pending' !== $r['status'] ) {
				$r['status'] = 'pending'; // the type is not tracked: no steps
			}
			if ( '' === $r['target'] && '' !== self::cell( $imp, $row, 'target' ) ) {
				$r['warns'][] = __( 'Target date could not be read', 'flx-donate' );
			}
			if ( '' === $r['created'] && '' !== self::cell( $imp, $row, 'created' ) ) {
				$r['warns'][] = __( 'Date could not be read', 'flx-donate' );
			}
			if ( isset( $existing[ $r['key'] ] ) || isset( $seen[ $r['key'] ] ) ) {
				$r['dup'] = true;
				++$stats['dup'];
			} elseif ( $r['errors'] ) {
				++$stats['error'];
			} else {
				++$stats['ok'];
				if ( $r['warns'] ) {
					++$stats['warn'];
				}
			}
			$seen[ $r['key'] ] = 1;
			$rows[]            = $r;
		}
		return array( 'groups' => $groups, 'rows' => $rows, 'stats' => $stats );
	}

	/** The same row is never imported twice: key over every mapped cell. */
	public static function row_key( array $imp, array $row ) {
		$parts = array();
		foreach ( array_keys( self::fields() ) as $f ) {
			$parts[] = flxb_fold( self::cell( $imp, $row, $f ) );
		}
		return substr( sha1( implode( '|', $parts ) ), 0, 40 );
	}

	/* ---------- Writing ---------- */

	/** Open new partners and fix the matches (once, before the first step). */
	public static function prepare( array &$imp ) {
		$a = self::analyze( $imp );
		foreach ( array( 'partner' => 'is_service', 'source' => 'is_source' ) as $f => $flag ) {
			foreach ( $a['groups'][ $f ] as $k => $g ) {
				$to = $g['to'];
				if ( 'new' === $to && '' !== $k ) {
					$id = FLXB_Partners::save( array( 'name' => trim( $g['raw'] ), $flag => 1, 'is_service' => 'is_service' === $flag ? 1 : 0, 'note' => __( 'From an Excel import', 'flx-donate' ) ) );
					$to = is_wp_error( $id ) ? '0' : (string) $id;
				}
				$imp['res'][ $f ][ $k ] = $to;
			}
		}
		foreach ( array( 'type', 'status', 'ref', 'paid' ) as $f ) {
			foreach ( $a['groups'][ $f ] as $k => $g ) {
				$imp['res'][ $f ][ $k ] = $g['to'];
			}
		}
		$imp['state'] = 'running';
		$imp['pos']   = 0;
		self::save( $imp );
	}

	private static function fail( array &$imp, array $r, $msg, $count = true ) {
		if ( $count ) {
			++$imp['result']['failed'];
		}
		if ( count( $imp['result']['errors'] ) < 300 ) {
			$imp['result']['errors'][] = array( 'n' => $r['n'], 'name' => '' !== $r['donor'] ? $r['donor'] : $r['name'], 'msg' => $msg );
		}
	}

	private static function phone( $v ) {
		$n = flx_phone_normalize( $v, 'tr' );
		if ( '' !== $n ) {
			return $n;
		}
		$d = preg_replace( '/\D/', '', (string) $v ); // Keep an unrecognised number as typed.
		return '' !== $d ? '+' . $d : '';
	}

	/** Write one row. @return true|WP_Error */
	public static function write_row( array $imp, array $r ) {
		global $wpdb;
		$who   = '' !== $r['donor'] ? $r['donor'] : $r['name'];
		$parts = preg_split( '/\s+/u', trim( $who ), 2 );
		$line  = array(
			'type_id' => $r['type_id'],
			'option'  => $r['option'],
			'units'   => $r['qty'],
		);
		if ( null !== $r['amount'] ) {
			$line['amount'] = $r['amount'];
		}
		if ( '' !== $r['name'] ) {
			$line['beneficiaries'] = array_fill( 0, $r['qty'], $r['name'] );
			$line['name']          = $r['name'];
			$t                     = FLXB_Types::get( $r['type_id'] );
			if ( $t && FLXB_Types::requires_proxy( $t ) ) {
				$pt                    = flxb_proxy_text();
				$line['proxy_consent'] = array( 'version' => $pt ? (int) $pt['version'] : 0, 'accepted_at' => current_time( 'mysql', true ), 'source' => 'import' );
			}
		}
		$paid  = (bool) $r['paid'];
		$args  = array(
			'lines'          => array( $line ),
			'billing'        => array( 'first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? '', 'phone' => self::phone( $r['donor_phone'] ), 'email' => $r['email'] ),
			'payment_method' => $paid ? 'flxb-import' : 'flxb_havale',
			'payment_title'  => $paid ? __( 'Import', 'flx-donate' ) : '',
			'status'         => $paid ? 'completed' : 'on-hold',
			'created_via'    => 'flxb-import',
			'ref_user_id'    => $r['ref'],
			'note'           => sanitize_textarea_field( $r['note'] ),
			'currency'       => $r['currency'],
			'created_at'     => ! empty( $imp['opts']['keep_date'] ) ? $r['created'] : '',
		);
		$order = flxb_create_order( (array) apply_filters( 'flxb_import_order_args', $args, $r, $imp ) );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$items = array();
		foreach ( flxb_order_donation_ids( $order ) as $did ) {
			foreach ( FLXB_Items::for_donation( (int) $did ) as $it ) {
				$items[] = $it;
			}
		}
		$source = is_numeric( $r['source'] ) ? (int) $r['source'] : 0;
		$place  = is_numeric( $r['partner'] ) ? (int) $r['partner'] : 0;
		$at     = '' !== $args['created_at'] ? get_gmt_from_date( $args['created_at'] ) : '';
		foreach ( $items as $n => $it ) {
			$upd = array( 'import_key' => 0 === $n ? $r['key'] : substr( $r['key'], 0, 36 ) . '#' . $n );
			if ( $source ) {
				$upd['source_id'] = $source;
			}
			if ( '' !== $at ) {
				$upd['created_at'] = $at;
			}
			FLXB_Track::upd( (int) $it['id'], $upd );
			if ( '' === (string) $it['code'] ) {
				continue;
			}
			if ( '' !== $r['phone'] ) {
				FLXB_Track::edit( (int) $it['id'], (string) $it['beneficiary_name'], self::phone( $r['phone'] ), false );
			}
			if ( '' !== $r['target'] ) {
				FLXB_Track::set_target( (int) $it['id'], $r['target'], false );
			}
			$path = array( 'approved' => array( 'approve' ), 'sent' => array( 'approve', 'assign' ), 'done' => array( 'approve', 'assign', 'done' ), 'delivered' => array( 'approve', 'assign', 'done', 'delivered' ), 'canceled' => array( 'cancel' ) );
			foreach ( $path[ $r['status'] ] ?? array() as $step ) {
				$o  = array( 'note' => __( 'Excel import', 'flx-donate' ), 'force_paid' => $paid );
				if ( 'assign' === $step ) {
					$o['place'] = 'pt:' . $place;
				}
				$st = FLXB_Track::apply( array( (int) $it['id'] ), $step, $o );
				if ( empty( $st['done'] ) ) {
					/* translators: %s: status */
					return new WP_Error( 'flxb_import_status', sprintf( __( 'Opened, but the status "%s" could not be set', 'flx-donate' ), FLXB_Track::statuses( (int) $it['type_id'] )[ $r['status'] ] ?? $r['status'] ) );
				}
			}
		}
		do_action( 'flxb_import_row_done', $order, $items, $r, $imp );
		return true;
	}

	/** Write the next rows. @return array|WP_Error { pos, total, done, result } */
	public static function step( $id, $n = self::STEP ) {
		global $wpdb;
		$imp = self::get( $id );
		if ( ! $imp || 'running' !== $imp['state'] ) {
			return new WP_Error( 'flxb_import', __( 'The import was not found or has finished.', 'flx-donate' ) );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$a     = self::analyze( $imp );
		$total = count( $a['rows'] );
		$end   = min( $total, $imp['pos'] + max( 1, (int) $n ) );
		$start = microtime( true );
		for ( $i = $imp['pos']; $i < $end; $i++ ) {
			$r          = $a['rows'][ $i ];
			$imp['pos'] = $i + 1;
			if ( ! empty( $r['dup'] ) || $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . flxb_table( 'items' ) . ' WHERE import_key = %s LIMIT 1', $r['key'] ) ) ) { // phpcs:ignore WordPress.DB
				++$imp['result']['skipped'];
				continue;
			}
			if ( $r['errors'] ) {
				self::fail( $imp, $r, implode( ', ', $r['errors'] ) );
				continue;
			}
			$ok = self::write_row( $imp, $r );
			if ( is_wp_error( $ok ) ) {
				self::fail( $imp, $r, $ok->get_error_message(), 'flxb_import_status' !== $ok->get_error_code() );
				if ( 'flxb_import_status' === $ok->get_error_code() ) {
					++$imp['result']['created'];
				}
			} else {
				++$imp['result']['created'];
			}
			if ( microtime( true ) - $start > 40 ) {
				break;
			}
		}
		if ( $imp['pos'] >= $total ) {
			$imp['state']       = 'done';
			$imp['finished_at'] = current_time( 'mysql' );
		}
		self::save( $imp );
		return array( 'pos' => $imp['pos'], 'total' => $total, 'done' => 'done' === $imp['state'], 'result' => $imp['result'] );
	}
}
