<?php
/**
 * Flux Cloud AI credits: a site connected to a FluxWP account (Flux Setup › FluxWP account) can use Flux AI
 * without its own key. Requests go in the OpenRouter format to the account's proxy (Flux Cloud
 * /wp-json/flx-cloud/v1/ai/chat/completions) with the site's Flux Cloud access token; the account's AI credits pay.
 *
 * Setting cloud_ai: auto (own key when there is one, else Flux Cloud), always (Flux Cloud even with own keys),
 * off (only own keys). Contract: wp-extras plugins/flx-cloud/docs/API.md (3b).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** Site is connected to a FluxWP account (Flux Setup 1.6+). */
function flxai_cloud_connected() {
	return class_exists( 'FLXK_Cloud' ) && method_exists( 'FLXK_Cloud', 'connected' ) && FLXK_Cloud::connected();
}

function flxai_cloud_mode() {
	$m = (string) flxai_setting( 'cloud_ai', 'auto' );
	return in_array( $m, array( 'auto', 'always', 'off' ), true ) ? $m : 'auto';
}

/**
 * Credits as Flux Cloud last reported them (account summary `ai`, refreshed from GET /ai/usage at most every
 * 5 minutes, and from the numbers each AI answer brings). Null when the site is not connected or Flux Cloud
 * has no AI credits (older Flux Cloud).
 *
 * @return array|null
 */
function flxai_cloud_status( $fresh = false ) {
	if ( ! flxai_cloud_connected() ) {
		return null;
	}
	$c = $fresh ? false : get_transient( 'flxai_cloud_ai' );
	if ( ! is_array( $c ) ) {
		$sum = (array) FLXK_Cloud::summary();
		$c   = is_array( $sum['ai'] ?? null ) ? $sum['ai'] : null;
		$tok = FLXK_Cloud::access_token();
		if ( ! is_wp_error( $tok ) ) {
			$base = $c && ! empty( $c['base'] ) ? (string) $c['base'] : FLXK_Cloud::url() . '/wp-json/flx-cloud/v1/ai';
			$r    = wp_remote_get(
				untrailingslashit( $base ) . '/usage',
				array(
					'timeout' => 8,
					'headers' => array(
						'Accept'        => 'application/json',
						'Authorization' => 'Bearer ' . $tok,
					),
				)
			);
			if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
				$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
				if ( is_array( $j ) && isset( $j['on'] ) ) {
					$c = $j;
				}
			}
		}
		$c = is_array( $c ) ? $c : array( 'on' => false );
		set_transient( 'flxai_cloud_ai', $c, 5 * MINUTE_IN_SECONDS );
	}
	return $c;
}

/** Flux Cloud can serve requests now (connected, turned on there, not off here). */
function flxai_cloud_ready() {
	if ( 'off' === flxai_cloud_mode() ) {
		return false;
	}
	$c = flxai_cloud_status();
	return is_array( $c ) && ! empty( $c['on'] ) && ! empty( $c['base'] );
}

/** Requests for this provider (anthropic|openrouter) go through Flux Cloud. */
function flxai_cloud_for( $provider ) {
	if ( ! flxai_cloud_ready() ) {
		return false;
	}
	return 'always' === flxai_cloud_mode() || '' === flxai_provider_key( $provider );
}

/** Anthropic model id → OpenRouter id (claude-haiku-4-5 → anthropic/claude-haiku-4.5). */
function flxai_cloud_model_id( $provider, $id ) {
	$id = (string) $id;
	if ( 'anthropic' === $provider && false === strpos( $id, '/' ) ) {
		$id = preg_replace( '/-(\d+)-(\d+)(-\d{8})?$/', '-$1.$2', $id );
		$id = preg_replace( '/-\d{8}$/', '', (string) $id );
		$id = 'anthropic/' . $id;
	}
	return (string) apply_filters( 'flxai_cloud_model_id', $id, $provider );
}

/** Keeps the numbers an answer brings (flx_cloud: credits, left, used, monthly). */
function flxai_cloud_note( $nums ) {
	if ( ! is_array( $nums ) ) {
		return;
	}
	$c = get_transient( 'flxai_cloud_ai' );
	if ( is_array( $c ) ) {
		foreach ( array( 'left', 'used', 'monthly' ) as $k ) {
			if ( isset( $nums[ $k ] ) ) {
				$c[ $k ] = (int) $nums[ $k ];
			}
		}
		$c['site_used'] = (int) ( $c['site_used'] ?? 0 ) + (int) ( $nums['credits'] ?? 0 );
		set_transient( 'flxai_cloud_ai', $c, 5 * MINUTE_IN_SECONDS );
	}
}

/**
 * Connection for an OpenRouter-format request: own key, or Flux Cloud (base + access token).
 *
 * @return array{base:string,key:string,cloud:bool}|WP_Error
 */
function flxai_openrouter_conn( $provider = 'openrouter', $force = false ) {
	if ( ! flxai_cloud_for( $provider ) ) {
		return array(
			'base'  => flxai_openrouter_base(),
			'key'   => (string) flxai_setting( 'key_openrouter' ),
			'cloud' => false,
		);
	}
	$tok = FLXK_Cloud::access_token( $force );
	if ( is_wp_error( $tok ) ) {
		/* translators: %s: error */
		return new WP_Error( 'flxai_cloud', sprintf( __( 'Flux Cloud session could not be renewed: %s', 'flx-ai' ), $tok->get_error_message() ) );
	}
	return array(
		'base'  => untrailingslashit( (string) flxai_cloud_status()['base'] ),
		'key'   => (string) $tok,
		'cloud' => true,
	);
}
