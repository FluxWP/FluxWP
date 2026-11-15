<?php
/**
 * Ajan hazırlığı (AI agent readiness):
 *  - robots.txt Content-Signal satırı (contentsignals.org: search, ai-input, ai-train).
 *  - Sitemap dizini ve taksonomi sitemap'lerinde <lastmod>.
 *  - Accept: text/markdown isteyen ajana sayfanın Markdown hâli.
 *  - WebMCP: sayfa açılınca tarayıcı ajanlarına araç kaydı (document/navigator.modelContext.registerTool).
 *
 * @package flx-seo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------------------
 * Content-Signal
 * ------------------------------------------------------------------------- */

/** "search=yes, ai-input=yes, ai-train=no"; kapalıysa ya da hiçbir değer yoksa boş. */
function flxseo_content_signal_value() {
	if ( ! flxseo_opt( 'content_signal' ) ) {
		return '';
	}
	$parts = array();
	foreach ( array( 'search' => 'cs_search', 'ai-input' => 'cs_ai_input', 'ai-train' => 'cs_ai_train' ) as $key => $opt ) {
		$v = (string) flxseo_opt( $opt );
		if ( in_array( $v, array( 'yes', 'no' ), true ) ) {
			$parts[] = $key . '=' . $v;
		}
	}
	return implode( ', ', (array) apply_filters( 'flxseo_content_signal', $parts ) );
}

/**
 * Content-Signal satırı "User-agent: *" grubunun içine yazılır (grubun dışındaki satırı bazı ayrıştırıcılar yok sayar).
 * Kökte gerçek robots.txt dosyası varsa sunucu onu verir; bu filtre çalışmaz (denetim bunu bildirir).
 */
add_filter(
	'robots_txt',
	function ( $out, $public ) {
		$value = flxseo_content_signal_value();
		if ( ! $public || '' === $value || false !== stripos( $out, 'Content-Signal:' ) ) {
			return $out;
		}
		$line = 'Content-Signal: ' . $value;
		if ( preg_match( '/^User-agent:\s*\*\s*$/mi', $out, $m, PREG_OFFSET_CAPTURE ) ) {
			$pos = $m[0][1] + strlen( $m[0][0] );
			return substr( $out, 0, $pos ) . "\n" . $line . substr( $out, $pos );
		}
		return "User-agent: *\n" . $line . "\n\n" . ltrim( $out );
	},
	30,
	2
);

/* ---------------------------------------------------------------------------
 * Sitemap lastmod
 * ------------------------------------------------------------------------- */

