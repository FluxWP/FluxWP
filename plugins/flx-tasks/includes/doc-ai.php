<?php
/**
 * Dokümanlarda yapay zekâ:
 *  - Belge araçları (list_docs, get_doc_page, edit_doc_page, create_doc, create_doc_page): Flux Tasks asistanı, MCP ve
 *    ortak Flux AI sohbeti aynı araçları kullanır. Ortak sohbete tüm Flux Tasks araçları "flxg_" önekiyle kaydedilir.
 *  - Belge ekranındaki hızlı işlemler (seçili metni iyileştir, çevir, sayfayı özetle…): POST doc-pages/{id}/ai.
 * Model çağrıları yalnız Flux AI üzerinden (flxg_ai_chat_raw).
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * HTML ⇄ blok
 * ====================================================================== */

function flxg_doc_new_bid() {
	return substr( bin2hex( random_bytes( 5 ) ), 0, 9 ) . base_convert( (string) time(), 10, 36 );
}

/** Düz metin ya da basit markdown → HTML (model bazen HTML yerine markdown yazar). */
function flxg_md_to_html( $text ) {
	$inline = function ( $s ) {
		$s = esc_html( $s );
		$s = preg_replace( '/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s );
		$s = preg_replace( '/(?<![\*\w])\*(?!\s)(.+?)(?<!\s)\*(?!\*)/u', '<em>$1</em>', $s );
		$s = preg_replace( '/`([^`]+)`/u', '<code>$1</code>', $s );
		return preg_replace( '/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/u', '<a href="$2" target="_blank" rel="noopener">$1</a>', $s );
	};
	$out  = '';
	$list = '';
	$para = array();
	$flush_para = function () use ( &$out, &$para, $inline ) {
		if ( $para ) {
			$out .= '<p>' . implode( '<br>', array_map( $inline, $para ) ) . '</p>';
			$para = array();
		}
	};
	$close_list = function () use ( &$out, &$list ) {
		if ( $list ) {
			$out .= 'ol' === $list ? '</ol>' : '</ul>';
			$list = '';
		}
	};
	foreach ( preg_split( '/\r?\n/', trim( (string) $text ) ) as $line ) {
		$l = rtrim( $line );
		if ( '' === trim( $l ) ) {
			$flush_para();
			$close_list();
			continue;
		}
		if ( preg_match( '/^(#{1,4})\s+(.+)$/u', $l, $m ) ) {
			$flush_para();
			$close_list();
			$n    = max( 1, min( 3, strlen( $m[1] ) ) );
			$out .= '<h' . $n . '>' . $inline( $m[2] ) . '</h' . $n . '>';
			continue;
		}
		if ( preg_match( '/^\s*[-*•]\s+\[( |x|X)\]\s+(.+)$/u', $l, $m ) ) {
			$flush_para();
			if ( 'check' !== $list ) {
				$close_list();
				$out .= '<ul data-checklist>';
				$list = 'check';
			}
			$out .= '<li data-checked="' . ( ' ' === $m[1] ? '0' : '1' ) . '">' . $inline( $m[2] ) . '</li>';
			continue;
		}
		if ( preg_match( '/^\s*[-*•]\s+(.+)$/u', $l, $m ) || preg_match( '/^\s*(\d+)[.)]\s+(.+)$/u', $l, $m2 ) ) {
			$flush_para();
			$want = isset( $m2[2] ) && empty( $m ) ? 'ol' : 'ul';
			if ( $want !== $list ) {
				$close_list();
				$out .= '<' . $want . '>';
				$list = $want;
			}
			$out .= '<li>' . $inline( 'ol' === $want ? $m2[2] : $m[1] ) . '</li>';
			$m    = array();
			$m2   = array();
			continue;
		}
		$close_list();
		if ( preg_match( '/^>\s?(.*)$/u', $l, $m ) ) {
			$flush_para();
			$out .= '<blockquote>' . $inline( $m[1] ) . '</blockquote>';
			continue;
		}
		$para[] = trim( $l );
	}
	$flush_para();
	$close_list();
	return $out;
}

/**
 * HTML'i editörün blok yapısına böler (her eleman bir blok; yalın metin ve satır içi öğeler paragrafa sarılır).
 *
 * @return string[] temizlenmiş blok HTML'leri
 */
function flxg_html_to_blocks( $html ) {
	$html = trim( (string) $html );
	$html = preg_replace( '/^```[a-z]*\s*|\s*```$/i', '', $html );
	if ( '' === $html ) {
		return array();
	}
	if ( ! preg_match( '/<(p|h[1-6]|ul|ol|li|table|blockquote|pre|hr|br|div|strong|em|b|i|a)\b/i', $html ) ) {
		$html = flxg_md_to_html( $html );
	}
	if ( ! class_exists( 'DOMDocument' ) ) {
		$b = flxg_kses_block( '<p>' . wp_strip_all_tags( $html ) . '</p>' );
		return '' !== $b ? array( $b ) : array();
	}
	$dom  = new DOMDocument();
	$prev = libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8"?><div id="flxg-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	$root = $dom->getElementById( 'flxg-root' );
	if ( ! $root ) {
		return array();
	}
	$blocks = array();
	$inline = '';
	$flush  = function () use ( &$inline, &$blocks ) {
		if ( '' !== trim( wp_strip_all_tags( $inline ) ) ) {
			$blocks[] = '<p>' . trim( $inline ) . '</p>';
		}
		$inline = '';
	};
	$walk = function ( $parent ) use ( &$walk, &$inline, &$blocks, $flush, $dom ) {
		foreach ( iterator_to_array( $parent->childNodes ) as $n ) {
			if ( XML_ELEMENT_NODE !== $n->nodeType ) {
				if ( XML_TEXT_NODE === $n->nodeType ) {
					$inline .= esc_html( $n->nodeValue );
				}
				continue;
			}
			$tag = strtolower( $n->nodeName );
			if ( in_array( $tag, array( 'div', 'section', 'article', 'main', 'body' ), true ) ) {
				$flush();
				$walk( $n );
				$flush();
				continue;
			}
			if ( in_array( $tag, array( 'h5', 'h6' ), true ) ) {
				$flush();
				$blocks[] = '<h3>' . flxg_dom_inner( $dom, $n ) . '</h3>';
				continue;
			}
			if ( 'li' === $tag ) {
				$flush();
				$blocks[] = '<ul>' . $dom->saveHTML( $n ) . '</ul>';
				continue;
			}
			if ( in_array( $tag, array( 'p', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'blockquote', 'pre', 'hr', 'table', 'figure' ), true ) ) {
				$flush();
				$blocks[] = $dom->saveHTML( $n );
				continue;
			}
			$inline .= $dom->saveHTML( $n );
		}
	};
	$walk( $root );
	$flush();
	$out = array();
	foreach ( $blocks as $b ) {
		$b = flxg_kses_block( $b );
		if ( '' !== $b && ( '' !== trim( wp_strip_all_tags( $b ) ) || preg_match( '/<(hr|img|table)\b/i', $b ) ) ) {
			$out[] = $b;
		}
	}
	return array_slice( $out, 0, 300 );
}

function flxg_dom_inner( $dom, $node ) {
	$s = '';
	foreach ( $node->childNodes as $c ) {
		$s .= $dom->saveHTML( $c );
	}
	return $s;
}

/** Bloklar → modelin okuyacağı kısa metin (başlık "#", madde "-", kontrol "[ ]"). */
function flxg_doc_blocks_text( array $blocks, $max = 14000 ) {
	$lines = array();
	foreach ( $blocks as $b ) {
		$html = (string) ( is_array( $b ) ? $b['html'] : $b );
		if ( false !== stripos( $html, '<table' ) ) {
			$html = preg_replace( '/<br\s*\/?>/i', ' ', $html );
		}
		$html = preg_replace( '/<h([1-4])[^>]*>/i', "\n" . '{{H$1}}', $html );
		$html = preg_replace( '/<li[^>]*data-checked="1"[^>]*>/i', "\n- [x] ", $html );
		$html = preg_replace( '/<li[^>]*data-checked="0"[^>]*>/i', "\n- [ ] ", $html );
		$html = preg_replace( '/<li[^>]*>/i', "\n- ", $html );
		$html = preg_replace( '/<(br|\/p|\/tr|\/blockquote)[^>]*>/i', "\n", $html );
		$html = preg_replace( '/<\/t[dh]>/i', ' | ', $html );
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace_callback(
			'/\{\{H([1-4])\}\}/',
			function ( $m ) {
				return str_repeat( '#', (int) $m[1] ) . ' ';
			},
			$text
		);
		$text = trim( preg_replace( "/\n{2,}/", "\n", $text ) );
		if ( '' !== $text ) {
			$lines[] = $text;
		}
	}
	$all = implode( "\n\n", $lines );
	return mb_strlen( $all ) > $max ? mb_substr( $all, 0, $max ) . "\n…" : $all;
}

/* =========================================================================
 * Belge araçları (asistan, MCP, ortak Flux AI sohbeti)
 * ====================================================================== */

function flxg_tool_page_or_fail( $page_id, $need = 'view' ) {
	$p   = flxg_get_page( (int) $page_id );
	$doc = flxg_page_doc( $p );
	if ( ! $doc || ! flxg_can_doc( $doc ) ) {
		flxg_tool_fail( 'Document page #' . (int) $page_id . ' not found or no access. Use list_docs.' );
	}
	if ( 'edit' === $need && ! flxg_can_doc( $doc, 'edit' ) ) {
		flxg_tool_fail( 'No edit permission on this document.' );
	}
	return array( $p, $doc );
}

function flxg_doc_hash( $doc_id, $page_id = 0 ) {
	return '#/doc/' . (int) $doc_id . ( $page_id ? '/p/' . (int) $page_id : '' );
}

function flxg_doc_tools() {
	$html_desc = 'HTML blocks: p, h1-h3, ul/ol/li, checklist <ul data-checklist><li data-checked="0|1">, blockquote, pre, table, hr; inline strong, em, u, s, mark, a href. Markdown is also accepted.';
	return array(
		'list_docs'       => array(
			'title'       => 'List documents',
			'description' => 'Documents (multi-page docs inside projects) the user can see, with their page tree. A Flux Tasks URL hash #/doc/{doc_id}/p/{page_id} is the page the user is viewing.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'project_id' => array( 'type' => 'integer' ),
					'query'      => array( 'type' => 'string', 'description' => 'Text in document or page titles.' ),
				),
			),
			'readonly'    => true,
			'run'         => 'flxg_tool_list_docs',
		),
		'get_doc_page'    => array(
			'title'       => 'Get document page',
			'description' => 'Read a document page: title and content blocks (block_id + html), in order. Read it before edit_doc_page. Give doc_id alone for its first page.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'page_id' => array( 'type' => 'integer' ),
					'doc_id'  => array( 'type' => 'integer' ),
				),
			),
			'readonly'    => true,
			'run'         => 'flxg_tool_get_doc_page',
		),
		'edit_doc_page'   => array(
			'title'       => 'Edit document page',
			'description' => 'Change a document page by blocks (use block ids from get_doc_page). edits run in order: {op:"replace", block_id, html} rewrites a block (html may hold several blocks), {op:"insert", after: block_id | "start" | "end", html} adds new blocks, {op:"delete", block_id}. title renames the page. People who have the page open see the change live. ' . $html_desc,
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'page_id' => array( 'type' => 'integer' ),
					'title'   => array( 'type' => 'string' ),
					'edits'   => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'op'       => array( 'type' => 'string', 'enum' => array( 'replace', 'insert', 'delete' ) ),
								'block_id' => array( 'type' => 'string' ),
								'after'    => array( 'type' => 'string' ),
								'html'     => array( 'type' => 'string' ),
							),
							'required'   => array( 'op' ),
						),
					),
				),
				'required'   => array( 'page_id' ),
			),
			'run'         => 'flxg_tool_edit_doc_page',
		),
		'create_doc'      => array(
			'title'       => 'Create document',
			'description' => 'Create a document in a project (list_projects gives project ids), optionally with content on its first page. ' . $html_desc,
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'project_id' => array( 'type' => 'integer' ),
					'title'      => array( 'type' => 'string' ),
					'page_title' => array( 'type' => 'string' ),
					'html'       => array( 'type' => 'string' ),
				),
				'required'   => array( 'project_id', 'title' ),
			),
			'run'         => 'flxg_tool_create_doc',
		),
		'create_doc_page' => array(
			'title'       => 'Add document page',
			'description' => 'Add a page (optionally under parent_id) to a document, optionally with content. ' . $html_desc,
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'doc_id'    => array( 'type' => 'integer' ),
					'parent_id' => array( 'type' => 'integer' ),
					'title'     => array( 'type' => 'string' ),
					'html'      => array( 'type' => 'string' ),
				),
				'required'   => array( 'doc_id', 'title' ),
			),
			'run'         => 'flxg_tool_create_doc_page',
		),
	);
}

