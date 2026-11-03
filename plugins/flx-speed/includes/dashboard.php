<?php
/**
 * Canlı sunucu kutuları (Heartbeat) ve Başlangıç ekranı widget'ı.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

function flxp_fmt_uptime( $s ) {
	if ( ! $s ) {
		return '—';
	}
	$d = (int) floor( $s / DAY_IN_SECONDS );
	$h = (int) floor( ( $s % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
	if ( ! $d && ! $h ) {
		/* translators: %d: dakika */
		return sprintf( __( '%d dk', 'flx-speed' ), (int) floor( $s / MINUTE_IN_SECONDS ) );
	}
	/* translators: 1: gün, 2: saat */
	return $d ? sprintf( __( '%1$d gün %2$d sa', 'flx-speed' ), $d, $h ) : sprintf( __( '%d sa', 'flx-speed' ), $h ); // phpcs:ignore
}

/**
 * Canlı ölçülerin ekrana hazır hali: anahtar → label, pct, text, sub.
 * Hem ilk çizimde hem Heartbeat cevabında kullanılır; JS yalnız yerine koyar.
 * Paylaşımlı hosting'de makinenin (tüm hesapların) CPU/RAM/diski hesabın değeri gibi gösterilmez.
 */
function flxp_live_view( $l = null ) {
	$l   = $l ? $l : flxp_server_live( 2 );
	$fmt = function ( $b ) {
		return ( null === $b || (float) $b <= 0 ) ? '—' : size_format( (float) $b, (float) $b >= 1073741824 ? 1 : 0 );
	};
	$lim   = flxp_bytes( ini_get( 'memory_limit' ) );
	$scope = $l['scope'];
	$na    = __( 'Hesap sınırı okunamıyor', 'flx-speed' );

	if ( 'account' === $scope ) {
		$cores   = number_format_i18n( $l['cores'], $l['cores'] == (int) $l['cores'] ? 0 : 1 ); // phpcs:ignore
		$cpu_sub = empty( $l['cpu_limited'] )
			/* translators: %s: çekirdek */
			? sprintf( __( '%s çekirdek, hesap sınırı yok', 'flx-speed' ), $cores )
			/* translators: %s: çekirdek */
			: sprintf( __( 'Hesap sınırı %s çekirdek', 'flx-speed' ), $cores );
	} elseif ( 'machine' === $scope ) {
		/* translators: 1: çekirdek sayısı, 2: 1/5/15 dk yük */
		$cpu_sub = $l['load'] ? sprintf( __( '%1$d çekirdek · yük %2$s', 'flx-speed' ), $l['cores'], implode( ' / ', $l['load'] ) ) : '';
	} else {
		$cpu_sub = $na;
	}

	$du = $l['disk_usage'];
	if ( ! $l['disk_shared'] ) {
		$disk = array(
			'label' => __( 'Disk', 'flx-speed' ),
			'text'  => null === $l['disk_pct'] ? '—' : $l['disk_pct'] . '%',
			/* translators: %s: boş alan */
			'sub'   => null === $l['disk_free'] ? '' : sprintf( __( '%s boş', 'flx-speed' ), $fmt( $l['disk_free'] ) ),
		);
	} elseif ( null === $du['bytes'] ) {
		$disk = array(
			'label' => __( 'Disk (hesap)', 'flx-speed' ),
			'text'  => __( 'Hesaplanıyor…', 'flx-speed' ),
			'sub'   => __( 'Hesap klasörü taranıyor', 'flx-speed' ),
		);
	} else {
		$used  = $du['bytes'] + (float) $du['db'];
		/* translators: 1: dosya boyutu, 2: veritabanı boyutu */
		$parts = sprintf( __( 'dosyalar %1$s · veritabanı %2$s', 'flx-speed' ), $fmt( $du['bytes'] ), $fmt( $du['db'] ) );
		$disk  = array(
			'label' => __( 'Disk (hesap)', 'flx-speed' ),
			'text'  => null === $l['disk_pct'] ? $fmt( $used ) : $l['disk_pct'] . '%',
			'sub'   => null === $l['disk_pct'] ? $parts : $fmt( $used ) . ' / ' . $fmt( $l['disk_total'] ) . ' · ' . $parts,
		);
	}
	$disk['pct'] = $l['disk_pct'];

	return array(
		'cpu'  => array(
			'label' => 'machine' === $scope ? __( 'İşlemci yükü', 'flx-speed' ) : __( 'İşlemci (hesap)', 'flx-speed' ),
			'pct'   => $l['load_pct'],
			'text'  => null === $l['load_pct'] ? '—' : $l['load_pct'] . '%',
			'sub'   => $cpu_sub,
		),
		'ram'  => array(
			'label' => 'machine' === $scope ? __( 'Bellek (RAM)', 'flx-speed' ) : __( 'Bellek (hesap)', 'flx-speed' ),
			'pct'   => $l['ram_pct'],
			'text'  => null === $l['ram_pct'] ? '—' : $l['ram_pct'] . '%',
			'sub'   => $l['ram_total_kb'] ? $fmt( $l['ram_used_kb'] * 1024 ) . ' / ' . $fmt( $l['ram_total_kb'] * 1024 ) : ( 'machine' === $scope ? '' : $na ),
		),
		'disk' => $disk,
		'php'  => array(
			'label' => __( 'PHP belleği (bu istek)', 'flx-speed' ),
			'pct'   => $lim > 0 ? (int) round( $l['php_mem_kb'] * 1024 / $lim * 100 ) : null,
			'text'  => $fmt( $l['php_mem_kb'] * 1024 ),
			'sub'   => 'memory_limit ' . ini_get( 'memory_limit' ),
		),
		'up'   => array(
			'label' => 'machine' === $scope ? __( 'Sunucu açık', 'flx-speed' ) : __( 'Makine açık', 'flx-speed' ),
			'pct'   => null,
			'text'  => flxp_fmt_uptime( $l['uptime_s'] ),
			'sub'   => '',
		),
	);
}