/** Terimdeki en son güncellenen yayının zamanı (GMT, ISO 8601); yoksa boş. */
function flxseo_term_lastmod( $term_id, $taxonomy ) {
	global $wpdb;
	$term = get_term( (int) $term_id, $taxonomy );
	if ( ! $term || is_wp_error( $term ) ) {
		return '';
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$gmt = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(p.post_modified_gmt) FROM {$wpdb->posts} p INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID WHERE tr.term_taxonomy_id = %d AND p.post_status = 'publish'", (int) $term->term_taxonomy_id ) );
	return $gmt && '0000-00-00 00:00:00' !== $gmt ? gmdate( 'c', strtotime( $gmt . ' UTC' ) ) : '';
}

add_filter(
	'wp_sitemaps_taxonomies_entry',
	function ( $entry, $term_id, $taxonomy ) {
		if ( empty( $entry['lastmod'] ) ) {
			$lm = flxseo_term_lastmod( $term_id, $taxonomy );
			if ( '' !== $lm ) {
				$entry['lastmod'] = $lm;
			}
		}
		return $entry;
	},
	10,
	3
);

/**
 * Sitemap dizinindeki her alt sitemap'e lastmod: o sayfadaki adreslerin en yenisi.
 * Alt sitemap'in adres listesi bir kez hesaplanır ve içerik değişene kadar saklanır.
 */
add_filter(
	'wp_sitemaps_index_entry',
	function ( $entry, $object_type, $subtype, $page ) {
		if ( ! empty( $entry['lastmod'] ) ) {
			return $entry;
		}
		$cache = get_transient( 'flxseo_sm_lastmod' );
		$cache = is_array( $cache ) ? $cache : array();
		$key   = $object_type . ':' . $subtype . ':' . (int) $page;
		if ( ! array_key_exists( $key, $cache ) ) {
			$names    = array(
				'post' => 'posts',
				'term' => 'taxonomies',
				'user' => 'users',
			);
			$provider = isset( $names[ $object_type ] ) ? wp_sitemaps_get_server()->registry->get_provider( $names[ $object_type ] ) : null;
			$latest   = '';
			if ( $provider ) {
				foreach ( (array) $provider->get_url_list( (int) $page, (string) $subtype ) as $u ) {
					$lm = (string) ( $u['lastmod'] ?? '' );
					if ( '' !== $lm && ( '' === $latest || strtotime( $lm ) > strtotime( $latest ) ) ) {
						$latest = $lm;
					}
				}
			}
			$cache[ $key ] = '' !== $latest ? gmdate( 'c', strtotime( $latest ) ) : '';
			set_transient( 'flxseo_sm_lastmod', $cache, DAY_IN_SECONDS );
		}
		if ( '' !== $cache[ $key ] ) {
			$entry['lastmod'] = $cache[ $key ];
		}
		return $entry;
	},
	10,
	4
);

function flxseo_sitemap_lastmod_flush() {
	delete_transient( 'flxseo_sm_lastmod' );
}
add_action( 'save_post', 'flxseo_sitemap_lastmod_flush' );
add_action( 'deleted_post', 'flxseo_sitemap_lastmod_flush' );
add_action( 'edited_term', 'flxseo_sitemap_lastmod_flush' );
add_action( 'update_option_flxseo', 'flxseo_sitemap_lastmod_flush' );

/* ---------------------------------------------------------------------------
 * Markdown (Accept: text/markdown)
 * ------------------------------------------------------------------------- */

/**
 * İstemci Markdown'ı HTML'den daha çok mu istiyor? Accept q değerleri karşılaştırılır;
 * "text/markdown" tek başına ya da HTML'den yüksek/eşit q ile gelirse evet.
 */
function flxseo_wants_markdown( $accept = null ) {
	$accept = strtolower( (string) ( null === $accept ? ( $_SERVER['HTTP_ACCEPT'] ?? '' ) : $accept ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( false === strpos( $accept, 'text/markdown' ) && false === strpos( $accept, 'text/x-markdown' ) ) {
		return false;
	}
	$q = array(
		'md'   => 0.0,
		'html' => 0.0,
	);
	foreach ( explode( ',', $accept ) as $part ) {
		$bits = array_map( 'trim', explode( ';', $part ) );
		$type = array_shift( $bits );
		$w    = 1.0;
		foreach ( $bits as $b ) {
			if ( 0 === strpos( $b, 'q=' ) ) {
				$w = (float) substr( $b, 2 );
			}
		}
		if ( in_array( $type, array( 'text/markdown', 'text/x-markdown' ), true ) ) {
			$q['md'] = max( $q['md'], $w );
		} elseif ( in_array( $type, array( 'text/html', 'application/xhtml+xml' ), true ) ) {
			$q['html'] = max( $q['html'], $w );
		}
	}
	return $q['md'] > 0 && $q['md'] >= $q['html'];
}

/** Markdown sunulabilecek bir sayfa mı? (yönetim, REST, besleme, arama, 404 ve noindex hariç) */
function flxseo_markdown_eligible() {
	if ( ! flxseo_opt( 'markdown' ) || is_admin() || wp_doing_ajax() || is_feed() || is_search() || is_404() || is_preview() || is_robots() || post_password_required() ) {
		return false;
	}
	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) ) {
		return false;
	}
	if ( is_singular() && flxseo_get( get_queried_object_id(), 'noindex' ) ) {
		return false;
	}
	return (bool) apply_filters( 'flxseo_markdown_eligible', is_singular() || is_front_page() || is_home() || is_archive() );
}

/* Markdown ve HTML aynı adresten sunulur: önbellekler Accept'e göre ayırsın. */
add_action(
	'send_headers',
	function () {
		if ( flxseo_opt( 'markdown' ) && ! is_admin() && ! headers_sent() ) {
			header( 'Vary: Accept', false );
		}
	}
);

add_action(
	'template_redirect',
	function () {
		if ( ! flxseo_wants_markdown() || ! flxseo_markdown_eligible() ) {
			return;
		}
		// Sayfa önbelleğe Markdown olarak girmesin (LiteSpeed, WP Super Cache, W3TC ve CDN'ler).
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'flx-seo markdown' );
		header( 'X-LiteSpeed-Cache-Control: no-cache' );
		header( 'Cache-Control: private, max-age=0' );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		$canonical = function_exists( 'flxseo_canonical' ) ? (string) flxseo_canonical() : '';
		ob_start(
			function ( $html ) use ( $canonical ) {
				return flxseo_html_to_markdown_page( (string) $html, $canonical );
			}
		);
	},
	99
);

