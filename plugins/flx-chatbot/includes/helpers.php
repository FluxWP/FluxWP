<?php
/**
 * Ayarlar, varsayılan metinler, yetki, simgeler ve küçük yardımcılar.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sayfa türleri (görünürlük).
 *
 * @return array<string,string>
 */
function flxcb_page_types() {
	return array(
		'home'     => __( 'Home', 'flx-chatbot' ),
		'posts'    => __( 'Posts', 'flx-chatbot' ),
		'pages'    => __( 'Pages', 'flx-chatbot' ),
		'shop'     => __( 'Shop and products', 'flx-chatbot' ),
		'cart'     => __( 'Cart', 'flx-chatbot' ),
		'checkout' => __( 'Checkout', 'flx-chatbot' ),
		'account'  => __( 'My account', 'flx-chatbot' ),
		'other'    => __( 'Other (archives, search, 404…)', 'flx-chatbot' ),
	);
}

function flxcb_defaults() {
	$d = array(
		// Genel.
		'enabled'          => 0,
		'mode'             => 'hybrid', // ai | hybrid | live
		// Görünüm.
		'bot_name'         => '',
		'avatar_id'        => 0,
		'avatar_icon'      => 'bot', // bot | sparkles | headset | smile | logo
		'color'            => '', // boş = tema rengi
		'color_text'       => '', // boş = otomatik
		'theme'            => 'auto', // auto | light | dark
		'position'         => 'right',
		'offset_x'         => 20,
		'offset_y'         => 20,
		'launcher_icon'    => 'chat', // chat | sparkles | headset | help | avatar
		'launcher_label'   => '',
		'launcher_size'    => 58,
		'radius'           => 18,
		'width'            => 360,
		'header_style'     => 'gradient', // solid | gradient
		'font'             => 'inherit', // inherit | system
		'mobile_full'      => 1,
		'show_branding'    => 1,
		'sound'            => 1,
		// Ana ekran.
		'home_enabled'     => 1,
		'home_title'       => '',
		'home_text'        => '',
		'welcome'          => '',
		'placeholder'      => '',
		'quick_replies'    => '',
		'links'            => '',
		'whatsapp_number'  => '',
		'whatsapp_message' => '',
		'whatsapp_label'   => '',
		'show_team'        => 1,
		'teaser_enabled'   => 1,
		'teaser_text'      => '',
		'teaser_delay'     => 12,
		// Yapay zekâ.
		'ai_persona'       => '',
		'ai_instructions'  => '',
		'ai_knowledge'     => '',
		'ai_src_pages'     => 1,
		'ai_src_posts'     => 1,
		'ai_src_products'  => 1,
		'ai_src_wiki'      => 1,
		'ai_tool_orders'   => 1,
		'ai_tool_lead'     => 1,
		'ai_cards'         => 1,
		'ai_max_turns'     => 40,
		'ai_daily_limit'   => 400,
		'ai_hour_limit'    => 30,
		// Canlı destek.
		'handoff_to'       => 'auto', // auto | flx-chat | inbox
		'hours_mode'       => 'always', // always | custom | flx-chat
		'hours'            => array(),
		'ask_email'        => 'offline', // offline | always | never
		'notify_email'     => '',
		'email_replies'    => 1,
		'rating'           => 1,
		'uploads'          => 1,
		'upload_mb'        => 5,
		'dictation'        => 1,
		'offline_text'     => '',
		// Görünürlük.
		'audience'         => 'all', // all | guests | members
		'device_desktop'   => 1,
		'device_mobile'    => 1,
		'exclude_urls'     => '',
		// Gizlilik.
		'retention_days'   => 180,
		'privacy_text'     => '',
	);
	foreach ( array_keys( flxcb_page_types() ) as $p ) {
		$d[ 'show_on_' . $p ] = 1;
	}
	for ( $i = 1; $i <= 7; $i++ ) {
		$d['hours'][ $i ] = array(
			'on'   => $i <= 5 ? 1 : 0,
			'from' => '09:00',
			'to'   => '18:00',
		);
	}
	return $d;
}

