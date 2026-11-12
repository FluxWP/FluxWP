<?php
/**
 * İsteğin dili ve adresler.
 *
 * /en/hakkimizda/ isteğinde "en" öneki REQUEST_URI'dan çıkarılır (WordPress /hakkimizda/ olarak çözer),
 * dil "en" olur ve o istekte üretilen tüm site adreslerine (home_url) /en/ eklenir. Yerel ayar en_US olur;
 * tema ve eklentilerin arayüz metinleri kendi çevirileriyle İngilizce görünür.
 *
 * Önbellek: sunucu dili yalnız adresten belirler (çerez/IP'ye göre farklı sayfa üretmez), LiteSpeed sayfa önbelleği güvenle çalışır.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['flxl_state'] = array(
	'current'  => '',     // İsteğin dili.
	'explicit' => false,  // Dil adresten ya da lang parametresinden geldi.
	'uri'      => '',     // Önek çıkarılmadan önceki REQUEST_URI.
	'root'     => '',     // Önek eklenmemiş site kökü (home seçeneği).
	'raw'      => 0,      // >0 iken home_url'e önek eklenmez (iç hesaplar için).
);

/**
 * Sitenin önek eklenmemiş kök adresi, sonda / yok.
 */
function flxl_root() {
	if ( '' === $GLOBALS['flxl_state']['root'] ) {
		$GLOBALS['flxl_state']['root'] = untrailingslashit( (string) get_option( 'home' ) );
	}
	return $GLOBALS['flxl_state']['root'];
}

function flxl_root_path() {
	return untrailingslashit( (string) wp_parse_url( flxl_root(), PHP_URL_PATH ) );
}

/**
 * İsteğin dili.
 */
function flxl_current_language() {
	$c = $GLOBALS['flxl_state']['current'];
	return '' !== $c ? $c : flxl_default_language();
}

/**
 * Dil adresten ya da lang parametresinden mi geldi (REST/AJAX süzme kararı için).
 */
function flxl_is_explicit() {
	return ! empty( $GLOBALS['flxl_state']['explicit'] );
}

/**
 * Geçici olarak başka dile geç (e-posta, AI işi, sitemap). flxl_restore_language() ile geri dön.
 */
function flxl_switch_language( $code ) {
	if ( ! flxl_is_language( $code ) ) {
		return false;
	}
	$GLOBALS['flxl_state']['stack'][] = array( $GLOBALS['flxl_state']['current'], $GLOBALS['flxl_state']['explicit'] );
	$GLOBALS['flxl_state']['current']  = $code;
	$GLOBALS['flxl_state']['explicit'] = true;
	switch_to_locale( flxl_language_locale( $code ) );
	flxl_apply_text_direction();
	return true;
}

function flxl_restore_language() {
	if ( empty( $GLOBALS['flxl_state']['stack'] ) ) {
		return false;
	}
	list( $GLOBALS['flxl_state']['current'], $GLOBALS['flxl_state']['explicit'] ) = array_pop( $GLOBALS['flxl_state']['stack'] );
	restore_previous_locale();
	flxl_apply_text_direction();
	return true;
}

/**
 * Önek eklemeden çalıştır (ör. url_to_postid, kanonik hesap).
 */
function flxl_raw( callable $fn ) {
	$GLOBALS['flxl_state']['raw']++;
	try {
		return $fn();
	} finally {
		$GLOBALS['flxl_state']['raw']--;
	}
}

/**
 * Bu istek yönetim ekranı mı (dil hep varsayılan, adresler öneksiz).
 */
function flxl_is_backend() {
	if ( wp_doing_ajax() ) {
		return false;
	}
	return is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron();
}

/**
 * Eklenti yüklenirken çağrılır: öneki bulur, REQUEST_URI'dan çıkarır.
 */
