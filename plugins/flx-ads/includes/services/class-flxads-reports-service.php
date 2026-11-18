<?php
/**
 * Flux Ads: report, ad spend, donor value, send log and the ad link builder. GraphQL
 * and Flux AI read the same legacy classes; this service gives GraphQL typed shapes of what the
 * screen shows. Permission checks stay with the caller (flxads_can).
 *
 * Errors are WP_Error with `graphql_code` INVALID_INPUT / NOT_FOUND and the input `field`.
 *
 * @package FLX_Ads
 * @phpstan-import-type FlxadsMoney from FLXADS_V
 *
 * @phpstan-type FlxadsFilter array<string, string>
 * @phpstan-type FlxadsOption array{key: string, label: string}
 * @phpstan-type FlxadsReportTotals array{count: int, amount: FlxadsMoney, adsCount: int, adsAmount: FlxadsMoney, adsPercent: float|null, newCount: int, mixed: bool, hasSpend: bool, spend: FlxadsMoney|null, clicks: int, roas: float|null, spendIncome: FlxadsMoney, spendCount: int, spendNew: int, cpa: FlxadsMoney|null, cpn: FlxadsMoney|null}
 * @phpstan-type FlxadsReportRow array{key: string, label: string, channel: string, channelLabel: string, count: int, amount: FlxadsMoney, amounts: list<FlxadsMoney>, otherCurrency: bool, newCount: int, share: float, hasSpend: bool, spend: FlxadsMoney|null, cpa: FlxadsMoney|null, roas: float|null, roasLevel: string}
 * @phpstan-type FlxadsRecentOrder array{orderId: int, orderNumber: string, editUrl: string|null, paidAt: string, isDonation: bool, newPerson: bool, channel: string, summary: string, total: FlxadsMoney}
 * @phpstan-type FlxadsReport array{range: string, from: string, to: string, fromLabel: string, toLabel: string, kind: string, by: string, byLabel: string, dimensions: list<FlxadsOption>, channel: string|null, channelLabel: string|null, base: string, totals: FlxadsReportTotals, showSpendColumns: bool, rows: list<FlxadsReportRow>, recent: list<FlxadsRecentOrder>}
 * @phpstan-type FlxadsMetaStatus array{ok: bool, at: string, message: string, rows: int, since: string, until: string, sinceLabel: string, untilLabel: string}
 * @phpstan-type FlxadsSpendCampaign array{name: string, channel: string, channelLabel: string, spend: FlxadsMoney}
 * @phpstan-type FlxadsManualSpend array{id: int, day: string, dayLabel: string, channel: string, channelLabel: string, campaign: string, ad: string, spend: FlxadsMoney}
 * @phpstan-type FlxadsSpendPage array{range: string, from: string, to: string, base: string, metaReady: bool, metaStatus: FlxadsMetaStatus|null, campaigns: list<FlxadsSpendCampaign>, total: FlxadsMoney, manual: list<FlxadsManualSpend>, channels: list<FlxadsOption>}
 * @phpstan-type FlxadsValueTotals array{newCount: int, adsNew: int, adsAvg: FlxadsMoney|null, cac: FlxadsMoney|null, spend: FlxadsMoney|null, ratio: float|null, rate: float|null}
 * @phpstan-type FlxadsValueRow array{key: string, label: string, channel: string, channelLabel: string, newCount: int, first: FlxadsMoney, ltv90: FlxadsMoney, ltv: FlxadsMoney, avg: FlxadsMoney|null, rate: float|null, hasSpend: bool, spend: FlxadsMoney|null, cac: FlxadsMoney|null, ratio: float|null, roasLevel: string, payback: int|null}
 * @phpstan-type FlxadsCohortRow array{month: string, label: string, newCount: int, cells: list<FlxadsMoney|null>}
 * @phpstan-type FlxadsValuePage array{range: string, from: string, to: string, kind: string, by: string, base: string, totals: FlxadsValueTotals, rows: list<FlxadsValueRow>, cohortMonths: int, cohort: list<FlxadsCohortRow>}
 * @phpstan-type FlxadsLogRow array{id: int, platform: string, platformLabel: string, orderId: int, orderNumber: string, editUrl: string|null, value: FlxadsMoney, status: string, statusLabel: string, reason: string, attempts: int, at: string, canRetry: bool}
 * @phpstan-type FlxadsSendLog array{sent: int, pending: int, failed: int, skipped: int, rows: list<FlxadsLogRow>}
 * @phpstan-type FlxadsLinkBuilder array{homeUrl: string, pages: list<string>, sources: list<FlxadsOption>, mediums: list<FlxadsOption>}
 */