/** Kutuların altındaki açıklama: değerlerin kime ait olduğu. */
function flxp_live_note( $l ) {
	if ( 'account' === $l['scope'] ) {
		$s = __( 'İşlemci ve bellek bu hesabın (konteyner/cgroup) sınırına göre.', 'flx-speed' );
	} elseif ( 'shared' === $l['scope'] ) {
		$s = __( 'Paylaşımlı hosting: PHP, sunucudaki tüm hesapların ortak işlemci/bellek/diskini görür, bunlar sizin değerleriniz değil ve gösterilmez. Hesabın işlemci ve bellek kullanımı hosting panelinde (Hostinger: hPanel › Hesap kaynakları).', 'flx-speed' );
	} else {
		$s = '';
	}
	if ( $l['disk_shared'] && ! flxp_settings( 'disk_quota_gb' ) ) {
		$s .= ' ' . __( 'Disk yüzdesi için Ayarlar\'a hosting planınızın disk kotasını girin.', 'flx-speed' );
	}
	return trim( $s . ' ' . __( 'Kutular 15 saniyede bir yenilenir.', 'flx-speed' ) );
}

/** Canlı kutuları basar. */
function flxp_render_live( $note = true ) {
	$l = flxp_server_live( 1 );
	echo '<div class="flxp-cards flxp-live" data-flxp-live>';
	foreach ( flxp_live_view( $l ) as $k => $c ) {
		$cls = null === $c['pct'] ? '' : ( $c['pct'] >= 90 ? 'err' : ( $c['pct'] >= 70 ? 'warn' : '' ) );
		echo '<div class="flxp-card flxp-meter ' . esc_attr( $cls ) . '" data-k="' . esc_attr( $k ) . '"><span>' . esc_html( $c['label'] ) . '</span><b>' . esc_html( $c['text'] ) . '</b>';
		echo '<i class="flxp-bar"' . ( null === $c['pct'] ? ' hidden' : '' ) . '><em style="width:' . (int) min( 100, max( 0, (int) $c['pct'] ) ) . '%"></em></i>';
		echo '<small>' . esc_html( $c['sub'] ) . '</small></div>';
	}
	echo '</div>';
	if ( $note ) {
		echo '<p class="description flxp-live-note">' . esc_html( flxp_live_note( $l ) ) . '</p>';
	}
}

