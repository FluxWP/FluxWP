<?php
/**
 * Typed values the theme service returns (GraphQL, the classic pages and Flux AI read the same shapes).
 *
 * @package fluxwp
 */

defined( 'ABSPATH' ) || exit;

/**
 * A page or campaign the settings screen offers in a select.
 */
final class FLX_Theme_Option {

	/**
	 * @param int    $id    Post or donation type ID.
	 * @param string $title Name.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $title,
	) {}
}

/**
 * Theme Settings (the `flx_ayarlar` option) with the context the screen needs.
 */
final class FLX_Theme_Settings {

	/**
	 * @param string                 $visibility       live | coming-soon | store (effective, WooCommerce in sync).
	 * @param string                 $coming_soon_title Entire-site title ('' = translated default).
	 * @param string                 $coming_soon_text  Entire-site message.
	 * @param string                 $store_title      Store-notice title ('' = default).
	 * @param string                 $store_text       Store-notice message.
	 * @param string                 $store_title_default Default store-notice title for the site type.
	 * @param string                 $store_text_default  Default store-notice message for the site type.
	 * @param string                 $contact_email    Public contact email.
	 * @param string                 $site_type        shop | donation.
	 * @param string                 $pattern          dots | leaves | none.
	 * @param string                 $table_head       Table header colour hex or ''.
	 * @param string                 $news_intro       News introduction.
	 * @param string                 $dark_mode        auto | off.
	 * @param bool                   $animations       Scroll animations on.
	 * @param string                 $pagination       sonsuz | numara.
	 * @param bool                   $active_card      Home page active campaign card on.
	 * @param int                    $active_campaign  Donation type ID (0 = automatic).
	 * @param bool                   $cookie_enabled   Cookie banner on.
	 * @param string                 $cookie_title     Banner title.
	 * @param string                 $cookie_text      Banner text (HTML allowed).
	 * @param string                 $cookie_accept    Accept label.
	 * @param string                 $cookie_reject    Reject label.
	 * @param string                 $cookie_more      Policy link label.
	 * @param int                    $cookie_page      Policy page ID (0 = automatic).
	 * @param string                 $cookie_position  card | bar.
	 * @param int                    $cookie_revision  Consent version.
	 * @param string                 $ga4_id           GA4 measurement ID.
	 * @param string                 $ga_mode          consent | after.
	 * @param string|null            $consent_scripts  Extra consent scripts; null without unfiltered_html.
	 * @param string                 $bank_holder      Account holder.
	 * @param string                 $bank_iban        IBAN without spaces.
	 * @param string                 $bank_in_use      Account in use when it differs from the one here ('' = same).
	 * @param bool                   $woocommerce      WooCommerce is active.
	 * @param bool                   $active_card_available The home page card exists (Flux Donate).
	 * @param bool                   $can_edit_scripts The user may save raw scripts.
	 * @param string                 $presets_url      Presets tab ('' without Flux Setup).
	 * @param list<FLX_Theme_Option> $campaigns        Donation types to pick from.
	 */
	public function __construct(
		public readonly string $visibility,
		public readonly string $coming_soon_title,
		public readonly string $coming_soon_text,
		public readonly string $store_title,
		public readonly string $store_text,
		public readonly string $store_title_default,
		public readonly string $store_text_default,
		public readonly string $contact_email,
		public readonly string $site_type,
		public readonly string $pattern,
		public readonly string $table_head,
		public readonly string $news_intro,
		public readonly string $dark_mode,
		public readonly bool $animations,
		public readonly string $pagination,
		public readonly bool $active_card,
		public readonly int $active_campaign,
		public readonly bool $cookie_enabled,
		public readonly string $cookie_title,
		public readonly string $cookie_text,
		public readonly string $cookie_accept,
		public readonly string $cookie_reject,
		public readonly string $cookie_more,
		public readonly int $cookie_page,
		public readonly string $cookie_position,
		public readonly int $cookie_revision,
		public readonly string $ga4_id,
		public readonly string $ga_mode,
		public readonly ?string $consent_scripts,
		public readonly string $bank_holder,
		public readonly string $bank_iban,
		public readonly string $bank_in_use,
		public readonly bool $woocommerce,
		public readonly bool $active_card_available,
		public readonly bool $can_edit_scripts,
		public readonly string $presets_url,
		public readonly array $campaigns,
	) {}
}

/**
 * A colour of the site palette.
 */
final class FLX_Theme_Color {

