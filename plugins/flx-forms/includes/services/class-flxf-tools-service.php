<?php
/**
 * Forms › Payments, System check and Add-ons for the Forms screen (GraphQL). Reads and writes what the
 * classic pages did: the payment records table and provider list (includes/flx/payments), the shared
 * flx_system_checks rows (includes/flx/system-checks) and the bundled add-ons (Flux Forms keeps them always on:
 * Emsfb::get_setting_Emsfb sets AdnOF, AdnSPF and AdnSMF).
 *
 * @package FLX_Forms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Payments, system check and add-on data behind flxForms* operations.
 */
final class FLXF_Tools_Service {

	/** Payments listed. */
	public const PAYMENTS_LIMIT = 50;

	private const STRIPE_FILE = 'vendor/stripe/class-Emsfb-stripe-payment.php';

	/* ------------------------------------------------------------ Payments */

	/**
	 * Flux Forms takes payments itself (with Flux Donate the payment layer and its screen are there).
	 */
	public static function payments_available(): bool {
		return class_exists( 'FLXF_Payments_Admin' ) && function_exists( 'flxf_payment_providers' );
	}

	/**
	 * Providers and the latest payments.
	 *
	 * @return array<string,mixed>
	 */
	public static function payments(): array {
		if ( ! self::payments_available() ) {
			return array(
				'available' => false,
				'providers' => array(),
				'payments'  => array(),
				'limit'     => self::PAYMENTS_LIMIT,
			);
		}
		$active    = function_exists( 'flxf_payment_active_provider_id' ) ? self::str( flxf_payment_active_provider_id() ) : '';
		$providers = array();
		foreach ( (array) flxf_payment_providers() as $id => $p ) {
			$id     = self::str( $id );
			$stripe = 'stripe' === $id && self::stripe_store();
			$providers[] = array(
				'id'           => $id,
				'label'        => self::str( $p->label() ),
				'active'       => $id === $active,
				'configured'   => (bool) $p->is_configured(),
				'webhookUrl'   => $stripe ? rest_url( 'Emsfb/v1/forms/payment/stripe/webhook' ) : rest_url( 'Emsfb/v1/forms/payment/webhook/' . $id ),
				'hint'         => $stripe
					? __( 'Optional but recommended: in the Stripe Dashboard add a webhook endpoint with this URL and the events payment_intent.succeeded, payment_intent.payment_failed, payment_intent.canceled, charge.refunded, customer.subscription.updated and customer.subscription.deleted, then paste its signing secret below.', 'flx-forms' )
					: __( 'Webhook URL for this provider (if it sends payment notifications):', 'flx-forms' ),
				'secretOption' => $stripe,
				'secretSet'    => $stripe && '' !== self::str( self::stripe_call( 'webhook_secret' ) ),
				'secretFixed'  => $stripe && defined( 'EMSFB_STRIPE_WEBHOOK_SECRET' ) && EMSFB_STRIPE_WEBHOOK_SECRET,
			);
		}
		return array(
			'available' => true,
			'providers' => $providers,
			'payments'  => self::payment_rows(),
			'limit'     => self::PAYMENTS_LIMIT,
		);
	}

	/**
	 * Saves or removes the Stripe webhook signing secret (same rule as the classic form).
	 */
	public static function stripe_secret( string $secret, bool $clear ): bool|WP_Error {
		if ( ! self::payments_available() || ! self::stripe_store() ) {
			return self::invalid( 'secret', __( 'Stripe is not available.', 'flx-forms' ) );
		}
		if ( $clear ) {
			delete_option( self::stripe_secret_option() );
			return true;
		}
		$secret = trim( sanitize_text_field( $secret ) );
		if ( '' === $secret ) {
			return true;
		}
		if ( 0 !== strpos( $secret, 'whsec_' ) ) {
			return self::invalid( 'secret', __( 'A Stripe webhook signing secret starts with "whsec_".', 'flx-forms' ) );
		}
		update_option( self::stripe_secret_option(), $secret, false );
		return true;
	}

	/**
	 * The Stripe add-on class that owns the payment records table.
	 */
	private static function stripe_store(): bool {
		if ( ! class_exists( '\Emsfb\StripePayment' ) ) {
			$f = EMSFB_PLUGIN_DIRECTORY . self::STRIPE_FILE;
			if ( is_readable( $f ) ) {
				require_once $f;
			}
		}
		return class_exists( '\Emsfb\StripePayment' );
	}

