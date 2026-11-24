<?php
/**
 * Flux Donate admin GraphQL (part b7): the Tracking page of the wp-admin screen (tracking module): the list
 * with its bulk steps and one item's page. Loaded only on flx_gql_register. Resolvers call
 * FLXB_Admin_Track_Service.
 *
 * Rights (fail closed, the classic ones):
 * - flxDonationTrack, flxDonationTrackItem: FLXB_Track::can_view().
 * - every mutation: FLXB_Track::can().
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

/**
 * A tracking mutation: input fields, `message` output and the resolver.
 *
 * @param array<string,array{0:string,1:string}> $input   Input fields.
 * @param callable                                $resolve Input → result.
 * @param array<string,array{0:string,1:string}> $output  Output fields besides message.
 * @return array<string,mixed>
 */
function flxb_gql_track_mutation( string $description, array $input, callable $resolve, array $output = array() ): array {
	return array(
		'description' => $description,
		'cap'         => static fn(): bool => FLXB_Admin_Track_Service::can(),
		'input'       => flxb_gql_admin_fields( $input ),
		'output'      => flxb_gql_admin_fields( array( 'message' => array( 'String', __( 'Result for the notice; null when refused (see errors).', 'flx-donate' ) ) ) + $output ),
		'resolve'     => static function ( $in ) use ( $resolve ) {
			return $resolve( flxb_gql_args( is_array( $in ) ? $in : array() ) );
		},
	);
}

