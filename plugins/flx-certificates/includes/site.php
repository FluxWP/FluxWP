<?php
/**
 * Sitede etkinlikler: sertifika etkinlikleri temanın "etkinlik" sayfalarına bağlanır.
 * - Etkinlik sayfasına fidan sayacı, hedef, tarih ve konum paneli eklenir.
 * - Ana sayfada (istatistik bandından sonra) açık etkinlikler bölümü gösterilir.
 * - Kısa kodlar: [flxs_etkinlikler], [flxs_etkinlik id=".."], [flxs_fidan_sayaci etkinlik=".."]
 */

defined( 'ABSPATH' ) || exit;

/** Varsayılan başlıklar (Türkçe kaynak). Kayıtlı değer varsayılanla aynıysa çevirisi gösterilir. */
function flxs_site_default_texts() {
	return array(
		'title'    => 'Devam Eden Etkinlikler',
		'subtitle' => 'Fidanlarınız bu etkinliklerde toprakla buluşuyor. Siz de bu süreçte fidan bağışıyla bir katkıda bulunabilirsiniz.',
	);
}
function flxs_site_text( $key, $val ) {
	$def = flxs_site_default_texts();
	// Eski varsayılan başlık/açıklama kayıtlıysa yenisi gösterilir
	if ( 'title' === $key && 'Yaklaşan Etkinlikler' === $val ) {
		$val = $def['title'];
	}
	if ( 'subtitle' === $key && 'Fidanlarınız bu etkinliklerde toprakla buluşuyor. Bağışınız sayaca anında eklenir.' === $val ) {
		$val = $def['subtitle'];
	}
	if ( isset( $def[ $key ] ) && $val === $def[ $key ] ) {
		return 'title' === $key ? __( 'Ongoing Events', 'flx-certificates' ) : __( 'Your saplings are planted at these events. You too can contribute by donating saplings.', 'flx-certificates' );
	}
	return $val;
}

function flxs_site_defaults() {
	$t = flxs_site_default_texts();
	return array(
		'home_section' => 1,
		'title'        => $t['title'],
		'subtitle'     => $t['subtitle'],
		'donate_url'   => '/bagis/',
		'site_base'    => 0,
	);
}

function flxs_site_opt( $key ) {
	$o = wp_parse_args( (array) get_option( 'flxs_site', array() ), flxs_site_defaults() );
	return $o[ $key ] ?? null;
}

function flxs_site_save( $data ) {
	$d = flxs_site_defaults();
	update_option(
		'flxs_site',
		array(
			'home_section' => empty( $data['home_section'] ) ? 0 : 1,
			'title'        => sanitize_text_field( $data['title'] ?? $d['title'] ),
			'subtitle'     => sanitize_text_field( $data['subtitle'] ?? $d['subtitle'] ),
			'donate_url'   => sanitize_text_field( $data['donate_url'] ?? $d['donate_url'] ),
			'site_base'    => max( 0, (int) ( $data['site_base'] ?? 0 ) ),
		)
	);
}

function flxs_donate_url() {
	$u = (string) flxs_site_opt( 'donate_url' );
	if ( '' === $u ) {
		return '';
	}
	return 0 === strpos( $u, '/' ) ? home_url( $u ) : $u;
}

/* ---------- Varlıklar ---------- */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_register_style( 'flxs-site', FLXS_URL . 'assets/css/site.css', array(), FLXS_VERSION );
		wp_register_script( 'flxs-site', FLXS_URL . 'assets/js/site.js', array(), FLXS_VERSION, true );
		wp_localize_script(
			'flxs-site',
			'FLXS_SITE',
			array(
				'locale'   => str_replace( '_', '-', determine_locale() ),
				'mapTitle' => __( 'Event location', 'flx-certificates' ),
			)
		);
		if ( is_front_page() || is_singular( 'etkinlik' ) || is_post_type_archive( 'etkinlik' ) ) {
			wp_enqueue_style( 'flxs-site' );
			wp_enqueue_script( 'flxs-site' );
		}
	}
);
function flxs_site_assets() {
	wp_enqueue_style( 'flxs-site' );
	wp_enqueue_script( 'flxs-site' );
}

