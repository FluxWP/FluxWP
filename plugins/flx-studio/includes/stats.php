<?php
/**
 * Yönetici panosu: kullanım, üretimler, harcama. Üretim sayıları Stüdyo kayıtlarından,
 * harcama Flux AI kayıtlarından (source = flx-studio; görsel, video ve metin istekleri) gelir.
 *
 * @package FLX_Studio
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pano verisi.
 *
 * @param int $days 7–365 gün.
 * @return array
 */
function flxst_stats( $days = 30 ) {
	global $wpdb;
	$days  = max( 1, min( 365, (int) $days ) );
	$off   = (int) wp_timezone()->getOffset( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) );
	$today = (int) floor( ( time() + $off ) / DAY_IN_SECONDS );
	$first = $today - $days + 1;
	$since = $first * DAY_IN_SECONDS - $off;
	$t     = flxst_table( 'items' );

	// Gün dizini (boş günler de görünsün).
	$day = array();
	for ( $d = $first; $d <= $today; $d++ ) {
		$day[ $d ] = array(
			'date'   => gmdate( 'Y-m-d', $d * DAY_IN_SECONDS ),
			'image'  => 0,
			'video'  => 0,
			'failed' => 0,
			'cost'   => 0.0,
		);
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT FLOOR((created + %d) / 86400) AS d, kind, state, COUNT(*) AS n FROM {$t} WHERE created >= %d GROUP BY d, kind, state", $off, $since ), ARRAY_A );
	$tot  = array(
		'image'     => 0,
		'video'     => 0,
		'failed'    => 0,
		'cancelled' => 0,
		'pending'   => 0,
	);
	foreach ( (array) $rows as $r ) {
		$d = (int) $r['d'];
		$n = (int) $r['n'];
		if ( 'done' === $r['state'] ) {
			$k          = 'video' === $r['kind'] ? 'video' : 'image';
			$tot[ $k ] += $n;
			if ( isset( $day[ $d ] ) ) {
				$day[ $d ][ $k ] += $n;
			}
		} elseif ( 'failed' === $r['state'] ) {
			$tot['failed'] += $n;
			if ( isset( $day[ $d ] ) ) {
				$day[ $d ]['failed'] += $n;
			}
		} elseif ( 'cancelled' === $r['state'] ) {
			$tot['cancelled'] += $n;
		} else {
			$tot['pending'] += $n;
		}
	}

	$by_model = $wpdb->get_results( $wpdb->prepare( "SELECT model, kind, SUM(state = 'done') AS done, SUM(state = 'failed') AS failed, SUM(cost) AS cost FROM {$t} WHERE created >= %d GROUP BY model, kind ORDER BY done DESC LIMIT 20", $since ), ARRAY_A );
	$by_prov  = $wpdb->get_results( $wpdb->prepare( "SELECT provider, SUM(state = 'done') AS done, SUM(state = 'failed') AS failed, SUM(cost) AS cost FROM {$t} WHERE created >= %d AND provider <> '' GROUP BY provider ORDER BY done DESC", $since ), ARRAY_A );
	$by_user  = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, SUM(state = 'done' AND kind = 'image') AS image, SUM(state = 'done' AND kind = 'video') AS video, SUM(cost) AS cost, MAX(created) AS last FROM {$t} WHERE created >= %d GROUP BY user_id ORDER BY (image + video) DESC LIMIT 12", $since ), ARRAY_A );
	$bytes    = (float) $wpdb->get_var( "SELECT SUM(bytes) FROM {$t} WHERE state = 'done'" );
	$errors   = $wpdb->get_results( $wpdb->prepare( "SELECT id, model, provider, error, created FROM {$t} WHERE created >= %d AND state = 'failed' ORDER BY created DESC LIMIT 6", $since ), ARRAY_A );

	// Harcama: Flux AI kayıtları (tek doğru kaynak; başarısız ama ücretlenen istekler ve metin istekleri dahil).
	$spend = array(
		'total' => 0.0,
		'media' => 0.0,
		'text'  => 0.0,
		'est'   => false,
		'ok'    => false,
	);
	if ( function_exists( 'flxai_table' ) ) {
		$l  = flxai_table( 'log' );
		$sr = $wpdb->get_results( $wpdb->prepare( "SELECT FLOOR((ts + %d) / 86400) AS d, model, SUM(cost) AS cost, MAX(est) AS est FROM {$l} WHERE source = 'flx-studio' AND kind = 'model' AND ts >= %d GROUP BY d, model", $off, $since ), ARRAY_A );
		if ( is_array( $sr ) ) {
			$spend['ok'] = true;
			$media_ids   = array_keys( (array) ( flxst_models()['models'] ?? array() ) );
			foreach ( $sr as $r ) {
				$c = (float) $r['cost'];
				// Medya satırı "sağlayıcı:katalog-modeli" biçiminde; gerisi metin (Geliştir, Beyin fırtınası).
				$parts           = explode( ':', (string) $r['model'], 2 );
				$text            = ! isset( $parts[1] ) || ! in_array( $parts[1], $media_ids, true );
				$spend['total'] += $c;
				$spend[ $text ? 'text' : 'media' ] += $c;
				$spend['est']    = $spend['est'] || ! empty( $r['est'] );
				if ( isset( $day[ (int) $r['d'] ] ) ) {
					$day[ (int) $r['d'] ]['cost'] += $c;
				}
			}
		}
	}
	// phpcs:enable

	if ( ! $spend['ok'] ) {
		// Flux AI kayıtları okunamazsa Stüdyo'nun kendi tutarları.
		foreach ( (array) $by_model as $r ) {
			$spend['total'] += (float) $r['cost'];
		}
		$spend['media'] = $spend['total'];
	}

	$users = array();
	foreach ( (array) $by_user as $r ) {
		$u       = get_userdata( (int) $r['user_id'] );
		$users[] = array(
			'id'     => (int) $r['user_id'],
			'name'   => $u ? $u->display_name : __( 'Deleted user', 'flx-studio' ),
			'avatar' => get_avatar_url( (int) $r['user_id'], array( 'size' => 48 ) ),
			'image'  => (int) $r['image'],
			'video'  => (int) $r['video'],
			'cost'   => round( (float) $r['cost'], 4 ),
			'last'   => (int) $r['last'],
		);
	}
	$cat    = flxst_models();
	$models = array();
	foreach ( (array) $by_model as $r ) {
		if ( '' === (string) $r['model'] ) {
			continue;
		}
		$models[] = array(
			'id'     => (string) $r['model'],
			'label'  => (string) ( $cat['models'][ $r['model'] ]['label'] ?? $r['model'] ),
			'kind'   => (string) $r['kind'],
			'done'   => (int) $r['done'],
			'failed' => (int) $r['failed'],
			'cost'   => round( (float) $r['cost'], 4 ),
		);
	}
	$provs = array();
	foreach ( (array) $by_prov as $r ) {
		$provs[] = array(
			'id'     => (string) $r['provider'],
			'label'  => (string) ( $cat['providers'][ $r['provider'] ]['label'] ?? $r['provider'] ),
			'done'   => (int) $r['done'],
			'failed' => (int) $r['failed'],
			'cost'   => round( (float) $r['cost'], 4 ),
		);
	}
	$errs = array();
	foreach ( (array) $errors as $r ) {
		$errs[] = array(
			'id'       => (int) $r['id'],
			'model'    => (string) ( $cat['models'][ $r['model'] ]['label'] ?? $r['model'] ),
			'provider' => (string) ( $cat['providers'][ $r['provider'] ]['label'] ?? $r['provider'] ),
			'error'    => wp_strip_all_tags( (string) $r['error'] ),
			'created'  => (int) $r['created'],
		);
	}
	foreach ( $day as &$x ) {
		$x['cost'] = round( $x['cost'], 4 );
	}
	unset( $x );

	return array(
		'days'      => $days,
		'totals'    => array(
			'image'     => $tot['image'],
			'video'     => $tot['video'],
			'failed'    => $tot['failed'],
			'cancelled' => $tot['cancelled'],
			'pending'   => $tot['pending'],
			'users'     => count( $users ),
			'bytes'     => $bytes,
		),
		'spend'     => array(
			'total' => round( $spend['total'], 4 ),
			'media' => round( $spend['media'], 4 ),
			'text'  => round( $spend['text'], 4 ),
			'est'   => $spend['est'],
			'from'  => $spend['ok'] ? 'flx-ai' : 'flx-studio',
		),
		'daily'     => array_values( $day ),
		'models'    => $models,
		'providers' => $provs,
		'users'     => $users,
		'errors'    => $errs,
		'budget'    => function_exists( 'flxai_budget_status' ) ? flxai_budget_status( true ) : null,
		'log_url'   => current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=flx-ai-log' ) : '',
	);
}
