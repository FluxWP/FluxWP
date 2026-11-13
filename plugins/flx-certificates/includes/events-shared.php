<?php
/**
 * 2.0.0: events live in the shared FluxWP events table ({prefix}flx_events, flx-brand events.php). The event screen
 * is Flux CRM › Events when Flux CRM is active, otherwise Flux Certificates › Events. Everything certificate-specific
 * reaches that screen through the shared filters below, so neither screen knows about certificates.
 *
 * Owned here: columns `target` and `base_count` (saplings), certificate counts and people per event, the
 * certificate links on the card, the location QR box and "add the certificates without an event" on the form,
 * the site settings under the list, and the one-time move of {prefix}flxs_events.
 *
 * @package flx-certificates
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_events_columns',
	static function ( $cols ) {
		$cols['target']     = 'int(11) NOT NULL DEFAULT 0';
		$cols['base_count'] = 'int(11) NOT NULL DEFAULT 0';
		return $cols;
	}
);

add_filter(
	'flx_event_fields',
	static function ( $f ) {
		$f['target']     = array(
			'section'     => 'saplings',
			'type'        => 'number',
			'label'       => __( 'Sapling target (optional)', 'flx-certificates' ),
			'placeholder' => '2000',
			'order'       => 10,
		);
		$f['base_count'] = array(
			'section'     => 'saplings',
			'type'        => 'number',
			'label'       => __( 'Extra saplings (donations without a certificate)', 'flx-certificates' ),
			'order'       => 20,
		);
		return $f;
	}
);

/** Section title for the sapling fields on any event screen. */
add_filter(
	'flx_event_sections',
	static function ( $s ) {
		$s['saplings'] = __( 'Saplings', 'flx-certificates' );
		return $s;
	}
);

/** People of an event: everyone holding a valid certificate of it. */
add_filter(
	'flx_event_people',
	static function ( $out, $id ) {
		global $wpdb;
		$label = __( 'Certificate', 'flx-certificates' );
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT person_id FROM ' . flxs_table( 'certificates' ) . " WHERE event_id = %d AND person_id > 0 AND status <> 'revoked'", (int) $id ) ) as $pid ) { // phpcs:ignore WordPress.DB
			$out[ (int) $pid ][] = $label;
		}
		return $out;
	},
	10,
	2
);

/**
 * 2.1.0: per-person values for event messages (Flux CRM › Events › Message to the event): {fidan} saplings on the
 * person's certificates of this event, {sertifika_sayisi}, {sertifika_link} (first certificate), {sertifika_no}.
 */
add_filter(
	'flx_event_vars',
	static function ( $out, $id, $pids ) {
		global $wpdb;
		$pids = array_filter( array_map( 'intval', (array) $pids ) );
		$f    = array();
		$n    = array();
		$link = array();
		$no   = array();
		foreach ( array_chunk( $pids, 500 ) as $chunk ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT person_id, code, number, data FROM ' . flxs_table( 'certificates' ) . " WHERE event_id = %d AND status <> 'revoked' AND person_id IN (" . implode( ',', $chunk ) . ') ORDER BY id ASC', (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( (array) $rows as $r ) {
				$p       = (int) $r['person_id'];
				$f[ $p ] = ( $f[ $p ] ?? 0 ) + flxs_cert_fidan( $r['data'] );
				$n[ $p ] = ( $n[ $p ] ?? 0 ) + 1;
				if ( ! isset( $link[ $p ] ) ) {
					$link[ $p ] = flxs_certificate_url( $r['code'] );
				}
				if ( '' !== (string) $r['number'] ) {
					$no[ $p ][] = (string) $r['number'];
				}
			}
		}
		$out['fidan']            = array( 'label' => __( 'Saplings of the person', 'flx-certificates' ), 'sample' => '3', 'values' => array_map( 'number_format_i18n', $f ) );
		$out['sertifika_sayisi'] = array( 'label' => __( 'Certificates of the person', 'flx-certificates' ), 'sample' => '1', 'values' => array_map( 'number_format_i18n', $n ) );
		$out['sertifika_link']   = array( 'label' => __( 'Certificate link', 'flx-certificates' ), 'sample' => home_url( '/sertifika/ABC123' ), 'values' => $link );
		$out['sertifika_no']     = array(
			'label'  => __( 'Certificate number', 'flx-certificates' ),
			'sample' => '2026-0042',
			'values' => array_map(
				static function ( $l ) {
					return implode( ', ', $l );
				},
				$no
			),
		);
		return $out;
	},
	10,
	3
);

