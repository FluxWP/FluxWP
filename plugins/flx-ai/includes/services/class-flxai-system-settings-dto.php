<?php
/**
 * Value objects of the per-system AI settings (fields a plugin declares with the Flux AI SDK).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** One field a plugin declared with flx_ai_settings() / flx_ai_feature(). */
final class FLXAI_System_Field {

	/** @param list<FLXAI_Option> $options Choices of a select field. */
	public function __construct(
		public readonly string $key,
		public readonly string $type,
		public readonly string $label,
		public readonly string $help,
		public readonly string $value,
		public readonly array $options,
		public readonly ?float $min,
		public readonly ?float $max,
	) {}
}

/** The AI settings page of one system. */
final class FLXAI_System_Settings {

	/** @param list<FLXAI_System_Field> $fields Declared fields. */
	public function __construct(
		public readonly string $source,
		public readonly string $label,
		public readonly string $description,
		public readonly bool $on,
		public readonly string $model,
		public readonly string $default_model,
		public readonly array $fields,
		public readonly bool $declared,
		public readonly int $tools_total,
		public readonly int $tools_on,
		public readonly string $sdk_version,
	) {}
}
