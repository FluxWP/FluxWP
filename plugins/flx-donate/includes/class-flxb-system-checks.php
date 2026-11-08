<?php
/**
 * Flux Donate: system checks (Donations › Settings › System check).
 *
 * Registers Flux Donate's own checks on the shared `flx_system_checks` filter (see
 * includes/flx-system-checks.php) and runs the "Test" buttons: iyzico connection (one signed
 * BIN lookup), cron spawn (one request to wp-cron.php) and a test e-mail. Nothing here calls
 * out unless an admin clicks one of those; every other check reads options and constants.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_System_Checks {

	const TESTS     = 'flxb_syscheck_tests';
	const HEARTBEAT = 'flxb_cron_heartbeat';
	const SOURCE    = 'Flux Donate';

	/** Flux Forms version Flux Donate is built against (payment hand-off, quote, "payment unavailable" box). */
	const MIN_FLXFORM = '2.0.7';
	/** Flux Certificates version with events and the background send queue. */
	const MIN_FLXS = '1.2.0';

	public static function init() {
		add_filter( 'flx_system_checks', array( __CLASS__, 'checks' ), 10 );
		add_filter( 'flx_system_checks_labels', array( __CLASS__, 'labels' ), 10 );
		add_filter(
			'flx_system_checks_versions',
			function ( $v ) {
				$v['flx-donate'] = FLXB_VERSION;
				return $v;
			}
		);
		add_filter(
			'flx_system_checks_page_url',
			function () {
				return self::url();
			},
			10
		);
		add_action( 'admin_post_flxb_syscheck_test', array( __CLASS__, 'test' ) );
		add_action( 'admin_init', array( __CLASS__, 'moved' ) );
		add_action( 'admin_notices', array( __CLASS__, 'test_notice' ) );
		if ( is_admin() ) {
			flx_system_checks_boot();
		}
	}

	/** 2.9.0: Flux Speed hosts the System check screen (Performance › System check). */
	public static function external() {
		return function_exists( 'flxp_system_checks_url' );
	}

	public static function url( array $args = array() ) {
		if ( self::external() ) {
			return add_query_arg( $args, flxp_system_checks_url() );
		}
		return FLXB_Admin_Url::page( 'flxb-settings', array_merge( array( 'tab' => 'system' ), $args ) );
	}

	/** Old address (Settings › System check) goes to Flux Speed's screen. */
	public static function moved() {
		if ( ! self::external() || 'flxb-settings' !== FLXB_Admin::q( 'page' ) || 'system' !== FLXB_Admin::q( 'tab' ) ) {
			return;
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/** Result of a "Test" button on Flux Speed's screen (it has no slot of its own for it). */
	public static function test_notice() {
		$what = self::external() ? FLXB_Admin::q( 'flxb_test', '' ) : '';
		$r    = '' !== $what ? self::result( $what ) : null;
		if ( $r ) {
			echo '<div class="notice ' . ( $r['ok'] ? 'notice-success' : 'notice-warning' ) . ' is-dismissible"><p><strong>' . esc_html( self::SOURCE ) . ':</strong> ' . esc_html( (string) $r['message'] ) . '</p></div>';
		}
	}

	private static function test_url( $what ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=flxb_syscheck_test&test=' . $what ), 'flxb_syscheck_' . $what );
	}

	/** Shared screen texts in Turkish (or the site language). */
	public static function labels( $l ) {
		return array_merge(
			(array) $l,
			array(
				'title'       => __( 'System check', 'flx-donate' ),
				'intro'       => __( 'Each line shows something the site needs and whether it is in place. Fix the errors first; warnings are worth a look.', 'flx-donate' ),
				'ok'          => __( 'OK', 'flx-donate' ),
				'warning'     => __( 'Warning', 'flx-donate' ),
				'error'       => __( 'Error', 'flx-donate' ),
				'info'        => __( 'Info', 'flx-donate' ),
				/* translators: 1: OK count, 2: warning count, 3: error count */
				'summary'     => __( '%1$d OK · %2$d warnings · %3$d errors', 'flx-donate' ),
				'recheck'     => __( 'Check again', 'flx-donate' ),
				'filter_hint' => __( 'Show only these (click again to show all)', 'flx-donate' ),
				/* translators: %s: time */
				'checked'     => __( 'Checked: %s', 'flx-donate' ),
				'col_status'  => __( 'Status', 'flx-donate' ),
				'col_check'   => __( 'Check', 'flx-donate' ),
				'col_detail'  => __( 'Details', 'flx-donate' ),
				'col_source'  => __( 'Source', 'flx-donate' ),
				'col_action'  => __( 'Action', 'flx-donate' ),
				'none'        => __( 'No checks are registered.', 'flx-donate' ),
				/* translators: %d: number of errors */
				'notice'      => __( 'System check found %d problem(s) that can stop donations or automations.', 'flx-donate' ),
				'notice_link' => __( 'Open system check', 'flx-donate' ),
				'dismiss'     => __( 'Hide until the next update', 'flx-donate' ),
				/* translators: %d: number of errors */
				'bar'         => __( 'System: %d error(s)', 'flx-donate' ),
				/* translators: %s: error message */
				'broken'      => __( 'This check could not run: %s', 'flx-donate' ),
				'groups'      => array(
					'Cron'     => __( 'Scheduled tasks (cron)', 'flx-donate' ),
					'Payments' => __( 'Payments', 'flx-donate' ),
					'Plugins'  => __( 'Plugins', 'flx-donate' ),
					'Config'   => __( 'Settings', 'flx-donate' ),
					'Email'    => __( 'E-mail', 'flx-donate' ),
					'Security' => __( 'Security', 'flx-donate' ),
					'Forms'    => __( 'Forms', 'flx-donate' ),
					'Other'    => __( 'Other', 'flx-donate' ),
				),
			)
		);
	}

	/** Cron heartbeat: called at the start of every flxb_iyzico_renewals run. */
	public static function beat() {
		update_option( self::HEARTBEAT, time(), false );
	}

	private static function c( $id, $group, $label, $status, $message, $url = '', $action = '' ) {
		return array(
			'id'           => 'flxb.' . $id,
			'source'       => self::SOURCE,
			'group'        => $group,
			'label'        => $label,
			'status'       => $status,
			'message'      => $message,
			'action_url'   => $url,
			'action_label' => $action,
		);
	}

	private static function when( $ts ) {
		/* translators: 1: date and time, 2: "5 minutes" */
		return sprintf( __( '\\n%1$s(%2$s)\\n', 'flx-donate' ), flxb_date( gmdate( 'Y-m-d H:i:s', (int) $ts ) ), flx_time_ago( (int) $ts ) );
	}

	/** Result of a "Test" button, or null. */
	private static function result( $what ) {
		$t = get_option( self::TESTS, array() );
		return is_array( $t ) && isset( $t[ $what ] ) && is_array( $t[ $what ] ) ? $t[ $what ] : null;
	}

	private static function result_line( $r ) {
		if ( ! $r ) {
			return '';
		}
		/* translators: 1: date, 2: result */
		return "\n" . sprintf( __( '\\nLast test%1$s:%2$s\\n', 'flx-donate' ), self::when( (int) $r['at'] ), (string) $r['message'] );
	}

	/* ------------------------------------------------------------------
	 * Checks
	 * ------------------------------------------------------------------ */

	public static function checks( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		foreach ( array( 'cron', 'payments', 'plugins', 'config' ) as $part ) {
			try {
				$checks = array_merge( $checks, self::$part() );
			} catch ( Throwable $e ) {
				/* translators: %s: error message */
				$checks[] = self::c( 'broken.' . $part, 'Config', __( 'System check', 'flx-donate' ), 'error', sprintf( __( 'This check could not run: %s', 'flx-donate' ), $e->getMessage() ) );
			}
		}
		return $checks;
	}

	private static function cron() {
		$out      = array();
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$beat     = (int) get_option( self::HEARTBEAT, 0 );
		$fresh    = $beat && time() - $beat <= 2 * HOUR_IN_SECONDS;
		$cron_url = site_url( 'wp-cron.php' );

		if ( $disabled ) {
			$out[] = self::c(
				'cron.mode',
				'Cron',
				__( 'WP-Cron mode', 'flx-donate' ),
				$fresh ? 'ok' : 'warning',
				/* translators: %s: wp-cron.php URL */
				sprintf( __( '[[\'WP-Cron is turned off in wp-config.php (DISABLE_WP_CRON). That is fine only if a server cron job calls%severy 5–15 minutes; without it recurring charges, retries and other scheduled tasks never run.\\n', 'flx-donate' ), $cron_url )
				. ( $fresh ? ' ' . __( '\\nScheduled tasks are running, so a server cron is in place.\\n', 'flx-donate' ) : ' ' . __( '\\nScheduled tasks have not run recently: check the server cron job (Hostinger: hPanel › Advanced › Cron Jobs).\', \'en\']]', 'flx-donate' ) )
			);
		} else {
			$out[] = self::c(
				'cron.mode',
				'Cron',
				__( 'WP-Cron mode', 'flx-donate' ),
				'info',
				__( '\\nWP-Cron runs when someone visits the site. On a quiet site tasks can run late; for reliable recurring charges add a server cron job that calls wp-cron.php every 15 minutes and set DISABLE_WP_CRON to true (see KURULUM.md).\\n', 'flx-donate' )
				. ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ? ' ' . __( '\\nALTERNATE_WP_CRON is on.\\n', 'flx-donate' ) : '' )
			);
		}

		$next = wp_next_scheduled( FLXB_Iyzico_Recurring::HOOK );
		if ( $fresh ) {
			/* translators: %s: last run */
			$out[] = self::c( 'cron.heartbeat', 'Cron', __( '\\nFlux Donate hourly task", \'en\']]', 'flx-donate' ), 'ok', sprintf( __( '\\nLast run:%s. Recurring iyzico charges are checked every hour.\\n', 'flx-donate' ), self::when( $beat ) ) );
		} elseif ( ! $beat && $next && $next > time() && (int) get_option( 'flxb_cron_first_seen', 0 ) > time() - 2 * HOUR_IN_SECONDS ) {
			/* translators: %s: date */
			$out[] = self::c( 'cron.heartbeat', 'Cron', __( '\\nFlux Donate hourly task", \'en\']]', 'flx-donate' ), 'info', sprintf( __( '\\nNot run yet; the first run is scheduled for%s.\\n', 'flx-donate' ), flxb_date( gmdate( 'Y-m-d H:i:s', $next ) ) ) );
		} else {
			$msg   = $beat
				/* translators: %s: last run */
				? sprintf( __( '\\nLast run:%s, more than 2 hours ago.\\n', 'flx-donate' ), self::when( $beat ) )
				: __( '\\nIt has never run.\\n', 'flx-donate' );
			$msg  .= ' ' . ( $disabled
				? __( '\\nWP-Cron is off, so a server cron job must call wp-cron.php. It seems to be missing or failing.\', \'en\']]', 'flx-donate' )
				: __( '\\nWP-Cron only runs on visits and may be blocked by the host or a security plugin. Use "Test cron" or add a server cron job.\\n', 'flx-donate' ) );
			$msg  .= $next ? ' ' . sprintf( /* translators: %s: date */ __( '\\nNext scheduled:%s.\\n', 'flx-donate' ), flxb_date( gmdate( 'Y-m-d H:i:s', $next ) ) ) : ' ' . __( '[[\'The task is not scheduled; it is added again on the next page load.\\n', 'flx-donate' );
			$out[] = self::c( 'cron.heartbeat', 'Cron', __( '\\nFlux Donate hourly task", \'en\']]', 'flx-donate' ), 'warning', $msg, self::test_url( 'cron' ), __( '\\nTest cron\\n', 'flx-donate' ) );
		}

		$late  = array();
		$limit = time() - 15 * MINUTE_IN_SECONDS;
		foreach ( (array) _get_cron_array() as $ts => $hooks ) {
			if ( (int) $ts >= $limit ) {
				break;
			}
			foreach ( array_keys( (array) $hooks ) as $h ) {
				$late[ $h ] = true;
			}
		}
		$n = count( $late );
		$out[] = $n
			? self::c(
				'cron.overdue',
				'Cron',
				__( '\\nOverdue scheduled tasks\\n', 'flx-donate' ),
				$n > 2 ? 'warning' : 'info',
				/* translators: 1: number, 2: task names */
				sprintf( _n( '\\n%1$dtask is more than 15 minutes late:%2$s.%1$dtasks are more than 15 minutes late:%2$s.\\n', '\\n%1$dtask is more than 15 minutes late:%2$s.%1$dtasks are more than 15 minutes late:%2$s.\\n', $n, 'flx-donate' ), $n, implode( ', ', array_slice( array_keys( $late ), 0, 4 ) ) . ( $n > 4 ? '…' : '' ) ) . ' ' . __( '\\nA few late tasks right after a quiet period are normal; many mean cron is not running.\\n', 'flx-donate' )
			)
			: self::c( 'cron.overdue', 'Cron', __( '\\nOverdue scheduled tasks\\n', 'flx-donate' ), 'ok', __( '\\nNo task is late.\\n', 'flx-donate' ) );

		$r     = self::result( 'cron' );
		$out[] = self::c(
			'cron.spawn',
			'Cron',
			__( '\\nCron test\\n', 'flx-donate' ),
			$r ? ( $r['ok'] ? 'ok' : 'warning' ) : 'info',
			( $r ? '' : __( '\\nNot tested. The test asks this server to call wp-cron.php once, as WordPress does after a visit.\\n', 'flx-donate' ) ) . ltrim( self::result_line( $r ) ),
			self::test_url( 'cron' ),
			__( '\\nTest cron\\n', 'flx-donate' )
		);
		return $out;
	}

	private static function payments() {
		$out = array();
		if ( ! function_exists( 'flxb_owns_payments' ) || ! flxb_owns_payments() || ! function_exists( 'flxf_payment_active_provider_id' ) ) {
			return $out;
		}
		$page   = FLXB_Admin_Url::page( 'flxb-payments' );
		$active = flxf_payment_active_provider_id();
		$p      = flxf_payment_provider( $active );
		if ( ! $p ) {
			$out[] = self::c( 'pay.provider', 'Payments', __( '\\nCard payment provider\\n', 'flx-donate' ), 'error', __( '\\nNo payment provider is available.\\n', 'flx-donate' ), $page, __( '\\nPayment settings\\n', 'flx-donate' ) );
			return $out;
		}
		$out[] = $p->is_configured()
			/* translators: %s: provider name */
			? self::c( 'pay.provider', 'Payments', __( '\\nCard payment provider\\n', 'flx-donate' ), 'ok', sprintf( __( '\\nCard payments are taken with%s.\\n', 'flx-donate' ), $p->label() ), $page, __( '\\nPayment settings\\n', 'flx-donate' ) )
			/* translators: %s: provider name */
			: self::c( 'pay.provider', 'Payments', __( '\\nCard payment provider\\n', 'flx-donate' ), 'error', sprintf( __( '\\n%sis the active provider but its keys are missing, so donation forms show "Online payment is unavailable".\\n', 'flx-donate' ), $p->label() ), $page, __( '\\nEnter keys\\n', 'flx-donate' ) );

		$iyz = flxf_payment_provider( 'iyzico' );
		if ( $iyz && ( 'iyzico' === $active || $iyz->is_configured() ) ) {
			$c      = FLXB_Iyzico_Api::credentials();
			$src    = array(
				'woocommerce'              => __( '\\nKeys are read from the iyzico WooCommerce plugin settings.\\n', 'flx-donate' ),
				'woocommerce-subscription' => __( '\\nKeys are read from the iyzico Subscription plugin settings.\\n', 'flx-donate' ),
				'own'                      => __( '\\nKeys are entered in Donations › Payments › iyzico.\\n', 'flx-donate' ),
			);
			$r      = self::result( 'iyzico' );
			$failed = $r && ! $r['ok'] && ( $r['sig'] ?? '' ) === self::key_sig( $c );
			if ( '' === $c['source'] ) {
				$st  = 'iyzico' === $active ? 'error' : 'warning';
				$msg = __( '[[\'No iyzico API key and secret key found (neither in the iyzico WooCommerce plugin nor in Flux Donate).\\n', 'flx-donate' );
			} else {
				$st  = $failed ? 'error' : ( $c['sandbox'] && 'iyzico' === $active ? 'warning' : 'ok' );
				$msg = ( $src[ $c['source'] ] ?? '' ) . ' ' . ( $c['sandbox']
					? __( '\\nMode: sandbox (test). No real money is collected; switch to live keys before launch.\\n', 'flx-donate' )
					: __( '\\nMode: live. Real payments are taken.\\n', 'flx-donate' ) );
				if ( ! $r ) {
					$msg .= ' ' . __( '\\nUse "Test connection" to check the keys with iyzico.\\n', 'flx-donate' );
				}
			}
			$out[] = self::c( 'pay.iyzico', 'Payments', __( '\\niyzico keys\\n', 'flx-donate' ), $st, $msg . self::result_line( $r ), '' === $c['source'] ? $page : self::test_url( 'iyzico' ), '' === $c['source'] ? __( '\\nEnter keys\\n', 'flx-donate' ) : __( 'Test connection', 'flx-donate' ) );
			$last  = get_option( FLXB_Iyzico_Notify::LAST_OPT );
			$hit   = is_array( $last ) && ! empty( $last['at'] ) && time() - (int) $last['at'] <= 30 * DAY_IN_SECONDS ? (int) $last['at'] : 0;
			/* translators: %s: notification URL */
			$msg   = sprintf( __( '\\nPaste into iyzico panel › Merchant notifications URL (İşyeri Bildirimleri Url):%s. Subscription notification URLs are not needed.\\n', 'flx-donate' ), FLXB_Iyzico_Notify::url() );
			$msg  .= ' ' . ( $hit
				/* translators: %s: date */
				? sprintf( __( '\\nLast notification received:%s.\\n', 'flx-donate' ), self::when( $hit ) )
				: __( '\\nNo notification received in the last 30 days (normal until the address is saved in the iyzico panel and a payment is made).\\n', 'flx-donate' ) );
			if ( '' !== FLXB_Iyzico_Notify::woo_key() ) {
				$msg .= ' ' . __( '\\nNotifications for WooCommerce orders are forwarded to the iyzico WooCommerce plugin.\\n', 'flx-donate' );
			}
			$out[] = self::c( 'pay.iyzico.notify', 'Payments', __( '\\niyzico notification URL\\n', 'flx-donate' ), $hit ? 'ok' : 'info', $msg, ( self::external() ? FLXB_Admin_Url::page( 'flxb-settings' ) : self::url() ) . '#flxb-iyzico-addresses', __( '\\nShow addresses\\n', 'flx-donate' ) );
			$failed_subs = (array) get_option( FLXB_Iyzico_Recurring::NOTICE_OPT, array() );
			if ( $failed_subs ) {
				/* translators: %d: number of recurring donations */
				$out[] = self::c( 'pay.iyzico.pastdue', 'Payments', __( '\\nFailed recurring charges\\n', 'flx-donate' ), 'warning', sprintf( _n( '[[\'%drecurring donation stopped after its card was declined 3 times.\', \'en\']]', '[[\'%drecurring donations stopped after their cards were declined 3 times.\', \'en\']]', count( $failed_subs ), 'flx-donate' ), count( $failed_subs ) ), FLXB_Admin_Url::page( 'flxb-subscriptions', array( 'status' => 'past_due' ) ), __( '\\nRecurring donations\\n', 'flx-donate' ) );
			}
		}

		$stripe = flxf_payment_provider( 'stripe' );
		if ( $stripe && ( 'stripe' === $active || $stripe->is_configured() ) ) {
			if ( ! $stripe->is_configured() ) {
				$out[] = self::c( 'pay.stripe', 'Payments', __( '\\nStripe keys\\n', 'flx-donate' ), 'error', __( '\\nThe Stripe publishable key and secret key are missing.\\n', 'flx-donate' ), $page, __( '\\nEnter keys\\n', 'flx-donate' ) );
			} else {
				$live  = 0 === strpos( (string) $stripe->publishable_key(), 'pk_live_' );
				$hook  = '' !== $stripe->webhook_secret();
				$msg   = ( $live ? __( '\\nMode: live.\\n', 'flx-donate' ) : __( '\\nMode: test (pk_test_). No real money is collected.\\n', 'flx-donate' ) ) . ' '
					. ( $hook ? __( '\\nWebhook signing secret is set.\\n', 'flx-donate' ) : __( '\\nWebhook signing secret is missing: refunds and renewals made in Stripe will not show up in Flux Donate.\\n', 'flx-donate' ) );
				$out[] = self::c( 'pay.stripe', 'Payments', __( '\\nStripe keys\\n', 'flx-donate' ), $hook && ( $live || 'stripe' !== $active ) ? 'ok' : 'warning', $msg, $page, __( '\\nPayment settings\\n', 'flx-donate' ) );
			}
		}
		return $out;
	}

	/** Active plugin files (site and network), cheap: two options. */
	private static function active_plugins() {
		$a = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$a = array_merge( $a, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		return $a;
	}

	private static function plugin_active( $basename ) {
		foreach ( self::active_plugins() as $f ) {
			if ( basename( (string) $f ) === $basename ) {
				return true;
			}
		}
		return false;
	}

	private static function plugins() {
		$out     = array();
		$plugins = admin_url( 'plugins.php' );
		if ( ! defined( 'EMSFB_PLUGIN_DIRECTORY' ) ) {
			$out[] = self::c( 'plugin.flxform', 'Plugins', 'Flux Forms', 'error', __( '\\nFlux Forms is not active. Donation forms and card payments need it.\\n', 'flx-donate' ), $plugins, __( 'Plugins', 'flx-donate' ) );
		} else {
			$v     = defined( 'FLXF_FORK_VERSION' ) ? (string) FLXF_FORK_VERSION : '';
			$out[] = '' !== $v && version_compare( $v, self::MIN_FLXFORM, '>=' )
				/* translators: %s: version */
				? self::c( 'plugin.flxform', 'Plugins', 'Flux Forms', 'ok', sprintf( __( '\\nActive, version%s.\\n', 'flx-donate' ), $v ) )
				/* translators: 1: installed version, 2: required version */
				: self::c( 'plugin.flxform', 'Plugins', 'Flux Forms', 'warning', sprintf( __( '\\nVersion%1$sis active; Flux Donate needs%2$sor later for payments and prices to work together.\\n', 'flx-donate' ), '' !== $v ? $v : '?', self::MIN_FLXFORM ), $plugins, __( 'Plugins', 'flx-donate' ) );
		}

		if ( defined( 'FLXS_VERSION' ) ) {
			$v     = (string) FLXS_VERSION;
			$out[] = version_compare( $v, self::MIN_FLXS, '>=' )
				/* translators: %s: version */
				? self::c( 'plugin.flxs', 'Plugins', 'Flux Certificates', 'ok', sprintf( __( '\\nActive, version%s. Certificates can be sent after donations.\\n', 'flx-donate' ), $v ) )
				/* translators: 1: installed version, 2: required version */
				: self::c( 'plugin.flxs', 'Plugins', 'Flux Certificates', 'warning', sprintf( __( '\\nVersion%1$sis active;%2$sor later is recommended.\\n', 'flx-donate' ), $v, self::MIN_FLXS ), $plugins, __( 'Plugins', 'flx-donate' ) );
		} else {
			$out[] = self::c( 'plugin.flxs', 'Plugins', 'Flux Certificates', 'warning', __( '\\nNot active. Donations are recorded, but no certificate is created or sent.\\n', 'flx-donate' ), $plugins, __( 'Plugins', 'flx-donate' ) );
		}

		$wc     = class_exists( 'WooCommerce' ) || self::plugin_active( 'woocommerce.php' );
		$iyz_wc = self::plugin_active( 'woocommerce-gateway-iyzico.php' );
		$iyz_s  = self::plugin_active( 'iyzico-subscription.php' );
		if ( ! $wc || ! class_exists( 'FLXB_WC' ) || ! FLXB_WC::active() ) {
			$out[] = self::c( 'plugin.wc', 'Plugins', 'WooCommerce', 'error', $wc
				/* translators: %s: version */
				? sprintf( __( '\\nWooCommerce is older than%s. Flux Donate 2.0 takes donations through WooCommerce; update it.\\n', 'flx-donate' ), class_exists( 'FLXB_WC' ) ? FLXB_WC::MIN_WC : '8.0' )
				: __( '\\nWooCommerce is not active. Flux Donate 2.0 takes donations through the WooCommerce cart and checkout; until it is active, only the older Flux Forms payment layer works.\\n', 'flx-donate' ), $plugins, __( 'Plugins', 'flx-donate' ) );
		}
		if ( defined( 'FLXWB_VERSION' ) ) {
			$out[] = self::c( 'plugin.flxwb', 'Plugins', 'Flux WooCommerce Bağış', 'error', __( '[[\'The old Flux WooCommerce Bağış plugin is still active. Flux Donate 2.0 replaces it: deactivate and delete it, otherwise orders get two certificates and two messages.\\n', 'flx-donate' ), $plugins, __( 'Plugins', 'flx-donate' ) );
		}
		$msg    = $wc ? __( '\\nWooCommerce is active.\\n', 'flx-donate' ) : __( 'WooCommerce is not active.', 'flx-donate' );
		$msg   .= ' ' . ( $iyz_wc
			? __( '\\nThe iyzico WooCommerce plugin is active: it takes one-time card payments, and Flux Donate reads its iyzico keys for monthly donations.\\n', 'flx-donate' )
			: ( get_option( 'woocommerce_iyzico_settings' ) ? __( '\\nThe iyzico WooCommerce plugin is not active, but its saved keys are still used.\\n', 'flx-donate' ) : __( '\\nThe iyzico WooCommerce plugin is not active; Flux Donate uses its own iyzico keys.\\n', 'flx-donate' ) ) );
		if ( $iyz_s ) {
			$msg .= ' ' . __( '\\nThe iyzico Subscription plugin is active; it renews WooCommerce subscriptions only. Flux Donate recurring donations use their own hourly task.\', \'en\']]', 'flx-donate' );
		}
		$out[] = self::c( 'plugin.woo', 'Plugins', __( '\\nWooCommerce and iyzico plugins\\n', 'flx-donate' ), 'info', $msg );
		return $out;
	}

	private static function config() {
		$out   = array();
		$types = function_exists( 'flxb_get_types' ) ? flxb_get_types( array( 'status' => 'open', 'progress' => false ) ) : array();
		$forms = flxb_forms();
		$tpage = FLXB_Admin_Url::page( 'flxb-types' );
		$priced = array_filter(
			$types,
			function ( $t ) {
				if ( ! empty( $t['allow_free'] ) ) {
					return true;
				}
				foreach ( (array) $t['options'] as $o ) {
					if ( ! empty( $o['active'] ) && null !== $o['price'] && (float) $o['price'] > 0 ) {
						return true;
					}
				}
				return false;
			}
		);
		$out[] = $priced
			/* translators: %d: number of donation types */
			? self::c( 'cfg.types', 'Config', __( '[[\'Open donation types\\n', 'flx-donate' ), 'ok', sprintf( _n( '[[\'%ddonation type is open and priced.\', \'en\']]', '[[\'%ddonation types are open and priced.\', \'en\']]', count( $priced ), 'flx-donate' ), count( $priced ) ), $tpage, __( 'Donation types', 'flx-donate' ) )
			: self::c( 'cfg.types', 'Config', __( '[[\'Open donation types\\n', 'flx-donate' ), 'error', __( '\\nNo donation type is open with a price (or free amount), so forms cannot take donations.\\n', 'flx-donate' ), $tpage, __( 'Donation types', 'flx-donate' ) );

		$unlinked = array();
		foreach ( $types as $t ) {
			$ok = false;
			foreach ( (array) ( $t['form_ids'] ?? array() ) as $fid ) {
				$ok = $ok || isset( $forms[ (int) $fid ] );
			}
			if ( ! $ok ) {
				$unlinked[] = (string) $t['title'];
			}
		}
		if ( $types ) {
			$out[] = $unlinked
				/* translators: %s: donation type names */
				? self::c( 'cfg.type_forms', 'Config', __( '\\nForms of open donation types\\n', 'flx-donate' ), 'warning', sprintf( __( '\\nNo (existing) form is linked to:%s. Visitors cannot give to these types online.\\n', 'flx-donate' ), implode( ', ', array_slice( $unlinked, 0, 6 ) ) . ( count( $unlinked ) > 6 ? '…' : '' ) ), $tpage, __( 'Donation types', 'flx-donate' ) )
				: self::c( 'cfg.type_forms', 'Config', __( '\\nForms of open donation types\\n', 'flx-donate' ), 'ok', __( '\\nEvery open donation type has a form.\\n', 'flx-donate' ) );
		}

		$cur   = flxb_default_currency();
		$st    = 'ok';
		/* translators: %s: currency code */
		$msg   = sprintf( __( '\\nDefault currency:%s.\\n', 'flx-donate' ), $cur );
		if ( function_exists( 'flxf_payment_active_provider_id' ) && 'iyzico' === flxf_payment_active_provider_id() && ( $iyz = flxf_payment_provider( 'iyzico' ) ) && ! in_array( $cur, (array) $iyz->currencies(), true ) ) {
			$st   = 'warning';
			$msg .= ' ' . __( '\\niyzico is not set up for this currency (Donations › Payments › iyzico › currencies).\\n', 'flx-donate' );
		}
		$out[] = self::c( 'cfg.currency', 'Config', __( 'Currency', 'flx-donate' ), $st, $msg, self::settings_url( 'general' ), __( 'Settings', 'flx-donate' ) );

		$banks = flxb_bank_accounts();
		$out[] = $banks
			/* translators: %d: number of bank accounts */
			? self::c( 'cfg.bank', 'Config', __( '\\nBank accounts (transfer)\\n', 'flx-donate' ), 'ok', sprintf( _n( '[[\'%dbank account is listed for bank transfer gifts.\', \'en\']]', '[[\'%dbank accounts are listed for bank transfer gifts.\', \'en\']]', count( $banks ), 'flx-donate' ), count( $banks ) ) )
			: self::c( 'cfg.bank', 'Config', __( '\\nBank accounts (transfer)\\n', 'flx-donate' ), 'info', __( '\\nNo bank account is listed. They come from the theme (filter flxb_bank_accounts) and are used when recording bank transfer gifts.\\n', 'flx-donate' ) );

		$org   = trim( (string) flxb_setting( 'org_name', '' ) );
		$out[] = '' !== $org
			/* translators: %s: organisation name */
			? self::c( 'cfg.org', 'Config', __( '\\nOrganisation name\\n', 'flx-donate' ), 'ok', sprintf( __( '\\nReceipts and e-mails show "%s".\', \'en\']]', 'flx-donate' ), $org ) )
			/* translators: %s: site title */
			: self::c( 'cfg.org', 'Config', __( '\\nOrganisation name\\n', 'flx-donate' ), 'warning', sprintf( __( '\\nNot set; receipts and e-mails use the site title "%s".\\n', 'flx-donate' ), get_bloginfo( 'name' ) ), self::settings_url( 'organisation' ), __( 'Organisation', 'flx-donate' ) );

		$r     = self::result( 'mail' );
		$out[] = self::c(
			'mail',
			'Email',
			__( '[[\'E-mail sending\\n', 'flx-donate' ),
			$r ? ( $r['ok'] ? 'ok' : 'error' ) : 'info',
			/* translators: %s: e-mail address */
			( $r ? '' : sprintf( __( '\\nNot tested. Donor e-mails (recurring donation started, card declined) go out with wp_mail. The test sends one message to%s.\\n', 'flx-donate' ), get_option( 'admin_email' ) ) ) . ltrim( self::result_line( $r ) ),
			self::test_url( 'mail' ),
			__( '\\nSend test e-mail\\n', 'flx-donate' )
		);

		$out[] = '' === (string) get_option( 'permalink_structure' )
			? self::c( 'cfg.permalinks', 'Config', __( 'Permalinks', 'flx-donate' ), 'warning', __( '\\nPlain permalinks are in use. Payment callbacks still work through ?rest_route=, but some hosts and security plugins block that; choose "Post name".\\n', 'flx-donate' ), admin_url( 'options-permalink.php' ), __( 'Permalinks', 'flx-donate' ) )
			: self::c( 'cfg.permalinks', 'Config', __( 'Permalinks', 'flx-donate' ), 'ok', __( '\\nPretty permalinks are on; REST callbacks use /wp-json/.\\n', 'flx-donate' ) );

		$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$local = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) || preg_match( '/\.(test|local|localhost)$/', $host ) || ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() );
		$https = 0 === strpos( home_url(), 'https://' ) && 0 === strpos( site_url(), 'https://' );
		$out[] = $https
			? self::c( 'sec.https', 'Security', 'HTTPS', 'ok', __( '\\nThe site runs on HTTPS.\\n', 'flx-donate' ) )
			: ( $local
				? self::c( 'sec.https', 'Security', 'HTTPS', 'info', __( '\\nLocal test site without HTTPS. The live site must use HTTPS; payment providers refuse plain HTTP.\\n', 'flx-donate' ) )
				: self::c( 'sec.https', 'Security', 'HTTPS', 'error', __( '\\nThe site address is not HTTPS. Card payments and payment callbacks need HTTPS (Settings › General, and an SSL certificate from the host).\\n', 'flx-donate' ), admin_url( 'options-general.php' ), __( 'General settings', 'flx-donate' ) ) );

		$weak = array();
		foreach ( array( 'AUTH_KEY', 'AUTH_SALT', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' ) as $k ) {
			if ( ! defined( $k ) || strlen( (string) constant( $k ) ) < 32 || false !== strpos( (string) constant( $k ), 'put your unique phrase here' ) ) {
				$weak[] = $k;
			}
		}
		$out[] = $weak
			/* translators: %s: constant names */
			? self::c( 'sec.salts', 'Security', __( '\\nWordPress security keys (salts)\\n', 'flx-donate' ), 'warning', sprintf( __( '\\nMissing or weak in wp-config.php:%s. Saved iyzico cards are encrypted with these keys; set unique values once (https://api.wordpress.org/secret-key/1.1/salt/) and do not change them afterwards, or saved cards stop working.\\n', 'flx-donate' ), implode( ', ', $weak ) ) )
			: self::c( 'sec.salts', 'Security', __( '\\nWordPress security keys (salts)\\n', 'flx-donate' ), 'ok', __( '\\nSet in wp-config.php. Saved iyzico cards are encrypted with them: do not change them, or saved cards stop working.\\n', 'flx-donate' ) );

		$db    = (string) get_option( FLXB_Install::DB_OPTION, '' );
		$out[] = version_compare( $db, FLXB_Install::DB_VERSION, '>=' )
			/* translators: %s: version */
			? self::c( 'cfg.db', 'Config', __( 'Database tables', 'flx-donate' ), 'ok', sprintf( __( '\\nUp to date (%s).\\n', 'flx-donate' ), $db ) )
			/* translators: 1: stored version, 2: expected version */
			: self::c( 'cfg.db', 'Config', __( 'Database tables', 'flx-donate' ), 'error', sprintf( __( '\\nTables are at version%1$s, expected%2$s. Deactivate and activate Flux Donate to update them.\\n', 'flx-donate' ), '' !== $db ? $db : '—', FLXB_Install::DB_VERSION ), $plugins = admin_url( 'plugins.php' ), __( 'Plugins', 'flx-donate' ) );
		return $out;
	}

	private static function settings_url( $tab ) {
		return FLXB_Admin_Url::page( 'flxb-settings', array( 'tab' => $tab ) );
	}

	/** Fingerprint of the keys a test ran with (never the key itself). */
	private static function key_sig( array $c ) {
		return substr( hash( 'sha256', (string) $c['api_key'] . '|' . (string) $c['base'] ), 0, 12 );
	}

	/* ------------------------------------------------------------------
	 * Test buttons
	 * ------------------------------------------------------------------ */

	public static function test() {
		$what = isset( $_GET['test'] ) ? sanitize_key( wp_unslash( $_GET['test'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $what, array( 'iyzico', 'cron', 'mail' ), true ) ) {
			wp_die( '', 400 );
		}
		check_admin_referer( 'flxb_syscheck_' . $what );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '\\nSorry, you are not allowed to do that.\\n', 'flx-donate' ), 403 );
		}
		$r          = call_user_func( array( __CLASS__, 'test_' . $what ) );
		$r['at']    = time();
		$all        = get_option( self::TESTS, array() );
		$all        = is_array( $all ) ? $all : array();
		$all[ $what ] = $r;
		update_option( self::TESTS, $all, false );
		$anchor = array( 'iyzico' => 'flxsc-flxb.pay.iyzico', 'cron' => 'flxsc-flxb.cron.spawn', 'mail' => 'flxsc-flxb.mail' );
		wp_safe_redirect( self::url( array( 'flxb_test' => $what, 'flxb_ok' => $r['ok'] ? 1 : 0 ) ) . '#' . $anchor[ $what ] );
		exit;
	}

	/** One signed, harmless request (BIN lookup) proves the keys and the environment match. */
	private static function test_iyzico() {
		$c   = FLXB_Iyzico_Api::credentials();
		$api = FLXB_Iyzico_Api::make();
		if ( ! $api ) {
			return array( 'ok' => false, 'message' => __( '\\nNo iyzico keys to test.\\n', 'flx-donate' ), 'sig' => '' );
		}
		$env = $c['sandbox'] ? __( '\\nsandbox\\n', 'flx-donate' ) : __( '\\nlive\\n', 'flx-donate' );
		try {
			$res = $api->bin_check( '554960' );
		} catch ( Throwable $e ) {
			return array( 'ok' => false, 'message' => $e->getMessage(), 'sig' => self::key_sig( $c ) );
		}
		if ( FLXB_Iyzico_Api::ok( $res ) ) {
			/* translators: %s: sandbox or live */
			return array( 'ok' => true, 'message' => sprintf( __( '\\nConnected; iyzico accepted the keys (%s).\\n', 'flx-donate' ), $env ), 'sig' => self::key_sig( $c ) );
		}
		/* translators: 1: sandbox or live, 2: iyzico error */
		return array( 'ok' => false, 'message' => sprintf( __( '\\niyzico refused the keys (%1$s):%2$s. Check that the keys belong to this environment.\\n', 'flx-donate' ), $env, FLXB_Iyzico_Api::error_text( $res ) ), 'sig' => self::key_sig( $c ) );
	}

	private static function test_cron() {
		$url = add_query_arg( 'doing_wp_cron', sprintf( '%.22F', microtime( true ) ), site_url( 'wp-cron.php' ) );
		$res = wp_remote_post(
			$url,
			array(
				'timeout'   => 10,
				'blocking'  => true,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);
		if ( is_wp_error( $res ) ) {
			/* translators: %s: error */
			return array( 'ok' => false, 'message' => sprintf( __( '\\nThis server could not reach its own wp-cron.php (%s). WP-Cron cannot start from visits; add a server cron job.\\n', 'flx-donate' ), $res->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		return 200 === $code
			? array( 'ok' => true, 'message' => __( '\\nwp-cron.php answered; WP-Cron can start.\\n', 'flx-donate' ) )
			/* translators: %d: HTTP status */
			: array( 'ok' => false, 'message' => sprintf( __( '\\nwp-cron.php answered with HTTP%d(blocked by the host or a security plugin?). Add a server cron job.\\n', 'flx-donate' ), $code ) );
	}

	private static function test_mail() {
		$to    = (string) get_option( 'admin_email' );
		$error = '';
		$catch = function ( $e ) use ( &$error ) {
			$error = is_wp_error( $e ) ? $e->get_error_message() : '';
		};
		add_action( 'wp_mail_failed', $catch );
		/* translators: %s: site title */
		$sent = wp_mail( $to, sprintf( __( '\\n[%s] Test e-mail from Flux Donate\\n', 'flx-donate' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ), __( '\\nThis is a test from Donations › Settings › System check. If you can read it, donor e-mails can be sent.\\n', 'flx-donate' ) );
		remove_action( 'wp_mail_failed', $catch );
		return $sent
			/* translators: %s: e-mail address */
			? array( 'ok' => true, 'message' => sprintf( __( '\\nSent to%s. Check that it arrived (and not in spam).\\n', 'flx-donate' ), $to ) )
			/* translators: %s: error */
			: array( 'ok' => false, 'message' => sprintf( __( '\\nSending failed:%s. Set up SMTP (e.g. an SMTP plugin with your mail account).\\n', 'flx-donate' ), '' !== $error ? $error : __( 'unknown error', 'flx-donate' ) ) );
	}

	/* ------------------------------------------------------------------
	 * Settings tab
	 * ------------------------------------------------------------------ */

	public static function render_tab() {
		$what = FLXB_Admin::q( 'flxb_test', '' );
		$r    = '' !== $what ? self::result( $what ) : null;
		if ( $r ) {
			echo '<div class="flxb-notice ' . ( $r['ok'] ? 'flxb-notice--ok' : 'flxb-notice--warn' ) . '" role="status">' . esc_html( (string) $r['message'] ) . '</div>';
		}
		flx_system_checks_render( array( 'recheck_url' => self::url() ) );
		self::iyzico_box();
	}

	/** Notification addresses to enter in the iyzico panel (when Flux Donate takes iyzico payments). */
	public static function iyzico_box() {
		if ( function_exists( 'flxb_owns_payments' ) && flxb_owns_payments() && function_exists( 'flxf_payment_provider' ) && ( $iyz = flxf_payment_provider( 'iyzico' ) ) && ( 'iyzico' === flxf_payment_active_provider_id() || $iyz->is_configured() ) ) {
			echo '<div id="flxb-iyzico-addresses" style="max-width:1180px">' . FLXB_Iyzico_Notify::admin_box() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		}
	}
}
