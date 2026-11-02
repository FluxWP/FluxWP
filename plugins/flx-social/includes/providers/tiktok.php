<?php
/**
 * TikTok Content Posting API.
 * Mod (Ayarlar): "inbox" = taslak olarak TikTok uygulamasına gönderir, kullanıcı uygulamadan yayınlar (audit gerekmez);
 * "direct" = doğrudan yayın (TikTok audit'i olmadan yalnız "Sadece ben" görünürlüğü).
 * Video FILE_UPLOAD ile parça parça yüklenir; fotoğraf (carousel) PULL_FROM_URL ister (alan adı TikTok'ta doğrulanmalı).
 */

defined( 'ABSPATH' ) || exit;

class FLXSM_TikTok extends FLXSM_Provider {

	const API   = 'https://open.tiktokapis.com/v2';
	const CHUNK = 10485760; // 10 MB

	public function id() {
		return 'tiktok';
	}

	public function label() {
		return 'TikTok';
	}

	public function color() {
		return '#000000';
	}

	public function caps( $acc = null ) {
		return array_merge(
			parent::caps( $acc ),
			array(
				'max_text'    => 2200,
				'max_images'  => 35,
				'needs_media' => true,
				'metrics'     => true,
				'options'     => array( 'tiktok_privacy', 'title', 'disable_comment', 'disable_duet', 'disable_stitch', 'brand_content', 'brand_organic', 'ai_generated', 'auto_music' ),
			)
		);
	}

	private function mode() {
		return 'direct' === flxsm_setting( 'tiktok_mode', 'inbox' ) ? 'direct' : 'inbox';
	}

	public function auth_url( array $ctx ) {
		$ok = $this->need_app( 'client_key', 'tiktok_secret' );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query(
			array(
				'client_key'    => flxsm_app( 'tiktok', 'client_key' ),
				'scope'         => 'user.info.basic,user.info.profile,user.info.stats,video.publish,video.upload,video.list',
				'response_type' => 'code',
				'redirect_uri'  => flxsm_redirect_uri( 'tiktok' ),
				'state'         => $ctx['state'],
			)
		);
	}

	private function token_req( array $body ) {
		$body['client_key']    = flxsm_app( 'tiktok', 'client_key' );
		$body['client_secret'] = flxsm_secret( 'tiktok_secret' );
		$r                     = flxsm_http( 'POST', self::API . '/oauth/token/', array( 'body' => $body ) );
		if ( ! $r['ok'] || ! empty( $r['json']['error'] ) && empty( $r['json']['access_token'] ) ) {
			return new WP_Error( 'flxsm_api', 'TikTok: ' . ( $r['json']['error_description'] ?? $r['error'] ), array( 'auth' => true ) );
		}
		return $r['json'];
	}

	private function user_info( $token ) {
		return flxsm_http(
			'GET',
			self::API . '/user/info/',
			array(
				'query'  => array( 'fields' => 'open_id,avatar_url,display_name,username,follower_count,video_count,likes_count' ),
				'bearer' => $token,
			)
		);
	}

	public function exchange( array $params, array $ctx ) {
		if ( ! empty( $params['error'] ) ) {
			return new WP_Error( 'flxsm_denied', (string) ( $params['error_description'] ?? $params['error'] ) );
		}
		$j = $this->token_req(
			array(
				'code'         => (string) ( $params['code'] ?? '' ),
				'grant_type'   => 'authorization_code',
				'redirect_uri' => flxsm_redirect_uri( 'tiktok' ),
			)
		);
		if ( is_wp_error( $j ) ) {
			return $j;
		}
		$u = $this->user_info( (string) $j['access_token'] );
		$d = (array) ( $u['json']['data']['user'] ?? array() );
		return array(
			array(
				'provider' => 'tiktok',
				'via'      => 'tiktok',
				'ext_id'   => (string) ( $j['open_id'] ?? $d['open_id'] ?? '' ),
				'name'     => (string) ( $d['display_name'] ?? '' ),
				'username' => (string) ( $d['username'] ?? '' ),
				'avatar'   => (string) ( $d['avatar_url'] ?? '' ),
				'token'    => array(
					'access_token'  => (string) $j['access_token'],
					'refresh_token' => (string) ( $j['refresh_token'] ?? '' ),
				),
				'expires'  => time() + (int) ( $j['expires_in'] ?? 86400 ),
				'meta'     => array( 'kind' => 'user' ),
			),
		);
	}

