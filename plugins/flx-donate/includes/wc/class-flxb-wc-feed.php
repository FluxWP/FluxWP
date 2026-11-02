<?php
/**
 * Product list XML (Google Merchant Center format) at /bagis-urunleri.xml (the address entered in
 * iyzico; /?flxb_feed=google without pretty permalinks). Services such as iyzico's "upload
 * your products with XML" read it. Every fixed-price option on sale of an open donation type is
 * one item; the link opens the donation page with that option selected. Listed: the types marked "Show in
 * the Donation options block" and every type with a "Page address"; filter `flxb_wc_feed_type`. An option with a − / +
 * quantity is listed at its own price (quantity 1). Free amounts have no fixed price and are left out.
 *
 * Item ids: "flxb-<type slug>-<option key>". Filter: `flxb_wc_feed_items`.
 *
 * GPL v2 or later.
 *
 * @package FLX_Bagis
 */

defined( 'ABSPATH' ) || exit;

class FLXB_WC_Feed {

	const SLUG = 'bagis-urunleri.xml';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'rule' ), 5 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'redirect_canonical', array( __CLASS__, 'no_slash' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_output' ), 0 );
	}

	public static function rule() {
		add_rewrite_rule( '^' . preg_quote( self::SLUG, '/' ) . '$', 'index.php?flxb_feed=google', 'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'flxb_feed';
		return $vars;
	}

	public static function no_slash( $url ) {
		return get_query_var( 'flxb_feed' ) ? false : $url;
	}

	public static function maybe_output() {
		if ( 'google' === get_query_var( 'flxb_feed' ) ) {
			self::output();
		}
	}

	public static function url() {
		return get_option( 'permalink_structure' ) ? home_url( '/' . self::SLUG ) : add_query_arg( 'flxb_feed', 'google', home_url( '/' ) );
	}

	/** Items of the list. */
	public static function items() {
		$brand = function_exists( 'flxb_brand' ) ? flxb_brand()['name'] : get_bloginfo( 'name' );
		$items = array();
		foreach ( FLXB_Types::all( 'active' ) as $t ) {
			/* Only types a visitor can donate to from the link: the donation page's own types, or types with a page address. */
			$listed = FLXB_WC_Cart::in_form( $t ) || '' !== FLXB_WC::type_page_url( $t );
			if ( ! FLXB_Types::is_open( $t ) || ! apply_filters( 'flxb_wc_feed_type', $listed, $t ) ) {
				continue;
			}
			$pid     = FLXB_WC_Products::product_id( $t, false );
			$product = $pid ? wc_get_product( $pid ) : null;
			$image   = $product && $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'full' ) : '';
			$image   = $image ? $image : ( (int) $t['image_id'] ? wp_get_attachment_image_url( (int) $t['image_id'], 'full' ) : '' );
			$image   = $image ? $image : FLXB_URL . 'assets/wc/img/' . ( 'fidan' === ( $t['options'][0]['unit'] ?? '' ) ? 'fidan-bagisi.jpg' : 'genel-bagis.jpg' );
			$desc    = trim( wp_strip_all_tags( (string) $t['description'] ) );
			$page    = '' !== FLXB_WC::type_page_url( $t ) ? FLXB_WC::type_page_url( $t ) : FLXB_WC::page_url();
			foreach ( $t['options'] as $o ) {
				if ( ! FLXB_Types::option_on_sale( $o ) ) {
					continue;
				}
				$n = (int) $o['units_per'];
				if ( 'fidan' === $o['unit'] ) {
					$count = number_format_i18n( max( 1, $n ) );
					/* translators: %s: number of saplings */
					$title = sprintf( __( '%s Sapling Donation', 'flx-donate' ), $count );
					/* translators: 1: number of saplings, 2: organisation */
					$text = sprintf( _n( 'Donation of %1$s sapling. %2$s plants it for you and sends an online certificate in your name. Pay by card or bank transfer.', 'Donation of %1$s saplings. %2$s plants them for you and sends an online certificate in your name. Pay by card or bank transfer.', max( 1, $n ), 'flx-donate' ), $count, $brand );
				} else {
					$title = $t['title'] . ' · ' . $o['label'];
					$text  = '';
				}
				$items[] = array(
					'id'          => 'flxb-' . $t['slug'] . '-' . $o['key'],
					'title'       => $title,
					'description' => '' !== $desc ? $desc : ( '' !== $text ? $text : $title ),
					'link'        => add_query_arg( array( 'sekme' => $t['slug'], 'flxb_opt' => $o['key'] ), $page ),
					'image'       => $image,
					'price'       => (float) $o['price'],
					'type'        => $t['title'],
				);
			}
		}
		return (array) apply_filters( 'flxb_wc_feed_items', $items );
	}

	public static function output() {
		$currency = get_woocommerce_currency();
		$brand    = function_exists( 'flxb_brand' ) ? flxb_brand()['name'] : get_bloginfo( 'name' );
		$x        = static function ( $s ) {
			return htmlspecialchars( (string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
		};
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'Cache-Control: public, max-age=3600' );
		header( 'X-Robots-Tag: noindex' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n<channel>\n";
		echo '<title>' . $x( $brand ) . "</title>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML-escaped
		echo '<link>' . $x( home_url( '/' ) ) . "</link>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<description>' . $x( __( 'Donation packages', 'flx-donate' ) ) . "</description>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( self::items() as $i ) {
			$rows = array(
				'g:id'                => $i['id'],
				'g:title'             => $i['title'],
				'g:description'       => $i['description'],
				'g:link'              => $i['link'],
				'g:image_link'        => $i['image'],
				'g:availability'      => 'in_stock',
				'g:price'             => number_format( (float) $i['price'], 2, '.', '' ) . ' ' . $currency,
				'g:condition'         => 'new',
				'g:brand'             => $brand,
				'g:identifier_exists' => 'no',
				/* translators: %s: donation type */
				'g:product_type'      => sprintf( __( 'Donation > %s', 'flx-donate' ), (string) ( $i['type'] ?? '' ) ),
			);
			echo "<item>\n";
			foreach ( $rows as $k => $v ) {
				if ( '' !== (string) $v ) {
					echo '  <' . $k . '>' . $x( $v ) . '</' . $k . ">\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
			echo "</item>\n";
		}
		echo "</channel>\n</rss>\n";
		exit;
	}
}
