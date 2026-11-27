<?php
/**
 * Relay: another plugin on this site (Flux Cloud's AI credits proxy on fluxwp.com) sends OpenRouter-format chat
 * requests through Flux AI with the keys entered in Flux AI › Settings. The caller never holds a key; Flux AI
 * is the only place that talks to the AI provider (project rule: AI code only through flx-ai).
 *
 * Route: Claude models (anthropic/… or claude-…) go to Anthropic when the Anthropic key is set (Anthropic's
 * OpenAI-compatible /chat/completions, cost from the Flux AI price table); everything else goes to OpenRouter.
 * With only an Anthropic key every request goes to Anthropic with the Claude model asked for, else Flux AI's
 * default Claude model.
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

/** Own Anthropic key of this site (Flux AI › Settings or FLXAI_KEY_ANTHROPIC). */
function flxai_relay_anthropic_key() {
	return (string) flxai_setting( 'key_anthropic' );
}

/** Model is a Claude model (anthropic/claude-haiku-4.5, claude:claude-haiku-4-5, claude-haiku-4-5). */
function flxai_relay_is_claude( $model ) {
	$m = strtolower( (string) $model );
	return 0 === strpos( $m, 'anthropic/' ) || 0 === strpos( $m, 'claude:' ) || 0 === strpos( $m, 'claude-' );
}

/** Anthropic model id of a Claude model (anthropic/claude-haiku-4.5 → claude-haiku-4-5). */
function flxai_relay_anthropic_id( $model ) {
	$m = (string) $model;
	if ( 0 === strpos( $m, 'anthropic/' ) ) {
		$m = str_replace( '.', '-', substr( $m, 10 ) );
	} elseif ( 0 === strpos( $m, 'claude:' ) ) {
		$m = substr( $m, 7 );
	}
	return (string) apply_filters( 'flxai_relay_anthropic_id', $m, $model );
}

/** Flux AI's default model as an OpenRouter id ('' when none is set). */
function flxai_relay_default_model() {
	$model = (string) flxai_setting( 'model', '' );
	if ( '' === $model ) {
		return '';
	}
	$p = flxai_parse_model( $model );
	return flxai_cloud_model_id( $p['provider'], $p['id'] );
}

/**
 * Where a request for $model goes.
 *
 * @return array{provider:string,base:string,key:string,model:string}|WP_Error
 */
function flxai_relay_route( $model, $prefer = 'auto' ) {
	$or     = flxai_relay_key();
	$an     = flxai_relay_anthropic_key();
	$prefer = in_array( $prefer, array( 'openrouter', 'anthropic' ), true ) ? $prefer : 'auto';
	if ( 'openrouter' === $prefer && '' === $or ) {
		return new WP_Error( 'flxai_relay_key', __( 'No OpenRouter key in Flux AI settings.', 'flx-ai' ), array( 'status' => 503 ) );
	}
	if ( 'anthropic' === $prefer && '' === $an ) {
		return new WP_Error( 'flxai_relay_key', __( 'No Anthropic key in Flux AI settings.', 'flx-ai' ), array( 'status' => 503 ) );
	}
	$use_an = 'anthropic' === $prefer || ( 'auto' === $prefer && '' !== $an && ( flxai_relay_is_claude( $model ) || '' === $or ) );
	if ( $use_an ) {
		if ( ! flxai_relay_is_claude( $model ) ) {
			$def   = flxai_relay_default_model();
			$model = flxai_relay_is_claude( $def ) ? $def : 'anthropic/claude-haiku-4.5';
		}
		return array(
			'provider' => 'anthropic',
			'base'     => flxai_anthropic_base(),
			'key'      => $an,
			'model'    => flxai_relay_anthropic_id( $model ),
		);
	}
	if ( '' !== $or ) {
		$model = (string) $model;
		if ( 0 === strpos( $model, 'claude:' ) ) {
			$model = flxai_cloud_model_id( 'anthropic', substr( $model, 7 ) );
		}
		return array(
			'provider' => 'openrouter',
			'base'     => flxai_openrouter_base(),
			'key'      => $or,
			'model'    => $model,
		);
	}
	return new WP_Error( 'flxai_relay_key', __( 'No OpenRouter or Anthropic key in Flux AI settings.', 'flx-ai' ), array( 'status' => 503 ) );
}

/**
 * Whether this site can relay requests and with which default model.
 *
 * @return array{ready:bool,provider:string,providers:array,default:string,key_end:string,settings_url:string,message:string}
 */
