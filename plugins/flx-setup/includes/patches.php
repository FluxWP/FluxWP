<?php
/**
 * Yamalar: AI sohbetinden ya da dış MCP'den gelen değişiklikler.
 *
 * Yama, flx-preset/1 biçiminde ama yalnız değişecek alanları içeren bir parçadır (id/name gerekmez).
 * Akış: önizle → (bekleyen yama) → onay → uygula. Uygulanan yama bir run olur, geri alınabilir.
 * Yerel yamalar "site" adlı sahte ön ayarın üzerine yazılır; böylece bir ön ayarın değerini değiştiren yama
 * o ön ayar için "elle değiştirildi" sayılır ve sonraki migration'lar onu ezmez.
 */

defined( 'ABSPATH' ) || exit;

const FLXK_SITE = 'site';

function flxk_patches() {
	$p = get_option( 'flxk_patches', array() );
	return is_array( $p ) ? $p : array();
}

function flxk_save_patches( array $all ) {
	update_option( 'flxk_patches', array_slice( $all, -50, null, true ), false );
}

/** Yamayı hazırlar ve doğrular. */
function flxk_patch_prepare( $patch ) {
	$data = flxk_load_json( $patch );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$data['format'] = $data['format'] ?? FLXK_FORMAT;
	unset( $data['migrations'] );
	$ok = flxk_validate( $data, true );
	return is_wp_error( $ok ) ? $ok : $data;
}

/** Önizleme: AI'ye ve panele özet. */
function flxk_patch_preview( $patch, array $opts = array() ) {
	$data = flxk_patch_prepare( $patch );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$plan = flxk_plan( FLXK_SITE, $data, $opts + array( 'mode' => 'apply' ) );
	return array(
		'counts'   => flxk_plan_counts( $plan ),
		'changes'  => flxk_plan_brief( $plan ),
		'warnings' => $plan['warnings'],
	);
}

/** Planın kısa listesi (aynı olanlar hariç). */
function flxk_plan_brief( array $plan, $limit = 80 ) {
	$out = array();
	foreach ( $plan['ops'] as $op ) {
		if ( 'same' === $op['status'] ) {
			continue;
		}
		$row = array(
			'target' => $op['target'],
			'status' => $op['status'],
			'old'    => flxk_preview_value( $op['old'] ),
			'new'    => flxk_preview_value( $op['new'] ),
		);
		if ( ! empty( $op['conflict'] ) ) {
			$row['owner'] = $op['conflict'];
		}
		$out[] = $row;
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

/** Bekleyen yama oluşturur (onay bekler). */
function flxk_patch_submit( $patch, $source = 'panel', $note = '' ) {
	$data = flxk_patch_prepare( $patch );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$id         = 'p' . substr( md5( wp_json_encode( $data ) . microtime() ), 0, 10 );
	$all        = flxk_patches();
	$all[ $id ] = array(
		'id'     => $id,
		'data'   => $data,
		'source' => sanitize_key( $source ),
		'note'   => sanitize_text_field( $note ),
		'at'     => current_time( 'mysql' ),
		'user'   => get_current_user_id(),
		'status' => 'pending',
	);
	flxk_save_patches( $all );
	do_action( 'flxk_patch_submitted', $id, $all[ $id ] );
	return $id;
}

/** Yamayı uygular (bekleyen id'si ya da doğrudan veri). */
function flxk_patch_apply( $id_or_patch, $source = 'panel', $note = '', array $opts = array() ) {
	$all = flxk_patches();
	$pid = is_string( $id_or_patch ) && isset( $all[ $id_or_patch ] ) ? $id_or_patch : '';
	if ( $pid ) {
		if ( 'pending' !== $all[ $pid ]['status'] ) {
			return new WP_Error( 'patch', __( 'This patch has already been processed.', 'flx-setup' ) );
		}
		$data   = $all[ $pid ]['data'];
		$source = $all[ $pid ]['source'];
		$note   = $all[ $pid ]['note'];
	} else {
		$data = flxk_patch_prepare( $id_or_patch );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
	}
	$res = flxk_execute(
		FLXK_SITE,
		$data,
		$opts + array( 'mode' => 'apply' ),
		array(
			'kind'   => 'patch',
			'title'  => $note ? $note : __( 'Patch', 'flx-setup' ),
			'source' => $source,
		)
	);
	if ( $pid && ! is_wp_error( $res ) ) {
		$all[ $pid ]['status'] = 'applied';
		$all[ $pid ]['run']    = $res['run'];
		flxk_save_patches( $all );
	}
	return $res;
}

function flxk_patch_reject( $id ) {
	$all = flxk_patches();
	if ( isset( $all[ $id ] ) && 'pending' === $all[ $id ]['status'] ) {
		$all[ $id ]['status'] = 'rejected';
		flxk_save_patches( $all );
		return true;
	}
	return false;
}

function flxk_pending_patches() {
	return array_filter(
		flxk_patches(),
		function ( $p ) {
			return 'pending' === ( $p['status'] ?? '' );
		}
	);
}
