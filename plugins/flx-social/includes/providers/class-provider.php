<?php
/**
 * Sağlayıcı temel sınıfı. Her ağ bir alt sınıf; kayıt `flxsm_providers` filtresiyle.
 *
 * Yayın adım adım ilerler (Hostinger PHP ~60 sn'de keser): publish_step() bir ya da birkaç kısa HTTP
 * isteği yapar ve şunlardan birini döner:
 *   array( 'done' => true, 'id' => dış kimlik, 'url' => bağlantı )
 *   array( 'wait' => saniye, 'state' => sonraki adımın durumu )   // video işleniyor, parça yükleniyor…
 *   WP_Error (data: retry => geçici mi, auth => belirteç geçersiz mi, wait => kaç sn sonra)
 */

defined( 'ABSPATH' ) || exit;

abstract class FLXSM_Provider {

	/** Sağlayıcı kimliği (hesap tablosundaki provider). */
	abstract public function id();

	/** Görünen ad. */
	abstract public function label();

	/** Hangi uygulama anahtarlarını kullanır (Ayarlar › Uygulamalar). */
	public function app() {
		return $this->id();
	}

	/** Marka rengi (arayüz). */
	public function color() {
		return '#5f6b64';
	}

	/**
	 * Yetenekler. Arayüz ve doğrulama bunları kullanır.
	 * kinds: hangi gönderi türleri; options: editörde gösterilecek hesaba özel alanlar.
	 */
	public function caps( $acc = null ) {
		return array(
			'text'        => true,
			'image'       => true,
			'video'       => true,
			'gif'         => false,
			'max_text'    => 2000,
			'max_images'  => 1,
			'max_videos'  => 1,
			'mix'         => false,
			'needs_media' => false,
			'edit'        => false,
			'delete'      => false,
			'comment'     => false,
			'metrics'     => false,
			'options'     => array(),
		);
	}

	/** Gönderim öncesi kurallar. $pl: text, media[], options. */
	public function validate( array $pl, $acc ) {
		$c    = $this->caps( $acc );
		$errs = array();
		$text = trim( (string) $pl['text'] );
		$imgs = array_filter( $pl['media'], fn( $m ) => 'image' === $m['kind'] );
		$gifs = array_filter( $pl['media'], fn( $m ) => 'gif' === $m['kind'] );
		$vids = array_filter( $pl['media'], fn( $m ) => 'video' === $m['kind'] );
		if ( '' === $text && ! $pl['media'] ) {
			$errs[] = __( 'Text or media is required.', 'flx-social' );
		}
		if ( flxsm_len( $text ) > $c['max_text'] ) {
			/* translators: 1: max, 2: current */
			$errs[] = sprintf( __( 'Text can be at most %1$d characters (currently %2$d).', 'flx-social' ), $c['max_text'], flxsm_len( $text ) );
		}
		if ( $c['needs_media'] && ! $pl['media'] ) {
			$errs[] = __( 'This network requires an image or video.', 'flx-social' );
		}
		if ( $imgs && ! $c['image'] ) {
			$errs[] = __( 'This network does not accept images.', 'flx-social' );
		}
		if ( $gifs && ! $c['gif'] ) {
			$errs[] = __( 'This network does not accept GIFs; use an image or video.', 'flx-social' );
		}
		if ( $vids && ! $c['video'] ) {
			$errs[] = __( 'This network does not accept video.', 'flx-social' );
		}
		if ( count( $imgs ) > $c['max_images'] ) {
			/* translators: %d: max images */
			$errs[] = sprintf( __( 'At most %d images.', 'flx-social' ), $c['max_images'] );
		}
		if ( count( $vids ) > $c['max_videos'] ) {
			/* translators: %d: max videos */
			$errs[] = sprintf( __( 'At most %d videos.', 'flx-social' ), $c['max_videos'] );
		}
		if ( ! $c['mix'] && $vids && ( $imgs || $gifs ) ) {
			$errs[] = __( 'Images and video cannot be used together in one post.', 'flx-social' );
		}
		foreach ( $pl['media'] as $m ) {
			if ( ! $m['url'] || 0 !== strpos( $m['url'], 'https://' ) ) {
				$errs[] = __( 'Media files must be reachable at a public https address.', 'flx-social' );
				break;
			}
		}
		return $errs;
	}

	/* ---------------- OAuth ---------------- */

	/** Bağlantı başlatma adresi. $ctx: state, verifier (PKCE), for (alt tür). WP_Error dönebilir. */
	public function auth_url( array $ctx ) {
		return new WP_Error( 'flxsm_oauth', __( 'This network does not connect via OAuth.', 'flx-social' ) );
	}

