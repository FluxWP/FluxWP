<?php
/**
 * Flux Donate admin GraphQL (part b4): the donation types list of the wp-admin screen.
 * Loaded only on flx_gql_register. Resolvers call FLXB_Admin_Types_Service.
 *
 * Rights (fail closed, the classic ones):
 * - flxDonationTypeAdminList: flx_bagis_view.
 * - flxDonationTypeSetStatus / flxDonationTypeMove / flxDonationTypeSamples: flxb_can_manage().
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

flx_gql_enum(
	'FlxDonationTypeStatus',
	array(
		'description' => __( 'Status of a donation type set in wp-admin.', 'flx-donate' ),
		'values'      => array(
			'ACTIVE'   => array( 'value' => 'active' ),
			'CLOSED'   => array( 'value' => 'closed' ),
			'ARCHIVED' => array( 'value' => 'archived' ),
		),
	)
);

flx_gql_type(
	'FlxDonationTypeRow',
	array(
		'description' => __( 'One donation type of the wp-admin list, with labels ready to show.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'databaseId' => array( 'Int!', __( 'Type id.', 'flx-donate' ) ),
				'title'      => array( 'String!', __( 'Title.', 'flx-donate' ) ),
				'stateLabel' => array( 'String!', __( 'Life-cycle as people read it ("Open", "Upcoming (date)"…).', 'flx-donate' ) ),
				'tone'       => array( 'FlxDonationTone!', __( 'Colour of the life-cycle label.', 'flx-donate' ) ),
				'category'   => array( 'String!', __( 'Category name.', 'flx-donate' ) ),
				'shortcode'  => array( 'String!', __( 'Shortcode of the type page.', 'flx-donate' ) ),
				'prices'     => array( 'String!', __( 'Options and prices, formatted.', 'flx-donate' ) ),
				'flags'      => array( 'String!', __( 'Quantity and recurring, when allowed.', 'flx-donate' ) ),
				'hasGoal'    => array( 'Boolean!', __( 'The type has a goal.', 'flx-donate' ) ),
				'percent'    => array( 'Float!', __( 'Share of the goal raised.', 'flx-donate' ) ),
				'progress'   => array( 'String!', __( 'Raised (of goal), formatted.', 'flx-donate' ) ),
				'donors'     => array( 'String!', __( 'Number of donors, formatted.', 'flx-donate' ) ),
				'dates'      => array( 'String!', __( 'Start and end dates, formatted.', 'flx-donate' ) ),
				'forms'      => array( 'String!', __( 'Forms that collect for the type; empty without one.', 'flx-donate' ) ),
				'status'     => array( 'FlxDonationTypeStatus!', __( 'Status.', 'flx-donate' ) ),
				'listUrl'    => array( 'String!', __( 'The type\'s donations in the donation list.', 'flx-donate' ) ),
				'editUrl'    => array( 'String!', __( 'Edit form; empty without the right.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationTypeSample',
	array(
		'description' => __( 'A sample set of donation types that can be loaded.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'value' => array( 'String!', __( 'Key.', 'flx-donate' ) ),
				'label' => array( 'String!', __( 'Name.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationTypeAdminList',
	array(
		'description' => __( 'The donation types screen: the list and the rights.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'items'     => array( '[FlxDonationTypeRow!]!', __( 'Types in display order.', 'flx-donate' ) ),
				'canManage' => array( 'Boolean!', __( 'The user may add, edit, open, close and order types.', 'flx-donate' ) ),
				'newUrl'    => array( 'String!', __( 'New type form; empty without the right.', 'flx-donate' ) ),
				'samples'   => array( '[FlxDonationTypeSample!]!', __( 'Sample sets; empty in the archive or without the right.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_query(
	'flxDonationTypeAdminList',
	array(
		'type'        => 'FlxDonationTypeAdminList!',
		'description' => __( 'Donation types of the wp-admin screen: open and closed ones, or the archive.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Types_Service::can_list(),
		'args'        => array(
			'archived' => array(
				'type'        => 'Boolean',
				'description' => __( 'List the archive instead.', 'flx-donate' ),
			),
		),
		'resolve'     => static function ( $root, $args ): array {
			unset( $root );
			$a = flxb_gql_args( is_array( $args ) ? $args : array() );
			return FLXB_Admin_Types_Service::list( ! empty( $a['archived'] ) );
		},
	)
);

flx_gql_mutation(
	'flxDonationTypeSetStatus',
	array(
		'description' => __( 'Opens, closes or archives a donation type. Opening is refused when one of its forms already collects for another open type.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Types_Service::can_manage(),
		'input'       => flxb_gql_admin_fields(
			array(
				'id'     => array( 'Int!', __( 'Type id.', 'flx-donate' ) ),
				'status' => array( 'FlxDonationTypeStatus!', __( 'New status.', 'flx-donate' ) ),
			)
		),
		'output'      => flxb_gql_admin_fields( array( 'message' => array( 'String', __( 'Result for the notice; null when refused (see errors).', 'flx-donate' ) ) ) ),
		'resolve'     => static function ( $input ) {
			$in = flxb_gql_args( is_array( $input ) ? $input : array() );
			return FLXB_Admin_Types_Service::set_status( flxb_gql_int( $in['id'] ?? 0 ), flxb_gql_str( $in['status'] ?? '' ) );
		},
	)
);

flx_gql_mutation(
	'flxDonationTypeMove',
	array(
		'description' => __( 'Moves a donation type one place up or down.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Types_Service::can_manage(),
		'input'       => flxb_gql_admin_fields(
			array(
				'id' => array( 'Int!', __( 'Type id.', 'flx-donate' ) ),
				'up' => array( 'Boolean!', __( 'True moves up, false down.', 'flx-donate' ) ),
			)
		),
		'output'      => flxb_gql_admin_fields( array( 'message' => array( 'String', __( 'Result for the notice; null when refused (see errors).', 'flx-donate' ) ) ) ),
		'resolve'     => static function ( $input ) {
			$in = flxb_gql_args( is_array( $input ) ? $input : array() );
			return FLXB_Admin_Types_Service::move( flxb_gql_int( $in['id'] ?? 0 ), empty( $in['up'] ) ? 1 : -1 );
		},
	)
);

flx_gql_mutation(
	'flxDonationTypeSamples',
	array(
		'description' => __( 'Loads a sample set of donation types and categories.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Types_Service::can_manage(),
		'input'       => flxb_gql_admin_fields( array( 'key' => array( 'String!', __( 'Sample set key.', 'flx-donate' ) ) ) ),
		'output'      => flxb_gql_admin_fields( array( 'message' => array( 'String', __( 'Result for the notice; null when refused (see errors).', 'flx-donate' ) ) ) ),
		'resolve'     => static function ( $input ) {
			$in = flxb_gql_args( is_array( $input ) ? $input : array() );
			return FLXB_Admin_Types_Service::samples( flxb_gql_str( $in['key'] ?? '' ) );
		},
	)
);
