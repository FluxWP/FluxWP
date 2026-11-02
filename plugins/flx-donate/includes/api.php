<?php
/**
 * Flux Donate: stable public read API for themes and other plugins.
 *
 * Everything here is part of the 1.x contract: signatures and return shapes only grow (new keys),
 * nothing is removed or renamed within 1.x. Feature-detect with function_exists( 'flxb_api_version' ).
 * Internal classes (FLXB_Types …) may change; FLXB_Types::get/get_by_slug/all/is_open/progress,
 * flxb_money() and flxb_categories() keep their 1.0 signatures through 1.x.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** API version ('2.0'). Bumped (minor) when keys or functions are added. 2.0 adds the WooCommerce functions at the end of this file; nothing of 1.x was removed. */
function flxb_api_version() {
	return '2.0';
}

/**
 * Donation types in the admin order.
 *
 * @param array $args {
 *     @type string       $status   'visible' (default: everything but archived) | 'open' (taking gifts today) | 'all'.
 *     @type string|array $category Category key(s).
 *     @type string[]     $slugs    Only these slugs.
 *     @type int[]        $ids      Only these ids.
 *     @type bool         $progress Include progress (default true; only for types with a goal).
 * }
 * @return array[] Public type shapes (see flxb_get_type()).
 */
function flxb_get_types( array $args = array() ) {
	$args   = wp_parse_args( $args, array( 'status' => 'visible', 'category' => '', 'slugs' => array(), 'ids' => array(), 'progress' => true ) );
	$list   = FLXB_Types::all( 'all' === $args['status'] ? 'all' : '' );
	$cats   = array_filter( (array) $args['category'], 'strlen' );
	$slugs  = array_map( 'sanitize_title', (array) $args['slugs'] );
	$ids    = array_map( 'intval', (array) $args['ids'] );
	$out    = array();
	foreach ( $list as $t ) {
		if ( 'open' === $args['status'] && ! FLXB_Types::is_open( $t ) ) {
			continue;
		}
		if ( $cats && ! in_array( $t['category'], $cats, true ) ) {
			continue;
		}
		if ( $slugs && ! in_array( $t['slug'], $slugs, true ) ) {
			continue;
		}
		if ( $ids && ! in_array( (int) $t['id'], $ids, true ) ) {
			continue;
		}
		$out[] = flxb_type_public( $t, (bool) $args['progress'] );
	}
	return $out;
}

/**
 * One donation type by id or slug, or null.
 *
 * Shape: id, slug, title, category, category_label, description, image_id, image_url, currency,
 * state (open|upcoming|ended|closed|archived), state_message, start_date, end_date,
 * options[] { key, label, price (float|null: null = price not announced), unit (string|null: null = no unit set), unit_set, unit_label,
 * unit_label_plural, units_per, active, badge, badge_label (2.0) }, allow_free, min_amount, allow_quantity, max_quantity,
 * allow_recurring, form_id, form_ids, requires_proxy, progress (array|null), meta {}.
 * The form mapping is not part of it. Filter: `flxb_type_public`.
 *
 * @param int|string $id_or_slug Type id or slug.
 * @return array|null
 */
function flxb_get_type( $id_or_slug ) {
	$t = is_numeric( $id_or_slug ) ? FLXB_Types::get( (int) $id_or_slug ) : FLXB_Types::get_by_slug( (string) $id_or_slug );
	return $t ? flxb_type_public( $t ) : null;
}

/**
 * State of a type today: open | upcoming | ended | closed | archived.
 *
 * @param array|int|string $t A type (public or internal shape), id or slug.
 */
function flxb_type_state( $t ) {
	if ( ! is_array( $t ) ) {
		$t = is_numeric( $t ) ? FLXB_Types::get( (int) $t ) : FLXB_Types::get_by_slug( (string) $t );
		if ( ! $t ) {
			return 'closed';
		}
	}
	if ( isset( $t['state'] ) && ! isset( $t['status'] ) ) {
		return (string) $t['state'];
	}
	return FLXB_Types::state( $t );
}

