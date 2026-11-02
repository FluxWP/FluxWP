<?php
/**
 * Anlık bildirim: menü değişince kayıtlı her menü sitesinin hook adresine imzalı POST (bloklamadan).
 *
 * Gövde: {"event":"menu.updated","menu":12,"version":8,"ts":1761300000}
 * Başlık: X-FLXQ-Signature: sha256=<hex hash_hmac('sha256', gövde, hook_secret)>
 *
 * Gönderim istek sonunda (shutdown) yapılır; mümkünse önce yanıt istemciye bitirilir.
 *
 * @package FLX_QR_Menu
 */

defined( 'ABSPATH' ) || exit;

function flxq_queue_ping( $menu_id = null ) {
	static $queue = array();
	static $hooked = false;
	if ( null === $menu_id ) {
		$q     = $queue;
		$queue = array();
		return $q;
	}
	$queue[ (int) $menu_id ] = true;
	if ( ! $hooked ) {
		$hooked = true;
		add_action( 'shutdown', 'flxq_flush_pings', 1 );
	}
	return array();
}

function flxq_flush_pings() {
	$ids = array_keys( flxq_queue_ping( null ) );
	if ( ! $ids ) {
		return;
	}
	if ( function_exists( 'fastcgi_finish_request' ) ) {
		fastcgi_finish_request();
	} elseif ( function_exists( 'litespeed_finish_request' ) ) {
		litespeed_finish_request();
	}
	foreach ( $ids as $id ) {
		flxq_send_pings( $id );
	}
}

/** Yalnız http(s); özel/yerel ağ adresleri WP_DEBUG kapalıyken atlanır (SSRF). */
function flxq_hook_url_allowed( $url ) {
	$p = wp_parse_url( (string) $url );
	if ( ! $p || empty( $p['host'] ) || empty( $p['scheme'] ) || ! in_array( strtolower( $p['scheme'] ), array( 'http', 'https' ), true ) ) {
		return false;
	}
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		return true;
	}
	$host = trim( $p['host'], '[]' );
	if ( 'localhost' === strtolower( $host ) ) {
		return false;
	}
	$ips = filter_var( $host, FILTER_VALIDATE_IP ) ? array( $host ) : (array) gethostbynamel( $host );
	if ( ! $ips ) {
		return false;
	}
	foreach ( $ips as $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}
	}
	return true;
}

/** @return int Gönderilen bildirim sayısı. */
function flxq_send_pings( $id ) {
	$secret = (string) get_post_meta( $id, '_flxq_hook_secret', true );
	if ( '' === $secret ) {
		return 0;
	}
	$body = wp_json_encode(
		array(
			'event'   => 'menu.updated',
			'menu'    => (int) $id,
			'version' => flxq_menu_version( $id ),
			'ts'      => time(),
		)
	);
	$sig  = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
	$sent = 0;
	$done = array();
	foreach ( flxq_get_sites( $id ) as $s ) {
		$hook = isset( $s['hook'] ) ? (string) $s['hook'] : '';
		if ( '' === $hook || isset( $done[ $hook ] ) || ! flxq_hook_url_allowed( $hook ) ) {
			continue;
		}
		$done[ $hook ] = true;
		wp_remote_post(
			$hook,
			array(
				'blocking'           => false,
				'timeout'            => 3,
				'redirection'        => 0,
				'reject_unsafe_urls' => ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ),
				'user-agent'         => 'flx-qr-menu/' . FLXQ_VERSION . '; ' . home_url( '/' ),
				'headers'            => array(
					'Content-Type'    => 'application/json',
					'X-FLXQ-Signature' => $sig,
				),
				'body'               => $body,
			)
		);
		++$sent;
	}
	if ( $sent ) {
		update_post_meta( $id, '_flxq_last_ping', time() );
	}
	return $sent;
}
