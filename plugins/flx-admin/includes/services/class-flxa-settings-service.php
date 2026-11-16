<?php
/**
 * Admin theme settings (Settings › Admin theme: General, Appearance, Dashboard, App): the one place that
 * reads and changes the `flxa_settings` option. The classic page (admin-post handler), GraphQL and
 * Flux AI call this. Permission checks stay with the caller (manage_options).
 *
 * Errors are WP_Error with `graphql_code` INVALID_INPUT and the input `field`.
 *
 * @package flx-admin
 */

defined( 'ABSPATH' ) || exit;

/**
 * @phpstan-type FlxaColors array{bg: string, side: string, surface: string, text: string}
 * @phpstan-type FlxaTheme array{radius: string, font: string, font_custom: string, font_size: int, density: string, light: FlxaColors, dark: FlxaColors}
 * @phpstan-type FlxaSettings array{
 *   enabled: bool, brand: string, subtitle: string, logo_id: int, logo_url: string, logo_fallback_url: string,
 *   accent: string, mode: string, dark_exclude: string, line_icons: bool, logo_style: string,
 *   bottom_bar: bool, app_rail: bool, app_switcher: bool, fast_nav: bool, palette: bool, login: bool, heartbeat: int,
 *   dash_header: bool, dash_stats: list<string>, quick_links: string,
 *   pwa: bool, pwa_name: string, pwa_short: string, theme: FlxaTheme
 * }
 * @phpstan-type FlxaSettingsMeta array{
 *   site_name: string, default_accent: string, pwa_possible: bool, pwa_icon_url: string, pwa_default_name: string,
 *   pwa_default_short: string, system_url: string|null, color_defaults: array{light: FlxaColors, dark: FlxaColors},
 *   dash_stat_choices: list<array{key: string, label: string}>
 * }
 */
final class FLXA_Settings_Service {

	public const CACHE_TAG = 'flx-admin:settings';

	/** Choices of the summary tiles on the dashboard (key => label). */
	public const DASH_STATS = array( 'donations_today', 'donations_month', 'pending', 'whatsapp', 'tasks', 'system' );

	/**
	 * Every setting, as the screens show it.
	 *
	 * @return FlxaSettings
	 */
	public static function get(): array {
		$s     = flxa_settings( true );
		$logo  = absint( FLXA_Util::int( $s['logo_id'] ) );
		$stats = array();
		foreach ( (array) $s['dash_stats'] as $k ) {
			if ( is_scalar( $k ) ) {
				$stats[] = (string) $k;
			}
		}
		$theme = self::theme();
		$mode  = FLXA_Util::str( $s['mode'], 'auto' );
		$accent = sanitize_hex_color( FLXA_Util::str( $s['accent'] ) );
		return array(
			'enabled'           => (bool) $s['enabled'],
			'brand'             => FLXA_Util::str( $s['brand'] ),
			'subtitle'          => FLXA_Util::str( $s['subtitle'] ),
			'logo_id'           => $logo,
			'logo_url'          => FLXA_Util::str( flxa_logo_url( 96 ) ),
			'logo_fallback_url' => FLXA_Util::str( flxa_logo_fallback_url( 96 ) ),
			'accent'            => is_string( $accent ) && '' !== $accent ? $accent : FLXA_Util::str( flxa_theme_primary() ),
			'mode'              => in_array( $mode, array( 'auto', 'light', 'dark' ), true ) ? $mode : 'auto',
			'dark_exclude'      => FLXA_Util::str( $s['dark_exclude'] ),
			'line_icons'        => (bool) $s['line_icons'],
			'logo_style'        => 'pastel' === $s['logo_style'] ? 'pastel' : 'gradient',
			'bottom_bar'        => (bool) $s['bottom_bar'],
			'app_rail'          => (bool) $s['app_rail'],
			'app_switcher'      => (bool) $s['app_switcher'],
			'fast_nav'          => (bool) $s['fast_nav'],
			'palette'           => (bool) $s['palette'],
			'login'             => (bool) $s['login'],
			'heartbeat'         => FLXA_Util::int( $s['heartbeat'], 60 ),
			'dash_header'       => (bool) $s['dash_header'],
			'dash_stats'        => $stats,
			'quick_links'       => FLXA_Util::str( $s['quick_links'] ),
			'pwa'               => (bool) $s['pwa'],
			'pwa_name'          => FLXA_Util::str( $s['pwa_name'] ),
			'pwa_short'         => FLXA_Util::str( $s['pwa_short'] ),
			'theme'             => $theme,
		);
	}

