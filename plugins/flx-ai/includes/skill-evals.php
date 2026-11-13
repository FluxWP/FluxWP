<?php
/**
 * Skill testleri (0.17.0): skill klasöründe evals/evals.json (skill-creator ile uyumlu biçim) ve evals/results.json.
 *
 * {
 *   "skill_name": "bagisci-incelemesi",
 *   "trigger_evals": [ { "query": "Dünkü bağışçılara bak", "should_trigger": true }, … ],
 *   "evals": [ { "id": "1", "prompt": "…", "expected_output": "…", "expectations": [ "…", … ] } ]
 * }
 *
 * - Tetiklenme testi: modele yalnız 1. seviye (ad + açıklama listesi) verilir; hangi skill'leri yükleyeceği sorulur.
 * - Fonksiyonel test + kıyas: aynı istem skill'li ve skill'siz cevaplanır (araçsız; yazma yok), beklentiler bir
 *   değerlendirme çağrısıyla işaretlenir; token, süre ve maliyet yan yana konur.
 * Her çalıştırma AI harcaması yapar (amaç skill_test, Kayıtlar'da görünür).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Test dosyası: önce sitedeki .skills/<ad>/evals/ (hazır skill kopyalanmadan buraya yazılır), yoksa skill klasörü.
 *
 * @return array decoded JSON
 */
function flxai_skill_eval_file_get( array $s, $name ) {
	$ws = flxai_skill_rel_dir( $s['slug'] ) . '/evals/' . $name;
	$tx = flxai_ws_read( $ws );
	if ( null === $tx ) {
		$r  = flxai_skill_file_read( $s, 'evals/' . $name, 0, 400000 );
		$tx = is_wp_error( $r ) ? '' : (string) ( $r['content'] ?? '' );
	}
	$d = '' === $tx ? null : json_decode( $tx, true );
	return is_array( $d ) ? $d : array();
}