/** @internal Public shape of an internal type row. */
function flxb_type_public( array $t, $with_progress = true ) {
	$cats    = flxb_categories();
	$options = array();
	foreach ( $t['options'] as $o ) {
		$options[] = array(
			'key'               => $o['key'],
			'label'             => $o['label'],
			'price'             => (float) $o['price'] > 0 ? (float) $o['price'] : null,
			'unit'              => '' !== $o['unit'] ? $o['unit'] : null, // null = no unit set
			'unit_set'          => '' !== $o['unit'],
			'unit_label'        => '' !== $o['unit'] ? flxb_unit_label( $o['unit'], 1 ) : null,
			'unit_label_plural' => '' !== $o['unit'] ? flxb_unit_label( $o['unit'], 2 ) : null,
			'units_per'         => (int) $o['units_per'],
			'active'            => (bool) $o['active'],
			'badge'             => (string) ( $o['badge'] ?? '' ),
			'badge_label'       => class_exists( 'FLXB_WC' ) ? FLXB_WC::badge_label( (string) ( $o['badge'] ?? '' ) ) : (string) ( $o['badge'] ?? '' ),
		);
	}
	$out = array(
		'id'              => (int) $t['id'],
		'slug'            => (string) $t['slug'],
		'title'           => (string) $t['title'],
		'category'        => (string) $t['category'],
		'category_label'  => (string) ( $cats[ $t['category'] ] ?? '' ),
		'description'     => (string) $t['description'],
		'image_id'        => (int) $t['image_id'],
		'image_url'       => (int) $t['image_id'] ? (string) wp_get_attachment_image_url( (int) $t['image_id'], 'large' ) : '',
		'currency'        => (string) $t['currency'],
		'state'           => FLXB_Types::state( $t ),
		'state_message'   => FLXB_Types::closed_message( $t ),
		'start_date'      => (string) $t['start_date'],
		'end_date'        => (string) $t['end_date'],
		'options'         => $options,
		'allow_free'      => (bool) $t['allow_free'],
		'min_amount'      => (float) $t['min_amount'],
		'allow_quantity'  => (bool) $t['allow_quantity'],
		'max_quantity'    => (int) $t['max_quantity'],
		'allow_recurring' => (bool) $t['allow_recurring'],
		'form_id'         => $t['form_ids'] ? (int) $t['form_ids'][0] : 0,
		'form_ids'        => array_map( 'intval', $t['form_ids'] ),
		'requires_proxy'  => FLXB_Types::requires_proxy( $t ),
		'progress'        => $with_progress && (float) $t['goal_amount'] > 0 ? FLXB_Types::progress( $t ) : null,
		'meta'            => (array) $t['meta'],
	);
	return (array) apply_filters( 'flxb_type_public', $out, $t );
}

/** Type meta value (kurban module keys: requires_proxy, intention, meat_to_needy, grouping). */
function flxb_type_meta( $t, $key, $default = null ) {
	if ( ! is_array( $t ) ) {
		$t = FLXB_Types::get( (int) $t );
	}
	return $t ? FLXB_Types::meta( $t, $key, $default ) : $default;
}

/**
 * The form element (choice id) that selects an option of a type in a linked form, or ''.
 * With $qty, a choice that already stands for that many units (e.g. "5 saplings") is preferred.
 */
function flxb_form_element_for_option( $type_id, $form_id, $key, $qty = 1 ) {
	$t = FLXB_Types::get( (int) $type_id );
	if ( ! $t ) {
		return '';
	}
	$map  = (array) ( $t['form_map'][ (int) $form_id ]['opts'] ?? ( $t['form_map'][ (string) (int) $form_id ]['opts'] ?? array() ) );
	$best = '';
	foreach ( $map as $el => $m ) {
		if ( (string) ( $m['opt'] ?? '' ) !== (string) $key ) {
			continue;
		}
		if ( (int) $m['units'] === max( 1, (int) $qty ) ) {
			return (string) $el;
		}
		if ( '' === $best || 1 === (int) $m['units'] ) {
			$best = (string) $el;
		}
	}
	return $best;
}

/** Names typed one per line (or separated by ";"), trimmed, empty ones dropped. */
function flxb_split_names( $raw ) {
	$parts = preg_split( '/(?:[\r\n;]|@n#)+/', str_replace( '\n', "\n", (string) $raw ) );
	$out   = array();
	foreach ( (array) $parts as $p ) {
		$p = trim( sanitize_text_field( $p ) );
		if ( '' !== $p ) {
			$out[] = mb_substr( $p, 0, 191 );
		}
	}
	return $out;
}