final class FLXADS_Reports_Service {

	public const CACHE_TAG = 'flx-ads:report';

	/**
	 * Report filter as the screen sends it.
	 *
	 * @param FlxadsFilter $in         Raw filter.
	 * @param string       $default_by Default grouping.
	 * @return array{range: string, kind: string, channel: string, by: string, from: string, to: string}
	 */
	public static function clean_filter( array $in, string $default_by = 'channel', string $default_range = '30' ): array {
		$dims  = FLXADS_Report::dimensions();
		$by    = isset( $in['by'] ) && isset( $dims[ $in['by'] ] ) ? $in['by'] : $default_by;
		$kind  = isset( $in['kind'] ) && in_array( $in['kind'], array( 'all', 'donations', 'orders' ), true ) ? $in['kind'] : 'all';
		$range = isset( $in['range'] ) ? sanitize_key( $in['range'] ) : $default_range;
		$days  = array(
			'7'   => 7,
			'30'  => 30,
			'90'  => 90,
			'365' => 365,
		);
		if ( 'custom' === $range ) {
			$from = isset( $in['from'] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $in['from'] ) ? $in['from'] : '';
			$to   = isset( $in['to'] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $in['to'] ) ? $in['to'] : '';
		} else {
			$range = isset( $days[ $range ] ) ? $range : $default_range;
			$n     = $days[ $range ];
			$from  = FLXADS_V::s( wp_date( 'Y-m-d', time() - ( $n - 1 ) * DAY_IN_SECONDS ) );
			$to    = FLXADS_V::s( wp_date( 'Y-m-d' ) );
		}
		return array(
			'range'   => $range,
			'kind'    => $kind,
			'channel' => isset( $in['channel'] ) ? sanitize_text_field( $in['channel'] ) : '',
			'by'      => $by,
			'from'    => $from,
			'to'      => $to,
		);
	}

	/**
	 * @param array<string,string> $labels Key → label map.
	 * @return list<FlxadsOption>
	 */
	public static function options( array $labels ): array {
		$out = array();
		foreach ( $labels as $k => $l ) {
			$out[] = array(
				'key'   => (string) $k,
				'label' => (string) $l,
			);
		}
		return $out;
	}

	/**
	 * Group by choices.
	 *
	 * @return list<FlxadsOption>
	 */
	public static function dimensions(): array {
		return self::options( FLXADS_Report::dimensions() );
	}

	/**
	 * Channels, "no ad" included.
	 *
	 * @return list<FlxadsOption>
	 */
	public static function channels(): array {
		return self::options( flxads_channel_labels() );
	}

	/**
	 * Order edit link and number.
	 *
	 * @param int $order_id Order.
	 * @return array{number: string, url: string|null}
	 */
	private static function order_ref( int $order_id ): array {
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
		if ( $order instanceof WC_Order ) {
			return array(
				'number' => (string) $order->get_order_number(),
				'url'    => (string) $order->get_edit_order_url(),
			);
		}
		return array(
			'number' => (string) $order_id,
			'url'    => null,
		);
	}

