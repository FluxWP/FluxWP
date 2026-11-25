<?php
/**
 * Forms screen (Flux GraphQL kit): dashboard, form list, responses and replies. Loaded on flx_gql_register.
 * Every field needs the classic panel right (manage_options or Emsfb).
 *
 * @package FLX_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field definition.
 *
 * @return array{type:string,description:string}
 */
function flxf_gql_f( string $type, string $description ): array {
	return array(
		'type'        => $type,
		'description' => $description,
	);
}

flx_gql_type(
	'FlxFormsAdminForm',
	array(
		'description' => __( 'A form in the panel list.', 'flx-forms' ),
		'fields'      => array(
			'databaseId'     => flxf_gql_f( 'Int!', __( 'Form id.', 'flx-forms' ) ),
			'title'          => flxf_gql_f( 'String!', __( 'Form name.', 'flx-forms' ) ),
			'formType'       => flxf_gql_f( 'String!', __( 'form, survey, subscribe, payment, register or login.', 'flx-forms' ) ),
			'createdAt'      => flxf_gql_f( 'FlxDateTime!', __( 'When the form was made.', 'flx-forms' ) ),
			'responses'      => flxf_gql_f( 'Int!', __( 'Responses.', 'flx-forms' ) ),
			'unread'         => flxf_gql_f( 'Int!', __( 'Unread responses.', 'flx-forms' ) ),
			'lastResponseAt' => flxf_gql_f( 'FlxDateTime', __( 'Latest response.', 'flx-forms' ) ),
			'shortcode'      => flxf_gql_f( 'String!', __( 'Shortcode that prints the form.', 'flx-forms' ) ),
			'editUrl'        => flxf_gql_f( 'String!', __( 'Form builder address.', 'flx-forms' ) ),
		),
	)
);
flx_gql_connection( 'FlxFormsAdminForm', __( 'A page of forms.', 'flx-forms' ) );

