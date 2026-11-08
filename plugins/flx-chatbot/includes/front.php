<?php
/**
 * Ön yüz: görünürlük, sohbet penceresi dosyaları ve ayarları, [flx_chatbot] kısa kodu.
 *
 * @package FLX_Chatbot
 */

defined( 'ABSPATH' ) || exit;

/** Geçerli sayfanın türü (görünürlük ayarları için). */
function flxcb_current_page_type() {
	if ( function_exists( 'is_cart' ) && is_cart() ) {
		return 'cart';
	}
	if ( function_exists( 'is_checkout' ) && is_checkout() ) {
		return 'checkout';
	}
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		return 'account';
	}
	if ( ( function_exists( 'is_shop' ) && is_shop() ) || ( function_exists( 'is_product' ) && is_product() ) || ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) ) {
		return 'shop';
	}
	if ( is_front_page() ) {
		return 'home';
	}
	if ( is_singular( 'post' ) ) {
		return 'posts';
	}
	if ( is_page() ) {
		return 'pages';
	}
	return 'other';
}

/** Adres hariç listesinde mi ("*" joker, satır başına bir yol ya da adres). */
function flxcb_url_excluded( $path ) {
	foreach ( flxcb_lines( 'exclude_urls' ) as $rule ) {
		$rule = preg_replace( '#^https?://[^/]+#i', '', $rule );
		if ( '' === $rule ) {
			continue;
		}
		$re = '#^' . str_replace( '\*', '.*', preg_quote( $rule, '#' ) ) . '$#i';
		if ( preg_match( $re, $path ) || preg_match( $re, untrailingslashit( $path ) ) || preg_match( $re, trailingslashit( $path ) ) ) {
			return true;
		}
	}
	return false;
}

/** Bu sayfada sohbet penceresi görünür mü. */
function flxcb_should_show() {
	if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return false;
	}
	$preview = isset( $_GET['flxcb_preview'] ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! flxcb_setting( 'enabled' ) && ! $preview ) {
		return false;
	}
	$aud = (string) flxcb_setting( 'audience' );
	if ( ( 'guests' === $aud && is_user_logged_in() ) || ( 'members' === $aud && ! is_user_logged_in() ) ) {
		return (bool) apply_filters( 'flxcb_show', $preview, 'audience' );
	}
	if ( ! $preview && ! flxcb_setting( 'show_on_' . flxcb_current_page_type() ) ) {
		return (bool) apply_filters( 'flxcb_show', false, 'page_type' );
	}
	$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/', PHP_URL_PATH );
	if ( ! $preview && flxcb_url_excluded( '' !== $path ? $path : '/' ) ) {
		return (bool) apply_filters( 'flxcb_show', false, 'url' );
	}
	return (bool) apply_filters( 'flxcb_show', true, '' );
}

