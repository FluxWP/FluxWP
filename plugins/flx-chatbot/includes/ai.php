<?php
/**
 * Flux AI & MCP kaydı (SDK): bağlı sistem, model ayarı, amaç etiketi ve yöneticiler için salt okunur araçlar
 * (site sohbetleri listesi, bir sohbetin dökümü, özet sayılar). Ziyaretçi asistanı bu araçları göremez.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'flx_ai_register',
	function () {
		flx_ai_plugin(
			'flx-chatbot',
			array(
				'label'       => 'Flux AI Chatbot',
				'prefix'      => 'flxcb',
				'plugin'      => plugin_basename( FLXCB_FILE ),
				'page'        => 'admin.php?page=flx-chatbot',
				'description' => __( 'Sitedeki sohbet penceresinin asistanı ve site sohbetleri.', 'flx-chatbot' ),
			)
		);
		flx_ai_settings(
			'flx-chatbot',
			array(
				'model' => array(
					'type'  => 'model',
					'label' => __( 'Asistan modeli', 'flx-chatbot' ),
					'help'  => __( 'Ziyaretçilere cevap veren model. Hızlı ve ucuz bir model önerilir; boşsa Flux AI varsayılanı.', 'flx-chatbot' ),
				),
			)
		);

		$cap = 'flxcb_reply';
		flx_ai_tool(
			'flx-chatbot',
			'conversations',
			array(
				'title'       => __( 'Site sohbetleri', 'flx-chatbot' ),
				'description' => 'Lists recent website chat conversations (AI assistant and live support). Filter by status: open (waiting for or talking to an agent), bot, queued, agent, offline, closed, or empty for all. Optional search in name, email, phone, page title.',
				'readonly'    => true,
				'cap'         => $cap,
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'status' => array( 'type' => 'string', 'enum' => array( '', 'open', 'bot', 'queued', 'agent', 'offline', 'closed' ) ),
						'q'      => array( 'type' => 'string' ),
						'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50 ),
					),
				),
				'run'         => function ( array $a ) {
					$rows = flxcb_query_conversations(
						array(
							'status' => sanitize_key( (string) ( $a['status'] ?? '' ) ),
							'q'      => sanitize_text_field( (string) ( $a['q'] ?? '' ) ),
							'limit'  => max( 1, min( 50, (int) ( $a['limit'] ?? 20 ) ) ),
						)
					);
					return array(
						'items' => array_map(
							function ( $c ) {
								$last = flxcb_last_message( (int) $c['id'] );
								return array(
									'id'      => (int) $c['id'],
									'title'   => flxcb_conv_title( $c ),
									'email'   => (string) $c['email'],
									'phone'   => (string) $c['phone'],
									'status'  => (string) $c['status'],
									'unread'  => (int) $c['unread'],
									'rating'  => (int) $c['rating'],
									'order'   => (int) $c['order_id'],
									'page'    => (string) $c['page_url'],
									'last'    => $last ? mb_substr( (string) $last['body'], 0, 160 ) : '',
									'updated' => flx_date( (int) floor( $c['last_ts'] / 1000 ), 'datetime' ),
									'url'     => flxcb_inbox_url( (int) $c['id'] ),
								);
							},
							$rows
						),
					);
				},
			)
		);
		flx_ai_tool(
			'flx-chatbot',
			'conversation',
			array(
				'title'       => __( 'Site sohbeti dökümü', 'flx-chatbot' ),
				'description' => 'Returns the transcript of one website chat conversation by id (visitor, assistant and agent messages, contact details, assistant handoff note).',
				'readonly'    => true,
				'cap'         => $cap,
				'schema'      => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'run'         => function ( array $a ) {
					$c = flxcb_get_conversation( (int) ( $a['id'] ?? 0 ) );
					if ( ! $c ) {
						return new WP_Error( 'flxcb_conv', __( 'Sohbet bulunamadı.', 'flx-chatbot' ) );
					}
					return array(
						'id'         => (int) $c['id'],
						'status'     => (string) $c['status'],
						'rating'     => (int) $c['rating'],
						'transcript' => flxcb_transcript( $c, 200 ),
						'url'        => flxcb_inbox_url( (int) $c['id'] ),
					);
				},
			)
		);
		flx_ai_tool(
			'flx-chatbot',
			'stats',
			array(
				'title'       => __( 'Sohbet istatistikleri', 'flx-chatbot' ),
				'description' => 'Website chat statistics for the last N days: conversations, handed to humans, resolved by the assistant (%), leads, orders after chat, average rating, open conversations.',
				'readonly'    => true,
				'cap'         => $cap,
				'schema'      => array(
					'type'       => 'object',
					'properties' => array( 'days' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 365 ) ),
				),
				'run'         => function ( array $a ) {
					$d = max( 1, min( 365, (int) ( $a['days'] ?? 30 ) ) );
					return array( 'days' => $d ) + flxcb_stats( $d );
				},
			)
		);
	}
);

add_filter(
	'flxai_purpose_labels',
	function ( $l ) {
		$l['chatbot'] = __( 'Site sohbeti', 'flx-chatbot' );
		return $l;
	}
);
