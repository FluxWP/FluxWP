<?php
/**
 * MCP sunucusu (Model Context Protocol, Streamable HTTP, durumsuz).
 *
 * Adres: /wp-json/flx-mcp/v1/mcp
 * Kimlik: "Authorization: Bearer <anahtar>" (Ayarlar › SEO & MCP › Yapay zekâ) veya
 *         WordPress Uygulama Parolası (Basic). Araçlar anahtarı oluşturan kullanıcının yetkileriyle çalışır;
 *         anahtara araç listesi verildiyse yalnız o araçlar görünür ve çalışır.
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FLXSEO_MCP_PROTOCOLS = array( '2025-06-18', '2025-03-26', '2024-11-05' );

function flxseo_mcp_url() {
	return rest_url( 'flx-mcp/v1/mcp' );
}

/* ---------------------------------------------------------------------------
 * Anahtarlar
 * ------------------------------------------------------------------------- */

function flxseo_mcp_keys() {
	return (array) get_option( 'flxseo_mcp_keys', array() );
}

function flxseo_mcp_find_key( $key ) {
	if ( ! $key || strlen( $key ) < 20 ) {
		return null;
	}
	$hash = hash( 'sha256', $key );
	foreach ( flxseo_mcp_keys() as $id => $k ) {
		if ( hash_equals( $k['hash'], $hash ) ) {
			return array( 'id' => $id ) + $k;
		}
	}
	return null;
}

/**
 * Bu istekteki anahtarın araç listesi. null = sınırsız (anahtar kapsamsız ya da Uygulama Parolası).
 * Yalnız flxseo_mcp_permission() ayarlar.
 */
function flxseo_mcp_scope( $set = false, $tools = null ) {
	static $scope = null;
	if ( $set ) {
		$scope = $tools;
	}
	return $scope;
}

function flxseo_mcp_allowed( $name ) {
	$scope = flxseo_mcp_scope();
	return null === $scope || in_array( $name, $scope, true );
}

function flxseo_mcp_request_key( WP_REST_Request $req ) {
	$auth = $req->get_header( 'authorization' );
	if ( ! $auth && isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
		$auth = sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
	}
	if ( $auth && 0 === stripos( $auth, 'Bearer ' ) ) {
		return trim( substr( $auth, 7 ) );
	}
	if ( $req->get_header( 'x_mcp_key' ) ) {
		return trim( $req->get_header( 'x_mcp_key' ) );
	}
	// Başlık eklenemeyen istemciler (ör. bazı web bağlayıcıları) için gizli adres: ?key=...
	return (string) $req->get_param( 'key' );
}

/**
 * Bearer anahtarı olan istekte WordPress'in Basic/çerez kimliğini aramasına gerek yok.
 */
add_filter(
	'determine_current_user',
	function ( $user_id ) {
		if ( $user_id || empty( $_SERVER['REQUEST_URI'] ) || false === strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'flx-mcp/v1' ) ) {
			return $user_id;
		}
		$auth = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '' ) );
		$key  = 0 === stripos( $auth, 'Bearer ' ) ? trim( substr( $auth, 7 ) ) : sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_MCP_KEY'] ?? $_GET['key'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$k    = flxseo_mcp_find_key( $key );
		return $k ? (int) $k['user'] : $user_id;
	},
	30
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flx-mcp/v1',
			'/mcp',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => 'flxseo_mcp_handle',
					'permission_callback' => 'flxseo_mcp_permission',
				),
				array(
					'methods'             => array( 'GET', 'DELETE' ),
					'callback'            => function () {
						return new WP_REST_Response( null, 405 );
					},
					'permission_callback' => '__return_true',
				),
			)
		);
	}
);

function flxseo_mcp_permission( WP_REST_Request $req ) {
	if ( ! flxseo_opt( 'mcp_enabled' ) ) {
		return new WP_Error( 'mcp_disabled', 'MCP kapalı. Ayarlar › SEO & MCP › Yapay zekâ sekmesinden açın.', array( 'status' => 403 ) );
	}
	$k = flxseo_mcp_find_key( flxseo_mcp_request_key( $req ) );
	flxseo_mcp_scope( true, $k && ! empty( $k['tools'] ) ? array_values( (array) $k['tools'] ) : null );
	if ( $k ) {
		wp_set_current_user( (int) $k['user'] );
		$keys                          = flxseo_mcp_keys();
		$keys[ $k['id'] ]['last_used'] = time();
		update_option( 'flxseo_mcp_keys', $keys, false );
	}
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'mcp_auth', 'Geçersiz veya eksik MCP anahtarı.', array( 'status' => 401 ) );
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		return new WP_Error( 'mcp_cap', 'Bu kullanıcının içerik düzenleme yetkisi yok.', array( 'status' => 403 ) );
	}
	return true;
}

/* ---------------------------------------------------------------------------
 * JSON-RPC
 * ------------------------------------------------------------------------- */

function flxseo_mcp_handle( WP_REST_Request $req ) {
	$body = json_decode( $req->get_body(), true );
	if ( null === $body ) {
		return new WP_REST_Response( flxseo_rpc_error( null, -32700, 'Parse error' ), 400 );
	}
	$batch = is_array( $body ) && array_is_list( $body );
	$msgs  = $batch ? $body : array( $body );
	$out   = array();
	foreach ( $msgs as $m ) {
		$r = flxseo_mcp_dispatch( $m );
		if ( null !== $r ) {
			$out[] = $r;
		}
	}
	if ( ! $out ) {
		return new WP_REST_Response( null, 202 );
	}
	return new WP_REST_Response( $batch ? $out : $out[0], 200 );
}

function flxseo_rpc_error( $id, $code, $msg ) {
	return array(
		'jsonrpc' => '2.0',
		'id'      => $id,
		'error'   => array( 'code' => $code, 'message' => $msg ),
	);
}

function flxseo_rpc_result( $id, $result ) {
	return array(
		'jsonrpc' => '2.0',
		'id'      => $id,
		'result'  => $result,
	);
}

function flxseo_mcp_dispatch( $m ) {
	if ( ! is_array( $m ) || ( $m['jsonrpc'] ?? '' ) !== '2.0' || empty( $m['method'] ) ) {
		return flxseo_rpc_error( $m['id'] ?? null, -32600, 'Invalid Request' );
	}
	$id     = $m['id'] ?? null;
	$params = (array) ( $m['params'] ?? array() );
	if ( ! array_key_exists( 'id', $m ) ) {
		return null; // Bildirim (notifications/initialized vb.): yanıt yok.
	}
	switch ( $m['method'] ) {
		case 'initialize':
			$want = $params['protocolVersion'] ?? '';
			return flxseo_rpc_result(
				$id,
				array(
					'protocolVersion' => in_array( $want, FLXSEO_MCP_PROTOCOLS, true ) ? $want : FLXSEO_MCP_PROTOCOLS[0],
					'capabilities'    => array(
						'tools'   => array( 'listChanged' => false ),
						'prompts'   => array( 'listChanged' => false ),
						'resources' => array( 'listChanged' => false ),
					),
					'serverInfo'      => array(
						'name'    => 'yesil-turkiye-wordpress',
						'title'   => get_bloginfo( 'name' ) . ' (WordPress)',
						'version' => FLXSEO_VERSION,
					),
					'instructions'    => flxseo_mcp_instructions(),
				)
			);
		case 'ping':
			return flxseo_rpc_result( $id, new stdClass() );
		case 'tools/list':
			$tools = array();
			foreach ( flxseo_mcp_tools() as $name => $t ) {
				if ( ! flxseo_mcp_allowed( $name ) || ( ! empty( $t['cap'] ) && ! current_user_can( $t['cap'] ) ) ) {
					continue;
				}
				$tools[] = array(
					'name'        => $name,
					'title'       => $t['title'],
					'description' => $t['description'],
					'inputSchema' => $t['schema'],
					'annotations' => array( 'readOnlyHint' => ! empty( $t['readonly'] ) ),
				);
			}
			return flxseo_rpc_result( $id, array( 'tools' => $tools ) );
		case 'tools/call':
			return flxseo_rpc_result( $id, flxseo_mcp_call( (string) ( $params['name'] ?? '' ), (array) ( $params['arguments'] ?? array() ) ) );
		case 'prompts/list':
			$list = array();
			foreach ( flxseo_mcp_prompts() as $name => $p ) {
				$list[] = array( 'name' => $name, 'title' => $p['title'], 'description' => $p['description'], 'arguments' => $p['arguments'] );
			}
			return flxseo_rpc_result( $id, array( 'prompts' => $list ) );
		case 'prompts/get':
			$p = flxseo_mcp_prompts()[ $params['name'] ?? '' ] ?? null;
			if ( ! $p ) {
				return flxseo_rpc_error( $id, -32602, 'Unknown prompt' );
			}
			return flxseo_rpc_result(
				$id,
				array(
					'description' => $p['description'],
					'messages'    => array(
						array(
							'role'    => 'user',
							'content' => array( 'type' => 'text', 'text' => call_user_func( $p['text'], (array) ( $params['arguments'] ?? array() ) ) ),
						),
					),
				)
			);
		case 'resources/list':
			$list = array();
			foreach ( flxseo_mcp_resources() as $uri => $r ) {
				$list[] = array( 'uri' => $uri, 'name' => $r['name'], 'title' => $r['title'], 'description' => $r['description'], 'mimeType' => $r['mimeType'] );
			}
			return flxseo_rpc_result( $id, array( 'resources' => $list ) );
		case 'resources/read':
			$uri = (string) ( $params['uri'] ?? '' );
			$r   = flxseo_mcp_resources()[ $uri ] ?? null;
			if ( ! $r || ! current_user_can( 'edit_posts' ) ) {
				return flxseo_rpc_error( $id, -32002, 'Resource not found' );
			}
			return flxseo_rpc_result( $id, array( 'contents' => array( array( 'uri' => $uri, 'mimeType' => $r['mimeType'], 'text' => (string) call_user_func( $r['read'] ) ) ) ) );
		case 'resources/templates/list':
			return flxseo_rpc_result( $id, array( 'resourceTemplates' => array() ) );
	}
	return flxseo_rpc_error( $id, -32601, 'Method not found: ' . $m['method'] );
}

