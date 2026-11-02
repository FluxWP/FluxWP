<?php
/**
 * Flux Donate 2.0: one hidden WooCommerce product per donation type.
 *
 * The product only carries the line in the cart and the order; its price is never used (the price
 * comes from the type on every calculation, see FLXB_WC_Cart). It is virtual, tax-free, hidden in
 * the shop, and its page redirects to the donation page.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FLXB_WC_Products {

	const META_TYPE = '_flxb_type_id';

	public static function init() {
		add_action( 'flxb_type_saved', array( __CLASS__, 'on_type_saved' ), 20, 1 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_product_page' ) );
		add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'purchasable' ), 10, 2 );
	}

	/** Write one key into a type's meta without going through the editor's save (no hooks). */
	public static function set_type_meta( $type_id, $key, $value ) {
		global $wpdb;
		$t = FLXB_Types::get( (int) $type_id );
		if ( ! $t ) {
			return;
		}
		$m = (array) $t['meta'];
		if ( null === $value ) {
			unset( $m[ $key ] );
		} else {
			$m[ $key ] = $value;
		}
		$wpdb->update( flxb_table( 'types' ), array( 'meta' => $m ? wp_json_encode( $m ) : null ), array( 'id' => (int) $type_id ) );
	}

	private static function usable( $id ) {
		return $id && 'product' === get_post_type( $id ) && 'trash' !== get_post_status( $id );
	}

	/**
	 * Product of a type; created (or adopted) when missing.
	 *
	 * @param array|int $type   Type row or id.
	 * @param bool      $create Create when missing.
	 * @return int
	 */
	public static function product_id( $type, $create = true ) {
		$t = is_array( $type ) ? $type : FLXB_Types::get( (int) $type );
		if ( ! $t ) {
			return 0;
		}
		$id = (int) FLXB_Types::meta( $t, 'wc_product_id', 0 );
		if ( self::usable( $id ) ) {
			return $id;
		}
		/* A product that already points at this type (e.g. type meta lost). */
		$found = get_posts( array( 'post_type' => 'product', 'post_status' => array( 'publish', 'private', 'draft' ), 'meta_key' => self::META_TYPE, 'meta_value' => (string) (int) $t['id'], 'fields' => 'ids', 'numberposts' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( $found ) {
			self::link( $t, (int) $found[0] );
			return (int) $found[0];
		}
		return $create ? self::create( $t ) : 0;
	}

	private static function link( array $t, $product_id ) {
		self::set_type_meta( (int) $t['id'], 'wc_product_id', (int) $product_id );
		$p = wc_get_product( $product_id );
		if ( $p && (int) $p->get_meta( self::META_TYPE ) !== (int) $t['id'] ) {
			$p->update_meta_data( self::META_TYPE, (int) $t['id'] );
			$p->save();
		}
	}

	private static function create( array $t ) {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return 0;
		}
		$p = new WC_Product_Simple();
		$p->set_name( (string) $t['title'] );
		$p->set_slug( sanitize_title( 'bagis-' . $t['slug'] ) );
		self::apply( $p, $t );
		$id = $p->save();
		self::set_type_meta( (int) $t['id'], 'wc_product_id', (int) $id );
		self::image( $id, $t );
		return (int) $id;
	}

	/** Fields every donation product keeps (hidden, virtual, tax-free). */
	private static function apply( WC_Product $p, array $t ) {
		$p->set_status( 'publish' );
		$p->set_virtual( true );
		$p->set_catalog_visibility( 'hidden' );
		$p->set_tax_status( 'none' );
		$p->set_reviews_allowed( false );
		$p->set_manage_stock( false );
		$p->set_stock_status( 'instock' );
		$p->set_regular_price( (string) self::list_price( $t ) );
		$p->update_meta_data( self::META_TYPE, (int) $t['id'] );
	}

	/** Shop list price (display only): the cheapest option on sale, else the minimum amount. */
	public static function list_price( array $t ) {
		$min = 0.0;
		foreach ( $t['options'] as $o ) {
			if ( FLXB_Types::option_on_sale( $o ) && ( ! $min || (float) $o['price'] < $min ) ) {
				$min = (float) $o['price'];
			}
		}
		if ( ! $min ) {
			$min = max( 1, (float) $t['min_amount'] );
		}
		return round( $min, 2 );
	}

	/** Product image: the type image, else the bundled sapling / donation picture. */
	public static function image( $id, array $t ) {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			return;
		}
		if ( (int) $t['image_id'] ) {
			if ( (int) $p->get_image_id() !== (int) $t['image_id'] ) {
				$p->set_image_id( (int) $t['image_id'] );
				$p->save();
			}
			return;
		}
		if ( $p->get_image_id() ) {
			return;
		}
		$file = 'fidan' === ( $t['options'][0]['unit'] ?? '' ) ? 'fidan-bagisi.jpg' : 'genel-bagis.jpg';
		$src  = FLXB_DIR . 'assets/wc/img/' . $file;
		if ( ! is_readable( $src ) ) {
			return;
		}
		$up = wp_upload_bits( $file, null, (string) file_get_contents( $src ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! empty( $up['error'] ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$att = wp_insert_attachment( array( 'post_mime_type' => 'image/jpeg', 'post_title' => $p->get_name(), 'post_status' => 'inherit' ), $up['file'], $id );
		if ( $att && ! is_wp_error( $att ) ) {
			wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $up['file'] ) );
			$p->set_image_id( $att );
			$p->save();
		}
	}

	/** Keep the product in step with its type (name, list price, image, visibility). */
	public static function sync( $type ) {
		$t = is_array( $type ) ? $type : FLXB_Types::get( (int) $type );
		if ( ! $t || 'archived' === $t['status'] && ! (int) FLXB_Types::meta( $t, 'wc_product_id', 0 ) ) {
			return 0;
		}
		$id = self::product_id( $t );
		$p  = $id ? wc_get_product( $id ) : null;
		if ( ! $p ) {
			return 0;
		}
		$t = FLXB_Types::get( (int) $t['id'] );
		$p->set_name( (string) $t['title'] );
		self::apply( $p, $t );
		$p->save();
		self::image( $id, $t );
		return $id;
	}

	public static function on_type_saved( $id ) {
		self::sync( (int) $id );
	}

	public static function sync_all() {
		$n = 0;
		foreach ( FLXB_Types::all( '' ) as $t ) {
			if ( self::sync( $t ) ) {
				++$n;
			}
		}
		return $n;
	}

	/** Type id behind a product (0 = not a donation product). */
	public static function type_of_product( $product ) {
		$id = $product instanceof WC_Product ? $product->get_id() : (int) $product;
		if ( ! $id ) {
			return 0;
		}
		$p = $product instanceof WC_Product ? $product : wc_get_product( $id );
		if ( ! $p ) {
			return 0;
		}
		$pid = $p->get_parent_id() ? $p->get_parent_id() : $id;
		$tid = (int) get_post_meta( $pid, self::META_TYPE, true );
		if ( $tid ) {
			return $tid;
		}
		return 0;
	}

	/** Every product page of a donation type goes to the donation page. */
	public static function redirect_product_page() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		$tid = self::type_of_product( get_queried_object_id() );
		if ( ! $tid ) {
			return;
		}
		$t   = FLXB_Types::get( $tid );
		$url = $t && '' !== FLXB_WC::type_page_url( $t ) ? FLXB_WC::type_page_url( $t ) : FLXB_WC::page_url();
		if ( $t ) {
			$url = add_query_arg( 'sekme', (string) $t['slug'], $url );
		}
		if ( $url && untrailingslashit( $url ) !== untrailingslashit( (string) get_permalink( get_queried_object_id() ) ) ) {
			wp_safe_redirect( $url, 302 );
			exit;
		}
	}

	/** A donation product can only be bought while its type is open. */
	public static function purchasable( $ok, $product ) {
		$tid = self::type_of_product( $product );
		if ( ! $tid ) {
			return $ok;
		}
		$t = FLXB_Types::get( $tid );
		return $t && FLXB_Types::is_open( $t );
	}
}
