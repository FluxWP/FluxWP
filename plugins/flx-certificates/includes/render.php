<?php
defined( 'ABSPATH' ) || exit;

/**
 * Sunucuda çizim: PDF (TCPDF + FPDI), sertifika görseli ve 1200x630 paylaşım görseli (GD).
 * Yerleşim assets/js/renderer.js ile aynı kurallarla hesaplanır (font, hizalama, sığdırma, satır kaydırma, QR).
 * Flux Forms / Flux Donate gibi arka planda oluşan sertifikaların dosyaları kimse sayfayı açmadan hazır olur.
 * Tarayıcıdaki çizici canlı önizleme için ve sunucu çizemezse yedek olarak kalır.
 */

add_action( 'flxs_certificate_created', 'flxs_render_schedule' );
add_action( 'flxs_render_files', 'flxs_render_files_job' );

/** Sunucuda çizim yapılabilir mi: GD (FreeType'lı) ve zlib gerekir. */
function flxs_render_available() {
	return function_exists( 'imagettftext' ) && function_exists( 'imagecreatefromjpeg' ) && function_exists( 'gzcompress' );
}

/** Yeni sertifika: dosyaları bir dakika sonra arka planda üret (panelde oluşturan tarayıcı bu arada yüklemiş olur). */
function flxs_render_schedule( $cert, $delay = 60 ) {
	if ( ! flxs_render_available() || empty( $cert['id'] ) ) {
		return;
	}
	$args = array( (int) $cert['id'] );
	if ( ! flx_background_worker_active() && function_exists( 'as_schedule_single_action' ) && did_action( 'action_scheduler_init' ) ) {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! as_has_scheduled_action( 'flxs_render_files', $args, 'flx-certificates' ) ) {
			as_schedule_single_action( time() + max( 1, (int) $delay ), 'flxs_render_files', $args, 'flx-certificates' );
		}
	} else {
		flx_background_schedule( 'flxs_render_files', $args, $delay );
	}
}

function flxs_render_files_job( $id ) {
	$cert = flxs_get_certificate( (int) $id );
	if ( $cert ) {
		$result = flxs_render_certificate_files( $cert );
		$key = 'flxs_render_retry_' . (int) $id;
		if ( is_wp_error( $result ) && 'flxs_render_busy' !== $result->get_error_code() ) {
			$attempts = (int) get_transient( $key ) + 1;
			set_transient( $key, $attempts, DAY_IN_SECONDS );
			if ( $attempts < 3 ) {
				flxs_render_schedule( $cert, 60 * $attempts );
			}
		} elseif ( ! is_wp_error( $result ) ) {
			delete_transient( $key );
		}
	}
}

/**
 * Eksik dosyaları (pdf, image, og) sunucuda üretip kaydeder.
 *
 * @return array|WP_Error üretilen türler
 */
function flxs_render_certificate_files( $cert, $types = null ) {
	if ( ! flxs_render_available() ) {
		return new WP_Error( 'flxs_render', __( 'The server has no GD (FreeType) extension.', 'flx-certificates' ) );
	}
	$types = null === $types ? flxs_certificate_missing_files( $cert ) : (array) $types;
	if ( ! $types ) {
		return array();
	}
	if ( flx_background_should_defer() ) {
		flxs_render_schedule( $cert, 1 );
		return new WP_Error( 'flxs_render_busy', __( 'This certificate\'s files are being prepared right now.', 'flx-certificates' ) );
	}
	$lock = 'certificate-files-' . (int) $cert['id'];
	$token = flx_background_lock( $lock, 180 );
	if ( ! $token ) {
		flxs_render_schedule( $cert, 30 );
		return new WP_Error( 'flxs_render_busy', __( 'This certificate\'s files are being prepared right now.', 'flx-certificates' ) );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore
	}
	try {
		$fresh = flxs_get_certificate( (int) $cert['id'] );
		if ( ! $fresh ) {
			return array();
		}
		// Another request may have stored files between the initial read and this lease.
		$cert = $fresh;
		$types = array_intersect( $types, flxs_certificate_missing_files( $cert ) );
		if ( ! $types ) {
			return array();
		}
		$out = flxs_render_certificate_files_now( $cert, $types );
	} catch ( \Throwable $e ) {
		$out = new WP_Error( 'flxs_render', $e->getMessage() );
	} finally {
		flx_background_unlock( $lock, $token );
	}
	if ( is_wp_error( $out ) ) {
		flxs_log( 'files_render_failed', 'certificate', $cert['id'], $cert['number'], array( 'error' => $out->get_error_message() ) );
	}
	return $out;
}

