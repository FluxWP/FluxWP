<?php
/**
 * Flux Donate admin GraphQL (part b4): the donation types list and the type editor of the wp-admin screen.
 * Loaded only on flx_gql_register. Resolvers call FLXB_Admin_Types_Service.
 *
 * Rights (fail closed, the classic ones):
 * - flxDonationTypeAdminList: flx_bagis_view.
 * - flxDonationTypeForm, flxDonationTypeSave, flxDonationTypeDelete, flxDonationTypeSetStatus,
 *   flxDonationTypeMove, flxDonationTypeSamples: flxb_can_manage().
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

flx_gql_type(
	'FlxDonationTypeAmount',
	array(
		'description' => __( 'An amount in a currency.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'currency' => array( 'String!', __( 'Currency code.', 'flx-donate' ) ),
				'amount'   => array( 'Float!', __( 'Amount.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationTypeFormOption',
	array(
		'description' => __( 'One option of a donation type in the editor.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'key'      => array( 'String!', __( 'Key.', 'flx-donate' ) ),
				'label'    => array( 'String!', __( 'Name.', 'flx-donate' ) ),
				'price'    => array( 'Float!', __( 'Price in the type\'s currency; 0 = not announced yet.', 'flx-donate' ) ),
				'unit'     => array( 'String!', __( 'Unit key; empty = not set.', 'flx-donate' ) ),
				'unitsPer' => array( 'Int!', __( 'Units per choice.', 'flx-donate' ) ),
				'badge'    => array( 'String!', __( 'Badge key.', 'flx-donate' ) ),
				'active'   => array( 'Boolean!', __( 'Shown and payable.', 'flx-donate' ) ),
				'prices'   => array( '[FlxDonationTypeAmount!]!', __( 'Prices in the other currencies.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationTypeFormLink',
	array(
		'description' => __( 'A payment form that can be linked to a donation type.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'databaseId' => array( 'Int!', __( 'Form id.', 'flx-donate' ) ),
				'title'      => array( 'String!', __( 'Form title.', 'flx-donate' ) ),
				'others'     => array( 'String!', __( 'Other open types the form is linked to; empty without one.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationTypeLabel',
	array(
		'description' => __( 'A tracking word the type may override.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'key'         => array( 'String!', __( 'Key.', 'flx-donate' ) ),
				'label'       => array( 'String!', __( 'What it is.', 'flx-donate' ) ),
				'value'       => array( 'String!', __( 'The type\'s word; empty = the default.', 'flx-donate' ) ),
				'placeholder' => array( 'String!', __( 'The default word.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxDonationTypeForm',
	array(
		'description' => __( 'The donation type editor: the fields and their choices.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'databaseId'      => array( 'Int!', __( 'Type id; 0 = a new type.', 'flx-donate' ) ),
				'title'           => array( 'String!', __( 'Title.', 'flx-donate' ) ),
				'slug'            => array( 'String!', __( 'Slug.', 'flx-donate' ) ),
				'category'        => array( 'String!', __( 'Category key; empty = none.', 'flx-donate' ) ),
				'currency'        => array( 'String!', __( 'Currency code.', 'flx-donate' ) ),
				'description'     => array( 'String!', __( 'Short description.', 'flx-donate' ) ),
				'subtitle'        => array( 'String!', __( 'Card subtitle.', 'flx-donate' ) ),
				'icon'            => array( 'String!', __( 'Card icon; empty = automatic.', 'flx-donate' ) ),
				'imageId'         => array( 'Int!', __( 'Image attachment id; 0 = none.', 'flx-donate' ) ),
				'imageUrl'        => array( 'String!', __( 'Image address; empty = none.', 'flx-donate' ) ),
				'options'         => array( '[FlxDonationTypeFormOption!]!', __( 'Options.', 'flx-donate' ) ),
				'allowFree'       => array( 'Boolean!', __( 'Free amount allowed.', 'flx-donate' ) ),
				'minAmount'       => array( 'Float!', __( 'Minimum free amount.', 'flx-donate' ) ),
				'minByCurrency'   => array( '[FlxDonationTypeAmount!]!', __( 'Minimum free amount in the other currencies.', 'flx-donate' ) ),
				'allowQuantity'   => array( 'Boolean!', __( 'Quantity allowed.', 'flx-donate' ) ),
				'maxQuantity'     => array( 'Int!', __( 'Maximum quantity; 0 = no limit.', 'flx-donate' ) ),
				'allowRecurring'  => array( 'Boolean!', __( 'Recurring giving allowed.', 'flx-donate' ) ),
				'formIds'         => array( '[Int!]!', __( 'Linked forms.', 'flx-donate' ) ),
				'lockPreselect'   => array( 'Boolean!', __( 'Hide the choice in the form when it was preselected.', 'flx-donate' ) ),
				'trackOn'         => array( 'Boolean!', __( 'The tracking module is on.', 'flx-donate' ) ),
				'trackItems'      => array( 'Boolean!', __( 'Track every unit one by one.', 'flx-donate' ) ),
				'fileCompletes'   => array( 'Boolean!', __( 'The first file marks it completed.', 'flx-donate' ) ),
				'trackLabels'     => array( '[FlxDonationTypeLabel!]!', __( 'Tracking words of the type.', 'flx-donate' ) ),
				'kurbanOn'        => array( 'Boolean!', __( 'Gifts on behalf of a person are on.', 'flx-donate' ) ),
				'requiresProxy'   => array( 'Boolean!', __( 'Proxy (vekâlet) required.', 'flx-donate' ) ),
				'intention'       => array( 'String!', __( 'Intention key; empty = none.', 'flx-donate' ) ),
				'grouping'        => array( 'String!', __( 'Animal: buyukbas, kucukbas or none.', 'flx-donate' ) ),
				'meatToNeedy'     => array( 'Boolean!', __( 'All of the meat goes to people in need.', 'flx-donate' ) ),
				'status'          => array( 'FlxDonationTypeStatus!', __( 'Status.', 'flx-donate' ) ),
				'startDate'       => array( 'String!', __( 'Start date (Y-m-d); empty = none.', 'flx-donate' ) ),
				'endDate'         => array( 'String!', __( 'End date (Y-m-d); empty = none.', 'flx-donate' ) ),
				'goal'            => array( 'Float!', __( 'Goal amount; 0 = none.', 'flx-donate' ) ),
				'shortcode'       => array( 'String!', __( 'Shortcode; empty for a new type.', 'flx-donate' ) ),
				'hasGifts'        => array( 'Boolean!', __( 'The type has gifts (it can be archived, not deleted).', 'flx-donate' ) ),
				'categories'      => array( '[FlxDonationChoice!]!', __( 'Categories.', 'flx-donate' ) ),
				'categoriesUrl'   => array( 'String!', __( 'Where categories are edited.', 'flx-donate' ) ),
				'currencies'      => array( '[FlxDonationChoice!]!', __( 'Currencies.', 'flx-donate' ) ),
				'extraCurrencies' => array( '[String!]!', __( 'Other currencies donors can choose (prices per option).', 'flx-donate' ) ),
				'icons'           => array( '[FlxDonationChoice!]!', __( 'Card icons.', 'flx-donate' ) ),
				'units'           => array( '[FlxDonationChoice!]!', __( 'Units.', 'flx-donate' ) ),
				'badges'          => array( '[FlxDonationChoice!]!', __( 'Option badges.', 'flx-donate' ) ),
				'intentions'      => array( '[FlxDonationChoice!]!', __( 'Intentions.', 'flx-donate' ) ),
				'forms'           => array( '[FlxDonationTypeFormLink!]!', __( 'Payment forms that can be linked.', 'flx-donate' ) ),
				'listUrl'         => array( 'String!', __( 'The donation types list.', 'flx-donate' ) ),
				'islands'         => array( '[FlxDonationIsland!]!', __( 'Cards printed on the page: form mapping, translations, other plugins (empty outside wp-admin).', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_input(
	'FlxDonationTypeAmountInput',
	array(
		'description' => __( 'An amount in a currency.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'currency' => array( 'String!', __( 'Currency code.', 'flx-donate' ) ),
				'amount'   => array( 'Float!', __( 'Amount; 0 = not offered.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_input(
	'FlxDonationTypeOptionInput',
	array(
		'description' => __( 'One option of a donation type.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'key'      => array( 'String!', __( 'Key; empty = from the name.', 'flx-donate' ) ),
				'label'    => array( 'String!', __( 'Name; an option without a name is dropped.', 'flx-donate' ) ),
				'price'    => array( 'Float!', __( 'Price; 0 = not announced yet.', 'flx-donate' ) ),
				'unit'     => array( 'String!', __( 'Unit key.', 'flx-donate' ) ),
				'unitsPer' => array( 'Int!', __( 'Units per choice.', 'flx-donate' ) ),
				'badge'    => array( 'String!', __( 'Badge key.', 'flx-donate' ) ),
				'active'   => array( 'Boolean!', __( 'Shown and payable.', 'flx-donate' ) ),
				'prices'   => array( '[FlxDonationTypeAmountInput!]!', __( 'Prices in the other currencies.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_input(
	'FlxDonationTypeLabelInput',
	array(
		'description' => __( 'A tracking word of the type.', 'flx-donate' ),
		'fields'      => flxb_gql_admin_fields(
			array(
				'key'   => array( 'String!', __( 'Key.', 'flx-donate' ) ),
				'value' => array( 'String!', __( 'Word; empty = the default.', 'flx-donate' ) ),
			)
		),
	)
);

flx_gql_query(
	'flxDonationTypeForm',
	array(
		'type'        => 'FlxDonationTypeForm',
		'description' => __( 'The editor of a donation type; id 0 = a new one. Null when the type does not exist.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Types_Service::can_manage(),
		'args'        => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Type id; 0 = a new type.', 'flx-donate' ),
			),
		),
		'resolve'     => static function ( $root, $args ): ?array {
			unset( $root );
			$a = flxb_gql_args( is_array( $args ) ? $args : array() );
			return FLXB_Admin_Types_Service::form( max( 0, flxb_gql_int( $a['id'] ?? 0 ) ) );
		},
	)
);

/**
 * Screen fields of flxDonationTypeSave → the classic editor fields of FLXB_Types::save().
 *
 * @param array<string,mixed> $in Input.
 * @return array<string,mixed>
 */
