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
 * Gece cron ile ve "Yeniden hesapla" düğmesiyle çalışır. Bir çalışma yaklaşık 20 saniye sürer
 * ve `flxcrm_score_cursor` içindeki kişi kimliğinden devam eder. Segment değişince
 * `flxcrm_segment_changed` eylemi ve zaman tüneline olay (ilk hesaplamada olay yazılmaz).
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Scoring {

	public static function init() {
		add_action( 'flxcrm_daily', array( __CLASS__, 'run' ) );
		add_action( 'flxcrm_score_resume', array( __CLASS__, 'run' ) );
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
	 * Skorları hesaplar. Beşte bölme bütün ödeyen kişilere bakmayı gerektirir; yazma
	 * 100'lik CASE grupları halindedir ve yaklaşık 20 saniyede durur.
	 *
	 * @return array{scored:int,changed:int,done:bool,total:int}
	 */
	public static function run() {
		global $wpdb;
		$t        = flxcrm_table( 'contacts' );
		$deadline = microtime( true ) + 20;
		$state    = get_option( 'flxcrm_score_cursor', array() );
		$cursor   = 0;
		$now      = time();
		$carry    = 0;
		// Bir günden eski imleç bırakılır: yarım kalan tur eski "şimdi" ile sürmesin.
		if ( is_array( $state ) && ! empty( $state['now'] ) && time() - (int) $state['now'] < DAY_IN_SECONDS ) {
			$cursor = (int) ( $state['id'] ?? 0 );
			$now    = (int) $state['now'];
			$carry  = (int) ( $state['changed'] ?? 0 );
		}

		$data  = array();
		$after = 0;
		while ( true ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT id, paid_days, ltv, last_paid_at, avg_gap_days, segment, scored_at, r_score, f_score, m_score, churn FROM $t WHERE paid_days > 0 AND id > %d ORDER BY id ASC LIMIT 500", $after ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);
			if ( ! $rows ) {
				break;
			}
			foreach ( $rows as $r ) {
				$last = $r['last_paid_at'] ? strtotime( $r['last_paid_at'] . ' UTC' ) : $now;
				$id   = (int) $r['id'];
				$data[ $id ] = array(
					'rec'     => max( 0, (int) floor( ( $now - $last ) / DAY_IN_SECONDS ) ),
					'f'       => (int) $r['paid_days'],
					'm'       => (float) $r['ltv'],
					'gap'     => (int) $r['avg_gap_days'],
					'seg'     => (string) $r['segment'],
					'first'   => empty( $r['scored_at'] ),
					'r_score' => (int) $r['r_score'],
					'f_score' => (int) $r['f_score'],
					'm_score' => (int) $r['m_score'],
					'churn'   => (int) $r['churn'],
				);
				$after = $id;
			}
			unset( $rows );
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
		$walked  = 0;
		$stamp   = flxcrm_now();
		$pending = array();
		$last    = $cursor;
		$stop    = false;
		$allow   = true;
		sort( $ids, SORT_NUMERIC );
		FLXCRM_Events::$defer_recalc = true;
		foreach ( $ids as $id ) {
			if ( $id <= $cursor ) {
				continue;
			}
			if ( ! $allow && microtime( true ) >= $deadline ) {
				$stop = true;
				break;
			}
			$allow = false;
			$d     = $data[ $id ];
			/* Tek ödeme yapan herkes en alt F diliminde değil: ham gün sayısı 1 ise F en fazla 1. */
			$f     = 1 === $d['f'] ? 1 : $fs[ $id ];
			$seg   = self::segment_for( $rs[ $id ], $f, $d['f'] );
			$churn = self::churn( $d['rec'], $d['gap'] );
			$last  = $id;
			++$walked;
			if ( $d['r_score'] === (int) $rs[ $id ] && $d['f_score'] === (int) $f && $d['m_score'] === (int) $ms[ $id ] && $d['seg'] === $seg && $d['churn'] === (int) $churn ) {
				continue;
			}
			$pending[] = array(
				'id'    => $id,
				'r'     => (int) $rs[ $id ],
				'f'     => (int) $f,
				'm'     => (int) $ms[ $id ],
				'seg'   => $seg,
				'churn' => (int) $churn,
				'from'  => $d['seg'],
				'first' => $d['first'],
			);
			if ( count( $pending ) >= 100 ) {
				$changed += self::write_score_batch( $t, $pending, $stamp );
				$pending  = array();
			}
		}
		if ( $pending ) {
			$changed += self::write_score_batch( $t, $pending, $stamp );
		}
		FLXCRM_Events::$defer_recalc = false;

		$changed += $carry;
		if ( $stop ) {
			$cursor_row = array(
				'id'      => $last,
				'now'     => $now,
				'changed' => $changed,
			);
			if ( false === get_option( 'flxcrm_score_cursor', false ) ) {
				add_option( 'flxcrm_score_cursor', $cursor_row, '', false );
			} else {
				update_option( 'flxcrm_score_cursor', $cursor_row, false );
			}
			// Cron'da yarım kaldıysa ertesi günü beklemeden sürdür.
			if ( wp_doing_cron() && ! wp_next_scheduled( 'flxcrm_score_resume' ) ) {
				wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'flxcrm_score_resume' );
			}
			return array(
				'scored'  => $walked,
				'changed' => $changed,
				'done'    => false,
				'total'   => count( $data ),
			);
		}

		delete_option( 'flxcrm_score_cursor' );
		/* Ödemesi kalmayan (iade) kişilerin skoru sıfırlanır. */
		$wpdb->query( "UPDATE $t SET r_score = 0, f_score = 0, m_score = 0, segment = '', churn = 0 WHERE paid_days = 0 AND segment <> ''" ); // phpcs:ignore WordPress.DB
		update_option( 'flxcrm_scored_at', time(), false );
		FLXCRM_Contacts::flush_stats();
		FLXCRM_Segments::recount_all();
		return array(
			'scored'  => count( $data ),
			'changed' => $changed,
			'done'    => true,
			'total'   => count( $data ),
		);
	}

	/**
	 * Değişen skorları tek UPDATE ile yazar ve segment olayını yalnızca değişende üretir.
	 *
	 * @param array<int,array<string,mixed>> $rows
	 */
	private static function write_score_batch( $table, array $rows, $stamp ) {
		global $wpdb;
		if ( ! $rows ) {
			return 0;
		}
		$allowed = array( 'new', 'champion', 'loyal', 'promising', 'at_risk', 'lost', 'hibernating' );
		$r_sql   = array();
		$f_sql   = array();
		$m_sql   = array();
		$s_sql   = array();
		$c_sql   = array();
		$ids     = array();
		foreach ( $rows as $row ) {
			$id    = (int) $row['id'];
			$ids[] = $id;
			$r_sql[] = 'WHEN ' . $id . ' THEN ' . (int) $row['r'];
			$f_sql[] = 'WHEN ' . $id . ' THEN ' . (int) $row['f'];
			$m_sql[] = 'WHEN ' . $id . ' THEN ' . (int) $row['m'];
			$name    = in_array( $row['seg'], $allowed, true ) ? $row['seg'] : '';
			$s_sql[] = 'WHEN ' . $id . " THEN '" . esc_sql( $name ) . "'";
			$c_sql[] = 'WHEN ' . $id . ' THEN ' . (int) $row['churn'];
		}
		$sql = "UPDATE $table SET r_score = CASE id " . implode( ' ', $r_sql )
			. ' END, f_score = CASE id ' . implode( ' ', $f_sql )
			. ' END, m_score = CASE id ' . implode( ' ', $m_sql )
			. ' END, segment = CASE id ' . implode( ' ', $s_sql )
			. ' END, churn = CASE id ' . implode( ' ', $c_sql )
			. ' END, scored_at = %s WHERE id IN (' . implode( ',', $ids ) . ')';
		$wpdb->query( $wpdb->prepare( $sql, $stamp ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$changed = 0;
		foreach ( $rows as $row ) {
			if ( $row['seg'] === $row['from'] ) {
				continue;
			}
			++$changed;
			$id = (int) $row['id'];
			FLXCRM_Contacts::forget( $id );
			if ( ! $row['first'] && '' !== $row['from'] ) {
				FLXCRM_Events::add(
					$id,
					'segment_entered',
					array(
						'title' => flxcrm_segment_label( $row['from'] ) . ' → ' . flxcrm_segment_label( $row['seg'] ),
						'data'  => array( 'from' => $row['from'], 'to' => $row['seg'] ),
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
			do_action( 'flxcrm_segment_changed', $id, $row['seg'], $row['from'] );
		}
		return $changed;
	}
}
