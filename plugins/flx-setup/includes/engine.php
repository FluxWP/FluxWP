<?php
/**
 * Motor: planla (önizle) ve uygula.
 *
 * Her yazılabilir birim bir "hedef"tir:
 *   profile:<yol>              flx_profile option'ı
 *   wp:<option>                WordPress çekirdek option'ı (blogname, page_on_front…)
 *   option:<option>:<yol>      eklenti ayarı (option dizisi içindeki yol)
 *   content:<tür>:<key>        içerik (sayfa, şablon, parça, stil, menü, paket türleri)
 *   media:<key>                medya kütüphanesi dosyası
 *
 * Durumlar: add (yok, eklenecek) · change (değişecek) · same (zaten aynı) · keep (dolu, "yalnız boşları doldur"da
 * dokunulmaz) · manual (panelden elle değiştirilmiş, atlanır; zorla uygulanabilir) · skip (tür yok/hata).
 * Elle değişiklik tespiti: motor her hedefe son yazdığı değerin hash'ini tutar (state.owned). Sitedeki değer
 * bu hash'ten farklıysa kullanıcı değiştirmiştir.
 *
 * Her uygulama bir "çalıştırma"dır (run): yazılan hedeflerin eski değerleri ve oluşturulan içerikler run kaydında
 * saklanır, run tek başına geri alınabilir. Ön ayarı kaldırmak = o ön ayarın run'larını tersten geri almak.
 */

defined( 'ABSPATH' ) || exit;

const FLXK_ABSENT = '__flxk_absent__';

/** Ön ayarın taban kısmı (migration'lar hariç). */
function flxk_base_of( array $p ) {
	unset( $p['migrations'] );
	return $p;
}

/** Ön ayar/yamadaki tema bölümünü içerik öğelerine çevirir. */
function flxk_theme_items( array $p ) {
	$items = array();
	$th    = isset( $p['theme'] ) && is_array( $p['theme'] ) ? $p['theme'] : array();
	$need  = (string) ( $p['requires']['theme'] ?? '' );
	if ( '' !== $need && get_stylesheet() !== $need && get_template() !== $need ) {
		return $items; // Şablonlar başka tema için: bu temada yazılmaz (panelde "eksik: tema" uyarısı).
	}
	if ( ! empty( $th['styles'] ) && is_array( $th['styles'] ) ) {
		$items[] = array(
			'type' => 'styles',
			'key'  => 'user',
			'data' => $th['styles'],
		);
	}
	foreach ( array(
		'templates' => 'template',
		'parts'     => 'part',
	) as $sec => $type ) {
		foreach ( (array) ( $th[ $sec ] ?? array() ) as $slug => $def ) {
			$def     = is_array( $def ) && ( isset( $def['body'] ) || isset( $def['title'] ) ) ? $def : array( 'body' => $def );
			$items[] = array(
				'type' => $type,
				'key'  => (string) $slug,
			) + $def;
		}
	}
	return $items;
}

/* ---------- Yer tutucular ---------- */

/**
 * Bağlam: profil (sitedeki + şablondaki), oluşturulan kayıtların ID'leri.
 */
function flxk_context( array $p, $preset_id = '' ) {
	return array(
		'preset'  => (string) $preset_id,
		'profile' => flxk_merge( flxk_profile_all(), (array) ( $p['profile'] ?? array() ) ),
		'refs'    => array(),
		'dry'     => false,
		'dir'     => $p['dir'] ?? '',
	);
}

function flxk_ref_id( $type, $key, array &$ctx ) {
	if ( isset( $ctx['refs'][ $type ][ $key ] ) ) {
		return (int) $ctx['refs'][ $type ][ $key ];
	}
	if ( 'media' === $type ) {
		return flxk_media_find( $key, $ctx['preset'] ?? '' );
	}
	$imp = flxk_importers();
	if ( empty( $imp[ $type ]['find'] ) ) {
		return 0;
	}
	$id                              = (int) call_user_func( $imp[ $type ]['find'], array( 'key' => $key ) );
	$ctx['refs'][ $type ][ $key ] = $id;
	return $id;
}

function flxk_ref_url( $type, $key, array &$ctx ) {
	$id = flxk_ref_id( $type, $key, $ctx );
	if ( ! $id ) {
		return '#';
	}
	if ( 'media' === $type ) {
		return (string) wp_get_attachment_url( $id );
	}
	$imp = flxk_importers();
	if ( ! empty( $imp[ $type ]['url'] ) ) {
		return (string) call_user_func( $imp[ $type ]['url'], $id );
	}
	return '#';
}

/** Değerdeki yer tutucuları çözer (diziler dahil). */
function flxk_resolve( $v, array &$ctx ) {
	if ( is_array( $v ) ) {
		foreach ( $v as $k => $x ) {
			$v[ $k ] = flxk_resolve( $x, $ctx );
		}
		return $v;
	}
	if ( ! is_string( $v ) || '' === $v ) {
		return $v;
	}
	if ( preg_match( '/^@([a-z_]+):([A-Za-z0-9_\-\/]+)$/', $v, $m ) ) {
		$id = flxk_ref_id( $m[1], $m[2], $ctx );
		return ( ! $id && ! empty( $ctx['plan'] ) ) ? $v : $id; // Önizlemede henüz oluşmamış kayıt adıyla görünür.
	}
	if ( false === strpos( $v, '{' ) ) {
		return $v;
	}
	return preg_replace_callback(
		'/\{(url:@([a-z_]+):([A-Za-z0-9_\-\/]+)|id:@([a-z_]+):([A-Za-z0-9_\-\/]+)|profile\.([a-z0-9_.]+)|domain|home|site_name|year)\}/',
		function ( $m ) use ( &$ctx ) {
			if ( ! empty( $m[2] ) ) {
				return flxk_ref_url( $m[2], $m[3], $ctx );
			}
			if ( ! empty( $m[4] ) ) {
				return (string) flxk_ref_id( $m[4], $m[5], $ctx );
			}
			if ( ! empty( $m[6] ) ) {
				$pv = flxk_path_get( $ctx['profile'], $m[6], '' );
				return is_scalar( $pv ) ? (string) $pv : '';
			}
			switch ( $m[1] ) {
				case 'domain':
					return flxk_domain();
				case 'home':
					return untrailingslashit( home_url() );
				case 'site_name':
					return (string) flxk_path_get( $ctx['profile'], 'name', get_bloginfo( 'name' ) );
				case 'year':
					return wp_date( 'Y' );
			}
			return $m[0];
		},
		$v
	);
}

