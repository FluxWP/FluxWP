<?php
namespace Stripe\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generic CRUD service for one Stripe resource collection (shim).
 */
class ResourceService {

	/** @var \Stripe\StripeClient */
	protected $client;
	/** @var string e.g. /v1/payment_intents */
	protected $path;

	public function __construct( $client, $path ) {
		$this->client = $client;
		$this->path   = (string) $path;
	}

	protected function item( $id ) {
		$id = (string) ( is_object( $id ) && isset( $id->id ) ? $id->id : $id );
		if ( '' === trim( $id ) ) {
			throw new \Stripe\Exception\InvalidRequestException( 'The resource ID cannot be null or whitespace.' );
		}
		return $this->path . '/' . rawurlencode( $id );
	}

	public function create( $params = null, $opts = null ) {
		return $this->client->request( 'post', $this->path, $params, $opts );
	}

	public function retrieve( $id, $params = null, $opts = null ) {
		return $this->client->request( 'get', $this->item( $id ), $params, $opts );
	}

	public function update( $id, $params = null, $opts = null ) {
		return $this->client->request( 'post', $this->item( $id ), $params, $opts );
	}

	public function all( $params = null, $opts = null ) {
		return $this->client->request( 'get', $this->path, $params, $opts );
	}

	public function cancel( $id, $params = null, $opts = null ) {
		/* Subscriptions are cancelled with DELETE, intents with POST /cancel. */
		if ( '/v1/subscriptions' === $this->path ) {
			return $this->client->request( 'delete', $this->item( $id ), $params, $opts );
		}
		return $this->client->request( 'post', $this->item( $id ) . '/cancel', $params, $opts );
	}

	public function confirm( $id, $params = null, $opts = null ) {
		return $this->client->request( 'post', $this->item( $id ) . '/confirm', $params, $opts );
	}
}
