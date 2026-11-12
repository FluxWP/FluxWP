<?php
/**
 * Flux Ads: ortak Sistem Kontrolü satırları (`flx_system_checks`). Ucuz kontroller; dış HTTP yok
 * (bağlantı testi yalnız yönetici "Test"e basınca, sonucu burada gösterilir).
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks',
	static function ( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		$src    = 'Flux Ads';
		$url    = flxads_admin_url( 'settings' );
		$tests  = (array) get_option( 'flxads_tests', array() );
		$any    = false;
		foreach ( flxads_platforms() as $p => $label ) {
			if ( ! flxads_setting( $p . '_on' ) ) {
				continue;
			}
			$any = true;
			if ( ! flxads_platform_on( $p ) ) {
				$st  = 'error';
				$msg = __( 'Turned on but the ID is missing or not valid. Nothing is tracked.', 'flx-ads' );
			} elseif ( ! flxads_platform_on( $p, true ) ) {
				$st  = 'warning';
				$msg = __( 'Browser pixel only. Without the server key, bank-transfer donations approved later and visitors with ad blockers are not counted.', 'flx-ads' );
			} else {
				$t   = $tests[ $p ] ?? null;
				$st  = $t ? ( $t['ok'] ? 'ok' : 'warning' ) : 'info';
				$msg = $t ? ( $t['ok'] ? __( 'Pixel and server events are set up. Last test passed.', 'flx-ads' ) : __( 'Last connection test failed:', 'flx-ads' ) . ' ' . $t['message'] ) : __( 'Pixel and server events are set up. Run "Test" once to check the key.', 'flx-ads' );
			}
			$checks[] = array(
				'id'           => 'flxads.' . $p,
				'source'       => $src,
				'group'        => __( 'Ads', 'flx-ads' ),
				'label'        => $label,
				'status'       => $st,
				'message'      => $msg,
				'action_url'   => $url,
				'action_label' => __( 'Settings', 'flx-ads' ),
			);
		}
		if ( ! $any ) {
			$checks[] = array(
				'id'           => 'flxads.none',
				'source'       => $src,
				'group'        => __( 'Ads', 'flx-ads' ),
				'label'        => __( 'Ad platforms', 'flx-ads' ),
				'status'       => 'info',
				'message'      => __( 'No ad platform is on. The ad report still works from UTM links and click IDs.', 'flx-ads' ),
				'action_url'   => $url,
				'action_label' => __( 'Settings', 'flx-ads' ),
			);
		}
		$c = FLXADS_Send::counts();
		if ( $c['failed'] || $c['pending'] ) {
			$beat     = (int) get_option( 'flxads_heartbeat' );
			$checks[] = array(
				'id'           => 'flxads.queue',
				'source'       => $src,
				'group'        => 'Cron',
				'label'        => __( 'Server conversions', 'flx-ads' ),
				'status'       => $c['failed'] ? 'warning' : 'info',
				/* translators: 1: failed count, 2: pending count, 3: last run */
				'message'      => sprintf( __( '%1$d failed, %2$d waiting. Last retry run: %3$s.', 'flx-ads' ), $c['failed'], $c['pending'], $beat ? flxads_ago( gmdate( 'Y-m-d H:i:s', $beat ) ) : __( 'never', 'flx-ads' ) ),
				'action_url'   => flxads_admin_url( 'log' ),
				'action_label' => __( 'Send log', 'flx-ads' ),
			);
		}
		if ( function_exists( 'flx_cookie_active' ) && ! flx_cookie_active() && 'auto' === flxads_setting( 'consent' ) && $any ) {
			$checks[] = array(
				'id'           => 'flxads.consent',
				'source'       => $src,
				'group'        => 'Security',
				'label'        => __( 'Cookie consent', 'flx-ads' ),
				'status'       => 'warning',
				'message'      => __( 'The theme cookie banner is off, so ad pixels load without asking. Under KVKK/GDPR marketing cookies need consent: turn the banner on in Appearance › Theme Settings.', 'flx-ads' ),
				'action_url'   => admin_url( 'themes.php?page=flx-ayarlar' ),
				'action_label' => __( 'Theme Settings', 'flx-ads' ),
			);
		}
		return $checks;
	}
);
