<?php
/**
 * Settings fields, form cleaning and the settings schema (presets, Flux AI and Flux GraphQL read it).
 * Loaded on every request: the admin page, the GraphQL schema and presets all need it.
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
		'whatsapp_number' => array( 'text', 'texts' ),
		'whatsapp_message' => array( 'text', 'texts' ),
		'whatsapp_label'  => array( 'text', 'texts' ),
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
		'dictation'       => array( 'bool', 'live' ),
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

/**
 * Formdan gelen değerleri temizler (yalnız formda olan alanlar).
 *
 * @return array<string,mixed>
 */
function flxcb_sanitize( array $in ) {
	$out = array();
	foreach ( flxcb_fields() as $k => $def ) {
		if ( ! array_key_exists( $k, $in ) ) {
			continue;
		}
		$v = $in[ $k ];
		switch ( $def[0] ) {
			case 'bool':
				$out[ $k ] = ! empty( $v ) ? 1 : 0;
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
	if ( isset( $out['whatsapp_number'] ) ) {
		$out['whatsapp_number'] = flxcb_whatsapp_digits( $out['whatsapp_number'] );
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
		$docs = array(
			'enabled'         => 'Show the chat window on the site. Off hides it from visitors (admins can still preview).',
			'bot_name'        => 'Assistant name in the window header. Empty uses the default.',
			'avatar_id'       => 'Media library image ID of the assistant avatar (0 = none).',
			'avatar_icon'     => 'Avatar icon used when there is no image.',
			'color_text'      => 'Header and button text color (hex). Empty picks a readable color from the main color.',
			'theme'           => 'Window colors: follow device and site, or always light or dark.',
			'position'        => 'Side of the screen the button sits on.',
			'offset_x'        => 'Space from the side edge, px.',
			'offset_y'        => 'Space from the bottom edge, px.',
			'launcher_icon'   => 'Icon of the open button.',
			'launcher_label'  => 'Text next to the open button; empty shows the button only.',
			'launcher_size'   => 'Open button size, px (44-80).',
			'radius'          => 'Window corner radius, px.',
			'width'           => 'Window width, px (320-480).',
			'header_style'    => 'Window header: gradient or solid color.',
			'font'            => 'Window font: same as the site or the system font.',
			'mobile_full'     => 'Cover the whole screen on small screens.',
			'show_branding'   => 'Show the small "Powered by FluxWP" note.',
			'sound'           => 'Play a short sound for a new reply while the window is closed.',
			'home_enabled'    => 'Show the home screen when the window opens (off opens the chat directly).',
			'home_title'      => 'Home screen headline. Empty uses the default.',
			'home_text'       => 'Home screen subtitle. Empty uses the default.',
			'welcome'         => 'The assistant\'s first message. Empty uses the default.',
			'placeholder'     => 'Hint in the message field. Empty uses the default.',
			'whatsapp_number' => 'WhatsApp number with country code (digits, e.g. 905301234567). Shows a WhatsApp button under the chat card on the home screen; empty hides it.',
			'whatsapp_message' => 'Ready-made text the WhatsApp chat opens with. Empty sends nothing.',
			'whatsapp_label'  => 'Text of the WhatsApp button. Empty uses the default.',
			'show_team'       => 'Show photos of agents who replied recently on the home screen.',
			'teaser_enabled'  => 'Pop a short message above the button after the visitor stays a while.',
			'teaser_text'     => 'Message of the teaser bubble. Empty uses the default.',
			'teaser_delay'    => 'Seconds on the page before the teaser shows (0-300).',
			'ai_persona'      => 'Personality and tone of the assistant.',
			'ai_instructions' => 'Rules the assistant must follow.',
			'ai_src_pages'    => 'Let the assistant search published pages.',
			'ai_src_posts'    => 'Let the assistant search published posts.',
			'ai_src_products' => 'Let the assistant look up WooCommerce products (price, stock, link).',
			'ai_src_wiki'     => 'Let the assistant search Flux Wiki pages.',
			'ai_tool_orders'  => 'Let a signed-in customer ask about their own orders.',
			'ai_tool_lead'    => 'Save contact details a visitor gives (also to Flux CRM when installed).',
			'ai_cards'        => 'Show suggested products as cards in the window.',
			'ai_max_turns'    => 'Assistant replies per chat before it goes to an agent (1-200).',
			'ai_hour_limit'   => 'Assistant replies per chat per hour (0 = unlimited).',
			'ai_daily_limit'  => 'Assistant replies per day on the whole site (0 = unlimited).',
			'handoff_to'      => 'Where handed-over chats go: automatic, Flux Chat or the plugin inbox.',
			'hours_mode'      => 'Working hours: always open, weekly hours, or Flux Chat hours.',
			'hours'           => 'Weekly hours: { "1".."7": { on: 0|1, from: "HH:MM", to: "HH:MM" } }, 1 = Monday.',
			'ask_email'       => 'When to ask the visitor for an email address.',
			'notify_email'    => 'Address for handoff and after-hours notifications; empty uses the site admin.',
			'email_replies'   => 'Also email agent replies when the visitor has left the page.',
			'rating'          => 'Ask for 1-5 stars at the end of a chat.',
			'uploads'         => 'Let visitors send images, PDFs and office documents.',
			'upload_mb'       => 'Largest file, MB (1-20).',
			'dictation'       => 'Show a voice dictation button in the message field.',
			'offline_text'    => 'Message shown outside working hours. Empty uses the default.',
			'audience'        => 'Who sees the window: everyone, guests only, or signed-in members only.',
			'device_desktop'  => 'Show on desktop.',
			'device_mobile'   => 'Show on mobile.',
			'exclude_urls'    => 'Paths where the window is hidden, one per line; * is a wildcard.',
			'retention_days'  => 'Delete chats and files after this many days (0 = keep).',
			'privacy_text'    => 'Note under the message field; links are allowed.',
		);
		foreach ( flxcb_page_types() as $p => $label ) {
			$docs[ 'show_on_' . $p ] = 'Show the window on this page type: ' . $p . '.';
		}
		foreach ( $docs as $k => $d ) {
			if ( isset( $fields[ $k ] ) ) {
				$fields[ $k ]['desc'] = $d;
			}
		}
		$fields['color']['desc']        = 'Widget main color (hex). Empty follows the theme primary color.';
		$fields['mode']['desc']         = 'hybrid = AI first, then human handoff; ai = AI only; live = human agents only.';
		$fields['ai_knowledge']['desc'] = 'Free-form knowledge base the assistant may answer from.';
		$fields['quick_replies']['desc'] = 'One suggested question per line.';
		$fields['links']['desc']        = 'Home screen contact links, one per line: Label | URL (https, mailto, tel, wa.me).';
		$s['flxcb_settings']            = array(
			'label'   => 'Flux AI Chatbot',
			'graphql' => 'Chatbot',
			'fields' => $fields,
		);
		return $s;
	}
);
