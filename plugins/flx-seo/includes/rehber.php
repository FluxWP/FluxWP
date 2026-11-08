<?php
/**
 * Site yapım rehberi (guide/site-build-guide.<dil>.md) ve denetim araçları:
 *  - MCP araçları `flxseo_site_build_guide`, `flxseo_site_audit` (Flux AI sohbetine de kendiliğinden gelir),
 *  - MCP kaynağı `flxseo://guide/site-build`, istem `site_audit_fix`,
 *  - Flux AI sohbet talimatı.
 * Rehbere ek bölüm: filtre `flxseo_guide` ( $markdown, $topic, $lang ).
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Rehber dili: tr ya da en (istenmezse kullanıcı/site dili). */
function flxseo_guide_lang( $lang = '' ) {
	$lang = strtolower( (string) $lang );
	if ( ! in_array( $lang, array( 'tr', 'en' ), true ) ) {
		$lang = 0 === strpos( determine_locale(), 'tr' ) ? 'tr' : 'en';
	}
	return $lang;
}

function flxseo_guide_topics() {
	return array( 'all', 'workflow', 'performance', 'accessibility', 'seo', 'best-practices', 'agentic', 'wordpress', 'checklist' );
}

/**
 * Rehber metni (Markdown).
 *
 * @param string $topic flxseo_guide_topics() içinden; 'all' tümü.
 * @param string $lang  tr|en.
 */
function flxseo_guide( $topic = 'all', $lang = '' ) {
	$lang = flxseo_guide_lang( $lang );
	$file = FLXSEO_DIR . 'guide/site-build-guide.' . $lang . '.md';
	$md   = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( 'all' !== $topic && in_array( $topic, flxseo_guide_topics(), true ) ) {
		$parts = preg_split( '/^<!-- topic:([a-z-]+) -->\s*$/m', $md, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out   = trim( (string) strtok( $md, "\n" ) ) . "\n\n";
		for ( $i = 1; $i < count( $parts ); $i += 2 ) {
			if ( $parts[ $i ] === $topic ) {
				$out .= trim( $parts[ $i + 1 ] ) . "\n";
			}
		}
		$md = $out;
	} else {
		$md = preg_replace( '/^<!-- topic:[a-z-]+ -->\s*\n/m', '', $md );
	}
	return (string) apply_filters( 'flxseo_guide', $md, $topic, $lang );
}

/** Bölüm başlıkları (panelde içindekiler). */
function flxseo_guide_sections( $lang = '' ) {
	preg_match_all( '/^## (.+)$/m', flxseo_guide( 'all', $lang ), $m );
	return $m[1];
}

/* ---------------------------------------------------------------------------
 * MCP araçları (flx_mcp_tools: Flux AI MCP sunucusu ve sohbeti)
 * ------------------------------------------------------------------------- */

add_filter(
	'flx_mcp_tools',
	function ( $tools ) {
		$tools['flxseo_site_build_guide'] = array(
			'aliases'     => array( 'site_build_guide' ), // Flux SEO 1.5.x ve öncesindeki ad.
			'source'      => 'flx-seo',
			'title'       => __( 'Site build guide', 'flx-seo' ),
			'description' => 'FluxWP site build guide (Markdown): rules for SEO, Core Web Vitals performance, accessibility (ARIA patterns), security headers and agent readiness (llms.txt, WebMCP) plus a pre-launch checklist. Read it BEFORE creating or redesigning pages, patterns, themes or presets, then follow it. topic narrows to one section; lang tr|en (default: user language).',
			'readonly'    => true,
			'cap'         => 'edit_posts',
			'log'         => false,
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'topic' => array( 'type' => 'string', 'enum' => flxseo_guide_topics(), 'default' => 'all' ),
					'lang'  => array( 'type' => 'string', 'enum' => array( 'tr', 'en' ) ),
				),
			),
			'run'         => function ( $a ) {
				$topic = in_array( $a['topic'] ?? 'all', flxseo_guide_topics(), true ) ? $a['topic'] ?? 'all' : 'all';
				$lang  = flxseo_guide_lang( $a['lang'] ?? '' );
				return array(
					'topic'    => $topic,
					'lang'     => $lang,
					'version'  => FLXSEO_VERSION,
					'sections' => flxseo_guide_sections( $lang ),
					'markdown' => flxseo_guide( $topic, $lang ),
				);
			},
		);
		$tools['flxseo_site_audit'] = array(
			'aliases'     => array( 'site_audit' ), // Flux SEO 1.5.x ve öncesindeki ad.
			'source'      => 'flx-seo',
			'title'       => __( 'Site audit', 'flx-seo' ),
			'description' => 'PageSpeed-style site audit sorted by priority (critical, high, medium, low) across performance, accessibility, best-practices, seo and agentic. refresh=true re-runs the on-site audit (~2-5 s); every run is saved to history (see flxseo_site_audit_history). pagespeed=mobile|desktop also runs Google PageSpeed Insights (15-40 s, uses the API key from settings). Each finding: id, category, priority, title, fix (how to fix), owner (theme/content/server/flx-seo/setting), items (offending elements), fix_id (one-step fix: pass it to flxseo_system_fix after telling the user). Fix in priority order, then re-run.',
			'readonly'    => true,
			'cap'         => 'manage_options',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'refresh'      => array( 'type' => 'boolean', 'default' => false ),
					'pagespeed'    => array( 'type' => 'string', 'enum' => array( 'none', 'mobile', 'desktop' ), 'default' => 'none' ),
					'url'          => array( 'type' => 'string', 'description' => 'Page of this site to audit (default: home page).' ),
					'category'     => array( 'type' => 'string', 'enum' => array_keys( flxseo_audit_categories() ) ),
					'min_priority' => array( 'type' => 'string', 'enum' => array_keys( flxseo_audit_priorities() ), 'default' => 'low' ),
				),
			),
			'run'         => function ( $a ) {
				$url = ! empty( $a['url'] ) ? flxseo_audit_url( $a['url'] ) : '';
				$err = array();
				$ran = false;
				if ( ! empty( $a['pagespeed'] ) && in_array( $a['pagespeed'], array( 'mobile', 'desktop' ), true ) ) {
					$p = flxseo_psi_run( $a['pagespeed'], $url );
					if ( is_wp_error( $p ) ) {
						$err[] = $p->get_error_message();
					} else {
						$ran = true;
					}
				}
				if ( ! empty( $a['refresh'] ) || ! get_option( 'flxseo_audit' ) ) {
					$l = flxseo_audit_run_local( $url );
					if ( is_wp_error( $l ) ) {
						$err[] = $l->get_error_message();
					} else {
						$ran = true;
					}
				}
				if ( $ran ) {
					do_action( 'flxseo_audit_done', 'mcp' );
				}
				$r     = flxseo_audit_report();
				$order = flxseo_audit_priorities();
				$min   = $order[ $a['min_priority'] ?? 'low' ][0] ?? 3;
				$cat   = (string) ( $a['category'] ?? '' );
				$list  = array();
				foreach ( $r['findings'] as $f ) {
					if ( ( $order[ $f['priority'] ][0] ?? 9 ) > $min ) {
						continue;
					}
					if ( $cat && $cat !== $f['category'] && ! in_array( $cat, (array) $f['also'], true ) ) {
						continue;
					}
					unset( $f['also'] );
					$list[] = $f;
				}
				$scores = array();
				foreach ( (array) $r['psi'] as $s => $p ) {
					$scores[ $s ] = array(
						'time'    => gmdate( 'c', (int) ( $p['time'] ?? 0 ) ),
						'scores'  => wp_list_pluck( (array) ( $p['scores'] ?? array() ), 'score' ),
						'metrics' => wp_list_pluck( (array) ( $p['metrics'] ?? array() ), 'value' ),
					);
				}
				$agentic = flxseo_audit_agentic( $r );
				return array(
					'audited_url' => $r['local']['url'] ?? home_url( '/' ),
					'audited_at'  => empty( $r['local']['time'] ) ? null : gmdate( 'c', (int) $r['local']['time'] ),
					'counts'      => $r['counts'],
					'pagespeed'   => $scores ? $scores : null,
					'agentic'     => count( array_filter( $agentic, fn( $c ) => $c[1] ) ) . '/' . count( $agentic ),
					'findings'    => $list,
					'errors'      => $err,
					'admin_url'   => flxseo_admin_url( 'denetim' ),
				);
			},
		);
		return $tools;
	},
	5
);

