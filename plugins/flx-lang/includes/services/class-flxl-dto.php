<?php
/**
 * Typed values the Flux Lang service returns (GraphQL, the classic settings page and the REST fields read the same shapes).
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

/**
 * A site language (active or not).
 */
final class FLXL_Language {

	/**
	 * @param string $code     Language code and address prefix (en, de, pt-br).
	 * @param string $name     Name shown to people.
	 * @param string $locale   WordPress locale of the site content (en_US).
	 * @param string $flag     Flag emoji; empty when none.
	 * @param bool   $active   Visitors can open it.
	 * @param bool   $default  The default language (no address prefix).
	 * @param string $address  Address of the language root: "/" for the default, "/en/" otherwise.
	 * @param string $hreflang hreflang value.
	 */
	public function __construct(
		public readonly string $code,
		public readonly string $name,
		public readonly string $locale,
		public readonly string $flag,
		public readonly bool $active,
		public readonly bool $default,
		public readonly string $address,
		public readonly string $hreflang,
	) {}
}

/**
 * One language version of a content.
 */
final class FLXL_Translation {

	/**
	 * @param string      $lang      Language code.
	 * @param int         $id        Post ID of the version.
	 * @param string      $title     Title (empty when the post has none).
	 * @param string      $status    WordPress post status (publish, draft …).
	 * @param string|null $url       Public address; null while it is not published.
	 * @param string|null $edit_url  Editor address; null when the viewer cannot edit it.
	 * @param bool        $is_self   This is the content the list was read from.
	 */
	public function __construct(
		public readonly string $lang,
		public readonly int $id,
		public readonly string $title,
		public readonly string $status,
		public readonly ?string $url,
		public readonly ?string $edit_url,
		public readonly bool $is_self,
	) {}
}

/**
 * One site text in one language (site title, tagline, Flux SEO home title …).
 */
final class FLXL_Site_Text {

	/**
	 * @param string $lang  Language code (never the default language).
	 * @param string $key   Text key (blogname, flxseo_home_title …).
	 * @param string $value Text; empty values are not stored.
	 */
	public function __construct(
		public readonly string $lang,
		public readonly string $key,
		public readonly string $value,
	) {}
}

/**
 * A translatable site text field.
 */
final class FLXL_Text_Field {

	/**
	 * @param string $key         Text key.
	 * @param string $label       Label.
	 * @param bool   $textarea    Long text.
	 * @param string $placeholder Value in the default language (shown as a hint).
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly bool $textarea,
		public readonly string $placeholder,
	) {}
}

/**
 * A language that can be added from the catalog.
 */
final class FLXL_Catalog_Entry {

	/**
	 * @param string $code   Code.
	 * @param string $name   Name.
	 * @param string $locale WordPress locale.
	 * @param string $flag   Flag emoji.
	 */
	public function __construct(
		public readonly string $code,
		public readonly string $name,
		public readonly string $locale,
		public readonly string $flag,
	) {}
}

/**
 * A public post type that can be translated.
 */
final class FLXL_Post_Type_Choice {

	/**
	 * @param string $name     Post type name.
	 * @param string $label    Plural label.
	 * @param bool   $selected Translated now.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $label,
		public readonly bool $selected,
	) {}
}

/**
 * What the server knows about the visitor's country.
 */
final class FLXL_Geo {

	/**
	 * @param string $country ISO 3166-1 alpha-2 code; empty when the server sends none.
	 * @param string $source  Name of the header the code came from; empty when none.
	 */
	public function __construct(
		public readonly string $country,
		public readonly string $source,
	) {}
}

/**
 * Everything the Languages settings screen shows and saves.
 */
final class FLXL_Settings {

	/**
	 * @param string                    $default_language Default language code.
	 * @param list<FLXL_Language>       $languages        Every language, inactive ones too, default first.
	 * @param list<string>              $post_types       Translated post types.
	 * @param bool                      $fallback         Show the default-language version when a translation is missing.
	 * @param string                    $suggest          off | banner | redirect.
	 * @param string                    $suggest_to       Language used when neither browser nor country language is active.
	 * @param list<string>              $home_countries   Country codes that see the default language.
	 * @param string                    $switcher         code | name | flag.
	 * @param string                    $switcher_place   footer | header | both.
	 * @param list<FLXL_Site_Text>      $texts            Site texts per language.
	 * @param list<FLXL_Catalog_Entry>  $catalog          Languages that can still be added.
	 * @param list<FLXL_Post_Type_Choice> $post_type_choices Public post types.
	 * @param list<FLXL_Text_Field>     $text_fields      Translatable site text fields.
	 * @param FLXL_Geo                  $geo              Country of the current request.
	 * @param bool                      $ai_available     Flux AI & MCP is ready for translation.
	 */
	public function __construct(
		public readonly string $default_language,
		public readonly array $languages,
		public readonly array $post_types,
		public readonly bool $fallback,
		public readonly string $suggest,
		public readonly string $suggest_to,
		public readonly array $home_countries,
		public readonly string $switcher,
		public readonly string $switcher_place,
		public readonly array $texts,
		public readonly array $catalog,
		public readonly array $post_type_choices,
		public readonly array $text_fields,
		public readonly FLXL_Geo $geo,
		public readonly bool $ai_available,
	) {}
}

/**
 * A running (or just finished) AI translation job.
 */
final class FLXL_Job {

	/**
	 * @param string       $id             Job key.
	 * @param string       $status         running | done | failed.
	 * @param int          $step           Last finished step (-1 before the first).
	 * @param int          $total          Number of steps.
	 * @param int          $source_id      Source content.
	 * @param int          $translation_id Translation being written.
	 * @param string       $lang           Target language.
	 * @param list<string> $errors         Parts that stayed untranslated ("meta" or a step number).
	 * @param string|null  $edit_url       Editor address of the translation.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $status,
		public readonly int $step,
		public readonly int $total,
		public readonly int $source_id,
		public readonly int $translation_id,
		public readonly string $lang,
		public readonly array $errors,
		public readonly ?string $edit_url,
	) {}
}
