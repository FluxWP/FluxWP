<?php
/**
 * MCP sunucusu (Model Context Protocol, Streamable HTTP, durumsuz).
 *
 * Adres: /wp-json/flx-tasks/v1/mcp
 * Kimlik: "Authorization: Bearer <anahtar>" ya da "X-MCP-Key: <anahtar>".
 * Anahtar yalnız bu rotanın permission_callback'inde kullanıcı olur; sorgu dizesinden okunmaz.
 * Her kullanıcı kendi anahtarını Flux Tasks › Profil › MCP bölümünden üretir; araçlar o kullanıcının liste yetkileriyle çalışır.
 */

defined( 'ABSPATH' ) || exit;

const FLXG_MCP_PROTOCOLS = array( '2025-06-18', '2025-03-26', '2024-11-05' );

function flxg_mcp_url() {
	return rest_url( 'flx-tasks/v1/mcp' );
}

function flxg_mcp_keys() {
	return (array) get_option( 'flxg_mcp_keys', array() );
}

function flxg_mcp_find_key( $key ) {
	if ( ! $key || strlen( $key ) < 20 ) {
		return null;
	}
	$hash = hash( 'sha256', $key );
	foreach ( flxg_mcp_keys() as $id => $k ) {
		if ( hash_equals( (string) $k['hash'], $hash ) ) {
			return array( 'id' => $id ) + $k;
		}
	}
	return null;
}

function flxg_mcp_create_key( $uid, $label ) {
	$key  = 'flxg_' . wp_generate_password( 40, false );
	$keys = flxg_mcp_keys();
	$id   = wp_generate_password( 10, false );
	$keys[ $id ] = array(
		'hash'      => hash( 'sha256', $key ),
		'user'      => (int) $uid,
		'label'     => mb_substr( sanitize_text_field( $label ), 0, 80 ),
		'hint'      => substr( $key, 0, 8 ) . '…' . substr( $key, -4 ),
		'created'   => time(),
		'last_used' => 0,
	);
	update_option( 'flxg_mcp_keys', $keys, false );
	return array( 'id' => $id, 'key' => $key );
}

function flxg_mcp_delete_key( $id ) {
	$keys = flxg_mcp_keys();
	unset( $keys[ $id ] );
	update_option( 'flxg_mcp_keys', $keys, false );
}

function flxg_mcp_request_key( WP_REST_Request $req ) {
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

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flx-tasks/v1',
			'/mcp',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => 'flxg_mcp_handle',
					'permission_callback' => 'flxg_mcp_permission',
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

function flxg_mcp_permission( WP_REST_Request $req ) {
	if ( ! flxg_setting( 'mcp_enabled' ) ) {
		return new WP_Error( 'mcp_disabled', __( 'MCP is off. Turn it on under Flux Tasks › Settings.', 'flx-tasks' ), array( 'status' => 403 ) );
	}
	$k = flxg_mcp_find_key( flxg_mcp_request_key( $req ) );
	if ( $k ) {
		wp_set_current_user( (int) $k['user'] );
		$keys = flxg_mcp_keys();
		if ( time() - (int) ( $keys[ $k['id'] ]['last_used'] ?? 0 ) > 300 ) {
			$keys[ $k['id'] ]['last_used'] = time();
			update_option( 'flxg_mcp_keys', $keys, false );
		}
	}
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'mcp_auth', __( 'Invalid or missing MCP key.', 'flx-tasks' ), array( 'status' => 401 ) );
	}
	if ( ! current_user_can( 'flxg_use' ) ) {
		return new WP_Error( 'mcp_cap', __( 'This user is not allowed to use Flux Tasks.', 'flx-tasks' ), array( 'status' => 403 ) );
	}
	return true;
}

function flxg_rpc_error( $id, $code, $msg ) {
	return array(
		'jsonrpc' => '2.0',
		'id'      => $id,
		'error'   => array( 'code' => $code, 'message' => $msg ),
	);
}

function flxg_rpc_result( $id, $result ) {
	return array(
		'jsonrpc' => '2.0',
		'id'      => $id,
		'result'  => $result,
	);
}

