<?php
/**
 * Ayarlar, varsayılan metinler, yetki, simgeler ve küçük yardımcılar.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

/** Sayfa türleri (görünürlük). */
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

/** Ayarlar (varsayılanlarla birleştirilmiş). */
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

function flxcb_update_settings( array $values ) {
	$s = array_merge( flxcb_settings(), $values );
	update_option( 'flxcb_settings', $s, false );
	flxcb_settings( true );
	return $s;
}

/** Boş bırakılan metin alanlarının çevrilmiş varsayılanları. */
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

function flxcb_admin_url( $tab = '' ) {
	return admin_url( 'admin.php?page=flx-chatbot' . ( $tab ? '&tab=' . rawurlencode( $tab ) : '' ) );
}

/** Sohbetlerin yönetildiği yer: tek gelen kutusunda Flux Chat, değilse eklentinin gelen kutusu. */
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

/** Asistanın adı. */
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

/** Görünen ad (temsilci). */
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
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 20 );
}

/**
 * Basit sayaç sınırı. true = sınır içinde (sayaç artırıldı).
 *
 * @param string $key    Anahtar.
 * @param int    $max    En fazla.
 * @param int    $window Saniye.
 */
function flxcb_rate( $key, $max, $window ) {
	if ( $max <= 0 ) {
		return true;
	}
	$t   = 'flxcb_rl_' . md5( $key . '|' . $window );
	$cur = get_transient( $t );
	$cur = is_array( $cur ) ? $cur : array( 'n' => 0, 'until' => time() + $window );
	if ( $cur['until'] < time() ) {
		$cur = array( 'n' => 0, 'until' => time() + $window );
	}
	if ( $cur['n'] >= $max ) {
		return false;
	}
	++$cur['n'];
	set_transient( $t, $cur, max( 1, $cur['until'] - time() ) );
	return true;
}

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

/** Satır içi SVG simgeleri (Lucide çizgileri). */
function flxcb_icon( $name, $size = 16 ) {
	$p = array(
		'chat'     => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
		'bot'      => '<path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2M20 14h2M15 13v2M9 13v2"/>',
		'sparkles' => '<path d="M9.94 14.06 4 20M14 4l1.5 3.5L19 9l-3.5 1.5L14 14l-1.5-3.5L9 9l3.5-1.5Z"/><path d="M5 3v4M3 5h4"/>',
		'headset'  => '<path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Zm0 0a9 9 0 1 1 18 0m0 0v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3Z"/><path d="M21 16v2a4 4 0 0 1-4 4h-5"/>',
		'palette'  => '<circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.9 0 1.7-.7 1.7-1.7 0-.4-.2-.8-.4-1.1-.3-.3-.4-.7-.4-1.1a1.6 1.6 0 0 1 1.6-1.7h2c3 0 5.5-2.5 5.5-5.5C22 6 17.5 2 12 2Z"/>',
		'home'     => '<path d="M3 10 12 3l9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/>',
		'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
		'shield'   => '<path d="M20 13c0 5-3.5 7.5-7.7 9a1 1 0 0 1-.6 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.2-2.7a1.2 1.2 0 0 1 1.6 0C14.5 3.8 17 5 19 5a1 1 0 0 1 1 1Z"/>',
		'settings' => '<path d="M20 7h-9M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>',
		'inbox'    => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/>',
		'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
		'star'     => '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01Z"/>',
		'cart'     => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
		'clock'    => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		'check'    => '<path d="M20 6 9 17l-5-5"/>',
		'alert'    => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/>',
		'info'     => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
		'ext'      => '<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		'send'     => '<path d="M14.54 21.69a.5.5 0 0 0 .94-.03l6.5-19a.5.5 0 0 0-.64-.64l-19 6.5a.5.5 0 0 0-.03.94l7.93 3.18a2 2 0 0 1 1.11 1.11Z"/><path d="m21.85 2.15-10.94 10.94"/>',
		'trash'    => '<path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
		'text'     => '<path d="M17 6.1H3M21 12.1H3M15.1 18H3"/>',
		'book'     => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>',
		'image'    => '<rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/>',
		'x'        => '<path d="M18 6 6 18M6 6l12 12"/>',
	);
	$inner = $p[ $name ] ?? $p['info'];
	return '<svg class="flxcb-i" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}
