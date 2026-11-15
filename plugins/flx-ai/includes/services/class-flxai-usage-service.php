<?php
/**
 * Usage: spending against the limits, reports, billing, the request log and the connected systems.
 * Read-only apart from refreshing the OpenRouter credit and switching a system on or off. The classic
 * pages, the REST routes and GraphQL read these shapes; permission checks stay with the caller
 * (manage_options).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

final class FLXAI_Usage_Service {

	public const CACHE_TAG = 'flx-ai:usage';

	/** Days the overview and the log can look back (classic pages offer fewer; the API takes up to this). */
	public const MAX_DAYS = 365;

	/**
	 * Spending against the day, week and month limits.
	 *
	 * @return list<FLXAI_Budget_Period>
	 */
	public static function budget(): array {
		$out = array();
		foreach ( FLXAI_Cast::arr( flxai_budget_status( true ) ) as $key => $row ) {
			$r     = FLXAI_Cast::arr( $row );
			$out[] = new FLXAI_Budget_Period(
				(string) $key,
				FLXAI_Cast::str( $r['label'] ?? '' ),
				FLXAI_Cast::float( $r['limit'] ?? 0 ),
				FLXAI_Cast::float( $r['spent'] ?? 0 ),
				FLXAI_Cast::float( $r['pct'] ?? 0 ),
				FLXAI_Cast::bool( $r['over'] ?? false ),
				FLXAI_Cast::int( $r['resets'] ?? 0 )
			);
		}
		return $out;
	}

	/**
	 * Usage report of the last N days.
	 *
	 * @param int    $days   1–365.
	 * @param string $source Only this plugin; empty = all.
	 */
	public static function report( int $days = 30, string $source = '' ): FLXAI_Report {
		$days = max( 1, min( self::MAX_DAYS, $days ) );
		return self::build_report( FLXAI_Cast::arr( flxai_report( $days, sanitize_key( $source ) ) ), array() );
	}

	/**
	 * Report rows as typed values.
	 *
	 * @param array<array-key,mixed>             $rep         flxai_report() result.
	 * @param array<array-key,mixed>             $by_provider flxai_billing() provider rows.
	 */
	private static function build_report( array $rep, array $by_provider ): FLXAI_Report {
		$t      = FLXAI_Cast::arr( $rep['totals'] ?? array() );
		$totals = new FLXAI_Report_Totals(
			FLXAI_Cast::int( $t['calls'] ?? 0 ),
			FLXAI_Cast::int( $t['tools'] ?? 0 ),
			FLXAI_Cast::int( $t['in_tok'] ?? 0 ),
			FLXAI_Cast::int( $t['out_tok'] ?? 0 ),
			FLXAI_Cast::int( $t['cr'] ?? 0 ),
			FLXAI_Cast::int( $t['cw'] ?? 0 ),
			FLXAI_Cast::float( $t['cost'] ?? 0 ),
			FLXAI_Cast::float( $t['full_cost'] ?? 0 ),
			FLXAI_Cast::float( $t['saved'] ?? 0 ),
			FLXAI_Cast::int( $t['model_ms'] ?? 0 ),
			FLXAI_Cast::int( $t['tool_ms'] ?? 0 ),
			FLXAI_Cast::int( $t['errors'] ?? 0 )
		);
		$daily  = array();
		foreach ( FLXAI_Cast::arr( $rep['daily'] ?? array() ) as $d ) {
			$d       = FLXAI_Cast::arr( $d );
			$daily[] = new FLXAI_Report_Day(
				FLXAI_Cast::str( $d['date'] ?? '' ),
				FLXAI_Cast::float( $d['cost'] ?? 0 ),
				FLXAI_Cast::int( $d['in'] ?? 0 ),
				FLXAI_Cast::int( $d['out'] ?? 0 ),
				FLXAI_Cast::int( $d['cr'] ?? 0 ),
				FLXAI_Cast::int( $d['model_ms'] ?? 0 ),
				FLXAI_Cast::int( $d['tool_ms'] ?? 0 ),
				FLXAI_Cast::int( $d['calls'] ?? 0 ),
				FLXAI_Cast::int( $d['tools'] ?? 0 ),
				FLXAI_Cast::int( $d['errors'] ?? 0 )
			);
		}
		$tools = array();
		foreach ( FLXAI_Cast::arr( $rep['by_tool'] ?? array() ) as $r ) {
			$r       = FLXAI_Cast::arr( $r );
			$tools[] = new FLXAI_Tool_Stat(
				FLXAI_Cast::str( $r['k'] ?? '' ),
				FLXAI_Cast::str( $r['label'] ?? ( $r['k'] ?? '' ) ),
				FLXAI_Cast::str( $r['source'] ?? '' ),
				FLXAI_Cast::int( $r['n'] ?? 0 ),
				FLXAI_Cast::int( $r['avg_ms'] ?? 0 ),
				FLXAI_Cast::int( $r['max_ms'] ?? 0 ),
				FLXAI_Cast::int( $r['total_ms'] ?? 0 ),
				FLXAI_Cast::int( $r['errors'] ?? 0 )
			);
		}
		return new FLXAI_Report(
			FLXAI_Cast::int( $rep['from'] ?? 0 ),
			FLXAI_Cast::int( $rep['to'] ?? 0 ),
			$totals,
			$daily,
			self::rows( $rep['by_source'] ?? array() ),
			self::rows( $rep['by_model'] ?? array() ),
			self::rows( $rep['by_purpose'] ?? array() ),
			$tools,
			self::rows( $by_provider, true )
		);
	}

	/**
	 * Breakdown rows as typed values.
	 *
	 * @param mixed $rows    Rows of flxai_report() / flxai_billing().
	 * @param bool  $billing The rows are provider rows (calls = COUNT, est = estimated rows).
	 * @return list<FLXAI_Breakdown_Row>
	 */
	private static function rows( $rows, bool $billing = false ): array {
		$out = array();
		foreach ( FLXAI_Cast::arr( $rows ) as $r ) {
			$r     = FLXAI_Cast::arr( $r );
			$key   = FLXAI_Cast::str( $r['k'] ?? '' );
			$out[] = new FLXAI_Breakdown_Row(
				$key,
				FLXAI_Cast::str( $r['label'] ?? $key ),
				FLXAI_Cast::int( $r['calls'] ?? 0 ),
				FLXAI_Cast::int( $r['tools'] ?? 0 ),
				FLXAI_Cast::float( $r['cost'] ?? 0 ),
				FLXAI_Cast::float( $r['full_cost'] ?? 0 ),
				FLXAI_Cast::int( $r['in_tok'] ?? 0 ),
				FLXAI_Cast::int( $r['out_tok'] ?? 0 ),
				FLXAI_Cast::int( $r['cr'] ?? 0 ),
				FLXAI_Cast::int( $r['model_ms'] ?? 0 ),
				FLXAI_Cast::int( $r['tool_ms'] ?? 0 ),
				FLXAI_Cast::int( $r['errors'] ?? 0 ),
				$billing && FLXAI_Cast::int( $r['est'] ?? 0 ) > 0
			);
		}
		return $out;
	}

	/**
	 * The last N months with their totals (billing list).
	 *
	 * @return list<FLXAI_Month>
	 */
	public static function months( int $n = 12 ): array {
		$out = array();
		foreach ( FLXAI_Cast::arr( flxai_months( max( 1, min( 36, $n ) ) ) ) as $m ) {
			$m     = FLXAI_Cast::arr( $m );
			$out[] = new FLXAI_Month(
				FLXAI_Cast::str( $m['ym'] ?? '' ),
				FLXAI_Cast::str( $m['label'] ?? '' ),
				FLXAI_Cast::float( $m['cost'] ?? 0 ),
				FLXAI_Cast::float( $m['saved'] ?? 0 ),
				FLXAI_Cast::int( $m['calls'] ?? 0 )
			);
		}
		return $out;
	}

	/**
	 * A billing month: report, provider split, the month list and the credit state.
	 *
	 * @param string $ym Y-m; empty or invalid = this month.
	 */
	public static function billing( string $ym = '' ): FLXAI_Billing {
		$bill = FLXAI_Cast::arr( flxai_billing( $ym ) );
		return new FLXAI_Billing(
			FLXAI_Cast::str( $bill['ym'] ?? wp_date( 'Y-m' ) ),
			self::build_report( $bill, FLXAI_Cast::arr( $bill['by_provider'] ?? array() ) ),
			self::months( 12 ),
			self::credits()
		);
	}

	/**
	 * OpenRouter account state from the last refresh (null when never refreshed).
	 */
	public static function credits(): ?FLXAI_Credits {
		$c = get_option( 'flxai_or_credits', null );
		return is_array( $c ) ? self::credits_from( $c ) : null;
	}

	/**
	 * @param array<array-key,mixed> $c Stored state.
	 */
	private static function credits_from( array $c ): FLXAI_Credits {
		$f = static fn( $v ): ?float => null === $v ? null : FLXAI_Cast::float( $v );
		return new FLXAI_Credits(
			FLXAI_Cast::int( $c['checked'] ?? 0 ),
			$f( $c['usage'] ?? null ),
			$f( $c['limit'] ?? null ),
			$f( $c['remaining'] ?? null ),
			$f( $c['credits'] ?? null )
		);
	}

	/**
	 * Asks OpenRouter for the key and credit state and stores it.
	 *
	 * @return FLXAI_Credits|WP_Error
	 */
	public static function refresh_credits() {
		$r = flxai_openrouter_credits();
		if ( $r instanceof WP_Error ) {
			$r->add_data( array( 'graphql_code' => 'INVALID_INPUT' ) );
			return $r;
		}
		return self::credits_from( FLXAI_Cast::arr( $r ) );
	}

	/**
	 * Log rows of a period, newest first.
	 *
	 * @param int    $days   1–365.
	 * @param string $kind   model | tool | '' (both).
	 * @param string $source Only this system; empty = all.
	 * @param bool   $errors Only failed rows.
	 * @return array{items: list<FLXAI_Log_Row>, total: int}
	 */
	public static function logs( int $days, string $kind, string $source, bool $errors, int $offset, int $limit ): array {
		global $wpdb;
		$days   = max( 1, min( self::MAX_DAYS, $days ) );
		$t      = flxai_table( 'log' );
		$b      = flxai_table( 'log_body' );
		$where  = $wpdb->prepare( 'l.ts >= %d', time() - $days * DAY_IN_SECONDS );
		if ( in_array( $kind, array( 'model', 'tool' ), true ) ) {
			$where .= $wpdb->prepare( ' AND l.kind = %s', $kind );
		}
		$source = sanitize_key( $source );
		if ( '' !== $source ) {
			$where .= $wpdb->prepare( ' AND l.source = %s', $source );
		}
		if ( $errors ) {
			$where .= ' AND l.ok = 0';
		}
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} l WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( "SELECT l.*, (b.log_id IS NOT NULL) AS has_body FROM {$t} l LEFT JOIN {$b} b ON b.log_id = l.id WHERE {$where} ORDER BY l.id DESC LIMIT " . max( 1, $limit ) . ' OFFSET ' . max( 0, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$users   = array();
		$items   = array();
		foreach ( FLXAI_Cast::arr( $rows ) as $row ) {
			$r   = FLXAI_Cast::arr( $row );
			$uid = FLXAI_Cast::int( $r['user_id'] ?? 0 );
			if ( $uid > 0 && ! isset( $users[ $uid ] ) ) {
				$u             = get_userdata( $uid );
				$users[ $uid ] = $u ? $u->display_name : '#' . $uid;
			}
			$src   = FLXAI_Cast::str( $r['source'] ?? '' );
			$purp  = FLXAI_Cast::str( $r['purpose'] ?? '' );
			$err   = FLXAI_Cast::str( $r['error'] ?? '' );
			$model = FLXAI_Cast::str( $r['model'] ?? '' );
			$items[] = new FLXAI_Log_Row(
				FLXAI_Cast::int( $r['id'] ?? 0 ),
				FLXAI_Cast::int( $r['ts'] ?? 0 ),
				FLXAI_Cast::str( $r['kind'] ?? 'model' ),
				$src,
				self::source_label( $src ),
				$purp,
				'' !== $purp ? FLXAI_Cast::str( flxai_purpose_label( $purp ) ) : '',
				FLXAI_Cast::int( $r['chat_id'] ?? 0 ),
				$uid,
				$uid > 0 ? ( $users[ $uid ] ?? '' ) : '',
				FLXAI_Cast::str( $r['provider'] ?? '' ),
				$model,
				'' !== $model ? FLXAI_Cast::str( flxai_model_label( $model ) ) : '',
				FLXAI_Cast::str( $r['tool'] ?? '' ),
				FLXAI_Cast::int( $r['in_tok'] ?? 0 ),
				FLXAI_Cast::int( $r['out_tok'] ?? 0 ),
				FLXAI_Cast::int( $r['cache_read'] ?? 0 ),
				FLXAI_Cast::int( $r['cache_write'] ?? 0 ),
				FLXAI_Cast::float( $r['cost'] ?? 0 ),
				FLXAI_Cast::int( $r['est'] ?? 0 ) > 0,
				FLXAI_Cast::int( $r['ms'] ?? 0 ),
				FLXAI_Cast::int( $r['ok'] ?? 1 ) > 0,
				$err,
				self::error_label( $err ),
				FLXAI_Cast::int( $r['has_body'] ?? 0 ) > 0
			);
		}
		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Name of a log source: connected systems first, then the known sources.
	 */
	public static function source_label( string $source ): string {
		if ( '' === $source ) {
			return '';
		}
		foreach ( FLXAI_Cast::arr( flxai_known_systems() ) as $id => $k ) {
			if ( (string) $id === $source ) {
				$label = FLXAI_Cast::str( FLXAI_Cast::arr( $k )['label'] ?? '' );
				if ( '' !== $label ) {
					return $label;
				}
			}
		}
		return FLXAI_Cast::str( flxai_source_label( $source ) );
	}

	/**
	 * Failure text of a log row.
	 */
	public static function error_label( string $error ): string {
		if ( '' === $error ) {
			return '';
		}
		if ( 'limit' === $error ) {
			return __( 'Spending limit reached', 'flx-ai' );
		}
		if ( 'off' === $error ) {
			return __( 'Off (Connected systems)', 'flx-ai' );
		}
		return $error;
	}

	/**
	 * Systems a log can be filtered by: the known plugins plus every system seen in the last 90 days.
	 *
	 * @return list<array{source: string, label: string}>
	 */
	public static function log_sources(): array {
		$out = array();
		foreach ( FLXAI_Cast::arr( flxai_sources() ) as $id => $label ) {
			$out[ (string) $id ] = FLXAI_Cast::str( $label );
		}
		foreach ( FLXAI_Cast::arr( flxai_systems() ) as $sys ) {
			$s                         = FLXAI_Cast::arr( $sys );
			$out[ FLXAI_Cast::str( $s['source'] ?? '' ) ] = FLXAI_Cast::str( $s['label'] ?? '' );
		}
		$rows = array();
		foreach ( $out as $id => $label ) {
			if ( '' !== $id ) {
				$rows[] = array(
					'source' => $id,
					'label'  => $label,
				);
			}
		}
		return $rows;
	}

	/**
	 * The stored content of a log row (shortened, personal data masked).
	 *
	 * @return FLXAI_Log_Body|WP_Error
	 */
	public static function log_body( int $id ) {
		$b = flxai_log_body_get( $id );
		if ( ! is_array( $b ) ) {
			return new WP_Error( 'flxai_no_body', __( 'Content was not stored for this record.', 'flx-ai' ), array( 'graphql_code' => 'NOT_FOUND' ) );
		}
		$req = array();
		foreach ( FLXAI_Cast::arr( $b['req'] ?? array() ) as $m ) {
			$m     = FLXAI_Cast::arr( $m );
			$req[] = new FLXAI_Log_Message( FLXAI_Cast::str( $m[0] ?? '' ), FLXAI_Cast::str( $m[1] ?? '' ) );
		}
		$res = FLXAI_Cast::arr( $b['res'] ?? array() );
		return new FLXAI_Log_Body(
			$id,
			$req,
			FLXAI_Cast::str( $res['text'] ?? '' ),
			FLXAI_Cast::strings( $res['tools'] ?? array() ),
			FLXAI_Cast::str( $res['error'] ?? '' )
		);
	}

	/**
	 * Connected systems with their 30-day usage.
	 *
	 * @return list<FLXAI_System>
	 */
	public static function systems(): array {
		$out = array();
		foreach ( FLXAI_Cast::arr( flxai_systems() ) as $row ) {
			$r       = FLXAI_Cast::arr( $row );
			$src     = FLXAI_Cast::str( $r['source'] ?? '' );
			$model   = FLXAI_Cast::str( $r['model'] ?? '' );
			$out[]   = new FLXAI_System(
				$src,
				FLXAI_Cast::str( $r['label'] ?? $src ),
				FLXAI_Cast::bool( $r['installed'] ?? false ),
				FLXAI_Cast::bool( $r['active'] ?? false ),
				FLXAI_Cast::bool( $r['on'] ?? false ),
				$model,
				'' !== $model ? FLXAI_Cast::str( flxai_model_label( $model ) ) : '',
				FLXAI_Cast::int( $r['calls'] ?? 0 ),
				FLXAI_Cast::int( $r['tools'] ?? 0 ),
				FLXAI_Cast::int( $r['tok'] ?? 0 ),
				FLXAI_Cast::float( $r['cost'] ?? 0 ),
				FLXAI_Cast::int( $r['errors'] ?? 0 ),
				FLXAI_Cast::ts( $r['last'] ?? 0 ),
				FLXAI_Cast::str( $r['page'] ?? '' ),
				function_exists( 'flx_ai_registry' ) && ( ! empty( FLXAI_Cast::arr( FLXAI_Cast::arr( flx_ai_registry() )['settings'] ?? array() )[ $src ] ) || isset( FLXAI_Cast::arr( FLXAI_Cast::arr( flx_ai_registry() )['plugins'] ?? array() )[ $src ] ) )
			);
		}
		return $out;
	}

	/**
	 * Switches systems on or off. Sources that are not listed keep their state.
	 *
	 * @param array<string,bool> $states source => on.
	 * @return list<FLXAI_System>
	 */
	public static function set_systems( array $states ): array {
		$off = array();
		foreach ( FLXAI_Cast::strings( flxai_setting( 'sources_off', array() ) ) as $s ) {
			$off[ $s ] = true;
		}
		foreach ( $states as $source => $on ) {
			$source = sanitize_key( (string) $source );
			if ( '' === $source ) {
				continue;
			}
			if ( $on ) {
				unset( $off[ $source ] );
			} else {
				$off[ $source ] = true;
			}
		}
		flxai_update_settings( array( 'sources_off' => array_map( 'strval', array_keys( $off ) ) ) );
		self::changed();
		return self::systems();
	}

	/** Drops the cached reads (GraphQL tag). */
	public static function changed(): void {
		if ( function_exists( 'flx_gql_purge' ) ) {
			flx_gql_purge( array( self::CACHE_TAG ) );
		}
	}
}
