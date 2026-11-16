<?php
/**
 * Flux SEO GraphQL schema (Flux GraphQL kit). Loaded on flx_gql_register, i.e. only inside a GraphQL request.
 * Resolvers call the services in includes/services; the SEO screen (wp-admin and FluxWP app) reads and writes only
 * through these fields. The `flxseo` option (General, Organization and Audit settings tabs) is typed by the kit
 * from `flx_settings_schema`: `flxSettings { flxseo { … } }` and `flxSettingsUpdate`.
 *
 * Everything here needs manage_options (as the classic tabs and REST), except `flxSeo` on content (public data
 * of the page head; the focus keyword needs edit_posts), `flxSeoGuide` (edit_posts) and `flxSeoContentSet`
 * (edit_post of the content).
 *
 * @package flx-seo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Service result → value, or the WP_Error as a typed GraphQL error.
 *
 * @template T
 * @param T|WP_Error $r Result.
 * @return T
 * @throws FLXGQL_Error On WP_Error.
 */
function flxseo_gql_ok( $r ) {
	if ( $r instanceof WP_Error ) {
		throw flx_gql_from_wp_error( $r );
	}
	return $r;
}

/**
 * Typed parent value of a field.
 *
 * @template T of object
 * @param mixed           $source Parent.
 * @param class-string<T> $class  Expected class.
 * @return T
 * @throws FLXGQL_Error When the parent has another type.
 */
function flxseo_gql_src( $source, string $class ) {
	if ( ! $source instanceof $class ) {
		throw flx_gql_error( 'INTERNAL' );
	}
	return $source;
}

/**
 * Resolver that reads a public property of the parent DTO.
 *
 * @param string $prop Property name.
 */
function flxseo_gql_prop( string $prop ): Closure {
	return static fn( $src ) => is_object( $src ) && isset( $src->{$prop} ) ? $src->{$prop} : null;
}

/**
 * Resolver for a Unix time property → FlxDateTime.
 *
 * @param string $prop Property name.
 */
function flxseo_gql_time( string $prop ): Closure {
	return static fn( $src ) => is_object( $src ) && isset( $src->{$prop} ) ? flx_gql_datetime( $src->{$prop} ) : null;
}

/**
 * Fields that only read DTO properties of the same name.
 *
 * @param array<string,array{0:string,1:string}> $map Field => [ GraphQL type, description ].
 * @param array<string,string>                   $props GraphQL field => DTO property when the names differ.
 * @return array<string,array<string,mixed>>
 */
function flxseo_gql_fields( array $map, array $props = array() ): array {
	$out = array();
	foreach ( $map as $name => $def ) {
		$out[ $name ] = array(
			'type'        => $def[0],
			'description' => $def[1],
			'resolve'     => 'FlxDateTime' === $def[0] || 'FlxDateTime!' === $def[0] ? flxseo_gql_time( $props[ $name ] ?? $name ) : flxseo_gql_prop( $props[ $name ] ?? $name ),
		);
	}
	return $out;
}

/* ---------------------------------------------------------------------------
 * Enums
 * ------------------------------------------------------------------------- */

flx_gql_enum(
	'FlxSeoPriority',
	array(
		'description' => __( 'How important an audit recommendation is.', 'flx-seo' ),
		'values'      => array(
			'CRITICAL' => array( 'value' => 'critical' ),
			'HIGH'     => array( 'value' => 'high' ),
			'MEDIUM'   => array( 'value' => 'medium' ),
			'LOW'      => array( 'value' => 'low' ),
		),
	)
);
flx_gql_enum(
	'FlxSeoAuditSource',
	array(
		'description' => __( 'Where a recommendation came from.', 'flx-seo' ),
		'values'      => array(
			'LOCAL'        => array( 'value' => 'local' ),
			'PSI_MOBILE'   => array( 'value' => 'psi-mobile' ),
			'PSI_DESKTOP'  => array( 'value' => 'psi-desktop' ),
		),
	)
);
flx_gql_enum(
	'FlxSeoStrategy',
	array(
		'description' => __( 'Device a PageSpeed run measures.', 'flx-seo' ),
		'values'      => array(
			'MOBILE'  => array( 'value' => 'mobile' ),
			'DESKTOP' => array( 'value' => 'desktop' ),
		),
	)
);
flx_gql_enum(
	'FlxSeoAuditMode',
	array(
		'description' => __( 'Kind of audit to run.', 'flx-seo' ),
		'values'      => array(
			'LOCAL'     => array(
				'value'       => 'local',
				'description' => __( 'On-site audit of the page; needs no key.', 'flx-seo' ),
			),
			'PAGESPEED' => array(
				'value'       => 'pagespeed',
				'description' => __( 'One PageSpeed Insights measurement (strategy required).', 'flx-seo' ),
			),
		),
	)
);
flx_gql_enum(
	'FlxSeoAuditTrigger',
	array(
		'description' => __( 'What started a saved audit report.', 'flx-seo' ),
		'values'      => array(
			'AUTO'   => array( 'value' => 'auto' ),
			'MANUAL' => array( 'value' => 'manual' ),
			'MCP'    => array( 'value' => 'mcp' ),
			'FIX'    => array( 'value' => 'fix' ),
		),
	)
);
flx_gql_enum(
	'FlxSeoIssueLevel',
	array(
		'description' => __( 'How serious a content recommendation is.', 'flx-seo' ),
		'values'      => array(
			'ERROR'   => array( 'value' => 'error' ),
			'WARNING' => array( 'value' => 'warning' ),
		),
	)
);
flx_gql_enum(
	'FlxSeoGuideLang',
	array(
		'description' => __( 'Language of the build guide.', 'flx-seo' ),
		'values'      => array(
			'TR' => array( 'value' => 'tr' ),
			'EN' => array( 'value' => 'en' ),
		),
	)
);

