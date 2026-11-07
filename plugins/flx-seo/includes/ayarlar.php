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
	if ( null === $o || doing_action( 'update_option_flxseo' ) ) {
		$o = flxseo_with_fallbacks( wp_parse_args( (array) get_option( 'flxseo', array() ), flxseo_defaults() ) );
	}
	if ( 'mcp_enabled' === $key ) {
		return flxseo_mcp_server_on() ? 1 : 0; // Geriye uyum: MCP sunucusu Flux AI'da.
	}
	return $o[ $key ] ?? null;
}

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
 */
function flxseo_search_engines() {
	return array(
		'verify_google' => array(
			'name'        => 'Google Search Console',
			'url'         => 'https://search.google.com/search-console',
			'link'        => __( 'Search Console\'u aç', 'flx-seo' ),
			'placeholder' => '<meta name="google-site-verification" …>',
			'help'        => __( 'Özellik ekle › URL öneki › HTML etiketi. DNS ile doğruladıysanız boş bırakın.', 'flx-seo' ),
		),
		'verify_bing'   => array(
			'name'        => 'Bing Webmaster Tools',
			'url'         => 'https://www.bing.com/webmasters',
			'link'        => __( 'Bing Webmaster\'ı aç', 'flx-seo' ),
			'placeholder' => '<meta name="msvalidate.01" …>',
			'help'        => __( 'En kolayı "Google Search Console\'dan içe aktar": doğrulama ve sitemap kendiliğinden gelir, kod gerekmez. Elle eklerken HTML Meta Tag kodunu yapıştırın. DuckDuckGo, Yahoo ve Ecosia sonuçları Bing\'den gelir.', 'flx-seo' ),
		),
		'verify_yandex' => array(
			'name'        => 'Yandex Webmaster',
			'url'         => 'https://webmaster.yandex.com/sites/add/',
			'link'        => __( 'Yandex Webmaster\'ı aç', 'flx-seo' ),
			'placeholder' => '<meta name="yandex-verification" …>',
			'help'        => __( 'Site ekle › Meta etiketi. Doğruladıktan sonra Indexing › Sitemap files bölümüne sitemap adresini ekleyin.', 'flx-seo' ),
		),
		'verify_naver'  => array(
			'name'        => 'Naver Search Advisor',
			'url'         => 'https://searchadvisor.naver.com/',
			'link'        => __( 'Naver Search Advisor\'ı aç', 'flx-seo' ),
			'placeholder' => '<meta name="naver-site-verification" …>',
			'help'        => __( 'Kore\'nin en büyük arama motoru. Site ekle › HTML etiketi; sonra Request › Submit sitemap. İsteğe bağlı.', 'flx-seo' ),
		),
		'verify_seznam' => array(
			'name'        => 'Seznam Webmaster',
			'url'         => 'https://search.seznam.cz/',
			'link'        => __( 'Seznam Webmaster\'ı aç', 'flx-seo' ),
			'placeholder' => '<meta name="seznam-wmt" …>',
			'help'        => __( 'Çekya\'nın arama motoru (Webmaster bağlantısı sayfa altında). Meta etiketi yöntemi. İsteğe bağlı.', 'flx-seo' ),
		),
		'verify_baidu'  => array(
			'name'        => 'Baidu Webmaster',
			'url'         => 'https://ziyuan.baidu.com/site/index',
			'link'        => __( 'Baidu Webmaster\'ı aç', 'flx-seo' ),
			'placeholder' => '<meta name="baidu-site-verification" …>',
			'help'        => __( 'Çin\'in arama motoru; kayıt için Çin telefon numarası gerekebilir. Çin\'e hitap etmiyorsanız gerek yok.', 'flx-seo' ),
		),
	);
}

/** Makale (Article schema, og:type article) sayılan yazı türleri. Ön ayarlar kendi türlerini ekler (ör. etkinlik, proje). */
function flxseo_article_types() {
	return array_values( array_unique( (array) apply_filters( 'flxseo_article_types', array( 'post' ) ) ) );
}

