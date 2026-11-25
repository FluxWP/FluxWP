<?php
/**
 * Flux AI: sesten metne (sohbetteki mikrofon düğmesi).
 *
 * Tarayıcının SpeechRecognition'ı her ortamda yok (WebView, Firefox, Tauri); bu yüzden ses kaydı sunucuya gelir ve
 * ayarlı anahtarla yazıya çevrilir: OpenAI (key_openai) → Gemini (key_gemini) → OpenRouter (key_openrouter).
 * REST: POST flx-ai/v1/transcribe (multipart: file, lang) → { text }.
 *
 * @package FluxAI
 */

defined( 'ABSPATH' ) || exit;

/** Ses dosyası kayıt sınırı (bayt). */
function flxai_stt_max_bytes() {
	return (int) apply_filters( 'flxai_stt_max_bytes', 12 * MB_IN_BYTES );
}

/** Kullanılabilir sağlayıcı: 'openai' | 'gemini' | 'openrouter' | ''. */
function flxai_stt_provider() {
	foreach ( array( 'openai' => 'key_openai', 'gemini' => 'key_gemini', 'openrouter' => 'key_openrouter' ) as $p => $k ) {
		if ( '' !== trim( (string) flxai_setting( $k ) ) ) {
			return $p;
		}
	}
	return '';
}

/**
 * Sesi yazıya çevirir.
 *
 * @param string $path Ses dosyası (wav, webm, mp4/m4a, ogg, mp3).
 * @param string $mime MIME türü.
 * @param string $lang BCP-47 (tr-TR) ya da boş.
 * @return string|WP_Error
 */
function flxai_transcribe( $path, $mime, $lang = '' ) {
	$prov = flxai_stt_provider();
	if ( '' === $prov ) {
		return new WP_Error( 'flxai_stt_key', __( 'Speech to text needs an OpenAI, Gemini or OpenRouter key (Flux AI & MCP › Settings).', 'flx-ai' ), array( 'status' => 400 ) );
	}
	$raw = file_get_contents( $path ); // phpcs:ignore
	if ( false === $raw || '' === $raw ) {
		return new WP_Error( 'flxai_stt_empty', __( 'No audio was received.', 'flx-ai' ), array( 'status' => 400 ) );
	}
	$mime  = strtolower( trim( explode( ';', (string) $mime )[0] ) );
	$short = strtolower( substr( (string) $lang, 0, 2 ) );
	$ext   = array(
		'audio/wav'   => 'wav',
		'audio/x-wav' => 'wav',
		'audio/webm'  => 'webm',
		'video/webm'  => 'webm',
		'audio/mp4'   => 'm4a',
		'video/mp4'   => 'm4a',
		'audio/ogg'   => 'ogg',
		'audio/mpeg'  => 'mp3',
	)[ $mime ] ?? '';
	if ( '' === $ext ) {
		return new WP_Error( 'flxai_stt_type', __( 'This audio format is not supported.', 'flx-ai' ), array( 'status' => 415 ) );
	}
	$out = 'openai' === $prov ? flxai_stt_openai( $raw, $ext, $mime, $short ) : ( 'gemini' === $prov ? flxai_stt_gemini( $raw, $mime, $short ) : flxai_stt_openrouter( $raw, $ext, $short ) );
	if ( is_wp_error( $out ) ) {
		return $out;
	}
	return trim( (string) $out );
}

function flxai_stt_openai( $raw, $ext, $mime, $lang ) {
	$key   = trim( (string) flxai_setting( 'key_openai' ) );
	$model = (string) apply_filters( 'flxai_stt_model', 'gpt-4o-mini-transcribe', 'openai' );
	$b     = wp_generate_password( 24, false );
	$body  = '';
	$field = function ( $n, $v ) use ( &$body, $b ) {
		$body .= "--$b\r\nContent-Disposition: form-data; name=\"$n\"\r\n\r\n$v\r\n";
	};
	$field( 'model', $model );
	$field( 'response_format', 'json' );
	if ( '' !== $lang ) {
		$field( 'language', $lang );
	}
	$body .= "--$b\r\nContent-Disposition: form-data; name=\"file\"; filename=\"audio.$ext\"\r\nContent-Type: $mime\r\n\r\n$raw\r\n--$b--\r\n";
	$r     = wp_remote_post(
		(string) apply_filters( 'flxai_openai_base', 'https://api.openai.com/v1' ) . '/audio/transcriptions',
		array(
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'multipart/form-data; boundary=' . $b,
			),
			'body'    => $body,
		)
	);
	return flxai_stt_result( $r, 'text' );
}

