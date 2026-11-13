<?php
/**
 * Görsel ve video üretimi: sağlayıcılar (fal.ai, EvoLink, OpenAI, Google Gemini, OpenRouter), iş kuyruğu, çıktı saklama.
 * Diğer eklentiler flxai_media_providers / flxai_media_submit / flxai_media_status / flxai_media_cancel kullanır (API.md).
 *
 * Kurallar: sağlayıcı istekten ÖNCE seçilir; ücretli olabilecek bir istek başka sağlayıcıya tekrar gönderilmez.
 * Cron yok: kuyruktaki işler flxai_media_status çağrıldıkça (tarayıcı polling) ilerler.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------------------
 * Sağlayıcılar ve modeller
 * ------------------------------------------------------------------------- */

/** Sağlayıcı adları. */
function flxai_media_provider_labels() {
	return array(
		'fal'        => 'fal.ai',
		'evolink'    => 'EvoLink',
		'openai'     => 'OpenAI',
		'gemini'     => 'Google Gemini',
		'openrouter' => 'OpenRouter',
	);
}

/** Sağlayıcı anahtarı (OpenRouter sohbetle ortak). */
function flxai_media_key( $provider ) {
	$map = array(
		'fal'        => 'key_fal',
		'evolink'    => 'key_evolink',
		'openai'     => 'key_openai',
		'gemini'     => 'key_gemini',
		'openrouter' => 'key_openrouter',
	);
	return isset( $map[ $provider ] ) ? (string) flxai_setting( $map[ $provider ], '' ) : '';
}

/** API kök adresi (testte flxai_media_base ile değiştirilir). */
function flxai_media_base( $provider ) {
	if ( 'openrouter' === $provider ) {
		return flxai_openrouter_base();
	}
	$d = array(
		'fal'     => 'https://queue.fal.run',
		'evolink' => 'https://api.evolink.ai',
		'openai'  => 'https://api.openai.com',
		'gemini'  => 'https://generativelanguage.googleapis.com',
	);
	return untrailingslashit( (string) apply_filters( 'flxai_media_base', $d[ $provider ] ?? '', $provider ) );
}

/**
 * Model kataloğu (2026-10 sağlayıcı sözleşmeleri). routes: Otomatik seçim sırası.
 * Fiyatlar tahmindir (Kayıtlar'da "~"); sağlayıcı gerçek tutar bildirirse o yazılır.
 */
function flxai_media_catalog() {
	$img_aspects = array( '1:1', '4:3', '3:4', '16:9', '9:16', '3:2', '2:3', '21:9' );
	$vid_aspects = array( 'adaptive', '16:9', '9:16', '1:1', '4:3', '3:4', '21:9' );
	$models      = array(
		'gpt-image-2.5-sunburst'     => array(
			'label'       => 'GPT Image 2.5',
			'kind'        => 'image',
			// OpenAI görsel çağrısı 60 sn'yi aşabilir; asenkron fal kuyruğu önce.
			'routes'      => array( 'fal', 'openai', 'openrouter' ),
			'aspects'     => array( '1:1', '4:3', '3:4', '16:9', '9:16' ),
			'resolutions' => array( '1K', '2K', '4K' ),
			'qualities'   => array( 'auto', 'low', 'medium', 'high' ),
			'max_refs'    => 16,
			'max_n'       => 4,
			'mask'        => true,
			'price'       => array( '1K' => 0.18, '2K' => 0.30, '4K' => 0.55 ), // görsel başına, yüksek kalite
		),
		'gemini-nano-banana-2.1'     => array(
			'label'       => 'Nano Banana 2.1',
			'kind'        => 'image',
			'routes'      => array( 'fal', 'gemini', 'openrouter' ),
			'aspects'     => $img_aspects,
			'resolutions' => array( '1K', '2K', '4K' ),
			'max_refs'    => 14,
			'max_n'       => 4,
			'mask'        => true,
			'price'       => array( '1K' => 0.04, '2K' => 0.06, '4K' => 0.134 ),
		),
		'gemini-3-pro-image-preview' => array(
			'label'       => 'Nano Banana Pro',
			'kind'        => 'image',
			'routes'      => array( 'fal', 'gemini', 'openrouter' ),
			'aspects'     => $img_aspects,
			'resolutions' => array( '1K', '2K', '4K' ),
			'max_refs'    => 14,
			'max_n'       => 4,
			'mask'        => true,
			'price'       => array( '1K' => 0.15, '2K' => 0.15, '4K' => 0.30 ),
		),
		'seedance-2.5'               => array(
			'label'       => 'Seedance 2.5',
			'kind'        => 'video',
			'routes'      => array( 'evolink', 'fal', 'openrouter' ),
			'aspects'     => $vid_aspects,
			'resolutions' => array( '480p', '720p', '1080p' ),
			'duration'    => array( 4, 30, 5 ),
			'max_refs'    => 30,
			'max_n'       => 1,
			'price'       => array( '480p' => 0.138, '720p' => 0.296, '1080p' => 0.532 ), // saniye başına
		),
		'seedance-2.0'               => array(
			'label'       => 'Seedance 2.0',
			'kind'        => 'video',
			'routes'      => array( 'evolink', 'fal', 'openrouter' ),
			'aspects'     => $vid_aspects,
			'resolutions' => array( '480p', '720p', '1080p' ),
			'duration'    => array( 4, 15, 5 ),
			'max_refs'    => 9,
			'max_n'       => 1,
			'price'       => array( '480p' => 0.092, '720p' => 0.199, '1080p' => 0.496 ),
		),
		'minimax-h3'                 => array(
			'label'       => 'MiniMax H3 Max',
			'kind'        => 'video',
			'routes'      => array( 'fal' ),
			'aspects'     => array( 'adaptive', '21:9', '16:9', '4:3', '1:1', '3:4', '9:16' ),
			'resolutions' => array( '480p', '768p', '1080p' ),
			'duration'    => array( 2, 15, 5 ),
			'max_refs'    => 9,
			'max_n'       => 1,
			'price'       => array( '480p' => 0.05, '768p' => 0.08, '1080p' => 0.16 ),
		),
	);
	return (array) apply_filters( 'flxai_media_models', $models );
}

/**
 * Yapılandırılmış sağlayıcılar ve modeller (arayüz için; anahtar içermez).
 *
 * @return array providers{id: {label, ready}}, models{id: {label, kind, providers[], auto, aspects, resolutions, qualities, duration, max_refs, max_n, mask, price, price_unit}}, default_image, default_video
 */
function flxai_media_providers() {
	$prov = array();
	foreach ( flxai_media_provider_labels() as $id => $label ) {
		$prov[ $id ] = array(
			'label' => $label,
			'ready' => '' !== flxai_media_key( $id ),
		);
	}
	$models = array();
	foreach ( flxai_media_catalog() as $id => $m ) {
		$ready           = array_values( array_filter( (array) $m['routes'], fn( $p ) => ! empty( $prov[ $p ]['ready'] ) ) );
		$models[ $id ]   = array(
			'label'       => $m['label'],
			'kind'        => $m['kind'],
			'routes'      => array_values( (array) $m['routes'] ),
			'providers'   => $ready,
			'auto'        => $ready[0] ?? '',
			'aspects'     => $m['aspects'],
			'resolutions' => $m['resolutions'],
			'qualities'   => $m['qualities'] ?? array(),
			'duration'    => $m['duration'] ?? null,
			'max_refs'    => (int) $m['max_refs'],
			'max_n'       => (int) $m['max_n'],
			'mask'        => ! empty( $m['mask'] ),
			'price'       => $m['price'],
			'price_unit'  => 'video' === $m['kind'] ? 'second' : 'image',
		);
	}
	return array(
		'providers'     => $prov,
		'models'        => $models,
		'default_image' => (string) flxai_setting( 'media_image_model', 'gemini-nano-banana-2.1' ),
		'default_video' => (string) flxai_setting( 'media_video_model', 'seedance-2.5' ),
	);
}

/** Sağlayıcı seçimi (istekten önce). */
function flxai_media_route( $model, $choice = 'auto' ) {
	$cat = flxai_media_catalog();
	if ( ! isset( $cat[ $model ] ) ) {
		return new WP_Error( 'flxai_media_model', __( 'Unsupported model.', 'flx-ai' ) );
	}
	$routes = (array) $cat[ $model ]['routes'];
	$labels = flxai_media_provider_labels();
	if ( '' !== (string) $choice && 'auto' !== $choice ) {
		if ( ! in_array( $choice, $routes, true ) ) {
			return new WP_Error( 'flxai_media_provider', __( 'This provider does not offer the selected model.', 'flx-ai' ) );
		}
		if ( '' === flxai_media_key( $choice ) ) {
			/* translators: %s: provider */
			return new WP_Error( 'flxai_key', sprintf( __( '%s key is not set (Flux AI & MCP › Settings › Image and video).', 'flx-ai' ), $labels[ $choice ] ?? $choice ) );
		}
		return $choice;
	}
	foreach ( $routes as $p ) {
		if ( '' !== flxai_media_key( $p ) ) {
			return $p;
		}
	}
	/* translators: %s: providers */
	return new WP_Error( 'flxai_key', sprintf( __( 'This model needs one of these keys: %s (Flux AI & MCP › Settings › Image and video).', 'flx-ai' ), implode( ', ', array_map( fn( $p ) => $labels[ $p ] ?? $p, $routes ) ) ) );
}

/** Tahmini maliyet (USD). */
function flxai_media_estimate( $model, array $req ) {
	$cat = flxai_media_catalog();
	$m   = $cat[ $model ] ?? null;
	if ( ! $m ) {
		return 0.0;
	}
	$res  = (string) ( $req['resolution'] ?? '' );
	$unit = (float) ( $m['price'][ $res ] ?? reset( $m['price'] ) );
	if ( 'video' === $m['kind'] ) {
		return round( $unit * max( 1, (int) ( $req['duration'] ?? 5 ) ), 4 );
	}
	if ( 'gpt-image-2.5-sunburst' === $model ) {
		$q     = array(
			'low'    => 0.25,
			'medium' => 0.5,
		);
		$unit *= $q[ $req['quality'] ?? '' ] ?? 1;
	}
	return round( $unit * max( 1, (int) ( $req['n'] ?? 1 ) ), 4 );
}

/* ---------------------------------------------------------------------------
 * İş kaydı
 * ------------------------------------------------------------------------- */

function flxai_media_job_get( $job ) {
	global $wpdb;
	$job = (string) $job;
	if ( ! preg_match( '/^flxm_[a-f0-9]{24}$/', $job ) ) {
		return null;
	}
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxai_table( 'media' ) . ' WHERE job = %s', $job ), ARRAY_A ); // phpcs:ignore
	return $row ? $row : null;
}

