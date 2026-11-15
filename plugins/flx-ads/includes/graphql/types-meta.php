<?php
/**
 * Flux Ads GraphQL types: Meta campaigns, suggestions, experiments.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

flxads_gql_def(
	'FlxAdsCampaignRow',
	__( 'A Meta campaign with its numbers.', 'flx-ads' ),
	array(
		'id'             => array( 'String!', __( 'Meta campaign ID.', 'flx-ads' ) ),
		'name'           => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'effective'      => array( 'String!', __( 'Meta effective status.', 'flx-ads' ) ),
		'statusLabel'    => array( 'String!', __( 'Status as text.', 'flx-ads' ) ),
		'statusTone'     => array( 'String!', __( 'Status colour: ok, warn, bad or off.', 'flx-ads' ) ),
		'objective'      => array( 'String!', __( 'Meta objective.', 'flx-ads' ) ),
		'objectiveLabel' => array( 'String!', __( 'Objective as text.', 'flx-ads' ) ),
		'budgetLabel'    => array( 'String!', __( 'Budget as text.', 'flx-ads' ) ),
		'canPause'       => array( 'Boolean!', __( 'The status can be switched.', 'flx-ads' ) ),
		'active'         => array( 'Boolean!', __( 'Delivering now.', 'flx-ads' ) ),
		'dailyBudget'    => array( 'Float', __( 'Daily budget; empty when the budget is on ad sets.', 'flx-ads' ) ),
		'spend'          => array( 'FlxMoney!', __( 'Spend in the period.', 'flx-ads' ) ),
		'impressions'    => array( 'Int!', __( 'Impressions.', 'flx-ads' ) ),
		'clicks'         => array( 'Int!', __( 'Link clicks.', 'flx-ads' ) ),
		'ctr'            => array( 'Float', __( 'Click rate (0–100).', 'flx-ads' ) ),
		'payments'       => array( 'Int!', __( 'Payments on the site from this campaign.', 'flx-ads' ) ),
		'income'         => array( 'FlxMoney!', __( 'Income on the site from this campaign.', 'flx-ads' ) ),
		'roas'           => array( 'Float', __( 'Return on ad spend.', 'flx-ads' ) ),
		'roasLevel'      => array( 'String!', __( 'none, bad, mid or good (colour).', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsCampaignTotals',
	__( 'Totals of the campaign list.', 'flx-ads' ),
	array(
		'spend'    => array( 'FlxMoney!', __( 'Spend.', 'flx-ads' ) ),
		'income'   => array( 'FlxMoney!', __( 'Income on the site.', 'flx-ads' ) ),
		'payments' => array( 'Int!', __( 'Payments.', 'flx-ads' ) ),
		'roas'     => array( 'Float', __( 'Return on ad spend.', 'flx-ads' ) ),
		'active'   => array( 'Int!', __( 'Campaigns delivering.', 'flx-ads' ) ),
		'count'    => array( 'Int!', __( 'Campaigns.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsCampaignList',
	__( 'The Campaigns tab. Meta problems come back in `error` instead of failing the request.', 'flx-ads' ),
	array(
		'range'      => array( 'String!', __( 'Period key.', 'flx-ads' ) ),
		'ranges'     => array( '[FlxAdsOption!]!', __( 'Periods to choose from.', 'flx-ads' ) ),
		'error'      => array( 'String', __( 'Why the list is empty (no token, Meta refused …); empty when it worked.', 'flx-ads' ) ),
		'currency'   => array( 'String!', __( 'Ad account currency.', 'flx-ads' ) ),
		'since'      => array( 'String!', __( 'Start (Y-m-d).', 'flx-ads' ) ),
		'until'      => array( 'String!', __( 'End (Y-m-d).', 'flx-ads' ) ),
		'sinceLabel' => array( 'String!', __( 'Start as text.', 'flx-ads' ) ),
		'untilLabel' => array( 'String!', __( 'End as text.', 'flx-ads' ) ),
		'totals'     => array( 'FlxAdsCampaignTotals', __( 'Totals; empty when there is an error.', 'flx-ads' ) ),
		'rows'       => array( '[FlxAdsCampaignRow!]!', __( 'The campaigns.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsAdRow',
	__( 'An ad with its numbers.', 'flx-ads' ),
	array(
		'id'          => array( 'String!', __( 'Meta ad ID.', 'flx-ads' ) ),
		'name'        => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'effective'   => array( 'String!', __( 'Meta effective status.', 'flx-ads' ) ),
		'statusLabel' => array( 'String!', __( 'Status as text.', 'flx-ads' ) ),
		'statusTone'  => array( 'String!', __( 'Status colour: ok, warn, bad or off.', 'flx-ads' ) ),
		'thumb'       => array( 'String', __( 'Preview image address.', 'flx-ads' ) ),
		'title'       => array( 'String!', __( 'Headline.', 'flx-ads' ) ),
		'canPause'    => array( 'Boolean!', __( 'The status can be switched.', 'flx-ads' ) ),
		'active'      => array( 'Boolean!', __( 'Delivering now.', 'flx-ads' ) ),
		'spend'       => array( 'FlxMoney!', __( 'Spend.', 'flx-ads' ) ),
		'impressions' => array( 'Int!', __( 'Impressions.', 'flx-ads' ) ),
		'clicks'      => array( 'Int!', __( 'Link clicks.', 'flx-ads' ) ),
		'ctr'         => array( 'Float', __( 'Click rate (0–100).', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsAdsetRow',
	__( 'An ad set with its ads.', 'flx-ads' ),
	array(
		'id'          => array( 'String!', __( 'Meta ad set ID.', 'flx-ads' ) ),
		'name'        => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'effective'   => array( 'String!', __( 'Meta effective status.', 'flx-ads' ) ),
		'statusLabel' => array( 'String!', __( 'Status as text.', 'flx-ads' ) ),
		'statusTone'  => array( 'String!', __( 'Status colour: ok, warn, bad or off.', 'flx-ads' ) ),
		'budgetLabel' => array( 'String!', __( 'Budget as text.', 'flx-ads' ) ),
		'who'         => array( 'String!', __( 'Who it targets, as text.', 'flx-ads' ) ),
		'canPause'    => array( 'Boolean!', __( 'The status can be switched.', 'flx-ads' ) ),
		'active'      => array( 'Boolean!', __( 'Delivering now.', 'flx-ads' ) ),
		'dailyBudget' => array( 'Float', __( 'Daily budget; empty when the budget is on the campaign.', 'flx-ads' ) ),
		'spend'       => array( 'FlxMoney!', __( 'Spend.', 'flx-ads' ) ),
		'impressions' => array( 'Int!', __( 'Impressions.', 'flx-ads' ) ),
		'clicks'      => array( 'Int!', __( 'Link clicks.', 'flx-ads' ) ),
		'ctr'         => array( 'Float', __( 'Click rate (0–100).', 'flx-ads' ) ),
		'ads'         => array( '[FlxAdsAdRow!]!', __( 'Ads of the ad set.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsBreakdownRow',
	__( 'One row of an age/gender or placement breakdown.', 'flx-ads' ),
	array(
		'label'  => array( 'String!', __( 'Group.', 'flx-ads' ) ),
		'spend'  => array( 'FlxMoney!', __( 'Spend.', 'flx-ads' ) ),
		'clicks' => array( 'Int!', __( 'Link clicks.', 'flx-ads' ) ),
		'share'  => array( 'Float!', __( 'Share of the spend (0–100).', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsCampaignDetail',
	__( 'One campaign: ad sets, ads and breakdowns. Meta problems come back in `error`.', 'flx-ads' ),
	array(
		'range'       => array( 'String!', __( 'Period key.', 'flx-ads' ) ),
		'error'       => array( 'String', __( 'Why the detail is empty; empty when it worked.', 'flx-ads' ) ),
		'id'          => array( 'String!', __( 'Meta campaign ID.', 'flx-ads' ) ),
		'name'        => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'effective'   => array( 'String!', __( 'Meta effective status.', 'flx-ads' ) ),
		'statusLabel' => array( 'String!', __( 'Status as text.', 'flx-ads' ) ),
		'statusTone'  => array( 'String!', __( 'Status colour: ok, warn, bad or off.', 'flx-ads' ) ),
		'summary'     => array( 'String!', __( 'Objective and budget in one line.', 'flx-ads' ) ),
		'canPause'    => array( 'Boolean!', __( 'The status can be switched.', 'flx-ads' ) ),
		'active'      => array( 'Boolean!', __( 'Delivering now.', 'flx-ads' ) ),
		'dailyBudget' => array( 'Float', __( 'Daily budget; empty when the budget is on ad sets.', 'flx-ads' ) ),
		'adsets'      => array( '[FlxAdsAdsetRow!]!', __( 'Ad sets.', 'flx-ads' ) ),
		'ageGender'   => array( '[FlxAdsBreakdownRow!]!', __( 'Spend by age and gender.', 'flx-ads' ) ),
		'placement'   => array( '[FlxAdsBreakdownRow!]!', __( 'Spend by placement.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsChange',
	__( 'A change made to the ad account from Flux Ads.', 'flx-ads' ),
	array(
		'at'      => array( 'String!', __( 'When (GMT, Y-m-d H:i:s).', 'flx-ads' ) ),
		'name'    => array( 'String!', __( 'What was changed.', 'flx-ads' ) ),
		'change'  => array( 'String!', __( 'The change as text.', 'flx-ads' ) ),
		'who'     => array( 'String!', __( 'Who made it.', 'flx-ads' ) ),
		'channel' => array( 'String!', __( 'Where it was made: screen, suggestion, AI …', 'flx-ads' ) ),
	)
);

/* ---- Suggestions ---- */