/**
 * Ayarlar (varsayılanlarla birleştirilmiş).
 *
 * @return array<string,mixed>
 */
function flxcb_settings( $refresh = false ) {
	static $cache = null;
	if ( null === $cache || $refresh ) {
		$s     = get_option( 'flxcb_settings', array() );
		$cache = array_merge( flxcb_defaults(), is_array( $s ) ? $s : array() );
		if ( ! is_array( $cache['hours'] ) || count( $cache['hours'] ) < 7 ) {
			$cache['hours'] = flxcb_defaults()['hours'];
		}
	}
	return $cache;
}

function flxcb_setting( $key ) {
	$s = flxcb_settings();
	return $s[ $key ] ?? null;
}

/**
 * @return array<string,mixed>
 */
function flxcb_update_settings( array $values ) {
	$s = array_merge( flxcb_settings(), $values );
	update_option( 'flxcb_settings', $s, false );
	flxcb_settings( true );
	return $s;
}

/**
 * Boş bırakılan metin alanlarının çevrilmiş varsayılanları.
 *
 * @return string
 */
function flxcb_default_text( $key ) {
	switch ( $key ) {
		case 'bot_name':
			return __( 'Assistant', 'flx-chatbot' );
		case 'home_title':
			return __( 'Hi there 👋', 'flx-chatbot' );
		case 'home_text':
			return __( 'How can we help? We answer your questions right away.', 'flx-chatbot' );
		case 'welcome':
			return __( 'Hi! I\'m this site\'s assistant. Ask me about products, orders or anything else.', 'flx-chatbot' );
		case 'placeholder':
			return __( 'Type your message…', 'flx-chatbot' );
		case 'teaser_text':
			return __( 'Hi! Is there anything I can help with?', 'flx-chatbot' );
		case 'offline_text':
			return __( 'No agents are online right now. Leave a message and we\'ll get back to you by email.', 'flx-chatbot' );
		case 'whatsapp_label':
			return __( 'Chat on WhatsApp', 'flx-chatbot' );
		case 'launcher_label':
			return '';
		case 'privacy_text':
			return '';
	}
	return '';
}

/** Ayardaki metin; boşsa çevrilmiş varsayılan. */
function flxcb_text( $key ) {
	$v = trim( (string) flxcb_setting( $key ) );
	return '' !== $v ? $v : flxcb_default_text( $key );
}

/** Satır listesi (hızlı cevaplar, bağlantılar). */
function flxcb_lines( $key ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) flxcb_setting( $key ) ) as $l ) {
		$l = trim( $l );
		if ( '' !== $l ) {
			$out[] = $l;
		}
	}
	return $out;
}

/** WhatsApp numarası: yalnız rakamlar (+, boşluk, tire atılır; başındaki 00 de). */
function flxcb_whatsapp_digits( $raw ) {
	$d = preg_replace( '/\D+/', '', (string) $raw );
	$d = preg_replace( '/^00/', '', (string) $d );
	return strlen( $d ) >= 7 && strlen( $d ) <= 15 ? $d : '';
}

/** Ana ekrandaki WhatsApp düğmesi; numara boşsa null (düğme çıkmaz). */
function flxcb_whatsapp() {
	$n = flxcb_whatsapp_digits( flxcb_setting( 'whatsapp_number' ) );
	if ( '' === $n ) {
		return null;
	}
	$text = trim( (string) flxcb_setting( 'whatsapp_message' ) );
	return array(
		'url'   => 'https://wa.me/' . $n . ( '' !== $text ? '?text=' . rawurlencode( $text ) : '' ),
		'label' => flxcb_text( 'whatsapp_label' ),
	);
}