function flxai_media_job_update( $id, array $data ) {
	global $wpdb;
	$data['updated'] = time();
	foreach ( array( 'req', 'outputs', 'meta' ) as $k ) {
		if ( isset( $data[ $k ] ) && is_array( $data[ $k ] ) ) {
			$data[ $k ] = wp_json_encode( $data[ $k ] );
		}
	}
	$wpdb->update( flxai_table( 'media' ), $data, array( 'id' => (int) $id ) ); // phpcs:ignore
}

/** Dışarı verilen iş görünümü (sağlayıcı anahtarı/adresi içermez). */
function flxai_media_public( array $row ) {
	$outputs = json_decode( (string) $row['outputs'], true );
	return array(
		'job'      => $row['job'],
		'state'    => $row['state'],
		'progress' => (float) $row['progress'],
		'kind'     => $row['kind'],
		'model'    => $row['model'],
		'provider' => $row['provider'],
		'outputs'  => is_array( $outputs ) ? $outputs : array(),
		'cost'     => (float) $row['cost'],
		'est'      => (bool) $row['est'],
		'error'    => (string) $row['error'],
		'source'   => $row['source'],
		'user_id'  => (int) $row['user_id'],
		'created'  => (int) $row['ts'],
		'updated'  => (int) $row['updated'],
	);
}

/** Kayıtlar satırını günceller (tahmini tutar → gerçek tutar, hata). */
function flxai_media_log_update( $log_id, array $data ) {
	global $wpdb;
	if ( ! $log_id ) {
		return;
	}
	$wpdb->update( flxai_table( 'log' ), $data, array( 'id' => (int) $log_id ) ); // phpcs:ignore
	if ( isset( $data['cost'] ) ) {
		flxai_spent( true );
		flxai_budget_after_log();
	}
}

/* ---------------------------------------------------------------------------
 * Gönderim
 * ------------------------------------------------------------------------- */

/**
 * Görsel/video işi gönderir. ~45 sn içinde döner: eşzamanlı sağlayıcıda iş biter (done), kuyrukta queued.
 *
 * @param array $req {
 *     source      string  Çağıran eklenti (flx-studio …). Zorunlu.
 *     purpose     string  Kısa etiket (generate, edit, variation …).
 *     model       string  Katalog kimliği (flxai_media_providers()['models']). Boşsa ayardaki varsayılan (kind'a göre).
 *     kind        string  image|video (model boşsa varsayılanı seçmek için).
 *     provider    string  auto|fal|evolink|openai|gemini|openrouter.
 *     prompt      string  İstem (en çok 20.000 karakter).
 *     refs        array   Referans görseller: ek (attachment) kimliği, https URL ya da data:image URL.
 *     video_refs  array   Video referansları (https URL / ek kimliği), yalnız video modelleri.
 *     audio_refs  array   Ses referansları (https URL / ek kimliği), yalnız video modelleri.
 *     mask        mixed   Alan düzenleme maskesi (PNG; saydam = düzenlenecek alan). refs[0] düzenlenen görseldir.
 *     aspect      string  En-boy oranı (modelin aspects listesinden).
 *     resolution  string  1K|2K|4K (görsel), 480p|720p|1080p (video).
 *     size        string  GPT Image için doğrudan "1536x1024" (aspect/resolution yerine).
 *     quality     string  GPT Image: auto|low|medium|high.
 *     n           int     Görsel adedi (1–max_n).
 *     duration    int     Video saniyesi.
 *     audio       bool    Video sesi üret (Seedance, varsayılan açık).
 *     mode        string  Video: auto|text|frames|reference.
 *     user_id     int     Varsayılan geçerli kullanıcı.
 *     ignore_limit bool   Harcama sınırını yok say (yalnız yönetici testleri).
 * }
 * @return array|WP_Error flxai_media_status() biçimi.
 */
function flxai_media_submit( array $req ) {
	global $wpdb;
	$source = sanitize_key( (string) ( $req['source'] ?? '' ) );
	$source = '' !== $source ? $source : 'unknown';
	if ( ! flxai_source_enabled( $source ) ) {
		/* translators: %s: plugin name */
		return new WP_Error( 'flxai_source_off', sprintf( __( 'AI is off for %s (Flux AI & MCP › Connected systems).', 'flx-ai' ), flxai_source_label( $source ) ) );
	}
	$req = flxai_media_normalize( $req );
	if ( is_wp_error( $req ) ) {
		return $req;
	}
	// Maskeli düzenlemede maske ve özgün görsel, kuyruk bitince birleştirme için dosyaya yazılır (data URL kayda geçmez).
	if ( 'image' === $req['kind'] && null !== $req['mask'] ) {
		foreach ( array( 'mask', 0 ) as $k ) {
			$v = 'mask' === $k ? $req['mask'] : $req['refs'][0];
			if ( is_string( $v ) && 0 === strpos( $v, 'data:' ) ) {
				$b = flxai_media_ref_bytes( $v );
				$f = is_wp_error( $b ) ? $b : flxai_media_write( $b[0], $b[1] );
				if ( is_wp_error( $f ) ) {
					return $f;
				}
				if ( 'mask' === $k ) {
					$req['mask'] = $f['url'];
				} else {
					$req['refs'][0] = $f['url'];
				}
			}
		}
	}
	$model    = $req['model'];
	$provider = flxai_media_route( $model, $req['provider'] );
	if ( is_wp_error( $provider ) ) {
		return $provider;
	}
	$est     = flxai_media_estimate( $model, $req );
	$purpose = sanitize_key( (string) ( $req['purpose'] ?? '' ) );
	$purpose = '' !== $purpose ? $purpose : $req['kind'];
	$user_id = (int) ( $req['user_id'] ?? get_current_user_id() );
	$ok      = flxai_budget_check( $req );
	if ( is_wp_error( $ok ) ) {
		flxai_log(
			array(
				'source'   => $source,
				'purpose'  => $purpose,
				'user_id'  => $user_id,
				'provider' => $provider,
				'model'    => $provider . ':' . $model,
				'ok'       => 0,
				'error'    => 'limit',
			)
		);
		return $ok;
	}
	// Kayıt istekten önce: tahmini tutar sınıra hemen işler (aynı anda gelen istekler sınırı aşamaz).
	$log_id = flxai_log(
		array(
			'source'   => $source,
			'purpose'  => $purpose,
			'user_id'  => $user_id,
			'provider' => $provider,
			'model'    => $provider . ':' . $model,
			'cost'     => $est,
			'full_cost' => $est,
			'est'      => 1,
		)
	);
	$job  = 'flxm_' . bin2hex( random_bytes( 12 ) );
	$now  = time();
	$keep = $req;
	unset( $keep['ignore_limit'] );
	foreach ( array( 'refs', 'video_refs', 'audio_refs' ) as $k ) {
		$keep[ $k ] = array_map( fn( $r ) => is_string( $r ) && 0 === strpos( $r, 'data:' ) ? 'data:…' : $r, $keep[ $k ] );
	}
	if ( is_string( $keep['mask'] ) && 0 === strpos( $keep['mask'], 'data:' ) ) {
		$keep['mask'] = 'data:…';
	}
	$wpdb->insert( // phpcs:ignore
		flxai_table( 'media' ),
		array(
			'job'      => $job,
			'ts'       => $now,
			'updated'  => $now,
			'user_id'  => $user_id,
			'source'   => $source,
			'purpose'  => $purpose,
			'kind'     => $req['kind'],
			'model'    => $model,
			'provider' => $provider,
			'state'    => 'queued',
			'progress' => 0,
			'req'      => wp_json_encode( $keep ),
			'outputs'  => '[]',
			'meta'     => '{}',
			'cost'     => $est,
			'est'      => 1,
			'log_id'   => $log_id,
			'error'    => '',
		)
	);
	$id = (int) $wpdb->insert_id;
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 70 ); // phpcs:ignore
	}
	$t0  = microtime( true );
	$res = flxai_media_send( $provider, $model, $req );
	$ms  = (int) round( ( microtime( true ) - $t0 ) * 1000 );
	if ( is_wp_error( $res ) ) {
		$charged = ! empty( $res->get_error_data()['maybe_charged'] );
		$msg     = $res->get_error_message();
		flxai_media_job_update(
			$id,
			array(
				'state' => 'failed',
				'error' => $msg,
				'cost'  => $charged ? $est : 0,
			)
		);
		flxai_media_log_update(
			$log_id,
			array(
				'ok'    => 0,
				'error' => mb_substr( $msg, 0, 250 ),
				'cost'  => $charged ? $est : 0,
				'ms'    => $ms,
			)
		);
		do_action( 'flxai_media_failed', $job, $res, $req );
		return $res;
	}
	$upd = array(
		'meta' => (array) ( $res['meta'] ?? array() ),
	);
	if ( ! empty( $res['outputs'] ) ) {
		$row  = flxai_media_job_get( $job );
		$outs = flxai_media_finish_outputs( $row, $res['outputs'], $req );
		// Gemini 60 sn sınırında istenenden az görsel döndürebilir: tahmin gelen adede göre.
		$part = 'image' === $req['kind'] && count( $res['outputs'] ) < $req['n'] ? count( $res['outputs'] ) / $req['n'] : 1;
		$cost = isset( $res['cost'] ) && null !== $res['cost'] ? (float) $res['cost'] : round( $est * $part, 4 );
		$upd += array(
			'state'    => 'done',
			'progress' => 1,
			'outputs'  => $outs,
			'cost'     => $cost,
			'est'      => isset( $res['cost'] ) && null !== $res['cost'] ? 0 : 1,
		);
		flxai_media_log_update(
			$log_id,
			array(
				'cost'      => $cost,
				'full_cost' => $cost,
				'est'       => $upd['est'],
				'ms'        => $ms,
			)
		);
	} else {
		$upd['state'] = 'queued';
	}
	flxai_media_job_update( $id, $upd );
	$row = flxai_media_job_get( $job );
	if ( 'done' === $row['state'] ) {
		do_action( 'flxai_media_done', flxai_media_public( $row ) );
	}
	return flxai_media_public( $row );
}