function flxseo_mcp_call( $name, $args ) {
	$tools = flxseo_mcp_tools();
	$ok    = false;
	try {
		if ( ! isset( $tools[ $name ] ) ) {
			throw new Exception( 'Bilinmeyen araç: ' . $name );
		}
		if ( ! flxseo_mcp_allowed( $name ) ) {
			throw new Exception( 'Bu anahtar bu aracı kullanamaz: ' . $name );
		}
		$t = $tools[ $name ];
		if ( ! empty( $t['cap'] ) && ! current_user_can( $t['cap'] ) ) {
			throw new Exception( 'Bu işlem için yetkiniz yok.' );
		}
		$data = call_user_func( $t['run'], $args );
		$ok   = true;
	} catch ( Throwable $e ) {
		$data = array( 'error' => $e->getMessage() );
	}
	// Sık yoklanan okuma araçları ('log' => false) başarılıysa Son işlemler'i doldurmasın.
	if ( ! $ok || ! isset( $t ) || false !== ( $t['log'] ?? true ) ) {
		flxseo_mcp_log( $name, $ok, $args );
	}
	$res = array(
		'content' => array(
			array(
				'type' => 'text',
				'text' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ),
			),
		),
		'isError' => ! $ok,
	);
	if ( $ok && is_array( $data ) && ! array_is_list( $data ) ) {
		$res['structuredContent'] = $data;
	}
	return $res;
}

function flxseo_mcp_log( $tool, $ok, $args ) {
	$log   = (array) get_option( 'flxseo_mcp_log', array() );
	$target = $args['id'] ?? ( $args['title'] ?? ( $args['search'] ?? '' ) );
	array_unshift(
		$log,
		array(
			't'    => time(),
			'u'    => get_current_user_id(),
			'tool' => $tool,
			'ok'   => $ok,
			'on'   => is_scalar( $target ) ? mb_substr( (string) $target, 0, 80 ) : '',
		)
	);
	update_option( 'flxseo_mcp_log', array_slice( $log, 0, 100 ), false );
}

function flxseo_mcp_instructions() {
	return 'Bu sunucu "' . get_bloginfo( 'name' ) . '" WordPress sitesini yönetir (' . home_url( '/' ) . "). Site Türkçe; içerikleri Türkçe yazın.\n"
		. "- İçerik gövdesi Gutenberg blok işaretlemesi, HTML veya Markdown olabilir (format alanı). Markdown/HTML otomatik bloğa çevrilir.\n"
		. "- Yeni sayfa tasarlarken önce list_patterns ile temanın hazır bölümlerini görün; create_content'e patterns vererek sayfayı bu bölümlerle kurun, sonra metinleri güncelleyin. get_pattern ile bir bölümün blok kodunu alıp düzenleyebilirsiniz.\n"
		. "- Yeni içerikler varsayılan olarak taslak (draft) oluşturulur; kullanıcı açıkça isterse publish yapın.\n"
		. "- SEO: başlık en fazla ~60, meta açıklama 120–160 karakter. seo_audit eksikleri listeler; set_seo ile düzeltin. Görsellere anlamlı alt metin verin (set_image_alt).\n"
		. "- Silme işlemi yalnızca çöpe taşır.\n"
		. "- Yeni sayfa, desen, tema ya da ön ayar yapmadan önce site_build_guide ile FluxWP site yapım rehberini okuyun ve uygulayın. Değişiklikten sonra site_audit(refresh=true) çalıştırın; bulguları öncelik sırasıyla (critical, high, medium, low) düzeltin. fix_id olan bulgu system_fix ile tek adımda düzelir. Geçmiş raporlar ve eğilim: site_audit_history.\n"
		. '- Site sağlığı: system_checks eklenti, cron ve ayar kontrollerini verir (status ile filtreleyin). Bir satırın "fixes" alanındaki düzeltme system_fix ile uygulanır; system_fixes izinli düzeltmeleri ve güncel değerlerini listeler. Ayar değiştirmeden önce kullanıcıya ne yapacağınızı söyleyin; her değişiklik system_fix_log\'a yazılır.';
}

/* ---------------------------------------------------------------------------
 * Yardımcılar
 * ------------------------------------------------------------------------- */

function flxseo_mcp_post_summary( $p ) {
	$issues = flxseo_audit_post( $p );
	return array(
		'id'         => $p->ID,
		'type'       => $p->post_type,
		'title'      => get_the_title( $p ),
		'status'     => $p->post_status,
		'slug'       => $p->post_name,
		'date'       => get_the_date( 'c', $p ),
		'modified'   => get_the_modified_date( 'c', $p ),
		'url'        => get_permalink( $p ),
		'categories' => 'post' === $p->post_type ? wp_list_pluck( get_the_category( $p->ID ), 'name' ) : array(),
		'seo_issues' => count( $issues ),
	);
}

function flxseo_mcp_seo_fields( $id ) {
	return array(
		'title'           => (string) flxseo_get( $id, 'title' ),
		'description'     => (string) flxseo_get( $id, 'desc' ),
		'focus_keyword'   => (string) flxseo_get( $id, 'focus' ),
		'noindex'         => (bool) flxseo_get( $id, 'noindex' ),
		'og_image'        => (string) flxseo_get( $id, 'image' ),
		'effective_title' => flxseo_get( $id, 'title' ) ? flxseo_get( $id, 'title' ) : get_the_title( $id ) . ' ' . flxseo_opt( 'sep' ) . ' ' . flxseo_opt( 'alt_name' ),
		'effective_desc'  => flxseo_post_description( $id ),
	);
}

function flxseo_mcp_save_seo( $id, $seo ) {
	$map = array(
		'title'         => 'title',
		'description'   => 'desc',
		'focus_keyword' => 'focus',
		'noindex'       => 'noindex',
		'og_image'      => 'image',
	);
	foreach ( $map as $in => $key ) {
		if ( ! array_key_exists( $in, $seo ) ) {
			continue;
		}
		$v = $seo[ $in ];
		if ( 'noindex' === $in ) {
			$v = (bool) $v;
		} elseif ( 'og_image' === $in ) {
			$v = esc_url_raw( $v );
		} else {
			$v = sanitize_text_field( $v );
		}
		update_post_meta( $id, '_flxseo_' . $key, $v );
	}
}

function flxseo_mcp_check_post( $id, $cap = 'edit_post' ) {
	$p = get_post( (int) $id );
	if ( ! $p ) {
		throw new Exception( 'İçerik bulunamadı: ' . $id );
	}
	if ( ! current_user_can( $cap, $p->ID ) ) {
		throw new Exception( 'Bu içerik için yetkiniz yok.' );
	}
	return $p;
}

