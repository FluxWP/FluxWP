<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Document;

/**
 * A single invariant violation detected by User_Primitive_Document_Validator.
 *
 */
final class User_Primitive_Validation_Error {

	/**
	 *
	 * @var string
	 */
	private string $id;

	/**
	 *
	 * @var string
	 */
	private string $message;

	/**
	 *
	 * @param string $id      The canonical dot-path id that is corrupt.
	 * @param string $message Human-readable description of the violation.
	 */
	public function __construct( string $id, string $message ) {
		$this->id      = $id;
		$this->message = $message;
	}

	/**
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 *
	 * @return string
	 */
	public function get_message(): string {
		return $this->message;
	}
}