/* ---------- Yardımcılar ---------- */
function flxs_ico( $name ) {
	$p = array(
		'cal'  => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'pin'  => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
		'tree' => '<path d="M12 22v-6M7 16h10l-3.5-4.5H16L12 5l-4 6.5h2.5z"/>',
		'user' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c.7-3.4 3-5.2 6-5.2s5.3 1.8 6 5.2M16 5.2a3 3 0 0 1 0 5.6M18 14.8c1.6.6 2.6 2.3 3 5.2"/>',
		'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'go'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'sprout' => '<path d="M12 21v-8"/><path d="M12 13C12 8 8.5 5 3.5 5c0 5 3.5 8 8.5 8z" fill="currentColor" fill-opacity=".9"/><path d="M12 11c0-4.4 3-7 7.5-7 0 4.4-3 7-7.5 7z" fill="currentColor" fill-opacity=".55"/>',
	);
	return '<svg class="flxs-ico" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $p[ $name ] ?? '' ) . '</svg>';
}
/** Etkinlik tarihi, ziyaretçinin dilinde (ortak flx_date). Biçim de çevrilebilir. */
function flxs_tr_date( $ymd, $fmt = null ) {
	if ( ! $ymd ) {
		return '';
	}
	/* translators: tarih biçimi (PHP date), örn. 11 Kasım 2026, Çarşamba */
	$fmt = $fmt ? $fmt : _x( 'l, F j, Y', 'context_39c93914dff5', 'flx-certificates' );
	return flx_date( strtotime( $ymd . ' 12:00:00' ), $fmt );
}
function flxs_n( $n ) {
	return number_format_i18n( (int) $n );
}
/** "fidan toplandı / dikildi" — tekil/çoğul destekli */
function flxs_fidan_word( $n, $closed ) {
	return $closed
		/* translators: sayının yanındaki ifade, örn. "250 fidan dikildi" */
		? _n( 'sapling planted', 'saplings planted', (int) $n, 'flx-certificates' )
		/* translators: sayının yanındaki ifade, örn. "250 fidan toplandı" */
		: _n( 'sapling collected', 'saplings collected', (int) $n, 'flx-certificates' );
}

/** Konum adı; harita varsa takipli /konum/ linki olur (yeni sekmede açılır). */
function flxs_event_loc_label_html( $p ) {
	$txt = $p['loc_name'] ? $p['loc_name'] : wp_trim_words( $p['loc_addr'], 6 );
	if ( '' === $txt ) {
		$txt = __( 'Event site', 'flx-certificates' );
	}
	return $p['loc_link']
		? '<a class="flxs-ev-locname" href="' . esc_url( $p['loc_link'] ) . '" target="_blank" rel="noopener">' . esc_html( $txt ) . '</a>'
		: esc_html( $txt );
}

/** Konum bloğu (açıklanmadıysa bilgi notu) */
function flxs_event_location_html( $p, $with_map = true ) {
	if ( ! $p['has_loc'] ) {
		$when = $p['announce'] ? flxs_tr_date( $p['announce'], _x( 'F j', 'context_a43dbd63afaf', 'flx-certificates' ) ) : '';
		$txt  = $when
			/* translators: %s: tarih, örn. 1 Kasım */
			? sprintf( esc_html__( 'The planting site is announced by the authorities 10 days before the event. We will share it here around %s.', 'flx-certificates' ), '<b>' . esc_html( $when ) . '</b>' )
			: esc_html__( 'The planting site is announced by the authorities 10 days before the event. We will share it here.', 'flx-certificates' );
		return '<div class="flxs-ev-loc is-pending">' . flxs_ico( 'pin' ) . '<div><strong>' . esc_html__( 'Location coming soon', 'flx-certificates' ) . '</strong><span>' . $txt . '</span></div></div>';
	}
	$h  = '<div class="flxs-ev-loc">' . flxs_ico( 'pin' ) . '<div>';
	$h .= '<strong>' . ( $p['loc_name'] ? flxs_event_loc_label_html( $p ) : ( $p['loc_link'] ? flxs_event_loc_label_html( $p ) : esc_html__( 'Event site', 'flx-certificates' ) ) ) . '</strong>';
	$h .= $p['loc_addr'] ? '<span>' . nl2br( esc_html( $p['loc_addr'] ) ) . '</span>' : '';
	$dir = $p['loc_link'];
	$h .= $dir ? '<a class="flxs-ev-link" href="' . esc_url( $dir ) . '" target="_blank" rel="noopener">' . esc_html__( 'Get directions', 'flx-certificates' ) . ' ' . flxs_ico( 'go' ) . '</a>' : '';
	$h .= '</div></div>';
	if ( $with_map && $p['map_q'] ) {
		$src = 'https://maps.google.com/maps?q=' . rawurlencode( $p['map_q'] ) . '&z=14&output=embed';
		$h  .= '<div class="flxs-ev-map"><button type="button" class="flxs-ev-map__btn" data-map="' . esc_url( $src ) . '">' . flxs_ico( 'pin' ) . '<span>' . esc_html__( 'Show map', 'flx-certificates' ) . '</span><small>' . esc_html__( 'Loads Google Maps', 'flx-certificates' ) . '</small></button></div>';
	}
	return $h;
}

