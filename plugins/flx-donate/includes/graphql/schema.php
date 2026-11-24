<?php
/**
 * Flux Donate public GraphQL (part a): donation types, add to cart, My donations, lookup, IBAN.
 * Loaded only on flx_gql_register. Resolvers call the services.
 *
 * Public fields (cap 'public'), and why:
 * - flxDonationTypes / flxDonationType: the donation page is anonymous; the REST routes are open.
 *   The type carries the title, description, amounts, image and options the page already shows.
 *   Archived types, form ids and type meta are not here.
 * - flxDonationCartAdd: the same page adds to the cart without an account. The cart is the
 *   visitor's own Store API session. Card data is not a field.
 * - flxDonationLookupStart / Verify / End: lookup is for visitors. Start returns the same
 *   answer whether or not a donation exists.
 * - flxDonationBankAccount: the donation page already shows this IBAN. The query calls
 *   flxb_bank_account(): the donation bank-transfer gateway when that class is loaded,
 *   otherwise WooCommerce direct bank transfer.
 *
 * flxMyDonations is not public: only the signed-in owner or a lookup session that proved
 * the address.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param mixed $v Value.
 */
function flxb_gql_str( $v ): string {
	return is_scalar( $v ) ? (string) $v : '';
}

/**
 * @param mixed $v Value.
 */
function flxb_gql_int( $v ): int {
	return is_numeric( $v ) ? (int) $v : 0;
}

/**
 * @param mixed $v Value.
 */
function flxb_gql_float( $v ): float {
	return is_numeric( $v ) ? (float) $v : 0.0;
}

/**
 * @param mixed $v Value.
 */
function flxb_gql_nullable_float( $v ): ?float {
	return is_numeric( $v ) ? (float) $v : null;
}

/**
 * @param mixed $v Value.
 */
function flxb_gql_nullable_str( $v ): ?string {
	$s = is_scalar( $v ) ? (string) $v : '';
	return '' !== $s ? $s : null;
}

/**
 * Connection and mutation arguments as a string-keyed map.
 *
 * @param array<mixed> $args Arguments.
 * @return array<string,mixed>
 */
function flxb_gql_args( array $args ): array {
	$out = array();
	foreach ( $args as $k => $v ) {
		if ( is_string( $k ) ) {
			$out[ $k ] = $v;
		}
	}
	return $out;
}

/**
 * @param mixed $row Public type row.
 * @return array<string,mixed>|null
 */
