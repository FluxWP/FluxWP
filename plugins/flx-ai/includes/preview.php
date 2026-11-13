<?php
/**
 * Taslak önizleme bağlantısı (imzalı, süreli) ve sayfa ekran görüntüsü aracı (0.20.0).
 *
 * - flxai_preview_link( $post_id ): oturum açmadan açılan, süreli önizleme adresi (Gotenberg ekran görüntüsü için).
 * - flxai_page_screenshot: sitenin bir sayfasının ya da taslağın PNG ekran görüntüsü (Gotenberg, Ayarlar › Görsel çevirici).
 * - flxai_tool_result: içerik araçlarının sonucuna preview_url eklenir (yayımlanmamış içerik için).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** İmza: içerik + son geçerlilik. */
function flxai_preview_sig( $post_id, $exp ) {
	return substr( hash_hmac( 'sha256', 'flxai-preview|' . (int) $post_id . '|' . (int) $exp, wp_salt( 'auth' ) ), 0, 24 );
}

/**
 * Oturumsuz açılan süreli önizleme adresi.
 *
 * @param int $ttl Saniye (varsayılan 2 saat, en çok 2 gün).
 */
function flxai_preview_link( $post_id, $ttl = 7200 ) {
	$p = get_post( $post_id );
	if ( ! $p ) {
		return '';
	}
	$exp  = time() + max( 300, min( 2 * DAY_IN_SECONDS, (int) $ttl ) );
	$args = 'page' === $p->post_type ? array( 'page_id' => $p->ID ) : ( 'post' === $p->post_type ? array( 'p' => $p->ID ) : array( 'post_type' => $p->post_type, 'p' => $p->ID ) );
	$args['flxai_pv'] = $exp . '.' . flxai_preview_sig( $p->ID, $exp );
	return add_query_arg( $args, home_url( '/' ) );
}

/** İstekteki önizleme imzası geçerliyse içerik kimliği, değilse 0. */
function flxai_preview_request_id() {
	static $id = null;
	if ( null !== $id ) {
		return $id;
	}
	$id  = 0;
	$tok = isset( $_GET['flxai_pv'] ) ? sanitize_text_field( wp_unslash( $_GET['flxai_pv'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' === $tok || false === strpos( $tok, '.' ) ) {
		return $id;
	}
	list( $exp, $sig ) = explode( '.', $tok, 2 );
	$pid = isset( $_GET['page_id'] ) ? (int) $_GET['page_id'] : (int) ( $_GET['p'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( $pid && (int) $exp >= time() && hash_equals( flxai_preview_sig( $pid, (int) $exp ), $sig ) ) {
		$id = $pid;
	}
	return $id;
}

/* Ana sorgu taslağı bulursa bellekte "yayımlanmış" gibi gösterilir (veritabanı değişmez). */
add_filter(
	'posts_results',
	function ( $posts, $q ) {
		$pid = flxai_preview_request_id();
		if ( ! $pid || ! $q->is_main_query() || 1 !== count( $posts ) || (int) $posts[0]->ID !== $pid ) {
			return $posts;
		}
		if ( in_array( $posts[0]->post_status, array( 'draft', 'pending', 'future', 'auto-draft' ), true ) ) {
			$posts[0]->post_status = 'publish';
			add_filter( 'wp_robots', 'wp_robots_no_robots' );
			nocache_headers();
			header( 'X-LiteSpeed-Cache-Control: no-cache' );
		}
		return $posts;
	},
	10,
	2
);
/* Taslağın durumu sorgudan önce "publish" değilse WP_Query bulmaz: önizlemede durum koşulu genişler. */
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( flxai_preview_request_id() && $q->is_main_query() ) {
			$q->set( 'post_status', array( 'publish', 'draft', 'pending', 'future' ) );
		}
	}
);

/** Gotenberg ile adresin ekran görüntüsü (PNG baytları). */
function flxai_screenshot_url( $url, $width = 1280, $height = 900 ) {
	$base = trim( (string) flxai_setting( 'render_url', '' ) );
	if ( '' === $base ) {
		return new WP_Error( 'flxai_render_off', __( 'No visual renderer (Gotenberg) is set: Flux AI & MCP › Settings › Visual renderer.', 'flx-ai' ) );
	}
	$boundary = 'flxai' . wp_generate_password( 16, false );
	$fields   = array(
		'url'                  => $url,
		'width'                => (string) $width,
		'height'               => (string) $height,
		'clip'                 => 'false',
		'format'               => 'png',
		'optimizeForSpeed'     => 'true',
		'waitDelay'            => '1s',
	);
	$body = '';
	foreach ( $fields as $k => $v ) {
		$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$k}\"\r\n\r\n{$v}\r\n";
	}
	$body   .= "--{$boundary}--\r\n";
	$headers = array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary );
	$user    = (string) flxai_setting( 'render_user', '' );
	if ( '' !== $user ) {
		$headers['Authorization'] = 'Basic ' . base64_encode( $user . ':' . (string) flxai_setting( 'key_render', '' ) ); // phpcs:ignore
	}
	$r = wp_remote_post( untrailingslashit( $base ) . '/forms/chromium/screenshot/url', array( 'timeout' => 45, 'headers' => $headers, 'body' => $body ) );
	if ( is_wp_error( $r ) ) {
		/* translators: %s: error */
		return new WP_Error( 'flxai_render_http', sprintf( __( 'Could not reach the visual renderer: %s', 'flx-ai' ), $r->get_error_message() ) );
	}
	$data = (string) wp_remote_retrieve_body( $r );
	if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) || "\x89PNG" !== substr( $data, 0, 4 ) ) {
		/* translators: %d: HTTP code */
		return new WP_Error( 'flxai_render_failed', sprintf( __( 'Could not take the screenshot (HTTP %d).', 'flx-ai' ), (int) wp_remote_retrieve_response_code( $r ) ) );
	}
	return $data;
}

