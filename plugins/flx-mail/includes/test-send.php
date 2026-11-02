<?php
/**
 * Bağlantı test senaryoları (metin, resim, ses, belge, şablon) ve WhatsApp medya gönderimi.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Eklentiyle gelen örnek dosyalar. */
function flxi_test_samples() {
	$base = FLXI_DIR . 'assets/test/';
	$url  = FLXI_URL . 'assets/test/';
	return array(
		'image'    => array( 'path' => $base . 'ornek.jpg', 'url' => $url . 'ornek.jpg', 'mime' => 'image/jpeg', 'name' => 'ornek.jpg' ),
		'audio'    => array( 'path' => $base . 'ornek.ogg', 'url' => $url . 'ornek.ogg', 'mime' => 'audio/ogg; codecs=opus', 'name' => 'ornek.ogg' ),
		'document' => array( 'path' => $base . 'ornek.pdf', 'url' => $url . 'ornek.pdf', 'mime' => 'application/pdf', 'name' => 'test-belgesi.pdf' ),
	);
}

/** Bağlantı tipinin desteklediği senaryolar. */
function flxi_test_scenarios( $type ) {
	if ( 'wacloud' === $type ) {
		return array( 'text', 'image', 'audio', 'document', 'template' );
	}
	if ( flxi_is_evo_type( $type ) ) {
		return array( 'text', 'image', 'audio', 'document' );
	}
	return array( 'text' );
}

/**
 * Tek senaryoyu gönderir.
 *
 * @param int    $id       Bağlantı.
 * @param string $scenario check|text|image|audio|document|template.
 * @param string $to       Alıcı.
 * @param array  $args     text, caption, template => [name, lang, params].
 */
function flxi_test_scenario( $id, $scenario, $to, $args = array() ) {
	if ( 'check' === $scenario || '' === $scenario ) {
		return flxi_test_connection( $id, '' );
	}
	$conn = flxi_get_connection( $id );
	if ( ! $conn ) {
		return array( 'ok' => false, 'error' => __( 'Bağlantı bulunamadı.', 'flx-mail' ) );
	}
	if ( ! in_array( $scenario, flxi_test_scenarios( $conn['type'] ), true ) ) {
		return array( 'ok' => false, 'error' => __( 'Bu bağlantı bu senaryoyu desteklemiyor.', 'flx-mail' ) );
	}
	if ( ! flxi_normalize_phone( $to ) ) {
		return array( 'ok' => false, 'error' => __( 'Geçerli bir alıcı numarası girin.', 'flx-mail' ) );
	}
	$type = flxi_connection_types()[ $conn['type'] ] ?? null;
	$text = trim( (string) ( $args['text'] ?? '' ) );
	try {
		if ( 'text' === $scenario ) {
			/* translators: %s: site adı */
			$text = $text ? $text : sprintf( __( 'Test mesajı · %s 🌱', 'flx-mail' ), get_bloginfo( 'name' ) );
			$r    = call_user_func( $type['send'], $conn['config'], array( 'recipient' => $to, 'body' => $text, 'media' => '', 'file_name' => '' ), $conn );
		} elseif ( 'template' === $scenario ) {
			$t = (array) ( $args['template'] ?? array() );
			if ( empty( $t['name'] ) ) {
				return array( 'ok' => false, 'error' => __( 'Şablon seçin.', 'flx-mail' ) );
			}
			$r = flxi_wacloud_send( $conn['config'], array( 'recipient' => $to, 'body' => '', 'media' => '', 'file_name' => '', 'template' => $t ), $conn );
		} else {
			$s = flxi_test_samples()[ $scenario ];
			$r = flxi_wa_send_media(
				$conn,
				$to,
				$scenario,
				array(
					'path'      => $s['path'],
					'mime'      => $s['mime'],
					'file_name' => $s['name'],
					'caption'   => 'audio' === $scenario ? '' : trim( (string) ( $args['caption'] ?? '' ) ),
				)
			);
		}
	} catch ( Throwable $e ) {
		$r = array( 'ok' => false, 'error' => $e->getMessage() );
	}
	$r = wp_parse_args( $r, array( 'ok' => false, 'error' => '' ) );
	if ( ! $r['ok'] && 'wacloud' === $conn['type'] && 131047 === (int) ( $r['code'] ?? 0 ) ) {
		$r['error'] .= ' ' . __( 'Denemek için önce o numaradan bu hatta bir mesaj atın ya da şablon gönderin.', 'flx-mail' );
	}
	$tests        = (array) get_option( 'flxi_conn_tests', array() );
	$tests[ $id ] = array( 'ok' => (bool) $r['ok'], 'error' => mb_substr( (string) $r['error'], 0, 300 ), 'at' => time() );
	update_option( 'flxi_conn_tests', $tests, false );
	unset( $r['response'] );
	return $r;
}

