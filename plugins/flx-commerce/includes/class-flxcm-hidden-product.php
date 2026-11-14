<?php
/**
 * Gizli ürün: mağaza, arama, site haritası ve ürün sayfası müşterilere kapalı; satın alma bağlantıyla
 * (?add-to-cart=ID, flxcm_add_subscription_to_cart) ya da başka bir eklentinin fiyat sayfasından yapılır.
 * Ürün sayfasını yalnız ürünü düzenleyebilenler görür (tanıtım görseli, açıklama yerinde kalır).
 *
 * Ürün meta: _flxcm_hidden (yes). Ayar: hidden_redirect (boş = ana sayfa).
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Hidden_Product {

	public static function init() {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'field' ), 5 );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
		add_action( 'template_redirect', array( __CLASS__, 'guard' ), 5 );
		add_action( 'woocommerce_before_single_product', array( __CLASS__, 'admin_note' ) );
		add_filter( 'woocommerce_cart_item_permalink', array( __CLASS__, 'no_link' ), 20, 2 );
		add_filter( 'woocommerce_order_item_permalink', array( __CLASS__, 'no_order_link' ), 20, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap' ), 10, 2 );
		add_filter( 'woocommerce_product_is_visible', array( __CLASS__, 'visible' ), 20, 2 );
	}

	public static function is_hidden( $product ) {
		$product = $product instanceof WC_Product ? $product : wc_get_product( $product );
		if ( ! $product ) {
			return false;
		}
		$id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
		return 'yes' === get_post_meta( $id, '_flxcm_hidden', true );
	}

	public static function field() {
		global $product_object;
		$p = $product_object instanceof WC_Product ? $product_object : null;
		echo '<div class="options_group flxcm-hidden-field">';
		woocommerce_wp_checkbox(
			array(
				'id'          => '_flxcm_hidden',
				'label'       => __( 'Hidden product', 'flx-commerce' ),
				'description' => __( 'Not shown in the shop, search or sitemap; customers cannot open the product page. Sold only through a link (e.g. a pricing page). Only editors see the product page.', 'flx-commerce' ),
				'value'       => $p ? $p->get_meta( '_flxcm_hidden' ) : '',
			)
		);
		echo '</div>';
	}

	public static function save( WC_Product $product ) {
		// phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce ürün kaydı kendi nonce'unu doğrular.
		$on  = ! empty( $_POST['_flxcm_hidden'] );
		$was = 'yes' === $product->get_meta( '_flxcm_hidden' );
		$product->update_meta_data( '_flxcm_hidden', $on ? 'yes' : 'no' );
		if ( $on ) {
			$product->set_catalog_visibility( 'hidden' );
		} elseif ( $was && 'hidden' === $product->get_catalog_visibility() ) {
			$product->set_catalog_visibility( 'visible' );
		}
	}

	public static function can_see( $product_id ) {
		return current_user_can( 'edit_post', (int) $product_id );
	}

	/** Ürün sayfası müşteriye kapalı: ayardaki sayfaya (yoksa ana sayfaya) gider. */
	public static function guard() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$id = (int) get_queried_object_id();
		if ( ! $id || ! self::is_hidden( $id ) || self::can_see( $id ) ) {
			return;
		}
		$to = (string) flxcm_setting( 'hidden_redirect' );
		$to = apply_filters( 'flxcm_hidden_product_redirect', $to ? $to : home_url( '/' ), $id );
		$to = wp_validate_redirect( (string) $to, home_url( '/' ) );
		nocache_headers();
		wp_safe_redirect( $to, 302 );
		exit;
	}

	public static function admin_note() {
		global $product;
		if ( ! $product instanceof WC_Product || ! self::is_hidden( $product ) ) {
			return;
		}
		wp_enqueue_style( 'flxcm-front', FLXCM_URL . 'assets/front.css', array(), FLXCM_VERSION );
		echo '<p class="flxcm-hidden-note">' . esc_html__( 'Hidden product: only editors see this page. Customers buy it through a link.', 'flx-commerce' ) . '</p>';
	}

	public static function no_link( $link, $item ) {
		return ! empty( $item['product_id'] ) && self::is_hidden( (int) $item['product_id'] ) ? '' : $link;
	}

	public static function no_order_link( $link, $item ) {
		return is_callable( array( $item, 'get_product_id' ) ) && self::is_hidden( (int) $item->get_product_id() ) ? '' : $link;
	}

	public static function sitemap( $args, $post_type ) {
		if ( 'product' !== $post_type ) {
			return $args;
		}
		$args['meta_query']   = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
		$args['meta_query'][] = array(
			'relation' => 'OR',
			array( 'key' => '_flxcm_hidden', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_flxcm_hidden', 'value' => 'yes', 'compare' => '!=' ),
		);
		return $args;
	}

	/** İlgili ürünler, kısa kodlar vb. katalog listelerinde de görünmez. */
	public static function visible( $visible, $product_id ) {
		return $visible && ! self::is_hidden( (int) $product_id );
	}
}
