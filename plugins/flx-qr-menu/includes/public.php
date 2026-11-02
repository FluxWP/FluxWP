<?php
/**
 * Menüleri bu sitede yayınlar: /<adres>/, /<adres>/tesekkur/, alt alan adının kökü ve (isteğe bağlı) ana sayfa.
 *
 * İstek `do_parse_request` aşamasında, yalnız `flxq_routes` seçeneğine bakılarak yakalanır (sorgu yok, rewrite flush yok).
 * Sayfa qr-menu'nün şablonuyla (public/menu.html, public/tesekkur.html) üretilir ve önbelleğe alınmaz:
 * menü kaydedilince ziyaretçinin açık sayfası /rev ucunu yoklayarak kendini yeniler.
 *
 * @package FLX_QR_Menu
 */

defined( 'ABSPATH' ) || exit;

const FLXQ_POLL = 20;

add_filter( 'do_parse_request', 'flxq_public_route', 1, 3 );
add_action( 'rest_api_init', 'flxq_register_rev_route' );
add_filter(
	'rest_post_dispatch',
	function ( $res, $server, $req ) {
		if ( $res instanceof WP_REST_Response && flxq_is_rev_request( $req ) ) {
			flxq_nocache_headers( $res, 'flx-qr-menu rev' );
		}
		return $res;
	},
	20,
	3
);

/* ---------- Yönlendirme ---------- */

/** İstek yolunu (sitenin alt klasörü çıkarılmış, baştaki/sondaki / olmadan) ve sondaki /'ı döndürür. */
function flxq_request_path() {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	$path = rawurldecode( $path );
	$home = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	$p    = ltrim( $path, '/' );
	if ( '' !== $home ) {
		if ( 0 !== strpos( $p . '/', $home . '/' ) ) {
			return null;
		}
		$p = (string) substr( $p, strlen( $home ) );
	}
	$slash = '' === $p || '/' === substr( $path, -1 );
	return array( strtolower( trim( $p, '/' ) ), $slash );
}

/** Ana sayfa ve alt alan adı kökünde yalnız bu sorgu parametreleriyle menü gösterilir (?rest_route=, ?p= gibi istekler WordPress'e kalır). */
function flxq_plain_query() {
	foreach ( array_keys( $_GET ) as $k ) { // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $k, array( 'menu', 'qr', 'ref', 'fbclid', 'gclid' ), true ) && 0 !== strpos( (string) $k, 'utm_' ) ) {
			return false;
		}
	}
	return true;
}

