<?php
/**
 * Menü deposu: yazı türü + meta, anahtar, siteler ve değişiklik geçmişi.
 *
 * Menü verisi `_flxq_data` meta alanında JSON metni olarak durur (bilinmeyen alanlar ve {} / [] ayrımı korunsun diye
 * nesne olarak çözülür). Anahtarın kendisi saklanmaz: yalnız sha256 özeti ve kanca imza sırrı.
 *
 * @package FLX_QR_Menu
 */

defined( 'ABSPATH' ) || exit;

const FLXQ_ICONS   = array( 'yemek', 'salata', 'icecek', 'tatli', 'fidan' );
const FLXQ_ACTIONS = array( 'create', 'save', 'restore', 'import', 'key', 'toggle', 'mcp', 'address', 'editors', 'images' );

/* ---------- Okuma ---------- */

/** @return WP_Post|null */
function flxq_get_post( $id ) {
	$post = get_post( (int) $id );
	return ( $post && 'flxq_menu' === $post->post_type ) ? $post : null;
}

function flxq_menu_json( $id ) {
	$raw = get_post_meta( (int) $id, '_flxq_data', true );
	return is_string( $raw ) && '' !== $raw ? $raw : '{"etkinlik":{"baslik":""},"genelBilgi":[],"kategoriler":[]}';
}

/** Menü verisi nesne olarak (stdClass); $assoc true ise dizi. */
function flxq_menu_data( $id, $assoc = false ) {
	$d = json_decode( flxq_menu_json( $id ), $assoc );
	// 1.3.1 öncesi kayıtlarda kapanis yazılmamış olabilir: tarihe göre kapanmasın diye açık say.
	if ( $assoc && is_array( $d ) && isset( $d['etkinlik'] ) && is_array( $d['etkinlik'] ) && ! isset( $d['etkinlik']['kapanis'] ) ) {
		$d['etkinlik']['kapanis'] = false;
	} elseif ( ! $assoc && is_object( $d ) && isset( $d->etkinlik ) && is_object( $d->etkinlik ) && ! isset( $d->etkinlik->kapanis ) ) {
		$d->etkinlik->kapanis = false;
	}
	return $d;
}

function flxq_menu_version( $id ) {
	return (int) get_post_meta( (int) $id, '_flxq_version', true );
}

function flxq_menu_enabled( $id ) {
	return '0' !== (string) get_post_meta( (int) $id, '_flxq_enabled', true );
}

/** Menü özeti: liste, MCP ve düzenleme ekranı için (gizli değer içermez). */
function flxq_menu_info( $id ) {
	$post = flxq_get_post( $id );
	if ( ! $post ) {
		return null;
	}
	$id      = (int) $post->ID;
	$stats   = get_post_meta( $id, '_flxq_stats', true );
	$stats   = wp_parse_args( is_array( $stats ) ? $stats : array(), array( 'items' => 0, 'soldout' => 0, 'hidden' => 0, 'categories' => 0 ) );
	$updated = (int) get_post_meta( $id, '_flxq_updated', true );
	$uid     = (int) get_post_meta( $id, '_flxq_updated_by', true );
	$sites   = flxq_get_sites( $id );
	$last    = 0;
	foreach ( $sites as $s ) {
		$last = max( $last, (int) $s['last'] );
	}
	return array(
		'id'           => $id,
		'name'         => $post->post_title,
		'status'       => $post->post_status,
		'version'      => flxq_menu_version( $id ),
		'enabled'      => flxq_menu_enabled( $id ),
		'updated'      => $updated,
		'updated_iso'  => $updated ? wp_date( 'c', $updated ) : '',
		'updated_by'   => $uid,
		'updated_name' => flxq_user_name( $uid ),
		'stats'        => $stats,
		'key'          => array(
			'has'     => (bool) get_post_meta( $id, '_flxq_key_hash', true ),
			'last4'   => (string) get_post_meta( $id, '_flxq_key_last4', true ),
			'created' => (int) get_post_meta( $id, '_flxq_key_created', true ),
		),
		'sites'        => $sites,
		'last_seen'    => $last,
		'author'       => (int) $post->post_author,
		'author_name'  => flxq_user_name( (int) $post->post_author ),
		'slug'         => flxq_menu_slug( $id ),
		'host'         => flxq_menu_host( $id ),
		'editors'      => flxq_menu_editors( $id ),
		'urls'         => flxq_menu_urls( $id ),
	);
}

