<?php
/**
 * Ortak MCP sunucusu (Model Context Protocol, Streamable HTTP, durumsuz).
 *
 * Adres: site.com/mcp (kısa adres, filtre flx_mcp_short_path) ve /wp-json/flx-mcp/v1/mcp (ikisi aynı sunucu).
 * Kimlik: OAuth 2.1 (oauth.php; istemci yalnız adresle bağlanır, WordPress girişi + onay),
 *         "Authorization: Bearer <anahtar>" (Flux AI & MCP › MCP), X-MCP-Key başlığı ya da
 *         WordPress Uygulama Parolası (Basic). Anahtar yalnız bu rotanın permission_callback'inde
 *         kullanıcı olur; sorgu dizesinden okunmaz. Araçlar anahtarı oluşturan kullanıcının yetkileriyle çalışır;
 *         anahtara araç listesi verildiyse yalnız o araçlar görünür ve çalışır.
 *
 * Eklentiler araçlarını `flx_mcp_tools` filtresiyle kaydeder (eski ad `flxseo_mcp_tools` da çalışır).
 * Aynı araçlar Flux AI sohbetinde de kullanılır (tools.php).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

const FLXAI_MCP_PROTOCOLS = array( '2025-06-18', '2025-03-26', '2024-11-05' );

/**
 * Flux SEO 1.4.x ve öncesi kendi MCP sunucusunu taşıyordu. O sürüm kuruluysa sunucuyu o çalıştırır
 * (aynı REST adresi iki kez kaydedilmesin); Flux AI yalnız uyarı gösterir.
 */
function flxai_mcp_legacy() {
	return function_exists( 'flxseo_mcp_handle' );
}

/** Sunucu adresi: kalıcı bağlantılar açıksa kısa adres (site.com/mcp), değilse REST adresi. */
function flxai_mcp_url() {
	$short = flxai_mcp_short_path();
	if ( '' !== $short && get_option( 'permalink_structure' ) && ! flxai_mcp_legacy() ) {
		return home_url( '/' . $short );
	}
	return flxai_mcp_rest_url();
}

function flxai_mcp_rest_url() {
	return rest_url( 'flx-mcp/v1/mcp' );
}

/**
 * Kısa adresin yolu (varsayılan "mcp"). Sitede aynı adlı sayfa varsa filtreyle değiştirilir ya da '' ile kapatılır.
 */
function flxai_mcp_short_path() {
	static $path = null;
	if ( null === $path || ! did_action( 'plugins_loaded' ) ) {
		$path = trim( (string) apply_filters( 'flx_mcp_short_path', 'mcp' ), '/' );
		$path = preg_match( '#^[A-Za-z0-9_\-/]{1,60}$#', $path ) ? $path : '';
	}
	return $path;
}

/** Site kökünün yolu (alt klasör kurulumunda "/wp"), sonda / yok. */
function flxai_home_path() {
	return untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
}

/** İsteğin site köküne göre yolu, baştaki ve sondaki / olmadan (ör. "mcp", ".well-known/oauth-protected-resource/mcp"). */
function flxai_request_rel_path() {
	$uri  = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$base = flxai_home_path();
	if ( '' !== $base && 0 === strpos( $uri, $base . '/' ) ) {
		$uri = substr( $uri, strlen( $base ) );
	}
	return trim( rawurldecode( $uri ), '/' );
}

function flxai_mcp_is_short_request() {
	$short = flxai_mcp_short_path();
	return '' !== $short && $short === flxai_request_rel_path();
}

