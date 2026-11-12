<?php
/**
 * Flux Ads: Flux AI sohbeti ve MCP istemcileri (Claude vb.) için salt okunur araçlar (`flx_mcp_tools`).
 * Gizli anahtarlar ve kişisel veri (e-posta, telefon) hiçbir araçta dönmez.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_mcp_tools',
	static function ( $tools ) {
		$range = array(
			'from' => array( 'type' => 'string', 'description' => 'Start date Y-m-d (site time). Default: 29 days ago.' ),
			'to'   => array( 'type' => 'string', 'description' => 'End date Y-m-d. Default: today.' ),
			'kind' => array( 'type' => 'string', 'enum' => array( 'all', 'donations', 'orders' ), 'description' => 'donations = Flux Donate gifts only, orders = shop orders only.' ),
		);
		$tools['flxads_report'] = array(
			'title'       => __( 'Ad conversions report', 'flx-ads' ),
			'description' => 'Which ad channel (meta, google, tiktok, microsoft, other UTM, or none), campaign (utm_campaign), ad (utm_content), source/medium, landing page or first ad touch brought how many paid donations/orders, how much money (in the shop base currency) and how many new donors. Use it to answer "which ad brought which donation".',
			'readonly'    => true,
			'cap'         => 'flxads_view',
			'source'      => 'flx-ads',
			'schema'      => array(
				'type'       => 'object',
				'properties' => $range + array(
					'by'      => array( 'type' => 'string', 'enum' => array_keys( FLXADS_Report::dimensions() ), 'description' => 'Group by. Default channel.' ),
					'channel' => array( 'type' => 'string', 'description' => 'Only this channel (meta, google, tiktok, microsoft, other, - for no ad).' ),
				),
			),
			'run'         => static function ( $args ) {
				$args = (array) $args;
				$r    = FLXADS_Report::grouped( (string) ( $args['by'] ?? 'channel' ), $args, 50 );
				foreach ( $r['rows'] as &$row ) {
					$row['amount'] = round( $row['amount'], 2 );
				}
				return $r;
			},
		);
		$tools['flxads_status'] = array(
			'title'       => __( 'Ad tracking status', 'flx-ads' ),
			'description' => 'Which ad platforms are set up (Meta Pixel + Conversions API, TikTok Pixel + Events API, GA4 + Measurement Protocol), consent mode, and server conversion send counts (pending, sent, failed, skipped) with the latest errors. No secrets.',
			'readonly'    => true,
			'cap'         => 'flxads_view',
			'source'      => 'flx-ads',
			'log'         => false,
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'run'         => static function () {
				global $wpdb;
				$out = array( 'platforms' => array() );
				foreach ( flxads_platforms() as $p => $label ) {
					$out['platforms'][ $p ] = array( 'label' => $label, 'browser' => flxads_platform_on( $p ), 'server' => flxads_platform_on( $p, true ) );
				}
				$out['consent'] = flxads_setting( 'consent' );
				$out['counts']  = FLXADS_Send::counts();
				$out['errors']  = $wpdb->get_results( 'SELECT platform, order_id, last_error, created_at FROM ' . flxads_table( 'conversions' ) . " WHERE status = 'failed' ORDER BY id DESC LIMIT 10", ARRAY_A ); // phpcs:ignore WordPress.DB
				return $out;
			},
		);
		return $tools;
	}
);
