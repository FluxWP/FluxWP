<?php
/**
 * MCP: Sistem Kontrolü sonuçlarını okuma ve izinli düzeltmeleri uygulama.
 *
 * Düzeltme kaydı (filtre `flx_system_fixes`): 'kimlik' => array(
 *   label, description,
 *   checks   => array( kontrol id'leri ) — bu düzeltmenin çözdüğü satırlar,
 *   value    => null | JSON şeması (ör. array( 'type' => 'string', 'enum' => … )),
 *   current  => callable(): mevcut değer (isteğe bağlı),
 *   run      => callable( $value ): sonuç metni döner, hata için Exception atar,
 *   source   => 'Tema' / 'Flux SEO' / eklenti adı,
 * ).
 * Yalnızca bu listedekiler çalışır; her çalıştırma `flxseo_system_fix_log`'a yazılır.
 *
 * @package FLX_SEO
 */

defined( 'ABSPATH' ) || exit;

/** Kayıtlı düzeltmeler (geçersiz satırlar atlanır). */
function flxseo_system_fixes() {
	$out = array();
	foreach ( (array) apply_filters( 'flx_system_fixes', array() ) as $id => $f ) {
		if ( is_array( $f ) && ! empty( $f['run'] ) && is_callable( $f['run'] ) ) {
			$out[ sanitize_key( str_replace( '.', '_', $id ) ) === $id ? $id : $id ] = $f + array(
				'label'       => $id,
				'description' => '',
				'checks'      => array(),
				'value'       => null,
				'source'      => '',
			);
		}
	}
	return $out;
}

/** Kontrol listesi (tema veya eklentilerin ortak yardımcısı; yoksa filtre). */
function flxseo_system_checks_list() {
	$list = function_exists( 'flx_system_checks_run' ) ? (array) flx_system_checks_run() : (array) apply_filters( 'flx_system_checks', array() );
	$out  = array();
	foreach ( $list as $c ) {
		if ( ! is_array( $c ) || empty( $c['label'] ) ) {
			continue;
		}
		$status = in_array( $c['status'] ?? '', array( 'ok', 'warning', 'error', 'info' ), true ) ? $c['status'] : 'info';
		$out[]  = array(
			'id'           => (string) ( $c['id'] ?? '' ),
			'source'       => (string) ( $c['source'] ?? '' ),
			'group'        => (string) ( $c['group'] ?? '' ),
			'label'        => wp_strip_all_tags( (string) $c['label'] ),
			'status'       => $status,
			'message'      => wp_strip_all_tags( (string) ( $c['message'] ?? '' ) ),
			'action_url'   => (string) ( $c['action_url'] ?? '' ),
			'action_label' => (string) ( $c['action_label'] ?? '' ),
		);
	}
	return $out;
}

/** Değişiklik kaydı: son 200 düzeltme. */
function flxseo_system_fix_log( $limit = 200 ) {
	return array_slice( (array) get_option( 'flxseo_system_fix_log', array() ), 0, $limit );
}

function flxseo_system_fix_log_add( $row ) {
	$log = (array) get_option( 'flxseo_system_fix_log', array() );
	array_unshift( $log, $row );
	update_option( 'flxseo_system_fix_log', array_slice( $log, 0, 200 ), false );
}

/** Değeri kayda yazılabilir kısa metne çevirir. */
function flxseo_system_short( $v ) {
	if ( is_bool( $v ) ) {
		return $v ? 'true' : 'false';
	}
	if ( is_scalar( $v ) || null === $v ) {
		return mb_substr( (string) $v, 0, 160 );
	}
	return mb_substr( (string) wp_json_encode( $v, JSON_UNESCAPED_UNICODE ), 0, 160 );
}

/**
 * Bir düzeltmeyi çalıştırır, önceki/sonraki değeri ve sonucu kaydeder.
 *
 * @return array { ok, message, before, after }
 */
