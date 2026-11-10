<?php
/**
 * Görünüm › Tema Ayarları: çerez banner'ı ve Google Analytics (yalnızca onaydan sonra yüklenir).
 *
 * @package fluxwp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function flx_ayar_defaults() {
	return array(
		'cookie_enabled'  => 1,
		'cookie_title'    => 'Çerezleri kullanıyoruz',
		'cookie_text'     => 'Sitemizin düzgün çalışması için zorunlu çerezler, ziyaretleri anlamak için ise yalnızca onay verirseniz analiz çerezleri kullanıyoruz.',
		'cookie_accept'   => 'Kabul et',
		'cookie_reject'   => 'Yalnızca zorunlu',
		'cookie_more'     => 'Çerez Politikası',
		'cookie_page'     => 0,
		'cookie_position' => 'card',
		'cookie_rev'      => 1,
		'ga4_id'          => '',
		'ga_mode'         => 'consent',
		'dark_mode'       => 'auto',
		'decoration_pattern' => 'dots',
		'table_head'      => '',
		'news_intro' => '',
		'animasyon'       => 'on',
		'sayfalama'       => 'sonsuz',
		'site_visibility' => 'auto',
		'coming_soon_title' => '',
		'coming_soon_text' => '',
		'coming_soon_email' => '',
		'site_mod'        => 'shop',
		'aktif_kart'      => 'on',
		'aktif_kampanya'  => 0,
		'consent_scripts' => '',
		'bank_holder'     => '',
		'bank_iban'       => '',
	);
}

/** IBAN: boşluksuz büyük harf; geçersizse boş. */
function flx_iban_temizle( $iban ) {
	$iban = strtoupper( preg_replace( '/\s+/', '', (string) $iban ) );
	return preg_match( '/^TR\d{24}$/', $iban ) ? $iban : '';
}

/** IBAN'ı 4'lü gruplar halinde gösterir. */
function flx_iban_bicim( $iban ) {
	return trim( chunk_split( (string) $iban, 4, ' ' ) );
}

/**
 * Havale hesabı tek kaynaktan (0.5.30): Flux Donate varsa WooCommerce'teki havale ayarı
 * (flxb_bank_account(): Havale (bağış) → Doğrudan banka havalesi), yoksa Tema Ayarları › Banka hesabı.
 *
 * @return array{holder:string,iban:string}
 */
function flx_havale_hesabi() {
	$a = function_exists( 'flxb_bank_account' ) ? flxb_bank_account() : null;
	if ( $a && '' !== (string) $a['iban'] ) {
		return array( 'holder' => (string) $a['holder'], 'iban' => (string) $a['iban'] );
	}
	return array( 'holder' => (string) flx_ayar( 'bank_holder' ), 'iban' => (string) flx_ayar( 'bank_iban' ) );
}

/**
 * Bağış sayfasındaki Havale/EFT kartı: "flx-copy--iban" ve "flx-copy--holder" paragrafları
 * flx_havale_hesabi()'ndan gelir (IBAN tek yerden değişir).
 */
add_filter(
	'render_block_core/paragraph',
	function ( $html, $block ) {
		$cls = ' ' . ( $block['attrs']['className'] ?? '' ) . ' ';
		if ( false !== strpos( $cls, ' flx-copy--iban ' ) ) {
			$val = flx_iban_bicim( flx_havale_hesabi()['iban'] );
		} elseif ( false !== strpos( $cls, ' flx-copy--holder ' ) ) {
			$val = flx_havale_hesabi()['holder'];
		} else {
			return $html;
		}
		if ( '' === $val ) {
			return $html;
		}
		return (string) preg_replace_callback(
			'#(<p\b[^>]*>).*?(</p>)#s',
			function ( $m ) use ( $val ) {
				return $m[1] . esc_html( $val ) . $m[2];
			},
			$html,
			1
		);
	},
	10,
	2
);

/** Flux Donate'a havale hesabı (Tema Ayarları › Banka hesabı). */
add_filter(
	'flxb_bank_accounts',
	function ( $accounts ) {
		$iban = (string) flx_ayar( 'bank_iban' );
		if ( $iban ) {
			$accounts['tl'] = array(
				'id'       => 'tl',
				'name'     => (string) flx_ayar( 'bank_holder' ),
				'currency' => 'TRY',
				'iban'     => flx_iban_bicim( $iban ),
			);
		}
		return $accounts;
	}
);

/**
 * Site türü: 'shop' (mağaza: Siparişler, Mağazaya dön) ya da 'donation' (bağış: Bağışlar, Bağış Yap).
 * Tema Ayarları › Genel'den ya da ön ayardan (flx_ayarlar.site_mod) gelir.
 */
function flx_donation_mode() {
	return 'donation' === flx_ayar( 'site_mod' );
}

