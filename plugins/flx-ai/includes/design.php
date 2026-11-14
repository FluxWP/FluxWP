<?php
/**
 * Tasarım (0.21.0): marka kiti + HTML tasarımı tam ölçüde PNG/JPEG'e çevirme.
 *
 * - Marka kiti (option flxai_brand_kit): kurum adı, renkler, logolar ve skill'lerin okuduğu Markdown belgeleri
 *   (ör. about-me.md, voice.md). Boş alanlar siteden gelir (site adı, logo, site simgesi, tema paleti).
 * - flxai_design_render: asistanın yazdığı HTML'i (kapak, gönderi görseli, hikâye, afiş) verilen ölçüde görsele
 *   çevirir ve medya kütüphanesine ekler. Paylaşımlı sunucuda tarayıcı yok: Gotenberg (Ayarlar › Görsel çevirici)
 *   ya da flxai_render_html filtresi. Gömülü yazı tipleri (Figtree, Caveat), QR (data-qr) ve metni kutuya sığdırma
 *   (data-fit) her belgeye eklenir; dışarıdan hiçbir şey yüklenmez.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Marka kiti ---------- */

/** Marka kitindeki profil alanları. */
function flxai_brand_fields() {
	return array(
		'name'          => 'text',
		'tagline'       => 'text',
		'website'       => 'text',
		'phone'         => 'text',
		'handle'        => 'text',
		'color_primary' => 'color',
		'color_accent'  => 'color',
		'color_dark'    => 'color',
		'color_light'   => 'color',
		'logo_id'       => 'id',
		'logo_light_id' => 'id',
		'partner_ids'   => 'ids',
		'notes'         => 'textarea',
	);
}

/** Kayıtlı marka kiti: array( profile, docs ). */
function flxai_brand_raw() {
	$v = get_option( 'flxai_brand_kit', array() );
	$v = is_array( $v ) ? $v : array();
	return array(
		'profile' => isset( $v['profile'] ) && is_array( $v['profile'] ) ? $v['profile'] : array(),
		'docs'    => isset( $v['docs'] ) && is_array( $v['docs'] ) ? $v['docs'] : array(),
	);
}

/** Siteden gelen varsayılanlar (boş alanlar için). */
function flxai_brand_defaults() {
	$d = array(
		'name'    => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
		'tagline' => wp_specialchars_decode( (string) get_bloginfo( 'description' ), ENT_QUOTES ),
		'website' => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
		'logo_id' => (int) get_theme_mod( 'custom_logo' ),
		'icon_id' => (int) get_option( 'site_icon' ),
	);
	$palette = array();
	if ( function_exists( 'wp_get_global_settings' ) ) {
		$p = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );
		foreach ( is_array( $p ) ? array_slice( $p, 0, 12 ) : array() as $c ) {
			if ( ! empty( $c['color'] ) && preg_match( '/^#[0-9a-f]{3,8}$/i', (string) $c['color'] ) ) {
				$palette[] = array(
					'slug'  => (string) ( $c['slug'] ?? '' ),
					'name'  => (string) ( $c['name'] ?? '' ),
					'color' => (string) $c['color'],
				);
			}
		}
	}
	$d['theme_palette'] = $palette;
	return $d;
}

/** Görsel kimliği için okunur bilgi. */
function flxai_brand_image( $id ) {
	$id = (int) $id;
	if ( ! $id || ! wp_attachment_is_image( $id ) && 'image/svg+xml' !== get_post_mime_type( $id ) ) {
		return null;
	}
	return array(
		'attachment_id' => $id,
		'url'           => (string) wp_get_attachment_url( $id ),
		'mime'          => (string) get_post_mime_type( $id ),
		'title'         => (string) get_the_title( $id ),
	);
}

/**
 * Marka kiti (asistan için). $doc verilirse o belgenin içeriği; yoksa küçükse hepsi.
 *
 * @return array
 */
