<?php
/**
 * Flux Ads: what happens in the Meta ad account: campaigns (read, pause, daily budget), the change history,
 * suggestions and A/B / holdout tests. The classic pages, GraphQL and Flux AI use the same legacy classes
 * (FLXADS_Manager, FLXADS_Optimizer, FLXADS_Experiments); this service gives GraphQL typed shapes and holds
 * the glue the classic admin-post handlers used to carry. Permission checks stay with the caller.
 *
 * Errors are WP_Error with `graphql_code` INVALID_INPUT / NOT_FOUND and the input `field`.
 *
 * @package FLX_Ads
 * @phpstan-import-type FlxadsMoney from FLXADS_V
 * @phpstan-import-type FlxadsOption from FLXADS_Reports_Service
 *
 * @phpstan-type FlxadsTargeting array{summary: string}
 * @phpstan-type FlxadsMetric array{spend: FlxadsMoney, impressions: int, clicks: int, ctr: float|null}
 * @phpstan-type FlxadsCampaignRow array{id: string, name: string, effective: string, statusLabel: string, statusTone: string, objective: string, objectiveLabel: string, budgetLabel: string, canPause: bool, active: bool, dailyBudget: float|null, spend: FlxadsMoney, impressions: int, clicks: int, ctr: float|null, payments: int, income: FlxadsMoney, roas: float|null, roasLevel: string}
 * @phpstan-type FlxadsCampaignTotals array{spend: FlxadsMoney, income: FlxadsMoney, payments: int, roas: float|null, active: int, count: int}
 * @phpstan-type FlxadsCampaignList array{range: string, ranges: list<FlxadsOption>, error: string|null, currency: string, since: string, until: string, sinceLabel: string, untilLabel: string, totals: FlxadsCampaignTotals|null, rows: list<FlxadsCampaignRow>}
 * @phpstan-type FlxadsAdRow array{id: string, name: string, effective: string, statusLabel: string, statusTone: string, thumb: string|null, title: string, canPause: bool, active: bool, spend: FlxadsMoney, impressions: int, clicks: int, ctr: float|null}
 * @phpstan-type FlxadsAdsetRow array{id: string, name: string, effective: string, statusLabel: string, statusTone: string, budgetLabel: string, who: string, canPause: bool, active: bool, dailyBudget: float|null, spend: FlxadsMoney, impressions: int, clicks: int, ctr: float|null, ads: list<FlxadsAdRow>}
 * @phpstan-type FlxadsBreakdownRow array{label: string, spend: FlxadsMoney, clicks: int, share: float}
 * @phpstan-type FlxadsCampaignDetail array{range: string, error: string|null, id: string, name: string, effective: string, statusLabel: string, statusTone: string, summary: string, canPause: bool, active: bool, dailyBudget: float|null, adsets: list<FlxadsAdsetRow>, ageGender: list<FlxadsBreakdownRow>, placement: list<FlxadsBreakdownRow>}
 * @phpstan-type FlxadsChange array{at: string, name: string, change: string, who: string, channel: string}
 * @phpstan-type FlxadsSuggestionAction array{field: string, to: string, confirm: string, label: string}
 * @phpstan-type FlxadsSuggestion array{id: string, type: string, typeLabel: string, tone: string, created: string, name: string, level: string, context: string, reason: string, campaignId: string, action: FlxadsSuggestionAction|null, brief: string, start: string, end: string, status: string, statusLabel: string, decided: string, who: string, result: string}
 * @phpstan-type FlxadsSuggestionRun array{at: string, ok: bool|null, message: string, newCount: int}
 * @phpstan-type FlxadsSuggestionsPage array{metaReady: bool, run: FlxadsSuggestionRun|null, targetRoas: float, rulesText: string, open: list<FlxadsSuggestion>, earlier: list<FlxadsSuggestion>}
 * @phpstan-type FlxadsExpTableRow array{label: string, a: string, b: string}
 * @phpstan-type FlxadsExperiment array{id: string, type: string, typeLabel: string, name: string, status: string, running: bool, start: string, end: string, startLabel: string, endLabel: string, subject: string, ok: bool, error: string, headA: string, headB: string, table: list<FlxadsExpTableRow>, winner: string, confidence: float|null, small: bool, verdict: string, loserAd: array{id: string, name: string}|null}
 * @phpstan-type FlxadsAdChoice array{key: string, label: string}
 * @phpstan-type FlxadsAdGroup array{campaign: string, ads: list<FlxadsAdChoice>}
 * @phpstan-type FlxadsHoldoutAudience array{id: string, label: string}
 * @phpstan-type FlxadsExperimentChoices array{metaReady: bool, today: string, defaultEnd: string, adGroups: list<FlxadsAdGroup>, audiences: list<FlxadsHoldoutAudience>}
 * @phpstan-type FlxadsExperimentInput array{type: string, name?: string, start?: string, end?: string, a?: string, b?: string, audience?: string, pct?: int}
 */
final class FLXADS_Meta_Service {

	public const CACHE_TAG = 'flx-ads:meta';

	/**
	 * Date range choices.
	 *
	 * @return list<FlxadsOption>
	 */
	public static function ranges(): array {
		return FLXADS_Reports_Service::options( FLXADS_Manager::ranges() );
	}

	/**
	 * A campaign range from input (7, 30, 90).
	 */
	public static function clean_range( string $r ): string {
		$r = sanitize_key( $r );
		return isset( FLXADS_Manager::ranges()[ $r ] ) ? $r : '7';
	}