/** Pencereye giden metinler. */
function flxcb_js_strings() {
	return array(
		'open'        => __( 'Open chat', 'flx-chatbot' ),
		'close'       => __( 'Close', 'flx-chatbot' ),
		'minimize'    => __( 'Minimize', 'flx-chatbot' ),
		'back'        => __( 'Back', 'flx-chatbot' ),
		'home'        => __( 'Home', 'flx-chatbot' ),
		'messages'    => __( 'Messages', 'flx-chatbot' ),
		'send'        => __( 'Send', 'flx-chatbot' ),
		'attach'      => __( 'Attach file', 'flx-chatbot' ),
		'dictate'     => __( 'Dictate', 'flx-chatbot' ),
		'dictateStop' => __( 'Stop listening', 'flx-chatbot' ),
		'startChat'   => __( 'Message us', 'flx-chatbot' ),
		'continueChat' => __( 'Continue the chat', 'flx-chatbot' ),
		'replyTime'   => __( 'We usually reply within a few minutes', 'flx-chatbot' ),
		'aiReply'     => __( 'Our assistant replies instantly', 'flx-chatbot' ),
		'online'      => __( 'Online', 'flx-chatbot' ),
		'offline'     => __( 'Offline right now', 'flx-chatbot' ),
		'typing'      => __( 'typing…', 'flx-chatbot' ),
		'thinking'    => __( 'Thinking…', 'flx-chatbot' ),
		'searching'   => __( 'Searching the site…', 'flx-chatbot' ),
		'products'    => __( 'Looking at products…', 'flx-chatbot' ),
		'orders'      => __( 'Looking at your orders…', 'flx-chatbot' ),
		'human'       => __( 'Talk to a person', 'flx-chatbot' ),
		'humanAsk'    => __( 'Would you like to talk to a person?', 'flx-chatbot' ),
		/* translators: %d: queue position */
		'queue'       => __( 'Your place in line: %d', 'flx-chatbot' ),
		'queued'      => __( 'Waiting for an agent', 'flx-chatbot' ),
		'agentJoined' => __( 'Agent', 'flx-chatbot' ),
		'seen'        => __( 'Seen', 'flx-chatbot' ),
		'sent'        => __( 'Sent', 'flx-chatbot' ),
		'you'         => __( 'You', 'flx-chatbot' ),
		'end'         => __( 'End chat', 'flx-chatbot' ),
		'endAsk'      => __( 'End the chat?', 'flx-chatbot' ),
		'yes'         => __( 'Yes', 'flx-chatbot' ),
		'no'          => __( 'No', 'flx-chatbot' ),
		'newChat'     => __( 'New chat', 'flx-chatbot' ),
		'rateTitle'   => __( 'How was the chat?', 'flx-chatbot' ),
		'rateNote'    => __( 'Anything you\'d like to add? (optional)', 'flx-chatbot' ),
		'rateThanks'  => __( 'Thanks, we got your feedback.', 'flx-chatbot' ),
		'rateSend'    => __( 'Send', 'flx-chatbot' ),
		'name'        => __( 'Your name', 'flx-chatbot' ),
		'email'       => __( 'Your email', 'flx-chatbot' ),
		'phone'       => __( 'Phone (optional)', 'flx-chatbot' ),
		'message'     => __( 'Your message', 'flx-chatbot' ),
		'leaveMsg'    => __( 'Leave a message', 'flx-chatbot' ),
		'contactIntro' => __( 'Leave your email so we can get back to you.', 'flx-chatbot' ),
		'contactSend' => __( 'Send', 'flx-chatbot' ),
		'badEmail'    => __( 'Enter a valid email address.', 'flx-chatbot' ),
		'error'       => __( 'Connection problem. Please try again.', 'flx-chatbot' ),
		'retry'       => __( 'Try again', 'flx-chatbot' ),
		'tooBig'      => __( 'The file is too large.', 'flx-chatbot' ),
		'addToCart'   => __( 'Add to cart', 'flx-chatbot' ),
		'view'        => __( 'View', 'flx-chatbot' ),
		'outOfStock'  => __( 'Out of stock', 'flx-chatbot' ),
		'poweredBy'   => __( 'Powered by FluxWP', 'flx-chatbot' ),
		'today'       => __( 'Today', 'flx-chatbot' ),
		'unread'      => __( 'new message', 'flx-chatbot' ),
		'askAnything' => __( 'Ask a question', 'flx-chatbot' ),
		'contactUs'   => __( 'Contact us', 'flx-chatbot' ),
		'hours'       => __( 'Working hours', 'flx-chatbot' ),
	);
}