/**
 * Markdown / HTML → Gutenberg blokları. Blok işaretlemesi gelirse aynen bırakılır.
 */
function flxseo_mcp_to_blocks( $content, $format = '' ) {
	$content = (string) $content;
	if ( '' === trim( $content ) || false !== strpos( $content, '<!-- wp:' ) || 'blocks' === $format ) {
		return $content;
	}
	if ( 'markdown' === $format || ( 'html' !== $format && ! preg_match( '/<(p|h[1-6]|ul|ol|div|figure|img|table|blockquote)\b/i', $content ) ) ) {
		$content = flxseo_markdown_to_html( $content );
	}
	return flxseo_html_to_blocks( $content );
}

function flxseo_md_inline( $s ) {
	$s = esc_html( $s );
	$s = preg_replace( '/!\[([^\]]*)\]\(([^)\s]+)\)/', '<img src="$2" alt="$1">', $s );
	$s = preg_replace( '/\[([^\]]+)\]\(([^)\s]+)\)/', '<a href="$2">$1</a>', $s );
	$s = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s );
	$s = preg_replace( '/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/', '<em>$1</em>', $s );
	return preg_replace( '/`([^`]+)`/', '<code>$1</code>', $s );
}

function flxseo_markdown_to_html( $md ) {
	$lines = preg_split( '/\r?\n/', $md );
	$html  = '';
	$para  = array();
	$list  = null;
	$items = array();
	$flush = function () use ( &$html, &$para, &$list, &$items ) {
		if ( $para ) {
			$html .= '<p>' . implode( ' ', $para ) . "</p>\n";
			$para  = array();
		}
		if ( $list ) {
			$html .= "<$list><li>" . implode( '</li><li>', $items ) . "</li></$list>\n";
			$list  = null;
			$items = array();
		}
	};
	foreach ( $lines as $line ) {
		$t = trim( $line );
		if ( '' === $t ) {
			$flush();
		} elseif ( preg_match( '/^(#{1,6})\s+(.*)$/', $t, $m ) ) {
			$flush();
			$lvl   = max( 2, strlen( $m[1] ) );
			$html .= "<h$lvl>" . flxseo_md_inline( $m[2] ) . "</h$lvl>\n";
		} elseif ( preg_match( '/^[-*+]\s+(.*)$/', $t, $m ) || preg_match( '/^\d+[.)]\s+(.*)$/', $t, $m2 ) ) {
			$type = isset( $m2 ) && $m2 ? 'ol' : 'ul';
			if ( $para || ( $list && $list !== $type ) ) {
				$flush();
			}
			$list    = $type;
			$items[] = flxseo_md_inline( $m ? $m[1] : $m2[1] );
			unset( $m2 );
		} elseif ( preg_match( '/^>\s?(.*)$/', $t, $m ) ) {
			$flush();
			$html .= '<blockquote><p>' . flxseo_md_inline( $m[1] ) . "</p></blockquote>\n";
		} elseif ( preg_match( '/^!\[([^\]]*)\]\(([^)\s]+)\)$/', $t, $m ) ) {
			$flush();
			$html .= '<figure><img src="' . esc_url( $m[2] ) . '" alt="' . esc_attr( $m[1] ) . "\"></figure>\n";
		} elseif ( preg_match( '/^(-{3,}|\*{3,})$/', $t ) ) {
			$flush();
			$html .= "<hr>\n";
		} else {
			if ( $list ) {
				$flush();
			}
			$para[] = flxseo_md_inline( $t );
		}
		unset( $m2 );
	}
	$flush();
	return $html;
}

function flxseo_html_to_blocks( $html ) {
	if ( ! class_exists( 'DOMDocument' ) ) {
		return '<!-- wp:html -->' . $html . '<!-- /wp:html -->';
	}
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div id="flxroot">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	$root = $doc->getElementById( 'flxroot' );
	$out  = array();
	$inner = function ( $node ) use ( $doc ) {
		$s = '';
		foreach ( $node->childNodes as $c ) {
			$s .= $doc->saveHTML( $c );
		}
		return trim( $s );
	};
	foreach ( iterator_to_array( $root->childNodes ) as $n ) {
		if ( XML_TEXT_NODE === $n->nodeType ) {
			$t = trim( $n->textContent );
			if ( '' !== $t ) {
				$out[] = "<!-- wp:paragraph -->\n<p>" . esc_html( $t ) . "</p>\n<!-- /wp:paragraph -->";
			}
			continue;
		}
		if ( XML_ELEMENT_NODE !== $n->nodeType ) {
			continue;
		}
		$tag = strtolower( $n->nodeName );
		if ( 'p' === $tag ) {
			$imgs = $n->getElementsByTagName( 'img' );
			if ( 1 === $imgs->length && '' === trim( $n->textContent ) ) {
				$n   = $imgs->item( 0 );
				$tag = 'img';
			} else {
				$out[] = "<!-- wp:paragraph -->\n<p>" . $inner( $n ) . "</p>\n<!-- /wp:paragraph -->";
				continue;
			}
		}
		if ( preg_match( '/^h([1-6])$/', $tag, $m ) ) {
			$lvl   = max( 2, (int) $m[1] );
			$attr  = 2 === $lvl ? '' : ' {"level":' . $lvl . '}';
			$out[] = "<!-- wp:heading$attr -->\n<h$lvl class=\"wp-block-heading\">" . $inner( $n ) . "</h$lvl>\n<!-- /wp:heading -->";
		} elseif ( 'ul' === $tag || 'ol' === $tag ) {
			$li = array();
			foreach ( $n->childNodes as $c ) {
				if ( XML_ELEMENT_NODE === $c->nodeType && 'li' === strtolower( $c->nodeName ) ) {
					$li[] = "<!-- wp:list-item -->\n<li>" . $inner( $c ) . "</li>\n<!-- /wp:list-item -->";
				}
			}
			$attr  = 'ol' === $tag ? ' {"ordered":true}' : '';
			$out[] = "<!-- wp:list$attr -->\n<$tag class=\"wp-block-list\">" . implode( "\n", $li ) . "</$tag>\n<!-- /wp:list -->";
		} elseif ( 'img' === $tag || 'figure' === $tag ) {
			$img = 'img' === $tag ? $n : $n->getElementsByTagName( 'img' )->item( 0 );
			if ( ! $img ) {
				$out[] = "<!-- wp:html -->\n" . $doc->saveHTML( $n ) . "\n<!-- /wp:html -->";
				continue;
			}
			$src   = $img->getAttribute( 'src' );
			$alt   = $img->getAttribute( 'alt' );
			$aid   = attachment_url_to_postid( $src );
			$cap   = 'figure' === $tag && $n->getElementsByTagName( 'figcaption' )->item( 0 ) ? $inner( $n->getElementsByTagName( 'figcaption' )->item( 0 ) ) : '';
			$attrs = array( 'sizeSlug' => 'large' );
			if ( $aid ) {
				$attrs = array( 'id' => $aid ) + $attrs;
			}
			$out[] = '<!-- wp:image ' . wp_json_encode( $attrs ) . " -->\n<figure class=\"wp-block-image size-large\"><img src=\"" . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '"' . ( $aid ? ' class="wp-image-' . $aid . '"' : '' ) . '/>' . ( $cap ? '<figcaption class="wp-element-caption">' . $cap . '</figcaption>' : '' ) . "</figure>\n<!-- /wp:image -->";
		} elseif ( 'blockquote' === $tag ) {
			$out[] = "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>" . trim( wp_strip_all_tags( $inner( $n ), false ) ) . "</p>\n<!-- /wp:paragraph --></blockquote>\n<!-- /wp:quote -->";
		} elseif ( 'hr' === $tag ) {
			$out[] = "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->";
		} else {
			$out[] = "<!-- wp:html -->\n" . $doc->saveHTML( $n ) . "\n<!-- /wp:html -->";
		}
	}
	return implode( "\n\n", $out );
}

function flxseo_mcp_terms( $names, $tax, $create = true ) {
	$ids = array();
	foreach ( (array) $names as $n ) {
		if ( is_numeric( $n ) ) {
			$ids[] = (int) $n;
			continue;
		}
		$t = get_term_by( 'name', $n, $tax );
		if ( ! $t ) {
			$t = get_term_by( 'slug', sanitize_title( $n ), $tax );
		}
		if ( $t ) {
			$ids[] = (int) $t->term_id;
		} elseif ( $create && current_user_can( get_taxonomy( $tax )->cap->manage_terms ) ) {
			$r = wp_insert_term( $n, $tax );
			if ( ! is_wp_error( $r ) ) {
				$ids[] = (int) $r['term_id'];
			}
		}
	}
	return $ids;
}

