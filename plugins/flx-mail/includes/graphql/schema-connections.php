<?php
/**
 * Flux Mail GraphQL part 2: WhatsApp / SMS connections and the WhatsApp Cloud setup.
 * Loaded from flxi_gql_register() on flx_gql_register.
 *
 * Every field and mutation calls FLXI_Connection_Service, the same service the classic
 * Connections page and the REST routes use. Secrets (API key, token, App Secret, password)
 * are write-only: an empty input keeps the stored value, "__clear__" removes it.
 *
 * The WhatsApp Cloud webhook (GET + POST /wp-json/flxi/v1/wacloud/{id}/webhook) stays REST because
 * Meta posts to that fixed URL. flxMailWacloudSetup returns the URL and the verify value for the
 * Meta app form; the App Secret is never returned.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the part-2 schema.
 */
function flxi_gql_register_connections(): void {
	$cap = flxi_gql_cap();

	flx_gql_type(
		'FlxMailConnectionField',
		array(
			'description' => __( 'One field of a connection form. The classic form renders the same definition.', 'flx-mail' ),
			'fields'      => array(
				'name'         => flxi_gql_f( 'String!', __( 'Input and output field name on FlxMailConnection. Empty for a heading.', 'flx-mail' ) ),
				'kind'         => flxi_gql_f( 'String!', __( 'text, url, number, password, select, checkbox or heading.', 'flx-mail' ) ),
				'label'        => flxi_gql_f( 'String!', __( 'Label.', 'flx-mail' ) ),
				'help'         => flxi_gql_f( 'String!', __( 'Help text, empty when there is none.', 'flx-mail' ) ),
				'required'     => flxi_gql_f( 'Boolean!', __( 'A value is required.', 'flx-mail' ) ),
				'placeholder'  => flxi_gql_f( 'String!', __( 'Placeholder.', 'flx-mail' ) ),
				'defaultValue' => flxi_gql_f( 'String!', __( 'Default for a new connection.', 'flx-mail' ) ),
				'options'      => flxi_gql_f( '[FlxMailChoice!]!', __( 'Choices of a select field.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailConnectionTypeInfo',
		array(
			'description' => __( 'A connection type this site offers.', 'flx-mail' ),
			'fields'      => array(
				'type'      => flxi_gql_f( 'String!', __( 'Type key: evolution, flxwpapi, wacloud or netgsm.', 'flx-mail' ) ),
				'channel'   => flxi_gql_f( 'FlxMailChannel!', __( 'Channel.', 'flx-mail' ) ),
				'label'     => flxi_gql_f( 'String!', __( 'Name.', 'flx-mail' ) ),
				'help'      => flxi_gql_f( 'String!', __( 'What the type is and where to find its values.', 'flx-mail' ) ),
				'scenarios' => flxi_gql_f( '[String!]!', __( 'Test scenarios the type supports.', 'flx-mail' ) ),
				'evolution' => flxi_gql_f( 'Boolean!', __( 'Talks the Evolution API (QR connect, restart, logout).', 'flx-mail' ) ),
				'cloud'     => flxi_gql_f( 'Boolean!', __( 'Meta WhatsApp Cloud API.', 'flx-mail' ) ),
				'fields'    => flxi_gql_f( '[FlxMailConnectionField!]!', __( 'Form fields in display order.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailConnectionMeta',
		array(
			'description' => __( 'What the Connections screen needs besides the connections.', 'flx-mail' ),
			'fields'      => array(
				'flxsActive'     => flxi_gql_f( 'Boolean!', __( 'Flux Certificates is active and sends through these connections.', 'flx-mail' ) ),
				'sampleImageUrl' => flxi_gql_f( 'String!', __( 'Sample image used by the image test.', 'flx-mail' ) ),
				'sampleAudioUrl' => flxi_gql_f( 'String!', __( 'Sample audio used by the audio test.', 'flx-mail' ) ),
				'types'          => flxi_gql_f( '[FlxMailConnectionTypeInfo!]!', __( 'Types a new connection can be.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailWaState',
		array(
			'description' => __( 'State of a WhatsApp number as last seen.', 'flx-mail' ),
			'fields'      => array(
				'state'   => flxi_gql_f( 'String!', __( 'open, connecting, close, missing, error or empty when unknown.', 'flx-mail' ) ),
				'me'      => flxi_gql_f( 'String!', __( 'The linked phone number.', 'flx-mail' ) ),
				'name'    => flxi_gql_f( 'String!', __( 'Profile or verified name.', 'flx-mail' ) ),
				'quality' => flxi_gql_f( 'String!', __( 'Cloud API quality rating, empty for other types.', 'flx-mail' ) ),
				'at'      => flxi_gql_f( 'FlxDateTime', __( 'When the state was read.', 'flx-mail' ) ),
				'error'   => flxi_gql_f( 'String!', __( 'Last error, empty when there is none.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailConnectionTest',
		array(
			'description' => __( 'The last test of a connection.', 'flx-mail' ),
			'fields'      => array(
				'ok'    => flxi_gql_f( 'Boolean!', __( 'The test passed.', 'flx-mail' ) ),
				'error' => flxi_gql_f( 'String!', __( 'Error text, empty when it passed.', 'flx-mail' ) ),
				'at'    => flxi_gql_f( 'FlxDateTime', __( 'When it ran.', 'flx-mail' ) ),
			),
		)
	);

	$conn = array(
		'databaseId'       => flxi_gql_f( 'Int!', __( 'Connection id.', 'flx-mail' ) ),
		'name'             => flxi_gql_f( 'String!', __( 'Name.', 'flx-mail' ) ),
		'type'             => flxi_gql_f( 'String!', __( 'Type key.', 'flx-mail' ) ),
		'typeLabel'        => flxi_gql_f( 'String!', __( 'Type name.', 'flx-mail' ) ),
		'channel'          => flxi_gql_f( 'FlxMailChannel!', __( 'Channel.', 'flx-mail' ) ),
		'active'           => flxi_gql_f( 'Boolean!', __( 'Used for sending.', 'flx-mail' ) ),
		'isDefault'        => flxi_gql_f( 'Boolean!', __( 'Default for its channel.', 'flx-mail' ) ),
		'origin'           => flxi_gql_f( 'String!', __( 'Where it was migrated from, empty when it was created here.', 'flx-mail' ) ),
		'scenarios'        => flxi_gql_f( '[String!]!', __( 'Test scenarios it supports.', 'flx-mail' ) ),
		'appSecretMissing' => flxi_gql_f( 'Boolean!', __( 'Cloud API connection without an App Secret: incoming webhooks are rejected.', 'flx-mail' ) ),
		'createdAt'        => flxi_gql_f( 'FlxDateTime', __( 'Created.', 'flx-mail' ) ),
		'updatedAt'        => flxi_gql_f( 'FlxDateTime', __( 'Last saved.', 'flx-mail' ) ),
		'lastTest'         => flxi_gql_f( 'FlxMailConnectionTest', __( 'Last test, null when it was never tested.', 'flx-mail' ) ),
		'waState'          => flxi_gql_f( 'FlxMailWaState', __( 'Last seen WhatsApp state, null for SMS.', 'flx-mail' ) ),
	);
	$input = array(
		'id'        => flxi_gql_f( 'Int', __( 'Connection to change. Omit to create one.', 'flx-mail' ) ),
		'type'      => flxi_gql_f( 'String', __( 'Type key for a new connection. Ignored on an update.', 'flx-mail' ) ),
		'name'      => flxi_gql_f( 'String', __( 'Name.', 'flx-mail' ) ),
		'active'    => flxi_gql_f( 'Boolean', __( 'Used for sending.', 'flx-mail' ) ),
		'isDefault' => flxi_gql_f( 'Boolean', __( 'Make it the default of its channel.', 'flx-mail' ) ),
		'copyFrom'  => flxi_gql_f( 'Int', __( 'New Evolution or Flux WPAPI connection: reuse the server address and API key of this one.', 'flx-mail' ) ),
	);
	foreach ( FLXI_Connection_Service::config_map() as $name => $row ) {
		$kind          = $row[1];
		/* translators: %s: setting name */
		$desc          = sprintf( __( 'Connection setting "%s". Connection types lists which types use it.', 'flx-mail' ), $name );
		$conn[ $name ] = flxi_gql_f( 'bool' === $kind ? 'Boolean!' : ( 'int' === $kind ? 'Int!' : 'String!' ), $desc );
		$input[ $name ] = flxi_gql_f( 'bool' === $kind ? 'Boolean' : ( 'int' === $kind ? 'Int' : 'String' ), $desc . ' ' . __( 'Omitted keeps the stored value.', 'flx-mail' ) );
	}
	foreach ( FLXI_Connection_Service::secret_map() as $name => $key ) {
		unset( $key );
		$conn[ $name ]  = flxi_gql_f( 'FlxMailSecret!', __( 'Write-only secret. The value is never returned.', 'flx-mail' ) );
		$input[ $name ] = flxi_gql_f( 'String', __( 'Empty keeps the stored value. __clear__ removes it.', 'flx-mail' ) );
	}
	flx_gql_type( 'FlxMailConnection', array( 'description' => __( 'A WhatsApp or SMS connection. Secrets are masked.', 'flx-mail' ), 'fields' => $conn ) );
	flx_gql_connection( 'FlxMailConnection', __( 'A page of connections.', 'flx-mail' ) );

	flx_gql_type(
		'FlxMailWaInstance',
		array(
			'description' => __( 'A number on the same WhatsApp server.', 'flx-mail' ),
			'fields'      => array(
				'name'  => flxi_gql_f( 'String!', __( 'Instance name.', 'flx-mail' ) ),
				'state' => flxi_gql_f( 'String!', __( 'Connection state.', 'flx-mail' ) ),
				'me'    => flxi_gql_f( 'String!', __( 'Linked phone number.', 'flx-mail' ) ),
				'used'  => flxi_gql_f( 'Int!', __( 'Id of the connection here that uses it, 0 when none.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailWaGroup',
		array(
			'description' => __( 'A WhatsApp group the number is in.', 'flx-mail' ),
			'fields'      => array(
				'id'       => flxi_gql_f( 'String!', __( 'Group id.', 'flx-mail' ) ),
				'name'     => flxi_gql_f( 'String!', __( 'Name.', 'flx-mail' ) ),
				'size'     => flxi_gql_f( 'Int!', __( 'Members.', 'flx-mail' ) ),
				'announce' => flxi_gql_f( 'Boolean!', __( 'Only admins can write.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailWaServerDb',
		array(
			'description' => __( 'Database of a Flux WPAPI server. The password is never returned.', 'flx-mail' ),
			'fields'      => array(
				'driver'      => flxi_gql_f( 'String!', __( 'file or mysql.', 'flx-mail' ) ),
				'host'        => flxi_gql_f( 'String!', __( 'Host.', 'flx-mail' ) ),
				'port'        => flxi_gql_f( 'Int!', __( 'Port.', 'flx-mail' ) ),
				'database'    => flxi_gql_f( 'String!', __( 'Database.', 'flx-mail' ) ),
				'user'        => flxi_gql_f( 'String!', __( 'User.', 'flx-mail' ) ),
				'passwordSet' => flxi_gql_f( 'Boolean!', __( 'A password is stored on the server.', 'flx-mail' ) ),
				'prefix'      => flxi_gql_f( 'String!', __( 'Table prefix.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailWaServer',
		array(
			'description' => __( 'Flux WPAPI server information and settings.', 'flx-mail' ),
			'fields'      => array(
				'version'        => flxi_gql_f( 'String!', __( 'Bridge version.', 'flx-mail' ) ),
				'baileys'        => flxi_gql_f( 'String!', __( 'Baileys version.', 'flx-mail' ) ),
				'node'           => flxi_gql_f( 'String!', __( 'Node.js version.', 'flx-mail' ) ),
				'store'          => flxi_gql_f( 'String!', __( 'Session store.', 'flx-mail' ) ),
				'storeError'     => flxi_gql_f( 'String!', __( 'Store error, empty when it works.', 'flx-mail' ) ),
				'lastKeepAlive'  => flxi_gql_f( 'FlxDateTime', __( 'Last keep-alive ping.', 'flx-mail' ) ),
				'keepAliveOk'    => flxi_gql_f( 'Boolean!', __( 'The last keep-alive worked.', 'flx-mail' ) ),
				'keepAliveError' => flxi_gql_f( 'String!', __( 'Keep-alive error.', 'flx-mail' ) ),
				'ffmpeg'         => flxi_gql_f( 'Boolean!', __( 'ffmpeg is available for voice notes.', 'flx-mail' ) ),
				'publicUrl'      => flxi_gql_f( 'String!', __( 'Public address of the bridge.', 'flx-mail' ) ),
				'keepAlive'      => flxi_gql_f( 'Boolean!', __( 'Keep-alive is on.', 'flx-mail' ) ),
				'dbLocked'       => flxi_gql_f( 'Boolean!', __( 'The database comes from environment variables and cannot be changed here.', 'flx-mail' ) ),
				'db'             => flxi_gql_f( 'FlxMailWaServerDb!', __( 'Database settings.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_input(
		'FlxMailWaServerDbInput',
		array(
			'description' => __( 'Database settings to send to the bridge.', 'flx-mail' ),
			'fields'      => array(
				'driver'   => flxi_gql_f( 'String', __( 'file or mysql.', 'flx-mail' ) ),
				'host'     => flxi_gql_f( 'String', __( 'Host.', 'flx-mail' ) ),
				'port'     => flxi_gql_f( 'Int', __( 'Port.', 'flx-mail' ) ),
				'database' => flxi_gql_f( 'String', __( 'Database.', 'flx-mail' ) ),
				'user'     => flxi_gql_f( 'String', __( 'User.', 'flx-mail' ) ),
				'password' => flxi_gql_f( 'String', __( 'Write-only. Empty keeps the stored password. It goes to the bridge and is not kept in WordPress.', 'flx-mail' ) ),
				'prefix'   => flxi_gql_f( 'String', __( 'Table prefix.', 'flx-mail' ) ),
			),
		)
	);

	flx_gql_type(
		'FlxMailWacloudPhone',
		array(
			'description' => __( 'Phone number details Meta reports for a Cloud API connection.', 'flx-mail' ),
			'fields'      => array(
				'displayPhoneNumber'     => flxi_gql_f( 'String!', __( 'Number shown to people.', 'flx-mail' ) ),
				'verifiedName'           => flxi_gql_f( 'String!', __( 'Verified business name.', 'flx-mail' ) ),
				'qualityRating'          => flxi_gql_f( 'String!', __( 'Quality rating.', 'flx-mail' ) ),
				'nameStatus'             => flxi_gql_f( 'String!', __( 'Display name review status.', 'flx-mail' ) ),
				'codeVerificationStatus' => flxi_gql_f( 'String!', __( 'Number verification status.', 'flx-mail' ) ),
				'platformType'           => flxi_gql_f( 'String!', __( 'Platform type.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailWacloudSetup',
		array(
			'description' => __( 'What to enter in the Meta app so WhatsApp Cloud posts to this site. Manager only. The App Secret is never returned.', 'flx-mail' ),
			'fields'      => array(
				'webhookUrl'         => flxi_gql_f( 'String!', __( 'Callback URL for the Meta webhook. Stays a REST route: Meta posts to this fixed address.', 'flx-mail' ) ),
				'webhookVerifyValue' => flxi_gql_f( 'String!', __( 'The verify token to paste into Meta. It is a handshake string, not a credential.', 'flx-mail' ) ),
				'signed'             => flxi_gql_f( 'Boolean!', __( 'An App Secret is stored, so incoming events are checked.', 'flx-mail' ) ),
				'hasWaba'            => flxi_gql_f( 'Boolean!', __( 'A WABA id is stored, so the template list works.', 'flx-mail' ) ),
				'openChats'          => flxi_gql_f( 'Int!', __( 'People who wrote in the last 24 hours (free-form replies are allowed).', 'flx-mail' ) ),
				'error'              => flxi_gql_f( 'String!', __( 'Why the number details could not be read, empty when they could.', 'flx-mail' ) ),
				'phone'              => flxi_gql_f( 'FlxMailWacloudPhone', __( 'Number details, null when Meta could not be reached.', 'flx-mail' ) ),
				'lastVerifyAt'       => flxi_gql_f( 'FlxDateTime', __( 'Last webhook verification by Meta.', 'flx-mail' ) ),
				'lastEventAt'        => flxi_gql_f( 'FlxDateTime', __( 'Last event Meta posted.', 'flx-mail' ) ),
				'badSignatureAt'     => flxi_gql_f( 'FlxDateTime', __( 'Last event rejected for a wrong signature.', 'flx-mail' ) ),
				'noSecretAt'         => flxi_gql_f( 'FlxDateTime', __( 'Last event rejected because no App Secret is stored.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_type(
		'FlxMailWacloudTemplate',
		array(
			'description' => __( 'A WhatsApp message template from the Meta account.', 'flx-mail' ),
			'fields'      => array(
				'name'     => flxi_gql_f( 'String!', __( 'Template name.', 'flx-mail' ) ),
				'lang'     => flxi_gql_f( 'String!', __( 'Language code.', 'flx-mail' ) ),
				'status'   => flxi_gql_f( 'String!', __( 'approved, pending, rejected …', 'flx-mail' ) ),
				'category' => flxi_gql_f( 'String!', __( 'Category.', 'flx-mail' ) ),
				'body'     => flxi_gql_f( 'String!', __( 'Body text with {{1}} placeholders.', 'flx-mail' ) ),
				'params'   => flxi_gql_f( 'Int!', __( 'Number of placeholders.', 'flx-mail' ) ),
			),
		)
	);
	flx_gql_connection( 'FlxMailWacloudTemplate', __( 'A page of Cloud API templates.', 'flx-mail' ) );
	flx_gql_input(
		'FlxMailWacloudTemplateInput',
		array(
			'description' => __( 'The template a test sends.', 'flx-mail' ),
			'fields'      => array(
				'name'   => flxi_gql_f( 'String!', __( 'Template name.', 'flx-mail' ) ),
				'lang'   => flxi_gql_f( 'String', __( 'Language code. Default tr.', 'flx-mail' ) ),
				'params' => flxi_gql_f( '[String!]', __( 'Values for {{1}}, {{2}} …', 'flx-mail' ) ),
			),
		)
	);

	$id_arg = array( 'id' => flxi_gql_f( 'Int!', __( 'Connection id.', 'flx-mail' ) ) );

	flx_gql_query(
		'flxMailConnectionMeta',
		array(
			'type'        => 'FlxMailConnectionMeta!',
			'description' => __( 'Connection types, their form fields and the sample test files.', 'flx-mail' ),
			'cap'         => $cap,
			'resolve'     => static fn() => FLXI_Connection_Service::meta(),
		)
	);
	flx_gql_query(
		'flxMailConnections',
		array(
			'type'        => 'FlxMailConnectionConnection!',
			'description' => __( 'WhatsApp and SMS connections.', 'flx-mail' ),
			'cap'         => $cap,
			'args'        => flx_gql_connection_args(
				array(
					'channel'    => flxi_gql_f( 'FlxMailChannel', __( 'Only this channel.', 'flx-mail' ) ),
					'activeOnly' => flxi_gql_f( 'Boolean', __( 'Only active connections.', 'flx-mail' ) ),
				)
			),
			'resolve'     => static function ( $root, array $args ): array {
				unset( $root );
				$args = flx_gql_arr( $args );
				$rows = FLXI_Connection_Service::list( flx_gql_str( $args['channel'] ?? '' ), ! empty( $args['activeOnly'] ) );
				return flx_gql_paginate( $args, static fn( int $offset, int $limit ): array => array( 'items' => array_slice( $rows, $offset, $limit ), 'total' => count( $rows ) ) );
			},
		)
	);
	flx_gql_query(
		'flxMailConnection',
		array(
			'type'        => 'FlxMailConnection',
			'description' => __( 'One connection, null when it does not exist.', 'flx-mail' ),
			'cap'         => $cap,
			'args'        => $id_arg,
			'resolve'     => static function ( $root, array $args ) {
				unset( $root );
				return FLXI_Connection_Service::one( flx_gql_int( flx_gql_arr( $args )['id'] ?? 0 ) );
			},
		)
	);
	flx_gql_query(
		'flxMailWaState',
		array(
			'type'        => 'FlxMailWaState!',
			'description' => __( 'State of a WhatsApp number. Cached for a minute; fresh asks the server.', 'flx-mail' ),
			'cap'         => $cap,
			'args'        => array_merge( $id_arg, array( 'fresh' => flxi_gql_f( 'Boolean', __( 'Ask the server instead of the cache.', 'flx-mail' ) ) ) ),
			'resolve'     => static function ( $root, array $args ) {
				unset( $root );
				$args = flx_gql_arr( $args );
				return FLXI_Connection_Service::wa_state( flx_gql_int( $args['id'] ?? 0 ), ! empty( $args['fresh'] ) );
			},
		)
	);
	flx_gql_query(
		'flxMailWaInstances',
		array(
			'type'        => '[FlxMailWaInstance!]!',
			'description' => __( 'Numbers on the same Evolution or Flux WPAPI server.', 'flx-mail' ),
			'cap'         => $cap,
			'args'        => $id_arg,
			'resolve'     => static function ( $root, array $args ) {
				unset( $root );
				return FLXI_Connection_Service::wa_instances( flx_gql_int( flx_gql_arr( $args )['id'] ?? 0 ) );
			},
		)
	);
	flx_gql_query(
		'flxMailWaGroups',
		array(
			'type'        => '[FlxMailWaGroup!]!',
			'description' => __( 'WhatsApp groups the number is in. Cached for ten minutes; fresh asks the server.', 'flx-mail' ),
			'cap'         => $cap,
			'args'        => array_merge( $id_arg, array( 'fresh' => flxi_gql_f( 'Boolean', __( 'Ask the server instead of the cache.', 'flx-mail' ) ) ) ),
			'resolve'     => static function ( $root, array $args ) {
				unset( $root );
				$args = flx_gql_arr( $args );
				return FLXI_Connection_Service::wa_groups( flx_gql_int( $args['id'] ?? 0 ), ! empty( $args['fresh'] ) );
			},
		)
	);
	flx_gql_query(
		'flxMailWaServer',
		array(
			'type'        => 'FlxMailWaServer!',
			'description' => __( 'Flux WPAPI server information. No key or password is returned.', 'flx-mail' ),
			'cap'         => $cap,
			'args'        => $id_arg,
			'resolve'     => static function ( $root, array $args ) {
				unset( $root );
				return FLXI_Connection_Service::wa_server_get( flx_gql_int( flx_gql_arr( $args )['id'] ?? 0 ) );
			},
		)
	);
	flx_gql_query(
		'flxMailWacloudSetup',
		array(
			'type'        => 'FlxMailWacloudSetup',
			'description' => __( 'Webhook URL, verify value and live status of a Cloud API connection. Null when it is not a Cloud API connection.', 'flx-mail' ),
			'cap'         => $cap,
			'args'        => $id_arg,
			'resolve'     => static function ( $root, array $args ) {
				unset( $root );
				return FLXI_Connection_Service::wacloud_setup( flx_gql_int( flx_gql_arr( $args )['id'] ?? 0 ) );
			},
		)
	);
	flx_gql_query(
		'flxMailWacloudTemplates',
		array(
			'type'        => 'FlxMailWacloudTemplateConnection!',
			'description' => __( 'Message templates of the Meta account (needs the WABA id).', 'flx-mail' ),
			'cap'         => $cap,
			'args'        => flx_gql_connection_args( $id_arg ),
			'resolve'     => static function ( $root, array $args ) {
				unset( $root );
				$args = flx_gql_arr( $args );
				$rows = FLXI_Connection_Service::wacloud_templates( flx_gql_int( $args['id'] ?? 0 ) );
				if ( is_wp_error( $rows ) ) {
					return $rows;
				}
				return flx_gql_paginate( $args, static fn( int $offset, int $limit ): array => array( 'items' => array_slice( $rows, $offset, $limit ), 'total' => count( $rows ) ) );
			},
		)
	);

	flx_gql_mutation(
		'flxMailConnectionSave',
		array(
			'description' => __( 'Creates or changes a connection. A secret left empty keeps the stored one; __clear__ removes it.', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => $input,
			'output'      => array( 'connection' => flxi_gql_f( 'FlxMailConnection', __( 'The connection after the change.', 'flx-mail' ) ) ),
			'resolve'     => static function ( array $in ) {
				$in  = flx_gql_arr( $in );
				$id  = flx_gql_int( $in['id'] ?? 0 );
				$res = FLXI_Connection_Service::save_input( $id, flx_gql_str( $in['type'] ?? '' ), $in, flx_gql_int( $in['copyFrom'] ?? 0 ) );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				return array( 'connection' => FLXI_Connection_Service::one( $res ) );
			},
		)
	);
	flx_gql_mutation(
		'flxMailConnectionDelete',
		array(
			'description' => __( 'Deletes a connection. remote also deletes the number on the Evolution or Flux WPAPI server.', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => array_merge( $id_arg, array( 'remote' => flxi_gql_f( 'Boolean', __( 'Also delete the number on the server.', 'flx-mail' ) ) ) ),
			'output'      => array( 'ok' => flxi_gql_f( 'Boolean', __( 'It was deleted.', 'flx-mail' ) ) ),
			'resolve'     => static function ( array $in ) {
				$in  = flx_gql_arr( $in );
				$res = FLXI_Connection_Service::delete( flx_gql_int( $in['id'] ?? 0 ), ! empty( $in['remote'] ) );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				return array( 'ok' => true );
			},
		)
	);
	flx_gql_mutation(
		'flxMailConnectionSetDefault',
		array(
			'description' => __( 'Makes a connection the default of its channel.', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => $id_arg,
			'output'      => array( 'connection' => flxi_gql_f( 'FlxMailConnection', __( 'The connection after the change.', 'flx-mail' ) ) ),
			'resolve'     => static function ( array $in ) {
				$id  = flx_gql_int( flx_gql_arr( $in )['id'] ?? 0 );
				$res = FLXI_Connection_Service::set_default( $id );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				return array( 'connection' => FLXI_Connection_Service::one( $id ) );
			},
		)
	);
	flx_gql_mutation(
		'flxMailConnectionTest',
		array(
			'description' => __( 'Checks a connection, or sends a test message (text, image, audio, document or Cloud API template).', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => array_merge(
				$id_arg,
				array(
					'to'       => flxi_gql_f( 'String', __( 'Recipient number.', 'flx-mail' ) ),
					'scenario' => flxi_gql_f( 'String', __( 'check, text, image, audio, document or template. Empty is check.', 'flx-mail' ) ),
					'text'     => flxi_gql_f( 'String', __( 'Message text for the text scenario.', 'flx-mail' ) ),
					'caption'  => flxi_gql_f( 'String', __( 'Caption for image and document.', 'flx-mail' ) ),
					'template' => flxi_gql_f( 'FlxMailWacloudTemplateInput', __( 'Template for the template scenario.', 'flx-mail' ) ),
				)
			),
			'output'      => array(
				'ok'         => flxi_gql_f( 'Boolean', __( 'The test passed.', 'flx-mail' ) ),
				'error'      => flxi_gql_f( 'String', __( 'Error text, empty when it passed.', 'flx-mail' ) ),
				'connection' => flxi_gql_f( 'FlxMailConnection', __( 'The connection with its new last test.', 'flx-mail' ) ),
			),
			'resolve'     => static function ( array $in ): array {
				$in  = flx_gql_arr( $in );
				$res = FLXI_Connection_Service::test( $in );
				return array_merge( $res, array( 'connection' => FLXI_Connection_Service::one( flx_gql_int( $in['id'] ?? 0 ) ) ) );
			},
		)
	);

	$wa_state = array( 'state' => flxi_gql_f( 'FlxMailWaState', __( 'State after the call.', 'flx-mail' ) ) );
	flx_gql_mutation(
		'flxMailWaConnect',
		array(
			'description' => __( 'Asks the server for a QR code, or a pairing code when a phone number is given. Creates the instance when it does not exist.', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => array_merge( $id_arg, array( 'number' => flxi_gql_f( 'String', __( 'Phone number to pair with a code instead of a QR.', 'flx-mail' ) ) ) ),
			'output'      => array(
				'state'   => flxi_gql_f( 'String', __( 'open, connecting …', 'flx-mail' ) ),
				'qr'      => flxi_gql_f( 'String', __( 'QR image as a data URI, empty when there is none.', 'flx-mail' ) ),
				'pairing' => flxi_gql_f( 'String', __( 'Pairing code, empty when there is none.', 'flx-mail' ) ),
				'me'      => flxi_gql_f( 'String', __( 'Linked phone number once open.', 'flx-mail' ) ),
				'name'    => flxi_gql_f( 'String', __( 'Profile name once open.', 'flx-mail' ) ),
				'error'   => flxi_gql_f( 'String', __( 'Error text.', 'flx-mail' ) ),
				'at'      => flxi_gql_f( 'FlxDateTime', __( 'When the state was read.', 'flx-mail' ) ),
			),
			'resolve'     => static function ( array $in ) {
				$in = flx_gql_arr( $in );
				return FLXI_Connection_Service::wa_connect( flx_gql_int( $in['id'] ?? 0 ), sanitize_text_field( flx_gql_str( $in['number'] ?? '' ) ) );
			},
		)
	);
	flx_gql_mutation(
		'flxMailWaRestart',
		array(
			'description' => __( 'Restarts the instance on the server.', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => $id_arg,
			'output'      => $wa_state,
			'resolve'     => static function ( array $in ) {
				$res = FLXI_Connection_Service::wa_restart( flx_gql_int( flx_gql_arr( $in )['id'] ?? 0 ) );
				return is_wp_error( $res ) ? $res : array( 'state' => $res );
			},
		)
	);
	flx_gql_mutation(
		'flxMailWaLogout',
		array(
			'description' => __( 'Logs the number out. The instance stays on the server; linking it again needs a QR scan.', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => $id_arg,
			'output'      => array_merge( array( 'ok' => flxi_gql_f( 'Boolean', __( 'It was logged out.', 'flx-mail' ) ) ), $wa_state ),
			'resolve'     => static function ( array $in ) {
				return FLXI_Connection_Service::wa_logout( flx_gql_int( flx_gql_arr( $in )['id'] ?? 0 ) );
			},
		)
	);
	flx_gql_mutation(
		'flxMailWaServerUpdate',
		array(
			'description' => __( 'Changes the Flux WPAPI server settings, or with test only tries the MySQL login. The database password is write-only and is not kept in WordPress.', 'flx-mail' ),
			'cap'         => $cap,
			'input'       => array_merge(
				$id_arg,
				array(
					'publicUrl' => flxi_gql_f( 'String', __( 'Public address of the bridge.', 'flx-mail' ) ),
					'keepAlive' => flxi_gql_f( 'Boolean', __( 'Keep-alive.', 'flx-mail' ) ),
					'db'        => flxi_gql_f( 'FlxMailWaServerDbInput', __( 'Database settings.', 'flx-mail' ) ),
					'test'      => flxi_gql_f( 'Boolean', __( 'Only try the MySQL login; save nothing.', 'flx-mail' ) ),
				)
			),
			'output'      => array(
				'ok'     => flxi_gql_f( 'Boolean', __( 'It worked.', 'flx-mail' ) ),
				'error'  => flxi_gql_f( 'String', __( 'Database test error, empty when it worked.', 'flx-mail' ) ),
				'server' => flxi_gql_f( 'FlxMailWaServer', __( 'Server settings after a save. Null for a test.', 'flx-mail' ) ),
			),
			'resolve'     => static function ( array $in ) {
				$in = flx_gql_arr( $in );
				return FLXI_Connection_Service::wa_server_save( flx_gql_int( $in['id'] ?? 0 ), $in );
			},
		)
	);
}
