<?php
/**
 * Etkinlikler: sertifikalar bir etkinlik (ör. 11 Kasım fidan dikimi) altında toplanır.
 * Kapatılan etkinlik arşive düşer: sertifikaları geçerli kalır, yeni sertifika eklenemez.
 */

defined( 'ABSPATH' ) || exit;

function flxs_get_event( $id ) {
	global $wpdb;
	$id = (int) $id;
	return $id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxs_table( 'events' ) . ' WHERE id = %d', $id ), ARRAY_A ) : null;
}

/** @param string $status '' (hepsi) | open | closed */
function flxs_get_events( $status = '' ) {
	global $wpdb;
	$t = flxs_table( 'events' );
	if ( $status ) {
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status = %s ORDER BY (event_date IS NULL), event_date DESC, id DESC", $status ), ARRAY_A ); // phpcs:ignore
	}
	return (array) $wpdb->get_results( "SELECT * FROM $t ORDER BY status = 'closed', (event_date IS NULL), event_date DESC, id DESC", ARRAY_A ); // phpcs:ignore
}

function flxs_event_label( $id ) {
	$e = flxs_get_event( $id );
	return $e ? $e['name'] : '';
}

/** Seçim listeleri için kısa biçim */
function flxs_events_for_js( $status = '' ) {
	return array_map(
		function ( $e ) {
			return array(
				'id'     => (int) $e['id'],
				'name'   => $e['name'],
				'date'   => $e['event_date'] ? $e['event_date'] : '',
				'status' => $e['status'],
				'target' => (int) $e['target'],
			);
		},
		flxs_get_events( $status )
	);
}

/** Yeni atama için geçerli mi? Kapalı etkinliğe sertifika eklenmez. 0 = etkinliksiz. */
function flxs_valid_event( $id, $allow_closed = false ) {
	$e = flxs_get_event( $id );
	if ( ! $e ) {
		return 0;
	}
	return ( 'open' === $e['status'] || $allow_closed ) ? (int) $e['id'] : 0;
}

function flxs_save_event( $data, $id = 0 ) {
	global $wpdb;
	$name = trim( sanitize_text_field( $data['name'] ?? '' ) );
	if ( '' === $name ) {
		return new WP_Error( 'flxs_event', 'Etkinlik adı boş olamaz.' );
	}
	$date = sanitize_text_field( $data['event_date'] ?? '' );
	$ann  = sanitize_text_field( $data['loc_announce'] ?? '' );
	$ymd  = '/^\d{4}-\d{2}-\d{2}$/';
	$row  = array(
		'name'         => $name,
		'event_date'   => preg_match( $ymd, $date ) ? $date : null,
		'event_time'   => substr( sanitize_text_field( $data['event_time'] ?? '' ), 0, 20 ),
		'target'       => max( 0, (int) ( $data['target'] ?? 0 ) ),
		'base_count'   => max( 0, (int) ( $data['base_count'] ?? 0 ) ),
		'summary'      => sanitize_textarea_field( $data['summary'] ?? '' ),
		'image_id'     => max( 0, (int) ( $data['image_id'] ?? 0 ) ),
		'loc_name'     => sanitize_text_field( $data['loc_name'] ?? '' ),
		'loc_address'  => sanitize_textarea_field( $data['loc_address'] ?? '' ),
		'loc_map'      => esc_url_raw( flxs_event_map_url( $data['loc_map'] ?? '' ) ),
		'loc_announce' => preg_match( $ymd, $ann ) ? $ann : null,
		'note'         => sanitize_textarea_field( $data['note'] ?? '' ),
		'updated_at'   => flxs_now(),
	);
	if ( isset( $data['post_id'] ) ) {
		$pid            = (int) $data['post_id'];
		$row['post_id'] = ( $pid > 0 && 'etkinlik' === get_post_type( $pid ) ) ? $pid : 0;
	}
	if ( $id ) {
		$wpdb->update( flxs_table( 'events' ), $row, array( 'id' => (int) $id ) );
	} else {
		$row['status']     = 'open';
		$row['created_by'] = get_current_user_id();
		$row['created_at'] = flxs_now();
		$wpdb->insert( flxs_table( 'events' ), $row );
		$id = (int) $wpdb->insert_id;
	}
	if ( ! empty( $data['create_post'] ) ) {
		flxs_event_ensure_post( $id );
	}
	// Görsel sitedeki etkinlik sayfasının öne çıkan görseli olur
	$e = flxs_get_event( $id );
	if ( $e && (int) $e['post_id'] && (int) $e['image_id'] && (int) get_post_thumbnail_id( (int) $e['post_id'] ) !== (int) $e['image_id'] ) {
		set_post_thumbnail( (int) $e['post_id'], (int) $e['image_id'] );
	}
	flxs_log( 'event_saved', 'event', $id, $name );
	return (int) $id;
}