/*
 * site.com/mcp → REST sunucusu. Yeniden yazma kuralı yok (flush gerekmez): istek WordPress'e ulaştığında
 * rest_route verilir, WordPress isteği REST olarak işler. Kimlik aranmadan önce (plugins_loaded) ayarlanır.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( flxai_mcp_legacy() || ! flxai_mcp_is_short_request() ) {
			return;
		}
		$_GET['rest_route']     = '/flx-mcp/v1/mcp';
		$_REQUEST['rest_route'] = '/flx-mcp/v1/mcp';
	},
	0
);

/* .well-known (OAuth keşfi): Flux App'ten önce (init 0) yanıt verir; Flux App kendi uçları için devam eder. */
add_action(
	'init',
	function () {
		$rel = flxai_request_rel_path();
		if ( 0 !== strpos( $rel, '.well-known/oauth-' ) || ! function_exists( 'flxai_oauth_owns' ) ) {
			return;
		}
		$rest = substr( $rel, strlen( '.well-known/' ) );
		if ( ! flxai_oauth_owns( $rest ) ) {
			return;
		}
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Headers: Authorization, Content-Type, MCP-Protocol-Version' );
		if ( 'OPTIONS' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			exit;
		}
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		$data = 0 === strpos( $rest, 'oauth-authorization-server' )
			? flxai_oauth_metadata()
			: flxai_oauth_resource_metadata( (string) substr( $rest, strlen( 'oauth-protected-resource' ) ) );
		echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	},
	0
);

function flxai_mcp_admin_url() {
	return admin_url( 'admin.php?page=flx-ai-mcp' );
}

/** MCP sunucusu açık mı? İlk okumada Flux SEO'daki eski aç/kapa değeri devralınır. */
function flxai_mcp_enabled() {
	$s = get_option( 'flxai_settings', array() );
	if ( ! is_array( $s ) || ! array_key_exists( 'mcp_enabled', $s ) ) {
		$old = (array) get_option( 'flxseo', array() );
		return ! empty( $old['mcp_enabled'] );
	}
	return ! empty( $s['mcp_enabled'] );
}

/* ---------------------------------------------------------------------------
 * Anahtarlar
 * ------------------------------------------------------------------------- */

/** Anahtarlar (yalnız özet saklanır). İlk okumada Flux SEO'nun eski anahtarları devralınır; bağlı asistanlar kopmaz. */
function flxai_mcp_keys() {
	$keys = get_option( 'flxai_mcp_keys', null );
	if ( null === $keys ) {
		$keys = (array) get_option( 'flxseo_mcp_keys', array() );
		update_option( 'flxai_mcp_keys', $keys, false );
	}
	return (array) $keys;
}

function flxai_mcp_find_key( $key ) {
	if ( ! $key || strlen( $key ) < 20 ) {
		return null;
	}
	$hash = hash( 'sha256', $key );
	foreach ( flxai_mcp_keys() as $id => $k ) {
		if ( ! empty( $k['hash'] ) && hash_equals( $k['hash'], $hash ) ) {
			if ( 'oauth' === ( $k['type'] ?? '' ) && (int) ( $k['exp'] ?? 0 ) < time() ) {
				return null; // Süresi dolmuş erişim belirteci: istemci yenileme belirteciyle yenisini alır.
			}
			return array( 'id' => $id ) + $k;
		}
	}
	return null;
}

/**
 * Bu istekteki anahtarın araç listesi. null = sınırsız (anahtar kapsamsız ya da Uygulama Parolası).
 * Yalnız flxai_mcp_permission() ayarlar.
 */
function flxai_mcp_scope( $set = false, $tools = null ) {
	static $scope = null;
	if ( $set ) {
		$scope = $tools;
	}
	return $scope;
}

function flxai_mcp_allowed( $name ) {
	$scope = flxai_mcp_scope();
	return null === $scope || in_array( flxai_tool_resolve( $name ), array_map( 'flxai_tool_resolve', $scope ), true );
}

/**
 * Bu kullanıcı MCP'de aracı görebilir ve çalıştırabilir mi?
 * Sıra: kapalı değil, anahtar kapsamı, cap (yoksa edit_posts), flx_mcp_tool_allowed.
 * Sohbet aynı cap ve filtreyi flxai_tool_allowed_for_user ile uygular; anahtar kapsamı yalnız burada.
 */
function flxai_mcp_tool_for_user( $name, array $tool, $user_id = 0 ) {
	return flxai_tool_allowed_for_user( $name, $tool, $user_id, ! flxai_tool_is_off( $name ) && flxai_mcp_allowed( $name ) );
}

/** Kaynak veya istem: cap yoksa edit_posts. Liste ve okuma aynı kontrolü kullanır. */
function flxai_mcp_item_allowed( array $item, $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	return user_can( $user_id, flxai_tool_required_cap( $item ) );
}

