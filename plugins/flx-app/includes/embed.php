<?php
/**
 * Uygulama içi web görünümü (masaüstü ve mobil): uygulama portlanmamış ekranı gömülü web görünümünde açar.
 * Uygulamanın kendi menüsü ve üst çubuğu olduğu için panelin menüsü, üst çubuğu, alt barı, altlığı ve
 * sayfa yükleme çubuğu gizlenir; ön yüzde yönetim çubuğu gösterilmez. Sayfa tüm alanı kullanır.
 *
 * Gömülü görünüm şunlardan biriyle açılır:
 *   - user agent'ta "FLXApp/<sürüm> (mobile|desktop; <marka>; ios|android|macos|windows)"
 *   - oturum devri (POST /flxapp/v1/session, `embed` varsayılan true) sonrası oturum çerezi `flxapp_embed`
 *   - adres parametresi `?flxapp_embed=1` (çerezi de yazar); `?flxapp_embed=0` kapatır
 * Tema: `flxapp_mode=dark|light` çerezi Flux Admin koyu/açık temasını seçer (uygulamanın tema modu).
 * Köprü: sayfa uygulamaya yalnız `{type: title|route|toast|counts|close, …}` mesajları gönderir (route: `path` + `url`)
 * (ReactNativeWebView, Tauri `flxapp` kanalı ya da `window.parent`); uygulama JS enjekte etmez.
 */

defined( 'ABSPATH' ) || exit;

const FLXAPP_EMBED_COOKIE = 'flxapp_embed';

function flxapp_set_embed_cookie( $on ) {
	if ( headers_sent() ) {
		return;
	}
	$path = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
	setcookie( FLXAPP_EMBED_COOKIE, $on ? '1' : '', $on ? 0 : time() - YEAR_IN_SECONDS, $path, defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '', is_ssl(), true );
	$_COOKIE[ FLXAPP_EMBED_COOKIE ] = $on ? '1' : '';
}

/** UA'daki uygulama bilgisi: { version, platform: mobile|desktop|'', brand, os } ya da null. */
function flxapp_embed_agent() {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! preg_match( '#FLXApp/([0-9][0-9A-Za-z.\-]*)(?:\s*\(([^)]*)\))?#', $ua, $m ) ) {
		return null;
	}
	$parts = array_map( 'trim', explode( ';', (string) ( $m[2] ?? '' ) ) );
	$pf    = in_array( $parts[0] ?? '', array( 'mobile', 'desktop' ), true ) ? $parts[0] : '';
	return array(
		'version'  => $m[1],
		'platform' => $pf,
		'brand'    => sanitize_key( $parts[1] ?? '' ),
		'os'       => sanitize_key( $parts[2] ?? '' ),
	);
}

