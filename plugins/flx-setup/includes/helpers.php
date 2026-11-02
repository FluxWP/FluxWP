<?php
/**
 * Yardımcılar: dizi yolu, derin birleştirme, hash, gizli anahtar tespiti, durum kaydı.
 */

defined( 'ABSPATH' ) || exit;

/** Ön ayarlar yönetim yetkisi. */
function flxk_cap() {
	return (string) apply_filters( 'flxk_cap', 'manage_options' );
}

/**
 * Ön ayarlar ekranı tema ayarlarının içinde mi? Tema `add_theme_support( 'flx-setup-panel', [ 'page' => 'flx-ayarlar', 'arg' => 'bolum', 'value' => 'onayarlar' ] )` der.
 *
 * @return array{page:string,arg:string,value:string}|null
 */
function flxk_panel_in_theme() {
	if ( ! current_theme_supports( 'flx-setup-panel' ) ) {
		return null;
	}
	$s = get_theme_support( 'flx-setup-panel' );
	$s = is_array( $s ) && isset( $s[0] ) && is_array( $s[0] ) ? $s[0] : array();
	return array(
		'page'  => sanitize_key( $s['page'] ?? 'flx-ayarlar' ),
		'arg'   => sanitize_key( $s['arg'] ?? 'bolum' ),
		'value' => sanitize_key( $s['value'] ?? 'onayarlar' ),
	);
}

/** Ön ayarlar ekranının adresi (tema sekmesi ya da Görünüm › Ön ayarlar). */
function flxk_admin_url( array $args = array() ) {
	$t    = flxk_panel_in_theme();
	$base = $t ? add_query_arg( array( 'page' => $t['page'], $t['arg'] => $t['value'] ), admin_url( 'themes.php' ) ) : admin_url( 'themes.php?page=flx-presets' );
	return add_query_arg( $args, $base );
}

/** Şu an tema ayarlarındaki Ön ayarlar sekmesinde miyiz? */
function flxk_is_panel_screen() {
	$t = flxk_panel_in_theme();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	return $t && is_admin() && ( $_GET['page'] ?? '' ) === $t['page'] && ( $_GET[ $t['arg'] ] ?? '' ) === $t['value'];
	// phpcs:enable
}

/** Ardışık (liste) dizi mi? */
function flxk_is_list( $v ) {
	if ( ! is_array( $v ) ) {
		return false;
	}
	if ( array() === $v ) {
		return true;
	}
	return array_keys( $v ) === range( 0, count( $v ) - 1 );
}

