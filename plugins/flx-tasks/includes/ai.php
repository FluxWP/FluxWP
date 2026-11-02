<?php
/**
 * Yapay zekâ: görev için hızlı işlemler ve araç kullanan sohbet asistanı.
 * Tüm model çağrıları Flux AI eklentisi üzerinden yapılır (anahtar, model, harcama sınırı ve raporlar orada).
 * Flux AI kurulu ya da hazır değilse yapay zekâ özellikleri kapalıdır; panel ve sistem kontrolü uyarır.
 */

defined( 'ABSPATH' ) || exit;

/** Flux AI kurulu mu (sürümü sohbet API'sini içeriyor mu)? */
function flxg_ai_hub() {
	return function_exists( 'flxai_chat' ) && function_exists( 'flxai_ready' );
}

/** Yapay zekâ kullanılabilir mi: Flux AI kurulu ve bu eklenti için seçilen modelin anahtarı var. */
function flxg_ai_ready() {
	return flxg_ai_hub() && flxai_ready( 'flx-tasks' );
}

/** Bu eklenti için kullanılacak model (Flux AI'de "Eklentiye özel model" ya da varsayılan). */
function flxg_ai_model() {
	return function_exists( 'flxai_model_for' ) ? (string) flxai_model_for( 'flx-tasks' ) : '';
}

/** Yapay zekâ kapalıyken kullanıcıya gösterilen neden. */
function flxg_ai_off_message() {
	if ( ! flxg_ai_hub() ) {
		return __( 'Yapay zekâ için Flux AI eklentisi gerekli. Yönetici Flux AI\'yi kurup anahtar girmeli.', 'flx-tasks' );
	}
	return __( 'Yapay zekâ ayarlanmamış. Yönetici Flux AI › Ayarlar\'dan anahtar ve model seçmeli.', 'flx-tasks' );
}

/**
 * Eski kurulumdan geçiş (bir kerelik): Flux Tasks'e girilmiş OpenRouter anahtarı ve model Flux AI'ye aktarılır,
 * Flux AI'de aynısı varsa buradaki kopya silinir. Flux AI'de farklı bir anahtar varsa ona dokunulmaz, buradaki silinir
 * (artık kullanılmıyor). Flux AI kurulu değilse ayar olduğu gibi bekler.
 */
function flxg_ai_migrate() {
	$raw = get_option( 'flxg_settings', array() );
	$raw = is_array( $raw ) ? $raw : array();
	$key = trim( (string) ( $raw['ai_key'] ?? '' ) );
	$mod = trim( (string) ( $raw['ai_model'] ?? '' ) );
	if ( '' === $key && '' === $mod ) {
		return array();
	}
	if ( ! function_exists( 'flxai_import_config' ) ) {
		return array();
	}
	$done = (array) flxai_import_config( 'flx-tasks', array_filter( array( 'key_openrouter' => $key, 'source_model' => $mod ), 'strlen' ) );
	unset( $raw['ai_key'], $raw['ai_model'] );
	update_option( 'flxg_settings', $raw, false );
	flxg_settings( true );
	delete_transient( 'flxg_ai_models' );
	return $done;
}
add_action( 'admin_init', 'flxg_ai_migrate' );

/**
 * @return array|WP_Error assistant mesajı (content, tool_calls, _usage, _raw…)
 */
function flxg_ai_chat_raw( array $messages, array $opts = array() ) {
	if ( ! flxg_ai_ready() ) {
		return flxg_error( 'flxg_ai_off', flxg_ai_off_message(), 400 );
	}
	$o = array(
		'source'     => 'flx-tasks',
		'purpose'    => (string) ( $opts['purpose'] ?? 'assistant' ),
		'max_tokens' => (int) ( $opts['max_tokens'] ?? 1200 ),
		'timeout'    => (int) ( $opts['timeout'] ?? 55 ),
	);
	if ( ! empty( $opts['tools'] ) ) {
		$o['tools'] = $opts['tools'];
	}
	if ( isset( $opts['temperature'] ) || '' !== (string) flxg_setting( 'ai_temperature' ) ) {
		$o['temperature'] = (float) ( $opts['temperature'] ?? flxg_setting( 'ai_temperature' ) );
	}
	$r = flxai_chat( $messages, $o );
	if ( is_wp_error( $r ) ) {
		// Harcama sınırı, anahtar, kapalı sistem gibi hesap hataları 402: asistan döngüsü ilk hatada durur.
		$status = function_exists( 'flxai_is_account_error' ) && flxai_is_account_error( $r ) ? 402 : 502;
		return flxg_error( 'flxg_ai_api', $r->get_error_message(), $status );
	}
	$u = is_array( $r['_usage'] ?? null ) ? $r['_usage'] : array();
	flxg_ai_track_usage( array( 'total_tokens' => (int) ( $u['in'] ?? 0 ) + (int) ( $u['out'] ?? 0 ), 'cost' => (float) ( $u['cost'] ?? 0 ) ) );
	return $r;
}

