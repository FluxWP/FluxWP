<?php
/**
 * Başlangıç ekranı: karşılama + özet kutucukları ve modüller (WordPress pano kutuları: sürükle-bırak sıralanır,
 * Ekran Seçenekleri'nden açılır/kapanır, kullanıcı başına saklanır).
 *
 * Özet kutucuğu eklemek: filtre `flxa_dashboard_stats` → [ id => {label, value, meta?, url?, tone? ok|warn|error} ].
 */
defined( 'ABSPATH' ) || exit;

add_action( 'in_admin_header', 'flxa_dash_header' );
add_action( 'wp_dashboard_setup', 'flxa_dash_widgets', 99 );
add_filter( 'default_hidden_meta_boxes', 'flxa_dash_default_hidden', 10, 2 );

function flxa_on_dashboard() {
	$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return $s && 'dashboard' === $s->id;
}

function flxa_greeting() {
	$h = (int) wp_date( 'G' );
	if ( $h >= 5 && $h < 12 ) {
		return __( 'Good morning', 'flx-admin' );
	}
	if ( $h >= 12 && $h < 18 ) {
		return __( 'Good afternoon', 'flx-admin' );
	}
	if ( $h >= 18 && $h < 23 ) {
		return __( 'Good evening', 'flx-admin' );
	}
	return __( 'Good night', 'flx-admin' );
}

function flxa_dash_header() {
	if ( ! flxa_shell_on() || ! flxa_on_dashboard() || ! flxa_setting( 'dash_header' ) ) {
		return;
	}
	$u     = wp_get_current_user();
	$name  = trim( (string) $u->first_name ) ? $u->first_name : $u->display_name;
	$stats = flxa_dash_stats();
	?>
	<section class="flxa-hero" aria-label="<?php esc_attr_e( 'Summary', 'flx-admin' ); ?>">
		<div class="flxa-hero-top">
			<div class="flxa-hero-title">
				<p class="flxa-date"><?php echo esc_html( flx_date( time(), __( 'l, F j, Y', 'flx-admin' ) ) ); ?></p>
				<h1><?php echo esc_html( sprintf( /* translators: 1: greeting, 2: name */ __( '%1$s, %2$s', 'flx-admin' ), flxa_greeting(), $name ) ); ?></h1>
			</div>
			<div class="flxa-hero-actions">
				<button type="button" class="flxa-btn" data-flxa-edit aria-expanded="false"><?php echo flxa_icon( 'grid', 16 ); // phpcs:ignore ?><span><?php esc_html_e( 'Edit modules', 'flx-admin' ); ?></span></button>
			</div>
		</div>
		<?php flxa_quick_bar(); ?>
		<?php if ( $stats ) : ?>
		<div class="flxa-stats">
			<?php foreach ( $stats as $id => $s ) : ?>
				<?php $tag = empty( $s['url'] ) ? 'div' : 'a'; ?>
				<<?php echo $tag; // phpcs:ignore ?> class="flxa-stat is-<?php echo esc_attr( $s['tone'] ?? 'neutral' ); ?>" data-stat="<?php echo esc_attr( $id ); ?>"<?php echo empty( $s['url'] ) ? '' : ' href="' . esc_url( $s['url'] ) . '"'; ?>>
					<span class="flxa-stat-label"><?php echo esc_html( $s['label'] ); ?></span>
					<strong class="flxa-stat-value"<?php echo empty( $s['count_key'] ) ? '' : ' data-flxa-count-text="' . esc_attr( $s['count_key'] ) . '"'; ?>><?php echo esc_html( $s['value'] ); ?></strong>
					<?php if ( ! empty( $s['meta'] ) ) : ?>
						<span class="flxa-stat-meta"><?php echo esc_html( $s['meta'] ); ?></span>
					<?php endif; ?>
				</<?php echo $tag; // phpcs:ignore ?>>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
		<div class="flxa-install" hidden>
			<span class="flxa-install-ico"><?php echo flxa_icon( 'download', 20 ); // phpcs:ignore ?></span>
			<span class="flxa-install-text"><strong><?php esc_html_e( 'Add the dashboard to your home screen', 'flx-admin' ); ?></strong><small data-flxa-install-hint><?php esc_html_e( 'Opens full screen like an app and shows unread counts on its badge.', 'flx-admin' ); ?></small></span>
			<button type="button" class="flxa-btn flxa-btn-primary" data-flxa-install><?php esc_html_e( 'Install', 'flx-admin' ); ?></button>
			<button type="button" class="flxa-icon-btn" data-flxa-install-close aria-label="<?php esc_attr_e( 'Close', 'flx-admin' ); ?>"><?php echo flxa_icon( 'x', 16 ); // phpcs:ignore ?></button>
		</div>
	</section>
	<?php
}

