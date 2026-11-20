<?php
/** Independently owned post fields. Legacy body hashes intentionally keep their original shape. */
defined( 'ABSPATH' ) || exit;

/** Presets requiring safe editorial migrations can fail closed on older engines. */
function flxk_content_fields_supported() { return true; }

function flxk_target_forced( $target, array $opts ) {
	return ! empty( $opts['force'] ) && ( true === $opts['force'] || in_array( $target, (array) $opts['force'], true ) );
}

/** A slug collision is not permission to adopt an existing post, even when its text matches. */
function flxk_content_identity_guard( array $op, $id, $key, array $st, array $opts ) {
	if ( $id ) {
		$matches = get_posts( array( 'post_type' => get_post_type( $id ), 'post_status' => 'any', 'meta_key' => '_flxk_key', 'meta_value' => $key, 'fields' => 'ids', 'posts_per_page' => 2 ) );
		if ( count( $matches ) > 1 ) {
			$op['status'] = 'manual'; $op['apply'] = false; $op['reason'] = 'ambiguous-content-key'; return $op;
		}
	}
	$op['identity_values'] = $id ? get_post_meta( $id, '_flxk_key', false ) : array();
	$op['identity'] = $id ? (string) get_post_meta( $id, '_flxk_key', true ) : '';
	if ( $id && flxk_target_forced( $op['target'], $opts ) && $op['identity'] !== $key ) {
		$op['status'] = 'change'; $op['apply'] = true;
	}
	if ( $id && ! flxk_target_forced( $op['target'], $opts ) &&
		( (string) get_post_meta( $id, '_flxk_key', true ) !== $key || ! isset( $st['owned'][ $op['target'] ] ) || ( isset( $st['content_ids'][ $op['target'] ] ) && (int) $st['content_ids'][ $op['target'] ] !== (int) $id ) ) ) {
		$op['status'] = 'manual';
		$op['apply'] = false;
		$op['reason'] = 'unowned-content';
	}
	return $op;
}

/** Preserve absent vs empty, duplicate values and serialized values; do not read unrelated fields. */
function flxk_content_field_read( $id, $kind, $field ) {
	if ( 'meta' === $kind ) { return get_post_meta( $id, $field, false ); }
	$ids = wp_get_object_terms( $id, $field, array( 'fields' => 'ids' ) );
	if ( is_wp_error( $ids ) ) { return $ids; }
	$ids = array_map( 'intval', $ids ); sort( $ids );
	return $ids;
}

function flxk_content_field_value( $kind, $field, $value ) {
	if ( 'meta' === $kind ) {
		// WordPress stores scalar meta as strings, preserving arrays/objects through serialization.
		return array( is_scalar( $value ) || null === $value ? (string) $value : $value );
	}
	$ids = array();
	foreach ( (array) $value as $term ) {
		$found = term_exists( $term, $field );
		$ids[] = $found ? (int) ( is_array( $found ) ? $found['term_id'] : $found ) : $term;
	}
	sort( $ids );
	return array_values( array_unique( $ids, SORT_REGULAR ) );
}

