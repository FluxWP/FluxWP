<?php
/**
 * Görünüm › Tema Ayarları: menü sitesinde gereksiz WordPress özelliklerini kapatan anahtarlar.
 * Hepsi varsayılan açık (= özellik kapalı) ve geri alınabilir; davranışlar inc/kapat.php'de.
 * Üstte "Görünmez mod" bölümü (varsayılan açık; davranışı inc/gorunmez.php).
 *
 * @package flx-menu-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Anahtar => varsayılan (1 = kapalı). */
function yf_ayar_defaults() {
	return array(
		'posts_off'       => 1,
		'comments_off'    => 1,
		'feeds_off'       => 1,
		'search_off'      => 1,
		'attachments_off' => 1,
		'xmlrpc_off'      => 1,
		'emoji_off'       => 1,
		'embeds_off'      => 1,
		'users_rest_off'  => 1,
	);
}

/** Görünmez mod ayarları => varsayılan. rest_allow: satır başına REST öneki; path_allow: satır başına adres öneki. */
function yf_gorunmez_defaults() {
	return array(
		'hidden'     => 1,
		'menus_only' => 1,
		'rest_allow' => 'flx-qr-menu/v1',
		'path_allow' => '',
	);
}

function yf_ayar_all_defaults() {
	return yf_ayar_defaults() + yf_gorunmez_defaults();
}

/** Anahtar => [başlık, açıklama]. */
function yf_ayar_labels() {
	return array(
		'posts_off'       => array( __( 'Yazılar kapalı', 'flx-menu-theme' ), __( 'Yazılar menüsü ve "+ Yeni › Yazı" gizlenir; tek yazı, kategori, etiket, tarih ve yazar arşivleri 404 olur; site haritasından yazılar, kategoriler ve kullanıcılar çıkar.', 'flx-menu-theme' ) ),
		'comments_off'    => array( __( 'Yorumlar kapalı', 'flx-menu-theme' ), __( 'Yorum ve geri bildirimler her yerde kapanır; Yorumlar menüsü ve pano kutusu gizlenir; yorum gönderme ve yorum beslemeleri engellenir.', 'flx-menu-theme' ) ),
		'feeds_off'       => array( __( 'RSS beslemeleri kapalı', 'flx-menu-theme' ), __( '/feed/ ve diğer beslemeler 404 olur, sayfa başlığındaki besleme bağlantıları kalkar.', 'flx-menu-theme' ) ),
		'search_off'      => array( __( 'Arama kapalı', 'flx-menu-theme' ), __( '?s= ile arama 404 olur.', 'flx-menu-theme' ) ),
		'attachments_off' => array( __( 'Ek sayfaları kapalı', 'flx-menu-theme' ), __( 'Ortam dosyalarının ayrı sayfası açılmaz; dosyanın kendisine yönlenir.', 'flx-menu-theme' ) ),
		'xmlrpc_off'      => array( __( 'XML-RPC kapalı', 'flx-menu-theme' ), __( 'xmlrpc.php 403 döner (pingback ve eski uygulamalar). Jetpack gibi XML-RPC kullanan eklenti varsa açın.', 'flx-menu-theme' ) ),
		'emoji_off'       => array( __( 'Emoji betiği kapalı', 'flx-menu-theme' ), __( 'WordPress\'in emoji betiği ve stili yüklenmez (emojiler cihazın kendi yazı tipiyle görünür).', 'flx-menu-theme' ) ),
		'embeds_off'      => array( __( 'oEmbed kapalı', 'flx-menu-theme' ), __( 'Sayfaların başka sitelere gömülme bağlantıları ve /embed/ adresleri kapanır.', 'flx-menu-theme' ) ),
		'users_rest_off'  => array( __( 'Kullanıcı listesi gizli', 'flx-menu-theme' ), __( 'Giriş yapmamış ziyaretçiye REST API\'de /wp/v2/users kapalı (kullanıcı adları görünmez).', 'flx-menu-theme' ) ),
	);
}

function yf_ayar_all() {
	return wp_parse_args( (array) get_option( 'yf_ayarlar', array() ), yf_ayar_all_defaults() );
}

