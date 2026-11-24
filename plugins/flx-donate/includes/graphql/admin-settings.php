<?php
/**
 * Flux Donate admin GraphQL (part b10): Donation settings of the wp-admin screen. Loaded only on
 * flx_gql_register. Resolvers call FLXB_Admin_Settings_Service.
 *
 * Rights (fail closed, the classic ones): everything needs flxb_can_manage().
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

$flxb_settings_types = array(
	'FlxDonationSettingsTab'      => array(
		__( 'A settings tab.', 'flx-donate' ),
		array(
			'id'     => array( 'String!', __( 'Tab key.', 'flx-donate' ) ),
			'label'  => array( 'String!', __( 'Label.', 'flx-donate' ) ),
			'island' => array( 'Boolean!', __( 'Printed by PHP (another module\'s tab); opens with the page.', 'flx-donate' ) ),
			'url'    => array( 'String!', __( 'wp-admin address of the tab.', 'flx-donate' ) ),
		),
	),
	'FlxDonationSettingsCategory' => array(
		__( 'A donation type category.', 'flx-donate' ),
		array(
			'key'   => array( 'String!', __( 'Key.', 'flx-donate' ) ),
			'label' => array( 'String!', __( 'Name.', 'flx-donate' ) ),
			'used'  => array( 'Int!', __( 'Donation types in it; used ones cannot be removed.', 'flx-donate' ) ),
		),
	),
	'FlxDonationSettingsLabel'    => array(
		__( 'A word of the tracking screens.', 'flx-donate' ),
		array(
			'key'         => array( 'String!', __( 'Key.', 'flx-donate' ) ),
			'label'       => array( 'String!', __( 'Label.', 'flx-donate' ) ),
			'value'       => array( 'String!', __( 'Own word; empty = built-in.', 'flx-donate' ) ),
			'placeholder' => array( 'String!', __( 'Built-in word.', 'flx-donate' ) ),
			'max'         => array( 'Int!', __( 'Max length.', 'flx-donate' ) ),
		),
	),
	'FlxDonationSettingsXTab'     => array(
		__( 'An extra detail tab in use.', 'flx-donate' ),
		array(
			'id'       => array( 'String!', __( 'Tab id.', 'flx-donate' ) ),
			'title'    => array( 'String!', __( 'Title.', 'flx-donate' ) ),
			'donation' => array( 'Boolean!', __( 'On the donation page (else the record page).', 'flx-donate' ) ),
			'fields'   => array( 'Int!', __( 'Fields.', 'flx-donate' ) ),
			'builtin'  => array( 'Boolean!', __( 'Added by a plugin.', 'flx-donate' ) ),
		),
	),
	'FlxDonationSettings'         => array(
		__( 'Donation settings.', 'flx-donate' ),
		array(
			'tabs'           => array( '[FlxDonationSettingsTab!]!', __( 'Tabs.', 'flx-donate' ) ),
			'currency'       => array( 'String!', __( 'Default currency.', 'flx-donate' ) ),
			'currencies'     => array( '[FlxDonationChoice!]!', __( 'Currencies.', 'flx-donate' ) ),
			'donationForms'  => array( 'String!', __( 'payments = every paid form, list = the chosen forms.', 'flx-donate' ) ),
			'formIds'        => array( '[Int!]!', __( 'Chosen forms.', 'flx-donate' ) ),
			'forms'          => array( '[FlxDonationChoice!]!', __( 'Payment forms.', 'flx-donate' ) ),
			'moduleTrack'    => array( 'Boolean!', __( 'Tracking module.', 'flx-donate' ) ),
			'moduleReports'  => array( 'Boolean!', __( 'Reports.', 'flx-donate' ) ),
			'moduleKurban'   => array( 'Boolean!', __( 'Gifts on behalf of a person.', 'flx-donate' ) ),
			'orgName'        => array( 'String!', __( 'Organisation name.', 'flx-donate' ) ),
			'orgPlaceholder' => array( 'String!', __( 'Site name.', 'flx-donate' ) ),
			'logoId'         => array( 'Int!', __( 'Logo attachment; 0 = none.', 'flx-donate' ) ),
			'logoUrl'        => array( 'String!', __( 'Logo.', 'flx-donate' ) ),
			'primaryColor'   => array( 'String!', __( 'Brand colour; empty = the theme\'s.', 'flx-donate' ) ),
			'colorHolder'    => array( 'String!', __( 'The theme\'s colour.', 'flx-donate' ) ),
			'receiptText'    => array( 'String!', __( 'Receipt text.', 'flx-donate' ) ),
			'footerText'     => array( 'String!', __( 'Footer text.', 'flx-donate' ) ),
			'brandName'      => array( 'String!', __( 'Name in use.', 'flx-donate' ) ),
			'brandLogo'      => array( 'String!', __( 'Logo in use.', 'flx-donate' ) ),
			'brandPrimary'   => array( 'String!', __( 'Colour in use.', 'flx-donate' ) ),
			'brandDark'      => array( 'String!', __( 'Dark colour in use.', 'flx-donate' ) ),
			'categories'     => array( '[FlxDonationSettingsCategory!]!', __( 'Categories.', 'flx-donate' ) ),
			'earlyDays'      => array( 'Int!', __( 'Early days.', 'flx-donate' ) ),
			'labels'         => array( '[FlxDonationSettingsLabel!]!', __( 'Tracking words.', 'flx-donate' ) ),
			'kurban'         => array( 'Boolean!', __( 'Gifts on behalf of a person are on.', 'flx-donate' ) ),
			'proxyText'      => array( 'String!', __( 'Proxy text.', 'flx-donate' ) ),
			'proxyVersions'  => array( '[String!]!', __( 'Proxy text versions, newest first.', 'flx-donate' ) ),
			'tabsJson'       => array( 'String!', __( 'Own extra tabs (JSON).', 'flx-donate' ) ),
			'tabsInUse'      => array( '[FlxDonationSettingsXTab!]!', __( 'Extra tabs in use.', 'flx-donate' ) ),
			'fieldTypes'     => array( 'String!', __( 'Field types.', 'flx-donate' ) ),
			'tabsExample'    => array( 'String!', __( 'Example (JSON).', 'flx-donate' ) ),
			'importReady'    => array( 'Boolean!', __( 'Flux Forms payments can be read.', 'flx-donate' ) ),
			'importLast'     => array( 'String!', __( 'Last run; empty = never.', 'flx-donate' ) ),
		),
	),
);
foreach ( $flxb_settings_types as $flxb_name => $flxb_def ) {
	flx_gql_type(
		$flxb_name,
		array(
			'description' => $flxb_def[0],
			'fields'      => flxb_gql_admin_fields( $flxb_def[1] ),
		)
	);
}
unset( $flxb_settings_types, $flxb_name, $flxb_def );

foreach (
	array(
		'FlxDonationCategoryInput' => array(
			__( 'A category row.', 'flx-donate' ),
			array(
				'key'   => array( 'String!', __( 'Key; empty = from the name.', 'flx-donate' ) ),
				'label' => array( 'String!', __( 'Name.', 'flx-donate' ) ),
			),
		),
		'FlxDonationLabelInput'    => array(
			__( 'A tracking word.', 'flx-donate' ),
			array(
				'key'   => array( 'String!', __( 'Key.', 'flx-donate' ) ),
				'value' => array( 'String!', __( 'Word; empty = built-in.', 'flx-donate' ) ),
			),
		),
	) as $flxb_name => $flxb_def
) {
	flx_gql_input(
		$flxb_name,
		array(
			'description' => $flxb_def[0],
			'fields'      => flxb_gql_admin_fields( $flxb_def[1] ),
		)
	);
}
unset( $flxb_name, $flxb_def );

flx_gql_query(
	'flxDonationSettings',
	array(
		'type'        => 'FlxDonationSettings!',
		'description' => __( 'Donation settings.', 'flx-donate' ),
		'cap'         => static fn(): bool => FLXB_Admin_Settings_Service::can(),
		'resolve'     => static fn(): array => FLXB_Admin_Settings_Service::settings(),
	)
);

/**
 * A settings mutation.
 *
 * @param array<string,array{0:string,1:string}> $input   Input fields.
 * @param callable                                $resolve Input → result.
 * @return array<string,mixed>
 */