function flxai_brand_kit( $doc = '' ) {
	$raw  = flxai_brand_raw();
	$def  = flxai_brand_defaults();
	$prof = array();
	foreach ( flxai_brand_fields() as $k => $type ) {
		$v = $raw['profile'][ $k ] ?? '';
		if ( ( '' === $v || array() === $v || 0 === $v ) && isset( $def[ $k ] ) ) {
			$v = $def[ $k ];
		}
		$prof[ $k ] = $v;
	}
	$out = array(
		'profile'       => $prof,
		'logo'          => flxai_brand_image( $prof['logo_id'] ),
		'logo_light'    => flxai_brand_image( $prof['logo_light_id'] ),
		'site_icon'     => flxai_brand_image( $def['icon_id'] ),
		'partners'      => array_values( array_filter( array_map( 'flxai_brand_image', (array) $prof['partner_ids'] ) ) ),
		'theme_palette' => $def['theme_palette'],
		'docs'          => array(),
	);
	$total = 0;
	foreach ( $raw['docs'] as $name => $md ) {
		$total        += strlen( (string) $md );
		$out['docs'][] = array(
			'name'    => (string) $name,
			'size'    => strlen( (string) $md ),
			'updated' => (string) ( $raw['profile']['_docs_updated'][ $name ] ?? '' ),
		);
	}
	if ( '' !== $doc ) {
		$doc = flxai_brand_doc_name( $doc );
		if ( ! isset( $raw['docs'][ $doc ] ) ) {
			$out['doc'] = array(
				'name'    => $doc,
				'missing' => true,
			);
		} else {
			$out['doc'] = array(
				'name'    => $doc,
				'content' => (string) $raw['docs'][ $doc ],
			);
		}
	} elseif ( $total && $total <= 16000 ) {
		$out['contents'] = $raw['docs'];
	}
	return $out;
}

/** Belge adı: küçük harf, rakam, tire; .md uzantılı. */
function flxai_brand_doc_name( $name ) {
	$name = strtolower( preg_replace( '/\.md$/i', '', trim( (string) $name ) ) );
	$name = trim( preg_replace( '/[^a-z0-9-]+/', '-', $name ), '-' );
	return '' !== $name ? substr( $name, 0, 60 ) . '.md' : '';
}

/**
 * Marka kitini günceller (yalnız verilen alanlar).
 *
 * @param array $profile Profil alanları (kısmi).
 * @param array $docs    name => Markdown (boş metin: belgeyi siler).
 * @return true|WP_Error
 */
function flxai_brand_save( array $profile = array(), array $docs = array() ) {
	$raw = flxai_brand_raw();
	foreach ( flxai_brand_fields() as $k => $type ) {
		if ( ! array_key_exists( $k, $profile ) ) {
			continue;
		}
		$v = $profile[ $k ];
		switch ( $type ) {
			case 'color':
				$v = trim( (string) $v );
				if ( '' !== $v && ! preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $v ) ) {
					/* translators: %s: color value */
					return new WP_Error( 'flxai_brand_color', sprintf( __( '"%s" is not a hex color (e.g. #0A5A40).', 'flx-ai' ), $v ) );
				}
				break;
			case 'id':
				$v = absint( $v );
				break;
			case 'ids':
				$v = is_array( $v ) ? $v : preg_split( '/[\s,]+/', (string) $v, -1, PREG_SPLIT_NO_EMPTY );
				$v = array_slice( array_values( array_filter( array_map( 'absint', $v ) ) ), 0, 6 );
				break;
			case 'textarea':
				$v = mb_substr( sanitize_textarea_field( (string) $v ), 0, 2000 );
				break;
			default:
				$v = mb_substr( sanitize_text_field( (string) $v ), 0, 200 );
		}
		$raw['profile'][ $k ] = $v;
	}
	foreach ( $docs as $name => $md ) {
		$name = flxai_brand_doc_name( $name );
		if ( '' === $name ) {
			continue;
		}
		$md = str_replace( "\r\n", "\n", (string) $md );
		if ( '' === trim( $md ) ) {
			unset( $raw['docs'][ $name ], $raw['profile']['_docs_updated'][ $name ] );
			continue;
		}
		if ( strlen( $md ) > 30000 ) {
			/* translators: %s: document name */
			return new WP_Error( 'flxai_brand_big', sprintf( __( '%s is too long (at most 30 KB).', 'flx-ai' ), $name ) );
		}
		if ( ! isset( $raw['docs'][ $name ] ) && count( $raw['docs'] ) >= 20 ) {
			return new WP_Error( 'flxai_brand_many', __( 'The brand kit can hold at most 20 documents.', 'flx-ai' ) );
		}
		$raw['docs'][ $name ]                     = $md;
		$raw['profile']['_docs_updated'][ $name ] = gmdate( 'Y-m-d' );
	}
	ksort( $raw['docs'] );
	update_option( 'flxai_brand_kit', $raw, false );
	return true;
}

