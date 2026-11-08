<?php
/**
 * Meta: Facebook Sayfa ve Instagram (profesyonel hesap).
 * Instagram iki yoldan bağlanır: Facebook girişiyle (sayfaya bağlı hesap, graph.facebook.com)
 * ya da Instagram girişiyle (sayfa gerekmez, graph.instagram.com).
 * Medyayı Meta sunucusu sitenin herkese açık adresinden kendisi indirir; bizim sunucuya yükleme yükü yok.
 */

defined( 'ABSPATH' ) || exit;

function flxsm_meta_version() {
	$v = (string) flxsm_setting( 'meta_version', 'v25.0' );
	return preg_match( '/^v\d+\.\d+$/', $v ) ? $v : 'v25.0';
}

/** Instagram yalnız JPEG kabul eder: PNG/WebP için uploads/flxsm altında JPEG kopya. */
function flxsm_jpeg_url( array $m ) {
	if ( in_array( $m['mime'], array( 'image/jpeg', 'image/jpg' ), true ) || 'video' === $m['kind'] ) {
		return $m['url'];
	}
	$up  = wp_upload_dir( null, false );
	$dir = trailingslashit( $up['basedir'] ) . 'flxsm';
	$fn  = 'jpg-' . (int) $m['id'] . '-' . substr( md5( $m['path'] . filemtime( $m['path'] ) ), 0, 8 ) . '.jpg';
	if ( ! file_exists( "{$dir}/{$fn}" ) ) {
		wp_mkdir_p( $dir );
		$ed = wp_get_image_editor( $m['path'] );
		if ( is_wp_error( $ed ) ) {
			return $m['url'];
		}
		$ed->set_quality( 90 );
		$s = $ed->save( "{$dir}/{$fn}", 'image/jpeg' );
		if ( is_wp_error( $s ) ) {
			return $m['url'];
		}
	}
	$url = trailingslashit( $up['baseurl'] ) . 'flxsm/' . $fn;
	return 0 === strpos( $url, 'http://' ) && is_ssl() ? 'https://' . substr( $url, 7 ) : $url;
}

abstract class FLXSM_Meta_Base extends FLXSM_Provider {

	public function app() {
		return 'meta';
	}

	protected function g( $acc = null ) {
		$host = is_array( $acc ) && 'instagram' === ( $acc['via'] ?? '' ) ? 'https://graph.instagram.com' : 'https://graph.facebook.com';
		return $host . '/' . flxsm_meta_version();
	}

	protected function tok( array $acc ) {
		return (string) ( $acc['token']['access_token'] ?? '' );
	}

	public function auth_url( array $ctx ) {
		$ok = $this->need_app( 'app_id', 'meta_secret' );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$args = array(
			'client_id'     => flxsm_app( 'meta', 'app_id' ),
			'redirect_uri'  => flxsm_redirect_uri( 'meta' ),
			'state'         => $ctx['state'],
			'response_type' => 'code',
		);
		$cfg = flxsm_app( 'meta', 'config_id' );
		if ( $cfg ) {
			$args['config_id'] = $cfg; // Facebook Login for Business yapılandırması
		} else {
			$args['scope'] = implode(
				',',
				array( 'pages_show_list', 'pages_read_engagement', 'pages_manage_posts', 'pages_manage_engagement', 'read_insights', 'business_management', 'instagram_basic', 'instagram_content_publish', 'instagram_manage_comments', 'instagram_manage_insights' )
			);
		}
		return 'https://www.facebook.com/' . flxsm_meta_version() . '/dialog/oauth?' . http_build_query( $args );
	}

