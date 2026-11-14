<?php
/**
 * Görsel çalışma döngüsü (0.21.0): topla → düzenle → üret/çevir → gör → düzelt.
 *
 * - flxai_image_import: görselleri (medya, site/dış adres, marka kiti) sohbetin çalışma alanı klasörüne alır.
 * - flxai_image_edit: klasördeki görseli GD ile düzenler (ölçekle, doldur, kırp, kenar ekle, döndür, filtre, kırpıntı).
 * - flxai_image_view: görseli modelin kendisine gösterir (kontrol, karşılaştırma).
 * - Araç sonucu '_view' => array( array( path|att|url, label ) ) taşırsa görsel sonraki model çağrısına eklenir
 *   (sohbet: flxai_agent_history; MCP: image içerik bloğu). Kayıtlı mesajda yalnız referans durur.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** Görsel türü → uzantı. */
function flxai_img_exts() {
	return array(
		'image/png'     => 'png',
		'image/jpeg'    => 'jpg',
		'image/webp'    => 'webp',
		'image/gif'     => 'gif',
		'image/svg+xml' => 'svg',
	);
}

/** Baytların görsel türü ('' görsel değil). */
function flxai_img_mime( $bytes, $hint = '' ) {
	$sz = @getimagesizefromstring( (string) $bytes ); // phpcs:ignore
	if ( $sz && ! empty( $sz['mime'] ) && isset( flxai_img_exts()[ $sz['mime'] ] ) ) {
		return (string) $sz['mime'];
	}
	$head = ltrim( substr( (string) $bytes, 0, 600 ) );
	if ( preg_match( '/^(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*(<!doctype svg[^>]*>\s*)?<svg[\s>]/is', $head ) || ( 'image/svg+xml' === $hint && false !== stripos( $head, '<svg' ) ) ) {
		return 'image/svg+xml';
	}
	return '';
}

/** Sohbetin çalışma alanı kuralları (sohbet yoksa ya da çalışma alanı kapalıysa null). */
function flxai_img_policy( array $ctx ) {
	if ( empty( $ctx['chat_id'] ) || ! function_exists( 'flxai_ws_policy' ) || ! function_exists( 'flxai_chat_get' ) ) {
		return null;
	}
	$chat = flxai_chat_get( (int) $ctx['chat_id'], (int) ( $ctx['user_id'] ?? 0 ) );
	return $chat ? flxai_ws_policy( $chat ) : null;
}

/** Yol → site köküne göre temiz yol; kök dışı göreli yol kendi klasörüne bağlanır. */
function flxai_img_rel( $path, array $p ) {
	$rel = flxai_ws_clean( (string) $path );
	if ( '' === $rel ) {
		return '';
	}
	if ( ! flxai_ws_inside( $rel, flxai_ws_root() ) && ! is_file( flxai_ws_abs( $rel ) ) ) {
		$rel = flxai_ws_clean( $p['dir'] . '/' . $rel );
	}
	return $rel;
}

/**
 * Görsel kaynağını okur: path (çalışma alanı), attachment_id, url (site, https, data:).
 *
 * @param mixed $src   array( path|attachment_id|url ) ya da tek değer.
 * @param array $ctx   Araç bağlamı.
 * @return array|WP_Error array( bytes, mime, label, ref ) — ref: _view için kalıcı referans.
 */
