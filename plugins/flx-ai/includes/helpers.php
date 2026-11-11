<?php
/**
 * Ayarlar, gizli değerler ve yetkiler.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** Varsayılan ayarlar. Gizli alanlar (key_*) şifreli saklanır. */
function flxai_defaults() {
	return array(
		'key_openrouter'      => '',
		'key_anthropic'       => '',
		'anthropic_workspace' => '',
		'model'               => '',        // varsayılan model (tüm eklentiler)
		'chat_model'          => '',        // ortak sohbet; boşsa varsayılan
		'title_model'         => '',        // sohbet adı; boşsa sohbet modeli
		'source_models'       => array(),   // kaynak (eklenti) => model
		'cache'               => '5m',      // off | 5m | 1h
		'effort'              => 'low',     // Claude: low | medium | high
		'thinking'            => 1,         // Claude düşünme özeti görünsün
		'limit_day'           => 0,         // USD, 0 = sınırsız
		'limit_week'          => 0,
		'limit_month'         => 0,
		'limit_action'        => 'block',   // block | warn
		'limit_warn_pct'      => 80,
		'limit_notify'        => 1,         // %80 ve aşımda yöneticilere e-posta
		'chat_roles'          => array( 'administrator' ),
		'chat_admin'          => 1,
		'chat_front'          => 1,
		'chat_mode'           => 'bubble',  // bubble | dock
		'chat_max_rounds'     => 12,
		'chat_history'        => 40,        // modele giden en fazla mesaj
		'chat_instructions'   => '',
		'chat_widgets'        => 1,         // sohbette ```widget / ```mermaid görselleri
		'render_url'          => '',        // Gotenberg adresi (WhatsApp vb. için HTML → PNG)
		'render_user'         => '',
		'key_render'          => '',
		'key_fal'             => '',        // görsel/video sağlayıcıları (media.php)
		'key_evolink'         => '',
		'key_openai'          => '',
		'key_gemini'          => '',
		'media_image_model'   => 'gemini-nano-banana-2.1',
		'media_video_model'   => 'seedance-2.5',
		'media_days'          => 90,        // iş kayıtları (dosyalar silinmez)
		'tools_off'           => array(),   // kapalı araçlar (sohbet + MCP)
		'tool_pick'           => array(),   // ad çakışmasında açık olacak kaynak: ad => eklenti
		'sources_off'         => array(),   // Flux AI & MCP › Bağlı sistemler'de kapatılan kaynaklar
		'log_bodies'          => 1,         // istek/cevap içeriği (maskeli, kısaltılmış)
		'log_body_days'       => 0,         // 0 = sınırsız
		'log_days'            => 0,         // 0 = sınırsız
		'delete_data'         => 0,
	);
}

/** Tüm ayarlar (gizli alanlar şifreli haliyle). */
function flxai_settings() {
	if ( empty( $GLOBALS['flxai_settings_cache'] ) ) {
		$s                              = get_option( 'flxai_settings', array() );
		$GLOBALS['flxai_settings_cache'] = array_merge( flxai_defaults(), is_array( $s ) ? $s : array() );
	}
	return $GLOBALS['flxai_settings_cache'];
}

/** Tek ayar. Gizli alanlar çözülmüş döner. */
function flxai_setting( $key, $default = null ) {
	$s = flxai_settings();
	if ( 0 === strpos( $key, 'key_' ) ) {
		$const = 'FLXAI_' . strtoupper( $key );
		if ( defined( $const ) && '' !== (string) constant( $const ) ) {
			return (string) constant( $const ); // wp-config isteğe bağlı override
		}
		return flxai_decrypt( (string) ( $s[ $key ] ?? '' ) );
	}
	return array_key_exists( $key, $s ) ? $s[ $key ] : $default;
}

/** Ayarları kaydeder (yalnız verilen alanlar). */
function flxai_update_settings( array $changes ) {
	$s = get_option( 'flxai_settings', array() );
	$s = is_array( $s ) ? $s : array();
	foreach ( $changes as $k => $v ) {
		if ( 0 === strpos( $k, 'key_' ) ) {
			$v = '' === (string) $v ? '' : flxai_encrypt( (string) $v );
		}
		$s[ $k ] = $v;
	}
	update_option( 'flxai_settings', $s, true );
	$GLOBALS['flxai_settings_cache'] = null;
}

