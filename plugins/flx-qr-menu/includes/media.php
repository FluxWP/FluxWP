<?php
/**
 * "Görselleri medya kütüphanesine aktar": menüdeki göreli görsel yollarını (img/x.jpg) bağlı menü sitesinden
 * indirip medya kütüphanesine ekler ve menüde tam adresle değiştirir. Parça parça çalışır (her istekte en çok 6 görsel),
 * böylece paylaşımlı hostingin süre sınırına takılmaz. Aynı kaynak ikinci kez indirilmez (`_flxq_src` meta).
 *
 * Örnek menünün görselleri eklentiyle gelir (assets/sample/img/). Göreli yol bunlardan birine denk geliyorsa görsel ağdan
 * indirilmez, yerel dosyadan eklenir (`_flxq_src` = "bundled:img/x.jpg"); menü sitesi bağlı olmasa da çalışır.
 *
 * @package FLX_QR_Menu
 */

defined( 'ABSPATH' ) || exit;

const FLXQ_IMPORT_BATCH = 6;
const FLXQ_IMPORT_MAX   = 8388608;
const FLXQ_SAMPLE_IMG   = 'assets/sample/';

/** Görsel alanı göreli bir yol mu (img/x.jpg, ./img/x.jpg, /img/x.jpg)? */
function flxq_is_relative_image( $g ) {
	$g = trim( (string) $g );
	return '' !== $g && ! preg_match( '#^([a-z][a-z0-9+.-]*:|//)#i', $g );
}

/** @return string[] Menüdeki farklı göreli görsel yolları. */
function flxq_relative_images( $data ) {
	$out = array();
	foreach ( isset( $data->kategoriler ) ? (array) $data->kategoriler : array() as $k ) {
		foreach ( isset( $k->urunler ) ? (array) $k->urunler : array() as $u ) {
			if ( isset( $u->gorsel ) && flxq_is_relative_image( $u->gorsel ) && ! in_array( $u->gorsel, $out, true ) ) {
				$out[] = (string) $u->gorsel;
			}
		}
	}
	return $out;
}

/** Göreli yolun menü sitesindeki tam adresi. */
function flxq_image_source_url( $base, $g ) {
	$g = trim( (string) $g );
	if ( '/' === substr( $g, 0, 1 ) ) {
		$p = wp_parse_url( $base );
		return $p['scheme'] . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' ) . $g;
	}
	return trailingslashit( $base ) . preg_replace( '#^\./#', '', $g );
}

/**
 * Göreli yol eklentiyle gelen örnek görsellerden birine denk geliyorsa paketteki göreli yolu (img/x.jpg), yoksa ''.
 * Yalnız img/ altındaki düz dosya adları kabul edilir (../ yok).
 */
function flxq_bundled_image( $g ) {
	$rel = ltrim( (string) preg_replace( '#^\./#', '', trim( (string) $g ) ), '/' );
	if ( false !== strpos( $rel, '..' ) || ! preg_match( '#^img/[A-Za-z0-9._-]+$#', $rel ) ) {
		return '';
	}
	return is_file( FLXQ_DIR . FLXQ_SAMPLE_IMG . $rel ) ? $rel : '';
}

/** @return string[] Eklentiyle gelen örnek görsellerin göreli yolları (img/x.jpg). */
function flxq_bundled_images() {
	$out = array();
	foreach ( (array) glob( FLXQ_DIR . FLXQ_SAMPLE_IMG . 'img/*' ) as $f ) {
		if ( preg_match( '/\.(jpe?g|png|gif|webp|svg)$/i', (string) $f ) ) {
			$out[] = 'img/' . basename( $f );
		}
	}
	return $out;
}

/** Örnek görselin tarayıcıdan açılan adresi. */
function flxq_bundled_image_url( $rel ) {
	return FLXQ_URL . FLXQ_SAMPLE_IMG . $rel;
}

/** Ara boyutlar: örnek görsellerde yalnız küçük resim ve orta boy üretilir (paylaşımlı hostingte süre). */
function flxq_sample_image_sizes( $sizes ) {
	return array_intersect_key( (array) $sizes, array( 'thumbnail' => 1, 'medium' => 1 ) );
}

/**
 * Eklentiyle gelen örnek görseli (ağ yok) medya kütüphanesine ekler; daha önce eklendiyse aynı eki döndürür.
 * SVG medya kütüphanesine yüklenemediği için yanındaki aynı adlı .jpg kopyası eklenir.
 *
 * @param string $rel   flxq_bundled_image() sonucu.
 * @param string $title Ek başlığı ve alternatif metni (ürün adı), boşsa dosya adı.
 * @return int|WP_Error Ek kimliği.
 */
