<?php
/**
 * Google: YouTube (Data API v3) ve Google İşletme Profili (Business Profile).
 * İkisi aynı Google Cloud OAuth istemcisini kullanır; kapsamlar bağlanırken seçilir.
 */

defined( 'ABSPATH' ) || exit;

abstract class FLXSM_Google_Base extends FLXSM_Provider {

	public function app() {
		return 'google';
	}

	abstract protected function scopes();

	public function auth_url( array $ctx ) {
		$ok = $this->need_app( 'client_id', 'google_secret' );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(
			array(
				'client_id'              => flxsm_app( 'google', 'client_id' ),
				'redirect_uri'           => flxsm_redirect_uri( 'google' ),
				'response_type'          => 'code',
				'scope'                  => implode( ' ', $this->scopes() ),
				'access_type'            => 'offline',
				'prompt'                 => 'consent select_account',
				'include_granted_scopes' => 'true',
				'state'                  => $ctx['state'],
			)
		);
	}

	protected function token_request( array $body ) {
		$body['client_id']     = flxsm_app( 'google', 'client_id' );
		$body['client_secret'] = flxsm_secret( 'google_secret' );
		$r                     = flxsm_http( 'POST', 'https://oauth2.googleapis.com/token', array( 'body' => $body ) );
		if ( ! $r['ok'] ) {
			return $this->err( $r );
		}
		return $r['json'];
	}

	protected function code_token( array $params ) {
		return $this->token_request(
			array(
				'code'         => (string) ( $params['code'] ?? '' ),
				'grant_type'   => 'authorization_code',
				'redirect_uri' => flxsm_redirect_uri( 'google' ),
			)
		);
	}

	public function refresh( array $acc ) {
		$rt = (string) ( $acc['token']['refresh_token'] ?? '' );
		if ( '' === $rt ) {
			return parent::refresh( $acc );
		}
		$j = $this->token_request(
			array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $rt,
			)
		);
		if ( is_wp_error( $j ) ) {
			$d         = (array) $j->get_error_data();
			$d['auth'] = true;
			return new WP_Error( 'flxsm_expired', $j->get_error_message(), $d );
		}
		return array(
			'token'   => array(
				'access_token'  => (string) $j['access_token'],
				'refresh_token' => (string) ( $j['refresh_token'] ?? $rt ),
			),
			'expires' => time() + (int) ( $j['expires_in'] ?? 3600 ),
		);
	}

	protected function tok( array $acc ) {
		return (string) ( $acc['token']['access_token'] ?? '' );
	}
}

class FLXSM_YouTube extends FLXSM_Google_Base {

	const API    = 'https://www.googleapis.com/youtube/v3';
	const CHUNK  = 8388608; // 8 MB (256 KB'nin katı)

	public function id() {
		return 'youtube';
	}

	public function label() {
		return 'YouTube';
	}

	public function color() {
		return '#ff0000';
	}

	protected function scopes() {
		return array( 'https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/youtube.force-ssl', 'https://www.googleapis.com/auth/youtube.readonly' );
	}

	public function caps( $acc = null ) {
		return array_merge(
			parent::caps( $acc ),
			array(
				'image'       => false,
				'max_text'    => 5000,
				'max_images'  => 0,
				'needs_media' => true,
				'edit'        => true,
				'delete'      => true,
				'comment'     => true,
				'metrics'     => true,
				'options'     => array( 'title', 'privacy', 'tags', 'flx_category', 'playlist', 'made_for_kids', 'notify', 'shorts', 'ai_generated', 'first_comment' ),
			)
		);
	}

	public function validate( array $pl, $acc ) {
		$errs = parent::validate( $pl, $acc );
		if ( ! $this->first_video( $pl ) ) {
			$errs[] = __( 'YouTube için bir video seçin.', 'flx-social' );
		}
		if ( flxsm_len( $this->title( $pl ) ) > 100 ) {
			$errs[] = __( 'YouTube başlığı en çok 100 karakter.', 'flx-social' );
		}
		if ( preg_match( '/[<>]/', $this->title( $pl ) . $pl['text'] ) ) {
			$errs[] = __( 'YouTube başlık ve açıklamada < ve > karakterlerini kabul etmiyor.', 'flx-social' );
		}
		return $errs;
	}