	/** Callback: belirteci alır, eklenebilecek hesapları döner (dizi). */
	public function exchange( array $params, array $ctx ) {
		return new WP_Error( 'flxsm_oauth', __( 'This network does not connect via OAuth.', 'flx-social' ) );
	}

	/** Gerekirse belirteci yeniler. Dönüş: güncel hesap ya da WP_Error. */
	public function ensure( array $acc ) {
		if ( $acc['expires'] && $acc['expires'] < time() + 300 ) {
			$r = $this->refresh( $acc );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			flxsm_update_account(
				$acc['id'],
				array(
					'token'      => $r['token'],
					'expires'    => (int) $r['expires'],
					'status'     => 'active',
					'last_error' => '',
				)
			);
			$acc['token']   = $r['token'];
			$acc['expires'] = (int) $r['expires'];
		}
		return $acc;
	}

	/** Belirteç yenileme: array( token => [], expires => ts ) ya da WP_Error. */
	public function refresh( array $acc ) {
		return new WP_Error( 'flxsm_expired', __( 'The connection expired, please reconnect.', 'flx-social' ), array( 'auth' => true ) );
	}

	/* ---------------- Yayın ---------------- */

	abstract public function publish_step( array $acc, array $pl, array $state );

	public function edit( array $acc, $remote_id, array $pl ) {
		return new WP_Error( 'flxsm_unsupported', __( 'This network does not allow editing a published post.', 'flx-social' ) );
	}

	public function delete( array $acc, $remote_id ) {
		return new WP_Error( 'flxsm_unsupported', __( 'This network does not allow deleting posts via the API; delete it in the app.', 'flx-social' ) );
	}

	public function comment( array $acc, $remote_id, $text ) {
		return new WP_Error( 'flxsm_unsupported', __( 'First comment is not supported on this network.', 'flx-social' ) );
	}

	/** Hesap metrikleri: array( followers => int, ... ). */
	public function account_metrics( array $acc ) {
		return new WP_Error( 'flxsm_unsupported', '' );
	}

	/** Gönderi metrikleri: array( likes, comments, shares, views, impressions, clicks ). */
	public function post_metrics( array $acc, $remote_id, array $target ) {
		return new WP_Error( 'flxsm_unsupported', '' );
	}

	/* ---------------- Yardımcılar ---------------- */

	/** HTTP cevabını hataya çevirir; 401/190 → auth, 429/5xx/ağ → retry. */
	protected function err( array $r, $prefix = '' ) {
		$code  = (int) $r['code'];
		$auth  = 401 === $code || 190 === (int) ( $r['json']['error']['code'] ?? 0 ) || 'invalid_grant' === ( $r['json']['error'] ?? '' );
		$retry = ! empty( $r['net'] ) || 429 === $code || $code >= 500 || in_array( (int) ( $r['json']['error']['code'] ?? 0 ), array( 1, 2, 4, 17, 32, 341, 613 ), true );
		$wait  = 0;
		if ( 429 === $code ) {
			$ra   = (int) ( $r['headers']['retry-after'] ?? 0 );
			$rst  = (int) ( $r['headers']['x-rate-limit-reset'] ?? 0 );
			$wait = $ra ? $ra : ( $rst > time() ? $rst - time() : 900 );
		}
		return new WP_Error( 'flxsm_api', ( $prefix ? $prefix . ': ' : '' ) . $r['error'], array( 'retry' => $retry, 'auth' => $auth, 'wait' => $wait, 'http' => $code ) );
	}

	protected function pkce_challenge( $verifier ) {
		return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
	}

	/** Uygulama anahtarları eksikse hata. */
	protected function need_app( $public_field, $secret_key = '' ) {
		if ( '' === flxsm_app( $this->app(), $public_field ) || ( $secret_key && ! flxsm_has_secret( $secret_key ) ) ) {
			return new WP_Error( 'flxsm_app', __( 'First enter this network\'s keys under Settings › Apps.', 'flx-social' ), array( 'status' => 400 ) );
		}
		return true;
	}

	protected function first_video( array $pl ) {
		foreach ( $pl['media'] as $m ) {
			if ( 'video' === $m['kind'] ) {
				return $m;
			}
		}
		return null;
	}

	protected function images( array $pl ) {
		return array_values( array_filter( $pl['media'], fn( $m ) => 'video' !== $m['kind'] ) );
	}
}