function flxs_render_certificate_files_now( $cert, $types ) {
	$template = flxs_get_template( $cert['template_id'] );
	if ( ! $template ) {
		return new WP_Error( 'flxs_template', __( 'Template not found.', 'flx-certificates' ) );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$config = $template['config'];
	$vars   = flxs_certificate_vars( $cert, null, $template );
	$meta   = array(
		'number' => $cert['number'],
		'link'   => flxs_certificate_url( $cert ),
	);
	$r      = new FLXS_Renderer( $config, $vars, $meta );
	$done   = array();
	$tmp    = array();

	if ( in_array( 'pdf', $types, true ) ) {
		$file  = wp_tempnam( 'flxs.pdf' );
		$tmp[] = $file;
		file_put_contents( $file, $r->pdf() ); // phpcs:ignore
		$res = flxs_store_certificate_file( $cert, 'pdf', $file );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$done[] = 'pdf';
	}
	$need_img = array_intersect( array( 'image', 'og' ), $types );
	if ( $need_img ) {
		$page = $r->raster( ( $r->w >= $r->h ) ? 1600 : 1100 );
		if ( ! $page ) {
			// PDF arka planın görüntüsü yok (yüklenmiş PDF, Imagick yok): görselleri tarayıcı tamamlar
			flxs_render_cleanup( $tmp );
			return $done;
		}
		if ( in_array( 'image', $types, true ) ) {
			$file  = wp_tempnam( 'flxs.jpg' );
			$tmp[] = $file;
			imagejpeg( $page, $file, 82 );
			$res = flxs_store_certificate_file( flxs_get_certificate( $cert['id'] ), 'image', $file, imagesx( $page ), imagesy( $page ) );
			if ( is_wp_error( $res ) ) {
				flxs_render_cleanup( $tmp );
				return $res;
			}
			$done[] = 'image';
		}
		if ( in_array( 'og', $types, true ) ) {
			$og    = flxs_render_share_image( $page, $cert['title'], $vars['ad_soyad'] ?? '', (string) flxs_setting( 'org_name' ) );
			$file  = wp_tempnam( 'flxs-og.jpg' );
			$tmp[] = $file;
			imagejpeg( $og, $file, 86 );
			imagedestroy( $og );
			$res = flxs_store_certificate_file( flxs_get_certificate( $cert['id'] ), 'og', $file );
			if ( is_wp_error( $res ) ) {
				flxs_render_cleanup( $tmp );
				return $res;
			}
			$done[] = 'og';
		}
		imagedestroy( $page );
	}
	flxs_render_cleanup( $tmp );
	if ( $done ) {
		flxs_log( 'files_rendered', 'certificate', $cert['id'], $cert['number'], array( 'files' => $done ) );
	}
	return $done;
}

function flxs_render_cleanup( $files ) {
	foreach ( $files as $f ) {
		wp_delete_file( $f );
	}
}

/**
 * Sertifika sayfası / PDF linki açıldığında eksik dosyaları hemen üretir (WhatsApp/Facebook önizlemesi
 * linki ilk açtığında paylaşım görseli hazır olsun). Üst üste hata veren sertifikada 3 denemeden sonra durur.
 */
function flxs_render_on_view( $cert ) {
	if ( 'active' !== $cert['status'] || ! flxs_render_available() ) {
		return $cert;
	}
	$need = flxs_certificate_missing_files( $cert );
	$key  = 'flxs_rf_' . (int) $cert['id'];
	if ( ! $need || (int) get_transient( $key ) >= 3 ) {
		return $cert;
	}
	$res = flxs_render_certificate_files( $cert, $need );
	if ( is_wp_error( $res ) ) {
		if ( 'flxs_render_busy' !== $res->get_error_code() ) {
			set_transient( $key, (int) get_transient( $key ) + 1, DAY_IN_SECONDS );
		}
		return $cert;
	}
	if ( ! $res ) {
		set_transient( $key, (int) get_transient( $key ) + 1, DAY_IN_SECONDS ); // ör. yüklenmiş PDF arka planın görüntüsü yok
		return $cert;
	}
	return flxs_get_certificate( $cert['id'] );
}

/** PDF'i ya da görseli eksik etkin sertifikalar. */
function flxs_certificates_missing_files( $limit = 0 ) {
	global $wpdb;
	$where = "status = 'active' AND ( pdf_file IS NULL OR pdf_file = '' OR image_file IS NULL OR image_file = '' )";
	if ( ! $limit ) {
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxs_table( 'certificates' ) . " WHERE $where" );
	}
	return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . flxs_table( 'certificates' ) . " WHERE $where ORDER BY id DESC LIMIT %d", $limit ) ) );
}

/** Eksik dosyaları toplu üretir (en çok $limit sertifika). Döner: [üretilen, hata] */
function flxs_render_missing( $limit = 25 ) {
	$ok   = 0;
	$fail = 0;
	$queued = 0;
	foreach ( flxs_certificates_missing_files( $limit ) as $id ) {
		$cert = flxs_get_certificate( $id );
		if ( $cert && flx_background_should_defer() ) {
			flxs_render_schedule( $cert, 1 );
			++$queued;
			continue;
		}
		$res  = $cert ? flxs_render_certificate_files( $cert ) : null;
		if ( is_wp_error( $res ) || ! $cert ) {
			++$fail;
		} else {
			++$ok;
		}
	}
	return array( $ok, $fail, $queued );
}