function flxs_progress_html( $p, $closed_word = false ) {
	if ( ! $p['target'] ) {
		return '';
	}
	return '<div class="flxs-ev-goal"><div class="flxs-ev-goal__bar" role="progressbar" aria-label="' . esc_attr__( 'Sapling goal progress', 'flx-certificates' ) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . (int) $p['pct'] . '"><span style="width:' . (int) $p['pct'] . '%"></span></div><div class="flxs-ev-goal__txt"><span>' . sprintf( /* translators: %s: hedef fidan sayısı */ esc_html__( 'Goal: %s saplings', 'flx-certificates' ), '<b>' . flxs_n( $p['target'] ) . '</b>' ) . '</span><span>' . esc_html( sprintf( /* translators: %s: yüzde değeri; Türkçede işaret başta (%63) */ __( '%s%%', 'flx-certificates' ), (int) $p['pct'] ) ) . '</span></div></div>';
}

/* ---------- Etkinlik paneli (etkinlik sayfasının başında) ---------- */
function flxs_event_panel_html( $e ) {
	flxs_site_assets();
	$p      = flxs_event_public( $e );
	$closed = 'closed' === $p['status'];
	$h      = '<section class="flxs-ev-panel' . ( $closed ? ' is-closed' : '' ) . '">';
	$h     .= '<div class="flxs-ev-panel__hero"><div class="flxs-ev-panel__main">';
	$h     .= '<span class="flxs-ev-pill">' . flxs_ico( 'tree' ) . ' ' . esc_html( $closed ? __( 'Completed event', 'flx-certificates' ) : __( 'Sapling counter', 'flx-certificates' ) ) . '</span>';
	$h     .= '<div class="flxs-ev-panel__count"><div class="flxs-ev-panel__num" data-count="' . (int) $p['fidan'] . '">' . flxs_n( $p['fidan'] ) . '</div><div class="flxs-ev-panel__lbl">' . esc_html( $closed ? __( 'saplings planted', 'flx-certificates' ) : flxs_fidan_word( $p['fidan'], false ) ) . ( $p['people'] ? '<small>' . sprintf( /* translators: %s: kişi sayısı */ esc_html( _n( '%s person contributed', '%s people contributed', (int) $p['people'], 'flx-certificates' ) ), flxs_n( $p['people'] ) ) . '</small>' : '' ) . '</div></div>';
	$h     .= flxs_progress_html( $p );
	$h     .= '</div><img class="flxs-ev-panel__art" src="' . esc_url( FLXS_URL . 'assets/img/fidan.svg' ) . '" alt="" width="240" height="220" aria-hidden="true"></div>';
	$h     .= '<div class="flxs-ev-facts">';
	if ( $p['date'] ) {
		$ts   = strtotime( $p['date'] . ' 12:00:00' );
		$sub  = flxs_tr_date( $p['date'], _x( 'F j, Y', 'context_a104e4060425', 'flx-certificates' ) );
		$sub .= $p['time'] ? ' · ' . sprintf( /* translators: %s: saat */ __( 'Time: %s', 'flx-certificates' ), $p['time'] ) : '';
		$h   .= '<div class="flxs-ev-fact flxs-ev-fact--date"><span class="flxs-ev-cal"><small>' . esc_html( flx_date( $ts, 'M' ) ) . '</small><b>' . esc_html( gmdate( 'j', $ts ) ) . '</b></span><div><strong>' . esc_html( flx_date( $ts, 'l' ) ) . '</strong><span>' . esc_html( $sub ) . '</span></div></div>';
	}
	$h .= flxs_event_location_html( $p );
	$h .= '</div>';
	if ( $closed ) {
		$h .= '<p class="flxs-ev-done">' . flxs_ico( 'tree' ) . ' ' . esc_html__( 'This event is complete. Thank you to everyone who contributed.', 'flx-certificates' ) . '</p>';
	} elseif ( flxs_donate_url() ) {
		$h .= '<div class="flxs-ev-cta"><a class="flxs-ev-btn" href="' . esc_url( flxs_donate_url() ) . '">' . flxs_ico( 'sprout' ) . ' ' . esc_html__( 'Donate saplings for this event', 'flx-certificates' ) . '</a><span class="flxs-ev-cta__note">' . esc_html__( 'Your donation is added to the counter instantly.', 'flx-certificates' ) . '</span></div>';
	}
	$h .= '</section>';
	return $h;
}

