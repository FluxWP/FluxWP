<?php
/**
 * Banka ekstresi düzenleri: tablo satırlarından ortak hareket listesi.
 *
 * Bilinen düzenler başlık imzasıyla tanınır (sütun => sadeleştirilmiş başlık). Banka sütun düzenleri
 * banka-ekstre projesinden uyarlandı (MIT, Copyright (c) 2026 Ahmet Said Kara). Tanınmayan dosyada
 * başlık satırı aranır: tarih + tutar (ya da borç/alacak) + açıklama sütunları.
 *
 * Yeni düzen: filtre `flxpos_bank_layouts` (id => name, sig, cols). cols: date, time, ref, desc, amount,
 * balance, currency (sütun indeksi).
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Bank_Layouts {

	const HEAD_SCAN = 60;

	public static function all() {
		$l = array(
			'ziraat'        => array(
				'name' => 'Ziraat Bankası',
				'sig'  => array( 0 => 'tarih', 1 => 'fisno', 2 => 'aciklama', 3 => 'islemtutari', 4 => 'bakiye' ),
				'cols' => array( 'date' => 0, 'ref' => 1, 'desc' => 2, 'amount' => 3, 'balance' => 4 ),
			),
			'yapikredi'     => array(
				'name' => 'Yapı Kredi',
				'sig'  => array( 0 => 'tarih', 1 => 'saat', 4 => 'referansno', 5 => 'aciklama', 6 => 'islemtutari' ),
				'cols' => array( 'date' => 0, 'time' => 1, 'ref' => 4, 'desc' => 5, 'amount' => 6, 'balance' => 7, 'currency' => 8 ),
			),
			'garanti'       => array(
				'name' => 'Garanti BBVA',
				'sig'  => array( 0 => 'tarih', 1 => 'aciklama', 2 => 'etiket', 3 => 'tutar', 5 => 'dekontno' ),
				'cols' => array( 'date' => 0, 'desc' => 1, 'amount' => 3, 'balance' => 4, 'ref' => 5 ),
			),
			'isbankasi'     => array(
				'name' => 'İş Bankası',
				'sig'  => array( 0 => 'tarihsaat', 1 => 'valor', 3 => 'islemtutari', 8 => 'aciklama' ),
				'cols' => array( 'date' => 0, 'amount' => 3, 'balance' => 4, 'desc' => 8, 'ref' => 14 ),
			),
			'turkiyefinans' => array(
				'name' => 'Türkiye Finans',
				'sig'  => array( 0 => 'islemtarihi', 1 => 'islemreferansi', 2 => 'aciklama', 3 => 'tutar' ),
				'cols' => array( 'date' => 0, 'ref' => 1, 'desc' => 2, 'amount' => 3, 'balance' => 4, 'currency' => 5 ),
			),
			'vakifbank'     => array(
				'name' => 'VakıfBank',
				'sig'  => array( 2 => 'harekettarih', 6 => 'tutar', 9 => 'islemno', 16 => 'aciklama' ),
				'cols' => array( 'date' => 2, 'amount' => 6, 'balance' => 7, 'ref' => 9, 'desc' => 16 ),
			),
		);
		return (array) apply_filters( 'flxpos_bank_layouts', $l );
	}

	/** Bankanın görünen adı. */
	public static function name( $id ) {
		$l = self::all();
		return isset( $l[ $id ] ) ? $l[ $id ]['name'] : ( 'auto' === $id ? __( 'Genel düzen', 'flx-pos' ) : (string) $id );
	}

	/** "İşlem Tutarı*" → "islemtutari". */
	public static function plain( $s ) {
		$s = strtr(
			(string) $s,
			array(
				'İ' => 'i',
				'I' => 'i',
				'ı' => 'i',
				'Ç' => 'c',
				'ç' => 'c',
				'Ğ' => 'g',
				'ğ' => 'g',
				'Ö' => 'o',
				'ö' => 'o',
				'Ş' => 's',
				'ş' => 's',
				'Ü' => 'u',
				'ü' => 'u',
				'Â' => 'a',
				'â' => 'a',
				'Î' => 'i',
				'î' => 'i',
				'Û' => 'u',
				'û' => 'u',
			)
		);
		return preg_replace( '/[^a-z0-9]/', '', strtolower( $s ) );
	}

	/** Metni sade parçalara böl. */
	public static function words( $s ) {
		$s = self::plain_spaced( $s );
		return array_values( array_filter( preg_split( '/[^a-z0-9]+/', $s ) ) );
	}

	private static function plain_spaced( $s ) {
		$s = preg_replace( '/[^\pL\pN]+/u', ' ', (string) $s );
		return implode( ' ', array_map( array( __CLASS__, 'plain' ), explode( ' ', $s ) ) );
	}

	/** Hücreden sayı: 1234.5, "1.234,56", "-1.234,56 TL", "1,234.56", "(12,00)". */
	public static function number( $v ) {
		if ( is_int( $v ) || is_float( $v ) ) {
			return (float) $v;
		}
		$s = trim( (string) $v );
		if ( '' === $s ) {
			return null;
		}
		$neg = (bool) preg_match( '/^\(.*\)$|^-|-$|\s(B|BORÇ|BORC)$/iu', $s );
		$s   = preg_replace( '/[^0-9.,]/', '', $s );
		if ( '' === $s || ! preg_match( '/\d/', $s ) ) {
			return null;
		}
		$lc = strrpos( $s, ',' );
		$ld = strrpos( $s, '.' );
		if ( false !== $lc && ( false === $ld || $lc > $ld ) ) {
			$s = str_replace( array( '.', ',' ), array( '', '.' ), $s );
		} elseif ( false !== $ld && false !== $lc ) {
			$s = str_replace( ',', '', $s );
		} elseif ( false !== $ld && substr_count( $s, '.' ) > 1 ) {
			$s = str_replace( '.', '', $s );
		}
		if ( ! is_numeric( $s ) ) {
			return null;
		}
		return $neg ? -(float) $s : (float) $s;
	}

	/** "10/01/2026", "10.01.2026 18:30", "2026-01-10" → array( 'Y-m-d', 'H:i' ). */
	public static function date( $v ) {
		$s = trim( (string) $v );
		if ( preg_match( '#^(\d{1,2})[./-](\d{1,2})[./-](\d{4})(?:[\sT-]+(\d{1,2}):(\d{2}))?#', $s, $m ) ) {
			$d = array( (int) $m[3], (int) $m[2], (int) $m[1] );
		} elseif ( preg_match( '#^(\d{4})-(\d{1,2})-(\d{1,2})(?:[\sT]+(\d{1,2}):(\d{2}))?#', $s, $m ) ) {
			$d = array( (int) $m[1], (int) $m[2], (int) $m[3] );
		} else {
			return null;
		}
		if ( ! checkdate( $d[1], $d[2], $d[0] ) ) {
			return null;
		}
		$time = isset( $m[4] ) && '' !== $m[4] ? sprintf( '%02d:%02d', (int) $m[4], (int) $m[5] ) : '';
		return array( sprintf( '%04d-%02d-%02d', $d[0], $d[1], $d[2] ), $time );
	}

	/**
	 * Satırlardan hareketler.
	 *
	 * @param array  $rows   FLXPOS_Sheet::read() çıktısı.
	 * @param string $bank   Düzen kimliği; boş = içerikten tanı.
	 * @return array|WP_Error array( 'bank' => id, 'items' => list )
	 */
	public static function parse( array $rows, $bank = '' ) {
		$layouts = self::all();
		$layout  = null;
		$start   = 0;
		if ( '' !== $bank && isset( $layouts[ $bank ] ) ) {
			$layout = $layouts[ $bank ];
			$h      = self::find_head( $rows, $layout['sig'] );
			$start  = null === $h ? 0 : $h + 1;
		} else {
			foreach ( $layouts as $id => $l ) {
				$h = self::find_head( $rows, $l['sig'] );
				if ( null !== $h ) {
					$bank   = $id;
					$layout = $l;
					$start  = $h + 1;
					break;
				}
			}
		}
		if ( ! $layout ) {
			$g = self::guess( $rows );
			if ( ! $g ) {
				return new WP_Error( 'flxpos_bank_layout', __( 'Ekstre düzeni tanınmadı: başlık satırında tarih, tutar ve açıklama sütunları bulunamadı.', 'flx-pos' ) );
			}
			$bank   = 'auto';
			$layout = array( 'cols' => $g['cols'] );
			$start  = $g['head'] + 1;
		}
		$c     = $layout['cols'];
		$items = array();
		$cnt   = count( $rows );
		for ( $i = $start; $i < $cnt; $i++ ) {
			$r  = (array) $rows[ $i ];
			$dt = self::date( $r[ $c['date'] ] ?? '' );
			if ( ! $dt ) {
				continue;
			}
			if ( isset( $c['credit'] ) || isset( $c['debit'] ) ) {
				$cr  = isset( $c['credit'] ) ? self::number( $r[ $c['credit'] ] ?? '' ) : null;
				$db  = isset( $c['debit'] ) ? self::number( $r[ $c['debit'] ] ?? '' ) : null;
				$amt = ( $cr ? abs( $cr ) : 0 ) - ( $db ? abs( $db ) : 0 );
				if ( null === $cr && null === $db ) {
					continue;
				}
			} else {
				$amt = self::number( $r[ $c['amount'] ] ?? '' );
				if ( null === $amt ) {
					continue;
				}
			}
			$time = $dt[1];
			if ( '' === $time && isset( $c['time'] ) && preg_match( '/^(\d{1,2}):(\d{2})/', (string) ( $r[ $c['time'] ] ?? '' ), $tm ) ) {
				$time = sprintf( '%02d:%02d', (int) $tm[1], (int) $tm[2] );
			}
			$bal = isset( $c['balance'] ) ? self::number( $r[ $c['balance'] ] ?? '' ) : null;
			$cur = isset( $c['currency'] ) ? strtoupper( trim( (string) ( $r[ $c['currency'] ] ?? '' ) ) ) : '';
			$cur = in_array( $cur, array( '', 'TL', 'TRL', 'YTL' ), true ) ? 'TRY' : substr( preg_replace( '/[^A-Z]/', '', $cur ), 0, 3 );
			$items[] = array(
				'date'     => $dt[0],
				'time'     => $time,
				'amount'   => round( (float) $amt, 2 ),
				'balance'  => null === $bal ? null : round( $bal, 2 ),
				'currency' => '' !== $cur ? $cur : 'TRY',
				'desc'     => self::cell( $r[ $c['desc'] ] ?? '' ),
				'ref'      => isset( $c['ref'] ) ? substr( self::cell( $r[ $c['ref'] ] ?? '' ), 0, 100 ) : '',
				'row'      => $i + 1,
			);
		}
		return array(
			'bank'  => $bank,
			'items' => $items,
		);
	}

	private static function cell( $v ) {
		if ( is_float( $v ) && floor( $v ) === $v && abs( $v ) < 1e15 ) {
			return (string) (int) $v;
		}
		return trim( preg_replace( '/\s+/u', ' ', (string) $v ) );
	}

	private static function find_head( array $rows, array $sig ) {
		foreach ( array_slice( $rows, 0, self::HEAD_SCAN ) as $i => $r ) {
			$ok = true;
			foreach ( $sig as $col => $name ) {
				if ( ! isset( $r[ $col ] ) || self::plain( $r[ $col ] ) !== $name ) {
					$ok = false;
					break;
				}
			}
			if ( $ok ) {
				return $i;
			}
		}
		return null;
	}

	/** Tanınmayan dosya: başlık satırını ve sütunları tahmin et. */
	private static function guess( array $rows ) {
		$names = array(
			'date'     => array( 'tarih', 'islemtarihi', 'tarihsaat', 'harekettarihi', 'harekettarih', 'islemtarihisaati', 'date', 'transactiondate', 'bookingdate' ),
			'time'     => array( 'saat', 'islemsaati', 'time' ),
			'amount'   => array( 'tutar', 'islemtutari', 'tutartl', 'miktar', 'amount', 'hareket', 'hareketttutari', 'hareketutari', 'islemtutaritl' ),
			'credit'   => array( 'alacak', 'giren', 'gelen', 'yatan', 'credit' ),
			'debit'    => array( 'borc', 'cikan', 'giden', 'cekilen', 'debit' ),
			'desc'     => array( 'aciklama', 'islemaciklamasi', 'description', 'detay', 'islemdetayi', 'hareketaciklamasi' ),
			'ref'      => array( 'referans', 'referansno', 'dekontno', 'fisno', 'islemno', 'islemreferansi', 'reference', 'refno' ),
			'balance'  => array( 'bakiye', 'balance', 'hesapbakiyesi', 'islemsonrasibakiye' ),
			'currency' => array( 'dovizcinsi', 'doviz', 'parabirimi', 'currency' ),
		);
		foreach ( array_slice( $rows, 0, self::HEAD_SCAN ) as $i => $r ) {
			$cols = array();
			foreach ( (array) $r as $ci => $cell ) {
				$p = self::plain( $cell );
				foreach ( $names as $k => $list ) {
					if ( ! isset( $cols[ $k ] ) && in_array( $p, $list, true ) ) {
						$cols[ $k ] = $ci;
					}
				}
			}
			$has_amount = isset( $cols['amount'] ) || isset( $cols['credit'] );
			if ( isset( $cols['date'], $cols['desc'] ) && $has_amount ) {
				if ( isset( $cols['amount'] ) ) {
					unset( $cols['credit'], $cols['debit'] );
				}
				return array(
					'head' => $i,
					'cols' => $cols,
				);
			}
		}
		return null;
	}
}
