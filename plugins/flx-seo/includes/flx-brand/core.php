<?php
/**
 * FluxWP brand signature: small FluxWP mark + "Powered by FluxWP" link to fluxwp.com.
 *
 *  - Admin: appended to the footer text of every FluxWP screen (?page=flx*, flx* post types / taxonomies).
 *  - Front end: block `flx/credit` (the FluxWP theme and the presets put it in the footer), shortcode
 *    [flx_credit], and flx_brand_credit() for plugins with their own pages (QR menu).
 *  - Themes that declare add_theme_support( 'flx-credit' ): if the footer template part was rendered
 *    without the block (e.g. edited in the Site Editor), a thin signature bar is added to the footer.
 *
 * Filters:
 *  - flx_brand_show( bool $show, string $context )  false hides the signature (white-label builds).
 *    Contexts: admin, block, shortcode, footer-fallback, or the plugin's own context (qr-menu, ...).
 *  - flx_brand_is_screen( bool $is, WP_Screen|null $screen )  which admin screens are FluxWP screens.
 *  - flx_brand_url( string $url, string $context )
 *
 * Source: FluxWP/fluxwp tools/brand/flx-brand (copy with tools/brand/sync.sh, do not edit the copies).
 *
 * @package flx-brand
 */

defined( 'ABSPATH' ) || exit;

/** Is the signature shown in this context? */
function flx_brand_enabled( $context = '' ) {
	return (bool) apply_filters( 'flx_brand_show', true, (string) $context );
}

/**
 * The few strings of the signature, TR (default) and EN. Kept here instead of a text domain because the
 * module is shared by every FluxWP package and must not depend on which package's translations are loaded.
 */
function flx_brand_str( $key ) {
	$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
	$tr     = 0 === strpos( (string) $locale, 'tr' );
	$s      = array(
		/* translators: %s: FluxWP */
		'powered' => $tr ? 'Altyapı: %s' : 'Powered by %s',
		'title'   => $tr ? 'FluxWP imzası' : 'FluxWP signature',
		'desc'    => $tr ? 'Küçük FluxWP logosu ve fluxwp.com bağlantısı.' : 'Small FluxWP logo with a link to fluxwp.com.',
		'link'    => $tr ? 'FluxWP web sitesi (yeni sekmede açılır)' : 'FluxWP website (opens in a new tab)',
	);
	$s = apply_filters( 'flx_brand_strings', $s, $tr ? 'tr' : 'en' );
	return isset( $s[ $key ] ) ? $s[ $key ] : '';
}

/** fluxwp.com address. */
function flx_brand_url( $context = '' ) {
	return (string) apply_filters( 'flx_brand_url', 'https://fluxwp.com/', (string) $context );
}

/**
 * FluxWP mark (B · Modul): the F blocks follow the text colour (currentColor), the "added plugin" block stays amber,
 * so it reads on light and dark backgrounds.
 */
function flx_brand_mark( $size = '1.15em' ) {
	$size = esc_attr( $size );
	return '<svg class="flx-credit__mark" width="' . $size . '" height="' . $size . '" viewBox="6 6 52 50" aria-hidden="true" focusable="false" style="display:block;flex:none">'
		. '<g fill="currentColor"><rect x="9" y="9" width="13" height="13" rx="3.5"/><rect x="25.5" y="9" width="13" height="13" rx="3.5"/>'
		. '<rect x="9" y="25.5" width="13" height="13" rx="3.5"/><rect x="25.5" y="25.5" width="13" height="13" rx="3.5"/>'
		. '<rect x="9" y="42" width="13" height="13" rx="3.5"/></g>'
		. '<rect x="44.2" y="6.8" width="13" height="13" rx="3.5" fill="#F59E0B"/></svg>';
}

/**
 * Signature link HTML: mark + "Powered by FluxWP". Empty string when hidden for this context.
 *
 * @param string $context Where it is printed (see flx_brand_show).
 * @param array  $args    size (mark size, CSS length), style (extra inline CSS on the link).
 */
function flx_brand_credit( $context = '', $args = array() ) {
	if ( ! flx_brand_enabled( $context ) ) {
		return '';
	}
	$args  = wp_parse_args( $args, array( 'size' => '1.15em', 'style' => '' ) );
	$label = sprintf( esc_html( flx_brand_str( 'powered' ) ), '<strong style="font-weight:600">FluxWP</strong>' );
	$style = 'display:inline-flex;align-items:center;gap:.4em;color:inherit;text-decoration:none;line-height:1.2;white-space:nowrap;' . $args['style'];

	$css = '';
	if ( empty( $GLOBALS['flx_brand_css'] ) ) {
		$GLOBALS['flx_brand_css'] = true;
		$css = '<style id="flx-credit-css">.flx-credit{text-underline-offset:3px}.flx-credit:hover,.flx-credit:focus-visible{text-decoration:underline}</style>';
	}
	$GLOBALS['flx_brand_printed'] = true;

	return $css . '<a class="flx-credit" href="' . esc_url( flx_brand_url( $context ) ) . '" target="_blank" rel="noopener"'
		. ' title="' . esc_attr( flx_brand_str( 'link' ) ) . '" style="' . esc_attr( $style ) . '"'
		. '>'
		. flx_brand_mark( $args['size'] ) . '<span>' . $label . '</span></a>';
}

