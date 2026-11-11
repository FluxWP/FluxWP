<?php
/**
 * FluxWP shared people: one person table for every FluxWP package.
 *
 * Any package can find, create and edit a person here without another package being installed. The table belongs
 * to this shared module (newest copy wins, see loader.php), not to a plugin: the first package that needs it creates it.
 * Flux CRM is the management layer on top of the same rows (merge, duplicates, roles, scoring, segments); when it is
 * active it takes over matching and writing through the `flx_person_pre_*` filters, so that logic lives in one place.
 *
 * Tables: {prefix}flx_people, {prefix}flx_people_links (person <-> a package's own record, e.g. donate / donor:12).
 * Phone is stored as digits in E.164 without "+", e-mail lower case, roles packed as ",donor,customer,".
 *
 * API: flx_person_ensure(), flx_person_find(), flx_person_get(), flx_person_update(), flx_person_link(),
 *      flx_person_links(), flx_people_query(), flx_people_table(), flx_people_ready().
 * Filters: flx_people_columns, flx_people_keys (extra columns/keys a package owns), flx_person_phone,
 *          flx_person_pre_find, flx_person_pre_ensure, flx_person_pre_update (short-circuit, used by Flux CRM).
 * Actions: flx_people_installed, flx_person_created( $id, $row ), flx_person_updated( $id, $changes, $before ).
 *
 * @package flx-brand
 */

defined( 'ABSPATH' ) || exit;

// Loaded once (core.php is loaded once); the constant guards a second include. Not function_exists(): the
// functions below are declared at compile time, so that check would be true before this line runs.
if ( defined( 'FLX_PEOPLE_SCHEMA' ) ) {
	return;
}

/** Schema revision of the core columns. Extra columns from packages are tracked by a hash next to it. */
define( 'FLX_PEOPLE_SCHEMA', 1 );

/** @param string $which people|links */
function flx_people_table( $which = 'people' ) {
	global $wpdb;
	return $wpdb->prefix . ( 'links' === $which ? 'flx_people_links' : 'flx_people' );
}

/**
 * Column definitions (name => SQL type). Packages add the columns they own with `flx_people_columns`
 * (e.g. Flux CRM adds its score columns); a column is never dropped or changed by another package.
 */
function flx_people_columns() {
	$core = array(
		'id'               => 'bigint(20) unsigned NOT NULL AUTO_INCREMENT',
		'name'             => "varchar(190) NOT NULL DEFAULT ''",
		'email'            => "varchar(190) NOT NULL DEFAULT ''",
		'phone'            => "varchar(20) NOT NULL DEFAULT ''",
		'user_id'          => 'bigint(20) unsigned NOT NULL DEFAULT 0',
		'roles'            => "varchar(255) NOT NULL DEFAULT ''",
		'status'           => "varchar(16) NOT NULL DEFAULT 'active'",
		'company'          => "varchar(190) NOT NULL DEFAULT ''",
		'city'             => "varchar(100) NOT NULL DEFAULT ''",
		'country'          => "varchar(2) NOT NULL DEFAULT ''",
		'source'           => "varchar(24) NOT NULL DEFAULT ''",
		'consent_email'    => 'tinyint(1) NOT NULL DEFAULT 0',
		'consent_sms'      => 'tinyint(1) NOT NULL DEFAULT 0',
		'consent_whatsapp' => 'tinyint(1) NOT NULL DEFAULT 0',
		'consent_ads'      => 'tinyint(1) NOT NULL DEFAULT 0',
		'consent_at'       => 'datetime NULL DEFAULT NULL',
		'note'             => 'text NULL',
		'meta'             => 'longtext NULL',
		'created_at'       => 'datetime NOT NULL',
		'updated_at'       => 'datetime NOT NULL',
	);
	$extra = (array) apply_filters( 'flx_people_columns', array() );
	foreach ( $extra as $name => $sql ) {
		$name = strtolower( (string) $name );
		if ( ! isset( $core[ $name ] ) && preg_match( '/^[a-z][a-z0-9_]{0,62}$/', $name ) && is_string( $sql ) && '' !== trim( $sql ) && false === strpos( $sql, ';' ) ) {
			$core[ $name ] = trim( $sql );
		}
	}
	return $core;
}

