<?php
/**
 * 2.22.0: Flux Donate keeps no donor table of its own. A donor is a row of the shared FluxWP people table
 * ({prefix}flx_people, flx-brand people.php) and the donor id is the person id, so the same person is seen by
 * Flux CRM, Flux Certificates, Flux Mail and Flux Chat. Flux Donate adds the columns it owns (gift totals and the
 * KVKK consent) and moves the old {prefix}flxb_donors table once.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_People {

	const DONE = 'flxb_people_shared';

	public static function init() {
		add_filter( 'flx_people_columns', array( __CLASS__, 'columns' ) );
		add_filter( 'flx_people_keys', array( __CLASS__, 'keys' ) );
		add_action( 'after_setup_theme', array( __CLASS__, 'upgrade' ), 2 ); // after flx-brand core (0) and Flux CRM's move (1)
	}

	/** Columns Flux Donate owns on the shared table. */
	public static function columns( $cols ) {
		$cols['first_donation_at'] = 'datetime NULL DEFAULT NULL';
		$cols['last_donation_at']  = 'datetime NULL DEFAULT NULL';
		$cols['total_amount']      = 'decimal(20,4) NOT NULL DEFAULT 0';
		$cols['donation_count']    = 'int(11) unsigned NOT NULL DEFAULT 0';
		$cols['consent_kvkk']      = 'tinyint(1) NOT NULL DEFAULT 0';
		return $cols;
	}

	public static function keys( $keys ) {
		$keys['last_donation_at'] = 'last_donation_at';
		return $keys;
	}

	public static function upgrade() {
		if ( ! function_exists( 'flx_people_ready' ) || get_option( self::DONE ) ) {
			return;
		}
		flx_people_ready();
		self::migrate();
	}

	/**
	 * Moves {prefix}flxb_donors into the shared table. A donor Flux CRM already knows (link donate/donor:<id>), or a
	 * person with the same phone, then e-mail, is reused and only gets empty fields filled; otherwise the donor id
	 * is kept when free. Donations and subscriptions follow the new ids, totals are recomputed, the old table is
	 * dropped after a complete copy. Running it again does nothing.
	 */
	public static function migrate() {
		global $wpdb;
		static $running = false;
		if ( $running ) {
			return;
		}
		$running = true;
		$old     = $wpdb->prefix . 'flxb_donors';
		$new     = flx_people_table();
		$links   = flx_people_table( 'links' );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $old ) ) ) !== $old ) { // phpcs:ignore WordPress.DB
			update_option( self::DONE, time(), true );
			$running = false;
			return;
		}
		$had  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $new" ); // phpcs:ignore WordPress.DB
		$rows = (array) $wpdb->get_results( "SELECT * FROM $old ORDER BY id", ARRAY_A ); // phpcs:ignore WordPress.DB
		$map  = array();
		foreach ( $rows as $r ) {
			$oid   = (int) $r['id'];
			$phone = ltrim( (string) $r['phone'], '+' );
			$phone = function_exists( 'flx_person_phone' ) && '' !== $phone ? flx_person_phone( $phone ) : $phone;
			$email = strtolower( trim( (string) $r['email'] ) );
			$match = 0;
			if ( $had ) {
				$match = (int) $wpdb->get_var( $wpdb->prepare( "SELECT l.contact_id FROM $links l INNER JOIN $new p ON p.id = l.contact_id WHERE l.source = 'donate' AND l.source_id = %s", 'donor:' . $oid ) ); // phpcs:ignore WordPress.DB
				if ( ! $match && '' !== $phone ) {
					$match = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $new WHERE phone = %s ORDER BY id LIMIT 1", $phone ) ); // phpcs:ignore WordPress.DB
				}
				if ( ! $match && '' !== $email ) {
					$match = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $new WHERE email = %s ORDER BY id LIMIT 1", $email ) ); // phpcs:ignore WordPress.DB
				}
			}
			if ( $match ) {
				$cur = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $new WHERE id = %d", $match ), ARRAY_A ); // phpcs:ignore WordPress.DB
				$upd = array();
				foreach ( array( 'name' => (string) $r['name'], 'phone' => $phone, 'email' => $email, 'note' => (string) $r['notes'] ) as $k => $v ) {
					if ( '' === trim( (string) ( $cur[ $k ] ?? '' ) ) && '' !== $v ) {
						$upd[ $k ] = $v;
					}
				}
				foreach ( array( 'consent_email', 'consent_sms', 'consent_kvkk' ) as $k ) {
					if ( (int) $r[ $k ] && ! (int) ( $cur[ $k ] ?? 0 ) ) {
						$upd[ $k ] = 1;
					}
				}
				if ( (string) $r['created_at'] < (string) $cur['created_at'] ) {
					$upd['created_at'] = (string) $r['created_at'];
				}
				if ( $upd ) {
					$wpdb->update( $new, $upd, array( 'id' => $match ) ); // phpcs:ignore WordPress.DB
				}
				$map[ $oid ] = $match;
				continue;
			}
			$row  = array(
				'name'          => mb_substr( (string) $r['name'], 0, 190 ),
				'phone'         => $phone,
				'email'         => $email,
				'note'          => (string) $r['notes'],
				'source'        => 'donate',
				'status'        => 'active',
				'consent_email' => (int) $r['consent_email'],
				'consent_sms'   => (int) $r['consent_sms'],
				'consent_kvkk'  => (int) $r['consent_kvkk'],
				'created_at'    => (string) $r['created_at'],
				'updated_at'    => (string) $r['updated_at'],
			);
			$free = ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $new WHERE id = %d", $oid ) ); // phpcs:ignore WordPress.DB
			if ( $free ) {
				$row['id'] = $oid;
			}
			if ( $wpdb->insert( $new, $row ) ) { // phpcs:ignore WordPress.DB
				$map[ $oid ] = $free ? $oid : (int) $wpdb->insert_id;
			}
		}
		if ( count( $map ) < count( $rows ) ) {
			$running = false; // a row could not be written: keep the old table, try again on the next request
			return;
		}
		$moved = array();
		foreach ( $map as $from => $to ) {
			if ( (int) $from !== (int) $to ) {
				$moved[ $from ] = $to;
			}
		}
		if ( $moved ) {
			$off = 1000000000; // through an offset, so that 5→7 and 7→9 do not run into each other
			foreach ( array( flxb_table( 'donations' ), flxb_table( 'subscriptions' ) ) as $t ) {
				foreach ( $moved as $from => $to ) {
					$wpdb->query( $wpdb->prepare( "UPDATE $t SET donor_id = %d WHERE donor_id = %d", (int) $to + $off, (int) $from ) ); // phpcs:ignore WordPress.DB
				}
				$wpdb->query( $wpdb->prepare( "UPDATE $t SET donor_id = donor_id - %d WHERE donor_id >= %d", $off, $off ) ); // phpcs:ignore WordPress.DB
			}
		}
		// Donate links now name the person itself: donate / donor:<person id>.
		$wpdb->query( "DELETE FROM $links WHERE source = 'donate' AND source_id LIKE 'donor:%'" ); // phpcs:ignore WordPress.DB
		foreach ( array_unique( array_values( $map ) ) as $pid ) {
			FLXB_Donors::mark( (int) $pid );
			FLXB_Donors::recalc( (int) $pid );
		}
		$wpdb->query( "DROP TABLE IF EXISTS $old" ); // phpcs:ignore WordPress.DB
		update_option( self::DONE, time(), true );
		$running = false;
	}
}
