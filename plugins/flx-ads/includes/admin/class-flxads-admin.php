<?php
/**
 * Flux Ads: yönetim ekranı. Sekmeler: Rapor, Gönderim kaydı, Reklam bağlantısı (UTM oluşturucu), Ayarlar.
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

final class FLXADS_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_post_flxads_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_flxads_retry', array( __CLASS__, 'retry' ) );
		add_action( 'admin_post_flxads_spend', array( __CLASS__, 'spend_action' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_icon' ) );
	}

	public static function menu() {
		add_menu_page( 'Flux Ads', 'Flux Ads', 'flxads_view', 'flx-ads', array( __CLASS__, 'render' ), 'none', 58.6 );
	}

	public static function menu_icon() {
		echo '<style>#toplevel_page_flx-ads .wp-menu-image{background:url(' . esc_url( FLXADS_URL . 'assets/icon.svg' ) . ') center/20px 20px no-repeat}</style>';
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_flx-ads' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'flxads-admin', FLXADS_URL . 'assets/admin.css', array(), FLXADS_VERSION );
		wp_enqueue_script( 'flxads-admin', FLXADS_URL . 'assets/admin.js', array(), FLXADS_VERSION, true );
		wp_localize_script(
			'flxads-admin',
			'flxAdsAdmin',
			array(
				'copied' => __( 'Copied', 'flx-ads' ),
				'copy'   => __( 'Copy', 'flx-ads' ),
				'home'   => home_url( '/' ),
			)
		);
	}

	private static function tabs() {
		$t = array(
			'report' => __( 'Report', 'flx-ads' ),
			'spend'  => __( 'Ad spend', 'flx-ads' ),
			'log'    => __( 'Send log', 'flx-ads' ),
			'links'  => __( 'Ad link', 'flx-ads' ),
		);
		if ( flxads_can( true ) ) {
			$t['settings'] = __( 'Settings', 'flx-ads' );
		}
		return $t;
	}

	public static function render() {
		if ( ! flxads_can() ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'flx-ads' ) );
		}
		/* Cron'a güvenilmez: ekran açılınca vadesi gelmiş gönderimler denenir. */
		if ( (int) get_option( 'flxads_heartbeat' ) < time() - 10 * MINUTE_IN_SECONDS ) {
			FLXADS_Send::run_queue( 10 );
		}
		FLXADS_Spend::maybe_sync_later();
		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'report'; // phpcs:ignore WordPress.Security.NonceVerification
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'report';
		echo '<div class="wrap flxads">';
		echo '<h1 class="flxads-title">' . esc_html__( 'Ad conversions', 'flx-ads' ) . '</h1>';
		echo '<p class="flxads-lead">' . esc_html__( 'Which ad, campaign and ad text brought which donation. Paid donations and orders only; bank transfers count when approved.', 'flx-ads' ) . '</p>';
		self::notice();
		echo '<nav class="flxads-tabs" aria-label="' . esc_attr__( 'Sections', 'flx-ads' ) . '">';
		foreach ( $tabs as $k => $label ) {
			echo '<a href="' . esc_url( flxads_admin_url( $k ) ) . '" class="flxads-tab' . ( $k === $tab ? ' is-on' : '' ) . '"' . ( $k === $tab ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
		call_user_func( array( __CLASS__, 'tab_' . $tab ) );
		echo '</div>';
	}

	private static function notice() {
		$m = isset( $_GET['flxads_msg'] ) ? sanitize_key( wp_unslash( $_GET['flxads_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'saved' === $m ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'flx-ads' ) . '</p></div>';
		} elseif ( 'tested' === $m ) {
			$p = isset( $_GET['p'] ) ? sanitize_key( wp_unslash( $_GET['p'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$t = ( (array) get_option( 'flxads_tests', array() ) )[ $p ] ?? null;
			if ( $t ) {
				echo '<div class="notice notice-' . ( $t['ok'] ? 'success' : 'error' ) . ' is-dismissible"><p><strong>' . esc_html( flxads_platforms()[ $p ] ?? $p ) . ':</strong> ' . esc_html( $t['message'] ) . '</p></div>';
			}
		} elseif ( 'synced' === $m ) {
			$st = FLXADS_Spend::status( 'meta' );
			/* translators: %d: rows */
			echo '<div class="notice notice-' . ( ! empty( $st['ok'] ) ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( ! empty( $st['ok'] ) ? sprintf( __( 'Meta spend updated: %d rows.', 'flx-ads' ), (int) ( $st['rows'] ?? 0 ) ) : __( 'Meta spend could not be read:', 'flx-ads' ) . ' ' . ( $st['message'] ?? '' ) ) . '</p></div>';
		} elseif ( 'spend_added' === $m ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Spend added.', 'flx-ads' ) . '</p></div>';
		} elseif ( 'spend_bad' === $m ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Enter an amount greater than zero.', 'flx-ads' ) . '</p></div>';
		} elseif ( 'retried' === $m ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Sent again. The row shows the result.', 'flx-ads' ) . '</p></div>';
		}
	}

	/* ------------------------------------------------------------ Rapor */

	private static function filters() {
		$g = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
		$p = isset( $g['range'] ) ? sanitize_key( $g['range'] ) : '30';
		$f = array(
			'kind'    => isset( $g['kind'] ) && in_array( $g['kind'], array( 'all', 'donations', 'orders' ), true ) ? $g['kind'] : 'all',
			'channel' => isset( $g['channel'] ) ? sanitize_text_field( $g['channel'] ) : '',
			'by'      => isset( $g['by'] ) && isset( FLXADS_Report::dimensions()[ $g['by'] ] ) ? $g['by'] : 'channel',
			'range'   => $p,
		);
		$days = array( '7' => 7, '30' => 30, '90' => 90, '365' => 365 );
		if ( 'custom' === $p ) {
			$f['from'] = isset( $g['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $g['from'] ) ? $g['from'] : '';
			$f['to']   = isset( $g['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $g['to'] ) ? $g['to'] : '';
		} else {
			$n         = $days[ $p ] ?? 30;
			$f['from'] = wp_date( 'Y-m-d', time() - ( $n - 1 ) * DAY_IN_SECONDS );
			$f['to']   = wp_date( 'Y-m-d' );
		}
		return $f;
	}

	private static function tab_report() {
		$f = self::filters();
		$r = FLXADS_Report::grouped( $f['by'], $f );
		$t = $r['totals'];
		echo '<form class="flxads-filters" method="get"><input type="hidden" name="page" value="flx-ads"><input type="hidden" name="tab" value="report">';
		echo '<label><span>' . esc_html__( 'Period', 'flx-ads' ) . '</span><select name="range" data-flxads-range>';
		foreach ( array( '7' => __( 'Last 7 days', 'flx-ads' ), '30' => __( 'Last 30 days', 'flx-ads' ), '90' => __( 'Last 90 days', 'flx-ads' ), '365' => __( 'Last 12 months', 'flx-ads' ), 'custom' => __( 'Custom', 'flx-ads' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $f['range'], (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label class="flxads-custom"' . ( 'custom' === $f['range'] ? '' : ' hidden' ) . '><span>' . esc_html__( 'From', 'flx-ads' ) . '</span><input type="date" name="from" value="' . esc_attr( $r['from'] ) . '"></label>';
		echo '<label class="flxads-custom"' . ( 'custom' === $f['range'] ? '' : ' hidden' ) . '><span>' . esc_html__( 'To', 'flx-ads' ) . '</span><input type="date" name="to" value="' . esc_attr( $r['to'] ) . '"></label>';
		echo '<label><span>' . esc_html__( 'Show', 'flx-ads' ) . '</span><select name="kind">';
		foreach ( array( 'all' => __( 'Donations and orders', 'flx-ads' ), 'donations' => __( 'Donations', 'flx-ads' ), 'orders' => __( 'Shop orders', 'flx-ads' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $f['kind'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label><span>' . esc_html__( 'Group by', 'flx-ads' ) . '</span><select name="by">';
		foreach ( FLXADS_Report::dimensions() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $f['by'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		if ( '' !== $f['channel'] ) {
			echo '<input type="hidden" name="channel" value="' . esc_attr( $f['channel'] ) . '">';
		}
		echo '<button class="button">' . esc_html__( 'Apply', 'flx-ads' ) . '</button></form>';

		if ( '' !== $f['channel'] ) {
			echo '<p class="flxads-chip">' . esc_html( flxads_channel_label( '-' === $f['channel'] ? '' : $f['channel'] ) ) . ' <a href="' . esc_url( remove_query_arg( 'channel' ) ) . '" aria-label="' . esc_attr__( 'Clear filter', 'flx-ads' ) . '">×</a></p>';
		}

		$base = $r['base'];
		echo '<div class="flxads-kpis">';
		self::kpi( __( 'Brought by ads', 'flx-ads' ), flxads_money( $t['ads_amount'], $base ), $t['amount'] > 0 ? sprintf( /* translators: %s: percent */ __( '%s%% of all income', 'flx-ads' ), number_format_i18n( 100 * $t['ads_amount'] / $t['amount'], 1 ) ) : '' );
		/* translators: %s: count */
		self::kpi( __( 'Payments from ads', 'flx-ads' ), number_format_i18n( $t['ads_count'] ), sprintf( __( 'of %s in total', 'flx-ads' ), number_format_i18n( $t['count'] ) ) );
		self::kpi( __( 'Total income', 'flx-ads' ), flxads_money( $t['amount'], $base ), flxads_date( $r['from'], 'date' ) . ' – ' . flxads_date( $r['to'], 'date' ) );
		self::kpi( __( 'New donors and customers', 'flx-ads' ), number_format_i18n( $t['new'] ), __( 'first paid order', 'flx-ads' ) );
		echo '</div>';
		if ( $t['spend'] > 0 ) {
			echo '<div class="flxads-kpis flxads-kpis--spend">';
			self::kpi( __( 'Ad spend', 'flx-ads' ), flxads_money( $t['spend'], $base ), $t['clicks'] ? sprintf( /* translators: %s: clicks */ __( '%s link clicks', 'flx-ads' ), number_format_i18n( $t['clicks'] ) ) : '' );
			self::kpi( __( 'Return on ad spend (ROAS)', 'flx-ads' ), self::roas( $t['roas'] ), sprintf( /* translators: %s: income */ __( '%s income from these ads', 'flx-ads' ), flxads_money( $t['spend_income'], $base ) ) );
			self::kpi( __( 'Cost per payment', 'flx-ads' ), null === $t['cpa'] ? '—' : flxads_money( $t['cpa'], $base ), sprintf( /* translators: %s: count */ __( '%s payments', 'flx-ads' ), number_format_i18n( $t['spend_count'] ) ) );
			self::kpi( __( 'Cost per new donor', 'flx-ads' ), null === $t['cpn'] ? '—' : flxads_money( $t['cpn'], $base ), sprintf( /* translators: %s: count */ __( '%s new', 'flx-ads' ), number_format_i18n( $t['spend_new'] ) ) );
			echo '</div>';
		}
		if ( $t['mixed'] ) {
			echo '<p class="flxads-muted">' . esc_html__( 'Some payments are in another currency and no exchange rate was found; their amount is shown per currency in the row but not added to the totals. Install Flux CRM for currency conversion.', 'flx-ads' ) . '</p>';
		}

		$sc = $r['spend'] && in_array( $f['by'], array( 'channel', 'campaign', 'content' ), true );
		echo '<div class="flxads-card"><table class="flxads-table"><thead><tr><th>' . esc_html( FLXADS_Report::dimensions()[ $f['by'] ] ) . '</th><th class="num">' . esc_html__( 'Payments', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Amount', 'flx-ads' ) . '</th>' . ( $sc ? '<th class="num">' . esc_html__( 'Spend', 'flx-ads' ) . '</th><th class="num">ROAS</th>' : '' ) . '<th class="num">' . esc_html__( 'New', 'flx-ads' ) . '</th><th class="share">' . esc_html__( 'Share', 'flx-ads' ) . '</th></tr></thead><tbody>';
		if ( ! $r['rows'] ) {
			echo '<tr><td colspan="' . ( $sc ? 7 : 5 ) . '" class="flxads-empty">' . esc_html__( 'No paid donation or order in this period yet. Use the Ad link tab to tag your ads.', 'flx-ads' ) . '</td></tr>';
		}
		foreach ( $r['rows'] as $row ) {
			$label = esc_html( $row['label'] );
			if ( 'channel' === $f['by'] ) {
				$label = '<a href="' . esc_url( add_query_arg( array( 'channel' => '' === $row['key'] ? '-' : $row['key'], 'by' => 'campaign' ) ) ) . '">' . $label . '</a>';
			} elseif ( 'campaign' === $f['by'] && '' !== $row['key'] ) {
				$label .= ' <small class="flxads-muted">' . esc_html( flxads_channel_label( $row['channel'] ) ) . '</small>';
			}
			$cur = count( $row['amounts'] ) > 1 || ! isset( $row['amounts'][ $base ] ) ? '<small class="flxads-muted">' . esc_html( implode( ' · ', array_map( static function ( $c, $a ) { return flxads_money( $a, $c ); }, array_keys( $row['amounts'] ), $row['amounts'] ) ) ) . '</small>' : '';
			echo '<tr><td data-label="' . esc_attr( FLXADS_Report::dimensions()[ $f['by'] ] ) . '"><span class="flxads-dot flxads-dot--' . esc_attr( $row['channel'] ? $row['channel'] : 'none' ) . '"></span>' . $label . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td class="num" data-label="' . esc_attr__( 'Payments', 'flx-ads' ) . '">' . esc_html( number_format_i18n( $row['count'] ) ) . '</td>';
			echo '<td class="num" data-label="' . esc_attr__( 'Amount', 'flx-ads' ) . '"><strong>' . esc_html( flxads_money( $row['amount'], $base ) ) . '</strong>' . $cur . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( $sc ) {
				$has = isset( $row['spend'] );
				echo '<td class="num" data-label="' . esc_attr__( 'Spend', 'flx-ads' ) . '">' . ( $has ? '<span>' . esc_html( flxads_money( $row['spend'], $base ) ) . ( null !== $row['cpa'] ? '<small class="flxads-muted">' . esc_html( sprintf( /* translators: %s: cost */ __( '%s per payment', 'flx-ads' ), flxads_money( $row['cpa'], $base ) ) ) . '</small>' : '' ) . '</span>' : '<span class="flxads-muted">—</span>' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<td class="num" data-label="ROAS">' . ( $has ? '<span class="flxads-roas flxads-roas--' . esc_attr( self::roas_level( $row['roas'] ) ) . '">' . esc_html( self::roas( $row['roas'] ) ) . '</span>' : '<span class="flxads-muted">—</span>' ) . '</td>';
			}
			echo '<td class="num" data-label="' . esc_attr__( 'New', 'flx-ads' ) . '">' . esc_html( number_format_i18n( $row['new'] ) ) . '</td>';
			echo '<td class="share" data-label="' . esc_attr__( 'Share', 'flx-ads' ) . '"><span class="flxads-bar"><i style="width:' . esc_attr( min( 100, $row['share'] ) ) . '%"></i></span><small>' . esc_html( number_format_i18n( $row['share'], 1 ) ) . '%</small></td></tr>';
		}
		echo '</tbody></table></div>';

		$recent = FLXADS_Report::recent( $f + array( 'ads_only' => 1 ), 12 );
		echo '<h2 class="flxads-h2">' . esc_html__( 'Latest payments from ads', 'flx-ads' ) . '</h2>';
		echo '<div class="flxads-card"><table class="flxads-table"><thead><tr><th>' . esc_html__( 'Date', 'flx-ads' ) . '</th><th>' . esc_html__( 'Order', 'flx-ads' ) . '</th><th>' . esc_html__( 'Ad', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Amount', 'flx-ads' ) . '</th></tr></thead><tbody>';
		if ( ! $recent ) {
			echo '<tr><td colspan="4" class="flxads-empty">' . esc_html__( 'None yet.', 'flx-ads' ) . '</td></tr>';
		}
		foreach ( $recent as $o ) {
			$order = wc_get_order( (int) $o['order_id'] );
			$num   = $order ? '#' . $order->get_order_number() : '#' . (int) $o['order_id'];
			$what  = $o['is_donation'] ? __( 'Donation', 'flx-ads' ) : __( 'Order', 'flx-ads' );
			echo '<tr><td data-label="' . esc_attr__( 'Date', 'flx-ads' ) . '">' . esc_html( flxads_date( $o['paid_at'], 'short' ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Order', 'flx-ads' ) . '">' . ( $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html( $num ) . '</a>' : esc_html( $num ) ) . ' <small class="flxads-muted">' . esc_html( $what ) . ( $o['new_person'] ? ' · ' . esc_html__( 'new', 'flx-ads' ) : '' ) . '</small></td>';
			echo '<td data-label="' . esc_attr__( 'Ad', 'flx-ads' ) . '"><span class="flxads-dot flxads-dot--' . esc_attr( $o['channel'] ) . '"></span>' . esc_html( FLXADS_CRM::summary( $o ) ) . '</td>';
			echo '<td class="num" data-label="' . esc_attr__( 'Amount', 'flx-ads' ) . '">' . esc_html( flxads_money( $o['total'], $o['currency'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/** "3,2x" biçimi. */
	private static function roas( $v ) {
		return null === $v ? '—' : number_format_i18n( (float) $v, $v < 10 ? 1 : 0 ) . 'x';
	}

	/** ROAS rengi: 1'in altı zarar, 1-3 orta, 3+ iyi. */
	private static function roas_level( $v ) {
		return null === $v ? 'none' : ( $v < 1 ? 'bad' : ( $v < 3 ? 'mid' : 'good' ) );
	}

	private static function kpi( $label, $value, $hint = '' ) {
		echo '<div class="flxads-kpi"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong>' . ( '' !== $hint ? '<small>' . esc_html( $hint ) . '</small>' : '' ) . '</div>';
	}

	/* ------------------------------------------------------------ Harcama */

	private static function tab_spend() {
		$f    = self::filters();
		$base = FLXADS_Report::base_currency();
		$st   = FLXADS_Spend::status( 'meta' );
		$man  = flxads_can( true );

		echo '<section class="flxads-card flxads-pad"><div class="flxads-platform__head"><h2 class="flxads-h2"><span class="flxads-dot flxads-dot--meta"></span>' . esc_html__( 'Meta ad spend', 'flx-ads' ) . '</h2>';
		if ( $man && FLXADS_Spend::meta_ready() ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'flxads_spend' );
			echo '<input type="hidden" name="action" value="flxads_spend"><input type="hidden" name="do" value="sync"><button class="button">' . esc_html__( 'Update now', 'flx-ads' ) . '</button></form>';
		}
		echo '</div>';
		if ( ! FLXADS_Spend::meta_ready() ) {
			echo '<p class="flxads-muted">' . esc_html__( 'Enter the ad account ID in Settings › Meta. Spend per campaign and ad is then read every 6 hours, so the report shows ROAS and cost per donation.', 'flx-ads' ) . '</p>';
			if ( $man ) {
				echo '<p><a class="button" href="' . esc_url( flxads_admin_url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'flx-ads' ) . '</a></p>';
			}
		} elseif ( ! $st ) {
			echo '<p class="flxads-muted">' . esc_html__( 'Not read yet. It runs in the background after this page loads; refresh in a minute.', 'flx-ads' ) . '</p>';
		} elseif ( ! empty( $st['ok'] ) ) {
			/* translators: 1: time ago, 2: from date, 3: to date, 4: rows */
			echo '<p class="flxads-test flxads-test--ok">' . esc_html( sprintf( __( 'Updated %1$s: %2$s – %3$s, %4$d rows. Updates every 6 hours.', 'flx-ads' ), flxads_ago( $st['at'] ), flxads_date( $st['since'], 'date' ), flxads_date( $st['until'], 'date' ), (int) $st['rows'] ) ) . '</p>';
		} else {
			echo '<p class="flxads-test flxads-test--bad">' . esc_html( __( 'Last update failed:', 'flx-ads' ) . ' ' . $st['message'] ) . ' <small>' . esc_html( flxads_ago( $st['at'] ) ) . '</small></p>';
			echo '<p class="flxads-muted">' . esc_html__( 'The key needs the ads_read permission on this ad account: Business Settings › Users › System users › add the ad account › Generate token (ads_read).', 'flx-ads' ) . '</p>';
		}
		echo '</section>';

		/* Dönemin harcaması kampanya bazında. */
		$sp = FLXADS_Spend::totals( $f['from'], $f['to'] );
		echo '<form class="flxads-filters" method="get"><input type="hidden" name="page" value="flx-ads"><input type="hidden" name="tab" value="spend">';
		echo '<label><span>' . esc_html__( 'Period', 'flx-ads' ) . '</span><select name="range" data-flxads-range>';
		foreach ( array( '7' => __( 'Last 7 days', 'flx-ads' ), '30' => __( 'Last 30 days', 'flx-ads' ), '90' => __( 'Last 90 days', 'flx-ads' ), '365' => __( 'Last 12 months', 'flx-ads' ), 'custom' => __( 'Custom', 'flx-ads' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $f['range'], (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label class="flxads-custom"' . ( 'custom' === $f['range'] ? '' : ' hidden' ) . '><span>' . esc_html__( 'From', 'flx-ads' ) . '</span><input type="date" name="from" value="' . esc_attr( $f['from'] ) . '"></label>';
		echo '<label class="flxads-custom"' . ( 'custom' === $f['range'] ? '' : ' hidden' ) . '><span>' . esc_html__( 'To', 'flx-ads' ) . '</span><input type="date" name="to" value="' . esc_attr( $f['to'] ) . '"></label>';
		echo '<button class="button">' . esc_html__( 'Apply', 'flx-ads' ) . '</button></form>';

		echo '<div class="flxads-card"><table class="flxads-table"><thead><tr><th>' . esc_html__( 'Campaign', 'flx-ads' ) . '</th><th>' . esc_html__( 'Channel', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Spend', 'flx-ads' ) . '</th></tr></thead><tbody>';
		$camps = $sp['by_campaign'];
		uasort(
			$camps,
			static function ( $a, $b ) {
				return $b[0] <=> $a[0];
			}
		);
		if ( ! $camps ) {
			echo '<tr><td colspan="3" class="flxads-empty">' . esc_html__( 'No spend in this period.', 'flx-ads' ) . '</td></tr>';
		}
		foreach ( array_slice( $camps, 0, 50 ) as $c ) {
			echo '<tr><td data-label="' . esc_attr__( 'Campaign', 'flx-ads' ) . '">' . esc_html( $c[2] ) . '</td><td data-label="' . esc_attr__( 'Channel', 'flx-ads' ) . '"><span class="flxads-dot flxads-dot--' . esc_attr( $c[1] ) . '"></span>' . esc_html( flxads_channel_label( $c[1] ) ) . '</td><td class="num" data-label="' . esc_attr__( 'Spend', 'flx-ads' ) . '">' . esc_html( flxads_money( $c[0], $base ) ) . '</td></tr>';
		}
		if ( $camps ) {
			echo '<tr class="flxads-total"><td>' . esc_html__( 'Total', 'flx-ads' ) . '</td><td></td><td class="num" data-label="' . esc_attr__( 'Total', 'flx-ads' ) . '"><strong>' . esc_html( flxads_money( $sp['total'], $base ) ) . '</strong></td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<p class="flxads-muted">' . esc_html__( 'Spend is matched to donations by campaign name (utm_campaign) and ad name (utm_content). Use the Meta URL parameters from the Ad link tab so the names are the same.', 'flx-ads' ) . '</p>';

		if ( ! $man ) {
			return;
		}
		echo '<h2 class="flxads-h2">' . esc_html__( 'Add spend by hand', 'flx-ads' ) . '</h2>';
		echo '<section class="flxads-card flxads-pad"><p class="flxads-muted">' . esc_html__( 'For Google Ads, TikTok, posters or anything not read automatically. Use the same campaign name as in the ad link.', 'flx-ads' ) . '</p>';
		echo '<form class="flxads-builder" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'flxads_spend' );
		echo '<input type="hidden" name="action" value="flxads_spend"><input type="hidden" name="do" value="add">';
		echo '<label><span>' . esc_html__( 'Channel', 'flx-ads' ) . '</span><select name="channel">';
		foreach ( flxads_channel_labels() as $k => $l ) {
			if ( '' !== $k ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $k, 'google', false ) . '>' . esc_html( $l ) . '</option>';
			}
		}
		echo '</select></label>';
		echo '<label><span>' . esc_html__( 'Date', 'flx-ads' ) . '</span><input type="date" name="day" value="' . esc_attr( wp_date( 'Y-m-d' ) ) . '" required></label>';
		echo '<label><span>' . esc_html__( 'Campaign', 'flx-ads' ) . '</span><input name="campaign" placeholder="fidan-kasim-2026"></label>';
		echo '<label><span>' . esc_html__( 'Ad (optional)', 'flx-ads' ) . '</span><input name="ad" placeholder="video-1"></label>';
		echo '<label><span>' . esc_html__( 'Amount', 'flx-ads' ) . '</span><input name="spend" inputmode="decimal" required placeholder="1500"></label>';
		echo '<label><span>' . esc_html__( 'Currency', 'flx-ads' ) . '</span><input name="currency" value="' . esc_attr( $base ) . '" maxlength="3"></label>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Add spend', 'flx-ads' ) . '</button></p></form></section>';

		$rows = FLXADS_Spend::manual_rows( 50 );
		if ( $rows ) {
			echo '<div class="flxads-card"><table class="flxads-table"><thead><tr><th>' . esc_html__( 'Date', 'flx-ads' ) . '</th><th>' . esc_html__( 'Channel', 'flx-ads' ) . '</th><th>' . esc_html__( 'Campaign', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Amount', 'flx-ads' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				echo '<tr><td data-label="' . esc_attr__( 'Date', 'flx-ads' ) . '">' . esc_html( flxads_date( $r['day'] . ' 12:00:00', 'date' ) ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Channel', 'flx-ads' ) . '"><span class="flxads-dot flxads-dot--' . esc_attr( $r['platform'] ) . '"></span>' . esc_html( flxads_channel_label( $r['platform'] ) ) . '</td>';
				echo '<td data-label="' . esc_attr__( 'Campaign', 'flx-ads' ) . '">' . esc_html( trim( $r['campaign'] . ( '' !== $r['ad'] ? ' · ' . $r['ad'] : '' ) ) ) . '</td>';
				echo '<td class="num" data-label="' . esc_attr__( 'Amount', 'flx-ads' ) . '">' . esc_html( flxads_money( $r['spend'], $r['currency'] ) ) . '</td><td>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( 'flxads_spend' );
				echo '<input type="hidden" name="action" value="flxads_spend"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="' . (int) $r['id'] . '"><button class="button button-small button-link-delete">' . esc_html__( 'Delete', 'flx-ads' ) . '</button></form></td></tr>';
			}
			echo '</tbody></table></div>';
		}
	}

	public static function spend_action() {
		if ( ! flxads_can( true ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-ads' ) );
		}
		check_admin_referer( 'flxads_spend' );
		$do  = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$msg = '';
		if ( 'sync' === $do ) {
			FLXADS_Spend::sync_meta();
			$msg = 'synced';
		} elseif ( 'add' === $do ) {
			$msg = FLXADS_Spend::add_manual( wp_unslash( $_POST ) ) ? 'spend_added' : 'spend_bad'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		} elseif ( 'delete' === $do ) {
			FLXADS_Spend::delete_manual( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 );
		}
		wp_safe_redirect( flxads_admin_url( 'spend', $msg ? array( 'flxads_msg' => $msg ) : array() ) );
		exit;
	}

	/* ------------------------------------------------------------ Gönderim kaydı */

	private static function status_label( $s, $err = '' ) {
		$map = array(
			'pending' => __( 'Waiting', 'flx-ads' ),
			'sent'    => __( 'Sent', 'flx-ads' ),
			'failed'  => __( 'Failed', 'flx-ads' ),
			'skipped' => __( 'Not sent', 'flx-ads' ),
		);
		$why = array(
			'no_consent'   => __( 'visitor did not accept cookies', 'flx-ads' ),
			'browser_sent' => __( 'already sent from the browser', 'flx-ads' ),
			'platform_off' => __( 'platform turned off', 'flx-ads' ),
			'order_missing' => __( 'order deleted', 'flx-ads' ),
		);
		return array( $map[ $s ] ?? $s, $why[ $err ] ?? $err );
	}

	private static function tab_log() {
		global $wpdb;
		$c = FLXADS_Send::counts();
		echo '<div class="flxads-kpis">';
		self::kpi( __( 'Sent', 'flx-ads' ), number_format_i18n( $c['sent'] ) );
		self::kpi( __( 'Waiting', 'flx-ads' ), number_format_i18n( $c['pending'] ) );
		self::kpi( __( 'Failed', 'flx-ads' ), number_format_i18n( $c['failed'] ) );
		self::kpi( __( 'Not sent', 'flx-ads' ), number_format_i18n( $c['skipped'] ), __( 'no consent, or sent from the browser', 'flx-ads' ) );
		echo '</div>';
		$rows = $wpdb->get_results( 'SELECT * FROM ' . flxads_table( 'conversions' ) . ' ORDER BY id DESC LIMIT 100', ARRAY_A ); // phpcs:ignore WordPress.DB
		echo '<div class="flxads-card"><table class="flxads-table"><thead><tr><th>' . esc_html__( 'Date', 'flx-ads' ) . '</th><th>' . esc_html__( 'Platform', 'flx-ads' ) . '</th><th>' . esc_html__( 'Order', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Value', 'flx-ads' ) . '</th><th>' . esc_html__( 'Status', 'flx-ads' ) . '</th><th></th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="6" class="flxads-empty">' . esc_html__( 'Nothing sent yet. When a donation is paid, a Purchase event is sent to every platform that has its server key.', 'flx-ads' ) . '</td></tr>';
		}
		foreach ( (array) $rows as $r ) {
			list( $st, $why ) = self::status_label( $r['status'], $r['last_error'] );
			$order            = wc_get_order( (int) $r['order_id'] );
			echo '<tr><td data-label="' . esc_attr__( 'Date', 'flx-ads' ) . '">' . esc_html( flxads_date( 'sent' === $r['status'] ? $r['sent_at'] : $r['created_at'], 'short' ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Platform', 'flx-ads' ) . '">' . esc_html( flxads_platforms()[ $r['platform'] ] ?? $r['platform'] ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Order', 'flx-ads' ) . '">' . ( $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>' : '#' . (int) $r['order_id'] ) . '</td>';
			echo '<td class="num" data-label="' . esc_attr__( 'Value', 'flx-ads' ) . '">' . esc_html( flxads_money( $r['value'], $r['currency'] ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Status', 'flx-ads' ) . '"><span class="flxads-pill flxads-pill--' . esc_attr( $r['status'] ) . '">' . esc_html( $st ) . '</span>' . ( '' !== $why ? ' <small class="flxads-muted">' . esc_html( $why ) . '</small>' : '' ) . ( (int) $r['attempts'] > 1 ? ' <small class="flxads-muted">' . esc_html( sprintf( /* translators: %d: attempts */ _n( '%d try', '%d tries', (int) $r['attempts'], 'flx-ads' ), (int) $r['attempts'] ) ) . '</small>' : '' ) . '</td><td>';
			if ( flxads_can( true ) && ( 'failed' === $r['status'] || ( 'skipped' === $r['status'] && 'no_consent' !== $r['last_error'] && 'browser_sent' !== $r['last_error'] ) ) ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( 'flxads_retry' );
				echo '<input type="hidden" name="action" value="flxads_retry"><input type="hidden" name="id" value="' . (int) $r['id'] . '"><button class="button button-small">' . esc_html__( 'Send again', 'flx-ads' ) . '</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/* ------------------------------------------------------------ UTM bağlantısı */

	private static function tab_links() {
		echo '<div class="flxads-card flxads-pad"><h2 class="flxads-h2">' . esc_html__( 'Tag each ad with its own link', 'flx-ads' ) . '</h2>';
		echo '<p class="flxads-muted">' . esc_html__( 'Meta, Google and TikTok add their click ID by themselves. A UTM link also names the campaign and the ad, so the report can show "which ad brought which donation". Paste the link as the ad\'s website URL; use a different "Ad" value for each ad or video.', 'flx-ads' ) . '</p>';
		echo '<div class="flxads-builder" data-flxads-builder>';
		$pages = array( home_url( '/' ) );
		foreach ( get_pages( array( 'number' => 50, 'sort_column' => 'menu_order,post_title' ) ) as $pg ) {
			$pages[] = get_permalink( $pg );
		}
		echo '<label><span>' . esc_html__( 'Page', 'flx-ads' ) . '</span><input type="url" list="flxads-pages" name="url" value="' . esc_attr( home_url( '/' ) ) . '"><datalist id="flxads-pages">';
		foreach ( array_unique( $pages ) as $u ) {
			echo '<option value="' . esc_attr( $u ) . '">';
		}
		echo '</datalist></label>';
		echo '<label><span>' . esc_html__( 'Platform', 'flx-ads' ) . '</span><select name="utm_source">';
		foreach ( array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'google' => 'Google', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp', 'newsletter' => __( 'E-mail newsletter', 'flx-ads' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label><span>' . esc_html__( 'Type', 'flx-ads' ) . '</span><select name="utm_medium"><option value="paid_social">' . esc_html__( 'Paid social', 'flx-ads' ) . '</option><option value="cpc">' . esc_html__( 'Search ad (cpc)', 'flx-ads' ) . '</option><option value="video">' . esc_html__( 'Video ad', 'flx-ads' ) . '</option><option value="social">' . esc_html__( 'Organic post', 'flx-ads' ) . '</option><option value="email">' . esc_html__( 'E-mail', 'flx-ads' ) . '</option></select></label>';
		echo '<label><span>' . esc_html__( 'Campaign', 'flx-ads' ) . '</span><input name="utm_campaign" placeholder="fidan-kasim-2026"></label>';
		echo '<label><span>' . esc_html__( 'Ad', 'flx-ads' ) . '</span><input name="utm_content" placeholder="video-1"></label>';
		echo '<div class="flxads-out"><code data-flxads-out></code><button type="button" class="button button-primary" data-flxads-copy>' . esc_html__( 'Copy', 'flx-ads' ) . '</button></div>';
		echo '</div>';
		echo '<p class="flxads-muted">' . esc_html__( 'Meta tip: instead of one link per ad you can put this in the ad\'s URL parameters field: utm_source={{site_source_name}}&utm_medium=paid_social&utm_campaign={{campaign.name}}&utm_content={{ad.name}}', 'flx-ads' ) . '</p>';
		echo '</div>';
	}

	/* ------------------------------------------------------------ Ayarlar */

	private static function secret_field( $key, $label, $help ) {
		$has  = '' !== flxads_secret( $key );
		$mask = $has ? flxads_mask( flxads_secret( $key ) ) : '';
		echo '<label class="flxads-field"><span>' . esc_html( $label ) . '</span><input type="password" name="s[' . esc_attr( $key ) . ']" value="" autocomplete="new-password" placeholder="' . esc_attr( $has ? sprintf( /* translators: %s: last 4 chars */ __( 'Saved (%s). Leave empty to keep.', 'flx-ads' ), $mask ) : '' ) . '"><small>' . esc_html( $help ) . '</small></label>';
	}

	private static function text_field( $key, $label, $help = '', $ph = '' ) {
		echo '<label class="flxads-field"><span>' . esc_html( $label ) . '</span><input type="text" class="code" name="s[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) flxads_setting( $key ) ) . '" placeholder="' . esc_attr( $ph ) . '">' . ( '' !== $help ? '<small>' . esc_html( $help ) . '</small>' : '' ) . '</label>';
	}

	private static function platform_box( $p, $title, callable $fields ) {
		$on    = (bool) flxads_setting( $p . '_on' );
		$tests = (array) get_option( 'flxads_tests', array() );
		echo '<section class="flxads-card flxads-pad flxads-platform"><div class="flxads-platform__head"><h2 class="flxads-h2"><span class="flxads-dot flxads-dot--' . esc_attr( 'ga4' === $p ? 'google' : $p ) . '"></span>' . esc_html( $title ) . '</h2>';
		echo '<label class="flxads-switch"><input type="checkbox" name="s[' . esc_attr( $p ) . '_on]" value="1"' . checked( $on, true, false ) . '> ' . esc_html__( 'On', 'flx-ads' ) . '</label></div>';
		$fields();
		if ( isset( $tests[ $p ] ) ) {
			echo '<p class="flxads-test flxads-test--' . ( $tests[ $p ]['ok'] ? 'ok' : 'bad' ) . '">' . esc_html( $tests[ $p ]['message'] ) . ' <small>' . esc_html( flxads_ago( $tests[ $p ]['at'] ) ) . '</small></p>';
		}
		if ( flxads_platform_on( $p, true ) ) {
			echo '<button class="button" name="test" value="' . esc_attr( $p ) . '">' . esc_html__( 'Test the connection', 'flx-ads' ) . '</button>';
		}
		echo '</section>';
	}

	private static function tab_settings() {
		if ( ! flxads_can( true ) ) {
			return;
		}
		$s = flxads_settings();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxads-settings">';
		wp_nonce_field( 'flxads_save' );
		echo '<input type="hidden" name="action" value="flxads_save">';

		self::platform_box(
			'meta',
			'Meta Pixel + Conversions API',
			static function () {
				self::text_field( 'meta_pixel', __( 'Pixel (dataset) ID', 'flx-ads' ), __( 'Events Manager › Data sources › your pixel › Settings.', 'flx-ads' ), '123456789012345' );
				self::secret_field( 'meta_token', __( 'Conversions API access token', 'flx-ads' ), __( 'Events Manager › Settings › Conversions API › Generate access token. Needed for server events (bank transfers, ad blockers).', 'flx-ads' ) );
				self::text_field( 'meta_test', __( 'Test event code (optional)', 'flx-ads' ), __( 'While testing only. Empty it after testing, or real donations are reported as test events.', 'flx-ads' ), 'TEST12345' );
				echo '<h3 class="flxads-h3">' . esc_html__( 'Ad spend (for ROAS)', 'flx-ads' ) . '</h3>';
				self::text_field( 'meta_account', __( 'Ad account ID', 'flx-ads' ), __( 'Ads Manager › account menu at the top left, the number under the account name (act_ is optional).', 'flx-ads' ), '1234567890' );
				self::secret_field( 'meta_ads_token', __( 'Marketing API token (ads_read)', 'flx-ads' ), __( 'Business Settings › Users › System users › add the ad account › Generate token with ads_read. Empty = the Conversions API token is tried. Read only; Flux Ads never changes your ads.', 'flx-ads' ) );
			}
		);
		self::platform_box(
			'tiktok',
			'TikTok Pixel + Events API',
			static function () {
				self::text_field( 'tiktok_pixel', __( 'Pixel ID', 'flx-ads' ), __( 'TikTok Ads Manager › Tools › Events › Web events.', 'flx-ads' ), 'C1A2B3C4D5E6F7G8H9I0' );
				self::secret_field( 'tiktok_token', __( 'Events API access token', 'flx-ads' ), __( 'Pixel › Settings › Events API › Generate access token.', 'flx-ads' ) );
				self::text_field( 'tiktok_test', __( 'Test event code (optional)', 'flx-ads' ), __( 'While testing only.', 'flx-ads' ), 'TEST12345' );
			}
		);
		self::platform_box(
			'ga4',
			'Google Analytics 4',
			static function () {
				$theme = function_exists( 'flx_ayar' ) ? (string) flx_ayar( 'ga4_id' ) : '';
				/* translators: %s: GA4 ID from theme */
				self::text_field( 'ga4_id', __( 'Measurement ID', 'flx-ads' ), '' !== $theme ? sprintf( __( 'Empty = the theme\'s ID (%s). The theme already loads the tag; Flux Ads only adds the donation events.', 'flx-ads' ), $theme ) : __( 'Admin › Data streams › Measurement ID.', 'flx-ads' ), 'G-XXXXXXXXXX' );
				self::secret_field( 'ga4_secret', __( 'Measurement Protocol API secret', 'flx-ads' ), __( 'Data stream › Measurement Protocol API secrets › Create. Needed for bank-transfer donations approved later. To see donations in Google Ads, import the GA4 "purchase" conversion there.', 'flx-ads' ) );
			}
		);

		echo '<section class="flxads-card flxads-pad"><h2 class="flxads-h2">' . esc_html__( 'Privacy', 'flx-ads' ) . '</h2>';
		echo '<fieldset class="flxads-field"><legend>' . esc_html__( 'Cookie consent', 'flx-ads' ) . '</legend>';
		echo '<label><input type="radio" name="s[consent]" value="auto"' . checked( $s['consent'], 'auto', false ) . '> ' . esc_html__( 'Wait for consent (recommended): pixels and the ad cookie start only after the visitor accepts the theme cookie banner. Donations of visitors who did not accept are counted in the report but not sent to ad platforms.', 'flx-ads' ) . '</label>';
		echo '<label><input type="radio" name="s[consent]" value="always"' . checked( $s['consent'], 'always', false ) . '> ' . esc_html__( 'Always: you collect consent another way.', 'flx-ads' ) . '</label></fieldset>';
		echo '<fieldset class="flxads-field"><legend>' . esc_html__( 'E-mail and phone (for better matching)', 'flx-ads' ) . '</legend>';
		foreach ( array( 'consent' => __( 'Only for contacts with ad consent in Flux CRM (recommended). Sent as SHA-256 hashes, never in plain text.', 'flx-ads' ), 'never' => __( 'Never: only the browser ID, IP and browser type.', 'flx-ads' ), 'always' => __( 'Always (hashed). Your privacy notice must say so.', 'flx-ads' ) ) as $k => $l ) {
			echo '<label><input type="radio" name="s[pii]" value="' . esc_attr( $k ) . '"' . checked( $s['pii'], $k, false ) . '> ' . esc_html( $l ) . '</label>';
		}
		echo '</fieldset>';
		echo '<label class="flxads-check"><input type="checkbox" name="s[browser]" value="1"' . checked( ! empty( $s['browser'] ), true, false ) . '> ' . esc_html__( 'Load the browser pixels too (page views, form and checkout steps). Off = server events only.', 'flx-ads' ) . '</label>';
		echo '<label class="flxads-field flxads-field--short"><span>' . esc_html__( 'Attribution window (days)', 'flx-ads' ) . '</span><input type="number" min="1" max="365" name="s[window_days]" value="' . (int) $s['window_days'] . '"><small>' . esc_html__( 'How long a click counts for a later donation.', 'flx-ads' ) . '</small></label>';
		echo '</section>';
		echo '<p><button class="button button-primary button-hero">' . esc_html__( 'Save settings', 'flx-ads' ) . '</button></p></form>';
	}

	public static function save() {
		if ( ! flxads_can( true ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-ads' ) );
		}
		check_admin_referer( 'flxads_save' );
		if ( ! empty( $_POST['test'] ) ) {
			self::test();
		}
		self::store();
		wp_safe_redirect( flxads_admin_url( 'settings', array( 'flxads_msg' => 'saved' ) ) );
		exit;
	}

	/** Gönderilen ayarları doğrula ve kaydet. */
	private static function store() {
		$in = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification
		$v  = array(
			'meta_on'      => empty( $in['meta_on'] ) ? 0 : 1,
			'meta_pixel'   => preg_replace( '/\D+/', '', (string) ( $in['meta_pixel'] ?? '' ) ),
			'meta_test'    => preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $in['meta_test'] ?? '' ) ),
			'meta_account' => preg_replace( '/\D+/', '', (string) ( $in['meta_account'] ?? '' ) ),
			'tiktok_on'    => empty( $in['tiktok_on'] ) ? 0 : 1,
			'tiktok_pixel' => strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $in['tiktok_pixel'] ?? '' ) ) ),
			'tiktok_test'  => preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $in['tiktok_test'] ?? '' ) ),
			'ga4_on'       => empty( $in['ga4_on'] ) ? 0 : 1,
			'ga4_id'       => preg_match( '/^G-[A-Z0-9]{4,}$/', strtoupper( trim( (string) ( $in['ga4_id'] ?? '' ) ) ) ) ? strtoupper( trim( (string) $in['ga4_id'] ) ) : '',
			'consent'      => 'always' === ( $in['consent'] ?? '' ) ? 'always' : 'auto',
			'pii'          => in_array( $in['pii'] ?? '', array( 'consent', 'never', 'always' ), true ) ? $in['pii'] : 'consent',
			'browser'      => empty( $in['browser'] ) ? 0 : 1,
			'window_days'  => max( 1, min( 365, (int) ( $in['window_days'] ?? 90 ) ) ),
		);
		foreach ( flxads_secret_keys() as $k ) {
			$v[ $k ] = trim( (string) ( $in[ $k ] ?? '' ) );
		}
		/* Hesap ya da anahtar değişince harcama baştan (30 gün) okunur. */
		if ( $v['meta_account'] !== (string) flxads_setting( 'meta_account' ) || '' !== $v['meta_ads_token'] ) {
			$sync = (array) get_option( 'flxads_spend_sync', array() );
			unset( $sync['meta'] );
			update_option( 'flxads_spend_sync', $sync, false );
		}
		flxads_save_settings( $v );
	}

	public static function test() {
		if ( ! flxads_can( true ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-ads' ) );
		}
		check_admin_referer( 'flxads_save' );
		self::store(); // formdaki son değerlerle test edilir
		$p = isset( $_POST['test'] ) ? sanitize_key( wp_unslash( $_POST['test'] ) ) : '';
		if ( isset( flxads_platforms()[ $p ] ) ) {
			FLXADS_Send::test( $p );
		}
		wp_safe_redirect( flxads_admin_url( 'settings', array( 'flxads_msg' => 'tested', 'p' => $p ) ) );
		exit;
	}

	public static function retry() {
		if ( ! flxads_can( true ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-ads' ) );
		}
		check_admin_referer( 'flxads_retry' );
		FLXADS_Send::retry( isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0 );
		wp_safe_redirect( flxads_admin_url( 'log', array( 'flxads_msg' => 'retried' ) ) );
		exit;
	}
}
