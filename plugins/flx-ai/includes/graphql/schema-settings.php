<?php
/**
 * Flux AI GraphQL: settings, API keys (write-only), the model picker and the connection tests.
 *
 * API keys never leave the server: FlxAiSecret only says whether a key is set and shows its last four
 * characters. They are written through FlxAiSettingsUpdateInput.secrets.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

flx_gql_enum(
	'FlxAiCacheMode',
	array(
		'description' => __( 'Claude prompt cache.', 'flx-ai' ),
		'values'      => array(
			'OFF'          => array( 'value' => 'off' ),
			'FIVE_MINUTES' => array( 'value' => '5m' ),
			'ONE_HOUR'     => array( 'value' => '1h' ),
		),
	)
);
flx_gql_enum(
	'FlxAiEffort',
	array(
		'description' => __( 'How deeply Claude thinks.', 'flx-ai' ),
		'values'      => array(
			'LOW'    => array( 'value' => 'low' ),
			'MEDIUM' => array( 'value' => 'medium' ),
			'HIGH'   => array( 'value' => 'high' ),
		),
	)
);
flx_gql_enum(
	'FlxAiLimitAction',
	array(
		'description' => __( 'What happens when a spending limit is reached.', 'flx-ai' ),
		'values'      => array(
			'BLOCK' => array( 'value' => 'block' ),
			'WARN'  => array( 'value' => 'warn' ),
		),
	)
);
flx_gql_enum(
	'FlxAiChatMode',
	array(
		'description' => __( 'Default layout of the shared chat.', 'flx-ai' ),
		'values'      => array(
			'BUBBLE' => array( 'value' => 'bubble' ),
			'DOCK'   => array( 'value' => 'dock' ),
			'PAGE'   => array( 'value' => 'page' ),
		),
	)
);
flx_gql_enum(
	'FlxAiSecretGroup',
	array(
		'description' => __( 'Where an API key is used.', 'flx-ai' ),
		'values'      => array(
			'PROVIDER' => array( 'value' => 'provider' ),
			'MEDIA'    => array( 'value' => 'media' ),
			'WEB'      => array( 'value' => 'web' ),
			'RENDER'   => array( 'value' => 'render' ),
		),
	)
);
flx_gql_enum(
	'FlxAiMediaKind',
	array(
		'description' => __( 'Kind of a media model.', 'flx-ai' ),
		'values'      => array(
			'IMAGE' => array( 'value' => 'image' ),
			'VIDEO' => array( 'value' => 'video' ),
		),
	)
);
flx_gql_enum(
	'FlxAiModelProvider',
	array(
		'description' => __( 'Provider of a language model.', 'flx-ai' ),
		'values'      => array(
			'OPENROUTER' => array( 'value' => 'openrouter' ),
			'ANTHROPIC'  => array( 'value' => 'anthropic' ),
		),
	)
);

flx_gql_type(
	'FlxAiSourceModel',
	array(
		'description' => __( 'The model one plugin uses; empty means the default model.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Source_Model::class,
			array(
				'source' => array( 'String!', __( 'System id, e.g. flx-chat.', 'flx-ai' ) ),
				'label'  => array( 'String!', __( 'Name for display.', 'flx-ai' ) ),
				'model'  => array( 'String!', __( 'Model id; empty means the default model.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSecret',
	array(
		'description' => __( 'An API key: whether it is set, never its value.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Secret::class,
			array(
				'name'       => array( 'String!', __( 'Setting name, e.g. key_openrouter.', 'flx-ai' ) ),
				'group'      => array( 'FlxAiSecretGroup!', __( 'Where the key is used.', 'flx-ai' ) ),
				'label'      => array( 'String!', __( 'Provider name for display.', 'flx-ai' ) ),
				'help'       => array( 'String!', __( 'Where to get the key; empty when unknown.', 'flx-ai' ) ),
				'configured' => array( 'Boolean!', __( 'A key is saved (or set in wp-config.php).', 'flx-ai' ) ),
				'hint'       => array( 'String!', __( 'Last four characters of a saved key; empty for short keys.', 'flx-ai' ) ),
				'fromConfig' => array( 'Boolean!', __( 'The key comes from a wp-config.php constant.', 'flx-ai' ), 'from_config' ),
			)
		),
	)
);

/**
 * The settings fields: name => [ type, description ]. One table drives the object type and the mutation
 * input (nullable there), so the two cannot drift apart.
 *
 * @return array<string,array{0:string,1:string}>
 */