/** Index definitions (name => column list). `flx_people_keys` adds more. */
function flx_people_keys() {
	$keys = array(
		'email'      => 'email',
		'phone'      => 'phone',
		'user_id'    => 'user_id',
		'created_at' => 'created_at',
	);
	foreach ( (array) apply_filters( 'flx_people_keys', array() ) as $name => $cols ) {
		$name = strtolower( (string) $name );
		if ( ! isset( $keys[ $name ] ) && preg_match( '/^[a-z][a-z0-9_]{0,62}$/', $name ) && is_string( $cols ) && preg_match( '/^[a-z0-9_, ]+$/', $cols ) ) {
			$keys[ $name ] = $cols;
		}
	}
	return $keys;
}

/** Signature of the wanted schema: changes when a package adds a column, so the table is brought up to date. */
function flx_people_schema_sig() {
	return FLX_PEOPLE_SCHEMA . ':' . md5( wp_json_encode( array( flx_people_columns(), flx_people_keys() ) ) );
}

/** Creates or extends the tables (dbDelta: adds what is missing, never drops). */
function flx_people_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$c    = $wpdb->get_charset_collate();
	$cols = array();
	foreach ( flx_people_columns() as $name => $sql ) {
		$cols[] = $name . ' ' . $sql;
	}
	$keys = array( 'PRIMARY KEY  (id)' );
	foreach ( flx_people_keys() as $name => $list ) {
		$keys[] = 'KEY ' . $name . ' (' . $list . ')';
	}
	dbDelta( 'CREATE TABLE ' . flx_people_table() . " (\n" . implode( ",\n", array_merge( $cols, $keys ) ) . "\n) $c;" );
	dbDelta(
		'CREATE TABLE ' . flx_people_table( 'links' ) . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
		source varchar(24) NOT NULL DEFAULT '',
		source_id varchar(100) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY source_ref (source,source_id),
		KEY contact_id (contact_id)
		) $c;"
	);
	update_option( 'flx_people_schema', flx_people_schema_sig(), true );
	do_action( 'flx_people_installed' );
}

/**
 * Makes sure the tables exist and match the wanted schema; called by every API function, so the tables appear
 * the first time a package uses people. Cheap: one autoloaded option compare per request.
 * A package that moves its own old table here hooks `flx_people_before_install` (runs once before the first create).
 */
function flx_people_ready() {
	static $ok = null;
	if ( null !== $ok ) {
		return $ok;
	}
	$ok = true;
	if ( get_option( 'flx_people_schema' ) !== flx_people_schema_sig() ) {
		do_action( 'flx_people_before_install' );
		flx_people_install();
	}
	return $ok;
}

function flx_people_now() {
	return current_time( 'mysql', true );
}

/** Phone as stored: digits, E.164 without "+". A 10/11 digit Turkish mobile gets 90. '' = not a number. */
function flx_person_phone( $phone ) {
	$d = preg_replace( '/\D+/', '', (string) $phone );
	if ( '' === $d ) {
		return '';
	}
	if ( 0 === strpos( $d, '00' ) ) {
		$d = substr( $d, 2 );
	}
	if ( 10 === strlen( $d ) && '5' === $d[0] ) {
		$d = '90' . $d;
	} elseif ( 11 === strlen( $d ) && '0' === $d[0] ) {
		$d = '90' . substr( $d, 1 );
	}
	if ( strlen( $d ) < 8 || strlen( $d ) > 15 ) {
		return '';
	}
	return (string) apply_filters( 'flx_person_phone', $d, $phone );
}

function flx_person_email( $email ) {
	$e = strtolower( trim( (string) $email ) );
	return is_email( $e ) ? $e : '';
}

/** @return string ",donor,customer," */
function flx_person_pack_roles( array $roles ) {
	$roles = array_values( array_unique( array_filter( array_map( 'sanitize_key', $roles ) ) ) );
	sort( $roles );
	return $roles ? ',' . implode( ',', $roles ) . ',' : '';
}