/**
 * Current proxy (vekâlet) text: array( version, text, created_at ) or null when none is set.
 * Every edit in Settings › Kurban creates a new version; donations keep the version they accepted.
 */
function flxb_proxy_text( $version = 0 ) {
	$all = get_option( 'flxb_proxy_texts', array() );
	if ( ! is_array( $all ) || ! $all ) {
		return null;
	}
	if ( $version ) {
		foreach ( $all as $v ) {
			if ( (int) $v['version'] === (int) $version ) {
				return $v;
			}
		}
		return null;
	}
	return end( $all );
}

/**
 * Bank accounts: WooCommerce's "Direct bank transfer" accounts first (2.2.2), then those a theme or
 * add-on supplies (filter `flxb_bank_accounts`, e.g. Tema Ayarları › Banka hesabı).
 */
function flxb_bank_accounts() {
	$wc = array();
	foreach ( (array) get_option( 'woocommerce_bacs_accounts', array() ) as $i => $a ) {
		if ( ! is_array( $a ) || '' === trim( (string) ( $a['iban'] ?? '' ) ) ) {
			continue;
		}
		$wc[ 'wc' . $i ] = array(
			'id'     => 'wc' . $i,
			'holder' => trim( (string) ( $a['account_name'] ?? '' ) ),
			'iban'   => strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $a['iban'] ) ),
			'bank'   => trim( (string) ( $a['bank_name'] ?? '' ) ),
			'source' => 'woocommerce',
		);
	}
	$out  = array();
	$seen = array();
	foreach ( (array) apply_filters( 'flxb_bank_accounts', $wc ) as $k => $a ) {
		$iban = is_array( $a ) ? strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $a['iban'] ?? '' ) ) ) : '';
		if ( '' !== $iban && isset( $seen[ $iban ] ) ) {
			continue; // the same account in WooCommerce and in the theme
		}
		$seen[ $iban ] = true;
		$out[ $k ]     = $a;
	}
	return $out;
}

/**
 * The organisation's account for bank transfer donations, one source for every screen (2.2.2):
 * WooCommerce › Settings › Payments › "Bank transfer (donation)" (holder, IBAN, bank), else the
 * first account of WooCommerce's "Direct bank transfer", else the theme's (Tema Ayarları).
 *
 * @return array{holder:string,iban:string,iban_display:string,bank:string}|null Null when no IBAN is set anywhere.
 */
function flxb_bank_account() {
	if ( ! class_exists( 'FLXB_WC_Havale' ) ) {
		$a = current( array_filter( flxb_bank_accounts(), 'is_array' ) );
		$a = $a ? array( 'holder' => (string) ( $a['holder'] ?? ( $a['name'] ?? '' ) ), 'iban' => strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $a['iban'] ?? '' ) ) ), 'bank' => (string) ( $a['bank'] ?? '' ) ) : null;
	} else {
		$a = array( 'holder' => (string) FLXB_WC_Havale::option( 'holder' ), 'iban' => strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) FLXB_WC_Havale::option( 'iban' ) ) ), 'bank' => (string) FLXB_WC_Havale::option( 'bank' ) );
	}
	if ( ! $a || '' === $a['iban'] ) {
		return null;
	}
	$a['iban_display'] = trim( chunk_split( $a['iban'], 4, ' ' ) );
	return $a;
}

/* ----------------------------------------------------------------------
 * 2.0: WooCommerce
 * ---------------------------------------------------------------------- */

/** Is the WooCommerce layer running (WooCommerce active and new enough)? */
function flxb_wc_active() {
	return class_exists( 'FLXB_WC' ) && FLXB_WC::ready();
}

