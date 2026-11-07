<?php
/**
 * Flux App: yönetici ayarları (write-only anahtarlar) ve sayfalı kayıt metaverisi.
 * REST: flx-ai/v1/log — içerik listede dönmez; maskeli gövde ayrı yönetici rotasından okunur.
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
		$res->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$res->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
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
				'limit_day'         => array( 'type' => 'number', 'default' => 0 ),
				'limit_week'        => array( 'type' => 'number', 'default' => 0 ),
				'limit_month'       => array( 'type' => 'number', 'default' => 0 ),
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
			),
		);
		return $s;
	}
);

/** Köprü düz metin yazarsa key_* alanlarını şifreler. enc1: / b64: dokunulmaz. */
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
			if ( '' === $v || 0 === strpos( $v, 'enc1:' ) || 0 === strpos( $v, 'b64:' ) ) {
				continue;
			}
			$value[ $k ] = flxai_encrypt( $v );
		}
		return $value;
	}
);

/** Paged metadata only. Saved, masked request/response bodies use the separate manager route. */
function flxai_app_log( WP_REST_Request $req ) {
	global $wpdb;
	$t = flxai_table( 'log' );
	$b = flxai_table( 'log_body' );
	$sources = flxai_sources();
	foreach ( flxai_systems() as $system ) { $sources[ $system['source'] ] = $system['label']; }
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t;
	if ( $exists ) {
		foreach ( (array) $wpdb->get_col( "SELECT DISTINCT source FROM {$t} ORDER BY source" ) as $source ) { // phpcs:ignore WordPress.DB.PreparedSQL
			if ( ! isset( $sources[ $source ] ) ) { $sources[ $source ] = flxai_source_label( $source ); }
		}
	}
	$options = array();
	foreach ( $sources as $id => $label ) { if ( '' !== (string) $id ) { $options[] = array( 'id' => (string) $id, 'label' => (string) $label ); } }
	$page = max( 1, (int) $req->get_param( 'page' ) );
	$per = max( 1, min( 50, (int) ( $req->get_param( 'per_page' ) ?: 25 ) ) );
	$empty = array( 'items' => array(), 'total' => 0, 'page' => $page, 'pages' => 0, 'sources' => $options, 'details_available' => true );
	if ( ! $exists ) { return rest_ensure_response( $empty ); }
	$where = array( '1=1' ); $args = array();
	$days = (int) $req->get_param( 'days' );
	if ( $days ) { $where[] = 'l.ts >= %d'; $args[] = time() - $days * DAY_IN_SECONDS; }
	$kind = sanitize_key( (string) $req->get_param( 'kind' ) );
	if ( '' !== $kind ) { $where[] = 'l.kind = %s'; $args[] = $kind; }
	$source = sanitize_key( (string) $req->get_param( 'source' ) );
	if ( '' !== $source ) { $where[] = 'l.source = %s'; $args[] = $source; }
	if ( $req->get_param( 'errors' ) ) { $where[] = 'l.ok = 0'; }
	$q = trim( (string) $req->get_param( 'q' ) );
	if ( '' !== $q ) {
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$where[] = '(l.model LIKE %s OR l.tool LIKE %s OR l.error LIKE %s OR l.source LIKE %s OR l.purpose LIKE %s)';
		array_push( $args, $like, $like, $like, $like, $like );
	}
	$w = implode( ' AND ', $where );
	$sqlc = "SELECT COUNT(*) FROM {$t} l WHERE {$w}";
	$total = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $sqlc, $args ) ) : $wpdb->get_var( $sqlc ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	// Only read the body existence flag; never join the request/response text into the list.
	$body_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $b ) ) === $b;
	$flag = $body_exists ? '(b.log_id IS NOT NULL)' : '0';
	$join = $body_exists ? " LEFT JOIN {$b} b ON b.log_id = l.id" : '';
	$sql = "SELECT l.id,l.ts,l.kind,l.source,l.purpose,l.user_id,l.chat_id,l.provider,l.model,l.tool,l.in_tok,l.out_tok,l.cache_read,l.cache_write,l.cost,l.est,l.ms,l.ok,l.error,{$flag} AS has_body FROM {$t} l{$join} WHERE {$w} ORDER BY l.id DESC LIMIT %d OFFSET %d";
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	$items = array(); $users = array();
	foreach ( (array) $rows as $row ) {
		$item = array();
		foreach ( array( 'id','ts','user_id','chat_id','in_tok','out_tok','cache_read','cache_write','ms','ok' ) as $key ) { $item[ $key ] = (int) $row[ $key ]; }
		foreach ( array( 'kind','source','purpose','provider','model','tool','error' ) as $key ) { $item[ $key ] = (string) $row[ $key ]; }
		$uid = $item['user_id'];
		if ( $uid && ! isset( $users[ $uid ] ) ) { $user = get_userdata( $uid ); $users[ $uid ] = $user ? $user->display_name : '#' . $uid; }
		$item['user_label'] = $uid ? $users[ $uid ] : ''; // The client localizes the system actor.
		$item['source_label'] = (string) ( $sources[ $row['source'] ] ?? flxai_source_label( $row['source'] ) );
		$item['purpose_label'] = '' !== $row['purpose'] ? flxai_purpose_label( $row['purpose'] ) : '';
		$item['model_label'] = '' !== $row['model'] ? flxai_model_label( $row['model'] ) : '';
		$item['ts_label'] = function_exists( 'flx_date' ) ? flx_date( $item['ts'], 'datetime' ) : '';
		$item['cost'] = (float) $row['cost']; $item['est'] = (bool) $row['est']; $item['has_body'] = (bool) $row['has_body'];
		$item['title'] = (string) ( $row['tool'] ?: ( $row['model'] ?: $row['kind'] ) );
		$items[] = $item;
	}
	return rest_ensure_response( array( 'items' => $items, 'total' => $total, 'page' => $page, 'pages' => (int) ceil( $total / $per ), 'sources' => $options, 'details_available' => true ) );
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
					'kind'     => array( 'type' => 'string', 'enum' => array( '', 'model', 'tool' ), 'default' => '' ),
					'days' => array( 'type' => 'integer', 'enum' => array( 0, 1, 7, 30, 90, 365 ), 'default' => 0 ),
					'errors' => array( 'type' => 'boolean', 'default' => false ),
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