function flxai_gql_settings_spec(): array {
	return array(
		'anthropicWorkspace' => array( 'String', __( 'Anthropic workspace ID; only needed when the key is not tied to a workspace.', 'flx-ai' ) ),
		'model'              => array( 'String', __( 'Default model, used when a plugin names none. Empty picks one from the saved keys.', 'flx-ai' ) ),
		'chatModel'          => array( 'String', __( 'Model of the shared chat; empty uses the default.', 'flx-ai' ) ),
		'titleModel'         => array( 'String', __( 'Model that names chats; empty uses the chat model.', 'flx-ai' ) ),
		'cache'              => array( 'FlxAiCacheMode', __( 'Claude prompt cache.', 'flx-ai' ) ),
		'effort'             => array( 'FlxAiEffort', __( 'How deeply Claude thinks.', 'flx-ai' ) ),
		'thinking'           => array( 'Boolean', __( 'Show the model\'s thinking summary in chat.', 'flx-ai' ) ),
		'limitDay'           => array( 'Float', __( 'Daily spending limit in USD; 0 means unlimited.', 'flx-ai' ) ),
		'limitWeek'          => array( 'Float', __( 'Weekly spending limit in USD; 0 means unlimited.', 'flx-ai' ) ),
		'limitMonth'         => array( 'Float', __( 'Monthly spending limit in USD; 0 means unlimited.', 'flx-ai' ) ),
		'limitAction'        => array( 'FlxAiLimitAction', __( 'Stop requests or only warn when a limit is reached.', 'flx-ai' ) ),
		'limitWarnPct'       => array( 'Int', __( 'Warning threshold in percent of a limit (10–99).', 'flx-ai' ) ),
		'limitNotify'        => array( 'Boolean', __( 'E-mail the admin at the threshold and when a limit is reached.', 'flx-ai' ) ),
		'chatRoles'          => array( '[String!]', __( 'WordPress roles that may use the shared chat.', 'flx-ai' ) ),
		'chatAdmin'          => array( 'Boolean', __( 'Show the shared chat in the admin panel.', 'flx-ai' ) ),
		'chatFront'          => array( 'Boolean', __( 'Show the shared chat on the site for permitted logged-in users.', 'flx-ai' ) ),
		'chatMode'           => array( 'FlxAiChatMode', __( 'Default layout of the shared chat.', 'flx-ai' ) ),
		'chatMaxRounds'      => array( 'Int', __( 'Most steps the model may take in one turn (2–30).', 'flx-ai' ) ),
		'chatHistory'        => array( 'Int', __( 'Most messages sent to the model (6–200).', 'flx-ai' ) ),
		'chatInstructions'   => array( 'String', __( 'Extra instructions added to every chat.', 'flx-ai' ) ),
		'chatWidgets'        => array( 'Boolean', __( 'Let the model draw tables, cards, diagrams and charts as visuals.', 'flx-ai' ) ),
		'mediaImageModel'    => array( 'String', __( 'Default image model (an id of FlxAiSettingsOptions.mediaModels).', 'flx-ai' ) ),
		'mediaVideoModel'    => array( 'String', __( 'Default video model (an id of FlxAiSettingsOptions.mediaModels).', 'flx-ai' ) ),
		'mediaDays'          => array( 'Int', __( 'Days image and video job records are kept (7–1825); generated files are not deleted.', 'flx-ai' ) ),
		'imageToolOn'        => array( 'Boolean', __( 'Chat and agents may generate images (each image is charged).', 'flx-ai' ) ),
		'webOn'              => array( 'Boolean', __( 'Allow the web search tool.', 'flx-ai' ) ),
		'webFetchOn'         => array( 'Boolean', __( 'Allow the tool that reads public web pages.', 'flx-ai' ) ),
		'shellOn'            => array( 'Boolean', __( 'Allow the virtual shell tool on the workspace files.', 'flx-ai' ) ),
		'webProvider'        => array( 'String', __( 'Search provider id, or auto for the first provider with a key.', 'flx-ai' ) ),
		'webSearchMonth'     => array( 'Int', __( 'Monthly search limit; 0 means unlimited.', 'flx-ai' ) ),
		'webSearchCost'      => array( 'Float', __( 'Cost per 1,000 searches in USD; above 0 each search is logged and counted in the limit.', 'flx-ai' ) ),
		'renderUrl'          => array( 'String', __( 'Gotenberg address that turns visuals into images; empty for none.', 'flx-ai' ) ),
		'renderUser'         => array( 'String', __( 'Gotenberg user name (basic authentication).', 'flx-ai' ) ),
		'logDays'            => array( 'Int', __( 'Days usage logs are kept; 0 keeps them forever.', 'flx-ai' ) ),
		'logBodies'          => array( 'Boolean', __( 'Also store shortened, masked request and reply content.', 'flx-ai' ) ),
		'logBodyDays'        => array( 'Int', __( 'Days stored content is kept; 0 keeps it forever.', 'flx-ai' ) ),
		'deleteData'         => array( 'Boolean', __( 'Delete logs and chats when the plugin is deleted.', 'flx-ai' ) ),
		'cloudAi'            => array( 'String', __( 'Flux Cloud AI credits: auto (own key first), always or off.', 'flx-ai' ) ),
	);
}

