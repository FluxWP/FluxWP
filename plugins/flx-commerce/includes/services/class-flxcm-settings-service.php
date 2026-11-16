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
		$valid = function_exists( 'wc_get_order_statuses' ) ? array_map( static function ( $k ) { return substr( (string) $k, 3 ); }, array_keys( wc_get_order_statuses() ) ) : array( 'processing', 'completed', 'on-hold', 'pending', 'cancelled', 'refunded', 'failed' );
		$paid  = array_values( array_intersect( array_map( 'sanitize_key', (array) ( $in['paid_statuses'] ?? array() ) ), $valid ) );
		$out['paid_statuses']      = $paid ? $paid : array( 'processing', 'completed' );
		$out['low_stock']          = min( 9999, absint( $in['low_stock'] ?? 3 ) );
		$out['free_ship_amount']   = max( 0, function_exists( 'wc_format_decimal' ) ? (float) wc_format_decimal( $in['free_ship_amount'] ?? 0 ) : (float) ( $in['free_ship_amount'] ?? 0 ) );
		$out['receipt_max_mb']     = max( 1, min( 20, absint( $in['receipt_max_mb'] ?? 8 ) ) );
		$out['receipt_notify']     = sanitize_email( (string) ( $in['receipt_notify'] ?? '' ) );
		$out['cancel_after_days']  = min( 60, absint( $in['cancel_after_days'] ?? 0 ) );
		$out['sub_renew_days']     = min( 60, absint( $in['sub_renew_days'] ?? 7 ) );
		$out['sub_grace_days']     = min( 60, absint( $in['sub_grace_days'] ?? 3 ) );
		$out['hidden_redirect']    = esc_url_raw( trim( (string) ( $in['hidden_redirect'] ?? '' ) ) );
		$out['feed_brand']         = sanitize_text_field( (string) ( $in['feed_brand'] ?? '' ) );
		$out['notify_admin_phone'] = preg_replace( '/[^0-9+ ]/', '', (string) ( $in['notify_admin_phone'] ?? '' ) );
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
			$out[ $to ] = empty( $in[ $from ] ) ? 0 : 1;
		}
		if ( array_key_exists( 'paidStatuses', $in ) && is_array( $in['paidStatuses'] ) ) {
			$valid = function_exists( 'wc_get_order_statuses' ) ? array_map( static function ( $k ) { return substr( (string) $k, 3 ); }, array_keys( wc_get_order_statuses() ) ) : array( 'processing', 'completed', 'on-hold', 'pending', 'cancelled', 'refunded', 'failed' );
			$paid  = array_values( array_intersect( array_map( 'sanitize_key', $in['paidStatuses'] ), $valid ) );
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
			$out[ $spec[0] ] = min( $spec[2], max( $spec[1], absint( $in[ $from ] ) ) );
		}
		if ( array_key_exists( 'freeShipAmount', $in ) && null !== $in['freeShipAmount'] ) {
			$out['free_ship_amount'] = max( 0, (float) $in['freeShipAmount'] );
		}
		if ( array_key_exists( 'receiptNotify', $in ) && null !== $in['receiptNotify'] ) {
			$out['receipt_notify'] = sanitize_email( (string) $in['receiptNotify'] );
		}
		if ( array_key_exists( 'hiddenRedirect', $in ) && null !== $in['hiddenRedirect'] ) {
			$out['hidden_redirect'] = esc_url_raw( trim( (string) $in['hiddenRedirect'] ) );
		}
		if ( array_key_exists( 'feedBrand', $in ) && null !== $in['feedBrand'] ) {
			$out['feed_brand'] = sanitize_text_field( (string) $in['feedBrand'] );
		}
		if ( array_key_exists( 'notifyAdminPhone', $in ) && null !== $in['notifyAdminPhone'] ) {
			$out['notify_admin_phone'] = (string) preg_replace( '/[^0-9+ ]/', '', (string) $in['notifyAdminPhone'] );
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $values Values to merge.
	 * @return array<string,mixed> GraphQL settings.
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
	 * @return array<string,mixed>
	 */
	public static function read(): array {
		$s    = flxcm_settings();
		$feed = (bool) $s['feed'];
		$min  = class_exists( 'FLXCM_Checkout' ) ? (float) FLXCM_Checkout::free_threshold() : 0.0;
		return array(
			'paidStatuses'      => array_values( array_map( 'strval', (array) $s['paid_statuses'] ) ),
			'excludeDonations'  => (bool) $s['exclude_donations'],
			'lowStock'          => (int) $s['low_stock'],
			'hideCompany'       => (bool) $s['hide_company'],
			'hideAddress2'      => (bool) $s['hide_address_2'],
			'virtualNoAddress'  => (bool) $s['virtual_no_address'],
			'orderNotes'        => (bool) $s['order_notes'],
			'stickyPay'         => (bool) $s['sticky_pay'],
			'freeShipBar'       => (bool) $s['free_ship_bar'],
			'freeShipAmount'    => (float) $s['free_ship_amount'],
			'freeShipThreshold' => flxcm_gql_money( $min ),
			'receiptMaxMb'      => (int) $s['receipt_max_mb'],
			'receiptNotify'     => (string) $s['receipt_notify'],
			'deferToDonate'     => (bool) $s['defer_to_donate'],
			'cancelAfterDays'   => (int) $s['cancel_after_days'],
			'feed'              => $feed,
			'feedBrand'         => (string) $s['feed_brand'],
			'feedUrl'           => ( $feed && class_exists( 'FLXCM_Feed' ) ) ? FLXCM_Feed::url() : '',
			'notifyCustomerWa'  => (bool) $s['notify_customer_wa'],
			'notifyAdminPhone'  => (string) $s['notify_admin_phone'],
			'notifyAdminOrder'  => (bool) $s['notify_admin_order'],
			'subRenewDays'      => (int) $s['sub_renew_days'],
			'subGraceDays'      => (int) $s['sub_grace_days'],
			'hiddenRedirect'    => (string) $s['hidden_redirect'],
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
			$slug = substr( (string) $key, 3 );
			if ( in_array( $slug, $skip, true ) ) {
				continue;
			}
			$out[] = array(
				'slug'  => $slug,
				'label' => (string) $label,
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
			if ( is_array( $provider ) && isset( $provider['label'] ) ) {
				$labels[] = (string) $provider['label'];
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
			'title'     => (string) ( $o['title'] ?? '' ),
			'description' => (string) ( $o['description'] ?? '' ),
			'holder'    => (string) ( $o['holder'] ?? '' ),
			'iban'      => (string) ( $o['iban'] ?? '' ),
			'bank'      => (string) ( $o['bank'] ?? '' ),
			'refFormat' => (string) ( $o['ref_format'] ?? '' ),
			'refPrefix' => (string) ( $o['ref_prefix'] ?? 'SP' ),
		);
	}

	/**
	 * @param array<string,mixed> $in Partial input.
	 * @return array<string,mixed>
	 */
	public static function bank_save( array $in ): array {
		$cur = get_option( self::BANK_OPTION, array() );
		$cur = is_array( $cur ) ? $cur : array();
		$str = array(
			'title'     => 'title',
			'description' => 'description',
			'holder'    => 'holder',
			'iban'      => 'iban',
			'bank'      => 'bank',
			'refFormat' => 'ref_format',
			'refPrefix' => 'ref_prefix',
		);
		foreach ( $str as $from => $to ) {
			if ( array_key_exists( $from, $in ) && null !== $in[ $from ] ) {
				$cur[ $to ] = sanitize_text_field( (string) $in[ $from ] );
			}
		}
		if ( array_key_exists( 'enabled', $in ) && null !== $in['enabled'] ) {
			$cur['enabled'] = empty( $in['enabled'] ) ? 'no' : 'yes';
		}
		update_option( self::BANK_OPTION, $cur, false );
		flxcm_gql_purge( array( 'flx-commerce:settings' ) );
		return self::bank_read();
	}
}
