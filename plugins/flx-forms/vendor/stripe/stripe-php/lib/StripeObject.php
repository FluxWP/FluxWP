<?php
namespace Stripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read/write bag of API values with property and array access (shim).
 * Nested hashes become StripeObject instances, lists stay PHP lists.
 */
class StripeObject implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable {

	/** @var array */
	protected $_values = array();

	public function __construct( $values = array() ) {
		if ( is_string( $values ) ) {
			$values = array( 'id' => $values );
		}
		foreach ( (array) $values as $k => $v ) {
			$this->_values[ $k ] = self::convertValue( $v );
		}
	}

	public static function constructFrom( $values, $opts = null ) {
		return new static( is_array( $values ) ? $values : array() );
	}

	protected static function convertValue( $v ) {
		if ( is_array( $v ) ) {
			if ( array() === $v ) {
				return array();
			}
			if ( array_keys( $v ) === range( 0, count( $v ) - 1 ) ) {
				return array_map( array( __CLASS__, 'convertValue' ), $v );
			}
			return new StripeObject( $v );
		}
		return $v;
	}

	public function __get( $k ) {
		return array_key_exists( $k, $this->_values ) ? $this->_values[ $k ] : null;
	}

	public function __set( $k, $v ) {
		$this->_values[ $k ] = self::convertValue( $v );
	}

	public function __isset( $k ) {
		return isset( $this->_values[ $k ] );
	}

	public function __unset( $k ) {
		unset( $this->_values[ $k ] );
	}

	#[\ReturnTypeWillChange]
	public function offsetExists( $k ) {
		return array_key_exists( $k, $this->_values );
	}

	#[\ReturnTypeWillChange]
	public function offsetGet( $k ) {
		return $this->__get( $k );
	}

	#[\ReturnTypeWillChange]
	public function offsetSet( $k, $v ) {
		$this->__set( $k, $v );
	}

	#[\ReturnTypeWillChange]
	public function offsetUnset( $k ) {
		unset( $this->_values[ $k ] );
	}

	#[\ReturnTypeWillChange]
	public function count() {
		return count( $this->_values );
	}

	#[\ReturnTypeWillChange]
	public function getIterator() {
		return new \ArrayIterator( $this->_values );
	}

	public function keys() {
		return array_keys( $this->_values );
	}

	public function toArray() {
		$out = array();
		foreach ( $this->_values as $k => $v ) {
			$out[ $k ] = self::unconvert( $v );
		}
		return $out;
	}

	protected static function unconvert( $v ) {
		if ( $v instanceof StripeObject ) {
			return $v->toArray();
		}
		if ( is_array( $v ) ) {
			return array_map( array( __CLASS__, 'unconvert' ), $v );
		}
		return $v;
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		return $this->toArray();
	}

	public function toJSON() {
		return json_encode( $this->toArray() );
	}

	public function __toString() {
		return (string) $this->toJSON();
	}
}
