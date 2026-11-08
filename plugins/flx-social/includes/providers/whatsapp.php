<?php
/**
 * WhatsApp: Evolution API üzerinden Durum (status), gruplar ve kanallar (deneysel).
 * Bağlantı ayarı pakette tutulmaz: Flux Mail (flxi_get_channel_connection) ya da Flux Chat'nden okunur.
 * Resmî WhatsApp Business API'de grup/kanal/durum paylaşımı yok; bu yol Evolution (WhatsApp Web) ile çalışır.
 */

defined( 'ABSPATH' ) || exit;

/** Evolution bağlantısı: base_url, api_key, instance. */
function flxsm_wa_config( $conn_id = 0 ) {
	$conn_id = (int) ( $conn_id ? $conn_id : flxsm_setting( 'wa_conn', 0 ) );
	if ( function_exists( 'flxi_get_channel_connection' ) ) {
		$c = flxi_get_channel_connection( 'whatsapp', $conn_id );
		if ( $c && ! empty( $c['config']['base_url'] ) ) {
			return array(
				'base_url' => (string) $c['config']['base_url'],
				'api_key'  => (string) ( $c['config']['api_key'] ?? '' ),
				'instance' => (string) ( $c['config']['instance'] ?? '' ),
				'name'     => (string) ( $c['name'] ?? '' ),
			);
		}
	}
	if ( function_exists( 'flxc_default_connection' ) && function_exists( 'flxc_connection_config' ) ) {
		$conn = flxc_default_connection();
		$cfg  = $conn ? flxc_connection_config( $conn ) : null;
		if ( is_array( $cfg ) && ! empty( $cfg['base_url'] ) ) {
			return array(
				'base_url' => (string) $cfg['base_url'],
				'api_key'  => (string) $cfg['api_key'],
				'instance' => (string) $cfg['instance'],
				'name'     => (string) ( $conn['name'] ?? 'WhatsApp' ),
			);
		}
	}
	return new WP_Error( 'flxsm_wa', __( 'No WhatsApp connection. Set up an Evolution connection in Flux Mail or Flux Chat.', 'flx-social' ), array( 'status' => 400 ) );
}

function flxsm_wa_choices() {
	return function_exists( 'flxi_connection_choices' ) ? flxi_connection_choices( 'whatsapp' ) : array();
}

class FLXSM_WhatsApp extends FLXSM_Provider {

	public function id() {
		return 'whatsapp';
	}

	public function label() {
		return 'WhatsApp';
	}

	public function color() {
		return '#25d366';
	}

	public function caps( $acc = null ) {
		$kind = is_array( $acc ) ? ( ( is_array( $acc['meta'] ) ? $acc['meta'] : flxsm_dec( $acc['meta'] ) )['kind'] ?? 'group' ) : 'group';
		return array_merge(
			parent::caps( $acc ),
			array(
				'max_text'   => 'status' === $kind ? 700 : 4096,
				'max_images' => 'status' === $kind ? 1 : 10,
				'gif'        => false,
				'edit'       => 'status' !== $kind,
				'delete'     => true,
				'metrics'    => 'group' === $kind,
				'options'    => array(),
			)
		);
	}

	public function evo( $method, $path, $body = null, $timeout = 30 ) {
		$cfg = flxsm_wa_config();
		if ( is_wp_error( $cfg ) ) {
			return array( 'ok' => false, 'code' => 0, 'json' => null, 'body' => '', 'headers' => array(), 'error' => $cfg->get_error_message() );
		}
		$args = array(
			'headers' => array( 'apikey' => $cfg['api_key'] ),
			'timeout' => $timeout,
		);
		if ( null !== $body ) {
			$args['json'] = $body;
		}
		return flxsm_http( $method, untrailingslashit( $cfg['base_url'] ) . str_replace( '{i}', rawurlencode( $cfg['instance'] ), $path ), $args );
	}

	/** Eklenebilecek hedefler: Durum + gruplar. */
	public function targets() {
		$cfg = flxsm_wa_config();
		if ( is_wp_error( $cfg ) ) {
			return $cfg;
		}
		$out = array(
			array(
				'ext_id' => 'status@' . $cfg['instance'],
				'name'   => __( 'WhatsApp Status', 'flx-social' ),
				'kind'   => 'status',
			),
		);
		$r   = $this->evo( 'GET', '/group/fetchAllGroups/{i}?getParticipants=false', null, 45 );
		if ( ! $r['ok'] ) {
			return new WP_Error( 'flxsm_api', 'Evolution: ' . $r['error'] );
		}
		foreach ( (array) $r['json'] as $g ) {
			if ( empty( $g['id'] ) ) {
				continue;
			}
			$out[] = array(
				'ext_id' => (string) $g['id'],
				'name'   => (string) ( $g['subject'] ?? $g['id'] ),
				'kind'   => 'group',
				'size'   => (int) ( $g['size'] ?? 0 ),
				'avatar' => (string) ( $g['pictureUrl'] ?? '' ),
			);
		}
		return $out;
	}

