<?php
/**
 * Ortak API: diğer FluxWP eklentileri yapay zekâyı buradan çağırır (belge: API.md).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sohbet tamamlama isteği.
 *
 * @param array $messages OpenAI biçimi: [{role: system|user|assistant|tool, content, tool_calls?, tool_call_id?}]
 *                        Sistem mesajında içerik parçası {type:text, text, cache:true} önbellek kırılma noktasıdır.
 * @param array $opts {
 *     source      string  Çağıran eklenti (flx-chat, flx-tasks …). Raporlar, kaynak modeli ve Bağlı sistemler aç/kapat için.
 *     purpose     string  Kısa amaç etiketi (bot, suggest, title, receipt …).
 *     model       string  Boşsa kaynak modeli → varsayılan model.
 *     tools       array   OpenAI biçimi araç tanımları.
 *     max_tokens  int     Görünen cevap sınırı (varsayılan 1024).
 *     temperature float   Destekleyen modellerde.
 *     timeout     int     Saniye (varsayılan 45; Hostinger ~60 sn'de keser).
 *     cache       string  off|5m|1h (boşsa ayar).
 *     effort      string  low|medium|high (Claude).
 *     thinking    bool    Düşünme özetini iste (_thinking alanında döner).
 *     user_id     int     Kullanıcı (varsayılan geçerli kullanıcı).
 *     chat_id     int     Flux AI sohbeti (iç kullanım).
 *     ignore_limit bool   Harcama sınırını yok say (yalnız yönetici testleri).
 *     stream      callable Cevap parça parça gelir: fn( array( 't' => text|think|tool, 'd' => parça, 'id', 'name' ) ). Dönüş aynıdır.
 *     stop_check  callable Akışta ~1,5 sn'de bir sorulur; true dönerse istek kesilir (WP_Error flxai_stopped, data.partial).
 * }
 * @return array|WP_Error Asistan mesajı: content, tool_calls?, _thinking?, _finish, _usage{provider,model,in,out,cr,cw,cost,full,est,ms}, _log_id
 */
function flxai_chat( array $messages, array $opts = array() ) {
	$source = sanitize_key( (string) ( $opts['source'] ?? '' ) );
	$source = '' !== $source ? $source : 'unknown';
	$model  = flxai_model_for( $source, (string) ( $opts['model'] ?? '' ) );
	if ( ! flxai_source_enabled( $source ) ) {
		flxai_log(
			array(
				'source'  => $source,
				'purpose' => (string) ( $opts['purpose'] ?? '' ),
				'model'   => $model,
				'ok'      => 0,
				'error'   => 'off',
				'chat_id' => (int) ( $opts['chat_id'] ?? 0 ),
			)
		);
		/* translators: %s: plugin name */
		return new WP_Error( 'flxai_source_off', sprintf( __( '%s için yapay zekâ kapalı (Flux AI & MCP › Bağlı sistemler).', 'flx-ai' ), flxai_source_label( $source ) ) );
	}
	if ( '' === $model ) {
		return new WP_Error( 'flxai_model', __( 'Yapay zekâ modeli seçilmemiş (Flux AI & MCP › Ayarlar).', 'flx-ai' ) );
	}
	$ok = flxai_budget_check( $opts );
	if ( is_wp_error( $ok ) ) {
		flxai_log(
			array(
				'source'  => $source,
				'purpose' => (string) ( $opts['purpose'] ?? '' ),
				'model'   => $model,
				'ok'      => 0,
				'error'   => 'limit',
				'chat_id' => (int) ( $opts['chat_id'] ?? 0 ),
			)
		);
		return $ok;
	}
	$messages = (array) apply_filters( 'flxai_request_messages', $messages, $opts );
	$p        = flxai_parse_model( $model );
	$t0       = microtime( true );
	$res      = 'anthropic' === $p['provider']
		? flxai_anthropic_request( $messages, $opts, $p['id'] )
		: flxai_openrouter_request( $messages, $opts, $p['id'] );
	$ms       = (int) round( ( microtime( true ) - $t0 ) * 1000 );
	$u        = is_wp_error( $res ) ? array() : (array) ( $res['_usage'] ?? array() );
	$log_id   = flxai_log(
		array(
			'source'      => $source,
			'purpose'     => (string) ( $opts['purpose'] ?? '' ),
			'user_id'     => (int) ( $opts['user_id'] ?? get_current_user_id() ),
			'chat_id'     => (int) ( $opts['chat_id'] ?? 0 ),
			'provider'    => $p['provider'],
			'model'       => $p['full'],
			'in_tok'      => (int) ( $u['in'] ?? 0 ),
			'out_tok'     => (int) ( $u['out'] ?? 0 ),
			'cache_read'  => (int) ( $u['cr'] ?? 0 ),
			'cache_write' => (int) ( $u['cw'] ?? 0 ),
			'cost'        => (float) ( $u['cost'] ?? 0 ),
			'full_cost'   => (float) ( $u['full'] ?? 0 ),
			'est'         => ! empty( $u['est'] ) ? 1 : 0,
			'ms'          => $ms,
			'ok'          => is_wp_error( $res ) ? 0 : 1,
			'error'       => is_wp_error( $res ) ? $res->get_error_message() : '',
		)
	);
	flxai_log_body( $log_id, $messages, $res );
	if ( is_wp_error( $res ) ) {
		do_action( 'flxai_request_failed', $res, $opts );
		return $res;
	}
	$res['_usage']['ms'] = $ms;
	$res['_log_id']      = $log_id;
	do_action( 'flxai_request_done', $res, $opts );
	return $res;
}