	/** Facebook girişi: sayfalar + sayfalara bağlı Instagram hesapları. */
	public function exchange( array $params, array $ctx ) {
		$base = 'https://graph.facebook.com/' . flxsm_meta_version();
		$r    = flxsm_http(
			'GET',
			$base . '/oauth/access_token',
			array(
				'query' => array(
					'client_id'     => flxsm_app( 'meta', 'app_id' ),
					'client_secret' => flxsm_secret( 'meta_secret' ),
					'redirect_uri'  => flxsm_redirect_uri( 'meta' ),
					'code'          => (string) ( $params['code'] ?? '' ),
				),
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r );
		}
		$short = (string) $r['json']['access_token'];
		$r     = flxsm_http(
			'GET',
			$base . '/oauth/access_token',
			array(
				'query' => array(
					'grant_type'        => 'fb_exchange_token',
					'client_id'         => flxsm_app( 'meta', 'app_id' ),
					'client_secret'     => flxsm_secret( 'meta_secret' ),
					'fb_exchange_token' => $short,
				),
			)
		);
		$user_token = $r['ok'] ? (string) $r['json']['access_token'] : $short;
		$out        = array();
		$url        = $base . '/me/accounts';
		$q          = array(
			'fields'       => 'id,name,username,picture{url},access_token,tasks,instagram_business_account{id,username,name,profile_picture_url}',
			'limit'        => '100',
			'access_token' => $user_token,
		);
		for ( $i = 0; $i < 5 && $url; $i++ ) {
			$r = flxsm_http( 'GET', $url, $q ? array( 'query' => $q ) : array() );
			if ( ! $r['ok'] ) {
				return $this->err( $r );
			}
			foreach ( (array) ( $r['json']['data'] ?? array() ) as $p ) {
				$out[] = array(
					'provider' => 'facebook',
					'via'      => 'meta',
					'ext_id'   => (string) $p['id'],
					'name'     => (string) $p['name'],
					'username' => (string) ( $p['username'] ?? '' ),
					'avatar'   => (string) ( $p['picture']['data']['url'] ?? '' ),
					'token'    => array( 'access_token' => (string) $p['access_token'] ),
					'expires'  => 0,
					'meta'     => array( 'kind' => 'page' ),
				);
				if ( ! empty( $p['instagram_business_account']['id'] ) ) {
					$ig    = $p['instagram_business_account'];
					$out[] = array(
						'provider' => 'instagram',
						'via'      => 'meta',
						'ext_id'   => (string) $ig['id'],
						'name'     => (string) ( $ig['name'] ?? $ig['username'] ?? '' ),
						'username' => (string) ( $ig['username'] ?? '' ),
						'avatar'   => (string) ( $ig['profile_picture_url'] ?? '' ),
						'token'    => array( 'access_token' => (string) $p['access_token'] ),
						'expires'  => 0,
						'meta'     => array(
							'kind'    => 'business',
							'page_id' => (string) $p['id'],
						),
					);
				}
			}
			$url = (string) ( $r['json']['paging']['next'] ?? '' );
			$q   = array();
		}
		if ( ! $out ) {
			return new WP_Error( 'flxsm_none', __( 'No Facebook page you manage was found. Make sure you selected the pages during login.', 'flx-social' ) );
		}
		return $out;
	}
}

class FLXSM_Facebook extends FLXSM_Meta_Base {

	public function id() {
		return 'facebook';
	}

	public function label() {
		return 'Facebook';
	}

	public function color() {
		return '#1877f2';
	}

	public function caps( $acc = null ) {
		return array_merge(
			parent::caps( $acc ),
			array(
				'max_text'   => 63206,
				'max_images' => 10,
				'gif'        => true,
				'edit'       => true,
				'delete'     => true,
				'comment'    => true,
				'metrics'    => true,
				'options'    => array( 'link', 'first_comment' ),
			)
		);
	}

