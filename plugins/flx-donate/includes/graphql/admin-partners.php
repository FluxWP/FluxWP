<?php
/**
 * Flux Donate admin GraphQL (part b6): Partners of the wp-admin screen (tracking module): the list, the
 * settlement and the partner editor. Loaded only on flx_gql_register. Resolvers call
 * FLXB_Admin_Partners_Service.
 *
 * Rights (fail closed, the classic ones):
 * - flxDonationPartners, flxDonationPartnerSettlement, flxDonationPartner: flx_bagis_view.
 * - every mutation: flxb_can_manage().
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

/**
 * A partner mutation: input fields, `message` output and the resolver.
 *
 * @param array<string,array{0:string,1:string}> $input   Input fields.
 * @param callable                                $resolve Input → result.
 * @param array<string,array{0:string,1:string}> $output  Output fields besides message.
 * @return array<string,mixed>
 */
function flxb_gql_partner_mutation( string $description, array $input, callable $resolve, array $output = array() ): array {
	return array(
		'description' => $description,
		'cap'         => static fn(): bool => FLXB_Admin_Partners_Service::can_manage(),
		'input'       => flxb_gql_admin_fields( $input ),
		'output'      => flxb_gql_admin_fields( array( 'message' => array( 'String', __( 'Result for the notice; null when refused (see errors).', 'flx-donate' ) ) ) + $output ),
		'resolve'     => static function ( $in ) use ( $resolve ) {
			return $resolve( flxb_gql_args( is_array( $in ) ? $in : array() ) );
		},
	);
}

