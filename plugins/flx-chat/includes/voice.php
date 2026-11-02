<?php
/**
 * 1.26.0 (Ahmet): sesli mesajı dinleyip cevap verme. Asistan cevaplamadan önce son 30 dk'daki dinlenmemiş
 * sesli mesajlar metne çevrilir (ses okuyan bir OpenRouter modeli, varsayılan ucuz Gemini). Ayardaki süreden
 * (varsayılan 60 sn) uzun sesler dinlenmez; asistan "ses çok uzun olduğu için dinleyemedik" der.
 * Kendi çeviricinizi flxc_audio_transcribe filtresiyle bağlayabilirsiniz (ör. Flux AI).
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

function flxc_voice_on() {
	return (int) flxc_setting( 'bot_voice' ) && flxc_ai_ready();
}

function flxc_voice_max() {
	return max( 10, min( 300, (int) flxc_setting( 'bot_voice_max' ) ) );
}

/** Ses okuyan model: ayardaki, yoksa listede ses okuyan bilinen ucuz bir model (yalnız OpenRouter; Claude ses okumaz). */
function flxc_voice_model() {
	$m = trim( (string) flxc_setting( 'ai_model_audio' ) );
	if ( '' !== $m ) {
		return $m;
	}
	$pref = apply_filters( 'flxc_audio_models', array( 'google/gemini-2.5-flash-lite', 'google/gemini-2.0-flash-001', 'google/gemini-2.5-flash', 'openai/gpt-4o-audio-preview' ) );
	$list = flxc_ai_models();
	if ( is_array( $list ) && $list ) {
		$ids = array();
		foreach ( $list as $x ) {
			if ( 'openrouter' === ( $x['provider'] ?? '' ) && ( ! isset( $x['audio'] ) || ! empty( $x['audio'] ) ) ) {
				$ids[ $x['id'] ] = 1;
			}
		}
		foreach ( $pref as $p ) {
			if ( isset( $ids[ $p ] ) ) {
				return $p;
			}
		}
	}
	return $pref[0];
}

/** "1:05" */
function flxc_voice_dur( $sec ) {
	$sec = max( 0, (int) $sec );
	return floor( $sec / 60 ) . ':' . str_pad( (string) ( $sec % 60 ), 2, '0', STR_PAD_LEFT );
}

/**
 * Sesi metne çevirir.
 *
 * @return string|WP_Error
 */
function flxc_voice_transcribe( $path, $mime, $chat_id = 0 ) {
	$pre = apply_filters( 'flxc_audio_transcribe', null, $path, $mime );
	if ( null !== $pre ) {
		return $pre;
	}
	$mime = strtolower( explode( ';', (string) $mime )[0] );
	$fmt  = array(
		'audio/ogg'  => 'ogg',
		'audio/opus' => 'ogg',
		'audio/mpeg' => 'mp3',
		'audio/mp3'  => 'mp3',
		'audio/mp4'  => 'm4a',
		'audio/m4a'  => 'm4a',
		'audio/aac'  => 'aac',
		'audio/wav'  => 'wav',
		'audio/x-wav' => 'wav',
		'audio/webm' => 'webm',
		'audio/flac' => 'flac',
	);
	$model = flxc_voice_model();
	if ( 'anthropic' === flxc_ai_model_provider( $model ) ) {
		return new WP_Error( 'flxc_voice', __( 'Claude modelleri ses okuyamaz; Ayarlar › Yapay zekâ › Sesli mesaj modelinden ses okuyan bir OpenRouter modeli seçin.', 'flx-chat' ) );
	}
	$msgs = array(
		array( 'role' => 'system', 'content' => 'Sana bir WhatsApp sesli mesajı veriliyor. Konuşulanı olduğu gibi, konuşulan dilde yazıya dök. Yorum, özet ya da açıklama ekleme. Konuşma yoksa ya da anlaşılmıyorsa yalnız [anlaşılmadı] yaz.' ),
		array(
			'role'    => 'user',
			'content' => array(
				array( 'type' => 'text', 'text' => 'Sesli mesaj:' ),
				array(
					'type'        => 'input_audio',
					'input_audio' => array(
						'data'   => base64_encode( (string) file_get_contents( $path ) ), // phpcs:ignore
						'format' => $fmt[ $mime ] ?? 'ogg',
					),
				),
			),
		),
	);
	$r = flxc_ai_chat(
		$msgs,
		array(
			'purpose'     => 'audio',
			'model'       => $model,
			'temperature' => 0,
			'max_tokens'  => 1200,
			'timeout'     => 25,
		)
	);
	flxc_bot_stat_add( array( 'voice' => 1 ), ! is_wp_error( $r ) && isset( $r['_usage'] ) ? array( $r['_usage'] ) : array(), (int) $chat_id );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$text = trim( flxc_ai_clean_text( (string) ( $r['content'] ?? '' ) ) );
	return '' === $text ? new WP_Error( 'flxc_voice', __( 'Ses metne çevrilemedi.', 'flx-chat' ) ) : $text;
}