/** Özet kutucukları (ayarda seçilenler, kullanılabilir olanlar). */
function flxa_dash_stats() {
	$want  = (array) flxa_setting( 'dash_stats' );
	$stats = array();
	try {
		if ( function_exists( 'flxb_table' ) && flxa_can_view_donations() ) {
			$d   = flxa_donation_totals();
			$cur = $d['currency'];
			$stats['donations_today'] = array(
				'label' => __( 'Donations today', 'flx-admin' ),
				'value' => flxa_money( $d['today'], $cur ),
				/* translators: %s: number of donations */
				'meta'  => sprintf( _n( '[[\'%sdonate\', \'tr\']]', '[[\'%sdonate\', \'tr\']]', $d['today_n'], 'flx-admin' ), number_format_i18n( $d['today_n'] ) ),
				'url'   => admin_url( 'admin.php?page=flxb-donations&from=' . wp_date( 'Y-m-d' ) ),
			);
			$stats['donations_month'] = array(
				'label' => __( 'This month', 'flx-admin' ),
				'value' => flxa_money( $d['month'], $cur ),
				/* translators: 1: donations, 2: donors */
				'meta'  => sprintf( __( '%1$s donations · %2$s donors', 'flx-admin' ), number_format_i18n( $d['month_n'] ), number_format_i18n( $d['month_donors'] ) ),
				'url'   => admin_url( 'admin.php?page=flxb-dashboard' ),
			);
			$p                = flxa_pending_donations();
			$stats['pending'] = array(
				'label'     => __( 'Awaiting approval', 'flx-admin' ),
				'value'     => number_format_i18n( $p ),
				'meta'      => __( 'Bank transfer / receipt', 'flx-admin' ),
				'url'       => admin_url( 'admin.php?page=flxb-donations&status=pending' ),
				'tone'      => $p ? 'warn' : 'neutral',
				'count_key' => 'pending',
			);
		}
		$c = flxa_counts();
		if ( isset( $c['whatsapp'] ) ) {
			$stats['whatsapp'] = array(
				'label'     => __( 'Unread messages', 'flx-admin' ),
				'value'     => number_format_i18n( $c['whatsapp'] ),
				'meta'      => __( 'Open Call Center →', 'flx-admin' ),
				'url'       => admin_url( 'admin.php?page=flx-chat' ),
				'tone'      => $c['whatsapp'] ? 'ok' : 'neutral',
				'count_key' => 'whatsapp',
			);
		}
		if ( function_exists( 'flxg_table' ) && current_user_can( 'flxg_use' ) ) {
			$open = count( flxa_my_tasks( 50 ) );
			$stats['tasks'] = array(
				'label' => __( 'My open tasks', 'flx-admin' ),
				'value' => number_format_i18n( $open ),
				'meta'  => __( 'Flux Tasks →', 'flx-admin' ),
				'url'   => admin_url( 'admin.php?page=flx-tasks#/home' ),
			);
		}
		if ( current_user_can( 'manage_options' ) && function_exists( 'flx_system_checks_run' ) && function_exists( 'flx_system_checks_summary' ) ) {
			$sum             = flxa_system_summary();
			$stats['system'] = array(
				'label' => __( 'System check', 'flx-admin' ),
				'value' => $sum['error'] ? sprintf( /* translators: %s: count */ _n( '[[\'%seven\', \'sw\']]', '[[\'%seven\', \'sw\']]', $sum['error'], 'flx-admin' ), number_format_i18n( $sum['error'] ) ) : ( $sum['warning'] ? sprintf( /* translators: %s: count */ _n( '[[\'%swarning\', \'tr\']]', '[[\'%swarning\', \'tr\']]', $sum['warning'], 'flx-admin' ), number_format_i18n( $sum['warning'] ) ) : __( 'No problems', 'flx-admin' ) ),
				/* translators: %s: count */
				'meta'  => sprintf( __( '%s checks OK', 'flx-admin' ), number_format_i18n( $sum['ok'] ) ),
				'url'   => (string) apply_filters( 'flx_system_checks_page_url', flxa_settings_url( 'system' ) ),
				'tone'  => $sum['error'] ? 'error' : ( $sum['warning'] ? 'warn' : 'ok' ),
			);
		}
	} catch ( Throwable $e ) {
		unset( $e );
	}
	$stats = (array) apply_filters( 'flxa_dashboard_stats', $stats );
	$out   = array();
	foreach ( $stats as $id => $s ) {
		$builtin = in_array( $id, array( 'donations_today', 'donations_month', 'pending', 'whatsapp', 'tasks', 'system' ), true );
		if ( $builtin && ! in_array( $id, $want, true ) ) {
			continue;
		}
		if ( is_array( $s ) && isset( $s['label'], $s['value'] ) ) {
			$out[ $id ] = $s;
		}
	}
	return $out;
}