/**
 * Etkinliğin sitede bir sayfası (etkinlik yazısı) olmasını sağlar: bağlı yazı yoksa ya da silindiyse
 * aynı adlı yayınlanmış etkinlik yazısına bağlar, o da yoksa yeni yazı oluşturur.
 * $publish: bağlı yazı taslak/beklemede/özel ise yayınlar.
 */
function flxs_event_ensure_post( $id, $publish = false ) {
	global $wpdb;
	$e = flxs_get_event( $id );
	if ( ! $e || ! post_type_exists( 'etkinlik' ) ) {
		return 0;
	}
	$post = (int) $e['post_id'] ? get_post( (int) $e['post_id'] ) : null;
	if ( $post && 'etkinlik' === $post->post_type && 'trash' !== $post->post_status ) {
		if ( $publish && in_array( $post->post_status, array( 'draft', 'pending', 'private', 'auto-draft' ), true ) ) {
			wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'publish' ) );
		}
		return (int) $post->ID;
	}
	$pid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'etkinlik' AND post_status = 'publish' AND post_title = %s ORDER BY ID DESC LIMIT 1", $e['name'] ) );
	if ( $pid && ! flxs_event_by_post( $pid ) ) {
		$wpdb->update( flxs_table( 'events' ), array( 'post_id' => $pid ), array( 'id' => (int) $id ) );
		return $pid;
	}
	$pid = wp_insert_post(
		array(
			'post_type'    => 'etkinlik',
			'post_status'  => 'publish',
			'post_title'   => $e['name'],
			'post_excerpt' => (string) $e['summary'],
			'post_content' => $e['summary'] ? '<!-- wp:paragraph --><p>' . esc_html( $e['summary'] ) . '</p><!-- /wp:paragraph -->' : '',
		),
		true
	);
	if ( is_wp_error( $pid ) ) {
		return 0;
	}
	$wpdb->update( flxs_table( 'events' ), array( 'post_id' => (int) $pid ), array( 'id' => (int) $id ) );
	if ( (int) $e['image_id'] ) {
		set_post_thumbnail( (int) $pid, (int) $e['image_id'] );
	}
	return (int) $pid;
}

/* Kurulu sitelerde açık etkinliklerin sitede yayınlanmış sayfası olsun: bir kez bağla/oluştur/yayınla (v1.1.4) */
add_action(
	'init',
	function () {
		if ( (int) get_option( 'flxs_ev_post_migration', 0 ) >= 2 || ! post_type_exists( 'etkinlik' ) ) {
			return;
		}
		update_option( 'flxs_ev_post_migration', 2 );
		foreach ( flxs_get_events() as $e ) {
			flxs_event_ensure_post( (int) $e['id'], 'open' === $e['status'] );
		}
	},
	99
);

/** Harita alanı: Google/Apple/OSM linki ya da "41.01, 28.97" koordinatı → link */
function flxs_event_map_url( $v ) {
	$v = trim( (string) $v );
	if ( preg_match( '/^(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)$/', $v, $m ) ) {
		return 'https://www.google.com/maps?q=' . $m[1] . ',' . $m[2];
	}
	return preg_match( '#^https?://#i', $v ) ? $v : '';
}

/** Takipli konum yönlendirmesinin yolu: /konum/{etkinlik tarihi | id} (filtre flxs_location_slug) */
function flxs_location_slug() {
	$slug = sanitize_title( (string) apply_filters( 'flxs_location_slug', 'konum' ) );
	return $slug ? $slug : 'konum';
}

/**
 * Etkinliğin takipli konum linki. QR ve sitedeki "Yol tarifi" bu linki kullanır;
 * tıklama sayılır, sonra harita adresine yönlendirilir.
 *
 * @param array  $e   Etkinlik satırı.
 * @param string $src qr | web
 */
function flxs_event_location_link( $e, $src = 'qr' ) {
	$key = $e['event_date'] && 1 === flxs_events_on_date( $e['event_date'] ) ? $e['event_date'] : (string) (int) $e['id'];
	$url = home_url( '/' . flxs_location_slug() . '/' . $key . '/' );
	return 'qr' === $src ? $url : add_query_arg( 'k', 'web', $url );
}

/** O tarihteki etkinlik sayısı (tarihli link yalnız tekse kullanılır) */
function flxs_events_on_date( $ymd ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxs_table( 'events' ) . ' WHERE event_date = %s', $ymd ) );
}

/** Konumun gerçek harita adresi (takipsiz) */
function flxs_event_map_target( $e ) {
	if ( $e['loc_map'] ) {
		return (string) $e['loc_map'];
	}
	$q = flxs_event_map_query( $e );
	return $q ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $q ) : '';
}

