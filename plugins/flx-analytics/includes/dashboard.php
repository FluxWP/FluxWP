<?php
/**
 * Başlangıç ekranı (Bakım/Dashboard) widget'ları: Canlı, Ziyaretçiler (14 gün), Trafik kaynakları, Bağış hunisi.
 * Sunucuda çizilir; assets/dashboard.js sekme açıkken Canlı'yı 5 sn'de, diğerlerini 30 sn'de bir /flxan/v1/dashboard ile yeniler.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

/** Widget kimliği → başlık ve çizici. */
function flxan_dashboard_widgets() {
	return array(
		'live'     => array( __( 'Canlı ziyaretçiler', 'flx-analytics' ), 'flxan_dw_live' ),
		'visitors' => array( __( 'Ziyaretçiler', 'flx-analytics' ), 'flxan_dw_visitors' ),
		'sources'  => array( __( 'Trafik kaynakları ve referanslar', 'flx-analytics' ), 'flxan_dw_sources' ),
		'funnel'   => array( __( 'Bağış hunisi', 'flx-analytics' ), 'flxan_dw_funnel' ),
	);
}

add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! flxan_can_view() ) {
			return;
		}
		foreach ( flxan_dashboard_widgets() as $id => $w ) {
			wp_add_dashboard_widget(
				'flxan_dw_' . $id,
				$w[0],
				function () use ( $id, $w ) {
					static $ran = false;
					if ( ! $ran ) {
						FLXAN_Aggregator::maybe_run( 120 );
						$ran = true;
					}
					echo '<div class="flxan-dw" data-w="' . esc_attr( $id ) . '">' . call_user_func( $w[1] ) . '</div>'; // phpcs:ignore -- çiziciler kaçırır
				},
				null,
				null,
				'live' === $id || 'sources' === $id ? 'normal' : 'side',
				'high'
			);
		}
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'index.php' !== $hook || ! flxan_can_view() ) {
			return;
		}
		wp_enqueue_style( 'flxan-dashboard', FLXAN_URL . 'assets/dashboard.css', array(), FLXAN_VERSION );
		wp_enqueue_script( 'flxan-dashboard', FLXAN_URL . 'assets/dashboard.js', array(), FLXAN_VERSION, true );
		wp_add_inline_script(
			'flxan-dashboard',
			'window.FLXAN_DW=' . wp_json_encode(
				array(
					'rest'  => esc_url_raw( rest_url( 'flxan/v1/dashboard' ) ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
					'live'  => 5,
					'every' => 30,
				)
			) . ';',
			'before'
		);
	}
);

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'flxan/v1',
			'/dashboard',
			array(
				'methods'             => 'GET',
				'permission_callback' => 'flxan_can_view',
				'args'                => array( 'w' => array( 'type' => 'string', 'default' => '' ) ),
				'callback'            => function ( WP_REST_Request $r ) {
					FLXAN_Aggregator::maybe_run( 5 );
					$want = array_filter( explode( ',', (string) $r['w'] ) );
					$out  = array();
					foreach ( flxan_dashboard_widgets() as $id => $w ) {
						if ( ! $want || in_array( $id, $want, true ) ) {
							$out[ $id ] = call_user_func( $w[1] );
						}
					}
					return $out;
				},
			)
		);
	}
);

/** Basit çubuk grafik (SVG). */
function flxan_svg_bars( array $items, $w = 400, $h = 90 ) {
	$n   = max( 1, count( $items ) );
	$max = 1;
	foreach ( $items as $it ) {
		$max = max( $max, (int) $it['pageviews'] );
	}
	$bw  = $w / $n;
	$out = '<svg class="flxan-mini" viewBox="0 0 ' . (int) $w . ' ' . (int) $h . '" preserveAspectRatio="none" role="img" aria-label="' . esc_attr__( 'Son 14 gün', 'flx-analytics' ) . '">';
	foreach ( array_values( $items ) as $i => $it ) {
		$ph   = round( (int) $it['pageviews'] / $max * ( $h - 4 ), 1 );
		$vh   = round( (int) $it['visitors'] / $max * ( $h - 4 ), 1 );
		$x    = round( $i * $bw + $bw * 0.15, 1 );
		$bwid = round( $bw * 0.7, 1 );
		/* translators: 1: tarih, 2: ziyaretçi, 3: görüntülenme */
		$tip  = sprintf( __( '%1$s: %2$s ziyaretçi, %3$s görüntülenme', 'flx-analytics' ), flx_date( strtotime( $it['d'] ), 'short' ), number_format_i18n( $it['visitors'] ), number_format_i18n( $it['pageviews'] ) );
		$out .= '<g><title>' . esc_html( $tip ) . '</title>';
		$out .= '<rect class="flxan-mini__pv" x="' . $x . '" y="' . ( $h - $ph ) . '" width="' . $bwid . '" height="' . $ph . '" rx="2"/>';
		$out .= '<rect class="flxan-mini__v" x="' . $x . '" y="' . ( $h - $vh ) . '" width="' . $bwid . '" height="' . $vh . '" rx="2"/></g>';
	}
	return $out . '</svg>';
}

