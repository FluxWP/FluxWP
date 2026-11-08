<?php
/**
 * Flux Forms: Dashboard admin page.
 *
 * Reads Flux Forms's own tables:
 * - {prefix}emsfb_form   forms (currency lives in form_structer[0].currency)
 * - {prefix}emsfb_msg_   responses; content = submitted JSON, read_ 0/3 = unread, 1/4 = read,
 *                        2 = draft waiting for an online payment (not a response yet)
 * - {prefix}emsfb_stts_  one row per form page view (session); pruned after ~8 days by EFB
 * - {prefix}emsfb_pay_   Stripe payments (created by vendor/stripe), created_at in UTC
 *
 * Charts are drawn by dashboard.js as hand-written SVG; nothing is loaded from a CDN.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FLXF_Dashboard {

	const SLUG       = 'flxf-dashboard';
	const NONCE      = 'flxf_dash';
	const CSV_NONCE  = 'flxf_dash_csv';
	const MAX_DAYS   = 731;
	const RECENT     = 10;

	/** Payment statuses (Stripe + EFB) grouped into the four buckets the chart shows. */
	const PAY_BUCKETS = array(
		'paid'     => array( 'completed', 'succeeded', 'paid', 'active', 'trialing' ),
		'pending'  => array( 'pending', 'processing', 'incomplete', 'requires_action', 'requires_payment_method' ),
		'failed'   => array( 'canceled', 'cancelled', 'failed', 'unpaid', 'past_due', 'incomplete_expired', 'mismatch' ),
		'refunded' => array( 'refunded', 'partially_refunded' ),
	);

	/** @var string */
	private static $hook = '';

	/** @var array<string,bool> */
	private static $tables = array();

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_ajax_flxf_dash_stats', array( __CLASS__, 'ajax_stats' ) );
		add_action( 'admin_post_flxf_dash_csv', array( __CLASS__, 'csv' ) );
	}

	/** Same rule EFB uses for its admin panel (efbFunction::user_permission_efb_admin_dashboard). */
	public static function can() {
		return is_user_logged_in() && ( current_user_can( 'manage_options' ) || current_user_can( 'Emsfb' ) );
	}

	/* ------------------------------------------------------------------
	 * Menu + assets
	 * ------------------------------------------------------------------ */

	public static function menu() {
		self::$hook = (string) add_submenu_page(
			'Emsfb',
			__( 'Dashboard', 'flx-forms' ),
			__( 'Dashboard', 'flx-forms' ),
			'Emsfb',
			self::SLUG,
			array( __CLASS__, 'render' ),
			0
		);
	}

	private static function asset_ver( $file ) {
		$path = __DIR__ . '/' . $file;
		return is_readable( $path ) ? (string) filemtime( $path ) : ( defined( 'FLXF_FORK_VERSION' ) ? FLXF_FORK_VERSION : '1' );
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_Emsfb' === $hook ) {
			/* Deep links from the dashboard (?flxf_form=ID&flxf_msg=ID) into EFB's own panel. */
			wp_enqueue_script( 'flxf-panel-bridge', plugins_url( 'panel-bridge.js', __FILE__ ), array( 'jquery' ), self::asset_ver( 'panel-bridge.js' ), true );
			return;
		}
		if ( '' === self::$hook || $hook !== self::$hook ) {
			return;
		}
		wp_enqueue_style( 'flxf-dashboard', plugins_url( 'dashboard.css', __FILE__ ), array(), self::asset_ver( 'dashboard.css' ) );
		$fonts = self::theme_font_faces();
		if ( '' !== $fonts ) {
			wp_add_inline_style( 'flxf-dashboard', $fonts );
		}
		wp_enqueue_script( 'flxf-dashboard', plugins_url( 'dashboard.js', __FILE__ ), array(), self::asset_ver( 'dashboard.js' ), true );
		wp_add_inline_script( 'flxf-dashboard', 'window.FLXF_DASH = ' . wp_json_encode( self::boot_data() ) . ';', 'before' );
	}

	/**
	 * The site theme ships Nunito / Nunito Sans as local files. When they are there, reuse them
	 * (no third-party request); otherwise the CSS falls back to system fonts.
	 */
	private static function theme_font_faces() {
		$css   = '';
		$faces = array(
			'Nunito'      => array( 'nunito-latin.woff2', 'nunito-latin-ext.woff2' ),
			'Nunito Sans' => array( 'nunitosans-latin.woff2', 'nunitosans-latin-ext.woff2' ),
		);
		foreach ( array( get_stylesheet_directory(), get_template_directory() ) as $i => $dir ) {
			$uri = 0 === $i ? get_stylesheet_directory_uri() : get_template_directory_uri();
			foreach ( $faces as $family => $files ) {
				foreach ( $files as $file ) {
					if ( is_readable( $dir . '/assets/fonts/' . $file ) && false === strpos( $css, $file ) ) {
						$css .= '@font-face{font-family:"' . $family . '";src:url("' . esc_url( $uri . '/assets/fonts/' . $file ) . '") format("woff2");font-weight:200 1000;font-style:normal;font-display:swap}';
					}
				}
			}
		}
		return $css;
	}

	private static function js_locale() {
		$l = str_replace( '_', '-', (string) get_user_locale() );
		return preg_replace( '/[^A-Za-z0-9\-]/', '', $l );
	}

	private static function boot_data() {
		$forms = array();
		foreach ( self::forms() as $f ) {
			$forms[] = array(
				'id'       => $f['id'],
				'title'    => $f['title'],
				'currency' => $f['currency'],
			);
		}
		return array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( self::NONCE ),
			'csvUrl'      => add_query_arg( array( 'action' => 'flxf_dash_csv', '_wpnonce' => wp_create_nonce( self::CSV_NONCE ) ), admin_url( 'admin-post.php' ) ),
			'panelUrl'    => admin_url( 'admin.php?page=Emsfb' ),
			'createUrl'   => current_user_can( 'Emsfb_create' ) || current_user_can( 'manage_options' ) ? admin_url( 'admin.php?page=Emsfb_create' ) : '',
			'locale'      => self::js_locale(),
			'today'       => wp_date( 'Y-m-d' ),
			'weekStart'   => (int) get_option( 'start_of_week', 1 ),
			'forms'       => $forms,
			'i18n'        => self::strings(),
		);
	}

	private static function strings() {
		return array(
			'd7'             => __( '7 days', 'flx-forms' ),
			'd30'            => __( '30 days', 'flx-forms' ),
			'd90'            => __( '90 days', 'flx-forms' ),
			'custom'         => __( 'Custom', 'flx-forms' ),
			'range'          => __( 'Date range', 'flx-forms' ),
			'from'           => __( 'From', 'flx-forms' ),
			'to'             => __( 'To', 'flx-forms' ),
			'apply'          => __( 'Apply', 'flx-forms' ),
			'allForms'       => __( 'All forms', 'flx-forms' ),
			'formFilter'     => __( 'Form', 'flx-forms' ),
			'csv'            => __( 'Export CSV', 'flx-forms' ),
			'csvHint'        => __( 'Download the responses in the selected range and form as a spreadsheet file.', 'flx-forms' ),
			'responses'      => __( 'Responses', 'flx-forms' ),
			'unread'         => __( 'Unread', 'flx-forms' ),
			'unreadAll'      => __( '%sunread in total', 'flx-forms' ),
			'views'          => __( 'Form views', 'flx-forms' ),
			'viewsNone'      => __( 'No view data', 'flx-forms' ),
			/* translators: %s: a date */
			'viewsSince'     => __( 'Recorded since%s', 'flx-forms' ),
			'viewsKept'      => __( 'View data is kept for about 8 days', 'flx-forms' ),
			'conversion'     => __( 'Conversion rate', 'flx-forms' ),
			'convHint'       => __( 'Responses per form view', 'flx-forms' ),
			/* translators: %s: a date */
			'convSince'      => __( 'Responses per view since%s', 'flx-forms' ),
			'payments'       => __( 'Payments received', 'flx-forms' ),
			/* translators: %s: number of payments */
			'paidCount'      => __( '%spaid', 'flx-forms' ),
			/* translators: %s: list of other currency totals */
			'otherCur'       => __( 'Also%s', 'flx-forms' ),
			'noPayments'     => __( 'No payments in this range', 'flx-forms' ),
			/* translators: %d: number of days */
			'vsPrev'         => __( 'vs previous%ddays', 'flx-forms' ),
			'newVal'         => __( 'new', 'flx-forms' ),
			'noChange'       => __( 'no change', 'flx-forms' ),
			'up'             => __( 'up', 'flx-forms' ),
			'down'           => __( 'down', 'flx-forms' ),
			'daily'          => __( 'Daily responses', 'flx-forms' ),
			'thisPeriod'     => __( 'Selected period', 'flx-forms' ),
			'prevPeriod'     => __( 'Previous period', 'flx-forms' ),
			'byForm'         => __( 'Responses by form', 'flx-forms' ),
			'payStatus'      => __( 'Payment status', 'flx-forms' ),
			'heat'           => __( 'When responses arrive', 'flx-forms' ),
			'heatHint'       => __( 'Day of week and hour of day', 'flx-forms' ),
			'heatLess'       => __( 'Less', 'flx-forms' ),
			'heatMore'       => __( 'More', 'flx-forms' ),
			'recent'         => __( 'Recent responses', 'flx-forms' ),
			'topForms'       => __( 'Top forms', 'flx-forms' ),
			'colForm'        => __( 'Form', 'flx-forms' ),
			'colDate'        => __( 'Date', 'flx-forms' ),
			'colPreview'     => __( 'Preview', 'flx-forms' ),
			'colStatus'      => __( 'Status', 'flx-forms' ),
			'colResponses'   => __( 'Responses', 'flx-forms' ),
			'colTotal'       => __( 'All time', 'flx-forms' ),
			'colViews'       => __( 'Views', 'flx-forms' ),
			'colConv'        => __( 'Conversion', 'flx-forms' ),
			'colLast'        => __( 'Last response', 'flx-forms' ),
			'colShare'       => __( 'Share', 'flx-forms' ),
			'statusUnread'   => __( 'New', 'flx-forms' ),
			'statusRead'     => __( 'Read', 'flx-forms' ),
			'open'           => __( 'Open', 'flx-forms' ),
			'openResponses'  => __( 'View responses', 'flx-forms' ),
			'viewAll'        => __( 'Open panel', 'flx-forms' ),
			'pay_paid'       => __( 'Paid', 'flx-forms' ),
			'pay_pending'    => __( 'Pending', 'flx-forms' ),
			'pay_failed'     => __( 'Failed or canceled', 'flx-forms' ),
			'pay_refunded'   => __( 'Refunded', 'flx-forms' ),
			/* translators: %s: number of payments */
			'payN'           => __( '%spayments', 'flx-forms' ),
			'noData'         => __( 'No responses in this range yet.', 'flx-forms' ),
			'noForms'        => __( 'You have not created a form yet.', 'flx-forms' ),
			'createForm'     => __( 'Create a form', 'flx-forms' ),
			'loading'        => __( 'Loading…', 'flx-forms' ),
			'error'          => __( 'The dashboard data could not be loaded.', 'flx-forms' ),
			'retry'          => __( 'Try again', 'flx-forms' ),
			'never'          => __( 'Never', 'flx-forms' ),
			'noPreview'      => __( '(empty response)', 'flx-forms' ),
			'total'          => __( 'Total', 'flx-forms' ),
			'responsesOn'    => __( 'responses', 'flx-forms' ),
			'tableView'      => __( 'Show as table', 'flx-forms' ),
			'chartView'      => __( 'Show as chart', 'flx-forms' ),
			'deleted'        => __( 'Deleted form', 'flx-forms' ),
			/* translators: %s: number of forms */
			'otherForms'     => __( '%sother forms', 'flx-forms' ),
		);
	}

	public static function render() {
		if ( ! self::can() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'flx-forms' ), 403 );
		}
		?>
		<div class="wrap flxfd">
			<div class="flxfd-head">
				<div>
					<h1 class="flxfd-title"><?php esc_html_e( 'Dashboard', 'flx-forms' ); ?></h1>
					<p class="flxfd-sub"><?php esc_html_e( 'Responses, form views, conversion and payments at a glance.', 'flx-forms' ); ?></p>
				</div>
				<div class="flxfd-head__actions">
					<a class="flxfd-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=Emsfb' ) ); ?>"><?php esc_html_e( 'Open panel', 'flx-forms' ); ?></a>
					<?php if ( current_user_can( 'Emsfb_create' ) || current_user_can( 'manage_options' ) ) : ?>
						<a class="flxfd-btn flxfd-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=Emsfb_create' ) ); ?>"><?php esc_html_e( 'New form', 'flx-forms' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<hr class="wp-header-end">
			<div id="flxfd-app" class="flxfd-app" aria-live="polite">
				<div class="flxfd-card flxfd-loading"><?php esc_html_e( 'Loading…', 'flx-forms' ); ?></div>
			</div>
			<noscript><div class="flxfd-card"><?php esc_html_e( 'The dashboard needs JavaScript.', 'flx-forms' ); ?></div></noscript>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------
	 * Data helpers
	 * ------------------------------------------------------------------ */

	private static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . $name;
	}

	private static function table_exists( $name ) {
		global $wpdb;
		if ( ! isset( self::$tables[ $name ] ) ) {
			$t                     = self::table( $name );
			self::$tables[ $name ] = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) ) === $t );
		}
		return self::$tables[ $name ];
	}

	/** EFB stores JSON with escaped quotes (\"); accept both forms. */
	public static function decode_json( $raw ) {
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$d = json_decode( $raw, true );
		if ( ! is_array( $d ) ) {
			$d = json_decode( stripslashes( $raw ), true );
		}
		return is_array( $d ) ? $d : array();
	}

	private static function plain( $s ) {
		$s = html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( preg_replace( '/[ \t]+/u', ' ', $s ) );
	}

	/** @return array<int,array{id:int,title:string,currency:string,type:string}> keyed by id */
	public static function forms() {
		global $wpdb;
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$cache = array();
		if ( ! self::table_exists( 'emsfb_form' ) ) {
			return $cache;
		}
		$t = self::table( 'emsfb_form' );
		foreach ( (array) $wpdb->get_results( "SELECT form_id, form_name, form_type, form_structer FROM `{$t}` ORDER BY form_id ASC", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
			$cur = 'TRY';
			$s   = self::decode_json( $r['form_structer'] );
			if ( isset( $s[0]['currency'] ) && is_string( $s[0]['currency'] ) && preg_match( '/^[A-Za-z]{3}$/', $s[0]['currency'] ) ) {
				$cur = strtoupper( $s[0]['currency'] );
			}
			$id           = (int) $r['form_id'];
			$cache[ $id ] = array(
				'id'       => $id,
				'title'    => '' !== self::plain( $r['form_name'] ) ? self::plain( $r['form_name'] ) : '#' . $id,
				'currency' => $cur,
				'type'     => (string) $r['form_type'],
			);
		}
		return $cache;
	}

	private static function valid_day( $s ) {
		if ( ! is_string( $s ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m ) ) {
			return false;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/** @return array{0:string,1:string,2:int} from, to, form id */
	private static function request_filters( $src ) {
		$today = wp_date( 'Y-m-d' );
		$to    = isset( $src['to'] ) ? sanitize_text_field( wp_unslash( $src['to'] ) ) : '';
		$from  = isset( $src['from'] ) ? sanitize_text_field( wp_unslash( $src['from'] ) ) : '';
		$to    = self::valid_day( $to ) ? $to : $today;
		$from  = self::valid_day( $from ) ? $from : gmdate( 'Y-m-d', strtotime( $to . ' -29 days' ) );
		if ( $from > $to ) {
			list( $from, $to ) = array( $to, $from );
		}
		if ( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS > self::MAX_DAYS ) {
			$from = gmdate( 'Y-m-d', strtotime( $to . ' -' . self::MAX_DAYS . ' days' ) );
		}
		$form = isset( $src['form'] ) ? absint( $src['form'] ) : 0;
		return array( $from, $to, $form );
	}

	private static function day_add( $day, $n ) {
		return gmdate( 'Y-m-d', strtotime( $day . ' 12:00:00 UTC' ) + $n * DAY_IN_SECONDS );
	}

	private static function is_unread( $read ) {
		$read = (int) $read;
		return 0 === $read || 3 === $read;
	}

	private static function pay_bucket( $status ) {
		$status = strtolower( (string) $status );
		foreach ( self::PAY_BUCKETS as $bucket => $list ) {
			if ( in_array( $status, $list, true ) ) {
				return $bucket;
			}
		}
		return 'pending';
	}

	/* ------------------------------------------------------------------
	 * Stats endpoint
	 * ------------------------------------------------------------------ */

	public static function ajax_stats() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session has expired. Reload the page.', 'flx-forms' ) ), 403 );
		}
		if ( ! self::can() ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do that.', 'flx-forms' ) ), 403 );
		}
		list( $from, $to, $form ) = self::request_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification -- verified above.
		nocache_headers();
		wp_send_json_success( self::stats( $from, $to, $form ) );
	}

	public static function stats( $from, $to, $form ) {
		global $wpdb;
		$forms = self::forms();
		$msg   = self::table( 'emsfb_msg_' );
		$days  = (int) round( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS ) + 1;
		$pfrom = self::day_add( $from, -$days );
		$pto   = self::day_add( $from, -1 );
		$fw    = $form ? $wpdb->prepare( ' AND form_id = %d', $form ) : '';
		$has   = self::table_exists( 'emsfb_msg_' );

		$series = array();
		for ( $i = 0; $i < $days; $i++ ) {
			$series[ self::day_add( $from, $i ) ] = array( 'd' => self::day_add( $from, $i ), 'n' => 0, 'p' => 0 );
		}
		$keys     = array_keys( $series );
		$by_form  = array();
		$heat     = array();
		$unread   = 0;
		$n        = 0;
		$day_rows = array();

		if ( $has ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table names and $fw are built with prepare().
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT form_id, `date`, read_ FROM `{$msg}` WHERE `date` BETWEEN %s AND %s AND read_ <> 2" . $fw, $from . ' 00:00:00', $to . ' 23:59:59' ),
				ARRAY_N
			);
			foreach ( (array) $rows as $r ) {
				$fid = (int) $r[0];
				$day = substr( (string) $r[1], 0, 10 );
				$hr  = (int) substr( (string) $r[1], 11, 2 );
				$n++;
				if ( isset( $series[ $day ] ) ) {
					$series[ $day ]['n']++;
				}
				$day_rows[] = $day;
				if ( ! isset( $by_form[ $fid ] ) ) {
					$by_form[ $fid ] = array( 'n' => 0, 'unread' => 0, 'conv_n' => 0 );
				}
				$by_form[ $fid ]['n']++;
				$by_form[ $fid ]['days'][] = $day;
				if ( self::is_unread( $r[2] ) ) {
					$unread++;
					$by_form[ $fid ]['unread']++;
				}
				$dow = ( (int) gmdate( 'N', strtotime( $day . ' 12:00:00 UTC' ) ) ) - 1; // 0 = Monday.
				$hk  = $dow . '-' . $hr;
				$heat[ $hk ] = isset( $heat[ $hk ] ) ? $heat[ $hk ] + 1 : 1;
			}

			$prev_n = 0;
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT `date` FROM `{$msg}` WHERE `date` BETWEEN %s AND %s AND read_ <> 2" . $fw, $pfrom . ' 00:00:00', $pto . ' 23:59:59' ) ) as $d ) {
				$prev_n++;
				$idx = (int) round( ( strtotime( substr( $d, 0, 10 ) ) - strtotime( $pfrom ) ) / DAY_IN_SECONDS );
				if ( isset( $keys[ $idx ] ) ) {
					$series[ $keys[ $idx ] ]['p']++;
				}
			}
			$unread_all = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$msg}` WHERE read_ IN (0,3)" . $fw );

			$totals_all = array();
			$last       = array();
			foreach ( (array) $wpdb->get_results( "SELECT form_id, COUNT(*) n, MAX(`date`) last_date FROM `{$msg}` WHERE read_ <> 2 GROUP BY form_id", ARRAY_A ) as $r ) {
				$totals_all[ (int) $r['form_id'] ] = (int) $r['n'];
				$last[ (int) $r['form_id'] ]       = (string) $r['last_date'];
			}
			// phpcs:enable
		} else {
			$prev_n     = 0;
			$unread_all = 0;
			$totals_all = array();
			$last       = array();
		}

		/* Views: emsfb_stts_ keeps one row per form page view for about eight days. */
		$views       = null;
		$prev_views  = null;
		$views_since = null;
		$conv        = null;
		$conv_n      = 0;
		$form_views  = array();
		if ( self::table_exists( 'emsfb_stts_' ) ) {
			$st  = self::table( 'emsfb_stts_' );
			$sfw = $form ? $wpdb->prepare( ' AND fid = %d', $form ) : '';
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$first = $wpdb->get_var( "SELECT MIN(`date`) FROM `{$st}` WHERE fid > 0 AND status <> 'admin'" . $sfw );
			if ( $first ) {
				$views_since = substr( (string) $first, 0, 10 );
				$vfrom       = max( $from, $views_since );
				if ( $vfrom <= $to ) {
					$views = 0;
					foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT fid, COUNT(*) n FROM `{$st}` WHERE fid > 0 AND status <> 'admin' AND `date` BETWEEN %s AND %s" . $sfw . ' GROUP BY fid', $vfrom . ' 00:00:00', $to . ' 23:59:59' ), ARRAY_A ) as $r ) {
						$form_views[ (int) $r['fid'] ] = (int) $r['n'];
						$views                        += (int) $r['n'];
					}
					foreach ( $day_rows as $d ) {
						if ( $d >= $vfrom ) {
							$conv_n++;
						}
					}
					foreach ( $by_form as $fid => $bf ) {
						foreach ( $bf['days'] as $d ) {
							if ( $d >= $vfrom ) {
								$by_form[ $fid ]['conv_n']++;
							}
						}
					}
					$conv = $views > 0 ? round( min( 1, $conv_n / $views ) * 100, 1 ) : null;
				}
				if ( $views_since <= $pto ) {
					$prev_views = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$st}` WHERE fid > 0 AND status <> 'admin' AND `date` BETWEEN %s AND %s" . $sfw, max( $pfrom, $views_since ) . ' 00:00:00', $pto . ' 23:59:59' ) );
				}
			}
			// phpcs:enable
		}

		/* Forms table (every form, so idle forms are visible too). */
		$table = array();
		$ids   = $form ? array( $form ) : array_unique( array_merge( array_keys( $forms ), array_keys( $by_form ) ) );
		foreach ( $ids as $fid ) {
			$bf      = isset( $by_form[ $fid ] ) ? $by_form[ $fid ] : array( 'n' => 0, 'unread' => 0, 'conv_n' => 0 );
			$fv      = isset( $form_views[ $fid ] ) ? $form_views[ $fid ] : ( null === $views ? null : 0 );
			$table[] = array(
				'id'     => (int) $fid,
				'title'  => isset( $forms[ $fid ] ) ? $forms[ $fid ]['title'] : '',
				'exists' => isset( $forms[ $fid ] ),
				'n'      => $bf['n'],
				'unread' => $bf['unread'],
				'total'  => isset( $totals_all[ $fid ] ) ? $totals_all[ $fid ] : 0,
				'views'  => $fv,
				'conv'   => $fv ? round( min( 1, $bf['conv_n'] / $fv ) * 100, 1 ) : null,
				'last'   => isset( $last[ $fid ] ) ? str_replace( ' ', 'T', $last[ $fid ] ) : '',
			);
		}
		usort(
			$table,
			function ( $a, $b ) {
				if ( $a['n'] !== $b['n'] ) {
					return $b['n'] - $a['n'];
				}
				if ( $a['total'] !== $b['total'] ) {
					return $b['total'] - $a['total'];
				}
				return $a['id'] - $b['id'];
			}
		);

		$heat_list = array();
		foreach ( $heat as $k => $v ) {
			list( $d, $h ) = array_map( 'intval', explode( '-', $k ) );
			$heat_list[]   = array( $d, $h, $v );
		}

		$currency = $form && isset( $forms[ $form ] ) ? $forms[ $form ]['currency'] : self::main_currency( $forms );
		$payments = self::payments( $from, $to, $form, $forms );
		if ( ! $form && ! empty( $payments['paid'] ) ) {
			$currency = $payments['paid'][0]['cur'];
		}

		return array(
			'from'       => $from,
			'to'         => $to,
			'days'       => $days,
			'now'        => str_replace( ' ', 'T', current_time( 'mysql' ) ),
			'currency'   => $currency,
			'totals'     => array(
				'n'          => $n,
				'unread'     => $unread,
				'unreadAll'  => $unread_all,
				'views'      => $views,
				'viewsSince' => $views_since && $views_since > $from ? $views_since : null,
				'viewsFirst' => $views_since,
				'conversion' => $conv,
			),
			'prev'       => array(
				'n'     => $prev_n,
				'views' => ( null !== $views && null !== $prev_views && $views_since <= $pfrom ) ? $prev_views : null,
				'paid'  => $payments['prevPaid'],
			),
			'series'     => array_values( $series ),
			'forms'      => $table,
			'heat'       => $heat_list,
			'payments'   => $payments,
			'recent'     => $has ? self::recent( $from, $to, $form, $forms ) : array(),
		);
	}

	private static function main_currency( $forms ) {
		$count = array();
		foreach ( $forms as $f ) {
			$count[ $f['currency'] ] = isset( $count[ $f['currency'] ] ) ? $count[ $f['currency'] ] + 1 : 1;
		}
		arsort( $count );
		return $count ? (string) key( $count ) : 'TRY';
	}

	/**
	 * Payments in the range. Uses the Stripe payments table when it has rows; otherwise falls back
	 * to the payment entries EFB writes into the response content (other gateways, older data).
	 */
	private static function payments( $from, $to, $form, $forms ) {
		global $wpdb;
		$buckets = array();
		$paid    = array();
		$prev    = array();
		$source  = 'none';

		$add = function ( $status, $amount, $cur, $is_prev ) use ( &$buckets, &$paid, &$prev ) {
			$b   = self::pay_bucket( $status );
			$cur = preg_match( '/^[A-Za-z]{3}$/', (string) $cur ) ? strtoupper( $cur ) : 'TRY';
			if ( $is_prev ) {
				if ( 'paid' === $b ) {
					$prev[ $cur ] = ( isset( $prev[ $cur ] ) ? $prev[ $cur ] : 0 ) + (float) $amount;
				}
				return;
			}
			if ( ! isset( $buckets[ $b ] ) ) {
				$buckets[ $b ] = array( 's' => $b, 'n' => 0, 'amount' => array() );
			}
			$buckets[ $b ]['n']++;
			$buckets[ $b ]['amount'][ $cur ] = ( isset( $buckets[ $b ]['amount'][ $cur ] ) ? $buckets[ $b ]['amount'][ $cur ] : 0 ) + (float) $amount;
			if ( 'paid' === $b ) {
				if ( ! isset( $paid[ $cur ] ) ) {
					$paid[ $cur ] = array( 'cur' => $cur, 'amount' => 0, 'n' => 0 );
				}
				$paid[ $cur ]['amount'] += (float) $amount;
				$paid[ $cur ]['n']++;
			}
		};

		$days  = (int) round( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS ) + 1;
		$pfrom = self::day_add( $from, -$days );

		$use_table = false;
		if ( self::table_exists( 'emsfb_pay_' ) ) {
			$pt        = self::table( 'emsfb_pay_' );
			$use_table = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$pt}`" ) > 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		if ( $use_table ) {
			$source = 'stripe';
			$pfw    = $form ? $wpdb->prepare( ' AND form_id = %d', $form ) : '';
			$g_from = get_gmt_from_date( $pfrom . ' 00:00:00' );
			$g_to   = get_gmt_from_date( $to . ' 23:59:59' );
			$g_cut  = get_gmt_from_date( $from . ' 00:00:00' );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, amount, currency, created_at FROM `{$pt}` WHERE created_at BETWEEN %s AND %s" . $pfw, $g_from, $g_to ), ARRAY_A );
			foreach ( (array) $rows as $r ) {
				$add( $r['status'], $r['amount'], $r['currency'], (string) $r['created_at'] < $g_cut );
			}
		} elseif ( self::table_exists( 'emsfb_msg_' ) ) {
			$msg = self::table( 'emsfb_msg_' );
			$fw  = $form ? $wpdb->prepare( ' AND form_id = %d', $form ) : '';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT form_id, `date`, read_, content FROM `{$msg}` WHERE `date` BETWEEN %s AND %s AND (read_ = 2 OR content LIKE %s)" . $fw . ' LIMIT 20000', $pfrom . ' 00:00:00', $to . ' 23:59:59', '%' . $wpdb->esc_like( 'payment' ) . '%' ), ARRAY_A );
			foreach ( (array) $rows as $r ) {
				$items   = self::decode_json( $r['content'] );
				$cur     = isset( $forms[ (int) $r['form_id'] ] ) ? $forms[ (int) $r['form_id'] ]['currency'] : 'TRY';
				$total   = 0;
				$sum     = 0;
				$has_pay = false;
				foreach ( $items as $c ) {
					if ( ! is_array( $c ) ) {
						continue;
					}
					if ( isset( $c['paymentcurrency'] ) && is_string( $c['paymentcurrency'] ) ) {
						$cur = $c['paymentcurrency'];
					}
					if ( isset( $c['type'] ) && 'payment' === $c['type'] ) {
						$has_pay = true;
						$total   = isset( $c['total'] ) ? (float) $c['total'] : 0;
					} elseif ( isset( $c['price'] ) && is_numeric( $c['price'] ) ) {
						$sum += (float) $c['price'];
					}
				}
				if ( ! $has_pay && 2 !== (int) $r['read_'] ) {
					continue;
				}
				$source = 'responses';
				$add( $has_pay ? 'completed' : 'pending', $total ? $total : $sum, $cur, substr( (string) $r['date'], 0, 10 ) < $from );
			}
		}

		$paid = array_values( $paid );
		usort(
			$paid,
			function ( $a, $b ) {
				return $b['amount'] <=> $a['amount'];
			}
		);
		$order = array_keys( self::PAY_BUCKETS );
		$list  = array();
		foreach ( $order as $b ) {
			if ( isset( $buckets[ $b ] ) ) {
				$list[] = $buckets[ $b ];
			}
		}
		$prev_paid = null;
		if ( $paid ) {
			$prev_paid = isset( $prev[ $paid[0]['cur'] ] ) ? $prev[ $paid[0]['cur'] ] : 0;
		}
		return array(
			'source'   => $source,
			'paid'     => $paid,
			'status'   => $list,
			'prevPaid' => $prev_paid,
		);
	}

	private static function recent( $from, $to, $form, $forms ) {
		global $wpdb;
		$msg = self::table( 'emsfb_msg_' );
		$fw  = $form ? $wpdb->prepare( ' AND form_id = %d', $form ) : '';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT msg_id, form_id, track, `date`, read_, content FROM `{$msg}` WHERE `date` BETWEEN %s AND %s AND read_ <> 2" . $fw . ' ORDER BY `date` DESC, msg_id DESC LIMIT %d', $from . ' 00:00:00', $to . ' 23:59:59', self::RECENT ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$fid   = (int) $r['form_id'];
			$out[] = array(
				'id'      => (int) $r['msg_id'],
				'form_id' => $fid,
				'form'    => isset( $forms[ $fid ] ) ? $forms[ $fid ]['title'] : '',
				'track'   => (string) $r['track'],
				'date'    => str_replace( ' ', 'T', (string) $r['date'] ),
				'unread'  => self::is_unread( $r['read_'] ),
				'preview' => self::preview( self::decode_json( $r['content'] ) ),
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Flattening submitted content
	 * ------------------------------------------------------------------ */

	private static function value_text( $c ) {
		$type = isset( $c['type'] ) ? (string) $c['type'] : '';
		$v    = isset( $c['value'] ) ? $c['value'] : '';
		if ( '@file@' === $v ) {
			return isset( $c['url'] ) ? (string) $c['url'] : '';
		}
		if ( 'esign' === $type ) {
			return ( is_string( $v ) && 0 === strpos( $v, 'data:' ) ) ? '[' . __( 'Signature', 'flx-forms' ) . ']' : (string) $v;
		}
		if ( 'maps' === $type && is_array( $v ) ) {
			$pts = array();
			foreach ( $v as $p ) {
				if ( is_array( $p ) ) {
					$pts[] = ! empty( $p['address'] ) ? self::plain( $p['address'] ) : ( ( isset( $p['lat'] ) ? (float) $p['lat'] : 0 ) . ',' . ( isset( $p['lng'] ) ? (float) $p['lng'] : 0 ) );
				}
			}
			return implode( '; ', $pts );
		}
		if ( is_array( $v ) ) {
			$flat = array();
			array_walk_recursive(
				$v,
				function ( $x ) use ( &$flat ) {
					if ( is_scalar( $x ) && '' !== (string) $x ) {
						$flat[] = (string) $x;
					}
				}
			);
			$v = implode( ' | ', $flat );
		}
		$v = self::plain( rtrim( str_replace( '@efb!', ',', (string) $v ), ", \t" ) );
		if ( isset( $c['qty'] ) && is_scalar( $c['qty'] ) && '' !== (string) $c['qty'] ) {
			$v .= ' × ' . self::plain( $c['qty'] );
		}
		if ( ( false !== strpos( $type, 'pay' ) || 'prcfld' === $type ) && isset( $c['price'] ) && is_numeric( $c['price'] ) && (string) $c['price'] !== $v ) {
			$v .= ( '' !== $v ? ' — ' : '' ) . $c['price'];
		}
		return $v;
	}

	/**
	 * @param array $items Decoded response content.
	 * @return array<string,string> label => text, in form order.
	 */
	public static function flatten( $items ) {
		$items = array_values( array_filter( (array) $items, 'is_array' ) );
		usort(
			$items,
			function ( $a, $b ) {
				$x = isset( $a['amount'] ) && is_numeric( $a['amount'] ) ? (float) $a['amount'] : 1e9;
				$y = isset( $b['amount'] ) && is_numeric( $b['amount'] ) ? (float) $b['amount'] : 1e9;
				return $x <=> $y;
			}
		);
		$out = array();
		$ids = array();
		foreach ( $items as $c ) {
			$type = isset( $c['type'] ) ? (string) $c['type'] : '';
			if ( in_array( $type, array( 'closed', 'opened' ), true ) ) {
				continue;
			}
			if ( 'w_link' === $type ) {
				$out[ __( 'Page URL', 'flx-forms' ) ] = isset( $c['value'] ) && is_string( $c['value'] ) ? $c['value'] : '';
				continue;
			}
			if ( 'payment' === $type ) {
				$out[ __( 'Payment ID', 'flx-forms' ) ]     = isset( $c['paymentIntent'] ) && is_scalar( $c['paymentIntent'] ) ? (string) $c['paymentIntent'] : '';
				$out[ __( 'Payment amount', 'flx-forms' ) ] = isset( $c['total'] ) && is_scalar( $c['total'] ) ? (string) $c['total'] : '';
				$out[ __( 'Payment method', 'flx-forms' ) ] = isset( $c['paymentGateway'] ) && is_scalar( $c['paymentGateway'] ) ? (string) $c['paymentGateway'] : '';
				continue;
			}
			$id    = isset( $c['id_'] ) && is_scalar( $c['id_'] ) ? (string) $c['id_'] : $type;
			$label = isset( $c['name'] ) && is_scalar( $c['name'] ) ? self::plain( $c['name'] ) : '';
			if ( 'r_matrix' === $type && ! empty( $c['label'] ) && is_scalar( $c['label'] ) ) {
				$label = self::plain( $c['label'] ) . ' – ' . $label;
			}
			if ( '' === $label ) {
				$label = '' !== $id ? $id : __( 'Field', 'flx-forms' );
			}
			$v = self::value_text( $c );
			if ( 'passwordRegisterEFB' === $id ) {
				$v = '********';
			}
			if ( isset( $out[ $label ] ) ) {
				if ( isset( $ids[ $label ] ) && $ids[ $label ] === $id ) {
					$out[ $label ] = '' === $out[ $label ] ? $v : $out[ $label ] . ' | ' . $v;
					continue;
				}
				$label .= ' (' . $id . ')';
			}
			$out[ $label ] = $v;
			$ids[ $label ] = $id;
		}
		return $out;
	}

	private static function cut( $s, $len ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $s, 'UTF-8' ) > $len ? rtrim( mb_substr( $s, 0, $len - 1, 'UTF-8' ) ) . '…' : $s;
		}
		return strlen( $s ) > $len ? rtrim( substr( $s, 0, $len - 1 ) ) . '…' : $s;
	}

	private static function preview( $items ) {
		$skip  = array( __( 'Page URL', 'flx-forms' ), __( 'Payment ID', 'flx-forms' ), __( 'Payment method', 'flx-forms' ) );
		$parts = array();
		foreach ( self::flatten( $items ) as $label => $v ) {
			if ( in_array( $label, $skip, true ) || '' === trim( $v ) || '********' === $v || 0 === strpos( $v, 'http' ) ) {
				continue;
			}
			$parts[] = self::cut( preg_replace( '/\s+/u', ' ', $v ), 48 );
			if ( count( $parts ) >= 3 ) {
				break;
			}
		}
		return self::cut( implode( ' · ', $parts ), 150 );
	}

	/* ------------------------------------------------------------------
	 * CSV export
	 * ------------------------------------------------------------------ */

	/** Neutralise spreadsheet formulas (CSV injection): =, +, -, @, tab, CR at the start. */
	public static function csv_cell( $v ) {
		$v = str_replace( array( "\r\n", "\r" ), "\n", (string) $v );
		if ( preg_match( '/^[\s]*[=+\-@\t\r]/u', $v ) || ( '' !== $v && in_array( $v[0], array( "\t", "\r" ), true ) ) ) {
			$v = "'" . $v;
		}
		return $v;
	}

	public static function csv() {
		if ( ! self::can() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'flx-forms' ), 403 );
		}
		check_admin_referer( self::CSV_NONCE );
		global $wpdb;
		list( $from, $to, $form ) = self::request_filters( $_GET );
		$forms = self::forms();
		$delim = 0 === strpos( strtolower( (string) get_user_locale() ), 'tr' ) ? ';' : ',';
		$fixed = array(
			__( 'Response ID', 'flx-forms' ),
			__( 'Form', 'flx-forms' ),
			__( 'Date', 'flx-forms' ),
			__( 'Tracking code', 'flx-forms' ),
			__( 'Status', 'flx-forms' ),
		);

		$msg   = self::table( 'emsfb_msg_' );
		$fw    = $form ? $wpdb->prepare( ' AND form_id = %d', $form ) : '';
		$chunk = 500;
		$fetch = function ( $after ) use ( $wpdb, $msg, $fw, $from, $to, $chunk ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT msg_id, form_id, track, `date`, read_, content FROM `{$msg}` WHERE `date` BETWEEN %s AND %s AND read_ <> 2 AND msg_id > %d" . $fw . ' ORDER BY msg_id ASC LIMIT %d', $from . ' 00:00:00', $to . ' 23:59:59', $after, $chunk ), ARRAY_A );
		};

		/* Pass 1: the union of field columns, in first-seen order. */
		$cols = array();
		if ( self::table_exists( 'emsfb_msg_' ) ) {
			$after = 0;
			do {
				$rows = $fetch( $after );
				foreach ( $rows as $r ) {
					$after = (int) $r['msg_id'];
					foreach ( array_keys( self::flatten( self::decode_json( $r['content'] ) ) ) as $k ) {
						$cols[ $k ] = true;
					}
				}
			} while ( count( $rows ) === $chunk );
		}
		$cols = array_keys( $cols );

		$name = 'responses-' . ( $form && isset( $forms[ $form ] ) ? sanitize_title( $forms[ $form ]['title'] ) . '-' : '' ) . $from . '_' . $to . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $name ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array_map( array( __CLASS__, 'csv_cell' ), array_merge( $fixed, $cols ) ), $delim, '"', '' );

		/* Pass 2: rows. */
		if ( self::table_exists( 'emsfb_msg_' ) ) {
			$after = 0;
			do {
				$rows = $fetch( $after );
				foreach ( $rows as $r ) {
					$after = (int) $r['msg_id'];
					$flat  = self::flatten( self::decode_json( $r['content'] ) );
					$fid   = (int) $r['form_id'];
					$line  = array(
						(int) $r['msg_id'],
						isset( $forms[ $fid ] ) ? $forms[ $fid ]['title'] : '#' . $fid,
						(string) $r['date'],
						(string) $r['track'],
						self::is_unread( $r['read_'] ) ? __( 'Unread', 'flx-forms' ) : __( 'Read', 'flx-forms' ),
					);
					foreach ( $cols as $k ) {
						$line[] = isset( $flat[ $k ] ) ? $flat[ $k ] : '';
					}
					fputcsv( $out, array_map( array( __CLASS__, 'csv_cell' ), $line ), $delim, '"', '' );
				}
			} while ( count( $rows ) === $chunk );
		}
		fclose( $out );
		exit;
	}
}
