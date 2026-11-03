<?php
/**
 * Ürün feed'i (Google Merchant Center RSS 2.0, g: alanları). Adres: /?flxcm_feed=google&key=<anahtar>.
 * Yayımlanmış, görünür, fiyatı olan ürünler; değişken üründe her varyasyon ayrı satır (item_group_id).
 * Flux Donate'in bağış ürünleri feed'e girmez. Sonuç 1 saat önbellekte, ürün kaydedilince silinir.
 *
 * @package FLX_Commerce
 */

defined( 'ABSPATH' ) || exit;

final class FLXCM_Feed {

	const LIMIT = 5000;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'serve' ), 20 );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'flush' ) );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'flush' ) );
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'flush' ) );
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'flush' ) );
	}

	public static function flush() {
		delete_transient( 'flxcm_feed_google' );
	}

	public static function key() {
		$k = (string) flxcm_setting( 'feed_key' );
		if ( '' === $k ) {
			$k = wp_generate_password( 20, false, false );
			flxcm_save_settings( array( 'feed_key' => $k ) );
		}
		return $k;
	}

	public static function url() {
		return add_query_arg( array( 'flxcm_feed' => 'google', 'key' => self::key() ), home_url( '/' ) );
	}

	public static function serve() {
		if ( ! isset( $_GET['flxcm_feed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$key = isset( $_GET['key'] ) ? (string) wp_unslash( $_GET['key'] ) : ''; // phpcs:ignore
		if ( ! flxcm_setting( 'feed' ) || '' === $key || ! hash_equals( self::key(), $key ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}
		$xml = get_transient( 'flxcm_feed_google' );
		if ( ! is_string( $xml ) || '' === $xml ) {
			$xml = self::build();
			set_transient( 'flxcm_feed_google', $xml, HOUR_IN_SECONDS );
		}
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	private static function x( $s ) {
		return htmlspecialchars( wp_strip_all_tags( html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ) ), ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	/** Feed'e giren ürün nesneleri. */
	public static function products() {
		$ids = wc_get_products(
			array(
				'status'     => 'publish',
				'visibility' => 'catalog',
				'limit'      => self::LIMIT,
				'return'     => 'ids',
				'type'       => array( 'simple', 'variable', 'external' ),
			)
		);
		$out = array();
		foreach ( (array) $ids as $id ) {
			if ( get_post_meta( $id, '_flxb_type_id', true ) || get_post_meta( $id, '_flxcm_no_feed', true ) ) {
				continue;
			}
			$p = wc_get_product( $id );
			if ( ! $p ) {
				continue;
			}
			if ( $p->is_type( 'variable' ) ) {
				foreach ( $p->get_children() as $vid ) {
					$v = wc_get_product( $vid );
					if ( $v && $v->is_purchasable() && '' !== $v->get_price() ) {
						$out[] = array( $v, $p );
					}
				}
			} elseif ( '' !== $p->get_price() ) {
				$out[] = array( $p, null );
			}
		}
		return (array) apply_filters( 'flxcm_feed_products', $out );
	}

	public static function build() {
		$cur   = get_woocommerce_currency();
		$brand = trim( (string) flxcm_setting( 'feed_brand' ) );
		$brand = '' !== $brand ? $brand : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$fmt   = static function ( $n ) use ( $cur ) {
			return wc_format_decimal( $n, 2 ) . ' ' . $cur;
		};
		$o  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$o .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>';
		$o .= '<title>' . self::x( get_bloginfo( 'name' ) ) . '</title><link>' . self::x( home_url( '/' ) ) . '</link><description>' . self::x( get_bloginfo( 'description' ) ) . '</description>';
		foreach ( self::products() as $pair ) {
			list( $p, $parent ) = $pair;
			$base  = $parent ? $parent : $p;
			$desc  = $p->get_description() ? $p->get_description() : ( $base->get_short_description() ? $base->get_short_description() : $base->get_description() );
			$img   = wp_get_attachment_image_url( $p->get_image_id() ? $p->get_image_id() : $base->get_image_id(), 'full' );
			$terms = get_the_terms( $base->get_id(), 'product_cat' );
			$o    .= '<item>';
			$o    .= '<g:id>' . self::x( $p->get_sku() ? $p->get_sku() : $p->get_id() ) . '</g:id>';
			$o    .= '<g:title>' . self::x( $p->get_name() ) . '</g:title>';
			$o    .= '<g:description>' . self::x( mb_substr( wp_strip_all_tags( (string) $desc ), 0, 4900 ) ) . '</g:description>';
			$o    .= '<g:link>' . self::x( $p->get_permalink() ) . '</g:link>';
			if ( $img ) {
				$o .= '<g:image_link>' . self::x( $img ) . '</g:image_link>';
			}
			foreach ( array_slice( $base->get_gallery_image_ids(), 0, 10 ) as $gid ) {
				$u = wp_get_attachment_image_url( $gid, 'full' );
				if ( $u ) {
					$o .= '<g:additional_image_link>' . self::x( $u ) . '</g:additional_image_link>';
				}
			}
			$o .= '<g:availability>' . ( $p->is_in_stock() ? ( 'onbackorder' === $p->get_stock_status() ? 'backorder' : 'in_stock' ) : 'out_of_stock' ) . '</g:availability>';
			$o .= '<g:price>' . self::x( $fmt( $p->get_regular_price() ? $p->get_regular_price() : $p->get_price() ) ) . '</g:price>';
			if ( $p->is_on_sale() && '' !== $p->get_sale_price() ) {
				$o .= '<g:sale_price>' . self::x( $fmt( $p->get_sale_price() ) ) . '</g:sale_price>';
			}
			$o   .= '<g:condition>new</g:condition>';
			$o   .= '<g:brand>' . self::x( $brand ) . '</g:brand>';
			$gtin = method_exists( $p, 'get_global_unique_id' ) ? (string) $p->get_global_unique_id() : '';
			if ( '' !== $gtin ) {
				$o .= '<g:gtin>' . self::x( $gtin ) . '</g:gtin>';
			} elseif ( $p->get_sku() ) {
				$o .= '<g:mpn>' . self::x( $p->get_sku() ) . '</g:mpn>';
			} else {
				$o .= '<g:identifier_exists>no</g:identifier_exists>';
			}
			if ( $terms && ! is_wp_error( $terms ) ) {
				$o .= '<g:product_type>' . self::x( implode( ' > ', wp_list_pluck( $terms, 'name' ) ) ) . '</g:product_type>';
			}
			if ( $parent ) {
				$o .= '<g:item_group_id>' . self::x( $parent->get_sku() ? $parent->get_sku() : $parent->get_id() ) . '</g:item_group_id>';
			}
			if ( $p->get_weight() ) {
				$o .= '<g:shipping_weight>' . self::x( wc_format_decimal( $p->get_weight() ) . ' ' . get_option( 'woocommerce_weight_unit', 'kg' ) ) . '</g:shipping_weight>';
			}
			$o .= (string) apply_filters( 'flxcm_feed_item_extra', '', $p, $parent );
			$o .= '</item>';
		}
		$o .= '</channel></rss>';
		return $o;
	}
}