/** Her elemanı "key" alanı olan nesne listesi mi? */
function flxk_is_keyed_list( $v ) {
	if ( ! flxk_is_list( $v ) || ! $v ) {
		return false;
	}
	foreach ( $v as $item ) {
		if ( ! is_array( $item ) || ! isset( $item['key'] ) || ! is_scalar( $item['key'] ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Derin birleştirme: nesneler anahtar anahtar, "key" alanlı listeler key ile birleşir, diğer listeler değişir.
 */
function flxk_merge( $base, $over ) {
	if ( array() === $over && is_array( $base ) && $base && ! flxk_is_list( $base ) ) {
		return $base; // Boş nesne değiştirmez; boş liste ise listeyi temizler.
	}
	if ( flxk_is_keyed_list( $over ) && ( flxk_is_keyed_list( $base ) || array() === $base ) ) {
		$out = array();
		foreach ( (array) $base as $item ) {
			$out[ (string) $item['key'] ] = $item;
		}
		foreach ( $over as $item ) {
			$k         = (string) $item['key'];
			$out[ $k ] = isset( $out[ $k ] ) ? flxk_merge( $out[ $k ], $item ) : $item;
		}
		return array_values( $out );
	}
	if ( is_array( $base ) && is_array( $over ) && ! flxk_is_list( $over ) && ! flxk_is_list( $base ) ) {
		foreach ( $over as $k => $v ) {
			$base[ $k ] = array_key_exists( $k, $base ) ? flxk_merge( $base[ $k ], $v ) : $v;
		}
		return $base;
	}
	return $over;
}

/**
 * Bir ayar ağacını yaprak yollara açar: [ 'a.b' => değer ]. Listeler (key'li listeler dahil) tek yaprak sayılır.
 *
 * @return array<string,mixed>
 */
function flxk_flatten( array $tree, $prefix = '' ) {
	$out = array();
	foreach ( $tree as $k => $v ) {
		$path = '' === $prefix ? (string) $k : $prefix . '.' . $k;
		if ( is_array( $v ) && $v && ! flxk_is_list( $v ) ) {
			$out += flxk_flatten( $v, $path );
		} else {
			$out[ $path ] = $v;
		}
	}
	return $out;
}

/** Yoldaki değer; yoksa $absent. */
function flxk_path_get( $arr, $path, $absent = null ) {
	if ( '' === $path ) {
		return $arr;
	}
	foreach ( explode( '.', $path ) as $seg ) {
		if ( ! is_array( $arr ) || ! array_key_exists( $seg, $arr ) ) {
			return $absent;
		}
		$arr = $arr[ $seg ];
	}
	return $arr;
}

/** Yola değer yazar ($unset true ise siler). */
function flxk_path_set( $arr, $path, $value, $unset = false ) {
	$arr  = is_array( $arr ) ? $arr : array();
	$segs = explode( '.', $path );
	$ref  = &$arr;
	$last = array_pop( $segs );
	foreach ( $segs as $seg ) {
		if ( ! isset( $ref[ $seg ] ) || ! is_array( $ref[ $seg ] ) ) {
			$ref[ $seg ] = array();
		}
		$ref = &$ref[ $seg ];
	}
	if ( $unset ) {
		unset( $ref[ $last ] );
	} else {
		$ref[ $last ] = $value;
	}
	unset( $ref );
	if ( $unset && $segs ) {
		// Silme yolunda boş kalan üst diziler de kalkar.
		for ( $i = count( $segs ); $i > 0; $i-- ) {
			$parent = implode( '.', array_slice( $segs, 0, $i ) );
			if ( array() === flxk_path_get( $arr, $parent ) ) {
				$arr = flxk_path_set( $arr, $parent, null, true );
				break;
			}
		}
	}
	return $arr;
}

/** Karşılaştırma için kararlı hash. */
function flxk_hash( $v ) {
	if ( is_array( $v ) ) {
		$v = flxk_ksort_deep( $v );
	}
	return md5( wp_json_encode( $v ) );
}

function flxk_ksort_deep( $v ) {
	if ( ! is_array( $v ) ) {
		return $v;
	}
	if ( ! flxk_is_list( $v ) ) {
		ksort( $v );
	}
	foreach ( $v as $k => $x ) {
		$v[ $k ] = flxk_ksort_deep( $x );
	}
	return $v;
}

/** Boş sayılan değer (yalnız boşları doldur modunda yazılır). */
function flxk_is_empty( $v ) {
	return null === $v || '' === $v || array() === $v || ( is_string( $v ) && '' === trim( $v ) );
}

/** Şifre, anahtar, token gibi gizli alan adı mı? Şablona yazılmaz, şablondan okunmaz. */
function flxk_is_secret_key( $key ) {
	$key = strtolower( (string) $key );
	$re  = (string) apply_filters( 'flxk_secret_key_regex', '/(pass(word)?|secret|token|api_?key|private|_pw$|_pass$|apikey|client_secret|license|salt|auth_key|webhook_key|^key$)/' );
	return (bool) preg_match( $re, $key );
}

/** Yolun herhangi bir parçası gizliyse true. */
function flxk_path_is_secret( $path ) {
	foreach ( explode( '.', (string) $path ) as $seg ) {
		if ( flxk_is_secret_key( $seg ) ) {
			return true;
		}
	}
	return false;
}

/** Değerin kısa gösterimi (önizleme tablosu). */
function flxk_preview_value( $v, $max = 140 ) {
	if ( null === $v ) {
		return '—';
	}
	if ( is_bool( $v ) ) {
		return $v ? 'true' : 'false';
	}
	if ( is_array( $v ) ) {
		$v = wp_json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
	$v = wp_strip_all_tags( (string) $v );
	return function_exists( 'mb_strimwidth' ) ? mb_strimwidth( $v, 0, $max, '…' ) : substr( $v, 0, $max );
}

/* ---------- Durum ---------- */

/**
 * Uygulanan ön ayarların durumu: [ id => { ver, applied_at, mode, owned, backup, created, migrations, log } ].
 */
function flxk_state( $id = null ) {
	$s = get_option( 'flxk_state', array() );
	$s = is_array( $s ) ? $s : array();
	if ( null === $id ) {
		return $s;
	}
	return isset( $s[ $id ] ) && is_array( $s[ $id ] ) ? $s[ $id ] : array();
}

function flxk_save_state( $id, $st ) {
	$all = flxk_state();
	if ( null === $st ) {
		unset( $all[ $id ] );
	} else {
		$all[ $id ] = $st;
	}
	update_option( 'flxk_state', $all, false );
}

/** Hedefin hangi ön ayara ait olduğu (son yazan) ve hash'i. */
function flxk_owner_of( $target, $except = '' ) {
	foreach ( flxk_state() as $id => $st ) {
		if ( $id !== $except && isset( $st['owned'][ $target ] ) ) {
			return array( $id, $st['owned'][ $target ] );
		}
	}
	return array( '', '' );
}

/** Geçmiş kaydı (son 200). */
function flxk_log( $entry ) {
	$log = get_option( 'flxk_history', array() );
	$log = is_array( $log ) ? $log : array();
	array_unshift(
		$log,
		$entry + array(
			'at'   => current_time( 'mysql' ),
			'user' => get_current_user_id(),
		)
	);
	update_option( 'flxk_history', array_slice( $log, 0, 200 ), false );
}

function flxk_history() {
	$log = get_option( 'flxk_history', array() );
	return is_array( $log ) ? $log : array();
}
