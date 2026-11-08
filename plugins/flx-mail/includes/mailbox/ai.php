<?php
/**
 * Posta kutusunda yapay zekâ (Flux AI üzerinden; Flux AI yoksa bu özellikler görünmez).
 *
 * - Gelen e-postaya cevap taslağı, yeni e-posta taslağı, taslağı düzeltme.
 * - Ortak sohbet araçları (flxai_tools): e-postaları listele/oku, taslak yaz, taslağı gönder (onaylı).
 *
 * AI'nın yazdığı her şey "Onay bekliyor" taslağıdır; gönderim insan onayıyla olur.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

function flxi_mailbox_ai_ready() {
	return function_exists( 'flxai_chat' ) && function_exists( 'flxai_ready' ) && flxai_ready( 'flx-mail' );
}

/** Modele verilen kurum bilgisi (diğer eklentiler flxi_mailbox_ai_context ile ekler). */
function flxi_mailbox_ai_context() {
	$b    = flxi_brand();
	$info = array(
		'Kurum: ' . $b['org'],
		$b['tagline'] ? 'Slogan: ' . $b['tagline'] : '',
		$b['website'] ? 'Web: ' . $b['website'] : '',
		$b['email'] ? 'E-posta: ' . $b['email'] : '',
		$b['phone'] ? 'Telefon: ' . $b['phone'] : '',
		$b['address'] ? 'Adres: ' . $b['address'] : '',
	);
	return (string) apply_filters( 'flxi_mailbox_ai_context', implode( "\n", array_filter( $info ) ) );
}

function flxi_mailbox_ai_system() {
	$rules = "Sen bir derneğin e-posta asistanısın; kurum adına e-posta taslağı yazarsın. Taslak bir insan tarafından okunup onaylanmadan gönderilmez.\n"
		. "Kurallar:\n"
		. "- Gelen e-posta hangi dildeyse o dilde yaz (çoğunlukla Türkçe). Kibar, sıcak, kurumsal ve kısa ol.\n"
		. "- Bilmediğin bilgiyi (tarih, fiyat, IBAN, kişi adı, söz) uydurma; gerekiyorsa [köşeli parantez içinde doldurulacak alan] bırak.\n"
		. "- Selamlama ile başla. İmza, kurum adı ve iletişim bilgisi EKLEME (otomatik eklenir).\n"
		. "- Gövde basit HTML olsun: <p>, <strong>, <ul><li>, <a>. Stil, tablo, görsel kullanma.\n"
		. "- Çıktın yalnız JSON olsun: {\"subject\": \"…\", \"body\": \"<p>…</p>\"}. Başka metin yazma.";
	return array(
		array(
			'type'  => 'text',
			'text'  => $rules,
			'cache' => true,
		),
		array(
			'type' => 'text',
			'text' => "Kurum bilgisi:\n" . flxi_mailbox_ai_context(),
		),
	);
}

/** Yazışmanın modele gidecek metni (en yeni 6 ileti, her biri kısaltılmış). */
function flxi_mailbox_thread_text( array $m ) {
	$rows = flxi_mailbox_query(
		array(
			'folder' => 'all',
			'thread' => $m['thread_key'],
			'system' => 1,
			'limit'  => 20,
		)
	)['rows'];
	$rows = array_slice( $rows, -6 );
	$out  = array();
	foreach ( $rows as $r ) {
		$full  = flxi_mailbox_get( $r['id'] );
		$text  = trim( (string) $full['body_text'] );
		$text  = '' !== $text ? $text : flxi_html_to_text( (string) $full['body_html'] );
		// Alıntılanmış eski kısımları at.
		$text  = preg_replace( '/^>.*$/m', '', $text );
		$text  = mb_substr( trim( $text ), 0, 3000 );
		$dir   = 'sent' === $r['folder'] ? 'GİDEN (biz)' : 'GELEN';
		$out[] = '--- ' . $dir . ' · ' . get_date_from_gmt( $r['mail_date'], 'd.m.Y H:i' ) . "\nKimden: " . ( $r['from_name'] ? $r['from_name'] . ' <' . $r['from_email'] . '>' : $r['from_email'] ) . "\nKime: " . $r['to_list'] . "\nKonu: " . $r['subject'] . "\n\n" . $text;
	}
	return implode( "\n\n", $out );
}

