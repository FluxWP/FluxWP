<?php
/**
 * Flux Donate admin GraphQL (part b5): Add donation of the wp-admin screen (the manager's form and the staff
 * donation request). Loaded only on flx_gql_register. Resolvers call FLXB_Admin_Add_Service.
 *
 * Rights (fail closed, the classic ones):
 * - flxDonationAddForm: flxb_can_manage() or the staff request right (FLXB_Refs::can_create()).
 * - flxDonationAdd: flxb_can_manage().
 * - flxDonationRequest: FLXB_Refs::can_create(); the referral is the caller.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

flx_gql_type(
	'FlxDonationAddOption',
	array(
		'description' => __( 'An option of a donation type on the Add donation form.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'key'      => array( 'String!', __( 'Key.', 'flx-donate' ) ),
				'label'    => array( 'String!', __( 'Name.', 'flx-donate' ) ),
				'price'    => array( 'Float!', __( 'Price; 0 = not announced yet.', 'flx-donate' ) ),
				'per'      => array( 'Int!', __( 'Units per choice.', 'flx-donate' ) ),
				'unitOne'  => array( 'String!', __( 'Unit word for one; empty = "piece".', 'flx-donate' ) ),
				'unitMany' => array( 'String!', __( 'Unit word for many; empty = "pieces".', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationAddType',
	array(
		'description' => __( 'A donation type on the Add donation form.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'databaseId' => array( 'Int!', __( 'Type id.', 'flx-donate' ) ),
				'title'      => array( 'String!', __( 'Title.', 'flx-donate' ) ),
				'currency'   => array( 'String!', __( 'Currency code.', 'flx-donate' ) ),
				'options'    => array( '[FlxDonationAddOption!]!', __( 'Options.', 'flx-donate' ) ),
				'free'       => array( 'Boolean!', __( 'A free amount can be given.', 'flx-donate' ) ),
				'proxy'      => array( 'Boolean!', __( 'Needs the proxy (vekâlet) consent and the names on behalf of.', 'flx-donate' ) ),
				'cert'       => array( 'Boolean!', __( 'Makes certificates.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationAddDonor',
	array(
		'description' => __( 'The donor the form starts with.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'name'         => array( 'String!', __( 'Full name.', 'flx-donate' ) ),
				'phone'        => array( 'String!', __( 'Phone.', 'flx-donate' ) ),
				'email'        => array( 'String!', __( 'E-mail.', 'flx-donate' ) ),
				'consentKvkk'  => array( 'Boolean!', __( 'Privacy notice (KVKK) accepted.', 'flx-donate' ) ),
				'consentEmail' => array( 'Boolean!', __( 'E-mail allowed.', 'flx-donate' ) ),
				'consentSms'   => array( 'Boolean!', __( 'SMS allowed.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationAddForm',
	array(
		'description' => __( 'The Add donation form: the manager records a received gift, staff open a request with a payment link.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'request'      => array( 'Boolean!', __( 'The staff request form (payment link) instead of recording a gift.', 'flx-donate' ) ),
				'types'        => array( '[FlxDonationAddType!]!', __( 'Donation types.', 'flx-donate' ) ),
				'methods'      => array( '[FlxDonationChoice!]!', __( 'Payment methods.', 'flx-donate' ) ),
				'accounts'     => array( '[FlxDonationChoice!]!', __( 'Bank accounts.', 'flx-donate' ) ),
				'referrals'    => array( '[FlxDonationChoice!]!', __( 'People who can be the referral.', 'flx-donate' ) ),
				'currencies'   => array( '[FlxDonationChoice!]!', __( 'Currencies of a free amount.', 'flx-donate' ) ),
				'currency'     => array( 'String!', __( 'Default currency.', 'flx-donate' ) ),
				'proxyVersion' => array( 'Int', __( 'Version of the proxy (vekâlet) text; null without one.', 'flx-donate' ) ),
				'now'          => array( 'String!', __( 'Now in the site time zone (Y-m-dTH:i).', 'flx-donate' ) ),
				'donor'        => array( 'FlxDonationAddDonor!', __( 'Donor to start with.', 'flx-donate' ) ),
				'listUrl'      => array( 'String!', __( 'The donation list; empty without the right.', 'flx-donate' ) ),
				'listLabel'    => array( 'String!', __( 'Name of the list.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_query(
	'flxDonationAddForm',
	array(
		'type'        => 'FlxDonationAddForm',
		'description' => __( 'The Add donation form; donorId prefills the donor (0 = none).', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Add_Service::can_request(),
		'args'        => array(
			'donorId' => array(
				'type'        => 'Int!',
				'description' => __( 'Donor id; 0 = none.', 'flx-donate' ),
			),
		),
		'resolve'     => static function ( $root, $args ): array {
			unset( $root );
			$a = flxb_gql_args( is_array( $args ) ? $args : array() );
			return FLXB_Admin_Add_Service::form( max( 0, flxb_gql_int( $a['donorId'] ?? 0 ) ) );
		},
	)
);

flx_gql_input(
	'FlxDonationAddPersonInput',
	array(
		'description' => __( 'A person the gift is on behalf of (one certificate each).', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'name'  => array( 'String!', __( 'Full name; a row without a name is dropped.', 'flx-donate' ) ),
				'phone' => array( 'String!', __( 'WhatsApp.', 'flx-donate' ) ),
				'email' => array( 'String!', __( 'E-mail.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_input(
	'FlxDonationAddDonorInput',
	array(
		'description' => __( 'The donor, matched on phone or e-mail.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'name'         => array( 'String!', __( 'Full name.', 'flx-donate' ) ),
				'phone'        => array( 'String!', __( 'Phone.', 'flx-donate' ) ),
				'email'        => array( 'String!', __( 'E-mail.', 'flx-donate' ) ),
				'consentKvkk'  => array( 'Boolean', __( 'Privacy notice (KVKK) accepted.', 'flx-donate' ) ),
				'consentEmail' => array( 'Boolean', __( 'E-mail allowed.', 'flx-donate' ) ),
				'consentSms'   => array( 'Boolean', __( 'SMS allowed.', 'flx-donate' ) ),
			)
		),
	)
);

/**
 * The donor input → the service's donor array.
 *
 * @param mixed $d Input.
 * @return array<string,mixed>
 */
