<?php
/**
 * SEO › Site denetimi: menü rozeti, ajan uyumu özeti ve adres süzgeci (ekran React'ta).
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Menüde kritik + yüksek bulgu sayısı. */
function flxseo_audit_menu_badge() {
	$s = get_option( 'flxseo_audit' );
	if ( ! is_array( $s ) ) {
		return '';
	}
	$c = flxseo_audit_report()['counts'];
	$n = (int) $c['critical'] + (int) $c['high'];
	return $n ? ' <span class="awaiting-mod count-' . $n . '"><span class="pending-count">' . number_format_i18n( $n ) . '</span></span>' : '';
}

/** Ajan uyumu özeti: geçen / toplam.
 * @return array<string, array{0: string, 1: bool}>
 */
function flxseo_audit_agentic( array $report ) {
	$ids    = wp_list_pluck( $report['findings'], 'id' );
	$checks = array(
		'llms'   => array( __( 'llms.txt follows recommendations', 'flx-seo' ), ! array_intersect( $ids, array( 'llms-txt', 'llms-txt-format' ) ) ),
		'tree'   => array( __( 'Accessibility tree is well-formed', 'flx-seo' ), ! array_intersect( $ids, array( 'aria-required-children', 'aria-required-parent' ) ) ),
		'webmcp' => array( __( 'WebMCP tools are registered', 'flx-seo' ), ! array_intersect( $ids, array( 'webmcp-forms', 'webmcp-tools' ) ) ),
		'md'     => array( __( 'Markdown is served to agents', 'flx-seo' ), ! in_array( 'markdown-negotiation', $ids, true ) ),
		'signal' => array( __( 'Content signals are declared', 'flx-seo' ), ! in_array( 'content-signal', $ids, true ) ),
		'org'    => array( __( 'Organization and contact pages are complete', 'flx-seo' ), ! array_intersect( $ids, array( 'org-schema-incomplete', 'key-pages-linked' ) ) ),
		'card'   => array( __( 'MCP Server Card is published', 'flx-seo' ), ! in_array( 'mcp-server-card', $ids, true ) ),
	);
	if ( ! empty( $report['psi'] ) ) {
		$checks['cls'] = array( __( 'Low layout shift (CLS)', 'flx-seo' ), ! in_array( 'cumulative-layout-shift', $ids, true ) );
	}
	return $checks;
}

/** Yalnız bu sitenin adresleri denetlenir (sunucuyu başka adreslere istek atmak için kullandırmayalım). */
function flxseo_audit_url( $url ) {
	$url  = esc_url_raw( trim( (string) $url ) );
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	return '' !== $host && strtolower( $host ) === strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ? $url : home_url( '/' );
}