	private function title( array $pl ) {
		$t = trim( (string) ( $pl['options']['title'] ?? '' ) );
		if ( '' === $t ) {
			$t = trim( strtok( (string) $pl['text'], "\n" ) );
		}
		if ( '' === $t ) {
			$t = get_bloginfo( 'name' );
		}
		if ( ! empty( $pl['options']['shorts'] ) && false === stripos( $t, '#shorts' ) ) {
			$t = mb_substr( $t, 0, 92 ) . ' #Shorts';
		}
		return $t;
	}

	public function exchange( array $params, array $ctx ) {
		$j = $this->code_token( $params );
		if ( is_wp_error( $j ) ) {
			return $j;
		}
		$token = array(
			'access_token'  => (string) $j['access_token'],
			'refresh_token' => (string) ( $j['refresh_token'] ?? '' ),
		);
		$r     = flxsm_http(
			'GET',
			self::API . '/channels',
			array(
				'query'  => array(
					'part' => 'snippet,statistics',
					'mine' => 'true',
				),
				'bearer' => $token['access_token'],
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r );
		}
		$pl = flxsm_http(
			'GET',
			self::API . '/playlists',
			array(
				'query'  => array(
					'part'       => 'snippet',
					'mine'       => 'true',
					'maxResults' => '50',
				),
				'bearer' => $token['access_token'],
			)
		);
		$lists = array();
		foreach ( (array) ( $pl['json']['items'] ?? array() ) as $p ) {
			$lists[] = array(
				'id'   => (string) $p['id'],
				'name' => (string) $p['snippet']['title'],
			);
		}
		$out = array();
		foreach ( (array) ( $r['json']['items'] ?? array() ) as $c ) {
			$out[] = array(
				'provider' => 'youtube',
				'via'      => 'google',
				'ext_id'   => (string) $c['id'],
				'name'     => (string) $c['snippet']['title'],
				'username' => (string) ( $c['snippet']['customUrl'] ?? '' ),
				'avatar'   => (string) ( $c['snippet']['thumbnails']['default']['url'] ?? '' ),
				'token'    => $token,
				'expires'  => time() + (int) ( $j['expires_in'] ?? 3600 ),
				'meta'     => array(
					'kind'      => 'channel',
					'playlists' => $lists,
				),
			);
		}
		if ( ! $out ) {
			return new WP_Error( 'flxsm_none', __( 'Bu Google hesabında YouTube kanalı yok.', 'flx-social' ) );
		}
		return $out;
	}