function flxai_img_source( $src, array $ctx ) {
	if ( ! is_array( $src ) ) {
		$src = is_int( $src ) || ctype_digit( (string) $src ) ? array( 'attachment_id' => (int) $src ) : ( preg_match( '#^(https?:|data:)#i', (string) $src ) ? array( 'url' => (string) $src ) : array( 'path' => (string) $src ) );
	}
	$max = 15728640;
	if ( ! empty( $src['path'] ) ) {
		$p = flxai_img_policy( $ctx );
		if ( ! $p ) {
			return new WP_Error( 'flxai_img_ws', __( 'There is no workspace here; pass attachment_id or url.', 'flx-ai' ) );
		}
		$rel = flxai_img_rel( (string) $src['path'], $p );
		if ( '' === $rel || 'deny' === flxai_ws_kind( $rel, $p, false ) ) {
			return new WP_Error( 'flxai_img_deny', __( 'This file is outside your workspace.', 'flx-ai' ) );
		}
		$abs = flxai_ws_abs( $rel );
		if ( ! is_file( $abs ) || ! is_readable( $abs ) ) {
			/* translators: %s: file path */
			return new WP_Error( 'flxai_img_missing', sprintf( __( 'File not found: %s', 'flx-ai' ), $rel ) );
		}
		if ( filesize( $abs ) > $max ) {
			return new WP_Error( 'flxai_img_big', __( 'The image is too large (at most 15 MB).', 'flx-ai' ) );
		}
		$b    = (string) file_get_contents( $abs ); // phpcs:ignore
		$mime = flxai_img_mime( $b, 'svg' === strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) ) ? 'image/svg+xml' : '' );
		if ( '' === $mime ) {
			/* translators: %s: file path */
			return new WP_Error( 'flxai_img_type', sprintf( __( 'Not an image (PNG, JPEG, WebP, GIF or SVG): %s', 'flx-ai' ), $rel ) );
		}
		return array( $b, $mime, basename( $rel ), array( 'path' => $rel ) );
	}
	$ref = ! empty( $src['attachment_id'] ) ? (int) $src['attachment_id'] : trim( (string) ( $src['url'] ?? '' ) );
	if ( ! $ref ) {
		return new WP_Error( 'flxai_img_src', __( 'Give path, attachment_id or url for each image.', 'flx-ai' ) );
	}
	if ( is_int( $ref ) && ! wp_attachment_is_image( $ref ) && 'image/svg+xml' !== get_post_mime_type( $ref ) ) {
		/* translators: %d: attachment id */
		return new WP_Error( 'flxai_img_att', sprintf( __( 'Attachment #%d is not an image.', 'flx-ai' ), $ref ) );
	}
	$b = flxai_media_ref_bytes( $ref, $max );
	if ( is_wp_error( $b ) ) {
		return $b;
	}
	$mime = flxai_img_mime( $b[0], (string) $b[1] );
	if ( '' === $mime ) {
		return new WP_Error( 'flxai_img_type', __( 'The file is not an image (PNG, JPEG, WebP, GIF or SVG).', 'flx-ai' ) );
	}
	if ( is_int( $ref ) ) {
		$file  = get_attached_file( $ref );
		$label = $file ? basename( $file ) : '#' . $ref;
		return array( $b[0], $mime, $label, array( 'att' => $ref ) );
	}
	$label = 0 === strpos( $ref, 'data:' ) ? 'image' : basename( (string) wp_parse_url( $ref, PHP_URL_PATH ) );
	return array( $b[0], $mime, '' !== $label ? $label : 'image', 0 === strpos( $ref, 'data:' ) ? null : array( 'url' => $ref ) );
}

/** Dosya adını temizler; uzantı türe göre konur. */
function flxai_img_name( $name, $mime, $fallback = 'gorsel' ) {
	$ext  = flxai_img_exts()[ $mime ] ?? 'png';
	$base = strtolower( remove_accents( preg_replace( '/\.[a-z0-9]{2,4}$/i', '', basename( (string) $name ) ) ) );
	$base = trim( preg_replace( '/[^a-z0-9_-]+/', '-', $base ), '-' );
	return substr( '' !== $base ? $base : $fallback, 0, 60 ) . '.' . $ext;
}

/**
 * Görseli kendi klasörüne yazar.
 *
 * @param array  $p      Çalışma alanı kuralları.
 * @param string $target Dosya yolu ya da klasör (sonu / ile) ya da ad.
 * @param string $bytes  İçerik.
 * @param string $mime   Tür.
 * @param bool   $unique Aynı ad varsa -2, -3 … ekle.
 * @return string|WP_Error Göreli yol.
 */
function flxai_img_put( array $p, $target, $bytes, $mime, $unique = true ) {
	$target = trim( str_replace( '\\', '/', (string) $target ) );
	$folder = '' === $target || '/' === substr( $target, -1 );
	$dir    = $folder ? rtrim( $target, '/' ) : dirname( $target );
	$name   = flxai_img_name( $folder ? '' : basename( $target ), $mime );
	$dir    = '.' === $dir || '' === $dir ? 'gorseller' : $dir;
	$rel    = flxai_img_rel( $dir . '/' . $name, $p );
	if ( '' === $rel || 'own' !== flxai_ws_kind( $rel, $p ) ) {
		/* translators: %s: folder path */
		return new WP_Error( 'flxai_img_own', sprintf( __( 'Images can only be saved in your own folder (%s).', 'flx-ai' ), $p['dir'] ) );
	}
	if ( $unique ) {
		$i    = 2;
		$stem = preg_replace( '/\.[a-z0-9]+$/', '', $rel );
		$ext  = pathinfo( $rel, PATHINFO_EXTENSION );
		while ( file_exists( flxai_ws_abs( $rel ) ) && $i < 500 ) {
			$rel = $stem . '-' . $i++ . '.' . $ext;
		}
	}
	$w = flxai_ws_write( $rel, $bytes );
	return is_wp_error( $w ) ? $w : $rel;
}