	/**
	 * Status text and tone (sent / failed / pending pill).
	 *
	 * @return array{0: string, 1: string}
	 */
	public static function status_pill( string $eff ): array {
		$l    = FLXADS_Manager::statuses();
		$tone = 'ACTIVE' === $eff ? 'sent' : ( in_array( $eff, array( 'DISAPPROVED', 'WITH_ISSUES', 'PENDING_BILLING_INFO' ), true ) ? 'failed' : 'pending' );
		return array( $l[ $eff ] ?? ucfirst( strtolower( str_replace( '_', ' ', $eff ) ) ), $tone );
	}

	/**
	 * Meta objective in words.
	 */
	public static function objective_label( string $o ): string {
		$m = array(
			'OUTCOME_SALES'         => __( 'Sales', 'flx-ads' ),
			'OUTCOME_LEADS'         => __( 'Leads', 'flx-ads' ),
			'OUTCOME_TRAFFIC'       => __( 'Traffic', 'flx-ads' ),
			'OUTCOME_ENGAGEMENT'    => __( 'Engagement', 'flx-ads' ),
			'OUTCOME_AWARENESS'     => __( 'Awareness', 'flx-ads' ),
			'OUTCOME_APP_PROMOTION' => __( 'App promotion', 'flx-ads' ),
		);
		return $m[ $o ] ?? ucfirst( strtolower( str_replace( array( 'OUTCOME_', '_' ), array( '', ' ' ), $o ) ) );
	}

	/**
	 * "500 ₺ / day", "5.000 ₺ total" or empty.
	 *
	 * @param array<string,mixed> $o   Meta item.
	 * @param string              $cur Ad account currency.
	 */
	private static function budget_label( array $o, string $cur ): string {
		if ( null !== FLXADS_V::fn( $o['daily'] ?? null ) ) {
			/* translators: %s: amount */
			return sprintf( __( '%s / day', 'flx-ads' ), flxads_money( FLXADS_V::f( $o['daily'] ), $cur ) );
		}
		if ( null !== FLXADS_V::fn( $o['lifetime'] ?? null ) ) {
			/* translators: %s: amount */
			return sprintf( __( '%s total', 'flx-ads' ), flxads_money( FLXADS_V::f( $o['lifetime'] ), $cur ) );
		}
		return '';
	}

	/**
	 * Shared columns of a campaign, ad set or ad row.
	 *
	 * @param array<string,mixed> $o   Meta item.
	 * @param string              $cur Ad account currency.
	 * @return array{id: string, name: string, effective: string, statusLabel: string, statusTone: string, canPause: bool, active: bool, dailyBudget: float|null, spend: FlxadsMoney, impressions: int, clicks: int, ctr: float|null}
	 */
	private static function metric_row( array $o, string $cur ): array {
		$eff = FLXADS_V::s( $o['effective'] ?? '' );
		$p   = self::status_pill( $eff );
		$st  = FLXADS_V::s( $o['status'] ?? '' );
		return array(
			'id'          => FLXADS_V::s( $o['id'] ?? '' ),
			'name'        => FLXADS_V::s( $o['name'] ?? '' ),
			'effective'   => $eff,
			'statusLabel' => $p[0],
			'statusTone'  => $p[1],
			'canPause'    => in_array( $st, array( 'ACTIVE', 'PAUSED' ), true ),
			'active'      => 'ACTIVE' === $st,
			'dailyBudget' => FLXADS_V::fn( $o['daily'] ?? null ),
			'spend'       => FLXADS_V::money( $o['spend'] ?? 0, $cur ),
			'impressions' => FLXADS_V::i( $o['impressions'] ?? 0 ),
			'clicks'      => FLXADS_V::i( $o['clicks'] ?? 0 ),
			'ctr'         => FLXADS_V::fn( $o['ctr'] ?? null ),
		);
	}

	/**
	 * Meta campaigns with spend and what they brought on the site.
	 *
	 * @return FlxadsCampaignList
	 */
	public static function campaigns( string $range ): array {
		$range = self::clean_range( $range );
		$out   = array(
			'range'      => $range,
			'ranges'     => self::ranges(),
			'error'      => null,
			'currency'   => FLXADS_Report::base_currency(),
			'since'      => '',
			'until'      => '',
			'sinceLabel' => '',
			'untilLabel' => '',
			'totals'     => null,
			'rows'       => array(),
		);
		if ( ! FLXADS_Spend::meta_ready() ) {
			return $out;
		}
		$d = FLXADS_Manager::campaigns( $range );
		if ( is_wp_error( $d ) ) {
			$out['error'] = $d->get_error_message();
			return $out;
		}
		$d     = FLXADS_V::a( $d );
		$cur   = FLXADS_V::s( $d['currency'] ?? '', FLXADS_Report::base_currency() );
		$base  = FLXADS_V::s( $d['base'] ?? '', FLXADS_Report::base_currency() );
		$t     = FLXADS_V::a( $d['totals'] ?? array() );
		$rows  = array();
		foreach ( FLXADS_V::rows( $d['rows'] ?? array() ) as $c ) {
			$roas   = FLXADS_V::fn( $c['roas'] ?? null );
			$obj    = FLXADS_V::s( $c['objective'] ?? '' );
			$rows[] = self::metric_row( $c, $cur ) + array(
				'objective'      => $obj,
				'objectiveLabel' => self::objective_label( $obj ),
				'budgetLabel'    => self::budget_label( $c, $cur ),
				'payments'       => FLXADS_V::i( $c['payments'] ?? 0 ),
				'income'         => FLXADS_V::money( $c['income'] ?? 0, $base ),
				'roas'           => $roas,
				'roasLevel'      => FLXADS_V::roas_level( $roas ),
			);
		}
		$since = FLXADS_V::s( $d['since'] ?? '' );
		$until = FLXADS_V::s( $d['until'] ?? '' );
		return array(
			'range'      => $range,
			'ranges'     => self::ranges(),
			'error'      => null,
			'currency'   => $cur,
			'since'      => $since,
			'until'      => $until,
			'sinceLabel' => FLXADS_V::s( flxads_date( $since, 'date' ) ),
			'untilLabel' => FLXADS_V::s( flxads_date( $until, 'date' ) ),
			'totals'     => array(
				'spend'    => FLXADS_V::money( $t['spend'] ?? 0, $cur ),
				'income'   => FLXADS_V::money( $t['income'] ?? 0, $base ),
				'payments' => FLXADS_V::i( $t['payments'] ?? 0 ),
				'roas'     => FLXADS_V::fn( $t['roas'] ?? null ),
				'active'   => FLXADS_V::i( $t['active'] ?? 0 ),
				'count'    => count( $rows ),
			),
			'rows'       => $rows,
		);
	}

