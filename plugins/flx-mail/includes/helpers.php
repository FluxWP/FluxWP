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
		'footer_art'       => 'tepeler',
		'logo'             => '',
		'logo_white'       => '',
		'logo_width'       => 210,
		'color_primary'    => '',   // boş: tema paletinden (flxi_theme_color)
		'color_dark'       => '',
		'color_bg'         => '',
		'color_text'       => '',
		'org_name'         => '',
		'tagline'          => 'Daha yeşil bir Türkiye mümkün',
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
	$plain = (string) $plain;
	if ( '' === $plain ) {
		return '';
	}
	if ( function_exists( 'sodium_crypto_secretbox' ) ) {
		$key   = substr( hash( 'sha256', wp_salt( 'secure_auth' ) . 'flxi', true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return 'flxi1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $key ) );
	}
	return 'flxi0:' . base64_encode( $plain );
}

function flxi_decrypt( $stored ) {
	$stored = (string) $stored;
	if ( 0 === strpos( $stored, 'flxi1:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
		$raw   = base64_decode( substr( $stored, 6 ) );
		$key   = substr( hash( 'sha256', wp_salt( 'secure_auth' ) . 'flxi', true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$out   = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, $key );
		return false === $out ? '' : $out;
	}
	if ( 0 === strpos( $stored, 'flxi0:' ) ) {
		return (string) base64_decode( substr( $stored, 6 ) );
	}
	return $stored;
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
		'logo'       => $s['logo'] ? $s['logo'] : FLXI_URL . 'assets/img/logo.png',
		'logo_white' => $s['logo_white'] ? $s['logo_white'] : FLXI_URL . 'assets/img/logo-beyaz.png',
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
	if ( function_exists( 'flxs_normalize_phone' ) ) {
		return flxs_normalize_phone( $raw );
	}
	$d = preg_replace( '/\D+/', '', (string) $raw );
	if ( '' === $d ) {
		return '';
	}
	if ( 0 === strpos( $d, '00' ) ) {
		$d = substr( $d, 2 );
	}
	if ( 10 === strlen( $d ) && '5' === $d[0] ) {
		$d = '90' . $d;
	} elseif ( 11 === strlen( $d ) && '0' === $d[0] ) {
		$d = '90' . substr( $d, 1 );
	}
	return strlen( $d ) >= 10 && strlen( $d ) <= 15 ? $d : '';
}

/** Alt kısım çizimleri (e-postanın en altındaki ağaç silüeti). */
function flxi_footer_arts() {
	return array(
		'none'    => __( 'None', 'flx-mail' ),
		'orman'   => __( 'Forest', 'flx-mail' ),
		'tepeler' => __( 'Hills', 'flx-mail' ),
		'cizgi'   => __( 'Line art', 'flx-mail' ),
	);
}

/** Seçili çizimin açık/koyu PNG adresleri (yoksa null). */
function flxi_footer_art_urls( $art ) {
	$art = (string) $art;
	if ( 'none' === $art || ! isset( flxi_footer_arts()[ $art ] ) || ! file_exists( FLXI_DIR . 'assets/img/footer/' . $art . '.png' ) ) {
		return null;
	}
	return array(
		'light' => FLXI_URL . 'assets/img/footer/' . $art . '.png',
		'dark'  => FLXI_URL . 'assets/img/footer/' . $art . '-dark.png',
	);
}
