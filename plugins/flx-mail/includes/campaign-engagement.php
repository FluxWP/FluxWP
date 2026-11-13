<?php
/**
 * Kampanya etkileşimi: ulaştı, okundu, cevap verdi, IPTAL.
 *
 * WhatsApp: Flux Mail'in gönderdiği mesaj Flux Chat'e "telefondan giden" olarak düşer ve teslim/okundu
 * durumu (messages.update) orada güncellenir. IPTAL / BAŞLA mesajı cevap sayılmaz. Kuyruk satırındaki mesaj kimliği (ext_id, 1.20.0+) ile,
 * eski satırlarda gönderim saatine en yakın giden mesajla eşleşir. Cevap: gönderimden sonraki 72 saatte
 * kişinin yazdığı ilk mesaj. IPTAL: bu kampanyadan sonra çıkış (optouts.campaign_id).
 * SMS: Netgsm teslim raporu. E-posta: gönderildi = ulaştı; açılma takibi yok (okundu null).
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Cevap penceresi (saniye). */
function flxi_engagement_window() {
	return (int) apply_filters( 'flxi_engagement_reply_window', 72 * HOUR_IN_SECONDS );
}

/** Flux Chat mesaj tabloları okunabiliyor mu. */
function flxi_chat_messages_ready() {
	global $wpdb;
	static $ok = null;
	if ( null === $ok ) {
		$ok = function_exists( 'flxc_table' ) && flxc_table( 'messages' ) === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', flxc_table( 'messages' ) ) ); // phpcs:ignore
	}
	return $ok;
}

/**
 * Satır başına etkileşim (kuyruk satırları zaten okunmuş olarak verilir).
 *
 * @param array $c    kampanya
 * @param array $rows kuyruk satırları (id, recipient, status, sent_at, ext_id)
 * @return array id => [ delivered, read, replied, optout, delivered_at, read_at, replied_at ] (bilinmeyen: null)
 */
