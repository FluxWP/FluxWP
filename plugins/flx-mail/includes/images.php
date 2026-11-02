<?php
/**
 * E-postadaki görseller her istemcide görünsün.
 *
 * Gönderim anında HTML'deki <img> etiketleri:
 * - göreli adresler mutlak yapılır, lazy load kalıntıları (data-src, srcset) temizlenir;
 * - WebP / AVIF / HEIC gibi e-posta istemcilerinin bir kısmının açamadığı biçimler JPG'ye (saydamsa PNG'ye) çevrilir;
 * - sitenin kendi görselleri (ayar açıksa) e-postaya gömülür (CID): uzaktan görsel engeli, hotlink ve önbellek sorunları olmaz.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** E-posta istemcilerinin güvenle gösterdiği biçimler. */
function flxi_email_safe_ext( $ext ) {
	return in_array( strtolower( (string) $ext ), array( 'jpg', 'jpeg', 'png', 'gif' ), true );
}

/** Site adresini mutlak URL'ye çevirir. */
function flxi_absolute_url( $src ) {
	$src = html_entity_decode( trim( (string) $src ), ENT_QUOTES );
	if ( '' === $src || 0 === strpos( $src, 'data:' ) || 0 === strpos( $src, 'cid:' ) ) {
		return $src;
	}
	if ( 0 === strpos( $src, '//' ) ) {
		return ( is_ssl() || 0 === strpos( home_url(), 'https' ) ? 'https:' : 'http:' ) . $src;
	}
	if ( ! preg_match( '~^[a-z][a-z0-9+.-]*:~i', $src ) ) {
		return home_url( '/' . ltrim( $src, '/' ) );
	}
	return $src;
}

/** Sitenin kendi görselinin diskteki yolu (değilse ''). */
function flxi_local_image_path( $url ) {
	$url  = strtok( (string) $url, '?#' );
	$up   = wp_get_upload_dir();
	$maps = array(
		set_url_scheme( $up['baseurl'], 'https' ) => $up['basedir'],
		set_url_scheme( $up['baseurl'], 'http' )  => $up['basedir'],
		set_url_scheme( content_url(), 'https' )  => WP_CONTENT_DIR,
		set_url_scheme( content_url(), 'http' )   => WP_CONTENT_DIR,
		set_url_scheme( site_url(), 'https' )     => untrailingslashit( ABSPATH ),
		set_url_scheme( site_url(), 'http' )      => untrailingslashit( ABSPATH ),
	);
	foreach ( $maps as $base => $dir ) {
		if ( 0 === strpos( $url, trailingslashit( $base ) ) ) {
			$rel  = rawurldecode( substr( $url, strlen( trailingslashit( $base ) ) ) );
			$path = wp_normalize_path( trailingslashit( $dir ) . $rel );
			if ( false === strpos( $rel, '..' ) && is_file( $path ) && is_readable( $path ) ) {
				return $path;
			}
		}
	}
	return '';
}

/** WebP dosyasında saydamlık var mı (VP8X alfa bayrağı ya da VP8L). */
function flxi_webp_has_alpha( $path ) {
	$h = @file_get_contents( $path, false, null, 0, 32 ); // phpcs:ignore
	if ( ! $h || 'WEBP' !== substr( $h, 8, 4 ) ) {
		return false;
	}
	$chunk = substr( $h, 12, 4 );
	if ( 'VP8X' === $chunk ) {
		return (bool) ( ord( $h[20] ) & 0x10 );
	}
	return 'VP8L' === $chunk;
}

/**
 * E-postaya uygun kopya (JPG/PNG, en çok 1200 px) üretir; önbellekte varsa onu verir.
 *
 * @return array path, url, mime | null
 */
function flxi_email_image_copy( $path ) {
	$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	if ( flxi_email_safe_ext( $ext ) ) {
		return null;
	}
	$png  = 'webp' === $ext ? flxi_webp_has_alpha( $path ) : in_array( $ext, array( 'avif', 'svg' ), true );
	$up   = wp_get_upload_dir();
	$dir  = trailingslashit( $up['basedir'] ) . 'flxi-email';
	$name = md5( $path . '|' . filemtime( $path ) . '|' . filesize( $path ) ) . ( $png ? '.png' : '.jpg' );
	$dest = $dir . '/' . $name;
	$out  = array(
		'path' => $dest,
		'url'  => trailingslashit( $up['baseurl'] ) . 'flxi-email/' . $name,
		'mime' => $png ? 'image/png' : 'image/jpeg',
	);
	if ( is_file( $dest ) ) {
		return $out;
	}
	wp_mkdir_p( $dir );
	if ( ! is_file( $dir . '/index.php' ) ) {
		@file_put_contents( $dir . '/index.php', "<?php\n" ); // phpcs:ignore
	}
	$ed = wp_get_image_editor( $path );
	if ( ! is_wp_error( $ed ) ) {
		$size = $ed->get_size();
		if ( ! empty( $size['width'] ) && $size['width'] > 1200 ) {
			$ed->resize( 1200, null );
		}
		if ( ! $png ) {
			$ed->set_quality( 85 );
		}
		$saved = $ed->save( $dest, $out['mime'] );
		if ( ! is_wp_error( $saved ) && is_file( $dest ) ) {
			return $out;
		}
	}
	// Yedek: GD ile doğrudan (saydam olmayanlar beyaz zemine).
	if ( 'webp' === $ext && function_exists( 'imagecreatefromwebp' ) ) {
		$im = @imagecreatefromwebp( $path ); // phpcs:ignore
		if ( $im ) {
			if ( $png ) {
				imagesavealpha( $im, true );
				$ok = imagepng( $im, $dest, 6 );
			} else {
				$bg = imagecreatetruecolor( imagesx( $im ), imagesy( $im ) );
				imagefill( $bg, 0, 0, imagecolorallocate( $bg, 255, 255, 255 ) );
				imagecopy( $bg, $im, 0, 0, 0, 0, imagesx( $im ), imagesy( $im ) );
				$ok = imagejpeg( $bg, $dest, 85 );
				imagedestroy( $bg );
			}
			imagedestroy( $im );
			if ( $ok ) {
				return $out;
			}
		}
	}
	return null;
}

