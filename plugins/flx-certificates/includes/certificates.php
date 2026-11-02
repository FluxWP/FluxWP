<?php
defined( 'ABSPATH' ) || exit;

function flxs_get_certificate( $id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxs_table( 'certificates' ) . ' WHERE id = %d', $id ), ARRAY_A );
	return $row ? flxs_prepare_certificate( $row ) : null;
}

function flxs_get_certificate_by_code( $code ) {
	global $wpdb;
	$code = strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', (string) $code ) );
	if ( '' === $code ) {
		return null;
	}
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxs_table( 'certificates' ) . ' WHERE code = %s', $code ), ARRAY_A );
	return $row ? flxs_prepare_certificate( $row ) : null;
}

function flxs_prepare_certificate( $row ) {
	$row['data'] = flxs_decode( $row['data'] );
	return $row;
}

function flxs_certificate_url( $cert ) {
	$code = is_array( $cert ) ? $cert['code'] : $cert;
	if ( get_option( 'permalink_structure' ) ) {
		return home_url( user_trailingslashit( flxs_slug() . '/' . $code ) );
	}
	return add_query_arg( 'flxs_kod', $code, home_url( '/' ) );
}

function flxs_certificate_pdf_url( $cert ) {
	if ( get_option( 'permalink_structure' ) ) {
		return home_url( flxs_slug() . '/' . $cert['code'] . '/pdf' );
	}
	return add_query_arg( array( 'flxs_kod' => $cert['code'], 'flxs_pdf' => 1 ), home_url( '/' ) );
}

/** Dosya adı: pdf, image (sertifika görseli) ya da og (1200x630 link önizleme görseli; data JSON içinde) */
function flxs_certificate_file_name( $cert, $type = 'image' ) {
	if ( 'og' === $type ) {
		return (string) ( $cert['data']['og'] ?? '' );
	}
	return (string) ( 'pdf' === $type ? $cert['pdf_file'] : $cert['image_file'] );
}

function flxs_certificate_file_url( $cert, $type = 'image' ) {
	$f = flxs_certificate_file_name( $cert, $type );
	return $f ? flxs_upload_dir()['url'] . '/' . $f : '';
}

function flxs_certificate_file_path( $cert, $type = 'image' ) {
	$f = flxs_certificate_file_name( $cert, $type );
	return $f ? flxs_upload_dir()['dir'] . '/' . $f : '';
}

/** Tarih alanını şablon biçimine çevirir (2026-10-09 => 09.10.2026). */
function flxs_format_date_value( $value, $format = 'd.m.Y' ) {
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $value ) ) {
		$ts = strtotime( $value . ' 12:00:00' );
		return wp_date( $format ? $format : 'd.m.Y', $ts, new DateTimeZone( 'UTC' ) );
	}
	return (string) $value;
}

/** Mesaj / OG / PDF yer tutucuları için değişkenler. */
function flxs_certificate_vars( $cert, $person = null, $template = null ) {
	if ( null === $person ) {
		$person = flxs_get_person( $cert['person_id'] );
	}
	if ( null === $template ) {
		$template = flxs_get_template( $cert['template_id'] );
	}
	$fmt    = $template ? $template['config']['date_format'] : 'd.m.Y';
	$values = (array) ( $cert['data']['values'] ?? array() );
	$vars   = array();
	if ( $template ) {
		foreach ( $template['config']['fields'] as $f ) {
			$v = $values[ $f['key'] ] ?? $f['default'];
			if ( 'date' === $f['type'] ) {
				$v = flxs_format_date_value( $v, $fmt );
			}
			if ( ! in_array( $f['type'], array( 'static', 'qr', 'number_auto' ), true ) ) {
				$vars[ $f['key'] ] = $v;
			}
		}
	}
	// İsim sertifikaya aittir: aynı telefonla (ör. aile) farklı isimlere sertifika verilebilir
	$name                 = flxs_certificate_name( $cert, $person );
	$vars['ad_soyad']     = $name;
	$vars['ad']           = flxs_first_name( $name );
	$parts                = preg_split( '/\s+/u', trim( $name ) );
	$vars['soyad']        = count( $parts ) > 1 ? end( $parts ) : '';
	$vars['sertifika']    = $cert['title'];
	$vars['sertifika_no'] = $cert['number'];
	$vars['kod']          = $cert['code'];
	$vars['link']         = flxs_certificate_url( $cert );
	$vars['site']         = get_bloginfo( 'name' );
	$vars['kurum']        = flxs_setting( 'org_name' );
	if ( empty( $vars['tarih'] ) ) {
		$vars['tarih'] = flxs_date( $cert['created_at'], $fmt );
	}
	return apply_filters( 'flxs_certificate_vars', $vars, $cert, $person, $template );
}

