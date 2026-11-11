<?php
/** Yardımcılar: şifreleme, HTTP, medya, günlük. */

defined( 'ABSPATH' ) || exit;

/* ---------------- Şifreleme (sodium; yoksa base64 ile sarılır) ---------------- */

function flxsm_crypt_key() {
	return substr( hash( 'sha256', 'flxsm|' . wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' ), true ), 0, 32 );
}

function flxsm_encrypt( $plain ) {
	return flx_secret_seal( (string) $plain, 'flx-social' );
}

function flxsm_decrypt( $enc ) {
	if ( ! function_exists( 'flx_secret_open' ) && function_exists( 'flx_brand_boot' ) ) {
		flx_brand_boot(); // Read before after_setup_theme (e.g. mail sent while plugins load): load the shared core now.
	}
	if ( ! function_exists( 'flx_secret_open' ) ) {
		return '';
	}
	$enc = (string) $enc;
	if ( 0 === strpos( $enc, 'b1:' ) ) {
		return (string) base64_decode( substr( $enc, 3 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}
	// Old boxes used a raw SHA-256 key, not generichash, so $legacy cannot open them.
	if ( 0 === strpos( $enc, 's1:' ) ) {
		try {
			$raw = base64_decode( substr( $enc, 3 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$n   = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
			if ( ! is_string( $raw ) || strlen( $raw ) <= $n ) {
				return '';
			}
			$out = sodium_crypto_secretbox_open( substr( $raw, $n ), substr( $raw, 0, $n ), flxsm_crypt_key() );
			return false === $out ? '' : $out;
		} catch ( Throwable $e ) {
			unset( $e );
			return '';
		}
	}
	if ( 0 === strpos( $enc, 'flx1:' ) ) {
		return flx_secret_open( $enc, 'flx-social' );
	}
	return '';
}

/** Uygulama gizli anahtarları (client secret vb.): ayrı seçenekte, şifreli. */
function flxsm_secret( $key ) {
	$all = (array) get_option( 'flxsm_secrets', array() );
	return isset( $all[ $key ] ) ? flxsm_decrypt( $all[ $key ] ) : '';
}

function flxsm_set_secret( $key, $value ) {
	$all = (array) get_option( 'flxsm_secrets', array() );
	if ( '' === (string) $value ) {
		unset( $all[ $key ] );
	} else {
		$all[ $key ] = flxsm_encrypt( $value );
	}
	update_option( 'flxsm_secrets', $all, false );
}

function flxsm_has_secret( $key ) {
	$all = (array) get_option( 'flxsm_secrets', array() );
	return ! empty( $all[ $key ] );
}

/** Uygulama alanı (herkese açık kısım, ör. client id). */
function flxsm_app( $provider, $field, $def = '' ) {
	$apps = (array) flxsm_setting( 'apps', array() );
	return isset( $apps[ $provider ][ $field ] ) && '' !== $apps[ $provider ][ $field ] ? $apps[ $provider ][ $field ] : $def;
}

/* ---------------- HTTP ---------------- */

/**
 * Tek tip HTTP çağrısı. Dönüş: ok, code, json, body, headers, error.
 * $args: headers, body (dizi → form; string → olduğu gibi), json (dizi → JSON gövde), query, timeout, bearer.
 */
function flxsm_http( $method, $url, array $args = array() ) {
	if ( ! empty( $args['query'] ) ) {
		$url = add_query_arg( array_map( 'rawurlencode', $args['query'] ), $url );
	}
	$headers = isset( $args['headers'] ) ? (array) $args['headers'] : array();
	if ( ! empty( $args['bearer'] ) ) {
		$headers['Authorization'] = 'Bearer ' . $args['bearer'];
	}
	$req = array(
		'method'      => strtoupper( $method ),
		'timeout'     => isset( $args['timeout'] ) ? (int) $args['timeout'] : 30,
		'headers'     => $headers,
		'redirection' => isset( $args['redirection'] ) ? (int) $args['redirection'] : 3,
		'user-agent'  => 'FluxWP-Sosyal/' . FLXSM_VERSION . '; ' . home_url( '/' ),
	);
	if ( isset( $args['json'] ) ) {
		$req['headers']['Content-Type'] = 'application/json';
		$req['body']                    = wp_json_encode( $args['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	} elseif ( isset( $args['body'] ) ) {
		$req['body'] = $args['body'];
	}
	$res = wp_remote_request( $url, $req );
	if ( is_wp_error( $res ) ) {
		return array( 'ok' => false, 'code' => 0, 'json' => null, 'body' => '', 'headers' => array(), 'error' => $res->get_error_message(), 'net' => true );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$raw  = (string) wp_remote_retrieve_body( $res );
	$json = json_decode( $raw, true );
	$hdr  = wp_remote_retrieve_headers( $res );
	$hdr  = is_object( $hdr ) && method_exists( $hdr, 'getAll' ) ? $hdr->getAll() : (array) $hdr;
	$ok   = $code >= 200 && $code < 300;
	return array(
		'ok'      => $ok,
		'code'    => $code,
		'json'    => is_array( $json ) ? $json : null,
		'body'    => $raw,
		'headers' => array_change_key_case( $hdr, CASE_LOWER ),
		'error'   => $ok ? '' : flxsm_http_error_text( $code, is_array( $json ) ? $json : null, $raw ),
	);
}

/** Sağlayıcı cevaplarından okunur hata metni çıkarır. */
function flxsm_http_error_text( $code, $json, $raw ) {
	$m = '';
	if ( is_array( $json ) ) {
		$cands = array(
			$json['error']['error_user_msg'] ?? null,
			$json['error']['message'] ?? null,
			$json['error_description'] ?? null,
			$json['detail'] ?? null,
			$json['message'] ?? null,
			$json['title'] ?? null,
			$json['errors'][0]['message'] ?? null,
			$json['error']['errors'][0]['message'] ?? null,
			is_string( $json['error'] ?? null ) ? $json['error'] : null,
		);
		foreach ( $cands as $c ) {
			if ( is_string( $c ) && '' !== trim( $c ) ) {
				$m = $c;
				break;
			}
		}
	}
	if ( '' === $m ) {
		$m = mb_substr( trim( wp_strip_all_tags( (string) $raw ) ), 0, 240 );
	}
	return 'HTTP ' . (int) $code . ( '' !== $m ? ': ' . mb_substr( $m, 0, 400 ) : '' );
}

/** Çok parçalı gövde: $fields düz alanlar, $files [ad => [path|data, mime, filename]]. */
function flxsm_multipart( array $fields, array $files = array() ) {
	$b    = '----flxsm' . wp_generate_password( 18, false );
	$body = '';
	foreach ( $fields as $k => $v ) {
		$body .= "--{$b}\r\nContent-Disposition: form-data; name=\"{$k}\"\r\n\r\n{$v}\r\n";
	}
	foreach ( $files as $k => $f ) {
		$data  = isset( $f['data'] ) ? $f['data'] : (string) file_get_contents( $f['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$fn    = isset( $f['filename'] ) ? $f['filename'] : 'file';
		$mime  = isset( $f['mime'] ) ? $f['mime'] : 'application/octet-stream';
		$body .= "--{$b}\r\nContent-Disposition: form-data; name=\"{$k}\"; filename=\"{$fn}\"\r\nContent-Type: {$mime}\r\n\r\n{$data}\r\n";
	}
	$body .= "--{$b}--\r\n";
	return array( 'body' => $body, 'type' => 'multipart/form-data; boundary=' . $b );
}

/** Dosyanın bir bölümünü okur (parçalı yükleme). */
function flxsm_read_chunk( $path, $offset, $len ) {
	$fh = @fopen( $path, 'rb' ); // phpcs:ignore
	if ( ! $fh ) {
		return false;
	}
	fseek( $fh, $offset );
	$data = fread( $fh, $len ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $data;
}

/* ---------------- Medya ---------------- */

/**
 * Ek (attachment) bilgisi: id, url, path, mime, kind (image|video|gif), bytes, width, height, thumb.
 * Sağlayıcıların çoğu dosyayı herkese açık adresten kendisi çeker; o yüzden url her zaman https olmalı.
 */
function flxsm_media_item( $id ) {
	$id = (int) $id;
	$p  = $id ? get_post( $id ) : null;
	if ( ! $p || 'attachment' !== $p->post_type ) {
		return null;
	}
	$mime = (string) get_post_mime_type( $id );
	$path = (string) get_attached_file( $id );
	$meta = wp_get_attachment_metadata( $id );
	$kind = 0 === strpos( $mime, 'video/' ) ? 'video' : ( 'image/gif' === $mime ? 'gif' : 'image' );
	$url  = (string) wp_get_attachment_url( $id );
	if ( 0 === strpos( $url, 'http://' ) && is_ssl() ) {
		$url = 'https://' . substr( $url, 7 );
	}
	$thumb = 'video' === $kind ? '' : (string) wp_get_attachment_image_url( $id, 'medium' );
	if ( 'video' === $kind ) {
		$poster = (int) get_post_meta( $id, '_flxsm_poster', true );
		$thumb  = $poster ? (string) wp_get_attachment_image_url( $poster, 'medium' ) : '';
	}
	return array(
		'id'       => $id,
		'url'      => $url,
		'path'     => $path,
		'mime'     => $mime,
		'kind'     => $kind,
		'bytes'    => $path && file_exists( $path ) ? (int) filesize( $path ) : 0,
		'width'    => (int) ( $meta['width'] ?? 0 ),
		'height'   => (int) ( $meta['height'] ?? 0 ),
		'duration' => (float) ( $meta['length'] ?? 0 ),
		'thumb'    => $thumb,
		'name'     => get_the_title( $id ),
		'alt'      => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
	);
}

function flxsm_media_list( $ids ) {
	$out = array();
	foreach ( (array) $ids as $id ) {
		$m = flxsm_media_item( $id );
		if ( $m ) {
			$out[] = $m;
		}
	}
	return $out;
}

/* ---------------- Metin ---------------- */

/** Site bağlantılarına UTM ekler (ayar açıksa). */
function flxsm_apply_utm( $text, $provider ) {
	if ( ! flxsm_setting( 'utm', 1 ) ) {
		return $text;
	}
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	return preg_replace_callback(
		'~https?://[^\s<>"\']+~u',
		function ( $m ) use ( $host, $provider ) {
			$u = rtrim( $m[0], '.,);!?' );
			$t = substr( $m[0], strlen( $u ) );
			if ( wp_parse_url( $u, PHP_URL_HOST ) !== $host || false !== strpos( $u, 'utm_source=' ) ) {
				return $m[0];
			}
			return add_query_arg(
				array(
					'utm_source'   => $provider,
					'utm_medium'   => 'social',
					'utm_campaign' => 'flx-social',
				),
				$u
			) . $t;
		},
		(string) $text
	);
}

/** Uzunluk: sağlayıcıların çoğu karakter sayar (emoji = 1 sayılır, yaklaşık). */
function flxsm_len( $s ) {
	return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $s, 'UTF-8' ) : strlen( (string) $s );
}

/* ---------------- Günlük ---------------- */

function flxsm_log( $msg, $level = 'info', $account_id = 0, $target_id = 0 ) {
	global $wpdb;
	$wpdb->insert( // phpcs:ignore WordPress.DB
		flxsm_table( 'log' ),
		array(
			'ts'         => time(),
			'level'      => $level,
			'account_id' => (int) $account_id,
			'target_id'  => (int) $target_id,
			'msg'        => mb_substr( (string) $msg, 0, 2000 ),
		)
	);
	if ( 0 === wp_rand( 0, 50 ) ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . flxsm_table( 'log' ) . ' WHERE ts < %d', time() - 45 * DAY_IN_SECONDS ) ); // phpcs:ignore WordPress.DB
	}
}

function flxsm_json( $v ) {
	return wp_json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

function flxsm_dec( $s ) {
	$d = json_decode( (string) $s, true );
	return is_array( $d ) ? $d : array();
}

/** Callback adresi (sağlayıcı geliştirici panelinde birebir yazılmalı). */
function flxsm_redirect_uri( $app ) {
	return rest_url( FLXSM_NS . '/oauth/callback/' . $app );
}

/** Arayüze giden medya: sunucu dosya yolu çıkarılır. */
function flxsm_media_public( $ids ) {
	return array_map(
		function ( $m ) {
			unset( $m['path'] );
			return $m;
		},
		flxsm_media_list( $ids )
	);
}