function flxseo_mcp_sideload( $url, $post_id = 0, $alt = '', $title = '', $base64 = '', $filename = '' ) {
	if ( ! current_user_can( 'upload_files' ) ) {
		throw new Exception( 'Dosya yükleme yetkiniz yok.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	if ( $base64 ) {
		$bin = base64_decode( preg_replace( '#^data:[^;]+;base64,#', '', $base64 ), true );
		if ( false === $bin ) {
			throw new Exception( 'base64 çözülemedi.' );
		}
		$tmp = wp_tempnam( $filename ? $filename : 'mcp-upload' );
		file_put_contents( $tmp, $bin ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$name = $filename ? $filename : 'gorsel.jpg';
	} else {
		$tmp = download_url( esc_url_raw( $url ), 60 );
		if ( is_wp_error( $tmp ) ) {
			throw new Exception( 'İndirilemedi: ' . $tmp->get_error_message() );
		}
		$name = $filename ? $filename : basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( ! preg_match( '/\.(jpe?g|png|gif|webp|avif|svg|pdf)$/i', $name ) ) {
			$name .= '.jpg';
		}
	}
	$id = media_handle_sideload( array( 'name' => sanitize_file_name( $name ), 'tmp_name' => $tmp ), (int) $post_id, $title ? $title : null );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		throw new Exception( $id->get_error_message() );
	}
	if ( $alt ) {
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
	}
	return $id;
}

function flxseo_mcp_theme_patterns() {
	$out = array();
	/** Listelenen desen ad alanları: tema + ön ayar eklentilerinin eklediği ad alanları. */
	$ns = (array) apply_filters( 'flxseo_pattern_namespaces', array( get_stylesheet() ) );
	foreach ( WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $p ) {
		$ok = false;
		foreach ( $ns as $n ) {
			if ( 0 === strpos( $p['name'], $n . '/' ) ) {
				$ok = true;
				break;
			}
		}
		if ( ! $ok ) {
			continue;
		}
		$out[ $p['name'] ] = $p;
	}
	return $out;
}

function flxseo_mcp_nav_links() {
	$part = get_block_template( get_stylesheet() . '//header', 'wp_template_part' );
	$html = $part ? $part->content : '';
	$out  = array();
	foreach ( parse_blocks( $html ) as $b ) {
		$stack = array( $b );
		while ( $stack ) {
			$x = array_pop( $stack );
			if ( 'core/navigation-link' === $x['blockName'] ) {
				$out[] = array( 'label' => $x['attrs']['label'] ?? '', 'url' => $x['attrs']['url'] ?? '' );
			}
			if ( 'core/navigation' === $x['blockName'] && ! empty( $x['attrs']['ref'] ) ) {
				$nav = get_post( (int) $x['attrs']['ref'] );
				if ( $nav ) {
					$stack = array_merge( $stack, array_reverse( parse_blocks( $nav->post_content ) ) );
				}
			}
			$stack = array_merge( $stack, array_reverse( $x['innerBlocks'] ) );
		}
	}
	return $out;
}

/**
 * create_content ve update_content için ortak alanlar.
 */
function flxseo_mcp_apply( $id, $a, $is_new ) {
	$post = get_post( $id );
	if ( ! empty( $a['categories'] ) && 'post' === $post->post_type ) {
		wp_set_post_categories( $id, flxseo_mcp_terms( $a['categories'], 'category' ) );
	}
	if ( isset( $a['tags'] ) && is_object_in_taxonomy( $post->post_type, 'post_tag' ) ) {
		wp_set_post_terms( $id, flxseo_mcp_terms( $a['tags'], 'post_tag' ), 'post_tag' );
	}
	if ( ! empty( $a['featured_image_id'] ) ) {
		set_post_thumbnail( $id, (int) $a['featured_image_id'] );
	} elseif ( ! empty( $a['featured_image_url'] ) ) {
		$aid = flxseo_mcp_sideload( $a['featured_image_url'], $id, $a['featured_image_alt'] ?? get_the_title( $id ) );
		set_post_thumbnail( $id, $aid );
	}
	if ( ! empty( $a['template'] ) ) {
		update_post_meta( $id, '_wp_page_template', sanitize_text_field( $a['template'] ) );
	}
	if ( ! empty( $a['seo'] ) && is_array( $a['seo'] ) ) {
		flxseo_mcp_save_seo( $id, $a['seo'] );
	}
}

function flxseo_mcp_status( $status, $type ) {
	$status = $status ? sanitize_key( $status ) : 'draft';
	if ( ! in_array( $status, array( 'draft', 'publish', 'pending', 'private', 'future' ), true ) ) {
		$status = 'draft';
	}
	$pto = get_post_type_object( $type );
	if ( in_array( $status, array( 'publish', 'future', 'private' ), true ) && ! current_user_can( $pto->cap->publish_posts ) ) {
		$status = 'pending';
	}
	return $status;
}

/* ---------------------------------------------------------------------------
 * Araçlar
 * ------------------------------------------------------------------------- */

/**
 * Araç listesi. Diğer eklentiler ve tema `flxseo_mcp_tools` filtresiyle araç ekleyebilir:
 * 'ad' => array( title, description, schema, run( $args ), readonly?, cap?, log? ). Her çağrı flxseo_mcp_log'a yazılır;
 * 'log' => false olan aracın yalnız hatalı çağrıları yazılır (ör. TV panelinin dakikalık yoklaması).
 */
function flxseo_mcp_tools() {
	$tools = (array) apply_filters( 'flxseo_mcp_tools', flxseo_mcp_tools_core() );
	foreach ( $tools as $name => $t ) {
		if ( ! is_array( $t ) || empty( $t['run'] ) || ! is_callable( $t['run'] ) ) {
			unset( $tools[ $name ] );
			continue;
		}
		$tools[ $name ] += array( 'title' => $name, 'description' => '', 'schema' => array( 'type' => 'object', 'properties' => new stdClass() ) );
	}
	return $tools;
}

function flxseo_mcp_tools_core() {
	$types   = flxseo_post_types();
	$seo     = array(
		'type'        => 'object',
		'description' => 'SEO alanları (isteğe bağlı).',
		'properties'  => array(
			'title'         => array( 'type' => 'string', 'description' => 'SEO başlığı (≤60 karakter). Boşsa "Başlık – Site adı".' ),
			'description'   => array( 'type' => 'string', 'description' => 'Meta açıklama (120–160 karakter).' ),
			'focus_keyword' => array( 'type' => 'string' ),
			'noindex'       => array( 'type' => 'boolean' ),
			'og_image'      => array( 'type' => 'string', 'description' => 'Paylaşım görseli URL (boşsa öne çıkan görsel).' ),
		),
	);
	$content = array(
		'title'              => array( 'type' => 'string' ),
		'content'            => array( 'type' => 'string', 'description' => 'Gövde: Gutenberg blok işaretlemesi, HTML veya Markdown.' ),
		'format'             => array( 'type' => 'string', 'enum' => array( 'auto', 'blocks', 'html', 'markdown' ), 'default' => 'auto' ),
		'excerpt'            => array( 'type' => 'string' ),
		'slug'               => array( 'type' => 'string' ),
		'status'             => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'pending', 'private', 'future' ) ),
		'date'               => array( 'type' => 'string', 'description' => 'Yayın tarihi (ISO 8601), status=future için.' ),
		'categories'         => array( 'type' => 'array', 'items' => array( 'type' => array( 'string', 'integer' ) ), 'description' => 'Yalnızca yazılar (post). Ad veya ID; yoksa oluşturulur.' ),
		'tags'               => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
		'featured_image_id'  => array( 'type' => 'integer' ),
		'featured_image_url' => array( 'type' => 'string', 'description' => 'URL verilirse medya kütüphanesine indirilip öne çıkan görsel yapılır.' ),
		'featured_image_alt' => array( 'type' => 'string' ),
		'parent'             => array( 'type' => 'integer', 'description' => 'Üst sayfa ID (sayfalar).' ),
		'template'           => array( 'type' => 'string', 'description' => 'Sayfa şablonu, ör. "page-full" (tam genişlik, pattern\'li sayfalar için).' ),
		'seo'                => $seo,
	);

	return array(
		'site_info'          => array(
			'title'       => 'Site bilgisi',
			'description' => 'Site adı, adres, dil, içerik türleri, kategoriler, menü bağlantıları, SEO ayarları ve içerik sayıları.',
			'readonly'    => true,
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'run'         => function () use ( $types ) {
				$counts = array();
				foreach ( $types as $t ) {
					$c            = wp_count_posts( $t );
					$counts[ $t ] = array( 'publish' => (int) ( $c->publish ?? 0 ), 'draft' => (int) ( $c->draft ?? 0 ) );
				}
				return array(
					'name'        => get_bloginfo( 'name' ),
					'tagline'     => get_bloginfo( 'description' ),
					'url'         => home_url( '/' ),
					'language'    => determine_locale(),
					'theme'       => wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ),
					'post_types'  => $counts,
					'categories'  => array_map(
						fn( $t ) => array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count ),
						get_categories( array( 'hide_empty' => false ) )
					),
					'menu'        => flxseo_mcp_nav_links(),
					'front_page'  => (int) get_option( 'page_on_front' ),
					'seo'         => array(
						'site_name'  => flxseo_opt( 'site_name' ),
						'home_title' => flxseo_opt( 'home_title' ),
						'home_desc'  => flxseo_opt( 'home_desc' ),
						'other_seo_plugin_active' => flxseo_other_seo_plugin(),
					),
					'woocommerce' => class_exists( 'WooCommerce' ),
				);
			},
		),
		'list_content'       => array(
			'title'       => 'İçerikleri listele',
			'description' => 'Yazı, sayfa, proje, etkinlik vb. listeler. Arama, kategori ve durum filtresi; her satırda SEO öneri sayısı.',
			'readonly'    => true,
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'post_type' => array( 'type' => 'string', 'enum' => $types, 'default' => 'post' ),
					'status'    => array( 'type' => 'string', 'default' => 'any' ),
					'search'    => array( 'type' => 'string' ),
					'category'  => array( 'type' => 'string', 'description' => 'Kategori adı veya slug (yazılar).' ),
					'per_page'  => array( 'type' => 'integer', 'default' => 20, 'maximum' => 100 ),
					'page'      => array( 'type' => 'integer', 'default' => 1 ),
				),
			),
			'run'         => function ( $a ) {
				$args = array(
					'post_type'      => sanitize_key( $a['post_type'] ?? 'post' ),
					'post_status'    => sanitize_key( $a['status'] ?? 'any' ),
					's'              => sanitize_text_field( $a['search'] ?? '' ),
					'posts_per_page' => min( 100, max( 1, (int) ( $a['per_page'] ?? 20 ) ) ),
					'paged'          => max( 1, (int) ( $a['page'] ?? 1 ) ),
				);
				if ( ! empty( $a['category'] ) ) {
					$args['category_name'] = sanitize_title( $a['category'] );
				}
				$q = new WP_Query( $args );
				return array(
					'total' => (int) $q->found_posts,
					'pages' => (int) $q->max_num_pages,
					'items' => array_map( 'flxseo_mcp_post_summary', $q->posts ),
				);
			},
		),
		'get_content'        => array(
			'title'       => 'İçeriği getir',
			'description' => 'Bir içeriğin başlık, blok gövdesi, özet, kategoriler, görsel, SEO alanları ve SEO önerileri.',
			'readonly'    => true,
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'id' => array( 'type' => 'integer' ) ),
				'required'   => array( 'id' ),
			),
			'run'         => function ( $a ) {
				$p = flxseo_mcp_check_post( $a['id'] ?? 0 );
				return flxseo_mcp_post_summary( $p ) + array(
					'content'        => $p->post_content,
					'excerpt'        => $p->post_excerpt,
					'parent'         => $p->post_parent,
					'template'       => get_page_template_slug( $p ),
					'tags'           => wp_list_pluck( (array) get_the_tags( $p->ID ), 'name' ),
					'featured_image' => get_the_post_thumbnail_url( $p, 'large' ) ? get_the_post_thumbnail_url( $p, 'large' ) : null,
					'seo'            => flxseo_mcp_seo_fields( $p->ID ),
					'seo_issues'     => flxseo_audit_post( $p ),
				);
			},
		),
		'create_content'     => array(
			'title'       => 'İçerik oluştur',
			'description' => 'Yeni yazı/sayfa/proje/etkinlik oluşturur (varsayılan taslak). patterns ile temanın hazır bölümleri sayfaya eklenir (sırayla, gövdeden önce).',
			'cap'         => 'edit_posts',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'post_type' => array( 'type' => 'string', 'enum' => $types, 'default' => 'post' ),
					'patterns'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'list_patterns\'taki adlar, ör. "fluxwp/hero".' ),
				) + $content,
				'required'   => array( 'title' ),
			),
			'run'         => function ( $a ) {
				$type = sanitize_key( $a['post_type'] ?? 'post' );
				$pto  = get_post_type_object( $type );
				if ( ! $pto || ! current_user_can( $pto->cap->create_posts ) ) {
					throw new Exception( 'Bu içerik türünü oluşturma yetkiniz yok.' );
				}
				$body = '';
				$pats = flxseo_mcp_theme_patterns();
				foreach ( (array) ( $a['patterns'] ?? array() ) as $slug ) {
					if ( isset( $pats[ $slug ] ) ) {
						$body .= $pats[ $slug ]['content'] . "\n\n";
					}
				}
				$body  .= flxseo_mcp_to_blocks( $a['content'] ?? '', $a['format'] ?? '' );
				$status = flxseo_mcp_status( $a['status'] ?? 'draft', $type );
				$data   = array(
					'post_type'    => $type,
					'post_title'   => sanitize_text_field( $a['title'] ),
					'post_content' => $body,
					'post_excerpt' => sanitize_textarea_field( $a['excerpt'] ?? '' ),
					'post_status'  => $status,
					'post_name'    => sanitize_title( $a['slug'] ?? '' ),
					'post_parent'  => (int) ( $a['parent'] ?? 0 ),
				);
				if ( ! empty( $a['date'] ) ) {
					$data['post_date'] = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( $a['date'] ) ) );
				}
				$id = wp_insert_post( wp_slash( $data ), true );
				if ( is_wp_error( $id ) ) {
					throw new Exception( $id->get_error_message() );
				}
				flxseo_mcp_apply( $id, $a, true );
				return array( 'created' => true, 'edit_url' => get_edit_post_link( $id, 'raw' ) ) + flxseo_mcp_post_summary( get_post( $id ) );
			},
		),
		'update_content'     => array(
			'title'       => 'İçeriği güncelle',
			'description' => 'Var olan içeriğin yalnızca verilen alanlarını değiştirir (başlık, gövde, özet, durum, kategoriler, görsel, SEO).',
			'cap'         => 'edit_posts',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'id' => array( 'type' => 'integer' ) ) + $content,
				'required'   => array( 'id' ),
			),
			'run'         => function ( $a ) {
				$p    = flxseo_mcp_check_post( $a['id'] ?? 0 );
				$data = array( 'ID' => $p->ID );
				if ( isset( $a['title'] ) ) {
					$data['post_title'] = sanitize_text_field( $a['title'] );
				}
				if ( isset( $a['content'] ) ) {
					$data['post_content'] = flxseo_mcp_to_blocks( $a['content'], $a['format'] ?? '' );
				}
				if ( isset( $a['excerpt'] ) ) {
					$data['post_excerpt'] = sanitize_textarea_field( $a['excerpt'] );
				}
				if ( isset( $a['slug'] ) ) {
					$data['post_name'] = sanitize_title( $a['slug'] );
				}
				if ( isset( $a['status'] ) ) {
					$data['post_status'] = flxseo_mcp_status( $a['status'], $p->post_type );
				}
				if ( isset( $a['parent'] ) ) {
					$data['post_parent'] = (int) $a['parent'];
				}
				if ( ! empty( $a['date'] ) ) {
					$data['post_date']     = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( $a['date'] ) ) );
					$data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', strtotime( $a['date'] ) );
				}
				if ( count( $data ) > 1 ) {
					$r = wp_update_post( wp_slash( $data ), true );
					if ( is_wp_error( $r ) ) {
						throw new Exception( $r->get_error_message() );
					}
				}
				flxseo_mcp_apply( $p->ID, $a, false );
				return array( 'updated' => true ) + flxseo_mcp_post_summary( get_post( $p->ID ) );
			},
		),
		'delete_content'     => array(
			'title'       => 'İçeriği çöpe taşı',
			'description' => 'İçeriği çöp kutusuna taşır (kalıcı silmez; WordPress\'ten geri alınabilir).',
			'cap'         => 'edit_posts',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'id' => array( 'type' => 'integer' ) ),
				'required'   => array( 'id' ),
			),
			'run'         => function ( $a ) {
				$p = flxseo_mcp_check_post( $a['id'] ?? 0, 'delete_post' );
				wp_trash_post( $p->ID );
				return array( 'trashed' => true, 'id' => $p->ID );
			},
		),
		'set_seo'            => array(
			'title'       => 'SEO alanlarını ayarla',
			'description' => 'Bir içeriğin SEO başlığı, meta açıklaması, odak kelimesi, noindex ve paylaşım görseli.',
			'cap'         => 'edit_posts',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'id' => array( 'type' => 'integer' ) ) + $seo['properties'],
				'required'   => array( 'id' ),
			),
			'run'         => function ( $a ) {
				$p = flxseo_mcp_check_post( $a['id'] ?? 0 );
				flxseo_mcp_save_seo( $p->ID, $a );
				return array( 'id' => $p->ID, 'seo' => flxseo_mcp_seo_fields( $p->ID ), 'remaining_issues' => flxseo_audit_post( $p ) );
			},
		),
		'seo_audit'          => array(
			'title'       => 'SEO kontrolü',
			'description' => 'id verilirse o içeriğin SEO önerileri; verilmezse yayındaki içeriklerin eksik listesi (en çok sorunlu önce) ve özet.',
			'readonly'    => true,
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'id'        => array( 'type' => 'integer' ),
					'post_type' => array( 'type' => 'string' ),
					'limit'     => array( 'type' => 'integer', 'default' => 50, 'maximum' => 300 ),
				),
			),
			'run'         => function ( $a ) {
				if ( ! empty( $a['id'] ) ) {
					$p = flxseo_mcp_check_post( $a['id'] );
					return array( 'id' => $p->ID, 'title' => get_the_title( $p ), 'seo' => flxseo_mcp_seo_fields( $p->ID ), 'issues' => flxseo_audit_post( $p ) );
				}
				$r          = flxseo_audit_site( 300, sanitize_key( $a['post_type'] ?? '' ) );
				$r['items'] = array_slice( $r['items'], 0, min( 300, max( 1, (int) ( $a['limit'] ?? 50 ) ) ) );
				return $r;
			},
		),
		'list_patterns'      => array(
			'title'       => 'Tema bölümleri (pattern)',
			'description' => 'Temanın hazır tasarım bölümleri: hero, istatistik, katkı kartları, bağış, iletişim vb. Sayfa kurarken create_content.patterns ile kullanın.',
			'readonly'    => true,
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'run'         => function () {
				$out = array();
				foreach ( flxseo_mcp_theme_patterns() as $name => $p ) {
					$out[] = array(
						'name'        => $name,
						'title'       => $p['title'],
						'description' => $p['description'] ?? '',
						'categories'  => $p['categories'] ?? array(),
					);
				}
				return array( 'patterns' => $out );
			},
		),
		'get_pattern'        => array(
			'title'       => 'Bölüm kodunu getir',
			'description' => 'Bir tema bölümünün blok işaretlemesi. Metinleri değiştirip content olarak kullanabilirsiniz.',
			'readonly'    => true,
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'name' => array( 'type' => 'string' ) ),
				'required'   => array( 'name' ),
			),
			'run'         => function ( $a ) {
				$p = flxseo_mcp_theme_patterns()[ $a['name'] ?? '' ] ?? null;
				if ( ! $p ) {
					throw new Exception( 'Pattern bulunamadı.' );
				}
				return array( 'name' => $p['name'], 'title' => $p['title'], 'content' => $p['content'] );
			},
		),
		'upload_media'       => array(
			'title'       => 'Görsel yükle',
			'description' => 'URL\'den (veya base64) medya kütüphanesine dosya yükler; alt metin verin.',
			'cap'         => 'upload_files',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'url'      => array( 'type' => 'string' ),
					'base64'   => array( 'type' => 'string' ),
					'filename' => array( 'type' => 'string' ),
					'alt'      => array( 'type' => 'string' ),
					'title'    => array( 'type' => 'string' ),
					'attach_to' => array( 'type' => 'integer' ),
				),
			),
			'run'         => function ( $a ) {
				if ( empty( $a['url'] ) && empty( $a['base64'] ) ) {
					throw new Exception( 'url veya base64 gerekli.' );
				}
				$id = flxseo_mcp_sideload( $a['url'] ?? '', (int) ( $a['attach_to'] ?? 0 ), $a['alt'] ?? '', $a['title'] ?? '', $a['base64'] ?? '', $a['filename'] ?? '' );
				return array( 'id' => $id, 'url' => wp_get_attachment_url( $id ), 'alt' => get_post_meta( $id, '_wp_attachment_image_alt', true ) );
			},
		),
		'list_media'         => array(
			'title'       => 'Medyayı listele',
			'description' => 'Medya kütüphanesi; missing_alt=true ile alt metni olmayan görseller.',
			'readonly'    => true,
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'search'      => array( 'type' => 'string' ),
					'missing_alt' => array( 'type' => 'boolean' ),
					'per_page'    => array( 'type' => 'integer', 'default' => 30, 'maximum' => 100 ),
					'page'        => array( 'type' => 'integer', 'default' => 1 ),
				),
			),
			'run'         => function ( $a ) {
				$args = array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'post_mime_type' => 'image',
					's'              => sanitize_text_field( $a['search'] ?? '' ),
					'posts_per_page' => min( 100, max( 1, (int) ( $a['per_page'] ?? 30 ) ) ),
					'paged'          => max( 1, (int) ( $a['page'] ?? 1 ) ),
				);
				if ( ! empty( $a['missing_alt'] ) ) {
					$args['meta_query'] = array(
						'relation' => 'OR',
						array( 'key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS' ),
						array( 'key' => '_wp_attachment_image_alt', 'value' => '' ),
					);
				}
				$q = new WP_Query( $args );
				return array(
					'total' => (int) $q->found_posts,
					'items' => array_map(
						fn( $p ) => array(
							'id'       => $p->ID,
							'title'    => $p->post_title,
							'url'      => wp_get_attachment_url( $p->ID ),
							'alt'      => get_post_meta( $p->ID, '_wp_attachment_image_alt', true ),
							'attached' => $p->post_parent,
						),
						$q->posts
					),
				);
			},
		),
		'set_image_alt'      => array(
			'title'       => 'Görsel alt metni',
			'description' => 'Medya kütüphanesindeki görselin alt metnini (ve isteğe bağlı başlığını) ayarlar.',
			'cap'         => 'upload_files',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'id'    => array( 'type' => 'integer' ),
					'alt'   => array( 'type' => 'string' ),
					'title' => array( 'type' => 'string' ),
				),
				'required'   => array( 'id', 'alt' ),
			),
			'run'         => function ( $a ) {
				$p = flxseo_mcp_check_post( $a['id'] ?? 0 );
				if ( 'attachment' !== $p->post_type ) {
					throw new Exception( 'Bu bir medya öğesi değil.' );
				}
				update_post_meta( $p->ID, '_wp_attachment_image_alt', sanitize_text_field( $a['alt'] ) );
				if ( ! empty( $a['title'] ) ) {
					wp_update_post( array( 'ID' => $p->ID, 'post_title' => sanitize_text_field( $a['title'] ) ) );
				}
				return array( 'id' => $p->ID, 'alt' => $a['alt'] );
			},
		),
		'list_terms'         => array(
			'title'       => 'Kategori ve etiketler',
			'description' => 'Kategori/etiket listesi.',
			'readonly'    => true,
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'taxonomy' => array( 'type' => 'string', 'default' => 'category' ) ),
			),
			'run'         => function ( $a ) {
				$tax = sanitize_key( $a['taxonomy'] ?? 'category' );
				if ( ! taxonomy_exists( $tax ) ) {
					throw new Exception( 'Taksonomi yok: ' . $tax );
				}
				return array(
					'items' => array_map(
						fn( $t ) => array(
							'id'          => $t->term_id,
							'name'        => $t->name,
							'slug'        => $t->slug,
							'count'       => $t->count,
							'parent'      => $t->parent,
							'seo_title'   => get_term_meta( $t->term_id, '_flxseo_title', true ),
							'seo_desc'    => get_term_meta( $t->term_id, '_flxseo_desc', true ),
						),
						get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) )
					),
				);
			},
		),
		'update_term'        => array(
			'title'       => 'Kategori oluştur/güncelle',
			'description' => 'Kategori veya etiket oluşturur ya da adını, açıklamasını ve SEO alanlarını günceller.',
			'cap'         => 'manage_categories',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'taxonomy'    => array( 'type' => 'string', 'default' => 'category' ),
					'id'          => array( 'type' => 'integer', 'description' => 'Boşsa yeni oluşturulur.' ),
					'name'        => array( 'type' => 'string' ),
					'slug'        => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'seo_title'   => array( 'type' => 'string' ),
					'seo_desc'    => array( 'type' => 'string' ),
				),
			),
			'run'         => function ( $a ) {
				$tax  = sanitize_key( $a['taxonomy'] ?? 'category' );
				$args = array_filter(
					array(
						'name'        => isset( $a['name'] ) ? sanitize_text_field( $a['name'] ) : null,
						'slug'        => isset( $a['slug'] ) ? sanitize_title( $a['slug'] ) : null,
						'description' => isset( $a['description'] ) ? sanitize_textarea_field( $a['description'] ) : null,
					),
					fn( $v ) => null !== $v
				);
				if ( ! empty( $a['id'] ) ) {
					$r = wp_update_term( (int) $a['id'], $tax, $args );
				} else {
					if ( empty( $a['name'] ) ) {
						throw new Exception( 'name gerekli.' );
					}
					$r = wp_insert_term( $args['name'], $tax, $args );
				}
				if ( is_wp_error( $r ) ) {
					throw new Exception( $r->get_error_message() );
				}
				foreach ( array( 'seo_title' => '_flxseo_title', 'seo_desc' => '_flxseo_desc' ) as $k => $m ) {
					if ( isset( $a[ $k ] ) ) {
						update_term_meta( $r['term_id'], $m, sanitize_text_field( $a[ $k ] ) );
					}
				}
				return array( 'id' => $r['term_id'], 'url' => get_term_link( (int) $r['term_id'], $tax ) );
			},
		),
		'update_site_seo'    => array(
			'title'       => 'Site SEO ayarları',
			'description' => 'Google\'daki site adı, ana sayfa başlığı/açıklaması, sosyal profiller, iletişim bilgileri.',
			'cap'         => 'manage_options',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'site_name'  => array( 'type' => 'string' ),
					'alt_name'   => array( 'type' => 'string' ),
					'home_title' => array( 'type' => 'string' ),
					'home_desc'  => array( 'type' => 'string' ),
					'same_as'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'phone'      => array( 'type' => 'string' ),
					'email'      => array( 'type' => 'string' ),
					'street'     => array( 'type' => 'string' ),
					'locality'   => array( 'type' => 'string' ),
				),
			),
			'run'         => function ( $a ) {
				$o = wp_parse_args( (array) get_option( 'flxseo', array() ), flxseo_defaults() );
				foreach ( array( 'site_name', 'alt_name', 'home_title', 'home_desc', 'phone', 'email', 'street', 'locality' ) as $k ) {
					if ( isset( $a[ $k ] ) ) {
						$o[ $k ] = $a[ $k ];
					}
				}
				if ( isset( $a['same_as'] ) ) {
					$o['same_as'] = implode( "\n", (array) $a['same_as'] );
				}
				update_option( 'flxseo', flxseo_sanitize( $o ) );
				return array( 'saved' => true, 'settings' => array_intersect_key( get_option( 'flxseo' ), array_flip( array( 'site_name', 'alt_name', 'home_title', 'home_desc', 'same_as', 'phone', 'email', 'street', 'locality' ) ) ) );
			},
		),
	);
}