/** İsteği doğrular ve varsayılanları doldurur. */
function flxai_media_normalize( array $req ) {
	$cat   = flxai_media_catalog();
	$kind  = 'video' === ( $req['kind'] ?? '' ) ? 'video' : 'image';
	$model = (string) ( $req['model'] ?? '' );
	if ( '' === $model ) {
		$model = (string) flxai_setting( 'video' === $kind ? 'media_video_model' : 'media_image_model', 'video' === $kind ? 'seedance-2.5' : 'gemini-nano-banana-2.1' );
	}
	if ( ! isset( $cat[ $model ] ) ) {
		return new WP_Error( 'flxai_media_model', __( 'Unsupported model.', 'flx-ai' ) );
	}
	$m      = $cat[ $model ];
	$prompt = trim( (string) ( $req['prompt'] ?? '' ) );
	if ( '' === $prompt || mb_strlen( $prompt ) > 20000 ) {
		return new WP_Error( 'flxai_media_prompt', __( 'A prompt is required (up to 20,000 characters).', 'flx-ai' ) );
	}
	$list = function ( $v ) {
		return array_values( array_filter( (array) $v, fn( $x ) => ( is_int( $x ) || ctype_digit( (string) $x ) ) ? (int) $x > 0 : ( is_string( $x ) && '' !== $x ) ) );
	};
	$out = array(
		'source'       => (string) ( $req['source'] ?? '' ),
		'purpose'      => (string) ( $req['purpose'] ?? '' ),
		'kind'         => $m['kind'],
		'model'        => $model,
		'provider'     => sanitize_key( (string) ( $req['provider'] ?? 'auto' ) ),
		'prompt'       => $prompt,
		'refs'         => $list( $req['refs'] ?? array() ),
		'video_refs'   => 'video' === $m['kind'] ? $list( $req['video_refs'] ?? array() ) : array(),
		'audio_refs'   => 'video' === $m['kind'] ? $list( $req['audio_refs'] ?? array() ) : array(),
		'mask'         => $req['mask'] ?? null,
		'aspect'       => (string) ( $req['aspect'] ?? '' ),
		'resolution'   => (string) ( $req['resolution'] ?? '' ),
		'size'         => (string) ( $req['size'] ?? '' ),
		'quality'      => (string) ( $req['quality'] ?? 'auto' ),
		'n'            => max( 1, min( (int) $m['max_n'], (int) ( $req['n'] ?? 1 ) ) ),
		'duration'     => (int) ( $req['duration'] ?? ( $m['duration'][2] ?? 5 ) ),
		'audio'        => ! isset( $req['audio'] ) || ! empty( $req['audio'] ),
		'mode'         => (string) ( $req['mode'] ?? 'auto' ),
		'user_id'      => (int) ( $req['user_id'] ?? get_current_user_id() ),
		'ignore_limit' => ! empty( $req['ignore_limit'] ),
	);
	if ( '' === $out['provider'] ) {
		$out['provider'] = 'auto';
	}
	if ( count( $out['refs'] ) > (int) $m['max_refs'] ) {
		/* translators: %d: max */
		return new WP_Error( 'flxai_media_refs', sprintf( __( 'This model accepts at most %d reference images.', 'flx-ai' ), (int) $m['max_refs'] ) );
	}
	if ( '' === $out['aspect'] ) {
		$out['aspect'] = 'video' === $m['kind'] ? '16:9' : '1:1';
	}
	if ( ! in_array( $out['aspect'], (array) $m['aspects'], true ) && ! ( 'image' === $m['kind'] && 'auto' === $out['aspect'] ) ) {
		return new WP_Error( 'flxai_media_aspect', __( 'This model does not support this aspect ratio.', 'flx-ai' ) );
	}
	$out['resolution'] = '' !== $out['resolution'] ? $out['resolution'] : ( 'video' === $m['kind'] ? '720p' : '1K' );
	$res_ok            = array_map( 'strtolower', (array) $m['resolutions'] );
	if ( ! in_array( strtolower( $out['resolution'] ), $res_ok, true ) ) {
		$out['resolution'] = 'video' === $m['kind'] ? ( in_array( '720p', $res_ok, true ) ? '720p' : '768p' ) : '1K';
	}
	foreach ( (array) $m['resolutions'] as $r ) {
		if ( strtolower( $r ) === strtolower( $out['resolution'] ) ) {
			$out['resolution'] = $r;
		}
	}
	if ( 'video' === $m['kind'] ) {
		$d = (array) $m['duration'];
		if ( $out['duration'] < $d[0] || $out['duration'] > $d[1] ) {
			/* translators: 1: min, 2: max */
			return new WP_Error( 'flxai_media_duration', sprintf( __( 'Duration must be %1$d–%2$d seconds.', 'flx-ai' ), $d[0], $d[1] ) );
		}
		if ( ! in_array( $out['mode'], array( 'auto', 'text', 'frames', 'reference' ), true ) ) {
			$out['mode'] = 'auto';
		}
		if ( 'auto' === $out['mode'] ) {
			$out['mode'] = $out['video_refs'] || $out['audio_refs'] || count( $out['refs'] ) > 2 ? 'reference' : ( $out['refs'] ? 'frames' : 'text' );
		}
		if ( 'frames' === $out['mode'] && ( ! $out['refs'] || count( $out['refs'] ) > 2 ) ) {
			return new WP_Error( 'flxai_media_refs', __( 'Pick 1 or 2 images for the first/last frame.', 'flx-ai' ) );
		}
		if ( 'reference' === $out['mode'] && ! $out['refs'] && ! $out['video_refs'] ) {
			return new WP_Error( 'flxai_media_refs', __( 'Reference mode needs an image or a video.', 'flx-ai' ) );
		}
		$out['mask'] = null;
	} else {
		if ( ! in_array( $out['quality'], (array) ( $m['qualities'] ?? array( 'auto' ) ), true ) ) {
			$out['quality'] = 'auto';
		}
		if ( '' !== $out['size'] && ! preg_match( '/^(\d{2,4})x(\d{2,4})$/', $out['size'] ) ) {
			$out['size'] = '';
		}
		if ( null !== $out['mask'] && '' !== $out['mask'] ) {
			if ( empty( $m['mask'] ) ) {
				return new WP_Error( 'flxai_media_mask', __( 'This model does not support area editing.', 'flx-ai' ) );
			}
			if ( ! $out['refs'] ) {
				return new WP_Error( 'flxai_media_mask', __( 'Area editing needs the image to edit (refs[0]).', 'flx-ai' ) );
			}
		} else {
			$out['mask'] = null;
		}
	}
	return $out;
}

/* ---------------------------------------------------------------------------
 * Referanslar ve HTTP
 * ------------------------------------------------------------------------- */

/** Referansı herkese açık URL'ye çevirir (sağlayıcılar URL ile indirir). */
function flxai_media_ref_url( $ref ) {
	if ( is_int( $ref ) || ctype_digit( (string) $ref ) ) {
		$u = wp_get_attachment_url( (int) $ref );
		return $u ? $u : '';
	}
	$ref = (string) $ref;
	if ( 0 === strpos( $ref, 'data:' ) || preg_match( '#^https?://#i', $ref ) ) {
		return $ref;
	}
	return '';
}

/**
 * Referansın baytları (Gemini satır içi, maske birleştirme): array( bytes, mime ) ya da WP_Error.
 */
function flxai_media_ref_bytes( $ref, $max = 20971520 ) {
	if ( is_int( $ref ) || ctype_digit( (string) $ref ) ) {
		$file = get_attached_file( (int) $ref );
		if ( $file && is_readable( $file ) && filesize( $file ) <= $max ) {
			return array( (string) file_get_contents( $file ), (string) get_post_mime_type( (int) $ref ) ); // phpcs:ignore
		}
		$ref = flxai_media_ref_url( $ref );
	}
	$ref = (string) $ref;
	if ( preg_match( '#^data:([a-z]+/[a-z0-9.+-]+);base64,(.+)$#is', $ref, $mm ) ) {
		$b = base64_decode( $mm[2], true ); // phpcs:ignore
		return false === $b ? new WP_Error( 'flxai_media_ref', __( 'Could not read the reference.', 'flx-ai' ) ) : array( $b, strtolower( $mm[1] ) );
	}
	// Kendi sitemizdeki dosya ise diskten oku.
	$up = wp_upload_dir();
	if ( 0 === strpos( $ref, $up['baseurl'] . '/' ) ) {
		$path = $up['basedir'] . '/' . substr( $ref, strlen( $up['baseurl'] ) + 1 );
		$real = realpath( $path );
		if ( $real && 0 === strpos( $real, realpath( $up['basedir'] ) ) && is_readable( $real ) && filesize( $real ) <= $max ) {
			$ft = wp_check_filetype( $real );
			return array( (string) file_get_contents( $real ), (string) ( $ft['type'] ? $ft['type'] : 'image/png' ) ); // phpcs:ignore
		}
	}
	if ( ! preg_match( '#^https?://#i', $ref ) ) {
		return new WP_Error( 'flxai_media_ref', __( 'Could not read the reference.', 'flx-ai' ) );
	}
	$r = wp_safe_remote_get(
		$ref,
		array(
			'timeout'             => 20,
			'limit_response_size' => $max,
		)
	);
	if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
		return new WP_Error( 'flxai_media_ref', __( 'Could not download the reference.', 'flx-ai' ) );
	}
	$mime = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $r, 'content-type' ) )[0] ) );
	return array( (string) wp_remote_retrieve_body( $r ), '' !== $mime ? $mime : 'image/png' );
}

/** Referansı data URL olarak (URL kabul etmeyen ya da siteye erişemeyen sağlayıcılar için). */
function flxai_media_ref_data( $ref ) {
	$b = flxai_media_ref_bytes( $ref );
	if ( is_wp_error( $b ) ) {
		return $b;
	}
	return 'data:' . $b[1] . ';base64,' . base64_encode( $b[0] ); // phpcs:ignore
}

/**
 * JSON isteği. Dönüş: array( code, json|null, raw ) ya da WP_Error (bağlantı/zaman aşımı).
 */
function flxai_media_http( $method, $url, array $headers, $body = null, $timeout = 30 ) {
	$args = array(
		'method'      => $method,
		'timeout'     => $timeout,
		'redirection' => 0,
		'headers'     => $headers + array( 'Accept' => 'application/json' ),
	);
	if ( null !== $body ) {
		$args['headers']['Content-Type'] = 'application/json';
		$args['body']                    = wp_json_encode( $body );
	}
	$r = wp_remote_request( $url, $args );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$raw = (string) wp_remote_retrieve_body( $r );
	return array( (int) wp_remote_retrieve_response_code( $r ), json_decode( $raw, true ), $raw );
}

/** Sağlayıcı hata mesajı (anahtar ve uzun girdiler gösterilmez). */
function flxai_media_error_text( $provider, $code, $json, $raw ) {
	$msg = '';
	if ( is_array( $json ) ) {
		$d = $json['detail'] ?? ( $json['error'] ?? ( $json['message'] ?? '' ) );
		if ( is_array( $d ) ) {
			if ( isset( $d['message'] ) ) {
				$msg = (string) $d['message'];
			} elseif ( isset( $d[0]['msg'] ) ) {
				$msg = implode( ' • ', array_filter( array_map( fn( $e ) => is_array( $e ) ? (string) ( $e['msg'] ?? '' ) : '', $d ) ) );
			}
		} else {
			$msg = (string) $d;
		}
	}
	if ( '' === $msg ) {
		$msg = mb_substr( wp_strip_all_tags( (string) $raw ), 0, 160 );
	}
	$labels = flxai_media_provider_labels();
	$msg    = preg_replace( '/(sk|key|fal)[-_][A-Za-z0-9_-]{8,}/', '***', $msg );
	return sprintf( '%s (%d): %s', $labels[ $provider ] ?? $provider, (int) $code, mb_substr( $msg, 0, 200 ) );
}

