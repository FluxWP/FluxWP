<?php
/**
 * Sağlayıcı istekleri. Giriş ve çıkış OpenAI biçiminde (system/user/assistant/tool, tool_calls);
 * Anthropic için Messages API'ye çevrilir.
 *
 * Dönen asistan mesajındaki "_" ile başlayan alanlar Flux AI'ye aittir:
 *  _thinking (düşünme özeti), _raw/_raw_model (Anthropic ham blokları, sonraki turda aynen geri gider),
 *  _finish (stop|tool_calls|length|refusal), _usage.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** OpenRouter'a gitmeden önce Flux AI alanları silinir. */
function flxai_strip_private( array $messages ) {
	$out = array();
	foreach ( $messages as $m ) {
		if ( ! is_array( $m ) ) {
			continue;
		}
		foreach ( array_keys( $m ) as $k ) {
			if ( '_' === substr( (string) $k, 0, 1 ) ) {
				unset( $m[ $k ] );
			}
		}
		if ( ! empty( $m['tool_calls'] ) ) {
			foreach ( $m['tool_calls'] as $j => $c ) {
				foreach ( array_keys( (array) $c ) as $k ) {
					if ( '_' === substr( (string) $k, 0, 1 ) ) {
						unset( $m['tool_calls'][ $j ][ $k ] );
					}
				}
			}
		}
		if ( is_array( $m['content'] ?? null ) ) {
			foreach ( $m['content'] as $j => $p ) {
				if ( is_array( $p ) ) {
					unset( $m['content'][ $j ]['cache'] );
				}
			}
		}
		$out[] = $m;
	}
	return $out;
}

/** HTTP hata metninden anahtarı temizler, WP_Error üretir. */
function flxai_api_error( $provider, $code, $msg, $key ) {
	$msg = str_replace( (string) $key, '***', (string) $msg );
	if ( 'anthropic' === $provider && false !== stripos( $msg, 'anthropic-workspace-id' ) ) {
		$msg .= ' ' . __( '→ Flux AI & MCP › Settings › "Anthropic workspace ID": paste the id (wrkspc_…) from console.anthropic.com › Settings › Workspaces.', 'flx-ai' );
	}
	return new WP_Error(
		'flxai_api',
		/* translators: 1: HTTP code, 2: message */
		sprintf( __( 'AI error (%1$d): %2$s', 'flx-ai' ), $code, '' !== $msg ? $msg : __( 'unknown error', 'flx-ai' ) ),
		array( 'http' => (int) $code, 'msg' => $msg, 'provider' => $provider )
	);
}

/* ---------------------------------------------------------------------------
 * Akış (SSE): $opts['stream'] verilirse cevap parça parça gelir.
 * $opts['stream']( array( 't' => 'text'|'think'|'tool', 'd' => …, 'id' => …, 'name' => … ) ) her parçada çağrılır.
 * $opts['stop_check']() true dönerse istek kesilir (WP_Error flxai_stopped, data.partial = gelen metin).
 * cURL yoksa ya da akış başarısızsa normal (tek parça) isteğe düşülür.
 * ------------------------------------------------------------------------- */

function flxai_can_stream( array $opts ) {
	return ! empty( $opts['stream'] ) && is_callable( $opts['stream'] ) && function_exists( 'curl_init' ) && ! defined( 'WP_PROXY_HOST' );
}

/**
 * SSE isteği. Her "data:" olayı JSON olarak $on_event'e gider.
 *
 * @return array|WP_Error array( code, raw ) — raw: SSE değilse (hata cevabı) gövde.
 */
