<?php
/**
 * Dekont yükleme (Havale/EFT). Müşteri (üye ya da misafir) sipariş anahtarıyla yükler: teşekkür sayfası,
 * Hesabım › sipariş ve e-postadaki bağlantı. Dosyalar uploads/flxcm-dekont/ altında, doğrudan erişime
 * kapalı; yalnız yönetici ve siparişin sahibi açar (?flxcm_dekont=<sipariş>&i=<n>).
 * REST: POST flx-commerce/v1/receipt (order, key, dekont). Flux Donate'in dekont katmanından genelleştirildi.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Receipts {

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
		$dir = trailingslashit( $up['basedir'] ) . 'flxcm-dekont';
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
		$r = $order->get_meta( '_flxcm_receipts' );
		return is_array( $r ) ? array_values( $r ) : array();
	}

	/** Müşteri (sipariş anahtarı) ya da yönetici (nonce) bağlantısı. */
	public static function url( WC_Order $order, $index, $admin = false ) {
		$args = array( 'flxcm_dekont' => $order->get_id(), 'i' => (int) $index );
		if ( $admin ) {
			$args['_wpnonce'] = wp_create_nonce( 'flxcm_dekont_' . $order->get_id() );
		} else {
			$args['key'] = $order->get_order_key();
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Dekontu kaydeder.
	 *
	 * @param array $file $_FILES öğesi.
	 * @return array|WP_Error
	 */
	public static function store( WC_Order $order, array $file ) {
		if ( ! FLXCM_Bank::awaiting( $order ) ) {
			return new WP_Error( 'flxcm_state', __( 'Bu sipariş için dekont beklenmiyor.', 'flx-commerce' ), array( 'status' => 409 ) );
		}
		$list = self::of( $order );
		if ( count( $list ) >= self::MAX ) {
			return new WP_Error( 'flxcm_limit', __( 'Bir sipariş için en fazla 5 dekont yüklenebilir.', 'flx-commerce' ), array( 'status' => 429 ) );
		}
		if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$big = isset( $file['error'] ) && in_array( (int) $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true );
			return new WP_Error( 'flxcm_file', $big ? __( 'Dosya çok büyük.', 'flx-commerce' ) : __( 'Dosya yüklenemedi. Lütfen tekrar deneyin.', 'flx-commerce' ), array( 'status' => 400 ) );
		}
		$max = max( 1, (int) flxcm_setting( 'receipt_max_mb' ) ) * MB_IN_BYTES;
		if ( (int) $file['size'] > $max ) {
			/* translators: %s: largest size in MB */
			return new WP_Error( 'flxcm_size', sprintf( __( 'Dosya en fazla %s MB olabilir.', 'flx-commerce' ), number_format_i18n( $max / MB_IN_BYTES ) ), array( 'status' => 413 ) );
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], self::mimes() );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			return new WP_Error( 'flxcm_type', __( 'Yalnız PDF, JPG, PNG, WEBP ya da HEIC yüklenebilir.', 'flx-commerce' ), array( 'status' => 415 ) );
		}
		$dir = self::dir();
		$sub = gmdate( 'Y/m' );
		wp_mkdir_p( $dir . '/' . $sub );
		$name = $order->get_id() . '-' . wp_generate_password( 16, false, false ) . '.' . $check['ext'];
		$dest = $dir . '/' . $sub . '/' . $name;
		if ( ! @move_uploaded_file( $file['tmp_name'], $dest ) ) { // phpcs:ignore
			return new WP_Error( 'flxcm_move', __( 'Dosya kaydedilemedi.', 'flx-commerce' ), array( 'status' => 500 ) );
		}
		@chmod( $dest, 0640 ); // phpcs:ignore
		$orig   = sanitize_file_name( wp_basename( (string) $file['name'] ) );
		$rec    = array(
			'file' => $sub . '/' . $name,
			'name' => '' !== $orig ? $orig : $name,
			'type' => $check['type'],
			'size' => (int) $file['size'],
			'at'   => time(),
			'by'   => get_current_user_id(),
		);
		$list[] = $rec;
		$order->update_meta_data( '_flxcm_receipts', $list );
		$order->save();
		/* translators: 1: file name, 2: size */
		$order->add_order_note( sprintf( __( 'Müşteri dekont yükledi: %1$s (%2$s). Ödemeyi kontrol edip onaylayın.', 'flx-commerce' ), $rec['name'], size_format( $rec['size'] ) ) );
		do_action( 'flxcm_receipt_uploaded', $order, $rec );
		return $rec;
	}

	/** Yükleme kutusu (JavaScript ile REST, olmadan düz form). */
	public static function uploader( WC_Order $order ) {
		$list = self::of( $order );
		$full = count( $list ) >= self::MAX;
		$max  = (int) flxcm_setting( 'receipt_max_mb' );
		ob_start();
		?>
<div class="flxcm-upload" data-flxcm-upload data-order="<?php echo esc_attr( $order->get_id() ); ?>" data-key="<?php echo esc_attr( $order->get_order_key() ); ?>">
	<ul class="flxcm-files" data-flxcm-files<?php echo $list ? '' : ' hidden'; ?>>
		<?php foreach ( $list as $i => $r ) : ?>
		<li><?php echo flxcm_icon( 'file', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><a href="<?php echo esc_url( self::url( $order, $i ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $r['name'] ); ?></a>
			<small><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $r['at'] ) ); ?></small></li>
		<?php endforeach; ?>
	</ul>
	<p class="flxcm-note flxcm-note--ok" data-flxcm-ok<?php echo $list ? '' : ' hidden'; ?>><?php echo flxcm_icon( 'check', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Dekontunuz bize ulaştı. Ödeme onaylanınca size haber vereceğiz.', 'flx-commerce' ); ?></span></p>
	<?php if ( ! $full ) : ?>
	<form class="flxcm-drop" method="post" enctype="multipart/form-data" action="">
		<input type="hidden" name="flxcm_action" value="receipt">
		<input type="hidden" name="order" value="<?php echo esc_attr( $order->get_id() ); ?>">
		<input type="hidden" name="key" value="<?php echo esc_attr( $order->get_order_key() ); ?>">
		<label class="flxcm-drop__zone">
			<input type="file" name="dekont" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,application/pdf,image/*" required data-flxcm-file>
			<span class="flxcm-drop__icon"><?php echo flxcm_icon( 'upload', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
			<span class="flxcm-drop__text"><strong data-flxcm-pick><?php echo $list ? esc_html__( 'Bir dekont daha ekle', 'flx-commerce' ) : esc_html__( 'Dekontu seçin ya da buraya bırakın', 'flx-commerce' ); ?></strong>
			<small><?php /* translators: %s: MB */ echo esc_html( sprintf( __( 'PDF, JPG ya da PNG · en fazla %s MB', 'flx-commerce' ), number_format_i18n( $max ) ) ); ?></small></span>
		</label>
		<button type="submit" class="flxcm-btn" data-flxcm-send><?php echo flxcm_icon( 'upload', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php esc_html_e( 'Dekontu gönder', 'flx-commerce' ); ?></span></button>
		<p class="flxcm-note flxcm-note--err" data-flxcm-err hidden role="alert"></p>
	</form>
	<?php endif; ?>
</div>
		<?php
		return (string) ob_get_clean();
	}

	public static function order_by_key( $order_id, $key ) {
		$order = wc_get_order( absint( $order_id ) );
		if ( ! $order || ! is_string( $key ) || '' === $key || ! hash_equals( $order->get_order_key(), $key ) ) {
			return null;
		}
		return $order;
	}

	public static function routes() {
		register_rest_route(
			'flx-commerce/v1',
			'/receipt',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Sipariş anahtarı yetkidir (önbellekli sayfalarda da çalışır).
				'callback'            => array( __CLASS__, 'rest_upload' ),
			)
		);
	}

	public static function rest_upload( WP_REST_Request $r ) {
		$order = self::order_by_key( $r->get_param( 'order' ), (string) $r->get_param( 'key' ) );
		if ( ! $order ) {
			return new WP_Error( 'flxcm_order', __( 'Sipariş bulunamadı.', 'flx-commerce' ), array( 'status' => 404 ) );
		}
		$files = $r->get_file_params();
		if ( empty( $files['dekont'] ) ) {
			return new WP_Error( 'flxcm_file', __( 'Lütfen bir dosya seçin.', 'flx-commerce' ), array( 'status' => 400 ) );
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
			'date'  => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'count' => count( $list ),
			'full'  => count( $list ) >= self::MAX,
		);
	}

	public static function form_post() {
		if ( empty( $_POST['flxcm_action'] ) || 'receipt' !== $_POST['flxcm_action'] || is_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification
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
		wc_add_notice( __( 'Dekontunuz bize ulaştı. Teşekkürler!', 'flx-commerce' ) );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : $order->get_checkout_order_received_url() );
		exit;
	}

	/* Dekontu açar: yönetici (nonce + yetki) ya da siparişin sahibi (anahtar). */
	public static function serve() {
		if ( empty( $_GET['flxcm_dekont'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$oid   = absint( $_GET['flxcm_dekont'] ); // phpcs:ignore
		$i     = absint( $_GET['i'] ?? 0 ); // phpcs:ignore
		$order = wc_get_order( $oid );
		$ok    = false;
		if ( $order ) {
			if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'flxcm_dekont_' . $oid ) && current_user_can( 'edit_shop_orders' ) ) {
				$ok = true;
			} elseif ( isset( $_GET['key'] ) && hash_equals( $order->get_order_key(), (string) wp_unslash( $_GET['key'] ) ) ) { // phpcs:ignore
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
