<?php
/**
 * Forms › Payments, Automations, System check and Add-ons (Flux GraphQL kit). Loaded on flx_gql_register.
 * Same rights as the classic pages: Payments, System check and Automations need manage_options (Automations:
 * the flxf_automations_capability filter), Add-ons (read only) manage_options or Emsfb.
 *
 * @package FLX_Forms
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------ Payments */

flx_gql_type(
	'FlxFormsPos',
	array(
		'description' => __( 'Flux POS, which takes the payments of payment forms.', 'flx-forms' ),
		'fields'      => array(
			'active'     => flxf_gql_f( 'Boolean!', __( 'Flux POS is active.', 'flx-forms' ) ),
			'installed'  => flxf_gql_f( 'Boolean!', __( 'Flux POS is installed (active or not).', 'flx-forms' ) ),
			'configured' => flxf_gql_f( 'Boolean!', __( 'Flux POS has an active account.', 'flx-forms' ) ),
			'currencies' => flxf_gql_f( '[String!]!', __( 'Currencies a POS account takes.', 'flx-forms' ) ),
			'test'       => flxf_gql_f( 'Boolean!', __( 'An account is in test mode.', 'flx-forms' ) ),
			'url'        => flxf_gql_f( 'String!', __( 'Where to set Flux POS up (its screen, or Plugins when it is not active).', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxFormsPayment',
	array(
		'description' => __( 'A payment taken through a form.', 'flx-forms' ),
		'fields'      => array(
			'id'          => flxf_gql_f( 'Int!', __( 'Record id.', 'flx-forms' ) ),
			'date'        => flxf_gql_f( 'FlxDateTime', __( 'When it was made.', 'flx-forms' ) ),
			'formId'      => flxf_gql_f( 'Int!', __( 'Form id.', 'flx-forms' ) ),
			'formName'    => flxf_gql_f( 'String!', __( 'Form name.', 'flx-forms' ) ),
			'track'       => flxf_gql_f( 'String!', __( 'Confirmation code.', 'flx-forms' ) ),
			'provider'    => flxf_gql_f( 'String!', __( 'Provider name.', 'flx-forms' ) ),
			'type'        => flxf_gql_f( 'String!', __( 'one-time or subscription.', 'flx-forms' ) ),
			'interval'    => flxf_gql_f( 'String!', __( 'Billing interval of a subscription.', 'flx-forms' ) ),
			'amount'      => flxf_gql_f( 'Float!', __( 'Amount.', 'flx-forms' ) ),
			'currency'    => flxf_gql_f( 'String!', __( 'Currency code.', 'flx-forms' ) ),
			'formatted'   => flxf_gql_f( 'String!', __( 'Amount with the currency.', 'flx-forms' ) ),
			'status'      => flxf_gql_f( 'String!', __( 'Status as the provider reports it.', 'flx-forms' ) ),
			'statusLabel' => flxf_gql_f( 'String!', __( 'Status in the user\'s language.', 'flx-forms' ) ),
			'tone'        => flxf_gql_f( 'String!', __( 'ok, pending, failed or refunded.', 'flx-forms' ) ),
			'reference'   => flxf_gql_f( 'String!', __( 'Transaction or subscription id at the provider.', 'flx-forms' ) ),
			'email'       => flxf_gql_f( 'String!', __( 'Payer e-mail.', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxFormsPayments',
	array(
		'description' => __( 'Forms › Payments.', 'flx-forms' ),
		'fields'      => array(
			'available' => flxf_gql_f( 'Boolean!', __( 'The payment records of the forms are kept here (not by Flux Donate).', 'flx-forms' ) ),
			'pos'       => flxf_gql_f( 'FlxFormsPos!', __( 'Flux POS state.', 'flx-forms' ) ),
			'payments'  => flxf_gql_f( '[FlxFormsPayment!]!', __( 'Latest payments, newest first.', 'flx-forms' ) ),
			'limit'     => flxf_gql_f( 'Int!', __( 'How many payments are listed at most.', 'flx-forms' ) ),
		),
	)
);

flx_gql_query(
	'flxFormsPayments',
	array(
		'type'        => 'FlxFormsPayments!',
		'description' => __( 'Flux POS state and the latest payments of the forms.', 'flx-forms' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn() => FLXF_Tools_Service::payments(),
	)
);

/* ------------------------------------------------------------ System check */

flx_gql_type(
	'FlxFormsSystemCheck',
	array(
		'description' => __( 'A system check row.', 'flx-forms' ),
		'fields'      => array(
			'id'          => flxf_gql_f( 'ID!', __( 'Check id.', 'flx-forms' ) ),
			'source'      => flxf_gql_f( 'String!', __( 'Plugin that adds it.', 'flx-forms' ) ),
			'group'       => flxf_gql_f( 'String!', __( 'Group key.', 'flx-forms' ) ),
			'groupLabel'  => flxf_gql_f( 'String!', __( 'Group name.', 'flx-forms' ) ),
			'label'       => flxf_gql_f( 'String!', __( 'What is checked.', 'flx-forms' ) ),
			'status'      => flxf_gql_f( 'String!', __( 'ok, warning, error or info.', 'flx-forms' ) ),
			'message'     => flxf_gql_f( 'String!', __( 'Details.', 'flx-forms' ) ),
			'actionUrl'   => flxf_gql_f( 'String!', __( 'Where to fix it.', 'flx-forms' ) ),
			'actionLabel' => flxf_gql_f( 'String!', __( 'Label of the fix link.', 'flx-forms' ) ),
		),
	)
);

flx_gql_type(
	'FlxFormsSystem',
	array(
		'description' => __( 'Forms › System check.', 'flx-forms' ),
		'fields'      => array(
			'available' => flxf_gql_f( 'Boolean!', __( 'Forms hosts the screen (Flux Donate or Flux Speed is not active).', 'flx-forms' ) ),
			'checks'    => flxf_gql_f( '[FlxFormsSystemCheck!]!', __( 'Every row, in display order.', 'flx-forms' ) ),
		),
	)
);

flx_gql_query(
	'flxFormsSystem',
	array(
		'type'        => 'FlxFormsSystem!',
		'description' => __( 'The shared system check rows of every FluxWP plugin.', 'flx-forms' ),
		'cap'         => 'manage_options',
		'resolve'     => static fn() => FLXF_Tools_Service::system(),
	)
);

/* ------------------------------------------------------------ Add-ons */

flx_gql_type(
	'FlxFormsAddon',
	array(
		'description' => __( 'An add-on that ships with Flux Forms.', 'flx-forms' ),
		'fields'      => array(
			'key'               => flxf_gql_f( 'ID!', __( 'Add-on key.', 'flx-forms' ) ),
			'title'             => flxf_gql_f( 'String!', __( 'Name.', 'flx-forms' ) ),
			'description'       => flxf_gql_f( 'String!', __( 'What it does.', 'flx-forms' ) ),
			'enabled'           => flxf_gql_f( 'Boolean!', __( 'It is on (Flux Forms keeps the bundled add-ons on).', 'flx-forms' ) ),
			'available'         => flxf_gql_f( 'Boolean!', __( 'Its files are in place.', 'flx-forms' ) ),
			'disabledFunctions' => flxf_gql_f( '[String!]!', __( 'PHP functions it needs that php.ini disables.', 'flx-forms' ) ),
			'missingFunctions'  => flxf_gql_f( '[String!]!', __( 'PHP functions it needs that no extension provides.', 'flx-forms' ) ),
		),
	)
);

flx_gql_query(
	'flxFormsAddons',
	array(
		'type'        => '[FlxFormsAddon!]!',
		'description' => __( 'The add-ons that ship with Flux Forms.', 'flx-forms' ),
		'cap'         => flx_gql_any_cap( 'manage_options', 'Emsfb' ),
		'resolve'     => static fn() => FLXF_Tools_Service::addons(),
	)
);

/* ------------------------------------------------------------ Automations */

if ( class_exists( 'FLXF_Automations' ) ) {
	$flxf_auto_cap = FLXF_Automations::capability();

	flx_gql_type(
		'FlxFormsAutomation',
		array(
			'description' => __( 'An automation in the list.', 'flx-forms' ),
			'fields'      => array(
				'id'          => flxf_gql_f( 'Int!', __( 'Flow id.', 'flx-forms' ) ),
				'title'       => flxf_gql_f( 'String!', __( 'Name.', 'flx-forms' ) ),
				'enabled'     => flxf_gql_f( 'Boolean!', __( 'It runs.', 'flx-forms' ) ),
				'triggerType' => flxf_gql_f( 'String!', __( 'form_submitted or payment_completed.', 'flx-forms' ) ),
				'formId'      => flxf_gql_f( 'Int!', __( 'Form it listens to (0 = any).', 'flx-forms' ) ),
				'formTitle'   => flxf_gql_f( 'String!', __( 'Name of that form.', 'flx-forms' ) ),
				'steps'       => flxf_gql_f( '[String!]!', __( 'Step types in order.', 'flx-forms' ) ),
				'lastRun'     => flxf_gql_f( 'FlxDateTime', __( 'Last run.', 'flx-forms' ) ),
				'runs'        => flxf_gql_f( 'Int!', __( 'How many times it ran.', 'flx-forms' ) ),
			),
		)
	);

	flx_gql_type(
		'FlxFormsAutomations',
		array(
			'description' => __( 'Forms › Automations.', 'flx-forms' ),
			'fields'      => array(
				'flows'             => flxf_gql_f( '[FlxFormsAutomation!]!', __( 'Flows, newest first.', 'flx-forms' ) ),
				'certAvailable'     => flxf_gql_f( 'Boolean!', __( 'Flux Certificates is active (certificate steps work).', 'flx-forms' ) ),
				'donationFormId'    => flxf_gql_f( 'Int!', __( 'The sapling donation form (0 = none yet).', 'flx-forms' ) ),
				'donationFormTitle' => flxf_gql_f( 'String!', __( 'Its name.', 'flx-forms' ) ),
				'runCount'          => flxf_gql_f( 'Int!', __( 'Runs in the log.', 'flx-forms' ) ),
				'maxRuns'           => flxf_gql_f( 'Int!', __( 'Runs kept at most.', 'flx-forms' ) ),
			),
		)
	);

	flx_gql_type(
		'FlxFormsAutomationRunStep',
		array(
			'description' => __( 'A step of a run.', 'flx-forms' ),
			'fields'      => array(
				'type'    => flxf_gql_f( 'String!', __( 'Step type.', 'flx-forms' ) ),
				'status'  => flxf_gql_f( 'String!', __( 'ok, partial, error, skipped or running.', 'flx-forms' ) ),
				'message' => flxf_gql_f( 'String!', __( 'Result or error.', 'flx-forms' ) ),
			),
		)
	);

	flx_gql_type(
		'FlxFormsAutomationRun',
		array(
			'description' => __( 'A run of an automation.', 'flx-forms' ),
			'fields'      => array(
				'id'        => flxf_gql_f( 'ID!', __( 'Run id.', 'flx-forms' ) ),
				'time'      => flxf_gql_f( 'FlxDateTime', __( 'When it ran.', 'flx-forms' ) ),
				'flowId'    => flxf_gql_f( 'Int!', __( 'Flow id.', 'flx-forms' ) ),
				'flowTitle' => flxf_gql_f( 'String!', __( 'Flow name.', 'flx-forms' ) ),
				'formTitle' => flxf_gql_f( 'String!', __( 'Form name.', 'flx-forms' ) ),
				'track'     => flxf_gql_f( 'String!', __( 'Tracking code of the response.', 'flx-forms' ) ),
				'status'    => flxf_gql_f( 'String!', __( 'ok, partial, error, skipped or running.', 'flx-forms' ) ),
				'steps'     => flxf_gql_f( '[FlxFormsAutomationRunStep!]!', __( 'Steps.', 'flx-forms' ) ),
			),
		)
	);

	flx_gql_query(
		'flxFormsAutomations',
		array(
			'type'        => 'FlxFormsAutomations!',
			'description' => __( 'The automation list. The first visit creates the ready-made donation flow once.', 'flx-forms' ),
			'cap'         => $flxf_auto_cap,
			'resolve'     => static fn() => FLXF_Automations_Service::overview(),
		)
	);

	flx_gql_query(
		'flxFormsAutomationRuns',
		array(
			'type'        => '[FlxFormsAutomationRun!]!',
			'description' => __( 'The run log, newest first.', 'flx-forms' ),
			'cap'         => $flxf_auto_cap,
			'resolve'     => static fn() => FLXF_Automations_Service::runs(),
		)
	);

	flx_gql_query(
		'flxFormsAutomationEditor',
		array(
			'type'        => 'FlxJSON!',
			'description' => __( 'The flow editor: the flow (null when not found; id 0 gives a blank one), forms with their fields, certificate templates, events, providers and channels.', 'flx-forms' ),
			'cap'         => $flxf_auto_cap,
			'args'        => array( 'id' => flxf_gql_f( 'Int!', __( 'Flow id; 0 for a new flow.', 'flx-forms' ) ) ),
			'resolve'     => static function ( $root, array $args ) {
				unset( $root );
				return FLXF_Automations_Service::editor( flx_gql_int( flx_gql_arr( $args )['id'] ?? 0 ) );
			},
		)
	);

	flx_gql_mutation(
		'flxFormsAutomationSave',
		array(
			'description' => __( 'Saves a flow from the editor (id 0 creates it).', 'flx-forms' ),
			'cap'         => $flxf_auto_cap,
			'input'       => array( 'flow' => flxf_gql_f( 'FlxJSON!', __( 'The flow: id, title, enabled, trigger, match, conditions, actions.', 'flx-forms' ) ) ),
			'output'      => array( 'flow' => flxf_gql_f( 'FlxJSON', __( 'The flow as saved.', 'flx-forms' ) ) ),
			'resolve'     => static function ( array $input ) {
				$r = FLXF_Automations_Service::save( flx_gql_arr( $input )['flow'] ?? null );
				return $r instanceof WP_Error ? $r : array( 'flow' => $r );
			},
		)
	);

	flx_gql_mutation(
		'flxFormsAutomationToggle',
		array(
			'description' => __( 'Turns a flow on or off.', 'flx-forms' ),
			'cap'         => $flxf_auto_cap,
			'input'       => array(
				'id'      => flxf_gql_f( 'Int!', __( 'Flow id.', 'flx-forms' ) ),
				'enabled' => flxf_gql_f( 'Boolean!', __( 'On or off.', 'flx-forms' ) ),
			),
			'output'      => array( 'enabled' => flxf_gql_f( 'Boolean', __( 'State after the change.', 'flx-forms' ) ) ),
			'resolve'     => static function ( array $input ) {
				$in = flx_gql_arr( $input );
				$r  = FLXF_Automations_Service::toggle( flx_gql_int( $in['id'] ?? 0 ), ! empty( $in['enabled'] ) );
				return $r instanceof WP_Error ? $r : array( 'enabled' => $r );
			},
		)
	);

	flx_gql_mutation(
		'flxFormsAutomationDelete',
		array(
			'description' => __( 'Deletes a flow.', 'flx-forms' ),
			'cap'         => $flxf_auto_cap,
			'input'       => array( 'id' => flxf_gql_f( 'Int!', __( 'Flow id.', 'flx-forms' ) ) ),
			'output'      => array( 'deleted' => flxf_gql_f( 'Boolean', __( 'It was deleted.', 'flx-forms' ) ) ),
			'resolve'     => static function ( array $input ) {
				$r = FLXF_Automations_Service::delete( flx_gql_int( flx_gql_arr( $input )['id'] ?? 0 ) );
				return $r instanceof WP_Error ? $r : array( 'deleted' => $r );
			},
		)
	);

	flx_gql_mutation(
		'flxFormsAutomationDuplicate',
		array(
			'description' => __( 'Copies a flow; the copy is off.', 'flx-forms' ),
			'cap'         => $flxf_auto_cap,
			'input'       => array( 'id' => flxf_gql_f( 'Int!', __( 'Flow id.', 'flx-forms' ) ) ),
			'output'      => array( 'id' => flxf_gql_f( 'Int', __( 'Id of the copy.', 'flx-forms' ) ) ),
			'resolve'     => static function ( array $input ) {
				$r = FLXF_Automations_Service::duplicate( flx_gql_int( flx_gql_arr( $input )['id'] ?? 0 ) );
				return $r instanceof WP_Error ? $r : array( 'id' => $r );
			},
		)
	);

	flx_gql_mutation(
		'flxFormsAutomationDefault',
		array(
			'description' => __( 'Creates the ready-made sapling donation → certificate flow.', 'flx-forms' ),
			'cap'         => $flxf_auto_cap,
			'input'       => array(),
			'output'      => array( 'id' => flxf_gql_f( 'Int', __( 'Id of the new flow.', 'flx-forms' ) ) ),
			'resolve'     => static function () {
				$r = FLXF_Automations_Service::create_default();
				return $r instanceof WP_Error ? $r : array( 'id' => $r );
			},
		)
	);

	flx_gql_mutation(
		'flxFormsAutomationDonationForm',
		array(
			'description' => __( 'Creates the sapling donation form (once) and points the unattached default flows at it.', 'flx-forms' ),
			'cap'         => 'manage_options',
			'input'       => array(),
			'output'      => array(
				'formId'    => flxf_gql_f( 'Int', __( 'Form id.', 'flx-forms' ) ),
				'shortcode' => flxf_gql_f( 'String', __( 'Its shortcode.', 'flx-forms' ) ),
			),
			'resolve'     => static fn() => FLXF_Automations_Service::donation_form(),
		)
	);

	flx_gql_mutation(
		'flxFormsAutomationRunsClear',
		array(
			'description' => __( 'Empties the run log.', 'flx-forms' ),
			'cap'         => $flxf_auto_cap,
			'input'       => array(),
			'output'      => array( 'cleared' => flxf_gql_f( 'Boolean', __( 'The log was emptied.', 'flx-forms' ) ) ),
			'resolve'     => static fn() => array( 'cleared' => FLXF_Automations_Service::clear_runs() ),
		)
	);
}
