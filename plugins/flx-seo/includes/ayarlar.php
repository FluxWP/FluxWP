<?php
/**
 * SEO ayar sayfası (sol menü › SEO).
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function flxseo_defaults() {
	// Metin alanları boş: site adı, başlık ve açıklama boşsa WordPress'in site adı ve sloganı kullanılır (flxseo_opt).
	// Ön ayarlar (flx-preset-*) kendi değerlerini Flux Setup ile yazar.
	return array(
		'enabled'        => 1,
		'site_name'      => '',
		'alt_name'       => '',
		'sep'            => '–',
		'home_title'     => '',
		'home_desc'      => '',
		'org_name'       => '',
		'org_type'       => 'Organization',
		'founding'       => '',
		'logo'           => '',
		'og_image'       => '',
		'street'         => '',
		'locality'       => '',
		'postal'         => '',
		'country'        => '',
		'phone'          => '',
		'email'          => '',
		'same_as'        => '',
		'twitter'        => '',
		'knows_about'    => '',
		'area_served'    => '',
		'donate_label'   => '',
		'donate_url'     => '',
		'verify_google'  => '',
		'verify_bing'    => '',
		'verify_yandex'  => '',
		'verify_naver'   => '',
		'verify_seznam'  => '',
		'verify_baidu'   => '',
		'noindex_shop'   => 1,
		'noindex_search' => 1,
		'noindex_author' => 1,
		'noindex_tags'   => 1,
		'auto_alt'       => 1,
		'indexnow'       => 1,
		// Site denetimi: ajan uyumu ve güvenlik başlıkları.
		'llms'           => 1,
		'llms_intro'     => '',
		'webmcp'         => 1,
		'markdown'       => 1,
		// robots.txt Content-Signal (contentsignals.org): '' = satır yazılmaz.
		'content_signal' => 1,
		'cs_search'      => 'yes',
		'cs_ai_input'    => 'yes',
		'cs_ai_train'    => 'no',
		'hdr_frame'      => 0,
		'hdr_coop'       => 0,
		'hdr_csp'        => 0,
		'hsts_sub'       => 0,
		'hsts_preload'   => 0,
		// Zamanlanmış denetim (metin: onay kutusu değil).
		'audit_freq'     => 'daily',
		'audit_day'      => '1',
		'audit_hour'     => '3',
		'audit_psi'      => 1,
		'audit_mail'     => 1,
		'audit_mail_to'  => '',
	);
}

/**
 * Sürüm geçişi (bir kez çalışır).
 * 1.2.9: Flux Setup ön ayarı ayarları kısmi yazdığında eski sanitize tüm açık/kapalı ayarları 0 yapıyordu
 * (SEO çıktısı, noindex, otomatik alt metin, IndexNow birlikte kapalı). Hepsi 0 ise varsayılanlarına döner.
 */
add_action(
	'init',
	function () {
		if ( version_compare( (string) get_option( 'flxseo_db', '0' ), '1.2.9', '>=' ) ) {
			return;
		}
		$o = get_option( 'flxseo' );
		if ( is_array( $o ) ) {
			$flags = array( 'enabled', 'noindex_shop', 'noindex_search', 'noindex_author', 'noindex_tags', 'auto_alt', 'indexnow' );
			$on    = 0;
			foreach ( $flags as $k ) {
				$on += empty( $o[ $k ] ) ? 0 : 1;
			}
			if ( 0 === $on ) {
				foreach ( $flags as $k ) {
					$o[ $k ] = 1;
				}
				update_option( 'flxseo', $o );
			}
		}
		update_option( 'flxseo_db', '1.2.9' );
	},
	1
);

function flxseo_opt( $key ) {
	static $o = null;
	if ( null === $o || doing_action( 'update_option_flxseo' ) || doing_action( 'add_option_flxseo' ) ) {
		$o = flxseo_with_fallbacks( wp_parse_args( (array) get_option( 'flxseo', array() ), flxseo_defaults() ) );
	}
	if ( 'mcp_enabled' === $key ) {
		return flxseo_mcp_server_on() ? 1 : 0; // Geriye uyum: MCP sunucusu Flux AI'da.
	}
	return $o[ $key ] ?? null;
}

