<?php declare( strict_types=1 );

namespace FluxWP\Blocks\Site_Styles;

use FluxWP\Blocks\Core\Dot;
use WP_Block_Type_Registry;

/**
 * Keeps the Flux site-level values when a user without `unfiltered_html`
 * saves Global Styles.
 *
 * For such a user core's `wp_filter_global_styles_post()` removes all of
 * `settings.custom` from the saved content. This reads `settings.custom.flxbk`
 * from the incoming content before core's filters run, and puts a sanitized
 * copy back after them: only supported blocks, only attributes the block has
 * (minus its content attributes), every value sanitized the way core sanitizes
 * block attributes for that user. A site value is thereby treated like the
 * same value saved on a block instance.
 *
 * When the user has `unfiltered_html`, core's filter doesn't run, the value is
 * never missing, and nothing changes.
 *
 */
final class Kses_Keeper {

	/**
	 * The supported blocks.
	 *
	 *
	 * @var Supported_Blocks
	 */
	private Supported_Blocks $blocks;

	/**
	 * The incoming `settings.custom.flxbk` value, per save filter.
	 *
	 *
	 * @var array<array-key, array<string, mixed>> Filter name => value.
	 */
	private array $incoming = [];

	/**
	 *
	 * @param Supported_Blocks $blocks The supported blocks.
	 */
	public function __construct( Supported_Blocks $blocks ) {
		$this->blocks = $blocks;
	}

	/**
	 * Remembers the incoming Flux values, before core's filters run.
	 *
	 *
	 * @param mixed $content The slashed post content.
	 *
	 * @return mixed The content, unchanged.
	 */
	public function capture( $content ) {
		unset( $this->incoming[ current_filter() ] );

		$data    = self::global_styles_data( $content );
		$flxbk = null === $data ? null : self::flxbk_values( $data );

		if ( null !== $flxbk ) {
			$this->incoming[ current_filter() ] = $flxbk;
		}

		return $content;
	}

	/**
	 * Puts back a sanitized copy of the Flux values when core's filters
	 * removed them.
	 *
	 *
	 * @param mixed $content The slashed post content, after core's filters.
	 *
	 * @return mixed The content, with the Flux values when they were removed.
	 */
	public function restore( $content ) {
		$incoming = $this->incoming[ current_filter() ] ?? null;
		unset( $this->incoming[ current_filter() ] );

		$data = self::global_styles_data( $content );

		if ( null === $incoming || null === $data || null !== self::flxbk_values( $data ) ) {
			return $content;
		}

		$kept = $this->sanitize( $incoming );

		if ( ! $kept ) {
			return $content;
		}

		$json = wp_json_encode(
			( new Dot( $data ) )->set( 'settings.custom.flxbk', $kept )->all(),
			JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
		);

		return false === $json ? $content : wp_slash( $json );
	}

	/**
	 *
	 * @param array<string, mixed> $flxbk The incoming `settings.custom.flxbk` value.
	 *
	 * @return array<string, array<string, mixed>> Supported block slug => sanitized attributes.
	 */
	private function sanitize( array $flxbk ): array {
		$kept = [];

		foreach ( $this->blocks->all() as $block_name => $block ) {
			$slug       = $block->get_slug();
			$values     = $flxbk[ $slug ] ?? null;
			$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $block_name );

			if ( ! is_array( $values ) || null === $block_type ) {
				continue;
			}

			$excluded = Supported_Blocks::excluded_attributes( $block );

			foreach ( $values as $attribute => $value ) {
				if ( ! is_string( $attribute ) || ! isset( $block_type->attributes[ $attribute ] ) || in_array( $attribute, $excluded, true ) ) {
					continue;
				}

				$kept[ $slug ][ $attribute ] = filter_block_kses_value( $value, 'post' );
			}
		}

		return $kept;
	}

	/**
	 *
	 * @param array<string, mixed> $data Decoded user Global Styles.
	 *
	 * @return array<string, mixed>|null The `settings.custom.flxbk` value, null when there is none.
	 */
	private static function flxbk_values( array $data ): ?array {
		$flxbk = ( new Dot( $data ) )->get( 'settings.custom.flxbk' );

		if ( ! is_array( $flxbk ) ) {
			return null;
		}

		return array_filter( $flxbk, 'is_string', ARRAY_FILTER_USE_KEY );
	}

	/**
	 *
	 * @param mixed $content Slashed post content.
	 *
	 * @return array<string, mixed>|null The decoded content when it is user Global Styles, null otherwise.
	 */
	private static function global_styles_data( $content ): ?array {
		if ( ! is_string( $content ) || ! str_contains( $content, 'isGlobalStylesUserThemeJSON' ) ) {
			return null;
		}

		$data = json_decode( wp_unslash( $content ), true );

		return is_array( $data ) && ! empty( $data['isGlobalStylesUserThemeJSON'] ) ? $data : null;
	}
}