/** Ana ekran bağlantıları: "Etiket | adres" satırları. */
function flxcb_links() {
	$out = array();
	foreach ( flxcb_lines( 'links' ) as $l ) {
		$p     = array_map( 'trim', explode( '|', $l, 2 ) );
		$label = $p[0];
		$url   = $p[1] ?? '';
		if ( '' === $url && preg_match( '#^(https?://|mailto:|tel:)#i', $label ) ) {
			$url = $label;
		}
		$url = esc_url_raw( $url, array( 'http', 'https', 'mailto', 'tel', 'whatsapp' ) );
		if ( '' === $url ) {
			continue;
		}
		$kind = 'link';
		if ( 0 === stripos( $url, 'mailto:' ) ) {
			$kind = 'mail';
		} elseif ( 0 === stripos( $url, 'tel:' ) ) {
			$kind = 'phone';
		} elseif ( preg_match( '#(wa\.me|whatsapp\.com|^whatsapp:)#i', $url ) ) {
			$kind = 'whatsapp';
		}
		$out[] = array(
			'label' => '' !== $label && $label !== $url ? $label : preg_replace( '#^(https?://|mailto:|tel:)#i', '', $url ),
			'url'   => $url,
			'kind'  => $kind,
		);
	}
	return array_slice( $out, 0, 6 );
}

/**
 * @return string
 */
function flxcb_admin_url( $tab = '' ) {
	return admin_url( 'admin.php?page=flx-chatbot' . ( $tab ? '&tab=' . rawurlencode( $tab ) : '' ) );
}

/**
 * Sohbetlerin yönetildiği yer: tek gelen kutusunda Flux Chat, değilse eklentinin gelen kutusu.
 *
 * @return string
 */
function flxcb_inbox_url( $conv_id = 0 ) {
	if ( function_exists( 'flxcb_fc_unified' ) && flxcb_fc_unified() ) {
		$conv = $conv_id ? flxcb_get_conversation( (int) $conv_id ) : null;
		return admin_url( 'admin.php?page=flx-chat' . ( $conv && (int) $conv['flxc_chat'] ? '&chat=' . (int) $conv['flxc_chat'] : '' ) );
	}
	return admin_url( 'admin.php?page=flx-chatbot-inbox' . ( $conv_id ? '&conv=' . (int) $conv_id : '' ) );
}

/** Temsilci olarak cevap verebilir mi (yönetici, eklenti yetkisi ya da Flux Chat temsilcisi). */
function flxcb_can_reply( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}
	return user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'flxcb_reply' ) || user_can( $user_id, 'flxc_use' );
}

/**
 * Asistanın adı.
 *
 * @return string
 */
function flxcb_bot_name() {
	return flxcb_text( 'bot_name' );
}

/** Avatar adresi ('' = simge). */
function flxcb_avatar_url() {
	$id = (int) flxcb_setting( 'avatar_id' );
	if ( $id ) {
		$u = wp_get_attachment_image_url( $id, 'thumbnail' );
		if ( $u ) {
			return $u;
		}
	}
	if ( 'logo' === flxcb_setting( 'avatar_icon' ) ) {
		$logo = get_site_icon_url( 128 );
		if ( $logo ) {
			return $logo;
		}
	}
	return '';
}

/**
 * Görünen ad (temsilci).
 *
 * @return string
 */
function flxcb_user_name( $user_id ) {
	$u = $user_id ? get_userdata( (int) $user_id ) : null;
	if ( ! $u ) {
		return '';
	}
	$first = trim( (string) $u->first_name );
	return '' !== $first ? $first : $u->display_name;
}

/** Hex renk → okunaklı yazı rengi. */
function flxcb_contrast( $hex ) {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) ) {
		return '#ffffff';
	}
	$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
	$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
	$b = hexdec( substr( $hex, 4, 2 ) ) / 255;
	$f = function ( $c ) {
		return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
	};
	$l = 0.2126 * $f( $r ) + 0.7152 * $f( $g ) + 0.0722 * $f( $b );
	return $l > 0.45 ? '#111827' : '#ffffff';
}