/** Gönderim sonrası belirsiz durum: istek gitmiş olabilir, ücret alınmış olabilir. Tekrar gönderilmez. */
function flxai_media_ambiguous( $provider, $e ) {
	$labels = flxai_media_provider_labels();
	/* translators: 1: provider, 2: error */
	return new WP_Error( 'flxai_media_ambiguous', sprintf( __( 'No response from %1$s (%2$s). The request may have been charged; check the provider\'s history before sending again.', 'flx-ai' ), $labels[ $provider ] ?? $provider, is_wp_error( $e ) ? $e->get_error_message() : (string) $e ), array( 'maybe_charged' => true ) );
}

/* ---------------------------------------------------------------------------
 * Sağlayıcı uyarlayıcıları: gönder
 * ------------------------------------------------------------------------- */

/** GPT Image boyutu. */
function flxai_media_gpt_size( array $req ) {
	if ( '' !== $req['size'] ) {
		return $req['size'];
	}
	$t = array(
		'1K' => array( '1:1' => '1024x1024', '4:3' => '1024x768', '3:4' => '768x1024', '16:9' => '1536x864', '9:16' => '864x1536' ),
		'2K' => array( '1:1' => '2048x2048', '4:3' => '2048x1536', '3:4' => '1536x2048', '16:9' => '2560x1440', '9:16' => '1440x2560' ),
		'4K' => array( '1:1' => '2880x2880', '4:3' => '3312x2480', '3:4' => '2480x3312', '16:9' => '3840x2160', '9:16' => '2160x3840' ),
	);
	return $t[ $req['resolution'] ][ $req['aspect'] ] ?? 'auto';
}

/**
 * İsteği sağlayıcıya yollar.
 *
 * @return array|WP_Error outputs[] (eşzamanlı: {bytes, mime} | {url}), cost (null = bilinmiyor), meta (kuyruk bilgisi).
 */
function flxai_media_send( $provider, $model, array $req ) {
	$key = flxai_media_key( $provider );
	switch ( $provider ) {
		case 'fal':
			return flxai_media_send_fal( $key, $model, $req );
		case 'evolink':
			return flxai_media_send_evolink( $key, $model, $req );
		case 'openai':
			return flxai_media_send_openai( $key, $model, $req );
		case 'gemini':
			return flxai_media_send_gemini( $key, $model, $req );
		case 'openrouter':
			return 'video' === $req['kind'] ? flxai_media_send_openrouter_video( $key, $model, $req ) : flxai_media_send_openrouter_image( $key, $model, $req );
	}
	return new WP_Error( 'flxai_media_provider', __( 'Unsupported provider.', 'flx-ai' ) );
}

/** fal.ai uç noktası ve girdi. */
function flxai_media_fal_input( $model, array $req ) {
	$refs = array_values( array_filter( array_map( 'flxai_media_ref_url', $req['refs'] ) ) );
	$vids = array_values( array_filter( array_map( 'flxai_media_ref_url', $req['video_refs'] ) ) );
	$auds = array_values( array_filter( array_map( 'flxai_media_ref_url', $req['audio_refs'] ) ) );
	if ( 'gpt-image-2.5-sunburst' === $model ) {
		$size = flxai_media_gpt_size( $req );
		$in   = array(
			'prompt'        => $req['prompt'],
			'image_size'    => 'auto' === $size ? 'auto' : array_combine( array( 'width', 'height' ), array_map( 'intval', explode( 'x', $size ) ) ),
			'quality'       => $req['quality'],
			'num_images'    => $req['n'],
			'output_format' => 'png',
		);
		if ( $refs ) {
			$in['image_urls'] = $refs;
		}
		return array( 'openai/gpt-image-2.5/sunburst/' . ( $refs ? 'edit' : 'text-to-image' ), $in );
	}
	if ( 'gemini-nano-banana-2.1' === $model || 'gemini-3-pro-image-preview' === $model ) {
		$in = array(
			'prompt'        => $req['prompt'],
			'aspect_ratio'  => $req['aspect'],
			'resolution'    => $req['resolution'],
			'num_images'    => $req['n'],
			'output_format' => 'png',
		);
		if ( 'gemini-nano-banana-2.1' === $model ) {
			$in['thinking_level'] = 'high';
		}
		if ( $refs ) {
			$in['image_urls'] = $refs;
		}
		$base = 'gemini-nano-banana-2.1' === $model ? 'google/nano-banana-2.1' : 'fal-ai/nano-banana-pro';
		return array( $base . ( $refs ? '/edit' : '' ), $in );
	}
	if ( 0 === strpos( $model, 'seedance-' ) ) {
		$in = array(
			'prompt'         => $req['prompt'],
			'resolution'     => $req['resolution'],
			'duration'       => (string) $req['duration'],
			'aspect_ratio'   => 'adaptive' === $req['aspect'] ? 'auto' : $req['aspect'],
			'generate_audio' => (bool) $req['audio'],
		);
		if ( 'frames' === $req['mode'] ) {
			$in['image_url'] = $refs[0] ?? '';
			if ( isset( $refs[1] ) ) {
				$in['end_image_url'] = $refs[1];
			}
		} elseif ( 'reference' === $req['mode'] ) {
			$in['image_urls'] = $refs;
			$in['video_urls'] = $vids;
			$in['audio_urls'] = $auds;
		}
		$mode = array(
			'text'      => 'text-to-video',
			'frames'    => 'image-to-video',
			'reference' => 'reference-to-video',
		);
		return array( 'bytedance/' . $model . '/' . $mode[ $req['mode'] ], $in );
	}
	if ( 'minimax-h3' === $model ) {
		$in = array(
			'prompt'                => $req['prompt'],
			'resolution'            => strtoupper( $req['resolution'] ),
			'duration'              => $req['duration'],
			'prompt_expansion_mode' => 'balanced',
			'sync_mode'             => false,
		);
		if ( 'frames' === $req['mode'] ) {
			$in['image_url'] = $refs[0] ?? '';
			if ( isset( $refs[1] ) ) {
				$in['end_image_url'] = $refs[1];
			}
			if ( $auds ) {
				$in['target_audio_url'] = $auds[0];
			}
			return array( 'minimax/h3-max/image-to-video', $in );
		}
		$in['aspect_ratio'] = $req['aspect'];
		if ( 'reference' === $req['mode'] ) {
			if ( $refs ) {
				$in['reference_image_urls'] = $refs;
			}
			if ( $vids ) {
				$in['reference_video_urls'] = $vids;
			}
			if ( $auds ) {
				$in['reference_audio_urls'] = $auds;
			}
			return array( 'minimax/h3-max/reference-to-video', $in );
		}
		if ( 'adaptive' === $in['aspect_ratio'] ) {
			$in['aspect_ratio'] = '16:9';
		}
		if ( $auds ) {
			$in['target_audio_url'] = $auds[0];
		}
		return array( 'minimax/h3-max/text-to-video', $in );
	}
	return new WP_Error( 'flxai_media_model', __( 'Unsupported model.', 'flx-ai' ) );
}

/** fal.ai kuyruğuna gönderir (asenkron). */
function flxai_media_send_fal( $key, $model, array $req ) {
	$fi = flxai_media_fal_input( $model, $req );
	if ( is_wp_error( $fi ) ) {
		return $fi;
	}
	list( $endpoint, $input ) = $fi;
	$r = flxai_media_http( 'POST', flxai_media_base( 'fal' ) . '/' . $endpoint, array( 'Authorization' => 'Key ' . $key ), $input, 40 );
	if ( is_wp_error( $r ) ) {
		return flxai_media_ambiguous( 'fal', $r );
	}
	list( $code, $json, $raw ) = $r;
	if ( $code >= 400 ) {
		return new WP_Error( 'flxai_media_provider', flxai_media_error_text( 'fal', $code, $json, $raw ), array( 'maybe_charged' => $code >= 500 ) );
	}
	$rid = (string) ( $json['request_id'] ?? '' );
	if ( ! preg_match( '/^[A-Za-z0-9_-]{1,128}$/', $rid ) ) {
		return flxai_media_ambiguous( 'fal', __( 'no request id', 'flx-ai' ) );
	}
	// Durum adresi uygulama yolundadır (…/edit gibi alt yollar olmadan); fal döndürdüyse onu kullan.
	$parts = explode( '/', $endpoint );
	$app   = implode( '/', array_slice( $parts, 0, 2 ) );
	$base  = flxai_media_base( 'fal' );
	$su    = (string) ( $json['status_url'] ?? '' );
	$ru    = (string) ( $json['response_url'] ?? '' );
	$meta  = array(
		'ref'      => $rid,
		'endpoint' => $endpoint,
		'status'   => 0 === strpos( $su, $base . '/' ) ? $su : $base . '/' . $app . '/requests/' . rawurlencode( $rid ) . '/status',
		'result'   => 0 === strpos( $ru, $base . '/' ) ? $ru : $base . '/' . $app . '/requests/' . rawurlencode( $rid ),
	);
	return array(
		'outputs' => array(),
		'meta'    => $meta,
	);
}

/** EvoLink (Seedance) görev gönderir. */
function flxai_media_send_evolink( $key, $model, array $req ) {
	$refs = array_values( array_filter( array_map( 'flxai_media_ref_url', $req['refs'] ) ) );
	$vids = array_values( array_filter( array_map( 'flxai_media_ref_url', $req['video_refs'] ) ) );
	$auds = array_values( array_filter( array_map( 'flxai_media_ref_url', $req['audio_refs'] ) ) );
	foreach ( array_merge( $refs, $vids, $auds ) as $u ) {
		if ( ! preg_match( '#^https?://#i', $u ) ) {
			return new WP_Error( 'flxai_media_ref', __( 'EvoLink only accepts public http(s) URLs; upload the reference to the site.', 'flx-ai' ) );
		}
	}
	$mode = array(
		'text'      => 'text-to-video',
		'frames'    => 'image-to-video',
		'reference' => 'reference-to-video',
	);
	$body = array(
		'model'          => $model . '-' . $mode[ $req['mode'] ],
		'prompt'         => $req['prompt'],
		'duration'       => $req['duration'],
		'quality'        => $req['resolution'],
		'aspect_ratio'   => 'frames' === $req['mode'] && 'seedance-2.5' === $model ? 'adaptive' : $req['aspect'],
		'generate_audio' => (bool) $req['audio'],
		'content_filter' => 'seedance-2.5' !== $model,
		'output_format'  => 'mp4',
	);
	if ( $refs ) {
		$body['image_urls'] = $refs;
	}
	if ( 'reference' === $req['mode'] ) {
		if ( $vids ) {
			$body['video_urls'] = $vids;
		}
		if ( $auds ) {
			$body['audio_urls'] = $auds;
		}
	}
	$r = flxai_media_http( 'POST', flxai_media_base( 'evolink' ) . '/v1/videos/generations', array( 'Authorization' => 'Bearer ' . $key ), $body, 40 );
	if ( is_wp_error( $r ) ) {
		return flxai_media_ambiguous( 'evolink', $r );
	}
	list( $code, $json, $raw ) = $r;
	if ( $code >= 400 ) {
		return new WP_Error( 'flxai_media_provider', flxai_media_error_text( 'evolink', $code, $json, $raw ), array( 'maybe_charged' => $code >= 500 ) );
	}
	$id = (string) ( $json['id'] ?? '' );
	if ( ! preg_match( '/^[A-Za-z0-9_.:-]{1,128}$/', $id ) ) {
		return flxai_media_ambiguous( 'evolink', __( 'no task id', 'flx-ai' ) );
	}
	return array(
		'outputs' => array(),
		'meta'    => array( 'ref' => $id ),
	);
}

