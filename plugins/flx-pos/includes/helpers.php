<?php
/**
 * Ortak yardımcılar: ayarlar, şifreleme, tutar biçimleri, HTTP ve XML.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

/** Genel ayarlar (kart yöntemi, taksit, görünüm). */
function flxpos_settings() {
	$d = array(
		'card_title'      => '',
		'card_desc'       => '',
		'card_account'    => '',      // kart yöntemini çeken hesap ('' = ilk etkin kart hesabı)
		'currency_routes' => array(), // para birimi => hesap
		'card_method'     => 'auto',  // auto | flxpos | <gateway id> | all (tek kart yöntemi kuralı)
		'block_fallback'  => 1,       // eklentisi blok desteklemeyen yönlendirmeli yöntemleri blok ödemeye ekle
		'show_brands'     => 1,       // kart formunda kart markası logoları
		'save_log'        => 1,       // ayrıntılı işlem günlüğü (kart verisi yazılmaz)
		'display'         => 'redirect', // 3D/ödeme sayfasının gösterimi: redirect | modal | inline
	);
	$s = get_option( 'flxpos_settings', array() );
	return array_merge( $d, is_array( $s ) ? $s : array() );
}

function flxpos_setting( $key ) {
	$s = flxpos_settings();
	return $s[ $key ] ?? null;
}

/** 3D / ödeme sayfası gösterim biçimleri. */
function flxpos_display_modes() {
	return array(
		'redirect' => __( 'Yönlendirme: banka ya da kuruluş sayfası tam ekran açılır', 'flx-pos' ),
		'modal'    => __( 'Açılır pencere: ödeme sayfasından ayrılmadan, çerçeve içinde', 'flx-pos' ),
		'inline'   => __( 'Site içinde: sitenin üst ve alt bölümüyle, çerçeve içinde', 'flx-pos' ),
	);
}

function flxpos_admin_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=flx-pos' ) );
}

function flxpos_locale() {
	return 0 === strpos( (string) determine_locale(), 'tr' ) ? 'tr' : 'en';
}

/* ---------------------------------------------------------------- */
/* Şifreleme: POS anahtarları veritabanında şifreli saklanır.          */
/* ---------------------------------------------------------------- */

function flxpos_crypto_key() {
	$base = defined( 'FLXPOS_KEY' ) ? (string) FLXPOS_KEY : wp_salt( 'secure_auth' ) . wp_salt( 'auth' );
	return hash( 'sha256', 'flx-pos|' . $base, true );
}