function flx_ai_relay_info() {
	$or    = flxai_relay_key();
	$an    = flxai_relay_anthropic_key();
	$prov  = array();
	$names = array();
	if ( '' !== $or ) {
		$prov['openrouter'] = substr( $or, -4 );
		$names[]            = 'OpenRouter …' . substr( $or, -4 );
	}
	if ( '' !== $an ) {
		$prov['anthropic'] = substr( $an, -4 );
		$names[]           = 'Anthropic (Claude) …' . substr( $an, -4 );
	}
	$default = flxai_relay_default_model();
	if ( '' === $or && '' !== $an && ! flxai_relay_is_claude( $default ) ) {
		$default = 'anthropic/claude-haiku-4.5';
	}
	if ( '' === $default ) {
		$default = '' !== $or ? 'google/gemini-2.5-flash' : 'anthropic/claude-haiku-4.5';
	}
	$ready = (bool) $prov;
	if ( ! $ready ) {
		$msg = __( 'No OpenRouter or Anthropic key in Flux AI settings. Enter one there; Flux AI sends the requests.', 'flx-ai' );
	} elseif ( '' === $or ) {
		$msg = __( 'Only the Anthropic key is set: every request uses a Claude model. Add an OpenRouter key in Flux AI for other models.', 'flx-ai' );
	} elseif ( '' === $an ) {
		$msg = __( 'Requests use the OpenRouter key in Flux AI settings.', 'flx-ai' );
	} else {
		$msg = __( 'Claude models use the Anthropic key, other models the OpenRouter key in Flux AI settings.', 'flx-ai' );
	}
	return array(
		'ready'        => $ready,
		'provider'     => '' !== $or ? 'openrouter' : ( '' !== $an ? 'anthropic' : '' ),
		'providers'    => $prov,
		'keys'         => implode( ', ', $names ),
		'default'      => $default,
		'key_end'      => (string) ( $prov['openrouter'] ?? $prov['anthropic'] ?? '' ),
		'settings_url' => admin_url( 'admin.php?page=flx-ai-settings' ),
		'message'      => $msg,
	);
}

/** Body as Anthropic's OpenAI-compatible endpoint takes it (OpenRouter-only fields left out). */
function flxai_relay_anthropic_body( array $body, $model ) {
	unset( $body['usage'], $body['plugins'], $body['reasoning'], $body['seed'], $body['provider'], $body['transforms'] );
	$body['model'] = $model;
	if ( ! empty( $body['stream'] ) ) {
		$body['stream_options'] = array( 'include_usage' => true );
	}
	if ( flxai_anthropic_no_sampling( $model ) ) {
		unset( $body['temperature'], $body['top_p'] );
	}
	return $body;
}

