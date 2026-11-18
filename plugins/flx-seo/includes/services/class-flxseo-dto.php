<?php
/**
 * Typed values the Flux SEO services return. GraphQL, REST and Flux AI read the same shapes.
 *
 * @package flx-seo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Untyped legacy values (options, the audit report) → typed values. The services never trust a stored shape.
 */
final class FLXSEO_Util {

	/**
	 * @param mixed $v Value.
	 */
	public static function str( $v, string $default = '' ): string {
		return is_scalar( $v ) ? (string) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 */
	public static function int( $v, int $default = 0 ): int {
		return is_numeric( $v ) ? (int) $v : $default;
	}

	/**
	 * @param mixed $v Value.
	 * @return array<mixed>
	 */
	public static function arr( $v ): array {
		return is_array( $v ) ? $v : array();
	}

	/**
	 * @param mixed $v Value.
	 * @return list<string>
	 */
	public static function strings( $v ): array {
		$out = array();
		foreach ( self::arr( $v ) as $s ) {
			if ( is_scalar( $s ) && '' !== (string) $s ) {
				$out[] = (string) $s;
			}
		}
		return $out;
	}
}

/**
 * One audit recommendation.
 */
final class FLXSEO_Finding {

	/**
	 * @param string       $id             Finding id (a Lighthouse audit id or a Flux SEO check).
	 * @param string       $title          Title in the user's language.
	 * @param string       $priority       critical|high|medium|low.
	 * @param string       $priority_label Priority name.
	 * @param string       $category       performance|accessibility|best-practices|seo|agentic.
	 * @param string       $category_label Section name.
	 * @param list<string> $also           Other sections the finding counts in.
	 * @param string       $detail         Short detail (e.g. "2.3 s"); empty when none.
	 * @param string       $fix            How to fix; empty when unknown.
	 * @param string|null  $owner          Who fixes it (theme, content, server …).
	 * @param string|null  $owner_label    Owner name.
	 * @param list<string> $items          Affected elements or URLs (at most 10).
	 * @param list<string> $sources        local|psi-mobile|psi-desktop.
	 * @param string|null  $fix_id         One-click fix id, when one is available and not on yet.
	 * @param string|null  $fix_label      Name of the one-click fix.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $title,
		public readonly string $priority,
		public readonly string $priority_label,
		public readonly string $category,
		public readonly string $category_label,
		public readonly array $also,
		public readonly string $detail,
		public readonly string $fix,
		public readonly ?string $owner,
		public readonly ?string $owner_label,
		public readonly array $items,
		public readonly array $sources,
		public readonly ?string $fix_id,
		public readonly ?string $fix_label,
	) {}
}

/**
 * Recommendation counts by priority.
 */
final class FLXSEO_Counts {

	public function __construct(
		public readonly int $critical,
		public readonly int $high,
		public readonly int $medium,
		public readonly int $low,
	) {}
}

/**
 * A PageSpeed category score.
 */
final class FLXSEO_Score {

	/**
	 * @param string   $category Section key.
	 * @param string   $label    Section name.
	 * @param int|null $score    0–100; null when Lighthouse gave none.
	 * @param int      $passed   Passed audits.
	 * @param int      $total    Audits that count.
	 */
	public function __construct(
		public readonly string $category,
		public readonly string $label,
		public readonly ?int $score,
		public readonly int $passed,
		public readonly int $total,
	) {}
}

/**
 * A Core Web Vitals style metric of a PageSpeed run.
 */
final class FLXSEO_Metric {

	/**
	 * @param string     $key   Lighthouse audit id (largest-contentful-paint …).
	 * @param string     $label Name.
	 * @param string     $value Display value ("2.3 s").
	 * @param float|null $score 0–1; null when none.
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly string $value,
		public readonly ?float $score,
	) {}
}

/**
 * The last PageSpeed run of one device.
 */
final class FLXSEO_Page_Speed {

	/**
	 * @param string             $strategy   mobile|desktop.
	 * @param int                $time       Unix time.
	 * @param string             $url        Measured address.
	 * @param string             $lighthouse Lighthouse version.
	 * @param list<FLXSEO_Score> $scores     Category scores.
	 * @param list<FLXSEO_Metric> $metrics   Metrics.
	 */
	public function __construct(
		public readonly string $strategy,
		public readonly int $time,
		public readonly string $url,
		public readonly string $lighthouse,
		public readonly array $scores,
		public readonly array $metrics,
	) {}
}

/**
 * One agent readiness check (llms.txt, WebMCP …).
 */
final class FLXSEO_Agentic_Check {

	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly bool $passed,
	) {}
}

/**
 * The last on-site audit run.
 */
final class FLXSEO_Local_Run {

