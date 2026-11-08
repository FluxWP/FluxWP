<?php
/**
 * Flux Certificates köprüsü (Flux Certificates'nın kodu değiştirilmeden):
 *  - Sertifika e-postası Flux Mail şablonuyla gider: logo, sertifikanın kendisi (görsel, tıklayınca sertifika sayfası), bilgiler, kurumsal alt bilgi.
 *  - Flux Certificates'ya "Flux Mail · WhatsApp / SMS" sağlayıcıları eklenir; gönderim buradaki bağlantılardan yapılır.
 *  - "Sağlayıcıları taşı": Flux Certificates'daki Evolution/Netgsm/SMTP ayarlarını buraya kopyalar, sertifikayı yeni sağlayıcılara bağlar (geri alınabilir).
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

function flxi_flxs_active() {
	return function_exists( 'flxs_get_certificate' ) && function_exists( 'flxs_certificate_vars' ) && function_exists( 'flxs_provider_types' );
}

/* ------------------------------------------------------------------ */
/* Şablon                                                              */
/* ------------------------------------------------------------------ */

add_filter(
	'flxi_email_types',
	function ( $types ) {
		if ( ! flxi_flxs_active() ) {
			return $types;
		}
		$types['flx-certificates/certificate'] = array(
			'label'       => __( 'Certificate ready', 'flx-mail' ),
			'group'       => 'Flux Certificates',
			'description' => __( 'Sent to the person when the certificate is emailed. The certificate image appears in the email, and clicking it opens the certificate page (where it can be downloaded and shared). {mesaj_html} is the email text from Flux Certificates › Settings or the template.', 'flx-mail' ),
			'recipient'   => 'user',
			'image'       => true,
			'vars'        => array(
				'ad'           => __( 'Person\'s name', 'flx-mail' ),
				'ad_soyad'     => __( 'Full name', 'flx-mail' ),
				'sertifika'    => __( 'Certificate name', 'flx-mail' ),
				'sertifika_no' => __( 'Certificate number', 'flx-mail' ),
				'fidan_sayisi' => __( 'Number of saplings (for sapling certificates)', 'flx-mail' ),
				'tarih'        => __( 'Certificate date', 'flx-mail' ),
				'link'         => __( 'Certificate page', 'flx-mail' ),
				'konu'         => __( 'Email subject from Flux Certificates', 'flx-mail' ),
				'mesaj_html'   => __( 'Email text from Flux Certificates', 'flx-mail' ),
			),
			'sample'      => array(
				'ad'           => 'Ahmet',
				'ad_soyad'     => 'Ahmet Hayrullah Yılmaz',
				'sertifika'    => __( 'Sapling Donation Certificate', 'flx-mail' ),
				'sertifika_no' => '2026-00042',
				'fidan_sayisi' => '7',
				'tarih'        => wp_date( 'd.m.Y' ),
				'link'         => home_url( '/sertifika/ornek/' ),
				'konu'         => __( 'Your Sapling Donation Certificate is ready 🌱', 'flx-mail' ),
				'mesaj_html'   => wpautop( __( 'Hello Ahmet,

Your Sapling Donation Certificate is ready. Thank you very much for your support.', 'flx-mail' ) ),
			),
			'defaults'    => array(
				'subject'     => '{konu}',
				'heading'     => __( 'Your certificate is ready 🌱', 'flx-mail' ),
				'body'        => '{mesaj_html}',
				'button_text' => __( 'View my certificate', 'flx-mail' ),
				'button_url'  => '{link}',
				'sender'      => 'info',
			),
		);
		return $types;
	}
);

/** Önizlemede örnek sertifika görseli. */
add_filter(
	'flxi_template_args',
	function ( $args, $key, $vars, $sample = false ) {
		if ( 'flx-certificates/certificate' !== $key ) {
			return $args;
		}
		// Örnek görsel yalnız önizleme ve testte; gerçek gönderimde görsel yoksa düğme yeterli.
		if ( empty( $args['image'] ) && $sample ) {
			$args['image']     = FLXI_URL . 'assets/img/sertifika-ornek.jpg';
			$args['image_link'] = $vars['link'] ?? '';
		}
		$args['image_width']   = 340;
		$args['image_alt']     = ! empty( $args['image_alt'] ) ? $args['image_alt'] : trim( ( $vars['sertifika'] ?? '' ) . ' · ' . ( $vars['ad_soyad'] ?? '' ), ' ·' );
		$args['image_caption'] = ! empty( $args['image_caption'] ) ? $args['image_caption'] : esc_html__( 'Tap the image to open your certificate', 'flx-mail' );
		if ( empty( $args['facts'] ) ) {
			$facts = array();
			if ( ! empty( $vars['fidan_sayisi'] ) ) {
				$facts[ __( 'Sapling', 'flx-mail' ) ] = sprintf(
					/* translators: %s: fidan sayısı */
					__( '%s saplings', 'flx-mail' ),
					number_format_i18n( (float) $vars['fidan_sayisi'] )
				);
			}
			$facts[ __( 'Certificate no.', 'flx-mail' ) ] = (string) ( $vars['sertifika_no'] ?? '' );
			$facts[ __( 'Date', 'flx-mail' ) ]        = (string) ( $vars['tarih'] ?? '' );
			$args['facts'] = $facts;
		}
		if ( empty( $args['note'] ) ) {
			$args['note'] = esc_html__( 'You can download it as a PDF from the certificate page and share it on WhatsApp and social media.', 'flx-mail' );
		}
		return $args;
	},
	10,
	4
);

/* ------------------------------------------------------------------ */
/* Flux Certificates sağlayıcıları                                          */
/* ------------------------------------------------------------------ */

add_filter(
	'flxs_provider_types',
	function ( $types ) {
		// E-posta: Flux Certificates'nın kendi tipleri Flux Mail üzerinden gönderir.
		foreach ( array( 'wpmail', 'smtp' ) as $t ) {
			if ( isset( $types[ $t ] ) ) {
				$types[ $t ]['send'] = 'flxi_flxs_email_send';
				$types[ $t ]['help'] = ( $types[ $t ]['help'] ?? '' ) . ' ' . __( 'Flux Mail is active: the email is sent with the branded template and the sender address from Communication › Settings.', 'flx-mail' );
			}
		}
		$choices = array( '' => __( 'Default connection', 'flx-mail' ) );
		foreach ( array( 'whatsapp', 'sms' ) as $ch ) {
			$opts = $choices;
			foreach ( flxi_list_connections( $ch ) as $c ) {
				$opts[ (string) $c['id'] ] = $c['name'] . ( $c['active'] ? '' : ' (' . __( 'disabled', 'flx-mail' ) . ')' );
			}
			$fields = array(
				array( 'key' => 'connection', 'label' => __( 'Flux Mail connection', 'flx-mail' ), 'type' => 'select', 'default' => '', 'options' => $opts ),
				array( 'key' => 'delay', 'label' => __( 'Delay between messages in bulk send (sec)', 'flx-mail' ), 'type' => 'number', 'default' => 'whatsapp' === $ch ? 4 : 0 ),
			);
			if ( 'whatsapp' === $ch ) {
				$fields[] = array( 'key' => 'send_pdf', 'label' => __( 'Also send the PDF as a document', 'flx-mail' ), 'type' => 'checkbox', 'default' => 0 );
			}
			$types[ 'flxi_' . $ch ] = array(
				'label'   => 'whatsapp' === $ch ? __( 'WhatsApp · Flux Mail', 'flx-mail' ) : __( 'SMS · Flux Mail', 'flx-mail' ),
				'channel' => $ch,
				'help'    => __( 'Connection settings (Evolution / Netgsm) are kept in Communication › Connections; here you only choose which one to use.', 'flx-mail' ),
				'fields'  => $fields,
				'send'    => 'flxi_flxs_message_send',
				'test'    => 'flxi_flxs_message_test',
			);
		}
		return $types;
	},
	20
);

function flxi_flxs_message_send( $config, $msg, $provider = array() ) {
	$channel = $provider['channel'] ?? ( 'flxi_sms' === ( $provider['type'] ?? '' ) ? 'sms' : 'whatsapp' );
	$media   = '';
	$file    = '';
	if ( 'whatsapp' === $channel && ! empty( $config['send_pdf'] ) && ! empty( $msg['certificate_id'] ) ) {
		$cert = flxs_get_certificate( $msg['certificate_id'] );
		if ( $cert && $cert['pdf_file'] ) {
			$media = flxs_certificate_file_url( $cert, 'pdf' );
			$file  = sanitize_file_name( $cert['number'] . '.pdf' );
		}
	}
	return flxi_send_message(
		$channel,
		$msg['recipient'],
		(string) $msg['body'],
		array(
			'connection_id' => (int) ( $config['connection'] ?? 0 ),
			'source'        => 'flx-certificates',
			'template'      => 'flx-certificates/' . $channel,
			'media'         => $media,
			'file_name'     => $file,
		)
	);
}

function flxi_flxs_message_test( $config, $to, $provider ) {
	$channel = $provider['channel'] ?? 'whatsapp';
	$conn    = flxi_resolve_connection( $channel, (int) ( $config['connection'] ?? 0 ) );
	if ( ! $conn ) {
		return array( 'ok' => false, 'error' => __( 'There is no active connection for this channel in Flux Mail.', 'flx-mail' ) );
	}
	return flxi_test_connection( $conn['id'], $to );
}

/** Sertifika e-postası. */
function flxi_flxs_email_send( $config, $msg, $provider = array() ) {
	$to = sanitize_email( (string) $msg['recipient'] );
	if ( ! is_email( $to ) ) {
		/* translators: %s: e-posta */
		return array( 'ok' => false, 'error' => sprintf( __( 'Invalid email: %s', 'flx-mail' ), $msg['recipient'] ), 'retry' => false );
	}
	$cert = ! empty( $msg['certificate_id'] ) ? flxs_get_certificate( $msg['certificate_id'] ) : null;
	$text = trim( preg_replace( '/^[ \t]*\{buton\}[ \t]*\n?/m', '', (string) $msg['body'] ) );
	$text = str_replace( '{buton}', '', $text );

	$error = '';
	$catch = function ( $e ) use ( &$error ) {
		$error = $e->get_error_message();
	};
	// Flux Certificates'da ayrı SMTP hesabı tanımlıysa ve burada SMTP yoksa o hesap kullanılır.
	$smtp = null;
	if ( 'smtp' === ( $provider['type'] ?? '' ) && ! flxi_smtp_ready() && ! empty( $config['host'] ) ) {
		$smtp = function ( $m ) use ( $config ) {
			$m->isSMTP();
			$m->Host        = $config['host'];
			$m->Port        = (int) ( $config['port'] ? $config['port'] : 587 );
			$m->SMTPSecure  = (string) $config['secure'];
			$m->SMTPAutoTLS = '' !== (string) $config['secure'];
			$m->SMTPAuth    = '' !== (string) $config['username'];
			$m->Username    = (string) $config['username'];
			$m->Password    = (string) $config['password'];
			$m->Timeout     = 20;
		};
		add_action( 'phpmailer_init', $smtp, 998 );
	}
	add_action( 'wp_mail_failed', $catch );

	if ( $cert ) {
		$vars               = flxs_certificate_vars( $cert );
		$vars['konu']       = (string) $msg['subject'];
		$vars['mesaj_html'] = flxi_text_to_html( $text );
		$image              = flxi_certificate_image( $cert );
		$ok                 = flxi_send_email(
			array(
				'to'         => $to,
				'template'   => 'flx-certificates/certificate',
				'vars'       => $vars,
				'image'      => $image,
				'image_link' => $vars['link'],
				'reply_to'   => $config['reply_to'] ?? '',
			)
		);
	} else {
		// Test e-postası (sertifikasız)
		$ok = flxi_send_email(
			array(
				'to'       => $to,
				'subject'  => (string) $msg['subject'],
				'heading'  => esc_html( (string) $msg['subject'] ),
				'body'     => flxi_text_to_html( $text ),
				'template' => 'flx-certificates/test',
				'reply_to' => $config['reply_to'] ?? '',
			)
		);
	}

	remove_action( 'wp_mail_failed', $catch );
	if ( $smtp ) {
		remove_action( 'phpmailer_init', $smtp, 998 );
	}
	if ( $ok ) {
		return array( 'ok' => true, 'response' => array( 'mailer' => 'flx-mail' ) );
	}
	return array( 'ok' => false, 'error' => $error ? $error : __( 'wp_mail returned false (the server may be unable to send email).', 'flx-mail' ), 'retry' => true );
}

/* ------------------------------------------------------------------ */
/* Sertifika görseli                                                   */
/* ------------------------------------------------------------------ */

/**
 * E-postada gösterilecek sertifika görseli: üretilmiş sertifika görseli > link önizleme görseli >
 * sunucuda şablon arka planı + isimle çizilen önizleme (dosyalar henüz tarayıcıda üretilmediyse).
 */
function flxi_certificate_image( $cert ) {
	foreach ( array( 'image', 'og' ) as $t ) {
		$p = flxs_certificate_file_path( $cert, $t );
		if ( $p && file_exists( $p ) ) {
			return flxs_certificate_file_url( $cert, $t );
		}
	}
	$gen = flxi_render_certificate_preview( $cert );
	return $gen ? $gen : '';
}

/** Yazı tipi dosyası (Flux Certificates'nın yazı tipleri). */
function flxi_flxs_font_file( $id ) {
	$fonts = function_exists( 'flxs_builtin_fonts' ) ? flxs_builtin_fonts() : array();
	$f     = $fonts[ $id ] ?? ( $fonts['nunito'] ?? null );
	if ( ! $f || ! defined( 'FLXS_DIR' ) ) {
		return '';
	}
	$path = FLXS_DIR . 'assets/fonts/' . $f[1];
	return file_exists( $path ) ? $path : '';
}

/**
 * Şablonun arka plan görseli üzerine metin alanlarını GD ile çizer (QR hariç). Sonuç uploads/flx-mail/sertifika/ altında saklanır.
 *
 * @return string URL ya da ''.
 */
function flxi_render_certificate_preview( $cert ) {
	if ( ! function_exists( 'imagecreatefromjpeg' ) || ! function_exists( 'imagettftext' ) || ! function_exists( 'flxs_get_template' ) ) {
		return '';
	}
	$tpl = flxs_get_template( $cert['template_id'] );
	if ( ! $tpl ) {
		return '';
	}
	$cfg  = $tpl['config'];
	$vars = flxs_certificate_vars( $cert, null, $tpl );
	$hash = substr( md5( flxi_json( array( $cfg['fields'] ?? array(), $vars, $tpl['thumb'] ?? '', 2 ) ) ), 0, 10 );
	$up   = wp_upload_dir();
	$dir  = trailingslashit( $up['basedir'] ) . 'flx-mail/sertifika';
	$name = $cert['code'] . '-' . $hash . '.jpg';
	$url  = trailingslashit( $up['baseurl'] ) . 'flx-mail/sertifika/' . $name;
	if ( file_exists( $dir . '/' . $name ) ) {
		return $url;
	}

	// Arka plan: yazısız temiz görsel gerekir (şablon küçük resminde örnek yazılar var).
	$bg  = '';
	$bgc = (array) ( $cfg['background'] ?? array() );
	if ( 'image' === ( $bgc['type'] ?? '' ) ) {
		if ( ! empty( $bgc['id'] ) ) {
			$bg = (string) get_attached_file( (int) $bgc['id'] );
		}
		if ( ( ! $bg || ! file_exists( $bg ) ) && ! empty( $bgc['url'] ) && 0 === strpos( $bgc['url'], $up['baseurl'] ) ) {
			$bg = $up['basedir'] . substr( $bgc['url'], strlen( $up['baseurl'] ) );
		}
	} elseif ( ! empty( $bgc['file'] ) && 'bundled' === ( $bgc['source'] ?? '' ) ) {
		// Hazır PDF arka planların JPG hali Flux Certificates'ın hazır klasörlerinde (raster/).
		if ( function_exists( 'flxs_bundled_path' ) ) {
			$jpg = preg_replace( '/\.pdf$/i', '.jpg', basename( (string) $bgc['file'] ) );
			$bg  = flxs_bundled_path( $jpg, 'raster/' );
			$bg  = '' !== $bg ? $bg : flxs_bundled_path( $jpg );
		}
	}
	$bg = apply_filters( 'flxi_certificate_background', $bg, $tpl, $cert );
	if ( ! $bg || ! file_exists( $bg ) ) {
		return '';
	}
	// Sunucunun GD'si FreeType desteklemiyorsa yazı çizilemez; önizleme yapılmaz.
	$probe = flxi_flxs_font_file( 'nunito' );
	if ( ! $probe || ! @imagettfbbox( 10, 0, $probe, 'A' ) ) { // phpcs:ignore
		return '';
	}
	$src = @imagecreatefromstring( (string) file_get_contents( $bg ) ); // phpcs:ignore
	if ( ! $src ) {
		return '';
	}
	$pw = (float) ( $cfg['page']['w'] ?? 0 );
	$ph = (float) ( $cfg['page']['h'] ?? 0 );
	if ( $pw <= 0 || $ph <= 0 ) {
		imagedestroy( $src );
		return '';
	}
	// En az 900 px uzun kenar (e-postada keskin görünsün).
	$sw    = imagesx( $src );
	$sh    = imagesy( $src );
	$out_w = max( $sw, (int) round( 900 * ( $pw >= $ph ? 1 : $pw / $ph ) ) );
	$out_h = (int) round( $out_w * $ph / $pw );
	$im    = imagecreatetruecolor( $out_w, $out_h );
	imagecopyresampled( $im, $src, 0, 0, 0, 0, $out_w, $out_h, $sw, $sh );
	imagedestroy( $src );
	$k = $out_w / $pw; // pt → px

	foreach ( (array) ( $cfg['fields'] ?? array() ) as $f ) {
		$type = $f['type'] ?? 'text';
		if ( 'qr' === $type || ( isset( $f['draw'] ) && false === $f['draw'] ) || (int) ( $f['page'] ?? 0 ) > 0 ) {
			continue;
		}
		$text = 'static' === $type ? flxs_fill( (string) ( $f['default'] ?? '' ), $vars ) : (string) ( $vars[ $f['key'] ] ?? '' );
		if ( '' === trim( $text ) ) {
			continue;
		}
		if ( ! empty( $f['upper'] ) ) {
			$text = mb_strtoupper( str_replace( array( 'i', 'ı' ), array( 'İ', 'I' ), $text ), 'UTF-8' );
		}
		$font = flxi_flxs_font_file( $f['font'] ?? 'nunito' );
		if ( ! $font ) {
			continue;
		}
		flxi_gd_text_box( $im, $text, $font, $f, $k );
	}

	wp_mkdir_p( $dir );
	if ( ! file_exists( $dir . '/index.php' ) ) {
		file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
	}
	$ok = imagejpeg( $im, $dir . '/' . $name, 86 );
	imagedestroy( $im );
	return $ok ? $url : '';
}

/** Sertifika tarayıcı çizicisiyle aynı kutu mantığı: (x,y) sol üst, w genişlik, satır kaydırma, sığdırma, hizalama. */
function flxi_gd_text_box( $im, $text, $font, array $f, $k ) {
	$size_pt  = (float) ( $f['size'] ?? 14 );
	$letter   = (float) ( $f['letter'] ?? 0 );
	$maxw     = (float) ( $f['w'] ?? 100 );
	$maxl     = max( 1, (int) ( $f['lines'] ?? 1 ) );
	$fit      = ! isset( $f['fit'] ) || false !== $f['fit'];
	$align    = $f['align'] ?? 'center';
	$measure  = function ( $s, $size ) use ( $font, $letter ) {
		$b = imagettfbbox( $size * 0.75, 0, $font, $s );
		return abs( $b[2] - $b[0] ) + $letter * max( 0, mb_strlen( $s ) - 1 );
	};
	$wrap = function ( $size ) use ( $text, $measure, $maxw ) {
		$out = array();
		foreach ( explode( "\n", $text ) as $para ) {
			$line = '';
			foreach ( preg_split( '/\s+/u', trim( $para ) ) as $word ) {
				$test = '' === $line ? $word : $line . ' ' . $word;
				if ( '' === $line || $measure( $test, $size ) <= $maxw ) {
					$line = $test;
				} else {
					$out[] = $line;
					$line  = $word;
				}
			}
			$out[] = $line;
		}
		return $out;
	};
	// Ölçüler pt cinsinden; çizimde px'e çevrilir.
	if ( 1 === $maxl ) {
		$text  = preg_replace( '/\s*\n\s*/u', ' ', $text );
		$w     = $measure( $text, $size_pt );
		if ( $fit && $w > $maxw ) {
			$size_pt = max( 4, $size_pt * $maxw / $w );
		}
		$lines = array( $text );
	} else {
		$lines = $wrap( $size_pt );
		$guard = 0;
		while ( $fit && count( $lines ) > $maxl && $size_pt > 5 && $guard++ < 40 ) {
			$size_pt *= 0.95;
			$lines    = $wrap( $size_pt );
		}
		$lines = array_slice( $lines, 0, $maxl );
	}
	$hex   = ltrim( (string) ( $f['color'] ?? '#4a5b66' ), '#' );
	$hex   = 3 === strlen( $hex ) ? $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] : str_pad( $hex, 6, '0' );
	$color = imagecolorallocate( $im, hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	$asc   = $size_pt * 0.93;
	$lh    = $size_pt * (float) ( $f['lineHeight'] ?? 1.25 );
	$px    = $size_pt * $k * 0.75; // GD punto (96 dpi) cinsinden
	foreach ( $lines as $i => $ln ) {
		$lw = $measure( $ln, $size_pt );
		$x  = (float) $f['x'];
		if ( 'center' === $align ) {
			$x += ( $maxw - $lw ) / 2;
		} elseif ( 'right' === $align ) {
			$x += $maxw - $lw;
		}
		$y = (float) $f['y'] + $asc + $i * $lh;
		if ( $letter ) {
			$cx = $x * $k;
			foreach ( preg_split( '//u', $ln, -1, PREG_SPLIT_NO_EMPTY ) as $ch ) {
				imagettftext( $im, $px, 0, (int) round( $cx ), (int) round( $y * $k ), $color, $font, $ch );
				$cx += ( $measure( $ch, $size_pt ) + $letter ) * $k;
			}
		} else {
			imagettftext( $im, $px, 0, (int) round( $x * $k ), (int) round( $y * $k ), $color, $font, $ln );
		}
	}
}