	/**
	 * @param string $slug  Palette slug.
	 * @param string $name  Name.
	 * @param string $color CSS colour.
	 */
	public function __construct(
		public readonly string $slug,
		public readonly string $name,
		public readonly string $color,
	) {}
}

/**
 * A font family of the site.
 */
final class FLX_Theme_Font {

	/**
	 * @param string $slug        Font slug.
	 * @param string $name        Name.
	 * @param string $font_family CSS font-family value.
	 */
	public function __construct(
		public readonly string $slug,
		public readonly string $name,
		public readonly string $font_family,
	) {}
}

/**
 * Global styles: what a headless front end and the app take from the theme.
 */
final class FLX_Theme_Styles {

	/**
	 * @param list<FLX_Theme_Color> $palette    Colours (Site Editor changes win over theme.json).
	 * @param list<FLX_Theme_Font>  $fonts      Font families.
	 * @param bool                  $dark_mode  The site follows the visitor's dark mode.
	 * @param string                $table_head Table header colour or ''.
	 * @param string                $pattern    dots | leaves | none.
	 */
	public function __construct(
		public readonly array $palette,
		public readonly array $fonts,
		public readonly bool $dark_mode,
		public readonly string $table_head,
		public readonly string $pattern,
	) {}
}

/**
 * A payment logo.
 */
final class FLX_Theme_Pay_Logo {

	/**
	 * @param string $key   iyzico | a:<attachment id> | plugin key.
	 * @param string $label Name.
	 * @param string $url   Image URL.
	 * @param bool   $chip  Shown in a white box.
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly string $url,
		public readonly bool $chip,
	) {}
}

/**
 * Payment logo settings.
 */
final class FLX_Theme_Pay_Logos {

	/**
	 * @param bool                      $automatic Automatic list (not hand-picked).
	 * @param list<FLX_Theme_Pay_Logo> $current   Logos shown in the footer.
	 * @param list<FLX_Theme_Pay_Logo> $builtin   Logos the theme and plugins provide.
	 * @param list<FLX_Theme_Pay_Logo> $automatic_list What the automatic list shows.
	 */
	public function __construct(
		public readonly bool $automatic,
		public readonly array $current,
		public readonly array $builtin,
		public readonly array $automatic_list,
	) {}
}

/**
 * A row of the System Check.
 */
final class FLX_Theme_Check {

	/**
	 * @param string $id           Check ID.
	 * @param string $source       Package code ('theme' for the theme).
	 * @param string $source_label Readable package name ('' for the theme).
	 * @param string $group        Group title.
	 * @param string $label        What is checked.
	 * @param string $status       ok | warning | error | info.
	 * @param string $message      Result.
	 * @param string $action_url   Fix page ('' = none).
	 * @param string $action_label Button label.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $source,
		public readonly string $source_label,
		public readonly string $group,
		public readonly string $label,
		public readonly string $status,
		public readonly string $message,
		public readonly string $action_url,
		public readonly string $action_label,
	) {}
}

/**
 * A fix applied through Flux AI / MCP.
 */
final class FLX_Theme_Fix_Entry {

	/**
	 * @param int    $time   Unix time.
	 * @param string $user   User login.
	 * @param string $label  Fix.
	 * @param string $value  Value sent ('' = none).
	 * @param string $before Value before.
	 * @param string $after  Value after.
	 * @param bool   $ok     Succeeded.
	 * @param string $message Result.
	 */
	public function __construct(
		public readonly int $time,
		public readonly string $user,
		public readonly string $label,
		public readonly string $value,
		public readonly string $before,
		public readonly string $after,
		public readonly bool $ok,
		public readonly string $message,
	) {}
}

/**
 * The System Check page.
 */
final class FLX_Theme_System {

	/**
	 * @param int                      $checked_at Unix time of the run.
	 * @param list<FLX_Theme_Check>    $checks     Rows, grouped, problems first inside a group.
	 * @param bool                     $fix_log_available Flux SEO logs fixes.
	 * @param list<FLX_Theme_Fix_Entry> $fix_log   Recent fixes (newest first).
	 */
	public function __construct(
		public readonly int $checked_at,
		public readonly array $checks,
		public readonly bool $fix_log_available,
		public readonly array $fix_log,
	) {}
}

/**
 * A previous theme whose data can be copied.
 */
final class FLX_Theme_Migration_Source {

	/**
	 * @param string   $stylesheet Theme folder name.
	 * @param int|null $last_run   Unix time of the last migration, null when never.
	 */
	public function __construct(
		public readonly string $stylesheet,
		public readonly ?int $last_run,
	) {}
}