	/**
	 * Who an ad set shows to, in one line.
	 *
	 * @param array<string,mixed> $tg Targeting summary.
	 */
	private static function who( array $tg ): string {
		if ( ! $tg ) {
			return '';
		}
		$gl  = array(
			'all'    => __( 'all genders', 'flx-ads' ),
			'male'   => __( 'men', 'flx-ads' ),
			'female' => __( 'women', 'flx-ads' ),
		);
		$who = sprintf(
			/* translators: 1: min age, 2: max age, 3: gender, 4: places */
			__( '%1$d–%2$d, %3$s, %4$s', 'flx-ads' ),
			FLXADS_V::i( $tg['age_min'] ?? 18 ),
			FLXADS_V::i( $tg['age_max'] ?? 65 ),
			$gl[ FLXADS_V::s( $tg['genders'] ?? 'all' ) ] ?? '',
			implode( ', ', array_merge( FLXADS_V::ls( $tg['countries'] ?? array() ), FLXADS_V::ls( $tg['cities'] ?? array() ) ) )
		);
		if ( FLXADS_V::i( $tg['audiences'] ?? 0 ) || FLXADS_V::i( $tg['excluded'] ?? 0 ) ) {
			/* translators: 1: included, 2: excluded */
			$who .= ' · ' . sprintf( __( 'audiences +%1$d / −%2$d', 'flx-ads' ), FLXADS_V::i( $tg['audiences'] ?? 0 ), FLXADS_V::i( $tg['excluded'] ?? 0 ) );
		}
		return $who;
	}

	/**
	 * Spend and clicks by age + gender or by placement.
	 *
	 * @param mixed  $rows Meta breakdown rows.
	 * @param string $cur  Ad account currency.
	 * @param string $kind age_gender | placement.
	 * @return list<FlxadsBreakdownRow>
	 */
	private static function breakdown( $rows, string $cur, string $kind ): array {
		$list = FLXADS_V::rows( $rows );
		$max  = 0.01;
		foreach ( $list as $r ) {
			$max = max( $max, FLXADS_V::f( $r['spend'] ?? 0 ) );
		}
		$pl  = array(
			'facebook'         => 'Facebook',
			'instagram'        => 'Instagram',
			'audience_network' => 'Audience Network',
			'messenger'        => 'Messenger',
			'threads'          => 'Threads',
		);
		$g   = array(
			'male'    => __( 'men', 'flx-ads' ),
			'female'  => __( 'women', 'flx-ads' ),
			'unknown' => __( 'unknown', 'flx-ads' ),
		);
		$out = array();
		foreach ( array_slice( $list, 0, 12 ) as $r ) {
			$k = FLXADS_V::ls( $r['key'] ?? array() );
			if ( 'age_gender' === $kind ) {
				$label = ( $k[0] ?? '' ) . ' · ' . ( $g[ $k[1] ?? '' ] ?? ( $k[1] ?? '' ) );
			} else {
				$label = ( $pl[ $k[0] ?? '' ] ?? ucfirst( str_replace( '_', ' ', $k[0] ?? '' ) ) ) . ' · ' . str_replace( '_', ' ', $k[1] ?? '' );
			}
			$out[] = array(
				'label'  => $label,
				'spend'  => FLXADS_V::money( $r['spend'] ?? 0, $cur ),
				'clicks' => FLXADS_V::i( $r['clicks'] ?? 0 ),
				'share'  => round( 100 * FLXADS_V::f( $r['spend'] ?? 0 ) / $max ),
			);
		}
		return $out;
	}

