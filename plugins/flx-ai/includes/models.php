<?php
/**
 * Model kataloğu ve fiyatlar.
 *
 * Model kimlikleri:
 *  - "claude:<anthropic id>"  → doğrudan Anthropic (ör. claude:claude-haiku-5-5)
 *  - "openrouter:<id>" ya da "<firma>/<model>" → OpenRouter (ör. google/gemini-2.5-flash)
 *  - önek yok ve "claude-" ile başlıyor → doğrudan Anthropic (kolaylık)
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

function flxai_openrouter_base() {
	return untrailingslashit( (string) apply_filters( 'flxai_openrouter_base', 'https://openrouter.ai/api/v1' ) );
}

function flxai_anthropic_base() {
	return untrailingslashit( (string) apply_filters( 'flxai_anthropic_base', 'https://api.anthropic.com/v1' ) );
}

/**
 * Model kimliğini çözer.
 *
 * @return array array( provider: anthropic|openrouter, id: sağlayıcıya giden kimlik, full: kayıtlı biçim )
 */
function flxai_parse_model( $model ) {
	$model = trim( (string) $model );
	if ( 0 === strpos( $model, 'claude:' ) ) {
		$id = substr( $model, 7 );
		return array( 'provider' => 'anthropic', 'id' => $id, 'full' => 'claude:' . $id );
	}
	if ( 0 === strpos( $model, 'openrouter:' ) ) {
		$id = substr( $model, 11 );
		return array( 'provider' => 'openrouter', 'id' => $id, 'full' => $id );
	}
	if ( false === strpos( $model, '/' ) && 0 === strpos( $model, 'claude-' ) ) {
		return array( 'provider' => 'anthropic', 'id' => $model, 'full' => 'claude:' . $model );
	}
	return array( 'provider' => 'openrouter', 'id' => $model, 'full' => $model );
}

function flxai_provider_key( $provider ) {
	return 'anthropic' === $provider ? flxai_setting( 'key_anthropic' ) : flxai_setting( 'key_openrouter' );
}

function flxai_provider_labels() {
	return array(
		'openrouter' => 'OpenRouter',
		'anthropic'  => 'Anthropic (Claude)',
	);
}

/** Anahtarı girilmiş sağlayıcılar. */
function flxai_providers_ready() {
	$on = array();
	foreach ( flxai_provider_labels() as $id => $label ) {
		if ( '' !== flxai_provider_key( $id ) ) {
			$on[ $id ] = $label;
		}
	}
	return $on;
}

/**
 * Bir kaynak (eklenti) için kullanılacak model: çağıranın verdiği → kaynak ayarı → varsayılan.
 */
function flxai_model_for( $source = '', $given = '' ) {
	if ( '' !== trim( (string) $given ) ) {
		return trim( (string) $given );
	}
	$map = (array) flxai_setting( 'source_models', array() );
	if ( '' !== $source && ! empty( $map[ $source ] ) ) {
		return (string) $map[ $source ];
	}
	$m = (string) flxai_setting( 'model', '' );
	if ( '' === $m ) {
		// Hiç seçilmemişse anahtarı olan sağlayıcının ucuz modeli.
		$ready = flxai_providers_ready();
		if ( isset( $ready['anthropic'] ) ) {
			$m = 'claude:claude-haiku-5-5';
		} elseif ( isset( $ready['openrouter'] ) ) {
			$m = 'google/gemini-2.5-flash';
		}
	}
	return (string) apply_filters( 'flxai_model_for', $m, $source );
}

/** Flux AI kullanıma hazır mı (en az bir anahtar + model)? */
function flxai_ready( $source = '' ) {
	$m = flxai_model_for( $source );
	if ( '' === $m ) {
		return false;
	}
	$p = flxai_parse_model( $m );
	return '' !== flxai_provider_key( $p['provider'] );
}

/* ---------------------------------------------------------------------------
 * Anthropic: fiyat ve yetenekler
 * ------------------------------------------------------------------------- */

