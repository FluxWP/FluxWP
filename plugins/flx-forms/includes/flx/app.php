<?php
/**
 * Flux Forms uygulama yüzeyi.
 *
 * Form listesi mevcut `efb/v1/forms` ucudur. Yanıt listesi bu dosyadadır:
 *
 *   GET  /wp-json/flx-forms/v1/responses
 *   GET  /wp-json/flx-forms/v1/responses/<id>
 *   POST /wp-json/flx-forms/v1/responses/<id>/read
 *
 * Yetki: `Emsfb` ya da `manage_options`. Form oluşturucu web görünümünde kalır.
 *
 * @package FLX_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'flxf_app_routes' );
add_filter( 'rest_post_dispatch', 'flxf_app_nocache', 10, 3 );
add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'FLXAPP_VERSION' ) ) {
			return;
		}
		add_filter( 'flxapp_manifest', 'flxf_app_manifest', 10, 2 );
	},
	20
);

/*
 * Native screen of the FluxWP app: the same React screen as the wp-admin panel (@fluxwp/modules
 * `flxf-forms`, data over GraphQL). App versions without it open the admin page instead.
 */
add_filter(
	'flxapp_modules',
	static function ( $m ) {
		$m               = is_array( $m ) ? $m : array();
		$m['flxf-forms'] = array(
			'title'   => __( 'Forms', 'flx-forms' ),
			'cap'     => 'Emsfb',
			'menu'    => 'Emsfb',
			'url'     => admin_url( 'admin.php?page=Emsfb' ),
			'api'     => home_url( '/graphql' ),
			'icon'    => 'note',
			'version' => flxf_app_version(),
			'order'   => 24,
		);
		return $m;
	}
);

function flxf_app_version() {
	static $v = null;
	if ( null === $v ) {
		$data = get_file_data( FLXF_PLUGIN_FILE, array( 'Version' => 'Version' ) );
		$v    = ! empty( $data['Version'] ) ? $data['Version'] : '2.1.14';
	}
	return $v;
}

function flxf_app_can() {
	return current_user_can( 'manage_options' ) || current_user_can( 'Emsfb' );
}

function flxf_app_routes() {
	$auth = 'flxf_app_can';
	$args = array(
		'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
		'per_page' => array( 'type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 100 ),
	);
	register_rest_route( 'flx-forms/v1', '/responses', array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => 'flxf_app_responses', 'args' => $args ) );
	register_rest_route( 'flx-forms/v1', '/responses/(?P<id>\d+)', array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => 'flxf_app_response' ) );
	register_rest_route( 'flx-forms/v1', '/responses/(?P<id>\d+)/read', array( 'methods' => 'POST', 'permission_callback' => $auth, 'callback' => 'flxf_app_response_read' ) );
}

function flxf_app_nocache( $result, $server, $request ) {
	unset( $server );
	if ( $result instanceof WP_REST_Response && 0 === strpos( (string) $request->get_route(), '/flx-forms/v1/' ) ) {
		flx_rest_nocache( $result );
		$result->header( 'CDN-Cache-Control', 'no-store' );
		do_action( 'litespeed_control_set_nocache', 'flxf-app' );
	}
	return $result;
}

function flxf_app_forms_map() {
	return FLXF_Response_Service::forms_map();
}

function flxf_app_unread( $read ) {
	$read = (int) $read;
	return 0 === $read || 3 === $read;
}

function flxf_app_fields( $content ) {
	$items = json_decode( (string) $content, true );
	if ( ! is_array( $items ) ) {
		return array();
	}
	$out = array();
	foreach ( $items as $c ) {
		if ( ! is_array( $c ) ) {
			continue;
		}
		$type = isset( $c['type'] ) ? (string) $c['type'] : '';
		if ( in_array( $type, array( 'closed', 'opened' ), true ) ) {
			continue;
		}
		$label = isset( $c['name'] ) ? wp_strip_all_tags( (string) $c['name'] ) : '';
		$value = isset( $c['value'] ) ? $c['value'] : '';
		if ( is_array( $value ) ) {
			$flat = array();
			foreach ( $value as $bit ) {
				if ( is_scalar( $bit ) && '' !== (string) $bit ) {
					$flat[] = (string) $bit;
				}
			}
			$value = implode( ', ', $flat );
		}
		$value = wp_strip_all_tags( (string) $value );
		if ( '@file@' === $value && ! empty( $c['url'] ) ) {
			$value = (string) $c['url'];
		}
		if ( '' === $label && '' === $value ) {
			continue;
		}
		$out[] = array(
			'label' => '' !== $label ? $label : __( 'Field', 'flx-forms' ),
			'value' => $value,
		);
	}
	return $out;
}

