<?php
/**
 * Flux Forms: system checks on the shared `flx_system_checks` filter (see flx-system-checks.php).
 *
 * When Flux Donate is active its Donations › Settings › System check screen shows these lines too.
 * Without Flux Donate, Flux Forms shows the same screen at Forms › System check, so it is never missing.
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 *
 * @package FLX_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXF_System_Checks {

	const SLUG   = 'flxf_system';
	const SOURCE = 'Flux Forms';
	/** Flux Certificates version the certificate automations are built against (events + send queue). */
	const MIN_FLXS = '1.2.0';

	private static $hook = '';

	public static function init() {
		add_filter( 'flx_system_checks', array( __CLASS__, 'checks' ), 15 );
		add_filter( 'flx_system_checks_labels', array( __CLASS__, 'labels' ), 5 );
		add_filter(
			'flx_system_checks_versions',
			function ( $v ) {
				$v['flx-forms'] = defined( 'FLXF_FORK_VERSION' ) ? FLXF_FORK_VERSION : '';
				return $v;
			}
		);
		add_action( 'admin_menu', array( __CLASS__, 'moved' ), 1 ); // before WordPress refuses the unregistered page
		if ( ! self::flxb() ) {
			add_filter(
				'flx_system_checks_page_url',
				function ( $url ) {
					return '' !== (string) $url ? $url : self::url();
				},
				20
			);
			if ( ! self::external() ) {
				add_action( 'admin_menu', array( __CLASS__, 'menu' ), 40 ); // 2.1.2: with Flux Speed the screen lives there
			}
			if ( is_admin() ) {
				flx_system_checks_boot();
			}
		}
	}

	/** Flux Donate is active (it then hosts the screen). */
	private static function flxb() {
		return defined( 'FLXB_VERSION' );
	}

	/** 2.1.2: Flux Speed hosts the System check screen (Performance › System check). */
	private static function external() {
		return function_exists( 'flxp_system_checks_url' );
	}

	public static function url() {
		return self::external() ? flxp_system_checks_url() : add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) );
	}

	/** The old address goes to Flux Speed's screen. */
	public static function moved() {
		if ( self::external() && isset( $_GET['page'] ) && self::SLUG === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( self::url() );
			exit;
		}
	}

	public static function menu() {
		self::$hook = (string) add_submenu_page( 'Emsfb', __( 'System check', 'flx-forms' ), __( 'System check', 'flx-forms' ), 'manage_options', self::SLUG, array( __CLASS__, 'render' ) );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'flx-forms' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'System check', 'flx-forms' ) . '</h1><hr class="wp-header-end">';
		flx_system_checks_render( array( 'recheck_url' => self::url() ) );
		echo '</div>';
	}

	public static function labels( $l ) {
		return array_merge(
			(array) $l,
			array(
				'title'       => __( 'System check', 'flx-forms' ),
				'intro'       => __( 'Each line shows something the site needs and whether it is in place. Fix the errors first; warnings are worth a look.', 'flx-forms' ),
				'ok'          => __( 'OK', 'flx-forms' ),
				'warning'     => __( 'Warning', 'flx-forms' ),
				'error'       => __( 'Error', 'flx-forms' ),
				'info'        => __( 'Info', 'flx-forms' ),
				/* translators: 1: OK count, 2: warning count, 3: error count */
				'summary'     => __( '%1$d OK · %2$d warnings · %3$d errors', 'flx-forms' ),
				'recheck'     => __( 'Check again', 'flx-forms' ),
				'filter_hint' => __( 'Show only these (click again to show all)', 'flx-forms' ),
				/* translators: %s: time */
				'checked'     => __( 'Checked: %s', 'flx-forms' ),
				'col_status'  => __( 'Status', 'flx-forms' ),
				'col_check'   => __( 'Check', 'flx-forms' ),
				'col_detail'  => __( 'Details', 'flx-forms' ),
				'col_source'  => __( 'Source', 'flx-forms' ),
				'col_action'  => __( 'Action', 'flx-forms' ),
				'none'        => __( 'No checks are registered.', 'flx-forms' ),
				/* translators: %d: number of errors */
				'notice'      => __( 'System check found %d problem(s) that can stop donations or automations.', 'flx-forms' ),
				'notice_link' => __( 'Open system check', 'flx-forms' ),
				'dismiss'     => __( 'Hide until the next update', 'flx-forms' ),
				/* translators: %d: number of errors */
				'bar'         => __( 'System: %d error(s)', 'flx-forms' ),
				/* translators: %s: error message */
				'broken'      => __( 'This check could not run: %s', 'flx-forms' ),
				'groups'      => array(
					'Cron'     => __( 'Scheduled tasks (cron)', 'flx-forms' ),
					'Payments' => __( 'Payments', 'flx-forms' ),
					'Plugins'  => __( 'Plugins', 'flx-forms' ),
					'Config'   => __( 'Settings', 'flx-forms' ),
					'Email'    => __( 'E-mail', 'flx-forms' ),
					'Security' => __( 'Security', 'flx-forms' ),
					'Forms'    => __( 'Forms', 'flx-forms' ),
					'Other'    => __( 'Other', 'flx-forms' ),
				),
			)
		);
	}

	private static function c( $id, $group, $label, $status, $message, $url = '', $action = '' ) {
		return array(
			'id'           => 'flxf.' . $id,
			'source'       => self::SOURCE,
			'group'        => $group,
			'label'        => $label,
			'status'       => $status,
			'message'      => $message,
			'action_url'   => $url,
			'action_label' => $action,
		);
	}

	public static function checks( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		foreach ( array( 'payments', 'plugins', 'automations', 'security', 'uploads' ) as $part ) {
			try {
				$checks = array_merge( $checks, self::$part() );
			} catch ( Throwable $e ) {
				/* translators: %s: error message */
				$checks[] = self::c( 'broken.' . $part, 'Config', __( 'System check', 'flx-forms' ), 'error', sprintf( __( 'This check could not run: %s', 'flx-forms' ), $e->getMessage() ) );
			}
		}
		return $checks;
	}

	/** Number of forms whose structure matches a LIKE pattern (or of a type). */
	private static function count_forms( $where, $arg ) {
		global $wpdb;
		$t = $wpdb->prefix . 'emsfb_form';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) ) !== $t ) {
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE {$where}", $arg ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and fixed clause.
	}

	private static function payments() {
		$pay = self::count_forms( 'form_type = %s', 'payment' );
		if ( self::flxb() ) {
			$delegated = function_exists( 'flxb_owns_payments' ) && flxb_owns_payments();
			return array(
				$delegated
					? self::c( 'handoff', 'Payments', __( 'Payment hand-off', 'flx-forms' ), 'ok', __( 'Flux Donate handles payments (providers, keys, webhooks): Donations › Payments.', 'flx-forms' ) )
					: self::c( 'handoff', 'Payments', __( 'Payment hand-off', 'flx-forms' ), 'warning', __( 'Flux Donate is active but did not take over payments (filter flxb_own_payments is off?). Flux Forms uses its own Stripe settings.', 'flx-forms' ) ),
			);
		}
		if ( ! function_exists( 'flxf_payment_active_provider' ) ) {
			return array();
		}
		$p   = flxf_payment_active_provider();
		$url = admin_url( 'admin.php?page=Emsfb' );
		if ( $p && $p->is_configured() ) {
			/* translators: %s: provider name */
			return array( self::c( 'handoff', 'Payments', __( 'Payments', 'flx-forms' ), 'ok', sprintf( __( 'Flux Donate is not active; Flux Forms takes payments itself with %s.', 'flx-forms' ), $p->label() ) ) );
		}
		return array(
			self::c(
				'handoff',
				'Payments',
				__( 'Payments', 'flx-forms' ),
				$pay ? 'error' : 'info',
				( $pay
					/* translators: %d: number of payment forms */
					? sprintf( _n( 'Flux Donate is not active and the Stripe keys are missing: %d payment form shows "Online payment is unavailable".', 'Flux Donate is not active and the Stripe keys are missing: %d payment forms show "Online payment is unavailable".', $pay, 'flx-forms' ), $pay )
					: __( 'Flux Donate is not active and no Stripe keys are set. No payment form exists, so nothing is affected.', 'flx-forms' ) ),
				$url,
				__( 'Panel › Settings › Payment', 'flx-forms' )
			),
		);
	}

	private static function plugins() {
		$out = array();
		if ( ! self::flxb() ) {
			$out[] = self::c( 'flxb', 'Plugins', 'Flux Donate', 'info', __( 'Not active. Forms work; donations are not tracked and recurring iyzico payments are not available.', 'flx-forms' ), admin_url( 'plugins.php' ), __( 'Plugins', 'flx-forms' ) );
		}
		$cert = self::cert_flows();
		if ( defined( 'FLXS_VERSION' ) ) {
			$v     = (string) FLXS_VERSION;
			$out[] = version_compare( $v, self::MIN_FLXS, '>=' )
				/* translators: %s: version */
				? self::c( 'flxs', 'Plugins', __( 'Flux Certificates (automations)', 'flx-forms' ), 'ok', sprintf( __( 'Version %s is active; certificate automations can run.', 'flx-forms' ), $v ) )
				/* translators: 1: installed version, 2: required version */
				: self::c( 'flxs', 'Plugins', __( 'Flux Certificates (automations)', 'flx-forms' ), 'warning', sprintf( __( 'Version %1$s is active; certificate automations need %2$s or later.', 'flx-forms' ), $v, self::MIN_FLXS ), admin_url( 'plugins.php' ), __( 'Plugins', 'flx-forms' ) );
		} else {
			$out[] = $cert
				/* translators: %d: number of flows */
				? self::c( 'flxs', 'Plugins', __( 'Flux Certificates (automations)', 'flx-forms' ), 'error', sprintf( _n( 'Not active, but %d active automation creates or sends certificates; it will fail.', 'Not active, but %d active automations create or send certificates; they will fail.', $cert, 'flx-forms' ), $cert ), admin_url( 'plugins.php' ), __( 'Plugins', 'flx-forms' ) )
				: self::c( 'flxs', 'Plugins', __( 'Flux Certificates (automations)', 'flx-forms' ), 'info', __( 'Not active. Needed only for certificate automations.', 'flx-forms' ) );
		}
		return $out;
	}

	/** Active flows with a certificate step. */
	private static function cert_flows() {
		if ( ! class_exists( 'FLXF_Automations' ) ) {
			return 0;
		}
		$n = 0;
		foreach ( FLXF_Automations::get_flows( true ) as $f ) {
			foreach ( (array) ( $f['actions'] ?? array() ) as $a ) {
				if ( in_array( $a['type'] ?? '', array( 'create_certificate', 'send_certificate' ), true ) ) {
					++$n;
					break;
				}
			}
		}
		return $n;
	}

	private static function automations() {
		if ( ! class_exists( 'FLXF_Automations' ) ) {
			return array();
		}
		$url   = admin_url( 'admin.php?page=flxf_automations' );
		$flows = FLXF_Automations::get_flows( true );
		if ( ! $flows ) {
			return array( self::c( 'automations', 'Forms', __( 'Automations', 'flx-forms' ), 'info', __( 'No automation is on. After a form or payment nothing runs automatically (certificate, e-mail, webhook).', 'flx-forms' ), $url, __( 'Automations', 'flx-forms' ) ) );
		}
		$since = time() - 7 * DAY_IN_SECONDS;
		$bad   = 0;
		foreach ( FLXF_Automations::get_runs() as $r ) {
			$r = isset( $r['log'] ) && is_array( $r['log'] ) ? $r['log'] : $r;
			if ( (int) ( $r['time'] ?? 0 ) >= $since && 'error' === ( $r['status'] ?? '' ) ) {
				++$bad;
			}
		}
		/* translators: %d: number of flows */
		$msg = sprintf( _n( '%d automation is on.', '%d automations are on.', count( $flows ), 'flx-forms' ), count( $flows ) );
		if ( $bad ) {
			/* translators: %d: number of failed runs */
			$msg .= ' ' . sprintf( _n( '%d run failed in the last 7 days; see the run log.', '%d runs failed in the last 7 days; see the run log.', $bad, 'flx-forms' ), $bad );
		}
		return array( self::c( 'automations', 'Forms', __( 'Automations', 'flx-forms' ), $bad ? 'warning' : 'ok', $msg, $url, __( 'Automations', 'flx-forms' ) ) );
	}

	private static function security() {
		$n = self::count_forms( 'form_structer LIKE %s', '%"captcha":true%' ) + self::count_forms( 'form_structer LIKE %s', '%"captcha":"1"%' ) + self::count_forms( 'form_structer LIKE %s', '%"captcha":1%' );
		if ( ! $n ) {
			return array();
		}
		$s   = json_decode( (string) get_option( 'emsfb_settings', '' ), true );
		$s   = is_array( $s ) ? $s : array();
		$ok  = strlen( (string) ( $s['siteKey'] ?? '' ) ) > 5 && strlen( (string) ( $s['secretKey'] ?? '' ) ) > 5;
		$url = admin_url( 'admin.php?page=Emsfb' );
		return array(
			$ok
				/* translators: %d: number of forms */
				? self::c( 'recaptcha', 'Security', 'Google reCAPTCHA', 'ok', sprintf( _n( 'Keys are set; %d form uses reCAPTCHA.', 'Keys are set; %d forms use reCAPTCHA.', $n, 'flx-forms' ), $n ) )
				/* translators: %d: number of forms */
				: self::c( 'recaptcha', 'Security', 'Google reCAPTCHA', 'error', sprintf( _n( '%d form has reCAPTCHA on but the site key or secret key is missing; the form is not shown.', '%d forms have reCAPTCHA on but the site key or secret key is missing; they are not shown.', $n, 'flx-forms' ), $n ), $url, __( 'Settings', 'flx-forms' ) ),
		);
	}

	private static function uploads() {
		$u = wp_upload_dir( null, false );
		if ( ! empty( $u['error'] ) ) {
			/* translators: %s: error */
			return array( self::c( 'uploads', 'Config', __( 'Uploads folder', 'flx-forms' ), 'error', sprintf( __( 'The uploads folder is not available: %s', 'flx-forms' ), wp_strip_all_tags( (string) $u['error'] ) ) ) );
		}
		return array(
			wp_is_writable( $u['basedir'] )
				? self::c( 'uploads', 'Config', __( 'Uploads folder', 'flx-forms' ), 'ok', __( 'Writable; files sent with forms can be saved.', 'flx-forms' ) )
				/* translators: %s: folder path */
				: self::c( 'uploads', 'Config', __( 'Uploads folder', 'flx-forms' ), 'error', sprintf( __( 'Not writable: %s. File fields in forms cannot save uploads; ask the host to fix the folder permissions.', 'flx-forms' ), $u['basedir'] ) ),
		);
	}
}
