<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Design_Tokens\Document;

/**
 * A single alias reference from a stored token to another token id.
 *
 */
final class Token_Reference {

	/**
	 * The reference kind. One of the KIND_* values.
	 *
	 *
	 * @var string
	 */
	public string $kind;

	/**
	 * The canonical dot-path of the token holding the reference.
	 *
	 *
	 * @var string
	 */
	public string $path;

	/**
	 * Whether the rename/delete cascade can automatically handle this reference.
	 *
	 *
	 * @var bool
	 */
	public bool $supported;

	/**
	 * Semantic layer override with a direct $value alias.
	 *
	 *
	 * @var string
	 */
	private const KIND_SEMANTIC_OVERRIDE = 'semantic_override';

	/**
	 * Alias found inside a composite $value sub-field (e.g. shadow.color).
	 *
	 *
	 * @var string
	 */
	private const KIND_COMPOSITE_FIELD = 'composite_field';

	/**
	 * Alias found inside $extensions.presets or $extensions.foundationPresets.
	 *
	 *
	 * @var string
	 */
	private const KIND_EXTENSION = 'extension';

	/**
	 * Primitive layer override with a direct $value alias to another primitive.
	 *
	 *
	 * @var string
	 */
	private const KIND_PRIMITIVE_OVERRIDE = 'primitive_override';

	/**
	 *
	 * @param string $kind      One of the KIND_* values.
	 * @param string $path      Canonical dot-path of the referencing token.
	 * @param bool   $supported Whether the cascade can handle this reference.
	 */
	public function __construct( string $kind, string $path, bool $supported ) {
		$this->kind      = $kind;
		$this->path      = $path;
		$this->supported = $supported;
	}

	/**
	 *
	 * @return string
	 */
	public static function get_kind_semantic_override(): string {
		return self::KIND_SEMANTIC_OVERRIDE;
	}

	/**
	 *
	 * @return string
	 */
	public static function get_kind_composite_field(): string {
		return self::KIND_COMPOSITE_FIELD;
	}

	/**
	 *
	 * @return string
	 */
	public static function get_kind_primitive_override(): string {
		return self::KIND_PRIMITIVE_OVERRIDE;
	}

	/**
	 *
	 * @return string
	 */
	public static function get_kind_extension(): string {
		return self::KIND_EXTENSION;
	}
}