// Ayar yazılınca önbelleği hemen tazele (aynı istekte okuyan kod yeni değeri görsün; ön ayar, MCP, GraphQL).
add_action( 'update_option_flxseo', static fn() => flxseo_opt( 'enabled' ), 1 );
add_action( 'add_option_flxseo', static fn() => flxseo_opt( 'enabled' ), 1 );

/**
 * Boş metin alanlarına WordPress'in kendi değerleri: site adı (Ayarlar › Genel) ve slogan.
 * Böylece ayar sayfası hiç açılmasa da her sayfada başlık, açıklama ve Organization/WebSite adı dolu gelir.
 */
function flxseo_with_fallbacks( array $o ) {
	$blog = trim( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) );
	$tag  = trim( wp_strip_all_tags( (string) get_bloginfo( 'description' ) ) );
	foreach ( array( 'site_name', 'alt_name', 'org_name', 'home_title', 'home_desc' ) as $k ) {
		$o[ $k ] = trim( (string) ( $o[ $k ] ?? '' ) );
	}
	if ( '' === $o['site_name'] ) {
		$o['site_name'] = $blog;
	}
	if ( '' === $o['alt_name'] ) {
		$o['alt_name'] = $o['site_name'];
	}
	if ( '' === $o['org_name'] ) {
		$o['org_name'] = $o['site_name'];
	}
	if ( '' === $o['home_title'] ) {
		$o['home_title'] = '' !== $tag ? $o['site_name'] . ' ' . $o['sep'] . ' ' . $tag : $o['site_name'];
	}
	if ( '' === $o['home_desc'] ) {
		$o['home_desc'] = $tag;
	}
	return $o;
}

function flxseo_logo_url() {
	return flxseo_opt( 'logo' ) ? flxseo_opt( 'logo' ) : FLXSEO_URL . 'assets/icons/icon-512.png';
}

function flxseo_default_image() {
	return flxseo_opt( 'og_image' ) ? flxseo_opt( 'og_image' ) : FLXSEO_URL . 'assets/icons/og-varsayilan.jpg';
}

function flxseo_admin_url( $tab = '' ) {
	return admin_url( 'admin.php?page=flxseo' . ( $tab && 'genel' !== $tab ? '&tab=' . $tab : '' ) );
}

/**
 * Doğrulama alanı olan arama motorları: ayar anahtarı => ad, panel adresi, açıklama.
 * Meta etiket adları head.php'de (flxseo_head).
 * @return array<string, array<string, string>>
 */
function flxseo_search_engines() {
	return array(
		'verify_google' => array(
			'name'        => 'Google Search Console',
			'url'         => 'https://search.google.com/search-console',
			'link'        => __( 'Open Search Console', 'flx-seo' ),
			'placeholder' => '<meta name="google-site-verification" …>',
			'help'        => __( 'Add property › URL prefix › HTML tag. Leave empty if you verified with DNS.', 'flx-seo' ),
		),
		'verify_bing'   => array(
			'name'        => 'Bing Webmaster Tools',
			'url'         => 'https://www.bing.com/webmasters',
			'link'        => __( 'Open Bing Webmaster', 'flx-seo' ),
			'placeholder' => '<meta name="msvalidate.01" …>',
			'help'        => __( 'The easiest path is "Import from Google Search Console": verification and the sitemap arrive on their own, no code. If you add it by hand, paste the HTML meta tag. DuckDuckGo, Yahoo and Ecosia results come from Bing.', 'flx-seo' ),
		),
		'verify_yandex' => array(
			'name'        => 'Yandex Webmaster',
			'url'         => 'https://webmaster.yandex.com/sites/add/',
			'link'        => __( 'Open Yandex Webmaster', 'flx-seo' ),
			'placeholder' => '<meta name="yandex-verification" …>',
			'help'        => __( 'Add site › Meta tag. After verifying, add the sitemap URL under Indexing › Sitemap files.', 'flx-seo' ),
		),
		'verify_naver'  => array(
			'name'        => 'Naver Search Advisor',
			'url'         => 'https://searchadvisor.naver.com/',
			'link'        => __( 'Open Naver Search Advisor', 'flx-seo' ),
			'placeholder' => '<meta name="naver-site-verification" …>',
			'help'        => __( 'Korea\'s largest search engine. Add site › HTML tag; then Request › Submit sitemap. Optional.', 'flx-seo' ),
		),
		'verify_seznam' => array(
			'name'        => 'Seznam Webmaster',
			'url'         => 'https://search.seznam.cz/',
			'link'        => __( 'Open Seznam Webmaster', 'flx-seo' ),
			'placeholder' => '<meta name="seznam-wmt" …>',
			'help'        => __( 'Czech search engine (Webmaster link at the bottom of the page). Meta tag method. Optional.', 'flx-seo' ),
		),
		'verify_baidu'  => array(
			'name'        => 'Baidu Webmaster',
			'url'         => 'https://ziyuan.baidu.com/site/index',
			'link'        => __( 'Open Baidu Webmaster', 'flx-seo' ),
			'placeholder' => '<meta name="baidu-site-verification" …>',
			'help'        => __( 'China\'s search engine; sign-up may need a Chinese phone number. Not needed unless you target China.', 'flx-seo' ),
		),
	);
}