function flxg_mcp_handle( WP_REST_Request $req ) {
	$body = json_decode( $req->get_body(), true );
	if ( null === $body ) {
		return new WP_REST_Response( flxg_rpc_error( null, -32700, 'Parse error' ), 400 );
	}
	$batch = is_array( $body ) && isset( $body[0] );
	$msgs  = $batch ? $body : array( $body );
	$out   = array();
	foreach ( $msgs as $m ) {
		$r = flxg_mcp_dispatch( $m );
		if ( null !== $r ) {
			$out[] = $r;
		}
	}
	if ( ! $out ) {
		return new WP_REST_Response( null, 202 );
	}
	return new WP_REST_Response( $batch ? $out : $out[0], 200 );
}

function flxg_mcp_instructions() {
	return 'This server manages the task tracker of "' . get_bloginfo( 'name' ) . '" (' . home_url( '/' ) . ").\n"
		. "- Structure: projects contain folders and lists; tasks live in lists. Call list_projects first to learn list ids and statuses.\n"
		. "- Dates are site-local (" . wp_timezone_string() . '), format YYYY-MM-DD or YYYY-MM-DDTHH:MM. Today is ' . flxg_today() . ".\n"
		. "- Priority: 1 urgent, 2 high, 3 normal, 4 low, 0 none.\n"
		. "- Write task titles and comments in the user's language (the site is mostly Turkish).\n"
		. '- delete_task is permanent; confirm with the user first.';
}

function flxg_mcp_dispatch( $m ) {
	if ( ! is_array( $m ) || ( $m['jsonrpc'] ?? '' ) !== '2.0' || empty( $m['method'] ) ) {
		return flxg_rpc_error( $m['id'] ?? null, -32600, 'Invalid Request' );
	}
	if ( ! array_key_exists( 'id', $m ) ) {
		return null;
	}
	$id     = $m['id'];
	$params = (array) ( $m['params'] ?? array() );
	switch ( $m['method'] ) {
		case 'initialize':
			$want = $params['protocolVersion'] ?? '';
			return flxg_rpc_result(
				$id,
				array(
					'protocolVersion' => in_array( $want, FLXG_MCP_PROTOCOLS, true ) ? $want : FLXG_MCP_PROTOCOLS[0],
					'capabilities'    => array( 'tools' => array( 'listChanged' => false ) ),
					'serverInfo'      => array(
						'name'    => 'flx-tasks',
						'title'   => get_bloginfo( 'name' ) . ' · Flux Tasks',
						'version' => FLXG_VERSION,
					),
					'instructions'    => flxg_mcp_instructions(),
				)
			);
		case 'ping':
			return flxg_rpc_result( $id, new stdClass() );
		case 'tools/list':
			$tools = array();
			foreach ( flxg_tools() as $name => $t ) {
				$tools[] = array(
					'name'        => $name,
					'title'       => $t['title'],
					'description' => $t['description'],
					'inputSchema' => $t['schema'],
					'annotations' => array(
						'readOnlyHint'    => ! empty( $t['readonly'] ),
						'destructiveHint' => ! empty( $t['destructive'] ),
					),
				);
			}
			return flxg_rpc_result( $id, array( 'tools' => $tools ) );
		case 'tools/call':
			list( $ok, $data ) = flxg_run_tool( (string) ( $params['name'] ?? '' ), (array) ( $params['arguments'] ?? array() ) );
			$res               = array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ),
					),
				),
				'isError' => ! $ok,
			);
			if ( $ok && is_array( $data ) && ! isset( $data[0] ) && $data ) {
				$res['structuredContent'] = $data;
			}
			return flxg_rpc_result( $id, $res );
		case 'prompts/list':
			return flxg_rpc_result( $id, array( 'prompts' => array() ) );
		case 'resources/list':
			return flxg_rpc_result( $id, array( 'resources' => array() ) );
		case 'resources/templates/list':
			return flxg_rpc_result( $id, array( 'resourceTemplates' => array() ) );
	}
	return flxg_rpc_error( $id, -32601, 'Method not found: ' . $m['method'] );
}
