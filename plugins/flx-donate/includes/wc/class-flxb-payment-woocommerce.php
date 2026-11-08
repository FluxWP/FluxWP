<?php
/**
 * Flux Donate 2.0: Flux Forms payment provider "woocommerce" (pay a form on the WooCommerce checkout).
 * See FLXB_WC_FLXForm for the whole flow.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXB_Payment_WooCommerce extends FLXB_Payment_Provider_Base {

	public function id() {
		return FLXB_WC_FLXForm::PROVIDER;
	}

	public function label() {
		return __( '\\nWooCommerce checkout\\n', 'flx-donate' );
	}

	public function supports( $currency, $method ) {
		return in_array( $method, array( 'charge', 'month' ), true ) && (bool) preg_match( '/^[A-Z]{3}$/', strtoupper( (string) $currency ) );
	}

	public function is_configured() {
		return FLXB_WC::ready();
	}

	public function front_assets() {
		return array(
			'scripts' => array(
				'flxf-pay-wc' => array(
					'src'  => FLXB_URL . 'assets/wc/flxf-pay-wc.js',
					'ver'  => FLXB_VERSION,
					'data' => array(
						'flxfPayWc' => array(
							'note'    => __( '\\nYou will choose how to pay (card or bank transfer) on the next page.\\n', 'flx-donate' ),
							'monthly' => __( '\\nMonthly donations are paid by card; the card is stored by iyzico and charged once a month.\\n', 'flx-donate' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Lines for the cart from the server price of the form.
	 *
	 * @return array|WP_Error
	 */
	public static function lines( $amount, array $meta ) {
		$snap    = isset( $meta['flxb'] ) && is_array( $meta['flxb'] ) ? $meta['flxb'] : array();
		$tid     = (int) ( $snap['type_id'] ?? 0 );
		$monthly = 'month' === (string) ( $meta['method'] ?? 'charge' );
		$form    = array( 'id' => (int) ( $meta['form_id'] ?? 0 ), 'track' => '' );
		$contact = isset( $meta['contact'] ) && is_array( $meta['contact'] ) ? $meta['contact'] : array();
		$names   = isset( $snap['beneficiaries'] ) && is_array( $snap['beneficiaries'] ) ? array_values( array_filter( array_map( 'strval', $snap['beneficiaries'] ) ) ) : array();
		if ( ! $tid ) {
			/* A payment form not linked to a donation type: one general donation line for the server amount. */
			$tid = FLXB_WC_Cart::fallback_type();
			if ( ! $tid ) {
				return new WP_Error( 'flxb_type', __( '\\nThis form is not linked to a donation type. Please contact the site administrator.\\n', 'flx-donate' ) );
			}
			$snap['lines'] = array( array( 'key' => '', 'units' => 1, 'amount' => $amount ) );
		}
		$t = FLXB_Types::get( $tid );
		if ( ! $t ) {
			return new WP_Error( 'flxb_type', __( '\\nThe donation type of this form was not found.\\n', 'flx-donate' ) );
		}
		$cert  = FLXB_WC_Cart::type_has_certificate( $t ) && ! FLXB_WC_Certificates::form_handles( array( 'form' => $form ) ) ? 'online' : '';
		$lines   = array();
		$sum     = 0.0;
		$proxy   = FLXB_Types::requires_proxy( $t );
		$consent = isset( $snap['proxy_consent'] ) && is_array( $snap['proxy_consent'] ) ? $snap['proxy_consent'] : null;
		$next    = 0;
		foreach ( (array) ( $snap['lines'] ?? array() ) as $l ) {
			$a = round( (float) ( $l['amount'] ?? 0 ), 2 );
			if ( $a <= 0 ) {
				continue;
			}
			$sum += $a;
			/* 2.0.1: one name per share (unit), as in 1.1; each line takes the next names in order. */
			$units = max( 1, (int) ( $l['units'] ?? 1 ) );
			$own   = $proxy && '' !== (string) ( $l['key'] ?? '' ) ? array_slice( $names, $next, $units ) : ( $proxy ? array() : $names );
			$next += $proxy && '' !== (string) ( $l['key'] ?? '' ) ? $units : 0;
			$lines[] = array_filter(
				array(
					'type_id'   => (int) $t['id'],
					'option'    => sanitize_key( (string) ( $l['key'] ?? '' ) ),
					'units'     => max( 1, (int) ( $l['units'] ?? 1 ) ),
					'amount'    => $a,
					'fixed'     => true,
					'cert'      => $cert,
					'name'      => (string) ( $contact['name'] ?? '' ),
					'for_other'     => (bool) $own,
					'ben_name'      => $own ? implode( ', ', $own ) : '',
					'beneficiaries' => $own ? array_values( $own ) : '',
					'proxy_consent' => $consent ? $consent : '',
					'monthly'       => $monthly,
					'form'          => $form,
				),
				static function ( $v ) {
					return '' !== $v && false !== $v;
				}
			) + array( 'option' => '' );
		}
		$rest = round( $amount - $sum, 2 );
		if ( $rest > 0.004 ) {
			/* Priced rows that are not options of the type (extras): one more line with the rest. */
			$lines[] = array( 'type_id' => (int) $t['id'], 'option' => '', 'units' => 1, 'amount' => $rest, 'fixed' => true, 'monthly' => $monthly, 'form' => $form );
		}
		if ( ! $lines || abs( $amount - array_sum( array_column( $lines, 'amount' ) ) ) > 0.004 ) {
			return new WP_Error( 'flxb_amount', __( '\\nThe amount could not be calculated. Please review your choices and try again.\\n', 'flx-donate' ) );
		}
		if ( $monthly && ! FLXB_WC_Cart::monthly_available() ) {
			return new WP_Error( 'flxb_monthly', __( '\\nMonthly donations are not available at the moment.\\n', 'flx-donate' ) );
		}
		return $lines;
	}

	public function create_payment( $amount, $currency, array $meta ) {
		if ( ! FLXB_WC::ready() ) {
			return FLXB_Payment_Result::failed( __( '\\nOnline payment is not available at the moment. Please try again later or contact us.\\n', 'flx-donate' ) );
		}
		if ( strtoupper( $currency ) !== strtoupper( get_woocommerce_currency() ) ) {
			return FLXB_Payment_Result::failed( __( '[[\'The currency of this form is not supported.\\n', 'flx-donate' ) );
		}
		$lines = self::lines( (float) $amount, $meta );
		if ( is_wp_error( $lines ) ) {
			return FLXB_Payment_Result::failed( $lines->get_error_message() );
		}
		$token = 'flxbwc_' . strtolower( wp_generate_password( 24, false, false ) );
		$rows  = is_array( FLXB_WC_FLXForm::$raw_rows ) ? FLXB_WC_FLXForm::$raw_rows : array();
		set_transient(
			FLXB_WC_FLXForm::key( $token ),
			array(
				'lines'   => $lines,
				'contact' => array_intersect_key( (array) ( $meta['contact'] ?? array() ), array_flip( array( 'name', 'email', 'phone' ) ) ),
				'form_id' => (int) ( $meta['form_id'] ?? 0 ),
				'track'   => '',
			),
			FLXB_WC_FLXForm::TTL
		);
		return FLXB_Payment_Result::redirect(
			$token,
			FLXB_WC_FLXForm::handoff_url( $token ),
			array(
				'record' => array(
					/* Always one-time here, also for monthly gifts: the monthly charges belong to the WooCommerce
					 * subscription (FLXB_WC_Recurring); a "subscription" row would be charged by the form job too. */
					'payment_type' => 'one-time',
					'status'       => 'pending',
					'meta'         => array(
						'handoff'  => $token,
						'page_url' => (string) ( $meta['page_url'] ?? '' ),
						'contact'  => array_intersect_key( (array) ( $meta['contact'] ?? array() ), array_flip( array( 'name', 'email', 'phone', 'consent_kvkk', 'consent_email', 'consent_sms' ) ) ),
						'rows'     => $rows,
						'monthly'  => 'month' === (string) ( $meta['method'] ?? '' ),
					),
				),
			)
		);
	}
}
