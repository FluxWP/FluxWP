<?php
/**
 * Relay: another plugin on this site (Flux Cloud's AI credits proxy on fluxwp.com) sends OpenRouter-format chat
 * requests through Flux AI with the keys entered in Flux AI › Settings. The caller never holds a key; Flux AI
 * is the only place that talks to the AI provider (project rule: AI code only through flx-ai).
 *
 *   flx_ai_relay_info()            → { ready, provider, default, key_end, settings_url, message }
 *   flx_ai_relay( $body, $opts )   → { code, json, usage, model, id, cost, streamed } | WP_Error
 *   flx_ai_relay_key_check()       → { usage, limit, message } | WP_Error
 *
 * Only the site's own OpenRouter key is used, never Flux Cloud credits (no loop). Contract: docs/flx-ai/API.md.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** Own OpenRouter key of this site (Flux AI › Settings or FLXAI_KEY_OPENROUTER). */
function flxai_relay_key() {
	return (string) flxai_setting( 'key_openrouter' );
}

/**
 * Whether this site can relay requests and with which default model.
 *
 * @return array{ready:bool,provider:string,default:string,key_end:string,settings_url:string,message:string}
 */
function flx_ai_relay_info() {
	$key   = flxai_relay_key();
	$model = (string) flxai_setting( 'model', '' );
	if ( '' !== $model ) {
		$p     = flxai_parse_model( $model );
		$model = flxai_cloud_model_id( $p['provider'], $p['id'] );
	}
	$ready = '' !== $key;
	return array(
		'ready'        => $ready,
		'provider'     => 'openrouter',
		'default'      => '' !== $model ? $model : 'google/gemini-2.5-flash',
		'key_end'      => $ready ? substr( $key, -4 ) : '',
		'settings_url' => admin_url( 'admin.php?page=flx-ai-settings' ),
		'message'      => $ready
			? __( 'Requests use the OpenRouter key in Flux AI settings.', 'flx-ai' )
			: __( 'No OpenRouter key in Flux AI settings. Enter it there; Flux AI sends the requests.', 'flx-ai' ),
	);
}

/**
 * Sends one OpenRouter-format chat request with this site's key.
 *
 * $opts:
 *   referer (string)  HTTP-Referer sent to OpenRouter (the calling site).
 *   title   (string)  X-Title.
 *   timeout (int)     Seconds (default 120, streams 280).
 *   on_start (callable(int $code))  Streaming: called once when the answer is an event stream, before on_sse.
 *   on_sse   (callable(string $lines))  Streaming: raw SSE lines as they come, "[DONE]" left out.
 *
 * With $body['stream'] and on_sse the answer is streamed (cURL); otherwise one JSON answer. usage/id/model are
 * read from the answer; cost is usage.cost or, when the stream ended without it, asked from OpenRouter once.
 *
 * @return array{code:int,json:array|null,raw:string,usage:array,model:string,id:string,cost:float|null,streamed:bool}|WP_Error
 */