/** Google Site Kit kurulu ve bir yönetici Google hesabını bağlamış mı. */
function flxseo_sitekit_connected() {
	return defined( 'GOOGLESITEKIT_VERSION' ) && (bool) get_option( 'googlesitekit_has_connected_admins', false );
}

/** Son IndexNow toplu bildiriminin kısa metni. */
function flxseo_indexnow_last_text( $last ) {
	if ( empty( $last['time'] ) ) {
		return '';
	}
	$code = (int) ( $last['code'] ?? 0 );
	/* translators: 1: tarih, 2: adres sayısı, 3: HTTP kodu */
	$t = sprintf( __( '%1$s · %2$d adres · HTTP %3$d', 'flx-seo' ), flx_date( (int) $last['time'], 'datetime' ), (int) ( $last['count'] ?? 0 ), $code );
	if ( $code >= 400 ) {
		$t .= ' · ' . ( 403 === $code ? __( 'anahtar dosyası doğrulanamadı', 'flx-seo' ) : ( 422 === $code ? __( 'adresler site alan adıyla eşleşmiyor', 'flx-seo' ) : ( 429 === $code ? __( 'çok sık istek, sonra tekrar deneyin', 'flx-seo' ) : __( 'hata', 'flx-seo' ) ) ) );
	}
	return $t;
}