$flxai_settings_fields = array();
foreach ( flxai_gql_settings_spec() as $flxai_name => $flxai_f ) {
	$flxai_settings_fields[ $flxai_name ] = array( $flxai_f[0] . '!', $flxai_f[1] );
}
$flxai_settings_fields['sourceModels'] = array( '[FlxAiSourceModel!]!', __( 'The model each plugin uses; empty means the default model.', 'flx-ai' ), 'source_models' );
unset( $flxai_name, $flxai_f );

flx_gql_type(
	'FlxAiSettings',
	array(
		'description' => __( 'Flux AI settings. API keys are not here, see flxAiSecrets.', 'flx-ai' ),
		'fields'      => flxai_gql_fields( FLXAI_Settings::class, $flxai_settings_fields ),
	)
);
unset( $flxai_settings_fields );

flx_gql_type(
	'FlxAiMediaModel',
	array(
		'description' => __( 'An image or video model of the catalog.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Media_Model::class,
			array(
				'id'     => array( 'String!', __( 'Model id to save.', 'flx-ai' ) ),
				'label'  => array( 'String!', __( 'Name for display.', 'flx-ai' ) ),
				'kind'   => array( 'FlxAiMediaKind!', __( 'Image or video.', 'flx-ai' ) ),
				'routes' => array( '[String!]!', __( 'Provider ids in the order they are tried.', 'flx-ai' ) ),
				'auto'   => array( 'String!', __( 'Provider chosen automatically (the first with a key); empty when none has a key.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSettingsOptions',
	array(
		'description' => __( 'Choices of the settings page and facts it shows.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Settings_Options::class,
			array(
				'roles'          => array( '[FlxAiOption!]!', __( 'WordPress roles.', 'flx-ai' ) ),
				'webProviders'   => array( '[FlxAiOption!]!', __( 'Web search providers.', 'flx-ai' ), 'web_providers' ),
				'mediaModels'    => array( '[FlxAiMediaModel!]!', __( 'Image and video catalog.', 'flx-ai' ), 'media_models' ),
				'mediaProviders' => array( '[FlxAiOption!]!', __( 'Image and video providers.', 'flx-ai' ), 'media_providers' ),
				'defaultModel'   => array( 'String!', __( 'The model used when none is chosen.', 'flx-ai' ), 'default_model' ),
				'renderer'       => array( 'String!', __( 'Visual renderer: gotenberg, imagick or empty for none.', 'flx-ai' ) ),
				'webMonthCount'  => array( 'Int!', __( 'Web searches this month.', 'flx-ai' ), 'web_month_count' ),
				'providersReady' => array( 'Boolean!', __( 'A model provider key is saved.', 'flx-ai' ), 'providers_ready' ),
				'cloud'          => array( 'FlxAiCloud', __( 'Flux Cloud AI credits of the connected FluxWP account; null when not connected.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiCloud',
	array(
		'description' => __( 'Flux Cloud AI credits of the FluxWP account (1 credit = 0.001 USD of model cost).', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Cloud::class,
			array(
				'on'           => array( 'Boolean!', __( 'Flux Cloud offers AI credits.', 'flx-ai' ) ),
				'active'       => array( 'Boolean!', __( 'The default model runs on the account credits now.', 'flx-ai' ) ),
				'basic'        => array( 'Boolean!', __( 'The plan has basic models only.', 'flx-ai' ) ),
				'monthly'      => array( 'Int!', __( 'Monthly credits of the plan; -1 unlimited.', 'flx-ai' ) ),
				'used'         => array( 'Int!', __( 'Monthly credits used this month (all sites of the account).', 'flx-ai' ) ),
				'bonus'        => array( 'Int!', __( 'Bought credits left.', 'flx-ai' ) ),
				'left'         => array( 'Int!', __( 'Credits left; -1 unlimited.', 'flx-ai' ) ),
				'siteUsed'     => array( 'Int!', __( 'Credits this site used this month.', 'flx-ai' ), 'site_used' ),
				'resets'       => array( 'Int!', __( 'Unix time the monthly credits renew.', 'flx-ai' ) ),
				'buyUrl'       => array( 'String!', __( 'Where to buy credits or change the plan.', 'flx-ai' ), 'buy_url' ),
				'defaultModel' => array( 'String!', __( 'Model Flux Cloud suggests when none is chosen.', 'flx-ai' ), 'default_model' ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiModel',
	array(
		'description' => __( 'A language model of the catalog.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Model::class,
			array(
				'id'       => array( 'String!', __( 'Model id as saved (claude:… for Anthropic).', 'flx-ai' ) ),
				'name'     => array( 'String!', __( 'Name for display.', 'flx-ai' ) ),
				'family'   => array( 'String!', __( 'Model family.', 'flx-ai' ) ),
				'provider' => array( 'FlxAiModelProvider!', __( 'Provider that serves it.', 'flx-ai' ) ),
				'context'  => array( 'Int!', __( 'Context window in tokens; 0 when unknown.', 'flx-ai' ) ),
				'priceIn'  => array( 'Float!', __( 'USD per million input tokens.', 'flx-ai' ), 'price_in' ),
				'priceOut' => array( 'Float!', __( 'USD per million output tokens.', 'flx-ai' ), 'price_out' ),
				'tools'    => array( 'Boolean!', __( 'Supports tools.', 'flx-ai' ) ),
				'vision'   => array( 'Boolean!', __( 'Understands images.', 'flx-ai' ) ),
				'thinking' => array( 'Boolean!', __( 'Can think before answering.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiTestResult',
	array(
		'description' => __( 'Result of a connection test.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Test_Result::class,
			array(
				'text'  => array( 'String!', __( 'Model reply (model test); empty for the renderer test.', 'flx-ai' ) ),
				'ms'    => array( 'Int!', __( 'Round trip in milliseconds.', 'flx-ai' ) ),
				'via'   => array( 'String!', __( 'Renderer that drew the sample (renderer test).', 'flx-ai' ) ),
				'image' => array( 'String!', __( 'PNG data URI of the sample (renderer test).', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_query(
	'flxAiSettings',
	array(
		'type'        => 'FlxAiSettings!',
		'description' => __( 'Flux AI settings (without API keys).', 'flx-ai' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): FLXAI_Settings {
			flx_gql_cache_tag( FLXAI_Settings_Service::CACHE_TAG );
			return FLXAI_Settings_Service::get();
		},
	)
);

flx_gql_query(
	'flxAiSecrets',
	array(
		'type'        => '[FlxAiSecret!]!',
		'description' => __( 'The API keys Flux AI uses: which are set, never their values.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXAI_Settings_Service::CACHE_TAG );
			return FLXAI_Settings_Service::secrets();
		},
	)
);

flx_gql_query(
	'flxAiSettingsOptions',
	array(
		'type'        => 'FlxAiSettingsOptions!',
		'description' => __( 'Choices of the settings page: roles, search providers, image and video models.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): FLXAI_Settings_Options {
			flx_gql_cache_tag( FLXAI_Settings_Service::CACHE_TAG );
			return FLXAI_Settings_Service::options();
		},
	)
);

flx_gql_query(
	'flxAiModels',
	array(
		'type'        => '[FlxAiModel!]!',
		'description' => __( 'Searches the model catalog (the model picker).', 'flx-ai' ),
		'cap'         => 'manage_options',
		'args'        => array(
			'search' => array(
				'type'        => 'String',
				'description' => __( 'Part of an id, name or family.', 'flx-ai' ),
			),
			'first'  => array(
				'type'        => 'Int',
				'description' => __( 'Most models to return (1–200); 50 by default.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): array {
			$r = FLXAI_Settings_Service::models( flx_gql_str( $args['search'] ?? '' ), flx_gql_int( $args['first'] ?? null, 50 ) );
			return flxai_gql_ok( $r );
		},
	)
);

flx_gql_input(
	'FlxAiSecretInput',
	array(
		'description' => __( 'Writes or removes one API key.', 'flx-ai' ),
		'fields'      => array(
			'name'  => array(
				'type'        => 'String!',
				'description' => __( 'Setting name from flxAiSecrets, e.g. key_openrouter.', 'flx-ai' ),
			),
			'value' => array(
				'type'        => 'String',
				'description' => __( 'The new key. Empty leaves the saved key as it is.', 'flx-ai' ),
			),
			'clear' => array(
				'type'        => 'Boolean',
				'description' => __( 'Remove the saved key.', 'flx-ai' ),
			),
		),
	)
);
flx_gql_input(
	'FlxAiSourceModelInput',
	array(
		'description' => __( 'The model one plugin uses.', 'flx-ai' ),
		'fields'      => array(
			'source' => array(
				'type'        => 'String!',
				'description' => __( 'System id.', 'flx-ai' ),
			),
			'model'  => array(
				'type'        => 'String!',
				'description' => __( 'Model id; empty means the default model.', 'flx-ai' ),
			),
		),
	)
);

$flxai_settings_input = array();
foreach ( flxai_gql_settings_spec() as $flxai_name => $flxai_f ) {
	$flxai_settings_input[ $flxai_name ] = array(
		'type'        => $flxai_f[0],
		'description' => $flxai_f[1],
	);
}
$flxai_settings_input['sourceModels'] = array(
	'type'        => '[FlxAiSourceModelInput!]',
	'description' => __( 'The model each plugin uses; plugins you leave out keep theirs.', 'flx-ai' ),
);
$flxai_settings_input['secrets']      = array(
	'type'        => '[FlxAiSecretInput!]',
	'description' => __( 'API keys to write or remove; keys you leave out stay as they are.', 'flx-ai' ),
);
unset( $flxai_name, $flxai_f );

flx_gql_mutation(
	'flxAiSettingsUpdate',
	array(
		'description' => __( 'Saves Flux AI settings and API keys. Fields you leave out stay as they are; nothing is written when any value is invalid.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => $flxai_settings_input,
		'output'      => array(
			'settings' => array(
				'type'        => 'FlxAiSettings',
				'description' => __( 'Settings after the change.', 'flx-ai' ),
			),
			'secrets'  => array(
				'type'        => '[FlxAiSecret!]!',
				'description' => __( 'The keys after the change (set or not).', 'flx-ai' ),
				'resolve'     => static fn(): array => FLXAI_Settings_Service::secrets(),
			),
		),
		'resolve'     => static function ( array $input ) {
			$in = array();
			foreach ( $input as $name => $value ) {
				if ( 'clientMutationId' === $name || null === $value ) {
					continue;
				}
				$key = 'sourceModels' === $name ? 'source_models' : strtolower( (string) preg_replace( '/(?<!^)[A-Z]/', '_$0', (string) $name ) );
				if ( 'source_models' === $key ) {
					$map = array();
					foreach ( flx_gql_arr( $value ) as $row ) {
						$row                                   = flx_gql_arr( $row );
						$map[ flx_gql_str( $row['source'] ?? '' ) ] = flx_gql_str( $row['model'] ?? '' );
					}
					$value = $map;
				}
				$in[ $key ] = $value;
			}
			$r = FLXAI_Settings_Service::save( $in );
			return $r instanceof WP_Error ? $r : array( 'settings' => $r );
		},
	)
);
unset( $flxai_settings_input );

flx_gql_mutation(
	'flxAiModelTest',
	array(
		'description' => __( 'Sends a short test message to a model.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'model' => array(
				'type'        => 'String',
				'description' => __( 'Model id; empty uses the default model.', 'flx-ai' ),
			),
		),
		'output'      => array(
			'result' => array(
				'type'        => 'FlxAiTestResult',
				'description' => __( 'The reply and the round trip.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Settings_Service::test_model( flx_gql_str( $input['model'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'result' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiRenderTest',
	array(
		'description' => __( 'Draws a sample with the visual renderer. The values may be the ones in the form and not saved yet; an empty password uses the saved one.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'url'  => array(
				'type'        => 'String',
				'description' => __( 'Gotenberg address; empty tries the local renderer.', 'flx-ai' ),
			),
			'user' => array(
				'type'        => 'String',
				'description' => __( 'Gotenberg user name.', 'flx-ai' ),
			),
			'pass' => array(
				'type'        => 'String',
				'description' => __( 'Gotenberg password; empty uses the saved one.', 'flx-ai' ),
			),
		),
		'output'      => array(
			'result' => array(
				'type'        => 'FlxAiTestResult',
				'description' => __( 'The sample image and the time it took.', 'flx-ai' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$pass = flx_gql_str( $input['pass'] ?? '' );
			$r    = FLXAI_Settings_Service::test_render( flx_gql_str( $input['url'] ?? '' ), flx_gql_str( $input['user'] ?? '' ), '' !== $pass ? $pass : FLXAI_Cast::str( flxai_setting( 'key_render', '' ) ) );
			return $r instanceof WP_Error ? $r : array( 'result' => $r );
		},
	)
);