/** OpenRouter video (Seedance) gönderir. */
function flxai_media_send_openrouter_video( $key, $model, array $req ) {
	if ( $req['video_refs'] || $req['audio_refs'] ) {
		return new WP_Error( 'flxai_media_ref', __( 'OpenRouter does not accept video/audio references; choose fal.ai or EvoLink.', 'flx-ai' ) );
	}
	if ( 'adaptive' === $req['aspect'] ) {
		return new WP_Error( 'flxai_media_aspect', __( 'Choose an aspect ratio for OpenRouter.', 'flx-ai' ) );
	}
	if ( 'seedance-2.5' === $model && '1080p' === $req['resolution'] ) {
		return new WP_Error( 'flxai_media_res', __( 'Seedance 2.5 on OpenRouter is limited to 720p; choose fal.ai or EvoLink for 1080p.', 'flx-ai' ) );
	}
	$refs = array_values( array_filter( array_map( 'flxai_media_ref_url', $req['refs'] ) ) );
	$body = array(
		'model'          => 'bytedance/' . $model,
		'prompt'         => $req['prompt'],
		'duration'       => $req['duration'],
		'resolution'     => $req['resolution'],
		'aspect_ratio'   => $req['aspect'],
		'generate_audio' => (bool) $req['audio'],
		'provider'       => array( 'allow_fallbacks' => false ),
	);
	if ( 'frames' === $req['mode'] ) {
		$body['frame_images'] = array_map(
			fn( $u, $i ) => array(
				'type'       => 'image_url',
				'image_url'  => array( 'url' => $u ),
				'frame_type' => $i ? 'last_frame' : 'first_frame',
			),
			$refs,
			array_keys( $refs )
		);
	} elseif ( $refs ) {
		$body['input_references'] = array_map( fn( $u ) => array( 'type' => 'image_url', 'image_url' => array( 'url' => $u ) ), array_slice( $refs, 0, 9 ) );
	}
	$r = flxai_media_http( 'POST', flxai_media_base( 'openrouter' ) . '/videos', array( 'Authorization' => 'Bearer ' . $key ), $body, 40 );
	if ( is_wp_error( $r ) ) {
		return flxai_media_ambiguous( 'openrouter', $r );
	}
	list( $code, $json, $raw ) = $r;
	if ( $code >= 400 ) {
		return new WP_Error( 'flxai_media_provider', flxai_media_error_text( 'openrouter', $code, $json, $raw ), array( 'maybe_charged' => $code >= 500 ) );
	}
	$id = (string) ( $json['id'] ?? '' );
	if ( ! preg_match( '/^[A-Za-z0-9_-]{1,128}$/', $id ) ) {
		return flxai_media_ambiguous( 'openrouter', __( 'no task id', 'flx-ai' ) );
	}
	return array(
		'outputs' => array(),
		'meta'    => array( 'ref' => $id ),
	);
}

/** Eşzamanlı görsel cevabından (OpenAI / OpenRouter biçimi) çıktılar. */
function flxai_media_b64_outputs( $json ) {
	$outs = array();
	foreach ( (array) ( $json['data'] ?? array() ) as $d ) {
		if ( ! empty( $d['b64_json'] ) ) {
			$b = base64_decode( (string) $d['b64_json'], true ); // phpcs:ignore
			if ( false !== $b ) {
				$outs[] = array(
					'bytes' => $b,
					'mime'  => (string) ( $d['media_type'] ?? 'image/png' ),
				);
			}
		} elseif ( ! empty( $d['url'] ) ) {
			$outs[] = array( 'url' => (string) $d['url'] );
		}
	}
	return $outs;
}

/** OpenAI GPT Image cevabındaki kullanımdan USD (2026-09 fiyatları; 1M token başına). */
function flxai_media_openai_cost( $usage ) {
	$d   = $usage['input_tokens_details'] ?? null;
	$txt = $d['text_tokens'] ?? null;
	$img = $d['image_tokens'] ?? null;
	$out = $usage['output_tokens_details']['image_tokens'] ?? ( $usage['output_tokens'] ?? null );
	if ( ! is_numeric( $txt ) || ! is_numeric( $img ) || ! is_numeric( $out ) ) {
		return null;
	}
	$c  = $d['cached_tokens_details'] ?? array();
	$ct = (float) ( $c['text_tokens'] ?? 0 );
	$ci = (float) ( $c['image_tokens'] ?? 0 );
	return ( ( $txt - $ct ) * 5 + $ct * 1.25 + ( $img - $ci ) * 8 + $ci * 2 + $out * 30 ) / 1000000;
}

/** OpenAI Images API (eşzamanlı; maske yerel desteklenir). */
function flxai_media_send_openai( $key, $model, array $req ) {
	$body = array(
		'model'         => $model,
		'prompt'        => $req['prompt'],
		'n'             => $req['n'],
		'size'          => flxai_media_gpt_size( $req ),
		'quality'       => $req['quality'],
		'output_format' => 'png',
		'moderation'    => 'low',
	);
	$path = '/v1/images/generations';
	if ( $req['refs'] ) {
		$path           = '/v1/images/edits';
		$body['images'] = array();
		foreach ( $req['refs'] as $ref ) {
			$u = flxai_media_ref_url( $ref );
			if ( '' === $u ) {
				return new WP_Error( 'flxai_media_ref', __( 'Could not read the reference.', 'flx-ai' ) );
			}
			$body['images'][] = array( 'image_url' => $u );
		}
		if ( null !== $req['mask'] ) {
			$mu = flxai_media_ref_data( $req['mask'] );
			if ( is_wp_error( $mu ) ) {
				return $mu;
			}
			$body['mask'] = array( 'image_url' => $mu );
		}
	}
	$r = flxai_media_http( 'POST', flxai_media_base( 'openai' ) . $path, array( 'Authorization' => 'Bearer ' . $key ), $body, 50 );
	if ( is_wp_error( $r ) ) {
		return flxai_media_ambiguous( 'openai', $r );
	}
	list( $code, $json, $raw ) = $r;
	if ( $code >= 400 ) {
		return new WP_Error( 'flxai_media_provider', flxai_media_error_text( 'openai', $code, $json, $raw ), array( 'maybe_charged' => $code >= 500 ) );
	}
	$outs = flxai_media_b64_outputs( $json );
	if ( ! $outs ) {
		return new WP_Error( 'flxai_media_empty', __( 'OpenAI returned no image. Check usage history before sending again.', 'flx-ai' ), array( 'maybe_charged' => true ) );
	}
	return array(
		'outputs'   => $outs,
		'cost'      => flxai_media_openai_cost( (array) ( $json['usage'] ?? array() ) ),
		'masked_ok' => true,
	);
}

/** OpenRouter görsel (eşzamanlı). */
function flxai_media_send_openrouter_image( $key, $model, array $req ) {
	$ids   = array(
		'gpt-image-2.5-sunburst'     => 'openai/gpt-image-2.5-sunburst',
		'gemini-3-pro-image-preview' => 'google/gemini-3-pro-image',
		'gemini-nano-banana-2.1'     => 'google/gemini-nano-banana-2.1',
	);
	$sun   = 'gpt-image-2.5-sunburst' === $model;
	$route = $sun ? 'openai' : ( 'gemini-3-pro-image-preview' === $model ? 'google-ai-studio/global' : 'google-ai-studio' );
	$body  = array(
		'model'    => $ids[ $model ] ?? $model,
		'prompt'   => $req['prompt'],
		'n'        => $req['n'],
		'provider' => array(
			'only'            => array( $route ),
			'allow_fallbacks' => false,
		),
	);
	if ( $sun ) {
		$body['size']          = flxai_media_gpt_size( $req );
		$body['quality']       = $req['quality'];
		$body['output_format'] = 'png';
	} else {
		$body['resolution']    = $req['resolution'];
		$body['aspect_ratio']  = $req['aspect'];
		$body['output_format'] = 'png';
	}
	if ( $req['refs'] ) {
		$body['input_references'] = array();
		foreach ( $req['refs'] as $ref ) {
			$u = flxai_media_ref_url( $ref );
			if ( '' === $u ) {
				return new WP_Error( 'flxai_media_ref', __( 'Could not read the reference.', 'flx-ai' ) );
			}
			$body['input_references'][] = array(
				'type'      => 'image_url',
				'image_url' => array( 'url' => $u ),
			);
		}
	}
	$r = flxai_media_http( 'POST', flxai_media_base( 'openrouter' ) . '/images', array( 'Authorization' => 'Bearer ' . $key ), $body, 50 );
	if ( is_wp_error( $r ) ) {
		return flxai_media_ambiguous( 'openrouter', $r );
	}
	list( $code, $json, $raw ) = $r;
	if ( $code >= 400 ) {
		return new WP_Error( 'flxai_media_provider', flxai_media_error_text( 'openrouter', $code, $json, $raw ), array( 'maybe_charged' => $code >= 500 ) );
	}
	$outs = flxai_media_b64_outputs( $json );
	if ( ! $outs ) {
		return new WP_Error( 'flxai_media_empty', __( 'OpenRouter returned no image. Check usage history before sending again.', 'flx-ai' ), array( 'maybe_charged' => true ) );
	}
	$c = $json['usage']['cost'] ?? null;
	return array(
		'outputs' => $outs,
		'cost'    => is_numeric( $c ) && $c >= 0 ? (float) $c : null,
	);
}