/* ---------- HTML → görsel ---------- */

/** Görsele gömülen yazı tipi dosyaları: ad => yol. */
function flxai_design_font_files() {
	$dir = FLXAI_DIR . 'assets/design/';
	return array(
		'figtree-latin.woff2'     => $dir . 'figtree-latin-wght-normal.woff2',
		'figtree-latin-ext.woff2' => $dir . 'figtree-latin-ext-wght-normal.woff2',
		'caveat-latin.woff2'      => $dir . 'caveat-latin.woff2',
		'caveat-latin-ext.woff2'  => $dir . 'caveat-latin-ext.woff2',
	);
}

/**
 * Tam belge: temel stil + yazı tipleri başa, QR ve sığdırma betiği sona. HTML parça ise sarılır.
 * Hazır olunca window.flxDesignReady = true (çevirici bunu bekler).
 */
function flxai_design_document( $html, $w, $h ) {
	$w    = (int) $w;
	$h    = (int) $h;
	$ext  = 'U+0100-02AF,U+0304,U+0308,U+0329,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF';
	$lat  = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
	$css  = "@font-face{font-family:'Figtree';src:url(figtree-latin.woff2) format('woff2');font-weight:300 900;unicode-range:{$lat}}"
		. "@font-face{font-family:'Figtree';src:url(figtree-latin-ext.woff2) format('woff2');font-weight:300 900;unicode-range:{$ext}}"
		. "@font-face{font-family:'Caveat';src:url(caveat-latin.woff2) format('woff2');font-weight:400 700;unicode-range:{$lat}}"
		. "@font-face{font-family:'Caveat';src:url(caveat-latin-ext.woff2) format('woff2');font-weight:400 700;unicode-range:{$ext}}"
		. "*,*::before,*::after{box-sizing:border-box}html,body{margin:0;padding:0;width:{$w}px;height:{$h}px;overflow:hidden}"
		. "body{position:relative;font-family:Figtree,'Segoe UI',Arial,sans-serif;-webkit-font-smoothing:antialiased;text-rendering:geometricPrecision}"
		. '[data-qr] svg{display:block;width:100%;height:auto}[data-qr] svg rect{fill:#fff}[data-qr] svg path{fill:var(--qr-color,#111)}';
	$head = '<meta charset="utf-8"><meta name="viewport" content="width=' . $w . '"><style id="flx-design-base">' . $css . '</style>';
	$qr   = false !== strpos( $html, 'data-qr' ) ? (string) file_get_contents( FLXAI_DIR . 'assets/design/qrcode.js' ) : ''; // phpcs:ignore
	// Sığdırma: data-fit öğesi kutusuna sığana kadar yazı boyutu %3 adımlarla küçülür (en az data-fit-min, varsayılan %45).
	// Harf kuyrukları (ğ, ş) satırdan taşar: yükseklikte 0,2em pay. Yazı tipleri açıkça yüklenir (yoksa fonts.ready erken döner, ölçüm yedek yazı tipiyle yapılır).
	$js = '<script>' . $qr . '</script><script>(function(){'
		. 'function qr(){if(typeof qrcode!=="function")return;document.querySelectorAll("[data-qr]").forEach(function(e){try{var q=qrcode(0,e.getAttribute("data-qr-level")||"M");q.addData(e.getAttribute("data-qr"),"Byte");q.make();e.innerHTML=q.createSvgTag({cellSize:4,margin:0,scalable:true});}catch(x){e.textContent="";}});}'
		. 'function fit(){document.querySelectorAll("[data-fit]").forEach(function(e){var s=parseFloat(getComputedStyle(e).fontSize),min=s*(parseFloat(e.getAttribute("data-fit-min"))||0.45),i=0;'
		. 'while(i++<40&&s>min&&(e.scrollHeight>e.clientHeight+s*0.2||e.scrollWidth>e.clientWidth+2)){s=s*0.97;e.style.fontSize=s+"px";}});}'
		. 'function imgs(){return Promise.all([].slice.call(document.images).map(function(i){return i.complete?0:new Promise(function(r){i.onload=i.onerror=r;});}));}'
		. 'function fonts(){if(!document.fonts)return Promise.resolve();var f=["400","700","900"].map(function(w){return document.fonts.load(w+" 32px Figtree","ğşİı");});if(/Caveat/.test(document.documentElement.innerHTML))f.push(document.fonts.load("700 32px Caveat"));return Promise.all(f).then(function(){return document.fonts.ready;});}'
		. 'function go(){fonts().then(imgs).then(function(){requestAnimationFrame(function(){fit();setTimeout(function(){window.flxDesignReady=true;},60);});});}'
		. 'qr();if(document.readyState==="complete")go();else window.addEventListener("load",go);'
		. 'setTimeout(function(){window.flxDesignReady=true;},4000);})();</script>';
	if ( preg_match( '/<html[\s>]/i', $html ) ) {
		$doc = preg_match( '/<head[^>]*>/i', $html ) ? preg_replace( '/<head([^>]*)>/i', '<head$1>' . $head, $html, 1 ) : preg_replace( '/<html([^>]*)>/i', '<html$1><head>' . $head . '</head>', $html, 1 );
		$at = strripos( $doc, '</body>' );
		return false !== $at ? substr_replace( $doc, $js, $at, 0 ) : $doc . $js;
	}
	$lang = substr( determine_locale(), 0, 2 );
	return '<!doctype html><html lang="' . esc_attr( $lang ) . '"><head>' . $head . '</head><body>' . $html . $js . '</body></html>';
}