function flxb_gql_type_node( $row ): ?array {
	if ( ! is_array( $row ) || 'archived' === flxb_gql_str( $row['state'] ?? '' ) ) {
		return null;
	}
	$currency = flxb_gql_str( $row['currency'] ?? '' );
	$options  = array();
	foreach ( (array) ( $row['options'] ?? array() ) as $o ) {
		if ( ! is_array( $o ) ) {
			continue;
		}
		$prices = array();
		foreach ( (array) ( $o['prices'] ?? array() ) as $code => $amount ) {
			$code = strtoupper( flxb_gql_str( $code ) );
			if ( preg_match( '/^[A-Z]{3}$/', $code ) && is_numeric( $amount ) ) {
				$prices[] = flx_gql_money( $amount, $code );
			}
		}
		$price = is_numeric( $o['price'] ?? null ) ? flx_gql_money( $o['price'], $currency ) : null;
		$options[] = array(
			'key'       => flxb_gql_str( $o['key'] ?? '' ),
			'label'     => flxb_gql_str( $o['label'] ?? '' ),
			'price'     => $price,
			'prices'    => $prices,
			'unit'      => flxb_gql_nullable_str( $o['unit'] ?? null ),
			'unitLabel' => flxb_gql_nullable_str( $o['unit_label'] ?? null ),
			'unitsPer'  => max( 1, flxb_gql_int( $o['units_per'] ?? 1 ) ),
			'active'    => ! empty( $o['active'] ),
		);
	}
	$progress = null;
	if ( is_array( $row['progress'] ?? null ) ) {
		$p         = $row['progress'];
		$p_cur     = flxb_gql_str( $p['currency'] ?? $currency );
		$progress  = array(
			'raised'   => flx_gql_money( flxb_gql_float( $p['raised'] ?? 0 ), $p_cur ),
			'gifts'    => flxb_gql_int( $p['gifts'] ?? 0 ),
			'goal'     => flx_gql_money( flxb_gql_float( $p['goal'] ?? 0 ), $p_cur ),
			'percent'  => flxb_gql_float( $p['percent'] ?? 0 ),
			'currency' => $p_cur,
		);
	}
	$image = flxb_gql_str( $row['image_url'] ?? '' );
	$state = flxb_gql_str( $row['state'] ?? 'closed' );
	if ( ! in_array( $state, array( 'open', 'upcoming', 'ended', 'closed' ), true ) ) {
		$state = 'closed';
	}
	return array(
		'databaseId'   => flxb_gql_int( $row['id'] ?? 0 ),
		'slug'          => flxb_gql_str( $row['slug'] ?? '' ),
		'title'         => flxb_gql_str( $row['title'] ?? '' ),
		'description'   => flxb_gql_str( $row['description'] ?? '' ),
		'category'      => flxb_gql_str( $row['category'] ?? '' ),
		'categoryLabel' => flxb_gql_str( $row['category_label'] ?? '' ),
		'imageUrl'      => '' !== $image ? $image : null,
		'currency'      => flxb_gql_str( $row['currency'] ?? '' ),
		'state'         => $state,
		'stateMessage'  => flxb_gql_str( $row['state_message'] ?? '' ),
		'options'       => $options,
		'allowFree'     => ! empty( $row['allow_free'] ),
		'minAmount'     => flx_gql_money( flxb_gql_float( $row['min_amount'] ?? 0 ), $currency ),
		'allowQuantity' => ! empty( $row['allow_quantity'] ),
		'requiresProxy' => ! empty( $row['requires_proxy'] ),
		'progress'      => $progress,
	);
}

/**
 * @param array<string,mixed> $gift gift_out() row.
 * @return array<string,mixed>
 */
function flxb_gql_my_node( array $gift ): array {
	$amount   = flxb_gql_float( $gift['amount'] ?? 0 );
	$currency = flxb_gql_str( $gift['currency'] ?? '' );
	$stored   = flxb_gql_str( $gift['created_at'] ?? '' );
	return array(
		'databaseId' => flxb_gql_int( $gift['id'] ?? 0 ),
		'title'       => flxb_gql_str( $gift['title'] ?? '' ),
		'status'      => flxb_gql_str( $gift['status'] ?? 'pending' ),
		'date'        => flx_gql_datetime( $stored ),
		'amount'      => flx_gql_money( $amount, $currency ),
	);
}

flx_gql_enum(
	'FlxDonationTypeState',
	array(
		'description' => __( 'Whether a donation type can be given today.', 'flx-donate' ),
		'values'      => array(
			'OPEN'     => array( 'value' => 'open' ),
			'UPCOMING' => array( 'value' => 'upcoming' ),
			'ENDED'    => array( 'value' => 'ended' ),
			'CLOSED'   => array( 'value' => 'closed' ),
		),
	)
);

flx_gql_enum(
	'FlxDonationStatus',
	array(
		'description' => __( 'Payment status of one donation.', 'flx-donate' ),
		'values'      => array(
			'COMPLETED'          => array( 'value' => 'completed' ),
			'PENDING'            => array( 'value' => 'pending' ),
			'FAILED'             => array( 'value' => 'failed' ),
			'CANCELED'           => array( 'value' => 'canceled' ),
			'REFUNDED'           => array( 'value' => 'refunded' ),
			'PARTIALLY_REFUNDED' => array( 'value' => 'partially_refunded' ),
		),
	)
);

flx_gql_enum(
	'FlxDonationTypeList',
	array(
		'description' => __( 'Which public types to list.', 'flx-donate' ),
		'values'      => array(
			'VISIBLE' => array( 'value' => 'visible' ),
			'OPEN'    => array( 'value' => 'open' ),
		),
	)
);