/**
 * Taslak metni yazdırır.
 *
 * @param array $a mail_id (cevaplanan), instruction, current (mevcut gövde: düzelt), subject, to
 * @return array|WP_Error subject, body
 */
function flxi_mailbox_ai_write( array $a ) {
	if ( ! flxi_mailbox_ai_ready() ) {
		return new WP_Error( 'flxi_ai', __( 'Flux AI & MCP is not installed or has no key (Flux AI & MCP › Settings).', 'flx-mail' ) );
	}
	$user = array();
	$m    = ! empty( $a['mail_id'] ) ? flxi_mailbox_get( $a['mail_id'] ) : null;
	if ( $m ) {
		$user[] = "Yazışma (eskiden yeniye):\n\n" . flxi_mailbox_thread_text( $m );
		$user[] = 'Görev: Son GELEN e-postaya kurum adına cevap taslağı yaz.';
	} else {
		$user[] = 'Görev: Yeni bir e-posta taslağı yaz.' . ( ! empty( $a['to'] ) ? ' Alıcı: ' . $a['to'] . '.' : '' );
	}
	if ( ! empty( $a['current'] ) ) {
		$user[] = "Mevcut taslak (bunu talimata göre düzelt, iyi kısımlarını koru):\nKonu: " . ( $a['subject'] ?? '' ) . "\n" . wp_kses_post( (string) $a['current'] );
	}
	if ( ! empty( $a['instruction'] ) ) {
		$user[] = 'Talimat: ' . sanitize_textarea_field( (string) $a['instruction'] );
	}
	$r = flxai_chat(
		array(
			array( 'role' => 'system', 'content' => flxi_mailbox_ai_system() ),
			array( 'role' => 'user', 'content' => implode( "\n\n", $user ) ),
		),
		array(
			'source'     => 'flx-mail',
			'purpose'    => $m ? 'mail_reply' : 'mail_compose',
			'max_tokens' => 1500,
		)
	);
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$text = trim( (string) ( $r['content'] ?? '' ) );
	$text = preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $text );
	$j    = json_decode( $text, true );
	if ( ! is_array( $j ) && preg_match( '/\{.*\}/s', $text, $mm ) ) {
		$j = json_decode( $mm[0], true );
	}
	if ( ! is_array( $j ) || empty( $j['body'] ) ) {
		$j = array( 'subject' => '', 'body' => flxi_text_to_html( $text ) );
	}
	return array(
		'subject' => sanitize_text_field( (string) ( $j['subject'] ?? '' ) ),
		'body'    => wp_kses_post( (string) $j['body'] ),
	);
}

/**
 * Bir e-postaya AI cevap taslağı oluşturur (Onay bekliyor).
 *
 * @return int|WP_Error taslak id
 */
function flxi_mailbox_ai_reply_draft( $mail_id, $instruction = '', $mode = 'reply' ) {
	$w = flxi_mailbox_ai_write( array( 'mail_id' => $mail_id, 'instruction' => $instruction ) );
	if ( is_wp_error( $w ) ) {
		return $w;
	}
	return flxi_mailbox_start_reply(
		$mail_id,
		$mode,
		array(
			'body'   => $w['body'],
			'status' => 'review',
			'origin' => 'ai',
			'note'   => $instruction ? mb_substr( sanitize_textarea_field( $instruction ), 0, 500 ) : '',
		)
	);
}

/* ------------------------------------------------------------------ */
/* Flux AI sohbet araçları                                               */
/* ------------------------------------------------------------------ */

function flxi_mailbox_tool_row( array $r ) {
	return array(
		'id'      => (int) $r['id'],
		'folder'  => $r['folder'],
		'from'    => $r['from_name'] ? $r['from_name'] . ' <' . $r['from_email'] . '>' : $r['from_email'],
		'to'      => $r['to_list'],
		'subject' => $r['subject'],
		'snippet' => $r['snippet'],
		'date'    => get_date_from_gmt( $r['mail_date'], 'Y-m-d H:i' ),
		'unread'  => ! (int) $r['is_read'],
		'replied' => (bool) $r['replied'],
	);
}

