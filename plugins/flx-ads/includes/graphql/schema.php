<?php
/**
 * Flux Ads GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a GraphQL
 * request. Resolvers call the FLXADS_*_Service classes; the Ad conversions screen (wp-admin and the
 * FluxWP app) reads and writes only through these fields.
 *
 * Secrets (access tokens, API secrets) are write-only: reads return whether one is set, never the value.
 * Ad platform OAuth flows and webhooks are not here (docs/graphql/ISTISNALAR.md).
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/types.php';
require_once __DIR__ . '/types-meta.php';
require_once __DIR__ . '/types-creative.php';

/**
 * Service result → value, or the WP_Error as a typed GraphQL error.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxads_gql_ok( $r ) {
	if ( $r instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $r );
	}
	return $r;
}

/**
 * Viewer reads: Flux Ads view or manage permission, or an administrator.
 */
function flxads_gql_view(): Closure {
	return flx_gql_any_cap( 'flxads_view', 'flxads_manage', 'manage_options' );
}

/**
 * Changes: Flux Ads manage permission, or an administrator.
 */
function flxads_gql_manage(): Closure {
	return flx_gql_any_cap( 'flxads_manage', 'manage_options' );
}

/**
 * List value (a non-list becomes an empty list).
 *
 * @param mixed $v Value.
 * @return list<mixed>
 */
function flxads_gql_list( $v ): array {
	return is_array( $v ) ? array_values( $v ) : array();
}

/**
 * Typed report filter from the `filter` argument.
 *
 * @param mixed $raw Argument.
 * @return array<string,string>
 */
function flxads_gql_filter( $raw ): array {
	$in  = flx_gql_arr( $raw );
	$out = array();
	foreach ( array( 'range', 'from', 'to', 'kind', 'channel', 'by' ) as $k ) {
		if ( isset( $in[ $k ] ) && is_string( $in[ $k ] ) && '' !== $in[ $k ] ) {
			$out[ $k ] = $in[ $k ];
		}
	}
	return $out;
}

/**
 * Argument definition shared by the report-like queries.
 *
 * @return array<string,array<string,string>>
 */
function flxads_gql_filter_arg(): array {
	return array(
		'filter' => array(
			'type'        => 'FlxAdsFilterInput',
			'description' => __( 'Period, kind, channel and grouping. Empty uses the screen\'s defaults.', 'flx-ads' ),
		),
	);
}

/**
 * Output of a mutation that only says it worked.
 *
 * @return array<string,array<string,mixed>>
 */
function flxads_gql_done(): array {
	return array(
		'ok' => array(
			'type'        => 'Boolean!',
			'description' => __( 'The change was made.', 'flx-ads' ),
			'resolve'     => static fn( $p ): bool => ! empty( flx_gql_arr( $p )['ok'] ),
		),
	);
}

/**
 * Registers a query with the shared view cap.
 *
 * @param string              $name Field name.
 * @param string              $type Type notation.
 * @param string              $desc Description.
 * @param callable            $fn   Resolver ( $root, $args ).
 * @param array<string,mixed> $args Arguments.
 * @param bool                $manage The query needs the manage cap.
 */
function flxads_gql_q( string $name, string $type, string $desc, callable $fn, array $args = array(), bool $manage = false ): void {
	flx_gql_query(
		$name,
		array(
			'type'        => $type,
			'description' => $desc,
			'cap'         => $manage ? flxads_gql_manage() : flxads_gql_view(),
			'args'        => $args,
			'resolve'     => $fn,
		)
	);
}

/**
 * Registers a mutation with the manage cap.
 *
 * @param string                                   $name   Mutation name.
 * @param string                                   $desc   Description.
 * @param array<string,array<string,string>>       $input  Input fields.
 * @param array<string,array<string,mixed>>        $output Output fields.
 * @param callable                                 $fn     Resolver ( $input ).
 * @param bool                                     $manage The mutation needs the manage cap (default); false = view cap.
 */
function flxads_gql_m( string $name, string $desc, array $input, array $output, callable $fn, bool $manage = true ): void {
	flx_gql_mutation(
		$name,
		array(
			'description' => $desc,
			'cap'         => $manage ? flxads_gql_manage() : flxads_gql_view(),
			'input'       => $input,
			'output'      => $output,
			'resolve'     => $fn,
		)
	);
}