	/**
	 * Ad report: totals, grouped rows and the latest payments from ads.
	 *
	 * @param FlxadsFilter $in Filter.
	 * @return FlxadsReport
	 */
	public static function report( array $in ): array {
		FLXADS_V::tag( self::CACHE_TAG );
		$f    = self::clean_filter( $in );
		$r    = FLXADS_Report::grouped( $f['by'], $f );
		$base = FLXADS_V::s( $r['base'] ?? '', FLXADS_Report::base_currency() );
		$t    = FLXADS_V::a( $r['totals'] ?? array() );
		$amt  = FLXADS_V::f( $t['amount'] ?? 0 );
		$ads  = FLXADS_V::f( $t['ads_amount'] ?? 0 );
		$sp   = FLXADS_V::f( $t['spend'] ?? 0 );
		$has  = $sp > 0;
		$cols = FLXADS_V::b( $r['spend'] ?? false ) && in_array( $f['by'], array( 'channel', 'campaign', 'content' ), true );

		$rows = array();
		foreach ( FLXADS_V::rows( $r['rows'] ?? array() ) as $row ) {
			$amounts = array();
			foreach ( FLXADS_V::a( $row['amounts'] ?? array() ) as $cur => $a ) {
				$amounts[] = FLXADS_V::money( $a, $cur );
			}
			$hasrow = array_key_exists( 'spend', $row );
			$roas   = $hasrow ? FLXADS_V::fn( $row['roas'] ?? null ) : null;
			$ch     = FLXADS_V::s( $row['channel'] ?? '' );
			$rows[] = array(
				'key'           => FLXADS_V::s( $row['key'] ?? '' ),
				'label'         => FLXADS_V::s( $row['label'] ?? '' ),
				'channel'       => $ch,
				'channelLabel'  => flxads_channel_label( $ch ),
				'count'         => FLXADS_V::i( $row['count'] ?? 0 ),
				'amount'        => FLXADS_V::money( $row['amount'] ?? 0, $base ),
				'amounts'       => $amounts,
				'otherCurrency' => count( $amounts ) > 1 || ! isset( FLXADS_V::a( $row['amounts'] ?? array() )[ $base ] ),
				'newCount'      => FLXADS_V::i( $row['new'] ?? 0 ),
				'share'         => FLXADS_V::f( $row['share'] ?? 0 ),
				'hasSpend'      => $hasrow,
				'spend'         => $hasrow ? FLXADS_V::moneyn( $row['spend'] ?? null, $base ) : null,
				'cpa'           => $hasrow ? FLXADS_V::moneyn( $row['cpa'] ?? null, $base ) : null,
				'roas'          => $roas,
				'roasLevel'     => FLXADS_V::roas_level( $roas ),
			);
		}

		$recent = array();
		foreach ( FLXADS_V::rows( FLXADS_Report::recent( $f + array( 'ads_only' => 1 ), 12 ) ) as $o ) {
			$ref      = self::order_ref( FLXADS_V::i( $o['order_id'] ?? 0 ) );
			$recent[] = array(
				'orderId'     => FLXADS_V::i( $o['order_id'] ?? 0 ),
				'orderNumber' => $ref['number'],
				'editUrl'     => $ref['url'],
				'paidAt'      => FLXADS_V::s( $o['paid_at'] ?? '' ),
				'isDonation'  => FLXADS_V::b( $o['is_donation'] ?? false ),
				'newPerson'   => FLXADS_V::b( $o['new_person'] ?? false ),
				'channel'     => FLXADS_V::s( $o['channel'] ?? '' ),
				'summary'     => FLXADS_V::s( FLXADS_CRM::summary( $o ) ),
				'total'       => FLXADS_V::money( $o['total'] ?? 0, $o['currency'] ?? '' ),
			);
		}

		$dims = FLXADS_Report::dimensions();
		$ch   = '' !== $f['channel'] ? $f['channel'] : null;
		return array(
			'range'            => $f['range'],
			'from'             => FLXADS_V::s( $r['from'] ?? $f['from'] ),
			'to'               => FLXADS_V::s( $r['to'] ?? $f['to'] ),
			'fromLabel'        => FLXADS_V::s( flxads_date( FLXADS_V::s( $r['from'] ?? $f['from'] ), 'date' ) ),
			'toLabel'          => FLXADS_V::s( flxads_date( FLXADS_V::s( $r['to'] ?? $f['to'] ), 'date' ) ),
			'kind'             => $f['kind'],
			'by'               => $f['by'],
			'byLabel'          => $dims[ $f['by'] ] ?? $f['by'],
			'dimensions'       => self::dimensions(),
			'channel'          => $ch,
			'channelLabel'     => null === $ch ? null : flxads_channel_label( '-' === $ch ? '' : $ch ),
			'base'             => $base,
			'totals'           => array(
				'count'       => FLXADS_V::i( $t['count'] ?? 0 ),
				'amount'      => FLXADS_V::money( $amt, $base ),
				'adsCount'    => FLXADS_V::i( $t['ads_count'] ?? 0 ),
				'adsAmount'   => FLXADS_V::money( $ads, $base ),
				'adsPercent'  => $amt > 0 ? round( 100 * $ads / $amt, 1 ) : null,
				'newCount'    => FLXADS_V::i( $t['new'] ?? 0 ),
				'mixed'       => FLXADS_V::b( $t['mixed'] ?? false ),
				'hasSpend'    => $has,
				'spend'       => $has ? FLXADS_V::money( $sp, $base ) : null,
				'clicks'      => FLXADS_V::i( $t['clicks'] ?? 0 ),
				'roas'        => FLXADS_V::fn( $t['roas'] ?? null ),
				'spendIncome' => FLXADS_V::money( $t['spend_income'] ?? 0, $base ),
				'spendCount'  => FLXADS_V::i( $t['spend_count'] ?? 0 ),
				'spendNew'    => FLXADS_V::i( $t['spend_new'] ?? 0 ),
				'cpa'         => FLXADS_V::moneyn( $t['cpa'] ?? null, $base ),
				'cpn'         => FLXADS_V::moneyn( $t['cpn'] ?? null, $base ),
			),
			'showSpendColumns' => $cols,
			'rows'             => $rows,
			'recent'           => $recent,
		);
	}