/** Test dosyasını sitedeki klasöre yazar (hazır skill'i kopyalamaz; SKILL.md'ye dokunmaz). */
function flxai_skill_eval_file_put( array $s, $name, array $data ) {
	return flxai_ws_write( flxai_skill_rel_dir( $s['slug'] ) . '/evals/' . $name, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
}

/** Skill'in testleri. */
function flxai_skill_evals_get( array $s ) {
	$d   = flxai_skill_eval_file_get( $s, 'evals.json' );
	$out = array(
		'skill_name'    => $s['slug'],
		'trigger_evals' => array(),
		'evals'         => array(),
	);
	foreach ( (array) ( $d['trigger_evals'] ?? array() ) as $t ) {
		$q = trim( (string) ( $t['query'] ?? '' ) );
		if ( '' !== $q ) {
			$out['trigger_evals'][] = array(
				'query'          => mb_substr( $q, 0, 1000 ),
				'should_trigger' => ! isset( $t['should_trigger'] ) || ! empty( $t['should_trigger'] ),
			);
		}
	}
	foreach ( (array) ( $d['evals'] ?? array() ) as $k => $e ) {
		$p = trim( (string) ( $e['prompt'] ?? '' ) );
		if ( '' === $p ) {
			continue;
		}
		$out['evals'][] = array(
			'id'              => (string) ( $e['id'] ?? ( $k + 1 ) ),
			'prompt'          => mb_substr( $p, 0, 4000 ),
			'expected_output' => mb_substr( (string) ( $e['expected_output'] ?? '' ), 0, 4000 ),
			'expectations'    => array_values( array_filter( array_map( fn( $x ) => mb_substr( trim( (string) $x ), 0, 300 ), (array) ( $e['expectations'] ?? array() ) ), 'strlen' ) ),
		);
	}
	return $out;
}

/** Testleri kaydeder. @return true|WP_Error */
function flxai_skill_evals_save( $slug, array $evals ) {
	$s = flxai_skill_get( $slug );
	if ( ! $s ) {
		return new WP_Error( 'flxai_not_found', __( 'Skill not found.', 'flx-ai' ), array( 'status' => 404 ) );
	}
	$tmp = array(
		'skill_name'    => $s['slug'],
		'trigger_evals' => array(),
		'evals'         => array(),
	);
	foreach ( (array) ( $evals['trigger_evals'] ?? array() ) as $t ) {
		$q = trim( (string) ( $t['query'] ?? '' ) );
		if ( '' !== $q && count( $tmp['trigger_evals'] ) < 40 ) {
			$tmp['trigger_evals'][] = array(
				'query'          => mb_substr( $q, 0, 1000 ),
				'should_trigger' => ! empty( $t['should_trigger'] ),
			);
		}
	}
	$n = 0;
	foreach ( (array) ( $evals['evals'] ?? array() ) as $e ) {
		$p = trim( (string) ( $e['prompt'] ?? '' ) );
		if ( '' === $p || count( $tmp['evals'] ) >= 10 ) {
			continue;
		}
		$tmp['evals'][] = array(
			'id'              => (string) ( $e['id'] ?? '' ) !== '' ? sanitize_key( (string) $e['id'] ) : (string) ( ++$n ),
			'prompt'          => mb_substr( $p, 0, 4000 ),
			'expected_output' => mb_substr( trim( (string) ( $e['expected_output'] ?? '' ) ), 0, 4000 ),
			'expectations'    => array_values( array_filter( array_map( fn( $x ) => mb_substr( trim( (string) $x ), 0, 300 ), (array) ( $e['expectations'] ?? array() ) ), 'strlen' ) ),
		);
	}
	return flxai_skill_eval_file_put( $s, 'evals.json', $tmp );
}

function flxai_skill_eval_results( array $s ) {
	return flxai_skill_eval_file_get( $s, 'results.json' ) + array( 'trigger' => null, 'functional' => array() );
}

function flxai_skill_eval_store( $slug, $key, $value, $sub = '' ) {
	$s = flxai_skill_get( $slug );
	if ( ! $s ) {
		return;
	}
	$d = flxai_skill_eval_results( $s );
	if ( '' !== $sub ) {
		$d[ $key ]         = is_array( $d[ $key ] ?? null ) ? $d[ $key ] : array();
		$d[ $key ][ $sub ] = $value;
	} else {
		$d[ $key ] = $value;
	}
	flxai_skill_eval_file_put( $s, 'results.json', $d );
}

/** Kısa model çağrısı (test). */
function flxai_skill_eval_call( array $messages, $max_tokens ) {
	$r = flxai_chat(
		$messages,
		array(
			'source'      => 'flx-ai',
			'purpose'     => 'skill_test',
			'max_tokens'  => (int) $max_tokens,
			'temperature' => 0,
			'timeout'     => 25,
			'cache'       => 'off',
		)
	);
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$u = (array) ( $r['_usage'] ?? array() );
	return array(
		'text' => trim( (string) ( is_string( $r['content'] ?? null ) ? $r['content'] : '' ) ),
		'in'   => (int) ( $u['in'] ?? 0 ),
		'out'  => (int) ( $u['out'] ?? 0 ),
		'cost' => (float) ( $u['cost'] ?? 0 ),
		'ms'   => (int) ( $u['ms'] ?? 0 ),
	);
}

/** Metindeki ilk JSON nesnesi. */
function flxai_skill_eval_json( $text ) {
	$text = preg_replace( '/^```(?:json)?|```$/m', '', (string) $text );
	if ( preg_match( '/\{.*\}/s', $text, $m ) ) {
		$d = json_decode( $m[0], true );
		return is_array( $d ) ? $d : null;
	}
	return null;
}

/**
 * Tetiklenme testi.
 *
 * @return array|WP_Error hits, total, rate, rows[query, should_trigger, triggered, picked[]], partial
 */
function flxai_skill_eval_trigger( $slug ) {
	$s = flxai_skill_get( $slug );
	if ( ! $s ) {
		return new WP_Error( 'flxai_not_found', __( 'Skill not found.', 'flx-ai' ), array( 'status' => 404 ) );
	}
	$ev = flxai_skill_evals_get( $s );
	if ( ! $ev['trigger_evals'] ) {
		return new WP_Error( 'flxai_skill_eval', __( 'Add trigger test prompts first (some that should use the skill, some that should not).', 'flx-ai' ), array( 'status' => 400 ) );
	}
	$list = flxai_skills_all( true );
	$list[ $s['slug'] ] = $s;
	$lines = array();
	foreach ( $list as $x ) {
		$lines[] = '- ' . $x['slug'] . ': ' . $x['description'];
	}
	$sys  = "Kullanıcı isteğine göre hangi skill'lerin (talimat paketlerinin) yükleneceğine karar veriyorsun. Yalnız ad ve açıklamaları görüyorsun:\n"
		. implode( "\n", $lines )
		. "\n\nİstek bir skill'in açıklamasına açıkça uyuyorsa onu seç; birden çok uyabilir; hiçbiri uymuyorsa boş liste. Yalnız JSON yaz: {\"skills\":[\"ad\", …]}";
	$t0   = microtime( true );
	$rows = array();
	$hits = 0;
	$cost = 0;
	foreach ( array_slice( $ev['trigger_evals'], 0, 20 ) as $t ) {
		if ( microtime( true ) - $t0 > 38 ) {
			break;
		}
		$r = flxai_skill_eval_call(
			array(
				array( 'role' => 'system', 'content' => $sys ),
				array( 'role' => 'user', 'content' => $t['query'] ),
			),
			120
		);
		if ( is_wp_error( $r ) ) {
			if ( ! $rows ) {
				return $r;
			}
			break;
		}
		$d      = flxai_skill_eval_json( $r['text'] );
		$picked = array_values( array_filter( array_map( 'flxai_skill_slug', (array) ( $d['skills'] ?? array() ) ) ) );
		$got    = in_array( $s['slug'], $picked, true );
		$hits  += $got === $t['should_trigger'] ? 1 : 0;
		$cost  += $r['cost'];
		$rows[] = array(
			'query'          => $t['query'],
			'should_trigger' => $t['should_trigger'],
			'triggered'      => $got,
			'picked'         => $picked,
		);
	}
	$out = array(
		'ts'      => time(),
		'model'   => flxai_model_for( 'flx-ai', '' ),
		'hits'    => $hits,
		'total'   => count( $rows ),
		'rate'    => $rows ? round( 100 * $hits / count( $rows ) ) : 0,
		'cost'    => round( $cost, 5 ),
		'rows'    => $rows,
		'partial' => count( $rows ) < min( 20, count( $ev['trigger_evals'] ) ),
	);
	flxai_skill_eval_store( $s['slug'], 'trigger', $out );
	return $out;
}

/**
 * Fonksiyonel test + kıyas (skill'li / skill'siz).
 *
 * @return array|WP_Error
 */
function flxai_skill_eval_functional( $slug, $id ) {
	$s = flxai_skill_get( $slug );
	if ( ! $s ) {
		return new WP_Error( 'flxai_not_found', __( 'Skill not found.', 'flx-ai' ), array( 'status' => 404 ) );
	}
	$case = null;
	foreach ( flxai_skill_evals_get( $s )['evals'] as $e ) {
		if ( '' === (string) $id || (string) $e['id'] === (string) $id ) {
			$case = $e;
			break;
		}
	}
	if ( ! $case ) {
		return new WP_Error( 'flxai_skill_eval', __( 'Test not found. Add a functional test with a prompt and expectations.', 'flx-ai' ), array( 'status' => 400 ) );
	}
	$sys = "Sen bu WordPress sitesinin AI asistanısın. Bu bir testtir: araç çağıramazsın ve hiçbir şeyi değiştiremezsin. Gerçek veri gerekiyorsa hangi aracı nasıl kullanacağını kısaca söyle ve cevabın biçimini makul örnek değerlerle göster (örnek olduğunu belirt).";
	$a   = flxai_skill_eval_call(
		array(
			array( 'role' => 'system', 'content' => $sys ),
			array( 'role' => 'user', 'content' => flxai_skill_prompt( $s ) . "\n\n" . $case['prompt'] ),
		),
		1000
	);
	if ( is_wp_error( $a ) ) {
		return $a;
	}
	$b = flxai_skill_eval_call(
		array(
			array( 'role' => 'system', 'content' => $sys ),
			array( 'role' => 'user', 'content' => $case['prompt'] ),
		),
		1000
	);
	if ( is_wp_error( $b ) ) {
		return $b;
	}
	$exp = $case['expectations'];
	if ( ! $exp && '' !== $case['expected_output'] ) {
		$exp = array( 'Cevap şu beklenen çıktıya içerik ve biçim olarak uyuyor: ' . $case['expected_output'] );
	}
	$ja = array();
	$jb = array();
	$jn = '';
	$jc = 0;
	if ( $exp ) {
		$q = "Beklentiler (sırayla):\n";
		foreach ( $exp as $k => $x ) {
			$q .= ( $k + 1 ) . '. ' . $x . "\n";
		}
		$q .= "\n=== CEVAP A ===\n" . mb_substr( $a['text'], 0, 6000 ) . "\n\n=== CEVAP B ===\n" . mb_substr( $b['text'], 0, 6000 )
			. "\n\nHer beklenti için A ve B'nin sağlayıp sağlamadığını işaretle. Yalnız JSON: {\"a\":[true,false,…],\"b\":[…],\"note\":\"tek cümle kıyas\"}";
		$j = flxai_skill_eval_call(
			array(
				array( 'role' => 'system', 'content' => 'Sen tarafsız ve katı bir değerlendiricisin. Beklentiyi açıkça karşılamayan cevap false.' ),
				array( 'role' => 'user', 'content' => $q ),
			),
			400
		);
		if ( ! is_wp_error( $j ) ) {
			$d  = flxai_skill_eval_json( $j['text'] );
			$ja = array_map( 'boolval', (array) ( $d['a'] ?? array() ) );
			$jb = array_map( 'boolval', (array) ( $d['b'] ?? array() ) );
			$jn = mb_substr( (string) ( $d['note'] ?? '' ), 0, 400 );
			$jc = $j['cost'];
		}
	}
	$checks = array();
	foreach ( $exp as $k => $x ) {
		$checks[] = array(
			'text'    => $x,
			'with'    => ! empty( $ja[ $k ] ),
			'without' => ! empty( $jb[ $k ] ),
		);
	}
	$side = function ( $r, $key ) use ( $checks ) {
		return array(
			'passed' => count( array_filter( $checks, fn( $c ) => $c[ $key ] ) ),
			'total'  => count( $checks ),
			'in'     => $r['in'],
			'out'    => $r['out'],
			'cost'   => round( $r['cost'], 5 ),
			'ms'     => $r['ms'],
			'output' => mb_substr( $r['text'], 0, 4000 ),
		);
	};
	$out = array(
		'ts'         => time(),
		'id'         => $case['id'],
		'prompt'     => $case['prompt'],
		'with'       => $side( $a, 'with' ),
		'without'    => $side( $b, 'without' ),
		'checks'     => $checks,
		'note'       => $jn,
		'judge_cost' => round( $jc, 5 ),
	);
	flxai_skill_eval_store( $s['slug'], 'functional', $out, (string) $case['id'] );
	return $out;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flx-ai/v1',
			'/skills/(?P<slug>[a-z0-9-]+)/test',
			array(
				'methods'             => 'POST',
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
				'callback'            => function ( WP_REST_Request $req ) {
					$slug = (string) $req['slug'];
					$r    = 'functional' === $req->get_param( 'kind' ) ? flxai_skill_eval_functional( $slug, (string) $req->get_param( 'id' ) ) : flxai_skill_eval_trigger( $slug );
					return is_wp_error( $r ) ? $r : rest_ensure_response( $r );
				},
			)
		);
	}
);