function flxi_engagement_rows( array $c, array $rows ) {
	global $wpdb;
	$out = array();
	$ch  = $c['channel'];
	$win = flxi_engagement_window();
	foreach ( $rows as $r ) {
		$sent             = in_array( $r['status'], array( 'sent', 'delivered' ), true );
		$out[ $r['id'] ] = array(
			'delivered'    => 'email' === $ch ? $sent : ( 'sms' === $ch ? ( 'delivered' === $r['status'] ? true : ( $sent ? null : false ) ) : null ),
			'read'         => null,
			'replied'      => 'email' === $ch ? null : false,
			'optout'       => false,
			'delivered_at' => 'email' === $ch && $sent ? $r['sent_at'] : null,
			'read_at'      => null,
			'replied_at'   => null,
		);
	}
	if ( ! $rows ) {
		return $out;
	}

	// IPTAL / çıkış: bu kampanyaya bağlı çıkışlar (kayıt sırasında öncesinde giden son mesajın kampanyası).
	if ( 'email' !== $ch ) {
		$keys = $wpdb->get_col( $wpdb->prepare( 'SELECT recipient FROM ' . flxi_table( 'optouts' ) . ' WHERE campaign_id = %d AND channel = %s', (int) $c['id'], $ch ) ); // phpcs:ignore
		$keys = array_flip( (array) $keys );
		foreach ( $rows as $r ) {
			if ( isset( $keys[ flxi_optout_key( $ch, $r['recipient'] ) ] ) ) {
				$out[ $r['id'] ]['optout'] = true;
			}
		}
	} else {
		$uns = array_flip( array_map( 'strtolower', flxi_unsubscribed() ) );
		foreach ( $rows as $r ) {
			$out[ $r['id'] ]['optout'] = isset( $uns[ strtolower( $r['recipient'] ) ] ) ? true : null;
		}
	}

	if ( 'whatsapp' !== $ch || ! flxi_chat_messages_ready() ) {
		if ( 'whatsapp' === $ch ) {
			foreach ( $out as &$o ) {
				$o['delivered'] = $o['read'] = $o['replied'] = null;
			}
			unset( $o );
		}
		return $out;
	}

	// WhatsApp: sohbetler (telefona göre) ve o sohbetlerdeki mesajlar, tek seferde.
	$by_phone = array();
	foreach ( $rows as $r ) {
		if ( $r['sent_at'] && in_array( $r['status'], array( 'sent', 'delivered' ), true ) ) {
			$by_phone[ flxi_optout_key( 'whatsapp', $r['recipient'] ) ][] = $r;
		}
	}
	if ( ! $by_phone ) {
		return $out;
	}
	$mt    = flxc_table( 'messages' );
	$ct    = flxc_table( 'chats' );
	$chats = array();
	foreach ( array_chunk( array_keys( $by_phone ), 300 ) as $chunk ) {
		$ph = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, phone FROM $ct WHERE phone IN ($ph)", $chunk ), ARRAY_A ) as $cr ) { // phpcs:ignore
			$chats[ (int) $cr['id'] ] = (string) $cr['phone'];
		}
	}
	if ( ! $chats ) {
		return $out;
	}
	$min  = PHP_INT_MAX;
	$max  = 0;
	foreach ( $by_phone as $list ) {
		foreach ( $list as $r ) {
			$t   = strtotime( $r['sent_at'] . ' UTC' );
			$min = min( $min, $t );
			$max = max( $max, $t );
		}
	}
	$msgs = array();
	foreach ( array_chunk( array_keys( $chats ), 300 ) as $chunk ) {
		$ph   = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
		$prm  = array_merge( $chunk, array( ( $min - 600 ) * 1000, ( $max + $win ) * 1000 ) );
		$list = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, chat_id, wa_id, direction, status, source, ts, meta, body FROM $mt WHERE chat_id IN ($ph) AND ts BETWEEN %d AND %d ORDER BY ts ASC", $prm ), ARRAY_A ); // phpcs:ignore
		foreach ( $list as $m ) {
			$msgs[ $chats[ (int) $m['chat_id'] ] ][] = $m;
		}
	}
	foreach ( $by_phone as $phone => $list ) {
		$ms = $msgs[ $phone ] ?? array();
		foreach ( $list as $r ) {
			$t    = strtotime( $r['sent_at'] . ' UTC' ) * 1000;
			$best = null;
			foreach ( $ms as $m ) {
				if ( 'out' !== $m['direction'] ) {
					continue;
				}
				if ( '' !== (string) ( $r['ext_id'] ?? '' ) && $m['wa_id'] === $r['ext_id'] ) {
					$best = $m;
					break;
				}
				// Eski satırlar: gönderim saatine 10 dk içinde en yakın giden mesaj.
				if ( abs( (int) $m['ts'] - $t ) <= 600000 && ( ! $best || abs( (int) $m['ts'] - $t ) < abs( (int) $best['ts'] - $t ) ) ) {
					$best = $m;
				}
			}
			$o = &$out[ $r['id'] ];
			if ( $best ) {
				$rank             = array( 'delivered' => 3, 'read' => 4, 'played' => 5 );
				$lvl              = $rank[ $best['status'] ] ?? 0;
				$o['delivered']   = $lvl >= 3;
				$o['read']        = $lvl >= 4;
				$meta             = json_decode( (string) $best['meta'], true );
				$o['delivered_at'] = $o['delivered'] && ! empty( $meta['delivered_at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $meta['delivered_at'] ) : null;
				$o['read_at']      = $o['read'] && ! empty( $meta['read_at'] ) ? gmdate( 'Y-m-d H:i:s', (int) $meta['read_at'] ) : null;
			} else {
				$o['delivered'] = $o['read'] = null; // Flux Chat'te karşılığı yok: bilinmiyor.
			}
			foreach ( $ms as $m ) {
				if ( 'in' !== $m['direction'] || (int) $m['ts'] <= $t || (int) $m['ts'] > $t + $win * 1000 ) {
					continue;
				}
				// Kişi yazdıysa (IPTAL dahil) mesaj ulaşmış ve görülmüştür.
				$o['delivered'] = true;
				$o['read']      = true;
				if ( '' === flxi_optout_match( (string) $m['body'] ) ) {
					$o['replied']    = true;
					$o['replied_at'] = gmdate( 'Y-m-d H:i:s', (int) floor( (int) $m['ts'] / 1000 ) );
					break;
				}
			}
			unset( $o );
		}
	}
	return $out;
}

/**
 * Kampanya özeti: kaç kişiye ulaştı, okudu, cevap verdi, IPTAL yazdı.
 *
 * @return array|null sent, delivered, read, replied, optout (bilinmeyen: null), channel, window_hours
 */
function flxi_campaign_engagement( $id ) {
	global $wpdb;
	$c = flxi_get_campaign( $id );
	if ( ! $c ) {
		return null;
	}
	$cache = get_transient( 'flxi_eng_' . (int) $id );
	if ( is_array( $cache ) ) {
		return $cache;
	}
	$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxi_table( 'queue' ) . " WHERE campaign_id = %d AND status IN ('sent','delivered','submitted')", (int) $id ), ARRAY_A ); // phpcs:ignore
	$e    = flxi_engagement_rows( $c, $rows );
	$sum  = array( 'delivered' => 0, 'read' => 0, 'replied' => 0, 'optout' => 0 );
	$known = array( 'delivered' => false, 'read' => false, 'replied' => false, 'optout' => false );
	foreach ( $e as $o ) {
		foreach ( $sum as $k => $v ) {
			if ( null !== $o[ $k ] ) {
				$known[ $k ] = true;
				$sum[ $k ]  += $o[ $k ] ? 1 : 0;
			}
		}
	}
	$res = array(
		'channel'      => $c['channel'],
		'sent'         => (int) $c['sent'],
		'delivered'    => $known['delivered'] ? $sum['delivered'] : null,
		'read'         => $known['read'] ? $sum['read'] : null,
		'replied'      => $known['replied'] ? $sum['replied'] : null,
		'optout'       => $known['optout'] ? $sum['optout'] : null,
		'window_hours' => (int) round( flxi_engagement_window() / HOUR_IN_SECONDS ),
	);
	if ( 'email' === $c['channel'] ) {
		$res['read'] = null;
	}
	$res = (array) apply_filters( 'flxi_campaign_engagement', $res, $c );
	set_transient( 'flxi_eng_' . (int) $id, $res, 2 * MINUTE_IN_SECONDS );
	return $res;
}