function flxg_ai_track_usage( $u ) {
	$month = gmdate( 'Y-m' );
	$s     = get_option( 'flxg_ai_usage', array() );
	if ( ! is_array( $s ) || ( $s['month'] ?? '' ) !== $month ) {
		$s = array( 'month' => $month, 'calls' => 0, 'tokens' => 0, 'cost' => 0.0 );
	}
	$s['calls']++;
	$s['tokens'] += (int) ( $u['total_tokens'] ?? 0 );
	$s['cost']   += (float) ( $u['cost'] ?? 0 );
	update_option( 'flxg_ai_usage', $s, false );
}

/** Kullanıcının dili (yanıt dili). */
function flxg_ai_lang() {
	$l = get_user_locale();
	return 0 === strpos( $l, 'tr' ) ? 'Turkish' : ( 0 === strpos( $l, 'en' ) ? 'English' : $l );
}

function flxg_ai_system_base() {
	$extra = trim( (string) flxg_setting( 'ai_instructions' ) );
	return 'You are the assistant of the task tracker "Flux Tasks" used by ' . flxg_brand_name() . '. '
		. 'Answer in ' . flxg_ai_lang() . ' unless the user writes in another language. Be brief and practical. '
		. 'Today is ' . wp_date( 'l, Y-m-d H:i' ) . ' (' . wp_timezone_string() . '). The user is ' . flxg_user_name( get_current_user_id() ) . ' (id ' . get_current_user_id() . ').'
		. ( '' !== $extra ? "\n\nOrganization notes:\n" . $extra : '' );
}

