<?php
/**
 * Stripe add-on (AdnSPF): payment records and the admin "Stripe" page.
 *
 * Core contract (includes/class-Emsfb-public.php, includes/class-Emsfb.php):
 *   - new \Emsfb\StripePayment()            (wp-admin bootstrap)
 *   - StripePayment::insert_payment( array ) (after a PaymentIntent / subscription is created)
 *   - StripePayment::update_payment( int $id, array )
 *   - table {$wpdb->prefix}emsfb_pay_ with at least id, track, gateway,
 *     transaction_id, status, capture_id.
 *
 * @package Easy_Form_Builder
 */

namespace Emsfb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-Emsfb-stripe-api.php';

if ( ! class_exists( '\Emsfb\StripePayment' ) ) {

	class StripePayment {

		const DB_VERSION        = '1.0.0';
		const DB_VERSION_OPTION = 'emsfb_pay_table_version';
		const SECRET_OPTION     = 'emsfb_stripe_webhook_secret';
		const PAGE_SLUG         = 'Emsfb_stripe';

		/** Columns callers may write. */
		const COLUMNS = array(
			'form_id'         => '%d',
			'track'           => '%s',
			'gateway'         => '%s',
			'payment_type'    => '%s',
			'transaction_id'  => '%s',
			'capture_id'      => '%s',
			'subscription_id' => '%s',
			'plan_id'         => '%s',
			'product_id'      => '%s',
			'interval_unit'   => '%s',
			'amount'          => '%f',
			'currency'        => '%s',
			'status'          => '%s',
			'payer_email'     => '%s',
			'uid'             => '%s',
			'ip'              => '%s',
			'form_name'       => '%s',
			'meta'            => '%s',
		);

		public function __construct() {
			if ( is_admin() ) {
				add_action( 'admin_init', array( __CLASS__, 'maybe_install_table' ) );
				add_action( 'admin_menu', array( $this, 'register_admin_page' ), 30 );
				add_action( 'admin_post_emsfb_stripe_save', array( $this, 'handle_save_settings' ) );
			}
		}

		/* ------------------------------------------------------------------
		 * Storage
		 * ------------------------------------------------------------------ */

		public static function table_name() {
			global $wpdb;
			return $wpdb->prefix . 'emsfb_pay_';
		}

		public static function maybe_install_table() {
			if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
				return;
			}
			self::install_table();
		}

		public static function install_table() {
			global $wpdb;
			$table   = self::table_name();
			$charset = $wpdb->get_charset_collate();
			$sql     = "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  form_id bigint(20) unsigned NOT NULL DEFAULT 0,
  track varchar(64) NOT NULL DEFAULT '',
  gateway varchar(32) NOT NULL DEFAULT '',
  payment_type varchar(32) NOT NULL DEFAULT 'one-time',
  transaction_id varchar(191) NOT NULL DEFAULT '',
  capture_id varchar(191) NOT NULL DEFAULT '',
  subscription_id varchar(191) NOT NULL DEFAULT '',
  plan_id varchar(191) NOT NULL DEFAULT '',
  product_id varchar(191) NOT NULL DEFAULT '',
  interval_unit varchar(16) NOT NULL DEFAULT '',
  amount decimal(20,4) NOT NULL DEFAULT 0,
  currency varchar(8) NOT NULL DEFAULT '',
  status varchar(32) NOT NULL DEFAULT 'pending',
  payer_email varchar(191) NOT NULL DEFAULT '',
  uid varchar(191) NOT NULL DEFAULT '',
  ip varchar(64) NOT NULL DEFAULT '',
  form_name varchar(255) NOT NULL DEFAULT '',
  meta longtext NULL,
  created_at datetime NULL DEFAULT NULL,
  updated_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY transaction_id (transaction_id),
  KEY track (track),
  KEY subscription_id (subscription_id),
  KEY form_id (form_id)
) {$charset};";
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
			dbDelta( $sql );
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
		}

		/** Keep only known columns and build the matching formats. */
		private static function prepare_row( array $data ) {
			$row     = array();
			$formats = array();
			foreach ( self::COLUMNS as $col => $fmt ) {
				if ( ! array_key_exists( $col, $data ) ) {
					continue;
				}
				$v = $data[ $col ];
				if ( 'meta' === $col && ! is_string( $v ) ) {
					$v = wp_json_encode( $v );
				}
				if ( '%d' === $fmt ) {
					$v = (int) $v;
				} elseif ( '%f' === $fmt ) {
					$v = (float) $v;
				} else {
					$v = sanitize_text_field( (string) $v );
					if ( 'currency' === $col ) {
						$v = strtoupper( $v );
					}
				}
				$row[ $col ] = $v;
				$formats[]   = $fmt;
			}
			return array( $row, $formats );
		}

		/**
		 * @param array $data Columns (see COLUMNS).
		 * @return int|false Insert id.
		 */
		public static function insert_payment( $data ) {
			global $wpdb;
			if ( ! is_array( $data ) ) {
				return false;
			}
			self::maybe_install_table();
			if ( ! isset( $data['status'] ) ) {
				$data['status'] = 'pending';
			}
			list( $row, $formats ) = self::prepare_row( $data );
			$now               = current_time( 'mysql', true );
			$row['created_at'] = $now;
			$row['updated_at'] = $now;
			$formats[]         = '%s';
			$formats[]         = '%s';
			$ok = $wpdb->insert( self::table_name(), $row, $formats );
			return $ok ? (int) $wpdb->insert_id : false;
		}

		/**
		 * @param int   $id   Row id.
		 * @param array $data Columns to change.
		 * @return int|false Rows updated.
		 */
		public static function update_payment( $id, $data ) {
			global $wpdb;
			$id = (int) $id;
			if ( $id <= 0 || ! is_array( $data ) ) {
				return false;
			}
			self::maybe_install_table();
			list( $row, $formats ) = self::prepare_row( $data );
			if ( empty( $row ) ) {
				return 0;
			}
			$row['updated_at'] = current_time( 'mysql', true );
			$formats[]         = '%s';
			return $wpdb->update( self::table_name(), $row, array( 'id' => $id ), $formats, array( '%d' ) );
		}

		public static function get_payment( $id ) {
			global $wpdb;
			$t = self::table_name();
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ) );
		}

		public static function get_by_transaction( $transaction_id ) {
			global $wpdb;
			$t = self::table_name();
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE transaction_id = %s AND gateway = 'stripe' ORDER BY id DESC LIMIT 1", (string) $transaction_id ) );
		}

		public static function get_by_track( $track ) {
			global $wpdb;
			$t = self::table_name();
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE track = %s AND gateway = 'stripe' ORDER BY id DESC LIMIT 1", (string) $track ) );
		}

		public static function get_by_subscription( $subscription_id ) {
			global $wpdb;
			$t = self::table_name();
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE subscription_id = %s AND gateway = 'stripe' ORDER BY id DESC LIMIT 1", (string) $subscription_id ) );
		}

		public static function webhook_secret() {
			if ( defined( 'EMSFB_STRIPE_WEBHOOK_SECRET' ) && EMSFB_STRIPE_WEBHOOK_SECRET ) {
				return (string) EMSFB_STRIPE_WEBHOOK_SECRET;
			}
			return (string) apply_filters( 'efb_stripe_webhook_secret', (string) get_option( self::SECRET_OPTION, '' ) );
		}

		/* ------------------------------------------------------------------
		 * Admin page
		 * ------------------------------------------------------------------ */

		public function register_admin_page() {
			add_submenu_page(
				'Emsfb',
				esc_html__( 'Stripe Payments', 'flx-forms' ),
				esc_html__( 'Stripe Payments', 'flx-forms' ),
				'manage_options',
				self::PAGE_SLUG,
				array( $this, 'render_admin_page' )
			);
		}

		public function handle_save_settings() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to do this.', 'flx-forms' ), 403 );
			}
			check_admin_referer( 'emsfb_stripe_save' );
			$secret = isset( $_POST['emsfb_stripe_webhook_secret'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['emsfb_stripe_webhook_secret'] ) ) ) : '';
			$clear  = ! empty( $_POST['emsfb_stripe_webhook_clear'] );
			if ( $clear ) {
				delete_option( self::SECRET_OPTION );
			} elseif ( '' !== $secret ) {
				if ( 0 !== strpos( $secret, 'whsec_' ) ) {
					wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'efb_msg' => 'invalid' ), admin_url( 'admin.php' ) ) );
					exit;
				}
				update_option( self::SECRET_OPTION, $secret, false );
			}
			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'efb_msg' => 'saved' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		public function render_admin_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			global $wpdb;
			self::maybe_install_table();
			$t      = self::table_name();
			$rows   = $wpdb->get_results( "SELECT * FROM {$t} WHERE gateway = 'stripe' ORDER BY id DESC LIMIT 50" );
			$hook   = rest_url( 'Emsfb/v1/forms/payment/stripe/webhook' );
			$has    = '' !== self::webhook_secret();
			$msg    = isset( $_GET['efb_msg'] ) ? sanitize_key( wp_unslash( $_GET['efb_msg'] ) ) : '';
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Stripe Payments', 'flx-forms' ); ?></h1>
				<?php if ( 'saved' === $msg ) : ?>
					<div class="notice notice-success"><p><?php esc_html_e( 'Settings saved.', 'flx-forms' ); ?></p></div>
				<?php elseif ( 'invalid' === $msg ) : ?>
					<div class="notice notice-error"><p><?php esc_html_e( 'A Stripe webhook signing secret starts with "whsec_".', 'flx-forms' ); ?></p></div>
				<?php endif; ?>
				<p><?php esc_html_e( 'The publishable and secret API keys are set on the plugin Settings page, Payment tab.', 'flx-forms' ); ?></p>
				<h2><?php esc_html_e( 'Webhook', 'flx-forms' ); ?></h2>
				<p><?php esc_html_e( 'Optional but recommended: in the Stripe Dashboard add a webhook endpoint with this URL and the events payment_intent.succeeded, payment_intent.payment_failed, payment_intent.canceled, charge.refunded, customer.subscription.updated and customer.subscription.deleted, then paste its signing secret below.', 'flx-forms' ); ?></p>
				<p><code><?php echo esc_html( $hook ); ?></code></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="emsfb_stripe_save">
					<?php wp_nonce_field( 'emsfb_stripe_save' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="emsfb_stripe_webhook_secret"><?php esc_html_e( 'Webhook signing secret', 'flx-forms' ); ?></label></th>
							<td>
								<input type="password" class="regular-text" autocomplete="off" id="emsfb_stripe_webhook_secret" name="emsfb_stripe_webhook_secret" value="" placeholder="<?php echo esc_attr( $has ? __( 'Saved - leave empty to keep it', 'flx-forms' ) : 'whsec_...' ); ?>">
								<?php if ( $has ) : ?>
									<label><input type="checkbox" name="emsfb_stripe_webhook_clear" value="1"> <?php esc_html_e( 'Remove the saved secret', 'flx-forms' ); ?></label>
								<?php endif; ?>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Save', 'flx-forms' ) ); ?>
				</form>
				<h2><?php esc_html_e( 'Recent payments', 'flx-forms' ); ?></h2>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Date (UTC)', 'flx-forms' ); ?></th>
						<th><?php esc_html_e( 'Form', 'flx-forms' ); ?></th>
						<th><?php esc_html_e( 'Confirmation code', 'flx-forms' ); ?></th>
						<th><?php esc_html_e( 'Type', 'flx-forms' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'flx-forms' ); ?></th>
						<th><?php esc_html_e( 'Status', 'flx-forms' ); ?></th>
						<th><?php esc_html_e( 'Stripe ID', 'flx-forms' ); ?></th>
						<th><?php esc_html_e( 'Email', 'flx-forms' ); ?></th>
					</tr></thead>
					<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="8"><?php esc_html_e( 'No payments yet.', 'flx-forms' ); ?></td></tr>
					<?php else : foreach ( $rows as $r ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $r->created_at ); ?></td>
							<td><?php echo esc_html( $r->form_name . ' (#' . (int) $r->form_id . ')' ); ?></td>
							<td><?php echo esc_html( $r->track ); ?></td>
							<td><?php echo esc_html( $r->payment_type . ( $r->interval_unit ? ' / ' . strtolower( $r->interval_unit ) : '' ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (float) $r->amount, StripeApiEfb::currency_decimals( $r->currency ) ) . ' ' . $r->currency ); ?></td>
							<td><?php echo esc_html( $r->status ); ?></td>
							<td><code><?php echo esc_html( $r->subscription_id ? $r->subscription_id : $r->transaction_id ); ?></code></td>
							<td><?php echo esc_html( $r->payer_email ); ?></td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
			<?php
		}
	}
}
