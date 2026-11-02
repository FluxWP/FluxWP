<?php
/**
 * Pinterest API v5: pin (görsel, 2–5 görsel carousel, video). Pano bağlanırken listelenir, editörde seçilir.
 * Deneme (trial) erişiminde yalnız sandbox çalışır: Ayarlar'da "Sandbox" açılır.
 */

defined( 'ABSPATH' ) || exit;

class FLXSM_Pinterest extends FLXSM_Provider {

	public function id() {
		return 'pinterest';
	}

	public function label() {
		return 'Pinterest';
	}

	public function color() {
		return '#e60023';
	}

	private function api() {
		return flxsm_setting( 'pin_sandbox', 0 ) ? 'https://api-sandbox.pinterest.com/v5' : 'https://api.pinterest.com/v5';
	}

	public function caps( $acc = null ) {
		return array_merge(
			parent::caps( $acc ),
			array(
				'max_text'    => 800,
				'max_images'  => 5,
				'needs_media' => true,
				'edit'        => true,
				'delete'      => true,
				'metrics'     => true,
				'options'     => array( 'board', 'title', 'link', 'alt' ),
			)
		);
	}

	public function validate( array $pl, $acc ) {
		$errs = parent::validate( $pl, $acc );
		if ( empty( $pl['options']['board'] ) ) {
			$errs[] = __( 'Pinterest panosu seçin.', 'flx-social' );
		}
		if ( flxsm_len( (string) ( $pl['options']['title'] ?? '' ) ) > 100 ) {
			$errs[] = __( 'Pinterest başlığı en çok 100 karakter.', 'flx-social' );
		}
		return $errs;
	}

	public function auth_url( array $ctx ) {
		$ok = $this->need_app( 'app_id', 'pinterest_secret' );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return 'https://www.pinterest.com/oauth/?' . http_build_query(
			array(
				'client_id'     => flxsm_app( 'pinterest', 'app_id' ),
				'redirect_uri'  => flxsm_redirect_uri( 'pinterest' ),
				'response_type' => 'code',
				'scope'         => 'boards:read,boards:write,pins:read,pins:write,user_accounts:read',
				'state'         => $ctx['state'],
			)
		);
	}

	private function token_req( array $body ) {
		$r = flxsm_http(
			'POST',
			'https://api.pinterest.com/v5/oauth/token',
			array(
				'body'    => $body,
				'headers' => array( 'Authorization' => 'Basic ' . base64_encode( flxsm_app( 'pinterest', 'app_id' ) . ':' . flxsm_secret( 'pinterest_secret' ) ) ),
			)
		);
		return $r['ok'] ? $r['json'] : $this->err( $r, 'Pinterest' );
	}

	private function boards( $token ) {
		$out = array();
		$r   = flxsm_http(
			'GET',
			$this->api() . '/boards',
			array(
				'query'  => array( 'page_size' => '100' ),
				'bearer' => $token,
			)
		);
		foreach ( (array) ( $r['json']['items'] ?? array() ) as $b ) {
			$out[] = array(
				'id'   => (string) $b['id'],
				'name' => (string) $b['name'],
			);
		}
		return $out;
	}

