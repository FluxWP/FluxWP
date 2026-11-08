<?php
/**
 * Yapay zekâ: üretim Flux AI medya API'si (flxai_media_*), Geliştir ve Beyin fırtınası flxai_chat ile.
 * İstem kuralları Banana Studio'dan uyarlandı (MIT, bkz. NOTICE.md).
 */

defined( 'ABSPATH' ) || exit;

function flxst_ai_ready() {
	return function_exists( 'flxai_media_submit' ) && function_exists( 'flxai_media_status' );
}

function flxst_text_ready() {
	return function_exists( 'flxai_chat' ) && function_exists( 'flxai_ready' ) && flxai_ready( 'flx-studio' );
}

/** Flux AI'nin model listesi (anahtar içermez). */
function flxst_models() {
	if ( ! function_exists( 'flxai_media_providers' ) ) {
		return array( 'providers' => array(), 'models' => array() );
	}
	$m = flxai_media_providers();
	return is_array( $m ) ? $m : array( 'providers' => array(), 'models' => array() );
}

/* ---------------------------------------------------------------------------
 * Üretim
 * ------------------------------------------------------------------------- */

/**
 * Üretim başlatır. Her çıktı için bir kayıt açılır (galeride yer tutucu), iş Flux AI'ye gönderilir.
 *
 * @return array|WP_Error items[]
 */
