<?php
/**
 * Diğer eklentiler için ortak API. Ayrıntı: API.md.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/**
 * Kurumsal şablonla e-posta gönderir.
 *
 * @param array $args {
 *   to (string|array, zorunlu), template (şablon anahtarı; kayıtlıysa konu/içerik oradan gelir), vars (değişkenler),
 *   subject, heading, body (HTML), button_text, button_url, image, image_link, image_alt, image_caption, facts, note,
 *   sender ('info'|'noreply'), reply_to, attachments, headers, campaign_id, unsubscribe_url, preheader
 * }
 * @return bool wp_mail sonucu (kapalı şablonda true döner, gönderilmez, kayda "kapalı" yazılır).
 */
function flxi_send_email( array $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'to'          => '',
			'template'    => '',
			'vars'        => array(),
			'sender'      => '',
			'reply_to'    => '',
			'attachments' => array(),
			'headers'     => array(),
			'campaign_id' => 0,
		)
	);
	$key   = (string) $args['template'];
	$types = flxi_email_types();
	$extra = array_diff_key( $args, array_flip( array( 'to', 'template', 'vars', 'reply_to', 'attachments', 'headers', 'campaign_id' ) ) );
	$extra = array_filter( $extra, 'flxi_not_empty_string' );

	if ( $key && isset( $types[ $key ] ) ) {
		$built   = flxi_build_template( $key, (array) $args['vars'], $extra );
		$subject = $built['subject'];
		$html    = $built['html'];
		$sender  = $args['sender'] ? $args['sender'] : $built['sender'];
	} else {
		$vars    = (array) $args['vars'];
		$a       = $extra;
		$a['subject'] = flxi_fill( (string) ( $a['subject'] ?? '' ), $vars );
		$a['heading'] = isset( $a['heading'] ) ? flxi_fill( $a['heading'], $vars, true ) : '';
		$a['body']    = isset( $a['body'] ) ? flxi_fill( flxi_body_html( $a['body'] ), $vars, true ) : '';
		if ( isset( $a['button_url'] ) ) {
			$a['button_url'] = flxi_fill( $a['button_url'], $vars );
		}
		$subject = $a['subject'];
		$sender  = $args['sender'] ? $args['sender'] : 'info';
		$a['sender'] = $sender;
		$html    = flxi_render_email( $a );
	}

	$headers   = flxi_header_lines( $args['headers'] );
	$headers[] = 'Content-Type: text/html; charset=UTF-8';
	$headers[] = 'X-FLXI-Rendered: 1';
	$headers[] = 'X-FLXI-Sender: ' . ( 'noreply' === $sender ? 'noreply' : 'info' );
	if ( $key ) {
		$headers[] = 'X-FLXI-Template: ' . $key;
	}
	if ( $args['campaign_id'] ) {
		$headers[] = 'X-FLXI-Campaign: ' . (int) $args['campaign_id'];
	}
	if ( $args['reply_to'] && is_email( $args['reply_to'] ) ) {
		$headers[] = 'Reply-To: ' . $args['reply_to'];
	}
	$GLOBALS['flxi_retry_ok'] = null;
	$ok = wp_mail( $args['to'], $subject, $html, $headers, (array) $args['attachments'] );
	// no-reply girişi başarısız olup bilgi@ ile yeniden gönderildiyse sonuç o gönderimdir.
	if ( ! $ok && null !== $GLOBALS['flxi_retry_ok'] ) {
		$ok = (bool) $GLOBALS['flxi_retry_ok'];
	}
	$GLOBALS['flxi_retry_ok'] = null;
	return $ok;
}

/** Kayıtlı bir şablonun açık olup olmadığı (diğer eklentiler kendi gönderimlerini atlamak için kullanabilir). */
function flxi_is_email_enabled( $key ) {
	return flxi_template_enabled( $key );
}

/**
 * Bir kanal için bağlantı ayarları (ör. Flux Chat Evolution adresini buradan alır).
 *
 * @return array|null id, type, name, config (çözülmüş: base_url, api_key, instance…)
 */
function flxi_get_channel_connection( $channel = 'whatsapp', $id = 0 ) {
	$c = flxi_resolve_connection( $channel, (int) $id );
	return $c ? array_intersect_key( $c, array_flip( array( 'id', 'type', 'name', 'channel', 'config' ) ) ) : null;
}

/** Bağlantıların listesi (seçim kutuları için; gizli alanlar hariç). */
function flxi_connection_choices( $channel ) {
	$out = array();
	foreach ( flxi_list_connections( $channel, true ) as $c ) {
		$row = array( 'id' => $c['id'], 'name' => $c['name'], 'type' => $c['type'], 'default' => (bool) $c['is_default'] );
		if ( 'whatsapp' === $channel && function_exists( 'flxi_is_wa_type' ) && flxi_is_wa_type( $c['type'] ) ) {
			$st           = flxi_wa_cached_state( $c['id'] );
			$row['state'] = $st['state'];
			$row['me']    = $st['me'];
		}
		$out[] = $row;
	}
	return $out;
}
