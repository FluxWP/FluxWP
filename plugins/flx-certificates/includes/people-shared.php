<?php
/**
 * 2.0.0: Flux Certificates keeps no person table of its own. People are rows of the shared FluxWP table
 * ({prefix}flx_people, flx-brand people.php), so a person added here is the same person in Flux CRM, Flux Mail,
 * Flux Donate or Flux Chat, and Flux Certificates still works alone (the shared module ships inside this plugin).
 *
 * Owned here: the `created_by` column (who added the person in Certificates, used for staff scope) and the
 * one-time move of the old {prefix}flxs_people table.
 *
 * @package flx-certificates
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_people_columns',
	static function ( $cols ) {
		$cols['created_by'] = 'bigint(20) unsigned NOT NULL DEFAULT 0';
		return $cols;
	}
);

add_action( 'after_setup_theme', 'flxs_people_upgrade', 2 ); // after flx-brand core (0) and Flux CRM's move (1)

function flxs_people_upgrade() {
	if ( ! function_exists( 'flx_people_ready' ) || get_option( 'flxs_people_shared' ) ) {
		return;
	}
	flx_people_ready();
	flxs_migrate_people();
}

/**
 * Moves {prefix}flxs_people into the shared table. Ids are kept when free; a person who is already in the shared
 * table (same phone, then same e-mail) is reused and only gets the empty fields filled. Certificate, message and
 * log references follow the new ids. Safe to run again: it does nothing once the old table is gone.
 */
function flxs_migrate_people() {
	global $wpdb;
	static $running = false;
	if ( $running ) {
		return;
	}
	$running = true;
	$old     = $wpdb->prefix . 'flxs_people';
	$new     = flx_people_table();
	$found   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $old ) ) ); // phpcs:ignore WordPress.DB
	if ( $found !== $old ) {
		update_option( 'flxs_people_shared', time(), true );
		$running = false;
		return;
	}
	$had  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $new" ); // phpcs:ignore WordPress.DB
	$rows = (array) $wpdb->get_results( "SELECT * FROM $old ORDER BY id", ARRAY_A ); // phpcs:ignore WordPress.DB
	$map  = array();
	foreach ( $rows as $r ) {
		$oid   = (int) $r['id'];
		$phone = function_exists( 'flx_person_phone' ) ? flx_person_phone( (string) $r['phone'] ) : preg_replace( '/\D+/', '', (string) $r['phone'] );
		$email = strtolower( trim( (string) $r['email'] ) );
		$match = 0;
		if ( $had ) {
			if ( '' !== $phone ) {
				$match = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $new WHERE phone = %s ORDER BY id LIMIT 1", $phone ) ); // phpcs:ignore WordPress.DB
			}
			if ( ! $match && '' !== $email ) {
				$match = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $new WHERE email = %s ORDER BY id LIMIT 1", $email ) ); // phpcs:ignore WordPress.DB
			}
		}
		if ( $match ) {
			$cur = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $new WHERE id = %d", $match ), ARRAY_A ); // phpcs:ignore WordPress.DB
			$upd = array();
			foreach ( array( 'name' => (string) $r['name'], 'phone' => $phone, 'email' => $email, 'note' => (string) $r['note'] ) as $k => $v ) {
				if ( '' === trim( (string) ( $cur[ $k ] ?? '' ) ) && '' !== $v ) {
					$upd[ $k ] = $v;
				}
			}
			if ( ! (int) ( $cur['created_by'] ?? 0 ) && (int) $r['created_by'] ) {
				$upd['created_by'] = (int) $r['created_by'];
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
			'name'       => (string) $r['name'],
			'phone'      => $phone,
			'email'      => $email,
			'note'       => (string) $r['note'],
			'source'     => 'certificates',
			'status'     => 'active',
			'created_by' => (int) $r['created_by'],
			'created_at' => (string) $r['created_at'],
			'updated_at' => (string) $r['updated_at'],
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
	$moved = array_filter(
		$map,
		static function ( $v, $k ) {
			return (int) $v !== (int) $k;
		},
		ARRAY_FILTER_USE_BOTH
	);
	if ( $moved ) {
		// Two passes through an offset so that 5→7 and 7→9 do not run into each other.
		$off  = 1000000000;
		$refs = array( array( flxs_table( 'certificates' ), 'person_id', '' ), array( flxs_table( 'messages' ), 'person_id', '' ), array( flxs_table( 'logs' ), 'object_id', "object_type = 'person'" ) );
		foreach ( $refs as $ref ) {
			list( $t, $col, $extra ) = $ref;
			$and = $extra ? " AND $extra" : '';
			foreach ( $moved as $from => $to ) {
				$wpdb->query( $wpdb->prepare( "UPDATE $t SET $col = %d WHERE $col = %d$and", (int) $to + $off, (int) $from ) ); // phpcs:ignore WordPress.DB
			}
			$wpdb->query( $wpdb->prepare( "UPDATE $t SET $col = $col - %d WHERE $col >= %d$and", $off, $off ) ); // phpcs:ignore WordPress.DB
		}
	}
	foreach ( array_unique( array_values( $map ) ) as $pid ) {
		flx_person_link( (int) $pid, 'certificates', (string) $pid );
	}
	$wpdb->query( "DROP TABLE IF EXISTS $old" ); // phpcs:ignore WordPress.DB
	update_option( 'flxs_people_shared', time(), true );
	$running = false;
}

/** Marks a person as used by Flux Certificates (shared link `certificates`), so other packages see it on the person. */
function flxs_person_mark( $id ) {
	if ( $id && function_exists( 'flx_person_link' ) ) {
		flx_person_link( (int) $id, 'certificates', (string) (int) $id );
	}
}

/** SQL condition: people Flux Certificates uses (tied with the `certificates` link or holding a certificate). */
function flxs_people_mine_sql( $alias = 'p' ) {
	global $wpdb;
	$a = $alias ? $alias . '.' : '';
	return "(EXISTS (SELECT 1 FROM {$wpdb->prefix}flx_people_links fl WHERE fl.contact_id = {$a}id AND fl.source = 'certificates') OR {$a}id IN (SELECT person_id FROM " . flxs_table( 'certificates' ) . '))';
}

/** A person holding certificates is not deleted by another package (Flux CRM merges instead). */
add_filter(
	'flx_person_in_use',
	static function ( $used, $id ) {
		global $wpdb;
		if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxs_table( 'certificates' ) . ' WHERE person_id = %d', (int) $id ) ) ) { // phpcs:ignore WordPress.DB
			$used[] = __( 'Certificates', 'flx-certificates' );
		}
		return $used;
	},
	10,
	2
);

/** Two people merged (Flux CRM): certificates and messages of the duplicate move to the kept person. */
add_action(
	'flx_person_merged',
	static function ( $primary_id, $dup_id ) {
		global $wpdb;
		foreach ( array( 'certificates', 'messages' ) as $t ) {
			$wpdb->update( flxs_table( $t ), array( 'person_id' => (int) $primary_id ), array( 'person_id' => (int) $dup_id ) ); // phpcs:ignore WordPress.DB
		}
	},
	10,
	2
);
