<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Schema\Validation\Values;

use FluxWP\Blocks\Design_Tokens\Schema\Validation\Contracts\Value_Validator;
use FluxWP\Blocks\Design_Tokens\Schema\Validation\Kind;
use FluxWP\Blocks\Design_Tokens\Schema\Vocabulary\Token_Type;
use FluxWP\Blocks\Design_Tokens\Schema\Validation\Validation_Error;

/**
 * Validates a dimension $value: an alias or a length literal ("0", number+unit, or a CSS function).
 *
 * The $value stays a plain scalar even for a responsive / clamp token — that shape lives in the leaf's
 * $extensions (validated by Dtcg_Validator::validate_leaf_extensions), which reuses this validator to
 * check each per-breakpoint / clamp slot. So this validator only ever sees the flat base value and needs
 * no responsive branch of its own.
 *
 */
final class Dimension_Value implements Value_Validator {

	/**
	 * Validate a dimension $value.
	 *
	 *
	 * @param mixed  $value The decoded $value.
	 * @param string $path  Dot-path to the value, for error reporting.
	 *
	 * @return Validation_Error[] Empty when valid.
	 */
	public function validate( $value, string $path ): array {
		return Kind::validate( Token_Type::get_type_dimension(), $value, $path );
	}
}
