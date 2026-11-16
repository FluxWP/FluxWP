<?php
/**
 * Public form read and submit (Flux GraphQL kit). Loaded on flx_gql_register.
 *
 * flxForm is public because the shortcode already prints these drawing fields to every visitor.
 * Notification recipients, webhooks, secrets and booking counters are not on the type.
 * flxFormSubmit, flxFormTrack and flxFormTrackReply are public because the same actions are
 * anonymous REST calls today (a tracking code is the secret).
 *
 * @package FLX_Forms
 */

defined( 'ABSPATH' ) || exit;

flx_gql_enum(
	'FlxFormType',
	array(
		'description' => __( 'Kind of form.', 'flx-forms' ),
		'values'      => array(
			'FORM'      => array( 'value' => 'form', 'description' => __( 'A message form.', 'flx-forms' ) ),
			'SURVEY'    => array( 'value' => 'survey', 'description' => __( 'A survey.', 'flx-forms' ) ),
			'SUBSCRIBE' => array( 'value' => 'subscribe', 'description' => __( 'A subscribe form.', 'flx-forms' ) ),
			'PAYMENT'   => array( 'value' => 'payment', 'description' => __( 'A payment form. Sending it stays on REST.', 'flx-forms' ) ),
			'REGISTER'  => array( 'value' => 'register', 'description' => __( 'A registration form. Sending it stays on REST.', 'flx-forms' ) ),
			'LOGIN'     => array( 'value' => 'login', 'description' => __( 'A login form. Sending it stays on REST.', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxFormOption',
	array(
		'description' => __( 'One choice a visitor can pick.', 'flx-forms' ),
		'fields'      => array(
			'value' => array( 'type' => 'String!', 'description' => __( 'Stored value.', 'flx-forms' ) ),
			'label' => array( 'type' => 'String!', 'description' => __( 'Label on the form.', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxFormField',
	array(
		'description' => __( 'One drawing field. Hidden and read-only fields are left out.', 'flx-forms' ),
		'fields'      => array(
			'id'          => array( 'type' => 'ID!', 'description' => __( 'Field id to send back on submit.', 'flx-forms' ) ),
			'type'        => array( 'type' => 'String!', 'description' => __( 'Editor field type. The editor type list is open, because add-ons and the form builder add types, so this is a string rather than an enum.', 'flx-forms' ) ),
			'label'       => array( 'type' => 'String!', 'description' => __( 'Label.', 'flx-forms' ) ),
			'required'    => array( 'type' => 'Boolean!', 'description' => __( 'The visitor must fill this in.', 'flx-forms' ) ),
			'placeholder' => array( 'type' => 'String!', 'description' => __( 'Placeholder. Empty when the field has none.', 'flx-forms' ) ),
			'options'     => array( 'type' => '[FlxFormOption!]!', 'description' => __( 'Choices. Empty when the field is not a choice.', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxForm',
	array(
		'description' => __( 'Drawing fields of one form. Notification, webhook and booking data are not included.', 'flx-forms' ),
		'fields'      => array(
			'id'             => array( 'type' => 'ID!', 'description' => __( 'Form id.', 'flx-forms' ) ),
			'title'          => array( 'type' => 'String!', 'description' => __( 'Form name.', 'flx-forms' ) ),
			'formType'       => array( 'type' => 'FlxFormType!', 'description' => __( 'Kind of form.', 'flx-forms' ) ),
			'steps'          => array( 'type' => 'Int!', 'description' => __( 'How many steps the form has.', 'flx-forms' ) ),
			'buttonLabel'    => array( 'type' => 'String!', 'description' => __( 'Send button label.', 'flx-forms' ) ),
			'successTitle'   => array( 'type' => 'String!', 'description' => __( 'Title shown after a successful send.', 'flx-forms' ) ),
			'successMessage' => array( 'type' => 'String!', 'description' => __( 'Message shown after a successful send.', 'flx-forms' ) ),
			'fields'         => array( 'type' => '[FlxFormField!]!', 'description' => __( 'Fields to draw.', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxFormAnswer',
	array(
		'description' => __( 'One answer on a tracked submission.', 'flx-forms' ),
		'fields'      => array(
			'label' => array( 'type' => 'String!', 'description' => __( 'Field label.', 'flx-forms' ) ),
			'value' => array( 'type' => 'String!', 'description' => __( 'What the visitor sent.', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxFormReply',
	array(
		'description' => __( 'One reply on a tracked submission.', 'flx-forms' ),
		'fields'      => array(
			'body'        => array( 'type' => 'String!', 'description' => __( 'Reply text.', 'flx-forms' ) ),
			'createdAt'   => array( 'type' => 'FlxDateTime!', 'description' => __( 'When the reply was stored.', 'flx-forms' ) ),
			'attachments' => array( 'type' => '[String!]!', 'description' => __( 'File URLs attached to the reply. Empty when there are none.', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxFormTrack',
	array(
		'description' => __( 'A submission opened with its tracking code.', 'flx-forms' ),
		'fields'      => array(
			'trackCode'   => array( 'type' => 'String!', 'description' => __( 'Tracking code.', 'flx-forms' ) ),
			'formTitle'   => array( 'type' => 'String!', 'description' => __( 'Form name stored with the submission.', 'flx-forms' ) ),
			'submittedAt' => array( 'type' => 'FlxDateTime!', 'description' => __( 'When the submission was stored.', 'flx-forms' ) ),
			'fields'      => array( 'type' => '[FlxFormAnswer!]!', 'description' => __( 'Answers.', 'flx-forms' ) ),
			'replies'     => array( 'type' => '[FlxFormReply!]!', 'description' => __( 'Replies, oldest first.', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxFormSubmission',
	array(
		'description' => __( 'A stored submission or reply.', 'flx-forms' ),
		'fields'      => array(
			'id'        => array( 'type' => 'ID!', 'description' => __( 'Row id.', 'flx-forms' ) ),
			'trackCode' => array( 'type' => 'String!', 'description' => __( 'Tracking code.', 'flx-forms' ) ),
		),
	)
);

flx_gql_input(
	'FlxFormFieldValueInput',
	array(
		'description' => __( 'One field value.', 'flx-forms' ),
		'fields'      => array(
			'fieldId' => array( 'type' => 'ID!', 'description' => __( 'Field id from flxForm.', 'flx-forms' ) ),
			'value'   => array( 'type' => 'String', 'description' => __( 'Value. A file field carries the URL the upload endpoint returned.', 'flx-forms' ) ),
		),
	)
);

/*
 * Public: the shortcode already prints these drawing fields to every visitor. A private form
 * (stateForm) is null for a visitor who is not signed in.
 */
flx_gql_query(
	'flxForm',
	array(
		'type'        => 'FlxForm',
		'description' => __( 'Drawing fields of one form. Null when the form is missing or private.', 'flx-forms' ),
		'cap'         => 'public',
		'args'        => array(
			'id' => array(
				'type'        => 'ID!',
				'description' => __( 'Form id.', 'flx-forms' ),
			),
		),
		'resolve'     => static function ( mixed $root, array $args ): ?array {
			unset( $root );
			$form_id = absint( flx_gql_int( $args['id'] ?? 0 ) );
			if ( function_exists( 'flx_gql_cache_tag' ) ) {
				flx_gql_cache_tag( 'flx-forms:form:' . (string) $form_id );
			}
			return FLXF_Form_Service::public_form( $form_id );
		},
	)
);

/*
 * Public: the same send is an anonymous REST call today. Captcha, honeypot and the rate limit
 * are enforced here. Login, registration and payment stay on REST.
 */
flx_gql_mutation(
	'flxFormSubmit',
	array(
		'description' => __( 'Sends a form, survey or subscribe form.', 'flx-forms' ),
		'cap'         => 'public',
		'input'       => array(
			'formId'       => array( 'type' => 'ID!', 'description' => __( 'Form id.', 'flx-forms' ) ),
			'fields'       => array( 'type' => '[FlxFormFieldValueInput!]', 'description' => __( 'Filled fields.', 'flx-forms' ) ),
			'captchaToken' => array( 'type' => 'String', 'description' => __( 'reCAPTCHA token when Flux reCAPTCHA protects form submission.', 'flx-forms' ) ),
			'honeypot'     => array( 'type' => 'String', 'description' => __( 'Leave empty. A value rejects the submission.', 'flx-forms' ) ),
		),
		'output'      => array(
			'submission' => array( 'type' => 'FlxFormSubmission', 'description' => __( 'Stored submission. Null when the input was rejected.', 'flx-forms' ) ),
		),
		'resolve'     => static function ( array $input ): array|WP_Error {
			return FLXF_Submission_Service::submit( flx_gql_arr( $input ) );
		},
	)
);

/*
 * Public: the tracking code is the secret, same as the public tracker page. The classic API does
 * not verify a captcha token, so this query does not take one.
 */
flx_gql_query(
	'flxFormTrack',
	array(
		'type'        => 'FlxFormTrack',
		'description' => __( 'Opens a submission by its tracking code.', 'flx-forms' ),
		'cap'         => 'public',
		'args'        => array(
			'code' => array(
				'type'        => 'String!',
				'description' => __( 'Tracking code.', 'flx-forms' ),
			),
		),
		'resolve'     => static function ( mixed $root, array $args ): array {
			unset( $root );
			$result = FLXF_Submission_Service::track( flx_gql_str( $args['code'] ?? '' ) );
			if ( $result instanceof WP_Error ) {
				throw flx_gql_from_wp_error( $result );
			}
			return $result;
		},
	)
);

/*
 * Public: a visitor replies on the tracker with the tracking code. Attachments must have been
 * reserved for that ticket by the upload endpoint. adminCode is the email verification code.
 */
flx_gql_mutation(
	'flxFormTrackReply',
	array(
		'description' => __( 'Adds a reply to a tracked submission.', 'flx-forms' ),
		'cap'         => 'public',
		'input'       => array(
			'trackCode'   => array( 'type' => 'String!', 'description' => __( 'Tracking code.', 'flx-forms' ) ),
			'message'     => array( 'type' => 'String!', 'description' => __( 'Reply text.', 'flx-forms' ) ),
			'attachments' => array( 'type' => '[String!]', 'description' => __( 'File URLs the upload endpoint reserved for this ticket.', 'flx-forms' ) ),
			'adminCode'   => array( 'type' => 'String', 'description' => __( 'Email verification code. Leave empty for a visitor reply.', 'flx-forms' ) ),
		),
		'output'      => array(
			'submission' => array( 'type' => 'FlxFormSubmission', 'description' => __( 'Stored reply. Null when the input was rejected.', 'flx-forms' ) ),
		),
		'resolve'     => static function ( array $input ): array|WP_Error {
			return FLXF_Submission_Service::reply( flx_gql_arr( $input ) );
		},
	)
);