/** /konum/{anahtar}: tıklamayı say, haritaya (konum yoksa etkinlik sayfasına) yönlendir */
function flxs_location_redirect( $key ) {
	global $wpdb;
	$e = null;
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $key ) ) {
		$e = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxs_table( 'events' ) . ' WHERE event_date = %s ORDER BY id DESC LIMIT 1', $key ), ARRAY_A );
	} else {
		$e = flxs_get_event( (int) $key );
	}
	nocache_headers();
	if ( ! $e ) {
		wp_safe_redirect( home_url( '/' ), 302 );
		return;
	}
	$src    = isset( $_GET['k'] ) && 'web' === $_GET['k'] ? 'web' : 'qr'; // phpcs:ignore WordPress.Security.NonceVerification
	$target = flxs_event_map_target( $e );
	$ua     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore
	$bot    = '' === $ua || preg_match( '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|curl|wget/i', $ua );
	if ( $target && ! $bot ) {
		$col = 'web' === $src ? 'loc_hits_web' : 'loc_hits_qr';
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . flxs_table( 'events' ) . " SET $col = $col + 1 WHERE id = %d", (int) $e['id'] ) ); // phpcs:ignore
		if ( function_exists( 'flxan_track_event' ) ) {
			flxan_track_event( 'event_location', $e['name'] . ' · ' . $src );
		}
		do_action( 'flxs_location_hit', $e, $src );
	}
	if ( ! $target ) {
		$post = (int) $e['post_id'] && 'publish' === get_post_status( (int) $e['post_id'] ) ? get_permalink( (int) $e['post_id'] ) : home_url( '/' );
		wp_safe_redirect( $post, 302 );
		return;
	}
	// Harita adresi dış site (Google Maps vb.); panelden giriliyor ve kayıtta doğrulanıyor.
	wp_redirect( esc_url_raw( $target ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
}

/** Haritada gösterilecek sorgu: koordinat varsa o, yoksa adres */
function flxs_event_map_query( $e ) {
	$q = '';
	if ( $e['loc_map'] && preg_match( '/[?&](?:q|query|ll)=(-?[\d.]+),\s*(-?[\d.]+)/', $e['loc_map'], $m ) ) {
		$q = $m[1] . ',' . $m[2];
	} elseif ( $e['loc_map'] && preg_match( '#@(-?[\d.]+),(-?[\d.]+)#', $e['loc_map'], $m ) ) {
		$q = $m[1] . ',' . $m[2];
	} elseif ( trim( (string) $e['loc_address'] ) ) {
		$q = trim( preg_replace( '/\s+/', ' ', $e['loc_name'] . ' ' . $e['loc_address'] ) );
	}
	return $q;
}

/** Sitede görünen fidan = sertifikalardaki fidan + elle girilen ek adet */
function flxs_event_fidan( $e ) {
	static $tot = null;
	if ( null === $tot ) {
		$tot = flxs_event_totals();
	}
	$t = $tot[ (int) $e['id'] ] ?? array( 'fidan' => 0, 'certs' => 0, 'people' => 0 );
	return array( 'fidan' => (int) $t['fidan'] + (int) $e['base_count'], 'certs' => (int) $t['certs'], 'people' => (int) $t['people'], 'cert_fidan' => (int) $t['fidan'] );
}

/** Ziyaretçiye gösterilecek etkinlik özeti */
function flxs_event_public( $e ) {
	$pid   = (int) $e['post_id'];
	$post  = $pid ? get_post( $pid ) : null;
	$live  = $post && 'publish' === $post->post_status;
	$img   = (int) $e['image_id'] ? (int) $e['image_id'] : ( $pid ? (int) get_post_thumbnail_id( $pid ) : 0 );
	$f     = flxs_event_fidan( $e );
	$ann   = $e['loc_announce'] ? $e['loc_announce'] : ( $e['event_date'] ? gmdate( 'Y-m-d', strtotime( $e['event_date'] . ' -10 days' ) ) : '' );
	$sum   = $e['summary'] ? $e['summary'] : ( $post ? (string) $post->post_excerpt : '' );
	return array(
		'id'        => (int) $e['id'],
		'name'      => $e['name'],
		'url'       => $live ? get_permalink( $post ) : '',
		'img'       => $img ? wp_get_attachment_image_url( $img, 'large' ) : '',
		'img_id'    => $img,
		'date'      => (string) $e['event_date'],
		'time'      => (string) $e['event_time'],
		'summary'   => $sum,
		'fidan'     => $f['fidan'],
		'people'    => $f['people'],
		'target'    => (int) $e['target'],
		'pct'       => $e['target'] ? min( 100, round( $f['fidan'] / $e['target'] * 100 ) ) : 0,
		'status'    => $e['status'],
		'loc_name'  => (string) $e['loc_name'],
		'loc_addr'  => (string) $e['loc_address'],
		'loc_map'   => (string) $e['loc_map'],
		'map_q'     => flxs_event_map_query( $e ),
		'announce'  => $ann,
		'has_loc'   => '' !== trim( $e['loc_name'] . $e['loc_address'] . $e['loc_map'] ),
		'loc_link'  => flxs_event_map_target( $e ) ? flxs_event_location_link( $e, 'web' ) : '',
	);
}

/** Sitedeki etkinlik yazısına bağlı sertifika etkinliği */
function flxs_event_by_post( $post_id ) {
	global $wpdb;
	return $post_id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxs_table( 'events' ) . ' WHERE post_id = %d ORDER BY id DESC LIMIT 1', (int) $post_id ), ARRAY_A ) : null;
}