/* ---------------------------------------------------------------- Admin */

/** Is the current admin screen a FluxWP screen? */
function flx_brand_is_screen() {
	$is     = false;
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only, screen detection.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( '' !== $page && 0 === strpos( $page, 'flx' ) ) {
		$is = true;
	} elseif ( $screen ) {
		foreach ( array( 'post_type', 'taxonomy' ) as $k ) {
			if ( ! empty( $screen->$k ) && 0 === strpos( $screen->$k, 'flx' ) ) {
				$is = true;
			}
		}
		if ( ! $is && ! empty( $screen->id ) && false !== strpos( $screen->id, '_page_flx' ) ) {
			$is = true;
		}
	}
	return (bool) apply_filters( 'flx_brand_is_screen', $is, $screen );
}

add_filter( 'admin_footer_text', 'flx_brand_admin_footer', 100 );

/** Appends the signature to the admin footer text on FluxWP screens. */
function flx_brand_admin_footer( $text ) {
	if ( ! flx_brand_is_screen() ) {
		return $text;
	}
	$credit = flx_brand_credit( 'admin', array( 'style' => 'vertical-align:middle' ) );
	if ( '' === $credit ) {
		return $text;
	}
	$text = trim( (string) $text );
	return ( '' !== $text ? $text . ' <span aria-hidden="true" style="margin:0 .35em;opacity:.5">·</span> ' : '' )
		. '<span id="flx-credit" style="display:inline-block;vertical-align:middle">' . $credit . '</span>';
}

/* ---------------------------------------------------------------- Front end */

add_action( 'init', 'flx_brand_register_block' );
add_shortcode( 'flx_credit', 'flx_brand_shortcode' );
add_filter( 'render_block_core/template-part', 'flx_brand_footer_fallback', 20, 2 );