/** USD cost of a Claude answer from its usage (Flux AI price table). */
function flxai_relay_anthropic_cost( $model, array $usage ) {
	$p      = flxai_anthropic_price( $model );
	$in     = (int) ( $usage['prompt_tokens'] ?? 0 );
	$cached = (int) ( $usage['prompt_tokens_details']['cached_tokens'] ?? 0 );
	$out    = (int) ( $usage['completion_tokens'] ?? 0 );
	return round( ( max( 0, $in - $cached ) * $p[0] + $cached * $p[0] * $p[2] + $out * $p[1] ) / 1000000, 6 );
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
	$route = flxai_relay_route( (string) ( $body['model'] ?? '' ), (string) ( $opts['provider'] ?? 'auto' ) );
	if ( is_wp_error( $route ) ) {
		return $route;
	}
	$headers = array(
		'Authorization' => 'Bearer ' . $route['key'],
		'Content-Type'  => 'application/json',
	);
	if ( 'anthropic' === $route['provider'] ) {
		$body                         = flxai_relay_anthropic_body( $body, $route['model'] );
		$headers['x-api-key']         = $route['key'];
		$headers['anthropic-version'] = '2023-06-01';
	} else {
		$body['usage']           = array( 'include' => true );
		$headers['HTTP-Referer'] = (string) ( $opts['referer'] ?? home_url( '/' ) );
		$headers['X-Title']      = (string) ( $opts['title'] ?? get_bloginfo( 'name' ) . ' · Flux AI' );
	}
	$url    = $route['base'] . '/chat/completions';
	$stream = ! empty( $body['stream'] ) && isset( $opts['on_sse'] ) && is_callable( $opts['on_sse'] ) && function_exists( 'curl_init' );
	if ( $stream ) {
		return flxai_relay_finish( flxai_relay_stream( $url, $headers, $body, $opts ), $route );
	}
	$body['stream'] = false;
	unset( $body['stream_options'] );
	$r = wp_remote_post(
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
	return flxai_relay_finish(
		array(
			'code'     => (int) wp_remote_retrieve_response_code( $r ),
			'json'     => $json,
			'raw'      => $raw,
			'usage'    => (array) ( $json['usage'] ?? array() ),
			'model'    => (string) ( $json['model'] ?? ( $body['model'] ?? '' ) ),
			'id'       => (string) ( $json['id'] ?? '' ),
			'cost'     => isset( $json['usage']['cost'] ) ? (float) $json['usage']['cost'] : null,
			'streamed' => false,
		),
		$route
	);
}

/** Fills cost (and the OpenRouter-style model id for Claude) once the answer is in. */
function flxai_relay_finish( $r, array $route ) {
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$ok = $r['code'] >= 200 && $r['code'] < 300 && ! isset( $r['json']['error'] );
	$r['provider'] = $route['provider'];
	if ( 'anthropic' === $route['provider'] ) {
		$id         = '' !== (string) $r['model'] ? (string) $r['model'] : $route['model'];
		$r['model'] = flxai_cloud_model_id( 'anthropic', $id );
		if ( $ok && null === $r['cost'] ) {
			$r['cost'] = flxai_relay_anthropic_cost( $id, (array) $r['usage'] );
		}
	} elseif ( $ok && null === $r['cost'] ) {
		$r['cost'] = flxai_relay_generation_cost( (string) $r['id'] );
	}
	return $r;
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
		'cost'     => isset( $st['usage']['cost'] ) ? (float) $st['usage']['cost'] : null,
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
 * Checks the keys in Flux AI: OpenRouter spent / limit (GET /key), Anthropic key accepted (GET /models).
 *
 * @return array{usage:float|null,limit:float|null,message:string}|WP_Error
 */
function flx_ai_relay_key_check() {
	$or = flxai_relay_key();
	$an = flxai_relay_anthropic_key();
	if ( '' === $or && '' === $an ) {
		return new WP_Error( 'flxai_relay_key', __( 'No OpenRouter or Anthropic key in Flux AI settings.', 'flx-ai' ) );
	}
	$msgs  = array();
	$used  = null;
	$limit = null;
	if ( '' !== $or ) {
		$r = wp_remote_get(
			flxai_openrouter_base() . '/key',
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $or ),
			)
		);
		$j = is_wp_error( $r ) ? null : json_decode( (string) wp_remote_retrieve_body( $r ), true );
		if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) || ! isset( $j['data'] ) ) {
			return new WP_Error( 'flxai_relay_key', __( 'OpenRouter did not accept the key in Flux AI settings.', 'flx-ai' ) );
		}
		$used   = (float) ( $j['data']['usage'] ?? 0 );
		$limit  = isset( $j['data']['limit'] ) ? (float) $j['data']['limit'] : null;
		$msgs[] = null === $limit
			/* translators: %s: USD spent */
			? sprintf( __( 'The OpenRouter key in Flux AI works. Spent so far: $%s, no spending limit on the key (set one on OpenRouter).', 'flx-ai' ), number_format_i18n( $used, 2 ) )
			/* translators: 1: USD spent, 2: USD limit */
			: sprintf( __( 'The OpenRouter key in Flux AI works. Spent so far: $%1$s of the $%2$s limit.', 'flx-ai' ), number_format_i18n( $used, 2 ), number_format_i18n( (float) $limit, 2 ) );
	}
	if ( '' !== $an ) {
		$r = wp_remote_get(
			flxai_anthropic_base() . '/models?limit=1',
			array(
				'timeout' => 15,
				'headers' => array(
					'x-api-key'         => $an,
					'anthropic-version' => '2023-06-01',
				),
			)
		);
		if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
			return new WP_Error( 'flxai_relay_key', __( 'Anthropic did not accept the key in Flux AI settings.', 'flx-ai' ) );
		}
		$msgs[] = __( 'The Anthropic key in Flux AI works.', 'flx-ai' );
	}
	return array(
		'usage'   => $used,
		'limit'   => $limit,
		'message' => implode( ' ', $msgs ),
	);
}
