<?php
/**
 * Görünmez mod: site dışarıdan görünmez. Giriş yapmamış ziyaretçi için
 *  - robots.txt "Disallow: /", her ön yüz/REST yanıtında X-Robots-Tag ve <meta name="robots"> noindex,
 *  - bütün site haritaları (WP, Yoast, Rank Math, WooCommerce tarzı *sitemap*.xml) 404,
 *  - REST API yalnız izin verilen ad alanlarında (varsayılan flx-qr-menu/v1: canlı yenileme + menü sitesi beslemesi),
 *    /wp-json/ dizini, ad alanı dizinleri, JSONP ve keşif bağlantıları gizli; ?rest_route= aynı kurala tabi,
 *  - admin-ajax.php ve admin-post.php (her eylem), xmlrpc.php, wp-trackback.php, wp-links-opml.php, wp-comments-post.php,
 *    wp-mail.php, wp-signup.php, wp-activate.php, maint/repair.php 404 (izinli yollar hariç),
 *  - beslemeler, /embed/, geri izleme, arama, yazar/tarih arşivleri, ek sayfaları, önizleme ve bulunamayan her adres
 *    tema şablonu olmadan sade 404; ana sayfa sorgu parametresiyle (?p=, ?page_id=, ?post_type=…) açılmaz,
 *  - WordPress/WooCommerce sürümü görünmez: generator etiketi yok, ?ver= değerleri özetlenir, emoji/oEmbed/besleme
 *    bağlantıları, X-Pingback, X-Powered-By, X-Redirect-By ve REST "Link" başlığı yok,
 *  - wp-login: kayıt kapalı, giriş ve parola sıfırlama hataları kullanıcının var olup olmadığını söylemez,
 *  - WooCommerce (kuruluysa): mağaza/ürün/sepet/ödeme/hesap sayfaları, Store API, wc/v1-3, ?wc-ajax=, wc-api,
 *    ?add-to-cart=, ?download_file= 404, ana sayfada WooCommerce betik/stilleri yüklenmez
 *    (ödeme geri dönüşleri "Açık kalacak yollar" listesiyle açılır),
 *  - "Yalnız menüler açık": ana sayfa, QR menüler (Flux QR Menu bunları WordPress sorgusundan önce sunar),
 *    wp-login ve izinli yollar dışında her ön yüz adresi 404.
 * Giriş yapmış her kullanıcı (yönetici, menü yöneticisi; uygulama parolası ya da MCP anahtarıyla gelen istek de)
 * ve WP-CLI siteyi normal görür. Kaydedince .htaccess'e "Yesil Fun" bloğu (dizin listesi kapalı, readme/changelog/
 * .md/.log dosyalarına ve kurulum betiklerine erişim yok) ve Ayarlar › Okuma "arama motorlarını engelle" uygulanır;
 * kapatınca ikisi de geri alınır.
 *
 * @package flx-menu-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const YF_ROBOTS_HEADER = 'noindex, nofollow, noarchive, nosnippet';

/** Görünmez mod açık mı (ayar). */
function yf_gizli() {
	return (bool) yf_ayar( 'hidden' );
}

/** Bu istek gizlenecek bir ziyaretçiden mi (görünmez mod açık ve giriş yok)? */
function yf_gizle() {
	return yf_gizli() && ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! is_user_logged_in();
}

/**
 * Metin alanındaki satırları listeye çevirir.
 *
 * @return string[]
 */
function yf_lines( $text ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $text ) ) ) );
}

/** @return string[] İzin verilen REST önekleri (baş/son / olmadan, küçük harf). */
function yf_rest_allow() {
	return array_map(
		function ( $p ) {
			return strtolower( trim( $p, '/' ) );
		},
		yf_lines( yf_ayar_text( 'rest_allow' ) )
	);
}

