<?php
/**
 * RFM skoru, segment ve kayıp (churn) riski.
 *
 * - Recency: son ödemeden bu yana gün. Frequency: ödeme yapılan farklı gün sayısı (site saat dilimi).
 *   Monetary: raporlama para birimindeki toplam değer.
 * - Skorlar ödeme yapmış kişiler arasında beşe bölünür (1 = en düşük, 5 = en iyi); eşitlikte kişi kimliği sırayı sabitler.
 * - Segment sırası önemli: "yeni" (tek ödeme günü ve R ≥ 4) şampiyon/sadıktan önce değerlendirilir.
 * - Kayıp riski kişinin kendi ritmine göre: min(100, 100 × geçen gün / max(ortalama aralık × 1,5; 30)).
 *   Tek ödemesi olanın aralığı 365 gün sayılır.
 *
 * Gece cron ile ve "Yeniden hesapla" düğmesiyle çalışır. Segment değişince `flxcrm_segment_changed` eylemi
 * ve zaman tüneline olay (ilk hesaplamada olay yazılmaz).
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Scoring {

	public static function init() {
		add_action( 'flxcrm_daily', array( __CLASS__, 'run' ) );
	}

	/** Kişinin kendi ritmine göre kayıp riski (0–100). */
	public static function churn( $recency_days, $avg_gap_days ) {
		$gap = $avg_gap_days > 0 ? (float) $avg_gap_days : 365.0;
		$den = max( $gap * 1.5, 30.0 );
		return (int) min( 100, max( 0, round( 100 * $recency_days / $den ) ) );
	}

	/** Beşe bölme: sıralı listede konuma göre 1..5. */
	private static function ntile( array $sorted_ids ) {
		$n   = count( $sorted_ids );
		$out = array();
		foreach ( array_values( $sorted_ids ) as $i => $id ) {
			$out[ $id ] = (int) floor( $i * 5 / max( 1, $n ) ) + 1;
		}
		return $out;
	}

	public static function segment_for( $r, $f, $paid_days ) {
		if ( 1 === (int) $paid_days && $r >= 4 ) {
			return 'new';
		}
		if ( $r >= 4 && $f >= 4 ) {
			return 'champion';
		}
		if ( $r >= 3 && $f >= 3 ) {
			return 'loyal';
		}
		if ( $r >= 3 ) {
			return 'promising';
		}
		if ( $f >= 3 ) {
			return 'at_risk';
		}
		if ( 1 === (int) $r ) {
			return 'lost';
		}
		return 'hibernating';
	}

	/**
	 * Tüm kişileri skorlar.
	 *
	 * @return array scored, changed
	 */
	public static function run() {
		global $wpdb;
		$t    = flxcrm_table( 'contacts' );
		$rows = $wpdb->get_results( "SELECT id, paid_days, ltv, last_paid_at, avg_gap_days, segment, scored_at FROM $t WHERE paid_days > 0", ARRAY_A ); // phpcs:ignore WordPress.DB
		$now  = time();
		$data = array();
		foreach ( (array) $rows as $r ) {
			$last = $r['last_paid_at'] ? strtotime( $r['last_paid_at'] . ' UTC' ) : $now;
			$data[ (int) $r['id'] ] = array(
				'rec'   => max( 0, (int) floor( ( $now - $last ) / DAY_IN_SECONDS ) ),
				'f'     => (int) $r['paid_days'],
				'm'     => (float) $r['ltv'],
				'gap'   => (int) $r['avg_gap_days'],
				'seg'   => (string) $r['segment'],
				'first' => empty( $r['scored_at'] ),
			);
		}
		/* R: az gün = iyi → büyükten küçüğe sırala. F ve M: küçükten büyüğe. Eşitlikte id. */
		$ids = array_keys( $data );
		$by  = static function ( $key, $desc ) use ( $data, $ids ) {
			$list = $ids;
			usort(
				$list,
				static function ( $a, $b ) use ( $data, $key, $desc ) {
					$x = $data[ $a ][ $key ];
					$y = $data[ $b ][ $key ];
					if ( $x == $y ) { // phpcs:ignore Universal.Operators.StrictComparisons
						return $a <=> $b;
					}
					return $desc ? ( $y <=> $x ) : ( $x <=> $y );
				}
			);
			return $list;
		};
		$rs = self::ntile( $by( 'rec', true ) );
		$fs = self::ntile( $by( 'f', false ) );
		$ms = self::ntile( $by( 'm', false ) );

		$changed = 0;
		$stamp   = flxcrm_now();
		FLXCRM_Events::$defer_recalc = true;
		foreach ( $data as $id => $d ) {
			/* Tek ödeme yapan herkes en alt F diliminde değil: ham gün sayısı 1 ise F en fazla 1. */
			$f   = 1 === $d['f'] ? 1 : $fs[ $id ];
			$seg = self::segment_for( $rs[ $id ], $f, $d['f'] );
			$wpdb->update( // phpcs:ignore WordPress.DB
				$t,
				array(
					'r_score'   => $rs[ $id ],
					'f_score'   => $f,
					'm_score'   => $ms[ $id ],
					'segment'   => $seg,
					'churn'     => self::churn( $d['rec'], $d['gap'] ),
					'scored_at' => $stamp,
				),
				array( 'id' => $id )
			);
			if ( $seg !== $d['seg'] ) {
				++$changed;
				FLXCRM_Contacts::forget( $id );
				if ( ! $d['first'] && '' !== $d['seg'] ) {
					FLXCRM_Events::add(
						$id,
						'segment_entered',
						array(
							'title' => flxcrm_segment_label( $d['seg'] ) . ' → ' . flxcrm_segment_label( $seg ),
							'data'  => array( 'from' => $d['seg'], 'to' => $seg ),
						)
					);
				}
				/**
				 * Kişi yeni bir RFM segmentine girdi.
				 *
				 * @param int    $id   Kişi.
				 * @param string $seg  Yeni segment.
				 * @param string $from Eski segment ('' = ilk hesaplama).
				 */
				do_action( 'flxcrm_segment_changed', $id, $seg, $d['seg'] );
			}
		}
		FLXCRM_Events::$defer_recalc = false;
		/* Ödemesi kalmayan (iade) kişilerin skoru sıfırlanır. */
		$wpdb->query( "UPDATE $t SET r_score = 0, f_score = 0, m_score = 0, segment = '', churn = 0 WHERE paid_days = 0 AND segment <> ''" ); // phpcs:ignore WordPress.DB
		update_option( 'flxcrm_scored_at', time(), false );
		FLXCRM_Contacts::flush_stats();
		FLXCRM_Segments::recount_all();
		return array( 'scored' => count( $data ), 'changed' => $changed );
	}
}