add_filter(
	'flxai_tools',
	function ( $tools ) {
		if ( '' === trim( (string) flxai_setting( 'render_url', '' ) ) ) {
			return $tools;
		}
		$tools['flxai_page_screenshot'] = array(
			'title'       => __( 'Page screenshot', 'flx-ai' ),
			'description' => 'Sitenin bir sayfasının ya da taslak içeriğin ekran görüntüsünü alır (PNG). post_id verirsen taslak da süreli önizleme bağlantısıyla çekilir. Dönen url\'yi raporda ![başlık](url) olarak göster. Yalnız bu sitenin adresleri.',
			'readonly'    => true,
			'cap'         => 'edit_posts',
			'source'      => 'flx-ai',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array( 'type' => 'integer', 'description' => 'İçerik kimliği (taslak olabilir)' ),
					'url'     => array( 'type' => 'string', 'description' => 'Ya da sitenin bir adresi' ),
					'width'   => array( 'type' => 'integer', 'description' => 'Genişlik px (varsayılan 1280; mobil için 390)' ),
				),
			),
			'run'         => function ( $a ) {
				$pid = (int) ( $a['post_id'] ?? 0 );
				if ( $pid ) {
					if ( ! current_user_can( 'edit_post', $pid ) ) {
						throw new Exception( __( 'You cannot view this content.', 'flx-ai' ) ); // phpcs:ignore
					}
					$url = 'publish' === get_post_status( $pid ) ? get_permalink( $pid ) : flxai_preview_link( $pid, 900 );
				} else {
					$url = esc_url_raw( (string) ( $a['url'] ?? '' ) );
					if ( wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
						throw new Exception( __( 'Only this site\'s addresses can be captured.', 'flx-ai' ) ); // phpcs:ignore
					}
				}
				if ( ! $url ) {
					throw new Exception( __( 'Content not found.', 'flx-ai' ) ); // phpcs:ignore
				}
				$w   = max( 320, min( 1920, (int) ( $a['width'] ?? 1280 ) ) );
				$png = flxai_screenshot_url( $url, $w, $w < 600 ? 844 : 900 );
				if ( is_wp_error( $png ) ) {
					throw new Exception( $png->get_error_message() ); // phpcs:ignore
				}
				$f = flxai_media_write( $png, 'image/png' );
				if ( is_wp_error( $f ) ) {
					throw new Exception( $f->get_error_message() ); // phpcs:ignore
				}
				return array( 'url' => $f['url'], 'width' => $f['width'] ?? $w, 'height' => $f['height'] ?? 0 );
			},
		);
		return $tools;
	}
);

/* İçerik araçlarının sonucu: yayımlanmamış içerik için önizleme ve düzenleme bağlantısı. */
add_filter(
	'flxai_tool_result',
	function ( $data, $name ) {
		if ( ! is_array( $data ) || empty( $data['id'] ) || ! in_array( $name, array( 'flxseo_create_content', 'flxseo_update_content', 'flxseo_get_content' ), true ) ) {
			return $data;
		}
		$p = get_post( (int) $data['id'] );
		if ( ! $p ) {
			return $data;
		}
		$data['edit_url'] = get_edit_post_link( $p->ID, 'raw' );
		if ( 'publish' !== $p->post_status ) {
			$data['preview_url'] = get_preview_post_link( $p );
		}
		return $data;
	},
	10,
	2
);