function flxseo_system_fix_run( $id, $value = null, $via = 'mcp' ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		throw new Exception( 'Bu işlem için yönetici yetkisi gerekir.' );
	}
	$fixes = flxseo_system_fixes();
	if ( ! isset( $fixes[ $id ] ) ) {
		throw new Exception( 'İzinli düzeltmeler arasında yok: ' . $id . '. flxseo_system_fixes ile listeyi görün.' );
	}
	$f = $fixes[ $id ];
	if ( $f['value'] && null === $value ) {
		throw new Exception( 'Bu düzeltme bir değer (value) ister.' );
	}
	if ( ! empty( $f['value']['enum'] ) && ! in_array( $value, $f['value']['enum'], true ) ) {
		throw new Exception( 'Geçersiz değer. İzinli değerler: ' . implode( ', ', $f['value']['enum'] ) );
	}
	$before = isset( $f['current'] ) && is_callable( $f['current'] ) ? call_user_func( $f['current'], $value ) : null;
	$ok     = false;
	try {
		$msg = (string) call_user_func( $f['run'], $value );
		$ok  = true;
	} catch ( Throwable $e ) {
		$msg = $e->getMessage();
	}
	$after = isset( $f['current'] ) && is_callable( $f['current'] ) ? call_user_func( $f['current'], $value ) : null;
	flxseo_system_fix_log_add(
		array(
			't'      => time(),
			'u'      => get_current_user_id(),
			'via'    => $via,
			'fix'    => $id,
			'label'  => $f['label'],
			'value'  => flxseo_system_short( $value ),
			'before' => flxseo_system_short( $before ),
			'after'  => flxseo_system_short( $after ),
			'ok'     => $ok,
			'msg'    => mb_substr( $msg, 0, 300 ),
		)
	);
	if ( ! $ok ) {
		throw new Exception( $msg );
	}
	return array( 'ok' => true, 'message' => $msg, 'before' => $before, 'after' => $after );
}

/* ---------- MCP araçları ---------- */