function flx_person_roles( $packed ) {
	return array_values( array_filter( explode( ',', (string) $packed ) ) );
}

/** @return array|null Row. */
function flx_person_get( $id ) {
	global $wpdb;
	$id = (int) $id;
	if ( ! $id || ! flx_people_ready() ) {
		return null;
	}
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flx_people_table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	return $row ? $row : null;
}

/** Person id behind a package record, 0 = none. */
function flx_person_by_link( $source, $source_id ) {
	global $wpdb;
	if ( ! flx_people_ready() ) {
		return 0;
	}
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT contact_id FROM ' . flx_people_table( 'links' ) . ' WHERE source = %s AND source_id = %s', (string) $source, (string) $source_id ) ); // phpcs:ignore WordPress.DB
}

/** Ties a package record to a person (moves the tie if it pointed elsewhere). */
function flx_person_link( $person_id, $source, $source_id ) {
	global $wpdb;
	$person_id = (int) $person_id;
	$source    = sanitize_key( (string) $source );
	$source_id = (string) $source_id;
	if ( ! $person_id || '' === $source || '' === $source_id || ! flx_people_ready() ) {
		return;
	}
	$t   = flx_people_table( 'links' );
	$cur = flx_person_by_link( $source, $source_id );
	if ( $cur === $person_id ) {
		return;
	}
	if ( $cur ) {
		$wpdb->update( $t, array( 'contact_id' => $person_id ), array( 'source' => $source, 'source_id' => $source_id ) ); // phpcs:ignore WordPress.DB
		return;
	}
	$wpdb->insert( $t, array( 'contact_id' => $person_id, 'source' => $source, 'source_id' => $source_id, 'created_at' => flx_people_now() ) ); // phpcs:ignore WordPress.DB
}