function flxg_ai_task_context( $task_id ) {
	$t = flxg_tool_get_task( array( 'task_id' => $task_id ) );
	return wp_json_encode( $t, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
}

/**
 * Görev için hızlı işlem.
 *
 * @param string $action title|description|subtasks|summary
 * @return array|WP_Error
 */
function flxg_ai_task_action( $task_id, $action, $hint = '' ) {
	$ctx   = flxg_ai_task_context( $task_id );
	$hint  = trim( (string) $hint );
	$tasks = array(
		'title'       => 'Suggest a better task title: short (max ~80 chars), clear, action-oriented, same language as the task. Return ONLY the title text.',
		'description' => 'Write a clear task description from the information available (improve the existing one if any). Use simple HTML: <p>, <ul><li>, <strong>. Include goal, steps/checklist and acceptance notes when useful. Do not invent facts like names, prices or dates. Return ONLY the HTML.',
		'subtasks'    => 'Break this task into 3-8 concrete subtasks. Return ONLY the subtask titles, one per line, no numbering or bullets.',
		'summary'     => 'Summarize the task status, the discussion in the comments, decisions and open questions in a few short bullet lines (plain text, lines starting with "• ").',
	);
	if ( ! isset( $tasks[ $action ] ) ) {
		return flxg_error( 'flxg_ai_action', 'Unknown action' );
	}
	$msg = flxg_ai_chat_raw(
		array(
			array( 'role' => 'system', 'content' => flxg_ai_system_base() ),
			array( 'role' => 'user', 'content' => "Task (JSON):\n" . $ctx . "\n\n" . $tasks[ $action ] . ( '' !== $hint ? "\n\nExtra instruction from the user: " . $hint : '' ) ),
		),
		array( 'max_tokens' => 'description' === $action ? 1500 : 600, 'purpose' => $action )
	);
	if ( is_wp_error( $msg ) ) {
		return $msg;
	}
	$text = trim( (string) ( $msg['content'] ?? '' ) );
	$text = preg_replace( '/^```[a-z]*\s*|\s*```$/i', '', $text );
	switch ( $action ) {
		case 'title':
			return array( 'text' => trim( $text, " \t\n\r\"'“”" ) );
		case 'description':
			if ( false === strpos( $text, '<' ) ) {
				$text = wpautop( esc_html( $text ) );
			}
			return array( 'html' => flxg_kses_desc( $text ) );
		case 'subtasks':
			$items = array();
			foreach ( preg_split( '/\r?\n/', $text ) as $line ) {
				$line = trim( preg_replace( '/^\s*(?:[-*•]|\d+[.)])\s*/u', '', $line ) );
				if ( '' !== $line ) {
					$items[] = mb_substr( $line, 0, 300 );
				}
			}
			return array( 'items' => array_slice( $items, 0, 12 ) );
		default:
			return array( 'text' => $text );
	}
}

/** Araçları OpenAI "tools" biçimine çevirir. */
function flxg_ai_tool_defs() {
	$out = array();
	foreach ( flxg_tools() as $name => $t ) {
		$schema = $t['schema'];
		if ( isset( $schema['properties'] ) && $schema['properties'] instanceof stdClass ) {
			$schema['properties'] = new stdClass();
		}
		$out[] = array(
			'type'     => 'function',
			'function' => array(
				'name'        => $name,
				'description' => $t['description'],
				'parameters'  => $schema,
			),
		);
	}
	return $out;
}

/**
 * Araç kullanan sohbet.
 *
 * @param array $history [{role: user|assistant, content}]
 * @param array $context { list_id, task_id }
 * @return array|WP_Error { reply, actions[] }
 */
function flxg_ai_chat( array $history, array $context = array() ) {
	$sys = flxg_ai_system_base() . "\n\n"
		. "You can read and change tasks with the provided tools; they run with the user's permissions. "
		. 'When the user asks to create or change things, do it with tools, then confirm briefly what you did with task ids (#id). '
		. 'Use list_projects to find list ids and statuses when needed. Never delete tasks unless the user explicitly asked. '
		. 'Mention people in comments as @{user_id}. '
		. 'Documents: list_docs, get_doc_page (blocks with ids), edit_doc_page (replace/insert/delete blocks), create_doc, create_doc_page; read a page before editing it.';
	if ( ! empty( $context['doc_page'] ) ) {
		$dp  = flxg_get_page( (int) $context['doc_page'] );
		$ddc = flxg_page_doc( $dp );
		if ( $ddc && flxg_can_doc( $ddc ) ) {
			$sys .= "\n\nThe user has document page #" . (int) $dp['id'] . ' "' . ( '' !== (string) $dp['title'] ? $dp['title'] : '(untitled)' ) . '" of document "' . $ddc['title'] . '" open. "This page/document" means it' . ( flxg_can_doc( $ddc, 'edit' ) ? '' : ' (read only for this user)' ) . '.';
		}
	}
	if ( ! empty( $context['list_id'] ) && flxg_can_list( (int) $context['list_id'] ) ) {
		$l    = flxg_get_list( (int) $context['list_id'] );
		$sys .= "\n\nThe user is currently looking at list #" . $l['id'] . ' "' . $l['name'] . '" (statuses: ' . implode( ', ', wp_list_pluck( $l['statuses'], 'label' ) ) . '). "This list" means this one.';
	}
	if ( ! empty( $context['task_id'] ) && flxg_can_task( (int) $context['task_id'] ) ) {
		$sys .= "\n\nThe user has task #" . (int) $context['task_id'] . " open. \"This task\" means:\n" . flxg_ai_task_context( (int) $context['task_id'] );
	}
	$messages = array( array( 'role' => 'system', 'content' => $sys ) );
	foreach ( array_slice( $history, -20 ) as $m ) {
		$role = 'assistant' === ( $m['role'] ?? '' ) ? 'assistant' : 'user';
		$text = mb_substr( (string) ( $m['content'] ?? '' ), 0, 8000 );
		if ( '' !== trim( $text ) ) {
			$messages[] = array( 'role' => $role, 'content' => $text );
		}
	}
	$tools   = flxg_ai_tool_defs();
	$actions = array();
	for ( $i = 0; $i < 8; $i++ ) {
		$msg = flxg_ai_chat_raw( $messages, array( 'tools' => $tools, 'max_tokens' => 1500, 'purpose' => 'assistant' ) );
		if ( is_wp_error( $msg ) ) {
			return $msg;
		}
		$calls = (array) ( $msg['tool_calls'] ?? array() );
		if ( ! $calls ) {
			return array(
				'reply'   => trim( (string) ( $msg['content'] ?? '' ) ),
				'actions' => $actions,
			);
		}
		$am = array(
			'role'       => 'assistant',
			'content'    => (string) ( $msg['content'] ?? '' ),
			'tool_calls' => $calls,
		);
		// Flux AI + Claude: ham cevap geri verilirse düşünme blokları sonraki turda korunur.
		if ( isset( $msg['_raw'] ) ) {
			$am['_raw']       = $msg['_raw'];
			$am['_raw_model'] = $msg['_raw_model'] ?? '';
		}
		$messages[] = $am;
		foreach ( $calls as $c ) {
			$name = (string) ( $c['function']['name'] ?? '' );
			$args = json_decode( (string) ( $c['function']['arguments'] ?? '{}' ), true );
			list( $ok, $data ) = flxg_run_tool( $name, is_array( $args ) ? $args : array() );
			$tool_info         = flxg_tools()[ $name ] ?? array();
			if ( empty( $tool_info['readonly'] ) ) {
				$actions[] = array(
					'tool'  => $name,
					'ok'    => $ok,
					'task'  => (int) ( $data['created']['id'] ?? ( $data['updated']['id'] ?? ( $data['task_id'] ?? ( $data['deleted'] ?? 0 ) ) ) ),
					'page'  => (int) ( $data['page_id'] ?? 0 ),
					'doc'   => (int) ( $data['doc_id'] ?? 0 ),
					'title' => (string) ( $data['created']['title'] ?? ( $data['updated']['title'] ?? '' ) ),
					'error' => $ok ? '' : (string) ( $data['error'] ?? '' ),
				);
			}
			$messages[] = array(
				'role'         => 'tool',
				'tool_call_id' => (string) ( $c['id'] ?? '' ),
				'content'      => mb_substr( wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), 0, 30000 ),
			);
		}
	}
	return array(
		'reply'   => __( 'İşlem çok uzun sürdü; isteği daha küçük adımlara bölün.', 'flx-tasks' ),
		'actions' => $actions,
	);
}