	/**
	 * A static method of the Stripe add-on class (legacy code, loaded at runtime).
	 *
	 * @param string $method Method name.
	 * @return mixed
	 */
	private static function stripe_call( string $method ) {
		$cb = array( '\Emsfb\StripePayment', $method );
		return is_callable( $cb ) ? call_user_func( $cb ) : null;
	}

	/** Option name of the Stripe webhook signing secret. */
	private static function stripe_secret_option(): string {
		$c = '\Emsfb\StripePayment::SECRET_OPTION';
		return defined( $c ) ? self::str( constant( $c ) ) : 'emsfb_stripe_webhook_secret';
	}

	/**
	 * Latest payment records.
	 *
	 * @return list<array<string,mixed>>
	 */
	private static function payment_rows(): array {
		global $wpdb;
		/** @var wpdb $wpdb */
		if ( ! self::stripe_store() ) {
			return array();
		}
		self::stripe_call( 'maybe_install_table' );
		$t    = self::str( self::stripe_call( 'table_name' ) );
		/** @var list<array<string,mixed>> $rows */
		// @phpstan-ignore argument.type
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} ORDER BY id DESC LIMIT %d", self::PAYMENTS_LIMIT ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
		$out  = array();
		foreach ( $rows as $r ) {
			$gateway  = self::str( $r['gateway'] ?? '' );
			$provider = function_exists( 'flxf_payment_provider' ) ? flxf_payment_provider( $gateway ) : null;
			$status   = strtolower( self::str( $r['status'] ?? '' ) );
			$st       = self::status( $status );
			$created  = self::str( $r['created_at'] ?? '' );
			$sub      = self::str( $r['subscription_id'] ?? '' );
			$amount   = is_numeric( $r['amount'] ?? null ) ? (float) $r['amount'] : 0.0;
			$currency = strtoupper( self::str( $r['currency'] ?? '' ) );
			$out[]    = array(
				'id'          => self::num( $r['id'] ?? 0 ),
				'date'        => flx_gql_datetime( $created ),
				'formId'      => self::num( $r['form_id'] ?? 0 ),
				'formName'    => self::str( $r['form_name'] ?? '' ),
				'track'       => self::str( $r['track'] ?? '' ),
				'provider'    => $provider instanceof FLXF_Payment_Provider ? self::str( $provider->label() ) : $gateway,
				'type'        => 'subscription' === self::str( $r['payment_type'] ?? '' ) ? 'subscription' : 'one-time',
				'interval'    => strtolower( self::str( $r['interval_unit'] ?? '' ) ),
				'amount'      => $amount,
				'currency'    => $currency,
				'formatted'   => self::money( $amount, $currency ),
				'status'      => $status,
				'statusLabel' => $st[1],
				'tone'        => $st[0],
				'reference'   => '' !== $sub ? $sub : self::str( $r['transaction_id'] ?? '' ),
				'email'       => self::str( $r['payer_email'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * Tone and label of a payment status.
	 *
	 * @return array{0:string,1:string}
	 */
	private static function status( string $s ): array {
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

	private static function money( float $amount, string $currency ): string {
		if ( ! class_exists( '\Emsfb\Formbuilder' ) && is_readable( EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-formbuilder.php' ) ) {
			require_once EMSFB_PLUGIN_DIRECTORY . 'includes/class-Emsfb-formbuilder.php';
		}
		if ( class_exists( '\Emsfb\Formbuilder' ) ) {
			return self::str( \Emsfb\Formbuilder::formatPrice_efb( $amount, $currency ) );
		}
		return trim( number_format_i18n( $amount, 2 ) . ' ' . $currency );
	}

	/* ------------------------------------------------------------ System check */

	/**
	 * Forms shows the System check screen: Flux Donate and Flux Speed host it when they are active.
	 */
	public static function system_available(): bool {
		return ! defined( 'FLXB_VERSION' ) && ! function_exists( 'flxp_system_checks_url' ) && function_exists( 'flx_system_checks_sorted' );
	}

	/**
	 * Every flx_system_checks row, in display order.
	 *
	 * @return array<string,mixed>
	 */
	public static function system(): array {
		if ( ! function_exists( 'flx_system_checks_sorted' ) ) {
			return array(
				'available' => false,
				'checks'    => array(),
			);
		}
		$labels = function_exists( 'flx_system_checks_labels' ) ? (array) flx_system_checks_labels() : array();
		$groups = is_array( $labels['groups'] ?? null ) ? $labels['groups'] : array();
		$checks = array();
		foreach ( (array) flx_system_checks_sorted() as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$group    = self::str( $c['group'] ?? 'Other' );
			$status   = self::str( $c['status'] ?? 'info' );
			$checks[] = array(
				'id'          => self::str( $c['id'] ?? '' ),
				'source'      => self::str( $c['source'] ?? '' ),
				'group'       => $group,
				'groupLabel'  => self::str( $groups[ $group ] ?? $group ),
				'label'       => self::str( $c['label'] ?? '' ),
				'status'      => in_array( $status, array( 'ok', 'warning', 'error', 'info' ), true ) ? $status : 'info',
				'message'     => self::str( $c['message'] ?? '' ),
				'actionUrl'   => self::str( $c['action_url'] ?? '' ),
				'actionLabel' => self::str( $c['action_label'] ?? '' ),
			);
		}
		return array(
			'available' => self::system_available(),
			'checks'    => $checks,
		);
	}

	/* ------------------------------------------------------------ Add-ons */

	/**
	 * Add-ons that ship with Flux Forms: title and description keys of the classic list.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	private static function addon_texts(): array {
		return array(
			'AdnSPF' => array( __( 'Stripe Payment Add-on', 'flx-forms' ), __( 'The Stripe add-on for Flux Forms enables you to integrate your WordPress site with Stripe for payment processing, donations, and online orders.', 'flx-forms' ) ),
			'AdnOF'  => array( __( 'Offline Forms Add-on', 'flx-forms' ), __( 'The Offline Forms add-on for Flux Forms allows users to save their progress when filling out forms in offline situations.', 'flx-forms' ) ),
			'AdnSMF' => array( __( 'Conditional logic Add-on', 'flx-forms' ), __( 'The Conditional Logic Add-on enables dynamic and interactive forms based on specific user inputs or conditional rules. It allows for highly personalized forms tailored to meet users’ unique needs.', 'flx-forms' ) ),
		);
	}

	/**
	 * The bundled add-ons with their state.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function addons(): array {
		$fn = function_exists( 'get_efbFunction' ) ? get_efbFunction() : null;
		if ( ! is_object( $fn ) || ! method_exists( $fn, 'fun_get_addons_list_efb' ) ) {
			return array();
		}
		$ac       = function_exists( 'get_setting_Emsfb' ) ? get_setting_Emsfb( 'decoded' ) : null;
		$state    = $fn->fun_get_addons_list_efb( is_object( $ac ) ? $ac : null );
		$state    = is_array( $state ) ? $state : array();
		$required = method_exists( $fn, 'get_addon_required_files_efb' ) ? $fn->get_addon_required_files_efb() : array();
		$required = is_array( $required ) ? $required : array();
		$issues   = function_exists( 'emsfb_get_incompatible_addons_efb' ) ? emsfb_get_incompatible_addons_efb( $ac ) : array();
		$issues   = is_array( $issues ) ? $issues : array();
		$bundled  = function_exists( 'flxf_fork_bundled_addons' ) ? (array) flxf_fork_bundled_addons() : array_keys( self::addon_texts() );
		$out      = array();
		foreach ( self::addon_texts() as $key => $text ) {
			if ( ! in_array( $key, $bundled, true ) ) {
				continue;
			}
			$files = in_array( $key, $bundled, true ) && ! empty( $required[ $key ] );
			foreach ( $files ? (array) $required[ $key ] : array() as $rel ) {
				if ( ! file_exists( EMSFB_PLUGIN_DIRECTORY . self::str( $rel ) ) ) {
					$files = false;
				}
			}
			$issue    = is_array( $issues[ $key ] ?? null ) ? $issues[ $key ] : null;
			$missing  = array();
			$disabled = array();
			if ( $issue ) {
				foreach ( (array) ( $issue['disabled_functions'] ?? array() ) as $f ) {
					$disabled[] = self::str( $f );
				}
				foreach ( (array) ( $issue['missing_extension_functions'] ?? array() ) as $f ) {
					$missing[] = self::str( $f );
				}
			}
			$out[] = array(
				'key'               => $key,
				'title'             => $text[0],
				'description'       => $text[1],
				'enabled'           => self::num( $state[ $key ] ?? 0 ) >= 1,
				'available'         => $files,
				'disabledFunctions' => $disabled,
				'missingFunctions'  => $missing,
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------ Helpers */

	private static function invalid( string $field, string $message ): WP_Error {
		return new WP_Error(
			'flxf_invalid',
			$message,
			array(
				'graphql_code' => 'INVALID_INPUT',
				'field'        => $field,
				'status'       => 400,
			)
		);
	}

	private static function str( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	private static function num( mixed $value ): int {
		if ( is_int( $value ) ) {
			return $value;
		}
		if ( is_float( $value ) || ( is_string( $value ) && is_numeric( $value ) ) ) {
			return (int) $value;
		}
		return 0;
	}
}
