<?php
/**
 * Store settings and the bank-transfer gateway options.
 *
 * The feed address is the full URL the classic screen shows. The bare key is not a field.
 * An empty GraphQL input keeps the stored value. The classic form still posts every visible key.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Settings_Service {

	const BANK_OPTION = 'woocommerce_flxcm_bank_settings';

	/**
	 * Classic admin-post body. Skips keys the screen does not render, same as before.
	 *
	 * @param array<string,mixed> $in Raw POST.
	 * @return array<string,mixed>
	 */
	public static function from_request( array $in ): array {
		$bool = array( 'exclude_donations', 'hide_company', 'hide_address_2', 'virtual_no_address', 'order_notes', 'sticky_pay', 'free_ship_bar', 'defer_to_donate', 'feed', 'notify_customer_wa', 'notify_admin_order' );
		$out  = array();
		foreach ( $bool as $k ) {
			// Ekranda olmayan anahtar (ör. Flux Donate yokken) eski değerini korur.
			if ( 'exclude_donations' === $k && ! class_exists( 'FLXB_WC_Cart' ) || 'defer_to_donate' === $k && ! class_exists( 'FLXB_WC_Havale' ) ) {
				continue;
			}
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		$valid = function_exists( 'wc_get_order_statuses' ) ? array_map( static function ( $k ) { return substr( FLXCM_Val::str($k), 3 ); }, array_keys( wc_get_order_statuses() ) ) : array( 'processing', 'completed', 'on-hold', 'pending', 'cancelled', 'refunded', 'failed' );
		$paid  = array_values( array_intersect( array_map( static function ( $status ) { return sanitize_key( FLXCM_Val::str( $status ) ); }, FLXCM_Val::arr( $in['paid_statuses'] ?? array() ) ), $valid ) );
		$out['paid_statuses']      = $paid ? $paid : array( 'processing', 'completed' );
		$out['low_stock']          = min( 9999, absint( FLXCM_Val::str( $in['low_stock'] ?? 3 ) ) );
		$out['free_ship_amount']   = max( 0, function_exists( 'wc_format_decimal' ) ? FLXCM_Val::float( wc_format_decimal( FLXCM_Val::str( $in['free_ship_amount'] ?? 0 ) ) ) : FLXCM_Val::float( $in['free_ship_amount'] ?? 0 ) );
		$out['receipt_max_mb']     = max( 1, min( 20, absint( FLXCM_Val::str( $in['receipt_max_mb'] ?? 8 ) ) ) );
		$out['receipt_notify']     = sanitize_email( FLXCM_Val::str(( $in['receipt_notify'] ?? '' )) );
		$out['cancel_after_days']  = min( 60, absint( FLXCM_Val::str( $in['cancel_after_days'] ?? 0 ) ) );
		$out['sub_renew_days']     = min( 60, absint( FLXCM_Val::str( $in['sub_renew_days'] ?? 7 ) ) );
		$out['sub_grace_days']     = min( 60, absint( FLXCM_Val::str( $in['sub_grace_days'] ?? 3 ) ) );
		$out['hidden_redirect']    = esc_url_raw( trim( FLXCM_Val::str(( $in['hidden_redirect'] ?? '' )) ) );
		$out['feed_brand']         = sanitize_text_field( FLXCM_Val::str(( $in['feed_brand'] ?? '' )) );
		$out['notify_admin_phone'] = preg_replace( '/[^0-9+ ]/', '', FLXCM_Val::str(( $in['notify_admin_phone'] ?? '' )) );
		return $out;
	}

	/**
	 * GraphQL partial update. Absent keys are left out so flxcm_save_settings keeps them.
	 * The feed key is never accepted here.
	 *
	 * @param array<string,mixed> $in Camel-case input.
	 * @return array<string,mixed>
	 */
	public static function from_input( array $in ): array {
		$map = array(
			'excludeDonations' => 'exclude_donations',
			'hideCompany'      => 'hide_company',
			'hideAddress2'     => 'hide_address_2',
			'virtualNoAddress' => 'virtual_no_address',
			'orderNotes'       => 'order_notes',
			'stickyPay'        => 'sticky_pay',
			'freeShipBar'      => 'free_ship_bar',
			'deferToDonate'    => 'defer_to_donate',
			'feed'             => 'feed',
			'notifyCustomerWa' => 'notify_customer_wa',
			'notifyAdminOrder' => 'notify_admin_order',
		);
		$out = array();
		foreach ( $map as $from => $to ) {
			if ( ! array_key_exists( $from, $in ) || null === $in[ $from ] ) {
				continue;
			}
			// Same guard as the classic form: without Flux Donate these keys are not editable and keep their value.
			if ( 'excludeDonations' === $from && ! class_exists( 'FLXB_WC_Cart' ) || 'deferToDonate' === $from && ! class_exists( 'FLXB_WC_Havale' ) ) {
				continue;
			}
			$out[ $to ] = empty( $in[ $from ] ) ? 0 : 1;
		}
		if ( array_key_exists( 'paidStatuses', $in ) && is_array( $in['paidStatuses'] ) ) {
			$valid = function_exists( 'wc_get_order_statuses' ) ? array_map( static function ( $k ) { return substr( FLXCM_Val::str($k), 3 ); }, array_keys( wc_get_order_statuses() ) ) : array( 'processing', 'completed', 'on-hold', 'pending', 'cancelled', 'refunded', 'failed' );
			$paid  = array_values( array_intersect( array_map( static function ( $status ) { return sanitize_key( FLXCM_Val::str( $status ) ); }, FLXCM_Val::arr( $in['paidStatuses'] ) ), $valid ) );
			$out['paid_statuses'] = $paid ? $paid : array( 'processing', 'completed' );
		}
		$ints = array(
			'lowStock'         => array( 'low_stock', 0, 9999 ),
			'receiptMaxMb'     => array( 'receipt_max_mb', 1, 20 ),
			'cancelAfterDays'  => array( 'cancel_after_days', 0, 60 ),
			'subRenewDays'     => array( 'sub_renew_days', 0, 60 ),
			'subGraceDays'     => array( 'sub_grace_days', 0, 60 ),
		);
		foreach ( $ints as $from => $spec ) {
			if ( ! array_key_exists( $from, $in ) || null === $in[ $from ] ) {
				continue;
			}
			$out[ $spec[0] ] = min( $spec[2], max( $spec[1], absint( FLXCM_Val::str( $in[ $from ] ) ) ) );
		}
		if ( array_key_exists( 'freeShipAmount', $in ) && null !== $in['freeShipAmount'] ) {
			$out['free_ship_amount'] = max( 0, FLXCM_Val::float($in['freeShipAmount']) );
		}
		if ( array_key_exists( 'receiptNotify', $in ) && null !== $in['receiptNotify'] ) {
			$out['receipt_notify'] = sanitize_email( FLXCM_Val::str($in['receiptNotify']) );
		}
		if ( array_key_exists( 'hiddenRedirect', $in ) && null !== $in['hiddenRedirect'] ) {
			$out['hidden_redirect'] = esc_url_raw( trim( FLXCM_Val::str($in['hiddenRedirect']) ) );
		}
		if ( array_key_exists( 'feedBrand', $in ) && null !== $in['feedBrand'] ) {
			$out['feed_brand'] = sanitize_text_field( FLXCM_Val::str($in['feedBrand']) );
		}
		if ( array_key_exists( 'notifyAdminPhone', $in ) && null !== $in['notifyAdminPhone'] ) {
			$out['notify_admin_phone'] = FLXCM_Val::str( preg_replace( '/[^0-9+ ]/', '', FLXCM_Val::str( $in['notifyAdminPhone'] ) ) );
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $values Values to merge.
	 * @return array{paidStatuses:list<string>,excludeDonations:bool,lowStock:int,hideCompany:bool,hideAddress2:bool,virtualNoAddress:bool,orderNotes:bool,stickyPay:bool,freeShipBar:bool,freeShipAmount:float,freeShipThreshold:array{amount:float,currency:string,formatted:string},receiptMaxMb:int,receiptNotify:string,deferToDonate:bool,cancelAfterDays:int,feed:bool,feedBrand:string,feedUrl:string,notifyCustomerWa:bool,notifyAdminPhone:string,notifyAdminOrder:bool,subRenewDays:int,subGraceDays:int,hiddenRedirect:string,orderStatuses:list<array{slug:string,label:string}>,cardProviders:list<string>,whatsappReady:bool,donateCart:bool,donateHavale:bool}
	 */
	public static function save( array $values ): array {
		flxcm_save_settings( $values );
		delete_transient( 'flxcm_free_min' );
		if ( class_exists( 'FLXCM_Feed' ) ) {
			FLXCM_Feed::flush();
		}
		flxcm_gql_purge( array( 'flx-commerce:settings', 'flx-commerce:summary' ) );
		return self::read();
	}

	/**
	 * Settings for a shop manager. `feedUrl` is the full address, including the key, as on the classic screen.
	 *
	 * @return array{paidStatuses:list<string>,excludeDonations:bool,lowStock:int,hideCompany:bool,hideAddress2:bool,virtualNoAddress:bool,orderNotes:bool,stickyPay:bool,freeShipBar:bool,freeShipAmount:float,freeShipThreshold:array{amount:float,currency:string,formatted:string},receiptMaxMb:int,receiptNotify:string,deferToDonate:bool,cancelAfterDays:int,feed:bool,feedBrand:string,feedUrl:string,notifyCustomerWa:bool,notifyAdminPhone:string,notifyAdminOrder:bool,subRenewDays:int,subGraceDays:int,hiddenRedirect:string,orderStatuses:list<array{slug:string,label:string}>,cardProviders:list<string>,whatsappReady:bool,donateCart:bool,donateHavale:bool}
	 */
	public static function read(): array {
		$s    = FLXCM_Val::map( flxcm_settings() );
		$feed = (bool) $s['feed'];
		$min  = class_exists( 'FLXCM_Checkout' ) ? FLXCM_Val::float(FLXCM_Checkout::free_threshold()) : 0.0;
		return array(
			'paidStatuses'      => array_values( array_map( static function ( $status ) { return FLXCM_Val::str( $status ); }, FLXCM_Val::arr( $s['paid_statuses'] ) ) ),
			'excludeDonations'  => (bool) $s['exclude_donations'],
			'lowStock'          => FLXCM_Val::int($s['low_stock']),
			'hideCompany'       => (bool) $s['hide_company'],
			'hideAddress2'      => (bool) $s['hide_address_2'],
			'virtualNoAddress'  => (bool) $s['virtual_no_address'],
			'orderNotes'        => (bool) $s['order_notes'],
			'stickyPay'         => (bool) $s['sticky_pay'],
			'freeShipBar'       => (bool) $s['free_ship_bar'],
			'freeShipAmount'    => FLXCM_Val::float($s['free_ship_amount']),
			'freeShipThreshold' => flxcm_gql_money( $min ),
			'receiptMaxMb'      => FLXCM_Val::int($s['receipt_max_mb']),
			'receiptNotify'     => FLXCM_Val::str($s['receipt_notify']),
			'deferToDonate'     => (bool) $s['defer_to_donate'],
			'cancelAfterDays'   => FLXCM_Val::int($s['cancel_after_days']),
			'feed'              => $feed,
			'feedBrand'         => FLXCM_Val::str($s['feed_brand']),
			'feedUrl'           => ( $feed && class_exists( 'FLXCM_Feed' ) ) ? FLXCM_Val::str( FLXCM_Feed::url() ) : '',
			'notifyCustomerWa'  => (bool) $s['notify_customer_wa'],
			'notifyAdminPhone'  => FLXCM_Val::str($s['notify_admin_phone']),
			'notifyAdminOrder'  => (bool) $s['notify_admin_order'],
			'subRenewDays'      => FLXCM_Val::int($s['sub_renew_days']),
			'subGraceDays'      => FLXCM_Val::int($s['sub_grace_days']),
			'hiddenRedirect'    => FLXCM_Val::str($s['hidden_redirect']),
			'orderStatuses'     => self::order_statuses(),
			'cardProviders'     => self::card_provider_labels(),
			'whatsappReady'     => class_exists( 'FLXCM_Notify' ) && FLXCM_Notify::whatsapp_ready(),
			'donateCart'        => class_exists( 'FLXB_WC_Cart' ),
			'donateHavale'      => class_exists( 'FLXB_WC_Havale' ),
		);
	}

	/**
	 * Statuses the classic settings screen offers as "paid".
	 *
	 * @return list<array{slug:string,label:string}>
	 */
	private static function order_statuses(): array {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}
		$skip = array( 'pending', 'cancelled', 'failed', 'checkout-draft', 'refunded' );
		$out  = array();
		foreach ( wc_get_order_statuses() as $key => $label ) {
			$slug = substr( FLXCM_Val::str($key), 3 );
			if ( in_array( $slug, $skip, true ) ) {
				continue;
			}
			$out[] = array(
				'slug'  => $slug,
				'label' => FLXCM_Val::str($label),
			);
		}
		return $out;
	}

	/**
	 * Provider names only. The card and charge callables stay on the PHP side.
	 *
	 * @return list<string>
	 */
	private static function card_provider_labels(): array {
		if ( ! class_exists( 'FLXCM_Sub_Cards' ) ) {
			return array();
		}
		$labels = array();
		foreach ( FLXCM_Sub_Cards::providers() as $provider ) {
			$provider = FLXCM_Val::map( $provider );
			if ( isset( $provider['label'] ) ) {
				$labels[] = FLXCM_Val::str($provider['label']);
			}
		}
		return $labels;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function bank_read(): array {
		$o = get_option( self::BANK_OPTION, array() );
		$o = is_array( $o ) ? $o : array();
		return array(
			'enabled'   => 'yes' === ( $o['enabled'] ?? 'no' ),
			'title'     => FLXCM_Val::str(( $o['title'] ?? '' )),
			'description' => FLXCM_Val::str(( $o['description'] ?? '' )),
			'holder'    => FLXCM_Val::str(( $o['holder'] ?? '' )),
			'iban'      => FLXCM_Val::str(( $o['iban'] ?? '' )),
			'bank'      => FLXCM_Val::str(( $o['bank'] ?? '' )),
			'refFormat' => FLXCM_Val::str(( $o['ref_format'] ?? '' )),
			'refPrefix' => FLXCM_Val::str(( $o['ref_prefix'] ?? 'SP' )),
		);
	}

	/**
	 * The registered bank gateway, or null while WooCommerce is not loaded.
	 *
	 * @return WC_Payment_Gateway|null
	 */
	private static function bank_gateway() {
		if ( ! flxcm_wc_ready() || ! function_exists( 'WC' ) ) {
			return null;
		}
		$gateways = WC()->payment_gateways()->payment_gateways();
		$gateway  = $gateways[ FLXCM_Bank::ID ] ?? null;
		return $gateway instanceof WC_Payment_Gateway ? $gateway : null;
	}

	/**
	 * Saves through the gateway's own WooCommerce Settings API, the way WooCommerce > Payments does:
	 * the field validators, the sanitized-fields filter, the update action and the option's autoload all run.
	 * Fields you leave out keep their stored value (the Settings API alone would blank them).
	 *
	 * @param array<string,mixed> $in Partial input.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function bank_save( array $in ) {
		$gateway = self::bank_gateway();
		if ( null === $gateway ) {
			return new WP_Error( 'flxcm_wc', __( 'WooCommerce is required.', 'flx-commerce' ), array( 'status' => 400 ) );
		}
		$gateway->init_settings();
		$cur  = FLXCM_Val::map( $gateway->settings );
		$str  = array(
			'title'       => 'title',
			'description' => 'description',
			'holder'      => 'holder',
			'iban'        => 'iban',
			'bank'        => 'bank',
			'refFormat'   => 'ref_format',
			'refPrefix'   => 'ref_prefix',
		);
		$new  = $cur;
		foreach ( $str as $from => $to ) {
			if ( array_key_exists( $from, $in ) && null !== $in[ $from ] ) {
				$new[ $to ] = FLXCM_Val::str( $in[ $from ] );
			}
		}
		$enabled = 'yes' === FLXCM_Val::str( $cur['enabled'] ?? 'no' );
		if ( array_key_exists( 'enabled', $in ) && null !== $in['enabled'] ) {
			$enabled = ! empty( $in['enabled'] );
		}
		// Build the POST the settings form would send. The Settings API strips slashes, so slash first.
		$post = array();
		foreach ( array_keys( $gateway->get_form_fields() ) as $key ) {
			$name = $gateway->get_field_key( $key );
			if ( 'enabled' === $key ) {
				if ( $enabled ) {
					$post[ $name ] = '1';
				}
				continue;
			}
			$post[ $name ] = wp_slash( FLXCM_Val::str( $new[ $key ] ?? '' ) );
		}
		$gateway->set_post_data( $post );
		do_action( 'woocommerce_update_options_payment_gateways_' . FLXCM_Bank::ID );
		$gateway->set_post_data( array() );
		$gateway->init_settings();
		flxcm_gql_purge( array( 'flx-commerce:settings' ) );
		return self::bank_read();
	}
}