/** Bugün / bu ay bağış toplamları (varsayılan para birimi). 2 dk önbellek. */
function flxa_donation_totals() {
	$key = 'flxa_dtot_' . wp_date( 'Ymd' );
	$c   = get_transient( $key );
	if ( is_array( $c ) ) {
		return $c;
	}
	global $wpdb;
	$t    = flxb_table( 'donations' );
	$cur  = function_exists( 'flxb_default_currency' ) ? flxb_default_currency() : 'TRY';
	$day  = flxa_local_to_utc( wp_date( 'Y-m-d' ) . ' 00:00:00' );
	$mon  = flxa_local_to_utc( wp_date( 'Y-m-01' ) . ' 00:00:00' );
	$wpdb->suppress_errors( true );
	// Adet: bir WooCommerce siparişi (sepet, birden çok satır) tek bağış sayılır. Tutarlar satır toplamı.
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN created_at >= %s THEN amount ELSE 0 END),0) AS today, SUM(CASE WHEN created_at >= %s AND wc_order_id = 0 THEN 1 ELSE 0 END) + COUNT(DISTINCT CASE WHEN created_at >= %s AND wc_order_id > 0 THEN wc_order_id END) AS today_n, COALESCE(SUM(amount),0) AS month, SUM(CASE WHEN wc_order_id = 0 THEN 1 ELSE 0 END) + COUNT(DISTINCT CASE WHEN wc_order_id > 0 THEN wc_order_id END) AS month_n, COUNT(DISTINCT NULLIF(donor_id,0)) AS donors FROM {$t} WHERE status IN ('completed','partially_refunded') AND currency = %s AND created_at >= %s", $day, $day, $day, $cur, $mon ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( ! $r && $wpdb->last_error ) {
		// wc_order_id sütunu olmayan eski Flux Donate.
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN created_at >= %s THEN amount ELSE 0 END),0) AS today, SUM(CASE WHEN created_at >= %s THEN 1 ELSE 0 END) AS today_n, COALESCE(SUM(amount),0) AS month, COUNT(*) AS month_n, COUNT(DISTINCT NULLIF(donor_id,0)) AS donors FROM {$t} WHERE status IN ('completed','partially_refunded') AND currency = %s AND created_at >= %s", $day, $day, $cur, $mon ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	$wpdb->suppress_errors( false );
	$c = array(
		'currency'     => $cur,
		'today'        => (float) ( $r['today'] ?? 0 ),
		'today_n'      => (int) ( $r['today_n'] ?? 0 ),
		'month'        => (float) ( $r['month'] ?? 0 ),
		'month_n'      => (int) ( $r['month_n'] ?? 0 ),
		'month_donors' => (int) ( $r['donors'] ?? 0 ),
	);
	set_transient( $key, $c, 120 );
	return $c;
}