	/**
	 * One campaign: ad sets with their ads and the breakdowns.
	 *
	 * @return FlxadsCampaignDetail
	 */
	public static function campaign( string $id, string $range ): array {
		$range = self::clean_range( $range );
		$out   = array(
			'range'       => $range,
			'error'       => null,
			'id'          => (string) preg_replace( '/\D+/', '', $id ),
			'name'        => '',
			'effective'   => '',
			'statusLabel' => '',
			'statusTone'  => 'pending',
			'summary'     => '',
			'canPause'    => false,
			'active'      => false,
			'dailyBudget' => null,
			'adsets'      => array(),
			'ageGender'   => array(),
			'placement'   => array(),
		);
		if ( ! FLXADS_Spend::meta_ready() ) {
			return $out;
		}
		$c = FLXADS_Manager::campaign( $id, $range );
		if ( is_wp_error( $c ) ) {
			$out['error'] = $c->get_error_message();
			return $out;
		}
		$c   = FLXADS_V::a( $c );
		$cur = FLXADS_V::s( $c['currency'] ?? '', FLXADS_Report::base_currency() );
		$row = self::metric_row( $c, $cur );
		$sets = array();
		foreach ( FLXADS_V::rows( $c['adsets'] ?? array() ) as $s ) {
			$ads = array();
			foreach ( FLXADS_V::rows( $s['ads'] ?? array() ) as $a ) {
				$cr    = FLXADS_V::a( $a['creative'] ?? array() );
				$ads[] = self::metric_row( $a, $cur ) + array(
					'thumb' => FLXADS_V::sn( $cr['thumb'] ?? '' ),
					'title' => FLXADS_V::s( $cr['title'] ?? '' ),
				);
			}
			$sets[] = self::metric_row( $s, $cur ) + array(
				'budgetLabel' => self::budget_label( $s, $cur ),
				'who'         => self::who( FLXADS_V::a( $s['targeting'] ?? array() ) ),
				'ads'         => $ads,
			);
		}
		$bd   = FLXADS_V::a( $c['breakdown'] ?? array() );
		$bits = array_filter(
			array(
				self::objective_label( FLXADS_V::s( $c['objective'] ?? '' ) ),
				self::budget_label( $c, $cur ),
				/* translators: %s: amount */
				sprintf( __( '%s spent', 'flx-ads' ), flxads_money( FLXADS_V::f( $c['spend'] ?? 0 ), $cur ) ),
			)
		);
		return array(
			'range'       => $range,
			'error'       => null,
			'id'          => $row['id'],
			'name'        => $row['name'],
			'effective'   => $row['effective'],
			'statusLabel' => $row['statusLabel'],
			'statusTone'  => $row['statusTone'],
			'summary'     => implode( ' · ', $bits ),
			'canPause'    => $row['canPause'],
			'active'      => $row['active'],
			'dailyBudget' => $row['dailyBudget'],
			'adsets'      => $sets,
			'ageGender'   => self::breakdown( $bd['age_gender'] ?? array(), $cur, 'age_gender' ),
			'placement'   => self::breakdown( $bd['placement'] ?? array(), $cur, 'placement' ),
		);
	}

	/**
	 * The change history (pause, turn on, budget) of this panel, Flux AI and MCP.
	 *
	 * @return list<FlxadsChange>
	 */
	public static function history( int $limit = 15 ): array {
		$ch  = array(
			'panel' => __( 'Panel', 'flx-ads' ),
			'ai'    => 'Flux AI',
			'mcp'   => 'MCP',
		);
		$st  = FLXADS_Manager::statuses();
		$out = array();
		foreach ( FLXADS_V::rows( FLXADS_Manager::history( $limit ) ) as $r ) {
			$u        = get_userdata( FLXADS_V::i( $r['user'] ?? 0 ) );
			$field    = FLXADS_V::s( $r['field'] ?? '' );
			$from     = FLXADS_V::s( $r['from'] ?? '' );
			$to       = FLXADS_V::s( $r['to'] ?? '' );
			$chg      = 'status' === $field
				? ( $st[ $from ] ?? $from ) . ' → ' . ( $st[ $to ] ?? $to )
				/* translators: 1: old, 2: new */
				: sprintf( __( 'Daily budget %1$s → %2$s', 'flx-ads' ), number_format_i18n( (float) $from, 2 ), number_format_i18n( (float) $to, 2 ) );
			$channel  = FLXADS_V::s( $r['channel'] ?? '' );
			$out[]    = array(
				'at'      => FLXADS_V::s( $r['at'] ?? '' ),
				'name'    => FLXADS_V::s( $r['name'] ?? '' ),
				'change'  => $chg,
				'who'     => $u ? $u->display_name : '',
				'channel' => $ch[ $channel ] ?? $channel,
			);
		}
		return $out;
	}

	/**
	 * Turns a campaign, ad set or ad on or off in Meta.
	 *
	 * @param string $status ACTIVE | PAUSED.
	 * @return true|WP_Error
	 */
	public static function set_status( string $id, string $status ) {
		$r = FLXADS_Manager::set_status( preg_replace( '/\D+/', '', $id ), sanitize_key( $status ), 'panel' );
		if ( is_wp_error( $r ) ) {
			return FLXADS_V::fail( $r );
		}
		return true;
	}

	/**
	 * Changes a daily budget in Meta.
	 *
	 * @return true|WP_Error
	 */
	public static function set_budget( string $id, float $amount ) {
		$r = FLXADS_Manager::set_budget( preg_replace( '/\D+/', '', $id ), $amount, 'panel' );
		if ( is_wp_error( $r ) ) {
			return FLXADS_V::fail( $r );
		}
		return true;
	}

	/* ------------------------------------------------------------ Suggestions */