/** REST yolu (/ns/v1/x) izinli bir önekle mi başlıyor? "wc/store" → /wc/store ve altı; sonu * ise düz önek ("wc/v*"). */
function yf_rest_allowed( $route ) {
	$route = strtolower( trim( (string) $route, '/' ) );
	if ( '' === $route ) {
		return false;
	}
	foreach ( yf_rest_allow() as $p ) {
		if ( '' === $p ) {
			continue;
		}
		if ( '*' === substr( $p, -1 ) ? 0 === strpos( $route, rtrim( $p, '*' ) ) : ( $route === $p || 0 === strpos( $route, $p . '/' ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * İstek yolu sitenin alt klasörü çıkarılmış ve başında / ile: [yol, sorgu].
 *
 * @return array{0:string,1:string}
 */
function yf_request_path() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$path = rawurldecode( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
	$qs   = (string) wp_parse_url( $uri, PHP_URL_QUERY );
	$home = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	if ( '' !== $home && 0 === stripos( $path, $home . '/' ) ) {
		$path = substr( $path, strlen( $home ) );
	}
	return array( '/' . ltrim( $path, '/' ), $qs );
}

/**
 * "Açık kalacak yollar" bu isteği kapsıyor mu? Karşılaştırılanlar: yol, yol?sorgu, ?wc-api=X için /wc-api/X,
 * ?wc-ajax=X için /wc-ajax/X ve admin-ajax.php / admin-post.php isteğinde (GET ya da POST) yol?action=X
 * (büyük/küçük harf duyarsız önek; ayarda "admin-ajax:X" kısaltması /wp-admin/admin-ajax.php?action=X olur).
 */
function yf_path_allowed() {
	static $memo = null;
	if ( null !== $memo ) {
		return $memo;
	}
	$allow = yf_lines( yf_ayar_text( 'path_allow' ) );
	if ( ! $allow ) {
		return $memo = false; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
	}
	list( $path, $qs ) = yf_request_path();
	$cands             = array( $path );
	if ( '' !== $qs ) {
		$cands[] = $path . '?' . $qs;
	}
	// phpcs:disable WordPress.Security.NonceVerification
	foreach ( array( 'wc-api', 'wc-ajax' ) as $k ) {
		if ( isset( $_GET[ $k ] ) && is_scalar( $_GET[ $k ] ) ) {
			$cands[] = '/' . $k . '/' . sanitize_text_field( wp_unslash( $_GET[ $k ] ) );
		}
	}
	if ( isset( $_REQUEST['action'] ) && is_scalar( $_REQUEST['action'] ) && in_array( yf_script(), array( 'admin-ajax.php', 'admin-post.php' ), true ) ) {
		$cands[] = $path . '?action=' . sanitize_text_field( wp_unslash( $_REQUEST['action'] ) );
	}
	// phpcs:enable
	foreach ( $allow as $a ) {
		$a = '/' . ltrim( $a, '/' );
		foreach ( $cands as $c ) {
			if ( 0 === strncasecmp( $c, $a, strlen( $a ) ) ) {
				return $memo = true; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
			}
		}
	}
	return $memo = false; // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments
}

/** Çalışan PHP dosyasının adı (index.php, admin-ajax.php, xmlrpc.php…). */
function yf_script() {
	return basename( isset( $_SERVER['SCRIPT_FILENAME'] ) ? (string) $_SERVER['SCRIPT_FILENAME'] : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
}

/** Sade 404 gönderip isteği bitirir (site haritası, wc-api gibi tema şablonuna gelmeden yakalanan istekler). */
function yf_gizli_404() {
	do_action( 'litespeed_control_set_nocache', 'flx-menu-theme hidden' );
	if ( ! headers_sent() ) {
		status_header( 404 );
		nocache_headers();
		header_remove( 'X-Powered-By' );
		header_remove( 'Link' );
		header_remove( 'X-Pingback' );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: ' . YF_ROBOTS_HEADER );
	}
	echo '<!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex, nofollow"><title>404</title></head><body><h1>404</h1></body></html>';
	exit;
}

/** Site haritası isteği mi (WP çekirdeği, Yoast, Rank Math, AIOSEO, WooCommerce eklentileri…)? */
function yf_is_sitemap_request() {
	list( $path ) = yf_request_path();
	if ( preg_match( '#sitemap[^/]*\.(xml|xsl|txt)(\.gz)?$#i', $path ) || preg_match( '#^/sitemaps?(/|$)#i', $path ) ) {
		return true;
	}
	foreach ( array( 'sitemap', 'sitemap-subtype', 'sitemap-stylesheet', 'sitemap_n', 'xsl' ) as $k ) {
		if ( isset( $_GET[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return true;
		}
	}
	return false;
}

/** WooCommerce etkin mi (yüklü değilse hiçbir WC kontrolü çalışmaz). */
function yf_wc() {
	return class_exists( 'WooCommerce' ) || function_exists( 'WC' );
}

/* ---------- En erken aşama (init): WordPress'in doğrudan dosyaları, admin-ajax/admin-post, WooCommerce işleyicileri ---------- */

/** Giriş yapmamış ziyaretçiye hiç açılmayan WordPress dosyaları ("Açık kalacak yollar"a /xmlrpc.php gibi yazılarak açılır). */
function yf_gizli_dosyalar() {
	return array( 'xmlrpc.php', 'wp-trackback.php', 'wp-links-opml.php', 'wp-comments-post.php', 'wp-mail.php', 'wp-signup.php', 'wp-activate.php', 'repair.php', 'admin-post.php' );
}

/* Eklentilerin ve WooCommerce'in init işleyicilerinden (indirme, sepet…) ve XML-RPC kapatmasından (kapat.php) önce. */
add_action( 'init', 'yf_gizli_init', -100 );

function yf_gizli_init() {
	if ( ! yf_gizli() ) {
		return;
	}
	if ( ! headers_sent() ) {
		header_remove( 'X-Powered-By' );
	}
	if ( ! yf_gizle() || yf_path_allowed() ) {
		return;
	}
	// admin-ajax.php: wp_ajax_nopriv_* eylemlerinin hiçbiri (heartbeat, WooCommerce, diğer eklentiler) çalışmaz.
	if ( wp_doing_ajax() && 'admin-ajax.php' === yf_script() ) {
		wp_die( '0', '', array( 'response' => 404 ) );
	}
	if ( in_array( yf_script(), yf_gizli_dosyalar(), true ) ) {
		yf_gizli_404();
	}
	// phpcs:disable WordPress.Security.NonceVerification
	foreach ( array( 'author', 'author_name', 'wc-api', 'wc-ajax', 'add-to-cart', 'download_file' ) as $k ) {
		if ( isset( $_GET[ $k ] ) ) {
			yf_gizli_404();
		}
	}
	// phpcs:enable
	list( $path ) = yf_request_path();
	if ( preg_match( '#^/(wc-api|wc-auth)(/|$)#i', $path ) ) {
		yf_gizli_404();
	}
}

/* ---------- Ön yüz isteği: ayrıştırmadan önce (Flux QR Menu'den önce, öncelik 0) ---------- */

add_filter( 'do_parse_request', 'yf_gizli_parse', 0, 1 );

function yf_gizli_parse( $do ) {
	if ( ! yf_gizli() || is_admin() ) {
		return $do;
	}
	// Bütün ön yüz ve REST yanıtları (QR menü sayfaları dahil, onlar da burada başlığı alır) dizine eklenmesin.
	if ( ! headers_sent() ) {
		header( 'X-Robots-Tag: ' . YF_ROBOTS_HEADER );
	}
	if ( yf_is_sitemap_request() ) {
		yf_gizli_404();
	}
	return $do;
}

/* ---------- Şablon aşaması: kapalı her adres tema şablonu olmadan sade 404 ---------- */

add_action( 'template_redirect', 'yf_gizli_template', 0 );

/** Ana sayfa sorgu parametresi olmadan mı istendi (?p=, ?page_id=, ?post_type=, ?embed… yok; utm_* gibi yabancılar sayılmaz)? */
function yf_sade_ana_sayfa() {
	global $wp;
	if ( ! is_front_page() || is_paged() ) {
		return false;
	}
	$qv = array_diff_key( (array) $wp->query_vars, array_flip( (array) apply_filters( 'yf_gizli_home_query_vars', array( 'lang' ) ) ) );
	return ! $qv;
}

/** WooCommerce sayfası mı (mağaza, ürün, kategori/etiket, sepet, ödeme, hesap)? */
function yf_wc_sayfa() {
	if ( ! yf_wc() ) {
		return false;
	}
	return ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) || ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) || ( function_exists( 'is_account_page' ) && is_account_page() ) || is_post_type_archive( 'product' ) || is_singular( 'product' ) || is_tax( array( 'product_cat', 'product_tag' ) );
}

function yf_gizli_template() {
	if ( ! yf_gizle() || is_robots() || is_favicon() || yf_path_allowed() ) {
		return;
	}
	if ( is_404() || is_feed() || is_comment_feed() || is_embed() || is_trackback() || is_search() || is_author() || is_date() || is_attachment() || is_preview() || yf_wc_sayfa() ) {
		yf_gizli_404();
	}
	if ( yf_ayar( 'menus_only' ) && ! yf_sade_ana_sayfa() ) {
		yf_gizli_404();
	}
}

/* WooCommerce form işleyicileri (sepete ekle, giriş, kayıt, ödeme…) giriş yapmamış ziyaretçide çalışmaz. */
add_action(
	'wp_loaded',
	function () {
		if ( ! yf_wc() || ! yf_gizle() || is_admin() || yf_path_allowed() ) {
			return;
		}
		foreach ( array( 'wp_loaded', 'template_redirect' ) as $hook ) {
			if ( empty( $GLOBALS['wp_filter'][ $hook ] ) ) {
				continue;
			}
			foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks as $prio => $cbs ) {
				foreach ( $cbs as $cb ) {
					if ( is_array( $cb['function'] ) && is_string( $cb['function'][0] ) && 'WC_Form_Handler' === $cb['function'][0] ) {
						remove_action( $hook, $cb['function'], $prio );
					}
				}
			}
		}
	},
	1
);

/* ---------- wp-login: kayıt kapalı, hatalar kullanıcının varlığını ele vermez ---------- */

add_filter(
	'pre_option_users_can_register',
	function ( $v ) {
		return yf_gizle() ? '0' : $v;
	}
);

/* "Kullanıcı adı kayıtlı değil" / "bu kullanıcının parolası yanlış" yerine tek ileti (çekirdek 20, spam denetimi 99'da). */
add_filter(
	'authenticate',
	function ( $user ) {
		if ( yf_gizli() && is_wp_error( $user ) && array_intersect( $user->get_error_codes(), array( 'invalid_username', 'invalid_email', 'incorrect_password' ) ) ) {
			return new WP_Error( 'yf_login_failed', '<strong>' . esc_html__( 'Hata:', 'flx-menu-theme' ) . '</strong> ' . esc_html__( 'Kullanıcı adı, e-posta ya da parola hatalı.', 'flx-menu-theme' ) );
		}
		return $user;
	},
	100
);
add_filter(
	'shake_error_codes',
	function ( $codes ) {
		$codes[] = 'yf_login_failed';
		return $codes;
	}
);

/* Parolamı unuttum: kullanıcı olsa da olmasa da (ya da e-posta gidemese de) aynı "e-postanızı kontrol edin" ekranı. */
add_action(
	'login_form_lostpassword',
	function () {
		// phpcs:disable WordPress.Security.NonceVerification -- çekirdek formu da nonce kullanmaz.
		if ( ! yf_gizli() || ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['user_login'] ) || ! is_string( $_POST['user_login'] ) ) {
			return;
		}
		$login = trim( sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) );
		if ( '' === $login ) {
			return; // Boş alan: çekirdeğin "kullanıcı adı ya da e-posta girin" iletisi.
		}
		retrieve_password( $login );
		$to = ! empty( $_REQUEST['redirect_to'] ) && is_string( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : 'wp-login.php?checkemail=confirm'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		// phpcs:enable
		wp_safe_redirect( $to );
		exit;
	}
);

/* ---------- robots.txt, meta robots, başlık etiketleri ---------- */

add_filter(
	'robots_txt',
	function ( $out ) {
		return yf_gizli() ? "User-agent: *\nDisallow: /\n" : $out;
	},
	PHP_INT_MAX
);

add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( ! yf_gizli() ) {
			return $robots;
		}
		unset( $robots['max-image-preview'], $robots['index'], $robots['follow'] );
		return array_merge(
			$robots,
			array(
				'noindex'   => true,
				'nofollow'  => true,
				'noarchive' => true,
			)
		);
	},
	PHP_INT_MAX
);

add_filter(
	'wp_headers',
	function ( $h ) {
		if ( yf_gizli() ) {
			$h['X-Robots-Tag'] = YF_ROBOTS_HEADER;
			unset( $h['X-Pingback'] );
		}
		return $h;
	},
	PHP_INT_MAX
);
add_filter(
	'x_redirect_by',
	function ( $by ) {
		return yf_gizli() ? false : $by;
	},
	PHP_INT_MAX
);

/* Site haritaları kapalı (çekirdek). 1.1.0'daki sayfa + menü haritası yalnız görünmez mod kapalıyken. */
add_filter(
	'wp_sitemaps_enabled',
	function ( $on ) {
		return yf_gizli() ? false : $on;
	},
	PHP_INT_MAX
);

/*
 * REST keşfi, kısa bağlantı, RSD/WLW, besleme ve oEmbed bağlantıları, emoji betiği (içinde sürümlü adres var) ve
 * sürüm etiketleri sayfa başlığından ve HTTP başlıklarından kalkar (Tema Ayarları'ndaki anahtarlardan bağımsız).
 */
add_action(
	'wp',
	function () {
		if ( ! yf_gizli() ) {
			return;
		}
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_filter( 'get_the_generator_html', 'wc_generator_tag', 10 );
		remove_filter( 'get_the_generator_xhtml', 'wc_generator_tag', 10 );
	},
	0
);
add_filter(
	'the_generator',
	function ( $g ) {
		return yf_gizli() ? '' : $g;
	},
	PHP_INT_MAX
);

/*
 * Giriş yapmamış ziyaretçiye giden betik/stil adreslerinde ?ver= sürüm yerine site anahtarıyla özet (önbellek kırma
 * çalışır, WordPress/eklenti sürümü okunmaz). wp-login sayfası da dahil.
 */
function yf_gizli_ver( $src ) {
	if ( ! yf_gizle() || false === strpos( (string) $src, 'ver=' ) ) {
		return $src;
	}
	$q = array();
	wp_parse_str( (string) wp_parse_url( $src, PHP_URL_QUERY ), $q );
	if ( ! isset( $q['ver'] ) || ! is_string( $q['ver'] ) ) {
		return $src;
	}
	return add_query_arg( 'ver', substr( wp_hash( 'yf-ver|' . $q['ver'] ), 0, 8 ), $src );
}
add_filter( 'script_loader_src', 'yf_gizli_ver', PHP_INT_MAX );
add_filter( 'style_loader_src', 'yf_gizli_ver', PHP_INT_MAX );

/*
 * Ana sayfada (ve "Yalnız menüler açık" kapalıyken açık sayfalarda) WooCommerce izi yok: betik/stiller (sepet parçaları,
 * wc-ajax adresi, sipariş kaynağı izleme), "woocommerce-no-js" betiği ve gövde sınıfları. Bloklar stillerini sonradan
 * eklediği için alt bilgiden önce bir kez daha temizlenir.
 */
function yf_gizli_wc_izleri() {
	if ( ! yf_wc() || ! yf_gizle() || yf_path_allowed() ) {
		return;
	}
	foreach ( array( wp_scripts(), wp_styles() ) as $deps ) {
		foreach ( (array) $deps->queue as $h ) {
			$src = isset( $deps->registered[ $h ] ) ? (string) $deps->registered[ $h ]->src : '';
			if ( false !== strpos( $src, '/plugins/woocommerce/' ) || 0 === strpos( $h, 'woocommerce' ) || 0 === strpos( $h, 'wc-' ) ) {
				$deps->dequeue( $h );
			}
		}
	}
	remove_action( 'wp_footer', 'wc_no_js' );
	remove_action( 'wp_head', 'wc_gallery_noscript' );
}
add_action( 'wp_enqueue_scripts', 'yf_gizli_wc_izleri', PHP_INT_MAX );
add_action( 'wp_footer', 'yf_gizli_wc_izleri', 1 );
/* WooCommerce'in ("Mağazanı başlat" özelliği) temaya eklediği Inter/Cardo yazı tipleri: adresleri eklenti klasörünü gösterir. */
add_filter(
	'wp_theme_json_data_theme',
	function ( $json ) {
		if ( is_admin() || ! yf_wc() || ! yf_gizle() || yf_path_allowed() ) {
			return $json;
		}
		$data  = $json->get_data();
		$fonts = isset( $data['settings']['typography']['fontFamilies']['theme'] ) ? (array) $data['settings']['typography']['fontFamilies']['theme'] : array();
		$keep  = array_values(
			array_filter(
				$fonts,
				function ( $f ) {
					return false === strpos( (string) wp_json_encode( isset( $f['fontFace'] ) ? $f['fontFace'] : array() ), 'plugins\/woocommerce\/' );
				}
			)
		);
		if ( count( $keep ) === count( $fonts ) ) {
			return $json;
		}
		return $json->update_with(
			array(
				'version'  => $data['version'],
				'settings' => array( 'typography' => array( 'fontFamilies' => array( 'theme' => $keep ) ) ),
			)
		);
	},
	PHP_INT_MAX
);
add_filter(
	'body_class',
	function ( $c ) {
		return yf_wc() && yf_gizle() && ! yf_path_allowed() ? array_values( preg_grep( '/^woocommerce/', $c, PREG_GREP_INVERT ) ) : $c;
	},
	PHP_INT_MAX
);

/* ---------- REST API ---------- */

/*
 * Kimlik doğrulamasından sonra (çerez+nonce, uygulama parolası, Flux MCP anahtarı) çalışır: giriş yoksa yalnız
 * izinli ad alanları. Kapalı adres "yok" gibi görünür (404 rest_no_route); /wp-json/ dizini de 404.
 */
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		if ( null !== $result || ! yf_gizle() ) {
			return $result;
		}
		// İzinli ad alanının kendi dizini (/wp-json/flx-qr-menu/v1) bütün uçları listeler; o da kapalı.
		$route = trim( (string) $request->get_route(), '/' );
		if ( yf_rest_allowed( $route ) && ! in_array( strtolower( $route ), array_map( 'strtolower', $server->get_namespaces() ), true ) ) {
			return $result;
		}
		return new WP_Error( 'rest_no_route', __( 'No route was found matching the URL and request method.' ), array( 'status' => 404 ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- çekirdeğin kendi iletisi.
	},
	0,
	3
);

/*
 * REST sunucusu kendi "X-Robots-Tag: noindex" başlığını yazar; tam değerle değiştir. Giriş yapmamış istekte
 * "Link: <…/wp-json/>; rel=api.w.org" keşif başlığı da kalkar.
 */
add_filter(
	'rest_post_dispatch',
	function ( $res ) {
		if ( yf_gizli() && $res instanceof WP_HTTP_Response ) {
			$res->header( 'X-Robots-Tag', YF_ROBOTS_HEADER );
		}
		if ( yf_gizle() && ! headers_sent() ) {
			header_remove( 'Link' );
			header_remove( 'X-Powered-By' );
		}
		return $res;
	},
	99
);

/* JSONP (?_jsonp=) giriş yapmamış istekte kapalı: açık uçların yanıtı da başka sitenin sayfasına gömülemez. */
add_filter(
	'rest_jsonp_enabled',
	function ( $on ) {
		return yf_gizle() ? false : $on;
	}
);

/* ---------- .htaccess ve Okuma ayarı (kaydedince / temaya geçince / modu kapatınca) ---------- */

const YF_HTACCESS_MARKER = 'Yesil Fun';

/** @return string[] .htaccess bloğunun satırları. */
function yf_htaccess_lines() {
	return array(
		'# Görünmez mod (Flux Menu Theme teması): dizin listesi kapalı, sürümü ele veren dosyalara ve kurulum betiklerine erişim yok.',
		'Options -Indexes',
		'<FilesMatch "(?i)(^(readme|changelog|changes|history|license|licence|upgrade)([._-][a-z0-9_-]*)?\.(html?|txt|md)$|^(license|licence|copying)$|\.(md|log)$|^wp-config-sample\.php$|^composer\.(json|lock)$)">',
		'	<IfModule mod_authz_core.c>',
		'		Require all denied',
		'	</IfModule>',
		'	<IfModule !mod_authz_core.c>',
		'		Order allow,deny',
		'		Deny from all',
		'	</IfModule>',
		'</FilesMatch>',
		'<IfModule mod_rewrite.c>',
		'	RewriteEngine On',
		'	RewriteRule (^|/)wp-admin/(install|setup-config)\.php$ - [F,L]',
		'	RewriteCond %{HTTP_COOKIE} !wordpress_logged_in_',
		'	RewriteRule (^|/)wp-admin/(upgrade\.php|maint/) - [F,L]',
		'</IfModule>',
		'<IfModule mod_headers.c>',
		'	Header unset X-Powered-By',
		'	Header always unset X-Powered-By',
		'</IfModule>',
	);
}

function yf_htaccess_file() {
	if ( ! function_exists( 'get_home_path' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	return get_home_path() . '.htaccess';
}

/** Sunucu .htaccess okuyor mu (Apache/LiteSpeed)? Test ortamı için filtrelenebilir. */
function yf_htaccess_supported() {
	global $is_apache;
	return (bool) apply_filters( 'yf_htaccess_supported', ! empty( $is_apache ) );
}

/**
 * Bloğu ekler (true) ya da kaldırır (false).
 *
 * @return string ok | off | unwritable | unsupported
 */
function yf_htaccess_apply( $on ) {
	$file = yf_htaccess_file();
	$has  = is_readable( $file ) && false !== strpos( (string) file_get_contents( $file ), '# BEGIN ' . YF_HTACCESS_MARKER ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $on ) {
		if ( ! $has ) {
			return 'off';
		}
		if ( ! wp_is_writable( $file ) ) {
			return 'unwritable';
		}
		$c = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$n = preg_replace( '/\n*# BEGIN ' . preg_quote( YF_HTACCESS_MARKER, '/' ) . '\R.*?# END ' . preg_quote( YF_HTACCESS_MARKER, '/' ) . '\R?/s', "\n", $c );
		file_put_contents( $file, ltrim( (string) $n, "\n" ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return 'off';
	}
	if ( ! yf_htaccess_supported() ) {
		return 'unsupported';
	}
	if ( ( file_exists( $file ) && ! wp_is_writable( $file ) ) || ( ! file_exists( $file ) && ! wp_is_writable( dirname( $file ) ) ) ) {
		return 'unwritable';
	}
	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	return insert_with_markers( $file, YF_HTACCESS_MARKER, yf_htaccess_lines() ) ? 'ok' : 'unwritable';
}

/**
 * Ayardaki duruma göre .htaccess bloğunu ve "arama motorlarını engelle" (blog_public=0) seçeneğini uygular.
 * Açarken önceki blog_public saklanır, kapatınca geri yüklenir. Tekrar çalıştırmak güvenli.
 *
 * @param bool|null $on Zorla açık/kapalı (null: ayara göre).
 */
function yf_gorunmez_sync( $on = null ) {
	$on    = null === $on ? yf_gizli() : (bool) $on;
	$state = array(
		'on'       => $on ? 1 : 0,
		'htaccess' => yf_htaccess_apply( $on ),
		'time'     => time(),
		'ver'      => YF_VERSION,
	);
	$prev = get_option( 'yf_gorunmez_blog_public', null );
	if ( $on ) {
		if ( null === $prev ) {
			update_option( 'yf_gorunmez_blog_public', (string) get_option( 'blog_public', '1' ), false );
		}
		if ( '0' !== (string) get_option( 'blog_public' ) ) {
			update_option( 'blog_public', '0' );
		}
	} elseif ( null !== $prev ) {
		update_option( 'blog_public', (string) $prev );
		delete_option( 'yf_gorunmez_blog_public' );
	}
	update_option( 'yf_gorunmez_state', $state, false );
	yf_purge_cache();
	return $state;
}

/* Ayar kaydedilince uygula. */
add_action(
	'update_option_yf_ayarlar',
	function () {
		yf_gorunmez_sync();
	}
);
add_action(
	'add_option_yf_ayarlar',
	function () {
		yf_gorunmez_sync();
	}
);

/* Tema güncellenince / ilk açılışta (ayar varsayılanı açık) bir kez uygula; tema değişince geri al. */
add_action(
	'admin_init',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$st = (array) get_option( 'yf_gorunmez_state', array() );
		// Tema güncellenince (.htaccess bloğu değişmiş olabilir) de yeniden uygula.
		if ( ! isset( $st['on'] ) || (int) $st['on'] !== (int) yf_gizli() || ( yf_gizli() && ( ! isset( $st['ver'] ) || YF_VERSION !== $st['ver'] ) ) ) {
			yf_gorunmez_sync();
		}
	}
);
add_action(
	'switch_theme',
	function () {
		yf_gorunmez_sync( false );
		delete_option( 'yf_gorunmez_state' );
	}
);

/** @return string[] Bu sitede kayıtlı REST ad alanları (Tema Ayarları'nda yardım olarak). */
function yf_rest_namespaces() {
	if ( ! function_exists( 'rest_get_server' ) ) {
		return array();
	}
	$ns = rest_get_server()->get_namespaces();
	sort( $ns );
	return $ns;
}
