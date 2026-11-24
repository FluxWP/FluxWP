<?php
/**
 * Flux Donate admin GraphQL (part b8): Import from Excel of the wp-admin screen: the upload page, the preview
 * and the run. Loaded only on flx_gql_register. Resolvers call FLXB_Admin_Import_Service.
 *
 * Rights (fail closed, the classic ones): everything needs flxb_can_manage().
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

$flxb_import_types = array(
	'FlxDonationKpi'           => array(
		__( 'A stat tile.', 'flx-donate' ),
		array(
			'label'  => array( 'String!', __( 'Label.', 'flx-donate' ) ),
			'value'  => array( 'String!', __( 'Value, formatted.', 'flx-donate' ) ),
			'accent' => array( 'String!', __( 'Colour (#rrggbb); empty = the brand colour.', 'flx-donate' ) ),
		),
	),
	'FlxDonationImportEarlier' => array(
		__( 'An earlier import.', 'flx-donate' ),
		array(
			'id'    => array( 'String!', __( 'Import id.', 'flx-donate' ) ),
			'file'  => array( 'String!', __( 'File name.', 'flx-donate' ) ),
			'state' => array( 'String!', __( 'State.', 'flx-donate' ) ),
			'sub'   => array( 'String!', __( 'When, rows and how many were written.', 'flx-donate' ) ),
		),
	),
	'FlxDonationImportRef'     => array(
		__( 'Ready rows of one referral.', 'flx-donate' ),
		array(
			'name'   => array( 'String!', __( 'Referral.', 'flx-donate' ) ),
			'rows'   => array( 'String!', __( 'Records.', 'flx-donate' ) ),
			'units'  => array( 'String!', __( 'Units.', 'flx-donate' ) ),
			'amount' => array( 'String!', __( 'Amount.', 'flx-donate' ) ),
		),
	),
	'FlxDonationImportColumn'  => array(
		__( 'A column of the file and the field it fills.', 'flx-donate' ),
		array(
			'col'     => array( 'Int!', __( 'Column index.', 'flx-donate' ) ),
			'header'  => array( 'String!', __( 'Column name.', 'flx-donate' ) ),
			'field'   => array( 'String!', __( 'Field; empty = skipped.', 'flx-donate' ) ),
			'example' => array( 'String!', __( 'A value of the column.', 'flx-donate' ) ),
		),
	),
	'FlxDonationImportOptions' => array(
		__( 'Options of an import.', 'flx-donate' ),
		array(
			'type'      => array( 'String!', __( 'Donation type when the row has none.', 'flx-donate' ) ),
			'paid'      => array( 'Boolean!', __( 'Paid when the row does not say.', 'flx-donate' ) ),
			'keepDate'  => array( 'Boolean!', __( 'Keep the dates of the file.', 'flx-donate' ) ),
			'nodate'    => array( 'String!', __( 'Date when the row has none: above, target, day or today.', 'flx-donate' ) ),
			'nodateDay' => array( 'String!', __( 'The chosen day (Y-m-d).', 'flx-donate' ) ),
		),
	),
	'FlxDonationImportMatchRow' => array(
		__( 'A value of the file and what it is matched to.', 'flx-donate' ),
		array(
			'key'   => array( 'String!', __( 'Key of the value.', 'flx-donate' ) ),
			'raw'   => array( 'String!', __( 'Value in the file.', 'flx-donate' ) ),
			'count' => array( 'Int!', __( 'Rows with it.', 'flx-donate' ) ),
			'to'    => array( 'String!', __( 'Matched to.', 'flx-donate' ) ),
		),
	),
	'FlxDonationImportMatch'   => array(
		__( 'A group of matches.', 'flx-donate' ),
		array(
			'group'   => array( 'String!', __( 'type, partner, source, status, ref or paid.', 'flx-donate' ) ),
			'title'   => array( 'String!', __( 'Title.', 'flx-donate' ) ),
			'choices' => array( '[FlxDonationChoice!]!', __( 'What a value can be matched to.', 'flx-donate' ) ),
			'rows'    => array( '[FlxDonationImportMatchRow!]!', __( 'Values.', 'flx-donate' ) ),
		),
	),
	'FlxDonationImportPill'    => array(
		__( 'A check of a row.', 'flx-donate' ),
		array(
			'label' => array( 'String!', __( 'Text.', 'flx-donate' ) ),
			'tone'  => array( 'String!', __( 'Colour.', 'flx-donate' ) ),
		),
	),
	'FlxDonationImportRow'     => array(
		__( 'A row of the file as it will be written.', 'flx-donate' ),
		array(
			'n'        => array( 'Int!', __( 'Row number.', 'flx-donate' ) ),
			'who'      => array( 'String!', __( 'Donor.', 'flx-donate' ) ),
			'sub'      => array( 'String!', __( 'On behalf of.', 'flx-donate' ) ),
			'date'     => array( 'String!', __( 'Date.', 'flx-donate' ) ),
			'dated'    => array( 'Boolean!', __( 'The row has its own date.', 'flx-donate' ) ),
			'type'     => array( 'String!', __( 'Donation type.', 'flx-donate' ) ),
			'quantity' => array( 'Int!', __( 'Quantity.', 'flx-donate' ) ),
			'amount'   => array( 'String!', __( 'Amount.', 'flx-donate' ) ),
			'status'   => array( 'String!', __( 'Status.', 'flx-donate' ) ),
			'checks'   => array( '[FlxDonationImportPill!]!', __( 'Checks.', 'flx-donate' ) ),
		),
	),
	'FlxDonationImportPreview' => array(
		__( 'The preview of an import.', 'flx-donate' ),
		array(
			'stats'    => array( '[FlxDonationKpi!]!', __( 'Counts of the rows.', 'flx-donate' ) ),
			'summary'  => array( '[FlxDonationKpi!]!', __( 'What will be written.', 'flx-donate' ) ),
			'byRef'    => array( '[FlxDonationImportRef!]!', __( 'Ready rows per referral.', 'flx-donate' ) ),
			'confirm'  => array( 'String!', __( 'Question asked before starting.', 'flx-donate' ) ),
			'ready'    => array( 'Int!', __( 'Ready rows.', 'flx-donate' ) ),
			'runLabel' => array( 'String!', __( 'Start button.', 'flx-donate' ) ),
			'fields'   => array( '[FlxDonationChoice!]!', __( 'Fields a column can fill.', 'flx-donate' ) ),
			'columns'  => array( '[FlxDonationImportColumn!]!', __( 'Columns.', 'flx-donate' ) ),
			'types'    => array( '[FlxDonationChoice!]!', __( 'Donation types.', 'flx-donate' ) ),
			'options'  => array( 'FlxDonationImportOptions!', __( 'Options.', 'flx-donate' ) ),
			'matches'  => array( '[FlxDonationImportMatch!]!', __( 'Matches.', 'flx-donate' ) ),
			'rows'     => array( '[FlxDonationImportRow!]!', __( 'Rows (problems first, at most 200).', 'flx-donate' ) ),
			'more'     => array( 'String!', __( 'Note when not every row is shown.', 'flx-donate' ) ),
		),
	),
	'FlxDonationImportError'   => array(
		__( 'A row that could not be written.', 'flx-donate' ),
		array(
			'n'    => array( 'Int!', __( 'Row number.', 'flx-donate' ) ),
			'name' => array( 'String!', __( 'Name.', 'flx-donate' ) ),
			'msg'  => array( 'String!', __( 'Reason.', 'flx-donate' ) ),
		),
	),
	'FlxDonationImportRun'     => array(
		__( 'A running or finished import.', 'flx-donate' ),
		array(
			'done'      => array( 'Boolean!', __( 'Finished.', 'flx-donate' ) ),
			'pos'       => array( 'Int!', __( 'Rows done.', 'flx-donate' ) ),
			'total'     => array( 'Int!', __( 'Rows in all.', 'flx-donate' ) ),
			'percent'   => array( 'Int!', __( 'Progress (0–100).', 'flx-donate' ) ),
			'progress'  => array( 'String!', __( 'Progress as people read it.', 'flx-donate' ) ),
			'kpis'      => array( '[FlxDonationKpi!]!', __( 'Written, skipped, errors.', 'flx-donate' ) ),
			'errors'    => array( '[FlxDonationImportError!]!', __( 'Rows that could not be written.', 'flx-donate' ) ),
			'nextUrl'   => array( 'String!', __( 'Where the records are.', 'flx-donate' ) ),
			'nextLabel' => array( 'String!', __( 'Label of that page.', 'flx-donate' ) ),
		),
	),
	'FlxDonationImport'        => array(
		__( 'The import page.', 'flx-donate' ),
		array(
			'id'          => array( 'String!', __( 'Import id; empty on the upload page.', 'flx-donate' ) ),
			'state'       => array( 'String!', __( 'upload, preview, running or done.', 'flx-donate' ) ),
			'file'        => array( 'String!', __( 'File name.', 'flx-donate' ) ),
			'uploadUrl'   => array( 'String!', __( 'Form address of an upload.', 'flx-donate' ) ),
			'uploadNonce' => array( 'String!', __( 'Nonce of an upload.', 'flx-donate' ) ),
			'earlier'     => array( '[FlxDonationImportEarlier!]!', __( 'Earlier imports (upload page).', 'flx-donate' ) ),
			'preview'     => array( 'FlxDonationImportPreview', __( 'The preview; null otherwise.', 'flx-donate' ) ),
			'run'         => array( 'FlxDonationImportRun', __( 'The run; null otherwise.', 'flx-donate' ) ),
		),
	),
);
foreach ( $flxb_import_types as $flxb_name => $flxb_def ) {
	flx_gql_type(
		$flxb_name,
		array(
			'description' => $flxb_def[0],
			'fields'      => flxb_gql_admin_fields( $flxb_def[1] ),
		)
	);
}
unset( $flxb_import_types, $flxb_name, $flxb_def );

foreach (
	array(
		'FlxDonationImportMapInput'   => array(
			__( 'A column and the field it fills.', 'flx-donate' ),
			array(
				'col'   => array( 'Int!', __( 'Column index.', 'flx-donate' ) ),
				'field' => array( 'String!', __( 'Field; empty = skip.', 'flx-donate' ) ),
			),
		),
		'FlxDonationImportMatchInput' => array(
			__( 'A value of the file and what it is matched to.', 'flx-donate' ),
			array(
				'group' => array( 'String!', __( 'Group.', 'flx-donate' ) ),
				'key'   => array( 'String!', __( 'Key of the value.', 'flx-donate' ) ),
				'value' => array( 'String!', __( 'Matched to.', 'flx-donate' ) ),
			),
		),
	) as $flxb_name => $flxb_def
) {
	flx_gql_input(
		$flxb_name,
		array(
			'description' => $flxb_def[0],
			'fields'      => flxb_gql_admin_fields( $flxb_def[1] ),
		)
	);
}
unset( $flxb_name, $flxb_def );

flx_gql_query(
	'flxDonationImport',
	array(
		'type'        => 'FlxDonationImport',
		'description' => __( 'The import page; id empty = the upload page. Null when the import is gone.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Import_Service::can(),
		'args'        => array(
			'id' => array(
				'type'        => 'String!',
				'description' => __( 'Import id; empty = the upload page.', 'flx-donate' ),
			),
		),
		'resolve'     => static function ( $root, $args ): ?array {
			unset( $root );
			$a = flxb_gql_args( is_array( $args ) ? $args : array() );
			return FLXB_Admin_Import_Service::page( flxb_gql_str( $a['id'] ?? '' ) );
		},
	)
);

/**
 * An import mutation.
 *
 * @param array<string,array{0:string,1:string}> $input   Input fields.
 * @param callable                                $resolve Input → result.
 * @param array<string,array{0:string,1:string}> $output  Output fields besides message.
 * @return array<string,mixed>
 */