	/**
	 * Theme tokens: colors are stored empty when they are the default, here every color has its value.
	 *
	 * @return FlxaTheme
	 */
	public static function theme(): array {
		$t   = flxa_theme();
		$def = self::color_defaults();
		$out = array(
			'radius'      => FLXA_Util::str( $t['radius'] ),
			'font'        => FLXA_Util::str( $t['font'] ),
			'font_custom' => FLXA_Util::str( $t['font_custom'] ),
			'font_size'   => FLXA_Util::int( $t['font_size'], 13 ),
			'density'     => FLXA_Util::str( $t['density'] ),
			'light'       => $def['light'],
			'dark'        => $def['dark'],
		);
		foreach ( array( 'light', 'dark' ) as $m ) {
			$set = FLXA_Util::arr( $t[ $m ] );
			foreach ( array( 'bg', 'side', 'surface', 'text' ) as $k ) {
				$v = FLXA_Util::str( $set[ $k ] ?? '' );
				if ( '' !== $v ) {
					$out[ $m ][ $k ] = $v;
				}
			}
		}
		return $out;
	}

	/**
	 * Default colors (empty setting = these).
	 *
	 * @return array{light: FlxaColors, dark: FlxaColors}
	 */
	public static function color_defaults(): array {
		/** @var array{light: FlxaColors, dark: FlxaColors} $d */
		$d = flxa_theme_color_defaults();
		return $d;
	}

	/**
	 * Values around the settings (labels, links, availability).
	 *
	 * @return FlxaSettingsMeta
	 */
	public static function meta(): array {
		$icons = flxa_pwa_icons();
		$icon  = isset( $icons[0]['src'] ) ? FLXA_Util::str( $icons[0]['src'] ) : '';
		return array(
			'site_name'         => FLXA_Util::str( get_bloginfo( 'name' ) ),
			'default_accent'    => FLXA_Util::str( flxa_theme_primary() ),
			'pwa_possible'      => (bool) flxa_pwa_possible(),
			'pwa_icon_url'      => $icon,
			'pwa_default_name'  => FLXA_Util::str( flxa_brand() ),
			'pwa_default_short' => FLXA_Util::str( flxa_pwa_short() ),
			'system_url'        => function_exists( 'flxp_system_checks_url' ) ? FLXA_Util::str( flxp_system_checks_url() ) : null,
			'color_defaults'    => self::color_defaults(),
			'dash_stat_choices' => self::dash_stat_choices(),
		);
	}

	/**
	 * Summary tile choices with their names.
	 *
	 * @return list<array{key: string, label: string}>
	 */
	public static function dash_stat_choices(): array {
		$labels = array(
			'donations_today' => __( 'Donations today', 'flx-admin' ),
			'donations_month' => __( 'This month', 'flx-admin' ),
			'pending'         => __( 'Donation awaiting approval', 'flx-admin' ),
			'whatsapp'        => __( 'Unread WhatsApp', 'flx-admin' ),
			'tasks'           => __( 'My open tasks', 'flx-admin' ),
			'system'          => __( 'System check', 'flx-admin' ),
		);
		$out    = array();
		foreach ( self::DASH_STATS as $k ) {
			$out[] = array(
				'key'   => $k,
				'label' => $labels[ $k ],
			);
		}
		return $out;
	}

