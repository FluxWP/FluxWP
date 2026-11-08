<?php
/**
 * Model bağımsız görseller: cevapta ```widget (HTML/SVG) ve ```mermaid blokları.
 * Sohbet arayüzü bunları sandbox iframe'de çizer; WhatsApp gibi HTML gösteremeyen kanallar
 * flxai_render_widget() ile PNG'ye çevirir (belge: API.md).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** İki #rrggbb rengi karıştırır ($w: ilk rengin payı, 0–1). */
function flxai_mix( $a, $b, $w ) {
	$a = ltrim( (string) $a, '#' );
	$b = ltrim( (string) $b, '#' );
	if ( 6 !== strlen( $a ) || 6 !== strlen( $b ) ) {
		return '#' . $a;
	}
	$o = '#';
	for ( $i = 0; $i < 6; $i += 2 ) {
		$o .= str_pad( dechex( (int) round( hexdec( substr( $a, $i, 2 ) ) * $w + hexdec( substr( $b, $i, 2 ) ) * ( 1 - $w ) ) ), 2, '0', STR_PAD_LEFT );
	}
	return $o;
}

/** Tema renkleri: widget'lar yalnız bu değişkenleri kullanır (açık/koyu tema uyumu). */
function flxai_widget_palette( $dark = false ) {
	// Vurgu: Flux Admin'in rengi (o da varsayılan olarak tema rengini alır), yoksa FluxWP indigo.
	$acc = function_exists( 'flxa_accent' ) ? flxa_accent() : '#1F2937';
	$p   = $dark
		? array( 'bg' => '#161b20', 'surface' => '#1e252c', 'text' => '#e8edf1', 'muted' => '#98a4af', 'accent' => flxai_mix( $acc, '#ffffff', .7 ), 'accent-soft' => flxai_mix( $acc, '#0d0e13', .25 ), 'border' => '#2e3842', 'warn' => '#e5b45a', 'danger' => '#f08080' )
		: array( 'bg' => '#ffffff', 'surface' => '#f6f7f8', 'text' => '#14202b', 'muted' => '#66727e', 'accent' => $acc, 'accent-soft' => flxai_mix( $acc, '#ffffff', .1 ), 'border' => '#e3e6ea', 'warn' => '#b7791f', 'danger' => '#c53030' );
	return (array) apply_filters( 'flxai_widget_palette', $p, $dark );
}

/**
 * Modele verilen görsel kuralları (sistem talimatına eklenir).
 *
 * @param string $mode html: sohbet (HTML+CSS ya da SVG, mermaid) · svg: görsele çevrilecek kanallar (yalnız SVG).
 */
function flxai_widget_rules( $mode = 'html' ) {
	$vars = '--bg, --surface, --text, --muted, --accent, --accent-soft, --border, --warn, --danger';
	if ( 'svg' === $mode ) {
		$r = "## Görseller\n"
			. "- Tablo, grafik, şema ya da kart gibi bir görsel cevabı gerçekten güçlendirecekse ```widget kod bloğu içinde TEK bir <svg> yaz; görsel kullanıcıya resim olarak gönderilir.\n"
			. "- <svg> kök öğe olsun, viewBox ver (genişlik en fazla 800), width=\"100%\". HTML, <foreignObject>, script, dış bağlantı, <image href=http…> kullanma.\n"
			. "- Renkleri doğrudan yaz (ör. #13906f yeşil, #14202b metin, #e3e6ea çizgi); yazı tipi font-family=\"sans-serif\". Emoji yerine basit şekiller kullan.\n"
			. "- Görselin dışında kısa bir metin açıklaması da yaz; görsel gönderilemezse metin yeterli olmalı.";
	} else {
		$r = "## Görseller\n"
			. "- Tablo, karşılaştırma, akış, zaman çizelgesi, özet kartı ya da basit grafik cevabı gerçekten güçlendirecekse ```widget kod bloğu içinde tek parça HTML + inline CSS ya da SVG yaz. Basit cevaplarda kullanma.\n"
			. "- Bloğun içinde yalnız gövde içeriği olsun (<html>/<head> yok). Dış kaynak, fetch, form, bağlantı, <img src=http…> yok; görsel gerekiyorsa SVG ya da data: kullan.\n"
			. "- Genişlik 100%, sabit yükseklik verme; içerik yüksekliği belirler. Başlık, kutu, daire, ok, gradient, yarı saydam emoji arka plan kullanabilirsin.\n"
			. '- Renkler için yalnız CSS değişkenleri: ' . $vars . " (ör. color: var(--text); border: 1px solid var(--border)). Böylece açık/koyu temaya uyar.\n"
			. "- Akış şeması, sıra diyagramı, gantt gibi şemalar için ```mermaid bloğu da kullanabilirsin.\n"
			. '- Görseli metinle birlikte ver; kodu ayrıca açıklama.';
	}
	return (string) apply_filters( 'flxai_widget_rules', $r, $mode );
}