/**
 * One String! id input.
 *
 * @param string $desc Description.
 * @return array<string,array<string,string>>
 */
function flxads_gql_id_input( string $desc ): array {
	return array(
		'id' => array(
			'type'        => 'String!',
			'description' => $desc,
		),
	);
}

/* ---- Queries ---- */

flxads_gql_q(
	'flxAdsContext',
	'FlxAdsContext!',
	__( 'What the Flux Ads screen needs before it draws.', 'flx-ads' ),
	static fn(): array => FLXADS_Settings_Service::context()
);

flxads_gql_q(
	'flxAdsReport',
	'FlxAdsReport!',
	__( 'The conversion report: which ad, campaign and ad text brought which donation.', 'flx-ads' ),
	static fn( $root, array $args ): array => FLXADS_Reports_Service::report( flxads_gql_filter( $args['filter'] ?? null ) ),
	flxads_gql_filter_arg()
);

flxads_gql_q(
	'flxAdsSpend',
	'FlxAdsSpendPage!',
	__( 'Ad spend by campaign, and the rows entered by hand (managers).', 'flx-ads' ),
	static fn( $root, array $args ): array => FLXADS_Reports_Service::spend_page( flxads_gql_filter( $args['filter'] ?? null ), flxads_can( true ) ),
	flxads_gql_filter_arg()
);

flxads_gql_q(
	'flxAdsValue',
	'FlxAdsValuePage!',
	__( 'Donor value by acquisition group, and the cohort table.', 'flx-ads' ),
	static fn( $root, array $args ): array => FLXADS_Reports_Service::value_page( flxads_gql_filter( $args['filter'] ?? null ) ),
	flxads_gql_filter_arg()
);

flxads_gql_q(
	'flxAdsSendLog',
	'FlxAdsSendLog!',
	__( 'Conversions sent to the ad platforms, and the ones that failed.', 'flx-ads' ),
	static fn(): array => FLXADS_Reports_Service::send_log()
);

flxads_gql_q(
	'flxAdsLinkBuilder',
	'FlxAdsLinkBuilder!',
	__( 'What the tracked link builder offers.', 'flx-ads' ),
	static fn(): array => FLXADS_Reports_Service::link_builder()
);

flxads_gql_q(
	'flxAdsAudiences',
	'FlxAdsAudiencesPage!',
	__( 'Audiences kept in Meta, and the CRM groups they can be built from.', 'flx-ads' ),
	static fn(): array => FLXADS_Creative_Service::audiences_page( flxads_can( true ) )
);

flxads_gql_q(
	'flxAdsCampaigns',
	'FlxAdsCampaignList!',
	__( 'Meta campaigns with their numbers and the income they brought on the site.', 'flx-ads' ),
	static fn( $root, array $args ): array => FLXADS_Meta_Service::campaigns( flx_gql_str( $args['range'] ?? '' ) ),
	array(
		'range' => array(
			'type'        => 'String',
			'description' => __( 'Period key from FlxAdsCampaignList.ranges; empty is the default.', 'flx-ads' ),
		),
	)
);

flxads_gql_q(
	'flxAdsCampaign',
	'FlxAdsCampaignDetail!',
	__( 'One campaign: ad sets, ads, age/gender and placement.', 'flx-ads' ),
	static fn( $root, array $args ): array => FLXADS_Meta_Service::campaign( flx_gql_str( $args['id'] ?? '' ), flx_gql_str( $args['range'] ?? '' ) ),
	array(
		'id'    => array(
			'type'        => 'String!',
			'description' => __( 'Meta campaign ID.', 'flx-ads' ),
		),
		'range' => array(
			'type'        => 'String',
			'description' => __( 'Period key; empty is the default.', 'flx-ads' ),
		),
	)
);

flxads_gql_q(
	'flxAdsChangeHistory',
	'[FlxAdsChange!]!',
	__( 'Changes made to the ad account from Flux Ads, newest first.', 'flx-ads' ),
	static fn( $root, array $args ): array => FLXADS_Meta_Service::history( max( 1, min( 100, flx_gql_int( $args['limit'] ?? 15 ) ) ) ),
	array(
		'limit' => array(
			'type'        => 'Int',
			'description' => __( 'How many (1–100). Default 15.', 'flx-ads' ),
		),
	)
);

