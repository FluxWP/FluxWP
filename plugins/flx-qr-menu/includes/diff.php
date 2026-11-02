<?php
/**
 * Değişiklik özeti: eski ve yeni menü JSON'unu karşılaştırıp okunur satırlar üretir
 * ("Samsa: tükendi", "Karkade: fiyat 150 → 100", "Künefe eklendi" …).
 *
 * @package FLX_QR_Menu
 */

defined( 'ABSPATH' ) || exit;

function flxq_join_summary( $lines, $max = 15 ) {
	$lines = array_values( array_unique( array_filter( (array) $lines, 'strlen' ) ) );
	$more  = count( $lines ) - $max;
	if ( $more > 0 ) {
		$lines   = array_slice( $lines, 0, $max );
		/* translators: %d: number of further changes */
		$lines[] = sprintf( _n( '%d değişiklik daha', '%d değişiklik daha', $more, 'flx-qr-menu' ), $more );
	}
	return implode( '; ', $lines );
}

function flxq_fmt_price( $v ) {
	if ( null === $v || '' === $v ) {
		return '—';
	}
	if ( is_int( $v ) ) {
		return number_format_i18n( $v );
	}
	if ( is_float( $v ) ) {
		return number_format_i18n( $v, floor( $v ) == $v ? 0 : 2 ); // phpcs:ignore Universal.Operators.StrictComparisons
	}
	return (string) $v;
}

function flxq_field_label( $f ) {
	$labels = array(
		'aciklama'    => __( 'açıklama', 'flx-qr-menu' ),
		'birim'       => __( 'birim', 'flx-qr-menu' ),
		'gorsel'      => __( 'görsel', 'flx-qr-menu' ),
		'rozet'       => __( 'rozet', 'flx-qr-menu' ),
		'link'        => __( 'bağlantı', 'flx-qr-menu' ),
		'buyukGorsel' => __( 'büyük görsel', 'flx-qr-menu' ),
		'bittiNot'    => __( 'tükendi notu', 'flx-qr-menu' ),
	);
	return isset( $labels[ $f ] ) ? $labels[ $f ] : $f;
}

/** Ürün ya da ara başlığın özette görünen adı. */
function flxq_item_label( $u ) {
	if ( array_key_exists( 'araBaslik', $u ) ) {
		/* translators: %s: sub-heading text */
		return sprintf( __( '"%s" ara başlığı', 'flx-qr-menu' ), (string) $u['araBaslik'] );
	}
	return '' !== (string) ( isset( $u['ad'] ) ? $u['ad'] : '' ) ? (string) $u['ad'] : __( '(adsız ürün)', 'flx-qr-menu' );
}

function flxq_item_key( $u ) {
	return array_key_exists( 'araBaslik', $u ) ? 'h:' . mb_strtolower( (string) $u['araBaslik'] ) : 'p:' . mb_strtolower( isset( $u['ad'] ) ? (string) $u['ad'] : '' );
}

/** Aynı adlı ürünler için anahtar sonuna sıra eklenir. */
function flxq_keyed_items( $items ) {
	$out  = array();
	$seen = array();
	foreach ( (array) $items as $i => $u ) {
		if ( ! is_array( $u ) ) {
			continue;
		}
		$k          = flxq_item_key( $u );
		$seen[ $k ] = isset( $seen[ $k ] ) ? $seen[ $k ] + 1 : 1;
		$out[ $seen[ $k ] > 1 ? $k . '#' . $seen[ $k ] : $k ] = array( 'i' => $i, 'u' => $u );
	}
	return $out;
}

