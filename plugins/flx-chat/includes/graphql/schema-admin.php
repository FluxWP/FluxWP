<?php
/**
 * Flux Chat GraphQL schema, part 2: settings, connections, system checks, AI model list and the WhatsApp
 * reports screen. Loaded on flx_gql_register after schema.php.
 *
 * Every field calls the service the classic page uses (FLXC_Settings_Service, FLXC_Report_Service); a
 * GraphQL save and a classic form post write the same option. Secrets are write-only: the settings read
 * says only whether an API key is saved (`hasApiKey`), a write with an empty key keeps it and "__clear__"
 * removes it. The webhook address carries `?t=<secret>` and is only returned by the manager-only
 * flxChatWebhookUrlReveal mutation, never by a query or a cache.
 *
 * @package FLX_Chat
 */

defined( 'ABSPATH' ) || exit;

/**
 * Setting keys the GraphQL type mirrors: key => [ kind, description ]. Kinds: b Boolean, i Int, f Float,
 * s String, ai [Int], as [String]. The GraphQL name is the camelCase of the key and is also the key
 * FLXC_Settings_Service::get() returns.
 *
 * @return array<string,array{0:string,1:string}>
 */
function flxc_gql_setting_defs(): array {
	return array(
		'brand_name'            => array( 's', __( 'Name shown in the panel.', 'flx-chat' ) ),
		'accent'                => array( 's', __( 'Accent color (hex).', 'flx-chat' ) ),
		'send_read'             => array( 'b', __( 'Send read receipts when a chat is opened.', 'flx-chat' ) ),
		'agent_signature'       => array( 'b', __( 'Prefix agent replies with the agent name.', 'flx-chat' ) ),
		'restrict_agents'       => array( 'b', __( 'Agents see only chats assigned to them or to nobody.', 'flx-chat' ) ),
		'show_groups'           => array( 'b', __( 'Show WhatsApp group chats.', 'flx-chat' ) ),
		'ui_flux'               => array( 'b', __( 'Open the chat page in the React screen.', 'flx-chat' ) ),
		'delete_data'           => array( 'b', __( 'Delete all chat data when the plugin is removed.', 'flx-chat' ) ),
		'delete_everyone_hours' => array( 'i', __( 'Hours a message can be deleted for everyone.', 'flx-chat' ) ),
		'poll_interval'         => array( 'i', __( 'Seconds between chat refreshes, 3 to 60.', 'flx-chat' ) ),
		'welcome_enabled'       => array( 'b', __( 'Send a welcome message to new chats.', 'flx-chat' ) ),
		'welcome_text'          => array( 's', __( 'Welcome message.', 'flx-chat' ) ),
		'away_enabled'          => array( 'b', __( 'Send an away message outside open hours.', 'flx-chat' ) ),
		'away_text'             => array( 's', __( 'Away message.', 'flx-chat' ) ),
		'away_days'             => array( 'ai', __( 'Open days, 1 (Monday) to 7 (Sunday).', 'flx-chat' ) ),
		'away_start'            => array( 's', __( 'Opening time, HH:MM.', 'flx-chat' ) ),
		'away_end'              => array( 's', __( 'Closing time, HH:MM.', 'flx-chat' ) ),
		'away_throttle'         => array( 'i', __( 'Hours before the away message is sent to the same chat again.', 'flx-chat' ) ),
		'agents_donations'      => array( 'b', __( 'Agents can record donations.', 'flx-chat' ) ),
		'agents_certificates'   => array( 'b', __( 'Agents can issue certificates.', 'flx-chat' ) ),
		'chips_bar'             => array( 'b', __( 'Show the quick action chips.', 'flx-chat' ) ),
		'msg_donation_pending'  => array( 's', __( 'Message for a donation awaiting payment.', 'flx-chat' ) ),
		'msg_donation_done'     => array( 's', __( 'Message for a paid donation.', 'flx-chat' ) ),
		'msg_receipt'           => array( 's', __( 'Message sent with a receipt.', 'flx-chat' ) ),
		'bank_text'             => array( 's', __( 'Bank transfer text.', 'flx-chat' ) ),
		'ai_provider'           => array( 's', __( 'AI provider.', 'flx-chat' ) ),
		'ai_model'              => array( 's', __( 'Model for suggestions.', 'flx-chat' ) ),
		'ai_model_bot'          => array( 's', __( 'Model for the assistant.', 'flx-chat' ) ),
		'ai_model_vision'       => array( 's', __( 'Model for images.', 'flx-chat' ) ),
		'ai_model_audio'        => array( 's', __( 'Model for voice notes.', 'flx-chat' ) ),
		'ai_temperature'        => array( 'f', __( 'Temperature, 0 to 1.', 'flx-chat' ) ),
		'ai_auto_suggest'       => array( 'b', __( 'Suggest a reply for each incoming message.', 'flx-chat' ) ),
		'ai_instructions'       => array( 's', __( 'Extra instructions for suggestions.', 'flx-chat' ) ),
		'bot_enabled'           => array( 'b', __( 'The assistant answers chats.', 'flx-chat' ) ),
		'bot_scope'             => array( 's', __( 'Which chats the assistant answers: all, away or unassigned.', 'flx-chat' ) ),
		'bot_test_only'         => array( 'b', __( 'The assistant answers only the test numbers.', 'flx-chat' ) ),
		'bot_test_numbers'      => array( 's', __( 'Test numbers.', 'flx-chat' ) ),
		'bot_pause_hours'       => array( 'i', __( 'Hours the assistant stays quiet after an agent replies.', 'flx-chat' ) ),
		'bot_daily_limit'       => array( 'i', __( 'Assistant replies per chat per day.', 'flx-chat' ) ),
		'bot_presets'           => array( 's', __( 'Quick donation amounts.', 'flx-chat' ) ),
		'bot_context_hours'     => array( 'i', __( 'Hours of history the assistant reads.', 'flx-chat' ) ),
		'bot_types'             => array( 'ai', __( 'Donation types the assistant offers. Empty is all open types.', 'flx-chat' ) ),
		'bot_receipt_ai'        => array( 'b', __( 'Read receipts with AI.', 'flx-chat' ) ),
		'bot_price_footer'      => array( 's', __( 'Text under the price list.', 'flx-chat' ) ),
		'bank_holder'           => array( 's', __( 'Bank account holder.', 'flx-chat' ) ),
		'bank_iban'             => array( 's', __( 'Bank IBAN.', 'flx-chat' ) ),
		'bank_desc'             => array( 's', __( 'Bank transfer description.', 'flx-chat' ) ),
		'donate_url'            => array( 's', __( 'Donation page address.', 'flx-chat' ) ),
		'bot_after_payment'     => array( 's', __( 'Message after a payment.', 'flx-chat' ) ),
		'bot_cancel_text'       => array( 's', __( 'Message when a donation is cancelled.', 'flx-chat' ) ),
		'bot_limit_text'        => array( 's', __( 'Message when the daily limit is reached.', 'flx-chat' ) ),
		'bot_handoff_text'      => array( 's', __( 'Message when a person takes over.', 'flx-chat' ) ),
		'bot_fallback_text'     => array( 's', __( 'Message when the assistant cannot answer.', 'flx-chat' ) ),
		'bot_fallback_wait'     => array( 'i', __( 'Seconds before the fallback message.', 'flx-chat' ) ),
		'bot_time_budget'       => array( 'i', __( 'Seconds an assistant turn may take.', 'flx-chat' ) ),
		'bot_notify_numbers'    => array( 's', __( 'Numbers told when a person is needed.', 'flx-chat' ) ),
		'bot_handoff_words'     => array( 's', __( 'Words that hand the chat to a person.', 'flx-chat' ) ),
		'bot_instructions'      => array( 's', __( 'Extra assistant instructions.', 'flx-chat' ) ),
		'bot_optout_text'       => array( 's', __( 'Message when a contact opts out.', 'flx-chat' ) ),
		'mem_enabled'           => array( 'b', __( 'Summarize chats into contact memory.', 'flx-chat' ) ),
		'mem_after_hours'       => array( 'i', __( 'Hours of silence before a chat is summarized.', 'flx-chat' ) ),
		'workers_max'           => array( 'i', __( 'Most background AI jobs at once.', 'flx-chat' ) ),
		'workers_reserved'      => array( 'i', __( 'Background jobs kept free for live replies.', 'flx-chat' ) ),
		'mem_window_from'       => array( 's', __( 'Night window start, HH:MM.', 'flx-chat' ) ),
		'mem_window_to'         => array( 's', __( 'Night window end, HH:MM.', 'flx-chat' ) ),
		'bot_reactions'         => array( 'b', __( 'The assistant reacts to messages.', 'flx-chat' ) ),
		'bot_voice'             => array( 'b', __( 'The assistant reads voice notes.', 'flx-chat' ) ),
		'bot_voice_max'         => array( 'i', __( 'Longest voice note in seconds.', 'flx-chat' ) ),
		'bot_widgets'           => array( 'b', __( 'The assistant can show widgets.', 'flx-chat' ) ),
		'benef_cert_notify'     => array( 'b', __( 'Tell the beneficiary about a certificate.', 'flx-chat' ) ),
		'benef_cert_text'       => array( 's', __( 'Beneficiary certificate message.', 'flx-chat' ) ),
		'rem_enabled'           => array( 'b', __( 'Send chat reminders.', 'flx-chat' ) ),
		'rem_time'              => array( 's', __( 'Reminder time, HH:MM.', 'flx-chat' ) ),
		'rem_max'               => array( 'i', __( 'Most reminders per run.', 'flx-chat' ) ),
		'staff_on'              => array( 'b', __( 'Staff mode.', 'flx-chat' ) ),
		'ref_notify'            => array( 'b', __( 'Tell staff about their referral donations.', 'flx-chat' ) ),
		'staff_donor_text'      => array( 's', __( 'Message to a staff donor.', 'flx-chat' ) ),
		'ref_cert_notify'       => array( 'b', __( 'Tell staff about referral certificates.', 'flx-chat' ) ),
		'ref_cert_text'         => array( 's', __( 'Referral certificate message.', 'flx-chat' ) ),
		'staff_cert_max'        => array( 'i', __( 'Most certificates staff may issue at once.', 'flx-chat' ) ),
		'email_verify'          => array( 'b', __( 'Verify the e-mail before records are shown.', 'flx-chat' ) ),
		'verify_ttl'            => array( 'i', __( 'Minutes a verification code is valid.', 'flx-chat' ) ),
		'verify_max_fails'      => array( 'i', __( 'Wrong codes allowed.', 'flx-chat' ) ),
		'verify_phone_channel'  => array( 's', __( 'Channel for phone codes: auto, whatsapp or sms.', 'flx-chat' ) ),
		'verify_fail_text'      => array( 's', __( 'Message after too many wrong codes.', 'flx-chat' ) ),
		'verify_msg'            => array( 's', __( 'Verification message.', 'flx-chat' ) ),
		'bot_site_search'       => array( 'b', __( 'The assistant searches the site.', 'flx-chat' ) ),
		'bot_site_types'        => array( 'as', __( 'Post types the assistant may search.', 'flx-chat' ) ),
		'bot_site_exclude'      => array( 's', __( 'Addresses the assistant must not share.', 'flx-chat' ) ),
		'mirror_group'          => array( 's', __( 'WhatsApp group that gets a copy of notifications.', 'flx-chat' ) ),
		'mirror_enabled'        => array( 'b', __( 'Copy notifications to the group.', 'flx-chat' ) ),
		'notify_to_group'       => array( 'b', __( 'Send notifications to the group.', 'flx-chat' ) ),
	);
}

