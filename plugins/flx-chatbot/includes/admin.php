<?php
/**
 * Yönetim: Chatbot ayarları (sekmeler + canlı önizleme) ve kaydetme.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Alan tanımları: anahtar => array( tür, sekme, ek ).
 * Türler: bool, int, text, textarea, enum, color, media, hours.
 */
function flxcb_fields() {
	$f = array(
		'enabled'         => array( 'bool', 'general' ),
		'mode'            => array( 'enum', 'general', array( 'hybrid', 'ai', 'live' ) ),
		'bot_name'        => array( 'text', 'look' ),
		'avatar_id'       => array( 'media', 'look' ),
		'avatar_icon'     => array( 'enum', 'look', array( 'bot', 'sparkles', 'headset', 'smile', 'logo' ) ),
		'color'           => array( 'color', 'look' ),
		'color_text'      => array( 'color', 'look' ),
		'theme'           => array( 'enum', 'look', array( 'auto', 'light', 'dark' ) ),
		'position'        => array( 'enum', 'look', array( 'right', 'left' ) ),
		'offset_x'        => array( 'int', 'look', array( 0, 200 ) ),
		'offset_y'        => array( 'int', 'look', array( 0, 300 ) ),
		'launcher_icon'   => array( 'enum', 'look', array( 'chat', 'sparkles', 'headset', 'help', 'avatar' ) ),
		'launcher_label'  => array( 'text', 'look' ),
		'launcher_size'   => array( 'int', 'look', array( 44, 80 ) ),
		'radius'          => array( 'int', 'look', array( 0, 28 ) ),
		'width'           => array( 'int', 'look', array( 320, 480 ) ),
		'header_style'    => array( 'enum', 'look', array( 'gradient', 'solid' ) ),
		'font'            => array( 'enum', 'look', array( 'inherit', 'system' ) ),
		'mobile_full'     => array( 'bool', 'look' ),
		'show_branding'   => array( 'bool', 'look' ),
		'sound'           => array( 'bool', 'look' ),
		'home_enabled'    => array( 'bool', 'texts' ),
		'home_title'      => array( 'text', 'texts' ),
		'home_text'       => array( 'textarea', 'texts' ),
		'welcome'         => array( 'textarea', 'texts' ),
		'placeholder'     => array( 'text', 'texts' ),
		'quick_replies'   => array( 'textarea', 'texts' ),
		'links'           => array( 'textarea', 'texts' ),
		'show_team'       => array( 'bool', 'texts' ),
		'teaser_enabled'  => array( 'bool', 'texts' ),
		'teaser_text'     => array( 'text', 'texts' ),
		'teaser_delay'    => array( 'int', 'texts', array( 0, 300 ) ),
		'ai_persona'      => array( 'textarea', 'ai' ),
		'ai_instructions' => array( 'textarea', 'ai' ),
		'ai_knowledge'    => array( 'textarea', 'ai' ),
		'ai_src_pages'    => array( 'bool', 'ai' ),
		'ai_src_posts'    => array( 'bool', 'ai' ),
		'ai_src_products' => array( 'bool', 'ai' ),
		'ai_src_wiki'     => array( 'bool', 'ai' ),
		'ai_tool_orders'  => array( 'bool', 'ai' ),
		'ai_tool_lead'    => array( 'bool', 'ai' ),
		'ai_cards'        => array( 'bool', 'ai' ),
		'ai_max_turns'    => array( 'int', 'ai', array( 1, 200 ) ),
		'ai_hour_limit'   => array( 'int', 'ai', array( 0, 500 ) ),
		'ai_daily_limit'  => array( 'int', 'ai', array( 0, 100000 ) ),
		'handoff_to'      => array( 'enum', 'live', array( 'auto', 'flx-chat', 'inbox' ) ),
		'hours_mode'      => array( 'enum', 'live', array( 'always', 'custom', 'flx-chat' ) ),
		'hours'           => array( 'hours', 'live' ),
		'ask_email'       => array( 'enum', 'live', array( 'offline', 'always', 'never' ) ),
		'notify_email'    => array( 'text', 'live' ),
		'email_replies'   => array( 'bool', 'live' ),
		'rating'          => array( 'bool', 'live' ),
		'uploads'         => array( 'bool', 'live' ),
		'upload_mb'       => array( 'int', 'live', array( 1, 20 ) ),
		'offline_text'    => array( 'textarea', 'live' ),
		'audience'        => array( 'enum', 'visibility', array( 'all', 'guests', 'members' ) ),
		'device_desktop'  => array( 'bool', 'visibility' ),
		'device_mobile'   => array( 'bool', 'visibility' ),
		'exclude_urls'    => array( 'textarea', 'visibility' ),
		'retention_days'  => array( 'int', 'visibility', array( 0, 3650 ) ),
		'privacy_text'    => array( 'text', 'visibility' ),
	);
	foreach ( array_keys( flxcb_page_types() ) as $p ) {
		$f[ 'show_on_' . $p ] = array( 'bool', 'visibility' );
	}
	return $f;
}

/** Formdan gelen değerleri temizler (yalnız formda olan alanlar). */
function flxcb_sanitize( array $in ) {
	$out = array();
	foreach ( flxcb_fields() as $k => $def ) {
		if ( ! array_key_exists( $k, $in ) ) {
			continue;
		}
		$v = $in[ $k ];
		switch ( $def[0] ) {
			case 'bool':
				$out[ $k ] = ! empty( $v ) && '0' !== $v ? 1 : 0;
				break;
			case 'int':
			case 'media':
				$n         = (int) $v;
				$out[ $k ] = isset( $def[2] ) ? max( $def[2][0], min( $def[2][1], $n ) ) : max( 0, $n );
				break;
			case 'enum':
				$out[ $k ] = in_array( (string) $v, $def[2], true ) ? (string) $v : $def[2][0];
				break;
			case 'color':
				$c         = sanitize_hex_color( (string) $v );
				$out[ $k ] = $c ? $c : '';
				break;
			case 'textarea':
				$out[ $k ] = sanitize_textarea_field( (string) $v );
				break;
			case 'hours':
				$h = array();
				for ( $i = 1; $i <= 7; $i++ ) {
					$d       = is_array( $v[ $i ] ?? null ) ? $v[ $i ] : array();
					$from    = preg_match( '/^\d{2}:\d{2}$/', (string) ( $d['from'] ?? '' ) ) ? $d['from'] : '09:00';
					$to      = preg_match( '/^\d{2}:\d{2}$/', (string) ( $d['to'] ?? '' ) ) ? $d['to'] : '18:00';
					$h[ $i ] = array( 'on' => ! empty( $d['on'] ) ? 1 : 0, 'from' => $from, 'to' => $to );
				}
				$out[ $k ] = $h;
				break;
			default:
				$out[ $k ] = sanitize_text_field( (string) $v );
		}
	}
	if ( isset( $out['privacy_text'] ) ) {
		$out['privacy_text'] = wp_kses( wp_unslash( (string) ( $in['privacy_text'] ?? '' ) ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) );
	}
	if ( isset( $out['notify_email'] ) && '' !== $out['notify_email'] && ! is_email( $out['notify_email'] ) ) {
		$out['notify_email'] = '';
	}
	return $out;
}