	public function exchange( array $params, array $ctx ) {
		$j = $this->token_req(
			array(
				'grant_type'   => 'authorization_code',
				'code'         => (string) ( $params['code'] ?? '' ),
				'redirect_uri' => flxsm_redirect_uri( 'pinterest' ),
			)
		);
		if ( is_wp_error( $j ) ) {
			return $j;
		}
		$tok = (string) $j['access_token'];
		$u   = flxsm_http( 'GET', $this->api() . '/user_account', array( 'bearer' => $tok ) );
		if ( ! $u['ok'] ) {
			return $this->err( $u, 'Pinterest' );
		}
		return array(
			array(
				'provider' => 'pinterest',
				'via'      => 'pinterest',
				'ext_id'   => (string) ( $u['json']['id'] ?? $u['json']['username'] ),
				'name'     => (string) ( $u['json']['business_name'] ?? $u['json']['username'] ?? '' ),
				'username' => (string) ( $u['json']['username'] ?? '' ),
				'avatar'   => (string) ( $u['json']['profile_image'] ?? '' ),
				'token'    => array(
					'access_token'  => $tok,
					'refresh_token' => (string) ( $j['refresh_token'] ?? '' ),
				),
				'expires'  => time() + (int) ( $j['expires_in'] ?? 2592000 ),
				'meta'     => array(
					'kind'   => strtolower( (string) ( $u['json']['account_type'] ?? 'business' ) ),
					'boards' => $this->boards( $tok ),
				),
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
			return new WP_Error( 'flxsm_expired', $j->get_error_message(), array( 'auth' => true ) );
		}
		return array(
			'token'   => array(
				'access_token'  => (string) $j['access_token'],
				'refresh_token' => (string) ( $j['refresh_token'] ?? $rt ),
			),
			'expires' => time() + (int) ( $j['expires_in'] ?? 2592000 ),
		);
	}

	/** Panoları yeniden okur (Hesaplar › Yenile). */
	public function reload_boards( array $acc ) {
		$b = $this->boards( $acc['token']['access_token'] );
		if ( $b ) {
			$acc['meta']['boards'] = $b;
			flxsm_update_account( $acc['id'], array( 'meta' => $acc['meta'] ) );
		}
		return $b;
	}

	public function publish_step( array $acc, array $pl, array $state ) {
		$o   = $pl['options'];
		$tok = $acc['token']['access_token'];
		$vid = $this->first_video( $pl );
		$pin = array(
			'board_id'    => (string) $o['board'],
			'title'       => mb_substr( (string) ( $o['title'] ?? '' ), 0, 100 ),
			'description' => mb_substr( (string) $pl['text'], 0, 800 ),
		);
		if ( ! empty( $o['link'] ) ) {
			$pin['link'] = $o['link'];
		}
		if ( ! empty( $o['alt'] ) ) {
			$pin['alt_text'] = mb_substr( $o['alt'], 0, 500 );
		}
		if ( $vid ) {
			$phase = (string) ( $state['phase'] ?? 'register' );
			if ( 'register' === $phase ) {
				$r = flxsm_http( 'POST', $this->api() . '/media', array( 'json' => array( 'media_type' => 'video' ), 'bearer' => $tok ) );
				if ( ! $r['ok'] ) {
					return $this->err( $r, 'Pinterest' );
				}
				$fields = (array) ( $r['json']['upload_parameters'] ?? array() );
				$mp     = flxsm_multipart(
					$fields,
					array(
						'file' => array(
							'path'     => $vid['path'],
							'mime'     => $vid['mime'],
							'filename' => basename( $vid['path'] ),
						),
					)
				);
				$u      = flxsm_http(
					'POST',
					(string) $r['json']['upload_url'],
					array(
						'body'    => $mp['body'],
						'headers' => array( 'Content-Type' => $mp['type'] ),
						'timeout' => 55,
					)
				);
				if ( ! $u['ok'] && 204 !== $u['code'] ) {
					return $this->err( $u, 'Pinterest' );
				}
				return array(
					'wait'  => 10,
					'state' => array(
						'phase' => 'wait',
						'mid'   => (string) $r['json']['media_id'],
						'n'     => 0,
					),
				);
			}
			$r = flxsm_http( 'GET', $this->api() . '/media/' . rawurlencode( $state['mid'] ), array( 'bearer' => $tok ) );
			if ( ! $r['ok'] ) {
				return $this->err( $r, 'Pinterest' );
			}
			$st = (string) ( $r['json']['status'] ?? '' );
			if ( 'failed' === $st ) {
				return new WP_Error( 'flxsm_api', __( 'Pinterest videoyu işleyemedi.', 'flx-social' ), array( 'retry' => false ) );
			}
			if ( 'succeeded' !== $st ) {
				$state['n'] = (int) $state['n'] + 1;
				if ( $state['n'] > 60 ) {
					return new WP_Error( 'flxsm_api', __( 'Pinterest video işlemesi uzun sürdü.', 'flx-social' ), array( 'retry' => false ) );
				}
				return array(
					'wait'  => 15,
					'state' => $state,
				);
			}
			$pin['media_source'] = array(
				'source_type' => 'video_id',
				'media_id'    => $state['mid'],
			);
			$cover               = $this->images( $pl );
			if ( $cover ) {
				$pin['media_source']['cover_image_url'] = $cover[0]['url'];
			} else {
				$pin['media_source']['cover_image_key_frame_time'] = 1;
			}
		} else {
			$imgs = $this->images( $pl );
			if ( count( $imgs ) > 1 ) {
				$pin['media_source'] = array(
					'source_type' => 'multiple_image_urls',
					'items'       => array_map(
						fn( $m ) => array(
							'url'   => $m['url'],
							'title' => $pin['title'],
							'link'  => $pin['link'] ?? '',
						),
						$imgs
					),
				);
			} else {
				$pin['media_source'] = array(
					'source_type' => 'image_url',
					'url'         => $imgs[0]['url'],
				);
			}
		}
		$r = flxsm_http( 'POST', $this->api() . '/pins', array( 'json' => $pin, 'bearer' => $tok ) );
		if ( ! $r['ok'] ) {
			return $this->err( $r, 'Pinterest' );
		}
		$id = (string) $r['json']['id'];
		return array(
			'done' => true,
			'id'   => $id,
			'url'  => 'https://www.pinterest.com/pin/' . $id . '/',
		);
	}

	public function edit( array $acc, $remote_id, array $pl ) {
		$b = array( 'description' => mb_substr( (string) $pl['text'], 0, 800 ) );
		foreach ( array( 'title', 'link' ) as $k ) {
			if ( isset( $pl['options'][ $k ] ) ) {
				$b[ $k ] = $pl['options'][ $k ];
			}
		}
		$r = flxsm_http( 'PATCH', $this->api() . '/pins/' . rawurlencode( $remote_id ), array( 'json' => $b, 'bearer' => $acc['token']['access_token'] ) );
		return $r['ok'] ? true : $this->err( $r, 'Pinterest' );
	}

	public function delete( array $acc, $remote_id ) {
		$r = flxsm_http( 'DELETE', $this->api() . '/pins/' . rawurlencode( $remote_id ), array( 'bearer' => $acc['token']['access_token'] ) );
		return $r['ok'] || 404 === $r['code'] ? true : $this->err( $r, 'Pinterest' );
	}

	public function account_metrics( array $acc ) {
		$r = flxsm_http( 'GET', $this->api() . '/user_account', array( 'bearer' => $acc['token']['access_token'] ) );
		if ( ! $r['ok'] ) {
			return $this->err( $r, 'Pinterest' );
		}
		return array(
			'followers' => (int) ( $r['json']['follower_count'] ?? 0 ),
			'posts'     => (int) ( $r['json']['pin_count'] ?? 0 ),
			'views'     => (int) ( $r['json']['monthly_views'] ?? 0 ),
		);
	}

	public function post_metrics( array $acc, $remote_id, array $target ) {
		$r = flxsm_http(
			'GET',
			$this->api() . '/pins/' . rawurlencode( $remote_id ) . '/analytics',
			array(
				'query'  => array(
					'start_date'   => gmdate( 'Y-m-d', max( (int) $target['published_at'], time() - 89 * DAY_IN_SECONDS ) ),
					'end_date'     => gmdate( 'Y-m-d' ),
					'metric_types' => 'IMPRESSION,SAVE,PIN_CLICK,OUTBOUND_CLICK',
				),
				'bearer' => $acc['token']['access_token'],
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r, 'Pinterest' );
		}
		$s = $r['json']['all']['lifetime_metrics'] ?? ( $r['json']['all']['summary_metrics'] ?? array() );
		return array(
			'impressions' => (int) ( $s['IMPRESSION'] ?? 0 ),
			'saves'       => (int) ( $s['SAVE'] ?? 0 ),
			'clicks'      => (int) ( $s['PIN_CLICK'] ?? 0 ) + (int) ( $s['OUTBOUND_CLICK'] ?? 0 ),
		);
	}
}