function flxg_tool_list_docs( $a ) {
	$q   = mb_strtolower( trim( (string) ( $a['query'] ?? '' ) ) );
	$sp  = (int) ( $a['project_id'] ?? 0 );
	$out = array();
	foreach ( flxg_visible_docs( get_current_user_id() ) as $d ) {
		if ( $sp && (int) $d['space_id'] !== $sp ) {
			continue;
		}
		$pages = flxg_doc_pages( $d['id'] );
		if ( '' !== $q ) {
			$hit = false !== mb_strpos( mb_strtolower( $d['title'] ), $q );
			foreach ( $pages as $p ) {
				$hit = $hit || false !== mb_strpos( mb_strtolower( $p['title'] ), $q );
			}
			if ( ! $hit ) {
				continue;
			}
		}
		$space = flxg_get_space( $d['space_id'] );
		$out[] = array(
			'doc_id'   => $d['id'],
			'title'    => $d['title'],
			'project'  => $space ? $space['name'] : null,
			'can_edit' => flxg_can_doc( $d, 'edit' ),
			'updated'  => $d['updated'],
			'pages'    => array_map(
				function ( $p ) {
					return array( 'page_id' => $p['id'], 'title' => '' !== $p['title'] ? $p['title'] : '(untitled)', 'parent_id' => $p['parent'] );
				},
				$pages
			),
		);
		if ( count( $out ) >= 60 ) {
			break;
		}
	}
	return array( 'docs' => $out );
}

