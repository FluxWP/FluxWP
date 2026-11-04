<?php
/**
 * Flux AI & MCP sunucusuna araçlar (flxseo_mcp_tools). Flux AI sohbeti de aynı araçları görür.
 * Gönderi oluştur/zamanla/gönder, listele, düzenle, sil, ağda düzenle, pano. Anahtar/belirteç asla dönmez.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'flxseo_mcp_tools', 'flxsm_mcp_tools' );

/** "2026-11-02 14:30" (site saat dilimi) ya da ISO 8601 → unix. Boş → 0. */
function flxsm_mcp_time( $v ) {
	$v = trim( (string) $v );
	if ( '' === $v ) {
		return 0;
	}
	if ( ctype_digit( $v ) ) {
		return (int) $v;
	}
	try {
		$d = new DateTime( $v, wp_timezone() );
		return $d->getTimestamp();
	} catch ( Exception $e ) {
		return -1;
	}
}

/** MCP çıktısı: gönderi (gereksiz alanlar atılır, zaman okunur biçimde). */
function flxsm_mcp_post( array $p ) {
	$fmt = fn( $t ) => $t ? wp_date( 'Y-m-d H:i', $t ) : '';
	$out = array(
		'id'           => $p['id'],
		'status'       => $p['status'],
		'title'        => $p['title'],
		'content'      => $p['content'],
		'tags'         => $p['tags'],
		'media'        => array_map(
			fn( $m ) => array(
				'id'   => $m['id'],
				'kind' => $m['kind'],
				'url'  => $m['url'],
			),
			(array) $p['media']
		),
		'scheduled_at' => $fmt( $p['scheduled_at'] ),
		'published_at' => $fmt( $p['published_at'] ),
		'user'         => $p['user'],
		'note'         => $p['note'],
		'targets'      => array(),
	);
	foreach ( $p['targets'] as $t ) {
		$row = array(
			'target_id'  => $t['id'],
			'account_id' => $t['account_id'],
			'network'    => $t['provider'],
			'account'    => $t['account'],
			'status'     => $t['status'],
			'url'        => $t['remote_url'],
			'error'      => $t['error'],
			'metrics'    => $t['metrics'],
		);
		if ( array_key_exists( 'content', $t ) && null !== $t['content'] ) {
			$row['content'] = $t['content'];
		}
		if ( ! empty( $t['options'] ) && (array) $t['options'] ) {
			$row['options'] = $t['options'];
		}
		$out['targets'][] = $row;
	}
	return $out;
}

/** media_ids + media_urls (sitenin ortam kütüphanesine indirilir). */
function flxsm_mcp_media( array $a ) {
	$ids = array_values( array_filter( array_map( 'absint', (array) ( $a['media_ids'] ?? array() ) ) ) );
	foreach ( (array) ( $a['media_urls'] ?? array() ) as $url ) {
		$url = esc_url_raw( (string) $url );
		if ( ! $url ) {
			continue;
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'mcp_cap', 'Dosya yükleme yetkisi yok (media_urls).' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_sideload_image( $url, 0, null, 'id' );
		if ( is_wp_error( $id ) ) {
			$tmp = download_url( $url, 120 );
			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}
			$id = media_handle_sideload(
				array(
					'name'     => basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ),
					'tmp_name' => $tmp,
				),
				0
			);
		}
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$ids[] = (int) $id;
	}
	return $ids;
}

/** accounts[] + per_account{} → hedef listesi. */
function flxsm_mcp_targets( array $a ) {
	$per  = (array) ( $a['per_account'] ?? array() );
	$ids  = array_map( 'absint', (array) ( $a['accounts'] ?? array() ) );
	$ids  = array_values( array_unique( array_merge( $ids, array_map( 'absint', array_keys( $per ) ) ) ) );
	$list = array();
	$have = array();
	foreach ( flxsm_accounts( true ) as $acc ) {
		$have[ (int) $acc['id'] ] = $acc;
	}
	foreach ( $ids as $id ) {
		if ( ! isset( $have[ $id ] ) ) {
			return new WP_Error( 'flxsm_account', sprintf( 'Hesap #%d yok ya da etkin değil. flxsm_accounts ile listeleyin.', $id ) );
		}
		$p   = (array) ( $per[ $id ] ?? $per[ (string) $id ] ?? array() );
		$row = array(
			'account_id' => $id,
			'options'    => (array) ( $p['options'] ?? array() ),
		);
		if ( isset( $p['content'] ) && '' !== (string) $p['content'] ) {
			$row['content'] = (string) $p['content'];
		}
		if ( isset( $p['media_ids'] ) ) {
			$row['media'] = array_map( 'absint', (array) $p['media_ids'] );
		}
		$list[] = $row;
	}
	return $list;
}