	public function __construct(
		public readonly int $time,
		public readonly string $url,
		public readonly int $ms,
		public readonly int $code,
	) {}
}

/**
 * The current audit report.
 */
final class FLXSEO_Audit {

	/**
	 * @param FLXSEO_Counts              $counts      Counts by priority.
	 * @param FLXSEO_Local_Run|null      $local       Last on-site run; null before the first audit.
	 * @param list<FLXSEO_Page_Speed>    $page_speed  PageSpeed runs (mobile, desktop).
	 * @param list<FLXSEO_Agentic_Check> $agentic     Agent readiness checks.
	 * @param list<FLXSEO_Finding>       $findings    Recommendations, most important first.
	 * @param string                     $audit_url   Address the audit form starts with.
	 * @param bool                       $mcp_on      MCP server is on.
	 * @param string                     $llms_url    /llms.txt address.
	 */
	public function __construct(
		public readonly FLXSEO_Counts $counts,
		public readonly ?FLXSEO_Local_Run $local,
		public readonly array $page_speed,
		public readonly array $agentic,
		public readonly array $findings,
		public readonly string $audit_url,
		public readonly bool $mcp_on,
		public readonly string $llms_url,
	) {}
}

/**
 * A finding named in a history change list.
 */
final class FLXSEO_Finding_Ref {

	public function __construct(
		public readonly string $id,
		public readonly string $title,
	) {}
}

/**
 * Score of one section on one device in a history report.
 */
final class FLXSEO_History_Score {

	public function __construct(
		public readonly string $strategy,
		public readonly string $category,
		public readonly string $label,
		public readonly ?int $score,
	) {}
}

/**
 * Core Web Vitals of one device in a history report.
 */
final class FLXSEO_History_Metrics {

	public function __construct(
		public readonly string $strategy,
		public readonly string $fcp,
		public readonly string $lcp,
		public readonly string $tbt,
		public readonly string $cls,
		public readonly string $si,
	) {}
}

/**
 * One saved audit report.
 */
final class FLXSEO_History_Entry {

	/**
	 * @param int                         $time          Unix time.
	 * @param string                      $trigger       auto|manual|mcp|fix.
	 * @param string                      $trigger_label Trigger name.
	 * @param string                      $url           Audited address.
	 * @param FLXSEO_Counts               $counts        Counts by priority.
	 * @param list<FLXSEO_History_Score>  $scores        PageSpeed scores.
	 * @param list<FLXSEO_History_Metrics> $metrics      Metrics per device.
	 * @param string                      $agentic       "passed/total".
	 * @param list<FLXSEO_Finding_Ref>    $added         New since the previous report.
	 * @param list<FLXSEO_Finding_Ref>    $resolved      Gone since the previous report.
	 */
	public function __construct(
		public readonly int $time,
		public readonly string $trigger,
		public readonly string $trigger_label,
		public readonly string $url,
		public readonly FLXSEO_Counts $counts,
		public readonly array $scores,
		public readonly array $metrics,
		public readonly string $agentic,
		public readonly array $added,
		public readonly array $resolved,
	) {}
}

/**
 * Report history and the automatic schedule.
 */
final class FLXSEO_History {

	/**
	 * @param list<FLXSEO_History_Entry> $entries Newest first.
	 * @param int                        $total   Saved reports.
	 * @param int|null                   $next    Next automatic audit (Unix time); null when off.
	 * @param bool                       $running An automatic audit is running now.
	 * @param list<string>               $errors  Problems of the last automatic audit.
	 */
	public function __construct(
		public readonly array $entries,
		public readonly int $total,
		public readonly ?int $next,
		public readonly bool $running,
		public readonly array $errors,
	) {}
}

/**
 * One recommendation of the SEO check tab (content level).
 */
final class FLXSEO_Content_Issue {

	/**
	 * @param string $level   error|warning.
	 * @param string $code    Stable code (title_long, desc_missing …).
	 * @param string $message What is wrong, in the user's language.
	 */
	public function __construct(
		public readonly string $level,
		public readonly string $code,
		public readonly string $message,
	) {}
}

/**
 * A published content item with recommendations.
 */
final class FLXSEO_Content_Row {

