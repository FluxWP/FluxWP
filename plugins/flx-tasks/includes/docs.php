<?php
/**
 * Dokümanlar: projede (isteğe bağlı klasörde) çok sayfalı belgeler.
 *
 * Sayfa içeriği bloklar halinde saklanır (paragraf, başlık, liste, görsel, tablo…). Ortak düzenlemede her istemci
 * yalnız değiştirdiği blokları gönderir; aynı bloğu aynı anda iki kişi değiştirirse son yazan kazanır, farklı bloklar
 * birbirini ezmez. Sunucu her değişikliğe artan bir sürüm verir; istemci "şu sürümden sonrası" diye sorar.
 * Canlılık websocket değil hafif yoklama ile (paylaşımlı sunucuda süreç sınırı): bkz. flxg_rest_doc_sync.
 *
 * Yetki projeden gelir: görüntüleyen okur, düzenleyen yazar, yönetici dokümanı siler.
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * Veri
 * ====================================================================== */

function flxg_doc_row( $r ) {
	return array(
		'id'        => (int) $r['id'],
		'space_id'  => (int) $r['space_id'],
		'folder_id' => (int) $r['folder_id'],
		'title'     => (string) $r['title'],
		'icon'      => (string) $r['icon'],
		'owner_id'  => (int) $r['owner_id'],
		'position'  => (float) $r['position'],
		'treeRev'   => (int) $r['tree_rev'],
		'updated'   => flxg_iso( $r['updated_at'] ),
	);
}

function flxg_all_docs( $refresh = false ) {
	static $cache = null;
	if ( null === $cache || $refresh ) {
		global $wpdb;
		$cache = array();
		foreach ( (array) $wpdb->get_results( 'SELECT * FROM ' . flxg_table( 'docs' ) . ' WHERE archived = 0 ORDER BY position, id', ARRAY_A ) as $r ) { // phpcs:ignore
			$cache[ (int) $r['id'] ] = flxg_doc_row( $r );
		}
	}
	return $cache;
}

function flxg_get_doc( $id ) {
	$all = flxg_all_docs();
	return $all[ (int) $id ] ?? null;
}

function flxg_doc_role( $doc, $uid = null ) {
	if ( is_numeric( $doc ) ) {
		$doc = flxg_get_doc( (int) $doc );
	}
	return $doc ? flxg_space_role( (int) $doc['space_id'], $uid ) : '';
}

function flxg_can_doc( $doc, $need = 'view', $uid = null ) {
	return flxg_role_rank( flxg_doc_role( $doc, $uid ) ) >= flxg_role_rank( $need );
}

function flxg_create_doc( array $d, $uid ) {
	global $wpdb;
	$now   = flxg_now();
	$space = (int) $d['space_id'];
	$max   = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(position) FROM ' . flxg_table( 'docs' ) . ' WHERE space_id = %d', $space ) ); // phpcs:ignore
	$f     = ! empty( $d['folder_id'] ) ? flxg_get_folder( (int) $d['folder_id'] ) : null;
	$wpdb->insert(
		flxg_table( 'docs' ),
		array(
			'space_id'   => $space,
			'folder_id'  => $f && (int) $f['space_id'] === $space ? (int) $f['id'] : 0,
			'title'      => mb_substr( sanitize_text_field( $d['title'] ?? '' ), 0, 190 ),
			'icon'       => mb_substr( sanitize_text_field( $d['icon'] ?? '' ), 0, 8 ),
			'owner_id'   => (int) $uid,
			'position'   => $max + 1,
			'created_at' => $now,
			'updated_at' => $now,
		)
	);
	$id = (int) $wpdb->insert_id;
	flxg_all_docs( true );
	if ( $id ) {
		flxg_create_doc_page( $id, array( 'title' => $d['page'] ?? '' ), $uid );
	}
	return $id;
}

function flxg_update_doc( $id, array $d ) {
	global $wpdb;
	$doc = flxg_get_doc( $id );
	if ( ! $doc ) {
		return null;
	}
	$set = array();
	if ( isset( $d['title'] ) ) {
		$set['title'] = mb_substr( sanitize_text_field( $d['title'] ), 0, 190 );
	}
	if ( isset( $d['icon'] ) ) {
		$set['icon'] = mb_substr( sanitize_text_field( $d['icon'] ), 0, 8 );
	}
	if ( isset( $d['folder_id'] ) ) {
		$f                = flxg_get_folder( (int) $d['folder_id'] );
		$set['folder_id'] = $f && (int) $f['space_id'] === (int) $doc['space_id'] ? (int) $f['id'] : 0;
	}
	if ( isset( $d['position'] ) ) {
		$set['position'] = (float) $d['position'];
	}
	if ( $set ) {
		$set['updated_at'] = flxg_now();
		$wpdb->update( flxg_table( 'docs' ), $set, array( 'id' => (int) $id ) );
		flxg_all_docs( true );
	}
	return flxg_get_doc( $id );
}