/**
 * Sertifika kaydı oluşturur. Dosyalar (PDF + görsel) tarayıcıda üretilip ayrıca yüklenir.
 */
/**
 * @param string $issued Geçmiş tarihli (mutabakat) sertifika için yerel tarih Y-m-d; boşsa şimdi.
 *                       created_at bu tarihe yazılır (raporlar created_at'e göre), gerçek oluşturma anı data.backdated'da durur.
 */
function flxs_create_certificate( $template_id, $person_id, array $values, $ref_user_id = 0, $issued = '' ) {
	global $wpdb;
	$template = flxs_get_template( $template_id );
	if ( ! $template ) {
		return new WP_Error( 'flxs_template', 'Şablon bulunamadı.' );
	}
	$values = flxs_clean_values( $template, $values );
	if ( is_wp_error( $values ) ) {
		return $values;
	}
	for ( $i = 0; $i < 5; $i++ ) {
		$code   = flxs_random_code();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxs_table( 'certificates' ) . ' WHERE code = %s', $code ) );
		if ( ! $exists ) {
			break;
		}
	}
	$ok = $wpdb->insert(
		flxs_table( 'certificates' ),
		array(
			'code'        => $code,
			'template_id' => (int) $template_id,
			'person_id'   => (int) $person_id,
			'title'       => $template['config']['title'],
			'data'        => flxs_json( $issued ? array( 'values' => $values, 'backdated' => flxs_now() ) : array( 'values' => $values ) ),
			'status'      => 'active',
			'ref_user_id' => flxs_valid_ref_user( $ref_user_id ),
			'created_by'  => get_current_user_id(),
			'created_at'  => $issued ? get_gmt_from_date( $issued . ' 12:00:00' ) : flxs_now(),
			'updated_at'  => flxs_now(),
		)
	);
	if ( ! $ok ) {
		return new WP_Error( 'flxs_db', 'Sertifika kaydedilemedi: ' . $wpdb->last_error );
	}
	$id     = (int) $wpdb->insert_id;
	$number = flxs_fill(
		flxs_setting( 'number_format' ),
		array(
			'yil'  => $issued ? substr( $issued, 0, 4 ) : gmdate( 'Y' ),
			'sira' => str_pad( (string) $id, 5, '0', STR_PAD_LEFT ),
			'id'   => $id,
			'kod'  => strtoupper( substr( $code, 0, 6 ) ),
		)
	);
	$wpdb->update( flxs_table( 'certificates' ), array( 'number' => $number ), array( 'id' => $id ) );
	$cert   = flxs_get_certificate( $id );
	$person = flxs_get_person( $person_id );
	flxs_log(
		'certificate_created',
		'certificate',
		$id,
		sprintf( '%s · %s · %s', $number, $person ? $person['name'] : '', $template['name'] ),
		array( 'template' => $template['name'], 'values' => $values, 'person_id' => $person_id, 'referans' => $ref_user_id ? flxs_user_label( $ref_user_id ) : '', 'gecmis_tarih' => $issued )
	);
	do_action( 'flxs_certificate_created', $cert );
	return $cert;
}