flxads_gql_q(
	'flxAdsSuggestions',
	'FlxAdsSuggestionsPage!',
	__( 'Budget and pause suggestions from the optimizer, and season reminders.', 'flx-ads' ),
	static fn(): array => FLXADS_Meta_Service::suggestions()
);

flxads_gql_q(
	'flxAdsExperiments',
	'[FlxAdsExperiment!]!',
	__( 'Tests: two ads side by side, or a group held out of the ads.', 'flx-ads' ),
	static fn(): array => FLXADS_Meta_Service::experiments()
);

flxads_gql_q(
	'flxAdsExperimentChoices',
	'FlxAdsExperimentChoices!',
	__( 'What the new test form offers.', 'flx-ads' ),
	static fn(): array => FLXADS_Meta_Service::experiment_choices()
);

flxads_gql_q(
	'flxAdsSeasons',
	'[FlxAdsSeason!]!',
	__( 'Periods when giving and sales rise, with last year\'s numbers.', 'flx-ads' ),
	static fn(): array => FLXADS_Creative_Service::seasons()
);

flxads_gql_q(
	'flxAdsStudio',
	'FlxAdsStudioPage!',
	__( 'Studio: what the editor offers and the drafts.', 'flx-ads' ),
	static fn(): array => FLXADS_Creative_Service::studio_page()
);

flxads_gql_q(
	'flxAdsDraft',
	'FlxAdsDraft',
	__( 'One Studio draft; empty when it does not exist.', 'flx-ads' ),
	static fn( $root, array $args ): ?array => FLXADS_Creative_Service::draft( flx_gql_str( $args['id'] ?? '' ) ),
	flxads_gql_id_input( __( 'Draft ID.', 'flx-ads' ) )
);

flxads_gql_q(
	'flxAdsSettings',
	'FlxAdsSettings!',
	__( 'Tracking settings. Secrets are never returned, only whether one is set.', 'flx-ads' ),
	static fn(): array => FLXADS_Settings_Service::get(),
	array(),
	true
);

/* ---- Mutations ---- */

flxads_gql_m(
	'flxAdsHeartbeat',
	__( 'What the classic page does when it opens: send due conversions and read ad spend. The screen calls it now and then. Nothing is written to an ad account.', 'flx-ads' ),
	array(),
	flxads_gql_done() + array(
		'openSuggestions' => array(
			'type'        => 'Int!',
			'description' => __( 'Suggestions waiting for a decision.', 'flx-ads' ),
		),
	),
	static fn(): array => FLXADS_Settings_Service::heartbeat(),
	false
);

flxads_gql_m(
	'flxAdsSettingsSave',
	__( 'Saves tracking settings. A secret left empty keeps the stored one; "__clear__" removes it.', 'flx-ads' ),
	array(
		'settings' => array(
			'type'        => 'FlxAdsSettingsInput!',
			'description' => __( 'Settings to save.', 'flx-ads' ),
		),
	),
	array(
		'settings' => array(
			'type'        => 'FlxAdsSettings!',
			'description' => __( 'Settings after the change.', 'flx-ads' ),
			'resolve'     => static fn(): array => FLXADS_Settings_Service::get(),
		),
	),
	static function ( array $input ): array {
		FLXADS_Settings_Service::save( flx_gql_arr( $input['settings'] ?? array() ) );
		return array();
	}
);

