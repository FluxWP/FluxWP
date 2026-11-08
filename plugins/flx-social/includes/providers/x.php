<?php
/**
 * X (Twitter): API v2, kullanıcı bağlamı OAuth 1.0a (belirteç süresiz; Mixpost ve Postiz de böyle).
 * Katman (Free / Basic / Pro / Pay-as-you-go) Ayarlar'dan seçilir: okuma metrikleri ücretsiz katmanda kapalı,
 * isteğe bağlı aylık gönderi sınırı uygulanır. Medya v2 parçalı yükleme (initialize/append/finalize/status).
 */

defined( 'ABSPATH' ) || exit;

class FLXSM_X extends FLXSM_Provider {

	const API   = 'https://api.x.com';
	const CHUNK = 4194304; // 4 MB (X ≤5 MB parça kabul eder)

	public function id() {
		return 'x';
	}

	public function label() {
		return 'X';
	}

	public function color() {
		return '#111111';
	}

	public static function tiers() {
		return array(
			'free'          => __( 'Free (no cost, low write limit, no metrics)', 'flx-social' ),
			'basic'         => __( 'Basic (paid monthly)', 'flx-social' ),
			'pro'           => __( 'Pro (paid monthly)', 'flx-social' ),
			'pay_as_you_go' => __( 'Pay-as-you-go', 'flx-social' ),
		);
	}

	public function caps( $acc = null ) {
		$premium = is_array( $acc ) && ! empty( ( is_array( $acc['meta'] ) ? $acc['meta'] : flxsm_dec( $acc['meta'] ) )['premium'] );
		return array_merge(
			parent::caps( $acc ),
			array(
				'max_text'   => $premium || flxsm_setting( 'x_premium', 0 ) ? 25000 : 280,
				'max_images' => 4,
				'gif'        => true,
				'delete'     => true,
				'comment'    => true,
				'metrics'    => 'free' !== flxsm_setting( 'x_tier', 'free' ),
				'options'    => array( 'reply_settings', 'first_comment', 'alt' ),
			)
		);
	}

	public function validate( array $pl, $acc ) {
		$errs = array();
		foreach ( parent::validate( $pl, $acc ) as $e ) {
			$errs[] = $e;
		}
		$n = count( $pl['media'] );
		$v = array_filter( $pl['media'], fn( $m ) => in_array( $m['kind'], array( 'video', 'gif' ), true ) );
		if ( $v && $n > 1 ) {
			$errs[] = __( 'X: a video or GIF is shared on its own.', 'flx-social' );
		}
		$lim = (int) flxsm_setting( 'x_month_limit', 0 );
		if ( $lim && flxsm_x_month_count() >= $lim ) {
			/* translators: %d: monthly limit */
			$errs[] = sprintf( __( 'The monthly limit of %d posts set for X has been reached.', 'flx-social' ), $lim );
		}
		return $errs;
	}

	/* ---------------- OAuth 1.0a ---------------- */

	private function enc( $s ) {
		return str_replace( '%7E', '~', rawurlencode( (string) $s ) );
	}

	/** Authorization başlığı. $params: imzaya girecek sorgu/form alanları. */
	private function oauth_header( $method, $url, array $params, $token = '', $token_secret = '', array $extra = array() ) {
		$oauth = array_merge(
			array(
				'oauth_consumer_key'     => flxsm_app( 'x', 'api_key' ),
				'oauth_nonce'            => wp_generate_password( 32, false ),
				'oauth_signature_method' => 'HMAC-SHA1',
				'oauth_timestamp'        => (string) time(),
				'oauth_version'          => '1.0',
			),
			$extra
		);
		if ( '' !== $token ) {
			$oauth['oauth_token'] = $token;
		}
		$all = array_merge( $params, $oauth );
		uksort( $all, 'strcmp' );
		$pairs = array();
		foreach ( $all as $k => $v ) {
			$pairs[] = $this->enc( $k ) . '=' . $this->enc( $v );
		}
		$base                     = strtoupper( $method ) . '&' . $this->enc( $url ) . '&' . $this->enc( implode( '&', $pairs ) );
		$key                      = $this->enc( flxsm_secret( 'x_secret' ) ) . '&' . $this->enc( $token_secret );
		$oauth['oauth_signature'] = base64_encode( hash_hmac( 'sha1', $base, $key, true ) );
		ksort( $oauth );
		$h = array();
		foreach ( $oauth as $k => $v ) {
			$h[] = $this->enc( $k ) . '="' . $this->enc( $v ) . '"';
		}
		return 'OAuth ' . implode( ', ', $h );
	}

