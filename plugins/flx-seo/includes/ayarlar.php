<?php
/**
 * Ayarlar › SEO & MCP sayfası.
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function flxseo_defaults() {
	return array(
		'enabled'        => 1,
		'site_name'      => 'Örnek Vakfı',
		'alt_name'       => 'Örnek Vakfı',
		'sep'            => '–',
		'home_title'     => 'Örnek Vakfı – Yeşil Dünya',
		'home_desc'      => 'Örnek Vakfı: fidan dikim etkinlikleri, çevre eğitimleri ve fidan bağışıyla daha yeşil bir Türkiye için çalışıyoruz.',
		'org_name'       => 'Örnek Vakfı',
		'org_type'       => 'NGO',
		'founding'       => '1950',
		'logo'           => '',
		'og_image'       => '',
		'street'         => 'Maslak Mah. Büyükdere Cad. No: 208',
		'locality'       => 'Sarıyer, İstanbul',
		'postal'         => '',
		'phone'          => '',
		'email'          => '',
		'same_as'        => '',
		'twitter'        => '',
		'verify_google'  => '',
		'verify_bing'    => '',
		'verify_yandex'  => '',
		'noindex_shop'   => 1,
		'noindex_search' => 1,
		'noindex_author' => 1,
		'noindex_tags'   => 1,
		'auto_alt'       => 1,
		'indexnow'       => 1,
		'mcp_enabled'    => 0,
	);
}

/**
 * Sürüm geçişleri (bir kez çalışır).
 * 1.1.1: site adı "Örnek Vakfı" → "Örnek Vakfı".
 * 1.0.4: ana sayfa başlığı "Örnek Vakfı – Yeşil Dünya" (eski varsayılan ya da "… Ana Sayfa" başlıkları yerine).
 */
add_action(
	'init',
	function () {
		$db = (string) get_option( 'flxseo_db', '0' );
		if ( version_compare( $db, '1.1.1', '>=' ) ) {
			return;
		}
		if ( version_compare( $db, '1.0.4', '>=' ) ) {
			// 1.1.1: Google'daki site adı kısa "Örnek Vakfı" (eski varsayılan değiştirilmediyse).
			$o = (array) get_option( 'flxseo', array() );
			if ( 'Örnek Vakfı' === ( $o['site_name'] ?? '' ) ) {
				$o['site_name'] = 'Örnek Vakfı';
				update_option( 'flxseo', $o );
			}
			update_option( 'flxseo_db', '1.1.1' );
			return;
		}
		$o   = (array) get_option( 'flxseo', array() );
		$old = $o['home_title'] ?? '';
		if ( '' === $old || 'Örnek Vakfı' === $old || false !== mb_stripos( $old, 'Ana Sayfa' ) ) {
			$o['home_title'] = 'Örnek Vakfı – Yeşil Dünya';
			update_option( 'flxseo', $o );
		}
		$front = (int) get_option( 'page_on_front' );
		if ( $front && false !== mb_stripos( (string) get_post_meta( $front, '_flxseo_title', true ), 'Ana Sayfa' ) ) {
			delete_post_meta( $front, '_flxseo_title' );
		}
		update_option( 'flxseo_db', '1.0.4' );
		// Bir sonraki yüklemede 1.1.1 geçişi çalışır.
	},
	1
);