/** Font id => TTF yolu (renderer.js gibi bilinmeyen font Nunito'ya düşer). */
function flxs_font_file( $id ) {
	$builtin = flxs_builtin_fonts();
	if ( isset( $builtin[ $id ] ) ) {
		return FLXS_DIR . 'assets/fonts/' . $builtin[ $id ][1];
	}
	if ( preg_match( '/^custom-(\d+)$/', (string) $id, $m ) ) {
		$p = get_attached_file( (int) $m[1] );
		if ( $p && file_exists( $p ) ) {
			return $p;
		}
	}
	return FLXS_DIR . 'assets/fonts/' . $builtin['nunito'][1];
}

/** TTF'in hhea ascender / unitsPerEm oranı (pdf-lib heightAtSize ile aynı). */
function flxs_font_ascent( $file ) {
	static $cache = array();
	if ( isset( $cache[ $file ] ) ) {
		return $cache[ $file ];
	}
	$asc = 0.9;
	$fh  = @fopen( $file, 'rb' ); // phpcs:ignore
	if ( $fh ) {
		$head = fread( $fh, 12 );
		$n    = unpack( 'n', substr( $head, 4, 2 ) )[1];
		$dir  = fread( $fh, 16 * $n );
		$tab  = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$e                  = substr( $dir, $i * 16, 16 );
			$tab[ substr( $e, 0, 4 ) ] = unpack( 'N', substr( $e, 8, 4 ) )[1];
		}
		if ( isset( $tab['head'], $tab['hhea'] ) ) {
			fseek( $fh, $tab['head'] + 18 );
			$upm = unpack( 'n', fread( $fh, 2 ) )[1];
			fseek( $fh, $tab['hhea'] + 4 );
			$a = unpack( 'n', fread( $fh, 2 ) )[1];
			$a = $a >= 0x8000 ? $a - 0x10000 : $a;
			if ( $upm ) {
				$asc = $a / $upm;
			}
		}
		fclose( $fh ); // phpcs:ignore
	}
	return $cache[ $file ] = $asc;
}

/** Arka plan PDF'inin görüntüsü: eklentiyle gelenler için assets/backgrounds/raster/, değilse Imagick. */
function flxs_background_raster_file( $bg ) {
	if ( 'bundled' === ( $bg['source'] ?? '' ) && ! empty( $bg['file'] ) ) {
		return flxs_bundled_path( preg_replace( '/\.pdf$/i', '.jpg', basename( $bg['file'] ) ), 'raster/' );
	}
	return '';
}

function flxs_background_file( $bg ) {
	if ( 'bundled' === ( $bg['source'] ?? '' ) && ! empty( $bg['file'] ) ) {
		return flxs_bundled_path( $bg['file'] );
	}
	if ( 'attachment' === ( $bg['source'] ?? '' ) && ! empty( $bg['id'] ) ) {
		$f = get_attached_file( (int) $bg['id'] );
		return $f && file_exists( $f ) ? $f : '';
	}
	return '';
}

/** GD ile görsel aç (JPG/PNG/WebP/GIF). */
function flxs_gd_open( $file ) {
	$data = @file_get_contents( $file ); // phpcs:ignore
	$im   = $data ? @imagecreatefromstring( $data ) : false;
	return $im ? $im : null;
}

function flxs_hex_rgb( $hex ) {
	if ( preg_match( '/^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', (string) $hex, $m ) ) {
		return array( hexdec( $m[1] ), hexdec( $m[2] ), hexdec( $m[3] ) );
	}
	return array( 20, 51, 74 );
}

/** Türkçe büyük harf (toLocaleUpperCase('tr-TR')). */
function flxs_upper_tr( $s ) {
	return mb_strtoupper( strtr( $s, array( 'i' => 'İ', 'ı' => 'I' ) ), 'UTF-8' );
}

class FLXS_Renderer {
	public $w;
	public $h;
	private $config;
	private $vars;
	private $meta;
	private $pdf;
	private $meter;
	private $fonts = array();
	private $items = null;

	public function __construct( $config, $vars, $meta ) {
		$this->config = $config;
		$this->vars   = $vars;
		$this->meta   = $meta;
		$this->w      = (float) ( $config['page']['w'] ?? 0 ) ? (float) $config['page']['w'] : 841.89;
		$this->h      = (float) ( $config['page']['h'] ?? 0 ) ? (float) $config['page']['h'] : 595.28;
	}

	private function tcpdf() {
		if ( $this->pdf ) {
			return $this->pdf;
		}
		flxs_load_tcpdf();
		$pdf = new FLXS_Fpdi( $this->w >= $this->h ? 'L' : 'P', 'pt', array( $this->w, $this->h ), true, 'UTF-8', false );
		$pdf->setPrintHeader( false );
		$pdf->setPrintFooter( false );
		$pdf->SetMargins( 0, 0, 0 );
		$pdf->SetAutoPageBreak( false, 0 );
		$pdf->setCellPaddings( 0, 0, 0, 0 );
		$pdf->setFontSubsetting( true );
		$pdf->SetCreator( 'Flux Certificates' );
		$pdf->SetAuthor( get_bloginfo( 'name' ) );
		$pdf->SetTitle( flxs_fill( '{sertifika} · {ad_soyad}', $this->vars ) );
		return $this->pdf = $pdf;
	}