/** _view öğesini temizler. */
function flxai_img_view_ref( $ref, $label = '' ) {
	if ( ! is_array( $ref ) ) {
		return null;
	}
	$out = array();
	if ( ! empty( $ref['path'] ) ) {
		$out['path'] = (string) $ref['path'];
	} elseif ( ! empty( $ref['att'] ) ) {
		$out['att'] = (int) $ref['att'];
	} elseif ( ! empty( $ref['url'] ) && preg_match( '#^https?://#i', (string) $ref['url'] ) ) {
		$out['url'] = (string) $ref['url'];
	} else {
		return null;
	}
	$out['label'] = mb_substr( (string) ( '' !== $label ? $label : ( $ref['label'] ?? '' ) ), 0, 120 );
	return $out;
}

/** Araç sonucundaki _view listesini temizler (en çok 4). */
function flxai_img_view_list( $v ) {
	$out = array();
	foreach ( is_array( $v ) ? $v : array() as $r ) {
		$r = flxai_img_view_ref( $r );
		if ( $r ) {
			$out[] = $r;
		}
	}
	return array_slice( $out, 0, 4 );
}

/**
 * _view öğesini modele gidecek görsele çevirir: uzun kenar en çok $max px JPEG (saydamlık beyaz zemine).
 *
 * @return array|null array( mime, base64, width, height )
 */
function flxai_img_view_data( array $v, $max = 1280 ) {
	$bytes = null;
	if ( ! empty( $v['path'] ) ) {
		$rel = flxai_ws_clean( (string) $v['path'] );
		$abs = '' !== $rel && flxai_ws_inside( $rel, flxai_ws_root() ) ? flxai_ws_abs( $rel ) : '';
		if ( $abs && is_file( $abs ) && filesize( $abs ) <= 15728640 ) {
			$bytes = (string) file_get_contents( $abs ); // phpcs:ignore
		}
	} elseif ( ! empty( $v['att'] ) || ! empty( $v['url'] ) ) {
		$b     = flxai_media_ref_bytes( ! empty( $v['att'] ) ? (int) $v['att'] : (string) $v['url'], 15728640 );
		$bytes = is_wp_error( $b ) ? null : $b[0];
	}
	if ( null === $bytes || '' === $bytes ) {
		return null;
	}
	$mime = flxai_img_mime( $bytes );
	if ( 'image/svg+xml' === $mime || '' === $mime ) {
		return null; // SVG modele gitmez (çoğu sağlayıcı kabul etmez).
	}
	$sz = @getimagesizefromstring( $bytes ); // phpcs:ignore
	$w  = (int) ( $sz[0] ?? 0 );
	$h  = (int) ( $sz[1] ?? 0 );
	if ( function_exists( 'imagecreatefromstring' ) && $w && $h ) {
		$im = @imagecreatefromstring( $bytes ); // phpcs:ignore
		if ( $im ) {
			$k  = min( 1, $max / max( $w, $h ) );
			$nw = max( 1, (int) round( $w * $k ) );
			$nh = max( 1, (int) round( $h * $k ) );
			$c  = imagecreatetruecolor( $nw, $nh );
			imagefill( $c, 0, 0, imagecolorallocate( $c, 255, 255, 255 ) );
			imagecopyresampled( $c, $im, 0, 0, 0, 0, $nw, $nh, $w, $h );
			ob_start();
			imagejpeg( $c, null, 82 );
			$jpg = (string) ob_get_clean();
			imagedestroy( $im );
			imagedestroy( $c );
			if ( '' !== $jpg ) {
				return array( 'image/jpeg', base64_encode( $jpg ), $w, $h ); // phpcs:ignore
			}
		}
	}
	if ( strlen( $bytes ) > 4194304 ) {
		return null;
	}
	return array( $mime, base64_encode( $bytes ), $w, $h ); // phpcs:ignore
}

/**
 * Geçmişteki _view'lı araç mesajlarının görsellerini modele ekler: son $keep mesajın görselleri bir kullanıcı
 * mesajı olarak araç sonuçlarının hemen ardına konur (kayıtlı sohbet değişmez).
 *
 * @param array $msgs   Modele gidecek mesajlar.
 * @param bool  $vision Model görsel görebiliyor mu?
 * @param int   $keep   Görseli gönderilecek son araç sonucu sayısı.
 */
