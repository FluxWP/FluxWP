<?php
/**
 * Flux Ads: Deneyler.
 * - A/B: aynı kampanyadaki iki reklam (çeşit) karşılaştırılır; ölçü sitedeki ödeme / tıklama (utm_content = reklam adı).
 * - Holdout: bir kitlenin sabit bir payı reklam görmez (Meta kitlesine girmez); dönem sonunda iki grubun ödeme oranı
 *   karşılaştırılır, reklamın gerçekten kazandırdığı (artırımlı) bağış hesaplanır.
 * İstatistik: iki oran z-testi, %95 güven.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

final class FLXADS_Experiments {

	const OPT = 'flxads_experiments';
	const MAX = 50;

	public static function all() {
		$a = get_option( self::OPT, array() );
		return is_array( $a ) ? array_values( $a ) : array();
	}

	public static function get( $id ) {
		foreach ( self::all() as $e ) {
			if ( $e['id'] === (string) $id ) {
				return $e;
			}
		}
		return null;
	}

	private static function put( array $e ) {
		$all = array();
		$hit = false;
		foreach ( self::all() as $x ) {
			if ( $x['id'] === $e['id'] ) {
				$x   = $e;
				$hit = true;
			}
			$all[] = $x;
		}
		if ( ! $hit ) {
			array_unshift( $all, $e );
		}
		update_option( self::OPT, array_slice( $all, 0, self::MAX ), false );
		return $e;
	}

	public static function types() {
		return array(
			'ab'      => __( 'A/B test', 'flx-ads' ),
			'holdout' => __( 'Holdout (lift)', 'flx-ads' ),
		);
	}

	/** Kişi holdout grubunda mı? Kitle + kişi kimliğine bağlı, değişmez. */
	public static function in_holdout( $aud, $pid, $pct ) {
		return $pct > 0 && ( crc32( $aud . ':' . (int) $pid ) % 100 ) < $pct;
	}

	/* ------------------------------------------------------------ Oluştur / bitir */

	/**
	 * @param array $d type ab|holdout, name, start (Y-m-d), end (Y-m-d|''), ab: campaign, a, b (Meta reklam kimlikleri); holdout: audience, pct
	 * @return array|WP_Error
	 */
	public static function create( array $d, $by = 'panel' ) {
		$type = (string) ( $d['type'] ?? '' );
		if ( ! isset( self::types()[ $type ] ) ) {
			return new WP_Error( 'flxads_exp', __( 'Choose the test type.', 'flx-ads' ) );
		}
		$start = self::day( $d['start'] ?? '' ) ?: wp_date( 'Y-m-d' );
		$end   = self::day( $d['end'] ?? '' );
		if ( '' !== $end && $end < $start ) {
			return new WP_Error( 'flxads_exp', __( 'The end date is before the start date.', 'flx-ads' ) );
		}
		$e = array(
			'id'      => strtolower( wp_generate_password( 10, false ) ),
			'type'    => $type,
			'name'    => mb_substr( trim( sanitize_text_field( (string) ( $d['name'] ?? '' ) ) ), 0, 120 ),
			'start'   => $start,
			'end'     => $end,
			'status'  => 'running',
			'created' => flxads_now(),
			'by'      => (string) $by,
			'ended'   => '',
		);
		if ( 'ab' === $type ) {
			$cid = preg_replace( '/\D+/', '', (string) ( $d['campaign'] ?? '' ) );
			$a   = preg_replace( '/\D+/', '', (string) ( $d['a'] ?? '' ) );
			$b   = preg_replace( '/\D+/', '', (string) ( $d['b'] ?? '' ) );
			if ( '' === $cid || '' === $a || '' === $b || $a === $b ) {
				return new WP_Error( 'flxads_exp', __( 'Pick a campaign and two different ads in it.', 'flx-ads' ) );
			}
			$c = FLXADS_Manager::campaign( $cid, '7', false );
			if ( is_wp_error( $c ) ) {
				return $c;
			}
			$names = array();
			foreach ( (array) $c['adsets'] as $s ) {
				foreach ( (array) $s['ads'] as $ad ) {
					$names[ $ad['id'] ] = $ad['name'];
				}
			}
			if ( ! isset( $names[ $a ], $names[ $b ] ) ) {
				return new WP_Error( 'flxads_exp', __( 'Both ads must be in the chosen campaign.', 'flx-ads' ) );
			}
			$e += array( 'campaign' => $cid, 'campaign_name' => $c['name'], 'a' => $a, 'a_name' => $names[ $a ], 'b' => $b, 'b_name' => $names[ $b ] );
			$e['name'] = '' !== $e['name'] ? $e['name'] : $names[ $a ] . ' / ' . $names[ $b ];
		} else {
			$aud = FLXADS_Audiences::get( (string) ( $d['audience'] ?? '' ) );
			$pct = (int) ( $d['pct'] ?? 10 );
			if ( ! $aud || 'custom' !== $aud['kind'] || 'target' !== $aud['purpose'] ) {
				return new WP_Error( 'flxads_exp', __( 'Pick an audience that ads are shown to.', 'flx-ads' ) );
			}
			if ( $pct < 5 || $pct > 50 ) {
				return new WP_Error( 'flxads_exp', __( 'The holdout share must be between 5% and 50%.', 'flx-ads' ) );
			}
			foreach ( self::all() as $x ) {
				if ( 'holdout' === $x['type'] && 'running' === $x['status'] && $x['audience'] === $aud['id'] ) {
					return new WP_Error( 'flxads_exp', __( 'This audience already has a running holdout test.', 'flx-ads' ) );
				}
			}
			$e += array( 'audience' => $aud['id'], 'audience_name' => $aud['name'], 'pct' => $pct );
			$e['name'] = '' !== $e['name'] ? $e['name'] : $aud['name'];
			$r = FLXADS_Audiences::set_holdout( $aud['id'], $pct, $e['id'] );
			if ( is_wp_error( $r ) ) {
				FLXADS_Audiences::set_holdout( $aud['id'], 0 );
				return $r;
			}
		}
		return self::put( $e );
	}

	/** Deneyi bitirir; holdout'ta kişiler kitleye geri girer. @return array|WP_Error */
	public static function finish( $id ) {
		$e = self::get( $id );
		if ( ! $e ) {
			return new WP_Error( 'flxads_exp', __( 'Test not found.', 'flx-ads' ) );
		}
		if ( 'running' !== $e['status'] ) {
			return $e;
		}
		if ( 'holdout' === $e['type'] && FLXADS_Audiences::get( $e['audience'] ) ) {
			$r = FLXADS_Audiences::set_holdout( $e['audience'], 0 );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		$e['status'] = 'ended';
		$e['ended']  = flxads_now();
		if ( '' === $e['end'] || $e['end'] > wp_date( 'Y-m-d' ) ) {
			$e['end'] = wp_date( 'Y-m-d' );
		}
		$e['result'] = self::result( $e );
		return self::put( $e );
	}

	/** Bitiş tarihi geçen deneyler kendiliğinden biter (saatlik iş). */
	public static function maybe_finish() {
		foreach ( self::all() as $e ) {
			if ( 'running' === $e['status'] && '' !== $e['end'] && $e['end'] < wp_date( 'Y-m-d' ) ) {
				self::finish( $e['id'] );
			}
		}
	}

	public static function delete( $id ) {
		$e = self::get( $id );
		if ( ! $e ) {
			return new WP_Error( 'flxads_exp', __( 'Test not found.', 'flx-ads' ) );
		}
		if ( 'running' === $e['status'] ) {
			$r = self::finish( $id );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		update_option( self::OPT, array_values( array_filter( self::all(), static function ( $x ) use ( $id ) { return $x['id'] !== (string) $id; } ) ), false );
		return true;
	}

	public static function init() {
		add_action( 'flxads_retry', array( __CLASS__, 'maybe_finish' ) );
	}

	/* ------------------------------------------------------------ Sonuç */

	/**
	 * @return array [ ok, a{...}, b{...}, p, confidence, winner (a|b|''), verdict, ... ]
	 */
	public static function result( array $e ) {
		if ( 'ended' === $e['status'] && ! empty( $e['result'] ) ) {
			return $e['result'];
		}
		$to = '' !== $e['end'] && $e['end'] < wp_date( 'Y-m-d' ) ? $e['end'] : wp_date( 'Y-m-d' );
		return 'ab' === $e['type'] ? self::ab_result( $e, $e['start'], $to ) : self::holdout_result( $e, $e['start'], $to );
	}

	private static function ab_result( array $e, $from, $to ) {
		$c = FLXADS_Manager::campaign( $e['campaign'], $from . ':' . $to, false );
		if ( is_wp_error( $c ) ) {
			return array( 'ok' => false, 'error' => $c->get_error_message() );
		}
		$m = array();
		foreach ( (array) $c['adsets'] as $s ) {
			foreach ( (array) $s['ads'] as $ad ) {
				if ( in_array( $ad['id'], array( $e['a'], $e['b'] ), true ) ) {
					$m[ $ad['id'] ] = isset( $m[ $ad['id'] ] ) ? $m[ $ad['id'] ] : array( 'name' => $ad['name'], 'status' => $ad['effective'], 'spend' => 0.0, 'impressions' => 0, 'clicks' => 0 );
					$m[ $ad['id'] ]['spend']       += $ad['spend'];
					$m[ $ad['id'] ]['impressions'] += $ad['impressions'];
					$m[ $ad['id'] ]['clicks']      += $ad['clicks'];
				}
			}
		}
		$pay = self::site_by_content( array( $e['campaign'], (string) $c['name'] ), $from, $to );
		$out = array( 'ok' => true, 'from' => $from, 'to' => $to, 'currency' => (string) $c['currency'], 'base' => FLXADS_Report::base_currency() );
		foreach ( array( 'a', 'b' ) as $k ) {
			$x         = $m[ $e[ $k ] ] ?? array( 'name' => $e[ $k . '_name' ], 'status' => '', 'spend' => 0.0, 'impressions' => 0, 'clicks' => 0 );
			$p         = $pay[ FLXADS_Spend::norm( $x['name'] ) ] ?? array( 0, 0.0 );
			$x['id']        = $e[ $k ];
			$x['payments']  = (int) $p[0];
			$x['income']    = round( (float) $p[1], 2 );
			$x['ctr']       = $x['impressions'] ? round( 100 * $x['clicks'] / $x['impressions'], 2 ) : null;
			$x['conv']      = $x['clicks'] ? round( 100 * $x['payments'] / $x['clicks'], 2 ) : null;
			$x['cpa']       = $x['payments'] ? round( $x['spend'] / $x['payments'], 2 ) : null;
			$x['roas']      = $x['spend'] > 0 ? round( $x['income'] / $x['spend'], 2 ) : null;
			$out[ $k ] = $x;
		}
		/* Önce ödeme / tıklama; ödeme çok azsa tıklama oranı (CTR) ile karar. */
		$enough = $out['a']['payments'] + $out['b']['payments'] >= 10;
		if ( $enough ) {
			$t             = self::ztest( $out['a']['payments'], $out['a']['clicks'], $out['b']['payments'], $out['b']['clicks'] );
			$out['metric'] = 'conv';
		} else {
			$t             = self::ztest( $out['a']['clicks'], $out['a']['impressions'], $out['b']['clicks'], $out['b']['impressions'] );
			$out['metric'] = 'ctr';
		}
		$out['p']          = $t['p'];
		$out['confidence'] = null === $t['p'] ? null : round( 100 * ( 1 - $t['p'] ), 1 );
		/* Az veriyle kazanan ilan edilmez. */
		$min_ok        = $enough ? min( $out['a']['clicks'], $out['b']['clicks'] ) >= 50 : min( $out['a']['impressions'], $out['b']['impressions'] ) >= 1000;
		$out['small']  = ! $min_ok;
		$out['winner'] = $min_ok && null !== $t['p'] && $t['p'] < 0.05 ? ( $t['z'] > 0 ? 'a' : 'b' ) : '';
		return $out;
	}

	/** Sitedeki Meta ödemeleri, utm_content'e göre (kampanya kimliği ya da adıyla): norm(content) => [ adet, tutar ]. */
	private static function site_by_content( array $campaigns, $from, $to ) {
		global $wpdb;
		$t                = flxads_table( 'orders' );
		list( $gf, $gt )  = self::range( $from, $to );
		$names            = array_values( array_unique( array_filter( array_map( 'strval', $campaigns ) ) ) );
		$rows             = (array) $wpdb->get_results( $wpdb->prepare( "SELECT campaign, content, total, currency, paid_at FROM $t WHERE status = 'paid' AND channel = 'meta' AND paid_at BETWEEN %s AND %s", $gf, $gt ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$want             = array_map( array( 'FLXADS_Spend', 'norm' ), $names );
		$out              = array();
		foreach ( $rows as $r ) {
			if ( ! in_array( (string) $r['campaign'], $names, true ) && ! in_array( FLXADS_Spend::norm( (string) $r['campaign'] ), $want, true ) ) {
				continue;
			}
			$k         = FLXADS_Spend::norm( (string) $r['content'] );
			$amt       = FLXADS_Report::to_base( (float) $r['total'], (string) $r['currency'], (string) $r['paid_at'] );
			$out[ $k ] = array( ( $out[ $k ][0] ?? 0 ) + 1, ( $out[ $k ][1] ?? 0.0 ) + ( null === $amt ? 0.0 : $amt ) );
		}
		return $out;
	}

	private static function holdout_result( array $e, $from, $to ) {
		global $wpdb;
		$aud = FLXADS_Audiences::get( $e['audience'] );
		if ( ! $aud ) {
			return array( 'ok' => false, 'error' => __( 'The audience of this test was deleted.', 'flx-ads' ) );
		}
		$ids = FLXADS_Audiences::member_ids( $aud['source'] );
		$grp = array( 'a' => array(), 'b' => array() ); // a = reklam gören, b = holdout
		foreach ( $ids as $pid ) {
			$grp[ self::in_holdout( $aud['id'], $pid, (int) $e['pct'] ) ? 'b' : 'a' ][] = (int) $pid;
		}
		$t               = flxads_table( 'orders' );
		list( $gf, $gt ) = self::range( $from, $to );
		$out             = array( 'ok' => true, 'from' => $from, 'to' => $to, 'base' => FLXADS_Report::base_currency() );
		foreach ( $grp as $k => $list ) {
			$payers = array();
			$n      = 0;
			$sum    = 0.0;
			foreach ( array_chunk( $list, 1000 ) as $chunk ) {
				$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT person_id, total, currency, paid_at FROM $t WHERE status = 'paid' AND paid_at BETWEEN %s AND %s AND person_id IN (" . implode( ',', $chunk ) . ')', $gf, $gt ), ARRAY_A ); // phpcs:ignore WordPress.DB
				foreach ( $rows as $r ) {
					$payers[ (int) $r['person_id'] ] = 1;
					++$n;
					$amt  = FLXADS_Report::to_base( (float) $r['total'], (string) $r['currency'], (string) $r['paid_at'] );
					$sum += null === $amt ? 0.0 : $amt;
				}
			}
			$size      = count( $list );
			$out[ $k ] = array(
				'people'     => $size,
				'payers'     => count( $payers ),
				'payments'   => $n,
				'income'     => round( $sum, 2 ),
				'rate'       => $size ? round( 100 * count( $payers ) / $size, 2 ) : null,
				'per_person' => $size ? round( $sum / $size, 2 ) : null,
			);
		}
		$t                 = self::ztest( $out['a']['payers'], $out['a']['people'], $out['b']['payers'], $out['b']['people'] );
		$out['p']          = $t['p'];
		$out['confidence'] = null === $t['p'] ? null : round( 100 * ( 1 - $t['p'] ), 1 );
		$ra                = $out['a']['people'] ? $out['a']['payers'] / $out['a']['people'] : 0;
		$rb                = $out['b']['people'] ? $out['b']['payers'] / $out['b']['people'] : 0;
		$out['lift']       = $rb > 0 ? round( 100 * ( $ra - $rb ) / $rb, 1 ) : null;
		$out['extra_payers'] = (int) round( ( $ra - $rb ) * $out['a']['people'] );
		$out['extra_income'] = round( ( (float) $out['a']['per_person'] - (float) $out['b']['per_person'] ) * $out['a']['people'], 2 );
		$out['small']      = $out['b']['people'] < 100;
		$out['winner']     = ! $out['small'] && null !== $t['p'] && $t['p'] < 0.05 ? ( $t['z'] > 0 ? 'a' : 'b' ) : '';
		return $out;
	}

	/** İki oran z-testi. @return array [ z, p ] (p iki yönlü; veri yetersizse null) */
	public static function ztest( $x1, $n1, $x2, $n2 ) {
		if ( $n1 < 1 || $n2 < 1 ) {
			return array( 'z' => 0.0, 'p' => null );
		}
		$p1 = $x1 / $n1;
		$p2 = $x2 / $n2;
		$p  = ( $x1 + $x2 ) / ( $n1 + $n2 );
		$se = sqrt( $p * ( 1 - $p ) * ( 1 / $n1 + 1 / $n2 ) );
		if ( $se <= 0 ) {
			return array( 'z' => 0.0, 'p' => null );
		}
		$z = ( $p1 - $p2 ) / $se;
		return array( 'z' => $z, 'p' => round( 2 * ( 1 - self::phi( abs( $z ) ) ), 4 ) );
	}

	/** Standart normal dağılım (Abramowitz–Stegun 7.1.26). */
	private static function phi( $x ) {
		$t = 1 / ( 1 + 0.3275911 * $x / M_SQRT2 );
		$y = 1 - ( ( ( ( 1.061405429 * $t - 1.453152027 ) * $t + 1.421413741 ) * $t - 0.284496736 ) * $t + 0.254829592 ) * $t * exp( - $x * $x / 2 );
		return 0.5 * ( 1 + $y );
	}

	/** Sonucun düz metni (sitenin dilinde). */
	public static function verdict( array $e, array $r ) {
		if ( empty( $r['ok'] ) ) {
			return (string) ( $r['error'] ?? '' );
		}
		if ( 'ab' === $e['type'] ) {
			if ( null === $r['p'] ) {
				return __( 'Not enough data yet: wait until both ads have clicks.', 'flx-ads' );
			}
			$metric = 'conv' === $r['metric'] ? __( 'payments per click', 'flx-ads' ) : __( 'click rate', 'flx-ads' );
			if ( '' === $r['winner'] ) {
				if ( ! empty( $r['small'] ) ) {
					return __( 'Too little data yet: each ad needs at least 50 clicks (1,000 impressions while payments are few). Let it run longer.', 'flx-ads' );
				}
				/* translators: 1: metric, 2: confidence */
				return sprintf( __( 'No clear winner yet on %1$s (confidence %2$s%%; 95%% is needed). Let it run longer.', 'flx-ads' ), $metric, number_format_i18n( (float) $r['confidence'], 1 ) );
			}
			$w = $r[ $r['winner'] ];
			$l = $r[ 'a' === $r['winner'] ? 'b' : 'a' ];
			/* translators: 1: winner, 2: metric, 3: confidence, 4: loser */
			return sprintf( __( '"%1$s" wins on %2$s with %3$s%% confidence. You can pause "%4$s".', 'flx-ads' ), $w['name'], $metric, number_format_i18n( (float) $r['confidence'], 1 ), $l['name'] );
		}
		if ( null === $r['p'] ) {
			return __( 'Not enough data yet.', 'flx-ads' );
		}
		if ( 'a' === $r['winner'] ) {
			/* translators: 1: lift percent, 2: extra payers, 3: extra income, 4: confidence */
			return sprintf( __( 'Ads work: people who could see them gave %1$s%% more often. About %2$s extra donors and %3$s extra income came from the ads (confidence %4$s%%).', 'flx-ads' ), number_format_i18n( (float) $r['lift'], 1 ), number_format_i18n( (int) $r['extra_payers'] ), flxads_money( $r['extra_income'], $r['base'] ), number_format_i18n( (float) $r['confidence'], 1 ) );
		}
		if ( 'b' === $r['winner'] ) {
			return __( 'People who did not see the ads gave more often. The ads to this audience are not adding donations; try other text or another audience.', 'flx-ads' );
		}
		if ( $r['small'] ) {
			/* translators: %s: people */
			return sprintf( __( 'The holdout group is still small (%s people; at least 100 are needed). Use a larger audience or share, or wait for it to grow.', 'flx-ads' ), number_format_i18n( (int) $r['b']['people'] ) );
		}
		/* translators: %s: confidence */
		return sprintf( __( 'No clear difference yet between people who could see the ads and the holdout (confidence %s%%; 95%% is needed).', 'flx-ads' ), number_format_i18n( (float) $r['confidence'], 1 ) );
	}

	public static function export( array $e ) {
		$r = self::result( $e );
		return array_diff_key( $e, array( 'result' => 1 ) ) + array( 'result' => $r, 'verdict' => self::verdict( $e, $r ) );
	}

	private static function day( $v ) {
		$v = (string) $v;
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
	}

	private static function range( $from, $to ) {
		$tz = wp_timezone();
		$gf = ( new DateTime( $from . ' 00:00:00', $tz ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		$gt = ( new DateTime( $to . ' 23:59:59', $tz ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		return array( $gf, $gt );
	}
}
