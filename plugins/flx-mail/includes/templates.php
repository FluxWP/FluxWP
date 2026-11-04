<?php
/**
 * E-posta şablon kataloğu.
 *
 * Üç tür kayıt vardır:
 *  - Kayıtlı şablonlar: içerik buradan düzenlenir (WordPress çekirdeği, Flux Certificates ve flxi_email_types filtresiyle kaydolan eklentiler).
 *  - WooCommerce e-postaları: içerik WooCommerce'te, burada açma/kapama ve kayıt.
 *  - Bulunan e-postalar: başka eklentilerin wp_mail ile gönderdiği, ilk gönderimde otomatik listeye düşen e-postalar
 *    (açma/kapama, gönderen ve kurumsal çerçeve ayarlanır).
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/**
 * Kayıtlı şablonlar.
 * Her şablon: label, group, description, recipient (user|admin), vars (ad => açıklama), defaults (subject, heading, body, button_text, button_url, sender),
 * editable (içerik düzenlenebilir mi), can_disable, sample (önizleme değerleri), wrap_only (yalnız çerçeve + açma/kapama).
 */
function flxi_email_types() {
	static $types = null;
	if ( null !== $types && ! doing_filter( 'flxi_email_types' ) ) {
		return $types;
	}
	$types = array();
	foreach ( flxi_core_email_types() as $k => $t ) {
		$types[ $k ] = $t;
	}
	$types = (array) apply_filters( 'flxi_email_types', $types );
	foreach ( $types as $k => $t ) {
		$types[ $k ] = wp_parse_args(
			$t,
			array(
				'label'       => $k,
				'group'       => __( 'Diğer', 'flx-mail' ),
				'description' => '',
				'recipient'   => 'user',
				'vars'        => array(),
				'defaults'    => array(),
				'editable'    => true,
				'can_disable' => true,
				'sample'      => array(),
				'image'       => false,
			)
		);
		$types[ $k ]['defaults'] = wp_parse_args(
			$types[ $k ]['defaults'],
			array(
				'subject'     => '',
				'heading'     => '',
				'body'        => '',
				'button_text' => '',
				'button_url'  => '',
				'sender'      => 'info',
				'enabled'     => 1,
			)
		);
	}
	return $types;
}

/** Kaydedilmiş değişiklikler (şablon anahtarı => alanlar). */
function flxi_template_overrides() {
	$o = get_option( 'flxi_templates', array() );
	return is_array( $o ) ? $o : array();
}

/** Şablonun geçerli ayarları: varsayılan + kaydedilen. */
function flxi_template( $key ) {
	$types = flxi_email_types();
	$over  = flxi_template_overrides()[ $key ] ?? array();
	if ( isset( $types[ $key ] ) ) {
		$t        = $types[ $key ];
		$conf     = wp_parse_args( array_filter( $over, 'flxi_not_empty_string' ), $t['defaults'] );
		$conf['enabled'] = isset( $over['enabled'] ) ? (int) $over['enabled'] : (int) $t['defaults']['enabled'];
		$conf['wrap']    = isset( $over['wrap'] ) ? (int) $over['wrap'] : 1;
		$conf['type']    = $t;
		$conf['kind']    = 'registered';
		return $conf;
	}
	// WooCommerce ya da bulunan e-posta
	return array(
		'enabled' => isset( $over['enabled'] ) ? (int) $over['enabled'] : 1,
		'sender'  => $over['sender'] ?? '',
		'wrap'    => isset( $over['wrap'] ) ? (int) $over['wrap'] : 1,
		'kind'    => 0 === strpos( $key, 'wc/' ) ? 'woocommerce' : 'discovered',
		'type'    => null,
	);
}

function flxi_not_empty_string( $v ) {
	return ! ( is_string( $v ) && '' === trim( $v ) ) && null !== $v;
}

function flxi_template_enabled( $key ) {
	return (bool) flxi_template( $key )['enabled'];
}

function flxi_save_template( $key, array $data ) {
	$all   = flxi_template_overrides();
	$cur   = $all[ $key ] ?? array();
	$clean = array();
	foreach ( $data as $k => $v ) {
		switch ( $k ) {
			case 'enabled':
			case 'wrap':
				$clean[ $k ] = $v ? 1 : 0;
				break;
			case 'sender':
				$clean[ $k ] = in_array( $v, array( 'info', 'noreply', '' ), true ) ? $v : '';
				break;
			case 'body':
				$clean[ $k ] = wp_kses_post( (string) $v );
				break;
			case 'button_url':
				$clean[ $k ] = sanitize_text_field( (string) $v );
				break;
			case 'subject':
			case 'heading':
			case 'button_text':
				$clean[ $k ] = sanitize_text_field( (string) $v );
				break;
		}
	}
	$all[ $key ] = array_merge( $cur, $clean );
	update_option( 'flxi_templates', $all, false );
	do_action( 'flxi_template_saved', $key, $all[ $key ] );
}

/** Şablonu varsayılana döndürür (açık/kapalı durumu korunur). */
function flxi_reset_template( $key ) {
	$all = flxi_template_overrides();
	if ( isset( $all[ $key ] ) ) {
		$keep        = array_intersect_key( $all[ $key ], array_flip( array( 'enabled' ) ) );
		$all[ $key ] = $keep;
		update_option( 'flxi_templates', $all, false );
	}
}

/**
 * Kayıtlı bir şablonu değişkenlerle doldurup e-posta parçalarını üretir.
 *
 * @return array subject, html, sender, enabled
 */
