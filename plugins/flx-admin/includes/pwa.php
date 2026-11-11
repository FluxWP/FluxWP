<?php
/**
 * PWA: manifest + service worker + çevrimdışı sayfa. Hepsi admin-ajax.php üzerinden verilir; böylece
 * service worker'ın kapsamı /wp-admin/ olur ve sunucuya (.htaccess, kök dizin) dosya yazmak gerekmez.
 *
 * Önbellek kuralı: yönetim sayfalarının HTML'i ASLA önbelleğe alınmaz (kişisel veri, nonce). Yalnız sürümlü
 * statik dosyalar (css/js/simge) saklanır; bağlantı yoksa çevrimdışı sayfası gösterilir.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_flxa_manifest', 'flxa_pwa_manifest' );
add_action( 'wp_ajax_nopriv_flxa_manifest', 'flxa_pwa_manifest' );
add_action( 'wp_ajax_flxa_sw', 'flxa_pwa_sw' );
add_action( 'wp_ajax_nopriv_flxa_sw', 'flxa_pwa_sw' );
add_action( 'wp_ajax_flxa_offline', 'flxa_pwa_offline' );
add_action( 'wp_ajax_nopriv_flxa_offline', 'flxa_pwa_offline' );
add_action( 'wp_ajax_flxa_splash', 'flxa_pwa_splash' );
add_action( 'wp_ajax_nopriv_flxa_splash', 'flxa_pwa_splash' );
add_action( 'admin_head', 'flxa_pwa_head', 2 );
add_action( 'login_head', 'flxa_pwa_head', 2 );
add_action( 'login_footer', 'flxa_pwa_login_watch', 99 );
add_action( 'wp_ajax_flxa_auth', 'flxa_pwa_auth' );
add_action( 'wp_ajax_nopriv_flxa_auth', 'flxa_pwa_auth' );
add_action( 'wp_login', 'flxa_pwa_keep_login', 99, 2 );
add_filter( 'flxu_package_icons', 'flxa_package_icon', 10, 2 );
add_action( 'plugins_loaded', 'flxa_pwa_skip_start_preload', 1 );

/**
 * Açılış adresine (flxa-start=1) gelen navigation preload isteği boşa gider: service worker açılış ekranını
 * önbellekten verir. Paneli boşuna iki kez oluşturmamak için sunucu hemen boş cevap döner.
 */
function flxa_pwa_skip_start_preload() {
	if ( isset( $_GET['flxa-start'], $_SERVER['HTTP_SERVICE_WORKER_NAVIGATION_PRELOAD'] ) ) { // phpcs:ignore WordPress.Security
		status_header( 204 );
		nocache_headers();
		exit;
	}
}

/** PWA yalnız HTTPS'te (veya localhost'ta) çalışır. */
function flxa_pwa_possible() {
	$host = (string) wp_parse_url( admin_url(), PHP_URL_HOST );
	return is_ssl() || 0 === strpos( admin_url(), 'https://' ) || in_array( $host, array( 'localhost', '127.0.0.1' ), true );
}

function flxa_pwa_on() {
	return flxa_enabled() && flxa_setting( 'pwa' ) && flxa_pwa_possible();
}

function flxa_pwa_sw_url() {
	return add_query_arg(
		array(
			'action' => 'flxa_sw',
			'v'      => FLXA_VERSION,
		),
		admin_url( 'admin-ajax.php' )
	);
}

function flxa_pwa_name() {
	$n = trim( (string) flxa_setting( 'pwa_name' ) );
	return '' !== $n ? $n : flxa_brand();
}

