<?php
/**
 * Araç kaydı. Kaynaklar:
 *  - flxai_tools filtresi (her eklenti kendi araçlarını ekler),
 *  - MCP araçları (flx_mcp_tools, eski adı flxseo_mcp_tools; mcp.php) otomatik gelir.
 *
 * Araç: name => array( title, description, schema (JSON Schema), readonly (bool), cap, run (callable $args, $ctx → array),
 *        source, confirm (bool: okuma aracı olsa da onay iste) ).
 * readonly olmayan her araç ortak sohbette çalışmadan önce kullanıcı onayı ister.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** Tüm kayıtlı araçlar (yetki ve kapalı listesi süzülmeden). */
function flxai_tools_all() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$tools = array();
	if ( function_exists( 'flxai_mcp_tools' ) ) {
		foreach ( (array) flxai_mcp_tools() as $name => $t ) {
			if ( ! is_array( $t ) || empty( $t['run'] ) ) {
				continue;
			}
			$tools[ $name ] = array(
				'title'       => (string) ( $t['title'] ?? $name ),
				'description' => (string) ( $t['description'] ?? '' ),
				'schema'      => $t['schema'] ?? array( 'type' => 'object', 'properties' => new stdClass() ),
				'readonly'    => ! empty( $t['readonly'] ),
				'cap'         => (string) ( $t['cap'] ?? 'edit_posts' ),
				'source'      => flxai_mcp_tool_source( $t ),
				'mcp'         => true,
				'run'         => null,
			);
		}
	}
	$tools = array_merge( $tools, flxai_core_tools() );
	$tools = (array) apply_filters( 'flxai_tools', $tools );
	foreach ( $tools as $name => $t ) {
		if ( ! is_array( $t ) || ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', (string) $name ) || ( empty( $t['mcp'] ) && ( empty( $t['run'] ) || ! is_callable( $t['run'] ) ) ) ) {
			unset( $tools[ $name ] );
			continue;
		}
		$tools[ $name ] += array(
			'title'       => $name,
			'description' => '',
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'readonly'    => false,
			'cap'         => 'read',
			'source'      => '',
			'confirm'     => false,
		);
	}
	$cache = $tools;
	return $tools;
}

/** Kullanıcının kullanabileceği, açık araçlar. */
function flxai_tools_for_user( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$off     = (array) flxai_setting( 'tools_off', array() );
	$out     = array();
	foreach ( flxai_tools_all() as $name => $t ) {
		if ( in_array( $name, $off, true ) ) {
			continue;
		}
		if ( '' !== $t['cap'] && ! user_can( $user_id, $t['cap'] ) ) {
			continue;
		}
		$out[ $name ] = $t;
	}
	return $out;
}

/** Bu araç çalışmadan önce onay ister mi? */
function flxai_tool_needs_approval( array $t ) {
	return empty( $t['readonly'] ) || ! empty( $t['confirm'] );
}

/** OpenAI biçimi araç tanımları. */
function flxai_tool_defs( array $tools ) {
	$defs = array();
	foreach ( $tools as $name => $t ) {
		$schema = is_array( $t['schema'] ) ? $t['schema'] : (array) $t['schema'];
		if ( empty( $schema['type'] ) ) {
			$schema['type'] = 'object';
		}
		if ( empty( $schema['properties'] ) ) {
			$schema['properties'] = new stdClass();
		}
		$desc = (string) $t['description'];
		if ( flxai_tool_needs_approval( $t ) ) {
			$desc .= ' [Değişiklik yapar: çalışmadan önce kullanıcı onayı istenir.]';
		}
		$defs[] = array(
			'type'     => 'function',
			'function' => array(
				'name'        => $name,
				'description' => mb_substr( $desc, 0, 1000 ),
				'parameters'  => $schema,
			),
		);
	}
	return $defs;
}

/**
 * Aracı çalıştırır, süresini kaydeder.
 *
 * @return array array( ok bool, result string (JSON), ms int )
 */
function flxai_run_tool( $name, $args, array $ctx = array() ) {
	$tools = flxai_tools_for_user( (int) ( $ctx['user_id'] ?? 0 ) );
	$t0    = microtime( true );
	$ok    = false;
	if ( ! isset( $tools[ $name ] ) ) {
		/* translators: %s: tool name */
		$data = array( 'error' => sprintf( __( 'Araç yok ya da yetkiniz yok: %s', 'flx-ai' ), $name ) );
	} else {
		$t = $tools[ $name ];
		try {
			if ( ! empty( $t['mcp'] ) ) {
				$r    = flxai_mcp_call( $name, is_array( $args ) ? $args : array() );
				$text = (string) ( $r['content'][0]['text'] ?? '' );
				$dec  = json_decode( $text, true );
				$data = null !== $dec ? $dec : $text;
				$ok   = empty( $r['isError'] );
			} else {
				$data = call_user_func( $t['run'], is_array( $args ) ? $args : array(), $ctx );
				$ok   = true;
			}
		} catch ( Throwable $e ) {
			$data = array( 'error' => $e->getMessage() );
		}
	}
	$ms   = (int) round( ( microtime( true ) - $t0 ) * 1000 );
	$json = is_string( $data ) ? $data : wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( mb_strlen( (string) $json ) > 30000 ) {
		$json = mb_substr( (string) $json, 0, 30000 ) . "\n…(kısaltıldı)";
	}
	flxai_log(
		array(
			'kind'    => 'tool',
			'source'  => (string) ( $ctx['source'] ?? 'flx-ai' ),
			'purpose' => (string) ( $ctx['purpose'] ?? 'chat' ),
			'chat_id' => (int) ( $ctx['chat_id'] ?? 0 ),
			'user_id' => (int) ( $ctx['user_id'] ?? get_current_user_id() ),
			'tool'    => $name,
			'ms'      => $ms,
			'ok'      => $ok,
			'error'   => $ok ? '' : ( is_array( $data ) ? (string) ( $data['error'] ?? '' ) : '' ),
		)
	);
	return array( 'ok' => $ok, 'result' => (string) $json, 'ms' => $ms );
}

/** Flux AI'nin kendi araçları. */
function flxai_core_tools() {
	return array(
		'flxai_usage'        => array(
			'title'       => __( 'Yapay zekâ kullanımı', 'flx-ai' ),
			'description' => 'Yapay zekâ harcaması ve kullanım özeti: günlük/haftalık/aylık harcama ve sınırlar, son 30 günün eklenti ve model dökümü.',
			'readonly'    => true,
			'cap'         => 'manage_options',
			'source'      => 'flx-ai',
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'run'         => function () {
				$r = flxai_report( 30 );
				return array(
					'budget'    => flxai_budget_status(),
					'totals'    => $r['totals'],
					'by_source' => $r['by_source'],
					'by_model'  => $r['by_model'],
					'currency'  => 'USD',
				);
			},
		),
		'flxai_current_time' => array(
			'title'       => __( 'Tarih ve saat', 'flx-ai' ),
			'description' => 'Sitenin saat dilimine göre şu anki tarih ve saat.',
			'readonly'    => true,
			'cap'         => 'read',
			'source'      => 'flx-ai',
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'run'         => function () {
				return array(
					'now'      => wp_date( 'c' ),
					'text'     => flx_date( time(), 'l, j F Y H:i' ),
					'timezone' => wp_timezone_string(),
				);
			},
		),
	);
}
