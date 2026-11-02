<?php
/**
 * LinkedIn: kişisel profil (Share on LinkedIn, self-servis) ve şirket/dernek sayfası
 * (Community Management API onayı gerekir). REST Posts API, versiyon başlığı Ayarlar'dan.
 */

defined( 'ABSPATH' ) || exit;

class FLXSM_LinkedIn extends FLXSM_Provider {

	const API = 'https://api.linkedin.com';

	public function id() {
		return 'linkedin';
	}

	public function label() {
		return 'LinkedIn';
	}

	public function color() {
		return '#0a66c2';
	}

	public function caps( $acc = null ) {
		$org = is_array( $acc ) && 'org' === ( ( is_array( $acc['meta'] ) ? $acc['meta'] : flxsm_dec( $acc['meta'] ) )['kind'] ?? '' );
		return array_merge(
			parent::caps( $acc ),
			array(
				'max_text'   => 3000,
				'max_images' => 20,
				'gif'        => true,
				'edit'       => true,
				'delete'     => true,
				'comment'    => true,
				'metrics'    => $org,
				'options'    => array( 'link', 'title', 'li_visibility', 'first_comment', 'alt' ),
			)
		);
	}

	private function ver() {
		$v = (string) flxsm_app( 'linkedin', 'version', '202607' );
		return preg_match( '/^\d{6}$/', $v ) ? $v : '202607';
	}

	private function h( array $acc, array $more = array() ) {
		return array_merge(
			array(
				'Authorization'             => 'Bearer ' . (string) ( $acc['token']['access_token'] ?? '' ),
				'LinkedIn-Version'          => $this->ver(),
				'X-Restli-Protocol-Version' => '2.0.0',
			),
			$more
		);
	}

	private function author( array $acc ) {
		return 'org' === ( $acc['meta']['kind'] ?? '' ) ? 'urn:li:organization:' . $acc['ext_id'] : 'urn:li:person:' . $acc['ext_id'];
	}

	/** LinkedIn "little text": özel karakterler kaçırılmazsa metin kesilir. */
	private function little( $s ) {
		return preg_replace( '/([\\\\(){}\[\]<>@|~_*#])/u', '\\\\$1', (string) $s );
	}

	public function auth_url( array $ctx ) {
		$ok = $this->need_app( 'client_id', 'linkedin_secret' );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$scopes = array( 'openid', 'profile', 'w_member_social' );
		if ( flxsm_setting( 'li_org', 1 ) ) {
			$scopes = array_merge( $scopes, array( 'w_organization_social', 'r_organization_admin', 'r_organization_social' ) );
		}
		return 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query(
			array(
				'response_type' => 'code',
				'client_id'     => flxsm_app( 'linkedin', 'client_id' ),
				'redirect_uri'  => flxsm_redirect_uri( 'linkedin' ),
				'state'         => $ctx['state'],
				'scope'         => implode( ' ', $scopes ),
			)
		);
	}

	private function token_req( array $body ) {
		$body['client_id']     = flxsm_app( 'linkedin', 'client_id' );
		$body['client_secret'] = flxsm_secret( 'linkedin_secret' );
		$r                     = flxsm_http( 'POST', 'https://www.linkedin.com/oauth/v2/accessToken', array( 'body' => $body ) );
		return $r['ok'] ? $r['json'] : $this->err( $r, 'LinkedIn' );
	}