add_filter(
	'flxai_tools',
	function ( $tools ) {
		if ( ! flxi_mailbox_enabled() && ! flxi_setting( 'mailbox_enabled' ) ) {
			return $tools;
		}
		$cap = 'flxi_manage';

		$tools['flxi_mail_list'] = array(
			'title'       => __( 'List emails', 'flx-mail' ),
			'description' => 'bilgi@ posta kutusundaki e-postaları listeler (gelen ya da gönderilen). Sistem/otomatik e-postalar varsayılan gizli.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'folder'         => array( 'type' => 'string', 'enum' => array( 'inbox', 'sent' ), 'description' => 'inbox = gelen, sent = gönderilen' ),
					'q'              => array( 'type' => 'string', 'description' => 'Konu, gönderen ya da alıcıda arama' ),
					'unread'         => array( 'type' => 'boolean', 'description' => 'Yalnız okunmamışlar' ),
					'include_system' => array( 'type' => 'boolean' ),
					'limit'          => array( 'type' => 'integer', 'description' => 'En çok 30' ),
				),
			),
			'readonly'    => true,
			'cap'         => $cap,
			'source'      => 'flx-mail',
			'run'         => function ( $args ) {
				$r = flxi_mailbox_query(
					array(
						'folder' => 'sent' === ( $args['folder'] ?? '' ) ? 'sent' : 'inbox',
						'search' => (string) ( $args['q'] ?? '' ),
						'unread' => ! empty( $args['unread'] ),
						'system' => ! empty( $args['include_system'] ),
						'limit'  => min( 30, max( 1, (int) ( $args['limit'] ?? 15 ) ) ),
					)
				);
				return array(
					'total' => $r['total'],
					'mails' => array_map( 'flxi_mailbox_tool_row', $r['rows'] ),
				);
			},
		);

		$tools['flxi_mail_read'] = array(
			'title'       => __( 'Read email', 'flx-mail' ),
			'description' => 'Bir e-postanın tam metnini, eklerinin adlarını ve aynı yazışmadaki diğer e-postaları verir.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'id' => array( 'type' => 'integer' ) ),
				'required'   => array( 'id' ),
			),
			'readonly'    => true,
			'cap'         => $cap,
			'source'      => 'flx-mail',
			'run'         => function ( $args ) {
				$m = flxi_mailbox_get( (int) ( $args['id'] ?? 0 ) );
				if ( ! $m ) {
					throw new Exception( 'E-posta bulunamadı.' ); // phpcs:ignore
				}
				$text = trim( (string) $m['body_text'] );
				$text = '' !== $text ? $text : flxi_html_to_text( (string) $m['body_html'] );
				$th   = flxi_mailbox_query( array( 'folder' => 'all', 'thread' => $m['thread_key'], 'system' => 1, 'limit' => 20 ) )['rows'];
				return array_merge(
					flxi_mailbox_tool_row( $m ),
					array(
						'cc'          => $m['cc_list'],
						'text'        => mb_substr( $text, 0, 12000 ),
						'attachments' => wp_list_pluck( $m['attachments'], 'name' ),
						'thread'      => array_map( 'flxi_mailbox_tool_row', $th ),
					)
				);
			},
		);

		$tools['flxi_mail_layouts'] = array(
			'title'       => __( 'List email layouts', 'flx-mail' ),
			'description' => 'Posta kutusundan gönderimde seçilebilen şablonlar (Kurumsal, Sade ve "Örnek Vakfı × Firma" gibi alt şablonlar).',
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'readonly'    => true,
			'cap'         => $cap,
			'source'      => 'flx-mail',
			'run'         => function () {
				$out = array();
				foreach ( flxi_layouts() as $id => $l ) {
					$out[] = array(
						'id'      => $id,
						'name'    => $l['name'],
						'desc'    => $l['desc'],
						'partner' => $l['partner_name'] ?? '',
						'body'    => isset( $l['body'] ) ? wp_strip_all_tags( $l['body'] ) : '',
					);
				}
				return $out;
			},
		);

		$tools['flxi_mail_draft'] = array(
			'title'       => __( 'Write email draft', 'flx-mail' ),
			'description' => 'Posta kutusuna taslak kaydeder ("Onay bekliyor"); GÖNDERMEZ. Cevap için reply_to_id verin (alıcı ve konu otomatik). Var olan taslağı düzeltmek için draft_id verin. Gövde basit HTML (<p>, <ul>, <strong>); imza otomatik eklenir.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'reply_to_id' => array( 'type' => 'integer', 'description' => 'Cevaplanan e-postanın id si' ),
					'draft_id'    => array( 'type' => 'integer', 'description' => 'Güncellenecek taslak' ),
					'to'          => array( 'type' => 'string', 'description' => 'Alıcılar, virgülle' ),
					'cc'          => array( 'type' => 'string' ),
					'subject'     => array( 'type' => 'string' ),
					'body'        => array( 'type' => 'string' ),
					'layout'      => array( 'type' => 'string', 'description' => 'Şablon id (flxi_mail_layouts); cevaplarda varsayılan sade' ),
				),
				'required'   => array( 'body' ),
			),
			'readonly'    => false,
			'cap'         => $cap,
			'source'      => 'flx-mail',
			'run'         => function ( $args ) {
				$d = array(
					'body'   => (string) ( $args['body'] ?? '' ),
					'status' => 'review',
					'origin' => 'ai',
				);
				foreach ( array( 'to' => 'to_list', 'cc' => 'cc_list', 'subject' => 'subject', 'layout' => 'layout' ) as $k => $col ) {
					if ( isset( $args[ $k ] ) && '' !== (string) $args[ $k ] ) {
						$d[ $col ] = (string) $args[ $k ];
					}
				}
				if ( ! empty( $args['draft_id'] ) ) {
					$cur = flxi_draft_get( (int) $args['draft_id'] );
					if ( ! $cur || 'sent' === $cur['status'] ) {
						throw new Exception( 'Taslak bulunamadı ya da gönderilmiş.' ); // phpcs:ignore
					}
					$id = flxi_draft_save( $d, (int) $args['draft_id'] );
				} elseif ( ! empty( $args['reply_to_id'] ) ) {
					$id = flxi_mailbox_start_reply( (int) $args['reply_to_id'], 'reply', $d );
					if ( ! $id ) {
						throw new Exception( 'Cevaplanacak e-posta bulunamadı.' ); // phpcs:ignore
					}
				} else {
					$d['mode'] = 'new';
					$id        = flxi_draft_save( $d );
				}
				$dr = flxi_draft_get( $id );
				return array(
					'draft_id' => $id,
					'to'       => $dr['to_list'],
					'subject'  => $dr['subject'],
					'status'   => 'Onay bekliyor',
					'url'      => flxi_admin_url( 'flxi-posta', array( 'taslak' => $id ) ),
				);
			},
		);

		$tools['flxi_mail_send_draft'] = array(
			'title'       => __( 'Send draft', 'flx-mail' ),
			'description' => 'Kaydedilmiş bir taslağı bilgi@ adresinden gönderir. Yalnız kullanıcı açıkça "gönder" dediyse kullanın.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'draft_id' => array( 'type' => 'integer' ) ),
				'required'   => array( 'draft_id' ),
			),
			'readonly'    => false,
			'confirm'     => true,
			'cap'         => $cap,
			'source'      => 'flx-mail',
			'run'         => function ( $args ) {
				$r = flxi_draft_send( (int) ( $args['draft_id'] ?? 0 ) );
				if ( ! $r['ok'] ) {
					throw new Exception( $r['error'] ); // phpcs:ignore
				}
				return array( 'sent' => true, 'mail_id' => $r['mail_id'] );
			},
		);
		return $tools;
	}
);

add_filter(
	'flxai_systems',
	function ( $s ) {
		if ( ! isset( $s['flx-mail'] ) ) {
			$s['flx-mail'] = array(
				'label'  => __( 'Communication (mailbox)', 'flx-mail' ),
				'plugin' => plugin_basename( FLXI_FILE ),
				'page'   => 'admin.php?page=flxi-posta',
			);
		}
		return $s;
	}
);

add_filter(
	'flxai_sources',
	function ( $s ) {
		if ( ! isset( $s['flx-mail'] ) ) {
			$s['flx-mail'] = __( 'Flux Mail', 'flx-mail' );
		}
		return $s;
	}
);

add_filter(
	'flxai_purpose_labels',
	function ( $l ) {
		$l['mail_reply']   = __( 'Email reply', 'flx-mail' );
		$l['mail_compose'] = __( 'Email draft', 'flx-mail' );
		return $l;
	}
);