function flxb_gql_add_donor( $d ): array {
	$d = flxb_gql_args( is_array( $d ) ? $d : array() );
	return array(
		'name'          => flxb_gql_str( $d['name'] ?? '' ),
		'phone'         => flxb_gql_str( $d['phone'] ?? '' ),
		'email'         => flxb_gql_str( $d['email'] ?? '' ),
		'consent_kvkk'  => ! empty( $d['consentKvkk'] ),
		'consent_email' => ! empty( $d['consentEmail'] ),
		'consent_sms'   => ! empty( $d['consentSms'] ),
	);
}

flx_gql_mutation(
	'flxDonationAdd',
	array(
		'description' => __( 'Records a gift paid or promised by bank transfer, in cash or another way. With a donation type and WooCommerce it is an order.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Add_Service::can_record(),
		'input'       => flxb_gql_admin_fields(
			array(
				'typeId'        => array( 'Int!', __( 'Donation type; 0 = none (free amount).', 'flx-donate' ) ),
				'optionKey'     => array( 'String!', __( 'Option; empty = free amount.', 'flx-donate' ) ),
				'quantity'      => array( 'Int!', __( 'Quantity of the option.', 'flx-donate' ) ),
				'unitPrice'     => array( 'Float', __( 'A different unit price; null = the option\'s.', 'flx-donate' ) ),
				'priceReason'   => array( 'String', __( 'Reason for a different unit price.', 'flx-donate' ) ),
				'amount'        => array( 'Float', __( 'Free amount.', 'flx-donate' ) ),
				'currency'      => array( 'String', __( 'Currency of a free amount.', 'flx-donate' ) ),
				'beneficiaries' => array( 'String', __( 'Names on behalf of, one per line (proxy types).', 'flx-donate' ) ),
				'proxyConsent'  => array( 'Boolean', __( 'The donor accepted the proxy (vekâlet) text.', 'flx-donate' ) ),
				'donor'         => array( 'FlxDonationAddDonorInput!', __( 'Donor.', 'flx-donate' ) ),
				'forOther'      => array( 'Boolean', __( 'On behalf of other people.', 'flx-donate' ) ),
				'people'        => array( '[FlxDonationAddPersonInput!]', __( 'The other people; the first takes the remaining units.', 'flx-donate' ) ),
				'method'        => array( 'String!', __( 'Payment method: bank_transfer, cash or other.', 'flx-donate' ) ),
				'account'       => array( 'String', __( 'Bank account (bank transfer).', 'flx-donate' ) ),
				'paid'          => array( 'Boolean!', __( 'Paid; false = awaiting payment.', 'flx-donate' ) ),
				'date'          => array( 'String', __( 'Date in the site time zone (Y-m-dTH:i); empty = now.', 'flx-donate' ) ),
				'reference'     => array( 'String', __( 'Receipt or transaction no.', 'flx-donate' ) ),
				'refUserId'     => array( 'Int', __( 'Referral; 0 = none.', 'flx-donate' ) ),
				'note'          => array( 'String', __( 'Notes.', 'flx-donate' ) ),
			)
		),
		'output'      => flxb_gql_admin_fields(
			array(
				'message'    => array( 'String', __( 'Result for the notice; null when refused (see errors).', 'flx-donate' ) ),
				'databaseId' => array( 'Int', __( 'The (first) recorded donation.', 'flx-donate' ) ),
				'url'        => array( 'String', __( 'The donation page.', 'flx-donate' ) ),
			)
		),
		'resolve'     => static function ( $input ) {
			$in     = flxb_gql_args( is_array( $input ) ? $input : array() );
			$people = array();
			foreach ( is_array( $in['people'] ?? null ) ? $in['people'] : array() as $r ) {
				$r        = flxb_gql_args( is_array( $r ) ? $r : array() );
				$people[] = array(
					'name'  => flxb_gql_str( $r['name'] ?? '' ),
					'phone' => flxb_gql_str( $r['phone'] ?? '' ),
					'email' => flxb_gql_str( $r['email'] ?? '' ),
				);
			}
			$res = FLXB_Admin_Add_Service::record(
				array(
					'type_id'       => max( 0, flxb_gql_int( $in['typeId'] ?? 0 ) ),
					'option_key'    => flxb_gql_str( $in['optionKey'] ?? '' ),
					'quantity'      => flxb_gql_int( $in['quantity'] ?? 1 ),
					'unit_price'    => is_numeric( $in['unitPrice'] ?? null ) ? (float) $in['unitPrice'] : null,
					'price_reason'  => flxb_gql_str( $in['priceReason'] ?? '' ),
					'amount'        => is_numeric( $in['amount'] ?? null ) ? (float) $in['amount'] : 0.0,
					'currency'      => flxb_gql_str( $in['currency'] ?? '' ),
					'beneficiaries' => flxb_gql_str( $in['beneficiaries'] ?? '' ),
					'proxy_consent' => ! empty( $in['proxyConsent'] ),
					'donor'         => flxb_gql_add_donor( $in['donor'] ?? null ),
					'for_other'     => ! empty( $in['forOther'] ),
					'people'        => $people,
					'method'        => flxb_gql_str( $in['method'] ?? '' ),
					'account'       => flxb_gql_str( $in['account'] ?? '' ),
					'paid'          => ! empty( $in['paid'] ),
					'date'          => flxb_gql_str( $in['date'] ?? '' ),
					'reference'     => flxb_gql_str( $in['reference'] ?? '' ),
					'ref_user_id'   => max( 0, flxb_gql_int( $in['refUserId'] ?? 0 ) ),
					'note'          => flxb_gql_str( $in['note'] ?? '' ),
				)
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			return array(
				'message'    => $res['message'],
				'databaseId' => $res['id'],
				'url'        => $res['url'],
			);
		},
	)
);

flx_gql_mutation(
	'flxDonationRequest',
	array(
		'description' => __( 'Opens a donation request on behalf of a donor: a payment link for the donor, with the caller as the referral.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Add_Service::can_request(),
		'input'       => flxb_gql_admin_fields(
			array(
				'typeId'        => array( 'Int!', __( 'Donation type.', 'flx-donate' ) ),
				'optionKey'     => array( 'String!', __( 'Option; empty = free amount.', 'flx-donate' ) ),
				'quantity'      => array( 'Int!', __( 'Quantity.', 'flx-donate' ) ),
				'amount'        => array( 'Float', __( 'Free amount.', 'flx-donate' ) ),
				'beneficiaries' => array( 'String', __( 'Names on behalf of, one per line.', 'flx-donate' ) ),
				'donor'         => array( 'FlxDonationAddDonorInput!', __( 'Donor; phone or e-mail is required.', 'flx-donate' ) ),
				'sendEmail'     => array( 'Boolean', __( 'E-mail the payment link to the donor.', 'flx-donate' ) ),
				'note'          => array( 'String', __( 'Notes.', 'flx-donate' ) ),
			)
		),
		'output'      => flxb_gql_admin_fields(
			array(
				'message'     => array( 'String', __( 'Result for the notice; null when refused (see errors).', 'flx-donate' ) ),
				'payUrl'      => array( 'String', __( 'Payment link.', 'flx-donate' ) ),
				'whatsappUrl' => array( 'String', __( 'Link that sends the payment link on WhatsApp; empty without a phone.', 'flx-donate' ) ),
				'emailed'     => array( 'Boolean', __( 'The payment link was e-mailed to the donor.', 'flx-donate' ) ),
			)
		),
		'resolve'     => static function ( $input ) {
			$in = flxb_gql_args( is_array( $input ) ? $input : array() );
			return FLXB_Admin_Add_Service::request(
				array(
					'type_id'       => max( 0, flxb_gql_int( $in['typeId'] ?? 0 ) ),
					'option_key'    => flxb_gql_str( $in['optionKey'] ?? '' ),
					'quantity'      => flxb_gql_int( $in['quantity'] ?? 1 ),
					'amount'        => is_numeric( $in['amount'] ?? null ) ? (float) $in['amount'] : 0.0,
					'beneficiaries' => flxb_gql_str( $in['beneficiaries'] ?? '' ),
					'donor'         => flxb_gql_add_donor( $in['donor'] ?? null ),
					'send_email'    => ! empty( $in['sendEmail'] ),
					'note'          => flxb_gql_str( $in['note'] ?? '' ),
				)
			);
		},
	)
);
