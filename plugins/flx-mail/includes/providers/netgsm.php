<?php
/**
 * SMS · Netgsm REST v2. Flux Certificates'daki sağlayıcıyla aynı istekler.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flxi_connection_types',
	function ( $types ) {
		$types['netgsm'] = array(
			'label'   => __( 'SMS · Netgsm', 'flx-mail' ),
			'channel' => 'sms',
			'help'    => __( 'Netgsm REST v2. The username is usually the subscriber number starting with 850/312…. The message header must be approved in the Netgsm panel.', 'flx-mail' ),
			'fields'  => array(
				array( 'key' => 'username', 'label' => __( 'Username (usercode)', 'flx-mail' ), 'type' => 'text', 'required' => true ),
				array( 'key' => 'password', 'label' => __( 'Password (API sub-user password)', 'flx-mail' ), 'type' => 'password', 'required' => true ),
				array( 'key' => 'header', 'label' => __( 'Message header (msgheader)', 'flx-mail' ), 'type' => 'text', 'required' => true, 'placeholder' => 'ORNEKVAKFI' ),
				array(
					'key'     => 'iysfilter',
					'label'   => __( 'İYS filter', 'flx-mail' ),
					'type'    => 'select',
					'default' => '0',
					'options' => array(
						'0'  => __( '0 · Informational (non-commercial)', 'flx-mail' ),
						'11' => __( '11 · Commercial, individual recipient (İYS-checked)', 'flx-mail' ),
						'12' => __( '12 · Commercial, merchant recipient (İYS-checked)', 'flx-mail' ),
					),
				),
				array(
					'key'     => 'encoding',
					'label'   => __( 'Turkish characters', 'flx-mail' ),
					'type'    => 'select',
					'default' => 'TR',
					'options' => array(
						'TR' => __( 'On (ş, ğ, ı…) · SMS length 155', 'flx-mail' ),
						''   => __( 'Off (ASCII) · SMS length 160', 'flx-mail' ),
					),
				),
				array( 'key' => 'partnercode', 'label' => __( 'Partner code (optional)', 'flx-mail' ), 'type' => 'text' ),
				array( 'key' => 'delay', 'label' => __( 'Delay between messages in bulk send (sec)', 'flx-mail' ), 'type' => 'number', 'default' => 0 ),
				array( 'key' => 'batch_size', 'label' => __( 'Recipients per Netgsm request', 'flx-mail' ), 'type' => 'number', 'default' => 1000, 'help' => __( 'Ready recipients are grouped in one API request. Default: 1000; maximum: 50000.', 'flx-mail' ) ),
			),
			'send'    => 'flxi_netgsm_send',
			'test'    => 'flxi_netgsm_test',
			'queue_batch' => 'flxi_netgsm_queue_batch',
		);
		return $types;
	}
);

function flxi_netgsm_codes() {
	return array(
		'00'   => __( 'Success', 'flx-mail' ),
		'20'   => __( 'Message text is invalid or too long', 'flx-mail' ),
		'30'   => __( 'Invalid username/password, no API permission, or IP restriction', 'flx-mail' ),
		'40'   => __( 'Message header (msgheader) is not defined in the system', 'flx-mail' ),
		'50'   => __( 'İYS-checked sending is not possible with a subscriber account', 'flx-mail' ),
		'51'   => __( 'No İYS brand information for the subscription', 'flx-mail' ),
		'70'   => __( 'Invalid query / missing parameter', 'flx-mail' ),
		'80'   => __( 'Sending rate limit exceeded', 'flx-mail' ),
		'85'   => __( 'More than 20 tasks to the same number in 1 minute', 'flx-mail' ),
		'100'  => __( 'Netgsm system error', 'flx-mail' ),
		'101'  => __( 'Netgsm system error', 'flx-mail' ),
		'5000' => __( 'Unknown error', 'flx-mail' ),
	);
}

/** Netgsm Türkiye numaralarını 5XXXXXXXXX ister; yurt dışı 00 ile. */
function flxi_netgsm_number( $phone ) {
	if ( 12 === strlen( $phone ) && 0 === strpos( $phone, '90' ) ) {
		return substr( $phone, 2 );
	}
	return '00' . $phone;
}

function flxi_netgsm_send( $config, $msg, $conn = array() ) {
	if ( ! empty( $conn['id'] ) ) { return flxi_netgsm_dispatch( $config, array( $msg ), $conn ); }
	return flxi_netgsm_send_batch( $config, array( $msg ) );
}

function flxi_netgsm_request( $config, $endpoint, $payload ) {
	if ( ! in_array( $endpoint, array( 'send', 'report', 'cancel' ), true ) ) { return array( 'ok' => false, 'code' => 0, 'error' => 'Invalid endpoint', 'json' => null, 'body' => '' ); }
	return flxi_http( 'POST', 'https://api.netgsm.com.tr/sms/rest/v2/' . $endpoint, array(
		'headers' => array( 'Content-Type' => 'application/json', 'Authorization' => 'Basic ' . base64_encode( $config['username'] . ':' . $config['password'] ) ),
		'body' => flxi_json( $payload ), 'timeout' => 20,
	) );
}