/**
 * Metindeki ```widget / ```mermaid bloklarını ayırır.
 *
 * @return array array( 'text' => blokları çıkarılmış metin, 'widgets' => [ { type: widget|mermaid, code } ] )
 */
function flxai_extract_widgets( $text ) {
	$widgets = array();
	$clean   = preg_replace_callback(
		'/^```(widget|mermaid)[^\n]*\n(.*?)\n```[ \t]*$/ms',
		function ( $m ) use ( &$widgets ) {
			$widgets[] = array(
				'type' => $m[1],
				'code' => $m[2],
			);
			return '';
		},
		(string) $text
	);
	return array(
		'text'    => trim( preg_replace( "/\n{3,}/", "\n\n", (string) $clean ) ),
		'widgets' => $widgets,
	);
}

/** CSS değişkenlerini (var(--x[, yedek])) paletle değiştirir; SVG çeviriciler değişken bilmez. */
function flxai_widget_resolve_vars( $code, $dark = false ) {
	$p = flxai_widget_palette( $dark );
	return preg_replace_callback(
		'/var\(\s*--([a-z0-9-]+)\s*(?:,\s*([^()]+?))?\s*\)/i',
		function ( $m ) use ( $p ) {
			$k = strtolower( $m[1] );
			return $p[ $k ] ?? ( isset( $m[2] ) ? trim( $m[2] ) : $p['text'] );
		},
		(string) $code
	);
}

/**
 * Widget'ı tek başına açılabilen HTML belgesine çevirir (sunucu tarafı çevirici ve önizleme için).
 * Tarayıcıdaki sohbet aynı CSP ile kendi belgesini kurar.
 */
function flxai_widget_document( $code, $dark = false, $width = 720 ) {
	$css = '';
	foreach ( flxai_widget_palette( $dark ) as $k => $v ) {
		$css .= '--' . $k . ':' . $v . ';';
	}
	return '<!doctype html><html><head><meta charset="utf-8">'
		. '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data:; font-src data:; script-src \'unsafe-inline\'">'
		. '<style>:root{' . $css . 'color-scheme:' . ( $dark ? 'dark' : 'light' ) . '}*{box-sizing:border-box}html,body{margin:0;padding:0}'
		. 'body{width:' . (int) $width . 'px;padding:16px;background:var(--bg);color:var(--text);font:15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}'
		. 'svg{max-width:100%;height:auto}</style></head><body>' . $code . '</body></html>';
}

/** Yalnız SVG mi (kök <svg>, HTML yok)? */
function flxai_widget_is_svg( $code ) {
	$c = trim( preg_replace( '/^<\?xml[^>]*>\s*/i', '', trim( (string) $code ) ) );
	return (bool) preg_match( '/^<svg[\s>].*<\/svg>$/is', $c ) && ! preg_match( '/<foreignObject|<script/i', $c );
}

/** Sunucuda hangi çevirici kullanılabilir: gotenberg | imagick | '' */
function flxai_widget_renderer() {
	if ( '' !== trim( (string) flxai_setting( 'render_url', '' ) ) ) {
		return 'gotenberg';
	}
	if ( class_exists( 'Imagick' ) ) {
		try {
			$f = Imagick::queryFormats( 'SVG' );
			if ( $f ) {
				return 'imagick';
			}
		} catch ( Exception $e ) {
			return '';
		}
	}
	return '';
}

/**
 * Widget'ı PNG'ye çevirir (WhatsApp vb. için).
 *
 * Sıra: flxai_render_widget filtresi (kendi çeviriciniz) → Gotenberg (Ayarlar › Görseller, HTML+SVG) → Imagick (yalnız SVG).
 * Hostinger paylaşımlı sunucuda tarayıcı yok; HTML widget'lar için Gotenberg (Docker) gerekir.
 *
 * @param string $code Widget kodu (```widget içi) ya da SVG.
 * @param array  $args { dark bool, width int (varsayılan 720) }
 * @return array|WP_Error array( 'mime' => 'image/png', 'data' => ikili, 'via' => gotenberg|imagick|filter )
 */
function flxai_render_widget( $code, array $args = array() ) {
	$code  = (string) $code;
	$dark  = ! empty( $args['dark'] );
	$width = max( 240, min( 1200, (int) ( $args['width'] ?? 720 ) ) );
	if ( '' === trim( $code ) ) {
		return new WP_Error( 'flxai_render_empty', __( 'Visual code is empty.', 'flx-ai' ) );
	}
	$pre = apply_filters( 'flxai_render_widget', null, $code, $args );
	if ( null !== $pre ) {
		return $pre;
	}
	$via = flxai_widget_renderer();
	$t0  = microtime( true );
	if ( 'gotenberg' === $via ) {
		$res = flxai_render_gotenberg( flxai_widget_document( $code, $dark, $width ), $width );
	} elseif ( 'imagick' === $via && flxai_widget_is_svg( $code ) ) {
		$res = flxai_render_imagick( flxai_widget_resolve_vars( $code, $dark ), $width, $dark );
	} else {
		return new WP_Error(
			'flxai_render_unavailable',
			'imagick' === $via
				? __( 'This visual contains HTML; the server can only convert SVG. For HTML, enter a Gotenberg address in Flux AI & MCP › Settings › Visuals.', 'flx-ai' )
				: __( 'No visual renderer on the server (Imagick with SVG support or a Gotenberg address is required).', 'flx-ai' )
		);
	}
	flxai_log(
		array(
			'kind'   => 'tool',
			'source' => sanitize_key( (string) ( $args['source'] ?? 'flx-ai' ) ),
			'tool'   => 'render_widget',
			'ms'     => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
			'ok'     => ! is_wp_error( $res ),
			'error'  => is_wp_error( $res ) ? $res->get_error_message() : '',
		)
	);
	return $res;
}