function flxst_generate( array $in, $user_id ) {
	if ( ! flxst_ai_ready() ) {
		return new WP_Error( 'flxst_ai', __( 'Flux AI (0.6.0+) is not installed or image generation is off.', 'flx-studio' ), array( 'status' => 503 ) );
	}
	$cat    = flxst_models();
	$s      = flxst_settings();
	$kind   = 'video' === ( $in['kind'] ?? '' ) ? 'video' : 'image';
	$model  = sanitize_text_field( (string) ( $in['model'] ?? '' ) );
	$model  = '' !== $model ? $model : (string) ( 'video' === $kind ? ( $s['video_model'] ? $s['video_model'] : ( $cat['default_video'] ?? '' ) ) : ( $s['image_model'] ? $s['image_model'] : ( $cat['default_image'] ?? '' ) ) );
	$m      = $cat['models'][ $model ] ?? null;
	if ( ! $m ) {
		return new WP_Error( 'flxst_model', __( 'Model not found. Check the keys in Flux AI & MCP › Settings › Images and video.', 'flx-studio' ), array( 'status' => 400 ) );
	}
	$kind   = ( $m['kind'] ?? 'image' ) === 'video' ? 'video' : 'image';
	$prompt = trim( (string) ( $in['prompt'] ?? '' ) );
	if ( '' === $prompt ) {
		return new WP_Error( 'flxst_prompt', __( 'Describe what to generate.', 'flx-studio' ), array( 'status' => 400 ) );
	}
	$max_n = max( 1, min( (int) $s['max_n'], (int) ( $m['max_n'] ?? 1 ) ) );
	$n     = 'video' === $kind ? 1 : max( 1, min( $max_n, (int) ( $in['n'] ?? 1 ) ) );
	$folder = (int) ( $in['folder'] ?? 0 );
	if ( ! flxst_folder_writable( $folder, $user_id ) ) {
		$folder = 0;
	}

	$res = flxst_resolve_refs( (array) ( $in['refs'] ?? array() ), $user_id, max( 1, (int) ( $m['max_refs'] ?? 14 ) ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	list( $inputs, $summary ) = $res;

	// Alan düzenleme: kaynak görsel refs[0], maske PNG (saydam = değişecek alan).
	$parent = (int) ( $in['parent'] ?? 0 );
	$mask   = '';
	if ( ! empty( $in['mask'] ) ) {
		$src = flxst_item( $parent );
		if ( ! flxst_item_visible( $src, $user_id ) || 'done' !== $src['state'] || 'image' !== $src['kind'] ) {
			return new WP_Error( 'flxst_edit', __( 'Image to edit not found.', 'flx-studio' ), array( 'status' => 400 ) );
		}
		if ( empty( $m['mask'] ) ) {
			return new WP_Error( 'flxst_edit', __( 'This model does not support area editing.', 'flx-studio' ), array( 'status' => 400 ) );
		}
		$d = flxst_parse_data_url( (string) $in['mask'], 20 );
		if ( ! $d || 'image/png' !== $d[1] ) {
			return new WP_Error( 'flxst_edit', __( 'Mask must be a PNG.', 'flx-studio' ), array( 'status' => 400 ) );
		}
		$mask = (string) $in['mask'];
		array_unshift( $inputs, flxst_ai_input( $src['url'], $src['path'] ) );
		array_unshift( $summary, array( 'type' => 'item', 'id' => $parent, 'role' => 'edit', 'thumb' => $src['thumb'] ? $src['thumb'] : $src['url'] ) );
	}

	$params = array(
		'aspect'     => sanitize_text_field( (string) ( $in['aspect'] ?? '' ) ),
		'resolution' => sanitize_text_field( (string) ( $in['resolution'] ?? '' ) ),
		'quality'    => sanitize_key( (string) ( $in['quality'] ?? '' ) ),
		'size'       => preg_match( '/^\d{3,4}x\d{3,4}$/', (string) ( $in['size'] ?? '' ) ) ? (string) $in['size'] : '',
		'n'          => $n,
		'refs'       => $summary,
		'edit'       => '' !== $mask,
		'layers'     => array_slice( (array) ( $in['layers'] ?? array() ), 0, 20 ),
		'enhanced'   => ! empty( $in['enhanced'] ),
		'draft'      => mb_substr( (string) ( $in['draft'] ?? '' ), 0, 4000 ),
		'idea'       => mb_substr( sanitize_text_field( (string) ( $in['idea'] ?? '' ) ), 0, 190 ),
		'requested_provider' => sanitize_key( (string) ( $in['provider'] ?? 'auto' ) ),
	);
	if ( 'video' === $kind ) {
		$params['duration'] = (int) ( $in['duration'] ?? 0 );
		$params['audio']    = ! isset( $in['audio'] ) || ! empty( $in['audio'] );
		$params['mode']     = sanitize_key( (string) ( $in['mode'] ?? 'auto' ) );
	}

	$batch = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) ( $in['batch'] ?? '' ) ) );
	$ids   = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$ids[] = flxst_item_insert(
			array(
				'user_id'   => $user_id,
				'folder_id' => $folder,
				'kind'      => $kind,
				'state'     => 'queued',
				'out_index' => $i,
				'batch'     => substr( $batch, 0, 32 ),
				'parent_id' => $parent,
				'title'     => mb_substr( sanitize_text_field( (string) ( $in['title'] ?? '' ) ), 0, 190 ),
				'prompt'    => $prompt,
				'model'     => $model,
				'params'    => flxst_json( $params ),
			)
		);
	}

	$req = array(
		'source'   => 'flx-studio',
		'purpose'  => '' !== $mask ? 'edit' : ( $batch ? 'brainstorm' : 'generate' ),
		'model'    => $model,
		'provider' => $params['requested_provider'] ? $params['requested_provider'] : 'auto',
		'prompt'   => $prompt,
		'refs'     => $inputs,
		'n'        => $n,
		'user_id'  => $user_id,
	);
	foreach ( array( 'aspect', 'resolution', 'quality', 'size' ) as $k ) {
		if ( '' !== $params[ $k ] ) {
			$req[ $k ] = $params[ $k ];
		}
	}
	if ( '' !== $mask ) {
		$req['mask'] = $mask;
	}
	if ( 'video' === $kind ) {
		if ( $params['duration'] ) {
			$req['duration'] = $params['duration'];
		}
		$req['audio'] = $params['audio'];
		$req['mode']  = $params['mode'];
		foreach ( array( 'video_refs', 'audio_refs' ) as $k ) {
			$req[ $k ] = array();
			foreach ( (array) ( $in[ $k ] ?? array() ) as $rid ) {
				$r = flxst_ref( (int) $rid );
				if ( flxst_ref_visible( $r, $user_id ) ) {
					$req[ $k ][] = $r['url'];
				}
			}
		}
	}
	// Kayıtlar hemen "sırada" döner; sağlayıcıya gönderim (45 sn sürebilir) cevap gittikten sonra yapılır.
	$tmp   = 'flxd_' . bin2hex( random_bytes( 8 ) );
	$guess = 'auto' !== $req['provider'] ? $req['provider'] : (string) ( $m['auto'] ?? '' );
	foreach ( $ids as $id ) {
		flxst_item_update( $id, array( 'job' => $tmp, 'provider' => $guess ) );
	}
	set_transient( 'flxst_' . $tmp, array( 'req' => $req, 'at' => time() ), HOUR_IN_SECONDS );
	flxst_defer( $tmp );
	return array_map( 'flxst_format_item', array_map( 'flxst_item', $ids ) );
}

