<?php
/**
 * Flux POS GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a GraphQL
 * request. Resolvers call FLXPOS_Screen_Service; the Flux POS screen (wp-admin and the FluxWP app)
 * reads and writes only through these fields.
 *
 * POS keys are write-only: reads return whether a secret is saved and its masked tail, writes accept a
 * new one (empty keeps the saved one). Card data never reaches GraphQL; transactions carry the masked
 * card only.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Typed parent value of a field.
 *
 * @template T of object
 * @param mixed           $source Parent.
 * @param class-string<T> $class  Expected class.
 * @return T
 * @throws FLXGQL_Error When the parent has another type.
 */
function flxpos_gql_src( $source, string $class ) {
	if ( ! $source instanceof $class ) {
		throw flx_gql_error( 'INTERNAL' );
	}
	return $source;
}

/** Marks the read for the cache. */
function flxpos_gql_tag(): void {
	flx_gql_cache_tag( FLXPOS_Screen_Service::CACHE_TAG );
}

/** Who may use the screen (shop managers and administrators). */
function flxpos_gql_cap(): bool {
	return FLXPOS_Screen_Service::can();
}

/* ------------------------------------------------------------------ */
/* Enums                                                               */
/* ------------------------------------------------------------------ */

flx_gql_enum(
	'FlxPosCapState',
	array(
		'description' => __( 'Whether a payment method can do something on this site.', 'flx-pos' ),
		'values'      => array(
			'YES'     => array( 'value' => 'yes', 'description' => __( 'Yes.', 'flx-pos' ) ),
			'FLUX'    => array( 'value' => 'flux', 'description' => __( 'Yes, a FluxWP plugin does it.', 'flx-pos' ) ),
			'PAGE'    => array( 'value' => 'page', 'description' => __( 'On the provider\'s page.', 'flx-pos' ) ),
			'NO'      => array( 'value' => 'no', 'description' => __( 'No.', 'flx-pos' ) ),
			'UNKNOWN' => array( 'value' => 'unknown', 'description' => __( 'Unknown; try it.', 'flx-pos' ) ),
		),
	)
);

flx_gql_enum(
	'FlxPosProviderGroup',
	array(
		'description' => __( 'Kind of POS provider.', 'flx-pos' ),
		'values'      => array(
			'BANK'   => array( 'value' => 'bank', 'description' => __( 'Bank virtual POS.', 'flx-pos' ) ),
			'PSP'    => array( 'value' => 'psp', 'description' => __( 'Payment institution.', 'flx-pos' ) ),
			'WALLET' => array( 'value' => 'wallet', 'description' => __( 'Wallet or alternative method (a separate checkout method).', 'flx-pos' ) ),
			'MEAL'   => array( 'value' => 'meal', 'description' => __( 'Meal and gift cards.', 'flx-pos' ) ),
		),
	)
);

flx_gql_enum(
	'FlxPosTxnStatus',
	array(
		'description' => __( 'Result of a transaction.', 'flx-pos' ),
		'values'      => array(
			'PENDING'  => array( 'value' => 'pending', 'description' => __( 'Started; waiting for the provider.', 'flx-pos' ) ),
			'PAID'     => array( 'value' => 'paid', 'description' => __( 'Paid.', 'flx-pos' ) ),
			'FAILED'   => array( 'value' => 'failed', 'description' => __( 'Failed.', 'flx-pos' ) ),
			'REFUNDED' => array( 'value' => 'refunded', 'description' => __( 'Refunded in full.', 'flx-pos' ) ),
			'PARTIAL'  => array( 'value' => 'partial', 'description' => __( 'Refunded in part.', 'flx-pos' ) ),
			'VOIDED'   => array( 'value' => 'voided', 'description' => __( 'Voided before end of day.', 'flx-pos' ) ),
			'REVIEW'   => array( 'value' => 'review', 'description' => __( 'Paid at the provider, but the amount or order does not match; check it.', 'flx-pos' ) ),
		),
	)
);

flx_gql_enum(
	'FlxPosTxnKind',
	array(
		'description' => __( 'Kind of transaction.', 'flx-pos' ),
		'values'      => array(
			'SALE'   => array( 'value' => 'sale', 'description' => __( 'Payment.', 'flx-pos' ) ),
			'REFUND' => array( 'value' => 'refund', 'description' => __( 'Refund.', 'flx-pos' ) ),
			'VOID'   => array( 'value' => 'void', 'description' => __( 'Void.', 'flx-pos' ) ),
		),
	)
);

/* ------------------------------------------------------------------ */
/* Types                                                               */
/* ------------------------------------------------------------------ */

