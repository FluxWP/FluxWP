<?php
/**
 * Flux Chat'te toplu mesaj başlığı: Flux Mail'in kampanyadan gönderdiği WhatsApp mesajının balonunun
 * üstünde kısa kampanya adı, tıklayınca gönderim detayı (Flux Chat 1.48.0+ `flxc_message_origin`).
 *
 * Eşleşme sırası: kuyruk satırındaki mesaj kimliği (ext_id), gönderim öncesi bırakılan işaret
 * (numara + metin, kampanya id'li), eski mesajlarda numara + zaman (±10 dk).
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/**
 * Kampanyanın gönderim detay adresi. CRM etkinliğinden gidenlerde etkinliğin mesaj sayfası;
 * CRM kendi gönderim detayına `flxi_campaign_url` ile yönlendirebilir.
 */
function flxi_campaign_url( array $c ) {
	$ref = (string) ( $c['data']['ref'] ?? '' );
	$url = flxi_admin_url( 'flxi-kuyruk', array( 'campaign' => (int) $c['id'] ) );
	if ( preg_match( '/^flxcrm_event:(\d+)$/', $ref, $m ) && function_exists( 'flxcrm_events_url' ) ) {
		$url = flxcrm_events_url( array( 'id' => (int) $m[1], 'view' => 'message', 'sub' => 'sent' ) );
	}
	return (string) apply_filters( 'flxi_campaign_url', $url, $c );
}

/** Flux Chat'e verilecek köken bilgisi. */
function flxi_campaign_origin( $cid ) {
	$c = flxi_get_campaign( $cid );
	if ( ! $c ) {
		return null;
	}
	$ref  = (string) ( $c['data']['ref'] ?? '' );
	$name = trim( (string) $c['name'] );
	if ( '' === $name ) {
		$name = wp_strip_all_tags( (string) ( $c['data']['text'] ?? '' ) );
	}
	return array(
		'label'       => mb_strimwidth( preg_replace( '/\s+/u', ' ', $name ), 0, 48, '…' ),
		'url'         => flxi_campaign_url( $c ),
		'kind'        => 0 === strpos( $ref, 'flxcrm_event:' ) ? 'event' : 'campaign',
		'campaign_id' => (int) $c['id'],
		'ref'         => $ref,
	);
}

/** Mesajın hangi kampanyadan gittiğini bulur (0: kampanya değil / bilinmiyor). */
function flxi_campaign_for_message( array $ctx ) {
	global $wpdb;
	$t     = flxi_table( 'queue' );
	$wa_id = (string) ( $ctx['wa_id'] ?? '' );
	if ( '' !== $wa_id ) {
		$cid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT campaign_id FROM $t WHERE ext_id = %s AND ext_id <> '' ORDER BY id DESC LIMIT 1", $wa_id ) ); // phpcs:ignore
		if ( $cid ) {
			return $cid;
		}
	}
	$phone = (string) ( $ctx['phone'] ?? '' );
	if ( '' === $phone ) {
		return 0;
	}
	// Gönderim yanıtından önce gelen echo: bekleyen işaret.
	$cid = flxi_sending_campaign( $phone, $ctx['body'] ?? null );
	if ( $cid ) {
		return $cid;
	}
	// Eski mesajlar: aynı numaraya ±10 dk içinde gönderilen kuyruk satırı.
	$ts = (float) ( $ctx['ts'] ?? 0 );
	if ( $ts <= 0 ) {
		return 0;
	}
	$ts   = $ts > 1e12 ? (int) floor( $ts / 1000 ) : (int) $ts;
	$key  = substr( flxi_optout_key( 'whatsapp', $phone ), -10 );
	if ( strlen( $key ) < 7 ) {
		return 0;
	}
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT campaign_id, sent_at FROM $t WHERE channel = 'whatsapp' AND recipient LIKE %s AND status IN ('sent','delivered') AND sent_at BETWEEN %s AND %s", '%' . $wpdb->esc_like( $key ), gmdate( 'Y-m-d H:i:s', $ts - 600 ), gmdate( 'Y-m-d H:i:s', $ts + 600 ) ), ARRAY_A ); // phpcs:ignore
	usort(
		$rows,
		function ( $a, $b ) use ( $ts ) {
			return abs( strtotime( $a['sent_at'] . ' UTC' ) - $ts ) <=> abs( strtotime( $b['sent_at'] . ' UTC' ) - $ts );
		}
	);
	$row = $rows ? $rows[0] : null;
	return $row ? (int) $row['campaign_id'] : 0;
}

add_filter(
	'flxc_message_origin',
	function ( $origin, $ctx = array() ) {
		$source = (string) ( $ctx['source'] ?? '' );
		if ( '' !== $source && 0 !== strpos( $source, 'flx-mail' ) && 'phone' !== $source ) {
			return $origin;
		}
		$cid = flxi_campaign_for_message( (array) $ctx );
		if ( ! $cid ) {
			return $origin;
		}
		$o = flxi_campaign_origin( $cid );
		return $o ? $o : $origin;
	},
	10,
	2
);
