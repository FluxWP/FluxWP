<?php
/**
 * Typed values the Flux Headless services return (PHP 8.1 readonly properties). GraphQL resolvers read
 * these; nothing here talks to WordPress.
 *
 * Routes and the site are built as arrays first so other packages can change them with the
 * `flx_headless_route` / `flx_headless_site` filters; `from()` turns the filtered array back into a value
 * and drops what has the wrong shape.
 *
 * @package FLX_Headless
 */

defined( 'ABSPATH' ) || exit;

/** Readers for filtered arrays (values from other plugins are untrusted mixed). */
final class FLXHL_Read {

	/** @param array<array-key,mixed> $a */
	public static function s( array $a, string $k, string $d = '' ): string {
		return isset( $a[ $k ] ) && is_scalar( $a[ $k ] ) ? (string) $a[ $k ] : $d;
	}

	/** @param array<array-key,mixed> $a */
	public static function ns( array $a, string $k ): ?string {
		$v = self::s( $a, $k );
		return '' === $v ? null : $v;
	}

	/** @param array<array-key,mixed> $a */
	public static function i( array $a, string $k, int $d = 0 ): int {
		return isset( $a[ $k ] ) && is_numeric( $a[ $k ] ) ? (int) $a[ $k ] : $d;
	}

	/** @param array<array-key,mixed> $a */
	public static function b( array $a, string $k ): bool {
		return ! empty( $a[ $k ] );
	}

	/**
	 * @param array<array-key,mixed> $a
	 * @return array<array-key,mixed>
	 */
	public static function a( array $a, string $k ): array {
		return isset( $a[ $k ] ) && is_array( $a[ $k ] ) ? $a[ $k ] : array();
	}

