<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Schema\Validation\Values;

use FluxWP\Blocks\Design_Tokens\Schema\Vocabulary\Token_Type;

/**
 * Validates a shadow $value: an alias, or an object of { color, offsetX, offsetY, blur, spread } where
 * each sub-field is itself an alias or its literal kind (a color, the rest dimensions).
 *
 */
final class Shadow_Value extends Composite_Value {

	/**
	 * @inheritDoc
	 *
	 */
	protected function type(): string {
		return Token_Type::get_type_shadow();
	}
}