/* Ön ayar ve AI için ayar şeması. */
add_filter(
	'flx_settings_schema',
	function ( $s ) {
		$map    = array( 'bool' => 'bool', 'int' => 'int', 'text' => 'string', 'textarea' => 'text', 'enum' => 'enum', 'color' => 'color', 'media' => 'int', 'hours' => 'object' );
		$d      = flxcb_defaults();
		$fields = array();
		foreach ( flxcb_fields() as $k => $def ) {
			$fields[ $k ] = array(
				'type'    => $map[ $def[0] ],
				'default' => $d[ $k ] ?? '',
			);
			if ( 'enum' === $def[0] ) {
				$fields[ $k ]['enum'] = $def[2];
			}
		}
		$fields['color']['desc']        = 'Widget main color (hex). Empty follows the theme primary color.';
		$fields['mode']['desc']         = 'hybrid = AI first, then human handoff; ai = AI only; live = human agents only.';
		$fields['ai_knowledge']['desc'] = 'Free-form knowledge base the assistant may answer from.';
		$fields['quick_replies']['desc'] = 'One suggested question per line.';
		$fields['links']['desc']        = 'Home screen contact links, one per line: Label | URL (https, mailto, tel, wa.me).';
		$s['flxcb_settings']            = array(
			'label'  => 'Flux AI Chatbot',
			'fields' => $fields,
		);
		return $s;
	}
);