	/**
	 * @param array<array-key,mixed> $a
	 * @return list<string>
	 */
	public static function strings( array $a, string $k ): array {
		$out = array();
		foreach ( self::a( $a, $k ) as $v ) {
			if ( is_scalar( $v ) && '' !== (string) $v ) {
				$out[] = (string) $v;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Rows of a list, each turned into a value by $make (null rows are dropped).
	 *
	 * @template T
	 * @param array<array-key,mixed>                    $a    Parent.
	 * @param string                                    $k    Key.
	 * @param callable(array<array-key,mixed>):(T|null) $make Row → value.
	 * @return list<T>
	 */
	public static function rows( array $a, string $k, callable $make ): array {
		$out = array();
		foreach ( self::a( $a, $k ) as $row ) {
			if ( is_object( $row ) ) {
				$row = get_object_vars( $row );
			}
			if ( ! is_array( $row ) ) {
				continue;
			}
			$v = $make( $row );
			if ( null !== $v ) {
				$out[] = $v;
			}
		}
		return $out;
	}
}

/** A headless front end this WordPress serves. The revalidate secret itself is never in this value. */
final class FLXHL_Frontend {
	/**
	 * @param list<string> $languages Language codes the front end serves; empty = every language.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $name,
		public readonly string $url,
		public readonly array $languages,
		public readonly bool $enabled,
		public readonly bool $is_default,
		public readonly string $revalidate_url,
		public readonly bool $secret_set,
		public readonly string $secret_hint,
		public readonly string $preview_path,
	) {}

	/** scheme://host[:port] of the front end. */
	public function origin(): string {
		return flxhl_origin( $this->url );
	}
}

/** One revalidate webhook delivery (or test). */
final class FLXHL_Delivery {
	/**
	 * @param list<string> $tags  Cache tags sent.
	 * @param list<string> $paths Front-end paths sent.
	 */
	public function __construct(
		public readonly int $time,
		public readonly string $frontend_id,
		public readonly string $frontend_name,
		public readonly string $event,
		public readonly array $tags,
		public readonly array $paths,
		public readonly int $status,
		public readonly bool $ok,
		public readonly string $message,
		public readonly int $ms,
	) {}

	/** @param array<array-key,mixed> $r */
	public static function from( array $r ): self {
		return new self(
			FLXHL_Read::i( $r, 'time' ),
			FLXHL_Read::s( $r, 'frontend_id' ),
			FLXHL_Read::s( $r, 'frontend_name' ),
			FLXHL_Read::s( $r, 'event', 'purge' ),
			FLXHL_Read::strings( $r, 'tags' ),
			FLXHL_Read::strings( $r, 'paths' ),
			FLXHL_Read::i( $r, 'status' ),
			FLXHL_Read::b( $r, 'ok' ),
			FLXHL_Read::s( $r, 'message' ),
			FLXHL_Read::i( $r, 'ms' ),
		);
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return get_object_vars( $this );
	}
}

/** Settings of the Flux Headless screen. */
final class FLXHL_Settings {
	/**
	 * @param list<FLXHL_Frontend> $frontends  Front ends.
	 * @param list<string>         $cors       Origins allowed to call /graphql from a browser (Flux GraphQL list + front ends).
	 * @param list<FLXHL_Delivery> $deliveries Last webhook deliveries, newest first.
	 */
	public function __construct(
		public readonly array $frontends,
		public readonly string $wp_front,
		public readonly array $cors,
		public readonly string $graphql_url,
		public readonly string $graphql_settings_url,
		public readonly bool $persisted_only,
		public readonly bool $lang_active,
		public readonly array $deliveries,
	) {}
}

/** A <meta> tag of the page head. */
final class FLXHL_Meta {
	public function __construct(
		public readonly string $name,
		public readonly string $property,
		public readonly string $content,
	) {}

	/** @param array<array-key,mixed> $r */
	public static function from( array $r ): ?self {
		$c = FLXHL_Read::s( $r, 'content' );
		$n = FLXHL_Read::s( $r, 'name' );
		$p = FLXHL_Read::s( $r, 'property' );
		return ( '' === $n && '' === $p ) ? null : new self( $n, $p, $c );
	}
}

/** A language version of the page (hreflang). */
final class FLXHL_Alternate {
	public function __construct(
		public readonly string $lang,
		public readonly string $hreflang,
		public readonly string $url,
		public readonly string $path,
	) {}

	/** @param array<array-key,mixed> $r */
	public static function from( array $r ): ?self {
		$url = FLXHL_Read::s( $r, 'url' );
		return '' === $url ? null : new self( FLXHL_Read::s( $r, 'lang' ), FLXHL_Read::s( $r, 'hreflang' ), $url, FLXHL_Read::s( $r, 'path' ) );
	}
}

/** What a front end puts in <head>. */
final class FLXHL_Head {
	/**
	 * @param list<string>     $robots  Robots directives (index, follow, max-image-preview:large …).
	 * @param list<FLXHL_Meta> $meta    Open Graph, Twitter and other meta tags.
	 * @param list<string>     $json_ld JSON-LD documents (each a JSON string).
	 */
	public function __construct(
		public readonly string $title,
		public readonly string $description,
		public readonly ?string $canonical,
		public readonly array $robots,
		public readonly bool $noindex,
		public readonly ?string $image,
		public readonly string $locale,
		public readonly array $meta,
		public readonly array $json_ld,
	) {}

	/** @param array<array-key,mixed> $r */
	public static function from( array $r ): self {
		return new self(
			FLXHL_Read::s( $r, 'title' ),
			FLXHL_Read::s( $r, 'description' ),
			FLXHL_Read::ns( $r, 'canonical' ),
			FLXHL_Read::strings( $r, 'robots' ),
			FLXHL_Read::b( $r, 'noindex' ),
			FLXHL_Read::ns( $r, 'image' ),
			FLXHL_Read::s( $r, 'locale' ),
			FLXHL_Read::rows( $r, 'meta', array( FLXHL_Meta::class, 'from' ) ),
			FLXHL_Read::strings( $r, 'json_ld' ),
		);
	}
}

/** What an address on the front end shows. */
final class FLXHL_Route {
	/**
	 * @param list<FLXHL_Alternate> $alternates Language versions (hreflang), the page itself included.
	 * @param list<string>          $cache_tags Tags to cache the page under; the revalidate webhook sends the same tags.
	 */
	public function __construct(
		public readonly string $path,
		public readonly string $kind,
		public readonly int $status,
		public readonly ?string $redirect,
		public readonly ?string $lang,
		public readonly string $object_type,
		public readonly int $object_id,
		public readonly ?string $post_type,
		public readonly ?string $taxonomy,
		public readonly string $title,
		public readonly int $page,
		public readonly FLXHL_Head $head,
		public readonly array $alternates,
		public readonly array $cache_tags,
		public readonly ?string $frontend_id,
	) {}

	/** @param array<array-key,mixed> $r */
	public static function from( array $r ): self {
		return new self(
			FLXHL_Read::s( $r, 'path', '/' ),
			FLXHL_Read::s( $r, 'kind', 'not_found' ),
			FLXHL_Read::i( $r, 'status', 404 ),
			FLXHL_Read::ns( $r, 'redirect' ),
			FLXHL_Read::ns( $r, 'lang' ),
			FLXHL_Read::s( $r, 'object_type' ),
			FLXHL_Read::i( $r, 'object_id' ),
			FLXHL_Read::ns( $r, 'post_type' ),
			FLXHL_Read::ns( $r, 'taxonomy' ),
			FLXHL_Read::s( $r, 'title' ),
			max( 1, FLXHL_Read::i( $r, 'page', 1 ) ),
			FLXHL_Head::from( FLXHL_Read::a( $r, 'head' ) ),
			FLXHL_Read::rows( $r, 'alternates', array( FLXHL_Alternate::class, 'from' ) ),
			FLXHL_Read::strings( $r, 'cache_tags' ),
			FLXHL_Read::ns( $r, 'frontend_id' ),
		);
	}
}

/** An image (logo). */
final class FLXHL_Image {
	public function __construct(
		public readonly string $url,
		public readonly int $width,
		public readonly int $height,
		public readonly string $alt,
	) {}

	/** @param array<array-key,mixed> $r */
	public static function from( array $r ): ?self {
		$url = FLXHL_Read::s( $r, 'url' );
		return '' === $url ? null : new self( $url, FLXHL_Read::i( $r, 'width' ), FLXHL_Read::i( $r, 'height' ), FLXHL_Read::s( $r, 'alt' ) );
	}
}

/** A menu link. */
final class FLXHL_Menu_Item {
	public function __construct(
		public readonly string $id,
		public readonly ?string $parent,
		public readonly string $label,
		public readonly string $url,
		public readonly ?string $path,
		public readonly ?string $target,
		public readonly bool $external,
		public readonly int $order,
	) {}

	/** @param array<array-key,mixed> $r */
	public static function from( array $r ): ?self {
		$label = FLXHL_Read::s( $r, 'label' );
		$url   = FLXHL_Read::s( $r, 'url' );
		if ( '' === $label || '' === $url ) {
			return null;
		}
		return new self(
			FLXHL_Read::s( $r, 'id' ),
			FLXHL_Read::ns( $r, 'parent' ),
			$label,
			$url,
			FLXHL_Read::ns( $r, 'path' ),
			FLXHL_Read::ns( $r, 'target' ),
			FLXHL_Read::b( $r, 'external' ),
			FLXHL_Read::i( $r, 'order' ),
		);
	}
}

/** A menu (classic menu location or a navigation block of the header / footer). */
final class FLXHL_Menu {
	/** @param list<FLXHL_Menu_Item> $items Links, flat; children point at their parent. */
	public function __construct(
		public readonly string $location,
		public readonly string $name,
		public readonly string $source,
		public readonly array $items,
	) {}

	/** @param array<array-key,mixed> $r */
	public static function from( array $r ): ?self {
		$loc = FLXHL_Read::s( $r, 'location' );
		return '' === $loc ? null : new self( $loc, FLXHL_Read::s( $r, 'name' ), FLXHL_Read::s( $r, 'source', 'block' ), FLXHL_Read::rows( $r, 'items', array( FLXHL_Menu_Item::class, 'from' ) ) );
	}
}

/** A palette colour of the theme. */
final class FLXHL_Color {
	public function __construct(
		public readonly string $slug,
		public readonly string $name,
		public readonly string $color,
	) {}

	/** @param array<array-key,mixed> $r */
	public static function from( array $r ): ?self {
		$slug = FLXHL_Read::s( $r, 'slug' );
		return '' === $slug ? null : new self( $slug, FLXHL_Read::s( $r, 'name', $slug ), FLXHL_Read::s( $r, 'color' ) );
	}
}

/** A language of the site. */
final class FLXHL_Language {
	public function __construct(
		public readonly string $code,
		public readonly string $locale,
		public readonly string $name,
		public readonly string $hreflang,
		public readonly string $path,
		public readonly string $url,
		public readonly bool $is_default,
	) {}

	/** @param array<array-key,mixed> $r */
	public static function from( array $r ): ?self {
		$code = FLXHL_Read::s( $r, 'code' );
		return '' === $code ? null : new self(
			$code,
			FLXHL_Read::s( $r, 'locale' ),
			FLXHL_Read::s( $r, 'name', $code ),
			FLXHL_Read::s( $r, 'hreflang', $code ),
			FLXHL_Read::s( $r, 'path', '/' ),
			FLXHL_Read::s( $r, 'url' ),
			FLXHL_Read::b( $r, 'default' ),
		);
	}
}

/** A legal page (privacy, terms …). */
final class FLXHL_Legal {
	public function __construct(
		public readonly string $kind,
		public readonly string $title,
		public readonly string $path,
		public readonly string $url,
	) {}

	/** @param array<array-key,mixed> $r */
	public static function from( array $r ): ?self {
		$kind = FLXHL_Read::s( $r, 'kind' );
		$url  = FLXHL_Read::s( $r, 'url' );
		return ( '' === $kind || '' === $url ) ? null : new self( $kind, FLXHL_Read::s( $r, 'title' ), FLXHL_Read::s( $r, 'path' ), $url );
	}
}

/** A currency the site sells or takes donations in. */
final class FLXHL_Currency {
	public function __construct(
		public readonly string $code,
		public readonly string $symbol,
		public readonly int $decimals,
		public readonly string $decimal_separator,
		public readonly string $thousand_separator,
		public readonly string $position,
		public readonly bool $is_default,
	) {}

	/** @param array<array-key,mixed> $r */
	public static function from( array $r ): ?self {
		$code = strtoupper( FLXHL_Read::s( $r, 'code' ) );
		if ( ! preg_match( '/^[A-Z]{3}$/', $code ) ) {
			return null;
		}
		return new self(
			$code,
			FLXHL_Read::s( $r, 'symbol', $code ),
			FLXHL_Read::i( $r, 'decimals', 2 ),
			FLXHL_Read::s( $r, 'decimal_separator', '.' ),
			FLXHL_Read::s( $r, 'thousand_separator', ',' ),
			FLXHL_Read::s( $r, 'position', 'left' ),
			FLXHL_Read::b( $r, 'default' ),
		);
	}
}

/** Contact details of the organisation. */
final class FLXHL_Contact {
	public function __construct(
		public readonly string $email,
		public readonly string $phone,
		public readonly string $whatsapp,
		public readonly string $address,
	) {}

	/** @param array<array-key,mixed> $r */
	public static function from( array $r ): self {
		return new self( FLXHL_Read::s( $r, 'email' ), FLXHL_Read::s( $r, 'phone' ), FLXHL_Read::s( $r, 'whatsapp' ), FLXHL_Read::s( $r, 'address' ) );
	}
}

/** Public captcha settings (the site key is public by design). */
final class FLXHL_Captcha {
	public function __construct(
		public readonly string $provider,
		public readonly string $version,
		public readonly string $site_key,
		public readonly string $script_url,
	) {}

	/** @param array<array-key,mixed> $r */
	public static function from( array $r ): ?self {
		$key = FLXHL_Read::s( $r, 'site_key' );
		return '' === $key ? null : new self( FLXHL_Read::s( $r, 'provider', 'recaptcha' ), FLXHL_Read::s( $r, 'version' ), $key, FLXHL_Read::s( $r, 'script_url' ) );
	}
}

/** A rendered template part (header, footer). */
final class FLXHL_Part {
	public function __construct(
		public readonly string $name,
		public readonly string $html,
	) {}
}

/** The site a front end draws its frame from. */
final class FLXHL_Site {
	/**
	 * @param list<FLXHL_Language> $languages  Languages, default first.
	 * @param list<FLXHL_Menu>     $menus      Menus.
	 * @param list<FLXHL_Color>    $palette    Theme palette.
	 * @param list<FLXHL_Currency> $currencies Currencies, default first.
	 * @param list<FLXHL_Legal>    $legal      Legal pages.
	 * @param list<string>         $social     Social profile URLs.
	 * @param list<string>         $cache_tags Tags to cache the site frame under.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $description,
		public readonly string $url,
		public readonly string $cms_url,
		public readonly string $graphql_url,
		public readonly string $language,
		public readonly string $locale,
		public readonly array $languages,
		public readonly ?FLXHL_Image $logo,
		public readonly ?string $icon,
		public readonly array $menus,
		public readonly array $palette,
		public readonly FLXHL_Contact $contact,
		public readonly array $currencies,
		public readonly array $legal,
		public readonly ?FLXHL_Captcha $captcha,
		public readonly array $social,
		public readonly string $sitemap_url,
		public readonly string $search_path,
		public readonly array $cache_tags,
		public readonly ?string $frontend_id,
	) {}

	/** @param array<array-key,mixed> $r */
	public static function from( array $r ): self {
		return new self(
			FLXHL_Read::s( $r, 'name' ),
			FLXHL_Read::s( $r, 'description' ),
			FLXHL_Read::s( $r, 'url' ),
			FLXHL_Read::s( $r, 'cms_url' ),
			FLXHL_Read::s( $r, 'graphql_url' ),
			FLXHL_Read::s( $r, 'language' ),
			FLXHL_Read::s( $r, 'locale' ),
			FLXHL_Read::rows( $r, 'languages', array( FLXHL_Language::class, 'from' ) ),
			FLXHL_Image::from( FLXHL_Read::a( $r, 'logo' ) ),
			FLXHL_Read::ns( $r, 'icon' ),
			FLXHL_Read::rows( $r, 'menus', array( FLXHL_Menu::class, 'from' ) ),
			FLXHL_Read::rows( $r, 'palette', array( FLXHL_Color::class, 'from' ) ),
			FLXHL_Contact::from( FLXHL_Read::a( $r, 'contact' ) ),
			FLXHL_Read::rows( $r, 'currencies', array( FLXHL_Currency::class, 'from' ) ),
			FLXHL_Read::rows( $r, 'legal', array( FLXHL_Legal::class, 'from' ) ),
			FLXHL_Captcha::from( FLXHL_Read::a( $r, 'captcha' ) ),
			FLXHL_Read::strings( $r, 'social' ),
			FLXHL_Read::s( $r, 'sitemap_url' ),
			FLXHL_Read::s( $r, 'search_path', '/?s=' ),
			FLXHL_Read::strings( $r, 'cache_tags' ),
			FLXHL_Read::ns( $r, 'frontend_id' ),
		);
	}
}