function flxai_stt_gemini( $raw, $mime, $lang ) {
	$key   = trim( (string) flxai_setting( 'key_gemini' ) );
	$model = (string) apply_filters( 'flxai_stt_model', 'gemini-2.5-flash', 'gemini' );
	$r     = wp_remote_post(
		'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent',
		array(
			'timeout' => 60,
			'headers' => array( 'x-goog-api-key' => $key, 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode(
				array(
					'contents' => array(
						array(
							'parts' => array(
								array( 'text' => flxai_stt_prompt( $lang ) ),
								array( 'inline_data' => array( 'mime_type' => $mime, 'data' => base64_encode( $raw ) ) ), // phpcs:ignore
							),
						),
					),
				)
			),
		)
	);
	if ( is_wp_error( $r ) ) {
		return flxai_stt_result( $r, '' );
	}
	$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	if ( (int) wp_remote_retrieve_response_code( $r ) >= 300 ) {
		return flxai_stt_result( $r, '' );
	}
	return (string) ( $j['candidates'][0]['content']['parts'][0]['text'] ?? '' );
}

function flxai_stt_openrouter( $raw, $ext, $lang ) {
	$key   = trim( (string) flxai_setting( 'key_openrouter' ) );
	$model = (string) apply_filters( 'flxai_stt_model', 'google/gemini-2.5-flash', 'openrouter' );
	// OpenRouter yalnız wav/mp3/ogg/m4a... kabul eder; webm gelirse tarayıcı zaten wav'a çevirmiş olmalı.
	$fmt = 'm4a' === $ext ? 'm4a' : $ext;
	$r   = wp_remote_post(
		flxai_openrouter_base() . '/chat/completions',
		array(
			'timeout' => 60,
			'headers' => array( 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json', 'HTTP-Referer' => home_url( '/' ), 'X-Title' => get_bloginfo( 'name' ) . ' · Flux AI' ),
			'body'    => wp_json_encode(
				array(
					'model'    => $model,
					'messages' => array(
						array(
							'role'    => 'user',
							'content' => array(
								array( 'type' => 'text', 'text' => flxai_stt_prompt( $lang ) ),
								array( 'type' => 'input_audio', 'input_audio' => array( 'data' => base64_encode( $raw ), 'format' => $fmt ) ), // phpcs:ignore
							),
						),
					),
				)
			),
		)
	);
	if ( is_wp_error( $r ) || (int) wp_remote_retrieve_response_code( $r ) >= 300 ) {
		return flxai_stt_result( $r, '' );
	}
	$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	return (string) ( $j['choices'][0]['message']['content'] ?? '' );
}

function flxai_stt_prompt( $lang ) {
	return 'Transcribe this audio verbatim' . ( '' !== $lang ? ' (language: ' . $lang . ')' : '' ) . '. Output only the spoken text, with natural punctuation, no commentary. If there is no speech, output nothing.';
}

/** HTTP cevabından metin ya da hata. $field: JSON alanı ('' = yalnız hata denetimi). */
function flxai_stt_result( $r, $field ) {
	if ( is_wp_error( $r ) ) {
		/* translators: %s: error */
		return new WP_Error( 'flxai_http', sprintf( __( 'Could not reach the AI service: %s', 'flx-ai' ), $r->get_error_message() ), array( 'status' => 502 ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	$j    = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	if ( $code >= 300 ) {
		$m = is_array( $j ) ? (string) ( $j['error']['message'] ?? ( is_string( $j['error'] ?? null ) ? $j['error'] : '' ) ) : '';
		/* translators: 1: HTTP code, 2: message */
		return new WP_Error( 'flxai_stt', sprintf( __( 'Speech to text failed (%1$d): %2$s', 'flx-ai' ), $code, '' !== $m ? $m : 'error' ), array( 'status' => 502 ) );
	}
	return is_array( $j ) ? (string) ( $j[ $field ] ?? '' ) : '';
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flx-ai/v1',
			'/transcribe',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return is_user_logged_in() && flxai_can_chat();
				},
				'callback'            => function ( WP_REST_Request $req ) {
					$f = $req->get_file_params();
					if ( empty( $f['file']['tmp_name'] ) || ! empty( $f['file']['error'] ) ) {
						return new WP_Error( 'flxai_stt_empty', __( 'No audio was received.', 'flx-ai' ), array( 'status' => 400 ) );
					}
					if ( (int) $f['file']['size'] > flxai_stt_max_bytes() ) {
						return new WP_Error( 'flxai_stt_big', __( 'The recording is too long.', 'flx-ai' ), array( 'status' => 413 ) );
					}
					$t = flxai_transcribe( (string) $f['file']['tmp_name'], (string) ( $f['file']['type'] ?? '' ), (string) $req->get_param( 'lang' ) );
					return is_wp_error( $t ) ? $t : array( 'text' => $t );
				},
			)
		);
	}
);
