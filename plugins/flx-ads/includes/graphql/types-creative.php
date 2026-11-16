<?php
/**
 * Flux Ads GraphQL types: audiences, Studio drafts, season plan.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

flxads_gql_def(
	'FlxAdsAudience',
	__( 'An audience Flux Ads keeps in Meta.', 'flx-ads' ),
	array(
		'id'           => array( 'String!', __( 'Audience ID in Flux Ads.', 'flx-ads' ) ),
		'name'         => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'kind'         => array( 'String!', __( 'custom or lookalike.', 'flx-ads' ) ),
		'metaId'       => array( 'String!', __( 'Audience ID in Meta.', 'flx-ads' ) ),
		'groupLabel'   => array( 'String!', __( 'Source group as text.', 'flx-ads' ) ),
		'purpose'      => array( 'String!', __( 'Purpose key.', 'flx-ads' ) ),
		'purposeLabel' => array( 'String!', __( 'Purpose as text.', 'flx-ads' ) ),
		'count'        => array( 'Int!', __( 'People sent at the last sync.', 'flx-ads' ) ),
		'syncedAt'     => array( 'FlxDateTime', __( 'Last sync; empty when never.', 'flx-ads' ), flxads_gql_dt( 'syncedAt' ) ),
		'error'        => array( 'String!', __( 'Why the last sync failed; empty when it worked.', 'flx-ads' ) ),
		'added'        => array( 'Int', __( 'People added at the last sync.', 'flx-ads' ) ),
		'removed'      => array( 'Int', __( 'People removed at the last sync.', 'flx-ads' ) ),
		'canSync'      => array( 'Boolean!', __( 'It can be synced again.', 'flx-ads' ) ),
		'holdout'      => array( 'Boolean!', __( 'Used by a holdout test.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsAudienceSource',
	__( 'A CRM group an audience can be built from.', 'flx-ads' ),
	array(
		'key'    => array( 'String!', __( 'Source key.', 'flx-ads' ) ),
		'label'  => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'people' => array( 'Int!', __( 'People with ad consent (managers only; 0 otherwise).', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsAudiencesPage',
	__( 'The Audiences tab.', 'flx-ads' ),
	array(
		'crmReady'        => array( 'Boolean!', __( 'Flux CRM is active.', 'flx-ads' ) ),
		'metaReady'       => array( 'Boolean!', __( 'The Meta ad account is set up.', 'flx-ads' ) ),
		'audiences'       => array( '[FlxAdsAudience!]!', __( 'Audiences.', 'flx-ads' ) ),
		'sources'         => array( '[FlxAdsAudienceSource!]!', __( 'CRM groups to build from.', 'flx-ads' ) ),
		'purposes'        => array( '[FlxAdsOption!]!', __( 'What an audience is for.', 'flx-ads' ) ),
		'customAudiences' => array( '[FlxAdsOption!]!', __( 'Own audiences a lookalike can start from.', 'flx-ads' ) ),
	)
);

flxads_gql_in(
	'FlxAdsAudienceInput',
	__( 'A new audience.', 'flx-ads' ),
	array(
		'name'      => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'source'    => array( 'String!', __( 'A source key from the page.', 'flx-ads' ) ),
		'purpose'   => array( 'String!', __( 'A purpose key from the page.', 'flx-ads' ) ),
		'lookalike' => array( 'Boolean', __( 'Also create a lookalike.', 'flx-ads' ) ),
		'country'   => array( 'String', __( 'Lookalike country code.', 'flx-ads' ) ),
		'ratio'     => array( 'Float', __( 'Lookalike size in percent (1–10).', 'flx-ads' ) ),
	)
);

/* ---- Studio ---- */