	/**
	 * One suggestion as the page shows it.
	 *
	 * @param array<string,mixed> $o   Stored suggestion.
	 * @param string              $cur Ad account currency.
	 * @return FlxadsSuggestion
	 */
	private static function suggestion( array $o, string $cur ): array {
		$types = FLXADS_Optimizer::types();
		$sts   = FLXADS_Optimizer::statuses();
		$tone  = array(
			'pause'       => 'bad',
			'budget_down' => 'warn',
			'budget_up'   => 'good',
			'pause_ad'    => 'warn',
			'fatigue'     => 'info',
			'season'      => 'good',
		);
		$type  = FLXADS_V::s( $o['type'] ?? '' );
		$d     = FLXADS_V::a( $o['data'] ?? array() );
		$level = FLXADS_V::s( $o['level'] ?? '' );
		$name  = FLXADS_V::s( $o['name'] ?? '' );
		$ctx   = '';
		if ( 'season' === $level ) {
			$ctx = flxads_date( FLXADS_V::s( $d['start'] ?? '' ), 'date' ) . ' – ' . flxads_date( FLXADS_V::s( $d['end'] ?? '' ), 'date' );
		} elseif ( 'campaign' !== $level ) {
			$ctx = trim( FLXADS_V::s( $d['campaign'] ?? '' ) . ( '' !== FLXADS_V::s( $d['adset'] ?? '' ) ? ' › ' . FLXADS_V::s( $d['adset'] ?? '' ) : '' ) );
		}
		$action = null;
		$a      = FLXADS_V::a( $o['action'] ?? array() );
		if ( $a ) {
			$field = FLXADS_V::s( $a['field'] ?? '' );
			$to    = $a['to'] ?? '';
			if ( 'budget' === $field ) {
				/* translators: 1: name, 2: amount */
				$confirm = sprintf( __( 'Set the daily budget of "%1$s" to %2$s in Meta?', 'flx-ads' ), $name, flxads_money( FLXADS_V::f( $to ), $cur ) );
				/* translators: %s: amount */
				$label = sprintf( __( 'Apply: %s a day', 'flx-ads' ), flxads_money( FLXADS_V::f( $to ), $cur ) );
			} else {
				/* translators: %s: name */
				$confirm = sprintf( __( 'Pause "%s" in Meta?', 'flx-ads' ), $name );
				$label   = __( 'Apply: pause', 'flx-ads' );
			}
			$action = array(
				'field'   => $field,
				'to'      => FLXADS_V::s( $to ),
				'confirm' => $confirm,
				'label'   => $label,
			);
		}
		$status = FLXADS_V::s( $o['status'] ?? '' );
		$u      = FLXADS_V::i( $o['user'] ?? 0 ) ? get_userdata( FLXADS_V::i( $o['user'] ?? 0 ) ) : false;
		$chs    = array(
			'panel' => __( 'Panel', 'flx-ads' ),
			'ai'    => 'Flux AI',
			'mcp'   => 'MCP',
		);
		$chan   = FLXADS_V::s( $o['channel'] ?? '' );
		$who    = implode(
			' · ',
			array_filter(
				array(
					$u ? $u->display_name : '',
					$u ? ( $chs[ $chan ] ?? $chan ) : '',
					FLXADS_V::s( $o['result'] ?? '' ),
				)
			)
		);
		return array(
			'id'          => FLXADS_V::s( $o['id'] ?? '' ),
			'type'        => $type,
			'typeLabel'   => $types[ $type ] ?? $type,
			'tone'        => $tone[ $type ] ?? 'info',
			'created'     => FLXADS_V::s( $o['created'] ?? '' ),
			'name'        => $name,
			'level'       => $level,
			'context'     => $ctx,
			'reason'      => FLXADS_V::s( FLXADS_Optimizer::reason( $o ) ),
			'campaignId'  => FLXADS_V::s( $d['campaign_id'] ?? '' ),
			'action'      => $action,
			'brief'       => FLXADS_V::s( $d['brief'] ?? '' ),
			'start'       => FLXADS_V::s( $d['start'] ?? '' ),
			'end'         => FLXADS_V::s( $d['end'] ?? '' ),
			'status'      => $status,
			'statusLabel' => $sts[ $status ] ?? $status,
			'decided'     => FLXADS_V::s( $o['decided'] ?? '' ),
			'who'         => $who,
			'result'      => FLXADS_V::s( $o['result'] ?? '' ),
		);
	}

