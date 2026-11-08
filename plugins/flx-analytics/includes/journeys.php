<?php
/**
 * Yolculuklar: ziyaretlerin sayfa sırası (giriş → adım adım → çıkış), hedefe ulaşma ve en sık izlenen yollar.
 * Kaynak: visits.seq (ziyaret başına ilk 8 sayfa, ardışık tekrarlar tek) + visits.exit_id + fsteps (hedef).
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

class FLXAN_Journeys {

	/** Akış grafiğinde bir adımda gösterilen en fazla sayfa (gerisi "Diğer"). */
	const PER_STEP = 6;

	/** Hedef seçenekleri: huni adımları ("fid:adım"). */
	public static function goals() {
		$out = array( '' => __( 'No goal', 'flx-analytics' ) );
		foreach ( flxan_funnels() as $f ) {
			foreach ( $f['steps'] as $i => $st ) {
				if ( 'any' === $st['type'] ) {
					continue;
				}
				$out[ (int) $f['id'] . ':' . ( $i + 1 ) ] = $st['label'] . ( count( flxan_funnels() ) > 1 ? ' (' . $f['name'] . ')' : '' );
			}
		}
		return $out;
	}

	/** Varsayılan hedef: ödeme sayfası adımı, yoksa ilk anlamlı adım. */
	public static function default_goal() {
		$co = function_exists( 'wc_get_checkout_url' ) ? (string) wp_parse_url( wc_get_checkout_url(), PHP_URL_PATH ) : '/odeme/';
		foreach ( flxan_funnels() as $f ) {
			foreach ( $f['steps'] as $i => $st ) {
				if ( 'path' === $st['type'] && untrailingslashit( $st['value'] ) === untrailingslashit( $co ) ) {
					return (int) $f['id'] . ':' . ( $i + 1 );
				}
			}
		}
		$keys = array_keys( self::goals() );
		return $keys[1] ?? '';
	}

	/** Ziyaretleri (seq, çıkış, hedef) gruplanmış olarak okur. */
	private static function load( $s, $e, $goal ) {
		global $wpdb;
		$v = flxan_table( 'visits' );
		$g = '0';
		$j = '';
		if ( preg_match( '/^(\d+):(\d+)$/', (string) $goal, $m ) ) {
			$g = 'IF(f.step >= ' . (int) $m[2] . ', 1, 0)';
			$j = ' LEFT JOIN ' . flxan_table( 'fsteps' ) . ' f ON f.d = v.d AND f.h = v.h AND f.f = ' . (int) $m[1];
		}
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT v.seq, v.exit_id, {$g} g, COUNT(*) n FROM {$v} v{$j} WHERE v.d BETWEEN %s AND %s AND v.seq <> ''" . FLXAN_Stats::fw( 'v.' ) . ' GROUP BY v.seq, v.exit_id, g', $s, $e ), ARRAY_A ); // phpcs:ignore
	}

	/** Yolu düğüm anahtarına çevirir (gruplu: /haber/yazi/ → /haber/*). */
	private static function key_of( $path, $group ) {
		if ( ! $group ) {
			return $path;
		}
		$parts = array_values( array_filter( explode( '/', $path ), 'strlen' ) );
		return count( $parts ) > 1 ? '/' . $parts[0] . '/*' : $path;
	}

	/**
	 * Ziyaret satırlarını yola çevirir.
	 *
	 * @return array[] her biri: keys (sıra), exit, goal, n
	 */
	private static function sessions( array $rows, array $paths, $group ) {
		$out = array();
		foreach ( $rows as $r ) {
			$keys = array();
			foreach ( explode( ',', $r['seq'] ) as $id ) {
				if ( ! isset( $paths[ (int) $id ] ) ) {
					continue;
				}
				$k = self::key_of( $paths[ (int) $id ], $group );
				if ( end( $keys ) !== $k ) {
					$keys[] = $k;
				}
			}
			if ( ! $keys ) {
				continue;
			}
			$exit  = isset( $paths[ (int) $r['exit_id'] ] ) ? self::key_of( $paths[ (int) $r['exit_id'] ], $group ) : end( $keys );
			$out[] = array(
				'keys' => $keys,
				'exit' => $exit,
				'goal' => (int) $r['g'],
				'n'    => (int) $r['n'],
			);
		}
		return $out;
	}

	/** Yol kimlik → yol (yalnız gereken kimlikler). */
	private static function paths_for( array $rows ) {
		global $wpdb;
		$ids = array();
		foreach ( $rows as $r ) {
			foreach ( explode( ',', $r['seq'] ) as $id ) {
				$ids[ (int) $id ] = true;
			}
			$ids[ (int) $r['exit_id'] ] = true;
		}
		unset( $ids[0] );
		$out = array();
		foreach ( array_chunk( array_keys( $ids ), 1000 ) as $chunk ) {
			foreach ( (array) $wpdb->get_results( 'SELECT id, path, post_id FROM ' . flxan_table( 'paths' ) . ' WHERE id IN (' . implode( ',', array_map( 'intval', $chunk ) ) . ')', ARRAY_A ) as $p ) { // phpcs:ignore
				$out[ (int) $p['id'] ] = $p['path'];
				self::$posts[ $p['path'] ] = (int) $p['post_id'];
			}
		}
		return $out;
	}

	/** Yol → yazı kimliği (başlık için). */
	private static $posts = array();

	/** Düğüm etiketi. */
	private static function label( $key ) {
		if ( '@' === substr( $key, 0, 1 ) ) {
			return self::event_label( substr( $key, 1 ) );
		}
		if ( '*' === substr( $key, -1 ) ) {
			return rawurldecode( $key );
		}
		return FLXAN_Stats::path_title( $key, self::$posts[ $key ] ?? 0 );
	}

	/** Yolculuktaki olay adımının adı (ör. "Sepete ekledi"). */
	public static function event_label( $ev ) {
		$map = array(
			'add_to_cart'      => __( 'Added to cart', 'flx-analytics' ),
			'remove_from_cart' => __( 'Removed from cart', 'flx-analytics' ),
			'coupon'           => __( 'Used a coupon', 'flx-analytics' ),
			'checkout:form'    => __( 'Started checkout form', 'flx-analytics' ),
			'checkout:submit'  => __( 'Clicked "Place order"', 'flx-analytics' ),
			'checkout'         => __( 'Checkout step', 'flx-analytics' ),
			'payment'          => __( 'Chose payment method', 'flx-analytics' ),
			'order'            => __( 'Order created', 'flx-analytics' ),
			'whatsapp'         => __( 'Clicked WhatsApp', 'flx-analytics' ),
			'tel'              => __( 'Clicked phone', 'flx-analytics' ),
			'download'         => __( 'Downloaded a file', 'flx-analytics' ),
		);
		if ( isset( $map[ $ev ] ) ) {
			return $map[ $ev ];
		}
		$labels = FLXAN_Stats::event_labels();
		return $labels[ $ev ] ?? $ev;
	}

	/** Sayfa süzgeci: yazılan yol (ya da grup) ile eşleşir mi? */
	private static function match( $key, $want ) {
		if ( '' === $want ) {
			return true;
		}
		$k = strtolower( rawurldecode( $key ) );
		$w = strtolower( rawurldecode( $want ) );
		return $k === $w || untrailingslashit( $k ) === untrailingslashit( $w ) || ( '*' === substr( $k, -1 ) && 0 === strpos( $w, rtrim( $k, '*' ) ) );
	}

	/** Yoldaki sayfa sayısı (olay adımları hariç). */
	private static function pages_in( array $keys ) {
		$n = 0;
		foreach ( $keys as $k ) {
			$n += '@' === substr( $k, 0, 1 ) ? 0 : 1;
		}
		return $n;
	}

	/** Bir dönemin özeti + yol sayıları. */
	private static function period( $s, $e, array $o ) {
		$rows  = self::load( $s, $e, $o['goal'] );
		$paths = self::paths_for( $rows );
		$all   = self::sessions( $rows, $paths, $o['group'] );
		$total = 0;
		$multi = array();
		foreach ( $all as $x ) {
			$total += $x['n'];
			if ( self::pages_in( $x['keys'] ) >= 2 && self::match( $x['keys'][0], $o['start'] ) && self::match( $x['exit'], $o['end'] ) ) {
				$multi[] = $x;
			}
		}
		$m       = 0;
		$len     = 0;
		$reached = 0;
		$entry   = array();
		$exit    = array();
		$pth     = array();
		foreach ( $multi as $x ) {
			$m       += $x['n'];
			$len     += self::pages_in( $x['keys'] ) * $x['n'];
			$reached += $x['goal'] * $x['n'];
			$entry[ $x['keys'][0] ] = ( $entry[ $x['keys'][0] ] ?? 0 ) + $x['n'];
			$exit[ $x['exit'] ]     = ( $exit[ $x['exit'] ] ?? 0 ) + $x['n'];
			$pk                     = implode( "\n", $x['keys'] );
			if ( ! isset( $pth[ $pk ] ) ) {
				$pth[ $pk ] = array( 0, 0 );
			}
			$pth[ $pk ][0] += $x['n'];
			$pth[ $pk ][1] += $x['goal'] * $x['n'];
		}
		arsort( $entry );
		arsort( $exit );
		return array(
			'total'   => $total,
			'multi'   => $m,
			'avg_len' => $m ? round( $len / $m, 1 ) : 0,
			'reached' => $reached,
			'entry'   => $entry,
			'exit'    => $exit,
			'paths'   => $pth,
			'list'    => $multi,
		);
	}

	/**
	 * Yolculuk raporu.
	 *
	 * @param array $o steps (2–6), start, end (yol), goal ("fid:adım" | ""), group (bool), top (yol sayısı).
	 */
	public static function report( $s, $e, $ps, $pe, array $o ) {
		$o = wp_parse_args(
			$o,
			array(
				'steps' => 4,
				'start' => '',
				'end'   => '',
				'goal'  => null,
				'group' => false,
				'top'   => 15,
			)
		);
		$o['steps'] = max( 2, min( 6, (int) $o['steps'] ) );
		$o['top']   = max( 5, min( 100, (int) $o['top'] ) );
		$o['group'] = (bool) $o['group'];
		$o['start'] = self::clean_path( $o['start'] );
		$o['end']   = self::clean_path( $o['end'] );
		$goals      = self::goals();
		$o['goal']  = null === $o['goal'] ? self::default_goal() : (string) $o['goal'];
		if ( ! isset( $goals[ $o['goal'] ] ) ) {
			$o['goal'] = '';
		}

		$now  = self::period( $s, $e, $o );
		$prev = self::period( $ps, $pe, $o );
		$pct  = function ( $a, $b ) {
			return $b ? round( $a / $b * 100, 1 ) : 0;
		};
		$node = function ( $key, $n, $of ) use ( $pct ) {
			return null === $key ? null : array(
				'key'   => $key,
				'label' => self::label( $key ),
				'count' => $n,
				'pct'   => $pct( $n, $of ),
			);
		};
		$top_entry = $now['entry'] ? array_key_first( $now['entry'] ) : null;
		$top_exit  = $now['exit'] ? array_key_first( $now['exit'] ) : null;

		$summary = array(
			'sessions'       => $now['total'],
			'multi'          => $now['multi'],
			'multi_pct'      => $pct( $now['multi'], $now['total'] ),
			'multi_change'   => FLXAN_Stats::change( $now['multi'], $prev['multi'] ),
			'avg_len'        => $now['avg_len'],
			'avg_len_change' => FLXAN_Stats::change( $now['avg_len'], $prev['avg_len'] ),
			'top_entry'      => $node( $top_entry, $top_entry ? $now['entry'][ $top_entry ] : 0, $now['multi'] ),
			'top_exit'       => $node( $top_exit, $top_exit ? $now['exit'][ $top_exit ] : 0, $now['multi'] ),
			'goal_rate'      => '' !== $o['goal'] ? $pct( $now['reached'], $now['multi'] ) : null,
			'goal_reached'   => $now['reached'],
			'goal_change'    => '' !== $o['goal'] ? FLXAN_Stats::change( $pct( $now['reached'], $now['multi'] ), $pct( $prev['reached'], $prev['multi'] ) ) : null,
		);

		return array(
			'options' => $o,
			'goals'   => $goals,
			'summary' => $summary,
			'flow'    => self::flow( $now['list'], $o['steps'] ),
			'paths'   => self::top_paths( $now, $prev, $o['top'] ),
		);
	}

	/** Adım adım akış: her adımda en kalabalık sayfalar, "burada bitti" sayıları ve adımlar arası bağlar. */
	private static function flow( array $list, $steps ) {
		$counts = array_fill( 0, $steps, array() );
		foreach ( $list as $x ) {
			foreach ( array_slice( $x['keys'], 0, $steps ) as $i => $k ) {
				$counts[ $i ][ $k ] = ( $counts[ $i ][ $k ] ?? 0 ) + $x['n'];
			}
		}
		$keep = array();
		foreach ( $counts as $i => $c ) {
			arsort( $c );
			$keep[ $i ] = array_flip( array_slice( array_keys( $c ), 0, self::PER_STEP ) );
		}
		$name  = function ( $i, $k ) use ( $keep ) {
			return isset( $keep[ $i ][ $k ] ) ? $k : '~';
		};
		$nodes = array_fill( 0, $steps, array() );
		$links = array();
		foreach ( $list as $x ) {
			$ks   = array_slice( $x['keys'], 0, $steps );
			$last = count( $ks ) - 1;
			foreach ( $ks as $i => $k ) {
				$nk = $name( $i, $k );
				if ( ! isset( $nodes[ $i ][ $nk ] ) ) {
					$nodes[ $i ][ $nk ] = array( 'count' => 0, 'ends' => 0 );
				}
				$nodes[ $i ][ $nk ]['count'] += $x['n'];
				if ( $i === $last && count( $x['keys'] ) === $i + 1 ) {
					$nodes[ $i ][ $nk ]['ends'] += $x['n'];
				}
				if ( $i < $last ) {
					$lk           = $i . "\t" . $nk . "\t" . $name( $i + 1, $ks[ $i + 1 ] );
					$links[ $lk ] = ( $links[ $lk ] ?? 0 ) + $x['n'];
				}
			}
		}
		$out = array();
		foreach ( $nodes as $i => $ns ) {
			uksort(
				$ns,
				function ( $a, $b ) use ( $ns ) {
					if ( '~' === $a || '~' === $b ) {
						return '~' === $a ? 1 : -1;
					}
					return $ns[ $b ]['count'] <=> $ns[ $a ]['count'];
				}
			);
			$col = array();
			foreach ( $ns as $k => $v ) {
				$col[] = array(
					'key'   => (string) $k,
					'label' => '~' === $k ? __( 'Other pages', 'flx-analytics' ) : self::label( $k ),
					'path'  => '~' === $k || '@' === substr( (string) $k, 0, 1 ) ? '' : rawurldecode( $k ),
					'ev'    => '@' === substr( (string) $k, 0, 1 ),
					'count' => $v['count'],
					'ends'  => $v['ends'],
				);
			}
			$out[] = $col;
		}
		$ls = array();
		foreach ( $links as $lk => $n ) {
			list( $i, $a, $b ) = explode( "\t", $lk );
			$ls[]              = array( 'step' => (int) $i, 'from' => $a, 'to' => $b, 'count' => $n );
		}
		usort(
			$ls,
			function ( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);
		return array( 'steps' => $out, 'links' => $ls );
	}

	/** En sık izlenen yollar (sekmeler istemcide süzülür; bu yüzden geniş liste döner). */
	private static function top_paths( array $now, array $prev, $top ) {
		$p = $now['paths'];
		uasort(
			$p,
			function ( $a, $b ) {
				return $b[0] <=> $a[0];
			}
		);
		$items = array();
		foreach ( array_slice( $p, 0, 200, true ) as $pk => $c ) {
			$keys    = explode( "\n", (string) $pk );
			$pv      = $prev['paths'][ $pk ][0] ?? 0;
			$items[] = array(
				'steps'    => array_map(
					function ( $k ) {
						$ev = '@' === substr( $k, 0, 1 );
						return array( 'key' => $k, 'label' => self::label( $k ), 'path' => $ev ? '' : rawurldecode( $k ), 'ev' => $ev );
					},
					$keys
				),
				'sessions' => $c[0],
				'reached'  => $c[1],
				'share'    => $now['multi'] ? round( $c[0] / $now['multi'] * 100, 1 ) : 0,
				'prev'     => $pv,
				'change'   => FLXAN_Stats::change( $c[0], $pv ),
				'rate'     => $c[0] ? round( $c[1] / $c[0] * 100, 1 ) : 0,
			);
		}
		return array(
			'top'   => $top,
			'total' => count( $p ),
			'items' => $items,
		);
	}

	private static function clean_path( $p ) {
		$p = trim( (string) $p );
		if ( '' === $p ) {
			return '';
		}
		$u = (string) wp_parse_url( $p, PHP_URL_PATH );
		$u = '' !== $u ? $u : $p;
		return '/' . ltrim( implode( '/', array_map( 'rawurlencode', array_map( 'rawurldecode', explode( '/', $u ) ) ) ), '/' );
	}
}