function flx_ayar( $key ) {
	$opts = wp_parse_args( (array) get_option( 'flx_ayarlar', array() ), flx_ayar_defaults() );
	return $opts[ $key ] ?? null;
}

/**
 * Çerez Politikası sayfasının üstünde banner'ı yeniden açan düğme (yalnız çerez banner'ı açıkken görünür).
 */
add_filter(
	'the_content',
	function ( $html ) {
		static $done = false;
		if ( $done || ! is_page() || (int) get_the_ID() !== (int) get_queried_object_id() ) {
			return $html;
		}
		$id = (int) flx_ayar( 'cookie_page' );
		$p  = get_queried_object();
		if ( ! $p || ( $id ? $p->ID !== $id : 'cerez-politikasi' !== $p->post_name ) ) {
			return $html;
		}
		$done = true;
		return '<div class="wp-block-buttons flx-cookie-open flx-cookie-change"><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#cerez-tercihleri">' . esc_html__( 'Change cookie preferences', 'fluxwp' ) . '</a></div></div>' . $html;
	},
	9
);

function flx_ayar_policy_url() {
	$id = (int) flx_ayar( 'cookie_page' );
	if ( ! $id || 'publish' !== get_post_status( $id ) ) {
		$page = get_page_by_path( 'cerez-politikasi' );
		$id   = $page && 'publish' === $page->post_status ? $page->ID : 0;
	}
	return $id ? get_permalink( $id ) : '';
}

/* ---------- Yönetim sayfası ---------- */

function flx_ayar_menu() {
	add_theme_page( __( 'Theme Settings', 'fluxwp' ), __( 'Theme Settings', 'fluxwp' ), 'manage_options', 'flx-ayarlar', 'flx_ayar_page' );
}
add_action( 'admin_menu', 'flx_ayar_menu' );

/** Tema Ayarları: renk alanları için WordPress renk seçici. */
function flx_ayar_assets( $hook ) {
	if ( 'appearance_page_flx-ayarlar' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".flx-color").wpColorPicker();});' );
}
add_action( 'admin_enqueue_scripts', 'flx_ayar_assets' );

/**
 * Tema Ayarları'ndaki tablo başlığı rengi: palet değişkenini ezer (boşsa stil/palet rengi).
 * Koyu modda theme.css kendi tonlarını kullanır.
 */
function flx_ayar_colors_css() {
	$css = '';
	foreach ( array( 'table_head' => 'table-head' ) as $key => $slug ) {
		$hex = sanitize_hex_color( (string) flx_ayar( $key ) );
		if ( $hex ) {
			$css .= '--wp--preset--color--' . $slug . ':' . $hex . ';';
		}
	}
	return $css ? ':root:root{' . $css . '}' : ''; // global-styles'tan sonra/önce basılmasından bağımsız kazansın.
}

function flx_ayar_register() {
	register_setting(
		'flx_ayarlar',
		'flx_ayarlar',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'flx_ayar_sanitize',
			'default'           => flx_ayar_defaults(),
		)
	);
}
add_action( 'admin_init', 'flx_ayar_register' );