function flxg_tool_get_doc_page( $a ) {
	$pid = (int) ( $a['page_id'] ?? 0 );
	if ( ! $pid && ! empty( $a['doc_id'] ) ) {
		$doc = flxg_get_doc( (int) $a['doc_id'] );
		if ( ! $doc || ! flxg_can_doc( $doc ) ) {
			flxg_tool_fail( 'Document #' . (int) $a['doc_id'] . ' not found or no access.' );
		}
		$first = flxg_doc_pages( $doc['id'] );
		$pid   = $first ? (int) $first[0]['id'] : 0;
	}
	list( $p, $doc ) = flxg_tool_page_or_fail( $pid );
	$blocks          = array();
	$len             = 0;
	foreach ( flxg_page_blocks( $p['id'] ) as $b ) {
		$len += strlen( $b['html'] );
		if ( $len > 26000 ) {
			$blocks[] = array( 'block_id' => '', 'html' => '…(rest of the page omitted)' );
			break;
		}
		$blocks[] = array( 'block_id' => $b['b'], 'html' => $b['html'] );
	}
	return array(
		'doc_id'    => $doc['id'],
		'doc_title' => $doc['title'],
		'page_id'   => (int) $p['id'],
		'title'     => (string) $p['title'],
		'url_hash'  => flxg_doc_hash( $doc['id'], $p['id'] ),
		'can_edit'  => flxg_can_doc( $doc, 'edit' ),
		'blocks'    => $blocks,
	);
}