/** Makale (Article schema, og:type article) sayılan yazı türleri. Ön ayarlar kendi türlerini ekler (ör. etkinlik, proje). */
function flxseo_article_types() {
	return array_values( array_unique( (array) apply_filters( 'flxseo_article_types', array( 'post' ) ) ) );
}

/** Google Site Kit kurulu ve bir yönetici Google hesabını bağlamış mı.
 * @return bool
 */
function flxseo_sitekit_connected() {
	return defined( 'GOOGLESITEKIT_VERSION' ) && (bool) get_option( 'googlesitekit_has_connected_admins', false );
}

/** Son IndexNow toplu bildiriminin kısa metni.
 * @return string
 */
function flxseo_indexnow_last_text( $last ) {
	if ( empty( $last['time'] ) ) {
		return '';
	}
	$code = (int) ( $last['code'] ?? 0 );
	/* translators: 1: tarih, 2: adres sayısı, 3: HTTP kodu */
	$t = sprintf( __( '%1$s · %2$d URLs · HTTP %3$d', 'flx-seo' ), flx_date( (int) $last['time'], 'datetime' ), (int) ( $last['count'] ?? 0 ), $code );
	if ( $code >= 400 ) {
		$t .= ' · ' . ( 403 === $code ? __( 'key file could not be verified', 'flx-seo' ) : ( 422 === $code ? __( 'URLs do not match the site domain', 'flx-seo' ) : ( 429 === $code ? __( 'too many requests, try again later', 'flx-seo' ) : __( 'error', 'flx-seo' ) ) ) );
	}
	return $t;
}

function flxseo_tabs() {
	return array(
		'genel'   => __( 'General', 'flx-seo' ),
		'kurum'   => __( 'Organization and social', 'flx-seo' ),
		'kontrol' => __( 'SEO check', 'flx-seo' ),
		'denetim' => __( 'Site audit', 'flx-seo' ),
		'ayarlar' => __( 'Settings', 'flx-seo' ),
	);
}

/**
 * Sol menüde ayrı "SEO" öğesi; sekmeler alt menü olarak da listelenir.
 */
add_action(
	'admin_menu',
	function () {
		// Renkli FluxWP logosu (assets/icon.svg) CSS ile verilir; WordPress menü ikonunu tek renge boyar.
		add_menu_page( 'SEO', 'SEO', 'manage_options', 'flxseo', 'flxseo_settings_page', 'none', 81 );
		foreach ( flxseo_tabs() as $k => $label ) {
			if ( 'denetim' === $k ) {
				$label .= flxseo_audit_menu_badge();
			}
			add_submenu_page( 'flxseo', 'SEO – ' . $label, $label, 'manage_options', 'genel' === $k ? 'flxseo' : 'flxseo&tab=' . $k, 'genel' === $k ? 'flxseo_settings_page' : '__return_null' );
		}
	}
);

