<?php
/**
 * Flux Ads: audiences, the Studio (AI campaign drafts) and the season plan. The classic pages, GraphQL and
 * Flux AI use the same legacy classes (FLXADS_Audiences, FLXADS_Studio, FLXADS_Planner); this service gives
 * GraphQL typed shapes and holds the glue the classic admin-post handlers used to carry. Permission checks
 * stay with the caller.
 *
 * Errors are WP_Error with `graphql_code` INVALID_INPUT / NOT_FOUND and the input `field`.
 *
 * @package FLX_Ads
 * @phpstan-import-type FlxadsMoney from FLXADS_V
 * @phpstan-import-type FlxadsOption from FLXADS_Reports_Service
 *
 * @phpstan-type FlxadsAudience array{id: string, name: string, kind: string, metaId: string, groupLabel: string, purpose: string, purposeLabel: string, count: int, syncedAt: string, error: string, added: int|null, removed: int|null, canSync: bool, holdout: bool}
 * @phpstan-type FlxadsAudienceSource array{key: string, label: string, people: int}
 * @phpstan-type FlxadsAudiencesPage array{crmReady: bool, metaReady: bool, audiences: list<FlxadsAudience>, sources: list<FlxadsAudienceSource>, purposes: list<FlxadsOption>, customAudiences: list<FlxadsOption>}
 * @phpstan-type FlxadsAudienceCreate array{name: string, source: string, purpose: string, lookalike: bool, country: string, ratio: float}
 * @phpstan-type FlxadsAdsetInput array{name: string, ageMin: int, ageMax: int, gender: string, countries: list<string>, audiences: list<string>, exclude: list<string>, share: int}
 * @phpstan-type FlxadsAdInput array{name: string, text: string, headline: string, description: string, cta: string, image: string}
 * @phpstan-type FlxadsDraftInput array{name: string, objective: string, link: string, dailyBudget: float, start: string, end: string, adsets: list<FlxadsAdsetInput>, ads: list<FlxadsAdInput>}
 * @phpstan-type FlxadsDraftRow array{id: string, name: string, status: string, statusLabel: string, statusTone: string, objective: string, objectiveLabel: string, dailyBudget: FlxadsMoney, adsetCount: int, adCount: int, updated: string, metaCampaignId: string, error: string}
 * @phpstan-type FlxadsDraft array{id: string, status: string, name: string, objective: string, link: string, dailyBudget: float, start: string, end: string, adsets: list<FlxadsAdsetInput>, ads: list<FlxadsAdInput>, notes: string, brief: string, sent: bool, error: string, sendSummary: string}
 * @phpstan-type FlxadsStudioPage array{aiReady: bool, missing: list<string>, objectives: list<FlxadsOption>, ctas: list<FlxadsOption>, audiences: list<FlxadsOption>, drafts: list<FlxadsDraftRow>, siteUrl: string}
 * @phpstan-type FlxadsSeasonLast array{payments: int, income: FlxadsMoney, newCount: int, spend: FlxadsMoney, roas: float|null}
 * @phpstan-type FlxadsSeason array{key: string, id: string|null, name: string, start: string, end: string, startLabel: string, endLabel: string, approx: bool, custom: bool, state: string, stateLabel: string, tone: string, days: int, whenLabel: string, hasLast: bool, last: FlxadsSeasonLast, brief: string}
 * @phpstan-type FlxadsSeasonInput array{name: string, start: string, end: string, prep: int}
 */
final class FLXADS_Creative_Service {

	public const CACHE_TAG = 'flx-ads:creative';

	/* ------------------------------------------------------------ Audiences */