/** Block flx/credit (server rendered; the editor shows the same HTML). */
function flx_brand_register_block() {
	if ( ! function_exists( 'register_block_type' ) || WP_Block_Type_Registry::get_instance()->is_registered( 'flx/credit' ) ) {
		return;
	}
	wp_register_script( 'flx-credit-editor', false, array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render' ), FLX_BRAND_VERSION, true );
	wp_add_inline_script(
		'flx-credit-editor',
		'(function(wp){if(!wp||!wp.blocks||wp.blocks.getBlockType("flx/credit"))return;var el=wp.element.createElement,SSR=wp.serverSideRender;'
		. 'wp.blocks.registerBlockType("flx/credit",{apiVersion:3,icon:el("svg",{viewBox:"6 6 52 50"},el("path",{d:"M12.5 9h6a3.5 3.5 0 0 1 3.5 3.5v6a3.5 3.5 0 0 1-3.5 3.5h-6A3.5 3.5 0 0 1 9 18.5v-6A3.5 3.5 0 0 1 12.5 9zm16.5 0h6a3.5 3.5 0 0 1 3.5 3.5v6a3.5 3.5 0 0 1-3.5 3.5h-6a3.5 3.5 0 0 1-3.5-3.5v-6A3.5 3.5 0 0 1 29 9zM12.5 25.5h6a3.5 3.5 0 0 1 3.5 3.5v6a3.5 3.5 0 0 1-3.5 3.5h-6A3.5 3.5 0 0 1 9 35v-6a3.5 3.5 0 0 1 3.5-3.5zm16.5 0h6a3.5 3.5 0 0 1 3.5 3.5v6a3.5 3.5 0 0 1-3.5 3.5h-6a3.5 3.5 0 0 1-3.5-3.5v-6a3.5 3.5 0 0 1 3.5-3.5zM12.5 42h6a3.5 3.5 0 0 1 3.5 3.5v6a3.5 3.5 0 0 1-3.5 3.5h-6A3.5 3.5 0 0 1 9 51.5v-6A3.5 3.5 0 0 1 12.5 42zm35.2-35.2h6a3.5 3.5 0 0 1 3.5 3.5v6a3.5 3.5 0 0 1-3.5 3.5h-6a3.5 3.5 0 0 1-3.5-3.5v-6a3.5 3.5 0 0 1 3.5-3.5z"})),'
		. 'edit:function(p){return el("div",wp.blockEditor.useBlockProps({style:{pointerEvents:"none"}}),el(SSR,{block:"flx/credit",attributes:p.attributes}));},save:function(){return null;}});})(window.wp);'
	);
	register_block_type(
		'flx/credit',
		array(
			'api_version'     => 3,
			'title'           => flx_brand_str( 'title' ),
			'description'     => flx_brand_str( 'desc' ),
			'category'        => 'theme',
			'keywords'        => array( 'fluxwp', 'credit', 'powered' ),
			'editor_script'   => 'flx-credit-editor',
			'render_callback' => 'flx_brand_render_block',
			'supports'        => array(
				'html'       => false,
				'multiple'   => false,
				'reusable'   => false,
				'color'      => array( 'text' => true, 'background' => false, 'link' => false ),
				'typography' => array( 'fontSize' => true ),
				'spacing'    => array( 'margin' => true ),
			),
		)
	);
}

/** Block render. */
function flx_brand_render_block( $attributes = array(), $content = '' ) {
	$credit = flx_brand_credit( 'block' );
	if ( '' === $credit ) {
		return '';
	}
	$wrap = function_exists( 'get_block_wrapper_attributes' ) ? get_block_wrapper_attributes() : 'class="wp-block-flx-credit"';
	return '<div ' . $wrap . '>' . $credit . '</div>';
}

/** [flx_credit] */
function flx_brand_shortcode() {
	return flx_brand_credit( 'shortcode' );
}

/**
 * Footer fallback: on themes with add_theme_support( 'flx-credit' ), if the footer template part did not
 * print the signature (block removed in the Site Editor), add a thin bar at the end of the footer.
 */
function flx_brand_footer_fallback( $html, $block ) {
	if ( ! current_theme_supports( 'flx-credit' ) || ! empty( $GLOBALS['flx_brand_printed'] ) ) {
		return $html;
	}
	$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
	$slug  = isset( $attrs['slug'] ) ? (string) $attrs['slug'] : '';
	$area  = isset( $attrs['area'] ) ? (string) $attrs['area'] : '';
	if ( 'footer' !== $slug && 'footer' !== $area && 0 !== strpos( $slug, 'footer' ) ) {
		return $html;
	}
	$credit = flx_brand_credit( 'footer-fallback', array( 'size' => '1.1em' ) );
	if ( '' === $credit ) {
		return $html;
	}
	$bar = '<div class="flx-credit-bar" style="display:flex;justify-content:center;padding:.75rem 1rem;font-size:.75rem">' . $credit . '</div>';
	$pos = strripos( $html, '</footer>' );
	return false === $pos ? $html . $bar : substr( $html, 0, $pos ) . $bar . substr( $html, $pos );
}

/* ---------- Dil bazlı tarih ve "… önce" (her FluxWP paketi kullanır) ----------
 *
 * WordPress'in human_time_diff() ve wp_date() ay/gün adları çekirdek dil paketine bağlıdır; paket inmemişse
 * ("31 minutes önce", "August 10, 2026") eklentinin Türkçe metniyle karışır. Bu işlevler TR ve EN'i kendi
 * içinde taşır, diğer dillerde WordPress'e bırakır. Yönetimde kullanıcının dili (determine_locale) esas alınır.
 */

/** Görüntü dili: 'tr', 'en' ya da '' (başka dil: WordPress çevirisi kullanılır). */
function flx_lang() {
	$l = (string) ( function_exists( 'determine_locale' ) ? determine_locale() : get_locale() );
	$l = (string) apply_filters( 'flx_lang_locale', $l );
	if ( 0 === strpos( $l, 'tr' ) ) {
		return 'tr';
	}
	return 0 === strpos( $l, 'en' ) ? 'en' : '';
}

/**
 * Zaman damgasına çevirir: int (Unix), DateTimeInterface ya da GMT "Y-m-d H:i:s" (WordPress veritabanı biçimi).
 *
 * @return int 0 = geçersiz.
 */
function flx_ts( $time ) {
	if ( $time instanceof DateTimeInterface ) {
		return $time->getTimestamp();
	}
	if ( is_numeric( $time ) ) {
		return (int) $time;
	}
	$time = trim( (string) $time );
	if ( '' === $time || 0 === strpos( $time, '0000-00-00' ) ) {
		return 0;
	}
	$ts = strtotime( preg_match( '/[zZ]|[+-]\d\d:?\d\d$/', $time ) ? $time : $time . ' UTC' );
	return $ts ? (int) $ts : 0;
}

/**
 * "31 dakika önce" / "31 minutes ago", "dün" / "yesterday", gelecek için "3 gün sonra" / "in 3 days".
 *
 * @param int|string|DateTimeInterface $time Bkz flx_ts().
 * @param int|string|null              $now  Karşılaştırma anı (varsayılan şimdi).
 */
function flx_time_ago( $time, $now = null ) {
	$ts = flx_ts( $time );
	if ( ! $ts ) {
		return '—';
	}
	$now  = null === $now ? time() : flx_ts( $now );
	$diff = $now - $ts;
	$fut  = $diff < 0;
	$diff = abs( $diff );
	$lang = flx_lang();
	if ( '' === $lang ) {
		/* translators: %s: time difference (WordPress core strings) */
		return sprintf( $fut ? __( '%s from now' ) : __( '%s ago' ), human_time_diff( $ts, $now ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
	}
	$tr = 'tr' === $lang;
	if ( $diff < 60 ) {
		return $tr ? ( $fut ? 'birazdan' : 'az önce' ) : ( $fut ? 'in a moment' : 'just now' );
	}
	$units = array(
		array( YEAR_IN_SECONDS, 'yıl', 'year' ),
		array( MONTH_IN_SECONDS, 'ay', 'month' ),
		array( WEEK_IN_SECONDS, 'hafta', 'week' ),
		array( DAY_IN_SECONDS, 'gün', 'day' ),
		array( HOUR_IN_SECONDS, 'saat', 'hour' ),
		array( MINUTE_IN_SECONDS, 'dakika', 'minute' ),
	);
	foreach ( $units as $u ) {
		if ( $diff >= $u[0] ) {
			$n = max( 1, (int) floor( $diff / $u[0] ) );
			if ( DAY_IN_SECONDS === $u[0] && 1 === $n ) {
				return $tr ? ( $fut ? 'yarın' : 'dün' ) : ( $fut ? 'tomorrow' : 'yesterday' );
			}
			$num = number_format_i18n( $n );
			if ( $tr ) {
				return $num . ' ' . $u[1] . ( $fut ? ' sonra' : ' önce' );
			}
			$w = $num . ' ' . $u[2] . ( 1 === $n ? '' : 's' );
			return $fut ? 'in ' . $w : $w . ' ago';
		}
	}
	return '';
}

/**
 * Tarih, kullanıcının dilinde ay/gün adlarıyla. Saat dilimi sitenin saat dilimi (wp_date).
 *
 * @param int|string|DateTimeInterface $time   Bkz flx_ts().
 * @param string                       $format 'date' (10 Ağustos 2026 / Aug 10, 2026), 'short' (10 Ağu / Aug 10),
 *                                             'datetime' (tarih + saat) ya da PHP tarih biçimi.
 */
function flx_date( $time, $format = 'date' ) {
	$ts = flx_ts( $time );
	if ( ! $ts ) {
		return '—';
	}
	$lang = flx_lang();
	$tr   = 'tr' === $lang;
	$presets = array(
		'date'     => $tr ? 'j F Y' : 'M j, Y',
		'short'    => $tr ? 'j M' : 'M j',
		'datetime' => $tr ? 'j F Y H:i' : 'M j, Y H:i',
		'time'     => 'H:i',
	);
	if ( '' === $lang ) {
		$presets = array(
			'date'     => get_option( 'date_format' ),
			'short'    => 'j M',
			'datetime' => get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			'time'     => get_option( 'time_format' ),
		);
	}
	$fmt = isset( $presets[ $format ] ) ? $presets[ $format ] : (string) $format;
	if ( '' === $lang ) {
		return wp_date( $fmt, $ts );
	}
	$names = $tr ? array(
		'F' => array( 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık' ),
		'M' => array( 'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara' ),
		'l' => array( 'Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi' ),
		'D' => array( 'Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt' ),
	) : array(
		'F' => array( 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ),
		'M' => array( 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' ),
		'l' => array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ),
		'D' => array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ),
	);
	$month = (int) wp_date( 'n', $ts ) - 1;
	$day   = (int) wp_date( 'w', $ts );
	$out   = '';
	$len   = strlen( $fmt );
	for ( $i = 0; $i < $len; $i++ ) {
		$c = $fmt[ $i ];
		if ( '\\' === $c && $i + 1 < $len ) {
			$out .= '\\' . $fmt[ ++$i ];
			continue;
		}
		if ( isset( $names[ $c ] ) ) {
			$word = $names[ $c ][ 'F' === $c || 'M' === $c ? $month : $day ];
			$out .= preg_replace( '/(.)/u', '\\\\$1', $word );
			continue;
		}
		$out .= $c;
	}
	return wp_date( $out, $ts );
}

/* ---------- Telefon alanı (her FluxWP paketi kullanır) ----------
 *
 * Tek bileşen: bayraklı ülke seçici + ülke arama + numara alanı + sağda gecikmeli ✓ / ✗ doğrulama
 * (libphonenumber, phone/utils.js, ilk alan görününce yüklenir). Değer gizli alanda E.164 (+4915123456789).
 *
 *   echo flx_phone_field( array( 'name' => 'phone', 'value' => '+905321234567' ) );
 *   $e164 = flx_phone_normalize( $_POST['phone'] );   // '' = geçersiz
 *
 * Filtreler: flx_phone_country (varsayılan ülke, iso2), flx_phone_preferred (listenin üstündeki ülkeler),
 * flx_phone_strings (metinler). JS: window.flxPhone.init(root), "flx-phone:change" olayı {valid, e164, country}.
 */

/** phone/ klasörünün adresi (eklenti, mu-plugin ya da tema kopyası). */
function flx_phone_url( $file = '' ) {
	$dir = wp_normalize_path( __DIR__ . '/phone' );
	$tpl = wp_normalize_path( get_theme_root() );
	if ( 0 === strpos( $dir, $tpl ) ) {
		$url = get_theme_root_uri() . substr( $dir, strlen( $tpl ) );
	} else {
		$url = plugins_url( '', $dir . '/x' );
	}
	return trailingslashit( $url ) . ltrim( $file, '/' );
}

/** Metinler (TR / EN gömülü, diğer dillerde EN). */
function flx_phone_strings() {
	$tr = 'tr' === flx_lang();
	return (array) apply_filters(
		'flx_phone_strings',
		array(
			'search'  => $tr ? 'Ülke ara' : 'Search country',
			'none'    => $tr ? 'Ülke bulunamadı' : 'No country found',
			'country' => $tr ? 'Ülke kodu' : 'Country code',
			'ok'      => $tr ? 'Numara geçerli' : 'Valid number',
			'bad'     => $tr ? 'Numara geçersiz görünüyor, lütfen kontrol edin.' : 'This number does not look right, please check it.',
			'label'   => $tr ? 'Telefon numarası' : 'Phone number',
		)
	);
}

/** Varsayılan ülke (iso2): filtre → WooCommerce mağaza ülkesi → site dili → tr. */
function flx_phone_country() {
	$c = '';
	if ( function_exists( 'WC' ) && WC()->countries ) {
		$c = (string) WC()->countries->get_base_country();
	}
	if ( '' === $c ) {
		$l = (string) get_locale();
		$c = false !== strpos( $l, '_' ) ? substr( $l, strpos( $l, '_' ) + 1, 2 ) : ( 'tr' === flx_lang() ? 'TR' : '' );
	}
	$c = strtolower( (string) apply_filters( 'flx_phone_country', $c ) );
	return preg_match( '/^[a-z]{2}$/', $c ) ? $c : 'tr';
}

/** Listenin üstündeki ülkeler (iso2). */
function flx_phone_preferred() {
	$p = apply_filters( 'flx_phone_preferred', array( flx_phone_country() ) );
	return array_values( array_unique( array_filter( array_map( 'strtolower', (array) $p ), static function ( $c ) { return (bool) preg_match( '/^[a-z]{2}$/', $c ); } ) ) );
}

/** CSS + JS (bir kez). Alanı kendi HTML'iyle basan paketler de çağırabilir. */
function flx_phone_enqueue() {
	if ( wp_script_is( 'flx-phone', 'enqueued' ) ) {
		return;
	}
	$v = defined( 'FLX_BRAND_VERSION' ) ? FLX_BRAND_VERSION : '1';
	wp_enqueue_style( 'flx-phone', flx_phone_url( 'phone.css' ), array(), $v );
	wp_add_inline_style( 'flx-phone', ':root{--flx-ph-flags:url("' . esc_url( flx_phone_url( 'flags.webp' ) ) . '")}' );
	wp_enqueue_script( 'flx-phone', flx_phone_url( 'phone.js' ), array(), $v, true );
	wp_localize_script(
		'flx-phone',
		'FLX_PHONE',
		array(
			'utils'     => flx_phone_url( 'utils.js' ) . '?ver=' . rawurlencode( $v ),
			'lang'      => '' !== flx_lang() ? flx_lang() : substr( (string) determine_locale(), 0, 2 ),
			'country'   => flx_phone_country(),
			'preferred' => implode( ',', flx_phone_preferred() ),
			'i18n'      => flx_phone_strings(),
		)
	);
}

/**
 * Telefon alanı HTML'i.
 *
 * @param array $args name (gerekli), value (E.164 ya da ham), id, country (iso2), preferred (iso2 dizisi ya da "de,fr"),
 *                    placeholder (boş = ülkenin örnek numarası), required, label (görünmez etiket), class, attrs (ek nitelikler),
 *                    message (true: hatalı numarada altta uyarı satırı).
 */
function flx_phone_field( array $args ) {
	flx_phone_enqueue();
	$s    = flx_phone_strings();
	$a    = wp_parse_args(
		$args,
		array(
			'name'        => 'phone',
			'value'       => '',
			'id'          => '',
			'country'     => '',
			'preferred'   => '',
			'placeholder' => '',
			'required'    => false,
			'label'       => $s['label'],
			'class'       => '',
			'attrs'       => array(),
			'message'     => true,
		)
	);
	$id   = '' !== $a['id'] ? $a['id'] : 'flx-ph-' . wp_rand( 10000, 99999 );
	$pref = is_array( $a['preferred'] ) ? implode( ',', $a['preferred'] ) : (string) $a['preferred'];
	$cc   = '' !== $a['country'] ? strtolower( $a['country'] ) : flx_phone_country();
	$val  = trim( (string) $a['value'] );
	$attr = '';
	foreach ( (array) $a['attrs'] as $k => $v ) {
		$attr .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
	}
	$h  = '<div class="flx-ph' . ( '' !== $a['class'] ? ' ' . esc_attr( $a['class'] ) : '' ) . '" data-flx-phone data-country="' . esc_attr( $cc ) . '"' . ( '' !== $pref ? ' data-preferred="' . esc_attr( $pref ) . '"' : '' ) . ( '' !== $a['placeholder'] ? ' data-placeholder' : '' ) . '>';
	$h .= '<button type="button" class="flx-ph__cc" aria-haspopup="listbox" aria-expanded="false" aria-label="' . esc_attr( $s['country'] ) . '"><span class="flx-ph__flag" aria-hidden="true"></span><span class="flx-ph__dial"></span><svg class="flx-ph__chev" viewBox="0 0 12 12" aria-hidden="true"><path d="M3 4.5l3 3 3-3"/></svg></button>';
	$h .= '<span class="flx-ph__box"><input type="tel" class="flx-ph__num" id="' . esc_attr( $id ) . '" name="' . esc_attr( $a['name'] ) . '" value="' . esc_attr( $val ) . '" inputmode="tel" autocomplete="tel-national" aria-label="' . esc_attr( $a['label'] ) . '"' . ( '' !== $a['placeholder'] ? ' placeholder="' . esc_attr( $a['placeholder'] ) . '"' : '' ) . ( $a['required'] ? ' required' : '' ) . $attr . '><span class="flx-ph__state" aria-hidden="true"></span></span>';
	$h .= '<input type="hidden" class="flx-ph__val" data-name="' . esc_attr( $a['name'] ) . '" value="' . esc_attr( $val ) . '">';
	if ( $a['message'] ) {
		$h .= '<p class="flx-ph__msg" role="alert" hidden></p>';
	}
	return $h . '</div>';
}

/**
 * Numarayı E.164'e çevirir (+905321234567). Ülke kodsuz yazılmışsa $country (iso2, boş = varsayılan ülke) eklenir.
 * Türkiye (tr): başında + ya da 00 olmayan numarada baştaki sıfırlar atılır (N). N tam 10 haneyse 90 eklenir,
 * 10'dan uzunsa N olduğu gibi kalır, daha kısaysa geçersizdir. Her yolda 900 + 10 hane (13) fazladan 0'ı düşer;
 * 90 ile başlayan sonuç yalnız 12 hane ve üçüncü hanesi 2, 3, 4, 5 ya da 8 ise geçerlidir.
 * Diğer ülkeler: yalnız biçim ve uzunluk (8–15 hane).
 *
 * @return string '' = geçersiz ya da boş.
 */
function flx_phone_normalize( $raw, $country = '' ) {
	$raw = trim( (string) $raw );
	$dg  = preg_replace( '/\D+/', '', $raw );
	if ( '' === $dg ) {
		return '';
	}
	$cc = '' !== $country ? strtolower( (string) $country ) : flx_phone_country();
	if ( 'tr' === $cc ) {
		$intl = '+' === substr( $raw, 0, 1 ) || 0 === strpos( $dg, '00' );
		if ( $intl ) {
			$dg = ltrim( $dg, '0' );
		} elseif ( '0' === $dg[0] ) {
			$dg = '0' . ltrim( $dg, '0' );
		}
		if ( '' === $dg ) {
			return '';
		}
		if ( 0 === strpos( $dg, '900' ) && 13 === strlen( $dg ) ) {
			$dg = '90' . substr( $dg, 3 );
		}
		if ( ! $intl ) {
			$len = strlen( $dg );
			if ( 11 === $len && '0' === $dg[0] ) {
				$dg = '90' . substr( $dg, 1 );
			} elseif ( 10 === $len && '0' !== $dg[0] ) {
				$dg = '90' . $dg;
			}
		}
		$dg = ltrim( $dg, '0' );
		if ( strlen( $dg ) < 10 || strlen( $dg ) > 15 ) {
			return '';
		}
		if ( 0 === strpos( $dg, '90' ) && ( 12 !== strlen( $dg ) || ! in_array( $dg[2], array( '2', '3', '4', '5', '8' ), true ) ) ) {
			return '';
		}
		return '+' . $dg;
	}
	if ( '+' === substr( $raw, 0, 1 ) ) {
		$out = $dg;
	} elseif ( 0 === strpos( $dg, '00' ) ) {
		$out = substr( $dg, 2 );
	} else {
		$dial = array( 'tr' => '90', 'de' => '49', 'at' => '43', 'ch' => '41', 'nl' => '31', 'be' => '32', 'fr' => '33', 'gb' => '44', 'us' => '1', 'it' => '39', 'es' => '34', 'se' => '46', 'dk' => '45', 'no' => '47', 'az' => '994' );
		$dial = (array) apply_filters( 'flx_phone_dial_codes', $dial );
		if ( ! isset( $dial[ $cc ] ) ) {
			return strlen( $dg ) >= 8 && strlen( $dg ) <= 15 ? '+' . ltrim( $dg, '0' ) : '';
		}
		$out = $dial[ $cc ] . ( '1' === $dial[ $cc ] ? $dg : ltrim( $dg, '0' ) );
		if ( 0 === strpos( $dg, $dial[ $cc ] ) && strlen( $dg ) > 10 ) {
			$out = $dg; // 905321234567 yazılmış
		}
	}
	return strlen( $out ) >= 8 && strlen( $out ) <= 15 ? '+' . $out : '';
}

/** Same number as flx_phone_normalize(), without the leading + (905321234567). '' when invalid. */
function flx_phone_digits( $raw, $country = '' ) {
	$n = flx_phone_normalize( $raw, $country );
	return '' === $n ? '' : substr( $n, 1 );
}

/** True when both values are valid and the same E.164. Trailing digits are not compared; empty or invalid values are not equal. */
function flx_phone_equal( $a, $b, $country = '' ) {
	$a = flx_phone_normalize( $a, $country );
	$b = flx_phone_normalize( $b, $country );
	return '' !== $a && $a === $b;
}

/** Quote a CSV cell that opens as a spreadsheet formula; leave a real number, including a negative, as a number. */
function flx_csv_cell( $v ) {
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

/** Send a CSV download for $header and $rows, then return. The caller exits. */
function flx_csv_stream( string $filename, array $header, iterable $rows, array $args = array() ) {
	$delimiter = isset( $args['delimiter'] ) ? (string) $args['delimiter'] : ',';
	$bom       = array_key_exists( 'bom', $args ) ? (bool) $args['bom'] : true;
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	$out = fopen( 'php://output', 'w' );
	if ( false === $out ) {
		return;
	}
	if ( $bom ) {
		fwrite( $out, "\xEF\xBB\xBF" );
	}
	$write = static function ( array $row ) use ( $out, $delimiter, $args ) {
		$cells = array();
		foreach ( $row as $cell ) {
			$cells[] = flx_csv_cell( $cell );
		}
		if ( array_key_exists( 'enclosure', $args ) || array_key_exists( 'escape', $args ) ) {
			$enclosure = array_key_exists( 'enclosure', $args ) ? (string) $args['enclosure'] : '"';
			$escape    = array_key_exists( 'escape', $args ) ? (string) $args['escape'] : '\\';
			fputcsv( $out, $cells, $delimiter, $enclosure, $escape );
			return;
		}
		fputcsv( $out, $cells, $delimiter );
	};
	if ( array() !== $header ) {
		$write( $header );
	}
	foreach ( $rows as $row ) {
		$write( array_values( (array) $row ) );
	}
	fclose( $out );
}

/** Create $dir and, when missing, deny web access with an .htaccess and an empty index.html. */
function flx_private_dir( string $dir ): bool {
	$dir = rtrim( $dir, "/\\" );
	if ( '' === $dir || ! wp_mkdir_p( $dir ) ) {
		return '' !== $dir && is_dir( $dir ) && wp_is_writable( $dir );
	}
	$ht = $dir . '/.htaccess';
	if ( ! is_file( $ht ) ) {
		file_put_contents(
			$ht,
			"<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n"
		);
	}
	$index = $dir . '/index.html';
	if ( ! is_file( $index ) ) {
		file_put_contents( $index, '' );
	}
	return is_dir( $dir ) && wp_is_writable( $dir );
}

/** Send a private file after the caller has checked permission, then return. The caller exits. */
function flx_send_file( string $path, array $args = array() ) {
	if ( ! is_file( $path ) || ! is_readable( $path ) ) {
		return false;
	}
	$filename = isset( $args['filename'] ) && '' !== (string) $args['filename'] ? (string) $args['filename'] : basename( $path );
	$type     = isset( $args['type'] ) ? (string) $args['type'] : '';
	if ( '' === $type && function_exists( 'wp_check_filetype' ) ) {
		$checked = wp_check_filetype( $filename );
		$type    = is_array( $checked ) && ! empty( $checked['type'] ) ? (string) $checked['type'] : '';
	}
	if ( '' === $type ) {
		$type = 'application/octet-stream';
	}
	$inline = ! empty( $args['inline'] );
	$cache  = array_key_exists( 'cache', $args ) ? (string) $args['cache'] : 'private, no-store';
	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}
	header( 'Content-Type: ' . $type );
	header( 'Content-Length: ' . (string) filesize( $path ) );
	header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . sanitize_file_name( $filename ) . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	if ( '' !== $cache ) {
		header( 'Cache-Control: ' . $cache );
	}
	readfile( $path );
	return true;
}

/** True when $ip sits inside one of the CIDR ranges. */
function flx_ip_in_cidrs( $ip, array $cidrs ) {
	$a = @inet_pton( (string) $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( false === $a ) {
		return false;
	}
	foreach ( $cidrs as $cidr ) {
		$cidr = (string) $cidr;
		if ( false === strpos( $cidr, '/' ) ) {
			continue;
		}
		list( $net, $bits ) = explode( '/', $cidr, 2 );
		$b = @inet_pton( $net ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $b || strlen( $a ) !== strlen( $b ) ) {
			continue;
		}
		$bits  = max( 0, min( strlen( $a ) * 8, (int) $bits ) );
		$bytes = intdiv( $bits, 8 );
		if ( substr( $a, 0, $bytes ) !== substr( $b, 0, $bytes ) ) {
			continue;
		}
		$rest = $bits % 8;
		if ( ! $rest || ( ( ord( $a[ $bytes ] ) ^ ord( $b[ $bytes ] ) ) & ( 0xFF << ( 8 - $rest ) ) & 0xFF ) === 0 ) {
			return true;
		}
	}
	return false;
}

/** Cloudflare published ranges (ips-v4 / ips-v6, 2026-11-10), the same list Flux Analytics uses. */
function flx_cloudflare_cidrs() {
	return array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);
}

/** Caller address for rate limits. Forwarded headers count only from a private or loopback peer, or from Cloudflare. */
function flx_client_ip(): string {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : '';
	if ( ! filter_var( $remote, FILTER_VALIDATE_IP ) ) {
		$remote = '';
	}
	$trust = false;
	$cloud = false;
	if ( '' !== $remote && ! filter_var( $remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
		$trust = true;
	} elseif ( '' !== $remote && flx_ip_in_cidrs( $remote, flx_cloudflare_cidrs() ) ) {
		$trust = true;
		$cloud = true;
	}
	$ip = $remote;
	if ( $trust ) {
		$picked = '';
		if ( $cloud && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$cand = trim( explode( ',', (string) $_SERVER['HTTP_CF_CONNECTING_IP'] )[0] );
			if ( filter_var( $cand, FILTER_VALIDATE_IP ) ) {
				$picked = $cand;
			}
		}
		if ( '' === $picked && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			foreach ( explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] ) as $part ) {
				$part = trim( $part );
				if ( filter_var( $part, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					$picked = $part;
				}
			}
		}
		if ( '' === $picked && ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$cand = trim( explode( ',', (string) $_SERVER['HTTP_X_REAL_IP'] )[0] );
			if ( filter_var( $cand, FILTER_VALIDATE_IP ) ) {
				$picked = $cand;
			}
		}
		if ( '' !== $picked ) {
			$ip = $picked;
		}
	}
	return (string) apply_filters( 'flx_client_ip', $ip );
}

/** Count this hit. True while $key stays within $max hits per fixed $window seconds. Prefix $key with the plugin (keys are shared site-wide). */
function flx_rate_limit( string $key, int $max, int $window ): bool {
	if ( $max <= 0 || $window <= 0 ) {
		return true;
	}
	global $wpdb;
	$now  = time();
	$end  = $now - ( $now % $window ) + $window;
	$name = '_flx_rl_' . sprintf( '%010d', $end ) . '_' . md5( $window . '|' . $key );
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'off') ON DUPLICATE KEY UPDATE option_value = option_value + 1",
			$name
		)
	);
	$n = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
			$name
		)
	);
	if ( 1 === wp_rand( 1, 50 ) ) {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name < %s",
				$wpdb->esc_like( '_flx_rl_' ) . '%',
				'_flx_rl_' . sprintf( '%010d', $now )
			)
		);
	}
	return $n <= $max;
}