function flx_ayar_sanitize( $in ) {
	$in  = (array) $in;
	$d   = flx_ayar_defaults();
	$old = wp_parse_args( (array) get_option( 'flx_ayarlar', array() ), $d );
	$on  = static function ( $v ) {
		return ! ( empty( $v ) || 'off' === $v || '0' === $v || 'false' === $v );
	};
	if ( empty( $in['_form'] ) ) {
		// Formdan gelmeyen yazım (ön ayar, AI, sistem aracı) kısmidir: gönderilmeyen alanlar olduğu gibi kalır.
		// Önceden eksik onay kutuları "kapalı" sayılıyordu; ön ayar yalnız site_mod yazınca çerez banner'ı kapanıyordu.
		$in = array_merge( $old, $in );
	}
	unset( $in['_form'] );
	$out = array(
		'site_visibility' => 'coming-soon' === ( $in['site_visibility'] ?? 'auto' ) ? 'coming-soon' : 'auto',
		'coming_soon_title' => sanitize_text_field( $in['coming_soon_title'] ?? '' ),
		'coming_soon_text' => sanitize_textarea_field( $in['coming_soon_text'] ?? '' ),
		'coming_soon_email' => sanitize_email( $in['coming_soon_email'] ?? '' ),
		'decoration_pattern' => in_array( $in['decoration_pattern'] ?? '', array( 'dots', 'leaves', 'none' ), true ) ? $in['decoration_pattern'] : 'dots',
		'news_intro' => sanitize_text_field( $in['news_intro'] ?? '' ),
		'table_head'      => (string) sanitize_hex_color( $in['table_head'] ?? '' ),
		'cookie_enabled'  => $on( $in['cookie_enabled'] ?? 0 ) ? 1 : 0,
		'cookie_title'    => sanitize_text_field( $in['cookie_title'] ?? $d['cookie_title'] ),
		'cookie_text'     => wp_kses_post( $in['cookie_text'] ?? $d['cookie_text'] ),
		'cookie_accept'   => sanitize_text_field( $in['cookie_accept'] ?? $d['cookie_accept'] ),
		'cookie_reject'   => sanitize_text_field( $in['cookie_reject'] ?? $d['cookie_reject'] ),
		'cookie_more'     => sanitize_text_field( $in['cookie_more'] ?? $d['cookie_more'] ),
		'cookie_page'     => absint( $in['cookie_page'] ?? 0 ),
		'cookie_position' => in_array( $in['cookie_position'] ?? '', array( 'card', 'bar' ), true ) ? $in['cookie_position'] : 'card',
		'cookie_rev'      => max( 1, absint( $old['cookie_rev'] ) ) + ( empty( $in['cookie_reset'] ) ? 0 : 1 ),
		'dark_mode'       => 'off' === ( $in['dark_mode'] ?? '' ) ? 'off' : 'auto',
		'animasyon'       => $on( $in['animasyon'] ?? 0 ) ? 'on' : 'off',
		'sayfalama'       => 'numara' === ( $in['sayfalama'] ?? '' ) ? 'numara' : 'sonsuz',
		'site_mod'        => 'donation' === ( $in['site_mod'] ?? '' ) ? 'donation' : 'shop',
		'aktif_kart'      => $on( $in['aktif_kart'] ?? 0 ) ? 'on' : 'off',
		'aktif_kampanya'  => absint( $in['aktif_kampanya'] ?? 0 ),
		'ga_mode'         => 'after' === ( $in['ga_mode'] ?? '' ) ? 'after' : 'consent',
		'ga4_id'          => preg_match( '/^G-[A-Z0-9]{4,}$/', strtoupper( trim( $in['ga4_id'] ?? '' ) ) ) ? strtoupper( trim( $in['ga4_id'] ) ) : '',
		'consent_scripts' => $old['consent_scripts'],
		'bank_holder'     => sanitize_text_field( $in['bank_holder'] ?? $old['bank_holder'] ),
		'bank_iban'       => flx_iban_temizle( $in['bank_iban'] ?? $old['bank_iban'] ),
	);
	if ( ! empty( $in['bank_iban'] ) && ! $out['bank_iban'] ) {
		if ( function_exists( 'add_settings_error' ) ) {
			add_settings_error( 'flx_ayarlar', 'iban', 'IBAN “TR” ile başlayan 26 karakter olmalı; kaydedilmedi.' );
		}
		$out['bank_iban'] = $old['bank_iban'];
	}
	// Ham script yalnızca unfiltered_html yetkisi olanlarca kaydedilir.
	if ( isset( $in['consent_scripts'] ) && current_user_can( 'unfiltered_html' ) ) {
		$out['consent_scripts'] = trim( (string) $in['consent_scripts'] );
	}
	if ( ! empty( $in['ga4_id'] ) && ! $out['ga4_id'] && function_exists( 'add_settings_error' ) ) {
		add_settings_error( 'flx_ayarlar', 'ga4', 'GA4 Ölçüm Kimliği “G-XXXXXXX” biçiminde olmalı; kaydedilmedi.' );
	}
	if ( function_exists( 'flx_purge_page_cache' ) ) {
		flx_purge_page_cache();
	}
	return $out;
}