function flxl_detect_request() {
	$def = flxl_default_language();
	$st  = &$GLOBALS['flxl_state'];
	$st['current'] = $def;

	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
		return;
	}
	$uri        = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$st['uri']  = $uri;
	$codes      = flxl_language_codes();

	// lang parametresi (REST, AJAX, wc-ajax): ?lang=en.
	$param = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' !== $param && in_array( $param, $codes, true ) && ( wp_doing_ajax() || flxl_uri_is_rest( $uri ) || isset( $_GET['wc-ajax'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$st['current']  = $param;
		$st['explicit'] = true;
	}

	if ( is_admin() ) {
		// Ön yüzden gelen admin-ajax isteği: sayfanın dili referer'dan.
		if ( wp_doing_ajax() && ! $st['explicit'] && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$ref = (string) wp_unslash( $_SERVER['HTTP_REFERER'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( false === strpos( $ref, '/wp-admin/' ) ) {
				$l = flxl_url_language( $ref );
				if ( $l !== $def ) {
					$st['current']  = $l;
					$st['explicit'] = true;
				}
			}
		}
		return;
	}

	$base = flxl_root_path();
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	if ( '' !== $base && 0 === strpos( $path, $base . '/' ) ) {
		$rest = substr( $uri, strlen( $base ) );
	} elseif ( '' !== $base && $path === $base ) {
		return;
	} else {
		$rest = $uri;
	}
	if ( ! preg_match( '#^/([a-z]{2,3}(?:-[a-z0-9]{2,8})*)(/|\?|$)#', $rest, $m ) || ! in_array( $m[1], $codes, true ) ) {
		return;
	}
	$code     = $m[1];
	$stripped = $base . '/' . ltrim( substr( $rest, strlen( $code ) + 1 ), '/' );

	if ( $code === $def ) {
		// Varsayılan dil önek almaz: /tr/hakkimizda/ → /hakkimizda/.
		$st['redirect'] = $stripped;
		return;
	}
	$st['current']          = $code;
	$st['explicit']         = true;
	$_SERVER['REQUEST_URI'] = $stripped;
}

function flxl_uri_is_rest( $uri ) {
	$prefix = '/' . trim( rest_get_url_prefix(), '/' ) . '/';
	return false !== strpos( (string) $uri, $prefix ) || false !== strpos( (string) $uri, 'rest_route=' );
}

/**
 * /tr/... öneki: kalıcı yönlendirme.
 */
add_action(
	'parse_request',
	function () {
		if ( ! empty( $GLOBALS['flxl_state']['redirect'] ) ) {
			wp_safe_redirect( flxl_origin() . $GLOBALS['flxl_state']['redirect'], 301 );
			exit;
		}
	},
	0
);

/**
 * Mutlak adres bu sitenin mi.
 */
function flxl_is_internal( $url ) {
	$r = preg_replace( '#^https?:#', '', flxl_root() );
	$u = preg_replace( '#^https?:#', '', (string) $url );
	return 0 === strpos( $u, $r ) && ( strlen( $u ) === strlen( $r ) || in_array( $u[ strlen( $r ) ], array( '/', '?', '#' ), true ) );
}

/**
 * Bir adresin dili (önekine göre).
 */
function flxl_url_language( $url ) {
	$root = flxl_root();
	$url  = (string) $url;
	if ( '/' === substr( $url, 0, 1 ) && '//' !== substr( $url, 0, 2 ) ) {
		$path = $url;
		$base = flxl_root_path();
		if ( '' !== $base && 0 === strpos( $path, $base ) ) {
			$path = substr( $path, strlen( $base ) );
		}
	} else {
		$noscheme_root = preg_replace( '#^https?:#', '', $root );
		$noscheme_url  = preg_replace( '#^https?:#', '', $url );
		if ( 0 !== strpos( $noscheme_url, $noscheme_root ) ) {
			return flxl_default_language();
		}
		$path = substr( $noscheme_url, strlen( $noscheme_root ) );
	}
	if ( preg_match( '#^/([a-z]{2,3}(?:-[a-z0-9]{2,8})*)(/|\?|\#|$)#', $path, $m ) && flxl_is_language( $m[1] ) ) {
		return $m[1];
	}
	return flxl_default_language();
}

/**
 * Site içi adresi verilen dile çevirir: var olan dil önekini kaldırır, gerekiyorsa yenisini ekler.
 * Site dışı adres olduğu gibi döner. Kök-göreli (/hakkimizda/) adres kök-göreli kalır.
 */
function flxl_url_for_language( $url, $code ) {
	$url  = (string) $url;
	$def  = flxl_default_language();
	$root = flxl_root();
	if ( '/' === substr( $url, 0, 1 ) && '//' !== substr( $url, 0, 2 ) ) {
		$prefix = flxl_root_path();
		$path   = $url;
		if ( '' !== $prefix && 0 === strpos( $path, $prefix ) ) {
			$path = substr( $path, strlen( $prefix ) );
		}
	} else {
		$scheme_free_root = preg_replace( '#^https?:#', '', $root );
		$scheme_free_url  = preg_replace( '#^https?:#', '', $url );
		if ( 0 !== strpos( $scheme_free_url, $scheme_free_root ) ) {
			return $url;
		}
		$prefix = substr( $url, 0, strlen( $url ) - strlen( $scheme_free_url ) ) . $scheme_free_root;
		$path   = substr( $scheme_free_url, strlen( $scheme_free_root ) );
		if ( '' !== $path && ! in_array( $path[0], array( '/', '?', '#' ), true ) ) {
			return $url; // Başka alt alan/yol (ör. kök "site", adres "site2").
		}
	}
	if ( preg_match( '#^/([a-z]{2,3}(?:-[a-z0-9]{2,8})*)(?=/|\?|\#|$)#', $path, $m ) && flxl_is_language( $m[1] ) ) {
		$path = substr( $path, strlen( $m[0] ) );
	}
	if ( '' === $path || '?' === $path[0] || '#' === $path[0] ) {
		$path = '/' . $path;
	}
	if ( $code && $code !== $def && flxl_is_language( $code ) ) {
		$path = '/' . $code . $path;
	}
	return $prefix . $path;
}

/**
 * Önek alması istenmeyen yollar (wp-admin, wp-content, sitemap, dosyalar).
 */
function flxl_path_is_neutral( $path ) {
	$path = ltrim( (string) $path, '/' );
	if ( '' === $path ) {
		return false;
	}
	$rest = trim( rest_get_url_prefix(), '/' );
	if ( 0 === strpos( $path, $rest . '/' ) || $path === $rest ) {
		return false;
	}
	return (bool) preg_match( '#^(wp-|xmlrpc\.php|feed/?$|robots\.txt|favicon\.ico|site\.webmanifest|sitemap)#', $path )
		|| (bool) preg_match( '#\.(xml|txt|ico|png|jpe?g|webp|gif|svg|pdf|zip|css|js|webmanifest)$#i', (string) strtok( $path, '?#' ) );
}

/**
 * home_url: bu istekte üretilen adreslere dil öneki.
 */
add_filter(
	'home_url',
	function ( $url, $path ) {
		if ( $GLOBALS['flxl_state']['raw'] > 0 ) {
			return $url;
		}
		$cur = flxl_current_language();
		if ( $cur === flxl_default_language() || flxl_is_backend() || flxl_path_is_neutral( $path ) ) {
			return $url;
		}
		return flxl_url_for_language( $url, $cur );
	},
	10,
	2
);

/**
 * WordPress'in kanonik yönlendirmesi önekli adresi tanısın (önek çıkarıldığı için döngü olmasın).
 */
add_filter(
	'redirect_canonical',
	function ( $redirect, $requested ) {
		if ( ! $redirect || ! flxl_is_explicit() ) {
			return $redirect;
		}
		$original = flxl_origin() . $GLOBALS['flxl_state']['uri'];
		$strip    = function ( $u ) {
			return preg_replace( '#^https?://#', '', (string) $u );
		};
		return $strip( $redirect ) === $strip( $original ) ? false : $redirect;
	},
	20,
	2
);

/**
 * Yerel ayar: ön yüzde istek dilinin locale'i.
 */
function flxl_filter_locale( $locale ) {
	// İç içe çağrı (dil hesaplanırken başka bir eklenti yerel ayarı sorarsa) gelen değeri aynen döndürür.
	static $busy = false;
	if ( $busy ) {
		return $locale;
	}
	$busy = true;
	$out  = $locale;
	$cur  = flxl_current_language();
	if ( $cur !== flxl_default_language() && ! ( is_admin() && ! wp_doing_ajax() ) && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! flxl_is_explicit() ) ) {
		$out = flxl_language_locale( $cur );
	}
	$busy = false;
	return $out;
}
add_filter( 'locale', 'flxl_filter_locale', 20 );
add_filter(
	'determine_locale',
	function ( $locale ) {
		// Yönetim ekranında kullanıcının kendi dili kalır.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $locale;
		}
		if ( wp_doing_ajax() && ! flxl_is_explicit() ) {
			return $locale;
		}
		return flxl_filter_locale( $locale );
	},
	20
);

/**
 * İsteğin önekli tam adresi (dil seçici, hreflang).
 */
function flxl_current_url() {
	$uri = $GLOBALS['flxl_state']['uri'];
	if ( '' === $uri ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}
	return flxl_origin() . $uri;
}

/**
 * Sitenin şema + alan adı (+ port), yol yok.
 */
function flxl_origin() {
	$u = wp_parse_url( flxl_root() );
	return ( $u['scheme'] ?? ( is_ssl() ? 'https' : 'http' ) ) . '://' . ( $u['host'] ?? '' ) . ( ! empty( $u['port'] ) ? ':' . $u['port'] : '' );
}