function flxseo_tabs() {
	return array(
		'genel'   => __( 'Genel', 'flx-seo' ),
		'kurum'   => __( 'Kurum ve sosyal', 'flx-seo' ),
		'kontrol' => __( 'SEO kontrolü', 'flx-seo' ),
		'denetim' => __( 'Site denetimi', 'flx-seo' ),
		'ayarlar' => __( 'Ayarlar', 'flx-seo' ),
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

add_action(
	'admin_init',
	function () {
		register_setting(
			'flxseo',
			'flxseo',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'flxseo_sanitize',
			)
		);
	}
);

function flxseo_sanitize( $in ) {
	$in  = (array) $in;
	$d   = flxseo_defaults();
	// Gelen dizide olmayan anahtar eski değerini korur (Flux Setup ön ayarı, MCP ve kod yalnız birkaç alan yazar).
	// Ayar formu kapalı onay kutuları için gizli 0 gönderir.
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
	return $out;
}

function flxseo_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'genel'; // phpcs:ignore WordPress.Security.NonceVerification
	$o   = wp_parse_args( (array) get_option( 'flxseo', array() ), flxseo_defaults() );
	$n   = 'flxseo';
	$tabs = flxseo_tabs();
	?>
<div class="wrap flxseo-wrap">
	<h1>SEO</h1>
	<?php settings_errors(); ?>
	<?php if ( flxseo_other_seo_plugin() ) : ?>
		<div class="notice notice-warning"><p>Başka bir SEO eklentisi etkin. Çakışma olmaması için bu eklentinin meta ve schema çıktısı kapalı; favicon, SEO kontrolü ve yapay zekâ araçları çalışmaya devam ediyor.</p></div>
	<?php endif; ?>
	<?php if ( ! function_exists( 'flxai_mcp_tools' ) ) : ?>
		<div class="notice notice-info"><p><?php esc_html_e( 'Flux SEO\'nun yapay zekâ (MCP) araçları ortak MCP sunucusu üzerinden çalışır. Sunucu, anahtarlar ve ayarlar Flux AI & MCP › MCP ekranındadır; Flux AI 0.7.0 ve üstünü kurup etkinleştirin.', 'flx-seo' ); ?></p></div>
	<?php endif; ?>
	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $k => $label ) : ?>
			<a class="nav-tab<?php echo $tab === $k ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( flxseo_admin_url( $k ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php
	if ( 'kontrol' === $tab ) {
		flxseo_audit_page();
		echo '</div>';
		return;
	}
	if ( 'denetim' === $tab ) {
		flxseo_audit_admin_page();
		echo '</div>';
		return;
	}
	if ( 'ayarlar' === $tab ) {
		flxseo_audit_settings_page();
		echo '</div>';
		return;
	}
	?>
	<form method="post" action="options.php">
		<?php settings_fields( 'flxseo' ); ?>
		<?php
		// Diğer sekmedeki değerler kaybolmasın.
		foreach ( $o as $k => $v ) {
			$here = 'genel' === $tab
				? in_array( $k, array( 'enabled', 'site_name', 'alt_name', 'sep', 'home_title', 'home_desc', 'og_image', 'verify_google', 'verify_bing', 'verify_yandex', 'verify_naver', 'verify_seznam', 'verify_baidu', 'noindex_shop', 'noindex_search', 'noindex_author', 'noindex_tags', 'auto_alt', 'indexnow' ), true )
				: in_array( $k, array( 'org_name', 'org_type', 'founding', 'logo', 'street', 'locality', 'postal', 'phone', 'email', 'same_as', 'twitter', 'knows_about', 'area_served', 'donate_label', 'donate_url' ), true );
			if ( ! $here ) {
				printf( '<input type="hidden" name="%s[%s]" value="%s">', esc_attr( $n ), esc_attr( $k ), esc_attr( $v ) );
			} elseif ( is_int( flxseo_defaults()[ $k ] ) ) {
				// Kapalı onay kutusu gönderilmez; gizli 0 kapalı olduğunu bildirir (onay kutusu sonra gelir, 1 kazanır).
				printf( '<input type="hidden" name="%s[%s]" value="0">', esc_attr( $n ), esc_attr( $k ) );
			}
		}
		?>
		<table class="form-table" role="presentation">
		<?php if ( 'genel' === $tab ) : ?>
			<tr><th scope="row">SEO çıktısı</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[enabled]" value="1" <?php checked( $o['enabled'] ); ?>> Meta etiketleri, Open Graph ve schema'yı ekle</label></td></tr>
			<tr><th scope="row"><label for="ys-sn">Google'da görünen site adı</label></th><td><input id="ys-sn" class="regular-text" name="<?php echo esc_attr( $n ); ?>[site_name]" value="<?php echo esc_attr( $o['site_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<p class="description">Arama sonuçlarında alan adı yerine bu ad görünür (WebSite schema). Kısa ve tanınan bir ad seçin. Değişiklik Google'a yeniden taramadan sonra yansır. Alternatif: <input class="regular-text" name="<?php echo esc_attr( $n ); ?>[alt_name]" value="<?php echo esc_attr( $o['alt_name'] ); ?>"></p></td></tr>
			<tr><th scope="row"><label for="ys-sep">Başlık ayıracı</label></th><td><input id="ys-sep" class="small-text" name="<?php echo esc_attr( $n ); ?>[sep]" value="<?php echo esc_attr( $o['sep'] ); ?>"> <span class="description">Örnek: Hakkımızda <?php echo esc_html( $o['sep'] ); ?> <?php echo esc_html( $o['alt_name'] ); ?></span></td></tr>
			<tr><th scope="row"><label for="ys-ht">Ana sayfa başlığı</label></th><td><input id="ys-ht" class="large-text" maxlength="70" name="<?php echo esc_attr( $n ); ?>[home_title]" value="<?php echo esc_attr( $o['home_title'] ); ?>" placeholder="<?php echo esc_attr( flxseo_opt( 'home_title' ) ); ?>"></td></tr>
			<tr><th scope="row"><label for="ys-hd">Ana sayfa açıklaması</label></th><td><textarea id="ys-hd" class="large-text" rows="3" maxlength="320" name="<?php echo esc_attr( $n ); ?>[home_desc]" placeholder="<?php echo esc_attr( flxseo_opt( 'home_desc' ) ); ?>"><?php echo esc_textarea( $o['home_desc'] ); ?></textarea><p class="description">150–160 karakter ideal. Boşsa site sloganı kullanılır.</p></td></tr>
			<tr><th scope="row"><label for="ys-og">Varsayılan paylaşım görseli</label></th><td><input id="ys-og" class="large-text code" name="<?php echo esc_attr( $n ); ?>[og_image]" value="<?php echo esc_attr( $o['og_image'] ); ?>" placeholder="<?php echo esc_attr( FLXSEO_URL . 'assets/icons/og-varsayilan.jpg' ); ?>"><p class="description">Öne çıkan görseli olmayan sayfalarda kullanılır. 1200×630 JPG/PNG. Boşsa eklentinin görseli.</p></td></tr>
			<tr id="search-engines"><th scope="row"><?php esc_html_e( 'Arama motorları', 'flx-seo' ); ?></th><td>
				<p class="description"><?php esc_html_e( 'Google dışındaki arama motorları (Bing, Yandex, DuckDuckGo, Yahoo, Ecosia) siteyi ancak tanıtılınca hızlı tarar. Her birinin kendi panelinde siteyi ekleyin; doğrulama kodunu aşağıya yapıştırın, sitemap adresini gönderin.', 'flx-seo' ); ?></p>
				<p><label for="flxseo-sitemap-url"><strong><?php esc_html_e( 'Sitemap adresi', 'flx-seo' ); ?></strong></label><br>
					<input id="flxseo-sitemap-url" class="regular-text code" readonly value="<?php echo esc_attr( home_url( '/wp-sitemap.xml' ) ); ?>" onclick="this.select()">
					<span class="description"><?php esc_html_e( 'robots.txt\'de de yazılı; Search Console, Bing ve Yandex panellerine bu adresi ekleyin.', 'flx-seo' ); ?></span></p>
				<table class="widefat striped" style="max-width:760px;margin-top:8px"><tbody>
				<?php foreach ( flxseo_search_engines() as $k => $e ) : ?>
					<tr>
						<td style="width:180px"><label for="flxseo-<?php echo esc_attr( $k ); ?>"><strong><?php echo esc_html( $e['name'] ); ?></strong></label><br>
							<a href="<?php echo esc_url( $e['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $e['link'] ); ?> ↗</a></td>
						<td><input id="flxseo-<?php echo esc_attr( $k ); ?>" class="regular-text code" name="<?php echo esc_attr( $n ); ?>[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $o[ $k ] ); ?>" placeholder="<?php echo esc_attr( $e['placeholder'] ); ?>">
							<?php echo $o[ $k ] ? '<span class="dashicons dashicons-yes-alt" style="color:#008a20" title="' . esc_attr__( 'Doğrulama etiketi sitede', 'flx-seo' ) . '"></span>' : ''; ?>
							<?php if ( 'verify_google' === $k && flxseo_sitekit_connected() ) : ?><p><span class="dashicons dashicons-yes-alt" style="color:#008a20"></span> <?php esc_html_e( 'Google Site Kit ile bağlı; Search Console doğrulaması ve sitemap Site Kit\'te. Bu alanı boş bırakabilirsiniz.', 'flx-seo' ); ?></p><?php endif; ?>
							<p class="description"><?php echo esc_html( $e['help'] ); ?></p></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table></td></tr>
			<tr id="indexnow"><th scope="row">IndexNow</th><td>
				<?php
				$imsg = get_transient( 'flxseo_indexnow_msg_' . get_current_user_id() );
				if ( $imsg ) {
					delete_transient( 'flxseo_indexnow_msg_' . get_current_user_id() );
					printf( '<div class="notice notice-%s inline"><p>%s</p></div>', esc_attr( $imsg[0] ), esc_html( $imsg[1] ) );
				}
				$ilast = (array) get_option( 'flxseo_indexnow_last', array() );
				?>
				<p><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[indexnow]" value="1" <?php checked( $o['indexnow'] ); ?>> <?php esc_html_e( 'Yayınlanan ve güncellenen sayfaları Bing, Yandex, DuckDuckGo (Bing üzerinden), Seznam ve Naver\'e anında bildir', 'flx-seo' ); ?></label></p>
				<p class="description"><?php esc_html_e( 'Anahtar dosyası:', 'flx-seo' ); ?> <a href="<?php echo esc_url( home_url( '/' . flxseo_indexnow_key() . '.txt' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( flxseo_indexnow_key() ); ?>.txt</a>. <?php esc_html_e( 'Açıkken tüm site bir kez kendiliğinden bildirilir. Google IndexNow kullanmaz; Google için Search Console\'a sitemap ekleyin.', 'flx-seo' ); ?>
				<?php if ( ! get_option( 'blog_public' ) ) : ?><br><strong><?php esc_html_e( 'Site arama motorlarına kapalı (Ayarlar › Okuma); IndexNow bildirim göndermez.', 'flx-seo' ); ?></strong><?php endif; ?>
				<?php if ( ! empty( $ilast['time'] ) ) : ?><br><?php esc_html_e( 'Son toplu bildirim:', 'flx-seo' ); ?> <?php echo esc_html( flxseo_indexnow_last_text( $ilast ) ); ?><?php endif; ?></p>
				<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flxseo_indexnow_all' ), 'flxseo_indexnow_all' ) ); ?>"><?php esc_html_e( 'Tüm siteyi şimdi bildir', 'flx-seo' ); ?></a></p></td></tr>
			<tr><th scope="row">Dizine ekleme</th><td>
				<p><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[noindex_shop]" value="1" <?php checked( $o['noindex_shop'] ); ?>> Sepet, ödeme ve Hesabım sayfaları dizine eklenmesin</label></p>
				<p><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[noindex_search]" value="1" <?php checked( $o['noindex_search'] ); ?>> Site içi arama sonuçları dizine eklenmesin</label></p>
				<p><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[noindex_author]" value="1" <?php checked( $o['noindex_author'] ); ?>> Yazar sayfalarını kapat (ana sayfaya yönlendir, sitemap'ten çıkar)</label></p>
				<p><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[noindex_tags]" value="1" <?php checked( $o['noindex_tags'] ); ?>> Etiket sayfaları dizine eklenmesin (sitemap'ten de çıkar; kategoriler açık kalır)</label></p>
				<p class="description">RSS beslemeleri her zaman "noindex" başlığıyla sunulur; Google'da sayfa olarak görünmeleri gerekmez.</p></td></tr>
			<tr><th scope="row">Görseller</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[auto_alt]" value="1" <?php checked( $o['auto_alt'] ); ?>> Alt metni boş görsellere medya kütüphanesindeki alt metni, o da yoksa görsel adını ekle</label></td></tr>
		<?php else : ?>
			<tr><th scope="row"><label for="ys-on">Kurum adı</label></th><td><input id="ys-on" class="large-text" name="<?php echo esc_attr( $n ); ?>[org_name]" value="<?php echo esc_attr( $o['org_name'] ); ?>"></td></tr>
			<tr><th scope="row"><label for="ys-ot">Kurum türü</label></th><td><select id="ys-ot" name="<?php echo esc_attr( $n ); ?>[org_type]"><?php foreach ( array( 'NGO' => 'Dernek / STK (NGO)', 'Organization' => 'Kurum (Organization)' ) as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $o['org_type'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select>
				Kuruluş yılı <input class="small-text" name="<?php echo esc_attr( $n ); ?>[founding]" value="<?php echo esc_attr( $o['founding'] ); ?>"></td></tr>
			<tr><th scope="row"><label for="ys-lg">Logo (kare)</label></th><td><input id="ys-lg" class="large-text code" name="<?php echo esc_attr( $n ); ?>[logo]" value="<?php echo esc_attr( $o['logo'] ); ?>" placeholder="<?php echo esc_attr( FLXSEO_URL . 'assets/icons/icon-512.png' ); ?>"><p class="description">Google bilgi paneli için. En az 112×112 PNG. Boşsa Flux SEO simgesi.</p></td></tr>
			<tr><th scope="row">Adres</th><td><input class="regular-text" name="<?php echo esc_attr( $n ); ?>[street]" value="<?php echo esc_attr( $o['street'] ); ?>" placeholder="Sokak, no"> <input class="regular-text" name="<?php echo esc_attr( $n ); ?>[locality]" value="<?php echo esc_attr( $o['locality'] ); ?>" placeholder="İlçe, il"> <input class="small-text" name="<?php echo esc_attr( $n ); ?>[postal]" value="<?php echo esc_attr( $o['postal'] ); ?>" placeholder="Posta kodu"></td></tr>
			<tr><th scope="row">İletişim</th><td><input class="regular-text" name="<?php echo esc_attr( $n ); ?>[phone]" value="<?php echo esc_attr( $o['phone'] ); ?>" placeholder="+90 ..."> <input class="regular-text" name="<?php echo esc_attr( $n ); ?>[email]" value="<?php echo esc_attr( $o['email'] ); ?>" placeholder="e-posta"></td></tr>
			<tr><th scope="row"><label for="ys-sa">Sosyal medya profilleri</label></th><td><textarea id="ys-sa" class="large-text code" rows="5" name="<?php echo esc_attr( $n ); ?>[same_as]" placeholder="https://www.instagram.com/...&#10;https://www.youtube.com/@..."><?php echo esc_textarea( $o['same_as'] ); ?></textarea><p class="description">Her satıra bir adres. Google kurum bilgisini bu profillerle eşleştirir.</p></td></tr>
			<tr><th scope="row"><label for="ys-tw">X (Twitter) kullanıcı adı</label></th><td><input id="ys-tw" class="regular-text" name="<?php echo esc_attr( $n ); ?>[twitter]" value="<?php echo esc_attr( $o['twitter'] ); ?>" placeholder="@kullanici"></td></tr>
			<tr><th scope="row"><label for="ys-ka"><?php esc_html_e( 'Uzmanlık konuları', 'flx-seo' ); ?></label></th><td><textarea id="ys-ka" class="large-text" rows="4" name="<?php echo esc_attr( $n ); ?>[knows_about]" placeholder="<?php esc_attr_e( 'Her satıra bir konu', 'flx-seo' ); ?>"><?php echo esc_textarea( $o['knows_about'] ); ?></textarea><p class="description"><?php esc_html_e( 'Kurumun uzman olduğu konular (schema knowsAbout). Arama motorları ve yapay zekâ asistanları kurumu bu konularla eşleştirir. Boşsa eklenmez.', 'flx-seo' ); ?></p></td></tr>
			<tr><th scope="row"><label for="ys-as"><?php esc_html_e( 'Hizmet bölgesi', 'flx-seo' ); ?></label></th><td><input id="ys-as" class="regular-text" name="<?php echo esc_attr( $n ); ?>[area_served]" value="<?php echo esc_attr( $o['area_served'] ); ?>" placeholder="<?php esc_attr_e( 'ör. Türkiye', 'flx-seo' ); ?>"><p class="description"><?php esc_html_e( 'Hizmet verilen ülke (schema areaServed). Boşsa eklenmez.', 'flx-seo' ); ?></p></td></tr>
			<tr><th scope="row"><label for="ys-dl"><?php esc_html_e( 'Bağış çağrısı', 'flx-seo' ); ?></label></th><td><input id="ys-dl" class="regular-text" name="<?php echo esc_attr( $n ); ?>[donate_label]" value="<?php echo esc_attr( $o['donate_label'] ); ?>" placeholder="<?php esc_attr_e( 'ör. Bağış yap', 'flx-seo' ); ?>"> <input class="regular-text code" name="<?php echo esc_attr( $n ); ?>[donate_url]" value="<?php echo esc_attr( $o['donate_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/…/' ) ); ?>"><p class="description"><?php esc_html_e( 'Bağış kabul eden kurumlar için (schema DonateAction). Ad boşsa eklenmez; adres boşsa bağış sayfası kendiliğinden bulunur.', 'flx-seo' ); ?></p></td></tr>
		<?php endif; ?>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
	<?php
}
