<?php
/**
 * Panel responses and settings (Flux GraphQL kit). Loaded on flx_gql_register.
 *
 * Every root field needs the panel capability: `Emsfb` or `manage_options`, the same test the
 * classic panel AJAX handlers and the FluxApp REST routes use (`user_permission_efb_admin_dashboard`).
 * `Emsfb` is mixed case, so the catalogue of the kit drops it (`^[a-z0-9_]+$`); the check here is
 * still `current_user_can`. Secrets are write-only: a read says whether one is saved.
 *
 * @package FLX_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Service result or WP_Error as a typed GraphQL error.
 *
 * @template T
 * @param T|WP_Error $result Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxf_gql_ok( $result ) {
	if ( $result instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $result );
	}
	return $result;
}

/**
 * The panel capability, as a kit cap value.
 *
 * @return mixed
 */
function flxf_gql_panel_cap() {
	return flx_gql_any_cap( 'Emsfb', 'manage_options' );
}

/**
 * A submission id from an `ID` argument.
 */
function flxf_gql_id( mixed $value ): int {
	return absint( flx_gql_int( $value ) );
}

flx_gql_enum(
	'FlxFormResponseStatus',
	array(
		'description' => __( 'Where a response stands in the panel.', 'flx-forms' ),
		'values'      => array(
			'UNREAD'           => array( 'value' => 'unread', 'description' => __( 'New, nobody has opened it.', 'flx-forms' ) ),
			'READ'             => array( 'value' => 'read', 'description' => __( 'Opened.', 'flx-forms' ) ),
			'PENDING_PAYMENT'  => array( 'value' => 'pending_payment', 'description' => __( 'A payment form that was not paid yet. Never listed.', 'flx-forms' ) ),
			'UNREAD_FOLLOWUP'  => array( 'value' => 'unread_followup', 'description' => __( 'The visitor wrote again and nobody has read it.', 'flx-forms' ) ),
			'READ_FOLLOWUP'    => array( 'value' => 'read_followup', 'description' => __( 'A follow-up that was read, or a closed ticket.', 'flx-forms' ) ),
		),
	)
);

flx_gql_enum(
	'FlxFormReplyAuthor',
	array(
		'description' => __( 'Who wrote a reply.', 'flx-forms' ),
		'values'      => array(
			'USER'    => array( 'value' => 'user', 'description' => __( 'A signed-in user, usually staff.', 'flx-forms' ) ),
			'SUPPORT' => array( 'value' => 'support', 'description' => __( 'Staff replying with the emailed admin code.', 'flx-forms' ) ),
			'GUEST'   => array( 'value' => 'guest', 'description' => __( 'The visitor.', 'flx-forms' ) ),
		),
	)
);

flx_gql_enum(
	'FlxFormReplyKind',
	array(
		'description' => __( 'What a reply row is.', 'flx-forms' ),
		'values'      => array(
			'REPLY'  => array( 'value' => 'reply', 'description' => __( 'A message.', 'flx-forms' ) ),
			'CLOSED' => array( 'value' => 'closed', 'description' => __( 'The ticket was closed.', 'flx-forms' ) ),
			'OPENED' => array( 'value' => 'opened', 'description' => __( 'The ticket was opened again.', 'flx-forms' ) ),
		),
	)
);

flx_gql_enum(
	'FlxFormReplyState',
	array(
		'description' => __( 'What an administrator reply does to the ticket.', 'flx-forms' ),
		'values'      => array(
			'REPLY' => array( 'value' => 'reply', 'description' => __( 'Only send the message.', 'flx-forms' ) ),
			'CLOSE' => array( 'value' => 'close', 'description' => __( 'Close the ticket (a message is optional).', 'flx-forms' ) ),
			'OPEN'  => array( 'value' => 'open', 'description' => __( 'Open a closed ticket again (a message is optional).', 'flx-forms' ) ),
		),
	)
);

flx_gql_enum(
	'FlxFormsTrackCodeStyle',
	array(
		'description' => __( 'How new tracking codes look.', 'flx-forms' ),
		'values'      => array(
			'DATE_NUM'         => array( 'value' => 'date_num', 'description' => __( 'Date and number.', 'flx-forms' ) ),
			'DATE_LOCAL_MIX'   => array( 'value' => 'date_local_mix', 'description' => __( 'Date, local-language letters and numbers.', 'flx-forms' ) ),
			'DATE_LOCAL_ALPHA' => array( 'value' => 'date_local_alpha', 'description' => __( 'Date and local-language letters.', 'flx-forms' ) ),
			'DATE_EN_MIX'      => array( 'value' => 'date_en_mix', 'description' => __( 'Date, English letters and numbers.', 'flx-forms' ) ),
			'DATE_LOCAL_NUM'   => array( 'value' => 'date_local_num', 'description' => __( 'Date, local-language digits.', 'flx-forms' ) ),
			'UNIQUE_NUM'       => array( 'value' => 'unique_num', 'description' => __( 'A unique number.', 'flx-forms' ) ),
			'LOCAL_MIX'        => array( 'value' => 'local_mix', 'description' => __( 'Local-language letters and numbers.', 'flx-forms' ) ),
		),
	)
);