flx_gql_type(
	'FlxDonationTypeOption',
	array(
		'description' => __( 'One choice on a donation type (a sapling package, a share).', 'flx-donate' ),
		'fields'      => array(
			'key'       => array(
				'type'        => 'String!',
				'description' => __( 'Choice key. flxDonationCartAdd sends it as option.', 'flx-donate' ),
			),
			'label'     => array(
				'type'        => 'String!',
				'description' => __( 'Label shown on the donation page.', 'flx-donate' ),
			),
			'price'     => array(
				'type'        => 'FlxMoney',
				'description' => __( 'Price in the type currency. Null when the price is not announced.', 'flx-donate' ),
			),
			'prices'    => array(
				'type'        => '[FlxMoney!]!',
				'description' => __( 'Prices in other currencies. Empty when the option has only its own currency.', 'flx-donate' ),
			),
			'unit'      => array(
				'type'        => 'String',
				'description' => __( 'Unit key (for example fidan). Null when the option has no unit.', 'flx-donate' ),
			),
			'unitLabel' => array(
				'type'        => 'String',
				'description' => __( 'Unit label for one item. Null when there is no unit.', 'flx-donate' ),
			),
			'unitsPer'  => array(
				'type'        => 'Int!',
				'description' => __( 'How many units one choice stands for.', 'flx-donate' ),
			),
			'active'    => array(
				'type'        => 'Boolean!',
				'description' => __( 'Whether the choice can be added from the page.', 'flx-donate' ),
			),
		),
	)
);

flx_gql_type(
	'FlxDonationTypeProgress',
	array(
		'description' => __( 'How far a type with a goal has come. Null on the type when there is no goal.', 'flx-donate' ),
		'fields'      => array(
			'raised'   => array(
				'type'        => 'FlxMoney!',
				'description' => __( 'Amount raised in the type currency.', 'flx-donate' ),
			),
			'gifts'    => array(
				'type'        => 'Int!',
				'description' => __( 'Number of paid donations.', 'flx-donate' ),
			),
			'goal'     => array(
				'type'        => 'FlxMoney!',
				'description' => __( 'Goal amount.', 'flx-donate' ),
			),
			'percent'  => array(
				'type'        => 'Float!',
				'description' => __( 'Percent of the goal, at most 100.', 'flx-donate' ),
			),
			'currency' => array(
				'type'        => 'String!',
				'description' => __( 'Type currency.', 'flx-donate' ),
			),
		),
	)
);

flx_gql_type(
	'FlxDonationType',
	array(
		'description' => __( 'A donation type the public page can show.', 'flx-donate' ),
		'fields'      => array(
			'databaseId'   => array(
				'type'        => 'Int!',
				'description' => __( 'Type id. The donation page already publishes it; flxDonationCartAdd takes it as typeId.', 'flx-donate' ),
			),
			'slug'          => array(
				'type'        => 'String!',
				'description' => __( 'URL slug.', 'flx-donate' ),
			),
			'title'         => array(
				'type'        => 'String!',
				'description' => __( 'Title.', 'flx-donate' ),
			),
			'description'   => array(
				'type'        => 'String!',
				'description' => __( 'Description.', 'flx-donate' ),
			),
			'category'      => array(
				'type'        => 'String!',
				'description' => __( 'Category slug. Empty when the type has none.', 'flx-donate' ),
			),
			'categoryLabel' => array(
				'type'        => 'String!',
				'description' => __( 'Category label.', 'flx-donate' ),
			),
			'imageUrl'      => array(
				'type'        => 'String',
				'description' => __( 'Large image. Null when the type has no image.', 'flx-donate' ),
			),
			'currency'      => array(
				'type'        => 'String!',
				'description' => __( 'Type currency.', 'flx-donate' ),
			),
			'state'         => array(
				'type'        => 'FlxDonationTypeState!',
				'description' => __( 'Whether donations are open.', 'flx-donate' ),
			),
			'stateMessage'  => array(
				'type'        => 'String!',
				'description' => __( 'Why a type is not open. Empty when it is open.', 'flx-donate' ),
			),
			'options'       => array(
				'type'        => '[FlxDonationTypeOption!]!',
				'description' => __( 'Choices.', 'flx-donate' ),
			),
			'allowFree'     => array(
				'type'        => 'Boolean!',
				'description' => __( 'Whether the donor may type an amount.', 'flx-donate' ),
			),
			'minAmount'     => array(
				'type'        => 'FlxMoney!',
				'description' => __( 'Lowest free amount, in the type currency. The amount is 0 when there is no minimum.', 'flx-donate' ),
			),
			'allowQuantity' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Whether the donor may choose how many.', 'flx-donate' ),
			),
			'requiresProxy' => array(
				'type'        => 'Boolean!',
				'description' => __( 'Whether each share needs a name and proxy consent.', 'flx-donate' ),
			),
			'progress'      => array(
				'type'        => 'FlxDonationTypeProgress',
				'description' => __( 'Goal progress. Null when the type has no goal.', 'flx-donate' ),
			),
		),
	)
);