	/** İmzalı istek. $opt: query, form (imzaya girer), json, multipart (array body,type). */
	private function req( array $acc, $method, $path, array $opt = array() ) {
		$url   = 0 === strpos( $path, 'http' ) ? $path : self::API . $path;
		$query = (array) ( $opt['query'] ?? array() );
		$sig   = $query;
		$args  = array(
			'headers' => array(),
			'timeout' => (int) ( $opt['timeout'] ?? 30 ),
		);
		if ( isset( $opt['form'] ) ) {
			$sig          = array_merge( $sig, $opt['form'] );
			$args['body'] = http_build_query( $opt['form'], '', '&', PHP_QUERY_RFC3986 );
			$args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
		} elseif ( isset( $opt['json'] ) ) {
			$args['json'] = $opt['json'];
		} elseif ( isset( $opt['multipart'] ) ) {
			$args['body']                    = $opt['multipart']['body'];
			$args['headers']['Content-Type'] = $opt['multipart']['type'];
		}
		$args['headers']['Authorization'] = $this->oauth_header( $method, $url, $sig, (string) ( $acc['token']['oauth_token'] ?? '' ), (string) ( $acc['token']['oauth_token_secret'] ?? '' ) );
		if ( $query ) {
			$args['query'] = $query;
		}
		return flxsm_http( $method, $url, $args );
	}

	public function auth_url( array $ctx ) {
		if ( '' === flxsm_app( 'x', 'api_key' ) || ! flxsm_has_secret( 'x_secret' ) ) {
			return new WP_Error( 'flxsm_app', __( 'First enter the API Key and API Key Secret under Settings › Apps › X.', 'flx-social' ), array( 'status' => 400 ) );
		}
		$url = self::API . '/oauth/request_token';
		$r   = flxsm_http(
			'POST',
			$url,
			array(
				'headers' => array(
					'Authorization' => $this->oauth_header(
						'POST',
						$url,
						array(),
						'',
						'',
						array(
							'oauth_callback' => flxsm_redirect_uri( 'x' ),
						)
					),
				),
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r, 'X' );
		}
		parse_str( $r['body'], $t );
		if ( empty( $t['oauth_token'] ) ) {
			return new WP_Error( 'flxsm_api', 'X: request_token' );
		}
		// X state döndürmez: oturumu istek belirteciyle eşleriz.
		set_transient(
			'flxsm_xrt_' . md5( $t['oauth_token'] ),
			array(
				'state'  => $ctx['state'],
				'secret' => (string) $t['oauth_token_secret'],
			),
			15 * MINUTE_IN_SECONDS
		);
		return self::API . '/oauth/authorize?oauth_token=' . rawurlencode( $t['oauth_token'] );
	}

	public function state_from_callback( array $params ) {
		$t = get_transient( 'flxsm_xrt_' . md5( (string) ( $params['oauth_token'] ?? '' ) ) );
		return is_array( $t ) ? (string) $t['state'] : '';
	}

