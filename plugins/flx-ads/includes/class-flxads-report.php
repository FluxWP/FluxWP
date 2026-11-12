<?php
/**
 * Flux Ads: rapor. Ödenen bağış/siparişler reklam kanalına, kampanyaya, reklama (utm_content), kaynağa ve
 * açılış sayfasına göre gruplanır: adet, tutar, yeni bağışçı. Tutar sitenin ana para birimine çevrilir
 * (Flux CRM'in kur çevirisi varsa o; yoksa her para birimi ayrı gösterilir).
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

final class FLXADS_Report {

	/** Gruplama boyutları. */
	public static function dimensions() {
		return array(
			'channel'  => __( 'Ad channel', 'flx-ads' ),
			'campaign' => __( 'Campaign', 'flx-ads' ),
			'content'  => __( 'Ad (utm_content)', 'flx-ads' ),
			'source'   => __( 'Source / medium', 'flx-ads' ),
			'landing'  => __( 'Landing page', 'flx-ads' ),
			'first'    => __( 'First ad touch', 'flx-ads' ),
		);
	}

	public static function base_currency() {
		return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'TRY';
	}

	/** Tutar ana para birimine (çevrilemezse null). */
	public static function to_base( $amount, $cur, $date = '' ) {
		$base = self::base_currency();
		if ( strtoupper( (string) $cur ) === $base ) {
			return (float) $amount;
		}
		if ( function_exists( 'flxcrm_to_base' ) ) {
			return (float) flxcrm_to_base( (float) $amount, $cur, $date );
		}
		return null;
	}

	/**
	 * Filtre: from, to (Y-m-d, site saati), kind (donations|orders|all), channel, campaign.
	 *
	 * @return array where SQL + değerler
	 */
	private static function where( array $f ) {
		global $wpdb;
		$tz   = wp_timezone();
		$from = ! empty( $f['from'] ) ? (string) $f['from'] : wp_date( 'Y-m-d', time() - 29 * DAY_IN_SECONDS );
		$to   = ! empty( $f['to'] ) ? (string) $f['to'] : wp_date( 'Y-m-d' );
		$gf   = ( new DateTime( $from . ' 00:00:00', $tz ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$gt   = ( new DateTime( $to . ' 23:59:59', $tz ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$w    = $wpdb->prepare( "status = 'paid' AND paid_at BETWEEN %s AND %s", $gf, $gt );
		$kind = (string) ( $f['kind'] ?? 'all' );
		if ( 'donations' === $kind ) {
			$w .= ' AND is_donation = 1';
		} elseif ( 'orders' === $kind ) {
			$w .= ' AND is_donation = 0';
		}
		if ( isset( $f['channel'] ) && '' !== (string) $f['channel'] && '*' !== $f['channel'] ) {
			$w .= $wpdb->prepare( ' AND channel = %s', '-' === $f['channel'] ? '' : (string) $f['channel'] );
		}
		if ( ! empty( $f['ads_only'] ) ) {
			$w .= " AND channel <> ''";
		}
		if ( isset( $f['campaign'] ) && '' !== (string) $f['campaign'] ) {
			$w .= $wpdb->prepare( ' AND campaign = %s', (string) $f['campaign'] );
		}
		return array( $w, $from, $to );
	}

	/**
	 * Gruplu rapor.
	 *
	 * @return array [ from, to, base, totals{count,amount,new,ads_count,ads_amount,mixed}, rows[ {key,label,channel,count,amount,new,share,amounts{cur:amount}} ] ]
	 */
	public static function grouped( $dim = 'channel', array $f = array(), $limit = 100 ) {
		global $wpdb;
		$dim                  = isset( self::dimensions()[ $dim ] ) ? $dim : 'channel';
		list( $w, $from, $to ) = self::where( $f );
		$col                  = array(
			'channel'  => 'channel',
			'campaign' => 'campaign',
			'content'  => 'content',
			'source'   => 'source',
			'landing'  => 'landing',
			'first'    => 'ft_channel',
		)[ $dim ];
		/* Kaynak / ortam: iki sütun ayrı gruplanır, anahtar PHP'de birleşir (SQL taşınabilir kalsın). */
		$col2 = 'source' === $dim ? 'medium' : "''";
		$grp  = 'source' === $dim ? 'source, medium' : $col;
		$t    = flxads_table( 'orders' );
		$rows = $wpdb->get_results( "SELECT $col AS k, $col2 AS k2, MAX(channel) AS ch, currency, COUNT(*) AS n, SUM(total) AS amt, SUM(new_person) AS nw, MIN(paid_at) AS d FROM $t WHERE $w GROUP BY $grp, currency", ARRAY_A ); // phpcs:ignore WordPress.DB
		$base = self::base_currency();
		$out  = array();
		$tot  = array( 'count' => 0, 'amount' => 0.0, 'new' => 0, 'ads_count' => 0, 'ads_amount' => 0.0, 'mixed' => false );
		foreach ( (array) $rows as $r ) {
			$k = (string) $r['k'];
			if ( 'source' === $dim ) {
				$k = trim( $k . ' / ' . (string) $r['k2'], ' /' );
			}
			if ( ! isset( $out[ $k ] ) ) {
				$out[ $k ] = array( 'key' => $k, 'channel' => (string) $r['ch'], 'count' => 0, 'amount' => 0.0, 'new' => 0, 'amounts' => array() );
			}
			$conv = self::to_base( (float) $r['amt'], (string) $r['currency'], (string) $r['d'] );
			if ( null === $conv ) {
				$tot['mixed'] = true;
				$conv         = 0.0;
			}
			$out[ $k ]['count']                         += (int) $r['n'];
			$out[ $k ]['new']                           += (int) $r['nw'];
			$out[ $k ]['amount']                        += $conv;
			$out[ $k ]['amounts'][ $r['currency'] ]      = ( $out[ $k ]['amounts'][ $r['currency'] ] ?? 0 ) + (float) $r['amt'];
			$tot['count']                               += (int) $r['n'];
			$tot['new']                                 += (int) $r['nw'];
			$tot['amount']                              += $conv;
			$is_ad = 'channel' === $dim || 'first' === $dim ? '' !== $k : '' !== (string) $r['ch'];
			if ( $is_ad ) {
				$tot['ads_count']  += (int) $r['n'];
				$tot['ads_amount'] += $conv;
			}
		}
		foreach ( $out as &$o ) {
			$o['label'] = self::label( $dim, $o['key'] );
			$o['share'] = $tot['amount'] > 0 ? round( 100 * $o['amount'] / $tot['amount'], 1 ) : 0;
		}
		unset( $o );
		usort(
			$out,
			static function ( $a, $b ) {
				return $b['amount'] <=> $a['amount'] ?: $b['count'] <=> $a['count'];
			}
		);
		return array(
			'from'   => $from,
			'to'     => $to,
			'base'   => $base,
			'totals' => $tot,
			'rows'   => array_slice( $out, 0, (int) $limit ),
		);
	}

	public static function label( $dim, $key ) {
		if ( 'channel' === $dim || 'first' === $dim ) {
			return flxads_channel_label( $key );
		}
		if ( '' === (string) $key ) {
			return __( '(not set)', 'flx-ads' );
		}
		if ( 'landing' === $dim ) {
			$p = wp_parse_url( (string) $key, PHP_URL_PATH );
			return $p ? $p : (string) $key;
		}
		return (string) $key;
	}

	/** Son atfedilmiş siparişler. */
	public static function recent( array $f = array(), $limit = 20 ) {
		global $wpdb;
		list( $w ) = self::where( $f );
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxads_table( 'orders' ) . " WHERE $w ORDER BY paid_at DESC LIMIT %d", (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	/** Bir kişinin atfedilmiş siparişleri (CRM kişi kartı). */
	public static function for_person( $person_id, $limit = 10 ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxads_table( 'orders' ) . ' WHERE person_id = %d ORDER BY paid_at DESC LIMIT %d', (int) $person_id, (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}
}