function flxseo_opt( $key ) {
	static $o = null;
	if ( null === $o || doing_action( 'update_option_flxseo' ) ) {
		$o = wp_parse_args( (array) get_option( 'flxseo', array() ), flxseo_defaults() );
	}
	return $o[ $key ] ?? null;
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

function flxseo_tabs() {
	return array(
		'genel'   => 'Genel',
		'kurum'   => 'Kurum ve sosyal',
		'kontrol' => 'SEO kontrolü',
		'mcp'     => 'Yapay zekâ (MCP)',
	);
}

/**
 * Sol menüde ayrı "SEO & MCP" öğesi; sekmeler alt menü olarak da listelenir.
 */
add_action(
	'admin_menu',
	function () {
		// Renkli FluxWP logosu (assets/icon.svg) CSS ile verilir; WordPress menü ikonunu tek renge boyar.
		add_menu_page( 'SEO & MCP', 'SEO & MCP', 'manage_options', 'flxseo', 'flxseo_settings_page', 'none', 81 );
		foreach ( flxseo_tabs() as $k => $label ) {
			add_submenu_page( 'flxseo', 'SEO & MCP – ' . $label, $label, 'manage_options', 'genel' === $k ? 'flxseo' : 'flxseo&tab=' . $k, 'genel' === $k ? 'flxseo_settings_page' : '__return_null' );
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

// Eski adres (Ayarlar › SEO & MCP) yeni menüye yönlenir.
add_action(
	'admin_init',
	function () {
		global $pagenow;
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
	$d   = flxseo_defaults();
	$out = array();
	foreach ( $d as $k => $v ) {
		if ( is_int( $v ) ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		} elseif ( in_array( $k, array( 'logo', 'og_image' ), true ) ) {
			$out[ $k ] = esc_url_raw( $in[ $k ] ?? '' );
		} elseif ( in_array( $k, array( 'home_desc', 'same_as' ), true ) ) {
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
	<h1>SEO &amp; MCP</h1>
	<?php settings_errors(); ?>
	<?php if ( flxseo_other_seo_plugin() ) : ?>
		<div class="notice notice-warning"><p>Başka bir SEO eklentisi etkin. Çakışma olmaması için bu eklentinin meta ve schema çıktısı kapalı; favicon, SEO kontrolü ve MCP çalışmaya devam ediyor.</p></div>
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
	if ( 'mcp' === $tab ) {
		flxseo_mcp_page( $o );
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
				? in_array( $k, array( 'enabled', 'site_name', 'alt_name', 'sep', 'home_title', 'home_desc', 'og_image', 'verify_google', 'verify_bing', 'verify_yandex', 'noindex_shop', 'noindex_search', 'noindex_author', 'noindex_tags', 'auto_alt', 'indexnow' ), true )
				: in_array( $k, array( 'org_name', 'org_type', 'founding', 'logo', 'street', 'locality', 'postal', 'phone', 'email', 'same_as', 'twitter' ), true );
			if ( ! $here ) {
				printf( '<input type="hidden" name="%s[%s]" value="%s">', esc_attr( $n ), esc_attr( $k ), esc_attr( $v ) );
			}
		}
		?>
		<table class="form-table" role="presentation">
		<?php if ( 'genel' === $tab ) : ?>
			<tr><th scope="row">SEO çıktısı</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[enabled]" value="1" <?php checked( $o['enabled'] ); ?>> Meta etiketleri, Open Graph ve schema'yı ekle</label></td></tr>
			<tr><th scope="row"><label for="ys-sn">Google'da görünen site adı</label></th><td><input id="ys-sn" class="regular-text" name="<?php echo esc_attr( $n ); ?>[site_name]" value="<?php echo esc_attr( $o['site_name'] ); ?>">
				<p class="description">Arama sonuçlarında alan adı yerine bu ad görünür (WebSite schema). Kısa ve tanınan bir ad seçin. Değişiklik Google'a yeniden taramadan sonra yansır. Alternatif: <input class="regular-text" name="<?php echo esc_attr( $n ); ?>[alt_name]" value="<?php echo esc_attr( $o['alt_name'] ); ?>"></p></td></tr>
			<tr><th scope="row"><label for="ys-sep">Başlık ayıracı</label></th><td><input id="ys-sep" class="small-text" name="<?php echo esc_attr( $n ); ?>[sep]" value="<?php echo esc_attr( $o['sep'] ); ?>"> <span class="description">Örnek: Hakkımızda <?php echo esc_html( $o['sep'] ); ?> <?php echo esc_html( $o['alt_name'] ); ?></span></td></tr>
			<tr><th scope="row"><label for="ys-ht">Ana sayfa başlığı</label></th><td><input id="ys-ht" class="large-text" maxlength="70" name="<?php echo esc_attr( $n ); ?>[home_title]" value="<?php echo esc_attr( $o['home_title'] ); ?>"></td></tr>
			<tr><th scope="row"><label for="ys-hd">Ana sayfa açıklaması</label></th><td><textarea id="ys-hd" class="large-text" rows="3" maxlength="320" name="<?php echo esc_attr( $n ); ?>[home_desc]"><?php echo esc_textarea( $o['home_desc'] ); ?></textarea><p class="description">150–160 karakter ideal.</p></td></tr>
			<tr><th scope="row"><label for="ys-og">Varsayılan paylaşım görseli</label></th><td><input id="ys-og" class="large-text code" name="<?php echo esc_attr( $n ); ?>[og_image]" value="<?php echo esc_attr( $o['og_image'] ); ?>" placeholder="<?php echo esc_attr( FLXSEO_URL . 'assets/icons/og-varsayilan.jpg' ); ?>"><p class="description">Öne çıkan görseli olmayan sayfalarda kullanılır. 1200×630 JPG/PNG. Boşsa eklentinin görseli.</p></td></tr>
			<tr><th scope="row">Arama motoru doğrulama</th><td>
				<p><label>Google Search Console <input class="regular-text code" name="<?php echo esc_attr( $n ); ?>[verify_google]" value="<?php echo esc_attr( $o['verify_google'] ); ?>" placeholder="meta etiketini yapıştırın"></label></p>
				<p><label>Bing Webmaster <input class="regular-text code" name="<?php echo esc_attr( $n ); ?>[verify_bing]" value="<?php echo esc_attr( $o['verify_bing'] ); ?>"></label></p>
				<p><label>Yandex <input class="regular-text code" name="<?php echo esc_attr( $n ); ?>[verify_yandex]" value="<?php echo esc_attr( $o['verify_yandex'] ); ?>"></label></p></td></tr>
			<tr id="indexnow"><th scope="row">IndexNow</th><td>
				<?php
				$imsg = get_transient( 'flxseo_indexnow_msg_' . get_current_user_id() );
				if ( $imsg ) {
					delete_transient( 'flxseo_indexnow_msg_' . get_current_user_id() );
					printf( '<div class="notice notice-%s inline"><p>%s</p></div>', esc_attr( $imsg[0] ), esc_html( $imsg[1] ) );
				}
				$ilast = (array) get_option( 'flxseo_indexnow_last', array() );
				?>
				<p><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[indexnow]" value="1" <?php checked( $o['indexnow'] ); ?>> Yayınlanan ve güncellenen sayfaları Bing, Yandex, DuckDuckGo (Bing üzerinden), Seznam ve Naver'e anında bildir</label></p>
				<p class="description">Anahtar dosyası: <a href="<?php echo esc_url( home_url( '/' . flxseo_indexnow_key() . '.txt' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( flxseo_indexnow_key() ); ?>.txt</a>. Google IndexNow kullanmaz; Google için Search Console'a sitemap ekleyin.
				<?php if ( ! empty( $ilast['time'] ) ) : ?><br>Son toplu bildirim: <?php echo esc_html( wp_date( 'd.m.Y H:i', $ilast['time'] ) . ' · ' . (int) $ilast['count'] . ' adres · HTTP ' . (int) $ilast['code'] ); ?><?php endif; ?></p>
				<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flxseo_indexnow_all' ), 'flxseo_indexnow_all' ) ); ?>">Tüm siteyi şimdi bildir</a></p></td></tr>
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
			<tr><th scope="row"><label for="ys-lg">Logo (kare)</label></th><td><input id="ys-lg" class="large-text code" name="<?php echo esc_attr( $n ); ?>[logo]" value="<?php echo esc_attr( $o['logo'] ); ?>" placeholder="<?php echo esc_attr( FLXSEO_URL . 'assets/icons/icon-512.png' ); ?>"><p class="description">Google bilgi paneli için. En az 112×112 PNG. Boşsa yaprak simgesi.</p></td></tr>
			<tr><th scope="row">Adres</th><td><input class="regular-text" name="<?php echo esc_attr( $n ); ?>[street]" value="<?php echo esc_attr( $o['street'] ); ?>" placeholder="Sokak, no"> <input class="regular-text" name="<?php echo esc_attr( $n ); ?>[locality]" value="<?php echo esc_attr( $o['locality'] ); ?>" placeholder="İlçe, il"> <input class="small-text" name="<?php echo esc_attr( $n ); ?>[postal]" value="<?php echo esc_attr( $o['postal'] ); ?>" placeholder="Posta kodu"></td></tr>
			<tr><th scope="row">İletişim</th><td><input class="regular-text" name="<?php echo esc_attr( $n ); ?>[phone]" value="<?php echo esc_attr( $o['phone'] ); ?>" placeholder="+90 ..."> <input class="regular-text" name="<?php echo esc_attr( $n ); ?>[email]" value="<?php echo esc_attr( $o['email'] ); ?>" placeholder="e-posta"></td></tr>
			<tr><th scope="row"><label for="ys-sa">Sosyal medya profilleri</label></th><td><textarea id="ys-sa" class="large-text code" rows="5" name="<?php echo esc_attr( $n ); ?>[same_as]" placeholder="https://www.instagram.com/...&#10;https://www.youtube.com/@..."><?php echo esc_textarea( $o['same_as'] ); ?></textarea><p class="description">Her satıra bir adres. Google kurum bilgisini bu profillerle eşleştirir.</p></td></tr>
			<tr><th scope="row"><label for="ys-tw">X (Twitter) kullanıcı adı</label></th><td><input id="ys-tw" class="regular-text" name="<?php echo esc_attr( $n ); ?>[twitter]" value="<?php echo esc_attr( $o['twitter'] ); ?>" placeholder="@kullanici"></td></tr>
		<?php endif; ?>
		</table>
		<?php submit_button(); ?>
	</form>
</div>
	<?php
}