/**
 * WhatsApp'a resim / ses / belge gönderir (Evolution, Flux WPAPI, Cloud API).
 *
 * @param array|int $conn Bağlantı ya da id.
 * @param string    $to   Alıcı.
 * @param string    $kind image|audio|document.
 * @param array     $m    path (yerel dosya) ya da url; mime, file_name, caption.
 * @return array ok, error, external_id.
 */
function flxi_wa_send_media( $conn, $to, $kind, array $m ) {
	$conn = is_array( $conn ) ? $conn : flxi_get_connection( (int) $conn );
	if ( ! $conn || ! flxi_is_wa_type( $conn['type'] ) ) {
		return array( 'ok' => false, 'error' => __( 'WhatsApp bağlantısı bulunamadı.', 'flx-mail' ) );
	}
	if ( ! in_array( $kind, array( 'image', 'audio', 'document' ), true ) ) {
		return array( 'ok' => false, 'error' => 'kind' );
	}
	$phone = flxi_normalize_phone( $to );
	if ( ! $phone ) {
		/* translators: %s: telefon */
		return array( 'ok' => false, 'error' => sprintf( __( 'Geçersiz telefon: %s', 'flx-mail' ), $to ) );
	}
	$path = (string) ( $m['path'] ?? '' );
	if ( $path && ! is_readable( $path ) ) {
		return array( 'ok' => false, 'error' => __( 'Dosya okunamadı.', 'flx-mail' ) );
	}
	$name = (string) ( $m['file_name'] ?? '' );
	$name = $name ? $name : basename( $path ? $path : (string) wp_parse_url( (string) ( $m['url'] ?? '' ), PHP_URL_PATH ) );
	$mime = (string) ( $m['mime'] ?? '' );
	$cfg  = $conn['config'];

	if ( 'wacloud' === $conn['type'] ) {
		$obj = array();
		if ( $path ) {
			$mid = flxi_wacloud_upload( $cfg, $path, $mime );
			if ( is_wp_error( $mid ) ) {
				return array( 'ok' => false, 'error' => $mid->get_error_message() );
			}
			$obj['id'] = $mid;
		} else {
			$obj['link'] = (string) $m['url'];
		}
		if ( 'audio' !== $kind && ! empty( $m['caption'] ) ) {
			$obj['caption'] = (string) $m['caption'];
		}
		if ( 'document' === $kind ) {
			$obj['filename'] = $name;
		}
		return flxi_wacloud_post( $cfg, $phone, array( 'type' => $kind, $kind => $obj ) );
	}

	// Evolution / Flux WPAPI: yerel dosya base64 gider (sunucunun siteye erişmesi gerekmez).
	$media    = $path ? base64_encode( (string) file_get_contents( $path ) ) : (string) $m['url']; // phpcs:ignore
	$instance = rawurlencode( (string) $cfg['instance'] );
	$v1       = 'v1' === ( $cfg['version'] ?? 'v2' );
	if ( 'audio' === $kind ) {
		$body = $v1
			? array( 'number' => $phone, 'audioMessage' => array( 'audio' => $media ), 'options' => array( 'encoding' => true ) )
			: array( 'number' => $phone, 'audio' => $media, 'encoding' => true );
		$path = '/message/sendWhatsAppAudio/' . $instance;
	} else {
		$item = array(
			'mediatype' => $kind,
			'mimetype'  => $mime ? preg_replace( '/;.*$/', '', $mime ) : ( 'image' === $kind ? 'image/jpeg' : 'application/pdf' ),
			'caption'   => (string) ( $m['caption'] ?? '' ),
			'media'     => $media,
			'fileName'  => $name,
		);
		$body = $v1 ? array( 'number' => $phone, 'mediaMessage' => $item ) : array_merge( array( 'number' => $phone ), $item );
		$path = '/message/sendMedia/' . $instance;
	}
	$r = flxi_evolution_request( $cfg, $path, $body );
	if ( ! $r['ok'] ) {
		$detail = $r['error'];
		if ( ! $detail && is_array( $r['json'] ) ) {
			$msg    = $r['json']['response']['message'] ?? ( $r['json']['message'] ?? '' );
			$detail = is_array( $msg ) ? wp_json_encode( $msg, JSON_UNESCAPED_UNICODE ) : (string) $msg;
		}
		if ( false !== strpos( (string) $detail, '"exists":false' ) ) {
			$detail = __( 'Bu numarada WhatsApp yok.', 'flx-mail' );
		}
		return array( 'ok' => false, 'error' => 'HTTP ' . $r['code'] . ( $detail ? ': ' . mb_substr( (string) $detail, 0, 300 ) : '' ) );
	}
	return array( 'ok' => true, 'external_id' => (string) ( $r['json']['key']['id'] ?? '' ) );
}

