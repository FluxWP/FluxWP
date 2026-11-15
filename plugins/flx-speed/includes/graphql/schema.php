<?php
/**
 * Flux Speed GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a
 * GraphQL request. Resolvers call FLXP_Speed_Service; the Performance screen (wp-admin and the
 * FluxWP app) reads and writes only through these fields.
 *
 * Display data (server, database, system check) comes as labelled sections and rows already
 * translated by the server, the same text the classic tabs show.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

/**
 * One field definition.
 *
 * @param string $type        GraphQL type.
 * @param string $description Description (already translated).
 * @return array{type:string,description:string}
 */
function flxp_gql_f( string $type, string $description ): array {
	return array(
		'type'        => $type,
		'description' => $description,
	);
}

/**
 * Service result → value, or the WP_Error as a typed GraphQL error. Errors that carry no GraphQL code
 * (installer, loopback and similar messages written for the admin) are input problems the person can read.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxp_gql_ok( $r ) {
	if ( $r instanceof WP_Error ) {
		$data = $r->get_error_data();
		if ( ! is_array( $data ) || ! isset( $data['graphql_code'] ) ) {
			$field = is_array( $data ) && isset( $data['field'] ) && is_string( $data['field'] ) ? $data['field'] : null;
			$e     = new WP_Error(
				$r->get_error_code(),
				$r->get_error_message(),
				array(
					'graphql_code' => 'INVALID_INPUT',
					'field'        => $field,
				)
			);
			throw flx_gql_from_wp_error( $e );
		}
		throw flx_gql_from_wp_error( $r );
	}
	return $r;
}

/** Everyone who may open the Performance screen. */
function flxp_gql_view_cap(): callable {
	return flx_gql_any_cap( 'manage_options', 'flxp_view' );
}

/* ------------------------------------------------------------ Shared types */

flx_gql_enum(
	'FlxSpeedTone',
	array(
		'description' => __( 'How much attention a figure needs.', 'flx-speed' ),
		'values'      => array(
			'NONE'    => array( 'value' => 'NONE' ),
			'WARNING' => array( 'value' => 'WARNING' ),
			'ERROR'   => array( 'value' => 'ERROR' ),
		),
	)
);

flx_gql_enum(
	'FlxSpeedStatus',
	array(
		'description' => __( 'Result of a system check line.', 'flx-speed' ),
		'values'      => array(
			'OK'      => array( 'value' => 'ok' ),
			'WARNING' => array( 'value' => 'warning' ),
			'ERROR'   => array( 'value' => 'error' ),
			'INFO'    => array( 'value' => 'info' ),
		),
	)
);

