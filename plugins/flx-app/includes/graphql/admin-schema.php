<?php
/**
 * Flux App admin GraphQL schema: the "Application" settings pages (General, Appearance, Applications, Modules, Menu,
 * OAuth clients, All access) and the personal "App access" page. Reads and writes through
 * FLXAPP_Admin_Service. Appearance carries the open `flxapp_app` option document and the resolved config as FlxJSON
 * (the same documents REST and the app read); everything else is typed.
 *
 * @package flx-app
 */

defined( 'ABSPATH' ) || exit;

flx_gql_enum(
	'FlxAppClientKind',
	array(
		'description' => __( 'Where an OAuth client comes from.', 'flx-app' ),
		'values'      => array(
			'BUILTIN' => array( 'value' => 'BUILTIN' ),
			'DYNAMIC' => array( 'value' => 'DYNAMIC' ),
			'MANUAL'  => array( 'value' => 'MANUAL' ),
		),
	)
);
flx_gql_enum(
	'FlxAppAccessKind',
	array(
		'description' => __( 'A personal API key or an app session.', 'flx-app' ),
		'values'      => array(
			'KEY'     => array( 'value' => 'KEY' ),
			'SESSION' => array( 'value' => 'SESSION' ),
		),
	)
);

flx_gql_type(
	'FlxAppChoice',
	array(
		'description' => __( 'A key with a label.', 'flx-app' ),
		'fields'      => array(
			'key'   => flxapp_gql_f( 'String!', __( 'Key.', 'flx-app' ) ),
			'label' => flxapp_gql_f( 'String!', __( 'Label.', 'flx-app' ) ),
		),
	)
);

/* ───────── General ───────── */

