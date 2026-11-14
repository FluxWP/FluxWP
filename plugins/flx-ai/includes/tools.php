<?php
/**
 * Araç kaydı. Kaynaklar:
 *  - Flux AI SDK: flx_ai_tool() (tools/ai-sdk; MCP + sohbet ya da yalnız biri),
 *  - flxai_tools filtresi (yalnız sohbet),
 *  - MCP araçları (flx_mcp_tools, eski adı flxseo_mcp_tools; mcp.php) otomatik gelir.
 * Toplama, kaynak tespiti, çakışma ve eski adlar: registry.php.
 *
 * Araç: name => array( title, description, schema (JSON Schema), readonly (bool), cap, run (callable $args, $ctx → array),
 *        source, confirm (bool: okuma aracı olsa da onay iste) ).
 * readonly olmayan her araç ortak sohbette çalışmadan önce kullanıcı onayı ister.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** Tüm kayıtlı araçlar (yetki ve kapalı listesi süzülmeden; çakışan ikinci tanımlar hariç, bkz. registry.php). */
function flxai_tools_all() {
	return flxai_chat_registry()['tools'];
}

/**
 * Aracın istediği yetki. Kayıtta cap yoksa (ya da boşsa) edit_posts: editör olmayanlar MCP'ye bağlanabildiği için
 * eski araçlar açıkta kalmasın.
 */
function flxai_tool_required_cap( array $tool ) {
	$cap = array_key_exists( 'cap', $tool ) && null !== $tool['cap'] ? (string) $tool['cap'] : '';
	return '' !== $cap ? $cap : 'edit_posts';
}

/**
 * Kullanıcı bu aracı görebilir ve çalıştırabilir mi? Sohbet ve MCP aynı fonksiyonu kullanır.
 * $gates: yönetici kapatması ve (MCP'de) anahtar kapsamı. Cap ve flx_mcp_tool_allowed burada.
 * Filtre true dönerse kapalı, kapsam dışı ya da yetkisiz aracı açmaz.
 *
 * @param bool $gates Ön koşullar (kapalı değil, MCP'de anahtar kapsamı uygun).
 */
function flxai_tool_allowed_for_user( $name, array $tool, $user_id = 0, $gates = true ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$base    = (bool) $gates && user_can( $user_id, flxai_tool_required_cap( $tool ) );
	$ok      = (bool) apply_filters( 'flx_mcp_tool_allowed', $base, (string) $name, $tool, $user_id );
	return $base && $ok;
}

/** Kullanıcının kullanabileceği, açık araçlar. */
function flxai_tools_for_user( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$off     = flxai_tools_off();
	$out     = array();
	foreach ( flxai_tools_all() as $name => $t ) {
		if ( ! flxai_tool_allowed_for_user( $name, $t, $user_id, ! in_array( $name, $off, true ) ) ) {
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

/**
 * Bu çağrı onay ister mi? Araç kuralına ek olarak çağrının girdisine bakılabilir
 * (ör. çalışma alanında kendi klasörüne yazma onaysızdır, site dosyası değişikliği PR'ye gider).
 *
 * @param array|null $chat Sohbet.
 */
function flxai_call_needs_approval( $name, array $t, $args, $chat = null ) {
	return (bool) apply_filters( 'flxai_call_needs_approval', flxai_tool_needs_approval( $t ), (string) $name, is_array( $args ) ? $args : array(), $chat );
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
 * @return array array( ok bool, result string (JSON), ms int, view array (görsel çıktı referansları) )
 */
function flxai_run_tool( $name, $args, array $ctx = array() ) {
	$name  = flxai_tool_resolve( $name );
	$tools = flxai_tools_for_user( (int) ( $ctx['user_id'] ?? 0 ) );
	$t0    = microtime( true );
	$ok    = false;
	if ( ! isset( $tools[ $name ] ) ) {
		/* translators: %s: tool name */
		$data = array( 'error' => sprintf( __( 'Tool not found or not permitted: %s', 'flx-ai' ), $name ) );
	} else {
		$t = $tools[ $name ];
		try {
			/**
			 * Aracı çalıştırmadan önce: null dışı dönüş sonuç olarak kullanılır, Exception hata olur
			 * (ör. çalışma alanı kuralları).
			 *
			 * @param mixed  $pre  null.
			 * @param string $name Araç.
			 * @param array  $args Girdi.
			 * @param array  $ctx  user_id, chat_id, turn, source, purpose.
			 */
			$pre = apply_filters( 'flxai_pre_run_tool', null, $name, is_array( $args ) ? $args : array(), $ctx );
			if ( null !== $pre ) {
				$data = $pre;
				$ok   = true;
			} elseif ( ! empty( $t['mcp'] ) ) {
				$r    = flxai_mcp_call( $name, is_array( $args ) ? $args : array() );
				$text = (string) ( $r['content'][0]['text'] ?? '' );
				$dec  = json_decode( $text, true );
				$data = null !== $dec ? $dec : $text;
				$ok   = empty( $r['isError'] );
			} else {
				$data = call_user_func( $t['run'], is_array( $args ) ? $args : array(), $ctx );
				$ok   = true;
			}
			if ( $ok ) {
				/**
				 * Başarılı araç sonucunu değiştir/zenginleştir (ör. taslak için önizleme bağlantısı).
				 *
				 * @param mixed  $data Sonuç.
				 * @param string $name Araç.
				 * @param array  $args Girdi.
				 * @param array  $ctx  user_id, chat_id, turn, source, purpose.
				 */
				$data = apply_filters( 'flxai_tool_result', $data, $name, is_array( $args ) ? $args : array(), $ctx );
			}
		} catch ( Throwable $e ) {
			$data = array( 'error' => $e->getMessage() );
		}
	}
	$ms   = (int) round( ( microtime( true ) - $t0 ) * 1000 );
	// Görsel çıktı (_view): modele sonraki çağrıda görsel olarak gider, JSON'a girmez (0.21.0).
	$view = array();
	if ( is_array( $data ) && array_key_exists( '_view', $data ) ) {
		$view = $ok && function_exists( 'flxai_img_view_list' ) ? flxai_img_view_list( $data['_view'] ) : array();
		unset( $data['_view'] );
	}
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
	return array( 'ok' => $ok, 'result' => (string) $json, 'ms' => $ms, 'view' => $view );
}

/** Flux AI'nin kendi araçları. */
function flxai_core_tools() {
	return array(
		'flxai_usage'        => array(
			'title'       => __( 'AI usage', 'flx-ai' ),
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
			'title'       => __( 'Date and time', 'flx-ai' ),
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
