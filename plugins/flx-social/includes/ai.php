<?php
/** Flux AI üzerinden yazım yardımı (kendi AI bağlantımız yok). */

defined( 'ABSPATH' ) || exit;

function flxsm_ai_ready() {
	return function_exists( 'flxai_complete' ) && function_exists( 'flxai_ready' ) && flxai_ready( 'flx-social' );
}

/** Ağ kuralları: AI'ye verilen kısa bilgi. */
function flxsm_ai_network_hint( $provider ) {
	$h = array(
		'facebook'  => 'Facebook page post: friendly, 1-3 short paragraphs, link allowed, 1-3 hashtags at most.',
		'instagram' => 'Instagram caption: hook in first line, line breaks, emojis allowed, 5-15 relevant hashtags at the end, no clickable links (say "link in bio").',
		'youtube'   => 'YouTube video description: first 2 lines summarize, then details, links and 3-5 hashtags.',
		'x'         => 'X post: max 280 characters total, punchy, 1-2 hashtags.',
		'linkedin'  => 'LinkedIn post: professional, first line hook, short paragraphs, 3-5 hashtags at the end.',
		'tiktok'    => 'TikTok caption: short, energetic, 3-6 hashtags.',
		'pinterest' => 'Pinterest pin description: keyword rich, 1-2 sentences, max 500 characters.',
		'gbp'       => 'Google Business Profile update: informative, local, max 1500 characters, no hashtags.',
		'whatsapp'  => 'WhatsApp message: warm, short, emojis allowed, no hashtags.',
	);
	return $h[ $provider ] ?? '';
}

/**
 * $mode: write (fikirden yaz), improve (düzelt/güzelleştir), shorten, hashtags, adapt (ağlara göre ayrı metin), translate.
 * $args: text, brief, providers[], tone, lang, limit.
 * Dönüş: write/improve/shorten/hashtags/translate → string; adapt → [provider => text].
 */
function flxsm_ai_write( $mode, array $args ) {
	if ( ! flxsm_ai_ready() ) {
		return new WP_Error( 'flxsm_ai', __( 'Flux AI & MCP is not installed or no key has been entered.', 'flx-social' ), array( 'status' => 400 ) );
	}
	$org   = get_bloginfo( 'name' );
	$lang  = ! empty( $args['lang'] ) ? (string) $args['lang'] : ( 0 === strpos( get_locale(), 'tr' ) ? 'Turkish' : 'English' );
	$tone  = ! empty( $args['tone'] ) ? (string) $args['tone'] : 'sincere, hopeful, clear';
	$sys   = "You write social media posts for \"{$org}\" (" . home_url( '/' ) . "), a non-profit tree-planting / environmental organization. Write in {$lang}. Tone: {$tone}. Never invent facts, prices, dates or statistics that are not in the input. Output only the post text, no quotes, no explanations.";
	$text  = (string) ( $args['text'] ?? '' );
	$brief = (string) ( $args['brief'] ?? '' );
	$prov  = (array) ( $args['providers'] ?? array() );
	$hint  = implode( "\n", array_filter( array_map( 'flxsm_ai_network_hint', $prov ) ) );
	$opts  = array(
		'source'     => 'flx-social',
		'purpose'    => 'social_' . $mode,
		'max_tokens' => 1500,
	);
	switch ( $mode ) {
		case 'write':
			$prompt = "Write a social media post from this brief:\n{$brief}\n" . ( $text ? "\nExisting draft to build on:\n{$text}\n" : '' ) . ( $hint ? "\nTarget networks:\n{$hint}\nWrite one version that works on all of them (respect the shortest limit only if X is included)." : '' );
			break;
		case 'improve':
			$prompt = "Improve this post: fix spelling and grammar, make it clearer and more engaging, keep the meaning, links and facts exactly.\n\n{$text}";
			break;
		case 'shorten':
			$lim    = max( 60, (int) ( $args['limit'] ?? 280 ) );
			$prompt = "Shorten this post to at most {$lim} characters including spaces, keep links and the key message.\n\n{$text}";
			break;
		case 'hashtags':
			$prompt = "Suggest 8 relevant hashtags for this post (mix of Turkish and general environmental ones if the post is Turkish). Output only the hashtags separated by spaces.\n\n{$text}";
			break;
		case 'translate':
			$to     = ! empty( $args['to'] ) ? (string) $args['to'] : 'English';
			$prompt = "Translate this post to {$to}. Keep links, emojis and hashtags (translate hashtags only if natural).\n\n{$text}";
			break;
		case 'adapt':
			$keys   = array_values( array_intersect( $prov, array_keys( flxsm_providers() ) ) );
			$lines  = array();
			foreach ( $keys as $k ) {
				$lines[] = "- {$k}: " . flxsm_ai_network_hint( $k );
			}
			$prompt = "Rewrite the post below separately for each network, following each network's style:\n" . implode( "\n", $lines ) . "\n\nReturn ONLY a JSON object whose keys are exactly: " . implode( ', ', $keys ) . " and values are the post texts.\n\nPost:\n{$text}";
			break;
		default:
			return new WP_Error( 'flxsm_ai', 'mode', array( 'status' => 400 ) );
	}
	$opts['system'] = $sys;
	$out            = flxai_complete( $prompt, $opts );
	if ( is_wp_error( $out ) ) {
		return $out;
	}
	$out = trim( (string) $out );
	if ( 'adapt' === $mode ) {
		if ( preg_match( '/\{.*\}/s', $out, $m ) ) {
			$j = json_decode( $m[0], true );
			if ( is_array( $j ) ) {
				return array_map( fn( $v ) => trim( (string) $v ), array_intersect_key( $j, array_flip( $prov ) ) );
			}
		}
		return new WP_Error( 'flxsm_ai', __( 'Could not read the AI response, please try again.', 'flx-social' ), array( 'status' => 502 ) );
	}
	return trim( $out, "\"“” \n" );
}