add_filter(
	'flx_event_stats',
	static function ( $out, $e ) {
		$f   = flxs_event_fidan( $e );
		$tgt = (int) ( $e['target'] ?? 0 );
		$out[] = array(
			'value'    => flxs_n( $f['fidan'] ),
			'label'    => __( 'saplings', 'flx-certificates' ),
			/* translators: 1: extra saplings entered by hand, 2: target */
			'hint'     => trim( ( (int) $e['base_count'] ? sprintf( __( '+%s extra', 'flx-certificates' ), flxs_n( (int) $e['base_count'] ) ) : '' ) . ( $tgt ? ' · ' . sprintf( __( 'target %s', 'flx-certificates' ), flxs_n( $tgt ) ) : '' ), ' ·' ),
			'progress' => $tgt ? min( 100, (int) round( $f['fidan'] / $tgt * 100 ) ) : null,
		);
		$out[] = array( 'value' => flxs_n( $f['certs'] ), 'label' => __( 'certificates', 'flx-certificates' ) );
		return $out;
	},
	10,
	2
);

add_filter(
	'flx_event_actions',
	static function ( $out, $e ) {
		if ( ! flxs_can_manage() && ! flxs_can_dashboard() ) {
			return $out;
		}
		$out[] = array( 'label' => __( 'Certificate dashboard', 'flx-certificates' ), 'url' => admin_url( 'admin.php?page=flxs-dashboard&event=' . (int) $e['id'] ) );
		$out[] = array( 'label' => __( 'Certificates', 'flx-certificates' ), 'url' => admin_url( 'admin.php?page=flxs&event=' . (int) $e['id'] ) );
		return $out;
	},
	10,
	2
);