	public function publish_step( array $acc, array $pl, array $state ) {
		$v = $this->first_video( $pl );
		if ( ! $v || ! $v['path'] || ! file_exists( $v['path'] ) ) {
			return new WP_Error( 'flxsm_media', __( 'Video dosyası sunucuda bulunamadı.', 'flx-social' ), array( 'retry' => false ) );
		}
		$o = $pl['options'];
		if ( empty( $state['upload'] ) ) {
			$snippet = array(
				'title'       => $this->title( $pl ),
				'description' => (string) $pl['text'],
				'categoryId'  => (string) ( $o['flx_category'] ?? '22' ),
			);
			if ( ! empty( $o['tags'] ) ) {
				$snippet['tags'] = array_slice( (array) $o['tags'], 0, 30 );
			}
			$status = array(
				'privacyStatus'           => in_array( $o['privacy'] ?? '', array( 'public', 'unlisted', 'private' ), true ) ? $o['privacy'] : 'public',
				'selfDeclaredMadeForKids' => ! empty( $o['made_for_kids'] ),
			);
			if ( ! empty( $o['ai_generated'] ) ) {
				$status['containsSyntheticMedia'] = true;
			}
			$r = flxsm_http(
				'POST',
				'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status&notifySubscribers=' . ( isset( $o['notify'] ) && ! $o['notify'] ? 'false' : 'true' ),
				array(
					'json'    => array(
						'snippet' => $snippet,
						'status'  => $status,
					),
					'bearer'  => $this->tok( $acc ),
					'headers' => array(
						'X-Upload-Content-Length' => (string) $v['bytes'],
						'X-Upload-Content-Type'   => $v['mime'],
					),
				)
			);
			if ( ! $r['ok'] ) {
				return $this->err( $r );
			}
			$loc = (string) ( $r['headers']['location'] ?? '' );
			if ( '' === $loc ) {
				return new WP_Error( 'flxsm_api', 'YouTube: upload URL missing', array( 'retry' => true ) );
			}
			$state = array(
				'upload' => $loc,
				'offset' => 0,
			);
		}
		if ( empty( $state['video_id'] ) ) {
			$start = microtime( true );
			$total = (int) $v['bytes'];
			$off   = (int) $state['offset'];
			while ( microtime( true ) - $start < 30 ) {
				$len  = min( self::CHUNK, $total - $off );
				$data = flxsm_read_chunk( $v['path'], $off, $len );
				if ( false === $data ) {
					return new WP_Error( 'flxsm_media', __( 'Video dosyası okunamadı.', 'flx-social' ), array( 'retry' => false ) );
				}
				$r = flxsm_http(
					'PUT',
					$state['upload'],
					array(
						'body'        => $data,
						'bearer'      => $this->tok( $acc ),
						'headers'     => array(
							'Content-Type'  => $v['mime'],
							'Content-Range' => 'bytes ' . $off . '-' . ( $off + $len - 1 ) . '/' . $total,
						),
						'timeout'     => 50,
						'redirection' => 0,
					)
				);
				if ( 308 === $r['code'] ) {
					$range = (string) ( $r['headers']['range'] ?? '' );
					$off   = preg_match( '/-(\d+)$/', $range, $m ) ? (int) $m[1] + 1 : $off + $len;
					$state['offset'] = $off;
					continue;
				}
				if ( $r['ok'] && ! empty( $r['json']['id'] ) ) {
					$state['video_id'] = (string) $r['json']['id'];
					break;
				}
				if ( 404 === $r['code'] ) {
					unset( $state['upload'] ); // oturum düştü, baştan
					return array(
						'wait'  => 5,
						'state' => array(),
					);
				}
				return $this->err( $r );
			}
			if ( empty( $state['video_id'] ) ) {
				return array(
					'wait'  => 1,
					'state' => $state,
				);
			}
		}
		$vid = $state['video_id'];
		if ( ! empty( $o['playlist'] ) && empty( $state['pl_done'] ) ) {
			flxsm_http(
				'POST',
				self::API . '/playlistItems?part=snippet',
				array(
					'json'   => array(
						'snippet' => array(
							'playlistId' => $o['playlist'],
							'resourceId' => array(
								'kind'    => 'youtube#video',
								'videoId' => $vid,
							),
						),
					),
					'bearer' => $this->tok( $acc ),
				)
			);
		}
		return array(
			'done' => true,
			'id'   => $vid,
			'url'  => ! empty( $o['shorts'] ) ? "https://www.youtube.com/shorts/{$vid}" : "https://www.youtube.com/watch?v={$vid}",
		);
	}

	public function edit( array $acc, $remote_id, array $pl ) {
		$r = flxsm_http(
			'GET',
			self::API . '/videos',
			array(
				'query'  => array(
					'part' => 'snippet',
					'id'   => $remote_id,
				),
				'bearer' => $this->tok( $acc ),
			)
		);
		if ( ! $r['ok'] || empty( $r['json']['items'][0] ) ) {
			return $r['ok'] ? new WP_Error( 'flxsm_api', __( 'Video YouTube’da bulunamadı.', 'flx-social' ) ) : $this->err( $r );
		}
		$sn                = $r['json']['items'][0]['snippet'];
		$sn['title']       = $this->title( $pl );
		$sn['description'] = (string) $pl['text'];
		if ( ! empty( $pl['options']['tags'] ) ) {
			$sn['tags'] = array_slice( (array) $pl['options']['tags'], 0, 30 );
		}
		unset( $sn['thumbnails'], $sn['localized'], $sn['channelTitle'], $sn['publishedAt'], $sn['channelId'], $sn['liveBroadcastContent'] );
		$u = flxsm_http(
			'PUT',
			self::API . '/videos?part=snippet',
			array(
				'json'   => array(
					'id'      => $remote_id,
					'snippet' => $sn,
				),
				'bearer' => $this->tok( $acc ),
			)
		);
		return $u['ok'] ? true : $this->err( $u );
	}

	public function delete( array $acc, $remote_id ) {
		$r = flxsm_http(
			'DELETE',
			self::API . '/videos',
			array(
				'query'  => array( 'id' => $remote_id ),
				'bearer' => $this->tok( $acc ),
			)
		);
		return $r['ok'] || 404 === $r['code'] ? true : $this->err( $r );
	}