	public function publish_step( array $acc, array $pl, array $state ) {
		$page = $acc['ext_id'];
		$tok  = $this->tok( $acc );
		$text = (string) $pl['text'];
		$vid  = $this->first_video( $pl );
		if ( $vid ) {
			$r = flxsm_http(
				'POST',
				'https://graph-video.facebook.com/' . flxsm_meta_version() . "/{$page}/videos",
				array(
					'body'    => array(
						'file_url'     => $vid['url'],
						'description'  => $text,
						'title'        => (string) ( $pl['options']['title'] ?? '' ),
						'access_token' => $tok,
					),
					'timeout' => 45,
				)
			);
			if ( ! $r['ok'] ) {
				return $this->err( $r );
			}
			$id = (string) $r['json']['id'];
			return array(
				'done' => true,
				'id'   => $id,
				'url'  => "https://www.facebook.com/{$page}/videos/{$id}",
			);
		}
		$photos = isset( $state['photos'] ) ? (array) $state['photos'] : array();
		$imgs   = $this->images( $pl );
		$start  = microtime( true );
		foreach ( $imgs as $i => $m ) {
			if ( isset( $photos[ $i ] ) ) {
				continue;
			}
			if ( microtime( true ) - $start > 25 ) {
				return array(
					'wait'  => 1,
					'state' => array( 'photos' => $photos ),
				);
			}
			$r = flxsm_http(
				'POST',
				$this->g() . "/{$page}/photos",
				array(
					'body'    => array(
						'url'          => $m['url'],
						'published'    => 'false',
						'access_token' => $tok,
					),
					'timeout' => 40,
				)
			);
			if ( ! $r['ok'] ) {
				return $this->err( $r );
			}
			$photos[ $i ] = (string) $r['json']['id'];
		}
		$body = array(
			'message'      => $text,
			'access_token' => $tok,
		);
		if ( ! empty( $pl['options']['link'] ) && ! $photos ) {
			$body['link'] = $pl['options']['link'];
		}
		foreach ( array_values( $photos ) as $i => $pid ) {
			$body[ "attached_media[{$i}]" ] = wp_json_encode( array( 'media_fbid' => $pid ) );
		}
		$r = flxsm_http( 'POST', $this->g() . "/{$page}/feed", array( 'body' => $body ) );
		if ( ! $r['ok'] ) {
			return $this->err( $r );
		}
		$id = (string) $r['json']['id'];
		return array(
			'done' => true,
			'id'   => $id,
			'url'  => 'https://www.facebook.com/' . $id,
		);
	}

	public function edit( array $acc, $remote_id, array $pl ) {
		$r = flxsm_http(
			'POST',
			$this->g() . '/' . rawurlencode( $remote_id ),
			array(
				'body' => array(
					'message'      => (string) $pl['text'],
					'access_token' => $this->tok( $acc ),
				),
			)
		);
		return $r['ok'] ? true : $this->err( $r );
	}

	public function delete( array $acc, $remote_id ) {
		$r = flxsm_http( 'DELETE', $this->g() . '/' . rawurlencode( $remote_id ), array( 'query' => array( 'access_token' => $this->tok( $acc ) ) ) );
		return $r['ok'] || 404 === $r['code'] ? true : $this->err( $r );
	}

	public function comment( array $acc, $remote_id, $text ) {
		$r = flxsm_http(
			'POST',
			$this->g() . '/' . rawurlencode( $remote_id ) . '/comments',
			array(
				'body' => array(
					'message'      => $text,
					'access_token' => $this->tok( $acc ),
				),
			)
		);
		return $r['ok'] ? (string) $r['json']['id'] : $this->err( $r );
	}

	public function account_metrics( array $acc ) {
		$r = flxsm_http(
			'GET',
			$this->g() . '/' . $acc['ext_id'],
			array(
				'query' => array(
					'fields'       => 'followers_count,fan_count,name,picture{url}',
					'access_token' => $this->tok( $acc ),
				),
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r );
		}
		return array(
			'followers' => (int) ( $r['json']['followers_count'] ?? $r['json']['fan_count'] ?? 0 ),
			'fans'      => (int) ( $r['json']['fan_count'] ?? 0 ),
			'avatar'    => (string) ( $r['json']['picture']['data']['url'] ?? '' ),
		);
	}