flxads_gql_m(
	'flxAdsConnectionTest',
	__( 'Tests one platform with a test event (Meta, TikTok) or the validation endpoint (GA4); nothing is recorded. Any settings sent are saved first, as the classic Test button does.', 'flx-ads' ),
	array(
		'platform' => array(
			'type'        => 'FlxAdsPlatform!',
			'description' => __( 'Platform to test.', 'flx-ads' ),
		),
		'settings' => array(
			'type'        => 'FlxAdsSettingsInput',
			'description' => __( 'Form values to save before testing.', 'flx-ads' ),
		),
	),
	array(
		'test'     => array(
			'type'        => 'FlxAdsTest',
			'description' => __( 'What the platform answered.', 'flx-ads' ),
		),
		'settings' => array(
			'type'        => 'FlxAdsSettings!',
			'description' => __( 'Settings after the test.', 'flx-ads' ),
			'resolve'     => static fn(): array => FLXADS_Settings_Service::get(),
		),
	),
	static function ( array $input ) {
		$in = isset( $input['settings'] ) ? flx_gql_arr( $input['settings'] ) : null;
		$t  = FLXADS_Settings_Service::test( flx_gql_str( $input['platform'] ?? '' ), $in );
		return $t instanceof WP_Error ? $t : array( 'test' => $t );
	}
);

flxads_gql_m(
	'flxAdsSpendSync',
	__( 'Reads ad spend from Meta now.', 'flx-ads' ),
	array(),
	array(
		'status' => array(
			'type'        => 'FlxAdsMetaStatus',
			'description' => __( 'The result of the read.', 'flx-ads' ),
		),
	),
	static fn(): array => array( 'status' => FLXADS_Reports_Service::sync_spend() )
);