	/**
	 * Suggestions page: open ones, earlier ones and the rules.
	 *
	 * @return FlxadsSuggestionsPage
	 */
	public static function suggestions(): array {
		$ready = (bool) FLXADS_Spend::meta_ready();
		$out   = array(
			'metaReady'  => $ready,
			'run'        => null,
			'targetRoas' => 2.0,
			'rulesText'  => '',
			'open'       => array(),
			'earlier'    => array(),
		);
		if ( ! $ready ) {
			return $out;
		}
		$run   = FLXADS_V::a( FLXADS_Optimizer::last_run() );
		$r     = FLXADS_V::a( FLXADS_Optimizer::rules( array() ) );
		$cur   = FLXADS_V::s( FLXADS_Manager::currency(), FLXADS_Report::base_currency() );
		$min   = FLXADS_V::f( FLXADS_V::a( flxads_settings() )['opt_min_spend'] ?? 0 );
		$rules = FLXADS_V::a( $run['rules'] ?? array() );
		if ( $min > 0 ) {
			/* translators: %s: amount */
			$text = sprintf( __( 'A campaign is judged after %s spend in 7 days.', 'flx-ads' ), flxads_money( $min, $cur ) );
		} elseif ( ! empty( $rules['min_spend'] ) ) {
			/* translators: %s: amount */
			$text = sprintf( __( 'A campaign is judged after spending twice your average cost per payment in 7 days (now %s).', 'flx-ads' ), flxads_money( FLXADS_V::f( $rules['min_spend'] ), $cur ) );
		} else {
			$text = __( 'A campaign is judged after spending twice your average cost per payment in 7 days.', 'flx-ads' );
		}
		$open = array();
		foreach ( FLXADS_V::rows( FLXADS_Optimizer::open() ) as $o ) {
			$open[] = self::suggestion( $o, $cur );
		}
		$done = array_values(
			array_filter(
				FLXADS_V::rows( FLXADS_Optimizer::all() ),
				static function ( array $x ): bool {
					return 'open' !== FLXADS_V::s( $x['status'] ?? '' );
				}
			)
		);
		usort(
			$done,
			static function ( array $a, array $b ): int {
				return strcmp( FLXADS_V::s( $b['decided'] ?? '' ), FLXADS_V::s( $a['decided'] ?? '' ) );
			}
		);
		$earlier = array();
		foreach ( array_slice( $done, 0, 20 ) as $x ) {
			$earlier[] = self::suggestion( $x, $cur );
		}
		return array(
			'metaReady'  => true,
			'run'        => $run ? array(
				'at'       => FLXADS_V::s( $run['at'] ?? '' ),
				'ok'       => isset( $run['ok'] ) ? FLXADS_V::b( $run['ok'] ) : null,
				'message'  => FLXADS_V::s( $run['message'] ?? '' ),
				'newCount' => FLXADS_V::i( $run['new'] ?? 0 ),
			) : null,
			'targetRoas' => FLXADS_V::f( $r['target_roas'] ?? 2 ),
			'rulesText'  => $text,
			'open'       => $open,
			'earlier'    => $earlier,
		);
	}

	/**
	 * Checks the campaigns now and returns the number of new suggestions.
	 *
	 * @return int|WP_Error
	 */
	public static function refresh_suggestions() {
		$r = FLXADS_Optimizer::refresh();
		if ( is_wp_error( $r ) ) {
			return FLXADS_V::fail( $r );
		}
		return FLXADS_V::i( FLXADS_V::a( $r )['new'] ?? 0 );
	}

	/**
	 * Applies a suggestion in Meta.
	 *
	 * @return true|WP_Error
	 */
	public static function apply_suggestion( string $id ) {
		$r = FLXADS_Optimizer::apply( sanitize_key( $id ), 'panel' );
		return is_wp_error( $r ) ? FLXADS_V::fail( $r ) : true;
	}

	/**
	 * Dismisses a suggestion for 14 days.
	 *
	 * @return true|WP_Error
	 */
	public static function dismiss_suggestion( string $id ) {
		$r = FLXADS_Optimizer::dismiss( sanitize_key( $id ), 'panel' );
		return is_wp_error( $r ) ? FLXADS_V::fail( $r ) : true;
	}

	/**
	 * Number of suggestions that wait (menu badge).
	 */
	public static function open_count(): int {
		return count( FLXADS_Optimizer::open() );
	}

	/* ------------------------------------------------------------ Tests */

	/**
	 * Result table of one test, built like the classic page does.
	 *
	 * @param array<string,mixed> $e   Test.
	 * @param array<string,mixed> $r   Result.
	 * @param string              $cur Ad account currency.
	 * @return array{headA: string, headB: string, table: list<FlxadsExpTableRow>}
	 */
	private static function exp_table( array $e, array $r, string $cur ): array {
		$base = FLXADS_V::s( $r['base'] ?? '', FLXADS_Report::base_currency() );
		$a    = FLXADS_V::a( $r['a'] ?? array() );
		$b    = FLXADS_V::a( $r['b'] ?? array() );
		$n    = static function ( $v ): string {
			return number_format_i18n( FLXADS_V::f( $v ) );
		};
		$pct  = static function ( $v ): string {
			return null === FLXADS_V::fn( $v ) ? '—' : number_format_i18n( FLXADS_V::f( $v ), 2 ) . '%';
		};
		$mon  = static function ( $v, string $c ): string {
			return null === FLXADS_V::fn( $v ) ? '—' : flxads_money( FLXADS_V::f( $v ), $c );
		};
		if ( 'ab' === FLXADS_V::s( $e['type'] ?? '' ) ) {
			$cell = array(
				__( 'Spend', 'flx-ads' )                => static function ( array $x ) use ( $cur ): string {
					return flxads_money( FLXADS_V::f( $x['spend'] ?? 0 ), $cur );
				},
				__( 'Link clicks', 'flx-ads' )          => static function ( array $x ) use ( $n ): string {
					return $n( $x['clicks'] ?? 0 ) . ( null !== FLXADS_V::fn( $x['ctr'] ?? null ) ? ' · CTR ' . number_format_i18n( FLXADS_V::f( $x['ctr'] ), 2 ) . '%' : '' );
				},
				__( 'Payments on the site', 'flx-ads' ) => static function ( array $x ) use ( $n, $base ): string {
					return $n( $x['payments'] ?? 0 ) . ' · ' . flxads_money( FLXADS_V::f( $x['income'] ?? 0 ), $base );
				},
				__( 'Payments per click', 'flx-ads' )   => static function ( array $x ) use ( $pct ): string {
					return $pct( $x['conv'] ?? null );
				},
				__( 'Cost per payment', 'flx-ads' )     => static function ( array $x ) use ( $mon, $cur ): string {
					return $mon( $x['cpa'] ?? null, $cur );
				},
			);
			$head = array(
				'A · ' . FLXADS_V::s( $a['name'] ?? '' ),
				'B · ' . FLXADS_V::s( $b['name'] ?? '' ),
			);
		} else {
			$cell = array(
				__( 'People', 'flx-ads' )             => static function ( array $x ) use ( $n ): string {
					return $n( $x['people'] ?? 0 );
				},
				__( 'Gave at least once', 'flx-ads' ) => static function ( array $x ) use ( $n ): string {
					return $n( $x['payers'] ?? 0 ) . ( null !== FLXADS_V::fn( $x['rate'] ?? null ) ? ' · ' . number_format_i18n( FLXADS_V::f( $x['rate'] ), 2 ) . '%' : '' );
				},
				__( 'Income', 'flx-ads' )             => static function ( array $x ) use ( $base ): string {
					return flxads_money( FLXADS_V::f( $x['income'] ?? 0 ), $base );
				},
				__( 'Per person', 'flx-ads' )         => static function ( array $x ) use ( $mon, $base ): string {
					return $mon( $x['per_person'] ?? null, $base );
				},
			);
			$head = array(
				__( 'Could see ads', 'flx-ads' ),
				__( 'Held out', 'flx-ads' ),
			);
		}
		$table = array();
		foreach ( $cell as $label => $fn ) {
			$table[] = array(
				'label' => (string) $label,
				'a'     => $fn( $a ),
				'b'     => $fn( $b ),
			);
		}
		return array(
			'headA' => $head[0],
			'headB' => $head[1],
			'table' => $table,
		);
	}

