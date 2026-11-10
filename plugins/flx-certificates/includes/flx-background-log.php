<?php
/** Bounded operational journal; no message bodies, recipients or credentials. */
defined( 'ABSPATH' ) || exit;
if ( ! function_exists( 'flx_background_log' ) ) {
	function flx_background_log( $event, $context = array() ) {
		global $wpdb;
		static $logging = false;
		if ( $logging ) { return; } $logging = true;
		try {
		$allowed = array( 'plugin', 'hook', 'job', 'batch', 'campaign', 'item', 'state', 'code', 'attempt', 'count', 'elapsed_ms', 'delivered', 'failed', 'pending', 'page', 'error_class', 'error_file', 'error_line' );
		$clean = array();
		foreach ( array_intersect_key( (array) $context, array_flip( $allowed ) ) as $key => $value ) {
			if ( is_scalar( $value ) ) { $clean[ $key ] = is_string( $value ) ? mb_substr( sanitize_text_field( $value ), 0, 160 ) : $value; }
		}
		$entry = array( 'event' => preg_replace( '/[^a-z0-9_.-]/', '', strtolower( $event ) ), 'time' => time(), 'context' => $clean );
		$key = 'flx_background_event_' . time() . '_' . wp_generate_uuid4();
		add_option( $key, $entry, '', false );
		if ( (int) get_option( 'flx_background_journal_pruned' ) < time() - 60 ) {
			update_option( 'flx_background_journal_pruned', time(), false );
			$pattern = $wpdb->esc_like( 'flx_background_event_' ) . '%';
			$limit = max( 100, min( 10000, (int) apply_filters( 'flx_background_log_limit', 2000 ) ) );
			$boundary = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC LIMIT 1 OFFSET %d", $pattern, $limit - 1 ) );
			$age = 'flx_background_event_' . ( time() - max( 1, (int) apply_filters( 'flx_background_log_days', 14 ) ) * DAY_IN_SECONDS ) . '_';
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND (option_name < %s OR option_id < %d) LIMIT 5000", $pattern, $age, $boundary ) );
		}
		do_action( 'flx_background_logged', $entry );
		} catch ( Throwable $error ) { /* Operational logging must not stop a delivery. */ }
		finally { $logging = false; }
	}
	function flx_background_events( $limit = 100, $campaign = 0 ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_id,option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC LIMIT %d", $wpdb->esc_like( 'flx_background_event_' ) . '%', $campaign ? 2000 : max( 1, min( 200, (int) $limit ) ) ), ARRAY_A );
		$out = array();
		foreach ( $rows as $row ) {
			$entry = maybe_unserialize( $row['option_value'] );
			if ( ! is_array( $entry ) || ( $campaign && (int) ( $entry['context']['campaign'] ?? 0 ) !== (int) $campaign ) ) { continue; }
			$entry['id'] = (int) $row['option_id']; $out[] = $entry;
			if ( count( $out ) >= $limit ) { break; }
		}
		return $out;
	}
}