	public function exchange( array $params, array $ctx ) {
		if ( ! empty( $params['error'] ) ) {
			return new WP_Error( 'flxsm_denied', (string) ( $params['error_description'] ?? $params['error'] ) );
		}
		$j = $this->token_req(
			array(
				'grant_type'   => 'authorization_code',
				'code'         => (string) ( $params['code'] ?? '' ),
				'redirect_uri' => flxsm_redirect_uri( 'linkedin' ),
			)
		);
		if ( is_wp_error( $j ) ) {
			return $j;
		}
		$token = array(
			'access_token'  => (string) $j['access_token'],
			'refresh_token' => (string) ( $j['refresh_token'] ?? '' ),
		);
		$exp   = time() + (int) ( $j['expires_in'] ?? 5184000 );
		$tmp   = array( 'token' => $token );
		$me    = flxsm_http( 'GET', self::API . '/v2/userinfo', array( 'bearer' => $token['access_token'] ) );
		if ( ! $me['ok'] ) {
			return $this->err( $me, 'LinkedIn' );
		}
		$out   = array(
			array(
				'provider' => 'linkedin',
				'via'      => 'linkedin',
				'ext_id'   => (string) $me['json']['sub'],
				'name'     => (string) ( $me['json']['name'] ?? '' ),
				'username' => '',
				'avatar'   => (string) ( $me['json']['picture'] ?? '' ),
				'token'    => $token,
				'expires'  => $exp,
				'meta'     => array( 'kind' => 'person' ),
			),
		);
		if ( flxsm_setting( 'li_org', 1 ) ) {
			$acl = flxsm_http(
				'GET',
				self::API . '/rest/organizationAcls',
				array(
					'query'   => array(
						'q'     => 'roleAssignee',
						'role'  => 'ADMINISTRATOR',
						'state' => 'APPROVED',
					),
					'headers' => $this->h( $tmp ),
				)
			);
			foreach ( (array) ( $acl['json']['elements'] ?? array() ) as $el ) {
				$oid = preg_replace( '/^urn:li:organization:/', '', (string) ( $el['organization'] ?? '' ) );
				if ( '' === $oid ) {
					continue;
				}
				$o     = flxsm_http( 'GET', self::API . '/rest/organizations/' . rawurlencode( $oid ), array( 'headers' => $this->h( $tmp ) ) );
				$out[] = array(
					'provider' => 'linkedin',
					'via'      => 'linkedin',
					'ext_id'   => $oid,
					'name'     => (string) ( $o['json']['localizedName'] ?? ( 'Organization ' . $oid ) ),
					'username' => (string) ( $o['json']['vanityName'] ?? '' ),
					'avatar'   => '',
					'token'    => $token,
					'expires'  => $exp,
					'meta'     => array( 'kind' => 'org' ),
				);
			}
		}
		return $out;
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
			'expires' => time() + (int) ( $j['expires_in'] ?? 5184000 ),
		);
	}

	/** Görsel yükle: urn döner. */
	private function upload_image( array $acc, array $m ) {
		$r = flxsm_http(
			'POST',
			self::API . '/rest/images?action=initializeUpload',
			array(
				'json'    => array( 'initializeUploadRequest' => array( 'owner' => $this->author( $acc ) ) ),
				'headers' => $this->h( $acc ),
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r, 'LinkedIn' );
		}
		$v    = $r['json']['value'];
		$data = file_get_contents( $m['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$u    = flxsm_http(
			'PUT',
			$v['uploadUrl'],
			array(
				'body'    => $data,
				'headers' => array(
					'Authorization' => 'Bearer ' . $acc['token']['access_token'],
					'Content-Type'  => $m['mime'],
				),
				'timeout' => 50,
			)
		);
		return $u['ok'] ? (string) $v['image'] : $this->err( $u, 'LinkedIn' );
	}

	public function publish_step( array $acc, array $pl, array $state ) {
		$vid   = $this->first_video( $pl );
		$phase = (string) ( $state['phase'] ?? 'media' );
		$start = microtime( true );

		if ( 'media' === $phase ) {
			if ( $vid ) {
				if ( empty( $state['video'] ) ) {
					$r = flxsm_http(
						'POST',
						self::API . '/rest/videos?action=initializeUpload',
						array(
							'json'    => array(
								'initializeUploadRequest' => array(
									'owner'           => $this->author( $acc ),
									'fileSizeBytes'   => (int) $vid['bytes'],
									'uploadCaptions'  => false,
									'uploadThumbnail' => false,
								),
							),
							'headers' => $this->h( $acc ),
						)
					);
					if ( ! $r['ok'] ) {
						return $this->err( $r, 'LinkedIn' );
					}
					$v     = $r['json']['value'];
					$state = array(
						'phase' => 'media',
						'video' => (string) $v['video'],
						'token' => (string) ( $v['uploadToken'] ?? '' ),
						'parts' => array_map(
							fn( $p ) => array( (string) $p['uploadUrl'], (int) $p['firstByte'], (int) $p['lastByte'] ),
							(array) $v['uploadInstructions']
						),
						'etags' => array(),
					);
				}
				$n = count( $state['etags'] );
				while ( $n < count( $state['parts'] ) ) {
					if ( microtime( true ) - $start > 30 ) {
						return array(
							'wait'  => 1,
							'state' => $state,
						);
					}
					list( $url, $from, $to ) = $state['parts'][ $n ];
					$data = flxsm_read_chunk( $vid['path'], $from, $to - $from + 1 );
					$u    = flxsm_http(
						'PUT',
						$url,
						array(
							'body'    => $data,
							'headers' => array( 'Content-Type' => 'application/octet-stream' ),
							'timeout' => 50,
						)
					);
					if ( ! $u['ok'] ) {
						return $this->err( $u, 'LinkedIn' );
					}
					$state['etags'][] = trim( (string) ( $u['headers']['etag'] ?? '' ), '"' );
					++$n;
				}
				$f = flxsm_http(
					'POST',
					self::API . '/rest/videos?action=finalizeUpload',
					array(
						'json'    => array(
							'finalizeUploadRequest' => array(
								'video'           => $state['video'],
								'uploadToken'     => $state['token'],
								'uploadedPartIds' => $state['etags'],
							),
						),
						'headers' => $this->h( $acc ),
					)
				);
				if ( ! $f['ok'] ) {
					return $this->err( $f, 'LinkedIn' );
				}
				return array(
					'wait'  => 10,
					'state' => array(
						'phase' => 'vwait',
						'video' => $state['video'],
						'n'     => 0,
					),
				);
			}
			$imgs = $this->images( $pl );
			$urns = (array) ( $state['urns'] ?? array() );
			foreach ( $imgs as $i => $m ) {
				if ( isset( $urns[ $i ] ) ) {
					continue;
				}
				if ( microtime( true ) - $start > 30 ) {
					return array(
						'wait'  => 1,
						'state' => array(
							'phase' => 'media',
							'urns'  => $urns,
						),
					);
				}
				$u = $this->upload_image( $acc, $m );
				if ( is_wp_error( $u ) ) {
					return $u;
				}
				$urns[ $i ] = $u;
			}
			$state = array(
				'phase' => 'post',
				'urns'  => $urns,
			);
		}

		if ( 'vwait' === $phase ) {
			$r = flxsm_http( 'GET', self::API . '/rest/videos/' . rawurlencode( $state['video'] ), array( 'headers' => $this->h( $acc ) ) );
			if ( ! $r['ok'] ) {
				return $this->err( $r, 'LinkedIn' );
			}
			$st = (string) ( $r['json']['status'] ?? '' );
			if ( 'PROCESSING_FAILED' === $st ) {
				return new WP_Error( 'flxsm_api', __( 'LinkedIn videoyu işleyemedi.', 'flx-social' ), array( 'retry' => false ) );
			}
			if ( 'AVAILABLE' !== $st ) {
				$state['n'] = (int) $state['n'] + 1;
				if ( $state['n'] > 90 ) {
					return new WP_Error( 'flxsm_api', __( 'LinkedIn video işlemesi uzun sürdü.', 'flx-social' ), array( 'retry' => false ) );
				}
				return array(
					'wait'  => 15,
					'state' => $state,
				);
			}
			$state = array(
				'phase' => 'post',
				'video' => $state['video'],
			);
		}

		$o    = $pl['options'];
		$body = array(
			'author'                    => $this->author( $acc ),
			'commentary'                => $this->little( $pl['text'] ),
			'visibility'                => in_array( $o['li_visibility'] ?? '', array( 'PUBLIC', 'CONNECTIONS' ), true ) ? $o['li_visibility'] : 'PUBLIC',
			'distribution'              => array(
				'feedDistribution'               => 'MAIN_FEED',
				'targetEntities'                 => array(),
				'thirdPartyDistributionChannels' => array(),
			),
			'lifecycleState'            => 'PUBLISHED',
			'isReshareDisabledByAuthor' => false,
		);
		if ( ! empty( $state['video'] ) ) {
			$body['content'] = array(
				'media' => array(
					'id'    => $state['video'],
					'title' => (string) ( $o['title'] ?? '' ),
				),
			);
		} elseif ( ! empty( $state['urns'] ) ) {
			$alt  = (string) ( $o['alt'] ?? '' );
			$urns = array_values( $state['urns'] );
			$body['content'] = 1 === count( $urns )
				? array(
					'media' => array(
						'id'      => $urns[0],
						'altText' => $alt,
					),
				)
				: array( 'multiImage' => array( 'images' => array_map( fn( $u ) => array( 'id' => $u, 'altText' => $alt ), $urns ) ) );
		} elseif ( ! empty( $o['link'] ) ) {
			$body['content'] = array(
				'article' => array(
					'source' => $o['link'],
					'title'  => (string) ( ! empty( $o['title'] ) ? $o['title'] : wp_parse_url( $o['link'], PHP_URL_HOST ) ),
				),
			);
		}
		$r = flxsm_http( 'POST', self::API . '/rest/posts', array( 'json' => $body, 'headers' => $this->h( $acc ) ) );
		if ( ! $r['ok'] ) {
			return $this->err( $r, 'LinkedIn' );
		}
		$urn = (string) ( $r['headers']['x-restli-id'] ?? $r['headers']['x-linkedin-id'] ?? '' );
		return array(
			'done' => true,
			'id'   => $urn,
			'url'  => $urn ? 'https://www.linkedin.com/feed/update/' . $urn . '/' : '',
		);
	}

	public function edit( array $acc, $remote_id, array $pl ) {
		$r = flxsm_http(
			'POST',
			self::API . '/rest/posts/' . rawurlencode( $remote_id ),
			array(
				'json'    => array( 'patch' => array( '$set' => array( 'commentary' => $this->little( $pl['text'] ) ) ) ),
				'headers' => $this->h( $acc, array( 'X-RestLi-Method' => 'PARTIAL_UPDATE' ) ),
			)
		);
		return $r['ok'] ? true : $this->err( $r, 'LinkedIn' );
	}

	public function delete( array $acc, $remote_id ) {
		$r = flxsm_http( 'DELETE', self::API . '/rest/posts/' . rawurlencode( $remote_id ), array( 'headers' => $this->h( $acc ) ) );
		return $r['ok'] || 404 === $r['code'] ? true : $this->err( $r, 'LinkedIn' );
	}

	public function comment( array $acc, $remote_id, $text ) {
		$r = flxsm_http(
			'POST',
			self::API . '/rest/socialActions/' . rawurlencode( $remote_id ) . '/comments',
			array(
				'json'    => array(
					'actor'   => $this->author( $acc ),
					'object'  => $remote_id,
					'message' => array( 'text' => $text ),
				),
				'headers' => $this->h( $acc ),
			)
		);
		return $r['ok'] ? (string) ( $r['headers']['x-restli-id'] ?? '1' ) : $this->err( $r, 'LinkedIn' );
	}

	public function account_metrics( array $acc ) {
		if ( 'org' !== ( $acc['meta']['kind'] ?? '' ) ) {
			return new WP_Error( 'flxsm_unsupported', '' );
		}
		$r = flxsm_http(
			'GET',
			self::API . '/rest/networkSizes/' . rawurlencode( 'urn:li:organization:' . $acc['ext_id'] ),
			array(
				'query'   => array( 'edgeType' => 'COMPANY_FOLLOWED_BY_MEMBER' ),
				'headers' => $this->h( $acc ),
			)
		);
		if ( ! $r['ok'] ) {
			return $this->err( $r, 'LinkedIn' );
		}
		return array( 'followers' => (int) ( $r['json']['firstDegreeSize'] ?? 0 ) );
	}

	public function post_metrics( array $acc, $remote_id, array $target ) {
		$r = flxsm_http( 'GET', self::API . '/rest/socialActions/' . rawurlencode( $remote_id ), array( 'headers' => $this->h( $acc ) ) );
		if ( ! $r['ok'] ) {
			return $this->err( $r, 'LinkedIn' );
		}
		return array(
			'likes'    => (int) ( $r['json']['likesSummary']['totalLikes'] ?? 0 ),
			'comments' => (int) ( $r['json']['commentsSummary']['aggregatedTotalComments'] ?? 0 ),
		);
	}
}