function flxq_user_name( $uid ) {
	if ( ! $uid ) {
		return '';
	}
	$u = get_userdata( (int) $uid );
	return $u ? $u->display_name : '#' . (int) $uid;
}

/** @return int[] */
function flxq_menu_ids( $status = 'publish' ) {
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'      => 'flxq_menu',
				'post_status'    => $status,
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		)
	);
}

/* ---------- Doğrulama ---------- */

/** Türkçe karakterleri sadeleştirip [a-z0-9-] kimlik üretir. */
function flxq_slug( $text ) {
	$s = remove_accents( (string) $text );
	$s = strtolower( strtr( $s, array( 'İ' => 'i', 'I' => 'i', 'ı' => 'i' ) ) );
	$s = preg_replace( '/[^a-z0-9]+/', '-', $s );
	return trim( substr( (string) $s, 0, 40 ), '-' );
}

function flxq_safe_url( $v ) {
	$v = trim( (string) $v );
	if ( preg_match( '/^(javascript|data|vbscript|file):/i', $v ) ) {
		return '';
	}
	return $v;
}

/** fiyat/eskiFiyat: sayı, null ya da metin ("İkramımızdır"). "150" / "150,5" sayıya çevrilir. */
function flxq_price( $v ) {
	if ( null === $v || is_bool( $v ) ) {
		return null;
	}
	if ( is_int( $v ) || is_float( $v ) ) {
		return $v + 0;
	}
	$s = trim( (string) $v );
	if ( '' === $s ) {
		return null;
	}
	$n = str_replace( ',', '.', $s );
	if ( is_numeric( $n ) ) {
		return $n + 0;
	}
	return mb_substr( $s, 0, 60 );
}

/**
 * Menü JSON'unu doğrular ve tipleri düzeltir. Bilinmeyen alanlara dokunmaz.
 *
 * @param mixed $data json_decode( $json ) sonucu (nesne).
 * @return object|WP_Error
 */
