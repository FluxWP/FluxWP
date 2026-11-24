<?php
/**
 * Flux Donate admin GraphQL (part b9): Reports of the wp-admin screen: a report with its period, totals and
 * table, and the JSON editor. Loaded only on flx_gql_register. Resolvers call FLXB_Admin_Reports_Service.
 *
 * Rights (fail closed, the classic ones):
 * - flxDonationReport: flx_bagis_view.
 * - flxDonationReportSource and every mutation: flxb_can_manage().
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

$flxb_report_types = array(
	'FlxDonationReportKpi'    => array(
		__( 'A total of a report.', 'flx-donate' ),
		array(
			'label' => array( 'String!', __( 'Metric.', 'flx-donate' ) ),
			'value' => array( 'String!', __( 'Total, formatted.', 'flx-donate' ) ),
			'sub'   => array( 'String!', __( 'Other currencies; empty without.', 'flx-donate' ) ),
		),
	),
	'FlxDonationReportColumn' => array(
		__( 'A column of a report.', 'flx-donate' ),
		array(
			'label' => array( 'String!', __( 'Label.', 'flx-donate' ) ),
			'num'   => array( 'Boolean!', __( 'A metric (right aligned).', 'flx-donate' ) ),
		),
	),
	'FlxDonationReportRow'    => array(
		__( 'A row of a report.', 'flx-donate' ),
		array(
			'cells' => array( '[String!]!', __( 'Cells, formatted.', 'flx-donate' ) ),
			'bar'   => array( 'Float!', __( 'Bar of the first metric (0–100); 0 without a bar.', 'flx-donate' ) ),
		),
	),
	'FlxDonationReport'       => array(
		__( 'A report with its period, totals and table.', 'flx-donate' ),
		array(
			'reports'     => array( '[FlxDonationChoice!]!', __( 'All reports.', 'flx-donate' ) ),
			'canManage'   => array( 'Boolean!', __( 'The viewer can edit reports.', 'flx-donate' ) ),
			'periods'     => array( '[FlxDonationChoice!]!', __( 'Periods (custom = dates).', 'flx-donate' ) ),
			'id'          => array( 'String!', __( 'Report id; empty without reports.', 'flx-donate' ) ),
			'title'       => array( 'String!', __( 'Title.', 'flx-donate' ) ),
			'description' => array( 'String!', __( 'Description.', 'flx-donate' ) ),
			'edited'      => array( 'Boolean!', __( 'The site has its own copy.', 'flx-donate' ) ),
			'builtin'     => array( 'Boolean!', __( 'Shipped with the plugin.', 'flx-donate' ) ),
			'period'      => array( 'String!', __( 'Period key.', 'flx-donate' ) ),
			'from'        => array( 'String!', __( 'First day (Y-m-d); empty = open.', 'flx-donate' ) ),
			'to'          => array( 'String!', __( 'Last day (Y-m-d); empty = open.', 'flx-donate' ) ),
			'csvUrl'      => array( 'String!', __( 'CSV download.', 'flx-donate' ) ),
			'kpis'        => array( '[FlxDonationReportKpi!]!', __( 'Totals.', 'flx-donate' ) ),
			'columns'     => array( '[FlxDonationReportColumn!]!', __( 'Columns.', 'flx-donate' ) ),
			'rows'        => array( '[FlxDonationReportRow!]!', __( 'Rows.', 'flx-donate' ) ),
			'note'        => array( 'String!', __( 'Note when rows are cut.', 'flx-donate' ) ),
		),
	),
	'FlxDonationReportHelp'   => array(
		__( 'What a report source offers.', 'flx-donate' ),
		array(
			'source'  => array( 'String!', __( 'Source key.', 'flx-donate' ) ),
			'label'   => array( 'String!', __( 'Source.', 'flx-donate' ) ),
			'group'   => array( '[String!]!', __( 'Dimensions ("key — label").', 'flx-donate' ) ),
			'metrics' => array( '[String!]!', __( 'Metrics ("key — label").', 'flx-donate' ) ),
		),
	),
	'FlxDonationReportSource' => array(
		__( 'The JSON editor of a report.', 'flx-donate' ),
		array(
			'id'      => array( 'String!', __( 'Report id; empty = a new one.', 'flx-donate' ) ),
			'title'   => array( 'String!', __( 'Title.', 'flx-donate' ) ),
			'json'    => array( 'String!', __( 'Definition (JSON).', 'flx-donate' ) ),
			'edited'  => array( 'Boolean!', __( 'The site has its own copy.', 'flx-donate' ) ),
			'builtin' => array( 'Boolean!', __( 'Shipped with the plugin.', 'flx-donate' ) ),
			'help'    => array( '[FlxDonationReportHelp!]!', __( 'Sources with their dimensions and metrics.', 'flx-donate' ) ),
			'periods' => array( 'String!', __( 'Period keys.', 'flx-donate' ) ),
		),
	),
);
foreach ( $flxb_report_types as $flxb_name => $flxb_def ) {
	flx_gql_type(
		$flxb_name,
		array(
			'description' => $flxb_def[0],
			'fields'      => flxb_gql_admin_fields( $flxb_def[1] ),
		)
	);
}
unset( $flxb_report_types, $flxb_name, $flxb_def );

flx_gql_query(
	'flxDonationReport',
	array(
		'type'        => 'FlxDonationReport!',
		'description' => __( 'A report with its period, totals and table.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Reports_Service::can_view(),
		'args'        => array(
			'id'     => array(
				'type'        => 'String!',
				'description' => __( 'Report id; empty = the first one.', 'flx-donate' ),
			),
			'period' => array(
				'type'        => 'String!',
				'description' => __( 'Period key or custom; empty = the report\'s own.', 'flx-donate' ),
			),
			'from'   => array(
				'type'        => 'String!',
				'description' => __( 'First day of custom (Y-m-d).', 'flx-donate' ),
			),
			'to'     => array(
				'type'        => 'String!',
				'description' => __( 'Last day of custom (Y-m-d).', 'flx-donate' ),
			),
		),
		'resolve'     => static function ( $root, $args ): array {
			unset( $root );
			$a = flxb_gql_args( is_array( $args ) ? $args : array() );
			return FLXB_Admin_Reports_Service::report( flxb_gql_str( $a['id'] ?? '' ), flxb_gql_str( $a['period'] ?? '' ), flxb_gql_str( $a['from'] ?? '' ), flxb_gql_str( $a['to'] ?? '' ) );
		},
	)
);

flx_gql_query(
	'flxDonationReportSource',
	array(
		'type'        => 'FlxDonationReportSource!',
		'description' => __( 'The JSON editor of a report.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Reports_Service::can_manage(),
		'args'        => array(
			'id' => array(
				'type'        => 'String!',
				'description' => __( 'Report id; empty = a new one.', 'flx-donate' ),
			),
		),
		'resolve'     => static function ( $root, $args ): array {
			unset( $root );
			$a = flxb_gql_args( is_array( $args ) ? $args : array() );
			return FLXB_Admin_Reports_Service::source( flxb_gql_str( $a['id'] ?? '' ) );
		},
	)
);

flx_gql_mutation(
	'flxDonationReportSave',
	array(
		'description' => __( 'Saves a report from its JSON.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Reports_Service::can_manage(),
		'input'       => flxb_gql_admin_fields(
			array(
				'json' => array( 'String!', __( 'Definition (JSON).', 'flx-donate' ) ),
				'old'  => array( 'String!', __( 'Id it had; empty = a new one.', 'flx-donate' ) ),
			)
		),
		'output'      => flxb_gql_admin_fields(
			array(
				'message' => array( 'String', __( 'Result for the notice; null when refused (see errors).', 'flx-donate' ) ),
				'id'      => array( 'String', __( 'The saved report.', 'flx-donate' ) ),
			)
		),
		'resolve'     => static function ( $in ) {
			$in = flxb_gql_args( is_array( $in ) ? $in : array() );
			return FLXB_Admin_Reports_Service::save( flxb_gql_str( $in['json'] ?? '' ), flxb_gql_str( $in['old'] ?? '' ) );
		},
	)
);

flx_gql_mutation(
	'flxDonationReportDelete',
	array(
		'description' => __( 'Removes the site\'s copy of a report: a shipped one goes back to its original, a new one is gone.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Reports_Service::can_manage(),
		'input'       => flxb_gql_admin_fields( array( 'id' => array( 'String!', __( 'Report id.', 'flx-donate' ) ) ) ),
		'output'      => flxb_gql_admin_fields( array( 'message' => array( 'String', __( 'Result for the notice; null when refused (see errors).', 'flx-donate' ) ) ) ),
		'resolve'     => static function ( $in ) {
			$in = flxb_gql_args( is_array( $in ) ? $in : array() );
			return FLXB_Admin_Reports_Service::delete( flxb_gql_str( $in['id'] ?? '' ) );
		},
	)
);
