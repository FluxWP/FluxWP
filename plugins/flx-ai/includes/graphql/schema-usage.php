<?php
/**
 * Flux AI GraphQL: spending, reports, billing, the request log and the connected systems.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

flx_gql_enum(
	'FlxAiLogKind',
	array(
		'description' => __( 'What a log row records.', 'flx-ai' ),
		'values'      => array(
			'MODEL' => array(
				'value'       => 'model',
				'description' => __( 'A request to a language model.', 'flx-ai' ),
			),
			'TOOL'  => array(
				'value'       => 'tool',
				'description' => __( 'A tool call.', 'flx-ai' ),
			),
		),
	)
);

flx_gql_type(
	'FlxAiBudgetPeriod',
	array(
		'description' => __( 'Spending against one limit period.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Budget_Period::class,
			array(
				'key'    => array( 'String!', __( 'day, week or month.', 'flx-ai' ) ),
				'label'  => array( 'String!', __( 'Period name in the user\'s language.', 'flx-ai' ) ),
				'limit'  => array( 'Float!', __( 'Limit in USD; 0 means unlimited.', 'flx-ai' ) ),
				'spent'  => array( 'Float!', __( 'Spent this period in USD.', 'flx-ai' ) ),
				'pct'    => array( 'Float!', __( 'Share of the limit used, in percent (0 without a limit).', 'flx-ai' ) ),
				'over'   => array( 'Boolean!', __( 'The limit is reached.', 'flx-ai' ) ),
				'resets' => array( 'FlxDateTime!', __( 'When the period resets.', 'flx-ai' ), 'resets', 'time' ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiReportTotals',
	array(
		'description' => __( 'Totals of a usage report.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Report_Totals::class,
			array(
				'calls'      => array( 'Int!', __( 'Model requests.', 'flx-ai' ) ),
				'tools'      => array( 'Int!', __( 'Tool calls.', 'flx-ai' ) ),
				'inTokens'   => array( 'Int!', __( 'Input tokens.', 'flx-ai' ), 'in_tok' ),
				'outTokens'  => array( 'Int!', __( 'Output tokens.', 'flx-ai' ), 'out_tok' ),
				'cacheRead'  => array( 'Int!', __( 'Tokens read from the prompt cache.', 'flx-ai' ) ),
				'cacheWrite' => array( 'Int!', __( 'Tokens written to the prompt cache.', 'flx-ai' ) ),
				'cost'       => array( 'Float!', __( 'Cost in USD.', 'flx-ai' ) ),
				'fullCost'   => array( 'Float!', __( 'Cost in USD without the prompt cache.', 'flx-ai' ) ),
				'saved'      => array( 'Float!', __( 'Saved by the prompt cache in USD.', 'flx-ai' ) ),
				'modelMs'    => array( 'Int!', __( 'Time spent in models, milliseconds.', 'flx-ai' ) ),
				'toolMs'     => array( 'Int!', __( 'Time spent in tools, milliseconds.', 'flx-ai' ) ),
				'errors'     => array( 'Int!', __( 'Failed requests and tool calls.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiReportDay',
	array(
		'description' => __( 'One day of a usage report.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Report_Day::class,
			array(
				'date'      => array( 'String!', __( 'Day as Y-m-d in the site time zone.', 'flx-ai' ) ),
				'cost'      => array( 'Float!', __( 'Cost in USD.', 'flx-ai' ) ),
				'inTokens'  => array( 'Int!', __( 'Input tokens.', 'flx-ai' ), 'in_tok' ),
				'outTokens' => array( 'Int!', __( 'Output tokens.', 'flx-ai' ), 'out_tok' ),
				'cacheRead' => array( 'Int!', __( 'Tokens read from the prompt cache.', 'flx-ai' ) ),
				'modelMs'   => array( 'Int!', __( 'Time spent in models, milliseconds.', 'flx-ai' ) ),
				'toolMs'    => array( 'Int!', __( 'Time spent in tools, milliseconds.', 'flx-ai' ) ),
				'calls'     => array( 'Int!', __( 'Model requests.', 'flx-ai' ) ),
				'tools'     => array( 'Int!', __( 'Tool calls.', 'flx-ai' ) ),
				'errors'    => array( 'Int!', __( 'Failed requests and tool calls.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiBreakdownRow',
	array(
		'description' => __( 'One row of a usage breakdown (by plugin, model, purpose or provider).', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Breakdown_Row::class,
			array(
				'key'       => array( 'String!', __( 'Raw key: source id, model id …', 'flx-ai' ) ),
				'label'     => array( 'String!', __( 'Name for display.', 'flx-ai' ) ),
				'calls'     => array( 'Int!', __( 'Model requests.', 'flx-ai' ) ),
				'tools'     => array( 'Int!', __( 'Tool calls.', 'flx-ai' ) ),
				'cost'      => array( 'Float!', __( 'Cost in USD.', 'flx-ai' ) ),
				'fullCost'  => array( 'Float!', __( 'Cost in USD without the prompt cache.', 'flx-ai' ) ),
				'inTokens'  => array( 'Int!', __( 'Input tokens.', 'flx-ai' ), 'in_tok' ),
				'outTokens' => array( 'Int!', __( 'Output tokens.', 'flx-ai' ), 'out_tok' ),
				'cacheRead' => array( 'Int!', __( 'Tokens read from the prompt cache.', 'flx-ai' ) ),
				'modelMs'   => array( 'Int!', __( 'Time spent in models, milliseconds.', 'flx-ai' ) ),
				'toolMs'    => array( 'Int!', __( 'Time spent in tools, milliseconds.', 'flx-ai' ) ),
				'errors'    => array( 'Int!', __( 'Failed requests and tool calls.', 'flx-ai' ) ),
				'partial'   => array( 'Boolean!', __( 'Part of the cost is estimated from the price list.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiToolStat',
	array(
		'description' => __( 'Time and errors of one tool.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Tool_Stat::class,
			array(
				'key'     => array( 'String!', __( 'Tool name.', 'flx-ai' ) ),
				'label'   => array( 'String!', __( 'Tool title for display.', 'flx-ai' ) ),
				'source'  => array( 'String!', __( 'Plugin that provides the tool.', 'flx-ai' ) ),
				'calls'   => array( 'Int!', __( 'Calls.', 'flx-ai' ) ),
				'avgMs'   => array( 'Int!', __( 'Average duration, milliseconds.', 'flx-ai' ) ),
				'maxMs'   => array( 'Int!', __( 'Longest duration, milliseconds.', 'flx-ai' ) ),
				'totalMs' => array( 'Int!', __( 'Total duration, milliseconds.', 'flx-ai' ) ),
				'errors'  => array( 'Int!', __( 'Failed calls.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiReport',
	array(
		'description' => __( 'Usage of Flux AI over a range: cost, time, tokens and tools.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Report::class,
			array(
				'from'       => array( 'FlxDateTime!', __( 'Start of the range.', 'flx-ai' ), 'from', 'time' ),
				'to'         => array( 'FlxDateTime!', __( 'End of the range.', 'flx-ai' ), 'to', 'time' ),
				'totals'     => array( 'FlxAiReportTotals!', __( 'Totals of the range.', 'flx-ai' ) ),
				'daily'      => array( '[FlxAiReportDay!]!', __( 'One entry per day.', 'flx-ai' ) ),
				'bySource'   => array( '[FlxAiBreakdownRow!]!', __( 'By plugin.', 'flx-ai' ), 'by_source' ),
				'byModel'    => array( '[FlxAiBreakdownRow!]!', __( 'By model.', 'flx-ai' ), 'by_model' ),
				'byPurpose'  => array( '[FlxAiBreakdownRow!]!', __( 'By plugin and purpose.', 'flx-ai' ), 'by_purpose' ),
				'byTool'     => array( '[FlxAiToolStat!]!', __( 'Slowest tools first.', 'flx-ai' ), 'by_tool' ),
				'byProvider' => array( '[FlxAiBreakdownRow!]!', __( 'By provider (billing months only).', 'flx-ai' ), 'by_provider' ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiMonth',
	array(
		'description' => __( 'A month of the billing list.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Month::class,
			array(
				'month' => array( 'String!', __( 'Month as Y-m.', 'flx-ai' ), 'ym' ),
				'label' => array( 'String!', __( 'Month name for display.', 'flx-ai' ) ),
				'cost'  => array( 'Float!', __( 'Cost in USD.', 'flx-ai' ) ),
				'saved' => array( 'Float!', __( 'Saved by the prompt cache in USD.', 'flx-ai' ) ),
				'calls' => array( 'Int!', __( 'Model requests.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiCredits',
	array(
		'description' => __( 'The OpenRouter account state from the last refresh.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Credits::class,
			array(
				'checked'   => array( 'FlxDateTime!', __( 'When the state was read.', 'flx-ai' ), 'checked', 'time' ),
				'usage'     => array( 'Float', __( 'Total usage of the key in USD; null when unknown.', 'flx-ai' ) ),
				'limit'     => array( 'Float', __( 'Limit of the key in USD; null when it has none.', 'flx-ai' ) ),
				'remaining' => array( 'Float', __( 'Remaining of the key limit in USD; null when it has none.', 'flx-ai' ) ),
				'credits'   => array( 'Float', __( 'Remaining account credit in USD; null when unknown.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiBilling',
	array(
		'description' => __( 'A billing month: report, provider split, the month list and the credit state.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Billing::class,
			array(
				'month'   => array( 'String!', __( 'Month as Y-m.', 'flx-ai' ), 'ym' ),
				'report'  => array( 'FlxAiReport!', __( 'Usage of the month.', 'flx-ai' ) ),
				'months'  => array( '[FlxAiMonth!]!', __( 'The last 12 months.', 'flx-ai' ) ),
				'credits' => array( 'FlxAiCredits', __( 'OpenRouter account state; null before the first refresh.', 'flx-ai' ) ),
				'csvUrl'  => array( 'String!', __( 'Link that downloads the month as CSV (carries a one-time check for the signed-in user).', 'flx-ai' ), 'csv_url' ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiLog',
	array(
		'description' => __( 'One model request or tool call.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Log_Row::class,
			array(
				'databaseId'   => array( 'Int!', __( 'Log row ID.', 'flx-ai' ), 'id' ),
				'time'         => array( 'FlxDateTime!', __( 'When it happened.', 'flx-ai' ), 'ts', 'time' ),
				'kind'         => array( 'FlxAiLogKind!', __( 'Model request or tool call.', 'flx-ai' ) ),
				'source'       => array( 'String!', __( 'System that made the request.', 'flx-ai' ) ),
				'sourceLabel'  => array( 'String!', __( 'System name for display.', 'flx-ai' ), 'source_label' ),
				'purpose'      => array( 'String!', __( 'Why it was made (chat, title, test …).', 'flx-ai' ) ),
				'purposeLabel' => array( 'String!', __( 'Purpose for display.', 'flx-ai' ), 'purpose_label' ),
				'chatId'       => array( 'Int!', __( 'Chat it belongs to; 0 when none.', 'flx-ai' ), 'chat_id' ),
				'userId'       => array( 'Int!', __( 'User it ran for; 0 for the system.', 'flx-ai' ), 'user_id' ),
				'userName'     => array( 'String!', __( 'Name of the user; empty for the system.', 'flx-ai' ), 'user_name' ),
				'provider'     => array( 'String!', __( 'Provider that served it.', 'flx-ai' ) ),
				'model'        => array( 'String!', __( 'Model id; empty for a tool call.', 'flx-ai' ) ),
				'modelLabel'   => array( 'String!', __( 'Model name for display.', 'flx-ai' ), 'model_label' ),
				'tool'         => array( 'String!', __( 'Tool name; empty for a model request.', 'flx-ai' ) ),
				'inTokens'     => array( 'Int!', __( 'Input tokens.', 'flx-ai' ), 'in_tok' ),
				'outTokens'    => array( 'Int!', __( 'Output tokens.', 'flx-ai' ), 'out_tok' ),
				'cacheRead'    => array( 'Int!', __( 'Tokens read from the prompt cache.', 'flx-ai' ) ),
				'cacheWrite'   => array( 'Int!', __( 'Tokens written to the prompt cache.', 'flx-ai' ) ),
				'cost'         => array( 'Float!', __( 'Cost in USD.', 'flx-ai' ) ),
				'estimated'    => array( 'Boolean!', __( 'The cost is estimated from the price list.', 'flx-ai' ) ),
				'ms'           => array( 'Int!', __( 'Duration in milliseconds.', 'flx-ai' ) ),
				'ok'           => array( 'Boolean!', __( 'It worked.', 'flx-ai' ) ),
				'error'        => array( 'String!', __( 'Raw failure reason; empty when it worked.', 'flx-ai' ) ),
				'errorLabel'   => array( 'String!', __( 'Failure for display; empty when it worked.', 'flx-ai' ), 'error_label' ),
				'hasBody'      => array( 'Boolean!', __( 'The content of the request was stored (flxAiLogBody).', 'flx-ai' ), 'has_body' ),
			)
		),
	)
);
flx_gql_connection( 'FlxAiLog', __( 'A page of log rows, newest first.', 'flx-ai' ) );

flx_gql_type(
	'FlxAiLogMessage',
	array(
		'description' => __( 'One message of a stored request.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Log_Message::class,
			array(
				'role' => array( 'String!', __( 'system, user, assistant or tool.', 'flx-ai' ) ),
				'text' => array( 'String!', __( 'Shortened text with personal data masked.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiLogBody',
	array(
		'description' => __( 'The stored content of a log row: the last outgoing messages and the reply, shortened, with e-mail, phone, IBAN and keys masked.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Log_Body::class,
			array(
				'logId'     => array( 'Int!', __( 'Log row ID.', 'flx-ai' ), 'log_id' ),
				'request'   => array( '[FlxAiLogMessage!]!', __( 'Outgoing messages.', 'flx-ai' ) ),
				'reply'     => array( 'String!', __( 'The reply text.', 'flx-ai' ) ),
				'toolCalls' => array( '[String!]!', __( 'Tool calls the reply asked for.', 'flx-ai' ), 'tool_calls' ),
				'error'     => array( 'String!', __( 'Failure message; empty when it worked.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiOption',
	array(
		'description' => __( 'A choice of a select field.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Option::class,
			array(
				'id'    => array( 'String!', __( 'Value to save.', 'flx-ai' ) ),
				'label' => array( 'String!', __( 'Name for display.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSystem',
	array(
		'description' => __( 'A system (plugin) that uses Flux AI, with its usage of the last 30 days.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_System::class,
			array(
				'source'        => array( 'String!', __( 'System id, e.g. flx-chat.', 'flx-ai' ) ),
				'label'         => array( 'String!', __( 'Name for display.', 'flx-ai' ) ),
				'installed'     => array( 'Boolean!', __( 'The plugin is installed.', 'flx-ai' ) ),
				'active'        => array( 'Boolean!', __( 'The plugin is active.', 'flx-ai' ) ),
				'on'            => array( 'Boolean!', __( 'AI is on for this system. A system that is off cannot send requests.', 'flx-ai' ) ),
				'model'         => array( 'String!', __( 'Model the system uses.', 'flx-ai' ) ),
				'modelLabel'    => array( 'String!', __( 'Model name for display.', 'flx-ai' ), 'model_label' ),
				'calls'         => array( 'Int!', __( 'Model requests in 30 days.', 'flx-ai' ) ),
				'tools'         => array( 'Int!', __( 'Tool calls in 30 days.', 'flx-ai' ) ),
				'tokens'        => array( 'Int!', __( 'Tokens in 30 days.', 'flx-ai' ) ),
				'cost'          => array( 'Float!', __( 'Cost in USD in 30 days.', 'flx-ai' ) ),
				'errors'        => array( 'Int!', __( 'Failures in 30 days.', 'flx-ai' ) ),
				'lastUsed'      => array( 'FlxDateTime', __( 'Last use; null when unused in 30 days.', 'flx-ai' ), 'last', 'time' ),
				'settingsPage'  => array( 'String!', __( 'The system\'s own settings page, relative to wp-admin; empty when none.', 'flx-ai' ), 'page' ),
				'hasAiSettings' => array( 'Boolean!', __( 'The system declares AI settings of its own (classic page).', 'flx-ai' ), 'has_ai_settings' ),
			)
		),
	)
);

flx_gql_query(
	'flxAiBudget',
	array(
		'type'        => '[FlxAiBudgetPeriod!]!',
		'description' => __( 'Spending against the daily, weekly and monthly limits.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXAI_Usage_Service::CACHE_TAG );
			return FLXAI_Usage_Service::budget();
		},
	)
);

flx_gql_query(
	'flxAiReport',
	array(
		'type'        => 'FlxAiReport!',
		'description' => __( 'Usage of the last days: cost, time, tokens, tools.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'args'        => array(
			'days'   => array(
				'type'        => 'Int',
				'description' => __( 'Days to look back (1–365); 30 by default.', 'flx-ai' ),
			),
			'source' => array(
				'type'        => 'String',
				'description' => __( 'Only this system, e.g. flx-chat; empty for all.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): FLXAI_Report {
			flx_gql_cache_tag( FLXAI_Usage_Service::CACHE_TAG );
			return FLXAI_Usage_Service::report( flx_gql_int( $args['days'] ?? null, 30 ), flx_gql_str( $args['source'] ?? '' ) );
		},
	)
);

flx_gql_query(
	'flxAiBilling',
	array(
		'type'        => 'FlxAiBilling!',
		'description' => __( 'A billing month with its provider split, the month list and the OpenRouter credit.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'args'        => array(
			'month' => array(
				'type'        => 'String',
				'description' => __( 'Month as Y-m; this month by default.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): FLXAI_Billing {
			// Not tagged for caching: csvUrl carries a nonce that must stay fresh.
			return FLXAI_Usage_Service::billing( flx_gql_str( $args['month'] ?? '' ) );
		},
	)
);

flx_gql_query(
	'flxAiLogs',
	array(
		'type'        => 'FlxAiLogConnection!',
		'description' => __( 'Every model request and tool call, newest first.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'args'        => flx_gql_connection_args(
			array(
				'days'       => array(
					'type'        => 'Int',
					'description' => __( 'Days to look back (1–365); 7 by default.', 'flx-ai' ),
				),
				'kind'       => array(
					'type'        => 'FlxAiLogKind',
					'description' => __( 'Only model requests or only tool calls.', 'flx-ai' ),
				),
				'source'     => array(
					'type'        => 'String',
					'description' => __( 'Only this system; empty for all.', 'flx-ai' ),
				),
				'errorsOnly' => array(
					'type'        => 'Boolean',
					'description' => __( 'Only failed rows.', 'flx-ai' ),
				),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			flx_gql_cache_tag( FLXAI_Usage_Service::CACHE_TAG );
			return flx_gql_paginate(
				$args,
				static fn( int $offset, int $limit ): array => FLXAI_Usage_Service::logs(
					flx_gql_int( $args['days'] ?? null, 7 ),
					flx_gql_str( $args['kind'] ?? '' ),
					flx_gql_str( $args['source'] ?? '' ),
					! empty( $args['errorsOnly'] ),
					$offset,
					$limit
				)
			);
		},
	)
);

flx_gql_query(
	'flxAiLogBody',
	array(
		'type'        => 'FlxAiLogBody',
		'description' => __( 'The stored content of a log row; null when none was stored.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'args'        => array(
			'id' => array(
				'type'        => 'Int!',
				'description' => __( 'Log row ID (FlxAiLog.databaseId).', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): ?FLXAI_Log_Body {
			$b = FLXAI_Usage_Service::log_body( flx_gql_int( $args['id'] ?? 0 ) );
			return $b instanceof FLXAI_Log_Body ? $b : null;
		},
	)
);

flx_gql_query(
	'flxAiLogSources',
	array(
		'type'        => '[FlxAiOption!]!',
		'description' => __( 'Systems a log can be filtered by.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			$out = array();
			foreach ( FLXAI_Usage_Service::log_sources() as $s ) {
				$out[] = new FLXAI_Option( $s['source'], $s['label'] );
			}
			return $out;
		},
	)
);

flx_gql_query(
	'flxAiSystems',
	array(
		'type'        => '[FlxAiSystem!]!',
		'description' => __( 'Plugins that use Flux AI, with their usage of the last 30 days.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXAI_Usage_Service::CACHE_TAG );
			return FLXAI_Usage_Service::systems();
		},
	)
);

flx_gql_input(
	'FlxAiSystemStateInput',
	array(
		'description' => __( 'Whether AI is on for one system.', 'flx-ai' ),
		'fields'      => array(
			'source' => array(
				'type'        => 'String!',
				'description' => __( 'System id.', 'flx-ai' ),
			),
			'on'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'AI is on for the system.', 'flx-ai' ),
			),
		),
	)
);

flx_gql_mutation(
	'flxAiSystemsUpdate',
	array(
		'description' => __( 'Switches AI on or off per system. Systems you leave out keep their state.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'systems' => array(
				'type'        => '[FlxAiSystemStateInput!]!',
				'description' => __( 'States to set.', 'flx-ai' ),
			),
		),
		'output'      => array(
			'systems' => array(
				'type'        => '[FlxAiSystem!]!',
				'description' => __( 'Every system after the change.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( array $input ): array {
			$states = array();
			foreach ( flx_gql_arr( $input['systems'] ?? array() ) as $row ) {
				$row = flx_gql_arr( $row );
				$src = flx_gql_str( $row['source'] ?? '' );
				if ( '' !== $src ) {
					$states[ $src ] = ! empty( $row['on'] );
				}
			}
			return array( 'systems' => FLXAI_Usage_Service::set_systems( $states ) );
		},
	)
);

flx_gql_mutation(
	'flxAiCreditsRefresh',
	array(
		'description' => __( 'Asks OpenRouter for the credit and key state and stores it.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(),
		'output'      => array(
			'credits' => array(
				'type'        => 'FlxAiCredits',
				'description' => __( 'The state that was read.', 'flx-ai' ),
			),
		),
		'resolve'     => static function () {
			$r = FLXAI_Usage_Service::refresh_credits();
			if ( $r instanceof WP_Error ) {
				return $r;
			}
			FLXAI_Usage_Service::changed();
			return array( 'credits' => $r );
		},
	)
);