/**
 * 1M token başına USD: array( giriş, çıkış, önbellek okuma çarpanı ).
 * Models API fiyat vermez; bilinen tablo (2026-10). flxai_anthropic_price ile değiştirilebilir.
 */
function flxai_anthropic_price( $id ) {
	$id    = (string) $id;
	$table = array(
		'claude-mythos-5-1' => array( 10, 50, 0.025 ),
		'claude-fable-5-1'  => array( 10, 50, 0.025 ),
		'claude-fable-5'    => array( 10, 50, 0.1 ),
		'claude-opus-5-5'   => array( 4, 20, 0.05 ),
		'claude-opus-5'     => array( 5, 25, 0.1 ),
		'claude-opus-4-8'   => array( 5, 25, 0.1 ),
		'claude-opus-4-7'   => array( 5, 25, 0.1 ),
		'claude-opus-4-6'   => array( 5, 25, 0.1 ),
		'claude-opus-4-5'   => array( 5, 25, 0.1 ),
		'claude-sonnet-5-5' => array( 2, 10, 0.1 ),
		'claude-sonnet-5'   => array( 2, 10, 0.1 ),
		'claude-sonnet-4-6' => array( 3, 15, 0.1 ),
		'claude-haiku-5-5'  => array( 0.10, 0.50, 0.1 ),
		'claude-haiku-4-5'  => array( 1, 5, 0.1 ),
	);
	$best = '';
	foreach ( $table as $k => $p ) {
		if ( 0 === strpos( $id, $k ) && strlen( $k ) > strlen( $best ) ) {
			$best = $k;
		}
	}
	if ( '' !== $best ) {
		$p = $table[ $best ];
	} elseif ( false !== strpos( $id, 'opus' ) ) {
		$p = array( 5, 25, 0.1 );
	} elseif ( false !== strpos( $id, 'haiku' ) ) {
		$p = array( 1, 5, 0.1 );
	} else {
		$p = array( 3, 15, 0.1 );
	}
	$p = (array) apply_filters( 'flxai_anthropic_price', $p, $id );
	return array( (float) $p[0], (float) $p[1], (float) ( $p[2] ?? 0.1 ) );
}

/** Adaptive thinking alan modeller (4.6+ ve 5 ailesi). Haiku 4.5 ve eskiler almaz. */
function flxai_anthropic_adaptive( $id ) {
	return (bool) preg_match( '/^claude-(opus-(4-[6-9]|5)|sonnet-(4-6|5)|haiku-5|fable|mythos)/', (string) $id );
}

/** output_config.effort alan modeller. */
function flxai_anthropic_effort( $id ) {
	return (bool) preg_match( '/^claude-(opus-(4-[5-9]|5)|sonnet-(4-6|5)|haiku-5|fable|mythos)/', (string) $id );
}

/** temperature kabul etmeyen modeller (4.7+ / 5 ailesi). */
function flxai_anthropic_no_sampling( $id ) {
	return (bool) preg_match( '/^claude-(opus-(4-[7-9]|5)|sonnet-5|haiku-5|fable|mythos)/', (string) $id );
}

/** Ret (refusal) durumunda sunucu tarafı yedek model. */
function flxai_anthropic_fallbacks( $id ) {
	return (bool) preg_match( '/^claude-(opus-5-5|opus-5|sonnet-5-5|fable-5-1)(-|$)/', (string) $id );
}

/** Models API yoksa gösterilen bilinen modeller. */
function flxai_anthropic_static() {
	return array(
		'claude-fable-5-1'  => 'Claude Fable 5.1',
		'claude-opus-5-5'   => 'Claude Opus 5.5',
		'claude-sonnet-5-5' => 'Claude Sonnet 5.5',
		'claude-haiku-5-5'  => 'Claude Haiku 5.5',
		'claude-opus-5'     => 'Claude Opus 5',
		'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
		'claude-haiku-4-5'  => 'Claude Haiku 4.5',
	);
}

/** Anthropic istek başlıkları. */
function flxai_anthropic_headers( $key ) {
	$h  = array(
		'x-api-key'         => $key,
		'anthropic-version' => '2023-06-01',
	);
	$ws = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) flxai_setting( 'anthropic_workspace' ) );
	if ( '' !== $ws ) {
		$h['anthropic-workspace-id'] = $ws;
	}
	return $h;
}