flx_gql_type(
	'FlxDonationPartnerRow',
	array(
		'description' => __( 'One partner of the wp-admin list, with labels ready to show.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'databaseId' => array( 'Int!', __( 'Partner id.', 'flx-donate' ) ),
				'name'       => array( 'String!', __( 'Name.', 'flx-donate' ) ),
				'group'      => array( 'String!', __( 'Group; empty without one.', 'flx-donate' ) ),
				'roles'      => array( 'String!', __( 'Roles.', 'flx-donate' ) ),
				'types'      => array( 'String!', __( 'Donation types it works for.', 'flx-donate' ) ),
				'agreement'  => array( 'String!', __( 'State of its agreements.', 'flx-donate' ) ),
				'contact'    => array( 'String!', __( 'Contact person and phone.', 'flx-donate' ) ),
				'active'     => array( 'Boolean!', __( 'Active.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationPartnerList',
	array(
		'description' => __( 'The partners of the tracking module.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'canManage'    => array( 'Boolean!', __( 'The viewer can add and edit partners.', 'flx-donate' ) ),
				'partnerLabel' => array( 'String!', __( 'What a partner that carries the work out is called on this site.', 'flx-donate' ) ),
				'items'        => array( '[FlxDonationPartnerRow!]!', __( 'Partners.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationPartnerMonth',
	array(
		'description' => __( 'A month card of the settlement.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'key'   => array( 'String!', __( 'Month (Y-m).', 'flx-donate' ) ),
				'label' => array( 'String!', __( 'Month as people read it.', 'flx-donate' ) ),
				'count' => array( 'String!', __( 'Records, formatted.', 'flx-donate' ) ),
				'sum'   => array( 'String!', __( 'Sums per currency, formatted.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationPartnerSettleRow',
	array(
		'description' => __( 'One partner of the settlement.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'partnerId' => array( 'Int!', __( 'Partner id.', 'flx-donate' ) ),
				'name'      => array( 'String!', __( 'Name.', 'flx-donate' ) ),
				'uncosted'  => array( 'String!', __( 'Records without a price; empty without one.', 'flx-donate' ) ),
				'count'     => array( 'String!', __( 'Records, formatted.', 'flx-donate' ) ),
				'sum'       => array( 'String!', __( 'Cost (or gift price), formatted.', 'flx-donate' ) ),
				'moved'     => array( 'String!', __( 'Paid (or received), formatted.', 'flx-donate' ) ),
				'open'      => array( 'String!', __( 'Open balance, formatted.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationPartnerSettleItem',
	array(
		'description' => __( 'A record of one partner in the settlement.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'date'        => array( 'String!', __( 'Date, formatted.', 'flx-donate' ) ),
				'code'        => array( 'String!', __( 'Record code.', 'flx-donate' ) ),
				'trackUrl'    => array( 'String!', __( 'The record in the tracking page.', 'flx-donate' ) ),
				'beneficiary' => array( 'String!', __( 'On behalf of.', 'flx-donate' ) ),
				'option'      => array( 'String!', __( 'Option.', 'flx-donate' ) ),
				'status'      => array( 'String!', __( 'Status.', 'flx-donate' ) ),
				'tone'        => array( 'String!', __( 'Colour of the status: ok, pending, recurring, failed or muted.', 'flx-donate' ) ),
				'price'       => array( 'String!', __( 'Price, formatted; "—" without one.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationPartnerSettleDetail',
	array(
		'description' => __( 'The records of the chosen partner.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'name'       => array( 'String!', __( 'Partner name.', 'flx-donate' ) ),
				'canReprice' => array( 'Boolean!', __( 'Prices can be read again from the agreements.', 'flx-donate' ) ),
				'items'      => array( '[FlxDonationPartnerSettleItem!]!', __( 'Records.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationPartnerSettlement',
	array(
		'description' => __( 'The settlement of a period.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'kind'         => array( 'String!', __( 'SERVICE (cost) or SOURCE (gift price).', 'flx-donate' ) ),
				'period'       => array( 'String!', __( 'Period: Y-m or Y-m-d..Y-m-d.', 'flx-donate' ) ),
				'from'         => array( 'String!', __( 'First day (Y-m-d).', 'flx-donate' ) ),
				'to'           => array( 'String!', __( 'Last day (Y-m-d).', 'flx-donate' ) ),
				'partnerLabel' => array( 'String!', __( 'What a partner that carries the work out is called on this site.', 'flx-donate' ) ),
				'months'       => array( '[FlxDonationPartnerMonth!]!', __( 'The last 12 months, newest first.', 'flx-donate' ) ),
				'partners'     => array( '[FlxDonationChoice!]!', __( 'Partners of the kind.', 'flx-donate' ) ),
				'rows'         => array( '[FlxDonationPartnerSettleRow!]!', __( 'Partners of the period.', 'flx-donate' ) ),
				'detail'       => array( 'FlxDonationPartnerSettleDetail', __( 'Records of the chosen partner; null without one.', 'flx-donate' ) ),
				'csvUrl'       => array( 'String!', __( 'CSV download.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationPartnerPayment',
	array(
		'description' => __( 'A payment to or from a partner.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'databaseId' => array( 'Int!', __( 'Payment id.', 'flx-donate' ) ),
				'date'       => array( 'String!', __( 'Date, formatted.', 'flx-donate' ) ),
				'direction'  => array( 'String!', __( 'Paid or received.', 'flx-donate' ) ),
				'amount'     => array( 'String!', __( 'Amount, formatted.', 'flx-donate' ) ),
				'note'       => array( 'String!', __( 'Note.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationPartnerAgreement',
	array(
		'description' => __( 'An agreed price of a partner.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'databaseId' => array( 'Int!', __( 'Agreement id.', 'flx-donate' ) ),
				'type'       => array( 'String!', __( 'Donation type.', 'flx-donate' ) ),
				'option'     => array( 'String!', __( 'Option.', 'flx-donate' ) ),
				'price'      => array( 'String!', __( 'Price, formatted.', 'flx-donate' ) ),
				'validFrom'  => array( 'String!', __( 'Valid from, formatted.', 'flx-donate' ) ),
				'active'     => array( 'Boolean!', __( 'Active.', 'flx-donate' ) ),
				'note'       => array( 'String!', __( 'Note.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationPartner',
	array(
		'description' => __( 'The partner editor with its agreements and payments.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'databaseId'   => array( 'Int!', __( 'Partner id; 0 = a new one.', 'flx-donate' ) ),
				'name'         => array( 'String!', __( 'Name.', 'flx-donate' ) ),
				'pkey'         => array( 'String!', __( 'Key.', 'flx-donate' ) ),
				'group'        => array( 'String!', __( 'Group.', 'flx-donate' ) ),
				'isService'    => array( 'Boolean!', __( 'Carries the work out.', 'flx-donate' ) ),
				'isSource'     => array( 'Boolean!', __( 'Brings gifts in.', 'flx-donate' ) ),
				'typeIds'      => array( '[Int!]!', __( 'Donation types; empty = all.', 'flx-donate' ) ),
				'contactName'  => array( 'String!', __( 'Contact person.', 'flx-donate' ) ),
				'phone'        => array( 'String!', __( 'Phone (WhatsApp).', 'flx-donate' ) ),
				'email'        => array( 'String!', __( 'E-mail.', 'flx-donate' ) ),
				'currency'     => array( 'String!', __( 'Currency.', 'flx-donate' ) ),
				'active'       => array( 'Boolean!', __( 'Active.', 'flx-donate' ) ),
				'note'         => array( 'String!', __( 'Note.', 'flx-donate' ) ),
				'canManage'    => array( 'Boolean!', __( 'The viewer can edit.', 'flx-donate' ) ),
				'partnerLabel' => array( 'String!', __( 'What a partner that carries the work out is called on this site.', 'flx-donate' ) ),
				'currencies'   => array( '[String!]!', __( 'Currencies.', 'flx-donate' ) ),
				'types'        => array( '[FlxDonationChoice!]!', __( 'Donation types.', 'flx-donate' ) ),
				'typeOptions'  => array( '[FlxDonationChoice!]!', __( 'Type and option choices of an agreement ("type|option").', 'flx-donate' ) ),
				'today'        => array( 'String!', __( 'Today (Y-m-d).', 'flx-donate' ) ),
				'payments'     => array( '[FlxDonationPartnerPayment!]!', __( 'Payments.', 'flx-donate' ) ),
				'agreements'   => array( '[FlxDonationPartnerAgreement!]!', __( 'Agreements.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_query(
	'flxDonationPartners',
	array(
		'type'        => 'FlxDonationPartnerList!',
		'description' => __( 'The partners of the tracking module.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Partners_Service::can_view(),
		'resolve'     => static fn(): array => FLXB_Admin_Partners_Service::list(),
	)
);

flx_gql_query(
	'flxDonationPartnerSettlement',
	array(
		'type'        => 'FlxDonationPartnerSettlement!',
		'description' => __( 'The settlement of a period.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Partners_Service::can_view(),
		'args'        => array(
			'kind'      => array(
				'type'        => 'String!',
				'description' => __( 'SERVICE (cost) or SOURCE (gift price).', 'flx-donate' ),
			),
			'period'    => array(
				'type'        => 'String!',
				'description' => __( 'Y-m or Y-m-d..Y-m-d; empty = this month.', 'flx-donate' ),
			),
			'partnerId' => array(
				'type'        => 'Int!',
				'description' => __( 'One partner with its records; 0 = all.', 'flx-donate' ),
			),
		),
		'resolve'     => static function ( $root, $args ): array {
			unset( $root );
			$a = flxb_gql_args( is_array( $args ) ? $args : array() );
			return FLXB_Admin_Partners_Service::settlement( strtolower( flxb_gql_str( $a['kind'] ?? '' ) ), flxb_gql_str( $a['period'] ?? '' ), max( 0, flxb_gql_int( $a['partnerId'] ?? 0 ) ) );
		},
	)
);

flx_gql_query(
	'flxDonationPartner',
	array(
		'type'        => 'FlxDonationPartner',
		'description' => __( 'The partner editor; id 0 = a new one. Null when the partner does not exist.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Partners_Service::can_view(),
		'args'        => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Partner id; 0 = a new one.', 'flx-donate' ),
			),
		),
		'resolve'     => static function ( $root, $args ): ?array {
			unset( $root );
			$a = flxb_gql_args( is_array( $args ) ? $args : array() );
			return FLXB_Admin_Partners_Service::form( max( 0, flxb_gql_int( $a['id'] ?? 0 ) ) );
		},
	)
);

flx_gql_mutation(
	'flxDonationPartnerSave',
	flxb_gql_partner_mutation(
		__( 'Creates or saves a partner.', 'flx-donate' ),
		array(
			'id'          => array( 'Int!', __( 'Partner id; 0 = a new one.', 'flx-donate' ) ),
			'name'        => array( 'String!', __( 'Name.', 'flx-donate' ) ),
			'pkey'        => array( 'String!', __( 'Key.', 'flx-donate' ) ),
			'group'       => array( 'String!', __( 'Group.', 'flx-donate' ) ),
			'isService'   => array( 'Boolean!', __( 'Carries the work out.', 'flx-donate' ) ),
			'isSource'    => array( 'Boolean!', __( 'Brings gifts in.', 'flx-donate' ) ),
			'typeIds'     => array( '[Int!]!', __( 'Donation types; empty = all.', 'flx-donate' ) ),
			'contactName' => array( 'String!', __( 'Contact person.', 'flx-donate' ) ),
			'phone'       => array( 'String!', __( 'Phone (WhatsApp).', 'flx-donate' ) ),
			'email'       => array( 'String!', __( 'E-mail.', 'flx-donate' ) ),
			'currency'    => array( 'String!', __( 'Currency.', 'flx-donate' ) ),
			'active'      => array( 'Boolean!', __( 'Active.', 'flx-donate' ) ),
			'note'        => array( 'String!', __( 'Note.', 'flx-donate' ) ),
		),
		static function ( array $in ) {
			$res = FLXB_Admin_Partners_Service::save(
				max( 0, flxb_gql_int( $in['id'] ?? 0 ) ),
				array(
					'name'         => flxb_gql_str( $in['name'] ?? '' ),
					'pkey'         => flxb_gql_str( $in['pkey'] ?? '' ),
					'group_name'   => flxb_gql_str( $in['group'] ?? '' ),
					'is_service'   => ! empty( $in['isService'] ),
					'is_source'    => ! empty( $in['isSource'] ),
					'type_ids'     => array_map( 'flxb_gql_int', is_array( $in['typeIds'] ?? null ) ? $in['typeIds'] : array() ),
					'contact_name' => flxb_gql_str( $in['contactName'] ?? '' ),
					'phone'        => flxb_gql_str( $in['phone'] ?? '' ),
					'email'        => flxb_gql_str( $in['email'] ?? '' ),
					'currency'     => flxb_gql_str( $in['currency'] ?? '' ),
					'active'       => ! empty( $in['active'] ),
					'note'         => flxb_gql_str( $in['note'] ?? '' ),
				)
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			return array(
				'message'    => $res['message'],
				'databaseId' => $res['id'],
			);
		},
		array( 'databaseId' => array( 'Int', __( 'The saved partner.', 'flx-donate' ) ) )
	)
);

flx_gql_mutation(
	'flxDonationPartnerPriceAdd',
	flxb_gql_partner_mutation(
		__( 'Adds an agreed price of a partner.', 'flx-donate' ),
		array(
			'partnerId'  => array( 'Int!', __( 'Partner id.', 'flx-donate' ) ),
			'typeOption' => array( 'String!', __( 'Type and option ("type|option"; 0 = all types, empty option = all options).', 'flx-donate' ) ),
			'price'      => array( 'String!', __( 'Price.', 'flx-donate' ) ),
			'currency'   => array( 'String!', __( 'Currency.', 'flx-donate' ) ),
			'validFrom'  => array( 'String!', __( 'Valid from (Y-m-d).', 'flx-donate' ) ),
			'note'       => array( 'String!', __( 'Note.', 'flx-donate' ) ),
		),
		static fn( array $in ) => FLXB_Admin_Partners_Service::price_add(
			array(
				'partner_id' => flxb_gql_int( $in['partnerId'] ?? 0 ),
				'type_opt'   => flxb_gql_str( $in['typeOption'] ?? '' ),
				'price'      => flxb_gql_str( $in['price'] ?? '' ),
				'currency'   => flxb_gql_str( $in['currency'] ?? '' ),
				'valid_from' => flxb_gql_str( $in['validFrom'] ?? '' ),
				'note'       => flxb_gql_str( $in['note'] ?? '' ),
			)
		)
	)
);

flx_gql_mutation(
	'flxDonationPartnerPriceToggle',
	flxb_gql_partner_mutation(
		__( 'Switches an agreed price on or off.', 'flx-donate' ),
		array( 'id' => array( 'Int!', __( 'Agreement id.', 'flx-donate' ) ) ),
		static fn( array $in ) => FLXB_Admin_Partners_Service::price_toggle( flxb_gql_int( $in['id'] ?? 0 ) )
	)
);

flx_gql_mutation(
	'flxDonationPartnerPriceDelete',
	flxb_gql_partner_mutation(
		__( 'Deletes an agreed price; items already sent keep their price.', 'flx-donate' ),
		array( 'id' => array( 'Int!', __( 'Agreement id.', 'flx-donate' ) ) ),
		static fn( array $in ) => FLXB_Admin_Partners_Service::price_delete( flxb_gql_int( $in['id'] ?? 0 ) )
	)
);

flx_gql_mutation(
	'flxDonationPartnerPaymentAdd',
	flxb_gql_partner_mutation(
		__( 'Adds a payment to or from a partner.', 'flx-donate' ),
		array(
			'partnerId' => array( 'Int!', __( 'Partner id.', 'flx-donate' ) ),
			'received'  => array( 'Boolean!', __( 'We received it (false = we paid).', 'flx-donate' ) ),
			'amount'    => array( 'String!', __( 'Amount.', 'flx-donate' ) ),
			'currency'  => array( 'String!', __( 'Currency.', 'flx-donate' ) ),
			'date'      => array( 'String!', __( 'Date (Y-m-d).', 'flx-donate' ) ),
			'note'      => array( 'String!', __( 'Note.', 'flx-donate' ) ),
		),
		static fn( array $in ) => FLXB_Admin_Partners_Service::payment_add(
			array(
				'partner_id' => flxb_gql_int( $in['partnerId'] ?? 0 ),
				'direction'  => empty( $in['received'] ) ? 'out' : 'in',
				'amount'     => flxb_gql_str( $in['amount'] ?? '' ),
				'currency'   => flxb_gql_str( $in['currency'] ?? '' ),
				'paid_at'    => flxb_gql_str( $in['date'] ?? '' ),
				'note'       => flxb_gql_str( $in['note'] ?? '' ),
			)
		)
	)
);

flx_gql_mutation(
	'flxDonationPartnerPaymentDelete',
	flxb_gql_partner_mutation(
		__( 'Deletes a partner payment.', 'flx-donate' ),
		array( 'id' => array( 'Int!', __( 'Payment id.', 'flx-donate' ) ) ),
		static fn( array $in ) => FLXB_Admin_Partners_Service::payment_delete( flxb_gql_int( $in['id'] ?? 0 ) )
	)
);

flx_gql_mutation(
	'flxDonationPartnerReprice',
	flxb_gql_partner_mutation(
		__( 'Reads the agreement prices again for the records a partner got in a period.', 'flx-donate' ),
		array(
			'partnerId' => array( 'Int!', __( 'Partner id.', 'flx-donate' ) ),
			'period'    => array( 'String!', __( 'Period: Y-m or Y-m-d..Y-m-d.', 'flx-donate' ) ),
		),
		static fn( array $in ) => FLXB_Admin_Partners_Service::reprice( flxb_gql_int( $in['partnerId'] ?? 0 ), flxb_gql_str( $in['period'] ?? '' ) )
	)
);