function yf_ayar( $key ) {
	$o = yf_ayar_all();
	return isset( $o[ $key ] ) ? (int) $o[ $key ] : 0;
}

function yf_ayar_text( $key ) {
	$o = yf_ayar_all();
	return isset( $o[ $key ] ) ? (string) $o[ $key ] : '';
}

function yf_ayar_menu() {
	add_theme_page( __( 'Tema Ayarları', 'flx-menu-theme' ), __( 'Tema Ayarları', 'flx-menu-theme' ), 'manage_options', 'yf-ayarlar', 'yf_ayar_page' );
}
add_action( 'admin_menu', 'yf_ayar_menu' );

function yf_ayar_register() {
	register_setting(
		'yf_ayarlar',
		'yf_ayarlar',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'yf_ayar_sanitize',
			'default'           => yf_ayar_all_defaults(),
		)
	);
}
add_action( 'admin_init', 'yf_ayar_register' );

function yf_ayar_sanitize( $in ) {
	$in  = is_array( $in ) ? $in : array();
	$out = array();
	foreach ( array_merge( array_keys( yf_ayar_defaults() ), array( 'hidden', 'menus_only' ) ) as $k ) {
		$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
	}
	// REST önekleri: ad/alan/sürüm (harf, rakam, - _ . / *); baştaki/sondaki / atılır.
	$rest = array();
	foreach ( yf_lines( isset( $in['rest_allow'] ) ? $in['rest_allow'] : '' ) as $l ) {
		$l = trim( strtolower( preg_replace( '#[^A-Za-z0-9_./*-]#', '', preg_replace( '#^.*?/wp-json/#', '', $l ) ) ), '/' );
		if ( '' !== $l ) {
			$rest[] = $l;
		}
	}
	$out['rest_allow'] = implode( "\n", array_unique( $rest ) );
	// Adres önekleri: / ile başlar; tam adres yapıştırılırsa alan adı atılır. "admin-ajax:eylem" / "admin-post:eylem"
	// kısaltması /wp-admin/admin-ajax.php?action=eylem olarak saklanır.
	$paths = array();
	foreach ( yf_lines( isset( $in['path_allow'] ) ? $in['path_allow'] : '' ) as $l ) {
		$l = preg_replace( '#^(admin-ajax|admin-post):\s*#i', '/wp-admin/$1.php?action=', $l );
		$l = preg_replace( '#^https?://[^/]+#i', '', $l );
		$l = preg_replace( '#[^A-Za-z0-9_./?=&%~:+-]#', '', $l );
		if ( '' !== $l ) {
			$paths[] = '/' . ltrim( $l, '/' );
		}
	}
	$out['path_allow'] = implode( "\n", array_unique( $paths ) );
	// Ayar değişince kalıcı bağlantılar (besleme/ek kuralları) ve sayfa önbelleği yenilenir.
	update_option( 'yf_flush_rewrite', 1, true );
	yf_purge_cache();
	return $out;
}

/** Sayfa önbelleğini temizler (LiteSpeed ve nesne önbelleği). */
function yf_purge_cache() {
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
	do_action( 'litespeed_purge_all' );
}

add_action(
	'init',
	function () {
		if ( get_option( 'yf_flush_rewrite' ) ) {
			delete_option( 'yf_flush_rewrite' );
			flush_rewrite_rules( false );
		}
	},
	99
);