function flxg_delete_doc( $id ) {
	global $wpdb;
	foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxg_table( 'doc_pages' ) . ' WHERE doc_id = %d', (int) $id ) ) as $pid ) { // phpcs:ignore
		flxg_purge_page( (int) $pid );
	}
	foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxg_table( 'files' ) . ' WHERE doc_id = %d', (int) $id ) ) as $fid ) { // phpcs:ignore
		flxg_delete_file( (int) $fid );
	}
	$wpdb->delete( flxg_table( 'docs' ), array( 'id' => (int) $id ) );
	flxg_all_docs( true );
}

/** Sayfa ağacı değişti (ekleme, ad, sıra, silme): açık istemciler ağacı yeniden ister. */
function flxg_bump_doc_tree( $doc_id ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . flxg_table( 'docs' ) . ' SET tree_rev = tree_rev + 1, updated_at = %s WHERE id = %d', flxg_now(), (int) $doc_id ) ); // phpcs:ignore
	flxg_all_docs( true );
}

function flxg_page_row( $r ) {
	return array(
		'id'        => (int) $r['id'],
		'doc'       => (int) $r['doc_id'],
		'parent'    => (int) $r['parent_id'],
		'title'     => (string) $r['title'],
		'pos'       => (float) $r['position'],
		'updated'   => flxg_iso( $r['updated_at'] ),
		'updatedBy' => (int) $r['updated_by'],
	);
}

function flxg_get_page( $id ) {
	global $wpdb;
	$r = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxg_table( 'doc_pages' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore
	return $r ? $r : null;
}

function flxg_doc_pages( $doc_id ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxg_table( 'doc_pages' ) . ' WHERE doc_id = %d ORDER BY position, id', (int) $doc_id ), ARRAY_A ); // phpcs:ignore
	return array_map( 'flxg_page_row', (array) $rows );
}

function flxg_create_doc_page( $doc_id, array $d, $uid ) {
	global $wpdb;
	$parent = (int) ( $d['parent'] ?? 0 );
	if ( $parent ) {
		$p = flxg_get_page( $parent );
		if ( ! $p || (int) $p['doc_id'] !== (int) $doc_id ) {
			$parent = 0;
		}
	}
	$max = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(position) FROM ' . flxg_table( 'doc_pages' ) . ' WHERE doc_id = %d AND parent_id = %d', (int) $doc_id, $parent ) ); // phpcs:ignore
	$now = flxg_now();
	$wpdb->insert(
		flxg_table( 'doc_pages' ),
		array(
			'doc_id'     => (int) $doc_id,
			'parent_id'  => $parent,
			'title'      => mb_substr( sanitize_text_field( $d['title'] ?? '' ), 0, 255 ),
			'position'   => isset( $d['position'] ) ? (float) $d['position'] : $max + 1024,
			'created_by' => (int) $uid,
			'updated_by' => (int) $uid,
			'created_at' => $now,
			'updated_at' => $now,
		)
	);
	$id = (int) $wpdb->insert_id;
	flxg_bump_doc_tree( $doc_id );
	return $id;
}

/** Sayfayı ve altındaki sayfaları siler. */
function flxg_delete_doc_page( $id ) {
	global $wpdb;
	$p = flxg_get_page( $id );
	if ( ! $p ) {
		return;
	}
	$level = array( (int) $id );
	$all   = array( (int) $id );
	for ( $i = 0; $level && $i < 20; $i++ ) {
		$level = array_map( 'intval', $wpdb->get_col( 'SELECT id FROM ' . flxg_table( 'doc_pages' ) . ' WHERE parent_id IN (' . flxg_ids_in( $level ) . ')' ) ); // phpcs:ignore
		$all   = array_merge( $all, $level );
	}
	foreach ( $all as $pid ) {
		flxg_purge_page( $pid );
	}
	flxg_bump_doc_tree( (int) $p['doc_id'] );
}

function flxg_purge_page( $pid ) {
	global $wpdb;
	$wpdb->delete( flxg_table( 'doc_blocks' ), array( 'page_id' => (int) $pid ) );
	$wpdb->delete( flxg_table( 'doc_presence' ), array( 'page_id' => (int) $pid ) );
	$wpdb->delete( flxg_table( 'doc_pages' ), array( 'id' => (int) $pid ) );
}

