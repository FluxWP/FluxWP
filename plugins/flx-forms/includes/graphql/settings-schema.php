<?php
/**
 * Forms › Settings (Flux GraphQL kit): read and save the classic settings row. Loaded on flx_gql_register.
 * Every field needs the classic panel right (manage_options or Emsfb). Secrets are write-only.
 *
 * @package FLX_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * GraphQL type of a settings field.
 */
function flxf_gql_setting_type( string $kind, bool $required ): string {
	$t = 'bool' === $kind ? 'Boolean' : ( 'days' === $kind ? 'Int' : 'String' );
	return $required ? $t . '!' : $t;
}

$flxf_settings_out = array();
$flxf_settings_in  = array();
foreach ( FLXF_Settings_Service::fields() as $flxf_field => $flxf_spec ) {
	/* translators: %s: classic settings key */
	$flxf_desc                        = sprintf( __( 'Setting %s.', 'flx-forms' ), $flxf_spec[0] );
	$flxf_settings_out[ $flxf_field ] = flxf_gql_f( flxf_gql_setting_type( $flxf_spec[1], true ), $flxf_desc );
	$flxf_settings_in[ $flxf_field ]  = flxf_gql_f( flxf_gql_setting_type( $flxf_spec[1], false ), $flxf_desc );
}

flx_gql_type(
	'FlxFormsSettings',
	array(
		'description' => __( 'Forms settings.', 'flx-forms' ),
		'fields'      => $flxf_settings_out + array(
			'devMode'        => flxf_gql_f( 'Boolean!', __( 'Developer mode (unminified scripts).', 'flx-forms' ) ),
			'secretKeySet'   => flxf_gql_f( 'Boolean!', __( 'A reCAPTCHA secret key is saved.', 'flx-forms' ) ),
			'fromHint'       => flxf_gql_f( 'String!', __( 'Sender address used when none is set.', 'flx-forms' ) ),
			'trackShortcode' => flxf_gql_f( 'String!', __( 'Shortcode of the track code finder.', 'flx-forms' ) ),
			'canEmailReport' => flxf_gql_f( 'Boolean!', __( 'This user may change the weekly report.', 'flx-forms' ) ),
			'canEmailStats'  => flxf_gql_f( 'Boolean!', __( 'This user may change the e-mail statistics report.', 'flx-forms' ) ),
		),
	)
);

flx_gql_query(
	'flxFormsSettings',
	array(
		'type'        => 'FlxFormsSettings!',
		'description' => __( 'Forms settings.', 'flx-forms' ),
		'cap'         => flx_gql_any_cap( 'manage_options', 'Emsfb' ),
		'resolve'     => static fn() => FLXF_Settings_Service::get(),
	)
);

flx_gql_mutation(
	'flxFormsSettingsUpdate',
	array(
		'description' => __( 'Saves the given settings; others stay as they are.', 'flx-forms' ),
		'cap'         => flx_gql_any_cap( 'manage_options', 'Emsfb' ),
		'input'       => $flxf_settings_in + array(
			'devMode'        => flxf_gql_f( 'Boolean', __( 'Developer mode.', 'flx-forms' ) ),
			'secretKey'      => flxf_gql_f( 'String', __( 'New reCAPTCHA secret key (write-only; empty keeps the saved one).', 'flx-forms' ) ),
			'clearSecretKey' => flxf_gql_f( 'Boolean', __( 'Removes the saved reCAPTCHA secret key.', 'flx-forms' ) ),
		),
		'output'      => array( 'settings' => flxf_gql_f( 'FlxFormsSettings', __( 'Settings after the save.', 'flx-forms' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXF_Settings_Service::update( flx_gql_arr( $input ) );
			return $r instanceof WP_Error ? $r : array( 'settings' => $r );
		},
	)
);

flx_gql_mutation(
	'flxFormsClearFiles',
	array(
		'description' => __( 'Deletes uploaded files that no response uses.', 'flx-forms' ),
		'cap'         => flx_gql_any_cap( 'manage_options', 'Emsfb' ),
		'input'       => array(),
		'output'      => array( 'deleted' => flxf_gql_f( 'Int!', __( 'Files deleted.', 'flx-forms' ) ) ),
		'resolve'     => static fn() => array( 'deleted' => FLXF_Settings_Service::clear_files() ),
	)
);