	/**
	 * Meta audiences, the CRM groups they can be built from and the choices of the forms.
	 *
	 * @param bool $manager The viewer manages Flux Ads (people counts per group are for them).
	 * @return FlxadsAudiencesPage
	 */
	public static function audiences_page( bool $manager ): array {
		$crm   = (bool) FLXADS_Audiences::crm_ready();
		$meta  = (bool) FLXADS_Spend::meta_ready();
		$all   = FLXADS_V::a( FLXADS_Audiences::all() );
		$src   = FLXADS_Audiences::sources();
		$pur   = FLXADS_Audiences::purposes();
		$rows  = array();
		$custom = array();
		foreach ( $all as $a ) {
			$a    = FLXADS_V::a( $a );
			$kind = FLXADS_V::s( $a['kind'] ?? '' );
			$s    = FLXADS_V::s( $a['source'] ?? '' );
			if ( 'lookalike' === $kind ) {
				$origin = FLXADS_V::a( $all[ substr( $s, 10 ) ] ?? array() );
				/* translators: %s: source audience */
				$group = sprintf( __( 'Similar to: %s', 'flx-ads' ), '' !== FLXADS_V::s( $origin['name'] ?? '' ) ? FLXADS_V::s( $origin['name'] ) : '—' );
			} else {
				$group = $src[ $s ] ?? __( '(removed group)', 'flx-ads' );
			}
			$last   = FLXADS_V::a( $a['last'] ?? array() );
			$purp   = FLXADS_V::s( $a['purpose'] ?? '' );
			$rows[] = array(
				'id'           => FLXADS_V::s( $a['id'] ?? '' ),
				'name'         => FLXADS_V::s( $a['name'] ?? '' ),
				'kind'         => $kind,
				'metaId'       => FLXADS_V::s( $a['meta_id'] ?? '' ),
				'groupLabel'   => $group,
				'purpose'      => $purp,
				'purposeLabel' => $pur[ $purp ] ?? '',
				'count'        => FLXADS_V::i( $a['count'] ?? 0 ),
				'syncedAt'     => FLXADS_V::s( $a['synced'] ?? '' ),
				'error'        => FLXADS_V::s( $a['error'] ?? '' ),
				'added'        => $last ? FLXADS_V::i( $last['added'] ?? 0 ) : null,
				'removed'      => $last ? FLXADS_V::i( $last['removed'] ?? 0 ) : null,
				'canSync'      => 'custom' === $kind,
				'holdout'      => ! empty( $a['holdout'] ),
			);
			if ( 'custom' === $kind ) {
				$custom[] = array(
					'key'   => FLXADS_V::s( $a['id'] ?? '' ),
					'label' => FLXADS_V::s( $a['name'] ?? '' ),
				);
			}
		}
		$sources = array();
		if ( $manager && $crm && $meta ) {
			foreach ( $src as $k => $l ) {
				$sources[] = array(
					'key'    => (string) $k,
					'label'  => (string) $l,
					'people' => count( FLXADS_V::l( FLXADS_Audiences::member_ids( (string) $k ) ) ),
				);
			}
		}
		return array(
			'crmReady'        => $crm,
			'metaReady'       => $meta,
			'audiences'       => $rows,
			'sources'         => $sources,
			'purposes'        => FLXADS_Reports_Service::options( $pur ),
			'customAudiences' => $custom,
		);
	}

	/**
	 * Creates an audience in Meta, fills it and optionally a lookalike.
	 *
	 * @param FlxadsAudienceCreate $in Input.
	 * @return true|WP_Error
	 */
	public static function create_audience( array $in ) {
		$r = FLXADS_Audiences::create(
			array(
				'name'      => $in['name'],
				'source'    => sanitize_text_field( $in['source'] ),
				'purpose'   => sanitize_key( $in['purpose'] ),
				'lookalike' => $in['lookalike'],
				'country'   => sanitize_text_field( $in['country'] ),
				'ratio'     => $in['ratio'],
			)
		);
		if ( is_wp_error( $r ) ) {
			return FLXADS_V::fail( $r );
		}
		$r   = FLXADS_V::a( $r );
		$err = trim( FLXADS_V::s( $r['sync_error'] ?? '' ) . ' ' . FLXADS_V::s( $r['lookalike_error'] ?? '' ) );
		return '' !== $err ? FLXADS_V::error( 'flxads_partial', $err ) : true;
	}

	/**
	 * Lookalike audience from one of the own audiences.
	 *
	 * @return true|WP_Error
	 */
	public static function create_lookalike( string $id, string $country, float $ratio ) {
		$r = FLXADS_Audiences::lookalike( sanitize_key( $id ), sanitize_text_field( $country ), $ratio );
		return is_wp_error( $r ) ? FLXADS_V::fail( $r ) : true;
	}

	/**
	 * Updates an audience in Meta now.
	 *
	 * @return true|WP_Error
	 */
	public static function sync_audience( string $id ) {
		$r = FLXADS_Audiences::sync( sanitize_key( $id ) );
		return is_wp_error( $r ) ? FLXADS_V::fail( $r ) : true;
	}

	/**
	 * Deletes an audience in Meta and here.
	 *
	 * @return true|WP_Error
	 */
	public static function delete_audience( string $id ) {
		$r = FLXADS_Audiences::delete( sanitize_key( $id ) );
		return is_wp_error( $r ) ? FLXADS_V::fail( $r ) : true;
	}

