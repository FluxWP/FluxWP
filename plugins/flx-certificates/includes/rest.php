<?php
defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'flxs_register_rest' );

function flxs_register_rest() {
	$ns  = 'flx-certificates/v1';
	$use = function () {
		return flxs_can_use();
	};
	$man = function () {
		return flxs_can_manage();
	};
	$bulk = function () {
		return flxs_can_bulk();
	};

	register_rest_route( $ns, '/people', array( 'methods' => 'GET', 'callback' => 'flxs_rest_people', 'permission_callback' => $use ) );
	register_rest_route( $ns, '/directory', array( 'methods' => 'GET', 'callback' => 'flxs_rest_directory', 'permission_callback' => $use ) );
	register_rest_route(
		$ns,
		'/certificates',
		array(
			array( 'methods' => 'GET', 'callback' => 'flxs_rest_cert_list', 'permission_callback' => $use ),
			array( 'methods' => 'POST', 'callback' => 'flxs_rest_cert_save', 'permission_callback' => $use ),
		)
	);
	register_rest_route( $ns, '/messages', array( 'methods' => 'GET', 'callback' => 'flxs_rest_messages', 'permission_callback' => $use ) );
	register_rest_route( $ns, '/overview', array( 'methods' => 'GET', 'callback' => 'flxs_rest_overview', 'permission_callback' => 'flxs_can_dashboard' ) );
	add_filter( 'rest_post_dispatch', 'flxs_rest_app_nocache', 10, 3 );
	register_rest_route(
		$ns,
		'/certificates/(?P<id>\d+)',
		array(
			array( 'methods' => 'GET', 'callback' => 'flxs_rest_cert_get', 'permission_callback' => $use ),
			array( 'methods' => 'POST', 'callback' => 'flxs_rest_cert_save', 'permission_callback' => $use ),
		)
	);
	register_rest_route( $ns, '/certificates/(?P<id>\d+)/files', array( 'methods' => 'POST', 'callback' => 'flxs_rest_cert_files', 'permission_callback' => $use ) );
	register_rest_route( $ns, '/certificates/(?P<id>\d+)/send', array( 'methods' => 'POST', 'callback' => 'flxs_rest_cert_send', 'permission_callback' => $use ) );
	register_rest_route(
		$ns,
		'/certificates/(?P<id>\d+)/message',
		array(
			array( 'methods' => 'GET', 'callback' => 'flxs_rest_cert_message', 'permission_callback' => $use ),
			array( 'methods' => 'POST', 'callback' => 'flxs_rest_cert_send_to', 'permission_callback' => $use ),
		)
	);
	register_rest_route(
		$ns,
		'/recipients',
		array(
			array( 'methods' => 'GET', 'callback' => 'flxs_rest_recipients', 'permission_callback' => $use ),
			array( 'methods' => 'POST', 'callback' => 'flxs_rest_recipient_add', 'permission_callback' => $use ),
		)
	);
	register_rest_route( $ns, '/recipients/(?P<rid>[a-z0-9]+)', array( 'methods' => 'DELETE', 'callback' => 'flxs_rest_recipient_delete', 'permission_callback' => $use ) );
	register_rest_route( $ns, '/stats', array( 'methods' => 'GET', 'callback' => 'flxs_rest_stats', 'permission_callback' => 'flxs_can_dashboard' ) );
	register_rest_route( $ns, '/certificates/(?P<id>\d+)/ref', array( 'methods' => 'POST', 'callback' => 'flxs_rest_cert_ref', 'permission_callback' => $use ) );
	register_rest_route( $ns, '/certificates/(?P<id>\d+)/status', array( 'methods' => 'POST', 'callback' => 'flxs_rest_cert_status', 'permission_callback' => $use ) );
	register_rest_route( $ns, '/templates', array( 'methods' => 'POST', 'callback' => 'flxs_rest_template_save', 'permission_callback' => $man ) );
	register_rest_route( $ns, '/templates/(?P<id>\d+)/thumb', array( 'methods' => 'POST', 'callback' => 'flxs_rest_template_thumb', 'permission_callback' => $man ) );
	register_rest_route( $ns, '/queue/run', array( 'methods' => 'POST', 'callback' => 'flxs_rest_queue_run', 'permission_callback' => $use ) );
	register_rest_route( $ns, '/queue/status', array( 'methods' => 'GET', 'callback' => 'flxs_rest_queue_status', 'permission_callback' => $use ) );
	register_rest_route( $ns, '/messages/(?P<id>\d+)/(?P<op>retry|cancel)', array( 'methods' => 'POST', 'callback' => 'flxs_rest_message_op', 'permission_callback' => $use ) );
	register_rest_route( $ns, '/bulk', array( 'methods' => 'POST', 'callback' => 'flxs_rest_bulk', 'permission_callback' => $bulk ) );
	register_rest_route( $ns, '/providers/(?P<id>\d+)/test', array( 'methods' => 'POST', 'callback' => 'flxs_rest_provider_test', 'permission_callback' => $man ) );
	register_rest_route( $ns, '/public-files/(?P<code>[A-Za-z0-9]{6,40})', array( 'methods' => 'POST', 'callback' => 'flxs_rest_public_files', 'permission_callback' => '__return_true' ) );
	register_rest_route( $ns, '/lookup', array( 'methods' => 'POST', 'callback' => 'flxs_rest_lookup', 'permission_callback' => '__return_true' ) );
}