function flxsm_mcp_tools( $tools ) {
	$cap = FLXSM_CAP_POST;
	$opt = array(
		'type'        => 'object',
		'description' => 'Ağa özel seçenekler: title (YouTube/Pinterest/LinkedIn makale başlığı), link, board (Pinterest pano id), privacy (YouTube public|unlisted|private), ig_type (feed|reel|story), tiktok_privacy (PUBLIC_TO_EVERYONE|MUTUAL_FOLLOW_FRIENDS|SELF_ONLY), gbp_topic (STANDARD|EVENT|OFFER), gbp_cta (LEARN_MORE|BOOK|ORDER|SHOP|SIGN_UP|CALL), gbp_cta_url, wa_kind (status|group), wa_to (grup jid), first_comment, alt, made_for_kids, shorts, playlist.',
	);

	$tools['flxsm_accounts'] = array(
		'title'       => 'Flux Social: bağlı hesaplar',
		'description' => 'Bağlı sosyal medya hesapları (id, ağ, ad, durum, yetenekler: metin sınırı, görsel/video sayısı, düzenleme/silme desteği). Gönderi oluştururken accounts alanına bu id\'ler yazılır.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => new stdClass(),
		),
		'run'         => function () {
			$out = array();
			foreach ( flxsm_accounts() as $a ) {
				$c     = (array) $a['caps'];
				$out[] = array(
					'id'       => $a['id'],
					'network'  => $a['provider'],
					'name'     => $a['name'],
					'username' => $a['username'],
					'status'   => $a['status'],
					'error'    => $a['last_error'] ?? '',
					'limits'   => array(
						'max_text'    => $c['max_text'] ?? 0,
						'max_images'  => $c['max_images'] ?? 0,
						'max_videos'  => $c['max_videos'] ?? 0,
						'needs_media' => ! empty( $c['needs_media'] ),
						'edit'        => ! empty( $c['edit'] ),
						'delete'      => ! empty( $c['delete'] ),
					),
					'boards'   => $a['boards'] ?? null,
				);
			}
			return array(
				'accounts' => $out,
				'timezone' => wp_timezone_string(),
				'now'      => wp_date( 'Y-m-d H:i' ),
			);
		},
	);

	$tools['flxsm_posts'] = array(
		'title'       => 'Flux Social: gönderiler',
		'description' => 'Sosyal medya gönderileri (yeniden eskiye). status: draft,pending,scheduled,publishing,published,partial,failed (virgülle birden çok); from/to: "Y-m-d" ya da "Y-m-d H:i" (site saati); q: metin arama.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'status'  => array( 'type' => 'string' ),
				'account' => array( 'type' => 'integer' ),
				'network' => array( 'type' => 'string' ),
				'q'       => array( 'type' => 'string' ),
				'from'    => array( 'type' => 'string' ),
				'to'      => array( 'type' => 'string' ),
				'page'    => array( 'type' => 'integer', 'minimum' => 1 ),
				'per'     => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50 ),
			),
		),
		'run'         => function ( $a ) {
			$r = flxsm_query_posts(
				array(
					'status'   => (string) ( $a['status'] ?? '' ),
					'account'  => (int) ( $a['account'] ?? 0 ),
					'provider' => (string) ( $a['network'] ?? '' ),
					'q'        => (string) ( $a['q'] ?? '' ),
					'from'     => max( 0, flxsm_mcp_time( $a['from'] ?? '' ) ),
					'to'       => max( 0, flxsm_mcp_time( $a['to'] ?? '' ) ),
					'page'     => (int) ( $a['page'] ?? 1 ),
					'per'      => min( 50, (int) ( $a['per'] ?? 20 ) ),
					'full'     => false,
				)
			);
			$r['items'] = array_map( 'flxsm_mcp_post', $r['items'] );
			return $r;
		},
	);

	$tools['flxsm_post_get'] = array(
		'title'       => 'Flux Social: gönderi ayrıntısı',
		'description' => 'Tek gönderi: hesap başına metin, seçenekler, ağdaki bağlantı, hata ve metrikler.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array( 'id' => array( 'type' => 'integer' ) ),
		),
		'run'         => function ( $a ) {
			$p = flxsm_post_row( (int) $a['id'] );
			return $p ? flxsm_mcp_post( flxsm_post_public( $p ) ) : new WP_Error( 'flxsm_not_found', 'Gönderi yok.' );
		},
	);

	$tools['flxsm_post_create'] = array(
		'title'       => 'Flux Social: gönderi oluştur / zamanla / gönder',
		'description' => 'Yeni sosyal medya gönderisi. content: ortak metin; accounts: hesap id\'leri (flxsm_accounts); per_account: {"<hesap id>": {"content": "o ağa özel metin", "options": {...}}}; media_ids: ortam kütüphanesi id; media_urls: indirilecek görsel/video adresleri. action: draft (varsayılan) | submit (onaya gönder) | schedule (schedule_at zamanında) | now (hemen yayınla). schedule_at: "Y-m-d H:i" site saati ya da ISO 8601. Yayın yetkisi olmayan kullanıcıda schedule/now onaya düşer. "now" ve "schedule" geri alınamaz şekilde ağa gönderir; kullanıcı açıkça istemedikçe draft kullanın.',
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'required'   => array( 'content', 'accounts' ),
			'properties' => array(
				'content'     => array( 'type' => 'string' ),
				'title'       => array( 'type' => 'string', 'description' => 'İç başlık (listede görünür)' ),
				'accounts'    => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'per_account' => array(
					'type'                 => 'object',
					'additionalProperties' => array(
						'type'       => 'object',
						'properties' => array(
							'content'   => array( 'type' => 'string' ),
							'media_ids' => array(
								'type'  => 'array',
								'items' => array( 'type' => 'integer' ),
							),
							'options'   => $opt,
						),
					),
				),
				'media_ids'   => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'media_urls'  => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'tags'        => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'action'      => array(
					'type' => 'string',
					'enum' => array( 'draft', 'submit', 'schedule', 'now' ),
				),
				'schedule_at' => array( 'type' => 'string' ),
				'repeat_days' => array( 'type' => 'integer', 'description' => 'Yayından N gün sonra kopyası yeniden zamanlanır (0 = yok)' ),
			),
		),
		'run'         => function ( $a ) {
			return flxsm_mcp_save( $a, 0 );
		},
	);

	$tools['flxsm_post_update'] = array(
		'title'       => 'Flux Social: gönderiyi düzenle',
		'description' => 'Henüz yayınlanmamış gönderiyi düzenler (yalnız verilen alanlar değişir). accounts verilirse hesap listesi bununla değişir. action ile zamanla/gönder/taslağa al/iptal: draft | submit | schedule | now | cancel | approve. Yayınlanmış gönderiyi ağda düzenlemek için flxsm_remote_edit.',
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer' ),
				'content'     => array( 'type' => 'string' ),
				'title'       => array( 'type' => 'string' ),
				'accounts'    => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'per_account' => array( 'type' => 'object' ),
				'media_ids'   => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'media_urls'  => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'tags'        => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'action'      => array(
					'type' => 'string',
					'enum' => array( 'draft', 'submit', 'schedule', 'now', 'cancel', 'approve' ),
				),
				'schedule_at' => array( 'type' => 'string' ),
			),
		),
		'run'         => function ( $a ) {
			return flxsm_mcp_save( $a, (int) $a['id'] );
		},
	);

	$tools['flxsm_post_delete'] = array(
		'title'       => 'Flux Social: gönderiyi sil',
		'description' => 'Gönderiyi listeden siler. remote=true ise yayınlanmış olduğu ağlardan da silinir (destekleyen ağlarda; Instagram ve TikTok API ile silinemez).',
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'     => array( 'type' => 'integer' ),
				'remote' => array( 'type' => 'boolean' ),
			),
		),
		'run'         => function ( $a ) {
			$p = flxsm_post_row( (int) $a['id'] );
			if ( ! $p ) {
				return new WP_Error( 'flxsm_not_found', 'Gönderi yok.' );
			}
			if ( ! flxsm_can_edit_post( $p ) ) {
				return new WP_Error( 'mcp_cap', 'Bu gönderiyi silme yetkiniz yok.' );
			}
			$left = flxsm_delete_post( (int) $p['id'], ! empty( $a['remote'] ) );
			return $left ? array(
				'deleted' => false,
				'errors'  => $left,
			) : array( 'deleted' => true );
		},
	);

	$tools['flxsm_remote_edit'] = array(
		'title'       => 'Flux Social: ağdaki gönderiyi düzenle ya da sil',
		'description' => 'Yayınlanmış gönderinin tek bir ağdaki halini düzenler (content) ya da siler (delete=true). target_id: flxsm_post_get çıktısındaki targets[].target_id. Düzenleme Facebook, YouTube, LinkedIn, Pinterest, Google İşletme ve WhatsApp\'ta desteklenir.',
		'cap'         => FLXSM_CAP_PUBLISH,
		'schema'      => array(
			'type'       => 'object',
			'required'   => array( 'target_id' ),
			'properties' => array(
				'target_id' => array( 'type' => 'integer' ),
				'content'   => array( 'type' => 'string' ),
				'options'   => $opt,
				'delete'    => array( 'type' => 'boolean' ),
			),
		),
		'run'         => function ( $a ) {
			$tid = (int) $a['target_id'];
			$res = ! empty( $a['delete'] ) ? flxsm_remote_delete( $tid ) : flxsm_remote_edit( $tid, isset( $a['content'] ) ? (string) $a['content'] : null, (array) ( $a['options'] ?? array() ) );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$t = flxsm_target_row( $tid );
			return flxsm_mcp_post( flxsm_post_public( flxsm_post_row( $t['post_id'] ) ) );
		},
	);

	$tools['flxsm_stats'] = array(
		'title'       => 'Flux Social: paylaşım panosu',
		'description' => 'Son 7/30/90 günün özeti: yayınlanan/başarısız/zamanlanmış sayıları, ağ başına etkileşim ve görüntülenme, takipçi değişimi, en iyi gönderiler, yaklaşan gönderiler, son hatalar.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'days' => array(
					'type' => 'integer',
					'enum' => array( 7, 30, 90 ),
				),
			),
		),
		'run'         => function ( $a ) {
			$d             = flxsm_dashboard( (int) ( $a['days'] ?? 30 ) );
			$d['upcoming'] = array_map( 'flxsm_mcp_post', $d['upcoming'] );
			unset( $d['by_day'] );
			return $d;
		},
	);

	$tools['flxsm_ai_write'] = array(
		'title'       => 'Flux Social: ağlara göre metin uyarla',
		'description' => 'Bir gönderi metnini her ağın üslubuna göre ayrı ayrı yeniden yazar (adapt) ya da kısaltır (shorten, limit karakter). Çıktıyı flxsm_post_create per_account alanında kullanın.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'required'   => array( 'text' ),
			'properties' => array(
				'text'     => array( 'type' => 'string' ),
				'mode'     => array(
					'type' => 'string',
					'enum' => array( 'adapt', 'shorten', 'improve', 'hashtags' ),
				),
				'networks' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'limit'    => array( 'type' => 'integer' ),
			),
		),
		'run'         => function ( $a ) {
			$res = flxsm_ai_write(
				(string) ( $a['mode'] ?? 'adapt' ),
				array(
					'text'      => (string) $a['text'],
					'providers' => array_map( 'sanitize_key', (array) ( $a['networks'] ?? array() ) ),
					'limit'     => (int) ( $a['limit'] ?? 280 ),
				)
			);
			return is_wp_error( $res ) ? $res : array( 'result' => $res );
		},
	);

	return $tools;
}