	/**
	 * Every test with its result.
	 *
	 * @return list<FlxadsExperiment>
	 */
	public static function experiments(): array {
		$typ = FLXADS_Experiments::types();
		$cur = FLXADS_Spend::meta_ready() ? FLXADS_V::s( FLXADS_Manager::currency(), FLXADS_Report::base_currency() ) : FLXADS_Report::base_currency();
		$out = array();
		foreach ( FLXADS_V::rows( FLXADS_Experiments::all() ) as $e ) {
			$r      = FLXADS_V::a( FLXADS_Experiments::result( $e ) );
			$type   = FLXADS_V::s( $e['type'] ?? '' );
			$status = FLXADS_V::s( $e['status'] ?? '' );
			$ok     = FLXADS_V::b( $r['ok'] ?? false );
			$end    = FLXADS_V::s( $e['end'] ?? '' );
			if ( 'ab' === $type ) {
				$subject = FLXADS_V::s( $e['campaign_name'] ?? '' );
			} else {
				/* translators: 1: audience, 2: percent */
				$subject = sprintf( __( 'Audience "%1$s", %2$s%% held out', 'flx-ads' ), FLXADS_V::s( $e['audience_name'] ?? '' ), FLXADS_V::s( $e['pct'] ?? '' ) );
			}
			$tbl   = $ok ? self::exp_table( $e, $r, $cur ) : array(
				'headA' => '',
				'headB' => '',
				'table' => array(),
			);
			$win   = FLXADS_V::s( $r['winner'] ?? '' );
			$loser = null;
			if ( 'running' === $status && 'ab' === $type && '' !== $win ) {
				$l = FLXADS_V::a( $r[ 'a' === $win ? 'b' : 'a' ] ?? array() );
				if ( 'ACTIVE' === FLXADS_V::s( $l['status'] ?? '' ) ) {
					$loser = array(
						'id'   => FLXADS_V::s( $l['id'] ?? '' ),
						'name' => FLXADS_V::s( $l['name'] ?? '' ),
					);
				}
			}
			$out[] = array(
				'id'         => FLXADS_V::s( $e['id'] ?? '' ),
				'type'       => $type,
				'typeLabel'  => $typ[ $type ] ?? $type,
				'name'       => FLXADS_V::s( $e['name'] ?? '' ),
				'status'     => $status,
				'running'    => 'running' === $status,
				'start'      => FLXADS_V::s( $e['start'] ?? '' ),
				'end'        => $end,
				'startLabel' => FLXADS_V::s( flxads_date( FLXADS_V::s( $e['start'] ?? '' ), 'date' ) ),
				'endLabel'   => '' !== $end ? FLXADS_V::s( flxads_date( $end, 'date' ) ) : __( 'open-ended', 'flx-ads' ),
				'subject'    => $subject,
				'ok'         => $ok,
				'error'      => FLXADS_V::s( $r['error'] ?? '' ),
				'headA'      => $tbl['headA'],
				'headB'      => $tbl['headB'],
				'table'      => $tbl['table'],
				'winner'     => $win,
				'confidence' => $ok && null !== FLXADS_V::fn( $r['confidence'] ?? null ) ? FLXADS_V::f( $r['confidence'] ) : null,
				'small'      => FLXADS_V::b( $r['small'] ?? false ),
				'verdict'    => FLXADS_V::s( FLXADS_Experiments::verdict( $e, $r ) ),
				'loserAd'    => $loser,
			);
		}
		return $out;
	}

