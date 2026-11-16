<?php
/**
 * Flux AI GraphQL: the tool list, switching tools on and off and settling name clashes.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

flx_gql_type(
	'FlxAiTool',
	array(
		'description' => __( 'A tool that the chat and the MCP server can call.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Tool::class,
			array(
				'name'        => array( 'String!', __( 'Tool name (with the plugin prefix).', 'flx-ai' ) ),
				'title'       => array( 'String!', __( 'Tool title for display.', 'flx-ai' ) ),
				'description' => array( 'String!', __( 'What the tool does.', 'flx-ai' ) ),
				'source'      => array( 'String!', __( 'Plugin folder that provides the tool.', 'flx-ai' ) ),
				'sourceLabel' => array( 'String!', __( 'Plugin name for display.', 'flx-ai' ), 'label' ),
				'readonly'    => array( 'Boolean!', __( 'The tool only reads.', 'flx-ai' ) ),
				'approval'    => array( 'Boolean!', __( 'A call waits for approval.', 'flx-ai' ) ),
				'capability'  => array( 'String!', __( 'Capability the caller needs.', 'flx-ai' ), 'cap' ),
				'mcp'         => array( 'Boolean!', __( 'Offered over MCP.', 'flx-ai' ) ),
				'chat'        => array( 'Boolean!', __( 'Offered in the Flux AI chat.', 'flx-ai' ) ),
				'off'         => array( 'Boolean!', __( 'Switched off by an administrator.', 'flx-ai' ) ),
				'aliases'     => array( '[String!]!', __( 'Old names that still work.', 'flx-ai' ) ),
				'duplicate'   => array( 'Boolean!', __( 'Another plugin registered the same name and this one is inactive.', 'flx-ai' ), 'dupe' ),
				'rivals'      => array( '[String!]!', __( 'Plugins that offer the same name.', 'flx-ai' ) ),
				'prefixed'    => array( 'Boolean!', __( 'The name carries the plugin prefix.', 'flx-ai' ) ),
				'calls'       => array( 'Int!', __( 'Calls in the last 30 days.', 'flx-ai' ) ),
				'errors'      => array( 'Int!', __( 'Failed calls in the last 30 days.', 'flx-ai' ) ),
				'avgMs'       => array( 'Int!', __( 'Average duration in the last 30 days, milliseconds.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_input(
	'FlxAiToolStateInput',
	array(
		'description' => __( 'One tool to switch.', 'flx-ai' ),
		'fields'      => array(
			'name' => array(
				'type'        => 'String!',
				'description' => __( 'Tool name; an old alias works too.', 'flx-ai' ),
			),
			'on'   => array(
				'type'        => 'Boolean!',
				'description' => __( 'Whether the tool is on.', 'flx-ai' ),
			),
		),
	)
);

flx_gql_query(
	'flxAiTools',
	array(
		'description' => __( 'Every tool with its state and use over the last 30 days.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'type'        => '[FlxAiTool!]!',
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXAI_Tools_Service::CACHE_TAG );
			flx_gql_cache_tag( FLXAI_Usage_Service::CACHE_TAG );
			flx_gql_cache_tag( FLXAI_Settings_Service::CACHE_TAG );
			return FLXAI_Tools_Service::all();
		},
	)
);

flx_gql_mutation(
	'flxAiToolsUpdate',
	array(
		'description' => __( 'Switches tools on or off. Tools you leave out keep their state.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'tools' => array(
				'type'        => '[FlxAiToolStateInput!]!',
				'description' => __( 'States to set.', 'flx-ai' ),
			),
		),
		'output'      => array(
			'tools' => array(
				'type'        => '[FlxAiTool!]',
				'description' => __( 'Every tool after the change; empty when the input was refused.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$states = array();
			foreach ( flx_gql_arr( $input['tools'] ?? array() ) as $row ) {
				$row = flx_gql_arr( $row );
				$n   = flx_gql_str( $row['name'] ?? '' );
				if ( '' !== $n ) {
					$states[ $n ] = ! empty( $row['on'] );
				}
			}
			$r = FLXAI_Tools_Service::set_on( $states );
			return $r instanceof WP_Error ? $r : array( 'tools' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiToolPick',
	array(
		'description' => __( 'Chooses which plugin provides a tool when several register the same name.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'name'   => array(
				'type'        => 'String!',
				'description' => __( 'Tool name.', 'flx-ai' ),
			),
			'source' => array(
				'type'        => 'String!',
				'description' => __( 'Plugin folder that should provide it.', 'flx-ai' ),
			),
		),
		'output'      => array(
			'tools' => array(
				'type'        => '[FlxAiTool!]',
				'description' => __( 'Every tool after the change; empty when the input was refused.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Tools_Service::pick( flx_gql_str( $input['name'] ?? '' ), flx_gql_str( $input['source'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'tools' => $r );
		},
	)
);