flx_gql_connection( 'FlxDonationType' );

flx_gql_type(
	'FlxDonationCartLine',
	array(
		'description' => __( 'The donation stored on one cart line. Several names are still one line and one payment.', 'flx-donate' ),
		'fields'      => array(
			'option' => array(
				'type'        => 'String!',
				'description' => __( 'Choice key. Empty for a free amount.', 'flx-donate' ),
			),
			'units'  => array(
				'type'        => 'Int!',
				'description' => __( 'How many. A free amount is 1.', 'flx-donate' ),
			),
			'names'  => array(
				'type'        => '[String!]!',
				'description' => __( 'Certificate names. Empty when the donation is not in someone else\'s name. One certificate is made per name.', 'flx-donate' ),
			),
			'amount' => array(
				'type'        => 'FlxMoney',
				'description' => __( 'Free amount, in the line currency. Null when the price comes from the option.', 'flx-donate' ),
			),
		),
	)
);

flx_gql_field(
	'FlxCartItem',
	'flxDonation',
	array(
		'type'        => 'FlxDonationCartLine',
		'description' => __( 'Set when this cart line is a donation. Null for an ordinary product.', 'flx-donate' ),
		'resolve'     => static function ( $item ): ?array {
			$key  = is_array( $item ) ? flxb_gql_str( $item['key'] ?? '' ) : '';
			$line = '' !== $key ? FLXB_Cart_Service::line_of( $key ) : null;
			if ( ! is_array( $line ) ) {
				return null;
			}
			$amount = null !== $line['amount'] ? flx_gql_money( $line['amount'], $line['currency'] ) : null;
			return array(
				'option' => $line['option'],
				'units'  => $line['units'],
				'names'  => FLXB_Cart_Service::names_of( $line ),
				'amount' => $amount,
			);
		},
	)
);

flx_gql_type(
	'FlxMyDonation',
	array(
		'description' => __( 'One donation of the signed-in donor, or of the lookup session.', 'flx-donate' ),
		'fields'      => array(
			'databaseId' => array(
				'type'        => 'Int!',
				'description' => __( 'Donation id.', 'flx-donate' ),
			),
			'title'       => array(
				'type'        => 'String!',
				'description' => __( 'What was given.', 'flx-donate' ),
			),
			'status'      => array(
				'type'        => 'FlxDonationStatus!',
				'description' => __( 'Payment status.', 'flx-donate' ),
			),
			'date'        => array(
				'type'        => 'FlxDateTime',
				'description' => __( 'When it was recorded. The stored value is UTC.', 'flx-donate' ),
			),
			'amount'      => array(
				'type'        => 'FlxMoney!',
				'description' => __( 'Amount.', 'flx-donate' ),
			),
		),
	)
);

flx_gql_connection( 'FlxMyDonation' );