/**
 * Gönderimi istek sonuna bırakır: cevap istemciye gider, bağlantı kapanır, sonra sağlayıcıya gönderilir.
 * Sunucu bağlantıyı erken kapatamıyorsa (finish_request yok) gönderim yine aynı istekte yapılır.
 */
function flxst_defer( $tmp ) {
	static $queue = null;
	if ( null === $queue ) {
		$queue = array();
		add_action(
			'shutdown',
			function () use ( &$queue ) {
				if ( ! $queue ) {
					return;
				}
				ignore_user_abort( true );
				if ( function_exists( 'set_time_limit' ) ) {
					@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}
				while ( ob_get_level() > 0 ) {
					@ob_end_flush(); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}
				flush();
				if ( function_exists( 'litespeed_finish_request' ) ) {
					litespeed_finish_request();
				} elseif ( function_exists( 'fastcgi_finish_request' ) ) {
					fastcgi_finish_request();
				}
				foreach ( $queue as $t ) {
					flxst_dispatch( $t );
				}
			},
			1
		);
	}
	$queue[] = $tmp;
}

/**
 * Bekleyen gönderimi Flux AI'ye iletir (bir kez; kilitli).
 *
 * @return string|null Gerçek iş kimliği (flxm_…) ya da null.
 */
function flxst_dispatch( $tmp ) {
	global $wpdb;
	$data = get_transient( 'flxst_' . $tmp );
	if ( ! is_array( $data ) || ! add_option( 'flxst_lock_' . $tmp, time(), '', 'no' ) ) {
		return null;
	}
	$t    = flxst_table( 'items' );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE job = %s", $tmp ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$live = array_filter( (array) $rows, function ( $r ) { return in_array( $r['state'], array( 'queued', 'running' ), true ); } );
	delete_transient( 'flxst_' . $tmp );
	if ( ! $live ) {
		delete_option( 'flxst_lock_' . $tmp );
		return null; // Gönderilmeden iptal edildi.
	}
	$job = flxai_media_submit( $data['req'] );
	if ( is_wp_error( $job ) ) {
		$err = (array) $job->get_error_data();
		$msg = $job->get_error_message();
		if ( ! empty( $err['maybe_charged'] ) ) {
			$msg .= ' ' . __( 'The request may have reached the provider; check the provider\'s history before sending again.', 'flx-studio' );
		}
		foreach ( $live as $r ) {
			flxst_item_update( (int) $r['id'], array( 'state' => 'failed', 'error' => $msg ) );
		}
		delete_option( 'flxst_lock_' . $tmp );
		return null;
	}
	// Gönderim sürerken iptal edildiyse sağlayıcıdaki iş de durdurulur.
	$cancelled = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE job = %s AND state = 'cancelled'", $tmp ) ); // phpcs:ignore WordPress.DB
	set_transient( 'flxst_map_' . $tmp, (string) $job['job'], DAY_IN_SECONDS );
	foreach ( $rows as $r ) {
		$f = array( 'job' => (string) $job['job'], 'provider' => (string) ( $job['provider'] ?? '' ) );
		if ( in_array( $r['state'], array( 'queued', 'running' ), true ) ) {
			$f['state'] = 'queued' === $job['state'] ? 'queued' : 'running';
		}
		flxst_item_update( (int) $r['id'], $f );
	}
	delete_option( 'flxst_lock_' . $tmp );
	if ( $cancelled && function_exists( 'flxai_media_cancel' ) ) {
		$st = flxai_media_cancel( (string) $job['job'] );
		$job = is_array( $st ) ? $st : $job;
	}
	flxst_apply_job( $job );
	return (string) $job['job'];
}