/** Google Gemini (eşzamanlı; her görsel ayrı istek). */
function flxai_media_send_gemini( $key, $model, array $req ) {
	$parts = array();
	foreach ( $req['refs'] as $ref ) {
		$b = flxai_media_ref_bytes( $ref );
		if ( is_wp_error( $b ) ) {
			return $b;
		}
		$parts[] = array(
			'inlineData' => array(
				'mimeType' => $b[1],
				'data'     => base64_encode( $b[0] ), // phpcs:ignore
			),
		);
	}
	$parts[] = array( 'text' => $req['prompt'] );
	$body    = array(
		'contents'         => array( array( 'parts' => $parts ) ),
		'generationConfig' => array(
			'responseModalities' => array( 'IMAGE' ),
			'imageConfig'        => array(
				'aspectRatio' => $req['aspect'],
				'imageSize'   => $req['resolution'],
			),
		),
	);
	$outs    = array();
	$tok     = 0;
	$t0      = microtime( true );
	for ( $i = 0; $i < $req['n']; $i++ ) {
		if ( $i > 0 && microtime( true ) - $t0 > 25 ) {
			break; // 60 sn sınırı: kalanlar gönderilmez (gelenler döner).
		}
		$r = flxai_media_http( 'POST', flxai_media_base( 'gemini' ) . '/v1beta/models/' . rawurlencode( $model ) . ':generateContent', array( 'x-goog-api-key' => $key ), $body, 45 );
		if ( is_wp_error( $r ) ) {
			if ( $outs ) {
				break;
			}
			return flxai_media_ambiguous( 'gemini', $r );
		}
		list( $code, $json, $raw ) = $r;
		if ( $code >= 400 ) {
			if ( $outs ) {
				break;
			}
			return new WP_Error( 'flxai_media_provider', flxai_media_error_text( 'gemini', $code, $json, $raw ), array( 'maybe_charged' => $code >= 500 ) );
		}
		foreach ( (array) ( $json['candidates'][0]['content']['parts'] ?? array() ) as $p ) {
			if ( ! empty( $p['inlineData']['data'] ) ) {
				$b = base64_decode( (string) $p['inlineData']['data'], true ); // phpcs:ignore
				if ( false !== $b ) {
					$outs[] = array(
						'bytes' => $b,
						'mime'  => (string) ( $p['inlineData']['mimeType'] ?? 'image/png' ),
					);
				}
			}
		}
		$tok += (int) ( $json['usageMetadata']['totalTokenCount'] ?? 0 );
	}
	if ( ! $outs ) {
		return new WP_Error( 'flxai_media_empty', __( 'Gemini returned no image (possibly the content filter).', 'flx-ai' ), array( 'maybe_charged' => true ) );
	}
	return array(
		'outputs' => $outs,
		'cost'    => null,
		'meta'    => array( 'tokens' => $tok ),
	);
}

/* ---------------------------------------------------------------------------
 * Durum
 * ------------------------------------------------------------------------- */

/**
 * İş durumu. Kuyruktaki işte sağlayıcıya sorar (en çok 3 sn'de bir); bitince çıktıları indirir.
 *
 * @param string $job flxai_media_submit'in döndürdüğü job.
 * @return array|WP_Error job, state (queued|running|done|failed|cancelled), progress (0–1), kind, model, provider, outputs[{url, path, mime, width, height, bytes, remote, stored}], cost, est, error, source, user_id, created, updated
 */
function flxai_media_status( $job ) {
	$row = flxai_media_job_get( $job );
	if ( ! $row ) {
		return new WP_Error( 'flxai_media_job', __( 'Job not found.', 'flx-ai' ), array( 'status' => 404 ) );
	}
	if ( ! in_array( $row['state'], array( 'queued', 'running' ), true ) ) {
		return flxai_media_public( $row );
	}
	$lock = 'flxai_mpoll_' . $row['id'];
	if ( get_transient( $lock ) ) {
		return flxai_media_public( $row );
	}
	set_transient( $lock, 1, 3 );
	$meta = json_decode( (string) $row['meta'], true );
	$meta = is_array( $meta ) ? $meta : array();
	$key  = flxai_media_key( $row['provider'] );
	if ( '' === $key ) {
		return flxai_media_public( array_merge( $row, array( 'error' => __( 'The provider key was removed; the job is kept and continues once the key is entered again.', 'flx-ai' ) ) ) );
	}
	switch ( $row['provider'] ) {
		case 'fal':
			$st = flxai_media_poll_fal( $key, $meta );
			break;
		case 'evolink':
			$st = flxai_media_poll_evolink( $key, $meta );
			break;
		case 'openrouter':
			$st = flxai_media_poll_openrouter( $key, $meta );
			break;
		default:
			$st = array(
				'state' => 'failed',
				'error' => __( 'The job was interrupted.', 'flx-ai' ),
			);
	}
	if ( is_wp_error( $st ) ) {
		// Geçici hata: iş korunur, sonraki sorguda tekrar bakılır.
		return flxai_media_public( array_merge( $row, array( 'error' => $st->get_error_message() ) ) );
	}
	$upd = array( 'error' => '' );
	if ( 'done' === $st['state'] ) {
		// İndirme uzun sürebilir: aynı anda iki sorgu indirmesin.
		set_transient( $lock, 1, 90 );
		$req          = json_decode( (string) $row['req'], true );
		$outs         = flxai_media_finish_outputs( $row, (array) $st['outputs'], is_array( $req ) ? $req : array() );
		$want         = max( 1, (int) ( $req['n'] ?? 1 ) );
		$part         = 'image' === $row['kind'] && count( $outs ) < $want ? count( $outs ) / $want : 1;
		$cost         = isset( $st['cost'] ) && null !== $st['cost'] ? (float) $st['cost'] : round( (float) $row['cost'] * $part, 4 );
		$upd         += array(
			'state'    => 'done',
			'progress' => 1,
			'outputs'  => $outs,
			'cost'     => $cost,
			'est'      => isset( $st['cost'] ) && null !== $st['cost'] ? 0 : (int) $row['est'],
		);
		flxai_media_log_update(
			(int) $row['log_id'],
			array(
				'cost'      => $cost,
				'full_cost' => $cost,
				'est'       => $upd['est'],
				'ms'        => max( 0, ( time() - (int) $row['ts'] ) * 1000 ),
			)
		);
		delete_transient( $lock );
	} elseif ( in_array( $st['state'], array( 'failed', 'cancelled' ), true ) ) {
		$upd += array(
			'state' => $st['state'],
			'error' => (string) ( $st['error'] ?? '' ),
			'cost'  => 0,
		);
		flxai_media_log_update(
			(int) $row['log_id'],
			array(
				'cost'      => 0,
				'full_cost' => 0,
				'ok'        => 0,
				'error'     => mb_substr( (string) ( $st['error'] ?? $st['state'] ), 0, 250 ),
			)
		);
	} else {
		$upd += array(
			'state'    => $st['state'],
			'progress' => max( (float) $row['progress'], (float) ( $st['progress'] ?? 0 ) ),
		);
	}
	flxai_media_job_update( (int) $row['id'], $upd );
	$row = flxai_media_job_get( $job );
	$pub = flxai_media_public( $row );
	if ( 'done' === $row['state'] ) {
		do_action( 'flxai_media_done', $pub );
	}
	return $pub;
}

/** fal.ai kuyruk durumu. */
function flxai_media_poll_fal( $key, array $meta ) {
	$h = array( 'Authorization' => 'Key ' . $key );
	$r = flxai_media_http( 'GET', (string) $meta['status'], $h, null, 20 );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	list( $code, $json, $raw ) = $r;
	if ( $code >= 400 && $code < 500 ) {
		return array(
			'state' => 'failed',
			'error' => flxai_media_error_text( 'fal', $code, $json, $raw ),
		);
	}
	if ( $code >= 400 ) {
		return new WP_Error( 'flxai_media_poll', flxai_media_error_text( 'fal', $code, $json, $raw ) );
	}
	$s = (string) ( $json['status'] ?? '' );
	if ( 'IN_QUEUE' === $s ) {
		return array(
			'state'    => 'queued',
			'progress' => 0.05,
		);
	}
	if ( 'IN_PROGRESS' === $s ) {
		return array(
			'state'    => 'running',
			'progress' => 0.4,
		);
	}
	if ( 'ERROR' === $s || 'CANCELLED' === $s ) {
		return array(
			'state' => 'CANCELLED' === $s ? 'cancelled' : 'failed',
			'error' => flxai_media_error_text( 'fal', $code, array( 'detail' => $json['error'] ?? ( $json['error_message'] ?? $s ) ), '' ),
		);
	}
	if ( 'COMPLETED' !== $s ) {
		return array(
			'state'    => 'running',
			'progress' => 0.3,
		);
	}
	$r = flxai_media_http( 'GET', (string) $meta['result'], $h, null, 20 );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	list( $code, $json, $raw ) = $r;
	if ( $code >= 400 && $code < 500 ) {
		return array(
			'state' => 'failed',
			'error' => flxai_media_error_text( 'fal', $code, $json, $raw ),
		);
	}
	if ( $code >= 400 || ! is_array( $json ) ) {
		return new WP_Error( 'flxai_media_poll', flxai_media_error_text( 'fal', $code, $json, $raw ) );
	}
	$outs = array();
	foreach ( (array) ( $json['images'] ?? array() ) as $im ) {
		if ( ! empty( $im['url'] ) ) {
			$outs[] = array(
				'url'  => (string) $im['url'],
				'mime' => (string) ( $im['content_type'] ?? '' ),
			);
		}
	}
	if ( ! empty( $json['video']['url'] ) ) {
		$outs[] = array(
			'url'  => (string) $json['video']['url'],
			'mime' => (string) ( $json['video']['content_type'] ?? 'video/mp4' ),
		);
	}
	if ( ! $outs ) {
		return array(
			'state' => 'failed',
			'error' => __( 'fal.ai returned no output (possibly the content filter).', 'flx-ai' ),
		);
	}
	return array(
		'state'   => 'done',
		'outputs' => $outs,
		'cost'    => null,
	);
}

/** EvoLink görev durumu. */
function flxai_media_poll_evolink( $key, array $meta ) {
	$r = flxai_media_http( 'GET', flxai_media_base( 'evolink' ) . '/v1/tasks/' . rawurlencode( (string) $meta['ref'] ), array( 'Authorization' => 'Bearer ' . $key ), null, 20 );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	list( $code, $json, $raw ) = $r;
	$s    = strtolower( (string) ( $json['status'] ?? '' ) );
	$urls = array();
	foreach ( array( 'results', 'result_data', 'outputs' ) as $k ) {
		foreach ( (array) ( $json[ $k ] ?? array() ) as $it ) {
			$u = is_string( $it ) ? $it : (string) ( $it['url'] ?? ( $it['video_url'] ?? ( $it['output_url'] ?? '' ) ) );
			if ( preg_match( '#^https?://#i', $u ) ) {
				$urls[] = array(
					'url'  => $u,
					'mime' => 'video/mp4',
				);
			}
		}
	}
	if ( in_array( $s, array( 'completed', 'succeeded', 'success' ), true ) ) {
		if ( ! $urls ) {
			return new WP_Error( 'flxai_media_poll', __( 'The EvoLink task finished but has no result URL yet.', 'flx-ai' ) );
		}
		$cost = $json['usage']['cost'] ?? ( $json['usage']['total_cost'] ?? null );
		return array(
			'state'   => 'done',
			'outputs' => $urls,
			'cost'    => is_numeric( $cost ) ? (float) $cost : null,
		);
	}
	if ( $code >= 400 ) {
		return new WP_Error( 'flxai_media_poll', flxai_media_error_text( 'evolink', $code, $json, $raw ) );
	}
	if ( in_array( $s, array( 'failed', 'failure', 'error', 'cancelled', 'canceled' ), true ) ) {
		return array(
			'state' => in_array( $s, array( 'cancelled', 'canceled' ), true ) ? 'cancelled' : 'failed',
			'error' => flxai_media_error_text( 'evolink', $code, $json, $raw ),
		);
	}
	$p = is_numeric( $json['progress'] ?? null ) ? max( 0, min( 1, (float) $json['progress'] / 100 ) ) : 0.1;
	return array(
		'state'    => 'pending' === $s || 'queued' === $s ? 'queued' : 'running',
		'progress' => $p,
	);
}