flx_gql_enum(
	'FlxFormsSmsService',
	array(
		'description' => __( 'Which service sends the SMS notices.', 'flx-forms' ),
		'values'      => array(
			'NONE'   => array( 'value' => 'null', 'description' => __( 'No SMS.', 'flx-forms' ) ),
			'WP_SMS' => array( 'value' => 'wpsms', 'description' => __( 'The WP SMS plugin.', 'flx-forms' ) ),
			'SERVICE' => array( 'value' => 'ws.team', 'description' => __( 'The built-in SMS service.', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxFormListItem',
	array(
		'description' => __( 'One form of the panel list with how many responses it has.', 'flx-forms' ),
		'fields'      => array(
			'id'        => array( 'type' => 'ID!', 'description' => __( 'Form id.', 'flx-forms' ) ),
			'title'     => array( 'type' => 'String!', 'description' => __( 'Form name.', 'flx-forms' ) ),
			'formType'  => array( 'type' => 'FlxFormType!', 'description' => __( 'Kind of form.', 'flx-forms' ) ),
			'shortcode' => array( 'type' => 'String!', 'description' => __( 'Shortcode that puts the form on a page.', 'flx-forms' ) ),
			'createdAt' => array( 'type' => 'FlxDateTime', 'description' => __( 'When the form was created. Null when the date is missing.', 'flx-forms' ) ),
			'total'     => array( 'type' => 'Int!', 'description' => __( 'Responses received.', 'flx-forms' ) ),
			'unread'    => array( 'type' => 'Int!', 'description' => __( 'Responses nobody has read.', 'flx-forms' ) ),
		),
	)
);
flx_gql_connection( 'FlxFormListItem' );

flx_gql_type(
	'FlxFormResponseField',
	array(
		'description' => __( 'One answer of a response.', 'flx-forms' ),
		'fields'      => array(
			'label'  => array( 'type' => 'String!', 'description' => __( 'Field label.', 'flx-forms' ) ),
			'value'  => array( 'type' => 'String!', 'description' => __( 'What the visitor sent. A file field carries its URL.', 'flx-forms' ) ),
			'isFile' => array(
				'type'        => 'Boolean!',
				'description' => __( 'The value is the URL of an uploaded file.', 'flx-forms' ),
				'resolve'     => static fn( $field ): bool => is_array( $field ) && ! empty( $field['file'] ),
			),
		),
	)
);

flx_gql_type(
	'FlxFormResponseReply',
	array(
		'description' => __( 'One reply of a response conversation.', 'flx-forms' ),
		'fields'      => array(
			'id'          => array( 'type' => 'ID!', 'description' => __( 'Reply id.', 'flx-forms' ) ),
			'by'          => array( 'type' => 'String!', 'description' => __( 'Display name of the author.', 'flx-forms' ) ),
			'author'      => array( 'type' => 'FlxFormReplyAuthor!', 'description' => __( 'Kind of author.', 'flx-forms' ) ),
			'kind'        => array( 'type' => 'FlxFormReplyKind!', 'description' => __( 'Message, or a ticket state change.', 'flx-forms' ) ),
			'body'        => array( 'type' => 'String!', 'description' => __( 'Text. Empty for a reply that only carries files.', 'flx-forms' ) ),
			'attachments' => array( 'type' => '[String!]!', 'description' => __( 'File URLs. Empty when there are none.', 'flx-forms' ) ),
			'createdAt'   => array( 'type' => 'FlxDateTime!', 'description' => __( 'When the reply was stored.', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxFormResponseItem',
	array(
		'description' => __( 'A response in a list.', 'flx-forms' ),
		'fields'      => array(
			'id'        => array( 'type' => 'ID!', 'description' => __( 'Response id.', 'flx-forms' ) ),
			'formId'    => array( 'type' => 'ID!', 'description' => __( 'Form id.', 'flx-forms' ) ),
			'formTitle' => array( 'type' => 'String!', 'description' => __( 'Form name. The title saved with the response when the form is gone.', 'flx-forms' ) ),
			'formType'  => array( 'type' => 'FlxFormType!', 'description' => __( 'Kind of form. A form that is gone reads as a plain form.', 'flx-forms' ) ),
			'trackCode' => array( 'type' => 'String!', 'description' => __( 'Tracking code.', 'flx-forms' ) ),
			'status'    => array( 'type' => 'FlxFormResponseStatus!', 'description' => __( 'Read state.', 'flx-forms' ) ),
			'createdAt' => array( 'type' => 'FlxDateTime!', 'description' => __( 'When the response was sent.', 'flx-forms' ) ),
			'preview'   => array( 'type' => 'String!', 'description' => __( 'The first filled answers, short.', 'flx-forms' ) ),
			'fields'    => array( 'type' => '[FlxFormResponseField!]!', 'description' => __( 'Answers. Survey charts count these.', 'flx-forms' ) ),
		),
	)
);
flx_gql_connection( 'FlxFormResponseItem' );

flx_gql_type(
	'FlxFormResponse',
	array(
		'description' => __( 'One response with its answers and the conversation.', 'flx-forms' ),
		'fields'      => array(
			'id'        => array( 'type' => 'ID!', 'description' => __( 'Response id.', 'flx-forms' ) ),
			'formId'    => array( 'type' => 'ID!', 'description' => __( 'Form id.', 'flx-forms' ) ),
			'formTitle' => array( 'type' => 'String!', 'description' => __( 'Form name. The title saved with the response when the form is gone.', 'flx-forms' ) ),
			'formType'  => array( 'type' => 'FlxFormType!', 'description' => __( 'Kind of form. A form that is gone reads as a plain form.', 'flx-forms' ) ),
			'trackCode' => array( 'type' => 'String!', 'description' => __( 'Tracking code.', 'flx-forms' ) ),
			'status'    => array( 'type' => 'FlxFormResponseStatus!', 'description' => __( 'Read state.', 'flx-forms' ) ),
			'createdAt' => array( 'type' => 'FlxDateTime!', 'description' => __( 'When the response was sent.', 'flx-forms' ) ),
			'readAt'    => array( 'type' => 'FlxDateTime', 'description' => __( 'When it was opened. Null while it is unread.', 'flx-forms' ) ),
			'preview'   => array( 'type' => 'String!', 'description' => __( 'The first filled answers, short.', 'flx-forms' ) ),
			'fields'    => array( 'type' => '[FlxFormResponseField!]!', 'description' => __( 'Answers.', 'flx-forms' ) ),
			'replies'   => array( 'type' => '[FlxFormResponseReply!]!', 'description' => __( 'Conversation, oldest first.', 'flx-forms' ) ),
			'open'      => array( 'type' => 'Boolean!', 'description' => __( 'The ticket is open. False after an administrator closed it.', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxFormsResponseStyle',
	array(
		'description' => __( 'Colours and fonts of the public response box.', 'flx-forms' ),
		'fields'      => array(
			'preset'      => array( 'type' => 'String!', 'description' => __( 'Palette preset the colours came from.', 'flx-forms' ) ),
			'brandColor'  => array( 'type' => 'String!', 'description' => __( 'The colour a brand preset derives from.', 'flx-forms' ) ),
			'primary'     => array( 'type' => 'String!', 'description' => __( 'Primary colour.', 'flx-forms' ) ),
			'primaryDark' => array( 'type' => 'String!', 'description' => __( 'Primary colour, dark.', 'flx-forms' ) ),
			'accent'      => array( 'type' => 'String!', 'description' => __( 'Accent colour.', 'flx-forms' ) ),
			'text'        => array( 'type' => 'String!', 'description' => __( 'Text colour.', 'flx-forms' ) ),
			'textMuted'   => array( 'type' => 'String!', 'description' => __( 'Muted text colour.', 'flx-forms' ) ),
			'bgCard'      => array( 'type' => 'String!', 'description' => __( 'Card background.', 'flx-forms' ) ),
			'bgMeta'      => array( 'type' => 'String!', 'description' => __( 'Meta strip background.', 'flx-forms' ) ),
			'bgTrack'     => array( 'type' => 'String!', 'description' => __( 'Tracker background.', 'flx-forms' ) ),
			'bgResp'      => array( 'type' => 'String!', 'description' => __( 'Reply background.', 'flx-forms' ) ),
			'bgEditor'    => array( 'type' => 'String!', 'description' => __( 'Editor background.', 'flx-forms' ) ),
			'editorText'  => array( 'type' => 'String!', 'description' => __( 'Editor text colour.', 'flx-forms' ) ),
			'editorPh'    => array( 'type' => 'String!', 'description' => __( 'Editor placeholder colour.', 'flx-forms' ) ),
			'btnText'     => array( 'type' => 'String!', 'description' => __( 'Button text colour.', 'flx-forms' ) ),
			'fontFamily'  => array( 'type' => 'String!', 'description' => __( 'Font family. "inherit" follows the site.', 'flx-forms' ) ),
			'fontSize'    => array( 'type' => 'String!', 'description' => __( 'Font size, for example 0.9rem.', 'flx-forms' ) ),
			'customFont'  => array( 'type' => 'String!', 'description' => __( 'Custom font name. Empty when none.', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxFormsSettings',
	array(
		'description' => __( 'Panel settings. Keys and tokens are never returned; "saved" flags say whether one is stored.', 'flx-forms' ),
		'fields'      => array(
			'bootstrap'           => array( 'type' => 'Boolean!', 'description' => __( 'The theme already loads Bootstrap, so forms use its styles.', 'flx-forms' ) ),
			'osLocationPicker'    => array( 'type' => 'Boolean!', 'description' => __( 'Use the OpenStreetMap location picker.', 'flx-forms' ) ),
			'version'             => array( 'type' => 'String!', 'description' => __( 'Form builder version stored with the settings.', 'flx-forms' ) ),
			'sessionDuration'     => array( 'type' => 'Int!', 'description' => __( 'Days a visitor can keep a response session (1 to 7).', 'flx-forms' ) ),
			'trackCodeStyle'      => array( 'type' => 'FlxFormsTrackCodeStyle!', 'description' => __( 'Look of new tracking codes.', 'flx-forms' ) ),
			'scaptcha'            => array( 'type' => 'Boolean!', 'description' => __( 'Show a captcha on the tracking page.', 'flx-forms' ) ),
			'dsupfile'            => array( 'type' => 'Boolean!', 'description' => __( 'Visitors may attach files to replies.', 'flx-forms' ) ),
			'activeDlBtn'         => array( 'type' => 'Boolean!', 'description' => __( 'Show a download button on the tracking page.', 'flx-forms' ) ),
			'showIp'              => array( 'type' => 'Boolean!', 'description' => __( 'Show the sender IP address in responses.', 'flx-forms' ) ),
			'adminSN'             => array( 'type' => 'Boolean!', 'description' => __( 'Show the staff name on replies.', 'flx-forms' ) ),
			'responseStyle'       => array( 'type' => 'FlxFormsResponseStyle!', 'description' => __( 'Colours and fonts of the public response box.', 'flx-forms' ) ),
			'siteKey'             => array( 'type' => 'String!', 'description' => __( 'Google reCAPTCHA site key (public). Empty when none.', 'flx-forms' ) ),
			'secretKeySaved'      => array( 'type' => 'Boolean!', 'description' => __( 'A reCAPTCHA secret key is saved.', 'flx-forms' ) ),
			'shieldSilentCaptcha' => array( 'type' => 'Boolean!', 'description' => __( 'Ask Shield to judge visitors silently.', 'flx-forms' ) ),
			'shieldAvailable'     => array( 'type' => 'Boolean!', 'description' => __( 'Shield is installed and can answer.', 'flx-forms' ) ),
			'mapKeySaved'         => array( 'type' => 'Boolean!', 'description' => __( 'A Google Maps key is saved.', 'flx-forms' ) ),
			'emailSupporter'      => array( 'type' => 'String!', 'description' => __( 'Address that receives form notices.', 'flx-forms' ) ),
			'femail'              => array( 'type' => 'String!', 'description' => __( 'Sender address of the mails.', 'flx-forms' ) ),
			'smtp'                => array( 'type' => 'Boolean!', 'description' => __( 'The site can send mail.', 'flx-forms' ) ),
			'weeklyEmailReport'   => array( 'type' => 'Boolean!', 'description' => __( 'Send the weekly mail delivery report.', 'flx-forms' ) ),
			'emailStatsReport'    => array( 'type' => 'Boolean!', 'description' => __( 'Include delivery statistics in the report.', 'flx-forms' ) ),
			'emailTemp'           => array( 'type' => 'String!', 'description' => __( 'HTML of the notification mail. Empty uses the default.', 'flx-forms' ) ),
			'emailBtnBgColor'     => array( 'type' => 'String!', 'description' => __( 'Mail button colour.', 'flx-forms' ) ),
			'emailBtnTextColor'   => array( 'type' => 'String!', 'description' => __( 'Mail button text colour.', 'flx-forms' ) ),
			'payProvider'         => array( 'type' => 'String!', 'description' => __( 'Payment provider id.', 'flx-forms' ) ),
			'stripePKey'          => array( 'type' => 'String!', 'description' => __( 'Stripe publishable key (public).', 'flx-forms' ) ),
			'paypalPKey'          => array( 'type' => 'String!', 'description' => __( 'PayPal client id (public).', 'flx-forms' ) ),
			'stripeSecretSaved'   => array( 'type' => 'Boolean!', 'description' => __( 'A Stripe secret key is saved.', 'flx-forms' ) ),
			'paypalSecretSaved'   => array( 'type' => 'Boolean!', 'description' => __( 'A PayPal secret is saved.', 'flx-forms' ) ),
			'payTokenSaved'       => array( 'type' => 'Boolean!', 'description' => __( 'A payment gateway token is saved.', 'flx-forms' ) ),
			'smsConfig'           => array( 'type' => 'FlxFormsSmsService!', 'description' => __( 'SMS service in use.', 'flx-forms' ) ),
			'smsPhoneSaved'       => array( 'type' => 'Boolean!', 'description' => __( 'Phone numbers for SMS notices are saved.', 'flx-forms' ) ),
		),
	)
);

flx_gql_input(
	'FlxFormsResponseStyleInput',
	array(
		'description' => __( 'Colours and fonts of the public response box. Fields left out stay as they are. Colours are hex like #3644d2.', 'flx-forms' ),
		'fields'      => array(
			'preset'      => array( 'type' => 'String', 'description' => __( 'Palette preset.', 'flx-forms' ) ),
			'brandColor'  => array( 'type' => 'String', 'description' => __( 'Brand colour.', 'flx-forms' ) ),
			'primary'     => array( 'type' => 'String', 'description' => __( 'Primary colour.', 'flx-forms' ) ),
			'primaryDark' => array( 'type' => 'String', 'description' => __( 'Primary colour, dark.', 'flx-forms' ) ),
			'accent'      => array( 'type' => 'String', 'description' => __( 'Accent colour.', 'flx-forms' ) ),
			'text'        => array( 'type' => 'String', 'description' => __( 'Text colour.', 'flx-forms' ) ),
			'textMuted'   => array( 'type' => 'String', 'description' => __( 'Muted text colour.', 'flx-forms' ) ),
			'bgCard'      => array( 'type' => 'String', 'description' => __( 'Card background.', 'flx-forms' ) ),
			'bgMeta'      => array( 'type' => 'String', 'description' => __( 'Meta strip background.', 'flx-forms' ) ),
			'bgTrack'     => array( 'type' => 'String', 'description' => __( 'Tracker background.', 'flx-forms' ) ),
			'bgResp'      => array( 'type' => 'String', 'description' => __( 'Reply background.', 'flx-forms' ) ),
			'bgEditor'    => array( 'type' => 'String', 'description' => __( 'Editor background.', 'flx-forms' ) ),
			'editorText'  => array( 'type' => 'String', 'description' => __( 'Editor text colour.', 'flx-forms' ) ),
			'editorPh'    => array( 'type' => 'String', 'description' => __( 'Editor placeholder colour.', 'flx-forms' ) ),
			'btnText'     => array( 'type' => 'String', 'description' => __( 'Button text colour.', 'flx-forms' ) ),
			'fontFamily'  => array( 'type' => 'String', 'description' => __( 'Font family.', 'flx-forms' ) ),
			'fontSize'    => array( 'type' => 'String', 'description' => __( 'Font size.', 'flx-forms' ) ),
			'customFont'  => array( 'type' => 'String', 'description' => __( 'Custom font name.', 'flx-forms' ) ),
		),
	)
);

/* ---------------------------------------------------------------- Queries */

flx_gql_query(
	'flxForms',
	array(
		'type'        => 'FlxFormListItemConnection!',
		'description' => __( 'Forms of the panel with their response counts. Forms with new responses come first.', 'flx-forms' ),
		'cap'         => flxf_gql_panel_cap(),
		'args'        => flx_gql_connection_args(),
		'resolve'     => static function ( mixed $root, array $args ): array {
			unset( $root );
			flx_gql_cache_tag( 'flx-forms:list' );
			$all = FLXF_Response_Service::forms();
			return flx_gql_paginate(
				flx_gql_arr( $args ),
				static fn( int $offset, int $limit ): array => array(
					'items' => array_slice( $all, $offset, $limit ),
					'total' => count( $all ),
				)
			);
		},
	)
);

flx_gql_query(
	'flxFormResponses',
	array(
		'type'        => 'FlxFormResponseItemConnection!',
		'description' => __( 'Responses, newest first. Payment forms that were not paid are not listed.', 'flx-forms' ),
		'cap'         => flxf_gql_panel_cap(),
		'args'        => flx_gql_connection_args(
			array(
				'formId' => array( 'type' => 'ID', 'description' => __( 'Only this form.', 'flx-forms' ) ),
				'unread' => array( 'type' => 'Boolean', 'description' => __( 'Only responses nobody has read (new and new follow-up).', 'flx-forms' ) ),
				'status' => array( 'type' => 'FlxFormResponseStatus', 'description' => __( 'Only this read state.', 'flx-forms' ) ),
				'search' => array( 'type' => 'String', 'description' => __( 'Text to find in the answers, replies, tracking code or form title.', 'flx-forms' ) ),
			)
		),
		'resolve'     => static function ( mixed $root, array $args ): array {
			unset( $root );
			flx_gql_cache_tag( 'flx-forms:responses' );
			$filters = array(
				'form'    => flxf_gql_id( $args['formId'] ?? 0 ),
				'unread'  => true === ( $args['unread'] ?? false ),
				'status'  => flx_gql_str( $args['status'] ?? '' ),
				'search'  => sanitize_text_field( flx_gql_str( $args['search'] ?? '' ) ),
				'replies' => true,
			);
			return flx_gql_paginate(
				flx_gql_arr( $args ),
				static function ( int $offset, int $limit ) use ( $filters ): array {
					$page  = FLXF_Response_Service::page( $filters, $offset, $limit );
					$forms = FLXF_Response_Service::forms_map();
					$types = FLXF_Response_Service::types_map();
					$items = array();
					foreach ( $page['rows'] as $row ) {
						$items[] = FLXF_Response_Service::item( $row, $forms, $types );
					}
					return array(
						'items' => $items,
						'total' => $page['total'],
					);
				}
			);
		},
	)
);

flx_gql_query(
	'flxFormResponse',
	array(
		'type'        => 'FlxFormResponse',
		'description' => __( 'One response. NOT_FOUND when it does not exist or is an unpaid payment draft.', 'flx-forms' ),
		'cap'         => flxf_gql_panel_cap(),
		'args'        => array(
			'id' => array( 'type' => 'ID!', 'description' => __( 'Response id.', 'flx-forms' ) ),
		),
		'resolve'     => static function ( mixed $root, array $args ): array {
			unset( $root );
			$id = flxf_gql_id( $args['id'] ?? 0 );
			flx_gql_cache_tag( 'flx-forms:response:' . (string) $id );
			$row = $id > 0 ? FLXF_Response_Service::row( $id ) : null;
			if ( null === $row || 2 === FLXF_Submission_Service::num( $row['read_'] ?? 0 ) ) {
				throw flx_gql_error( 'NOT_FOUND', __( 'Response not found.', 'flx-forms' ) );
			}
			return FLXF_Response_Service::view( $row );
		},
	)
);

flx_gql_query(
	'flxFormResponseByTrack',
	array(
		'type'        => 'FlxFormResponse',
		'description' => __( 'Opens a response by its tracking code. NOT_FOUND when no response has it.', 'flx-forms' ),
		'cap'         => flxf_gql_panel_cap(),
		'args'        => array(
			'code' => array( 'type' => 'String!', 'description' => __( 'Tracking code.', 'flx-forms' ) ),
		),
		'resolve'     => static function ( mixed $root, array $args ): array {
			unset( $root );
			$row = FLXF_Response_Service::row_by_track( sanitize_text_field( flx_gql_str( $args['code'] ?? '' ) ) );
			if ( null === $row || 2 === FLXF_Submission_Service::num( $row['read_'] ?? 0 ) ) {
				throw flx_gql_error( 'NOT_FOUND', __( 'Response not found.', 'flx-forms' ) );
			}
			return FLXF_Response_Service::view( $row );
		},
	)
);

flx_gql_query(
	'flxFormsExportUrl',
	array(
		'type'        => 'String!',
		'description' => __( 'Link that downloads the responses as a CSV file (admin-post, with the nonce). The file is built by the dashboard export, so it is the same bytes as before.', 'flx-forms' ),
		'cap'         => flxf_gql_panel_cap(),
		'args'        => array(
			'formId' => array( 'type' => 'ID', 'description' => __( 'Only this form. All forms when left out.', 'flx-forms' ) ),
		),
		'resolve'     => static function ( mixed $root, array $args ): string {
			unset( $root );
			return FLXF_Response_Service::export_url( flxf_gql_id( $args['formId'] ?? 0 ) );
		},
	)
);

flx_gql_query(
	'flxFormsSettings',
	array(
		'type'        => 'FlxFormsSettings!',
		'description' => __( 'Panel settings. Keys and tokens are not returned.', 'flx-forms' ),
		'cap'         => flxf_gql_panel_cap(),
		'resolve'     => static function (): array {
			return FLXF_Settings_Service::read();
		},
	)
);

/* -------------------------------------------------------------- Mutations */

flx_gql_mutation(
	'flxFormResponseRead',
	array(
		'description' => __( 'Marks a response read.', 'flx-forms' ),
		'cap'         => flxf_gql_panel_cap(),
		'input'       => array(
			'id' => array( 'type' => 'ID!', 'description' => __( 'Response id.', 'flx-forms' ) ),
		),
		'output'      => array(
			'response' => array( 'type' => 'FlxFormResponse', 'description' => __( 'The response after the change.', 'flx-forms' ) ),
		),
		'resolve'     => static function ( array $input ): array {
			$id = flxf_gql_id( $input['id'] ?? 0 );
			if ( null === FLXF_Response_Service::mark_read( $id, true ) ) {
				throw flx_gql_error( 'NOT_FOUND', __( 'Response not found.', 'flx-forms' ) );
			}
			$row = FLXF_Response_Service::row( $id );
			return array( 'response' => null !== $row ? FLXF_Response_Service::view( $row ) : null );
		},
	)
);

flx_gql_mutation(
	'flxFormResponsesRead',
	array(
		'description' => __( 'Marks several responses read (at most 200).', 'flx-forms' ),
		'cap'         => flxf_gql_panel_cap(),
		'input'       => array(
			'ids' => array( 'type' => '[ID!]!', 'description' => __( 'Response ids.', 'flx-forms' ) ),
		),
		'output'      => array(
			'updated' => array( 'type' => 'Int', 'description' => __( 'How many responses exist and were handled. Null when the input was rejected.', 'flx-forms' ) ),
		),
		'resolve'     => static function ( array $input ): array|WP_Error {
			$ids = FLXF_Response_Service::ids( array_map( 'flxf_gql_id', (array) ( $input['ids'] ?? array() ) ) );
			if ( ! $ids || count( $ids ) > FLXF_Response_Service::MAX_BULK ) {
				return new WP_Error( 'flxf_invalid', __( 'Pick between 1 and 200 responses.', 'flx-forms' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'ids', 'status' => 400 ) );
			}
			$done = 0;
			foreach ( $ids as $id ) {
				if ( null !== FLXF_Response_Service::mark_read( $id, true ) ) {
					++$done;
				}
			}
			return array( 'updated' => $done );
		},
	)
);

flx_gql_mutation(
	'flxFormResponseDelete',
	array(
		'description' => __( 'Deletes a response and its replies.', 'flx-forms' ),
		'cap'         => flxf_gql_panel_cap(),
		'input'       => array(
			'id' => array( 'type' => 'ID!', 'description' => __( 'Response id.', 'flx-forms' ) ),
		),
		'output'      => array(
			'deleted' => array( 'type' => 'Boolean!', 'description' => __( 'The response existed and is gone.', 'flx-forms' ) ),
		),
		'resolve'     => static function ( array $input ): array {
			return array( 'deleted' => FLXF_Response_Service::delete( array( flxf_gql_id( $input['id'] ?? 0 ) ) ) > 0 );
		},
	)
);

flx_gql_mutation(
	'flxFormResponsesDelete',
	array(
		'description' => __( 'Deletes several responses and their replies (at most 200).', 'flx-forms' ),
		'cap'         => flxf_gql_panel_cap(),
		'input'       => array(
			'ids' => array( 'type' => '[ID!]!', 'description' => __( 'Response ids.', 'flx-forms' ) ),
		),
		'output'      => array(
			'deleted' => array( 'type' => 'Int', 'description' => __( 'How many responses were deleted. Null when the input was rejected.', 'flx-forms' ) ),
		),
		'resolve'     => static function ( array $input ): array|WP_Error {
			$ids = FLXF_Response_Service::ids( array_map( 'flxf_gql_id', (array) ( $input['ids'] ?? array() ) ) );
			if ( ! $ids || count( $ids ) > FLXF_Response_Service::MAX_BULK ) {
				return new WP_Error( 'flxf_invalid', __( 'Pick between 1 and 200 responses.', 'flx-forms' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'ids', 'status' => 400 ) );
			}
			return array( 'deleted' => FLXF_Response_Service::delete( $ids ) );
		},
	)
);

flx_gql_mutation(
	'flxFormResponseReply',
	array(
		'description' => __( 'Replies to a response as the signed-in staff member. The visitor gets the same notification mail as from the classic panel.', 'flx-forms' ),
		'cap'         => flxf_gql_panel_cap(),
		'input'       => array(
			'submissionId' => array( 'type' => 'ID!', 'description' => __( 'Response id.', 'flx-forms' ) ),
			'message'      => array( 'type' => 'String', 'description' => __( 'Reply text. Plain text only. Needed unless the reply closes or opens the ticket or carries a file.', 'flx-forms' ) ),
			'attachments'  => array( 'type' => '[String!]', 'description' => __( 'File URLs the upload endpoint reserved for this response.', 'flx-forms' ) ),
			'state'        => array( 'type' => 'FlxFormReplyState', 'description' => __( 'What the reply does to the ticket. Defaults to REPLY.', 'flx-forms' ) ),
		),
		'output'      => array(
			'response' => array( 'type' => 'FlxFormResponse', 'description' => __( 'The response with the new reply. Null when the input was rejected.', 'flx-forms' ) ),
		),
		'resolve'     => static function ( array $input ): array|WP_Error {
			return FLXF_Response_Service::reply( flx_gql_arr( $input ) );
		},
	)
);

flx_gql_mutation(
	'flxFormsStorageClear',
	array(
		'description' => __( 'Deletes uploaded response files that no stored response points to any more.', 'flx-forms' ),
		'cap'         => flxf_gql_panel_cap(),
		'input'       => array(),
		'output'      => array(
			'cleared' => array( 'type' => 'Boolean!', 'description' => __( 'The cleanup ran.', 'flx-forms' ) ),
		),
		'resolve'     => static function (): array {
			FLXF_Response_Service::clear_orphan_files();
			return array( 'cleared' => true );
		},
	)
);

flx_gql_mutation(
	'flxFormsSettingsUpdate',
	array(
		'description' => __( 'Changes panel settings. Fields left out stay as they are. Secret fields: empty keeps the saved value, "__clear__" removes it.', 'flx-forms' ),
		'cap'         => flxf_gql_panel_cap(),
		'input'       => array(
			'bootstrap'           => array( 'type' => 'Boolean', 'description' => __( 'The theme already loads Bootstrap, so forms use its styles.', 'flx-forms' ) ),
			'osLocationPicker'    => array( 'type' => 'Boolean', 'description' => __( 'Use the OpenStreetMap location picker.', 'flx-forms' ) ),
			'sessionDuration'     => array( 'type' => 'Int', 'description' => __( 'Days a visitor can keep a response session (1 to 7).', 'flx-forms' ) ),
			'trackCodeStyle'      => array( 'type' => 'FlxFormsTrackCodeStyle', 'description' => __( 'Look of new tracking codes.', 'flx-forms' ) ),
			'scaptcha'            => array( 'type' => 'Boolean', 'description' => __( 'Show a captcha on the tracking page.', 'flx-forms' ) ),
			'dsupfile'            => array( 'type' => 'Boolean', 'description' => __( 'Visitors may attach files to replies.', 'flx-forms' ) ),
			'activeDlBtn'         => array( 'type' => 'Boolean', 'description' => __( 'Show a download button on the tracking page.', 'flx-forms' ) ),
			'showIp'              => array( 'type' => 'Boolean', 'description' => __( 'Show the sender IP address in responses.', 'flx-forms' ) ),
			'adminSN'             => array( 'type' => 'Boolean', 'description' => __( 'Show the staff name on replies.', 'flx-forms' ) ),
			'responseStyle'       => array( 'type' => 'FlxFormsResponseStyleInput', 'description' => __( 'Colours and fonts of the public response box.', 'flx-forms' ) ),
			'siteKey'             => array( 'type' => 'String', 'description' => __( 'Google reCAPTCHA site key (public).', 'flx-forms' ) ),
			'secretKey'           => array( 'type' => 'String', 'description' => __( 'Google reCAPTCHA secret key. Write-only.', 'flx-forms' ) ),
			'mapKey'              => array( 'type' => 'String', 'description' => __( 'Google Maps key. Write-only.', 'flx-forms' ) ),
			'shieldSilentCaptcha' => array( 'type' => 'Boolean', 'description' => __( 'Ask Shield to judge visitors silently.', 'flx-forms' ) ),
			'emailSupporter'      => array( 'type' => 'String', 'description' => __( 'Address that receives form notices.', 'flx-forms' ) ),
			'femail'              => array( 'type' => 'String', 'description' => __( 'Sender address of the mails.', 'flx-forms' ) ),
			'smtp'                => array( 'type' => 'Boolean', 'description' => __( 'The site can send mail.', 'flx-forms' ) ),
			'weeklyEmailReport'   => array( 'type' => 'Boolean', 'description' => __( 'Send the weekly mail delivery report.', 'flx-forms' ) ),
			'emailStatsReport'    => array( 'type' => 'Boolean', 'description' => __( 'Include delivery statistics in the report.', 'flx-forms' ) ),
			'emailTemp'           => array( 'type' => 'String', 'description' => __( 'HTML of the notification mail. It has to contain the shortcode_message placeholder. Empty restores the default.', 'flx-forms' ) ),
			'emailBtnBgColor'     => array( 'type' => 'String', 'description' => __( 'Mail button colour.', 'flx-forms' ) ),
			'emailBtnTextColor'   => array( 'type' => 'String', 'description' => __( 'Mail button text colour.', 'flx-forms' ) ),
			'payProvider'         => array( 'type' => 'String', 'description' => __( 'Payment provider id.', 'flx-forms' ) ),
			'stripePKey'          => array( 'type' => 'String', 'description' => __( 'Stripe publishable key (public).', 'flx-forms' ) ),
			'paypalPKey'          => array( 'type' => 'String', 'description' => __( 'PayPal client id (public).', 'flx-forms' ) ),
			'stripeSecretKey'     => array( 'type' => 'String', 'description' => __( 'Stripe secret key. Write-only.', 'flx-forms' ) ),
			'paypalSecretKey'     => array( 'type' => 'String', 'description' => __( 'PayPal secret. Write-only.', 'flx-forms' ) ),
			'payToken'            => array( 'type' => 'String', 'description' => __( 'Payment gateway token. Write-only.', 'flx-forms' ) ),
			'smsConfig'           => array( 'type' => 'FlxFormsSmsService', 'description' => __( 'SMS service in use.', 'flx-forms' ) ),
			'smsPhoneNumbers'     => array( 'type' => 'String', 'description' => __( 'Phone numbers for SMS notices, with country code, separated by commas. Write-only.', 'flx-forms' ) ),
		),
		'output'      => array(
			'settings' => array( 'type' => 'FlxFormsSettings', 'description' => __( 'The settings after the change. Null when the input was rejected.', 'flx-forms' ) ),
		),
		'resolve'     => static function ( array $input ): array|WP_Error {
			return FLXF_Settings_Service::update( flx_gql_arr( $input ) );
		},
	)
);