function flxq_normalize( $data ) {
	if ( is_array( $data ) ) {
		$data = json_decode( wp_json_encode( $data ) );
	}
	if ( ! is_object( $data ) ) {
		return new WP_Error( 'flxq_invalid', __( 'Menü verisi bir JSON nesnesi olmalı.', 'flx-qr-menu' ) );
	}
	if ( ! isset( $data->kategoriler ) || ! is_array( $data->kategoriler ) ) {
		return new WP_Error( 'flxq_invalid', __( '"kategoriler" listesi bulunamadı.', 'flx-qr-menu' ) );
	}
	if ( ! isset( $data->etkinlik ) || ! is_object( $data->etkinlik ) ) {
		$data->etkinlik = (object) array( 'baslik' => '' );
	}
	$e = $data->etkinlik;
	foreach ( array( 'baslik', 'slogan', 'site' ) as $f ) {
		if ( isset( $e->$f ) && ! is_string( $e->$f ) ) {
			$e->$f = is_scalar( $e->$f ) ? (string) $e->$f : '';
		}
	}
	if ( isset( $e->gunler ) ) {
		$gunler = array();
		foreach ( is_array( $e->gunler ) ? $e->gunler : array() as $g ) {
			if ( ! is_object( $g ) ) {
				continue;
			}
			$g->etiket = isset( $g->etiket ) ? (string) $g->etiket : '';
			$g->tarih  = isset( $g->tarih ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $g->tarih ) ? (string) $g->tarih : '';
			$gunler[]  = $g;
		}
		$e->gunler = $gunler;
	}
	// Tarihe göre otomatik kapanış yok: kapanis yalnız elle (Menü aktif kutusu) değişir; yazılmamışsa menü açık.
	$e->kapanis = property_exists( $e, 'kapanis' ) && null !== $e->kapanis ? (bool) $e->kapanis : false;
	if ( ! isset( $data->genelBilgi ) || ! is_array( $data->genelBilgi ) ) {
		$data->genelBilgi = array();
	}
	$data->genelBilgi = array_values( array_filter( array_map( 'strval', array_filter( $data->genelBilgi, 'is_scalar' ) ), 'strlen' ) );

	$seen = array();
	$cats = array();
	foreach ( $data->kategoriler as $i => $k ) {
		if ( ! is_object( $k ) ) {
			continue;
		}
		$k->baslik = isset( $k->baslik ) ? (string) $k->baslik : '';
		$id        = flxq_slug( isset( $k->id ) && '' !== (string) $k->id ? $k->id : $k->baslik );
		if ( '' === $id ) {
			$id = 'kategori';
		}
		$base = $id;
		$n    = 2;
		while ( isset( $seen[ $id ] ) ) {
			$id = $base . '-' . $n++;
		}
		$seen[ $id ] = true;
		$k->id       = $id;
		if ( isset( $k->not ) ) {
			$k->not = (string) $k->not;
		}
		if ( ! isset( $k->ikon ) || ! is_string( $k->ikon ) || '' === $k->ikon ) {
			$k->ikon = 'yemek';
		} else {
			$k->ikon = sanitize_key( $k->ikon );
		}
		if ( isset( $k->sekme ) ) {
			$k->sekme = (bool) $k->sekme;
		}
		$items = array();
		foreach ( isset( $k->urunler ) && is_array( $k->urunler ) ? $k->urunler : array() as $u ) {
			if ( ! is_object( $u ) ) {
				continue;
			}
			if ( property_exists( $u, 'araBaslik' ) ) {
				$u->araBaslik = (string) $u->araBaslik; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
			} else {
				$u->ad = isset( $u->ad ) ? (string) $u->ad : '';
				if ( property_exists( $u, 'fiyat' ) ) {
					$u->fiyat = flxq_price( $u->fiyat );
				}
				if ( property_exists( $u, 'eskiFiyat' ) ) {
					$u->eskiFiyat = flxq_price( $u->eskiFiyat ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName
				}
				foreach ( array( 'aciklama', 'birim', 'bittiNot' ) as $f ) {
					if ( isset( $u->$f ) ) {
						$u->$f = is_scalar( $u->$f ) ? (string) $u->$f : '';
					}
				}
				foreach ( array( 'gorsel', 'link' ) as $f ) {
					if ( isset( $u->$f ) ) {
						$u->$f = is_string( $u->$f ) ? flxq_safe_url( $u->$f ) : '';
					}
				}
				foreach ( array( 'bitti', 'buyukGorsel' ) as $f ) {
					if ( isset( $u->$f ) ) {
						$u->$f = (bool) $u->$f;
					}
				}
				if ( isset( $u->rozet ) && ! is_bool( $u->rozet ) ) {
					$u->rozet = is_string( $u->rozet ) && '' !== trim( $u->rozet ) ? mb_substr( trim( $u->rozet ), 0, 40 ) : false;
				}
			}
			if ( isset( $u->gizli ) ) {
				$u->gizli = (bool) $u->gizli;
			}
			$items[] = $u;
		}
		$k->urunler = $items;
		$cats[]     = $k;
	}
	$data->kategoriler = $cats;
	if ( strlen( (string) wp_json_encode( $data ) ) > 1048576 ) {
		return new WP_Error( 'flxq_too_big', __( 'Menü verisi çok büyük (en fazla 1 MB).', 'flx-qr-menu' ) );
	}
	return $data;
}

function flxq_encode( $data ) {
	return (string) wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

/** @return array{items:int,soldout:int,hidden:int,categories:int} */
function flxq_stats( $data ) {
	$st = array( 'items' => 0, 'soldout' => 0, 'hidden' => 0, 'categories' => 0 );
	foreach ( isset( $data->kategoriler ) ? (array) $data->kategoriler : array() as $k ) {
		++$st['categories'];
		foreach ( isset( $k->urunler ) ? (array) $k->urunler : array() as $u ) {
			if ( property_exists( $u, 'araBaslik' ) ) {
				continue;
			}
			++$st['items'];
			if ( ! empty( $u->bitti ) ) {
				++$st['soldout'];
			}
			if ( ! empty( $u->gizli ) ) {
				++$st['hidden'];
			}
		}
	}
	return $st;
}

/* ---------- Yazma ---------- */

function flxq_write_data( $id, $name, $data, $version ) {
	update_post_meta( $id, '_flxq_data', wp_slash( flxq_encode( $data ) ) );
	update_post_meta( $id, '_flxq_version', (int) $version );
	update_post_meta( $id, '_flxq_updated', time() );
	update_post_meta( $id, '_flxq_updated_by', get_current_user_id() );
	update_post_meta( $id, '_flxq_stats', flxq_stats( $data ) );
	$post = flxq_get_post( $id );
	if ( $post && $post->post_title !== $name ) {
		wp_update_post( array( 'ID' => $id, 'post_title' => wp_slash( $name ) ) );
	}
}

function flxq_clean_name( $name ) {
	$name = trim( sanitize_text_field( (string) $name ) );
	return '' === $name ? __( 'Adsız menü', 'flx-qr-menu' ) : mb_substr( $name, 0, 120 );
}

/**
 * Yeni menü.
 *
 * @return int|WP_Error
 */
function flxq_create_menu( $name, $data, $action = 'create', $summary = '' ) {
	$data = flxq_normalize( $data );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$name = flxq_clean_name( $name );
	$id   = wp_insert_post(
		array(
			'post_type'   => 'flxq_menu',
			'post_status' => 'publish',
			'post_title'  => wp_slash( $name ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_post_meta( $id, '_flxq_enabled', '1' );
	flxq_write_data( $id, $name, $data, 1 );
	if ( '' === $summary ) {
		$summary = 'import' === $action ? __( 'JSON\'dan içe aktarıldı', 'flx-qr-menu' ) : __( 'Menü oluşturuldu', 'flx-qr-menu' );
	}
	flxq_history_add( $id, 1, $action, $summary, flxq_encode( $data ) );
	return (int) $id;
}

/**
 * Menüyü kaydeder (iyimser eşzamanlılık: $args['base_version'] sunucudakinden eskiyse 409).
 *
 * @param array $args action, base_version (null = kontrol yok), force, summary (önek).
 * @return array|WP_Error {version, unchanged, summary}
 */
function flxq_save_menu( $id, $name, $data, $args = array() ) {
	$args = wp_parse_args( $args, array( 'action' => 'save', 'base_version' => null, 'force' => false, 'summary' => '' ) );
	$post = flxq_get_post( $id );
	if ( ! $post || 'trash' === $post->post_status ) {
		return new WP_Error( 'flxq_not_found', __( 'Menü bulunamadı.', 'flx-qr-menu' ), array( 'status' => 404 ) );
	}
	$data = flxq_normalize( $data );
	if ( is_wp_error( $data ) ) {
		$data->add_data( array( 'status' => 400 ) );
		return $data;
	}
	$name    = null === $name ? $post->post_title : flxq_clean_name( $name );
	$current = flxq_menu_version( $id );
	if ( null !== $args['base_version'] && (int) $args['base_version'] !== $current && ! $args['force'] ) {
		return new WP_Error(
			'flxq_conflict',
			__( 'Bu menü siz düzenlerken başka bir yerden kaydedildi.', 'flx-qr-menu' ),
			array(
				'status'  => 409,
				'version' => $current,
				'changes' => flxq_history_since( $id, (int) $args['base_version'] ),
			)
		);
	}
	$old_json = flxq_menu_json( $id );
	$new_json = flxq_encode( $data );
	$old      = json_decode( $old_json, true );
	$new      = json_decode( $new_json, true );
	if ( $old === $new && $name === $post->post_title && 'restore' !== $args['action'] ) {
		return array( 'version' => $current, 'unchanged' => true, 'summary' => '' );
	}
	$lines = flxq_diff_summary( is_array( $old ) ? $old : array(), $new );
	if ( $name !== $post->post_title ) {
		/* translators: %s: new menu name */
		array_unshift( $lines, sprintf( __( 'Menü adı: %s', 'flx-qr-menu' ), $name ) );
	}
	$summary = flxq_join_summary( $lines );
	if ( '' !== $args['summary'] ) {
		$summary = $args['summary'] . ( '' !== $summary ? ': ' . $summary : '' );
	}
	$version = $current + 1;
	flxq_write_data( $id, $name, $data, $version );
	flxq_history_add( $id, $version, $args['action'], $summary, $new_json );
	flxq_queue_ping( $id );
	return array( 'version' => $version, 'unchanged' => false, 'summary' => $summary );
}

function flxq_set_enabled( $id, $enabled ) {
	$enabled = (bool) $enabled;
	if ( flxq_menu_enabled( $id ) === $enabled ) {
		return;
	}
	update_post_meta( $id, '_flxq_enabled', $enabled ? '1' : '0' );
	flxq_history_add(
		$id,
		flxq_menu_version( $id ),
		'toggle',
		$enabled ? __( 'Menü yayına alındı', 'flx-qr-menu' ) : __( 'Menü yayından kaldırıldı', 'flx-qr-menu' ),
		null
	);
	flxq_queue_ping( $id );
}

/**
 * Geçmişteki bir sürüme döner (yeni sürüm olarak kaydedilir).
 *
 * @return array|WP_Error
 */
function flxq_restore( $id, $hid, $base_version = null ) {
	$row = flxq_history_get( $id, $hid );
	if ( ! $row || null === $row['data'] ) {
		return new WP_Error( 'flxq_not_found', __( 'Bu kayıtta menü verisi yok.', 'flx-qr-menu' ), array( 'status' => 404 ) );
	}
	return flxq_save_menu(
		$id,
		null,
		json_decode( $row['data'] ),
		array(
			'action'       => 'restore',
			'base_version' => $base_version,
			/* translators: %d: version number */
			'summary'      => sprintf( __( 'v%d sürümüne dönüldü', 'flx-qr-menu' ), (int) $row['version'] ),
		)
	);
}

/** @return int|WP_Error */
function flxq_duplicate_menu( $id ) {
	$post = flxq_get_post( $id );
	if ( ! $post ) {
		return new WP_Error( 'flxq_not_found', __( 'Menü bulunamadı.', 'flx-qr-menu' ) );
	}
	/* translators: %s: menu name */
	$name = sprintf( __( '%s (kopya)', 'flx-qr-menu' ), $post->post_title );
	/* translators: %s: source menu name */
	return flxq_create_menu( $name, flxq_menu_data( $id ), 'create', sprintf( __( '"%s" menüsünden kopyalandı', 'flx-qr-menu' ), $post->post_title ) );
}

/** Kalıcı silme: menü, anahtar eşlemesi ve geçmiş. */
function flxq_delete_menu( $id ) {
	global $wpdb;
	if ( ! flxq_get_post( $id ) ) {
		return;
	}
	flxq_key_map_remove( $id );
	$wpdb->delete( flxq_history_table(), array( 'menu_id' => (int) $id ), array( '%d' ) );
	wp_delete_post( (int) $id, true );
	flxq_rebuild_routes();
}

/* ---------- Anahtar ---------- */

function flxq_random_key() {
	$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
	$key   = 'flxq_';
	for ( $i = 0; $i < 40; $i++ ) {
		$key .= $chars[ random_int( 0, 61 ) ];
	}
	return $key;
}

function flxq_key_map() {
	$m = get_option( 'flxq_keys', array() );
	return is_array( $m ) ? $m : array();
}

function flxq_key_map_remove( $id ) {
	$map = flxq_key_map();
	foreach ( $map as $h => $mid ) {
		if ( (int) $mid === (int) $id ) {
			unset( $map[ $h ] );
		}
	}
	update_option( 'flxq_keys', $map, true );
	return $map;
}

/** Yeni anahtar üretir, eskisini hemen geçersiz kılar. Anahtarın kendisi yalnız burada döner. */
function flxq_generate_key( $id ) {
	$key  = flxq_random_key();
	$hash = hash( 'sha256', $key );
	$map  = flxq_key_map_remove( $id );

	$map[ $hash ] = (int) $id;
	update_option( 'flxq_keys', $map, true );
	update_post_meta( $id, '_flxq_key_hash', $hash );
	update_post_meta( $id, '_flxq_hook_secret', hash_hmac( 'sha256', 'flxq-hook', $key ) );
	update_post_meta( $id, '_flxq_key_last4', substr( $key, -4 ) );
	update_post_meta( $id, '_flxq_key_created', time() );
	/* translators: %s: last 4 characters of the key */
	flxq_history_add( $id, flxq_menu_version( $id ), 'key', sprintf( __( 'Yeni bağlantı anahtarı oluşturuldu (…%s)', 'flx-qr-menu' ), substr( $key, -4 ) ), null );
	return $key;
}

/** Anahtara ait yayındaki menünün kimliği, yoksa 0. */
function flxq_find_menu_by_key( $key ) {
	$key = (string) $key;
	if ( ! preg_match( '/^flxq_[A-Za-z0-9]{40}$/', $key ) ) {
		return 0;
	}
	$hash = hash( 'sha256', $key );
	$map  = flxq_key_map();
	if ( empty( $map[ $hash ] ) ) {
		return 0;
	}
	$id   = (int) $map[ $hash ];
	$post = flxq_get_post( $id );
	if ( ! $post || 'publish' !== $post->post_status ) {
		return 0;
	}
	return hash_equals( (string) get_post_meta( $id, '_flxq_key_hash', true ), $hash ) ? $id : 0;
}

/* ---------- Bağlı siteler ---------- */

function flxq_get_sites( $id ) {
	$s = get_post_meta( (int) $id, '_flxq_sites', true );
	return is_array( $s ) ? $s : array();
}

function flxq_clean_http_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url || strlen( $url ) > 300 || ! preg_match( '#^https?://#i', $url ) ) {
		return '';
	}
	// Özel/yerel IP denetimi bildirim gönderilirken yapılır (burada DNS sorgusu yok, feed hızlı kalsın).
	return esc_url_raw( $url, array( 'http', 'https' ) );
}

/**
 * Feed isteğinden gelen siteyi kaydeder. Yazma site başına en fazla 5 dakikada bir; menü başına en fazla 20 site
 * (dolunca en uzun süredir görülmeyen çıkar).
 */
function flxq_register_site( $id, $site, $hook, $client, $ip ) {
	$site   = flxq_clean_http_url( $site );
	$hook   = flxq_clean_http_url( $hook );
	$client = mb_substr( sanitize_text_field( (string) $client ), 0, 60 );
	$ip     = (string) $ip;
	$k      = '' !== $site ? untrailingslashit( strtolower( $site ) ) : 'ip:' . $ip;
	$sites  = flxq_get_sites( $id );
	$now    = time();
	if ( isset( $sites[ $k ] ) ) {
		$s = $sites[ $k ];
		if ( $now - (int) $s['last'] < 300 && $s['hook'] === $hook && $s['client'] === $client && $s['ip'] === $ip ) {
			return;
		}
		$sites[ $k ] = array_merge( $s, array( 'hook' => $hook, 'client' => $client, 'ip' => $ip, 'last' => $now ) );
	} else {
		if ( count( $sites ) >= FLXQ_MAX_SITES ) {
			uasort(
				$sites,
				function ( $a, $b ) {
					return (int) $a['last'] - (int) $b['last'];
				}
			);
			$sites = array_slice( $sites, count( $sites ) - FLXQ_MAX_SITES + 1, null, true );
		}
		$sites[ $k ] = array(
			'url'    => $site,
			'hook'   => $hook,
			'client' => $client,
			'ip'     => $ip,
			'first'  => $now,
			'last'   => $now,
		);
	}
	update_post_meta( $id, '_flxq_sites', $sites );
}

function flxq_remove_site( $id, $k ) {
	$sites = flxq_get_sites( $id );
	if ( isset( $sites[ $k ] ) ) {
		unset( $sites[ $k ] );
		update_post_meta( $id, '_flxq_sites', $sites );
		return true;
	}
	return false;
}

/** Göreli görsel yollarını (img/x.jpg) önizlemek için en son görülen sitenin adresi. */
function flxq_preview_base( $id ) {
	$best = '';
	$last = 0;
	foreach ( flxq_get_sites( $id ) as $s ) {
		if ( ! empty( $s['url'] ) && (int) $s['last'] > $last ) {
			$best = $s['url'];
			$last = (int) $s['last'];
		}
	}
	return $best ? trailingslashit( $best ) : '';
}

/* ---------- Geçmiş ---------- */

function flxq_history_add( $menu_id, $version, $action, $summary, $data_json ) {
	global $wpdb;
	$table = flxq_history_table();
	$wpdb->insert(
		$table,
		array(
			'menu_id'     => (int) $menu_id,
			'version'     => (int) $version,
			'user_id'     => get_current_user_id(),
			'created_gmt' => gmdate( 'Y-m-d H:i:s' ),
			'action'      => in_array( $action, FLXQ_ACTIONS, true ) ? $action : 'save',
			'summary'     => mb_substr( (string) $summary, 0, 4000 ),
			'data'        => $data_json,
		),
		array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
	);
	// Menü başına son FLXQ_MAX_REVISIONS kayıt tutulur.
	$cut = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE menu_id = %d ORDER BY id DESC LIMIT 1 OFFSET %d", (int) $menu_id, FLXQ_MAX_REVISIONS - 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $cut ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE menu_id = %d AND id < %d", (int) $menu_id, (int) $cut ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}

function flxq_history_row_out( $r ) {
	$ts = strtotime( $r['created_gmt'] . ' UTC' );
	return array(
		'id'       => (int) $r['id'],
		'version'  => (int) $r['version'],
		'user'     => flxq_user_name( (int) $r['user_id'] ),
		'time'     => $ts,
		'time_str' => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ),
		'action'   => $r['action'],
		'summary'  => (string) $r['summary'],
		'has_data' => ! empty( $r['has_data'] ),
	);
}

/** @return array{rows:array,total:int} */
function flxq_history_list( $menu_id, $page = 1, $per = 20 ) {
	global $wpdb;
	$table = flxq_history_table();
	$page  = max( 1, (int) $page );
	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE menu_id = %d", (int) $menu_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, version, user_id, created_gmt, action, summary, CASE WHEN data IS NULL THEN 0 ELSE 1 END AS has_data FROM {$table} WHERE menu_id = %d ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			(int) $menu_id,
			(int) $per,
			( $page - 1 ) * (int) $per
		),
		ARRAY_A
	);
	return array(
		'rows'  => array_map( 'flxq_history_row_out', (array) $rows ),
		'total' => $total,
	);
}

function flxq_history_get( $menu_id, $hid ) {
	global $wpdb;
	$table = flxq_history_table();
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE menu_id = %d AND id = %d", (int) $menu_id, (int) $hid ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $row ? $row : null;
}

/** Belirtilen sürümden sonraki değişiklik özetleri (409 yanıtında gösterilir). */
function flxq_history_since( $menu_id, $version ) {
	global $wpdb;
	$table = flxq_history_table();
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, version, user_id, created_gmt, action, summary, 0 AS has_data FROM {$table} WHERE menu_id = %d AND version > %d AND data IS NOT NULL ORDER BY id DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			(int) $menu_id,
			(int) $version
		),
		ARRAY_A
	);
	return array_map( 'flxq_history_row_out', (array) $rows );
}

function flxq_history_table_exists() {
	global $wpdb;
	$t = flxq_history_table();
	return $t === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) );
}