function flxs_rest_error( $e, $status = 400 ) {
	return new WP_REST_Response( array( 'ok' => false, 'message' => is_wp_error( $e ) ? $e->get_error_message() : (string) $e ), $status );
}

function flxs_rest_can_see( $cert ) {
	return flxs_can_see_certificate( $cert );
}

function flxs_rest_people( WP_REST_Request $r ) {
	$rows = flxs_search_people( (string) $r->get_param( 'search' ), 8, flxs_own_ref() );
	return array_map( 'flxs_person_public', $rows );
}

function flxs_rest_cert_get( WP_REST_Request $r ) {
	$cert = flxs_get_certificate( (int) $r['id'] );
	if ( ! flxs_rest_can_see( $cert ) ) {
		return flxs_rest_error( 'Sertifika bulunamadı.', 404 );
	}
	return flxs_certificate_for_js( $cert );
}

/** Oluştur / güncelle */
function flxs_rest_cert_save( WP_REST_Request $r ) {
	$id     = (int) $r['id'];
	$p      = (array) $r->get_param( 'person' );
	$values = (array) $r->get_param( 'values' );
	if ( $id ) {
		$cert = flxs_get_certificate( $id );
		if ( ! $cert || ! flxs_user_can_edit_certificate( $cert ) ) {
			return flxs_rest_error( 'Bu sertifikayı düzenleme yetkiniz yok.', 403 );
		}
	}
	$name = $p['name'] ?? ( $values['ad_soyad'] ?? '' );
	$up   = flxs_upsert_person( $name, $p['phone'] ?? '', $p['email'] ?? '', (int) ( $p['id'] ?? 0 ) );
	if ( is_wp_error( $up ) ) {
		return flxs_rest_error( $up );
	}
	$values['ad_soyad'] = trim( sanitize_text_field( $name ) );
	$ref = $r->has_param( 'ref_user' ) ? (int) $r->get_param( 'ref_user' ) : null;
	$evt = $r->has_param( 'event' ) ? (int) $r->get_param( 'event' ) : null;
	if ( $evt && ! flxs_valid_event( $evt ) && ( ! $id || (int) ( flxs_get_certificate( $id )['event_id'] ?? 0 ) !== $evt ) ) {
		return flxs_rest_error( 'Seçilen etkinlik kapatılmış. Açık bir etkinlik seçin.' );
	}
	if ( $id ) {
		$cert = flxs_update_certificate( $id, (int) $up['person']['id'], $values, $ref );
	} else {
		$issued = (string) $r->get_param( 'issued' );
		if ( '' !== $issued ) {
			if ( ! flxs_can_manage() ) {
				return flxs_rest_error( 'Geçmiş tarihli sertifikayı yalnızca yönetici oluşturabilir.', 403 );
			}
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $issued ) || $issued > wp_date( 'Y-m-d' ) || $issued < '2015-01-01' ) {
				return flxs_rest_error( 'Geçmiş tarih geçersiz.' );
			}
		}
		$cert = flxs_create_certificate( (int) $r->get_param( 'template_id' ), (int) $up['person']['id'], $values, (int) $ref, $issued );
	}
	if ( is_wp_error( $cert ) ) {
		return flxs_rest_error( $cert );
	}
	if ( null !== $evt && (int) $cert['event_id'] !== $evt ) {
		flxs_set_certificate_event( $cert['id'], $evt );
		$cert = flxs_get_certificate( $cert['id'] );
	}
	return array(
		'ok'          => true,
		'certificate' => flxs_certificate_for_js( $cert ),
		'person'      => flxs_person_public( $up['person'] ),
		'notes'       => $up['notes'],
	);
}