function flxapp_is_embed() {
	static $on = null;
	if ( null !== $on ) {
		return $on;
	}
	$q = isset( $_GET['flxapp_embed'] ) ? sanitize_key( wp_unslash( $_GET['flxapp_embed'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification
	if ( null !== $q ) {
		flxapp_set_embed_cookie( '1' === $q );
		$on = '1' === $q;
		return $on;
	}
	$on = null !== flxapp_embed_agent() || '1' === ( $_COOKIE[ FLXAPP_EMBED_COOKIE ] ?? '' );
	return $on;
}

/** Gömülü görünümün platformu (CSS sınıfı için): mobile | desktop. UA'da yoksa mobile. */
function flxapp_embed_platform() {
	$a = flxapp_embed_agent();
	return $a && 'desktop' === $a['platform'] ? 'desktop' : 'mobile';
}

/** Uygulamanın tema modu: dark | light | ''. */
function flxapp_embed_mode() {
	$m = sanitize_key( wp_unslash( $_COOKIE['flxapp_mode'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	return in_array( $m, array( 'dark', 'light' ), true ) ? $m : '';
}

/* Parametre/çerez kararı çıktı başlamadan verilsin. */
add_action(
	'init',
	function () {
		if ( isset( $_GET['flxapp_embed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			flxapp_is_embed();
		}
	},
	0
);

add_filter(
	'admin_body_class',
	function ( $c ) {
		if ( ! flxapp_is_embed() ) {
			return $c;
		}
		$c .= ' flxapp-embed flxapp-embed-' . flxapp_embed_platform();
		return 'dark' === flxapp_embed_mode() ? $c . ' flx-dark' : $c;
	}
);

function flxapp_embed_css() {
	// Seçiciler Flux Admin uygulama modu kurallarından (html body.flxa-appmode …) güçlü olsun diye sınıf iki kez yazılır.
	$b = 'html body.flxapp-embed.flxapp-embed';
	return 'html.wp-toolbar{padding-top:0!important}'
		. $b . '{--flxa-app-w:0px;--flxa-side-w:0px;--flxa-rail-w:0px;--wp-admin--admin-bar--height:0px}'
		. implode( ',', array_map( fn( $x ) => "$b $x", array( '#wpadminbar', '#adminmenumain', '#adminmenuback', '#adminmenuwrap', '#wpfooter', '#flxa-bottom', '.flxa-resizer', '.flxa-sw', '#screen-meta-links', '#flxa-progress' ) ) ) . '{display:none!important}'
		. "$b #wpcontent{margin-left:0!important}$b #wpbody-content{padding-bottom:24px!important}"
		. "$b #flxg-app,$b #flxc-app,$b #flxst-app{top:0!important;left:0!important;right:0!important;bottom:0!important;width:auto!important;height:auto!important}"
		// Mobil: çentik/ana çubuk boşlukları, tam genişlik, bildirim yığınları dar.
		. "html body.flxapp-embed-mobile #wpcontent{padding-left:max(10px,env(safe-area-inset-left))!important;padding-right:max(10px,env(safe-area-inset-right))!important}"
		. "html body.flxapp-embed-mobile #wpbody-content{padding-bottom:calc(24px + env(safe-area-inset-bottom))!important}"
		. "html body.flxapp-embed-mobile .wrap{margin:8px 0 0!important}"
		. "$b .notice,$b div.updated,$b div.error{margin:6px 0!important;padding:4px 10px!important}$b .notice p{margin:4px 0!important}"
		. "$b .notice:nth-of-type(n+3){display:none!important}"
		// Blok editörü: üst çubuk yok, tam ekran.
		. "$b.block-editor-page .interface-interface-skeleton{top:0!important;left:0!important}$b.block-editor-page .edit-post-fullscreen-mode-close{display:none!important}";
}

add_action(
	'admin_head',
	function () {
		if ( ! flxapp_is_embed() ) {
			return;
		}
		echo '<style id="flxapp-embed">' . flxapp_embed_css() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$mode = flxapp_embed_mode();
		if ( '' !== $mode ) {
			// Flux Admin'in tema anahtarı (FLXA_APPLY_MODE) varsa uygulamanın modunu uygula.
			echo '<script>(function(m){var h=document.documentElement;if(window.FLXA_APPLY_MODE){window.FLXA_APPLY_MODE(m);}else{h.classList.toggle("flxa-dark",m==="dark");}})(' . wp_json_encode( $mode ) . ');</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	},
	99
);

/* Ön yüz (Hesabım, bağış sayfası vb.) uygulamada açılınca yönetim çubuğu yok. */
add_filter(
	'show_admin_bar',
	function ( $show ) {
		return flxapp_is_embed() && flxapp_opt( 'embed_front' ) ? false : $show;
	},
	99
);

add_filter(
	'body_class',
	function ( $c ) {
		if ( flxapp_is_embed() ) {
			$c[] = 'flxapp-embed';
			$c[] = 'flxapp-embed-' . flxapp_embed_platform();
		}
		return $c;
	}
);

/* Giriş sayfası uygulamada (oturum düştü ya da sınırlı mod): site bağlantıları ve dil seçici yok. */
add_action(
	'login_head',
	function () {
		if ( ! flxapp_is_embed() ) {
			return;
		}
		add_filter( 'login_display_language_dropdown', '__return_false' );
		echo '<style id="flxapp-embed-login">#backtoblog,#nav,.privacy-policy-page-link,.language-switcher{display:none!important}body.login{padding-top:env(safe-area-inset-top)}</style>' . "\n";
	}
);

/* Blok editörü uygulamada tam ekran açılır. */
add_action(
	'enqueue_block_editor_assets',
	function () {
		if ( ! flxapp_is_embed() ) {
			return;
		}
		wp_add_inline_script( 'wp-edit-post', 'wp.domReady(function(){try{var p=wp.data.dispatch("core/preferences");p&&p.set("core/edit-post","fullscreenMode",true);}catch(e){}});' );
	}
);

/** Köprü betiği: başlık, adres ve başarı bildirimini uygulamaya iletir (yalnız beyaz listeli türler). */
function flxapp_embed_bridge() {
	if ( ! flxapp_is_embed() ) {
		return;
	}
	$js = '(function(w,d){function s(m){m.v=1;var j=JSON.stringify(m);try{if(w.ReactNativeWebView){w.ReactNativeWebView.postMessage(j);}else if(w.__TAURI_INTERNALS__&&w.__TAURI_INTERNALS__.invoke){w.__TAURI_INTERNALS__.invoke("plugin:event|emit",{event:"flxapp://bridge",payload:m});}else if(w.parent&&w.parent!==w){w.parent.postMessage(m,"*");}}catch(e){}}'
		. 'var t="";function title(){var n=(d.title||"").split(/ [‹|\u2039\u2014-] /)[0].trim();if(n&&n!==t){t=n;s({type:"title",title:n});}}'
		. 's({type:"route",path:w.location.pathname+w.location.search,url:w.location.href});title();var el=d.querySelector("title");if(el&&w.MutationObserver){new MutationObserver(title).observe(el,{childList:true});}'
		. 'var ok=d.querySelector(".notice-success p, #message.updated p");if(ok){s({type:"toast",text:ok.textContent.trim().slice(0,200),tone:"success"});}'
		. 'w.FLXAppBridge={send:function(type,data){if(["title","route","toast","close","counts"].indexOf(type)>-1){var m=data||{};m.type=type;s(m);}}};'
		. '})(window,document);';
	echo '<script id="flxapp-bridge">' . $js . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'admin_footer', 'flxapp_embed_bridge', 99 );
add_action( 'wp_footer', 'flxapp_embed_bridge', 99 );
