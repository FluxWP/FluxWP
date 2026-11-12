<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Schema\Validation\Values;

use FluxWP\Blocks\Design_Tokens\Schema\Validation\Contracts\Value_Validator;
use FluxWP\Blocks\Design_Tokens\Schema\Validation\Kind;
use FluxWP\Blocks\Design_Tokens\Schema\Vocabulary\Token_Type;
use FluxWP\Blocks\Design_Tokens\Schema\Validation\Validation_Error;

/**
 * Validates a color $value: an alias or a color literal (hex / function / keyword / named color).
 *
 */
final class Color_Value implements Value_Validator {

	/**
	 * Validate a color $value.
	 *
	 *
	 * @param mixed  $value The decoded $value.
	 * @param string $path  Dot-path to the value, for error reporting.
	 *
	 * @return Validation_Error[] Empty when valid.
	 */
	public function validate( $value, string $path ): array {
		return Kind::validate( Token_Type::get_type_color(), $value, $path );
	}
}