/* ---------- Medya ---------- */

/** Medya: önce bu ön ayarın anahtarı ("<ön ayar>:<key>"), yoksa herhangi bir ön ayarın aynı anahtarı. */
function flxk_media_find( $key, $preset_id = '' ) {
	$keys = $preset_id ? array( $preset_id . ':' . $key, $key ) : array( $key );
	foreach ( $keys as $k ) {
		$q = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_key'       => '_flxk_media', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => (string) $k, // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		if ( $q ) {
			return (int) $q[0];
		}
		if ( ! $preset_id ) {
			break;
		}
	}
	if ( $preset_id && false === strpos( $key, ':' ) ) {
		// Başka ön ayarın medyası: "<diğer>:<key>".
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_flxk_media' AND meta_value LIKE %s LIMIT 1", '%:' . $wpdb->esc_like( $key ) ) ); // phpcs:ignore
		return $id;
	}
	return 0;
}

/** Medya öğesini kütüphaneye alır. Yerel dosya (şablon klasörü) ya da URL. */
function flxk_media_import( $key, $def, $dir, $preset_id = '' ) {
	$def = is_array( $def ) ? $def : array( 'file' => $def );
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp   = '';
	$name  = '';
	$local = false;
	if ( ! empty( $def['file'] ) && $dir ) {
		$path = flxk_safe_path( $dir, (string) $def['file'] );
		if ( ! $path ) {
			/* translators: %s: dosya */
			return new WP_Error( 'media', sprintf( __( 'Media file missing: %s', 'flx-setup' ), $def['file'] ) );
		}
		$tmp   = wp_tempnam( basename( $path ) );
		copy( $path, $tmp );
		$name  = basename( $path );
		$local = true;
	} elseif ( ! empty( $def['url'] ) ) {
		$tmp = download_url( esc_url_raw( (string) $def['url'] ), 30 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		$name = basename( (string) wp_parse_url( (string) $def['url'], PHP_URL_PATH ) );
	} else {
		return new WP_Error( 'media', __( 'Media needs "file" or "url".', 'flx-setup' ) );
	}
	// SVG yalnız şablonun kendi klasöründen (güvenilir dosya) kabul edilir.
	$svg = function ( $m ) {
		$m['svg'] = 'image/svg+xml';
		return $m;
	};
	$chk = function ( $data, $file, $filename ) {
		if ( preg_match( '/\.svg$/i', $filename ) ) {
			return array(
				'ext'             => 'svg',
				'type'            => 'image/svg+xml',
				'proper_filename' => false,
			);
		}
		return $data;
	};
	if ( $local ) {
		add_filter( 'upload_mimes', $svg );
		add_filter( 'wp_check_filetype_and_ext', $chk, 10, 3 );
	}
	$id = media_handle_sideload(
		array(
			'name'     => $name,
			'tmp_name' => $tmp,
		),
		0,
		(string) ( $def['title'] ?? '' )
	);
	if ( $local ) {
		remove_filter( 'upload_mimes', $svg );
		remove_filter( 'wp_check_filetype_and_ext', $chk, 10 );
	}
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return $id;
	}
	update_post_meta( $id, '_flxk_media', ( $preset_id ? $preset_id . ':' : '' ) . $key );
	if ( ! empty( $def['alt'] ) ) {
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $def['alt'] ) );
	}
	return (int) $id;
}

/* ---------- Plan ---------- */

/**
 * Bir hedefin durumunu belirler.
 *
 * @param array $st     ön ayarın durumu
 * @param array $opts   mode (apply|fill), force (true ya da hedef listesi)
 */
function flxk_decide( $preset_id, $target, $exists, $cur, $new, array $st, array $opts, $same = null ) {
	$same = null === $same ? ( $exists && flxk_hash( $cur ) === flxk_hash( $new ) ) : $same;
	$op   = array(
		'target' => $target,
		'old'    => $exists ? $cur : null,
		'new'    => $new,
	);
	list( $other ) = flxk_owner_of( $target, $preset_id );
	if ( $other ) {
		$op['conflict'] = $other;
	}
	$force = ! empty( $opts['force'] ) && ( true === $opts['force'] || in_array( $target, (array) $opts['force'], true ) );
	if ( $same ) {
		$op['status'] = 'same';
	} elseif ( isset( $st['owned'][ $target ] ) ) {
		$op['status'] = ( flxk_hash( $exists ? $cur : FLXK_ABSENT ) !== $st['owned'][ $target ] && ! $force ) ? 'manual' : 'change';
	} elseif ( ! $exists || flxk_is_empty( $cur ) ) {
		$op['status'] = 'add';
	} elseif ( 'fill' === ( $opts['mode'] ?? 'apply' ) && ! $force ) {
		$op['status'] = 'keep';
	} else {
		$op['status'] = 'change';
	}
	$op['apply'] = in_array( $op['status'], array( 'add', 'change' ), true );
	return $op;
}

/**
 * Şablonun (ya da yamanın) siteye etkisini çıkarır. Hiçbir şey yazmaz.
 *
 * @return array{ops: array, warnings: string[]}
 */