/** Blok HTML'i: belge biçimleri + tablo + görev bağlantısı; görsel ve dosya yalnız kimlikle. */
function flxg_kses_block( $html ) {
	$allowed = array(
		'p'          => array( 'style' => true ),
		'br'         => array(),
		'strong'     => array(),
		'b'          => array(),
		'em'         => array(),
		'i'          => array(),
		'u'          => array(),
		's'          => array(),
		'strike'     => array(),
		'del'        => array(),
		'mark'       => array(),
		'sub'        => array(),
		'sup'        => array(),
		'h1'         => array( 'style' => true ),
		'h2'         => array( 'style' => true ),
		'h3'         => array( 'style' => true ),
		'h4'         => array( 'style' => true ),
		'ul'         => array( 'data-checklist' => true ),
		'ol'         => array(),
		'li'         => array( 'data-checked' => true ),
		'blockquote' => array(),
		'code'       => array(),
		'pre'        => array(),
		'hr'         => array(),
		'a'          => array( 'href' => true, 'target' => true, 'rel' => true, 'data-file' => true ),
		'div'        => array( 'class' => true ),
		'span'       => array( 'data-task' => true, 'data-page' => true, 'class' => true, 'contenteditable' => true ),
		'img'        => array( 'data-file' => true, 'alt' => true, 'width' => true ),
		'figure'     => array(),
		'figcaption' => array(),
		'table'      => array( 'data-fixed' => true ),
		'thead'      => array(),
		'tbody'      => array(),
		'tr'         => array(),
		'th'         => array( 'colspan' => true, 'rowspan' => true, 'data-bg' => true, 'data-fg' => true, 'style' => true ),
		'td'         => array( 'colspan' => true, 'rowspan' => true, 'data-bg' => true, 'data-fg' => true, 'style' => true ),
	);
	$html = wp_kses( (string) $html, $allowed );
	// Yalnız hizalama ve (tablo hücresinde) sütun genişliği stili; renkler data-bg/data-fg ile.
	$html = preg_replace_callback(
		'/\sstyle="([^"]*)"/i',
		function ( $m ) {
			$out = array();
			if ( preg_match( '/text-align:\s*(left|center|right|justify)/i', $m[1], $a ) ) {
				$out[] = 'text-align: ' . strtolower( $a[1] );
			}
			if ( preg_match( '/(?:^|;)\s*width:\s*(\d{2,4})px/i', $m[1], $w ) ) {
				$out[] = 'width: ' . max( 40, min( 1200, (int) $w[1] ) ) . 'px';
			}
			return $out ? ' style="' . implode( '; ', $out ) . '"' : '';
		},
		$html
	);
	$html = preg_replace_callback(
		'/\sdata-(bg|fg)="([^"]*)"/i',
		function ( $m ) {
			return in_array( $m[2], array( 'gray', 'brown', 'orange', 'yellow', 'green', 'blue', 'purple', 'pink', 'red' ), true ) ? ' data-' . strtolower( $m[1] ) . '="' . $m[2] . '"' : '';
		},
		$html
	);
	$html = preg_replace_callback(
		'/<img\b[^>]*>/i',
		function ( $m ) {
			if ( ! preg_match( '/data-file="(\d+)"/', $m[0], $id ) ) {
				return '';
			}
			$alt = preg_match( '/alt="([^"]*)"/', $m[0], $a ) ? $a[1] : '';
			$w   = preg_match( '/width="(\d{1,4})"/', $m[0], $ww ) ? ' width="' . (int) $ww[1] . '"' : '';
			return '<img data-file="' . (int) $id[1] . '" alt="' . esc_attr( $alt ) . '"' . $w . ' />';
		},
		$html
	);
	return trim( mb_substr( $html, 0, 200000 ) );
}

function flxg_page_blocks( $page_id ) {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT bid, html, position, rev, user_id FROM ' . flxg_table( 'doc_blocks' ) . ' WHERE page_id = %d AND deleted = 0 ORDER BY position, id', (int) $page_id ), ARRAY_A ); // phpcs:ignore
	$out  = array();
	foreach ( (array) $rows as $r ) {
		$out[] = array(
			'b'    => (string) $r['bid'],
			'html' => (string) $r['html'],
			'pos'  => (float) $r['position'],
			'rev'  => (int) $r['rev'],
		);
	}
	return $out;
}

function flxg_page_rev( $page_id ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(rev) FROM ' . flxg_table( 'doc_blocks' ) . ' WHERE page_id = %d', (int) $page_id ) ); // phpcs:ignore
}

function flxg_next_doc_rev( $page_id ) {
	global $wpdb;
	$wpdb->insert( flxg_table( 'doc_revs' ), array( 'page_id' => (int) $page_id ) );
	return (int) $wpdb->insert_id;
}

/**
 * Blok değişikliklerini uygular. op = { b, html?, after? ('' = en başa), del? }.
 *
 * @return int[] yazılan sürümler
 */
