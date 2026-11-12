<?php
/**
 * Görsel rapor (0.15.0): asistan Tailwind / Chart.js / Mermaid ile tek sayfalık etkileşimli HTML rapor yazar,
 * sohbetin klasörüne (raporlar/) kaydeder; kullanıcı sohbetteki karttan çalışma alanında açar.
 *
 * Güvenlik: HTML hiçbir zaman WordPress sayfasına basılmaz. Ayrı REST adresinden (ws/view) CSP "sandbox"
 * başlığıyla (opak köken, çerez/oturum yok, dışarıya istek yok; yalnız izinli CDN'lerden betik/stil) sunulur.
 * Tasarım ilkeleri nicobailon/visual-explainer (MIT) skill'inden uyarlandı.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** Rapor sayfasında izin verilen CDN'ler (CSP). */
function flxai_report_csp() {
	$cdn = (array) apply_filters( 'flxai_report_cdns', array( 'https://cdn.tailwindcss.com', 'https://cdn.jsdelivr.net', 'https://unpkg.com', 'https://cdnjs.cloudflare.com' ) );
	$cdn = implode( ' ', array_filter( array_map( 'esc_url_raw', $cdn ) ) );
	return "sandbox allow-scripts allow-popups allow-popups-to-escape-sandbox; default-src 'none'; script-src 'unsafe-inline' {$cdn}; style-src 'unsafe-inline' https://fonts.googleapis.com {$cdn}; font-src https://fonts.gstatic.com data: {$cdn}; img-src data: blob: https:; media-src data: https:; connect-src 'none'; frame-ancestors 'self'; form-action 'none'; base-uri 'none'";
}

/** Gövde parçasını tam sayfaya sarar (Tailwind + koyu mod; gerekiyorsa Chart.js / Mermaid). */
function flxai_report_wrap( $title, $html ) {
	if ( preg_match( '/<html[\s>]/i', $html ) ) {
		return $html;
	}
	$lang  = substr( determine_locale(), 0, 2 );
	$head  = '<script src="https://cdn.tailwindcss.com/3.4.16"></script>';
	$head .= "<script>tailwind.config={darkMode:'media',theme:{extend:{fontFamily:{sans:['Inter','ui-sans-serif','system-ui','sans-serif']}}}}</script>";
	if ( false !== stripos( $html, 'new Chart' ) ) {
		$head .= '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>';
	}
	if ( false !== stripos( $html, 'class="mermaid"' ) ) {
		$head .= "<script type=\"module\">import m from 'https://cdn.jsdelivr.net/npm/mermaid@11.4.1/dist/mermaid.esm.min.mjs';m.initialize({startOnLoad:true,securityLevel:'strict',theme:matchMedia('(prefers-color-scheme: dark)').matches?'dark':'neutral'});</script>";
	}
	return '<!doctype html><html lang="' . esc_attr( $lang ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $title ) . '</title>' . $head
		. '</head><body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100"><main class="mx-auto max-w-5xl px-4 py-8 sm:px-8 sm:py-12">' . $html . '</main></body></html>';
}

/** Raporu sohbetin klasörüne yazar. @return array|WP_Error path, title */
function flxai_report_save( array $chat, $title, $html, $file = '' ) {
	if ( ! function_exists( 'flxai_ws_ready' ) || ! flxai_ws_ready() ) {
		return new WP_Error( 'flxai_report_ws', __( 'Visual reports need Flux Files (workspace).', 'flx-ai' ) );
	}
	$title = mb_substr( trim( wp_strip_all_tags( (string) $title ) ), 0, 160 );
	$title = '' !== $title ? $title : __( 'Report', 'flx-ai' );
	$doc   = flxai_report_wrap( $title, (string) $html );
	if ( strlen( $doc ) > flxai_ws_max_write() ) {
		return new WP_Error( 'flxai_report_big', __( 'The report is too large.', 'flx-ai' ) );
	}
	$slug = sanitize_title( '' !== (string) $file ? preg_replace( '/\.html?$/i', '', (string) $file ) : $title );
	$slug = '' !== $slug ? substr( $slug, 0, 60 ) : 'rapor';
	$rel  = flxai_ws_policy( $chat )['dir'] . '/raporlar/' . $slug . '.html';
	$r    = flxai_ws_write( $rel, $doc );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	return array(
		'path'  => $rel,
		'title' => $title,
	);
}

