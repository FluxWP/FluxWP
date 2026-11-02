<?php
/**
 * Cron görev süreleri: wp-cron.php isteğinde çalışan her kancanın süresini ölçer.
 *
 * "WP-Cron ortalama 21 sn" bilgisi tek başına işe yaramaz; hangi görevin (hangi eklentinin) uzun sürdüğü
 * bilinmeli. Kanca başına son/en uzun/ortalama süre flxp_cron_stats seçeneğinde (autoload kapalı) tutulur,
 * istek kaydına da (data.cron) yazılır. Ek sorgu: cron isteği başına bir update_option.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

class FLXP_Cron_Timing {

	const OPTION = 'flxp_cron_stats';
	const MAX    = 80;

	/** @var array hook => [ms, runs, comp] (bu istek) */
	public static $runs = array();

	private static $t0 = array();

	public static function boot() {
		if ( ! wp_doing_cron() || ( defined( 'FLXP_DISABLED' ) && FLXP_DISABLED ) ) {
			return;
		}
		// wp-cron.php görevleri wp-load.php bittikten sonra çalıştırır; o anda vadesi gelenler bellidir.
		add_action( 'wp_loaded', array( __CLASS__, 'attach' ), PHP_INT_MAX );
		add_action( 'shutdown', array( __CLASS__, 'save' ), 1 );
	}

	/** Vadesi gelen kancaların başına ve sonuna ölçüm ekler. */
	public static function attach() {
		$crons = function_exists( '_get_cron_array' ) ? _get_cron_array() : array();
		if ( ! is_array( $crons ) ) {
			return;
		}
		$now   = microtime( true );
		$hooks = array();
		foreach ( $crons as $ts => $list ) {
			if ( $ts > $now ) {
				break; // Dizi zamana göre sıralı.
			}
			foreach ( array_keys( (array) $list ) as $hook ) {
				$hooks[ $hook ] = true;
			}
		}
		foreach ( array_keys( $hooks ) as $hook ) {
			$hook = (string) $hook;
			add_action(
				$hook,
				function () use ( $hook ) {
					FLXP_Cron_Timing::$t0[ $hook ] = microtime( true );
				},
				PHP_INT_MIN
			);
			add_action(
				$hook,
				function () use ( $hook ) {
					FLXP_Cron_Timing::stop( $hook );
				},
				PHP_INT_MAX
			);
		}
	}

	public static function stop( $hook ) {
		if ( ! isset( self::$t0[ $hook ] ) ) {
			return;
		}
		$ms = ( microtime( true ) - self::$t0[ $hook ] ) * 1000;
		unset( self::$t0[ $hook ] );
		if ( ! isset( self::$runs[ $hook ] ) ) {
			self::$runs[ $hook ] = array( 'hook' => $hook, 'ms' => 0, 'runs' => 0, 'comp' => self::component( $hook ) );
		}
		self::$runs[ $hook ]['ms'] += $ms;
		self::$runs[ $hook ]['runs']++;
	}

	/** Bu istekte süre bitmeden kesilen (zaman aşımı) görev varsa onu da say. */
	private static function close_open() {
		foreach ( array_keys( self::$t0 ) as $hook ) {
			self::stop( $hook );
			self::$runs[ $hook ]['cut'] = true;
		}
	}

	/** Bu isteğin listesi (istek kaydı için), uzundan kısaya. */
	public static function current() {
		self::close_open();
		$out = array();
		foreach ( self::$runs as $r ) {
			$r['ms'] = round( $r['ms'], 1 );
			$out[]   = $r;
		}
		usort(
			$out,
			function ( $a, $b ) {
				return $b['ms'] <=> $a['ms'];
			}
		);
		return $out;
	}

	/** Kanca başına kalıcı özet. */
	public static function save() {
		$list = self::current();
		if ( ! $list ) {
			return;
		}
		$all = get_option( self::OPTION, array() );
		$all = is_array( $all ) ? $all : array();
		$now = time();
		foreach ( $list as $r ) {
			$h = $r['hook'];
			$o = isset( $all[ $h ] ) && is_array( $all[ $h ] ) ? $all[ $h ] : array( 'runs' => 0, 'total' => 0, 'max' => 0 );
			// Bir günden eski ortalamayı yumuşat: dünkü ağır bir iş bugünü sonsuza kadar kirletmesin.
			if ( ! empty( $o['at'] ) && $now - (int) $o['at'] > DAY_IN_SECONDS ) {
				$o = array( 'runs' => 0, 'total' => 0, 'max' => 0 );
			}
			$o['runs']  = (int) $o['runs'] + (int) $r['runs'];
			$o['total'] = (float) $o['total'] + (float) $r['ms'];
			$o['max']   = max( (float) $o['max'], (float) $r['ms'] );
			$o['last']  = (float) $r['ms'];
			$o['at']    = $now;
			$o['comp']  = $r['comp'];
			$o['cut']   = ! empty( $r['cut'] );
			$all[ $h ]  = $o;
		}
		// En eski kayıtları at.
		if ( count( $all ) > self::MAX ) {
			uasort(
				$all,
				function ( $a, $b ) {
					return (int) ( $b['at'] ?? 0 ) <=> (int) ( $a['at'] ?? 0 );
				}
			);
			$all = array_slice( $all, 0, self::MAX, true );
		}
		update_option( self::OPTION, $all, false );
	}

	/**
	 * Admin ekranı ve MCP için: kanca başına ortalama/en uzun/son süre, toplam süreye göre sıralı.
	 *
	 * @return array[]
	 */
	public static function stats() {
		$all = get_option( self::OPTION, array() );
		$out = array();
		foreach ( is_array( $all ) ? $all : array() as $hook => $o ) {
			$runs  = max( 1, (int) ( $o['runs'] ?? 0 ) );
			$out[] = array(
				'hook'      => (string) $hook,
				'component' => flxp_component_label( $o['comp'] ?? array() ),
				'runs'      => (int) ( $o['runs'] ?? 0 ),
				'avg_ms'    => (int) round( (float) ( $o['total'] ?? 0 ) / $runs ),
				'max_ms'    => (int) round( (float) ( $o['max'] ?? 0 ) ),
				'last_ms'   => (int) round( (float) ( $o['last'] ?? 0 ) ),
				'total_ms'  => (int) round( (float) ( $o['total'] ?? 0 ) ),
				'last_at'   => (int) ( $o['at'] ?? 0 ),
				'cut'       => ! empty( $o['cut'] ),
			);
		}
		usort(
			$out,
			function ( $a, $b ) {
				return $b['total_ms'] <=> $a['total_ms'];
			}
		);
		return $out;
	}

	public static function reset() {
		delete_option( self::OPTION );
	}

	/** Kancaya bağlı ilk geri çağrının dosyasından eklenti/tema. */
	private static function component( $hook ) {
		global $wp_filter;
		if ( empty( $wp_filter[ $hook ] ) || ! $wp_filter[ $hook ] instanceof WP_Hook ) {
			return array( 'type' => 'other', 'slug' => 'other' );
		}
		foreach ( $wp_filter[ $hook ]->callbacks as $prio => $cbs ) {
			if ( PHP_INT_MIN === $prio || PHP_INT_MAX === $prio ) {
				continue;
			}
			foreach ( $cbs as $cb ) {
				$file = flxp_callback_file( $cb['function'] );
				if ( $file ) {
					return flxp_component_from_file( $file );
				}
			}
		}
		return array( 'type' => 'other', 'slug' => 'other' );
	}
}

/** Bir geri çağrının tanımlandığı dosya (bulunamazsa ''). */
function flxp_callback_file( $fn ) {
	try {
		if ( is_string( $fn ) && false !== strpos( $fn, '::' ) ) {
			$fn = explode( '::', $fn, 2 );
		}
		if ( is_array( $fn ) && 2 === count( $fn ) ) {
			$r = new ReflectionMethod( is_object( $fn[0] ) ? get_class( $fn[0] ) : (string) $fn[0], (string) $fn[1] );
		} elseif ( $fn instanceof Closure || ( is_string( $fn ) && function_exists( $fn ) ) ) {
			$r = new ReflectionFunction( $fn );
		} elseif ( is_object( $fn ) && method_exists( $fn, '__invoke' ) ) {
			$r = new ReflectionMethod( $fn, '__invoke' );
		} else {
			return '';
		}
		return (string) $r->getFileName();
	} catch ( Throwable $e ) { // phpcs:ignore
		return '';
	}
}
