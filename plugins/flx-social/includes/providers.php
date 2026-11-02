<?php
/** Sağlayıcı kaydı ve uygulama (anahtar) tanımları. */

defined( 'ABSPATH' ) || exit;

/** @return FLXSM_Provider[] */
function flxsm_providers() {
	static $list = null;
	if ( null === $list ) {
		$list = array();
		$cls  = apply_filters(
			'flxsm_providers',
			array(
				'facebook'  => 'FLXSM_Facebook',
				'instagram' => 'FLXSM_Instagram',
				'youtube'   => 'FLXSM_YouTube',
				'x'         => 'FLXSM_X',
				'linkedin'  => 'FLXSM_LinkedIn',
				'tiktok'    => 'FLXSM_TikTok',
				'pinterest' => 'FLXSM_Pinterest',
				'gbp'       => 'FLXSM_GBP',
				'whatsapp'  => 'FLXSM_WhatsApp',
			)
		);
		foreach ( $cls as $id => $c ) {
			if ( class_exists( $c ) ) {
				$list[ $id ] = new $c();
			}
		}
	}
	return $list;
}

/** @return FLXSM_Provider|null */
function flxsm_provider( $id ) {
	$all = flxsm_providers();
	return $all[ $id ] ?? null;
}

/**
 * Uygulamalar: Ayarlar › Uygulamalar ekranı. Alanlar: public (açık saklanır) ya da secret (şifreli, yalnız yazılır).
 * connect: hesap bağlama düğmeleri (oauth app + for).
 */