flxads_gql_m(
	'flxAdsSpendAdd',
	__( 'Adds a spend row by hand (Google, TikTok or anything not read from Meta).', 'flx-ads' ),
	array(
		'channel'  => array(
			'type'        => 'String!',
			'description' => __( 'Channel key from FlxAdsSpendPage.channels.', 'flx-ads' ),
		),
		'day'      => array(
			'type'        => 'String!',
			'description' => __( 'Day (Y-m-d).', 'flx-ads' ),
		),
		'campaign' => array(
			'type'        => 'String',
			'description' => __( 'Campaign name.', 'flx-ads' ),
		),
		'ad'       => array(
			'type'        => 'String',
			'description' => __( 'Ad name.', 'flx-ads' ),
		),
		'spend'    => array(
			'type'        => 'String!',
			'description' => __( 'Amount as typed.', 'flx-ads' ),
		),
		'currency' => array(
			'type'        => 'String',
			'description' => __( 'ISO currency; empty is the shop currency.', 'flx-ads' ),
		),
	),
	flxads_gql_done(),
	static function ( array $input ) {
		$r = FLXADS_Reports_Service::add_spend(
			array(
				'channel'  => flx_gql_str( $input['channel'] ?? '' ),
				'day'      => flx_gql_str( $input['day'] ?? '' ),
				'campaign' => flx_gql_str( $input['campaign'] ?? '' ),
				'ad'       => flx_gql_str( $input['ad'] ?? '' ),
				'spend'    => flx_gql_str( $input['spend'] ?? '' ),
				'currency' => flx_gql_str( $input['currency'] ?? '' ),
			)
		);
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

flxads_gql_m(
	'flxAdsSpendDelete',
	__( 'Deletes a spend row entered by hand.', 'flx-ads' ),
	array(
		'id' => array(
			'type'        => 'Int!',
			'description' => __( 'Row ID.', 'flx-ads' ),
		),
	),
	flxads_gql_done(),
	static function ( array $input ) {
		$r = FLXADS_Reports_Service::delete_spend( flx_gql_int( $input['id'] ?? 0 ) );
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

flxads_gql_m(
	'flxAdsSendRetry',
	__( 'Sends a failed conversion again.', 'flx-ads' ),
	array(
		'id' => array(
			'type'        => 'Int!',
			'description' => __( 'Log row ID.', 'flx-ads' ),
		),
	),
	flxads_gql_done(),
	static function ( array $input ) {
		$r = FLXADS_Reports_Service::retry( flx_gql_int( $input['id'] ?? 0 ) );
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

/* Audiences */

flxads_gql_m(
	'flxAdsAudienceCreate',
	__( 'Creates an audience in Meta from a CRM group, fills it, and optionally a lookalike.', 'flx-ads' ),
	array(
		'audience' => array(
			'type'        => 'FlxAdsAudienceInput!',
			'description' => __( 'The audience.', 'flx-ads' ),
		),
	),
	flxads_gql_done(),
	static function ( array $input ) {
		$a = flx_gql_arr( $input['audience'] ?? array() );
		$r = FLXADS_Creative_Service::create_audience(
			array(
				'name'      => flx_gql_str( $a['name'] ?? '' ),
				'source'    => flx_gql_str( $a['source'] ?? '' ),
				'purpose'   => flx_gql_str( $a['purpose'] ?? '' ),
				'lookalike' => ! empty( $a['lookalike'] ),
				'country'   => flx_gql_str( $a['country'] ?? '' ),
				'ratio'     => is_numeric( $a['ratio'] ?? null ) ? (float) $a['ratio'] : 1.0,
			)
		);
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

flxads_gql_m(
	'flxAdsAudienceLookalike',
	__( 'Creates a lookalike audience from one of your own.', 'flx-ads' ),
	flxads_gql_id_input( __( 'Audience ID to start from.', 'flx-ads' ) ) + array(
		'country' => array(
			'type'        => 'String!',
			'description' => __( 'Country code.', 'flx-ads' ),
		),
		'ratio'   => array(
			'type'        => 'Float!',
			'description' => __( 'Size in percent (1–10).', 'flx-ads' ),
		),
	),
	flxads_gql_done(),
	static function ( array $input ) {
		$r = FLXADS_Creative_Service::create_lookalike( flx_gql_str( $input['id'] ?? '' ), flx_gql_str( $input['country'] ?? '' ), is_numeric( $input['ratio'] ?? null ) ? (float) $input['ratio'] : 1.0 );
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

flxads_gql_m(
	'flxAdsAudienceSync',
	__( 'Sends the current CRM group to the audience in Meta.', 'flx-ads' ),
	flxads_gql_id_input( __( 'Audience ID.', 'flx-ads' ) ),
	flxads_gql_done(),
	static function ( array $input ) {
		$r = FLXADS_Creative_Service::sync_audience( flx_gql_str( $input['id'] ?? '' ) );
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

flxads_gql_m(
	'flxAdsAudienceDelete',
	__( 'Deletes an audience in Meta and in Flux Ads.', 'flx-ads' ),
	flxads_gql_id_input( __( 'Audience ID.', 'flx-ads' ) ),
	flxads_gql_done(),
	static function ( array $input ) {
		$r = FLXADS_Creative_Service::delete_audience( flx_gql_str( $input['id'] ?? '' ) );
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

/* Campaigns, suggestions */

flxads_gql_m(
	'flxAdsCampaignStatus',
	__( 'Pauses or resumes a campaign, ad set or ad in Meta.', 'flx-ads' ),
	flxads_gql_id_input( __( 'Meta ID.', 'flx-ads' ) ) + array(
		'status' => array(
			'type'        => 'FlxAdsCampaignStatus!',
			'description' => __( 'The new status.', 'flx-ads' ),
		),
	),
	flxads_gql_done(),
	static function ( array $input ) {
		$r = FLXADS_Meta_Service::set_status( flx_gql_str( $input['id'] ?? '' ), flx_gql_str( $input['status'] ?? '' ) );
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

flxads_gql_m(
	'flxAdsCampaignBudget',
	__( 'Changes the daily budget of a campaign or ad set in Meta.', 'flx-ads' ),
	flxads_gql_id_input( __( 'Meta ID.', 'flx-ads' ) ) + array(
		'amount' => array(
			'type'        => 'Float!',
			'description' => __( 'New daily budget in the ad account currency.', 'flx-ads' ),
		),
	),
	flxads_gql_done(),
	static function ( array $input ) {
		$r = FLXADS_Meta_Service::set_budget( flx_gql_str( $input['id'] ?? '' ), is_numeric( $input['amount'] ?? null ) ? (float) $input['amount'] : 0.0 );
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

flxads_gql_m(
	'flxAdsSuggestionsRefresh',
	__( 'Looks at the numbers again and writes new suggestions.', 'flx-ads' ),
	array(),
	array(
		'newCount' => array(
			'type'        => 'Int',
			'description' => __( 'New suggestions.', 'flx-ads' ),
		),
	),
	static function () {
		$r = FLXADS_Meta_Service::refresh_suggestions();
		return $r instanceof WP_Error ? $r : array( 'newCount' => (int) $r );
	}
);

flxads_gql_m(
	'flxAdsSuggestionApply',
	__( 'Applies a suggestion to the ad account.', 'flx-ads' ),
	flxads_gql_id_input( __( 'Suggestion ID.', 'flx-ads' ) ),
	flxads_gql_done(),
	static function ( array $input ) {
		$r = FLXADS_Meta_Service::apply_suggestion( flx_gql_str( $input['id'] ?? '' ) );
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

flxads_gql_m(
	'flxAdsSuggestionDismiss',
	__( 'Dismisses a suggestion.', 'flx-ads' ),
	flxads_gql_id_input( __( 'Suggestion ID.', 'flx-ads' ) ),
	flxads_gql_done(),
	static function ( array $input ) {
		$r = FLXADS_Meta_Service::dismiss_suggestion( flx_gql_str( $input['id'] ?? '' ) );
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

/* Season plan */

flxads_gql_m(
	'flxAdsSeasonAdd',
	__( 'Adds a date of your own to the season plan.', 'flx-ads' ),
	array(
		'season' => array(
			'type'        => 'FlxAdsSeasonInput!',
			'description' => __( 'The date.', 'flx-ads' ),
		),
	),
	flxads_gql_done(),
	static function ( array $input ) {
		$s = flx_gql_arr( $input['season'] ?? array() );
		$r = FLXADS_Creative_Service::add_season(
			array(
				'name'  => flx_gql_str( $s['name'] ?? '' ),
				'start' => flx_gql_str( $s['start'] ?? '' ),
				'end'   => flx_gql_str( $s['end'] ?? '' ),
				'prep'  => flx_gql_int( $s['prep'] ?? 14, 14 ),
			)
		);
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

flxads_gql_m(
	'flxAdsSeasonDelete',
	__( 'Deletes a date you added.', 'flx-ads' ),
	flxads_gql_id_input( __( 'Season ID (FlxAdsSeason.id).', 'flx-ads' ) ),
	flxads_gql_done(),
	static function ( array $input ) {
		$r = FLXADS_Creative_Service::delete_season( flx_gql_str( $input['id'] ?? '' ) );
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

/* Experiments */

flxads_gql_m(
	'flxAdsExperimentCreate',
	__( 'Starts a test. It only watches; it changes nothing in the ad account.', 'flx-ads' ),
	array(
		'experiment' => array(
			'type'        => 'FlxAdsExperimentInput!',
			'description' => __( 'The test.', 'flx-ads' ),
		),
	),
	array(
		'id' => array(
			'type'        => 'String',
			'description' => __( 'New test ID; empty when the input was refused.', 'flx-ads' ),
		),
	),
	static function ( array $input ) {
		$e  = flx_gql_arr( $input['experiment'] ?? array() );
		$in = array( 'type' => flx_gql_str( $e['type'] ?? '' ) );
		if ( isset( $e['name'] ) ) {
			$in['name'] = flx_gql_str( $e['name'] );
		}
		if ( isset( $e['start'] ) ) {
			$in['start'] = flx_gql_str( $e['start'] );
		}
		if ( isset( $e['end'] ) ) {
			$in['end'] = flx_gql_str( $e['end'] );
		}
		if ( isset( $e['a'] ) ) {
			$in['a'] = flx_gql_str( $e['a'] );
		}
		if ( isset( $e['b'] ) ) {
			$in['b'] = flx_gql_str( $e['b'] );
		}
		if ( isset( $e['audience'] ) ) {
			$in['audience'] = flx_gql_str( $e['audience'] );
		}
		if ( isset( $e['pct'] ) ) {
			$in['pct'] = flx_gql_int( $e['pct'] );
		}
		$r = FLXADS_Meta_Service::create_experiment( $in );
		return $r instanceof WP_Error ? $r : array( 'id' => $r );
	}
);

flxads_gql_m(
	'flxAdsExperimentFinish',
	__( 'Ends a test now.', 'flx-ads' ),
	flxads_gql_id_input( __( 'Test ID.', 'flx-ads' ) ),
	flxads_gql_done(),
	static function ( array $input ) {
		$r = FLXADS_Meta_Service::finish_experiment( flx_gql_str( $input['id'] ?? '' ) );
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

flxads_gql_m(
	'flxAdsExperimentDelete',
	__( 'Deletes a test.', 'flx-ads' ),
	flxads_gql_id_input( __( 'Test ID.', 'flx-ads' ) ),
	flxads_gql_done(),
	static function ( array $input ) {
		$r = FLXADS_Meta_Service::delete_experiment( flx_gql_str( $input['id'] ?? '' ) );
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

flxads_gql_m(
	'flxAdsExperimentPauseLoser',
	__( 'Pauses the weaker ad of a finished test in Meta.', 'flx-ads' ),
	flxads_gql_id_input( __( 'Test ID.', 'flx-ads' ) ),
	flxads_gql_done(),
	static function ( array $input ) {
		$r = FLXADS_Meta_Service::pause_loser( flx_gql_str( $input['id'] ?? '' ) );
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

/* Studio */

/**
 * Typed draft from the input.
 *
 * @param mixed $raw Input.
 * @return array{name: string, objective: string, link: string, dailyBudget: float, start: string, end: string, adsets: list<array{name: string, ageMin: int, ageMax: int, gender: string, countries: list<string>, audiences: list<string>, exclude: list<string>, share: int}>, ads: list<array{name: string, text: string, headline: string, description: string, cta: string, image: string}>}
 */
function flxads_gql_draft_input( $raw ): array {
	$d     = flx_gql_arr( $raw );
	$sets  = array();
	foreach ( flxads_gql_list( $d['adsets'] ?? array() ) as $s ) {
		$s      = flx_gql_arr( $s );
		$sets[] = array(
			'name'      => flx_gql_str( $s['name'] ?? '' ),
			'ageMin'    => flx_gql_int( $s['ageMin'] ?? 18, 18 ),
			'ageMax'    => flx_gql_int( $s['ageMax'] ?? 65, 65 ),
			'gender'    => flx_gql_str( $s['gender'] ?? 'all', 'all' ),
			'countries' => array_map( 'flx_gql_str', flxads_gql_list( $s['countries'] ?? array() ) ),
			'audiences' => array_map( 'flx_gql_str', flxads_gql_list( $s['audiences'] ?? array() ) ),
			'exclude'   => array_map( 'flx_gql_str', flxads_gql_list( $s['exclude'] ?? array() ) ),
			'share'     => flx_gql_int( $s['share'] ?? 100, 100 ),
		);
	}
	$ads = array();
	foreach ( flxads_gql_list( $d['ads'] ?? array() ) as $a ) {
		$a     = flx_gql_arr( $a );
		$ads[] = array(
			'name'        => flx_gql_str( $a['name'] ?? '' ),
			'text'        => flx_gql_str( $a['text'] ?? '' ),
			'headline'    => flx_gql_str( $a['headline'] ?? '' ),
			'description' => flx_gql_str( $a['description'] ?? '' ),
			'cta'         => flx_gql_str( $a['cta'] ?? '' ),
			'image'       => flx_gql_str( $a['image'] ?? '' ),
		);
	}
	return array(
		'name'        => flx_gql_str( $d['name'] ?? '' ),
		'objective'   => flx_gql_str( $d['objective'] ?? '' ),
		'link'        => flx_gql_str( $d['link'] ?? '' ),
		'dailyBudget' => is_numeric( $d['dailyBudget'] ?? null ) ? (float) $d['dailyBudget'] : 0.0,
		'start'       => flx_gql_str( $d['start'] ?? '' ),
		'end'         => flx_gql_str( $d['end'] ?? '' ),
		'adsets'      => $sets,
		'ads'         => $ads,
	);
}

flxads_gql_m(
	'flxAdsDraftSave',
	__( 'Saves a Studio draft; a new one when no ID is given. Nothing reaches Meta.', 'flx-ads' ),
	array(
		'id'    => array(
			'type'        => 'String',
			'description' => __( 'Draft ID; empty creates a new draft.', 'flx-ads' ),
		),
		'draft' => array(
			'type'        => 'FlxAdsDraftInput!',
			'description' => __( 'The draft.', 'flx-ads' ),
		),
	),
	array(
		'id'    => array(
			'type'        => 'String',
			'description' => __( 'Draft ID.', 'flx-ads' ),
		),
		'draft' => array(
			'type'        => 'FlxAdsDraft',
			'description' => __( 'The saved draft.', 'flx-ads' ),
			'resolve'     => static fn( $p ): ?array => FLXADS_Creative_Service::draft( flx_gql_str( flx_gql_arr( $p )['id'] ?? '' ) ),
		),
	),
	static function ( array $input ) {
		$r = FLXADS_Creative_Service::save_draft( flx_gql_str( $input['id'] ?? '' ), flxads_gql_draft_input( $input['draft'] ?? array() ) );
		return $r instanceof WP_Error ? $r : array( 'id' => $r );
	}
);

flxads_gql_m(
	'flxAdsDraftGenerate',
	__( 'Flux AI writes a draft from a brief (up to 90 seconds). Nothing reaches Meta.', 'flx-ads' ),
	array(
		'brief' => array(
			'type'        => 'FlxAdsDraftBriefInput!',
			'description' => __( 'What to write from.', 'flx-ads' ),
		),
	),
	array(
		'id'    => array(
			'type'        => 'String',
			'description' => __( 'New draft ID.', 'flx-ads' ),
		),
		'draft' => array(
			'type'        => 'FlxAdsDraft',
			'description' => __( 'The new draft.', 'flx-ads' ),
			'resolve'     => static fn( $p ): ?array => FLXADS_Creative_Service::draft( flx_gql_str( flx_gql_arr( $p )['id'] ?? '' ) ),
		),
	),
	static function ( array $input ) {
		$b = flx_gql_arr( $input['brief'] ?? array() );
		$r = FLXADS_Creative_Service::generate_draft(
			array(
				'brief'       => flx_gql_str( $b['brief'] ?? '' ),
				'link'        => flx_gql_str( $b['link'] ?? '' ),
				'dailyBudget' => is_numeric( $b['dailyBudget'] ?? null ) ? (float) $b['dailyBudget'] : 0.0,
				'objective'   => flx_gql_str( $b['objective'] ?? '' ),
				'start'       => flx_gql_str( $b['start'] ?? '' ),
				'end'         => flx_gql_str( $b['end'] ?? '' ),
			)
		);
		return $r instanceof WP_Error ? $r : array( 'id' => $r );
	}
);

flxads_gql_m(
	'flxAdsDraftDuplicate',
	__( 'Copies a draft.', 'flx-ads' ),
	flxads_gql_id_input( __( 'Draft ID.', 'flx-ads' ) ),
	array(
		'id' => array(
			'type'        => 'String',
			'description' => __( 'The copy\'s ID.', 'flx-ads' ),
		),
	),
	static function ( array $input ) {
		$r = FLXADS_Creative_Service::duplicate_draft( flx_gql_str( $input['id'] ?? '' ) );
		return $r instanceof WP_Error ? $r : array( 'id' => $r );
	}
);

flxads_gql_m(
	'flxAdsDraftDelete',
	__( 'Deletes a draft. Nothing changes in Meta.', 'flx-ads' ),
	flxads_gql_id_input( __( 'Draft ID.', 'flx-ads' ) ),
	flxads_gql_done(),
	static function ( array $input ) {
		$r = FLXADS_Creative_Service::delete_draft( flx_gql_str( $input['id'] ?? '' ) );
		return $r instanceof WP_Error ? $r : array( 'ok' => true );
	}
);

flxads_gql_m(
	'flxAdsDraftSend',
	__( 'Creates the draft in Meta. The campaign, ad sets and ads are always created paused.', 'flx-ads' ),
	flxads_gql_id_input( __( 'Draft ID.', 'flx-ads' ) ),
	flxads_gql_done() + array(
		'draft' => array(
			'type'        => 'FlxAdsDraft',
			'description' => __( 'The draft after sending.', 'flx-ads' ),
		),
	),
	static function ( array $input ) {
		$id = flx_gql_str( $input['id'] ?? '' );
		$r  = FLXADS_Creative_Service::send_draft( $id );
		return $r instanceof WP_Error ? $r : array( 'ok' => true, 'draft' => FLXADS_Creative_Service::draft( $id ) );
	}
);
