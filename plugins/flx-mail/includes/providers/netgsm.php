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
			'help'    => __( 'Netgsm REST v2. Kullanıcı adı genelde 850/312… ile başlayan abone numarasıdır. Mesaj başlığı Netgsm panelinde onaylı olmalı.', 'flx-mail' ),
			'fields'  => array(
				array( 'key' => 'username', 'label' => __( 'Kullanıcı adı (usercode)', 'flx-mail' ), 'type' => 'text', 'required' => true ),
				array( 'key' => 'password', 'label' => __( 'Şifre (API alt kullanıcı şifresi)', 'flx-mail' ), 'type' => 'password', 'required' => true ),
				array( 'key' => 'header', 'label' => __( 'Mesaj başlığı (msgheader)', 'flx-mail' ), 'type' => 'text', 'required' => true, 'placeholder' => 'ORNEKVAKFI' ),
				array(
					'key'     => 'iysfilter',
					'label'   => __( 'İYS filtresi', 'flx-mail' ),
					'type'    => 'select',
					'default' => '0',
					'options' => array(
						'0'  => __( '0 · Bilgilendirme (ticari değil)', 'flx-mail' ),
						'11' => __( '11 · Ticari, bireysel alıcı (İYS kontrollü)', 'flx-mail' ),
						'12' => __( '12 · Ticari, tacir alıcı (İYS kontrollü)', 'flx-mail' ),
					),
				),
				array(
					'key'     => 'encoding',
					'label'   => __( 'Türkçe karakter', 'flx-mail' ),
					'type'    => 'select',
					'default' => 'TR',
					'options' => array(
						'TR' => __( 'Açık (ş, ğ, ı…) · SMS boyu 155', 'flx-mail' ),
						''   => __( 'Kapalı (ASCII) · SMS boyu 160', 'flx-mail' ),
					),
				),
				array( 'key' => 'partnercode', 'label' => __( 'Partner kodu (isteğe bağlı)', 'flx-mail' ), 'type' => 'text' ),
				array( 'key' => 'delay', 'label' => __( 'Toplu gönderimde mesajlar arası bekleme (sn)', 'flx-mail' ), 'type' => 'number', 'default' => 0 ),
			),
			'send'    => 'flxi_netgsm_send',
			'test'    => 'flxi_netgsm_test',
		);
		return $types;
	}
);

function flxi_netgsm_codes() {
	return array(
		'00'   => __( 'Başarılı', 'flx-mail' ),
		'20'   => __( 'Mesaj metni hatalı ya da çok uzun', 'flx-mail' ),
		'30'   => __( 'Geçersiz kullanıcı adı/şifre, API yetkisi yok ya da IP kısıtı', 'flx-mail' ),
		'40'   => __( 'Mesaj başlığı (msgheader) sistemde tanımlı değil', 'flx-mail' ),
		'50'   => __( 'Abone hesabıyla İYS kontrollü gönderim yapılamaz', 'flx-mail' ),
		'51'   => __( 'Aboneliğe ait İYS marka bilgisi yok', 'flx-mail' ),
		'70'   => __( 'Hatalı sorgu / eksik parametre', 'flx-mail' ),
		'80'   => __( 'Gönderim hız limiti aşıldı', 'flx-mail' ),
		'85'   => __( 'Aynı numaraya 1 dakikada 20\'den fazla görev', 'flx-mail' ),
		'100'  => __( 'Netgsm sistem hatası', 'flx-mail' ),
		'101'  => __( 'Netgsm sistem hatası', 'flx-mail' ),
		'5000' => __( 'Tanımsız hata', 'flx-mail' ),
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
	$phone = flxi_normalize_phone( $msg['recipient'] );
	if ( ! $phone ) {
		/* translators: %s: telefon */
		return array( 'ok' => false, 'error' => sprintf( __( 'Geçersiz telefon: %s', 'flx-mail' ), $msg['recipient'] ), 'retry' => false );
	}
	$payload = array(
		'msgheader' => (string) $config['header'],
		'messages'  => array( array( 'msg' => (string) $msg['body'], 'no' => flxi_netgsm_number( $phone ) ) ),
		'encoding'  => (string) ( $config['encoding'] ?? 'TR' ),
		'iysfilter' => (string) ( $config['iysfilter'] ?? '0' ),
		'appname'   => 'flx-mail',
	);
	if ( ! empty( $config['partnercode'] ) ) {
		$payload['partnercode'] = (string) $config['partnercode'];
	}
	if ( '' === $payload['encoding'] ) {
		unset( $payload['encoding'] );
	}
	$r     = flxi_http(
		'POST',
		'https://api.netgsm.com.tr/sms/rest/v2/send',
		array(
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( $config['username'] . ':' . $config['password'] ),
			),
			'body'    => flxi_json( $payload ),
		)
	);
	$json  = is_array( $r['json'] ) ? $r['json'] : array();
	$code  = isset( $json['code'] ) ? (string) $json['code'] : '';
	$codes = flxi_netgsm_codes();
	$resp  = array( 'http' => $r['code'], 'body' => $json ? $json : $r['body'] );

	if ( '00' === $code ) {
		return array( 'ok' => true, 'external_id' => (string) ( $json['jobid'] ?? '' ), 'response' => $resp );
	}
	if ( $r['error'] ) {
		return array( 'ok' => false, 'error' => __( 'Bağlantı hatası:', 'flx-mail' ) . ' ' . $r['error'], 'retry' => true, 'response' => $resp );
	}
	$text  = $code ? ( 'Netgsm ' . $code . ': ' . ( $codes[ $code ] ?? ( $json['description'] ?? '?' ) ) ) : ( 'HTTP ' . $r['code'] . ': ' . mb_substr( $r['body'], 0, 200 ) );
	$retry = in_array( $code, array( '80', '85', '100', '101', '5000', '' ), true );
	return array( 'ok' => false, 'error' => $text, 'retry' => $retry, 'response' => $resp );
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
	return array( 'ok' => false, 'error' => __( 'Bakiye sorgusu başarısız:', 'flx-mail' ) . ' ' . ( $r['error'] ? $r['error'] : mb_substr( $r['body'], 0, 200 ) ), 'response' => $j );
}