/**
 * GraphQL type notation for a setting kind.
 */
function flxc_gql_setting_type( string $kind, bool $required ): string {
	$map = array(
		'b'  => 'Boolean',
		'i'  => 'Int',
		'f'  => 'Float',
		's'  => 'String',
		'ai' => '[Int!]',
		'as' => '[String!]',
	);
	return ( $map[ $kind ] ?? 'String' ) . ( $required ? '!' : '' );
}

/**
 * camelCase of a snake_case key.
 */
function flxc_gql_camel( string $key ): string {
	return lcfirst( str_replace( ' ', '', ucwords( str_replace( '_', ' ', $key ) ) ) );
}

/**
 * Settings payload array for the output type.
 *
 * @return array<string,mixed>
 */
function flxc_gql_settings_view(): array {
	return FLXC_Settings_Service::get();
}

/**
 * A GraphQL mutation input as the snake_case keys the service reads. Only keys the caller sent are set.
 *
 * @param array<string,mixed> $input Input.
 * @return array<string,mixed>
 */
function flxc_gql_settings_input( array $input ): array {
	$out = array();
	foreach ( array_keys( flxc_gql_setting_defs() ) as $key ) {
		$camel = flxc_gql_camel( $key );
		if ( array_key_exists( $camel, $input ) && null !== $input[ $camel ] ) {
			$out[ $key ] = $input[ $camel ];
		}
	}
	if ( isset( $input['labels'] ) && is_array( $input['labels'] ) ) {
		$out['labels'] = $input['labels'];
	}
	if ( isset( $input['connections'] ) && is_array( $input['connections'] ) ) {
		$rows = array();
		foreach ( $input['connections'] as $row ) {
			$row = flx_gql_arr( $row );
			$rows[] = array(
				'id'            => FLXC_Val::str( $row['id'] ?? '' ),
				'delete'        => ! empty( $row['delete'] ),
				'source'        => FLXC_Val::str( $row['source'] ?? 'own' ),
				'label'         => FLXC_Val::str( $row['label'] ?? '' ),
				'base_url'      => FLXC_Val::str( $row['baseUrl'] ?? '' ),
				'instance'      => FLXC_Val::str( $row['instance'] ?? '' ),
				'api_key'       => FLXC_Val::str( $row['apiKey'] ?? '' ),
				'rotate_secret' => ! empty( $row['rotateSecret'] ),
			);
		}
		$out['connections'] = $rows;
	}
	return $out;
}

/**
 * Unix time or 0 → FlxDateTime or null.
 *
 * @param mixed $ts Unix time.
 * @return array{iso:string,timestamp:int,label:string,relative:string}|null
 */
function flxc_gql_ts( $ts ): ?array {
	$n = FLXC_Val::int( $ts );
	return $n > 0 ? flx_gql_datetime( $n ) : null;
}

$flxc_gql_manage_cfg = static fn(): bool => flxc_can_manage();
$flxc_gql_reports    = flx_gql_any_cap( 'flx_reports_manage', 'flxc_manage' );

/* ---------- types ---------- */