/** Ek görselleri dosyaya çevirir: ad => array( bytes, mime ). $renames: istenen ad => gerçek ad. */
function flxai_design_images( array $images, &$renames = array() ) {
	$renames = array();
	$out     = array();
	$total = 0;
	foreach ( array_slice( $images, 0, 10 ) as $img ) {
		if ( ! is_array( $img ) ) {
			continue;
		}
		$ref = ! empty( $img['attachment_id'] ) ? (int) $img['attachment_id'] : (string) ( $img['url'] ?? '' );
		if ( ! $ref ) {
			continue;
		}
		$b = flxai_media_ref_bytes( $ref, 15728640 );
		if ( is_wp_error( $b ) ) {
			/* translators: %s: image name or id */
			return new WP_Error( 'flxai_design_image', sprintf( __( 'Could not read the image %s.', 'flx-ai' ), is_int( $ref ) ? '#' . $ref : $ref ) );
		}
		$mime = strtolower( (string) $b[1] );
		$exts = array(
			'image/png'     => 'png',
			'image/jpeg'    => 'jpg',
			'image/webp'    => 'webp',
			'image/gif'     => 'gif',
			'image/svg+xml' => 'svg',
		);
		if ( ! isset( $exts[ $mime ] ) ) {
			continue;
		}
		// Gerçek uzantıyla kaydedilir (SVG ancak .svg adıyla açılır); şablondaki ad (ör. bg.jpg) farklıysa HTML'de değiştirilir.
		$want = strtolower( trim( (string) ( $img['name'] ?? '' ) ) );
		$base = trim( preg_replace( '/[^a-z0-9_-]+/', '-', preg_replace( '/\.[a-z0-9]+$/', '', $want ) ), '-' );
		$name = ( '' !== $base ? substr( $base, 0, 40 ) : 'img' . ( count( $out ) + 1 ) ) . '.' . $exts[ $mime ];
		if ( '' !== $want && $want !== $name && preg_match( '/^[a-z0-9_.-]+$/', $want ) ) {
			$renames[ $want ] = $name;
		}
		$total += strlen( $b[0] );
		if ( $total > 31457280 ) {
			return new WP_Error( 'flxai_design_big', __( 'The images are too large in total (at most 30 MB).', 'flx-ai' ) );
		}
		$out[ $name ] = array( $b[0], $mime );
	}
	return $out;
}