/**
 * Create a WooCommerce order for donations (call centres, imports, other plugins). Prices come from
 * the donation types unless a line gives `amount`. Customer e-mails of WooCommerce are not sent
 * unless `emails` is true. Every line is written to the donation list.
 *
 * @param array $args {
 *     @type array[] $lines          [ { type_id, option, units, amount, cert ('' | online | print), name,
 *                                   for_other, ben_name, ben_phone, ben_email, donation_id (link to an
 *                                   existing donation row instead of adding one) } ].
 *     @type array   $billing        { first_name, last_name, email, phone }.
 *     @type int     $customer_id    Optional; found by billing e-mail when empty.
 *     @type string  $payment_method 'flxb_havale' (bank transfer, gets a reference code) or any gateway id; '' = none.
 *     @type string  $payment_title  Shown on the order (e.g. "Cash").
 *     @type string  $status         pending (default) | on-hold | completed (paid now).
 *     @type string  $created_via    Default 'flxb-api'.
 *     @type string  $note           Private order note.
 *     @type bool    $emails         Send WooCommerce e-mails (default false).
 *     @type int     $ref_user_id    2.3.0: referral (order meta `_flx_ref_user`, also on the donation rows).
 *     @type string  $currency       2.10.0: order currency (no conversion).
 *     @type string  $created_at     2.11.0: local 'Y-m-d H:i:s' for older records (order and donation date).
 * }
 * @return WC_Order|WP_Error
 */
function flxb_create_order( array $args ) {
	if ( ! flxb_wc_active() ) {
		return new WP_Error( 'flxb_wc_inactive', __( 'WooCommerce is not active.', 'flx-donate' ) );
	}
	return FLXB_WC_Callcenter::create_order( $args );
}

/** WooCommerce order id of a donation row (0 = not paid through WooCommerce). */
function flxb_donation_order_id( $donation_id ) {
	return flxb_wc_active() ? (int) FLXB_WC_Ledger::order_of( (int) $donation_id ) : 0;
}

/** Donation rows of an order: order item id => donation id (written when the order is synced). */
function flxb_order_donation_ids( $order ) {
	$order = $order instanceof WC_Order ? $order : ( function_exists( 'wc_get_order' ) ? wc_get_order( $order ) : null );
	return $order ? array_map( 'intval', (array) $order->get_meta( '_flxb_donation_ids' ) ) : array();
}

/**
 * Does the "Donation options" block (flx-wc-bagis/secenekler, [flx_bagis_secenekleri]) have anything
 * to show: WooCommerce layer running and at least one open type marked for it?
 *
 * @since 2.0.0
 * @return bool
 */
function flxb_wc_form_available() {
	return flxb_wc_active() && class_exists( 'FLXB_WC_Form' ) && FLXB_WC_Form::available();
}

/**
 * Link that opens the donation page with a type (and option) selected.
 *
 * @param int|string $type   Type id or slug.
 * @param string     $option Option key.
 */
function flxb_donate_url( $type, $option = '' ) {
	$t = is_numeric( $type ) ? FLXB_Types::get( (int) $type ) : FLXB_Types::get_by_slug( (string) $type );
	if ( ! $t || ! flxb_wc_active() ) {
		return home_url( '/' );
	}
	$page = FLXB_WC::type_page_url( $t );
	$page = '' !== $page ? $page : FLXB_WC::page_url();
	return add_query_arg( array_filter( array( 'sekme' => (string) $t['slug'], 'flxb_opt' => sanitize_key( (string) $option ) ) ), $page );
}

/**
 * Gifts awaiting approval (bank transfer / receipt); one WooCommerce order counts once (2.0.9).
 *
 * @return int
 */
function flxb_pending_count() {
	return class_exists( 'FLXB_Donations' ) ? FLXB_Donations::pending_count() : 0;
}

/**
 * 2.3.0: a donation request on behalf of a donor, opened by a staff member (flxb_create_ref).
 * Makes a pending WooCommerce order (the donor chooses card or bank transfer on the payment page),
 * the referral is the staff member, and returns the payment link to send to the donor.
 *
 * @param array $args {
 *     @type int      $type_id     Donation type.
 *     @type string   $option      Option key ('' = free amount, then `amount`).
 *     @type int      $units       Quantity (default 1).
 *     @type float    $amount      Only for a free amount.
 *     @type string[] $names       "On behalf of" names, one per unit.
 *     @type string   $cert        '' | online (default: online when the type has certificates).
 *     @type array    $donor       { name, phone, email } — phone or e-mail required.
 *     @type int      $ref_user_id Staff member (default: current user); needs flxb_create_ref or management rights.
 *     @type bool     $send_email  E-mail the payment link to the donor (default true when an e-mail is given).
 *     @type string   $note        Private order note.
 * }
 * @return array|WP_Error { order_id, donation_id, pay_url, amount, currency, emailed }
 */
function flxb_create_ref_request( array $args ) {
	return FLXB_Refs::create_request( $args );
}