flx_gql_type(
	'FlxFormsFormOption',
	array(
		'description' => __( 'A form for a filter.', 'flx-forms' ),
		'fields'      => array(
			'databaseId' => flxf_gql_f( 'Int!', __( 'Form id.', 'flx-forms' ) ),
			'title'      => flxf_gql_f( 'String!', __( 'Form name.', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxFormsEntryField',
	array(
		'description' => __( 'One answer on a response.', 'flx-forms' ),
		'fields'      => array(
			'label'  => flxf_gql_f( 'String!', __( 'Field label.', 'flx-forms' ) ),
			'value'  => flxf_gql_f( 'String!', __( 'Answer as text.', 'flx-forms' ) ),
			'isLink' => flxf_gql_f( 'Boolean!', __( 'The answer is an address (file, page).', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxFormsEntryReply',
	array(
		'description' => __( 'One reply on a response.', 'flx-forms' ),
		'fields'      => array(
			'databaseId'  => flxf_gql_f( 'Int!', __( 'Reply id.', 'flx-forms' ) ),
			'body'        => flxf_gql_f( 'String!', __( 'Reply text.', 'flx-forms' ) ),
			'createdAt'   => flxf_gql_f( 'FlxDateTime!', __( 'When the reply was stored.', 'flx-forms' ) ),
			'attachments' => flxf_gql_f( '[String!]!', __( 'File addresses.', 'flx-forms' ) ),
			'byStaff'     => flxf_gql_f( 'Boolean!', __( 'Written by staff.', 'flx-forms' ) ),
			'author'      => flxf_gql_f( 'String!', __( 'Staff member name. Empty for the visitor.', 'flx-forms' ) ),
			'state'       => flxf_gql_f( 'String!', __( 'closed or opened when the reply changed the thread. Empty otherwise.', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxFormsEntry',
	array(
		'description' => __( 'A response to a form.', 'flx-forms' ),
		'fields'      => array(
			'databaseId' => flxf_gql_f( 'Int!', __( 'Response id.', 'flx-forms' ) ),
			'formId'     => flxf_gql_f( 'Int!', __( 'Form id.', 'flx-forms' ) ),
			'formTitle'  => flxf_gql_f( 'String!', __( 'Form name.', 'flx-forms' ) ),
			'trackCode'  => flxf_gql_f( 'String!', __( 'Tracking code.', 'flx-forms' ) ),
			'createdAt'  => flxf_gql_f( 'FlxDateTime!', __( 'When it was sent.', 'flx-forms' ) ),
			'read'       => flxf_gql_f( 'Boolean!', __( 'Read.', 'flx-forms' ) ),
			'preview'    => flxf_gql_f( 'String!', __( 'First answers, short.', 'flx-forms' ) ),
			'ip'         => flxf_gql_f( 'String', __( 'Sender IP. Only on one response.', 'flx-forms' ) ),
			'closed'     => flxf_gql_f( 'Boolean', __( 'The thread is closed. Only on one response.', 'flx-forms' ) ),
			'fields'     => flxf_gql_f( '[FlxFormsEntryField!]', __( 'Answers. Only on one response.', 'flx-forms' ) ),
			'replies'    => flxf_gql_f( '[FlxFormsEntryReply!]', __( 'Replies, oldest first. Only on one response.', 'flx-forms' ) ),
		),
	)
);
flx_gql_connection( 'FlxFormsEntry', __( 'A page of responses.', 'flx-forms' ) );

flx_gql_query(
	'flxFormsDashboard',
	array(
		'type'        => 'FlxJSON!',
		'description' => __( 'Dashboard numbers: responses per day, views, conversion, payments, busy hours, forms and latest responses.', 'flx-forms' ),
		'cap'         => flx_gql_any_cap( 'manage_options', 'Emsfb' ),
		'args'        => array(
			'from' => flxf_gql_f( 'String', __( 'First day (Y-m-d). Default 29 days before the last.', 'flx-forms' ) ),
			'to'   => flxf_gql_f( 'String', __( 'Last day (Y-m-d). Default today.', 'flx-forms' ) ),
			'form' => flxf_gql_f( 'Int', __( 'Only this form.', 'flx-forms' ) ),
		),
		'resolve'     => static function ( $root, array $args ) {
			unset( $root );
			return FLXF_Admin_Service::dashboard( flx_gql_arr( $args ) );
		},
	)
);

flx_gql_query(
	'flxFormsAdminForms',
	array(
		'type'        => 'FlxFormsAdminFormConnection!',
		'description' => __( 'Forms, newest first.', 'flx-forms' ),
		'cap'         => flx_gql_any_cap( 'manage_options', 'Emsfb' ),
		'args'        => flx_gql_connection_args(
			array(
				'search' => flxf_gql_f( 'String', __( 'Name or id.', 'flx-forms' ) ),
				'type'   => flxf_gql_f( 'String', __( 'Form type.', 'flx-forms' ) ),
			)
		),
		'resolve'     => static function ( $root, array $args ) {
			unset( $root );
			$args   = flx_gql_arr( $args );
			$filter = array(
				'search' => flx_gql_str( $args['search'] ?? '' ),
				'type'   => flx_gql_str( $args['type'] ?? '' ),
			);
			return flx_gql_paginate( $args, static fn( int $offset, int $limit ): array => FLXF_Admin_Service::forms( $filter, $offset, $limit ) );
		},
	)
);

flx_gql_query(
	'flxFormsFormOptions',
	array(
		'type'        => '[FlxFormsFormOption!]!',
		'description' => __( 'Every form, by name, for filters.', 'flx-forms' ),
		'cap'         => flx_gql_any_cap( 'manage_options', 'Emsfb' ),
		'resolve'     => static function () {
			return FLXF_Admin_Service::form_options();
		},
	)
);

flx_gql_query(
	'flxFormsEntries',
	array(
		'type'        => 'FlxFormsEntryConnection!',
		'description' => __( 'Responses, newest first. Drafts waiting for a payment are left out.', 'flx-forms' ),
		'cap'         => flx_gql_any_cap( 'manage_options', 'Emsfb' ),
		'args'        => flx_gql_connection_args(
			array(
				'form'   => flxf_gql_f( 'Int', __( 'Only this form.', 'flx-forms' ) ),
				'status' => flxf_gql_f( 'String', __( 'unread or read.', 'flx-forms' ) ),
				'search' => flxf_gql_f( 'String', __( 'Tracking code or a word in the answers.', 'flx-forms' ) ),
			)
		),
		'resolve'     => static function ( $root, array $args ) {
			unset( $root );
			$args   = flx_gql_arr( $args );
			$filter = array(
				'form'   => flx_gql_int( $args['form'] ?? 0 ),
				'status' => flx_gql_str( $args['status'] ?? '' ),
				'search' => flx_gql_str( $args['search'] ?? '' ),
			);
			return flx_gql_paginate( $args, static fn( int $offset, int $limit ): array => FLXF_Admin_Service::entries( $filter, $offset, $limit ) );
		},
	)
);

flx_gql_query(
	'flxFormsEntry',
	array(
		'type'        => 'FlxFormsEntry',
		'description' => __( 'One response with its answers and replies. Null when it is missing.', 'flx-forms' ),
		'cap'         => flx_gql_any_cap( 'manage_options', 'Emsfb' ),
		'args'        => array( 'id' => flxf_gql_f( 'Int!', __( 'Response id.', 'flx-forms' ) ) ),
		'resolve'     => static function ( $root, array $args ) {
			unset( $root );
			return FLXF_Admin_Service::entry( flx_gql_int( flx_gql_arr( $args )['id'] ?? 0 ) );
		},
	)
);

flx_gql_mutation(
	'flxFormsEntriesMark',
	array(
		'description' => __( 'Marks responses read or unread.', 'flx-forms' ),
		'cap'         => flx_gql_any_cap( 'manage_options', 'Emsfb' ),
		'input'       => array(
			'ids'  => flxf_gql_f( '[Int!]!', __( 'Response ids.', 'flx-forms' ) ),
			'read' => flxf_gql_f( 'Boolean!', __( 'Read.', 'flx-forms' ) ),
		),
		'output'      => array( 'changed' => flxf_gql_f( 'Int!', __( 'Responses changed.', 'flx-forms' ) ) ),
		'resolve'     => static function ( array $input ) {
			$input = flx_gql_arr( $input );
			return array( 'changed' => FLXF_Admin_Service::mark( flx_gql_arr( $input['ids'] ?? array() ), ! empty( $input['read'] ) ) );
		},
	)
);

flx_gql_mutation(
	'flxFormsEntriesDelete',
	array(
		'description' => __( 'Deletes responses and their replies.', 'flx-forms' ),
		'cap'         => flx_gql_any_cap( 'manage_options', 'Emsfb' ),
		'input'       => array( 'ids' => flxf_gql_f( '[Int!]!', __( 'Response ids.', 'flx-forms' ) ) ),
		'output'      => array( 'deleted' => flxf_gql_f( 'Int!', __( 'Responses deleted.', 'flx-forms' ) ) ),
		'resolve'     => static function ( array $input ) {
			return array( 'deleted' => FLXF_Admin_Service::delete_entries( flx_gql_arr( flx_gql_arr( $input )['ids'] ?? array() ) ) );
		},
	)
);

flx_gql_mutation(
	'flxFormsEntryReply',
	array(
		'description' => __( 'Staff reply on a response. The visitor gets the usual reply notification.', 'flx-forms' ),
		'cap'         => flx_gql_any_cap( 'manage_options', 'Emsfb' ),
		'input'       => array(
			'id'      => flxf_gql_f( 'Int!', __( 'Response id.', 'flx-forms' ) ),
			'message' => flxf_gql_f( 'String!', __( 'Reply text.', 'flx-forms' ) ),
			'close'   => flxf_gql_f( 'Boolean', __( 'true closes the thread, false opens it again. Left out: no change.', 'flx-forms' ) ),
		),
		'output'      => array( 'entry' => flxf_gql_f( 'FlxFormsEntry', __( 'The response after the reply.', 'flx-forms' ) ) ),
		'resolve'     => static function ( array $input ) {
			$input = flx_gql_arr( $input );
			$close = array_key_exists( 'close', $input ) && null !== $input['close'] ? (bool) $input['close'] : null;
			$entry = FLXF_Admin_Service::reply( flx_gql_int( $input['id'] ?? 0 ), flx_gql_str( $input['message'] ?? '' ), $close );
			if ( is_wp_error( $entry ) ) {
				return $entry;
			}
			return array( 'entry' => $entry );
		},
	)
);

flx_gql_mutation(
	'flxFormsFormDelete',
	array(
		'description' => __( 'Deletes a form and its responses.', 'flx-forms' ),
		'cap'         => flx_gql_any_cap( 'manage_options', 'Emsfb' ),
		'input'       => array( 'id' => flxf_gql_f( 'Int!', __( 'Form id.', 'flx-forms' ) ) ),
		'output'      => array( 'deleted' => flxf_gql_f( 'Boolean!', __( 'The form was deleted.', 'flx-forms' ) ) ),
		'resolve'     => static function ( array $input ) {
			return array( 'deleted' => FLXF_Admin_Service::delete_form( flx_gql_int( flx_gql_arr( $input )['id'] ?? 0 ) ) );
		},
	)
);

flx_gql_mutation(
	'flxFormsFormDuplicate',
	array(
		'description' => __( 'Copies a form. Responses are not copied.', 'flx-forms' ),
		'cap'         => flx_gql_any_cap( 'manage_options', 'Emsfb' ),
		'input'       => array( 'id' => flxf_gql_f( 'Int!', __( 'Form id.', 'flx-forms' ) ) ),
		'output'      => array( 'form' => flxf_gql_f( 'FlxFormsAdminForm', __( 'The copy.', 'flx-forms' ) ) ),
		'resolve'     => static function ( array $input ) {
			$form = FLXF_Admin_Service::duplicate_form( flx_gql_int( flx_gql_arr( $input )['id'] ?? 0 ) );
			if ( null === $form ) {
				return new WP_Error(
					'flxf_not_found',
					__( 'The form was not found.', 'flx-forms' ),
					array(
						'graphql_code' => 'NOT_FOUND',
						'status'       => 404,
					)
				);
			}
			return array( 'form' => $form );
		},
	)
);