function flxf_app_preview( array $fields ) {
	$bits = array();
	foreach ( $fields as $f ) {
		if ( '' !== $f['value'] ) {
			$bits[] = $f['value'];
		}
		if ( count( $bits ) >= 2 ) {
			break;
		}
	}
	$text = implode( ' · ', $bits );
	if ( function_exists( 'mb_substr' ) && mb_strlen( $text ) > 140 ) {
		return mb_substr( $text, 0, 140 ) . '…';
	}
	return $text;
}

function flxf_app_row( array $r, array $forms ) {
	$fields = flxf_app_fields( $r['content'] );
	$fid    = (int) $r['form_id'];
	$when   = strtotime( (string) $r['date'] );
	return array(
		'id'         => (int) $r['msg_id'],
		'form_id'    => $fid,
		'form'       => isset( $forms[ $fid ] ) ? $forms[ $fid ] : (string) $r['form_title_x'],
		'track'      => (string) $r['track'],
		'preview'    => flxf_app_preview( $fields ),
		'unread'     => flxf_app_unread( $r['read_'] ),
		'date'       => $when ? gmdate( 'c', $when ) : '',
		'date_label' => $when && function_exists( 'flx_date' ) ? flx_date( $when, 'datetime' ) : (string) $r['date'],
		'fields'     => $fields,
	);
}

function flxf_app_responses( WP_REST_Request $r ) {
	$per  = (int) $r['per_page'];
	$page = (int) $r['page'];
	$out  = FLXF_Response_Service::page(
		array(
			'form'    => absint( $r->get_param( 'form' ) ),
			'unread'  => (bool) $r->get_param( 'unread' ),
			'status'  => '',
			'search'  => sanitize_text_field( (string) $r->get_param( 's' ) ),
			'replies' => false,
		),
		( $page - 1 ) * $per,
		$per
	);
	$total = $out['total'];
	$forms = flxf_app_forms_map();
	$items = array();
	foreach ( $out['rows'] as $row ) {
		$item = flxf_app_row( $row, $forms );
		unset( $item['fields'] );
		$items[] = $item;
	}
	return array(
		'items' => $items,
		'total' => $total,
		'pages' => max( 1, (int) ceil( $total / max( 1, $per ) ) ),
	);
}

function flxf_app_response( WP_REST_Request $r ) {
	$row = FLXF_Response_Service::row( (int) $r['id'] );
	if ( ! $row || 2 === (int) $row['read_'] ) {
		return new WP_Error( 'flxf_missing', __( 'Response not found.', 'flx-forms' ), array( 'status' => 404 ) );
	}
	return flxf_app_row( $row, flxf_app_forms_map() );
}

function flxf_app_response_read( WP_REST_Request $r ) {
	$id = FLXF_Response_Service::mark_read( (int) $r['id'] );
	if ( null === $id ) {
		return new WP_Error( 'flxf_missing', __( 'Response not found.', 'flx-forms' ), array( 'status' => 404 ) );
	}
	return array( 'ok' => true, 'id' => $id );
}

/**
 * @param array   $m
 * @param WP_User $user
 */
