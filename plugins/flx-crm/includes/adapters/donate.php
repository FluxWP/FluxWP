<?php
/**
 * Flux Donate → kişi + "Bağış" olayı. Bağış verisi Flux Donate'te kalır; burada özet ve bağlantı tutulur.
 * Kancalar: flxb_donation_recorded, flxb_donation_updated, flxb_donation_trashed/restored/deleted.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Adapter_Donate {

	const KEY = 'donate';

	public static function enabled() {
		return (bool) flxcrm_setting( 'src_donate' ) && function_exists( 'flxb_table' );
	}

	public static function label() {
		return 'Flux Donate';
	}

	public static function init() {
		if ( ! self::enabled() ) {
			return;
		}
		add_action( 'flxb_donation_recorded', array( __CLASS__, 'on_change' ), 20, 1 );
		add_action( 'flxb_donation_updated', array( __CLASS__, 'on_change' ), 20, 1 );
		add_action( 'flxb_donation_restored', array( __CLASS__, 'on_change' ), 20, 1 );
		add_action( 'flxb_donation_trashed', array( __CLASS__, 'on_remove' ), 20, 1 );
		add_action( 'flxb_donation_deleted', array( __CLASS__, 'on_remove' ), 20, 1 );
	}

	public static function on_change( $id ) {
		try {
			self::sync( (int) $id );
		} catch ( \Throwable $e ) {
			error_log( 'flx-crm donate: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	public static function on_remove( $id ) {
		FLXCRM_Events::delete_by_uniq( 'donate:donation:' . (int) $id );
	}

	/** Tek bağışı eşitler. */
	public static function sync( $id, $row = null ) {
		global $wpdb;
		if ( null === $row ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxb_table( 'donations' ) . ' WHERE id = %d', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		}
		if ( ! $row ) {
			return 0;
		}
		if ( 'trash' === $row['status'] ) {
			self::on_remove( $row['id'] );
			return 0;
		}
		$donor = (int) $row['donor_id'] ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxb_table( 'donors' ) . ' WHERE id = %d', (int) $row['donor_id'] ), ARRAY_A ) : null; // phpcs:ignore WordPress.DB
		if ( ! $donor ) {
			return 0;
		}
		$meta = $row['meta'] ? json_decode( (string) $row['meta'], true ) : array();
		$cid  = FLXCRM_Contacts::upsert(
			array(
				'name'          => $donor['name'],
				'email'         => $donor['email'],
				'phone'         => $donor['phone'],
				'user_id'       => self::user_for( $row ),
				'roles'         => 'completed' === $row['status'] ? array( (string) flxcrm_setting( 'role_donate' ) ) : array(),
				'source'        => self::KEY,
				'link'          => array( self::KEY, 'donor:' . (int) $donor['id'] ),
				'consent_email' => (int) $donor['consent_email'],
				'consent_sms'   => (int) $donor['consent_sms'],
				'utm_source'    => is_array( $meta ) ? (string) ( $meta['utm_source'] ?? '' ) : '',
				'utm_campaign'  => is_array( $meta ) ? (string) ( $meta['utm_campaign'] ?? '' ) : '',
				'at'            => $donor['created_at'],
			)
		);
		if ( ! $cid ) {
			return 0;
		}
		$title = '' !== (string) $row['option_label'] ? $row['option_label'] : self::type_title( (int) $row['type_id'] );
		if ( (int) $row['quantity'] > 1 ) {
			$title .= ' × ' . (int) $row['quantity'];
		}
		FLXCRM_Events::add(
			$cid,
			'donation',
			array(
				'source'   => self::KEY,
				'uniq'     => 'donate:donation:' . (int) $row['id'],
				'title'    => '' !== trim( $title ) ? $title : __( 'Bağış', 'flx-crm' ),
				'amount'   => (float) $row['amount'],
				'currency' => (string) $row['currency'],
				'paid'     => 'completed' === $row['status'],
				'url'      => 'admin.php?page=flxb-donations&donation=' . (int) $row['id'],
				'data'     => array(
					'status'    => $row['status'],
					'recurring' => 'recurring' === $row['type'],
					'type_id'   => (int) $row['type_id'],
					'provider'  => $row['provider'],
				),
				'at'       => $row['created_at'],
			)
		);
		return $cid;
	}

	private static function user_for( array $row ) {
		if ( (int) $row['wc_order_id'] && function_exists( 'wc_get_order' ) ) {
			$o = wc_get_order( (int) $row['wc_order_id'] );
			if ( $o ) {
				return (int) $o->get_customer_id();
			}
		}
		return 0;
	}

	private static function type_title( $type_id ) {
		static $titles = array();
		if ( ! $type_id ) {
			return '';
		}
		if ( ! isset( $titles[ $type_id ] ) ) {
			global $wpdb;
			$titles[ $type_id ] = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT title FROM ' . flxb_table( 'types' ) . ' WHERE id = %d', $type_id ) ); // phpcs:ignore WordPress.DB
		}
		return $titles[ $type_id ];
	}

	/** Geçmiş bağışlar (çöp hariç). */
	public static function backfill( $page, $per ) {
		global $wpdb;
		$t     = flxb_table( 'donations' );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status <> 'trash'" ); // phpcs:ignore WordPress.DB
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status <> 'trash' ORDER BY id LIMIT %d OFFSET %d", $per, ( $page - 1 ) * $per ), ARRAY_A ); // phpcs:ignore WordPress.DB
		foreach ( (array) $rows as $r ) {
			self::sync( (int) $r['id'], $r );
		}
		return array( 'done' => count( (array) $rows ) < $per, 'scanned' => min( $total, $page * $per ), 'total' => $total );
	}
}