function flxai_mcp_request_key( WP_REST_Request $req ) {
	$auth = $req->get_header( 'authorization' );
	if ( ! $auth ) {
		// CGI/LiteSpeed Authorization'ı PHP'ye iletmez. WordPress uygulama parolalarıyla aynı sıra.
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			$auth = sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		} elseif ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$auth = sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}
	}
	if ( $auth && 0 === stripos( $auth, 'Bearer ' ) ) {
		return trim( substr( $auth, 7 ) );
	}
	if ( $req->get_header( 'x_mcp_key' ) ) {
		return trim( $req->get_header( 'x_mcp_key' ) );
	}
	return '';
}

/* ---------------------------------------------------------------------------
 * Kayıt: araçlar, istemler, kaynaklar, talimatlar
 * ------------------------------------------------------------------------- */

/**
 * Tüm MCP araçları (yetki ve kapalı listesi süzülmeden). Biçim: 'ad' => array( title, description, schema, run( $args ),
 * readonly?, cap?, log?, source?, aliases? ). Kaynaklar: Flux AI SDK (flx_ai_tool), `flx_mcp_tools` ve eski
 * `flxseo_mcp_tools` filtreleri. Aynı adı ikinci bir eklenti kaydederse çakışan tanım ayrılır (registry.php).
 * 'log' => false olan aracın yalnız hatalı çağrıları Son işlemler'e yazılır (ör. TV panelinin dakikalık yoklaması).
 */
function flxai_mcp_tools() {
	if ( flxai_mcp_legacy() && function_exists( 'flxseo_mcp_tools' ) ) {
		$tools = (array) flxseo_mcp_tools();
		return (array) apply_filters( 'flx_mcp_tools', $tools );
	}
	return flxai_mcp_registry()['tools'];
}

/**
 * İstem şablonları (prompts/list). 'ad' => array( title, description, arguments[], text( $args ) → string ).
 */
function flxai_mcp_prompts() {
	$list = (array) apply_filters( 'flx_mcp_prompts', array() );
	return array_filter( $list, fn( $p ) => is_array( $p ) && ! empty( $p['text'] ) && is_callable( $p['text'] ) );
}

/**
 * Kaynaklar (resources/list). 'uri' => array( name, title, description, mimeType, read() → string ).
 * `flx_mcp_resources` ve eski `flxseo_mcp_resources` filtreleri.
 */
function flxai_mcp_resources() {
	$res = (array) apply_filters( 'flx_mcp_resources', array() );
	$res = (array) apply_filters( 'flxseo_mcp_resources', $res );
	return array_filter( $res, fn( $r ) => is_array( $r ) && ! empty( $r['read'] ) && is_callable( $r['read'] ) );
}

/** Sunucu talimatları: genel kurallar + eklentilerin satırları (`flx_mcp_instructions`, satır dizisi). */
function flxai_mcp_instructions() {
	$lines = array(
		sprintf( 'This server manages the WordPress site "%1$s" (%2$s). Site language: %3$s; write content in that language unless the user asks otherwise.', get_bloginfo( 'name' ), home_url( '/' ), get_bloginfo( 'language' ) ),
		'- Tools run with the permissions of the user who created the key. Tools that are not marked read-only change data: tell the user what you are about to do first.',
		'- New content is created as a draft unless the user explicitly asks to publish. Deleting content only moves it to trash.',
	);
	$lines = (array) apply_filters( 'flx_mcp_instructions', $lines );
	return implode( "\n", array_filter( array_map( 'strval', $lines ) ) );
}

/* ---------------------------------------------------------------------------
 * REST
 * ------------------------------------------------------------------------- */