function flxs_update_certificate( $id, $person_id, array $values, $ref_user_id = null ) {
	global $wpdb;
	$cert = flxs_get_certificate( $id );
	if ( ! $cert ) {
		return new WP_Error( 'flxs_404', 'Sertifika bulunamadı.' );
	}
	$template = flxs_get_template( $cert['template_id'] );
	$values   = flxs_clean_values( $template, $values );
	if ( is_wp_error( $values ) ) {
		return $values;
	}
	$before = $cert['data']['values'] ?? array();
	$wpdb->update(
		flxs_table( 'certificates' ),
		array(
			'person_id'   => (int) $person_id,
			'data'        => flxs_json( array_merge( $cert['data'], array( 'values' => $values ) ) ),
			'ref_user_id' => null === $ref_user_id ? (int) $cert['ref_user_id'] : flxs_valid_ref_user( $ref_user_id ),
			'updated_at'  => flxs_now(),
		),
		array( 'id' => $id )
	);
	$diff = array();
	foreach ( $values as $k => $v ) {
		if ( ( $before[ $k ] ?? null ) !== $v ) {
			$diff[ $k ] = array( $before[ $k ] ?? null, $v );
		}
	}
	if ( (int) $cert['person_id'] !== (int) $person_id ) {
		$diff['person_id'] = array( (int) $cert['person_id'], (int) $person_id );
	}
	if ( null !== $ref_user_id && (int) $cert['ref_user_id'] !== flxs_valid_ref_user( $ref_user_id ) ) {
		$diff['referans'] = array( flxs_ref_label( $cert['ref_user_id'] ), flxs_ref_label( flxs_valid_ref_user( $ref_user_id ) ) );
	}
	flxs_log( 'certificate_updated', 'certificate', $id, $cert['number'], array( 'changes' => $diff ) );
	return flxs_get_certificate( $id );
}

function flxs_clean_values( $template, array $values ) {
	$out = array();
	foreach ( $template['config']['fields'] as $f ) {
		if ( in_array( $f['type'], array( 'static', 'qr', 'number_auto' ), true ) ) {
			continue;
		}
		$v = isset( $values[ $f['key'] ] ) ? $values[ $f['key'] ] : '';
		$v = 'textarea' === $f['type'] ? sanitize_textarea_field( $v ) : sanitize_text_field( $v );
		if ( 'date' === $f['type'] && '' !== $v && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
			return new WP_Error( 'flxs_date', $f['label'] . ': tarih biçimi geçersiz.' );
		}
		if ( $f['required'] && '' === trim( $v ) && 'ad_soyad' !== $f['key'] ) {
			return new WP_Error( 'flxs_required', $f['label'] . ' gerekli.' );
		}
		$out[ $f['key'] ] = $v;
	}
	return $out;
}

/** Tarayıcıda üretilen PDF / JPG dosyasını kaydeder. */
function flxs_store_certificate_file( $cert, $type, $tmp_path, $w = 0, $h = 0 ) {
	global $wpdb;
	$head = (string) file_get_contents( $tmp_path, false, null, 0, 8 );
	if ( 'pdf' === $type && 0 !== strpos( $head, '%PDF' ) ) {
		return new WP_Error( 'flxs_file', 'Geçersiz PDF.' );
	}
	if ( 'pdf' !== $type && "\xFF\xD8\xFF" !== substr( $head, 0, 3 ) ) {
		return new WP_Error( 'flxs_file', 'Geçersiz görsel (JPEG bekleniyor).' );
	}
	if ( filesize( $tmp_path ) > 15 * MB_IN_BYTES ) {
		return new WP_Error( 'flxs_file', 'Dosya çok büyük.' );
	}
	$up   = flxs_upload_dir();
	$sub  = gmdate( 'Y', strtotime( $cert['created_at'] . ' UTC' ) );
	wp_mkdir_p( $up['dir'] . '/' . $sub );
	$ext  = 'pdf' === $type ? 'pdf' : 'jpg';
	$name = $sub . '/' . $cert['code'] . ( 'og' === $type ? '-og' : '' ) . '-' . strtolower( wp_generate_password( 6, false ) ) . '.' . $ext;
	if ( ! @copy( $tmp_path, $up['dir'] . '/' . $name ) ) { // phpcs:ignore
		return new WP_Error( 'flxs_file', 'Dosya yazılamadı (uploads klasörü izinleri?).' );
	}
	$old = flxs_certificate_file_name( $cert, $type );
	if ( 'og' === $type ) {
		$data       = (array) $cert['data'];
		$data['og'] = $name;
		$upd        = array( 'data' => flxs_json( $data ) );
	} else {
		$upd = array( ( 'pdf' === $type ? 'pdf_file' : 'image_file' ) => $name );
	}
	if ( 'image' === $type ) {
		$upd['image_w'] = (int) $w;
		$upd['image_h'] = (int) $h;
	}
	$wpdb->update( flxs_table( 'certificates' ), $upd, array( 'id' => $cert['id'] ) );
	if ( $old && $old !== $name ) {
		wp_delete_file( $up['dir'] . '/' . $old );
	}
	return $name;
}