function flxai_img_history( array $msgs, $vision = true, $keep = 2 ) {
	$idx = array();
	foreach ( $msgs as $i => $m ) {
		if ( 'tool' === ( $m['role'] ?? '' ) && ! empty( $m['_view'] ) ) {
			$idx[] = $i;
		}
	}
	if ( ! $idx ) {
		return $msgs;
	}
	$show = array_flip( array_slice( $idx, -max( 1, (int) $keep ) ) );
	$out  = array();
	$pend = array();
	$n    = count( $msgs );
	foreach ( array_values( $msgs ) as $i => $m ) {
		$out[] = $m;
		if ( isset( $show[ $i ] ) ) {
			$pend[] = $m;
		}
		$next_tool = $i + 1 < $n && 'tool' === ( $msgs[ $i + 1 ]['role'] ?? '' );
		if ( ! $pend || $next_tool ) {
			continue;
		}
		$parts = array();
		$names = array();
		foreach ( $pend as $pm ) {
			foreach ( flxai_img_view_list( $pm['_view'] ) as $v ) {
				$d = $vision ? flxai_img_view_data( $v ) : null;
				$l = '' !== $v['label'] ? $v['label'] : basename( (string) ( $v['path'] ?? ( $v['url'] ?? '#' . ( $v['att'] ?? '' ) ) ) );
				if ( $d ) {
					$parts[] = array(
						'type'      => 'image_url',
						'image_url' => array( 'url' => 'data:' . $d[0] . ';base64,' . $d[1] ),
					);
					$names[] = $l . ' (' . $d[2] . '×' . $d[3] . ')';
				} elseif ( ! $vision ) {
					$names[] = $l;
				}
			}
		}
		$pend = array();
		if ( ! $names ) {
			continue;
		}
		if ( $parts ) {
			$text = '[Otomatik: az önceki araç çıktısının görselleri — ' . implode( ', ', $names ) . ". Kullanıcıya göstermeden önce kendin dikkatle kontrol et: yazı taşması/kesilmesi, okunurluk ve kontrast, yazım hataları, logo doğru ve net mi, yüz/konu kırpılmış mı, hizalar ve boşluklar, istenen içerik eksiksiz mi. Sorun varsa düzeltip yeniden üret; yoksa devam et. Bu mesajı kullanıcı yazmadı.]\n";
			$out[] = array(
				'role'    => 'user',
				'content' => array_merge( array( array( 'type' => 'text', 'text' => $text ) ), $parts ),
			);
		} else {
			$out[] = array(
				'role'    => 'user',
				'content' => '[Otomatik: bu model görsel göremiyor (' . implode( ', ', $names ) . '). Görseli kendin kontrol edemezsin; ölçü ve taşma bilgisini araç sonucundan oku, görseli kullanıcıya gösterip onayını iste. Bu mesajı kullanıcı yazmadı.]',
			);
		}
	}
	return $out;
}

/** MCP sonucu için görsel içerik blokları. */
function flxai_img_mcp_blocks( $view ) {
	$out = array();
	foreach ( flxai_img_view_list( $view ) as $v ) {
		$d = flxai_img_view_data( $v, 1568 );
		if ( $d ) {
			$out[] = array(
				'type'     => 'image',
				'data'     => $d[1],
				'mimeType' => $d[0],
			);
		}
	}
	return $out;
}

/* ---------- Düzenleme (GD) ---------- */

/** Renk "#rrggbb" ya da "transparent" → GD rengi. */
function flxai_img_color( $im, $c ) {
	$c = strtolower( trim( (string) $c ) );
	if ( 'transparent' === $c || '' === $c ) {
		return imagecolorallocatealpha( $im, 0, 0, 0, 127 );
	}
	if ( preg_match( '/^#?([0-9a-f]{3})$/', $c, $m ) ) {
		$c = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
	}
	if ( ! preg_match( '/^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/', $c, $m ) ) {
		return imagecolorallocate( $im, 255, 255, 255 );
	}
	return imagecolorallocate( $im, hexdec( $m[1] ), hexdec( $m[2] ), hexdec( $m[3] ) );
}

/** Saydam zeminli boş tuval. */
function flxai_img_canvas( $w, $h, $bg = 'transparent' ) {
	$c = imagecreatetruecolor( max( 1, (int) $w ), max( 1, (int) $h ) );
	imagealphablending( $c, false );
	imagesavealpha( $c, true );
	imagefilledrectangle( $c, 0, 0, $w, $h, flxai_img_color( $c, $bg ) );
	imagealphablending( $c, true );
	return $c;
}