add_action(
	'rest_api_init',
	function () {
		if ( flxai_mcp_legacy() ) {
			return;
		}
		register_rest_route(
			'flx-mcp/v1',
			'/mcp',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => 'flxai_mcp_handle',
					'permission_callback' => 'flxai_mcp_permission',
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

function flxai_mcp_permission( WP_REST_Request $req ) {
	if ( ! flxai_mcp_enabled() ) {
		return new WP_Error( 'mcp_disabled', __( 'MCP is off. Turn it on in Flux AI & MCP › MCP.', 'flx-ai' ), array( 'status' => 403 ) );
	}
	$k = flxai_mcp_find_key( flxai_mcp_request_key( $req ) );
	flxai_mcp_scope( true, $k && ! empty( $k['tools'] ) ? array_values( (array) $k['tools'] ) : null );
	if ( $k ) {
		wp_set_current_user( (int) $k['user'] );
		$keys = flxai_mcp_keys();
		// Son kullanım dakikada bir yazılır (yoklayan cihazlar her istekte veritabanına yazmasın).
		if ( time() - (int) ( $keys[ $k['id'] ]['last_used'] ?? 0 ) > 60 ) {
			$keys[ $k['id'] ]['last_used'] = time();
			update_option( 'flxai_mcp_keys', $keys, false );
		}
	}
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'mcp_auth', __( 'Invalid or missing MCP key.', 'flx-ai' ), array( 'status' => 401 ) );
	}
	if ( ! current_user_can( FLXAI_CAP_MCP ) ) {
		return new WP_Error( 'mcp_cap', __( 'This user can\'t use MCP.', 'flx-ai' ), array( 'status' => 403 ) );
	}
	return true;
}

function flxai_mcp_handle( WP_REST_Request $req ) {
	$body = json_decode( $req->get_body(), true );
	if ( null === $body ) {
		return new WP_REST_Response( flxai_rpc_error( null, -32700, 'Parse error' ), 400 );
	}
	$batch = is_array( $body ) && array() !== $body && array_keys( $body ) === range( 0, count( $body ) - 1 );
	$msgs  = $batch ? $body : array( $body );
	$out   = array();
	foreach ( $msgs as $m ) {
		$r = flxai_mcp_dispatch( $m );
		if ( null !== $r ) {
			$out[] = $r;
		}
	}
	if ( ! $out ) {
		return new WP_REST_Response( null, 202 );
	}
	return new WP_REST_Response( $batch ? $out : $out[0], 200 );
}

function flxai_rpc_error( $id, $code, $msg ) {
	return array(
		'jsonrpc' => '2.0',
		'id'      => $id,
		'error'   => array( 'code' => $code, 'message' => $msg ),
	);
}

function flxai_rpc_result( $id, $result ) {
	return array(
		'jsonrpc' => '2.0',
		'id'      => $id,
		'result'  => $result,
	);
}

function flxai_mcp_dispatch( $m ) {
	if ( ! is_array( $m ) || ( $m['jsonrpc'] ?? '' ) !== '2.0' || empty( $m['method'] ) ) {
		return flxai_rpc_error( $m['id'] ?? null, -32600, 'Invalid Request' );
	}
	$id     = $m['id'] ?? null;
	$params = (array) ( $m['params'] ?? array() );
	if ( ! array_key_exists( 'id', $m ) ) {
		return null; // Bildirim (notifications/initialized vb.): yanıt yok.
	}
	switch ( $m['method'] ) {
		case 'initialize':
			$want = $params['protocolVersion'] ?? '';
			return flxai_rpc_result(
				$id,
				array(
					'protocolVersion' => in_array( $want, FLXAI_MCP_PROTOCOLS, true ) ? $want : FLXAI_MCP_PROTOCOLS[0],
					'capabilities'    => array(
						'tools'     => array( 'listChanged' => false ),
						'prompts'   => array( 'listChanged' => false ),
						'resources' => array( 'listChanged' => false ),
					),
					'serverInfo'      => array(
						'name'    => 'fluxwp',
						'title'   => get_bloginfo( 'name' ) . ' (WordPress)',
						'version' => FLXAI_VERSION,
					),
					'instructions'    => flxai_mcp_instructions(),
				)
			);
		case 'ping':
			return flxai_rpc_result( $id, new stdClass() );
		case 'tools/list':
			$tools = array();
			foreach ( flxai_mcp_tools() as $name => $t ) {
				if ( ! is_array( $t ) || ! flxai_mcp_tool_for_user( $name, $t ) ) {
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
			return flxai_rpc_result( $id, array( 'tools' => $tools ) );
		case 'tools/call':
			return flxai_rpc_result( $id, flxai_mcp_call( (string) ( $params['name'] ?? '' ), (array) ( $params['arguments'] ?? array() ) ) );
		case 'prompts/list':
			$list = array();
			foreach ( flxai_mcp_prompts() as $name => $p ) {
				if ( ! flxai_mcp_item_allowed( $p ) ) {
					continue;
				}
				$list[] = array(
					'name'        => $name,
					'title'       => $p['title'] ?? $name,
					'description' => $p['description'] ?? '',
					'arguments'   => $p['arguments'] ?? array(),
				);
			}
			return flxai_rpc_result( $id, array( 'prompts' => $list ) );
		case 'prompts/get':
			$p = flxai_mcp_prompts()[ $params['name'] ?? '' ] ?? null;
			if ( ! $p || ! flxai_mcp_item_allowed( $p ) ) {
				return flxai_rpc_error( $id, -32602, 'Unknown prompt' );
			}
			return flxai_rpc_result(
				$id,
				array(
					'description' => $p['description'] ?? '',
					'messages'    => array(
						array(
							'role'    => 'user',
							'content' => array( 'type' => 'text', 'text' => (string) call_user_func( $p['text'], (array) ( $params['arguments'] ?? array() ) ) ),
						),
					),
				)
			);
		case 'resources/list':
			$list = array();
			foreach ( flxai_mcp_resources() as $uri => $r ) {
				if ( ! flxai_mcp_item_allowed( $r ) ) {
					continue;
				}
				$list[] = array(
					'uri'         => $uri,
					'name'        => $r['name'] ?? $uri,
					'title'       => $r['title'] ?? '',
					'description' => $r['description'] ?? '',
					'mimeType'    => $r['mimeType'] ?? 'text/plain',
				);
			}
			return flxai_rpc_result( $id, array( 'resources' => $list ) );
		case 'resources/read':
			$uri = (string) ( $params['uri'] ?? '' );
			$r   = flxai_mcp_resources()[ $uri ] ?? null;
			if ( ! $r || ! flxai_mcp_item_allowed( $r ) ) {
				return flxai_rpc_error( $id, -32002, 'Resource not found' );
			}
			return flxai_rpc_result( $id, array( 'contents' => array( array( 'uri' => $uri, 'mimeType' => $r['mimeType'] ?? 'text/plain', 'text' => (string) call_user_func( $r['read'] ) ) ) ) );
		case 'resources/templates/list':
			return flxai_rpc_result( $id, array( 'resourceTemplates' => array() ) );
	}
	return flxai_rpc_error( $id, -32601, 'Method not found: ' . $m['method'] );
}

/**
 * Aracı çalıştırır (MCP ve Flux AI sohbeti ortak). Sonuç MCP biçiminde: content[], isError, structuredContent?.
 */
function flxai_mcp_call( $name, $args ) {
	if ( flxai_mcp_legacy() && function_exists( 'flxseo_mcp_call' ) ) {
		return flxseo_mcp_call( $name, $args );
	}
	$name  = flxai_tool_resolve( $name ); // Eski adla gelen çağrı (ör. site_info → flxseo_site_info).
	$tools = flxai_mcp_tools();
	$ok    = false;
	$t     = null;
	try {
		$t = $tools[ $name ] ?? null;
		// Kapalı, kapsam dışı, yetkisiz ya da filtreyle elenen araç çalışmaz; bilinmeyen araçla aynı yanıt.
		if ( ! is_array( $t ) || ! flxai_mcp_tool_for_user( $name, $t ) ) {
			/* translators: %s: tool name */
			throw new Exception( sprintf( __( 'Unknown tool: %s', 'flx-ai' ), $name ) );
		}
		$data = call_user_func( $t['run'], $args );
		$ok   = true;
	} catch ( Throwable $e ) {
		$data = array( 'error' => $e->getMessage() );
	}
	// Sık yoklanan okuma araçları ('log' => false) başarılıysa Son işlemler'i doldurmasın.
	if ( ! $ok || null === $t || false !== ( $t['log'] ?? true ) ) {
		flxai_mcp_log( $name, $ok, $args );
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
	if ( $ok && is_array( $data ) && array() !== $data && array_keys( $data ) !== range( 0, count( $data ) - 1 ) ) {
		$res['structuredContent'] = $data;
	}
	return $res;
}

function flxai_mcp_log( $tool, $ok, $args ) {
	$log    = (array) get_option( 'flxai_mcp_log', array() );
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
	update_option( 'flxai_mcp_log', array_slice( $log, 0, 100 ), false );
}

/** Aracı kaydeden eklentinin klasörü (Araçlar ekranındaki "Kaynak" sütunu için). */
function flxai_mcp_tool_source( array $t ) {
	if ( ! empty( $t['source'] ) ) {
		return (string) $t['source'];
	}
	$src = flxai_callable_source( $t['run'] ?? null );
	return '' !== $src ? $src : 'mcp';
}

/* ---------------------------------------------------------------------------
 * Yönetim: aç/kapa, anahtarlar
 * ------------------------------------------------------------------------- */

add_action(
	'admin_post_flxai_mcp',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'flx-ai' ) );
		}
		check_admin_referer( 'flxai_mcp' );
		$do   = sanitize_key( $_POST['do'] ?? '' );
		$keys = flxai_mcp_keys();
		if ( 'toggle' === $do ) {
			flxai_update_settings(
				array(
					'mcp_enabled' => empty( $_POST['mcp_enabled'] ) ? 0 : 1,
					'mcp_oauth'   => empty( $_POST['mcp_oauth'] ) ? 0 : 1,
				)
			);
		} elseif ( 'create' === $do ) {
			$key         = 'flxmcp_' . wp_generate_password( 40, false, false );
			$id          = wp_generate_uuid4();
			$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
			$keys[ $id ] = array(
				'name'      => '' !== $name ? $name : __( 'AI', 'flx-ai' ),
				'hash'      => hash( 'sha256', $key ),
				'user'      => get_current_user_id(),
				'created'   => time(),
				'last_used' => 0,
				'hint'      => substr( $key, -4 ),
			);
			// Araç seçildiyse anahtar yalnız onlarla sınırlı (ör. TV paneli için yalnız order_summary).
			$tools = array_values( array_intersect( array_map( 'sanitize_key', (array) wp_unslash( $_POST['tools'] ?? array() ) ), array_map( 'sanitize_key', array_keys( flxai_mcp_tools() ) ) ) );
			if ( $tools ) {
				$keys[ $id ]['tools'] = $tools;
			}
			update_option( 'flxai_mcp_keys', $keys, false );
			set_transient( 'flxai_mcp_new_key_' . get_current_user_id(), $key, 300 );
		} elseif ( 'revoke' === $do ) {
			unset( $keys[ sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) ) ] );
			update_option( 'flxai_mcp_keys', $keys, false );
		}
		wp_safe_redirect( flxai_mcp_admin_url() );
		exit;
	}
);

/* ---------------------------------------------------------------------------
 * Geriye uyum: eski Flux SEO işlevleri (diğer eklentiler function_exists ile denetler)
 * ------------------------------------------------------------------------- */

add_action(
	'plugins_loaded',
	function () {
		if ( flxai_mcp_legacy() ) {
			return;
		}
		if ( ! function_exists( 'flxseo_mcp_tools' ) ) {
			/** @deprecated Flux AI 0.7.0: flxai_mcp_tools(). */
			function flxseo_mcp_tools() {
				return flxai_mcp_tools();
			}
		}
		if ( ! function_exists( 'flxseo_mcp_call' ) ) {
			/** @deprecated Flux AI 0.7.0: flxai_mcp_call(). */
			function flxseo_mcp_call( $name, $args ) {
				return flxai_mcp_call( $name, $args );
			}
		}
		if ( ! function_exists( 'flxseo_mcp_url' ) ) {
			/** @deprecated Flux AI 0.7.0: flxai_mcp_url(). */
			function flxseo_mcp_url() {
				return flxai_mcp_url();
			}
		}
	},
	1
);