function flxg_apply_block_ops( $page_id, array $ops, $uid ) {
	global $wpdb;
	$t    = flxg_table( 'doc_blocks' );
	$revs = array();
	$now  = flxg_now();
	foreach ( array_slice( $ops, 0, 400 ) as $op ) {
		if ( ! is_array( $op ) ) {
			continue;
		}
		$bid = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $op['b'] ?? '' ) );
		if ( '' === $bid || strlen( $bid ) > 24 ) {
			continue;
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, position FROM $t WHERE page_id = %d AND bid = %s", (int) $page_id, $bid ), ARRAY_A ); // phpcs:ignore
		$rev = flxg_next_doc_rev( $page_id );
		if ( ! empty( $op['del'] ) ) {
			if ( $row ) {
				$wpdb->update( $t, array( 'deleted' => 1, 'rev' => $rev, 'user_id' => (int) $uid, 'updated_at' => $now ), array( 'id' => (int) $row['id'] ) );
				$revs[] = $rev;
			}
			continue;
		}
		$set = array( 'rev' => $rev, 'user_id' => (int) $uid, 'deleted' => 0, 'updated_at' => $now );
		if ( array_key_exists( 'html', $op ) ) {
			$set['html'] = flxg_kses_block( $op['html'] );
		}
		if ( array_key_exists( 'after', $op ) || ! $row ) {
			$set['position'] = flxg_block_position( $page_id, (string) ( $op['after'] ?? '' ), $bid, ! array_key_exists( 'after', $op ) );
		}
		if ( $row ) {
			$wpdb->update( $t, $set, array( 'id' => (int) $row['id'] ) );
		} else {
			$set['page_id'] = (int) $page_id;
			$set['bid']     = $bid;
			if ( ! isset( $set['html'] ) ) {
				$set['html'] = '';
			}
			$wpdb->insert( $t, $set );
		}
		$revs[] = $rev;
	}
	if ( $revs ) {
		$wpdb->update( flxg_table( 'doc_pages' ), array( 'updated_at' => $now, 'updated_by' => (int) $uid ), array( 'id' => (int) $page_id ) );
	}
	return $revs;
}

/** $after bloğunun hemen arkası ('' = en baş; bulunamazsa ya da $append ise en son). */
function flxg_block_position( $page_id, $after, $self, $append = false ) {
	global $wpdb;
	$t = flxg_table( 'doc_blocks' );
	if ( $append ) {
		return 1024 + (float) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(position) FROM $t WHERE page_id = %d AND deleted = 0 AND bid <> %s", (int) $page_id, $self ) ); // phpcs:ignore
	}
	if ( '' === $after ) {
		$min = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(position) FROM $t WHERE page_id = %d AND deleted = 0 AND bid <> %s", (int) $page_id, $self ) ); // phpcs:ignore
		return null === $min ? 1024 : (float) $min - 1024;
	}
	$a = $wpdb->get_var( $wpdb->prepare( "SELECT position FROM $t WHERE page_id = %d AND bid = %s", (int) $page_id, $after ) ); // phpcs:ignore
	if ( null === $a ) {
		return flxg_block_position( $page_id, '', $self, true );
	}
	$a    = (float) $a;
	$next = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(position) FROM $t WHERE page_id = %d AND deleted = 0 AND bid <> %s AND bid <> %s AND position > %f", (int) $page_id, $self, $after, $a ) ); // phpcs:ignore
	return null === $next ? $a + 1024 : ( $a + (float) $next ) / 2;
}

/** Sayfadaki diğer kişiler (son 25 sn) ve imleçleri. */
function flxg_page_presence( $page_id, $uid ) {
	global $wpdb;
	$since = gmdate( 'Y-m-d H:i:s', time() - 30 );
	$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT user_id, cursor_data, seen FROM ' . flxg_table( 'doc_presence' ) . ' WHERE page_id = %d AND user_id <> %d AND seen >= %s', (int) $page_id, (int) $uid, $since ), ARRAY_A ); // phpcs:ignore
	$out   = array();
	foreach ( (array) $rows as $r ) {
		$out[ (int) $r['user_id'] ] = array(
			'user'   => (int) $r['user_id'],
			'cursor' => flxg_json( $r['cursor_data'], null ),
			'seen'   => flxg_iso( $r['seen'] ),
		);
	}
	return array_values( $out );
}

function flxg_set_presence( $page_id, $uid, $cursor ) {
	global $wpdb;
	$t   = flxg_table( 'doc_presence' );
	$cur = '';
	if ( is_array( $cursor ) && ! empty( $cursor['b'] ) ) {
		$cur = flxg_enc(
			array(
				'b' => substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $cursor['b'] ), 0, 24 ),
				'o' => max( 0, min( 200000, (int) ( $cursor['o'] ?? 0 ) ) ),
				'l' => max( 0, min( 200000, (int) ( $cursor['l'] ?? 0 ) ) ),
				'e' => isset( $cursor['e'] ) ? substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $cursor['e'] ), 0, 24 ) : '',
				'eo' => max( 0, min( 200000, (int) ( $cursor['eo'] ?? 0 ) ) ),
				'w' => ! empty( $cursor['w'] ) ? 1 : 0,
			)
		);
	}
	$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE page_id = %d AND user_id = %d", (int) $page_id, (int) $uid ) ); // phpcs:ignore
	if ( $id ) {
		$wpdb->update( $t, array( 'cursor_data' => $cur, 'seen' => flxg_now() ), array( 'id' => (int) $id ) );
	} else {
		$wpdb->insert( $t, array( 'page_id' => (int) $page_id, 'user_id' => (int) $uid, 'cursor_data' => $cur, 'seen' => flxg_now() ) );
	}
}