	/* ------------------------------------------------------------ Studio */

	/**
	 * Draft ad sets in the input shape.
	 *
	 * @param mixed $rows Stored ad sets.
	 * @return list<FlxadsAdsetInput>
	 */
	private static function adsets( $rows ): array {
		$out = array();
		foreach ( FLXADS_V::rows( $rows ) as $s ) {
			$out[] = array(
				'name'      => FLXADS_V::s( $s['name'] ?? '' ),
				'ageMin'    => FLXADS_V::i( $s['age_min'] ?? 18, 18 ),
				'ageMax'    => FLXADS_V::i( $s['age_max'] ?? 65, 65 ),
				'gender'    => FLXADS_V::s( $s['gender'] ?? 'all', 'all' ),
				'countries' => FLXADS_V::ls( $s['countries'] ?? array() ),
				'audiences' => FLXADS_V::ls( $s['audiences'] ?? array() ),
				'exclude'   => FLXADS_V::ls( $s['exclude'] ?? array() ),
				'share'     => FLXADS_V::i( $s['share'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * Draft ads in the input shape.
	 *
	 * @param mixed $rows Stored ads.
	 * @return list<FlxadsAdInput>
	 */
	private static function ads( $rows ): array {
		$out = array();
		foreach ( FLXADS_V::rows( $rows ) as $a ) {
			$out[] = array(
				'name'        => FLXADS_V::s( $a['name'] ?? '' ),
				'text'        => FLXADS_V::s( $a['text'] ?? '' ),
				'headline'    => FLXADS_V::s( $a['headline'] ?? '' ),
				'description' => FLXADS_V::s( $a['description'] ?? '' ),
				'cta'         => FLXADS_V::s( $a['cta'] ?? 'DONATE_NOW', 'DONATE_NOW' ),
				'image'       => FLXADS_V::s( $a['image'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * Studio page: what is missing to send, the AI state and the drafts.
	 *
	 * @return FlxadsStudioPage
	 */
	public static function studio_page(): array {
		$st   = FLXADS_Studio::statuses();
		$ob   = FLXADS_Studio::objectives();
		$cur  = FLXADS_Spend::meta_ready() ? FLXADS_V::s( FLXADS_Manager::currency(), FLXADS_Report::base_currency() ) : FLXADS_Report::base_currency();
		$rows = array();
		foreach ( FLXADS_V::a( FLXADS_Studio::all() ) as $d ) {
			$d      = FLXADS_V::a( $d );
			$status = FLXADS_V::s( $d['status'] ?? '' );
			$tone   = array(
				'draft' => 'pending',
				'sent'  => 'sent',
				'error' => 'failed',
			);
			$meta   = FLXADS_V::a( $d['meta'] ?? array() );
			$obj    = FLXADS_V::s( $d['objective'] ?? '' );
			$rows[] = array(
				'id'             => FLXADS_V::s( $d['id'] ?? '' ),
				'name'           => FLXADS_V::s( $d['name'] ?? '' ),
				'status'         => $status,
				'statusLabel'    => $st[ $status ] ?? $status,
				'statusTone'     => $tone[ $status ] ?? 'pending',
				'objective'      => $obj,
				'objectiveLabel' => $ob[ $obj ] ?? $obj,
				'dailyBudget'    => FLXADS_V::money( $d['daily_budget'] ?? 0, $cur ),
				'adsetCount'     => count( FLXADS_V::l( $d['adsets'] ?? array() ) ),
				'adCount'        => count( FLXADS_V::l( $d['ads'] ?? array() ) ),
				'updated'        => FLXADS_V::s( $d['updated'] ?? '' ),
				'metaCampaignId' => 'sent' === $status ? FLXADS_V::s( $meta['campaign'] ?? '' ) : '',
				'error'          => FLXADS_V::s( $d['error'] ?? '' ),
			);
		}
		$auds = array();
		foreach ( FLXADS_V::rows( array_values( FLXADS_V::a( FLXADS_Audiences::all() ) ) ) as $a ) {
			$auds[] = array(
				'key'   => FLXADS_V::s( $a['id'] ?? '' ),
				'label' => FLXADS_V::s( $a['name'] ?? '' ),
			);
		}
		return array(
			'aiReady'    => function_exists( 'flxai_ready' ) && (bool) flxai_ready( 'flx-ads' ),
			'missing'    => FLXADS_V::ls( FLXADS_Studio::missing() ),
			'objectives' => FLXADS_Reports_Service::options( $ob ),
			'ctas'       => FLXADS_Reports_Service::options( FLXADS_Studio::ctas() ),
			'audiences'  => $auds,
			'drafts'     => $rows,
			'siteUrl'    => home_url( '/' ),
		);
	}

	/**
	 * One draft.
	 *
	 * @return FlxadsDraft|null
	 */
	public static function draft( string $id ): ?array {
		$d = FLXADS_Studio::get( $id );
		if ( ! $d ) {
			return null;
		}
		$d      = FLXADS_V::a( $d );
		$status = FLXADS_V::s( $d['status'] ?? '' );
		$sets   = count( FLXADS_V::l( $d['adsets'] ?? array() ) );
		$ads    = count( FLXADS_V::l( $d['ads'] ?? array() ) );
		return array(
			'id'          => FLXADS_V::s( $d['id'] ?? '' ),
			'status'      => $status,
			'name'        => FLXADS_V::s( $d['name'] ?? '' ),
			'objective'   => FLXADS_V::s( $d['objective'] ?? 'OUTCOME_SALES', 'OUTCOME_SALES' ),
			'link'        => FLXADS_V::s( $d['link'] ?? '' ),
			'dailyBudget' => FLXADS_V::f( $d['daily_budget'] ?? 0 ),
			'start'       => FLXADS_V::s( $d['start'] ?? '' ),
			'end'         => FLXADS_V::s( $d['end'] ?? '' ),
			'adsets'      => self::adsets( $d['adsets'] ?? array() ),
			'ads'         => self::ads( $d['ads'] ?? array() ),
			'notes'       => FLXADS_V::s( $d['notes'] ?? '' ),
			'brief'       => FLXADS_V::s( $d['brief'] ?? '' ),
			'sent'        => 'sent' === $status,
			'error'       => FLXADS_V::s( $d['error'] ?? '' ),
			/* translators: 1: ad sets, 2: ads */
			'sendSummary' => sprintf( __( 'A paused campaign with %1$d ad sets and %2$d ads is created in Meta. Nothing is shown or spent until you turn it on under Campaigns.', 'flx-ads' ), $sets, $sets * $ads ),
		);
	}

	/**
	 * Draft input → the legacy field names Studio cleans.
	 *
	 * @param FlxadsDraftInput $in Input.
	 * @return array<string,mixed>
	 */
	private static function legacy_draft( array $in ): array {
		$sets = array();
		foreach ( $in['adsets'] as $s ) {
			$sets[] = array(
				'name'      => $s['name'],
				'age_min'   => $s['ageMin'],
				'age_max'   => $s['ageMax'],
				'gender'    => $s['gender'],
				'countries' => $s['countries'],
				'audiences' => $s['audiences'],
				'exclude'   => $s['exclude'],
				'share'     => $s['share'],
			);
		}
		return array(
			'name'         => $in['name'],
			'objective'    => $in['objective'],
			'link'         => $in['link'],
			'daily_budget' => $in['dailyBudget'],
			'start'        => $in['start'],
			'end'          => $in['end'],
			'adsets'       => $sets,
			'ads'          => $in['ads'],
		);
	}

	/**
	 * Saves a draft (new when `$id` is empty).
	 *
	 * @param FlxadsDraftInput $in Input.
	 * @return string|WP_Error The draft id.
	 */
	public static function save_draft( string $id, array $in ) {
		$r = FLXADS_Studio::save( self::legacy_draft( $in ), $id );
		return is_wp_error( $r ) ? FLXADS_V::fail( $r ) : FLXADS_V::s( $r );
	}

	/**
	 * Writes a draft with Flux AI from a brief.
	 *
	 * @param array{brief: string, link: string, dailyBudget: float, objective: string, start: string, end: string} $in Brief.
	 * @return string|WP_Error The draft id.
	 */
	public static function generate_draft( array $in ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 90 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$r = FLXADS_Studio::generate(
			array(
				'brief'        => $in['brief'],
				'link'         => $in['link'],
				'daily_budget' => $in['dailyBudget'],
				'objective'    => $in['objective'],
				'start'        => $in['start'],
				'end'          => $in['end'],
			)
		);
		return is_wp_error( $r ) ? FLXADS_V::fail( $r ) : FLXADS_V::s( $r );
	}

	/**
	 * Copies a draft.
	 *
	 * @return string|WP_Error The new draft id.
	 */
	public static function duplicate_draft( string $id ) {
		$r = FLXADS_Studio::duplicate( $id );
		return is_wp_error( $r ) ? FLXADS_V::fail( $r ) : FLXADS_V::s( $r );
	}

	/**
	 * Deletes a draft (the campaign it created in Meta stays).
	 *
	 * @return true|WP_Error
	 */
	public static function delete_draft( string $id ) {
		if ( ! FLXADS_Studio::delete( $id ) ) {
			return FLXADS_V::error( 'flxads_draft', __( 'Draft not found.', 'flx-ads' ), 'id', 'NOT_FOUND' );
		}
		return true;
	}

	/**
	 * Creates the draft in Meta, paused.
	 *
	 * @return true|WP_Error
	 */
	public static function send_draft( string $id ) {
		$r = FLXADS_Studio::send( sanitize_key( $id ), 'panel' );
		return is_wp_error( $r ) ? FLXADS_V::fail( $r ) : true;
	}

	/* ------------------------------------------------------------ Season plan */

	/**
	 * Giving seasons of the next 12 months with what the same period brought last year.
	 *
	 * @return list<FlxadsSeason>
	 */
	public static function seasons(): array {
		$out   = array();
		$state = array(
			'now'   => array( __( 'Now', 'flx-ads' ), 'good' ),
			'prep'  => array( __( 'Prepare now', 'flx-ads' ), 'warn' ),
			'later' => array( __( 'Later', 'flx-ads' ), 'info' ),
		);
		foreach ( FLXADS_V::rows( FLXADS_Planner::upcoming( 365 ) ) as $e ) {
			$l     = FLXADS_V::a( $e['last'] ?? array() );
			$s     = $state[ FLXADS_V::s( $e['state'] ?? 'later' ) ] ?? $state['later'];
			$days  = FLXADS_V::i( $e['days'] ?? 0 );
			$base  = FLXADS_V::s( $l['base'] ?? '', FLXADS_Report::base_currency() );
			$spend = FLXADS_V::f( $l['spend'] ?? 0 );
			$inc   = FLXADS_V::f( $l['income'] ?? 0 );
			$pay   = FLXADS_V::i( $l['payments'] ?? 0 );
			$out[] = array(
				'key'        => FLXADS_V::s( $e['key'] ?? '' ),
				'id'         => FLXADS_V::sn( $e['id'] ?? '' ),
				'name'       => FLXADS_V::s( $e['name'] ?? '' ),
				'start'      => FLXADS_V::s( $e['start'] ?? '' ),
				'end'        => FLXADS_V::s( $e['end'] ?? '' ),
				'startLabel' => FLXADS_V::s( flxads_date( FLXADS_V::s( $e['start'] ?? '' ), 'date' ) ),
				'endLabel'   => FLXADS_V::s( flxads_date( FLXADS_V::s( $e['end'] ?? '' ), 'date' ) ),
				'approx'     => FLXADS_V::b( $e['approx'] ?? false ),
				'custom'     => FLXADS_V::b( $e['custom'] ?? false ),
				'state'      => FLXADS_V::s( $e['state'] ?? '' ),
				'stateLabel' => $s[0],
				'tone'       => $s[1],
				'days'       => $days,
				/* translators: %s: days */
				'whenLabel'  => 'now' === FLXADS_V::s( $e['state'] ?? '' ) ? __( 'running', 'flx-ads' ) : sprintf( _n( 'in %s day', 'in %s days', $days, 'flx-ads' ), number_format_i18n( $days ) ),
				'hasLast'    => $pay > 0 || $spend > 0,
				'last'       => array(
					'payments' => $pay,
					'income'   => FLXADS_V::money( $inc, $base ),
					'newCount' => FLXADS_V::i( $l['new'] ?? 0 ),
					'spend'    => FLXADS_V::money( $spend, $base ),
					'roas'     => $spend > 0 ? $inc / $spend : null,
				),
				'brief'      => FLXADS_V::s( FLXADS_Planner::brief( $e ) ),
			);
		}
		return $out;
	}

	/**
	 * Adds a campaign day of your own.
	 *
	 * @param FlxadsSeasonInput $in Input.
	 * @return true|WP_Error
	 */
	public static function add_season( array $in ) {
		$r = FLXADS_Planner::add_custom( $in );
		return is_wp_error( $r ) ? FLXADS_V::fail( $r ) : true;
	}

	/**
	 * Deletes a date of your own.
	 *
	 * @return true|WP_Error
	 */
	public static function delete_season( string $id ) {
		$r = FLXADS_Planner::delete_custom( sanitize_key( $id ) );
		return is_wp_error( $r ) ? FLXADS_V::fail( $r ) : true;
	}
}