/**
 * Tek işlemi uygular.
 *
 * @param GdImage|resource $im Görsel.
 * @param array            $op op + parametreler.
 * @return GdImage|resource Yeni görsel.
 * @throws Exception Geçersiz işlem.
 */
function flxai_img_apply( $im, array $op ) {
	$w    = imagesx( $im );
	$h    = imagesy( $im );
	$name = strtolower( (string) ( $op['op'] ?? '' ) );
	$px   = fn( $k, $def = 0 ) => max( 0, min( 8000, (int) ( $op[ $k ] ?? $def ) ) );
	switch ( $name ) {
		case 'resize':
			$tw = $px( 'width' );
			$th = $px( 'height' );
			if ( ! $tw && ! $th ) {
				throw new Exception( 'resize: width or height is required.' );
			}
			$k  = min( $tw ? $tw / $w : INF, $th ? $th / $h : INF );
			$nw = max( 1, (int) round( $w * $k ) );
			$nh = max( 1, (int) round( $h * $k ) );
			$c  = flxai_img_canvas( $nw, $nh );
			imagealphablending( $c, false );
			imagecopyresampled( $c, $im, 0, 0, 0, 0, $nw, $nh, $w, $h );
			return $c;
		case 'cover':
			$tw = $px( 'width' );
			$th = $px( 'height' );
			if ( ! $tw || ! $th ) {
				throw new Exception( 'cover: width and height are required.' );
			}
			$k  = max( $tw / $w, $th / $h );
			$sw = $tw / $k;
			$sh = $th / $k;
			$fx = max( 0, min( 100, (float) ( $op['x'] ?? 50 ) ) ) / 100;
			$fy = max( 0, min( 100, (float) ( $op['y'] ?? 50 ) ) ) / 100;
			$sx = max( 0, min( $w - $sw, $w * $fx - $sw / 2 ) );
			$sy = max( 0, min( $h - $sh, $h * $fy - $sh / 2 ) );
			$c  = flxai_img_canvas( $tw, $th );
			imagealphablending( $c, false );
			imagecopyresampled( $c, $im, 0, 0, (int) round( $sx ), (int) round( $sy ), $tw, $th, (int) round( $sw ), (int) round( $sh ) );
			return $c;
		case 'crop':
			$x  = min( $w - 1, $px( 'x' ) );
			$y  = min( $h - 1, $px( 'y' ) );
			$cw = min( $w - $x, $px( 'width', $w ) );
			$ch = min( $h - $y, $px( 'height', $h ) );
			if ( $cw < 1 || $ch < 1 ) {
				throw new Exception( 'crop: the area is empty.' );
			}
			$c = flxai_img_canvas( $cw, $ch );
			imagealphablending( $c, false );
			imagecopy( $c, $im, 0, 0, $x, $y, $cw, $ch );
			return $c;
		case 'pad':
			$tw = max( $px( 'width', $w ), 1 );
			$th = max( $px( 'height', $h ), 1 );
			$k  = min( 1, $tw / $w, $th / $h );
			$nw = max( 1, (int) round( $w * $k ) );
			$nh = max( 1, (int) round( $h * $k ) );
			$c  = flxai_img_canvas( $tw, $th, (string) ( $op['color'] ?? 'transparent' ) );
			imagecopyresampled( $c, $im, (int) ( ( $tw - $nw ) / 2 ), (int) ( ( $th - $nh ) / 2 ), 0, 0, $nw, $nh, $w, $h );
			return $c;
		case 'rotate':
			$deg = (float) ( $op['degrees'] ?? 90 );
			$r   = imagerotate( $im, -$deg, imagecolorallocatealpha( $im, 0, 0, 0, 127 ) );
			if ( ! $r ) {
				throw new Exception( 'rotate failed.' );
			}
			imagesavealpha( $r, true );
			return $r;
		case 'flip':
			imageflip( $im, 'v' === ( $op['direction'] ?? 'h' ) ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL );
			return $im;
		case 'grayscale':
			imagefilter( $im, IMG_FILTER_GRAYSCALE );
			return $im;
		case 'brightness':
			imagefilter( $im, IMG_FILTER_BRIGHTNESS, (int) round( max( -100, min( 100, (float) ( $op['value'] ?? 10 ) ) ) * 2.55 ) );
			return $im;
		case 'contrast':
			imagefilter( $im, IMG_FILTER_CONTRAST, - (int) max( -100, min( 100, (float) ( $op['value'] ?? 10 ) ) ) );
			return $im;
		case 'blur':
			for ( $i = 0, $n = max( 1, min( 30, (int) ( $op['value'] ?? 4 ) ) ); $i < $n; $i++ ) {
				imagefilter( $im, IMG_FILTER_GAUSSIAN_BLUR );
			}
			return $im;
		case 'darken':
			// Yazı okunurluğu için üstüne yarı saydam renk (varsayılan siyah %35).
			$a = max( 0, min( 100, (float) ( $op['value'] ?? 35 ) ) );
			$c = flxai_img_color( $im, (string) ( $op['color'] ?? '#000000' ) );
			$rgb = imagecolorsforindex( $im, $c );
			imagealphablending( $im, true );
			imagefilledrectangle( $im, 0, 0, $w, $h, imagecolorallocatealpha( $im, $rgb['red'], $rgb['green'], $rgb['blue'], (int) round( 127 - $a * 1.27 ) ) );
			return $im;
		case 'trim':
			$r = function_exists( 'imagecropauto' ) ? imagecropauto( $im, IMG_CROP_THRESHOLD, max( 0, min( 1, (float) ( $op['value'] ?? 0.1 ) ) ), imagecolorat( $im, 0, 0 ) ) : false;
			if ( $r ) {
				imagesavealpha( $r, true );
				return $r;
			}
			return $im;
	}
	/* translators: %s: operation name */
	throw new Exception( sprintf( 'Unknown operation: %s. Use resize, cover, crop, pad, rotate, flip, grayscale, brightness, contrast, blur, darken, trim.', $name ) );
}