/** Pencere ayarları (ön yüz ve yönetim önizlemesi aynı nesneyi kullanır). */
function flxcb_widget_config() {
	$s      = flxcb_settings();
	$color  = sanitize_hex_color( (string) $s['color'] );
	$tcolor = sanitize_hex_color( (string) $s['color_text'] );
	$user   = null;
	if ( is_user_logged_in() ) {
		$u    = wp_get_current_user();
		$user = array(
			'name'  => flxcb_user_name( $u->ID ),
			'email' => $u->user_email,
		);
	}
	$target = flxcb_handoff_target();
	$cfg    = array(
		'rest'       => esc_url_raw( rest_url( 'flx-chatbot/v1/' ) ),
		'css'        => FLXCB_URL . 'assets/widget.css?ver=' . FLXCB_VERSION,
		'flxTheme'   => 'fluxwp' === get_template(),
		'nonce'      => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
		'mode'       => (string) $s['mode'],
		'ai'         => 'live' !== $s['mode'] && flxcb_ai_ready(),
		'live'       => '' !== $target,
		'online'     => flxcb_is_online(),
		'hours'      => flxcb_hours_text(),
		'botName'    => flxcb_bot_name(),
		'siteName'   => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'avatar'     => flxcb_avatar_url(),
		'avatarIcon' => (string) $s['avatar_icon'],
		'launcher'   => array(
			'icon'  => (string) $s['launcher_icon'],
			'label' => trim( (string) $s['launcher_label'] ),
			'size'  => max( 44, min( 80, (int) $s['launcher_size'] ) ),
		),
		'color'      => $color ? $color : '',
		'colorText'  => $tcolor ? $tcolor : ( $color ? flxcb_contrast( $color ) : '' ),
		'theme'      => (string) $s['theme'],
		'position'   => 'left' === $s['position'] ? 'left' : 'right',
		'offsetX'    => max( 0, min( 200, (int) $s['offset_x'] ) ),
		'offsetY'    => max( 0, min( 300, (int) $s['offset_y'] ) ),
		'radius'     => max( 0, min( 28, (int) $s['radius'] ) ),
		'width'      => max( 320, min( 480, (int) $s['width'] ) ),
		'header'     => 'solid' === $s['header_style'] ? 'solid' : 'gradient',
		'font'       => 'system' === $s['font'] ? 'system' : 'inherit',
		'mobileFull' => (bool) $s['mobile_full'],
		'branding'   => (bool) apply_filters( 'flxcb_show_branding', (bool) $s['show_branding'] ),
		'sound'      => (bool) $s['sound'],
		'devices'    => array(
			'desktop' => (bool) $s['device_desktop'],
			'mobile'  => (bool) $s['device_mobile'],
		),
		'home'       => array(
			'enabled' => (bool) $s['home_enabled'],
			'title'   => flxcb_text( 'home_title' ),
			'text'    => flxcb_text( 'home_text' ),
			'team'    => $s['show_team'] && '' !== $target ? flxcb_team() : array(),
			'links'   => flxcb_links(),
		),
		'quick'      => array_slice( flxcb_lines( 'quick_replies' ), 0, 6 ),
		'welcome'    => 'live' === $s['mode'] ? '' : flxcb_text( 'welcome' ),
		'placeholder' => flxcb_text( 'placeholder' ),
		'teaser'     => array(
			'enabled' => (bool) $s['teaser_enabled'],
			'text'    => flxcb_text( 'teaser_text' ),
			'delay'   => max( 0, min( 300, (int) $s['teaser_delay'] ) ),
		),
		'offlineText' => flxcb_text( 'offline_text' ),
		'askEmail'   => (string) $s['ask_email'],
		'rating'     => (bool) $s['rating'],
		'uploads'    => (bool) $s['uploads'],
		'uploadMb'   => max( 1, (int) $s['upload_mb'] ),
		'dictation'  => (bool) $s['dictation'],
		'privacy'    => wp_kses( flxcb_text( 'privacy_text' ), array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) ) ),
		'user'       => $user,
		'locale'     => determine_locale(),
		'i18n'       => flxcb_js_strings(),
	);
	return (array) apply_filters( 'flxcb_widget_config', $cfg );
}

function flxcb_enqueue_widget( $preview = false ) {
	wp_enqueue_script( 'flxcb-widget', FLXCB_URL . 'assets/widget.js', array(), FLXCB_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	$cfg            = flxcb_widget_config();
	$cfg['preview'] = (bool) $preview;
	wp_add_inline_script( 'flxcb-widget', 'window.flxcbConfig=' . wp_json_encode( $cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . ';', 'before' );
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( flxcb_should_show() ) {
			flxcb_enqueue_widget();
		}
	}
);

/*
 * [flx_chatbot] kısa kodu: sayfa içinde "Bize yazın" düğmesi (pencereyi açar).
 * Herhangi bir bağlantıya data-flxcb-open ya da #flxcb-open adresi vermek de pencereyi açar.
 */
add_shortcode(
	'flx_chatbot',
	function ( $atts ) {
		$a = shortcode_atts(
			array(
				'label'   => __( 'Message us', 'flx-chatbot' ),
				'message' => '',
			),
			$atts,
			'flx_chatbot'
		);
		if ( ! wp_script_is( 'flxcb-widget', 'enqueued' ) && flxcb_setting( 'enabled' ) ) {
			flxcb_enqueue_widget();
		}
		return '<button type="button" class="flxcb-open-btn wp-element-button" data-flxcb-open' . ( '' !== $a['message'] ? ' data-flxcb-message="' . esc_attr( $a['message'] ) . '"' : '' ) . '>' . esc_html( $a['label'] ) . '</button>';
	}
);
