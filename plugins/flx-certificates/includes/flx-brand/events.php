<?php
/**
 * FluxWP shared events: one event table for every FluxWP package (1.4.0).
 *
 * An event (a planting day, a meeting, a training) lives here once. Packages attach their own records to it
 * (Flux Certificates: certificates, later Flux Forms registrations, Flux Donate gifts) and tell the others what
 * they have through filters, so the event screen (Flux CRM › Events, or Flux Certificates › Events when Flux CRM
 * is not installed) shows every package's numbers and people without knowing the packages.
 *
 * Table: {prefix}flx_events. Packages add the columns they own with `flx_events_columns`.
 *
 * API: flx_events_table(), flx_events_ready(), flx_event_get(), flx_events_query(), flx_event_fields(),
 *      flx_event_save(), flx_event_set_status(), flx_event_delete(), flx_event_people(), flx_event_stats(),
 *      flx_event_actions(), flx_event_ensure_post(), flx_event_map_url().
 * Filters: flx_events_columns, flx_event_fields (form fields with a section, label and type; extra fields of a
 *          package come with its own translated label), flx_event_people (person ids per package),
 *          flx_event_stats (numbers per package), flx_event_actions (buttons per package), flx_event_post_type.
 * Actions: flx_events_before_install, flx_events_installed, flx_event_saved( $id, $row, $is_new ),
 *          flx_event_status( $id, $status, $before ), flx_event_deleting( $id, $row ).
 *
 * @package flx-brand
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'FLX_EVENTS_SCHEMA' ) ) {
	return;
}

define( 'FLX_EVENTS_SCHEMA', 1 );

function flx_events_table() {
	global $wpdb;
	return $wpdb->prefix . 'flx_events';
}

/** Column definitions (name => SQL type). Package columns come from `flx_events_columns`. */
function flx_events_columns() {
	$core  = array(
		'id'           => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
		'name'         => "varchar(190) NOT NULL DEFAULT ''",
		'event_date'   => 'date DEFAULT NULL',
		'event_time'   => "varchar(20) NOT NULL DEFAULT ''",
		'status'       => "varchar(20) NOT NULL DEFAULT 'open'",
		'summary'      => 'text NULL',
		'image_id'     => 'bigint(20) unsigned NOT NULL DEFAULT 0',
		'post_id'      => 'bigint(20) unsigned NOT NULL DEFAULT 0',
		'loc_name'     => "varchar(190) NOT NULL DEFAULT ''",
		'loc_address'  => 'text NULL',
		'loc_map'      => "varchar(500) NOT NULL DEFAULT ''",
		'loc_announce' => 'date DEFAULT NULL',
		'loc_hits_qr'  => 'int(11) NOT NULL DEFAULT 0',
		'loc_hits_web' => 'int(11) NOT NULL DEFAULT 0',
		'note'         => 'text NULL',
		'meta'         => 'longtext NULL',
		'created_by'   => 'bigint(20) unsigned NOT NULL DEFAULT 0',
		'created_at'   => 'datetime NOT NULL',
		'updated_at'   => 'datetime NOT NULL',
		'closed_at'    => 'datetime DEFAULT NULL',
	);
	foreach ( (array) apply_filters( 'flx_events_columns', array() ) as $name => $sql ) {
		$name = strtolower( (string) $name );
		if ( ! isset( $core[ $name ] ) && preg_match( '/^[a-z][a-z0-9_]{0,62}$/', $name ) && is_string( $sql ) && '' !== trim( $sql ) && false === strpos( $sql, ';' ) ) {
			$core[ $name ] = trim( $sql );
		}
	}
	return $core;
}

function flx_events_schema_sig() {
	return FLX_EVENTS_SCHEMA . ':' . md5( wp_json_encode( flx_events_columns() ) );
}

function flx_events_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$cols = array();
	foreach ( flx_events_columns() as $name => $sql ) {
		$cols[] = $name . ' ' . $sql;
	}
	$cols[] = 'PRIMARY KEY  (id)';
	$cols[] = 'KEY status (status)';
	$cols[] = 'KEY event_date (event_date)';
	$cols[] = 'KEY post_id (post_id)';
	dbDelta( 'CREATE TABLE ' . flx_events_table() . " (\n" . implode( ",\n", $cols ) . "\n) " . $wpdb->get_charset_collate() . ';' );
	update_option( 'flx_events_schema', flx_events_schema_sig(), true );
	do_action( 'flx_events_installed' );
}