$flxb_track_types = array(
	'FlxDonationTrackTab'    => array(
		__( 'A status tab of the tracking list.', 'flx-donate' ),
		array(
			'value' => array( 'String!', __( 'Status; empty = all.', 'flx-donate' ) ),
			'label' => array( 'String!', __( 'Label.', 'flx-donate' ) ),
			'count' => array( 'String!', __( 'Items, formatted.', 'flx-donate' ) ),
		),
	),
	'FlxDonationTrackAction' => array(
		__( 'A button of the page head.', 'flx-donate' ),
		array(
			'label'   => array( 'String!', __( 'Label.', 'flx-donate' ) ),
			'url'     => array( 'String!', __( 'Address.', 'flx-donate' ) ),
			'primary' => array( 'Boolean!', __( 'Main button.', 'flx-donate' ) ),
		),
	),
	'FlxDonationTrackPay'    => array(
		__( 'Payment status of the gift.', 'flx-donate' ),
		array(
			'label' => array( 'String!', __( 'Status.', 'flx-donate' ) ),
			'tone'  => array( 'String!', __( 'Colour.', 'flx-donate' ) ),
		),
	),
	'FlxDonationTrackTarget' => array(
		__( 'Target date of an item.', 'flx-donate' ),
		array(
			'date'  => array( 'String!', __( 'Day, formatted; empty without one.', 'flx-donate' ) ),
			'state' => array( 'String!', __( 'early, window, late, ok or empty.', 'flx-donate' ) ),
			'label' => array( 'String!', __( 'due or late; empty otherwise.', 'flx-donate' ) ),
		),
	),
	'FlxDonationTrackRow'    => array(
		__( 'One item of the tracking list.', 'flx-donate' ),
		array(
			'databaseId'  => array( 'Int!', __( 'Item id.', 'flx-donate' ) ),
			'code'        => array( 'String!', __( 'Code.', 'flx-donate' ) ),
			'option'      => array( 'String!', __( 'Option.', 'flx-donate' ) ),
			'beneficiary' => array( 'String!', __( 'On behalf of.', 'flx-donate' ) ),
			'phone'       => array( 'String!', __( 'Owner phone, formatted.', 'flx-donate' ) ),
			'donationId'  => array( 'Int!', __( 'Donation id.', 'flx-donate' ) ),
			'donationUrl' => array( 'String!', __( 'The donation page.', 'flx-donate' ) ),
			'donor'       => array( 'String!', __( 'Donor name.', 'flx-donate' ) ),
			'pay'         => array( 'FlxDonationTrackPay', __( 'Payment status; null without one.', 'flx-donate' ) ),
			'target'      => array( 'FlxDonationTrackTarget!', __( 'Target date.', 'flx-donate' ) ),
			'place'       => array( 'String!', __( 'Partner name.', 'flx-donate' ) ),
			'status'      => array( 'String!', __( 'Status.', 'flx-donate' ) ),
			'tone'        => array( 'String!', __( 'Colour of the status.', 'flx-donate' ) ),
			'files'       => array( 'String!', __( 'Files and how many were sent; empty without any.', 'flx-donate' ) ),
			'cells'       => array( '[String!]!', __( 'HTML of the extra columns, in their order.', 'flx-donate' ) ),
		),
	),
	'FlxDonationTrackList'   => array(
		__( 'The tracking list.', 'flx-donate' ),
		array(
			'title'        => array( 'String!', __( 'Page title.', 'flx-donate' ) ),
			'partnerLabel' => array( 'String!', __( 'What a partner is called.', 'flx-donate' ) ),
			'filesLabel'   => array( 'String!', __( 'What the files are called.', 'flx-donate' ) ),
			'canEdit'      => array( 'Boolean!', __( 'The viewer can change items.', 'flx-donate' ) ),
			'actions'      => array( '[FlxDonationTrackAction!]!', __( 'Buttons of the page head.', 'flx-donate' ) ),
			'tabs'         => array( '[FlxDonationTrackTab!]!', __( 'Status tabs with counts.', 'flx-donate' ) ),
			'types'        => array( '[FlxDonationChoice!]!', __( 'Tracked donation types; empty with one.', 'flx-donate' ) ),
			'places'       => array( '[FlxDonationChoice!]!', __( 'Partners.', 'flx-donate' ) ),
			'steps'        => array( '[FlxDonationChoice!]!', __( 'Steps of the bulk bar.', 'flx-donate' ) ),
			'columns'      => array( '[String!]!', __( 'Labels of the extra columns.', 'flx-donate' ) ),
			'items'        => array( '[FlxDonationTrackRow!]!', __( 'Items of the page.', 'flx-donate' ) ),
			'total'        => array( 'Int!', __( 'Items in all.', 'flx-donate' ) ),
			'totalLabel'   => array( 'String!', __( 'Items in all, as people read it.', 'flx-donate' ) ),
			'page'         => array( 'Int!', __( 'Page.', 'flx-donate' ) ),
			'perPage'      => array( 'Int!', __( 'Items per page.', 'flx-donate' ) ),
		),
	),
	'FlxDonationTrackFlow'   => array(
		__( 'A step of the flow strip.', 'flx-donate' ),
		array(
			'label' => array( 'String!', __( 'Status.', 'flx-donate' ) ),
			'state' => array( 'String!', __( 'done, on or empty.', 'flx-donate' ) ),
			'date'  => array( 'String!', __( 'When, formatted; empty when not reached.', 'flx-donate' ) ),
		),
	),
	'FlxDonationTrackRowHtml' => array(
		__( 'A label with its value as HTML.', 'flx-donate' ),
		array(
			'label' => array( 'String!', __( 'Label.', 'flx-donate' ) ),
			'html'  => array( 'String!', __( 'Value (escaped HTML).', 'flx-donate' ) ),
		),
	),
	'FlxDonationTrackStep'   => array(
		__( 'A status button of an item.', 'flx-donate' ),
		array(
			'step'    => array( 'String!', __( 'Step.', 'flx-donate' ) ),
			'label'   => array( 'String!', __( 'Label.', 'flx-donate' ) ),
			'tone'    => array( 'String!', __( 'primary, danger or empty.', 'flx-donate' ) ),
			'confirm' => array( 'String!', __( 'Question asked first; empty = none.', 'flx-donate' ) ),
		),
	),
	'FlxDonationTrackFile'   => array(
		__( 'A file kept on an item.', 'flx-donate' ),
		array(
			'att'  => array( 'Int!', __( 'Attachment id.', 'flx-donate' ) ),
			'url'  => array( 'String!', __( 'Address.', 'flx-donate' ) ),
			'kind' => array( 'String!', __( 'video, image, audio or file.', 'flx-donate' ) ),
			'name' => array( 'String!', __( 'File name.', 'flx-donate' ) ),
			'meta' => array( 'String!', __( 'When and by whom.', 'flx-donate' ) ),
			'sent' => array( '[String!]!', __( 'Where and when it was sent.', 'flx-donate' ) ),
		),
	),
	'FlxDonationTrackEvent'  => array(
		__( 'A line of the history.', 'flx-donate' ),
		array(
			'date'  => array( 'String!', __( 'When, formatted.', 'flx-donate' ) ),
			'event' => array( 'String!', __( 'Event key.', 'flx-donate' ) ),
			'label' => array( 'String!', __( 'Event.', 'flx-donate' ) ),
			'more'  => array( 'String!', __( 'Details; empty without.', 'flx-donate' ) ),
			'note'  => array( 'String!', __( 'Note.', 'flx-donate' ) ),
			'user'  => array( 'String!', __( 'Who.', 'flx-donate' ) ),
		),
	),
	'FlxDonationTrackItem'   => array(
		__( 'One tracked item.', 'flx-donate' ),
		array(
			'databaseId'     => array( 'Int!', __( 'Item id.', 'flx-donate' ) ),
			'code'           => array( 'String!', __( 'Code.', 'flx-donate' ) ),
			'title'          => array( 'String!', __( 'Page title.', 'flx-donate' ) ),
			'subtitle'       => array( 'String!', __( 'Option.', 'flx-donate' ) ),
			'listTitle'      => array( 'String!', __( 'Title of the list.', 'flx-donate' ) ),
			'partnerLabel'   => array( 'String!', __( 'What a partner is called.', 'flx-donate' ) ),
			'filesLabel'     => array( 'String!', __( 'What the files are called.', 'flx-donate' ) ),
			'itemLabel'      => array( 'String!', __( 'What one item is called.', 'flx-donate' ) ),
			'assignLabel'    => array( 'String!', __( 'Button: send to partner.', 'flx-donate' ) ),
			'status'         => array( 'String!', __( 'Status.', 'flx-donate' ) ),
			'statusKey'      => array( 'String!', __( 'Status key.', 'flx-donate' ) ),
			'tone'           => array( 'String!', __( 'Colour of the status.', 'flx-donate' ) ),
			'canceled'       => array( 'Boolean!', __( 'Canceled.', 'flx-donate' ) ),
			'late'           => array( 'Boolean!', __( 'Past the target date and not completed.', 'flx-donate' ) ),
			'canEdit'        => array( 'Boolean!', __( 'The viewer can change it.', 'flx-donate' ) ),
			'canAssign'      => array( 'Boolean!', __( 'It can be sent to a partner now.', 'flx-donate' ) ),
			'flow'           => array( '[FlxDonationTrackFlow!]!', __( 'Flow strip.', 'flx-donate' ) ),
			'details'        => array( '[FlxDonationTrackRowHtml!]!', __( 'Details.', 'flx-donate' ) ),
			'beneficiary'    => array( 'String!', __( 'On behalf of.', 'flx-donate' ) ),
			'phone'          => array( 'String!', __( 'Owner phone as stored.', 'flx-donate' ) ),
			'targetDate'     => array( 'String!', __( 'Target day (Y-m-d); empty without one.', 'flx-donate' ) ),
			'donationStatus' => array( 'String!', __( 'Status of the donation; empty without one.', 'flx-donate' ) ),
			'donationTone'   => array( 'String!', __( 'Colour of the donation status.', 'flx-donate' ) ),
			'payment'        => array( '[FlxDonationTrackRowHtml!]!', __( 'Donation and payment.', 'flx-donate' ) ),
			'unpaid'         => array( 'Boolean!', __( 'The payment is not received yet.', 'flx-donate' ) ),
			'places'         => array( '[FlxDonationChoice!]!', __( 'Partners it can go to.', 'flx-donate' ) ),
			'actions'        => array( '[FlxDonationTrackStep!]!', __( 'Status buttons.', 'flx-donate' ) ),
			'files'          => array( '[FlxDonationTrackFile!]!', __( 'Files.', 'flx-donate' ) ),
			'filesHint'      => array( 'String!', __( 'Hint of the files card.', 'flx-donate' ) ),
			'canSend'        => array( 'Boolean!', __( 'Files can be sent to the owner over WhatsApp.', 'flx-donate' ) ),
			'sendHint'       => array( 'String!', __( 'Why files cannot be sent; empty when they can.', 'flx-donate' ) ),
			'ownerPhone'     => array( 'String!', __( 'Where files go, formatted.', 'flx-donate' ) ),
			'uploadUrl'      => array( 'String!', __( 'Form address of a file upload; empty without the right.', 'flx-donate' ) ),
			'uploadNonce'    => array( 'String!', __( 'Nonce of a file upload.', 'flx-donate' ) ),
			'islands'        => array( '[String!]!', __( 'Ids of the cards printed with the page (extra tabs).', 'flx-donate' ) ),
			'history'        => array( '[FlxDonationTrackEvent!]!', __( 'History.', 'flx-donate' ) ),
		),
	),
);
foreach ( $flxb_track_types as $flxb_name => $flxb_def ) {
	flx_gql_type(
		$flxb_name,
		array(
			'description' => $flxb_def[0],
			'fields'      => flxb_gql_admin_fields( $flxb_def[1] ),
		)
	);
}
unset( $flxb_track_types, $flxb_name, $flxb_def );