/** HTML'i yeni bloklar olarak $after bloğunun arkasına yazar ('' = başa, null = sona). Son yazılan blok kimliğini döner. */
function flxg_doc_insert_html( $page_id, $html, $after, $uid, &$count ) {
	$ops = array();
	foreach ( flxg_html_to_blocks( $html ) as $bh ) {
		$bid = flxg_doc_new_bid();
		$op  = array( 'b' => $bid, 'html' => $bh );
		if ( null !== $after ) {
			$op['after'] = $after;
		}
		$ops[] = $op;
		$after = $bid;
	}
	if ( $ops ) {
		$count += count( flxg_apply_block_ops( $page_id, $ops, $uid ) );
	}
	return $after;
}

function flxg_tool_edit_doc_page( $a ) {
	global $wpdb;
	list( $p, $doc ) = flxg_tool_page_or_fail( $a['page_id'] ?? 0, 'edit' );
	$uid             = get_current_user_id();
	$pid             = (int) $p['id'];
	$exists          = array();
	foreach ( flxg_page_blocks( $pid ) as $b ) {
		$exists[ $b['b'] ] = 1;
	}
	$count = 0;
	foreach ( array_slice( (array) ( $a['edits'] ?? array() ), 0, 200 ) as $i => $e ) {
		$op  = (string) ( $e['op'] ?? '' );
		$bid = (string) ( $e['block_id'] ?? '' );
		if ( in_array( $op, array( 'replace', 'delete' ), true ) && ! isset( $exists[ $bid ] ) ) {
			flxg_tool_fail( 'edits[' . $i . ']: unknown block_id "' . $bid . '". Read the page again with get_doc_page.' );
		}
		if ( 'delete' === $op ) {
			$count += count( flxg_apply_block_ops( $pid, array( array( 'b' => $bid, 'del' => 1 ) ), $uid ) );
			unset( $exists[ $bid ] );
		} elseif ( 'replace' === $op ) {
			$parts = flxg_html_to_blocks( (string) ( $e['html'] ?? '' ) );
			if ( ! $parts ) {
				$count += count( flxg_apply_block_ops( $pid, array( array( 'b' => $bid, 'del' => 1 ) ), $uid ) );
				unset( $exists[ $bid ] );
				continue;
			}
			$count += count( flxg_apply_block_ops( $pid, array( array( 'b' => $bid, 'html' => array_shift( $parts ) ) ), $uid ) );
			if ( $parts ) {
				flxg_doc_insert_html( $pid, implode( '', $parts ), $bid, $uid, $count );
			}
		} elseif ( 'insert' === $op ) {
			$after = (string) ( $e['after'] ?? 'end' );
			if ( 'start' === $after || '' === $after ) {
				$after = '';
			} elseif ( 'end' === $after ) {
				$after = null;
			} elseif ( ! isset( $exists[ $after ] ) ) {
				flxg_tool_fail( 'edits[' . $i . ']: unknown after block "' . $after . '".' );
			}
			flxg_doc_insert_html( $pid, (string) ( $e['html'] ?? '' ), $after, $uid, $count );
		} else {
			flxg_tool_fail( 'edits[' . $i . ']: op must be replace, insert or delete.' );
		}
	}
	$renamed = false;
	if ( isset( $a['title'] ) && '' !== trim( (string) $a['title'] ) ) {
		$title = mb_substr( sanitize_text_field( (string) $a['title'] ), 0, 255 );
		if ( $title !== $p['title'] ) {
			$wpdb->update( flxg_table( 'doc_pages' ), array( 'title' => $title, 'updated_at' => flxg_now(), 'updated_by' => $uid ), array( 'id' => $pid ) );
			flxg_bump_doc_tree( $doc['id'] );
			$renamed = true;
		}
	}
	return array(
		'page_id'        => $pid,
		'doc_id'         => $doc['id'],
		'changed_blocks' => $count,
		'renamed'        => $renamed,
		'url_hash'       => flxg_doc_hash( $doc['id'], $pid ),
	);
}

