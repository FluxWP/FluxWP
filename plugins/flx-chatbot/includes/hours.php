<?php
/**
 * Canlı destek saatleri ve devir hedefi.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

/** Flux Chat etkin ve sohbet alabilir mi. */
function flxcb_flux_chat_active() {
	return function_exists( 'flxc_insert_message' ) && function_exists( 'flxc_ensure_chat' );
}

/** Devir nereye: 'flx-chat' | 'inbox' | '' (canlı destek kapalı). */
function flxcb_handoff_target() {
	if ( 'ai' === flxcb_setting( 'mode' ) ) {
		return '';
	}
	$to = (string) flxcb_setting( 'handoff_to' );
	if ( 'inbox' === $to ) {
		return 'inbox';
	}
	if ( function_exists( 'flxcb_fc_ready' ) && flxcb_fc_ready() ) {
		return 'flx-chat';
	}
	return 'inbox';
}

/** Şu an temsilci saati mi. */
function flxcb_is_online( $ts = null ) {
	if ( '' === flxcb_handoff_target() ) {
		return false;
	}
	$mode = (string) flxcb_setting( 'hours_mode' );
	if ( 'flx-chat' === $mode && function_exists( 'flxc_is_open_hours' ) ) {
		return (bool) flxc_is_open_hours();
	}
	if ( 'custom' !== $mode ) {
		return (bool) apply_filters( 'flxcb_is_online', true );
	}
	$now   = new DateTimeImmutable( '@' . ( null === $ts ? time() : (int) $ts ) );
	$now   = $now->setTimezone( wp_timezone() );
	$day   = (int) $now->format( 'N' );
	$hours = (array) flxcb_setting( 'hours' );
	$h     = $hours[ $day ] ?? null;
	if ( ! $h || empty( $h['on'] ) ) {
		return (bool) apply_filters( 'flxcb_is_online', false );
	}
	$cur  = $now->format( 'H:i' );
	$from = (string) ( $h['from'] ?? '09:00' );
	$to   = (string) ( $h['to'] ?? '18:00' );
	$open = $from <= $to ? ( $cur >= $from && $cur < $to ) : ( $cur >= $from || $cur < $to ); // gece yarısını geçen vardiya
	return (bool) apply_filters( 'flxcb_is_online', $open );
}

/** Ziyaretçiye gösterilecek "genelde … arası" metni. */
function flxcb_hours_text() {
	if ( 'custom' !== flxcb_setting( 'hours_mode' ) ) {
		return '';
	}
	$hours = (array) flxcb_setting( 'hours' );
	$on    = array();
	foreach ( $hours as $d => $h ) {
		if ( ! empty( $h['on'] ) ) {
			$on[ (int) $d ] = $h['from'] . '–' . $h['to'];
		}
	}
	if ( ! $on ) {
		return '';
	}
	$uniq = array_unique( $on );
	if ( 1 === count( $uniq ) ) {
		$days = array_keys( $on );
		if ( array( 1, 2, 3, 4, 5 ) === $days ) {
			/* translators: %s: time range */
			return sprintf( __( 'Weekdays %s', 'flx-chatbot' ), reset( $uniq ) );
		}
		if ( 7 === count( $days ) ) {
			/* translators: %s: time range */
			return sprintf( __( 'Every day %s', 'flx-chatbot' ), reset( $uniq ) );
		}
	}
	$names = flxcb_day_names( true );
	$parts = array();
	foreach ( $on as $d => $r ) {
		$parts[] = $names[ $d ] . ' ' . $r;
	}
	return implode( ', ', $parts );
}

function flxcb_day_names( $short = false ) {
	return $short
		? array( 1 => __( 'Mon', 'flx-chatbot' ), 2 => __( 'Tue', 'flx-chatbot' ), 3 => __( 'Wed', 'flx-chatbot' ), 4 => __( 'Thu', 'flx-chatbot' ), 5 => __( 'Fri', 'flx-chatbot' ), 6 => __( 'Sat', 'flx-chatbot' ), 7 => __( 'Sun', 'flx-chatbot' ) )
		: array( 1 => __( 'Monday', 'flx-chatbot' ), 2 => __( 'Tuesday', 'flx-chatbot' ), 3 => __( 'Wednesday', 'flx-chatbot' ), 4 => __( 'Thursday', 'flx-chatbot' ), 5 => __( 'Friday', 'flx-chatbot' ), 6 => __( 'Saturday', 'flx-chatbot' ), 7 => __( 'Sunday', 'flx-chatbot' ) );
}

/** Ana ekrandaki ekip avatarları: son 30 günde cevap veren temsilciler, yoksa yöneticiler. */
function flxcb_team() {
	$cached = get_transient( 'flxcb_team' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT user_id FROM ' . flxcb_table( 'messages' ) . " WHERE role = 'agent' AND user_id > 0 AND ts > %d ORDER BY id DESC LIMIT 3", ( time() - 30 * DAY_IN_SECONDS ) * 1000 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	if ( ! $ids ) {
		$ids = get_users( array( 'role' => 'administrator', 'number' => 3, 'fields' => 'ID' ) );
	}
	$out = array();
	foreach ( array_slice( (array) $ids, 0, 3 ) as $id ) {
		$out[] = array(
			'name'   => flxcb_user_name( (int) $id ),
			'avatar' => get_avatar_url( (int) $id, array( 'size' => 64 ) ),
		);
	}
	set_transient( 'flxcb_team', $out, 6 * HOUR_IN_SECONDS );
	return $out;
}