	/* ------------------------------------------------------------ Ad spend */

	/**
	 * Meta sync status, or null when it never ran.
	 *
	 * @return FlxadsMetaStatus|null
	 */
	public static function meta_status(): ?array {
		$st = FLXADS_Spend::status( 'meta' );
		if ( ! $st ) {
			return null;
		}
		$st = FLXADS_V::a( $st );
		return array(
			'ok'         => FLXADS_V::b( $st['ok'] ?? false ),
			'at'         => FLXADS_V::s( $st['at'] ?? '' ),
			'message'    => FLXADS_V::s( $st['message'] ?? '' ),
			'rows'       => FLXADS_V::i( $st['rows'] ?? 0 ),
			'since'      => FLXADS_V::s( $st['since'] ?? '' ),
			'until'      => FLXADS_V::s( $st['until'] ?? '' ),
			'sinceLabel' => FLXADS_V::s( flxads_date( FLXADS_V::s( $st['since'] ?? '' ), 'date' ) ),
			'untilLabel' => FLXADS_V::s( flxads_date( FLXADS_V::s( $st['until'] ?? '' ), 'date' ) ),
		);
	}

	/**
	 * Ad spend page: Meta status, spend per campaign for the period and the hand-entered rows.
	 *
	 * @param FlxadsFilter $in           Filter.
	 * @param bool         $with_manual  The viewer manages Flux Ads (the manual rows are theirs).
	 * @return FlxadsSpendPage
	 */
	public static function spend_page( array $in, bool $with_manual ): array {
		FLXADS_V::tag( self::CACHE_TAG );
		$f    = self::clean_filter( $in );
		$base = FLXADS_Report::base_currency();
		$sp   = FLXADS_Spend::totals( $f['from'], $f['to'] );
		$camp = FLXADS_V::a( $sp['by_campaign'] ?? array() );
		uasort(
			$camp,
			static function ( $a, $b ): int {
				return FLXADS_V::f( FLXADS_V::l( $b )[0] ?? 0 ) <=> FLXADS_V::f( FLXADS_V::l( $a )[0] ?? 0 );
			}
		);
		$campaigns = array();
		foreach ( array_slice( $camp, 0, 50 ) as $c ) {
			$c           = FLXADS_V::l( $c );
			$ch          = FLXADS_V::s( $c[1] ?? '' );
			$campaigns[] = array(
				'name'         => FLXADS_V::s( $c[2] ?? '' ),
				'channel'      => $ch,
				'channelLabel' => flxads_channel_label( $ch ),
				'spend'        => FLXADS_V::money( $c[0] ?? 0, $base ),
			);
		}
		$manual = array();
		if ( $with_manual ) {
			foreach ( FLXADS_V::rows( FLXADS_Spend::manual_rows( 50 ) ) as $r ) {
				$ch       = FLXADS_V::s( $r['platform'] ?? '' );
				$ad       = FLXADS_V::s( $r['ad'] ?? '' );
				$manual[] = array(
					'id'           => FLXADS_V::i( $r['id'] ?? 0 ),
					'day'          => FLXADS_V::s( $r['day'] ?? '' ),
					'dayLabel'     => FLXADS_V::s( flxads_date( FLXADS_V::s( $r['day'] ?? '' ) . ' 12:00:00', 'date' ) ),
					'channel'      => $ch,
					'channelLabel' => flxads_channel_label( $ch ),
					'campaign'     => FLXADS_V::s( $r['campaign'] ?? '' ),
					'ad'           => $ad,
					'spend'        => FLXADS_V::money( $r['spend'] ?? 0, $r['currency'] ?? '' ),
				);
			}
		}
		$channels = array();
		foreach ( flxads_channel_labels() as $k => $l ) {
			if ( '' !== (string) $k ) {
				$channels[] = array(
					'key'   => (string) $k,
					'label' => (string) $l,
				);
			}
		}
		return array(
			'range'      => $f['range'],
			'from'       => $f['from'],
			'to'         => $f['to'],
			'base'       => $base,
			'metaReady'  => (bool) FLXADS_Spend::meta_ready(),
			'metaStatus' => self::meta_status(),
			'campaigns'  => $campaigns,
			'total'      => FLXADS_V::money( $sp['total'] ?? 0, $base ),
			'manual'     => $manual,
			'channels'   => $channels,
		);
	}