/**
 * Henüz gönderilmemiş (flxd_) iş: gerçek işe eşlenmişse onu döner; sunucu istek sonunda gönderemediyse
 * (bağlantı erken kapanmadı, süre doldu) 45 sn sonra sorgu sırasında gönderilir; süresi geçmişse başarısız sayılır.
 */
function flxst_resolve_pending( $tmp ) {
	global $wpdb;
	$real = get_transient( 'flxst_map_' . $tmp );
	if ( $real ) {
		return (string) $real;
	}
	$data = get_transient( 'flxst_' . $tmp );
	if ( is_array( $data ) ) {
		if ( time() - (int) $data['at'] > 45 ) {
			// Kilit 3 dk'dan eskiyse (yarıda kalmış gönderim) bırakılır.
			$lock = (int) get_option( 'flxst_lock_' . $tmp, 0 );
			if ( $lock && time() - $lock > 180 ) {
				delete_option( 'flxst_lock_' . $tmp );
			}
			return flxst_dispatch( $tmp ) ?? $tmp;
		}
		return $tmp;
	}
	$lock = (int) get_option( 'flxst_lock_' . $tmp, 0 );
	if ( ! $lock || time() - $lock > 180 ) {
		delete_option( 'flxst_lock_' . $tmp );
		$t = flxst_table( 'items' );
		$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET state = 'failed', error = %s WHERE job = %s AND state IN ('queued','running')", __( 'Sending did not complete, please try again.', 'flx-studio' ), $tmp ) ); // phpcs:ignore WordPress.DB
	}
	return $tmp;
}

/**
 * Flux AI iş durumunu kayıtlara işler. Bitti ise çıktılar sırayla kayıtlara yazılır.
 */
