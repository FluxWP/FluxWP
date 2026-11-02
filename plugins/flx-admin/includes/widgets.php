<?php
/**
 * Başlangıç modülleri: hızlı işlem çubuğu, Görevler (filtreli, pasta grafikli), Bağışlar (dönem + dağılım),
 * hızlı görev oluşturma. Modül filtreleri kişi başı `flxa_wprefs` kullanıcı verisinde saklanır.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_flxa_widget', 'flxa_ajax_widget' );
add_action( 'wp_ajax_flxa_task_form', 'flxa_ajax_task_form' );
add_action( 'wp_ajax_flxa_task_create', 'flxa_ajax_task_create' );

function flxa_task_available() {
	return function_exists( 'flxg_table' ) && function_exists( 'flxg_visible_list_ids' ) && current_user_can( 'flxg_use' );
}

/** Modül filtreleri (kişi başı). */
function flxa_widget_prefs( $id ) {
	$defaults = array(
		'tasks'     => array(
			'scope' => 'mine',
			'list'  => 0,
			'chart' => 'status',
		),
		'donations' => array(
			'days'  => 14,
			'split' => 'type',
		),
	);
	$all = get_user_meta( get_current_user_id(), 'flxa_wprefs', true );
	$p   = is_array( $all ) && isset( $all[ $id ] ) && is_array( $all[ $id ] ) ? $all[ $id ] : array();
	return wp_parse_args( $p, $defaults[ $id ] ?? array() );
}

function flxa_widget_prefs_save( $id, array $in ) {
	$p = flxa_widget_prefs( $id );
	if ( 'tasks' === $id ) {
		$p['scope'] = in_array( $in['scope'] ?? '', array( 'mine', 'created', 'all' ), true ) ? $in['scope'] : $p['scope'];
		$p['list']  = absint( $in['list'] ?? $p['list'] );
		$p['chart'] = in_array( $in['chart'] ?? '', array( 'status', 'priority', 'list', 'person' ), true ) ? $in['chart'] : $p['chart'];
	} elseif ( 'donations' === $id ) {
		$d          = (int) ( $in['days'] ?? 0 );
		$p['days']  = in_array( $d, array( 7, 14, 30 ), true ) ? $d : $p['days'];
		$p['split'] = in_array( $in['split'] ?? '', array( 'type', 'provider', 'none' ), true ) ? $in['split'] : $p['split'];
	} else {
		return $p;
	}
	$all        = get_user_meta( get_current_user_id(), 'flxa_wprefs', true );
	$all        = is_array( $all ) ? $all : array();
	$all[ $id ] = $p;
	update_user_meta( get_current_user_id(), 'flxa_wprefs', $all );
	return $p;
}

/** Seçim kutuları satırı (değişince modül yerinde yenilenir). */
function flxa_widget_filters( array $fields ) {
	$h = '<div class="flxa-wf">';
	foreach ( $fields as $name => $f ) {
		$h .= '<label class="flxa-wf-f"><span class="screen-reader-text">' . esc_html( $f['label'] ) . '</span><select data-flxa-wf="' . esc_attr( $name ) . '">';
		foreach ( $f['options'] as $v => $l ) {
			$h .= '<option value="' . esc_attr( $v ) . '"' . selected( (string) $f['value'], (string) $v, false ) . '>' . esc_html( $l ) . '</option>';
		}
		$h .= '</select></label>';
	}
	return $h . '</div>';
}

/** Halka (pasta) grafik: [ [etiket, değer, renk], ... ]. */
function flxa_donut( array $seg, $center = '', $sub = '', $fmt = null ) {
	$total = 0;
	foreach ( $seg as $s ) {
		$total += max( 0, (float) $s[1] );
	}
	if ( $total <= 0 ) {
		return '';
	}
	$stops = array();
	$acc   = 0;
	foreach ( $seg as $s ) {
		$from    = $acc / $total * 100;
		$acc    += max( 0, (float) $s[1] );
		$to      = $acc / $total * 100;
		$stops[] = sanitize_hex_color( $s[2] ) . ' ' . round( $from, 2 ) . '% ' . round( $to, 2 ) . '%';
	}
	$h  = '<div class="flxa-donut-wrap">';
	$h .= '<div class="flxa-donut" role="img" aria-label="' . esc_attr( wp_strip_all_tags( $sub ) ) . '" style="background:conic-gradient(' . esc_attr( implode( ',', $stops ) ) . ')"><span><strong>' . esc_html( $center ) . '</strong><small>' . esc_html( $sub ) . '</small></span></div>';
	$h .= '<ul class="flxa-legend">';
	foreach ( $seg as $s ) {
		$pct = round( (float) $s[1] / $total * 100 );
		$val = $fmt ? call_user_func( $fmt, $s[1] ) : number_format_i18n( (float) $s[1] );
		$h  .= '<li><i style="background:' . esc_attr( sanitize_hex_color( $s[2] ) ) . '"></i><span>' . esc_html( $s[0] ) . '</span><b>' . esc_html( $val ) . '</b><small>%' . esc_html( number_format_i18n( $pct ) ) . '</small></li>';
	}
	return $h . '</ul></div>';
}