add_action(
	'flxg_daily',
	function () {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . flxg_table( 'doc_presence' ) . ' WHERE seen < %s', gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) ); // phpcs:ignore
		// Sürüm üreticisinde yalnız son kayıt gerekli; silinen bloklar bir hafta sonra temizlenir.
		$max = (int) $wpdb->get_var( 'SELECT MAX(id) FROM ' . flxg_table( 'doc_revs' ) ); // phpcs:ignore
		if ( $max > 1000 ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . flxg_table( 'doc_revs' ) . ' WHERE id < %d', $max - 100 ) ); // phpcs:ignore
		}
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . flxg_table( 'doc_blocks' ) . ' WHERE deleted = 1 AND updated_at < %s', gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS ) ) ); // phpcs:ignore
	}
);

/* =========================================================================
 * REST
 * ====================================================================== */

add_action( 'rest_api_init', 'flxg_register_doc_routes' );
function flxg_register_doc_routes() {
	flxg_route( '/docs', 'POST', 'flxg_rest_doc_create' );
	flxg_route( '/docs/(?P<id>\d+)', 'GET', 'flxg_rest_doc_get' );
	flxg_route( '/docs/(?P<id>\d+)', 'POST, PUT, PATCH', 'flxg_rest_doc_update' );
	flxg_route( '/docs/(?P<id>\d+)', 'DELETE', 'flxg_rest_doc_delete' );
	flxg_route( '/docs/(?P<id>\d+)/pages', 'POST', 'flxg_rest_page_create' );
	flxg_route( '/doc-pages/(?P<id>\d+)', 'GET', 'flxg_rest_page_get' );
	flxg_route( '/doc-pages/(?P<id>\d+)', 'POST, PUT, PATCH', 'flxg_rest_page_update' );
	flxg_route( '/doc-pages/(?P<id>\d+)', 'DELETE', 'flxg_rest_page_delete' );
	flxg_route( '/doc-pages/(?P<id>\d+)/sync', 'POST', 'flxg_rest_doc_sync' );
	flxg_route( '/doc-pages/(?P<id>\d+)/email-draft', 'POST', 'flxg_rest_page_email_draft' );
}

/** Kullanıcının görebildiği dokümanlar (yapı verisine eklenir). */
function flxg_visible_docs( $uid ) {
	$out = array();
	foreach ( flxg_all_docs() as $d ) {
		$role = flxg_doc_role( $d, $uid );
		if ( $role ) {
			$d['role'] = $role;
			$out[]     = $d;
		}
	}
	return $out;
}

function flxg_rest_doc_create( WP_REST_Request $r ) {
	$b     = flxg_body( $r );
	$space = (int) ( $b['space'] ?? 0 );
	if ( ! flxg_get_space( $space ) || ! flxg_can_space( $space, 'edit' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'You are not allowed to create documents in this project.', 'flx-tasks' ), 403 );
	}
	$title = trim( (string) ( $b['title'] ?? '' ) );
	$id    = flxg_create_doc(
		array(
			'space_id'  => $space,
			'folder_id' => (int) ( $b['folder'] ?? 0 ),
			'title'     => '' !== $title ? $title : __( 'Untitled document', 'flx-tasks' ),
			'icon'      => $b['icon'] ?? '',
			'page'      => '',
		),
		get_current_user_id()
	);
	flxg_bump_structure();
	return array( 'id' => $id ) + flxg_structure( get_current_user_id() );
}

function flxg_rest_doc_get( WP_REST_Request $r ) {
	$doc = flxg_get_doc( (int) $r['id'] );
	if ( ! $doc || ! flxg_can_doc( $doc ) ) {
		return flxg_error( 'flxg_404', __( 'Document not found or you have no access.', 'flx-tasks' ), 404 );
	}
	$pages = flxg_doc_pages( $doc['id'] );
	if ( ! $pages && flxg_can_doc( $doc, 'edit' ) ) {
		flxg_create_doc_page( $doc['id'], array(), get_current_user_id() );
		$pages = flxg_doc_pages( $doc['id'] );
		$doc   = flxg_get_doc( $doc['id'] );
	}
	return array(
		'doc'   => $doc,
		'role'  => flxg_doc_role( $doc ),
		'pages' => $pages,
	);
}

function flxg_rest_doc_update( WP_REST_Request $r ) {
	$doc = flxg_get_doc( (int) $r['id'] );
	if ( ! $doc || ! flxg_can_doc( $doc, 'edit' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'You are not allowed to edit this document.', 'flx-tasks' ), 403 );
	}
	$doc = flxg_update_doc( $doc['id'], flxg_body( $r ) );
	flxg_bump_structure();
	return array( 'doc' => $doc ) + flxg_structure( get_current_user_id() );
}

