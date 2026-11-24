<?php
/**
 * Flux Donate admin GraphQL (part b2): the donation page of the wp-admin screen and its actions.
 * Loaded only on flx_gql_register. Resolvers call FLXB_Admin_Detail_Service (rights are checked there,
 * the classic ones; the field caps only let in people who may open the donation list).
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

/**
 * A type made of plain fields.
 *
 * @param array<string,array{0: string, 1: string}> $fields Fields.
 */
function flxb_gql_detail_type( string $name, string $description, array $fields ): void {
	flx_gql_type(
		$name,
		array(
			'description' => $description,
			'fields'      => flxb_gql_admin_fields( $fields ),
		)
	);
}

/**
 * Input or output fields: name => [ type, description ].
 *
 * @param array<string,array{0: string, 1: string}> $spec Fields.
 * @return array<string,array<string,string>>
 */
function flxb_gql_detail_io( array $spec ): array {
	return flxb_gql_admin_fields( $spec );
}

/**
 * The donation id of a mutation input.
 *
 * @param mixed $input Input.
 */
function flxb_gql_detail_id( $input ): int {
	return flxb_gql_int( flxb_gql_args( is_array( $input ) ? $input : array() )['id'] ?? 0 );
}

flxb_gql_detail_type(
	'FlxDonationFact',
	__( 'One fact of the donation page: a label and a text, shown as plain text, code, a pill or a chip.', 'flx-donate' ),
	array(
		'label' => array( 'String!', __( 'Label.', 'flx-donate' ) ),
		'text'  => array( 'String!', __( 'Text.', 'flx-donate' ) ),
		'url'   => array( 'String!', __( 'Link; empty without one.', 'flx-donate' ) ),
		'look'  => array( 'String!', __( 'text, code, strong, muted, pill, chip or chip-donor.', 'flx-donate' ) ),
		'tone'  => array( 'String!', __( 'Colour of a pill.', 'flx-donate' ) ),
		'hint'  => array( 'String!', __( 'Tooltip.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationApprovalFile',
	__( 'A receipt awaiting approval.', 'flx-donate' ),
	array(
		'url'  => array( 'String!', __( 'File address.', 'flx-donate' ) ),
		'kind' => array( 'String!', __( 'pdf, image or file.', 'flx-donate' ) ),
		'name' => array( 'String!', __( 'File name.', 'flx-donate' ) ),
		'meta' => array( 'String!', __( 'When and from where.', 'flx-donate' ) ),
		'warn' => array( 'String!', __( 'Warning about the file; empty without one.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationApproval',
	__( 'A payment or receipt awaiting approval.', 'flx-donate' ),
	array(
		'title'    => array( 'String!', __( 'What it waits for.', 'flx-donate' ) ),
		'hint'     => array( 'String!', __( 'Amount, method and order.', 'flx-donate' ) ),
		'canAct'   => array( 'Boolean!', __( 'May approve or reject.', 'flx-donate' ) ),
		'receipts' => array( '[FlxDonationApprovalFile!]!', __( 'Uploaded receipts.', 'flx-donate' ) ),
		'notifyTo' => array( 'String!', __( 'Channels the donor can be told on; empty without any.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationLineShare',
	__( 'Kurban share of an item.', 'flx-donate' ),
	array(
		'code'        => array( 'String!', __( 'Share code.', 'flx-donate' ) ),
		'url'         => array( 'String!', __( 'Tracking page.', 'flx-donate' ) ),
		'statusLabel' => array( 'String!', __( 'Status.', 'flx-donate' ) ),
		'tone'        => array( 'String!', __( 'Colour of the status.', 'flx-donate' ) ),
		'place'       => array( 'String!', __( 'Place.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationLine',
	__( 'One line of the items table.', 'flx-donate' ),
	array(
		'image'       => array( 'String!', __( 'Picture of the donation type; empty without one.', 'flx-donate' ) ),
		'name'        => array( 'String!', __( 'Name.', 'flx-donate' ) ),
		'url'         => array( 'String!', __( 'Link (the donation type, or the other line of the basket).', 'flx-donate' ) ),
		'current'     => array( 'Boolean!', __( 'The line of this page in a basket.', 'flx-donate' ) ),
		'bits'        => array( '[FlxDonationFact!]!', __( 'Details under the name.', 'flx-donate' ) ),
		'statusLabel' => array( 'String!', __( 'Status of a basket line; empty otherwise.', 'flx-donate' ) ),
		'tone'        => array( 'String!', __( 'Colour of the status.', 'flx-donate' ) ),
		'share'       => array( 'FlxDonationLineShare', __( 'Kurban share.', 'flx-donate' ) ),
		'unit'        => array( 'Float!', __( 'Unit price (for sorting).', 'flx-donate' ) ),
		'qty'         => array( 'Int!', __( 'Quantity.', 'flx-donate' ) ),
		'total'       => array( 'Float!', __( 'Line total (for sorting).', 'flx-donate' ) ),
		'unitLabel'   => array( 'String!', __( 'Unit price.', 'flx-donate' ) ),
		'qtyLabel'    => array( 'String!', __( 'Quantity.', 'flx-donate' ) ),
		'totalLabel'  => array( 'String!', __( 'Line total.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationTotalLine',
	__( 'One line of the totals.', 'flx-donate' ),
	array(
		'label' => array( 'String!', __( 'Label.', 'flx-donate' ) ),
		'value' => array( 'String!', __( 'Amount.', 'flx-donate' ) ),
		'look'  => array( 'String!', __( 'empty, total, paid, none, wait or refund.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationLineTotals',
	__( 'Totals under the items.', 'flx-donate' ),
	array(
		'lines' => array( '[FlxDonationTotalLine!]!', __( 'Subtotal, fees and total.', 'flx-donate' ) ),
		'paid'  => array( '[FlxDonationTotalLine!]!', __( 'Paid or awaited, refunded and net.', 'flx-donate' ) ),
		'via'   => array( 'String!', __( 'How and when it was paid.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationNameField',
	__( 'The name on whose behalf of one item.', 'flx-donate' ),
	array(
		'itemId' => array( 'Int!', __( 'Item id.', 'flx-donate' ) ),
		'label'  => array( 'String!', __( 'Label.', 'flx-donate' ) ),
		'value'  => array( 'String!', __( 'Name.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationLines',
	__( 'What was given.', 'flx-donate' ),
	array(
		'hint'   => array( 'String!', __( 'Note under the title.', 'flx-donate' ) ),
		'rows'   => array( '[FlxDonationLine!]!', __( 'Lines.', 'flx-donate' ) ),
		'totals' => array( 'FlxDonationLineTotals!', __( 'Totals.', 'flx-donate' ) ),
		'names'  => array( '[FlxDonationNameField!]', __( 'Editable names on whose behalf; null when not offered.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationCertificate',
	__( 'A certificate of the donation.', 'flx-donate' ),
	array(
		'label'   => array( 'String!', __( 'Number and name.', 'flx-donate' ) ),
		'url'     => array( 'String!', __( 'Certificate page; empty when it is not found.', 'flx-donate' ) ),
		'revoked' => array( 'Boolean!', __( 'Revoked.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationRefundOffer',
	__( 'Refund of a WooCommerce order line.', 'flx-donate' ),
	array(
		'label' => array( 'String!', __( 'Button.', 'flx-donate' ) ),
		'help'  => array( 'String!', __( 'What happens.', 'flx-donate' ) ),
		'field' => array( 'String!', __( 'Amount field label.', 'flx-donate' ) ),
		'max'   => array( 'Float!', __( 'Amount left to refund.', 'flx-donate' ) ),
		'auto'  => array( 'Boolean!', __( 'The payment method sends the money back.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationMenu',
	__( 'The "More actions" menu of the payment card.', 'flx-donate' ),
	array(
		'orderLabel' => array( 'String!', __( 'WooCommerce order.', 'flx-donate' ) ),
		'orderUrl'   => array( 'String!', __( 'Order screen.', 'flx-donate' ) ),
		'refund'     => array( 'FlxDonationRefundOffer', __( 'Refund; null when there is nothing to refund.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationPaymentCard',
	__( 'How it was paid.', 'flx-donate' ),
	array(
		'title'           => array( 'String!', __( 'Title.', 'flx-donate' ) ),
		'kindLabel'       => array( 'String!', __( 'One-time or recurring.', 'flx-donate' ) ),
		'recurring'       => array( 'Boolean!', __( 'Recurring.', 'flx-donate' ) ),
		'statusLabel'     => array( 'String!', __( 'Status.', 'flx-donate' ) ),
		'tone'            => array( 'String!', __( 'Colour of the status.', 'flx-donate' ) ),
		'facts'           => array( '[FlxDonationFact!]!', __( 'Facts.', 'flx-donate' ) ),
		'certificates'    => array( '[FlxDonationCertificate!]!', __( 'Certificates.', 'flx-donate' ) ),
		'canCertificates' => array( 'Boolean!', __( 'May make and send the certificates again.', 'flx-donate' ) ),
		'note'            => array( 'String!', __( 'Note.', 'flx-donate' ) ),
		'menu'            => array( 'FlxDonationMenu', __( 'More actions; null without a WooCommerce order.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationContact',
	__( 'Contact of a person on whose behalf.', 'flx-donate' ),
	array(
		'name'     => array( 'String!', __( 'Name.', 'flx-donate' ) ),
		'phone'    => array( 'String!', __( 'Phone.', 'flx-donate' ) ),
		'phoneUrl' => array( 'String!', __( 'tel: link.', 'flx-donate' ) ),
		'email'    => array( 'String!', __( 'E-mail.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationReceipt',
	__( 'A linked receipt.', 'flx-donate' ),
	array(
		'name' => array( 'String!', __( 'File name.', 'flx-donate' ) ),
		'url'  => array( 'String!', __( 'Address; empty when it is not a web address.', 'flx-donate' ) ),
		'meta' => array( 'String!', __( 'When, who and from where.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationHistoryLine',
	__( 'One change of the donation.', 'flx-donate' ),
	array(
		'date' => array( 'String!', __( 'When.', 'flx-donate' ) ),
		'text' => array( 'String!', __( 'What changed.', 'flx-donate' ) ),
		'user' => array( 'String!', __( 'Who.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationDonorCard',
	__( 'The donor in the side column.', 'flx-donate' ),
	array(
		'name'    => array( 'String!', __( 'Name.', 'flx-donate' ) ),
		'initial' => array( 'String!', __( 'First letter.', 'flx-donate' ) ),
		'contact' => array( '[String!]!', __( 'Phone and e-mail.', 'flx-donate' ) ),
		'paid'    => array( 'String!', __( 'Paid gifts.', 'flx-donate' ) ),
		'total'   => array( 'String!', __( 'Total.', 'flx-donate' ) ),
		'waiting' => array( 'String!', __( 'Gifts waiting.', 'flx-donate' ) ),
		'cardUrl' => array( 'String!', __( 'Donor card; empty without the right.', 'flx-donate' ) ),
		'chatUrl' => array( 'String!', __( 'Flux Chat conversation; empty without it.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationPerson',
	__( 'A person with contact lines.', 'flx-donate' ),
	array(
		'name'    => array( 'String!', __( 'Name.', 'flx-donate' ) ),
		'initial' => array( 'String!', __( 'First letter.', 'flx-donate' ) ),
		'contact' => array( '[String!]!', __( 'Phone and e-mail.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationReferralCard',
	__( 'Referral of the donation.', 'flx-donate' ),
	array(
		'person'  => array( 'FlxDonationPerson', __( 'Referral; null without one.', 'flx-donate' ) ),
		'hint'    => array( 'String!', __( 'The donor\'s earlier referral; empty without one.', 'flx-donate' ) ),
		'current' => array( 'Int!', __( 'Referral user id; 0 = none.', 'flx-donate' ) ),
		'canEdit' => array( 'Boolean!', __( 'May change it.', 'flx-donate' ) ),
		'choices' => array( '[FlxDonationChoice!]!', __( 'People who can be a referral.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationManageCard',
	__( 'What an admin can do with the gift.', 'flx-donate' ),
	array(
		'multi'         => array( 'Boolean!', __( 'One basket: every item changes together.', 'flx-donate' ) ),
		'statuses'      => array( '[FlxDonationChoice!]!', __( 'Statuses that can be set; empty when the status cannot be changed.', 'flx-donate' ) ),
		'statusConfirm' => array( 'String!', __( 'Confirmation of a status change.', 'flx-donate' ) ),
		'trashed'       => array( 'Boolean!', __( 'In the trash.', 'flx-donate' ) ),
		'trashConfirm'  => array( 'String!', __( 'Confirmation of moving it to the trash.', 'flx-donate' ) ),
		'canDelete'     => array( 'Boolean!', __( 'May delete it for good.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationOrderNote',
	__( 'A note of the WooCommerce order.', 'flx-donate' ),
	array(
		'id'       => array( 'Int!', __( 'Note id.', 'flx-donate' ) ),
		'html'     => array( 'String!', __( 'Note (sanitised HTML).', 'flx-donate' ) ),
		'meta'     => array( 'String!', __( 'When and who.', 'flx-donate' ) ),
		'customer' => array( 'Boolean!', __( 'Sent to the donor.', 'flx-donate' ) ),
		'system'   => array( 'Boolean!', __( 'Added by the system.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationOrderNotes',
	__( 'Notes of the WooCommerce order.', 'flx-donate' ),
	array(
		'items' => array( '[FlxDonationOrderNote!]!', __( 'Notes, newest first.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationIsland',
	__( 'A box of another plugin: the page prints its HTML, the screen places it.', 'flx-donate' ),
	array(
		'id'    => array( 'String!', __( 'Box id.', 'flx-donate' ) ),
		'title' => array( 'String!', __( 'Title.', 'flx-donate' ) ),
		'col'   => array( 'String!', __( 'main or side.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationLayout',
	__( 'The person\'s order of the boxes.', 'flx-donate' ),
	array(
		'main'   => array( '[String!]!', __( 'Main column.', 'flx-donate' ) ),
		'side'   => array( '[String!]!', __( 'Side column.', 'flx-donate' ) ),
		'closed' => array( '[String!]!', __( 'Folded boxes.', 'flx-donate' ) ),
	)
);

flxb_gql_detail_type(
	'FlxDonationDetail',
	__( 'The donation page of wp-admin.', 'flx-donate' ),
	array(
		'databaseId'  => array( 'Int!', __( 'Donation id.', 'flx-donate' ) ),
		'title'       => array( 'String!', __( '"Donation of …".', 'flx-donate' ) ),
		'date'        => array( 'String!', __( 'When it was recorded.', 'flx-donate' ) ),
		'statusLabel' => array( 'String!', __( 'Status.', 'flx-donate' ) ),
		'tone'        => array( 'String!', __( 'Colour of the status.', 'flx-donate' ) ),
		'kind'        => array( 'FlxDonationKind!', __( 'One-time or recurring.', 'flx-donate' ) ),
		'kindLabel'   => array( 'String!', __( 'One-time or recurring, in words.', 'flx-donate' ) ),
		'multi'       => array( 'Boolean!', __( 'One basket with several items.', 'flx-donate' ) ),
		'trashed'     => array( 'Boolean!', __( 'In the trash.', 'flx-donate' ) ),
		'receiptUrl'  => array( 'String!', __( 'Printable receipt; empty until it is paid.', 'flx-donate' ) ),
		'listUrl'     => array( 'String!', __( 'Donation list.', 'flx-donate' ) ),
		'hero'        => array( '[FlxDonationFact!]!', __( 'Summary under the title.', 'flx-donate' ) ),
		'approval'    => array( 'FlxDonationApproval', __( 'Awaiting approval; null otherwise.', 'flx-donate' ) ),
		'lines'       => array( 'FlxDonationLines!', __( 'Items.', 'flx-donate' ) ),
		'payment'     => array( 'FlxDonationPaymentCard!', __( 'Payment.', 'flx-donate' ) ),
		'contacts'    => array( '[FlxDonationContact!]!', __( 'Contacts of the people on whose behalf.', 'flx-donate' ) ),
		'receipts'    => array( '[FlxDonationReceipt!]!', __( 'Linked receipts.', 'flx-donate' ) ),
		'history'     => array( '[FlxDonationHistoryLine!]!', __( 'Changes, newest first.', 'flx-donate' ) ),
		'donor'       => array( 'FlxDonationDonorCard', __( 'Donor; null for an anonymous gift.', 'flx-donate' ) ),
		'referral'    => array( 'FlxDonationReferralCard', __( 'Referral; null for staff who see only their own referrals.', 'flx-donate' ) ),
		'manage'      => array( 'FlxDonationManageCard', __( 'Actions; null without the right.', 'flx-donate' ) ),
		'notes'       => array( 'FlxDonationOrderNotes', __( 'Order notes; null without an order or the right.', 'flx-donate' ) ),
		'islands'     => array( '[FlxDonationIsland!]!', __( 'Boxes of other plugins printed on the page (empty outside wp-admin).', 'flx-donate' ) ),
		'layout'      => array( 'FlxDonationLayout!', __( 'The person\'s order of the boxes.', 'flx-donate' ) ),
	)
);

flx_gql_query(
	'flxDonationDetail',
	array(
		'type'        => 'FlxDonationDetail',
		'description' => __( 'One donation as the wp-admin donation page shows it. Null when it does not exist or the user may not open it.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Donations_Service::can_list(),
		'args'        => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Donation id.', 'flx-donate' ),
			),
		),
		'resolve'     => static function ( $root, $args ): ?array {
			unset( $root );
			return FLXB_Admin_Detail_Service::view( flxb_gql_detail_id( $args ) );
		},
	)
);

flx_gql_enum(
	'FlxDonationCertificateAction',
	array(
		'description' => __( 'What to do with the certificates of the order.', 'flx-donate' ),
		'values'      => array(
			'REMAKE' => array(
				'value'       => 'remake',
				'description' => __( 'Revoke them and make new ones, one per name (not sent).', 'flx-donate' ),
			),
			'SEND'   => array(
				'value'       => 'send',
				'description' => __( 'Send them to the donor.', 'flx-donate' ),
			),
		),
	)
);

flx_gql_input(
	'FlxDonationNameInput',
	array(
		'description' => __( 'The name on whose behalf of one item.', 'flx-donate' ),
		'fields'      => flxb_gql_detail_io(
			array(
				'itemId' => array( 'Int!', __( 'Item id.', 'flx-donate' ) ),
				'name'   => array( 'String!', __( 'Name; empty clears it.', 'flx-donate' ) ),
			)
		),
	)
);

$flxb_detail_message = flxb_gql_detail_io( array( 'message' => array( 'String', __( 'Result for the notice; null when the action was refused (see errors).', 'flx-donate' ) ) ) );
$flxb_detail_cap     = static fn(): bool => FLXB_Admin_Donations_Service::can_list();
$flxb_detail_id      = array( 'id' => array( 'Int!', __( 'Donation id.', 'flx-donate' ) ) );

flx_gql_mutation(
	'flxDonationSetStatus',
	array(
		'description' => __( 'Changes the status of a gift; every item of its order follows.', 'flx-donate' ),
		'cap'         => $flxb_detail_cap,
		'input'       => flxb_gql_detail_io( $flxb_detail_id + array( 'status' => array( 'String!', __( 'pending, completed, canceled or failed.', 'flx-donate' ) ) ) ),
		'output'      => $flxb_detail_message,
		'resolve'     => static function ( $input ) {
			$in = flxb_gql_args( is_array( $input ) ? $input : array() );
			return FLXB_Admin_Detail_Service::set_status( flxb_gql_detail_id( $in ), sanitize_key( flxb_gql_str( $in['status'] ?? '' ) ) );
		},
	)
);

flx_gql_mutation(
	'flxDonationSetReferral',
	array(
		'description' => __( 'Changes the referral of a gift (its whole order, recurring donation and certificates).', 'flx-donate' ),
		'cap'         => $flxb_detail_cap,
		'input'       => flxb_gql_detail_io( $flxb_detail_id + array( 'referralId' => array( 'Int!', __( 'Referral user id; 0 = none.', 'flx-donate' ) ) ) ),
		'output'      => $flxb_detail_message,
		'resolve'     => static function ( $input ) {
			$in = flxb_gql_args( is_array( $input ) ? $input : array() );
			return FLXB_Admin_Detail_Service::set_referral( flxb_gql_detail_id( $in ), max( 0, flxb_gql_int( $in['referralId'] ?? 0 ) ) );
		},
	)
);

flx_gql_mutation(
	'flxDonationSaveNames',
	array(
		'description' => __( 'Corrects the names on whose behalf of the items.', 'flx-donate' ),
		'cap'         => $flxb_detail_cap,
		'input'       => flxb_gql_detail_io( $flxb_detail_id + array( 'names' => array( '[FlxDonationNameInput!]!', __( 'Names.', 'flx-donate' ) ) ) ),
		'output'      => $flxb_detail_message,
		'resolve'     => static function ( $input ) {
			$in    = flxb_gql_args( is_array( $input ) ? $input : array() );
			$names = array();
			foreach ( (array) ( $in['names'] ?? array() ) as $n ) {
				$n = flxb_gql_args( is_array( $n ) ? $n : array() );
				$names[ flxb_gql_int( $n['itemId'] ?? 0 ) ] = mb_substr( flxb_gql_str( $n['name'] ?? '' ), 0, 190 );
			}
			return FLXB_Admin_Detail_Service::save_names( flxb_gql_detail_id( $in ), $names );
		},
	)
);

flx_gql_mutation(
	'flxDonationApprove',
	array(
		'description' => __( 'Approves a payment awaiting approval: the order is paid, the certificate is made and sent.', 'flx-donate' ),
		'cap'         => $flxb_detail_cap,
		'input'       => flxb_gql_detail_io( $flxb_detail_id ),
		'output'      => $flxb_detail_message,
		'resolve'     => static fn( $input ) => FLXB_Admin_Detail_Service::approve( flxb_gql_detail_id( $input ) ),
	)
);

flx_gql_mutation(
	'flxDonationReject',
	array(
		'description' => __( 'Rejects a payment awaiting approval; the donation is canceled.', 'flx-donate' ),
		'cap'         => $flxb_detail_cap,
		'input'       => flxb_gql_detail_io(
			$flxb_detail_id + array(
				'reason' => array( 'String!', __( 'Reason (sent to the donor).', 'flx-donate' ) ),
				'notify' => array( 'Boolean', __( 'Tell the donor; default yes.', 'flx-donate' ) ),
			)
		),
		'output'      => $flxb_detail_message,
		'resolve'     => static function ( $input ) {
			$in = flxb_gql_args( is_array( $input ) ? $input : array() );
			return FLXB_Admin_Detail_Service::reject( flxb_gql_detail_id( $in ), flxb_gql_str( $in['reason'] ?? '' ), false !== ( $in['notify'] ?? true ) );
		},
	)
);

flx_gql_mutation(
	'flxDonationCertificates',
	array(
		'description' => __( 'Makes the certificates of the order again or sends them.', 'flx-donate' ),
		'cap'         => $flxb_detail_cap,
		'input'       => flxb_gql_detail_io( $flxb_detail_id + array( 'action' => array( 'FlxDonationCertificateAction!', __( 'What to do.', 'flx-donate' ) ) ) ),
		'output'      => $flxb_detail_message,
		'resolve'     => static function ( $input ) {
			$in = flxb_gql_args( is_array( $input ) ? $input : array() );
			return FLXB_Admin_Detail_Service::certificates( flxb_gql_detail_id( $in ), 'send' === flxb_gql_str( $in['action'] ?? '' ) ? 'send' : 'remake' );
		},
	)
);

flx_gql_mutation(
	'flxDonationRefund',
	array(
		'description' => __( 'Refunds an amount of the gift\'s WooCommerce order line.', 'flx-donate' ),
		'cap'         => $flxb_detail_cap,
		'input'       => flxb_gql_detail_io( $flxb_detail_id + array( 'amount' => array( 'Float!', __( 'Amount, up to what is left.', 'flx-donate' ) ) ) ),
		'output'      => $flxb_detail_message,
		'resolve'     => static function ( $input ) {
			$in  = flxb_gql_args( is_array( $input ) ? $input : array() );
			$amt = $in['amount'] ?? 0;
			return FLXB_Admin_Detail_Service::refund( flxb_gql_detail_id( $in ), is_numeric( $amt ) ? (float) $amt : 0.0 );
		},
	)
);

flx_gql_mutation(
	'flxDonationNoteAdd',
	array(
		'description' => __( 'Adds a note to the gift\'s WooCommerce order.', 'flx-donate' ),
		'cap'         => $flxb_detail_cap,
		'input'       => flxb_gql_detail_io(
			$flxb_detail_id + array(
				'text'     => array( 'String!', __( 'Note.', 'flx-donate' ) ),
				'customer' => array( 'Boolean', __( 'Send it to the donor by e-mail.', 'flx-donate' ) ),
			)
		),
		'output'      => $flxb_detail_message,
		'resolve'     => static function ( $input ) {
			$in = flxb_gql_args( is_array( $input ) ? $input : array() );
			return FLXB_Admin_Detail_Service::note_add( flxb_gql_detail_id( $in ), flxb_gql_str( $in['text'] ?? '' ), true === ( $in['customer'] ?? false ) );
		},
	)
);

flx_gql_mutation(
	'flxDonationNoteDelete',
	array(
		'description' => __( 'Deletes a note of the gift\'s WooCommerce order.', 'flx-donate' ),
		'cap'         => $flxb_detail_cap,
		'input'       => flxb_gql_detail_io( $flxb_detail_id + array( 'noteId' => array( 'Int!', __( 'Note id.', 'flx-donate' ) ) ) ),
		'output'      => $flxb_detail_message,
		'resolve'     => static function ( $input ) {
			$in = flxb_gql_args( is_array( $input ) ? $input : array() );
			return FLXB_Admin_Detail_Service::note_delete( flxb_gql_detail_id( $in ), flxb_gql_int( $in['noteId'] ?? 0 ) );
		},
	)
);

flx_gql_mutation(
	'flxDonationLayoutSave',
	array(
		'description' => __( 'Saves the person\'s order and folded boxes of the donation page.', 'flx-donate' ),
		'cap'         => static fn(): bool => is_user_logged_in(),
		'input'       => flxb_gql_detail_io(
			array(
				'main'   => array( '[String!]!', __( 'Main column.', 'flx-donate' ) ),
				'side'   => array( '[String!]!', __( 'Side column.', 'flx-donate' ) ),
				'closed' => array( '[String!]!', __( 'Folded boxes.', 'flx-donate' ) ),
			)
		),
		'output'      => flxb_gql_detail_io( array( 'ok' => array( 'Boolean', __( 'Saved; null when it was refused.', 'flx-donate' ) ) ) ),
		'resolve'     => static function ( $input ) {
			$in   = flxb_gql_args( is_array( $input ) ? $input : array() );
			$list = static fn( $v ): array => array_values( array_map( 'flxb_gql_str', is_array( $v ) ? $v : array() ) );
			return array( 'ok' => FLXB_Admin_Detail_Service::save_layout( $list( $in['main'] ?? null ), $list( $in['side'] ?? null ), $list( $in['closed'] ?? null ) ) );
		},
	)
);