/**
 * HTML'i görsele çevirir.
 *
 * @param string $html  Belge ya da gövde parçası.
 * @param array  $args  width, height, format (png|jpeg|webp), quality, files (ad => array( bytes, mime )).
 * @return array|WP_Error array( mime, data, via )
 */
function flxai_design_render_html( $html, array $args ) {
	$w      = max( 200, min( 4096, (int) ( $args['width'] ?? 1080 ) ) );
	$h      = max( 200, min( 4096, (int) ( $args['height'] ?? 1080 ) ) );
	$format = in_array( $args['format'] ?? '', array( 'png', 'jpeg', 'webp' ), true ) ? $args['format'] : 'png';
	$files  = (array) ( $args['files'] ?? array() );
	foreach ( flxai_design_font_files() as $n => $path ) {
		if ( is_readable( $path ) ) {
			$files[ $n ] = array( (string) file_get_contents( $path ), 'font/woff2' ); // phpcs:ignore
		}
	}
	$doc = flxai_design_document( (string) $html, $w, $h );
	$req = array(
		'width'   => $w,
		'height'  => $h,
		'format'  => $format,
		'quality' => max( 40, min( 100, (int) ( $args['quality'] ?? 90 ) ) ),
		'files'   => $files,
	);
	/**
	 * Kendi çeviriciniz (ör. sunucuda Chromium): array( 'mime', 'data' ) ya da WP_Error döndürün; null ise Gotenberg.
	 *
	 * @param null|array $pre  Sonuç.
	 * @param string     $doc  Tam HTML belge (index.html; dosyalar yanında, göreli adla).
	 * @param array      $req  width, height, format, quality, files (ad => array( bytes, mime )).
	 */
	$pre = apply_filters( 'flxai_render_html', null, $doc, $req );
	if ( null !== $pre ) {
		return $pre;
	}
	$base = trim( (string) flxai_setting( 'render_url', '' ) );
	if ( '' === $base ) {
		return new WP_Error( 'flxai_render_off', __( 'No visual renderer (Gotenberg) is set: Flux AI & MCP › Settings › Visual renderer.', 'flx-ai' ) );
	}
	$boundary = 'flxai' . wp_generate_password( 16, false );
	$fields   = array(
		'width'                => (string) $w,
		'height'               => (string) $h,
		'clip'                 => 'true',
		'format'               => $format,
		'optimizeForSpeed'     => 'false',
		'skipNetworkIdleEvent' => 'true',
		'waitForExpression'    => 'window.flxDesignReady === true',
	);
	if ( 'png' !== $format ) {
		$fields['quality'] = (string) $req['quality'];
	}
	$body = '';
	foreach ( $fields as $k => $v ) {
		$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$k}\"\r\n\r\n{$v}\r\n";
	}
	$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"files\"; filename=\"index.html\"\r\nContent-Type: text/html\r\n\r\n{$doc}\r\n";
	foreach ( $files as $n => $f ) {
		$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"files\"; filename=\"{$n}\"\r\nContent-Type: {$f[1]}\r\n\r\n{$f[0]}\r\n";
	}
	$body   .= "--{$boundary}--\r\n";
	$headers = array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary );
	$user    = (string) flxai_setting( 'render_user', '' );
	if ( '' !== $user ) {
		$headers['Authorization'] = 'Basic ' . base64_encode( $user . ':' . (string) flxai_setting( 'key_render', '' ) ); // phpcs:ignore
	}
	$r = wp_remote_post(
		untrailingslashit( $base ) . '/forms/chromium/screenshot/html',
		array(
			'timeout' => 45,
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
	$mime = array(
		'png'  => 'image/png',
		'jpeg' => 'image/jpeg',
		'webp' => 'image/webp',
	)[ $format ];
	if ( 200 !== $code || strlen( $data ) < 100 ) {
		/* translators: %d: HTTP code */
		return new WP_Error( 'flxai_render_failed', sprintf( __( 'Could not convert the visual (HTTP %d).', 'flx-ai' ), $code ) );
	}
	return array(
		'mime' => $mime,
		'data' => $data,
		'via'  => 'gotenberg',
	);
}

/** Tasarım çevirici hazır mı? */
function flxai_design_ready() {
	return '' !== trim( (string) flxai_setting( 'render_url', '' ) ) || has_filter( 'flxai_render_html' );
}

/* ---------- Araçlar ---------- */

add_filter(
	'flxai_tools',
	function ( $tools ) {
		$tools['flxai_brand_kit'] = array(
			'title'       => __( 'Brand kit', 'flx-ai' ),
			'description' => 'Sitenin marka kiti: kurum adı, slogan, web adresi, renkler (primary/accent/dark/light), logo ve beyaz logo (attachment_id + url), iş ortağı logoları, tema paleti ve skill belgeleri (ör. about-me.md, voice.md, newsletter-voice.md). Tasarım ve yazım skill\'leri işe başlamadan bunu okur; boş alan siteden doldurulur. doc verilirse o belgenin içeriği döner.',
			'readonly'    => true,
			'cap'         => 'edit_posts',
			'source'      => 'flx-ai',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'doc' => array( 'type' => 'string', 'description' => 'Okunacak belge, ör. voice.md' ),
				),
			),
			'run'         => fn( $a ) => flxai_brand_kit( (string) ( $a['doc'] ?? '' ) ),
		);
		$tools['flxai_brand_kit_save'] = array(
			'title'       => __( 'Update brand kit', 'flx-ai' ),
			'description' => 'Marka kitini günceller; yalnız verilen alanlar değişir. profile: name, tagline, website, phone, handle, color_primary, color_accent, color_dark, color_light (#hex), logo_id, logo_light_id (koyu zeminde beyaz logo), partner_ids, notes. docs: { "voice.md": "Markdown" } (boş metin belgeyi siler; en çok 20 belge, her biri 30 KB). Belgeyi değiştirirken önce oku, ilgisiz kuralları koru.',
			'readonly'    => false,
			'cap'         => 'edit_others_posts',
			'source'      => 'flx-ai',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'profile' => array( 'type' => 'object' ),
					'docs'    => array(
						'type'                 => 'object',
						'additionalProperties' => array( 'type' => 'string' ),
					),
				),
			),
			'run'         => function ( $a ) {
				$r = flxai_brand_save( is_array( $a['profile'] ?? null ) ? $a['profile'] : array(), is_array( $a['docs'] ?? null ) ? $a['docs'] : array() );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() ); // phpcs:ignore
				}
				$k = flxai_brand_kit();
				return array(
					'saved'   => true,
					'profile' => $k['profile'],
					'docs'    => $k['docs'],
				);
			},
		);
		if ( ! flxai_design_ready() ) {
			return $tools;
		}
		$tools['flxai_design_render'] = array(
			'title'       => __( 'Render design', 'flx-ai' ),
			'description' => 'HTML/CSS tasarımı (kapak, sosyal medya görseli, hikâye, afiş, karusel sayfası) tam olarak width×height piksel PNG/JPEG\'e çevirir ve medya kütüphanesine ekler; attachment_id + url döner (öne çıkan görsel, WhatsApp/e-posta eki). Belge hazır: Figtree (300-900) ve Caveat (el yazısı) yazı tipleri gömülü, dış kaynak YÜKLENMEZ (CDN, Google Fonts, uzak görsel yok). Görseller images ile verilir ve HTML\'de adıyla kullanılır: images=[{name:"bg.jpg",attachment_id:12}] → <img src="bg.jpg"> ya da url(bg.jpg). QR: <div data-qr="https://..."></div> (kare SVG; renk --qr-color). Sığdırma: data-fit verilen öğenin yazısı kutusuna (sabit width/height ya da max-height) sığana kadar küçülür. Taslak için tek ölçü çevir, onaydan sonra diğer ölçüler.',
			'readonly'    => true,
			'cap'         => 'upload_files',
			'source'      => 'flx-ai',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'html'    => array( 'type' => 'string', 'description' => 'Tam HTML belge ya da gövde' ),
					'width'   => array( 'type' => 'integer', 'description' => 'Genişlik px (200-4096)' ),
					'height'  => array( 'type' => 'integer', 'description' => 'Yükseklik px (200-4096)' ),
					'format'  => array(
						'type' => 'string',
						'enum' => array( 'png', 'jpeg', 'webp' ),
					),
					'images'  => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'name'          => array( 'type' => 'string' ),
								'attachment_id' => array( 'type' => 'integer' ),
								'url'           => array( 'type' => 'string' ),
							),
						),
					),
					'title'   => array( 'type' => 'string', 'description' => 'Medya kütüphanesindeki ad' ),
					'alt'     => array( 'type' => 'string', 'description' => 'Alt metin' ),
				),
				'required'   => array( 'html', 'width', 'height' ),
			),
			'run'         => function ( $a, $ctx = array() ) {
				$html = (string) ( $a['html'] ?? '' );
				if ( '' === trim( $html ) ) {
					throw new Exception( __( 'The design HTML is empty.', 'flx-ai' ) ); // phpcs:ignore
				}
				if ( strlen( $html ) > 400000 ) {
					throw new Exception( __( 'The design HTML is too large (at most 400 KB). Pass images with "images", not inline.', 'flx-ai' ) ); // phpcs:ignore
				}
				$files = flxai_design_images( is_array( $a['images'] ?? null ) ? $a['images'] : array(), $renames );
				if ( is_wp_error( $files ) ) {
					throw new Exception( $files->get_error_message() ); // phpcs:ignore
				}
				foreach ( $renames as $from => $to ) {
					$html = preg_replace( '/(?<=[\s"\'(=\/])' . preg_quote( $from, '/' ) . '(?=["\')\s?#])/i', $to, $html );
				}
				$w   = max( 200, min( 4096, (int) ( $a['width'] ?? 1080 ) ) );
				$h   = max( 200, min( 4096, (int) ( $a['height'] ?? 1080 ) ) );
				$t0  = microtime( true );
				$res = flxai_design_render_html(
					$html,
					array(
						'width'  => $w,
						'height' => $h,
						'format' => (string) ( $a['format'] ?? 'png' ),
						'files'  => $files,
					)
				);
				flxai_log(
					array(
						'kind'   => 'tool',
						'source' => sanitize_key( (string) ( $ctx['source'] ?? 'flx-ai' ) ),
						'tool'   => 'design_render',
						'ms'     => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
						'ok'     => ! is_wp_error( $res ),
						'error'  => is_wp_error( $res ) ? $res->get_error_message() : '',
					)
				);
				if ( is_wp_error( $res ) ) {
					throw new Exception( $res->get_error_message() ); // phpcs:ignore
				}
				$f = flxai_media_write( $res['data'], $res['mime'] );
				if ( is_wp_error( $f ) ) {
					throw new Exception( $f->get_error_message() ); // phpcs:ignore
				}
				$title = (string) ( $a['title'] ?? '' );
				$aid   = flxai_media_to_library( $f, '' !== $title ? $title : __( 'Design', 'flx-ai' ), (string) ( $a['alt'] ?? '' ) );
				if ( $aid ) {
					update_post_meta( $aid, '_flxai_design_html', wp_slash( $html ) );
					update_post_meta( $aid, '_flxai_design_size', $w . 'x' . $h );
				}
				return array(
					'attachment_id' => $aid,
					'url'           => $aid ? wp_get_attachment_url( $aid ) : $f['url'],
					'width'         => (int) ( $f['width'] ?? $w ),
					'height'        => (int) ( $f['height'] ?? $h ),
					'bytes'         => (int) $f['bytes'],
					'note'          => 'Sohbette ![başlık](url) olarak göster.',
				);
			},
		);
		return $tools;
	}
);
