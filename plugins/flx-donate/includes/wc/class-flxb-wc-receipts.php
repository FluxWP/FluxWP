<?php
/**
 * Flux Donate 2.0: bank receipt upload.
 *
 * The donor (logged in or guest) uploads with the order key: on the thank-you page, in "My pending
 * donations" and from the link in the e-mail. Files live in uploads/flxb-dekont/, closed to direct
 * access; only an admin and the order's owner can open them (?flxb_dekont=<order>&i=<n>).
 * REST: POST flxb-wc/v1/receipt (order, key, dekont), kept for pages cached with the old address.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_WC_Receipts {

	const MAX = 5;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'form_post' ), 26 );
		add_action( 'init', array( __CLASS__, 'serve' ), 5 );
	}

	public static function mimes() {
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
			'heic'         => 'image/heic',
			'pdf'          => 'application/pdf',
		);
	}

	public static function dir() {
		$up  = wp_upload_dir( null, false );
		$dir = trailingslashit( $up['basedir'] ) . 'flxb-dekont';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			@file_put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
		}
		return $dir;
	}

	public static function of( WC_Order $order ) {
		$r = $order->get_meta( '_flxb_receipts' );
		return is_array( $r ) ? array_values( $r ) : array();
	}

	/** Link for the donor (with the order key) or for the admin (nonce). */
	public static function url( WC_Order $order, $index, $admin = false ) {
		$args = array( 'flxb_dekont' => $order->get_id(), 'i' => (int) $index );
		if ( $admin ) {
			$args['_wpnonce'] = wp_create_nonce( 'flxb_dekont_' . $order->get_id() );
		} else {
			$args['key'] = $order->get_order_key();
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/** 2.8.0: short-lived signed address (the mobile app opens it without a browser session). */
	public static function signed_url( WC_Order $order, $index, $ttl = 900 ) {
		$exp = time() + (int) $ttl;
		return add_query_arg( array( 'flxb_dekont' => $order->get_id(), 'i' => (int) $index, 'exp' => $exp, 'sig' => self::sig( $order->get_id(), (int) $index, $exp ) ), home_url( '/' ) );
	}

	private static function sig( $oid, $i, $exp ) {
		return hash_hmac( 'sha256', 'flxb_dekont|' . (int) $oid . '|' . (int) $i . '|' . (int) $exp, wp_salt( 'auth' ) );
	}

	/**
	 * Store a receipt.
	 *
	 * @param array $file $_FILES entry.
	 * @return array|WP_Error
	 */
	public static function store( WC_Order $order, array $file ) {
		if ( ! FLXB_WC_Havale::awaiting( $order ) ) {
			return new WP_Error( 'flxb_state', __( '\\nNo receipt is expected for this donation.\\n', 'flx-donate' ), array( 'status' => 409 ) );
		}
		$list = self::of( $order );
		if ( count( $list ) >= self::MAX ) {
			return new WP_Error( 'flxb_limit', __( '\\nAt most 5 receipts can be uploaded for a donation.\\n', 'flx-donate' ), array( 'status' => 429 ) );
		}
		if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$big = isset( $file['error'] ) && in_array( (int) $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true );
			return new WP_Error( 'flxb_file', $big ? __( 'The file is too large.', 'flx-donate' ) : __( 'The file could not be uploaded. Please try again.', 'flx-donate' ), array( 'status' => 400 ) );
		}
		$max = max( 1, (int) FLXB_WC::setting( 'receipt_max_mb' ) ) * MB_IN_BYTES;
		if ( (int) $file['size'] > $max ) {
			/* translators: %s: largest size in MB */
			return new WP_Error( 'flxb_size', sprintf( __( 'The file can be at most %s MB.', 'flx-donate' ), number_format_i18n( $max / MB_IN_BYTES ) ), array( 'status' => 413 ) );
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], self::mimes() );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			return new WP_Error( 'flxb_type', __( 'Only PDF, JPG, PNG, WEBP or HEIC files can be uploaded.', 'flx-donate' ), array( 'status' => 415 ) );
		}
		$dir = self::dir();
		$sub = gmdate( 'Y/m' );
		wp_mkdir_p( $dir . '/' . $sub );
		$name = $order->get_id() . '-' . wp_generate_password( 16, false, false ) . '.' . $check['ext'];
		$dest = $dir . '/' . $sub . '/' . $name;
		if ( ! @move_uploaded_file( $file['tmp_name'], $dest ) ) { // phpcs:ignore
			return new WP_Error( 'flxb_move', __( 'The file could not be saved.', 'flx-donate' ), array( 'status' => 500 ) );
		}
		@chmod( $dest, 0640 ); // phpcs:ignore
		$orig = sanitize_file_name( wp_basename( (string) $file['name'] ) );
		$rec  = array(
			'file' => $sub . '/' . $name,
			'name' => '' !== $orig ? $orig : $name,
			'type' => $check['type'],
			'size' => (int) $file['size'],
			'at'   => time(),
			'by'   => get_current_user_id(),
			'ip'   => class_exists( 'WC_Geolocation' ) ? WC_Geolocation::get_ip_address() : '',
		);
		$rec    = self::ledger( $order, $rec, $dest );
		$list[] = $rec;
		$order->update_meta_data( '_flxb_receipts', $list );
		$order->update_meta_data( '_flxb_state', 'uploaded' );
		$order->save();
		/* translators: 1: file name, 2: size */
		$order->add_order_note( sprintf( __( '\\nThe donor uploaded a receipt:%1$s(%2$s). Check the payment and confirm it.\\n', 'flx-donate' ), $rec['name'], size_format( $rec['size'] ) ) );
		if ( ! empty( $rec['dup'] ) ) {
			$order->add_order_note( self::dup_text( $rec ) );
		}
		self::notify_admin( $order, $rec );
		do_action( 'flxb_wc_receipt_uploaded', $order, $rec );
		return $rec;
	}

	/**
	 * 2.3.2: write the receipt to the shared receipt ledger (Flux Chat 1.15+, filter
	 * flx_dekont_register) so the same file sent again from the site or WhatsApp is caught. Without
	 * the ledger the filter returns null and nothing changes.
	 */
	private static function ledger( WC_Order $order, array $rec, $path ) {
		global $wpdb;
		$acc = function_exists( 'flxb_bank_account' ) ? flxb_bank_account() : null;
		$did = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxb_table( 'donations' ) . ' WHERE wc_order_id = %d ORDER BY id ASC LIMIT 1', $order->get_id() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$r   = apply_filters(
			'flx_dekont_register',
			null,
			array(
				'payer'       => trim( $order->get_formatted_billing_full_name() ),
				'dekont_no'   => '',
				'amount'      => (float) $order->get_total(),
				'currency'    => $order->get_currency(),
				'date'        => wp_date( 'd.m.Y', $rec['at'] ),
				'recipient'   => $acc ? $acc['holder'] : '',
				'iban'        => $acc ? $acc['iban'] : '',
				'source'      => 'site',
				'donation_id' => $did,
				'order_id'    => $order->get_id(),
				'file'        => $path,
			)
		);
		if ( ! is_array( $r ) ) {
			return $rec;
		}
		$rec['ledger'] = (int) ( $r['id'] ?? 0 );
		// The same donor sending the same file again for this donation is not a warning.
		$dups = array_values(
			array_filter(
				(array) ( $r['duplicates'] ?? array() ),
				function ( $x ) use ( $did ) {
					return is_array( $x ) && ! ( $did && 'site' === ( $x['source'] ?? '' ) && (int) ( $x['donation_id'] ?? 0 ) === $did );
				}
			)
		);
		if ( $dups ) {
			$d          = $dups[0];
			$rec['dup'] = array(
				'count'       => count( $dups ),
				'source'      => sanitize_key( (string) ( $d['source'] ?? '' ) ),
				'donation_id' => (int) ( $d['donation_id'] ?? 0 ),
				'payer'       => sanitize_text_field( (string) ( $d['payer'] ?? '' ) ),
				'created_at'  => (int) ( $d['created_at'] ?? 0 ),
			);
			$order->update_meta_data( '_flxb_dekont_dup', 1 );
		}
		return $rec;
	}

	/** Warning for a receipt the ledger has seen before. */
	public static function dup_text( array $rec ) {
		$d    = (array) ( $rec['dup'] ?? array() );
		$from = 'whatsapp' === ( $d['source'] ?? '' ) ? __( 'WhatsApp', 'flx-donate' ) : __( '\\nthe site\\n', 'flx-donate' );
		$text = sprintf(
			/* translators: 1: number of earlier uploads, 2: where (the site / WhatsApp) */
			_n( '[[\'Warning: this receipt was uploaded before (%1$dtime, via%2$s). Check it before confirming the payment.\', \'en\']]', '[[\'Warning: this receipt was uploaded before (%1$dtimes, via%2$s). Check it before confirming the payment.\', \'en\']]', (int) ( $d['count'] ?? 1 ), 'flx-donate' ),
			(int) ( $d['count'] ?? 1 ),
			$from
		);
		if ( ! empty( $d['donation_id'] ) ) {
			/* translators: %d: donation number */
			$text .= ' ' . sprintf( __( '\\nEarlier donation: #%d.\\n', 'flx-donate' ), (int) $d['donation_id'] );
		}
		return $text;
	}

	private static function notify_admin( WC_Order $order, array $rec ) {
		$to = trim( (string) FLXB_WC::setting( 'receipt_notify' ) );
		$to = is_email( $to ) ? $to : get_option( 'admin_email' );
		FLXB_Mail::send(
			'flx-donate/receipt-uploaded',
			$to,
			array(
				'siparis'  => $order->get_order_number(),
				'ad'       => $order->get_formatted_billing_full_name(),
				'tutar'    => FLXB_WC::money_text( $order->get_total(), $order->get_currency() ),
				'aciklama' => FLXB_WC_Havale::reference( $order ),
				'dosya'    => $rec['name'],
				'link'     => $order->get_edit_order_url(),
			)
		);
	}

	/** Upload box (REST with JavaScript, plain form POST without). */
	public static function uploader( WC_Order $order ) {
		$list = self::of( $order );
		$full = count( $list ) >= self::MAX;
		$max  = (int) FLXB_WC::setting( 'receipt_max_mb' );
		ob_start();
		?>
<div class="flxwb-upload" data-flxwb-upload data-order="<?php echo esc_attr( $order->get_id() ); ?>" data-key="<?php echo esc_attr( $order->get_order_key() ); ?>">
	<ul class="flxwb-files" data-flxwb-files<?php echo $list ? '' : ' hidden'; ?>>
		<?php foreach ( $list as $i => $r ) : ?>
		<li><?php echo FLXB_WC::icon( 'file' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><a href="<?php echo esc_url( self::url( $order, $i ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $r['name'] ); ?></a>
			<small><?php echo esc_html( flx_date( (int) $r['at'], 'datetime' ) ); ?></small></li>
		<?php endforeach; ?>
	</ul>
	<?php if ( $list ) : ?>
	<p class="flxwb-note flxwb-note--ok" data-flxwb-upload-ok><?php echo FLXB_WC::icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'We have your receipt. We will let you know once the payment is confirmed.', 'flx-donate' ); ?></span></p>
	<?php endif; ?>
	<?php if ( ! $full ) : ?>
	<form class="flxwb-drop" method="post" enctype="multipart/form-data" action="">
		<input type="hidden" name="flxb_action" value="receipt">
		<input type="hidden" name="order" value="<?php echo esc_attr( $order->get_id() ); ?>">
		<input type="hidden" name="key" value="<?php echo esc_attr( $order->get_order_key() ); ?>">
		<label class="flxwb-drop__zone">
			<input type="file" name="dekont" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,application/pdf,image/*" required data-flxwb-file>
			<span class="flxwb-drop__icon"><?php echo FLXB_WC::icon( 'upload' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<span class="flxwb-drop__text"><strong><?php echo $list ? esc_html__( 'Add another receipt', 'flx-donate' ) : esc_html__( 'Choose a receipt or drop it here', 'flx-donate' ); ?></strong>
			<small><?php /* translators: %s: MB */ echo esc_html( sprintf( __( 'PDF, JPG or PNG · up to %s MB', 'flx-donate' ), number_format_i18n( $max ) ) ); ?></small></span>
		</label>
		<button type="submit" class="flxwb-btn flxwb-btn--primary flxwb-drop__btn" data-flxwb-upload-btn><?php echo FLXB_WC::icon( 'upload' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Send receipt', 'flx-donate' ); ?></span></button>
		<p class="flxwb-note flxwb-note--err" data-flxwb-upload-err hidden role="alert"></p>
	</form>
	<?php endif; ?>
</div>
		<?php
		return ob_get_clean();
	}

	/** Order by its key (guests too). */
	public static function order_by_key( $order_id, $key ) {
		$order = wc_get_order( absint( $order_id ) );
		if ( ! $order || ! is_string( $key ) || '' === $key || ! hash_equals( $order->get_order_key(), $key ) ) {
			return null;
		}
		return $order;
	}

	public static function routes() {
		register_rest_route(
			'flxb-wc/v1',
			'/receipt',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // the order key is the permission (works on cached pages)
				'callback'            => array( __CLASS__, 'rest_upload' ),
			)
		);
	}

	public static function rest_upload( WP_REST_Request $r ) {
		$order = self::order_by_key( $r->get_param( 'order' ), (string) $r->get_param( 'key' ) );
		if ( ! $order ) {
			return new WP_Error( 'flxb_order', __( 'Donation not found.', 'flx-donate' ), array( 'status' => 404 ) );
		}
		$files = $r->get_file_params();
		if ( empty( $files['dekont'] ) ) {
			return new WP_Error( 'flxb_file', __( 'Please choose a file.', 'flx-donate' ), array( 'status' => 400 ) );
		}
		$rec = self::store( $order, $files['dekont'] );
		if ( is_wp_error( $rec ) ) {
			return $rec;
		}
		$list = self::of( $order );
		return array(
			'ok'    => true,
			'name'  => $rec['name'],
			'url'   => self::url( $order, count( $list ) - 1 ),
			'date'  => flx_date( time(), 'datetime' ),
			'count' => count( $list ),
			'full'  => count( $list ) >= self::MAX,
		);
	}

	/* Upload without JavaScript. */
	public static function form_post() {
		if ( empty( $_POST['flxb_action'] ) || 'receipt' !== $_POST['flxb_action'] || is_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$order = self::order_by_key( $_POST['order'] ?? 0, (string) wp_unslash( $_POST['key'] ?? '' ) ); // phpcs:ignore
		if ( ! $order || empty( $_FILES['dekont'] ) ) {
			return;
		}
		$rec = self::store( $order, $_FILES['dekont'] ); // phpcs:ignore
		if ( is_wp_error( $rec ) ) {
			wc_add_notice( $rec->get_error_message(), 'error' );
			return;
		}
		wc_add_notice( __( 'We have your receipt. Thank you!', 'flx-donate' ) );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : $order->get_checkout_order_received_url() );
		exit;
	}

	/* Serve a receipt: admin (nonce + capability), the order's owner (key) or a signed address (app). */
	public static function serve() {
		if ( empty( $_GET['flxb_dekont'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$oid   = absint( $_GET['flxb_dekont'] ); // phpcs:ignore
		$i     = absint( $_GET['i'] ?? 0 ); // phpcs:ignore
		$order = wc_get_order( $oid );
		$ok    = false;
		if ( $order ) {
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'flxb_dekont_' . $oid ) && current_user_can( 'edit_shop_orders' ) ) {
				$ok = true;
			} elseif ( isset( $_GET['key'] ) && hash_equals( $order->get_order_key(), (string) wp_unslash( $_GET['key'] ) ) ) { // phpcs:ignore
				$ok = true;
			} elseif ( isset( $_GET['sig'], $_GET['exp'] ) && absint( $_GET['exp'] ) >= time() && hash_equals( self::sig( $oid, $i, absint( $_GET['exp'] ) ), (string) wp_unslash( $_GET['sig'] ) ) ) { // phpcs:ignore
				$ok = true;
			}
		}
		$list = $order && $ok ? self::of( $order ) : array();
		if ( ! isset( $list[ $i ] ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
		$path = self::dir() . '/' . $list[ $i ]['file'];
		if ( false !== strpos( $list[ $i ]['file'], '..' ) || ! is_readable( $path ) ) {
			status_header( 404 );
			exit;
		}
		nocache_headers();
		header( 'Content-Type: ' . $list[ $i ]['type'] );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: inline; filename="' . rawurlencode( $list[ $i ]['name'] ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex' );
		readfile( $path ); // phpcs:ignore
		exit;
	}
}