/**
 * Kısa yol: tek istem → metin.
 *
 * @return string|WP_Error
 */
function flxai_complete( $prompt, array $opts = array() ) {
	$msgs = array();
	if ( ! empty( $opts['system'] ) ) {
		$msgs[] = array( 'role' => 'system', 'content' => (string) $opts['system'] );
	}
	$msgs[] = array( 'role' => 'user', 'content' => (string) $prompt );
	$r      = flxai_chat( $msgs, $opts );
	return is_wp_error( $r ) ? $r : trim( (string) $r['content'] );
}

/**
 * Kayıt satırı (model çağrısı ya da araç). Diğer eklentiler kendi araç sürelerini de yazabilir:
 * flxai_log( array( 'kind' => 'tool', 'source' => 'flx-chat', 'tool' => 'donation_options', 'ms' => 120 ) ).
 *
 * @return int Kayıt kimliği.
 */
function flxai_log( array $row ) {
	global $wpdb;
	$data = array(
		'ts'          => time(),
		'kind'        => in_array( $row['kind'] ?? 'model', array( 'model', 'tool' ), true ) ? $row['kind'] ?? 'model' : 'model',
		'source'      => substr( sanitize_key( (string) ( $row['source'] ?? '' ) ), 0, 60 ),
		'purpose'     => substr( sanitize_key( (string) ( $row['purpose'] ?? '' ) ), 0, 60 ),
		'user_id'     => (int) ( $row['user_id'] ?? get_current_user_id() ),
		'chat_id'     => (int) ( $row['chat_id'] ?? 0 ),
		'provider'    => substr( (string) ( $row['provider'] ?? '' ), 0, 20 ),
		'model'       => substr( (string) ( $row['model'] ?? '' ), 0, 120 ),
		'tool'        => substr( (string) ( $row['tool'] ?? '' ), 0, 80 ),
		'in_tok'      => max( 0, (int) ( $row['in_tok'] ?? 0 ) ),
		'out_tok'     => max( 0, (int) ( $row['out_tok'] ?? 0 ) ),
		'cache_read'  => max( 0, (int) ( $row['cache_read'] ?? 0 ) ),
		'cache_write' => max( 0, (int) ( $row['cache_write'] ?? 0 ) ),
		'cost'        => max( 0, (float) ( $row['cost'] ?? 0 ) ),
		'full_cost'   => max( 0, (float) ( $row['full_cost'] ?? 0 ) ),
		'est'         => ! empty( $row['est'] ) ? 1 : 0,
		'ms'          => max( 0, (int) ( $row['ms'] ?? 0 ) ),
		'ok'          => isset( $row['ok'] ) ? ( $row['ok'] ? 1 : 0 ) : 1,
		'error'       => mb_substr( (string) ( $row['error'] ?? '' ), 0, 250 ),
	);
	$wpdb->insert( flxai_table( 'log' ), $data ); // phpcs:ignore
	$id = (int) $wpdb->insert_id;
	if ( 'model' === $data['kind'] && $data['cost'] > 0 ) {
		flxai_budget_after_log();
	}
	return $id;
}

/**
 * Hesap düzeyinde hata mı (anahtar, kredi, bütçe, hız sınırı, Flux AI harcama sınırı)?
 * Bu hatalarda sonraki istekler de reddedilir; toplu işler durmalı.
 */
function flxai_is_account_error( $e ) {
	if ( ! is_wp_error( $e ) ) {
		return false;
	}
	if ( in_array( $e->get_error_code(), array( 'flxai_limit', 'flxai_key', 'flxai_source_off' ), true ) ) {
		return true;
	}
	$d    = (array) $e->get_error_data();
	$code = (int) ( $d['http'] ?? 0 );
	if ( in_array( $code, array( 401, 402, 429 ), true ) ) {
		return true;
	}
	if ( 400 === $code && preg_match( '/credit balance|billing/i', (string) ( $d['msg'] ?? '' ) ) ) {
		return true;
	}
	return 403 === $code && preg_match( '/budget|credit|limit|quota|exceeded/i', (string) ( $d['msg'] ?? '' ) );
}

/** Ortak model seçici betiği (diğer eklentilerin ayar sayfaları da kullanabilir). */
function flxai_enqueue_model_picker() {
	wp_enqueue_script( 'flxai-model-picker', FLXAI_URL . 'assets/js/model-picker.js', array(), FLXAI_VERSION, true );
	wp_enqueue_style( 'flxai-admin', FLXAI_URL . 'assets/css/admin.css', array(), FLXAI_VERSION );
	wp_localize_script(
		'flxai-model-picker',
		'FLXAI_MP',
		array(
			'rest'  => esc_url_raw( rest_url( 'flx-ai/v1/' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'i18n'  => flxai_js_strings(),
		)
	);
}