function flxq_import_bundled_image( $rel, $menu_id, $title = '' ) {
	$src   = 'bundled:' . $rel;
	$found = flxq_attachment_by_source( $src );
	if ( $found ) {
		return $found;
	}
	$file = FLXQ_DIR . FLXQ_SAMPLE_IMG . $rel;
	if ( preg_match( '/\.svg$/i', $file ) ) {
		$file = preg_replace( '/\.svg$/i', '.jpg', $file );
	}
	if ( ! is_file( $file ) ) {
		return new WP_Error( 'flxq_bundled', __( 'Örnek görsel dosyası bulunamadı.', 'flx-qr-menu' ) );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = wp_tempnam( basename( $file ) );
	if ( ! $tmp || ! copy( $file, $tmp ) ) {
		wp_delete_file( $tmp );
		return new WP_Error( 'flxq_bundled', __( 'Örnek görsel geçici klasöre kopyalanamadı.', 'flx-qr-menu' ) );
	}
	$post_data = array();
	if ( '' !== $title ) {
		$post_data['post_title'] = $title;
	}
	add_filter( 'intermediate_image_sizes_advanced', 'flxq_sample_image_sizes', 99 );
	$att = media_handle_sideload( array( 'name' => basename( $file ), 'tmp_name' => $tmp ), 0, null, $post_data );
	remove_filter( 'intermediate_image_sizes_advanced', 'flxq_sample_image_sizes', 99 );
	if ( is_wp_error( $att ) ) {
		wp_delete_file( $tmp );
		return $att;
	}
	update_post_meta( $att, '_flxq_src', $src );
	update_post_meta( $att, '_flxq_menu', (int) $menu_id );
	if ( '' !== $title ) {
		update_post_meta( $att, '_wp_attachment_image_alt', $title );
	}
	return (int) $att;
}

/** Göreli yol → ilk kullanan ürünün adı (ek başlığı için). */
function flxq_image_titles( $data ) {
	$out = array();
	foreach ( isset( $data->kategoriler ) ? (array) $data->kategoriler : array() as $k ) {
		foreach ( isset( $k->urunler ) ? (array) $k->urunler : array() as $u ) {
			if ( isset( $u->gorsel, $u->ad ) && is_string( $u->gorsel ) && ! isset( $out[ $u->gorsel ] ) ) {
				$out[ $u->gorsel ] = sanitize_text_field( (string) $u->ad );
			}
		}
	}
	return $out;
}

/** Menüdeki görsel yollarını eşlemeye göre değiştirir. */
function flxq_replace_images( $data, $map ) {
	foreach ( isset( $data->kategoriler ) ? (array) $data->kategoriler : array() as $k ) {
		foreach ( isset( $k->urunler ) ? (array) $k->urunler : array() as $u ) {
			if ( isset( $u->gorsel ) && is_string( $u->gorsel ) && isset( $map[ $u->gorsel ] ) ) {
				$u->gorsel = $map[ $u->gorsel ];
			}
		}
	}
	return $data;
}

/**
 * Örnek menüden yeni menü açılırken: eklentiyle gelen görselleri medya kütüphanesine ekler ve menüde tam adresle değiştirir.
 * Dosya yükleme yetkisi yoksa ya da bir görsel eklenemezse o görsel göreli kalır (düzenleyicideki uyarı + düğme durur).
 * Süre sınırına karşı ~35 sn'den sonra kalanlar bırakılır.
 *
 * @return object Menü verisi.
 */
function flxq_localize_bundled_images( $data, $menu_id = 0 ) {
	if ( ! current_user_can( 'upload_files' ) ) {
		return $data;
	}
	$start  = microtime( true );
	$limit  = (float) apply_filters( 'flxq_bundled_import_seconds', 35 );
	$titles = flxq_image_titles( $data );
	$map    = array();
	if ( function_exists( 'wp_raise_memory_limit' ) ) {
		wp_raise_memory_limit( 'image' );
	}
	foreach ( flxq_relative_images( $data ) as $g ) {
		$rel = flxq_bundled_image( $g );
		if ( '' === $rel ) {
			continue;
		}
		if ( microtime( true ) - $start > $limit ) {
			break;
		}
		$att = flxq_import_bundled_image( $rel, $menu_id, isset( $titles[ $g ] ) ? $titles[ $g ] : '' );
		$url = is_wp_error( $att ) ? '' : wp_get_attachment_url( $att );
		if ( $url ) {
			$map[ $g ] = $url;
		}
	}
	return $map ? flxq_replace_images( $data, $map ) : $data;
}

function flxq_attachment_by_source( $src ) {
	$ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => '_flxq_src', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $src, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Tek görseli indirip medya kütüphanesine ekler.
 *
 * @return int|WP_Error Ek kimliği.
 */
function flxq_sideload_image( $src, $menu_id ) {
	$found = flxq_attachment_by_source( $src );
	if ( $found ) {
		return $found;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = wp_tempnam( 'flxq-img' );
	$res = wp_remote_get(
		$src,
		array(
			'timeout'             => 15,
			'redirection'         => 3,
			'stream'              => true,
			'filename'            => $tmp,
			'limit_response_size' => FLXQ_IMPORT_MAX,
			// Yerel/özel IP adreslerine istek yok (geliştirme ortamında WP_DEBUG ile açılır).
			'reject_unsafe_urls'  => (bool) apply_filters( 'flxq_reject_unsafe_media_urls', ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ),
			'user-agent'          => 'FluxWP-QR-Menu/' . FLXQ_VERSION . '; ' . home_url( '/' ),
		)
	);
	if ( is_wp_error( $res ) ) {
		wp_delete_file( $tmp );
		return $res;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$type = strtolower( (string) wp_remote_retrieve_header( $res, 'content-type' ) );
	if ( 200 !== $code ) {
		wp_delete_file( $tmp );
		/* translators: %d: HTTP status code */
		return new WP_Error( 'flxq_http', sprintf( __( 'Sunucu %d yanıtı verdi.', 'flx-qr-menu' ), $code ) );
	}
	if ( ! preg_match( '#^image/(jpeg|png|gif|webp|avif)#', $type ) || filesize( $tmp ) >= FLXQ_IMPORT_MAX ) {
		wp_delete_file( $tmp );
		return new WP_Error( 'flxq_type', __( 'Dosya bir görsel değil ya da 8 MB\'tan büyük.', 'flx-qr-menu' ) );
	}
	$name = sanitize_file_name( basename( (string) wp_parse_url( $src, PHP_URL_PATH ) ) );
	if ( ! preg_match( '/\.(jpe?g|png|gif|webp|avif)$/i', $name ) ) {
		$name .= '.' . explode( '/', strtok( $type, ';' ) )[1];
	}
	$att = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), 0 );
	if ( is_wp_error( $att ) ) {
		wp_delete_file( $tmp );
		return $att;
	}
	update_post_meta( $att, '_flxq_src', $src );
	update_post_meta( $att, '_flxq_menu', (int) $menu_id );
	return (int) $att;
}

/**
 * Bir parça görseli aktarır ve menüyü kaydeder.
 *
 * @param string[] $skip Daha önce başarısız olan yollar (tekrar denenmez).
 * @return array|WP_Error {done, failed[{path,error}], remaining, saved}
 */
function flxq_import_images( $id, $base_version, $skip = array() ) {
	$base    = flxq_preview_base( $id );
	$data    = flxq_menu_data( $id );
	$todo    = array_values( array_diff( flxq_relative_images( $data ), (array) $skip ) );
	$bundled = array_filter( array_map( 'flxq_bundled_image', $todo ) );
	if ( '' === $base && ! $bundled ) {
		return new WP_Error( 'flxq_no_base', __( 'Görsellerin indirileceği menü sitesi bilinmiyor. Menü sitesi bu menüye bağlanıp en az bir kez veri aldıktan sonra tekrar deneyin.', 'flx-qr-menu' ), array( 'status' => 400 ) );
	}
	$batch  = array_slice( $todo, 0, FLXQ_IMPORT_BATCH );
	$titles = flxq_image_titles( $data );
	$map    = array();
	$fail   = array();
	foreach ( $batch as $g ) {
		// Eklentiyle gelen örnek görsel önce (ağ yok), yoksa bağlı menü sitesinden indir.
		$rel = flxq_bundled_image( $g );
		if ( '' !== $rel ) {
			$att = flxq_import_bundled_image( $rel, $id, isset( $titles[ $g ] ) ? $titles[ $g ] : '' );
		} elseif ( '' === $base ) {
			$att = new WP_Error( 'flxq_no_base', __( 'Menü sitesi bağlı değil.', 'flx-qr-menu' ) );
		} else {
			$att = flxq_sideload_image( flxq_image_source_url( $base, $g ), $id );
		}
		if ( is_wp_error( $att ) ) {
			$fail[] = array( 'path' => $g, 'error' => $att->get_error_message() );
			continue;
		}
		$url = wp_get_attachment_url( $att );
		if ( $url ) {
			$map[ $g ] = $url;
		}
	}
	$saved = null;
	if ( $map ) {
		$data  = flxq_replace_images( $data, $map );
		$saved = flxq_save_menu(
			$id,
			null,
			$data,
			array(
				'action'       => 'images',
				'base_version' => $base_version,
				/* translators: %d: number of images */
				'summary'      => sprintf( _n( '%d görsel medya kütüphanesine aktarıldı', '%d görsel medya kütüphanesine aktarıldı', count( $map ), 'flx-qr-menu' ), count( $map ) ),
			)
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
	}
	return array(
		'done'      => count( $map ),
		'failed'    => $fail,
		'remaining' => max( 0, count( $todo ) - count( $batch ) ),
		'saved'     => $saved,
	);
}