add_filter(
	'flx_mcp_tools',
	function ( $tools ) {
		$tools['flxseo_system_checks'] = array(
			'aliases'     => array( 'system_checks' ), // Flux SEO 1.5.x ve öncesindeki ad.
			'source'      => 'flx-seo',
			'title'       => 'Sistem kontrolü',
			'description' => 'Görünüm › Sistem Kontrolü ekranındaki tüm satırlar: eklentiler (kurulu/etkin/sürüm), cron, site ayarları, sayfalar, SEO ve eklentilerin kendi kontrolleri. Her satırda status (ok|warning|error|info), mesaj ve varsa "fixes" (flxseo_system_fix ile uygulanabilecek düzeltme kimlikleri) bulunur.',
			'readonly'    => true,
			'cap'         => 'manage_options',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'status' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string', 'enum' => array( 'error', 'warning', 'info', 'ok' ) ),
						'description' => 'Yalnızca bu durumlar. Boşsa hepsi.',
					),
					'source' => array( 'type' => 'string', 'description' => 'Kaynak adında geçen metin (ör. "Flux Donate", "Tema").' ),
					'search' => array( 'type' => 'string', 'description' => 'Etiket, grup veya mesajda geçen metin.' ),
				),
			),
			'run'         => function ( $a ) {
				$want   = array_filter( (array) ( $a['status'] ?? array() ) );
				$source = mb_strtolower( (string) ( $a['source'] ?? '' ) );
				$search = mb_strtolower( (string) ( $a['search'] ?? '' ) );
				$byfix  = array();
				foreach ( flxseo_system_fixes() as $fid => $f ) {
					foreach ( (array) $f['checks'] as $cid ) {
						$byfix[ $cid ][] = $fid;
					}
				}
				$all     = flxseo_system_checks_list();
				$summary = array( 'error' => 0, 'warning' => 0, 'info' => 0, 'ok' => 0 );
				$items   = array();
				foreach ( $all as $c ) {
					++$summary[ $c['status'] ];
					if ( $want && ! in_array( $c['status'], $want, true ) ) {
						continue;
					}
					if ( $source && false === mb_strpos( mb_strtolower( $c['source'] ), $source ) ) {
						continue;
					}
					if ( $search && false === mb_strpos( mb_strtolower( $c['label'] . ' ' . $c['group'] . ' ' . $c['message'] ), $search ) ) {
						continue;
					}
					if ( isset( $byfix[ $c['id'] ] ) ) {
						$c['fixes'] = $byfix[ $c['id'] ];
					}
					$items[] = $c;
				}
				$order = array( 'error' => 0, 'warning' => 1, 'info' => 2, 'ok' => 3 );
				usort(
					$items,
					function ( $x, $y ) use ( $order ) {
						return $order[ $x['status'] ] <=> $order[ $y['status'] ];
					}
				);
				return array(
					'checked_at' => wp_date( 'Y-m-d H:i:s' ),
					'summary'    => $summary,
					'count'      => count( $items ),
					'checks'     => $items,
				);
			},
		);

		$tools['flxseo_system_fixes'] = array(
			'aliases'     => array( 'system_fixes' ), // Flux SEO 1.5.x ve öncesindeki ad.
			'source'      => 'flx-seo',
			'title'       => 'İzinli sistem düzeltmeleri',
			'description' => 'flxseo_system_fix ile uygulanabilecek düzeltmeler: ne yaptığı, değer isteyip istemediği (value şeması), mevcut değer ve hangi kontrol satırlarını çözdüğü.',
			'readonly'    => true,
			'cap'         => 'manage_options',
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'run'         => function () {
				$out = array();
				foreach ( flxseo_system_fixes() as $id => $f ) {
					$cur   = isset( $f['current'] ) && is_callable( $f['current'] ) && ! $f['value'] ? call_user_func( $f['current'], null ) : null;
					$out[] = array_filter(
						array(
							'id'          => $id,
							'label'       => $f['label'],
							'description' => $f['description'],
							'source'      => $f['source'],
							'value'       => $f['value'],
							'current'     => $cur,
							'checks'      => array_values( (array) $f['checks'] ),
						),
						function ( $v ) {
							return null !== $v && '' !== $v && array() !== $v;
						}
					);
				}
				return array( 'fixes' => $out );
			},
		);

		$tools['flxseo_system_fix'] = array(
			'aliases'     => array( 'system_fix' ), // Flux SEO 1.5.x ve öncesindeki ad.
			'source'      => 'flx-seo',
			'title'       => 'Sistem düzeltmesi uygula',
			'description' => 'flxseo_system_fixes listesindeki bir düzeltmeyi uygular (yalnızca izinli liste; yönetici anahtarı gerekir). Önceki ve sonraki değer kaydedilir. Uyguladıktan sonra flxseo_system_checks ile sonucu doğrulayın.',
			'cap'         => 'manage_options',
			'schema'      => array(
				'type'       => 'object',
				'required'   => array( 'fix' ),
				'properties' => array(
					'fix'   => array( 'type' => 'string', 'description' => 'Düzeltme kimliği (flxseo_system_fixes).' ),
					'value' => array( 'description' => 'Düzeltme değer istiyorsa (flxseo_system_fixes › value şeması).' ),
				),
			),
			'run'         => function ( $a ) {
				return flxseo_system_fix_run( (string) ( $a['fix'] ?? '' ), $a['value'] ?? null, 'mcp' );
			},
		);

		$tools['flxseo_system_fix_log'] = array(
			'aliases'     => array( 'system_fix_log' ), // Flux SEO 1.5.x ve öncesindeki ad.
			'source'      => 'flx-seo',
			'title'       => 'Sistem düzeltme kaydı',
			'description' => 'Son düzeltmeler: zaman, kullanıcı, düzeltme, önceki → sonraki değer, sonuç.',
			'readonly'    => true,
			'cap'         => 'manage_options',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'limit' => array( 'type' => 'integer', 'default' => 30 ) ),
			),
			'run'         => function ( $a ) {
				$rows = array();
				foreach ( flxseo_system_fix_log( max( 1, min( 200, (int) ( $a['limit'] ?? 30 ) ) ) ) as $r ) {
					$u      = get_userdata( (int) $r['u'] );
					$rows[] = array(
						'time'   => wp_date( 'Y-m-d H:i:s', (int) $r['t'] ),
						'user'   => $u ? $u->user_login : '?',
						'via'    => $r['via'],
						'fix'    => $r['fix'],
						'label'  => $r['label'],
						'value'  => $r['value'],
						'before' => $r['before'],
						'after'  => $r['after'],
						'ok'     => (bool) $r['ok'],
						'result' => $r['msg'],
					);
				}
				return array( 'log' => $rows );
			},
		);
		return $tools;
	}
);