	public function post_metrics( array $acc, $remote_id, array $target ) {
		$r = flxsm_http(
			'GET',
			$this->g() . '/' . rawurlencode( $remote_id ),
			array(
				'query' => array(
					'fields'       => 'shares,reactions.summary(total_count).limit(0),comments.summary(total_count).limit(0)',
					'access_token' => $this->tok( $acc ),
				),
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r );
		}
		$out = array(
			'likes'    => (int) ( $r['json']['reactions']['summary']['total_count'] ?? 0 ),
			'comments' => (int) ( $r['json']['comments']['summary']['total_count'] ?? 0 ),
			'shares'   => (int) ( $r['json']['shares']['count'] ?? 0 ),
		);
		$i = flxsm_http(
			'GET',
			$this->g() . '/' . rawurlencode( $remote_id ) . '/insights',
			array(
				'query' => array(
					'metric'       => 'post_impressions_unique,post_clicks',
					'access_token' => $this->tok( $acc ),
				),
			)
		);
		if ( $i['ok'] ) {
			foreach ( (array) ( $i['json']['data'] ?? array() ) as $row ) {
				$v = (int) ( $row['values'][0]['value'] ?? 0 );
				if ( 'post_impressions_unique' === $row['name'] ) {
					$out['reach'] = $v;
				} elseif ( 'post_clicks' === $row['name'] ) {
					$out['clicks'] = $v;
				}
			}
		}
		return $out;
	}
}

class FLXSM_Instagram extends FLXSM_Meta_Base {

	public function id() {
		return 'instagram';
	}

	public function label() {
		return 'Instagram';
	}

	public function color() {
		return '#e1306c';
	}

	/** Facebook girişi meta uygulamasını, Instagram girişi instagram uygulamasını kullanır. */
	public function app() {
		return 'meta';
	}

	public function caps( $acc = null ) {
		return array_merge(
			parent::caps( $acc ),
			array(
				'max_text'    => 2200,
				'max_images'  => 10,
				'max_videos'  => 10,
				'mix'         => true,
				'needs_media' => true,
				'comment'     => true,
				'metrics'     => true,
				'options'     => array( 'ig_type', 'share_to_feed', 'first_comment', 'thumb_url' ),
			)
		);
	}

	public function validate( array $pl, $acc ) {
		$errs = parent::validate( $pl, $acc );
		$type = (string) ( $pl['options']['ig_type'] ?? 'feed' );
		if ( 'story' === $type && count( $pl['media'] ) > 1 ) {
			$errs[] = __( 'A story has a single image or video.', 'flx-social' );
		}
		if ( preg_match_all( '/#[\p{L}\p{N}_]+/u', (string) $pl['text'] ) > 30 ) {
			$errs[] = __( 'Instagram accepts at most 30 hashtags.', 'flx-social' );
		}
		foreach ( $pl['media'] as $m ) {
			if ( 'image' === $m['kind'] && $m['width'] && $m['height'] && 'story' !== $type ) {
				$r = $m['width'] / $m['height'];
				if ( $r < 0.79 || $r > 1.92 ) {
					$errs[] = __( 'Instagram feed image aspect ratio must be between 4:5 and 1.91:1 (except stories).', 'flx-social' );
					break;
				}
			}
		}
		return $errs;
	}

	/** Instagram girişi (sayfasız). */
	public function auth_url( array $ctx ) {
		if ( 'instagram' !== ( $ctx['app'] ?? '' ) ) {
			return parent::auth_url( $ctx );
		}
		if ( '' === flxsm_app( 'instagram', 'app_id' ) || ! flxsm_has_secret( 'instagram_secret' ) ) {
			return new WP_Error( 'flxsm_app', __( 'First enter the keys under Settings › Apps › Instagram login.', 'flx-social' ), array( 'status' => 400 ) );
		}
		return 'https://www.instagram.com/oauth/authorize?' . http_build_query(
			array(
				'client_id'     => flxsm_app( 'instagram', 'app_id' ),
				'redirect_uri'  => flxsm_redirect_uri( 'instagram' ),
				'response_type' => 'code',
				'state'         => $ctx['state'],
				'scope'         => 'instagram_business_basic,instagram_business_content_publish,instagram_business_manage_comments,instagram_business_manage_insights',
			)
		);
	}