function flxk_plan( $preset_id, array $p, array $opts = array() ) {
	$st   = flxk_state( $preset_id );
	$ctx  = flxk_context( $p, $preset_id );
	$ctx['plan'] = true;
	$ops  = array();
	$warn = array();

	foreach ( (array) ( $p['media'] ?? array() ) as $key => $def ) {
		$id                              = flxk_media_find( $preset_id . ':' . $key );
		$ctx['refs']['media'][ $key ] = $id;
		$op                              = flxk_decide( $preset_id, 'media:' . $key, (bool) $id, $id ? 'id:' . $id : null, basename( (string) ( is_array( $def ) ? ( $def['file'] ?? ( $def['url'] ?? '' ) ) : $def ) ), $st, $opts, (bool) $id );
		$op                             += array(
			'section' => 'media',
			'def'     => $def,
		);
		$ops[]                           = $op;
	}

	foreach ( flxk_flatten( (array) ( $p['profile'] ?? array() ) ) as $path => $v ) {
		$all   = flxk_profile_all();
		$cur   = flxk_path_get( $all, $path, FLXK_ABSENT );
		$new   = flxk_resolve( $v, $ctx );
		$ops[] = flxk_decide( $preset_id, 'profile:' . $path, FLXK_ABSENT !== $cur, $cur, $new, $st, $opts ) + array( 'section' => 'profile' );
	}

	$imp   = flxk_importers();
	$items = array_merge( flxk_theme_items( $p ), (array) ( $p['content'] ?? array() ) );
	foreach ( $items as $item ) {
		$type = (string) ( $item['type'] ?? '' );
		$key  = (string) ( $item['key'] ?? '' );
		if ( ! isset( $imp[ $type ] ) ) {
			/* translators: %s: tür */
			$warn[] = sprintf( __( 'Content type "%s" does not exist on this site, skipped.', 'flx-setup' ), $type );
			continue;
		}
		$T        = $imp[ $type ];
		$resolved = flxk_resolve( $item, $ctx );
		$id       = (int) call_user_func( $T['find'], $resolved );
		$cur      = $id ? (array) call_user_func( $T['read'], $id ) : array();
		$new      = (array) call_user_func( $T['data'], $resolved );
		$same     = $id ? ( isset( $T['same'] ) ? (bool) call_user_func( $T['same'], $new, $cur ) : flxk_hash( $new ) === flxk_hash( $cur ) ) : false;
		$op       = flxk_decide( $preset_id, 'content:' . $type . ':' . $key, (bool) $id, $cur, $new, $st, $opts, $same );
		$op      += array(
			'section' => 'content',
			'type'    => $type,
			'key'     => $key,
			'id'      => $id,
			'item'    => $item,
			'label'   => $T['label'] ?? $type,
		);
		foreach ( array( 'content' ) as $f ) {
			if ( isset( $new[ $f ] ) && is_string( $new[ $f ] ) && false !== strpos( $new[ $f ], '<!-- wp:' ) ) {
				foreach ( flxk_check_markup( $new[ $f ] ) as $w ) {
					$warn[] = $type . ':' . $key . ' · ' . $w;
				}
			}
		}
		$ops[] = $op;
	}

	foreach ( (array) ( $p['wp'] ?? array() ) as $name => $v ) {
		if ( ! flxk_option_allowed( $name ) ) {
			/* translators: %s: option */
			$warn[] = sprintf( __( '"%s" cannot be written, skipped.', 'flx-setup' ), $name );
			continue;
		}
		$exists = false !== get_option( $name, false );
		$cur    = get_option( $name );
		$new    = flxk_resolve( $v, $ctx );
		if ( 'WPLANG' === $name && '' !== $new && 'en_US' !== $new && ! in_array( $new, get_available_languages(), true ) ) {
			/* translators: %s: dil kodu */
			$warn[] = sprintf( __( 'The %s language pack is not installed; the site language was not set (Settings › General).', 'flx-setup' ), $new );
			continue;
		}
		$ops[]  = flxk_decide( $preset_id, 'wp:' . $name, $exists, $cur, $new, $st, $opts, $exists && (string) wp_json_encode( $cur ) === (string) wp_json_encode( $new ) || ( $exists && is_scalar( $cur ) && is_scalar( $new ) && (string) $cur === (string) $new ) ) + array( 'section' => 'wp' );
	}

	foreach ( (array) ( $p['settings'] ?? array() ) as $opt => $tree ) {
		if ( ! flxk_option_allowed( $opt ) || ! is_array( $tree ) ) {
			continue;
		}
		$stored = get_option( $opt, array() );
		$stored = is_array( $stored ) ? $stored : array();
		foreach ( flxk_flatten( $tree ) as $path => $v ) {
			if ( flxk_path_is_secret( $path ) ) {
				continue;
			}
			$w = flxk_settings_path_warning( $opt, $path );
			if ( $w ) {
				$warn[] = $w;
			}
			$cur   = flxk_path_get( $stored, $path, FLXK_ABSENT );
			$new   = flxk_resolve( $v, $ctx );
			$new   = flxk_is_keyed_list( $new ) && FLXK_ABSENT !== $cur ? flxk_merge( $cur, $new ) : $new;
			$same  = FLXK_ABSENT !== $cur && ( flxk_hash( $cur ) === flxk_hash( $new ) || ( is_scalar( $cur ) && is_scalar( $new ) && (string) $cur === (string) $new ) );
			$ops[] = flxk_decide( $preset_id, 'option:' . $opt . ':' . $path, FLXK_ABSENT !== $cur, FLXK_ABSENT === $cur ? null : $cur, $new, $st, $opts, $same ) + array(
				'section' => 'settings',
				'option'  => $opt,
				'path'    => $path,
			);
		}
	}

	return array(
		'ops'      => $ops,
		'warnings' => array_values( array_unique( array_merge( $warn, flxk_validate_warnings() ) ) ),
	);
}