/**
 * Gotenberg 8: POST /forms/chromium/screenshot/html (index.html). $conf: url, user, pass (boşsa kayıtlı ayarlar; "Test et" kaydetmeden dener).
 */
function flxai_render_gotenberg( $html, $width, $conf = array() ) {
	$base     = isset( $conf['url'] ) && '' !== $conf['url'] ? (string) $conf['url'] : (string) flxai_setting( 'render_url', '' );
	$url      = untrailingslashit( trim( $base ) ) . '/forms/chromium/screenshot/html';
	$boundary = 'flxai' . wp_generate_password( 16, false );
	$fields   = array(
		'width'                => (string) ( $width + 32 ),
		'height'               => '200',
		'clip'                 => 'false',
		'format'               => 'png',
		'optimizeForSpeed'     => 'true',
		'skipNetworkIdleEvent' => 'true',
	);
	$body = '';
	foreach ( $fields as $k => $v ) {
		$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$k}\"\r\n\r\n{$v}\r\n";
	}
	// Tam sayfa: yükseklik içeriğe göre uzar (Gotenberg ekran görüntüsü tüm sayfayı alır).
	$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"files\"; filename=\"index.html\"\r\nContent-Type: text/html\r\n\r\n{$html}\r\n--{$boundary}--\r\n";
	$headers = array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary );
	$user    = isset( $conf['user'] ) ? (string) $conf['user'] : (string) flxai_setting( 'render_user', '' );
	$pass    = isset( $conf['pass'] ) && '' !== $conf['pass'] ? (string) $conf['pass'] : (string) flxai_setting( 'key_render', '' );
	if ( '' !== $user ) {
		$headers['Authorization'] = 'Basic ' . base64_encode( $user . ':' . $pass ); // phpcs:ignore
	}
	$r = wp_remote_post(
		$url,
		array(
			'timeout' => 30,
			'headers' => $headers,
			'body'    => $body,
		)
	);
	if ( is_wp_error( $r ) ) {
		/* translators: %s: error */
		return new WP_Error( 'flxai_render_http', sprintf( __( 'Could not reach the visual renderer: %s', 'flx-ai' ), $r->get_error_message() ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	$data = (string) wp_remote_retrieve_body( $r );
	if ( 401 === $code || 403 === $code ) {
		return new WP_Error( 'flxai_render_auth', __( 'The image renderer rejected the username or password.', 'flx-ai' ) );
	}
	if ( 200 !== $code || "\x89PNG" !== substr( $data, 0, 4 ) ) {
		/* translators: %d: HTTP code */
		return new WP_Error( 'flxai_render_failed', sprintf( __( 'Could not convert the visual (HTTP %d).', 'flx-ai' ), $code ) );
	}
	return array(
		'mime' => 'image/png',
		'data' => $data,
		'via'  => 'gotenberg',
	);
}

/** Imagick ile SVG → PNG (beyaz/koyu zemin). */
function flxai_render_imagick( $svg, $width, $dark ) {
	try {
		$svg = trim( preg_replace( '/^<\?xml[^>]*>\s*/i', '', $svg ) );
		if ( ! preg_match( '/^<svg[^>]*xmlns=/i', $svg ) ) {
			$svg = preg_replace( '/^<svg/i', '<svg xmlns="http://www.w3.org/2000/svg"', $svg, 1 );
		}
		$im = new Imagick();
		$im->setBackgroundColor( new ImagickPixel( flxai_widget_palette( $dark )['bg'] ) );
		$im->setResolution( 144, 144 );
		$im->readImageBlob( '<?xml version="1.0" encoding="UTF-8"?>' . $svg );
		$im->setImageFormat( 'png' );
		if ( $im->getImageWidth() > $width * 2 ) {
			$im->resizeImage( $width * 2, 0, Imagick::FILTER_LANCZOS, 1 );
		}
		$im   = $im->mergeImageLayers( Imagick::LAYERMETHOD_FLATTEN );
		$data = $im->getImageBlob();
		$im->clear();
		return array(
			'mime' => 'image/png',
			'data' => $data,
			'via'  => 'imagick',
		);
	} catch ( Exception $e ) {
		/* translators: %s: error */
		return new WP_Error( 'flxai_render_failed', sprintf( __( 'Could not convert SVG: %s', 'flx-ai' ), $e->getMessage() ) );
	}
}
