<?php
/**
 * Shared scalars and types every package uses.
 *
 *   FlxJSON          free-form JSON (settings objects, view configs). Prefer real types; use only for open maps.
 *   FlxDateTime      { iso, timestamp, label, relative }   label = flx_date(), relative = flx_time_ago()
 *   FlxMoney         { amount, currency, formatted }
 *   FlxPhone         { e164, digits, display }             flx_phone_* normalisation
 *   FlxFile          { id, name, mime, size, url, isImage, thumb }
 *   FlxPerson        interface (owned by Flux CRM): { id, databaseId, name, email, phone, avatar }
 *   FlxUserError     { code, message, field }              mutation payload errors
 *
 * Value builders: flx_gql_datetime(), flx_gql_money(), flx_gql_phone(), flx_gql_file().
 *
 * @package FLX_GraphQL
 */

defined( 'ABSPATH' ) || exit;

add_action( 'flx_gql_register_core', 'flx_gql_register_shared_types', 5 );

/**
 * Registers the shared types.
 */
function flx_gql_register_shared_types(): void {
	register_graphql_scalar(
		'FlxJSON',
		array(
			'description'  => __( 'Free-form JSON value (object, list, string, number or boolean).', 'flx-graphql' ),
			'serialize'    => static fn( $v ) => $v,
			'parseValue'   => static fn( $v ) => $v,
			'parseLiteral' => static function ( \GraphQL\Language\AST\Node $ast, ?array $variables = null ) {
				return \GraphQL\Utils\AST::valueFromASTUntyped( $ast, null === $variables ? null : flx_gql_arr( $variables ) );
			},
		)
	);

	flx_gql_type(
		'FlxUserError',
		array(
			'description' => __( 'A problem with the input of a mutation, shown next to the form field.', 'flx-graphql' ),
			'fields'      => array(
				'code'    => array(
					'type'        => 'String!',
					'description' => __( 'Stable code: INVALID_INPUT, CONFLICT, …', 'flx-graphql' ),
				),
				'message' => array(
					'type'        => 'String!',
					'description' => __( 'Message in the user\'s language.', 'flx-graphql' ),
				),
				'field'   => array(
					'type'        => 'String',
					'description' => __( 'Input field (dotted path) the problem belongs to.', 'flx-graphql' ),
				),
			),
		)
	);

	flx_gql_type(
		'FlxDateTime',
		array(
			'description' => __( 'A point in time with labels in the user\'s language.', 'flx-graphql' ),
			'fields'      => array(
				'iso'       => array(
					'type'        => 'String!',
					'description' => __( 'ISO 8601 in UTC (2026-11-15T14:59:00Z).', 'flx-graphql' ),
				),
				'timestamp' => array(
					'type'        => 'Int!',
					'description' => __( 'Unix timestamp.', 'flx-graphql' ),
				),
				'label'     => array(
					'type'        => 'String!',
					'description' => __( 'Date and time for display, in the site time zone and the user\'s language.', 'flx-graphql' ),
				),
				'relative'  => array(
					'type'        => 'String!',
					'description' => __( 'Relative time for display ("5 minutes ago").', 'flx-graphql' ),
				),
				'day'       => array(
					'type'        => 'String!',
					'description' => __( 'Date only, in the site time zone and the user\'s language.', 'flx-graphql' ),
				),
				'day'       => function_exists( 'flx_date' ) ? flx_gql_str( flx_date( $ts, 'date' ) ) : gmdate( 'Y-m-d', $ts ),
		'short'     => array(
					'type'        => 'String!',
					'description' => __( 'Compact date and time for tables (16.10.2026 14:24 in Turkish).', 'flx-graphql' ),
				),
			),
		)
	);

	flx_gql_type(
		'FlxMoney',
		array(
			'description' => __( 'An amount of money.', 'flx-graphql' ),
			'fields'      => array(
				'amount'    => array(
					'type'        => 'Float!',
					'description' => __( 'Amount in the currency unit (150.5).', 'flx-graphql' ),
				),
				'currency'  => array(
					'type'        => 'String!',
					'description' => __( 'ISO 4217 code (TRY, USD).', 'flx-graphql' ),
				),
				'formatted' => array(
					'type'        => 'String!',
					'description' => __( 'Amount with the currency sign for display.', 'flx-graphql' ),
				),
			),
		)
	);

	flx_gql_type(
		'FlxPhone',
		array(
			'description' => __( 'A phone number.', 'flx-graphql' ),
			'fields'      => array(
				'e164'    => array(
					'type'        => 'String!',
					'description' => __( 'E.164 form (+905301234567); empty when the number is not valid.', 'flx-graphql' ),
				),
				'digits'  => array(
					'type'        => 'String!',
					'description' => __( 'Digits only (905301234567).', 'flx-graphql' ),
				),
				'display' => array(
					'type'        => 'String!',
					'description' => __( 'Number as entered, for display.', 'flx-graphql' ),
				),
			),
		)
	);

	flx_gql_type(
		'FlxFile',
		array(
			'description' => __( 'A stored file.', 'flx-graphql' ),
			'fields'      => array(
				'id'      => array(
					'type'        => 'ID!',
					'description' => __( 'File ID (upload ID or the package\'s own ID).', 'flx-graphql' ),
				),
				'name'    => array(
					'type'        => 'String!',
					'description' => __( 'File name.', 'flx-graphql' ),
				),
				'mime'    => array(
					'type'        => 'String!',
					'description' => __( 'MIME type.', 'flx-graphql' ),
				),
				'size'    => array(
					'type'        => 'Int!',
					'description' => __( 'Size in bytes.', 'flx-graphql' ),
				),
				'url'     => array(
					'type'        => 'String',
					'description' => __( 'Download address (may need the same sign-in).', 'flx-graphql' ),
				),
				'isImage' => array(
					'type'        => 'Boolean!',
					'description' => __( 'The file is an image.', 'flx-graphql' ),
				),
				'thumb'   => array(
					'type'        => 'String',
					'description' => __( 'Thumbnail address for images.', 'flx-graphql' ),
				),
			),
		)
	);

	flx_gql_interface(
		'FlxPerson',
		array(
			'description' => __( 'A person (donor, customer, contact). Flux CRM owns the record; other packages return it.', 'flx-graphql' ),
			'fields'      => array(
				'id'         => array(
					'type'        => 'ID!',
					'description' => __( 'Global ID.', 'flx-graphql' ),
				),
				'databaseId' => array(
					'type'        => 'Int!',
					'description' => __( 'Person ID in the shared people table.', 'flx-graphql' ),
				),
				'name'       => array(
					'type'        => 'String!',
					'description' => __( 'Full name.', 'flx-graphql' ),
				),
				'email'      => array(
					'type'        => 'String',
					'description' => __( 'E-mail address.', 'flx-graphql' ),
				),
				'phone'      => array(
					'type'        => 'FlxPhone',
					'description' => __( 'Phone number.', 'flx-graphql' ),
				),
				'avatar'     => array(
					'type'        => 'String',
					'description' => __( 'Picture address.', 'flx-graphql' ),
				),
			),
			'resolveType' => static function ( $value ) {
				$type = is_array( $value ) ? flx_gql_str( $value['__typename'] ?? '' ) : ( $value instanceof FLXGQL_Node ? $value->gql_type() : '' );
				$type = '' !== $type ? $type : null;
				/**
				 * Concrete FlxPerson type of a value (Flux CRM registers FlxCrmPerson).
				 *
				 * @param string|null $type  Type name.
				 * @param mixed       $value Resolved value.
				 */
				return apply_filters( 'flx_gql_person_type', $type, $value );
			},
		)
	);
}

