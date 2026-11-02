<?php
/**
 * Site haritasına Flux QR Menu'nün yayındaki menü adreslerini ekler (/wp-sitemap-qrmenus-1.xml).
 *
 * @package flx-menu-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YF_Sitemap_Menus extends WP_Sitemaps_Provider {

	public function __construct() {
		$this->name        = 'qrmenus';
		$this->object_type = 'qrmenu';
	}

	/** @return string[] Yayındaki menülerin bu alan adındaki adresleri. */
	private function urls() {
		$out = array();
		if ( ! function_exists( 'flxq_menu_urls' ) || ! function_exists( 'flxq_menu_ids' ) ) {
			return $out;
		}
		foreach ( flxq_menu_ids() as $id ) {
			if ( function_exists( 'flxq_menu_enabled' ) && ! flxq_menu_enabled( $id ) ) {
				continue;
			}
			foreach ( flxq_menu_urls( $id ) as $u ) {
				// Site haritası yalnız bu alan adındaki adresleri içerebilir (alt alan adları hariç).
				if ( 0 === strpos( $u['url'], home_url( '/' ) ) ) {
					$out[] = $u['url'];
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	public function get_url_list( $page_num, $object_subtype = '' ) {
		return array_map(
			function ( $u ) {
				return array( 'loc' => $u );
			},
			1 === (int) $page_num ? $this->urls() : array()
		);
	}

	public function get_max_num_pages( $object_subtype = '' ) {
		return $this->urls() ? 1 : 0;
	}
}