function flxseo_mcp_prompts() {
	return array(
		'seo_duzelt'  => array(
			'title'       => 'SEO eksiklerini düzelt',
			'description' => 'Sitedeki içeriklerin SEO eksiklerini bulup düzeltir.',
			'arguments'   => array(
				array( 'name' => 'limit', 'description' => 'Kaç içerik (varsayılan 10)', 'required' => false ),
			),
			'text'        => function ( $a ) {
				$n = max( 1, (int) ( $a['limit'] ?? 10 ) );
				return "seo_audit ile sitedeki SEO eksiklerini listele. En çok sorunu olan $n içerik için sırayla: get_content ile oku, içeriğe uygun Türkçe SEO başlığı (≤60 karakter) ve meta açıklama (120–160 karakter) yaz, odak kelime belirle ve set_seo ile kaydet; alt metni olmayan görseller için list_media(missing_alt) ve set_image_alt kullan. Gövde metnini değiştirmen gerekiyorsa önce bana öner. Sonunda yaptıklarını tablo olarak özetle.";
			},
		),
		'haber_yaz'   => array(
			'title'       => 'Haber/duyuru yaz',
			'description' => 'Verilen konuda SEO uyumlu bir haber taslağı oluşturur.',
			'arguments'   => array(
				array( 'name' => 'konu', 'description' => 'Haberin konusu ve ayrıntıları', 'required' => true ),
			),
			'text'        => function ( $a ) {
				return 'Şu konuda derneğimiz için bir haber yaz: ' . ( $a['konu'] ?? '' ) . "\n\nTürkçe, samimi ama kurumsal bir dil kullan; 400–700 kelime, H2 ara başlıklar, sonda ilgili sayfalarımıza (bağış, projeler, iletişim) iç bağlantılar. site_info ile kategorileri gör ve uygun olanı seç. create_content ile taslak (draft) oluştur; SEO başlığı, meta açıklama ve odak kelimeyi de doldur. Bitince düzenleme bağlantısını ver.";
			},
		),
		'site_audit_fix' => array(
			'title'       => 'Site denetimini düzelt',
			'description' => 'PageSpeed tarzı site denetimindeki bulguları öncelik sırasıyla düzeltir.',
			'arguments'   => array(
				array( 'name' => 'pagespeed', 'description' => 'mobile, desktop ya da none (varsayılan none)', 'required' => false ),
			),
			'text'        => function ( $a ) {
				$ps = in_array( $a['pagespeed'] ?? '', array( 'mobile', 'desktop' ), true ) ? $a['pagespeed'] : 'none';
				return "Önce site_build_guide(topic=checklist) oku. Sonra site_audit(refresh=true, pagespeed=$ps) çalıştır. Bulguları öncelik sırasıyla ele al: owner=flx-seo ve fix_id olanları bana söyleyip system_fix ile uygula; owner=content olanları (alt metin, kopya sayfa, meta açıklama) MCP içerik araçlarıyla düzelt; owner=theme/server olanlar için hangi dosyada neyin değişeceğini kısa bir liste olarak öner. Sonunda site_audit'i yeniden çalıştırıp önce/sonra sayılarını tablo yap.";
			},
		),
		'sayfa_kur'   => array(
			'title'       => 'Tema bölümleriyle sayfa kur',
			'description' => 'Temanın hazır tasarım bölümlerini kullanarak yeni bir sayfa taslağı oluşturur.',
			'arguments'   => array(
				array( 'name' => 'sayfa', 'description' => 'Sayfanın amacı ve içeriği', 'required' => true ),
			),
			'text'        => function ( $a ) {
				return 'Şu sayfayı oluştur: ' . ( $a['sayfa'] ?? '' ) . "\n\nlist_patterns ile temanın bölümlerine bak, uygun olanları get_pattern ile al, metinlerini bu sayfaya göre Türkçe yeniden yaz (blok yapısını ve sınıfları bozmadan) ve create_content(post_type=page, template=page-full, status=draft) ile kaydet. SEO alanlarını doldur ve önizleme bağlantısını ver.";
			},
		),
	);
}

