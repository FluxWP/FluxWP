<?php
/**
 * Reports for the admin screen (GraphQL part b9): pick a report, choose a period, read the totals and the
 * table; managers edit a report's JSON, add new ones and put shipped ones back.
 *
 * The wp-admin screen (packages/modules/src/donate/Reports.tsx) calls this class. Reading needs
 * flx_bagis_view; the editor and every write need flxb_can_manage(). The CSV stays a download
 * (admin-post flxb_report_csv).
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_Admin_Reports_Service {

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	private static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/**
	 * @param mixed $v Value.
	 * @return array<string,mixed>
	 */
	private static function map( $v ): array {
		$out = array();
		foreach ( is_array( $v ) ? $v : array() as $k => $x ) {
			$out[ (string) $k ] = $x;
		}
		return $out;
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function s( $v ): string {
		if ( is_array( $v ) ) { // flxb_status_label() gives [tone, label]
			return self::s( $v[1] ?? '' );
		}
		return is_scalar( $v ) ? (string) $v : '';
	}

	public static function can_view(): bool {
		return (bool) flxb_can_view();
	}

	public static function can_manage(): bool {
		return (bool) flxb_can_manage();
	}

	private static function invalid( string $msg ): WP_Error {
		return new WP_Error( 'flxb_report', $msg, array( 'graphql_code' => 'INVALID_INPUT' ) );
	}

	/**
	 * A cell as people read it.
	 *
	 * @param array<string,mixed> $col Column.
	 * @param mixed               $v   Value.
	 */
	private static function cell( array $col, $v, string $cur ): string {
		if ( ! empty( $col['dim'] ) ) {
			return self::s( FLXB_Reports::show( self::s( $col['kind'] ?? '' ), $v ) );
		}
		$f = is_numeric( $v ) ? (float) $v : 0.0;
		if ( 'money' === ( $col['kind'] ?? '' ) ) {
			return self::s( flxb_money( $f, $cur ) );
		}
		return self::s( number_format_i18n( $f, floor( $f ) == $f ? 0 : 1 ) ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
	}

	/**
	 * A report with its period, totals and table.
	 *
	 * @param string $id     Report id; empty = the first one.
	 * @param string $period Period key or custom; empty = the report's own.
	 * @return array<string,mixed>
	 */
	public static function report( string $id, string $period, string $from, string $to ): array {
		$all  = self::arr( FLXB_Reports::all() );
		$list = array();
		foreach ( $all as $k => $x ) {
			$list[] = array(
				'value' => (string) $k,
				'label' => self::s( FLXB_Reports::text( self::map( $x )['title'] ?? '' ) ),
			);
		}
		$periods = array();
		foreach ( self::arr( FLXB_Reports::periods() ) + array( 'custom' => __( 'Custom dates', 'flx-donate' ) ) as $k => $l ) {
			$periods[] = array(
				'value' => (string) $k,
				'label' => self::s( $l ),
			);
		}
		$id  = sanitize_key( $id );
		$raw = $all[ $id ] ?? ( $all ? reset( $all ) : null );
		$out = array(
			'reports'     => $list,
			'canManage'   => self::can_manage(),
			'periods'     => $periods,
			'id'          => '',
			'title'       => '',
			'description' => '',
			'edited'      => false,
			'builtin'     => false,
			'period'      => '',
			'from'        => '',
			'to'          => '',
			'csvUrl'      => '',
			'kpis'        => array(),
			'columns'     => array(),
			'rows'        => array(),
			'note'        => '',
		);
		if ( ! is_array( $raw ) ) {
			return $out;
		}
		$r   = self::map( $raw );
		$rid = self::s( $r['id'] ?? '' );
		$day = static fn( string $d ): string => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : '';
		$pk  = sanitize_key( $period );
		if ( 'custom' === $pk ) {
			$f = $day( $from );
			$t = $day( $to );
		} else {
			$pk               = isset( self::arr( FLXB_Reports::periods() )[ $pk ] ) ? $pk : self::s( $r['period'] ?? '' );
			list( $f, $t )    = array_map( array( __CLASS__, 's' ), array_pad( self::arr( FLXB_Reports::range( $pk ) ), 2, '' ) );
		}
		$res  = self::map(
			FLXB_Reports::run(
				$raw,
				array(
					'from' => $f,
					'to'   => $t,
				)
			)
		);
		$cols = self::map( $res['cols'] ?? array() );
		$rows = self::arr( $res['rows'] ?? array() );

		/* Totals as stat tiles; money in the site currency first, other currencies below. */
		$kpis = array();
		if ( $rows ) {
			$main   = self::s( flxb_default_currency() );
			$totals = self::map( $res['totals'] ?? array() );
			$total  = self::map( $res['total'] ?? array() );
			foreach ( $cols as $k => $c ) {
				$c = self::map( $c );
				if ( ! empty( $c['dim'] ) ) {
					continue;
				}
				$sub = '';
				if ( 'money' === ( $c['kind'] ?? '' ) ) {
					$curs = array_map( 'strval', array_keys( $totals ) );
					$top  = in_array( $main, $curs, true ) ? $main : (string) reset( $curs );
					$val  = self::cell( $c, self::map( $totals[ $top ] ?? array() )[ $k ] ?? 0, $top );
					$rest = array();
					foreach ( $totals as $cur => $tt ) {
						if ( $cur !== $top ) {
							$rest[] = self::cell( $c, self::map( $tt )[ $k ] ?? 0, $cur );
						}
					}
					$sub = $rest ? '+ ' . implode( ' · ', $rest ) : '';
				} else {
					$val = self::cell( $c, $total[ $k ] ?? 0, '' );
				}
				$kpis[] = array(
					'label' => self::s( $c['label'] ?? '' ),
					'value' => $val,
					'sub'   => $sub,
				);
			}
		}

		/* Table: the first metric gets a bar when the report says "chart": "bar". */
		$group    = array_map( array( __CLASS__, 's' ), self::arr( $r['group'] ?? array() ) );
		$show_cur = isset( $cols['currency'] ) && ! in_array( 'currency', $group, true );
		$first    = '';
		foreach ( $cols as $k => $c ) {
			if ( empty( self::map( $c )['dim'] ) && '' === $first ) {
				$first = $k;
			}
		}
		$max = 0.0;
		if ( 'bar' === ( $r['chart'] ?? '' ) && '' !== $first ) {
			foreach ( $rows as $row ) {
				$v   = self::map( $row )[ $first ] ?? 0;
				$max = max( $max, is_numeric( $v ) ? (float) $v : 0.0 );
			}
		}
		$columns = array();
		foreach ( $cols as $k => $c ) {
			if ( 'currency' === $k && $show_cur ) {
				continue;
			}
			$c         = self::map( $c );
			$columns[] = array(
				'label' => self::s( $c['label'] ?? '' ),
				'num'   => empty( $c['dim'] ),
			);
		}
		$table = array();
		foreach ( $rows as $row ) {
			$row   = self::map( $row );
			$cur   = self::s( $row['currency'] ?? '' );
			$cells = array();
			foreach ( $cols as $k => $c ) {
				if ( 'currency' === $k && $show_cur ) {
					continue;
				}
				$cells[] = self::cell( self::map( $c ), $row[ $k ] ?? '', $cur );
			}
			$v       = $row[ $first ] ?? 0;
			$table[] = array(
				'cells' => $cells,
				'bar'   => $max > 0 ? round( 100 * ( is_numeric( $v ) ? (float) $v : 0.0 ) / $max, 1 ) : 0.0,
			);
		}
		$out['id']          = $rid;
		$out['title']       = self::s( FLXB_Reports::text( $r['title'] ?? '' ) );
		$out['description'] = self::s( FLXB_Reports::text( $r['description'] ?? '' ) );
		$out['edited']      = ! empty( $r['edited'] );
		$out['builtin']     = ! empty( $r['builtin'] );
		$out['period']      = $pk;
		$out['from']        = $f;
		$out['to']          = $t;
		$out['csvUrl']      = wp_nonce_url( admin_url( 'admin-post.php?action=flxb_report_csv&r=' . $rid . '&from=' . $f . '&to=' . $t ), 'flxb_report_csv' );
		$out['kpis']        = $kpis;
		$out['columns']     = $columns;
		$out['rows']        = $table;
		/* translators: %s: row limit */
		$out['note'] = ! empty( $res['cut'] ) ? sprintf( __( 'Only the first %s rows are shown. The CSV has the same rows.', 'flx-donate' ), number_format_i18n( (float) self::s( $r['limit'] ?? 0 ) ) ) : '';
		return $out;
	}

	/** @return array<string,mixed> */
	private static function template(): array {
		return array(
			'id'          => 'my-report',
			'title'       => array(
				'tr' => 'Yeni rapor',
				'en' => 'New report',
			),
			'description' => '',
			'source'      => 'donations',
			'filter'      => array( 'paid' => true ),
			'group'       => array( 'type' ),
			'metrics'     => array( 'count', 'amount', 'donors' ),
			'period'      => 'this_year',
			'chart'       => 'bar',
		);
	}

	/**
	 * The JSON editor of a report; id empty = a new one.
	 *
	 * @return array<string,mixed>
	 */
	public static function source( string $id ): array {
		$id  = sanitize_key( $id );
		$raw = '' !== $id ? FLXB_Reports::raw( $id ) : null;
		$raw = is_array( $raw ) ? $raw : self::template();
		$def = '' !== $id ? FLXB_Reports::get( $id ) : null;
		$def = is_array( $def ) ? self::map( $def ) : null;
		$help = array();
		foreach ( self::arr( FLXB_Reports::sources() ) as $s => $sl ) {
			if ( 'items' === $s && ! flxb_track_enabled() ) {
				continue;
			}
			$dims = array();
			foreach ( self::arr( FLXB_Reports::dims( $s ) ) as $k => $d ) {
				$dims[] = (string) $k . ' — ' . self::s( self::arr( $d )[0] ?? '' );
			}
			$mets = array();
			foreach ( self::arr( FLXB_Reports::metrics( $s ) ) as $k => $d ) {
				$mets[] = (string) $k . ' — ' . self::s( self::arr( $d )[0] ?? '' );
			}
			$help[] = array(
				'source'  => (string) $s,
				'label'   => self::s( $sl ),
				'group'   => $dims,
				'metrics' => $mets,
			);
		}
		return array(
			'id'      => $def ? $id : '',
			'title'   => $def ? self::s( FLXB_Reports::text( $def['title'] ?? '' ) ) : '',
			'json'    => self::s( wp_json_encode( $raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ),
			'edited'  => $def && ! empty( $def['edited'] ),
			'builtin' => $def && ! empty( $def['builtin'] ),
			'help'    => $help,
			'periods' => implode( ', ', array_map( 'strval', array_keys( self::arr( FLXB_Reports::periods() ) ) ) ),
		);
	}

	/** @return array{message: string, id: string}|WP_Error */
	public static function save( string $json, string $old ) {
		$r = FLXB_Reports::save( $json, sanitize_key( $old ) );
		if ( is_wp_error( $r ) ) {
			return self::invalid( $r->get_error_message() );
		}
		return array(
			'message' => __( 'Saved.', 'flx-donate' ),
			'id'      => self::s( $r ),
		);
	}

	/** @return array{message: string} */
	public static function delete( string $id ): array {
		FLXB_Reports::delete( sanitize_key( $id ) );
		return array( 'message' => __( 'Saved.', 'flx-donate' ) );
	}
}
