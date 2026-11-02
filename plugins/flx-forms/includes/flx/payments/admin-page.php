<?php
/**
 * Flux Forms: "Payments" admin page (Forms › Payments), provider-neutral.
 *
 * Replaces the Stripe add-on's plain "Stripe Payments" page; the old slug
 * (admin.php?page=Emsfb_stripe) redirects here, and the Stripe webhook secret
 * form still posts to the add-on's own handler (admin_post_emsfb_stripe_save).
 *
 * Part of Flux Forms (GPL v2 or later), a fork of Easy Form Builder 4.2.0 by WhiteStudio.
 *
 * @package FLX_Form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXF_Payments_Admin {

	const SLUG     = 'flxf_payments';
	const OLD_SLUG = 'Emsfb_stripe';

	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 31 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	public static function menu() {
		/* Old links (and the webhook form's redirect) land on the new page. */
		if ( isset( $_GET['page'] ) && self::OLD_SLUG === $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			$args = array();
			if ( isset( $_GET['efb_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$args['efb_msg'] = sanitize_key( wp_unslash( $_GET['efb_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}
			wp_safe_redirect( self::url( $args ) );
			exit;
		}
		remove_submenu_page( 'Emsfb', self::OLD_SLUG );
		self::$hook = (string) add_submenu_page(
			'Emsfb',
			__( 'Payments', 'flx-forms' ),
			__( 'Payments', 'flx-forms' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function assets( $hook ) {
		if ( '' === self::$hook || $hook !== self::$hook ) {
			return;
		}
		$f = __DIR__ . '/assets/admin.css';
		wp_enqueue_style( 'flxf-payments-admin', plugins_url( 'assets/admin.css', __FILE__ ), array(), is_readable( $f ) ? (string) filemtime( $f ) : FLXF_FORK_VERSION );
	}

	/** Status => array( pill modifier, label ). */
	private static function status( $s ) {
		$s   = strtolower( (string) $s );
		$map = array(
			'completed'          => array( 'ok', __( 'Completed', 'flx-forms' ) ),
			'succeeded'          => array( 'ok', __( 'Completed', 'flx-forms' ) ),
			'active'             => array( 'ok', __( 'Active', 'flx-forms' ) ),
			'trialing'           => array( 'ok', __( 'Trial', 'flx-forms' ) ),
			'pending'            => array( 'pending', __( 'Pending', 'flx-forms' ) ),
			'processing'         => array( 'pending', __( 'Processing', 'flx-forms' ) ),
			'failed'             => array( 'failed', __( 'Failed', 'flx-forms' ) ),
			'canceled'           => array( 'failed', __( 'Canceled', 'flx-forms' ) ),
			'mismatch'           => array( 'failed', __( 'Amount mismatch', 'flx-forms' ) ),
			'past_due'           => array( 'failed', __( 'Past due', 'flx-forms' ) ),
			'unpaid'             => array( 'failed', __( 'Unpaid', 'flx-forms' ) ),
			'incomplete'         => array( 'failed', __( 'Incomplete', 'flx-forms' ) ),
			'incomplete_expired' => array( 'failed', __( 'Expired', 'flx-forms' ) ),
			'refunded'           => array( 'refunded', __( 'Refunded', 'flx-forms' ) ),
			'partially_refunded' => array( 'refunded', __( 'Partly refunded', 'flx-forms' ) ),
		);
		return $map[ $s ] ?? array( 'refunded', $s );
	}

	private static function money( $amount, $currency ) {
		if ( ! class_exists( '\Emsfb\Formbuilder' ) && is_readable( EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-formbuilder.php' ) ) {
			require_once EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-formbuilder.php';
		}
		if ( class_exists( '\Emsfb\Formbuilder' ) && method_exists( '\Emsfb\Formbuilder', 'formatPrice_efb' ) ) {
			return \Emsfb\Formbuilder::formatPrice_efb( (float) $amount, strtoupper( (string) $currency ) );
		}
		return number_format_i18n( (float) $amount, 2 ) . ' ' . strtoupper( (string) $currency );
	}

	private static function icon( $name ) {
		$p = array(
			'card'  => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
			'hook'  => '<path d="M18 16.98h-5.99c-1.1 0-1.95.94-2.48 1.9A4 4 0 0 1 2 17c.01-.7.2-1.4.57-2"/><path d="m6 17 3.13-5.78c.53-.97.1-2.18-.5-3.1a4 4 0 1 1 6.89-4.06"/><path d="m12 6 3.13 5.73C15.66 12.7 16.9 13 18 13a4 4 0 0 1 0 8"/>',
			'alert' => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
		);
		return '<svg class="flxfp-ico" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $p[ $name ] ?? '' ) . '</svg>';
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'flx-forms' ) );
		}
		global $wpdb;
		$rows = array();
		if ( class_exists( '\Emsfb\StripePayment' ) || is_readable( EMSFB_PLUGIN_DIRECTORY . 'vendor/stripe/class-Emsfb-stripe-payment.php' ) ) {
			if ( ! class_exists( '\Emsfb\StripePayment' ) ) {
				require_once EMSFB_PLUGIN_DIRECTORY . 'vendor/stripe/class-Emsfb-stripe-payment.php';
			}
			\Emsfb\StripePayment::maybe_install_table();
			$t    = \Emsfb\StripePayment::table_name();
			$rows = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY id DESC LIMIT 50" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$providers = flxf_payment_providers();
		$active    = flxf_payment_active_provider_id();
		$msg       = isset( $_GET['efb_msg'] ) ? sanitize_key( wp_unslash( $_GET['efb_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		echo '<div class="wrap flxfp">';
		echo '<div class="flxfp-head"><div><h1 class="flxfp-title">' . esc_html__( 'Payments', 'flx-forms' ) . '</h1>';
		echo '<p class="flxfp-sub">' . esc_html__( 'Payments taken through your forms, from every payment provider. API keys and the active provider are set on the plugin Settings page, Payment tab.', 'flx-forms' ) . '</p></div></div>';
		echo '<hr class="wp-header-end">';

		if ( 'saved' === $msg ) {
			echo '<div class="flxfp-notice flxfp-notice--ok" role="status">' . esc_html__( 'Settings saved.', 'flx-forms' ) . '</div>';
		} elseif ( 'invalid' === $msg ) {
			echo '<div class="flxfp-notice flxfp-notice--warn" role="alert">' . self::icon( 'alert' ) . esc_html__( 'A Stripe webhook signing secret starts with "whsec_".', 'flx-forms' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/* Providers + webhooks */
		echo '<h2 class="flxfp-h2">' . esc_html__( 'Providers and webhooks', 'flx-forms' ) . '</h2>';
		echo '<div class="flxfp-cards">';
		foreach ( $providers as $id => $p ) {
			$is_active = $id === $active;
			echo '<section class="flxfp-card' . ( $is_active ? ' is-active' : '' ) . '">';
			echo '<div class="flxfp-card__head"><span class="flxfp-card__icon">' . self::icon( 'card' ) . '</span><h3>' . esc_html( $p->label() ) . '</h3>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<span class="flxfp-pills">';
			if ( $is_active ) {
				echo '<span class="flxfp-pill flxfp-pill--ok">' . esc_html__( 'Active', 'flx-forms' ) . '</span>';
			}
			echo $p->is_configured()
				? '<span class="flxfp-pill flxfp-pill--soft">' . esc_html__( 'Configured', 'flx-forms' ) . '</span>'
				: '<span class="flxfp-pill flxfp-pill--pending">' . esc_html__( 'Not configured', 'flx-forms' ) . '</span>';
			echo '</span></div>';
			if ( method_exists( $p, 'admin_webhook_card' ) ) {
				$p->admin_webhook_card();
			} else {
				echo '<p class="flxfp-muted">' . esc_html__( 'Webhook URL for this provider (if it sends payment notifications):', 'flx-forms' ) . '</p>';
				echo '<p><code class="flxfp-code">' . esc_html( rest_url( 'Emsfb/v1/forms/payment/webhook/' . $id ) ) . '</code></p>';
			}
			echo '</section>';
		}
		echo '</div>';

		/* Recent payments */
		echo '<h2 class="flxfp-h2">' . esc_html__( 'Recent payments', 'flx-forms' ) . '</h2>';
		echo '<div class="flxfp-panel">';
		if ( empty( $rows ) ) {
			echo '<div class="flxfp-empty">' . self::icon( 'card' ) . '<p>' . esc_html__( 'No payments yet.', 'flx-forms' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<div class="flxfp-table-wrap"><table class="flxfp-table"><thead><tr>';
			foreach ( array( __( 'Date', 'flx-forms' ), __( 'Form', 'flx-forms' ), __( 'Confirmation code', 'flx-forms' ), __( 'Provider', 'flx-forms' ), __( 'Type', 'flx-forms' ), __( 'Amount', 'flx-forms' ), __( 'Status', 'flx-forms' ), __( 'Transaction ID', 'flx-forms' ), __( 'Email', 'flx-forms' ) ) as $i => $h ) {
				echo '<th scope="col"' . ( 5 === $i ? ' class="num"' : '' ) . '>' . esc_html( $h ) . '</th>';
			}
			echo '</tr></thead><tbody>';
			$fmt = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
			foreach ( $rows as $r ) {
				$ts       = $r->created_at ? strtotime( $r->created_at . ' UTC' ) : 0;
				$st       = self::status( $r->status );
				$provider = flxf_payment_provider( $r->gateway );
				$type     = 'subscription' === $r->payment_type ? __( 'Subscription', 'flx-forms' ) : __( 'One-time', 'flx-forms' );
				echo '<tr>';
				echo '<td>' . ( $ts ? '<time datetime="' . esc_attr( gmdate( 'c', $ts ) ) . '">' . esc_html( wp_date( $fmt, $ts ) ) . '</time>' : '' ) . '</td>';
				echo '<td><span class="flxfp-strong">' . esc_html( (string) $r->form_name ) . '</span> <span class="flxfp-muted">#' . (int) $r->form_id . '</span></td>';
				echo '<td><code>' . esc_html( (string) $r->track ) . '</code></td>';
				echo '<td>' . esc_html( $provider ? $provider->label() : (string) $r->gateway ) . '</td>';
				echo '<td>' . esc_html( $type . ( $r->interval_unit ? ' / ' . strtolower( (string) $r->interval_unit ) : '' ) ) . '</td>';
				echo '<td class="num">' . esc_html( self::money( $r->amount, $r->currency ) ) . '</td>';
				echo '<td><span class="flxfp-pill flxfp-pill--' . esc_attr( $st[0] ) . '">' . esc_html( $st[1] ) . '</span></td>';
				echo '<td><code class="flxfp-id">' . esc_html( $r->subscription_id ? (string) $r->subscription_id : (string) $r->transaction_id ) . '</code></td>';
				echo '<td>' . esc_html( (string) $r->payer_email ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table></div>';
			/* translators: %d: number of rows shown */
			echo '<p class="flxfp-foot flxfp-muted">' . esc_html( sprintf( __( 'The latest %d payments are shown.', 'flx-forms' ), 50 ) ) . '</p>';
		}
		echo '</div></div>';
	}
}

FLXF_Payments_Admin::init();
