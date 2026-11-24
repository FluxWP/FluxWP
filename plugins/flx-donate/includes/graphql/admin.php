<?php
/**
 * Flux Donate admin GraphQL (part b1): donation dashboard and the donation list of the wp-admin screen.
 * Loaded only on flx_gql_register. Resolvers call FLXB_Admin_Donations_Service.
 *
 * Rights (fail closed, the classic ones):
 * - flxDonationDashboard: flx_bagis_view or flxb_dashboard. With flxb_dashboard alone the top donors
 *   carry no contact and no link.
 * - flxDonationList / flxDonationListInfo: flx_bagis_view, or flxb_view_own (only their referrals).
 * - flxDonationsBulk: flxb_can_manage() and not own-only; permanent delete also manage_options.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plain fields: name => [ type, description ].
 *
 * @param array<string,array{0: string, 1: string}> $spec Fields.
 * @return array<string,array<string,string>>
 */
function flxb_gql_admin_fields( array $spec ): array {
	$out = array();
	foreach ( $spec as $name => $f ) {
		$out[ $name ] = array(
			'type'        => $f[0],
			'description' => $f[1],
		);
	}
	return $out;
}

/**
 * The filter argument as a string-keyed array.
 *
 * @param mixed $args Field arguments.
 * @return array<string,mixed>
 */
function flxb_gql_admin_filter( $args ): array {
	$f = is_array( $args ) && is_array( $args['filter'] ?? null ) ? $args['filter'] : array();
	return flxb_gql_args( $f );
}

flx_gql_enum(
	'FlxDonationKind',
	array(
		'description' => __( 'One-time or recurring gift.', 'flx-donate' ),
		'values'      => array(
			'ONE_TIME'  => array( 'value' => 'one_time' ),
			'RECURRING' => array( 'value' => 'recurring' ),
		),
	)
);

flx_gql_enum(
	'FlxDonationListStatus',
	array(
		'description' => __( 'Status filter of the donation list. RECEIPT and PAYMENT are the two stages of a pending gift; TRASH shows the trash.', 'flx-donate' ),
		'values'      => array(
			'COMPLETED'          => array( 'value' => 'completed' ),
			'PENDING'            => array( 'value' => 'pending' ),
			'RECEIPT'            => array( 'value' => 'receipt' ),
			'PAYMENT'            => array( 'value' => 'payment' ),
			'FAILED'             => array( 'value' => 'failed' ),
			'CANCELED'           => array( 'value' => 'canceled' ),
			'REFUNDED'           => array( 'value' => 'refunded' ),
			'PARTIALLY_REFUNDED' => array( 'value' => 'partially_refunded' ),
			'TRASH'              => array( 'value' => 'trash' ),
		),
	)
);

flx_gql_enum(
	'FlxDonationTone',
	array(
		'description' => __( 'Colour of a status label.', 'flx-donate' ),
		'values'      => array(
			'OK'      => array( 'value' => 'ok' ),
			'PENDING' => array( 'value' => 'pending' ),
			'RECEIPT' => array( 'value' => 'receipt' ),
			'FAILED'  => array( 'value' => 'failed' ),
			'MUTED'   => array( 'value' => 'muted' ),
		),
	)
);

flx_gql_enum(
	'FlxDonationBulkAction',
	array(
		'description' => __( 'Bulk action on donations. An order\'s donations always move together, with the order.', 'flx-donate' ),
		'values'      => array(
			'TRASH'   => array( 'value' => 'trash' ),
			'RESTORE' => array( 'value' => 'restore' ),
			'DELETE'  => array( 'value' => 'delete' ),
		),
	)
);

flx_gql_enum(
	'FlxDonationRange',
	array(
		'description' => __( 'Period of the donation dashboard.', 'flx-donate' ),
		'values'      => array(
			'DAYS_7'  => array( 'value' => '7' ),
			'DAYS_30' => array( 'value' => '30' ),
			'DAYS_90' => array( 'value' => '90' ),
			'CUSTOM'  => array( 'value' => 'custom' ),
		),
	)
);

