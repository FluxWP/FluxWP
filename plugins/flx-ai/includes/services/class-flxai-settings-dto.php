<?php
/**
 * Typed values of the settings service. API keys never appear here: a key is only ever described by
 * FLXAI_Secret (is it set, the last characters), and written through FLXAI_Settings_Service::save().
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** The model one plugin uses (empty = the default model). */
final class FLXAI_Source_Model {

	public function __construct(
		public readonly string $source,
		public readonly string $label,
		public readonly string $model,
	) {}
}

/** An API key: whether it is set, never its value. */
final class FLXAI_Secret {

	/**
	 * @param string $name        Setting name (key_openrouter …).
	 * @param string $group       provider | media | web | render.
	 * @param string $label       Provider name for display.
	 * @param string $help        Where to get the key; empty when unknown.
	 * @param bool   $configured  A key is saved (or set in wp-config.php).
	 * @param string $hint        Last four characters of a saved key; empty for short keys.
	 * @param bool   $from_config The key comes from a wp-config.php constant; saving here has no effect on it.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $group,
		public readonly string $label,
		public readonly string $help,
		public readonly bool $configured,
		public readonly string $hint,
		public readonly bool $from_config,
	) {}
}

/** Everything on the Flux AI settings page except the keys. */
final class FLXAI_Settings {

	/**
	 * @param list<FLXAI_Source_Model> $source_models One row per plugin that can use its own model.
	 * @param list<string>             $chat_roles    Role keys that may use the shared chat.
	 */
	public function __construct(
		public readonly string $anthropic_workspace,
		public readonly string $model,
		public readonly string $chat_model,
		public readonly string $title_model,
		public readonly array $source_models,
		public readonly string $cache,
		public readonly string $effort,
		public readonly bool $thinking,
		public readonly float $limit_day,
		public readonly float $limit_week,
		public readonly float $limit_month,
		public readonly string $limit_action,
		public readonly int $limit_warn_pct,
		public readonly bool $limit_notify,
		public readonly array $chat_roles,
		public readonly bool $chat_admin,
		public readonly bool $chat_front,
		public readonly string $chat_mode,
		public readonly int $chat_max_rounds,
		public readonly int $chat_history,
		public readonly string $chat_instructions,
		public readonly bool $chat_widgets,
		public readonly string $media_image_model,
		public readonly string $media_video_model,
		public readonly int $media_days,
		public readonly bool $image_tool_on,
		public readonly bool $web_on,
		public readonly bool $web_fetch_on,
		public readonly bool $shell_on,
		public readonly string $web_provider,
		public readonly int $web_search_month,
		public readonly float $web_search_cost,
		public readonly string $render_url,
		public readonly string $render_user,
		public readonly int $log_days,
		public readonly bool $log_bodies,
		public readonly int $log_body_days,
		public readonly bool $delete_data,
		public readonly string $cloud_ai = 'auto',
	) {}
}

/** A choice of a select field. */
final class FLXAI_Option {

	public function __construct(
		public readonly string $id,
		public readonly string $label,
	) {}
}

/** An image or video model of the media catalog. */
final class FLXAI_Media_Model {

	/**
	 * @param string       $kind   image | video.
	 * @param list<string> $routes Provider ids in the order they are tried.
	 * @param string       $auto   Provider chosen automatically (the first with a key); empty when none has a key.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly string $kind,
		public readonly array $routes,
		public readonly string $auto,
	) {}
}

/** Choices and read-only facts the settings page needs. */
final class FLXAI_Settings_Options {

	/**
	 * @param list<FLXAI_Option>      $roles          WordPress roles.
	 * @param list<FLXAI_Option>      $web_providers  Web search providers.
	 * @param list<FLXAI_Media_Model> $media_models   Image and video catalog.
	 * @param list<FLXAI_Option>      $media_providers Image and video providers (label only).
	 * @param string                  $default_model  The model used when none is chosen.
	 * @param string                  $renderer       gotenberg | imagick | '' (none).
	 * @param int                     $web_month_count Web searches this month.
	 * @param bool                    $providers_ready At least one text-model key is saved (or Flux Cloud credits).
	 * @param FLXAI_Cloud|null        $cloud          Flux Cloud AI credits; null when the site is not connected.
	 */
	public function __construct(
		public readonly array $roles,
		public readonly array $web_providers,
		public readonly array $media_models,
		public readonly array $media_providers,
		public readonly string $default_model,
		public readonly string $renderer,
		public readonly int $web_month_count,
		public readonly bool $providers_ready,
		public readonly ?FLXAI_Cloud $cloud = null,
	) {}
}

/** Flux Cloud AI credits of the FluxWP account this site is connected to (1 credit = 0.001 USD). */
final class FLXAI_Cloud {

	/**
	 * @param bool   $on       Flux Cloud offers AI credits.
	 * @param bool   $active   Requests of the default model go through Flux Cloud now.
	 * @param bool   $basic    The plan has basic models only.
	 * @param int    $monthly  Monthly credits of the plan; -1 unlimited.
	 * @param int    $left     Credits left (monthly + bought); -1 unlimited.
	 * @param int    $resets   Unix time the monthly credits renew.
	 */
	public function __construct(
		public readonly bool $on,
		public readonly bool $active,
		public readonly bool $basic,
		public readonly int $monthly,
		public readonly int $used,
		public readonly int $bonus,
		public readonly int $left,
		public readonly int $site_used,
		public readonly int $resets,
		public readonly string $buy_url,
		public readonly string $default_model,
	) {}
}

/** A model of the catalog (for the model picker). */
final class FLXAI_Model {

	/**
	 * @param string $id       Full id as stored ("claude:claude-haiku-5-5", "google/gemini-2.5-flash").
	 * @param string $provider openrouter | anthropic.
	 * @param float  $price_in USD per million input tokens.
	 * @param float  $price_out USD per million output tokens.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $name,
		public readonly string $family,
		public readonly string $provider,
		public readonly int $context,
		public readonly float $price_in,
		public readonly float $price_out,
		public readonly bool $tools,
		public readonly bool $vision,
		public readonly bool $thinking,
	) {}
}

/** Result of the model test. */
final class FLXAI_Test_Result {

	/**
	 * @param string $text Model reply (testing a model); empty for the renderer test.
	 * @param int    $ms   Round trip in milliseconds.
	 * @param string $via  Renderer that drew the sample (renderer test); empty otherwise.
	 * @param string $image PNG data URI of the sample (renderer test); empty otherwise.
	 */
	public function __construct(
		public readonly string $text,
		public readonly int $ms,
		public readonly string $via,
		public readonly string $image,
	) {}
}