final class FLXCB_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 58 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_logo' ) );
		add_action( 'admin_post_flxcb_save', array( __CLASS__, 'save' ) );
	}

	public static function menu() {
		$open  = flxcb_stats_open_count();
		$label = __( 'Chatbot', 'flx-chatbot' ) . ( $open ? ' <span class="awaiting-mod">' . (int) $open . '</span>' : '' );
		add_menu_page( __( 'Flux AI Chatbot', 'flx-chatbot' ), $label, 'flxcb_reply', 'flx-chatbot-inbox', array( 'FLXCB_Inbox', 'render' ), 'none', 27 );
		add_submenu_page( 'flx-chatbot-inbox', __( 'Site sohbetleri', 'flx-chatbot' ), __( 'Sohbetler', 'flx-chatbot' ), 'flxcb_reply', 'flx-chatbot-inbox', array( 'FLXCB_Inbox', 'render' ) );
		add_submenu_page( 'flx-chatbot-inbox', __( 'Chatbot ayarları', 'flx-chatbot' ), __( 'Ayarlar', 'flx-chatbot' ), 'manage_options', 'flx-chatbot', array( __CLASS__, 'render' ) );
	}

	public static function menu_logo() {
		echo '<style>#toplevel_page_flx-chatbot-inbox .wp-menu-image{background:url(' . esc_url( FLXCB_URL . 'assets/icon.svg?ver=' . FLXCB_VERSION ) . ') center/20px 20px no-repeat}</style>';
	}

	private static function page() {
		return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function assets() {
		$page = self::page();
		if ( 'flx-chatbot' === $page ) {
			wp_enqueue_media();
			wp_enqueue_style( 'flxcb-admin', FLXCB_URL . 'assets/admin.css', array(), FLXCB_VERSION );
			wp_enqueue_script( 'flxcb-admin', FLXCB_URL . 'assets/admin.js', array(), FLXCB_VERSION, true );
			$cfg            = flxcb_widget_config();
			$cfg['preview'] = true;
			$cfg['enabled'] = (bool) flxcb_setting( 'enabled' );
			$cfg['demo']    = self::demo();
			wp_add_inline_script( 'flxcb-admin', 'window.flxcbConfig=' . wp_json_encode( $cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . ';', 'before' );
			$defaults = array();
			foreach ( array( 'bot_name', 'home_title', 'home_text', 'welcome', 'placeholder', 'teaser_text', 'offline_text' ) as $k ) {
				$defaults[ $k ] = flxcb_default_text( $k );
			}
			wp_localize_script(
				'flxcb-admin',
				'flxcbAdmin',
				array(
					'defaults' => $defaults,
					'theme'    => '',
					'i18n'     => array(
						'pick'    => __( 'Avatar seç', 'flx-chatbot' ),
						'use'     => __( 'Bu görseli kullan', 'flx-chatbot' ),
						'unsaved' => __( 'Kaydedilmemiş değişiklikler var', 'flx-chatbot' ),
					),
				)
			);
			wp_enqueue_script( 'flxcb-widget', FLXCB_URL . 'assets/widget.js', array( 'flxcb-admin' ), FLXCB_VERSION, true );
		} elseif ( 'flx-chatbot-inbox' === $page ) {
			wp_enqueue_style( 'flxcb-admin', FLXCB_URL . 'assets/admin.css', array(), FLXCB_VERSION );
			wp_enqueue_script( 'flxcb-inbox', FLXCB_URL . 'assets/inbox.js', array(), FLXCB_VERSION, true );
			wp_localize_script(
				'flxcb-inbox',
				'flxcbInbox',
				array(
					'rest'  => esc_url_raw( rest_url( 'flx-chatbot/v1/agent/' ) ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
					'conv'  => isset( $_GET['conv'] ) ? absint( $_GET['conv'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification
					'admin' => current_user_can( 'manage_options' ),
					'me'    => flxcb_user_name( get_current_user_id() ),
					'i18n'  => FLXCB_Inbox::strings(),
				)
			);
		}
	}

	/** Önizlemedeki örnek konuşma (gerçek sohbet yokken). Mağaza varsa son ürünle kart gösterilir. */
	private static function demo() {
		$now  = flxcb_now_ms();
		$card = array();
		if ( function_exists( 'wc_get_products' ) ) {
			$ids = wc_get_products( array( 'status' => 'publish', 'limit' => 1, 'orderby' => 'date' ) );
			$p   = $ids ? flxcb_product_info( $ids[0] ) : null;
			if ( $p ) {
				$card = array( flxcb_product_card( $p ) );
			}
		}
		return array(
			array( 'id' => -1, 'role' => 'visitor', 'body' => __( 'Merhaba, kargo ne kadar sürüyor?', 'flx-chatbot' ), 'ts' => $now - 60000, 'read' => 1 ),
			array(
				'id'    => -2,
				'role'  => 'bot',
				'body'  => $card ? __( 'Siparişler **1-3 iş günü** içinde kargoya verilir. İlginizi çekebilecek bir ürün:', 'flx-chatbot' ) : __( 'Siparişler **1-3 iş günü** içinde kargoya verilir; 500 TL üzeri kargo ücretsiz. Başka bir konuda yardımcı olabilir miyim?', 'flx-chatbot' ),
				'ts'    => $now - 55000,
				'cards' => $card,
			),
		);
	}

	/* ---------------- Sayfa ---------------- */

	private static function field_id( $k ) {
		return 'flxcb-f-' . str_replace( '_', '-', $k );
	}

	private static function sw( $k, $label, $help = '', $disabled = false ) {
		$s = flxcb_settings();
		echo '<label class="flxcb-toggle' . ( $disabled ? ' is-off' : '' ) . '"><span class="flxcb-toggle__t"><strong>' . esc_html( $label ) . '</strong>' . ( $help ? '<small>' . esc_html( $help ) . '</small>' : '' ) . '</span>';
		echo '<span class="flxcb-switch"><input type="hidden" name="s[' . esc_attr( $k ) . ']" value="0"><input type="checkbox" name="s[' . esc_attr( $k ) . ']" value="1" data-k="' . esc_attr( $k ) . '"' . checked( ! empty( $s[ $k ] ), true, false ) . disabled( $disabled, true, false ) . '><span class="flxcb-switch__ui" aria-hidden="true"></span></span></label>';
	}

	private static function text( $k, $label, $help = '', $ph = null, $type = 'text', $attrs = '' ) {
		$s  = flxcb_settings();
		$ph = null === $ph ? flxcb_default_text( $k ) : $ph;
		echo '<label class="flxcb-field" for="' . esc_attr( self::field_id( $k ) ) . '"><span>' . esc_html( $label ) . '</span><input id="' . esc_attr( self::field_id( $k ) ) . '" type="' . esc_attr( $type ) . '" name="s[' . esc_attr( $k ) . ']" value="' . esc_attr( (string) $s[ $k ] ) . '" placeholder="' . esc_attr( $ph ) . '" data-k="' . esc_attr( $k ) . '" ' . $attrs . '>' . ( $help ? '<small>' . esc_html( $help ) . '</small>' : '' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private static function area( $k, $label, $help = '', $rows = 3, $ph = null ) {
		$s  = flxcb_settings();
		$ph = null === $ph ? flxcb_default_text( $k ) : $ph;
		echo '<label class="flxcb-field" for="' . esc_attr( self::field_id( $k ) ) . '"><span>' . esc_html( $label ) . '</span><textarea id="' . esc_attr( self::field_id( $k ) ) . '" name="s[' . esc_attr( $k ) . ']" rows="' . (int) $rows . '" placeholder="' . esc_attr( $ph ) . '" data-k="' . esc_attr( $k ) . '">' . esc_textarea( (string) $s[ $k ] ) . '</textarea>' . ( $help ? '<small>' . esc_html( $help ) . '</small>' : '' ) . '</label>';
	}

	private static function select( $k, $label, array $opts, $help = '' ) {
		$s = flxcb_settings();
		echo '<label class="flxcb-field" for="' . esc_attr( self::field_id( $k ) ) . '"><span>' . esc_html( $label ) . '</span><select id="' . esc_attr( self::field_id( $k ) ) . '" name="s[' . esc_attr( $k ) . ']" data-k="' . esc_attr( $k ) . '">';
		foreach ( $opts as $v => $l ) {
			echo '<option value="' . esc_attr( $v ) . '"' . selected( (string) $s[ $k ], (string) $v, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select>' . ( $help ? '<small>' . esc_html( $help ) . '</small>' : '' ) . '</label>';
	}

	private static function range( $k, $label, $min, $max, $unit = 'px' ) {
		$s = flxcb_settings();
		echo '<label class="flxcb-field" for="' . esc_attr( self::field_id( $k ) ) . '"><span>' . esc_html( $label ) . ' <output>' . (int) $s[ $k ] . esc_html( $unit ) . '</output></span><input id="' . esc_attr( self::field_id( $k ) ) . '" type="range" min="' . (int) $min . '" max="' . (int) $max . '" name="s[' . esc_attr( $k ) . ']" value="' . (int) $s[ $k ] . '" data-k="' . esc_attr( $k ) . '" data-unit="' . esc_attr( $unit ) . '"></label>';
	}

	/** Seçim kartları (radyo). */
	private static function choices( $k, array $opts, $cols = 3 ) {
		$s = flxcb_settings();
		echo '<div class="flxcb-choices" style="--cols:' . (int) $cols . '">';
		foreach ( $opts as $v => $o ) {
			echo '<label class="flxcb-choice"><input type="radio" name="s[' . esc_attr( $k ) . ']" value="' . esc_attr( $v ) . '" data-k="' . esc_attr( $k ) . '"' . checked( (string) $s[ $k ], (string) $v, false ) . '>' . ( ! empty( $o[2] ) ? '<span class="flxcb-choice__i">' . flxcb_icon( $o[2], 18 ) . '</span>' : '' ) . '<span><strong>' . esc_html( $o[0] ) . '</strong>' . ( ! empty( $o[1] ) ? '<small>' . esc_html( $o[1] ) . '</small>' : '' ) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
	}

	/** Simge seçici. */
	private static function icons( $k, array $opts ) {
		$s = flxcb_settings();
		echo '<div class="flxcb-icons">';
		foreach ( $opts as $v => $o ) {
			echo '<label class="flxcb-iconpick" title="' . esc_attr( $o[0] ) . '"><input type="radio" name="s[' . esc_attr( $k ) . ']" value="' . esc_attr( $v ) . '" data-k="' . esc_attr( $k ) . '"' . checked( (string) $s[ $k ], (string) $v, false ) . '><span>' . flxcb_icon( $o[1], 20 ) . '</span><small>' . esc_html( $o[0] ) . '</small></label>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
	}

	private static function card_open( $id, $icon, $title, $desc = '' ) {
		echo '<section class="flxcb-card" id="' . esc_attr( $id ) . '"><div class="flxcb-card__head"><h2>' . flxcb_icon( $icon, 16 ) . esc_html( $title ) . '</h2></div>' . ( $desc ? '<p class="flxcb-muted">' . esc_html( $desc ) . '</p>' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private static function kpi( $icon, $label, $value, $hint = '', $cls = '' ) {
		echo '<div class="flxcb-kpi ' . esc_attr( $cls ) . '"><span class="flxcb-kpi__icon">' . flxcb_icon( $icon, 18 ) . '</span><span class="flxcb-kpi__body"><span class="flxcb-kpi__label">' . esc_html( $label ) . '</span><span class="flxcb-kpi__value">' . esc_html( $value ) . '</span>' . ( $hint ? '<span class="flxcb-kpi__hint">' . esc_html( $hint ) . '</span>' : '' ) . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/** Durum satırı. */
	private static function status_row( $ok, $title, $text, $link = '', $link_label = '' ) {
		echo '<li class="flxcb-status ' . ( true === $ok ? 'is-ok' : ( null === $ok ? 'is-info' : 'is-warn' ) ) . '"><span class="flxcb-status__i">' . flxcb_icon( true === $ok ? 'check' : ( null === $ok ? 'info' : 'alert' ), 14 ) . '</span><span><strong>' . esc_html( $title ) . '</strong><small>' . esc_html( $text ) . '</small></span>' . ( $link ? '<a class="button button-small" href="' . esc_url( $link ) . '">' . esc_html( $link_label ) . '</a>' : '' ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-chatbot' ), 403 );
		}
		$s     = flxcb_settings();
		$st    = flxcb_stats( 30 );
		$tabs  = array(
			'general'    => array( __( 'Genel', 'flx-chatbot' ), 'settings' ),
			'look'       => array( __( 'Görünüm', 'flx-chatbot' ), 'palette' ),
			'texts'      => array( __( 'Metinler', 'flx-chatbot' ), 'text' ),
			'ai'         => array( __( 'Yapay zekâ', 'flx-chatbot' ), 'sparkles' ),
			'live'       => array( __( 'Canlı destek', 'flx-chatbot' ), 'headset' ),
			'visibility' => array( __( 'Görünürlük', 'flx-chatbot' ), 'eye' ),
		);
		$tab   = isset( $_GET['tab'] ) && isset( $tabs[ sanitize_key( wp_unslash( $_GET['tab'] ) ) ] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		$ai_ok = flxcb_ai_ready();
		$fc    = flxcb_fc_ready();

		echo '<div class="wrap flxcb">';
		echo '<div class="flxcb-head"><div><h1>' . esc_html__( 'Flux AI Chatbot', 'flx-chatbot' ) . '</h1><p class="flxcb-lead">' . esc_html__( 'Sitenize yapay zekâ destekli sohbet penceresi: sorulara içeriğinizden ve ürünlerinizden cevap verir, gerekince sohbeti canlı temsilciye devreder. Değişiklikleri sağdaki önizlemede hemen görürsünüz.', 'flx-chatbot' ) . '</p></div>';
		echo '<div class="flxcb-head__actions"><a class="button" href="' . esc_url( flxcb_inbox_url() ) . '">' . flxcb_icon( 'inbox', 14 ) . esc_html__( 'Sohbetler', 'flx-chatbot' ) . '</a><a class="button" href="' . esc_url( add_query_arg( 'flxcb_preview', '1', home_url( '/' ) ) ) . '" target="_blank" rel="noopener">' . flxcb_icon( 'ext', 14 ) . esc_html__( 'Sitede dene', 'flx-chatbot' ) . '</a></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput

		if ( isset( $_GET['msg'] ) && 'saved' === $_GET['msg'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="flxcb-notice is-ok">' . flxcb_icon( 'check', 16 ) . esc_html__( 'Ayarlar kaydedildi.', 'flx-chatbot' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}

		echo '<div class="flxcb-kpis">';
		self::kpi( 'chat', __( 'Sohbet', 'flx-chatbot' ), number_format_i18n( $st['total'] ), __( 'son 30 gün', 'flx-chatbot' ) );
		self::kpi( 'sparkles', __( 'Asistanın çözdüğü', 'flx-chatbot' ), $st['total'] ? '%' . number_format_i18n( $st['resolved'] ) : '–', __( 'temsilciye geçmeden', 'flx-chatbot' ), 'is-ok' );
		self::kpi( 'users', __( 'Potansiyel müşteri', 'flx-chatbot' ), number_format_i18n( $st['leads'] ), __( 'iletişim bilgisi bıraktı', 'flx-chatbot' ) );
		if ( function_exists( 'wc_get_orders' ) ) {
			self::kpi( 'cart', __( 'Sohbet sonrası sipariş', 'flx-chatbot' ), number_format_i18n( $st['orders'] ), __( '7 gün içinde', 'flx-chatbot' ) );
		}
		/* translators: %s: number of ratings */
		self::kpi( 'star', __( 'Memnuniyet', 'flx-chatbot' ), $st['rated'] ? number_format_i18n( $st['rating'], 1 ) . ' / 5' : '–', $st['rated'] ? sprintf( __( '%s puan', 'flx-chatbot' ), number_format_i18n( $st['rated'] ) ) : __( 'henüz puan yok', 'flx-chatbot' ) );
		echo '</div>';

		echo '<div class="flxcb-layout"><div class="flxcb-main">';
		echo '<nav class="flxcb-tabs" role="tablist">';
		foreach ( $tabs as $k => $t ) {
			echo '<a href="' . esc_url( flxcb_admin_url( $k ) ) . '" class="flxcb-tab' . ( $k === $tab ? ' is-on' : '' ) . '" data-tab="' . esc_attr( $k ) . '" role="tab">' . flxcb_icon( $t[1], 15 ) . '<span>' . esc_html( $t[0] ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</nav>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxcb-form" data-flxcb-form>';
		wp_nonce_field( 'flxcb_save' );
		echo '<input type="hidden" name="action" value="flxcb_save"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" data-tab-input>';

		/* ---------- Genel ---------- */
		echo '<div class="flxcb-pane" data-pane="general"' . ( 'general' === $tab ? '' : ' hidden' ) . '>';
		self::card_open( 'flxcb-on', 'chat', __( 'Sohbet penceresi', 'flx-chatbot' ) );
		self::sw( 'enabled', __( 'Sitede göster', 'flx-chatbot' ), __( 'Kapalıyken pencere ziyaretçilere görünmez; yöneticiler "Sitede dene" ile önizleyebilir.', 'flx-chatbot' ) );
		echo '<h3 class="flxcb-sub">' . esc_html__( 'Çalışma şekli', 'flx-chatbot' ) . '</h3>';
		self::choices(
			'mode',
			array(
				'hybrid' => array( __( 'AI + canlı destek', 'flx-chatbot' ), __( 'Asistan cevaplar, gerekince temsilciye devreder. Önerilen.', 'flx-chatbot' ), 'sparkles' ),
				'ai'     => array( __( 'Yalnız AI', 'flx-chatbot' ), __( 'Temsilciye devir yok; asistan iletişim bilgilerinizi verir.', 'flx-chatbot' ), 'bot' ),
				'live'   => array( __( 'Yalnız canlı destek', 'flx-chatbot' ), __( 'Asistan yok; mesajlar doğrudan temsilcilere gider.', 'flx-chatbot' ), 'headset' ),
			)
		);
		echo '</section>';

		self::card_open( 'flxcb-health', 'shield', __( 'Bağlantılar', 'flx-chatbot' ) );
		echo '<ul class="flxcb-statuses">';
		if ( $ai_ok ) {
			self::status_row( true, __( 'Flux AI & MCP bağlı', 'flx-chatbot' ), __( 'Anahtar, model, harcama sınırı ve kayıtlar Flux AI\'dan gelir.', 'flx-chatbot' ), flx_ai_settings_url( 'flx-chatbot' ), __( 'Model seç', 'flx-chatbot' ) );
		} elseif ( function_exists( 'flxai_chat' ) ) {
			self::status_row( false, __( 'Flux AI hazır değil', 'flx-chatbot' ), __( 'Flux AI & MCP\'de bir API anahtarı girin ya da Bağlı sistemler\'de Flux AI Chatbot\'u açın.', 'flx-chatbot' ), admin_url( 'admin.php?page=flx-ai' ), __( 'Flux AI', 'flx-chatbot' ) );
		} else {
			self::status_row( false, __( 'Flux AI & MCP kurulu değil', 'flx-chatbot' ), __( 'Asistan için Flux AI & MCP eklentisini kurun. O zamana kadar pencere yalnız canlı destek olarak çalışır.', 'flx-chatbot' ) );
		}
		if ( 'ai' === $s['mode'] ) {
			self::status_row( null, __( 'Canlı destek kapalı', 'flx-chatbot' ), __( 'Çalışma şekli "Yalnız AI".', 'flx-chatbot' ) );
		} elseif ( 'flx-chat' === flxcb_handoff_target() ) {
			self::status_row( true, __( 'Flux Chat gelen kutusu', 'flx-chatbot' ), __( 'Devredilen sohbetler çağrı merkezinde "Site sohbeti" olarak açılır; temsilciler WhatsApp sohbetleriyle aynı ekrandan cevaplar.', 'flx-chatbot' ), admin_url( 'admin.php?page=flx-chat' ), __( 'Flux Chat', 'flx-chatbot' ) );
		} else {
			self::status_row( true, __( 'Eklentinin gelen kutusu', 'flx-chatbot' ), $fc || flxcb_flux_chat_active() ? __( 'Devredilen sohbetler Chatbot › Sohbetler ekranına düşer.', 'flx-chatbot' ) : __( 'Devredilen sohbetler Chatbot › Sohbetler ekranına düşer. Flux Chat (1.37+) kurulursa çağrı merkezinden de cevaplanır.', 'flx-chatbot' ), flxcb_inbox_url(), __( 'Sohbetler', 'flx-chatbot' ) );
		}
		/* translators: %s: hours text */
		self::status_row( flxcb_is_online() ? true : null, flxcb_is_online() ? __( 'Şu an çevrim içi', 'flx-chatbot' ) : __( 'Şu an mesai dışı', 'flx-chatbot' ), 'custom' === $s['hours_mode'] ? sprintf( __( 'Çalışma saatleri: %s', 'flx-chatbot' ), flxcb_hours_text() ) : ( 'flx-chat' === $s['hours_mode'] ? __( 'Mesai saatleri Flux Chat ayarından.', 'flx-chatbot' ) : __( 'Temsilciler her zaman çevrim içi sayılır.', 'flx-chatbot' ) ), flxcb_admin_url( 'live' ), __( 'Saatler', 'flx-chatbot' ) );
		echo '</ul></section>';

		self::card_open( 'flxcb-embed', 'ext', __( 'Sayfadan açma', 'flx-chatbot' ), __( 'Herhangi bir düğme ya da bağlantı pencereyi açabilir.', 'flx-chatbot' ) );
		echo '<ul class="flxcb-code"><li><code>[flx_chatbot label="' . esc_html__( 'Bize yazın', 'flx-chatbot' ) . '"]</code><small>' . esc_html__( 'Kısa kod: sayfa içinde düğme.', 'flx-chatbot' ) . '</small></li><li><code>&lt;a href="#flxcb-open"&gt;…&lt;/a&gt;</code><small>' . esc_html__( 'Bağlantı adresi #flxcb-open olunca pencere açılır.', 'flx-chatbot' ) . '</small></li><li><code>data-flxcb-message="…"</code><small>' . esc_html__( 'Düğmeye eklenirse pencere bu mesajla açılır.', 'flx-chatbot' ) . '</small></li><li><code>FluxChatbot.open()</code><small>' . esc_html__( 'JavaScript ile açma.', 'flx-chatbot' ) . '</small></li></ul></section>';
		echo '</div>';

		/* ---------- Görünüm ---------- */
		echo '<div class="flxcb-pane" data-pane="look"' . ( 'look' === $tab ? '' : ' hidden' ) . '>';
		self::card_open( 'flxcb-brand', 'palette', __( 'Renk ve tema', 'flx-chatbot' ) );
		echo '<div class="flxcb-grid2">';
		echo '<div class="flxcb-field"><span>' . esc_html__( 'Ana renk', 'flx-chatbot' ) . '</span><div class="flxcb-color"><input type="color" value="' . esc_attr( $s['color'] ? $s['color'] : '#1f2937' ) . '" data-color-for="color"><input type="text" name="s[color]" value="' . esc_attr( $s['color'] ) . '" placeholder="' . esc_attr__( 'Tema rengi', 'flx-chatbot' ) . '" data-k="color" class="code" maxlength="7"><button type="button" class="button-link" data-clear="color">' . esc_html__( 'Temayı izle', 'flx-chatbot' ) . '</button></div><small>' . esc_html__( 'Boşsa sitenin tema rengi kullanılır.', 'flx-chatbot' ) . '</small></div>';
		echo '<div class="flxcb-field"><span>' . esc_html__( 'Yazı rengi (başlık ve düğmeler)', 'flx-chatbot' ) . '</span><div class="flxcb-color"><input type="color" value="' . esc_attr( $s['color_text'] ? $s['color_text'] : '#ffffff' ) . '" data-color-for="color_text"><input type="text" name="s[color_text]" value="' . esc_attr( $s['color_text'] ) . '" placeholder="' . esc_attr__( 'Otomatik', 'flx-chatbot' ) . '" data-k="color_text" class="code" maxlength="7"><button type="button" class="button-link" data-clear="color_text">' . esc_html__( 'Otomatik', 'flx-chatbot' ) . '</button></div><small>' . esc_html__( 'Boşsa ana renge göre okunaklı renk seçilir.', 'flx-chatbot' ) . '</small></div>';
		echo '</div>';
		echo '<div class="flxcb-swatches" data-swatches>';
		foreach ( array( '#1f2937', '#2563eb', '#0f766e', '#16a34a', '#7c3aed', '#db2777', '#ea580c', '#0891b2' ) as $c ) {
			echo '<button type="button" style="--sw:' . esc_attr( $c ) . '" data-swatch="' . esc_attr( $c ) . '" aria-label="' . esc_attr( $c ) . '"></button>';
		}
		echo '</div>';
		echo '<div class="flxcb-grid3">';
		self::select( 'theme', __( 'Açık / koyu', 'flx-chatbot' ), array( 'auto' => __( 'Otomatik (cihaza ve siteye uyar)', 'flx-chatbot' ), 'light' => __( 'Hep açık', 'flx-chatbot' ), 'dark' => __( 'Hep koyu', 'flx-chatbot' ) ) );
		self::select( 'header_style', __( 'Başlık', 'flx-chatbot' ), array( 'gradient' => __( 'Degrade', 'flx-chatbot' ), 'solid' => __( 'Düz renk', 'flx-chatbot' ) ) );
		self::select( 'font', __( 'Yazı tipi', 'flx-chatbot' ), array( 'inherit' => __( 'Siteyle aynı', 'flx-chatbot' ), 'system' => __( 'Sistem yazı tipi', 'flx-chatbot' ) ) );
		echo '</div></section>';

		self::card_open( 'flxcb-avatar', 'bot', __( 'Asistan', 'flx-chatbot' ) );
		echo '<div class="flxcb-grid2">';
		self::text( 'bot_name', __( 'Asistanın adı', 'flx-chatbot' ), __( 'Sohbet başlığında görünür.', 'flx-chatbot' ) );
		$aurl = (int) $s['avatar_id'] ? wp_get_attachment_image_url( (int) $s['avatar_id'], 'thumbnail' ) : '';
		echo '<div class="flxcb-field"><span>' . esc_html__( 'Avatar görseli', 'flx-chatbot' ) . '</span><div class="flxcb-media"><span class="flxcb-media__img" data-avatar-img>' . ( $aurl ? '<img src="' . esc_url( $aurl ) . '" alt="">' : flxcb_icon( 'image', 18 ) ) . '</span><input type="hidden" name="s[avatar_id]" value="' . (int) $s['avatar_id'] . '" data-k="avatar_id" data-url="' . esc_url( (string) $aurl ) . '"><button type="button" class="button" data-media>' . esc_html__( 'Seç', 'flx-chatbot' ) . '</button><button type="button" class="button-link" data-media-clear' . ( $aurl ? '' : ' hidden' ) . '>' . esc_html__( 'Kaldır', 'flx-chatbot' ) . '</button></div><small>' . esc_html__( 'Kare görsel önerilir. Seçilmezse aşağıdaki simge kullanılır.', 'flx-chatbot' ) . '</small></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
		echo '<div class="flxcb-field"><span>' . esc_html__( 'Avatar simgesi', 'flx-chatbot' ) . '</span>';
		self::icons(
			'avatar_icon',
			array(
				'bot'      => array( __( 'Robot', 'flx-chatbot' ), 'bot' ),
				'sparkles' => array( __( 'Pırıltı', 'flx-chatbot' ), 'sparkles' ),
				'headset'  => array( __( 'Kulaklık', 'flx-chatbot' ), 'headset' ),
				'smile'    => array( __( 'Gülen yüz', 'flx-chatbot' ), 'info' ),
				'logo'     => array( __( 'Site simgesi', 'flx-chatbot' ), 'home' ),
			)
		);
		echo '</div></section>';

		self::card_open( 'flxcb-launch', 'chat', __( 'Başlatıcı düğme ve konum', 'flx-chatbot' ) );
		echo '<div class="flxcb-field"><span>' . esc_html__( 'Düğme simgesi', 'flx-chatbot' ) . '</span>';
		self::icons(
			'launcher_icon',
			array(
				'chat'     => array( __( 'Balon', 'flx-chatbot' ), 'chat' ),
				'sparkles' => array( __( 'Pırıltı', 'flx-chatbot' ), 'sparkles' ),
				'headset'  => array( __( 'Kulaklık', 'flx-chatbot' ), 'headset' ),
				'help'     => array( __( 'Soru', 'flx-chatbot' ), 'info' ),
				'avatar'   => array( __( 'Avatar', 'flx-chatbot' ), 'image' ),
			)
		);
		echo '</div><div class="flxcb-grid2">';
		self::text( 'launcher_label', __( 'Düğme yanı etiketi', 'flx-chatbot' ), __( 'Boşsa yalnız düğme görünür. Örnek: Yardım lazım mı?', 'flx-chatbot' ), __( 'Yok', 'flx-chatbot' ) );
		self::select( 'position', __( 'Konum', 'flx-chatbot' ), array( 'right' => __( 'Sağ alt', 'flx-chatbot' ), 'left' => __( 'Sol alt', 'flx-chatbot' ) ) );
		echo '</div><div class="flxcb-grid3">';
		self::range( 'launcher_size', __( 'Düğme boyutu', 'flx-chatbot' ), 44, 80 );
		self::range( 'offset_x', __( 'Kenar boşluğu', 'flx-chatbot' ), 0, 120 );
		self::range( 'offset_y', __( 'Alt boşluk', 'flx-chatbot' ), 0, 200 );
		self::range( 'width', __( 'Pencere genişliği', 'flx-chatbot' ), 320, 480 );
		self::range( 'radius', __( 'Köşe yuvarlaklığı', 'flx-chatbot' ), 0, 28 );
		echo '</div><div class="flxcb-toggles">';
		self::sw( 'mobile_full', __( 'Mobilde tam ekran', 'flx-chatbot' ), __( 'Küçük ekranlarda pencere tüm ekranı kaplar.', 'flx-chatbot' ) );
		self::sw( 'sound', __( 'Bildirim sesi', 'flx-chatbot' ), __( 'Pencere kapalıyken yeni cevap gelince kısa ses.', 'flx-chatbot' ) );
		self::sw( 'show_branding', __( '"FluxWP ile çalışır" yazısı', 'flx-chatbot' ), __( 'Pencerenin altında küçük imza.', 'flx-chatbot' ) );
		echo '</div></section>';
		echo '</div>';

		/* ---------- Ana ekran ve metinler ---------- */
		echo '<div class="flxcb-pane" data-pane="texts"' . ( 'texts' === $tab ? '' : ' hidden' ) . '>';
		self::card_open( 'flxcb-home', 'home', __( 'Ana ekran', 'flx-chatbot' ), __( 'Pencere açılınca ilk görünen karşılama ekranı. Boş bırakılan metinler dile göre (TR/EN) varsayılanı kullanır.', 'flx-chatbot' ) );
		self::sw( 'home_enabled', __( 'Ana ekranı göster', 'flx-chatbot' ), __( 'Kapalıysa pencere doğrudan sohbetle açılır.', 'flx-chatbot' ) );
		echo '<div class="flxcb-grid2">';
		self::text( 'home_title', __( 'Başlık', 'flx-chatbot' ) );
		self::text( 'placeholder', __( 'Yazma alanı', 'flx-chatbot' ) );
		echo '</div>';
		self::area( 'home_text', __( 'Alt metin', 'flx-chatbot' ), '', 2 );
		self::sw( 'show_team', __( 'Ekip avatarları', 'flx-chatbot' ), __( 'Son cevap veren temsilcilerin fotoğrafları başlıkta görünür.', 'flx-chatbot' ) );
		echo '<div class="flxcb-grid2">';
		self::area( 'quick_replies', __( 'Hazır sorular', 'flx-chatbot' ), __( 'Satır başına bir soru (en fazla 6). Ana ekranda ve sohbet başında tıklanabilir.', 'flx-chatbot' ), 5, __( "Kargo ne kadar sürer?\nİade nasıl yapılır?\nSiparişim nerede?", 'flx-chatbot' ) );
		self::area( 'links', __( 'İletişim bağlantıları', 'flx-chatbot' ), __( 'Satır başına: Etiket | adres. https, mailto:, tel: ve wa.me desteklenir.', 'flx-chatbot' ), 5, "WhatsApp | https://wa.me/905xxxxxxxxx\n" . __( 'E-posta', 'flx-chatbot' ) . " | mailto:info@example.com\n" . __( 'Telefon', 'flx-chatbot' ) . ' | tel:+90212xxxxxxx' );
		echo '</div></section>';

		self::card_open( 'flxcb-msgs', 'chat', __( 'Sohbet metinleri', 'flx-chatbot' ) );
		self::area( 'welcome', __( 'Karşılama mesajı', 'flx-chatbot' ), __( 'Asistanın ilk mesajı.', 'flx-chatbot' ), 2 );
		echo '</section>';

		self::card_open( 'flxcb-teaser', 'sparkles', __( 'Tanıtım balonu', 'flx-chatbot' ), __( 'Ziyaretçi bir süre sayfada kalınca düğmenin üstünde kısa bir mesaj belirir (oturum başına bir kez).', 'flx-chatbot' ) );
		self::sw( 'teaser_enabled', __( 'Tanıtım balonunu göster', 'flx-chatbot' ) );
		echo '<div class="flxcb-grid2">';
		self::text( 'teaser_text', __( 'Mesaj', 'flx-chatbot' ) );
		self::text( 'teaser_delay', __( 'Kaç saniye sonra', 'flx-chatbot' ), '', '12', 'number', 'min="0" max="300"' );
		echo '</div></section>';
		echo '</div>';

		/* ---------- Yapay zekâ ---------- */
		echo '<div class="flxcb-pane" data-pane="ai"' . ( 'ai' === $tab ? '' : ' hidden' ) . '>';
		if ( ! $ai_ok ) {
			echo '<div class="flxcb-callout is-warn">' . flxcb_icon( 'alert', 18 ) . '<div><strong>' . esc_html__( 'Asistan şu an çalışmıyor.', 'flx-chatbot' ) . '</strong> ' . esc_html__( 'Yapay zekâ çağrıları Flux AI & MCP üzerinden yapılır; anahtar, model ve harcama sınırı orada. Flux AI hazır olana kadar ziyaretçi mesajları temsilciye gider.', 'flx-chatbot' ) . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo '<div class="flxcb-callout is-info">' . flxcb_icon( 'info', 18 ) . '<div>' . esc_html__( 'Anahtar, model, harcama sınırı ve kayıtlar Flux AI & MCP\'de. Bu eklentiye özel model seçmek için:', 'flx-chatbot' ) . ' <a href="' . esc_url( flx_ai_settings_url( 'flx-chatbot' ) ) . '">' . esc_html__( 'Flux AI › Bağlı sistemler › Flux AI Chatbot', 'flx-chatbot' ) . '</a></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		self::card_open( 'flxcb-brain', 'sparkles', __( 'Kişilik ve talimat', 'flx-chatbot' ) );
		self::area( 'ai_persona', __( 'Kişilik ve üslup', 'flx-chatbot' ), __( 'Örnek: Samimi ama kibar konuş, "siz" diye hitap et, kısa yaz.', 'flx-chatbot' ), 2, '' );
		self::area( 'ai_instructions', __( 'Kurallar', 'flx-chatbot' ), __( 'Asistanın uyması gerekenler. Örnek: İndirim kodu verme. Kurumsal satış sorularını temsilciye devret.', 'flx-chatbot' ), 4, '' );
		self::area( 'ai_knowledge', __( 'Bilgi bankası', 'flx-chatbot' ), __( 'Sitede yazmayan bilgiler: kargo, iade, çalışma saatleri, sık sorulan sorular. Düz metin; asistan yalnız buradan ve sitenizden cevap verir.', 'flx-chatbot' ), 9, __( "Kargo: 1-3 iş günü, 500 TL üzeri ücretsiz.\nİade: 14 gün içinde, faturayla.\nS: Mağazanız var mı?\nC: Yalnız online satış yapıyoruz.", 'flx-chatbot' ) );
		echo '</section>';
		self::card_open( 'flxcb-sources', 'book', __( 'Kaynaklar ve araçlar', 'flx-chatbot' ), __( 'Asistan cevap vermeden önce bunları arar. Yalnız yayımlanmış, şifresiz içerik okunur.', 'flx-chatbot' ) );
		echo '<div class="flxcb-toggles">';
		self::sw( 'ai_src_pages', __( 'Sayfalar', 'flx-chatbot' ) );
		self::sw( 'ai_src_posts', __( 'Yazılar', 'flx-chatbot' ) );
		self::sw( 'ai_src_products', __( 'WooCommerce ürünleri', 'flx-chatbot' ), __( 'Fiyat, stok ve bağlantı; ürünler pencerede kart olarak görünür.', 'flx-chatbot' ), ! function_exists( 'wc_get_product' ) );
		self::sw( 'ai_cards', __( 'Ürün kartları', 'flx-chatbot' ), __( 'Görsel, fiyat, "İncele" ve "Sepete ekle".', 'flx-chatbot' ), ! function_exists( 'wc_get_product' ) );
		self::sw( 'ai_src_wiki', __( 'Flux Wiki sayfaları', 'flx-chatbot' ), '', ! defined( 'FLXWK_PT' ) );
		self::sw( 'ai_tool_orders', __( 'Sipariş durumu', 'flx-chatbot' ), __( 'Giriş yapmış müşteri yalnız kendi siparişlerini sorabilir.', 'flx-chatbot' ), ! function_exists( 'wc_get_orders' ) );
		self::sw( 'ai_tool_lead', __( 'İletişim bilgisi kaydı', 'flx-chatbot' ), __( 'Ziyaretçi e-posta ya da telefon verirse sohbete (ve varsa Flux CRM\'e) yazılır.', 'flx-chatbot' ) );
		echo '</div></section>';
		self::card_open( 'flxcb-limits', 'shield', __( 'Sınırlar', 'flx-chatbot' ), __( 'Herkese açık AI bütçeyi hızla tüketebilir. Genel harcama sınırı Flux AI\'da; bunlar ek koruma.', 'flx-chatbot' ) );
		echo '<div class="flxcb-grid3">';
		self::text( 'ai_max_turns', __( 'Sohbet başına cevap', 'flx-chatbot' ), __( 'Dolunca temsilciye devredilir.', 'flx-chatbot' ), '40', 'number', 'min="1" max="200"' );
		self::text( 'ai_hour_limit', __( 'Sohbet başına saatlik', 'flx-chatbot' ), __( '0 = sınırsız', 'flx-chatbot' ), '30', 'number', 'min="0" max="500"' );
		self::text( 'ai_daily_limit', __( 'Sitede günlük toplam', 'flx-chatbot' ), __( '0 = sınırsız', 'flx-chatbot' ), '400', 'number', 'min="0"' );
		echo '</div></section>';
		echo '</div>';

		/* ---------- Canlı destek ---------- */
		echo '<div class="flxcb-pane" data-pane="live"' . ( 'live' === $tab ? '' : ' hidden' ) . '>';
		self::card_open( 'flxcb-target', 'headset', __( 'Temsilciye devir', 'flx-chatbot' ) );
		self::choices(
			'handoff_to',
			array(
				'auto'     => array( __( 'Otomatik', 'flx-chatbot' ), __( 'Flux Chat varsa orası, yoksa eklentinin gelen kutusu.', 'flx-chatbot' ), 'sparkles' ),
				'flx-chat' => array( __( 'Flux Chat', 'flx-chatbot' ), $fc ? __( 'Çağrı merkezi gelen kutusu; WhatsApp sohbetleriyle birlikte.', 'flx-chatbot' ) : __( 'Flux Chat 1.37+ kurulu değil; gelen kutusu kullanılır.', 'flx-chatbot' ), 'chat' ),
				'inbox'    => array( __( 'Eklentinin gelen kutusu', 'flx-chatbot' ), __( 'Chatbot › Sohbetler ekranı; e-posta bildirimiyle.', 'flx-chatbot' ), 'inbox' ),
			)
		);
		echo '<div class="flxcb-grid2">';
		self::select( 'ask_email', __( 'E-posta sor', 'flx-chatbot' ), array( 'offline' => __( 'Yalnız mesai dışında', 'flx-chatbot' ), 'always' => __( 'Temsilciye geçmeden önce', 'flx-chatbot' ), 'never' => __( 'Hiç sorma', 'flx-chatbot' ) ) );
		self::text( 'notify_email', __( 'Bildirim e-postası', 'flx-chatbot' ), __( 'Yeni devir ve mesai dışı mesajlar. Boşsa site yöneticisi.', 'flx-chatbot' ), (string) get_option( 'admin_email' ), 'email' );
		echo '</div>';
		self::area( 'offline_text', __( 'Mesai dışı mesajı', 'flx-chatbot' ), '', 2 );
		echo '<div class="flxcb-toggles">';
		self::sw( 'email_replies', __( 'Cevapları e-postayla da gönder', 'flx-chatbot' ), __( 'Ziyaretçi sayfadan ayrıldıysa temsilci cevabı e-postasına gider.', 'flx-chatbot' ) );
		self::sw( 'rating', __( 'Sohbet sonu puanlama', 'flx-chatbot' ), __( '1-5 yıldız ve isteğe bağlı not.', 'flx-chatbot' ) );
		self::sw( 'uploads', __( 'Dosya gönderimi', 'flx-chatbot' ), __( 'Resim, PDF ve ofis belgeleri; dosyalar herkese açık klasöre konmaz.', 'flx-chatbot' ) );
		echo '</div><div class="flxcb-grid3">';
		self::text( 'upload_mb', __( 'En büyük dosya (MB)', 'flx-chatbot' ), '', '5', 'number', 'min="1" max="20"' );
		echo '</div></section>';

		self::card_open( 'flxcb-hours', 'clock', __( 'Çalışma saatleri', 'flx-chatbot' ), __( 'Mesai dışında ziyaretçiye "mesaj bırakın" gösterilir, temsilci e-postayla döner. Saatler site saat dilimine göredir.', 'flx-chatbot' ) );
		self::choices(
			'hours_mode',
			array(
				'always'   => array( __( 'Her zaman açık', 'flx-chatbot' ), '', 'check' ),
				'custom'   => array( __( 'Haftalık saatler', 'flx-chatbot' ), '', 'clock' ),
				'flx-chat' => array( __( 'Flux Chat mesaisi', 'flx-chatbot' ), flxcb_flux_chat_active() ? '' : __( 'Flux Chat kurulu değil', 'flx-chatbot' ), 'chat' ),
			)
		);
		echo '<table class="flxcb-hours" data-hours' . ( 'custom' === $s['hours_mode'] ? '' : ' hidden' ) . '><tbody>';
		foreach ( flxcb_day_names() as $d => $name ) {
			$h = $s['hours'][ $d ] ?? array( 'on' => 0, 'from' => '09:00', 'to' => '18:00' );
			echo '<tr><th scope="row"><label><input type="hidden" name="s[hours][' . (int) $d . '][on]" value="0"><input type="checkbox" name="s[hours][' . (int) $d . '][on]" value="1"' . checked( ! empty( $h['on'] ), true, false ) . '> ' . esc_html( $name ) . '</label></th><td><input type="time" name="s[hours][' . (int) $d . '][from]" value="' . esc_attr( $h['from'] ) . '"> – <input type="time" name="s[hours][' . (int) $d . '][to]" value="' . esc_attr( $h['to'] ) . '"></td></tr>';
		}
		echo '</tbody></table></section>';
		echo '</div>';

		/* ---------- Görünürlük ve gizlilik ---------- */
		echo '<div class="flxcb-pane" data-pane="visibility"' . ( 'visibility' === $tab ? '' : ' hidden' ) . '>';
		self::card_open( 'flxcb-where', 'eye', __( 'Nerede görünsün', 'flx-chatbot' ) );
		echo '<div class="flxcb-checks">';
		foreach ( flxcb_page_types() as $k => $l ) {
			echo '<label class="flxcb-check"><input type="hidden" name="s[show_on_' . esc_attr( $k ) . ']" value="0"><input type="checkbox" name="s[show_on_' . esc_attr( $k ) . ']" value="1"' . checked( ! empty( $s[ 'show_on_' . $k ] ), true, false ) . '> ' . esc_html( $l ) . '</label>';
		}
		echo '</div><div class="flxcb-grid2">';
		self::select( 'audience', __( 'Kimlere', 'flx-chatbot' ), array( 'all' => __( 'Herkese', 'flx-chatbot' ), 'guests' => __( 'Yalnız ziyaretçilere (giriş yapmamış)', 'flx-chatbot' ), 'members' => __( 'Yalnız üyelere (giriş yapmış)', 'flx-chatbot' ) ) );
		echo '<div class="flxcb-field"><span>' . esc_html__( 'Cihaz', 'flx-chatbot' ) . '</span><div class="flxcb-checks">';
		foreach ( array( 'device_desktop' => __( 'Masaüstü', 'flx-chatbot' ), 'device_mobile' => __( 'Mobil', 'flx-chatbot' ) ) as $k => $l ) {
			echo '<label class="flxcb-check"><input type="hidden" name="s[' . esc_attr( $k ) . ']" value="0"><input type="checkbox" name="s[' . esc_attr( $k ) . ']" value="1"' . checked( ! empty( $s[ $k ] ), true, false ) . '> ' . esc_html( $l ) . '</label>';
		}
		echo '</div></div></div>';
		self::area( 'exclude_urls', __( 'Gösterilmeyecek adresler', 'flx-chatbot' ), __( 'Satır başına bir yol. * joker karakterdir. Örnek: /odeme/* ya da /kampanya', 'flx-chatbot' ), 3, "/checkout/*\n/landing" );
		echo '</section>';
		self::card_open( 'flxcb-privacy', 'shield', __( 'Gizlilik', 'flx-chatbot' ), __( 'Ziyaretçinin IP adresi saklanmaz; sohbet tarayıcıdaki rastgele bir anahtarla eşleşir.', 'flx-chatbot' ) );
		echo '<div class="flxcb-grid2">';
		self::text( 'privacy_text', __( 'Yazma alanı altındaki not', 'flx-chatbot' ), __( 'Örnek: Sohbet kayıtları <a href="/gizlilik">Gizlilik Politikası</a> kapsamında saklanır. Bağlantı yazılabilir.', 'flx-chatbot' ), '' );
		self::text( 'retention_days', __( 'Saklama süresi (gün)', 'flx-chatbot' ), __( 'Süresi dolan sohbetler ve dosyaları silinir. 0 = silme.', 'flx-chatbot' ), '180', 'number', 'min="0"' );
		echo '</div></section>';
		echo '</div>';

		echo '<div class="flxcb-savebar"><span class="flxcb-dirty" data-dirty hidden>' . esc_html__( 'Kaydedilmemiş değişiklikler var', 'flx-chatbot' ) . '</span><button type="submit" class="button button-primary button-large">' . esc_html__( 'Kaydet', 'flx-chatbot' ) . '</button></div>';
		echo '</form></div>';

		/* Önizleme */
		echo '<aside class="flxcb-side"><div class="flxcb-preview"><div class="flxcb-preview__bar"><strong>' . esc_html__( 'Önizleme', 'flx-chatbot' ) . '</strong><span class="flxcb-seg" role="group"><button type="button" class="is-on" data-pv="home">' . esc_html__( 'Ana ekran', 'flx-chatbot' ) . '</button><button type="button" data-pv="chat">' . esc_html__( 'Sohbet', 'flx-chatbot' ) . '</button><button type="button" data-pv="teaser">' . esc_html__( 'Balon', 'flx-chatbot' ) . '</button><button type="button" data-pv="closed">' . esc_html__( 'Kapalı', 'flx-chatbot' ) . '</button></span></div>';
		echo '<div class="flxcb-stage" data-flxcb-stage><div class="flxcb-fake" aria-hidden="true"><i></i><i></i><i class="s"></i><b></b><b></b><b class="s"></b></div></div>';
		echo '<p class="flxcb-muted flxcb-preview__note">' . esc_html__( 'Önizlemede yazdığınız mesajlar gerçek asistana gider ve Sohbetler\'de görünür.', 'flx-chatbot' ) . '</p></div></aside>';
		echo '</div></div>';
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'flx-chatbot' ), 403 );
		}
		check_admin_referer( 'flxcb_save' );
		$in  = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'general';
		flxcb_update_settings( flxcb_sanitize( $in ) );
		delete_transient( 'flxcb_team' );
		do_action( 'flxcb_settings_saved' );
		wp_safe_redirect( add_query_arg( 'msg', 'saved', flxcb_admin_url( $tab ) ) );
		exit;
	}
}

/** Açık (temsilci bekleyen / görüşülen) sohbet sayısı, menü rozeti için 60 sn önbellekli. */
function flxcb_stats_open_count() {
	$n = get_transient( 'flxcb_open_count' );
	if ( false === $n ) {
		global $wpdb;
		$n = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . flxcb_table( 'conversations' ) . " WHERE status IN ('queued','offline') OR (status = 'agent' AND unread > 0)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		set_transient( 'flxcb_open_count', $n, MINUTE_IN_SECONDS );
	}
	return (int) $n;
}

add_action( 'plugins_loaded', array( 'FLXCB_Admin', 'init' ), 15 );