	/**
	 * Changes the given settings; the ones left out stay as they are.
	 *
	 * Keys: enabled, brand, subtitle, logo_id (0 = remove), accent ('' = theme color), mode, dark_exclude,
	 * line_icons, logo_style, bottom_bar, app_rail, app_switcher, fast_nav, palette, login, heartbeat,
	 * dash_header, dash_stats, quick_links, pwa, pwa_name, pwa_short, theme (partial: radius, font,
	 * font_custom, font_size, density, light{…}, dark{…}).
	 *
	 * @param array<string,mixed> $patch  Changes.
	 * @param bool                $strict Invalid values are errors (GraphQL); false = replaced by the default (classic form).
	 * @return FlxaSettings|WP_Error
	 */
	public static function update( array $patch, bool $strict = true ) {
		$old = get_option( 'flxa_settings', array() );
		$s   = is_array( $old ) ? $old : array();
		$err = new WP_Error();

		foreach ( array( 'enabled', 'bottom_bar', 'app_rail', 'app_switcher', 'fast_nav', 'palette', 'login', 'line_icons', 'dash_header', 'pwa' ) as $k ) {
			if ( array_key_exists( $k, $patch ) && null !== $patch[ $k ] ) {
				$s[ $k ] = empty( $patch[ $k ] ) ? 0 : 1;
			}
		}
		foreach ( array( 'brand', 'subtitle', 'pwa_name' ) as $k ) {
			if ( array_key_exists( $k, $patch ) && null !== $patch[ $k ] ) {
				$s[ $k ] = sanitize_text_field( FLXA_Util::str( $patch[ $k ] ) );
			}
		}
		if ( array_key_exists( 'pwa_short', $patch ) && null !== $patch['pwa_short'] ) {
			$s['pwa_short'] = mb_substr( sanitize_text_field( FLXA_Util::str( $patch['pwa_short'] ) ), 0, 20 );
		}
		if ( array_key_exists( 'logo_id', $patch ) && null !== $patch['logo_id'] ) {
			$id = absint( FLXA_Util::int( $patch['logo_id'] ) );
			if ( $id && ! wp_attachment_is_image( $id ) ) {
				if ( $strict ) {
					$err->add( 'flxa_logo', __( 'The logo must be an image from the media library.', 'flx-admin' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'logoId' ) );
				}
			} else {
				$s['logo_id'] = $id;
			}
		}
		if ( array_key_exists( 'heartbeat', $patch ) && null !== $patch['heartbeat'] ) {
			$hb = FLXA_Util::int( $patch['heartbeat'], 60 );
			if ( in_array( $hb, array( 30, 60, 120 ), true ) ) {
				$s['heartbeat'] = $hb;
			} elseif ( $strict ) {
				$err->add( 'flxa_heartbeat', __( 'Choose 30, 60 or 120 seconds.', 'flx-admin' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'heartbeat' ) );
			} else {
				$s['heartbeat'] = 60;
			}
		}
		if ( array_key_exists( 'logo_style', $patch ) && null !== $patch['logo_style'] ) {
			$s['logo_style'] = 'pastel' === $patch['logo_style'] ? 'pastel' : 'gradient';
		}
		if ( array_key_exists( 'mode', $patch ) && null !== $patch['mode'] ) {
			$m         = FLXA_Util::str( $patch['mode'] );
			$s['mode'] = in_array( $m, array( 'auto', 'light', 'dark' ), true ) ? $m : 'auto';
		}
		if ( array_key_exists( 'accent', $patch ) && null !== $patch['accent'] ) {
			$raw = trim( FLXA_Util::str( $patch['accent'] ) );
			$c   = sanitize_hex_color( $raw );
			if ( '' !== $raw && ! $c && $strict ) {
				$err->add( 'flxa_accent', __( 'Enter a color like #1F2937.', 'flx-admin' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'accent' ) );
			} else {
				// Same as the theme color: not stored, so the panel follows when the theme or preset color changes.
				$s['accent'] = ( $c && strtolower( $c ) !== strtolower( FLXA_Util::str( flxa_theme_primary() ) ) ) ? $c : '';
			}
		}
		if ( array_key_exists( 'dark_exclude', $patch ) && null !== $patch['dark_exclude'] ) {
			$lines             = preg_split( '/\r\n|\r|\n/', FLXA_Util::str( $patch['dark_exclude'] ) );
			$s['dark_exclude'] = implode( "\n", array_filter( array_map( 'sanitize_text_field', is_array( $lines ) ? $lines : array() ) ) );
		}
		if ( array_key_exists( 'dash_stats', $patch ) && null !== $patch['dash_stats'] ) {
			$want = array();
			foreach ( is_array( $patch['dash_stats'] ) ? $patch['dash_stats'] : array() as $k ) {
				$want[] = FLXA_Util::str( $k );
			}
			$s['dash_stats'] = array_values( array_intersect( self::DASH_STATS, $want ) );
		}
		if ( array_key_exists( 'quick_links', $patch ) && null !== $patch['quick_links'] ) {
			$s['quick_links'] = self::clean_quick_links( FLXA_Util::str( $patch['quick_links'] ) );
		}
		if ( array_key_exists( 'theme', $patch ) && is_array( $patch['theme'] ) ) {
			$theme = self::merge_theme( $patch['theme'], $strict, $err );
			if ( $theme ) {
				$s['theme'] = $theme;
			}
		}

		if ( $err->has_errors() ) {
			return $err;
		}
		update_option( 'flxa_settings', $s, true );
		delete_transient( 'flxa_syscheck' );
		FLXA_Util::purge( array( self::CACHE_TAG ) );
		return self::get();
	}

