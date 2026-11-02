<?php
/**
 * Ortak sohbet yükleyicisi: yalnız yetkili kullanıcıya, admin ve sitede.
 * Sayfa akışını etkilememek için betik defer + footer, sohbet "load" sonrası boşta başlar.
 * Panel sağa yanaşıksa sayfa ilk boyamada kaydırılır (head'deki küçük satır içi betik), sonradan zıplamaz.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** Bu istekte sohbet gösterilsin mi? */
function flxai_dock_enabled() {
	static $on = null;
	if ( null !== $on ) {
		return $on;
	}
	$on = false;
	if ( ! is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST ) ) {
		return $on;
	}
	if ( is_admin() ? ! flxai_setting( 'chat_admin', 1 ) : ! flxai_setting( 'chat_front', 1 ) ) {
		return $on;
	}
	if ( ! is_admin() && ( is_embed() || is_customize_preview() || isset( $_GET['elementor-preview'] ) ) ) { // phpcs:ignore
		return $on;
	}
	$on = flxai_can_chat() && flxai_source_enabled( 'flx-ai' );
	$on = (bool) apply_filters( 'flxai_dock_enabled', $on );
	return $on;
}

/** Sağa yanaşık panelin sayfayı itmesi: ilk boyamadan önce uygulanır. */
function flxai_dock_head() {
	if ( ! flxai_dock_enabled() ) {
		return;
	}
	$sel = (array) apply_filters( 'flxai_dock_push_selectors', array( '#wpadminbar', '#flxa-bottom', '#flxc-app', '#flxg-app', '.flx-cookie' ) );
	$sel = implode( ',', array_map( function ( $s ) {
		return 'html.flxai-docked ' . $s;
	}, array_filter( array_map( 'trim', $sel ) ) ) );
	echo "<style id=\"flxai-dock-css\">@media (min-width:1024px){html.flxai-docked body{margin-right:var(--flxai-w,420px)!important;width:auto!important;min-width:0!important;max-width:none!important;transition:margin-right .2s}html.flxai-docked #wpadminbar{left:0!important;width:auto!important}"
		. ( '' !== $sel ? wp_strip_all_tags( $sel ) . '{right:var(--flxai-w,420px)!important}' : '' )
		. "}</style>\n";
	echo "<script id=\"flxai-dock-js\">(function(){try{var u=JSON.parse(localStorage.getItem('flxai-ui')||'{}');if(u.open&&u.mode==='dock'&&innerWidth>=1024){var w=Math.max(340,Math.min(u.w||420,960,innerWidth-480));var h=document.documentElement;h.classList.add('flxai-docked');h.style.setProperty('--flxai-w',w+'px');}}catch(e){}})();</script>\n";
}
add_action( 'wp_head', 'flxai_dock_head', 1 );
add_action( 'admin_head', 'flxai_dock_head', 1 );

/**
 * Sayfa geçişinde panel yeniden belirmesin: önceki sayfanın panel görüntüsü (sessionStorage, aynı sekme)
 * gövdenin başında, ilk boyamadan önce çizilir. chat.js yüklenince aynı kabı devralır, sunucudan tazeler.
 * "expect" bağlantısı, destekleyen tarayıcıda ilk boyamayı bu satıra kadar bekletir.
 */
function flxai_dock_expect() {
	if ( flxai_dock_enabled() ) {
		echo '<link rel="expect" href="#flxai-early" blocking="render">' . "\n";
	}
}
add_action( 'wp_head', 'flxai_dock_expect', 2 );
add_action( 'admin_head', 'flxai_dock_expect', 2 );

function flxai_dock_early() {
	static $done = false;
	if ( $done || ! flxai_dock_enabled() ) {
		return;
	}
	$done = true;
	echo "<script id=\"flxai-early\">(function(){try{var s=JSON.parse(sessionStorage.getItem('flxai-snap')||'null');if(!s||!s.html||!s.css||document.getElementById('flxai-host')||!document.body.attachShadow)return;var h=document.createElement('div');h.id='flxai-host';h.className=s.cls||'';h.setAttribute('data-nosnippet','');h.setAttribute('data-early','1');document.body.appendChild(h);var r=h.attachShadow({mode:'open'}),st=document.createElement('style');st.textContent=s.css;r.appendChild(st);var w=document.createElement('div');w.innerHTML=s.html;r.appendChild(w);var b=w.querySelector('.body');if(b)b.scrollTop=s.scroll||0;}catch(e){}})();</script>\n";
}
add_action( 'in_admin_header', 'flxai_dock_early', 0 );
add_action( 'wp_body_open', 'flxai_dock_early', 0 );

/** Önerilen ilk mesajlar (boş sohbette). */
function flxai_chat_suggestions() {
	$s = is_admin()
		? array(
			__( 'Bu sayfada neler yapabilirim?', 'flx-ai' ),
			__( 'Sitedeki sistem kontrolü uyarılarını özetle', 'flx-ai' ),
			__( 'Son eklenen taslak içerikleri listele', 'flx-ai' ),
			__( 'Bu ay yapay zekâya ne kadar harcadık?', 'flx-ai' ),
		)
		: array(
			__( 'Bu sayfayı özetle', 'flx-ai' ),
			__( 'Bu sayfanın SEO eksiklerini göster', 'flx-ai' ),
			__( 'Bu sayfadaki yazım hatalarını bul', 'flx-ai' ),
		);
	return array_values( (array) apply_filters( 'flxai_chat_suggestions', $s, is_admin() ) );
}

function flxai_dock_enqueue() {
	if ( ! flxai_dock_enabled() ) {
		return;
	}
	$args = array( 'in_footer' => true, 'strategy' => 'defer' );
	wp_enqueue_script( 'flxai-chat', FLXAI_URL . 'assets/js/chat.js', array(), FLXAI_VERSION, $args );
	wp_localize_script(
		'flxai-chat',
		'FLXAI_CHAT',
		array(
			'rest'        => esc_url_raw( rest_url( 'flx-ai/v1/' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'css'         => FLXAI_URL . 'assets/css/chat.css?ver=' . FLXAI_VERSION,
			'admin'       => is_admin(),
			'mode'        => (string) flxai_setting( 'chat_mode', 'bubble' ) === 'dock' ? 'dock' : 'float',
			'locale'      => str_replace( '_', '-', determine_locale() ),
			'lightOnly'   => function_exists( 'flx_ayar' ) && 'off' === flx_ayar( 'dark_mode' ),
			'settingsUrl' => flxai_can_manage() ? admin_url( 'admin.php?page=flx-ai-settings' ) : '',
			'suggestions' => flxai_chat_suggestions(),
			'widgets'     => (bool) flxai_setting( 'chat_widgets', 1 ),
			'mermaid'     => FLXAI_URL . 'assets/vendor/mermaid/mermaid.min.js?ver=12.2.0',
			'i18n'        => flxai_js_strings(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'flxai_dock_enqueue', 99 );
add_action( 'admin_enqueue_scripts', 'flxai_dock_enqueue', 99 );