function flxg_tool_create_doc( $a ) {
	$space = (int) ( $a['project_id'] ?? 0 );
	if ( ! flxg_get_space( $space ) || ! flxg_can_space( $space, 'edit' ) ) {
		flxg_tool_fail( 'Project #' . $space . ' not found or no edit permission. Use list_projects.' );
	}
	$uid = get_current_user_id();
	$id  = flxg_create_doc(
		array(
			'space_id' => $space,
			'title'    => '' !== trim( (string) ( $a['title'] ?? '' ) ) ? (string) $a['title'] : __( 'Untitled document', 'flx-tasks' ),
			'page'     => (string) ( $a['page_title'] ?? '' ),
		),
		$uid
	);
	flxg_bump_structure();
	$pages = flxg_doc_pages( $id );
	$pid   = $pages ? (int) $pages[0]['id'] : 0;
	$n     = 0;
	if ( $pid && ! empty( $a['html'] ) ) {
		flxg_doc_insert_html( $pid, (string) $a['html'], null, $uid, $n );
	}
	return array( 'doc_id' => $id, 'page_id' => $pid, 'blocks' => $n, 'url_hash' => flxg_doc_hash( $id, $pid ) );
}

function flxg_tool_create_doc_page( $a ) {
	$doc = flxg_get_doc( (int) ( $a['doc_id'] ?? 0 ) );
	if ( ! $doc || ! flxg_can_doc( $doc, 'edit' ) ) {
		flxg_tool_fail( 'Document not found or no edit permission.' );
	}
	$uid = get_current_user_id();
	$pid = flxg_create_doc_page( $doc['id'], array( 'title' => (string) ( $a['title'] ?? '' ), 'parent' => (int) ( $a['parent_id'] ?? 0 ) ), $uid );
	$n   = 0;
	if ( $pid && ! empty( $a['html'] ) ) {
		flxg_doc_insert_html( $pid, (string) $a['html'], null, $uid, $n );
	}
	return array( 'doc_id' => $doc['id'], 'page_id' => $pid, 'blocks' => $n, 'url_hash' => flxg_doc_hash( $doc['id'], $pid ) );
}

/* =========================================================================
 * Ortak Flux AI sohbeti: Flux Tasks araçları + sayfa bilgisi
 * ====================================================================== */

/** Ortak sohbetin onay kartında görünen Türkçe adlar. */
function flxg_flxai_tool_titles() {
	return array(
		'list_projects'      => __( 'List task projects', 'flx-tasks' ),
		'list_users'         => __( 'List task people', 'flx-tasks' ),
		'search_tasks'       => __( 'Search tasks', 'flx-tasks' ),
		'get_task'           => __( 'Read task', 'flx-tasks' ),
		'create_task'        => __( 'Create task', 'flx-tasks' ),
		'update_task'        => __( 'Update task', 'flx-tasks' ),
		'add_comment'        => __( 'Comment on task', 'flx-tasks' ),
		'list_fields'        => __( 'List task fields', 'flx-tasks' ),
		'create_list'        => __( 'Create task list', 'flx-tasks' ),
		'delete_task'        => __( 'Delete task', 'flx-tasks' ),
		'list_notifications' => __( 'List task notifications', 'flx-tasks' ),
		'list_docs'          => __( 'List documents', 'flx-tasks' ),
		'get_doc_page'       => __( 'Read document page', 'flx-tasks' ),
		'edit_doc_page'      => __( 'Edit document page', 'flx-tasks' ),
		'create_doc'         => __( 'Create document', 'flx-tasks' ),
		'create_doc_page'    => __( 'Add page to document', 'flx-tasks' ),
	);
}