/** Cloud API'ye dosya yükler; medya kimliği (aynı dosya için 20 gün önbellek). */
function flxi_wacloud_upload( array $cfg, $path, $mime ) {
	$mime = $mime ? $mime : ( wp_check_filetype( $path )['type'] ?: 'application/octet-stream' );
	$key  = 'flxi_wacm_' . md5( $cfg['phone_id'] . '|' . $path . '|' . filemtime( $path ) );
	$id   = get_transient( $key );
	if ( $id ) {
		return $id;
	}
	$b    = wp_generate_password( 24, false );
	$body = '';
	foreach ( array( 'messaging_product' => 'whatsapp', 'type' => $mime ) as $k => $v ) {
		$body .= "--$b\r\nContent-Disposition: form-data; name=\"$k\"\r\n\r\n$v\r\n";
	}
	$body .= "--$b\r\nContent-Disposition: form-data; name=\"file\"; filename=\"" . basename( $path ) . "\"\r\nContent-Type: $mime\r\n\r\n" . file_get_contents( $path ) . "\r\n--$b--\r\n"; // phpcs:ignore
	$ver   = preg_match( '/^v\d+\.\d+$/', (string) ( $cfg['graph'] ?? '' ) ) ? $cfg['graph'] : 'v23.0';
	$r     = flxi_http(
		'POST',
		apply_filters( 'flxi_wacloud_base', 'https://graph.facebook.com/' ) . $ver . '/' . rawurlencode( (string) $cfg['phone_id'] ) . '/media',
		array(
			'headers' => array( 'Authorization' => 'Bearer ' . (string) $cfg['token'], 'Content-Type' => 'multipart/form-data; boundary=' . $b ),
			'body'    => $body,
			'timeout' => 40,
		)
	);
	if ( ! $r['ok'] || empty( $r['json']['id'] ) ) {
		return new WP_Error( 'flxi_wac_upload', __( 'Dosya Meta\'ya yüklenemedi:', 'flx-mail' ) . ' ' . flxi_wacloud_error( $r ) );
	}
	set_transient( $key, (string) $r['json']['id'], 20 * DAY_IN_SECONDS );
	return (string) $r['json']['id'];
}