flx_gql_type(
	'FlxChatChoice',
	array(
		'description' => __( 'A value and its label.', 'flx-chat' ),
		'fields'      => array(
			'value' => array( 'type' => 'String!', 'description' => __( 'Value.', 'flx-chat' ) ),
			'label' => array( 'type' => 'String!', 'description' => __( 'Label.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatLine',
	array(
		'description' => __( 'A WhatsApp line. The API key is never returned; hasApiKey says whether one is saved.', 'flx-chat' ),
		'fields'      => array(
			'id'            => array( 'type' => 'String!', 'description' => __( 'Line id.', 'flx-chat' ) ),
			'label'         => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-chat' ) ),
			'source'        => array( 'type' => 'String!', 'description' => __( 'own, flxi (Flux Mail) or flxs (Flux Certificates).', 'flx-chat' ) ),
			'sourceValue'   => array( 'type' => 'String!', 'description' => __( 'Selected source: own, flxi:<id> or flxs:<id>.', 'flx-chat' ) ),
			'origin'        => array( 'type' => 'String!', 'description' => __( 'Where the line details come from.', 'flx-chat' ), 'resolve' => static fn( $c ): string => FLXC_Val::str( flx_gql_arr( $c )['origin'] ?? '' ) ),
			'baseUrl'       => array( 'type' => 'String!', 'description' => __( 'Evolution API address.', 'flx-chat' ) ),
			'instance'      => array( 'type' => 'String!', 'description' => __( 'Instance name.', 'flx-chat' ) ),
			'hasApiKey'     => array( 'type' => 'Boolean!', 'description' => __( 'An API key is saved.', 'flx-chat' ) ),
			'state'         => array( 'type' => 'String!', 'description' => __( 'Last known state: open, connecting, close, missing or error. Empty when unknown.', 'flx-chat' ) ),
			'stateAt'       => array( 'type' => 'FlxDateTime', 'description' => __( 'When the state was read.', 'flx-chat' ), 'resolve' => static fn( $c ): ?array => flxc_gql_ts( flx_gql_arr( $c )['stateAt'] ?? 0 ) ),
			'lastWebhookAt' => array( 'type' => 'FlxDateTime', 'description' => __( 'Last webhook received. Null when none yet.', 'flx-chat' ), 'resolve' => static fn( $c ): ?array => flxc_gql_ts( flx_gql_arr( $c )['lastWebhookAt'] ?? 0 ) ),
			'sources'       => array( 'type' => '[FlxChatChoice!]!', 'description' => __( 'Source choices for this line.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatLabelRule',
	array(
		'description' => __( 'A chat label.', 'flx-chat' ),
		'fields'      => array(
			'id'    => array( 'type' => 'String!', 'description' => __( 'Id.', 'flx-chat' ) ),
			'name'  => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-chat' ) ),
			'color' => array( 'type' => 'String!', 'description' => __( 'Color (hex).', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatAiUsage',
	array(
		'description' => __( 'AI use of this month.', 'flx-chat' ),
		'fields'      => array(
			'month'  => array( 'type' => 'String!', 'description' => __( 'Month, YYYY-MM.', 'flx-chat' ) ),
			'calls'  => array( 'type' => 'Int!', 'description' => __( 'Calls.', 'flx-chat' ) ),
			'tokens' => array( 'type' => 'Int!', 'description' => __( 'Tokens.', 'flx-chat' ) ),
			'cost'   => array( 'type' => 'Float!', 'description' => __( 'Cost in US dollars.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatMirrorError',
	array(
		'description' => __( 'Last problem copying a notification to the group.', 'flx-chat' ),
		'fields'      => array(
			'at'    => array( 'type' => 'FlxDateTime', 'description' => __( 'When.', 'flx-chat' ), 'resolve' => static fn( $e ): ?array => flxc_gql_ts( flx_gql_arr( $e )['at'] ?? 0 ) ),
			'error' => array( 'type' => 'String!', 'description' => __( 'Message.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatDonationType',
	array(
		'description' => __( 'An open Flux Donate type.', 'flx-chat' ),
		'fields'      => array(
			'databaseId' => array( 'type' => 'Int!', 'description' => __( 'Type id.', 'flx-chat' ), 'resolve' => static fn( $t ): int => FLXC_Val::int( flx_gql_arr( $t )['id'] ?? 0 ) ),
			'title'      => array( 'type' => 'String!', 'description' => __( 'Title.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatPostType',
	array(
		'description' => __( 'A public post type the assistant may search.', 'flx-chat' ),
		'fields'      => array(
			'name'         => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-chat' ) ),
			'label'        => array( 'type' => 'String!', 'description' => __( 'Label.', 'flx-chat' ) ),
			'autoSelected' => array( 'type' => 'Boolean!', 'description' => __( 'Searched by default.', 'flx-chat' ) ),
		),
	)
);

$flxc_ctx_fields = array(
	'flxiOn'                 => array( 'Boolean!', __( 'Flux Mail provides WhatsApp lines.', 'flx-chat' ) ),
	'flxiConnectionsUrl'     => array( 'String!', __( 'Flux Mail connections page.', 'flx-chat' ) ),
	'flxsOn'                 => array( 'Boolean!', __( 'Flux Certificates is active.', 'flx-chat' ) ),
	'flxbOn'                 => array( 'Boolean!', __( 'Flux Donate is active.', 'flx-chat' ) ),
	'rolesOn'                => array( 'Boolean!', __( 'Flux Roles is active.', 'flx-chat' ) ),
	'aiHub'                  => array( 'Boolean!', __( 'Flux AI is active.', 'flx-chat' ) ),
	'aiReady'                => array( 'Boolean!', __( 'The assistant model and key are ready.', 'flx-chat' ) ),
	'aiTypesProblem'         => array( 'String!', __( 'Why the assistant cannot reply. Empty when fine.', 'flx-chat' ) ),
	'aiSettingsUrl'          => array( 'String!', __( 'Flux AI settings page.', 'flx-chat' ) ),
	'assistantUrl'           => array( 'String!', __( 'Assistant page.', 'flx-chat' ) ),
	'chatsUrl'               => array( 'String!', __( 'Chats page.', 'flx-chat' ) ),
	'newUserUrl'             => array( 'String!', __( 'New user page.', 'flx-chat' ) ),
	'widgetsRendererOk'      => array( 'Boolean!', __( 'The widget renderer works.', 'flx-chat' ) ),
	'widgetsPluginOk'        => array( 'Boolean!', __( 'Flux AI can render widgets.', 'flx-chat' ) ),
	'timezone'               => array( 'String!', __( 'Site time zone.', 'flx-chat' ) ),
	'isOpenHours'            => array( 'Boolean!', __( 'It is open hours now.', 'flx-chat' ) ),
	'siteName'               => array( 'String!', __( 'Site name.', 'flx-chat' ) ),
	'pricePreview'           => array( 'String!', __( 'Price list as the assistant sends it.', 'flx-chat' ) ),
	'defaultDonationPending' => array( 'String!', __( 'Built-in text.', 'flx-chat' ) ),
	'defaultDonationDone'    => array( 'String!', __( 'Built-in text.', 'flx-chat' ) ),
	'defaultReceipt'         => array( 'String!', __( 'Built-in text.', 'flx-chat' ) ),
	'defaultBankText'        => array( 'String!', __( 'Built-in text.', 'flx-chat' ) ),
	'defaultBankHolder'      => array( 'String!', __( 'Built-in value.', 'flx-chat' ) ),
	'defaultBankIban'        => array( 'String!', __( 'Built-in value.', 'flx-chat' ) ),
	'defaultDonateUrl'       => array( 'String!', __( 'Built-in value.', 'flx-chat' ) ),
	'defaultCancelText'      => array( 'String!', __( 'Built-in text.', 'flx-chat' ) ),
	'defaultHandoffWords'    => array( 'String!', __( 'Built-in words.', 'flx-chat' ) ),
	'defaultBenefCertText'   => array( 'String!', __( 'Built-in text.', 'flx-chat' ) ),
	'defaultRefCertText'     => array( 'String!', __( 'Built-in text.', 'flx-chat' ) ),
	'defaultVerifyFailText'  => array( 'String!', __( 'Built-in text.', 'flx-chat' ) ),
	'defaultVerifyMsg'       => array( 'String!', __( 'Built-in text.', 'flx-chat' ) ),
);
$flxc_ctx_out = array();
foreach ( $flxc_ctx_fields as $name => $def ) {
	$flxc_ctx_out[ $name ] = array( 'type' => $def[0], 'description' => $def[1] );
}
$flxc_ctx_out['agents']        = array( 'type' => '[FlxChatAgent!]!', 'description' => __( 'People who can be assigned a chat.', 'flx-chat' ) );
$flxc_ctx_out['aiProviders']   = array( 'type' => '[FlxChatProvider!]!', 'description' => __( 'AI providers that are ready.', 'flx-chat' ) );
$flxc_ctx_out['aiUsage']       = array( 'type' => 'FlxChatAiUsage', 'description' => __( 'AI use this month. Null when none.', 'flx-chat' ) );
$flxc_ctx_out['defaultSource'] = array( 'type' => 'FlxChatChoice!', 'description' => __( 'Source for a new line.', 'flx-chat' ) );
$flxc_ctx_out['sourceChoices'] = array( 'type' => '[FlxChatChoice!]!', 'description' => __( 'Source choices for a new line.', 'flx-chat' ) );
$flxc_ctx_out['donationTypes'] = array( 'type' => '[FlxChatDonationType!]!', 'description' => __( 'Open donation types.', 'flx-chat' ) );
$flxc_ctx_out['postTypes']     = array( 'type' => '[FlxChatPostType!]!', 'description' => __( 'Public post types.', 'flx-chat' ) );
$flxc_ctx_out['mirrorError']   = array( 'type' => 'FlxChatMirrorError', 'description' => __( 'Last copy problem. Null when none.', 'flx-chat' ) );

flx_gql_type(
	'FlxChatProvider',
	array(
		'description' => __( 'An AI provider.', 'flx-chat' ),
		'fields'      => array(
			'id'    => array( 'type' => 'String!', 'description' => __( 'Id.', 'flx-chat' ) ),
			'label' => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatSettingsContext',
	array(
		'description' => __( 'What the settings screen needs to explain itself: what is installed, defaults and choices. Nothing secret.', 'flx-chat' ),
		'fields'      => $flxc_ctx_out,
	)
);

$flxc_set_out = array();
foreach ( flxc_gql_setting_defs() as $key => $def ) {
	$flxc_set_out[ flxc_gql_camel( $key ) ] = array( 'type' => flxc_gql_setting_type( $def[0], true ), 'description' => $def[1] );
}
$flxc_set_out['connections'] = array( 'type' => '[FlxChatLine!]!', 'description' => __( 'WhatsApp lines. No keys.', 'flx-chat' ) );
$flxc_set_out['labels']      = array( 'type' => '[FlxChatLabelRule!]!', 'description' => __( 'Chat labels.', 'flx-chat' ) );
$flxc_set_out['context']     = array( 'type' => 'FlxChatSettingsContext!', 'description' => __( 'Installed packages, defaults and choices.', 'flx-chat' ) );

flx_gql_type(
	'FlxChatSettings',
	array(
		'description' => __( 'Flux Chat settings. The webhook address and every API key are left out; see flxChatWebhookUrlReveal.', 'flx-chat' ),
		'fields'      => $flxc_set_out,
	)
);

flx_gql_type(
	'FlxChatSystemCheck',
	array(
		'description' => __( 'One line of the system check.', 'flx-chat' ),
		'fields'      => array(
			'id'          => array( 'type' => 'String!', 'description' => __( 'Id.', 'flx-chat' ) ),
			'group'       => array( 'type' => 'String!', 'description' => __( 'Group.', 'flx-chat' ) ),
			'label'       => array( 'type' => 'String!', 'description' => __( 'What is checked.', 'flx-chat' ) ),
			'status'      => array( 'type' => 'String!', 'description' => __( 'ok, warning, error or info.', 'flx-chat' ) ),
			'message'     => array( 'type' => 'String!', 'description' => __( 'Result.', 'flx-chat' ) ),
			'actionTab'   => array( 'type' => 'String!', 'description' => __( 'Settings tab that fixes it. Empty when none.', 'flx-chat' ) ),
			'actionUrl'   => array( 'type' => 'String!', 'description' => __( 'Where to fix it. Empty when none.', 'flx-chat' ) ),
			'actionLabel' => array( 'type' => 'String!', 'description' => __( 'Fix link text.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatSystemChecks',
	array(
		'description' => __( 'System check of Flux Chat.', 'flx-chat' ),
		'fields'      => array(
			'errorCount'   => array( 'type' => 'Int!', 'description' => __( 'Errors.', 'flx-chat' ) ),
			'warningCount' => array( 'type' => 'Int!', 'description' => __( 'Warnings.', 'flx-chat' ) ),
			'okCount'      => array( 'type' => 'Int!', 'description' => __( 'Passed.', 'flx-chat' ) ),
			'items'        => array( 'type' => '[FlxChatSystemCheck!]!', 'description' => __( 'Lines.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatGroup',
	array(
		'description' => __( 'A WhatsApp group.', 'flx-chat' ),
		'fields'      => array(
			'id'   => array( 'type' => 'String!', 'description' => __( 'Group id.', 'flx-chat' ) ),
			'name' => array( 'type' => 'String!', 'description' => __( 'Group name.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatAiModel',
	array(
		'description' => __( 'A model of the Flux AI catalog.', 'flx-chat' ),
		'fields'      => array(
			'id'       => array( 'type' => 'String!', 'description' => __( 'Model id.', 'flx-chat' ) ),
			'name'     => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-chat' ) ),
			'family'   => array( 'type' => 'String!', 'description' => __( 'Family.', 'flx-chat' ) ),
			'provider' => array( 'type' => 'String!', 'description' => __( 'Provider.', 'flx-chat' ) ),
			'context'  => array( 'type' => 'Int!', 'description' => __( 'Context size in tokens.', 'flx-chat' ), 'resolve' => static fn( $m ): int => FLXC_Val::int( flx_gql_arr( $m )['ctx'] ?? 0 ) ),
			'priceIn'  => array( 'type' => 'Float!', 'description' => __( 'US dollars per million input tokens.', 'flx-chat' ), 'resolve' => static fn( $m ): float => FLXC_Val::float( flx_gql_arr( $m )['in'] ?? 0 ) ),
			'priceOut' => array( 'type' => 'Float!', 'description' => __( 'US dollars per million output tokens.', 'flx-chat' ), 'resolve' => static fn( $m ): float => FLXC_Val::float( flx_gql_arr( $m )['out'] ?? 0 ) ),
			'tools'    => array( 'type' => 'Boolean!', 'description' => __( 'Calls tools.', 'flx-chat' ), 'resolve' => static fn( $m ): bool => FLXC_Val::bool( flx_gql_arr( $m )['tools'] ?? false ) ),
			'vision'   => array( 'type' => 'Boolean!', 'description' => __( 'Reads images.', 'flx-chat' ), 'resolve' => static fn( $m ): bool => FLXC_Val::bool( flx_gql_arr( $m )['vision'] ?? false ) ),
		),
	)
);

flx_gql_type(
	'FlxChatAiModels',
	array(
		'description' => __( 'A page of the model catalog.', 'flx-chat' ),
		'fields'      => array(
			'models'    => array( 'type' => '[FlxChatAiModel!]!', 'description' => __( 'Models, at most 50.', 'flx-chat' ) ),
			'total'     => array( 'type' => 'Int!', 'description' => __( 'Models that match.', 'flx-chat' ) ),
			'providers' => array( 'type' => '[FlxChatProvider!]!', 'description' => __( 'Providers that are ready.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatLineTest',
	array(
		'description' => __( 'Result of a line check. The webhook address has its key hidden.', 'flx-chat' ),
		'fields'      => array(
			'ok'         => array( 'type' => 'Boolean!', 'description' => __( 'The server answered.', 'flx-chat' ) ),
			'state'      => array( 'type' => 'String!', 'description' => __( 'Connection state.', 'flx-chat' ) ),
			'error'      => array( 'type' => 'String!', 'description' => __( 'Error. Empty when none.', 'flx-chat' ) ),
			'stored'     => array( 'type' => 'Int!', 'description' => __( 'Messages on the server. -1 when unknown.', 'flx-chat' ) ),
			'webhookOk'  => array( 'type' => 'Boolean!', 'description' => __( 'The server sends to this site.', 'flx-chat' ), 'resolve' => static fn( $r ): bool => FLXC_Val::bool( flx_gql_arr( $r )['webhook_ok'] ?? false ) ),
			'webhookUrl' => array( 'type' => 'String!', 'description' => __( 'Address the server sends to, key hidden.', 'flx-chat' ), 'resolve' => static fn( $r ): string => FLXC_Val::str( flx_gql_arr( $r )['webhook_url'] ?? '' ) ),
			'version'    => array( 'type' => 'String!', 'description' => __( 'Server version.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatReportRule',
	array(
		'description' => __( 'A scheduled WhatsApp report.', 'flx-chat' ),
		'fields'      => array(
			'id'             => array( 'type' => 'String!', 'description' => __( 'Rule id.', 'flx-chat' ) ),
			'title'          => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-chat' ) ),
			'active'         => array( 'type' => 'Boolean!', 'description' => __( 'Scheduled.', 'flx-chat' ) ),
			'audience'       => array( 'type' => 'FlxChatReportAudience!', 'description' => __( 'Who gets it.', 'flx-chat' ) ),
			'users'          => array( 'type' => '[Int!]!', 'description' => __( 'Selected people when the audience is USERS.', 'flx-chat' ) ),
			'content'        => array( 'type' => 'FlxChatReportContent!', 'description' => __( 'What it says.', 'flx-chat' ) ),
			'period'         => array( 'type' => 'FlxChatReportPeriod!', 'description' => __( 'Period covered.', 'flx-chat' ) ),
			'freq'           => array( 'type' => 'FlxChatReportFreq!', 'description' => __( 'How often.', 'flx-chat' ) ),
			'weekday'        => array( 'type' => 'Int!', 'description' => __( 'Day of week, 1 Monday to 7 Sunday.', 'flx-chat' ) ),
			'monthday'       => array( 'type' => 'Int!', 'description' => __( 'Day of month, 1 to 28.', 'flx-chat' ) ),
			'time'           => array( 'type' => 'String!', 'description' => __( 'Send time, HH:MM, site time zone.', 'flx-chat' ) ),
			'motivation'     => array( 'type' => 'String!', 'description' => __( 'Closing lines, one per line.', 'flx-chat' ) ),
			'recipientCount' => array( 'type' => 'Int!', 'description' => __( 'People with a number and permission.', 'flx-chat' ) ),
			'lastSent'       => array( 'type' => 'FlxDateTime', 'description' => __( 'Day of the last complete send. Null when never.', 'flx-chat' ), 'resolve' => static fn( $r ): ?array => flxc_gql_ts( flx_gql_arr( $r )['lastSent'] ?? 0 ) ),
		),
	)
);

flx_gql_type(
	'FlxChatReportPerson',
	array(
		'description' => __( 'A person a report can be sent to.', 'flx-chat' ),
		'fields'      => array(
			'databaseId' => array( 'type' => 'Int!', 'description' => __( 'User id.', 'flx-chat' ), 'resolve' => static fn( $p ): int => FLXC_Val::int( flx_gql_arr( $p )['id'] ?? 0 ) ),
			'name'       => array( 'type' => 'String!', 'description' => __( 'Name.', 'flx-chat' ) ),
			'hasNumber'  => array( 'type' => 'Boolean!', 'description' => __( 'A WhatsApp number is saved on the profile.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatReportDay',
	array(
		'description' => __( 'A weekday.', 'flx-chat' ),
		'fields'      => array(
			'value' => array( 'type' => 'Int!', 'description' => __( '1 Monday to 7 Sunday.', 'flx-chat' ) ),
			'label' => array( 'type' => 'String!', 'description' => __( 'Name in the site language.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatReports',
	array(
		'description' => __( 'The reports screen.', 'flx-chat' ),
		'fields'      => array(
			'on'     => array( 'type' => 'Boolean!', 'description' => __( 'Flux Roles and Flux Donate are active, so reports can be sent.', 'flx-chat' ) ),
			'days'   => array( 'type' => '[FlxChatReportDay!]!', 'description' => __( 'Weekdays.', 'flx-chat' ) ),
			'people' => array( 'type' => '[FlxChatReportPerson!]!', 'description' => __( 'People who can receive or view reports, at most 300.', 'flx-chat' ) ),
			'rules'  => array( 'type' => '[FlxChatReportRule!]!', 'description' => __( 'Rules.', 'flx-chat' ) ),
		),
	)
);

flx_gql_type(
	'FlxChatReportStatus',
	array(
		'description' => __( 'Delivery state of scheduled reports. Reading it sends nothing.', 'flx-chat' ),
		'fields'      => array(
			'on'              => array( 'type' => 'Boolean!', 'description' => __( 'Reports can be sent.', 'flx-chat' ) ),
			'due'             => array( 'type' => 'Boolean!', 'description' => __( 'A report is waiting to be sent.', 'flx-chat' ) ),
			'running'         => array( 'type' => 'Boolean!', 'description' => __( 'A send is running.', 'flx-chat' ) ),
			'nextAt'          => array( 'type' => 'FlxDateTime', 'description' => __( 'Next scheduled run. Null when none.', 'flx-chat' ), 'resolve' => static fn( $s ): ?array => flxc_gql_ts( flx_gql_arr( $s )['nextAt'] ?? 0 ) ),
			'loopbackError'   => array( 'type' => 'String!', 'description' => __( 'Last problem reaching the background request. Empty when none.', 'flx-chat' ) ),
			'loopbackErrorAt' => array( 'type' => 'FlxDateTime', 'description' => __( 'When that problem happened.', 'flx-chat' ), 'resolve' => static fn( $s ): ?array => flxc_gql_ts( flx_gql_arr( $s )['loopbackErrorAt'] ?? 0 ) ),
		),
	)
);

/* ---------- enums and inputs ---------- */

flx_gql_enum(
	'FlxChatReportAudience',
	array(
		'description' => __( 'Who gets a report.', 'flx-chat' ),
		'values'      => array(
			'RECEIVE' => array( 'value' => 'receive', 'description' => __( 'Everyone who can receive reports.', 'flx-chat' ) ),
			'VIEW'    => array( 'value' => 'view', 'description' => __( 'Those who can see the overall report.', 'flx-chat' ) ),
			'USERS'   => array( 'value' => 'users', 'description' => __( 'Selected people.', 'flx-chat' ) ),
		),
	)
);
flx_gql_enum(
	'FlxChatReportContent',
	array(
		'description' => __( 'What a report says.', 'flx-chat' ),
		'values'      => array(
			'OWN'     => array( 'value' => 'own', 'description' => __( 'The person\'s own donations.', 'flx-chat' ) ),
			'GENERAL' => array( 'value' => 'general', 'description' => __( 'Overall totals and per staff.', 'flx-chat' ) ),
		),
	)
);
flx_gql_enum(
	'FlxChatReportPeriod',
	array(
		'description' => __( 'Period a report covers.', 'flx-chat' ),
		'values'      => array(
			'TODAY'     => array( 'value' => 'today' ),
			'YESTERDAY' => array( 'value' => 'yesterday' ),
			'WEEK'      => array( 'value' => 'week', 'description' => __( 'Last 7 days.', 'flx-chat' ) ),
			'MONTH'     => array( 'value' => 'month', 'description' => __( 'This month.', 'flx-chat' ) ),
		),
	)
);
flx_gql_enum(
	'FlxChatReportFreq',
	array(
		'description' => __( 'How often a report goes out.', 'flx-chat' ),
		'values'      => array(
			'DAILY'   => array( 'value' => 'daily' ),
			'WEEKLY'  => array( 'value' => 'weekly' ),
			'MONTHLY' => array( 'value' => 'monthly' ),
		),
	)
);
flx_gql_enum(
	'FlxChatReportSendMode',
	array(
		'description' => __( 'Who a manual send goes to.', 'flx-chat' ),
		'values'      => array(
			'TEST' => array( 'value' => 'test', 'description' => __( 'Only the viewer.', 'flx-chat' ) ),
			'ALL'  => array( 'value' => 'all', 'description' => __( 'Every recipient, at most 25 per call.', 'flx-chat' ) ),
		),
	)
);

flx_gql_input(
	'FlxChatLabelInput',
	array(
		'description' => __( 'A label. A blank id makes one from the name.', 'flx-chat' ),
		'fields'      => array(
			'id'    => array( 'type' => 'String', 'description' => __( 'Id.', 'flx-chat' ) ),
			'name'  => array( 'type' => 'String!', 'description' => __( 'Name. A blank name drops the label.', 'flx-chat' ) ),
			'color' => array( 'type' => 'String', 'description' => __( 'Color (hex).', 'flx-chat' ) ),
		),
	)
);

flx_gql_input(
	'FlxChatLineInput',
	array(
		'description' => __( 'A line to keep. Lines left out of the list are removed. The API key is write-only: empty keeps the saved one, "__clear__" removes it.', 'flx-chat' ),
		'fields'      => array(
			'id'           => array( 'type' => 'String', 'description' => __( 'Line id. Blank adds a new line.', 'flx-chat' ) ),
			'delete'       => array( 'type' => 'Boolean', 'description' => __( 'Remove this line.', 'flx-chat' ) ),
			'source'       => array( 'type' => 'String', 'description' => __( 'own, flxi:<id> or flxs:<id>.', 'flx-chat' ) ),
			'label'        => array( 'type' => 'String', 'description' => __( 'Name.', 'flx-chat' ) ),
			'baseUrl'      => array( 'type' => 'String', 'description' => __( 'Evolution API address.', 'flx-chat' ) ),
			'instance'     => array( 'type' => 'String', 'description' => __( 'Instance name.', 'flx-chat' ) ),
			'apiKey'       => array( 'type' => 'String', 'description' => __( 'API key. Write-only.', 'flx-chat' ) ),
			'rotateSecret' => array( 'type' => 'Boolean', 'description' => __( 'Make a new webhook key.', 'flx-chat' ) ),
		),
	)
);

flx_gql_input(
	'FlxChatReportInput',
	array(
		'description' => __( 'The editable fields of a report rule.', 'flx-chat' ),
		'fields'      => array(
			'id'         => array( 'type' => 'String!', 'description' => __( 'Rule id.', 'flx-chat' ) ),
			'title'      => array( 'type' => 'String', 'description' => __( 'Name.', 'flx-chat' ) ),
			'active'     => array( 'type' => 'Boolean', 'description' => __( 'Scheduled.', 'flx-chat' ) ),
			'audience'   => array( 'type' => 'FlxChatReportAudience', 'description' => __( 'Who gets it.', 'flx-chat' ) ),
			'users'      => array( 'type' => '[Int!]', 'description' => __( 'Selected people.', 'flx-chat' ) ),
			'content'    => array( 'type' => 'FlxChatReportContent', 'description' => __( 'What it says.', 'flx-chat' ) ),
			'period'     => array( 'type' => 'FlxChatReportPeriod', 'description' => __( 'Period.', 'flx-chat' ) ),
			'freq'       => array( 'type' => 'FlxChatReportFreq', 'description' => __( 'How often.', 'flx-chat' ) ),
			'weekday'    => array( 'type' => 'Int', 'description' => __( 'Day of week.', 'flx-chat' ) ),
			'monthday'   => array( 'type' => 'Int', 'description' => __( 'Day of month.', 'flx-chat' ) ),
			'time'       => array( 'type' => 'String', 'description' => __( 'HH:MM.', 'flx-chat' ) ),
			'motivation' => array( 'type' => 'String', 'description' => __( 'Closing lines.', 'flx-chat' ) ),
		),
	)
);

/* ---------- settings ---------- */

flx_gql_query(
	'flxChatSettings',
	array(
		'type'        => 'FlxChatSettings!',
		'description' => __( 'Settings. No API key and no webhook address.', 'flx-chat' ),
		'cap'         => $flxc_gql_manage_cfg,
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXC_Settings_Service::CACHE );
			return flxc_gql_settings_view();
		},
	)
);

$flxc_save_input = array(
	'labels'      => array( 'type' => '[FlxChatLabelInput!]', 'description' => __( 'The labels to keep. Replaces the list.', 'flx-chat' ) ),
	'connections' => array( 'type' => '[FlxChatLineInput!]', 'description' => __( 'The lines to keep. Replaces the list.', 'flx-chat' ) ),
);
foreach ( flxc_gql_setting_defs() as $key => $def ) {
	$flxc_save_input[ flxc_gql_camel( $key ) ] = array( 'type' => flxc_gql_setting_type( $def[0], false ), 'description' => $def[1] );
}

flx_gql_mutation(
	'flxChatSettingsSave',
	array(
		'description' => __( 'Save the fields that are sent; the rest keep their value. Values are limited and cleaned exactly as the classic settings page does.', 'flx-chat' ),
		'cap'         => $flxc_gql_manage_cfg,
		'input'       => $flxc_save_input,
		'output'      => array( 'settings' => array( 'type' => 'FlxChatSettings!', 'description' => __( 'Settings after the save.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ): array {
			FLXC_Settings_Service::apply( '', flxc_gql_settings_input( flx_gql_arr( $input ) ) );
			return array( 'settings' => flxc_gql_settings_view() );
		},
	)
);

flx_gql_mutation(
	'flxChatWebhookUrlReveal',
	array(
		'description' => __( 'The address Evolution API must send to. It carries the line\'s secret key, so only a manager gets it and it is never cached or listed with the settings.', 'flx-chat' ),
		'cap'         => $flxc_gql_manage_cfg,
		'input'       => array(
			'id'         => array( 'type' => 'String!', 'description' => __( 'Line id.', 'flx-chat' ) ),
			'regenerate' => array( 'type' => 'Boolean', 'description' => __( 'Make a new key first. The old address stops working.', 'flx-chat' ) ),
		),
		'output'      => array( 'url' => array( 'type' => 'String!', 'description' => __( 'Webhook address.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ) {
			return FLXC_Settings_Service::webhook_url_reveal( FLXC_Val::str( $input['id'] ), ! empty( $input['regenerate'] ) );
		},
	)
);

flx_gql_mutation(
	'flxChatConnectionTest',
	array(
		'description' => __( 'Ask the server for the line state, its webhook and message count.', 'flx-chat' ),
		'cap'         => $flxc_gql_manage_cfg,
		'input'       => array( 'id' => array( 'type' => 'String!', 'description' => __( 'Line id.', 'flx-chat' ) ) ),
		'output'      => array( 'result' => array( 'type' => 'FlxChatLineTest!', 'description' => __( 'Result.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXC_Settings_Service::connection_test( FLXC_Val::str( $input['id'] ) );
			return is_wp_error( $r ) ? $r : array( 'result' => $r );
		},
	)
);

flx_gql_mutation(
	'flxChatConnectionWebhook',
	array(
		'description' => __( 'Point the server\'s webhook at this site.', 'flx-chat' ),
		'cap'         => $flxc_gql_manage_cfg,
		'input'       => array( 'id' => array( 'type' => 'String!', 'description' => __( 'Line id.', 'flx-chat' ) ) ),
		'output'      => array(
			'ok'    => array( 'type' => 'Boolean!', 'description' => __( 'The server accepted it.', 'flx-chat' ) ),
			'error' => array( 'type' => 'String!', 'description' => __( 'Error. Empty when none.', 'flx-chat' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXC_Settings_Service::connection_webhook( FLXC_Val::str( $input['id'] ) );
			return is_wp_error( $r ) ? $r : array( 'ok' => FLXC_Val::bool( $r['ok'] ), 'error' => FLXC_Val::str( $r['error'] ) );
		},
	)
);

flx_gql_mutation(
	'flxChatConnectionQr',
	array(
		'description' => __( 'Ask for a pairing QR code. A line that is already open says so.', 'flx-chat' ),
		'cap'         => $flxc_gql_manage_cfg,
		'input'       => array( 'id' => array( 'type' => 'String!', 'description' => __( 'Line id.', 'flx-chat' ) ) ),
		'output'      => array(
			'ok'    => array( 'type' => 'Boolean!', 'description' => __( 'The server answered.', 'flx-chat' ) ),
			'qr'    => array( 'type' => 'String!', 'description' => __( 'QR image as base64. Empty when none.', 'flx-chat' ) ),
			'code'  => array( 'type' => 'String!', 'description' => __( 'Pairing code. Empty when none.', 'flx-chat' ) ),
			'state' => array( 'type' => 'String!', 'description' => __( 'Line state.', 'flx-chat' ) ),
			'error' => array( 'type' => 'String!', 'description' => __( 'Error. Empty when none.', 'flx-chat' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXC_Settings_Service::connection_qr( FLXC_Val::str( $input['id'] ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			return array(
				'ok'    => FLXC_Val::bool( $r['ok'] ?? false ),
				'qr'    => FLXC_Val::str( $r['qr'] ?? '' ),
				'code'  => FLXC_Val::str( $r['code'] ?? '' ),
				'state' => FLXC_Val::str( $r['state'] ?? '' ),
				'error' => FLXC_Val::str( $r['error'] ?? '' ),
			);
		},
	)
);

flx_gql_mutation(
	'flxChatConnectionImport',
	array(
		'description' => __( 'Bring in the latest chats and their recent messages from the server.', 'flx-chat' ),
		'cap'         => $flxc_gql_manage_cfg,
		'input'       => array(
			'id'    => array( 'type' => 'String!', 'description' => __( 'Line id.', 'flx-chat' ) ),
			'chats' => array( 'type' => 'Int', 'description' => __( 'How many chats, 1 to 200. 30 when omitted.', 'flx-chat' ) ),
		),
		'output'      => array(
			'ok'       => array( 'type' => 'Boolean!', 'description' => __( 'The import ran.', 'flx-chat' ) ),
			'chats'    => array( 'type' => 'Int!', 'description' => __( 'Chats brought in.', 'flx-chat' ) ),
			'messages' => array( 'type' => 'Int!', 'description' => __( 'Messages brought in.', 'flx-chat' ) ),
			'error'    => array( 'type' => 'String!', 'description' => __( 'Error. Empty when none.', 'flx-chat' ) ),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXC_Settings_Service::connection_import( FLXC_Val::str( $input['id'] ), FLXC_Val::int( $input['chats'] ?? 30, 30 ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			return array(
				'ok'       => FLXC_Val::bool( $r['ok'] ?? false ),
				'chats'    => FLXC_Val::int( $r['chats'] ?? 0 ),
				'messages' => FLXC_Val::int( $r['messages'] ?? 0 ),
				'error'    => FLXC_Val::str( $r['error'] ?? '' ),
			);
		},
	)
);

flx_gql_query(
	'flxChatSystemChecks',
	array(
		'type'        => 'FlxChatSystemChecks!',
		'description' => __( 'The system check table. Read only.', 'flx-chat' ),
		'cap'         => $flxc_gql_manage_cfg,
		'resolve'     => static fn(): array => FLXC_Settings_Service::system_checks(),
	)
);

flx_gql_query(
	'flxChatGroups',
	array(
		'type'        => '[FlxChatGroup!]!',
		'description' => __( 'WhatsApp groups of the notification line. refresh asks the server again.', 'flx-chat' ),
		'cap'         => $flxc_gql_manage_cfg,
		'args'        => array( 'refresh' => array( 'type' => 'Boolean', 'description' => __( 'Ask the server instead of the cache.', 'flx-chat' ) ) ),
		'resolve'     => static function ( $root, array $args ): array {
			unset( $root );
			return FLXC_Settings_Service::groups( ! empty( $args['refresh'] ) );
		},
	)
);

flx_gql_query(
	'flxChatAiModels',
	array(
		'type'        => 'FlxChatAiModels!',
		'description' => __( 'Models of the Flux AI catalog, matching search, at most 50 per call. Models in ids are always included.', 'flx-chat' ),
		'cap'         => $flxc_gql_manage_cfg,
		'args'        => array(
			'search'   => array( 'type' => 'String', 'description' => __( 'Text in the id or name.', 'flx-chat' ) ),
			'provider' => array( 'type' => 'String', 'description' => __( 'Only this provider.', 'flx-chat' ) ),
			'ids'      => array( 'type' => '[String!]', 'description' => __( 'Models to include whatever the search says (the selected ones).', 'flx-chat' ) ),
			'first'    => array( 'type' => 'Int', 'description' => __( 'Page size, at most 50.', 'flx-chat' ) ),
			'fresh'    => array( 'type' => 'Boolean', 'description' => __( 'Ask the providers instead of the cache.', 'flx-chat' ) ),
		),
		'resolve'     => static function ( $root, array $args ) {
			unset( $root );
			$r = FLXC_Settings_Service::ai_models( ! empty( $args['fresh'] ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$search = strtolower( trim( FLXC_Val::str( $args['search'] ?? '' ) ) );
			$prov   = FLXC_Val::str( $args['provider'] ?? '' );
			$want   = array_flip( array_map( static fn( $v ): string => FLXC_Val::str( $v ), (array) ( $args['ids'] ?? array() ) ) );
			$first  = flx_gql_page_size( flx_gql_arr( $args ), 50 );
			$match  = array();
			$pinned = array();
			foreach ( (array) $r['models'] as $m ) {
				if ( ! is_array( $m ) ) {
					continue;
				}
				$id = FLXC_Val::str( $m['id'] ?? '' );
				if ( isset( $want[ $id ] ) ) {
					$pinned[] = $m;
					continue;
				}
				if ( '' !== $prov && FLXC_Val::str( $m['provider'] ?? '' ) !== $prov ) {
					continue;
				}
				if ( '' !== $search && false === strpos( strtolower( $id . ' ' . FLXC_Val::str( $m['name'] ?? '' ) ), $search ) ) {
					continue;
				}
				$match[] = $m;
			}
			$providers = array();
			foreach ( (array) $r['providers'] as $p ) {
				$p           = flx_gql_arr( $p );
				$providers[] = array( 'id' => FLXC_Val::str( $p['id'] ?? '' ), 'label' => FLXC_Val::str( $p['label'] ?? '' ) );
			}
			return array(
				'models'    => array_merge( $pinned, array_slice( $match, 0, $first ) ),
				'total'     => count( $match ),
				'providers' => $providers,
			);
		},
	)
);

flx_gql_mutation(
	'flxChatAiTest',
	array(
		'description' => __( 'Send one short test message to the configured model. It spends a few tokens.', 'flx-chat' ),
		'cap'         => $flxc_gql_manage_cfg,
		'input'       => array(),
		'output'      => array(
			'text' => array( 'type' => 'String!', 'description' => __( 'The model\'s answer.', 'flx-chat' ) ),
			'ms'   => array( 'type' => 'Int!', 'description' => __( 'Milliseconds it took.', 'flx-chat' ) ),
		),
		'resolve'     => static function () {
			return FLXC_Settings_Service::ai_test();
		},
	)
);

/* ---------- reports ---------- */

flx_gql_query(
	'flxChatReports',
	array(
		'type'        => 'FlxChatReports!',
		'description' => __( 'Report rules and the people they can reach.', 'flx-chat' ),
		'cap'         => $flxc_gql_reports,
		'resolve'     => static function (): array {
			flx_gql_cache_tag( FLXC_Report_Service::CACHE );
			return FLXC_Report_Service::screen();
		},
	)
);

flx_gql_query(
	'flxChatReportPreview',
	array(
		'type'        => 'String!',
		'description' => __( 'The text of a report as the viewer would get it. Sends nothing.', 'flx-chat' ),
		'cap'         => $flxc_gql_reports,
		'args'        => array( 'id' => array( 'type' => 'String!', 'description' => __( 'Rule id.', 'flx-chat' ) ) ),
		'resolve'     => static function ( $root, array $args ) {
			unset( $root );
			$rule = FLXC_Report_Service::rule( FLXC_Val::str( $args['id'] ) );
			if ( null === $rule ) {
				return new WP_Error( 'flxc_report_missing', __( 'Report not found.', 'flx-chat' ), array( 'status' => 404 ) );
			}
			return FLXC_Report_Service::preview( $rule );
		},
	)
);

flx_gql_query(
	'flxChatReportStatus',
	array(
		'type'        => 'FlxChatReportStatus!',
		'description' => __( 'Whether a report is due, running or waiting for the background request. Sends nothing.', 'flx-chat' ),
		'cap'         => $flxc_gql_reports,
		'resolve'     => static fn(): array => FLXC_Report_Service::status(),
	)
);

/**
 * Reports payload.
 *
 * @return array{reports: array<string,mixed>}
 */
function flxc_gql_reports_payload(): array {
	return array( 'reports' => FLXC_Report_Service::screen() );
}

flx_gql_mutation(
	'flxChatReportsSave',
	array(
		'description' => __( 'Save the report rules that are sent. Rules not in the list keep their value.', 'flx-chat' ),
		'cap'         => $flxc_gql_reports,
		'input'       => array( 'rules' => array( 'type' => '[FlxChatReportInput!]!', 'description' => __( 'Rules.', 'flx-chat' ) ) ),
		'output'      => array( 'reports' => array( 'type' => 'FlxChatReports!', 'description' => __( 'Reports after the save.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ) {
			$list   = FLXC_Report_Service::rules();
			$stored = array();
			foreach ( $list as $r ) {
				$stored[ FLXC_Val::str( $r['id'] ) ] = $r;
			}
			$forms = array();
			foreach ( (array) $input['rules'] as $rule ) {
				$rule = flx_gql_arr( $rule );
				$id   = FLXC_Val::str( $rule['id'] ?? '' );
				if ( ! isset( $stored[ $id ] ) ) {
					return new WP_Error( 'flxc_report_missing', __( 'Report not found.', 'flx-chat' ), array( 'status' => 404 ) );
				}
				// A field that is not sent keeps its stored value; the classic form always sends all of them.
				$f = array_intersect_key( $stored[ $id ], array_flip( array( 'title', 'active', 'audience', 'content', 'period', 'freq', 'weekday', 'monthday', 'time', 'motivation', 'users' ) ) );
				foreach ( array_keys( $f ) as $k ) {
					if ( array_key_exists( $k, $rule ) && null !== $rule[ $k ] ) {
						$f[ $k ] = $rule[ $k ];
					}
				}
				$forms[ $id ] = $f;
			}
			flxc_reports_save( FLXC_Report_Service::apply_forms( $list, $forms ) );
			FLXC_Report_Service::changed();
			return flxc_gql_reports_payload();
		},
	)
);

flx_gql_mutation(
	'flxChatReportAdd',
	array(
		'description' => __( 'Add an inactive report rule.', 'flx-chat' ),
		'cap'         => $flxc_gql_reports,
		'input'       => array(),
		'output'      => array(
			'id'      => array( 'type' => 'String!', 'description' => __( 'The new rule.', 'flx-chat' ) ),
			'reports' => array( 'type' => 'FlxChatReports!', 'description' => __( 'Reports after the change.', 'flx-chat' ) ),
		),
		'resolve'     => static function (): array {
			$list = FLXC_Report_Service::with_new( FLXC_Report_Service::rules() );
			$last = end( $list );
			flxc_reports_save( $list );
			FLXC_Report_Service::changed();
			return array( 'id' => FLXC_Val::str( is_array( $last ) ? ( $last['id'] ?? '' ) : '' ) ) + flxc_gql_reports_payload();
		},
	)
);

flx_gql_mutation(
	'flxChatReportDelete',
	array(
		'description' => __( 'Delete a report rule.', 'flx-chat' ),
		'cap'         => $flxc_gql_reports,
		'input'       => array( 'id' => array( 'type' => 'String!', 'description' => __( 'Rule id.', 'flx-chat' ) ) ),
		'output'      => array( 'reports' => array( 'type' => 'FlxChatReports!', 'description' => __( 'Reports after the change.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ) {
			$id = FLXC_Val::str( $input['id'] );
			if ( null === FLXC_Report_Service::rule( $id ) ) {
				return new WP_Error( 'flxc_report_missing', __( 'Report not found.', 'flx-chat' ), array( 'status' => 404 ) );
			}
			flxc_reports_save( FLXC_Report_Service::without( FLXC_Report_Service::rules(), $id ) );
			FLXC_Report_Service::changed();
			return flxc_gql_reports_payload();
		},
	)
);

flx_gql_mutation(
	'flxChatReportSend',
	array(
		'description' => __( 'Send a report now: a test to the viewer, or to every recipient (at most 25 per call).', 'flx-chat' ),
		'cap'         => $flxc_gql_reports,
		'input'       => array(
			'id'   => array( 'type' => 'String!', 'description' => __( 'Rule id.', 'flx-chat' ) ),
			'mode' => array( 'type' => 'FlxChatReportSendMode!', 'description' => __( 'Who gets it.', 'flx-chat' ) ),
		),
		'output'      => array( 'sent' => array( 'type' => 'Int', 'description' => __( 'Messages sent. Null when the request was refused.', 'flx-chat' ) ) ),
		'resolve'     => static function ( array $input ) {
			$rule = FLXC_Report_Service::rule( FLXC_Val::str( $input['id'] ) );
			if ( null === $rule ) {
				return new WP_Error( 'flxc_report_missing', __( 'Report not found.', 'flx-chat' ), array( 'status' => 404 ) );
			}
			if ( 'test' === $input['mode'] ) {
				$n = FLXC_Report_Service::send_test( $rule );
				if ( is_wp_error( $n ) ) {
					return $n;
				}
				return array( 'sent' => $n );
			}
			return array( 'sent' => FLXC_Report_Service::send_now( $rule ) );
		},
	)
);

flx_gql_mutation(
	'flxChatReportRun',
	array(
		'description' => __( 'Run the report schedule now on the background request (the same one chat jobs use). Reports that are not due are not sent.', 'flx-chat' ),
		'cap'         => $flxc_gql_reports,
		'input'       => array(),
		'output'      => array(
			'started' => array( 'type' => 'Boolean!', 'description' => __( 'The background request was accepted.', 'flx-chat' ) ),
			'status'  => array( 'type' => 'FlxChatReportStatus!', 'description' => __( 'State after the request.', 'flx-chat' ) ),
		),
		'resolve'     => static function (): array {
			$started = FLXC_Report_Service::run();
			return array( 'started' => $started, 'status' => FLXC_Report_Service::status() );
		},
	)
);
