<?php
/**
 * WhatsApp · Evolution API (self-hosted). Flux Certificates'daki sağlayıcıyla aynı istekler.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flxi_connection_types',
	function ( $types ) {
		$types['evolution'] = array(
			'label'   => __( 'WhatsApp · Evolution API', 'flx-mail' ),
			'channel' => 'whatsapp',
			'help'    => __( 'The Evolution API on your own server. The address is e.g. https://wa.yourdomain.com; instance is the connection name in the Evolution panel; API key is the global AUTHENTICATION_API_KEY or the instance token.', 'flx-mail' ),
			'fields'  => array(
				array( 'key' => 'base_url', 'label' => __( 'API address', 'flx-mail' ), 'type' => 'url', 'required' => true, 'placeholder' => 'https://wa.ornek.com' ),
				array( 'key' => 'api_key', 'label' => __( 'API key (apikey)', 'flx-mail' ), 'type' => 'password', 'required' => true ),
				array( 'key' => 'instance', 'label' => __( 'Instance name', 'flx-mail' ), 'type' => 'text', 'required' => true ),
				array( 'key' => 'version', 'label' => __( 'API version', 'flx-mail' ), 'type' => 'select', 'default' => 'v2', 'options' => array( 'v2' => 'v2', 'v1' => 'v1' ) ),
				array( 'key' => 'link_preview', 'label' => __( 'Show link preview', 'flx-mail' ), 'type' => 'checkbox', 'default' => 1 ),
				array( 'key' => 'delay', 'label' => __( 'Delay between messages in bulk send (sec)', 'flx-mail' ), 'type' => 'number', 'default' => 4, 'help' => __( 'To avoid the number being blocked, messages are sent at intervals in bulk sends.', 'flx-mail' ) ),
				array( 'key' => 'typing', 'label' => __( '"Typing…" duration (ms)', 'flx-mail' ), 'type' => 'number', 'default' => 1200 ),
			),
			'send'    => 'flxi_evolution_send',
			'test'    => 'flxi_evolution_test',
			'evo'     => true,
		);
		// Flux WPAPI: Evolution v2 uçlarını konuşan hafif köprü (Hostinger Node uygulaması). Aynı istekler, v1 yok.
		$types['flxwpapi'] = array(
			'label'   => __( 'WhatsApp · Flux WPAPI', 'flx-mail' ),
			'channel' => 'whatsapp',
			'help'    => __( 'The Flux WPAPI bridge installed as a Node.js app on Hostinger. The address is e.g. https://wa.example.com; the API key is the bridge panel\'s general API key; the instance is the name you give this number (created on "Connect" if it does not exist).', 'flx-mail' ),
			'fields'  => array_values(
				array_filter(
					$types['evolution']['fields'],
					function ( $f ) {
						return 'version' !== $f['key'];
					}
				)
			),
			'send'    => 'flxi_evolution_send',
			'test'    => 'flxi_evolution_test',
			'evo'     => true,
		);
		return $types;
	}
);

function flxi_evolution_request( $config, $path, $body = null, $method = 'POST' ) {
	$url  = untrailingslashit( (string) $config['base_url'] ) . $path;
	$args = array(
		'headers' => array( 'Content-Type' => 'application/json', 'apikey' => (string) $config['api_key'] ),
		'timeout' => 30,
	);
	if ( null !== $body ) {
		$args['body'] = flxi_json( $body );
	}
	return flxi_http( $method, $url, $args );
}

function flxi_evolution_send( $config, $msg, $conn = array() ) {
	$phone = flxi_wa_number( $msg['recipient'] ); // grup kimliği (…@g.us) olduğu gibi gider
	if ( ! $phone ) {
		/* translators: %s: telefon */
		return array( 'ok' => false, 'error' => sprintf( __( 'Invalid phone: %s', 'flx-mail' ), $msg['recipient'] ), 'retry' => false );
	}
	$instance = rawurlencode( (string) $config['instance'] );
	$v1       = 'v1' === ( $config['version'] ?? 'v2' );
	$preview  = ! empty( $config['link_preview'] );
	$typing   = max( 0, (int) ( $config['typing'] ?? 0 ) );

	if ( $v1 ) {
		$body = array(
			'number'      => $phone,
			'options'     => array( 'delay' => $typing, 'presence' => 'composing', 'linkPreview' => $preview ),
			'textMessage' => array( 'text' => (string) $msg['body'] ),
		);
	} else {
		$body = array(
			'number'      => $phone,
			'text'        => (string) $msg['body'],
			'delay'       => $typing,
			'linkPreview' => $preview,
		);
	}
	$r    = flxi_evolution_request( $config, '/message/sendText/' . $instance, $body );
	$json = is_array( $r['json'] ) ? $r['json'] : array();
	$resp = array( 'http' => $r['code'], 'body' => $json ? $json : $r['body'] );

	if ( ! $r['ok'] ) {
		if ( $r['error'] ) {
			return array( 'ok' => false, 'error' => __( 'Connection error:', 'flx-mail' ) . ' ' . $r['error'], 'retry' => true, 'response' => $resp );
		}
		$detail = '';
		if ( isset( $json['response']['message'] ) ) {
			$detail = is_array( $json['response']['message'] ) ? wp_json_encode( $json['response']['message'], JSON_UNESCAPED_UNICODE ) : $json['response']['message'];
		} elseif ( isset( $json['message'] ) ) {
			$detail = is_array( $json['message'] ) ? wp_json_encode( $json['message'], JSON_UNESCAPED_UNICODE ) : $json['message'];
		}
		if ( false !== strpos( (string) $detail, '"exists":false' ) ) {
			$detail = __( 'This number does not have WhatsApp.', 'flx-mail' );
		}
		$retry = $r['code'] >= 500 || in_array( $r['code'], array( 404, 429 ), true );
		return array( 'ok' => false, 'error' => 'Evolution HTTP ' . $r['code'] . ( $detail ? ': ' . mb_substr( (string) $detail, 0, 300 ) : '' ), 'retry' => $retry, 'response' => $resp );
	}
	$external = (string) ( $json['key']['id'] ?? '' );

	if ( ! empty( $msg['media'] ) ) {
		$media = array(
			'number'    => $phone,
			'mediatype' => 'document',
			'mimetype'  => 'application/pdf',
			'media'     => $msg['media'],
			'fileName'  => $msg['file_name'] ? $msg['file_name'] : basename( (string) wp_parse_url( $msg['media'], PHP_URL_PATH ) ),
			'caption'   => '',
		);
		$body2       = $v1 ? array( 'number' => $phone, 'mediaMessage' => array_diff_key( $media, array( 'number' => 1 ) ) ) : $media;
		$r2          = flxi_evolution_request( $config, '/message/sendMedia/' . $instance, $body2 );
		$resp['pdf'] = array( 'http' => $r2['code'], 'ok' => $r2['ok'] );
	}
	return array( 'ok' => true, 'external_id' => $external, 'response' => $resp );
}

