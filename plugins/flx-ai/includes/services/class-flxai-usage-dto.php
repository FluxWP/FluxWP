<?php
/**
 * Typed values of the usage service: spending, reports, billing, logs and connected systems.
 * GraphQL, the classic pages and Flux AI read the same shapes.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** Spending against one limit period (day, week, month). */
final class FLXAI_Budget_Period {

	/**
	 * @param string $key    day | week | month.
	 * @param string $label  Period name in the user's language.
	 * @param float  $limit  Limit in USD; 0 = unlimited.
	 * @param float  $spent  Spent in USD this period.
	 * @param float  $pct    Share of the limit used (0 without a limit).
	 * @param bool   $over   The limit is reached.
	 * @param int    $resets Unix time the period resets.
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly float $limit,
		public readonly float $spent,
		public readonly float $pct,
		public readonly bool $over,
		public readonly int $resets,
	) {}
}

/** Totals of a report range. */
final class FLXAI_Report_Totals {

	public function __construct(
		public readonly int $calls,
		public readonly int $tools,
		public readonly int $in_tok,
		public readonly int $out_tok,
		public readonly int $cache_read,
		public readonly int $cache_write,
		public readonly float $cost,
		public readonly float $full_cost,
		public readonly float $saved,
		public readonly int $model_ms,
		public readonly int $tool_ms,
		public readonly int $errors,
	) {}
}

/** One day of a report. */
final class FLXAI_Report_Day {

	/**
	 * @param string $date Y-m-d in the site time zone.
	 */
	public function __construct(
		public readonly string $date,
		public readonly float $cost,
		public readonly int $in_tok,
		public readonly int $out_tok,
		public readonly int $cache_read,
		public readonly int $model_ms,
		public readonly int $tool_ms,
		public readonly int $calls,
		public readonly int $tools,
		public readonly int $errors,
	) {}
}

/** One row of a breakdown (by plugin, model, purpose, provider). */
final class FLXAI_Breakdown_Row {

	/**
	 * @param string $key     Raw key (source id, model id …).
	 * @param string $label   Name for display.
	 * @param bool   $partial Part of the cost is estimated from the price list (billing by provider).
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly int $calls,
		public readonly int $tools,
		public readonly float $cost,
		public readonly float $full_cost,
		public readonly int $in_tok,
		public readonly int $out_tok,
		public readonly int $cache_read,
		public readonly int $model_ms,
		public readonly int $tool_ms,
		public readonly int $errors,
		public readonly bool $partial,
	) {}
}

/** Time and error statistics of one tool. */
final class FLXAI_Tool_Stat {

	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly string $source,
		public readonly int $calls,
		public readonly int $avg_ms,
		public readonly int $max_ms,
		public readonly int $total_ms,
		public readonly int $errors,
	) {}
}

/** A usage report over a range. */
final class FLXAI_Report {

	/**
	 * @param list<FLXAI_Report_Day>    $daily
	 * @param list<FLXAI_Breakdown_Row> $by_source
	 * @param list<FLXAI_Breakdown_Row> $by_model
	 * @param list<FLXAI_Breakdown_Row> $by_purpose
	 * @param list<FLXAI_Tool_Stat>     $by_tool
	 * @param list<FLXAI_Breakdown_Row> $by_provider Only in a billing month.
	 */
	public function __construct(
		public readonly int $from,
		public readonly int $to,
		public readonly FLXAI_Report_Totals $totals,
		public readonly array $daily,
		public readonly array $by_source,
		public readonly array $by_model,
		public readonly array $by_purpose,
		public readonly array $by_tool,
		public readonly array $by_provider,
	) {}
}

/** A month of the billing list. */
final class FLXAI_Month {

	public function __construct(
		public readonly string $ym,
		public readonly string $label,
		public readonly float $cost,
		public readonly float $saved,
		public readonly int $calls,
	) {}
}

/** The OpenRouter account state from the last refresh. */
final class FLXAI_Credits {

	/**
	 * @param int        $checked   Unix time of the check.
	 * @param float|null $usage     Total usage of the key (USD).
	 * @param float|null $limit     Limit of the key (USD).
	 * @param float|null $remaining Remaining of the key limit (USD).
	 * @param float|null $credits   Remaining account credit (USD).
	 */
	public function __construct(
		public readonly int $checked,
		public readonly ?float $usage,
		public readonly ?float $limit,
		public readonly ?float $remaining,
		public readonly ?float $credits,
	) {}
}

/** A month of billing with its report and provider split. */
final class FLXAI_Billing {

	/**
	 * @param list<FLXAI_Month> $months Last 12 months.
	 */
	public function __construct(
		public readonly string $ym,
		public readonly FLXAI_Report $report,
		public readonly array $months,
		public readonly ?FLXAI_Credits $credits,
	) {}
}

/** One model request or tool call of the log. */
final class FLXAI_Log_Row {

	/**
	 * @param string $kind  model | tool.
	 * @param string $error Raw error ('limit', 'off' or a message); empty when it worked.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $ts,
		public readonly string $kind,
		public readonly string $source,
		public readonly string $source_label,
		public readonly string $purpose,
		public readonly string $purpose_label,
		public readonly int $chat_id,
		public readonly int $user_id,
		public readonly string $user_name,
		public readonly string $provider,
		public readonly string $model,
		public readonly string $model_label,
		public readonly string $tool,
		public readonly int $in_tok,
		public readonly int $out_tok,
		public readonly int $cache_read,
		public readonly int $cache_write,
		public readonly float $cost,
		public readonly bool $estimated,
		public readonly int $ms,
		public readonly bool $ok,
		public readonly string $error,
		public readonly string $error_label,
		public readonly bool $has_body,
	) {}
}

/** One message of a stored request or reply. */
final class FLXAI_Log_Message {

	public function __construct(
		public readonly string $role,
		public readonly string $text,
	) {}
}

/** The stored (shortened, masked) content of a log row. */
final class FLXAI_Log_Body {

	/**
	 * @param list<FLXAI_Log_Message> $request
	 * @param list<string>            $tool_calls
	 */
	public function __construct(
		public readonly int $log_id,
		public readonly array $request,
		public readonly string $reply,
		public readonly array $tool_calls,
		public readonly string $error,
	) {}
}

/** A system (plugin) that uses Flux AI. */
final class FLXAI_System {

	/**
	 * @param string $page Admin page of the system's own settings (relative to wp-admin); empty when none.
	 */
	public function __construct(
		public readonly string $source,
		public readonly string $label,
		public readonly bool $installed,
		public readonly bool $active,
		public readonly bool $on,
		public readonly string $model,
		public readonly string $model_label,
		public readonly int $calls,
		public readonly int $tools,
		public readonly int $tokens,
		public readonly float $cost,
		public readonly int $errors,
		public readonly ?int $last,
		public readonly string $page,
		public readonly bool $has_ai_settings,
	) {}
}
