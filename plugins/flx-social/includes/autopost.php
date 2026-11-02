<?php
/**
 * Sitede yazı yayınlanınca sosyal gönderi hazırlar (Ayarlar › Otomatik paylaşım).
 * Kip: taslak (biri gözden geçirip gönderir) ya da N dk sonra zamanlanmış. Her yazı için bir kez.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'transition_post_status', 'flxsm_autopost_maybe', 20, 3 );

function flxsm_autopost_maybe( $new, $old, $post ) {
	if ( 'publish' !== $new || 'publish' === $old || ! $post instanceof WP_Post ) {
		return;
	}
	$a = (array) flxsm_setting( 'auto', array() );
	if ( empty( $a['enabled'] ) || empty( $a['accounts'] ) || ! in_array( $post->post_type, (array) ( $a['post_types'] ?? array() ), true ) ) {
		return;
	}
	if ( get_post_meta( $post->ID, '_flxsm_auto', true ) || wp_is_post_revision( $post ) || ! empty( $post->post_password ) ) {
		return;
	}
	if ( apply_filters( 'flxsm_autopost_skip', false, $post ) ) {
		return;
	}
	update_post_meta( $post->ID, '_flxsm_auto', 1 );
	flxsm_autopost_create( $post, $a );
}

/** Şablon: {title} {excerpt} {link} {site} {author} {category} {tags}. */
function flxsm_autopost_text( WP_Post $post, $tpl ) {
	$excerpt = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 40, '…' );
	$cats    = get_the_terms( $post, 'category' );
	$tags    = get_the_terms( $post, 'post_tag' );
	$hash    = is_array( $tags ) ? implode( ' ', array_map( fn( $t ) => '#' . preg_replace( '/[^\p{L}\p{N}_]+/u', '', $t->name ), array_slice( $tags, 0, 5 ) ) ) : '';
	$author  = get_userdata( (int) $post->post_author );
	$text    = strtr(
		(string) $tpl,
		array(
			'{title}'    => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'{excerpt}'  => html_entity_decode( $excerpt, ENT_QUOTES, 'UTF-8' ),
			'{link}'     => get_permalink( $post ),
			'{site}'     => get_bloginfo( 'name' ),
			'{author}'   => $author ? $author->display_name : '',
			'{category}' => is_array( $cats ) && $cats ? $cats[0]->name : '',
			'{tags}'     => $hash,
		)
	);
	return trim( preg_replace( "/\n{3,}/", "\n\n", $text ) );
}

function flxsm_autopost_create( WP_Post $post, array $a ) {
	$text    = flxsm_autopost_text( $post, (string) ( $a['template'] ?? "{title}\n\n{link}" ) );
	$media   = array();
	$thumb   = (int) get_post_thumbnail_id( $post );
	if ( ! empty( $a['image'] ) && $thumb ) {
		$media[] = $thumb;
	}
	$active  = wp_list_pluck( flxsm_accounts( true ), 'id' );
	$targets = array();
	foreach ( array_map( 'absint', (array) $a['accounts'] ) as $aid ) {
		if ( in_array( $aid, $active, true ) ) {
			$targets[] = array(
				'account_id' => $aid,
				'options'    => array(
					'link'  => get_permalink( $post ),
					'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
				),
			);
		}
	}
	if ( ! $targets ) {
		return;
	}
	$id = flxsm_save_post(
		array(
			'title'   => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'content' => $text,
			'media'   => $media,
			'targets' => $targets,
			'source'  => 'auto',
			'user_id' => (int) $post->post_author,
			'note'    => sprintf( 'wp:%d', $post->ID ),
		)
	);
	if ( is_wp_error( $id ) ) {
		flxsm_log( 'auto: ' . $id->get_error_message(), 'error' );
		return;
	}
	update_post_meta( $post->ID, '_flxsm_auto', $id );
	if ( 'schedule' === ( $a['mode'] ?? 'draft' ) ) {
		global $wpdb;
		$wpdb->update( flxsm_table( 'posts' ), array( 'scheduled_at' => time() + 60 * max( 0, (int) ( $a['delay'] ?? 10 ) ) ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		$res = flxsm_apply_action( $id, 'schedule', array( '_system' => 1 ) );
		if ( is_wp_error( $res ) ) {
			/* Ağ kuralına uymuyorsa (ör. görselsiz Instagram) taslakta kalır, panelde düzeltilir. */
			flxsm_log( 'auto #' . $id . ': ' . $res->get_error_message(), 'warning' );
		}
		flxsm_refresh_post_status( $id );
	}
	do_action( 'flxsm_autopost_created', $id, $post );
}
