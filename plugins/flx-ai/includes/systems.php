<?php
/**
 * Bağlı sistemler (Flux AI'yı kullanan eklentiler) ve istek/cevap içerik kayıtları (maskeli).
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Flux AI'yı kullanan bilinen sistemler. Diğer paketler `flxai_systems` ile kendini ekler:
 * 'kaynak' => array( 'label' => ..., 'plugin' => 'klasor/dosya.php', 'page' => 'admin.php?page=...' ).
 */
function flxai_known_systems() {
	return (array) apply_filters(
		'flxai_systems',
		array(
			'flx-ai'            => array(
				'label'  => __( 'Flux AI sohbet', 'flx-ai' ),
				'plugin' => '',
				'page'   => 'admin.php?page=flx-ai-settings#chat',
			),
			'flx-chat' => array(
				'label'  => 'Flux Chat',
				'plugin' => 'flx-chat/flx-chat.php',
				'page'   => 'admin.php?page=flx-chat',
			),
			'flx-tasks'         => array(
				'label'  => 'Flux Tasks',
				'plugin' => 'flx-tasks/flx-tasks.php',
				'page'   => 'admin.php?page=flx-tasks',
			),
		)
	);
}

/** Bu sistem için yapay zekâ açık mı (Flux AI & MCP › Bağlı sistemler). */
function flxai_source_enabled( $source ) {
	return ! in_array( sanitize_key( (string) $source ), (array) flxai_setting( 'sources_off', array() ), true );
}

/**
 * Bağlı sistemler listesi: bilinenler + son 90 günde kayıt bırakan kaynaklar, 30 günlük kullanım ile.
 *
 * @return array[] source, label, installed, active, page, on, model, calls, tools, cost, errors, last
 */
function flxai_systems() {
	global $wpdb;
	$t     = flxai_table( 'log' );
	$since = time() - 30 * DAY_IN_SECONDS;
	$stats = array();
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT source, SUM(kind='model') AS calls, SUM(kind='tool') AS tools, SUM(cost) AS cost, SUM(ok=0) AS errors, SUM(in_tok+out_tok) AS tok, MAX(ts) AS last FROM {$t} WHERE ts >= %d GROUP BY source", $since ), ARRAY_A ) as $r ) { // phpcs:ignore
		$stats[ $r['source'] ] = $r;
	}
	$seen = (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT source FROM {$t} WHERE ts >= %d", time() - 90 * DAY_IN_SECONDS ) ); // phpcs:ignore
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$known = flxai_known_systems();
	$ids   = array_unique( array_merge( array_keys( $known ), array_filter( array_map( 'strval', $seen ) ) ) );
	$out   = array();
	foreach ( $ids as $id ) {
		$k      = $known[ $id ] ?? array();
		$plugin = (string) ( $k['plugin'] ?? '' );
		$s      = $stats[ $id ] ?? array();
		$out[]  = array(
			'source'    => $id,
			'label'     => (string) ( $k['label'] ?? flxai_source_label( $id ) ),
			'installed' => '' === $plugin || file_exists( WP_PLUGIN_DIR . '/' . $plugin ),
			'active'    => '' === $plugin || is_plugin_active( $plugin ),
			'page'      => (string) ( $k['page'] ?? '' ),
			'on'        => flxai_source_enabled( $id ),
			'model'     => flxai_model_for( 'unknown' === $id ? '' : $id ),
			'calls'     => (int) ( $s['calls'] ?? 0 ),
			'tools'     => (int) ( $s['tools'] ?? 0 ),
			'tok'       => (int) ( $s['tok'] ?? 0 ),
			'cost'      => (float) ( $s['cost'] ?? 0 ),
			'errors'    => (int) ( $s['errors'] ?? 0 ),
			'last'      => (int) ( $s['last'] ?? 0 ),
		);
	}
	return $out;
}

/* ---------------------------------------------------------------------------
 * İçerik kayıtları (giden / gelen), maskeli
 * ------------------------------------------------------------------------- */

/**
 * Kişisel veri ve gizli bilgileri maskeler: e-posta, telefon / kart / TC (10+ hane), IBAN, API anahtarları.
 * Filtre `flxai_log_mask` ek kurallar için.
 */