function flxg_rest_doc_delete( WP_REST_Request $r ) {
	$doc = flxg_get_doc( (int) $r['id'] );
	if ( ! $doc || ! ( flxg_can_doc( $doc, 'admin' ) || ( (int) $doc['owner_id'] === get_current_user_id() && flxg_can_doc( $doc, 'edit' ) ) ) ) {
		return flxg_error( 'flxg_forbidden', __( 'Only the owner or a project admin can delete the document.', 'flx-tasks' ), 403 );
	}
	flxg_delete_doc( $doc['id'] );
	flxg_bump_structure();
	return flxg_structure( get_current_user_id() );
}

function flxg_page_doc( $page ) {
	return $page ? flxg_get_doc( (int) $page['doc_id'] ) : null;
}

function flxg_rest_page_create( WP_REST_Request $r ) {
	$doc = flxg_get_doc( (int) $r['id'] );
	if ( ! $doc || ! flxg_can_doc( $doc, 'edit' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'You are not allowed to edit this document.', 'flx-tasks' ), 403 );
	}
	$b  = flxg_body( $r );
	$id = flxg_create_doc_page( $doc['id'], array( 'title' => $b['title'] ?? '', 'parent' => (int) ( $b['parent'] ?? 0 ) ), get_current_user_id() );
	// İsteğe bağlı başlangıç içeriği (ör. yapay zekâ sonucu "Yeni sayfa olarak ekle").
	if ( $id && ! empty( $b['html'] ) && is_string( $b['html'] ) && function_exists( 'flxg_doc_insert_html' ) ) {
		$n = 0;
		flxg_doc_insert_html( $id, $b['html'], null, get_current_user_id(), $n );
	}
	return array( 'id' => $id, 'pages' => flxg_doc_pages( $doc['id'] ), 'doc' => flxg_get_doc( $doc['id'] ) );
}

function flxg_rest_page_get( WP_REST_Request $r ) {
	$p   = flxg_get_page( (int) $r['id'] );
	$doc = flxg_page_doc( $p );
	if ( ! $doc || ! flxg_can_doc( $doc ) ) {
		return flxg_error( 'flxg_404', __( 'Page not found or you have no access.', 'flx-tasks' ), 404 );
	}
	// Açan kişi ilk yoklamayı beklemeden "burada" görünsün.
	flxg_set_presence( $p['id'], get_current_user_id(), null );
	return array(
		'page'     => flxg_page_row( $p ),
		'blocks'   => flxg_page_blocks( $p['id'] ),
		'rev'      => flxg_page_rev( $p['id'] ),
		'presence' => flxg_page_presence( $p['id'], get_current_user_id() ),
		'role'     => flxg_doc_role( $doc ),
	);
}

function flxg_rest_page_update( WP_REST_Request $r ) {
	global $wpdb;
	$p   = flxg_get_page( (int) $r['id'] );
	$doc = flxg_page_doc( $p );
	if ( ! $doc || ! flxg_can_doc( $doc, 'edit' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'You are not allowed to edit this document.', 'flx-tasks' ), 403 );
	}
	$b   = flxg_body( $r );
	$set = array();
	if ( isset( $b['title'] ) ) {
		$set['title'] = mb_substr( sanitize_text_field( $b['title'] ), 0, 255 );
	}
	if ( isset( $b['parent'] ) ) {
		$parent = (int) $b['parent'];
		// Kendi altına taşınamaz.
		for ( $q = $parent, $i = 0; $q && $i < 25; $i++ ) {
			if ( $q === (int) $p['id'] ) {
				return flxg_error( 'flxg_parent', __( 'A page cannot be moved under its own subpage.', 'flx-tasks' ) );
			}
			$qq = flxg_get_page( $q );
			if ( ! $qq || (int) $qq['doc_id'] !== (int) $doc['id'] ) {
				$parent = 0;
				break;
			}
			$q = (int) $qq['parent_id'];
		}
		$set['parent_id'] = $parent;
	}
	if ( isset( $b['position'] ) ) {
		$set['position'] = (float) $b['position'];
	}
	if ( $set ) {
		$set['updated_at'] = flxg_now();
		$set['updated_by'] = get_current_user_id();
		$wpdb->update( flxg_table( 'doc_pages' ), $set, array( 'id' => (int) $p['id'] ) );
		flxg_bump_doc_tree( $doc['id'] );
	}
	return array( 'pages' => flxg_doc_pages( $doc['id'] ), 'doc' => flxg_get_doc( $doc['id'] ) );
}

function flxg_rest_page_delete( WP_REST_Request $r ) {
	global $wpdb;
	$p   = flxg_get_page( (int) $r['id'] );
	$doc = flxg_page_doc( $p );
	if ( ! $doc || ! flxg_can_doc( $doc, 'edit' ) ) {
		return flxg_error( 'flxg_forbidden', __( 'You are not allowed to edit this document.', 'flx-tasks' ), 403 );
	}
	$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxg_table( 'doc_pages' ) . ' WHERE doc_id = %d AND parent_id = 0', (int) $doc['id'] ) ); // phpcs:ignore
	if ( ! (int) $p['parent_id'] && $count < 2 ) {
		return flxg_error( 'flxg_last', __( 'The last page of a document cannot be deleted. You can delete the document instead.', 'flx-tasks' ) );
	}
	flxg_delete_doc_page( $p['id'] );
	return array( 'pages' => flxg_doc_pages( $doc['id'] ), 'doc' => flxg_get_doc( $doc['id'] ) );
}