function flxa_local_to_utc( $local ) {
	try {
		$d = new DateTimeImmutable( $local, wp_timezone() );
		return $d->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	} catch ( Throwable $e ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
}

/** Sistem kontrolü özeti (5 dk önbellek; "Yeniden kontrol et" kendi ekranında). */
function flxa_system_summary( $with_rows = false ) {
	$c = get_transient( 'flxa_syscheck' );
	if ( ! is_array( $c ) ) {
		// Ortak kütüphane (flx_system_checks_*) başka bir FluxWP eklentisinden gelir; eksik parçaları burada tamamla.
		$rows = function_exists( 'flx_system_checks_sorted' ) ? flx_system_checks_sorted() : ( function_exists( 'flx_system_checks_run' ) ? flx_system_checks_run() : (array) apply_filters( 'flx_system_checks', array() ) );
		if ( function_exists( 'flx_system_checks_summary' ) ) {
			$sum = flx_system_checks_summary( $rows );
		} else {
			$sum = array( 'ok' => 0, 'warning' => 0, 'error' => 0, 'info' => 0 );
			foreach ( $rows as $r ) {
				$st = (string) ( $r['status'] ?? 'info' );
				$sum[ isset( $sum[ $st ] ) ? $st : 'info' ]++;
			}
		}
		$bad  = array();
		foreach ( $rows as $r ) {
			if ( in_array( $r['status'], array( 'error', 'warning' ), true ) ) {
				$bad[] = array_intersect_key( $r, array_flip( array( 'id', 'source', 'label', 'status', 'message', 'action_url', 'action_label' ) ) );
			}
		}
		usort(
			$bad,
			function ( $a, $b ) {
				return ( 'error' === $b['status'] ) <=> ( 'error' === $a['status'] );
			}
		);
		$c = array(
			'sum' => wp_parse_args( $sum, array( 'ok' => 0, 'warning' => 0, 'error' => 0, 'info' => 0 ) ),
			'bad' => array_slice( $bad, 0, 6 ),
		);
		set_transient( 'flxa_syscheck', $c, 5 * MINUTE_IN_SECONDS );
	}
	return $with_rows ? $c : $c['sum'];
}

/** Bana atanmış açık görevler (Flux Tasks). */
function flxa_my_tasks( $limit = 6 ) {
	if ( ! function_exists( 'flxg_table' ) ) {
		return array();
	}
	static $cache = array();
	if ( isset( $cache[ $limit ] ) ) {
		return $cache[ $limit ];
	}
	global $wpdb;
	$t = flxg_table( 'tasks' );
	$a = flxg_table( 'assignees' );
	$wpdb->suppress_errors( true );
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT t.id, t.title, t.due_date FROM {$t} t INNER JOIN {$a} a ON a.task_id = t.id WHERE a.user_id = %d AND t.archived = 0 AND t.completed_at IS NULL ORDER BY (t.due_date = ''), t.due_date ASC, t.id DESC LIMIT %d", get_current_user_id(), (int) $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->suppress_errors( false );
	$cache[ $limit ] = (array) $rows;
	return $cache[ $limit ];
}

function flxa_dash_widgets() {
	if ( ! flxa_enabled() ) {
		return;
	}
	if ( function_exists( 'flxb_table' ) && flxa_can_view_donations() ) {
		wp_add_dashboard_widget( 'flxa_donations', __( 'Donations', 'flx-admin' ), 'flxa_widget_donations' );
	}
	if ( flxa_task_available() ) {
		wp_add_dashboard_widget( 'flxa_tasks', __( 'Tasks', 'flx-admin' ), 'flxa_widget_tasks', null, null, 'side' );
	}
	if ( current_user_can( 'manage_options' ) && function_exists( 'flx_system_checks_run' ) ) {
		wp_add_dashboard_widget( 'flxa_system', __( 'System check', 'flx-admin' ), 'flxa_widget_system', null, null, 'side' );
	}
	if ( ! flxa_setting( 'dash_header' ) ) {
		wp_add_dashboard_widget( 'flxa_quick', __( 'Quick actions', 'flx-admin' ), 'flxa_widget_quick' );
	}

	/* Bizim modüller önde (kullanıcı sıraladıysa onun sırası geçerli). */
	global $wp_meta_boxes;
	foreach ( array( 'normal', 'side' ) as $ctx ) {
		if ( empty( $wp_meta_boxes['dashboard'][ $ctx ]['core'] ) ) {
			continue;
		}
		$core = $wp_meta_boxes['dashboard'][ $ctx ]['core'];
		$mine = array();
		foreach ( $core as $id => $box ) {
			if ( 0 === strpos( $id, 'flxa_' ) ) {
				$mine[ $id ] = $box;
				unset( $core[ $id ] );
			}
		}
		$wp_meta_boxes['dashboard'][ $ctx ]['core'] = $mine + $core; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}
}

/** İlk açılışta WordPress haberleri ve hızlı taslak kapalı gelsin (Ekran Seçenekleri'nden açılır). */
function flxa_dash_default_hidden( $hidden, $screen ) {
	if ( flxa_enabled() && $screen && 'dashboard' === $screen->id ) {
		$hidden = array_merge( (array) $hidden, array( 'dashboard_primary', 'dashboard_quick_press' ) );
	}
	return $hidden;
}

function flxa_initials( $name ) {
	$parts = preg_split( '/\s+/u', trim( (string) $name ) );
	$i     = '';
	foreach ( array_slice( (array) $parts, 0, 2 ) as $p ) {
		$i .= function_exists( 'mb_substr' ) ? mb_substr( $p, 0, 1 ) : substr( $p, 0, 1 );
	}
	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $i ) : strtoupper( $i );
}