function flx_ai_relay( array $body, array $opts = array() ) {
	$key = flxai_relay_key();
	if ( '' === $key ) {
		return new WP_Error( 'flxai_relay_key', __( 'No OpenRouter key in Flux AI settings.', 'flx-ai' ), array( 'status' => 503 ) );
	}
	$body['usage'] = array( 'include' => true );
	$headers       = array(
		'Authorization' => 'Bearer ' . $key,
		'Content-Type'  => 'application/json',
		'HTTP-Referer'  => (string) ( $opts['referer'] ?? home_url( '/' ) ),
		'X-Title'       => (string) ( $opts['title'] ?? get_bloginfo( 'name' ) . ' · Flux AI' ),
	);
	$url    = flxai_openrouter_base() . '/chat/completions';
	$stream = ! empty( $body['stream'] ) && isset( $opts['on_sse'] ) && is_callable( $opts['on_sse'] ) && function_exists( 'curl_init' );
	if ( $stream ) {
		return flxai_relay_stream( $url, $headers, $body, $opts );
	}
	$body['stream'] = false;
	$r              = wp_remote_post(
		$url,
		array(
			'timeout' => (int) ( $opts['timeout'] ?? 120 ),
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
		)
	);
	if ( is_wp_error( $r ) ) {
		return new WP_Error( 'flxai_relay_http', $r->get_error_message(), array( 'status' => 502 ) );
	}
	$raw  = (string) wp_remote_retrieve_body( $r );
	$json = json_decode( $raw, true );
	$json = is_array( $json ) ? $json : null;
	$u    = (array) ( $json['usage'] ?? array() );
	$id   = (string) ( $json['id'] ?? '' );
	$ok   = $json && ! isset( $json['error'] );
	return array(
		'code'     => (int) wp_remote_retrieve_response_code( $r ),
		'json'     => $json,
		'raw'      => $raw,
		'usage'    => $u,
		'model'    => (string) ( $json['model'] ?? ( $body['model'] ?? '' ) ),
		'id'       => $id,
		'cost'     => $ok ? ( isset( $u['cost'] ) ? (float) $u['cost'] : flxai_relay_generation_cost( $id ) ) : null,
		'streamed' => false,
	);
}