/** A deleted event leaves its certificates without an event; they are not deleted. */
add_action(
	'flx_event_deleting',
	static function ( $id ) {
		global $wpdb;
		$wpdb->update( flxs_table( 'certificates' ), array( 'event_id' => 0 ), array( 'event_id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	}
);

/** Event form (any screen): location QR box, and on a new event "add the certificates without an event". */
add_action(
	'flx_event_form_after',
	static function ( $e ) {
		global $wpdb;
		if ( ! empty( $e['id'] ) ) {
			echo '<div class="flxs-wrap">';
			flxs_event_location_qr_box( $e );
			echo '</div>';
			return;
		}
		$none = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxs_table( 'certificates' ) . ' WHERE event_id = 0' ); // phpcs:ignore WordPress.DB
		if ( $none ) {
			/* translators: %s: number of certificates */
			echo '<label class="flx-ev-check"><input type="checkbox" name="flxs_adopt" value="1" checked> ' . esc_html( sprintf( __( 'Add the certificates without an event (%s) to this event', 'flx-certificates' ), number_format_i18n( $none ) ) ) . '</label>';
		}
	}
);

/** The host screen checked the nonce and capability before saving. */
add_action(
	'flx_event_saved',
	static function ( $id, $e, $is_new ) {
		global $wpdb;
		if ( ! $is_new || empty( $_POST['flxs_adopt'] ) || ! flxs_can_manage() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$n = (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . flxs_table( 'certificates' ) . ' SET event_id = %d WHERE event_id = 0', (int) $id ) ); // phpcs:ignore WordPress.DB
		if ( $n ) {
			flxs_log( 'event_saved', 'event', $id, sprintf( '%d etkinliksiz sertifika etkinliğe eklendi', $n ) );
		}
	},
	10,
	3
);

/** Under the event list (any screen): how the events show on the site. */
add_action(
	'flx_events_screen_after',
	static function () {
		if ( flxs_can_manage() && function_exists( 'flxs_site_settings_form' ) ) {
			echo '<div class="flxs-wrap">';
			flxs_site_settings_form();
			echo '</div>';
		}
	}
);

/** Our styles on the CRM event screen (site settings form, location QR box). */
add_action(
	'admin_enqueue_scripts',
	static function () {
		if ( isset( $_GET['page'] ) && 'flx-crm-events' === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_enqueue_style( 'flxs-admin', FLXS_URL . 'assets/css/admin.css', array(), FLXS_VERSION );
		}
	}
);

/** With Flux CRM active the event screen is there; Certificates › Events goes to it. */
function flxs_events_screen_url( $args = array() ) {
	if ( function_exists( 'flxcrm_events_url' ) ) {
		return flxcrm_events_url( $args );
	}
	return add_query_arg( $args, admin_url( 'admin.php?page=flxs-etkinlikler' ) );
}

add_action(
	'admin_init',
	static function () {
		if ( isset( $_GET['page'] ) && 'flxs-etkinlikler' === $_GET['page'] && function_exists( 'flxcrm_events_url' ) && ! wp_doing_ajax() ) { // phpcs:ignore WordPress.Security.NonceVerification
			$args = array();
			foreach ( array( 'edit', 'add', 'tab' ) as $k ) {
				if ( isset( $_GET[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$args[ $k ] = sanitize_key( wp_unslash( $_GET[ $k ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
				}
			}
			wp_safe_redirect( flxcrm_events_url( $args ) );
			exit;
		}
	}
);

add_action( 'after_setup_theme', 'flxs_events_upgrade', 2 );

function flxs_events_upgrade() {
	if ( ! function_exists( 'flx_events_ready' ) || get_option( 'flxs_events_shared' ) ) {
		return;
	}
	flx_events_ready();
	flxs_migrate_events();
}

/**
 * Moves {prefix}flxs_events into the shared table. Ids are kept when free (they are on a normal site: Flux
 * Certificates was the only package with events); otherwise the event gets a new id and its certificates follow.
 */
function flxs_migrate_events() {
	global $wpdb;
	$old = $wpdb->prefix . 'flxs_events';
	$new = flx_events_table();
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $old ) ) ) !== $old ) { // phpcs:ignore WordPress.DB
		update_option( 'flxs_events_shared', time(), true );
		return;
	}
	$cols  = array_keys( flx_events_columns() );
	$rows  = (array) $wpdb->get_results( "SELECT * FROM $old ORDER BY id", ARRAY_A ); // phpcs:ignore WordPress.DB
	$moved = array();
	$ok    = 0;
	foreach ( $rows as $r ) {
		$oid = (int) $r['id'];
		$row = array_intersect_key( $r, array_flip( $cols ) );
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $new WHERE id = %d", $oid ) ) ) { // phpcs:ignore WordPress.DB
			unset( $row['id'] );
		}
		if ( $wpdb->insert( $new, $row ) ) { // phpcs:ignore WordPress.DB
			++$ok;
			$nid = isset( $row['id'] ) ? $oid : (int) $wpdb->insert_id;
			if ( $nid !== $oid ) {
				$moved[ $oid ] = $nid;
			}
		}
	}
	if ( $ok < count( $rows ) ) {
		return; // keep the old table, try again on the next request
	}
	if ( $moved ) {
		$off = 1000000000;
		$t   = flxs_table( 'certificates' );
		foreach ( $moved as $from => $to ) {
			$wpdb->query( $wpdb->prepare( "UPDATE $t SET event_id = %d WHERE event_id = %d", (int) $to + $off, (int) $from ) ); // phpcs:ignore WordPress.DB
		}
		$wpdb->query( $wpdb->prepare( "UPDATE $t SET event_id = event_id - %d WHERE event_id >= %d", $off, $off ) ); // phpcs:ignore WordPress.DB
	}
	$wpdb->query( "DROP TABLE IF EXISTS $old" ); // phpcs:ignore WordPress.DB
	update_option( 'flxs_events_shared', time(), true );
}