	public function refresh( array $acc ) {
		$rt = (string) ( $acc['token']['refresh_token'] ?? '' );
		if ( '' === $rt ) {
			return parent::refresh( $acc );
		}
		$j = $this->token_req(
			array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $rt,
			)
		);
		if ( is_wp_error( $j ) ) {
			return $j;
		}
		return array(
			'token'   => array(
				'access_token'  => (string) $j['access_token'],
				'refresh_token' => (string) ( $j['refresh_token'] ?? $rt ),
			),
			'expires' => time() + (int) ( $j['expires_in'] ?? 86400 ),
		);
	}

	private function api( array $acc, $path, array $body ) {
		$r = flxsm_http( 'POST', self::API . $path, array( 'json' => $body, 'bearer' => $acc['token']['access_token'] ) );
		$c = (string) ( $r['json']['error']['code'] ?? '' );
		if ( ! $r['ok'] || ( '' !== $c && 'ok' !== $c ) ) {
			$msg = (string) ( $r['json']['error']['message'] ?? $r['error'] );
			if ( false !== strpos( $c, 'privacy_level_option_mismatch' ) || false !== strpos( $c, 'unaudited' ) ) {
				$msg = __( 'TikTok uygulaması henüz denetlenmediği için yalnız "Sadece ben" görünürlüğü kullanılabilir.', 'flx-social' );
			}
			return new WP_Error(
				'flxsm_api',
				'TikTok: ' . $msg,
				array(
					'retry' => in_array( $c, array( 'rate_limit_exceeded', 'internal_error' ), true ) || $r['code'] >= 500,
					'auth'  => in_array( $c, array( 'access_token_invalid', 'scope_not_authorized' ), true ),
					'wait'  => 'rate_limit_exceeded' === $c ? 120 : 0,
				)
			);
		}
		return (array) ( $r['json']['data'] ?? array() );
	}

	public function publish_step( array $acc, array $pl, array $state ) {
		$o     = $pl['options'];
		$vid   = $this->first_video( $pl );
		$phase = (string) ( $state['phase'] ?? 'init' );
		$mode  = $this->mode();

		if ( 'init' === $phase ) {
			$privacy = (string) ( $o['tiktok_privacy'] ?? 'SELF_ONLY' );
			$info    = array(
				'title'                    => mb_substr( (string) ( $vid ? $pl['text'] : ( $o['title'] ?? '' ) ), 0, $vid ? 2200 : 90 ),
				'privacy_level'            => $privacy,
				'disable_comment'          => ! empty( $o['disable_comment'] ),
				'brand_content_toggle'     => ! empty( $o['brand_content'] ),
				'brand_organic_toggle'     => ! empty( $o['brand_organic'] ),
				'is_aigc'                  => ! empty( $o['ai_generated'] ),
			);
			if ( $vid ) {
				$info['disable_duet']   = ! empty( $o['disable_duet'] );
				$info['disable_stitch'] = ! empty( $o['disable_stitch'] );
				$size                   = (int) $vid['bytes'];
				$chunk                  = $size < 5242880 ? $size : self::CHUNK;
				$count                  = max( 1, (int) floor( $size / $chunk ) );
				$src                    = array(
					'source'            => 'FILE_UPLOAD',
					'video_size'        => $size,
					'chunk_size'        => $chunk,
					'total_chunk_count' => $count,
				);
				if ( 'direct' === $mode ) {
					$d = $this->api(
						$acc,
						'/post/publish/video/init/',
						array(
							'post_info'   => $info,
							'source_info' => $src,
						)
					);
				} else {
					$d = $this->api( $acc, '/post/publish/inbox/video/init/', array( 'source_info' => $src ) );
				}
				if ( is_wp_error( $d ) ) {
					return $d;
				}
				return array(
					'wait'  => 0,
					'state' => array(
						'phase' => 'upload',
						'pid'   => (string) $d['publish_id'],
						'url'   => (string) $d['upload_url'],
						'chunk' => $chunk,
						'count' => $count,
						'i'     => 0,
					),
				);
			}
			$info['description'] = mb_substr( (string) $pl['text'], 0, 4000 );
			$d                   = $this->api(
				$acc,
				'/post/publish/content/init/',
				array(
					'post_info'   => $info + array( 'auto_add_music' => ! empty( $o['auto_music'] ) ),
					'source_info' => array(
						'source'            => 'PULL_FROM_URL',
						'photo_cover_index' => 0,
						'photo_images'      => array_map( 'flxsm_jpeg_url', $this->images( $pl ) ),
					),
					'post_mode'   => 'direct' === $mode ? 'DIRECT_POST' : 'MEDIA_UPLOAD',
					'media_type'  => 'PHOTO',
				)
			);
			if ( is_wp_error( $d ) ) {
				return $d;
			}
			return array(
				'wait'  => 10,
				'state' => array(
					'phase' => 'status',
					'pid'   => (string) $d['publish_id'],
					'n'     => 0,
				),
			);
		}

		if ( 'upload' === $phase ) {
			$start = microtime( true );
			$size  = (int) $vid['bytes'];
			while ( $state['i'] < $state['count'] ) {
				if ( microtime( true ) - $start > 30 ) {
					return array(
						'wait'  => 1,
						'state' => $state,
					);
				}
				$from = $state['i'] * $state['chunk'];
				$to   = $state['i'] === $state['count'] - 1 ? $size - 1 : $from + $state['chunk'] - 1;
				$data = flxsm_read_chunk( $vid['path'], $from, $to - $from + 1 );
				$r    = flxsm_http(
					'PUT',
					$state['url'],
					array(
						'body'    => $data,
						'headers' => array(
							'Content-Type'  => $vid['mime'],
							'Content-Range' => "bytes {$from}-{$to}/{$size}",
						),
						'timeout' => 55,
					)
				);
				if ( ! $r['ok'] && 206 !== $r['code'] ) {
					return $this->err( $r, 'TikTok' );
				}
				++$state['i'];
			}
			return array(
				'wait'  => 10,
				'state' => array(
					'phase' => 'status',
					'pid'   => $state['pid'],
					'n'     => 0,
				),
			);
		}

		if ( 'status' === $phase ) {
			$d = $this->api( $acc, '/post/publish/status/fetch/', array( 'publish_id' => $state['pid'] ) );
			if ( is_wp_error( $d ) ) {
				return $d;
			}
			$st = (string) ( $d['status'] ?? '' );
			if ( 'FAILED' === $st ) {
				return new WP_Error( 'flxsm_api', 'TikTok: ' . (string) ( $d['fail_reason'] ?? 'failed' ), array( 'retry' => false ) );
			}
			if ( 'PUBLISH_COMPLETE' === $st || 'SEND_TO_USER_INBOX' === $st ) {
				$pid = (string) ( $d['publicaly_available_post_id'][0] ?? '' );
				$usr = $acc['username'] ? '@' . $acc['username'] : '';
				return array(
					'done' => true,
					'id'   => $pid ? $pid : $state['pid'],
					'url'  => $pid && $usr ? "https://www.tiktok.com/{$usr}/video/{$pid}" : ( $usr ? "https://www.tiktok.com/{$usr}" : '' ),
					'note' => 'SEND_TO_USER_INBOX' === $st ? __( 'Taslak TikTok uygulamasına gönderildi; uygulamadaki bildirimden yayınlayın.', 'flx-social' ) : '',
				);
			}
			$state['n'] = (int) $state['n'] + 1;
			if ( $state['n'] > 120 ) {
				return new WP_Error( 'flxsm_api', __( 'TikTok işlemesi uzun sürdü; TikTok uygulamasından kontrol edin.', 'flx-social' ), array( 'retry' => false ) );
			}
			return array(
				'wait'  => 15,
				'state' => $state,
			);
		}
		return new WP_Error( 'flxsm_state', 'TikTok: unknown state', array( 'retry' => false ) );
	}

	public function account_metrics( array $acc ) {
		$r = $this->user_info( $acc['token']['access_token'] );
		if ( ! $r['ok'] ) {
			return $this->err( $r, 'TikTok' );
		}
		$d = (array) ( $r['json']['data']['user'] ?? array() );
		return array(
			'followers' => (int) ( $d['follower_count'] ?? 0 ),
			'posts'     => (int) ( $d['video_count'] ?? 0 ),
			'likes'     => (int) ( $d['likes_count'] ?? 0 ),
		);
	}

	public function post_metrics( array $acc, $remote_id, array $target ) {
		if ( ! ctype_digit( (string) $remote_id ) ) {
			return new WP_Error( 'flxsm_unsupported', '' );
		}
		$r = flxsm_http(
			'POST',
			self::API . '/video/query/?fields=id,like_count,comment_count,share_count,view_count',
			array(
				'json'   => array( 'filters' => array( 'video_ids' => array( (string) $remote_id ) ) ),
				'bearer' => $acc['token']['access_token'],
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r, 'TikTok' );
		}
		$v = $r['json']['data']['videos'][0] ?? array();
		return array(
			'views'    => (int) ( $v['view_count'] ?? 0 ),
			'likes'    => (int) ( $v['like_count'] ?? 0 ),
			'comments' => (int) ( $v['comment_count'] ?? 0 ),
			'shares'   => (int) ( $v['share_count'] ?? 0 ),
		);
	}
}