function flxs_rest_cert_files( WP_REST_Request $r ) {
	$cert = flxs_get_certificate( (int) $r['id'] );
	if ( ! $cert || ! flxs_user_can_edit_certificate( $cert ) ) {
		return flxs_rest_error( 'Yetki yok.', 403 );
	}
	$files = $r->get_file_params();
	$saved = array();
	foreach ( array( 'pdf' => 'pdf', 'image' => 'image', 'og' => 'og' ) as $key => $type ) {
		if ( empty( $files[ $key ]['tmp_name'] ) || ! is_uploaded_file( $files[ $key ]['tmp_name'] ) ) {
			continue;
		}
		$res = flxs_store_certificate_file( $cert, $type, $files[ $key ]['tmp_name'], (int) $r->get_param( 'w' ), (int) $r->get_param( 'h' ) );
		if ( is_wp_error( $res ) ) {
			return flxs_rest_error( $res );
		}
		$saved[] = $type;
		$cert    = flxs_get_certificate( $cert['id'] );
	}
	return array( 'ok' => true, 'saved' => $saved, 'certificate' => flxs_certificate_for_js( $cert ) );
}

/**
 * Sertifika sayfasını açan tarayıcı, eksik PDF / görsel / paylaşım görselini çizip buraya yükler.
 * Yalnızca EKSİK dosya yazılır (var olan değiştirilemez), sayfadaki imza gerekir, dosya türü ve boyutu doğrulanır.
 */
function flxs_rest_public_files( WP_REST_Request $r ) {
	$cert = flxs_get_certificate_by_code( (string) $r['code'] );
	if ( ! $cert || ! hash_equals( flxs_public_file_token( $cert ), (string) $r->get_param( 'token' ) ) ) {
		return flxs_rest_error( 'Yetki yok.', 403 );
	}
	$need = flxs_certificate_missing_files( $cert );
	if ( ! $need ) {
		return array( 'ok' => true, 'saved' => array() );
	}
	$files = $r->get_file_params();
	if ( ! $files ) {
		// Dosyasız istek: yalnızca hâlâ eksik olanları bildir (önbellekteki eski sayfa boşuna çizim yapmasın)
		return array( 'ok' => true, 'need' => flxs_public_files_blocked( $cert ) ? array() : $need );
	}
	$key = 'flxs_pf_' . $cert['id'];
	$n   = (int) get_transient( $key );
	if ( $n >= 12 ) {
		return flxs_rest_error( 'Çok fazla deneme.', 429 );
	}
	set_transient( $key, $n + 1, 6 * HOUR_IN_SECONDS );
	$saved = array();
	foreach ( $need as $type ) {
		if ( empty( $files[ $type ]['tmp_name'] ) || ! is_uploaded_file( $files[ $type ]['tmp_name'] ) ) {
			continue;
		}
		$tmp = $files[ $type ]['tmp_name'];
		$w   = 0;
		$h   = 0;
		if ( 'pdf' !== $type ) {
			$sz = @getimagesize( $tmp ); // phpcs:ignore
			if ( ! $sz || IMAGETYPE_JPEG !== $sz[2] || ( 'og' === $type && ( 1200 !== (int) $sz[0] || 630 !== (int) $sz[1] ) ) || $sz[0] > 4000 || $sz[1] > 4000 ) {
				continue;
			}
			$w = (int) $sz[0];
			$h = (int) $sz[1];
		}
		$res = flxs_store_certificate_file( $cert, $type, $tmp, $w, $h );
		if ( ! is_wp_error( $res ) ) {
			$saved[] = $type;
			$cert    = flxs_get_certificate( $cert['id'] );
		}
	}
	if ( $saved ) {
		flxs_log( 'files_completed', 'certificate', $cert['id'], 'Sayfa açılışında eksik dosyalar üretildi: ' . implode( ', ', $saved ), array(), 0 );
	}
	return array( 'ok' => true, 'saved' => $saved );
}

function flxs_rest_cert_send( WP_REST_Request $r ) {
	$cert = flxs_get_certificate( (int) $r['id'] );
	if ( ! flxs_rest_can_see( $cert ) ) {
		return flxs_rest_error( 'Sertifika bulunamadı.', 404 );
	}
	$channels  = array_map( 'sanitize_key', (array) $r->get_param( 'channels' ) );
	$providers = array_map( 'intval', (array) $r->get_param( 'providers' ) );
	$res       = flxs_send_certificate( $cert, $channels, $providers, (string) $r->get_param( 'source' ) === 'create' ? 'create' : 'manual' );
	$out       = array();
	foreach ( $res as $ch => $v ) {
		$out[ $ch ] = is_wp_error( $v ) ? array( 'ok' => false, 'message' => $v->get_error_message() ) : array( 'ok' => true, 'message_id' => $v );
	}
	flxs_kick_queue();
	return array( 'ok' => true, 'results' => $out );
}

