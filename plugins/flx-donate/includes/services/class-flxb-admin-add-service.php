<?php
/**
 * Add donation for the admin screen (GraphQL part b5): the manager's "record a received gift" and the staff
 * donation request (payment link).
 *
 * The wp-admin screen (packages/modules/src/donate/Add.tsx) calls this class. Rights are the classic ones:
 * recording needs flxb_can_manage(); a request needs FLXB_Refs::can_create() and its referral is the caller.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

final class FLXB_Admin_Add_Service {

	/** 2.29.0: created_via of orders made on this page. */
	const VIA = 'flxb-manual';

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	private static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function s( $v ): string {
		return is_scalar( $v ) ? (string) $v : '';
	}

	/**
	 * @param mixed $v Value.
	 * @return array<string,mixed>
	 */
	private static function map( $v ): array {
		$out = array();
		foreach ( is_array( $v ) ? $v : array() as $k => $x ) {
			$out[ (string) $k ] = $x;
		}
		return $out;
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function i( $v ): int {
		return is_numeric( $v ) ? (int) $v : 0;
	}

	/**
	 * @param mixed $v Value.
	 */
	private static function f( $v ): float {
		return is_numeric( $v ) ? (float) $v : 0.0;
	}

	public static function can_record(): bool {
		return (bool) flxb_can_manage();
	}

	public static function can_request(): bool {
		return (bool) FLXB_Refs::can_create();
	}

	/** The screen opens as the staff request form (not the manager's form). */
	public static function staff(): bool {
		return (bool) FLXB_Refs::staff_create();
	}

	/**
	 * @return array<string,string>
	 */
	public static function methods(): array {
		return array(
			'bank_transfer' => __( 'Bank transfer', 'flx-donate' ),
			'cash'          => __( 'Cash', 'flx-donate' ),
			'other'         => __( 'Other', 'flx-donate' ),
		);
	}

	/**
	 * Bank accounts from `flxb_bank_accounts`: id => label (accepts strings or arrays with label/iban/name).
	 *
	 * @return array<string,string>
	 */
	private static function accounts(): array {
		$out = array();
		foreach ( self::arr( flxb_bank_accounts() ) as $k => $a ) {
			if ( is_array( $a ) ) {
				$parts = array( self::s( $a['label'] ?? ( $a['name'] ?? ( $a['bank'] ?? '' ) ) ), self::s( $a['currency'] ?? '' ), self::s( $a['iban'] ?? '' ) );
				$label = trim( implode( ' · ', array_filter( $parts, static fn( string $p ): bool => '' !== $p ) ) );
				$id    = self::s( $a['id'] ?? ( $a['iban'] ?? $k ) );
			} else {
				$label = self::s( $a );
				$id    = is_string( $k ) ? $k : self::s( $a );
			}
			if ( '' !== $label ) {
				$out[ sanitize_text_field( $id ) ] = sanitize_text_field( $label );
			}
		}
		return $out;
	}

	/**
	 * @param array<string|int,string> $map Value => label.
	 * @return list<array{value: string, label: string}>
	 */
	private static function choices( array $map ): array {
		$out = array();
		foreach ( $map as $v => $l ) {
			$out[] = array(
				'value' => (string) $v,
				'label' => (string) $l,
			);
		}
		return $out;
	}

	/**
	 * The types the form offers with their options. The request form offers only open types, their active priced
	 * options, and a free amount only for types without them.
	 *
	 * @return list<array<string,mixed>>
	 */
	private static function types( bool $request ): array {
		$units = self::arr( flxb_units() );
		$out   = array();
		foreach ( self::arr( FLXB_Types::all() ) as $t ) {
			$t = self::map( $t );
			if ( $request && 'open' !== flxb_type_state( $t ) ) {
				continue;
			}
			$opts = array();
			foreach ( self::arr( $t['options'] ?? null ) as $o ) {
				$o = self::map( $o );
				if ( $request && ( empty( $o['active'] ) || self::f( $o['price'] ?? 0 ) <= 0 ) ) {
					continue;
				}
				$u      = self::s( $o['unit'] ?? '' );
				$uw     = isset( $units[ $u ] ) ? self::arr( $units[ $u ] ) : array();
				$opts[] = array(
					'key'      => self::s( $o['key'] ?? '' ),
					'label'    => self::s( $o['label'] ?? '' ),
					'price'    => self::f( $o['price'] ?? 0 ),
					'per'      => max( 1, self::i( $o['units_per'] ?? 1 ) ),
					'unitOne'  => self::s( $uw[0] ?? '' ),
					'unitMany' => self::s( $uw[1] ?? '' ),
				);
			}
			$free = $request ? ! $opts : ( ! empty( $t['allow_free'] ) || ! $opts );
			if ( $request && ! $opts && empty( $t['allow_free'] ) ) {
				continue;
			}
			$out[] = array(
				'databaseId' => self::i( $t['id'] ?? 0 ),
				'title'      => self::s( $t['title'] ?? '' ),
				'currency'   => self::s( $t['currency'] ?? '' ),
				'options'    => $opts,
				'free'       => $free,
				'proxy'      => ! $request && FLXB_Types::requires_proxy( $t ),
				'cert'       => ! $request && flxb_wc_active() && class_exists( 'FLXB_WC_Cart' ) && FLXB_WC_Cart::type_has_certificate( $t ),
			);
		}
		return $out;
	}

	/**
	 * What the form needs; `$donor` prefills the donor (the donor card's "Add donation").
	 *
	 * @return array<string,mixed>
	 */
	public static function form( int $donor ): array {
		$request = self::staff();
		$cur     = array();
		foreach ( array_unique( array_merge( array_keys( self::arr( flxb_currencies() ) ), array( 'TRY', 'EUR', 'USD', 'GBP' ) ) ) as $c ) {
			$c         = self::s( $c );
			$cur[ $c ] = $c . ' (' . self::s( flxb_currency_symbol( $c ) ) . ')';
		}
		$pre = ! $request && $donor > 0 ? FLXB_Donors::get( $donor ) : null;
		$pre = is_array( $pre ) ? $pre : array();
		$px  = $request ? null : flxb_proxy_text();
		$own = current_user_can( FLXB_Refs::CAP_OWN );
		return array(
			'request'      => $request,
			'types'        => self::types( $request ),
			'methods'      => self::choices( self::methods() ),
			'accounts'     => $request ? array() : self::choices( self::accounts() ),
			'referrals'    => $request ? array() : self::choices( array_map( static fn( $l ): string => self::s( $l ), self::arr( FLXB_Refs::users() ) ) ),
			'currencies'   => self::choices( $cur ),
			'currency'     => self::s( flxb_default_currency() ),
			'proxyVersion' => is_array( $px ) ? self::i( $px['version'] ?? 0 ) : null,
			'now'          => self::s( wp_date( 'Y-m-d\TH:i' ) ),
			'donor'        => array(
				'name'         => self::s( $pre['name'] ?? '' ),
				'phone'        => self::s( $pre['phone'] ?? '' ),
				'email'        => self::s( $pre['email'] ?? '' ),
				'consentKvkk'  => ! empty( $pre['consent_kvkk'] ),
				'consentEmail' => ! empty( $pre['consent_email'] ),
				'consentSms'   => ! empty( $pre['consent_sms'] ),
			),
			'listUrl'      => ! $request || $own ? self::s( FLXB_Admin_Url::page( 'flxb-donations' ) ) : '',
			'listLabel'    => $request ? __( 'My referrals', 'flx-donate' ) : __( 'Donations', 'flx-donate' ),
		);
	}

	private static function phone( string $raw ): string {
		$raw = sanitize_text_field( $raw );
		if ( '' === $raw || ! function_exists( 'flx_phone_normalize' ) ) {
			return $raw;
		}
		$n = self::s( flx_phone_normalize( $raw ) );
		return '' !== $n ? $n : $raw;
	}

	/**
	 * People rows: [ { name, phone, email } ] with a name, in order.
	 *
	 * @param mixed $raw Rows.
	 * @return list<array{name: string, phone: string, email: string}>
	 */
	private static function people( $raw ): array {
		$out = array();
		foreach ( self::arr( $raw ) as $r ) {
			$r    = self::arr( $r );
			$name = mb_substr( trim( sanitize_text_field( self::s( $r['name'] ?? '' ) ) ), 0, 80 );
			if ( '' === $name ) {
				continue;
			}
			$out[] = array(
				'name'  => $name,
				'phone' => self::phone( self::s( $r['phone'] ?? '' ) ),
				'email' => self::s( sanitize_email( self::s( $r['email'] ?? '' ) ) ),
			);
		}
		return $out;
	}

	private static function invalid( string $msg ): WP_Error {
		return new WP_Error( 'flxb_add', $msg, array( 'graphql_code' => 'INVALID_INPUT' ) );
	}

	/**
	 * Records a gift received outside the payment provider. With a donation type and WooCommerce it is a
	 * WooCommerce order (as on the site and in the Excel import).
	 *
	 * @param array<string,mixed> $p type_id, option_key, quantity, unit_price (null = the option's), price_reason,
	 *                               amount, currency, beneficiaries, proxy_consent, donor{name,phone,email,
	 *                               consent_kvkk,consent_email,consent_sms}, for_other, people[], method, account,
	 *                               paid, date (Y-m-dTH:i), reference, ref_user_id, note.
	 * @return array{message: string, id: int, url: string}|WP_Error
	 */
	public static function record( array $p ) {
		$num      = static fn( $v ): float => round( (float) str_replace( ',', '.', self::s( $v ) ), 2 );
		$tid      = absint( self::s( $p['type_id'] ?? 0 ) );
		$type     = $tid ? FLXB_Types::get( $tid ) : null;
		$type     = is_array( $type ) ? self::map( $type ) : null;
		$key      = sanitize_key( self::s( $p['option_key'] ?? '' ) );
		$opt      = $type && '' !== $key ? FLXB_Types::option( $type, $key ) : null;
		$opt      = is_array( $opt ) ? self::map( $opt ) : null;
		$qty      = max( 1, min( 1000, absint( self::s( $p['quantity'] ?? 1 ) ) ) );
		$currency = strtoupper( sanitize_text_field( self::s( $p['currency'] ?? '' ) ) );
		$currency = preg_match( '/^[A-Z]{3}$/', $currency ) ? $currency : ( $type ? self::s( $type['currency'] ?? '' ) : self::s( flxb_default_currency() ) );
		$method   = sanitize_key( self::s( $p['method'] ?? '' ) );
		$method   = isset( self::methods()[ $method ] ) ? $method : 'other';
		$meta     = array(
			'created_by' => get_current_user_id(),
			'method'     => $method,
		);
		if ( $tid && ! $type ) {
			return self::invalid( __( 'Please choose an option of the selected donation type.', 'flx-donate' ) );
		}
		if ( '' !== $key && ! $opt ) {
			return self::invalid( __( 'Please choose an option of the selected donation type.', 'flx-donate' ) );
		}
		if ( $type && $opt ) {
			$currency = self::s( $type['currency'] ?? '' ); // option prices are in the type's currency
			$given    = isset( $p['unit_price'] ) && '' !== trim( self::s( $p['unit_price'] ) ) ? $num( $p['unit_price'] ) : null;
			$unit     = self::f( $opt['price'] ?? 0 );
			if ( null !== $given && abs( $given - $unit ) >= 0.005 ) {
				$reason = sanitize_text_field( self::s( $p['price_reason'] ?? '' ) );
				if ( '' === $reason ) {
					return self::invalid( __( 'Please give a reason for the different unit price.', 'flx-donate' ) );
				}
				$meta['price_override'] = array(
					'type_price' => $unit,
					'price'      => $given,
					'reason'     => $reason,
				);
				$unit                   = $given;
			}
			if ( $unit <= 0 ) {
				return self::invalid( __( 'This option has no price yet. Enter the unit price and a reason.', 'flx-donate' ) );
			}
			$amount = round( $unit * $qty, 2 );
			$lines  = array(
				array(
					'key'        => self::s( $opt['key'] ?? '' ),
					'label'      => self::s( $opt['label'] ?? '' ),
					'units'      => $qty,
					'unit_price' => $unit,
					'amount'     => $amount,
					'unit'       => self::s( $opt['unit'] ?? '' ),
					'units_per'  => self::i( $opt['units_per'] ?? 1 ),
				),
			);
		} else {
			$amount = $num( $p['amount'] ?? 0 );
			$unit   = $amount;
			$qty    = 1;
			$lines  = array(
				array(
					'key'        => '',
					'label'      => __( 'Free amount', 'flx-donate' ),
					'units'      => 1,
					'unit_price' => $amount,
					'amount'     => $amount,
					'unit'       => '',
					'units_per'  => 1,
				),
			);
		}
		if ( $amount <= 0 ) {
			return self::invalid( __( 'Please enter an amount.', 'flx-donate' ) );
		}
		$is_proxy = $type && FLXB_Types::requires_proxy( $type );
		$names    = array_values( array_map( static fn( $x ): string => self::s( $x ), self::arr( flxb_split_names( self::s( $p['beneficiaries'] ?? '' ) ) ) ) );
		if ( $is_proxy ) {
			$text = flxb_proxy_text();
			if ( ! is_array( $text ) || empty( $p['proxy_consent'] ) ) {
				return self::invalid( __( 'This donation type needs the donor\'s proxy (vekâlet) consent.', 'flx-donate' ) );
			}
			$meta['proxy_consent'] = array(
				'version'     => self::i( $text['version'] ?? 0 ),
				'accepted_at' => flxb_now(),
				'source'      => 'manual',
			);
		}
		/* 2.29.0: other people: the first takes the remaining units, every other one unit with their own certificate. */
		$people = ! $is_proxy && ! empty( $p['for_other'] ) ? array_slice( self::people( $p['people'] ?? null ), 0, $qty ) : array();
		foreach ( array( 'account', 'reference' ) as $k ) {
			$v = sanitize_text_field( self::s( $p[ $k ] ?? '' ) );
			if ( '' !== $v ) {
				$meta[ $k ] = $v;
			}
		}
		if ( 'bank_transfer' !== $method ) {
			unset( $meta['account'] );
		}
		$note = sanitize_textarea_field( self::s( $p['note'] ?? '' ) );
		if ( '' !== $note ) {
			$meta['note'] = $note;
		}
		$date    = sanitize_text_field( self::s( $p['date'] ?? '' ) );
		$local   = '';
		$created = self::s( flxb_now() );
		try {
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $date ) ) {
				$dt      = new DateTimeImmutable( str_replace( 'T', ' ', $date ) . ':00', wp_timezone() );
				$local   = $dt->format( 'Y-m-d H:i:s' );
				$created = $dt->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
			}
		} catch ( \Exception $e ) {
			$local = '';
		}
		$d     = self::arr( $p['donor'] ?? null );
		$donor = array(
			'name'            => sanitize_text_field( self::s( $d['name'] ?? '' ) ),
			'phone'           => self::phone( self::s( $d['phone'] ?? '' ) ),
			'email'           => self::s( sanitize_email( self::s( $d['email'] ?? '' ) ) ),
			'consent_kvkk'    => empty( $d['consent_kvkk'] ) ? 0 : 1,
			'consent_email'   => empty( $d['consent_email'] ) ? 0 : 1,
			'consent_sms'     => empty( $d['consent_sms'] ) ? 0 : 1,
			'allow_anonymous' => true,
		);
		$paid  = ! empty( $p['paid'] );
		$refid = self::i( FLXB_Refs::valid( absint( self::s( $p['ref_user_id'] ?? 0 ) ) ) );

		if ( $type && flxb_wc_active() ) {
			$id = self::save_order( $type, $opt, $qty, $amount, $currency, $method, $paid, $people, $names, $meta, $donor, $refid, $local );
		} else {
			if ( $people ) {
				$first                        = $people[0];
				$rest                         = array_slice( $people, 1 );
				$names                        = array_merge( array_fill( 0, max( 1, $qty - count( $rest ) ), $first['name'] ), array_column( $rest, 'name' ) );
				$meta['beneficiary_contacts'] = $people;
			}
			$id = (int) FLXB_Donations::record(
				array(
					'uniq'          => 'manual:' . wp_generate_uuid4(),
					'provider'      => $method,
					'provider_ref'  => self::s( $meta['reference'] ?? '' ),
					'amount'        => $amount,
					'currency'      => $currency,
					'type'          => 'one_time',
					'status'        => $paid ? 'completed' : 'pending',
					'donor_id'      => FLXB_Donors::upsert( $donor ),
					'type_id'       => $type ? self::i( $type['id'] ?? 0 ) : 0,
					'option_key'    => $opt ? self::s( $opt['key'] ?? '' ) : '',
					'option_label'  => $opt ? self::s( $opt['label'] ?? '' ) : '',
					'quantity'      => $qty,
					'unit_price'    => $unit,
					'source'        => 'manual',
					'ref_user_id'   => $refid,
					'created_at'    => $created,
					'meta'          => $meta,
					'lines'         => $lines,
					'beneficiaries' => $names,
				)
			);
		}
		if ( is_wp_error( $id ) ) {
			return self::invalid( $id->get_error_message() );
		}
		if ( ! $id ) {
			return self::invalid( __( 'Something went wrong. Please check the form and try again.', 'flx-donate' ) );
		}
		return array(
			'message' => __( 'Saved.', 'flx-donate' ),
			'id'      => (int) $id,
			'url'     => self::s( FLXB_Admin_Url::page( 'flxb-donations', array( 'donation' => (int) $id ) ) ),
		);
	}

	/**
	 * 2.29.0: write the gift as a WooCommerce order (created_via "flxb-manual") through flxb_create_order().
	 *
	 * @param array<string,mixed>      $type   Type.
	 * @param array<string,mixed>|null $opt    Option.
	 * @param list<array{name: string, phone: string, email: string}> $people Other people.
	 * @param list<string>             $names  Names (proxy).
	 * @param array<string,mixed>      $meta   Donation meta.
	 * @param array<string,mixed>      $donor  Donor.
	 * @return int|WP_Error First donation id.
	 */
	private static function save_order( array $type, $opt, int $qty, float $amount, string $currency, string $method, bool $paid, array $people, array $names, array $meta, array $donor, int $refid, string $local ) {
		$line = array(
			'type_id' => self::i( $type['id'] ?? 0 ),
			'option'  => $opt ? self::s( $opt['key'] ?? '' ) : '',
			'units'   => $opt ? $qty : 1,
			'amount'  => $amount,
		);
		if ( class_exists( 'FLXB_WC_Cart' ) && FLXB_WC_Cart::type_has_certificate( $type ) ) {
			$line['cert'] = 'online';
		}
		if ( ! empty( $meta['proxy_consent'] ) ) {
			$line['proxy_consent'] = $meta['proxy_consent'];
		}
		if ( $names ) {
			$line['beneficiaries'] = $names;
			$line['name']          = $names[0];
		}
		if ( $people ) {
			$first             = $people[0];
			$rest              = array_slice( $people, 1, max( 0, $qty - 1 ) );
			$line['for_other'] = true;
			$line['name']      = $first['name'];
			$line['ben_name']  = $first['name'];
			$line['ben_phone'] = $first['phone'];
			$line['ben_email'] = $first['email'];
			if ( $rest && FLXB_Types::tracks_items( $type ) ) {
				/* Tracked types keep one name per unit (each unit is an item with its own code). */
				$line['beneficiaries'] = array_merge( array_fill( 0, max( 1, $qty - count( $rest ) ), $first['name'] ), array_column( $rest, 'name' ) );
			} elseif ( $rest ) {
				$line['people'] = $rest;
			}
		}
		$parts = preg_split( '/\s+/u', trim( self::s( $donor['name'] ?? '' ) ), 2 );
		$parts = is_array( $parts ) ? $parts : array();
		$order = flxb_create_order(
			array(
				'lines'          => array( $line ),
				'billing'        => array(
					'first_name' => $parts[0] ?? '',
					'last_name'  => $parts[1] ?? '',
					'phone'      => $donor['phone'] ?? '',
					'email'      => $donor['email'] ?? '',
				),
				'payment_method' => 'bank_transfer' === $method ? FLXB_WC_Havale::ID : self::VIA,
				'payment_title'  => 'bank_transfer' === $method ? '' : self::methods()[ $method ],
				'status'         => $paid ? 'completed' : ( 'bank_transfer' === $method ? 'on-hold' : 'pending' ),
				'created_via'    => self::VIA,
				'ref_user_id'    => $refid,
				'note'           => self::s( $meta['note'] ?? '' ),
				'currency'       => $currency,
				'created_at'     => $local,
			)
		);
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		$ids = array_values( array_filter( self::arr( flxb_order_donation_ids( $order ) ) ) );
		if ( ! $ids ) {
			return new WP_Error( 'flxb_manual', __( 'Something went wrong. Please check the form and try again.', 'flx-donate' ) );
		}
		unset( $meta['proxy_consent'] );
		foreach ( $ids as $did ) {
			FLXB_Donations::update_meta( self::i( $did ), $meta );
		}
		FLXB_Donors::upsert( $donor ); // consents (KVKK, e-mail, SMS) on the donor card
		return self::i( $ids[0] );
	}

	/**
	 * 2.3.0: a staff donation request: a WooCommerce order the donor pays by the link; the caller is the referral.
	 *
	 * @param array<string,mixed> $p type_id, option_key, quantity, amount, beneficiaries, donor{name,phone,email},
	 *                               send_email, note.
	 * @return array<string,mixed>|WP_Error message, payUrl, whatsappUrl, text, emailed.
	 */
	public static function request( array $p ) {
		$d   = self::arr( $p['donor'] ?? null );
		$res = flxb_create_ref_request(
			array(
				'type_id'     => absint( self::s( $p['type_id'] ?? 0 ) ),
				'option'      => self::s( $p['option_key'] ?? '' ),
				'units'       => absint( self::s( $p['quantity'] ?? 1 ) ),
				'amount'      => round( (float) str_replace( ',', '.', self::s( $p['amount'] ?? 0 ) ), 2 ),
				'names'       => self::s( $p['beneficiaries'] ?? '' ),
				'donor'       => array(
					'name'  => self::s( $d['name'] ?? '' ),
					'phone' => self::s( $d['phone'] ?? '' ),
					'email' => self::s( $d['email'] ?? '' ),
				),
				'ref_user_id' => get_current_user_id(),
				'send_email'  => ! empty( $p['send_email'] ),
				'note'        => self::s( $p['note'] ?? '' ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return self::invalid( $res->get_error_message() );
		}
		$res   = self::arr( $res );
		$order = function_exists( 'wc_get_order' ) ? wc_get_order( self::i( $res['order_id'] ?? 0 ) ) : null;
		$url   = self::s( $res['pay_url'] ?? '' );
		$wa    = '';
		$text  = '';
		if ( $order instanceof WC_Order ) {
			$name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
			$phone = self::s( preg_replace( '/\D/', '', $order->get_billing_phone() ) );
			if ( 10 === strlen( $phone ) && '5' === $phone[0] ) {
				$phone = '90' . $phone;
			} elseif ( 11 === strlen( $phone ) && '0' === $phone[0] ) {
				$phone = '9' . $phone;
			}
			/* translators: 1: donor name, 2: amount, 3: payment link */
			$text = sprintf( __( 'Hello %1$s, we have received your donation request (%2$s). You can pay here: %3$s', 'flx-donate' ), $name, html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ), $url );
			$wa   = strlen( $phone ) >= 10 ? 'https://wa.me/' . $phone . '?text=' . rawurlencode( $text ) : '';
		}
		return array(
			'message'     => __( 'Request created', 'flx-donate' ),
			'payUrl'      => $url,
			'whatsappUrl' => $wa,
			'emailed'     => ! empty( $res['emailed'] ),
		);
	}
}