/** @return array[] source, source_id, created_at */
function flx_person_links( $person_id ) {
	global $wpdb;
	if ( ! flx_people_ready() ) {
		return array();
	}
	return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT source, source_id, created_at FROM ' . flx_people_table( 'links' ) . ' WHERE contact_id = %d ORDER BY id', (int) $person_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

/**
 * Finds a person: package link, site user, e-mail, phone, in that order (exact matches only).
 *
 * @param array $who link [source, id], user_id, email, phone.
 * @return int 0 = none.
 */
function flx_person_find( array $who ) {
	global $wpdb;
	$pre = apply_filters( 'flx_person_pre_find', null, $who );
	if ( null !== $pre ) {
		return (int) $pre;
	}
	if ( ! flx_people_ready() ) {
		return 0;
	}
	$t = flx_people_table();
	if ( ! empty( $who['link'] ) && is_array( $who['link'] ) && isset( $who['link'][1] ) ) {
		$id = flx_person_by_link( $who['link'][0], $who['link'][1] );
		if ( $id && flx_person_get( $id ) ) {
			return $id;
		}
	}
	if ( ! empty( $who['user_id'] ) ) {
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE user_id = %d ORDER BY id LIMIT 1", (int) $who['user_id'] ) ); // phpcs:ignore WordPress.DB
		if ( $id ) {
			return $id;
		}
	}
	$email = flx_person_email( $who['email'] ?? '' );
	if ( '' !== $email ) {
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE email = %s ORDER BY id LIMIT 1", $email ) ); // phpcs:ignore WordPress.DB
		if ( $id ) {
			return $id;
		}
	}
	$phone = flx_person_phone( $who['phone'] ?? '' );
	if ( '' !== $phone ) {
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE phone = %s ORDER BY id LIMIT 1", $phone ) ); // phpcs:ignore WordPress.DB
		if ( $id ) {
			return $id;
		}
	}
	return 0;
}

/**
 * Finds the person or creates one. Data from a package only fills empty fields; what was typed by hand stays.
 * Consents are only ever switched on here. Roles are added, never removed.
 *
 * @param array $d name, email, phone, user_id, roles[], source, link [source, id], company, city, country,
 *                 consent_{email,sms,whatsapp,ads}, note, at (first contact, GMT), allow_empty (create without e-mail/phone).
 * @return int Person id; 0 when there is nothing to identify the person by.
 */
function flx_person_ensure( array $d ) {
	global $wpdb;
	$pre = apply_filters( 'flx_person_pre_ensure', null, $d );
	if ( null !== $pre ) {
		return (int) $pre;
	}
	if ( ! flx_people_ready() ) {
		return 0;
	}
	$t        = flx_people_table();
	$email    = flx_person_email( $d['email'] ?? '' );
	$phone    = flx_person_phone( $d['phone'] ?? '' );
	$uid      = (int) ( $d['user_id'] ?? 0 );
	$name     = trim( wp_strip_all_tags( (string) ( $d['name'] ?? '' ) ) );
	$consents = array( 'email', 'sms', 'whatsapp', 'ads' );
	$id       = flx_person_find( array_merge( $d, array( 'email' => $email, 'phone' => $phone ) ) );
	$now      = flx_people_now();

	if ( ! $id ) {
		if ( '' === $email && '' === $phone && ! $uid && empty( $d['allow_empty'] ) ) {
			return 0;
		}
		$at  = ! empty( $d['at'] ) ? (string) $d['at'] : $now;
		$row = array(
			'name'       => mb_substr( $name, 0, 190 ),
			'email'      => $email,
			'phone'      => $phone,
			'user_id'    => $uid,
			'roles'      => flx_person_pack_roles( (array) ( $d['roles'] ?? array() ) ),
			'status'     => 'active',
			'company'    => mb_substr( sanitize_text_field( (string) ( $d['company'] ?? '' ) ), 0, 190 ),
			'city'       => mb_substr( sanitize_text_field( (string) ( $d['city'] ?? '' ) ), 0, 100 ),
			'country'    => strtoupper( substr( sanitize_key( (string) ( $d['country'] ?? '' ) ), 0, 2 ) ),
			'source'     => sanitize_key( (string) ( $d['source'] ?? 'manual' ) ),
			'created_at' => $at < $now ? $at : $now,
			'updated_at' => $now,
		);
		if ( isset( $d['note'] ) ) {
			$row['note'] = sanitize_textarea_field( (string) $d['note'] );
		}
		$any = 0;
		foreach ( $consents as $k ) {
			$row[ 'consent_' . $k ] = empty( $d[ 'consent_' . $k ] ) ? 0 : 1;
			$any                   += $row[ 'consent_' . $k ];
		}
		if ( $any ) {
			$row['consent_at'] = $now;
		}
		if ( ! $wpdb->insert( $t, $row ) ) { // phpcs:ignore WordPress.DB
			return 0;
		}
		$id = (int) $wpdb->insert_id;
		if ( ! empty( $d['link'] ) && is_array( $d['link'] ) && isset( $d['link'][1] ) ) {
			flx_person_link( $id, $d['link'][0], $d['link'][1] );
		}
		do_action( 'flx_person_created', $id, $row );
		return $id;
	}

	$cur = flx_person_get( $id );
	$upd = array();
	if ( '' === trim( (string) $cur['name'] ) && '' !== $name ) {
		$upd['name'] = mb_substr( $name, 0, 190 );
	}
	if ( '' === $cur['email'] && '' !== $email ) {
		$upd['email'] = $email;
	}
	if ( '' === $cur['phone'] && '' !== $phone ) {
		$upd['phone'] = $phone;
	}
	if ( ! (int) $cur['user_id'] && $uid ) {
		$upd['user_id'] = $uid;
	}
	foreach ( array( 'company' => 190, 'city' => 100 ) as $k => $len ) {
		if ( '' === (string) $cur[ $k ] && ! empty( $d[ $k ] ) ) {
			$upd[ $k ] = mb_substr( sanitize_text_field( (string) $d[ $k ] ), 0, $len );
		}
	}
	foreach ( $consents as $k ) {
		if ( ! empty( $d[ 'consent_' . $k ] ) && ! (int) $cur[ 'consent_' . $k ] ) {
			$upd[ 'consent_' . $k ] = 1;
			$upd['consent_at']      = $now;
		}
	}
	if ( ! empty( $d['roles'] ) ) {
		$roles = flx_person_pack_roles( array_merge( flx_person_roles( $cur['roles'] ), (array) $d['roles'] ) );
		if ( $roles !== (string) $cur['roles'] ) {
			$upd['roles'] = $roles;
		}
	}
	if ( ! empty( $d['at'] ) && (string) $d['at'] < (string) $cur['created_at'] ) {
		$upd['created_at'] = (string) $d['at'];
	}
	if ( $upd ) {
		$upd['updated_at'] = $now;
		$wpdb->update( $t, $upd, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		do_action( 'flx_person_updated', $id, $upd, $cur );
	}
	if ( ! empty( $d['link'] ) && is_array( $d['link'] ) && isset( $d['link'][1] ) ) {
		flx_person_link( $id, $d['link'][0], $d['link'][1] );
	}
	return $id;
}

/**
 * Edits a person by hand (overwrites). An e-mail or phone that already belongs to someone else is refused:
 * deciding whether two people are the same is the management layer's job (Flux CRM merge).
 *
 * @param array $d name, email, phone, user_id, company, city, country, status, note, roles[], consent_*.
 * @return true|WP_Error
 */
function flx_person_update( $id, array $d ) {
	global $wpdb;
	$id  = (int) $id;
	$pre = apply_filters( 'flx_person_pre_update', null, $id, $d );
	if ( null !== $pre ) {
		return $pre;
	}
	$cur = flx_person_get( $id );
	if ( ! $cur ) {
		return new WP_Error( 'flx_person_404', 'Person not found.' );
	}
	$t   = flx_people_table();
	$upd = array();
	if ( array_key_exists( 'name', $d ) ) {
		$upd['name'] = mb_substr( trim( wp_strip_all_tags( (string) $d['name'] ) ), 0, 190 );
	}
	foreach ( array( 'email', 'phone' ) as $k ) {
		if ( ! array_key_exists( $k, $d ) ) {
			continue;
		}
		$raw = trim( (string) $d[ $k ] );
		$v   = 'email' === $k ? flx_person_email( $raw ) : flx_person_phone( $raw );
		if ( '' !== $raw && '' === $v ) {
			return new WP_Error( 'flx_person_' . $k, 'email' === $k ? 'Invalid e-mail address.' : 'Invalid phone number.' );
		}
		if ( '' !== $v ) {
			$other = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE $k = %s AND id <> %d LIMIT 1", $v, $id ) ); // phpcs:ignore WordPress.DB
			if ( $other ) {
				return new WP_Error( 'flx_person_taken', 'email' === $k ? 'This e-mail belongs to another person.' : 'This phone belongs to another person.', array( 'person' => $other, 'field' => $k ) );
			}
		}
		$upd[ $k ] = $v;
	}
	if ( array_key_exists( 'user_id', $d ) ) {
		$upd['user_id'] = max( 0, (int) $d['user_id'] );
	}
	foreach ( array( 'company' => 190, 'city' => 100 ) as $k => $len ) {
		if ( array_key_exists( $k, $d ) ) {
			$upd[ $k ] = mb_substr( sanitize_text_field( (string) $d[ $k ] ), 0, $len );
		}
	}
	if ( array_key_exists( 'country', $d ) ) {
		$upd['country'] = strtoupper( substr( sanitize_key( (string) $d['country'] ), 0, 2 ) );
	}
	if ( array_key_exists( 'status', $d ) ) {
		$upd['status'] = substr( sanitize_key( (string) $d['status'] ), 0, 16 );
	}
	if ( array_key_exists( 'note', $d ) ) {
		$upd['note'] = sanitize_textarea_field( (string) $d['note'] );
	}
	if ( array_key_exists( 'roles', $d ) ) {
		$upd['roles'] = flx_person_pack_roles( (array) $d['roles'] );
	}
	foreach ( array( 'email', 'sms', 'whatsapp', 'ads' ) as $k ) {
		if ( array_key_exists( 'consent_' . $k, $d ) ) {
			$v = empty( $d[ 'consent_' . $k ] ) ? 0 : 1;
			if ( $v !== (int) $cur[ 'consent_' . $k ] ) {
				$upd[ 'consent_' . $k ] = $v;
				$upd['consent_at']      = flx_people_now();
			}
		}
	}
	foreach ( $upd as $k => $v ) {
		if ( (string) $v === (string) ( $cur[ $k ] ?? '' ) ) {
			unset( $upd[ $k ] );
		}
	}
	if ( ! $upd ) {
		return true;
	}
	$upd['updated_at'] = flx_people_now();
	$wpdb->update( $t, $upd, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
	do_action( 'flx_person_updated', $id, $upd, $cur );
	return true;
}

/**
 * Simple list for a package's own people screen or a recipient picker.
 *
 * @param array $f search, role, source (link source: only people tied to that package), consent (email|sms|whatsapp|ads),
 *                 has (email|phone), ids[], orderby (id|name|created_at|updated_at), order, per_page (max 500), page, count (bool).
 * @return array|int Rows, or the total when count is set.
 */
function flx_people_query( array $f = array() ) {
	global $wpdb;
	if ( ! flx_people_ready() ) {
		return empty( $f['count'] ) ? array() : 0;
	}
	$t    = flx_people_table();
	$w    = array( '1=1' );
	$args = array();
	if ( isset( $f['search'] ) && '' !== trim( (string) $f['search'] ) ) {
		$like = '%' . $wpdb->esc_like( trim( (string) $f['search'] ) ) . '%';
		$dg   = preg_replace( '/\D+/', '', (string) $f['search'] );
		$w[]  = '(p.name LIKE %s OR p.email LIKE %s' . ( strlen( $dg ) >= 3 ? ' OR p.phone LIKE %s' : '' ) . ')';
		array_push( $args, $like, $like );
		if ( strlen( $dg ) >= 3 ) {
			$args[] = '%' . $wpdb->esc_like( ltrim( $dg, '0' ) ) . '%';
		}
	}
	if ( ! empty( $f['role'] ) ) {
		$w[]    = 'p.roles LIKE %s';
		$args[] = '%,' . $wpdb->esc_like( sanitize_key( (string) $f['role'] ) ) . ',%';
	}
	if ( ! empty( $f['source'] ) ) {
		$w[]    = 'EXISTS (SELECT 1 FROM ' . flx_people_table( 'links' ) . ' l WHERE l.contact_id = p.id AND l.source = %s)';
		$args[] = sanitize_key( (string) $f['source'] );
	}
	if ( ! empty( $f['consent'] ) && in_array( $f['consent'], array( 'email', 'sms', 'whatsapp', 'ads' ), true ) ) {
		$w[] = 'p.consent_' . $f['consent'] . ' = 1';
	}
	if ( ! empty( $f['has'] ) && in_array( $f['has'], array( 'email', 'phone' ), true ) ) {
		$w[] = 'p.' . $f['has'] . " <> ''";
	}
	if ( ! empty( $f['ids'] ) ) {
		$ids = array_filter( array_map( 'intval', (array) $f['ids'] ) );
		$w[] = $ids ? 'p.id IN (' . implode( ',', $ids ) . ')' : '1=0';
	}
	$where = implode( ' AND ', $w );
	if ( ! empty( $f['count'] ) ) {
		$sql = "SELECT COUNT(*) FROM $t p WHERE $where";
		return (int) $wpdb->get_var( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB
	}
	$by   = in_array( $f['orderby'] ?? '', array( 'id', 'name', 'created_at', 'updated_at' ), true ) ? $f['orderby'] : 'id';
	$dir  = 'asc' === strtolower( (string) ( $f['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC';
	$per  = min( 500, max( 1, (int) ( $f['per_page'] ?? 50 ) ) );
	$off  = ( max( 1, (int) ( $f['page'] ?? 1 ) ) - 1 ) * $per;
	$sql  = "SELECT p.* FROM $t p WHERE $where ORDER BY p.$by $dir, p.id DESC LIMIT %d OFFSET %d";
	$args = array_merge( $args, array( $per, $off ) );
	return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB
}