	/**
	 * Reads the Meta spend now.
	 *
	 * @return FlxadsMetaStatus|null
	 */
	public static function sync_spend(): ?array {
		FLXADS_Spend::sync_meta();
		FLXADS_V::purge( self::CACHE_TAG );
		return self::meta_status();
	}

	/**
	 * Adds a hand-entered spend row.
	 *
	 * @param array{channel: string, day: string, campaign: string, ad: string, spend: string, currency: string} $in Row.
	 * @return true|WP_Error
	 */
	public static function add_spend( array $in ) {
		if ( ! FLXADS_Spend::add_manual( $in ) ) {
			return FLXADS_V::error( 'flxads_spend_bad', __( 'Enter an amount greater than zero.', 'flx-ads' ), 'spend' );
		}
		FLXADS_V::purge( self::CACHE_TAG );
		return true;
	}

	/**
	 * Deletes a hand-entered spend row.
	 *
	 * @return true|WP_Error
	 */
	public static function delete_spend( int $id ) {
		if ( ! FLXADS_Spend::delete_manual( $id ) ) {
			return FLXADS_V::error( 'flxads_not_found', __( 'Spend not found.', 'flx-ads' ), 'id', 'NOT_FOUND' );
		}
		FLXADS_V::purge( self::CACHE_TAG );
		return true;
	}

	/* ------------------------------------------------------------ Donor value */