	public function comment( array $acc, $remote_id, $text ) {
		$r = flxsm_http(
			'POST',
			self::API . '/commentThreads?part=snippet',
			array(
				'json'   => array(
					'snippet' => array(
						'videoId'         => $remote_id,
						'topLevelComment' => array( 'snippet' => array( 'textOriginal' => $text ) ),
					),
				),
				'bearer' => $this->tok( $acc ),
			)
		);
		return $r['ok'] ? (string) $r['json']['id'] : $this->err( $r );
	}

	public function account_metrics( array $acc ) {
		$r = flxsm_http(
			'GET',
			self::API . '/channels',
			array(
				'query'  => array(
					'part' => 'statistics,snippet',
					'id'   => $acc['ext_id'],
				),
				'bearer' => $this->tok( $acc ),
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r );
		}
		$s = $r['json']['items'][0]['statistics'] ?? array();
		return array(
			'followers' => (int) ( $s['subscriberCount'] ?? 0 ),
			'views'     => (int) ( $s['viewCount'] ?? 0 ),
			'posts'     => (int) ( $s['videoCount'] ?? 0 ),
			'avatar'    => (string) ( $r['json']['items'][0]['snippet']['thumbnails']['default']['url'] ?? '' ),
		);
	}

	public function post_metrics( array $acc, $remote_id, array $target ) {
		$r = flxsm_http(
			'GET',
			self::API . '/videos',
			array(
				'query'  => array(
					'part' => 'statistics',
					'id'   => $remote_id,
				),
				'bearer' => $this->tok( $acc ),
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r );
		}
		$s = $r['json']['items'][0]['statistics'] ?? array();
		return array(
			'views'    => (int) ( $s['viewCount'] ?? 0 ),
			'likes'    => (int) ( $s['likeCount'] ?? 0 ),
			'comments' => (int) ( $s['commentCount'] ?? 0 ),
		);
	}
}

class FLXSM_GBP extends FLXSM_Google_Base {

	const V4 = 'https://mybusiness.googleapis.com/v4/';

	public function id() {
		return 'gbp';
	}

	public function label() {
		return 'Google İşletme';
	}

	public function color() {
		return '#4285f4';
	}

	protected function scopes() {
		return array( 'https://www.googleapis.com/auth/business.manage' );
	}

	public function caps( $acc = null ) {
		return array_merge(
			parent::caps( $acc ),
			array(
				'video'      => false,
				'max_text'   => 1500,
				'max_images' => 1,
				'max_videos' => 0,
				'edit'       => true,
				'delete'     => true,
				'options'    => array( 'gbp_topic', 'gbp_cta', 'gbp_cta_url', 'event_title', 'event_start', 'event_end', 'offer_code' ),
			)
		);
	}

	public function validate( array $pl, $acc ) {
		$errs = parent::validate( $pl, $acc );
		$t    = (string) ( $pl['options']['gbp_topic'] ?? 'STANDARD' );
		if ( in_array( $t, array( 'EVENT', 'OFFER' ), true ) && ( empty( $pl['options']['event_title'] ) || empty( $pl['options']['event_start'] ) ) ) {
			$errs[] = __( 'Etkinlik/teklif gönderisi için başlık ve başlangıç tarihi gerekli.', 'flx-social' );
		}
		return $errs;
	}