/** Makes sure the table exists and has every package's columns. A package moving its old table hooks `flx_events_before_install`. */
function flx_events_ready() {
	static $ok = null;
	if ( null !== $ok ) {
		return $ok;
	}
	$ok = true;
	if ( get_option( 'flx_events_schema' ) !== flx_events_schema_sig() ) {
		do_action( 'flx_events_before_install' );
		flx_events_install();
	}
	return $ok;
}

/** @return array|null */
function flx_event_get( $id ) {
	global $wpdb;
	$id = (int) $id;
	if ( ! $id || ! flx_events_ready() ) {
		return null;
	}
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flx_events_table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	return $row ? $row : null;
}

/**
 * @param array $f status (open|closed), search, ids[], date (Y-m-d), post_id, per_page (default 200, max 500), page, count (bool).
 *                 Order: open first, dated newest first, then id.
 * @return array|int
 */
function flx_events_query( array $f = array() ) {
	global $wpdb;
	if ( ! flx_events_ready() ) {
		return empty( $f['count'] ) ? array() : 0;
	}
	$t    = flx_events_table();
	$w    = array( '1=1' );
	$args = array();
	if ( ! empty( $f['status'] ) && in_array( $f['status'], array( 'open', 'closed' ), true ) ) {
		$w[]    = 'status = %s';
		$args[] = $f['status'];
	}
	if ( isset( $f['search'] ) && '' !== trim( (string) $f['search'] ) ) {
		$like = '%' . $wpdb->esc_like( trim( (string) $f['search'] ) ) . '%';
		$w[]  = '(name LIKE %s OR loc_name LIKE %s)';
		array_push( $args, $like, $like );
	}
	if ( ! empty( $f['date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $f['date'] ) ) {
		$w[]    = 'event_date = %s';
		$args[] = $f['date'];
	}
	if ( ! empty( $f['post_id'] ) ) {
		$w[]    = 'post_id = %d';
		$args[] = (int) $f['post_id'];
	}
	if ( ! empty( $f['ids'] ) ) {
		$ids = array_filter( array_map( 'intval', (array) $f['ids'] ) );
		$w[] = $ids ? 'id IN (' . implode( ',', $ids ) . ')' : '1=0';
	}
	$where = implode( ' AND ', $w );
	if ( ! empty( $f['count'] ) ) {
		$sql = "SELECT COUNT(*) FROM $t WHERE $where";
		return (int) $wpdb->get_var( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB
	}
	$per  = min( 500, max( 1, (int) ( $f['per_page'] ?? 200 ) ) );
	$off  = ( max( 1, (int) ( $f['page'] ?? 1 ) ) - 1 ) * $per;
	$sql  = "SELECT * FROM $t WHERE $where ORDER BY status = 'closed', (event_date IS NULL), event_date DESC, id DESC LIMIT %d OFFSET %d";
	$args = array_merge( $args, array( $per, $off ) );
	return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

/**
 * Editable fields, in sections. Core fields carry no label here (this module has no text domain): the screen that
 * shows the form labels them. A package adds its own fields with a translated label and, if needed, a column.
 *
 * Field: section, label, type (text|textarea|date|time|number|url|image|post), placeholder, help, order,
 *        sanitize (callable, optional; default by type).
 */
function flx_event_fields() {
	$core = array(
		'name'         => array( 'section' => 'event', 'type' => 'text', 'required' => true, 'order' => 10 ),
		'event_date'   => array( 'section' => 'event', 'type' => 'date', 'order' => 20 ),
		'event_time'   => array( 'section' => 'event', 'type' => 'time', 'order' => 30 ),
		'summary'      => array( 'section' => 'event', 'type' => 'textarea', 'order' => 40 ),
		'image_id'     => array( 'section' => 'event', 'type' => 'image', 'order' => 50 ),
		'loc_name'     => array( 'section' => 'location', 'type' => 'text', 'order' => 10 ),
		'loc_announce' => array( 'section' => 'location', 'type' => 'date', 'order' => 20 ),
		'loc_address'  => array( 'section' => 'location', 'type' => 'textarea', 'order' => 30 ),
		'loc_map'      => array( 'section' => 'location', 'type' => 'url', 'order' => 40, 'sanitize' => 'flx_event_map_url' ),
		'post_id'      => array( 'section' => 'page', 'type' => 'post', 'order' => 10 ),
		'note'         => array( 'section' => 'note', 'type' => 'textarea', 'order' => 10 ),
	);
	$out = array();
	foreach ( (array) apply_filters( 'flx_event_fields', $core ) as $k => $f ) {
		$k = strtolower( (string) $k );
		if ( ! preg_match( '/^[a-z][a-z0-9_]{0,62}$/', $k ) || ! is_array( $f ) ) {
			continue;
		}
		$out[ $k ] = wp_parse_args( $f, array( 'section' => 'event', 'label' => '', 'type' => 'text', 'placeholder' => '', 'help' => '', 'order' => 100, 'required' => false ) );
	}
	uasort(
		$out,
		static function ( $a, $b ) {
			return (int) $a['order'] - (int) $b['order'];
		}
	);
	return $out;
}

/** Map field: a Google/Apple/OSM link or "41.01, 28.97" → link. */
function flx_event_map_url( $v ) {
	$v = trim( (string) $v );
	if ( preg_match( '/^(-?\d{1,2}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)$/', $v, $m ) ) {
		return 'https://www.google.com/maps?q=' . $m[1] . ',' . $m[2];
	}
	return preg_match( '#^https?://#i', $v ) ? esc_url_raw( $v ) : '';
}

/** Post type of the site page of an event (the theme's "etkinlik" by default). */
function flx_event_post_type() {
	return sanitize_key( (string) apply_filters( 'flx_event_post_type', 'etkinlik' ) );
}

function flx_event_sanitize( $key, array $f, $v ) {
	if ( ! empty( $f['sanitize'] ) && is_callable( $f['sanitize'] ) ) {
		return call_user_func( $f['sanitize'], $v );
	}
	switch ( $f['type'] ) {
		case 'textarea':
			return sanitize_textarea_field( (string) $v );
		case 'date':
			$v = sanitize_text_field( (string) $v );
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : null;
		case 'time':
			return substr( sanitize_text_field( (string) $v ), 0, 20 );
		case 'number':
		case 'image':
			return max( 0, (int) $v );
		case 'post':
			$pid = (int) $v;
			return ( $pid > 0 && flx_event_post_type() === get_post_type( $pid ) ) ? $pid : 0;
		case 'url':
			return esc_url_raw( (string) $v );
		default:
			return mb_substr( sanitize_text_field( (string) $v ), 0, 190 );
	}
}

/**
 * Creates or updates an event from posted fields (only fields present in $data are written).
 *
 * @param array $data Field values; create_post (bool) also makes the site page.
 * @return int|WP_Error Event id.
 */
function flx_event_save( array $data, $id = 0 ) {
	global $wpdb;
	if ( ! flx_events_ready() ) {
		return new WP_Error( 'flx_event_db', 'Events table is not ready.' );
	}
	$id     = (int) $id;
	$before = $id ? flx_event_get( $id ) : null;
	if ( $id && ! $before ) {
		return new WP_Error( 'flx_event_404', 'Event not found.' );
	}
	$cols = flx_events_columns();
	$row  = array();
	foreach ( flx_event_fields() as $k => $f ) {
		if ( array_key_exists( $k, $data ) && isset( $cols[ $k ] ) ) {
			$row[ $k ] = flx_event_sanitize( $k, $f, $data[ $k ] );
		}
	}
	if ( ( ! $id || array_key_exists( 'name', $row ) ) && '' === trim( (string) ( $row['name'] ?? '' ) ) ) {
		return new WP_Error( 'flx_event_name', 'Event name is required.' );
	}
	$now               = current_time( 'mysql', true );
	$row['updated_at'] = $now;
	if ( $id ) {
		$wpdb->update( flx_events_table(), $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
	} else {
		$row['status']     = 'open';
		$row['created_by'] = get_current_user_id();
		$row['created_at'] = $now;
		if ( ! $wpdb->insert( flx_events_table(), $row ) ) { // phpcs:ignore WordPress.DB
			return new WP_Error( 'flx_event_db', 'The event could not be saved.' );
		}
		$id = (int) $wpdb->insert_id;
	}
	if ( ! empty( $data['create_post'] ) ) {
		flx_event_ensure_post( $id );
	}
	$e = flx_event_get( $id );
	// The event image becomes the featured image of its site page.
	if ( $e && (int) $e['post_id'] && (int) $e['image_id'] && (int) get_post_thumbnail_id( (int) $e['post_id'] ) !== (int) $e['image_id'] ) {
		set_post_thumbnail( (int) $e['post_id'], (int) $e['image_id'] );
	}
	do_action( 'flx_event_saved', $id, $e, ! $before );
	return $id;
}

/** open | closed. Closed events are archived: their records stay valid, packages stop adding new ones. */
function flx_event_set_status( $id, $status ) {
	global $wpdb;
	$e = flx_event_get( $id );
	if ( ! $e || ! in_array( $status, array( 'open', 'closed' ), true ) || $e['status'] === $status ) {
		return false;
	}
	$now = current_time( 'mysql', true );
	$wpdb->update( flx_events_table(), array( 'status' => $status, 'closed_at' => 'closed' === $status ? $now : null, 'updated_at' => $now ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	do_action( 'flx_event_status', (int) $id, $status, $e );
	return true;
}

/** Deletes the event. Packages detach their records on `flx_event_deleting` (they are not deleted). */
function flx_event_delete( $id ) {
	global $wpdb;
	$e = flx_event_get( $id );
	if ( ! $e ) {
		return false;
	}
	do_action( 'flx_event_deleting', (int) $id, $e );
	$wpdb->delete( flx_events_table(), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	return true;
}

/**
 * People of an event, from every package: [ person_id => [ 'source' => label, ... ] ].
 * A package adds `$out[ $person_id ][] = 'Certificate'`-style labels through `flx_event_people`.
 */
function flx_event_people( $id ) {
	$out = array();
	foreach ( (array) apply_filters( 'flx_event_people', array(), (int) $id ) as $pid => $why ) {
		$pid = (int) $pid;
		if ( $pid ) {
			$out[ $pid ] = array_values( array_unique( array_filter( array_map( 'strval', (array) $why ) ) ) );
		}
	}
	return $out;
}

/**
 * Numbers packages show on the event card: [ [ 'value' => '120', 'label' => 'saplings', 'hint' => '', 'progress' => 0..100|null ], ... ].
 *
 * @param array $e Event row.
 */
function flx_event_stats( array $e ) {
	$out = array();
	foreach ( (array) apply_filters( 'flx_event_stats', array(), $e ) as $s ) {
		if ( is_array( $s ) && isset( $s['value'], $s['label'] ) ) {
			$out[] = wp_parse_args( $s, array( 'hint' => '', 'progress' => null ) );
		}
	}
	return $out;
}

/** Links packages add to the event card: [ [ 'label' => …, 'url' => …, 'primary' => bool, 'target' => '' ], ... ]. */
function flx_event_actions( array $e ) {
	$out = array();
	foreach ( (array) apply_filters( 'flx_event_actions', array(), $e ) as $a ) {
		if ( is_array( $a ) && ! empty( $a['label'] ) && ! empty( $a['url'] ) ) {
			$out[] = wp_parse_args( $a, array( 'primary' => false, 'target' => '' ) );
		}
	}
	return $out;
}

/**
 * Makes sure the event has a page on the site (a post of flx_event_post_type()): links a published post with the
 * same title, or creates one. $publish publishes a linked draft.
 */
function flx_event_ensure_post( $id, $publish = false ) {
	global $wpdb;
	$e  = flx_event_get( $id );
	$pt = flx_event_post_type();
	if ( ! $e || ! post_type_exists( $pt ) ) {
		return 0;
	}
	$post = (int) $e['post_id'] ? get_post( (int) $e['post_id'] ) : null;
	if ( $post && $pt === $post->post_type && 'trash' !== $post->post_status ) {
		if ( $publish && in_array( $post->post_status, array( 'draft', 'pending', 'private', 'auto-draft' ), true ) ) {
			wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'publish' ) );
		}
		return (int) $post->ID;
	}
	$pid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_title = %s ORDER BY ID DESC LIMIT 1", $pt, $e['name'] ) ); // phpcs:ignore WordPress.DB
	if ( $pid && ! flx_events_query( array( 'post_id' => $pid, 'count' => true ) ) ) {
		$wpdb->update( flx_events_table(), array( 'post_id' => $pid ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
		return $pid;
	}
	$pid = wp_insert_post(
		array(
			'post_type'    => $pt,
			'post_status'  => 'publish',
			'post_title'   => $e['name'],
			'post_excerpt' => (string) $e['summary'],
			'post_content' => $e['summary'] ? '<!-- wp:paragraph --><p>' . esc_html( $e['summary'] ) . '</p><!-- /wp:paragraph -->' : '',
		),
		true
	);
	if ( is_wp_error( $pid ) ) {
		return 0;
	}
	$wpdb->update( flx_events_table(), array( 'post_id' => (int) $pid ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
	if ( (int) $e['image_id'] ) {
		set_post_thumbnail( (int) $pid, (int) $e['image_id'] );
	}
	return (int) $pid;
}
