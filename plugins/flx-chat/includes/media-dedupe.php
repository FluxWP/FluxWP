<?php
/**
 * 1.47.0: eski medya (Y/m/<rastgele>.<uzantı>, her mesaja ayrı kopya) içerik özetli tek kopyaya taşınır.
 *
 * Yönetici sayfası açıldıkça arka planda küçük parçalar halinde çalışır (Hostinger cron'a güvenilmez):
 * en sık 30 sn'de bir, parça başına en fazla 150 mesaj ve ~8 sn. Bitince bir daha çalışmaz.
 * Her dosya için: özet hesaplanır, hedef yoksa dosya taşınır, varsa (boyut aynıysa) eski kopya
 * ancak hiçbir mesaj artık onu göstermiyorsa silinir. Sonuç sayıları flxc_media_dedupe seçeneğinde tutulur.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

/** @return array state: done, last_id, moved, merged, freed (bayt), missing */
function flxc_media_dedupe_state() {
	$s = get_option( 'flxc_media_dedupe' );
	return wp_parse_args(
		is_array( $s ) ? $s : array(),
		array( 'done' => false, 'last_id' => 0, 'moved' => 0, 'merged' => 0, 'freed' => 0, 'missing' => 0 )
	);
}

/**
 * Bir parça işler.
 *
 * @return array state
 */
function flxc_media_dedupe_run( $limit = 150, $budget = 8.0 ) {
	global $wpdb;
	$st = flxc_media_dedupe_state();
	if ( $st['done'] ) {
		return $st;
	}
	$mt    = flxc_table( 'messages' );
	$dir   = flxc_media_dir();
	$start = microtime( true );
	// phpcs:ignore WordPress.DB.PreparedSQL
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, media_path, media_mime, media_name FROM $mt WHERE id > %d AND media_path <> '' AND media_path NOT LIKE 'files/%%' ORDER BY id ASC LIMIT %d", $st['last_id'], $limit ), ARRAY_A );
	if ( ! $rows ) {
		$st['done'] = true;
		update_option( 'flxc_media_dedupe', $st, false );
		return $st;
	}
	$seen = array();
	foreach ( $rows as $r ) {
		if ( microtime( true ) - $start > $budget ) {
			break;
		}
		$st['last_id'] = (int) $r['id'];
		$old           = (string) $r['media_path'];
		if ( isset( $seen[ $old ] ) ) {
			continue; // Bu parçada aynı yol zaten taşındı (tüm mesajlarıyla birlikte).
		}
		$seen[ $old ] = 1;
		$src           = $dir . '/' . $old;
		if ( false !== strpos( $old, '..' ) || ! is_file( $src ) ) {
			++$st['missing'];
			continue;
		}
		$sha  = sha1_file( $src );
		$new  = flxc_media_hash_path( $sha, (string) $r['media_mime'], (string) $r['media_name'] );
		$dst  = $dir . '/' . $new;
		$size = filesize( $src );
		if ( is_file( $dst ) ) {
			if ( filesize( $dst ) !== $size ) {
				continue; // Beklenmedik: aynı özet farklı boyut. Dokunma.
			}
			++$st['merged'];
		} else {
			wp_mkdir_p( dirname( $dst ) );
			// phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( ! @rename( $src, $dst ) ) {
				continue;
			}
			++$st['moved'];
		}
		// Bu yolu gösteren tüm mesajlar yeni yola geçer; eski dosya ancak artık kimse göstermiyorsa silinir.
		$wpdb->update( $mt, array( 'media_path' => $new ), array( 'media_path' => $old ) );
		if ( is_file( $src ) && flxc_media_release( $old ) ) {
			$st['freed'] += $size;
		}
	}
	update_option( 'flxc_media_dedupe', $st, false );
	return $st;
}

/** Yönetici sayfalarında arka planda, en sık 30 sn'de bir, tek seferde tek parça. */
add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || ! flxc_can_use() ) {
			return;
		}
		$st = flxc_media_dedupe_state();
		if ( $st['done'] || get_transient( 'flxc_media_dedupe_lock' ) ) {
			return;
		}
		set_transient( 'flxc_media_dedupe_lock', 1, 30 );
		add_action(
			'shutdown',
			function () {
				// Sayfa önce kullanıcıya gitsin, iş arkasından çalışsın.
				if ( function_exists( 'litespeed_finish_request' ) ) {
					litespeed_finish_request();
				} elseif ( function_exists( 'fastcgi_finish_request' ) ) {
					fastcgi_finish_request();
				}
				flxc_media_dedupe_run();
			}
		);
	}
);
