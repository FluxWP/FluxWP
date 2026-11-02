<?php
defined( 'ABSPATH' ) || exit;

/*
 * Evolution API webhook ucu: POST /wp-json/flx-chat/v1/webhook/{bağlantı}?t={gizli anahtar}
 * "Webhook by events" açıksa Evolution adresin sonuna olay adını ekler (/messages-upsert); o da kabul edilir.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flx-chat/v1',
			'/webhook/(?P<conn>[a-z0-9]+)(?:/(?P<ev>[a-z0-9-]+))?',
			array(
				'methods'             => 'POST',
				'callback'            => 'flxc_rest_webhook',
				'permission_callback' => '__return_true',
			)
		);
	}
);

function flxc_rest_webhook( WP_REST_Request $req ) {
	$conn = flxc_get_connection( sanitize_key( $req['conn'] ) );
	$t    = (string) $req->get_param( 't' );
	if ( ! $conn || '' === $t || ! hash_equals( (string) $conn['secret'], $t ) ) {
		return new WP_REST_Response( array( 'ok' => false ), 403 );
	}
	$payload = $req->get_json_params();
	if ( ! is_array( $payload ) ) {
		$payload = json_decode( (string) $req->get_body(), true );
	}
	if ( ! is_array( $payload ) ) {
		return new WP_REST_Response( array( 'ok' => false ), 400 );
	}
	if ( empty( $payload['event'] ) && ! empty( $req['ev'] ) ) {
		$payload['event'] = str_replace( '-', '.', (string) $req['ev'] );
	}
	try {
		flxc_handle_event( $conn['id'], $payload );
	} catch ( Throwable $e ) {
		flxc_log_error( 'webhook: ' . $e->getMessage() );
	}
	update_option( 'flxc_last_webhook_' . $conn['id'], time(), false );
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}