flx_gql_type(
	'FlxSpeedWhen',
	array(
		'description' => __( 'A point in time for display.', 'flx-speed' ),
		'fields'      => array(
			'timestamp' => flxp_gql_f( 'Int!', __( 'Unix timestamp.', 'flx-speed' ) ),
			'short'     => flxp_gql_f( 'String!', __( 'Day, month and time with seconds, in the site time zone.', 'flx-speed' ) ),
			'date'      => flxp_gql_f( 'String!', __( 'Date for display, in the user\'s language.', 'flx-speed' ) ),
			'relative'  => flxp_gql_f( 'String!', __( 'Relative time for display ("5 minutes ago").', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedLabel',
	array(
		'description' => __( 'A key with its label.', 'flx-speed' ),
		'fields'      => array(
			'key'   => flxp_gql_f( 'String!', __( 'Stable key.', 'flx-speed' ) ),
			'label' => flxp_gql_f( 'String!', __( 'Name in the user\'s language.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedRow',
	array(
		'description' => __( 'A label and its value, both ready for display.', 'flx-speed' ),
		'fields'      => array(
			'label' => flxp_gql_f( 'String!', __( 'Row name.', 'flx-speed' ) ),
			'value' => flxp_gql_f( 'String!', __( 'Row value.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedSection',
	array(
		'description' => __( 'A titled box of label and value rows.', 'flx-speed' ),
		'fields'      => array(
			'key'   => flxp_gql_f( 'String!', __( 'Stable key of the box.', 'flx-speed' ) ),
			'title' => flxp_gql_f( 'String!', __( 'Box title.', 'flx-speed' ) ),
			'rows'  => flxp_gql_f( '[FlxSpeedRow!]!', __( 'Rows of the box.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedCard',
	array(
		'description' => __( 'A figure card.', 'flx-speed' ),
		'fields'      => array(
			'label' => flxp_gql_f( 'String!', __( 'Card name.', 'flx-speed' ) ),
			'value' => flxp_gql_f( 'String!', __( 'Figure for display.', 'flx-speed' ) ),
			'tone'  => flxp_gql_f( 'FlxSpeedTone!', __( 'How much attention the figure needs.', 'flx-speed' ) ),
		),
	)
);

/* ------------------------------------------------------------ Config, live and hints */

flx_gql_type(
	'FlxSpeedTab',
	array(
		'description' => __( 'A tab of the Performance screen the person may open.', 'flx-speed' ),
		'fields'      => array(
			'key'       => flxp_gql_f( 'String!', __( 'Tab key: summary, records, server, database, autoload, cron, system, pagespeed, cache or settings.', 'flx-speed' ) ),
			'label'     => flxp_gql_f( 'String!', __( 'Tab name.', 'flx-speed' ) ),
			'adminOnly' => flxp_gql_f( 'Boolean!', __( 'Only administrators open the tab.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedConfig',
	array(
		'description' => __( 'What the Performance screen needs before it loads any tab.', 'flx-speed' ),
		'fields'      => array(
			'canManage'  => flxp_gql_f( 'Boolean!', __( 'The person is an administrator (settings, cleanup and cache actions).', 'flx-speed' ) ),
			'recording'  => flxp_gql_f( 'Boolean!', __( 'Slow and failing requests are being recorded.', 'flx-speed' ) ),
			'classicUrl' => flxp_gql_f( 'String!', __( 'The classic Performance page.', 'flx-speed' ) ),
			'adminUrl'   => flxp_gql_f( 'String!', __( 'WordPress admin address.', 'flx-speed' ) ),
			'tabs'       => flxp_gql_f( '[FlxSpeedTab!]!', __( 'Tabs in display order.', 'flx-speed' ) ),
			'flags'      => flxp_gql_f( '[FlxSpeedLabel!]!', __( 'Request flags for the Records filter.', 'flx-speed' ) ),
			'types'      => flxp_gql_f( '[FlxSpeedLabel!]!', __( 'Request types for the Records filter.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedMeter',
	array(
		'description' => __( 'A live server card (CPU, memory, disk, PHP memory, uptime).', 'flx-speed' ),
		'fields'      => array(
			'key'   => flxp_gql_f( 'String!', __( 'Card key: cpu, ram, disk, php or up.', 'flx-speed' ) ),
			'label' => flxp_gql_f( 'String!', __( 'Card name.', 'flx-speed' ) ),
			'text'  => flxp_gql_f( 'String!', __( 'Figure for display.', 'flx-speed' ) ),
			'sub'   => flxp_gql_f( 'String!', __( 'Small line under the figure; may be empty.', 'flx-speed' ) ),
			'pct'   => flxp_gql_f( 'Int', __( 'Percentage for the bar; empty when the value has no scale.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedLive',
	array(
		'description' => __( 'Live server cards and the note that says whose values they are.', 'flx-speed' ),
		'fields'      => array(
			'meters' => flxp_gql_f( '[FlxSpeedMeter!]!', __( 'The cards.', 'flx-speed' ) ),
			'note'   => flxp_gql_f( 'String!', __( 'Explains whose values the cards show.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedHint',
	array(
		'description' => __( 'A performance check that needs attention.', 'flx-speed' ),
		'fields'      => array(
			'status'      => flxp_gql_f( 'FlxSpeedStatus!', __( 'Warning or error.', 'flx-speed' ) ),
			'label'       => flxp_gql_f( 'String!', __( 'What was checked.', 'flx-speed' ) ),
			'message'     => flxp_gql_f( 'String!', __( 'What was found and what to do.', 'flx-speed' ) ),
			'actionUrl'   => flxp_gql_f( 'String', __( 'Page that fixes it; empty when there is none.', 'flx-speed' ) ),
			'actionLabel' => flxp_gql_f( 'String', __( 'Link text for the page; empty when there is none.', 'flx-speed' ) ),
		),
	)
);

flx_gql_query(
	'flxSpeedConfig',
	array(
		'type'        => 'FlxSpeedConfig!',
		'description' => __( 'Tabs, filters and links of the Performance screen.', 'flx-speed' ),
		'cap'         => flxp_gql_view_cap(),
		'resolve'     => static fn(): array => FLXP_Speed_Service::config(),
	)
);

flx_gql_query(
	'flxSpeedLive',
	array(
		'type'        => 'FlxSpeedLive!',
		'description' => __( 'Live server cards. Light enough to poll every 15 seconds or more.', 'flx-speed' ),
		'cap'         => flxp_gql_view_cap(),
		'resolve'     => static fn(): array => FLXP_Speed_Service::live(),
	)
);

flx_gql_query(
	'flxSpeedHints',
	array(
		'type'        => '[FlxSpeedHint!]!',
		'description' => __( 'Performance checks that are in warning or error.', 'flx-speed' ),
		'cap'         => flxp_gql_view_cap(),
		'resolve'     => static fn(): array => FLXP_Speed_Service::hints(),
	)
);

/* ------------------------------------------------------------ Summary */

flx_gql_type(
	'FlxSpeedFlagCount',
	array(
		'description' => __( 'How many recorded requests carry a flag.', 'flx-speed' ),
		'fields'      => array(
			'key'   => flxp_gql_f( 'String!', __( 'Flag key.', 'flx-speed' ) ),
			'label' => flxp_gql_f( 'String!', __( 'Flag name.', 'flx-speed' ) ),
			'count' => flxp_gql_f( 'Int!', __( 'Requests with the flag.', 'flx-speed' ) ),
			'tone'  => flxp_gql_f( 'FlxSpeedTone!', __( 'Errors are ERROR, slowness is WARNING.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedTypeStat',
	array(
		'description' => __( 'Times of one request type.', 'flx-speed' ),
		'fields'      => array(
			'type'  => flxp_gql_f( 'String!', __( 'Type key (front, admin, ajax, rest, cron…).', 'flx-speed' ) ),
			'label' => flxp_gql_f( 'String!', __( 'Type name.', 'flx-speed' ) ),
			'count' => flxp_gql_f( 'Int!', __( 'Recorded requests.', 'flx-speed' ) ),
			'msAvg' => flxp_gql_f( 'Int!', __( 'Average time in milliseconds.', 'flx-speed' ) ),
			'msP50' => flxp_gql_f( 'Int!', __( 'Median time in milliseconds.', 'flx-speed' ) ),
			'msP95' => flxp_gql_f( 'Int!', __( '95th percentile time in milliseconds.', 'flx-speed' ) ),
			'msMax' => flxp_gql_f( 'Int!', __( 'Longest time in milliseconds.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedUrlStat',
	array(
		'description' => __( 'One of the slowest addresses.', 'flx-speed' ),
		'fields'      => array(
			'url'    => flxp_gql_f( 'String!', __( 'Address without the query string.', 'flx-speed' ) ),
			'count'  => flxp_gql_f( 'Int!', __( 'Recorded requests.', 'flx-speed' ) ),
			'msAvg'  => flxp_gql_f( 'Int!', __( 'Average time in milliseconds.', 'flx-speed' ) ),
			'msMax'  => flxp_gql_f( 'Int!', __( 'Longest time in milliseconds.', 'flx-speed' ) ),
			'errors' => flxp_gql_f( 'Int!', __( 'Errors seen.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedComponentStat',
	array(
		'description' => __( 'Query time of one plugin, theme or core.', 'flx-speed' ),
		'fields'      => array(
			'component'    => flxp_gql_f( 'String!', __( 'Plugin, theme or core name.', 'flx-speed' ) ),
			'requests'     => flxp_gql_f( 'Int!', __( 'Requests it ran queries in.', 'flx-speed' ) ),
			'queries'      => flxp_gql_f( 'Int!', __( 'Number of queries.', 'flx-speed' ) ),
			'msPerRequest' => flxp_gql_f( 'Float!', __( 'Query time per request in milliseconds.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedSlowQuery',
	array(
		'description' => __( 'A slow or failed query.', 'flx-speed' ),
		'fields'      => array(
			'sql'       => flxp_gql_f( 'String!', __( 'Normalized SQL.', 'flx-speed' ) ),
			'error'     => flxp_gql_f( 'String', __( 'Database error; empty when the query only was slow.', 'flx-speed' ) ),
			'component' => flxp_gql_f( 'String!', __( 'Plugin, theme or core that ran it.', 'flx-speed' ) ),
			'count'     => flxp_gql_f( 'Int!', __( 'Times seen.', 'flx-speed' ) ),
			'msAvg'     => flxp_gql_f( 'Float!', __( 'Average time in milliseconds.', 'flx-speed' ) ),
			'msMax'     => flxp_gql_f( 'Float!', __( 'Longest time in milliseconds.', 'flx-speed' ) ),
			'lastId'    => flxp_gql_f( 'Int!', __( 'Newest record that has it.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedPhpError',
	array(
		'description' => __( 'A PHP error seen in recorded requests.', 'flx-speed' ),
		'fields'      => array(
			'level'     => flxp_gql_f( 'String!', __( 'Level: warning, notice, deprecated or fatal.', 'flx-speed' ) ),
			'message'   => flxp_gql_f( 'String!', __( 'Error message.', 'flx-speed' ) ),
			'file'      => flxp_gql_f( 'String!', __( 'File and line.', 'flx-speed' ) ),
			'component' => flxp_gql_f( 'String!', __( 'Plugin, theme or core it comes from.', 'flx-speed' ) ),
			'requests'  => flxp_gql_f( 'Int!', __( 'Requests it was seen in.', 'flx-speed' ) ),
			'lastId'    => flxp_gql_f( 'Int!', __( 'Newest record that has it.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedHttpCall',
	array(
		'description' => __( 'An outgoing HTTP call seen in recorded requests.', 'flx-speed' ),
		'fields'      => array(
			'endpoint'  => flxp_gql_f( 'String!', __( 'Method, host and path.', 'flx-speed' ) ),
			'component' => flxp_gql_f( 'String!', __( 'Plugin, theme or core that made it.', 'flx-speed' ) ),
			'count'     => flxp_gql_f( 'Int!', __( 'Number of calls.', 'flx-speed' ) ),
			'failed'    => flxp_gql_f( 'Int!', __( 'Failed calls.', 'flx-speed' ) ),
			'msAvg'     => flxp_gql_f( 'Float!', __( 'Average time in milliseconds.', 'flx-speed' ) ),
			'lastError' => flxp_gql_f( 'String!', __( 'Last error; empty when none.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedSummary',
	array(
		'description' => __( 'Numbers of the recorded requests in a period.', 'flx-speed' ),
		'fields'      => array(
			'hours'       => flxp_gql_f( 'Int!', __( 'Period in hours.', 'flx-speed' ) ),
			'recorded'    => flxp_gql_f( 'Int!', __( 'Recorded requests.', 'flx-speed' ) ),
			'msAvg'       => flxp_gql_f( 'Int!', __( 'Average time in milliseconds.', 'flx-speed' ) ),
			'msP95'       => flxp_gql_f( 'Int!', __( '95th percentile time in milliseconds.', 'flx-speed' ) ),
			'msMax'       => flxp_gql_f( 'Int!', __( 'Longest time in milliseconds.', 'flx-speed' ) ),
			'flags'       => flxp_gql_f( '[FlxSpeedFlagCount!]!', __( 'Problem flags that occur at least once.', 'flx-speed' ) ),
			'byType'      => flxp_gql_f( '[FlxSpeedTypeStat!]!', __( 'Times by request type.', 'flx-speed' ) ),
			'slowestUrls' => flxp_gql_f( '[FlxSpeedUrlStat!]!', __( 'The ten slowest addresses.', 'flx-speed' ) ),
			'components'  => flxp_gql_f( '[FlxSpeedComponentStat!]!', __( 'Query time by component, heaviest first.', 'flx-speed' ) ),
			'slowQueries' => flxp_gql_f( '[FlxSpeedSlowQuery!]!', __( 'Slow and failed queries.', 'flx-speed' ) ),
			'phpErrors'   => flxp_gql_f( '[FlxSpeedPhpError!]!', __( 'PHP errors.', 'flx-speed' ) ),
			'httpCalls'   => flxp_gql_f( '[FlxSpeedHttpCall!]!', __( 'Outgoing HTTP calls.', 'flx-speed' ) ),
		),
	)
);

flx_gql_query(
	'flxSpeedSummary',
	array(
		'type'        => 'FlxSpeedSummary!',
		'description' => __( 'Summary of the recorded requests: times, flags, slow addresses, queries, errors, HTTP calls.', 'flx-speed' ),
		'cap'         => flxp_gql_view_cap(),
		'args'        => array(
			'hours' => array(
				'type'        => 'Int',
				'description' => __( 'Period in hours (1–720). Default 24.', 'flx-speed' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): array {
			$h = isset( $args['hours'] ) && is_numeric( $args['hours'] ) ? (int) $args['hours'] : 24;
			return FLXP_Speed_Service::summary( $h );
		},
	)
);

/* ------------------------------------------------------------ Records */

flx_gql_type(
	'FlxSpeedRecord',
	array(
		'description' => __( 'A recorded request.', 'flx-speed' ),
		'fields'      => array(
			'databaseId' => flxp_gql_f( 'Int!', __( 'Record number.', 'flx-speed' ) ),
			'at'         => flxp_gql_f( 'FlxSpeedWhen!', __( 'When the request happened.', 'flx-speed' ) ),
			'url'        => flxp_gql_f( 'String!', __( 'Requested address (secrets masked).', 'flx-speed' ) ),
			'method'     => flxp_gql_f( 'String!', __( 'HTTP method.', 'flx-speed' ) ),
			'type'       => flxp_gql_f( 'String!', __( 'Type key (front, admin, ajax, rest, cron…).', 'flx-speed' ) ),
			'typeLabel'  => flxp_gql_f( 'String!', __( 'Type name.', 'flx-speed' ) ),
			'status'     => flxp_gql_f( 'Int!', __( 'HTTP status.', 'flx-speed' ) ),
			'ms'         => flxp_gql_f( 'Int!', __( 'Time in milliseconds.', 'flx-speed' ) ),
			'memKb'      => flxp_gql_f( 'Int!', __( 'Peak memory in kilobytes.', 'flx-speed' ) ),
			'queries'    => flxp_gql_f( 'Int!', __( 'Database queries.', 'flx-speed' ) ),
			'queryMs'    => flxp_gql_f( 'Int!', __( 'Total query time in milliseconds.', 'flx-speed' ) ),
			'http'       => flxp_gql_f( 'Int!', __( 'Outgoing HTTP calls.', 'flx-speed' ) ),
			'errors'     => flxp_gql_f( 'Int!', __( 'PHP errors.', 'flx-speed' ) ),
			'flags'      => flxp_gql_f( '[FlxSpeedLabel!]!', __( 'Flags of the request.', 'flx-speed' ) ),
			'failed'     => flxp_gql_f( 'Boolean!', __( 'The request had a fatal error, a PHP error or a server error.', 'flx-speed' ) ),
		),
	)
);
flx_gql_connection( 'FlxSpeedRecord', __( 'A page of recorded requests.', 'flx-speed' ) );

flx_gql_type(
	'FlxSpeedPanel',
	array(
		'description' => __( 'What the shared request panel needs to draw a record.', 'flx-speed' ),
		'fields'      => array(
			'slowQueryMs' => flxp_gql_f( 'Int!', __( 'Slow query threshold in milliseconds.', 'flx-speed' ) ),
			'slowMs'      => flxp_gql_f( 'Int!', __( 'Slow request threshold in milliseconds.', 'flx-speed' ) ),
			'strings'     => flxp_gql_f( 'FlxJSON!', __( 'Texts of the panel in the user\'s language (open map).', 'flx-speed' ) ),
			'scriptUrl'   => flxp_gql_f( 'String!', __( 'Panel script address.', 'flx-speed' ) ),
			'styleUrl'    => flxp_gql_f( 'String!', __( 'Panel style address.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedRecordDetail',
	array(
		'description' => __( 'A recorded request with the full profile the panel draws.', 'flx-speed' ),
		'fields'      => array(
			'record' => flxp_gql_f( 'FlxSpeedRecord!', __( 'The request.', 'flx-speed' ) ),
			'data'   => flxp_gql_f( 'FlxJSON!', __( 'Stored profile (queries, errors, HTTP calls, assets…). An open structure only the request panel reads.', 'flx-speed' ) ),
			'labels' => flxp_gql_f( 'FlxJSON!', __( 'Readable names of the components in the profile (open map).', 'flx-speed' ) ),
			'panel'  => flxp_gql_f( 'FlxSpeedPanel!', __( 'Panel texts, thresholds and assets.', 'flx-speed' ) ),
		),
	)
);

flx_gql_query(
	'flxSpeedRecords',
	array(
		'type'        => 'FlxSpeedRecordConnection!',
		'description' => __( 'Recorded requests, newest or slowest first.', 'flx-speed' ),
		'cap'         => flxp_gql_view_cap(),
		'args'        => flx_gql_connection_args(
			array(
				'hours' => array(
					'type'        => 'Int',
					'description' => __( 'Only the last N hours; empty or 0 for all time.', 'flx-speed' ),
				),
				'flag'  => array(
					'type'        => 'String',
					'description' => __( 'Only requests with this flag.', 'flx-speed' ),
				),
				'type'  => array(
					'type'        => 'String',
					'description' => __( 'Only this request type.', 'flx-speed' ),
				),
				'q'     => array(
					'type'        => 'String',
					'description' => __( 'Only addresses that contain this text.', 'flx-speed' ),
				),
				'slow'  => array(
					'type'        => 'Boolean',
					'description' => __( 'Slowest first instead of newest first.', 'flx-speed' ),
				),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			$filter = array(
				'hours' => isset( $args['hours'] ) && is_numeric( $args['hours'] ) ? (int) $args['hours'] : 0,
				'flag'  => isset( $args['flag'] ) && is_string( $args['flag'] ) ? $args['flag'] : '',
				'type'  => isset( $args['type'] ) && is_string( $args['type'] ) ? $args['type'] : '',
				'q'     => isset( $args['q'] ) && is_string( $args['q'] ) ? $args['q'] : '',
				'order' => ! empty( $args['slow'] ) ? 'slow' : 'new',
			);
			return flx_gql_paginate( $args, static fn( int $offset, int $limit ): array => FLXP_Speed_Service::records( $filter, $offset, $limit ) );
		},
	)
);

flx_gql_query(
	'flxSpeedRecord',
	array(
		'type'        => 'FlxSpeedRecordDetail',
		'description' => __( 'One recorded request with its full profile; empty when the record does not exist.', 'flx-speed' ),
		'cap'         => flxp_gql_view_cap(),
		'args'        => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Record number.', 'flx-speed' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): ?array {
			$id = isset( $args['id'] ) && is_numeric( $args['id'] ) ? (int) $args['id'] : 0;
			$r  = FLXP_Speed_Service::record( $id );
			if ( null === $r ) {
				return null;
			}
			$data   = $r['data'];
			$labels = $r['labels'];
			unset( $r['data'], $r['labels'] );
			return array(
				'record' => $r,
				'data'   => $data,
				'labels' => $labels,
				'panel'  => FLXP_Speed_Service::panel_assets(),
			);
		},
	)
);

flx_gql_mutation(
	'flxSpeedRecordsClear',
	array(
		'description' => __( 'Deletes every recorded request.', 'flx-speed' ),
		'cap'         => 'manage_options',
		'input'       => array(),
		'output'      => array(
			'cleared' => flxp_gql_f( 'Boolean!', __( 'The records were deleted.', 'flx-speed' ) ),
		),
		'resolve'     => static function (): array {
			FLXP_Speed_Service::records_clear();
			return array( 'cleared' => true );
		},
	)
);

flx_gql_mutation(
	'flxSpeedProfile',
	array(
		'description' => __( 'Opens a page of this site as a visitor (bypassing the cache) and records it in full detail.', 'flx-speed' ),
		'cap'         => flxp_gql_view_cap(),
		'input'       => array(
			'url' => array(
				'type'        => 'String',
				'description' => __( 'Path or address on this site, e.g. /donate/. Empty profiles the home page.', 'flx-speed' ),
			),
		),
		'output'      => array(
			'recordId' => flxp_gql_f( 'Int', __( 'The new record; empty when the request failed.', 'flx-speed' ) ),
			'status'   => flxp_gql_f( 'Int', __( 'HTTP status the page answered with.', 'flx-speed' ) ),
			'url'      => flxp_gql_f( 'String', __( 'The profiled address.', 'flx-speed' ) ),
		),
		'resolve'     => static function ( $input ): array {
			$in  = is_array( $input ) ? $input : array();
			$url = isset( $in['url'] ) && is_string( $in['url'] ) ? sanitize_text_field( $in['url'] ) : '';
			$r   = flxp_gql_ok( FLXP_Speed_Service::profile( $url ) );
			return array(
				'recordId' => $r['id'],
				'status'   => $r['status'],
				'url'      => $r['url'],
			);
		},
	)
);

/* ------------------------------------------------------------ Server */

flx_gql_query(
	'flxSpeedServer',
	array(
		'type'        => '[FlxSpeedSection!]!',
		'description' => __( 'Server, PHP, OPcache, cache and WordPress details as labelled boxes.', 'flx-speed' ),
		'cap'         => flxp_gql_view_cap(),
		'resolve'     => static fn(): array => FLXP_Speed_Service::server_sections(),
	)
);

/* ------------------------------------------------------------ Database */

flx_gql_type(
	'FlxSpeedCleanupJob',
	array(
		'description' => __( 'A safe cleanup job.', 'flx-speed' ),
		'fields'      => array(
			'key'      => flxp_gql_f( 'String!', __( 'Job key.', 'flx-speed' ) ),
			'label'    => flxp_gql_f( 'String!', __( 'Job name.', 'flx-speed' ) ),
			'count'    => flxp_gql_f( 'Int!', __( 'Items it would clean.', 'flx-speed' ) ),
			'runnable' => flxp_gql_f( 'Boolean!', __( 'The person may run it and there is something to clean.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedCount',
	array(
		'description' => __( 'A label and a number.', 'flx-speed' ),
		'fields'      => array(
			'label' => flxp_gql_f( 'String!', __( 'Name.', 'flx-speed' ) ),
			'count' => flxp_gql_f( 'Int!', __( 'Number.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedTable',
	array(
		'description' => __( 'A database table.', 'flx-speed' ),
		'fields'      => array(
			'name'    => flxp_gql_f( 'String!', __( 'Table name.', 'flx-speed' ) ),
			'engine'  => flxp_gql_f( 'String!', __( 'Storage engine.', 'flx-speed' ) ),
			'rows'    => flxp_gql_f( 'Int!', __( 'Rows (approximate).', 'flx-speed' ) ),
			'data'    => flxp_gql_f( 'Float!', __( 'Data size in bytes.', 'flx-speed' ) ),
			'index'   => flxp_gql_f( 'Float!', __( 'Index size in bytes.', 'flx-speed' ) ),
			'free'    => flxp_gql_f( 'Float!', __( 'Free space in bytes.', 'flx-speed' ) ),
			'collate' => flxp_gql_f( 'String!', __( 'Collation.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedDatabase',
	array(
		'description' => __( 'Database size, server settings, status, cleanup jobs and tables.', 'flx-speed' ),
		'fields'      => array(
			'cards'    => flxp_gql_f( '[FlxSpeedCard!]!', __( 'Size cards.', 'flx-speed' ) ),
			'sections' => flxp_gql_f( '[FlxSpeedSection!]!', __( 'Server and status boxes.', 'flx-speed' ) ),
			'jobs'     => flxp_gql_f( '[FlxSpeedCleanupJob!]!', __( 'Safe cleanup jobs.', 'flx-speed' ) ),
			'info'     => flxp_gql_f( '[FlxSpeedCount!]!', __( 'Counts that are shown but never cleaned here.', 'flx-speed' ) ),
			'tables'   => flxp_gql_f( '[FlxSpeedTable!]!', __( 'Tables, largest first.', 'flx-speed' ) ),
		),
	)
);

flx_gql_query(
	'flxSpeedDatabase',
	array(
		'type'        => 'FlxSpeedDatabase!',
		'description' => __( 'Database details and the cleanup jobs.', 'flx-speed' ),
		'cap'         => flxp_gql_view_cap(),
		'resolve'     => static fn(): array => FLXP_Speed_Service::database(),
	)
);

flx_gql_mutation(
	'flxSpeedCleanup',
	array(
		'description' => __( 'Runs one safe cleanup job. Posts, pages and revisions are never deleted.', 'flx-speed' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'job' => array(
				'type'        => 'String!',
				'description' => __( 'Job key from flxSpeedDatabase.jobs.', 'flx-speed' ),
			),
		),
		'output'      => array(
			'done' => flxp_gql_f( 'Int', __( 'Items cleaned.', 'flx-speed' ) ),
		),
		'resolve'     => static function ( $input ): array {
			$in  = is_array( $input ) ? $input : array();
			$job = isset( $in['job'] ) && is_string( $in['job'] ) ? $in['job'] : '';
			return array( 'done' => flxp_gql_ok( FLXP_Speed_Service::cleanup( $job ) ) );
		},
	)
);

/* ------------------------------------------------------------ Autoload and cron */

flx_gql_type(
	'FlxSpeedAutoloadOption',
	array(
		'description' => __( 'One of the largest autoloaded options.', 'flx-speed' ),
		'fields'      => array(
			'name'  => flxp_gql_f( 'String!', __( 'Option name.', 'flx-speed' ) ),
			'owner' => flxp_gql_f( 'String', __( 'Plugin or theme the option belongs to; empty when unknown.', 'flx-speed' ) ),
			'kb'    => flxp_gql_f( 'Float!', __( 'Size in kilobytes.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedAutoload',
	array(
		'description' => __( 'Options WordPress loads from the database on every request.', 'flx-speed' ),
		'fields'      => array(
			'count'   => flxp_gql_f( 'Int!', __( 'Autoloaded options.', 'flx-speed' ) ),
			'totalKb' => flxp_gql_f( 'Float!', __( 'Total size in kilobytes.', 'flx-speed' ) ),
			'tone'    => flxp_gql_f( 'FlxSpeedTone!', __( 'WARNING over 800 KB, ERROR over 1600 KB.', 'flx-speed' ) ),
			'largest' => flxp_gql_f( '[FlxSpeedAutoloadOption!]!', __( 'The largest options.', 'flx-speed' ) ),
		),
	)
);

flx_gql_query(
	'flxSpeedAutoload',
	array(
		'type'        => 'FlxSpeedAutoload!',
		'description' => __( 'Autoloaded options: total and the largest.', 'flx-speed' ),
		'cap'         => flxp_gql_view_cap(),
		'resolve'     => static fn(): array => FLXP_Speed_Service::autoload(),
	)
);

flx_gql_type(
	'FlxSpeedCronTiming',
	array(
		'description' => __( 'How long one scheduled task takes.', 'flx-speed' ),
		'fields'      => array(
			'hook'      => flxp_gql_f( 'String!', __( 'Hook name.', 'flx-speed' ) ),
			'component' => flxp_gql_f( 'String!', __( 'Plugin, theme or core that owns the task.', 'flx-speed' ) ),
			'runs'      => flxp_gql_f( 'Int!', __( 'Times measured.', 'flx-speed' ) ),
			'avgMs'     => flxp_gql_f( 'Int!', __( 'Average time in milliseconds.', 'flx-speed' ) ),
			'maxMs'     => flxp_gql_f( 'Int!', __( 'Longest time in milliseconds.', 'flx-speed' ) ),
			'lastMs'    => flxp_gql_f( 'Int!', __( 'Last time in milliseconds.', 'flx-speed' ) ),
			'lastAt'    => flxp_gql_f( 'FlxSpeedWhen', __( 'Last run; empty when never.', 'flx-speed' ) ),
			'cut'       => flxp_gql_f( 'Boolean!', __( 'The task hit the time limit and stopped halfway.', 'flx-speed' ) ),
			'slow'      => flxp_gql_f( 'Boolean!', __( 'The average is 5 seconds or more.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedCronEvent',
	array(
		'description' => __( 'A scheduled event.', 'flx-speed' ),
		'fields'      => array(
			'hook'     => flxp_gql_f( 'String!', __( 'Hook name.', 'flx-speed' ) ),
			'next'     => flxp_gql_f( 'FlxSpeedWhen!', __( 'Next run.', 'flx-speed' ) ),
			'schedule' => flxp_gql_f( 'String!', __( 'Recurrence name; empty for a single run.', 'flx-speed' ) ),
			'args'     => flxp_gql_f( 'Int!', __( 'Number of arguments.', 'flx-speed' ) ),
			'overdue'  => flxp_gql_f( 'Boolean!', __( 'More than 10 minutes late.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedCron',
	array(
		'description' => __( 'Scheduled tasks (WP-Cron).', 'flx-speed' ),
		'fields'      => array(
			'disabled' => flxp_gql_f( 'Boolean!', __( 'DISABLE_WP_CRON is on: events run only when a server cron job calls wp-cron.php.', 'flx-speed' ) ),
			'events'   => flxp_gql_f( 'Int!', __( 'Scheduled events.', 'flx-speed' ) ),
			'overdue'  => flxp_gql_f( 'Int!', __( 'Events more than 10 minutes late.', 'flx-speed' ) ),
			'lastSeen' => flxp_gql_f( 'FlxSpeedWhen', __( 'Last time cron was seen running; empty when never.', 'flx-speed' ) ),
			'lastWarn' => flxp_gql_f( 'Boolean!', __( 'Cron was not seen in the last hour.', 'flx-speed' ) ),
			'timings'  => flxp_gql_f( '[FlxSpeedCronTiming!]!', __( 'Task durations, heaviest first.', 'flx-speed' ) ),
			'items'    => flxp_gql_f( '[FlxSpeedCronEvent!]!', __( 'Scheduled events.', 'flx-speed' ) ),
		),
	)
);

flx_gql_query(
	'flxSpeedCron',
	array(
		'type'        => 'FlxSpeedCron!',
		'description' => __( 'Scheduled events and measured task durations.', 'flx-speed' ),
		'cap'         => flxp_gql_view_cap(),
		'resolve'     => static fn(): array => FLXP_Speed_Service::cron(),
	)
);

/* ------------------------------------------------------------ System check */

flx_gql_type(
	'FlxSpeedCheck',
	array(
		'description' => __( 'A line of the shared system check.', 'flx-speed' ),
		'fields'      => array(
			'id'          => flxp_gql_f( 'String!', __( 'Check id, e.g. flxp.cron.', 'flx-speed' ) ),
			'source'      => flxp_gql_f( 'String!', __( 'Plugin or theme that registered the check.', 'flx-speed' ) ),
			'group'       => flxp_gql_f( 'String!', __( 'Group key.', 'flx-speed' ) ),
			'groupLabel'  => flxp_gql_f( 'String!', __( 'Group name.', 'flx-speed' ) ),
			'label'       => flxp_gql_f( 'String!', __( 'What is checked.', 'flx-speed' ) ),
			'status'      => flxp_gql_f( 'FlxSpeedStatus!', __( 'Result.', 'flx-speed' ) ),
			'message'     => flxp_gql_f( 'String!', __( 'What was found and what to do.', 'flx-speed' ) ),
			'actionUrl'   => flxp_gql_f( 'String', __( 'Page that fixes it; empty when there is none.', 'flx-speed' ) ),
			'actionLabel' => flxp_gql_f( 'String', __( 'Link text; empty when there is none.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedCheckSummary',
	array(
		'description' => __( 'Counts of the system check results.', 'flx-speed' ),
		'fields'      => array(
			'ok'      => flxp_gql_f( 'Int!', __( 'Checks that are fine.', 'flx-speed' ) ),
			'warning' => flxp_gql_f( 'Int!', __( 'Warnings.', 'flx-speed' ) ),
			'error'   => flxp_gql_f( 'Int!', __( 'Errors.', 'flx-speed' ) ),
			'info'    => flxp_gql_f( 'Int!', __( 'Information lines.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedFix',
	array(
		'description' => __( 'A fix applied by AI or MCP.', 'flx-speed' ),
		'fields'      => array(
			'at'      => flxp_gql_f( 'FlxSpeedWhen!', __( 'When it ran.', 'flx-speed' ) ),
			'user'    => flxp_gql_f( 'String!', __( 'Login of the user it ran as.', 'flx-speed' ) ),
			'label'   => flxp_gql_f( 'String!', __( 'Fix name.', 'flx-speed' ) ),
			'value'   => flxp_gql_f( 'String!', __( 'Value that was set; empty when none.', 'flx-speed' ) ),
			'before'  => flxp_gql_f( 'String!', __( 'State before.', 'flx-speed' ) ),
			'after'   => flxp_gql_f( 'String!', __( 'State after.', 'flx-speed' ) ),
			'ok'      => flxp_gql_f( 'Boolean!', __( 'The fix worked.', 'flx-speed' ) ),
			'message' => flxp_gql_f( 'String!', __( 'Result text.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedSystem',
	array(
		'description' => __( 'The shared system check and the recent AI fixes.', 'flx-speed' ),
		'fields'      => array(
			'checks'   => flxp_gql_f( '[FlxSpeedCheck!]!', __( 'Every check, worst first.', 'flx-speed' ) ),
			'summary'  => flxp_gql_f( 'FlxSpeedCheckSummary!', __( 'Counts by result.', 'flx-speed' ) ),
			'fixes'    => flxp_gql_f( '[FlxSpeedFix!]!', __( 'The last 20 fixes.', 'flx-speed' ) ),
			'hasFixes' => flxp_gql_f( 'Boolean!', __( 'Fixes are logged (Flux SEO is active).', 'flx-speed' ) ),
		),
	)
);

flx_gql_query(
	'flxSpeedSystem',
	array(
		'type'        => 'FlxSpeedSystem!',
		'description' => __( 'The shared system check of every FluxWP plugin and the recent AI fixes.', 'flx-speed' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): array => FLXP_Speed_Service::system(),
	)
);

/* ------------------------------------------------------------ Settings */

flx_gql_enum(
	'FlxSpeedLoginBlocked',
	array(
		'description' => __( 'What visitors of the old login addresses see.', 'flx-speed' ),
		'values'      => array(
			'NOT_FOUND' => array( 'value' => 'NOT_FOUND' ),
			'HOME'      => array( 'value' => 'HOME' ),
		),
	)
);

flx_gql_type(
	'FlxSpeedSettings',
	array(
		'description' => __( 'Flux Speed settings and the custom admin login address.', 'flx-speed' ),
		'fields'      => array(
			'panel'           => flxp_gql_f( 'Boolean!', __( 'Show a summary in the admin bar and a bottom panel to authorized users.', 'flx-speed' ) ),
			'panelFront'      => flxp_gql_f( 'Boolean!', __( 'Show the panel on the front end too.', 'flx-speed' ) ),
			'caps'            => flxp_gql_f( 'Boolean!', __( 'Collect capability checks.', 'flx-speed' ) ),
			'record'          => flxp_gql_f( 'Boolean!', __( 'Record slow and failing requests.', 'flx-speed' ) ),
			'recordAdmins'    => flxp_gql_f( 'Boolean!', __( 'Also record the admin\'s own requests while the panel is on.', 'flx-speed' ) ),
			'sample'          => flxp_gql_f( 'Int!', __( 'Share of requests collected in detail, 0–100 percent.', 'flx-speed' ) ),
			'slowMs'          => flxp_gql_f( 'Int!', __( 'Slow request threshold in milliseconds.', 'flx-speed' ) ),
			'slowQueryMs'     => flxp_gql_f( 'Int!', __( 'Slow query threshold in milliseconds.', 'flx-speed' ) ),
			'keepDays'        => flxp_gql_f( 'Int!', __( 'Days records are kept.', 'flx-speed' ) ),
			'maxRows'         => flxp_gql_f( 'Int!', __( 'Most records kept.', 'flx-speed' ) ),
			'diskQuotaGb'     => flxp_gql_f( 'Float!', __( 'Disk limit of the hosting plan in GB; 0 when unknown.', 'flx-speed' ) ),
			'loginSlug'       => flxp_gql_f( 'String!', __( 'Custom login address (the part after the site address); empty for wp-login.php.', 'flx-speed' ) ),
			'loginBlocked'    => flxp_gql_f( 'FlxSpeedLoginBlocked!', __( 'What visitors of wp-login.php see.', 'flx-speed' ) ),
			'loginSlugLocked' => flxp_gql_f( 'Boolean!', __( 'The address comes from FLXP_LOGIN_SLUG in wp-config.php and cannot be changed here.', 'flx-speed' ) ),
			'loginDisabled'   => flxp_gql_f( 'Boolean!', __( 'FLXP_LOGIN_DISABLE is defined: the custom address is off.', 'flx-speed' ) ),
			'loginActive'     => flxp_gql_f( 'Boolean!', __( 'The custom login address is in use.', 'flx-speed' ) ),
			'loginUrl'        => flxp_gql_f( 'String!', __( 'The login address in use; empty when none.', 'flx-speed' ) ),
			'loginPrefix'     => flxp_gql_f( 'String!', __( 'Site address that comes before the login slug.', 'flx-speed' ) ),
			'mcpActive'       => flxp_gql_f( 'Boolean!', __( 'Flux AI & MCP is active, so the tools are registered.', 'flx-speed' ) ),
			'mcpTools'        => flxp_gql_f( 'String!', __( 'Names of the read-only AI tools.', 'flx-speed' ) ),
		),
	)
);

flx_gql_query(
	'flxSpeedSettings',
	array(
		'type'        => 'FlxSpeedSettings!',
		'description' => __( 'Flux Speed settings and the custom admin login address.', 'flx-speed' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): array => FLXP_Speed_Service::settings(),
	)
);

flx_gql_mutation(
	'flxSpeedSettingsSave',
	array(
		'description' => __( 'Saves Flux Speed settings. Fields you leave out stay as they are.', 'flx-speed' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'panel'        => flxp_gql_f( 'Boolean', __( 'Show a summary in the admin bar and a bottom panel to authorized users.', 'flx-speed' ) ),
			'panelFront'   => flxp_gql_f( 'Boolean', __( 'Show the panel on the front end too.', 'flx-speed' ) ),
			'caps'         => flxp_gql_f( 'Boolean', __( 'Collect capability checks.', 'flx-speed' ) ),
			'record'       => flxp_gql_f( 'Boolean', __( 'Record slow and failing requests.', 'flx-speed' ) ),
			'recordAdmins' => flxp_gql_f( 'Boolean', __( 'Also record the admin\'s own requests while the panel is on.', 'flx-speed' ) ),
			'sample'       => flxp_gql_f( 'Int', __( 'Share of requests collected in detail, 0–100 percent.', 'flx-speed' ) ),
			'slowMs'       => flxp_gql_f( 'Int', __( 'Slow request threshold in milliseconds (100–60000).', 'flx-speed' ) ),
			'slowQueryMs'  => flxp_gql_f( 'Int', __( 'Slow query threshold in milliseconds (1–10000).', 'flx-speed' ) ),
			'keepDays'     => flxp_gql_f( 'Int', __( 'Days records are kept (1–90).', 'flx-speed' ) ),
			'maxRows'      => flxp_gql_f( 'Int', __( 'Most records kept (100–50000).', 'flx-speed' ) ),
			'diskQuotaGb'  => flxp_gql_f( 'Float', __( 'Disk limit of the hosting plan in GB; 0 when unknown.', 'flx-speed' ) ),
			'loginSlug'    => flxp_gql_f( 'String', __( 'Custom login address: 3–40 lowercase letters, digits, hyphen or underscore. Empty goes back to wp-login.php.', 'flx-speed' ) ),
			'loginBlocked' => flxp_gql_f( 'FlxSpeedLoginBlocked', __( 'What visitors of wp-login.php see.', 'flx-speed' ) ),
		),
		'output'      => array(
			'settings'     => flxp_gql_f( 'FlxSpeedSettings', __( 'The settings after saving.', 'flx-speed' ) ),
			'loginChanged' => flxp_gql_f( 'Boolean', __( 'The login address changed.', 'flx-speed' ) ),
			'loginRemoved' => flxp_gql_f( 'Boolean', __( 'The custom login address was turned off.', 'flx-speed' ) ),
		),
		'resolve'     => static function ( $input ): array {
			$in  = is_array( $input ) ? $input : array();
			$cur = flx_gql_arr( flxp_settings() );
			$map = array(
				'panel'        => 'panel',
				'panelFront'   => 'panel_front',
				'caps'         => 'caps',
				'record'       => 'record',
				'recordAdmins' => 'record_admins',
				'sample'       => 'sample',
				'slowMs'       => 'slow_ms',
				'slowQueryMs'  => 'slow_query_ms',
				'keepDays'     => 'keep_days',
				'maxRows'      => 'max_rows',
				'diskQuotaGb'  => 'disk_quota_gb',
			);
			$raw = $cur;
			foreach ( $map as $gql => $key ) {
				if ( array_key_exists( $gql, $in ) && null !== $in[ $gql ] ) {
					$raw[ $key ] = is_bool( $in[ $gql ] ) ? ( $in[ $gql ] ? 1 : 0 ) : $in[ $gql ];
				}
			}
			if ( array_key_exists( 'loginBlocked', $in ) && is_string( $in['loginBlocked'] ) ) {
				$raw['login_blocked'] = 'HOME' === $in['loginBlocked'] || 'home' === $in['loginBlocked'] ? 'home' : '404';
			}
			if ( array_key_exists( 'loginSlug', $in ) && is_string( $in['loginSlug'] ) ) {
				$raw['login_slug'] = $in['loginSlug'];
			} else {
				unset( $raw['login_slug'] );
			}
			$r = flxp_gql_ok( FLXP_Speed_Service::settings_save( $raw ) );
			return array(
				'settings'     => FLXP_Speed_Service::settings(),
				'loginChanged' => $r['loginChanged'],
				'loginRemoved' => $r['loginRemoved'],
			);
		},
	)
);

/* ------------------------------------------------------------ PageSpeed */

flx_gql_enum(
	'FlxSpeedCssScope',
	array(
		'description' => __( 'Which CSS files the unused CSS trimming looks at.', 'flx-speed' ),
		'values'      => array(
			'THEME' => array( 'value' => 'THEME' ),
			'LOCAL' => array( 'value' => 'LOCAL' ),
		),
	)
);

flx_gql_type(
	'FlxSpeedToggle',
	array(
		'description' => __( 'A PageSpeed switch.', 'flx-speed' ),
		'fields'      => array(
			'key'    => flxp_gql_f( 'String!', __( 'Setting name: ps_viewport, ps_emoji, ps_css, ps_min_css, ps_min_js, ps_defer, ps_defer_jquery, ps_fonts or ps_preload.', 'flx-speed' ) ),
			'on'     => flxp_gql_f( 'Boolean!', __( 'The switch is on.', 'flx-speed' ) ),
			'forced' => flxp_gql_f( 'Boolean!', __( 'The theme or a preset overrides this feature in code, so the switch may not take effect.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedAnimation',
	array(
		'description' => __( 'An animation that does not run on the GPU.', 'flx-speed' ),
		'fields'      => array(
			'name'    => flxp_gql_f( 'String!', __( '@keyframes name.', 'flx-speed' ) ),
			'props'   => flxp_gql_f( '[String!]!', __( 'Animated properties other than transform and opacity.', 'flx-speed' ) ),
			'file'    => flxp_gql_f( 'String!', __( 'CSS file.', 'flx-speed' ) ),
			'reduced' => flxp_gql_f( 'Boolean!', __( 'The file has a prefers-reduced-motion rule.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedPageSpeed',
	array(
		'description' => __( 'PageSpeed switches, their cache and the animation report.', 'flx-speed' ),
		'fields'      => array(
			'toggles'    => flxp_gql_f( '[FlxSpeedToggle!]!', __( 'Every on/off switch.', 'flx-speed' ) ),
			'cssScope'   => flxp_gql_f( 'FlxSpeedCssScope!', __( 'Which CSS the trimming covers.', 'flx-speed' ) ),
			'inlineKb'   => flxp_gql_f( 'Int!', __( 'Trimmed CSS up to this many kilobytes is written into the head; 0 always uses a file.', 'flx-speed' ) ),
			'safelist'   => flxp_gql_f( 'String!', __( 'Classes and ids that are always kept, one per line.', 'flx-speed' ) ),
			'exclude'    => flxp_gql_f( 'String!', __( 'Files to leave alone, one per line.', 'flx-speed' ) ),
			'skipUrls'   => flxp_gql_f( 'String!', __( 'Pages to leave alone, one per line.', 'flx-speed' ) ),
			'cssFiles'   => flxp_gql_f( 'Int!', __( 'Cached CSS copies.', 'flx-speed' ) ),
			'jsFiles'    => flxp_gql_f( 'Int!', __( 'Cached JS copies.', 'flx-speed' ) ),
			'cacheBytes' => flxp_gql_f( 'Float!', __( 'Cache size in bytes.', 'flx-speed' ) ),
			'homeUrl'    => flxp_gql_f( 'String!', __( 'Home page address.', 'flx-speed' ) ),
			'offUrl'     => flxp_gql_f( 'String!', __( 'Home page address with the optimizations off.', 'flx-speed' ) ),
			'auditUrl'   => flxp_gql_f( 'String', __( 'Flux SEO site audit page; empty without Flux SEO.', 'flx-speed' ) ),
			'animations' => flxp_gql_f( '[FlxSpeedAnimation!]!', __( 'Animations that repaint every frame.', 'flx-speed' ) ),
			'seenFiles'  => flxp_gql_f( 'Int', __( 'CSS files seen on the front end; empty when the front end was not seen yet.', 'flx-speed' ) ),
			'seenAt'     => flxp_gql_f( 'FlxSpeedWhen', __( 'When the front end was last seen; empty when never.', 'flx-speed' ) ),
		),
	)
);

flx_gql_query(
	'flxSpeedPageSpeed',
	array(
		'type'        => 'FlxSpeedPageSpeed!',
		'description' => __( 'PageSpeed switches, cache status and the animation report.', 'flx-speed' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): array => FLXP_Speed_Service::pagespeed(),
	)
);

flx_gql_mutation(
	'flxSpeedPageSpeedSave',
	array(
		'description' => __( 'Saves the PageSpeed settings, then clears the PageSpeed cache and the LiteSpeed page cache. Fields you leave out stay as they are.', 'flx-speed' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'viewport'    => flxp_gql_f( 'Boolean', __( 'Remove the zoom lock.', 'flx-speed' ) ),
			'emoji'       => flxp_gql_f( 'Boolean', __( 'Remove the emoji script.', 'flx-speed' ) ),
			'css'         => flxp_gql_f( 'Boolean', __( 'Remove unused CSS and inline it.', 'flx-speed' ) ),
			'minCss'      => flxp_gql_f( 'Boolean', __( 'Minify CSS files.', 'flx-speed' ) ),
			'minJs'       => flxp_gql_f( 'Boolean', __( 'Minify JS files.', 'flx-speed' ) ),
			'defer'       => flxp_gql_f( 'Boolean', __( 'Defer head scripts.', 'flx-speed' ) ),
			'deferJquery' => flxp_gql_f( 'Boolean', __( 'Defer jQuery too.', 'flx-speed' ) ),
			'fonts'       => flxp_gql_f( 'Boolean', __( 'Remove unused fonts.', 'flx-speed' ) ),
			'preload'     => flxp_gql_f( 'Boolean', __( 'Preload the above-the-fold font.', 'flx-speed' ) ),
			'cssScope'    => flxp_gql_f( 'FlxSpeedCssScope', __( 'Which CSS the trimming covers.', 'flx-speed' ) ),
			'inlineKb'    => flxp_gql_f( 'Int', __( 'Inline limit in kilobytes (0–500).', 'flx-speed' ) ),
			'safelist'    => flxp_gql_f( 'String', __( 'Classes and ids that are always kept, one per line.', 'flx-speed' ) ),
			'exclude'     => flxp_gql_f( 'String', __( 'Files to leave alone, one per line.', 'flx-speed' ) ),
			'skipUrls'    => flxp_gql_f( 'String', __( 'Pages to leave alone, one per line.', 'flx-speed' ) ),
		),
		'output'      => array(
			'pageSpeed' => flxp_gql_f( 'FlxSpeedPageSpeed', __( 'The settings after saving.', 'flx-speed' ) ),
		),
		'resolve'     => static function ( $input ): array {
			$in   = is_array( $input ) ? $input : array();
			$cur  = flx_gql_arr( flxp_settings() );
			$raw  = array();
			$keys = array( 'ps_viewport', 'ps_emoji', 'ps_css', 'ps_css_scope', 'ps_inline_kb', 'ps_safelist', 'ps_min_css', 'ps_min_js', 'ps_defer', 'ps_defer_jquery', 'ps_fonts', 'ps_preload', 'ps_exclude', 'ps_skip_urls' );
			foreach ( $keys as $k ) {
				$raw[ $k ] = $cur[ $k ] ?? '';
			}
			$map = array(
				'viewport'    => 'ps_viewport',
				'emoji'       => 'ps_emoji',
				'css'         => 'ps_css',
				'minCss'      => 'ps_min_css',
				'minJs'       => 'ps_min_js',
				'defer'       => 'ps_defer',
				'deferJquery' => 'ps_defer_jquery',
				'fonts'       => 'ps_fonts',
				'preload'     => 'ps_preload',
				'inlineKb'    => 'ps_inline_kb',
				'safelist'    => 'ps_safelist',
				'exclude'     => 'ps_exclude',
				'skipUrls'    => 'ps_skip_urls',
			);
			foreach ( $map as $gql => $key ) {
				if ( array_key_exists( $gql, $in ) && null !== $in[ $gql ] ) {
					$raw[ $key ] = is_bool( $in[ $gql ] ) ? ( $in[ $gql ] ? 1 : 0 ) : $in[ $gql ];
				}
			}
			if ( isset( $in['cssScope'] ) && is_string( $in['cssScope'] ) ) {
				$raw['ps_css_scope'] = 'LOCAL' === $in['cssScope'] ? 'local' : 'theme';
			}
			FLXP_Speed_Service::pagespeed_save( $raw );
			return array( 'pageSpeed' => FLXP_Speed_Service::pagespeed() );
		},
	)
);

flx_gql_mutation(
	'flxSpeedPageSpeedClear',
	array(
		'description' => __( 'Clears the PageSpeed cache and the LiteSpeed page cache.', 'flx-speed' ),
		'cap'         => 'manage_options',
		'input'       => array(),
		'output'      => array(
			'pageSpeed' => flxp_gql_f( 'FlxSpeedPageSpeed', __( 'The status after clearing.', 'flx-speed' ) ),
		),
		'resolve'     => static function (): array {
			FLXP_Speed_Service::pagespeed_clear();
			return array( 'pageSpeed' => FLXP_Speed_Service::pagespeed() );
		},
	)
);

/* ------------------------------------------------------------ Cache */

flx_gql_enum(
	'FlxSpeedCacheKind',
	array(
		'description' => __( 'Backend of the LiteSpeed object cache.', 'flx-speed' ),
		'values'      => array(
			'MEMCACHED' => array( 'value' => 'MEMCACHED' ),
			'REDIS'     => array( 'value' => 'REDIS' ),
		),
	)
);

flx_gql_enum(
	'FlxSpeedCacheOp',
	array(
		'description' => __( 'An operation of the Cache tab.', 'flx-speed' ),
		'values'      => array(
			'INSTALL_LITESPEED'  => array( 'value' => 'install_litespeed' ),
			'INSTALL_SQLITE'     => array( 'value' => 'install_sqlite' ),
			'ACTIVATE_LITESPEED' => array( 'value' => 'activate_litespeed' ),
			'ACTIVATE_SQLITE'    => array( 'value' => 'activate_sqlite' ),
			'USE_SQLITE'         => array( 'value' => 'use_sqlite' ),
			'USE_LITESPEED'      => array( 'value' => 'use_litespeed' ),
			'OBJECT_OFF'         => array( 'value' => 'object_off' ),
			'REPAIR'             => array( 'value' => 'repair' ),
			'PAGE_ON'            => array( 'value' => 'page_on' ),
			'PAGE_OFF'           => array( 'value' => 'page_off' ),
			'PURGE'              => array( 'value' => 'purge' ),
			'FLUSH'              => array( 'value' => 'flush' ),
			'TEST'               => array( 'value' => 'test' ),
		),
	)
);

flx_gql_type(
	'FlxSpeedCachePlugin',
	array(
		'description' => __( 'A cache plugin Flux Speed can install and drive.', 'flx-speed' ),
		'fields'      => array(
			'name'      => flxp_gql_f( 'String!', __( 'Plugin name.', 'flx-speed' ) ),
			'installed' => flxp_gql_f( 'Boolean!', __( 'The plugin is installed.', 'flx-speed' ) ),
			'active'    => flxp_gql_f( 'Boolean!', __( 'The plugin is active.', 'flx-speed' ) ),
			'version'   => flxp_gql_f( 'String!', __( 'Installed version; empty when not installed.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedCacheLiteSpeed',
	array(
		'description' => __( 'LiteSpeed Cache: page cache and object cache state.', 'flx-speed' ),
		'fields'      => array(
			'name'       => flxp_gql_f( 'String!', __( 'Plugin name.', 'flx-speed' ) ),
			'installed'  => flxp_gql_f( 'Boolean!', __( 'The plugin is installed.', 'flx-speed' ) ),
			'active'     => flxp_gql_f( 'Boolean!', __( 'The plugin is active.', 'flx-speed' ) ),
			'version'    => flxp_gql_f( 'String!', __( 'Installed version; empty when not installed.', 'flx-speed' ) ),
			'pageCache'  => flxp_gql_f( 'Boolean!', __( 'The page cache is on.', 'flx-speed' ) ),
			'object'     => flxp_gql_f( 'Boolean!', __( 'The object cache is on.', 'flx-speed' ) ),
			'objectKind' => flxp_gql_f( 'FlxSpeedCacheKind!', __( 'Object cache backend.', 'flx-speed' ) ),
			'objectHost' => flxp_gql_f( 'String!', __( 'Object cache host and port.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedCacheSqlite',
	array(
		'description' => __( 'SQLite Object Cache state.', 'flx-speed' ),
		'fields'      => array(
			'name'      => flxp_gql_f( 'String!', __( 'Plugin name.', 'flx-speed' ) ),
			'installed' => flxp_gql_f( 'Boolean!', __( 'The plugin is installed.', 'flx-speed' ) ),
			'active'    => flxp_gql_f( 'Boolean!', __( 'The plugin is active.', 'flx-speed' ) ),
			'version'   => flxp_gql_f( 'String!', __( 'Installed version; empty when not installed.', 'flx-speed' ) ),
			'fileName'  => flxp_gql_f( 'String', __( 'Cache file name; empty when there is none.', 'flx-speed' ) ),
			'fileSize'  => flxp_gql_f( 'Float!', __( 'Cache file size in bytes.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedCacheExtensions',
	array(
		'description' => __( 'PHP extensions the cache options need.', 'flx-speed' ),
		'fields'      => array(
			'memcached' => flxp_gql_f( 'Boolean!', __( 'Memcached extension is loaded.', 'flx-speed' ) ),
			'redis'     => flxp_gql_f( 'Boolean!', __( 'Redis extension is loaded.', 'flx-speed' ) ),
			'sqlite3'   => flxp_gql_f( 'Boolean!', __( 'SQLite3 extension is loaded.', 'flx-speed' ) ),
			'igbinary'  => flxp_gql_f( 'Boolean!', __( 'igbinary extension is loaded.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedCacheConflict',
	array(
		'description' => __( 'A problem between the page cache and the object cache.', 'flx-speed' ),
		'fields'      => array(
			'code'       => flxp_gql_f( 'String!', __( 'Conflict code.', 'flx-speed' ) ),
			'text'       => flxp_gql_f( 'String!', __( 'What is wrong and what to do.', 'flx-speed' ) ),
			'repairable' => flxp_gql_f( 'Boolean!', __( 'The Repair operation fixes it.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedCacheTest',
	array(
		'description' => __( 'Result of the last object cache test.', 'flx-speed' ),
		'fields'      => array(
			'at'         => flxp_gql_f( 'FlxSpeedWhen!', __( 'When the test ran.', 'flx-speed' ) ),
			'provider'   => flxp_gql_f( 'String!', __( 'Object cache owner when it ran.', 'flx-speed' ) ),
			'persistent' => flxp_gql_f( 'Boolean!', __( 'Values survived between two requests.', 'flx-speed' ) ),
			'ms'         => flxp_gql_f( 'Float!', __( 'Time of 100 writes and 100 reads in milliseconds.', 'flx-speed' ) ),
			'error'      => flxp_gql_f( 'String', __( 'Why the test could not finish; empty when it did.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedCacheUrls',
	array(
		'description' => __( 'Settings pages of the cache plugins.', 'flx-speed' ),
		'fields'      => array(
			'litespeedPage'   => flxp_gql_f( 'String!', __( 'LiteSpeed Cache settings.', 'flx-speed' ) ),
			'litespeedObject' => flxp_gql_f( 'String!', __( 'LiteSpeed Cache object cache settings.', 'flx-speed' ) ),
			'sqlitePlugin'    => flxp_gql_f( 'String!', __( 'SQLite Object Cache in the plugin list.', 'flx-speed' ) ),
		),
	)
);

flx_gql_type(
	'FlxSpeedCache',
	array(
		'description' => __( 'Page cache and object cache state with the options the Cache tab offers.', 'flx-speed' ),
		'fields'      => array(
			'pendingError'    => flxp_gql_f( 'String', __( 'Why finishing the LiteSpeed setup failed; empty when there is nothing wrong.', 'flx-speed' ) ),
			'litespeedServer' => flxp_gql_f( 'Boolean!', __( 'The web server is LiteSpeed.', 'flx-speed' ) ),
			'extensions'      => flxp_gql_f( 'FlxSpeedCacheExtensions!', __( 'PHP extensions.', 'flx-speed' ) ),
			'litespeed'       => flxp_gql_f( 'FlxSpeedCacheLiteSpeed!', __( 'LiteSpeed Cache.', 'flx-speed' ) ),
			'sqlite'          => flxp_gql_f( 'FlxSpeedCacheSqlite!', __( 'SQLite Object Cache.', 'flx-speed' ) ),
			'dropinExists'    => flxp_gql_f( 'Boolean!', __( 'wp-content/object-cache.php exists.', 'flx-speed' ) ),
			'dropinOwner'     => flxp_gql_f( 'String!', __( 'Who owns object-cache.php: litespeed, sqlite, redis, memcached, other or none.', 'flx-speed' ) ),
			'dropinName'      => flxp_gql_f( 'String!', __( 'Name of another plugin that owns object-cache.php; empty otherwise.', 'flx-speed' ) ),
			'provider'        => flxp_gql_f( 'String!', __( 'The object cache in use.', 'flx-speed' ) ),
			'persistent'      => flxp_gql_f( 'Boolean!', __( 'An external, persistent object cache is in use.', 'flx-speed' ) ),
			'conflicts'       => flxp_gql_f( '[FlxSpeedCacheConflict!]!', __( 'Problems between the caches.', 'flx-speed' ) ),
			'test'            => flxp_gql_f( 'FlxSpeedCacheTest', __( 'Last object cache test; empty when never run.', 'flx-speed' ) ),
			'settingsUrls'    => flxp_gql_f( 'FlxSpeedCacheUrls!', __( 'Settings pages.', 'flx-speed' ) ),
			'canInstall'      => flxp_gql_f( 'Boolean!', __( 'The person may install plugins.', 'flx-speed' ) ),
		),
	)
);

flx_gql_query(
	'flxSpeedCache',
	array(
		'type'        => 'FlxSpeedCache!',
		'description' => __( 'Page cache (LiteSpeed Cache) and object cache (SQLite Object Cache or LiteSpeed + Memcached) state.', 'flx-speed' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): array => FLXP_Speed_Service::cache(),
	)
);

flx_gql_mutation(
	'flxSpeedCacheRun',
	array(
		'description' => __( 'Runs one operation of the Cache tab: install or activate a cache plugin, switch the object cache, turn the page cache on or off, purge, flush or test.', 'flx-speed' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'op' => array(
				'type'        => 'FlxSpeedCacheOp!',
				'description' => __( 'The operation.', 'flx-speed' ),
			),
		),
		'output'      => array(
			'message' => flxp_gql_f( 'String', __( 'What happened; empty after a test.', 'flx-speed' ) ),
			'cache'   => flxp_gql_f( 'FlxSpeedCache', __( 'The state after the operation.', 'flx-speed' ) ),
		),
		'resolve'     => static function ( $input ): array {
			$in = is_array( $input ) ? $input : array();
			$op = isset( $in['op'] ) && is_string( $in['op'] ) ? $in['op'] : '';
			if ( ( 'install_litespeed' === $op || 'install_sqlite' === $op ) && ! current_user_can( 'install_plugins' ) ) {
				throw flx_gql_error( 'FORBIDDEN' );
			}
			$r = flxp_gql_ok( FLXP_Speed_Service::cache_run( $op ) );
			return array(
				'message' => $r['message'],
				'cache'   => FLXP_Speed_Service::cache(),
			);
		},
	)
);