/**
 * Ortak düzenleme turu: istemcinin değişikliklerini yazar, başkalarının değişikliklerini ve imleçlerini döner.
 * Gövde: { since: int, ops: [ {b, html?, after?, del?} ], title?: string, cursor?: {b, o, l, e, eo}, leave?: bool }
 */
function flxg_rest_doc_sync( WP_REST_Request $r ) {
	global $wpdb;
	$p   = flxg_get_page( (int) $r['id'] );
	$doc = flxg_page_doc( $p );
	if ( ! $doc || ! flxg_can_doc( $doc ) ) {
		return flxg_error( 'flxg_404', __( 'Page not found or you have no access.', 'flx-tasks' ), 404 );
	}
	$uid  = get_current_user_id();
	$b    = flxg_body( $r );
	$edit = flxg_can_doc( $doc, 'edit' );
	$mine = array();
	if ( ! empty( $b['ops'] ) && is_array( $b['ops'] ) ) {
		if ( ! $edit ) {
			return flxg_error( 'flxg_forbidden', __( 'You are not allowed to edit this document.', 'flx-tasks' ), 403 );
		}
		$mine = flxg_apply_block_ops( $p['id'], $b['ops'], $uid );
	}
	// Yazılan blokların sunucudaki sırası: istemci başkalarının bloklarını doğru yere koyabilsin.
	$saved_pos = array();
	if ( $mine ) {
		$in = implode( ',', array_map( 'intval', $mine ) );
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT bid, position, rev FROM ' . flxg_table( 'doc_blocks' ) . " WHERE page_id = %d AND deleted = 0 AND rev IN ($in)", (int) $p['id'] ), ARRAY_A ) as $row ) { // phpcs:ignore
			$saved_pos[ (string) $row['bid'] ] = array( 'p' => (float) $row['position'], 'r' => (int) $row['rev'] );
		}
	}
	if ( $edit && isset( $b['title'] ) && is_string( $b['title'] ) ) {
		$title = mb_substr( sanitize_text_field( $b['title'] ), 0, 255 );
		if ( $title !== $p['title'] ) {
			$wpdb->update( flxg_table( 'doc_pages' ), array( 'title' => $title, 'updated_at' => flxg_now(), 'updated_by' => $uid ), array( 'id' => (int) $p['id'] ) );
			flxg_bump_doc_tree( $doc['id'] );
			$p['title'] = $title;
		}
	}
	if ( ! empty( $b['leave'] ) ) {
		$wpdb->delete( flxg_table( 'doc_presence' ), array( 'page_id' => (int) $p['id'], 'user_id' => $uid ) );
	} else {
		flxg_set_presence( $p['id'], $uid, $b['cursor'] ?? null );
	}
	$since   = max( 0, (int) ( $b['since'] ?? 0 ) );
	$rows    = $wpdb->get_results( $wpdb->prepare( 'SELECT bid, html, position, rev, user_id, deleted FROM ' . flxg_table( 'doc_blocks' ) . ' WHERE page_id = %d AND rev > %d ORDER BY rev LIMIT 500', (int) $p['id'], $since ), ARRAY_A ); // phpcs:ignore
	$changes = array();
	foreach ( (array) $rows as $row ) {
		// Bu istekte yazılanlar istemcide zaten var; geri göndermek yazılmaya devam eden metni ezer.
		if ( in_array( (int) $row['rev'], $mine, true ) ) {
			continue;
		}
		$changes[] = array(
			'b'    => (string) $row['bid'],
			'html' => (int) $row['deleted'] ? '' : (string) $row['html'],
			'pos'  => (float) $row['position'],
			'rev'  => (int) $row['rev'],
			'user' => (int) $row['user_id'],
			'del'  => (int) $row['deleted'] ? 1 : 0,
		);
	}
	$fresh = flxg_get_page( $p['id'] );
	return array(
		'rev'      => flxg_page_rev( $p['id'] ),
		'changes'  => $changes,
		'more'     => count( (array) $rows ) >= 500,
		'presence' => flxg_page_presence( $p['id'], $uid ),
		'title'    => $fresh ? (string) $fresh['title'] : '',
		'treeRev'  => (int) ( flxg_get_doc( $doc['id'] )['treeRev'] ?? 0 ),
		'saved'    => $mine ? max( $mine ) : 0,
		'savedPos' => (object) $saved_pos,
	);
}

/* =========================================================================
 * Belge sayfasından e-posta taslağı (Flux Mail posta kutusu)
 * ====================================================================== */

/** Flux Mail kurulu ve kişi posta kutusunu yönetebiliyor mu? */
function flxg_can_mail_draft() {
	return function_exists( 'flxi_mailbox_new_draft' ) && function_exists( 'flxi_can_manage' ) && flxi_can_manage();
}