/* ---------------------------------------------------------------------------
 * Katalog
 * ------------------------------------------------------------------------- */

/** Anthropic model listesi (Models API, 12 saat). Hata olursa bilinen modeller. */
function flxai_anthropic_models( $fresh = false ) {
	$c = $fresh ? false : get_transient( 'flxai_models_anthropic' );
	if ( is_array( $c ) && $c ) {
		return $c;
	}
	$key  = flxai_setting( 'key_anthropic' );
	$rows = array();
	$err  = false;
	if ( '' !== $key ) {
		$after = '';
		for ( $page = 0; $page < 3; $page++ ) {
			$r = wp_remote_get(
				flxai_anthropic_base() . '/models?limit=100' . ( '' !== $after ? '&after_id=' . rawurlencode( $after ) : '' ),
				array(
					'timeout' => 20,
					'headers' => flxai_anthropic_headers( $key ),
				)
			);
			if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
				$err = true;
				break;
			}
			$json = json_decode( (string) wp_remote_retrieve_body( $r ), true );
			foreach ( (array) ( $json['data'] ?? array() ) as $m ) {
				if ( ! empty( $m['id'] ) ) {
					$rows[] = $m;
				}
			}
			if ( empty( $json['has_more'] ) || empty( $json['last_id'] ) ) {
				break;
			}
			$after = (string) $json['last_id'];
		}
	}
	if ( ! $rows ) {
		foreach ( flxai_anthropic_static() as $id => $name ) {
			$rows[] = array( 'id' => $id, 'display_name' => $name, 'max_input_tokens' => 'claude-haiku-4-5' === $id ? 200000 : 1000000 );
		}
	}
	$out = array();
	foreach ( $rows as $m ) {
		$id    = (string) $m['id'];
		$p     = flxai_anthropic_price( $id );
		$out[] = array(
			'id'       => 'claude:' . $id,
			'name'     => (string) ( $m['display_name'] ?? $id ),
			'family'   => 'Anthropic',
			'provider' => 'anthropic',
			'ctx'      => (int) ( $m['max_input_tokens'] ?? 0 ),
			'in'       => $p[0],
			'out'      => $p[1],
			'cache'    => round( $p[0] * $p[2], 4 ),
			'tools'    => true,
			'vision'   => true,
			'thinking' => flxai_anthropic_adaptive( $id ),
		);
	}
	set_transient( 'flxai_models_anthropic', $out, $err || '' === $key ? 10 * MINUTE_IN_SECONDS : 12 * HOUR_IN_SECONDS );
	return $out;
}

/** "Google: Gemini 2.5 Flash" → Google. */
function flxai_family( $id, $name ) {
	if ( preg_match( '/^([^:]{2,30}):\s/u', (string) $name, $m ) ) {
		return trim( $m[1] );
	}
	$pre = strstr( (string) $id, '/', true );
	return false !== $pre && '' !== $pre ? ucfirst( $pre ) : '';
}