flx_gql_input(
	'FlxDonationTrackFilter',
	array(
		'description' => __( 'Filter of the tracking list.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'status' => array( 'String', __( 'Status; empty = all.', 'flx-donate' ) ),
				'place'  => array( 'String', __( 'Partner ref.', 'flx-donate' ) ),
				'typeId' => array( 'Int', __( 'Donation type.', 'flx-donate' ) ),
				'paid'   => array( 'String', __( '"1" paid, "0" not paid, empty = any.', 'flx-donate' ) ),
				'due'    => array( 'Boolean', __( 'Only items due now, by target date.', 'flx-donate' ) ),
				'search' => array( 'String', __( 'Code, name, phone, donation no.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_query(
	'flxDonationTrack',
	array(
		'type'        => 'FlxDonationTrackList!',
		'description' => __( 'The tracking list.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Track_Service::can_view(),
		'args'        => array(
			'filter' => array(
				'type'        => 'FlxDonationTrackFilter',
				'description' => __( 'Filter.', 'flx-donate' ),
			),
			'page'   => array(
				'type'        => 'Int',
				'description' => __( 'Page (from 1).', 'flx-donate' ),
			),
		),
		'resolve'     => static function ( $root, $args ): array {
			unset( $root );
			$a = flxb_gql_args( is_array( $args ) ? $args : array() );
			$f = is_array( $a['filter'] ?? null ) ? flxb_gql_args( $a['filter'] ) : array();
			return FLXB_Admin_Track_Service::list( $f, max( 1, flxb_gql_int( $a['page'] ?? 1 ) ) );
		},
	)
);

flx_gql_query(
	'flxDonationTrackItem',
	array(
		'type'        => 'FlxDonationTrackItem',
		'description' => __( 'One tracked item; null when it does not exist.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Track_Service::can_view(),
		'args'        => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Item id.', 'flx-donate' ),
			),
		),
		'resolve'     => static function ( $root, $args ): ?array {
			unset( $root );
			$a = flxb_gql_args( is_array( $args ) ? $args : array() );
			return FLXB_Admin_Track_Service::item( max( 0, flxb_gql_int( $a['id'] ?? 0 ) ) );
		},
	)
);

flx_gql_mutation(
	'flxDonationTrackApply',
	flxb_gql_track_mutation(
		__( 'Applies a step to items (approve, assign, revoke, done, delivered, cancel, reset).', 'flx-donate' ),
		array(
			'ids'   => array( '[Int!]!', __( 'Items.', 'flx-donate' ) ),
			'step'  => array( 'String!', __( 'Step.', 'flx-donate' ) ),
			'place' => array( 'String!', __( 'Partner ref of assign; empty otherwise.', 'flx-donate' ) ),
			'note'  => array( 'String!', __( 'Note for the history.', 'flx-donate' ) ),
		),
		static fn( array $in ) => FLXB_Admin_Track_Service::apply(
			array_values( array_map( 'flxb_gql_int', is_array( $in['ids'] ?? null ) ? $in['ids'] : array() ) ),
			flxb_gql_str( $in['step'] ?? '' ),
			flxb_gql_str( $in['place'] ?? '' ),
			flxb_gql_str( $in['note'] ?? '' )
		),
		array( 'warning' => array( 'Boolean', __( 'Some items were skipped (the message says why).', 'flx-donate' ) ) )
	)
);

flx_gql_mutation(
	'flxDonationTrackEdit',
	flxb_gql_track_mutation(
		__( 'Saves who an item is on behalf of, the owner phone and the target date.', 'flx-donate' ),
		array(
			'id'         => array( 'Int!', __( 'Item id.', 'flx-donate' ) ),
			'name'       => array( 'String!', __( 'On behalf of.', 'flx-donate' ) ),
			'phone'      => array( 'String!', __( 'Owner phone; empty = donor phone.', 'flx-donate' ) ),
			'targetDate' => array( 'String!', __( 'Target day (Y-m-d); empty = none.', 'flx-donate' ) ),
		),
		static fn( array $in ) => FLXB_Admin_Track_Service::edit( flxb_gql_int( $in['id'] ?? 0 ), flxb_gql_str( $in['name'] ?? '' ), flxb_gql_str( $in['phone'] ?? '' ), flxb_gql_str( $in['targetDate'] ?? '' ) )
	)
);

flx_gql_mutation(
	'flxDonationTrackNote',
	flxb_gql_track_mutation(
		__( 'Adds a note to an item\'s history.', 'flx-donate' ),
		array(
			'id'   => array( 'Int!', __( 'Item id.', 'flx-donate' ) ),
			'note' => array( 'String!', __( 'Note.', 'flx-donate' ) ),
		),
		static fn( array $in ) => FLXB_Admin_Track_Service::note( flxb_gql_int( $in['id'] ?? 0 ), flxb_gql_str( $in['note'] ?? '' ) )
	)
);

flx_gql_mutation(
	'flxDonationTrackFileSend',
	flxb_gql_track_mutation(
		__( 'Sends a file of an item to its owner over WhatsApp.', 'flx-donate' ),
		array(
			'id'  => array( 'Int!', __( 'Item id.', 'flx-donate' ) ),
			'att' => array( 'Int!', __( 'Attachment id.', 'flx-donate' ) ),
		),
		static fn( array $in ) => FLXB_Admin_Track_Service::file_send( flxb_gql_int( $in['id'] ?? 0 ), flxb_gql_int( $in['att'] ?? 0 ) )
	)
);

flx_gql_mutation(
	'flxDonationTrackFileRemove',
	flxb_gql_track_mutation(
		__( 'Removes a file from an item; it stays in the media library.', 'flx-donate' ),
		array(
			'id'  => array( 'Int!', __( 'Item id.', 'flx-donate' ) ),
			'att' => array( 'Int!', __( 'Attachment id.', 'flx-donate' ) ),
		),
		static fn( array $in ) => FLXB_Admin_Track_Service::file_remove( flxb_gql_int( $in['id'] ?? 0 ), flxb_gql_int( $in['att'] ?? 0 ) )
	)
);