flxads_gql_def(
	'FlxAdsDraftAdset',
	__( 'An ad set of a draft.', 'flx-ads' ),
	array(
		'name'      => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'ageMin'    => array( 'Int!', __( 'Youngest age.', 'flx-ads' ) ),
		'ageMax'    => array( 'Int!', __( 'Oldest age.', 'flx-ads' ) ),
		'gender'    => array( 'String!', __( 'all, men or women.', 'flx-ads' ) ),
		'countries' => array( '[String!]!', __( 'Country codes.', 'flx-ads' ) ),
		'audiences' => array( '[String!]!', __( 'Audience IDs to include.', 'flx-ads' ) ),
		'exclude'   => array( '[String!]!', __( 'Audience IDs to exclude.', 'flx-ads' ) ),
		'share'     => array( 'Int!', __( 'Share of the budget (0–100).', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsDraftAd',
	__( 'An ad of a draft.', 'flx-ads' ),
	array(
		'name'        => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'text'        => array( 'String!', __( 'Main text.', 'flx-ads' ) ),
		'headline'    => array( 'String!', __( 'Headline.', 'flx-ads' ) ),
		'description' => array( 'String!', __( 'Description.', 'flx-ads' ) ),
		'cta'         => array( 'String!', __( 'Button key.', 'flx-ads' ) ),
		'image'       => array( 'String!', __( 'Image address.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsDraft',
	__( 'A Studio draft. Nothing reaches Meta until it is sent, and then it is created paused.', 'flx-ads' ),
	array(
		'id'          => array( 'String!', __( 'Draft ID.', 'flx-ads' ) ),
		'status'      => array( 'String!', __( 'draft, sent or failed.', 'flx-ads' ) ),
		'name'        => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'objective'   => array( 'String!', __( 'Objective key.', 'flx-ads' ) ),
		'link'        => array( 'String!', __( 'Address the ads open.', 'flx-ads' ) ),
		'dailyBudget' => array( 'Float!', __( 'Daily budget.', 'flx-ads' ) ),
		'start'       => array( 'String!', __( 'Start (Y-m-d).', 'flx-ads' ) ),
		'end'         => array( 'String!', __( 'End (Y-m-d).', 'flx-ads' ) ),
		'adsets'      => array( '[FlxAdsDraftAdset!]!', __( 'Ad sets.', 'flx-ads' ) ),
		'ads'         => array( '[FlxAdsDraftAd!]!', __( 'Ads.', 'flx-ads' ) ),
		'notes'       => array( 'String!', __( 'Notes the AI left for the person.', 'flx-ads' ) ),
		'brief'       => array( 'String!', __( 'The brief it was written from.', 'flx-ads' ) ),
		'sent'        => array( 'Boolean!', __( 'Already created in Meta.', 'flx-ads' ) ),
		'error'       => array( 'String!', __( 'Why sending failed; empty otherwise.', 'flx-ads' ) ),
		'sendSummary' => array( 'String!', __( 'What was created in Meta.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsDraftRow',
	__( 'A draft in the list.', 'flx-ads' ),
	array(
		'id'             => array( 'String!', __( 'Draft ID.', 'flx-ads' ) ),
		'name'           => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'status'         => array( 'String!', __( 'draft, sent or failed.', 'flx-ads' ) ),
		'statusLabel'    => array( 'String!', __( 'Status as text.', 'flx-ads' ) ),
		'statusTone'     => array( 'String!', __( 'Status colour: ok, warn, bad or off.', 'flx-ads' ) ),
		'objective'      => array( 'String!', __( 'Objective key.', 'flx-ads' ) ),
		'objectiveLabel' => array( 'String!', __( 'Objective as text.', 'flx-ads' ) ),
		'dailyBudget'    => array( 'FlxMoney!', __( 'Daily budget.', 'flx-ads' ) ),
		'adsetCount'     => array( 'Int!', __( 'Ad sets.', 'flx-ads' ) ),
		'adCount'        => array( 'Int!', __( 'Ads.', 'flx-ads' ) ),
		'updated'        => array( 'String!', __( 'Last change as text.', 'flx-ads' ) ),
		'metaCampaignId' => array( 'String!', __( 'Meta campaign ID once sent.', 'flx-ads' ) ),
		'error'          => array( 'String!', __( 'Why sending failed; empty otherwise.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsStudioPage',
	__( 'The Studio tab: what the editor offers and the drafts.', 'flx-ads' ),
	array(
		'aiReady'    => array( 'Boolean!', __( 'Flux AI can write drafts.', 'flx-ads' ) ),
		'missing'    => array( '[String!]!', __( 'What is missing before a draft can be sent (token, ad account, page).', 'flx-ads' ) ),
		'objectives' => array( '[FlxAdsOption!]!', __( 'Objectives.', 'flx-ads' ) ),
		'ctas'       => array( '[FlxAdsOption!]!', __( 'Call-to-action buttons.', 'flx-ads' ) ),
		'audiences'  => array( '[FlxAdsOption!]!', __( 'Audiences a draft can target.', 'flx-ads' ) ),
		'drafts'     => array( '[FlxAdsDraftRow!]!', __( 'Drafts, newest first.', 'flx-ads' ) ),
		'siteUrl'    => array( 'String!', __( 'Site address.', 'flx-ads' ) ),
	)
);

flxads_gql_in(
	'FlxAdsDraftAdsetInput',
	__( 'An ad set of a draft.', 'flx-ads' ),
	array(
		'name'      => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'ageMin'    => array( 'Int', __( 'Youngest age.', 'flx-ads' ) ),
		'ageMax'    => array( 'Int', __( 'Oldest age.', 'flx-ads' ) ),
		'gender'    => array( 'String', __( 'all, men or women.', 'flx-ads' ) ),
		'countries' => array( '[String!]', __( 'Country codes.', 'flx-ads' ) ),
		'audiences' => array( '[String!]', __( 'Audience IDs to include.', 'flx-ads' ) ),
		'exclude'   => array( '[String!]', __( 'Audience IDs to exclude.', 'flx-ads' ) ),
		'share'     => array( 'Int', __( 'Share of the budget (0–100).', 'flx-ads' ) ),
	)
);

flxads_gql_in(
	'FlxAdsDraftAdInput',
	__( 'An ad of a draft.', 'flx-ads' ),
	array(
		'name'        => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'text'        => array( 'String', __( 'Main text.', 'flx-ads' ) ),
		'headline'    => array( 'String', __( 'Headline.', 'flx-ads' ) ),
		'description' => array( 'String', __( 'Description.', 'flx-ads' ) ),
		'cta'         => array( 'String', __( 'Button key.', 'flx-ads' ) ),
		'image'       => array( 'String', __( 'Image address.', 'flx-ads' ) ),
	)
);

flxads_gql_in(
	'FlxAdsDraftInput',
	__( 'A Studio draft to save.', 'flx-ads' ),
	array(
		'name'        => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'objective'   => array( 'String', __( 'Objective key.', 'flx-ads' ) ),
		'link'        => array( 'String', __( 'Address the ads open.', 'flx-ads' ) ),
		'dailyBudget' => array( 'Float', __( 'Daily budget.', 'flx-ads' ) ),
		'start'       => array( 'String', __( 'Start (Y-m-d).', 'flx-ads' ) ),
		'end'         => array( 'String', __( 'End (Y-m-d).', 'flx-ads' ) ),
		'adsets'      => array( '[FlxAdsDraftAdsetInput!]', __( 'Ad sets.', 'flx-ads' ) ),
		'ads'         => array( '[FlxAdsDraftAdInput!]', __( 'Ads.', 'flx-ads' ) ),
	)
);

flxads_gql_in(
	'FlxAdsDraftBriefInput',
	__( 'What Flux AI writes a draft from.', 'flx-ads' ),
	array(
		'brief'       => array( 'String!', __( 'The brief.', 'flx-ads' ) ),
		'link'        => array( 'String', __( 'Address the ads open.', 'flx-ads' ) ),
		'dailyBudget' => array( 'Float', __( 'Daily budget.', 'flx-ads' ) ),
		'objective'   => array( 'String', __( 'Objective key.', 'flx-ads' ) ),
		'start'       => array( 'String', __( 'Start (Y-m-d).', 'flx-ads' ) ),
		'end'         => array( 'String', __( 'End (Y-m-d).', 'flx-ads' ) ),
	)
);

/* ---- Season plan ---- */

flxads_gql_def(
	'FlxAdsSeasonLast',
	__( 'Last year\'s numbers for the same period.', 'flx-ads' ),
	array(
		'payments' => array( 'Int!', __( 'Payments.', 'flx-ads' ) ),
		'income'   => array( 'FlxMoney!', __( 'Income.', 'flx-ads' ) ),
		'newCount' => array( 'Int!', __( 'New people.', 'flx-ads' ) ),
		'spend'    => array( 'FlxMoney!', __( 'Ad spend.', 'flx-ads' ) ),
		'roas'     => array( 'Float', __( 'Return on ad spend.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsSeason',
	__( 'A period when giving and sales rise.', 'flx-ads' ),
	array(
		'key'        => array( 'String!', __( 'Key.', 'flx-ads' ) ),
		'id'         => array( 'String', __( 'ID of a date you added; empty for built-in ones.', 'flx-ads' ) ),
		'name'       => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'start'      => array( 'String!', __( 'Start (Y-m-d).', 'flx-ads' ) ),
		'end'        => array( 'String!', __( 'End (Y-m-d).', 'flx-ads' ) ),
		'startLabel' => array( 'String!', __( 'Start as text.', 'flx-ads' ) ),
		'endLabel'   => array( 'String!', __( 'End as text.', 'flx-ads' ) ),
		'approx'     => array( 'Boolean!', __( 'The dates follow the moon and can shift by a day.', 'flx-ads' ) ),
		'custom'     => array( 'Boolean!', __( 'Added by a person.', 'flx-ads' ) ),
		'state'      => array( 'String!', __( 'now, prep or later.', 'flx-ads' ) ),
		'stateLabel' => array( 'String!', __( 'State as text.', 'flx-ads' ) ),
		'tone'       => array( 'String!', __( 'Colour: ok, warn, bad or off.', 'flx-ads' ) ),
		'days'       => array( 'Int!', __( 'Days until it starts; 0 while it runs.', 'flx-ads' ) ),
		'whenLabel'  => array( 'String!', __( 'When, as text.', 'flx-ads' ) ),
		'hasLast'    => array( 'Boolean!', __( 'There are numbers from last year.', 'flx-ads' ) ),
		'last'       => array( 'FlxAdsSeasonLast!', __( 'Last year\'s numbers.', 'flx-ads' ) ),
		'brief'      => array( 'String!', __( 'Ready brief for Studio.', 'flx-ads' ) ),
	)
);

flxads_gql_in(
	'FlxAdsSeasonInput',
	__( 'A date of your own.', 'flx-ads' ),
	array(
		'name'  => array( 'String!', __( 'Name.', 'flx-ads' ) ),
		'start' => array( 'String!', __( 'Start (Y-m-d).', 'flx-ads' ) ),
		'end'   => array( 'String', __( 'End (Y-m-d); empty is the start day.', 'flx-ads' ) ),
		'prep'  => array( 'Int', __( 'Days to prepare before it starts (3–60).', 'flx-ads' ) ),
	)
);