add_filter( 'the_content', 'flxs_event_content_panel', 5 );
function flxs_event_content_panel( $content ) {
	static $busy = false;
	if ( $busy || ! is_singular( 'etkinlik' ) || ! in_the_loop() || ! is_main_query() || get_queried_object_id() !== get_the_ID() ) {
		return $content;
	}
	$e = flxs_event_by_post( get_the_ID() );
	if ( ! $e ) {
		return $content;
	}
	$busy = true;
	$html = flxs_event_panel_html( $e );
	$busy = false;
	return $html . $content;
}

/* ---------- Etkinlik kartları ---------- */
function flxs_event_card_html( $p ) {
	$tag  = $p['url'] ? 'a' : 'div';
	$href = $p['url'] ? ' href="' . esc_url( $p['url'] ) . '"' : '';
	$d    = $p['date'] ? strtotime( $p['date'] . ' 12:00:00' ) : 0;
	$h    = '<article class="flxs-ev-card' . ( 'closed' === $p['status'] ? ' is-closed' : '' ) . ( $p['url'] ? ' has-link' : '' ) . '"><' . $tag . $href . ' class="flxs-ev-card__img" tabindex="-1" aria-hidden="true">';
	$h   .= $p['img'] ? '<img src="' . esc_url( $p['img'] ) . '" alt="" loading="lazy">' : '<span class="flxs-ev-card__ph">' . flxs_ico( 'tree' ) . '</span>';
	if ( $d ) {
		$h .= '<span class="flxs-ev-card__date"><b>' . esc_html( gmdate( 'j', $d ) ) . '</b>' . esc_html( flx_date( $d, 'M' ) ) . '</span>';
	}
	$h .= '</' . $tag . '><div class="flxs-ev-card__body">';
	$h .= '<span class="flxs-ev-pill">' . flxs_ico( 'tree' ) . ' ' . esc_html__( 'Tree planting events', 'flx-certificates' ) . '</span>';
	$h .= '<h3><' . $tag . $href . ( $p['url'] ? ' class="flxs-ev-card__link"' : '' ) . '>' . esc_html( $p['name'] ) . '</' . $tag . '></h3>';
	$h .= $p['summary'] ? '<p>' . esc_html( wp_trim_words( $p['summary'], 26 ) ) . '</p>' : '';
	$h .= '<div class="flxs-ev-card__meta">';
	$h .= $d ? '<span>' . flxs_ico( 'cal' ) . esc_html( flxs_tr_date( $p['date'], _x( 'F j, Y', 'context_a104e4060425', 'flx-certificates' ) ) . ( $p['time'] ? ' · ' . $p['time'] : '' ) ) . '</span>' : '';
	$h .= '<span>' . flxs_ico( 'pin' ) . ( $p['has_loc'] ? flxs_event_loc_label_html( $p ) : esc_html__( 'Location to be announced', 'flx-certificates' ) ) . '</span>';
	$h .= '</div>';
	$h .= '<div class="flxs-ev-card__count">' . flxs_ico( 'sprout' ) . '<b>' . flxs_n( $p['fidan'] ) . '</b> ' . esc_html( flxs_fidan_word( $p['fidan'], 'closed' === $p['status'] ) ) . '</div>';
	$h .= flxs_progress_html( $p );
	$h .= '<div class="flxs-ev-card__acts">';
	$h .= $p['url'] ? '<a class="flxs-ev-btn flxs-ev-btn--ghost" href="' . esc_url( $p['url'] ) . '">' . esc_html__( 'Details', 'flx-certificates' ) . '</a>' : '';
	$h .= ( 'closed' !== $p['status'] && flxs_donate_url() ) ? '<a class="flxs-ev-btn" href="' . esc_url( flxs_donate_url() ) . '">' . flxs_ico( 'sprout' ) . esc_html__( 'Donate saplings', 'flx-certificates' ) . '</a>' : '';
	$h .= '</div></div></article>';
	return $h;
}