/** Çubuklu kısa liste. */
function flxan_dw_list( $title, array $rows ) {
	if ( ! $rows ) {
		return '';
	}
	$max = 1;
	foreach ( $rows as $r ) {
		$max = max( $max, (int) $r[1] );
	}
	$h = '<h4>' . esc_html( $title ) . '</h4><ul class="flxan-dw__list">';
	foreach ( $rows as $r ) {
		$h .= '<li><span class="bar" style="width:' . round( $r[1] / $max * 100, 1 ) . '%"></span><span class="txt">' . $r[0] . '</span><b>' . esc_html( number_format_i18n( $r[1] ) ) . '</b></li>';
	}
	return $h . '</ul>';
}

function flxan_dw_link( $hash = '' ) {
	return '<p class="flxan-dw__more"><a href="' . esc_url( admin_url( 'admin.php?page=flx-analytics' . $hash ) ) . '">' . esc_html__( 'Tüm istatistikler →', 'flx-analytics' ) . '</a></p>';
}

/** Canlı: şu an (5 dk), son 30 dk, dakikalık çubuklar, sayfalar, referanslar. */
function flxan_dw_live() {
	FLXAN_Stats::set_filter();
	$rt   = FLXAN_Stats::realtime( 30 );
	$max  = max( 1, max( $rt['per_minute'] ) );
	$w    = 100 / count( $rt['per_minute'] );
	$bars = '';
	foreach ( $rt['per_minute'] as $i => $v ) {
		$hh    = round( $v / $max * 40, 2 );
		$bars .= '<rect x="' . round( $i * $w + $w * 0.12, 2 ) . '" y="' . ( 40 - $hh ) . '" width="' . round( $w * 0.76, 2 ) . '" height="' . max( $hh, $v ? 1.5 : 0.6 ) . '"' . ( $v ? '' : ' class="z"' ) . '/>';
	}
	$h  = '<div class="flxan-dw__now"><div><span class="flxan-dw__dot" aria-hidden="true"></span><b class="flxan-dw__big">' . esc_html( number_format_i18n( $rt['active'] ) ) . '</b><span>' . esc_html__( 'şu an sitede (son 5 dk)', 'flx-analytics' ) . '</span></div>';
	/* translators: %s: görüntülenme */
	$h .= '<div><b class="flxan-dw__mid">' . esc_html( number_format_i18n( $rt['visitors'] ) ) . '</b><span>' . esc_html( sprintf( __( 'son 30 dk · %s görüntülenme', 'flx-analytics' ), number_format_i18n( $rt['pageviews'] ) ) ) . '</span></div></div>';
	$h .= '<svg class="flxan-dw__bars" viewBox="0 0 100 40" preserveAspectRatio="none" aria-hidden="true">' . $bars . '</svg>';
	$h .= '<div class="flxan-dw__cols">';
	$h .= '<div>' . flxan_dw_list( __( 'Şu an bakılan sayfalar', 'flx-analytics' ), array_map( function ( $p ) { return array( esc_html( $p['title'] ), $p['pageviews'] ); }, $rt['pages'] ) ) . '</div>';
	$side = flxan_dw_list( __( 'Referans bağlantıları', 'flx-analytics' ), array_map( function ( $p ) { return array( esc_html( $p['label'] ), $p['visitors'] ); }, $rt['codes'] ) );
	$side .= flxan_dw_list( __( 'Yönlendiren siteler', 'flx-analytics' ), array_map( function ( $p ) { return array( esc_html( $p['host'] ), $p['pageviews'] ); }, $rt['referrers'] ) );
	$h .= '<div>' . $side . '</div></div>';
	if ( ! $rt['visitors'] ) {
		$h .= '<p class="flxan-dw__empty">' . esc_html__( 'Şu an kimse yok', 'flx-analytics' ) . '</p>';
	}
	return $h . flxan_dw_link();
}