/** İki ürün sürümü arasındaki alan değişiklikleri. */
function flxq_item_changes( $a, $b ) {
	$name  = flxq_item_label( $b );
	$lines = array();
	$bool  = function ( $x, $f ) {
		return ! empty( $x[ $f ] );
	};
	if ( $bool( $a, 'bitti' ) !== $bool( $b, 'bitti' ) ) {
		/* translators: %s: item name */
		$lines[] = sprintf( $bool( $b, 'bitti' ) ? __( '%s: tükendi', 'flx-qr-menu' ) : __( '%s: tekrar satışta', 'flx-qr-menu' ), $name );
	}
	if ( $bool( $a, 'gizli' ) !== $bool( $b, 'gizli' ) ) {
		/* translators: %s: item name */
		$lines[] = sprintf( $bool( $b, 'gizli' ) ? __( '%s: gizlendi', 'flx-qr-menu' ) : __( '%s: gösterildi', 'flx-qr-menu' ), $name );
	}
	foreach ( array( 'fiyat', 'eskiFiyat' ) as $f ) {
		$x = isset( $a[ $f ] ) ? $a[ $f ] : null;
		$y = isset( $b[ $f ] ) ? $b[ $f ] : null;
		if ( flxq_fmt_price( $x ) !== flxq_fmt_price( $y ) ) {
			$lines[] = 'fiyat' === $f
				/* translators: 1: item name, 2: old price, 3: new price */
				? sprintf( __( '%1$s: fiyat %2$s → %3$s', 'flx-qr-menu' ), $name, flxq_fmt_price( $x ), flxq_fmt_price( $y ) )
				/* translators: 1: item name, 2: old price, 3: new price */
				: sprintf( __( '%1$s: eski fiyat %2$s → %3$s', 'flx-qr-menu' ), $name, flxq_fmt_price( $x ), flxq_fmt_price( $y ) );
		}
	}
	$other = array();
	$keys  = array_unique( array_merge( array_keys( $a ), array_keys( $b ) ) );
	foreach ( $keys as $f ) {
		if ( in_array( $f, array( 'ad', 'araBaslik', 'bitti', 'gizli', 'fiyat', 'eskiFiyat' ), true ) ) {
			continue;
		}
		$x = array_key_exists( $f, $a ) ? $a[ $f ] : null;
		$y = array_key_exists( $f, $b ) ? $b[ $f ] : null;
		// Yok / false / "" aynı sayılır (ör. rozet: false ile alanın olmaması).
		if ( ( empty( $x ) && empty( $y ) ) || $x === $y ) {
			continue;
		}
		$other[] = flxq_field_label( $f );
	}
	if ( $other ) {
		/* translators: 1: item name, 2: comma separated field names */
		$lines[] = sprintf( __( '%1$s: %2$s değişti', 'flx-qr-menu' ), $name, implode( ', ', $other ) );
	}
	return $lines;
}

/**
 * @param array $old Eski menü (dizi).
 * @param array $new Yeni menü (dizi).
 * @return string[]
 */