/** GD görselini kodlar. */
function flxai_img_encode( $im, $format, $quality = 88 ) {
	ob_start();
	if ( 'jpeg' === $format ) {
		$c = imagecreatetruecolor( imagesx( $im ), imagesy( $im ) );
		imagefill( $c, 0, 0, imagecolorallocate( $c, 255, 255, 255 ) );
		imagecopy( $c, $im, 0, 0, 0, 0, imagesx( $im ), imagesy( $im ) );
		imagejpeg( $c, null, $quality );
		imagedestroy( $c );
	} elseif ( 'webp' === $format && function_exists( 'imagewebp' ) ) {
		imagesavealpha( $im, true );
		imagewebp( $im, null, $quality );
	} else {
		imagesavealpha( $im, true );
		imagepng( $im, null, 6 );
	}
	return (string) ob_get_clean();
}

/* ---------- Araçlar ---------- */

/** Görsel kaynağı şeması (path | attachment_id | url). */
function flxai_img_src_schema( $extra = array() ) {
	return array(
		'type'       => 'object',
		'properties' => array_merge(
			array(
				'path'          => array( 'type' => 'string', 'description' => 'Çalışma alanındaki dosya (ör. gorseller/foto.jpg)' ),
				'attachment_id' => array( 'type' => 'integer', 'description' => 'Medya kütüphanesi kimliği' ),
				'url'           => array( 'type' => 'string', 'description' => 'Site ya da https adresi' ),
			),
			$extra
		),
	);
}