function flxk_content_field_plan( $preset_id, array $item, $id, array $body, array $st, array $opts ) {
	$ops = array();
	foreach ( array( 'meta', 'terms' ) as $kind ) {
		foreach ( (array) ( $item[ $kind ] ?? array() ) as $field => $value ) {
			if ( ! is_string( $field ) || ( 'meta' === $kind && '_flxk_key' === $field ) ) { continue; }
			$target = 'content-field:' . $item['type'] . ':' . $item['key'] . ':' . $kind . ':' . rawurlencode( $field );
			$cur = $id ? flxk_content_field_read( $id, $kind, $field ) : array();
			$new = flxk_content_field_value( $kind, $field, $value );
			$op = flxk_decide( $preset_id, $target, ! empty( $cur ), $cur, $new, $st, $opts, $id && ! is_wp_error( $cur ) && flxk_hash( $cur ) === flxk_hash( $new ) );
			$forced = flxk_target_forced( $target, $opts );
			$identity = ! $id || ( (string) get_post_meta( $id, '_flxk_key', true ) === $item['key'] && isset( $st['owned'][ $body['target'] ] ) && ( ! isset( $st['content_ids'][ $body['target'] ] ) || (int) $st['content_ids'][ $body['target'] ] === (int) $id ) );
			// Old body hashes contain no SEO/terms baseline. Never infer one from unchanged body text.
			if ( ! $forced && ( ! $identity || ( $id && ! isset( $st['owned'][ $target ] ) && ! empty( $cur ) && 'same' !== $op['status'] ) ) ) {
				$op['status'] = 'manual'; $op['apply'] = false; $op['reason'] = 'unknown-field-baseline';
			}
			if ( 'ambiguous-content-key' === ( $body['reason'] ?? '' ) ) { $op['status'] = 'manual'; $op['apply'] = false; $op['reason'] = 'ambiguous-content-key'; }
			if ( is_wp_error( $cur ) || ( 'terms' === $kind && ! taxonomy_exists( $field ) ) ) {
				$op['status'] = 'skip'; $op['apply'] = false; $op['reason'] = 'missing-taxonomy';
			}
			$ops[] = $op + array( 'section' => 'content_field', 'type' => $item['type'], 'key' => $item['key'], 'id' => $id, 'kind' => $kind, 'field' => $field, 'value' => $value );
		}
	}
	return $ops;
}

function flxk_content_field_execute( array $op, array &$ctx, array &$run ) {
	$id = (int) ( $ctx['refs'][ $op['type'] ][ $op['key'] ] ?? 0 );
	if ( ! $id || ( $op['id'] && $id !== (int) $op['id'] ) || (string) get_post_meta( $id, '_flxk_key', true ) !== $op['key'] ) {
		$run['errors'][] = $op['target'] . ': content identity unavailable'; return;
	}
	$old = flxk_content_field_read( $id, $op['kind'], $op['field'] );
	// Recheck after body save/hooks, rather than overwriting a concurrently changed field.
	if ( is_wp_error( $old ) || ( $op['id'] && flxk_hash( $old ) !== flxk_hash( $op['old'] ?? array() ) ) ) {
		$run['errors'][] = $op['target'] . ': field changed since preview'; return;
	}
	$value = flxk_resolve( $op['value'], $ctx );
	if ( 'meta' === $op['kind'] ) {
		if ( count( $old ) > 1 ) { delete_post_meta( $id, $op['field'] ); }
		update_post_meta( $id, $op['field'], wp_slash( $value ) );
	} else {
		$result = wp_set_object_terms( $id, flxk_content_field_value( 'terms', $op['field'], $value ), $op['field'] );
		if ( is_wp_error( $result ) ) { $run['errors'][] = $op['target'] . ': ' . $result->get_error_message(); return; }
	}
	$current = flxk_content_field_read( $id, $op['kind'], $op['field'] );
	$run['backup'][ $op['target'] ] = array( 'id' => $id, 'values' => $old );
	$run['written'][ $op['target'] ] = flxk_hash( $current );
	if ( flxk_hash( $current ) !== flxk_hash( flxk_content_field_value( $op['kind'], $op['field'], $value ) ) ) {
		$run['errors'][] = $op['target'] . ': saved value differs';
	}
}

function flxk_content_field_restore( $target, array $backup ) {
	list( , $type, $key, $kind, $field ) = explode( ':', $target, 5 );
	$id = (int) $backup['id']; $field = rawurldecode( $field );
	if ( ! get_post( $id ) || (string) get_post_meta( $id, '_flxk_key', true ) !== $key ) { return false; }
	if ( 'meta' === $kind ) {
		delete_post_meta( $id, $field );
		foreach ( $backup['values'] as $value ) { add_post_meta( $id, $field, wp_slash( $value ) ); }
	} else {
		$result = wp_set_object_terms( $id, array_map( 'intval', $backup['values'] ), $field );
		if ( is_wp_error( $result ) ) { return false; }
	}
	return flxk_hash( flxk_content_field_read( $id, $kind, $field ) ) === flxk_hash( $backup['values'] );
}
