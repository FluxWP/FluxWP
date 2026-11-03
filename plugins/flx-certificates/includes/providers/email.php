<?php
defined( 'ABSPATH' ) || exit;

add_filter(
	'flxs_provider_types',
	function ( $types ) {
		$from = array(
			array( 'key' => 'from_name', 'label' => 'Gönderen adı', 'type' => 'text', 'default' => 'Örnek Vakfı' ),
			array( 'key' => 'from_email', 'label' => 'Gönderen e-posta', 'type' => 'text', 'help' => 'Boşsa WordPress varsayılanı. SMTP hesabıyla aynı alan adında olmalı (SPF/DKIM).' ),
			array( 'key' => 'reply_to', 'label' => 'Yanıt adresi (Reply-To)', 'type' => 'text' ),
		);
		$types['wpmail'] = array(
			'label'   => 'WordPress e-postası (wp_mail)',
			'channel' => 'email',
			'help'    => 'Sitenin mevcut e-posta ayarını kullanır (ör. WP Mail SMTP eklentisi kuruluysa onun üzerinden gider).',
			'fields'  => $from,
			'send'    => 'flxs_email_send',
			'test'    => 'flxs_email_test',
		);
		$types['smtp'] = array(
			'label'   => 'SMTP hesabı',
			'channel' => 'email',
			'help'    => 'Gmail/Workspace, Yandex, Office 365 veya hosting SMTP. Gmail için uygulama şifresi gerekir.',
			'fields'  => array_merge(
				array(
					array( 'key' => 'host', 'label' => 'SMTP sunucu', 'type' => 'text', 'required' => true, 'placeholder' => 'smtp.gmail.com' ),
					array( 'key' => 'port', 'label' => 'Port', 'type' => 'number', 'default' => 587 ),
					array( 'key' => 'secure', 'label' => 'Şifreleme', 'type' => 'select', 'options' => array( 'tls' => 'TLS (587)', 'ssl' => 'SSL (465)', '' => 'Yok' ), 'default' => 'tls' ),
					array( 'key' => 'username', 'label' => 'Kullanıcı adı', 'type' => 'text' ),
					array( 'key' => 'password', 'label' => 'Şifre', 'type' => 'password' ),
				),
				$from
			),
			'send'    => 'flxs_email_send',
			'test'    => 'flxs_email_test',
		);
		return $types;
	}
);

/**
 * E-posta renkleri: sitenin tema paleti (FluxWP temasında indigo; ön ayar ya da Site Editor değiştirdiyse o).
 *
 * @return array{primary:string,dark:string,ink:string,text:string,mint:string,soft:string,line:string}
 */
function flxs_email_colors() {
	$c = array(
		'primary'      => '#4338CA',
		'primary-dark' => '#3730A3',
		'ink'          => '#1E1B4B',
		'text'         => '#4B5563',
		'mint'         => '#EEF2FF',
		'mint-soft'    => '#F7F8FE',
		'line'         => '#E0E7FF',
	);
	if ( function_exists( 'wp_get_global_settings' ) ) {
		$p = wp_get_global_settings( array( 'color', 'palette' ) );
		foreach ( array( 'theme', 'custom' ) as $origin ) {
			foreach ( (array) ( $p[ $origin ] ?? array() ) as $item ) {
				$hex = sanitize_hex_color( $item['color'] ?? '' );
				if ( $hex && isset( $c[ $item['slug'] ?? '' ] ) ) {
					$c[ $item['slug'] ] = $hex;
				}
			}
		}
	}
	return array(
		'primary' => $c['primary'],
		'dark'    => $c['primary-dark'],
		'ink'     => $c['ink'],
		'text'    => $c['text'],
		'mint'    => $c['mint'],
		'soft'    => $c['mint-soft'],
		'line'    => $c['line'],
	);
}

function flxs_email_html( $subject, $body_text, $link = '' ) {
	$k    = flxs_email_colors();
	$text = esc_html( $body_text );
	$text = preg_replace( '~(https?://[^\s<]+)~', '<a href="$1" style="color:' . $k['primary'] . '">$1</a>', $text );
	$button = '';
	if ( $link ) {
		$button = '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:8px 0 8px"><tr><td style="border-radius:999px;background:' . $k['primary'] . '">'
			. '<a href="' . esc_url( $link ) . '" style="display:inline-block;padding:13px 26px;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;border-radius:999px">Sertifikayı görüntüle</a>'
			. '</td></tr></table>';
	}
	$text = str_replace( '{buton}', $button, $text );
	$text = nl2br( $text );
	$site = esc_html( get_bloginfo( 'name' ) );
	$home = esc_url( home_url( '/' ) );
	return '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . esc_html( $subject ) . '</title></head>'
		. '<body style="margin:0;background:' . $k['soft'] . ';font-family:Segoe UI,Helvetica,Arial,sans-serif;color:' . $k['ink'] . '">'
		. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:' . $k['soft'] . ';padding:24px 12px"><tr><td align="center">'
		. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid ' . $k['line'] . '">'
		. '<tr><td style="background:' . $k['primary'] . ';padding:18px 26px;color:#ffffff;font-size:17px;font-weight:800">🌱 ' . $site . '</td></tr>'
		. '<tr><td style="padding:26px;font-size:15px;line-height:1.6;color:' . $k['ink'] . '">' . $text . '</td></tr>'
		. '<tr><td style="padding:14px 26px;background:' . $k['mint'] . ';font-size:12px;color:' . $k['text'] . '"><a href="' . $home . '" style="color:' . $k['dark'] . '">' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</a></td></tr>'
		. '</table></td></tr></table></body></html>';
}

