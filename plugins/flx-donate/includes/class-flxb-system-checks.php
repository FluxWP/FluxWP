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
		return sprintf( __( '%1$s (%2$s ago)', 'flx-donate' ), flxb_date( gmdate( 'Y-m-d H:i:s', (int) $ts ) ), human_time_diff( (int) $ts, time() ) );
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
		return "\n" . sprintf( __( 'Last test %1$s: %2$s', 'flx-donate' ), self::when( (int) $r['at'] ), (string) $r['message'] );
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
				sprintf( __( 'WP-Cron is turned off in wp-config.php (DISABLE_WP_CRON). That is fine only if a server cron job calls %s every 5–15 minutes; without it recurring charges, retries and other scheduled tasks never run.', 'flx-donate' ), $cron_url )
				. ( $fresh ? ' ' . __( 'Scheduled tasks are running, so a server cron is in place.', 'flx-donate' ) : ' ' . __( 'Scheduled tasks have not run recently: check the server cron job (Hostinger: hPanel › Advanced › Cron Jobs).', 'flx-donate' ) )
			);
		} else {
			$out[] = self::c(
				'cron.mode',
				'Cron',
				__( 'WP-Cron mode', 'flx-donate' ),
				'info',
				__( 'WP-Cron runs when someone visits the site. On a quiet site tasks can run late; for reliable recurring charges add a server cron job that calls wp-cron.php every 15 minutes and set DISABLE_WP_CRON to true (see KURULUM.md).', 'flx-donate' )
				. ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ? ' ' . __( 'ALTERNATE_WP_CRON is on.', 'flx-donate' ) : '' )
			);
		}

		$next = wp_next_scheduled( FLXB_Iyzico_Recurring::HOOK );
		if ( $fresh ) {
			/* translators: %s: last run */
			$out[] = self::c( 'cron.heartbeat', 'Cron', __( 'Flux Donate hourly task', 'flx-donate' ), 'ok', sprintf( __( 'Last run: %s. Recurring iyzico charges are checked every hour.', 'flx-donate' ), self::when( $beat ) ) );
		} elseif ( ! $beat && $next && $next > time() && (int) get_option( 'flxb_cron_first_seen', 0 ) > time() - 2 * HOUR_IN_SECONDS ) {
			/* translators: %s: date */
			$out[] = self::c( 'cron.heartbeat', 'Cron', __( 'Flux Donate hourly task', 'flx-donate' ), 'info', sprintf( __( 'Not run yet; the first run is scheduled for %s.', 'flx-donate' ), flxb_date( gmdate( 'Y-m-d H:i:s', $next ) ) ) );
		} else {
			$msg   = $beat
				/* translators: %s: last run */
				? sprintf( __( 'Last run: %s, more than 2 hours ago.', 'flx-donate' ), self::when( $beat ) )
				: __( 'It has never run.', 'flx-donate' );
			$msg  .= ' ' . ( $disabled
				? __( 'WP-Cron is off, so a server cron job must call wp-cron.php. It seems to be missing or failing.', 'flx-donate' )
				: __( 'WP-Cron only runs on visits and may be blocked by the host or a security plugin. Use "Test cron" or add a server cron job.', 'flx-donate' ) );
			$msg  .= $next ? ' ' . sprintf( /* translators: %s: date */ __( 'Next scheduled: %s.', 'flx-donate' ), flxb_date( gmdate( 'Y-m-d H:i:s', $next ) ) ) : ' ' . __( 'The task is not scheduled; it is added again on the next page load.', 'flx-donate' );
			$out[] = self::c( 'cron.heartbeat', 'Cron', __( 'Flux Donate hourly task', 'flx-donate' ), 'warning', $msg, self::test_url( 'cron' ), __( 'Test cron', 'flx-donate' ) );
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
				__( 'Overdue scheduled tasks', 'flx-donate' ),
				$n > 2 ? 'warning' : 'info',
				/* translators: 1: number, 2: task names */
				sprintf( _n( '%1$d task is more than 15 minutes late: %2$s.', '%1$d tasks are more than 15 minutes late: %2$s.', $n, 'flx-donate' ), $n, implode( ', ', array_slice( array_keys( $late ), 0, 4 ) ) . ( $n > 4 ? '…' : '' ) ) . ' ' . __( 'A few late tasks right after a quiet period are normal; many mean cron is not running.', 'flx-donate' )
			)
			: self::c( 'cron.overdue', 'Cron', __( 'Overdue scheduled tasks', 'flx-donate' ), 'ok', __( 'No task is late.', 'flx-donate' ) );

		$r     = self::result( 'cron' );
		$out[] = self::c(
			'cron.spawn',
			'Cron',
			__( 'Cron test', 'flx-donate' ),
			$r ? ( $r['ok'] ? 'ok' : 'warning' ) : 'info',
			( $r ? '' : __( 'Not tested. The test asks this server to call wp-cron.php once, as WordPress does after a visit.', 'flx-donate' ) ) . ltrim( self::result_line( $r ) ),
			self::test_url( 'cron' ),
			__( 'Test cron', 'flx-donate' )
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
			$out[] = self::c( 'pay.provider', 'Payments', __( 'Card payment provider', 'flx-donate' ), 'error', __( 'No payment provider is available.', 'flx-donate' ), $page, __( 'Payment settings', 'flx-donate' ) );
			return $out;
		}
		$out[] = $p->is_configured()
			/* translators: %s: provider name */
			? self::c( 'pay.provider', 'Payments', __( 'Card payment provider', 'flx-donate' ), 'ok', sprintf( __( 'Card payments are taken with %s.', 'flx-donate' ), $p->label() ), $page, __( 'Payment settings', 'flx-donate' ) )
			/* translators: %s: provider name */
			: self::c( 'pay.provider', 'Payments', __( 'Card payment provider', 'flx-donate' ), 'error', sprintf( __( '%s is the active provider but its keys are missing, so donation forms show "Online payment is unavailable".', 'flx-donate' ), $p->label() ), $page, __( 'Enter keys', 'flx-donate' ) );

		$iyz = flxf_payment_provider( 'iyzico' );
		if ( $iyz && ( 'iyzico' === $active || $iyz->is_configured() ) ) {
			$c      = FLXB_Iyzico_Api::credentials();
			$src    = array(
				'woocommerce'              => __( 'Keys are read from the iyzico WooCommerce plugin settings.', 'flx-donate' ),
				'woocommerce-subscription' => __( 'Keys are read from the iyzico Subscription plugin settings.', 'flx-donate' ),
				'own'                      => __( 'Keys are entered in Donations › Payments › iyzico.', 'flx-donate' ),
			);
			$r      = self::result( 'iyzico' );
			$failed = $r && ! $r['ok'] && ( $r['sig'] ?? '' ) === self::key_sig( $c );
			if ( '' === $c['source'] ) {
				$st  = 'iyzico' === $active ? 'error' : 'warning';
				$msg = __( 'No iyzico API key and secret key found (neither in the iyzico WooCommerce plugin nor in Flux Donate).', 'flx-donate' );
			} else {
				$st  = $failed ? 'error' : ( $c['sandbox'] && 'iyzico' === $active ? 'warning' : 'ok' );
				$msg = ( $src[ $c['source'] ] ?? '' ) . ' ' . ( $c['sandbox']
					? __( 'Mode: sandbox (test). No real money is collected; switch to live keys before launch.', 'flx-donate' )
					: __( 'Mode: live. Real payments are taken.', 'flx-donate' ) );
				if ( ! $r ) {
					$msg .= ' ' . __( 'Use "Test connection" to check the keys with iyzico.', 'flx-donate' );
				}
			}
			$out[] = self::c( 'pay.iyzico', 'Payments', __( 'iyzico keys', 'flx-donate' ), $st, $msg . self::result_line( $r ), '' === $c['source'] ? $page : self::test_url( 'iyzico' ), '' === $c['source'] ? __( 'Enter keys', 'flx-donate' ) : __( 'Test connection', 'flx-donate' ) );
			$last  = get_option( FLXB_Iyzico_Notify::LAST_OPT );
			$hit   = is_array( $last ) && ! empty( $last['at'] ) && time() - (int) $last['at'] <= 30 * DAY_IN_SECONDS ? (int) $last['at'] : 0;
			/* translators: %s: notification URL */
			$msg   = sprintf( __( 'Paste into iyzico panel › Merchant notifications URL (İşyeri Bildirimleri Url): %s. Subscription notification URLs are not needed.', 'flx-donate' ), FLXB_Iyzico_Notify::url() );
			$msg  .= ' ' . ( $hit
				/* translators: %s: date */
				? sprintf( __( 'Last notification received: %s.', 'flx-donate' ), self::when( $hit ) )
				: __( 'No notification received in the last 30 days (normal until the address is saved in the iyzico panel and a payment is made).', 'flx-donate' ) );
			if ( '' !== FLXB_Iyzico_Notify::woo_key() ) {
				$msg .= ' ' . __( 'Notifications for WooCommerce orders are forwarded to the iyzico WooCommerce plugin.', 'flx-donate' );
			}
			$out[] = self::c( 'pay.iyzico.notify', 'Payments', __( 'iyzico notification URL', 'flx-donate' ), $hit ? 'ok' : 'info', $msg, ( self::external() ? FLXB_Admin_Url::page( 'flxb-settings' ) : self::url() ) . '#flxb-iyzico-addresses', __( 'Show addresses', 'flx-donate' ) );
			$failed_subs = (array) get_option( FLXB_Iyzico_Recurring::NOTICE_OPT, array() );
			if ( $failed_subs ) {
				/* translators: %d: number of recurring donations */
				$out[] = self::c( 'pay.iyzico.pastdue', 'Payments', __( 'Failed recurring charges', 'flx-donate' ), 'warning', sprintf( _n( '%d recurring donation stopped after its card was declined 3 times.', '%d recurring donations stopped after their cards were declined 3 times.', count( $failed_subs ), 'flx-donate' ), count( $failed_subs ) ), FLXB_Admin_Url::page( 'flxb-subscriptions', array( 'status' => 'past_due' ) ), __( 'Recurring donations', 'flx-donate' ) );
			}
		}

		$stripe = flxf_payment_provider( 'stripe' );
		if ( $stripe && ( 'stripe' === $active || $stripe->is_configured() ) ) {
			if ( ! $stripe->is_configured() ) {
				$out[] = self::c( 'pay.stripe', 'Payments', __( 'Stripe keys', 'flx-donate' ), 'error', __( 'The Stripe publishable key and secret key are missing.', 'flx-donate' ), $page, __( 'Enter keys', 'flx-donate' ) );
			} else {
				$live  = 0 === strpos( (string) $stripe->publishable_key(), 'pk_live_' );
				$hook  = '' !== $stripe->webhook_secret();
				$msg   = ( $live ? __( 'Mode: live.', 'flx-donate' ) : __( 'Mode: test (pk_test_). No real money is collected.', 'flx-donate' ) ) . ' '
					. ( $hook ? __( 'Webhook signing secret is set.', 'flx-donate' ) : __( 'Webhook signing secret is missing: refunds and renewals made in Stripe will not show up in Flux Donate.', 'flx-donate' ) );
				$out[] = self::c( 'pay.stripe', 'Payments', __( 'Stripe keys', 'flx-donate' ), $hook && ( $live || 'stripe' !== $active ) ? 'ok' : 'warning', $msg, $page, __( 'Payment settings', 'flx-donate' ) );
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
			$out[] = self::c( 'plugin.flxform', 'Plugins', 'Flux Forms', 'error', __( 'Flux Forms is not active. Donation forms and card payments need it.', 'flx-donate' ), $plugins, __( 'Plugins', 'flx-donate' ) );
		} else {
			$v     = defined( 'FLXF_FORK_VERSION' ) ? (string) FLXF_FORK_VERSION : '';
			$out[] = '' !== $v && version_compare( $v, self::MIN_FLXFORM, '>=' )
				/* translators: %s: version */
				? self::c( 'plugin.flxform', 'Plugins', 'Flux Forms', 'ok', sprintf( __( 'Active, version %s.', 'flx-donate' ), $v ) )
				/* translators: 1: installed version, 2: required version */
				: self::c( 'plugin.flxform', 'Plugins', 'Flux Forms', 'warning', sprintf( __( 'Version %1$s is active; Flux Donate needs %2$s or later for payments and prices to work together.', 'flx-donate' ), '' !== $v ? $v : '?', self::MIN_FLXFORM ), $plugins, __( 'Plugins', 'flx-donate' ) );
		}

		if ( defined( 'FLXS_VERSION' ) ) {
			$v     = (string) FLXS_VERSION;
			$out[] = version_compare( $v, self::MIN_FLXS, '>=' )
				/* translators: %s: version */
				? self::c( 'plugin.flxs', 'Plugins', 'Flux Certificates', 'ok', sprintf( __( 'Active, version %s. Certificates can be sent after donations.', 'flx-donate' ), $v ) )
				/* translators: 1: installed version, 2: required version */
				: self::c( 'plugin.flxs', 'Plugins', 'Flux Certificates', 'warning', sprintf( __( 'Version %1$s is active; %2$s or later is recommended.', 'flx-donate' ), $v, self::MIN_FLXS ), $plugins, __( 'Plugins', 'flx-donate' ) );
		} else {
			$out[] = self::c( 'plugin.flxs', 'Plugins', 'Flux Certificates', 'warning', __( 'Not active. Donations are recorded, but no certificate is created or sent.', 'flx-donate' ), $plugins, __( 'Plugins', 'flx-donate' ) );
		}

		$wc     = class_exists( 'WooCommerce' ) || self::plugin_active( 'woocommerce.php' );
		$iyz_wc = self::plugin_active( 'woocommerce-gateway-iyzico.php' );
		$iyz_s  = self::plugin_active( 'iyzico-subscription.php' );
		if ( ! $wc || ! class_exists( 'FLXB_WC' ) || ! FLXB_WC::active() ) {
			$out[] = self::c( 'plugin.wc', 'Plugins', 'WooCommerce', 'error', $wc
				/* translators: %s: version */
				? sprintf( __( 'WooCommerce is older than %s. Flux Donate 2.0 takes donations through WooCommerce; update it.', 'flx-donate' ), class_exists( 'FLXB_WC' ) ? FLXB_WC::MIN_WC : '8.0' )
				: __( 'WooCommerce is not active. Flux Donate 2.0 takes donations through the WooCommerce cart and checkout; until it is active, only the older Flux Forms payment layer works.', 'flx-donate' ), $plugins, __( 'Plugins', 'flx-donate' ) );
		}
		if ( defined( 'FLXWB_VERSION' ) ) {
			$out[] = self::c( 'plugin.flxwb', 'Plugins', 'Flux WooCommerce Bağış', 'error', __( 'The old Flux WooCommerce Bağış plugin is still active. Flux Donate 2.0 replaces it: deactivate and delete it, otherwise orders get two certificates and two messages.', 'flx-donate' ), $plugins, __( 'Plugins', 'flx-donate' ) );
		}
		$msg    = $wc ? __( 'WooCommerce is active.', 'flx-donate' ) : __( 'WooCommerce is not active.', 'flx-donate' );
		$msg   .= ' ' . ( $iyz_wc
			? __( 'The iyzico WooCommerce plugin is active: it takes one-time card payments, and Flux Donate reads its iyzico keys for monthly donations.', 'flx-donate' )
			: ( get_option( 'woocommerce_iyzico_settings' ) ? __( 'The iyzico WooCommerce plugin is not active, but its saved keys are still used.', 'flx-donate' ) : __( 'The iyzico WooCommerce plugin is not active; Flux Donate uses its own iyzico keys.', 'flx-donate' ) ) );
		if ( $iyz_s ) {
			$msg .= ' ' . __( 'The iyzico Subscription plugin is active; it renews WooCommerce subscriptions only. Flux Donate recurring donations use their own hourly task.', 'flx-donate' );
		}
		$out[] = self::c( 'plugin.woo', 'Plugins', __( 'WooCommerce and iyzico plugins', 'flx-donate' ), 'info', $msg );
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
			? self::c( 'cfg.types', 'Config', __( 'Open donation types', 'flx-donate' ), 'ok', sprintf( _n( '%d donation type is open and priced.', '%d donation types are open and priced.', count( $priced ), 'flx-donate' ), count( $priced ) ), $tpage, __( 'Donation types', 'flx-donate' ) )
			: self::c( 'cfg.types', 'Config', __( 'Open donation types', 'flx-donate' ), 'error', __( 'No donation type is open with a price (or free amount), so forms cannot take donations.', 'flx-donate' ), $tpage, __( 'Donation types', 'flx-donate' ) );

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
				? self::c( 'cfg.type_forms', 'Config', __( 'Forms of open donation types', 'flx-donate' ), 'warning', sprintf( __( 'No (existing) form is linked to: %s. Visitors cannot give to these types online.', 'flx-donate' ), implode( ', ', array_slice( $unlinked, 0, 6 ) ) . ( count( $unlinked ) > 6 ? '…' : '' ) ), $tpage, __( 'Donation types', 'flx-donate' ) )
				: self::c( 'cfg.type_forms', 'Config', __( 'Forms of open donation types', 'flx-donate' ), 'ok', __( 'Every open donation type has a form.', 'flx-donate' ) );
		}

		$cur   = flxb_default_currency();
		$st    = 'ok';
		/* translators: %s: currency code */
		$msg   = sprintf( __( 'Default currency: %s.', 'flx-donate' ), $cur );
		if ( function_exists( 'flxf_payment_active_provider_id' ) && 'iyzico' === flxf_payment_active_provider_id() && ( $iyz = flxf_payment_provider( 'iyzico' ) ) && ! in_array( $cur, (array) $iyz->currencies(), true ) ) {
			$st   = 'warning';
			$msg .= ' ' . __( 'iyzico is not set up for this currency (Donations › Payments › iyzico › currencies).', 'flx-donate' );
		}
		$out[] = self::c( 'cfg.currency', 'Config', __( 'Currency', 'flx-donate' ), $st, $msg, self::settings_url( 'general' ), __( 'Settings', 'flx-donate' ) );

		$banks = flxb_bank_accounts();
		$out[] = $banks
			/* translators: %d: number of bank accounts */
			? self::c( 'cfg.bank', 'Config', __( 'Bank accounts (transfer)', 'flx-donate' ), 'ok', sprintf( _n( '%d bank account is listed for bank transfer gifts.', '%d bank accounts are listed for bank transfer gifts.', count( $banks ), 'flx-donate' ), count( $banks ) ) )
			: self::c( 'cfg.bank', 'Config', __( 'Bank accounts (transfer)', 'flx-donate' ), 'info', __( 'No bank account is listed. They come from the theme (filter flxb_bank_accounts) and are used when recording bank transfer gifts.', 'flx-donate' ) );

		$org   = trim( (string) flxb_setting( 'org_name', '' ) );
		$out[] = '' !== $org
			/* translators: %s: organisation name */
			? self::c( 'cfg.org', 'Config', __( 'Organisation name', 'flx-donate' ), 'ok', sprintf( __( 'Receipts and e-mails show "%s".', 'flx-donate' ), $org ) )
			/* translators: %s: site title */
			: self::c( 'cfg.org', 'Config', __( 'Organisation name', 'flx-donate' ), 'warning', sprintf( __( 'Not set; receipts and e-mails use the site title "%s".', 'flx-donate' ), get_bloginfo( 'name' ) ), self::settings_url( 'organisation' ), __( 'Organisation', 'flx-donate' ) );

		$r     = self::result( 'mail' );
		$out[] = self::c(
			'mail',
			'Email',
			__( 'E-mail sending', 'flx-donate' ),
			$r ? ( $r['ok'] ? 'ok' : 'error' ) : 'info',
			/* translators: %s: e-mail address */
			( $r ? '' : sprintf( __( 'Not tested. Donor e-mails (recurring donation started, card declined) go out with wp_mail. The test sends one message to %s.', 'flx-donate' ), get_option( 'admin_email' ) ) ) . ltrim( self::result_line( $r ) ),
			self::test_url( 'mail' ),
			__( 'Send test e-mail', 'flx-donate' )
		);

		$out[] = '' === (string) get_option( 'permalink_structure' )
			? self::c( 'cfg.permalinks', 'Config', __( 'Permalinks', 'flx-donate' ), 'warning', __( 'Plain permalinks are in use. Payment callbacks still work through ?rest_route=, but some hosts and security plugins block that; choose "Post name".', 'flx-donate' ), admin_url( 'options-permalink.php' ), __( 'Permalinks', 'flx-donate' ) )
			: self::c( 'cfg.permalinks', 'Config', __( 'Permalinks', 'flx-donate' ), 'ok', __( 'Pretty permalinks are on; REST callbacks use /wp-json/.', 'flx-donate' ) );

		$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$local = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) || preg_match( '/\.(test|local|localhost)$/', $host ) || ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() );
		$https = 0 === strpos( home_url(), 'https://' ) && 0 === strpos( site_url(), 'https://' );
		$out[] = $https
			? self::c( 'sec.https', 'Security', 'HTTPS', 'ok', __( 'The site runs on HTTPS.', 'flx-donate' ) )
			: ( $local
				? self::c( 'sec.https', 'Security', 'HTTPS', 'info', __( 'Local test site without HTTPS. The live site must use HTTPS; payment providers refuse plain HTTP.', 'flx-donate' ) )
				: self::c( 'sec.https', 'Security', 'HTTPS', 'error', __( 'The site address is not HTTPS. Card payments and payment callbacks need HTTPS (Settings › General, and an SSL certificate from the host).', 'flx-donate' ), admin_url( 'options-general.php' ), __( 'General settings', 'flx-donate' ) ) );

		$weak = array();
		foreach ( array( 'AUTH_KEY', 'AUTH_SALT', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY' ) as $k ) {
			if ( ! defined( $k ) || strlen( (string) constant( $k ) ) < 32 || false !== strpos( (string) constant( $k ), 'put your unique phrase here' ) ) {
				$weak[] = $k;
			}
		}
		$out[] = $weak
			/* translators: %s: constant names */
			? self::c( 'sec.salts', 'Security', __( 'WordPress security keys (salts)', 'flx-donate' ), 'warning', sprintf( __( 'Missing or weak in wp-config.php: %s. Saved iyzico cards are encrypted with these keys; set unique values once (https://api.wordpress.org/secret-key/1.1/salt/) and do not change them afterwards, or saved cards stop working.', 'flx-donate' ), implode( ', ', $weak ) ) )
			: self::c( 'sec.salts', 'Security', __( 'WordPress security keys (salts)', 'flx-donate' ), 'ok', __( 'Set in wp-config.php. Saved iyzico cards are encrypted with them: do not change them, or saved cards stop working.', 'flx-donate' ) );

		$db    = (string) get_option( FLXB_Install::DB_OPTION, '' );
		$out[] = version_compare( $db, FLXB_Install::DB_VERSION, '>=' )
			/* translators: %s: version */
			? self::c( 'cfg.db', 'Config', __( 'Database tables', 'flx-donate' ), 'ok', sprintf( __( 'Up to date (%s).', 'flx-donate' ), $db ) )
			/* translators: 1: stored version, 2: expected version */
			: self::c( 'cfg.db', 'Config', __( 'Database tables', 'flx-donate' ), 'error', sprintf( __( 'Tables are at version %1$s, expected %2$s. Deactivate and activate Flux Donate to update them.', 'flx-donate' ), '' !== $db ? $db : '—', FLXB_Install::DB_VERSION ), $plugins = admin_url( 'plugins.php' ), __( 'Plugins', 'flx-donate' ) );
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
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'flx-donate' ), 403 );
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
			return array( 'ok' => false, 'message' => __( 'No iyzico keys to test.', 'flx-donate' ), 'sig' => '' );
		}
		$env = $c['sandbox'] ? __( 'sandbox', 'flx-donate' ) : __( 'live', 'flx-donate' );
		try {
			$res = $api->bin_check( '554960' );
		} catch ( Throwable $e ) {
			return array( 'ok' => false, 'message' => $e->getMessage(), 'sig' => self::key_sig( $c ) );
		}
		if ( FLXB_Iyzico_Api::ok( $res ) ) {
			/* translators: %s: sandbox or live */
			return array( 'ok' => true, 'message' => sprintf( __( 'Connected; iyzico accepted the keys (%s).', 'flx-donate' ), $env ), 'sig' => self::key_sig( $c ) );
		}
		/* translators: 1: sandbox or live, 2: iyzico error */
		return array( 'ok' => false, 'message' => sprintf( __( 'iyzico refused the keys (%1$s): %2$s. Check that the keys belong to this environment.', 'flx-donate' ), $env, FLXB_Iyzico_Api::error_text( $res ) ), 'sig' => self::key_sig( $c ) );
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
			return array( 'ok' => false, 'message' => sprintf( __( 'This server could not reach its own wp-cron.php (%s). WP-Cron cannot start from visits; add a server cron job.', 'flx-donate' ), $res->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		return 200 === $code
			? array( 'ok' => true, 'message' => __( 'wp-cron.php answered; WP-Cron can start.', 'flx-donate' ) )
			/* translators: %d: HTTP status */
			: array( 'ok' => false, 'message' => sprintf( __( 'wp-cron.php answered with HTTP %d (blocked by the host or a security plugin?). Add a server cron job.', 'flx-donate' ), $code ) );
	}

	private static function test_mail() {
		$to    = (string) get_option( 'admin_email' );
		$error = '';
		$catch = function ( $e ) use ( &$error ) {
			$error = is_wp_error( $e ) ? $e->get_error_message() : '';
		};
		add_action( 'wp_mail_failed', $catch );
		/* translators: %s: site title */
		$sent = wp_mail( $to, sprintf( __( '[%s] Test e-mail from Flux Donate', 'flx-donate' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ), __( 'This is a test from Donations › Settings › System check. If you can read it, donor e-mails can be sent.', 'flx-donate' ) );
		remove_action( 'wp_mail_failed', $catch );
		return $sent
			/* translators: %s: e-mail address */
			? array( 'ok' => true, 'message' => sprintf( __( 'Sent to %s. Check that it arrived (and not in spam).', 'flx-donate' ), $to ) )
			/* translators: %s: error */
			: array( 'ok' => false, 'message' => sprintf( __( 'Sending failed: %s. Set up SMTP (e.g. an SMTP plugin with your mail account).', 'flx-donate' ), '' !== $error ? $error : __( 'unknown error', 'flx-donate' ) ) );
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