function flxsm_apps() {
	$apps = array(
		'meta'      => array(
			'label'    => 'Meta (Facebook + Instagram)',
			'console'  => 'https://developers.facebook.com/apps/',
			'fields'   => array(
				array( 'key' => 'app_id', 'label' => 'App ID' ),
				array( 'key' => 'meta_secret', 'label' => 'App Secret', 'secret' => true ),
				array( 'key' => 'config_id', 'label' => __( 'Login for Business yapılandırma kimliği (isteğe bağlı)', 'flx-social' ) ),
			),
			'redirect' => flxsm_redirect_uri( 'meta' ),
			'help'     => __( 'developers.facebook.com › Uygulama oluştur (İşletme türü) › Facebook Girişi ürünü › Geçerli OAuth yönlendirme adresine aşağıdaki adresi ekleyin. Kendi sayfalarınız için uygulamada yönetici/geliştirici olmanız yeterli; App Review gerekmez. Instagram profesyonel hesabı bir Facebook sayfasına bağlıysa aynı girişle gelir.', 'flx-social' ),
			'connect'  => array(
				array( 'for' => 'facebook', 'label' => __( 'Facebook sayfası (+ bağlı Instagram)', 'flx-social' ), 'provider' => 'facebook' ),
			),
		),
		'instagram' => array(
			'label'    => __( 'Instagram girişi (Facebook sayfası olmadan)', 'flx-social' ),
			'console'  => 'https://developers.facebook.com/apps/',
			'fields'   => array(
				array( 'key' => 'app_id', 'label' => 'Instagram App ID' ),
				array( 'key' => 'instagram_secret', 'label' => 'Instagram App Secret', 'secret' => true ),
			),
			'redirect' => flxsm_redirect_uri( 'instagram' ),
			'help'     => __( 'Meta uygulamasına "Instagram" ürününü ekleyin (API setup with Instagram login). Ürün sayfasındaki Instagram App ID/Secret buraya; yönlendirme adresi aşağıda. Hesap İşletme ya da İçerik üretici olmalı.', 'flx-social' ),
			'connect'  => array(
				array( 'for' => 'instagram', 'label' => __( 'Instagram (Instagram girişi)', 'flx-social' ), 'provider' => 'instagram' ),
			),
		),
		'google'    => array(
			'label'    => 'Google (YouTube + İşletme Profili)',
			'console'  => 'https://console.cloud.google.com/apis/credentials',
			'fields'   => array(
				array( 'key' => 'client_id', 'label' => 'Client ID' ),
				array( 'key' => 'google_secret', 'label' => 'Client Secret', 'secret' => true ),
			),
			'redirect' => flxsm_redirect_uri( 'google' ),
			'help'     => __( 'Google Cloud › API’ler: YouTube Data API v3 (ve İşletme için My Business Account Management, Business Information, Google My Business API) etkinleştirin › OAuth istemcisi (Web uygulaması) › Yetkili yönlendirme adresi aşağıda. Denetlenmemiş projede YouTube’a yüklenen videolar "Özel" kalır; herkese açık için Google API denetimine başvurun. İşletme Profili API’si için Google’dan erişim onayı gerekir.', 'flx-social' ),
			'connect'  => array(
				array( 'for' => 'youtube', 'label' => __( 'YouTube kanalı', 'flx-social' ), 'provider' => 'youtube' ),
				array( 'for' => 'gbp', 'label' => __( 'Google İşletme konumu', 'flx-social' ), 'provider' => 'gbp' ),
			),
		),
		'x'         => array(
			'label'    => 'X (Twitter)',
			'console'  => 'https://developer.x.com/en/portal/dashboard',
			'fields'   => array(
				array( 'key' => 'api_key', 'label' => 'API Key (Consumer Key)' ),
				array( 'key' => 'x_secret', 'label' => 'API Key Secret', 'secret' => true ),
			),
			'redirect' => flxsm_redirect_uri( 'x' ),
			'help'     => __( 'developer.x.com › Proje › Uygulama › User authentication settings: izin "Read and write", tür "Web App", Callback URI aşağıdaki adres. Keys and tokens bölümündeki API Key ve Secret buraya. Katmanı aşağıdan seçin (ücretsiz katmanda yazma sınırı düşüktür, metrikler okunmaz).', 'flx-social' ),
			'connect'  => array(
				array( 'for' => 'x', 'label' => __( 'X hesabı', 'flx-social' ), 'provider' => 'x' ),
			),
		),
		'linkedin'  => array(
			'label'    => 'LinkedIn',
			'console'  => 'https://www.linkedin.com/developers/apps',
			'fields'   => array(
				array( 'key' => 'client_id', 'label' => 'Client ID' ),
				array( 'key' => 'linkedin_secret', 'label' => 'Client Secret', 'secret' => true ),
				array( 'key' => 'version', 'label' => __( 'API sürümü (YYYYAA)', 'flx-social' ), 'placeholder' => '202607' ),
			),
			'redirect' => flxsm_redirect_uri( 'linkedin' ),
			'help'     => __( 'linkedin.com/developers › Uygulama (dernek sayfasına bağlı) › Products: "Sign In with LinkedIn using OpenID Connect" ve "Share on LinkedIn" (kişisel); şirket sayfası için "Community Management API" (onay gerekir). Auth › Authorized redirect URL aşağıda.', 'flx-social' ),
			'connect'  => array(
				array( 'for' => 'linkedin', 'label' => __( 'LinkedIn profil / sayfa', 'flx-social' ), 'provider' => 'linkedin' ),
			),
		),
		'tiktok'    => array(
			'label'    => 'TikTok',
			'console'  => 'https://developers.tiktok.com/apps',
			'fields'   => array(
				array( 'key' => 'client_key', 'label' => 'Client Key' ),
				array( 'key' => 'tiktok_secret', 'label' => 'Client Secret', 'secret' => true ),
			),
			'redirect' => flxsm_redirect_uri( 'tiktok' ),
			'help'     => __( 'developers.tiktok.com › Uygulama › Login Kit + Content Posting API ekleyin, Redirect URI aşağıda. Denetim (audit) yoksa "Taslak" modu önerilir: video TikTok uygulamasına düşer, oradan yayınlanır. Fotoğraf paylaşımı için site alan adını TikTok’ta doğrulayın (URL properties).', 'flx-social' ),
			'connect'  => array(
				array( 'for' => 'tiktok', 'label' => __( 'TikTok hesabı', 'flx-social' ), 'provider' => 'tiktok' ),
			),
		),
		'pinterest' => array(
			'label'    => 'Pinterest',
			'console'  => 'https://developers.pinterest.com/apps/',
			'fields'   => array(
				array( 'key' => 'app_id', 'label' => 'App ID' ),
				array( 'key' => 'pinterest_secret', 'label' => 'App Secret', 'secret' => true ),
			),
			'redirect' => flxsm_redirect_uri( 'pinterest' ),
			'help'     => __( 'developers.pinterest.com › Uygulama › Redirect URI aşağıda. Deneme (trial) erişiminde yalnız sandbox çalışır; Standard erişim için başvurun.', 'flx-social' ),
			'connect'  => array(
				array( 'for' => 'pinterest', 'label' => __( 'Pinterest hesabı', 'flx-social' ), 'provider' => 'pinterest' ),
			),
		),
		'whatsapp'  => array(
			'label'   => 'WhatsApp (Evolution)',
			'fields'  => array(),
			'help'    => __( 'Evolution bağlantısı Flux Mail’den (ya da Flux Chat’nden) gelir; burada anahtar girilmez. Durum ve gruplara paylaşım yapılır.', 'flx-social' ),
			'connect' => array(
				array( 'for' => 'whatsapp', 'label' => __( 'WhatsApp Durum / grup', 'flx-social' ), 'provider' => 'whatsapp', 'local' => true ),
			),
		),
	);
	return apply_filters( 'flxsm_apps', $apps );
}