	/** TCPDF font adı (TTF ilk kullanımda uploads/flx-certificates/fonts/ altına dönüştürülür). */
	private function font( $id ) {
		if ( isset( $this->fonts[ $id ] ) ) {
			return $this->fonts[ $id ];
		}
		$ttf = flxs_font_file( $id );
		$dir = flxs_upload_dir()['dir'] . '/fonts/';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			// TCPDF font tanımları PHP dosyası: dışarıdan erişilmesin
			file_put_contents( $dir . '.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
			file_put_contents( $dir . 'index.php', "<?php // Silence\n" ); // phpcs:ignore
		}
		$name = TCPDF_FONTS::addTTFfont( $ttf, 'TrueTypeUnicode', '', 32, $dir );
		if ( ! $name ) {
			throw new Exception( 'Font: ' . basename( $ttf ) );
		}
		return $this->fonts[ $id ] = array(
			'name' => $name,
			'php'  => $dir . $name . '.php',
			'ttf'  => $ttf,
			'asc'  => flxs_font_ascent( $ttf ),
		);
	}

	/** Ölçüm için ayrı TCPDF (çıktı alınan belge Output'tan sonra kullanılamaz). */
	private function meter() {
		if ( ! $this->meter ) {
			flxs_load_tcpdf();
			$this->meter = new TCPDF( 'P', 'pt', 'A4', true, 'UTF-8', false );
			$this->meter->setPrintHeader( false );
			$this->meter->setPrintFooter( false );
		}
		return $this->meter;
	}

	private function width( $font, $text, $size, $letter ) {
		$pdf = $this->meter();
		$pdf->SetFont( $font['name'], '', $size, $font['php'] );
		$pdf->setFontSpacing( 0 );
		return $pdf->GetStringWidth( $text ) + $letter * max( 0, mb_strlen( $text ) - 1 );
	}

	private function wrap( $font, $text, $size, $max, $letter ) {
		$out = array();
		foreach ( explode( "\n", (string) $text ) as $para ) {
			$words = array_values( array_filter( preg_split( '/\s+/u', $para ), 'strlen' ) );
			if ( ! $words ) {
				$out[] = '';
				continue;
			}
			$line = $words[0];
			for ( $i = 1; $i < count( $words ); $i++ ) {
				$test = $line . ' ' . $words[ $i ];
				if ( $this->width( $font, $test, $size, $letter ) <= $max ) {
					$line = $test;
				} else {
					$out[] = $line;
					$line  = $words[ $i ];
				}
			}
			$out[] = $line;
		}
		return $out;
	}

	/** Çizilecek öğeler: metin satırları (x, taban çizgisi) ve QR — pt, sayfanın sol üstünden. */
	public function items() {
		if ( null !== $this->items ) {
			return $this->items;
		}
		$items = array();
		foreach ( (array) $this->config['fields'] as $f ) {
			if ( isset( $f['draw'] ) && false === $f['draw'] ) {
				continue;
			}
			if ( (int) ( $f['page'] ?? 0 ) > 0 ) {
				continue; // görsel yalnız ilk sayfa; PDF'te ek sayfalar arka planla gelir
			}
			if ( 'qr' === $f['type'] ) {
				if ( '' !== (string) $this->meta['link'] ) {
					$items[] = array( 'qr', $f, $this->meta['link'] );
				}
				continue;
			}
			if ( 'static' === $f['type'] ) {
				$text = flxs_fill( $f['default'] ?? '', $this->vars );
			} elseif ( 'number_auto' === $f['type'] ) {
				$text = (string) $this->meta['number'];
			} else {
				$text = (string) ( $this->vars[ $f['key'] ] ?? '' );
			}
			if ( '' === $text ) {
				continue;
			}
			if ( ! empty( $f['upper'] ) ) {
				$text = flxs_upper_tr( $text );
			}
			$font   = $this->font( $f['font'] ?? '' );
			$size   = (float) ( $f['size'] ?? 0 ) ? (float) $f['size'] : 14;
			$letter = (float) ( $f['letter'] ?? 0 );
			$maxl   = max( 1, (int) ( $f['lines'] ?? 1 ) );
			$fw     = (float) $f['w'];
			$fit    = ! isset( $f['fit'] ) || false !== $f['fit'];
			if ( 1 === $maxl ) {
				$text = preg_replace( '/\s*\n\s*/u', ' ', $text );
				$w    = $this->width( $font, $text, $size, $letter );
				if ( $fit && $w > $fw ) {
					$size = max( 4, $size * $fw / $w );
				}
				$lines = array( $text );
			} else {
				$lines = $this->wrap( $font, $text, $size, $fw, $letter );
				$guard = 0;
				while ( $fit && count( $lines ) > $maxl && $size > 5 && $guard++ < 40 ) {
					$size *= 0.95;
					$lines = $this->wrap( $font, $text, $size, $fw, $letter );
				}
				$lines = array_slice( $lines, 0, $maxl );
			}
			$asc = $font['asc'] * $size;
			$lh  = $size * ( (float) ( $f['lineHeight'] ?? 0 ) ? (float) $f['lineHeight'] : 1.25 );
			foreach ( $lines as $i => $ln ) {
				$lw = $this->width( $font, $ln, $size, $letter );
				$x  = (float) $f['x'];
				if ( 'center' === ( $f['align'] ?? '' ) ) {
					$x += ( $fw - $lw ) / 2;
				} elseif ( 'right' === ( $f['align'] ?? '' ) ) {
					$x += $fw - $lw;
				}
				$items[] = array( 'text', $ln, $x, (float) $f['y'] + $asc + $i * $lh, $size, $font, flxs_hex_rgb( $f['color'] ?? '' ), $letter );
			}
		}
		return $this->items = $items;
	}