/** Asistan cevaplamadan önce: son 30 dk'daki dinlenmemiş sesli mesajlar (en fazla 3). */
function flxc_bot_voice_turn( $chat ) {
	if ( ! flxc_voice_on() ) {
		return;
	}
	$max = flxc_voice_max();
	$n   = 0;
	foreach ( flxc_recent_messages( $chat['id'], 1, 12, 30 ) as $m ) {
		if ( 'in' !== $m['direction'] || 'audio' !== $m['type'] || (int) $m['ts'] < ( time() - 30 * MINUTE_IN_SECONDS ) * 1000 ) {
			continue;
		}
		$meta = flxc_decode( $m['meta'] );
		if ( isset( $meta['stt'] ) ) {
			continue;
		}
		$sec = (int) ( $meta['seconds'] ?? 0 );
		if ( $sec > $max ) {
			$meta['stt'] = array( 'skip' => 'long' );
		} elseif ( $n >= 3 ) {
			$meta['stt'] = array( 'skip' => 'many' );
		} else {
			$n++;
			$path = flxc_message_media_path( $m );
			if ( is_wp_error( $path ) || ! is_readable( $path ) || filesize( $path ) > 8 * MB_IN_BYTES ) {
				$meta['stt'] = array( 'error' => __( 'Ses dosyası okunamadı.', 'flx-chat' ) );
			} else {
				$m2  = flxc_get_message( $m['id'] );
				$t0  = microtime( true );
				$txt = flxc_voice_transcribe( $path, (string) ( $m2['media_mime'] ?? 'audio/ogg' ), (int) $chat['id'] );
				$meta['stt'] = is_wp_error( $txt )
					? array( 'error' => $txt->get_error_message() )
					: array( 'text' => mb_substr( $txt, 0, 4000 ), 'ms' => (int) ( ( microtime( true ) - $t0 ) * 1000 ) );
			}
		}
		flxc_update_message( $m['id'], array( 'meta' => $meta ) );
		$dur = flxc_voice_dur( $sec );
		if ( isset( $meta['stt']['text'] ) ) {
			/* translators: 1: duration, 2: transcript */
			flxc_system_message( $chat, sprintf( __( 'Sesli mesaj (%1$s) metne çevrildi: %2$s', 'flx-chat' ), $dur, $meta['stt']['text'] ) );
		} elseif ( isset( $meta['stt']['error'] ) ) {
			/* translators: 1: duration, 2: error */
			flxc_system_message( $chat, sprintf( __( 'Sesli mesaj (%1$s) dinlenemedi: %2$s', 'flx-chat' ), $dur, $meta['stt']['error'] ) );
		}
	}
}

/** Sesli mesajın modele giden hali. */
function flxc_voice_model_text( array $m, array $meta ) {
	$dur = flxc_voice_dur( (int) ( $meta['seconds'] ?? 0 ) );
	$stt = (array) ( $meta['stt'] ?? array() );
	if ( isset( $stt['text'] ) ) {
		return '[Sesli mesaj ' . $dur . ', metne çevrildi] ' . $stt['text'];
	}
	if ( 'long' === ( $stt['skip'] ?? '' ) ) {
		return '[Sesli mesaj ' . $dur . ': ' . flxc_voice_max() . ' saniyeden uzun olduğu için dinlenmedi]';
	}
	if ( isset( $stt['error'] ) || 'many' === ( $stt['skip'] ?? '' ) ) {
		return '[Sesli mesaj ' . $dur . ': dinlenemedi]';
	}
	return '[Sesli mesaj ' . $dur . ']';
}

function flxc_voice_rule() {
	if ( ! flxc_voice_on() ) {
		return '- Sesli mesajları dinleyemiyorsun: kişi ses gönderirse sesi dinleyemediğini kibarca söyle ve yazarak iletmesini rica et.';
	}
	return '- Sesli mesajlar metne çevrilmiş gelir ("[Sesli mesaj 0:23, metne çevrildi] …"): yazılmış mesaj gibi cevap ver, ayrıca "sesinizi dinledim" deme. "Dinlenmedi" yazıyorsa ses çok uzun olduğu için dinleyemediğini kibarca söyle; kısaca yazmasını ya da ' . flxc_voice_max() . ' saniyeden kısa bir ses göndermesini rica et. "Dinlenemedi" yazıyorsa sesi anlayamadığını söyleyip yazmasını rica et.';
}
