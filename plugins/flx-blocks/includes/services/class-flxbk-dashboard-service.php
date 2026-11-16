<?php
/**
 * Block Editor dashboard service: the block list (turned on / off), editor widths and the admin bar
 * setting. The classic dashboard (Flx_Blocks_Settings, admin-ajax and options.php) and GraphQL both use it.
 *
 * @package flx-blocks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Block Editor dashboard.
 */
final class FLXBK_Dashboard_Service {

	/** Option of the turned-off block names. */
	const OPT_OFF = 'fxt_blocks_unregistered_blocks';

	/** Option of the editor widths. */
	const OPT_WIDTH = 'fxt_blocks_editor_width';

	/** Option of the admin bar setting. */
	const OPT_ADMIN_BAR = 'flxbk_admin_bar_settings';

	/** Width modes. */
	const WIDTH_MODES = array( 'sidebar', 'nosidebar', 'fullwidth' );

	/**
	 * Capability of the dashboard (filter flxbk_admin_settings_capability, default edit_pages).
	 */
	public static function capability(): string {
		$cap = apply_filters( 'flxbk_admin_settings_capability', 'edit_pages' );
		return is_string( $cap ) && '' !== $cap ? $cap : 'edit_pages';
	}

	/**
	 * Whether the current user may use the dashboard.
	 */
	public static function can_manage(): bool {
		return current_user_can( self::capability() );
	}

	/**
	 * Blocks of the dashboard, in its order.
	 *
	 * @return list<FLXBK_Block_Type>
	 */
	public static function block_types(): array {
		$off = self::off();
		$out = array();
		$raw = class_exists( 'Flx_Blocks_Settings' ) ? Flx_Blocks_Settings::get_instance()->blocks_array() : array();
		foreach ( is_array( $raw ) ? $raw : array() as $b ) {
			if ( ! is_array( $b ) || empty( $b['slug'] ) || ! is_string( $b['slug'] ) ) {
				continue;
			}
			$out[] = new FLXBK_Block_Type(
				$b['slug'],
				isset( $b['name'] ) && is_string( $b['name'] ) ? $b['name'] : $b['slug'],
				isset( $b['desc'] ) && is_string( $b['desc'] ) ? wp_strip_all_tags( $b['desc'] ) : '',
				! in_array( $b['slug'], $off, true ),
				! empty( $b['link'] ) && is_string( $b['link'] ) ? $b['link'] : null,
				! empty( $b['linkText'] ) && is_string( $b['linkText'] ) ? $b['linkText'] : null
			);
		}
		return $out;
	}

	/**
	 * Turns a block on or off.
	 *
	 * @param string $slug   Block name.
	 * @param bool   $active On.
	 * @return FLXBK_Block_Type|WP_Error
	 */
	public static function set_active( string $slug, bool $active ) {
		$known = null;
		foreach ( self::block_types() as $t ) {
			if ( $t->slug === $slug ) {
				$known = $t;
			}
		}
		if ( null === $known ) {
			return new WP_Error( 'flxbk_block_unknown', __( 'Unknown block.', 'flx-blocks' ), array( 'status' => 400, 'field' => 'slug' ) );
		}
		$off = array_values( array_diff( self::off(), array( $slug ) ) );
		if ( ! $active ) {
			$off[] = $slug;
		}
		update_option( self::OPT_OFF, $off );
		return new FLXBK_Block_Type( $known->slug, $known->name, $known->description, $active, $known->link, $known->link_text );
	}

	/**
	 * Flips a block (the classic dashboard's button).
	 *
	 * @param string $slug Block name.
	 */
	public static function toggle( string $slug ): void {
		$off = self::off();
		if ( in_array( $slug, $off, true ) ) {
			$off = array_values( array_diff( $off, array( $slug ) ) );
		} else {
			$off[] = $slug;
		}
		update_option( self::OPT_OFF, $off );
	}

	/**
	 * Dashboard settings.
	 */
	public static function settings(): FLXBK_Settings {
		$bar  = self::option_array( self::OPT_ADMIN_BAR );
		$show = class_exists( 'Flx_Blocks_Settings' ) && (bool) apply_filters( 'flxbk_editor_width', Flx_Blocks_Settings::get_instance()->show_editor_width() );
		return new FLXBK_Settings(
			'true' === ( $bar['show_headers_in_admin_bar'] ?? 'false' ),
			(bool) apply_filters( 'flxbk_show_admin_bar', true ),
			$show ? self::editor_width() : null,
			admin_url( 'admin.php?page=flx-blocks-style-library' )
		);
	}