	public function exchange( array $params, array $ctx ) {
		if ( 'instagram' !== ( $ctx['app'] ?? '' ) ) {
			return parent::exchange( $params, $ctx );
		}
		$code = preg_replace( '/#_$/', '', (string) ( $params['code'] ?? '' ) );
		$r    = flxsm_http(
			'POST',
			'https://api.instagram.com/oauth/access_token',
			array(
				'body' => array(
					'client_id'     => flxsm_app( 'instagram', 'app_id' ),
					'client_secret' => flxsm_secret( 'instagram_secret' ),
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => flxsm_redirect_uri( 'instagram' ),
					'code'          => $code,
				),
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r );
		}
		$short = (string) ( $r['json']['access_token'] ?? ( $r['json']['data'][0]['access_token'] ?? '' ) );
		$r     = flxsm_http(
			'GET',
			'https://graph.instagram.com/access_token',
			array(
				'query' => array(
					'grant_type'    => 'ig_exchange_token',
					'client_secret' => flxsm_secret( 'instagram_secret' ),
					'access_token'  => $short,
				),
			)
		);
		$tok = $r['ok'] ? (string) $r['json']['access_token'] : $short;
		$exp = $r['ok'] ? time() + (int) ( $r['json']['expires_in'] ?? 5183944 ) : time() + 3600;
		$me  = flxsm_http(
			'GET',
			'https://graph.instagram.com/' . flxsm_meta_version() . '/me',
			array(
				'query' => array(
					'fields'       => 'user_id,username,name,profile_picture_url,account_type',
					'access_token' => $tok,
				),
			)
		);
		if ( ! $me['ok'] ) {
			return $this->err( $me );
		}
		$j = $me['json'];
		return array(
			array(
				'provider' => 'instagram',
				'via'      => 'instagram',
				'ext_id'   => (string) ( $j['user_id'] ?? $j['id'] ),
				'name'     => (string) ( $j['name'] ?? $j['username'] ?? '' ),
				'username' => (string) ( $j['username'] ?? '' ),
				'avatar'   => (string) ( $j['profile_picture_url'] ?? '' ),
				'token'    => array( 'access_token' => $tok ),
				'expires'  => $exp,
				'meta'     => array( 'kind' => strtolower( (string) ( $j['account_type'] ?? 'business' ) ) ),
			),
		);
	}

	/** Instagram girişi belirteci 60 gün; süresi bitmeden yenilenir. */
	public function ensure( array $acc ) {
		if ( 'instagram' === $acc['via'] && $acc['expires'] && $acc['expires'] < time() + 10 * DAY_IN_SECONDS ) {
			$r = $this->refresh( $acc );
			if ( ! is_wp_error( $r ) ) {
				flxsm_update_account(
					$acc['id'],
					array(
						'token'   => $r['token'],
						'expires' => $r['expires'],
					)
				);
				$acc['token']   = $r['token'];
				$acc['expires'] = $r['expires'];
			} elseif ( $acc['expires'] < time() ) {
				return $r;
			}
		}
		return $acc;
	}

	public function refresh( array $acc ) {
		if ( 'instagram' !== $acc['via'] ) {
			return parent::refresh( $acc );
		}
		$r = flxsm_http(
			'GET',
			'https://graph.instagram.com/refresh_access_token',
			array(
				'query' => array(
					'grant_type'   => 'ig_refresh_token',
					'access_token' => $this->tok( $acc ),
				),
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r );
		}
		return array(
			'token'   => array( 'access_token' => (string) $r['json']['access_token'] ),
			'expires' => time() + (int) ( $r['json']['expires_in'] ?? 5183944 ),
		);
	}

	private function container( array $acc, array $body ) {
		$body['access_token'] = $this->tok( $acc );
		$r                    = flxsm_http( 'POST', $this->g( $acc ) . '/' . $acc['ext_id'] . '/media', array( 'body' => $body, 'timeout' => 45 ) );
		return $r['ok'] ? (string) $r['json']['id'] : $this->err( $r );
	}