function flxa_palette_colors() {
	return array( '#1E7A4C', '#3B82F6', '#F59E0B', '#A855F7', '#EF4444', '#14B8A6', '#EC4899', '#84CC16', '#64748B' );
}

/* ───────────────────────── Hızlı işlem çubuğu ───────────────────────── */

function flxa_quick_bar() {
	$links = flxa_quick_links();
	if ( ! $links ) {
		return;
	}
	echo '<nav class="flxa-qa" aria-label="' . esc_attr__( 'Hızlı işlemler', 'flx-admin' ) . '">';
	foreach ( $links as $l ) {
		echo '<a class="flxa-qa-item" href="' . esc_url( $l[1] ) . '"' . ( 'task' === ( $l[3] ?? '' ) ? ' data-flxa-task' : '' ) . '><span class="flxa-qa-ico">' . flxa_icon( $l[2] ?? 'dot', 18 ) . '</span><span>' . esc_html( $l[0] ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	if ( current_user_can( 'manage_options' ) ) {
		echo '<a class="flxa-qa-item is-add" href="' . esc_url( flxa_settings_url( 'dashboard' ) ) . '" title="' . esc_attr__( 'Kısayol ekle', 'flx-admin' ) . '"><span class="flxa-qa-ico">' . flxa_icon( 'plus', 18 ) . '</span><span class="screen-reader-text">' . esc_html__( 'Kısayol ekle', 'flx-admin' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</nav>';
}

/* ───────────────────────── Görevler ───────────────────────── */

function flxa_widget_tasks() {
	echo '<div class="flxa-wbody" data-flxa-widget="tasks">';
	echo flxa_render_tasks( flxa_widget_prefs( 'tasks' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '</div>';
}

/** Görülebilir listeler: id => [ad, renk, durumlar, proje adı]. */
function flxa_task_lists( $need = 'view' ) {
	$uid   = get_current_user_id();
	$ids   = flxg_visible_list_ids( 0, $uid, $need );
	$out   = array();
	$space = function_exists( 'flxg_all_spaces' ) ? flxg_all_spaces() : array();
	foreach ( $ids as $id ) {
		$l = flxg_get_list( $id );
		if ( ! $l ) {
			continue;
		}
		$out[ $id ] = array(
			'name'     => $l['name'],
			'color'    => $l['color'],
			'statuses' => $l['statuses'],
			'space'    => isset( $space[ $l['space_id'] ] ) ? (string) $space[ $l['space_id'] ]['name'] : '',
		);
	}
	return $out;
}

function flxa_render_tasks( array $p ) {
	$lists = flxa_task_lists();
	$opts  = array( 0 => __( 'Tüm listeler', 'flx-admin' ) );
	foreach ( $lists as $id => $l ) {
		$opts[ $id ] = ( '' !== $l['space'] ? $l['space'] . ' / ' : '' ) . $l['name'];
	}
	$h = flxa_widget_filters(
		array(
			'scope' => array(
				'label'   => __( 'Kapsam', 'flx-admin' ),
				'value'   => $p['scope'],
				'options' => array(
					'mine'    => __( 'Bana atanan', 'flx-admin' ),
					'created' => __( 'Oluşturduğum', 'flx-admin' ),
					'all'     => __( 'Tüm görevler', 'flx-admin' ),
				),
			),
			'list'  => array(
				'label'   => __( 'Liste', 'flx-admin' ),
				'value'   => isset( $lists[ $p['list'] ] ) ? $p['list'] : 0,
				'options' => $opts,
			),
			'chart' => array(
				'label'   => __( 'Grafik', 'flx-admin' ),
				'value'   => $p['chart'],
				'options' => array(
					'status'   => __( 'Duruma göre', 'flx-admin' ),
					'priority' => __( 'Önceliğe göre', 'flx-admin' ),
					'list'     => __( 'Listeye göre', 'flx-admin' ),
					'person'   => __( 'Kişiye göre', 'flx-admin' ),
				),
			),
		)
	);
	if ( ! $lists ) {
		return $h . '<p class="flxa-empty">' . esc_html__( 'Görebildiğiniz görev listesi yok.', 'flx-admin' ) . '</p>';
	}

	global $wpdb;
	$uid   = get_current_user_id();
	$t     = flxg_table( 'tasks' );
	$a     = flxg_table( 'assignees' );
	$ids   = isset( $lists[ $p['list'] ] ) ? array( (int) $p['list'] ) : array_map( 'intval', array_keys( $lists ) );
	$where = 't.archived = 0 AND t.list_id IN (' . implode( ',', $ids ) . ')';
	if ( 'mine' === $p['scope'] ) {
		$where .= $wpdb->prepare( " AND EXISTS (SELECT 1 FROM {$a} x WHERE x.task_id = t.id AND x.user_id = %d)", $uid ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	} elseif ( 'created' === $p['scope'] ) {
		$where .= $wpdb->prepare( ' AND t.creator_id = %d', $uid );
	}
	$today = wp_date( 'Y-m-d' );
	$week  = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
	$wpdb->suppress_errors( true );
	$k = $wpdb->get_row( $wpdb->prepare( "SELECT SUM(CASE WHEN t.completed_at IS NULL THEN 1 ELSE 0 END) AS open_n, SUM(CASE WHEN t.completed_at IS NULL AND t.due_date <> '' AND SUBSTR(t.due_date,1,10) < %s THEN 1 ELSE 0 END) AS late_n, SUM(CASE WHEN t.completed_at IS NULL AND SUBSTR(t.due_date,1,10) = %s THEN 1 ELSE 0 END) AS today_n, SUM(CASE WHEN t.completed_at >= %s THEN 1 ELSE 0 END) AS done_n FROM {$t} t WHERE {$where}", $today, $today, $week ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	/* Dağılım (açık görevler). */
	$seg = array();
	$pal = flxa_palette_colors();
	if ( 'status' === $p['chart'] ) {
		$rows = $wpdb->get_results( "SELECT t.list_id, t.status, COUNT(*) AS n FROM {$t} t WHERE {$where} AND t.completed_at IS NULL GROUP BY t.list_id, t.status", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $r ) {
			$label = $r['status'];
			$color = '#94A3B8';
			foreach ( (array) ( $lists[ (int) $r['list_id'] ]['statuses'] ?? array() ) as $st ) {
				if ( ( $st['key'] ?? '' ) === $r['status'] ) {
					$label = $st['label'];
					$color = $st['color'];
				}
			}
			$key = mb_strtolower( $label );
			if ( ! isset( $seg[ $key ] ) ) {
				$seg[ $key ] = array( $label, 0, $color );
			}
			$seg[ $key ][1] += (int) $r['n'];
		}
	} elseif ( 'priority' === $p['chart'] ) {
		$pr   = function_exists( 'flxg_priorities' ) ? flxg_priorities() : array();
		$rows = $wpdb->get_results( "SELECT t.priority, COUNT(*) AS n FROM {$t} t WHERE {$where} AND t.completed_at IS NULL GROUP BY t.priority ORDER BY (t.priority = 0), t.priority", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $r ) {
			$i     = (int) $r['priority'];
			$seg[] = array( $pr[ $i ]['label'] ?? __( 'Önceliksiz', 'flx-admin' ), (int) $r['n'], $pr[ $i ]['color'] ?? '#CBD5E1' );
		}
	} elseif ( 'list' === $p['chart'] ) {
		$rows = $wpdb->get_results( "SELECT t.list_id, COUNT(*) AS n FROM {$t} t WHERE {$where} AND t.completed_at IS NULL GROUP BY t.list_id ORDER BY n DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $i => $r ) {
			$l     = $lists[ (int) $r['list_id'] ] ?? array( 'name' => '#' . $r['list_id'], 'color' => $pal[ $i % count( $pal ) ] );
			$seg[] = array( $l['name'], (int) $r['n'], $l['color'] );
		}
	} else {
		$rows = $wpdb->get_results( "SELECT COALESCE(x.user_id, 0) AS uid, COUNT(*) AS n FROM {$t} t LEFT JOIN {$a} x ON x.task_id = t.id WHERE {$where} AND t.completed_at IS NULL GROUP BY COALESCE(x.user_id, 0) ORDER BY n DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( (array) $rows as $i => $r ) {
			$u     = (int) $r['uid'] ? get_userdata( (int) $r['uid'] ) : null;
			$seg[] = array( $u ? $u->display_name : __( 'Atanmamış', 'flx-admin' ), (int) $r['n'], $u ? $pal[ $i % count( $pal ) ] : '#CBD5E1' );
		}
	}
	$seg = array_values( $seg );
	if ( count( $seg ) > 7 ) {
		$rest = array_slice( $seg, 6 );
		$seg  = array_slice( $seg, 0, 6 );
		$seg[] = array( __( 'Diğer', 'flx-admin' ), array_sum( wp_list_pluck( $rest, 1 ) ), '#94A3B8' );
	}

	$rows = $wpdb->get_results( "SELECT t.id, t.title, t.due_date, t.priority, t.list_id FROM {$t} t WHERE {$where} AND t.completed_at IS NULL ORDER BY (t.due_date = ''), t.due_date ASC, t.id DESC LIMIT 6", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->suppress_errors( false );

	$base = admin_url( 'admin.php?page=flx-tasks' );
	$kpi  = array(
		array( __( 'Açık', 'flx-admin' ), (int) ( $k['open_n'] ?? 0 ), '' ),
		array( __( 'Geciken', 'flx-admin' ), (int) ( $k['late_n'] ?? 0 ), 'is-error' ),
		array( __( 'Bugün', 'flx-admin' ), (int) ( $k['today_n'] ?? 0 ), 'is-warn' ),
		array( __( 'Son 7 günde biten', 'flx-admin' ), (int) ( $k['done_n'] ?? 0 ), 'is-ok' ),
	);
	$h .= '<div class="flxa-kpis">';
	foreach ( $kpi as $x ) {
		$h .= '<span class="flxa-kpi ' . esc_attr( $x[1] ? $x[2] : '' ) . '"><strong>' . esc_html( number_format_i18n( $x[1] ) ) . '</strong><small>' . esc_html( $x[0] ) . '</small></span>';
	}
	$h .= '</div>';
	$h .= $seg ? flxa_donut( $seg, number_format_i18n( (int) ( $k['open_n'] ?? 0 ) ), __( 'açık görev', 'flx-admin' ) ) : '';

	if ( $rows ) {
		$pr = function_exists( 'flxg_priorities' ) ? flxg_priorities() : array();
		$h .= '<ul class="flxa-list">';
		foreach ( $rows as $r ) {
			$due  = substr( (string) $r['due_date'], 0, 10 );
			$late = $due && $due < $today;
			$lbl  = $due ? ( $due === $today ? __( 'Bugün', 'flx-admin' ) : wp_date( 'j M', strtotime( $due . ' 12:00:00' ) ) ) : '';
			$url  = function_exists( 'flxg_app_url' ) ? flxg_app_url( (int) $r['id'] ) : $base . '#/task/' . (int) $r['id'];
			$col  = $pr[ (int) $r['priority'] ]['color'] ?? '';
			$h   .= '<li><a href="' . esc_url( $url ) . '"><span class="flxa-check"' . ( $col ? ' style="border-color:' . esc_attr( $col ) . '"' : '' ) . ' aria-hidden="true"></span><span class="flxa-li-main"><strong>' . esc_html( $r['title'] ) . '</strong><small>' . esc_html( $lists[ (int) $r['list_id'] ]['name'] ?? '' ) . '</small></span>' . ( $lbl ? '<span class="flxa-pill ' . ( $late ? 'is-error' : ( $due === $today ? 'is-warn' : 'is-neutral' ) ) . '">' . esc_html( $lbl ) . '</span>' : '' ) . '</a></li>';
		}
		$h .= '</ul>';
	} else {
		$h .= '<p class="flxa-empty">' . esc_html__( 'Açık görev yok.', 'flx-admin' ) . '</p>';
	}
	$h .= '<p class="flxa-widget-foot"><a href="#" data-flxa-task>' . esc_html__( '+ Görev oluştur', 'flx-admin' ) . '</a><a href="' . esc_url( $base . '#/home' ) . '">' . esc_html__( 'Flux Tasks\'i aç', 'flx-admin' ) . ' →</a></p>';
	return $h;
}

/* ───────────────────────── Bağışlar ───────────────────────── */

function flxa_widget_donations() {
	echo '<div class="flxa-wbody" data-flxa-widget="donations">';
	echo flxa_render_donations( flxa_widget_prefs( 'donations' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '</div>';
}

function flxa_render_donations( array $p ) {
	global $wpdb;
	$days = (int) $p['days'];
	$h    = flxa_widget_filters(
		array(
			'days'  => array(
				'label'   => __( 'Dönem', 'flx-admin' ),
				'value'   => $days,
				'options' => array(
					7  => __( 'Son 7 gün', 'flx-admin' ),
					14 => __( 'Son 14 gün', 'flx-admin' ),
					30 => __( 'Son 30 gün', 'flx-admin' ),
				),
			),
			'split' => array(
				'label'   => __( 'Dağılım', 'flx-admin' ),
				'value'   => $p['split'],
				'options' => array(
					'type'     => __( 'Bağış türüne göre', 'flx-admin' ),
					'provider' => __( 'Ödeme yöntemine göre', 'flx-admin' ),
					'none'     => __( 'Dağılım yok', 'flx-admin' ),
				),
			),
		)
	);
	$t     = flxb_table( 'donations' );
	$cur   = function_exists( 'flxb_default_currency' ) ? flxb_default_currency() : 'TRY';
	$off   = (int) wp_timezone()->getOffset( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) );
	$from  = flxa_local_to_utc( wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS ) . ' 00:00:00' );
	$valid = "status IN ('completed','partially_refunded')";
	$wpdb->suppress_errors( true );
	$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(DATE_ADD(created_at, INTERVAL %d SECOND)) AS d, SUM(amount) AS s FROM {$t} WHERE {$valid} AND currency = %s AND created_at >= %s GROUP BY d", $off, $cur, $from ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	// Son bağışlar: bir WooCommerce siparişinin satırları (sepet) tek satır, tutar toplam.
	$recent = $wpdb->get_results( "SELECT MAX(x.id) AS id, SUM(x.amount) AS amount, MAX(x.currency) AS currency, MAX(x.status) AS status, MAX(x.provider) AS provider, MAX(x.created_at) AS created_at, MAX(d.name) AS name, COUNT(*) AS line_n FROM {$t} x LEFT JOIN " . flxb_table( 'donors' ) . ' d ON d.id = x.donor_id GROUP BY CASE WHEN x.wc_order_id > 0 THEN x.wc_order_id ELSE 0 END, CASE WHEN x.wc_order_id > 0 THEN 0 ELSE x.id END ORDER BY MAX(x.id) DESC LIMIT 5', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( ! $recent && $wpdb->last_error ) {
		$recent = $wpdb->get_results( "SELECT x.id, x.amount, x.currency, x.status, x.provider, x.created_at, d.name, 1 AS line_n FROM {$t} x LEFT JOIN " . flxb_table( 'donors' ) . ' d ON d.id = x.donor_id ORDER BY x.id DESC LIMIT 5', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	$split  = array();
	if ( 'type' === $p['split'] ) {
		$split = $wpdb->get_results( $wpdb->prepare( "SELECT COALESCE(NULLIF(y.title,''), '') AS k, SUM(x.amount) AS s FROM {$t} x LEFT JOIN " . flxb_table( 'types' ) . " y ON y.id = x.type_id WHERE x.{$valid} AND x.currency = %s AND x.created_at >= %s GROUP BY k ORDER BY s DESC", $cur, $from ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	} elseif ( 'provider' === $p['split'] ) {
		$split = $wpdb->get_results( $wpdb->prepare( "SELECT provider AS k, SUM(amount) AS s FROM {$t} WHERE {$valid} AND currency = %s AND created_at >= %s GROUP BY provider ORDER BY s DESC", $cur, $from ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	$wpdb->suppress_errors( false );

	$by = array();
	foreach ( (array) $rows as $r ) {
		$by[ $r['d'] ] = (float) $r['s'];
	}
	$list  = array();
	$max   = 0;
	$total = 0;
	for ( $i = $days - 1; $i >= 0; $i-- ) {
		$ts     = time() - $i * DAY_IN_SECONDS;
		$k      = wp_date( 'Y-m-d', $ts );
		$v      = $by[ $k ] ?? 0;
		$max    = max( $max, $v );
		$total += $v;
		$list[] = array( $k, $v, wp_date( 'j M', $ts ) );
	}
	$h .= '<div class="flxa-dsum"><strong>' . esc_html( flxa_money( $total, $cur ) ) . '</strong><small>' . esc_html( sprintf( /* translators: %d: days */ __( 'son %d gün', 'flx-admin' ), $days ) ) . '</small></div>';
	$h .= '<div class="flxa-chart" style="--n:' . (int) $days . '" role="img" aria-label="' . esc_attr__( 'Günlük bağış toplamları', 'flx-admin' ) . '">';
	foreach ( $list as $i => $d ) {
		$bh = $max > 0 ? max( 3, round( $d[1] / $max * 100 ) ) : 3;
		$h .= '<span class="flxa-bar' . ( $days - 1 === $i ? ' is-today' : '' ) . '" style="--h:' . (int) $bh . '%" title="' . esc_attr( $d[2] . ' · ' . flxa_money( $d[1], $cur ) ) . '"></span>';
	}
	$h .= '</div><div class="flxa-chart-axis"><span>' . esc_html( $list[0][2] ) . '</span><span>' . esc_html( $list[ $days - 1 ][2] ) . '</span></div>';

	if ( $split ) {
		$pal = flxa_palette_colors();
		$seg = array();
		foreach ( $split as $i => $r ) {
			$label = 'provider' === $p['split'] ? ( function_exists( 'flxb_provider_label' ) ? flxb_provider_label( $r['k'] ) : $r['k'] ) : ( '' !== $r['k'] ? $r['k'] : __( 'Türsüz', 'flx-admin' ) );
			$seg[] = array( '' !== $label ? $label : '—', (float) $r['s'], $pal[ $i % count( $pal ) ] );
		}
		if ( count( $seg ) > 7 ) {
			$rest  = array_slice( $seg, 6 );
			$seg   = array_slice( $seg, 0, 6 );
			$seg[] = array( __( 'Diğer', 'flx-admin' ), array_sum( wp_list_pluck( $rest, 1 ) ), '#94A3B8' );
		}
		$h .= flxa_donut(
			$seg,
			flxa_money( $total, $cur ),
			'type' === $p['split'] ? __( 'türlere göre', 'flx-admin' ) : __( 'yöntemlere göre', 'flx-admin' ),
			function ( $v ) use ( $cur ) {
				return flxa_money( $v, $cur );
			}
		);
	}

	if ( $recent ) {
		$h .= '<ul class="flxa-list">';
		foreach ( $recent as $r ) {
			$name = '' !== (string) $r['name'] ? $r['name'] : __( 'Anonim', 'flx-admin' );
			$st   = function_exists( 'flxb_status_label' ) ? flxb_status_label( $r['status'] ) : array( '', $r['status'] );
			$tone = 'completed' === $r['status'] ? 'ok' : ( 'pending' === $r['status'] ? 'warn' : 'neutral' );
			$url  = admin_url( 'admin.php?page=flxb-donations&donation=' . (int) $r['id'] );
			$ago  = human_time_diff( strtotime( $r['created_at'] . ' UTC' ), time() );
			if ( (int) $r['line_n'] > 1 ) {
				/* translators: %d: number of lines in one order */
				$ago .= ' · ' . sprintf( __( '%d kalem', 'flx-admin' ), (int) $r['line_n'] );
			}
			$h   .= '<li><a href="' . esc_url( $url ) . '"><span class="flxa-ava">' . esc_html( flxa_initials( $name ) ) . '</span><span class="flxa-li-main"><strong>' . esc_html( $name ) . '</strong><small>' . esc_html( $ago ) . '</small></span><span class="flxa-pill is-' . esc_attr( $tone ) . '">' . esc_html( (string) ( $st[1] ?? $r['status'] ) ) . '</span><b>' . esc_html( flxa_money( $r['amount'], $r['currency'] ) ) . '</b></a></li>';
		}
		$h .= '</ul>';
	} else {
		$h .= '<p class="flxa-empty">' . esc_html__( 'Henüz bağış yok.', 'flx-admin' ) . '</p>';
	}
	$h .= '<p class="flxa-widget-foot"><a href="' . esc_url( admin_url( 'admin.php?page=flxb-donations' ) ) . '">' . esc_html__( 'Tüm bağışlar', 'flx-admin' ) . ' →</a></p>';
	return $h;
}

/* ───────────────────────── AJAX ───────────────────────── */

/** Modül filtresi değişti: kaydet ve modülü yeniden çiz. */
function flxa_ajax_widget() {
	check_ajax_referer( 'flxa', 'nonce' );
	$id    = sanitize_key( wp_unslash( $_POST['widget'] ?? '' ) );
	$prefs = json_decode( (string) wp_unslash( $_POST['prefs'] ?? '{}' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$prefs = is_array( $prefs ) ? $prefs : array();
	if ( 'tasks' === $id && flxa_task_available() ) {
		wp_send_json_success( array( 'html' => flxa_render_tasks( flxa_widget_prefs_save( 'tasks', $prefs ) ) ) );
	}
	if ( 'donations' === $id && function_exists( 'flxb_table' ) && flxa_can_view_donations() ) {
		wp_send_json_success( array( 'html' => flxa_render_donations( flxa_widget_prefs_save( 'donations', $prefs ) ) ) );
	}
	wp_send_json_error( null, 403 );
}

/** Hızlı görev formu: düzenlenebilir listeler ve öncelikler. */
function flxa_ajax_task_form() {
	check_ajax_referer( 'flxa', 'nonce' );
	if ( ! flxa_task_available() ) {
		wp_send_json_error( null, 403 );
	}
	$lists = array();
	foreach ( flxa_task_lists( 'edit' ) as $id => $l ) {
		$lists[] = array(
			'id'    => (int) $id,
			'name'  => $l['name'],
			'space' => $l['space'],
		);
	}
	$pr = array();
	foreach ( ( function_exists( 'flxg_priorities' ) ? flxg_priorities() : array() ) as $k => $v ) {
		$pr[] = array(
			'id'    => (int) $k,
			'label' => $v['label'],
		);
	}
	wp_send_json_success(
		array(
			'lists'      => $lists,
			'priorities' => $pr,
			'last'       => (int) get_user_meta( get_current_user_id(), 'flxa_task_list', true ),
		)
	);
}

function flxa_ajax_task_create() {
	check_ajax_referer( 'flxa', 'nonce' );
	if ( ! flxa_task_available() || ! function_exists( 'flxg_create_task' ) ) {
		wp_send_json_error( array( 'message' => __( 'Flux Tasks kullanılamıyor.', 'flx-admin' ) ), 403 );
	}
	$uid  = get_current_user_id();
	$list = flxg_get_list( absint( $_POST['list'] ?? 0 ) );
	if ( ! $list || ! flxg_can_list( $list, 'edit' ) ) {
		wp_send_json_error( array( 'message' => __( 'Bu listeye görev ekleme yetkiniz yok.', 'flx-admin' ) ), 403 );
	}
	$d = array(
		'list_id'  => (int) $list['id'],
		'title'    => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
		'due'      => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $_POST['due'] ?? '' ) ) ? (string) $_POST['due'] : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		'priority' => max( 0, min( 4, (int) ( $_POST['priority'] ?? 0 ) ) ),
	);
	if ( ! empty( $_POST['mine'] ) ) {
		$d['assignees'] = array( $uid );
	}
	$id = flxg_create_task( $d, $uid );
	if ( is_wp_error( $id ) ) {
		wp_send_json_error( array( 'message' => $id->get_error_message() ), 400 );
	}
	update_user_meta( $uid, 'flxa_task_list', (int) $list['id'] );
	wp_send_json_success(
		array(
			'id'  => (int) $id,
			'url' => function_exists( 'flxg_app_url' ) ? flxg_app_url( (int) $id ) : admin_url( 'admin.php?page=flx-tasks#/task/' . (int) $id ),
		)
	);
}