/** Tam sayfa HTML'inden ön bilgi (title, url, description) + ana içerik Markdown'ı. */
function flxseo_html_to_markdown_page( $html, $url = '' ) {
	if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
		return $html;
	}
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR );
	libxml_clear_errors();
	$xp    = new DOMXPath( $doc );
	$title = trim( (string) ( $xp->query( '//title' )->item( 0 )->textContent ?? '' ) );
	$desc  = '';
	$d     = $xp->query( '//meta[@name="description"]/@content' )->item( 0 );
	if ( $d ) {
		$desc = trim( $d->nodeValue );
	}
	if ( '' === $url ) {
		$c   = $xp->query( '//link[@rel="canonical"]/@href' )->item( 0 );
		$url = $c ? $c->nodeValue : '';
	}
	$lang = (string) $doc->documentElement->getAttribute( 'lang' );
	if ( '' === $lang ) {
		$h    = $xp->query( '//html' )->item( 0 );
		$lang = $h ? $h->getAttribute( 'lang' ) : '';
	}
	$root = $xp->query( '//main' )->item( 0 );
	if ( ! $root ) {
		$root = $xp->query( '//*[@role="main"]' )->item( 0 );
	}
	if ( ! $root ) {
		$root = $xp->query( '//body' )->item( 0 );
	}
	$body = $root ? flxseo_md_node( $root, array( 'base' => $url, 'inmain' => 'body' !== strtolower( $root->nodeName ) ) ) : '';
	$body = trim( preg_replace( "/\n{3,}/", "\n\n", preg_replace( "/[ \t]+\n/", "\n", $body ) ) );

	$esc   = function ( $s ) {
		return '"' . str_replace( array( '\\', '"', "\n" ), array( '\\\\', '\"', ' ' ), html_entity_decode( $s, ENT_QUOTES, 'UTF-8' ) ) . '"';
	};
	$front = array( '---', 'title: ' . $esc( $title ) );
	if ( $url ) {
		$front[] = 'url: ' . $esc( $url );
	}
	if ( $desc ) {
		$front[] = 'description: ' . $esc( $desc );
	}
	if ( $lang ) {
		$front[] = 'lang: ' . $esc( $lang );
	}
	$front[] = '---';
	return (string) apply_filters( 'flxseo_markdown', implode( "\n", $front ) . "\n\n" . $body . "\n", $html, $url );
}

/** Göreli adresi tam adrese çevirir. */
function flxseo_md_abs( $href, $base ) {
	$href = trim( (string) $href );
	if ( '' === $href || preg_match( '#^(https?:|mailto:|tel:|\#)#i', $href ) ) {
		return $href;
	}
	if ( 0 === strpos( $href, '//' ) ) {
		return 'https:' . $href;
	}
	if ( 0 === strpos( $href, '/' ) ) {
		return untrailingslashit( home_url() ) . $href;
	}
	return $base ? trailingslashit( preg_replace( '#[^/]*$#', '', $base ) ) . $href : $href;
}

/** Satır içi metni sadeleştirir. */
function flxseo_md_text( $s ) {
	return preg_replace( '/\s+/u', ' ', (string) $s );
}

/**
 * DOM düğümünü Markdown'a çevirir (başlık, paragraf, liste, bağlantı, görsel, tablo, alıntı, kod).
 * Gizli, dekoratif ve gezinme öğeleri (nav, header, footer, script, svg, form) atlanır.
 */
