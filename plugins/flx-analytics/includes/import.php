<?php
/**
 * Koko Analytics'ten geçmiş verinin aktarımı (yalnız okuma; Koko tablolarına dokunulmaz).
 * Flux Analiz'in kurulduğu günden önceki günler aktarılır; tekrar çalıştırmak aynı sonucu verir.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

function flxan_table_exists( $t ) {
	global $wpdb;
	return $t === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) );
}

function flxan_table_columns( $t ) {
	global $wpdb;
	return (array) $wpdb->get_col( "SHOW COLUMNS FROM {$t}" ); // phpcs:ignore
}

/** Aktarılabilecek Koko verisi: false ya da [ days, first, last ]. */
function flxan_koko_available() {
	global $wpdb;
	$t = $wpdb->prefix . 'koko_analytics_site_stats';
	if ( ! flxan_table_exists( $t ) ) {
		return false;
	}
	$r = $wpdb->get_row( "SELECT COUNT(*) n, MIN(date) a, MAX(date) b FROM {$t}", ARRAY_A ); // phpcs:ignore
	return $r && (int) $r['n'] ? array( 'days' => (int) $r['n'], 'first' => $r['a'], 'last' => $r['b'] ) : false;
}

/** Flux Analiz'in kendi verisinin başladığı gün; bundan önceki günler Koko'dan alınır. */
function flxan_import_cutoff() {
	$d = get_option( 'flxan_first_day' );
	if ( ! $d ) {
		$d = wp_date( 'Y-m-d' );
		update_option( 'flxan_first_day', $d, false );
	}
	return $d;
}

/** Aktarım. Dönüş: [ site, pages, referrers ] satır sayıları. */
function flxan_koko_import() {
	global $wpdb;
	$k   = $wpdb->prefix . 'koko_analytics_';
	$cut = flxan_import_cutoff();
	$out = array( 'site' => 0, 'pages' => 0, 'referrers' => 0 );

	$site = flxan_table( 'site' );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$site} WHERE d < %s", $cut ) ); // phpcs:ignore
	$out['site'] = (int) $wpdb->query( $wpdb->prepare( "INSERT INTO {$site} (d, visitors, pageviews) SELECT date, visitors, pageviews FROM {$k}site_stats WHERE date < %s", $cut ) ); // phpcs:ignore

	if ( flxan_table_exists( $k . 'paths' ) && flxan_table_exists( $k . 'post_stats' ) && in_array( 'path_id', flxan_table_columns( $k . 'post_stats' ), true ) ) {
		$paths = flxan_table( 'paths' );
		$pages = flxan_table( 'pages' );
		$wpdb->query( "INSERT IGNORE INTO {$paths} (path, post_id) SELECT LEFT(kp.path, 255), MAX(ps.post_id) FROM {$k}paths kp JOIN {$k}post_stats ps ON ps.path_id = kp.id GROUP BY kp.id" ); // phpcs:ignore
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$pages} WHERE d < %s", $cut ) ); // phpcs:ignore
		$out['pages'] = (int) $wpdb->query( $wpdb->prepare( "INSERT INTO {$pages} (d, path_id, visitors, pageviews) SELECT ps.date, yp.id, SUM(ps.visitors), SUM(ps.pageviews) FROM {$k}post_stats ps JOIN {$k}paths kp ON kp.id = ps.path_id JOIN {$paths} yp ON yp.path = LEFT(kp.path, 255) WHERE ps.date < %s GROUP BY ps.date, yp.id", $cut ) ); // phpcs:ignore
	}

	$labels = flxan_table_exists( $k . 'referrer_labels' ) ? array( $k . 'referrer_labels', 'value' ) : ( flxan_table_exists( $k . 'referrer_urls' ) ? array( $k . 'referrer_urls', 'url' ) : null );
	if ( $labels && flxan_table_exists( $k . 'referrer_stats' ) ) {
		$cols = flxan_table_columns( $k . 'referrer_stats' );
		$v    = in_array( 'unique_hits', $cols, true ) ? 'unique_hits' : 'visitors';
		$p    = in_array( 'hits', $cols, true ) ? 'hits' : 'pageviews';
		$own  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$map  = array();
		foreach ( (array) $wpdb->get_results( "SELECT id, {$labels[1]} v FROM {$labels[0]}", ARRAY_A ) as $row ) { // phpcs:ignore
			$val  = (string) $row['v'];
			$host = flxan_normalize_referrer( preg_match( '~^[a-z][a-z0-9+.-]*://~i', $val ) ? $val : 'https://' . $val, $own );
			if ( '' !== $host ) {
				$map[ $host ][] = (int) $row['id'];
			}
		}
		$refs = flxan_table( 'refs' );
		$rs   = flxan_table( 'referrers' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$rs} WHERE d < %s", $cut ) ); // phpcs:ignore
		foreach ( $map as $host => $ids ) {
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$refs} (host) VALUES (%s)", $host ) ); // phpcs:ignore
			$rid = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$refs} WHERE host = %s", $host ) ); // phpcs:ignore
			$in  = implode( ',', array_map( 'intval', $ids ) );
			$out['referrers'] += (int) $wpdb->query( $wpdb->prepare( "INSERT INTO {$rs} (d, ref_id, visitors, pageviews) SELECT date, %d, SUM({$v}), SUM({$p}) FROM {$k}referrer_stats WHERE id IN ({$in}) AND date < %s GROUP BY date", $rid, $cut ) ); // phpcs:ignore
		}
	}
	update_option( 'flxan_koko_imported', array( 'time' => time(), 'rows' => $out ), false );
	return $out;
}