function flxst_apply_job( array $job ) {
	global $wpdb;
	if ( empty( $job['job'] ) ) {
		return;
	}
	$t    = flxst_table( 'items' );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE job = %s ORDER BY out_index", $job['job'] ), ARRAY_A ); // phpcs:ignore WordPress.DB
	if ( ! $rows ) {
		return;
	}
	$state = (string) ( $job['state'] ?? '' );
	if ( in_array( $state, array( 'queued', 'running' ), true ) ) {
		foreach ( $rows as $r ) {
			if ( in_array( $r['state'], array( 'queued', 'running' ), true ) ) {
				$p             = flxst_dec( $r['params'] );
				$p['progress'] = (float) ( $job['progress'] ?? 0 );
				flxst_item_update( (int) $r['id'], array( 'state' => $state, 'provider' => (string) ( $job['provider'] ?? $r['provider'] ), 'params' => flxst_json( $p ) ) );
			}
		}
		return;
	}
	if ( 'done' === $state ) {
		$outs = array_values( (array) ( $job['outputs'] ?? array() ) );
		$cost = count( $outs ) ? (float) ( $job['cost'] ?? 0 ) / count( $outs ) : 0;
		foreach ( $rows as $i => $r ) {
			if ( 'done' === $r['state'] ) {
				continue;
			}
			$o = $outs[ $i ] ?? null;
			if ( ! $o ) {
				flxst_item_update( (int) $r['id'], array( 'state' => 'failed', 'error' => __( 'The provider returned fewer results than requested.', 'flx-studio' ) ) );
				continue;
			}
			$path = (string) ( $o['path'] ?? '' );
			$p    = flxst_dec( $r['params'] );
			unset( $p['progress'] );
			if ( ! empty( $o['protected'] ) ) {
				$p['protected'] = true;
			}
			flxst_item_update(
				(int) $r['id'],
				array(
					'state'    => 'done',
					'provider' => (string) ( $job['provider'] ?? $r['provider'] ),
					'url'      => (string) ( $o['url'] ?? '' ),
					'path'     => $path,
					'thumb'    => flxst_make_thumb( $path, 'i' . $r['id'] ),
					'mime'     => (string) ( $o['mime'] ?? '' ),
					'width'    => (int) ( $o['width'] ?? 0 ),
					'height'   => (int) ( $o['height'] ?? 0 ),
					'bytes'    => (int) ( $o['bytes'] ?? 0 ),
					'cost'     => round( $cost, 4 ),
					'error'    => empty( $o['stored'] ) && empty( $path ) ? __( 'The file is hosted temporarily by the provider; add it to the Media Library or download it.', 'flx-studio' ) : '',
					'params'   => flxst_json( $p ),
				)
			);
		}
		// Sağlayıcı fazla çıktı verdiyse ek kayıt aç.
		$first = $rows[0];
		for ( $i = count( $rows ); $i < count( $outs ); $i++ ) {
			$o  = $outs[ $i ];
			$id = flxst_item_insert(
				array(
					'user_id'   => (int) $first['user_id'],
					'folder_id' => (int) $first['folder_id'],
					'kind'      => $first['kind'],
					'state'     => 'done',
					'job'       => $first['job'],
					'out_index' => $i,
					'batch'     => $first['batch'],
					'parent_id' => (int) $first['parent_id'],
					'title'     => $first['title'],
					'prompt'    => $first['prompt'],
					'model'     => $first['model'],
					'provider'  => (string) ( $job['provider'] ?? '' ),
					'params'    => $first['params'],
					'url'       => (string) ( $o['url'] ?? '' ),
					'path'      => (string) ( $o['path'] ?? '' ),
					'mime'      => (string) ( $o['mime'] ?? '' ),
					'width'     => (int) ( $o['width'] ?? 0 ),
					'height'    => (int) ( $o['height'] ?? 0 ),
					'bytes'     => (int) ( $o['bytes'] ?? 0 ),
					'cost'      => round( $cost, 4 ),
				)
			);
			flxst_item_update( $id, array( 'thumb' => flxst_make_thumb( (string) ( $o['path'] ?? '' ), 'i' . $id ) ) );
		}
		return;
	}
	// failed | cancelled
	$err = (string) ( $job['error'] ?? '' );
	if ( 'cancelled' === $state ) {
		$err = $err ? $err : __( 'Cancelled.', 'flx-studio' );
	}
	foreach ( $rows as $r ) {
		if ( 'done' !== $r['state'] ) {
			flxst_item_update( (int) $r['id'], array( 'state' => 'cancelled' === $state ? 'cancelled' : 'failed', 'error' => $err ? $err : __( 'Generation failed.', 'flx-studio' ) ) );
		}
	}
}

/* Flux AI işi kendi tarafında bitirirse (ör. kendi uç noktasından sorgulandıysa) kayıtları güncelle. */
add_action(
	'flxai_media_done',
	function ( $job ) {
		if ( is_array( $job ) && 'flx-studio' === ( $job['source'] ?? '' ) ) {
			flxst_apply_job( $job );
		}
	}
);

/**
 * Bekleyen işleri sorgular (en çok 8 iş). Flux AI sağlayıcıya en sık 3 sn'de bir sorar.
 * @return array items[] (değişenler dahil, istenen işlerin tüm kayıtları)
 */