/* Sohbet aracı (yalnız sohbette: rapor sohbetin klasörüne yazılır). */
add_filter(
	'flxai_tools',
	function ( $tools ) {
		$tools                        = is_array( $tools ) ? $tools : array();
		$tools['flxai_visual_report'] = array(
			'title'       => __( 'Create a visual report', 'flx-ai' ),
			'description' => 'Tailwind ile tek sayfalık, etkileşimli görsel rapor (HTML) oluşturur ve sohbetin klasörüne (raporlar/) kaydeder; kullanıcı sohbetteki karttan açar. '
				. 'Kullanıcı görsel rapor, açıklayıcı sayfa, pano, karşılaştırma, plan ya da sunum istediğinde ya da cevap uzun tablo/grafik gerektirdiğinde kullan (küçük görseller için ```widget yeter). '
				. 'html: <main> içine konacak gövde (Tailwind sınıfları; koyu mod için dark: sınıfları) ya da tam <html> belgesi. Grafik için <canvas> + <script>new Chart(...)</script> (Chart.js kendiliğinden yüklenir), şema için <pre class="mermaid">. '
				. 'Dış istek (fetch) çalışmaz: veriyi önce araçlarla topla, sayfaya göm. Önce gereken veriyi topla, sonra bu aracı TEK kez çağır; cevabında HTML\'i tekrar yazma, 2-3 cümlelik özet yaz. Aynı file ile çağırırsan rapor güncellenir.',
			'readonly'    => true,
			'cap'         => 'read',
			'source'      => 'flx-ai',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'title' => array(
						'type'        => 'string',
						'description' => 'Rapor başlığı.',
					),
					'html'  => array(
						'type'        => 'string',
						'description' => 'Rapor gövdesi (Tailwind HTML) ya da tam HTML belgesi.',
					),
					'file'  => array(
						'type'        => 'string',
						'description' => 'İsteğe bağlı dosya adı (ör. kasim-bagis-raporu). Var olan raporu güncellemek için aynı adı ver.',
					),
				),
				'required'   => array( 'title', 'html' ),
			),
			'run'         => function ( $a, $ctx = array() ) {
				$chat = flxai_chat_get( (int) ( $ctx['chat_id'] ?? 0 ) );
				if ( ! $chat ) {
					throw new Exception( __( 'This tool only works in the AI chat.', 'flx-ai' ) );
				}
				$r = flxai_report_save( $chat, (string) ( $a['title'] ?? '' ), (string) ( $a['html'] ?? '' ), (string) ( $a['file'] ?? '' ) );
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				return $r + array( 'saved' => true, 'note' => 'Kullanıcı sohbetteki "Görsel rapor" kartından açar. HTML\'i cevapta tekrarlama.' );
			},
		);
		return $tools;
	}
);

/* Raporu sandbox'lı ayrı sayfa olarak sunar: /flx-ai/v1/chats/<id>/ws/view?path=…&_wpnonce=… */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flx-ai/v1',
			'/chats/(?P<id>\d+)/ws/view',
			array(
				'methods'             => 'GET',
				'permission_callback' => function () {
					return is_user_logged_in() && flxai_can_chat();
				},
				'callback'            => function ( WP_REST_Request $req ) {
					$c   = flxai_chat_get( (int) $req['id'] );
					$rel = flxai_ws_clean( (string) $req['path'] );
					if ( ! $c || ! flxai_ws_ready() || ! preg_match( '/\.html?$/i', $rel ) || ! flxai_ws_can_browse( $rel, flxai_ws_policy( $c ) ) ) {
						return new WP_Error( 'flxai_forbidden', __( 'This file is not part of the workspace.', 'flx-ai' ), array( 'status' => 403 ) );
					}
					$html = flxai_ws_read( $rel );
					if ( null === $html ) {
						return new WP_Error( 'flxai_not_found', __( 'File not found.', 'flx-ai' ), array( 'status' => 404 ) );
					}
					nocache_headers();
					header( 'Content-Type: text/html; charset=utf-8' );
					header( 'Content-Security-Policy: ' . flxai_report_csp() );
					header( 'X-Content-Type-Options: nosniff' );
					header( 'Referrer-Policy: no-referrer' );
					header( 'Cache-Control: no-store, private' );
					header( 'X-LiteSpeed-Cache-Control: no-cache' );
					do_action( 'litespeed_control_set_nocache', 'flx-ai report' );
					echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- CSP sandbox altında, ayrı köken.
					exit;
				},
			)
		);
	}
);