	private function qr_matrix( $link ) {
		flxs_load_tcpdf();
		$b = new TCPDF2DBarcode( $link, 'QRCODE,M' );
		$a = $b->getBarcodeArray();
		return $a ? $a : null;
	}

	/** PDF baytları. */
	public function pdf() {
		$pdf = $this->tcpdf();
		$bg  = (array) ( $this->config['background'] ?? array() );
		$off = array( 0, 0 );
		$extra = 0;
		$pdf->AddPage();
		$file = flxs_background_file( $bg );
		$type = $bg['type'] ?? 'none';
		$done = false;
		if ( 'pdf' === $type && $file ) {
			try {
				$count = $pdf->setSourceFile( $file );
				$tpl   = $pdf->importPage( 1 );
				$pdf->useTemplate( $tpl, 0, 0, $this->w, $this->h );
				$off   = $pdf->flxs_media_origin( 1 );
				$extra = $count - 1;
				$done  = true;
			} catch ( \Throwable $e ) {
				$done = false;
			}
			if ( ! $done ) {
				$raster = flxs_background_raster_file( $bg );
				if ( ! $raster ) {
					throw new Exception( __( 'The background PDF could not be read on the server.', 'flx-certificates' ) );
				}
				$pdf->Image( $raster, 0, 0, $this->w, $this->h, '', '', '', true, 300 );
				$done = true;
			}
		} elseif ( 'image' === $type && $file ) {
			$img = $file;
			$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
				$gd  = flxs_gd_open( $file );
				$img = wp_tempnam( 'flxs-bg.png' );
				imagepng( $gd, $img );
				imagedestroy( $gd );
			}
			$pdf->Image( $img, 0, 0, $this->w, $this->h, '', '', '', true, 300 );
			if ( $img !== $file ) {
				wp_delete_file( $img );
			}
			$done = true;
		}
		if ( ! $done ) {
			$c = flxs_hex_rgb( $bg['color'] ?? '#ffffff' );
			$pdf->Rect( 0, 0, $this->w, $this->h, 'F', array(), $c );
		}
		foreach ( $this->items() as $it ) {
			if ( 'qr' === $it[0] ) {
				$f = $it[1];
				$m = $this->qr_matrix( $it[2] );
				if ( ! $m ) {
					continue;
				}
				$n = $m['num_cols'];
				$s = (float) $f['w'] / $n;
				$c = flxs_hex_rgb( $f['color'] ?? '' );
				for ( $r = 0; $r < $m['num_rows']; $r++ ) {
					for ( $col = 0; $col < $n; $col++ ) {
						if ( $m['bcode'][ $r ][ $col ] ) {
							$pdf->Rect( $off[0] + $f['x'] + $col * $s, $off[1] + $f['y'] + $r * $s, $s, $s, 'F', array(), $c );
						}
					}
				}
				continue;
			}
			list( , $text, $x, $base, $size, $font, $rgb, $letter ) = $it;
			$pdf->SetFont( $font['name'], '', $size, $font['php'] );
			$pdf->setFontSpacing( $letter );
			$pdf->SetTextColor( $rgb[0], $rgb[1], $rgb[2] );
			$pdf->Text( $off[0] + $x, $off[1] + $base, $text, false, false, true, 0, 0, '', false, '', 0, false, 'L', 'B' );
		}
		$pdf->setFontSpacing( 0 );
		for ( $p = 2; $p <= $extra + 1; $p++ ) {
			$pdf->AddPage();
			$pdf->useTemplate( $pdf->importPage( $p ), 0, 0, $this->w, $this->h );
		}
		$out       = $pdf->Output( 'sertifika.pdf', 'S' );
		$this->pdf = null; // Output belgeyi kapatır
		return $out;
	}

	/** İlk sayfanın görüntüsü (GD), genişlik px. Arka plan görüntüsü yoksa null. */
	public function raster( $width ) {
		$bg    = (array) ( $this->config['background'] ?? array() );
		$type  = $bg['type'] ?? 'none';
		$scale = $width / $this->w;
		$H     = (int) round( $this->h * $scale );
		$im    = imagecreatetruecolor( $width, $H );
		$off   = array( 0, 0 );
		if ( 'image' === $type || 'pdf' === $type ) {
			$src = 'image' === $type ? flxs_background_file( $bg ) : flxs_background_raster_file( $bg );
			$gd  = $src ? flxs_gd_open( $src ) : null;
			if ( ! $gd && 'pdf' === $type ) {
				$gd = flxs_imagick_raster( flxs_background_file( $bg ), $width );
			}
			if ( ! $gd ) {
				imagedestroy( $im );
				return null;
			}
			$white = imagecolorallocate( $im, 255, 255, 255 );
			imagefill( $im, 0, 0, $white );
			imagecopyresampled( $im, $gd, 0, 0, 0, 0, $width, $H, imagesx( $gd ), imagesy( $gd ) );
			imagedestroy( $gd );
			if ( 'pdf' === $type && flxs_background_file( $bg ) ) {
				$off = $this->media_origin( flxs_background_file( $bg ) );
			}
		} else {
			$c = flxs_hex_rgb( $bg['color'] ?? '#ffffff' );
			imagefill( $im, 0, 0, imagecolorallocate( $im, $c[0], $c[1], $c[2] ) );
		}
		imagealphablending( $im, true );
		foreach ( $this->items() as $it ) {
			if ( 'qr' === $it[0] ) {
				$f = $it[1];
				$m = $this->qr_matrix( $it[2] );
				if ( ! $m ) {
					continue;
				}
				$c   = flxs_hex_rgb( $f['color'] ?? '' );
				$col = imagecolorallocate( $im, $c[0], $c[1], $c[2] );
				$s   = (float) $f['w'] / $m['num_cols'] * $scale;
				$x0  = ( $off[0] + $f['x'] ) * $scale;
				$y0  = ( $off[1] + $f['y'] ) * $scale;
				for ( $r = 0; $r < $m['num_rows']; $r++ ) {
					for ( $cc = 0; $cc < $m['num_cols']; $cc++ ) {
						if ( $m['bcode'][ $r ][ $cc ] ) {
							imagefilledrectangle( $im, (int) round( $x0 + $cc * $s ), (int) round( $y0 + $r * $s ), (int) round( $x0 + ( $cc + 1 ) * $s ) - 1, (int) round( $y0 + ( $r + 1 ) * $s ) - 1, $col );
						}
					}
				}
				continue;
			}
			list( , $text, $x, $base, $size, $font, $rgb, $letter ) = $it;
			$col = imagecolorallocate( $im, $rgb[0], $rgb[1], $rgb[2] );
			$pt  = $size * $scale * 0.75; // GD punto = 96 dpi
			$bx  = ( $off[0] + $x ) * $scale;
			$by  = (int) round( ( $off[1] + $base ) * $scale );
			if ( $letter ) {
				foreach ( preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
					imagettftext( $im, $pt, 0, (int) round( $bx ), $by, $col, $font['ttf'], $ch );
					$bx += ( $this->width( $font, $ch, $size, 0 ) + $letter ) * $scale;
				}
			} else {
				imagettftext( $im, $pt, 0, (int) round( $bx ), $by, $col, $font['ttf'], $text );
			}
		}
		return $im;
	}

	private function media_origin( $file ) {
		try {
			$pdf = $this->tcpdf();
			$pdf->setSourceFile( $file );
			return $pdf->flxs_media_origin( 1 );
		} catch ( \Throwable $e ) {
			return array( 0, 0 );
		}
	}
}