/**
 * Belgedeki dosya alıcıya açık olsun diye herkese açık bir kopyası (uploads/flx-tasks-mail) üretilir.
 * Yalnız bu sayfada kullanılan ve kişinin görebildiği dosyalar kopyalanır; aynı dosya bir kez kopyalanır.
 */
function flxg_mail_public_url( $file_id ) {
	$f = flxg_get_file( (int) $file_id );
	if ( ! $f || ! function_exists( 'flxg_file_can_view' ) || ! flxg_file_can_view( $f ) ) {
		return '';
	}
	$src = trailingslashit( flxg_files_dir() ) . $f['path'];
	if ( ! is_readable( $src ) ) {
		return '';
	}
	$up   = wp_upload_dir( null, false );
	$dir  = trailingslashit( $up['basedir'] ) . 'flx-tasks-mail';
	$ext  = strtolower( pathinfo( (string) $f['name'], PATHINFO_EXTENSION ) );
	$name = substr( hash( 'sha256', $f['id'] . '|' . $f['path'] . '|' . wp_salt( 'auth' ) ), 0, 24 ) . ( preg_match( '/^[a-z0-9]{1,5}$/', $ext ) ? '.' . $ext : '' );
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
	}
	if ( ! file_exists( $dir . '/' . $name ) && ! @copy( $src, $dir . '/' . $name ) ) { // phpcs:ignore
		return '';
	}
	return trailingslashit( $up['baseurl'] ) . 'flx-tasks-mail/' . $name;
}

/** Sayfa bloklarından e-postaya uygun HTML: görseller herkese açık adresle, görev bağlantıları düz metin, kontrol listesi işaretli. */
function flxg_page_email_html( $page_id ) {
	$html = '';
	foreach ( flxg_page_blocks( $page_id ) as $b ) {
		$html .= $b['html'];
	}
	// Görev ve alt sayfa bağlantısı: alıcı göremez, yalnız adı kalır.
	$html = preg_replace( '/<span[^>]*class="[^"]*flxg-(?:tref|pref)[^"]*"[^>]*>(.*?)<\/span>/is', '$1', $html );
	$html = preg_replace_callback(
		'/<img[^>]*data-file="(\d+)"[^>]*>/i',
		function ( $m ) {
			$url = flxg_mail_public_url( (int) $m[1] );
			if ( '' === $url ) {
				return '';
			}
			$alt = preg_match( '/alt="([^"]*)"/', $m[0], $a ) ? $a[1] : '';
			$w   = preg_match( '/width="(\d+)"/', $m[0], $ww ) ? min( 600, (int) $ww[1] ) : 0;
			return '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"' . ( $w ? ' width="' . $w . '"' : '' ) . ' style="max-width:100%;height:auto;border-radius:8px" />';
		},
		$html
	);
	$html = preg_replace_callback(
		'/<a([^>]*)data-file="(\d+)"([^>]*)>/i',
		function ( $m ) {
			$url = flxg_mail_public_url( (int) $m[2] );
			$rest = preg_replace( '/\shref="[^"]*"/i', '', $m[1] . $m[3] );
			return '<a' . $rest . ( '' !== $url ? ' href="' . esc_url( $url ) . '"' : '' ) . '>';
		},
		$html
	);
	$html = preg_replace( '/<li data-checked="1">/', '<li>&#9745; ', $html );
	$html = preg_replace( '/<li data-checked="0">/', '<li>&#9744; ', $html );
	$html = preg_replace( '/\s(data-checklist|data-checked|data-task|data-page|contenteditable|class)="[^"]*"/i', '', $html );
	return $html;
}

function flxg_rest_page_email_draft( WP_REST_Request $r ) {
	$p   = flxg_get_page( (int) $r['id'] );
	$doc = flxg_page_doc( $p );
	if ( ! $doc || ! flxg_can_doc( $doc ) ) {
		return flxg_error( 'flxg_404', __( 'Page not found or you have no access.', 'flx-tasks' ), 404 );
	}
	if ( ! flxg_can_mail_draft() ) {
		return flxg_error( 'flxg_mail', __( 'An email draft needs Flux Communication and mailbox permission.', 'flx-tasks' ), 403 );
	}
	$title = trim( (string) $p['title'] );
	if ( '' === $title ) {
		$title = (string) $doc['title'];
	}
	$res = flxi_mailbox_new_draft(
		array(
			'subject' => $title,
			'body'    => flxg_page_email_html( (int) $p['id'] ),
			'layout'  => 'kurumsal',
			/* translators: %s: document title */
			'note'    => sprintf( __( 'Document: %s', 'flx-tasks' ), $doc['title'] . ( (string) $doc['title'] !== $title ? ' / ' . $title : '' ) ),
			'to'      => '',
		)
	);
	if ( is_wp_error( $res ) ) {
		return flxg_error( 'flxg_mail', $res->get_error_message(), 400 );
	}
	return array( 'id' => (int) $res['id'], 'url' => (string) $res['url'] );
}