function yf_ayar_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$o = yf_ayar_all();
	?>
	<div class="wrap yf-admin">
		<?php yf_admin_style(); ?>
		<h1><?php esc_html_e( 'Flux Menu Theme: Tema Ayarları', 'flx-menu-theme' ); ?></h1>
		<p class="yf-lead"><?php esc_html_e( 'menu.example.org yalnız QR menüler için kullanılır. İşaretli özellikler kapalıdır; işareti kaldırınca WordPress\'in varsayılan davranışı geri gelir.', 'flx-menu-theme' ); ?></p>
		<?php settings_errors(); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'yf_ayarlar' ); ?>
			<?php yf_gorunmez_card( $o ); ?>
			<div class="yf-card">
				<h2><?php esc_html_e( 'Kapalı WordPress özellikleri', 'flx-menu-theme' ); ?></h2>
				<ul class="yf-toggles">
					<?php foreach ( yf_ayar_labels() as $k => $l ) : ?>
						<li>
							<label>
								<input type="checkbox" name="yf_ayarlar[<?php echo esc_attr( $k ); ?>]" value="1" <?php checked( ! empty( $o[ $k ] ) ); ?>>
								<span><strong><?php echo esc_html( $l[0] ); ?></strong><small><?php echo esc_html( $l[1] ); ?></small></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php submit_button( __( 'Kaydet', 'flx-menu-theme' ) ); ?>
		</form>
		<p>
			<?php esc_html_e( 'Kurulum adımları ve hazır güncellemeler:', 'flx-menu-theme' ); ?>
			<a href="<?php echo esc_url( admin_url( 'themes.php?page=yf-kurulum' ) ); ?>"><?php esc_html_e( 'Görünüm › Site Kurulumu', 'flx-menu-theme' ); ?></a>
		</p>
	</div>
	<?php
}

