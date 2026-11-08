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
				array( 'key' => 'config_id', 'label' => __( 'Login for Business configuration ID (optional)', 'flx-social' ) ),
			),
			'redirect' => flxsm_redirect_uri( 'meta' ),
			'help'     => __( 'developers.facebook.com › Create app (Business type) › Facebook Login product › add the address below to Valid OAuth redirect URIs. For your own pages being an admin/developer of the app is enough; no App Review needed. An Instagram professional account linked to a Facebook page comes with the same login.', 'flx-social' ),
			'connect'  => array(
				array( 'for' => 'facebook', 'label' => __( 'Facebook page (+ linked Instagram)', 'flx-social' ), 'provider' => 'facebook' ),
			),
		),
		'instagram' => array(
			'label'    => __( 'Instagram login (without a Facebook page)', 'flx-social' ),
			'console'  => 'https://developers.facebook.com/apps/',
			'fields'   => array(
				array( 'key' => 'app_id', 'label' => 'Instagram App ID' ),
				array( 'key' => 'instagram_secret', 'label' => 'Instagram App Secret', 'secret' => true ),
			),
			'redirect' => flxsm_redirect_uri( 'instagram' ),
			'help'     => __( 'Add the "Instagram" product to the Meta app (API setup with Instagram login). Put the Instagram App ID/Secret from the product page here; the redirect URL is below. The account must be Business or Creator.', 'flx-social' ),
			'connect'  => array(
				array( 'for' => 'instagram', 'label' => __( 'Instagram (Instagram login)', 'flx-social' ), 'provider' => 'instagram' ),
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
			'help'     => __( 'Google Cloud › APIs: enable YouTube Data API v3 (and for Business: My Business Account Management, Business Information, Google My Business API) › OAuth client (Web application) › authorized redirect URL below. In an unaudited project videos uploaded to YouTube stay "Private"; apply for the Google API audit to make them public. The Business Profile API requires access approval from Google.', 'flx-social' ),
			'connect'  => array(
				array( 'for' => 'youtube', 'label' => __( 'YouTube channel', 'flx-social' ), 'provider' => 'youtube' ),
				array( 'for' => 'gbp', 'label' => __( 'Google Business location', 'flx-social' ), 'provider' => 'gbp' ),
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
			'help'     => __( 'developer.x.com › Project › App › User authentication settings: permission "Read and write", type "Web App", Callback URI the address below. Put the API Key and Secret from Keys and tokens here. Choose the tier below (the free tier has a low write limit and metrics are not read).', 'flx-social' ),
			'connect'  => array(
				array( 'for' => 'x', 'label' => __( 'X account', 'flx-social' ), 'provider' => 'x' ),
			),
		),
		'linkedin'  => array(
			'label'    => 'LinkedIn',
			'console'  => 'https://www.linkedin.com/developers/apps',
			'fields'   => array(
				array( 'key' => 'client_id', 'label' => 'Client ID' ),
				array( 'key' => 'linkedin_secret', 'label' => 'Client Secret', 'secret' => true ),
				array( 'key' => 'version', 'label' => __( 'API version (YYYYMM)', 'flx-social' ), 'placeholder' => '202607' ),
			),
			'redirect' => flxsm_redirect_uri( 'linkedin' ),
			'help'     => __( 'linkedin.com/developers › App (linked to the organization page) › Products: "Sign In with LinkedIn using OpenID Connect" and "Share on LinkedIn" (personal); for company pages "Community Management API" (needs approval). Auth › Authorized redirect URL below.', 'flx-social' ),
			'connect'  => array(
				array( 'for' => 'linkedin', 'label' => __( 'LinkedIn profile / page', 'flx-social' ), 'provider' => 'linkedin' ),
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
			'help'     => __( 'developers.tiktok.com › App › add Login Kit + Content Posting API, Redirect URI below. Without the audit "Draft" mode is recommended: the video lands in the TikTok app and is published from there. For photo posts verify the site domain in TikTok (URL properties).', 'flx-social' ),
			'connect'  => array(
				array( 'for' => 'tiktok', 'label' => __( 'TikTok account', 'flx-social' ), 'provider' => 'tiktok' ),
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
			'help'     => __( 'developers.pinterest.com › App › Redirect URI below. Trial access works only in the sandbox; apply for Standard access.', 'flx-social' ),
			'connect'  => array(
				array( 'for' => 'pinterest', 'label' => __( 'Pinterest account', 'flx-social' ), 'provider' => 'pinterest' ),
			),
		),
		'whatsapp'  => array(
			'label'   => 'WhatsApp (Evolution)',
			'fields'  => array(),
			'help'    => __( 'The Evolution connection comes from Flux Mail (or Flux Chat); no key is entered here. Shares go to Status and groups.', 'flx-social' ),
			'connect' => array(
				array( 'for' => 'whatsapp', 'label' => __( 'WhatsApp Status / group', 'flx-social' ), 'provider' => 'whatsapp', 'local' => true ),
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
		return new WP_Error( 'flxsm_app', __( 'Unknown app.', 'flx-social' ), array( 'status' => 400 ) );
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