/** OpenRouter model listesi (12 saat, anahtarsız da alınır). */
function flxai_openrouter_models( $fresh = false ) {
	$c = $fresh ? false : get_transient( 'flxai_models_openrouter' );
	if ( is_array( $c ) ) {
		return $c;
	}
	$r = wp_remote_get( flxai_openrouter_base() . '/models', array( 'timeout' => 20 ) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return is_wp_error( $r ) ? $r : new WP_Error( 'flxai_models', __( 'Could not load the model list.', 'flx-ai' ) );
	}
	$json = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	$out  = array();
	foreach ( (array) ( $json['data'] ?? array() ) as $m ) {
		if ( empty( $m['id'] ) ) {
			continue;
		}
		$name   = (string) ( $m['name'] ?? $m['id'] );
		$params = (array) ( $m['supported_parameters'] ?? array() );
		$out[]  = array(
			'id'       => (string) $m['id'],
			'name'     => $name,
			'family'   => flxai_family( $m['id'], $name ),
			'provider' => 'openrouter',
			'ctx'      => (int) ( $m['context_length'] ?? 0 ),
			'in'       => round( (float) ( $m['pricing']['prompt'] ?? 0 ) * 1000000, 4 ),
			'out'      => round( (float) ( $m['pricing']['completion'] ?? 0 ) * 1000000, 4 ),
			'cache'    => round( (float) ( $m['pricing']['input_cache_read'] ?? 0 ) * 1000000, 4 ),
			'tools'    => in_array( 'tools', $params, true ),
			'vision'   => in_array( 'image', (array) ( $m['architecture']['input_modalities'] ?? array() ), true ),
			'thinking' => in_array( 'reasoning', $params, true ),
		);
	}
	usort(
		$out,
		function ( $a, $b ) {
			return strcmp( $a['id'], $b['id'] );
		}
	);
	set_transient( 'flxai_models_openrouter', $out, 12 * HOUR_IN_SECONDS );
	return $out;
}

/**
 * Birleşik katalog (anahtarı girilmiş sağlayıcılar; hiçbiri yoksa OpenRouter listesi).
 * Sonuç "flxai_models_all" geçicisinde tutulur; maliyet tahmini dış istek atmadan okur.
 *
 * @return array|WP_Error
 */
function flxai_models( $fresh = false ) {
	$c = $fresh ? false : get_transient( 'flxai_models_all' );
	if ( is_array( $c ) && $c ) {
		return $c;
	}
	$ready = flxai_providers_ready();
	if ( ! $ready ) {
		$ready = array( 'openrouter' => 'OpenRouter' );
	}
	$out = array();
	$err = null;
	foreach ( array_keys( $ready ) as $p ) {
		$l = 'anthropic' === $p ? flxai_anthropic_models( $fresh ) : flxai_openrouter_models( $fresh );
		if ( is_wp_error( $l ) ) {
			$err = $l;
			continue;
		}
		$out = array_merge( $out, $l );
	}
	if ( ! $out && $err ) {
		return $err;
	}
	$out = (array) apply_filters( 'flxai_models', $out );
	set_transient( 'flxai_models_all', $out, 12 * HOUR_IN_SECONDS );
	return $out;
}

function flxai_models_flush() {
	delete_transient( 'flxai_models_all' );
	delete_transient( 'flxai_models_anthropic' );
	delete_transient( 'flxai_models_openrouter' );
}

/** Katalogdaki bir model (önbellekten; dış istek atmaz). */
function flxai_model_info( $model ) {
	$p    = flxai_parse_model( $model );
	$list = get_transient( 'flxai_models_all' );
	foreach ( is_array( $list ) ? $list : array() as $m ) {
		if ( $m['id'] === $p['full'] ) {
			return $m;
		}
	}
	if ( 'anthropic' === $p['provider'] ) {
		$pr = flxai_anthropic_price( $p['id'] );
		return array(
			'id'       => $p['full'],
			'name'     => $p['id'],
			'provider' => 'anthropic',
			'in'       => $pr[0],
			'out'      => $pr[1],
			'cache'    => $pr[0] * $pr[2],
			'tools'    => true,
			'vision'   => true,
			'thinking' => flxai_anthropic_adaptive( $p['id'] ),
		);
	}
	return null;
}

/** Modelin kısa adı (arayüz): "Claude Haiku 5.5" / "google/gemini-2.5-flash". */
function flxai_model_label( $model ) {
	$i = flxai_model_info( $model );
	return $i ? (string) $i['name'] : (string) $model;
}

/** Fiyat listesinden USD tahmini. */
function flxai_estimate_cost( $model, $in, $out, $cache_read = 0 ) {
	$i = flxai_model_info( $model );
	if ( ! $i ) {
		return 0.0;
	}
	$cr = (int) $cache_read;
	return ( ( (int) $in - $cr ) * (float) $i['in'] + $cr * (float) ( $i['cache'] ?: $i['in'] ) + (int) $out * (float) $i['out'] ) / 1000000;
}