function flx_ayar_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$o = wp_parse_args( (array) get_option( 'flx_ayarlar', array() ), flx_ayar_defaults() );
	$n = 'flx_ayarlar';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$bolum = sanitize_key( $_GET['bolum'] ?? 'genel' );
	$has_presets = function_exists( 'flxk_render_panel' );
	?>
	<div class="wrap<?php echo 'onayarlar' === $bolum ? ' flxk' : ''; ?>">
		<h1><?php esc_html_e( 'Theme Settings', 'fluxwp' ); ?></h1>
		<?php if ( $has_presets ) : ?>
		<nav class="nav-tab-wrapper">
			<a class="nav-tab<?php echo 'onayarlar' !== $bolum ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'themes.php?page=flx-ayarlar' ) ); ?>"><?php esc_html_e( 'General', 'fluxwp' ); ?></a>
			<a class="nav-tab<?php echo 'onayarlar' === $bolum ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'themes.php?page=flx-ayarlar&bolum=onayarlar' ) ); ?>"><?php esc_html_e( 'Presets', 'fluxwp' ); ?></a>
		</nav>
		<?php endif; ?>
		<?php
		if ( $has_presets && 'onayarlar' === $bolum ) {
			echo '<p class="flxk-lead">' . esc_html__( 'A preset sets up the site in one step with the organisation profile, plugin settings, colours, header/footer, templates, pages and menus. Changes you make in the panel are never overwritten; updates are applied one by one with your approval.', 'fluxwp' ) . '</p>';
			flxk_render_panel();
			echo '</div>';
			return;
		}
		?>
		<?php settings_errors( 'flx_ayarlar' ); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'flx_ayarlar' ); ?>
			<input type="hidden" name="flx_ayarlar[_form]" value="1">
			<?php flx_coming_soon_settings( $o ); ?>
			<h2 class="title"><?php esc_html_e( 'Appearance', 'fluxwp' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Site type', 'fluxwp' ); ?></th><td>
					<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[site_mod]" value="shop" <?php checked( $o['site_mod'], 'shop' ); ?>> <?php esc_html_e( 'Shop: Orders, Return to shop', 'fluxwp' ); ?></label><br>
					<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[site_mod]" value="donation" <?php checked( $o['site_mod'], 'donation' ); ?>> <?php esc_html_e( 'Donation: Donations, Donate button, return to the donation page', 'fluxwp' ); ?></label>
					<p class="description"><?php esc_html_e( 'Sets the wording in My account, the cart and the mobile menu.', 'fluxwp' ); ?></p></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Background pattern', 'fluxwp' ); ?></th><td><select name="<?php echo esc_attr( $n ); ?>[decoration_pattern]">
                    <?php foreach ( array( 'dots' => __( 'Dots', 'fluxwp' ), 'leaves' => __( 'Leaves', 'fluxwp' ), 'none' => __( 'None', 'fluxwp' ) ) as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $o['decoration_pattern'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></td></tr>
				<tr><th scope="row"><label for="flx-table-head"><?php esc_html_e( 'Table header background', 'fluxwp' ); ?></label></th><td><input id="flx-table-head" class="flx-color" name="<?php echo esc_attr( $n ); ?>[table_head]" value="<?php echo esc_attr( $o['table_head'] ); ?>" data-default-color="#F1F2F4">
					<p class="description"><?php esc_html_e( 'Header cells of tables in pages, Wiki and WooCommerce. Pick a tone slightly darker than the page background. Leave empty to use the style colour.', 'fluxwp' ); ?></p></td></tr>
                <tr><th scope="row"><?php esc_html_e( 'News introduction', 'fluxwp' ); ?></th><td><input class="large-text" name="<?php echo esc_attr( $n ); ?>[news_intro]" value="<?php echo esc_attr( $o['news_intro'] ); ?>" placeholder="<?php esc_attr_e( 'Latest news, updates and articles.', 'fluxwp' ); ?>"></td></tr>
				<tr><th scope="row">Dark mode</th><td>
					<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[dark_mode]" value="auto" <?php checked( $o['dark_mode'], 'auto' ); ?>> Otomatik: cihaz koyu moddaysa site de koyu</label><br>
					<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[dark_mode]" value="off" <?php checked( $o['dark_mode'], 'off' ); ?>> Kapalı: site her zaman açık renk</label>
					<p class="description">Her iki durumda da tarayıcı eklentilerinin (Dark Reader, Noir vb.) siteyi zorla koyulaştırması engellenir.</p></td></tr>
				<tr><th scope="row">Kaydırma animasyonları</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[animasyon]" value="1" <?php checked( 'off' !== $o['animasyon'] ); ?>> Başlıklar, kartlar ve görseller sayfa kaydırıldıkça yumuşakça belirsin</label>
					<p class="description">Cihazında "hareketi azalt" ayarı açık olan ziyaretçilerde animasyon gösterilmez.</p></td></tr>
				<tr><th scope="row">Liste sayfaları</th><td>
					<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[sayfalama]" value="sonsuz" <?php checked( $o['sayfalama'], 'sonsuz' ); ?>> Sonsuz kaydırma: aşağı inildikçe sonraki yazılar kendiliğinden yüklenir</label><br>
					<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[sayfalama]" value="numara" <?php checked( $o['sayfalama'], 'numara' ); ?>> Sayfa numaraları (1 2 3 … Sonraki)</label>
					<p class="description">Haberler, kategori, etiket, arama ve arşiv sayfaları. Sonsuz kaydırmada da sayfa bağlantıları arka planda kalır (Google ve JavaScript'i kapalı ziyaretçiler için).</p></td></tr>
				<?php if ( function_exists( 'flx_aktif_kart_html' ) ) : ?>
				<tr><th scope="row">Ana sayfa: aktif proje kartı</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[aktif_kart]" value="1" <?php checked( 'off' !== $o['aktif_kart'] ); ?>> Slider'ın altında son "devam eden" projeyi bağış düğmesiyle göster</label>
					<?php if ( class_exists( 'FLXB_Types' ) ) : ?>
					<p><label>Bağış ilerlemesi: <select name="<?php echo esc_attr( $n ); ?>[aktif_kampanya]"><option value="0"><?php echo esc_html( 'Otomatik (hedefi olan ilk açık bağış türü)' ); ?></option><?php foreach ( FLXB_Types::all() as $flx_t ) : ?><option value="<?php echo (int) $flx_t['id']; ?>" <?php selected( (int) $o['aktif_kampanya'], (int) $flx_t['id'] ); ?>><?php echo esc_html( $flx_t['title'] ); ?></option><?php endforeach; ?></select></label></p>
					<?php endif; ?>
					<p class="description">Proje görseli, başlığı ve özeti projenin kendisinden gelir.</p></td></tr>
				<?php endif; ?>
			</table>

			<h2 class="title">Çerez banner'ı</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Durum</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[cookie_enabled]" value="1" <?php checked( $o['cookie_enabled'] ); ?>> İlk ziyarette çerez banner'ını göster</label></td></tr>
				<tr><th scope="row"><label for="flx-ct">Başlık</label></th><td><input id="flx-ct" class="regular-text" name="<?php echo esc_attr( $n ); ?>[cookie_title]" value="<?php echo esc_attr( $o['cookie_title'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="flx-cx">Metin</label></th><td><textarea id="flx-cx" class="large-text" rows="3" name="<?php echo esc_attr( $n ); ?>[cookie_text]"><?php echo esc_textarea( $o['cookie_text'] ); ?></textarea></td></tr>
				<tr><th scope="row">Butonlar</th><td>
					<input name="<?php echo esc_attr( $n ); ?>[cookie_accept]" value="<?php echo esc_attr( $o['cookie_accept'] ); ?>" aria-label="Kabul butonu">
					<input name="<?php echo esc_attr( $n ); ?>[cookie_reject]" value="<?php echo esc_attr( $o['cookie_reject'] ); ?>" aria-label="Reddet butonu">
					<input name="<?php echo esc_attr( $n ); ?>[cookie_more]" value="<?php echo esc_attr( $o['cookie_more'] ); ?>" aria-label="Politika bağlantısı">
					<p class="description">Kabul · Reddet · Politika bağlantısı metinleri.</p></td></tr>
				<tr><th scope="row"><label for="flx-cp">Çerez Politikası sayfası</label></th><td>
					<?php
					wp_dropdown_pages(
						array(
							'name'              => $n . '[cookie_page]',
							'id'                => 'flx-cp',
							'selected'          => (int) $o['cookie_page'],
							'show_option_none'  => 'Otomatik (/cerez-politikasi/)',
							'option_none_value' => 0,
						)
					);
					?>
				</td></tr>
				<tr><th scope="row">Görünüm</th><td>
					<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[cookie_position]" value="card" <?php checked( $o['cookie_position'], 'card' ); ?>> Sol altta kart</label>&nbsp;&nbsp;
					<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[cookie_position]" value="bar" <?php checked( $o['cookie_position'], 'bar' ); ?>> Altta tam genişlik şerit</label></td></tr>
				<tr><th scope="row">Onayları sıfırla</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[cookie_reset]" value="1"> Kaydedince herkese banner'ı yeniden göster</label> <span class="description">(politika değişince; şu an sürüm <?php echo (int) $o['cookie_rev']; ?>)</span></td></tr>
			</table>

			<h2 class="title">Google Analytics ve onaylı kodlar</h2>
			<p>Aşağıdakiler yalnızca ziyaretçi “<?php echo esc_html( $o['cookie_accept'] ); ?>” dedikten sonra yüklenir (Google Consent Mode v2 varsayılanı: reddedildi). Banner kapalıysa doğrudan yüklenir.</p>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="flx-ga">GA4 Ölçüm Kimliği</label></th><td><input id="flx-ga" class="regular-text code" placeholder="G-XXXXXXXXXX" name="<?php echo esc_attr( $n ); ?>[ga4_id]" value="<?php echo esc_attr( $o['ga4_id'] ); ?>"><p class="description">Google Analytics › Yönetici › Veri akışları › Ölçüm kimliği.</p></td></tr>
				<tr><th scope="row">Yükleme</th><td>
					<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[ga_mode]" value="consent" <?php checked( $o['ga_mode'], 'consent' ); ?>> Google etiketi her sayfada &lt;head&gt; içinde, onaya kadar çerezsiz (Google Consent Mode v2, önerilen; Google'ın etiket testi bunu görür)</label><br>
					<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[ga_mode]" value="after" <?php checked( $o['ga_mode'], 'after' ); ?>> Etiket yalnızca “Kabul et”ten sonra yüklensin (Google etiket testi bulamaz)</label></td></tr>
				<tr><th scope="row"><label for="flx-cs">Ek kodlar</label></th><td><textarea id="flx-cs" class="large-text code" rows="6" name="<?php echo esc_attr( $n ); ?>[consent_scripts]" <?php disabled( ! current_user_can( 'unfiltered_html' ) ); ?> placeholder="&lt;script&gt;...&lt;/script&gt; (Meta Pixel, Hotjar, GTM vb.)"><?php echo esc_textarea( $o['consent_scripts'] ); ?></textarea><p class="description">Onaydan sonra sayfaya eklenir.</p></td></tr>
			</table>
			<h2 class="title">Banka hesabı (Havale/EFT)</h2>
			<p>Yedek hesap: IBAN önce WooCommerce › Ayarlar › Ödemeler › <strong>Havale (bağış)</strong>'tan, orada yoksa <strong>Doğrudan banka havalesi</strong> hesaplarından okunur; ikisi de boşsa bu hesap kullanılır (bağış sayfası, ödeme sayfası, Çağrı Merkezi).<?php
			$flx_h = function_exists( 'flxb_bank_account' ) ? flxb_bank_account() : null;
			if ( $flx_h && flx_iban_temizle( $flx_h['iban'] ) !== flx_iban_temizle( (string) $o['bank_iban'] ) ) {
				echo ' <strong>Şu an kullanılan: ' . esc_html( $flx_h['iban_display'] . ( '' !== $flx_h['holder'] ? ' · ' . $flx_h['holder'] : '' ) ) . '</strong>';
			}
			?></p>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="flx-bh">Alıcı adı</label></th><td><input id="flx-bh" class="large-text" name="<?php echo esc_attr( $n ); ?>[bank_holder]" value="<?php echo esc_attr( $o['bank_holder'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="flx-bi">TL IBAN</label></th><td><input id="flx-bi" class="regular-text code" name="<?php echo esc_attr( $n ); ?>[bank_iban]" value="<?php echo esc_attr( flx_iban_bicim( $o['bank_iban'] ) ); ?>"></td></tr>
			</table>
			<?php flx_pay_logos_admin(); ?>
			<?php submit_button( __( 'Save settings', 'fluxwp' ) ); ?>
		</form>
		<?php flx_tasi_box(); ?>
	</div>
	<?php
}

/* ---------- Ön yüz ---------- */

function flx_cookie_active() {
	return (bool) flx_ayar( 'cookie_enabled' );
}

function flx_cookie_body_class( $classes ) {
	if ( flx_cookie_active() ) {
		$classes[] = 'flx-cookie-on';
	}
	if ( 'off' !== flx_ayar( 'dark_mode' ) ) {
		$classes[] = 'flx-dark';
	}
	if ( 'off' !== flx_ayar( 'animasyon' ) ) {
		$classes[] = 'flx-anim';
	}
	if ( 'numara' !== flx_ayar( 'sayfalama' ) ) {
		$classes[] = 'flx-infinite';
	}
	return $classes;
}
add_filter( 'body_class', 'flx_cookie_body_class' );

/**
 * Consent Mode v2 varsayılanı en başta (diğer etiketlerden önce) tanımlanır.
 */
function flx_cookie_head() {
	if ( is_admin() ) {
		return;
	}
	// Dark mode temanın kendisinde; tarayıcı/eklenti ikinci kez koyulaştırmasın.
	echo '<meta name="color-scheme" content="' . ( 'off' === flx_ayar( 'dark_mode' ) ? 'only light' : 'light dark' ) . '">' . "\n" . '<meta name="darkreader-lock">' . "\n";
	?>
<script id="flx-consent-default">window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',functionality_storage:'granted',security_storage:'granted',wait_for_update:500});(function(){var m=document.cookie.match(/(?:^|; )flx_cerez=([^;]+)/),v=m?decodeURIComponent(m[1]):'';try{v=v||localStorage.getItem('flx_cerez')||''}catch(e){}if(v==='kabul:<?php echo (int) flx_ayar( 'cookie_rev' ); ?>'<?php echo flx_cookie_active() ? '' : '||1'; ?>){gtag('consent','update',{ad_storage:'granted',ad_user_data:'granted',ad_personalization:'granted',analytics_storage:'granted'});}})();</script>
	<?php
	$ga = flx_ayar( 'ga4_id' );
	if ( $ga && ( 'consent' === flx_ayar( 'ga_mode' ) || ! flx_cookie_active() ) ) :
		?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga ); ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', '<?php echo esc_js( $ga ); ?>');
</script>
		<?php
	endif;
}
add_action( 'wp_head', 'flx_cookie_head', 1 );

function flx_cookie_footer() {
	$ga      = flx_ayar( 'ga4_id' );
	$scripts = flx_ayar( 'consent_scripts' );
	$active  = flx_cookie_active();
	if ( ! $active && ! $ga && ! $scripts ) {
		return;
	}
	$config = array(
		'active' => $active,
		'rev'    => (int) flx_ayar( 'cookie_rev' ),
		'ga'     => $ga,
		'gaHead' => $ga && ( 'consent' === flx_ayar( 'ga_mode' ) || ! $active ),
	);
	if ( $scripts ) {
		echo '<template id="flx-consent-scripts">' . $scripts . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput -- yalnızca unfiltered_html yetkili yönetici kaydedebilir.
	}
	if ( $active ) {
		$pos    = 'bar' === flx_ayar( 'cookie_position' ) ? 'bar' : 'card';
		$policy = flx_ayar_policy_url();
		?>
<div class="flx-cookie flx-cookie--<?php echo esc_attr( $pos ); ?>" id="flx-cookie" role="dialog" aria-live="polite" aria-labelledby="flx-cookie-title" hidden>
	<div class="flx-cookie__icon" aria-hidden="true"></div>
	<div class="flx-cookie__body">
		<p class="flx-cookie__title" id="flx-cookie-title"><?php echo esc_html( flx_t( flx_ayar( 'cookie_title' ) ) ); ?></p>
		<p class="flx-cookie__text"><?php echo wp_kses_post( flx_t( flx_ayar( 'cookie_text' ) ) ); ?>
		<?php if ( $policy ) : ?> <a href="<?php echo esc_url( $policy ); ?>"><?php echo esc_html( flx_t( flx_ayar( 'cookie_more' ) ) ); ?></a><?php endif; ?></p>
	</div>
	<div class="flx-cookie__actions">
		<button type="button" class="flx-cookie__btn flx-cookie__btn--ghost" data-flx-consent="red"><?php echo esc_html( flx_t( flx_ayar( 'cookie_reject' ) ) ); ?></button>
		<button type="button" class="flx-cookie__btn" data-flx-consent="kabul"><?php echo esc_html( flx_t( flx_ayar( 'cookie_accept' ) ) ); ?></button>
	</div>
</div>
		<?php
	}
	wp_print_inline_script_tag( 'window.flxConsent=' . wp_json_encode( $config ) . ';' );
	wp_print_script_tag(
		array(
			'src'   => add_query_arg( 'ver', FLX_VERSION, get_theme_file_uri( 'assets/js/cerez.js' ) ),
			'defer' => true,
		)
	);
}
add_action( 'wp_footer', 'flx_cookie_footer', 50 );

/**
 * YouTube gömmeleri: oynatıcı, sayfanın adresini (referrer) görmezse "Video kullanılamıyor" (Hata 153) verir.
 * Sunucu/güvenlik eklentisi "Referrer-Policy: no-referrer" gönderse bile iframe adresini iletsin.
 */
function flx_youtube_referrer( $html, $url ) {
	if ( false !== strpos( $url, 'youtu' ) && false !== strpos( $html, '<iframe' ) && false === strpos( $html, 'referrerpolicy' ) ) {
		$html = str_replace( '<iframe', '<iframe referrerpolicy="strict-origin-when-cross-origin"', $html );
	}
	return $html;
}
add_filter( 'embed_oembed_html', 'flx_youtube_referrer', 10, 2 );

/**
 * YouTube gömmesi: önce kapak görseli + oynat butonu (tıklanınca oynatıcı yüklenir, öncesinde YouTube'a istek/çerez yok).
 * Altında her zaman "YouTube'da izle" bağlantısı.
 */
function flx_youtube_id( $url ) {
	if ( preg_match( '#(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)([\w-]{11})#', (string) $url, $m ) ) {
		return $m[1];
	}
	return '';
}

function flx_youtube_facade( $html, $block ) {
	if ( 'core/embed' !== $block['blockName'] || empty( $block['attrs']['url'] ) ) {
		return $html;
	}
	$id = flx_youtube_id( $block['attrs']['url'] );
	if ( ! $id ) {
		return $html;
	}
	$dikey = false !== strpos( $block['attrs']['url'], '/shorts/' ) || false !== strpos( $block['attrs']['className'] ?? '', 'flx-video--dikey' );
	$cls   = 'wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube flx-video' . ( $dikey ? ' flx-video--dikey' : '' );
	if ( ! empty( $block['attrs']['className'] ) ) {
		$cls .= ' ' . $block['attrs']['className'];
	}
	$watch = $dikey ? 'https://www.youtube.com/shorts/' . $id : 'https://www.youtube.com/watch?v=' . $id;
	$thumb = 'https://i.ytimg.com/vi/' . $id . '/' . ( $dikey ? 'oar2.jpg' : 'maxresdefault.jpg' );
	$alt   = 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg';
	ob_start();
	?>
<figure class="<?php echo esc_attr( implode( ' ', array_unique( explode( ' ', $cls ) ) ) ); ?>">
	<div class="wp-block-embed__wrapper flx-player" data-flx-id="<?php echo esc_attr( $id ); ?>">
		<button type="button" class="flx-player__btn" aria-label="<?php esc_attr_e( 'Play video', 'fluxwp' ); ?>">
			<img src="<?php echo esc_url( $thumb ); ?>" onerror="this.onerror=null;this.src='<?php echo esc_url( $alt ); ?>'" alt="" loading="lazy" decoding="async">
			<span class="flx-player__play" aria-hidden="true"></span>
		</button>
	</div>
	<figcaption class="flx-player__alt"><a href="<?php echo esc_url( $watch ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Watch on YouTube', 'fluxwp' ); ?></a></figcaption>
</figure>
	<?php
	return ob_get_clean();
}
add_filter( 'render_block', 'flx_youtube_facade', 20, 2 );

/**
 * Ön ayar / AI için ayar şeması (Flux Setup `flx_settings_schema`). Açıklamalar makine içindir (AI, ön ayar yazarı).
 */
add_filter(
	'flx_settings_schema',
	function ( $s ) {
		$d                = flx_ayar_defaults();
		$f                = function ( $type, $desc, $extra = array() ) {
			return array(
				'type' => $type,
				'desc' => $desc,
			) + $extra;
		};
		$fields           = array(
			'site_visibility' => $f( 'enum', 'auto follows WooCommerce (or live without it); coming-soon gates the entire public site.', array( 'enum' => array( 'auto', 'coming-soon' ) ) ),
			'coming_soon_title' => $f( 'string', 'Optional coming-soon heading. Empty uses the translated default.' ),
			'coming_soon_text' => $f( 'string', 'Optional plain-text coming-soon message.' ),
			'coming_soon_email' => $f( 'string', 'Optional explicitly public contact email.' ),
			'decoration_pattern' => $f( 'enum', 'Background decoration for title bands, menus and consent cards.', array( 'enum' => array( 'dots', 'leaves', 'none' ) ) ),
			'news_intro' => $f( 'string', 'Optional news archive introduction. Empty uses the translated generic theme text.' ),
			'table_head'      => $f( 'string', 'Table header cell background hex colour (#RRGGBB), used by core tables, Flux Wiki and WooCommerce. Empty uses the palette "table-head" colour (default light grey).' ),
			'cookie_enabled'  => $f( 'bool', 'Show the cookie consent banner on the first visit.' ),
			'cookie_title'    => $f( 'string', 'Banner title (Flux Lang: texts.<lang>.tema_cookie_title).' ),
			'cookie_text'     => $f( 'html', 'Banner text; links allowed (Flux Lang: tema_cookie_text).' ),
			'cookie_accept'   => $f( 'string', 'Accept button label.' ),
			'cookie_reject'   => $f( 'string', 'Necessary-only button label.' ),
			'cookie_more'     => $f( 'string', 'Policy link label; empty hides the link.' ),
			'cookie_page'     => $f( 'page', 'Cookie/privacy policy page ("@page:<key>").' ),
			'cookie_position' => $f( 'enum', 'Banner layout.', array( 'enum' => array( 'card', 'bar' ) ) ),
			'ga4_id'          => $f( 'string', 'Google Analytics 4 measurement ID (G-XXXXXXX).' ),
			'ga_mode'         => $f( 'enum', 'consent: GA loads in consent mode; after: only after accept.', array( 'enum' => array( 'consent', 'after' ) ) ),
			'dark_mode'       => $f( 'enum', 'auto follows the visitor system setting.', array( 'enum' => array( 'auto', 'off' ) ) ),
			'animasyon'       => $f( 'enum', 'Scroll reveal animations.', array( 'enum' => array( 'on', 'off' ) ) ),
			'sayfalama'       => $f( 'enum', 'Archive pagination.', array( 'enum' => array( 'sonsuz', 'numara' ) ) ),
			'site_mod'        => $f( 'enum', 'shop (WooCommerce store) or donation (Flux Donate).', array( 'enum' => array( 'shop', 'donation' ) ) ),
			'aktif_kart'      => $f( 'enum', 'Active campaign card.', array( 'enum' => array( 'on', 'off' ) ) ),
			'aktif_kampanya'  => $f( 'int', 'Active campaign ID.' ),
			'bank_holder'     => $f( 'string', 'Bank account holder for Havale/EFT.' ),
			'bank_iban'       => $f( 'string', 'IBAN (TR + 24 digits).' ),
		);
		foreach ( $fields as $k => $v ) {
			$fields[ $k ]['default'] = $d[ $k ] ?? null;
		}
		$s['flx_ayarlar'] = array(
			'label'  => 'FluxWP theme settings (Appearance > Theme Settings)',
			'fields' => $fields,
		);
		return $s;
	}
);

/** Presets and the theme panel share the same allowlisted decoration setting. */
add_filter( 'body_class', function ( $classes ) {
    $pattern = flx_ayar( 'decoration_pattern' );
    $classes[] = 'flx-decoration--' . ( in_array( $pattern, array( 'dots', 'leaves', 'none' ), true ) ? $pattern : 'dots' );
    return $classes;
} );