function flxa_widget_system() {
	$c   = flxa_system_summary( true );
	$sum = $c['sum'];
	echo '<div class="flxa-sys">';
	foreach ( array( 'ok' => __( 'OK', 'flx-admin' ), 'warning' => __( 'Warning', 'flx-admin' ), 'error' => __( 'Error', 'flx-admin' ) ) as $k => $l ) {
		echo '<span class="flxa-sys-n is-' . esc_attr( $k ) . '"><strong>' . esc_html( number_format_i18n( (int) $sum[ $k ] ) ) . '</strong><small>' . esc_html( $l ) . '</small></span>';
	}
	echo '</div>';
	if ( $c['bad'] ) {
		echo '<ul class="flxa-list flxa-sys-list">';
		foreach ( $c['bad'] as $r ) {
			echo '<li><span class="flxa-dot is-' . esc_attr( $r['status'] ) . '" aria-hidden="true"></span><span class="flxa-li-main"><strong>' . esc_html( $r['label'] ) . '</strong><small>' . esc_html( $r['source'] . ( $r['message'] ? ' · ' . wp_trim_words( $r['message'], 14 ) : '' ) ) . '</small>';
			if ( $r['action_url'] ) {
				echo '<a class="flxa-li-action" href="' . esc_url( $r['action_url'] ) . '">' . esc_html( $r['action_label'] ? $r['action_label'] : __( 'Fix', 'flx-admin' ) ) . '</a>';
			}
			echo '</span></li>';
		}
		echo '</ul>';
	}
	echo '<p class="flxa-widget-foot"><a href="' . esc_url( (string) apply_filters( 'flx_system_checks_page_url', flxa_settings_url( 'system' ) ) ) . '">' . esc_html__( 'All checks', 'flx-admin' ) . ' →</a></p>';
}