function flxf_app_manifest( $m, $user ) {
	unset( $user );
	$page = array( 'mode' => 'page', 'param' => 'page', 'size_param' => 'per_page', 'size' => 25, 'items' => 'items', 'total' => 'total', 'pages' => 'pages' );
	$m['flxf'] = array(
		'id'       => 'flxf',
		'plugin'   => 'flx-forms',
		'version'  => flxf_app_version(),
		'min_app'  => '1.0.0',
		'title'    => __( 'Forms', 'flx-forms' ),
		'icon'     => 'note',
		'order'    => 24,
		'menu'     => 'Emsfb',
		'entry'    => 'responses',
		'tabs'     => array( 'forms', 'responses' ),
		'caps'     => array(
			'view' => array( 'manage_options', 'Emsfb' ),
		),
		'fallback' => array( 'web' => 'admin.php?page=Emsfb' ),
		'routes'   => array(
			'admin.php?page=Emsfb'        => array( 'screen' => 'responses' ),
			'admin.php?page=Emsfb_create' => array( 'screen' => 'builder' ),
		),
		'search'   => array(
			'source'  => 'responses',
			'param'   => 's',
			'item'    => array( 'title' => '{{form}}', 'subtitle' => '{{preview}}' ),
			'onPress' => array( 'target' => 'screen:flxf/response', 'params' => array( 'id' => '{{id}}' ) ),
		),
		'sources'  => array(
			'forms'     => array(
				'path'   => 'efb/v1/forms',
				'paging' => array( 'mode' => 'none', 'items' => 'forms' ),
				'key'    => 'id',
				'cache'  => array( 'stale' => 300, 'persist' => true ),
			),
			'responses' => array(
				'path'   => 'flx-forms/v1/responses',
				'query'  => array( 'form' => '{{params.form}}' ),
				'paging' => $page,
				'key'    => 'id',
				'cache'  => array( 'stale' => 30, 'persist' => true ),
				'poll'   => 60,
			),
			'response'  => array( 'path' => 'flx-forms/v1/responses/{{params.id}}', 'cache' => array( 'stale' => 15 ) ),
		),
		'screens'  => array(
			'forms'     => array(
				'type'    => 'list',
				'title'   => __( 'Forms', 'flx-forms' ),
				'source'  => 'forms',
				'item'    => array(
					'title'    => '{{name}}',
					'subtitle' => '{{type}}',
				),
				'onPress' => array( 'target' => 'screen:flxf/responses', 'params' => array( 'form' => '{{id}}' ) ),
				'actions' => array( 'header' => array( 'open_builder' ) ),
				'empty'   => array( 'title' => __( 'No forms', 'flx-forms' ), 'body' => __( 'The form builder opens in the panel.', 'flx-forms' ) ),
			),
			'responses' => array(
				'type'    => 'list',
				'title'   => __( 'Responses', 'flx-forms' ),
				'source'  => 'responses',
				'search'  => array( 'param' => 's', 'placeholder' => __( 'Search in responses', 'flx-forms' ) ),
				'filters' => array(
					array( 'param' => 'unread', 'label' => __( 'Unread', 'flx-forms' ), 'type' => 'toggle' ),
				),
				'item'    => array(
					'title'    => '{{form}}',
					'subtitle' => '{{preview}}',
					'caption'  => '{{date_label}}',
					'unread'   => '{{unread}}',
					'badges'   => array(
						array( 'text' => __( 'New', 'flx-forms' ), 'tone' => 'accent', 'when' => array( 'path' => 'unread', 'op' => 'truthy' ) ),
					),
				),
				'onPress' => array( 'target' => 'screen:flxf/response', 'params' => array( 'id' => '{{id}}' ) ),
				'empty'   => array( 'title' => __( 'No responses', 'flx-forms' ), 'body' => __( 'Form submissions appear here.', 'flx-forms' ) ),
			),
			'response'  => array(
				'type'     => 'detail',
				'title'    => __( 'Reply', 'flx-forms' ),
				'source'   => 'response',
				'header'   => array( 'title' => '{{form}}', 'subtitle' => '{{date_label}}' ),
				'sections' => array(
					array(
						'type'   => 'fields',
						'title'  => __( 'Reply', 'flx-forms' ),
						'fields' => array(
							array( 'label' => __( 'Form', 'flx-forms' ), 'value' => '{{form}}' ),
							array( 'label' => __( 'Tracking code', 'flx-forms' ), 'value' => '{{track}}', 'copy' => true, 'when' => array( 'path' => 'track', 'op' => 'filled' ) ),
							array( 'label' => __( 'Summary', 'flx-forms' ), 'value' => '{{preview}}' ),
						),
					),
					array(
						'type'  => 'list',
						'title' => __( 'Fields', 'flx-forms' ),
						'path'  => 'fields',
						'item'  => array( 'title' => '{{label}}', 'subtitle' => '{{value}}' ),
					),
				),
				'actions'  => array( 'footer' => array( 'mark_read' ) ),
			),
			'builder'   => array(
				'type'  => 'web',
				'title' => __( 'Form builder', 'flx-forms' ),
				'path'  => 'admin.php?page=Emsfb_create',
			),
		),
		'actions'  => array(
			'open_builder' => array( 'label' => __( 'Form builder', 'flx-forms' ), 'icon' => 'edit', 'web' => 'admin.php?page=Emsfb_create' ),
			'mark_read'    => array(
				'label'   => __( 'Mark as read', 'flx-forms' ),
				'icon'    => 'check',
				'when'    => array( 'path' => 'unread', 'op' => 'truthy' ),
				'request' => array( 'method' => 'POST', 'path' => 'flx-forms/v1/responses/{{data.id}}/read' ),
				'after'   => array( 'toast' => __( 'Read', 'flx-forms' ), 'refresh' => true, 'invalidate' => array( 'responses' ) ),
			),
		),
	);
	return $m;
}