/** Ziyaretçi IP'sinin tuzlu özeti (yalnız hız sınırı için, saklanmaz). */
function flxcb_ip_key() {
	return substr( hash_hmac( 'sha256', flx_client_ip(), wp_salt( 'nonce' ) ), 0, 20 );
}

/**
 * Basit sayaç sınırı. true = sınır içinde (sayaç artırıldı).
 *
 * @param string $key    Anahtar.
 * @param int    $max    En fazla.
 * @param int    $window Saniye.
 */
function flxcb_rate( $key, $max, $window ) {
	return flx_rate_limit( 'flxcb|' . $key, (int) $max, (int) $window );
}

/** Tek uçuş kilidinin seçeneği (sohbet başına bir satır, bırakılınca silinir). */
function flxcb_flight_key( $conv_id ) {
	return 'flxcb_fly_' . (int) $conv_id;
}

/**
 * Sohbet başına tek asistan isteği. add_option atomiktir.
 * Bayat süre bütçenin biraz üstünde (55 sn): ölü istek kilidi tutmaz.
 *
 * @param int $conv_id Sohbet.
 * @return bool true = bu istek aldı.
 */
function flxcb_flight_acquire( $conv_id ) {
	$key = flxcb_flight_key( $conv_id );
	$now = time();
	if ( ! add_option( $key, $now, '', false ) ) {
		$started = (int) get_option( $key, 0 );
		if ( $started > 0 && ( $now - $started ) < 55 ) {
			return false;
		}
		delete_option( $key );
		if ( ! add_option( $key, $now, '', false ) ) {
			return false;
		}
	}
	flxcb_flight_track( (int) $conv_id, false );
	return true;
}

/** Kilidi bırakır (finally ve kapanış yedeği). */
function flxcb_flight_release( $conv_id ) {
	$conv_id = (int) $conv_id;
	if ( $conv_id <= 0 ) {
		return;
	}
	delete_option( flxcb_flight_key( $conv_id ) );
	flxcb_flight_track( $conv_id, true );
}

/** Kapanışta bırakılmamış kilitleri siler. $drop true ise bu sohbeti listeden çıkarır. */
function flxcb_flight_track( $conv_id, $drop ) {
	static $ids    = array();
	static $hooked = false;
	$conv_id       = (int) $conv_id;
	if ( $drop ) {
		unset( $ids[ $conv_id ] );
		return;
	}
	$ids[ $conv_id ] = true;
	if ( $hooked ) {
		return;
	}
	$hooked = true;
	register_shutdown_function(
		static function () use ( &$ids ) {
			foreach ( array_keys( $ids ) as $id ) {
				delete_option( 'flxcb_fly_' . (int) $id );
			}
		}
	);
}

/**
 * @return int
 */
function flxcb_now_ms() {
	return (int) floor( microtime( true ) * 1000 );
}

function flxcb_json( $v ) {
	return wp_json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

function flxcb_decode( $s ) {
	$v = json_decode( (string) $s, true );
	return is_array( $v ) ? $v : array();
}

/** Yükleme klasörü (doğrudan erişime kapalı). */
function flxcb_upload_dir() {
	$u   = wp_upload_dir( null, false );
	$dir = trailingslashit( $u['basedir'] ) . 'flx-chatbot';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		@file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
	}
	return $dir;
}

/** İzin verilen dosya türleri: mime => tür. */
function flxcb_allowed_mimes() {
	return (array) apply_filters(
		'flxcb_allowed_mimes',
		array(
			'image/jpeg'      => 'image',
			'image/png'       => 'image',
			'image/gif'       => 'image',
			'image/webp'      => 'image',
			'application/pdf' => 'file',
			'text/plain'      => 'file',
			'application/msword' => 'file',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'file',
			'application/vnd.ms-excel' => 'file',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'file',
		)
	);
}