/**
 * FlxDateTime value. Accepts a Unix timestamp, DateTimeInterface or a GMT "Y-m-d H:i:s" string.
 *
 * @param mixed $time Time.
 * @return array{iso:string,timestamp:int,label:string,relative:string,day:string,short:string}|null Null for an empty time.
 */
function flx_gql_datetime( $time ): ?array {
	if ( null === $time || '' === $time || '0000-00-00 00:00:00' === $time ) {
		return null;
	}
	if ( $time instanceof DateTimeInterface ) {
		$ts = $time->getTimestamp();
	} elseif ( is_numeric( $time ) ) {
		$ts = (int) $time;
	} else {
		$ts = strtotime( flx_gql_str( $time ) . ' UTC' );
		if ( false === $ts ) {
			return null;
		}
	}
	return array(
		'iso'       => gmdate( 'Y-m-d\TH:i:s\Z', $ts ),
		'timestamp' => $ts,
		'label'     => function_exists( 'flx_date' ) ? flx_gql_str( flx_date( $ts, 'datetime' ) ) : gmdate( 'Y-m-d H:i', $ts ),
		'relative'  => function_exists( 'flx_time_ago' ) ? flx_gql_str( flx_time_ago( $ts ) ) : gmdate( 'Y-m-d H:i', $ts ),
		'short'     => function_exists( 'flx_date' ) ? flx_gql_str( flx_date( $ts, ( function_exists( 'flx_lang' ) && 'tr' === flx_lang() ) ? 'd.m.Y H:i' : 'datetime' ) ) : gmdate( 'Y-m-d H:i', $ts ),
	);
}