	private function container_status( array $acc, $id ) {
		$r = flxsm_http(
			'GET',
			$this->g( $acc ) . '/' . $id,
			array(
				'query' => array(
					'fields'       => 'status_code,status',
					'access_token' => $this->tok( $acc ),
				),
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r );
		}
		return (string) ( $r['json']['status_code'] ?? 'FINISHED' );
	}

	private function item_body( array $m, $type, $child = false ) {
		$b = array();
		if ( 'video' === $m['kind'] ) {
			$b['video_url']  = $m['url'];
			$b['media_type'] = 'story' === $type ? 'STORIES' : ( $child ? 'VIDEO' : 'REELS' );
		} else {
			$b['image_url'] = flxsm_jpeg_url( $m );
			if ( 'story' === $type ) {
				$b['media_type'] = 'STORIES';
			}
		}
		if ( $child ) {
			$b['is_carousel_item'] = 'true';
		}
		return $b;
	}

	public function publish_step( array $acc, array $pl, array $state ) {
		$type  = (string) ( $pl['options']['ig_type'] ?? 'feed' );
		$text  = (string) $pl['text'];
		$media = array_values( $pl['media'] );
		$phase = (string) ( $state['phase'] ?? 'create' );

		if ( 'create' === $phase ) {
			if ( count( $media ) > 1 && 'story' !== $type ) {
				$kids = (array) ( $state['kids'] ?? array() );
				foreach ( $media as $i => $m ) {
					if ( isset( $kids[ $i ] ) ) {
						continue;
					}
					$id = $this->container( $acc, $this->item_body( $m, $type, true ) );
					if ( is_wp_error( $id ) ) {
						return $id;
					}
					$kids[ $i ] = $id;
					if ( count( $kids ) < count( $media ) ) {
						return array(
							'wait'  => 1,
							'state' => array(
								'phase' => 'create',
								'kids'  => $kids,
							),
						);
					}
				}
				return array(
					'wait'  => 3,
					'state' => array(
						'phase' => 'kids',
						'kids'  => $kids,
					),
				);
			}
			$b = $this->item_body( $media[0], $type );
			if ( 'story' !== $type ) {
				$b['caption'] = $text;
			}
			if ( 'video' === $media[0]['kind'] && 'story' !== $type ) {
				$b['share_to_feed'] = isset( $pl['options']['share_to_feed'] ) && ! $pl['options']['share_to_feed'] ? 'false' : 'true';
				if ( ! empty( $pl['options']['thumb_url'] ) ) {
					$b['cover_url'] = $pl['options']['thumb_url'];
				}
			}
			$id = $this->container( $acc, $b );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			return array(
				'wait'  => 'video' === $media[0]['kind'] ? 10 : 2,
				'state' => array(
					'phase' => 'wait',
					'cid'   => $id,
					'n'     => 0,
				),
			);
		}

		if ( 'kids' === $phase ) {
			foreach ( (array) $state['kids'] as $kid ) {
				$st = $this->container_status( $acc, $kid );
				if ( is_wp_error( $st ) ) {
					return $st;
				}
				if ( in_array( $st, array( 'ERROR', 'EXPIRED' ), true ) ) {
					return new WP_Error( 'flxsm_api', __( 'Instagram could not process a carousel item (format/size not suitable).', 'flx-social' ), array( 'retry' => false ) );
				}
				if ( 'FINISHED' !== $st ) {
					return array(
						'wait'  => 10,
						'state' => $state,
					);
				}
			}
			$id = $this->container(
				$acc,
				array(
					'media_type' => 'CAROUSEL',
					'children'   => implode( ',', (array) $state['kids'] ),
					'caption'    => $text,
				)
			);
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			return array(
				'wait'  => 3,
				'state' => array(
					'phase' => 'wait',
					'cid'   => $id,
					'n'     => 0,
				),
			);
		}

		if ( 'wait' === $phase ) {
			$st = $this->container_status( $acc, $state['cid'] );
			if ( is_wp_error( $st ) ) {
				return $st;
			}
			if ( in_array( $st, array( 'ERROR', 'EXPIRED' ), true ) ) {
				return new WP_Error( 'flxsm_api', __( 'Instagram could not process the media. Video: MP4 (H.264/AAC), 3–90 s (Reels 15 min), up to 1 GB; image: JPEG, up to 8 MB.', 'flx-social' ), array( 'retry' => false ) );
			}
			if ( 'FINISHED' !== $st ) {
				$n = (int) $state['n'] + 1;
				if ( $n > 90 ) {
					return new WP_Error( 'flxsm_api', __( 'Instagram video processing did not finish in 30 minutes.', 'flx-social' ), array( 'retry' => false ) );
				}
				return array(
					'wait'  => 20,
					'state' => array_merge( $state, array( 'n' => $n ) ),
				);
			}
			$r = flxsm_http(
				'POST',
				$this->g( $acc ) . '/' . $acc['ext_id'] . '/media_publish',
				array(
					'body' => array(
						'creation_id'  => $state['cid'],
						'access_token' => $this->tok( $acc ),
					),
				)
			);
			if ( ! $r['ok'] ) {
				return $this->err( $r );
			}
			$mid = (string) $r['json']['id'];
			$p   = flxsm_http(
				'GET',
				$this->g( $acc ) . '/' . $mid,
				array(
					'query' => array(
						'fields'       => 'permalink',
						'access_token' => $this->tok( $acc ),
					),
				)
			);
			return array(
				'done' => true,
				'id'   => $mid,
				'url'  => $p['ok'] ? (string) ( $p['json']['permalink'] ?? '' ) : '',
			);
		}
		return new WP_Error( 'flxsm_state', 'Instagram: unknown state', array( 'retry' => false ) );
	}