/** Imagick + Ghostscript varsa yüklenmiş PDF arka planı görüntüye çevirir. */
function flxs_imagick_raster( $file, $width ) {
	if ( ! $file || ! class_exists( 'Imagick' ) ) {
		return null;
	}
	try {
		$im = new Imagick();
		$im->setResolution( 150, 150 );
		$im->readImage( $file . '[0]' );
		$im->setImageBackgroundColor( 'white' );
		$im = $im->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
		$im->setImageFormat( 'png' );
		$gd = imagecreatefromstring( $im->getImageBlob() );
		$im->clear();
		return $gd ? $gd : null;
	} catch ( \Throwable $e ) {
		return null;
	}
}

function flxs_load_tcpdf() {
	if ( class_exists( 'TCPDF', false ) ) {
		flxs_load_fpdi();
		return;
	}
	if ( ! defined( 'TCPDF_SILENCE_DEPRECATION' ) ) {
		define( 'TCPDF_SILENCE_DEPRECATION', true );
	}
	if ( ! defined( 'K_TCPDF_EXTERNAL_CONFIG' ) ) {
		define( 'K_TCPDF_EXTERNAL_CONFIG', true );
		define( 'K_PATH_MAIN', FLXS_DIR . 'includes/vendor/tcpdf/' );
		define( 'K_PATH_URL', '' );
		define( 'K_PATH_FONTS', FLXS_DIR . 'includes/vendor/tcpdf/fonts/' );
		define( 'K_PATH_CACHE', trailingslashit( get_temp_dir() ) );
		define( 'K_PATH_URL_CACHE', '' );
		define( 'K_PATH_IMAGES', '' );
		define( 'K_BLANK_IMAGE', '' );
		define( 'K_CELL_HEIGHT_RATIO', 1.25 );
		define( 'K_SMALL_RATIO', 2 / 3 );
		define( 'K_THAI_TOPCHARS', false );
		define( 'K_TCPDF_CALLS_IN_HTML', false );
		define( 'K_TCPDF_THROW_EXCEPTION_ERROR', true );
		define( 'K_TIMEZONE', 'UTC' );
		define( 'PDF_PAGE_FORMAT', 'A4' );
		define( 'PDF_PAGE_ORIENTATION', 'P' );
		define( 'PDF_CREATOR', 'Flux Certificates' );
		define( 'PDF_AUTHOR', '' );
		define( 'PDF_UNIT', 'pt' );
		define( 'PDF_FONT_NAME_MAIN', 'helvetica' );
		define( 'PDF_FONT_SIZE_MAIN', 10 );
		define( 'PDF_FONT_NAME_DATA', 'helvetica' );
		define( 'PDF_FONT_SIZE_DATA', 8 );
		define( 'PDF_FONT_MONOSPACED', 'helvetica' );
		define( 'PDF_IMAGE_SCALE_RATIO', 1 );
		define( 'HEAD_MAGNIFICATION', 1 );
		define( 'PDF_MARGIN_HEADER', 0 );
		define( 'PDF_MARGIN_FOOTER', 0 );
		define( 'PDF_MARGIN_TOP', 0 );
		define( 'PDF_MARGIN_BOTTOM', 0 );
		define( 'PDF_MARGIN_LEFT', 0 );
		define( 'PDF_MARGIN_RIGHT', 0 );
		define( 'PDF_HEADER_LOGO', '' );
		define( 'PDF_HEADER_LOGO_WIDTH', 0 );
		define( 'PDF_HEADER_TITLE', '' );
		define( 'PDF_HEADER_STRING', '' );
	}
	require_once FLXS_DIR . 'includes/vendor/tcpdf/tcpdf.php';
	require_once FLXS_DIR . 'includes/vendor/tcpdf/tcpdf_barcodes_2d.php';
	flxs_load_fpdi();
}