function flxb_gql_settings_mutation( string $description, array $input, callable $resolve ): array {
	return array(
		'description' => $description,
		'cap'         => static fn(): bool => FLXB_Admin_Settings_Service::can(),
		'input'       => flxb_gql_admin_fields( $input ),
		'output'      => flxb_gql_admin_fields( array( 'message' => array( 'String', __( 'Result for the notice; null when refused (see errors).', 'flx-donate' ) ) ) ),
		'resolve'     => static function ( $in ) use ( $resolve ) {
			return $resolve( flxb_gql_args( is_array( $in ) ? $in : array() ) );
		},
	);
}

flx_gql_mutation(
	'flxDonationSettingsGeneral',
	flxb_gql_settings_mutation(
		__( 'Saves the general settings.', 'flx-donate' ),
		array(
			'currency'      => array( 'String!', __( 'Default currency.', 'flx-donate' ) ),
			'donationForms' => array( 'String!', __( 'payments or list.', 'flx-donate' ) ),
			'formIds'       => array( '[Int!]!', __( 'Chosen forms.', 'flx-donate' ) ),
			'moduleTrack'   => array( 'Boolean!', __( 'Tracking module.', 'flx-donate' ) ),
			'moduleReports' => array( 'Boolean!', __( 'Reports.', 'flx-donate' ) ),
			'moduleKurban'  => array( 'Boolean!', __( 'Gifts on behalf of a person.', 'flx-donate' ) ),
		),
		static fn( array $in ): array => FLXB_Admin_Settings_Service::general( $in )
	)
);