/** Gönder ekranı: "40 ulaştı · 31 okudu · 6 cevap · 1 IPTAL" */
function flxi_campaign_engagement_html( $id ) {
	$e = flxi_campaign_engagement( $id );
	if ( ! $e || ! $e['sent'] ) {
		return '';
	}
	$parts = array();
	foreach ( array(
		/* translators: %s: sayı */
		'delivered' => __( '%s reached', 'flx-mail' ),
		/* translators: %s: sayı */
		'read'      => __( '%s read', 'flx-mail' ),
		/* translators: %s: sayı */
		'replied'   => __( '%s replied', 'flx-mail' ),
		/* translators: %s: sayı */
		'optout'    => __( '%s opted out', 'flx-mail' ),
	) as $k => $l ) {
		if ( null !== $e[ $k ] ) {
			$parts[] = '<span class="flxi-eng flxi-eng--' . esc_attr( $k ) . '">' . esc_html( sprintf( $l, number_format_i18n( $e[ $k ] ) ) ) . '</span>';
		}
	}
	return $parts ? '<div class="flxi-muted flxi-eng-row">' . implode( ' · ', $parts ) . '</div>' : '';
}

add_filter(
	'flxi_campaign_features',
	function ( $f ) {
		$f[] = 'engagement';
		return array_values( array_unique( (array) $f ) );
	},
	21
);