	/**
	 * What the people an ad brought gave in total, per campaign or channel, and the monthly cohort table.
	 *
	 * @param FlxadsFilter $in Filter (defaults to the last 90 days).
	 * @return FlxadsValuePage
	 */
	public static function value_page( array $in ): array {
		FLXADS_V::tag( self::CACHE_TAG );
		$by = isset( $in['by'] ) && 'channel' === $in['by'] ? 'channel' : 'campaign';
		$f  = self::clean_filter( $in, 'campaign', '90' );
		$r    = FLXADS_Cohort::value( $by, $f );
		$t    = FLXADS_V::a( $r['totals'] ?? array() );
		$base = FLXADS_V::s( $r['base'] ?? '', FLXADS_Report::base_currency() );
		$rows = array();
		foreach ( FLXADS_V::rows( $r['rows'] ?? array() ) as $row ) {
			$ratio  = FLXADS_V::fn( $row['ratio'] ?? null );
			$ch     = FLXADS_V::s( $row['channel'] ?? '' );
			$spend  = FLXADS_V::fn( $row['spend'] ?? null );
			$rows[] = array(
				'key'          => FLXADS_V::s( $row['key'] ?? '' ),
				'label'        => FLXADS_V::s( $row['label'] ?? '' ),
				'channel'      => $ch,
				'channelLabel' => flxads_channel_label( $ch ),
				'newCount'     => FLXADS_V::i( $row['new'] ?? 0 ),
				'first'        => FLXADS_V::money( $row['first'] ?? 0, $base ),
				'ltv90'        => FLXADS_V::money( $row['ltv90'] ?? 0, $base ),
				'ltv'          => FLXADS_V::money( $row['ltv'] ?? 0, $base ),
				'avg'          => FLXADS_V::moneyn( $row['avg'] ?? null, $base ),
				'rate'         => FLXADS_V::fn( $row['rate'] ?? null ),
				'hasSpend'     => null !== $spend,
				'spend'        => FLXADS_V::moneyn( $spend, $base ),
				'cac'          => FLXADS_V::moneyn( $row['cac'] ?? null, $base ),
				'ratio'        => $ratio,
				'roasLevel'    => FLXADS_V::roas_level( $ratio ),
				'payback'      => isset( $row['payback'] ) && is_numeric( $row['payback'] ) ? (int) $row['payback'] : null,
			);
		}
		$m       = FLXADS_Cohort::matrix( array( 'kind' => $f['kind'] ), 12 );
		$cohort  = array();
		foreach ( FLXADS_V::rows( $m['rows'] ?? array() ) as $row ) {
			$cells = array();
			foreach ( FLXADS_V::l( $row['cells'] ?? array() ) as $c ) {
				$cells[] = FLXADS_V::moneyn( $c, $base );
			}
			$cohort[] = array(
				'month'    => FLXADS_V::s( $row['month'] ?? '' ),
				'label'    => FLXADS_V::s( $row['label'] ?? '' ),
				'newCount' => FLXADS_V::i( $row['new'] ?? 0 ),
				'cells'    => $cells,
			);
		}
		$spend = FLXADS_V::f( $t['spend'] ?? 0 );
		return array(
			'range'        => $f['range'],
			'from'         => FLXADS_V::s( $r['from'] ?? $f['from'] ),
			'to'           => FLXADS_V::s( $r['to'] ?? $f['to'] ),
			'kind'         => $f['kind'],
			'by'           => $by,
			'base'         => $base,
			'totals'       => array(
				'newCount' => FLXADS_V::i( $t['new'] ?? 0 ),
				'adsNew'   => FLXADS_V::i( $t['ads_new'] ?? 0 ),
				'adsAvg'   => FLXADS_V::moneyn( $t['ads_avg'] ?? null, $base ),
				'cac'      => FLXADS_V::moneyn( $t['cac'] ?? null, $base ),
				'spend'    => $spend > 0 ? FLXADS_V::money( $spend, $base ) : null,
				'ratio'    => FLXADS_V::fn( $t['ratio'] ?? null ),
				'rate'     => FLXADS_V::fn( $t['rate'] ?? null ),
			),
			'rows'         => $rows,
			'cohortMonths' => FLXADS_V::i( $m['months'] ?? 12, 12 ),
			'cohort'       => $cohort,
		);
	}

	/* ------------------------------------------------------------ Send log */

	/**
	 * Status text and the reason the send log shows.
	 *
	 * @return array{0: string, 1: string}
	 */
	public static function status_label( string $s, string $err = '' ): array {
		$map = array(
			'pending' => __( 'Waiting', 'flx-ads' ),
			'sent'    => __( 'Sent', 'flx-ads' ),
			'failed'  => __( 'Failed', 'flx-ads' ),
			'skipped' => __( 'Not sent', 'flx-ads' ),
		);
		$why = array(
			'no_consent'    => __( 'visitor did not accept cookies', 'flx-ads' ),
			'browser_sent'  => __( 'already sent from the browser', 'flx-ads' ),
			'platform_off'  => __( 'platform turned off', 'flx-ads' ),
			'order_missing' => __( 'order deleted', 'flx-ads' ),
		);
		return array( $map[ $s ] ?? $s, $why[ $err ] ?? $err );
	}