/** Canlı kutular için Heartbeat betiği; $interval saniye (15-120). */
function flxp_enqueue_live( $interval = 60 ) {
	wp_enqueue_style( 'flxp-admin', FLXP_URL . 'assets/admin.css', array(), FLXP_VERSION );
	wp_enqueue_script( 'flxp-live', FLXP_URL . 'assets/live.js', array( 'heartbeat' ), FLXP_VERSION, true );
	wp_add_inline_script( 'flxp-live', 'window.flxpLive=' . wp_json_encode( array( 'interval' => (int) $interval ) ) . ';', 'before' );
}

/** Heartbeat cevabı. */
add_filter(
	'heartbeat_received',
	function ( $response, $data ) {
		if ( ! empty( $data['flxp_live'] ) && flxp_can_view() ) {
			$response['flxp_live'] = flxp_live_view();
		}
		return $response;
	},
	10,
	2
);

/* Başlangıç ekranı widget'ı. */
add_action(
	'wp_dashboard_setup',
	function () {
		if ( ! flxp_can_view() ) {
			return;
		}
		wp_add_dashboard_widget( 'flxp_dashboard', __( 'Performans', 'flx-speed' ), 'flxp_dashboard_widget' );
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'index.php' === $hook && flxp_can_view() ) {
			flxp_enqueue_live( 60 );
		}
	}
);

function flxp_dashboard_widget() {
	$page = admin_url( 'admin.php?page=flx-speed' );
	echo '<div class="flxp-wrap flxp-dash">';
	flxp_render_live( false );
	$s     = FLXP_Store::summary( 24 );
	$fatal = (int) ( $s['flags']['fatal'] ?? 0 );
	$err   = (int) ( $s['flags']['error'] ?? 0 ) + (int) ( $s['flags']['5xx'] ?? 0 ) + $fatal;
	$slow  = (int) ( $s['flags']['slow'] ?? 0 );
	echo '<ul class="flxp-dash-list">';
	echo '<li><span>' . esc_html__( 'p95 süre (24 sa)', 'flx-speed' ) . '</span><b>' . esc_html( $s['recorded'] ? number_format_i18n( $s['ms_p95'] ) . ' ms' : '—' ) . '</b></li>';
	echo '<li><span>' . esc_html__( 'Hatalı istek (24 sa)', 'flx-speed' ) . '</span><b class="' . ( $err ? 'flxp-err' : '' ) . '"><a href="' . esc_url( add_query_arg( array( 'tab' => 'records', 'hours' => 24, 'flag' => $fatal ? 'fatal' : 'error' ), $page ) ) . '">' . esc_html( number_format_i18n( $err ) ) . '</a></b></li>';
	echo '<li><span>' . esc_html__( 'Yavaş istek (24 sa)', 'flx-speed' ) . '</span><b><a href="' . esc_url( add_query_arg( array( 'tab' => 'records', 'hours' => 24, 'flag' => 'slow' ), $page ) ) . '">' . esc_html( number_format_i18n( $slow ) ) . '</a></b></li>';
	echo '</ul>';
	$bad = array_filter(
		flxp_server_checks(),
		function ( $c ) {
			return in_array( $c['status'], array( 'error', 'warning' ), true );
		}
	);
	foreach ( array_slice( $bad, 0, 3 ) as $c ) {
		echo '<p class="flxp-dash-hint flxp-hint-' . esc_attr( $c['status'] ) . '">' . esc_html( $c['label'] ) . ': ' . esc_html( $c['message'] ) . '</p>';
	}
	echo '<p class="flxp-dash-foot"><a href="' . esc_url( $page ) . '">' . esc_html__( 'Ayrıntılar', 'flx-speed' ) . ' →</a></p>';
	echo '</div>';
}