function flxq_diff_summary( $old, $new ) {
	$lines = array();
	$oe    = isset( $old['etkinlik'] ) && is_array( $old['etkinlik'] ) ? $old['etkinlik'] : array();
	$ne    = isset( $new['etkinlik'] ) && is_array( $new['etkinlik'] ) ? $new['etkinlik'] : array();
	$get   = function ( $arr, $k ) {
		return isset( $arr[ $k ] ) ? $arr[ $k ] : null;
	};
	$event = array(
		'baslik' => __( 'Etkinlik başlığı değişti', 'flx-qr-menu' ),
		'slogan' => __( 'Slogan değişti', 'flx-qr-menu' ),
		'gunler' => __( 'Etkinlik günleri değişti', 'flx-qr-menu' ),
		'site'   => __( 'Site adresi değişti', 'flx-qr-menu' ),
	);
	foreach ( $event as $f => $msg ) {
		if ( $get( $oe, $f ) != $get( $ne, $f ) ) { // phpcs:ignore Universal.Operators.StrictComparisons
			$lines[] = $msg;
		}
	}
	if ( $get( $oe, 'kapanis' ) !== $get( $ne, 'kapanis' ) ) {
		$k       = $get( $ne, 'kapanis' );
		$lines[] = $k ? __( 'Menü kapatıldı (teşekkür sayfası)', 'flx-qr-menu' ) : __( 'Menü aktif edildi', 'flx-qr-menu' );
	}
	$rest_o = array_diff_key( $oe, $event, array( 'kapanis' => 1 ) );
	$rest_n = array_diff_key( $ne, $event, array( 'kapanis' => 1 ) );
	if ( $rest_o != $rest_n ) { // phpcs:ignore Universal.Operators.StrictComparisons
		$lines[] = __( 'Etkinlik bilgisi değişti', 'flx-qr-menu' );
	}
	if ( $get( $old, 'genelBilgi' ) != $get( $new, 'genelBilgi' ) ) { // phpcs:ignore Universal.Operators.StrictComparisons
		$lines[] = __( 'Genel bilgiler değişti', 'flx-qr-menu' );
	}
	$top_o = array_diff_key( $old, array( 'etkinlik' => 1, 'genelBilgi' => 1, 'kategoriler' => 1 ) );
	$top_n = array_diff_key( $new, array( 'etkinlik' => 1, 'genelBilgi' => 1, 'kategoriler' => 1 ) );
	if ( $top_o != $top_n ) { // phpcs:ignore Universal.Operators.StrictComparisons
		$lines[] = __( 'Menü verisi değişti', 'flx-qr-menu' );
	}

	$ocats = array();
	foreach ( isset( $old['kategoriler'] ) ? (array) $old['kategoriler'] : array() as $k ) {
		if ( is_array( $k ) && isset( $k['id'] ) ) {
			$ocats[ $k['id'] ] = $k;
		}
	}
	$ncats = array();
	foreach ( isset( $new['kategoriler'] ) ? (array) $new['kategoriler'] : array() as $k ) {
		if ( is_array( $k ) && isset( $k['id'] ) ) {
			$ncats[ $k['id'] ] = $k;
		}
	}
	$cat_name = function ( $k ) {
		return '' !== (string) ( isset( $k['baslik'] ) ? $k['baslik'] : '' ) ? (string) $k['baslik'] : (string) $k['id'];
	};

	$common = array_values( array_intersect( array_keys( $ocats ), array_keys( $ncats ) ) );
	$order  = array_values( array_intersect( array_keys( $ncats ), $common ) );
	if ( $common !== $order ) {
		$lines[] = __( 'Kategori sırası değişti', 'flx-qr-menu' );
	}

	$removed = array(); // Kategoriler arası taşıma tespiti için.
	$added   = array();

	foreach ( $ocats as $id => $k ) {
		if ( ! isset( $ncats[ $id ] ) ) {
			/* translators: %s: category name */
			$lines[] = sprintf( __( 'Kategori "%s" silindi', 'flx-qr-menu' ), $cat_name( $k ) );
		}
	}
	foreach ( $ncats as $id => $k ) {
		if ( ! isset( $ocats[ $id ] ) ) {
			/* translators: %s: category name */
			$lines[] = sprintf( __( 'Kategori "%s" eklendi', 'flx-qr-menu' ), $cat_name( $k ) );
			foreach ( flxq_keyed_items( isset( $k['urunler'] ) ? $k['urunler'] : array() ) as $key => $x ) {
				$added[ $key ][] = array( 'cat' => $cat_name( $k ), 'u' => $x['u'], 'silent' => true );
			}
			continue;
		}
		$o = $ocats[ $id ];
		if ( $get( $o, 'baslik' ) !== $get( $k, 'baslik' ) ) {
			/* translators: 1: old category name, 2: new category name */
			$lines[] = sprintf( __( 'Kategori "%1$s" adı "%2$s" oldu', 'flx-qr-menu' ), $cat_name( $o ), $cat_name( $k ) );
		}
		$cf = array();
		if ( (string) $get( $o, 'not' ) !== (string) $get( $k, 'not' ) ) {
			$cf[] = __( 'not', 'flx-qr-menu' );
		}
		if ( $get( $o, 'ikon' ) !== $get( $k, 'ikon' ) ) {
			$cf[] = __( 'ikon', 'flx-qr-menu' );
		}
		if ( ( false !== $get( $o, 'sekme' ) ) !== ( false !== $get( $k, 'sekme' ) ) ) {
			$cf[] = __( 'sekme', 'flx-qr-menu' );
		}
		if ( $cf ) {
			/* translators: 1: category name, 2: comma separated field names */
			$lines[] = sprintf( __( 'Kategori "%1$s": %2$s değişti', 'flx-qr-menu' ), $cat_name( $k ), implode( ', ', $cf ) );
		}

		$oi = flxq_keyed_items( isset( $o['urunler'] ) ? $o['urunler'] : array() );
		$ni = flxq_keyed_items( isset( $k['urunler'] ) ? $k['urunler'] : array() );
		$rm = array_diff_key( $oi, $ni );
		$ad = array_diff_key( $ni, $oi );
		// Aynı sırada bir silinen + bir eklenen aynı türde ürün: ad değişikliği.
		foreach ( $rm as $rk => $r ) {
			foreach ( $ad as $ak => $a ) {
				if ( $r['i'] === $a['i'] && $rk[0] === $ak[0] ) {
					/* translators: 1: old name, 2: new name */
					$lines[] = sprintf( __( '%1$s adı "%2$s" oldu', 'flx-qr-menu' ), flxq_item_label( $r['u'] ), array_key_exists( 'araBaslik', $a['u'] ) ? $a['u']['araBaslik'] : $a['u']['ad'] );
					$lines   = array_merge( $lines, flxq_item_changes( $r['u'], $a['u'] ) );
					unset( $rm[ $rk ], $ad[ $ak ] );
					break;
				}
			}
		}
		foreach ( $rm as $rk => $r ) {
			$removed[ $rk ][] = array( 'cat' => $cat_name( $k ), 'u' => $r['u'] );
		}
		foreach ( $ad as $ak => $a ) {
			$added[ $ak ][] = array( 'cat' => $cat_name( $k ), 'u' => $a['u'], 'silent' => false );
		}
		$moved = false;
		$seq_o = array_values( array_intersect( array_keys( $oi ), array_keys( $ni ) ) );
		$seq_n = array_values( array_intersect( array_keys( $ni ), array_keys( $oi ) ) );
		foreach ( $seq_n as $key ) {
			$lines = array_merge( $lines, flxq_item_changes( $oi[ $key ]['u'], $ni[ $key ]['u'] ) );
		}
		if ( $seq_o !== $seq_n ) {
			$moved = true;
		}
		if ( $moved ) {
			/* translators: %s: category name */
			$lines[] = sprintf( __( '"%s" sıralaması değişti', 'flx-qr-menu' ), $cat_name( $k ) );
		}
	}
	// Silinen kategorilerin ürünleri de taşınmış olabilir.
	foreach ( $ocats as $id => $k ) {
		if ( ! isset( $ncats[ $id ] ) ) {
			foreach ( flxq_keyed_items( isset( $k['urunler'] ) ? $k['urunler'] : array() ) as $key => $x ) {
				$removed[ $key ][] = array( 'cat' => $cat_name( $k ), 'u' => $x['u'], 'silent' => true );
			}
		}
	}
	foreach ( $removed as $key => $list ) {
		foreach ( $list as $r ) {
			if ( ! empty( $added[ $key ] ) ) {
				$a = array_shift( $added[ $key ] );
				/* translators: 1: item name, 2: category name */
				$lines[] = sprintf( __( '%1$s, "%2$s" kategorisine taşındı', 'flx-qr-menu' ), flxq_item_label( $a['u'] ), $a['cat'] );
				$lines   = array_merge( $lines, flxq_item_changes( $r['u'], $a['u'] ) );
			} elseif ( empty( $r['silent'] ) ) {
				/* translators: %s: item name */
				$lines[] = sprintf( __( '%s silindi', 'flx-qr-menu' ), flxq_item_label( $r['u'] ) );
			}
		}
	}
	foreach ( $added as $list ) {
		foreach ( $list as $a ) {
			if ( empty( $a['silent'] ) ) {
				/* translators: %s: item name */
				$lines[] = sprintf( __( '%s eklendi', 'flx-qr-menu' ), flxq_item_label( $a['u'] ) );
			}
		}
	}
	if ( ! $lines && $old != $new ) { // phpcs:ignore Universal.Operators.StrictComparisons
		$lines[] = __( 'Menü verisi değişti', 'flx-qr-menu' );
	}
	return $lines;
}
