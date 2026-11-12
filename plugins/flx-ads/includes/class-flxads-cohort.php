<?php
/**
 * Flux Ads: bağışçı değeri (LTV) ve kohortlar. Kişinin ilk ödemesi onu getiren kanal/kampanyaya yazılır
 * (edinme); sonraki tüm ödemeleri (reklamlı ya da reklamsız) o kampanyanın değerine eklenir. Böylece
 * "ilk bağışı ucuz ama tekrar etmeyen" kampanya ile "pahalı ama sadık bağışçı getiren" kampanya ayrılır.
 *
 * CAC = dönemdeki kampanya harcaması / o kampanyayla edinilen yeni kişi. LTV:CAC = ortalama değer / CAC.
 * Geri dönüş süresi = kohortun toplam ödemesinin harcamayı geçtiği gün.
 *
 * Yalnız bir kişiye bağlanmış ödemeler sayılır (e-posta ya da telefonla bulunan ortak kişi kaydı).
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

final class FLXADS_Cohort {

	const MONTHS = 12;

	/** Site saatinde Y-m-d → UTC aralığı. */
	private static function range( $from, $to ) {
		$tz = wp_timezone();
		$gf = ( new DateTime( $from . ' 00:00:00', $tz ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$gt = ( new DateTime( $to . ' 23:59:59', $tz ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		return array( $gf, $gt );
	}

	/**
	 * Edinilen kişiler ve tüm ödemeleri.
	 *
	 * @return array person_id => [ first(ts), ch, campaign, month(Y-m site saati), pays[ [offset_days, amount] ] ]
	 */
	private static function people( $from, $to, array $f ) {
		global $wpdb;
		$t = flxads_table( 'orders' );
		list( $gf, $gt ) = self::range( $from, $to );
		/* İlk ödemesi dönemde olan kişiler (daha önce ödemesi olan kişi bu dönemde edinilmiş sayılmaz). */
		$firsts = (array) $wpdb->get_results( $wpdb->prepare( "SELECT person_id, m FROM (SELECT person_id, MIN(paid_at) AS m FROM $t WHERE status = 'paid' AND person_id > 0 GROUP BY person_id) f WHERE m BETWEEN %s AND %s", $gf, $gt ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( ! $firsts ) {
			return array();
		}
		$ids = array_map( 'intval', wp_list_pluck( $firsts, 'person_id' ) );
		$out = array();
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$in   = implode( ',', $chunk );
			$rows = (array) $wpdb->get_results( "SELECT order_id, person_id, paid_at, total, currency, channel, campaign, is_donation FROM $t WHERE status = 'paid' AND person_id IN ($in) ORDER BY paid_at, order_id", ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( $rows as $r ) {
				$pid = (int) $r['person_id'];
				$ts  = strtotime( $r['paid_at'] . ' UTC' );
				if ( ! isset( $out[ $pid ] ) ) {
					$kind = (string) ( $f['kind'] ?? 'all' );
					if ( ( 'donations' === $kind && ! (int) $r['is_donation'] ) || ( 'orders' === $kind && (int) $r['is_donation'] ) ) {
						$out[ $pid ] = false; // ilk ödemesi bu türden değil: kohorta girmez
						continue;
					}
					$out[ $pid ] = array(
						'first'    => $ts,
						'ch'       => (string) $r['channel'],
						'campaign' => (string) $r['campaign'],
						'month'    => wp_date( 'Y-m', $ts ),
						'pays'     => array(),
					);
				}
				if ( false === $out[ $pid ] ) {
					continue;
				}
				$amt = FLXADS_Report::to_base( (float) $r['total'], (string) $r['currency'], (string) $r['paid_at'] );
				$out[ $pid ]['pays'][] = array( (int) floor( ( $ts - $out[ $pid ]['first'] ) / DAY_IN_SECONDS ), null === $amt ? 0.0 : $amt );
			}
		}
		$out = array_filter( $out );
		/* Kanal / kampanya süzgeci edinme kaynağına uygulanır. */
		if ( isset( $f['channel'] ) && '' !== (string) $f['channel'] && '*' !== $f['channel'] ) {
			$want = '-' === $f['channel'] ? '' : (string) $f['channel'];
			$out  = array_filter(
				$out,
				static function ( $p ) use ( $want ) {
					return $p['ch'] === $want;
				}
			);
		}
		if ( isset( $f['campaign'] ) && '' !== (string) $f['campaign'] ) {
			$want = FLXADS_Spend::norm( (string) $f['campaign'] );
			$out  = array_filter(
				$out,
				static function ( $p ) use ( $want ) {
					return FLXADS_Spend::norm( $p['campaign'] ) === $want;
				}
			);
		}
		return $out;
	}

	/**
	 * Kampanya (ya da kanal) başına edinme değeri.
	 *
	 * @param string $by campaign|channel
	 * @return array [ from, to, base, totals{…}, rows[ {key,label,channel,new,first,ltv,ltv30,ltv90,ltv180,repeat,avg,spend,cac,ratio,payback} ] ]
	 */
	public static function value( $by = 'campaign', array $f = array() ) {
		$by   = 'channel' === $by ? 'channel' : 'campaign';
		$from = ! empty( $f['from'] ) ? (string) $f['from'] : wp_date( 'Y-m-d', time() - 89 * DAY_IN_SECONDS );
		$to   = ! empty( $f['to'] ) ? (string) $f['to'] : wp_date( 'Y-m-d' );
		$ppl  = self::people( $from, $to, $f );
		$rows = array();
		$tot  = array( 'new' => 0, 'first' => 0.0, 'ltv' => 0.0, 'repeat' => 0, 'ads_new' => 0, 'ads_ltv' => 0.0, 'spend' => 0.0 );
		foreach ( $ppl as $p ) {
			$k = 'channel' === $by ? $p['ch'] : ( '' === $p['ch'] ? '' : FLXADS_Spend::norm( $p['campaign'] ) );
			$k = 'campaign' === $by && '' !== $p['ch'] && '' === $k ? '~' . $p['ch'] : $k; // reklamlı ama kampanyasız
			if ( ! isset( $rows[ $k ] ) ) {
				$rows[ $k ] = array(
					'key'     => 'channel' === $by ? $p['ch'] : $p['campaign'],
					'channel' => $p['ch'],
					'new'     => 0,
					'first'   => 0.0,
					'ltv'     => 0.0,
					'ltv30'   => 0.0,
					'ltv90'   => 0.0,
					'ltv180'  => 0.0,
					'repeat'  => 0,
					'pays'    => array(),
				);
			}
			$r = &$rows[ $k ];
			++$r['new'];
			$r['first'] += $p['pays'][0][1];
			foreach ( $p['pays'] as $pay ) {
				$r['ltv'] += $pay[1];
				foreach ( array( 30, 90, 180 ) as $d ) {
					if ( $pay[0] < $d ) {
						$r[ 'ltv' . $d ] += $pay[1];
					}
				}
				$r['pays'][] = $pay;
			}
			if ( count( $p['pays'] ) > 1 ) {
				++$r['repeat'];
			}
			unset( $r );
			++$tot['new'];
			$tot['first'] += $p['pays'][0][1];
			$tot['ltv']   += array_sum( array_column( $p['pays'], 1 ) );
			$tot['repeat'] += count( $p['pays'] ) > 1 ? 1 : 0;
			if ( '' !== $p['ch'] ) {
				++$tot['ads_new'];
				$tot['ads_ltv'] += array_sum( array_column( $p['pays'], 1 ) );
			}
		}

		/* Harcama: dönemdeki kampanya / kanal harcaması. Kampanya kimliğiyle gelen utm_campaign de eşleşir. */
		$sp  = FLXADS_Spend::totals( $from, $to );
		$map = 'channel' === $by ? $sp['by_channel'] : array_map(
			static function ( $c ) {
				return $c[0];
			},
			$sp['by_campaign']
		);
		$ids = array();
		if ( 'campaign' === $by ) {
			foreach ( $sp['by_campaign'] as $nk => $c ) {
				foreach ( array_keys( $c[3] ) as $cid ) {
					$ids[ FLXADS_Spend::norm( (string) $cid ) ] = $nk;
				}
			}
		}
		$used = array();
		foreach ( $rows as $k => &$r ) {
			$sk = isset( $map[ $k ] ) ? $k : ( $ids[ $k ] ?? null );
			if ( '' !== (string) $k && null !== $sk && isset( $map[ $sk ] ) && ! isset( $used[ $sk ] ) ) {
				$used[ $sk ]  = true;
				$r['spend']   = (float) $map[ $sk ];
				$tot['spend'] += $r['spend'];
			}
		}
		unset( $r );
		foreach ( $map as $sk => $amt ) {
			if ( ! isset( $used[ $sk ] ) && $amt > 0 ) {
				$rows[ 'spend:' . $sk ] = array(
					'key'     => 'channel' === $by ? (string) $sk : (string) $sp['by_campaign'][ $sk ][2],
					'channel' => 'channel' === $by ? (string) $sk : (string) $sp['by_campaign'][ $sk ][1],
					'new'     => 0,
					'first'   => 0.0,
					'ltv'     => 0.0,
					'ltv30'   => 0.0,
					'ltv90'   => 0.0,
					'ltv180'  => 0.0,
					'repeat'  => 0,
					'pays'    => array(),
					'spend'   => (float) $amt,
				);
				$tot['spend'] += (float) $amt;
			}
		}

		foreach ( $rows as &$r ) {
			$r['label']   = 'channel' === $by ? flxads_channel_label( $r['key'] ) : ( '' === $r['channel'] ? __( 'Not from ads', 'flx-ads' ) : ( '' === (string) $r['key'] ? __( '(not set)', 'flx-ads' ) : (string) $r['key'] ) );
			$r['avg']     = $r['new'] ? $r['ltv'] / $r['new'] : null;
			$r['rate']    = $r['new'] ? round( 100 * $r['repeat'] / $r['new'], 1 ) : null;
			$r['spend']   = $r['spend'] ?? null;
			$r['cac']     = null !== $r['spend'] && $r['new'] ? $r['spend'] / $r['new'] : null;
			$r['ratio']   = null !== $r['cac'] && $r['cac'] > 0 && null !== $r['avg'] ? $r['avg'] / $r['cac'] : null;
			$r['payback'] = null;
			if ( null !== $r['spend'] && $r['spend'] > 0 && $r['pays'] ) {
				usort(
					$r['pays'],
					static function ( $a, $b ) {
						return $a[0] <=> $b[0];
					}
				);
				$cum = 0.0;
				foreach ( $r['pays'] as $pay ) {
					$cum += $pay[1];
					if ( $cum >= $r['spend'] ) {
						$r['payback'] = $pay[0];
						break;
					}
				}
			}
			unset( $r['pays'] );
		}
		unset( $r );
		uasort(
			$rows,
			static function ( $a, $b ) {
				return $b['ltv'] <=> $a['ltv'] ?: ( $b['spend'] ?? 0 ) <=> ( $a['spend'] ?? 0 );
			}
		);
		$tot['avg']     = $tot['new'] ? $tot['ltv'] / $tot['new'] : null;
		$tot['rate']    = $tot['new'] ? round( 100 * $tot['repeat'] / $tot['new'], 1 ) : null;
		$tot['ads_avg'] = $tot['ads_new'] ? $tot['ads_ltv'] / $tot['ads_new'] : null;
		$tot['cac']     = $tot['spend'] > 0 && $tot['ads_new'] ? $tot['spend'] / $tot['ads_new'] : null;
		$tot['ratio']   = $tot['cac'] && null !== $tot['ads_avg'] ? $tot['ads_avg'] / $tot['cac'] : null;
		return array(
			'from'   => $from,
			'to'     => $to,
			'base'   => FLXADS_Report::base_currency(),
			'totals' => $tot,
			'rows'   => array_values( $rows ),
		);
	}

	/**
	 * Aylık kohort tablosu: edinme ayı × geçen ay, kişi başı birikimli değer.
	 *
	 * @return array [ months: N, rows[ {month, label, new, cells[ k => per person cumulative | null ]} ] ]
	 */
	public static function matrix( array $f = array(), $months = self::MONTHS ) {
		$months = max( 3, min( 24, (int) $months ) );
		$to     = ! empty( $f['to'] ) ? (string) $f['to'] : wp_date( 'Y-m-d' );
		$from   = ! empty( $f['from'] ) ? (string) $f['from'] : wp_date( 'Y-m-01', strtotime( '-' . ( $months - 1 ) . ' months', time() ) );
		$ppl    = self::people( $from, $to, $f );
		$now    = wp_date( 'Y-m' );
		$rows   = array();
		foreach ( $ppl as $p ) {
			$m = $p['month'];
			if ( ! isset( $rows[ $m ] ) ) {
				$rows[ $m ] = array( 'month' => $m, 'new' => 0, 'sum' => array_fill( 0, $months, 0.0 ) );
			}
			++$rows[ $m ]['new'];
			foreach ( $p['pays'] as $pay ) {
				$k = (int) floor( $pay[0] / 30 );
				for ( $i = max( 0, $k ); $i < $months; $i++ ) {
					$rows[ $m ]['sum'][ $i ] += $pay[1];
				}
			}
		}
		ksort( $rows );
		$out = array();
		foreach ( $rows as $m => $r ) {
			$age   = ( (int) substr( $now, 0, 4 ) - (int) substr( $m, 0, 4 ) ) * 12 + ( (int) substr( $now, 5, 2 ) - (int) substr( $m, 5, 2 ) );
			$cells = array();
			for ( $i = 0; $i < $months; $i++ ) {
				$cells[ $i ] = $i <= $age ? round( $r['sum'][ $i ] / max( 1, $r['new'] ), 2 ) : null;
			}
			$out[] = array(
				'month' => $m,
				'label' => flx_date( strtotime( $m . '-15 12:00:00 UTC' ), 'M Y' ),
				'new'   => $r['new'],
				'cells' => $cells,
			);
		}
		return array( 'months' => $months, 'from' => $from, 'to' => $to, 'rows' => $out );
	}
}