function flxs_rest_cert_status( WP_REST_Request $r ) {
	$cert = flxs_get_certificate( (int) $r['id'] );
	if ( ! $cert || ! flxs_user_can_edit_certificate( $cert ) ) {
		return flxs_rest_error( 'Yetki yok.', 403 );
	}
	$status = 'revoked' === $r->get_param( 'status' ) ? 'revoked' : 'active';
	flxs_set_certificate_status( $cert['id'], $status );
	return array( 'ok' => true, 'status' => $status );
}

function flxs_rest_template_save( WP_REST_Request $r ) {
	$id     = (int) $r->get_param( 'id' );
	$name   = (string) $r->get_param( 'name' );
	$config = (array) $r->get_param( 'config' );
	if ( '' === trim( $name ) ) {
		return flxs_rest_error( 'Şablon adı gerekli.' );
	}
	$keys = array();
	foreach ( (array) ( $config['fields'] ?? array() ) as $f ) {
		$k = sanitize_key( $f['key'] ?? '' );
		if ( isset( $keys[ $k ] ) ) {
			return flxs_rest_error( 'Alan anahtarı iki kez kullanılmış: ' . $k );
		}
		$keys[ $k ] = 1;
	}
	$id = flxs_save_template( $id, $name, $config, (string) $r->get_param( 'status' ) );
	return array( 'ok' => true, 'template' => flxs_template_for_js( flxs_get_template( $id ) ) );
}

function flxs_rest_template_thumb( WP_REST_Request $r ) {
	global $wpdb;
	$t     = flxs_get_template( (int) $r['id'] );
	$files = $r->get_file_params();
	if ( ! $t || empty( $files['image']['tmp_name'] ) ) {
		return flxs_rest_error( 'Eksik.' );
	}
	$tmp = $files['image']['tmp_name'];
	if ( "\xFF\xD8\xFF" !== (string) file_get_contents( $tmp, false, null, 0, 3 ) ) {
		return flxs_rest_error( 'JPEG bekleniyor.' );
	}
	$up   = flxs_upload_dir();
	wp_mkdir_p( $up['dir'] . '/sablon' );
	$name = 'sablon/' . (int) $t['id'] . '-' . strtolower( wp_generate_password( 6, false ) ) . '.jpg';
	copy( $tmp, $up['dir'] . '/' . $name );
	if ( $t['thumb'] ) {
		wp_delete_file( $up['dir'] . '/' . $t['thumb'] );
	}
	$wpdb->update( flxs_table( 'templates' ), array( 'thumb' => $name ), array( 'id' => $t['id'] ) );
	return array( 'ok' => true, 'thumb' => $up['url'] . '/' . $name );
}

function flxs_rest_queue_run( WP_REST_Request $r ) {
	$res = flxs_run_queue( 15 );
	return array_merge( $res, array( 'counts' => flxs_queue_counts( 0, true ) ) );
}

function flxs_rest_queue_status( WP_REST_Request $r ) {
	$ids = array_filter( array_map( 'intval', explode( ',', (string) $r->get_param( 'ids' ) ) ) );
	$out = array( 'counts' => flxs_queue_counts( (int) $r->get_param( 'batch' ), true ) );
	if ( $ids ) {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT id, status, last_error, channel, attempts, sent_at FROM ' . flxs_table( 'messages' ) . ' WHERE id IN (' . implode( ',', $ids ) . ')', ARRAY_A );
		$out['messages'] = $rows;
	}
	return $out;
}

function flxs_rest_message_op( WP_REST_Request $r ) {
	$ok = 'retry' === $r['op'] ? flxs_retry_message( (int) $r['id'] ) : flxs_cancel_message( (int) $r['id'] );
	if ( $ok && 'retry' === $r['op'] ) {
		flxs_kick_queue();
	}
	return array( 'ok' => (bool) $ok );
}

function flxs_rest_bulk( WP_REST_Request $r ) {
	$spec = (array) $r->get_param( 'spec' );
	$res  = flxs_bulk_send( $spec, (bool) $r->get_param( 'dry' ) );
	if ( is_wp_error( $res ) ) {
		return flxs_rest_error( $res );
	}
	return array_merge( array( 'ok' => true ), $res );
}

function flxs_rest_provider_test( WP_REST_Request $r ) {
	$p = flxs_get_provider( (int) $r['id'] );
	if ( ! $p ) {
		return flxs_rest_error( 'Sağlayıcı yok.', 404 );
	}
	$types = flxs_provider_types();
	$to    = trim( (string) $r->get_param( 'to' ) );
	$res   = call_user_func( $types[ $p['type'] ]['test'], $p['config'], $to, $p );
	flxs_log( 'provider_test', 'provider', $p['id'], $p['name'] . ( $to ? ' → ' . $to : '' ) . ' · ' . ( $res['ok'] ? 'başarılı' : $res['error'] ), array( 'response' => $res['response'] ?? null ) );
	return array(
		'ok'       => (bool) $res['ok'],
		'message'  => $res['ok'] ? 'Başarılı' : ( $res['error'] ?? 'Hata' ),
		'response' => $res['response'] ?? null,
	);
}