function flxb_gql_type_save_fields( array $in ): array {
	$amounts = static function ( $list ): array {
		$out = array();
		foreach ( is_array( $list ) ? $list : array() as $p ) {
			$p = flxb_gql_args( is_array( $p ) ? $p : array() );
			$c = strtoupper( flxb_gql_str( $p['currency'] ?? '' ) );
			if ( '' !== $c ) {
				$out[ $c ] = is_numeric( $p['amount'] ?? null ) ? (float) $p['amount'] : 0.0;
			}
		}
		return $out;
	};
	$options = array();
	foreach ( is_array( $in['options'] ?? null ) ? $in['options'] : array() as $o ) {
		$o         = flxb_gql_args( is_array( $o ) ? $o : array() );
		$options[] = array(
			'key'       => flxb_gql_str( $o['key'] ?? '' ),
			'label'     => flxb_gql_str( $o['label'] ?? '' ),
			'price'     => is_numeric( $o['price'] ?? null ) ? (float) $o['price'] : 0.0,
			'unit'      => flxb_gql_str( $o['unit'] ?? '' ),
			'units_per' => flxb_gql_int( $o['unitsPer'] ?? 1 ),
			'badge'     => flxb_gql_str( $o['badge'] ?? '' ),
			'active'    => ! empty( $o['active'] ),
			'prices'    => $amounts( $o['prices'] ?? null ),
		);
	}
	$meta = array(
		'subtitle'        => flxb_gql_str( $in['subtitle'] ?? '' ),
		'icon'            => flxb_gql_str( $in['icon'] ?? '' ),
		'lock_preselect'  => ! empty( $in['lockPreselect'] ),
		'min_by_currency' => $amounts( $in['minByCurrency'] ?? null ),
	);
	foreach ( array( 'trackItems' => 'track_items', 'fileCompletes' => 'file_completes', 'requiresProxy' => 'requires_proxy', 'meatToNeedy' => 'meat_to_needy' ) as $k => $mk ) {
		if ( isset( $in[ $k ] ) ) {
			$meta[ $mk ] = ! empty( $in[ $k ] );
		}
	}
	foreach ( array( 'intention', 'grouping' ) as $k ) {
		if ( isset( $in[ $k ] ) ) {
			$meta[ $k ] = flxb_gql_str( $in[ $k ] );
		}
	}
	if ( isset( $in['trackLabels'] ) && is_array( $in['trackLabels'] ) ) {
		$meta['labels'] = array();
		foreach ( $in['trackLabels'] as $l ) {
			$l = flxb_gql_args( is_array( $l ) ? $l : array() );
			$meta['labels'][ flxb_gql_str( $l['key'] ?? '' ) ] = flxb_gql_str( $l['value'] ?? '' );
		}
	}
	return array(
		'title'           => flxb_gql_str( $in['title'] ?? '' ),
		'slug'            => flxb_gql_str( $in['slug'] ?? '' ),
		'category'        => flxb_gql_str( $in['category'] ?? '' ),
		'currency'        => flxb_gql_str( $in['currency'] ?? '' ),
		'description'     => flxb_gql_str( $in['description'] ?? '' ),
		'image_id'        => max( 0, flxb_gql_int( $in['imageId'] ?? 0 ) ),
		'options'         => $options,
		'allow_free'      => ! empty( $in['allowFree'] ),
		'min_amount'      => is_numeric( $in['minAmount'] ?? null ) ? (float) $in['minAmount'] : 0.0,
		'allow_quantity'  => ! empty( $in['allowQuantity'] ),
		'max_quantity'    => max( 0, flxb_gql_int( $in['maxQuantity'] ?? 0 ) ),
		'allow_recurring' => ! empty( $in['allowRecurring'] ),
		'form_ids'        => array_map( 'flxb_gql_int', is_array( $in['formIds'] ?? null ) ? $in['formIds'] : array() ),
		'status'          => flxb_gql_str( $in['status'] ?? 'active' ),
		'start_date'      => flxb_gql_str( $in['startDate'] ?? '' ),
		'end_date'        => flxb_gql_str( $in['endDate'] ?? '' ),
		'goal_amount'     => is_numeric( $in['goal'] ?? null ) ? (float) $in['goal'] : 0.0,
		'meta'            => $meta,
	);
}