/* ---------------------------------------------------------------------------
 * Yönetim: anahtarlar ve bağlantı talimatları
 * ------------------------------------------------------------------------- */

add_action(
	'admin_post_flxseo_mcp',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Yetkisiz.' );
		}
		check_admin_referer( 'flxseo_mcp' );
		$do   = sanitize_key( $_POST['do'] ?? '' );
		$keys = flxseo_mcp_keys();
		if ( 'toggle' === $do ) {
			$o                = wp_parse_args( (array) get_option( 'flxseo', array() ), flxseo_defaults() );
			$o['mcp_enabled'] = empty( $_POST['mcp_enabled'] ) ? 0 : 1;
			update_option( 'flxseo', $o );
		} elseif ( 'create' === $do ) {
			$key                      = 'flxmcp_' . wp_generate_password( 40, false, false );
			$id                       = wp_generate_uuid4();
			$keys[ $id ]              = array(
				'name'      => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ) ?: 'Yapay zekâ',
				'hash'      => hash( 'sha256', $key ),
				'user'      => get_current_user_id(),
				'created'   => time(),
				'last_used' => 0,
				'hint'      => substr( $key, -4 ),
			);
			// Araç seçildiyse anahtar yalnız onlarla sınırlı (ör. TV paneli için yalnız order_summary).
			$tools = array_values( array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['tools'] ?? array() ) ), array_keys( flxseo_mcp_tools() ) ) );
			if ( $tools ) {
				$keys[ $id ]['tools'] = $tools;
			}
			update_option( 'flxseo_mcp_keys', $keys, false );
			set_transient( 'flxseo_new_key_' . get_current_user_id(), $key, 300 );
		} elseif ( 'revoke' === $do ) {
			unset( $keys[ sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) ) ] );
			update_option( 'flxseo_mcp_keys', $keys, false );
		}
		wp_safe_redirect( flxseo_admin_url( 'mcp' ) );
		exit;
	}
);

