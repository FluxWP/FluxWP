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
		'coming_soon_store_title' => '',
		'coming_soon_store_text' => '',
		'coming_soon_email' => '',
		'site_mod'        => 'shop',
		'aktif_kart'      => 'on',
		'aktif_kampanya'  => 0,
		'consent_scripts' => '',
		'bank_holder'     => '',
		'bank_iban'       => '',
		'auth_style'      => 'card',
		'auth_logo'       => 0,
		'auth_title'      => '',
		'auth_text'       => '',
		'auth_image'      => 0,
		'auth_badge'      => '',
		'auth_panel_title' => '',
		'auth_panel_text' => '',
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
// Registered on init (not admin_init) so every write path (GraphQL, presets, Flux AI) is sanitised the same way.
add_action( 'init', 'flx_ayar_register' );

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
	if ( ! empty( $in['_form'] ) && function_exists( 'flx_visibility_form_save' ) ) {
		flx_visibility_form_save( true );
	}
	unset( $in['_form'] );
	$out = array(
		'site_visibility' => in_array( $in['site_visibility'] ?? '', array( 'coming-soon', 'store' ), true ) ? $in['site_visibility'] : 'auto',
		'coming_soon_title' => sanitize_text_field( $in['coming_soon_title'] ?? '' ),
		'coming_soon_text' => sanitize_textarea_field( $in['coming_soon_text'] ?? '' ),
		'coming_soon_store_title' => sanitize_text_field( $in['coming_soon_store_title'] ?? '' ),
		'coming_soon_store_text' => sanitize_textarea_field( $in['coming_soon_store_text'] ?? '' ),
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
		'auth_style'      => 'split' === ( $in['auth_style'] ?? '' ) ? 'split' : 'card',
		'auth_logo'       => $on( $in['auth_logo'] ?? 0 ) ? 1 : 0,
		'auth_title'      => sanitize_text_field( $in['auth_title'] ?? '' ),
		'auth_text'       => sanitize_textarea_field( $in['auth_text'] ?? '' ),
		'auth_image'      => absint( $in['auth_image'] ?? 0 ),
		'auth_badge'      => sanitize_text_field( $in['auth_badge'] ?? '' ),
		'auth_panel_title' => sanitize_text_field( $in['auth_panel_title'] ?? '' ),
		'auth_panel_text' => sanitize_textarea_field( $in['auth_panel_text'] ?? '' ),
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
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$bolum = sanitize_key( $_GET['bolum'] ?? 'genel' );
	// Presets tab (Flux Setup panel) stays a PHP page; everything else is the shared React screen.
	if ( 'onayarlar' === $bolum && function_exists( 'flxk_render_panel' ) ) {
		?>
		<div class="wrap flxk">
			<h1><?php esc_html_e( 'Theme Settings', 'fluxwp' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<a class="nav-tab" href="<?php echo esc_url( admin_url( 'themes.php?page=flx-ayarlar' ) ); ?>"><?php esc_html_e( 'General', 'fluxwp' ); ?></a>
				<a class="nav-tab nav-tab-active" href="<?php echo esc_url( admin_url( 'themes.php?page=flx-ayarlar&bolum=onayarlar' ) ); ?>"><?php esc_html_e( 'Presets', 'fluxwp' ); ?></a>
			</nav>
			<?php
			echo '<p class="flxk-lead">' . esc_html__( 'A preset sets up the site in one step with the organisation profile, plugin settings, colours, header/footer, templates, pages and menus. Changes you make in the panel are never overwritten; updates are applied one by one with your approval.', 'fluxwp' ) . '</p>';
			flxk_render_panel();
			?>
		</div>
		<?php
		return;
	}
	flx_flux_root( 'flx-theme-settings-app' );
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
add_filter( 'render_block_core/embed', 'flx_youtube_facade', 20, 2 );

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
			'site_visibility' => $f( 'enum', 'auto = live; coming-soon = entire public site shows the coming-soon page; store = site stays open, WooCommerce store pages (shop, product, cart, checkout) show a notice inside the header/footer. With WooCommerce this is synced both ways with WooCommerce › Settings › Site visibility; store needs WooCommerce.', array( 'enum' => array( 'auto', 'coming-soon', 'store' ) ) ),
			'coming_soon_title' => $f( 'string', 'Optional coming-soon heading. Empty uses the translated default.' ),
			'coming_soon_text' => $f( 'string', 'Optional plain-text coming-soon message.' ),
			'coming_soon_store_title' => $f( 'string', 'Optional store-only notice heading. Empty uses the translated default for the site type.' ),
			'coming_soon_store_text' => $f( 'string', 'Optional plain-text store-only notice message.' ),
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
			'auth_style'      => $f( 'enum', 'Sign-in window layout: card = single column; split = picture panel on the left (auth_image, auth_badge, auth_panel_title, auth_panel_text), form on the right. On phones the panel becomes a short strip above the form.', array( 'enum' => array( 'card', 'split' ) ) ),
			'auth_logo'       => $f( 'bool', 'Show the header logo at the top of the sign-in window (card layout).' ),
			'auth_title'      => $f( 'string', 'Heading above the sign-in form; empty uses the translated default (Flux Lang: tema_auth_title).' ),
			'auth_text'       => $f( 'string', 'Short line under the heading; empty shows none (Flux Lang: tema_auth_text).' ),
			'auth_image'      => $f( 'int', 'Attachment ID of the split layout picture ("@media:<key>" in a preset). Use a large, sharp photo; 0 shows a colour panel.' ),
			'auth_badge'      => $f( 'string', 'Small label at the top of the picture panel; empty hides it (Flux Lang: tema_auth_badge).' ),
			'auth_panel_title' => $f( 'string', 'Big line on the picture panel (Flux Lang: tema_auth_panel_title).' ),
			'auth_panel_text' => $f( 'string', 'Sentence under it on the picture panel (Flux Lang: tema_auth_panel_text).' ),
		);
		foreach ( $fields as $k => $v ) {
			$fields[ $k ]['default'] = $d[ $k ] ?? null;
		}
		$s['flx_ayarlar'] = array(
			'graphql' => 'theme_settings', // flxSettings { themeSettings { … } } for presets and AI; the Theme Settings screen uses flxThemeSettings.
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