	/**
	 * Choices of the new-test forms: ads of active campaigns and audiences that can hold out.
	 *
	 * @return FlxadsExperimentChoices
	 */
	public static function experiment_choices(): array {
		$groups = array();
		$ready  = (bool) FLXADS_Spend::meta_ready();
		if ( $ready ) {
			$d = FLXADS_Manager::campaigns( '30' );
			if ( ! is_wp_error( $d ) ) {
				$n = 0;
				foreach ( FLXADS_V::rows( FLXADS_V::a( $d )['rows'] ?? array() ) as $c ) {
					if ( 'ACTIVE' !== FLXADS_V::s( $c['effective'] ?? '' ) || ++$n > 10 ) {
						continue;
					}
					$x = FLXADS_Manager::campaign( FLXADS_V::s( $c['id'] ?? '' ), '30', false );
					if ( is_wp_error( $x ) ) {
						continue;
					}
					$ads = array();
					foreach ( FLXADS_V::rows( FLXADS_V::a( $x )['adsets'] ?? array() ) as $s ) {
						foreach ( FLXADS_V::rows( $s['ads'] ?? array() ) as $a ) {
							$ads[] = array(
								'key'   => FLXADS_V::s( $c['id'] ?? '' ) . ':' . FLXADS_V::s( $a['id'] ?? '' ),
								'label' => FLXADS_V::s( $s['name'] ?? '' ) . ' › ' . FLXADS_V::s( $a['name'] ?? '' ),
							);
						}
					}
					if ( $ads ) {
						$groups[] = array(
							'campaign' => FLXADS_V::s( $c['name'] ?? '' ),
							'ads'      => $ads,
						);
					}
				}
			}
		}
		$auds = array();
		foreach ( FLXADS_V::rows( FLXADS_Audiences::all() ) as $a ) {
			if ( 'custom' === FLXADS_V::s( $a['kind'] ?? '' ) && 'target' === FLXADS_V::s( $a['purpose'] ?? '' ) && empty( $a['holdout'] ) ) {
				$auds[] = array(
					'id'    => FLXADS_V::s( $a['id'] ?? '' ),
					/* translators: 1: name, 2: people */
					'label' => sprintf( __( '%1$s (%2$s people)', 'flx-ads' ), FLXADS_V::s( $a['name'] ?? '' ), number_format_i18n( FLXADS_V::i( $a['count'] ?? 0 ) ) ),
				);
			}
		}
		return array(
			'metaReady'  => $ready,
			'today'      => wp_date( 'Y-m-d' ),
			'defaultEnd' => wp_date( 'Y-m-d', time() + 28 * DAY_IN_SECONDS ),
			'adGroups'   => $groups,
			'audiences'  => $auds,
		);
	}

	/**
	 * Starts an A/B or holdout test. For A/B, `a` and `b` are "campaignId:adId" as the form sends them.
	 *
	 * @param FlxadsExperimentInput $in Input.
	 * @return string|WP_Error The new test id.
	 */
	public static function create_experiment( array $in ) {
		$d = array(
			'type'     => $in['type'],
			'name'     => $in['name'] ?? '',
			'start'    => $in['start'] ?? '',
			'end'      => $in['end'] ?? '',
			'audience' => $in['audience'] ?? '',
			'pct'      => $in['pct'] ?? 10,
		);
		if ( 'ab' === $in['type'] ) {
			$a          = explode( ':', $in['a'] ?? '' );
			$b          = explode( ':', $in['b'] ?? '' );
			$d['campaign'] = ( $a[0] ?? '' ) === ( $b[0] ?? '' ) ? ( $a[0] ?? '' ) : '';
			$d['a']        = $a[1] ?? '';
			$d['b']        = $b[1] ?? '';
		}
		$r = FLXADS_Experiments::create( $d, 'panel' );
		if ( is_wp_error( $r ) ) {
			return FLXADS_V::fail( $r );
		}
		return FLXADS_V::s( FLXADS_V::a( $r )['id'] ?? '' );
	}

	/**
	 * Ends a test; the result is kept.
	 *
	 * @return true|WP_Error
	 */
	public static function finish_experiment( string $id ) {
		$r = FLXADS_Experiments::finish( sanitize_key( $id ) );
		return is_wp_error( $r ) ? FLXADS_V::fail( $r ) : true;
	}

	/**
	 * Deletes a test and its result.
	 *
	 * @return true|WP_Error
	 */
	public static function delete_experiment( string $id ) {
		$r = FLXADS_Experiments::delete( sanitize_key( $id ) );
		return is_wp_error( $r ) ? FLXADS_V::fail( $r ) : true;
	}

	/**
	 * Pauses the losing ad of an A/B test that has a winner.
	 *
	 * @return true|WP_Error
	 */
	public static function pause_loser( string $id ) {
		$e = FLXADS_Experiments::get( sanitize_key( $id ) );
		if ( ! $e ) {
			return FLXADS_V::error( 'flxads_exp', __( 'Test not found.', 'flx-ads' ), 'id', 'NOT_FOUND' );
		}
		$r   = FLXADS_V::a( FLXADS_Experiments::result( FLXADS_V::a( $e ) ) );
		$win = FLXADS_V::s( $r['winner'] ?? '' );
		if ( '' === $win ) {
			return FLXADS_V::error( 'flxads_exp', __( 'There is no winner yet.', 'flx-ads' ), 'id' );
		}
		$loser = FLXADS_V::a( $r[ 'a' === $win ? 'b' : 'a' ] ?? array() );
		$s     = FLXADS_Manager::set_status( FLXADS_V::s( $loser['id'] ?? '' ), 'PAUSED', 'panel' );
		return is_wp_error( $s ) ? FLXADS_V::fail( $s ) : true;
	}
}