	public function exchange( array $params, array $ctx ) {
		if ( ! empty( $params['denied'] ) ) {
			return new WP_Error( 'flxsm_denied', __( 'Connection refused.', 'flx-social' ) );
		}
		$rt   = get_transient( 'flxsm_xrt_' . md5( (string) ( $params['oauth_token'] ?? '' ) ) );
		$url  = self::API . '/oauth/access_token';
		$form = array( 'oauth_verifier' => (string) ( $params['oauth_verifier'] ?? '' ) );
		$r    = flxsm_http(
			'POST',
			$url,
			array(
				'headers' => array(
					'Authorization' => $this->oauth_header( 'POST', $url, $form, (string) $params['oauth_token'], is_array( $rt ) ? $rt['secret'] : '' ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => http_build_query( $form, '', '&', PHP_QUERY_RFC3986 ),
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r, 'X' );
		}
		parse_str( $r['body'], $t );
		$acc = array(
			'token' => array(
				'oauth_token'        => (string) $t['oauth_token'],
				'oauth_token_secret' => (string) $t['oauth_token_secret'],
			),
		);
		$me  = $this->req(
			$acc,
			'GET',
			'/2/users/me',
			array( 'query' => array( 'user.fields' => 'profile_image_url,verified,verified_type,public_metrics' ) )
		);
		$d   = $me['ok'] ? (array) $me['json']['data'] : array();
		return array(
			array(
				'provider' => 'x',
				'via'      => 'x',
				'ext_id'   => (string) ( $t['user_id'] ?? $d['id'] ?? '' ),
				'name'     => (string) ( $d['name'] ?? $t['screen_name'] ?? '' ),
				'username' => (string) ( $t['screen_name'] ?? $d['username'] ?? '' ),
				'avatar'   => str_replace( '_normal', '_400x400', (string) ( $d['profile_image_url'] ?? '' ) ),
				'token'    => $acc['token'],
				'expires'  => 0,
				'meta'     => array(
					'kind'    => 'user',
					'premium' => ! empty( $d['verified_type'] ) && 'none' !== $d['verified_type'],
				),
			),
		);
	}

	/* ---------------- Yayın ---------------- */

	public function publish_step( array $acc, array $pl, array $state ) {
		$media = array_values( $pl['media'] );
		$ids   = (array) ( $state['ids'] ?? array() );
		$start = microtime( true );
		foreach ( $media as $i => $m ) {
			if ( isset( $ids[ $i ] ) ) {
				continue;
			}
			$up = (array) ( $state['up'] ?? array() );
			if ( empty( $up ) || (int) $up['i'] !== $i ) {
				$cat = 'video' === $m['kind'] ? 'tweet_video' : ( 'gif' === $m['kind'] ? 'tweet_gif' : 'tweet_image' );
				$r   = $this->req(
					$acc,
					'POST',
					'/2/media/upload/initialize',
					array(
						'json' => array(
							'media_type'     => $m['mime'],
							'total_bytes'    => (int) $m['bytes'],
							'media_category' => $cat,
						),
					)
				);
				if ( ! $r['ok'] ) {
					return $this->err( $r, 'X' );
				}
				$up = array(
					'i'   => $i,
					'id'  => (string) $r['json']['data']['id'],
					'seg' => 0,
					'off' => 0,
				);
			}
			if ( 'done' !== ( $up['phase'] ?? '' ) && 'wait' !== ( $up['phase'] ?? '' ) ) {
				while ( $up['off'] < $m['bytes'] ) {
					if ( microtime( true ) - $start > 30 ) {
						return array(
							'wait'  => 1,
							'state' => array(
								'ids' => $ids,
								'up'  => $up,
							),
						);
					}
					$len  = (int) min( self::CHUNK, $m['bytes'] - $up['off'] );
					$data = flxsm_read_chunk( $m['path'], $up['off'], $len );
					if ( false === $data ) {
						return new WP_Error( 'flxsm_media', __( 'Could not read the media file.', 'flx-social' ), array( 'retry' => false ) );
					}
					$mp = flxsm_multipart(
						array( 'segment_index' => (string) $up['seg'] ),
						array(
							'media' => array(
								'data'     => $data,
								'mime'     => 'application/octet-stream',
								'filename' => 'blob',
							),
						)
					);
					$r  = $this->req(
						$acc,
						'POST',
						'/2/media/upload/' . $up['id'] . '/append',
						array(
							'multipart' => $mp,
							'timeout'   => 50,
						)
					);
					if ( ! $r['ok'] ) {
						return $this->err( $r, 'X' );
					}
					$up['off'] += $len;
					++$up['seg'];
				}
				$r = $this->req( $acc, 'POST', '/2/media/upload/' . $up['id'] . '/finalize' );
				if ( ! $r['ok'] ) {
					return $this->err( $r, 'X' );
				}
				$pi          = $r['json']['data']['processing_info'] ?? null;
				$up['phase'] = $pi && 'succeeded' !== ( $pi['state'] ?? '' ) ? 'wait' : 'done';
				if ( 'wait' === $up['phase'] ) {
					return array(
						'wait'  => max( 2, (int) ( $pi['check_after_secs'] ?? 5 ) ),
						'state' => array(
							'ids' => $ids,
							'up'  => $up,
						),
					);
				}
			}
			if ( 'wait' === $up['phase'] ) {
				$r = $this->req(
					$acc,
					'GET',
					'/2/media/upload',
					array(
						'query' => array(
							'command'  => 'STATUS',
							'media_id' => $up['id'],
						),
					)
				);
				if ( ! $r['ok'] ) {
					return $this->err( $r, 'X' );
				}
				$pi = $r['json']['data']['processing_info'] ?? null;
				if ( $pi && 'failed' === $pi['state'] ) {
					return new WP_Error( 'flxsm_api', 'X: ' . ( $pi['error']['message'] ?? __( 'video could not be processed', 'flx-social' ) ), array( 'retry' => false ) );
				}
				if ( $pi && 'succeeded' !== $pi['state'] ) {
					return array(
						'wait'  => max( 2, (int) ( $pi['check_after_secs'] ?? 5 ) ),
						'state' => array(
							'ids' => $ids,
							'up'  => $up,
						),
					);
				}
			}
			$ids[ $i ] = $up['id'];
			if ( ! empty( $pl['options']['alt'] ) && 'image' === $m['kind'] ) {
				$this->req(
					$acc,
					'POST',
					'/2/media/metadata',
					array(
						'json' => array(
							'id'       => $up['id'],
							'metadata' => array( 'alt_text' => array( 'text' => mb_substr( $pl['options']['alt'], 0, 1000 ) ) ),
						),
					)
				);
			}
			$state['up'] = array();
		}
		$body = array( 'text' => (string) $pl['text'] );
		if ( $ids ) {
			$body['media'] = array( 'media_ids' => array_values( $ids ) );
		}
		if ( in_array( $pl['options']['reply_settings'] ?? '', array( 'following', 'mentionedUsers', 'subscribers', 'verified' ), true ) ) {
			$body['reply_settings'] = $pl['options']['reply_settings'];
		}
		$r = $this->req( $acc, 'POST', '/2/tweets', array( 'json' => $body ) );
		if ( ! $r['ok'] ) {
			return $this->err( $r, 'X' );
		}
		$id = (string) $r['json']['data']['id'];
		return array(
			'done' => true,
			'id'   => $id,
			'url'  => 'https://x.com/' . ( $acc['username'] ? $acc['username'] : 'i' ) . '/status/' . $id,
		);
	}

	public function delete( array $acc, $remote_id ) {
		$r = $this->req( $acc, 'DELETE', '/2/tweets/' . rawurlencode( $remote_id ) );
		return $r['ok'] || 404 === $r['code'] ? true : $this->err( $r, 'X' );
	}

	public function comment( array $acc, $remote_id, $text ) {
		$r = $this->req(
			$acc,
			'POST',
			'/2/tweets',
			array(
				'json' => array(
					'text'  => $text,
					'reply' => array( 'in_reply_to_tweet_id' => (string) $remote_id ),
				),
			)
		);
		return $r['ok'] ? (string) $r['json']['data']['id'] : $this->err( $r, 'X' );
	}

	public function account_metrics( array $acc ) {
		$r = $this->req( $acc, 'GET', '/2/users/me', array( 'query' => array( 'user.fields' => 'public_metrics,profile_image_url' ) ) );
		if ( ! $r['ok'] ) {
			return $this->err( $r, 'X' );
		}
		$m = $r['json']['data']['public_metrics'] ?? array();
		return array(
			'followers' => (int) ( $m['followers_count'] ?? 0 ),
			'posts'     => (int) ( $m['tweet_count'] ?? 0 ),
		);
	}

	public function post_metrics( array $acc, $remote_id, array $target ) {
		if ( 'free' === flxsm_setting( 'x_tier', 'free' ) ) {
			return new WP_Error( 'flxsm_unsupported', '' );
		}
		$r = $this->req( $acc, 'GET', '/2/tweets/' . rawurlencode( $remote_id ), array( 'query' => array( 'tweet.fields' => 'public_metrics' ) ) );
		if ( ! $r['ok'] ) {
			return $this->err( $r, 'X' );
		}
		$m = $r['json']['data']['public_metrics'] ?? array();
		return array(
			'likes'       => (int) ( $m['like_count'] ?? 0 ),
			'comments'    => (int) ( $m['reply_count'] ?? 0 ),
			'shares'      => (int) ( $m['retweet_count'] ?? 0 ) + (int) ( $m['quote_count'] ?? 0 ),
			'impressions' => (int) ( $m['impression_count'] ?? 0 ),
		);
	}
}

/** Bu ay X'e yapılan başarılı gönderi sayısı (aylık sınır için). */
function flxsm_x_month_count() {
	global $wpdb;
	$from = strtotime( gmdate( 'Y-m-01 00:00:00' ) );
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxsm_table( 'targets' ) . ' t JOIN ' . flxsm_table( 'accounts' ) . " a ON a.id = t.account_id WHERE a.provider = 'x' AND t.published_at >= %d", $from ) ); // phpcs:ignore WordPress.DB
}