/** Provider acceptance is distinct from a delivery receipt. */
function flxi_netgsm_send_batch( $config, $messages, $options = array() ) {
	$list = array(); $seen = array();
	foreach ( $messages as $msg ) {
		$phone = flxi_normalize_phone( $msg['recipient'] );
		if ( ! $phone || isset( $seen[ $phone ] ) ) {
		/* translators: %s: telefon */
		return array( 'ok' => false, 'error' => sprintf( __( 'Invalid phone: %s', 'flx-mail' ), $msg['recipient'] ), 'retry' => false );
		}
		$seen[ $phone ] = true; $list[] = array( 'msg' => (string) $msg['body'], 'no' => flxi_netgsm_number( $phone ) );
	}
	$payload = array(
		'msgheader' => (string) $config['header'],
		'messages'  => $list,
		'encoding'  => (string) ( $config['encoding'] ?? 'TR' ),
		'iysfilter' => (string) ( $config['iysfilter'] ?? '0' ),
		'appname'   => 'flx-mail',
	);
	if ( ! empty( $options['reference'] ) ) { $payload['referansID'] = $options['reference']; }
	if ( (int) ( $options['send_at'] ?? 0 ) > time() ) {
		$payload['startdate'] = ( new DateTimeImmutable( '@' . (int) $options['send_at'] ) )->setTimezone( new DateTimeZone( 'Europe/Istanbul' ) )->format( 'dmYHi' );
	}
	if ( ! empty( $config['partnercode'] ) ) {
		$payload['partnercode'] = (string) $config['partnercode'];
	}
	if ( '' === $payload['encoding'] ) {
		unset( $payload['encoding'] );
	}
	$r = flxi_netgsm_request( $config, 'send', $payload );
	$json  = is_array( $r['json'] ) ? $r['json'] : array();
	$code  = isset( $json['code'] ) ? (string) $json['code'] : '';
	$codes = flxi_netgsm_codes();
	$resp  = array( 'http' => $r['code'], 'body' => $json ? $json : $r['body'] );

	if ( in_array( $code, array( '00', '01', '02' ), true ) && ! empty( $json['jobid'] ) ) {
		return array( 'ok' => true, 'external_id' => (string) $json['jobid'], 'response' => $resp, 'code' => $code );
	}
	if ( $r['error'] ) {
		return array( 'ok' => false, 'error' => __( 'Delivery acceptance is unknown. Check Netgsm before sending again.', 'flx-mail' ), 'retry' => false, 'uncertain' => true, 'response' => $resp, 'code' => 'transport' );
	}
	$text  = $code ? ( 'Netgsm ' . $code . ': ' . ( $codes[ $code ] ?? ( $json['description'] ?? '?' ) ) ) : ( 'HTTP ' . $r['code'] . ': ' . mb_substr( $r['body'], 0, 200 ) );
	$uncertain = ! empty( $json['jobid'] ) || ( '' === $code && ( $r['code'] >= 500 || 200 === $r['code'] ) ) || ( '00' === $code && empty( $json['jobid'] ) );
	$retry = ! $uncertain && ( 429 === $r['code'] || in_array( $code, array( '80', '85', '100', '101', '5000' ), true ) );
	return array( 'ok' => false, 'error' => $uncertain ? __( 'Delivery acceptance is unknown. Check Netgsm before sending again.', 'flx-mail' ) : $text, 'retry' => $retry, 'uncertain' => $uncertain, 'response' => $resp, 'code' => $code ?: (string) $r['code'] );
}

function flxi_netgsm_test( $config, $to, $conn ) {
	if ( $to ) {
		/* translators: %s: site adı */
		return flxi_netgsm_send( $config, array( 'recipient' => $to, 'body' => sprintf( __( 'Test SMS · %s', 'flx-mail' ), get_bloginfo( 'name' ) ) ), $conn );
	}
	// Numara verilmezse yalnız bakiye sorgusu (SMS harcamaz).
	$r = flxi_http(
		'POST',
		'https://api.netgsm.com.tr/balance',
		array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => flxi_json( array( 'usercode' => $config['username'], 'password' => $config['password'], 'stip' => 2 ) ),
		)
	);
	$j = is_array( $r['json'] ) ? $r['json'] : array();
	if ( $r['ok'] && ( isset( $j['balance'] ) || ( isset( $j['code'] ) && '00' === (string) $j['code'] ) ) ) {
		return array( 'ok' => true, 'response' => $j );
	}
	return array( 'ok' => false, 'error' => __( 'Balance query failed:', 'flx-mail' ) . ' ' . ( $r['error'] ? $r['error'] : mb_substr( $r['body'], 0, 200 ) ), 'response' => $j );
}