function flxs_set_certificate_status( $id, $status ) {
	global $wpdb;
	$cert = flxs_get_certificate( $id );
	if ( ! $cert ) {
		return false;
	}
	$wpdb->update( flxs_table( 'certificates' ), array( 'status' => $status, 'updated_at' => flxs_now() ), array( 'id' => $id ) );
	flxs_log( 'revoked' === $status ? 'certificate_revoked' : 'certificate_restored', 'certificate', $id, $cert['number'] );
	return true;
}

function flxs_delete_certificate( $id ) {
	global $wpdb;
	$cert = flxs_get_certificate( $id );
	if ( ! $cert ) {
		return false;
	}
	foreach ( array( 'pdf', 'image', 'og' ) as $t ) {
		$p = flxs_certificate_file_path( $cert, $t );
		if ( $p ) {
			wp_delete_file( $p );
		}
	}
	$wpdb->delete( flxs_table( 'certificates' ), array( 'id' => $id ) );
	flxs_log( 'certificate_deleted', 'certificate', $id, $cert['number'], array( 'code' => $cert['code'], 'values' => $cert['data']['values'] ?? array() ) );
	return true;
}

/** Sertifika listesi (admin). */
function flxs_query_certificates( $args = array() ) {
	global $wpdb;
	$a = wp_parse_args(
		$args,
		array(
			'search'      => '',
			'template_id' => 0,
			'status'      => '',
			'created_by'  => 0,
			'person_id'   => 0,
			'ref_user_id' => '',
			'event_id'    => '',
			'per_page'    => 20,
			'page'        => 1,
			'orderby'     => 'id',
			'order'       => 'DESC',
			'ids'         => array(),
			'scope'       => false, // true: geçerli kullanıcının görebildikleri (flxs_scope_sql)
		)
	);
	$c     = flxs_table( 'certificates' );
	$p     = flxs_table( 'people' );
	$t     = flxs_table( 'templates' );
	$where = array( '1=1' );
	$prm   = array();
	if ( $a['search'] ) {
		$like    = '%' . $wpdb->esc_like( $a['search'] ) . '%';
		$digits  = preg_replace( '/\D+/', '', $a['search'] );
		$where[] = '(p.name LIKE %s OR c.data LIKE %s OR p.email LIKE %s OR c.number LIKE %s OR c.code LIKE %s' . ( strlen( $digits ) >= 3 ? ' OR p.phone LIKE %s' : '' ) . ')';
		array_push( $prm, $like, $like, $like, $like, $like );
		if ( strlen( $digits ) >= 3 ) {
			$prm[] = '%' . $wpdb->esc_like( ltrim( $digits, '0' ) ) . '%';
		}
	}
	if ( '' !== (string) ( $a['ref_user_id'] ?? '' ) ) {
		$where[] = 'c.ref_user_id = %d';
		$prm[]   = (int) $a['ref_user_id'];
	}
	if ( '' !== (string) ( $a['event_id'] ?? '' ) ) {
		$where[] = 'c.event_id = %d';
		$prm[]   = (int) $a['event_id'];
	}
	foreach ( array( 'template_id', 'created_by', 'person_id' ) as $k ) {
		if ( $a[ $k ] ) {
			$where[] = "c.$k = %d";
			$prm[]   = (int) $a[ $k ];
		}
	}
	if ( $a['scope'] && ( $scope = flxs_scope_sql( 'c' ) ) ) {
		$where[] = $scope;
	}
	if ( $a['status'] ) {
		$where[] = 'c.status = %s';
		$prm[]   = $a['status'];
	}
	if ( $a['ids'] ) {
		$where[] = 'c.id IN (' . implode( ',', array_map( 'intval', $a['ids'] ) ) . ')';
	}
	$orderby = in_array( $a['orderby'], array( 'id', 'created_at', 'views', 'number' ), true ) ? 'c.' . $a['orderby'] : 'c.id';
	$order   = 'ASC' === strtoupper( $a['order'] ) ? 'ASC' : 'DESC';
	$w       = implode( ' AND ', $where );
	$from    = "FROM $c c LEFT JOIN $p p ON p.id = c.person_id LEFT JOIN $t t ON t.id = c.template_id WHERE $w";
	$total   = (int) $wpdb->get_var( $prm ? $wpdb->prepare( "SELECT COUNT(*) $from", $prm ) : "SELECT COUNT(*) $from" );
	$limit   = $a['per_page'] > 0 ? $wpdb->prepare( ' LIMIT %d OFFSET %d', $a['per_page'], ( max( 1, $a['page'] ) - 1 ) * $a['per_page'] ) : '';
	$sql     = "SELECT c.*, p.name AS person_name, p.phone AS person_phone, p.email AS person_email, t.name AS template_name $from ORDER BY $orderby $order" . $limit;
	$rows    = $wpdb->get_results( $prm ? $wpdb->prepare( $sql, $prm ) : $sql, ARRAY_A );
	foreach ( $rows as &$r ) {
		$r = flxs_prepare_certificate( $r );
	}
	return array( 'items' => $rows, 'total' => $total );
}