	/**
	 * @param int                       $id       Post ID.
	 * @param string                    $title    Title.
	 * @param string                    $type     Post type.
	 * @param string                    $url      Public address.
	 * @param string|null               $edit_url Editor address; null without permission.
	 * @param list<FLXSEO_Content_Issue> $issues  Recommendations.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly string $type,
		public readonly string $url,
		public readonly ?string $edit_url,
		public readonly array $issues,
	) {}
}

/**
 * Result of the content check.
 */
final class FLXSEO_Content_Check {

	/**
	 * @param int                      $checked Published items checked.
	 * @param list<FLXSEO_Content_Row> $items   Items with at least one recommendation, most first.
	 * @param int                      $total   Items with recommendations.
	 */
	public function __construct(
		public readonly int $checked,
		public readonly array $items,
		public readonly int $total,
	) {}
}

/**
 * A search engine with a verification tag.
 */
final class FLXSEO_Search_Engine {

	/**
	 * @param string $key         Setting key (verify_google …).
	 * @param string $name        Search engine name.
	 * @param string $url         Webmaster panel address.
	 * @param string $link        Link text.
	 * @param string $placeholder Example tag.
	 * @param string $help        How to verify.
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $name,
		public readonly string $url,
		public readonly string $link,
		public readonly string $placeholder,
		public readonly string $help,
	) {}
}

/**
 * Everything the settings tabs show besides the setting values.
 */
final class FLXSEO_Setup {

	/**
	 * @param bool                      $other_plugin   Another SEO plugin is active (this plugin's meta and schema output is off).
	 * @param bool                      $ai_ready       Flux AI & MCP (MCP server) is installed.
	 * @param string                    $blog_name      WordPress site name (placeholder).
	 * @param string                    $home_url       Site address.
	 * @param string                    $sitemap_url    Sitemap address.
	 * @param string                    $logo_default   Logo used when none is set.
	 * @param string                    $image_default  Share image used when none is set.
	 * @param string                    $home_title     Home title in use (placeholder).
	 * @param string                    $home_desc      Home description in use (placeholder).
	 * @param bool                      $sitekit        Google Site Kit is connected.
	 * @param bool                      $site_public    The site is visible to search engines.
	 * @param string                    $indexnow_key   IndexNow key file name (without .txt).
	 * @param string                    $indexnow_url   IndexNow key file address.
	 * @param string                    $indexnow_last  Text of the last bulk submission; empty when none.
	 * @param bool                      $indexnow_ready IndexNow can send (on, public, not a local site).
	 * @param bool                      $psi_key_saved  A PageSpeed API key is saved.
	 * @param string                    $admin_email    Site admin email (placeholder of the report address).
	 * @param list<FLXSEO_Search_Engine> $engines       Search engines.
	 * @param list<string>              $guide_sections Titles of the build guide.
	 */
	public function __construct(
		public readonly bool $other_plugin,
		public readonly bool $ai_ready,
		public readonly string $blog_name,
		public readonly string $home_url,
		public readonly string $sitemap_url,
		public readonly string $logo_default,
		public readonly string $image_default,
		public readonly string $home_title,
		public readonly string $home_desc,
		public readonly bool $sitekit,
		public readonly bool $site_public,
		public readonly string $indexnow_key,
		public readonly string $indexnow_url,
		public readonly string $indexnow_last,
		public readonly bool $indexnow_ready,
		public readonly bool $psi_key_saved,
		public readonly string $admin_email,
		public readonly array $engines,
		public readonly array $guide_sections,
	) {}
}

/**
 * The build guide as Markdown.
 */
final class FLXSEO_Guide {

	/**
	 * @param string       $lang     tr|en.
	 * @param string       $markdown Guide text.
	 * @param list<string> $sections Section titles.
	 */
	public function __construct(
		public readonly string $lang,
		public readonly string $markdown,
		public readonly array $sections,
	) {}
}

/**
 * SEO fields of a post or a term.
 */
final class FLXSEO_Meta {

	/**
	 * @param string $title           Own SEO title; empty = automatic.
	 * @param string $description     Own meta description; empty = automatic.
	 * @param string $focus_keyword   Focus keyword (editors only).
	 * @param bool   $noindex         Hidden from search engines.
	 * @param string $og_image        Own share image address.
	 * @param string $effective_title Title the page uses.
	 * @param string $effective_desc  Description the page uses.
	 */
	public function __construct(
		public readonly string $title,
		public readonly string $description,
		public readonly string $focus_keyword,
		public readonly bool $noindex,
		public readonly string $og_image,
		public readonly string $effective_title,
		public readonly string $effective_desc,
	) {}
}