flx_gql_type(
	'FlxPosOption',
	array(
		'description' => __( 'A value with its label.', 'flx-pos' ),
		'fields'      => array(
			'value' => array(
				'type'        => 'String!',
				'description' => __( 'Stored value.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Option::class )->value,
			),
			'label' => array(
				'type'        => 'String!',
				'description' => __( 'Label in the user\'s language.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Option::class )->label,
			),
		),
	)
);

flx_gql_type(
	'FlxPosCap',
	array(
		'description' => __( 'What a payment method can do on this site.', 'flx-pos' ),
		'fields'      => array(
			'key' => array(
				'type'        => 'String!',
				'description' => __( 'Capability key (blocks, no_cart, no_address, verified, installments, tokenization, refunds, monthly).', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Cap::class )->key,
			),
			'label' => array(
				'type'        => 'String!',
				'description' => __( 'Column label.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Cap::class )->label,
			),
			'help' => array(
				'type'        => 'String!',
				'description' => __( 'What the capability means.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Cap::class )->help,
			),
			'state' => array(
				'type'        => 'FlxPosCapState!',
				'description' => __( 'Whether the method can do it.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Cap::class )->state,
			),
			'note' => array(
				'type'        => 'String!',
				'description' => __( 'Detail; empty when none.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Cap::class )->note,
			),
		),
	)
);

flx_gql_type(
	'FlxPosMethod',
	array(
		'description' => __( 'A WooCommerce payment method of any plugin, in checkout order.', 'flx-pos' ),
		'fields'      => array(
			'id' => array(
				'type'        => 'String!',
				'description' => __( 'Gateway id.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Method::class )->id,
			),
			'title' => array(
				'type'        => 'String!',
				'description' => __( 'Title the payer sees at checkout.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Method::class )->title,
			),
			'methodTitle' => array(
				'type'        => 'String!',
				'description' => __( 'Name of the method in the admin.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Method::class )->method_title,
			),
			'description' => array(
				'type'        => 'String!',
				'description' => __( 'Description the payer sees under the title (plain text).', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Method::class )->description,
			),
			'enabled' => array(
				'type'        => 'Boolean!',
				'description' => __( 'The method is on in WooCommerce.', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Method::class )->enabled,
			),
			'order' => array(
				'type'        => 'Int!',
				'description' => __( 'Position at checkout, from 0.', 'flx-pos' ),
				'resolve'     => static fn( $s ): int => flxpos_gql_src( $s, FLXPOS_Method::class )->order,
			),
			'icons' => array(
				'type'        => '[String!]!',
				'description' => __( 'Logo addresses the method shows at checkout.', 'flx-pos' ),
				'resolve'     => static fn( $s ): array => flxpos_gql_src( $s, FLXPOS_Method::class )->icons,
			),
			'flux' => array(
				'type'        => 'Boolean!',
				'description' => __( 'A Flux POS method (its settings are on this screen).', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Method::class )->flux,
			),
			'hidden' => array(
				'type'        => 'Boolean!',
				'description' => __( 'On, but hidden at checkout by the one-card rule.', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Method::class )->hidden,
			),
			'hasFields' => array(
				'type'        => 'Boolean!',
				'description' => __( 'The method draws its own fields (card form) at checkout.', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Method::class )->has_fields,
			),
			'offline' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Bank transfer, cheque or cash on delivery.', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Method::class )->offline,
			),
			'settingsUrl' => array(
				'type'        => 'String!',
				'description' => __( 'Where the method\'s own settings are.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Method::class )->settings_url,
			),
			'caps' => array(
				'type'        => '[FlxPosCap!]!',
				'description' => __( 'What the method can do on this site.', 'flx-pos' ),
				'resolve'     => static fn( $s ): array => flxpos_gql_src( $s, FLXPOS_Method::class )->caps,
			),
		),
	)
);

flx_gql_type(
	'FlxPosMethods',
	array(
		'description' => __( 'Every payment method and the rules around them.', 'flx-pos' ),
		'fields'      => array(
			'methods' => array(
				'type'        => '[FlxPosMethod!]!',
				'description' => __( 'Methods in checkout order.', 'flx-pos' ),
				'resolve'     => static fn( $s ): array => flxpos_gql_src( $s, FLXPOS_Methods_Info::class )->methods,
			),
			'blockCheckout' => array(
				'type'        => 'Boolean!',
				'description' => __( 'The checkout page is built with the Checkout block.', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Methods_Info::class )->block_checkout,
			),
			'cardMethod' => array(
				'type'        => 'String!',
				'description' => __( 'One-card rule: auto, iyzico, flxpos, a gateway id or all.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Methods_Info::class )->card_method,
			),
			'cardMethodOwner' => array(
				'type'        => 'String!',
				'description' => __( 'Which plugin runs the one-card rule: donate (Flux Donate) or pos.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Methods_Info::class )->card_method_owner,
			),
			'cardChoices' => array(
				'type'        => '[FlxPosOption!]!',
				'description' => __( 'Choices of the one-card rule.', 'flx-pos' ),
				'resolve'     => static fn( $s ): array => flxpos_gql_src( $s, FLXPOS_Methods_Info::class )->card_choices,
			),
			'checkoutUrl' => array(
				'type'        => 'String!',
				'description' => __( 'Checkout page address.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Methods_Info::class )->checkout_url,
			),
			'currency' => array(
				'type'        => 'String!',
				'description' => __( 'Store currency.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Methods_Info::class )->currency,
			),
		),
	)
);

flx_gql_type(
	'FlxPosField',
	array(
		'description' => __( 'A field of a provider\'s account form.', 'flx-pos' ),
		'fields'      => array(
			'key' => array(
				'type'        => 'String!',
				'description' => __( 'Field key.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Field::class )->key,
			),
			'label' => array(
				'type'        => 'String!',
				'description' => __( 'Field label.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Field::class )->label,
			),
			'secret' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Write-only secret (stored encrypted, never returned).', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Field::class )->secret,
			),
			'help' => array(
				'type'        => 'String!',
				'description' => __( 'Help text; empty when none.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Field::class )->help,
			),
			'defaultValue' => array(
				'type'        => 'String!',
				'description' => __( 'Value used when left empty.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Field::class )->default_value,
			),
		),
	)
);

flx_gql_type(
	'FlxPosProvider',
	array(
		'description' => __( 'A bank, payment institution or wallet of the catalog.', 'flx-pos' ),
		'fields'      => array(
			'id' => array(
				'type'        => 'String!',
				'description' => __( 'Provider id.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Provider::class )->id,
			),
			'name' => array(
				'type'        => 'String!',
				'description' => __( 'Provider name.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Provider::class )->name,
			),
			'group' => array(
				'type'        => 'FlxPosProviderGroup!',
				'description' => __( 'Kind of provider.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Provider::class )->group,
			),
			'ready' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Flux POS has a driver for it; otherwise it waits for documentation.', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Provider::class )->ready,
			),
			'modes' => array(
				'type'        => '[FlxPosOption!]!',
				'description' => __( 'Payment models the provider offers.', 'flx-pos' ),
				'resolve'     => static fn( $s ): array => flxpos_gql_src( $s, FLXPOS_Provider::class )->modes,
			),
			'fields' => array(
				'type'        => '[FlxPosField!]!',
				'description' => __( 'Account fields.', 'flx-pos' ),
				'resolve'     => static fn( $s ): array => flxpos_gql_src( $s, FLXPOS_Provider::class )->fields,
			),
			'currencies' => array(
				'type'        => '[String!]!',
				'description' => __( 'Supported currencies.', 'flx-pos' ),
				'resolve'     => static fn( $s ): array => flxpos_gql_src( $s, FLXPOS_Provider::class )->currencies,
			),
			'caps' => array(
				'type'        => '[String!]!',
				'description' => __( 'Driver capabilities: refund, partial, cancel, status, installments, notify.', 'flx-pos' ),
				'resolve'     => static fn( $s ): array => flxpos_gql_src( $s, FLXPOS_Provider::class )->caps,
			),
			'notes' => array(
				'type'        => 'String!',
				'description' => __( 'Notes for the merchant; empty when none.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Provider::class )->notes,
			),
			'endpoints' => array(
				'type'        => '[FlxPosOption!]!',
				'description' => __( 'Live endpoint addresses (value = key, label = address); an account can override them.', 'flx-pos' ),
				'resolve'     => static fn( $s ): array => flxpos_gql_src( $s, FLXPOS_Provider::class )->endpoints,
			),
			'testCards' => array(
				'type'        => 'Int!',
				'description' => __( 'Number of published test cards.', 'flx-pos' ),
				'resolve'     => static fn( $s ): int => flxpos_gql_src( $s, FLXPOS_Provider::class )->test_cards,
			),
		),
	)
);

flx_gql_type(
	'FlxPosCred',
	array(
		'description' => __( 'A saved credential. A secret is never returned: only whether it is saved and its masked tail.', 'flx-pos' ),
		'fields'      => array(
			'key' => array(
				'type'        => 'String!',
				'description' => __( 'Field key (url_<endpoint> for an endpoint override).', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Cred::class )->key,
			),
			'value' => array(
				'type'        => 'String!',
				'description' => __( 'Value of a plain field; always empty for a secret.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Cred::class )->value,
			),
			'isSet' => array(
				'type'        => 'Boolean!',
				'description' => __( 'A value is saved.', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Cred::class )->is_set,
			),
			'mask' => array(
				'type'        => 'String!',
				'description' => __( 'Masked last characters of a saved secret; empty otherwise.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Cred::class )->mask,
			),
		),
	)
);

flx_gql_type(
	'FlxPosInstallment',
	array(
		'description' => __( 'One installment option.', 'flx-pos' ),
		'fields'      => array(
			'count' => array(
				'type'        => 'Int!',
				'description' => __( 'Number of installments (2-36).', 'flx-pos' ),
				'resolve'     => static fn( $s ): int => flxpos_gql_src( $s, FLXPOS_Installment::class )->count,
			),
			'rate' => array(
				'type'        => 'Float!',
				'description' => __( 'Extra charge in percent.', 'flx-pos' ),
				'resolve'     => static fn( $s ): float => flxpos_gql_src( $s, FLXPOS_Installment::class )->rate,
			),
		),
	)
);

flx_gql_type(
	'FlxPosAccount',
	array(
		'description' => __( 'A POS account: provider, keys and options.', 'flx-pos' ),
		'fields'      => array(
			'id' => array(
				'type'        => 'String!',
				'description' => __( 'Account id.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->id,
			),
			'provider' => array(
				'type'        => 'String!',
				'description' => __( 'Provider id.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->provider,
			),
			'providerName' => array(
				'type'        => 'String!',
				'description' => __( 'Provider name.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->provider_name,
			),
			'group' => array(
				'type'        => 'FlxPosProviderGroup!',
				'description' => __( 'Kind of provider.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->group,
			),
			'label' => array(
				'type'        => 'String!',
				'description' => __( 'Own name; empty uses the provider name.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->label,
			),
			'name' => array(
				'type'        => 'String!',
				'description' => __( 'Name shown in lists.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->name,
			),
			'enabled' => array(
				'type'        => 'Boolean!',
				'description' => __( 'The account takes payments.', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->enabled,
			),
			'test' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Test mode (the provider\'s test service).', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->test,
			),
			'mode' => array(
				'type'        => 'String!',
				'description' => __( 'Payment model.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->mode,
			),
			'creds' => array(
				'type'        => '[FlxPosCred!]!',
				'description' => __( 'Saved credentials (secrets masked).', 'flx-pos' ),
				'resolve'     => static fn( $s ): array => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->creds,
			),
			'currencies' => array(
				'type'        => '[String!]!',
				'description' => __( 'Chosen currencies; empty means every currency of the provider.', 'flx-pos' ),
				'resolve'     => static fn( $s ): array => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->currencies,
			),
			'installments' => array(
				'type'        => '[FlxPosInstallment!]!',
				'description' => __( 'Installment table; empty means single payment only.', 'flx-pos' ),
				'resolve'     => static fn( $s ): array => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->installments,
			),
			'instMin' => array(
				'type'        => 'Float!',
				'description' => __( 'Smallest amount that offers installments.', 'flx-pos' ),
				'resolve'     => static fn( $s ): float => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->inst_min,
			),
			'title' => array(
				'type'        => 'String!',
				'description' => __( 'Title at checkout for a wallet or a hosted page.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->title,
			),
			'display' => array(
				'type'        => 'String!',
				'description' => __( 'Display of the provider page: redirect, modal, inline; empty uses the general setting.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->display,
			),
			'notifyUrl' => array(
				'type'        => 'String!',
				'description' => __( 'Address to enter in the provider\'s panel for server notifications.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->notify_url,
			),
			'takesCard' => array(
				'type'        => 'Boolean!',
				'description' => __( 'The card is typed on this site (otherwise on the provider\'s page).', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Account_DTO::class )->takes_card,
			),
		),
	)
);

flx_gql_type(
	'FlxPosSettings',
	array(
		'description' => __( 'General Flux POS settings.', 'flx-pos' ),
		'fields'      => array(
			'cardTitle' => array(
				'type'        => 'String!',
				'description' => __( 'Title of the card method at checkout; empty uses the default.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Settings_DTO::class )->card_title,
			),
			'cardDesc' => array(
				'type'        => 'String!',
				'description' => __( 'Description of the card method.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Settings_DTO::class )->card_desc,
			),
			'cardAccount' => array(
				'type'        => 'String!',
				'description' => __( 'Account that takes card payments; empty uses the first fitting one.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Settings_DTO::class )->card_account,
			),
			'routes' => array(
				'type'        => '[FlxPosOption!]!',
				'description' => __( 'Account per currency (value = currency, label = account id).', 'flx-pos' ),
				'resolve'     => static fn( $s ): array => flxpos_gql_src( $s, FLXPOS_Settings_DTO::class )->routes,
			),
			'display' => array(
				'type'        => 'String!',
				'description' => __( 'Display of the provider page: redirect, modal or inline.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Settings_DTO::class )->display,
			),
			'showBrands' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Card brand logos in the card form.', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Settings_DTO::class )->show_brands,
			),
			'blockFallback' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Show redirect plugins without block support on the Checkout block.', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Settings_DTO::class )->block_fallback,
			),
			'saveLog' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Keep provider answers in the transaction log (never card data or keys).', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Settings_DTO::class )->save_log,
			),
			'displays' => array(
				'type'        => '[FlxPosOption!]!',
				'description' => __( 'Display choices.', 'flx-pos' ),
				'resolve'     => static fn( $s ): array => flxpos_gql_src( $s, FLXPOS_Settings_DTO::class )->displays,
			),
		),
	)
);

flx_gql_type(
	'FlxPosTxn',
	array(
		'description' => __( 'A payment attempt, refund or void. The card is masked (first 6 and last 4 digits).', 'flx-pos' ),
		'fields'      => array(
			'databaseId' => array(
				'type'        => 'Int!',
				'description' => __( 'Transaction id.', 'flx-pos' ),
				'resolve'     => static fn( $s ): int => flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->id,
			),
			'ref' => array(
				'type'        => 'String!',
				'description' => __( 'Reference sent to the provider.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->ref,
			),
			'bankRef' => array(
				'type'        => 'String!',
				'description' => __( 'Provider reference; empty when none.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->bank_ref,
			),
			'kind' => array(
				'type'        => 'FlxPosTxnKind!',
				'description' => __( 'Sale, refund or void.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->kind,
			),
			'account' => array(
				'type'        => 'String!',
				'description' => __( 'Account id.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->account,
			),
			'accountName' => array(
				'type'        => 'String!',
				'description' => __( 'Account name.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->account_label,
			),
			'orderId' => array(
				'type'        => 'Int!',
				'description' => __( 'WooCommerce order id; 0 without an order.', 'flx-pos' ),
				'resolve'     => static fn( $s ): int => flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->order_id,
			),
			'orderNumber' => array(
				'type'        => 'String!',
				'description' => __( 'Order number; empty without an order.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->order_number,
			),
			'orderUrl' => array(
				'type'        => 'String!',
				'description' => __( 'Order edit address; empty without an order.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->order_url,
			),
			'amount' => array(
				'type'        => 'FlxMoney!',
				'description' => __( 'Amount.', 'flx-pos' ),
				'resolve'     => static fn( $s ): array => flx_gql_money( flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->amount, flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->currency ),
			),
			'refunded' => array(
				'type'        => 'FlxMoney!',
				'description' => __( 'Refunded so far.', 'flx-pos' ),
				'resolve'     => static fn( $s ): array => flx_gql_money( flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->refunded, flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->currency ),
			),
			'installment' => array(
				'type'        => 'Int!',
				'description' => __( 'Number of installments (1 = single).', 'flx-pos' ),
				'resolve'     => static fn( $s ): int => flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->installment,
			),
			'card' => array(
				'type'        => 'String!',
				'description' => __( 'Masked card; empty when none.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->card,
			),
			'status' => array(
				'type'        => 'FlxPosTxnStatus!',
				'description' => __( 'Result.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->status,
			),
			'message' => array(
				'type'        => 'String!',
				'description' => __( 'Provider message; empty when none.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->message,
			),
			'test' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Made in test mode.', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->test,
			),
			'created' => array(
				'type'        => 'FlxDateTime!',
				'description' => __( 'When it started.', 'flx-pos' ),
				'resolve'     => static fn( $s ): ?array => flx_gql_datetime( flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->created ),
			),
			'refundable' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Can be refunded from this screen.', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Txn_DTO::class )->refundable,
			),
		),
	)
);

flx_gql_type(
	'FlxPosTestResult',
	array(
		'description' => __( 'Result of an account connection test.', 'flx-pos' ),
		'fields'      => array(
			'ok' => array(
				'type'        => 'Boolean!',
				'description' => __( 'The provider accepted the keys.', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Test_Result::class )->ok,
			),
			'supported' => array(
				'type'        => 'Boolean!',
				'description' => __( 'The provider has a connection test; otherwise try a small payment with a test card.', 'flx-pos' ),
				'resolve'     => static fn( $s ): bool => flxpos_gql_src( $s, FLXPOS_Test_Result::class )->supported,
			),
			'message' => array(
				'type'        => 'String!',
				'description' => __( 'What happened, in the user\'s language.', 'flx-pos' ),
				'resolve'     => static fn( $s ): string => flxpos_gql_src( $s, FLXPOS_Test_Result::class )->message,
			),
		),
	)
);

flx_gql_connection( 'FlxPosTxn', __( 'A page of transactions, newest first.', 'flx-pos' ) );

/* ------------------------------------------------------------------ */
/* Queries                                                             */
/* ------------------------------------------------------------------ */

flx_gql_query(
	'flxPosMethods',
	array(
		'type'        => 'FlxPosMethods!',
		'description' => __( 'Every WooCommerce payment method (any plugin) in checkout order, with its switch and what it can do here.', 'flx-pos' ),
		'cap'         => static fn(): bool => flxpos_gql_cap(),
		'resolve'     => static function (): FLXPOS_Methods_Info {
			flxpos_gql_tag();
			return FLXPOS_Screen_Service::methods();
		},
	)
);

flx_gql_query(
	'flxPosProviders',
	array(
		'type'        => '[FlxPosProvider!]!',
		'description' => __( 'Catalog of banks, payment institutions and wallets Flux POS can connect to.', 'flx-pos' ),
		'cap'         => static fn(): bool => flxpos_gql_cap(),
		'resolve'     => static function (): array {
			flxpos_gql_tag();
			return FLXPOS_Screen_Service::providers();
		},
	)
);

flx_gql_query(
	'flxPosAccounts',
	array(
		'type'        => '[FlxPosAccount!]!',
		'description' => __( 'Saved POS accounts. Secret keys are never returned.', 'flx-pos' ),
		'cap'         => static fn(): bool => flxpos_gql_cap(),
		'resolve'     => static function (): array {
			flxpos_gql_tag();
			return FLXPOS_Screen_Service::accounts();
		},
	)
);

flx_gql_query(
	'flxPosSettings',
	array(
		'type'        => 'FlxPosSettings!',
		'description' => __( 'General Flux POS settings.', 'flx-pos' ),
		'cap'         => static fn(): bool => flxpos_gql_cap(),
		'resolve'     => static function (): FLXPOS_Settings_DTO {
			flxpos_gql_tag();
			return FLXPOS_Screen_Service::settings();
		},
	)
);

flx_gql_query(
	'flxPosTxns',
	array(
		'type'        => 'FlxPosTxnConnection!',
		'description' => __( 'Transactions, newest first.', 'flx-pos' ),
		'cap'         => static fn(): bool => flxpos_gql_cap(),
		'args'        => flx_gql_connection_args(
			array(
				'status'  => array(
					'type'        => 'FlxPosTxnStatus',
					'description' => __( 'Only this result.', 'flx-pos' ),
				),
				'account' => array(
					'type'        => 'String',
					'description' => __( 'Only this account.', 'flx-pos' ),
				),
				'search'  => array(
					'type'        => 'String',
					'description' => __( 'Reference, order number, card or message.', 'flx-pos' ),
				),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			flxpos_gql_tag();
			$filter = array(
				'status'  => flx_gql_str( $args['status'] ?? '' ),
				'account' => flx_gql_str( $args['account'] ?? '' ),
				'search'  => flx_gql_str( $args['search'] ?? '' ),
			);
			return flx_gql_paginate( $args, static fn( int $offset, int $limit ): array => FLXPOS_Screen_Service::txns( $filter, $offset, $limit ) );
		},
	)
);

/* ------------------------------------------------------------------ */
/* Mutations                                                           */
/* ------------------------------------------------------------------ */

flx_gql_input(
	'FlxPosSwitchInput',
	array(
		'description' => __( 'Turns one payment method on or off.', 'flx-pos' ),
		'fields'      => array(
			'id'      => array(
				'type'        => 'String!',
				'description' => __( 'Gateway id.', 'flx-pos' ),
			),
			'enabled' => array(
				'type'        => 'Boolean!',
				'description' => __( 'On.', 'flx-pos' ),
			),
		),
	)
);

flx_gql_mutation(
	'flxPosMethodsUpdate',
	array(
		'description' => __( 'Changes the checkout order, the switches and the one-card rule of the payment methods. Fields left out stay as they are.', 'flx-pos' ),
		'cap'         => static fn(): bool => flxpos_gql_cap(),
		'input'       => array(
			'order'      => array(
				'type'        => '[String!]',
				'description' => __( 'Gateway ids in the new checkout order; ids left out keep their place after these.', 'flx-pos' ),
			),
			'switches'   => array(
				'type'        => '[FlxPosSwitchInput!]',
				'description' => __( 'Switches to change.', 'flx-pos' ),
			),
			'cardMethod' => array(
				'type'        => 'String',
				'description' => __( 'One-card rule: auto, iyzico, flxpos, a gateway id or all.', 'flx-pos' ),
			),
		),
		'output'      => array(
			'methods' => array(
				'type'        => 'FlxPosMethods!',
				'description' => __( 'Methods after the change.', 'flx-pos' ),
				'resolve'     => static fn(): FLXPOS_Methods_Info => FLXPOS_Screen_Service::methods(),
			),
		),
		'resolve'     => static function ( array $input ) {
			$order    = array_values( array_map( 'flx_gql_str', flx_gql_arr( $input['order'] ?? array() ) ) );
			$switches = array();
			foreach ( flx_gql_arr( $input['switches'] ?? array() ) as $sw ) {
				$sw         = flx_gql_arr( $sw );
				$switches[] = array(
					'id'      => flx_gql_str( $sw['id'] ?? '' ),
					'enabled' => (bool) ( $sw['enabled'] ?? false ),
				);
			}
			$card = isset( $input['cardMethod'] ) ? flx_gql_str( $input['cardMethod'] ) : null;
			$r    = FLXPOS_Screen_Service::update_methods( $order, $switches, $card );
			return $r instanceof WP_Error ? $r : array();
		},
	)
);

flx_gql_input(
	'FlxPosCredInput',
	array(
		'description' => __( 'One account field. A secret is write-only: empty keeps the saved value.', 'flx-pos' ),
		'fields'      => array(
			'key'   => array(
				'type'        => 'String!',
				'description' => __( 'Field key (url_<endpoint> overrides an endpoint).', 'flx-pos' ),
			),
			'value' => array(
				'type'        => 'String!',
				'description' => __( 'Value.', 'flx-pos' ),
			),
		),
	)
);

flx_gql_input(
	'FlxPosInstallmentInput',
	array(
		'description' => __( 'One installment option.', 'flx-pos' ),
		'fields'      => array(
			'count' => array(
				'type'        => 'Int!',
				'description' => __( 'Number of installments (2-36).', 'flx-pos' ),
			),
			'rate'  => array(
				'type'        => 'Float!',
				'description' => __( 'Extra charge in percent.', 'flx-pos' ),
			),
		),
	)
);

flx_gql_mutation(
	'flxPosAccountSave',
	array(
		'description' => __( 'Adds a POS account or updates one. Secret keys left empty keep the saved value; keys are stored encrypted.', 'flx-pos' ),
		'cap'         => static fn(): bool => flxpos_gql_cap(),
		'input'       => array(
			'id'           => array(
				'type'        => 'String',
				'description' => __( 'Account id; empty adds a new account.', 'flx-pos' ),
			),
			'provider'     => array(
				'type'        => 'String!',
				'description' => __( 'Provider id from flxPosProviders.', 'flx-pos' ),
			),
			'label'        => array(
				'type'        => 'String',
				'description' => __( 'Own name.', 'flx-pos' ),
			),
			'enabled'      => array(
				'type'        => 'Boolean',
				'description' => __( 'Takes payments.', 'flx-pos' ),
			),
			'test'         => array(
				'type'        => 'Boolean',
				'description' => __( 'Test mode.', 'flx-pos' ),
			),
			'mode'         => array(
				'type'        => 'String',
				'description' => __( 'Payment model.', 'flx-pos' ),
			),
			'creds'        => array(
				'type'        => '[FlxPosCredInput!]',
				'description' => __( 'Account fields.', 'flx-pos' ),
			),
			'currencies'   => array(
				'type'        => '[String!]',
				'description' => __( 'Currencies; empty means all of the provider\'s.', 'flx-pos' ),
			),
			'installments' => array(
				'type'        => '[FlxPosInstallmentInput!]',
				'description' => __( 'Installment table; replaces the saved one.', 'flx-pos' ),
			),
			'instMin'      => array(
				'type'        => 'Float',
				'description' => __( 'Smallest amount that offers installments.', 'flx-pos' ),
			),
			'title'        => array(
				'type'        => 'String',
				'description' => __( 'Title at checkout for a wallet or a hosted page.', 'flx-pos' ),
			),
			'display'      => array(
				'type'        => 'String',
				'description' => __( 'redirect, modal, inline; empty uses the general setting.', 'flx-pos' ),
			),
		),
		'output'      => array(
			'account'  => array(
				'type'        => 'FlxPosAccount',
				'description' => __( 'The saved account.', 'flx-pos' ),
			),
			'accounts' => array(
				'type'        => '[FlxPosAccount!]!',
				'description' => __( 'Every account after the change.', 'flx-pos' ),
				'resolve'     => static fn(): array => FLXPOS_Screen_Service::accounts(),
			),
		),
		'resolve'     => static function ( array $input ) {
			$id  = flx_gql_str( $input['id'] ?? '' );
			$old = '' !== $id ? FLXPOS_Accounts::get( $id ) : null;
			$old = is_array( $old ) ? $old : array();
			$in  = array(
				'id'       => $id,
				'provider' => flx_gql_str( $input['provider'] ?? '' ),
			);
			foreach ( array( 'label', 'mode', 'title', 'display' ) as $k ) {
				$in[ $k ] = isset( $input[ $k ] ) ? flx_gql_str( $input[ $k ] ) : ( $old[ $k ] ?? '' );
			}
			foreach ( array( 'enabled', 'test' ) as $k ) {
				$in[ $k ] = isset( $input[ $k ] ) ? (bool) $input[ $k ] : (bool) ( $old[ $k ] ?? ( 'enabled' === $k || 'test' === $k ) );
			}
			$creds = array();
			foreach ( flx_gql_arr( $input['creds'] ?? array() ) as $c ) {
				$c                                      = flx_gql_arr( $c );
				$creds[ flx_gql_str( $c['key'] ?? '' ) ] = flx_gql_str( $c['value'] ?? '' );
			}
			$in['creds']      = $creds;
			$in['currencies'] = isset( $input['currencies'] ) ? array_values( array_map( 'flx_gql_str', flx_gql_arr( $input['currencies'] ) ) ) : ( $old['currencies'] ?? array() );
			if ( isset( $input['installments'] ) ) {
				$inst = array();
				foreach ( flx_gql_arr( $input['installments'] ) as $r ) {
					$r                                   = flx_gql_arr( $r );
					$inst[ flx_gql_int( $r['count'] ?? 0 ) ] = is_numeric( $r['rate'] ?? null ) ? (string) $r['rate'] : '0';
				}
				$in['installments'] = $inst;
			} else {
				$in['installments'] = $old['installments'] ?? array();
			}
			$in['inst_min'] = isset( $input['instMin'] ) && is_numeric( $input['instMin'] ) ? (float) $input['instMin'] : ( $old['inst_min'] ?? 0 );
			$r              = FLXPOS_Screen_Service::save_account( $in );
			return $r instanceof WP_Error ? $r : array( 'account' => $r );
		},
	)
);

flx_gql_mutation(
	'flxPosAccountDelete',
	array(
		'description' => __( 'Deletes a POS account and its encrypted keys. Its transactions stay.', 'flx-pos' ),
		'cap'         => static fn(): bool => flxpos_gql_cap(),
		'input'       => array(
			'id' => array(
				'type'        => 'String!',
				'description' => __( 'Account id.', 'flx-pos' ),
			),
		),
		'output'      => array(
			'accounts' => array(
				'type'        => '[FlxPosAccount!]!',
				'description' => __( 'Every account after the change.', 'flx-pos' ),
				'resolve'     => static fn(): array => FLXPOS_Screen_Service::accounts(),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXPOS_Screen_Service::delete_account( flx_gql_str( $input['id'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array();
		},
	)
);

flx_gql_mutation(
	'flxPosAccountTest',
	array(
		'description' => __( 'Asks the provider whether the saved keys work (no money moves). Providers without a test say so.', 'flx-pos' ),
		'cap'         => static fn(): bool => flxpos_gql_cap(),
		'input'       => array(
			'id' => array(
				'type'        => 'String!',
				'description' => __( 'Account id.', 'flx-pos' ),
			),
		),
		'output'      => array(
			'result' => array(
				'type'        => 'FlxPosTestResult',
				'description' => __( 'Result of the test.', 'flx-pos' ),
			),
		),
		'resolve'     => static fn( array $input ): array => array( 'result' => FLXPOS_Screen_Service::test_account( flx_gql_str( $input['id'] ?? '' ) ) ),
	)
);

flx_gql_input(
	'FlxPosRouteInput',
	array(
		'description' => __( 'Account that takes card payments in one currency.', 'flx-pos' ),
		'fields'      => array(
			'currency' => array(
				'type'        => 'String!',
				'description' => __( 'Currency code.', 'flx-pos' ),
			),
			'account'  => array(
				'type'        => 'String!',
				'description' => __( 'Account id; empty uses the default.', 'flx-pos' ),
			),
		),
	)
);

flx_gql_mutation(
	'flxPosSettingsUpdate',
	array(
		'description' => __( 'Changes the general settings. Fields left out stay as they are.', 'flx-pos' ),
		'cap'         => static fn(): bool => flxpos_gql_cap(),
		'input'       => array(
			'cardTitle'     => array(
				'type'        => 'String',
				'description' => __( 'Title of the card method at checkout.', 'flx-pos' ),
			),
			'cardDesc'      => array(
				'type'        => 'String',
				'description' => __( 'Description of the card method.', 'flx-pos' ),
			),
			'cardAccount'   => array(
				'type'        => 'String',
				'description' => __( 'Account that takes card payments; empty uses the first fitting one.', 'flx-pos' ),
			),
			'routes'        => array(
				'type'        => '[FlxPosRouteInput!]',
				'description' => __( 'Account per currency; replaces the list.', 'flx-pos' ),
			),
			'display'       => array(
				'type'        => 'String',
				'description' => __( 'redirect, modal or inline.', 'flx-pos' ),
			),
			'showBrands'    => array(
				'type'        => 'Boolean',
				'description' => __( 'Card brand logos in the card form.', 'flx-pos' ),
			),
			'blockFallback' => array(
				'type'        => 'Boolean',
				'description' => __( 'Show redirect plugins without block support on the Checkout block.', 'flx-pos' ),
			),
			'saveLog'       => array(
				'type'        => 'Boolean',
				'description' => __( 'Keep provider answers in the transaction log.', 'flx-pos' ),
			),
		),
		'output'      => array(
			'settings' => array(
				'type'        => 'FlxPosSettings!',
				'description' => __( 'Settings after the change.', 'flx-pos' ),
				'resolve'     => static fn(): FLXPOS_Settings_DTO => FLXPOS_Screen_Service::settings(),
			),
		),
		'resolve'     => static function ( array $input ): array {
			$in = array();
			foreach ( array( 'cardTitle' => 'card_title', 'cardDesc' => 'card_desc', 'cardAccount' => 'card_account', 'display' => 'display' ) as $g => $k ) {
				if ( isset( $input[ $g ] ) ) {
					$in[ $k ] = flx_gql_str( $input[ $g ] );
				}
			}
			foreach ( array( 'showBrands' => 'show_brands', 'blockFallback' => 'block_fallback', 'saveLog' => 'save_log' ) as $g => $k ) {
				if ( isset( $input[ $g ] ) ) {
					$in[ $k ] = (bool) $input[ $g ];
				}
			}
			if ( isset( $input['routes'] ) ) {
				$routes = array();
				foreach ( flx_gql_arr( $input['routes'] ) as $r ) {
					$r        = flx_gql_arr( $r );
					$routes[] = array(
						'value' => flx_gql_str( $r['currency'] ?? '' ),
						'label' => flx_gql_str( $r['account'] ?? '' ),
					);
				}
				$in['routes'] = $routes;
			}
			FLXPOS_Screen_Service::update_settings( $in );
			return array();
		},
	)
);

flx_gql_mutation(
	'flxPosTxnRefund',
	array(
		'description' => __( 'Refunds a paid transaction at the provider (same day and full amount voids first). Leave the amount out to refund what is left.', 'flx-pos' ),
		'cap'         => static fn(): bool => flxpos_gql_cap(),
		'input'       => array(
			'id'     => array(
				'type'        => 'Int!',
				'description' => __( 'Transaction id.', 'flx-pos' ),
			),
			'amount' => array(
				'type'        => 'Float',
				'description' => __( 'Amount to refund.', 'flx-pos' ),
			),
		),
		'output'      => array(
			'txn' => array(
				'type'        => 'FlxPosTxn',
				'description' => __( 'The transaction after the refund.', 'flx-pos' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$amount = isset( $input['amount'] ) && is_numeric( $input['amount'] ) ? (float) $input['amount'] : null;
			$r      = FLXPOS_Screen_Service::refund( flx_gql_int( $input['id'] ?? 0 ), $amount );
			return $r instanceof WP_Error ? $r : array( 'txn' => $r );
		},
	)
);