/**
 * Başka bir eklentinin AI ayarlarını Flux AI'ye aktarır (ör. Çağrı Merkezi'nden bir kerelik geçiş).
 * Yalnız Flux AI'de boş olan alanları doldurur; dolu olanlara dokunmaz.
 *
 * @param string $source Eklenti kaynağı (flx-chat …).
 * @param array  $conf   key_anthropic, key_openrouter, anthropic_workspace, model (varsayılan), source_model (bu kaynağa özel).
 *                       Model: OpenRouter kimliği (google/gemini-2.5-flash) ya da Claude için claude:<id> / claude-… kimliği.
 * @return string[] Aktarılan alanlar.
 */
function flxai_import_config( $source, array $conf ) {
	$source  = sanitize_key( (string) $source );
	$changes = array();
	foreach ( array( 'key_anthropic', 'key_openrouter', 'anthropic_workspace', 'model' ) as $k ) {
		$v = trim( (string) ( $conf[ $k ] ?? '' ) );
		if ( '' !== $v && '' === (string) flxai_setting( $k, '' ) ) {
			$v             = sanitize_text_field( $v );
			$changes[ $k ] = 'model' === $k ? flxai_parse_model( $v )['full'] : $v;
		}
	}
	$sm = trim( (string) ( $conf['source_model'] ?? '' ) );
	$all = (array) flxai_setting( 'source_models', array() );
	if ( '' !== $source && '' !== $sm && '' === (string) ( $all[ $source ] ?? '' ) ) {
		$all[ $source ]           = flxai_parse_model( sanitize_text_field( $sm ) )['full'];
		$changes['source_models'] = $all;
	}
	if ( $changes ) {
		flxai_update_settings( $changes );
	}
	return array_keys( $changes );
}

/** Kaynağa özel modeli yazar (boş = varsayılana döner). Eklentinin kendi ayar ekranındaki model seçimi için. */
function flxai_set_source_model( $source, $model ) {
	$source = sanitize_key( (string) $source );
	$all    = (array) flxai_setting( 'source_models', array() );
	$model  = sanitize_text_field( (string) $model );
	if ( '' === $model ) {
		unset( $all[ $source ] );
	} else {
		$all[ $source ] = flxai_parse_model( $model )['full'];
	}
	flxai_update_settings( array( 'source_models' => $all ) );
}