/** Hızlı işlemler: yetkiye göre hazır bağlantılar + ayardaki "Etiket|Adres" satırları. */
function flxa_quick_links() {
	$l = array();
	if ( function_exists( 'flxb_table' ) && current_user_can( (string) apply_filters( 'flxb_manage_capability', 'manage_options' ) ) ) {
		$l[] = array( __( 'Add donation', 'flx-admin' ), admin_url( 'admin.php?page=flxb-add' ), 'heart' );
	}
	if ( defined( 'FLXS_VERSION' ) && ( current_user_can( 'flxs_use' ) || current_user_can( 'manage_options' ) ) ) {
		$l[] = array( __( 'Create certificate', 'flx-admin' ), admin_url( 'admin.php?page=flxs-yeni' ), 'medal' );
	}
	if ( flxa_task_available() ) {
		$l[] = array( __( 'Create task', 'flx-admin' ), admin_url( 'admin.php?page=flx-tasks' ), 'check', 'task' );
	}
	if ( defined( 'FLXC_VERSION' ) && function_exists( 'flxc_can_use' ) && flxc_can_use() ) {
		$l[] = array( __( 'Messages', 'flx-admin' ), admin_url( 'admin.php?page=flx-chat' ), 'chat' );
	}
	if ( current_user_can( 'edit_pages' ) ) {
		$l[] = array( __( 'Add page', 'flx-admin' ), admin_url( 'post-new.php?post_type=page' ), 'file' );
	}
	if ( current_user_can( 'edit_posts' ) ) {
		$l[] = array( __( 'Write a post', 'flx-admin' ), admin_url( 'post-new.php' ), 'pen' );
	}
	if ( current_user_can( 'upload_files' ) ) {
		$l[] = array( __( 'Upload media', 'flx-admin' ), admin_url( 'media-new.php' ), 'image' );
	}
	if ( function_exists( 'flxi_send_email' ) && current_user_can( 'manage_options' ) ) {
		$l[] = array( __( 'Send email', 'flx-admin' ), admin_url( 'admin.php?page=flxi-gonder' ), 'mail' );
	}
	foreach ( preg_split( '/\r\n|\r|\n/', (string) flxa_setting( 'quick_links' ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( 2 === count( $p ) && '' !== $p[0] && '' !== $p[1] ) {
			$url = preg_match( '#^https?://#', $p[1] ) ? $p[1] : admin_url( ltrim( $p[1], '/' ) );
			$l[] = array( $p[0], $url, 'external' );
		}
	}
	return (array) apply_filters( 'flxa_quick_links', $l );
}

function flxa_widget_quick() {
	$links = flxa_quick_links();
	echo '<div class="flxa-quick">';
	foreach ( $links as $l ) {
		echo '<a class="flxa-quick-item" href="' . esc_url( $l[1] ) . '"' . ( 'task' === ( $l[3] ?? '' ) ? ' data-flxa-task' : '' ) . '>' . flxa_icon( $l[2] ?? 'dot', 18 ) . '<span>' . esc_html( $l[0] ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	if ( current_user_can( 'manage_options' ) ) {
		echo '<a class="flxa-quick-item is-add" href="' . esc_url( flxa_settings_url( 'dashboard' ) ) . '">' . flxa_icon( 'plus', 18 ) . '<span>' . esc_html__( 'Add shortcut', 'flx-admin' ) . '</span></a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</div>';
}