flx_gql_type(
	'FlxAppAdminSettings',
	array(
		'description' => __( 'Sign-in, session and panel settings of the app.', 'flx-app' ),
		'fields'      => array(
			'accessMinutes'   => flxapp_gql_f( 'Int!', __( 'Access token lifetime in minutes (5 to 1440).', 'flx-app' ) ),
			'refreshDays'     => flxapp_gql_f( 'Int!', __( 'Session lifetime in days when unused (1 to 365).', 'flx-app' ) ),
			'dynamicClients'  => flxapp_gql_f( 'Boolean!', __( 'MCP clients may register themselves.', 'flx-app' ) ),
			'webSession'      => flxapp_gql_f( 'Boolean!', __( 'The app may open admin pages with a one-time sign-in link.', 'flx-app' ) ),
			'otpEnabled'      => flxapp_gql_f( 'Boolean!', __( 'Donors may sign in with an emailed or phone code.', 'flx-app' ) ),
			'otpCreate'       => flxapp_gql_f( 'Boolean!', __( 'Create a customer account for a confirmed email.', 'flx-app' ) ),
			'otpStaff'        => flxapp_gql_f( 'Boolean!', __( 'Staff may sign in with a code too.', 'flx-app' ) ),
			'phoneSelfVerify' => flxapp_gql_f( 'Boolean!', __( 'A signed-in donor may confirm a phone number.', 'flx-app' ) ),
			'keysCap'         => flxapp_gql_f( 'String!', __( 'Who can create API keys (a capability).', 'flx-app' ) ),
			'keysCapChoices'  => flxapp_gql_f( '[FlxAppChoice!]!', __( 'The choices for keysCap.', 'flx-app' ) ),
			'version'         => flxapp_gql_f( 'String!', __( 'Flux App version.', 'flx-app' ) ),
		),
	)
);
flx_gql_query(
	'flxAppAdminSettings',
	array(
		'type'        => 'FlxAppAdminSettings!',
		'description' => __( 'The General settings.', 'flx-app' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): array => FLXAPP_Admin_Service::settings(),
	)
);
flx_gql_mutation(
	'flxAppAdminSettingsSave',
	array(
		'description' => __( 'Saves the General settings; only the sent fields change.', 'flx-app' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'accessMinutes'   => flxapp_gql_f( 'Int', __( 'Access token lifetime in minutes (5 to 1440).', 'flx-app' ) ),
			'refreshDays'     => flxapp_gql_f( 'Int', __( 'Session lifetime in days when unused (1 to 365).', 'flx-app' ) ),
			'dynamicClients'  => flxapp_gql_f( 'Boolean', __( 'MCP clients may register themselves.', 'flx-app' ) ),
			'webSession'      => flxapp_gql_f( 'Boolean', __( 'The app may open admin pages with a one-time sign-in link.', 'flx-app' ) ),
			'otpEnabled'      => flxapp_gql_f( 'Boolean', __( 'Donors may sign in with an emailed or phone code.', 'flx-app' ) ),
			'otpCreate'       => flxapp_gql_f( 'Boolean', __( 'Create a customer account for a confirmed email.', 'flx-app' ) ),
			'otpStaff'        => flxapp_gql_f( 'Boolean', __( 'Staff may sign in with a code too.', 'flx-app' ) ),
			'phoneSelfVerify' => flxapp_gql_f( 'Boolean', __( 'A signed-in donor may confirm a phone number.', 'flx-app' ) ),
			'keysCap'         => flxapp_gql_f( 'String', __( 'Who can create API keys (a capability).', 'flx-app' ) ),
		),
		'output'      => array(
			'settings' => flxapp_gql_f( 'FlxAppAdminSettings', __( 'The settings after the change.', 'flx-app' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXAPP_Admin_Service::settings_save( flx_gql_arr( $input ) );
			return $r instanceof WP_Error ? $r : array( 'settings' => $r );
		},
	)
);

/* ───────── Modules ───────── */

flx_gql_type(
	'FlxAppAdminModule',
	array(
		'description' => __( 'A package module of the app.', 'flx-app' ),
		'fields'      => array(
			'id'      => flxapp_gql_f( 'String!', __( 'Module key.', 'flx-app' ) ),
			'title'   => flxapp_gql_f( 'String!', __( 'Module title.', 'flx-app' ) ),
			'menu'    => flxapp_gql_f( 'String', __( 'Panel menu slug; empty for a separate item.', 'flx-app' ) ),
			'cap'     => flxapp_gql_f( 'String', __( 'Capability needed to open it.', 'flx-app' ) ),
			'version' => flxapp_gql_f( 'String', __( 'Package version; empty when not reported.', 'flx-app' ) ),
			'kind'    => flxapp_gql_f( 'String!', __( 'native, manifest or web.', 'flx-app' ) ),
			'screens' => flxapp_gql_f( 'Int!', __( 'Number of manifest screens.', 'flx-app' ) ),
			'errors'  => flxapp_gql_f( '[String!]!', __( 'Manifest problems.', 'flx-app' ) ),
			'on'      => flxapp_gql_f( 'Boolean!', __( 'The module is on in the app.', 'flx-app' ) ),
		),
	)
);
flx_gql_query(
	'flxAppAdminModules',
	array(
		'type'        => '[FlxAppAdminModule!]!',
		'description' => __( 'Modules the app can show, with their on/off state.', 'flx-app' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): array => FLXAPP_Admin_Service::modules(),
	)
);
flx_gql_mutation(
	'flxAppAdminModulesSave',
	array(
		'description' => __( 'Sets which modules are on; the others turn off (their menu opens as an admin page).', 'flx-app' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'on' => flxapp_gql_f( '[String!]!', __( 'Module keys that stay on.', 'flx-app' ) ),
		),
		'output'      => array(
			'modules' => flxapp_gql_f( '[FlxAppAdminModule!]', __( 'The modules after the change.', 'flx-app' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXAPP_Admin_Service::modules_save( FLXAPP_Admin_Service::strs( $input['on'] ?? array() ) );
			return $r instanceof WP_Error ? $r : array( 'modules' => $r );
		},
	)
);

/* ───────── Menu ───────── */

flx_gql_type(
	'FlxAppAdminMenuPlatform',
	array(
		'description' => __( 'Whether a menu item shows on a platform.', 'flx-app' ),
		'fields'      => array(
			'key'     => flxapp_gql_f( 'String!', __( 'Platform key.', 'flx-app' ) ),
			'visible' => flxapp_gql_f( 'Boolean!', __( 'The item shows there.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppAdminMenuRow',
	array(
		'description' => __( 'A menu item hidden on some platform for a role.', 'flx-app' ),
		'fields'      => array(
			'role'      => flxapp_gql_f( 'String!', __( 'Role name.', 'flx-app' ) ),
			'menu'      => flxapp_gql_f( 'String!', __( 'Menu item name.', 'flx-app' ) ),
			'platforms' => flxapp_gql_f( '[FlxAppAdminMenuPlatform!]!', __( 'Visibility per platform.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppAdminMenu',
	array(
		'description' => __( 'Menu items hidden by platform (the menu itself is edited in Flux Admin).', 'flx-app' ),
		'fields'      => array(
			'available' => flxapp_gql_f( 'Boolean!', __( 'Flux Admin 1.3.0 or later is active.', 'flx-app' ) ),
			'editorUrl' => flxapp_gql_f( 'String', __( 'The menu editor address.', 'flx-app' ) ),
			'platforms' => flxapp_gql_f( '[FlxAppChoice!]!', __( 'Platforms.', 'flx-app' ) ),
			'rows'      => flxapp_gql_f( '[FlxAppAdminMenuRow!]!', __( 'Hidden items.', 'flx-app' ) ),
		),
	)
);
flx_gql_query(
	'flxAppAdminMenu',
	array(
		'type'        => 'FlxAppAdminMenu!',
		'description' => __( 'Menu items hidden by platform.', 'flx-app' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): array => FLXAPP_Admin_Service::menu(),
	)
);

/* ───────── OAuth clients ───────── */

flx_gql_type(
	'FlxAppAdminClient',
	array(
		'description' => __( 'An OAuth client.', 'flx-app' ),
		'fields'      => array(
			'id'           => flxapp_gql_f( 'String!', __( 'client_id.', 'flx-app' ) ),
			'name'         => flxapp_gql_f( 'String!', __( 'Name.', 'flx-app' ) ),
			'redirectUris' => flxapp_gql_f( '[String!]!', __( 'Redirect URIs.', 'flx-app' ) ),
			'kind'         => flxapp_gql_f( 'FlxAppClientKind!', __( 'Where it comes from.', 'flx-app' ) ),
			'hasSecret'    => flxapp_gql_f( 'Boolean!', __( 'It has a client secret.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppAdminClients',
	array(
		'description' => __( 'OAuth clients and the connection details.', 'flx-app' ),
		'fields'      => array(
			'clients'    => flxapp_gql_f( '[FlxAppAdminClient!]!', __( 'Clients.', 'flx-app' ) ),
			'connection' => flxapp_gql_f( '[FlxAppChoice!]!', __( 'Connection details (label holds the address).', 'flx-app' ) ),
		),
	)
);
flx_gql_query(
	'flxAppAdminClients',
	array(
		'type'        => 'FlxAppAdminClients!',
		'description' => __( 'OAuth clients and the connection details.', 'flx-app' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): array => FLXAPP_Admin_Service::clients(),
	)
);
flx_gql_mutation(
	'flxAppAdminClientCreate',
	array(
		'description' => __( 'Adds an OAuth client; a client secret is shown once.', 'flx-app' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'name'         => flxapp_gql_f( 'String!', __( 'Name.', 'flx-app' ) ),
			'redirectUris' => flxapp_gql_f( '[String!]!', __( 'Redirect URIs.', 'flx-app' ) ),
			'confidential' => flxapp_gql_f( 'Boolean', __( 'Generate a client secret.', 'flx-app' ) ),
		),
		'output'      => array(
			'client' => flxapp_gql_f( 'FlxAppAdminClient', __( 'The new client.', 'flx-app' ) ),
			'secret' => flxapp_gql_f( 'String', __( 'The client secret; shown only now.', 'flx-app' ) ),
		),
		'resolve'     => static fn( array $input ) => FLXAPP_Admin_Service::client_create(
			flx_gql_str( $input['name'] ?? '' ),
			FLXAPP_Admin_Service::strs( $input['redirectUris'] ?? array() ),
			! empty( $input['confidential'] )
		),
	)
);
flx_gql_mutation(
	'flxAppAdminClientDelete',
	array(
		'description' => __( 'Deletes an OAuth client and all of its sessions.', 'flx-app' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'id' => flxapp_gql_f( 'String!', __( 'client_id.', 'flx-app' ) ),
		),
		'output'      => array(
			'ok' => flxapp_gql_f( 'Boolean!', __( 'Deleted.', 'flx-app' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXAPP_Admin_Service::client_delete( flx_gql_str( $input['id'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'ok' => true );
		},
	)
);

/* ───────── Access: devices, sessions, keys ───────── */

flx_gql_type(
	'FlxAppAccessRow',
	array(
		'description' => __( 'An app session or a personal API key.', 'flx-app' ),
		'fields'      => array(
			'id'       => flxapp_gql_f( 'Int!', __( 'Row ID.', 'flx-app' ) ),
			'kind'     => flxapp_gql_f( 'FlxAppAccessKind!', __( 'Session or key.', 'flx-app' ) ),
			'name'     => flxapp_gql_f( 'String!', __( 'Name.', 'flx-app' ) ),
			'hint'     => flxapp_gql_f( 'String!', __( 'Last characters of a key; empty for a session.', 'flx-app' ) ),
			'userId'   => flxapp_gql_f( 'Int!', __( 'User ID.', 'flx-app' ) ),
			'user'     => flxapp_gql_f( 'String!', __( 'Display name.', 'flx-app' ) ),
			'created'  => flxapp_gql_f( 'Int!', __( 'Unix time.', 'flx-app' ) ),
			'lastUsed' => flxapp_gql_f( 'Int!', __( 'Unix time of the last use; 0 = never.', 'flx-app' ) ),
			'lastIp'   => flxapp_gql_f( 'String!', __( 'Last IP of a key; empty for a session.', 'flx-app' ) ),
			'expires'  => flxapp_gql_f( 'Int!', __( 'Unix time it expires; 0 = never.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppDeviceRow',
	array(
		'description' => __( 'A device signed in to the app.', 'flx-app' ),
		'fields'      => array(
			'id'          => flxapp_gql_f( 'Int!', __( 'Device ID.', 'flx-app' ) ),
			'name'        => flxapp_gql_f( 'String!', __( 'Device name.', 'flx-app' ) ),
			'platform'    => flxapp_gql_f( 'String!', __( 'Platform and model.', 'flx-app' ) ),
			'app'         => flxapp_gql_f( 'String!', __( 'Application and version.', 'flx-app' ) ),
			'brand'       => flxapp_gql_f( 'String', __( 'Brand of a branded app.', 'flx-app' ) ),
			'push'        => flxapp_gql_f( 'Boolean!', __( 'Notifications are on.', 'flx-app' ) ),
			'lastSeen'    => flxapp_gql_f( 'Int!', __( 'Unix time last seen.', 'flx-app' ) ),
			'lastSeenAgo' => flxapp_gql_f( 'String!', __( 'Last seen, in words.', 'flx-app' ) ),
			'userId'      => flxapp_gql_f( 'Int!', __( 'User ID.', 'flx-app' ) ),
			'user'        => flxapp_gql_f( 'String!', __( 'Display name.', 'flx-app' ) ),
			'current'     => flxapp_gql_f( 'Boolean!', __( 'This is the device that asks.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppDownloads',
	array(
		'description' => __( 'Desktop app download addresses.', 'flx-app' ),
		'fields'      => array(
			'macos'      => flxapp_gql_f( 'String!', __( 'macOS.', 'flx-app' ) ),
			'windows'    => flxapp_gql_f( 'String!', __( 'Windows.', 'flx-app' ) ),
			'windowsMsi' => flxapp_gql_f( 'String!', __( 'Windows MSI package.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppAccess',
	array(
		'description' => __( 'Devices, sessions and keys.', 'flx-app' ),
		'fields'      => array(
			'devices'       => flxapp_gql_f( '[FlxAppDeviceRow!]!', __( 'Devices.', 'flx-app' ) ),
			'rows'          => flxapp_gql_f( '[FlxAppAccessRow!]!', __( 'Sessions and keys.', 'flx-app' ) ),
			'canCreateKeys' => flxapp_gql_f( 'Boolean!', __( 'The user may create API keys.', 'flx-app' ) ),
			'canManage'     => flxapp_gql_f( 'Boolean!', __( 'The user manages the app settings.', 'flx-app' ) ),
			'downloads'     => flxapp_gql_f( 'FlxAppDownloads!', __( 'Desktop app downloads.', 'flx-app' ) ),
		),
	)
);
flx_gql_query(
	'flxAppAccess',
	array(
		'type'        => 'FlxAppAccess!',
		'description' => __( 'Devices, sessions and keys of the signed-in user, or of everyone (`all`, administrators).', 'flx-app' ),
		'cap'         => 'logged_in',
		'args'        => array(
			'all' => flxapp_gql_f( 'Boolean', __( 'Everyone (administrators only).', 'flx-app' ) ),
		),
		'resolve'     => static fn( $root, array $args ): array => flxapp_gql_ok( FLXAPP_Admin_Service::access( ! empty( $args['all'] ) ) ),
	)
);
flx_gql_mutation(
	'flxAppKeyCreate',
	array(
		'description' => __( 'Creates a personal API key; it is shown only now.', 'flx-app' ),
		'cap'         => 'logged_in',
		'input'       => array(
			'name' => flxapp_gql_f( 'String!', __( 'Name.', 'flx-app' ) ),
			'days' => flxapp_gql_f( 'Int', __( 'Valid for 30, 90 or 365 days; 0 = never expires.', 'flx-app' ) ),
		),
		'output'      => array(
			'key' => flxapp_gql_f( 'String', __( 'The key; shown only now.', 'flx-app' ) ),
		),
		'resolve'     => static fn( array $input ) => FLXAPP_Admin_Service::key_create( flx_gql_str( $input['name'] ?? '' ), flx_gql_int( $input['days'] ?? 0 ) ),
	)
);
flx_gql_mutation(
	'flxAppAccessRevoke',
	array(
		'description' => __( 'Removes a session or key (own, or any as administrator).', 'flx-app' ),
		'cap'         => 'logged_in',
		'input'       => array(
			'id' => flxapp_gql_f( 'Int!', __( 'Row ID.', 'flx-app' ) ),
		),
		'output'      => array(
			'ok' => flxapp_gql_f( 'Boolean!', __( 'Removed.', 'flx-app' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXAPP_Admin_Service::access_revoke( flx_gql_int( $input['id'] ?? 0 ) );
			return $r instanceof WP_Error ? $r : array( 'ok' => true );
		},
	)
);
flx_gql_mutation(
	'flxAppDeviceRevoke',
	array(
		'description' => __( 'Signs a device out (own, or any as administrator).', 'flx-app' ),
		'cap'         => 'logged_in',
		'input'       => array(
			'id' => flxapp_gql_f( 'Int!', __( 'Device ID.', 'flx-app' ) ),
		),
		'output'      => array(
			'ok' => flxapp_gql_f( 'Boolean!', __( 'Signed out.', 'flx-app' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXAPP_Admin_Service::device_revoke( flx_gql_int( $input['id'] ?? 0 ) );
			return $r instanceof WP_Error ? $r : array( 'ok' => true );
		},
	)
);

/* ───────── Applications ───────── */

flx_gql_type(
	'FlxAppBrandedApp',
	array(
		'description' => __( 'An app published under the organisation\'s own name.', 'flx-app' ),
		'fields'      => array(
			'id'           => flxapp_gql_f( 'String!', __( 'client_id.', 'flx-app' ) ),
			'name'         => flxapp_gql_f( 'String!', __( 'Name.', 'flx-app' ) ),
			'platform'     => flxapp_gql_f( 'String!', __( 'mobile or desktop.', 'flx-app' ) ),
			'redirectUris' => flxapp_gql_f( '[String!]!', __( 'Redirect URIs.', 'flx-app' ) ),
		),
	)
);
flx_gql_input(
	'FlxAppBrandedAppInput',
	array(
		'description' => __( 'A branded app.', 'flx-app' ),
		'fields'      => array(
			'id'           => flxapp_gql_f( 'String!', __( 'client_id.', 'flx-app' ) ),
			'name'         => flxapp_gql_f( 'String', __( 'Name.', 'flx-app' ) ),
			'platform'     => flxapp_gql_f( 'String', __( 'mobile or desktop.', 'flx-app' ) ),
			'redirectUris' => flxapp_gql_f( '[String!]', __( 'Redirect URIs.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppLastPush',
	array(
		'description' => __( 'The last push send.', 'flx-app' ),
		'fields'      => array(
			'at'     => flxapp_gql_f( 'Int!', __( 'Unix time.', 'flx-app' ) ),
			'ago'    => flxapp_gql_f( 'String!', __( 'In words.', 'flx-app' ) ),
			'sent'   => flxapp_gql_f( 'Int!', __( 'Sent.', 'flx-app' ) ),
			'failed' => flxapp_gql_f( 'Int!', __( 'Failed.', 'flx-app' ) ),
			'error'  => flxapp_gql_f( 'String', __( 'Error text.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppAdminMobile',
	array(
		'description' => __( 'Which apps may connect, store links and notifications.', 'flx-app' ),
		'fields'      => array(
			'genericApp'   => flxapp_gql_f( 'Boolean!', __( 'The main FluxWP app may connect.', 'flx-app' ) ),
			'storeIos'     => flxapp_gql_f( 'String!', __( 'App Store link.', 'flx-app' ) ),
			'storeAndroid' => flxapp_gql_f( 'String!', __( 'Google Play link.', 'flx-app' ) ),
			'pushEnabled'  => flxapp_gql_f( 'Boolean!', __( 'Push notifications are sent.', 'flx-app' ) ),
			'pushHide'     => flxapp_gql_f( 'Boolean!', __( 'Hide content on the lock screen.', 'flx-app' ) ),
			'hasExpoToken' => flxapp_gql_f( 'Boolean!', __( 'An Expo access token is saved (never shown).', 'flx-app' ) ),
			'embedFront'   => flxapp_gql_f( 'Boolean!', __( 'Hide the admin bar on site pages opened in the app.', 'flx-app' ) ),
			'devRedirects' => flxapp_gql_f( 'Boolean!', __( 'Allow Expo development redirects.', 'flx-app' ) ),
			'apps'         => flxapp_gql_f( '[FlxAppBrandedApp!]!', __( 'Branded apps.', 'flx-app' ) ),
			'deviceCount'  => flxapp_gql_f( 'Int!', __( 'Devices signed in.', 'flx-app' ) ),
			'pushCount'    => flxapp_gql_f( 'Int!', __( 'Devices with notifications on.', 'flx-app' ) ),
			'lastPush'     => flxapp_gql_f( 'FlxAppLastPush', __( 'The last push send.', 'flx-app' ) ),
			'bootstrapUrl' => flxapp_gql_f( 'String!', __( 'Bootstrap address.', 'flx-app' ) ),
			'preset'       => flxapp_gql_f( 'String', __( 'Active preset.', 'flx-app' ) ),
		),
	)
);
flx_gql_query(
	'flxAppAdminMobile',
	array(
		'type'        => 'FlxAppAdminMobile!',
		'description' => __( 'The Applications settings.', 'flx-app' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): array => FLXAPP_Admin_Service::mobile(),
	)
);
flx_gql_mutation(
	'flxAppAdminMobileSave',
	array(
		'description' => __( 'Saves the Applications settings; only the sent fields change.', 'flx-app' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'genericApp'     => flxapp_gql_f( 'Boolean', __( 'The main FluxWP app may connect.', 'flx-app' ) ),
			'storeIos'       => flxapp_gql_f( 'String', __( 'App Store link.', 'flx-app' ) ),
			'storeAndroid'   => flxapp_gql_f( 'String', __( 'Google Play link.', 'flx-app' ) ),
			'pushEnabled'    => flxapp_gql_f( 'Boolean', __( 'Push notifications are sent.', 'flx-app' ) ),
			'pushHide'       => flxapp_gql_f( 'Boolean', __( 'Hide content on the lock screen.', 'flx-app' ) ),
			'expoToken'      => flxapp_gql_f( 'String', __( 'A new Expo access token.', 'flx-app' ) ),
			'clearExpoToken' => flxapp_gql_f( 'Boolean', __( 'Delete the saved Expo access token.', 'flx-app' ) ),
			'embedFront'     => flxapp_gql_f( 'Boolean', __( 'Hide the admin bar on site pages opened in the app.', 'flx-app' ) ),
			'devRedirects'   => flxapp_gql_f( 'Boolean', __( 'Allow Expo development redirects.', 'flx-app' ) ),
			'apps'           => flxapp_gql_f( '[FlxAppBrandedAppInput!]', __( 'The branded apps (replaces the list).', 'flx-app' ) ),
		),
		'output'      => array(
			'mobile' => flxapp_gql_f( 'FlxAppAdminMobile', __( 'The settings after the change.', 'flx-app' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXAPP_Admin_Service::mobile_save( flx_gql_arr( $input ) );
			return $r instanceof WP_Error ? $r : array( 'mobile' => $r );
		},
	)
);
flx_gql_mutation(
	'flxAppAdminPushTest',
	array(
		'description' => __( 'Sends a test notification to the administrator\'s devices.', 'flx-app' ),
		'cap'         => 'manage_options',
		'output'      => array(
			'sent'    => flxapp_gql_f( 'Int', __( 'Sent.', 'flx-app' ) ),
			'failed'  => flxapp_gql_f( 'Int', __( 'Failed.', 'flx-app' ) ),
			'skipped' => flxapp_gql_f( 'Int', __( 'Skipped.', 'flx-app' ) ),
		),
		'resolve'     => static fn() => FLXAPP_Admin_Service::push_test(),
	)
);

/* ───────── Appearance ───────── */

flx_gql_type(
	'FlxAppTargetGroup',
	array(
		'description' => __( 'A group of targets the pickers offer.', 'flx-app' ),
		'fields'      => array(
			'label' => flxapp_gql_f( 'String!', __( 'Group name.', 'flx-app' ) ),
			'items' => flxapp_gql_f( '[FlxAppChoice!]!', __( 'Targets.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppScreenChoice',
	array(
		'description' => __( 'A native screen that can be turned off.', 'flx-app' ),
		'fields'      => array(
			'key'   => flxapp_gql_f( 'String!', __( 'module/screen.', 'flx-app' ) ),
			'label' => flxapp_gql_f( 'String!', __( 'Name.', 'flx-app' ) ),
			'on'    => flxapp_gql_f( 'Boolean!', __( 'It opens natively in the app.', 'flx-app' ) ),
		),
	)
);
flx_gql_type(
	'FlxAppAdminAppearance',
	array(
		'description' => __( 'Appearance of the desktop and mobile app. `option` is the flxapp_app site layer and `theme` the resolved theme; both are open documents the client validates.', 'flx-app' ),
		'fields'      => array(
			'option'         => flxapp_gql_f( 'FlxJSON!', __( 'The site layer (theme, shell, features, links, brand); open and versioned.', 'flx-app' ) ),
			'theme'          => flxapp_gql_f( 'FlxJSON!', __( 'The resolved theme (colours by mode, fonts); open.', 'flx-app' ) ),
			'fonts'          => flxapp_gql_f( '[String!]!', __( 'Fonts to choose from.', 'flx-app' ) ),
			'colorKeys'      => flxapp_gql_f( '[FlxAppChoice!]!', __( 'Theme colours and their names.', 'flx-app' ) ),
			'targets'        => flxapp_gql_f( '[FlxAppTargetGroup!]!', __( 'Targets for tabs, home screens and shortcuts.', 'flx-app' ) ),
			'targetLabels'   => flxapp_gql_f( '[FlxAppChoice!]!', __( 'Short names of all targets.', 'flx-app' ) ),
			'screens'        => flxapp_gql_f( '[FlxAppScreenChoice!]!', __( 'Screens that can be turned off.', 'flx-app' ) ),
			'previewMobile'  => flxapp_gql_f( 'FlxJSON!', __( 'The resolved mobile config for the preview (flx-app-config/1).', 'flx-app' ) ),
			'previewDesktop' => flxapp_gql_f( 'FlxJSON!', __( 'The resolved desktop config for the preview (flx-app-config/1).', 'flx-app' ) ),
		),
	)
);
flx_gql_query(
	'flxAppAdminAppearance',
	array(
		'type'        => 'FlxAppAdminAppearance!',
		'description' => __( 'The Appearance settings and what the pickers need.', 'flx-app' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn(): array => FLXAPP_Admin_Service::appearance(),
	)
);
flx_gql_mutation(
	'flxAppAdminAppearanceSave',
	array(
		'description' => __( 'Saves the theme, the mobile and desktop shell and the enabled screens (the same sanitizing as presets).', 'flx-app' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'theme'    => flxapp_gql_f( 'FlxJSON!', __( 'Theme overrides: mode, radius, density, font, light/dark colours.', 'flx-app' ) ),
			'mobile'   => flxapp_gql_f( 'FlxJSON!', __( 'Mobile shell: nav, home, tabs, drawer, topbar.', 'flx-app' ) ),
			'desktop'  => flxapp_gql_f( 'FlxJSON!', __( 'Desktop shell: home, topbar, commands.', 'flx-app' ) ),
			'screensOn' => flxapp_gql_f( '[String!]!', __( 'Screens that open natively; the others open in the web view.', 'flx-app' ) ),
		),
		'output'      => array(
			'appearance' => flxapp_gql_f( 'FlxAppAdminAppearance', __( 'The settings after the change.', 'flx-app' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXAPP_Admin_Service::appearance_save(
				array(
					'theme'      => FLXAPP_Service::plain( $input['theme'] ?? array() ),
					'mobile'     => FLXAPP_Service::plain( $input['mobile'] ?? array() ),
					'desktop'    => FLXAPP_Service::plain( $input['desktop'] ?? array() ),
					'screens_on' => FLXAPP_Admin_Service::strs( $input['screensOn'] ?? array() ),
					'screens_all' => implode( ',', array_keys( flxapp_screen_catalog() ) ),
				)
			);
			return $r instanceof WP_Error ? $r : array( 'appearance' => $r );
		},
	)
);