/** Tema Ayarları'nın en üstündeki "Görünmez mod" kartı. */
function yf_gorunmez_card( $o ) {
	$st   = (array) get_option( 'yf_gorunmez_state', array() );
	$hts  = array(
		'ok'          => array( __( '.htaccess: dizin listesi kapalı; readme, changelog, .md ve .log dosyalarına ve kurulum betiklerine erişim yok.', 'flx-menu-theme' ), false ),
		'unwritable'  => array( __( '.htaccess yazılamadı; dizin listesi ve dosya engelleri uygulanmadı (Sistem Kontrolü\'ne bakın).', 'flx-menu-theme' ), true ),
		'unsupported' => array( __( 'Sunucu .htaccess okumuyor (Apache/LiteSpeed değil); dizin listesini, readme/.md/.log dosyalarını ve wp-admin/install.php erişimini sunucu ayarından kapatın.', 'flx-menu-theme' ), true ),
	);
	$ns   = yf_rest_namespaces();
	$name = 'yf_ayarlar';
	?>
	<div class="yf-card yf-hide" id="gorunmez">
		<h2><?php esc_html_e( 'Görünmez mod', 'flx-menu-theme' ); ?></h2>
		<p><?php esc_html_e( 'Site dışarıdan görünmez: arama motorları ve botlar dizine eklemez, site haritası ve REST API kapalıdır, adresler gezilemez. Giriş yapmış kullanıcılar (yönetici, menü yöneticisi) siteyi normal görür.', 'flx-menu-theme' ); ?></p>
		<ul class="yf-toggles">
			<li><label>
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[hidden]" value="1" <?php checked( ! empty( $o['hidden'] ) ); ?>>
				<span><strong><?php esc_html_e( 'Görünmez mod açık', 'flx-menu-theme' ); ?></strong><small><?php esc_html_e( 'robots.txt bütün botlara "Disallow: /", her yanıtta noindex (X-Robots-Tag ve meta), Ayarlar › Okuma "arama motorlarını engelle" işaretlenir (kapatınca önceki hâline döner). Bütün site haritaları (sitemap.xml, wp-sitemap.xml, sitemap_index.xml…) 404. REST API yalnız aşağıdaki ad alanlarında açık; /wp-json/ ve ad alanı dizinleri, JSONP gizli. admin-ajax.php, admin-post.php, xmlrpc.php, geri izleme, beslemeler, /embed/, arama, yazar/tarih arşivleri ve bulunamayan adresler sade 404. Sayfalarda WordPress/WooCommerce sürümü görünmez; giriş ve parola sıfırlama hataları kullanıcının var olup olmadığını söylemez, kayıt kapalı. WooCommerce kuruluysa mağaza, ürün, sepet, ödeme ve hesap sayfaları, Store API, wc-ajax, wc-api, sepete ekle ve indirme bağlantıları kapalı.', 'flx-menu-theme' ); ?></small></span>
			</label></li>
			<li><label>
				<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[menus_only]" value="1" <?php checked( ! empty( $o['menus_only'] ) ); ?>>
				<span><strong><?php esc_html_e( 'Yalnız menüler açık', 'flx-menu-theme' ); ?></strong><small><?php esc_html_e( 'Giriş yapmamış ziyaretçiye yalnız ana sayfa, QR menüler (/menu/, /menu/tesekkur/, alt alan adları), giriş sayfası ve aşağıdaki açık yollar açılır; diğer bütün adresler (sayfalar, arşivler, arama…) 404.', 'flx-menu-theme' ); ?></small></span>
			</label></li>
		</ul>
		<?php if ( yf_gizli() && isset( $st['htaccess'], $hts[ $st['htaccess'] ] ) ) : ?>
			<p class="yf-state<?php echo $hts[ $st['htaccess'] ][1] ? ' is-warn' : ''; ?>"><?php echo esc_html( $hts[ $st['htaccess'] ][0] ); ?></p>
		<?php endif; ?>
		<div class="yf-field">
			<label for="yf-rest-allow"><strong><?php esc_html_e( 'Açık REST ad alanları', 'flx-menu-theme' ); ?></strong></label>
			<textarea id="yf-rest-allow" name="<?php echo esc_attr( $name ); ?>[rest_allow]" rows="3" spellcheck="false" placeholder="flx-qr-menu/v1"><?php echo esc_textarea( (string) $o['rest_allow'] ); ?></textarea>
			<small><?php esc_html_e( 'Satır başına bir önek; giriş yapmamış istekler yalnız bunlara ulaşır. flx-qr-menu/v1 menülerin canlı yenilenmesi ve menü sitelerinin beslemesi için gerekli. Örnek: flx-chat/v1, wc/store. Uygulama parolası ya da Flux MCP anahtarıyla gelen istekler giriş yapmış sayılır, listeye gerek yok.', 'flx-menu-theme' ); ?></small>
			<?php if ( $ns ) : ?>
				<details><summary><?php esc_html_e( 'Bu sitedeki REST ad alanları', 'flx-menu-theme' ); ?></summary><p class="yf-ns"><?php foreach ( $ns as $n ) : ?><code><?php echo esc_html( $n ); ?></code> <?php endforeach; ?></p></details>
			<?php endif; ?>
		</div>
		<div class="yf-field">
			<label for="yf-path-allow"><strong><?php esc_html_e( 'Açık kalacak yollar', 'flx-menu-theme' ); ?></strong></label>
			<textarea id="yf-path-allow" name="<?php echo esc_attr( $name ); ?>[path_allow]" rows="3" spellcheck="false" placeholder="/wc-api/iyzico"><?php echo esc_textarea( (string) $o['path_allow'] ); ?></textarea>
			<small><?php esc_html_e( 'Satır başına bir adres öneki (/ ile başlar); giriş yapmamış ziyaretçiye bu adresler açık kalır. Ödeme geri dönüşleri için: /wc-api/<ad> (?wc-api=<ad> de açılır), /wc-ajax/<eylem> (?wc-ajax=<eylem>); bir eklentinin admin-ajax eylemi için admin-ajax:<eylem> (admin-post:<eylem>); XML-RPC (Jetpack) için /xmlrpc.php (XML-RPC anahtarı da açık olmalı); bir sayfa için /sayfa-adresi/.', 'flx-menu-theme' ); ?></small>
		</div>
		<p><a href="<?php echo esc_url( home_url( '/robots.txt' ) ); ?>" target="_blank" rel="noopener">robots.txt</a></p>
	</div>
	<?php
}

