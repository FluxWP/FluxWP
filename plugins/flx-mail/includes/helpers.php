<?php
/**
 * Ayarlar ve ortak yardımcılar.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Sitenin alan adı (www'suz). bilgi@ / no-reply@ varsayılanları bundan üretilir. */
function flxi_site_domain() {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	return preg_replace( '/^www\./i', '', strtolower( $host ) );
}

/** Flux SEO "Kurum ve sosyal" ayarları (varsa). */
function flxi_seo_option( $key ) {
	$o = get_option( 'flxseo', array() );
	return is_array( $o ) && isset( $o[ $key ] ) ? (string) $o[ $key ] : '';
}

function flxi_defaults() {
	return (array) apply_filters( 'flxi_defaults', flxi_default_values() );
}

/** Eklentinin nötr varsayılanları; kuruma özel değer (slogan, alt çizim) ön ayardan `flxi_defaults` filtresiyle gelir. */
function flxi_default_values() {
	$domain = flxi_site_domain();
	$name   = flxi_seo_option( 'site_name' );
	if ( '' === $name ) {
		$name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}
	return array(
		// Gönderen
		'from_name'        => $name,
		'from_email'       => 'bilgi@' . $domain,
		'noreply_name'     => $name,
		'noreply_email'    => 'no-reply@' . $domain,
		'reply_to'         => '',
		'force_from'       => 1,
		'set_sender'       => 1,
		// Gönderim yöntemi
		'mailer'           => 'php',
		'smtp_host'        => '',
		'smtp_port'        => 465,
		'smtp_secure'      => 'ssl',
		'smtp_auth'        => 1,
		'smtp_user'        => '',
		'smtp_pass'        => '',
		'smtp_nr_user'     => '',
		'smtp_nr_pass'     => '',
		// Şablon
		'wrap_plain'       => 1,
		'wrap_html'        => 1,
		'email_images_fix' => 1,
		'embed_images'     => 1,
		'footer_art'       => 'none',
		'logo'             => '',
		'logo_white'       => '',
		'logo_width'       => 210,
		'color_primary'    => '',   // boş: tema paletinden (flxi_theme_color)
		'color_dark'       => '',
		'color_bg'         => '',
		'color_text'       => '',
		'org_name'         => '',
		'tagline'          => '',
		'address'          => '',
		'phone'            => '',
		'contact_email'    => '',
		'website'          => '',
		'social'           => array(),
		'footer_note'      => '',
		// Kayıt
		'log_enabled'      => 1,
		'log_days'         => 60,
		'log_body'         => 1,
		// Google ile giriş
		'social_block_core' => 1,
		'social_block_wc'   => 1,
		'social_verified'   => 1,
		// Toplu gönderim
		'bulk_per_run'     => 20,
		'bulk_unsubscribe' => 1,
		// Tanıtım mesajlarından çıkış (IPTAL); boş metinler site dilinde varsayılanı kullanır.
		'optout_enabled'   => 1,
		'optout_footer_wa' => 1,
		'optout_footer_sms' => 0,
		'optout_text'      => '',
		'optout_keywords'  => '',
		'optin_keywords'   => '',
		'optout_reply'     => '',
		'optin_reply'      => '',
		// Posta kutusu sınırları (Hostinger e-posta paketi)
		'limit_daily'          => 100,
		'limit_recipients'     => 100,
		'limit_size_mb'        => 35,
		'limit_attach_mb'      => 25,
		'limit_storage_gb'     => 1,
		'limit_mailbox_emails' => 15000,
		'limit_aliases'        => 5,
		'limit_forwarders'     => 10,
		'limit_reserve'        => 20,
		// Posta kutusu (IMAP: gelen ve gönderilen e-postalar)
		'mailbox_enabled'      => 0,
		'imap_host'            => 'imap.hostinger.com',
		'imap_port'            => 993,
		'imap_secure'          => 'ssl',
		'imap_user'            => '',
		'imap_pass'            => '',
		'mailbox_days'         => 30,
		'mailbox_keep_days'    => 365,
		'mailbox_signature'    => '',
	);
}

