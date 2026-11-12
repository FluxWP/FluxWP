<?php
/**
 * Flux Ads: Sezon planı. Bağış ve satışın yükseldiği dönemler (Ramazan, Kurban, 11 Kasım, yıl sonu, Giving Tuesday...)
 * ve kullanıcının eklediği tarihler; her biri için geçen yılın aynı dönemindeki gelir/harcama ve "hazırlık zamanı".
 * Taslak Stüdyo'da yazılır; burada hiçbir şey Meta'ya gitmez.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

final class FLXADS_Planner {

	const OPT  = 'flxads_seasons_custom';
	const PREP = 14;

	/** Diyanet takvimine göre (yaklaşık; gün sapabilir). Yıl => [ Ramazan 1. gün, Ramazan Bayramı 1. gün, Kurban Bayramı 1. gün ]. */
	const HIJRI = array(
		2025 => array( '2025-03-01', '2025-03-30', '2025-06-06' ),
		2026 => array( '2026-02-19', '2026-03-20', '2026-05-27' ),
		2027 => array( '2027-02-08', '2027-03-09', '2027-05-16' ),
		2028 => array( '2028-01-28', '2028-02-26', '2028-05-05' ),
	);

	/** Bir yılın dönemleri: key => [ name, start, end, prep_days, approx ]. */
	public static function year( $y ) {
		$y   = (int) $y;
		$out = array();
		if ( isset( self::HIJRI[ $y ] ) ) {
			list( $ram, $fitr, $adha ) = self::HIJRI[ $y ];
			$out['ramadan'] = array( __( 'Ramadan and Eid', 'flx-ads' ), $ram, self::add( $fitr, 2 ), 21, true );
			$out['qurban']  = array( __( 'Qurban (Eid al-Adha)', 'flx-ads' ), self::add( $adha, -30 ), self::add( $adha, 3 ), 14, true );
		}
		$thanks = self::nth_weekday( $y, 11, 4, 4 ); // Kasım'ın 4. perşembesi
		$out['forest']   = array( __( 'World Forestry Day and Forest Week', 'flx-ads' ), "$y-03-21", "$y-03-26", 14, false );
		$out['mothers']  = array( __( 'Mother\'s Day (donate in her name)', 'flx-ads' ), self::add( self::nth_weekday( $y, 5, 0, 2 ), -7 ), self::nth_weekday( $y, 5, 0, 2 ), 14, false );
		$out['environ']  = array( __( 'World Environment Day', 'flx-ads' ), "$y-05-29", "$y-06-05", 14, false );
		$out['trees']    = array( __( 'National Tree Planting Day (11 November)', 'flx-ads' ), "$y-10-25", "$y-11-11", 21, false );
		$out['teachers'] = array( __( 'Teachers\' Day (donate in their name)', 'flx-ads' ), "$y-11-17", "$y-11-24", 14, false );
		$out['giving']   = array( __( 'Black Friday and Giving Tuesday', 'flx-ads' ), self::add( $thanks, 1 ), self::add( $thanks, 5 ), 14, false );
		$out['yearend']  = array( __( 'Year-end giving', 'flx-ads' ), "$y-12-15", "$y-12-31", 14, false );
		/**
		 * Sezon planı dönemleri (ön ayarlar kendi tarihlerini ekleyebilir).
		 *
		 * @param array $out key => [ name, start Y-m-d, end Y-m-d, prep_days, approx ]
		 * @param int   $y   Yıl.
		 */
		return (array) apply_filters( 'flxads_seasons', $out, $y );
	}

	public static function custom() {
		$a = get_option( self::OPT, array() );
		return is_array( $a ) ? array_values( $a ) : array();
	}

	/** @return array|WP_Error */
	public static function add_custom( array $d ) {
		$name  = mb_substr( trim( sanitize_text_field( (string) ( $d['name'] ?? '' ) ) ), 0, 80 );
		$start = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $d['start'] ?? '' ) ) ? (string) $d['start'] : '';
		$end   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $d['end'] ?? '' ) ) ? (string) $d['end'] : $start;
		if ( '' === $name || '' === $start ) {
			return new WP_Error( 'flxads_season', __( 'Enter a name and a start date.', 'flx-ads' ) );
		}
		if ( $end < $start ) {
			return new WP_Error( 'flxads_season', __( 'The end date is before the start date.', 'flx-ads' ) );
		}
		$all = self::custom();
		if ( count( $all ) >= 50 ) {
			return new WP_Error( 'flxads_season', __( 'Too many dates; delete old ones first.', 'flx-ads' ) );
		}
		$e     = array( 'id' => 'c' . strtolower( wp_generate_password( 8, false ) ), 'name' => $name, 'start' => $start, 'end' => $end, 'prep' => max( 3, min( 60, (int) ( $d['prep'] ?? self::PREP ) ) ) );
		$all[] = $e;
		update_option( self::OPT, $all, false );
		return $e;
	}

	public static function delete_custom( $id ) {
		$all  = self::custom();
		$keep = array_values( array_filter( $all, static function ( $e ) use ( $id ) { return $e['id'] !== (string) $id; } ) );
		if ( count( $keep ) === count( $all ) ) {
			return new WP_Error( 'flxads_season', __( 'Date not found.', 'flx-ads' ) );
		}
		update_option( self::OPT, $keep, false );
		return true;
	}

	/**
	 * Önümüzdeki dönemler (bugün süren ve $days gün içinde başlayanlar), geçen yılın sayılarıyla.
	 *
	 * @return array[] { key, name, start, end, prep, approx, custom, days (başlamaya kalan, süren için 0), state (now|prep|later), last{from,to,income,payments,new,spend} }
	 */
	public static function upcoming( $days = 365, $with_last = true ) {
		$today = wp_date( 'Y-m-d' );
		$until = wp_date( 'Y-m-d', time() + $days * DAY_IN_SECONDS );
		$y     = (int) wp_date( 'Y' );
		$list  = array();
		foreach ( array( $y, $y + 1 ) as $yy ) {
			foreach ( self::year( $yy ) as $k => $e ) {
				$list[] = array( 'key' => $k . '-' . $yy, 'type' => $k, 'year' => $yy, 'name' => $e[0], 'start' => $e[1], 'end' => $e[2], 'prep' => (int) $e[3], 'approx' => ! empty( $e[4] ), 'custom' => false );
			}
		}
		foreach ( self::custom() as $c ) {
			$list[] = array( 'key' => $c['id'], 'type' => 'custom', 'year' => (int) substr( $c['start'], 0, 4 ), 'name' => $c['name'], 'start' => $c['start'], 'end' => $c['end'], 'prep' => (int) $c['prep'], 'approx' => false, 'custom' => true, 'id' => $c['id'] );
		}
		$out = array();
		foreach ( $list as $e ) {
			if ( $e['end'] < $today || $e['start'] > $until ) {
				continue;
			}
			$left       = (int) round( ( strtotime( $e['start'] . ' 12:00' ) - strtotime( $today . ' 12:00' ) ) / DAY_IN_SECONDS );
			$e['days']  = max( 0, $left );
			$e['state'] = $left <= 0 ? 'now' : ( $left <= $e['prep'] ? 'prep' : 'later' );
			if ( $with_last ) {
				$e['last'] = self::last_year( $e );
			}
			$out[] = $e;
		}
		usort( $out, static function ( $a, $b ) { return strcmp( $a['start'], $b['start'] ); } );
		return $out;
	}

	/** Geçen yılın aynı dönemi (hicri dönemlerde geçen yılın kendi tarihleri). */
	public static function last_year( array $e ) {
		$prev = $e['year'] - 1;
		if ( ! $e['custom'] && isset( self::year( $prev )[ $e['type'] ] ) ) {
			$p    = self::year( $prev )[ $e['type'] ];
			$from = $p[1];
			$to   = $p[2];
		} else {
			$from = self::add_years( $e['start'], -1 );
			$to   = self::add_years( $e['end'], -1 );
		}
		$g = FLXADS_Report::grouped( 'channel', array( 'from' => $from, 'to' => $to, 'kind' => 'all' ) );
		$t = (array) ( $g['totals'] ?? array() );
		return array(
			'from'     => $from,
			'to'       => $to,
			'income'   => round( (float) ( $t['amount'] ?? 0 ), 2 ),
			'payments' => (int) ( $t['count'] ?? 0 ),
			'new'      => (int) ( $t['new'] ?? 0 ),
			'spend'    => round( (float) FLXADS_Spend::totals( $from, $to, '' )['total'], 2 ),
			'base'     => FLXADS_Report::base_currency(),
		);
	}

	/** Stüdyo için hazır brif. */
	public static function brief( array $e ) {
		$l = $e['last'] ?? self::last_year( $e );
		/* translators: 1: season name, 2: start, 3: end */
		$b = sprintf( __( 'Campaign for %1$s (%2$s – %3$s).', 'flx-ads' ), $e['name'], flxads_date( $e['start'], 'date' ), flxads_date( $e['end'], 'date' ) );
		if ( $l['payments'] ) {
			/* translators: 1: payments, 2: income */
			$b .= ' ' . sprintf( __( 'Last year the same period brought %1$s payments and %2$s.', 'flx-ads' ), number_format_i18n( $l['payments'] ), flxads_money( $l['income'], $l['base'] ) );
		}
		return $b;
	}

	/** Stüdyo'yu bu dönemin brifi ve tarihleriyle açan adres. */
	public static function studio_url( array $e ) {
		return flxads_admin_url( 'studio', array( 'pre_brief' => rawurlencode( $e['brief'] ?? self::brief( $e ) ), 'pre_start' => $e['start'], 'pre_end' => $e['end'] ) );
	}

	private static function add( $date, $days ) {
		return wp_date( 'Y-m-d', strtotime( $date . ' 12:00 UTC' ) + $days * DAY_IN_SECONDS, new DateTimeZone( 'UTC' ) );
	}

	private static function add_years( $date, $n ) {
		$d = DateTime::createFromFormat( 'Y-m-d', $date, new DateTimeZone( 'UTC' ) );
		return $d ? $d->modify( ( $n >= 0 ? '+' : '' ) . $n . ' year' )->format( 'Y-m-d' ) : $date;
	}

	/** Ayın n. haftagünü (0 = pazar). */
	private static function nth_weekday( $y, $m, $dow, $n ) {
		$first = (int) gmdate( 'w', gmmktime( 12, 0, 0, $m, 1, $y ) );
		$day   = 1 + ( ( $dow - $first + 7 ) % 7 ) + 7 * ( $n - 1 );
		return sprintf( '%04d-%02d-%02d', $y, $m, $day );
	}
}