/** Gizli değeri şifreler (sodium varsa). */
function flxai_encrypt( $plain ) {
	if ( '' === $plain ) {
		return '';
	}
	if ( function_exists( 'sodium_crypto_secretbox' ) ) {
		$key   = sodium_crypto_generichash( wp_salt( 'auth' ) . 'flxai', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return 'enc1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $key ) ); // phpcs:ignore
	}
	return 'b64:' . base64_encode( $plain ); // phpcs:ignore
}

function flxai_decrypt( $stored ) {
	if ( '' === $stored ) {
		return '';
	}
	if ( 0 === strpos( $stored, 'enc1:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
		$raw = base64_decode( substr( $stored, 5 ), true ); // phpcs:ignore
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$key   = sodium_crypto_generichash( wp_salt( 'auth' ) . 'flxai', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $key );
		return false === $plain ? '' : $plain;
	}
	if ( 0 === strpos( $stored, 'b64:' ) ) {
		return (string) base64_decode( substr( $stored, 4 ), true ); // phpcs:ignore
	}
	return $stored;
}

/** Gizli değerin ekranda gösterilecek hali: "kayıtlı (…ab12)". */
function flxai_mask( $secret ) {
	$secret = (string) $secret;
	if ( '' === $secret ) {
		return '';
	}
	/* translators: %s: last 4 characters */
	return sprintf( __( 'saved (…%s)', 'flx-ai' ), substr( $secret, -4 ) );
}

/** Yönetim yetkisi (ayarlar, raporlar, faturalandırma). */
function flxai_can_manage() {
	return current_user_can( 'manage_options' );
}

/** Kullanıcı ortak sohbeti kullanabilir mi? (rol ayarı + flxai_chat yetkisi) */
function flxai_can_chat( $user_id = 0 ) {
	$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return false;
	}
	$ok = user_can( $user, 'flxai_chat' );
	if ( ! $ok ) {
		$roles = (array) flxai_setting( 'chat_roles', array() );
		$ok    = (bool) array_intersect( $roles, (array) $user->roles );
	}
	return (bool) apply_filters( 'flxai_can_chat', $ok, $user );
}

/** Kullanıcı dili TR mi? */
function flxai_is_tr() {
	return 0 === strpos( determine_locale(), 'tr' );
}

/** USD biçimi: $0.0123 */
function flxai_usd( $v, $dec = 4 ) {
	$v = (float) $v;
	if ( $v >= 100 ) {
		$dec = 2;
	}
	return '$' . number_format_i18n( $v, $dec );
}

/** Saniye/milisaniye: "1,2 sn" / "850 ms". */
function flxai_ms( $ms ) {
	$ms = (int) $ms;
	if ( $ms < 1000 ) {
		/* translators: %s: milliseconds */
		return sprintf( __( '%s ms', 'flx-ai' ), number_format_i18n( $ms ) );
	}
	/* translators: %s: seconds */
	return sprintf( __( '%s s', 'flx-ai' ), number_format_i18n( $ms / 1000, 1 ) );
}

/** Amaç etiketi (bilinenler çevrilir). */
function flxai_purpose_label( $p ) {
	$l = (array) apply_filters(
		'flxai_purpose_labels',
		array(
			'chat'  => __( 'chat', 'flx-ai' ),
			'title' => __( 'title', 'flx-ai' ),
			'test'  => __( 'test', 'flx-ai' ),
		)
	);
	return $l[ $p ] ?? (string) $p;
}

/** Bilinen kaynaklar (eklentiler). Diğer paketler flxai_sources ile kendini tanıtır. */
function flxai_sources() {
	$list = array(
		'flx-ai'            => __( 'Flux AI chat', 'flx-ai' ),
		'flx-chat' => 'Flux Chat',
		'flx-tasks'         => 'Flux Tasks',
		'flx-certificates'     => 'Flux Certificates',
		'flx-donate'         => 'Flux Donate',
		'flx-seo'           => 'Flux SEO',
		'flx-mail'      => 'Flux Mail',
		'flx-forms'          => 'Flux Forms',
	);
	return (array) apply_filters( 'flxai_sources', $list );
}

function flxai_source_label( $id ) {
	$l = flxai_sources();
	if ( '' === (string) $id ) {
		return '';
	}
	if ( 'unknown' === $id ) {
		return __( 'Unspecified', 'flx-ai' );
	}
	return $l[ $id ] ?? (string) $id;
}

/** Değişken veri dönen REST yanıtları önbelleğe alınmaz (LiteSpeed/CDN). */
add_filter(
	'rest_post_dispatch',
	function ( $res, $server, $req ) {
		if ( 0 !== strpos( (string) $req->get_route(), '/flx-ai/v1' ) || ! $res instanceof WP_REST_Response ) {
			return $res;
		}
		$res->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private' );
		$res->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
		$res->header( 'CDN-Cache-Control', 'no-store' );
		do_action( 'litespeed_control_set_nocache', 'flx-ai api' );
		return $res;
	},
	10,
	3
);

/** FluxWP Panel › Güncellemeler listesinde logo (güncelleyicide flx-ai.svg yoksa). */
add_filter(
	'flxu_package_icons',
	function ( $icons, $slug ) {
		if ( 'flx-ai' !== $slug || ! empty( $icons ) ) {
			return $icons;
		}
		$url = FLXAI_URL . 'assets/icon.svg?ver=' . FLXAI_VERSION;
		return array(
			'svg' => $url,
			'1x'  => $url,
			'2x'  => $url,
		);
	},
	10,
	2
);

/** CSV cell: quote a formula, leave a real number (including a negative) as a number. */
function flxai_csv_cell( $v ) {
	if ( is_int( $v ) || is_float( $v ) ) {
		return $v;
	}
	$s = (string) $v;
	if ( preg_match( '/^[+-]?(?:\d+(?:\.\d+)?|\.\d+)$/', $s ) ) {
		return $s;
	}
	$probe = preg_replace( '/^[\x00-\x08\x0A-\x0C\x0E-\x20]*/', '', $s );
	if ( is_string( $probe ) && isset( $probe[0] ) && false !== strpos( "=+-@\t\r", $probe[0] ) ) {
		return "'" . $s;
	}
	return $s;
}