flx_gql_type(
	'FlxDonationBankAccount',
	array(
		'description' => __( 'The account flxb_bank_account() returns: the donation bank-transfer gateway when that class is loaded, otherwise WooCommerce direct bank transfer.', 'flx-donate' ),
		'fields'      => array(
			'holder'      => array(
				'type'        => 'String!',
				'description' => __( 'Account holder.', 'flx-donate' ),
			),
			'iban'        => array(
				'type'        => 'String!',
				'description' => __( 'IBAN, without spaces.', 'flx-donate' ),
			),
			'ibanDisplay' => array(
				'type'        => 'String!',
				'description' => __( 'IBAN grouped in fours.', 'flx-donate' ),
			),
			'bank'        => array(
				'type'        => 'String!',
				'description' => __( 'Bank name. Empty when it is not set.', 'flx-donate' ),
			),
		),
	)
);

flx_gql_query(
	'flxDonationTypes',
	array(
		'type'        => 'FlxDonationTypeConnection!',
		'description' => __( 'Donation types the public page lists. Archived types are left out.', 'flx-donate' ),
		'cap'         => 'public',
		'args'        => flx_gql_connection_args(
			array(
				'status'   => array(
					'type'        => 'FlxDonationTypeList',
					'description' => __( 'VISIBLE (default) or only types that are open today.', 'flx-donate' ),
				),
				'category' => array(
					'type'        => 'String',
					'description' => __( 'Only this category. Empty lists every category.', 'flx-donate' ),
				),
			)
		),
		'resolve'     => static function ( $root, array $args ): array {
			unset( $root );
			$args = flxb_gql_args( $args );
			flx_gql_cache_tag( FLXB_Type_Public_Service::TAG );
			$status   = flxb_gql_str( $args['status'] ?? 'visible' );
			$category = flxb_gql_str( $args['category'] ?? '' );
			return flx_gql_paginate(
				$args,
				static function ( int $offset, int $limit ) use ( $status, $category ): array {
					$page  = FLXB_Type_Public_Service::page( $status, $category, $offset, $limit );
					$nodes = array();
					foreach ( $page['items'] as $row ) {
						$node = flxb_gql_type_node( $row );
						if ( null !== $node ) {
							$nodes[] = $node;
						}
					}
					return array(
						'items' => $nodes,
						'total' => $page['total'],
					);
				}
			);
		},
	)
);