	/**
	 * Conversions sent to the ad platforms (last 100) and the status counts.
	 *
	 * @return FlxadsSendLog
	 */
	public static function send_log(): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		$c    = FLXADS_Send::counts();
		$rows = array();
		foreach ( FLXADS_V::rows( $wpdb->get_results( 'SELECT * FROM ' . flxads_table( 'conversions' ) . ' ORDER BY id DESC LIMIT 100', ARRAY_A ) ) as $r ) { // phpcs:ignore WordPress.DB
			$status           = FLXADS_V::s( $r['status'] ?? '' );
			$err              = FLXADS_V::s( $r['last_error'] ?? '' );
			list( $st, $why ) = self::status_label( $status, $err );
			$ref              = self::order_ref( FLXADS_V::i( $r['order_id'] ?? 0 ) );
			$rows[]           = array(
				'id'            => FLXADS_V::i( $r['id'] ?? 0 ),
				'platform'      => FLXADS_V::s( $r['platform'] ?? '' ),
				'platformLabel' => flxads_platforms()[ FLXADS_V::s( $r['platform'] ?? '' ) ] ?? FLXADS_V::s( $r['platform'] ?? '' ),
				'orderId'       => FLXADS_V::i( $r['order_id'] ?? 0 ),
				'orderNumber'   => $ref['number'],
				'editUrl'       => $ref['url'],
				'value'         => FLXADS_V::money( $r['value'] ?? 0, $r['currency'] ?? '' ),
				'status'        => $status,
				'statusLabel'   => $st,
				'reason'        => $why,
				'attempts'      => FLXADS_V::i( $r['attempts'] ?? 0 ),
				'at'            => FLXADS_V::s( 'sent' === $status ? ( $r['sent_at'] ?? '' ) : ( $r['created_at'] ?? '' ) ),
				'canRetry'      => 'failed' === $status || ( 'skipped' === $status && 'no_consent' !== $err && 'browser_sent' !== $err ),
			);
		}
		return array(
			'sent'    => FLXADS_V::i( $c['sent'] ?? 0 ),
			'pending' => FLXADS_V::i( $c['pending'] ?? 0 ),
			'failed'  => FLXADS_V::i( $c['failed'] ?? 0 ),
			'skipped' => FLXADS_V::i( $c['skipped'] ?? 0 ),
			'rows'    => $rows,
		);
	}

	/**
	 * Sends a failed conversion again.
	 *
	 * @return true|WP_Error
	 */
	public static function retry( int $id ) {
		if ( ! FLXADS_Send::get( $id ) ) {
			return FLXADS_V::error( 'flxads_not_found', __( 'Row not found.', 'flx-ads' ), 'id', 'NOT_FOUND' );
		}
		FLXADS_Send::retry( $id );
		return true;
	}

	/* ------------------------------------------------------------ Ad link */

	/**
	 * Pages and choices of the UTM link builder.
	 *
	 * @return FlxadsLinkBuilder
	 */
	public static function link_builder(): array {
		$pages = array( home_url( '/' ) );
		$found = get_pages(
			array(
				'number'      => 50,
				'sort_column' => 'menu_order,post_title',
			)
		);
		foreach ( is_array( $found ) ? $found : array() as $pg ) {
			$pages[] = get_permalink( $pg );
		}
		return array(
			'homeUrl' => home_url( '/' ),
			'pages'   => array_values( array_unique( $pages ) ),
			'sources' => self::options(
				array(
					'facebook'   => 'Facebook',
					'instagram'  => 'Instagram',
					'google'     => 'Google',
					'youtube'    => 'YouTube',
					'tiktok'     => 'TikTok',
					'whatsapp'   => 'WhatsApp',
					'newsletter' => __( 'E-mail newsletter', 'flx-ads' ),
				)
			),
			'mediums' => self::options(
				array(
					'paid_social' => __( 'Paid social', 'flx-ads' ),
					'cpc'         => __( 'Search ad (cpc)', 'flx-ads' ),
					'video'       => __( 'Video ad', 'flx-ads' ),
					'social'      => __( 'Organic post', 'flx-ads' ),
					'email'       => __( 'E-mail', 'flx-ads' ),
				)
			),
		);
	}
}