/* Araç kaydı includes/mcp.php'de: `flx_mcp_tools` hem MCP'ye hem bu sohbete verir. */

/** Ortak sohbet: kullanıcı Flux Tasks'de bir belgedeyse "bu sayfa" hangisi, nasıl düzenlenir. */
add_filter(
	'flxai_chat_system',
	function ( $text, $user_id = 0 ) {
		if ( ! user_can( $user_id ? (int) $user_id : get_current_user_id(), 'flxg_use' ) ) {
			return $text;
		}
		return $text . "\n\nYT Görev (task tracker, admin.php?page=flx-tasks): tools prefixed flxg_ read and change tasks and documents with the user's permissions. "
			. 'If the page URL hash is #/doc/{doc_id}/p/{page_id}, the user is viewing that document page; "this document/page" means it. '
			. 'To change a document: read it with flxg_get_doc_page, then flxg_edit_doc_page with block ids (replace/insert/delete). Do not answer that you cannot edit documents.';
	},
	20,
	2
);

add_filter(
	'flxai_purpose_labels',
	function ( $labels ) {
		$labels = is_array( $labels ) ? $labels : array();
		foreach ( flxg_doc_ai_actions() as $k => $a ) {
			/* translators: %s: action name, e.g. "Improve" */
			$labels[ 'doc_' . $k ] = sprintf( __( 'Document: %s', 'flx-tasks' ), $a['label'] );
		}
		return $labels;
	}
);

/* =========================================================================
 * Belge ekranı hızlı işlemleri: POST doc-pages/{id}/ai
 * ====================================================================== */

/** sel: seçili metin üzerinde; page: sayfanın tamamı üzerinde. out: html (blok/satır içi) ya da text. */
function flxg_doc_ai_actions() {
	return array(
		'improve'   => array( 'on' => 'sel', 'label' => __( 'Improve', 'flx-tasks' ), 'p' => 'Improve the writing: clearer, more fluent and well structured. Keep the meaning, facts and language.' ),
		'fix'       => array( 'on' => 'sel', 'label' => __( 'Fix spelling & grammar', 'flx-tasks' ), 'p' => 'Fix spelling, grammar and punctuation only. Do not change wording otherwise.' ),
		'shorter'   => array( 'on' => 'sel', 'label' => __( 'Shorten', 'flx-tasks' ), 'p' => 'Make it noticeably shorter and more concise while keeping the key points.' ),
		'longer'    => array( 'on' => 'sel', 'label' => __( 'Make longer', 'flx-tasks' ), 'p' => 'Expand it with useful detail and explanation. Do not invent specific facts like names, prices or dates.' ),
		'simplify'  => array( 'on' => 'sel', 'label' => __( 'Simplify', 'flx-tasks' ), 'p' => 'Rewrite in simple, plain language anyone can understand.' ),
		'formal'    => array( 'on' => 'sel', 'label' => __( 'More formal', 'flx-tasks' ), 'p' => 'Rewrite in a formal, professional tone suitable for an official association.' ),
		'friendly'  => array( 'on' => 'sel', 'label' => __( 'More friendly', 'flx-tasks' ), 'p' => 'Rewrite in a warm, friendly tone.' ),
		'bullets'   => array( 'on' => 'sel', 'label' => __( 'Turn into bullets', 'flx-tasks' ), 'p' => 'Turn it into a clear bullet list (<ul><li>).', 'block' => true ),
		'table'     => array( 'on' => 'sel', 'label' => __( 'Turn into table', 'flx-tasks' ), 'p' => 'Turn it into a well-structured HTML table (<table> with a header row in <thead><tr><th>).', 'block' => true ),
		'translate' => array( 'on' => 'sel', 'label' => __( 'Translate', 'flx-tasks' ), 'p' => 'Translate it into {lang}. Keep formatting.' ),
		'explain'   => array( 'on' => 'sel', 'label' => __( 'Explain', 'flx-tasks' ), 'p' => 'Explain what this text means in a few short sentences for the reader. Do not rewrite it.', 'block' => true, 'answer' => true ),
		'custom'    => array( 'on' => 'sel', 'label' => __( 'Custom request', 'flx-tasks' ), 'p' => 'Apply the user instruction to the selected text.' ),
		'write'     => array( 'on' => 'page', 'label' => __( 'Write', 'flx-tasks' ), 'p' => 'Write new content for this page following the user instruction. It will be inserted into the page, so do not repeat existing content.' ),
		'continue'  => array( 'on' => 'page', 'label' => __( 'Continue writing', 'flx-tasks' ), 'p' => 'Continue writing the page naturally from where it ends: 1-3 paragraphs (or list items if the page ends with a list). Do not repeat existing content.' ),
		'summary'   => array( 'on' => 'page', 'label' => __( 'Summarize', 'flx-tasks' ), 'p' => 'Write a short summary of the page: a heading <h3> plus 3-6 bullet points with the key points and decisions.' ),
		'actions'   => array( 'on' => 'page', 'label' => __( 'Extract action items', 'flx-tasks' ), 'json' => true, 'p' => 'Extract everything that needs to be done from the page as task items. Always return items, even when the page names no person or date: turn open questions and decisions still to be made into tasks that start with a verb (e.g. "Sitenin adı ne olacak?" -> "Sitenin adını belirle"). Skip only things that are clearly already done. Use the person and date only if the page states them.' ),
		'outline'   => array( 'on' => 'page', 'label' => __( 'Draft an outline', 'flx-tasks' ), 'p' => 'Propose a structured outline for this page: headings (<h2>/<h3>) with one short line or bullets under each. Use the page title and existing content as the topic.' ),
		'title'     => array( 'on' => 'page', 'label' => __( 'Suggest a title', 'flx-tasks' ), 'p' => 'Suggest one short, clear page title (max ~70 characters). Return ONLY the title text, no quotes or HTML.', 'text' => true ),
		'ask'       => array( 'on' => 'page', 'label' => __( 'Ask the page', 'flx-tasks' ), 'p' => 'Answer the user question using the page content. If the page does not contain the answer, say so briefly.', 'answer' => true ),
	);
}

