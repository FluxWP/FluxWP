<?php
/**
 * Flux Donate 2.11: JSON-defined reports.
 *
 * A report is a small JSON document (schema flxb-report/1): what to count (donations or tracked
 * records), how to group (up to two dimensions), which numbers to show and the default period.
 * Only whitelisted dimensions and metrics are turned into SQL, so an edited report can never run
 * arbitrary queries.
 *
 * Sources of reports, later wins on the same id:
 *   1. assets/reports/*.json shipped with the plugin,
 *   2. filter flxb_reports (other plugins, e.g. Flux Kurban: id => array or path to a .json file),
 *   3. option flxb_reports (edited on the Reports screen; preset importer type flxb_report).
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_Reports {

	const SCHEMA = 'flxb-report/1';
	const OPTION = 'flxb_reports';
	const MAX    = 1000;

	private static $cache = null;

	/** Text in the site's language: a string, or {"tr": "...", "en": "..."}. */
	public static function text( $v ) {
		if ( is_array( $v ) ) {
			$loc = strtolower( substr( determine_locale(), 0, 2 ) );
			foreach ( array( $loc, 'en', 'tr' ) as $k ) {
				if ( isset( $v[ $k ] ) && '' !== (string) $v[ $k ] ) {
					return (string) $v[ $k ];
				}
			}
			$v = reset( $v );
		}
		return is_scalar( $v ) ? (string) $v : '';
	}

	public static function sources() {
		return array(
			'donations' => __( 'Donations', 'flx-donate' ),
			'items'     => __( 'Tracked records', 'flx-donate' ),
		);
	}

	public static function periods() {
		return array(
			'this_month'     => __( 'This month', 'flx-donate' ),
			'last_month'     => __( 'Last month', 'flx-donate' ),
			'last_30_days'   => __( 'Last 30 days', 'flx-donate' ),
			'this_year'      => __( 'This year', 'flx-donate' ),
			'last_year'      => __( 'Last year', 'flx-donate' ),
			'last_12_months' => __( 'Last 12 months', 'flx-donate' ),
			'all'            => __( 'All time', 'flx-donate' ),
		);
	}

	/** [from, to] in Y-m-d ('' = open) for a period key. */
	public static function range( $period ) {
		$t = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		switch ( $period ) {
			case 'last_month':
				$f = strtotime( gmdate( 'Y-m-01', $t ) . ' -1 month' );
				return array( gmdate( 'Y-m-01', $f ), gmdate( 'Y-m-t', $f ) );
			case 'last_30_days':
				return array( gmdate( 'Y-m-d', $t - 29 * DAY_IN_SECONDS ), gmdate( 'Y-m-d', $t ) );
			case 'this_year':
				return array( gmdate( 'Y-01-01', $t ), gmdate( 'Y-12-31', $t ) );
			case 'last_year':
				$y = (int) gmdate( 'Y', $t ) - 1;
				return array( $y . '-01-01', $y . '-12-31' );
			case 'last_12_months':
				return array( gmdate( 'Y-m-01', strtotime( gmdate( 'Y-m-01', $t ) . ' -11 months' ) ), gmdate( 'Y-m-t', $t ) );
			case 'all':
				return array( '', '' );
			default:
				return array( gmdate( 'Y-m-01', $t ), gmdate( 'Y-m-t', $t ) );
		}
	}

	/**
	 * Dimensions per source: key => [label, sql, kind]. kind tells how a raw value is shown.
	 */
	public static function dims( $source ) {
		if ( 'items' === $source ) {
			$date = '%DATE%';
			return array(
				'type'          => array( __( 'Donation type', 'flx-donate' ), 'i.type_id', 'type' ),
				'option'        => array( __( 'Option', 'flx-donate' ), 'i.option_label', 'text' ),
				'status'        => array( __( 'Status', 'flx-donate' ), 'i.status', 'item_status' ),
				'partner'       => array( FLXB_Track::label( 'partner' ), 'i.partner_id', 'partner' ),
				'partner_group' => array( __( 'Partner group', 'flx-donate' ), 'p.group_name', 'text' ),
				'source'        => array( __( 'Brought in by', 'flx-donate' ), 'i.source_id', 'partner' ),
				'region'        => array( __( 'Region', 'flx-donate' ), 'i.region', 'text' ),
				'payment'       => array( __( 'Payment method', 'flx-donate' ), 'd.provider', 'provider' ),
				'ref_user'      => array( __( 'Referral', 'flx-donate' ), 'd.ref_user_id', 'user' ),
				'currency'      => array( __( 'Currency', 'flx-donate' ), 'i.currency', 'text' ),
				'day'           => array( __( 'Day', 'flx-donate' ), "SUBSTR($date,1,10)", 'day' ),
				'month'         => array( __( 'Month', 'flx-donate' ), "SUBSTR($date,1,7)", 'month' ),
				'year'          => array( __( 'Year', 'flx-donate' ), "SUBSTR($date,1,4)", 'text' ),
			);
		}
		return array(
			'type'     => array( __( 'Donation type', 'flx-donate' ), 'd.type_id', 'type' ),
			'option'   => array( __( 'Option', 'flx-donate' ), 'd.option_label', 'text' ),
			'status'   => array( __( 'Status', 'flx-donate' ), 'd.status', 'donation_status' ),
			'kind'     => array( __( 'One-time / regular', 'flx-donate' ), 'd.type', 'kind' ),
			'payment'  => array( __( 'Payment method', 'flx-donate' ), 'd.provider', 'provider' ),
			'channel'  => array( __( 'Channel', 'flx-donate' ), 'd.source', 'text' ),
			'ref_user' => array( __( 'Referral', 'flx-donate' ), 'd.ref_user_id', 'user' ),
			'currency' => array( __( 'Currency', 'flx-donate' ), 'd.currency', 'text' ),
			'day'      => array( __( 'Day', 'flx-donate' ), 'SUBSTR(d.created_at,1,10)', 'day' ),
			'month'    => array( __( 'Month', 'flx-donate' ), 'SUBSTR(d.created_at,1,7)', 'month' ),
			'year'     => array( __( 'Year', 'flx-donate' ), 'SUBSTR(d.created_at,1,4)', 'text' ),
		);
	}

	/** Metrics per source: key => [label, sql, money]. Money metrics are summed per currency. */
	public static function metrics( $source ) {
		if ( 'items' === $source ) {
			$same = "(i.cost_currency = '' OR i.cost_currency = i.currency)";
			return array(
				'count'    => array( __( 'Records', 'flx-donate' ), 'COUNT(*)', false ),
				'quantity' => array( __( 'Quantity', 'flx-donate' ), 'SUM(i.quantity)', false ),
				'done'     => array( FLXB_Track::label( 'st_done' ), "SUM(CASE WHEN i.status IN ('done','delivered') THEN 1 ELSE 0 END)", false ),
				'open'     => array( __( 'Open', 'flx-donate' ), "SUM(CASE WHEN i.status IN ('pending','approved','sent') THEN 1 ELSE 0 END)", false ),
				'donors'   => array( __( 'Donors', 'flx-donate' ), 'COUNT(DISTINCT d.donor_id)', false ),
				'amount'   => array( __( 'Amount', 'flx-donate' ), 'SUM(i.amount)', true ),
				'cost'     => array( __( 'Cost', 'flx-donate' ), "SUM(CASE WHEN $same THEN i.cost ELSE 0 END)", true ),
				'margin'   => array( __( 'Margin', 'flx-donate' ), "SUM(i.amount - CASE WHEN $same THEN i.cost ELSE 0 END)", true ),
			);
		}
		return array(
			'count'    => array( __( 'Donations', 'flx-donate' ), 'COUNT(*)', false ),
			'quantity' => array( __( 'Quantity', 'flx-donate' ), 'SUM(d.quantity)', false ),
			'donors'   => array( __( 'Donors', 'flx-donate' ), 'COUNT(DISTINCT d.donor_id)', false ),
			'amount'   => array( __( 'Amount', 'flx-donate' ), 'SUM(d.amount)', true ),
			'average'  => array( __( 'Average', 'flx-donate' ), 'AVG(d.amount)', true ),
		);
	}

	public static function date_fields() {
		return array(
			'created' => array( __( 'Created', 'flx-donate' ), 'i.created_at' ),
			'done'    => array( __( 'Completed', 'flx-donate' ), 'i.done_at' ),
			'target'  => array( __( 'Target date', 'flx-donate' ), 'i.target_date' ),
		);
	}

	/**
	 * Check and normalise a definition.
	 *
	 * @param mixed $r      Decoded JSON.
	 * @param array $errors Filled with readable problems.
	 * @return array|null
	 */
	public static function clean( $r, array &$errors = array() ) {
		if ( ! is_array( $r ) ) {
			$errors[] = __( 'The report must be a JSON object.', 'flx-donate' );
			return null;
		}
		$id = sanitize_key( (string) ( $r['id'] ?? '' ) );
		if ( '' === $id ) {
			$errors[] = __( 'The report needs an "id" (letters, digits, dash).', 'flx-donate' );
		}
		$source = (string) ( $r['source'] ?? 'donations' );
		if ( ! isset( self::sources()[ $source ] ) ) {
			/* translators: %s: value given */
			$errors[] = sprintf( __( 'Unknown "source":%s. Use donations or items.', 'flx-donate' ), $source );
			$source   = 'donations';
		}
		$dims  = self::dims( $source );
		$group = array();
		foreach ( (array) ( $r['group'] ?? array() ) as $g ) {
			$g = (string) $g;
			if ( ! isset( $dims[ $g ] ) ) {
				/* translators: %s: value given */
				$errors[] = sprintf( __( 'Unknown "group" value:%s', 'flx-donate' ), $g );
			} elseif ( ! in_array( $g, $group, true ) ) {
				$group[] = $g;
			}
		}
		if ( count( $group ) > 2 ) {
			$errors[] = __( 'Group by at most two dimensions.', 'flx-donate' );
			$group    = array_slice( $group, 0, 2 );
		}
		$mets    = self::metrics( $source );
		$metrics = array();
		foreach ( (array) ( $r['metrics'] ?? array( 'count' ) ) as $m ) {
			$m = (string) $m;
			if ( ! isset( $mets[ $m ] ) ) {
				/* translators: %s: value given */
				$errors[] = sprintf( __( 'Unknown "metrics" value:%s', 'flx-donate' ), $m );
			} elseif ( ! in_array( $m, $metrics, true ) ) {
				$metrics[] = $m;
			}
		}
		if ( ! $metrics ) {
			$metrics = array( 'count' );
		}
		$f      = is_array( $r['filter'] ?? null ) ? $r['filter'] : array();
		$filter = array();
		foreach ( array( 'type_ids', 'partner_ids', 'source_ids' ) as $k ) {
			if ( ! empty( $f[ $k ] ) ) {
				$filter[ $k ] = array_values( array_filter( array_map( 'absint', (array) $f[ $k ] ) ) );
			}
		}
		if ( ! empty( $f['type_slugs'] ) ) {
			$filter['type_slugs'] = array_values( array_filter( array_map( 'sanitize_title', (array) $f['type_slugs'] ) ) );
		}
		if ( ! empty( $f['status'] ) ) {
			$filter['status'] = array_values( array_filter( array_map( 'sanitize_key', (array) $f['status'] ) ) );
		}
		if ( ! empty( $f['currency'] ) && preg_match( '/^[A-Z]{3}$/', strtoupper( (string) $f['currency'] ) ) ) {
			$filter['currency'] = strtoupper( (string) $f['currency'] );
		}
		foreach ( array( 'paid', 'tracked' ) as $k ) {
			if ( isset( $f[ $k ] ) ) {
				$filter[ $k ] = (bool) $f[ $k ];
			}
		}
		$period = (string) ( $r['period'] ?? 'this_year' );
		$sort   = (string) ( $r['sort'] ?? '' );
		$sk     = ltrim( $sort, '-' );
		if ( '' !== $sort && ! in_array( $sk, $group, true ) && ! in_array( $sk, $metrics, true ) ) {
			/* translators: %s: value given */
			$errors[] = sprintf( __( '"sort" must be one of the group or metrics values:%s', 'flx-donate' ), $sort );
			$sort     = '';
		}
		$date = (string) ( $r['date'] ?? 'created' );
		return array(
			'schema'      => self::SCHEMA,
			'id'          => $id,
			'title'       => $r['title'] ?? $id,
			'description' => $r['description'] ?? '',
			'source'      => $source,
			'group'       => $group,
			'metrics'     => $metrics,
			'filter'      => $filter,
			'date'        => isset( self::date_fields()[ $date ] ) ? $date : 'created',
			'period'      => isset( self::periods()[ $period ] ) ? $period : 'this_year',
			'sort'        => $sort,
			'limit'       => max( 1, min( self::MAX, (int) ( $r['limit'] ?? 200 ) ) ),
			'chart'       => 'bar' === ( $r['chart'] ?? '' ) ? 'bar' : '',
			'requires'    => 'track' === ( $r['requires'] ?? '' ) || 'items' === $source ? 'track' : '',
			'order'       => (int) ( $r['order'] ?? 50 ),
		);
	}

	private static function load_file( $path ) {
		if ( ! is_string( $path ) || ! is_readable( $path ) ) {
			return null;
		}
		return json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/** Shipped and plugin-provided definitions: id => raw array. */
	public static function builtin() {
		$out = array();
		foreach ( (array) glob( FLXB_DIR . 'assets/reports/*.json' ) as $file ) {
			$r = self::load_file( $file );
			if ( is_array( $r ) && ! empty( $r['id'] ) ) {
				$out[ sanitize_key( $r['id'] ) ] = $r;
			}
		}
		foreach ( (array) apply_filters( 'flxb_reports', array() ) as $k => $r ) {
			$r = is_string( $r ) ? self::load_file( $r ) : $r;
			if ( is_array( $r ) ) {
				$id         = sanitize_key( (string) ( $r['id'] ?? $k ) );
				$r['id']    = $id;
				$out[ $id ] = $r;
			}
		}
		return $out;
	}

	/** Site's own definitions (edited copies and new reports): id => raw array. */
	public static function custom() {
		$o = get_option( self::OPTION, array() );
		return is_array( $o ) ? $o : array();
	}

	/** All usable reports, cleaned, sorted: id => def (+ builtin, edited flags). */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$built = self::builtin();
		$own   = self::custom();
		$out   = array();
		foreach ( $built + $own as $id => $unused ) {
			$raw = $own[ $id ] ?? $built[ $id ];
			$e   = array();
			$r   = self::clean( $raw, $e );
			if ( ! $r || '' === $r['id'] ) {
				continue;
			}
			if ( 'track' === $r['requires'] && ! flxb_track_enabled() ) {
				continue;
			}
			$r['builtin'] = isset( $built[ $id ] );
			$r['edited']  = isset( $own[ $id ] );
			$out[ $id ]   = $r;
		}
		uasort(
			$out,
			function ( $a, $b ) {
				return array( $a['order'], self::text( $a['title'] ) ) <=> array( $b['order'], self::text( $b['title'] ) );
			}
		);
		self::$cache = $out;
		return $out;
	}

	public static function get( $id ) {
		$all = self::all();
		return $all[ sanitize_key( (string) $id ) ] ?? null;
	}

	/** Raw JSON-able definition as the editor shows it. */
	public static function raw( $id ) {
		$own = self::custom();
		if ( isset( $own[ $id ] ) ) {
			return $own[ $id ];
		}
		$b = self::builtin();
		return $b[ $id ] ?? null;
	}

	/**
	 * Save a definition from JSON text (or an array).
	 *
	 * @return string|WP_Error Report id.
	 */
	public static function save( $json, $old_id = '' ) {
		$raw = is_array( $json ) ? $json : json_decode( (string) $json, true );
		if ( null === $raw ) {
			/* translators: %s: JSON error message */
			return new WP_Error( 'flxb_json', sprintf( __( 'The JSON could not be read:%s', 'flx-donate' ), json_last_error_msg() ) );
		}
		$e = array();
		$r = self::clean( $raw, $e );
		if ( $e ) {
			return new WP_Error( 'flxb_report', implode( ' ', $e ) );
		}
		$own = self::custom();
		if ( '' !== $old_id && $old_id !== $r['id'] ) {
			unset( $own[ $old_id ] );
		}
		$raw['id']         = $r['id'];
		$raw['schema']     = self::SCHEMA;
		$own[ $r['id'] ]   = $raw;
		update_option( self::OPTION, $own, false );
		self::$cache = null;
		return $r['id'];
	}

	/** Remove the site's copy: a shipped report goes back to its original, a new one is gone. */
	public static function delete( $id ) {
		$own = self::custom();
		unset( $own[ $id ] );
		update_option( self::OPTION, $own, false );
		self::$cache = null;
	}

	/** Type ids a filter names (ids and portable slugs). */
	private static function type_ids( array $f ) {
		$ids = (array) ( $f['type_ids'] ?? array() );
		foreach ( (array) ( $f['type_slugs'] ?? array() ) as $slug ) {
			$t     = FLXB_Types::get_by_slug( $slug );
			$ids[] = $t ? (int) $t['id'] : -1; // an unknown slug matches nothing, not everything
		}
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Run a report.
	 *
	 * @param array $r    Cleaned definition.
	 * @param array $args from, to (Y-m-d; '' = open). Missing → the report's period.
	 * @return array { cols, rows, total: [metric => v] (money mixed, use totals), totals: [currency => [metric => v]], from, to, cut }
	 */
	public static function run( array $r, array $args = array() ) {
		global $wpdb;
		list( $from, $to ) = self::range( $r['period'] );
		if ( array_key_exists( 'from', $args ) || array_key_exists( 'to', $args ) ) {
			$from = (string) ( $args['from'] ?? '' );
			$to   = (string) ( $args['to'] ?? '' );
		}
		$items = 'items' === $r['source'];
		$dims  = self::dims( $r['source'] );
		$mets  = self::metrics( $r['source'] );
		$datec = $items ? self::date_fields()[ $r['date'] ][1] : 'd.created_at';
		$money = false;
		foreach ( $r['metrics'] as $m ) {
			$money = $money || $mets[ $m ][2];
		}
		$group = $r['group'];
		if ( $money && ! in_array( 'currency', $group, true ) ) {
			$group[] = 'currency'; // amounts in different currencies are never added together
		}

		$where = array( "d.status <> 'trash'" );
		$vals  = array();
		$f     = $r['filter'];
		if ( ! empty( $f['paid'] ) ) {
			$where[] = "d.status IN ('" . implode( "','", array_map( 'esc_sql', FLXB_Donations::PAID ) ) . "')";
		}
		$tids = self::type_ids( $f );
		if ( $tids ) {
			$where[] = ( $items ? 'i' : 'd' ) . '.type_id IN (' . implode( ',', $tids ) . ')';
		}
		if ( ! empty( $f['status'] ) ) {
			$where[] = ( $items ? 'i' : 'd' ) . ".status IN ('" . implode( "','", array_map( 'esc_sql', $f['status'] ) ) . "')";
		}
		if ( ! empty( $f['currency'] ) ) {
			$where[] = ( $items ? 'i' : 'd' ) . '.currency = %s';
			$vals[]  = $f['currency'];
		}
		if ( $items ) {
			if ( ! isset( $f['tracked'] ) || $f['tracked'] ) {
				$where[] = "i.code <> ''";
			}
			if ( ! empty( $f['partner_ids'] ) ) {
				$where[] = 'i.partner_id IN (' . implode( ',', $f['partner_ids'] ) . ')';
			}
			if ( ! empty( $f['source_ids'] ) ) {
				$where[] = 'i.source_id IN (' . implode( ',', $f['source_ids'] ) . ')';
			}
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
			$where[] = "$datec >= %s";
			$vals[]  = $from . ( 'i.target_date' === $datec ? '' : ' 00:00:00' );
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
			$where[] = "$datec <= %s";
			$vals[]  = $to . ( 'i.target_date' === $datec ? '' : ' 23:59:59' );
		}
		if ( 'i.created_at' !== $datec && 'd.created_at' !== $datec ) {
			$where[] = "$datec IS NOT NULL";
		}

		$fromsql = $items
			? flxb_table( 'items' ) . ' i LEFT JOIN ' . flxb_table( 'donations' ) . ' d ON d.id = i.donation_id LEFT JOIN ' . flxb_table( 'partners' ) . ' p ON p.id = i.partner_id'
			: flxb_table( 'donations' ) . ' d';
		$wsql    = implode( ' AND ', $where );

		$sel_g = array();
		foreach ( $group as $k => $g ) {
			$sel_g[] = str_replace( '%DATE%', $datec, $dims[ $g ][1] ) . ' AS g' . $k;
		}
		$sel_m = array();
		foreach ( $r['metrics'] as $m ) {
			$sel_m[] = $mets[ $m ][1] . ' AS m_' . $m;
		}
		$gb  = $sel_g ? ' GROUP BY ' . implode( ',', array_map( function ( $k ) { return 'g' . $k; }, array_keys( $group ) ) ) : '';
		$sql = 'SELECT ' . implode( ',', array_merge( $sel_g, $sel_m ) ) . " FROM $fromsql WHERE $wsql" . $gb;
		$rows = (array) $wpdb->get_results( $vals ? $wpdb->prepare( $sql, $vals ) : $sql, ARRAY_A ); // phpcs:ignore WordPress.DB

		$out   = array();
		foreach ( $rows as $row ) {
			$x = array();
			foreach ( $group as $k => $g ) {
				$x[ $g ] = (string) $row[ 'g' . $k ];
			}
			foreach ( $r['metrics'] as $m ) {
				$x[ $m ] = (float) $row[ 'm_' . $m ];
			}
			$out[] = $x;
		}

		/* Totals: counts over everything (distinct counts stay right), money per currency. */
		$tsql  = 'SELECT ' . implode( ',', $sel_m ) . " FROM $fromsql WHERE $wsql";
		$trow  = (array) $wpdb->get_row( $vals ? $wpdb->prepare( $tsql, $vals ) : $tsql, ARRAY_A ); // phpcs:ignore WordPress.DB
		$total = array();
		foreach ( $r['metrics'] as $m ) {
			$total[ $m ] = (float) ( $trow[ 'm_' . $m ] ?? 0 );
		}
		$totals = array();
		if ( $money ) {
			$tsql = 'SELECT ' . implode( ',', $sel_m ) . ', ' . ( $items ? 'i' : 'd' ) . ".currency AS cur FROM $fromsql WHERE $wsql GROUP BY cur";
			foreach ( (array) $wpdb->get_results( $vals ? $wpdb->prepare( $tsql, $vals ) : $tsql, ARRAY_A ) as $t ) { // phpcs:ignore WordPress.DB
				foreach ( $r['metrics'] as $m ) {
					$totals[ (string) $t['cur'] ][ $m ] = (float) $t[ 'm_' . $m ];
				}
			}
		}

		/* Sort: by the given key, else by the time dimension, else biggest first. */
		$sort = $r['sort'];
		if ( '' === $sort ) {
			$time = array_values( array_intersect( $r['group'], array( 'day', 'month', 'year' ) ) );
			$sort = $time ? $time[0] : ( $r['group'] ? '-' . $r['metrics'][0] : '' );
		}
		if ( '' !== $sort ) {
			$desc = '-' === $sort[0];
			$sk   = ltrim( $sort, '-' );
			usort(
				$out,
				function ( $a, $b ) use ( $sk, $desc ) {
					$c = is_float( $a[ $sk ] ) ? $a[ $sk ] <=> $b[ $sk ] : strnatcasecmp( (string) $a[ $sk ], (string) $b[ $sk ] );
					return $desc ? -$c : $c;
				}
			);
		}
		$cut = count( $out ) > $r['limit'];
		$out = array_slice( $out, 0, $r['limit'] );

		$cols = array();
		foreach ( $group as $g ) {
			$cols[ $g ] = array( 'label' => $dims[ $g ][0], 'kind' => $dims[ $g ][2], 'dim' => true );
		}
		foreach ( $r['metrics'] as $m ) {
			$cols[ $m ] = array( 'label' => $mets[ $m ][0], 'kind' => $mets[ $m ][2] ? 'money' : 'number', 'dim' => false );
		}
		return array( 'cols' => $cols, 'rows' => $out, 'total' => $total, 'totals' => $totals, 'from' => $from, 'to' => $to, 'cut' => $cut );
	}

	/** A dimension value as people read it. */
	public static function show( $kind, $v ) {
		$v = (string) $v;
		switch ( $kind ) {
			case 'type':
				$t = (int) $v ? FLXB_Types::get( (int) $v ) : null;
				return $t ? (string) $t['title'] : '—';
			case 'item_status':
				$s = FLXB_Track::statuses();
				return $s[ $v ] ?? ( '' !== $v ? $v : '—' );
			case 'donation_status':
				return flxb_status_label( $v );
			case 'kind':
				return flxb_type_label( $v );
			case 'partner':
				return (int) $v ? FLXB_Partners::name( (int) $v ) : '—';
			case 'provider':
				return '' !== $v ? flxb_provider_label( $v ) : '—';
			case 'user':
				$u = (int) $v ? get_userdata( (int) $v ) : null;
				return $u ? $u->display_name : '—';
			case 'month':
				return preg_match( '/^\d{4}-\d{2}$/', $v ) ? flx_date( strtotime( $v . '-15' ), 'F Y' ) : '—';
			case 'day':
				return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? flx_date( strtotime( $v . ' 12:00' ) ) : '—';
			default:
				return '' !== $v ? $v : '—';
		}
	}
}
