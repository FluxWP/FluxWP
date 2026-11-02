<?php
/**
 * Okuma katmanı: tarih aralıkları ve rapor sorguları. Panel, REST, MCP ve kısa kodlar bunu kullanır.
 * Not: "ziyaretçi" günlük tekil ziyaretçilerin toplamıdır (aynı kişi iki farklı gün gelirse 2 sayılır).
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

class FLXAN_Stats {

	/**
	 * Etkin süzgeç: seg = all | guest (giriş yapmamış) | members (giriş yapmış) | <rol>;
	 * rc = '' (hepsi) | '*' (yalnız referansla gelenler) | <kod>.
	 */
	public static $filter = array( 'seg' => 'all', 'rc' => '' );

	public static function set_filter( $seg = 'all', $rc = '' ) {
		$seg = sanitize_key( (string) $seg );
		$rc  = '*' === $rc ? '*' : flxan_clean_ref( (string) $rc );
		self::$filter = array( 'seg' => '' === $seg ? 'all' : $seg, 'rc' => $rc );
		return self::$filter;
	}

	/** Süzgecin SQL parçası (" AND …"). */
	public static function fw( $alias = '' ) {
		global $wpdb;
		$f   = self::$filter;
		$out = '';
		if ( 'guest' === $f['seg'] ) {
			$out .= " AND {$alias}seg = ''";
		} elseif ( 'members' === $f['seg'] ) {
			$out .= " AND {$alias}seg <> ''";
		} elseif ( 'all' !== $f['seg'] ) {
			$out .= $wpdb->prepare( " AND {$alias}seg = %s", $f['seg'] );
		}
		if ( '*' === $f['rc'] ) {
			$out .= " AND {$alias}rc <> ''";
		} elseif ( '' !== $f['rc'] ) {
			$out .= $wpdb->prepare( " AND {$alias}rc = %s", $f['rc'] );
		}
		return $out;
	}

	/** Hazır aralıklar → etiket. */
	public static function presets() {
		return array(
			'today'      => __( 'Bugün', 'flx-analytics' ),
			'yesterday'  => __( 'Dün', 'flx-analytics' ),
			'7d'         => __( 'Son 7 gün', 'flx-analytics' ),
			'30d'        => __( 'Son 30 gün', 'flx-analytics' ),
			'90d'        => __( 'Son 90 gün', 'flx-analytics' ),
			'this_month' => __( 'Bu ay', 'flx-analytics' ),
			'last_month' => __( 'Geçen ay', 'flx-analytics' ),
			'12m'        => __( 'Son 12 ay', 'flx-analytics' ),
			'this_year'  => __( 'Bu yıl', 'flx-analytics' ),
			'last_year'  => __( 'Geçen yıl', 'flx-analytics' ),
			'all'        => __( 'Tüm zamanlar', 'flx-analytics' ),
			'custom'     => __( 'Özel aralık', 'flx-analytics' ),
		);
	}

	/** Boyut türleri → etiket. */
	public static function kinds() {
		return array(
			'channel'      => __( 'Kanal', 'flx-analytics' ),
			'device'       => __( 'Cihaz', 'flx-analytics' ),
			'browser'      => __( 'Tarayıcı', 'flx-analytics' ),
			'os'           => __( 'İşletim sistemi', 'flx-analytics' ),
			'lang'         => __( 'Tarayıcı dili', 'flx-analytics' ),
			'country'      => __( 'Ülke', 'flx-analytics' ),
			'utm_source'   => __( 'Kampanya kaynağı', 'flx-analytics' ),
			'utm_medium'   => __( 'Kampanya ortamı', 'flx-analytics' ),
			'utm_campaign' => __( 'Kampanya', 'flx-analytics' ),
		);
	}

	/** Olay adları → etiket. */
	public static function event_labels() {
		return array(
			'outbound' => __( 'Dış bağlantı tıklaması', 'flx-analytics' ),
			'download' => __( 'Dosya indirme', 'flx-analytics' ),
			'tel'      => __( 'Telefon tıklaması', 'flx-analytics' ),
			'mail'     => __( 'E-posta tıklaması', 'flx-analytics' ),
			'whatsapp' => __( 'WhatsApp tıklaması', 'flx-analytics' ),
			'search'   => __( 'Site içi arama', 'flx-analytics' ),
			'donation' => __( 'Bağış / ödeme', 'flx-analytics' ),
			'checkout' => __( 'Ödeme adımı', 'flx-analytics' ),
			'add_to_cart'      => __( 'Sepete ekleme', 'flx-analytics' ),
			'remove_from_cart' => __( 'Sepetten çıkarma', 'flx-analytics' ),
			'coupon'           => __( 'Kupon kullanma', 'flx-analytics' ),
			'payment'          => __( 'Ödeme yöntemi seçme', 'flx-analytics' ),
			'order'            => __( 'Sipariş oluşturma (ödeme öncesi)', 'flx-analytics' ),
		);
	}

	private static function valid_date( $d ) {
		return is_string( $d ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) && strtotime( $d );
	}

	/**
	 * Aralığı çözer.
	 *
	 * @return array start, end, preset, label, days, prev_start, prev_end
	 */
	public static function range( $preset = '30d', $start = '', $end = '' ) {
		$tz    = wp_timezone();
		$today = new DateTimeImmutable( 'today', $tz );
		$p     = array_key_exists( $preset, self::presets() ) ? $preset : '30d';
		switch ( $p ) {
			case 'today':
				$s = $today;
				$e = $today;
				break;
			case 'yesterday':
				$s = $today->modify( '-1 day' );
				$e = $s;
				break;
			case '7d':
				$s = $today->modify( '-6 days' );
				$e = $today;
				break;
			case '90d':
				$s = $today->modify( '-89 days' );
				$e = $today;
				break;
			case 'this_month':
				$s = $today->modify( 'first day of this month' );
				$e = $today;
				break;
			case 'last_month':
				$s = $today->modify( 'first day of last month' );
				$e = $today->modify( 'last day of last month' );
				break;
			case '12m':
				$s = $today->modify( 'first day of this month' )->modify( '-11 months' );
				$e = $today;
				break;
			case 'this_year':
				$s = $today->setDate( (int) $today->format( 'Y' ), 1, 1 );
				$e = $today;
				break;
			case 'last_year':
				$y = (int) $today->format( 'Y' ) - 1;
				$s = $today->setDate( $y, 1, 1 );
				$e = $today->setDate( $y, 12, 31 );
				break;
			case 'all':
				global $wpdb;
				$min = $wpdb->get_var( 'SELECT MIN(d) FROM ' . flxan_table( 'site' ) ); // phpcs:ignore
				$s   = $min ? new DateTimeImmutable( $min, $tz ) : $today;
				$e   = $today;
				break;
			case 'custom':
				$s = self::valid_date( $start ) ? new DateTimeImmutable( $start, $tz ) : $today->modify( '-29 days' );
				$e = self::valid_date( $end ) ? new DateTimeImmutable( $end, $tz ) : $today;
				if ( $e < $s ) {
					list( $s, $e ) = array( $e, $s );
				}
				break;
			default:
				$s = $today->modify( '-29 days' );
				$e = $today;
		}
		$days = (int) $s->diff( $e )->days + 1;
		$pe   = $s->modify( '-1 day' );
		$ps   = $pe->modify( '-' . ( $days - 1 ) . ' days' );
		return array(
			'preset'     => $p,
			'label'      => self::presets()[ $p ],
			'start'      => $s->format( 'Y-m-d' ),
			'end'        => $e->format( 'Y-m-d' ),
			'days'       => $days,
			'prev_start' => $ps->format( 'Y-m-d' ),
			'prev_end'   => $pe->format( 'Y-m-d' ),
		);
	}

	/** Toplam ziyaretçi ve görüntülenme. */
	public static function totals( $s, $e ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT COALESCE(SUM(visitors),0) v, COALESCE(SUM(pageviews),0) p FROM ' . flxan_table( 'site' ) . ' WHERE d BETWEEN %s AND %s' . self::fw(), $s, $e ), ARRAY_A ); // phpcs:ignore
		return array(
			'visitors'  => (int) $r['v'],
			'pageviews' => (int) $r['p'],
		);
	}

	/** Zaman serisi; eksik günler sıfırla doldurulur. group: day | week | month | auto. */
	public static function series( $s, $e, $group = 'auto' ) {
		global $wpdb;
		$days = (int) ( new DateTime( $s ) )->diff( new DateTime( $e ) )->days + 1;
		if ( 'auto' === $group || ! in_array( $group, array( 'day', 'week', 'month' ), true ) ) {
			$group = $days <= 92 ? 'day' : ( $days <= 400 ? 'week' : 'month' );
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT d, SUM(visitors) visitors, SUM(pageviews) pageviews FROM ' . flxan_table( 'site' ) . ' WHERE d BETWEEN %s AND %s' . self::fw() . ' GROUP BY d', $s, $e ), ARRAY_A ); // phpcs:ignore
		$by   = array();
		foreach ( (array) $rows as $r ) {
			$by[ $r['d'] ] = array( (int) $r['visitors'], (int) $r['pageviews'] );
		}
		$out = array();
		$cur = new DateTimeImmutable( $s );
		$end = new DateTimeImmutable( $e );
		while ( $cur <= $end ) {
			$d = $cur->format( 'Y-m-d' );
			if ( 'week' === $group ) {
				$k = $cur->modify( 'monday this week' )->format( 'Y-m-d' );
				if ( $k < $s ) {
					$k = $s;
				}
			} elseif ( 'month' === $group ) {
				$k = $cur->format( 'Y-m-01' );
				if ( $k < $s ) {
					$k = $s;
				}
			} else {
				$k = $d;
			}
			if ( ! isset( $out[ $k ] ) ) {
				$out[ $k ] = array( 'd' => $k, 'visitors' => 0, 'pageviews' => 0 );
			}
			if ( isset( $by[ $d ] ) ) {
				$out[ $k ]['visitors']  += $by[ $d ][0];
				$out[ $k ]['pageviews'] += $by[ $d ][1];
			}
			$cur = $cur->modify( '+1 day' );
		}
		return array(
			'group' => $group,
			'items' => array_values( $out ),
		);
	}

	/** Yolun ekranda görünen adı. */
	public static function path_title( $path, $post_id ) {
		if ( $post_id > 0 ) {
			$t = get_the_title( $post_id );
			if ( '' !== (string) $t ) {
				return wp_strip_all_tags( html_entity_decode( $t, ENT_QUOTES, 'UTF-8' ) );
			}
		}
		if ( '/' === $path ) {
			return __( 'Ana sayfa', 'flx-analytics' );
		}
		return rawurldecode( $path );
	}

	/** En çok görüntülenen sayfalar. */
	public static function pages( $s, $e, $limit = 10, $offset = 0, $q = '', $only404 = false ) {
		global $wpdb;
		$pt    = flxan_table( 'pages' );
		$pa    = flxan_table( 'paths' );
		$where = $wpdb->prepare( 's.d BETWEEN %s AND %s', $s, $e ) . self::fw( 's.' );
		if ( '' !== (string) $q ) {
			$where .= $wpdb->prepare( ' AND p.path LIKE %s', '%' . $wpdb->esc_like( self::encode_path( $q ) ) . '%' );
		}
		if ( $only404 ) {
			$where .= ' AND p.is404 = 1';
		}
		$total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT s.path_id) FROM {$pt} s JOIN {$pa} p ON p.id = s.path_id WHERE {$where}" ); // phpcs:ignore
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT p.path, p.post_id, p.is404, SUM(s.visitors) visitors, SUM(s.pageviews) pageviews FROM {$pt} s JOIN {$pa} p ON p.id = s.path_id WHERE {$where} GROUP BY s.path_id ORDER BY pageviews DESC, visitors DESC LIMIT %d OFFSET %d", max( 1, (int) $limit ), max( 0, (int) $offset ) ), ARRAY_A ); // phpcs:ignore
		$ids   = array_filter( wp_list_pluck( (array) $rows, 'post_id' ) );
		if ( $ids ) {
			_prime_post_caches( array_map( 'intval', $ids ), false, false );
		}
		$items = array();
		foreach ( (array) $rows as $r ) {
			$items[] = array(
				'path'      => rawurldecode( $r['path'] ),
				'url'       => home_url( $r['path'] ),
				'post_id'   => (int) $r['post_id'],
				'title'     => self::path_title( $r['path'], (int) $r['post_id'] ),
				'is404'     => (bool) $r['is404'],
				'visitors'  => (int) $r['visitors'],
				'pageviews' => (int) $r['pageviews'],
			);
		}
		return array( 'total' => $total, 'items' => $items );
	}

	/** Aramada Türkçe karakterli yol: tabloda kodlu tutulur. */
	private static function encode_path( $q ) {
		return implode( '/', array_map( 'rawurlencode', explode( '/', (string) $q ) ) );
	}

	/** Yönlendiren siteler. */
	public static function referrers( $s, $e, $limit = 10, $offset = 0, $q = '' ) {
		global $wpdb;
		$rt    = flxan_table( 'referrers' );
		$rf    = flxan_table( 'refs' );
		$where = $wpdb->prepare( 's.d BETWEEN %s AND %s', $s, $e ) . self::fw( 's.' );
		if ( '' !== (string) $q ) {
			$where .= $wpdb->prepare( ' AND r.host LIKE %s', '%' . $wpdb->esc_like( strtolower( $q ) ) . '%' );
		}
		$total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT s.ref_id) FROM {$rt} s JOIN {$rf} r ON r.id = s.ref_id WHERE {$where}" ); // phpcs:ignore
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT r.host, SUM(s.visitors) visitors, SUM(s.pageviews) pageviews FROM {$rt} s JOIN {$rf} r ON r.id = s.ref_id WHERE {$where} GROUP BY s.ref_id ORDER BY visitors DESC, pageviews DESC LIMIT %d OFFSET %d", max( 1, (int) $limit ), max( 0, (int) $offset ) ), ARRAY_A ); // phpcs:ignore
		$known = flxan_known_sources();
		$items = array();
		foreach ( (array) $rows as $r ) {
			$items[] = array(
				'host'      => $r['host'],
				'channel'   => isset( $known[ $r['host'] ] ) ? $known[ $r['host'] ][1] : 'referral',
				'visitors'  => (int) $r['visitors'],
				'pageviews' => (int) $r['pageviews'],
			);
		}
		return array( 'total' => $total, 'items' => $items );
	}

	/** Boyut dağılımı (kanal, cihaz, tarayıcı…). */
	public static function dim( $kind, $s, $e, $limit = 10, $offset = 0 ) {
		global $wpdb;
		if ( ! array_key_exists( $kind, self::kinds() ) ) {
			return array( 'total' => 0, 'items' => array() );
		}
		$t     = flxan_table( 'dims' );
		$order = in_array( $kind, array( 'channel', 'utm_source', 'utm_medium', 'utm_campaign' ), true ) ? 'visitors DESC, pageviews DESC' : 'pageviews DESC, visitors DESC';
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT val) FROM {$t} WHERE kind = %s AND d BETWEEN %s AND %s" . self::fw(), $kind, $s, $e ) ); // phpcs:ignore
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT val, SUM(visitors) visitors, SUM(pageviews) pageviews FROM {$t} WHERE kind = %s AND d BETWEEN %s AND %s" . self::fw() . " GROUP BY val ORDER BY {$order} LIMIT %d OFFSET %d", $kind, $s, $e, max( 1, (int) $limit ), max( 0, (int) $offset ) ), ARRAY_A ); // phpcs:ignore
		$items = array();
		foreach ( (array) $rows as $r ) {
			$items[] = array(
				'value'     => $r['val'],
				'label'     => flxan_dim_label( $kind, $r['val'] ),
				'visitors'  => (int) $r['visitors'],
				'pageviews' => (int) $r['pageviews'],
			);
		}
		return array( 'total' => $total, 'items' => $items );
	}

	/** Olay türleri toplamı. */
	public static function event_names( $s, $e ) {
		global $wpdb;
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT name, SUM(hits) hits, SUM(uniques) uniques, SUM(amount) amount FROM ' . flxan_table( 'events' ) . ' WHERE d BETWEEN %s AND %s' . self::fw() . ' GROUP BY name ORDER BY hits DESC', $s, $e ), ARRAY_A ); // phpcs:ignore
		$labels = self::event_labels();
		$out    = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'name'    => $r['name'],
				'label'   => $labels[ $r['name'] ] ?? $r['name'],
				'hits'    => (int) $r['hits'],
				'uniques' => (int) $r['uniques'],
				'amount'  => (float) $r['amount'],
			);
		}
		return $out;
	}

	/** Olay ayrıntıları (ad + parametre). */
	public static function events( $s, $e, $name = '', $limit = 10, $offset = 0 ) {
		global $wpdb;
		$t     = flxan_table( 'events' );
		$where = $wpdb->prepare( 'd BETWEEN %s AND %s', $s, $e ) . self::fw();
		if ( '' !== (string) $name ) {
			$where .= $wpdb->prepare( ' AND name = %s', $name );
		}
		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM (SELECT 1 FROM {$t} WHERE {$where} GROUP BY name, param) x" ); // phpcs:ignore
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT name, param, SUM(hits) hits, SUM(uniques) uniques, SUM(amount) amount FROM {$t} WHERE {$where} GROUP BY name, param ORDER BY hits DESC LIMIT %d OFFSET %d", max( 1, (int) $limit ), max( 0, (int) $offset ) ), ARRAY_A ); // phpcs:ignore
		$labels = self::event_labels();
		$items  = array();
		foreach ( (array) $rows as $r ) {
			$items[] = array(
				'name'    => $r['name'],
				'label'   => $labels[ $r['name'] ] ?? $r['name'],
				'param'   => $r['param'],
				'hits'    => (int) $r['hits'],
				'uniques' => (int) $r['uniques'],
				'amount'  => (float) $r['amount'],
			);
		}
		return array( 'total' => $total, 'items' => $items );
	}

	/** Son X dakika: görüntülenme, ziyaretçi, dakikalık dağılım, sayfalar, kaynaklar. */
	public static function realtime( $minutes = 30 ) {
		global $wpdb;
		$minutes = max( 1, min( 60, (int) $minutes ) );
		$since   = time() - $minutes * MINUTE_IN_SECONDS;
		$t       = flxan_table( 'recent' );
		$row     = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) pv, COUNT(DISTINCT h) v FROM {$t} WHERE ts >= %d" . self::fw(), $since ), ARRAY_A ); // phpcs:ignore
		$per     = array_fill( 0, $minutes, 0 );
		$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT FLOOR((%d - ts) / 60) m, COUNT(*) n FROM {$t} WHERE ts >= %d" . self::fw() . " GROUP BY m", time(), $since ), ARRAY_A ); // phpcs:ignore
		foreach ( (array) $rows as $r ) {
			$i = $minutes - 1 - (int) $r['m'];
			if ( $i >= 0 && $i < $minutes ) {
				$per[ $i ] = (int) $r['n'];
			}
		}
		$pages = $wpdb->get_results( $wpdb->prepare( "SELECT p.path, p.post_id, COUNT(*) n FROM {$t} r JOIN " . flxan_table( 'paths' ) . " p ON p.id = r.path_id WHERE r.ts >= %d" . self::fw( 'r.' ) . " GROUP BY r.path_id ORDER BY n DESC LIMIT 5", $since ), ARRAY_A ); // phpcs:ignore
		$refs  = $wpdb->get_results( $wpdb->prepare( "SELECT f.host, COUNT(*) n FROM {$t} r JOIN " . flxan_table( 'refs' ) . " f ON f.id = r.ref_id WHERE r.ts >= %d AND r.ref_id > 0" . self::fw( 'r.' ) . " GROUP BY r.ref_id ORDER BY n DESC LIMIT 5", $since ), ARRAY_A ); // phpcs:ignore
		$ctry  = $wpdb->get_results( $wpdb->prepare( "SELECT country c, COUNT(DISTINCT h) n FROM {$t} WHERE ts >= %d AND country <> ''" . self::fw() . ' GROUP BY country ORDER BY n DESC LIMIT 10', $since ), ARRAY_A ); // phpcs:ignore
		$codes = $wpdb->get_results( $wpdb->prepare( "SELECT rc, COUNT(DISTINCT h) n FROM {$t} WHERE ts >= %d AND rc <> ''" . self::fw() . ' GROUP BY rc ORDER BY n DESC LIMIT 5', $since ), ARRAY_A ); // phpcs:ignore
		$now5  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT h) FROM {$t} WHERE ts >= %d" . self::fw(), time() - 5 * MINUTE_IN_SECONDS ) ); // phpcs:ignore
		return array(
			'minutes'    => $minutes,
			'active'     => $now5,
			'countries'  => array_map(
				function ( $r ) {
					return array( 'value' => $r['c'], 'label' => flxan_dim_label( 'country', $r['c'] ), 'visitors' => (int) $r['n'] );
				},
				(array) $ctry
			),
			'codes'      => array_map(
				function ( $r ) {
					return array( 'code' => $r['rc'], 'label' => flxan_ref_label( $r['rc'] ), 'visitors' => (int) $r['n'] );
				},
				(array) $codes
			),
			'pageviews'  => (int) $row['pv'],
			'visitors'   => (int) $row['v'],
			'per_minute' => $per,
			'pages'      => array_map(
				function ( $r ) {
					return array(
						'path'      => rawurldecode( $r['path'] ),
						'title'     => self::path_title( $r['path'], (int) $r['post_id'] ),
						'pageviews' => (int) $r['n'],
					);
				},
				(array) $pages
			),
			'referrers'  => array_map(
				function ( $r ) {
					return array( 'host' => $r['host'], 'pageviews' => (int) $r['n'] );
				},
				(array) $refs
			),
			'updated'    => (int) get_option( 'flxan_last_agg', 0 ),
		);
	}

	/** Adres ya da yazı kimliğinden yol kimlikleri. */
	public static function path_ids_for( $target ) {
		global $wpdb;
		$pa = flxan_table( 'paths' );
		if ( is_numeric( $target ) && (int) $target > 0 ) {
			return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$pa} WHERE post_id = %d", (int) $target ) ) ); // phpcs:ignore
		}
		$path = (string) $target;
		if ( preg_match( '~^https?://~i', $path ) ) {
			$path = (string) wp_parse_url( $path, PHP_URL_PATH );
		}
		$path = '/' . ltrim( $path, '/' );
		$enc  = self::encode_path( rawurldecode( $path ) );
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$pa} WHERE path IN (%s, %s)", $path, $enc ) ) ); // phpcs:ignore
	}

	/** Tek sayfa/yazı: toplam ve günlük seri. */
	public static function page_stats( $target, $s, $e ) {
		global $wpdb;
		$ids = self::path_ids_for( $target );
		if ( ! $ids ) {
			return array( 'found' => false, 'visitors' => 0, 'pageviews' => 0, 'series' => array() );
		}
		$in   = implode( ',', array_map( 'intval', $ids ) );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT d, SUM(visitors) v, SUM(pageviews) p FROM ' . flxan_table( 'pages' ) . " WHERE path_id IN ({$in}) AND d BETWEEN %s AND %s" . self::fw() . " GROUP BY d ORDER BY d", $s, $e ), ARRAY_A ); // phpcs:ignore
		$v    = 0;
		$p    = 0;
		$ser  = array();
		foreach ( (array) $rows as $r ) {
			$v    += (int) $r['v'];
			$p    += (int) $r['p'];
			$ser[] = array( 'd' => $r['d'], 'visitors' => (int) $r['v'], 'pageviews' => (int) $r['p'] );
		}
		$paths = $wpdb->get_col( "SELECT path FROM " . flxan_table( 'paths' ) . " WHERE id IN ({$in})" ); // phpcs:ignore
		return array(
			'found'     => true,
			'paths'     => array_map( 'rawurldecode', (array) $paths ),
			'visitors'  => $v,
			'pageviews' => $p,
			'series'    => $ser,
		);
	}

	/** Yazı kimlikleri için toplam görüntülenme (liste sütunu, kısa kod). */
	public static function post_views( array $post_ids, $s, $e ) {
		global $wpdb;
		$post_ids = array_filter( array_map( 'intval', $post_ids ) );
		if ( ! $post_ids ) {
			return array();
		}
		$in   = implode( ',', $post_ids );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT p.post_id, SUM(s.pageviews) pv, SUM(s.visitors) v FROM ' . flxan_table( 'pages' ) . ' s JOIN ' . flxan_table( 'paths' ) . " p ON p.id = s.path_id WHERE p.post_id IN ({$in}) AND s.d BETWEEN %s AND %s GROUP BY p.post_id", $s, $e ), ARRAY_A ); // phpcs:ignore
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r['post_id'] ] = array( 'pageviews' => (int) $r['pv'], 'visitors' => (int) $r['v'] );
		}
		return $out;
	}

	/** En çok görüntülenen yazılar (kısa kod / widget). */
	public static function popular_posts( $days, $limit, $post_types ) {
		global $wpdb;
		$e     = wp_date( 'Y-m-d' );
		$s     = $days > 0 ? wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS ) : '1970-01-01';
		$types = array_filter( array_map( 'sanitize_key', (array) $post_types ) );
		if ( ! $types ) {
			$types = array( 'post' );
		}
		$tin = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$sql = 'SELECT p.post_id, SUM(s.pageviews) pv FROM ' . flxan_table( 'pages' ) . ' s JOIN ' . flxan_table( 'paths' ) . " p ON p.id = s.path_id JOIN {$wpdb->posts} wp ON wp.ID = p.post_id WHERE p.post_id > 0 AND s.d BETWEEN %s AND %s AND wp.post_status = 'publish' AND wp.post_type IN ({$tin}) GROUP BY p.post_id ORDER BY pv DESC LIMIT %d";
		return $wpdb->get_results( $wpdb->prepare( $sql, array_merge( array( $s, $e ), $types, array( max( 1, min( 50, (int) $limit ) ) ) ) ), ARRAY_A ); // phpcs:ignore
	}

	/** Segment (rol) etiketi. */
	public static function seg_label( $seg ) {
		if ( '' === (string) $seg ) {
			return __( 'Dışarıdan gelenler', 'flx-analytics' );
		}
		$names = function_exists( 'wp_roles' ) ? wp_roles()->get_names() : array();
		return isset( $names[ $seg ] ) ? translate_user_role( $names[ $seg ] ) : $seg;
	}

	/** Oturum ölçüleri: ziyaret, ortalama süre (sn), hemen çıkma (%), ziyaret başına sayfa. */
	public static function visit_metrics( $s, $e ) {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) n, COALESCE(SUM(pages),0) pg, COALESCE(SUM(pages = 1),0) b, COALESCE(SUM(LEAST(last_ts - first_ts, 7200)),0) dur FROM ' . flxan_table( 'visits' ) . ' WHERE d BETWEEN %s AND %s' . self::fw(), $s, $e ), ARRAY_A ); // phpcs:ignore
		$n = (int) $r['n'];
		return array(
			'sessions'     => $n,
			'avg_duration' => $n ? (int) round( $r['dur'] / $n ) : 0,
			'bounce_rate'  => $n ? round( $r['b'] / $n * 100, 1 ) : 0,
			'pages_per'    => $n ? round( $r['pg'] / $n, 2 ) : 0,
		);
	}

	/** Rol bazlı dağılım (site tablosu). */
	public static function segments( $s, $e ) {
		global $wpdb;
		$f   = self::$filter;
		$rcw = '';
		if ( '*' === $f['rc'] ) {
			$rcw = " AND rc <> ''";
		} elseif ( '' !== $f['rc'] ) {
			$rcw = $wpdb->prepare( ' AND rc = %s', $f['rc'] );
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT seg, SUM(visitors) visitors, SUM(pageviews) pageviews FROM ' . flxan_table( 'site' ) . ' WHERE d BETWEEN %s AND %s' . $rcw . ' GROUP BY seg ORDER BY visitors DESC', $s, $e ), ARRAY_A ); // phpcs:ignore
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'value'     => $r['seg'],
				'label'     => self::seg_label( $r['seg'] ),
				'visitors'  => (int) $r['visitors'],
				'pageviews' => (int) $r['pageviews'],
			);
		}
		return $out;
	}

	/** Süzgeçte seçilebilecek roller (veri olan + tanımlı tüm roller). */
	public static function seg_options() {
		global $wpdb;
		$have  = (array) $wpdb->get_col( 'SELECT DISTINCT seg FROM ' . flxan_table( 'site' ) . " WHERE seg <> ''" ); // phpcs:ignore
		$names = function_exists( 'wp_roles' ) ? wp_roles()->get_names() : array();
		$out   = array();
		foreach ( array_unique( array_merge( $have, array_keys( $names ) ) ) as $seg ) {
			$out[ $seg ] = self::seg_label( $seg );
		}
		return $out;
	}

	/**
	 * Referans kodları: ziyaretçi, görüntülenme, giriş, bağış ve huni derinliği.
	 * Adı verilmiş ama henüz trafik almamış kodlar da listelenir.
	 */
	public static function refs( $s, $e, $limit = 50 ) {
		global $wpdb;
		$segw = self::fw();
		if ( '' !== self::$filter['rc'] ) {
			$keep = self::$filter;
			self::$filter['rc'] = '';
			$segw = self::fw();
			self::$filter = $keep;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT rc, SUM(visitors) visitors, SUM(pageviews) pageviews FROM ' . flxan_table( 'site' ) . " WHERE d BETWEEN %s AND %s AND rc <> ''" . $segw . ' GROUP BY rc', $s, $e ), ARRAY_A ); // phpcs:ignore
		$vis  = $wpdb->get_results( $wpdb->prepare( 'SELECT rc, COUNT(*) n, SUM(pages) pg, SUM(LEAST(last_ts - first_ts, 7200)) dur FROM ' . flxan_table( 'visits' ) . " WHERE d BETWEEN %s AND %s AND rc <> ''" . $segw . ' GROUP BY rc', $s, $e ), OBJECT_K ); // phpcs:ignore
		$don  = $wpdb->get_results( $wpdb->prepare( 'SELECT rc, SUM(hits) n, SUM(amount) a FROM ' . flxan_table( 'events' ) . " WHERE name = 'donation' AND d BETWEEN %s AND %s AND rc <> ''" . $segw . ' GROUP BY rc', $s, $e ), OBJECT_K ); // phpcs:ignore
		$fun  = flxan_funnels();
		$f1   = $fun ? $fun[0] : null;
		$deep = array();
		if ( $f1 ) {
			$last = count( $f1['steps'] );
			$fr   = $wpdb->get_results( $wpdb->prepare( 'SELECT v.rc, MAX(f.step) mx, SUM(f.step >= %d) done FROM ' . flxan_table( 'fsteps' ) . ' f JOIN ' . flxan_table( 'visits' ) . " v ON v.d = f.d AND v.h = f.h WHERE f.f = %d AND f.d BETWEEN %s AND %s AND v.rc <> ''" . self::fw( 'v.' ) . ' GROUP BY v.rc', $last, (int) $f1['id'], $s, $e ), OBJECT_K ); // phpcs:ignore
			foreach ( (array) $fr as $rc => $r ) {
				$deep[ $rc ] = array( (int) $r->mx, (int) $r->done );
			}
		}
		$names = flxan_ref_names();
		$all   = array();
		foreach ( (array) $rows as $r ) {
			$all[ $r['rc'] ] = array( (int) $r['visitors'], (int) $r['pageviews'] );
		}
		if ( '' === self::$filter['rc'] || '*' === self::$filter['rc'] ) {
			foreach ( $names as $code => $n ) {
				if ( ! isset( $all[ $code ] ) ) {
					$all[ $code ] = array( 0, 0 );
				}
			}
		} elseif ( ! isset( $all[ self::$filter['rc'] ] ) ) {
			$all = array_intersect_key( $all, array( self::$filter['rc'] => 1 ) );
		} else {
			$all = array( self::$filter['rc'] => $all[ self::$filter['rc'] ] );
		}
		$items = array();
		foreach ( $all as $code => $a ) {
			$code    = (string) $code;
			$v       = $vis[ $code ] ?? null;
			$dn      = $don[ $code ] ?? null;
			$mx      = $deep[ $code ][0] ?? 0;
			$items[] = array(
				'code'         => $code,
				'name'         => isset( $names[ $code ] ) ? $names[ $code ] : '',
				'label'        => flxan_ref_label( $code ),
				'url'          => add_query_arg( 'r', $code, home_url( '/' ) ),
				'visitors'     => $a[0],
				'pageviews'    => $a[1],
				'sessions'     => $v ? (int) $v->n : 0,
				'pages_per'    => $v && $v->n ? round( $v->pg / $v->n, 2 ) : 0,
				'avg_duration' => $v && $v->n ? (int) round( $v->dur / $v->n ) : 0,
				'donations'    => $dn ? (int) $dn->n : 0,
				'amount'       => $dn ? (float) $dn->a : 0.0,
				'deepest'      => $mx,
				'deepest_label' => $mx && $f1 ? $f1['steps'][ $mx - 1 ]['label'] : '',
				'completed'    => $deep[ $code ][1] ?? 0,
			);
		}
		usort(
			$items,
			function ( $a, $b ) {
				return array( $b['visitors'], $b['pageviews'] ) <=> array( $a['visitors'], $a['pageviews'] );
			}
		);
		return array_slice( $items, 0, max( 1, (int) $limit ) );
	}

	/** Huni gruplama seçenekleri. */
	public static function funnel_by() {
		return array(
			''        => __( 'Toplam', 'flx-analytics' ),
			'channel' => __( 'Kanala göre', 'flx-analytics' ),
			'ref'     => __( 'Yönlendiren siteye göre', 'flx-analytics' ),
			'rc'      => __( 'Referans koduna göre', 'flx-analytics' ),
			'seg'     => __( 'Role göre', 'flx-analytics' ),
			'country' => __( 'Ülkeye göre', 'flx-analytics' ),
		);
	}

	/**
	 * Huni: her adım için o adıma (ya da daha ilerisine) ulaşan ziyaret sayısı.
	 * by verilirse her grup için ayrı satır (en kalabalık 8 grup).
	 */
	public static function funnel( $fid, $s, $e, $by = '' ) {
		global $wpdb;
		$funnels = flxan_funnels();
		$f       = null;
		foreach ( $funnels as $x ) {
			if ( (int) $x['id'] === (int) $fid ) {
				$f = $x;
			}
		}
		if ( ! $f ) {
			$f = $funnels[0] ?? null;
		}
		if ( ! $f ) {
			return array( 'found' => false );
		}
		$n    = count( $f['steps'] );
		$cols = array(
			'channel' => 'v.channel',
			'ref'     => 'v.ref_id',
			'rc'      => 'v.rc',
			'seg'     => 'v.seg',
			'country' => 'v.country',
		);
		$by   = isset( $cols[ $by ] ) ? $by : '';
		$g    = $by ? $cols[ $by ] : "''";
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$g} g, f.step, COUNT(*) n FROM " . flxan_table( 'fsteps' ) . ' f JOIN ' . flxan_table( 'visits' ) . ' v ON v.d = f.d AND v.h = f.h WHERE f.f = %d AND f.d BETWEEN %s AND %s' . self::fw( 'v.' ) . ' GROUP BY g, f.step', (int) $f['id'], $s, $e ), ARRAY_A ); // phpcs:ignore
		$grp  = array();
		foreach ( (array) $rows as $r ) {
			$k = (string) $r['g'];
			if ( ! isset( $grp[ $k ] ) ) {
				$grp[ $k ] = array_fill( 0, $n, 0 );
			}
			$st = min( $n, (int) $r['step'] );
			for ( $i = 0; $i < $st; $i++ ) {
				$grp[ $k ][ $i ] += (int) $r['n'];
			}
		}
		$total = array_fill( 0, $n, 0 );
		foreach ( $grp as $c ) {
			foreach ( $c as $i => $v ) {
				$total[ $i ] += $v;
			}
		}
		$steps = array();
		foreach ( $f['steps'] as $i => $st ) {
			$steps[] = array(
				'label' => $st['label'],
				'type'  => $st['type'],
				'value' => $st['value'],
				'count' => $total[ $i ],
				'rate'  => $total[0] ? round( $total[ $i ] / $total[0] * 100, 1 ) : 0,
				'drop'  => $i && $total[ $i - 1 ] ? round( ( $total[ $i - 1 ] - $total[ $i ] ) / $total[ $i - 1 ] * 100, 1 ) : 0,
			);
		}
		$groups = array();
		if ( $by ) {
			uasort(
				$grp,
				function ( $a, $b ) {
					return $b[0] <=> $a[0];
				}
			);
			$hosts = array();
			if ( 'ref' === $by && $grp ) {
				$ids   = array_map( 'intval', array_keys( $grp ) );
				$hosts = $wpdb->get_results( 'SELECT id, host FROM ' . flxan_table( 'refs' ) . ' WHERE id IN (' . implode( ',', $ids ?: array( 0 ) ) . ')', OBJECT_K ); // phpcs:ignore
			}
			foreach ( array_slice( $grp, 0, 8, true ) as $k => $c ) {
				$k = (string) $k;
				switch ( $by ) {
					case 'channel':
						$label = flxan_dim_label( 'channel', '' !== $k ? $k : 'direct' );
						break;
					case 'ref':
						$label = '0' === $k || '' === $k ? __( '(doğrudan / bilinmiyor)', 'flx-analytics' ) : ( isset( $hosts[ $k ] ) ? $hosts[ $k ]->host : $k );
						break;
					case 'rc':
						$label = '' === $k ? __( '(referanssız)', 'flx-analytics' ) : flxan_ref_label( $k );
						break;
					case 'seg':
						$label = self::seg_label( $k );
						break;
					default:
						$label = '' === $k ? __( '(bilinmiyor)', 'flx-analytics' ) : flxan_dim_label( 'country', $k );
				}
				$groups[] = array(
					'value'  => $k,
					'label'  => $label,
					'counts' => $c,
					'rate'   => $c[0] ? round( $c[ $n - 1 ] / $c[0] * 100, 1 ) : 0,
				);
			}
		}
		return array(
			'found'   => true,
			'id'      => (int) $f['id'],
			'name'    => $f['name'],
			'by'      => $by,
			'steps'   => $steps,
			'groups'  => $groups,
			'funnels' => array_map(
				function ( $x ) {
					return array( 'id' => (int) $x['id'], 'name' => $x['name'] );
				},
				$funnels
			),
		);
	}

	/** Yüzde değişim (önceki dönem 0 ise null). */
	public static function change( $now, $prev ) {
		if ( ! $prev ) {
			return null;
		}
		return round( ( $now - $prev ) / $prev * 100, 1 );
	}

	/** Üst göstergeler + grafik (canlı yenilemede de kullanılır). */
	public static function kpi( $r ) {
		$t  = self::totals( $r['start'], $r['end'] );
		$p  = self::totals( $r['prev_start'], $r['prev_end'] );
		$vm = self::visit_metrics( $r['start'], $r['end'] );
		$vp = self::visit_metrics( $r['prev_start'], $r['prev_end'] );
		return array(
			'totals'      => $t,
			'prev'        => $p,
			'visits'      => $vm,
			'visits_prev' => $vp,
			'change'      => array(
				'visitors'     => self::change( $t['visitors'], $p['visitors'] ),
				'pageviews'    => self::change( $t['pageviews'], $p['pageviews'] ),
				'sessions'     => self::change( $vm['sessions'], $vp['sessions'] ),
				'avg_duration' => self::change( $vm['avg_duration'], $vp['avg_duration'] ),
				'bounce_rate'  => self::change( $vm['bounce_rate'], $vp['bounce_rate'] ),
				'pages_per'    => self::change( $vm['pages_per'], $vp['pages_per'] ),
			),
			'series'      => self::series( $r['start'], $r['end'] ),
		);
	}

	/** Panelin tek istekte istediği her şey. */
	public static function report( $r ) {
		$dims = array();
		foreach ( array_keys( self::kinds() ) as $k ) {
			$dims[ $k ] = self::dim( $k, $r['start'], $r['end'], 'country' === $k ? 250 : 8 );
		}
		return array(
			'range'     => $r,
			'filter'    => self::$filter,
		) + self::kpi( $r ) + array(
			'pages'     => self::pages( $r['start'], $r['end'], 10 ),
			'notfound'  => self::pages( $r['start'], $r['end'], 5, 0, '', true ),
			'referrers' => self::referrers( $r['start'], $r['end'], 10 ),
			'dims'      => $dims,
			'segments'  => self::segments( $r['start'], $r['end'] ),
			'refs'      => self::refs( $r['start'], $r['end'], 20 ),
			'funnel'    => self::funnel( 0, $r['start'], $r['end'], 'channel' ),
			'event_sum' => self::event_names( $r['start'], $r['end'] ),
			'events'    => self::events( $r['start'], $r['end'], '', 10 ),
			'realtime'  => self::realtime( 30 ),
		);
	}
}