flx_gql_input(
	'FlxDonationListFilter',
	array(
		'description' => __( 'Filters of the donation list (the same as the classic list and its CSV export).', 'flx-donate' ),
		'fields'      => array(
			'search'         => array(
				'type'        => 'String',
				'description' => __( 'Name, e-mail, phone or code.', 'flx-donate' ),
			),
			'from'           => array(
				'type'        => 'String',
				'description' => __( 'First day (Y-m-d, site time).', 'flx-donate' ),
			),
			'to'             => array(
				'type'        => 'String',
				'description' => __( 'Last day (Y-m-d, site time).', 'flx-donate' ),
			),
			'status'         => array(
				'type'        => 'FlxDonationListStatus',
				'description' => __( 'Status or stage. Without it the trash is left out.', 'flx-donate' ),
			),
			'kind'           => array(
				'type'        => 'FlxDonationKind',
				'description' => __( 'One-time or recurring.', 'flx-donate' ),
			),
			'donationTypeId' => array(
				'type'        => 'Int',
				'description' => __( 'Donation type id; 0 = no donation type.', 'flx-donate' ),
			),
			'formId'         => array(
				'type'        => 'Int',
				'description' => __( 'Payment form id.', 'flx-donate' ),
			),
			'donorId'        => array(
				'type'        => 'Int',
				'description' => __( 'Donor id.', 'flx-donate' ),
			),
			'referralId'     => array(
				'type'        => 'Int',
				'description' => __( 'Referral user id; 0 = no referral. Ignored for staff who see only their own referrals.', 'flx-donate' ),
			),
		),
	)
);

flx_gql_type(
	'FlxDonationListItem',
	array(
		'description' => __( 'One row of the donation list. The lines of one order are one row.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'databaseId'        => array( 'Int!', __( 'Donation id (first line of the order).', 'flx-donate' ) ),
				'donorId'           => array( 'Int!', __( 'Donor id; 0 = anonymous.', 'flx-donate' ) ),
				'donorName'         => array( 'String!', __( 'Name, else e-mail or phone, else "Anonymous".', 'flx-donate' ) ),
				'contact'           => array( 'String!', __( 'E-mail or phone under the name. Empty when it is the name.', 'flx-donate' ) ),
				'amount'            => array( 'FlxMoney!', __( 'Amount of the row (all lines of the order).', 'flx-donate' ) ),
				'amountLabel'       => array( 'String!', __( 'Amount as the classic screens print it.', 'flx-donate' ) ),
				'kind'              => array( 'FlxDonationKind!', __( 'One-time or recurring.', 'flx-donate' ) ),
				'statusLabel'       => array( 'String!', __( 'Status as people read it ("Receipt awaiting approval", "Awaiting payment"…).', 'flx-donate' ) ),
				'tone'              => array( 'FlxDonationTone!', __( 'Colour of the status.', 'flx-donate' ) ),
				'date'              => array( 'FlxDateTime', __( 'When it was recorded.', 'flx-donate' ) ),
				'where'             => array( 'String!', __( 'Donation type and form, or the item count of an order.', 'flx-donate' ) ),
				'lines'             => array( 'Int!', __( 'Lines of the order.', 'flx-donate' ) ),
				'referral'          => array( 'String!', __( 'Referral name. Empty without one.', 'flx-donate' ) ),
				'referralFromDonor' => array( 'Boolean!', __( 'The gift has no referral; the name is the donor\'s earlier referral.', 'flx-donate' ) ),
				'code'              => array( 'String!', __( 'Confirmation code.', 'flx-donate' ) ),
				'url'               => array( 'String!', __( 'Donation page in wp-admin.', 'flx-donate' ) ),
			)
		),
	)
);
flx_gql_connection( 'FlxDonationListItem' );