function flxg_doc_ai_run( $page_id, array $b ) {
	$p   = flxg_get_page( (int) $page_id );
	$doc = flxg_page_doc( $p );
	if ( ! $doc || ! flxg_can_doc( $doc ) ) {
		return flxg_error( 'flxg_404', __( 'Page not found or you have no access.', 'flx-tasks' ), 404 );
	}
	$acts   = flxg_doc_ai_actions();
	$action = (string) ( $b['action'] ?? '' );
	if ( ! isset( $acts[ $action ] ) ) {
		return flxg_error( 'flxg_ai_action', __( 'Unknown action.', 'flx-tasks' ) );
	}
	$a      = $acts[ $action ];
	$prompt = trim( mb_substr( (string) ( $b['prompt'] ?? '' ), 0, 2000 ) );
	if ( in_array( $action, array( 'custom', 'write', 'ask' ), true ) && '' === $prompt ) {
		return flxg_error( 'flxg_ai_prompt', __( 'Write what should be done.', 'flx-tasks' ) );
	}
	$lang      = 'en' === ( $b['lang'] ?? '' ) ? 'English' : ( 'tr' === ( $b['lang'] ?? '' ) ? 'Turkish' : ( preg_match( '/^[a-z]{2}$/', (string) ( $b['lang'] ?? '' ) ) ? strtoupper( $b['lang'] ) : 'English' ) );
	$inline    = 'sel' === $a['on'] && ! empty( $b['inline'] ) && empty( $a['block'] );
	$page_text = flxg_doc_blocks_text( flxg_page_blocks( $p['id'] ), 'sel' === $a['on'] ? 5000 : 14000 );
	$title     = '' !== (string) $p['title'] ? (string) $p['title'] : '(untitled)';

	$fmt = ! empty( $a['json'] ) ? 'Return ONLY JSON, no code fences: {"items":[{"title":"short task title in the document language","assignee":user id number or 0,"due":"YYYY-MM-DD" or ""}]}. People (id: name): ' . flxg_doc_ai_people() . '.'
		: ( ! empty( $a['text'] ) ? 'Return ONLY plain text.'
		: ( $inline
			? 'The selection is inline text inside a paragraph. Return ONLY inline HTML (text with optional <strong>, <em>, <u>, <a href>); no block tags, no markdown, no code fences, no explanations.'
			: 'Return ONLY HTML blocks: <p>, <h2>, <h3>, <ul>/<ol>/<li>, checklist <ul data-checklist><li data-checked="0">, <blockquote>, <table>, with inline <strong>, <em>, <a href>. No markdown, no code fences, no preface like "Here is".' ) );
	$sys = flxg_ai_system_base() . "\n\nYou are helping inside the document editor. Write in the same language as the document unless asked to translate. " . $fmt;

	$user = 'Document: "' . $doc['title'] . '", page: "' . $title . "\"\n\n";
	if ( 'sel' === $a['on'] ) {
		$sel   = mb_substr( (string) ( $b['html'] ?? '' ), 0, 12000 );
		$sel   = '' !== trim( $sel ) ? $sel : esc_html( mb_substr( (string) ( $b['text'] ?? '' ), 0, 12000 ) );
		$user .= "Page content (context only):\n" . $page_text . "\n\nSELECTED TEXT (HTML):\n" . $sel . "\n\nTask: " . str_replace( '{lang}', $lang, $a['p'] );
	} else {
		$user .= "Page content:\n" . ( '' !== $page_text ? $page_text : '(empty page)' ) . "\n\nTask: " . $a['p'];
	}
	if ( '' !== $prompt ) {
		$user .= "\n\nUser instruction: " . $prompt;
	}
	$prev = trim( mb_substr( (string) ( $b['prev'] ?? '' ), 0, 12000 ) );
	if ( '' !== $prev ) {
		$user .= "\n\nYour previous answer was:\n" . $prev . "\n\nRevise that answer according to the user instruction.";
	}
	$msg = flxg_ai_chat_raw(
		array(
			array( 'role' => 'system', 'content' => $sys ),
			array( 'role' => 'user', 'content' => $user ),
		),
		array( 'max_tokens' => 'page' === $a['on'] ? 2200 : 1600, 'purpose' => 'doc_' . $action )
	);
	if ( is_wp_error( $msg ) ) {
		return $msg;
	}
	$out = trim( (string) ( $msg['content'] ?? '' ) );
	$out = trim( preg_replace( '/^```[a-z]*\s*|\s*```$/i', '', $out ) );
	if ( ! empty( $a['json'] ) ) {
		$items = flxg_doc_ai_items( $out );
		if ( ! $items ) {
			return flxg_error( 'flxg_ai_empty', __( 'The AI could not extract items. Try again or describe what you need.', 'flx-tasks' ), 502 );
		}
		return array( 'items' => $items, 'answer' => false );
	}
	if ( ! empty( $a['text'] ) ) {
		return array( 'text' => trim( wp_strip_all_tags( $out ), " \t\n\r\"'“”" ), 'answer' => true );
	}
	if ( $inline ) {
		if ( ! preg_match( '/<[a-z]/i', $out ) ) {
			$out = esc_html( $out );
		}
		$out = preg_replace( '/^\s*<p[^>]*>|<\/p>\s*$/i', '', $out );
		$out = wp_kses( str_replace( array( '</p><p>', '</p>' ), array( '<br>', '' ), $out ), array( 'strong' => array(), 'b' => array(), 'em' => array(), 'i' => array(), 'u' => array(), 's' => array(), 'mark' => array(), 'code' => array(), 'br' => array(), 'a' => array( 'href' => true, 'target' => true, 'rel' => true ) ) );
		return array( 'html' => trim( $out ), 'inline' => true, 'answer' => ! empty( $a['answer'] ) );
	}
	$blocks = flxg_html_to_blocks( $out );
	if ( ! $blocks ) {
		return flxg_error( 'flxg_ai_empty', __( 'The AI returned an empty answer. Try again.', 'flx-tasks' ), 502 );
	}
	return array( 'html' => implode( '', $blocks ), 'inline' => false, 'answer' => ! empty( $a['answer'] ) );
}