function flxs_events_section_html( $atts = array() ) {
	$a      = shortcode_atts( array( 'arsiv' => '0', 'limit' => 6, 'baslik' => null, 'aciklama' => null, 'bolum' => '1' ), $atts );
	$events = flxs_get_events( 'open' );
	if ( '1' === (string) $a['arsiv'] ) {
		$events = array_merge( $events, flxs_get_events( 'closed' ) );
	}
	// Sitede yayınlanan sayfası olan ya da en azından tarih/isim bilgisi olan etkinlikler
	$events = array_slice( $events, 0, max( 1, (int) $a['limit'] ) );
	if ( ! $events ) {
		return '';
	}
	usort( $events, function ( $x, $y ) {
		if ( $x['status'] !== $y['status'] ) {
			return 'open' === $x['status'] ? -1 : 1;
		}
		return strcmp( (string) $x['event_date'], (string) $y['event_date'] ) * ( 'open' === $x['status'] ? 1 : -1 );
	} );
	flxs_site_assets();
	$title = null === $a['baslik'] ? flxs_site_text( 'title', flxs_site_opt( 'title' ) ) : $a['baslik'];
	$sub   = null === $a['aciklama'] ? flxs_site_text( 'subtitle', flxs_site_opt( 'subtitle' ) ) : $a['aciklama'];
	$h     = '<div class="flxs-ev-grid flxs-ev-grid--' . min( 3, count( $events ) ) . '">' . implode( '', array_map( function ( $e ) { return flxs_event_card_html( flxs_event_public( $e ) ); }, $events ) ) . '</div>';
	if ( '0' === (string) $a['bolum'] ) {
		return $h;
	}
	$archive = post_type_exists( 'etkinlik' ) ? get_post_type_archive_link( 'etkinlik' ) : '';
	return '<section class="flxs-ev-section alignfull"><div class="flxs-ev-section__in"><div class="flxs-ev-section__head"><div>' .
		( $title ? '<h2>' . esc_html( $title ) . '</h2>' : '' ) . ( $sub ? '<p>' . esc_html( $sub ) . '</p>' : '' ) .
		'</div>' . ( $archive ? '<a class="flxs-ev-link" href="' . esc_url( $archive ) . '">' . esc_html__( 'All events', 'flx-certificates' ) . ' ' . flxs_ico( 'go' ) . '</a>' : '' ) . '</div>' . $h . '</div></section>';
}

/* ---------- Ana sayfaya otomatik bölüm: istatistik bandından sonra ---------- */
add_filter( 'render_block', 'flxs_inject_home_section', 10, 2 );
function flxs_inject_home_section( $html, $block ) {
	static $done = false;
	if ( $done || is_admin() || ! is_front_page() || ! flxs_site_opt( 'home_section' ) ) {
		return $html;
	}
	$cls = (string) ( $block['attrs']['className'] ?? '' );
	if ( 'core/group' !== ( $block['blockName'] ?? '' ) || ! preg_match( '/(^|\s)flx-stats(\s|$)/', $cls ) ) {
		return $html;
	}
	$done = true;
	return $html . flxs_events_section_html();
}

/* ---------- Kısa kodlar ---------- */
add_shortcode( 'flxs_etkinlikler', 'flxs_events_section_html' );
add_shortcode(
	'flxs_etkinlik',
	function ( $atts ) {
		$a = shortcode_atts( array( 'id' => 0 ), $atts );
		$e = (int) $a['id'] ? flxs_get_event( (int) $a['id'] ) : ( is_singular( 'etkinlik' ) ? flxs_event_by_post( get_the_ID() ) : null );
		return $e ? flxs_event_panel_html( $e ) : '';
	}
);
add_shortcode(
	'flxs_fidan_sayaci',
	function ( $atts ) {
		$a = shortcode_atts( array( 'etkinlik' => '', 'ek' => 0 ), $atts );
		if ( '' !== (string) $a['etkinlik'] ) {
			$e = flxs_get_event( (int) $a['etkinlik'] );
			$n = $e ? flxs_event_fidan( $e )['fidan'] : 0;
		} else {
			$n = (int) flxs_site_opt( 'site_base' );
			foreach ( flxs_event_totals() as $t ) {
				$n += (int) $t['fidan'];
			}
			foreach ( flxs_get_events() as $e ) {
				$n += (int) $e['base_count'];
			}
		}
		return esc_html( flxs_n( $n + (int) $a['ek'] ) );
	}
);

/* Etkinlik arşiv/kart listelerinde (tema sorgu blokları) başlığın altına küçük fidan rozeti */
add_filter( 'render_block_core/post-title', 'flxs_event_title_badge', 10, 3 );
function flxs_event_title_badge( $html, $block, $instance ) {
	if ( is_admin() || is_singular() || empty( $instance->context['postId'] ) || 'etkinlik' !== get_post_type( $instance->context['postId'] ) ) {
		return $html;
	}
	$e = flxs_event_by_post( (int) $instance->context['postId'] );
	if ( ! $e ) {
		return $html;
	}
	flxs_site_assets();
	$f = flxs_event_fidan( $e );
	return $html . '<p class="flxs-ev-badge">' . flxs_ico( 'tree' ) . ' <b>' . flxs_n( $f['fidan'] ) . '</b> ' . esc_html( flxs_fidan_word( $f['fidan'], 'closed' === $e['status'] ) ) . '</p>';
}