flx_gql_mutation(
	'flxDonationSettingsOrganisation',
	flxb_gql_settings_mutation(
		__( 'Saves the organisation (receipts, pages, exports).', 'flx-donate' ),
		array(
			'orgName'      => array( 'String!', __( 'Organisation name.', 'flx-donate' ) ),
			'logoId'       => array( 'Int!', __( 'Logo attachment; 0 = none.', 'flx-donate' ) ),
			'primaryColor' => array( 'String!', __( 'Brand colour (#rrggbb); empty = the theme\'s.', 'flx-donate' ) ),
			'receiptText'  => array( 'String!', __( 'Receipt text.', 'flx-donate' ) ),
			'footerText'   => array( 'String!', __( 'Footer text.', 'flx-donate' ) ),
		),
		static fn( array $in ): array => FLXB_Admin_Settings_Service::organisation( $in )
	)
);

flx_gql_mutation(
	'flxDonationSettingsCategories',
	flxb_gql_settings_mutation(
		__( 'Saves the categories; the ones in use are kept.', 'flx-donate' ),
		array( 'categories' => array( '[FlxDonationCategoryInput!]!', __( 'Rows.', 'flx-donate' ) ) ),
		static fn( array $in ): array => FLXB_Admin_Settings_Service::categories( is_array( $in['categories'] ?? null ) ? $in['categories'] : array() )
	)
);

flx_gql_mutation(
	'flxDonationSettingsTrack',
	flxb_gql_settings_mutation(
		__( 'Saves the tracking words, early days and the proxy text.', 'flx-donate' ),
		array(
			'earlyDays' => array( 'Int!', __( 'Early days (0–30).', 'flx-donate' ) ),
			'labels'    => array( '[FlxDonationLabelInput!]!', __( 'Words.', 'flx-donate' ) ),
			'proxyText' => array( 'String!', __( 'Proxy text (with gifts on behalf of a person).', 'flx-donate' ) ),
		),
		static fn( array $in ): array => FLXB_Admin_Settings_Service::track( $in )
	)
);

flx_gql_mutation(
	'flxDonationSettingsTabs',
	flxb_gql_settings_mutation(
		__( 'Saves the extra tabs (JSON).', 'flx-donate' ),
		array( 'json' => array( 'String!', __( 'Tabs (JSON list); empty = none.', 'flx-donate' ) ) ),
		static fn( array $in ) => FLXB_Admin_Settings_Service::tabs_json( flxb_gql_str( $in['json'] ?? '' ) )
	)
);

flx_gql_mutation(
	'flxDonationSettingsBackfill',
	flxb_gql_settings_mutation(
		__( 'Imports the completed Flux Forms payments that are not donations yet.', 'flx-donate' ),
		array(),
		static fn(): array => FLXB_Admin_Settings_Service::backfill()
	)
);