function flxai_http_stream( $url, array $headers, $body, $timeout, callable $on_event, $stop_check = null ) {
	$h = array();
	foreach ( $headers as $k => $v ) {
		$h[] = $k . ': ' . $v;
	}
	$h[]     = 'Accept: text/event-stream';
	$buf     = '';
	$raw     = '';
	$sse     = null;
	$stopped = false;
	$last    = microtime( true );
	$ch      = curl_init( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$ca      = ABSPATH . WPINC . '/certificates/ca-bundle.crt';
	curl_setopt_array( // phpcs:ignore WordPress.WP.AlternativeFunctions
		$ch,
		array(
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_HTTPHEADER     => $h,
			CURLOPT_TIMEOUT        => (int) $timeout,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_CAINFO         => file_exists( $ca ) ? $ca : null,
			CURLOPT_USERAGENT      => 'WordPress/' . get_bloginfo( 'version' ) . '; Flux AI',
			CURLOPT_WRITEFUNCTION  => function ( $c, $chunk ) use ( &$buf, &$raw, &$sse, &$stopped, &$last, $on_event, $stop_check ) {
				if ( null === $sse ) {
					$type = (string) curl_getinfo( $c, CURLINFO_CONTENT_TYPE ); // phpcs:ignore WordPress.WP.AlternativeFunctions
					$sse  = false !== stripos( $type, 'event-stream' ) && 200 === (int) curl_getinfo( $c, CURLINFO_RESPONSE_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}
				if ( ! $sse ) {
					$raw .= $chunk;
					return strlen( $chunk );
				}
				$buf .= str_replace( "\r\n", "\n", $chunk );
				while ( false !== ( $p = strpos( $buf, "\n\n" ) ) ) {
					$ev  = substr( $buf, 0, $p );
					$buf = (string) substr( $buf, $p + 2 );
					$data = '';
					foreach ( explode( "\n", $ev ) as $line ) {
						if ( 0 === strpos( $line, 'data:' ) ) {
							$data .= ltrim( substr( $line, 5 ) );
						}
					}
					if ( '' === $data || '[DONE]' === $data ) {
						continue;
					}
					$j = json_decode( $data, true );
					if ( is_array( $j ) ) {
						$on_event( $j );
					}
				}
				if ( $stop_check && microtime( true ) - $last > 1.5 ) {
					$last = microtime( true );
					if ( $stop_check() ) {
						$stopped = true;
						return 0;
					}
				}
				return strlen( $chunk );
			},
		)
	);
	curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$err  = curl_errno( $ch ) ? curl_error( $ch ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
	curl_close( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( $stopped ) {
		return new WP_Error( 'flxai_stopped', __( 'Stopped by the user.', 'flx-ai' ) );
	}
	if ( '' !== $err && ( ! $code || $sse ) ) {
		return new WP_Error( 'flxai_http', $err );
	}
	return array( 'code' => $code, 'raw' => $raw, 'sse' => (bool) $sse );
}

/* ---------------------------------------------------------------------------
 * OpenRouter
 * ------------------------------------------------------------------------- */

function flxai_openrouter_request( array $messages, array $opts, $model ) {
	$key = flxai_setting( 'key_openrouter' );
	if ( '' === $key ) {
		return new WP_Error( 'flxai_key', __( 'No OpenRouter API key entered (Flux AI & MCP › Settings).', 'flx-ai' ) );
	}
	$body = array(
		'model'      => $model,
		'messages'   => flxai_strip_private( $messages ),
		'max_tokens' => (int) ( $opts['max_tokens'] ?? 1024 ),
		'usage'      => array( 'include' => true ),
	);
	if ( isset( $opts['temperature'] ) && '' !== $opts['temperature'] ) {
		$body['temperature'] = (float) $opts['temperature'];
	}
	if ( ! empty( $opts['plugins'] ) ) {
		$body['plugins'] = $opts['plugins'];
	}
	if ( ! empty( $opts['response_format'] ) ) {
		$body['response_format'] = $opts['response_format'];
	}
	if ( ! empty( $opts['tools'] ) ) {
		$body['tools']       = array_values( $opts['tools'] );
		$body['tool_choice'] = 'auto';
	}
	if ( ! empty( $opts['thinking'] ) ) {
		$body['reasoning'] = array( 'effort' => (string) ( $opts['effort'] ?? 'low' ) );
	}
	$url     = flxai_openrouter_base() . '/chat/completions';
	$headers = array(
		'Authorization' => 'Bearer ' . $key,
		'Content-Type'  => 'application/json',
		'HTTP-Referer'  => home_url( '/' ),
		'X-Title'       => get_bloginfo( 'name' ) . ' · Flux AI',
	);
	$timeout = (int) ( $opts['timeout'] ?? 45 );
	if ( flxai_can_stream( $opts ) ) {
		$body['stream'] = true;
		$emit = $opts['stream'];
		$acc  = array( 'content' => '', 'reasoning' => '', 'calls' => array(), 'finish' => '', 'usage' => null, 'model' => '', 'error' => null );
		$st   = flxai_http_stream(
			$url,
			$headers,
			wp_json_encode( $body ),
			$timeout,
			function ( $j ) use ( &$acc, $emit ) {
				if ( isset( $j['error'] ) ) {
					$acc['error'] = $j['error'];
					return;
				}
				if ( ! empty( $j['model'] ) ) {
					$acc['model'] = (string) $j['model'];
				}
				if ( is_array( $j['usage'] ?? null ) ) {
					$acc['usage'] = $j['usage'];
				}
				$ch = $j['choices'][0] ?? null;
				if ( ! is_array( $ch ) ) {
					return;
				}
				if ( ! empty( $ch['finish_reason'] ) ) {
					$acc['finish'] = (string) $ch['finish_reason'];
				}
				$d = (array) ( $ch['delta'] ?? array() );
				if ( isset( $d['reasoning'] ) && '' !== (string) $d['reasoning'] ) {
					$acc['reasoning'] .= (string) $d['reasoning'];
					$emit( array( 't' => 'think', 'd' => (string) $d['reasoning'] ) );
				}
				if ( isset( $d['content'] ) && '' !== (string) $d['content'] ) {
					$acc['content'] .= (string) $d['content'];
					$emit( array( 't' => 'text', 'd' => (string) $d['content'] ) );
				}
				foreach ( (array) ( $d['tool_calls'] ?? array() ) as $tc ) {
					$i = (int) ( $tc['index'] ?? 0 );
					if ( ! isset( $acc['calls'][ $i ] ) ) {
						$acc['calls'][ $i ] = array( 'id' => '', 'type' => 'function', 'function' => array( 'name' => '', 'arguments' => '' ) );
					}
					if ( ! empty( $tc['id'] ) ) {
						$acc['calls'][ $i ]['id'] = (string) $tc['id'];
					}
					if ( ! empty( $tc['function']['name'] ) ) {
						$acc['calls'][ $i ]['function']['name'] .= (string) $tc['function']['name'];
						$emit( array( 't' => 'tool', 'id' => $acc['calls'][ $i ]['id'], 'name' => $acc['calls'][ $i ]['function']['name'] ) );
					}
					if ( isset( $tc['function']['arguments'] ) ) {
						$acc['calls'][ $i ]['function']['arguments'] .= (string) $tc['function']['arguments'];
					}
				}
			},
			$opts['stop_check'] ?? null
		);
		if ( is_wp_error( $st ) ) {
			if ( 'flxai_stopped' === $st->get_error_code() ) {
				$st->add_data( array( 'partial' => $acc['content'] ) );
				return $st;
			}
			/* translators: %s: error */
			return new WP_Error( 'flxai_http', sprintf( __( 'Could not reach the AI service: %s', 'flx-ai' ), $st->get_error_message() ), array( 'provider' => 'openrouter' ) );
		}
		$code = (int) $st['code'];
		if ( $st['sse'] ) {
			$m = array( 'role' => 'assistant', 'content' => $acc['content'] );
			if ( '' !== $acc['reasoning'] ) {
				$m['reasoning'] = $acc['reasoning'];
			}
			if ( $acc['calls'] ) {
				ksort( $acc['calls'] );
				$m['tool_calls'] = array_values( $acc['calls'] );
			}
			$json = array(
				'model'   => $acc['model'],
				'choices' => array( array( 'message' => $m, 'finish_reason' => $acc['finish'] ) ),
				'usage'   => $acc['usage'],
			);
			if ( $acc['error'] ) {
				$json['error'] = $acc['error'];
			}
		} else {
			$json = json_decode( (string) $st['raw'], true );
		}
	} else {
		$r = wp_remote_post(
			$url,
			array(
				'timeout' => $timeout,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $r ) ) {
			/* translators: %s: error */
			return new WP_Error( 'flxai_http', sprintf( __( 'Could not reach the AI service: %s', 'flx-ai' ), $r->get_error_message() ), array( 'provider' => 'openrouter' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		$json = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	}
	if ( $code < 200 || $code >= 300 || ! is_array( $json ) || isset( $json['error'] ) ) {
		$msg = is_array( $json ) ? (string) ( $json['error']['message'] ?? '' ) : '';
		return flxai_api_error( 'openrouter', $code ? $code : 500, $msg, $key );
	}
	$msg = $json['choices'][0]['message'] ?? null;
	if ( ! is_array( $msg ) ) {
		return new WP_Error( 'flxai_empty', __( 'The AI returned an empty answer.', 'flx-ai' ) );
	}
	$u   = is_array( $json['usage'] ?? null ) ? $json['usage'] : array();
	$in  = (int) ( $u['prompt_tokens'] ?? 0 );
	$out = (int) ( $u['completion_tokens'] ?? 0 );
	$cr  = (int) ( $u['prompt_tokens_details']['cached_tokens'] ?? 0 );
	$cw  = (int) ( $u['prompt_tokens_details']['cache_write_tokens'] ?? 0 );
	$est = ! isset( $u['cost'] );
	$out_msg = array(
		'role'    => 'assistant',
		'content' => (string) ( $msg['content'] ?? '' ),
	);
	if ( ! empty( $msg['tool_calls'] ) ) {
		$out_msg['tool_calls'] = $msg['tool_calls'];
	}
	$think = (string) ( $msg['reasoning'] ?? '' );
	if ( '' !== trim( $think ) ) {
		$out_msg['_thinking'] = $think;
	}
	$out_msg['_finish'] = (string) ( $json['choices'][0]['finish_reason'] ?? '' );
	$out_msg['_usage']  = array(
		'provider' => 'openrouter',
		'model'    => (string) ( $json['model'] ?? $model ),
		'in'       => $in,
		'out'      => $out,
		'cr'       => $cr,
		'cw'       => $cw,
		'cost'     => $est ? flxai_estimate_cost( $model, $in, $out, $cr ) : (float) $u['cost'],
		'full'     => flxai_estimate_cost( $model, $in, $out, 0 ),
		'est'      => $est,
	);
	// Katalog önbellekte yoksa önbelleksiz tutar bilinmez; tasarrufu sıfır göster.
	$out_msg['_usage']['full'] = max( $out_msg['_usage']['full'], $out_msg['_usage']['cost'] );
	return $out_msg;
}

/* ---------------------------------------------------------------------------
 * Anthropic (Claude)
 * ------------------------------------------------------------------------- */

/** OpenAI içerik parçası → Anthropic bloğu (null: atlanır). */
function flxai_anthropic_part( $p ) {
	if ( is_string( $p ) ) {
		return '' !== trim( $p ) ? array( 'type' => 'text', 'text' => $p ) : null;
	}
	$type = (string) ( $p['type'] ?? '' );
	if ( 'text' === $type ) {
		if ( '' === trim( (string) ( $p['text'] ?? '' ) ) ) {
			return null;
		}
		$b = array( 'type' => 'text', 'text' => (string) $p['text'] );
		if ( ! empty( $p['cache'] ) ) {
			$b['cache_control'] = array( 'type' => 'ephemeral' );
		}
		return $b;
	}
	if ( 'image_url' === $type ) {
		$url = (string) ( is_array( $p['image_url'] ?? null ) ? ( $p['image_url']['url'] ?? '' ) : ( $p['image_url'] ?? '' ) );
		if ( preg_match( '#^data:([^;]+);base64,(.*)$#s', $url, $m ) ) {
			return array( 'type' => 'image', 'source' => array( 'type' => 'base64', 'media_type' => $m[1], 'data' => $m[2] ) );
		}
		return '' !== $url ? array( 'type' => 'image', 'source' => array( 'type' => 'url', 'url' => $url ) ) : null;
	}
	if ( 'file' === $type ) {
		$data = (string) ( $p['file']['file_data'] ?? '' );
		if ( preg_match( '#^data:application/pdf;base64,(.*)$#s', $data, $m ) ) {
			return array( 'type' => 'document', 'source' => array( 'type' => 'base64', 'media_type' => 'application/pdf', 'data' => $m[1] ) );
		}
	}
	return null;
}

/**
 * OpenAI biçimli mesajlar → array( system blokları, messages ).
 * Sistem mesajındaki "cache" işaretli parça önbellek kırılma noktası olur.
 */
function flxai_anthropic_messages( array $messages, $model, $cache_on ) {
	$system = array();
	$out    = array();
	$push   = function ( $role, array $blocks ) use ( &$out ) {
		if ( ! $blocks ) {
			return;
		}
		$n = count( $out );
		if ( $n && $out[ $n - 1 ]['role'] === $role ) {
			$out[ $n - 1 ]['content'] = array_merge( $out[ $n - 1 ]['content'], $blocks );
			return;
		}
		$out[] = array( 'role' => $role, 'content' => $blocks );
	};
	// Önceki turların düşünme blokları gönderilmez (API zaten yok sayar); imzaları sistem metnine bağlı olduğundan
	// sistem değişince (ek talimat, tarih) hata verir. Yalnız son kullanıcı mesajından sonraki tur korunur.
	$last_user = -1;
	foreach ( array_values( $messages ) as $i => $m ) {
		if ( 'user' === ( $m['role'] ?? '' ) ) {
			$last_user = $i;
		}
	}
	foreach ( array_values( $messages ) as $idx => $m ) {
		$role    = (string) ( $m['role'] ?? 'user' );
		$content = $m['content'] ?? '';
		if ( 'system' === $role || 'developer' === $role ) {
			foreach ( is_array( $content ) ? $content : array( (string) $content ) as $p ) {
				$b = flxai_anthropic_part( $p );
				if ( $b ) {
					$system[] = $b;
				}
			}
			continue;
		}
		if ( 'tool' === $role ) {
			$res = array(
				'type'        => 'tool_result',
				'tool_use_id' => (string) ( $m['tool_call_id'] ?? '' ),
				'content'     => is_string( $content ) ? ( '' !== $content ? $content : '(boş)' ) : wp_json_encode( $content ),
			);
			if ( ! empty( $m['_error'] ) ) {
				$res['is_error'] = true;
			}
			$push( 'user', array( $res ) );
			continue;
		}
		if ( 'assistant' === $role ) {
			// Aynı modelin ham cevabı varsa aynen (düşünme blokları korunur).
			if ( ! empty( $m['_raw'] ) && is_array( $m['_raw'] ) && ( $m['_raw_model'] ?? '' ) === $model ) {
				$raw = array();
				foreach ( $m['_raw'] as $b ) {
					$bt = (string) ( $b['type'] ?? '' );
					if ( $idx < $last_user && ( 'thinking' === $bt || 'redacted_thinking' === $bt ) ) {
						continue;
					}
					if ( 'tool_use' === $bt && empty( $b['input'] ) ) {
						$b['input'] = new stdClass();
					}
					$raw[] = $b;
				}
				if ( $raw ) {
					$push( 'assistant', $raw );
					continue;
				}
			}
			$blocks = array();
			if ( is_string( $content ) && '' !== trim( $content ) ) {
				$blocks[] = array( 'type' => 'text', 'text' => $content );
			}
			foreach ( (array) ( $m['tool_calls'] ?? array() ) as $c ) {
				$args     = json_decode( (string) ( $c['function']['arguments'] ?? '' ), true );
				$blocks[] = array(
					'type'  => 'tool_use',
					'id'    => (string) ( $c['id'] ?? '' ),
					'name'  => (string) ( $c['function']['name'] ?? '' ),
					'input' => is_array( $args ) && $args ? $args : new stdClass(),
				);
			}
			$push( 'assistant', $blocks );
			continue;
		}
		$blocks = array();
		foreach ( is_array( $content ) ? $content : array( (string) $content ) as $p ) {
			$b = flxai_anthropic_part( $p );
			if ( $b ) {
				$blocks[] = $b;
			}
		}
		$push( 'user', $blocks );
	}
	if ( ! $out || 'user' !== $out[0]['role'] ) {
		array_unshift( $out, array( 'role' => 'user', 'content' => array( array( 'type' => 'text', 'text' => '…' ) ) ) );
	}
	if ( 'assistant' === $out[ count( $out ) - 1 ]['role'] ) {
		$out[] = array( 'role' => 'user', 'content' => array( array( 'type' => 'text', 'text' => 'Devam et.' ) ) );
	}
	if ( $cache_on && $system ) {
		$has = false;
		foreach ( $system as $b ) {
			$has = $has || isset( $b['cache_control'] );
		}
		if ( ! $has ) {
			$system[0]['cache_control'] = array( 'type' => 'ephemeral' );
		}
	}
	if ( ! $cache_on ) {
		foreach ( $system as $i => $b ) {
			unset( $system[ $i ]['cache_control'] );
		}
	}
	return array( $system, $out );
}

/** OpenAI araç tanımları → Anthropic. */
function flxai_anthropic_tools( array $tools ) {
	$out = array();
	foreach ( $tools as $t ) {
		$f = $t['function'] ?? $t;
		if ( empty( $f['name'] ) ) {
			continue;
		}
		$schema         = is_array( $f['parameters'] ?? null ) ? $f['parameters'] : ( is_array( $f['input_schema'] ?? null ) ? $f['input_schema'] : array() );
		$schema['type'] = 'object';
		if ( empty( $schema['properties'] ) ) {
			$schema['properties'] = new stdClass();
		}
		if ( isset( $schema['required'] ) && ! $schema['required'] ) {
			unset( $schema['required'] );
		}
		$out[] = array(
			'name'         => (string) $f['name'],
			'description'  => (string) ( $f['description'] ?? '' ),
			'input_schema' => $schema,
		);
	}
	return $out;
}

function flxai_anthropic_request( array $messages, array $opts, $model ) {
	$key = flxai_setting( 'key_anthropic' );
	if ( '' === $key ) {
		return new WP_Error( 'flxai_key', __( 'No Anthropic (Claude) API key entered (Flux AI & MCP › Settings).', 'flx-ai' ) );
	}
	$cache = (string) ( $opts['cache'] ?? '' );
	if ( ! in_array( $cache, array( 'off', '5m', '1h' ), true ) ) {
		$cache = (string) flxai_setting( 'cache', '5m' );
	}
	list( $system, $msgs ) = flxai_anthropic_messages( $messages, $model, 'off' !== $cache );
	$max  = (int) ( $opts['max_tokens'] ?? 1024 );
	$body = array(
		'model'      => $model,
		'max_tokens' => $max,
		'messages'   => $msgs,
	);
	if ( $system ) {
		if ( '1h' === $cache ) {
			foreach ( $system as $i => $b ) {
				if ( isset( $b['cache_control'] ) ) {
					$system[ $i ]['cache_control']['ttl'] = '1h';
				}
			}
		}
		$body['system'] = $system;
	}
	if ( 'off' !== $cache ) {
		// Otomatik önbellek: konuşmanın sonuna kadar olan önek (araçlar + sistem + geçmiş) okunur.
		$cc = array( 'type' => 'ephemeral' );
		if ( '1h' === $cache ) {
			$cc['ttl'] = '1h';
		}
		$body['cache_control'] = $cc;
	}
	$adaptive = flxai_anthropic_adaptive( $model );
	if ( flxai_anthropic_effort( $model ) ) {
		$effort = (string) ( $opts['effort'] ?? flxai_setting( 'effort', 'low' ) );
		if ( ! in_array( $effort, array( 'low', 'medium', 'high', 'xhigh', 'max' ), true ) ) {
			$effort = 'low';
		}
		$body['output_config'] = array( 'effort' => $effort );
	}
	if ( ! flxai_anthropic_no_sampling( $model ) && isset( $opts['temperature'] ) && '' !== $opts['temperature'] ) {
		$body['temperature'] = (float) $opts['temperature'];
	}
	if ( $adaptive ) {
		$show = $opts['thinking'] ?? false;
		if ( $show ) {
			$body['thinking'] = preg_match( '/^claude-(opus|sonnet)-4-6/', $model )
				? array( 'type' => 'adaptive' )
				: array( 'type' => 'adaptive', 'display' => 'summarized' );
		}
		// Düşünme çıkış token'ından yer; görünen cevap için pay bırakılır.
		$body['max_tokens'] = min( 32000, $max + 6000 );
	}
	if ( ! empty( $opts['tools'] ) ) {
		$body['tools'] = flxai_anthropic_tools( (array) $opts['tools'] );
	}
	$headers                 = flxai_anthropic_headers( $key );
	$headers['content-type'] = 'application/json';
	if ( flxai_anthropic_fallbacks( $model ) ) {
		$headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
		$body['fallbacks']         = 'default';
	}
	$url     = flxai_anthropic_base() . '/messages';
	$timeout = (int) ( $opts['timeout'] ?? 45 );
	if ( flxai_can_stream( $opts ) ) {
		$body['stream'] = true;
		$emit = $opts['stream'];
		$acc  = array( 'blocks' => array(), 'parts' => array(), 'usage' => array(), 'stop' => '', 'model' => '', 'error' => null );
		$st   = flxai_http_stream(
			$url,
			$headers,
			wp_json_encode( $body ),
			$timeout,
			function ( $j ) use ( &$acc, $emit ) {
				$type = (string) ( $j['type'] ?? '' );
				$i    = (int) ( $j['index'] ?? 0 );
				if ( 'message_start' === $type ) {
					$acc['usage'] = (array) ( $j['message']['usage'] ?? array() );
					$acc['model'] = (string) ( $j['message']['model'] ?? '' );
				} elseif ( 'content_block_start' === $type ) {
					$b                     = (array) ( $j['content_block'] ?? array() );
					$acc['blocks'][ $i ]   = $b;
					if ( 'tool_use' === ( $b['type'] ?? '' ) ) {
						$acc['parts'][ $i ] = '';
						$emit( array( 't' => 'tool', 'id' => (string) ( $b['id'] ?? '' ), 'name' => (string) ( $b['name'] ?? '' ) ) );
					}
				} elseif ( 'content_block_delta' === $type ) {
					$d = (array) ( $j['delta'] ?? array() );
					switch ( (string) ( $d['type'] ?? '' ) ) {
						case 'text_delta':
							$acc['blocks'][ $i ]['text'] = (string) ( $acc['blocks'][ $i ]['text'] ?? '' ) . (string) $d['text'];
							$emit( array( 't' => 'text', 'd' => (string) $d['text'] ) );
							break;
						case 'thinking_delta':
							$acc['blocks'][ $i ]['thinking'] = (string) ( $acc['blocks'][ $i ]['thinking'] ?? '' ) . (string) $d['thinking'];
							$emit( array( 't' => 'think', 'd' => (string) $d['thinking'] ) );
							break;
						case 'signature_delta':
							$acc['blocks'][ $i ]['signature'] = (string) ( $acc['blocks'][ $i ]['signature'] ?? '' ) . (string) $d['signature'];
							break;
						case 'input_json_delta':
							$acc['parts'][ $i ] = (string) ( $acc['parts'][ $i ] ?? '' ) . (string) $d['partial_json'];
							break;
					}
				} elseif ( 'content_block_stop' === $type ) {
					if ( isset( $acc['parts'][ $i ] ) ) {
						$in                            = json_decode( '' !== $acc['parts'][ $i ] ? $acc['parts'][ $i ] : '{}', true );
						$acc['blocks'][ $i ]['input']  = is_array( $in ) && $in ? $in : new stdClass();
					}
				} elseif ( 'message_delta' === $type ) {
					$acc['stop']  = (string) ( $j['delta']['stop_reason'] ?? $acc['stop'] );
					$acc['usage'] = array_merge( $acc['usage'], (array) ( $j['usage'] ?? array() ) );
				} elseif ( 'error' === $type ) {
					$acc['error'] = (array) ( $j['error'] ?? array( 'message' => 'stream error' ) );
				}
			},
			$opts['stop_check'] ?? null
		);
		if ( is_wp_error( $st ) ) {
			if ( 'flxai_stopped' === $st->get_error_code() ) {
				$txt = '';
				foreach ( $acc['blocks'] as $b ) {
					$txt .= 'text' === ( $b['type'] ?? '' ) ? (string) ( $b['text'] ?? '' ) : '';
				}
				$st->add_data( array( 'partial' => $txt ) );
				return $st;
			}
			/* translators: %s: error */
			return new WP_Error( 'flxai_http', sprintf( __( 'Could not reach the AI service: %s', 'flx-ai' ), $st->get_error_message() ), array( 'provider' => 'anthropic' ) );
		}
		$code = (int) $st['code'];
		if ( $st['sse'] ) {
			ksort( $acc['blocks'] );
			$json = array(
				'content'     => array_values( $acc['blocks'] ),
				'usage'       => $acc['usage'],
				'stop_reason' => $acc['stop'],
				'model'       => $acc['model'],
			);
			if ( $acc['error'] ) {
				$json['error'] = $acc['error'];
			}
		} else {
			$json = json_decode( (string) $st['raw'], true );
		}
	} else {
		$r = wp_remote_post(
			$url,
			array(
				'timeout' => $timeout,
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $r ) ) {
			/* translators: %s: error */
			return new WP_Error( 'flxai_http', sprintf( __( 'Could not reach the AI service: %s', 'flx-ai' ), $r->get_error_message() ), array( 'provider' => 'anthropic' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		$json = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	}
	if ( $code < 200 || $code >= 300 || ! is_array( $json ) || isset( $json['error'] ) ) {
		$msg = is_array( $json ) ? (string) ( $json['error']['message'] ?? '' ) : '';
		return flxai_api_error( 'anthropic', $code ? $code : 500, $msg, $key );
	}
	$blocks = is_array( $json['content'] ?? null ) ? $json['content'] : array();
	$text   = '';
	$think  = '';
	$calls  = array();
	foreach ( $blocks as $b ) {
		$t = (string) ( $b['type'] ?? '' );
		if ( 'text' === $t ) {
			$text .= (string) $b['text'];
		} elseif ( 'thinking' === $t ) {
			$think .= ( '' !== $think ? "\n\n" : '' ) . (string) ( $b['thinking'] ?? '' );
		} elseif ( 'tool_use' === $t ) {
			$calls[] = array(
				'id'       => (string) $b['id'],
				'type'     => 'function',
				'function' => array(
					'name'      => (string) $b['name'],
					'arguments' => wp_json_encode( ! empty( $b['input'] ) ? $b['input'] : new stdClass() ),
				),
			);
		}
	}
	$u    = is_array( $json['usage'] ?? null ) ? $json['usage'] : array();
	$p    = flxai_anthropic_price( $model );
	$cw   = (int) ( $u['cache_creation_input_tokens'] ?? 0 );
	$cr   = (int) ( $u['cache_read_input_tokens'] ?? 0 );
	$cw1h = min( $cw, (int) ( $u['cache_creation']['ephemeral_1h_input_tokens'] ?? 0 ) );
	$in   = (int) ( $u['input_tokens'] ?? 0 ) + $cw + $cr;
	$out  = (int) ( $u['output_tokens'] ?? 0 );
	// Yazma 5 dk 1,25x, 1 saat 2x; okuma modele göre.
	$cost = ( ( $in - $cw - $cr ) * $p[0] + ( $cw - $cw1h ) * $p[0] * 1.25 + $cw1h * $p[0] * 2 + $cr * $p[0] * $p[2] + $out * $p[1] ) / 1000000;
	$full = ( $in * $p[0] + $out * $p[1] ) / 1000000;
	$msg  = array(
		'role'    => 'assistant',
		'content' => $text,
	);
	if ( $calls ) {
		$msg['tool_calls'] = $calls;
	}
	$msg['_raw']       = $blocks;
	$msg['_raw_model'] = $model;
	if ( '' !== trim( $think ) ) {
		$msg['_thinking'] = $think;
	}
	$stop           = (string) ( $json['stop_reason'] ?? '' );
	$map            = array( 'end_turn' => 'stop', 'stop_sequence' => 'stop', 'tool_use' => 'tool_calls', 'max_tokens' => 'length' );
	$msg['_finish'] = $map[ $stop ] ?? $stop;
	$msg['_usage']  = array(
		'provider' => 'anthropic',
		'model'    => 'claude:' . (string) ( $json['model'] ?? $model ),
		'in'       => $in,
		'out'      => $out,
		'cr'       => $cr,
		'cw'       => $cw,
		'cost'     => round( $cost, 8 ),
		'full'     => round( $full, 8 ),
		'est'      => false,
		'cache'    => $cache,
	);
	return $msg;
}