function flxs_load_fpdi() {
	require_once FLXS_DIR . 'includes/vendor/fpdi/src/autoload.php';
	require_once FLXS_DIR . 'includes/render-fpdi.php';
}

/** 1200x630 paylaşım görseli (renderer.js toShareImage ile aynı düzen). $page: sertifika görüntüsü (GD). */
function flxs_render_share_image( $page, $title, $name, $org ) {
	$W  = 1200;
	$H  = 630;
	$im = imagecreatetruecolor( $W, $H );
	// çapraz gradyan #1F2937 → #0a4a35 (canvas createLinearGradient(0,0,W,H) gibi), şeritlerle
	$len = $W * $W + $H * $H;
	$n   = 240;
	for ( $i = 0; $i < $n; $i++ ) {
		$t  = ( $i + 0.5 ) / $n;
		$c0 = $len * $i / $n;
		$c1 = $len * ( $i + 1 ) / $n + 2 * $W;
		$col = imagecolorallocate( $im, (int) round( 14 + ( 10 - 14 ) * $t ), (int) round( 122 + ( 74 - 122 ) * $t ), (int) round( 86 + ( 53 - 86 ) * $t ) );
		imagefilledpolygon( $im, array( (int) ( $c0 / $W ), 0, (int) ( $c1 / $W ) + 1, 0, (int) ( ( $c1 - $H * $H ) / $W ) + 1, $H, (int) ( ( $c0 - $H * $H ) / $W ), $H ), $col );
	}
	imagealphablending( $im, true );
	$deco = imagecolorallocatealpha( $im, 255, 255, 255, 119 ); // ~%6
	imagefilledellipse( $im, $W - 120, -60, 520, 520, $deco );
	imagefilledellipse( $im, 80, $H + 40, 400, 400, $deco );

	// sertifika
	$pw    = imagesx( $page );
	$ph    = imagesy( $page );
	$ratio = $pw / $ph;
	$ch    = 560;
	$cw    = $ch * $ratio;
	if ( $cw > 640 ) {
		$cw = 640;
		$ch = $cw / $ratio;
	}
	$cx = (int) round( $W - $cw - 40 );
	$cy = (int) round( ( $H - $ch ) / 2 );
	$cw = (int) round( $cw );
	$ch = (int) round( $ch );
	for ( $i = 14; $i >= 1; $i-- ) { // yumuşak gölge
		$a = imagecolorallocatealpha( $im, 0, 0, 0, 127 - (int) round( 5 * ( 15 - $i ) / 14 ) );
		imagefilledrectangle( $im, $cx - 8 - $i * 2, $cy - 8 - $i * 2 + 14, $cx + $cw + 8 + $i * 2, $cy + $ch + 8 + $i * 2 + 14, $a );
	}
	imagefilledrectangle( $im, $cx - 8, $cy - 8, $cx + $cw + 7, $cy + $ch + 7, imagecolorallocate( $im, 255, 255, 255 ) );
	imagecopyresampled( $im, $page, $cx, $cy, 0, 0, $cw, $ch, $pw, $ph );

	$bold = flxs_font_file( 'montserrat-bold' );
	$reg  = flxs_font_file( 'montserrat' );
	$semi = flxs_font_file( 'montserrat-semibold' );
	$px   = function ( $p ) {
		return $p * 0.75;
	};
	$tw   = function ( $font, $size, $text ) use ( $px ) {
		$b = imagettfbbox( $px( $size ), 0, $font, $text );
		return $b[2] - $b[0];
	};
	$wrap = function ( $font, $size, $text, $max ) use ( $tw ) {
		$lines = array();
		$cur   = '';
		foreach ( preg_split( '/\s+/u', trim( (string) $text ) ) as $w ) {
			if ( '' === $w ) {
				continue;
			}
			$t = '' !== $cur ? $cur . ' ' . $w : $w;
			if ( '' !== $cur && $tw( $font, $size, $t ) > $max ) {
				$lines[] = $cur;
				$cur     = $w;
			} else {
				$cur = $t;
			}
		}
		if ( '' !== $cur ) {
			$lines[] = $cur;
		}
		return $lines;
	};
	$white = imagecolorallocate( $im, 255, 255, 255 );
	$soft  = imagecolorallocatealpha( $im, 255, 255, 255, 19 ); // .85
	$tx    = 56;
	$tmax  = $cx - $tx - 40;

	// rozet: ✓ Doğrulanmış sertifika
	$badge = __( 'Verified certificate', 'flx-certificates' );
	$bw    = $tw( $bold, 20, $badge ) + 36 + 28;
	$pill = imagecreatetruecolor( $bw + 1, 45 );
	imagealphablending( $pill, false );
	imagesavealpha( $pill, true );
	imagefill( $pill, 0, 0, imagecolorallocatealpha( $pill, 0, 0, 0, 127 ) );
	flxs_gd_round_rect( $pill, 0, 0, $bw, 44, 22, imagecolorallocatealpha( $pill, 255, 255, 255, 107 ) );
	imagecopy( $im, $pill, $tx, 70, 0, 0, $bw + 1, 45 );
	imagedestroy( $pill );
	imagesetthickness( $im, 3 );
	imageline( $im, $tx + 18, 93, $tx + 23, 99, $white );
	imageline( $im, $tx + 23, 99, $tx + 33, 86, $white );
	imagesetthickness( $im, 1 );
	imagettftext( $im, $px( 20 ), 0, $tx + 46, 100, $white, $bold, $badge );

	$ty = 175;
	foreach ( array_slice( $wrap( $semi, 26, $title, $tmax ), 0, 2 ) as $l ) {
		imagettftext( $im, $px( 26 ), 0, $tx, $ty, $soft, $semi, $l );
		$ty += 34;
	}
	$size = 64;
	do {
		$lines = $wrap( $bold, $size, $name, $tmax );
		$over  = count( $lines ) > 3;
		foreach ( $lines as $l ) {
			if ( $tw( $bold, $size, $l ) > $tmax ) {
				$over = true;
			}
		}
		$size -= 4;
	} while ( $over && $size > 26 );
	$size += 4;
	$ty   += $size * 0.55;
	foreach ( array_slice( $lines, 0, 3 ) as $l ) {
		$ty += $size * 1.1;
		imagettftext( $im, $px( $size ), 0, $tx, (int) round( $ty ), $white, $bold, $l );
	}
	imagefilledrectangle( $im, $tx, (int) round( $ty + 26 ), $tx + 71, (int) round( $ty + 31 ), imagecolorallocate( $im, 168, 224, 122 ) );
	$ol = $wrap( $reg, 22, $org, $tmax );
	foreach ( array_slice( $ol, 0, 2 ) as $i => $l ) {
		imagettftext( $im, $px( 22 ), 0, $tx, $H - 70 + $i * 28 - ( count( $ol ) > 1 ? 28 : 0 ), $soft, $reg, $l );
	}
	return $im;
}

function flxs_gd_round_rect( $im, $x1, $y1, $x2, $y2, $r, $col ) {
	imagefilledrectangle( $im, $x1 + $r, $y1, $x2 - $r, $y2, $col );
	imagefilledrectangle( $im, $x1, $y1 + $r, $x1 + $r - 1, $y2 - $r, $col );
	imagefilledrectangle( $im, $x2 - $r + 1, $y1 + $r, $x2, $y2 - $r, $col );
	imagefilledarc( $im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $col, IMG_ARC_PIE );
	imagefilledarc( $im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 360, $col, IMG_ARC_PIE );
	imagefilledarc( $im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, 90, 180, $col, IMG_ARC_PIE );
	imagefilledarc( $im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, 0, 90, $col, IMG_ARC_PIE );
}