add_action(
	'admin_head',
	function () {
		echo '<style>#toplevel_page_flxseo .wp-menu-image{background:url(' . esc_url( FLXSEO_URL . 'assets/icon.svg?v=' . FLXSEO_VERSION ) . ') center/20px 20px no-repeat}#toplevel_page_flxseo .wp-menu-image:before{content:none}</style>';
	}
);

// Alt menü linkleri admin.php?page=flxseo&tab=… adresine gider; aktif sekme menüde işaretlenir.
add_filter(
	'submenu_file',
	function ( $file, $parent ) {
		if ( 'flxseo' === $parent && ! empty( $_GET['tab'] ) && 'genel' !== $_GET['tab'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return 'flxseo&tab=' . sanitize_key( $_GET['tab'] ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		return $file;
	},
	10,
	2
);

// Eski adresler: Ayarlar › SEO yeni menüye, eski Yapay zekâ (MCP) sekmesi Flux AI & MCP › MCP'ye yönlenir.
add_action(
	'admin_init',
	function () {
		global $pagenow;
		if ( 'admin.php' === $pagenow && isset( $_GET['page'], $_GET['tab'] ) && 'flxseo' === $_GET['page'] && 'mcp' === $_GET['tab'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( flxseo_mcp_admin_url() );
			exit;
		}
		if ( 'options-general.php' === $pagenow && isset( $_GET['page'] ) && 'flxseo' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( flxseo_admin_url( isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
			exit;
		}
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( FLXSEO_FILE ),
	function ( $links ) {
		array_unshift( $links, '<a href="' . esc_url( flxseo_admin_url() ) . '">Ayarlar</a>' );
		return $links;
	}
);

// Every write of the option (GraphQL flxSettingsUpdate, presets, MCP) goes through the same validation.
add_filter( 'sanitize_option_flxseo', 'flxseo_sanitize' );

function flxseo_sanitize( $in ) {
	$in  = (array) $in;
	$d   = flxseo_defaults();
	// Gelen dizide olmayan anahtar eski değerini korur (Flux Setup ön ayarı, MCP ve kod yalnız birkaç alan yazar).
	$in  = array_merge( wp_parse_args( (array) get_option( 'flxseo', array() ), $d ), $in );
	$out = array();
	foreach ( $d as $k => $v ) {
		if ( is_int( $v ) ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		} elseif ( in_array( $k, array( 'logo', 'og_image', 'donate_url' ), true ) ) {
			$out[ $k ] = esc_url_raw( $in[ $k ] ?? '' );
		} elseif ( in_array( $k, array( 'home_desc', 'same_as', 'llms_intro', 'knows_about' ), true ) ) {
			$out[ $k ] = sanitize_textarea_field( $in[ $k ] ?? '' );
		} elseif ( 0 === strpos( $k, 'verify_' ) ) {
			// Doğrulama: tam <meta> etiketi yapıştırılırsa yalnızca content değeri alınır.
			$val = (string) ( $in[ $k ] ?? '' );
			if ( preg_match( '/content=["\']([^"\']+)["\']/', $val, $m ) ) {
				$val = $m[1];
			}
			$out[ $k ] = sanitize_text_field( $val );
		} else {
			$out[ $k ] = sanitize_text_field( $in[ $k ] ?? '' );
		}
	}
	$out['audit_freq']    = in_array( $out['audit_freq'], array( 'daily', 'weekly', 'off' ), true ) ? $out['audit_freq'] : 'daily';
	$out['audit_day']     = (string) max( 1, min( 7, (int) $out['audit_day'] ) );
	$out['audit_hour']    = (string) max( 0, min( 23, (int) $out['audit_hour'] ) );
	$out['audit_mail_to'] = sanitize_email( $out['audit_mail_to'] );
	foreach ( array( 'cs_search', 'cs_ai_input', 'cs_ai_train' ) as $k ) {
		$out[ $k ] = in_array( $out[ $k ], array( 'yes', 'no', '' ), true ) ? $out[ $k ] : $d[ $k ];
	}
	$out['country'] = preg_match( '/^[A-Za-z]{2}$/', $out['country'] ) ? strtoupper( $out['country'] ) : '';
	return $out;
}

function flxseo_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	flx_admin_screen_page();
}
