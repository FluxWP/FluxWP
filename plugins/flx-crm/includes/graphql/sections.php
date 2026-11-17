<?php
/**
 * Person-card sections (FlxContact.sections). Other packages register with flxcrm_register_person_section().
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

flx_gql_enum(
	'FlxPersonPlacement',
	array(
		'description' => __( 'Where a package section sits on the person card.', 'flx-crm' ),
		'values'      => array(
			'ACTIONS'     => array( 'value' => 'actions', 'description' => __( 'Buttons in the header.', 'flx-crm' ) ),
			'MAIN_BEFORE' => array( 'value' => 'main_before', 'description' => __( 'Main column, above the timeline.', 'flx-crm' ) ),
			'MAIN'        => array( 'value' => 'main', 'description' => __( 'Main column, under the timeline.', 'flx-crm' ) ),
			'SIDE'        => array( 'value' => 'side', 'description' => __( 'Side column.', 'flx-crm' ) ),
		),
	)
);
flx_gql_enum(
	'FlxPersonBlockKind',
	array(
		'description' => __( 'How to draw one block of a person-card section.', 'flx-crm' ),
		'values'      => array(
			'STATS'   => array( 'value' => 'stats', 'description' => __( 'Numbers.', 'flx-crm' ) ),
			'LINKS'   => array( 'value' => 'links', 'description' => __( 'Links.', 'flx-crm' ) ),
			'LIST'    => array( 'value' => 'list', 'description' => __( 'A list.', 'flx-crm' ) ),
			'TABLE'   => array( 'value' => 'table', 'description' => __( 'A table.', 'flx-crm' ) ),
			'NOTE'    => array( 'value' => 'note', 'description' => __( 'A note.', 'flx-crm' ) ),
			'HTML'    => array( 'value' => 'html', 'description' => __( 'HTML, sanitized with wp_kses_post.', 'flx-crm' ) ),
			'ACTIONS' => array( 'value' => 'actions', 'description' => __( 'Buttons.', 'flx-crm' ) ),
		),
	)
);
flx_gql_type(
	'FlxPersonItem',
	array(
		'description' => __( 'One line of a stats, links, list or actions block.', 'flx-crm' ),
		'fields'      => array(
			'label'   => array( 'type' => 'String!', 'description' => __( 'Label.', 'flx-crm' ) ),
			'value'   => array( 'type' => 'String!', 'description' => __( 'Value.', 'flx-crm' ) ),
			'url'     => array( 'type' => 'String!', 'description' => __( 'Link. Empty when it is not a link.', 'flx-crm' ) ),
			'meta'    => array( 'type' => 'String!', 'description' => __( 'Secondary line.', 'flx-crm' ) ),
			'primary' => array( 'type' => 'Boolean!', 'description' => __( 'Drawn as the main action.', 'flx-crm' ) ),
		),
	)
);
flx_gql_type(
	'FlxPersonRow',
	array(
		'description' => __( 'One row of a table block.', 'flx-crm' ),
		'fields'      => array(
			'cells' => array( 'type' => '[String!]!', 'description' => __( 'Cells, in column order.', 'flx-crm' ) ),
			'url'   => array( 'type' => 'String!', 'description' => __( 'Row link.', 'flx-crm' ) ),
			'label' => array( 'type' => 'String!', 'description' => __( 'Accessible name of the row.', 'flx-crm' ) ),
			'meta'  => array( 'type' => 'String!', 'description' => __( 'Secondary line.', 'flx-crm' ) ),
		),
	)
);
flx_gql_type(
	'FlxPersonBlock',
	array(
		'description' => __( 'One block inside a person-card section.', 'flx-crm' ),
		'fields'      => array(
			'kind'    => array( 'type' => 'FlxPersonBlockKind!', 'description' => __( 'How to draw it.', 'flx-crm' ) ),
			'text'    => array( 'type' => 'String!', 'description' => __( 'Note or HTML.', 'flx-crm' ) ),
			'columns' => array( 'type' => '[String!]!', 'description' => __( 'Table headings.', 'flx-crm' ) ),
			'items'   => array( 'type' => '[FlxPersonItem!]!', 'description' => __( 'Lines of a stats, links, list or actions block.', 'flx-crm' ) ),
			'rows'    => array( 'type' => '[FlxPersonRow!]!', 'description' => __( 'Table rows.', 'flx-crm' ) ),
		),
	)
);
flx_gql_type(
	'FlxPersonSection',
	array(
		'description' => __( 'A block a package adds to the person card.', 'flx-crm' ),
		'fields'      => array(
			'id'        => array( 'type' => 'String!', 'description' => __( 'Stable id. legacy: plus the hook name is the unmigrated HTML.', 'flx-crm' ) ),
			'title'     => array( 'type' => 'String!', 'description' => __( 'Heading.', 'flx-crm' ) ),
			'placement' => array( 'type' => 'FlxPersonPlacement!', 'description' => __( 'Where it sits.', 'flx-crm' ) ),
			'package'   => array( 'type' => 'String!', 'description' => __( 'Package slug. Empty for a legacy hook.', 'flx-crm' ) ),
			'blocks'    => array( 'type' => '[FlxPersonBlock!]!', 'description' => __( 'What to draw.', 'flx-crm' ) ),
		),
	)
);

flx_gql_field(
	'FlxContact',
	'sections',
	array(
		'type'        => '[FlxPersonSection!]!',
		'cap'         => flxcrm_gql_view(),
		'description' => __( 'Sections other packages add. Hidden ones are left out.', 'flx-crm' ),
		'args'        => array(
			'context'   => array( 'type' => 'String', 'description' => __( 'Caller context. Empty is the CRM card.', 'flx-crm' ) ),
			'placement' => array( 'type' => 'FlxPersonPlacement', 'description' => __( 'Only this place. Empty is every place.', 'flx-crm' ) ),
		),
		'resolve'     => static function ( $source, array $args ): array {
			$contact = flxcrm_gql_c( $source );
			flx_gql_cache_tag( 'flx-crm:contact:' . $contact->id );
			return FLXCRM_Section_Service::for_contact( $contact, flx_gql_str( $args['placement'] ?? '' ), flx_gql_str( $args['context'] ?? '' ) );
		},
	)
);