/** Ziyaretçiler: bugün / dün / bu hafta + 14 günlük çubuk + bugünün sayfaları. */
function flxan_dw_visitors() {
	FLXAN_Stats::set_filter();
	$today = wp_date( 'Y-m-d' );
	$yday  = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS );
	$t     = FLXAN_Stats::totals( $today, $today );
	$y     = FLXAN_Stats::totals( $yday, $yday );
	$w7    = FLXAN_Stats::totals( wp_date( 'Y-m-d', time() - 6 * DAY_IN_SECONDS ), $today );
	$ser   = FLXAN_Stats::series( wp_date( 'Y-m-d', time() - 13 * DAY_IN_SECONDS ), $today, 'day' );
	$top   = FLXAN_Stats::pages( $today, $today, 5 );
	$h     = '<div class="flxan-dw__nums">';
	/* translators: %s: görüntülenme */
	$h .= '<div><b>' . esc_html( number_format_i18n( $t['visitors'] ) ) . '</b><span>' . esc_html( sprintf( __( 'bugün · %s görüntülenme', 'flx-analytics' ), number_format_i18n( $t['pageviews'] ) ) ) . '</span></div>';
	$h .= '<div><b>' . esc_html( number_format_i18n( $y['visitors'] ) ) . '</b><span>' . esc_html__( 'dün', 'flx-analytics' ) . '</span></div>';
	$h .= '<div><b>' . esc_html( number_format_i18n( $w7['visitors'] ) ) . '</b><span>' . esc_html__( 'son 7 gün', 'flx-analytics' ) . '</span></div></div>';
	$h .= flxan_svg_bars( $ser['items'] );
	$h .= flxan_dw_list( __( 'Bugün en çok görüntülenenler', 'flx-analytics' ), array_map( function ( $p ) { return array( '<a href="' . esc_url( $p['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $p['title'] ) . '</a>', $p['pageviews'] ); }, $top['items'] ) );
	return $h . flxan_dw_link( '#range=7d' );
}

/** Kaynaklar: son 7 gün kanallar, referans kodları, dışarıdan / üyeler. */
function flxan_dw_sources() {
	FLXAN_Stats::set_filter();
	$s    = wp_date( 'Y-m-d', time() - 6 * DAY_IN_SECONDS );
	$e    = wp_date( 'Y-m-d' );
	$ch   = FLXAN_Stats::dim( 'channel', $s, $e, 6 )['items'];
	$refs = array_filter(
		FLXAN_Stats::refs( $s, $e, 5 ),
		function ( $r ) {
			return $r['visitors'] > 0;
		}
	);
	$seg  = FLXAN_Stats::segments( $s, $e );
	$g    = 0;
	$m    = 0;
	foreach ( $seg as $x ) {
		if ( '' === $x['value'] ) {
			$g += $x['visitors'];
		} else {
			$m += $x['visitors'];
		}
	}
	$h  = '<p class="flxan-dw__sub">' . esc_html__( 'Son 7 gün', 'flx-analytics' ) . '</p>';
	$h .= '<div class="flxan-dw__split" title="' . esc_attr__( 'Dışarıdan gelenler / giriş yapanlar', 'flx-analytics' ) . '"><span style="flex:' . max( 1, $g ) . '"></span><span style="flex:' . (int) $m . '"></span></div>';
	/* translators: 1: dışarıdan, 2: giriş yapan */
	$h .= '<p class="flxan-dw__sub">' . esc_html( sprintf( __( 'Dışarıdan: %1$s · Giriş yapan: %2$s', 'flx-analytics' ), number_format_i18n( $g ), number_format_i18n( $m ) ) ) . '</p>';
	$h .= '<div class="flxan-dw__cols"><div>' . flxan_dw_list( __( 'Kanallar', 'flx-analytics' ), array_map( function ( $x ) { return array( esc_html( $x['label'] ), $x['visitors'] ); }, $ch ) ) . '</div>';
	$h .= '<div>' . flxan_dw_list(
		__( 'Referans bağlantıları', 'flx-analytics' ),
		array_map(
			function ( $x ) {
				$don = $x['donations'] ? ' <small>' . esc_html( sprintf( /* translators: %s: bağış sayısı */ __( '%s bağış', 'flx-analytics' ), number_format_i18n( $x['donations'] ) ) ) . '</small>' : '';
				return array( esc_html( $x['label'] ) . $don, $x['visitors'] );
			},
			array_values( $refs )
		)
	) . '</div></div>';
	if ( ! $ch ) {
		$h .= '<p class="flxan-dw__empty">' . esc_html__( 'Bu aralıkta veri yok.', 'flx-analytics' ) . '</p>';
	}
	return $h . flxan_dw_link( '#range=7d' );
}

/** Huni: son 7 gün adım adım. */
function flxan_dw_funnel() {
	FLXAN_Stats::set_filter();
	$f = FLXAN_Stats::funnel( 0, wp_date( 'Y-m-d', time() - 6 * DAY_IN_SECONDS ), wp_date( 'Y-m-d' ) );
	if ( empty( $f['found'] ) ) {
		return '<p class="flxan-dw__empty">' . esc_html__( 'Huni tanımlı değil.', 'flx-analytics' ) . '</p>';
	}
	/* translators: %s: huni adı */
	$h     = '<p class="flxan-dw__sub">' . esc_html( sprintf( __( '%s · son 7 gün', 'flx-analytics' ), $f['name'] ) ) . '</p><ol class="flxan-dw__funnel">';
	$first = $f['steps'][0]['count'] ?? 0;
	foreach ( $f['steps'] as $st ) {
		$pct = $first ? $st['count'] / $first * 100 : 0;
		$h  .= '<li><div class="flxan-dw__frow"><span>' . esc_html( $st['label'] ) . '</span><b>' . esc_html( number_format_i18n( $st['count'] ) ) . '</b></div><div class="flxan-dw__fbar"><span style="width:' . round( max( $pct, $st['count'] ? 1.5 : 0 ), 1 ) . '%"></span></div><small>' . esc_html( number_format_i18n( $st['rate'], 1 ) ) . '%</small></li>';
	}
	return $h . '</ol>' . flxan_dw_link( '#range=7d' );
}
