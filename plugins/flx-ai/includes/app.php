<?php
/**
 * Flux App: ayarlar (köprü, anahtarlar write-only) ve kayıt listesi. Sohbet yerelde sonra açılır (flxai-chat).
 * REST: flx-ai/v1/log  — istek gövdesi dönmez.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param mixed $node
 * @return mixed
 */
function flxai_app_texts( $node, $domain ) {
	if ( ! is_array( $node ) ) {
		return $node;
	}
	foreach ( $node as $k => $v ) {
		if ( 'text' === $k && is_string( $v ) && false === strpos( $v, '{{' ) ) {
			$node[ $k ] = translate( $v, $domain ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain
		} else {
			$node[ $k ] = flxai_app_texts( $v, $domain );
		}
	}
	return $node;
}

function flxai_app_can() {
	return current_user_can( 'manage_options' );
}

function flxai_app_nocache( $res, $server, $req ) {
	unset( $server );
	if ( $res instanceof WP_REST_Response && 0 === strpos( (string) $req->get_route(), '/flx-ai/v1/' ) ) {
		flx_rest_nocache( $res );
		$res->header( 'CDN-Cache-Control', 'no-store' );
		do_action( 'litespeed_control_set_nocache', 'flx-ai rest' );
	}
	return $res;
}
add_filter( 'rest_post_dispatch', 'flxai_app_nocache', 10, 3 );

add_filter(
	'flx_settings_schema',
	function ( $s ) {
		$s['flxai_settings'] = array(
			'label'  => 'Flux AI',
			'fields' => array(
				'model'             => array( 'type' => 'string', 'default' => '' ),
				'chat_model'        => array( 'type' => 'string', 'default' => '' ),
				'title_model'       => array( 'type' => 'string', 'default' => '' ),
				'cache'             => array( 'type' => 'enum', 'enum' => array( 'off', '5m', '1h' ), 'default' => '5m' ),
				'effort'            => array( 'type' => 'enum', 'enum' => array( 'low', 'medium', 'high' ), 'default' => 'low' ),
				'thinking'          => array( 'type' => 'bool', 'default' => 1 ),
				'limit_day'         => array( 'type' => 'int', 'default' => 0 ),
				'limit_week'        => array( 'type' => 'int', 'default' => 0 ),
				'limit_month'       => array( 'type' => 'int', 'default' => 0 ),
				'limit_action'      => array( 'type' => 'enum', 'enum' => array( 'block', 'warn' ), 'default' => 'block' ),
				'limit_warn_pct'    => array( 'type' => 'int', 'default' => 80 ),
				'limit_notify'      => array( 'type' => 'bool', 'default' => 1 ),
				'chat_admin'        => array( 'type' => 'bool', 'default' => 1 ),
				'chat_front'        => array( 'type' => 'bool', 'default' => 1 ),
				'chat_mode'         => array( 'type' => 'enum', 'enum' => array( 'bubble', 'dock' ), 'default' => 'bubble' ),
				'chat_max_rounds'   => array( 'type' => 'int', 'default' => 12 ),
				'chat_history'      => array( 'type' => 'int', 'default' => 40 ),
				'chat_instructions' => array( 'type' => 'text', 'default' => '' ),
				'chat_widgets'      => array( 'type' => 'bool', 'default' => 1 ),
				'render_url'        => array( 'type' => 'url', 'default' => '' ),
				'log_bodies'        => array( 'type' => 'bool', 'default' => 1 ),
				'log_body_days'     => array( 'type' => 'int', 'default' => 0 ),
				'log_days'          => array( 'type' => 'int', 'default' => 0 ),
				'key_openrouter'    => array( 'type' => 'string', 'default' => '' ),
				'key_anthropic'     => array( 'type' => 'string', 'default' => '' ),
				'key_openai'        => array( 'type' => 'string', 'default' => '' ),
				'key_gemini'        => array( 'type' => 'string', 'default' => '' ),
				'key_fal'           => array( 'type' => 'string', 'default' => '' ),
				'key_evolink'       => array( 'type' => 'string', 'default' => '' ),
				'key_render'        => array( 'type' => 'string', 'default' => '' ),
				'key_brave'         => array( 'type' => 'string', 'default' => '' ),
				'web_on'            => array( 'type' => 'bool', 'default' => 1 ),
				'web_fetch_on'      => array( 'type' => 'bool', 'default' => 1 ),
				'shell_on'          => array( 'type' => 'bool', 'default' => 1 ),
				'web_search_month'  => array( 'type' => 'int', 'default' => 0 ),
			),
		);
		return $s;
	}
);

/** Köprü düz metin yazarsa key_* alanlarını şifreler. flx1: / enc1: / b64: dokunulmaz. */
add_filter(
	'pre_update_option_flxai_settings',
	function ( $value ) {
		if ( ! is_array( $value ) || ! function_exists( 'flxai_encrypt' ) ) {
			return $value;
		}
		foreach ( $value as $k => $v ) {
			if ( 0 !== strpos( (string) $k, 'key_' ) ) {
				continue;
			}
			$v = (string) $v;
			if ( '' === $v || 0 === strpos( $v, 'flx1:' ) || 0 === strpos( $v, 'enc1:' ) || 0 === strpos( $v, 'b64:' ) ) {
				continue;
			}
			$value[ $k ] = flxai_encrypt( $v );
		}
		return $value;
	}
);

function flxai_app_log( WP_REST_Request $req ) {
	global $wpdb;
	$t = flxai_table( 'log' );
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) !== $t ) {
		return rest_ensure_response( array( 'items' => array(), 'total' => 0, 'page' => 1, 'pages' => 0 ) );
	}
	$where = array( '1=1' );
	$args  = array();
	$kind  = sanitize_key( (string) $req->get_param( 'kind' ) );
	if ( in_array( $kind, array( 'model', 'tool' ), true ) ) {
		$where[] = 'kind = %s';
		$args[]  = $kind;
	}
	$source = sanitize_key( (string) $req->get_param( 'source' ) );
	if ( '' !== $source ) {
		$where[] = 'source = %s';
		$args[]  = $source;
	}
	$q = trim( (string) $req->get_param( 'q' ) );
	if ( '' !== $q ) {
		$like    = '%' . $wpdb->esc_like( $q ) . '%';
		$where[] = '(model LIKE %s OR tool LIKE %s OR error LIKE %s OR source LIKE %s)';
		array_push( $args, $like, $like, $like, $like );
	}
	$w    = implode( ' AND ', $where );
	$per  = max( 1, min( 50, (int) ( $req->get_param( 'per_page' ) ? $req->get_param( 'per_page' ) : 25 ) ) );
	$page = max( 1, (int) $req->get_param( 'page' ) );
	$sqlc = "SELECT COUNT(*) FROM {$t} WHERE {$w}";
	$sql  = "SELECT id, ts, kind, source, purpose, user_id, provider, model, tool, cost, ms, ok, error FROM {$t} WHERE {$w} ORDER BY id DESC LIMIT %d OFFSET %d";
	$total = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $sqlc, $args ) ) : $wpdb->get_var( $sqlc ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	$rows  = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	$items = array();
	foreach ( (array) $rows as $r ) {
		$items[] = array(
			'id'       => (int) $r['id'],
			'ts'       => (int) $r['ts'],
			'ts_label' => function_exists( 'flx_date' ) ? flx_date( (int) $r['ts'], 'datetime' ) : '',
			'kind'     => (string) $r['kind'],
			'source'   => (string) $r['source'],
			'purpose'  => (string) $r['purpose'],
			'provider' => (string) $r['provider'],
			'model'    => (string) $r['model'],
			'tool'     => (string) $r['tool'],
			'cost'     => (float) $r['cost'],
			'ms'       => (int) $r['ms'],
			'ok'       => (int) $r['ok'],
			'error'    => (string) $r['error'],
			'title'    => (string) ( $r['tool'] ? $r['tool'] : ( $r['model'] ? $r['model'] : $r['kind'] ) ),
		);
	}
	return rest_ensure_response(
		array(
			'items' => $items,
			'total' => $total,
			'page'  => $page,
			'pages' => (int) ceil( $total / $per ),
		)
	);
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flx-ai/v1',
			'/log',
			array(
				'methods'             => 'GET',
				'callback'            => 'flxai_app_log',
				'permission_callback' => 'flxai_app_can',
				'args'                => array(
					'page'     => array( 'type' => 'integer', 'default' => 1 ),
					'per_page' => array( 'type' => 'integer', 'default' => 25 ),
					'kind'     => array( 'type' => 'string', 'default' => '' ),
					'source'   => array( 'type' => 'string', 'default' => '' ),
					'q'        => array( 'type' => 'string', 'default' => '' ),
				),
			)
		);
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! function_exists( 'flxapp_manifest_file' ) ) {
			return;
		}
		add_filter(
			'flxapp_manifest',
			function ( $m ) {
				$loaded   = flxapp_manifest_file(
					FLXAI_DIR . 'app/manifest.json',
					'flx-ai',
					array(
						'version' => FLXAI_VERSION,
						'caps'    => array( 'view' => 'manage_options' ),
					)
				);
				$m['ai'] = flxai_app_texts( $loaded, 'flx-ai' );
				return $m;
			},
			10,
			2
		);
	},
	20
);
