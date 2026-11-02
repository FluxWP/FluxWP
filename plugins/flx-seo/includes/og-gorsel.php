<?php
/**
 * Paylaşım görseli (og:image): WhatsApp, Facebook, LinkedIn için 1200×630 JPEG.
 * WhatsApp ~300 KB üstü ve WebP görsellerde bulanık küçük önizleme gösterir; bu yüzden
 * her görselin bir kez kırpılıp sıkıştırılmış JPEG kopyası uploads/flxseo-og/ altında tutulur.
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FLXSEO_OG_W        = 1200;
const FLXSEO_OG_H        = 630;
const FLXSEO_OG_MAX_BYTE = 290000;

/**
 * Ek dosyasının paylaşım kopyası (gerekirse üretir).
 *
 * @return array|null url, width, height, type
 */
function flxseo_og_copy( $attachment_id ) {
	$attachment_id = (int) $attachment_id;
	$src           = $attachment_id ? wp_get_original_image_path( $attachment_id ) : '';
	if ( ! $src || ! file_exists( $src ) ) {
		$src = $attachment_id ? get_attached_file( $attachment_id ) : '';
	}
	if ( ! $src || ! file_exists( $src ) || ! wp_attachment_is_image( $attachment_id ) ) {
		return null;
	}
	$up   = wp_get_upload_dir();
	$dir  = trailingslashit( $up['basedir'] ) . 'flxseo-og';
	$name = $attachment_id . '-' . FLXSEO_OG_W . 'x' . FLXSEO_OG_H . '.jpg';
	$file = $dir . '/' . $name;
	$url  = trailingslashit( $up['baseurl'] ) . 'flxseo-og/' . $name;

	if ( ! file_exists( $file ) || filemtime( $file ) < filemtime( $src ) ) {
		// Aynı istek içinde iki kez, ya da başarısız dosyayı her sayfada yeniden denemeyelim.
		if ( get_transient( 'flxseo_og_fail_' . $attachment_id ) ) {
			return null;
		}
		wp_mkdir_p( $dir );
		$ok = false;
		foreach ( array( 82, 72, 62 ) as $q ) {
			$ed = wp_get_image_editor( $src );
			if ( is_wp_error( $ed ) ) {
				break;
			}
			$size = $ed->get_size();
			// Küçük görsel büyütülmez: oranı koruyup en fazla 1200×630'a kırpılır.
			$w = min( FLXSEO_OG_W, (int) $size['width'] );
			$h = min( FLXSEO_OG_H, (int) round( $w * FLXSEO_OG_H / FLXSEO_OG_W ), (int) $size['height'] );
			$w = (int) round( $h * FLXSEO_OG_W / FLXSEO_OG_H );
			$ed->resize( $w, $h, true );
			$ed->set_quality( $q );
			$r = $ed->save( $file, 'image/jpeg' );
			if ( is_wp_error( $r ) ) {
				break;
			}
			clearstatcache( true, $file );
			$ok = true;
			if ( filesize( $file ) <= FLXSEO_OG_MAX_BYTE ) {
				break;
			}
		}
		if ( ! $ok ) {
			set_transient( 'flxseo_og_fail_' . $attachment_id, 1, HOUR_IN_SECONDS );
			return null;
		}
	}
	$dim = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	return array(
		'url'    => $url . '?v=' . filemtime( $file ),
		'width'  => $dim ? $dim[0] : FLXSEO_OG_W,
		'height' => $dim ? $dim[1] : FLXSEO_OG_H,
		'type'   => 'image/jpeg',
	);
}

/**
 * flxseo_image() sonucunu paylaşım için hazırlar: ek dosyasıysa JPEG kopyası, değilse olduğu gibi.
 */
function flxseo_og_image( $img ) {
	$id = (int) ( $img['id'] ?? 0 );
	if ( ! $id && ! empty( $img['url'] ) ) {
		$id = (int) attachment_url_to_postid( preg_replace( '/-\d+x\d+(?=\.[a-z]+$)/i', '', strtok( $img['url'], '?' ) ) );
	}
	$copy = $id ? flxseo_og_copy( $id ) : null;
	if ( $copy ) {
		return array_merge( $img, $copy );
	}
	$ext         = strtolower( pathinfo( (string) wp_parse_url( $img['url'], PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	$types       = array( 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif' );
	$img['type'] = $types[ $ext ] ?? '';
	return $img;
}

/** Görsel değişince/silinince kopyası da gider. */
add_action(
	'delete_attachment',
	function ( $id ) {
		$f = trailingslashit( wp_get_upload_dir()['basedir'] ) . 'flxseo-og/' . (int) $id . '-' . FLXSEO_OG_W . 'x' . FLXSEO_OG_H . '.jpg';
		if ( file_exists( $f ) ) {
			wp_delete_file( $f );
		}
	}
);