flx_gql_type(
	'FlxDonationChoice',
	array(
		'description' => __( 'One choice of a list filter.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'value' => array( 'String!', __( 'Value.', 'flx-donate' ) ),
				'label' => array( 'String!', __( 'Label.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationAwaiting',
	array(
		'description' => __( 'A donation whose receipt awaits approval.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'databaseId' => array( 'Int!', __( 'Donation id.', 'flx-donate' ) ),
				'donorName'  => array( 'String!', __( 'Donor.', 'flx-donate' ) ),
				'what'       => array( 'String!', __( 'Donation type or item count.', 'flx-donate' ) ),
				'date'       => array( 'FlxDateTime', __( 'When it was recorded.', 'flx-donate' ) ),
				'amount'     => array( 'FlxMoney!', __( 'Amount.', 'flx-donate' ) ),
				'amountLabel' => array( 'String!', __( 'Amount as the classic screens print it.', 'flx-donate' ) ),
				'url'        => array( 'String!', __( 'Donation page in wp-admin.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationListInfo',
	array(
		'description' => __( 'Filter choices, totals, links and rights of the donation list.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'sum'                  => array( 'FlxMoney!', __( 'Received amount of the filtered list.', 'flx-donate' ) ),
				'sumLabel'             => array( 'String!', __( 'Received amount, formatted.', 'flx-donate' ) ),
				'trashCount'           => array( 'Int!', __( 'Donations in the trash (0 for people who cannot manage them).', 'flx-donate' ) ),
				'statuses'             => array( '[FlxDonationChoice!]!', __( 'Status filter choices.', 'flx-donate' ) ),
				'donationTypes'        => array( '[FlxDonationChoice!]!', __( 'Donation type filter choices.', 'flx-donate' ) ),
				'forms'                => array( '[FlxDonationChoice!]!', __( 'Payment form filter choices.', 'flx-donate' ) ),
				'referrals'            => array( '[FlxDonationChoice!]!', __( 'Referral filter choices. Empty for own-only staff.', 'flx-donate' ) ),
				'awaiting'             => array( '[FlxDonationAwaiting!]!', __( 'Receipts awaiting approval (above the list).', 'flx-donate' ) ),
				'ownOnly'              => array( 'Boolean!', __( 'The user sees only the donations they referred.', 'flx-donate' ) ),
				'canManage'            => array( 'Boolean!', __( 'May move donations to the trash and restore them.', 'flx-donate' ) ),
				'canDeletePermanently' => array( 'Boolean!', __( 'May delete donations permanently.', 'flx-donate' ) ),
				'canAdd'               => array( 'Boolean!', __( 'May add a donation by hand.', 'flx-donate' ) ),
				'csvUrl'               => array( 'String!', __( 'CSV export of the filtered list (signed admin-post link).', 'flx-donate' ) ),
				'addUrl'               => array( 'String!', __( 'Add donation page.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationTotals',
	array(
		'description' => __( 'Received totals of a period (default currency).', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'total'     => array( 'Float!', __( 'Received amount.', 'flx-donate' ) ),
				'gifts'     => array( 'Int!', __( 'Gifts.', 'flx-donate' ) ),
				'donors'    => array( 'Int!', __( 'Donors who gave.', 'flx-donate' ) ),
				'newDonors' => array( 'Int!', __( 'Donors whose first gift is in the period.', 'flx-donate' ) ),
				'average'   => array( 'Float!', __( 'Average gift.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationDay',
	array(
		'description' => __( 'Received amount of one day.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'day'       => array( 'String!', __( 'Day (Y-m-d, site time).', 'flx-donate' ) ),
				'label'     => array( 'String!', __( 'Short date label.', 'flx-donate' ) ),
				'oneTime'   => array( 'Float!', __( 'One-time gifts.', 'flx-donate' ) ),
				'recurring' => array( 'Float!', __( 'Recurring gifts.', 'flx-donate' ) ),
				'gifts'     => array( 'Int!', __( 'Gifts.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationSlice',
	array(
		'description' => __( 'Amount and count of one part.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'amount' => array( 'Float!', __( 'Received amount.', 'flx-donate' ) ),
				'gifts'  => array( 'Int!', __( 'Gifts.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationBar',
	array(
		'description' => __( 'One bar of "By donation type" / "By form".', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'id'     => array( 'Int!', __( 'Donation type or form id; 0 = none / manual.', 'flx-donate' ) ),
				'label'  => array( 'String!', __( 'Label.', 'flx-donate' ) ),
				'amount' => array( 'Float!', __( 'Received amount.', 'flx-donate' ) ),
				'gifts'  => array( 'Int!', __( 'Gifts.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationTopDonor',
	array(
		'description' => __( 'A top donor of the period.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'id'      => array( 'Int!', __( 'Donor id.', 'flx-donate' ) ),
				'name'    => array( 'String!', __( 'Name (else e-mail or phone).', 'flx-donate' ) ),
				'contact' => array( 'String!', __( 'E-mail or phone. Empty for dashboard-only users.', 'flx-donate' ) ),
				'amount'  => array( 'Float!', __( 'Received amount.', 'flx-donate' ) ),
				'gifts'   => array( 'Int!', __( 'Gifts.', 'flx-donate' ) ),
				'url'     => array( 'String!', __( 'Donor page. Empty for dashboard-only users.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationDashboard',
	array(
		'description' => __( 'Donation dashboard of a period, compared with the period before (default currency).', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'from'                 => array( 'String!', __( 'First day (Y-m-d).', 'flx-donate' ) ),
				'to'                   => array( 'String!', __( 'Last day (Y-m-d).', 'flx-donate' ) ),
				'fromLabel'            => array( 'String!', __( 'First day, formatted.', 'flx-donate' ) ),
				'toLabel'              => array( 'String!', __( 'Last day, formatted.', 'flx-donate' ) ),
				'currency'             => array( 'String!', __( 'Currency of the figures.', 'flx-donate' ) ),
				'symbol'               => array( 'String!', __( 'Currency symbol.', 'flx-donate' ) ),
				'hasAny'               => array( 'Boolean!', __( 'Any donation recorded at all.', 'flx-donate' ) ),
				'allDonors'            => array( 'Int!', __( 'Donors of all time.', 'flx-donate' ) ),
				'now'                  => array( 'FlxDonationTotals!', __( 'This period.', 'flx-donate' ) ),
				'prev'                 => array( 'FlxDonationTotals!', __( 'The period before, of the same length.', 'flx-donate' ) ),
				'mrr'                  => array( 'Float!', __( 'Monthly value of active recurring donations.', 'flx-donate' ) ),
				'activeSubscriptions'  => array( 'Int!', __( 'Active recurring donations.', 'flx-donate' ) ),
				'pastDueSubscriptions' => array( 'Int!', __( 'Recurring donations past due.', 'flx-donate' ) ),
				'series'               => array( '[FlxDonationDay!]!', __( 'Days of the period.', 'flx-donate' ) ),
				'oneTime'              => array( 'FlxDonationSlice!', __( 'One-time gifts of the period.', 'flx-donate' ) ),
				'recurring'            => array( 'FlxDonationSlice!', __( 'Recurring gifts of the period.', 'flx-donate' ) ),
				'byType'               => array( '[FlxDonationBar!]!', __( 'By donation type (top 12).', 'flx-donate' ) ),
				'byForm'               => array( '[FlxDonationBar!]!', __( 'By form (top 12).', 'flx-donate' ) ),
				'topDonors'            => array( '[FlxDonationTopDonor!]!', __( 'Top 10 donors of the period.', 'flx-donate' ) ),
				'canList'              => array( 'Boolean!', __( 'May open the donation and donor lists.', 'flx-donate' ) ),
				'csvUrl'               => array( 'String!', __( 'CSV export of the period. Empty without the list right.', 'flx-donate' ) ),
				'listUrl'              => array( 'String!', __( 'Donation list. Empty without the list right.', 'flx-donate' ) ),
				'donorsUrl'            => array( 'String!', __( 'Donor list. Empty without the list right.', 'flx-donate' ) ),
				'importUrl'            => array( 'String!', __( 'Import of existing payments (Settings).', 'flx-donate' ) ),
				'ownOnly'              => array( 'Boolean!', __( 'The user sees only their own referrals in the list.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_query(
	'flxDonationDashboard',
	array(
		'type'        => 'FlxDonationDashboard!',
		'description' => __( 'Donation dashboard of a period.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Donations_Service::can_dashboard(),
		'args'        => array(
			'range' => array(
				'type'        => 'FlxDonationRange',
				'description' => __( 'Period; default 30 days.', 'flx-donate' ),
			),
			'from'  => array(
				'type'        => 'String',
				'description' => __( 'First day of a custom period (Y-m-d).', 'flx-donate' ),
			),
			'to'    => array(
				'type'        => 'String',
				'description' => __( 'Last day of a custom period (Y-m-d).', 'flx-donate' ),
			),
		),
		'resolve'     => static function ( $root, $args ): array {
			unset( $root );
			$a = flxb_gql_args( is_array( $args ) ? $args : array() );
			return FLXB_Admin_Donations_Service::dashboard( flxb_gql_str( $a['range'] ?? '30' ), flxb_gql_str( $a['from'] ?? '' ), flxb_gql_str( $a['to'] ?? '' ) );
		},
	)
);

flx_gql_query(
	'flxDonationList',
	array(
		'type'        => 'FlxDonationListItemConnection!',
		'description' => __( 'Donation list of the wp-admin screen: an order is one row, open gifts first. Staff with flxb_view_own see only their referrals.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Donations_Service::can_list(),
		'args'        => flx_gql_connection_args(
			array(
				'filter' => array(
					'type'        => 'FlxDonationListFilter',
					'description' => __( 'Filters.', 'flx-donate' ),
				),
			)
		),
		'resolve'     => static function ( $root, $args ): array {
			unset( $root );
			$a = flxb_gql_args( is_array( $args ) ? $args : array() );
			$f = FLXB_Admin_Donations_Service::filter( flxb_gql_admin_filter( $a ) );
			return flx_gql_paginate( $a, static fn( int $offset, int $limit ): array => FLXB_Admin_Donations_Service::page( $f, $offset, $limit ) );
		},
	)
);

flx_gql_query(
	'flxDonationListInfo',
	array(
		'type'        => 'FlxDonationListInfo!',
		'description' => __( 'Filter choices, totals, receipts awaiting approval and rights of the donation list.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Donations_Service::can_list(),
		'args'        => array(
			'filter' => array(
				'type'        => 'FlxDonationListFilter',
				'description' => __( 'Filters (for the received total and the CSV link).', 'flx-donate' ),
			),
		),
		'resolve'     => static function ( $root, $args ): array {
			unset( $root );
			return FLXB_Admin_Donations_Service::info( FLXB_Admin_Donations_Service::filter( flxb_gql_admin_filter( $args ) ) );
		},
	)
);

flx_gql_mutation(
	'flxDonationsBulk',
	array(
		'description' => __( 'Moves donations to the trash, restores or deletes them. An order\'s donations always move together, with the order.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Donations_Service::can_bulk(),
		'input'       => array(
			'ids'    => array(
				'type'        => '[Int!]!',
				'description' => __( 'Donation ids.', 'flx-donate' ),
			),
			'action' => array(
				'type'        => 'FlxDonationBulkAction!',
				'description' => __( 'What to do.', 'flx-donate' ),
			),
		),
		'output'      => array(
			'done'    => array(
				'type'        => 'Int!',
				'description' => __( 'Rows (orders) changed.', 'flx-donate' ),
			),
			'message' => array(
				'type'        => 'String!',
				'description' => __( 'Result for the notice.', 'flx-donate' ),
			),
		),
		'resolve'     => static function ( $input ) {
			$in  = is_array( $input ) ? $input : array();
			$ids = array();
			foreach ( (array) ( $in['ids'] ?? array() ) as $id ) {
				$ids[] = flxb_gql_int( $id );
			}
			$res = FLXB_Admin_Donations_Service::bulk( $ids, flxb_gql_str( $in['action'] ?? '' ) );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			return $res + array( 'errors' => array() );
		},
	)
);
