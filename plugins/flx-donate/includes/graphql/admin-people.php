<?php
/**
 * Flux Donate admin GraphQL (part b3): donors, the donor card and recurring donations of the wp-admin screen.
 * Loaded only on flx_gql_register. Resolvers call FLXB_Admin_People_Service.
 *
 * Rights (fail closed, the classic ones):
 * - flxDonorList / flxDonor: flx_bagis_view, or flxb_donors_own (only the donors of their referrals).
 * - flxDonorSave: flxb_can_manage().
 * - flxSubscriptionList / flxSubscriptionTotals: flx_bagis_view.
 * - flxSubscriptionCancel: flxb_can_manage() and Flux Donate takes the payments.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

flx_gql_enum(
	'FlxDonorKind',
	array(
		'description' => __( 'Which donors the list shows.', 'flx-donate' ),
		'values'      => array(
			'RECURRING' => array(
				'value'       => 'recurring',
				'description' => __( 'With an active recurring donation.', 'flx-donate' ),
			),
			'CONSENT'   => array(
				'value'       => 'consent',
				'description' => __( 'Who may be contacted by e-mail or SMS.', 'flx-donate' ),
			),
		),
	)
);

flx_gql_enum(
	'FlxDonorOrder',
	array(
		'description' => __( 'Sort order of the donor list.', 'flx-donate' ),
		'values'      => array(
			'LAST_GIFT'  => array( 'value' => 'last_donation_at' ),
			'TOTAL'      => array( 'value' => 'total_amount' ),
			'GIFTS'      => array( 'value' => 'donation_count' ),
			'FIRST_GIFT' => array( 'value' => 'first_donation_at' ),
			'NAME'       => array( 'value' => 'name' ),
		),
	)
);

flx_gql_enum(
	'FlxSubscriptionStatus',
	array(
		'description' => __( 'Status of a recurring donation.', 'flx-donate' ),
		'values'      => array(
			'ACTIVE'   => array( 'value' => 'active' ),
			'PAST_DUE' => array( 'value' => 'past_due' ),
			'CANCELED' => array( 'value' => 'canceled' ),
		),
	)
);

flx_gql_input(
	'FlxDonorListFilter',
	array(
		'description' => __( 'Filters of the donor list.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'search'  => array( 'String', __( 'Name, e-mail or phone.', 'flx-donate' ) ),
				'kind'    => array( 'FlxDonorKind', __( 'Only recurring donors or only those who may be contacted.', 'flx-donate' ) ),
				'orderBy' => array( 'FlxDonorOrder', __( 'Sort order; latest gift first by default.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_input(
	'FlxSubscriptionListFilter',
	array(
		'description' => __( 'Filters of the recurring donation list.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'search' => array( 'String', __( 'Name, e-mail, phone or provider reference.', 'flx-donate' ) ),
				'status' => array( 'FlxSubscriptionStatus', __( 'Status.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonorListItem',
	array(
		'description' => __( 'One donor of the donor list. For own-only staff the figures count only the gifts they referred.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'databaseId' => array( 'Int!', __( 'Donor id.', 'flx-donate' ) ),
				'name'       => array( 'String!', __( 'Name, else e-mail or phone.', 'flx-donate' ) ),
				'email'      => array( 'String!', __( 'E-mail under the name. Empty when it is the name.', 'flx-donate' ) ),
				'phone'      => array( 'String!', __( 'Phone, formatted.', 'flx-donate' ) ),
				'totalLabel' => array( 'String!', __( 'Total given, formatted.', 'flx-donate' ) ),
				'gifts'      => array( 'Int!', __( 'Number of gifts.', 'flx-donate' ) ),
				'lastGift'   => array( 'FlxDateTime', __( 'Latest gift.', 'flx-donate' ) ),
				'recurring'  => array( 'Boolean!', __( 'Has an active recurring donation.', 'flx-donate' ) ),
				'url'        => array( 'String!', __( 'Donor card in wp-admin.', 'flx-donate' ) ),
			)
		),
	)
);
flx_gql_connection( 'FlxDonorListItem' );

flx_gql_type(
	'FlxDonorListInfo',
	array(
		'description' => __( 'Rights of the donor list screen.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'ownOnly' => array( 'Boolean!', __( 'The user sees only the donors of the gifts they referred.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSubscriptionItem',
	array(
		'description' => __( 'One recurring donation as the classic table shows it.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'databaseId'    => array( 'Int!', __( 'Recurring donation id.', 'flx-donate' ) ),
				'donorId'       => array( 'Int!', __( 'Donor id; 0 = anonymous.', 'flx-donate' ) ),
				'donorName'     => array( 'String!', __( 'Donor.', 'flx-donate' ) ),
				'donorUrl'      => array( 'String!', __( 'Donor card; empty for anonymous.', 'flx-donate' ) ),
				'reference'     => array( 'String!', __( 'Reference at the payment provider.', 'flx-donate' ) ),
				'amountLabel'   => array( 'String!', __( 'Amount, formatted.', 'flx-donate' ) ),
				'intervalLabel' => array( 'String!', __( 'Interval ("Monthly"…).', 'flx-donate' ) ),
				'status'        => array( 'FlxSubscriptionStatus!', __( 'Status.', 'flx-donate' ) ),
				'statusLabel'   => array( 'String!', __( 'Status as people read it.', 'flx-donate' ) ),
				'tone'          => array( 'FlxDonationTone!', __( 'Colour of the status.', 'flx-donate' ) ),
				'note'          => array( 'String!', __( 'Card and the last failed attempt; empty without one.', 'flx-donate' ) ),
				'startedLabel'  => array( 'String!', __( 'Start date.', 'flx-donate' ) ),
				'nextLabel'     => array( 'String!', __( 'Next charge, or when it was canceled; empty when unknown.', 'flx-donate' ) ),
				'gifts'         => array( 'Int!', __( 'Gifts received.', 'flx-donate' ) ),
				'giftsUrl'      => array( 'String!', __( 'The donor\'s recurring gifts in the donation list.', 'flx-donate' ) ),
				'canCancel'     => array( 'Boolean!', __( 'May be canceled from here.', 'flx-donate' ) ),
			)
		),
	)
);
flx_gql_connection( 'FlxSubscriptionItem' );

flx_gql_type(
	'FlxSubscriptionTotals',
	array(
		'description' => __( 'Figures above the recurring donation list.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'mrrLabel' => array( 'String!', __( 'Monthly value of the active ones, formatted.', 'flx-donate' ) ),
				'active'   => array( 'Int!', __( 'Active.', 'flx-donate' ) ),
				'pastDue'  => array( 'Int!', __( 'Past due.', 'flx-donate' ) ),
				'canceled' => array( 'Int!', __( 'Canceled.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonorCertificate',
	array(
		'description' => __( 'A certificate of the donor (Flux Certificates).', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'number'    => array( 'String!', __( 'Number, else code.', 'flx-donate' ) ),
				'dateLabel' => array( 'String!', __( 'When it was made.', 'flx-donate' ) ),
				'url'       => array( 'String!', __( 'Certificate page.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonor',
	array(
		'description' => __( 'The donor card. For own-only staff: only the gifts they referred, no recurring donations, certificates or notes.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'databaseId'       => array( 'Int!', __( 'Donor id.', 'flx-donate' ) ),
				'name'             => array( 'String!', __( 'Name, else e-mail or phone.', 'flx-donate' ) ),
				'initial'          => array( 'String!', __( 'First letter of the name.', 'flx-donate' ) ),
				'email'            => array( 'String!', __( 'E-mail.', 'flx-donate' ) ),
				'phone'            => array( 'String!', __( 'Phone as stored (for tel: links).', 'flx-donate' ) ),
				'phoneLabel'       => array( 'String!', __( 'Phone, formatted.', 'flx-donate' ) ),
				'sinceLabel'       => array( 'String!', __( 'First gift (else when the card was made).', 'flx-donate' ) ),
				'totalLabel'       => array( 'String!', __( 'Total given, formatted.', 'flx-donate' ) ),
				'giftCount'        => array( 'Int!', __( 'Number of gifts.', 'flx-donate' ) ),
				'averageLabel'     => array( 'String!', __( 'Average gift, formatted.', 'flx-donate' ) ),
				'lastGiftLabel'    => array( 'String!', __( 'Latest gift; empty without one.', 'flx-donate' ) ),
				'ownOnly'          => array( 'Boolean!', __( 'Own-only view (staff).', 'flx-donate' ) ),
				'gifts'            => array( '[FlxDonationListItem!]!', __( 'Latest gifts (25).', 'flx-donate' ) ),
				'giftsTotal'       => array( 'Int!', __( 'All gifts.', 'flx-donate' ) ),
				'giftsUrl'         => array( 'String!', __( 'All gifts in the donation list.', 'flx-donate' ) ),
				'subscriptions'    => array( '[FlxSubscriptionItem!]!', __( 'Recurring donations.', 'flx-donate' ) ),
				'certificatesOn'   => array( 'Boolean!', __( 'Flux Certificates is available.', 'flx-donate' ) ),
				'certificates'     => array( '[FlxDonorCertificate!]!', __( 'Certificates (12).', 'flx-donate' ) ),
				'moreCertificates' => array( 'Int!', __( 'Certificates not listed.', 'flx-donate' ) ),
				'consentKvkk'      => array( 'Boolean!', __( 'Privacy notice (KVKK) accepted.', 'flx-donate' ) ),
				'consentEmail'     => array( 'Boolean!', __( 'May be contacted by e-mail.', 'flx-donate' ) ),
				'consentSms'       => array( 'Boolean!', __( 'May be contacted by SMS.', 'flx-donate' ) ),
				'note'             => array( 'String!', __( 'Note.', 'flx-donate' ) ),
				'canEdit'          => array( 'Boolean!', __( 'May change the consent and the note.', 'flx-donate' ) ),
				'canAdd'           => array( 'Boolean!', __( 'May add a donation for this donor.', 'flx-donate' ) ),
				'addUrl'           => array( 'String!', __( 'Add donation page for this donor.', 'flx-donate' ) ),
				'listUrl'          => array( 'String!', __( 'Donor list.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_query(
	'flxDonorList',
	array(
		'type'        => 'FlxDonorListItemConnection!',
		'description' => __( 'Donors of the wp-admin donor list.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_People_Service::can_donors(),
		'args'        => flx_gql_connection_args(
			array(
				'filter' => array(
					'type'        => 'FlxDonorListFilter',
					'description' => __( 'Filters.', 'flx-donate' ),
				),
			)
		),
		'resolve'     => static function ( $root, $args ): array {
			unset( $root );
			$a = flxb_gql_args( is_array( $args ) ? $args : array() );
			$f = FLXB_Admin_People_Service::donor_filter( flxb_gql_admin_filter( $a ) );
			return flx_gql_paginate( $a, static fn( int $offset, int $limit ): array => FLXB_Admin_People_Service::donors( $f, $offset, $limit ) );
		},
	)
);

flx_gql_query(
	'flxDonorListInfo',
	array(
		'type'        => 'FlxDonorListInfo!',
		'description' => __( 'Rights of the donor list screen.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_People_Service::can_donors(),
		'resolve'     => static fn(): array => array( 'ownOnly' => FLXB_Admin_People_Service::own() > 0 ),
	)
);

flx_gql_query(
	'flxDonor',
	array(
		'type'        => 'FlxDonor',
		'description' => __( 'One donor card. Null when the donor does not exist or the user may not open it.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_People_Service::can_donors(),
		'args'        => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Donor id.', 'flx-donate' ),
			),
		),
		'resolve'     => static function ( $root, $args ): ?array {
			unset( $root );
			$a = flxb_gql_args( is_array( $args ) ? $args : array() );
			return FLXB_Admin_People_Service::donor( flxb_gql_int( $a['id'] ?? 0 ) );
		},
	)
);

flx_gql_query(
	'flxSubscriptionList',
	array(
		'type'        => 'FlxSubscriptionItemConnection!',
		'description' => __( 'Recurring donations, active first.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_People_Service::can_subscriptions(),
		'args'        => flx_gql_connection_args(
			array(
				'filter' => array(
					'type'        => 'FlxSubscriptionListFilter',
					'description' => __( 'Filters.', 'flx-donate' ),
				),
			)
		),
		'resolve'     => static function ( $root, $args ): array {
			unset( $root );
			$a = flxb_gql_args( is_array( $args ) ? $args : array() );
			$f = FLXB_Admin_People_Service::sub_filter( flxb_gql_admin_filter( $a ) );
			return flx_gql_paginate( $a, static fn( int $offset, int $limit ): array => FLXB_Admin_People_Service::subscriptions( $f, $offset, $limit ) );
		},
	)
);

flx_gql_query(
	'flxSubscriptionTotals',
	array(
		'type'        => 'FlxSubscriptionTotals!',
		'description' => __( 'Figures above the recurring donation list (default currency).', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_People_Service::can_subscriptions(),
		'resolve'     => static fn(): array => FLXB_Admin_People_Service::sub_totals(),
	)
);

flx_gql_mutation(
	'flxDonorSave',
	array(
		'description' => __( 'Saves the consent switches and the note of a donor.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_People_Service::can_manage(),
		'input'       => flxb_gql_admin_fields(
			array(
				'id'           => array( 'Int!', __( 'Donor id.', 'flx-donate' ) ),
				'consentKvkk'  => array( 'Boolean!', __( 'Privacy notice (KVKK) accepted.', 'flx-donate' ) ),
				'consentEmail' => array( 'Boolean!', __( 'May be contacted by e-mail.', 'flx-donate' ) ),
				'consentSms'   => array( 'Boolean!', __( 'May be contacted by SMS.', 'flx-donate' ) ),
				'note'         => array( 'String!', __( 'Note.', 'flx-donate' ) ),
			)
		),
		'output'      => flxb_gql_admin_fields( array( 'message' => array( 'String', __( 'Result for the notice; null when refused (see errors).', 'flx-donate' ) ) ) ),
		'resolve'     => static function ( $input ) {
			$in = flxb_gql_args( is_array( $input ) ? $input : array() );
			return FLXB_Admin_People_Service::save_donor(
				flxb_gql_int( $in['id'] ?? 0 ),
				! empty( $in['consentKvkk'] ),
				! empty( $in['consentEmail'] ),
				! empty( $in['consentSms'] ),
				mb_substr( flxb_gql_str( $in['note'] ?? '' ), 0, 5000 )
			);
		},
	)
);

flx_gql_mutation(
	'flxSubscriptionCancel',
	array(
		'description' => __( 'Cancels a recurring donation at the payment provider. No further charges are made.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_People_Service::can_cancel(),
		'input'       => flxb_gql_admin_fields( array( 'id' => array( 'Int!', __( 'Recurring donation id.', 'flx-donate' ) ) ) ),
		'output'      => flxb_gql_admin_fields( array( 'message' => array( 'String', __( 'Result for the notice; null when refused (see errors).', 'flx-donate' ) ) ) ),
		'resolve'     => static function ( $input ) {
			$in = flxb_gql_args( is_array( $input ) ? $input : array() );
			return FLXB_Admin_People_Service::cancel( flxb_gql_int( $in['id'] ?? 0 ) );
		},
	)
);