/** Arayüz için uygulama durumu (gizli değer yok). */
function flxsm_apps_public() {
	$out = array();
	foreach ( flxsm_apps() as $id => $a ) {
		$fields = array();
		$ready  = true;
		foreach ( $a['fields'] as $f ) {
			$secret   = ! empty( $f['secret'] );
			$has      = $secret ? flxsm_has_secret( $f['key'] ) : '' !== flxsm_app( $id, $f['key'] );
			$fields[] = array(
				'key'         => $f['key'],
				'label'       => $f['label'],
				'secret'      => $secret,
				'value'       => $secret ? '' : (string) flxsm_app( $id, $f['key'] ),
				'set'         => $has,
				'placeholder' => (string) ( $f['placeholder'] ?? '' ),
				'optional'    => in_array( $f['key'], array( 'config_id', 'version' ), true ),
			);
			if ( ! $has && ! in_array( $f['key'], array( 'config_id', 'version' ), true ) ) {
				$ready = false;
			}
		}
		if ( 'whatsapp' === $id ) {
			$ready = ! is_wp_error( flxsm_wa_config() );
		}
		$out[] = array(
			'id'       => $id,
			'label'    => $a['label'],
			'console'  => (string) ( $a['console'] ?? '' ),
			'fields'   => $fields,
			'redirect' => (string) ( $a['redirect'] ?? '' ),
			'help'     => (string) ( $a['help'] ?? '' ),
			'connect'  => $a['connect'],
			'ready'    => $ready,
		);
	}
	return $out;
}

/** Uygulama alanlarını kaydeder. Gizli alan boş gelirse eskisi kalır; "__clear__" siler. */
function flxsm_save_app( $id, array $values ) {
	$apps = flxsm_apps();
	if ( ! isset( $apps[ $id ] ) ) {
		return new WP_Error( 'flxsm_app', __( 'Bilinmeyen uygulama.', 'flx-social' ), array( 'status' => 400 ) );
	}
	$all = (array) flxsm_setting( 'apps', array() );
	foreach ( $apps[ $id ]['fields'] as $f ) {
		if ( ! array_key_exists( $f['key'], $values ) ) {
			continue;
		}
		$v = trim( (string) $values[ $f['key'] ] );
		if ( ! empty( $f['secret'] ) ) {
			if ( '__clear__' === $v ) {
				flxsm_set_secret( $f['key'], '' );
			} elseif ( '' !== $v ) {
				flxsm_set_secret( $f['key'], $v );
			}
		} else {
			$all[ $id ][ $f['key'] ] = sanitize_text_field( $v );
		}
	}
	flxsm_save_settings( array( 'apps' => $all ) );
	return true;
}

/** Bağlantı başlatırken hangi sağlayıcı sınıfı ve uygulama: for → [provider, app]. */
function flxsm_connect_target( $for ) {
	$map = array(
		'facebook'  => array( 'facebook', 'meta' ),
		'instagram' => array( 'instagram', 'instagram' ),
		'youtube'   => array( 'youtube', 'google' ),
		'gbp'       => array( 'gbp', 'google' ),
		'x'         => array( 'x', 'x' ),
		'linkedin'  => array( 'linkedin', 'linkedin' ),
		'tiktok'    => array( 'tiktok', 'tiktok' ),
		'pinterest' => array( 'pinterest', 'pinterest' ),
	);
	return $map[ $for ] ?? null;
}