/* ---------------------------------------------------------------------------
 * Types: audit
 * ------------------------------------------------------------------------- */

flx_gql_type(
	'FlxSeoCounts',
	array(
		'description' => __( 'Recommendation counts by priority.', 'flx-seo' ),
		'fields'      => flxseo_gql_fields(
			array(
				'critical' => array( 'Int!', __( 'Critical recommendations.', 'flx-seo' ) ),
				'high'     => array( 'Int!', __( 'High priority recommendations.', 'flx-seo' ) ),
				'medium'   => array( 'Int!', __( 'Medium priority recommendations.', 'flx-seo' ) ),
				'low'      => array( 'Int!', __( 'Low priority recommendations.', 'flx-seo' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSeoFinding',
	array(
		'description' => __( 'One audit recommendation.', 'flx-seo' ),
		'fields'      => flxseo_gql_fields(
			array(
				'id'            => array( 'String!', __( 'Finding id (a Lighthouse audit id or a Flux SEO check).', 'flx-seo' ) ),
				'title'         => array( 'String!', __( 'Title in the user\'s language.', 'flx-seo' ) ),
				'priority'      => array( 'FlxSeoPriority!', __( 'How important it is.', 'flx-seo' ) ),
				'priorityLabel' => array( 'String!', __( 'Priority name in the user\'s language.', 'flx-seo' ) ),
				'category'      => array( 'String!', __( 'Section: performance, accessibility, best-practices, seo or agentic (packages may add more).', 'flx-seo' ) ),
				'categoryLabel' => array( 'String!', __( 'Section name in the user\'s language.', 'flx-seo' ) ),
				'also'          => array( '[String!]!', __( 'Other sections the recommendation counts in.', 'flx-seo' ) ),
				'detail'        => array( 'String!', __( 'Short detail such as a measured value; empty when none.', 'flx-seo' ) ),
				'fix'           => array( 'String!', __( 'How to fix it; empty when unknown.', 'flx-seo' ) ),
				'owner'         => array( 'String', __( 'Who fixes it (theme, content, server …); null when unknown.', 'flx-seo' ) ),
				'ownerLabel'    => array( 'String', __( 'Name of the owner; null when unknown.', 'flx-seo' ) ),
				'items'         => array( '[String!]!', __( 'Affected elements or addresses (at most 10).', 'flx-seo' ) ),
				'sources'       => array( '[FlxSeoAuditSource!]!', __( 'Which audits found it.', 'flx-seo' ) ),
				'fixId'         => array( 'String', __( 'One-click fix id; null when there is none or it is already on.', 'flx-seo' ) ),
				'fixLabel'      => array( 'String', __( 'Name of the one-click fix; null when there is none.', 'flx-seo' ) ),
			),
			array(
				'priorityLabel' => 'priority_label',
				'categoryLabel' => 'category_label',
				'also'          => 'also',
				'ownerLabel'    => 'owner_label',
				'fixId'         => 'fix_id',
				'fixLabel'      => 'fix_label',
			)
		),
	)
);

flx_gql_type(
	'FlxSeoScore',
	array(
		'description' => __( 'A PageSpeed score of one section.', 'flx-seo' ),
		'fields'      => flxseo_gql_fields(
			array(
				'category' => array( 'String!', __( 'Section key.', 'flx-seo' ) ),
				'label'    => array( 'String!', __( 'Section name.', 'flx-seo' ) ),
				'score'    => array( 'Int', __( '0–100; null when Lighthouse gave none.', 'flx-seo' ) ),
				'passed'   => array( 'Int!', __( 'Audits passed.', 'flx-seo' ) ),
				'total'    => array( 'Int!', __( 'Audits that count.', 'flx-seo' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSeoMetric',
	array(
		'description' => __( 'A Core Web Vitals style metric of a PageSpeed run.', 'flx-seo' ),
		'fields'      => flxseo_gql_fields(
			array(
				'key'   => array( 'String!', __( 'Lighthouse audit id (largest-contentful-paint …).', 'flx-seo' ) ),
				'label' => array( 'String!', __( 'Name.', 'flx-seo' ) ),
				'value' => array( 'String!', __( 'Display value such as "2.3 s".', 'flx-seo' ) ),
				'score' => array( 'Float', __( '0–1; null when none.', 'flx-seo' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSeoPageSpeedRun',
	array(
		'description' => __( 'The last PageSpeed Insights run of one device.', 'flx-seo' ),
		'fields'      => flxseo_gql_fields(
			array(
				'strategy'   => array( 'FlxSeoStrategy!', __( 'Device measured.', 'flx-seo' ) ),
				'time'       => array( 'FlxDateTime!', __( 'When it ran.', 'flx-seo' ) ),
				'url'        => array( 'String!', __( 'Measured address.', 'flx-seo' ) ),
				'lighthouse' => array( 'String!', __( 'Lighthouse version; empty when unknown.', 'flx-seo' ) ),
				'scores'     => array( '[FlxSeoScore!]!', __( 'Section scores.', 'flx-seo' ) ),
				'metrics'    => array( '[FlxSeoMetric!]!', __( 'Core Web Vitals and speed metrics.', 'flx-seo' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSeoAgenticCheck',
	array(
		'description' => __( 'One agent readiness check.', 'flx-seo' ),
		'fields'      => flxseo_gql_fields(
			array(
				'id'     => array( 'String!', __( 'Check id (llms, webmcp, md …).', 'flx-seo' ) ),
				'label'  => array( 'String!', __( 'What is checked.', 'flx-seo' ) ),
				'passed' => array( 'Boolean!', __( 'Whether the site passes.', 'flx-seo' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSeoLocalRun',
	array(
		'description' => __( 'The last on-site audit run.', 'flx-seo' ),
		'fields'      => flxseo_gql_fields(
			array(
				'time'       => array( 'FlxDateTime!', __( 'When it ran.', 'flx-seo' ) ),
				'url'        => array( 'String!', __( 'Audited address.', 'flx-seo' ) ),
				'durationMs' => array( 'Int!', __( 'How long the page took to answer (ms).', 'flx-seo' ) ),
				'httpStatus' => array( 'Int!', __( 'HTTP status the page answered with.', 'flx-seo' ) ),
			),
			array(
				'durationMs' => 'ms',
				'httpStatus' => 'code',
			)
		),
	)
);

flx_gql_type(
	'FlxSeoAudit',
	array(
		'description' => __( 'The current site audit report: scores, agent readiness and recommendations by priority.', 'flx-seo' ),
		'fields'      => array_merge(
			flxseo_gql_fields(
				array(
					'counts'     => array( 'FlxSeoCounts!', __( 'Recommendation counts by priority.', 'flx-seo' ) ),
					'local'      => array( 'FlxSeoLocalRun', __( 'Last on-site audit; null before the first audit.', 'flx-seo' ) ),
					'pageSpeed'  => array( '[FlxSeoPageSpeedRun!]!', __( 'Last PageSpeed run per device (none before the first run).', 'flx-seo' ) ),
					'agentic'    => array( '[FlxSeoAgenticCheck!]!', __( 'Agent readiness checks.', 'flx-seo' ) ),
					'findings'   => array( '[FlxSeoFinding!]!', __( 'Recommendations, most important first.', 'flx-seo' ) ),
					'auditUrl'   => array( 'String!', __( 'Address the audit form starts with.', 'flx-seo' ) ),
					'mcpServerOn' => array( 'Boolean!', __( 'The MCP server is on.', 'flx-seo' ) ),
					'llmsUrl'    => array( 'String!', __( 'Address of /llms.txt.', 'flx-seo' ) ),
				),
				array(
					'pageSpeed'   => 'page_speed',
					'auditUrl'    => 'audit_url',
					'mcpServerOn' => 'mcp_on',
					'llmsUrl'     => 'llms_url',
				)
			),
			array(
				'agenticPassed' => array(
					'type'        => 'Int!',
					'description' => __( 'Agent readiness checks the site passes.', 'flx-seo' ),
					'resolve'     => static fn( $a ): int => count( array_filter( flxseo_gql_src( $a, FLXSEO_Audit::class )->agentic, static fn( FLXSEO_Agentic_Check $c ): bool => $c->passed ) ),
				),
			)
		),
	)
);

flx_gql_type(
	'FlxSeoFindingRef',
	array(
		'description' => __( 'A recommendation named in a report change list.', 'flx-seo' ),
		'fields'      => flxseo_gql_fields(
			array(
				'id'    => array( 'String!', __( 'Finding id.', 'flx-seo' ) ),
				'title' => array( 'String!', __( 'Title in the user\'s language.', 'flx-seo' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSeoHistoryScore',
	array(
		'description' => __( 'A PageSpeed score in a saved report.', 'flx-seo' ),
		'fields'      => flxseo_gql_fields(
			array(
				'strategy' => array( 'FlxSeoStrategy!', __( 'Device.', 'flx-seo' ) ),
				'category' => array( 'String!', __( 'Section key.', 'flx-seo' ) ),
				'label'    => array( 'String!', __( 'Section name.', 'flx-seo' ) ),
				'score'    => array( 'Int', __( '0–100; null when none.', 'flx-seo' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSeoHistoryMetrics',
	array(
		'description' => __( 'Speed metrics of one device in a saved report.', 'flx-seo' ),
		'fields'      => flxseo_gql_fields(
			array(
				'strategy' => array( 'FlxSeoStrategy!', __( 'Device.', 'flx-seo' ) ),
				'fcp'      => array( 'String!', __( 'First Contentful Paint; empty when not measured.', 'flx-seo' ) ),
				'lcp'      => array( 'String!', __( 'Largest Contentful Paint; empty when not measured.', 'flx-seo' ) ),
				'tbt'      => array( 'String!', __( 'Total Blocking Time; empty when not measured.', 'flx-seo' ) ),
				'cls'      => array( 'String!', __( 'Cumulative Layout Shift; empty when not measured.', 'flx-seo' ) ),
				'si'       => array( 'String!', __( 'Speed Index; empty when not measured.', 'flx-seo' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSeoHistoryEntry',
	array(
		'description' => __( 'One saved audit report.', 'flx-seo' ),
		'fields'      => flxseo_gql_fields(
			array(
				'time'         => array( 'FlxDateTime!', __( 'When the report was saved.', 'flx-seo' ) ),
				'trigger'      => array( 'FlxSeoAuditTrigger!', __( 'What started the audit.', 'flx-seo' ) ),
				'triggerLabel' => array( 'String!', __( 'Trigger name in the user\'s language.', 'flx-seo' ) ),
				'url'          => array( 'String!', __( 'Audited address.', 'flx-seo' ) ),
				'counts'       => array( 'FlxSeoCounts!', __( 'Recommendation counts by priority.', 'flx-seo' ) ),
				'scores'       => array( '[FlxSeoHistoryScore!]!', __( 'PageSpeed scores (none when PageSpeed did not run).', 'flx-seo' ) ),
				'metrics'      => array( '[FlxSeoHistoryMetrics!]!', __( 'Speed metrics per device.', 'flx-seo' ) ),
				'agentic'      => array( 'String!', __( 'Agent readiness as "passed/total"; empty for old reports.', 'flx-seo' ) ),
				'added'        => array( '[FlxSeoFindingRef!]!', __( 'Recommendations that are new since the previous report.', 'flx-seo' ) ),
				'resolved'     => array( '[FlxSeoFindingRef!]!', __( 'Recommendations gone since the previous report.', 'flx-seo' ) ),
			),
			array( 'triggerLabel' => 'trigger_label' )
		),
	)
);

flx_gql_type(
	'FlxSeoHistory',
	array(
		'description' => __( 'Saved audit reports (newest first) and the automatic schedule.', 'flx-seo' ),
		'fields'      => flxseo_gql_fields(
			array(
				'entries' => array( '[FlxSeoHistoryEntry!]!', __( 'Reports, newest first.', 'flx-seo' ) ),
				'total'   => array( 'Int!', __( 'Saved reports (at most 90).', 'flx-seo' ) ),
				'next'    => array( 'FlxDateTime', __( 'Next automatic audit; null when automatic audits are off.', 'flx-seo' ) ),
				'running' => array( 'Boolean!', __( 'An automatic audit is running now.', 'flx-seo' ) ),
				'errors'  => array( '[String!]!', __( 'Problems of the last automatic audit.', 'flx-seo' ) ),
			)
		),
	)
);

/* ---------------------------------------------------------------------------
 * Types: content check, setup, guide, SEO meta
 * ------------------------------------------------------------------------- */

flx_gql_type(
	'FlxSeoContentIssue',
	array(
		'description' => __( 'A recommendation for one content item.', 'flx-seo' ),
		'fields'      => flxseo_gql_fields(
			array(
				'level'   => array( 'FlxSeoIssueLevel!', __( 'How serious it is.', 'flx-seo' ) ),
				'code'    => array( 'String!', __( 'Stable code (title_long, desc_missing, img_alt …).', 'flx-seo' ) ),
				'message' => array( 'String!', __( 'What is wrong, in the user\'s language.', 'flx-seo' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSeoContentRow',
	array(
		'description' => __( 'A published content item with recommendations.', 'flx-seo' ),
		'fields'      => flxseo_gql_fields(
			array(
				'databaseId' => array( 'Int!', __( 'Post ID.', 'flx-seo' ) ),
				'title'      => array( 'String!', __( 'Title.', 'flx-seo' ) ),
				'postType'   => array( 'String!', __( 'Post type.', 'flx-seo' ) ),
				'url'        => array( 'String!', __( 'Public address.', 'flx-seo' ) ),
				'editUrl'    => array( 'String', __( 'Editor address; null when the user cannot edit it.', 'flx-seo' ) ),
				'issues'     => array( '[FlxSeoContentIssue!]!', __( 'Recommendations.', 'flx-seo' ) ),
			),
			array(
				'databaseId' => 'id',
				'postType'   => 'type',
				'editUrl'    => 'edit_url',
			)
		),
	)
);

flx_gql_type(
	'FlxSeoContentCheck',
	array(
		'description' => __( 'SEO check of the published content.', 'flx-seo' ),
		'fields'      => flxseo_gql_fields(
			array(
				'checked' => array( 'Int!', __( 'Published items checked.', 'flx-seo' ) ),
				'total'   => array( 'Int!', __( 'Items with at least one recommendation.', 'flx-seo' ) ),
				'items'   => array( '[FlxSeoContentRow!]!', __( 'The 100 items with the most recommendations.', 'flx-seo' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxSeoSearchEngine',
	array(
		'description' => __( 'A search engine that takes a verification tag.', 'flx-seo' ),
		'fields'      => array_merge(
			flxseo_gql_fields(
				array(
					'key'         => array( 'String!', __( 'Setting key (verify_google …).', 'flx-seo' ) ),
					'name'        => array( 'String!', __( 'Search engine name.', 'flx-seo' ) ),
					'url'         => array( 'String!', __( 'Webmaster panel address.', 'flx-seo' ) ),
					'link'        => array( 'String!', __( 'Link text.', 'flx-seo' ) ),
					'placeholder' => array( 'String!', __( 'Example tag.', 'flx-seo' ) ),
					'help'        => array( 'String!', __( 'How to verify the site.', 'flx-seo' ) ),
				)
			),
			array(
				'field' => array(
					'type'        => 'String!',
					'description' => __( 'Field of flxSettings.flxseo that holds the verification code (verifyGoogle …).', 'flx-seo' ),
					'resolve'     => static fn( $e ): string => flx_gql_camel( flxseo_gql_src( $e, FLXSEO_Search_Engine::class )->key ),
				),
			)
		),
	)
);

flx_gql_type(
	'FlxSeoSetup',
	array(
		'description' => __( 'What the settings tabs show besides the setting values.', 'flx-seo' ),
		'fields'      => flxseo_gql_fields(
			array(
				'otherPlugin'   => array( 'Boolean!', __( 'Another SEO plugin is active; this plugin\'s meta and schema output is off.', 'flx-seo' ) ),
				'aiReady'       => array( 'Boolean!', __( 'Flux AI & MCP (the shared MCP server) is installed.', 'flx-seo' ) ),
				'blogName'      => array( 'String!', __( 'WordPress site name (placeholder of the site name field).', 'flx-seo' ) ),
				'homeUrl'       => array( 'String!', __( 'Site address.', 'flx-seo' ) ),
				'sitemapUrl'    => array( 'String!', __( 'Sitemap address.', 'flx-seo' ) ),
				'logoDefault'   => array( 'String!', __( 'Logo used when none is set.', 'flx-seo' ) ),
				'imageDefault'  => array( 'String!', __( 'Share image used when none is set.', 'flx-seo' ) ),
				'homeTitle'     => array( 'String!', __( 'Home page title in use (placeholder).', 'flx-seo' ) ),
				'homeDesc'      => array( 'String!', __( 'Home page description in use (placeholder).', 'flx-seo' ) ),
				'sitekit'       => array( 'Boolean!', __( 'Google Site Kit is connected to a Google account.', 'flx-seo' ) ),
				'sitePublic'    => array( 'Boolean!', __( 'The site is visible to search engines (Settings › Reading).', 'flx-seo' ) ),
				'indexnowKey'   => array( 'String!', __( 'IndexNow key file name (without .txt). It is public: search engines read it.', 'flx-seo' ) ),
				'indexnowUrl'   => array( 'String!', __( 'IndexNow key file address.', 'flx-seo' ) ),
				'indexnowLast'  => array( 'String!', __( 'Text of the last bulk submission; empty when none.', 'flx-seo' ) ),
				'indexnowReady' => array( 'Boolean!', __( 'IndexNow can send now (on, site public, not a local site).', 'flx-seo' ) ),
				'psiKeySaved'   => array( 'Boolean!', __( 'A PageSpeed API key is saved. The key itself is never returned.', 'flx-seo' ) ),
				'adminEmail'    => array( 'String!', __( 'Site admin email (default report address).', 'flx-seo' ) ),
				'engines'       => array( '[FlxSeoSearchEngine!]!', __( 'Search engines that take a verification tag.', 'flx-seo' ) ),
				'guideSections' => array( '[String!]!', __( 'Section titles of the new site build guide.', 'flx-seo' ) ),
			),
			array(
				'otherPlugin'   => 'other_plugin',
				'aiReady'       => 'ai_ready',
				'blogName'      => 'blog_name',
				'homeUrl'       => 'home_url',
				'sitemapUrl'    => 'sitemap_url',
				'logoDefault'   => 'logo_default',
				'imageDefault'  => 'image_default',
				'homeTitle'     => 'home_title',
				'homeDesc'      => 'home_desc',
				'sitePublic'    => 'site_public',
				'indexnowKey'   => 'indexnow_key',
				'indexnowUrl'   => 'indexnow_url',
				'indexnowLast'  => 'indexnow_last',
				'indexnowReady' => 'indexnow_ready',
				'psiKeySaved'   => 'psi_key_saved',
				'adminEmail'    => 'admin_email',
				'guideSections' => 'guide_sections',
			)
		),
	)
);

flx_gql_type(
	'FlxSeoGuide',
	array(
		'description' => __( 'The new site build guide (rules and checklist) as Markdown.', 'flx-seo' ),
		'fields'      => array_merge(
			flxseo_gql_fields(
				array(
					'markdown' => array( 'String!', __( 'Guide text.', 'flx-seo' ) ),
					'sections' => array( '[String!]!', __( 'Section titles.', 'flx-seo' ) ),
				)
			),
			array(
				'lang' => array(
					'type'        => 'FlxSeoGuideLang!',
					'description' => __( 'Language of the text.', 'flx-seo' ),
					'resolve'     => static fn( $g ): string => flxseo_gql_src( $g, FLXSEO_Guide::class )->lang,
				),
			)
		),
	)
);

flx_gql_type(
	'FlxSeoMeta',
	array(
		'description' => __( 'SEO fields of a page, post or term (the data in the page head).', 'flx-seo' ),
		'fields'      => array_merge(
			flxseo_gql_fields(
				array(
					'title'            => array( 'String!', __( 'Own SEO title; empty = the automatic one.', 'flx-seo' ) ),
					'description'      => array( 'String!', __( 'Own meta description; empty = the automatic one.', 'flx-seo' ) ),
					'noindex'          => array( 'Boolean!', __( 'Hidden from search engines.', 'flx-seo' ) ),
					'ogImage'          => array( 'String!', __( 'Own share image address; empty = the featured or default image.', 'flx-seo' ) ),
					'effectiveTitle'   => array( 'String!', __( 'Title the page uses.', 'flx-seo' ) ),
					'effectiveDescription' => array( 'String!', __( 'Description the page uses.', 'flx-seo' ) ),
				),
				array(
					'ogImage'              => 'og_image',
					'effectiveTitle'       => 'effective_title',
					'effectiveDescription' => 'effective_desc',
				)
			),
			array(
				'focusKeyword' => array(
					'type'        => 'String!',
					'description' => __( 'Focus keyword the editor set; only editors can read it.', 'flx-seo' ),
					'cap'         => 'edit_posts',
					'resolve'     => static fn( $m ): string => flxseo_gql_src( $m, FLXSEO_Meta::class )->focus_keyword,
				),
			)
		),
	)
);

/* ---------------------------------------------------------------------------
 * Fields on WordPress types
 * ------------------------------------------------------------------------- */

flx_gql_field(
	'ContentNode',
	'flxSeo',
	array(
		'type'        => 'FlxSeoMeta',
		'description' => __( 'SEO fields of this content (the data in the page head). Empty only when the SEO fields cannot be read.', 'flx-seo' ),
		'cap'         => 'public',
		'resolve'     => static function ( $node ): ?FLXSEO_Meta {
			$id = is_object( $node ) ? flx_gql_int( $node->databaseId ?? 0 ) : 0; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( $id < 1 ) {
				return null;
			}
			flx_gql_cache_tag( FLXSEO_Content_Service::cache_tag( $id ) );
			return FLXSEO_Content_Service::for_post( $id );
		},
	)
);

flx_gql_field(
	'TermNode',
	'flxSeo',
	array(
		'type'        => 'FlxSeoMeta',
		'description' => __( 'SEO title and description of this term.', 'flx-seo' ),
		'cap'         => 'public',
		'resolve'     => static function ( $node ): ?FLXSEO_Meta {
			$id = is_object( $node ) ? flx_gql_int( $node->databaseId ?? 0 ) : 0; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( $id < 1 ) {
				return null;
			}
			flx_gql_cache_tag( 'flx-seo:term:' . $id );
			return FLXSEO_Content_Service::for_term( $id );
		},
	)
);

/* ---------------------------------------------------------------------------
 * Queries
 * ------------------------------------------------------------------------- */

flx_gql_query(
	'flxSeoAudit',
	array(
		'type'        => 'FlxSeoAudit!',
		'description' => __( 'The current site audit report.', 'flx-seo' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): FLXSEO_Audit {
			flx_gql_cache_tag( FLXSEO_Audit_Service::CACHE_TAG );
			return FLXSEO_Audit_Service::audit();
		},
	)
);

flx_gql_query(
	'flxSeoAuditHistory',
	array(
		'type'        => 'FlxSeoHistory!',
		'description' => __( 'Saved audit reports (newest first) and the automatic schedule.', 'flx-seo' ),
		'cap'         => 'manage_options',
		'args'        => array(
			'limit' => array(
				'type'        => 'Int',
				'description' => __( 'Reports to return (1–90). Default 30.', 'flx-seo' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): FLXSEO_History {
			flx_gql_cache_tag( FLXSEO_Audit_Service::CACHE_TAG );
			return FLXSEO_Audit_Service::history( flx_gql_int( $args['limit'] ?? 30, 30 ) );
		},
	)
);

flx_gql_query(
	'flxSeoContentCheck',
	array(
		'type'        => 'FlxSeoContentCheck!',
		'description' => __( 'SEO check of the published content: what is missing or too long, per item.', 'flx-seo' ),
		'cap'         => 'manage_options',
		'args'        => array(
			'postType' => array(
				'type'        => 'String',
				'description' => __( 'Check one post type only. Default: every public type.', 'flx-seo' ),
			),
			'limit'    => array(
				'type'        => 'Int',
				'description' => __( 'Items to check (1–500). Default 300.', 'flx-seo' ),
			),
		),
		'resolve'     => static function ( $root, array $args ): FLXSEO_Content_Check {
			return FLXSEO_Audit_Service::content_check( flx_gql_str( $args['postType'] ?? '' ), flx_gql_int( $args['limit'] ?? 300, 300 ) );
		},
	)
);

flx_gql_query(
	'flxSeoSetup',
	array(
		'type'        => 'FlxSeoSetup!',
		'description' => __( 'What the settings tabs show besides the setting values: search engines, sitemap, IndexNow state.', 'flx-seo' ),
		'cap'         => 'manage_options',
		'resolve'     => static function (): FLXSEO_Setup {
			flx_gql_cache_tag( FLXSEO_Settings_Service::CACHE_TAG );
			return FLXSEO_Settings_Service::setup();
		},
	)
);

flx_gql_query(
	'flxSeoGuide',
	array(
		'type'        => 'FlxSeoGuide!',
		'description' => __( 'The new site build guide (rules and checklist for SEO, speed, accessibility, security and agent readiness).', 'flx-seo' ),
		'cap'         => 'edit_posts',
		'args'        => array(
			'lang' => array(
				'type'        => 'FlxSeoGuideLang',
				'description' => __( 'Language. Default: the user\'s language (Turkish or English).', 'flx-seo' ),
			),
		),
		'resolve'     => static fn( $root, array $args ): FLXSEO_Guide => FLXSEO_Audit_Service::guide( flx_gql_str( $args['lang'] ?? '' ) ),
	)
);

/* ---------------------------------------------------------------------------
 * Mutations
 * ------------------------------------------------------------------------- */

flx_gql_mutation(
	'flxSeoAuditRun',
	array(
		'description' => __( 'Runs an audit: the on-site audit, or one PageSpeed measurement (15–40 s; call it for mobile, then desktop, then run the on-site audit).', 'flx-seo' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'mode'     => array(
				'type'        => 'FlxSeoAuditMode!',
				'description' => __( 'Kind of audit.', 'flx-seo' ),
			),
			'strategy' => array(
				'type'        => 'FlxSeoStrategy',
				'description' => __( 'Device for a PageSpeed run. Default mobile.', 'flx-seo' ),
			),
			'url'      => array(
				'type'        => 'String',
				'description' => __( 'Address of this site to audit. Any other address audits the home page.', 'flx-seo' ),
			),
		),
		'output'      => array(
			'audit'   => array(
				'type'        => 'FlxSeoAudit',
				'description' => __( 'The report after the run.', 'flx-seo' ),
				'resolve'     => static fn(): FLXSEO_Audit => FLXSEO_Audit_Service::audit(),
			),
			'history' => array(
				'type'        => 'FlxSeoHistory',
				'description' => __( 'Saved reports after the run.', 'flx-seo' ),
				'resolve'     => static fn(): FLXSEO_History => FLXSEO_Audit_Service::history( 30 ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$url = flx_gql_str( $input['url'] ?? '' );
			$r   = 'pagespeed' === flx_gql_str( $input['mode'] ?? '' )
				? FLXSEO_Audit_Service::run_pagespeed( flx_gql_str( $input['strategy'] ?? 'mobile', 'mobile' ), $url )
				: FLXSEO_Audit_Service::run_local( $url );
			return $r instanceof WP_Error ? $r : array();
		},
	)
);

flx_gql_mutation(
	'flxSeoFixApply',
	array(
		'description' => __( 'Turns on a one-click fix a recommendation offers, then refreshes the on-site audit.', 'flx-seo' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'id' => array(
				'type'        => 'String!',
				'description' => __( 'Fix id (a finding\'s fixId).', 'flx-seo' ),
			),
		),
		'output'      => array(
			'audit' => array(
				'type'        => 'FlxSeoAudit',
				'description' => __( 'The report after the fix.', 'flx-seo' ),
				'resolve'     => static fn(): FLXSEO_Audit => FLXSEO_Audit_Service::audit(),
			),
		),
		'resolve'     => static function ( array $input ) {
			$r = FLXSEO_Audit_Service::apply_fix( flx_gql_str( $input['id'] ?? '' ), 'graphql' );
			return $r instanceof WP_Error ? $r : array();
		},
	)
);

flx_gql_mutation(
	'flxSeoPsiKeySave',
	array(
		'description' => __( 'Saves or deletes the PageSpeed Insights API key. The key is never returned.', 'flx-seo' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'key'   => array(
				'type'        => 'String',
				'description' => __( 'New key. Empty keeps the saved key.', 'flx-seo' ),
			),
			'clear' => array(
				'type'        => 'Boolean',
				'description' => __( 'Delete the saved key.', 'flx-seo' ),
			),
		),
		'output'      => array(
			'saved' => array(
				'type'        => 'Boolean!',
				'description' => __( 'A key is saved after the change.', 'flx-seo' ),
				'resolve'     => static fn(): bool => '' !== flxseo_psi_key(),
			),
		),
		'resolve'     => static function ( array $input ) {
			FLXSEO_Audit_Service::set_psi_key( isset( $input['key'] ) ? flx_gql_str( $input['key'] ) : null, ! empty( $input['clear'] ) );
			flx_gql_purge( array( FLXSEO_Settings_Service::CACHE_TAG ) );
			return array();
		},
	)
);

flx_gql_mutation(
	'flxSeoIndexNowSubmit',
	array(
		'description' => __( 'Sends every public address of the site to Bing, Yandex and the other IndexNow search engines now.', 'flx-seo' ),
		'cap'         => 'manage_options',
		'input'       => array(),
		'output'      => array(
			'submitted' => array(
				'type'        => 'Int',
				'description' => __( 'Addresses sent.', 'flx-seo' ),
			),
			'setup'     => array(
				'type'        => 'FlxSeoSetup',
				'description' => __( 'Setup info after the submission (last submission text).', 'flx-seo' ),
				'resolve'     => static fn(): FLXSEO_Setup => FLXSEO_Settings_Service::setup(),
			),
		),
		'resolve'     => static function () {
			$r = FLXSEO_Settings_Service::submit_indexnow();
			if ( $r instanceof WP_Error ) {
				return $r;
			}
			flx_gql_purge( array( FLXSEO_Settings_Service::CACHE_TAG ) );
			return array( 'submitted' => $r );
		},
	)
);

flx_gql_mutation(
	'flxSeoContentSet',
	array(
		'description' => __( 'Changes the SEO fields of a post or page. Fields you leave out stay as they are.', 'flx-seo' ),
		'cap'         => 'edit_posts',
		'input'       => array(
			'databaseId'   => array(
				'type'        => 'Int!',
				'description' => __( 'Post ID.', 'flx-seo' ),
			),
			'title'        => array(
				'type'        => 'String',
				'description' => __( 'Own SEO title; empty = automatic.', 'flx-seo' ),
			),
			'description'  => array(
				'type'        => 'String',
				'description' => __( 'Own meta description; empty = automatic.', 'flx-seo' ),
			),
			'focusKeyword' => array(
				'type'        => 'String',
				'description' => __( 'Focus keyword.', 'flx-seo' ),
			),
			'noindex'      => array(
				'type'        => 'Boolean',
				'description' => __( 'Hide from search engines.', 'flx-seo' ),
			),
			'ogImage'      => array(
				'type'        => 'String',
				'description' => __( 'Own share image address; empty = automatic.', 'flx-seo' ),
			),
		),
		'output'      => array(
			'seo' => array(
				'type'        => 'FlxSeoMeta',
				'description' => __( 'SEO fields after the change.', 'flx-seo' ),
			),
		),
		'resolve'     => static function ( array $input ) {
			$in = array();
			foreach ( array( 'title' => 'title', 'description' => 'description', 'focusKeyword' => 'focus_keyword', 'ogImage' => 'og_image' ) as $gql => $key ) {
				if ( array_key_exists( $gql, $input ) && null !== $input[ $gql ] ) {
					$in[ $key ] = flx_gql_str( $input[ $gql ] );
				}
			}
			if ( array_key_exists( 'noindex', $input ) && null !== $input['noindex'] ) {
				$in['noindex'] = (bool) $input['noindex'];
			}
			$r = FLXSEO_Content_Service::set_post( flx_gql_int( $input['databaseId'] ?? 0 ), $in );
			return $r instanceof WP_Error ? $r : array( 'seo' => $r );
		},
	)
);