/** Sertifikadaki isim (kayıtta yoksa kişinin adı) */
function flxs_certificate_name( $cert, $person = null ) {
	$n = trim( (string) ( $cert['data']['values']['ad_soyad'] ?? '' ) );
	if ( '' !== $n ) {
		return $n;
	}
	if ( null === $person ) {
		$person = flxs_get_person( $cert['person_id'] );
	}
	return $person ? (string) $person['name'] : '';
}

/** JS / REST için sertifika özeti */
function flxs_certificate_for_js( $cert ) {
	$person = flxs_get_person( $cert['person_id'] );
	return array(
		'id'          => (int) $cert['id'],
		'code'        => $cert['code'],
		'number'      => $cert['number'],
		'title'       => $cert['title'],
		'status'      => $cert['status'],
		'template_id' => (int) $cert['template_id'],
		'values'      => (object) ( $cert['data']['values'] ?? array() ),
		'person'      => flxs_person_public( $person ),
		'url'         => flxs_certificate_url( $cert ),
		'pdf_url'     => flxs_certificate_pdf_url( $cert ),
		'image_url'   => flxs_certificate_file_url( $cert, 'image' ),
		'has_files'   => (bool) $cert['pdf_file'],
		'created_at'  => flxs_date( $cert['created_at'] ),
		'created_by'  => flxs_user_label( $cert['created_by'] ),
		'ref_user_id' => (int) $cert['ref_user_id'],
		'ref_user'    => flxs_ref_label( $cert['ref_user_id'] ),
		'event_id'    => (int) ( $cert['event_id'] ?? 0 ),
		'event'       => flxs_event_label( $cert['event_id'] ?? 0 ),
	);
}

function flxs_user_can_edit_certificate( $cert ) {
	if ( flxs_can_manage() ) {
		return true;
	}
	return flxs_can_use() && (int) $cert['created_by'] === get_current_user_id();
}

function flxs_ref_label( $user_id ) {
	return (int) $user_id ? flxs_user_label( (int) $user_id ) : '';
}

/** Referans + toplu referans atama */
function flxs_set_certificate_ref( $id, $ref_user_id ) {
	global $wpdb;
	$cert = flxs_get_certificate( $id );
	if ( ! $cert ) {
		return false;
	}
	$ref = flxs_valid_ref_user( $ref_user_id );
	if ( (int) $cert['ref_user_id'] === $ref ) {
		return true;
	}
	$wpdb->update( flxs_table( 'certificates' ), array( 'ref_user_id' => $ref, 'updated_at' => flxs_now() ), array( 'id' => $id ) );
	flxs_log( 'certificate_updated', 'certificate', $id, $cert['number'], array( 'changes' => array( 'referans' => array( flxs_ref_label( $cert['ref_user_id'] ), flxs_ref_label( $ref ) ) ) ) );
	return true;
}