/** MCP kaynakları (resources/list, resources/read); Flux AI sunucusuna `flx_mcp_resources` ile eklenir. */
add_filter(
	'flx_mcp_resources',
	function ( $res ) {
		return array_merge( (array) $res, flxseo_mcp_resources() );
	}
);

function flxseo_mcp_resources() {
	$res = array();
	foreach ( array( 'tr', 'en' ) as $l ) {
		$res[ 'flxseo://guide/site-build?lang=' . $l ] = array(
			'name'        => 'site-build-guide-' . $l,
			'title'       => 'tr' === $l ? 'Site yapım rehberi (Türkçe)' : 'Site build guide (English)',
			'description' => 'SEO, performance, accessibility, security and agent-readiness rules for building FluxWP/WordPress sites.',
			'mimeType'    => 'text/markdown',
			'read'        => fn() => flxseo_guide( 'all', $l ),
		);
	}
	$res['flxseo://guide/site-build'] = array(
		'name'        => 'site-build-guide',
		'title'       => 'Site build guide',
		'description' => 'Same guide in the user language.',
		'mimeType'    => 'text/markdown',
		'read'        => fn() => flxseo_guide( 'all' ),
	);
	$res['flxseo://llms.txt'] = array(
		'name'        => 'llms-txt',
		'title'       => 'llms.txt',
		'description' => 'The llms.txt summary this site serves at /llms.txt.',
		'mimeType'    => 'text/markdown',
		'read'        => fn() => flxseo_llms_txt(),
	);
	return $res;
}

/* Flux AI sohbeti: sayfa/tema işinde önce rehber, sonra denetim. */
add_filter(
	'flxai_chat_system',
	function ( $text ) {
		return $text . "\n\nSite building: before creating or redesigning pages, patterns, themes or presets call flxseo_site_build_guide and follow it. After changes run flxseo_site_audit (refresh=true) and fix findings in priority order (critical, high, medium, low). Findings with fix_id can be applied with flxseo_system_fix after the user agrees.";
	}
);
