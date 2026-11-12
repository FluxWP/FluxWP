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
		add_action( 'admin_post_flxads_aud', array( __CLASS__, 'audience_action' ) );
		add_action( 'admin_post_flxads_mgr', array( __CLASS__, 'mgr_action' ) );
		add_action( 'admin_post_flxads_studio', array( __CLASS__, 'studio_action' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_head', array( __CLASS__, 'menu_icon' ) );
	}

	public static function menu() {
		add_menu_page( 'Flux Ads', 'Flux Ads', 'flxads_view', 'flx-ads', array( __CLASS__, 'render' ), 'none', 58.6 );
		foreach ( self::tabs() as $tab => $label ) {
			$cap = 'settings' === $tab ? 'flxads_manage' : 'flxads_view';
			add_submenu_page( 'flx-ads', $label . ' ‹ Flux Ads', $label, $cap, flxads_page_slug( $tab ), array( __CLASS__, 'render' ) );
		}
	}

	public static function menu_icon() {
		echo '<style>#toplevel_page_flx-ads .wp-menu-image{background:url(' . esc_url( FLXADS_URL . 'assets/icon.svg' ) . ') center/20px 20px no-repeat}</style>';
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, '_page_flx-ads' ) ) {
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
			'report'    => __( 'Report', 'flx-ads' ),
			'campaigns' => __( 'Campaigns', 'flx-ads' ),
			'studio'    => __( 'Studio', 'flx-ads' ),
			'value'     => __( 'Donor value', 'flx-ads' ),
			'spend'     => __( 'Ad spend', 'flx-ads' ),
			'audiences' => __( 'Audiences', 'flx-ads' ),
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
		$tab  = flxads_current_tab();
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'report';
		echo '<div class="wrap flxads">';
		echo '<h1 class="flxads-title">' . esc_html( 'report' === $tab ? __( 'Ad conversions', 'flx-ads' ) : $tabs[ $tab ] ) . '</h1>';
		if ( 'report' === $tab ) {
			echo '<p class="flxads-lead">' . esc_html__( 'Which ad, campaign and ad text brought which donation. Paid donations and orders only; bank transfers count when approved.', 'flx-ads' ) . '</p>';
		}
		self::notice();
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
		} elseif ( 'aud_err' === $m ) {
			$e = (string) get_transient( 'flxads_aud_err_' . get_current_user_id() );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( __( 'Meta did not accept it:', 'flx-ads' ) . ' ' . $e ) . '</p></div>';
		} elseif ( in_array( $m, array( 'aud_create', 'aud_lookalike', 'aud_sync', 'aud_delete' ), true ) ) {
			$t = array(
				'aud_create'    => __( 'Audience created in Meta and filled.', 'flx-ads' ),
				'aud_lookalike' => __( 'Lookalike audience created. Meta fills it within a few hours.', 'flx-ads' ),
				'aud_sync'      => __( 'Audience updated.', 'flx-ads' ),
				'aud_delete'    => __( 'Audience deleted.', 'flx-ads' ),
			);
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $t[ $m ] ) . '</p></div>';
		} elseif ( 'studio_err' === $m ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( (string) get_transient( 'flxads_aud_err_' . get_current_user_id() ) ) . '</p></div>';
		} elseif ( in_array( $m, array( 'studio_ai', 'studio_saved', 'studio_deleted', 'studio_sent' ), true ) ) {
			$t = array(
				'studio_ai'      => __( 'Flux AI wrote a draft. Read and edit it before sending it to Meta.', 'flx-ads' ),
				'studio_saved'   => __( 'Draft saved.', 'flx-ads' ),
				'studio_deleted' => __( 'Draft deleted.', 'flx-ads' ),
				'studio_sent'    => __( 'Created in Meta, paused. Turn it on under Campaigns when you are ready.', 'flx-ads' ),
			);
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $t[ $m ] ) . '</p></div>';
		} elseif ( 'mgr_status' === $m || 'mgr_budget' === $m ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( 'mgr_status' === $m ? __( 'Status changed in Meta.', 'flx-ads' ) : __( 'Daily budget changed in Meta.', 'flx-ads' ) ) . '</p></div>';
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
		echo '<form class="flxads-filters" method="get"><input type="hidden" name="page" value="flx-ads">';
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
		echo '<form class="flxads-filters" method="get"><input type="hidden" name="page" value="flx-ads-spend">';
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

	/* ------------------------------------------------------------ Bağışçı değeri (LTV) */

	private static function tab_value() {
		$g    = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification
		$f    = self::filters();
		$by   = isset( $g['by'] ) && 'channel' === $g['by'] ? 'channel' : 'campaign';
		if ( ! isset( $g['range'] ) ) {
			$f['range'] = '90';
			$f['from']  = wp_date( 'Y-m-d', time() - 89 * DAY_IN_SECONDS );
		}
		$r    = FLXADS_Cohort::value( $by, $f );
		$t    = $r['totals'];
		$base = $r['base'];

		echo '<p class="flxads-lead">' . esc_html__( 'What the people an ad brought in gave in total, not only their first payment. A campaign whose first gifts are cheap but never come back shows up here.', 'flx-ads' ) . '</p>';
		echo '<form class="flxads-filters" method="get"><input type="hidden" name="page" value="flx-ads-value">';
		echo '<label><span>' . esc_html__( 'First payment in', 'flx-ads' ) . '</span><select name="range" data-flxads-range>';
		foreach ( array( '30' => __( 'Last 30 days', 'flx-ads' ), '90' => __( 'Last 90 days', 'flx-ads' ), '365' => __( 'Last 12 months', 'flx-ads' ), 'custom' => __( 'Custom', 'flx-ads' ) ) as $k => $l ) {
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
		echo '<label><span>' . esc_html__( 'Group by', 'flx-ads' ) . '</span><select name="by"><option value="campaign"' . selected( $by, 'campaign', false ) . '>' . esc_html__( 'Campaign', 'flx-ads' ) . '</option><option value="channel"' . selected( $by, 'channel', false ) . '>' . esc_html__( 'Channel', 'flx-ads' ) . '</option></select></label>';
		echo '<button class="button">' . esc_html__( 'Apply', 'flx-ads' ) . '</button></form>';

		echo '<div class="flxads-kpis">';
		/* translators: %s: count */
		self::kpi( __( 'New donors and customers', 'flx-ads' ), number_format_i18n( $t['new'] ), sprintf( __( '%s of them from ads', 'flx-ads' ), number_format_i18n( $t['ads_new'] ) ) );
		self::kpi( __( 'Value per person from ads', 'flx-ads' ), null === $t['ads_avg'] ? '—' : flxads_money( $t['ads_avg'], $base ), __( 'all payments so far', 'flx-ads' ) );
		self::kpi( __( 'Cost to win one (CAC)', 'flx-ads' ), null === $t['cac'] ? '—' : flxads_money( $t['cac'], $base ), $t['spend'] > 0 ? sprintf( /* translators: %s: amount */ __( '%s spend', 'flx-ads' ), flxads_money( $t['spend'], $base ) ) : __( 'no spend recorded', 'flx-ads' ) );
		self::kpi( __( 'Value / cost', 'flx-ads' ), self::roas( $t['ratio'] ), null === $t['rate'] ? '' : sprintf( /* translators: %s: percent */ __( '%s%% gave again', 'flx-ads' ), number_format_i18n( $t['rate'], 1 ) ) );
		echo '</div>';

		$label = 'channel' === $by ? __( 'Channel', 'flx-ads' ) : __( 'Campaign', 'flx-ads' );
		echo '<div class="flxads-card"><table class="flxads-table"><thead><tr><th>' . esc_html( $label ) . '</th><th class="num">' . esc_html__( 'New', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'First payment', 'flx-ads' ) . '</th><th class="num">' . esc_html__( '90 days', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'So far', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Per person', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Gave again', 'flx-ads' ) . '</th><th class="num">CAC</th><th class="num">' . esc_html__( 'Value / cost', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Paid back in', 'flx-ads' ) . '</th></tr></thead><tbody>';
		if ( ! $r['rows'] ) {
			echo '<tr><td colspan="10" class="flxads-empty">' . esc_html__( 'Nobody made a first payment in this period yet.', 'flx-ads' ) . '</td></tr>';
		}
		foreach ( $r['rows'] as $row ) {
			$name = esc_html( $row['label'] );
			if ( 'campaign' === $by && '' !== $row['channel'] ) {
				$name .= ' <small class="flxads-muted">' . esc_html( flxads_channel_label( $row['channel'] ) ) . '</small>';
			}
			$dash = '<span class="flxads-muted">—</span>';
			echo '<tr><td data-label="' . esc_attr( $label ) . '"><span class="flxads-dot flxads-dot--' . esc_attr( $row['channel'] ? $row['channel'] : 'none' ) . '"></span>' . $name . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td class="num" data-label="' . esc_attr__( 'New', 'flx-ads' ) . '">' . esc_html( number_format_i18n( $row['new'] ) ) . '</td>';
			echo '<td class="num" data-label="' . esc_attr__( 'First payment', 'flx-ads' ) . '">' . esc_html( flxads_money( $row['first'], $base ) ) . '</td>';
			echo '<td class="num" data-label="' . esc_attr__( '90 days', 'flx-ads' ) . '">' . esc_html( flxads_money( $row['ltv90'], $base ) ) . '</td>';
			echo '<td class="num" data-label="' . esc_attr__( 'So far', 'flx-ads' ) . '"><strong>' . esc_html( flxads_money( $row['ltv'], $base ) ) . '</strong></td>';
			echo '<td class="num" data-label="' . esc_attr__( 'Per person', 'flx-ads' ) . '">' . ( null === $row['avg'] ? $dash : esc_html( flxads_money( $row['avg'], $base ) ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td class="num" data-label="' . esc_attr__( 'Gave again', 'flx-ads' ) . '">' . ( null === $row['rate'] ? $dash : esc_html( number_format_i18n( $row['rate'], 1 ) . '%' ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td class="num" data-label="CAC">' . ( null === $row['cac'] ? ( null !== $row['spend'] ? '<span>' . esc_html( flxads_money( $row['spend'], $base ) ) . '<small class="flxads-muted">' . esc_html__( 'nobody new', 'flx-ads' ) . '</small></span>' : $dash ) : esc_html( flxads_money( $row['cac'], $base ) ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td class="num" data-label="' . esc_attr__( 'Value / cost', 'flx-ads' ) . '">' . ( null === $row['ratio'] ? $dash : '<span class="flxads-roas flxads-roas--' . esc_attr( self::roas_level( $row['ratio'] ) ) . '">' . esc_html( self::roas( $row['ratio'] ) ) . '</span>' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			/* translators: %s: days */
			echo '<td class="num" data-label="' . esc_attr__( 'Paid back in', 'flx-ads' ) . '">' . ( null === $row['payback'] ? ( null !== $row['spend'] && $row['spend'] > 0 ? '<span class="flxads-muted">' . esc_html__( 'not yet', 'flx-ads' ) . '</span>' : $dash ) : esc_html( sprintf( _n( '%s day', '%s days', max( 1, $row['payback'] ), 'flx-ads' ), number_format_i18n( max( 1, $row['payback'] ) ) ) ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</tbody></table></div>';
		echo '<p class="flxads-muted">' . esc_html__( 'A person belongs to the campaign of their first payment. Their later payments count for that campaign even when they came back without an ad. CAC is the campaign spend in this period divided by the new people it brought. Value / cost above 3x is good; below 1x the ad has not paid for itself yet.', 'flx-ads' ) . '</p>';

		/* Kohort tablosu. */
		$m = FLXADS_Cohort::matrix( array( 'kind' => $f['kind'] ), 12 );
		echo '<h2 class="flxads-h2">' . esc_html__( 'Value by month of first payment', 'flx-ads' ) . '</h2>';
		echo '<p class="flxads-muted">' . esc_html__( 'Each row is the people who paid for the first time that month. The cells show what one person gave in total by the end of each month since then.', 'flx-ads' ) . '</p>';
		echo '<div class="flxads-card flxads-scroll"><table class="flxads-table flxads-cohort"><thead><tr><th>' . esc_html__( 'Month', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'People', 'flx-ads' ) . '</th>';
		for ( $i = 0; $i < $m['months']; $i++ ) {
			/* translators: %d: month number since first payment */
			echo '<th class="num">' . esc_html( sprintf( __( 'M%d', 'flx-ads' ), $i ) ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( ! $m['rows'] ) {
			echo '<tr><td colspan="' . (int) ( $m['months'] + 2 ) . '" class="flxads-empty">' . esc_html__( 'None yet.', 'flx-ads' ) . '</td></tr>';
		}
		$max = 0.0;
		foreach ( $m['rows'] as $row ) {
			foreach ( $row['cells'] as $c ) {
				$max = max( $max, (float) $c );
			}
		}
		foreach ( $m['rows'] as $row ) {
			echo '<tr><th scope="row">' . esc_html( $row['label'] ) . '</th><td class="num">' . esc_html( number_format_i18n( $row['new'] ) ) . '</td>';
			foreach ( $row['cells'] as $c ) {
				if ( null === $c ) {
					echo '<td class="num flxads-cohort__na"></td>';
					continue;
				}
				$a = $max > 0 ? round( 0.04 + 0.32 * $c / $max, 2 ) : 0;
				echo '<td class="num" style="--a:' . esc_attr( $a ) . '">' . esc_html( flxads_money( $c, FLXADS_Report::base_currency() ) ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/* ------------------------------------------------------------ Meta kitleleri */

	private static function tab_audiences() {
		$man = flxads_can( true );
		echo '<p class="flxads-lead">' . esc_html__( 'Send a group of people from Flux CRM to Meta as an audience: to show ads to similar people, or to stop showing ads to people who just donated. Only people who gave ad consent are sent, as encrypted (hashed) email and phone. The list is updated every day; someone who withdraws consent is removed straight away.', 'flx-ads' ) . '</p>';
		if ( ! FLXADS_Audiences::crm_ready() ) {
			echo '<section class="flxads-card flxads-pad"><p>' . esc_html__( 'Audiences need Flux CRM: people, ad consent and segments come from there.', 'flx-ads' ) . '</p></section>';
			return;
		}
		if ( ! FLXADS_Spend::meta_ready() ) {
			echo '<section class="flxads-card flxads-pad"><p>' . esc_html__( 'Enter the Meta ad account ID and a Marketing API key with the ads_management permission in Settings › Meta.', 'flx-ads' ) . '</p>';
			if ( $man ) {
				echo '<p><a class="button" href="' . esc_url( flxads_admin_url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'flx-ads' ) . '</a></p>';
			}
			echo '</section>';
		}
		$all = FLXADS_Audiences::all();
		$src = FLXADS_Audiences::sources();
		$pur = FLXADS_Audiences::purposes();
		echo '<div class="flxads-card"><table class="flxads-table"><thead><tr><th>' . esc_html__( 'Audience', 'flx-ads' ) . '</th><th>' . esc_html__( 'Group', 'flx-ads' ) . '</th><th>' . esc_html__( 'Use', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'People', 'flx-ads' ) . '</th><th>' . esc_html__( 'Updated', 'flx-ads' ) . '</th><th></th></tr></thead><tbody>';
		if ( ! $all ) {
			echo '<tr><td colspan="6" class="flxads-empty">' . esc_html__( 'No audience yet.', 'flx-ads' ) . '</td></tr>';
		}
		foreach ( $all as $a ) {
			$group = 'lookalike' === $a['kind'] ? sprintf( /* translators: %s: source audience */ __( 'Similar to: %s', 'flx-ads' ), ( $all[ substr( $a['source'], 10 ) ]['name'] ?? '—' ) ) : ( $src[ $a['source'] ] ?? __( '(removed group)', 'flx-ads' ) );
			echo '<tr><td data-label="' . esc_attr__( 'Audience', 'flx-ads' ) . '"><strong>' . esc_html( $a['name'] ) . '</strong><small class="flxads-muted">Meta ID ' . esc_html( $a['meta_id'] ) . '</small></td>';
			echo '<td data-label="' . esc_attr__( 'Group', 'flx-ads' ) . '">' . esc_html( $group ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Use', 'flx-ads' ) . '"><span class="flxads-pill flxads-pill--' . esc_attr( $a['purpose'] ) . '">' . esc_html( $pur[ $a['purpose'] ] ?? '' ) . '</span></td>';
			echo '<td class="num" data-label="' . esc_attr__( 'People', 'flx-ads' ) . '">' . ( 'lookalike' === $a['kind'] ? '<span class="flxads-muted">' . esc_html__( 'Meta fills it', 'flx-ads' ) . '</span>' : esc_html( number_format_i18n( (int) $a['count'] ) ) ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Updated', 'flx-ads' ) . '">';
			if ( '' !== (string) $a['error'] ) {
				echo '<span class="flxads-test flxads-test--bad">' . esc_html( $a['error'] ) . '</span>';
			} elseif ( ! empty( $a['last'] ) ) {
				/* translators: 1: time ago, 2: added, 3: removed */
				echo esc_html( sprintf( __( '%1$s · +%2$d / −%3$d', 'flx-ads' ), flxads_ago( $a['synced'] ), (int) $a['last']['added'], (int) $a['last']['removed'] ) );
			} else {
				echo esc_html( flxads_ago( $a['synced'] ) );
			}
			echo '</td><td><div class="flxads-actions">';
			if ( $man ) {
				if ( 'custom' === $a['kind'] ) {
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
					wp_nonce_field( 'flxads_aud' );
					echo '<input type="hidden" name="action" value="flxads_aud"><input type="hidden" name="do" value="sync"><input type="hidden" name="id" value="' . esc_attr( $a['id'] ) . '"><button class="button button-small">' . esc_html__( 'Update now', 'flx-ads' ) . '</button></form>';
				}
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-flx-confirm="' . esc_attr__( 'The audience is deleted in Meta too. Ad sets that use it stop reaching these people.', 'flx-ads' ) . '" data-flx-confirm-danger="1">';
				wp_nonce_field( 'flxads_aud' );
				echo '<input type="hidden" name="action" value="flxads_aud"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="' . esc_attr( $a['id'] ) . '"><button class="button button-small button-link-delete">' . esc_html__( 'Delete', 'flx-ads' ) . '</button></form>';
			}
			echo '</div></td></tr>';
		}
		echo '</tbody></table></div>';

		if ( ! $man || ! FLXADS_Spend::meta_ready() ) {
			return;
		}
		echo '<h2 class="flxads-h2">' . esc_html__( 'New audience', 'flx-ads' ) . '</h2>';
		echo '<section class="flxads-card flxads-pad"><form class="flxads-builder" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'flxads_aud' );
		echo '<input type="hidden" name="action" value="flxads_aud"><input type="hidden" name="do" value="create">';
		echo '<label><span>' . esc_html__( 'Name in Meta', 'flx-ads' ) . '</span><input name="name" required maxlength="100" placeholder="' . esc_attr__( 'Donors – last 12 months', 'flx-ads' ) . '"></label>';
		echo '<label><span>' . esc_html__( 'Group', 'flx-ads' ) . '</span><select name="source">';
		foreach ( $src as $k => $l ) {
			/* translators: %s: count */
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l . ' (' . sprintf( __( '%s people', 'flx-ads' ), number_format_i18n( count( FLXADS_Audiences::member_ids( $k ) ) ) ) . ')' ) . '</option>';
		}
		echo '</select></label>';
		echo '<label><span>' . esc_html__( 'Use', 'flx-ads' ) . '</span><select name="purpose">';
		foreach ( $pur as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label class="flxads-check"><input type="checkbox" name="lookalike" value="1" data-flxads-toggle="lk"> <span>' . esc_html__( 'Also create a lookalike audience (people similar to this group)', 'flx-ads' ) . '</span></label>';
		echo '<label data-flxads-show="lk" hidden><span>' . esc_html__( 'Lookalike country', 'flx-ads' ) . '</span><input name="country" value="TR" maxlength="2"></label>';
		echo '<label data-flxads-show="lk" hidden><span>' . esc_html__( 'Lookalike size', 'flx-ads' ) . '</span><select name="ratio">';
		foreach ( array( 1, 2, 3, 5, 10 ) as $p ) {
			/* translators: %d: percent */
			echo '<option value="' . (int) $p . '">' . esc_html( sprintf( __( '%d%% of the country', 'flx-ads' ), $p ) ) . '</option>';
		}
		echo '</select></label>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Create in Meta', 'flx-ads' ) . '</button></p></form>';
		echo '<p class="flxads-muted">' . esc_html__( 'Meta needs at least 100 matched people for a lookalike. 1% is the closest match; larger sizes reach more people with less similarity.', 'flx-ads' ) . '</p></section>';

		$lk = array_filter(
			$all,
			static function ( $a ) {
				return 'custom' === $a['kind'];
			}
		);
		if ( $lk ) {
			echo '<h2 class="flxads-h2">' . esc_html__( 'Lookalike from an existing audience', 'flx-ads' ) . '</h2>';
			echo '<section class="flxads-card flxads-pad"><form class="flxads-builder" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'flxads_aud' );
			echo '<input type="hidden" name="action" value="flxads_aud"><input type="hidden" name="do" value="lookalike">';
			echo '<label><span>' . esc_html__( 'Source audience', 'flx-ads' ) . '</span><select name="id">';
			foreach ( $lk as $a ) {
				echo '<option value="' . esc_attr( $a['id'] ) . '">' . esc_html( $a['name'] ) . '</option>';
			}
			echo '</select></label>';
			echo '<label><span>' . esc_html__( 'Lookalike country', 'flx-ads' ) . '</span><input name="country" value="TR" maxlength="2"></label>';
			echo '<label><span>' . esc_html__( 'Lookalike size', 'flx-ads' ) . '</span><select name="ratio">';
			foreach ( array( 1, 2, 3, 5, 10 ) as $p ) {
				echo '<option value="' . (int) $p . '">' . esc_html( sprintf( __( '%d%% of the country', 'flx-ads' ), $p ) ) . '</option>';
			}
			echo '</select></label><p><button class="button">' . esc_html__( 'Create lookalike', 'flx-ads' ) . '</button></p></form></section>';
		}
	}

	public static function audience_action() {
		if ( ! flxads_can( true ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-ads' ) );
		}
		check_admin_referer( 'flxads_aud' );
		$p  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$do = sanitize_key( (string) ( $p['do'] ?? '' ) );
		$id = sanitize_key( (string) ( $p['id'] ?? '' ) );
		if ( 'create' === $do ) {
			$r = FLXADS_Audiences::create(
				array(
					'name'      => (string) ( $p['name'] ?? '' ),
					'source'    => sanitize_text_field( (string) ( $p['source'] ?? '' ) ),
					'purpose'   => sanitize_key( (string) ( $p['purpose'] ?? '' ) ),
					'lookalike' => ! empty( $p['lookalike'] ),
					'country'   => sanitize_text_field( (string) ( $p['country'] ?? 'TR' ) ),
					'ratio'     => (float) ( $p['ratio'] ?? 1 ),
				)
			);
			if ( ! is_wp_error( $r ) && ( ! empty( $r['sync_error'] ) || ! empty( $r['lookalike_error'] ) ) ) {
				$r = new WP_Error( 'flxads_partial', trim( ( $r['sync_error'] ?? '' ) . ' ' . ( $r['lookalike_error'] ?? '' ) ) );
			}
		} elseif ( 'lookalike' === $do ) {
			$r = FLXADS_Audiences::lookalike( $id, sanitize_text_field( (string) ( $p['country'] ?? 'TR' ) ), (float) ( $p['ratio'] ?? 1 ) );
		} elseif ( 'sync' === $do ) {
			$r = FLXADS_Audiences::sync( $id );
		} elseif ( 'delete' === $do ) {
			$r = FLXADS_Audiences::delete( $id );
		} else {
			$r = new WP_Error( 'flxads_bad', __( 'Unknown action.', 'flx-ads' ) );
		}
		if ( is_wp_error( $r ) ) {
			set_transient( 'flxads_aud_err_' . get_current_user_id(), $r->get_error_message(), 120 );
			$msg = 'aud_err';
		} else {
			$msg = 'aud_' . $do;
		}
		wp_safe_redirect( flxads_admin_url( 'audiences', array( 'flxads_msg' => $msg ) ) );
		exit;
	}

	/* ------------------------------------------------------------ Kampanyalar (Meta) */

	private static function mgr_range() {
		$r = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '7'; // phpcs:ignore WordPress.Security.NonceVerification
		return isset( FLXADS_Manager::ranges()[ $r ] ) ? $r : '7';
	}

	private static function mgr_status( $eff ) {
		$l    = FLXADS_Manager::statuses();
		$tone = 'ACTIVE' === $eff ? 'sent' : ( in_array( $eff, array( 'DISAPPROVED', 'WITH_ISSUES', 'PENDING_BILLING_INFO' ), true ) ? 'failed' : 'pending' );
		return '<span class="flxads-pill flxads-pill--' . esc_attr( $tone ) . '">' . esc_html( $l[ $eff ] ?? ucfirst( strtolower( str_replace( '_', ' ', $eff ) ) ) ) . '</span>';
	}

	private static function mgr_budget( array $o, $cur ) {
		if ( null !== $o['daily'] ) {
			/* translators: %s: amount */
			return sprintf( __( '%s / day', 'flx-ads' ), flxads_money( $o['daily'], $cur ) );
		}
		if ( null !== $o['lifetime'] ) {
			/* translators: %s: amount */
			return sprintf( __( '%s total', 'flx-ads' ), flxads_money( $o['lifetime'], $cur ) );
		}
		return '';
	}

	/** Aç/duraklat + günlük bütçe formları. */
	private static function mgr_controls( array $o, $cur, $back ) {
		if ( ! flxads_can( true ) ) {
			return;
		}
		$url = esc_url( admin_url( 'admin-post.php' ) );
		$hid = '<input type="hidden" name="action" value="flxads_mgr"><input type="hidden" name="id" value="' . esc_attr( $o['id'] ) . '"><input type="hidden" name="back" value="' . esc_attr( $back ) . '">';
		echo '<div class="flxads-actions">';
		if ( in_array( $o['status'], array( 'ACTIVE', 'PAUSED' ), true ) ) {
			$on  = 'ACTIVE' === $o['status'];
			$msg = $on
				/* translators: %s: name */
				? sprintf( __( '"%s" stops showing in Meta right away. You can turn it back on any time.', 'flx-ads' ), $o['name'] )
				/* translators: %s: name */
				: sprintf( __( '"%s" starts showing in Meta and spending its budget.', 'flx-ads' ), $o['name'] );
			echo '<form method="post" action="' . $url . '" data-flx-confirm="' . esc_attr( $msg ) . '" data-flx-confirm-ok="' . esc_attr( $on ? __( 'Pause', 'flx-ads' ) : __( 'Turn on', 'flx-ads' ) ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput
			wp_nonce_field( 'flxads_mgr' );
			echo $hid . '<input type="hidden" name="do" value="status"><input type="hidden" name="value" value="' . ( $on ? 'PAUSED' : 'ACTIVE' ) . '"><button class="button button-small' . ( $on ? '' : ' button-primary' ) . '">' . esc_html( $on ? __( 'Pause', 'flx-ads' ) : __( 'Turn on', 'flx-ads' ) ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		if ( null !== $o['daily'] ) {
			/* translators: 1: name, 2: current budget */
			$msg = sprintf( __( 'The daily budget of "%1$s" changes in Meta right away (now %2$s).', 'flx-ads' ), $o['name'], flxads_money( $o['daily'], $cur ) );
			echo '<form class="flxads-budget" method="post" action="' . $url . '" data-flx-confirm="' . esc_attr( $msg ) . '" data-flx-confirm-ok="' . esc_attr__( 'Change budget', 'flx-ads' ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput
			wp_nonce_field( 'flxads_mgr' );
			echo $hid . '<input type="hidden" name="do" value="budget"><label><span class="screen-reader-text">' . esc_html__( 'Daily budget', 'flx-ads' ) . '</span><input type="number" name="value" min="1" step="1" value="' . esc_attr( (string) round( $o['daily'] ) ) . '"></label><button class="button button-small">' . esc_html__( 'Save', 'flx-ads' ) . '</button></form>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';
	}

	private static function mgr_metrics_cells( array $o, $cur ) {
		echo '<td class="num" data-label="' . esc_attr__( 'Spend', 'flx-ads' ) . '">' . esc_html( flxads_money( $o['spend'], $cur ) ) . '</td>';
		echo '<td class="num" data-label="' . esc_attr__( 'Impressions', 'flx-ads' ) . '">' . esc_html( number_format_i18n( $o['impressions'] ) ) . '</td>';
		echo '<td class="num" data-label="' . esc_attr__( 'Link clicks', 'flx-ads' ) . '"><span>' . esc_html( number_format_i18n( $o['clicks'] ) ) . ( null !== $o['ctr'] ? '<small class="flxads-muted">' . esc_html( sprintf( /* translators: %s: CTR */ __( 'CTR %s%%', 'flx-ads' ), number_format_i18n( $o['ctr'], 2 ) ) ) . '</small>' : '' ) . '</span></td>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private static function tab_campaigns() {
		echo '<p class="flxads-lead">' . esc_html__( 'Your Meta campaigns, ad sets and ads with spend and the donations they brought on this site. Pause, turn on or change the daily budget here; every change goes to Meta at once and is written to the history below.', 'flx-ads' ) . '</p>';
		if ( ! FLXADS_Spend::meta_ready() ) {
			echo '<section class="flxads-card flxads-pad"><p>' . esc_html__( 'Enter the Meta ad account ID and a Marketing API key in Settings › Meta. Reading needs ads_read; pausing and budget changes need ads_management.', 'flx-ads' ) . '</p>';
			if ( flxads_can( true ) ) {
				echo '<p><a class="button" href="' . esc_url( flxads_admin_url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'flx-ads' ) . '</a></p>';
			}
			echo '</section>';
			return;
		}
		$range = self::mgr_range();
		$cid   = isset( $_GET['campaign'] ) ? preg_replace( '/\D+/', '', (string) wp_unslash( $_GET['campaign'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<nav class="flxads-seg" aria-label="' . esc_attr__( 'Period', 'flx-ads' ) . '">';
		foreach ( FLXADS_Manager::ranges() as $k => $l ) {
			$args = array( 'range' => $k );
			if ( '' !== $cid ) {
				$args['campaign'] = $cid;
			}
			echo '<a class="' . ( (string) $k === $range ? 'is-on' : '' ) . '" href="' . esc_url( flxads_admin_url( 'campaigns', $args ) ) . '"' . ( (string) $k === $range ? ' aria-current="true"' : '' ) . '>' . esc_html( $l ) . '</a>';
		}
		echo '</nav>';
		if ( '' !== $cid ) {
			self::mgr_campaign( $cid, $range );
		} else {
			self::mgr_list( $range );
		}
		self::mgr_history();
	}

	private static function mgr_list( $range ) {
		$d = FLXADS_Manager::campaigns( $range );
		if ( is_wp_error( $d ) ) {
			echo '<section class="flxads-card flxads-pad"><p class="flxads-test flxads-test--bad">' . esc_html( __( 'Meta could not be read:', 'flx-ads' ) . ' ' . $d->get_error_message() ) . '</p></section>';
			return;
		}
		$cur = $d['currency'];
		$t   = $d['totals'];
		echo '<div class="flxads-kpis">';
		self::kpi( __( 'Ad spend', 'flx-ads' ), flxads_money( $t['spend'], $cur ), flxads_date( $d['since'], 'date' ) . ' – ' . flxads_date( $d['until'], 'date' ) );
		/* translators: %s: payments */
		self::kpi( __( 'Brought on the site', 'flx-ads' ), flxads_money( $t['income'], $d['base'] ), sprintf( __( '%s payments', 'flx-ads' ), number_format_i18n( $t['payments'] ) ) );
		self::kpi( __( 'Return on ad spend (ROAS)', 'flx-ads' ), self::roas( $t['roas'] ) );
		/* translators: %s: count */
		self::kpi( __( 'Active campaigns', 'flx-ads' ), number_format_i18n( $t['active'] ), sprintf( __( '%s in total', 'flx-ads' ), number_format_i18n( count( $d['rows'] ) ) ) );
		echo '</div>';
		echo '<div class="flxads-card"><table class="flxads-table flxads-mgr"><thead><tr><th>' . esc_html__( 'Campaign', 'flx-ads' ) . '</th><th>' . esc_html__( 'Budget', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Spend', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Impressions', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Link clicks', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'On the site', 'flx-ads' ) . '</th><th class="num">ROAS</th><th></th></tr></thead><tbody>';
		if ( ! $d['rows'] ) {
			echo '<tr><td colspan="8" class="flxads-empty">' . esc_html__( 'No campaigns in this ad account.', 'flx-ads' ) . '</td></tr>';
		}
		foreach ( $d['rows'] as $c ) {
			echo '<tr><td data-label="' . esc_attr__( 'Campaign', 'flx-ads' ) . '"><a class="flxads-mgr__name" href="' . esc_url( flxads_admin_url( 'campaigns', array( 'campaign' => $c['id'], 'range' => $range ) ) ) . '">' . esc_html( $c['name'] ) . '</a> ' . self::mgr_status( $c['effective'] ) . '<small class="flxads-muted">' . esc_html( self::objective( $c['objective'] ?? '' ) ) . '</small></td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			$b = self::mgr_budget( $c, $cur );
			echo '<td data-label="' . esc_attr__( 'Budget', 'flx-ads' ) . '">' . ( '' !== $b ? esc_html( $b ) : '<span class="flxads-muted">' . esc_html__( 'in ad sets', 'flx-ads' ) . '</span>' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			self::mgr_metrics_cells( $c, $cur );
			echo '<td class="num" data-label="' . esc_attr__( 'On the site', 'flx-ads' ) . '">' . ( $c['payments'] ? '<span>' . esc_html( flxads_money( $c['income'], $d['base'] ) ) . '<small class="flxads-muted">' . esc_html( sprintf( /* translators: %s: payments */ _n( '%s payment', '%s payments', $c['payments'], 'flx-ads' ), number_format_i18n( $c['payments'] ) ) ) . '</small></span>' : '<span class="flxads-muted">—</span>' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td class="num" data-label="ROAS"><span class="flxads-roas flxads-roas--' . esc_attr( self::roas_level( $c['roas'] ) ) . '">' . esc_html( self::roas( $c['roas'] ) ) . '</span></td><td>';
			self::mgr_controls( $c, $cur, 'list:' . $range );
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<p class="flxads-muted">' . esc_html__( 'Donations on the site are matched by campaign name or ID in the ad link (utm_campaign). Meta numbers are refreshed every 10 minutes.', 'flx-ads' ) . '</p>';
	}

	private static function objective( $o ) {
		$m = array(
			'OUTCOME_SALES'         => __( 'Sales', 'flx-ads' ),
			'OUTCOME_LEADS'         => __( 'Leads', 'flx-ads' ),
			'OUTCOME_TRAFFIC'       => __( 'Traffic', 'flx-ads' ),
			'OUTCOME_ENGAGEMENT'    => __( 'Engagement', 'flx-ads' ),
			'OUTCOME_AWARENESS'     => __( 'Awareness', 'flx-ads' ),
			'OUTCOME_APP_PROMOTION' => __( 'App promotion', 'flx-ads' ),
		);
		return $m[ $o ] ?? ucfirst( strtolower( str_replace( array( 'OUTCOME_', '_' ), array( '', ' ' ), (string) $o ) ) );
	}

	private static function mgr_campaign( $cid, $range ) {
		$c = FLXADS_Manager::campaign( $cid, $range );
		echo '<p><a href="' . esc_url( flxads_admin_url( 'campaigns', array( 'range' => $range ) ) ) . '">← ' . esc_html__( 'All campaigns', 'flx-ads' ) . '</a></p>';
		if ( is_wp_error( $c ) ) {
			echo '<section class="flxads-card flxads-pad"><p class="flxads-test flxads-test--bad">' . esc_html( __( 'Meta could not be read:', 'flx-ads' ) . ' ' . $c->get_error_message() ) . '</p></section>';
			return;
		}
		$cur  = $c['currency'];
		$back = 'campaign:' . $c['id'] . ':' . $range;
		echo '<section class="flxads-card flxads-pad flxads-mgr__head"><div><h2>' . esc_html( $c['name'] ) . ' ' . self::mgr_status( $c['effective'] ) . '</h2>'; // phpcs:ignore WordPress.Security.EscapeOutput
		$bits = array_filter( array( self::objective( $c['objective'] ?? '' ), self::mgr_budget( $c, $cur ), sprintf( /* translators: %s: amount */ __( '%s spent', 'flx-ads' ), flxads_money( $c['spend'], $cur ) ) ) );
		echo '<p class="flxads-muted">' . esc_html( implode( ' · ', $bits ) ) . '</p></div>';
		self::mgr_controls( $c, $cur, $back );
		echo '</section>';

		echo '<h2 class="flxads-h2">' . esc_html__( 'Ad sets and ads', 'flx-ads' ) . '</h2>';
		echo '<div class="flxads-card"><table class="flxads-table flxads-mgr"><thead><tr><th>' . esc_html__( 'Ad set / ad', 'flx-ads' ) . '</th><th>' . esc_html__( 'Budget', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Spend', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Impressions', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Link clicks', 'flx-ads' ) . '</th><th></th></tr></thead><tbody>';
		if ( ! $c['adsets'] ) {
			echo '<tr><td colspan="6" class="flxads-empty">' . esc_html__( 'No ad sets.', 'flx-ads' ) . '</td></tr>';
		}
		$gl = array( 'all' => __( 'all genders', 'flx-ads' ), 'male' => __( 'men', 'flx-ads' ), 'female' => __( 'women', 'flx-ads' ) );
		foreach ( $c['adsets'] as $s ) {
			$tg  = $s['targeting'] ?? array();
			$who = $tg ? sprintf( /* translators: 1: min age, 2: max age, 3: gender, 4: places */ __( '%1$d–%2$d, %3$s, %4$s', 'flx-ads' ), $tg['age_min'], $tg['age_max'], $gl[ $tg['genders'] ] ?? '', implode( ', ', array_merge( $tg['countries'], $tg['cities'] ) ) ) : '';
			if ( $tg && ( $tg['audiences'] || $tg['excluded'] ) ) {
				/* translators: 1: included, 2: excluded */
				$who .= ' · ' . sprintf( __( 'audiences +%1$d / −%2$d', 'flx-ads' ), $tg['audiences'], $tg['excluded'] );
			}
			echo '<tr class="flxads-mgr__set"><td data-label="' . esc_attr__( 'Ad set', 'flx-ads' ) . '"><strong>' . esc_html( $s['name'] ) . '</strong> ' . self::mgr_status( $s['effective'] ) . ( '' !== $who ? '<small class="flxads-muted">' . esc_html( $who ) . '</small>' : '' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td data-label="' . esc_attr__( 'Budget', 'flx-ads' ) . '">' . esc_html( self::mgr_budget( $s, $cur ) ) . '</td>';
			self::mgr_metrics_cells( $s, $cur );
			echo '<td>';
			self::mgr_controls( $s, $cur, $back );
			echo '</td></tr>';
			foreach ( $s['ads'] as $a ) {
				$cr = $a['creative'] ?? array();
				echo '<tr class="flxads-mgr__ad"><td data-label="' . esc_attr__( 'Ad', 'flx-ads' ) . '"><div class="flxads-mgr__adcell">' . ( ! empty( $cr['thumb'] ) ? '<img src="' . esc_url( $cr['thumb'] ) . '" alt="" width="40" height="40" loading="lazy">' : '' ) . '<div>' . esc_html( $a['name'] ) . ' ' . self::mgr_status( $a['effective'] ) . ( ! empty( $cr['title'] ) ? '<small class="flxads-muted">' . esc_html( $cr['title'] ) . '</small>' : '' ) . '</div></div></td><td></td>'; // phpcs:ignore WordPress.Security.EscapeOutput
				self::mgr_metrics_cells( $a, $cur );
				echo '<td>';
				self::mgr_controls( $a, $cur, $back );
				echo '</td></tr>';
			}
		}
		echo '</tbody></table></div>';

		$bd = $c['breakdown'];
		echo '<div class="flxads-bd">';
		self::mgr_breakdown( __( 'Age and gender', 'flx-ads' ), $bd['age_gender'], $cur, static function ( $k ) use ( $gl ) {
			$g = array( 'male' => $gl['male'], 'female' => $gl['female'], 'unknown' => __( 'unknown', 'flx-ads' ) );
			return $k[0] . ' · ' . ( $g[ $k[1] ] ?? $k[1] );
		} );
		self::mgr_breakdown( __( 'Placement', 'flx-ads' ), $bd['placement'], $cur, static function ( $k ) {
			$pl = array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'audience_network' => 'Audience Network', 'messenger' => 'Messenger', 'threads' => 'Threads' );
			return ( $pl[ $k[0] ] ?? ucfirst( str_replace( '_', ' ', $k[0] ) ) ) . ' · ' . str_replace( '_', ' ', $k[1] );
		} );
		echo '</div>';
	}

	private static function mgr_breakdown( $title, array $rows, $cur, callable $label ) {
		echo '<section class="flxads-card"><h3 class="flxads-h3">' . esc_html( $title ) . '</h3><table class="flxads-table"><thead><tr><th></th><th class="num">' . esc_html__( 'Spend', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Link clicks', 'flx-ads' ) . '</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="3" class="flxads-empty">' . esc_html__( 'No data for this period.', 'flx-ads' ) . '</td></tr>';
		}
		$max = $rows ? max( 0.01, max( array_column( $rows, 'spend' ) ) ) : 1;
		foreach ( array_slice( $rows, 0, 12 ) as $r ) {
			echo '<tr><td><span class="flxads-bar" style="--w:' . esc_attr( (string) round( 100 * $r['spend'] / $max ) ) . '%"></span>' . esc_html( $label( $r['key'] ) ) . '</td><td class="num">' . esc_html( flxads_money( $r['spend'], $cur ) ) . '</td><td class="num">' . esc_html( number_format_i18n( $r['clicks'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
	}

	private static function mgr_history() {
		$h = FLXADS_Manager::history( 15 );
		if ( ! $h ) {
			return;
		}
		$ch = array( 'panel' => __( 'Panel', 'flx-ads' ), 'ai' => 'Flux AI', 'mcp' => 'MCP' );
		echo '<h2 class="flxads-h2">' . esc_html__( 'Change history', 'flx-ads' ) . '</h2><div class="flxads-card"><table class="flxads-table"><thead><tr><th>' . esc_html__( 'When', 'flx-ads' ) . '</th><th>' . esc_html__( 'What', 'flx-ads' ) . '</th><th>' . esc_html__( 'Change', 'flx-ads' ) . '</th><th>' . esc_html__( 'Who', 'flx-ads' ) . '</th></tr></thead><tbody>';
		$st = FLXADS_Manager::statuses();
		foreach ( $h as $r ) {
			$u   = get_userdata( (int) $r['user'] );
			$chg = 'status' === $r['field'] ? ( $st[ $r['from'] ] ?? $r['from'] ) . ' → ' . ( $st[ $r['to'] ] ?? $r['to'] ) : sprintf( /* translators: 1: old, 2: new */ __( 'Daily budget %1$s → %2$s', 'flx-ads' ), number_format_i18n( (float) $r['from'], 2 ), number_format_i18n( (float) $r['to'], 2 ) );
			echo '<tr><td data-label="' . esc_attr__( 'When', 'flx-ads' ) . '">' . esc_html( flxads_ago( $r['at'] ) ) . '</td><td data-label="' . esc_attr__( 'What', 'flx-ads' ) . '">' . esc_html( $r['name'] ) . '</td><td data-label="' . esc_attr__( 'Change', 'flx-ads' ) . '">' . esc_html( $chg ) . '</td><td data-label="' . esc_attr__( 'Who', 'flx-ads' ) . '">' . esc_html( ( $u ? $u->display_name : '—' ) . ' · ' . ( $ch[ $r['channel'] ] ?? $r['channel'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function mgr_action() {
		if ( ! flxads_can( true ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-ads' ) );
		}
		check_admin_referer( 'flxads_mgr' );
		$p  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$do = sanitize_key( (string) ( $p['do'] ?? '' ) );
		$id = preg_replace( '/\D+/', '', (string) ( $p['id'] ?? '' ) );
		if ( 'status' === $do ) {
			$r = FLXADS_Manager::set_status( $id, sanitize_key( (string) ( $p['value'] ?? '' ) ), 'panel' );
		} elseif ( 'budget' === $do ) {
			$r = FLXADS_Manager::set_budget( $id, (float) ( $p['value'] ?? 0 ), 'panel' );
		} else {
			$r = new WP_Error( 'flxads_bad', __( 'Unknown action.', 'flx-ads' ) );
		}
		if ( is_wp_error( $r ) ) {
			set_transient( 'flxads_aud_err_' . get_current_user_id(), $r->get_error_message(), 120 );
			$args = array( 'flxads_msg' => 'aud_err' );
		} else {
			$args = array( 'flxads_msg' => 'mgr_' . $do );
		}
		$back = explode( ':', sanitize_text_field( (string) ( $p['back'] ?? '' ) ) );
		if ( 'campaign' === ( $back[0] ?? '' ) ) {
			$args += array( 'campaign' => preg_replace( '/\D+/', '', (string) ( $back[1] ?? '' ) ), 'range' => sanitize_key( (string) ( $back[2] ?? '7' ) ) );
		} elseif ( 'list' === ( $back[0] ?? '' ) ) {
			$args['range'] = sanitize_key( (string) ( $back[1] ?? '7' ) );
		}
		wp_safe_redirect( flxads_admin_url( 'campaigns', $args ) );
		exit;
	}

	/* ------------------------------------------------------------ Stüdyo (AI taslakları) */

	private static function tab_studio() {
		$man  = flxads_can( true );
		$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' !== $edit && $man ) {
			self::studio_editor( 'new' === $edit ? null : FLXADS_Studio::get( $edit ) );
			return;
		}
		echo '<p class="flxads-lead">' . esc_html__( 'Flux AI writes a campaign draft from your brief and this site\'s numbers: ad sets, audiences, budget split and ad text variants. You read and edit it here. "Send to Meta" creates everything paused; nothing is shown or spent until you turn it on under Campaigns.', 'flx-ads' ) . '</p>';
		$miss = FLXADS_Studio::missing();
		if ( $miss ) {
			/* translators: %s: list */
			echo '<section class="flxads-card flxads-pad"><p>' . esc_html( sprintf( __( 'You can write drafts now. To send them to Meta, add: %s.', 'flx-ads' ), implode( ', ', $miss ) ) ) . '</p>' . ( $man ? '<p><a class="button" href="' . esc_url( flxads_admin_url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'flx-ads' ) . '</a></p>' : '' ) . '</section>';
		}
		if ( $man ) {
			$ai = function_exists( 'flxai_ready' ) && flxai_ready( 'flx-ads' );
			echo '<h2 class="flxads-h2">' . esc_html__( 'Write a draft with AI', 'flx-ads' ) . '</h2><section class="flxads-card flxads-pad">';
			if ( ! $ai ) {
				echo '<p class="flxads-muted">' . esc_html__( 'Flux AI is not ready. Install Flux AI & MCP and add a model key, or start from an empty draft.', 'flx-ads' ) . '</p>';
			}
			echo '<form class="flxads-builder flxads-brief" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'flxads_studio' );
			echo '<input type="hidden" name="action" value="flxads_studio"><input type="hidden" name="do" value="generate">';
			echo '<label><span>' . esc_html__( 'Brief', 'flx-ads' ) . '</span><textarea name="brief" rows="4" required maxlength="2000" placeholder="' . esc_attr__( 'e.g. Sapling campaign for November; reach people like our donors in Istanbul and Ankara, leave out people who donated this month.', 'flx-ads' ) . '"></textarea></label>';
			echo '<label><span>' . esc_html__( 'Page the ads open', 'flx-ads' ) . '</span><input type="url" name="link" placeholder="' . esc_attr( home_url( '/' ) ) . '"></label>';
			echo '<label><span>' . esc_html__( 'Daily budget', 'flx-ads' ) . '</span><input type="number" name="daily_budget" min="1" step="1" required placeholder="500"></label>';
			echo '<label><span>' . esc_html__( 'Goal', 'flx-ads' ) . '</span><select name="objective">';
			foreach ( FLXADS_Studio::objectives() as $k => $l ) {
				echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
			}
			echo '</select></label>';
			echo '<label><span>' . esc_html__( 'Start', 'flx-ads' ) . '</span><input type="date" name="start"></label>';
			echo '<label><span>' . esc_html__( 'End', 'flx-ads' ) . '</span><input type="date" name="end"></label>';
			echo '<p class="flxads-actions flxads-actions--start"><button class="button button-primary"' . ( $ai ? '' : ' disabled' ) . '>' . esc_html__( 'Write draft', 'flx-ads' ) . '</button> <a class="button" href="' . esc_url( flxads_admin_url( 'studio', array( 'edit' => 'new' ) ) ) . '">' . esc_html__( 'Empty draft', 'flx-ads' ) . '</a></p>';
			echo '</form></section>';
		}

		$all = FLXADS_Studio::all();
		$st  = FLXADS_Studio::statuses();
		$ob  = FLXADS_Studio::objectives();
		echo '<h2 class="flxads-h2">' . esc_html__( 'Drafts', 'flx-ads' ) . '</h2><div class="flxads-card"><table class="flxads-table"><thead><tr><th>' . esc_html__( 'Campaign', 'flx-ads' ) . '</th><th>' . esc_html__( 'Goal', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Daily budget', 'flx-ads' ) . '</th><th class="num">' . esc_html__( 'Ad sets / ads', 'flx-ads' ) . '</th><th>' . esc_html__( 'Updated', 'flx-ads' ) . '</th><th></th></tr></thead><tbody>';
		if ( ! $all ) {
			echo '<tr><td colspan="6" class="flxads-empty">' . esc_html__( 'No drafts yet.', 'flx-ads' ) . '</td></tr>';
		}
		$cur = FLXADS_Spend::meta_ready() ? FLXADS_Manager::currency() : FLXADS_Report::base_currency();
		$url = esc_url( admin_url( 'admin-post.php' ) );
		foreach ( $all as $d ) {
			$tone = array( 'draft' => 'pending', 'sent' => 'sent', 'error' => 'failed' )[ $d['status'] ] ?? 'pending';
			echo '<tr><td data-label="' . esc_attr__( 'Campaign', 'flx-ads' ) . '"><a class="flxads-mgr__name" href="' . esc_url( flxads_admin_url( 'studio', array( 'edit' => $d['id'] ) ) ) . '">' . esc_html( $d['name'] ) . '</a> <span class="flxads-pill flxads-pill--' . esc_attr( $tone ) . '">' . esc_html( $st[ $d['status'] ] ?? $d['status'] ) . '</span>' . ( 'ai' === $d['by'] ? ' <span class="flxads-pill">AI</span>' : '' ) . ( '' !== (string) $d['error'] ? '<small class="flxads-test flxads-test--bad">' . esc_html( $d['error'] ) . '</small>' : '' ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Goal', 'flx-ads' ) . '">' . esc_html( $ob[ $d['objective'] ] ?? $d['objective'] ) . '</td>';
			echo '<td class="num" data-label="' . esc_attr__( 'Daily budget', 'flx-ads' ) . '">' . esc_html( flxads_money( $d['daily_budget'], $cur ) ) . '</td>';
			echo '<td class="num" data-label="' . esc_attr__( 'Ad sets / ads', 'flx-ads' ) . '">' . (int) count( $d['adsets'] ) . ' / ' . (int) count( $d['ads'] ) . '</td>';
			echo '<td data-label="' . esc_attr__( 'Updated', 'flx-ads' ) . '">' . esc_html( flxads_ago( $d['updated'] ) ) . '</td><td><div class="flxads-actions">';
			if ( 'sent' === $d['status'] && ! empty( $d['meta']['campaign'] ) ) {
				echo '<a class="button button-small" href="' . esc_url( flxads_admin_url( 'campaigns', array( 'campaign' => $d['meta']['campaign'] ) ) ) . '">' . esc_html__( 'Open in Campaigns', 'flx-ads' ) . '</a>';
			}
			if ( $man ) {
				foreach ( array( 'duplicate' => __( 'Duplicate', 'flx-ads' ) ) as $do => $l ) {
					echo '<form method="post" action="' . $url . '">'; // phpcs:ignore WordPress.Security.EscapeOutput
					wp_nonce_field( 'flxads_studio' );
					echo '<input type="hidden" name="action" value="flxads_studio"><input type="hidden" name="do" value="' . esc_attr( $do ) . '"><input type="hidden" name="id" value="' . esc_attr( $d['id'] ) . '"><button class="button button-small">' . esc_html( $l ) . '</button></form>';
				}
				echo '<form method="post" action="' . $url . '" data-flx-confirm="' . esc_attr( 'sent' === $d['status'] ? __( 'Only the draft here is deleted. The paused campaign stays in Meta.', 'flx-ads' ) : __( 'The draft is deleted.', 'flx-ads' ) ) . '" data-flx-confirm-danger="1">'; // phpcs:ignore WordPress.Security.EscapeOutput
				wp_nonce_field( 'flxads_studio' );
				echo '<input type="hidden" name="action" value="flxads_studio"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="' . esc_attr( $d['id'] ) . '"><button class="button button-small button-link-delete">' . esc_html__( 'Delete', 'flx-ads' ) . '</button></form>';
			}
			echo '</div></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	private static function studio_adset_row( $i, array $s, array $auds ) {
		$n = 's[adsets][' . $i . ']';
		echo '<div class="flxads-card flxads-pad flxads-row" data-flxads-row="adsets"><button type="button" class="flxads-row__del" data-flxads-del aria-label="' . esc_attr__( 'Remove', 'flx-ads' ) . '">×</button><div class="flxads-builder">';
		echo '<label><span>' . esc_html__( 'Ad set name', 'flx-ads' ) . '</span><input name="' . esc_attr( $n ) . '[name]" value="' . esc_attr( (string) ( $s['name'] ?? '' ) ) . '" maxlength="120"></label>';
		echo '<label><span>' . esc_html__( 'Ages', 'flx-ads' ) . '</span><span class="flxads-inline"><input type="number" min="18" max="65" name="' . esc_attr( $n ) . '[age_min]" value="' . (int) ( $s['age_min'] ?? 18 ) . '"> – <input type="number" min="18" max="65" name="' . esc_attr( $n ) . '[age_max]" value="' . (int) ( $s['age_max'] ?? 65 ) . '"></span></label>';
		echo '<label><span>' . esc_html__( 'Gender', 'flx-ads' ) . '</span><select name="' . esc_attr( $n ) . '[gender]">';
		foreach ( array( 'all' => __( 'all genders', 'flx-ads' ), 'female' => __( 'women', 'flx-ads' ), 'male' => __( 'men', 'flx-ads' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( (string) ( $s['gender'] ?? 'all' ), $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label><span>' . esc_html__( 'Countries (ISO, comma)', 'flx-ads' ) . '</span><input name="' . esc_attr( $n ) . '[countries]" value="' . esc_attr( implode( ', ', (array) ( $s['countries'] ?? array( 'TR' ) ) ) ) . '"></label>';
		echo '<label><span>' . esc_html__( 'Budget share %', 'flx-ads' ) . '</span><input type="number" min="0" max="100" name="' . esc_attr( $n ) . '[share]" value="' . (int) ( $s['share'] ?? 0 ) . '"></label>';
		if ( $auds ) {
			foreach ( array( 'audiences' => __( 'Show to audiences', 'flx-ads' ), 'exclude' => __( 'Leave out audiences', 'flx-ads' ) ) as $k => $l ) {
				echo '<fieldset class="flxads-auds"><legend>' . esc_html( $l ) . '</legend>';
				foreach ( $auds as $a ) {
					echo '<label class="flxads-check"><input type="checkbox" name="' . esc_attr( $n . '[' . $k . '][]' ) . '" value="' . esc_attr( $a['id'] ) . '"' . checked( in_array( $a['id'], (array) ( $s[ $k ] ?? array() ), true ), true, false ) . '> ' . esc_html( $a['name'] ) . '</label>';
				}
				echo '</fieldset>';
			}
		}
		echo '</div></div>';
	}

	private static function studio_ad_row( $i, array $a ) {
		$n = 's[ads][' . $i . ']';
		echo '<div class="flxads-card flxads-pad flxads-row flxads-adrow" data-flxads-row="ads"><button type="button" class="flxads-row__del" data-flxads-del aria-label="' . esc_attr__( 'Remove', 'flx-ads' ) . '">×</button><div class="flxads-builder">';
		echo '<label><span>' . esc_html__( 'Variant name', 'flx-ads' ) . '</span><input name="' . esc_attr( $n ) . '[name]" value="' . esc_attr( (string) ( $a['name'] ?? '' ) ) . '" maxlength="80"></label>';
		echo '<label class="flxads-wide"><span>' . esc_html__( 'Primary text', 'flx-ads' ) . '</span><textarea rows="3" data-k="text" name="' . esc_attr( $n ) . '[text]" maxlength="2000">' . esc_textarea( (string) ( $a['text'] ?? '' ) ) . '</textarea></label>';
		echo '<label><span>' . esc_html__( 'Headline', 'flx-ads' ) . '</span><input data-k="headline" name="' . esc_attr( $n ) . '[headline]" value="' . esc_attr( (string) ( $a['headline'] ?? '' ) ) . '" maxlength="100"></label>';
		echo '<label><span>' . esc_html__( 'Description', 'flx-ads' ) . '</span><input data-k="description" name="' . esc_attr( $n ) . '[description]" value="' . esc_attr( (string) ( $a['description'] ?? '' ) ) . '" maxlength="120"></label>';
		echo '<label><span>' . esc_html__( 'Button', 'flx-ads' ) . '</span><select name="' . esc_attr( $n ) . '[cta]">';
		foreach ( FLXADS_Studio::ctas() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( (string) ( $a['cta'] ?? 'DONATE_NOW' ), $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label><span>' . esc_html__( 'Image address (optional)', 'flx-ads' ) . '</span><input type="url" data-k="image" name="' . esc_attr( $n ) . '[image]" value="' . esc_attr( (string) ( $a['image'] ?? '' ) ) . '" placeholder="https://…/image.jpg"></label>';
		echo '</div>';
		$img = (string) ( $a['image'] ?? '' );
		echo '<div class="flxads-pv" data-flxads-pv aria-hidden="true"><p data-pv="text">' . esc_html( (string) ( $a['text'] ?? '' ) ) . '</p><div class="flxads-pv__img" data-pv="image"' . ( '' !== $img ? ' style="background-image:url(&quot;' . esc_url( $img ) . '&quot;)"' : '' ) . '></div><div class="flxads-pv__foot"><div><small>' . esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) ) . '</small><strong data-pv="headline">' . esc_html( (string) ( $a['headline'] ?? '' ) ) . '</strong><small data-pv="description">' . esc_html( (string) ( $a['description'] ?? '' ) ) . '</small></div><span>' . esc_html( FLXADS_Studio::ctas()[ $a['cta'] ?? 'DONATE_NOW' ] ?? '' ) . '</span></div></div>';
		echo '</div>';
	}

	private static function studio_editor( $d ) {
		if ( null === $d && isset( $_GET['edit'] ) && 'new' !== $_GET['edit'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<section class="flxads-card flxads-pad"><p>' . esc_html__( 'Draft not found.', 'flx-ads' ) . '</p></section>';
			return;
		}
		$d    = $d ? $d : array(
			'id'           => '',
			'status'       => 'draft',
			'name'         => '',
			'objective'    => 'OUTCOME_SALES',
			'link'         => home_url( '/' ),
			'daily_budget' => '',
			'start'        => '',
			'end'          => '',
			'adsets'       => array( array( 'name' => '', 'age_min' => 25, 'age_max' => 65, 'gender' => 'all', 'countries' => array( 'TR' ), 'share' => 100 ) ),
			'ads'          => array( array( 'cta' => 'DONATE_NOW' ) ),
			'notes'        => '',
			'brief'        => '',
		);
		/* Kaydetme hatası: yazılanlar kaybolmasın. */
		$back = get_transient( 'flxads_studio_form_' . get_current_user_id() );
		if ( is_array( $back ) && (string) $back['id'] === (string) $d['id'] ) {
			delete_transient( 'flxads_studio_form_' . get_current_user_id() );
			$f = (array) $back['s'];
			foreach ( array( 'name', 'objective', 'link', 'daily_budget', 'start', 'end' ) as $k ) {
				$d[ $k ] = sanitize_text_field( (string) ( $f[ $k ] ?? '' ) );
			}
			$d['adsets'] = array_values( array_map( static function ( $x ) { $x = (array) $x; $x['countries'] = explode( ',', (string) ( $x['countries'] ?? '' ) ); return $x; }, (array) ( $f['adsets'] ?? array() ) ) ) ?: $d['adsets'];
			$d['ads']    = array_values( (array) ( $f['ads'] ?? array() ) ) ?: $d['ads'];
		}
		$sent = 'sent' === $d['status'];
		$auds = array_values( FLXADS_Audiences::all() );
		echo '<p><a href="' . esc_url( flxads_admin_url( 'studio' ) ) . '">← ' . esc_html__( 'All drafts', 'flx-ads' ) . '</a></p>';
		if ( $sent ) {
			echo '<section class="flxads-card flxads-pad"><p>' . esc_html__( 'This draft is in Meta as a paused campaign. Turn it on or change it under Campaigns; to try a new version, duplicate it.', 'flx-ads' ) . '</p><p><a class="button button-primary" href="' . esc_url( flxads_admin_url( 'campaigns', array( 'campaign' => $d['meta']['campaign'] ?? '' ) ) ) . '">' . esc_html__( 'Open in Campaigns', 'flx-ads' ) . '</a></p></section>';
		}
		if ( '' !== (string) ( $d['notes'] ?? '' ) ) {
			echo '<section class="flxads-card flxads-pad flxads-note"><h3>' . esc_html__( 'Why this plan (AI)', 'flx-ads' ) . '</h3><p>' . nl2br( esc_html( $d['notes'] ) ) . '</p></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxads-studio"><fieldset' . ( $sent ? ' disabled' : '' ) . '>';
		wp_nonce_field( 'flxads_studio' );
		echo '<input type="hidden" name="action" value="flxads_studio"><input type="hidden" name="do" value="save"><input type="hidden" name="id" value="' . esc_attr( $d['id'] ) . '"><input type="hidden" name="s[notes]" value="' . esc_attr( (string) $d['notes'] ) . '"><input type="hidden" name="s[brief]" value="' . esc_attr( (string) $d['brief'] ) . '">';
		echo '<h2 class="flxads-h2">' . esc_html__( 'Campaign', 'flx-ads' ) . '</h2><section class="flxads-card flxads-pad"><div class="flxads-builder">';
		echo '<label><span>' . esc_html__( 'Name', 'flx-ads' ) . '</span><input name="s[name]" required maxlength="120" value="' . esc_attr( $d['name'] ) . '"></label>';
		echo '<label><span>' . esc_html__( 'Goal', 'flx-ads' ) . '</span><select name="s[objective]">';
		foreach ( FLXADS_Studio::objectives() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $d['objective'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label>';
		echo '<label><span>' . esc_html__( 'Page the ads open', 'flx-ads' ) . '</span><input type="url" name="s[link]" required value="' . esc_attr( $d['link'] ) . '"></label>';
		echo '<label><span>' . esc_html__( 'Daily budget', 'flx-ads' ) . '</span><input type="number" name="s[daily_budget]" min="1" step="1" required value="' . esc_attr( (string) $d['daily_budget'] ) . '"></label>';
		echo '<label><span>' . esc_html__( 'Start', 'flx-ads' ) . '</span><input type="date" name="s[start]" value="' . esc_attr( $d['start'] ) . '"></label>';
		echo '<label><span>' . esc_html__( 'End', 'flx-ads' ) . '</span><input type="date" name="s[end]" value="' . esc_attr( $d['end'] ) . '"></label>';
		echo '</div></section>';

		echo '<h2 class="flxads-h2">' . esc_html__( 'Ad sets (who sees it)', 'flx-ads' ) . '</h2><div data-flxads-rows="adsets" data-max="6">';
		foreach ( $d['adsets'] as $i => $s ) {
			self::studio_adset_row( (string) $i, $s, $auds );
		}
		echo '</div><template data-flxads-tpl="adsets">';
		self::studio_adset_row( '__i__', array( 'age_min' => 25, 'age_max' => 65, 'countries' => array( 'TR' ) ), $auds );
		echo '</template><p><button type="button" class="button" data-flxads-add="adsets">+ ' . esc_html__( 'Add ad set', 'flx-ads' ) . '</button></p>';

		echo '<h2 class="flxads-h2">' . esc_html__( 'Ads (text variants)', 'flx-ads' ) . '</h2><p class="flxads-muted">' . esc_html__( 'Each variant runs in every ad set, so Meta can find the text that works best.', 'flx-ads' ) . '</p><div data-flxads-rows="ads" data-max="6">';
		foreach ( $d['ads'] as $i => $a ) {
			self::studio_ad_row( (string) $i, $a );
		}
		echo '</div><template data-flxads-tpl="ads">';
		self::studio_ad_row( '__i__', array( 'cta' => 'DONATE_NOW' ) );
		echo '</template><p><button type="button" class="button" data-flxads-add="ads">+ ' . esc_html__( 'Add variant', 'flx-ads' ) . '</button></p>';
		if ( ! $sent ) {
			echo '<p class="flxads-actions flxads-actions--start"><button class="button button-primary">' . esc_html__( 'Save draft', 'flx-ads' ) . '</button> <a class="button" href="' . esc_url( flxads_admin_url( 'studio' ) ) . '">' . esc_html__( 'Cancel', 'flx-ads' ) . '</a></p>';
		}
		echo '</fieldset></form>';

		if ( ! $sent && '' !== $d['id'] ) {
			$miss = FLXADS_Studio::missing();
			echo '<h2 class="flxads-h2">' . esc_html__( 'Send to Meta', 'flx-ads' ) . '</h2><section class="flxads-card flxads-pad">';
			if ( $miss ) {
				/* translators: %s: list */
				echo '<p>' . esc_html( sprintf( __( 'Missing: %s.', 'flx-ads' ), implode( ', ', $miss ) ) ) . '</p>';
			} else {
				/* translators: 1: ad sets, 2: ads */
				$msg = sprintf( __( 'A paused campaign with %1$d ad sets and %2$d ads is created in Meta. Nothing is shown or spent until you turn it on under Campaigns.', 'flx-ads' ), count( $d['adsets'] ), count( $d['adsets'] ) * count( $d['ads'] ) );
				echo '<p class="flxads-muted">' . esc_html__( 'Save your edits first. Ads open the page with UTM tags, so donations show up in the report and under Campaigns.', 'flx-ads' ) . '</p>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-flx-confirm="' . esc_attr( $msg ) . '" data-flx-confirm-ok="' . esc_attr__( 'Create paused', 'flx-ads' ) . '">';
				wp_nonce_field( 'flxads_studio' );
				echo '<input type="hidden" name="action" value="flxads_studio"><input type="hidden" name="do" value="send"><input type="hidden" name="id" value="' . esc_attr( $d['id'] ) . '"><button class="button button-primary">' . esc_html__( 'Create in Meta (paused)', 'flx-ads' ) . '</button></form>';
			}
			echo '</section>';
		}
	}

	public static function studio_action() {
		if ( ! flxads_can( true ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-ads' ) );
		}
		check_admin_referer( 'flxads_studio' );
		$p    = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$do   = sanitize_key( (string) ( $p['do'] ?? '' ) );
		$id   = sanitize_key( (string) ( $p['id'] ?? '' ) );
		$args = array();
		if ( 'generate' === $do ) {
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 90 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
			$r = FLXADS_Studio::generate( array_intersect_key( (array) $p, array_flip( array( 'brief', 'link', 'daily_budget', 'objective', 'start', 'end' ) ) ) );
			$args = is_wp_error( $r ) ? array() : array( 'edit' => $r, 'flxads_msg' => 'studio_ai' );
		} elseif ( 'save' === $do ) {
			$r    = FLXADS_Studio::save( (array) ( $p['s'] ?? array() ), $id );
			if ( is_wp_error( $r ) ) {
				set_transient( 'flxads_studio_form_' . get_current_user_id(), array( 'id' => $id, 's' => (array) ( $p['s'] ?? array() ) ), 300 );
			}
			$args = is_wp_error( $r ) ? array( 'edit' => '' !== $id ? $id : 'new' ) : array( 'edit' => $r, 'flxads_msg' => 'studio_saved' );
		} elseif ( 'duplicate' === $do ) {
			$r    = FLXADS_Studio::duplicate( $id );
			$args = is_wp_error( $r ) ? array() : array( 'edit' => $r, 'flxads_msg' => 'studio_saved' );
		} elseif ( 'delete' === $do ) {
			$r    = FLXADS_Studio::delete( $id ) ? true : new WP_Error( 'flxads_draft', __( 'Draft not found.', 'flx-ads' ) );
			$args = array( 'flxads_msg' => 'studio_deleted' );
		} elseif ( 'send' === $do ) {
			$r    = FLXADS_Studio::send( $id, 'panel' );
			$args = is_wp_error( $r ) ? array( 'edit' => $id ) : array( 'edit' => $id, 'flxads_msg' => 'studio_sent' );
		} else {
			$r = new WP_Error( 'flxads_bad', __( 'Unknown action.', 'flx-ads' ) );
		}
		if ( is_wp_error( $r ) ) {
			set_transient( 'flxads_aud_err_' . get_current_user_id(), $r->get_error_message(), 120 );
			$args['flxads_msg'] = 'studio_err';
		}
		wp_safe_redirect( flxads_admin_url( 'studio', $args ) );
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
				self::text_field( 'meta_page', __( 'Facebook Page ID', 'flx-ads' ), __( 'The page Studio ads are published as. Page › About › Page transparency › Page ID.', 'flx-ads' ), '1029384756' );
				self::secret_field( 'meta_ads_token', __( 'Marketing API token', 'flx-ads' ), __( 'Business Settings › Users › System users › add the ad account › Generate token. ads_read is enough for spend; audiences, Campaigns changes and Studio drafts need ads_management. Empty = the Conversions API token is tried. Flux Ads changes your ad account only when you approve it: an audience, a pause or budget change, or a Studio draft (always created paused).', 'flx-ads' ) );
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
		$v  = flxads_clean_settings( $in );
		foreach ( flxads_secret_keys() as $k ) {
			$v[ $k ] = trim( (string) ( $in[ $k ] ?? '' ) );
		}
		flxads_store_settings( $v );
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