function flxseo_md_node( DOMNode $n, array $ctx = array() ) {
	if ( XML_TEXT_NODE === $n->nodeType ) {
		return ! empty( $ctx['pre'] ) ? $n->nodeValue : flxseo_md_text( $n->nodeValue );
	}
	if ( XML_ELEMENT_NODE !== $n->nodeType ) {
		return '';
	}
	/** @var DOMElement $n */
	$tag = strtolower( $n->nodeName );
	if ( in_array( $tag, array( 'script', 'style', 'noscript', 'template', 'svg', 'canvas', 'iframe', 'nav', 'form', 'button', 'input', 'select', 'textarea', 'dialog', 'video', 'audio', 'source' ), true ) ) {
		return '';
	}
	// Site başlığı ve alt bilgi atlanır; ana içerikteki yazı başlığı (article > header) kalır.
	if ( in_array( $tag, array( 'header', 'footer' ), true ) && empty( $ctx['inmain'] ) ) {
		return '';
	}
	if ( in_array( $tag, array( 'main', 'article' ), true ) ) {
		$ctx['inmain'] = true;
	}
	if ( $n->hasAttribute( 'hidden' ) || 'true' === $n->getAttribute( 'aria-hidden' ) || preg_match( '/\b(screen-reader-text|skip-link|visually-hidden|sr-only)\b/', $n->getAttribute( 'class' ) ) || preg_match( '/display\s*:\s*none/i', $n->getAttribute( 'style' ) ) ) {
		return '';
	}
	$kids = function ( $extra = array() ) use ( $n, $ctx ) {
		$s = '';
		foreach ( $n->childNodes as $c ) {
			$s .= flxseo_md_node( $c, array_merge( $ctx, $extra ) );
		}
		return $s;
	};
	switch ( $tag ) {
		case 'h1':
		case 'h2':
		case 'h3':
		case 'h4':
		case 'h5':
		case 'h6':
			$t = trim( flxseo_md_text( $kids() ) );
			if ( '' !== $t && ! empty( $ctx['depth'] ) ) {
				return '**' . $t . '** '; // Liste içindeki başlık (ör. yazı kartı) kalın satır olur.
			}
			return '' === $t ? '' : "\n\n" . str_repeat( '#', (int) $tag[1] ) . ' ' . $t . "\n\n";
		case 'p':
			$t = trim( $kids() );
			return '' === $t ? '' : "\n\n" . $t . "\n\n";
		case 'br':
			return "  \n";
		case 'hr':
			return "\n\n---\n\n";
		case 'strong':
		case 'b':
			$t = trim( $kids() );
			return '' === $t ? '' : '**' . $t . '**';
		case 'em':
		case 'i':
			$t = trim( $kids() );
			return '' === $t ? '' : '_' . $t . '_';
		case 'code':
			return ! empty( $ctx['pre'] ) ? $kids() : '`' . trim( $n->textContent ) . '`';
		case 'pre':
			return "\n\n```\n" . rtrim( $kids( array( 'pre' => true ) ) ) . "\n```\n\n";
		case 'blockquote':
			$t = trim( $kids() );
			return '' === $t ? '' : "\n\n> " . str_replace( "\n", "\n> ", preg_replace( "/\n{2,}/", "\n\n", $t ) ) . "\n\n";
		case 'a':
			$t    = trim( flxseo_md_text( $kids() ) );
			$href = flxseo_md_abs( $n->getAttribute( 'href' ), $ctx['base'] ?? '' );
			if ( '' === $t ) {
				$t = trim( $n->getAttribute( 'aria-label' ) );
			}
			if ( '' === $t ) {
				return '';
			}
			return ( '' === $href || '#' === $href[0] || ! empty( $ctx['pre'] ) ) ? $t : '[' . str_replace( array( '[', ']' ), array( '\[', '\]' ), $t ) . '](' . str_replace( ' ', '%20', $href ) . ')';
		case 'img':
			$alt = trim( $n->getAttribute( 'alt' ) );
			$src = $n->getAttribute( 'src' );
			if ( '' === $alt || '' === $src || 0 === strpos( $src, 'data:' ) ) {
				return '';
			}
			return '![' . $alt . '](' . flxseo_md_abs( $src, $ctx['base'] ?? '' ) . ')';
		case 'ul':
		case 'ol':
			$out   = "\n";
			$i     = 0;
			$depth = (int) ( $ctx['depth'] ?? 0 );
			foreach ( $n->childNodes as $li ) {
				if ( XML_ELEMENT_NODE !== $li->nodeType || 'li' !== strtolower( $li->nodeName ) ) {
					continue;
				}
				$t = trim( preg_replace( "/\n{2,}/", "\n", flxseo_md_node( $li, array_merge( $ctx, array( 'depth' => $depth + 1 ) ) ) ) );
				if ( '' === $t ) {
					continue;
				}
				++$i;
				$out .= str_repeat( '  ', $depth ) . ( 'ol' === $tag ? $i . '. ' : '- ' ) . $t . "\n";
			}
			if ( 0 === $i ) {
				return '';
			}
			return 0 === $depth ? "\n" . $out . "\n" : $out;
		case 'li':
			return $kids();
		case 'table':
			$rows = array();
			foreach ( ( new DOMXPath( $n->ownerDocument ) )->query( './/tr', $n ) as $tr ) {
				$cells = array();
				foreach ( $tr->childNodes as $td ) {
					if ( XML_ELEMENT_NODE === $td->nodeType && in_array( strtolower( $td->nodeName ), array( 'td', 'th' ), true ) ) {
						$cells[] = str_replace( '|', '\|', trim( flxseo_md_text( flxseo_md_node( $td, $ctx ) ) ) );
					}
				}
				if ( $cells ) {
					$rows[] = $cells;
				}
			}
			if ( ! $rows ) {
				return '';
			}
			$w   = max( array_map( 'count', $rows ) );
			$out = "\n\n";
			foreach ( $rows as $k => $r ) {
				$out .= '| ' . implode( ' | ', array_pad( $r, $w, '' ) ) . " |\n";
				if ( 0 === $k ) {
					$out .= '|' . str_repeat( ' --- |', $w ) . "\n";
				}
			}
			return $out . "\n";
		case 'td':
		case 'th':
		case 'figcaption':
			return $kids();
		case 'div':
		case 'section':
		case 'article':
		case 'aside':
		case 'figure':
		case 'main':
		case 'details':
		case 'summary':
		case 'dl':
		case 'dt':
		case 'dd':
			$t = $kids();
			return '' === trim( $t ) ? '' : "\n" . $t . "\n";
		default:
			return $kids();
	}
}