/** Bir metni şifreler; "fx1:" önekli base64 döner. */
function flxpos_encrypt( $plain, $key = null ) {
	$plain = (string) $plain;
	if ( '' === $plain ) {
		return '';
	}
	$key = null === $key ? flxpos_crypto_key() : $key;
	if ( function_exists( 'sodium_crypto_secretbox' ) ) {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return 'fx1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $key ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
	$iv  = random_bytes( 16 );
	$enc = openssl_encrypt( $plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
	$mac = hash_hmac( 'sha256', $iv . $enc, $key, true );
	return 'fx2:' . base64_encode( $iv . $mac . $enc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

function flxpos_decrypt( $value, $key = null ) {
	$value = (string) $value;
	$key   = null === $key ? flxpos_crypto_key() : $key;
	if ( 0 === strpos( $value, 'fx1:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
		$raw = base64_decode( substr( $value, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$out = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $key );
		return false === $out ? '' : $out;
	}
	if ( 0 === strpos( $value, 'fx2:' ) ) {
		$raw = base64_decode( substr( $value, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) <= 48 ) {
			return '';
		}
		$iv  = substr( $raw, 0, 16 );
		$mac = substr( $raw, 16, 32 );
		$enc = substr( $raw, 48 );
		if ( ! hash_equals( hash_hmac( 'sha256', $iv . $enc, $key, true ), $mac ) ) {
			return '';
		}
		$out = openssl_decrypt( $enc, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		return false === $out ? '' : $out;
	}
	return $value; // şifresiz (eski / elle yazılmış)
}

/** Sitenin dönüş adresleri için imza. */
function flxpos_sign( $data ) {
	return substr( hash_hmac( 'sha256', (string) $data, flxpos_crypto_key() ), 0, 32 );
}

/* ---------------------------------------------------------------- */
/* Tutarlar                                                           */
/* ---------------------------------------------------------------- */

/** ISO 4217 sayısal kodlar (bankalar sayısal ister). */
function flxpos_currency_num( $code ) {
	$map = array(
		'TRY' => '949',
		'TL'  => '949',
		'USD' => '840',
		'EUR' => '978',
		'GBP' => '826',
		'JPY' => '392',
		'RUB' => '643',
		'CHF' => '756',
		'SAR' => '682',
		'AED' => '784',
		'MKD' => '807',
		'AZN' => '944',
		'CNY' => '156',
		'KZT' => '398',
		'BHD' => '048',
		'IQD' => '368',
		'BRL' => '986',
		'ARS' => '032',
	);
	return $map[ strtoupper( (string) $code ) ] ?? '949';
}

/** 150 → "150.00" */
function flxpos_dec( $amount, $sep = '.' ) {
	return number_format( round( (float) $amount, 2 ), 2, $sep, '' );
}

/** 150 → "15000" (kuruş) */
function flxpos_minor( $amount ) {
	return (string) (int) round( (float) $amount * 100 );
}

/** Banka yanıtındaki tutar: 150, "150", "150.50", "150,50", "1.250,50", "15050" (kuruş, $minor). */
function flxpos_parse_amount( $v, $minor = false ) {
	if ( is_int( $v ) || is_float( $v ) ) {
		return $minor ? $v / 100 : (float) $v;
	}
	$s = preg_replace( '/[^0-9.,]/', '', (string) $v );
	if ( $minor ) {
		return (float) preg_replace( '/\D/', '', $s ) / 100;
	}
	if ( false !== strpos( $s, ',' ) ) {
		$s = str_replace( array( '.', ',' ), array( '', '.' ), $s );
	}
	return (float) $s;
}

/** İki tutar kuruşuna kadar eşit mi? */
function flxpos_same_amount( $a, $b ) {
	return abs( round( (float) $a, 2 ) - round( (float) $b, 2 ) ) < 0.009;
}

/* ---------------------------------------------------------------- */
/* İstemci                                                            */
/* ---------------------------------------------------------------- */

function flxpos_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return (string) apply_filters( 'flxpos_client_ip', filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '127.0.0.1' );
}

/** Sitenin sunucu IP'si (bazı bankalar ister). */
function flxpos_server_ip() {
	$ip = isset( $_SERVER['SERVER_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '127.0.0.1';
}

/** Yalın ASCII (bankaların çoğu Türkçe karakteri bozar). */
function flxpos_ascii( $s, $max = 0 ) {
	$s = strtr( (string) $s, array( 'ç' => 'c', 'Ç' => 'C', 'ğ' => 'g', 'Ğ' => 'G', 'ı' => 'i', 'İ' => 'I', 'ö' => 'o', 'Ö' => 'O', 'ş' => 's', 'Ş' => 'S', 'ü' => 'u', 'Ü' => 'U' ) );
	$s = remove_accents( $s );
	$s = trim( preg_replace( '/[^A-Za-z0-9 .,@_\-]/', '', $s ) );
	return $max > 0 ? substr( $s, 0, $max ) : $s;
}

/* ---------------------------------------------------------------- */
/* HTTP                                                               */
/* ---------------------------------------------------------------- */

/**
 * Sağlayıcıya istek. Yanıt gövdesi ya da WP_Error. Test ortamında `flxpos_http_pre` ile sahte yanıt verilebilir.
 *
 * @param string $url    Adres.
 * @param mixed  $body   Gövde (dizi = form, metin = olduğu gibi).
 * @param array  $args   headers, method, timeout, provider.
 * @return array{code:int,body:string,headers:array}|WP_Error
 */
function flxpos_http( $url, $body = null, array $args = array() ) {
	$req = array(
		'method'      => $args['method'] ?? ( null === $body ? 'GET' : 'POST' ),
		'timeout'     => (int) ( $args['timeout'] ?? 45 ),
		'headers'     => (array) ( $args['headers'] ?? array() ),
		'body'        => $body,
		'redirection' => 0,
		'user-agent'  => 'FluxPOS/' . FLXPOS_VERSION . '; ' . home_url( '/' ),
		'sslverify'   => (bool) apply_filters( 'flxpos_sslverify', true, $url ),
	);
	$pre = apply_filters( 'flxpos_http_pre', null, $url, $req, $args );
	$r   = null !== $pre ? $pre : wp_remote_request( $url, $req );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	return array(
		'code'    => (int) wp_remote_retrieve_response_code( $r ),
		'body'    => (string) wp_remote_retrieve_body( $r ),
		'headers' => (array) wp_remote_retrieve_headers( $r ),
	);
}

/** JSON yanıtı diziye çevirir; olmazsa WP_Error. */
function flxpos_json( $r, $who = '' ) {
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$j = json_decode( $r['body'], true );
	if ( ! is_array( $j ) ) {
		/* translators: 1: sağlayıcı, 2: HTTP durum kodu */
		return new WP_Error( 'flxpos_http', sprintf( __( '%1$s yanıt vermedi (HTTP %2$d).', 'flx-pos' ), $who ? $who : 'POS', $r['code'] ) );
	}
	return $j;
}

/** XML metni → dizi (öznitelikler atılır, tekil düğümler metin). */
function flxpos_xml_to_array( $xml ) {
	$xml = trim( (string) $xml );
	if ( '' === $xml ) {
		return null;
	}
	$prev = libxml_use_internal_errors( true );
	$x    = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
	libxml_use_internal_errors( $prev );
	if ( false === $x ) {
		return null;
	}
	return flxpos_xml_node( $x );
}

function flxpos_xml_node( SimpleXMLElement $x ) {
	$out = array();
	$ns  = array_merge( array( '' ), array_keys( (array) $x->getDocNamespaces( true ) ) );
	foreach ( $ns as $prefix ) {
		$kids = '' === $prefix ? $x->children() : $x->children( $prefix, true );
		foreach ( $kids as $name => $child ) {
			$v = $child->count() ? flxpos_xml_node( $child ) : trim( (string) $child );
			if ( isset( $out[ $name ] ) ) {
				if ( ! is_array( $out[ $name ] ) || ! isset( $out[ $name ][0] ) ) {
					$out[ $name ] = array( $out[ $name ] );
				}
				$out[ $name ][] = $v;
			} else {
				$out[ $name ] = $v;
			}
		}
	}
	return $out;
}

/** Dizi → XML (anahtar = etiket, dizi = alt düğüm, liste = tekrar). */
function flxpos_array_to_xml( $root, array $data, $decl = 'ISO-8859-9' ) {
	$out = $decl ? '<?xml version="1.0" encoding="' . $decl . '"?>' : '';
	return $out . flxpos_xml_el( $root, $data );
}

function flxpos_xml_el( $name, $v ) {
	if ( is_array( $v ) && isset( $v[0] ) ) {
		$s = '';
		foreach ( $v as $item ) {
			$s .= flxpos_xml_el( $name, $item );
		}
		return $s;
	}
	if ( is_array( $v ) ) {
		$s = '';
		foreach ( $v as $k => $c ) {
			$s .= flxpos_xml_el( $k, $c );
		}
		return '<' . $name . '>' . $s . '</' . $name . '>';
	}
	return '<' . $name . '>' . htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</' . $name . '>';
}

/** İç içe dizide anahtar ara (büyük/küçük harf duyarsız), ilk bulduğunu döner. */
function flxpos_find( $arr, $key, $default = '' ) {
	if ( ! is_array( $arr ) ) {
		return $default;
	}
	foreach ( $arr as $k => $v ) {
		if ( 0 === strcasecmp( (string) $k, $key ) ) {
			return $v;
		}
	}
	foreach ( $arr as $v ) {
		if ( is_array( $v ) ) {
			$f = flxpos_find( $v, $key, null );
			if ( null !== $f ) {
				return $f;
			}
		}
	}
	return $default;
}

/** Günlük için hassas alanları maskeler. */
function flxpos_redact( $data ) {
	if ( is_array( $data ) ) {
		$out = array();
		foreach ( $data as $k => $v ) {
			$lk = strtolower( (string) $k );
			if ( preg_match( '/^(pan|cardnumber|card_number|cardno|ccno|kk_no|number|cvv|cvv2|cvc|cv2|kk_cvc|card_cvv|securitycode|expiry|expdate|ecom_payment_card_expdate_month|ecom_payment_card_expdate_year|password|pass|merchantpassword|provisionpassword|storekey|secret|secretkey|app_secret|merchant_key|api_key|apikey|hash|hashdata|hashpassword|g_sifre|token)$/', $lk ) || false !== strpos( $lk, 'password' ) || false !== strpos( $lk, 'secret' ) ) {
				$out[ $k ] = is_scalar( $v ) && '' !== (string) $v ? '***' : $v;
			} else {
				$out[ $k ] = flxpos_redact( $v );
			}
		}
		return $out;
	}
	if ( is_string( $data ) ) {
		$data = preg_replace( '/\b(\d{6})\d{3,9}(\d{4})\b/', '$1******$2', $data ); // kart numarası
	}
	return $data;
}

/** Düz metin tutar (not ve mesajlar için): "₺150,00". */
function flxpos_money( $amount, $currency ) {
	return function_exists( 'wc_price' ) ? html_entity_decode( wp_strip_all_tags( wc_price( (float) $amount, array( 'currency' => $currency ) ) ), ENT_QUOTES, 'UTF-8' ) : flxpos_dec( $amount ) . ' ' . $currency;
}