/** Plan özeti: durum => sayı. */
function flxk_plan_counts( array $plan ) {
	$c = array();
	foreach ( $plan['ops'] as $op ) {
		$c[ $op['status'] ] = ( $c[ $op['status'] ] ?? 0 ) + 1;
	}
	return $c;
}

/* ---------- Uygula ---------- */

/**
 * Şablonu (ya da yamayı) uygular, bir run kaydı döner.
 *
 * @param array $meta kind (base|migration|patch), title, source (panel|ai|mcp), migration
 * @return array|WP_Error run özeti
 */
function flxk_execute( $preset_id, array $p, array $opts = array(), array $meta = array() ) {
	$st  = flxk_state( $preset_id ) + array(
		'owned'      => array(),
		'migrations' => array(),
		'runs'       => array(),
	);
	$ctx = flxk_context( $p, $preset_id );
	$run = array(
		'id'      => wp_generate_uuid4(),
		'preset'  => $preset_id,
		'kind'    => $meta['kind'] ?? 'base',
		'title'   => $meta['title'] ?? '',
		'source'  => $meta['source'] ?? 'panel',
		'mig'     => $meta['migration'] ?? '',
		'backup'  => array(),
		'created' => array(),
		'written' => array(),
		'errors'  => array(),
	);

	// 1. aşama: profil, medya, içerik. 2. aşama: wp ve ayarlar yeniden planlanır (yeni sayfaların ID'leri çözülsün).
	$first  = $p;
	unset( $first['wp'], $first['settings'] );
	$second = array_intersect_key( $p, array_flip( array( 'format', 'profile', 'wp', 'settings', 'dir' ) ) );
	$counts = array();

	$plan1 = flxk_plan( $preset_id, $first, $opts );
	flxk_execute_ops( $plan1['ops'], $p, $ctx, $run, $st );
	if ( ! empty( $second['wp'] ) || ! empty( $second['settings'] ) ) {
		flxk_save_state( $preset_id, $st ); // Profil hedefleri artık sahipli; 2. planda tekrar sayılmasın.
		$plan2 = flxk_plan( $preset_id, array_diff_key( $second, array( 'profile' => 1 ) ), $opts );
		flxk_execute_ops( $plan2['ops'], $p, $ctx, $run, $st );
		$plan1['ops'] = array_merge( $plan1['ops'], $plan2['ops'] );
	}
	$counts = flxk_plan_counts( $plan1 );

	$st['runs'][]     = $run['id'];
	$st['applied_at'] = $st['applied_at'] ?? current_time( 'mysql' );
	$st['updated_at'] = current_time( 'mysql' );
	if ( ! empty( $p['version'] ) ) {
		$st['ver'] = (string) $p['version'];
	}
	if ( 'migration' === $run['kind'] && ! empty( $meta['migration'] ) ) {
		$st['migrations'][ $meta['migration'] ] = array(
			'at'     => current_time( 'mysql' ),
			'status' => 'applied',
		);
	}
	flxk_save_state( $preset_id, $st );
	update_option( 'flxk_run_' . $run['id'], $run, false );

	$summary = array(
		'run'     => $run['id'],
		'preset'  => $preset_id,
		'kind'    => $run['kind'],
		'title'   => $run['title'],
		'source'  => $run['source'],
		'written' => count( $run['written'] ),
		'counts'  => $counts,
		'errors'  => $run['errors'],
	);
	flxk_log( $summary );
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
	do_action( 'flxk_applied', $preset_id, $summary );
	do_action( 'litespeed_purge_all' );
	return $summary;
}