	public function comment( array $acc, $remote_id, $text ) {
		$r = flxsm_http(
			'POST',
			$this->g( $acc ) . '/' . rawurlencode( $remote_id ) . '/comments',
			array(
				'body' => array(
					'message'      => $text,
					'access_token' => $this->tok( $acc ),
				),
			)
		);
		return $r['ok'] ? (string) $r['json']['id'] : $this->err( $r );
	}

	public function account_metrics( array $acc ) {
		$r = flxsm_http(
			'GET',
			$this->g( $acc ) . '/' . $acc['ext_id'],
			array(
				'query' => array(
					'fields'       => 'followers_count,media_count,profile_picture_url',
					'access_token' => $this->tok( $acc ),
				),
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r );
		}
		return array(
			'followers' => (int) ( $r['json']['followers_count'] ?? 0 ),
			'posts'     => (int) ( $r['json']['media_count'] ?? 0 ),
			'avatar'    => (string) ( $r['json']['profile_picture_url'] ?? '' ),
		);
	}

	public function post_metrics( array $acc, $remote_id, array $target ) {
		$r = flxsm_http(
			'GET',
			$this->g( $acc ) . '/' . rawurlencode( $remote_id ),
			array(
				'query' => array(
					'fields'       => 'like_count,comments_count',
					'access_token' => $this->tok( $acc ),
				),
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r );
		}
		$out = array(
			'likes'    => (int) ( $r['json']['like_count'] ?? 0 ),
			'comments' => (int) ( $r['json']['comments_count'] ?? 0 ),
		);
		$i   = flxsm_http(
			'GET',
			$this->g( $acc ) . '/' . rawurlencode( $remote_id ) . '/insights',
			array(
				'query' => array(
					'metric'       => 'reach,views,shares,saved',
					'access_token' => $this->tok( $acc ),
				),
			)
		);
		if ( $i['ok'] ) {
			foreach ( (array) ( $i['json']['data'] ?? array() ) as $row ) {
				$out[ $row['name'] ] = (int) ( $row['values'][0]['value'] ?? $row['total_value']['value'] ?? 0 );
			}
		}
		return $out;
	}
}