flx_gql_mutation(
	'flxDonationTypeSave',
	array(
		'description' => __( 'Creates or saves a donation type. A linked form that already collects for another open type with the same dates is not linked (see warning).', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Types_Service::can_manage(),
		'input'       => flxb_gql_admin_fields(
			array(
				'id'             => array( 'Int!', __( 'Type id; 0 = a new type.', 'flx-donate' ) ),
				'title'          => array( 'String!', __( 'Title.', 'flx-donate' ) ),
				'slug'           => array( 'String!', __( 'Slug; empty = from the title.', 'flx-donate' ) ),
				'category'       => array( 'String!', __( 'Category key.', 'flx-donate' ) ),
				'currency'       => array( 'String!', __( 'Currency code.', 'flx-donate' ) ),
				'description'    => array( 'String!', __( 'Short description.', 'flx-donate' ) ),
				'subtitle'       => array( 'String!', __( 'Card subtitle.', 'flx-donate' ) ),
				'icon'           => array( 'String!', __( 'Card icon.', 'flx-donate' ) ),
				'imageId'        => array( 'Int!', __( 'Image attachment id; 0 = none.', 'flx-donate' ) ),
				'options'        => array( '[FlxDonationTypeOptionInput!]!', __( 'Options.', 'flx-donate' ) ),
				'allowFree'      => array( 'Boolean!', __( 'Free amount allowed.', 'flx-donate' ) ),
				'minAmount'      => array( 'Float!', __( 'Minimum free amount.', 'flx-donate' ) ),
				'minByCurrency'  => array( '[FlxDonationTypeAmountInput!]!', __( 'Minimum free amount in the other currencies.', 'flx-donate' ) ),
				'allowQuantity'  => array( 'Boolean!', __( 'Quantity allowed.', 'flx-donate' ) ),
				'maxQuantity'    => array( 'Int!', __( 'Maximum quantity; 0 = no limit.', 'flx-donate' ) ),
				'allowRecurring' => array( 'Boolean!', __( 'Recurring giving allowed.', 'flx-donate' ) ),
				'formIds'        => array( '[Int!]!', __( 'Linked forms.', 'flx-donate' ) ),
				'lockPreselect'  => array( 'Boolean!', __( 'Hide the choice in the form when it was preselected.', 'flx-donate' ) ),
				'trackItems'     => array( 'Boolean', __( 'Track every unit one by one (tracking module).', 'flx-donate' ) ),
				'fileCompletes'  => array( 'Boolean', __( 'The first file marks it completed (tracking module).', 'flx-donate' ) ),
				'trackLabels'    => array( '[FlxDonationTypeLabelInput!]', __( 'Tracking words (tracking module).', 'flx-donate' ) ),
				'requiresProxy'  => array( 'Boolean', __( 'Proxy (vekâlet) required (gifts on behalf of a person).', 'flx-donate' ) ),
				'intention'      => array( 'String', __( 'Intention key (gifts on behalf of a person).', 'flx-donate' ) ),
				'grouping'       => array( 'String', __( 'Animal: buyukbas, kucukbas or none (gifts on behalf of a person).', 'flx-donate' ) ),
				'meatToNeedy'    => array( 'Boolean', __( 'All of the meat goes to people in need (gifts on behalf of a person).', 'flx-donate' ) ),
				'status'         => array( 'FlxDonationTypeStatus!', __( 'Status.', 'flx-donate' ) ),
				'startDate'      => array( 'String!', __( 'Start date (Y-m-d); empty = none.', 'flx-donate' ) ),
				'endDate'        => array( 'String!', __( 'End date (Y-m-d); empty = none.', 'flx-donate' ) ),
				'goal'           => array( 'Float!', __( 'Goal amount; 0 = none.', 'flx-donate' ) ),
				'extra'          => array( 'String', __( 'Fields of the cards printed on the page, as a form body (meta[…], form_map[…]).', 'flx-donate' ) ),
			)
		),
		'output'      => flxb_gql_admin_fields(
			array(
				'message'    => array( 'String', __( 'Result for the notice; null when refused (see errors).', 'flx-donate' ) ),
				'databaseId' => array( 'Int', __( 'The saved type.', 'flx-donate' ) ),
				'warning'    => array( 'String', __( 'Forms that could not be linked; empty without one.', 'flx-donate' ) ),
			)
		),
		'resolve'     => static function ( $input ) {
			$in  = flxb_gql_args( is_array( $input ) ? $input : array() );
			$res = FLXB_Admin_Types_Service::save( max( 0, flxb_gql_int( $in['id'] ?? 0 ) ), flxb_gql_type_save_fields( $in ), flxb_gql_str( $in['extra'] ?? '' ) );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			return array(
				'message'    => $res['message'],
				'databaseId' => $res['id'],
				'warning'    => $res['warning'],
			);
		},
	)
);

flx_gql_mutation(
	'flxDonationTypeDelete',
	array(
		'description' => __( 'Deletes a donation type that has no gifts.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Types_Service::can_manage(),
		'input'       => flxb_gql_admin_fields( array( 'id' => array( 'Int!', __( 'Type id.', 'flx-donate' ) ) ) ),
		'output'      => flxb_gql_admin_fields( array( 'message' => array( 'String', __( 'Result for the notice; null when refused (see errors).', 'flx-donate' ) ) ) ),
		'resolve'     => static function ( $input ) {
			$in = flxb_gql_args( is_array( $input ) ? $input : array() );
			return FLXB_Admin_Types_Service::delete( flxb_gql_int( $in['id'] ?? 0 ) );
		},
	)
);