/**
 * LiteSpeed sunucu önbelleği istek PHP'ye ulaşmadan HTML'i verir. Markdown isteyen istekler önbelleği atlasın:
 * .htaccess'in başına küçük bir blok yazılır (yalnız LiteSpeed sunucusunda; ayar kapanınca silinir).
 */
function flxseo_md_htaccess_sync() {
	if ( ! function_exists( 'get_home_path' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	$file = get_home_path() . '.htaccess';
	$ls   = false !== stripos( (string) ( $_SERVER['SERVER_SOFTWARE'] ?? '' ), 'litespeed' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! is_file( $file ) || ! wp_is_writable( $file ) ) {
		return false;
	}
	$body  = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$clean = preg_replace( '/# BEGIN Flux SEO Markdown.*?# END Flux SEO Markdown\R*/s', '', $body );
	$want  = $clean;
	if ( $ls && flxseo_opt( 'markdown' ) ) {
		$block = "# BEGIN Flux SEO Markdown\n<IfModule LiteSpeed>\nRewriteEngine on\nRewriteCond %{HTTP_ACCEPT} text/(x-)?markdown [NC]\nRewriteRule .* - [E=Cache-Control:no-cache]\n</IfModule>\n# END Flux SEO Markdown\n\n";
		$want  = $block . $clean;
	}
	if ( $want !== $body ) {
		file_put_contents( $file, $want, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	update_option( 'flxseo_md_htaccess', FLXSEO_VERSION . ':' . ( flxseo_opt( 'markdown' ) ? 1 : 0 ), false );
	return true;
}
add_action( 'update_option_flxseo', 'flxseo_md_htaccess_sync', 20 );
add_action(
	'admin_init',
	function () {
		if ( current_user_can( 'manage_options' ) && ! wp_doing_ajax() && get_option( 'flxseo_md_htaccess' ) !== FLXSEO_VERSION . ':' . ( flxseo_opt( 'markdown' ) ? 1 : 0 ) ) {
			flxseo_md_htaccess_sync();
		}
	}
);
register_deactivation_hook(
	FLXSEO_FILE,
	function () {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$file = get_home_path() . '.htaccess';
		if ( is_file( $file ) && wp_is_writable( $file ) ) {
			$body = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$new  = preg_replace( '/# BEGIN Flux SEO Markdown.*?# END Flux SEO Markdown\R*/s', '', $body );
			if ( $new !== $body ) {
				file_put_contents( $file, $new, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
		delete_option( 'flxseo_md_htaccess' );
	}
);

/* ---------------------------------------------------------------------------
 * WebMCP (buyurgan API): sayfa açılınca site araçları kaydedilir.
 * ------------------------------------------------------------------------- */

/** Tarayıcı ajanlarına açılan araçların tanımı (JS tarafı yalnız bilinen türleri çalıştırır). */
function flxseo_webmcp_config() {
	return (array) apply_filters(
		'flxseo_webmcp_config',
		array(
			'search' => rest_url( 'wp/v2/search' ),
			'llms'   => flxseo_opt( 'llms' ) ? home_url( '/llms.txt' ) : '',
			'md'     => (bool) flxseo_opt( 'markdown' ),
			'origin' => untrailingslashit( home_url() ),
			't'      => array(
				'search'      => __( 'Searches this website and returns matching pages and posts with their title and address.', 'flx-seo' ),
				'query'       => __( 'Words to search for', 'flx-seo' ),
				'limit'       => __( 'How many results to return (1-20)', 'flx-seo' ),
				'read'        => __( 'Reads a page of this website as Markdown (title, description and main content). Without an address, reads the current page.', 'flx-seo' ),
				'url'         => __( 'Page address on this website; empty for the current page', 'flx-seo' ),
				'overview'    => __( 'Returns a short overview of this website (llms.txt): what it is about and its main pages.', 'flx-seo' ),
			),
		)
	);
}

add_action(
	'wp_footer',
	function () {
		if ( ! flxseo_opt( 'webmcp' ) || is_admin() || is_customize_preview() ) {
			return;
		}
		$cfg = wp_json_encode( flxseo_webmcp_config(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		$js  = '(function(c){var mc=document.modelContext||navigator.modelContext;if(!mc||typeof mc.registerTool!=="function")return;'
			. 'function same(u){try{var x=new URL(u||location.href,location.href);return x.origin===location.origin&&x.href.indexOf(c.origin)===0?x:null}catch(e){return null}}'
			. 'function reg(t){try{var r=mc.registerTool(t);if(r&&r.catch)r.catch(function(){})}catch(e){}}'
			. 'reg({name:"search_site",description:c.t.search,inputSchema:{type:"object",properties:{query:{type:"string",description:c.t.query},limit:{type:"integer",minimum:1,maximum:20,description:c.t.limit}},required:["query"]},annotations:{readOnlyHint:true},'
			. 'execute:function(a){var u=new URL(c.search);u.searchParams.set("search",String(a&&a.query||""));u.searchParams.set("per_page",String(Math.min(20,Math.max(1,parseInt(a&&a.limit,10)||10))));u.searchParams.set("_fields","title,url,subtype");'
			. 'return fetch(u.toString(),{credentials:"same-origin"}).then(function(r){return r.json()}).then(function(d){return JSON.stringify((Array.isArray(d)?d:[]).map(function(x){return{title:x.title,url:x.url,type:x.subtype}}))})}});'
			. 'if(c.md)reg({name:"read_page",description:c.t.read,inputSchema:{type:"object",properties:{url:{type:"string",description:c.t.url}}},annotations:{readOnlyHint:true,untrustedContentHint:true},'
			. 'execute:function(a){var u=same(a&&a.url);if(!u)return Promise.resolve("Error: the address must be on "+c.origin);return fetch(u.href,{headers:{Accept:"text/markdown"},credentials:"same-origin"}).then(function(r){return r.text()})}});'
			. 'if(c.llms)reg({name:"site_overview",description:c.t.overview,inputSchema:{type:"object",properties:{}},annotations:{readOnlyHint:true},'
			. 'execute:function(){return fetch(c.llms,{credentials:"same-origin"}).then(function(r){return r.text()})}});'
			. '})(' . $cfg . ');';
		if ( function_exists( 'wp_print_inline_script_tag' ) ) {
			wp_print_inline_script_tag( $js, array( 'id' => 'flxseo-webmcp' ) );
		} else {
			echo '<script id="flxseo-webmcp">' . $js . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	},
	50
);