/** Modelin görev atayabileceği kişiler (id: ad). */
function flxg_doc_ai_people() {
	$out = array();
	foreach ( array_slice( flxg_users(), 0, 80 ) as $u ) {
		$out[] = $u['id'] . ': ' . $u['name'];
	}
	return $out ? implode( ', ', $out ) : '(none)';
}

/** "Yapılacaklar" JSON cevabı → [{title, assignee, due}] (kişi ve tarih doğrulanır). */
function flxg_doc_ai_items( $out ) {
	if ( preg_match( '/\{.*\}/s', (string) $out, $m ) ) {
		$out = $m[0];
	}
	$d     = json_decode( (string) $out, true );
	$users = flxg_users();
	$items = array();
	foreach ( (array) ( is_array( $d ) ? ( $d['items'] ?? $d ) : array() ) as $it ) {
		$title = is_array( $it ) ? (string) ( $it['title'] ?? '' ) : (string) $it;
		$title = trim( mb_substr( sanitize_text_field( $title ), 0, 300 ) );
		if ( '' === $title ) {
			continue;
		}
		$uid     = is_array( $it ) ? (int) ( $it['assignee'] ?? 0 ) : 0;
		$due     = is_array( $it ) ? (string) ( $it['due'] ?? '' ) : '';
		$items[] = array(
			'title'    => $title,
			'assignee' => isset( $users[ $uid ] ) ? $uid : 0,
			'due'      => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $due ) ? $due : '',
		);
		if ( count( $items ) >= 40 ) {
			break;
		}
	}
	return $items;
}

function flxg_rest_doc_ai( WP_REST_Request $r ) {
	if ( ! flxg_ai_ready() ) {
		return flxg_error( 'flxg_ai_off', flxg_ai_off_message() );
	}
	return flxg_doc_ai_run( (int) $r['id'], flxg_body( $r ) );
}
