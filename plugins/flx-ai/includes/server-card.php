<?php
/**
 * MCP Server Card: /.well-known/mcp/server-card.json (SEP-1649; SEP-2127 adı server-cards.json da verilir).
 * Ajanlar sitenin MCP sunucusunu bu kartla bulur: ad, sürüm, uç adresi, taşıma, yetenekler, kimlik doğrulama.
 * Kart araç listesi içermez (araçlar bağlanınca tools/list ile gelir). MCP kapalıysa kart yoktur (404).
 *
 * @package flx-ai
 */

defined( 'ABSPATH' ) || exit;

/** Kart yolları (site köküne göre). */
function flxai_server_card_paths() {
	$paths = array( '.well-known/mcp/server-card.json', '.well-known/mcp/server-cards.json', '.well-known/mcp.json' );
	if ( '' !== flxai_mcp_short_path() ) {
		$paths[] = flxai_mcp_short_path() . '/server-card';
	}
	return $paths;
}

/** Ters alan adı biçiminde sunucu kimliği: fluxwp.com → com.fluxwp/wordpress. */
function flxai_server_card_name() {
	$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$host = preg_replace( '/^www\./', '', $host );
	$path = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	$name = implode( '.', array_reverse( explode( '.', $host ) ) ) . '/' . ( '' !== $path ? sanitize_title( $path ) : 'wordpress' );
	return (string) apply_filters( 'flx_mcp_server_card_name', $name );
}

/** Kartın içeriği. */
function flxai_server_card() {
	$url   = flxai_mcp_url();
	$site  = wp_strip_all_tags( html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	$desc  = wp_strip_all_tags( html_entity_decode( get_bloginfo( 'description' ), ENT_QUOTES ) );
	$oauth = function_exists( 'flxai_oauth_enabled' ) && flxai_oauth_enabled();
	/* translators: %s: site name */
	$fallback = sprintf( __( 'MCP server of %s: read and manage the site content and the tools of its FluxWP plugins.', 'flx-ai' ), $site );
	$caps     = array(
		'tools'     => array( 'listChanged' => false ),
		'prompts'   => array( 'listChanged' => false ),
		'resources' => array( 'listChanged' => false ),
	);
	$auth     = array(
		'required' => true,
		'schemes'  => $oauth ? array( 'oauth2', 'bearer' ) : array( 'bearer' ),
	);
	if ( $oauth ) {
		$rel                      = substr( (string) wp_parse_url( $url, PHP_URL_PATH ), strlen( flxai_home_path() ) );
		$auth['resourceMetadata'] = home_url( '/.well-known/oauth-protected-resource' . $rel );
	}
	$card = array(
		// SEP-2127 / MCP registry alanları.
		'name'                      => flxai_server_card_name(),
		'title'                     => $site,
		'description'               => '' !== $desc ? $desc : $fallback,
		'version'                   => FLXAI_VERSION,
		'websiteUrl'                => home_url( '/' ),
		'remotes'                   => array(
			array(
				'type' => 'streamable-http',
				'url'  => $url,
			),
		),
		'supportedProtocolVersions' => FLXAI_MCP_PROTOCOLS,
		// SEP-1649 alanları.
		'protocolVersion'           => FLXAI_MCP_PROTOCOLS[0],
		'serverInfo'                => array(
			'name'    => 'fluxwp',
			'title'   => $site . ' (WordPress)',
			'version' => FLXAI_VERSION,
		),
		'transport'                 => array(
			'type'     => 'streamable-http',
			'endpoint' => $url,
		),
		'capabilities'              => $caps,
		'authentication'            => $auth,
		'documentationUrl'          => 'https://fluxwp.com/plugins/flux-ai/',
	);
	$icon = get_site_icon_url( 512 );
	if ( $icon ) {
		$card['icons']   = array(
			array(
				'src'   => $icon,
				'sizes' => '512x512',
			),
		);
		$card['iconUrl'] = $icon;
	}
	return (array) apply_filters( 'flx_mcp_server_card', $card );
}

/* Kart, Flux App ve diğer .well-known yanıtlarından önce (init 0) verilir. */
add_action(
	'init',
	function () {
		if ( ! in_array( flxai_request_rel_path(), flxai_server_card_paths(), true ) || flxai_mcp_legacy() || ! flxai_mcp_enabled() ) {
			return;
		}
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
		if ( 'OPTIONS' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			exit;
		}
		status_header( 200 );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Cache-Control: public, max-age=3600' );
		header( 'X-Robots-Tag: noindex' );
		echo wp_json_encode( flxai_server_card(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		exit;
	},
	0
);

/* Ana sayfadan da duyurulur: <link rel="mcp-server-card">. */
add_action(
	'wp_head',
	function () {
		if ( flxai_mcp_enabled() && ! flxai_mcp_legacy() && ( is_front_page() || is_home() ) ) {
			echo '<link rel="mcp-server-card" type="application/json" href="' . esc_url( home_url( '/.well-known/mcp/server-card.json' ) ) . '">' . "\n";
		}
	},
	3
);