function flxi_build_template( $key, array $vars = array(), array $extra = array() ) {
	$conf = flxi_template( $key );
	$t    = $conf['type'];
	// Önizlemede örnek değerler, gerçek gönderimde boş kalan değişkenler boş metin olur.
	$blank  = $t ? array_fill_keys( array_map( 'strtolower', array_keys( (array) $t['vars'] ) ), '' ) : array();
	$sample = ! empty( $extra['use_sample'] ) && $t ? (array) $t['sample'] : array();
	$vars   = array_merge( $blank, $sample, $vars );
	$subject = flxi_fill( $conf['subject'], $vars );
	$heading = flxi_fill( $conf['heading'], $vars, true );
	$body    = flxi_fill( flxi_body_html( $conf['body'] ), $vars, true );
	$args    = array_merge(
		array(
			'subject'     => $subject,
			'heading'     => $heading,
			'body'        => $body,
			'button_text' => flxi_fill( $conf['button_text'], $vars ),
			'button_url'  => flxi_fill( $conf['button_url'], $vars ),
			'sender'      => $conf['sender'],
		),
		$extra
	);
	$sample = ! empty( $args['use_sample'] );
	unset( $args['use_sample'] );
	$args = apply_filters( 'flxi_template_args', $args, $key, $vars, $sample );
	return array(
		'subject' => $args['subject'],
		'html'    => flxi_render_email( $args ),
		'sender'  => $conf['sender'],
		'enabled' => (bool) $conf['enabled'],
		'args'    => $args,
	);
}

/** Editörden gelen gövde: blok etiketi yoksa paragraflara çevrilir. */
function flxi_body_html( $body ) {
	$body = (string) $body;
	if ( preg_match( '/^\s*\{[a-z0-9_]+_html\}\s*$/i', $body ) ) {
		return trim( $body );
	}
	if ( ! preg_match( '~<(p|div|ul|ol|table|h[1-6]|blockquote)[\s>]~i', $body ) ) {
		$body = wpautop( $body );
	}
	return $body;
}

/* ------------------------------------------------------------------ */
/* Bulunan e-postalar                                                  */
/* ------------------------------------------------------------------ */

function flxi_discovered() {
	$d = get_option( 'flxi_discovered', array() );
	return is_array( $d ) ? $d : array();
}

/** Başka bir eklentinin e-postası ilk kez görülünce kataloğa eklenir (sonra yalnız sayaç güncellenir, saatte en fazla bir yazma). */
function flxi_remember_discovered( $key, $source, $caller, $subject ) {
	$all = flxi_discovered();
	$now = time();
	if ( isset( $all[ $key ] ) ) {
		$all[ $key ]['count'] = (int) $all[ $key ]['count'] + 1;
		if ( $now - (int) $all[ $key ]['last'] < HOUR_IN_SECONDS && $all[ $key ]['count'] % 20 ) {
			return;
		}
		$all[ $key ]['last']    = $now;
		$all[ $key ]['subject'] = mb_substr( wp_strip_all_tags( $subject ), 0, 160 );
	} else {
		if ( count( $all ) > 300 ) {
			return;
		}
		$all[ $key ] = array(
			'source'  => $source,
			'caller'  => $caller,
			'subject' => mb_substr( wp_strip_all_tags( $subject ), 0, 160 ),
			'count'   => 1,
			'first'   => $now,
			'last'    => $now,
		);
	}
	update_option( 'flxi_discovered', $all, false );
}

/** Kaynak (eklenti klasörü) için okunur ad. */
function flxi_source_label( $slug ) {
	$known = array(
		'wordpress'                      => 'WordPress',
		'woocommerce'                    => 'WooCommerce',
		'flx-certificates'                   => 'Flux Certificates',
		'flx-donate'                       => 'Flux Donate',
		'flx-forms'                        => 'Flux Forms',
		'flx-wc-bagis'                    => 'Flux WooCommerce Bağış',
		'flx-tasks'                       => 'Flux Tasks',
		'flx-chat'               => 'Flux Chat',
		'flx-seo'                         => 'Flux SEO',
		'flx-mail'                    => 'Flux Mail',
		'tema'                           => __( 'Tema', 'flx-mail' ),
		'nextend-facebook-connect'       => 'Nextend Social Login',
		'google-site-kit'                => 'Site Kit by Google',
		'super-socializer'               => 'Super Socializer',
		'miniorange-login-openid'        => 'miniOrange Social Login',
		'litespeed-cache'                => 'LiteSpeed Cache',
		'hostinger'                      => 'Hostinger',
		'all-in-one-seo-pack'            => 'All in One SEO',
	);
	if ( isset( $known[ $slug ] ) ) {
		return $known[ $slug ];
	}
	if ( function_exists( 'get_plugins' ) ) {
		foreach ( get_plugins() as $file => $data ) {
			if ( 0 === strpos( $file, $slug . '/' ) ) {
				return $data['Name'];
			}
		}
	}
	return $slug;
}

/** Bulunan e-postanın okunur adı: çağıran fonksiyondan. */
function flxi_discovered_label( $key, array $d ) {
	$labels = apply_filters(
		'flxi_discovered_labels',
		array(
			'flx-certificates:flxs_email_send'                          => __( 'Sertifika e-postası', 'flx-mail' ),
			'flx-wc-bagis:FLXWB_Receipts::notify_admin'              => __( 'Dekont yüklendi (yöneticiye)', 'flx-mail' ),
			'wordpress:wp_notify_moderator'                        => __( 'Onay bekleyen yorum', 'flx-mail' ),
			'wordpress:wp_notify_postauthor'                       => __( 'Yazıya yeni yorum', 'flx-mail' ),
		)
	);
	if ( isset( $labels[ $key ] ) ) {
		return $labels[ $key ];
	}
	return $d['subject'] ? $d['subject'] : $d['caller'];
}
