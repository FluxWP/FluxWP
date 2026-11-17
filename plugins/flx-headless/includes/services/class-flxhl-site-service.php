<?php
/**
 * flxSite: the frame a front end draws every page in: name, logo, icon, languages, menus (classic menu
 * locations and the navigation blocks of the header / footer), palette, contact details, currencies, legal
 * pages, public captcha settings. Addresses are pointed at the front end. Other packages change it with the
 * `flx_headless_site` filter.
 *
 * @package FLX_Headless
 */

defined( 'ABSPATH' ) || exit;

final class FLXHL_Site_Service {

	public const MENU_TAG = 'flx-headless:menus';

	public static function site( ?string $lang = null, ?string $frontend = null ): FLXHL_Site {
		$fe       = FLXHL_Frontend_Service::current( $frontend );
		$switched = self::switch_language( $lang );
		try {
			$data = self::build( $fe );
		} finally {
			if ( $switched && function_exists( 'flxl_restore_language' ) ) {
				flxl_restore_language();
			}
		}
		return $data;
	}

	/** Switches Flux Lang to $lang for the call; true when it did. */
	public static function switch_language( ?string $lang ): bool {
		if ( null === $lang || '' === $lang || ! function_exists( 'flxl_is_language' ) || ! flxl_is_language( $lang ) || ! function_exists( 'flxl_switch_language' ) || ! function_exists( 'flxl_current_language' ) ) {
			return false;
		}
		if ( flxl_current_language() === $lang ) {
			return false;
		}
		flxl_switch_language( $lang );
		return true;
	}

	private static function build( ?FLXHL_Frontend $fe ): FLXHL_Site {
		$code = function_exists( 'flxl_current_language' ) ? flxhl_str( flxl_current_language() ) : strtolower( substr( determine_locale(), 0, 2 ) );
		$data = array(
			'name'        => html_entity_decode( flxhl_str( get_bloginfo( 'name' ) ), ENT_QUOTES, 'UTF-8' ),
			'description' => html_entity_decode( flxhl_str( get_bloginfo( 'description' ) ), ENT_QUOTES, 'UTF-8' ),
			'url'         => $fe ? $fe->url : flxhl_home(),
			'cms_url'     => flxhl_home(),
			'graphql_url' => function_exists( 'graphql_get_endpoint_url' ) ? flxhl_str( graphql_get_endpoint_url() ) : home_url( '/graphql' ),
			'language'    => $code,
			'locale'      => str_replace( '_', '-', determine_locale() ),
			'languages'   => self::languages( $fe ),
			'logo'        => self::logo(),
			'icon'        => self::icon(),
			'menus'       => self::menus( $fe ),
			'palette'     => self::palette(),
			'contact'     => self::contact(),
			'currencies'  => self::currencies(),
			'legal'       => self::legal( $fe ),
			'captcha'     => self::captcha(),
			'social'      => self::social(),
			'sitemap_url' => home_url( '/wp-sitemap.xml' ) . ( $fe ? '?flxhl=' . rawurlencode( $fe->id ) : '' ),
			'search_path' => '/?s=',
			'cache_tags'  => array( FLXHL_Frontend_Service::CACHE_TAG, self::MENU_TAG ),
			'frontend_id' => $fe ? $fe->id : null,
		);
		/**
		 * Site frame a front end gets. Packages add currencies, legal pages, contact details or tags here.
		 *
		 * @param array<string,mixed> $data Site (see FLXHL_Site::from()).
		 * @param FLXHL_Frontend|null $fe   Front end.
		 */
		$data = apply_filters( 'flx_headless_site', $data, $fe );
		return FLXHL_Site::from( flxhl_arr( $data ) );
	}