/**
 * FlxMoney value.
 *
 * @param float|int|string $amount   Amount.
 * @param string           $currency ISO 4217 code; empty = store currency.
 * @return array{amount:float,currency:string,formatted:string}
 */
function flx_gql_money( $amount, string $currency = '' ): array {
	$amount   = (float) $amount;
	$currency = '' !== $currency ? strtoupper( $currency ) : ( function_exists( 'get_woocommerce_currency' ) ? flx_gql_str( get_woocommerce_currency(), 'TRY' ) : 'TRY' );
	if ( function_exists( 'wc_price' ) ) {
		$formatted = html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'currency' => $currency ) ) ), ENT_QUOTES, 'UTF-8' );
	} else {
		$formatted = number_format_i18n( $amount, 2 ) . ' ' . $currency;
	}
	return array(
		'amount'    => $amount,
		'currency'  => $currency,
		'formatted' => $formatted,
	);
}

/**
 * FlxPhone value.
 *
 * @param string $raw     Number as stored.
 * @param string $country Default ISO2 country.
 * @return array{e164:string,digits:string,display:string}|null Null for an empty number.
 */
function flx_gql_phone( string $raw, string $country = '' ): ?array {
	if ( '' === trim( $raw ) ) {
		return null;
	}
	$e164 = function_exists( 'flx_phone_normalize' ) ? flx_gql_str( flx_phone_normalize( $raw, $country ) ) : '';
	return array(
		'e164'    => $e164,
		'digits'  => '' !== $e164 ? ltrim( $e164, '+' ) : (string) preg_replace( '/\D+/', '', $raw ),
		'display' => $raw,
	);
}

/**
 * FlxFile value.
 *
 * @param array<string,mixed> $f id, name, mime, size, url?, thumb?.
 * @return array<string,mixed>
 */
function flx_gql_file( array $f ): array {
	$mime = flx_gql_str( $f['mime'] ?? '', 'application/octet-stream' );
	return array(
		'id'      => flx_gql_str( $f['id'] ?? '' ),
		'name'    => flx_gql_str( $f['name'] ?? '' ),
		'mime'    => '' !== $mime ? $mime : 'application/octet-stream',
		'size'    => flx_gql_int( $f['size'] ?? 0 ),
		'url'     => isset( $f['url'] ) ? flx_gql_str( $f['url'] ) : null,
		'isImage' => str_starts_with( $mime, 'image/' ),
		'thumb'   => isset( $f['thumb'] ) ? flx_gql_str( $f['thumb'] ) : null,
	);
}