/* ---------- Flux SEO'nun kendi düzeltmeleri ---------- */

add_filter(
	'flx_system_fixes',
	function ( $fixes ) {
		$set = function ( $key, $value ) {
			$o         = wp_parse_args( (array) get_option( 'flxseo', array() ), flxseo_defaults() );
			$o[ $key ] = $value;
			update_option( 'flxseo', flxseo_sanitize( $o ) );
		};
		// flxseo_opt() istek içinde önbellekli; değişiklik sonrası değeri doğrudan oku.
		$get = function ( $key ) {
			$o = wp_parse_args( (array) get_option( 'flxseo', array() ), flxseo_defaults() );
			return $o[ $key ] ?? null;
		};
		$fixes['seo.browser_cache'] = array(
			'label'       => 'Tarayıcı önbelleği kurallarını .htaccess\'e yeniden yaz',
			'description' => 'Görseller 30 gün, CSS/JS/font 1 yıl önbellek kuralı.',
			'checks'      => array( 'flxseo.browser-cache' ),
			'source'      => 'Flux SEO',
			'current'     => function () {
				return flxseo_cache_active();
			},
			'run'         => function () {
				if ( ! flxseo_cache_htaccess() ) {
					throw new Exception( '.htaccess yazılamadı (dosya izni ya da sunucu Apache/LiteSpeed değil).' );
				}
				update_option( 'flxseo_cache_rules', FLXSEO_VERSION, false );
				return 'Kurallar yazıldı.';
			},
		);
		$fixes['seo.output'] = array(
			'label'   => 'SEO çıktısını aç',
			'checks'  => array( 'flxseo.output' ),
			'source'  => 'Flux SEO',
			'current' => function () use ( $get ) {
				return (bool) $get( 'enabled' );
			},
			'run'     => function () use ( $set ) {
				$set( 'enabled', 1 );
				return 'SEO çıktısı açıldı.';
			},
		);
		// Kurum ve doğrulama alanları: yalnızca bu anahtarlar.
		$fields = array(
			'phone'         => 'Telefon',
			'email'         => 'E-posta',
			'street'        => 'Adres (sokak)',
			'locality'      => 'İlçe, il',
			'postal'        => 'Posta kodu',
			'same_as'       => 'Sosyal hesap adresleri (satır başına bir)',
			'verify_google' => 'Google Search Console doğrulama kodu',
			'verify_bing'   => 'Bing doğrulama kodu',
		);
		$fixes['seo.setting'] = array(
			'label'       => 'Flux SEO kurum/doğrulama alanı',
			'description' => 'value: { "key": "' . implode( '|', array_keys( $fields ) ) . '", "value": "…" }',
			'checks'      => array( 'flxseo.org', 'flxseo.gsc' ),
			'source'      => 'Flux SEO',
			'value'       => array(
				'type'       => 'object',
				'required'   => array( 'key', 'value' ),
				'properties' => array(
					'key'   => array( 'type' => 'string', 'enum' => array_keys( $fields ) ),
					'value' => array( 'type' => 'string' ),
				),
			),
			'current'     => function ( $v ) use ( $fields, $get ) {
				if ( is_array( $v ) && isset( $fields[ $v['key'] ?? '' ] ) ) {
					return $get( $v['key'] );
				}
				$o = array();
				foreach ( $fields as $k => $l ) {
					$o[ $k ] = $get( $k );
				}
				return $o;
			},
			'run'         => function ( $v ) use ( $set, $fields ) {
				if ( ! is_array( $v ) || ! isset( $fields[ $v['key'] ?? '' ] ) ) {
					throw new Exception( 'key şunlardan biri olmalı: ' . implode( ', ', array_keys( $fields ) ) );
				}
				$set( $v['key'], (string) ( $v['value'] ?? '' ) );
				return $fields[ $v['key'] ] . ' güncellendi.';
			},
		);
		return $fixes;
	}
);