/** Ayarlar (kaydedilmemiş alanlar varsayılandan gelir). */
function flxi_settings( $reset = false ) {
	static $cache = null;
	if ( null === $cache || $reset ) {
		$saved = get_option( 'flxi_settings', array() );
		$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), flxi_defaults() );
	}
	return $cache;
}

function flxi_setting( $key ) {
	$s = flxi_settings();
	return $s[ $key ] ?? null;
}

function flxi_update_settings( array $changes ) {
	$saved = get_option( 'flxi_settings', array() );
	$saved = is_array( $saved ) ? $saved : array();
	foreach ( $changes as $k => $v ) {
		$saved[ $k ] = $v;
	}
	update_option( 'flxi_settings', $saved, true );
	flxi_settings( true );
}

/* ------------------------------------------------------------------ */
/* Gizli alanlar (SMTP şifresi, API anahtarları)                        */
/* ------------------------------------------------------------------ */

/** Veritabanında düz metin durmasın diye site anahtarıyla şifrelenir (veritabanı tek başına sızarsa okunmaz). */
function flxi_encrypt( $plain ) {
	return flx_secret_seal( (string) $plain, 'flx-mail' );
}

function flxi_decrypt( $stored ) {
	if ( ! function_exists( 'flx_secret_open' ) && function_exists( 'flx_brand_boot' ) ) {
		flx_brand_boot(); // Read before after_setup_theme (e.g. mail sent while plugins load): load the shared core now.
	}
	if ( ! function_exists( 'flx_secret_open' ) ) {
		return '';
	}
	$stored = (string) $stored;
	if ( 0 === strpos( $stored, 'flxi0:' ) ) {
		return (string) base64_decode( substr( $stored, 6 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	}
	// Old boxes used a raw SHA-256 key, not generichash, so $legacy cannot open them.
	if ( 0 === strpos( $stored, 'flxi1:' ) ) {
		try {
			$raw = base64_decode( substr( $stored, 6 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$n   = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
			if ( ! is_string( $raw ) || strlen( $raw ) <= $n ) {
				return '';
			}
			$key = substr( hash( 'sha256', wp_salt( 'secure_auth' ) . 'flxi', true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
			$out = sodium_crypto_secretbox_open( substr( $raw, $n ), substr( $raw, 0, $n ), $key );
			return false === $out ? '' : $out;
		} catch ( Throwable $e ) {
			unset( $e );
			return '';
		}
	}
	return flx_secret_open( $stored, 'flx-mail' );
}

/** SMTP şifresi: wp-config.php'de FLXI_SMTP_PASS tanımlıysa o kullanılır. */
function flxi_smtp_password( $noreply = false ) {
	if ( $noreply ) {
		if ( defined( 'FLXI_SMTP_NOREPLY_PASS' ) ) {
			return (string) FLXI_SMTP_NOREPLY_PASS;
		}
		return flxi_decrypt( flxi_setting( 'smtp_nr_pass' ) );
	}
	if ( defined( 'FLXI_SMTP_PASS' ) ) {
		return (string) FLXI_SMTP_PASS;
	}
	return flxi_decrypt( flxi_setting( 'smtp_pass' ) );
}

/** Gizli değerin panelde gösterilecek hali (yalnız son 2 karakter). */
function flxi_mask( $plain ) {
	$plain = (string) $plain;
	if ( '' === $plain ) {
		return '';
	}
	return str_repeat( '•', 8 ) . ( strlen( $plain ) > 6 ? substr( $plain, -2 ) : '' );
}

/* ------------------------------------------------------------------ */
/* Marka / kurum bilgisi                                               */
/* ------------------------------------------------------------------ */

/** E-posta şablonunda kullanılan kurum ve görünüm bilgileri. Boş alanlar Flux SEO'dan ve siteden doldurulur. */
function flxi_brand() {
	$s     = flxi_settings();
	$org   = $s['org_name'] ? $s['org_name'] : flxi_seo_option( 'org_name' );
	$org   = $org ? $org : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$addr  = $s['address'];
	if ( '' === $addr ) {
		$addr = trim( implode( ', ', array_filter( array( flxi_seo_option( 'street' ), flxi_seo_option( 'locality' ) ) ) ) );
	}
	$phone = $s['phone'] ? $s['phone'] : flxi_seo_option( 'phone' );
	$mail  = $s['contact_email'] ? $s['contact_email'] : ( flxi_seo_option( 'email' ) ? flxi_seo_option( 'email' ) : $s['from_email'] );
	$web   = $s['website'] ? $s['website'] : home_url( '/' );
	$site  = flxi_site_logos();

	$social = array();
	foreach ( (array) $s['social'] as $k => $url ) {
		if ( $url ) {
			$social[ $k ] = esc_url_raw( $url );
		}
	}
	if ( ! $social ) {
		foreach ( preg_split( '/\s+/', flxi_seo_option( 'same_as' ) ) as $url ) {
			$k = flxi_social_service( $url );
			if ( $k && empty( $social[ $k ] ) ) {
				$social[ $k ] = esc_url_raw( $url );
			}
		}
	}

	$brand = array(
		'name'       => $s['from_name'] ? $s['from_name'] : $org,
		'org'        => $org,
		'tagline'    => $s['tagline'],
		'address'    => $addr,
		'phone'      => $phone,
		'email'      => $mail,
		'website'    => $web,
		'host'       => preg_replace( '/^www\./', '', (string) wp_parse_url( $web, PHP_URL_HOST ) ),
		// Ayarda logo yoksa sitenin kendi logosu; o da yoksa boş kalır ve düzen site adını (varsa simgesiyle) yazar.
		'logo'       => $s['logo'] ? $s['logo'] : $site['logo'],
		'logo_white' => $s['logo_white'] ? $s['logo_white'] : ( $s['logo'] ? '' : $site['logo_white'] ),
		'icon'       => $site['icon'],
		'logo_width' => max( 80, min( 320, (int) $s['logo_width'] ) ),
		'primary'    => flxi_hex( $s['color_primary'], flxi_theme_color( 'color_primary' ) ),
		'dark'       => flxi_hex( $s['color_dark'], flxi_theme_color( 'color_dark' ) ),
		'bg'         => flxi_hex( $s['color_bg'], flxi_theme_color( 'color_bg' ) ),
		'text'       => flxi_hex( $s['color_text'], flxi_theme_color( 'color_text' ) ),
		'social'     => $social,
		'note'       => $s['footer_note'],
		'art'        => flxi_footer_art_urls( $s['footer_art'] ),
	);
	return apply_filters( 'flxi_brand', $brand );
}

/**
 * Sitenin kendi logoları (e-posta ayarında logo seçilmemişse kullanılır). Sıra:
 * Flux SEO kurum logosu > kurum profili logosu (ön ayarlar doldurur) > temanın "Site Logosu".
 * E-posta istemcileri SVG göstermediği için yalnız PNG/JPG/GIF/WebP alınır. Koyu zemin logosu
 * kurum profilindeki açık renk logodur (logo_light). Simge: kurum profili simgesi > WordPress site simgesi.
 *
 * @return array{logo:string,logo_white:string,icon:string}
 */
function flxi_site_logos() {
	$url = function ( $v, $size = 'full' ) {
		if ( is_numeric( $v ) && (int) $v > 0 ) {
			$v = (string) wp_get_attachment_image_url( (int) $v, $size );
		}
		$v = is_string( $v ) ? trim( $v ) : '';
		return $v && preg_match( '/\.(png|jpe?g|gif|webp)(\?.*)?$/i', $v ) ? esc_url_raw( $v ) : '';
	};
	$profile = function ( $key ) {
		return function_exists( 'flx_profile' ) ? flx_profile( $key, '' ) : '';
	};
	$logo = $url( flxi_seo_option( 'logo' ) );
	if ( '' === $logo ) {
		$logo = $url( $profile( 'logo' ) );
	}
	if ( '' === $logo ) {
		$logo = $url( (int) get_theme_mod( 'custom_logo' ) );
	}
	$icon = $url( $profile( 'icon' ), array( 96, 96 ) );
	if ( '' === $icon && has_site_icon() ) {
		$icon = $url( get_site_icon_url( 192 ) );
	}
	$out = array(
		'logo'       => $logo,
		'logo_white' => $logo ? $url( $profile( 'logo_light' ) ) : '',
		'icon'       => $icon,
	);
	return wp_parse_args( (array) apply_filters( 'flxi_site_logos', $out ), $out );
}

/**
 * E-postanın üstündeki logo: logo görseli (koyu modda açık renk sürümüyle), logo yoksa site simgesi + site adı.
 *
 * @param array  $b     flxi_brand().
 * @param int    $width Logo genişliği (px).
 * @param string $font  Yazı tipi ailesi (logo yokken ad için).
 * @return string
 */
function flxi_logo_markup( array $b, $width, $font ) {
	$width = (int) $width;
	if ( '' !== (string) $b['logo'] ) {
		$dark = (string) $b['logo_white'];
		$swap = '' !== $dark && $dark !== $b['logo'];
		$o    = '<img' . ( $swap ? ' class="flxi-logo-l"' : '' ) . ' src="' . esc_url( $b['logo'] ) . '" width="' . $width . '" alt="' . esc_attr( $b['org'] ) . '" style="display:block;width:' . $width . 'px;max-width:100%;height:auto;">';
		if ( $swap ) {
			$o .= '<!--[if !mso]><!--><img class="flxi-logo-d" src="' . esc_url( $dark ) . '" width="' . $width . '" alt="' . esc_attr( $b['org'] ) . '" style="display:none;width:' . $width . 'px;max-width:100%;height:auto;max-height:0;overflow:hidden;"><!--<![endif]-->';
		}
		return $o;
	}
	$name = '<span class="flxi-brandname" style="font-family:' . $font . ';font-size:20px;line-height:1.25;font-weight:800;color:' . $b['text'] . ';">' . esc_html( $b['name'] ) . '</span>';
	if ( '' === (string) $b['icon'] ) {
		return $name;
	}
	return '<table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr>'
		. '<td valign="middle" style="padding-right:10px;"><img src="' . esc_url( $b['icon'] ) . '" width="36" height="36" alt="" style="display:block;width:36px;height:36px;border-radius:8px;"></td>'
		. '<td valign="middle">' . $name . '</td></tr></table>';
}

/**
 * E-posta renginin varsayılanı: sitenin tema paletindeki karşılığı (FluxWP temasında indigo; ön ayar ya da
 * Site Editor değiştirdiyse o). Tema paleti yoksa FluxWP renkleri.
 *
 * @param string $key color_primary | color_dark | color_bg | color_text.
 * @return string
 */
function flxi_theme_color( $key ) {
	static $palette = null;
	$map = array(
		'color_primary' => array( 'primary', '#1F2937' ),
		'color_dark'    => array( 'primary-dark', '#111827' ),
		'color_bg'      => array( 'mint-soft', '#F8F5F0' ),
		'color_text'    => array( 'ink', '#111827' ),
	);
	if ( ! isset( $map[ $key ] ) ) {
		return '';
	}
	if ( null === $palette ) {
		$palette = array();
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$p = wp_get_global_settings( array( 'color', 'palette' ) );
			foreach ( array( 'theme', 'custom' ) as $origin ) {
				foreach ( (array) ( $p[ $origin ] ?? array() ) as $c ) {
					$hex = sanitize_hex_color( $c['color'] ?? '' );
					if ( $hex && ! empty( $c['slug'] ) ) {
						$palette[ $c['slug'] ] = $hex;
					}
				}
			}
		}
	}
	return $palette[ $map[ $key ][0] ] ?? $map[ $key ][1];
}

function flxi_hex( $v, $fallback ) {
	$v = sanitize_hex_color( (string) $v );
	return $v ? $v : $fallback;
}

function flxi_social_services() {
	return array(
		'instagram' => 'Instagram',
		'facebook'  => 'Facebook',
		'x'         => 'X',
		'youtube'   => 'YouTube',
		'linkedin'  => 'LinkedIn',
		'whatsapp'  => 'WhatsApp',
	);
}

function flxi_social_service( $url ) {
	$host = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_HOST ) );
	$map  = array(
		'instagram.com' => 'instagram',
		'facebook.com'  => 'facebook',
		'twitter.com'   => 'x',
		'x.com'         => 'x',
		'youtube.com'   => 'youtube',
		'youtu.be'      => 'youtube',
		'linkedin.com'  => 'linkedin',
		'wa.me'         => 'whatsapp',
		'whatsapp.com'  => 'whatsapp',
	);
	foreach ( $map as $h => $k ) {
		if ( $host === $h || substr( $host, -strlen( '.' . $h ) ) === '.' . $h ) {
			return $k;
		}
	}
	return '';
}

/* ------------------------------------------------------------------ */
/* Değişkenler                                                         */
/* ------------------------------------------------------------------ */

/** {degisken} yer tutucularını doldurur. $html true ise değerler kaçışlanır. */
function flxi_fill( $text, array $vars, $html = false ) {
	$vars  = array_merge( flxi_global_vars(), $vars );
	$empty = false;
	$out   = preg_replace_callback(
		'/\{([a-z0-9_]+)\}/i',
		function ( $m ) use ( $vars, $html, &$empty ) {
			$k = strtolower( $m[1] );
			if ( ! array_key_exists( $k, $vars ) ) {
				return $m[0];
			}
			$v = is_scalar( $vars[ $k ] ) ? (string) $vars[ $k ] : '';
			if ( '' === trim( $v ) ) {
				$empty = true;
				return "\x1A";
			}
			// _html ile biten değişkenler hazır HTML'dir (ör. {mesaj_html}); kaçışlanmaz.
			if ( $html && '_html' !== substr( $k, -5 ) ) {
				return esc_html( $v );
			}
			return $html ? $v : ( '_html' === substr( $k, -5 ) ? wp_strip_all_tags( $v ) : $v );
		},
		(string) $text
	);
	if ( $empty ) {
		// Boş değişkenin bıraktığı boşlukları topla: "Merhaba {ad}," → "Merhaba,".
		$out = preg_replace(
			array( '/[ \t]*(?:\x1A[ \t]*)+(?=[,.!?;:])/u', '/[ \t]*(?:\x1A[ \t]*)+/u', '/(^|\n|>) /u', '/ (\n|$|<\/)/u' ),
			array( '', ' ', '$1', '$1' ),
			$out
		);
	}
	return $out;
}

function flxi_global_vars() {
	$b = flxi_brand();
	return array(
		'site'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'site_url' => home_url( '/' ),
		'kurum'    => $b['org'],
		'yil'      => wp_date( 'Y' ),
		'tarih'    => flx_date( time() ),
		'kurum_telefon' => $b['phone'],
		'kurum_eposta'  => $b['email'],
	);
}

/** Kişinin hitap adı (ad, yoksa görünen ad, yoksa kullanıcı adı). */
function flxi_user_first_name( $user ) {
	if ( ! $user instanceof WP_User ) {
		return '';
	}
	$n = trim( (string) $user->first_name );
	if ( '' === $n ) {
		$n = trim( (string) $user->display_name );
	}
	return '' !== $n ? $n : $user->user_login;
}

function flxi_now() {
	return current_time( 'mysql', true );
}

function flxi_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . 'flxi_' . $name;
}

function flxi_json( $v ) {
	return wp_json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

function flxi_can_manage() {
	return current_user_can( 'manage_options' ) || current_user_can( 'flxi_manage' );
}

/** Admin sayfa adresi. */
function flxi_admin_url( $page = 'flxi', $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
}

/** Telefonu uluslararası rakam biçimine çevirir: 0532 123 45 67 → 905321234567. Flux Certificates ile aynı kural. */
function flxi_normalize_phone( $raw ) {
	return flx_phone_digits( $raw, 'tr' );
}

/** WhatsApp grup kimliği mi (120363…@g.us, eski biçim 905321234567-1600000000@g.us). */
function flxi_is_wa_group( $to ) {
	return (bool) preg_match( '/^\d{5,30}(-\d{5,20})?@g\.us$/', trim( (string) $to ) );
}

/** Evolution / Flux WPAPI için alıcı: grup kimliği olduğu gibi, telefon rakam biçiminde. '' geçersiz. */
function flxi_wa_number( $to ) {
	$to = trim( (string) $to );
	return flxi_is_wa_group( $to ) ? $to : flxi_normalize_phone( $to );
}

/** Cloud API grup mesajı göndermez: grup alıcısında dönen hata. */
function flxi_wa_group_unsupported() {
	return array( 'ok' => false, 'error' => __( 'WhatsApp Cloud API cannot send to groups. Choose an Evolution API or Flux WPAPI connection for group messages.', 'flx-mail' ), 'retry' => false, 'code' => 'group_unsupported' );
}

/** Alt kısım çizimleri (e-postanın en altındaki ağaç silüeti). */
function flxi_footer_arts() {
	// Ön ayarlar kendi çizimini `flxi_footer_arts` (anahtar => ad) + `flxi_footer_art_urls` (anahtar => light/dark adresi) ile ekler.
	return (array) apply_filters(
		'flxi_footer_arts',
		array(
			'none'    => __( 'None', 'flx-mail' ),
			'orman'   => __( 'Forest', 'flx-mail' ),
			'tepeler' => __( 'Hills', 'flx-mail' ),
			'cizgi'   => __( 'Line art', 'flx-mail' ),
		)
	);
}

/** Seçili çizimin açık/koyu PNG adresleri (yoksa null). */
function flxi_footer_art_urls( $art ) {
	$art = (string) $art;
	$ext = apply_filters( 'flxi_footer_art_urls', null, $art );
	if ( is_array( $ext ) && ! empty( $ext['light'] ) && 'none' !== $art ) {
		return array(
			'light' => (string) $ext['light'],
			'dark'  => (string) ( $ext['dark'] ?? $ext['light'] ),
		);
	}
	if ( 'none' === $art || ! isset( flxi_footer_arts()[ $art ] ) || ! file_exists( FLXI_DIR . 'assets/img/footer/' . $art . '.png' ) ) {
		return null;
	}
	return array(
		'light' => FLXI_URL . 'assets/img/footer/' . $art . '.png',
		'dark'  => FLXI_URL . 'assets/img/footer/' . $art . '-dark.png',
	);
}

/** Medya adresinin türü (WhatsApp): image | video | document. */
function flxi_media_kind( $url, $name = '' ) {
	$ext = strtolower( pathinfo( (string) ( $name ? $name : wp_parse_url( (string) $url, PHP_URL_PATH ) ), PATHINFO_EXTENSION ) );
	if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif' ), true ) ) {
		return 'image';
	}
	if ( in_array( $ext, array( 'mp4', '3gp', 'mov' ), true ) ) {
		return 'video';
	}
	return 'document';
}

/** Medya MIME türü (bilinmiyorsa türe göre varsayılan). */
function flxi_media_mime( $url, $name, $kind ) {
	$file = (string) ( $name ? $name : wp_parse_url( (string) $url, PHP_URL_PATH ) );
	$t    = wp_check_filetype( $file );
	if ( ! empty( $t['type'] ) ) {
		return $t['type'];
	}
	return 'image' === $kind ? 'image/jpeg' : ( 'video' === $kind ? 'video/mp4' : 'application/pdf' );
}

/** Ön ayar / AI ile yazılabilir e-posta şablonu alanları (flx_settings_schema). */
add_filter(
	'flx_settings_schema',
	function ( $s ) {
		$s['flxi_settings'] = array(
			'label'  => 'Flux Mail',
			'fields' => array(
				'tagline'    => array( 'type' => 'string', 'desc' => 'E-posta alt kısmındaki slogan.' ),
				'footer_art' => array( 'type' => 'string', 'desc' => 'E-posta altındaki çizim anahtarı (none, orman, tepeler, cizgi ya da ön ayarın eklediği).' ),
			),
		);
		return $s;
	}
);