	public function exchange( array $params, array $ctx ) {
		$j = $this->code_token( $params );
		if ( is_wp_error( $j ) ) {
			return $j;
		}
		$token = array(
			'access_token'  => (string) $j['access_token'],
			'refresh_token' => (string) ( $j['refresh_token'] ?? '' ),
		);
		$r     = flxsm_http( 'GET', 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts', array( 'bearer' => $token['access_token'] ) );
		if ( ! $r['ok'] ) {
			return $this->err( $r, __( 'İşletme hesapları alınamadı (Google’ın Business Profile API erişim onayı gerekir)', 'flx-social' ) );
		}
		$out = array();
		foreach ( (array) ( $r['json']['accounts'] ?? array() ) as $a ) {
			$l = flxsm_http(
				'GET',
				'https://mybusinessbusinessinformation.googleapis.com/v1/' . $a['name'] . '/locations',
				array(
					'query'  => array(
						'readMask' => 'name,title,metadata,storefrontAddress',
						'pageSize' => '100',
					),
					'bearer' => $token['access_token'],
				)
			);
			foreach ( (array) ( $l['json']['locations'] ?? array() ) as $loc ) {
				$out[] = array(
					'provider' => 'gbp',
					'via'      => 'google',
					'ext_id'   => $a['name'] . '/' . $loc['name'],
					'name'     => (string) $loc['title'],
					'username' => (string) ( $loc['storefrontAddress']['locality'] ?? '' ),
					'avatar'   => '',
					'token'    => $token,
					'expires'  => time() + (int) ( $j['expires_in'] ?? 3600 ),
					'meta'     => array(
						'kind'    => 'location',
						'maps'    => (string) ( $loc['metadata']['mapsUri'] ?? '' ),
						'account' => (string) $a['name'],
					),
				);
			}
		}
		if ( ! $out ) {
			return new WP_Error( 'flxsm_none', __( 'Bu Google hesabında yönetilen işletme konumu bulunamadı.', 'flx-social' ) );
		}
		return $out;
	}

	private function body( array $pl ) {
		$o     = $pl['options'];
		$topic = in_array( $o['gbp_topic'] ?? '', array( 'STANDARD', 'EVENT', 'OFFER' ), true ) ? $o['gbp_topic'] : 'STANDARD';
		$b     = array(
			'languageCode' => substr( get_locale(), 0, 2 ),
			'summary'      => (string) $pl['text'],
			'topicType'    => $topic,
		);
		$img   = $this->images( $pl );
		if ( $img ) {
			$b['media'] = array(
				array(
					'mediaFormat' => 'PHOTO',
					'sourceUrl'   => $img[0]['url'],
				),
			);
		}
		if ( ! empty( $o['gbp_cta'] ) && 'OFFER' !== $topic ) {
			$b['callToAction'] = array( 'actionType' => $o['gbp_cta'] );
			if ( 'CALL' !== $o['gbp_cta'] ) {
				$b['callToAction']['url'] = (string) ( $o['gbp_cta_url'] ?? home_url( '/' ) );
			}
		}
		if ( in_array( $topic, array( 'EVENT', 'OFFER' ), true ) ) {
			$d  = function ( $s ) {
				$t = strtotime( (string) $s );
				return $t ? array(
					'year'  => (int) gmdate( 'Y', $t ),
					'month' => (int) gmdate( 'n', $t ),
					'day'   => (int) gmdate( 'j', $t ),
				) : null;
			};
			$st = $d( $o['event_start'] ?? '' );
			$en = $d( ! empty( $o['event_end'] ) ? $o['event_end'] : ( $o['event_start'] ?? '' ) );
			$b['event'] = array(
				'title'    => (string) ( $o['event_title'] ?? '' ),
				'schedule' => array(
					'startDate' => $st,
					'endDate'   => $en,
				),
			);
			if ( 'OFFER' === $topic && ! empty( $o['offer_code'] ) ) {
				$b['offer'] = array( 'couponCode' => $o['offer_code'] );
			}
		}
		return $b;
	}

	public function publish_step( array $acc, array $pl, array $state ) {
		$r = flxsm_http( 'POST', self::V4 . $acc['ext_id'] . '/localPosts', array( 'json' => $this->body( $pl ), 'bearer' => $this->tok( $acc ) ) );
		if ( ! $r['ok'] ) {
			return $this->err( $r );
		}
		return array(
			'done' => true,
			'id'   => (string) $r['json']['name'],
			'url'  => (string) ( $r['json']['searchUrl'] ?? '' ),
		);
	}

	public function edit( array $acc, $remote_id, array $pl ) {
		$r = flxsm_http( 'PATCH', self::V4 . $remote_id . '?updateMask=summary', array( 'json' => array( 'summary' => (string) $pl['text'] ), 'bearer' => $this->tok( $acc ) ) );
		return $r['ok'] ? true : $this->err( $r );
	}

	public function delete( array $acc, $remote_id ) {
		$r = flxsm_http( 'DELETE', self::V4 . $remote_id, array( 'bearer' => $this->tok( $acc ) ) );
		return $r['ok'] || 404 === $r['code'] ? true : $this->err( $r );
	}
}