/** Oluştur/düzenle ortak yolu. */
function flxsm_mcp_save( array $a, $id ) {
	$old = $id ? flxsm_post_row( $id ) : null;
	if ( $id && ! $old ) {
		return new WP_Error( 'flxsm_not_found', 'Gönderi yok.' );
	}
	if ( $old && ! flxsm_can_edit_post( $old ) ) {
		return new WP_Error( 'mcp_cap', 'Bu gönderiyi değiştirme yetkiniz yok.' );
	}
	$d = array( 'source' => 'ai' );
	foreach ( array( 'content', 'title', 'tags', 'repeat_days' ) as $k ) {
		if ( isset( $a[ $k ] ) ) {
			$d[ $k ] = $a[ $k ];
		}
	}
	if ( isset( $a['media_ids'] ) || isset( $a['media_urls'] ) ) {
		$m = flxsm_mcp_media( $a );
		if ( is_wp_error( $m ) ) {
			return $m;
		}
		$d['media'] = $m;
	}
	if ( isset( $a['accounts'] ) || isset( $a['per_account'] ) ) {
		$t = flxsm_mcp_targets( $a );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$d['targets'] = $t;
	}
	if ( isset( $a['schedule_at'] ) ) {
		$ts = flxsm_mcp_time( $a['schedule_at'] );
		if ( $ts < 0 ) {
			return new WP_Error( 'flxsm_time', 'schedule_at okunamadı. Örnek: 2026-11-02 14:30' );
		}
		$d['scheduled_at'] = $ts;
	}
	$act = (string) ( $a['action'] ?? ( $id ? '' : 'draft' ) );
	if ( 'schedule' === $act && empty( $d['scheduled_at'] ) && ( ! $old || ! (int) $old['scheduled_at'] ) ) {
		return new WP_Error( 'flxsm_time', 'schedule için schedule_at gerekli.' );
	}
	if ( 'approve' === $act && ! flxsm_can_publish() ) {
		return new WP_Error( 'mcp_cap', 'Onay yetkiniz yok.' );
	}
	if ( '' !== $act ) {
		$d['action'] = $act;
	}
	$res = flxsm_save_post( $d, $id );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	return flxsm_mcp_post( flxsm_post_public( flxsm_post_row( $res ) ) );
}