function flxst_poll( array $jobs, $user_id ) {
	global $wpdb;
	$t   = flxst_table( 'items' );
	$out = array();
	foreach ( array_slice( array_unique( array_filter( array_map( 'strval', $jobs ) ) ), 0, 8 ) as $job ) {
		if ( ! preg_match( '/^flx[md]_[a-f0-9]{8,40}$/', $job ) ) {
			continue;
		}
		if ( 0 === strpos( $job, 'flxd_' ) ) {
			$job = flxst_resolve_pending( $job );
			if ( 0 === strpos( $job, 'flxd_' ) ) {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE job = %s ORDER BY out_index", $job ), ARRAY_A ); // phpcs:ignore WordPress.DB
				if ( $rows && flxst_item_visible( $rows[0], $user_id ) ) {
					foreach ( $rows as $r ) {
						$out[] = flxst_format_item( $r );
					}
				}
				continue;
			}
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE job = %s", $job ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( ! $rows || ! flxst_item_visible( $rows[0], $user_id ) ) {
			continue;
		}
		$pending = false;
		foreach ( $rows as $r ) {
			$pending = $pending || in_array( $r['state'], array( 'queued', 'running' ), true );
		}
		if ( $pending && flxst_ai_ready() ) {
			$st = flxai_media_status( $job );
			if ( ! is_wp_error( $st ) ) {
				flxst_apply_job( $st );
			}
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE job = %s ORDER BY out_index", $job ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $r ) {
			$out[] = flxst_format_item( $r );
		}
	}
	return $out;
}

/* ---------------------------------------------------------------------------
 * Metin: Geliştir, Beyin fırtınası
 * ------------------------------------------------------------------------- */

function flxst_rules_base() {
	return "You prepare production prompts for image and video generation models.\n"
		. "Write the final prompt in English, even when the draft is Turkish. Keep requested visible text, names and quoted dialogue in their original language and spelling, inside quotes.\n"
		. "Describe the intended result through subject, composition, visible detail, lighting and medium. Use the simplest readable format; detail should change the image, not praise its quality. Camera terms are optional appearance cues. Keep API settings (size, aspect ratio, number of images, model names) out of the prompt.\n"
		. "Assign each reference image a clear purpose. Identity photos establish who a person is, not the output medium; preserve likeness while following the requested pose, clothing and expression. Several photos of one person still mean one person.\n"
		. "Attaching a reference never means a local edit unless the task says EDIT. For a similar scene, develop a distinct composition instead of copying the reference.\n"
		. "Do not add sharpening, HDR, beauty retouching or extra saturation unless asked. Do not invent logos, extra text or people that were not requested.";
}

function flxst_rules_for( $mode ) {
	if ( 'edit' === $mode ) {
		return "TASK: EDIT. Image 1 is the approved source. Only the selected (masked) area may change. State the requested change precisely, then list what must stay identical (faces, text, layout, colors, lighting outside the area). Keep the result consistent with the surrounding pixels.";
	}
	if ( 'video' === $mode ) {
		return "TASK: VIDEO. Describe subject, setting and style, then the motion: what moves, how, camera movement, pacing and timing within the clip duration. Give one continuous shot unless cuts are requested. If first/last frame images are given, describe the transition between them.";
	}
	return 'TASK: NEW IMAGE. Create a complete new image from the brief.';
}

/** Referans özetinden modele açıklama (kişiler, roller). */
function flxst_refs_note( array $refs ) {
	if ( ! $refs ) {
		return '';
	}
	$lines = array( 'REFERENCES (in order):' );
	$n     = 1;
	foreach ( $refs as $r ) {
		if ( 'person' === ( $r['type'] ?? '' ) ) {
			$c       = max( 1, (int) ( $r['photos'] ?? 1 ) );
			$lines[] = sprintf( 'images %d-%d: identity photos of ONE person named "%s".', $n, $n + $c - 1, (string) ( $r['name'] ?? '' ) );
			$n      += $c;
		} else {
			$role    = (string) ( $r['role'] ?? '' );
			$lines[] = sprintf( 'image %d: %s', $n, $role ? $role . ' reference' : 'visual reference; use only the role the brief assigns' );
			++$n;
		}
	}
	return implode( "\n", $lines );
}

function flxst_chat( array $messages, $purpose, $max = 1500 ) {
	if ( ! flxst_text_ready() ) {
		return new WP_Error( 'flxst_ai', __( 'Flux AI text model is not ready (Flux AI & MCP › Settings).', 'flx-studio' ), array( 'status' => 503 ) );
	}
	$opts = array(
		'source'     => 'flx-studio',
		'purpose'    => $purpose,
		'max_tokens' => $max,
		'timeout'    => 50,
	);
	$model = (string) flxst_settings()['text_model'];
	if ( '' !== $model ) {
		$opts['model'] = $model;
	}
	$r = flxai_chat( $messages, $opts );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	return trim( (string) ( $r['content'] ?? '' ) );
}