	/**
	 * Saves settings; a null value stays unchanged.
	 *
	 * @param bool|null                                                                                          $admin_bar Show block headers in the admin bar.
	 * @param array{enabled?:bool|null,small?:int|null,large?:int|null,postDefault?:string|null,pageDefault?:string|null}|null $width     Editor widths.
	 * @return FLXBK_Settings|WP_Error
	 */
	public static function update_settings( ?bool $admin_bar, ?array $width ) {
		if ( null !== $admin_bar ) {
			$bar                              = self::option_array( self::OPT_ADMIN_BAR );
			$bar['show_headers_in_admin_bar'] = $admin_bar ? 'true' : 'false';
			update_option( self::OPT_ADMIN_BAR, $bar );
		}
		if ( null !== $width ) {
			$w = self::option_array( self::OPT_WIDTH );
			foreach ( array( 'postDefault' => 'post_default', 'pageDefault' => 'page_default' ) as $in => $key ) {
				$v = $width[ $in ] ?? null;
				if ( null === $v ) {
					continue;
				}
				if ( ! in_array( $v, self::WIDTH_MODES, true ) ) {
					return new WP_Error( 'flxbk_width_mode', __( 'Choose Small, Large or Fullwidth.', 'flx-blocks' ), array( 'status' => 400, 'field' => $in ) );
				}
				$w[ $key ] = $v;
			}
			foreach ( array( 'small' => 'sidebar', 'large' => 'nosidebar' ) as $in => $key ) {
				$v = $width[ $in ] ?? null;
				if ( null === $v ) {
					continue;
				}
				if ( $v < 200 || $v > 4000 ) {
					return new WP_Error( 'flxbk_width', __( 'Enter a width between 200 and 4000 px.', 'flx-blocks' ), array( 'status' => 400, 'field' => $in ) );
				}
				$w[ $key ] = (string) $v;
			}
			if ( null !== ( $width['enabled'] ?? null ) ) {
				$w['enable_editor_width'] = $width['enabled'] ? 'true' : 'false';
			}
			update_option( self::OPT_WIDTH, $w );
		}
		return self::settings();
	}

	/**
	 * Editor widths as stored (defaults as the classic form shows them).
	 */
	private static function editor_width(): FLXBK_Editor_Width {
		$w     = self::option_array( self::OPT_WIDTH );
		$small = apply_filters( 'flxbk_default_small_editor_width', '750' );
		$large = apply_filters( 'flxbk_default_large_editor_width', '1140' );
		$post  = is_string( $w['post_default'] ?? null ) && in_array( $w['post_default'], self::WIDTH_MODES, true ) ? $w['post_default'] : 'sidebar';
		$page  = is_string( $w['page_default'] ?? null ) && in_array( $w['page_default'], self::WIDTH_MODES, true ) ? $w['page_default'] : 'sidebar';
		global $content_width;
		return new FLXBK_Editor_Width(
			'false' !== ( $w['enable_editor_width'] ?? 'true' ),
			is_numeric( $w['sidebar'] ?? null ) ? (int) $w['sidebar'] : ( is_numeric( $small ) ? (int) $small : 750 ),
			is_numeric( $w['nosidebar'] ?? null ) ? (int) $w['nosidebar'] : ( is_numeric( $large ) ? (int) $large : 1140 ),
			$post,
			$page,
			is_numeric( $content_width ) ? (int) $content_width : 0
		);
	}

	/**
	 * Turned-off block names.
	 *
	 * @return list<string>
	 */
	private static function off(): array {
		$off = get_option( self::OPT_OFF, array() );
		$out = array();
		foreach ( is_array( $off ) ? $off : array() as $v ) {
			if ( is_string( $v ) ) {
				$out[] = $v;
			}
		}
		return $out;
	}

	/**
	 * An array option.
	 *
	 * @param string $name Option.
	 * @return array<string,mixed>
	 */
	private static function option_array( string $name ): array {
		$v   = get_option( $name, array() );
		$out = array();
		foreach ( is_array( $v ) ? $v : array() as $k => $x ) {
			$out[ (string) $k ] = $x;
		}
		return $out;
	}
}