function flxb_gql_import_mutation( string $description, array $input, callable $resolve, array $output = array() ): array {
	return array(
		'description' => $description,
		'cap'         => static fn(): bool => FLXB_Admin_Import_Service::can(),
		'input'       => flxb_gql_admin_fields( $input ),
		'output'      => flxb_gql_admin_fields( array( 'message' => array( 'String', __( 'Result for the notice; null when refused (see errors).', 'flx-donate' ) ) ) + $output ),
		'resolve'     => static function ( $in ) use ( $resolve ) {
			return $resolve( flxb_gql_args( is_array( $in ) ? $in : array() ) );
		},
	);
}

flx_gql_mutation(
	'flxDonationImportSave',
	flxb_gql_import_mutation(
		__( 'Saves the columns, options and matches of an import; with run (and no column change) it starts.', 'flx-donate' ),
		array(
			'id'        => array( 'String!', __( 'Import id.', 'flx-donate' ) ),
			'map'       => array( '[FlxDonationImportMapInput!]!', __( 'Columns.', 'flx-donate' ) ),
			'matches'   => array( '[FlxDonationImportMatchInput!]!', __( 'Matches.', 'flx-donate' ) ),
			'type'      => array( 'String!', __( 'Donation type when the row has none.', 'flx-donate' ) ),
			'paid'      => array( 'Boolean!', __( 'Paid when the row does not say.', 'flx-donate' ) ),
			'keepDate'  => array( 'Boolean!', __( 'Keep the dates of the file.', 'flx-donate' ) ),
			'nodate'    => array( 'String!', __( 'Date when the row has none.', 'flx-donate' ) ),
			'nodateDay' => array( 'String!', __( 'The chosen day.', 'flx-donate' ) ),
			'run'       => array( 'Boolean!', __( 'Start the import.', 'flx-donate' ) ),
		),
		static function ( array $in ) {
			$map = array();
			foreach ( is_array( $in['map'] ?? null ) ? $in['map'] : array() as $m ) {
				$m                                     = is_array( $m ) ? $m : array();
				$map[ flxb_gql_int( $m['col'] ?? 0 ) ] = flxb_gql_str( $m['field'] ?? '' );
			}
			$res = array();
			foreach ( is_array( $in['matches'] ?? null ) ? $in['matches'] : array() as $m ) {
				$m     = is_array( $m ) ? $m : array();
				$res[] = array(
					'group' => flxb_gql_str( $m['group'] ?? '' ),
					'key'   => flxb_gql_str( $m['key'] ?? '' ),
					'value' => flxb_gql_str( $m['value'] ?? '' ),
				);
			}
			return FLXB_Admin_Import_Service::save(
				flxb_gql_str( $in['id'] ?? '' ),
				$map,
				$res,
				array(
					'type'      => flxb_gql_str( $in['type'] ?? '' ),
					'paid'      => ! empty( $in['paid'] ),
					'keepDate'  => ! empty( $in['keepDate'] ),
					'nodate'    => flxb_gql_str( $in['nodate'] ?? '' ),
					'nodateDay' => flxb_gql_str( $in['nodateDay'] ?? '' ),
				),
				! empty( $in['run'] )
			);
		},
		array( 'state' => array( 'String', __( 'State after saving: preview or running.', 'flx-donate' ) ) )
	)
);

flx_gql_mutation(
	'flxDonationImportDelete',
	flxb_gql_import_mutation(
		__( 'Discards an import file (records already written stay).', 'flx-donate' ),
		array( 'id' => array( 'String!', __( 'Import id.', 'flx-donate' ) ) ),
		static fn( array $in ) => FLXB_Admin_Import_Service::delete( flxb_gql_str( $in['id'] ?? '' ) )
	)
);

flx_gql_mutation(
	'flxDonationImportStep',
	flxb_gql_import_mutation(
		__( 'Writes the next rows of a running import.', 'flx-donate' ),
		array( 'id' => array( 'String!', __( 'Import id.', 'flx-donate' ) ) ),
		static fn( array $in ) => FLXB_Admin_Import_Service::step( flxb_gql_str( $in['id'] ?? '' ) ),
		array( 'done' => array( 'Boolean', __( 'The import has finished.', 'flx-donate' ) ) )
	)
);
