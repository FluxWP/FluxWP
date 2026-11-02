<?php
/**
 * Sistem kontrolü satırları (ortak `flx_system_checks` sözleşmesi). Ucuz: seçenek + meta okuma, dış HTTP yok.
 *
 * @package FLX_QR_Menu
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'flx_system_checks', 'flxq_system_checks_filter' );

function flxq_system_checks_filter( $checks ) {
	return array_merge( is_array( $checks ) ? $checks : array(), flxq_system_checks() );
}

function flxq_system_checks() {
	$src   = __( 'Flux QR Menu', 'flx-qr-menu' );
	$group = __( 'QR Menü', 'flx-qr-menu' );
	$url   = admin_url( 'admin.php?page=flx-qr-menu' );
	$open  = __( 'QR Menüler', 'flx-qr-menu' );
	$row   = function ( $id, $label, $status, $message ) use ( $src, $group, $url, $open ) {
		return array(
			'id'           => 'flxq.' . $id,
			'source'       => $src,
			'group'        => $group,
			'label'        => $label,
			'status'       => $status,
			'message'      => $message,
			'action_url'   => $url,
			'action_label' => $open,
		);
	};
	$out = array();

	$ok_table = flxq_history_table_exists();
	$out[]    = $row(
		'history',
		__( 'Değişiklik geçmişi tablosu', 'flx-qr-menu' ),
		$ok_table ? 'ok' : 'error',
		$ok_table ? __( 'Geçmiş tablosu kurulu.', 'flx-qr-menu' ) : __( 'Geçmiş tablosu yok. Eklentiyi devre dışı bırakıp yeniden etkinleştirin.', 'flx-qr-menu' )
	);

	$ids   = flxq_menu_ids();
	$out[] = $row(
		'menus',
		__( 'Menüler', 'flx-qr-menu' ),
		$ids ? 'ok' : 'info',
		$ids
			/* translators: %d: number of menus */
			? sprintf( _n( '%d menü var.', '%d menü var.', count( $ids ), 'flx-qr-menu' ), count( $ids ) )
			: __( 'Henüz menü yok.', 'flx-qr-menu' )
	);
	if ( ! $ids ) {
		return $out;
	}

	$no_key = array();
	$never  = array();
	$stale  = array();
	foreach ( $ids as $id ) {
		if ( ! flxq_menu_enabled( $id ) ) {
			continue;
		}
		$name = get_the_title( $id );
		if ( ! get_post_meta( $id, '_flxq_key_hash', true ) && flxq_menu_urls( $id ) ) {
			continue; // Yalnız bu sitede yayınlanan menünün anahtara ihtiyacı yok.
		}
		if ( ! get_post_meta( $id, '_flxq_key_hash', true ) ) {
			$no_key[] = $name;
			continue;
		}
		$last = 0;
		foreach ( flxq_get_sites( $id ) as $s ) {
			$last = max( $last, (int) $s['last'] );
		}
		if ( ! $last ) {
			$never[] = $name;
		} elseif ( time() - $last > DAY_IN_SECONDS ) {
			$stale[] = $name;
		}
	}
	$routes = flxq_routes();
	$count  = count( $routes['slugs'] ) + count( $routes['hosts'] );
	if ( $count ) {
		$plain = '' === (string) get_option( 'permalink_structure' );
		$out[] = $row(
			'routes',
			__( 'Menü adresleri', 'flx-qr-menu' ),
			$plain ? 'warning' : 'ok',
			$plain
				? __( 'Kalıcı bağlantılar "Düz" ayarında; /menu/ gibi adresler sunucuda 404 verebilir. Ayarlar › Kalıcı bağlantılar › "Yazı adı" seçip kaydedin.', 'flx-qr-menu' )
				/* translators: %d: number of addresses */
				: sprintf( _n( 'Bu sitede %d menü adresi açık.', 'Bu sitede %d menü adresi açık.', $count, 'flx-qr-menu' ), $count )
		);
	}
	$out[] = $row(
		'keys',
		__( 'Bağlantı anahtarı', 'flx-qr-menu' ),
		$no_key ? 'warning' : 'ok',
		$no_key
			/* translators: %s: comma separated menu names */
			? sprintf( __( 'Anahtarı olmayan yayındaki menüler: %s. Menü › Bağlantı sekmesinden anahtar oluşturun.', 'flx-qr-menu' ), implode( ', ', $no_key ) )
			: __( 'Yayındaki tüm menülerin anahtarı var.', 'flx-qr-menu' )
	);
	$msg = array();
	if ( $never ) {
		/* translators: %s: comma separated menu names */
		$msg[] = sprintf( __( 'Hiç bağlanmamış: %s.', 'flx-qr-menu' ), implode( ', ', $never ) );
	}
	if ( $stale ) {
		/* translators: %s: comma separated menu names */
		$msg[] = sprintf( __( '24 saattir görülmeyen: %s.', 'flx-qr-menu' ), implode( ', ', $stale ) );
	}
	$out[] = $row(
		'sites',
		__( 'Menü siteleri', 'flx-qr-menu' ),
		$msg ? 'warning' : 'ok',
		$msg ? implode( ' ', $msg ) : __( 'Anahtarlı menülerin siteleri son 24 saatte veri çekti.', 'flx-qr-menu' )
	);
	return $out;
}