function flxi_evolution_test( $config, $to, $conn ) {
	$r     = flxi_evolution_request( $config, '/instance/connectionState/' . rawurlencode( (string) $config['instance'] ), null, 'GET' );
	$j     = is_array( $r['json'] ) ? $r['json'] : array();
	$state = $j['instance']['state'] ?? ( $j['state'] ?? '' );
	if ( ! $r['ok'] ) {
		return array( 'ok' => false, 'error' => $r['error'] ? $r['error'] : 'HTTP ' . $r['code'] . ' ' . mb_substr( $r['body'], 0, 200 ), 'response' => $j );
	}
	if ( 'open' !== $state ) {
		/* translators: %s: bağlantı durumu */
		return array( 'ok' => false, 'error' => sprintf( __( 'Number not connected (status: %s). Scan the QR with "Connect".', 'flx-mail' ), $state ? $state : '?' ), 'response' => $j );
	}
	if ( $to ) {
		/* translators: %s: site adı */
		return flxi_evolution_send( $config, array( 'recipient' => $to, 'body' => sprintf( __( 'Test message · %s 🌱', 'flx-mail' ), get_bloginfo( 'name' ) ), 'media' => '', 'file_name' => '' ), $conn );
	}
	return array( 'ok' => true, 'response' => $j );
}