function flxai_mask_text( $s ) {
	$s = (string) $s;
	// API anahtarı / token benzeri.
	$s = preg_replace( '/\b(?:sk-(?:ant-|or-)?[A-Za-z0-9_\-]{8,}|wrkspc_[A-Za-z0-9]+|Bearer\s+[A-Za-z0-9._\-]{12,}|[A-Za-z0-9_\-]{40,})\b/', '[gizli]', $s );
	// IBAN: ülke + kontrol + son 4 hane görünür.
	$s = preg_replace_callback(
		'/\b([A-Z]{2})\d{2}(?:[ ]?[0-9A-Z]{4}){3,7}(?:[ ]?[0-9A-Z]{1,4})?\b/',
		function ( $m ) {
			$raw = preg_replace( '/\s+/', '', $m[0] );
			return strlen( $raw ) < 15 ? $m[0] : $m[1] . '** **** ' . substr( $raw, -4 );
		},
		$s
	);
	// E-posta: ilk harf + alan adının ilk harfi.
	$s = preg_replace( '/([A-Za-z0-9])[A-Za-z0-9._%+\-]*@([A-Za-z0-9])[A-Za-z0-9\-]*((?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,})/', '$1***@$2***$3', $s );
	// 10+ haneli numaralar (telefon, kart, TC): son 2 hane görünür.
	$s = preg_replace_callback(
		'/(?<![\w])\+?\d[\d \-().]{8,}\d(?![\w])/',
		function ( $m ) {
			$digits = preg_replace( '/\D/', '', $m[0] );
			if ( strlen( $digits ) < 10 || preg_match( '/^\d{4}-\d{2}-\d{2}/', $m[0] ) ) { // tarih değil
				return $m[0];
			}
			return str_repeat( '•', strlen( $digits ) - 2 ) . substr( $digits, -2 );
		},
		$s
	);
	return (string) apply_filters( 'flxai_log_mask', $s );
}

/** Mesaj içeriğini düz metne çevirir (parçalı içerik, görseller). */
function flxai_flatten_content( $c ) {
	if ( is_string( $c ) ) {
		return $c;
	}
	$out = array();
	foreach ( (array) $c as $part ) {
		if ( is_array( $part ) && isset( $part['text'] ) ) {
			$out[] = (string) $part['text'];
		} elseif ( is_array( $part ) ) {
			$out[] = '[' . (string) ( $part['type'] ?? 'part' ) . ']';
		}
	}
	return implode( "\n", $out );
}

function flxai_clip( $s, $n ) {
	$s = (string) $s;
	return mb_strlen( $s ) > $n ? mb_substr( $s, 0, $n ) . ' …' : $s;
}

/**
 * İsteğin ve cevabın kısaltılmış, maskeli kopyasını saklar (Ayarlar › Kayıtlar › İçerik kaydı).
 *
 * @param int            $log_id   flxai_log kimliği.
 * @param array          $messages Giden mesajlar.
 * @param array|WP_Error $res      Gelen cevap.
 */
function flxai_log_body( $log_id, array $messages, $res ) {
	if ( ! $log_id || ! flxai_setting( 'log_bodies', 1 ) ) {
		return;
	}
	$req    = array();
	$system = '';
	foreach ( $messages as $m ) {
		$role = (string) ( $m['role'] ?? '' );
		$text = flxai_flatten_content( $m['content'] ?? '' );
		if ( 'system' === $role ) {
			$system .= ( '' !== $system ? "\n" : '' ) . $text;
			continue;
		}
		if ( ! empty( $m['tool_calls'] ) ) {
			foreach ( (array) $m['tool_calls'] as $tc ) {
				$text .= "\n→ " . (string) ( $tc['function']['name'] ?? '' ) . ' ' . flxai_clip( (string) ( $tc['function']['arguments'] ?? '' ), 300 );
			}
		}
		$req[] = array( $role, flxai_mask_text( flxai_clip( trim( $text ), 'tool' === $role ? 800 : 2000 ) ) );
	}
	$req = array_slice( $req, -8 );
	if ( '' !== $system ) {
		/* translators: %s: character count */
		array_unshift( $req, array( 'system', sprintf( __( '%s karakter sistem talimatı', 'flx-ai' ), number_format_i18n( mb_strlen( $system ) ) ) . ":\n" . flxai_mask_text( flxai_clip( $system, 400 ) ) ) );
	}
	if ( is_wp_error( $res ) ) {
		$out = array( 'error' => flxai_mask_text( $res->get_error_message() ) );
	} else {
		$out = array( 'text' => flxai_mask_text( flxai_clip( (string) ( $res['content'] ?? '' ), 6000 ) ) );
		foreach ( (array) ( $res['tool_calls'] ?? array() ) as $tc ) {
			$out['tools'][] = (string) ( $tc['function']['name'] ?? '' ) . ' ' . flxai_mask_text( flxai_clip( (string) ( $tc['function']['arguments'] ?? '' ), 500 ) );
		}
	}
	global $wpdb;
	$wpdb->insert( // phpcs:ignore
		flxai_table( 'log_body' ),
		array(
			'log_id' => (int) $log_id,
			'ts'     => time(),
			'req'    => wp_json_encode( $req ),
			'res'    => wp_json_encode( $out ),
		)
	);
}

/** Tek kaydın içeriği (yönetici). */
function flxai_log_body_get( $log_id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT req, res FROM ' . flxai_table( 'log_body' ) . ' WHERE log_id = %d', (int) $log_id ), ARRAY_A ); // phpcs:ignore
	if ( ! $row ) {
		return null;
	}
	return array(
		'req' => (array) json_decode( (string) $row['req'], true ),
		'res' => (array) json_decode( (string) $row['res'], true ),
	);
}