function flxs_set_event_status( $id, $status ) {
	global $wpdb;
	$e = flxs_get_event( $id );
	if ( ! $e || ! in_array( $status, array( 'open', 'closed' ), true ) || $e['status'] === $status ) {
		return false;
	}
	$wpdb->update(
		flxs_table( 'events' ),
		array( 'status' => $status, 'closed_at' => 'closed' === $status ? flxs_now() : null, 'updated_at' => flxs_now() ),
		array( 'id' => (int) $id )
	);
	flxs_log( 'closed' === $status ? 'event_closed' : 'event_reopened', 'event', $id, $e['name'] );
	return true;
}

/** Etkinliği siler; sertifikaları silinmez, etkinliksiz kalır. */
function flxs_delete_event( $id ) {
	global $wpdb;
	$e = flxs_get_event( $id );
	if ( ! $e ) {
		return false;
	}
	$wpdb->update( flxs_table( 'certificates' ), array( 'event_id' => 0 ), array( 'event_id' => (int) $id ) );
	$wpdb->delete( flxs_table( 'events' ), array( 'id' => (int) $id ) );
	flxs_log( 'event_deleted', 'event', $id, $e['name'] );
	return true;
}

/** Sertifikanın etkinliğini değiştirir (kapalı etkinliğe taşınamaz). */
function flxs_set_certificate_event( $cert_id, $event_id ) {
	global $wpdb;
	$cert = flxs_get_certificate( $cert_id );
	if ( ! $cert ) {
		return new WP_Error( 'flxs_404', 'Sertifika bulunamadı.' );
	}
	$event_id = (int) $event_id;
	if ( (int) $cert['event_id'] === $event_id ) {
		return true;
	}
	if ( $event_id && ! flxs_valid_event( $event_id ) ) {
		return new WP_Error( 'flxs_event', 'Bu etkinlik kapalı ya da yok. Kapalı etkinliğe sertifika eklenemez.' );
	}
	$wpdb->update( flxs_table( 'certificates' ), array( 'event_id' => $event_id, 'updated_at' => flxs_now() ), array( 'id' => (int) $cert_id ) );
	flxs_log( 'certificate_updated', 'certificate', $cert_id, $cert['number'], array( 'changes' => array( 'etkinlik' => array( flxs_event_label( $cert['event_id'] ), flxs_event_label( $event_id ) ) ) ) );
	return true;
}

/** Etkinlik başına fidan / sertifika / kişi toplamları: [event_id => [...]] (0 = etkinliksiz) */
function flxs_event_totals() {
	global $wpdb;
	$out  = array();
	$rows = $wpdb->get_results( 'SELECT event_id, person_id, data, created_at FROM ' . flxs_table( 'certificates' ) . " WHERE status <> 'revoked'", ARRAY_A ); // phpcs:ignore
	foreach ( (array) $rows as $r ) {
		$e = (int) $r['event_id'];
		if ( ! isset( $out[ $e ] ) ) {
			$out[ $e ] = array( 'fidan' => 0, 'certs' => 0, 'people' => array(), 'first' => $r['created_at'], 'last' => $r['created_at'] );
		}
		$out[ $e ]['fidan'] += flxs_cert_fidan( $r['data'] );
		$out[ $e ]['certs']++;
		if ( (int) $r['person_id'] ) {
			$out[ $e ]['people'][ (int) $r['person_id'] ] = 1;
		}
		$out[ $e ]['first'] = min( $out[ $e ]['first'], $r['created_at'] );
		$out[ $e ]['last']  = max( $out[ $e ]['last'], $r['created_at'] );
	}
	foreach ( $out as &$o ) {
		$o['people'] = count( $o['people'] );
	}
	return $out;
}