function flxq_request_host() {
	$h = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( (string) wp_unslash( $_SERVER['HTTP_HOST'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return preg_replace( '/:\d+$/', '', $h );
}

/**
 * @param bool         $do    WordPress isteği çözsün mü.
 * @param WP           $wp    WP nesnesi.
 * @param array|string $extra Ek sorgu değişkenleri.
 */
function flxq_public_route( $do, $wp, $extra ) {
	if ( ! $do || is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! empty( $extra ) ) {
		return $do;
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( 'GET' !== $method && 'HEAD' !== $method ) {
		return $do;
	}
	$req = flxq_request_path();
	if ( null === $req ) {
		return $do;
	}
	list( $path, $slash ) = $req;
	$routes               = flxq_routes();
	$host                 = flxq_request_host();
	$seg                  = '' === $path ? array() : explode( '/', $path );

	// Alt alan adı: kök menü, /tesekkur/ teşekkür sayfası.
	if ( '' !== $host && isset( $routes['hosts'][ $host ] ) && $host !== flxq_home_host() ) {
		$id   = (int) $routes['hosts'][ $host ];
		$base = ( is_ssl() ? 'https' : 'http' ) . '://' . $host . trailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
		if ( ! $seg && flxq_plain_query() ) {
			flxq_serve( $id, 'menu', $base );
		}
		if ( array( 'tesekkur' ) === $seg ) {
			flxq_serve_or_slash( $id, 'thanks', $base, $slash );
		}
		return $do;
	}

	// Yol: /menu/ ve /menu/tesekkur/.
	if ( $seg && isset( $routes['slugs'][ $seg[0] ] ) && count( $seg ) <= 2 ) {
		$id   = (int) $routes['slugs'][ $seg[0] ];
		$base = home_url( '/' . $seg[0] . '/' );
		if ( 1 === count( $seg ) ) {
			flxq_serve_or_slash( $id, 'menu', $base, $slash );
		}
		if ( 'tesekkur' === $seg[1] ) {
			flxq_serve_or_slash( $id, 'thanks', $base, $slash );
		}
		return $do;
	}

	// Ana sayfa.
	$s = flxq_settings();
	if ( 'blank' === $s['front_mode'] || ! $s['front_menu'] || ! flxq_get_post( $s['front_menu'] ) || 'publish' !== get_post_status( $s['front_menu'] ) ) {
		return $do;
	}
	if ( ! $seg && flxq_plain_query() ) {
		if ( 'redirect' === $s['front_mode'] ) {
			$urls = array_values(
				array_filter(
					flxq_menu_urls( $s['front_menu'] ),
					function ( $u ) {
						return 'front' !== $u['type'];
					}
				)
			);
			if ( $urls ) {
				usort(
					$urls,
					function ( $a, $b ) {
						return ( 'slug' === $b['type'] ) - ( 'slug' === $a['type'] );
					}
				);
				flxq_nocache_raw();
				$qs = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				wp_redirect( $urls[0]['url'] . ( '' !== $qs ? '?' . $qs : '' ), 302, 'Flux QR Menu' ); // phpcs:ignore WordPress.Security.SafeRedirect -- menünün kendi adresi.
				exit;
			}
			return $do;
		}
		flxq_serve( (int) $s['front_menu'], 'menu', home_url( '/' ) );
	}
	if ( 'menu' === $s['front_mode'] && array( 'tesekkur' ) === $seg ) {
		flxq_serve_or_slash( (int) $s['front_menu'], 'thanks', home_url( '/' ), $slash );
	}
	return $do;
}

function flxq_serve_or_slash( $id, $page, $base, $slash ) {
	if ( ! $slash ) {
		$to  = 'thanks' === $page ? $base . 'tesekkur/' : $base;
		$qs  = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$url = $to . ( '' !== $qs ? '?' . $qs : '' );
		wp_redirect( $url, 301, 'Flux QR Menu' ); // phpcs:ignore WordPress.Security.SafeRedirect -- aynı site ya da menünün kendi alt alan adı.
		exit;
	}
	flxq_serve( $id, $page, $base );
}

/** Sayfa önbelleği (LiteSpeed, Hostinger, CDN) ve tarayıcı önbelleği kapalı. */
function flxq_nocache_raw() {
	do_action( 'litespeed_control_set_nocache', 'flx-qr-menu public' );
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	if ( headers_sent() ) {
		return;
	}
	nocache_headers();
	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
	header( 'X-LiteSpeed-Cache-Control: no-cache' );
	header( 'CDN-Cache-Control: no-store' );
}

/** Menüyü ya da teşekkür sayfasını gönderir ve isteği bitirir. */
function flxq_serve( $id, $page, $base ) {
	$post = flxq_get_post( $id );
	flxq_nocache_raw();
	if ( ! headers_sent() ) {
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	}
	if ( ! $post || 'publish' !== $post->post_status || ! flxq_menu_enabled( $id ) ) {
		status_header( 404 );
		$html = flxq_render_off( $post ? (int) $post->ID : 0 );
	} else {
		status_header( 200 );
		$html = 'thanks' === $page ? flxq_render_thanks( $id, $base ) : flxq_render_menu( $id, $base );
	}
	if ( 'HEAD' !== strtoupper( (string) ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'GET' ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- şablon + kaçışlı JSON/meta.
	}
	exit;
}

/* ---------- Sayfa üretimi (qr-menu lib/core.php ile aynı) ---------- */

function flxq_public_template( $name ) {
	return (string) file_get_contents( FLXQ_DIR . 'public/' . $name ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

function flxq_set_meta( $html, $attr, $name, $value ) {
	$re = '#(<meta ' . $attr . '="' . preg_quote( $name, '#' ) . '" content=")[^"]*(")#';
	return preg_replace_callback(
		$re,
		function ( $m ) use ( $value ) {
			return $m[1] . esc_attr( $value ) . $m[2];
		},
		$html,
		1
	);
}

/** "9 Ekim", "10 Ekim", "11 Ekim" → "9-10-11 Ekim" (aynı ay ise). */
function flxq_compact_days( array $labels ) {
	$labels = array_values( $labels );
	if ( ! $labels ) {
		return '';
	}
	$nums  = array();
	$month = null;
	foreach ( $labels as $l ) {
		if ( ! preg_match( '/^(\d{1,2})\s+(\S+)$/u', trim( $l ), $m ) || ( null !== $month && $month !== $m[2] ) ) {
			return implode( ', ', $labels );
		}
		$month  = $m[2];
		$nums[] = $m[1];
	}
	return implode( '-', $nums ) . ' ' . $month;
}

/** Başlık + gün etiketleri, ör. "Yenikapı Tanıtım Günleri Menüsü · 9-10-11 Ekim". Metinler şablonla aynı dilde (Türkçe). */
function flxq_titles( array $d ) {
	$e      = isset( $d['etkinlik'] ) && is_array( $d['etkinlik'] ) ? $d['etkinlik'] : array();
	$baslik = isset( $e['baslik'] ) ? trim( (string) $e['baslik'] ) : '';
	$gunler = array();
	foreach ( isset( $e['gunler'] ) && is_array( $e['gunler'] ) ? $e['gunler'] : array() as $g ) {
		$gunler[] = is_array( $g ) ? ( isset( $g['etiket'] ) ? (string) $g['etiket'] : '' ) : (string) $g;
	}
	$gun     = flxq_compact_days( array_filter( $gunler ) );
	$urunler = array();
	foreach ( isset( $d['kategoriler'] ) ? (array) $d['kategoriler'] : array() as $cat ) {
		foreach ( isset( $cat['urunler'] ) && is_array( $cat['urunler'] ) ? $cat['urunler'] : array() as $it ) {
			if ( ! empty( $it['ad'] ) && empty( $it['gizli'] ) && count( $urunler ) < 5 ) {
				$urunler[] = $it['ad'];
			}
		}
	}
	$menu = '' !== $baslik ? $baslik . ' Menüsü' : 'Menü';
	$desc = trim( ( '' !== $gun ? $gun . ' ' : '' ) . ( '' !== $baslik ? $baslik . ' menüsü' : 'Menü' ) . ( $urunler ? ': ' . implode( ', ', $urunler ) . '.' : '.' ) );
	return array(
		'title'  => $menu,
		'og'     => $menu . ( '' !== $gun ? ' · ' . $gun : '' ),
		'desc'   => $desc,
		'baslik' => $baslik,
	);
}

/**
 * Göreli görsel yolu (img/x.jpg): eklentinin public/img/ klasöründe ya da örnek menü görsellerinde (assets/sample/img/)
 * varsa oradan, yoksa görsel gösterilmez.
 * Tam adresler (https://…, medya kütüphanesi) olduğu gibi kalır.
 */
function flxq_public_image( $g ) {
	$g = trim( (string) $g );
	if ( '' === $g || preg_match( '#^(https?:)?//#i', $g ) ) {
		return $g;
	}
	$rel = ltrim( preg_replace( '#^\./#', '', $g ), '/' );
	if ( false !== strpos( $rel, '..' ) || ! preg_match( '#^img/[A-Za-z0-9._/-]+$#', $rel ) ) {
		return '';
	}
	if ( is_file( FLXQ_DIR . 'public/' . $rel ) ) {
		return FLXQ_URL . 'public/' . $rel;
	}
	$bundled = flxq_bundled_image( $rel );
	return '' !== $bundled ? flxq_bundled_image_url( $bundled ) : '';
}

/** Şablona girecek menü verisi (görsel yolları çözülmüş). */
function flxq_public_data( $id ) {
	return flxq_public_data_from( flxq_menu_data( $id, true ) );
}

/**
 * Verilen menü verisinden (dizi) şablon verisi: kayıtlı menü ve önizleme aynı yoldan geçer.
 *
 * @param mixed $d json_decode( $json, true ) sonucu.
 */
function flxq_public_data_from( $d ) {
	$d = is_array( $d ) ? $d : array( 'etkinlik' => array(), 'kategoriler' => array() );
	foreach ( isset( $d['kategoriler'] ) && is_array( $d['kategoriler'] ) ? array_keys( $d['kategoriler'] ) : array() as $ci ) {
		if ( empty( $d['kategoriler'][ $ci ]['urunler'] ) || ! is_array( $d['kategoriler'][ $ci ]['urunler'] ) ) {
			continue;
		}
		foreach ( array_keys( $d['kategoriler'][ $ci ]['urunler'] ) as $ui ) {
			if ( isset( $d['kategoriler'][ $ci ]['urunler'][ $ui ]['gorsel'] ) ) {
				$d['kategoriler'][ $ci ]['urunler'][ $ui ]['gorsel'] = flxq_public_image( $d['kategoriler'][ $ci ]['urunler'][ $ui ]['gorsel'] );
			}
		}
	}
	return $d;
}

/** Şablondaki göreli simge ve paylaşım görseli adreslerini eklentinin public/ klasörüne çevirir. */
function flxq_public_assets( $html ) {
	$pub = FLXQ_URL . 'public/';
	return str_replace(
		array( 'href="favicon-32.png"', 'href="apple-touch-icon.png"' ),
		array( 'href="' . esc_url( $pub . 'favicon-32.png' ) . '"', 'href="' . esc_url( $pub . 'apple-touch-icon.png' ) . '"' ),
		$html
	);
}

/** Aynı kökenli yoklama adresi (alt alan adında da çalışsın diye göreli, ?rest_route= biçiminde). */
function flxq_rev_url( $id ) {
	$path = trailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
	return $path . '?rest_route=/' . FLXQ_NS . '/rev/' . (int) $id;
}

/**
 * Canlı güncelleme betiği: sekme görünürken 20 sn'de bir (`flxq_poll_seconds` filtresi) yoklar, hata olursa bekleme ikiye katlanır (en çok 5 dk).
 * Menü değişince (açık pencere/oyun yoksa) kaydırma konumu korunarak yenilenir.
 *
 * @param array $cfg id, v (sürüm), to (değişince gidilecek adres; boşsa yenile), off (yayında değil sayfası).
 */
function flxq_live_script( array $cfg ) {
	$poll = max( 5, (int) apply_filters( 'flxq_poll_seconds', FLXQ_POLL ) );
	$cfg  = wp_json_encode( array_merge( array( 'p' => $poll, 'u' => flxq_rev_url( $cfg['id'] ) ), $cfg ), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );
	return <<<HTML
<script>
/* Canlı güncelleme: menü değişince sayfa kaydırma konumu korunarak yenilenir. */
(()=>{const C={$cfg};let bekle=C.p*1000,t=0,mesgul=false;
try{const y=sessionStorage.getItem('flxqY');if(y!==null){sessionStorage.removeItem('flxqY');addEventListener('load',()=>scrollTo(0,+y),{once:true})}}catch(_){}
const bos=()=>{const d=document.getElementById('dlg'),o=document.getElementById('oyun');return !(d&&d.open)&&!(o&&!o.hidden)};
function uygula(){if(!bos()){setTimeout(uygula,3000);return}try{sessionStorage.setItem('flxqY',scrollY)}catch(_){}C.to?location.replace(C.to+location.search):location.reload()}
function kur(ms){clearTimeout(t);t=setTimeout(yokla,ms)}
function yokla(){if(document.hidden||mesgul)return;mesgul=true;
  fetch(C.u+'&_t='+Date.now(),{cache:'no-store',credentials:'omit',headers:{'If-None-Match':'"flxq-rev-'+C.id+'-'+C.v+'"'}}).then(r=>{
    if(r.status===304){bekle=C.p*1000;return}
    if(r.status===404)return r.json().then(j=>{bekle=C.p*1000;if(j&&j.code==='flxq_menu_off'&&!C.off)uygula()});
    if(!r.ok)throw 0;return r.json().then(j=>{bekle=C.p*1000;if(j&&j.v!==undefined&&(C.off||String(j.v)!==String(C.v))){C.v=j.v;uygula()}})})
  .catch(()=>{bekle=Math.min(bekle*2,300000)}).finally(()=>{mesgul=false;kur(bekle)})}
document.addEventListener('visibilitychange',()=>{if(!document.hidden)kur(300)});kur(bekle)})();
</script>
HTML;
}

function flxq_render_menu( $id, $base ) {
	return flxq_render_menu_html( flxq_public_data( $id ), $base, array( 'id' => (int) $id, 'v' => flxq_menu_version( $id ) ) );
}

/**
 * Menü sayfasının HTML'i (yayındaki sayfa ve yönetimdeki canlı önizleme ortak).
 *
 * @param array  $d    flxq_public_data_from() çıktısı.
 * @param string $base Menünün adresi (teşekkür sayfası ve og:url).
 * @param array  $live Canlı güncelleme betiği ayarı (id, v); 'preview' => true ise önizleme:
 *                     yoklama betiği yok, teşekkür sayfasına geçiş yok (?menu gibi), bağlantılar çerçevede tıklanmaz.
 */
function flxq_render_menu_html( array $d, $base, array $live ) {
	$tpl  = flxq_public_template( 'menu.html' );
	$t    = flxq_titles( $d );
	$og   = FLXQ_URL . 'public/og-menu.jpg';
	$json = wp_json_encode( $d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );
	$html = preg_replace_callback(
		'#(<script type="application/json" id="menu-data">)(.*?)(</script>)#s',
		function ( $m ) use ( $json ) {
			return $m[1] . "\n" . $json . "\n" . $m[3];
		},
		$tpl,
		1
	);
	$html = flxq_set_meta( $html, 'name', 'description', $t['desc'] );
	$html = flxq_set_meta( $html, 'property', 'og:title', $t['og'] );
	$html = flxq_set_meta( $html, 'property', 'og:description', $t['desc'] );
	$html = flxq_set_meta( $html, 'property', 'og:url', $base );
	$html = flxq_set_meta( $html, 'property', 'og:image', $og );
	$html = flxq_set_meta( $html, 'property', 'og:image:alt', $t['og'] );
	$html = flxq_set_meta( $html, 'name', 'twitter:title', $t['og'] );
	$html = flxq_set_meta( $html, 'name', 'twitter:description', $t['desc'] );
	$html = flxq_set_meta( $html, 'name', 'twitter:image', $og );
	$html = preg_replace( '#<title>.*?</title>#s', '<title>' . esc_html( $t['title'] ) . '</title>', $html, 1 );
	$html = str_replace( 'location.replace("tesekkur.html")', 'location.replace(' . wp_json_encode( $base . 'tesekkur/', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ) . ')', $html );
	$html = flxq_public_assets( $html );
	if ( ! empty( $live['preview'] ) ) {
		$html = str_replace( 'if(/[?&]menu(=|&|$)/.test(location.search))return;', 'return;', $html );
		$html = str_replace( ' type="button" autofocus>', ' type="button">', $html ); // Çerçevede autofocus engellenir, konsola hata yazar.
		return str_replace( '</body>', flxq_preview_script() . "\n</body>", $html );
	}
	return str_replace( '</body>', flxq_live_script( $live ) . "\n</body>", $html );
}

/**
 * Önizleme çerçevesindeki (sandbox, srcdoc) yardımcı: kaydırma konumunu üst pencereye bildirir, istenen konuma kayar,
 * sayfa dışına giden bağlantıları ve formları etkisiz bırakır.
 */
function flxq_preview_script() {
	return <<<'HTML'
<script>
/* Flux QR Menu önizlemesi */
(()=>{const P=parent,say=(m)=>{try{P.postMessage(Object.assign({flxqPreview:1},m),'*')}catch(_){}};let t=0;
addEventListener('scroll',()=>{clearTimeout(t);t=setTimeout(()=>say({y:scrollY}),80)},{passive:true});
addEventListener('message',e=>{const d=e.data;if(e.source!==P||!d||!d.flxqScroll)return;scrollTo(0,+d.y||0);requestAnimationFrame(()=>say({ready:1,y:scrollY}))});
document.addEventListener('click',e=>{const a=e.target.closest&&e.target.closest('a[href]');if(a&&(a.getAttribute('href')||'').charAt(0)!=='#')e.preventDefault()},true);
document.addEventListener('submit',e=>e.preventDefault(),true);
say({loaded:1})})();
</script>
HTML;
}

function flxq_render_thanks( $id, $base ) {
	$html = flxq_public_template( 'tesekkur.html' );
	$d    = flxq_public_data( $id );
	$t    = flxq_titles( $d );
	$e    = isset( $d['etkinlik'] ) && is_array( $d['etkinlik'] ) ? $d['etkinlik'] : array();
	if ( '' !== $t['baslik'] ) {
		$html = str_replace( 'Yenikapı Tanıtım Günleri', esc_html( $t['baslik'] ), $html );
	}
	$html = flxq_set_meta( $html, 'property', 'og:image', FLXQ_URL . 'public/og-menu.jpg' );
	if ( ! empty( $e['site'] ) ) {
		$site = preg_match( '#^https?://#i', $e['site'] ) ? $e['site'] : 'https://' . $e['site'];
		$html = str_replace( 'href="https://example.org"', 'href="' . esc_url( $site ) . '"', $html );
	}
	$html = flxq_public_assets( $html );
	// Kapanış kaldırılınca ziyaretçi menüye döner.
	return str_replace( '</body>', flxq_live_script( array( 'id' => (int) $id, 'v' => flxq_menu_version( $id ), 'to' => $base ) ) . "\n</body>", $html );
}

/** Yayında olmayan menü: 404 durumlu sade sayfa; menü yeniden açılınca kendini yeniler. */
function flxq_render_off( $id ) {
	$title = __( 'Menü yayında değil', 'flx-qr-menu' );
	$text  = __( 'Bu menü şu anda yayında değil. Menü yeniden açıldığında bu sayfa kendiliğinden yenilenir.', 'flx-qr-menu' );
	$live  = $id ? flxq_live_script( array( 'id' => (int) $id, 'v' => 0, 'off' => true ) ) : '';
	$icon  = esc_url( FLXQ_URL . 'public/favicon-32.png' );
	$lang  = esc_attr( str_replace( '_', '-', get_locale() ) );
	return '<!doctype html><html lang="' . $lang . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><meta name="color-scheme" content="light dark"><title>' . esc_html( $title ) . '</title><link rel="icon" href="' . $icon . '">'
		. '<style>html,body{margin:0;height:100%}body{display:grid;place-items:center;font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#fff;color:#14324a;text-align:center;padding:24px;box-sizing:border-box}main{max-width:420px}h1{font-size:22px;margin:12px 0 8px}p{color:#4a5b66;margin:0}.i{font-size:44px}@media (prefers-color-scheme:dark){body{background:#0f1a14;color:#e6efe9}p{color:#a9bcb1}}</style></head>'
		. '<body><main><div class="i" aria-hidden="true">🌱</div><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $text ) . '</p></main>' . $live . '</body></html>';
}

/* ---------- Canlı güncelleme ucu ---------- */

function flxq_register_rev_route() {
	register_rest_route(
		FLXQ_NS,
		'/rev/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'flxq_rest_rev',
			'permission_callback' => 'flxq_rev_permission',
		)
	);
}

/** Yalnız yayındaki (etkin) menülerin sürümü okunabilir; diğerleri 404 flxq_menu_off. */
function flxq_rev_permission( WP_REST_Request $req ) {
	$id   = (int) $req['id'];
	$post = flxq_get_post( $id );
	if ( ! $post || 'publish' !== $post->post_status || ! flxq_menu_enabled( $id ) ) {
		return new WP_Error( 'flxq_menu_off', __( 'Menü yayında değil.', 'flx-qr-menu' ), array( 'status' => 404 ) );
	}
	return true;
}

function flxq_rest_rev( WP_REST_Request $req ) {
	$id   = (int) $req['id'];
	$v    = flxq_menu_version( $id );
	$etag = '"flxq-rev-' . $id . '-' . $v . '"';
	if ( flxq_etag_matches( $req->get_header( 'if_none_match' ), $etag ) ) {
		$res = new WP_REST_Response( null, 304 );
	} else {
		$res = new WP_REST_Response( array( 'v' => $v ), 200 );
	}
	$res->header( 'ETag', $etag );
	return $res;
}

function flxq_is_rev_request( $req ) {
	return $req instanceof WP_REST_Request && 0 === strpos( (string) $req->get_route(), '/' . FLXQ_NS . '/rev/' );
}