	/** @return list<array<string,mixed>> */
	private static function languages( ?FLXHL_Frontend $fe ): array {
		$base = $fe ? $fe->url : flxhl_home();
		if ( ! class_exists( 'FLXL_Language_Service' ) ) {
			$loc = determine_locale();
			return array(
				array(
					'code'     => strtolower( substr( $loc, 0, 2 ) ),
					'locale'   => $loc,
					'name'     => $loc,
					'hreflang' => strtolower( str_replace( '_', '-', $loc ) ),
					'path'     => '/',
					'url'      => $base . '/',
					'default'  => true,
				),
			);
		}
		$out = array();
		foreach ( FLXL_Language_Service::languages() as $l ) {
			if ( ! $l->active || ( $fe && $fe->languages && ! in_array( $l->code, $fe->languages, true ) ) ) {
				continue;
			}
			$out[] = array(
				'code'     => $l->code,
				'locale'   => $l->locale,
				'name'     => $l->name,
				'hreflang' => $l->hreflang,
				'path'     => $l->address,
				'url'      => $base . $l->address,
				'default'  => $l->default,
			);
		}
		return $out;
	}

	/** @return array<string,mixed> */
	private static function logo(): array {
		$id = flxhl_int( get_theme_mod( 'custom_logo' ) );
		if ( ! $id ) {
			$id = flxhl_int( get_option( 'site_logo' ) );
		}
		if ( ! $id ) {
			return array();
		}
		$src = wp_get_attachment_image_src( $id, 'full' );
		if ( ! is_array( $src ) ) {
			return array();
		}
		return array(
			'url'    => flxhl_str( $src[0] ),
			'width'  => flxhl_int( $src[1] ),
			'height' => flxhl_int( $src[2] ),
			'alt'    => flxhl_str( get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
		);
	}

	private static function icon(): ?string {
		$u = get_site_icon_url( 512 );
		return '' === $u ? null : $u;
	}

	/** @return list<array{slug: string, name: string, color: string}> */
	private static function palette(): array {
		$p   = flxhl_arr( wp_get_global_settings( array( 'color', 'palette' ) ) );
		$out = array();
		foreach ( array( 'theme', 'custom' ) as $origin ) {
			foreach ( flxhl_arr( $p[ $origin ] ?? array() ) as $c ) {
				$c    = flxhl_arr( $c );
				$slug = sanitize_key( flxhl_str( $c['slug'] ?? '' ) );
				if ( '' !== $slug ) {
					$out[ $slug ] = array( 'slug' => $slug, 'name' => flxhl_str( $c['name'] ?? $slug ), 'color' => flxhl_str( $c['color'] ?? '' ) );
				}
			}
		}
		return array_values( $out );
	}

	/** @return array<string,string> */
	private static function contact(): array {
		$prof = static fn( string $k ): string => function_exists( 'flx_profile' ) ? trim( flxhl_str( flx_profile( $k, '' ) ) ) : '';
		if ( function_exists( 'flxseo_org_contact' ) ) {
			$c    = flxhl_arr( flxseo_org_contact() );
			$addr = flxhl_arr( $c['address'] ?? array() );
			unset( $addr['@type'] );
			return array(
				'email'    => flxhl_str( $c['email'] ?? '' ),
				'phone'    => flxhl_str( $c['phone'] ?? '' ),
				'whatsapp' => $prof( 'whatsapp' ),
				'address'  => implode( ', ', array_filter( array_map( 'flxhl_str', $addr ) ) ),
			);
		}
		$email = $prof( 'email' );
		return array(
			'email'    => is_email( $email ) ? $email : '',
			'phone'    => $prof( 'phone' ),
			'whatsapp' => $prof( 'whatsapp' ),
			'address'  => (string) preg_replace( '/\s+/u', ' ', $prof( 'address' ) ),
		);
	}

	/** @return list<string> */
	private static function social(): array {
		$s   = function_exists( 'flx_profile' ) ? flxhl_arr( flx_profile( 'social', array() ) ) : array();
		$out = array();
		foreach ( $s as $url ) {
			$url = esc_url_raw( flxhl_str( $url ) );
			if ( '' !== $url && preg_match( '#^https?://#', $url ) ) {
				$out[] = $url;
			}
		}
		return $out;
	}

	/** @return list<array<string,mixed>> */
	private static function currencies(): array {
		if ( ! function_exists( 'get_woocommerce_currency' ) ) {
			return array();
		}
		$code = flxhl_str( get_woocommerce_currency() );
		return array(
			array(
				'code'               => $code,
				'symbol'             => html_entity_decode( flxhl_str( get_woocommerce_currency_symbol( $code ) ), ENT_QUOTES, 'UTF-8' ),
				'decimals'           => function_exists( 'wc_get_price_decimals' ) ? flxhl_int( wc_get_price_decimals() ) : 2,
				'decimal_separator'  => function_exists( 'wc_get_price_decimal_separator' ) ? flxhl_str( wc_get_price_decimal_separator() ) : '.',
				'thousand_separator' => function_exists( 'wc_get_price_thousand_separator' ) ? flxhl_str( wc_get_price_thousand_separator() ) : ',',
				'position'           => flxhl_str( get_option( 'woocommerce_currency_pos', 'left' ) ),
				'default'            => true,
			),
		);
	}

	/** @return list<array<string,string>> */
	private static function legal( ?FLXHL_Frontend $fe ): array {
		$pages = array( 'privacy' => flxhl_int( get_option( 'wp_page_for_privacy_policy' ) ) );
		if ( function_exists( 'wc_terms_and_conditions_page_id' ) ) {
			$pages['terms'] = flxhl_int( wc_terms_and_conditions_page_id() );
		}
		$out = array();
		foreach ( $pages as $kind => $id ) {
			$p = $id ? get_post( $id ) : null;
			if ( ! $p instanceof WP_Post || 'publish' !== $p->post_status ) {
				continue;
			}
			$link  = flxhl_str( get_permalink( $p ) );
			$out[] = array(
				'kind'  => $kind,
				'title' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
				'path'  => flxhl_str( FLXHL_Frontend_Service::front_path( $link ) ),
				'url'   => FLXHL_Frontend_Service::to_front( $link, $fe ),
			);
		}
		return $out;
	}

	/** @return array<string,string> */
	private static function captcha(): array {
		if ( ! function_exists( 'flxrc_active' ) || ! flxrc_active() || ! function_exists( 'flxrc_site_key' ) || ! class_exists( 'FLXRC_Settings_Service' ) ) {
			return array();
		}
		$key = flxhl_str( flxrc_site_key() );
		$ver = FLXRC_Settings_Service::settings()->version;
		$js  = ( function_exists( 'flxrc_api_host' ) ? flxhl_str( flxrc_api_host() ) : 'https://www.google.com' ) . '/recaptcha/api.js';
		return array(
			'provider'   => 'recaptcha',
			'version'    => $ver,
			'site_key'   => $key,
			'script_url' => 'v3' === $ver ? $js . '?render=' . rawurlencode( $key ) : $js . '?render=explicit',
		);
	}

	/* ------------------------------------------------------------------ */
	/* Menus                                                               */
	/* ------------------------------------------------------------------ */

	/** @return list<array<string,mixed>> */
	public static function menus( ?FLXHL_Frontend $fe ): array {
		$out = array();
		foreach ( flxhl_arr( get_nav_menu_locations() ) as $loc => $menu_id ) {
			$menu  = wp_get_nav_menu_object( flxhl_int( $menu_id ) );
			$items = $menu ? wp_get_nav_menu_items( $menu->term_id ) : false;
			if ( ! $menu || ! is_array( $items ) ) {
				continue;
			}
			$rows = array();
			foreach ( $items as $i => $it ) {
				if ( ! $it instanceof WP_Post ) {
					continue;
				}
				$parent = flxhl_int( $it->menu_item_parent ?? 0 ); // @phpstan-ignore property.notFound (nav menu item fields)
				$rows[] = self::item( 'm' . $it->ID, $parent ? 'm' . $parent : null, flxhl_str( $it->title ?? '' ), flxhl_str( $it->url ?? '' ), flxhl_str( $it->target ?? '' ), (int) $i, $fe ); // @phpstan-ignore property.notFound
			}
			$out[] = array( 'location' => (string) $loc, 'name' => $menu->name, 'source' => 'classic', 'items' => $rows );
		}
		foreach ( array( 'header', 'footer' ) as $part ) {
			$n = 0;
			foreach ( self::navigation_blocks( $part ) as $nav ) {
				$order = 0;
				$rows  = self::nav_items( $nav, null, 'b' . $part . ( $n + 1 ), $order, $fe );
				if ( ! $rows ) {
					continue;
				}
				++$n;
				$out[] = array(
					'location' => 1 === $n ? $part : $part . '-' . $n,
					'name'     => flxhl_str( $nav['attrs']['ariaLabel'] ?? '' ),
					'source'   => 'block',
					'items'    => $rows,
				);
			}
		}
		/**
		 * Menus of the site frame.
		 *
		 * @param list<array<string,mixed>> $out Menus.
		 * @param FLXHL_Frontend|null       $fe  Front end.
		 */
		$filtered = apply_filters( 'flx_headless_menus', $out, $fe );
		return is_array( $filtered ) ? array_values( array_filter( $filtered, 'is_array' ) ) : $out;
	}

	/** @return array<string,mixed> */
	private static function item( string $id, ?string $parent, string $label, string $url, string $target, int $order, ?FLXHL_Frontend $fe ): array {
		$url  = self::special_url( $url );
		$path = FLXHL_Frontend_Service::front_path( $url );
		return array(
			'id'       => $id,
			'parent'   => $parent,
			'label'    => trim( html_entity_decode( wp_strip_all_tags( $label ), ENT_QUOTES, 'UTF-8' ) ),
			'url'      => null === $path ? $url : ( $fe ? $fe->url . $path : flxhl_home() . $path ),
			'path'     => $path,
			'target'   => '' === $target ? null : $target,
			'external' => null === $path && (bool) preg_match( '#^(https?:)?//#', $url ),
			'order'    => $order,
		);
	}

	/** Theme shortcuts (#wc-shop, #wc-cart …) to the real page addresses. */
	private static function special_url( string $url ): string {
		$map = array( '#wc-shop' => 'shop', '#wc-cart' => 'cart', '#wc-account' => 'myaccount', '#wc-checkout' => 'checkout' );
		if ( ! isset( $map[ $url ] ) ) {
			return $url;
		}
		return function_exists( 'wc_get_page_permalink' ) ? flxhl_str( wc_get_page_permalink( $map[ $url ] ) ) : '';
	}

	/**
	 * core/navigation blocks of a template part.
	 *
	 * @return list<array<string,mixed>>
	 */
	private static function navigation_blocks( string $part ): array {
		$tpl = get_block_template( get_stylesheet() . '//' . $part, 'wp_template_part' );
		if ( ! $tpl || '' === $tpl->content ) {
			return array();
		}
		$found = array();
		self::find_blocks( parse_blocks( $tpl->content ), 'core/navigation', $found );
		return $found;
	}

	/**
	 * @param array<array-key,mixed>    $blocks
	 * @param list<array<string,mixed>> $found
	 */
	private static function find_blocks( array $blocks, string $name, array &$found ): void {
		foreach ( $blocks as $b ) {
			$b = flxhl_arr( $b );
			if ( ( $b['blockName'] ?? '' ) === $name ) {
				$row = array();
				foreach ( $b as $k => $v ) {
					$row[ (string) $k ] = $v;
				}
				$found[] = $row;
				continue;
			}
			self::find_blocks( flxhl_arr( $b['innerBlocks'] ?? array() ), $name, $found );
		}
	}

	/**
	 * Links of a navigation block (its own inner blocks, or the wp_navigation post it refers to).
	 *
	 * @param array<string,mixed> $nav
	 * @return list<array<string,mixed>>
	 */
	private static function nav_items( array $nav, ?string $parent, string $prefix, int &$order, ?FLXHL_Frontend $fe ): array {
		$attrs = flxhl_arr( $nav['attrs'] ?? array() );
		$inner = flxhl_arr( $nav['innerBlocks'] ?? array() );
		$ref   = flxhl_int( $attrs['ref'] ?? 0 );
		if ( $ref && 'core/navigation' === ( $nav['blockName'] ?? '' ) ) {
			$p     = get_post( $ref );
			$inner = $p instanceof WP_Post && 'wp_navigation' === $p->post_type && 'publish' === $p->post_status ? parse_blocks( $p->post_content ) : array();
		} elseif ( ! $inner && 'core/navigation' === ( $nav['blockName'] ?? '' ) && function_exists( 'block_core_navigation_get_fallback_blocks' ) ) {
			// No links and no menu chosen: WordPress shows its fallback (the latest menu or the page list).
			$inner = flxhl_arr( block_core_navigation_get_fallback_blocks() );
		}
		$rows = array();
		foreach ( $inner as $b ) {
			$b    = flxhl_arr( $b );
			$name = flxhl_str( $b['blockName'] ?? '' );
			$a    = flxhl_arr( $b['attrs'] ?? array() );
			$id   = $prefix . '-' . ( $order + 1 );
			if ( in_array( $name, array( 'core/navigation-link', 'core/navigation-submenu', 'core/home-link' ), true ) ) {
				$url = 'core/home-link' === $name ? home_url( '/' ) : flxhl_str( $a['url'] ?? '' );
				if ( '' === $url && ! empty( $a['id'] ) ) {
					$url = flxhl_str( get_permalink( flxhl_int( $a['id'] ) ) );
				}
				$label  = flxhl_str( $a['label'] ?? ( 'core/home-link' === $name ? __( 'Home', 'flx-headless' ) : '' ) );
				if ( '' === self::special_url( $url ) ) {
					continue; // A WooCommerce shortcut without WooCommerce.
				}
				$rows[] = self::item( $id, $parent, $label, $url, ! empty( $a['opensInNewTab'] ) ? '_blank' : '', $order++, $fe );
				if ( 'core/navigation-submenu' === $name ) {
					$b['blockName'] = 'core/navigation-submenu';
					$rows           = array_merge( $rows, self::nav_items( self::row( $b ), $id, $id, $order, $fe ) );
				}
			} elseif ( 'core/page-list' === $name ) {
				$map = array();
				foreach ( get_pages( array( 'sort_column' => 'menu_order,post_title' ) ) as $pg ) {
					$pid         = $prefix . '-p' . $pg->ID;
					$map[ $pg->ID ] = $pid;
					$rows[]      = self::item( $pid, $pg->post_parent && isset( $map[ $pg->post_parent ] ) ? $map[ $pg->post_parent ] : $parent, get_the_title( $pg ), flxhl_str( get_permalink( $pg ) ), '', $order++, $fe );
				}
			}
		}
		return $rows;
	}

	/**
	 * @param array<array-key,mixed> $b
	 * @return array<string,mixed>
	 */
	private static function row( array $b ): array {
		$out = array();
		foreach ( $b as $k => $v ) {
			$out[ (string) $k ] = $v;
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Template parts                                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Rendered template parts (header, footer …) with page addresses pointed at the front end. For front ends
	 * that reuse the theme's own markup and CSS instead of drawing the frame themselves.
	 *
	 * @param list<string> $names Part slugs.
	 * @return list<FLXHL_Part>
	 */
	public static function parts( array $names, ?string $lang = null, ?string $frontend = null ): array {
		$fe       = FLXHL_Frontend_Service::current( $frontend );
		$switched = self::switch_language( $lang );
		$out      = array();
		try {
			foreach ( array_slice( array_values( array_unique( array_map( 'sanitize_key', $names ) ) ), 0, 5 ) as $slug ) {
				if ( '' === $slug || ! get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' ) ) {
					continue;
				}
				$html  = do_blocks( '<!-- wp:template-part {"slug":"' . $slug . '","theme":"' . esc_attr( get_stylesheet() ) . '"} /-->' );
				$out[] = new FLXHL_Part( $slug, self::front_html( $html, $fe ) );
			}
		} finally {
			if ( $switched && function_exists( 'flxl_restore_language' ) ) {
				flxl_restore_language();
			}
		}
		return $out;
	}

	/** href="…" page addresses inside HTML pointed at the front end. */
	public static function front_html( string $html, ?FLXHL_Frontend $fe ): string {
		if ( null === $fe ) {
			return $html;
		}
		return (string) preg_replace_callback(
			'#\shref=(["\'])([^"\']+)\1#i',
			static fn( array $m ): string => ' href=' . $m[1] . esc_url( FLXHL_Frontend_Service::to_front( html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ), $fe ) ) . $m[1],
			$html
		);
	}
}