function flxs_email_send( $config, $msg, $provider = array() ) {
	$to = sanitize_email( $msg['recipient'] );
	if ( ! is_email( $to ) ) {
		return array( 'ok' => false, 'error' => 'Geçersiz e-posta: ' . $msg['recipient'], 'retry' => false );
	}
	$link = '';
	if ( ! empty( $msg['certificate_id'] ) ) {
		$c    = flxs_get_certificate( $msg['certificate_id'] );
		$link = $c ? flxs_certificate_url( $c ) : '';
	}
	$html    = flxs_email_html( $msg['subject'], (string) $msg['body'], $link );
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	if ( ! empty( $config['reply_to'] ) && is_email( $config['reply_to'] ) ) {
		$headers[] = 'Reply-To: ' . $config['reply_to'];
	}

	$error = '';
	$on_fail = function ( $wp_error ) use ( &$error ) {
		$error = $wp_error->get_error_message();
	};
	$from_email = ! empty( $config['from_email'] ) && is_email( $config['from_email'] ) ? $config['from_email'] : '';
	$from_name  = ! empty( $config['from_name'] ) ? $config['from_name'] : '';
	$f_email = function ( $v ) use ( $from_email ) {
		return $from_email ? $from_email : $v;
	};
	$f_name = function ( $v ) use ( $from_name ) {
		return $from_name ? $from_name : $v;
	};
	$smtp = null;
	if ( 'smtp' === ( $provider['type'] ?? '' ) ) {
		$smtp = function ( $mailer ) use ( $config, $from_email, $from_name ) {
			$mailer->isSMTP();
			$mailer->Host       = $config['host'];
			$mailer->Port       = (int) ( $config['port'] ? $config['port'] : 587 );
			$mailer->SMTPSecure = (string) $config['secure'];
			$mailer->SMTPAutoTLS = '' !== (string) $config['secure'];
			$mailer->SMTPAuth   = '' !== (string) $config['username'];
			$mailer->Username   = (string) $config['username'];
			$mailer->Password   = (string) $config['password'];
			$mailer->Timeout    = 20;
			if ( $from_email ) {
				$mailer->setFrom( $from_email, $from_name, false );
			} elseif ( $config['username'] && is_email( $config['username'] ) ) {
				$mailer->setFrom( $config['username'], $from_name, false );
			}
		};
		add_action( 'phpmailer_init', $smtp, PHP_INT_MAX );
	}
	add_action( 'wp_mail_failed', $on_fail );
	add_filter( 'wp_mail_from', $f_email, PHP_INT_MAX );
	add_filter( 'wp_mail_from_name', $f_name, PHP_INT_MAX );

	$ok = wp_mail( $to, $msg['subject'], $html, $headers );

	remove_action( 'wp_mail_failed', $on_fail );
	remove_filter( 'wp_mail_from', $f_email, PHP_INT_MAX );
	remove_filter( 'wp_mail_from_name', $f_name, PHP_INT_MAX );
	if ( $smtp ) {
		remove_action( 'phpmailer_init', $smtp, PHP_INT_MAX );
	}

	if ( $ok ) {
		return array( 'ok' => true, 'response' => array( 'mailer' => $provider['type'] ?? 'wpmail' ) );
	}
	return array( 'ok' => false, 'error' => $error ? $error : 'wp_mail false döndü (sunucu e-posta gönderemiyor olabilir).', 'retry' => true );
}

function flxs_email_test( $config, $to, $provider ) {
	$msg = array(
		'recipient'      => $to,
		'subject'        => 'Test e-postası · ' . get_bloginfo( 'name' ),
		'body'           => "Bu bir test e-postasıdır.\nSağlayıcı: " . $provider['name'] . "\n\n{buton}",
		'certificate_id' => 0,
	);
	return flxs_email_send( $config, $msg, $provider );
}
