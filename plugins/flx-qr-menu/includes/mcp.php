<?php
/**
 * Flux SEO & MCP araçları (`flxseo_mcp_tools`): menüleri listele, menüyü oku, ürün fiyatı / tükendi / gizli güncelle.
 * Menü yöneticisi yalnız kendi menülerini görür ve değiştirir.
 *
 * @package FLX_QR_Menu
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'flxseo_mcp_tools', 'flxq_mcp_tools' );

function flxq_mcp_tools( $tools ) {
	$tools = is_array( $tools ) ? $tools : array();

	$tools['flxq_list_menus'] = array(
		'title'       => __( 'QR menüleri listele', 'flx-qr-menu' ),
		'description' => __( 'QR menüleri: kimlik, ad, sürüm, yayında mı, ürün ve tükenen sayısı, bağlı siteler.', 'flx-qr-menu' ),
		'readonly'    => true,
		'cap'         => FLXQ_EDIT_CAP,
		'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
		'run'         => function () {
			$out = array();
			foreach ( flxq_visible_menu_ids() as $id ) {
				$i     = flxq_menu_info( $id );
				$out[] = array(
					'id'        => $i['id'],
					'name'      => $i['name'],
					'version'   => $i['version'],
					'enabled'   => $i['enabled'],
					'updated'   => $i['updated_iso'],
					'items'     => $i['stats']['items'],
					'soldout'   => $i['stats']['soldout'],
					'has_key'   => $i['key']['has'],
					'sites'     => count( $i['sites'] ),
					'last_seen' => $i['last_seen'] ? wp_date( 'c', $i['last_seen'] ) : null,
					'urls'      => wp_list_pluck( $i['urls'], 'url' ),
				);
			}
			return array( 'menus' => $out );
		},
	);

	$tools['flxq_get_menu'] = array(
		'title'       => __( 'QR menüyü oku', 'flx-qr-menu' ),
		'description' => __( 'Bir QR menünün tüm verisi (kategoriler, ürünler, etkinlik).', 'flx-qr-menu' ),
		'readonly'    => true,
		'cap'         => FLXQ_EDIT_CAP,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array( 'menu_id' => array( 'type' => 'integer' ) ),
			'required'   => array( 'menu_id' ),
		),
		'run'         => function ( $args ) {
			$id = flxq_mcp_menu_id( $args );
			return array(
				'id'      => $id,
				'name'    => get_the_title( $id ),
				'version' => flxq_menu_version( $id ),
				'enabled' => flxq_menu_enabled( $id ),
				'data'    => flxq_menu_data( $id, true ),
			);
		},
	);

	$tools['flxq_update_item'] = array(
		'title'       => __( 'QR menüde ürün güncelle', 'flx-qr-menu' ),
		'description' => __( 'Kategori kimliği ve ürün adıyla bulunan ürünün fiyatını, tükendi ya da gizli durumunu değiştirir. Geçmişe yazılır, menü sitelerine bildirim gider.', 'flx-qr-menu' ),
		'readonly'    => false,
		'cap'         => FLXQ_EDIT_CAP,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'menu_id'  => array( 'type' => 'integer' ),
				'category' => array( 'type' => 'string', 'description' => __( 'Kategori kimliği (ör. "ana")', 'flx-qr-menu' ) ),
				'item'     => array( 'type' => 'string', 'description' => __( 'Ürün adı (büyük/küçük harf duyarsız)', 'flx-qr-menu' ) ),
				'fiyat'    => array( 'type' => array( 'number', 'string', 'null' ) ),
				'bitti'    => array( 'type' => 'boolean' ),
				'gizli'    => array( 'type' => 'boolean' ),
			),
			'required'   => array( 'menu_id', 'category', 'item' ),
		),
		'run'         => 'flxq_mcp_update_item',
	);
	return $tools;
}

function flxq_mcp_menu_id( $args ) {
	$id   = isset( $args['menu_id'] ) ? (int) $args['menu_id'] : 0;
	$post = flxq_get_post( $id );
	if ( ! $post || 'trash' === $post->post_status ) {
		throw new Exception( esc_html__( 'Menü bulunamadı.', 'flx-qr-menu' ) );
	}
	if ( ! flxq_can_edit_menu( $id ) ) {
		throw new Exception( esc_html__( 'Bu menüye erişim yetkiniz yok.', 'flx-qr-menu' ), 403 );
	}
	return $id;
}

function flxq_mcp_update_item( $args ) {
	$id   = flxq_mcp_menu_id( $args );
	$data = flxq_menu_data( $id );
	$cat  = isset( $args['category'] ) ? (string) $args['category'] : '';
	$name = mb_strtolower( trim( isset( $args['item'] ) ? (string) $args['item'] : '' ) );
	$hit  = null;
	foreach ( $data->kategoriler as $k ) {
		if ( $k->id !== $cat ) {
			continue;
		}
		foreach ( $k->urunler as $u ) {
			if ( ! property_exists( $u, 'araBaslik' ) && isset( $u->ad ) && mb_strtolower( trim( $u->ad ) ) === $name ) {
				$hit = $u;
				break 2;
			}
		}
	}
	if ( ! $hit ) {
		throw new Exception( esc_html__( 'Ürün bulunamadı (kategori kimliği ve ürün adını kontrol edin).', 'flx-qr-menu' ) );
	}
	if ( array_key_exists( 'fiyat', $args ) ) {
		$hit->fiyat = flxq_price( $args['fiyat'] );
	}
	foreach ( array( 'bitti', 'gizli' ) as $f ) {
		if ( array_key_exists( $f, $args ) ) {
			$hit->$f = (bool) $args[ $f ];
		}
	}
	$res = flxq_save_menu( $id, null, $data, array( 'action' => 'mcp' ) );
	if ( is_wp_error( $res ) ) {
		throw new Exception( esc_html( $res->get_error_message() ) );
	}
	return array(
		'menu_id' => $id,
		'version' => $res['version'],
		'changed' => ! $res['unchanged'],
		'summary' => $res['summary'],
		'item'    => json_decode( wp_json_encode( $hit ), true ),
	);
}