flxads_gql_def(
	'FlxAdsSuggestionAction',
	__( 'The change a suggestion applies.', 'flx-ads' ),
	array(
		'field'   => array( 'String!', __( 'What changes (daily budget, status …).', 'flx-ads' ) ),
		'to'      => array( 'String!', __( 'The new value.', 'flx-ads' ) ),
		'confirm' => array( 'String!', __( 'Text to confirm with the user.', 'flx-ads' ) ),
		'label'   => array( 'String!', __( 'Button text.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsSuggestion',
	__( 'A suggestion from the optimizer.', 'flx-ads' ),
	array(
		'id'          => array( 'String!', __( 'Suggestion ID.', 'flx-ads' ) ),
		'type'        => array( 'String!', __( 'Type key.', 'flx-ads' ) ),
		'typeLabel'   => array( 'String!', __( 'Type as text.', 'flx-ads' ) ),
		'tone'        => array( 'String!', __( 'Colour: ok, warn, bad or info.', 'flx-ads' ) ),
		'created'     => array( 'String!', __( 'Created (GMT, Y-m-d H:i:s).', 'flx-ads' ) ),
		'name'        => array( 'String!', __( 'Campaign or season name.', 'flx-ads' ) ),
		'level'       => array( 'String!', __( 'campaign, adset or ad.', 'flx-ads' ) ),
		'context'     => array( 'String!', __( 'The numbers behind it, as text.', 'flx-ads' ) ),
		'reason'      => array( 'String!', __( 'Why, in the user\'s language.', 'flx-ads' ) ),
		'campaignId'  => array( 'String!', __( 'Meta campaign ID.', 'flx-ads' ) ),
		'action'      => array( 'FlxAdsSuggestionAction', __( 'The change it applies; empty for advice only.', 'flx-ads' ) ),
		'brief'       => array( 'String!', __( 'Studio brief (season suggestions).', 'flx-ads' ) ),
		'start'       => array( 'String!', __( 'Season start (Y-m-d).', 'flx-ads' ) ),
		'end'         => array( 'String!', __( 'Season end (Y-m-d).', 'flx-ads' ) ),
		'status'      => array( 'String!', __( 'open, applied or dismissed.', 'flx-ads' ) ),
		'statusLabel' => array( 'String!', __( 'Status as text.', 'flx-ads' ) ),
		'decided'     => array( 'String!', __( 'When it was decided.', 'flx-ads' ) ),
		'who'         => array( 'String!', __( 'Who decided.', 'flx-ads' ) ),
		'result'      => array( 'String!', __( 'What happened when applied.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsSuggestionRun',
	__( 'The last time suggestions were refreshed.', 'flx-ads' ),
	array(
		'at'       => array( 'String!', __( 'When it ran.', 'flx-ads' ) ),
		'ok'       => array( 'Boolean', __( 'It worked; empty when still running.', 'flx-ads' ) ),
		'message'  => array( 'String!', __( 'What happened.', 'flx-ads' ) ),
		'newCount' => array( 'Int!', __( 'New suggestions.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsSuggestionsPage',
	__( 'The Suggestions tab.', 'flx-ads' ),
	array(
		'metaReady' => array( 'Boolean!', __( 'The Meta ad account is set up.', 'flx-ads' ) ),
		'run'       => array( 'FlxAdsSuggestionRun', __( 'The last refresh.', 'flx-ads' ) ),
		'targetRoas' => array( 'Float!', __( 'Target ROAS.', 'flx-ads' ) ),
		'rulesText' => array( 'String!', __( 'The rules in one sentence.', 'flx-ads' ) ),
		'open'      => array( '[FlxAdsSuggestion!]!', __( 'Waiting for a decision.', 'flx-ads' ) ),
		'earlier'   => array( '[FlxAdsSuggestion!]!', __( 'Already decided.', 'flx-ads' ) ),
	)
);

/* ---- Experiments ---- */

flxads_gql_def(
	'FlxAdsExpTableRow',
	__( 'A row of the two-sided result table.', 'flx-ads' ),
	array(
		'label' => array( 'String!', __( 'Measure.', 'flx-ads' ) ),
		'a'     => array( 'String!', __( 'Side A value.', 'flx-ads' ) ),
		'b'     => array( 'String!', __( 'Side B value.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsLoserAd',
	__( 'The weaker ad of a finished test.', 'flx-ads' ),
	array(
		'id'   => array( 'String!', __( 'Meta ad ID.', 'flx-ads' ) ),
		'name' => array( 'String!', __( 'Ad name.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsExperiment',
	__( 'A test: two ads side by side, or a group held out of the ads.', 'flx-ads' ),
	array(
		'id'         => array( 'String!', __( 'Test ID.', 'flx-ads' ) ),
		'type'       => array( 'String!', __( 'ab or holdout.', 'flx-ads' ) ),
		'typeLabel'  => array( 'String!', __( 'Type as text.', 'flx-ads' ) ),
		'name'       => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'status'     => array( 'String!', __( 'running or finished.', 'flx-ads' ) ),
		'running'    => array( 'Boolean!', __( 'Still running.', 'flx-ads' ) ),
		'start'      => array( 'String!', __( 'Start (Y-m-d).', 'flx-ads' ) ),
		'end'        => array( 'String!', __( 'End (Y-m-d).', 'flx-ads' ) ),
		'startLabel' => array( 'String!', __( 'Start as text.', 'flx-ads' ) ),
		'endLabel'   => array( 'String!', __( 'End as text.', 'flx-ads' ) ),
		'subject'    => array( 'String!', __( 'What is compared, as text.', 'flx-ads' ) ),
		'ok'         => array( 'Boolean!', __( 'The numbers could be read.', 'flx-ads' ) ),
		'error'      => array( 'String!', __( 'Why they could not; empty when ok.', 'flx-ads' ) ),
		'headA'      => array( 'String!', __( 'Side A heading.', 'flx-ads' ) ),
		'headB'      => array( 'String!', __( 'Side B heading.', 'flx-ads' ) ),
		'table'      => array( '[FlxAdsExpTableRow!]!', __( 'Result table.', 'flx-ads' ) ),
		'winner'     => array( 'String!', __( 'a, b or empty.', 'flx-ads' ) ),
		'confidence' => array( 'Float', __( 'Confidence of the winner (0–100).', 'flx-ads' ) ),
		'small'      => array( 'Boolean!', __( 'Too little data to judge.', 'flx-ads' ) ),
		'verdict'    => array( 'String!', __( 'The result in one sentence.', 'flx-ads' ) ),
		'loserAd'    => array( 'FlxAdsLoserAd', __( 'The weaker ad that can be paused.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsAdChoice',
	__( 'An ad that can be picked for a test.', 'flx-ads' ),
	array(
		'key'   => array( 'String!', __( 'Value to send back (campaign and ad).', 'flx-ads' ) ),
		'label' => array( 'String!', __( 'Ad name.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsAdGroup',
	__( 'The ads of one campaign.', 'flx-ads' ),
	array(
		'campaign' => array( 'String!', __( 'Campaign name.', 'flx-ads' ) ),
		'ads'      => array( '[FlxAdsAdChoice!]!', __( 'Its ads.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsHoldoutAudience',
	__( 'An audience a holdout test can use.', 'flx-ads' ),
	array(
		'id'    => array( 'String!', __( 'Audience ID.', 'flx-ads' ) ),
		'label' => array( 'String!', __( 'Audience name.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsExperimentChoices',
	__( 'What the new test form offers.', 'flx-ads' ),
	array(
		'metaReady'  => array( 'Boolean!', __( 'The Meta ad account is set up.', 'flx-ads' ) ),
		'today'      => array( 'String!', __( 'Today (Y-m-d).', 'flx-ads' ) ),
		'defaultEnd' => array( 'String!', __( 'Suggested end date (Y-m-d).', 'flx-ads' ) ),
		'adGroups'   => array( '[FlxAdsAdGroup!]!', __( 'Ads by campaign.', 'flx-ads' ) ),
		'audiences'  => array( '[FlxAdsHoldoutAudience!]!', __( 'Audiences for a holdout.', 'flx-ads' ) ),
	)
);

flxads_gql_in(
	'FlxAdsExperimentInput',
	__( 'A new test.', 'flx-ads' ),
	array(
		'type'     => array( 'String!', __( 'ab or holdout.', 'flx-ads' ) ),
		'name'     => array( 'String', __( 'Name.', 'flx-ads' ) ),
		'start'    => array( 'String', __( 'Start (Y-m-d).', 'flx-ads' ) ),
		'end'      => array( 'String', __( 'End (Y-m-d).', 'flx-ads' ) ),
		'a'        => array( 'String', __( 'Side A: an ad key from the choices.', 'flx-ads' ) ),
		'b'        => array( 'String', __( 'Side B: an ad key from the choices.', 'flx-ads' ) ),
		'audience' => array( 'String', __( 'Audience ID for a holdout.', 'flx-ads' ) ),
		'pct'      => array( 'Int', __( 'Share held out (1–50).', 'flx-ads' ) ),
	)
);