flx_gql_query(
	'flxDonationType',
	array(
		'type'        => 'FlxDonationType',
		'description' => __( 'One public donation type, by id or slug. Null when it is missing or archived.', 'flx-donate' ),
		'cap'         => 'public',
		'args'        => array(
			'id'   => array(
				'type'        => 'Int',
				'description' => __( 'Type id.', 'flx-donate' ),
			),
			'slug' => array(
				'type'        => 'String',
				'description' => __( 'Type slug. Used when id is empty.', 'flx-donate' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): ?array {
			unset( $root );
			$args = flxb_gql_args( $args );
			flx_gql_cache_tag( FLXB_Type_Public_Service::TAG );
			$id  = flxb_gql_int( $args['id'] ?? 0 );
			$ref = $id > 0 ? (string) $id : flxb_gql_str( $args['slug'] ?? '' );
			if ( '' === $ref ) {
				return null;
			}
			$row = FLXB_Type_Public_Service::one( $ref );
			return $row instanceof WP_Error ? null : flxb_gql_type_node( $row );
		},
	)
);

flx_gql_query(
	'flxDonationBankAccount',
	array(
		'type'        => 'FlxDonationBankAccount',
		'description' => __( 'Bank account shown on the donation page. Null when no IBAN is set.', 'flx-donate' ),
		'cap'         => 'public',
		'resolve'     => static function (): ?array {
			$a = FLXB_Type_Public_Service::bank();
			if ( null === $a ) {
				return null;
			}
			return array(
				'holder'      => $a['holder'],
				'iban'        => $a['iban'],
				'ibanDisplay' => $a['iban_display'],
				'bank'        => $a['bank'],
			);
		},
	)
);

flx_gql_query(
	'flxMyDonations',
	array(
		'type'        => 'FlxMyDonationConnection!',
		'description' => __( 'Donations of the signed-in donor, or of the lookup session. Nobody else\'s.', 'flx-donate' ),
		'cap'         => static function (): bool {
			// Personal answer, denial included: decided before the kit's cap check can throw, so it is never stored.
			flx_gql_no_store();
			return FLXB_My_Donations_Service::can_see();
		},
		'args'        => flx_gql_connection_args(),
		'resolve'     => static function ( $root, array $args ): array {
			unset( $root );
			flx_gql_no_store();
			$all  = FLXB_My_Donations_Service::visible();
			$args = flxb_gql_args( $args );
			return flx_gql_paginate(
				$args,
				static function ( int $offset, int $limit ) use ( $all ): array {
					$slice = array();
					foreach ( array_slice( $all, $offset, $limit ) as $gift ) {
						$slice[] = flxb_gql_my_node( $gift );
					}
					return array(
						'items' => $slice,
						'total' => count( $all ),
					);
				}
			);
		},
	)
);

flx_gql_input(
	'FlxDonationContactInput',
	array(
		'description' => __( 'Where one certificate is delivered: a WhatsApp number, an e-mail address, or both.', 'flx-donate' ),
		'fields'      => array(
			'phone' => array(
				'type'        => 'String',
				'description' => __( 'WhatsApp number.', 'flx-donate' ),
			),
			'email' => array(
				'type'        => 'String',
				'description' => __( 'E-mail address.', 'flx-donate' ),
			),
		),
	)
);

flx_gql_mutation(
	'flxDonationCartAdd',
	array(
		'description' => __( 'Add a donation to the visitor\'s cart. Checkout stays on the Store API.', 'flx-donate' ),
		'cap'         => 'public',
		'input'       => array(
			'typeId'       => array(
				'type'        => 'Int!',
				'description' => __( 'Donation type id.', 'flx-donate' ),
			),
			'option'       => array(
				'type'        => 'String',
				'description' => __( 'Choice key. Empty for a free amount.', 'flx-donate' ),
			),
			'units'        => array(
				'type'        => 'Int',
				'description' => __( 'How many. Defaults to the number of names, or 1.', 'flx-donate' ),
			),
			'amount'       => array(
				'type'        => 'String',
				'description' => __( 'Free amount. Ignored when an option is set.', 'flx-donate' ),
			),
			'currency'     => array(
				'type'        => 'String',
				'description' => __( 'Currency to switch to before the line is built. Empty keeps the current one.', 'flx-donate' ),
			),
			'names'        => array(
				'type'        => '[String!]',
				'description' => __( 'Certificate names. Several names are one cart line, one payment and one certificate each.', 'flx-donate' ),
			),
			'contacts'     => array(
				'type'        => '[FlxDonationContactInput!]',
				'description' => __( 'Delivery contact of each name, in the same order. A certificate needs a WhatsApp number or an e-mail address (at least one), as on the donation form. Not used for a proxy type.', 'flx-donate' ),
			),
			'feeCover'     => array(
				'type'        => 'Boolean',
				'description' => __( 'The donor covers the transaction fee, as the checkbox on the donation form.', 'flx-donate' ),
			),
			'proxyConsent' => array(
				'type'        => 'Boolean',
				'description' => __( 'The donor accepts the current proxy text. Required for a type that needs it.', 'flx-donate' ),
			),
		),
		'output'       => array(
			'cart'    => array(
				'type'        => 'FlxCart',
				'description' => __( 'The cart after the line was added. Null when the input was refused.', 'flx-donate' ),
			),
			'itemKey' => array(
				'type'        => 'String',
				'description' => __( 'Key of the new line. Null when the input was refused.', 'flx-donate' ),
			),
		),
		'resolve'      => static function ( array $input ): array {
			$added = FLXB_Cart_Service::add( flxb_gql_args( $input ) );
			if ( $added instanceof WP_Error ) {
				return array(
					'cart'    => null,
					'itemKey' => null,
					'errors'  => $added,
				);
			}
			return array(
				'cart'    => $added['cart'],
				'itemKey' => '' !== $added['itemKey'] ? $added['itemKey'] : null,
			);
		},
	)
);

flx_gql_mutation(
	'flxDonationLookupStart',
	array(
		'description' => __( 'Send a code to view donations. The answer does not say whether a donation exists.', 'flx-donate' ),
		'cap'         => 'public',
		'input'       => array(
			'login' => array(
				'type'        => 'String!',
				'description' => __( 'E-mail address or phone number.', 'flx-donate' ),
			),
		),
		'output'       => array(
			'ok'      => array(
				'type'        => 'Boolean!',
				'description' => __( 'The request was accepted.', 'flx-donate' ),
			),
			'via'     => array(
				'type'        => 'String!',
				'description' => __( 'Where a code would be sent: email, whatsapp or sms.', 'flx-donate' ),
			),
			'message' => array(
				'type'        => 'String!',
				'description' => __( 'Text for the donor. It does not confirm that a donation exists.', 'flx-donate' ),
			),
		),
		'resolve'      => static function ( array $input ) {
			flx_gql_no_store();
			$input = flxb_gql_args( $input );
			$out   = FLXB_Lookup_Service::start( flxb_gql_str( $input['login'] ?? '' ) );
			if ( $out instanceof WP_Error ) {
				$data   = $out->get_error_data();
				$status = is_array( $data ) ? flxb_gql_int( $data['status'] ?? 400 ) : 400;
				if ( 400 !== $status ) {
					return $out;
				}
				return array(
					'ok'      => false,
					'via'     => '',
					'message' => '',
					'errors'  => $out,
				);
			}
			return array(
				'ok'      => true,
				'via'     => (string) $out['via'],
				'message' => (string) $out['message'],
			);
		},
	)
);

flx_gql_mutation(
	'flxDonationLookupVerify',
	array(
		'description' => __( 'Open the lookup session with the code. A wrong code and an unknown address fail the same way.', 'flx-donate' ),
		'cap'         => 'public',
		'input'       => array(
			'login' => array(
				'type'        => 'String!',
				'description' => __( 'The same e-mail or phone used to start.', 'flx-donate' ),
			),
			'code'  => array(
				'type'        => 'String!',
				'description' => __( 'Six-digit code.', 'flx-donate' ),
			),
		),
		'output'       => array(
			'ok' => array(
				'type'        => 'Boolean!',
				'description' => __( 'The session is open. Read the donations with flxMyDonations.', 'flx-donate' ),
			),
		),
		'resolve'      => static function ( array $input ) {
			flx_gql_no_store();
			$input = flxb_gql_args( $input );
			$out   = FLXB_Lookup_Service::verify( flxb_gql_str( $input['login'] ?? '' ), flxb_gql_str( $input['code'] ?? '' ) );
			if ( $out instanceof WP_Error ) {
				$data   = $out->get_error_data();
				$status = is_array( $data ) ? flxb_gql_int( $data['status'] ?? 400 ) : 400;
				if ( 400 !== $status ) {
					return $out;
				}
				return array(
					'ok'     => false,
					'errors' => $out,
				);
			}
			return array( 'ok' => ! empty( $out['ok'] ) );
		},
	)
);

flx_gql_mutation(
	'flxDonationLookupEnd',
	array(
		'description' => __( 'End the lookup session.', 'flx-donate' ),
		'cap'         => 'public',
		'input'       => array(),
		'output'       => array(
			'ok' => array(
				'type'        => 'Boolean!',
				'description' => __( 'The session was cleared.', 'flx-donate' ),
			),
		),
		'resolve'      => static function (): array {
			flx_gql_no_store();
			$out = FLXB_Lookup_Service::end();
			return array( 'ok' => ! empty( $out['ok'] ) );
		},
	)
);