function flxseo_mcp_page( $o ) {
	$new  = get_transient( 'flxseo_new_key_' . get_current_user_id() );
	delete_transient( 'flxseo_new_key_' . get_current_user_id() );
	$url  = flxseo_mcp_url();
	$keys = flxseo_mcp_keys();
	$log  = array_slice( (array) get_option( 'flxseo_mcp_log', array() ), 0, 20 );
	$form = function ( $do, $inner, $button, $class = 'button' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
		wp_nonce_field( 'flxseo_mcp' );
		echo '<input type="hidden" name="action" value="flxseo_mcp"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">' . $inner; // phpcs:ignore WordPress.Security.EscapeOutput
		echo ' <button class="' . esc_attr( $class ) . '">' . esc_html( $button ) . '</button></form>';
	};
	$key_show = $new ? $new : 'ANAHTARINIZ';
	$json     = wp_json_encode(
		array(
			'mcpServers' => array(
				'yesil-turkiye' => array(
					'command' => 'npx',
					'args'    => array( '-y', 'mcp-remote', $url, '--header', 'Authorization:Bearer ' . $key_show ),
				),
			),
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	);
	?>
	<style>.flxseo-code{background:#1d2327;color:#f0f0f1;padding:12px 14px;border-radius:6px;overflow:auto;font:12px/1.5 Menlo,Consolas,monospace;white-space:pre}.flxseo-key{font:600 14px Menlo,Consolas,monospace;background:#fff8e5;border:1px solid #dba617;padding:10px 12px;border-radius:6px;display:inline-block;user-select:all}</style>
	<p>MCP (Model Context Protocol), Claude gibi yapay zekâ asistanlarının bu siteye bağlanıp içerik listelemesini, yazı ve sayfa oluşturmasını, SEO alanlarını doldurmasını ve görselleri yönetmesini sağlar. Asistan, anahtarı oluşturan kullanıcının yetkileriyle çalışır; yeni içerikler varsayılan olarak taslak açılır, silme yalnızca çöpe taşır.</p>
	<h2>1. MCP'yi aç</h2>
	<?php
	$form( 'toggle', '<label><input type="checkbox" name="mcp_enabled" value="1" ' . checked( $o['mcp_enabled'], 1, false ) . '> MCP sunucusu etkin</label>', 'Kaydet', 'button button-primary' );
	?>
	<p>Sunucu adresi: <code><?php echo esc_html( $url ); ?></code></p>
	<h2>2. Anahtar oluştur</h2>
	<?php if ( $new ) : ?>
		<div class="notice notice-success inline"><p><strong>Yeni anahtar (yalnızca şimdi gösterilir, kopyalayın):</strong></p><p><span class="flxseo-key"><?php echo esc_html( $new ); ?></span></p></div>
	<?php endif; ?>
	<?php
	$checks = '';
	foreach ( flxseo_mcp_tools() as $name => $t ) {
		$checks .= '<label style="display:block;margin:2px 0"><input type="checkbox" name="tools[]" value="' . esc_attr( $name ) . '"> <code>' . esc_html( $name ) . '</code> '
			. esc_html( $t['title'] ) . ( empty( $t['readonly'] ) ? '' : ' <span style="color:#007a1f">(yalnız okuma)</span>' ) . '</label>';
	}
	$scope = '<details style="margin:8px 0;max-width:820px"><summary>Araçları sınırla (isteğe bağlı)</summary>'
		. '<p class="description">Hiçbiri seçilmezse anahtar, oluşturan kullanıcının bütün yetkileriyle çalışır. Seçilirse yalnız işaretli araçları görür ve çalıştırır (ör. TV sipariş paneli için yalnız <code>order_summary</code>).</p>'
		. $checks . '</details>';
	$form( 'create', '<input name="name" placeholder="Ör. Claude – Ahmet" class="regular-text">' . $scope, 'Yeni anahtar oluştur', 'button button-secondary' );
	?>
	<?php if ( $keys ) : ?>
	<table class="widefat striped" style="max-width:820px;margin-top:12px"><thead><tr><th>Ad</th><th>Kullanıcı</th><th>Araçlar</th><th>Oluşturma</th><th>Son kullanım</th><th></th></tr></thead><tbody>
		<?php foreach ( $keys as $id => $k ) : $u = get_userdata( $k['user'] ); ?>
		<tr><td><?php echo esc_html( $k['name'] ); ?> <code>…<?php echo esc_html( $k['hint'] ?? '' ); ?></code></td><td><?php echo esc_html( $u ? $u->user_login : '?' ); ?></td><td><?php echo empty( $k['tools'] ) ? 'Tümü' : '<code>' . esc_html( implode( ', ', (array) $k['tools'] ) ) . '</code>'; ?></td><td><?php echo esc_html( wp_date( 'd.m.Y H:i', $k['created'] ) ); ?></td><td><?php echo $k['last_used'] ? esc_html( flx_time_ago( $k['last_used'] ) ) : '—'; ?></td>
		<td><?php $form( 'revoke', '<input type="hidden" name="id" value="' . esc_attr( $id ) . '">', 'İptal et', 'button-link-delete button-link' ); ?></td></tr>
		<?php endforeach; ?>
	</tbody></table>
	<?php endif; ?>
	<h2>3. Asistana bağla</h2>
	<p><strong>Claude Desktop / Claude Code / Cursor</strong> (claude_desktop_config.json → mcpServers):</p>
	<div class="flxseo-code"><?php echo esc_html( $json ); ?></div>
	<p><strong>Claude Code</strong> tek satır:</p>
	<div class="flxseo-code">claude mcp add --transport http yesil-turkiye <?php echo esc_html( $url ); ?> --header "Authorization: Bearer <?php echo esc_html( $key_show ); ?>"</div>
	<p><strong>Claude.ai › Ayarlar › Bağlayıcılar › Özel bağlayıcı ekle</strong> (başlık eklenemeyen yerler için anahtarlı adres; bu adresi kimseyle paylaşmayın):</p>
	<div class="flxseo-code"><?php echo esc_html( add_query_arg( 'key', $key_show, $url ) ); ?></div>
	<p class="description">Sunucu Apache ise ve "Geçersiz anahtar" hatası alırsanız .htaccess'e <code>SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1</code> ekleyin ya da anahtarlı adresi kullanın.</p>
	<p>Örnek istekler: <em>"Sitedeki SEO eksiklerini bul ve düzelt"</em>, <em>"Yenikapı tanıtım günleri için haber taslağı yaz"</em>, <em>"Tema bölümleriyle Kurumsal İş Birlikleri sayfası kur"</em>.</p>
	<?php if ( $log ) : ?>
	<h2>Son işlemler</h2>
	<table class="widefat striped" style="max-width:820px"><thead><tr><th>Zaman</th><th>Kullanıcı</th><th>Araç</th><th>Hedef</th><th>Sonuç</th></tr></thead><tbody>
		<?php foreach ( $log as $l ) : $u = get_userdata( $l['u'] ); ?>
		<tr><td><?php echo esc_html( wp_date( 'd.m H:i:s', $l['t'] ) ); ?></td><td><?php echo esc_html( $u ? $u->user_login : '?' ); ?></td><td><code><?php echo esc_html( $l['tool'] ); ?></code></td><td><?php echo esc_html( $l['on'] ); ?></td><td><?php echo $l['ok'] ? '✓' : '✗'; ?></td></tr>
		<?php endforeach; ?>
	</tbody></table>
	<?php endif; ?>
	<?php
}
