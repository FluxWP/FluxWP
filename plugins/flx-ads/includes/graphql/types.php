<?php
/**
 * Flux Ads GraphQL types. Each field is [ type, description ]; values come from the typed shapes the
 * services return (FLXADS_*_Service @phpstan-type), so the default resolver reads the key.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers an object type from the compact field notation.
 *
 * @param string                           $name   Type name.
 * @param string                           $desc   Description.
 * @param array<string,array{0:string,1:string,2?:callable}> $fields Field => [ type, description, resolver ].
 */
function flxads_gql_def( string $name, string $desc, array $fields ): void {
	$out = array();
	foreach ( $fields as $key => $f ) {
		$out[ $key ] = array(
			'type'        => $f[0],
			'description' => $f[1],
		);
		if ( isset( $f[2] ) ) {
			$out[ $key ]['resolve'] = $f[2];
		}
	}
	flx_gql_type(
		$name,
		array(
			'description' => $desc,
			'fields'      => $out,
		)
	);
}

/**
 * Resolver of a GMT time field as FlxDateTime.
 *
 * @param string $key Field key in the service array.
 */
function flxads_gql_dt( string $key ): Closure {
	return static fn( $row ): ?array => flx_gql_datetime( flx_gql_arr( $row )[ $key ] ?? '' );
}

/**
 * Registers an input type from the compact field notation.
 *
 * @param string                           $name   Type name.
 * @param string                           $desc   Description.
 * @param array<string,array{0:string,1:string}> $fields Field => [ type, description ].
 */
function flxads_gql_in( string $name, string $desc, array $fields ): void {
	$out = array();
	foreach ( $fields as $key => $f ) {
		$out[ $key ] = array(
			'type'        => $f[0],
			'description' => $f[1],
		);
	}
	flx_gql_input(
		$name,
		array(
			'description' => $desc,
			'fields'      => $out,
		)
	);
}

flx_gql_enum(
	'FlxAdsPlatform',
	array(
		'description' => __( 'A conversion tracking platform.', 'flx-ads' ),
		'values'      => array(
			'META'   => array(
				'value'       => 'meta',
				'description' => __( 'Meta Pixel and Conversions API.', 'flx-ads' ),
			),
			'TIKTOK' => array(
				'value'       => 'tiktok',
				'description' => __( 'TikTok Pixel and Events API.', 'flx-ads' ),
			),
			'GA4'    => array(
				'value'       => 'ga4',
				'description' => __( 'Google Analytics 4.', 'flx-ads' ),
			),
		),
	)
);

flx_gql_enum(
	'FlxAdsCampaignStatus',
	array(
		'description' => __( 'The status a campaign, ad set or ad can be set to.', 'flx-ads' ),
		'values'      => array(
			'ACTIVE' => array(
				'value'       => 'ACTIVE',
				'description' => __( 'Delivering.', 'flx-ads' ),
			),
			'PAUSED' => array(
				'value'       => 'PAUSED',
				'description' => __( 'Paused.', 'flx-ads' ),
			),
		),
	)
);

flxads_gql_def(
	'FlxAdsOption',
	__( 'A key with its label (a filter choice).', 'flx-ads' ),
	array(
		'key'   => array( 'String!', __( 'Value to send back.', 'flx-ads' ) ),
		'label' => array( 'String!', __( 'Text in the user\'s language.', 'flx-ads' ) ),
	)
);

flxads_gql_in(
	'FlxAdsFilterInput',
	__( 'Report filter. Empty fields use the screen\'s defaults.', 'flx-ads' ),
	array(
		'range'   => array( 'String', __( 'Period key (7, 30, 90, month, last_month, year or custom).', 'flx-ads' ) ),
		'from'    => array( 'String', __( 'Start date (Y-m-d) for the custom period.', 'flx-ads' ) ),
		'to'      => array( 'String', __( 'End date (Y-m-d) for the custom period.', 'flx-ads' ) ),
		'kind'    => array( 'String', __( 'What to count: all, donations or orders.', 'flx-ads' ) ),
		'channel' => array( 'String', __( 'Limit to one channel key.', 'flx-ads' ) ),
		'by'      => array( 'String', __( 'Grouping key (channel, campaign, content …).', 'flx-ads' ) ),
	)
);