add_filter(
	'flxai_tools',
	function ( $tools ) {
		$tools['flxai_image_view'] = array(
			'title'       => __( 'View image', 'flx-ai' ),
			'description' => 'Görseli SEN görürsün (sonraki adımda görsel olarak gelir): çalışma alanı dosyası, medya ek kimliği ya da site/https adresi. Ürettiğin ya da çevirdiğin her görseli kullanıcıya vermeden önce kontrol etmek, kaynak fotoğrafı tanımak (konu nerede, kırpma odağı) ve en çok 4 seçeneği karşılaştırmak için. flxai_design_render ve flxai_image_generate görselini zaten otomatik gösterir.',
			'readonly'    => true,
			'cap'         => 'upload_files',
			'source'      => 'flx-ai',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'images' => array(
						'type'        => 'array',
						'description' => 'En çok 4 görsel',
						'items'       => flxai_img_src_schema(),
					),
				),
				'required'   => array( 'images' ),
			),
			'run'         => function ( $a, $ctx = array() ) {
				$out  = array();
				$view = array();
				foreach ( array_slice( (array) ( $a['images'] ?? array() ), 0, 4 ) as $s ) {
					$r = flxai_img_source( $s, $ctx );
					if ( is_wp_error( $r ) ) {
						$out[] = array( 'error' => $r->get_error_message() );
						continue;
					}
					$sz    = @getimagesizefromstring( $r[0] ); // phpcs:ignore
					$out[] = array(
						'name'   => $r[2],
						'mime'   => $r[1],
						'width'  => (int) ( $sz[0] ?? 0 ),
						'height' => (int) ( $sz[1] ?? 0 ),
						'bytes'  => strlen( $r[0] ),
						'note'   => 'image/svg+xml' === $r[1] ? 'SVG gösterilemez; PNG\'ye çevirip bak (flxai_design_render).' : '',
					);
					if ( $r[3] ) {
						$view[] = $r[3] + array( 'label' => $r[2] );
					}
				}
				if ( ! $out ) {
					throw new Exception( __( 'Give at least one image.', 'flx-ai' ) ); // phpcs:ignore
				}
				return array(
					'images' => $out,
					'_view'  => $view,
				);
			},
		);
		$tools['flxai_image_import'] = array(
			'title'       => __( 'Collect images', 'flx-ai' ),
			'description' => 'Görselleri sohbetin çalışma alanı klasörüne alır (varsayılan gorseller/): medya kütüphanesi (attachment_id), site ya da dış https adresi (ör. web aramasında bulunan, başka bir MCP aracının ürettiği), marka kiti logosu. Bir tasarıma başlarken kullanacağın tüm görselleri (fotoğraf, logo, iş ortağı logosu) önce buraya topla; sonra flxai_image_edit ile hazırla, flxai_design_render images içinde path ile ver. Dönen path\'leri kullan.',
			'readonly'    => true,
			'cap'         => 'upload_files',
			'source'      => 'flx-ai',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'images' => array(
						'type'  => 'array',
						'items' => flxai_img_src_schema( array( 'name' => array( 'type' => 'string', 'description' => 'Dosya adı, ör. logo.svg, foto.jpg (uzantı türe göre düzeltilir)' ) ) ),
					),
					'folder' => array( 'type' => 'string', 'description' => 'Klasör (kendi klasörünün içinde), varsayılan gorseller' ),
					'view'   => array( 'type' => 'boolean', 'description' => 'Alınan görselleri de gör (varsayılan true)' ),
				),
				'required'   => array( 'images' ),
			),
			'run'         => function ( $a, $ctx = array() ) {
				$p = flxai_img_policy( $ctx );
				if ( ! $p ) {
					throw new Exception( __( 'There is no workspace here; pass attachment_id or url.', 'flx-ai' ) ); // phpcs:ignore
				}
				$folder = trim( (string) ( $a['folder'] ?? 'gorseller' ), '/' );
				$out    = array();
				$view   = array();
				foreach ( array_slice( (array) ( $a['images'] ?? array() ), 0, 10 ) as $s ) {
					$r = flxai_img_source( $s, $ctx );
					if ( is_wp_error( $r ) ) {
						$out[] = array( 'error' => $r->get_error_message() );
						continue;
					}
					$want = is_array( $s ) && ! empty( $s['name'] ) ? (string) $s['name'] : $r[2];
					$rel  = flxai_img_put( $p, ( '' !== $folder ? $folder : 'gorseller' ) . '/' . $want, $r[0], $r[1], true );
					if ( is_wp_error( $rel ) ) {
						$out[] = array( 'error' => $rel->get_error_message() );
						continue;
					}
					$sz    = @getimagesizefromstring( $r[0] ); // phpcs:ignore
					$out[] = array(
						'path'   => $rel,
						'name'   => basename( $rel ),
						'mime'   => $r[1],
						'width'  => (int) ( $sz[0] ?? 0 ),
						'height' => (int) ( $sz[1] ?? 0 ),
					);
					$view[] = array(
						'path'  => $rel,
						'label' => basename( $rel ),
					);
				}
				return array(
					'images' => $out,
					'_view'  => false === ( $a['view'] ?? true ) ? array() : $view,
				);
			},
		);
		if ( function_exists( 'imagecreatefromstring' ) ) {
			$tools['flxai_image_edit'] = array(
				'title'       => __( 'Edit image', 'flx-ai' ),
				'description' => 'Çalışma alanındaki (ya da medya/adres) görseli düzenler ve klasörüne kaydeder; sonucu otomatik görürsün. ops sırayla uygulanır: resize {width,height} (oranı korur), cover {width,height,x,y} (alanı doldurur; x/y 0-100 odak, ör. yüz sağdaysa x:70), crop {x,y,width,height}, pad {width,height,color} (kenar ekleyip ortalar; color #hex ya da transparent), rotate {degrees}, flip {direction:h|v}, grayscale, brightness {value -100..100}, contrast {value}, blur {value 1-30}, darken {value 0-100,color} (yazı okunurluğu için koyu örtü), trim {value} (logo kenarındaki boşluğu kırpar). Arka plan silme, nesne ekleme gibi yaratıcı düzenleme için flxai_image_generate (refs ile) kullan. SVG düzenlenmez.',
				'readonly'    => true,
				'cap'         => 'upload_files',
				'source'      => 'flx-ai',
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'image'   => flxai_img_src_schema(),
						'ops'     => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'op' => array(
										'type' => 'string',
										'enum' => array( 'resize', 'cover', 'crop', 'pad', 'rotate', 'flip', 'grayscale', 'brightness', 'contrast', 'blur', 'darken', 'trim' ),
									),
								),
								'required'   => array( 'op' ),
							),
						),
						'save_as' => array( 'type' => 'string', 'description' => 'Kayıt yolu, ör. gorseller/foto-kare.jpg (varsayılan: kaynak adı + -duzen)' ),
						'format'  => array(
							'type' => 'string',
							'enum' => array( 'png', 'jpeg', 'webp' ),
						),
						'quality' => array( 'type' => 'integer', 'description' => '40-100, varsayılan 88' ),
					),
					'required'   => array( 'image', 'ops' ),
				),
				'run'         => function ( $a, $ctx = array() ) {
					$p = flxai_img_policy( $ctx );
					if ( ! $p ) {
						throw new Exception( __( 'There is no workspace here; pass attachment_id or url.', 'flx-ai' ) ); // phpcs:ignore
					}
					$r = flxai_img_source( $a['image'] ?? null, $ctx );
					if ( is_wp_error( $r ) ) {
						throw new Exception( $r->get_error_message() ); // phpcs:ignore
					}
					if ( 'image/svg+xml' === $r[1] ) {
						throw new Exception( __( 'SVG files cannot be edited; use them as they are.', 'flx-ai' ) ); // phpcs:ignore
					}
					$im = @imagecreatefromstring( $r[0] ); // phpcs:ignore
					if ( ! $im ) {
						throw new Exception( __( 'The image could not be opened.', 'flx-ai' ) ); // phpcs:ignore
					}
					if ( ! imageistruecolor( $im ) ) {
						imagepalettetotruecolor( $im );
					}
					imagealphablending( $im, true );
					imagesavealpha( $im, true );
					$done = array();
					foreach ( array_slice( (array) ( $a['ops'] ?? array() ), 0, 12 ) as $op ) {
						$im     = flxai_img_apply( $im, is_array( $op ) ? $op : array( 'op' => (string) $op ) );
						$done[] = is_array( $op ) ? (string) ( $op['op'] ?? '' ) : (string) $op;
					}
					$fmt  = in_array( $a['format'] ?? '', array( 'png', 'jpeg', 'webp' ), true ) ? $a['format'] : ( 'image/jpeg' === $r[1] ? 'jpeg' : ( 'image/webp' === $r[1] ? 'webp' : 'png' ) );
					$mime = array(
						'png'  => 'image/png',
						'jpeg' => 'image/jpeg',
						'webp' => 'image/webp',
					)[ $fmt ];
					$data = flxai_img_encode( $im, $fmt, max( 40, min( 100, (int) ( $a['quality'] ?? 88 ) ) ) );
					$w    = imagesx( $im );
					$h    = imagesy( $im );
					imagedestroy( $im );
					$save   = trim( (string) ( $a['save_as'] ?? '' ) );
					$stem   = preg_replace( '/\.[a-z0-9]+$/i', '', $r[2] );
					$target = '' !== $save ? $save : ( ! empty( $r[3]['path'] ) ? dirname( $r[3]['path'] ) : 'gorseller' ) . '/' . $stem . '-duzen';
					$rel    = flxai_img_put( $p, $target, $data, $mime, '' === $save );
					if ( is_wp_error( $rel ) ) {
						throw new Exception( $rel->get_error_message() ); // phpcs:ignore
					}
					return array(
						'path'   => $rel,
						'width'  => $w,
						'height' => $h,
						'bytes'  => strlen( $data ),
						'ops'    => $done,
						'_view'  => array(
							array(
								'path'  => $rel,
								'label' => basename( $rel ),
							),
						),
					);
				},
			);
		}
		return $tools;
	}
);
