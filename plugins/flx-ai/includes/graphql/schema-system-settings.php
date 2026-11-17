<?php
/**
 * Flux AI GraphQL: the AI settings of one connected system (the switch, its model and the fields the plugin
 * declared with the Flux AI SDK).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

flx_gql_type(
	'FlxAiSystemField',
	array(
		'description' => __( 'A setting a plugin declared with the Flux AI SDK.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_System_Field::class,
			array(
				'key'     => array( 'String!', __( 'Setting key.', 'flx-ai' ) ),
				'type'    => array( 'String!', __( 'toggle, text, textarea, number, select or model.', 'flx-ai' ) ),
				'label'   => array( 'String!', __( 'Label.', 'flx-ai' ) ),
				'help'    => array( 'String!', __( 'Help text.', 'flx-ai' ) ),
				'value'   => array( 'String!', __( 'Saved value (toggles are "1" or "0").', 'flx-ai' ) ),
				'options' => array( '[FlxAiOption!]!', __( 'Choices of a select field.', 'flx-ai' ) ),
				'min'     => array( 'Float', __( 'Lowest number.', 'flx-ai' ) ),
				'max'     => array( 'Float', __( 'Highest number.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSystemSettings',
	array(
		'description' => __( 'The AI settings page of one system.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_System_Settings::class,
			array(
				'source'       => array( 'String!', __( 'System id (plugin folder).', 'flx-ai' ) ),
				'label'        => array( 'String!', __( 'Plugin name.', 'flx-ai' ) ),
				'description'  => array( 'String!', __( 'What the plugin does with AI.', 'flx-ai' ) ),
				'on'           => array( 'Boolean!', __( 'The plugin may use AI.', 'flx-ai' ) ),
				'model'        => array( 'String!', __( 'Model of this plugin; empty uses the default.', 'flx-ai' ) ),
				'defaultModel' => array( 'String!', __( 'The default model.', 'flx-ai' ), 'default_model' ),
				'fields'       => array( '[FlxAiSystemField!]!', __( 'Declared settings.', 'flx-ai' ) ),
				'declared'     => array( 'Boolean!', __( 'The plugin declared settings or details with the SDK.', 'flx-ai' ) ),
				'toolsTotal'   => array( 'Int!', __( 'Tools of the plugin.', 'flx-ai' ), 'tools_total' ),
				'toolsOn'      => array( 'Int!', __( 'Tools that are on.', 'flx-ai' ), 'tools_on' ),
				'sdkVersion'   => array( 'String!', __( 'Flux AI SDK version; empty when not loaded.', 'flx-ai' ), 'sdk_version' ),
			)
		),
	)
);

flx_gql_query(
	'flxAiSystemSettings',
	array(
		'description' => __( 'The AI settings of one system.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'type'        => 'FlxAiSystemSettings!',
		'args'        => array(
			'source' => array( 'type' => 'String!', 'description' => __( 'System id.', 'flx-ai' ) ),
		),
		'resolve'     => static function ( $root, array $args ): FLXAI_System_Settings {
			flx_gql_cache_tag( FLXAI_System_Settings_Service::CACHE_TAG );
			flx_gql_cache_tag( FLXAI_Usage_Service::CACHE_TAG );
			flx_gql_cache_tag( FLXAI_Settings_Service::CACHE_TAG );
			flx_gql_cache_tag( FLXAI_Tools_Service::CACHE_TAG );
			return FLXAI_System_Settings_Service::get( flx_gql_str( $args['source'] ?? '' ) );
		},
	)
);

flx_gql_input(
	'FlxAiSystemValueInput',
	array(
		'description' => __( 'The value of one declared setting.', 'flx-ai' ),
		'fields'      => array(
			'key'   => array( 'type' => 'String!', 'description' => __( 'Setting key.', 'flx-ai' ) ),
			'value' => array( 'type' => 'String!', 'description' => __( 'Value (toggles "1" or "0").', 'flx-ai' ) ),
		),
	)
);

flx_gql_mutation(
	'flxAiSystemSettingsUpdate',
	array(
		'description' => __( 'Saves the AI settings of one system. What you leave out keeps its value.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'source' => array( 'type' => 'String!', 'description' => __( 'System id.', 'flx-ai' ) ),
			'on'     => array( 'type' => 'Boolean', 'description' => __( 'The plugin may use AI.', 'flx-ai' ) ),
			'model'  => array( 'type' => 'String', 'description' => __( 'Model of this plugin; empty uses the default.', 'flx-ai' ) ),
			'values' => array( 'type' => '[FlxAiSystemValueInput!]', 'description' => __( 'Declared settings to change.', 'flx-ai' ) ),
		),
		'output'      => array(
			'settings' => array(
				'type'        => 'FlxAiSystemSettings',
				'description' => __( 'The settings after the change; empty when the input was refused.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$values = array();
			foreach ( flx_gql_arr( $input['values'] ?? array() ) as $row ) {
				$row = flx_gql_arr( $row );
				$values[ flx_gql_str( $row['key'] ?? '' ) ] = flx_gql_str( $row['value'] ?? '' );
			}
			$r = FLXAI_System_Settings_Service::save(
				flx_gql_str( $input['source'] ?? '' ),
				array_key_exists( 'on', $input ) && null !== $input['on'] ? ! empty( $input['on'] ) : null,
				array_key_exists( 'model', $input ) && null !== $input['model'] ? flx_gql_str( $input['model'] ) : null,
				$values
			);
			return $r instanceof WP_Error ? $r : array( 'settings' => $r );
		},
	)
);