function flxa_pwa_short() {
	$n = trim( (string) flxa_setting( 'pwa_short' ) );
	if ( '' !== $n ) {
		return $n;
	}
	$b = flxa_brand();
	if ( ! function_exists( 'mb_strlen' ) || mb_strlen( $b ) <= 12 ) {
		return $b;
	}
	// Kelime ortasından kesme: sığan kelimeler, o da olmazsa baş harfler (Örnek Vakfı → ÖV).
	$out = '';
	foreach ( preg_split( '/\s+/u', $b ) as $w ) {
		$try = trim( $out . ' ' . $w );
		if ( mb_strlen( $try ) > 14 ) {
			break;
		}
		$out = $try;
	}
	if ( '' !== $out && $out !== $b ) {
		return $out;
	}
	$ini = '';
	foreach ( preg_split( '/\s+/u', $b ) as $w ) {
		$ini .= mb_strtoupper( mb_substr( $w, 0, 1 ) );
	}
	return mb_substr( $ini, 0, 6 );
}

/** Simgeler: site simgesi varsa o, yoksa paketteki Flux Admin simgesi. */
function flxa_pwa_icons() {
	$icons = array();
	$id    = (int) get_option( 'site_icon' );
	if ( $id ) {
		foreach ( array( 192, 512 ) as $s ) {
			$u = wp_get_attachment_image_url( $id, array( $s, $s ) );
			if ( $u ) {
				$icons[] = array(
					'src'     => $u,
					'sizes'   => $s . 'x' . $s,
					'type'    => (string) get_post_mime_type( $id ),
					'purpose' => 'any',
				);
			}
		}
	}
	if ( ! $icons ) {
		$icons[] = array( 'src' => FLXA_URL . 'assets/icons/icon-192.png?v=' . FLXA_VERSION, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' );
		$icons[] = array( 'src' => FLXA_URL . 'assets/icons/icon-512.png?v=' . FLXA_VERSION, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' );
	}
	$icons[] = array( 'src' => FLXA_URL . 'assets/icons/maskable-512.png?v=' . FLXA_VERSION, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' );
	return $icons;
}

function flxa_pwa_manifest() {
	if ( ! flxa_pwa_on() ) {
		status_header( 404 );
		exit;
	}
	$admin = admin_url();
	$path  = (string) wp_parse_url( $admin, PHP_URL_PATH );
	$short = array(
		array( 'name' => __( 'Dashboard', 'flx-admin' ), 'url' => $admin ),
	);
	if ( defined( 'FLXC_VERSION' ) ) {
		$short[] = array( 'name' => __( 'Messages', 'flx-admin' ), 'url' => admin_url( 'admin.php?page=flx-chat' ) );
	}
	if ( defined( 'FLXG_VERSION' ) ) {
		$short[] = array( 'name' => __( 'Tasks', 'flx-admin' ), 'url' => admin_url( 'admin.php?page=flx-tasks' ) );
	}
	if ( function_exists( 'flxb_table' ) ) {
		$short[] = array( 'name' => __( 'Donations', 'flx-admin' ), 'url' => admin_url( 'admin.php?page=flxb-donations' ) );
	}
	$m = array(
		'id'               => $path,
		'name'             => flxa_pwa_name(),
		'short_name'       => flxa_pwa_short(),
		'description'      => __( 'Admin dashboard', 'flx-admin' ),
		'lang'             => str_replace( '_', '-', get_locale() ),
		'dir'              => is_rtl() ? 'rtl' : 'ltr',
		// Açılışta service worker önce önbellekteki hafif açılış ekranını hemen gösterir, panel arkada yüklenir.
		'start_url'        => add_query_arg( 'flxa-start', '1', $admin ),
		// Kapsam sitenin kökü: wp-login.php ve Google girişinin dönüş adresi uygulamanın içinde açılır.
		// /wp-admin/ olunca iOS giriş sayfasını ayrı bir tarayıcı katmanında açıyor, oturum çerezi uygulamaya
		// ancak yeniden açılınca geçiyordu (giriş → yine giriş sayfası döngüsü).
		'scope'            => (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ),
		'display'          => 'standalone',
		'display_override' => array( 'window-controls-overlay', 'standalone' ),
		'orientation'      => 'any',
		'background_color' => '#F3F2EF',
		'theme_color'      => flxa_accent(),
		'icons'            => flxa_pwa_icons(),
		'shortcuts'        => (array) apply_filters( 'flxa_pwa_shortcuts', $short ),
	);
	nocache_headers();
	header( 'Content-Type: application/manifest+json; charset=utf-8' );
	header( 'X-LiteSpeed-Cache-Control: no-cache' );
	echo wp_json_encode( apply_filters( 'flxa_pwa_manifest', $m ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}

function flxa_pwa_sw() {
	header( 'Content-Type: application/javascript; charset=utf-8' );
	header( 'Service-Worker-Allowed: ' . (string) wp_parse_url( admin_url(), PHP_URL_PATH ) );
	header( 'X-LiteSpeed-Cache-Control: no-cache' );
	if ( ! flxa_pwa_on() ) {
		// Kapatıldıysa kendini kaldıran işçi: eski kayıtlar temizlenir.
		header( 'Cache-Control: no-cache' );
		echo "self.addEventListener('install',function(){self.skipWaiting();});self.addEventListener('activate',function(e){e.waitUntil(caches.keys().then(function(k){return Promise.all(k.filter(function(n){return n.indexOf('flxa-')===0;}).map(function(n){return caches.delete(n);}));}).then(function(){return self.registration.unregister();}));});";
		exit;
	}
	// Tarayıcı güncellemeyi en fazla günde bir kez sorar (updateViaCache: 'all'); sürüm değişince adres değişir.
	header( 'Cache-Control: public, max-age=86400' );
	$cfg = array(
		'cache'   => 'flxa-' . FLXA_VERSION,
		'offline' => add_query_arg( array( 'action' => 'flxa_offline', 'v' => FLXA_VERSION ), admin_url( 'admin-ajax.php' ) ),
		'splash'  => add_query_arg( array( 'action' => 'flxa_splash', 'v' => FLXA_VERSION ), admin_url( 'admin-ajax.php' ) ),
		'pre'     => array(
			FLXA_URL . 'assets/css/admin.css?ver=' . FLXA_VERSION,
			FLXA_URL . 'assets/js/admin.js?ver=' . FLXA_VERSION,
			FLXA_URL . 'assets/icons/icon-192.png?v=' . FLXA_VERSION,
		),
		'static'  => array( '/wp-includes/', '/wp-admin/css/', '/wp-admin/js/', '/wp-content/plugins/', '/wp-content/themes/' ),
	);
	echo 'var FLXA_SW=' . wp_json_encode( $cfg, JSON_UNESCAPED_SLASHES ) . ";\n";
	readfile( FLXA_DIR . 'assets/js/sw.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

function flxa_pwa_offline() {
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	$accent = flxa_accent();
	?>
<!doctype html>
<html lang="<?php echo esc_attr( str_replace( '_', '-', get_locale() ) ); ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php esc_html_e( 'No connection', 'flx-admin' ); ?></title>
<style>
:root{color-scheme:light dark;--a:<?php echo esc_html( $accent ); ?>}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font:16px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;background:#F3F2EF;color:#16181D;padding:24px;box-sizing:border-box}
@media (prefers-color-scheme:dark){body{background:#101114;color:#E8E8EA}.c{background:#191B20!important;border-color:#2B2D33!important}}
.c{max-width:380px;text-align:center;background:#fff;border:1px solid #E4E2DD;border-radius:20px;padding:32px 24px}
img{width:64px;height:64px;border-radius:16px}h1{font-size:22px;margin:16px 0 8px}p{margin:0 0 20px;opacity:.8}
button{font:inherit;font-weight:700;border:0;border-radius:12px;padding:12px 20px;background:var(--a);color:#fff;cursor:pointer}
</style></head>
<body><div class="c"><img src="<?php echo esc_url( FLXA_URL . 'assets/icons/icon-192.png?v=' . FLXA_VERSION ); ?>" alt="">
<h1><?php esc_html_e( 'No connection', 'flx-admin' ); ?></h1>
<p><?php esc_html_e( 'The dashboard reopens where you left off when the connection is back.', 'flx-admin' ); ?></p>
<button type="button" onclick="location.reload()"><?php esc_html_e( 'Try again', 'flx-admin' ); ?></button></div></body></html>
	<?php
	exit;
}

/**
 * Açılış ekranı: uygulama simgesine basınca anında (önbellekten) görünür, ilerleme çubuğuyla asıl sayfaya geçer.
 * Kişisel veri içermez; önbelleğe alınabilir.
 */
function flxa_pwa_splash() {
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Cache-Control: public, max-age=86400' );
	$accent = flxa_accent();
	$id     = (int) get_option( 'site_icon' );
	$icon   = $id ? wp_get_attachment_image_url( $id, array( 192, 192 ) ) : '';
	?>
<!doctype html>
<html lang="<?php echo esc_attr( str_replace( '_', '-', get_locale() ) ); ?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title><?php echo esc_html( flxa_pwa_name() ); ?></title>
<meta name="theme-color" content="#FFFFFF" media="(prefers-color-scheme: light)"><meta name="theme-color" content="#14161A" media="(prefers-color-scheme: dark)">
<style>
:root{color-scheme:light dark;--a:<?php echo esc_html( $accent ); ?>}
html,body{margin:0;height:100%;background:#F3F2EF;color:#16181D;font:600 15px/1.4 system-ui,-apple-system,"Segoe UI",sans-serif}
@media (prefers-color-scheme:dark){html,body{background:#101114;color:#E8E8EA}}
body{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px}
img{width:72px;height:72px;border-radius:18px;animation:p 1.6s ease-in-out infinite}
@keyframes p{50%{transform:scale(.94);opacity:.85}}
.b{position:fixed;top:0;left:0;right:0;height:3px;background:var(--a);transform-origin:0 50%;animation:g 8s cubic-bezier(.08,.75,.2,1) forwards}
@keyframes g{0%{transform:scaleX(0)}6%{transform:scaleX(.3)}100%{transform:scaleX(.94)}}
</style></head>
<body><div class="b"></div><img src="<?php echo esc_url( $icon ? $icon : FLXA_URL . 'assets/icons/icon-192.png?v=' . FLXA_VERSION ); ?>" alt=""><div><?php echo esc_html( flxa_pwa_name() ); ?></div>
<script>(function(){var u=new URL(location.href);u.searchParams.delete('flxa-start');location.replace(u.toString());})();</script></body></html>
	<?php
	exit;
}

function flxa_pwa_head() {
	if ( ! flxa_pwa_on() ) {
		return;
	}
	$manifest = add_query_arg( 'action', 'flxa_manifest', admin_url( 'admin-ajax.php' ) );
	echo '<link rel="manifest" href="' . esc_url( $manifest ) . '">' . "\n";
	echo '<meta name="theme-color" content="#FFFFFF" media="(prefers-color-scheme: light)">' . "\n";
	echo '<meta name="theme-color" content="#14161A" media="(prefers-color-scheme: dark)">' . "\n";
	echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
	echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
	echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr( flxa_pwa_short() ) . '">' . "\n";
	$id = (int) get_option( 'site_icon' );
	$ap = $id ? wp_get_attachment_image_url( $id, array( 180, 180 ) ) : '';
	echo '<link rel="apple-touch-icon" href="' . esc_url( $ap ? $ap : FLXA_URL . 'assets/icons/apple-touch-icon.png?v=' . FLXA_VERSION ) . '">' . "\n";
}

/**
 * Uygulama kapanınca oturum düşmesin: "Beni hatırla" işaretlenmemiş girişler (Google ile giriş dahil)
 * tarayıcı oturumu çerezi alır ve iOS uygulamayı kapatınca onu siler. Uygulamadan yapılan girişte kalıcı çerez yazılır.
 */
function flxa_pwa_keep_login( $login, $user ) {
	// Yalnız uygulamadan (PWA) yapılan girişler: tarayıcıda kalıcı oturum, admin'e sitenin önbelleksiz (LiteSpeed)
	// sayfalarını gösterip ilk açılışı yavaşlatıyordu. flxa_app çerezini uygulama içindeki sayfalar yazar.
	$app = ! empty( $_COOKIE['flxa_app'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! $app || ! flxa_pwa_on() || ! ( $user instanceof WP_User ) || ! apply_filters( 'flxa_pwa_keep_login', true, $user ) ) {
		return;
	}
	wp_set_auth_cookie( $user->ID, true );
}

/** Giriş sayfası bu adresle "oturum açıldı mı" diye sorar; çerez gelmişse panele geçer. */
function flxa_pwa_auth() {
	nocache_headers();
	header( 'X-LiteSpeed-Cache-Control: no-cache' );
	wp_send_json( array( 'in' => is_user_logged_in() ) );
}

/**
 * Uygulama (standalone) içindeki giriş sayfası: Google girişi ayrı pencerede bitince oturum çerezi bu sayfaya
 * geç ulaşabiliyor. Sayfa öne gelince ve dış girişe tıklandıktan sonra 2 dakika boyunca sorulur
 * (hemen, sonra 2 sn; yanıt değişmezse 15 sn'ye kadar seyrekleşir). Gizli sekmede durur, dönünce hemen sorar.
 * Hata veya HTTP 429 katlanarak en çok 60 sn bekler. Aynı anda tek istek vardır. Oturum açıksa panele geçilir.
 * Uygulama dışındaki tarayıcıda hiçbir şey yapmaz.
 */
function flxa_pwa_login_watch() {
	if ( ! flxa_pwa_on() ) {
		return;
	}
	$to = isset( $_GET['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_GET['redirect_to'] ), admin_url() ) : admin_url(); // phpcs:ignore WordPress.Security
	$cfg = array(
		'u'  => add_query_arg( 'action', 'flxa_auth', admin_url( 'admin-ajax.php' ) ),
		'to' => $to,
	);
	?>
<script>(function(c){
var sa=(window.matchMedia&&matchMedia('(display-mode: standalone)').matches)||navigator.standalone;
if(sa){try{document.cookie='flxa_app=1; path=/; max-age=31536000; samesite=lax'+(location.protocol==='https:'?'; secure':'');}catch(e){}}
if(!sa||/[?&]action=(logout|lostpassword|rp|resetpass|register)/.test(location.search))return;
var busy=0,until=0,t=0,quiet=0,fails=0;
function delay(){if(fails)return Math.min(60000,2000*Math.pow(2,Math.min(6,fails)));return Math.min(15000,2000+Math.min(quiet,3)*4500);}
function arm(ms){if(t)clearTimeout(t);t=0;if(document.hidden||Date.now()>until)return;t=setTimeout(ask,ms);}
function ask(){if(t){clearTimeout(t);t=0;}if(busy||document.hidden)return;busy=1;
fetch(c.u+'&_='+Date.now(),{credentials:'same-origin',cache:'no-store'}).then(function(r){if(!r.ok)throw r;return r.json();}).then(function(j){fails=0;if(j&&j.in){location.replace(c.to);return;}var d=delay();quiet=Math.min(6,quiet+1);return d;}).catch(function(){fails=Math.min(8,fails+1);return delay();}).then(function(d){busy=0;if(d&&!document.hidden&&Date.now()<=until)arm(d);});}
function poll(now){until=Date.now()+120000;quiet=0;fails=0;if(busy)return;arm(now?0:delay());}
document.addEventListener('visibilitychange',function(){if(document.hidden){if(t){clearTimeout(t);t=0;}return;}poll(1);});
window.addEventListener('pageshow',ask);window.addEventListener('focus',ask);
document.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a,button');if(a&&!a.closest('#loginform'))poll();},true);
})(<?php echo wp_json_encode( $cfg, JSON_UNESCAPED_SLASHES ); ?>);</script>
	<?php
}

/** FluxWP Panel'in Güncellemeler ekranında logo. */
function flxa_package_icon( $icons, $slug ) {
	if ( 'flx-admin' === $slug && empty( $icons ) ) {
		$u     = FLXA_URL . 'assets/icon.svg?ver=' . FLXA_VERSION;
		$icons = array( 'svg' => $u, '1x' => $u, '2x' => $u );
	}
	return $icons;
}