	public function publish_step( array $acc, array $pl, array $state ) {
		$kind  = (string) ( $acc['meta']['kind'] ?? 'group' );
		$text  = (string) $pl['text'];
		$media = array_values( $pl['media'] );
		if ( 'status' === $kind ) {
			$m    = $media[0] ?? null;
			$body = $m ? array(
				'type'        => 'video' === $m['kind'] ? 'video' : 'image',
				'content'     => $m['url'],
				'caption'     => $text,
				'allContacts' => true,
			) : array(
				'type'            => 'text',
				'content'         => $text,
				'backgroundColor' => '#1f7a4d',
				'font'            => 1,
				'allContacts'     => true,
			);
			$r    = $this->evo( 'POST', '/message/sendStatus/{i}', $body, 60 );
			if ( ! $r['ok'] ) {
				return $this->err( $r, 'WhatsApp' );
			}
			return array(
				'done' => true,
				'id'   => 'status@broadcast|' . (string) ( $r['json']['key']['id'] ?? '' ),
				'url'  => '',
			);
		}
		$jid  = $acc['ext_id'];
		$sent = (array) ( $state['sent'] ?? array() );
		if ( ! $media ) {
			$r = $this->evo(
				'POST',
				'/message/sendText/{i}',
				array(
					'number'      => $jid,
					'text'        => $text,
					'linkPreview' => true,
				)
			);
			if ( ! $r['ok'] ) {
				return $this->err( $r, 'WhatsApp' );
			}
			return array(
				'done' => true,
				'id'   => $jid . '|' . (string) ( $r['json']['key']['id'] ?? '' ),
				'url'  => '',
			);
		}
		foreach ( $media as $i => $m ) {
			if ( isset( $sent[ $i ] ) ) {
				continue;
			}
			$r = $this->evo(
				'POST',
				'/message/sendMedia/{i}',
				array(
					'number'    => $jid,
					'mediatype' => 'video' === $m['kind'] ? 'video' : 'image',
					'mimetype'  => $m['mime'],
					'media'     => $m['url'],
					'fileName'  => basename( $m['path'] ),
					'caption'   => 0 === $i ? $text : '',
				),
				60
			);
			if ( ! $r['ok'] ) {
				return $this->err( $r, 'WhatsApp' );
			}
			$sent[ $i ] = (string) ( $r['json']['key']['id'] ?? '' );
			if ( count( $sent ) < count( $media ) ) {
				return array(
					'wait'  => 2,
					'state' => array( 'sent' => $sent ),
				);
			}
		}
		return array(
			'done' => true,
			'id'   => $jid . '|' . (string) $sent[0],
			'url'  => '',
		);
	}

	private function split( $remote_id ) {
		$p = explode( '|', (string) $remote_id, 2 );
		return array( $p[0], $p[1] ?? '' );
	}

	public function edit( array $acc, $remote_id, array $pl ) {
		list( $jid, $id ) = $this->split( $remote_id );
		$r = $this->evo(
			'POST',
			'/chat/updateMessage/{i}',
			array(
				'number' => $jid,
				'key'    => array(
					'remoteJid' => $jid,
					'fromMe'    => true,
					'id'        => $id,
				),
				'text'   => (string) $pl['text'],
			)
		);
		return $r['ok'] ? true : $this->err( $r, 'WhatsApp' );
	}

	public function delete( array $acc, $remote_id ) {
		list( $jid, $id ) = $this->split( $remote_id );
		$r = $this->evo(
			'DELETE',
			'/chat/deleteMessageForEveryone/{i}',
			array(
				'id'        => $id,
				'remoteJid' => $jid,
				'fromMe'    => true,
			)
		);
		return $r['ok'] ? true : $this->err( $r, 'WhatsApp' );
	}

	public function account_metrics( array $acc ) {
		if ( 'group' !== ( $acc['meta']['kind'] ?? '' ) ) {
			return new WP_Error( 'flxsm_unsupported', '' );
		}
		$r = $this->evo( 'GET', '/group/findGroupInfos/{i}?groupJid=' . rawurlencode( $acc['ext_id'] ) );
		if ( ! $r['ok'] ) {
			return $this->err( $r, 'WhatsApp' );
		}
		return array( 'followers' => (int) ( $r['json']['size'] ?? count( (array) ( $r['json']['participants'] ?? array() ) ) ) );
	}
}