/**
 * Herkese açık sorgulama: telefon/e-posta girilir, sertifika linkleri SADECE o adrese gönderilir.
 * Sayfada hiçbir kişisel veri gösterilmez; kayıt olsa da olmasa da aynı yanıt döner.
 */
function flxs_rest_lookup( WP_REST_Request $r ) {
	// Ziyaretçinin sayfa dili (çok dilli sitelerde REST isteği varsayılan dilde gelir)
	$lang = (string) $r->get_param( 'lang' );
	if ( $lang && preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?$/', $lang ) && $lang !== determine_locale() ) {
		switch_to_locale( $lang );
	}
	if ( ! flxs_setting( 'lookup_enabled' ) ) {
		return flxs_rest_error( __( 'Lookup is disabled.', 'flx-certificates' ), 403 );
	}
	// Sayfa önbellekte olabileceği için nonce yerine IP + adres limiti ve bal küpü alanı kullanılıyor.
	if ( '' !== (string) $r->get_param( 'website' ) ) { // bal küpü
		return array( 'ok' => true, 'message' => flxs_lookup_message() );
	}
	$contact = trim( (string) $r->get_param( 'contact' ) );
	$ip      = flxs_client_ip();
	$ipkey   = 'flxs_lk_ip_' . md5( $ip );
	$hits    = (int) get_transient( $ipkey );
	if ( $hits >= 10 ) {
		return flxs_rest_error( __( 'Too many attempts. Please try again later.', 'flx-certificates' ), 429 );
	}
	set_transient( $ipkey, $hits + 1, HOUR_IN_SECONDS );

	$is_email = (bool) is_email( $contact );
	$phone    = $is_email ? '' : flxs_normalize_phone( $contact );
	if ( ! $is_email && ! $phone ) {
		return flxs_rest_error( __( 'Enter a valid phone number or email address.', 'flx-certificates' ) );
	}
	$ckey = 'flxs_lk_c_' . md5( $is_email ? strtolower( $contact ) : $phone );
	if ( get_transient( $ckey ) ) {
		return array( 'ok' => true, 'message' => flxs_lookup_message() );
	}
	set_transient( $ckey, 1, 15 * MINUTE_IN_SECONDS );

	$person = $is_email ? flxs_find_person_by_email( $contact ) : flxs_find_person_by_phone( $phone );
	if ( $person ) {
		$q     = flxs_query_certificates( array( 'person_id' => $person['id'], 'status' => 'active', 'per_page' => 10 ) );
		$allow = (array) flxs_setting( 'lookup_channels' );
		if ( $q['items'] ) {
			$lines = array();
			foreach ( $q['items'] as $c ) {
				$lines[] = $c['title'] . ': ' . flxs_certificate_url( $c );
			}
			$channel = $is_email ? 'email' : ( in_array( 'whatsapp', $allow, true ) && flxs_resolve_provider( 'whatsapp' ) ? 'whatsapp' : 'sms' );
			if ( in_array( $channel, $allow, true ) && flxs_resolve_provider( $channel ) ) {
				/* translators: 1: ad, 2: site adı, 3: sertifika linkleri (satır satır) */
				$body = sprintf( __( 'Hello %1$s,

Your %2$s certificates:
%3$s', 'flx-certificates' ), flxs_first_name( $person['name'] ), get_bloginfo( 'name' ), implode( "\n", $lines ) );
				flxs_enqueue(
					array(
						'channel'        => $channel,
						'recipient'      => $is_email ? $person['email'] : $person['phone'],
						'recipient_name' => $person['name'],
						'person_id'      => $person['id'],
						'subject'        => __( 'Your certificates', 'flx-certificates' ) . ' · ' . get_bloginfo( 'name' ),
						'body'           => $body,
						'source'         => 'lookup',
					)
				);
				flxs_kick_queue();
			}
		}
		flxs_log( 'lookup_request', 'person', $person['id'], 'Sorgulama: ' . count( $q['items'] ) . ' sertifika', array( 'channel' => $is_email ? 'email' : 'sms' ), 0 );
	}
	return array( 'ok' => true, 'message' => flxs_lookup_message() );
}

function flxs_lookup_message() {
	return __( 'If a certificate is registered with these details, its link will be sent to you within a few minutes.', 'flx-certificates' );
}

/* ---------- Başka birine gönder + kayıtlı alıcılar (rehber) ---------- */

/** Kanal için hazır mesaj metni (düzenlenebilir önizleme) */
function flxs_rest_cert_message( WP_REST_Request $r ) {
	$cert = flxs_get_certificate( (int) $r['id'] );
	if ( ! flxs_rest_can_see( $cert ) ) {
		return flxs_rest_error( 'Sertifika bulunamadı.', 404 );
	}
	$ch = sanitize_key( (string) $r->get_param( 'channel' ) );
	if ( ! isset( flxs_channels()[ $ch ] ) ) {
		return flxs_rest_error( 'Kanal geçersiz.' );
	}
	$m = flxs_build_certificate_message( $cert, $ch, array( 'to' => 'email' === $ch ? 'onizleme@ornek.com' : '05000000000' ) );
	if ( is_wp_error( $m ) ) {
		return flxs_rest_error( $m );
	}
	$p = flxs_get_person( $cert['person_id'] );
	return array(
		'ok'      => true,
		'subject' => $m['subject'],
		'body'    => $m['body'],
		'url'     => flxs_certificate_url( $cert ),
		'title'   => $cert['title'],
		'owner'   => $p ? flxs_person_public( $p ) : null,
		'has_provider' => (bool) flxs_resolve_provider( $ch ),
	);
}

function flxs_rest_cert_send_to( WP_REST_Request $r ) {
	$cert = flxs_get_certificate( (int) $r['id'] );
	if ( ! flxs_rest_can_see( $cert ) ) {
		return flxs_rest_error( 'Sertifika bulunamadı.', 404 );
	}
	$ch = sanitize_key( (string) $r->get_param( 'channel' ) );
	if ( ! isset( flxs_channels()[ $ch ] ) ) {
		return flxs_rest_error( 'Kanal geçersiz.' );
	}
	$to   = trim( (string) $r->get_param( 'to' ) );
	$name = sanitize_text_field( (string) $r->get_param( 'name' ) );
	if ( '' === $to ) {
		return flxs_rest_error( 'email' === $ch ? 'E-posta adresi girin.' : 'Telefon numarası girin.' );
	}
	$ov = array(
		'to'      => $to,
		'to_name' => $name,
		'body'    => sanitize_textarea_field( (string) $r->get_param( 'body' ) ),
		'subject' => sanitize_text_field( (string) $r->get_param( 'subject' ) ),
	);
	$res = flxs_send_certificate( $cert, array( $ch ), array( $ch => (int) $r->get_param( 'provider' ) ), 'forward', 0, array( $ch => $ov ) );
	$v   = $res[ $ch ] ?? new WP_Error( 'flxs', 'Gönderilemedi.' );
	if ( is_wp_error( $v ) ) {
		return flxs_rest_error( $v );
	}
	if ( $r->get_param( 'save' ) ) {
		flxs_save_recipient( $name, 'email' === $ch ? '' : $to, 'email' === $ch ? $to : '' );
	}
	flxs_kick_queue();
	return array( 'ok' => true, 'message_id' => $v );
}

function flxs_get_recipients() {
	$list = get_option( 'flxs_recipients', array() );
	return is_array( $list ) ? array_values( $list ) : array();
}

/** Rehbere ekler; aynı numara/e-posta varsa adını günceller. */
function flxs_save_recipient( $name, $phone_raw, $email_raw ) {
	$phone = flxs_normalize_phone( $phone_raw );
	$email = strtolower( sanitize_email( $email_raw ) );
	if ( ! $phone && ! is_email( $email ) ) {
		return new WP_Error( 'flxs_to', 'Geçerli bir telefon ya da e-posta girin.' );
	}
	$name = sanitize_text_field( $name );
	$list = flxs_get_recipients();
	foreach ( $list as &$x ) {
		if ( ( $phone && $x['phone'] === $phone ) || ( ! $phone && $email && $x['email'] === $email ) ) {
			if ( $name ) {
				$x['name'] = $name;
			}
			if ( $email && ! $x['email'] ) {
				$x['email'] = $email;
			}
			update_option( 'flxs_recipients', $list, false );
			return $x;
		}
	}
	unset( $x );
	$item   = array(
		'id'         => strtolower( wp_generate_password( 8, false ) ),
		'name'       => $name ? $name : ( $phone ? flxs_format_phone( $phone ) : $email ),
		'phone'      => $phone,
		'email'      => $phone ? ( is_email( $email ) ? $email : '' ) : $email,
		'created_by' => get_current_user_id(),
		'created_at' => flxs_now(),
	);
	array_unshift( $list, $item );
	update_option( 'flxs_recipients', array_slice( $list, 0, 500 ), false );
	flxs_log( 'recipient_saved', 'recipient', 0, $item['name'], array( 'phone' => $phone, 'email' => $item['email'] ) );
	return $item;
}

function flxs_recipient_public( $x ) {
	return array(
		'id'     => $x['id'],
		'name'   => $x['name'],
		'phone'  => $x['phone'] ? flxs_format_phone( $x['phone'] ) : '',
		'email'  => $x['email'],
	);
}

function flxs_rest_recipients() {
	return array_map( 'flxs_recipient_public', flxs_get_recipients() );
}

function flxs_rest_recipient_add( WP_REST_Request $r ) {
	$x = flxs_save_recipient( (string) $r->get_param( 'name' ), (string) $r->get_param( 'phone' ), (string) $r->get_param( 'email' ) );
	return is_wp_error( $x ) ? flxs_rest_error( $x ) : array( 'ok' => true, 'recipient' => flxs_recipient_public( $x ) );
}

function flxs_rest_recipient_delete( WP_REST_Request $r ) {
	$list = flxs_get_recipients();
	$keep = array();
	$gone = null;
	foreach ( $list as $x ) {
		if ( $x['id'] === $r['rid'] ) {
			$gone = $x;
		} else {
			$keep[] = $x;
		}
	}
	if ( ! $gone ) {
		return flxs_rest_error( 'Bulunamadı.', 404 );
	}
	update_option( 'flxs_recipients', $keep, false );
	flxs_log( 'recipient_deleted', 'recipient', 0, $gone['name'] );
	return array( 'ok' => true );
}

function flxs_rest_cert_ref( WP_REST_Request $r ) {
	$cert = flxs_get_certificate( (int) $r['id'] );
	if ( ! $cert || ! flxs_user_can_edit_certificate( $cert ) ) {
		return flxs_rest_error( 'Yetki yok.', 403 );
	}
	$ref = flxs_valid_ref_user( (int) $r->get_param( 'ref_user' ) );
	flxs_set_certificate_ref( $cert['id'], $ref );
	return array( 'ok' => true, 'ref_user_id' => $ref, 'ref_user' => flxs_ref_label( $ref ) );
}


/** Uygulama listeleri önbelleğe alınmasın. */
function flxs_rest_app_nocache( $result, $server, $request ) {
	unset( $server );
	$route = (string) $request->get_route();
	$live  = array( '/flx-certificates/v1/certificates', '/flx-certificates/v1/directory', '/flx-certificates/v1/messages', '/flx-certificates/v1/queue/status', '/flx-certificates/v1/stats', '/flx-certificates/v1/overview' );
	if ( $result instanceof WP_REST_Response && in_array( $route, $live, true ) ) {
		$result->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$result->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
		$result->header( 'CDN-Cache-Control', 'no-store' );
		do_action( 'litespeed_control_set_nocache', 'flxs-app' );
	}
	return $result;
}

function flxs_rest_pages( $total, $per ) {
	return max( 1, (int) ceil( (int) $total / max( 1, (int) $per ) ) );
}

/** Sertifika listesi (uygulama). Oluşturma POST'u aynı yolda durur. */
function flxs_rest_cert_list( WP_REST_Request $r ) {
	$per = min( 100, max( 1, absint( $r->get_param( 'per_page' ) ? $r->get_param( 'per_page' ) : 25 ) ) );
	$q   = flxs_query_certificates(
		array(
			'search'   => sanitize_text_field( (string) $r->get_param( 's' ) ),
			'status'   => sanitize_key( (string) $r->get_param( 'status' ) ),
			'page'     => max( 1, absint( $r->get_param( 'page' ) ) ),
			'per_page' => $per,
			'scope'    => true,
			'orderby'  => 'created_at',
		)
	);
	$items = array();
	foreach ( $q['items'] as $row ) {
		$js              = flxs_certificate_for_js( $row );
		$js['name']      = (string) ( $row['person_name'] ?? ( $js['person']['name'] ?? '' ) );
		$js['email']     = (string) ( $row['person_email'] ?? ( $js['person']['email'] ?? '' ) );
		$js['phone']     = (string) ( $js['person']['phone_label'] ?? '' );
		$js['template']  = (string) ( $row['template_name'] ?? '' );
		$js['date_label'] = flxs_date( $row['created_at'] );
		$js['status_label'] = 'revoked' === $row['status'] ? __( 'Cancelled', 'flx-certificates' ) : __( 'Valid', 'flx-certificates' );
		$items[]         = $js;
	}
	return array(
		'items' => $items,
		'total' => (int) $q['total'],
		'pages' => flxs_rest_pages( $q['total'], $per ),
	);
}

/** Kişiler, sayfalı. Arama ucu `/people` olduğu gibi kalır. */
function flxs_rest_directory( WP_REST_Request $r ) {
	global $wpdb;
	$per  = min( 100, max( 1, absint( $r->get_param( 'per_page' ) ? $r->get_param( 'per_page' ) : 25 ) ) );
	$page = max( 1, absint( $r->get_param( 'page' ) ) );
	$s    = sanitize_text_field( (string) $r->get_param( 's' ) );
	$t     = flxs_table( 'people' );
	$where = array( '1=1' );
	$prm   = array();
	if ( '' !== $s ) {
		$like    = '%' . $wpdb->esc_like( $s ) . '%';
		$where[] = '(p.name LIKE %s OR p.email LIKE %s OR p.phone LIKE %s)';
		array_push( $prm, $like, $like, $like );
	}
	$scope = flxs_people_scope_sql( 'p' );
	if ( $scope ) {
		$where[] = $scope;
	}
	$w     = implode( ' AND ', $where );
	$total = (int) ( $prm ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} p WHERE {$w}", $prm ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM {$t} p WHERE {$w}" ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	$sql   = "SELECT p.* FROM {$t} p WHERE {$w} ORDER BY p.id DESC" . $wpdb->prepare( ' LIMIT %d OFFSET %d', $per, ( $page - 1 ) * $per );
	$rows  = $prm ? $wpdb->get_results( $wpdb->prepare( $sql, $prm ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	$items = array();
	foreach ( (array) $rows as $row ) {
		$pub         = flxs_person_public( $row );
		$pub['date_label'] = flxs_date( $row['created_at'] );
		$pub['certs'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxs_table( 'certificates' ) . ' WHERE person_id = %d', (int) $row['id'] ) );
		$items[]     = $pub;
	}
	return array( 'items' => $items, 'total' => $total, 'pages' => flxs_rest_pages( $total, $per ) );
}

/** Gönderim kuyruğu. */
function flxs_rest_messages( WP_REST_Request $r ) {
	$per = min( 100, max( 1, absint( $r->get_param( 'per_page' ) ? $r->get_param( 'per_page' ) : 25 ) ) );
	$q   = flxs_query_messages(
		array(
			'search'   => sanitize_text_field( (string) $r->get_param( 's' ) ),
			'status'   => sanitize_key( (string) $r->get_param( 'status' ) ),
			'channel'  => sanitize_key( (string) $r->get_param( 'channel' ) ),
			'page'     => max( 1, absint( $r->get_param( 'page' ) ) ),
			'per_page' => $per,
			'scope'    => true,
		)
	);
	$labels = flxs_status_labels();
	$items  = array();
	foreach ( $q['items'] as $row ) {
		$items[] = array(
			'id'           => (int) $row['id'],
			'certificate_id' => (int) $row['certificate_id'],
			'channel'      => (string) $row['channel'],
			'channel_label' => flxs_channel_label( $row['channel'] ),
			'status'       => (string) $row['status'],
			'status_label' => $labels[ $row['status'] ] ?? $row['status'],
			'name'         => (string) $row['recipient_name'],
			'recipient'    => (string) $row['recipient'],
			'subject'      => (string) $row['subject'],
			'error'        => (string) $row['last_error'],
			'date_label'   => flxs_date( $row['created_at'] ),
		);
	}
	return array(
		'items' => $items,
		'total' => (int) $q['total'],
		'pages' => flxs_rest_pages( $q['total'], $per ),
	);
}

/** Uygulama özeti: sayaçlar ve seri, tek kaynak. */
function flxs_rest_overview() {
	$stats = flxs_stats( array() );
	$q     = flxs_queue_counts( 0, true );
	return array(
		'certs'   => (int) ( $stats['totals']['certs'] ?? 0 ),
		'people'  => (int) ( $stats['totals']['people'] ?? 0 ),
		'fidan'   => (int) ( $stats['totals']['fidan'] ?? 0 ),
		'queued'  => (int) ( $q['queued'] ?? 0 ),
		'failed'  => (int) ( $q['failed'] ?? 0 ),
		'series'  => array_values( (array) ( $stats['series'] ?? array() ) ),
	);
}

/** Dashboard verisi */
function flxs_rest_stats( WP_REST_Request $r ) {
	return rest_ensure_response(
		flxs_stats(
			array(
				'from' => sanitize_text_field( (string) $r->get_param( 'from' ) ),
				'to'   => sanitize_text_field( (string) $r->get_param( 'to' ) ),
				'ref'  => '' === (string) $r->get_param( 'ref' ) ? '' : (int) $r->get_param( 'ref' ),
				'tpl'  => (int) $r->get_param( 'tpl' ),
				'event' => '' === (string) $r->get_param( 'event' ) ? '' : (int) $r->get_param( 'event' ),
			)
		)
	);
}