/** Plan adımlarını uygular; run ve durum kaydını günceller. */
function flxk_execute_ops( array $ops, array $p, array &$ctx, array &$run, array &$st ) {
	$profile_dirty = false;
	$profile       = flxk_profile_all();
	$options       = array();

	foreach ( $ops as $op ) {
		$t = $op['target'];
		if ( 'same' === $op['status'] && ! isset( $st['owned'][ $t ] ) ) {
			$st['owned'][ $t ] = flxk_target_hash( $t ); // Aynı değer: artık bu ön ayarın sayılır.
		}
		if ( 'content' === $op['section'] && $op['id'] ) {
			$ctx['refs'][ $op['type'] ][ $op['key'] ] = $op['id'];
		}
		if ( 'media' === $op['section'] && preg_match( '/^id:(\d+)$/', (string) $op['old'], $m ) ) {
			$ctx['refs']['media'][ substr( $t, 6 ) ] = (int) $m[1];
		}
		if ( empty( $op['apply'] ) ) {
			continue;
		}
		switch ( $op['section'] ) {
			case 'profile':
				$path                 = substr( $t, 8 );
				$new                  = flxk_resolve( flxk_path_get( (array) $p['profile'], $path ), $ctx );
				$profile              = flxk_path_set( $profile, $path, $new );
				$run['backup'][ $t ]  = null === $op['old'] ? FLXK_ABSENT : $op['old'];
				$run['written'][ $t ] = flxk_hash( $new );
				$profile_dirty        = true;
				break;

			case 'media':
				$key = substr( $t, 6 );
				$id  = flxk_media_import( $key, $op['def'], $p['dir'] ?? '', $ctx['preset'] ?? '' );
				if ( is_wp_error( $id ) ) {
					$run['errors'][] = $t . ': ' . $id->get_error_message();
					break;
				}
				$ctx['refs']['media'][ $key ]      = $id;
				$run['created'][ 'media:' . $key ] = $id;
				$run['written'][ $t ]              = flxk_hash( 'media' );
				break;

			case 'content':
				$T    = flxk_importers()[ $op['type'] ];
				$item = flxk_resolve( $op['item'], $ctx );
				$id   = call_user_func( $T['save'], $item, (int) $op['id'] );
				if ( is_wp_error( $id ) ) {
					$run['errors'][] = $t . ': ' . $id->get_error_message();
					break;
				}
				$ctx['refs'][ $op['type'] ][ $op['key'] ] = (int) $id;
				if ( ! $op['id'] ) {
					$run['created'][ $op['type'] . ':' . $op['key'] ] = (int) $id;
				} else {
					$run['backup'][ $t ] = array(
						'id'   => (int) $id,
						'data' => $op['old'],
					);
				}
				$run['written'][ $t ] = flxk_hash( call_user_func( $T['read'], (int) $id ) );
				break;

			case 'wp':
				$name                 = substr( $t, 3 );
				$new                  = flxk_resolve( $p['wp'][ $name ], $ctx );
				$run['backup'][ $t ]  = null === $op['old'] ? FLXK_ABSENT : $op['old'];
				update_option( $name, $new );
				$run['written'][ $t ] = flxk_target_hash( $t );
				break;

			case 'settings':
				$opt = $op['option'];
				if ( ! isset( $options[ $opt ] ) ) {
					$cur             = get_option( $opt, array() );
					$options[ $opt ] = is_array( $cur ) ? $cur : array();
				}
				$new = flxk_resolve( flxk_path_get( (array) $p['settings'][ $opt ], $op['path'] ), $ctx );
				$old = flxk_path_get( $options[ $opt ], $op['path'], FLXK_ABSENT );
				if ( flxk_is_keyed_list( $new ) && FLXK_ABSENT !== $old ) {
					$new = flxk_merge( $old, $new );
				}
				$run['backup'][ $t ]  = $old;
				$options[ $opt ]      = flxk_path_set( $options[ $opt ], $op['path'], $new );
				$run['written'][ $t ] = flxk_hash( $new );
				break;
		}
	}

	if ( $profile_dirty ) {
		update_option( 'flx_profile', $profile );
	}
	foreach ( $options as $opt => $val ) {
		$before = get_option( $opt, array() );
		update_option( $opt, $val );
		flxk_guard_option( $opt, is_array( $before ) ? $before : array(), $run['written'] );
		do_action( 'flxk_option_written', $opt, get_option( $opt ) );
	}
	foreach ( $run['written'] as $t => $h ) {
		if ( 0 === strpos( $t, 'option:' ) || 0 === strpos( $t, 'profile:' ) ) {
			$h                    = flxk_target_hash( $t ); // Eklentinin sanitize sonrası kaydettiği hal.
			$run['written'][ $t ] = $h;
		}
		$st['owned'][ $t ] = $h;
	}
}

/**
 * Ön ayarı ilk kez uygular: taban + o anki tüm migration'lar.
 *
 * @param string $mode apply | fill
 */
function flxk_apply_preset( $id, $mode = 'apply', $force = array() ) {
	$p = flxk_get_preset( $id );
	if ( ! $p ) {
		return new WP_Error( 'preset', __( 'Preset not found.', 'flx-setup' ) );
	}
	$st  = flxk_state( $id );
	$res = flxk_execute(
		$id,
		flxk_base_of( $p ),
		array(
			'mode'  => $mode,
			'force' => $force,
		),
		array(
			'kind'  => 'base',
			'title' => $p['name'],
		)
	);
	$st = flxk_state( $id );
	if ( empty( $st['mode'] ) ) {
		$st['mode'] = $mode;
	}
	// İlk kurulumda mevcut migration'lar tabanın parçası sayılır: sırayla uygulanır.
	foreach ( (array) ( $p['migrations'] ?? array() ) as $m ) {
		if ( empty( $m['id'] ) || isset( $st['migrations'][ $m['id'] ] ) ) {
			continue;
		}
		$need = flxk_inactive_plugins( (array) ( $m['requires'] ?? array() ) );
		if ( $need ) {
			// Gereken eklenti şimdi kurulup etkinleşir; migration bekleyenlerde kalır ("auto" ise sonraki istekte uygulanır).
			flxk_install_plugins( $need );
			continue;
		}
		flxk_apply_migration( $id, $m['id'], array( 'mode' => $mode ) );
	}
	flxk_save_state( $id, flxk_state( $id ) + $st );
	return $res;
}

function flxk_migration( $id, $mig_id ) {
	$p = flxk_get_preset( $id );
	foreach ( (array) ( $p['migrations'] ?? array() ) as $m ) {
		if ( ( $m['id'] ?? '' ) === $mig_id ) {
			return $m + array(
				'format' => FLXK_FORMAT,
				'dir'    => $p['dir'] ?? '',
			);
		}
	}
	return null;
}

/** Bekleyen migration'lar (ön ayar uygulanmışsa). */
function flxk_pending_migrations( $id ) {
	$p  = flxk_get_preset( $id );
	$st = flxk_state( $id );
	if ( ! $p || ! $st ) {
		return array();
	}
	$out = array();
	foreach ( (array) ( $p['migrations'] ?? array() ) as $m ) {
		if ( ! empty( $m['id'] ) && ! isset( $st['migrations'][ $m['id'] ] ) ) {
			$out[] = $m;
		}
	}
	return $out;
}

function flxk_apply_migration( $id, $mig_id, array $opts = array() ) {
	$m = flxk_migration( $id, $mig_id );
	if ( ! $m ) {
		return new WP_Error( 'migration', __( 'Migration not found.', 'flx-setup' ) );
	}
	return flxk_execute(
		$id,
		$m,
		$opts + array( 'mode' => 'apply' ),
		array(
			'kind'      => 'migration',
			'title'     => (string) ( $m['title'] ?? $mig_id ),
			'migration' => $mig_id,
		)
	);
}

function flxk_skip_migration( $id, $mig_id ) {
	$st                          = flxk_state( $id );
	$st['migrations'][ $mig_id ] = array(
		'at'     => current_time( 'mysql' ),
		'status' => 'skipped',
	);
	flxk_save_state( $id, $st );
	flxk_log(
		array(
			'preset' => $id,
			'kind'   => 'skip',
			'title'  => $mig_id,
		)
	);
}