/* ---- Context and settings ---- */

flxads_gql_def(
	'FlxAdsContext',
	__( 'What the Flux Ads screen needs before it draws.', 'flx-ads' ),
	array(
		'canManage'       => array( 'Boolean!', __( 'The viewer can change settings and the ad account.', 'flx-ads' ) ),
		'metaReady'       => array( 'Boolean!', __( 'The Meta ad account and token are set.', 'flx-ads' ) ),
		'aiReady'         => array( 'Boolean!', __( 'Flux AI can write for Flux Ads.', 'flx-ads' ) ),
		'crmReady'        => array( 'Boolean!', __( 'Flux CRM is active (audiences need it).', 'flx-ads' ) ),
		'openSuggestions' => array( 'Int!', __( 'Suggestions waiting for a decision.', 'flx-ads' ) ),
		'baseCurrency'    => array( 'String!', __( 'Currency the report adds up in.', 'flx-ads' ) ),
		'version'         => array( 'String!', __( 'Flux Ads version.', 'flx-ads' ) ),
		'tabs'            => array( '[String!]!', __( 'Tabs the viewer can open, in order.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsSecret',
	__( 'A stored secret. The value is never returned; only whether it is set.', 'flx-ads' ),
	array(
		'set'        => array( 'Boolean!', __( 'A value is stored.', 'flx-ads' ) ),
		'mask'       => array( 'String!', __( 'Last four characters, or empty.', 'flx-ads' ) ),
		'fromConfig' => array( 'Boolean!', __( 'The value comes from a wp-config constant.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsTest',
	__( 'The result of the last connection test.', 'flx-ads' ),
	array(
		'ok'      => array( 'Boolean!', __( 'The platform accepted the test event.', 'flx-ads' ) ),
		'message' => array( 'String!', __( 'What the platform answered, in the user\'s language.', 'flx-ads' ) ),
		'at'      => array( 'FlxDateTime', __( 'When it ran; empty when never.', 'flx-ads' ), flxads_gql_dt( 'at' ) ),
	)
);

flxads_gql_def(
	'FlxAdsPlatformState',
	__( 'One tracking platform and its state.', 'flx-ads' ),
	array(
		'key'     => array( 'String!', __( 'meta, tiktok or ga4.', 'flx-ads' ) ),
		'label'   => array( 'String!', __( 'Platform name.', 'flx-ads' ) ),
		'on'      => array( 'Boolean!', __( 'Switched on.', 'flx-ads' ) ),
		'ready'   => array( 'Boolean!', __( 'Browser tracking is set up.', 'flx-ads' ) ),
		'canTest' => array( 'Boolean!', __( 'Server events are set up, so the test can run.', 'flx-ads' ) ),
		'test'    => array( 'FlxAdsTest', __( 'The last test; empty when none ran.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsSettings',
	__( 'Flux Ads settings. Secrets are write-only.', 'flx-ads' ),
	array(
		'metaOn'       => array( 'Boolean!', __( 'Meta tracking on.', 'flx-ads' ) ),
		'metaPixel'    => array( 'String!', __( 'Pixel (dataset) ID.', 'flx-ads' ) ),
		'metaToken'    => array( 'FlxAdsSecret!', __( 'Conversions API access token.', 'flx-ads' ) ),
		'metaTest'     => array( 'String!', __( 'Test event code.', 'flx-ads' ) ),
		'metaAccount'  => array( 'String!', __( 'Ad account ID.', 'flx-ads' ) ),
		'metaPage'     => array( 'String!', __( 'Facebook Page ID.', 'flx-ads' ) ),
		'metaAdsToken' => array( 'FlxAdsSecret!', __( 'Marketing API token.', 'flx-ads' ) ),
		'tiktokOn'     => array( 'Boolean!', __( 'TikTok tracking on.', 'flx-ads' ) ),
		'tiktokPixel'  => array( 'String!', __( 'TikTok pixel ID.', 'flx-ads' ) ),
		'tiktokToken'  => array( 'FlxAdsSecret!', __( 'Events API access token.', 'flx-ads' ) ),
		'tiktokTest'   => array( 'String!', __( 'Test event code.', 'flx-ads' ) ),
		'ga4On'        => array( 'Boolean!', __( 'GA4 tracking on.', 'flx-ads' ) ),
		'ga4Id'        => array( 'String!', __( 'Measurement ID; empty uses the theme\'s.', 'flx-ads' ) ),
		'ga4ThemeId'   => array( 'String!', __( 'Measurement ID set in the theme.', 'flx-ads' ) ),
		'ga4Secret'    => array( 'FlxAdsSecret!', __( 'Measurement Protocol API secret.', 'flx-ads' ) ),
		'consent'      => array( 'String!', __( 'auto (wait for the cookie banner) or always.', 'flx-ads' ) ),
		'pii'          => array( 'String!', __( 'E-mail and phone matching: consent, never or always.', 'flx-ads' ) ),
		'browser'      => array( 'Boolean!', __( 'Load the browser pixels too.', 'flx-ads' ) ),
		'windowDays'   => array( 'Int!', __( 'Attribution window in days.', 'flx-ads' ) ),
		'optRoas'      => array( 'Float!', __( 'Target ROAS for suggestions.', 'flx-ads' ) ),
		'optMinSpend'  => array( 'Float!', __( 'Spend before judging a campaign; 0 is automatic.', 'flx-ads' ) ),
		'platforms'    => array( '[FlxAdsPlatformState!]!', __( 'The three platforms and their test results.', 'flx-ads' ) ),
	)
);

flxads_gql_in(
	'FlxAdsSettingsInput',
	__( 'Settings to save. Fields you leave out stay as they are. A secret left empty keeps the stored one; "__clear__" removes it.', 'flx-ads' ),
	array(
		'metaOn'       => array( 'Boolean', __( 'Meta tracking on.', 'flx-ads' ) ),
		'metaPixel'    => array( 'String', __( 'Pixel (dataset) ID.', 'flx-ads' ) ),
		'metaToken'    => array( 'String', __( 'Conversions API access token (write-only).', 'flx-ads' ) ),
		'metaTest'     => array( 'String', __( 'Test event code.', 'flx-ads' ) ),
		'metaAccount'  => array( 'String', __( 'Ad account ID.', 'flx-ads' ) ),
		'metaPage'     => array( 'String', __( 'Facebook Page ID.', 'flx-ads' ) ),
		'metaAdsToken' => array( 'String', __( 'Marketing API token (write-only).', 'flx-ads' ) ),
		'tiktokOn'     => array( 'Boolean', __( 'TikTok tracking on.', 'flx-ads' ) ),
		'tiktokPixel'  => array( 'String', __( 'TikTok pixel ID.', 'flx-ads' ) ),
		'tiktokToken'  => array( 'String', __( 'Events API access token (write-only).', 'flx-ads' ) ),
		'tiktokTest'   => array( 'String', __( 'Test event code.', 'flx-ads' ) ),
		'ga4On'        => array( 'Boolean', __( 'GA4 tracking on.', 'flx-ads' ) ),
		'ga4Id'        => array( 'String', __( 'Measurement ID.', 'flx-ads' ) ),
		'ga4Secret'    => array( 'String', __( 'Measurement Protocol API secret (write-only).', 'flx-ads' ) ),
		'consent'      => array( 'String', __( 'auto or always.', 'flx-ads' ) ),
		'pii'          => array( 'String', __( 'consent, never or always.', 'flx-ads' ) ),
		'browser'      => array( 'Boolean', __( 'Load the browser pixels too.', 'flx-ads' ) ),
		'windowDays'   => array( 'Int', __( 'Attribution window in days (1–365).', 'flx-ads' ) ),
		'optRoas'      => array( 'Float', __( 'Target ROAS.', 'flx-ads' ) ),
		'optMinSpend'  => array( 'Float', __( 'Spend before judging a campaign.', 'flx-ads' ) ),
	)
);

/* ---- Report ---- */

flxads_gql_def(
	'FlxAdsReportTotals',
	__( 'Totals of the conversion report.', 'flx-ads' ),
	array(
		'count'       => array( 'Int!', __( 'Payments.', 'flx-ads' ) ),
		'amount'      => array( 'FlxMoney!', __( 'Income.', 'flx-ads' ) ),
		'adsCount'    => array( 'Int!', __( 'Payments that came from ads.', 'flx-ads' ) ),
		'adsAmount'   => array( 'FlxMoney!', __( 'Income that came from ads.', 'flx-ads' ) ),
		'adsPercent'  => array( 'Float', __( 'Share of income from ads (0–100); empty without income.', 'flx-ads' ) ),
		'newCount'    => array( 'Int!', __( 'New people.', 'flx-ads' ) ),
		'mixed'       => array( 'Boolean!', __( 'Payments in other currencies are in the totals.', 'flx-ads' ) ),
		'hasSpend'    => array( 'Boolean!', __( 'Ad spend is known for the period.', 'flx-ads' ) ),
		'spend'       => array( 'FlxMoney', __( 'Ad spend; empty when unknown.', 'flx-ads' ) ),
		'clicks'      => array( 'Int!', __( 'Link clicks.', 'flx-ads' ) ),
		'roas'        => array( 'Float', __( 'Income per unit of spend; empty without spend.', 'flx-ads' ) ),
		'spendIncome' => array( 'FlxMoney!', __( 'Income of the rows that have spend.', 'flx-ads' ) ),
		'spendCount'  => array( 'Int!', __( 'Payments of the rows that have spend.', 'flx-ads' ) ),
		'spendNew'    => array( 'Int!', __( 'New people of the rows that have spend.', 'flx-ads' ) ),
		'cpa'         => array( 'FlxMoney', __( 'Cost per payment.', 'flx-ads' ) ),
		'cpn'         => array( 'FlxMoney', __( 'Cost per new person.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsReportRow',
	__( 'One group of the conversion report.', 'flx-ads' ),
	array(
		'key'           => array( 'String!', __( 'Group key.', 'flx-ads' ) ),
		'label'         => array( 'String!', __( 'Group name.', 'flx-ads' ) ),
		'channel'       => array( 'String!', __( 'Channel key.', 'flx-ads' ) ),
		'channelLabel'  => array( 'String!', __( 'Channel name.', 'flx-ads' ) ),
		'count'         => array( 'Int!', __( 'Payments.', 'flx-ads' ) ),
		'amount'        => array( 'FlxMoney!', __( 'Income in the base currency.', 'flx-ads' ) ),
		'amounts'       => array( '[FlxMoney!]!', __( 'Income per currency.', 'flx-ads' ) ),
		'otherCurrency' => array( 'Boolean!', __( 'Includes another currency.', 'flx-ads' ) ),
		'newCount'      => array( 'Int!', __( 'New people.', 'flx-ads' ) ),
		'share'         => array( 'Float!', __( 'Share of the income (0–100).', 'flx-ads' ) ),
		'hasSpend'      => array( 'Boolean!', __( 'Spend is known.', 'flx-ads' ) ),
		'spend'         => array( 'FlxMoney', __( 'Ad spend.', 'flx-ads' ) ),
		'cpa'           => array( 'FlxMoney', __( 'Cost per payment.', 'flx-ads' ) ),
		'roas'          => array( 'Float', __( 'Return on ad spend.', 'flx-ads' ) ),
		'roasLevel'     => array( 'String!', __( 'none, bad, mid or good (colour).', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsRecentOrder',
	__( 'A recent attributed payment.', 'flx-ads' ),
	array(
		'orderId'     => array( 'Int!', __( 'Order ID.', 'flx-ads' ) ),
		'orderNumber' => array( 'String!', __( 'Order number.', 'flx-ads' ) ),
		'editUrl'     => array( 'String', __( 'Order edit page; empty when the viewer cannot open it.', 'flx-ads' ) ),
		'paidAt'      => array( 'FlxDateTime', __( 'When it was paid.', 'flx-ads' ), flxads_gql_dt( 'paidAt' ) ),
		'isDonation'  => array( 'Boolean!', __( 'A donation rather than a shop order.', 'flx-ads' ) ),
		'newPerson'   => array( 'Boolean!', __( 'First payment of this person.', 'flx-ads' ) ),
		'channel'     => array( 'String!', __( 'Channel name.', 'flx-ads' ) ),
		'summary'     => array( 'String!', __( 'What was bought or given to.', 'flx-ads' ) ),
		'total'       => array( 'FlxMoney!', __( 'Amount.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsReport',
	__( 'The conversion report: which ad brought which donation.', 'flx-ads' ),
	array(
		'range'            => array( 'String!', __( 'Period key.', 'flx-ads' ) ),
		'from'             => array( 'String!', __( 'Start (Y-m-d).', 'flx-ads' ) ),
		'to'               => array( 'String!', __( 'End (Y-m-d).', 'flx-ads' ) ),
		'fromLabel'        => array( 'String!', __( 'Start as text.', 'flx-ads' ) ),
		'toLabel'          => array( 'String!', __( 'End as text.', 'flx-ads' ) ),
		'kind'             => array( 'String!', __( 'all, donations or orders.', 'flx-ads' ) ),
		'by'               => array( 'String!', __( 'Grouping key.', 'flx-ads' ) ),
		'byLabel'          => array( 'String!', __( 'Grouping name.', 'flx-ads' ) ),
		'dimensions'       => array( '[FlxAdsOption!]!', __( 'Groupings to choose from.', 'flx-ads' ) ),
		'channel'          => array( 'String', __( 'Channel filter; empty when none.', 'flx-ads' ) ),
		'channelLabel'     => array( 'String', __( 'Channel filter name.', 'flx-ads' ) ),
		'base'             => array( 'String!', __( 'Base currency.', 'flx-ads' ) ),
		'totals'           => array( 'FlxAdsReportTotals!', __( 'Totals.', 'flx-ads' ) ),
		'showSpendColumns' => array( 'Boolean!', __( 'Show the spend, cost and ROAS columns.', 'flx-ads' ) ),
		'rows'             => array( '[FlxAdsReportRow!]!', __( 'The groups.', 'flx-ads' ) ),
		'recent'           => array( '[FlxAdsRecentOrder!]!', __( 'Latest attributed payments.', 'flx-ads' ) ),
	)
);

/* ---- Spend ---- */

flxads_gql_def(
	'FlxAdsMetaStatus',
	__( 'The last read of ad spend from Meta.', 'flx-ads' ),
	array(
		'ok'         => array( 'Boolean!', __( 'The last read worked.', 'flx-ads' ) ),
		'at'         => array( 'FlxDateTime', __( 'When it ran.', 'flx-ads' ), flxads_gql_dt( 'at' ) ),
		'message'    => array( 'String!', __( 'What happened, in the user\'s language.', 'flx-ads' ) ),
		'rows'       => array( 'Int!', __( 'Rows read.', 'flx-ads' ) ),
		'since'      => array( 'String!', __( 'First day read (Y-m-d).', 'flx-ads' ) ),
		'until'      => array( 'String!', __( 'Last day read (Y-m-d).', 'flx-ads' ) ),
		'sinceLabel' => array( 'String!', __( 'First day as text.', 'flx-ads' ) ),
		'untilLabel' => array( 'String!', __( 'Last day as text.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsSpendCampaign',
	__( 'Spend of one campaign in the period.', 'flx-ads' ),
	array(
		'name'         => array( 'String!', __( 'Campaign.', 'flx-ads' ) ),
		'channel'      => array( 'String!', __( 'Channel key.', 'flx-ads' ) ),
		'channelLabel' => array( 'String!', __( 'Channel name.', 'flx-ads' ) ),
		'spend'        => array( 'FlxMoney!', __( 'Spend.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsManualSpend',
	__( 'A spend row entered by hand.', 'flx-ads' ),
	array(
		'id'           => array( 'Int!', __( 'Row ID.', 'flx-ads' ) ),
		'day'          => array( 'String!', __( 'Day (Y-m-d).', 'flx-ads' ) ),
		'dayLabel'     => array( 'String!', __( 'Day as text.', 'flx-ads' ) ),
		'channel'      => array( 'String!', __( 'Channel key.', 'flx-ads' ) ),
		'channelLabel' => array( 'String!', __( 'Channel name.', 'flx-ads' ) ),
		'campaign'     => array( 'String!', __( 'Campaign.', 'flx-ads' ) ),
		'ad'           => array( 'String!', __( 'Ad.', 'flx-ads' ) ),
		'spend'        => array( 'FlxMoney!', __( 'Spend.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsSpendPage',
	__( 'The Ad spend tab.', 'flx-ads' ),
	array(
		'range'      => array( 'String!', __( 'Period key.', 'flx-ads' ) ),
		'from'       => array( 'String!', __( 'Start (Y-m-d).', 'flx-ads' ) ),
		'to'         => array( 'String!', __( 'End (Y-m-d).', 'flx-ads' ) ),
		'base'       => array( 'String!', __( 'Base currency.', 'flx-ads' ) ),
		'metaReady'  => array( 'Boolean!', __( 'Meta spend can be read.', 'flx-ads' ) ),
		'metaStatus' => array( 'FlxAdsMetaStatus', __( 'The last Meta read; empty when none.', 'flx-ads' ) ),
		'campaigns'  => array( '[FlxAdsSpendCampaign!]!', __( 'Spend per campaign.', 'flx-ads' ) ),
		'total'      => array( 'FlxMoney!', __( 'Total spend.', 'flx-ads' ) ),
		'manual'     => array( '[FlxAdsManualSpend!]!', __( 'Rows entered by hand (managers only).', 'flx-ads' ) ),
		'channels'   => array( '[FlxAdsOption!]!', __( 'Channels a row can be added for.', 'flx-ads' ) ),
	)
);

/* ---- Value ---- */

flxads_gql_def(
	'FlxAdsValueTotals',
	__( 'Totals of the donor value report.', 'flx-ads' ),
	array(
		'newCount' => array( 'Int!', __( 'New people.', 'flx-ads' ) ),
		'adsNew'   => array( 'Int!', __( 'New people from ads.', 'flx-ads' ) ),
		'adsAvg'   => array( 'FlxMoney', __( 'Average lifetime value of people from ads.', 'flx-ads' ) ),
		'cac'      => array( 'FlxMoney', __( 'Cost to win one person.', 'flx-ads' ) ),
		'spend'    => array( 'FlxMoney', __( 'Spend.', 'flx-ads' ) ),
		'ratio'    => array( 'Float', __( 'Lifetime value per cost to win.', 'flx-ads' ) ),
		'rate'     => array( 'Float', __( 'Share who gave again (0–100).', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsValueRow',
	__( 'Donor value of one acquisition group.', 'flx-ads' ),
	array(
		'key'          => array( 'String!', __( 'Group key.', 'flx-ads' ) ),
		'label'        => array( 'String!', __( 'Group name.', 'flx-ads' ) ),
		'channel'      => array( 'String!', __( 'Channel key.', 'flx-ads' ) ),
		'channelLabel' => array( 'String!', __( 'Channel name.', 'flx-ads' ) ),
		'newCount'     => array( 'Int!', __( 'New people.', 'flx-ads' ) ),
		'first'        => array( 'FlxMoney!', __( 'Average first payment.', 'flx-ads' ) ),
		'ltv90'        => array( 'FlxMoney!', __( 'Average value in the first 90 days.', 'flx-ads' ) ),
		'ltv'          => array( 'FlxMoney!', __( 'Average value so far.', 'flx-ads' ) ),
		'avg'          => array( 'FlxMoney', __( 'Average payment.', 'flx-ads' ) ),
		'rate'         => array( 'Float', __( 'Share who gave again (0–100).', 'flx-ads' ) ),
		'hasSpend'     => array( 'Boolean!', __( 'Spend is known.', 'flx-ads' ) ),
		'spend'        => array( 'FlxMoney', __( 'Spend.', 'flx-ads' ) ),
		'cac'          => array( 'FlxMoney', __( 'Cost to win one person.', 'flx-ads' ) ),
		'ratio'        => array( 'Float', __( 'Lifetime value per cost to win.', 'flx-ads' ) ),
		'roasLevel'    => array( 'String!', __( 'none, bad, mid or good (colour).', 'flx-ads' ) ),
		'payback'      => array( 'Int', __( 'Days until the cost to win is paid back; empty when not yet.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsCohortRow',
	__( 'One acquisition month of the cohort table.', 'flx-ads' ),
	array(
		'month'    => array( 'String!', __( 'Month (Y-m).', 'flx-ads' ) ),
		'label'    => array( 'String!', __( 'Month as text.', 'flx-ads' ) ),
		'newCount' => array( 'Int!', __( 'New people that month.', 'flx-ads' ) ),
		'cells'    => array( '[FlxMoney]!', __( 'Cumulative value per person after each month; empty where the month has not come yet.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsValuePage',
	__( 'The Donor value tab.', 'flx-ads' ),
	array(
		'range'        => array( 'String!', __( 'Period key.', 'flx-ads' ) ),
		'from'         => array( 'String!', __( 'Start (Y-m-d).', 'flx-ads' ) ),
		'to'           => array( 'String!', __( 'End (Y-m-d).', 'flx-ads' ) ),
		'kind'         => array( 'String!', __( 'all, donations or orders.', 'flx-ads' ) ),
		'by'           => array( 'String!', __( 'Grouping key.', 'flx-ads' ) ),
		'base'         => array( 'String!', __( 'Base currency.', 'flx-ads' ) ),
		'totals'       => array( 'FlxAdsValueTotals!', __( 'Totals.', 'flx-ads' ) ),
		'rows'         => array( '[FlxAdsValueRow!]!', __( 'The groups.', 'flx-ads' ) ),
		'cohortMonths' => array( 'Int!', __( 'Months in the cohort table.', 'flx-ads' ) ),
		'cohort'       => array( '[FlxAdsCohortRow!]!', __( 'Cohort rows.', 'flx-ads' ) ),
	)
);

/* ---- Send log and link builder ---- */

flxads_gql_def(
	'FlxAdsLogRow',
	__( 'One conversion sent (or waiting) to a platform.', 'flx-ads' ),
	array(
		'id'            => array( 'Int!', __( 'Log row ID.', 'flx-ads' ) ),
		'platform'      => array( 'String!', __( 'Platform key.', 'flx-ads' ) ),
		'platformLabel' => array( 'String!', __( 'Platform name.', 'flx-ads' ) ),
		'orderId'       => array( 'Int!', __( 'Order ID.', 'flx-ads' ) ),
		'orderNumber'   => array( 'String!', __( 'Order number.', 'flx-ads' ) ),
		'editUrl'       => array( 'String', __( 'Order edit page; empty when the viewer cannot open it.', 'flx-ads' ) ),
		'value'         => array( 'FlxMoney!', __( 'Conversion value.', 'flx-ads' ) ),
		'status'        => array( 'String!', __( 'sent, pending, failed or skipped.', 'flx-ads' ) ),
		'statusLabel'   => array( 'String!', __( 'Status as text.', 'flx-ads' ) ),
		'reason'        => array( 'String!', __( 'Why it failed or was skipped.', 'flx-ads' ) ),
		'attempts'      => array( 'Int!', __( 'Attempts.', 'flx-ads' ) ),
		'at'            => array( 'FlxDateTime', __( 'Last attempt.', 'flx-ads' ), flxads_gql_dt( 'at' ) ),
		'canRetry'      => array( 'Boolean!', __( 'It can be sent again.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsSendLog',
	__( 'The conversion send log.', 'flx-ads' ),
	array(
		'sent'    => array( 'Int!', __( 'Sent.', 'flx-ads' ) ),
		'pending' => array( 'Int!', __( 'Waiting.', 'flx-ads' ) ),
		'failed'  => array( 'Int!', __( 'Failed.', 'flx-ads' ) ),
		'skipped' => array( 'Int!', __( 'Skipped.', 'flx-ads' ) ),
		'rows'    => array( '[FlxAdsLogRow!]!', __( 'The latest 100 rows.', 'flx-ads' ) ),
	)
);

flxads_gql_def(
	'FlxAdsLinkBuilder',
	__( 'What the tracked link builder offers.', 'flx-ads' ),
	array(
		'homeUrl' => array( 'String!', __( 'Site address.', 'flx-ads' ) ),
		'pages'   => array( '[String!]!', __( 'Page addresses to link to.', 'flx-ads' ) ),
		'sources' => array( '[FlxAdsOption!]!', __( 'utm_source choices.', 'flx-ads' ) ),
		'mediums' => array( '[FlxAdsOption!]!', __( 'utm_medium choices.', 'flx-ads' ) ),
	)
);