	/**
	 * Theme patch merged over the stored theme, then cleaned.
	 *
	 * @param array<mixed> $patch  Partial theme.
	 * @param bool         $strict Report invalid colors.
	 * @param WP_Error     $err    Error collector.
	 * @return array<string,mixed>
	 */
	private static function merge_theme( array $patch, bool $strict, WP_Error $err ): array {
		$cur = flxa_theme();
		$in  = $cur;
		foreach ( array( 'radius', 'font', 'font_custom', 'font_size', 'density' ) as $k ) {
			if ( array_key_exists( $k, $patch ) && null !== $patch[ $k ] ) {
				$in[ $k ] = $patch[ $k ];
			}
		}
		foreach ( array( 'light', 'dark' ) as $m ) {
			$set = FLXA_Util::arr( $patch[ $m ] ?? null );
			$old = FLXA_Util::arr( $in[ $m ] );
			foreach ( array( 'bg', 'side', 'surface', 'text' ) as $k ) {
				if ( ! array_key_exists( $k, $set ) || null === $set[ $k ] ) {
					continue;
				}
				$raw = trim( FLXA_Util::str( $set[ $k ] ) );
				if ( '' !== $raw && ! sanitize_hex_color( $raw ) && $strict ) {
					$err->add( 'flxa_color', __( 'Enter a color like #1F2937.', 'flx-admin' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'theme.' . $m . '.' . $k ) );
				}
				$old[ $k ] = $raw;
			}
			$in[ $m ] = $old;
		}
		return flxa_theme_sanitize( $in );
	}

	/**
	 * "Label|Address" lines: addresses are relative to wp-admin or full http(s) URLs.
	 */
	public static function clean_quick_links( string $text ): string {
		$lines = array();
		$split = preg_split( '/\r\n|\r|\n/', $text );
		foreach ( is_array( $split ) ? $split : array() as $line ) {
			$p = array_map( 'trim', explode( '|', $line, 2 ) );
			if ( 2 === count( $p ) && '' !== $p[0] && '' !== $p[1] ) {
				$url     = preg_match( '#^https?://#i', $p[1] ) ? esc_url_raw( $p[1] ) : ltrim( (string) preg_replace( '#[^A-Za-z0-9._\-/?=&%\#]#', '', $p[1] ), '/' );
				$lines[] = sanitize_text_field( $p[0] ) . '|' . $url;
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * Takes an uploaded image over as the admin logo (media library item).
	 *
	 * @param array<string,mixed> $file flx_gql_upload_take() result.
	 * @return int|WP_Error Attachment ID.
	 */
	public static function logo_from_upload( array $file ) {
		$path = FLXA_Util::str( $file['path'] ?? '' );
		$name = FLXA_Util::str( $file['name'] ?? 'logo.png', 'logo.png' );
		if ( '' === $path || ! is_file( $path ) ) {
			return new WP_Error( 'flxa_logo_file', __( 'The uploaded file was not found. Upload it again.', 'flx-admin' ), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'logoUploadId' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$tmp = wp_tempnam( $name );
		if ( ! copy( $path, $tmp ) ) {
			return new WP_Error( 'flxa_logo_copy', __( 'The file could not be saved on the server.', 'flx-admin' ), array( 'graphql_code' => 'INTERNAL' ) );
		}
		$id = media_handle_sideload(
			array(
				'name'     => $name,
				'tmp_name' => $tmp,
			),
			0
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'flxa_logo_sideload', $id->get_error_message(), array( 'graphql_code' => 'INVALID_INPUT', 'field' => 'logoUploadId' ) );
		}
		wp_delete_file( $path );
		return $id;
	}
}