/** Stop caches storing a REST response. A response object is returned; anything else is sent with header(). */
function flx_rest_nocache( $response = null ) {
	$headers = array(
		'Cache-Control'             => 'no-store, no-cache, must-revalidate, max-age=0, private',
		'Pragma'                    => 'no-cache',
		'X-LiteSpeed-Cache-Control' => 'no-cache',
	);
	if ( is_object( $response ) && class_exists( 'WP_HTTP_Response', false ) && $response instanceof WP_HTTP_Response ) {
		foreach ( $headers as $name => $value ) {
			$response->header( $name, $value );
		}
		return $response;
	}
	if ( ! headers_sent() ) {
		foreach ( $headers as $name => $value ) {
			header( $name . ': ' . $value );
		}
	}
	return $response;
}

/** Seal a secret for one plugin slug. Empty input or a crypto failure returns an empty string, never plain text. */
function flx_secret_seal( string $plain, string $context ): string {
	if ( '' === $plain ) {
		return '';
	}
	try {
		$key   = sodium_crypto_generichash( 'flx-secret|' . $context . '|' . wp_salt( 'auth' ), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return 'flx1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, $key ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	} catch ( Throwable $e ) {
		unset( $e );
		return '';
	}
}

/** Open a flx1: value for $context, or a legacy prefix keyed by the old generichash material. No known prefix is returned unchanged. */
function flx_secret_open( string $stored, string $context, array $legacy = array() ): string {
	try {
		$b64 = null;
		$key = null;
		if ( 0 === strpos( $stored, 'flx1:' ) ) {
			$b64 = substr( $stored, 5 );
			$key = sodium_crypto_generichash( 'flx-secret|' . $context . '|' . wp_salt( 'auth' ), '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		} else {
			$match = '';
			foreach ( $legacy as $prefix => $material ) {
				$prefix = (string) $prefix;
				if ( '' !== $prefix && 0 === strpos( $stored, $prefix ) && strlen( $prefix ) > strlen( $match ) ) {
					$match = $prefix;
					$b64   = substr( $stored, strlen( $prefix ) );
					$key   = sodium_crypto_generichash( (string) $material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
				}
			}
			if ( '' === $match ) {
				return $stored;
			}
		}
		$raw = base64_decode( (string) $b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$n   = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
		if ( false === $raw || strlen( $raw ) <= $n ) {
			return '';
		}
		$plain = sodium_crypto_secretbox_open( substr( $raw, $n ), substr( $raw, 0, $n ), $key );
		return false === $plain ? '' : $plain;
	} catch ( Throwable $e ) {
		unset( $e );
		return '';
	}
}

if ( ! function_exists( 'flx_job_sig' ) ) {
	/** HMAC of "$action|$id" with the auth salt. Same bytes an in-flight loopback already carries. */
	function flx_job_sig( string $action, $id ): string {
		return hash_hmac( 'sha256', $action . '|' . $id, wp_salt( 'auth' ) );
	}
}

if ( ! function_exists( 'flx_job_kick' ) ) {
	/** Signed admin-ajax loopback that does not wait for the job. */
	function flx_job_kick( string $action, $id ): bool {
		$res = wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			array(
				'blocking'  => false,
				'timeout'   => 0.5,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'body'      => array(
					'action' => $action,
					'id'     => $id,
					'sig'    => flx_job_sig( $action, $id ),
				),
			)
		);
		return ! is_wp_error( $res );
	}
}

if ( ! function_exists( 'flx_job_check' ) ) {
	/** Posted job id when the signature matches, otherwise 0. The caller answers 403. */
	function flx_job_check( string $action ): int {
		$id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$sig = isset( $_POST['sig'] ) && is_string( $_POST['sig'] ) ? $_POST['sig'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $id && hash_equals( flx_job_sig( $action, $id ), $sig ) ) {
			return $id;
		}
		return 0;
	}
}

/* Shared people table and API (1.3.0): one person record for every FluxWP package. */
require_once __DIR__ . '/people.php';

/* Shared events table and API (1.4.0): one event record for every FluxWP package. */
require_once __DIR__ . '/events.php';
