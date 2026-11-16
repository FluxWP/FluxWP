<?php
/**
 * Test-site fixtures (copied into mu-plugins by tools/tests/wp-graphql/site.sh). Never shipped.
 *
 * - A persisted query manifest with FlxTestPing.
 * - Upload context flx-test/file (upload_files).
 * - flxTestEcho / flxTestFail fields for the kit contract tests.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_gql_persisted_manifests',
	static function ( $m ) {
		$m['flx-test'] = __DIR__ . '/flx-test-persisted.json';
		return $m;
	}
);

add_filter(
	'flx_gql_upload_contexts',
	static function ( $c ) {
		$c['flx-test/file'] = array(
			'cap'    => 'upload_files',
			'mimes'  => array(
				'txt'      => 'text/plain',
				'png'      => 'image/png',
			),
			'max_mb' => 1,
		);
		return $c;
	}
);

add_action(
	'flx_gql_register',
	static function () {
		flx_gql_query(
			'flxTestEcho',
			array(
				'type'    => 'String!',
				'cap'     => 'public',
				'args'    => array( 'text' => 'String!' ),
				'resolve' => static fn( $root, $args ) => $args['text'],
			)
		);
		flx_gql_query(
			'flxTestAdminOnly',
			array(
				'type'    => 'String!',
				'cap'     => 'manage_options',
				'resolve' => static fn() => 'secret',
			)
		);
		flx_gql_query(
			'flxTestFail',
			array(
				'type'    => 'String',
				'cap'     => 'public',
				'resolve' => static fn() => new WP_Error( 'flx_test_missing', 'Nothing here.', array( 'status' => 404 ) ),
			)
		);
		flx_gql_query(
			'flxTestNoCap', // Refused at registration: no cap.
			array(
				'type'    => 'String',
				'resolve' => static fn() => 'leak',
			)
		);
		flx_gql_connection( 'FlxTestItem' );
		flx_gql_type(
			'FlxTestItem',
			array(
				'fields' => array(
					'n'    => array( 'type' => 'Int!' ),
					'when' => array( 'type' => 'FlxDateTime' ),
				),
			)
		);
		flx_gql_query(
			'flxTestItems',
			array(
				'type'    => 'FlxTestItemConnection!',
				'cap'     => 'public',
				'args'    => flx_gql_connection_args(),
				'resolve' => static fn( $root, $args ) => flx_gql_paginate(
					$args,
					static function ( $offset, $limit ) {
						$all = array();
						for ( $i = 1; $i <= 7; $i++ ) {
							$all[] = array( 'n' => $i, 'when' => flx_gql_datetime( 1763218740 + $i * 60 ) );
						}
						return array( 'items' => array_slice( $all, $offset, $limit ), 'total' => count( $all ) );
					}
				),
			)
		);
		flx_gql_mutation(
			'flxTestSave',
			array(
				'cap'     => 'logged_in',
				'input'   => array( 'name' => 'String!' ),
				'output'  => array( 'name' => array( 'type' => 'String' ) ),
				'resolve' => static function ( $input ) {
					if ( '' === trim( $input['name'] ) ) {
						return new WP_Error( 'empty', 'Name is required.', array( 'status' => 400, 'field' => 'name' ) );
					}
					return array( 'name' => strtoupper( $input['name'] ) );
				},
			)
		);
	}
);

// Settings with secrets: none of them may reach GraphQL (kit-test "Secrets in settings").
add_filter(
	'flx_settings_schema',
	static function ( $schema ) {
		$schema['flxt_conf'] = array(
			'label'  => 'Secret test',
			'fields' => array(
				'title'          => array( 'type' => 'string' ),
				'key_live'       => array( 'type' => 'string' ),
				'stripe_key'     => array( 'type' => 'string' ),
				'api_key'        => array( 'type' => 'string' ),
				'apikey'         => array( 'type' => 'string' ),
				'clientSecret'   => array( 'type' => 'string' ),
				'access-token'   => array( 'type' => 'string' ),
				'smtp_password'  => array( 'type' => 'string' ),
				'smtp_pass'      => array( 'type' => 'string' ),
				'auth'           => array( 'type' => 'string' ),
				'private_key'    => array( 'type' => 'string' ),
				'webhook_signing' => array( 'type' => 'string', 'secret' => true ),
				'login'          => array( 'type' => 'password' ),
				'providers'      => array( 'type' => 'object' ),
			),
		);
		$schema['flxt_api_keys'] = array(
			'label'  => 'Whole option is a secret',
			'fields' => array( 'openrouter' => array( 'type' => 'string' ) ),
		);
		return $schema;
	}
);