/** <img> etiketinden bir özniteliği okur. */
function flxi_img_attr( $tag, $name ) {
	return preg_match( '/\s' . preg_quote( $name, '/' ) . '\s*=\s*(["\'])(.*?)\1/is', $tag, $m ) ? $m[2] : null;
}

/** <img> etiketinde özniteliği ayarlar (yoksa ekler). */
function flxi_img_set_attr( $tag, $name, $value ) {
	$value = esc_attr( $value );
	$re    = '/(\s' . preg_quote( $name, '/' ) . '\s*=\s*)(["\'])(.*?)\2/is';
	if ( preg_match( $re, $tag ) ) {
		return preg_replace_callback(
			$re,
			function ( $m ) use ( $value ) {
				return $m[1] . '"' . $value . '"';
			},
			$tag,
			1
		);
	}
	return preg_replace( '/^<img\b/i', '<img ' . $name . '="' . $value . '"', $tag, 1 );
}

/**
 * HTML'deki görselleri e-postaya uygun hale getirir.
 *
 * @param string   $html   gövde
 * @param callable $embed  (path, mime) => cid; null ise gömülmez
 * @return string
 */
function flxi_prepare_email_images( $html, $embed = null ) {
	if ( false === stripos( (string) $html, '<img' ) ) {
		return $html;
	}
	$cache = array();
	return preg_replace_callback(
		'/<img\b[^>]*>/i',
		function ( $m ) use ( $embed, &$cache ) {
			$tag = $m[0];
			$src = (string) flxi_img_attr( $tag, 'src' );
			// Lazy load kalıntısı: asıl adres data-* özniteliğinde.
			foreach ( array( 'data-src', 'data-lazy-src', 'data-litespeed-src', 'data-original' ) as $alt ) {
				$v = flxi_img_attr( $tag, $alt );
				if ( $v && ( '' === $src || 0 === strpos( $src, 'data:' ) ) ) {
					$src = $v;
				}
				$tag = preg_replace( '/\s' . preg_quote( $alt, '/' ) . '\s*=\s*(["\']).*?\1/is', '', $tag );
			}
			$tag = preg_replace( '/\s(srcset|sizes|loading|decoding|fetchpriority|data-srcset|data-lazy-srcset)\s*=\s*(["\']).*?\2/is', '', $tag );
			if ( '' === $src || 0 === strpos( $src, 'data:' ) || 0 === strpos( $src, 'cid:' ) ) {
				return $tag;
			}
			$url  = flxi_absolute_url( $src );
			$path = flxi_local_image_path( $url );
			if ( $path ) {
				if ( ! isset( $cache[ $path ] ) ) {
					$copy = flxi_email_image_copy( $path );
					$file = $copy ? $copy['path'] : $path;
					$mime = $copy ? $copy['mime'] : ( wp_check_filetype( $path )['type'] ?: 'image/jpeg' );
					$new  = $copy ? $copy['url'] : $url;
					if ( $embed && flxi_email_safe_ext( pathinfo( $file, PATHINFO_EXTENSION ) ) && filesize( $file ) <= (int) apply_filters( 'flxi_embed_max_bytes', 1536 * 1024 ) ) {
						$cid = call_user_func( $embed, $file, $mime );
						if ( $cid ) {
							$new = 'cid:' . $cid;
						}
					}
					$cache[ $path ] = $new;
				}
				$url = $cache[ $path ];
			}
			if ( null === flxi_img_attr( $tag, 'style' ) ) {
				$tag = flxi_img_set_attr( $tag, 'style', 'max-width:100%;height:auto;' );
			}
			return flxi_img_set_attr( $tag, 'src', $url );
		},
		$html
	);
}

/* Gönderim anında (PHPMailer hazırken) uygula. */
add_action(
	'phpmailer_init',
	function ( $mailer ) {
		if ( 'text/html' !== $mailer->ContentType || ! flxi_setting( 'email_images_fix' ) ) {
			return;
		}
		$embed = null;
		if ( flxi_setting( 'embed_images' ) ) {
			$embed = function ( $file, $mime ) use ( $mailer ) {
				$cid = 'flxi-' . substr( md5( $file ), 0, 12 ) . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
				try {
					return $mailer->addEmbeddedImage( $file, $cid, basename( $file ), 'base64', $mime ) ? $cid : '';
				} catch ( Exception $e ) {
					return '';
				}
			};
		}
		$mailer->Body = flxi_prepare_email_images( $mailer->Body, $embed );
	},
	1000
);