/** Tema Ayarları ve Site Kurulumu ekranlarının ortak stili (yalnız .yf-admin altında; koyu temaya uyar). */
function yf_admin_style() {
	?>
	<style>
		.yf-admin{--yf-surface:#fff;--yf-border:#dcdcde;--yf-muted:#646970;--yf-text:#1d2327;--yf-ok:#1f7a43;--yf-accent:#1f8a4c;max-width:980px}
		.yf-admin *{box-sizing:border-box}
		.yf-admin .yf-lead{font-size:14px;color:var(--yf-muted);max-width:760px}
		.yf-admin .yf-card{background:var(--yf-surface);border:1px solid var(--yf-border);border-radius:12px;padding:16px 20px;margin:16px 0;color:var(--yf-text)}
		.yf-admin .yf-card h2{margin:0 0 8px;font-size:16px}
		.yf-admin .yf-toggles{margin:0;display:grid;gap:4px}
		.yf-admin .yf-toggles li{margin:0;padding:10px 0;border-bottom:1px solid var(--yf-border)}
		.yf-admin .yf-toggles li:last-child{border-bottom:0}
		.yf-admin .yf-toggles label,.yf-admin .yf-steps label{display:flex;gap:12px;align-items:flex-start;cursor:pointer}
		.yf-admin .yf-toggles input,.yf-admin .yf-steps input{margin-top:3px;flex:0 0 auto}
		.yf-admin .yf-toggles span,.yf-admin .yf-steps span{display:flex;flex-direction:column;gap:2px}
		.yf-admin small{color:var(--yf-muted);font-size:12.5px;line-height:1.45}
		.yf-admin .yf-steps{display:grid;gap:10px;margin:8px 0 14px}
		.yf-admin .yf-hide{border-color:var(--yf-accent);box-shadow:inset 4px 0 0 var(--yf-accent)}
		.yf-admin .yf-field{display:flex;flex-direction:column;gap:6px;margin:14px 0 0}
		.yf-admin .yf-field textarea{width:100%;max-width:560px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px}
		.yf-admin .yf-field details{font-size:12.5px;color:var(--yf-muted)}
		.yf-admin .yf-ns{display:flex;flex-wrap:wrap;gap:4px;margin:6px 0 0}
		.yf-admin .yf-ns code{font-size:12px;word-break:break-all}
		.yf-admin .yf-state{margin:10px 0 0;color:var(--yf-ok)}
		.yf-admin .yf-state.is-warn{color:#a04a06}
		.yf-admin .yf-log{margin:8px 0 0 18px;list-style:disc}
		.yf-admin .yf-log li.is-warn{color:#a04a06}
		.yf-admin .yf-mig{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;margin:0}
		.yf-admin .yf-mig li{border:1px solid var(--yf-border);border-radius:10px;padding:12px 14px;margin:0;display:flex;flex-direction:column;gap:6px}
		.yf-admin .yf-mig .yf-done{color:var(--yf-ok);font-size:12px}
		.yf-admin .yf-mig .button{align-self:flex-start;margin-top:auto}
		@media (prefers-color-scheme:dark){
			html:not(.flxa-html) body.yf-screen,html:not(.flxa-html) body.yf-screen #wpwrap,html:not(.flxa-html) body.yf-screen #wpcontent,html:not(.flxa-html) body.yf-screen #wpbody-content{background:#0f1512;color-scheme:dark}
			html:not(.flxa-html) body.yf-screen .yf-admin{--yf-surface:#161e19;--yf-border:#2a3730;--yf-muted:#9aaba0;--yf-text:#e6eee8;--yf-ok:#7fdca4;color:#e6eee8}
			html:not(.flxa-html) body.yf-screen .yf-admin h1,html:not(.flxa-html) body.yf-screen .yf-admin h2,html:not(.flxa-html) body.yf-screen .yf-admin p,html:not(.flxa-html) body.yf-screen .yf-admin label{color:#e6eee8}
			html:not(.flxa-html) body.yf-screen .yf-admin a:not(.button){color:#7fdca4}
			html:not(.flxa-html) body.yf-screen .yf-admin .yf-log li.is-warn,html:not(.flxa-html) body.yf-screen .yf-admin .yf-state.is-warn{color:#f0a35e}
			html:not(.flxa-html) body.yf-screen .yf-admin textarea{background:#0f1512;color:#e6eee8;border-color:#2a3730}
			html:not(.flxa-html) body.yf-screen .yf-admin code{background:#22302a;color:#e6eee8}
		}
		@media (max-width:782px){.yf-admin .yf-card{padding:14px}.yf-admin .yf-toggles input,.yf-admin .yf-steps input{margin-top:0}}
	</style>
	<?php
}

add_filter(
	'admin_body_class',
	function ( $c ) {
		$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $s && in_array( $s->id, array( 'appearance_page_yf-ayarlar', 'appearance_page_yf-kurulum' ), true ) ? $c . ' yf-screen' : $c;
	}
);
