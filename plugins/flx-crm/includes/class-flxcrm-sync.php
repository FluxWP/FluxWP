<?php
/**
 * Geçmiş veriyi parça parça içe alır: her kaynak kendi `backfill( $page )` yöntemini sunar; ardından
 * kişilerin toplamları yeniden hesaplanır ve skorlama çalışır. Yeni kayıtlar kancalarla anında gelir;
 * bu yalnız kurulumda ya da "Yeniden eşitle"de gerekir.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Sync {

	const CHUNK = 100;

	public static function init() {
		add_action( 'wp_ajax_flxcrm_sync', array( __CLASS__, 'ajax' ) );
	}

	/**
	 * Etkin kaynaklar: anahtar => [ ad, sınıf ].
	 */
	/** Etkin kaynaklar: anahtar => [ ad, sınıf, yöntem (varsayılan backfill) ]. */
	public static function sources() {
		$out = array();
		foreach ( array( 'FLXCRM_Adapter_Donate', 'FLXCRM_Adapter_Commerce', 'FLXCRM_Adapter_Chat', 'FLXCRM_Adapter_Forms', 'FLXCRM_Adapter_Users', 'FLXCRM_Adapter_Mail' ) as $cls ) {
			if ( $cls::enabled() && method_exists( $cls, 'backfill' ) ) {
				$out[ $cls::KEY ] = array( $cls::label(), $cls );
				if ( 'FLXCRM_Adapter_Chat' === $cls && flxcrm_setting( 'chat_events' ) ) {
					$out['chat_msgs'] = array( __( 'WhatsApp messages', 'flx-crm' ), $cls, 'backfill_messages' );
				}
			}
		}
		return (array) apply_filters( 'flxcrm_sync_sources', $out );
	}

	/** Bir adım: step = kaynak anahtarı | recalc | score. */
	public static function step( $step, $page ) {
		$sources = self::sources();
		$order   = array_merge( array_keys( $sources ), array( 'recalc', 'score' ) );
		if ( ! in_array( $step, $order, true ) ) {
			$step = $order[0];
		}
		$page = max( 1, (int) $page );
		if ( isset( $sources[ $step ] ) ) {
			FLXCRM_Events::$defer_recalc = true;
			$r = call_user_func( array( $sources[ $step ][1], $sources[ $step ][2] ?? 'backfill' ), $page, self::CHUNK );
			FLXCRM_Events::$defer_recalc = false;
			$label = $sources[ $step ][0];
		} elseif ( 'recalc' === $step ) {
			$r     = self::recalc_page( $page );
			$label = __( 'Totals', 'flx-crm' );
		} else {
			$s     = FLXCRM_Scoring::run();
			$r     = array( 'done' => true, 'scanned' => $s['scored'], 'total' => $s['scored'] );
			$label = __( 'RFM score', 'flx-crm' );
			update_option( 'flxcrm_synced_at', time(), false );
			FLXCRM_Segments::seed();
			FLXCRM_Contacts::flush_stats();
		}
		$next = null;
		if ( ! empty( $r['done'] ) ) {
			$i    = array_search( $step, $order, true );
			$next = $order[ $i + 1 ] ?? null;
			$page = 1;
		} else {
			++$page;
		}
		return array(
			'step'    => $step,
			'label'   => $label,
			'scanned' => (int) ( $r['scanned'] ?? 0 ),
			'total'   => (int) ( $r['total'] ?? 0 ),
			'next'    => $r['done'] ? $next : $step,
			'page'    => $page,
			'finished' => null === $next && ! empty( $r['done'] ),
		);
	}

	private static function recalc_page( $page ) {
		global $wpdb;
		$t     = flxcrm_table( 'contacts' );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ); // phpcs:ignore WordPress.DB
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $t ORDER BY id LIMIT %d OFFSET %d", 200, ( $page - 1 ) * 200 ) ); // phpcs:ignore WordPress.DB
		foreach ( $ids as $id ) {
			FLXCRM_Contacts::recalc( (int) $id );
		}
		return array( 'done' => count( $ids ) < 200, 'scanned' => min( $total, $page * 200 ), 'total' => $total );
	}

	public static function ajax() {
		check_ajax_referer( 'flxcrm_admin' );
		if ( ! flxcrm_can( true ) ) {
			wp_send_json_error( null, 403 );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		wp_send_json_success( self::step( $step, $page ) );
	}

	/** Komut satırından / testte hepsini tek seferde çalıştırır. */
	public static function run_all() {
		$step = '';
		$page = 1;
		for ( $i = 0; $i < 10000; $i++ ) {
			$r = self::step( $step, $page );
			if ( $r['finished'] ) {
				return true;
			}
			$step = $r['next'];
			$page = $r['page'];
		}
		return false;
	}
}