/** OpenRouter video durumu. */
function flxai_media_poll_openrouter( $key, array $meta ) {
	$base = flxai_media_base( 'openrouter' ) . '/videos/' . rawurlencode( (string) $meta['ref'] );
	$h    = array( 'Authorization' => 'Bearer ' . $key );
	$r    = flxai_media_http( 'GET', $base, $h, null, 20 );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	list( $code, $json, $raw ) = $r;
	if ( $code >= 400 ) {
		return new WP_Error( 'flxai_media_poll', flxai_media_error_text( 'openrouter', $code, $json, $raw ) );
	}
	$s = strtolower( (string) ( $json['status'] ?? '' ) );
	if ( in_array( $s, array( 'failed', 'cancelled', 'expired' ), true ) ) {
		return array(
			'state' => 'cancelled' === $s ? 'cancelled' : 'failed',
			'error' => flxai_media_error_text( 'openrouter', $code, $json, $raw ),
		);
	}
	if ( 'completed' !== $s ) {
		return array(
			'state'    => 'pending' === $s ? 'queued' : 'running',
			'progress' => 'pending' === $s ? 0.05 : 0.5,
		);
	}
	$outs = array();
	foreach ( (array) ( $json['urls'] ?? array() ) as $u ) {
		if ( is_string( $u ) && preg_match( '#^https?://#i', $u ) ) {
			$outs[] = array(
				'url'  => $u,
				'mime' => 'video/mp4',
			);
		}
	}
	if ( ! $outs ) {
		// İmzalı adres yoksa içerik anahtarla indirilir.
		$outs[] = array(
			'url'     => $base . '/content?index=0',
			'mime'    => 'video/mp4',
			'private' => true,
		);
	}
	$c = $json['usage']['cost'] ?? null;
	return array(
		'state'   => 'done',
		'outputs' => $outs,
		'cost'    => is_numeric( $c ) && $c >= 0 ? (float) $c : null,
	);
}

/**
 * İşi iptal eder. fal.ai kuyruğunda sağlayıcıdan da iptal istenir; diğerlerinde yalnız yerel kayıt kapanır
 * (sağlayıcıda başlamış iş ücretlendirilebilir).
 *
 * @return array|WP_Error flxai_media_status() biçimi.
 */
function flxai_media_cancel( $job ) {
	$row = flxai_media_job_get( $job );
	if ( ! $row ) {
		return new WP_Error( 'flxai_media_job', __( 'Job not found.', 'flx-ai' ), array( 'status' => 404 ) );
	}
	if ( ! in_array( $row['state'], array( 'queued', 'running' ), true ) ) {
		return flxai_media_public( $row );
	}
	$meta = json_decode( (string) $row['meta'], true );
	$note = '';
	if ( 'fal' === $row['provider'] && ! empty( $meta['result'] ) ) {
		$key = flxai_media_key( 'fal' );
		$r   = '' !== $key ? flxai_media_http( 'PUT', $meta['result'] . '/cancel', array( 'Authorization' => 'Key ' . $key ), null, 15 ) : null;
		$ok  = is_array( $r ) && $r[0] < 300;
		$note = $ok ? '' : __( 'The provider did not confirm the cancellation; the job may be charged if it completes.', 'flx-ai' );
		$cost = $ok ? 0 : (float) $row['cost'];
	} else {
		$note = __( 'Cancelled locally; a job already started at the provider may be charged.', 'flx-ai' );
		$cost = (float) $row['cost'];
	}
	flxai_media_job_update(
		(int) $row['id'],
		array(
			'state' => 'cancelled',
			'error' => $note,
			'cost'  => $cost,
		)
	);
	flxai_media_log_update(
		(int) $row['log_id'],
		array(
			'cost'      => $cost,
			'full_cost' => $cost,
			'ok'        => 0,
			'error'     => 'cancelled',
		)
	);
	return flxai_media_public( flxai_media_job_get( $job ) );
}

/* ---------------------------------------------------------------------------
 * Çıktılar
 * ------------------------------------------------------------------------- */

/** Saklama klasörü (uploads/flxai-media/YYYY/MM). */
function flxai_media_dir() {
	$up  = wp_upload_dir();
	$sub = 'flxai-media/' . gmdate( 'Y/m' );
	$dir = $up['basedir'] . '/' . $sub;
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
		foreach ( array( $up['basedir'] . '/flxai-media', $dir ) as $d ) {
			if ( ! file_exists( $d . '/index.php' ) ) {
				file_put_contents( $d . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
			}
		}
	}
	return array( $dir, $up['baseurl'] . '/' . $sub );
}

function flxai_media_ext( $mime ) {
	$m = array(
		'image/png'  => 'png',
		'image/jpeg' => 'jpg',
		'image/webp' => 'webp',
		'video/mp4'  => 'mp4',
		'video/webm' => 'webm',
	);
	return $m[ strtolower( (string) $mime ) ] ?? '';
}

/** Baytları diske yazar. */
function flxai_media_write( $bytes, $mime ) {
	$ext = flxai_media_ext( $mime );
	if ( '' === $ext ) {
		$f = finfo_open( FILEINFO_MIME_TYPE );
		$mime = $f ? (string) finfo_buffer( $f, $bytes ) : '';
		$ext  = flxai_media_ext( $mime );
	}
	if ( '' === $ext ) {
		return new WP_Error( 'flxai_media_type', __( 'Unsupported output type.', 'flx-ai' ) );
	}
	list( $dir, $url ) = flxai_media_dir();
	$name = bin2hex( random_bytes( 10 ) ) . '.' . $ext;
	if ( false === file_put_contents( $dir . '/' . $name, $bytes ) ) { // phpcs:ignore
		return new WP_Error( 'flxai_media_write', __( 'Could not save the output.', 'flx-ai' ) );
	}
	$out = array(
		'url'    => $url . '/' . $name,
		'path'   => $dir . '/' . $name,
		'mime'   => $mime,
		'bytes'  => strlen( $bytes ),
		'stored' => true,
	);
	if ( 0 === strpos( $mime, 'image/' ) ) {
		$sz = @getimagesizefromstring( $bytes ); // phpcs:ignore
		if ( $sz ) {
			$out['width']  = (int) $sz[0];
			$out['height'] = (int) $sz[1];
		}
	}
	return $out;
}

/** Uzak çıktıyı indirir (görsel 50 MB, video 256 MB sınırı). */
function flxai_media_download( array $o, $provider ) {
	$url     = (string) $o['url'];
	$video   = 0 === strpos( (string) ( $o['mime'] ?? '' ), 'video/' );
	$max     = $video ? 268435456 : 52428800;
	$headers = array();
	if ( ! empty( $o['private'] ) ) {
		$headers['Authorization'] = 'Bearer ' . flxai_media_key( $provider );
	}
	if ( preg_match( '#^data:([a-z]+/[a-z0-9.+-]+);base64,(.+)$#is', $url, $mm ) ) {
		$b = base64_decode( $mm[2], true ); // phpcs:ignore
		return false === $b ? new WP_Error( 'flxai_media_dl', 'data' ) : flxai_media_write( $b, strtolower( $mm[1] ) );
	}
	list( $dir ) = flxai_media_dir();
	$tmp         = $dir . '/.dl-' . bin2hex( random_bytes( 6 ) );
	$r           = wp_remote_get(
		$url,
		array(
			'timeout'             => $video ? 45 : 25,
			'redirection'         => 3,
			'stream'              => true,
			'filename'            => $tmp,
			'limit_response_size' => $max,
			'headers'             => $headers,
		)
	);
	if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) || ! file_exists( $tmp ) ) {
		@unlink( $tmp ); // phpcs:ignore
		return new WP_Error( 'flxai_media_dl', __( 'Could not download the output; the provider URL is used for now.', 'flx-ai' ) );
	}
	$mime = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $r, 'content-type' ) )[0] ) );
	$f    = finfo_open( FILEINFO_MIME_TYPE );
	$real = $f ? (string) finfo_file( $f, $tmp ) : '';
	$mime = '' !== flxai_media_ext( $real ) ? $real : $mime;
	$ext  = flxai_media_ext( $mime );
	if ( '' === $ext ) {
		@unlink( $tmp ); // phpcs:ignore
		return new WP_Error( 'flxai_media_type', __( 'Unsupported output type.', 'flx-ai' ) );
	}
	$name = bin2hex( random_bytes( 10 ) ) . '.' . $ext;
	rename( $tmp, $dir . '/' . $name ); // phpcs:ignore
	list( , $base ) = flxai_media_dir();
	$out            = array(
		'url'    => $base . '/' . $name,
		'path'   => $dir . '/' . $name,
		'mime'   => $mime,
		'bytes'  => (int) filesize( $dir . '/' . $name ),
		'stored' => true,
	);
	if ( 0 === strpos( $mime, 'image/' ) ) {
		$sz = @getimagesize( $out['path'] ); // phpcs:ignore
		if ( $sz ) {
			$out['width']  = (int) $sz[0];
			$out['height'] = (int) $sz[1];
		}
	}
	return $out;
}

/**
 * Sağlayıcı çıktılarını kalıcı dosyalara çevirir; maskeli düzenlemede dışarıdaki pikseller korunur.
 */
function flxai_media_finish_outputs( array $row, array $outs, array $req ) {
	$done = array();
	foreach ( $outs as $o ) {
		$remote = isset( $o['url'] ) && empty( $o['private'] ) ? (string) $o['url'] : '';
		$f      = isset( $o['bytes'] ) ? flxai_media_write( $o['bytes'], (string) ( $o['mime'] ?? '' ) ) : flxai_media_download( $o, $row['provider'] );
		if ( is_wp_error( $f ) ) {
			if ( '' !== $remote ) {
				$done[] = array(
					'url'    => $remote,
					'mime'   => (string) ( $o['mime'] ?? '' ),
					'remote' => $remote,
					'stored' => false,
				);
			}
			continue;
		}
		$f['remote'] = 0 === strpos( $remote, 'data:' ) ? '' : $remote;
		$done[]      = $f;
	}
	// Maske: OpenAI kendi uygular; diğer sağlayıcılarda sonuç özgün görselle birleştirilir.
	if ( 'image' === $row['kind'] && ! empty( $req['mask'] ) && 'openai' !== $row['provider'] && ! empty( $req['refs'][0] ) ) {
		$mask = flxai_media_ref_bytes( $req['mask'] );
		$orig = flxai_media_ref_bytes( $req['refs'][0] );
		if ( ! is_wp_error( $mask ) && ! is_wp_error( $orig ) ) {
			foreach ( $done as $i => $f ) {
				if ( ! empty( $f['stored'] ) && 0 === strpos( $f['mime'], 'image/' ) ) {
					$done[ $i ]['protected'] = flxai_media_protect( $f['path'], $orig[0], $mask[0] );
				}
			}
		}
	}
	return $done;
}