/** "requires.plugins" listesinden etkin olmayan eklenti slug'ları ("flx-wiki>=0.1" gibi sürüm eki atılır). */
function flxk_inactive_plugins( array $req ) {
	$active = array();
	foreach ( (array) get_option( 'active_plugins', array() ) as $b ) {
		$active[ dirname( $b ) ] = true;
	}
	$out = array();
	foreach ( (array) ( $req['plugins'] ?? array() ) as $r ) {
		$slug = preg_replace( '/[<>=].*$/', '', (string) $r );
		if ( '' !== $slug && empty( $active[ $slug ] ) ) {
			$out[] = $slug;
		}
	}
	return $out;
}

/**
 * Eksik eklentileri FluxWP Panel'in paket kurucusuyla kurar ve etkinleştirir (gerekli eklentileriyle birlikte;
 * FluxWP paketi güncelleme sunucusundan, diğerleri wordpress.org'dan). Yeni etkinleşen eklentinin init kancaları
 * bir sonraki istekte çalışır; içerik ondan sonra yazılmalı.
 *
 * @return array{ok: bool, lines: array} lines: {ok, text}
 */
function flxk_install_plugins( array $slugs ) {
	$lines = array();
	if ( ! $slugs ) {
		return array( 'ok' => true, 'lines' => $lines );
	}
	if ( ! class_exists( 'FLXU_Installer' ) || ! current_user_can( 'activate_plugins' ) ) {
		return array( 'ok' => false, 'lines' => $lines );
	}
	$catalog = FLXU_Updater::instance()->catalog();
	$rows    = FLXU_Admin::rows( $catalog );
	$steps   = FLXU_Installer::steps( FLXU_Installer::plan( $slugs, $catalog, $rows ), $catalog, true );
	foreach ( $steps as $st ) {
		$res   = FLXU_Ajax::run_step( $st['kind'], $st['slug'] );
		$lines = array_merge( $lines, (array) ( $res['lines'] ?? array() ) );
	}
	// Kurulu ama kapalı olup plana girmeyenler (katalogda yoksa) doğrudan etkinleşir.
	if ( ! function_exists( 'activate_plugin' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$installed = FLXU_Installer::installed_plugins();
	foreach ( flxk_inactive_plugins( array( 'plugins' => $slugs ) ) as $slug ) {
		if ( isset( $installed[ $slug ] ) ) {
			$r       = activate_plugin( $installed[ $slug ]['file'] );
			$lines[] = array(
				'ok'   => ! is_wp_error( $r ),
				/* translators: %s: eklenti */
				'text' => is_wp_error( $r ) ? $r->get_error_message() : sprintf( __( '%s activated.', 'flx-setup' ), $installed[ $slug ]['name'] ),
			);
		}
	}
	return array(
		'ok'    => ! flxk_inactive_plugins( array( 'plugins' => $slugs ) ),
		'lines' => $lines,
	);
}

/**
 * "auto": true migration'lar güncellemeden sonra kendiliğinden uygulanır.
 * Migration "requires": {"plugins": [...]} bildirirse eksik eklentiler önce kurulup etkinleştirilir; migration
 * hepsi etkin olana kadar bekler (atlandı sayılmaz) ve bir sonraki istekte, eklentinin içerik türleri
 * kayıtlıyken uygulanır. Kurma denemesi saatte bir kez yapılır.
 */
add_action( 'admin_init', 'flxk_auto_migrations' );
function flxk_auto_migrations() {
	if ( wp_doing_ajax() || ! current_user_can( flxk_cap() ) ) {
		return;
	}
	foreach ( array_keys( flxk_state() ) as $id ) {
		foreach ( flxk_pending_migrations( $id ) as $m ) {
			if ( empty( $m['auto'] ) ) {
				continue;
			}
			$need = flxk_inactive_plugins( (array) ( $m['requires'] ?? array() ) );
			if ( $need ) {
				$key = 'flxk_req_' . md5( $id . '|' . $m['id'] );
				if ( ! get_transient( $key ) ) {
					set_transient( $key, 1, HOUR_IN_SECONDS );
					flxk_install_plugins( $need );
				}
				continue;
			}
			flxk_apply_migration( $id, $m['id'] );
		}
	}
}

/* ---------- Yalnız bildirilen alanlar ---------- */

/**
 * Ön ayar bir option'ın yalnız bildirdiği yollarını değiştirir. Bazı eklentilerin sanitize callback'i formdan
 * gelmeyen onay kutularını "kapalı" sayar (ör. tema çerez banner'ı): ön ayar `site_mod` yazınca `cookie_enabled`
 * 0 oluyordu. Yazdıktan sonra bildirilmeyen üst anahtarlar eski haline döner (yoksa silinir, eklentinin varsayılanı
 * geçerli olur). Kapatmak için: `flxk_option_guard` filtresi false.
 *
 * @param string $opt     option adı
 * @param array  $before  yazmadan önceki değer
 * @param array  $written bu çalıştırmada yazılan hedefler (target => hash)
 */
function flxk_guard_option( $opt, array $before, array $written ) {
	if ( ! apply_filters( 'flxk_option_guard', true, $opt ) ) {
		return;
	}
	$after = get_option( $opt, array() );
	if ( ! is_array( $after ) ) {
		return;
	}
	$declared = array();
	$prefix   = 'option:' . $opt . ':';
	foreach ( array_keys( $written ) as $t ) {
		if ( 0 === strpos( $t, $prefix ) ) {
			$declared[ strtok( substr( $t, strlen( $prefix ) ), '.' ) ] = true;
		}
	}
	$fixed = $after;
	foreach ( $after as $k => $v ) {
		if ( isset( $declared[ (string) $k ] ) ) {
			continue;
		}
		if ( ! array_key_exists( $k, $before ) ) {
			unset( $fixed[ $k ] );
		} elseif ( flxk_hash( $before[ $k ] ) !== flxk_hash( $v ) ) {
			$fixed[ $k ] = $before[ $k ];
		}
	}
	foreach ( $before as $k => $v ) {
		if ( ! isset( $declared[ (string) $k ] ) && ! array_key_exists( $k, $fixed ) ) {
			$fixed[ $k ] = $v; // Sanitize bildirilmeyen anahtarı silmiş.
		}
	}
	if ( flxk_hash( $fixed ) === flxk_hash( $after ) ) {
		return;
	}
	// Ham yazım: düzeltmeyi eklentinin sanitize'ı yeniden bozmasın.
	global $wp_filter;
	$hook  = 'sanitize_option_' . $opt;
	$saved = $wp_filter[ $hook ] ?? null;
	unset( $wp_filter[ $hook ] );
	update_option( $opt, $fixed );
	if ( null !== $saved ) {
		$wp_filter[ $hook ] = $saved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	}
}

/* ---------- Ön ayar güncellenince eşitle ---------- */

/** Ön ayarın ayar bölümleri (profil, WordPress ayarları, eklenti ayarları). */
function flxk_settings_part( array $p ) {
	return array_intersect_key( $p, array_flip( array( 'format', 'id', 'name', 'version', 'profile', 'wp', 'settings', 'dir' ) ) );
}

/**
 * Ön ayarın sürümü değiştiyse ayar bölümlerini (profile, wp, settings) siteyle eşitler. Motorun sahiplik kuralı
 * geçerlidir: ön ayarın daha önce yazdığı ve kullanıcının değiştirmediği değerler yenilenir, elle değiştirilenler
 * ("manual") atlanır, yeni alanlar eklenir. İçerik ve şablonlar migration'larla güncellenir.
 *
 * @param bool $force sürüm aynı olsa da eşitle
 * @return array|null run özeti (bir şey yazıldıysa)
 */
function flxk_sync_preset( $id, $force = false ) {
	$p  = flxk_get_preset( $id );
	$st = flxk_state( $id );
	if ( ! $p || ! $st || ! empty( $p['error'] ) ) {
		return null;
	}
	$ver = (string) ( $p['version'] ?? '' );
	if ( ! $force && (string) ( $st['ver'] ?? '' ) === $ver ) {
		return null;
	}
	$part = flxk_settings_part( $p );
	$opts = array( 'mode' => (string) ( $st['mode'] ?? 'apply' ) );
	$plan = flxk_plan( $id, $part, $opts );
	$todo = array_filter(
		$plan['ops'],
		static function ( $op ) {
			return ! empty( $op['apply'] );
		}
	);
	if ( ! $todo ) {
		$st['ver'] = $ver;
		flxk_save_state( $id, $st );
		return null;
	}
	return flxk_execute(
		$id,
		$part,
		$opts,
		array(
			'kind'  => 'sync',
			/* translators: %s: ön ayar sürümü */
			'title' => sprintf( __( 'Settings synced (%s)', 'flx-setup' ), $ver ),
		)
	);
}

/** Eklenti güncellemesinden sonra: ayarları eşitle, sonra "auto" migration'lar (aşağıda) çalışır. */
add_action( 'admin_init', 'flxk_sync_presets', 9 );
function flxk_sync_presets() {
	if ( wp_doing_ajax() || ! current_user_can( flxk_cap() ) || ! apply_filters( 'flxk_auto_sync', true ) ) {
		return;
	}
	foreach ( array_keys( flxk_state() ) as $id ) {
		flxk_sync_preset( $id );
	}
}

/* ---------- Geri al ---------- */

function flxk_run( $run_id ) {
	$r = get_option( 'flxk_run_' . $run_id );
	return is_array( $r ) ? $r : null;
}

/**
 * Bir run'ı geri alır. Sonradan elle değiştirilen hedeflere dokunmaz. Oluşturulan içerik pasife alınır.
 *
 * @return array|WP_Error geri yüklenen/atlanan sayıları
 */
function flxk_revert_run( $run_id ) {
	$run = flxk_run( $run_id );
	if ( ! $run ) {
		return new WP_Error( 'run', __( 'Record not found.', 'flx-setup' ) );
	}
	$pid      = $run['preset'];
	$st       = flxk_state( $pid );
	$restored = 0;
	$skipped  = array();
	$profile  = flxk_profile_all();
	$pdirty   = false;
	$options  = array();
	foreach ( $run['backup'] as $t => $old ) {
		$cur_hash = flxk_target_hash( $t );
		if ( isset( $run['written'][ $t ] ) && $cur_hash !== $run['written'][ $t ] ) {
			$skipped[] = $t; // Sonradan değişmiş.
			continue;
		}
		if ( 0 === strpos( $t, 'profile:' ) ) {
			$path    = substr( $t, 8 );
			$profile = flxk_path_set( $profile, $path, $old, FLXK_ABSENT === $old );
			$pdirty  = true;
		} elseif ( 0 === strpos( $t, 'wp:' ) ) {
			if ( FLXK_ABSENT === $old ) {
				delete_option( substr( $t, 3 ) );
			} else {
				update_option( substr( $t, 3 ), $old );
			}
		} elseif ( 0 === strpos( $t, 'option:' ) ) {
			list( , $opt, $path ) = explode( ':', $t, 3 );
			if ( ! isset( $options[ $opt ] ) ) {
				$options[ $opt ] = (array) get_option( $opt, array() );
			}
			$options[ $opt ] = flxk_path_set( $options[ $opt ], $path, $old, FLXK_ABSENT === $old );
		} elseif ( 0 === strpos( $t, 'content:' ) && is_array( $old ) && ! empty( $old['data'] ) ) {
			list( , $type ) = explode( ':', $t, 3 );
			$T             = flxk_importers()[ $type ] ?? null;
			if ( $T ) {
				call_user_func( $T['save'], flxk_content_restore_item( $type, $t, $old['data'] ), (int) $old['id'] );
			}
		}
		unset( $st['owned'][ $t ] );
		++$restored;
	}
	if ( $pdirty ) {
		update_option( 'flx_profile', $profile );
	}
	foreach ( $options as $opt => $val ) {
		update_option( $opt, $val );
	}
	foreach ( $run['created'] as $ck => $id ) {
		list( $type, $key ) = explode( ':', $ck, 2 );
		if ( 'media' === $type ) {
			unset( $st['owned'][ 'media:' . $key ] );
			continue; // Medya kütüphanede kalır.
		}
		$T = flxk_importers()[ $type ] ?? null;
		if ( $T && ! empty( $T['disable'] ) ) {
			call_user_func( $T['disable'], (int) $id );
		}
		unset( $st['owned'][ 'content:' . $type . ':' . $key ] );
		++$restored;
	}
	$st['runs'] = array_values( array_diff( (array) ( $st['runs'] ?? array() ), array( $run_id ) ) );
	if ( ! empty( $run['mig'] ) ) {
		unset( $st['migrations'][ $run['mig'] ] );
	}
	flxk_save_state( $pid, $st );
	delete_option( 'flxk_run_' . $run_id );
	$res = array(
		'restored' => $restored,
		'skipped'  => $skipped,
	);
	flxk_log(
		array(
			'preset' => $pid,
			'kind'   => 'revert',
			'title'  => $run['title'],
			'run'    => $run_id,
			'counts' => array(
				'restored' => $restored,
				'manual'   => count( $skipped ),
			),
		)
	);
	do_action( 'litespeed_purge_all' );
	return $res;
}

/** İçerik geri yüklemesi için okunan veriyi importer'ın beklediği öğeye çevirir. */
function flxk_content_restore_item( $type, $target, array $data ) {
	$key  = substr( $target, strlen( 'content:' . $type . ':' ) );
	$item = array(
		'type' => $type,
		'key'  => $key,
	);
	if ( isset( $data['content'] ) ) {
		$item['body'] = $data['content'];
	}
	if ( 'styles' === $type ) {
		$item['data']      = $data;
		$item['__replace'] = true;
	}
	return $item + $data;
}

/** Hedefin sitedeki güncel değerinin hash'i (elle değişiklik tespiti). */
function flxk_target_hash( $t ) {
	if ( 0 === strpos( $t, 'profile:' ) ) {
		$v = flxk_path_get( flxk_profile_all(), substr( $t, 8 ), FLXK_ABSENT );
		return flxk_hash( $v );
	}
	if ( 0 === strpos( $t, 'wp:' ) ) {
		$v = get_option( substr( $t, 3 ), FLXK_ABSENT );
		return flxk_hash( false === $v ? FLXK_ABSENT : $v );
	}
	if ( 0 === strpos( $t, 'option:' ) ) {
		list( , $opt, $path ) = explode( ':', $t, 3 );
		return flxk_hash( flxk_path_get( (array) get_option( $opt, array() ), $path, FLXK_ABSENT ) );
	}
	if ( 0 === strpos( $t, 'media:' ) ) {
		return flxk_hash( 'media' ); // Medya yalnız bir kez eklenir; elle değişiklik takibi yok.
	}
	if ( 0 === strpos( $t, 'content:' ) ) {
		list( , $type, $key ) = explode( ':', $t, 3 );
		$T                    = flxk_importers()[ $type ] ?? null;
		if ( ! $T ) {
			return '';
		}
		$id = (int) call_user_func( $T['find'], array( 'key' => $key ) );
		return $id ? flxk_hash( call_user_func( $T['read'], $id ) ) : flxk_hash( FLXK_ABSENT );
	}
	return '';
}

/** Ön ayarı kaldırır: run'larını tersten geri alır. Elle değiştirilen değerler ve içerik kalır (pasif). */
function flxk_remove_preset( $id ) {
	$st    = flxk_state( $id );
	$total = array(
		'restored' => 0,
		'skipped'  => array(),
	);
	foreach ( array_reverse( (array) ( $st['runs'] ?? array() ) ) as $run_id ) {
		$r = flxk_revert_run( $run_id );
		if ( ! is_wp_error( $r ) ) {
			$total['restored'] += $r['restored'];
			$total['skipped']   = array_merge( $total['skipped'], $r['skipped'] );
		}
	}
	flxk_save_state( $id, null );
	flxk_log(
		array(
			'preset' => $id,
			'kind'   => 'remove',
			'title'  => $id,
		)
	);
	return $total;
}

/* ---------- Çalışma anı: ön ayar pattern'leri ---------- */

add_action( 'init', 'flxk_register_patterns', 20 );
function flxk_register_patterns() {
	foreach ( array_keys( flxk_state() ) as $id ) {
		$p = flxk_get_preset( $id );
		if ( ! $p || empty( $p['theme']['patterns'] ) ) {
			continue;
		}
		if ( ! WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( 'flxk-' . $id ) ) {
			register_block_pattern_category( 'flxk-' . $id, array( 'label' => (string) $p['name'] ) );
		}
		$ctx = flxk_context( $p, $id );
		foreach ( (array) $p['theme']['patterns'] as $slug => $def ) {
			$def = is_array( $def ) && isset( $def['body'] ) ? $def : array( 'body' => $def );
			register_block_pattern(
				( false === strpos( $slug, '/' ) ? $id . '/' . $slug : $slug ),
				array(
					'title'      => (string) ( $def['title'] ?? $slug ),
					'categories' => array_merge( array( 'flxk-' . $id ), (array) ( $def['categories'] ?? array() ) ),
					'content'    => flxk_resolve( flxk_body_to_markup( $def['body'] ), $ctx ),
					'blockTypes' => (array) ( $def['blockTypes'] ?? array() ),
				)
			);
		}
	}
}