/** Streaming half of flx_ai_relay(): raw SSE lines go to on_sse as they come. */
function flxai_relay_stream( $url, array $headers, array $body, array $opts ) {
	$h = array( 'Accept: text/event-stream' );
	foreach ( $headers as $k => $v ) {
		$h[] = $k . ': ' . $v;
	}
	$on_sse   = $opts['on_sse'];
	$on_start = isset( $opts['on_start'] ) && is_callable( $opts['on_start'] ) ? $opts['on_start'] : null;
	$st       = array(
		'sse'   => null,
		'raw'   => '',
		'buf'   => '',
		'usage' => array(),
		'id'    => '',
		'model' => (string) ( $body['model'] ?? '' ),
	);
	$body['stream'] = true;
	$ch             = curl_init( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$ca             = ABSPATH . WPINC . '/certificates/ca-bundle.crt';
	curl_setopt_array( // phpcs:ignore WordPress.WP.AlternativeFunctions
		$ch,
		array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => wp_json_encode( $body ),
			CURLOPT_HTTPHEADER     => $h,
			CURLOPT_TIMEOUT        => (int) ( $opts['timeout'] ?? 280 ),
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_CAINFO         => file_exists( $ca ) ? $ca : null,
			CURLOPT_USERAGENT      => 'WordPress/' . get_bloginfo( 'version' ) . '; Flux AI relay',
			CURLOPT_WRITEFUNCTION  => static function ( $c, $chunk ) use ( &$st, $on_sse, $on_start ) {
				if ( null === $st['sse'] ) {
					$type      = (string) curl_getinfo( $c, CURLINFO_CONTENT_TYPE ); // phpcs:ignore WordPress.WP.AlternativeFunctions
					$st['sse'] = false !== stripos( $type, 'event-stream' ) && 200 === (int) curl_getinfo( $c, CURLINFO_RESPONSE_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions
					if ( $st['sse'] && $on_start ) {
						$on_start( 200 );
					}
				}
				if ( ! $st['sse'] ) {
					$st['raw'] .= $chunk;
					return strlen( $chunk );
				}
				$st['buf'] .= str_replace( "\r\n", "\n", $chunk );
				$out        = '';
				while ( false !== ( $p = strpos( $st['buf'], "\n" ) ) ) {
					$line      = substr( $st['buf'], 0, $p );
					$st['buf'] = (string) substr( $st['buf'], $p + 1 );
					if ( 0 === strpos( $line, 'data:' ) ) {
						$data = trim( substr( $line, 5 ) );
						if ( '[DONE]' === $data ) {
							continue; // The caller ends the stream (it may add its own event first).
						}
						$j = json_decode( $data, true );
						if ( is_array( $j ) ) {
							if ( ! empty( $j['id'] ) ) {
								$st['id'] = (string) $j['id'];
							}
							if ( ! empty( $j['model'] ) ) {
								$st['model'] = (string) $j['model'];
							}
							if ( is_array( $j['usage'] ?? null ) ) {
								$st['usage'] = $j['usage'];
							}
						}
					}
					$out .= $line . "\n";
				}
				if ( '' !== $out ) {
					$on_sse( $out );
				}
				return strlen( $chunk );
			},
		)
	);
	curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$err  = curl_errno( $ch ) ? curl_error( $ch ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
	curl_close( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $st['sse'] ) {
		if ( ! $code && '' !== $err ) {
			return new WP_Error( 'flxai_relay_http', $err, array( 'status' => 502 ) );
		}
		$json = json_decode( $st['raw'], true );
		return array(
			'code'     => $code ? $code : 502,
			'json'     => is_array( $json ) ? $json : null,
			'raw'      => $st['raw'],
			'usage'    => array(),
			'model'    => $st['model'],
			'id'       => '',
			'cost'     => null,
			'streamed' => false,
		);
	}
	return array(
		'code'     => 200,
		'json'     => null,
		'raw'      => '',
		'usage'    => $st['usage'],
		'model'    => $st['model'],
		'id'       => $st['id'],
		'cost'     => isset( $st['usage']['cost'] ) ? (float) $st['usage']['cost'] : flxai_relay_generation_cost( $st['id'] ),
		'streamed' => true,
	);
}

/** Cost of a finished generation, asked from OpenRouter once (null when unknown). */
function flxai_relay_generation_cost( $id ) {
	if ( '' === (string) $id || '' === flxai_relay_key() ) {
		return null;
	}
	$r = wp_remote_get(
		flxai_openrouter_base() . '/generation?id=' . rawurlencode( (string) $id ),
		array(
			'timeout' => 8,
			'headers' => array( 'Authorization' => 'Bearer ' . flxai_relay_key() ),
		)
	);
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return null;
	}
	$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	return isset( $j['data']['total_cost'] ) ? (float) $j['data']['total_cost'] : null;
}

/**
 * Spent and limit of the OpenRouter key (GET /key).
 *
 * @return array{usage:float,limit:float|null,message:string}|WP_Error
 */
function flx_ai_relay_key_check() {
	$key = flxai_relay_key();
	if ( '' === $key ) {
		return new WP_Error( 'flxai_relay_key', __( 'No OpenRouter key in Flux AI settings.', 'flx-ai' ) );
	}
	$r = wp_remote_get(
		flxai_openrouter_base() . '/key',
		array(
			'timeout' => 15,
			'headers' => array( 'Authorization' => 'Bearer ' . $key ),
		)
	);
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	if ( 200 !== (int) wp_remote_retrieve_response_code( $r ) || ! isset( $j['data'] ) ) {
		return new WP_Error( 'flxai_relay_key', __( 'OpenRouter did not accept the key in Flux AI settings.', 'flx-ai' ) );
	}
	$used  = (float) ( $j['data']['usage'] ?? 0 );
	$limit = isset( $j['data']['limit'] ) ? (float) $j['data']['limit'] : null;
	return array(
		'usage'   => $used,
		'limit'   => $limit,
		'message' => null === $limit
			/* translators: %s: USD spent */
			? sprintf( __( 'The OpenRouter key in Flux AI works. Spent so far: $%s, no spending limit on the key (set one on OpenRouter).', 'flx-ai' ), number_format_i18n( $used, 2 ) )
			/* translators: 1: USD spent, 2: USD limit */
			: sprintf( __( 'The OpenRouter key in Flux AI works. Spent so far: $%1$s of the $%2$s limit.', 'flx-ai' ), number_format_i18n( $used, 2 ), number_format_i18n( (float) $limit, 2 ) ),
	);
}