/**
 * Maske dışındaki pikselleri özgün görselden geri yazar (maske: saydam = düzenlenen alan).
 * Imagick varsa onunla, yoksa GD ile (en çok ~16 MP). Başarıda true.
 */
function flxai_media_protect( $path, $orig_bytes, $mask_bytes ) {
	if ( class_exists( 'Imagick' ) ) {
		try {
			$res = new Imagick( $path );
			$w   = $res->getImageWidth();
			$h   = $res->getImageHeight();
			$org = new Imagick();
			$org->readImageBlob( $orig_bytes );
			$org->resizeImage( $w, $h, Imagick::FILTER_LANCZOS, 1 );
			$msk = new Imagick();
			$msk->readImageBlob( $mask_bytes );
			$msk->resizeImage( $w, $h, Imagick::FILTER_LANCZOS, 1 );
			$msk->setImageAlphaChannel( Imagick::ALPHACHANNEL_EXTRACT ); // opak (korunacak) = beyaz
			$org->setImageAlphaChannel( Imagick::ALPHACHANNEL_SET );
			$org->compositeImage( $msk, Imagick::COMPOSITE_COPYOPACITY, 0, 0 );
			$res->compositeImage( $org, Imagick::COMPOSITE_OVER, 0, 0 );
			$res->writeImage( $path );
			return true;
		} catch ( Exception $e ) {
			unset( $e );
		}
	}
	if ( ! function_exists( 'imagecreatefromstring' ) ) {
		return false;
	}
	$res = @imagecreatefromstring( (string) file_get_contents( $path ) ); // phpcs:ignore
	$org = @imagecreatefromstring( $orig_bytes ); // phpcs:ignore
	$msk = @imagecreatefromstring( $mask_bytes ); // phpcs:ignore
	if ( ! $res || ! $org || ! $msk ) {
		return false;
	}
	foreach ( array( $res, $org, $msk ) as $im ) {
		if ( ! imageistruecolor( $im ) ) {
			imagepalettetotruecolor( $im );
		}
	}
	$w = imagesx( $res );
	$h = imagesy( $res );
	if ( $w * $h > 16000000 ) {
		return false;
	}
	foreach ( array( 'org', 'msk' ) as $v ) {
		if ( imagesx( $$v ) !== $w || imagesy( $$v ) !== $h ) {
			$t = imagecreatetruecolor( $w, $h );
			imagealphablending( $t, false );
			imagesavealpha( $t, true );
			imagecopyresampled( $t, $$v, 0, 0, 0, 0, $w, $h, imagesx( $$v ), imagesy( $$v ) );
			$$v = $t;
		}
	}
	imagealphablending( $res, false );
	imagesavealpha( $res, true );
	for ( $y = 0; $y < $h; $y++ ) {
		for ( $x = 0; $x < $w; $x++ ) {
			$a    = ( imagecolorat( $msk, $x, $y ) >> 24 ) & 0x7F; // 0 opak (korunur) … 127 saydam (düzenlenir)
			if ( 127 === $a ) {
				continue;
			}
			$o = imagecolorat( $org, $x, $y );
			if ( 0 === $a ) {
				imagesetpixel( $res, $x, $y, $o & 0xFFFFFF );
				continue;
			}
			$n = imagecolorat( $res, $x, $y );
			$k = $a / 127; // yeni görselin payı
			$c = 0;
			foreach ( array( 16, 8, 0 ) as $sh ) {
				$c |= ( (int) round( ( ( $n >> $sh ) & 0xFF ) * $k + ( ( $o >> $sh ) & 0xFF ) * ( 1 - $k ) ) ) << $sh;
			}
			imagesetpixel( $res, $x, $y, $c );
		}
	}
	$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	$ok  = 'jpg' === $ext ? imagejpeg( $res, $path, 92 ) : ( 'webp' === $ext && function_exists( 'imagewebp' ) ? imagewebp( $res, $path, 92 ) : imagepng( $res, $path ) );
	imagedestroy( $res );
	return (bool) $ok;
}

/* ---------------------------------------------------------------------------
 * REST: tarayıcı polling (gönderim çağıran eklentinin kendi uç noktasından yapılır)
 * ------------------------------------------------------------------------- */

/** İşi görebilir mi: sahibi ya da yönetici (filtre: flxai_media_can_view). */
function flxai_media_can_view( array $row ) {
	$ok = (int) $row['user_id'] === get_current_user_id() || flxai_can_manage();
	return (bool) apply_filters( 'flxai_media_can_view', $ok, flxai_media_public( $row ) );
}

add_action(
	'rest_api_init',
	function () {
		$cb = function ( $fn ) {
			return function ( WP_REST_Request $r ) use ( $fn ) {
				$row = flxai_media_job_get( (string) $r['job'] );
				if ( ! $row || ! flxai_media_can_view( $row ) ) {
					return new WP_Error( 'flxai_media_job', __( 'Job not found.', 'flx-ai' ), array( 'status' => 404 ) );
				}
				$res = $fn( $row['job'] );
				return is_wp_error( $res ) ? $res : rest_ensure_response( $res );
			};
		};
		$in = function () {
			return is_user_logged_in();
		};
		register_rest_route(
			'flx-ai/v1',
			'/media/(?P<job>flxm_[a-f0-9]{24})',
			array(
				'methods'             => 'GET',
				'callback'            => $cb( 'flxai_media_status' ),
				'permission_callback' => $in,
			)
		);
		register_rest_route(
			'flx-ai/v1',
			'/media/(?P<job>flxm_[a-f0-9]{24})/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => $cb( 'flxai_media_cancel' ),
				'permission_callback' => $in,
			)
		);
	}
);

/* ---------------------------------------------------------------------------
 * Sohbet aracı: görsel üret (0.20.0)
 * ------------------------------------------------------------------------- */

/**
 * Üretilen görseli medya kütüphanesine ekler (dosya zaten uploads altında; kopyalanmaz).
 *
 * @return int Ek kimliği ya da 0.
 */
function flxai_media_to_library( array $out, $title = '', $alt = '' ) {
	if ( empty( $out['path'] ) || ! is_readable( $out['path'] ) || 0 !== strpos( (string) ( $out['mime'] ?? '' ), 'image/' ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = wp_insert_attachment(
		array(
			'post_mime_type' => $out['mime'],
			'post_title'     => sanitize_text_field( '' !== $title ? $title : __( 'AI image', 'flx-ai' ) ),
			'post_status'    => 'inherit',
			'guid'           => $out['url'],
		),
		$out['path']
	);
	if ( ! $id || is_wp_error( $id ) ) {
		return 0;
	}
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $out['path'] ) );
	if ( '' !== $alt ) {
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
	}
	update_post_meta( $id, '_flxai_generated', 1 );
	return (int) $id;
}

/** Görsel üretimi için hazır bir sağlayıcı var mı? */
function flxai_media_image_ready() {
	$p = flxai_media_providers();
	$m = $p['models'][ $p['default_image'] ] ?? null;
	return $m && ! empty( $m['providers'] );
}

add_filter(
	'flxai_tools',
	function ( $tools ) {
		if ( ! flxai_setting( 'image_tool_on', 1 ) || ! flxai_media_image_ready() ) {
			return $tools;
		}
		$tools['flxai_image_generate'] = array(
			'title'       => __( 'Generate image', 'flx-ai' ),
			'description' => 'Metinden görsel üretir (Flux AI › Görsel ve video ayarındaki varsayılan model) ve medya kütüphanesine ekler. attachment_id döner: yazının öne çıkan görseli için featured_image_id olarak, gövdede ![alt](url) olarak kullan. İstem İngilizce ve ayrıntılı olsun (konu, ortam, ışık, kompozisyon); yazı/logo isteme. Her çağrı ücretlidir: gerektiği kadar üret.',
			'readonly'    => true,
			'cap'         => 'upload_files',
			'source'      => 'flx-ai',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'prompt' => array( 'type' => 'string', 'description' => 'Görsel istemi' ),
					'aspect' => array( 'type' => 'string', 'description' => 'En-boy oranı, ör. 16:9 (öne çıkan görsel), 1:1, 4:5. Varsayılan 16:9.' ),
					'title'  => array( 'type' => 'string', 'description' => 'Medya kütüphanesindeki ad' ),
					'alt'    => array( 'type' => 'string', 'description' => 'Alt metin (sitenin dilinde)' ),
				),
				'required'   => array( 'prompt' ),
			),
			'run'         => function ( $a, $ctx = array() ) {
				$cat    = flxai_media_providers();
				$model  = $cat['default_image'];
				$aspect = (string) ( $a['aspect'] ?? '16:9' );
				$asps   = (array) ( $cat['models'][ $model ]['aspects'] ?? array() );
				$req    = array(
					'source'  => (string) ( $ctx['source'] ?? 'flx-ai' ),
					'purpose' => 'image_tool',
					'kind'    => 'image',
					'prompt'  => (string) ( $a['prompt'] ?? '' ),
					'n'       => 1,
					'user_id' => (int) ( $ctx['user_id'] ?? get_current_user_id() ),
				);
				if ( $asps && in_array( $aspect, $asps, true ) ) {
					$req['aspect'] = $aspect;
				}
				$r = flxai_media_submit( $req );
				// Kuyruklu sağlayıcı: en çok ~40 sn bekle (PHP ~60 sn'de kesilir).
				for ( $i = 0; ! is_wp_error( $r ) && in_array( $r['state'], array( 'queued', 'running' ), true ) && $i < 10; $i++ ) {
					sleep( 4 );
					$r = flxai_media_status( $r['job'] );
				}
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() ); // phpcs:ignore
				}
				if ( 'done' !== $r['state'] ) {
					return array(
						'state' => $r['state'],
						'job'   => $r['job'],
						'note'  => 'Görsel henüz hazır değil; biraz sonra aynı istemle yeniden deneme, iş sürüyor. Görselsiz devam edebilirsin.',
					);
				}
				$out = array();
				foreach ( $r['outputs'] as $o ) {
					$aid   = flxai_media_to_library( $o, (string) ( $a['title'] ?? '' ), (string) ( $a['alt'] ?? '' ) );
					$out[] = array(
						'attachment_id' => $aid,
						'url'           => $aid ? wp_get_attachment_url( $aid ) : (string) $o['url'],
						'width'         => (int) ( $o['width'] ?? 0 ),
						'height'        => (int) ( $o['height'] ?? 0 ),
					);
				}
				return array(
					'images' => $out,
					'cost'   => $r['cost'],
					'model'  => $r['model'],
				);
			},
		);
		return $tools;
	}
);