/**
 * Taslağı modele uygun ayrıntılı isteme çevirir.
 * $mode: image | video | edit
 */
function flxst_enhance( $draft, $mode, array $refs, $model_label = '' ) {
	$draft = trim( (string) $draft );
	if ( '' === $draft ) {
		return new WP_Error( 'flxst_prompt', __( 'Write a short draft first.', 'flx-studio' ), array( 'status' => 400 ) );
	}
	$sys = flxst_rules_base() . "\n\n" . flxst_rules_for( $mode ) . "\n\nReturn ONLY the final prompt text, no preface, no markdown, no quotes around the whole prompt.";
	$usr = ( $model_label ? 'Target model: ' . $model_label . "\n" : '' ) . flxst_refs_note( $refs ) . "\n\nDRAFT:\n" . $draft;
	$out = flxst_chat(
		array(
			array( 'role' => 'system', 'content' => array( array( 'type' => 'text', 'text' => $sys, 'cache' => true ) ) ),
			array( 'role' => 'user', 'content' => trim( $usr ) ),
		),
		'enhance'
	);
	if ( is_wp_error( $out ) ) {
		return $out;
	}
	return trim( preg_replace( '/^```[a-z]*\s*|\s*```$/i', '', $out ) );
}

/**
 * Bir brief'ten N farklı fikir: [{title, prompt}].
 * $style: simple | youtube
 */
function flxst_brainstorm( $brief, $count, $style, array $refs, $mode = 'image' ) {
	$brief = trim( (string) $brief );
	if ( '' === $brief ) {
		return new WP_Error( 'flxst_prompt', __( 'Briefly describe the idea first.', 'flx-studio' ), array( 'status' => 400 ) );
	}
	$count = max( 1, min( 8, (int) $count ) );
	$extra = 'youtube' === $style
		? "STYLE: YouTube thumbnail concepts. Readable at small size: one clear focal subject, camera-facing people with strong expressions, high contrast, at most 3-4 words of large headline text when text helps, uncluttered background. Vary composition, emotion and color plan across ideas; vary wardrobe across ideas unless the brief fixes it. 16:9 framing."
		: 'STYLE: general concepts. Make the ideas clearly different from each other (composition, viewpoint, mood, palette), all faithful to the brief.';
	$sys = flxst_rules_base() . "\n\n" . flxst_rules_for( $mode ) . "\n\n" . $extra
		. "\n\nReturn ONLY JSON: {\"ideas\":[{\"title\":\"short Turkish title (max 6 words)\",\"prompt\":\"full English production prompt\"}]} with exactly " . $count . ' ideas.';
	$out = flxst_chat(
		array(
			array( 'role' => 'system', 'content' => array( array( 'type' => 'text', 'text' => $sys, 'cache' => true ) ) ),
			array( 'role' => 'user', 'content' => trim( flxst_refs_note( $refs ) . "\n\nBRIEF:\n" . $brief ) ),
		),
		'brainstorm',
		600 + 450 * $count
	);
	if ( is_wp_error( $out ) ) {
		return $out;
	}
	if ( preg_match( '/\{.*\}/s', $out, $m ) ) {
		$out = $m[0];
	}
	$d     = json_decode( $out, true );
	$ideas = array();
	foreach ( (array) ( $d['ideas'] ?? array() ) as $i ) {
		$p = trim( (string) ( $i['prompt'] ?? '' ) );
		if ( '' !== $p ) {
			$ideas[] = array(
				'title'  => mb_substr( sanitize_text_field( (string) ( $i['title'] ?? '' ) ), 0, 120 ),
				'prompt' => $p,
			);
		}
	}
	if ( ! $ideas ) {
		return new WP_Error( 'flxst_ai', __( 'The model did not return a valid idea list. Try again.', 'flx-studio' ), array( 'status' => 502 ) );
	}
	return array_slice( $ideas, 0, $count );
}
