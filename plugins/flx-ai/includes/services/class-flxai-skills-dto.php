<?php
/**
 * Value objects of the skills, their tests and the brand kit.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** One row of the skills list. */
final class FLXAI_Skill_Summary {

	public function __construct(
		public readonly string $slug,
		public readonly string $name,
		public readonly string $description,
		public readonly int $version,
		public readonly string $status,
		public readonly bool $bundled,
		public readonly string $source,
		public readonly bool $changed,
		public readonly bool $saved,
		public readonly int $file_count,
		public readonly bool $has_scripts,
		public readonly int $tasks,
		public readonly string $export_url,
	) {}
}

/** The skills page. */
final class FLXAI_Skills_Overview {

	/** @param list<FLXAI_Skill_Summary> $skills Skills, by name. */
	public function __construct(
		public readonly array $skills,
		public readonly bool $restrict,
	) {}
}

/** A file in the skill folder. */
final class FLXAI_Skill_File {

	public function __construct(
		public readonly string $path,
		public readonly int $size,
		public readonly string $download_url,
	) {}
}

/** The content of one skill file. */
final class FLXAI_Skill_File_Content {

	public function __construct(
		public readonly string $path,
		public readonly int $size,
		public readonly bool $text,
		public readonly string $content,
		public readonly string $note,
	) {}
}

/** A trigger test prompt. */
final class FLXAI_Skill_Trigger_Case {

	public function __construct(
		public readonly string $query,
		public readonly bool $should_trigger,
	) {}
}

/** A functional test. */
final class FLXAI_Skill_Eval_Case {

	/** @param list<string> $expectations Checks. */
	public function __construct(
		public readonly string $id,
		public readonly string $prompt,
		public readonly array $expectations,
	) {}
}

/** The saved tests of a skill. */
final class FLXAI_Skill_Evals {

	/**
	 * @param list<FLXAI_Skill_Trigger_Case> $triggers Trigger prompts.
	 * @param list<FLXAI_Skill_Eval_Case>    $cases    Functional tests.
	 */
	public function __construct(
		public readonly array $triggers,
		public readonly array $cases,
	) {}
}

/** One prompt of a trigger test run. */
final class FLXAI_Skill_Trigger_Row {

	/** @param list<string> $picked Skills the model picked. */
	public function __construct(
		public readonly string $query,
		public readonly bool $should_trigger,
		public readonly bool $triggered,
		public readonly array $picked,
	) {}
}

/** The last trigger test run. */
final class FLXAI_Skill_Trigger_Result {

	/** @param list<FLXAI_Skill_Trigger_Row> $rows Rows. */
	public function __construct(
		public readonly int $ts,
		public readonly string $model,
		public readonly int $hits,
		public readonly int $total,
		public readonly int $rate,
		public readonly float $cost,
		public readonly array $rows,
		public readonly bool $partial,
	) {}
}

/** One side (with or without the skill) of a functional test run. */
final class FLXAI_Skill_Side {

	public function __construct(
		public readonly int $passed,
		public readonly int $total,
		public readonly int $in,
		public readonly int $out,
		public readonly float $cost,
		public readonly int $ms,
		public readonly string $output,
	) {}
}

/** One check of a functional test run. */
final class FLXAI_Skill_Check {

	public function __construct(
		public readonly string $text,
		public readonly bool $with,
		public readonly bool $without,
	) {}
}

/** The last run of a functional test. */
final class FLXAI_Skill_Func_Result {

	/** @param list<FLXAI_Skill_Check> $checks Checks. */
	public function __construct(
		public readonly int $ts,
		public readonly string $id,
		public readonly string $prompt,
		public readonly FLXAI_Skill_Side $with,
		public readonly FLXAI_Skill_Side $without,
		public readonly array $checks,
		public readonly string $note,
		public readonly float $judge_cost,
	) {}
}

/** The last results of a skill's tests. */
final class FLXAI_Skill_Results {

	/** @param list<FLXAI_Skill_Func_Result> $functional One per functional test. */
	public function __construct(
		public readonly ?FLXAI_Skill_Trigger_Result $trigger,
		public readonly array $functional,
	) {}
}

/** One skill with everything the edit screen needs. */
final class FLXAI_Skill_Detail {

	/**
	 * @param list<string>           $tools      allowed-tools.
	 * @param list<FLXAI_Skill_File> $files      Folder files.
	 * @param list<string>           $tool_names Tool names to suggest.
	 */
	public function __construct(
		public readonly FLXAI_Skill_Summary $skill,
		public readonly string $body,
		public readonly array $tools,
		public readonly string $license,
		public readonly string $compatibility,
		public readonly string $example,
		public readonly array $files,
		public readonly array $tool_names,
		public readonly FLXAI_Skill_Evals $evals,
		public readonly FLXAI_Skill_Results $results,
	) {}
}

/** What an import did. */
final class FLXAI_Skill_Import_Result {

	/**
	 * @param list<string> $imported Skills written.
	 * @param list<string> $notes    Things to know (skipped files…).
	 */
	public function __construct(
		public readonly array $imported,
		public readonly array $notes,
		public readonly FLXAI_Skills_Overview $overview,
	) {}
}

/** A brand kit document. */
final class FLXAI_Brand_Doc {

	public function __construct(
		public readonly string $name,
		public readonly string $content,
	) {}
}

/** A color of the theme. */
final class FLXAI_Brand_Color {

	public function __construct(
		public readonly string $slug,
		public readonly string $name,
		public readonly string $color,
	) {}
}

/** The brand kit. */
final class FLXAI_Brand {

	/**
	 * @param list<int>                $partner_ids   Partner logo media IDs.
	 * @param list<string>             $partner_urls  Their URLs.
	 * @param list<FLXAI_Brand_Doc>    $docs          Markdown documents.
	 * @param list<FLXAI_Brand_Color>  $theme_palette Theme colors.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $tagline,
		public readonly string $website,
		public readonly string $phone,
		public readonly string $handle,
		public readonly string $color_primary,
		public readonly string $color_accent,
		public readonly string $color_dark,
		public readonly string $color_light,
		public readonly int $logo_id,
		public readonly string $logo_url,
		public readonly int $logo_light_id,
		public readonly string $logo_light_url,
		public readonly array $partner_ids,
		public readonly array $partner_urls,
		public readonly string $notes,
		public readonly array $docs,
		public readonly string $default_name,
		public readonly string $default_tagline,
		public readonly string $default_website,
		public readonly array $theme_palette,
	) {}
}
