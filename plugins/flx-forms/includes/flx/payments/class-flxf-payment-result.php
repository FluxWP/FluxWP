<?php
/**
 * Flux Forms: normalized result of a provider call (create / verify / refund).
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 *
 * @package FLX_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXF_Payment_Result {

	/** Money is taken and verified. */
	const COMPLETED = 'completed';
	/** Created, waiting for the provider (webhook / later verify). */
	const PENDING = 'pending';
	/** The browser must finish it (3-D Secure, card element...): see $client. */
	const REQUIRES_ACTION = 'requires_action';
	/** The browser must go to $client['redirect_url'] (hosted payment page). */
	const REDIRECT = 'redirect';
	/** Refused or error: see $message. */
	const FAILED = 'failed';

	/** @var string One of the constants above. */
	public $status = self::FAILED;
	/** @var string Provider transaction / payment id (stored as transaction_id). */
	public $reference = '';
	/** @var string Message for the visitor (failures). */
	public $message = '';
	/** @var array Data handed to the provider's front script (client secret, redirect URL...). Never secrets. */
	public $client = array();
	/** @var array Extra emsfb_pay_ columns: payment_type, capture_id, subscription_id, interval_unit, meta... */
	public $record = array();

	public function __construct( $status, $reference = '', array $args = array() ) {
		$this->status    = in_array( $status, array( self::COMPLETED, self::PENDING, self::REQUIRES_ACTION, self::REDIRECT, self::FAILED ), true ) ? $status : self::FAILED;
		$this->reference = (string) $reference;
		foreach ( array( 'message', 'client', 'record' ) as $k ) {
			if ( isset( $args[ $k ] ) ) {
				$this->$k = 'message' === $k ? (string) $args[ $k ] : (array) $args[ $k ];
			}
		}
	}

	public static function completed( $reference, array $args = array() ) {
		return new self( self::COMPLETED, $reference, $args );
	}

	public static function pending( $reference, array $args = array() ) {
		return new self( self::PENDING, $reference, $args );
	}

	public static function requires_action( $reference, array $client, array $args = array() ) {
		$args['client'] = $client;
		return new self( self::REQUIRES_ACTION, $reference, $args );
	}

	public static function redirect( $reference, $url, array $args = array() ) {
		$args['client'] = array_merge( isset( $args['client'] ) ? (array) $args['client'] : array(), array( 'redirect_url' => esc_url_raw( $url ) ) );
		return new self( self::REDIRECT, $reference, $args );
	}

	public static function failed( $message, array $args = array() ) {
		$args['message'] = (string) $message;
		return new self( self::FAILED, '', $args );
	}

	public function is_ok() {
		return self::FAILED !== $this->status;
	}

	public function is_paid() {
		return self::COMPLETED === $this->status;
	}

	/** Shape sent to the browser. */
	public function to_array() {
		return array(
			'status'    => $this->status,
			'reference' => $this->reference,
			'message'   => $this->message,
			'client'    => $this->client,
		);
	}
}
