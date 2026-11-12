<?php
/**
 * Flux Ads: Öneriler (optimizer). Etkin Meta kampanyaları günde bir (ve ekran açılınca) kurallarla incelenir:
 * harcayıp ödeme getirmeyeni duraklat, düşük ROAS'ta bütçe kıs, yüksek ROAS'ta bütçe artır, zayıf reklamı duraklat,
 * yorulan kitleye yeni metin. HİÇBİR ŞEY kendiliğinden uygulanmaz: her öneri onaylanınca Meta'ya gider (FLXADS_Manager).
 *
 * @package FLX_Ads
 */

defined( 'ABSPATH' ) || exit;

final class FLXADS_Optimizer {

	const OPT      = 'flxads_suggestions';
	const RUN      = 'flxads_opt_run';
	const MAX      = 150;
	const STEP     = 0.2;  // bütçe adımı (%20: Meta öğrenmesini bozmayacak kadar)
	const FREQ     = 4.0;  // 7 günde kişi başı gösterim; üstü yorgunluk
	const SNOOZE   = 14;   // kapatılan öneri bu kadar gün dönmez
	const COOLDOWN = 7;    // uygulanan öneri bu kadar gün yeniden önerilmez
	/** @var array Son çalıştırmada kullanılan kurallar. */
	private static $rules = array();

	const SALES    = array( 'OUTCOME_SALES', 'CONVERSIONS', 'PRODUCT_CATALOG_SALES', 'OUTCOME_LEADS', 'LEAD_GENERATION' );

	public static function init() {
		add_action( 'flxads_retry', array( __CLASS__, 'maybe_run' ) );
	}

	/** Günde bir (saatlik işten). */
	public static function maybe_run() {
		$r = (array) get_option( self::RUN, array() );
		if ( ! FLXADS_Spend::meta_ready() || ( ! empty( $r['at'] ) && strtotime( $r['at'] . ' UTC' ) > time() - 20 * HOUR_IN_SECONDS ) ) {
			return;
		}
		self::refresh();
	}

	/** Ekran açılınca: son kontrol bir saatten eskiyse. */
	public static function maybe_run_now() {
		$r = (array) get_option( self::RUN, array() );
		if ( FLXADS_Spend::meta_ready() && ( empty( $r['at'] ) || strtotime( $r['at'] . ' UTC' ) < time() - HOUR_IN_SECONDS ) ) {
			self::refresh();
		}
	}

	/** Ayarlardaki hedef ROAS ve "karar için en az harcama" (0 = otomatik). */
	public static function rules( array $rows = array() ) {
		$s      = flxads_settings();
		$target = (float) ( $s['opt_roas'] ?? 2 );
		$min    = (float) ( $s['opt_min_spend'] ?? 0 );
		if ( $min <= 0 ) {
			/* Otomatik: ödeme başı ortalama maliyetin 2 katı (bir ödeme getirmesi beklenecek kadar), yoksa 500. */
			$sp  = array_sum( array_column( $rows, 'spend' ) );
			$pay = array_sum( array_column( $rows, 'payments' ) );
			$min = $pay > 0 ? round( 2 * $sp / $pay, 2 ) : 500.0;
		}
		return array( 'target_roas' => $target > 0 ? $target : 2.0, 'min_spend' => max( 1.0, $min ), 'auto' => (float) ( $s['opt_min_spend'] ?? 0 ) <= 0 );
	}

	public static function all() {
		return array_values( array_filter( (array) get_option( self::OPT, array() ), 'is_array' ) );
	}

	public static function open() {
		return array_values( array_filter( self::all(), static function ( $s ) { return 'open' === $s['status']; } ) );
	}

	public static function get( $id ) {
		foreach ( self::all() as $s ) {
			if ( $s['id'] === (string) $id ) {
				return $s;
			}
		}
		return null;
	}

	private static function store( array $list ) {
		usort( $list, static function ( $a, $b ) { return strcmp( $b['created'], $a['created'] ); } );
		update_option( self::OPT, array_slice( $list, 0, self::MAX ), false );
	}

	public static function last_run() {
		return (array) get_option( self::RUN, array() );
	}

	/**
	 * Kuralları çalıştırır; yeni önerileri ekler, artık geçerli olmayan açık önerileri "gone" yapar.
	 *
	 * @return array|WP_Error [ 'new' => n, 'open' => n ]
	 */
	public static function refresh() {
		$found = self::detect();
		if ( is_wp_error( $found ) ) {
			update_option( self::RUN, array( 'at' => flxads_now(), 'ok' => false, 'message' => $found->get_error_message() ), false );
			return $found;
		}
		$now  = flxads_now();
		$list = self::all();
		$idx  = array();
		foreach ( $list as $i => $s ) {
			$idx[ $s['sig'] ][] = $i;
		}
		$new = array();
		foreach ( $found as $f ) {
			$skip = false;
			foreach ( $idx[ $f['sig'] ] ?? array() as $i ) {
				$s   = $list[ $i ];
				$age = ( time() - strtotime( (string) ( $s['decided'] ?: $s['created'] ) . ' UTC' ) ) / DAY_IN_SECONDS;
				if ( 'open' === $s['status'] ) {
					$list[ $i ]['data']   = $f['data'];
					$list[ $i ]['action'] = $f['action'];
					$list[ $i ]['name']   = $f['name'];
					$list[ $i ]['seen']   = $now;
					$skip                 = true;
				} elseif ( ( 'dismissed' === $s['status'] && $age < self::SNOOZE ) || ( 'applied' === $s['status'] && $age < self::COOLDOWN ) ) {
					$skip = true;
				}
			}
			if ( ! $skip ) {
				$f    += array( 'id' => strtolower( wp_generate_password( 10, false ) ) );
				$new[] = $f;
			}
		}
		$sigs = array_column( $found, 'sig' );
		foreach ( $list as $i => $s ) {
			if ( 'open' === $s['status'] && ! in_array( $s['sig'], $sigs, true ) ) {
				$list[ $i ]['status']  = 'gone';
				$list[ $i ]['decided'] = $now;
			}
		}
		foreach ( $new as $f ) {
			$list[] = $f + array( 'status' => 'open', 'created' => $now, 'seen' => $now, 'decided' => '', 'user' => 0, 'channel' => '', 'result' => '' );
		}
		self::store( $list );
		$open = count( array_filter( $list, static function ( $s ) { return 'open' === $s['status']; } ) );
		update_option( self::RUN, array( 'at' => $now, 'ok' => true, 'new' => count( $new ), 'open' => $open, 'rules' => self::$rules ), false );
		if ( $new ) {
			/**
			 * Yeni reklam önerileri çıktı (ör. Flux AI brifingi ya da bildirim için).
			 *
			 * @param array $new Öneriler.
			 */
			do_action( 'flxads_suggestions_new', $new );
		}
		return array( 'new' => count( $new ), 'open' => $open );
	}

	/**
	 * Kurallar. Son 7 gün; yalnız etkin kampanyalar, harcamaya göre ilk 10'u ayrıntılı.
	 *
	 * @return array|WP_Error
	 */
	public static function detect() {
		$d = FLXADS_Manager::campaigns( '7' );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		$r    = self::rules( $d['rows'] );
		self::$rules = $r;
		$tr   = $r['target_roas'];
		$min  = $r['min_spend'];
		$out  = array();
		$deep = 0;
		foreach ( $d['rows'] as $c ) {
			if ( 'ACTIVE' !== $c['effective'] ) {
				continue;
			}
			$sales = in_array( (string) ( $c['objective'] ?? '' ), self::SALES, true ) || $c['payments'] > 0;
			$base  = array( 'spend' => $c['spend'], 'payments' => $c['payments'], 'income' => $c['income'], 'roas' => $c['roas'], 'target' => $tr, 'min_spend' => $min, 'currency' => $d['currency'], 'base' => $d['base'] );
			$camp  = array( 'campaign_id' => $c['id'], 'campaign' => $c['name'] );
			if ( $sales && $c['spend'] >= $min && 0 === $c['payments'] ) {
				$out[] = self::make( 'pause', 'campaign', $c, $camp, $base, array( 'field' => 'status', 'to' => 'PAUSED' ) );
				continue;
			}
			if ( null !== $c['daily'] && $c['payments'] > 0 && $c['spend'] >= $min && null !== $c['roas'] && $c['roas'] < $tr * 0.5 ) {
				$to    = self::round_budget( $c['daily'] * ( 1 - self::STEP ) );
				$out[] = self::make( 'budget_down', 'campaign', $c, $camp, $base + array( 'daily' => $c['daily'], 'to' => $to ), array( 'field' => 'budget', 'to' => $to ) );
			} elseif ( null !== $c['daily'] && $c['payments'] >= 3 && $c['spend'] >= $min * 0.5 && null !== $c['roas'] && $c['roas'] >= $tr * 1.5 ) {
				$to    = self::round_budget( $c['daily'] * ( 1 + self::STEP ) );
				$out[] = self::make( 'budget_up', 'campaign', $c, $camp, $base + array( 'daily' => $c['daily'], 'to' => $to ), array( 'field' => 'budget', 'to' => $to ) );
			}
			if ( $deep >= 10 ) {
				continue;
			}
			++$deep;
			$x = FLXADS_Manager::campaign( $c['id'], '7', false );
			if ( is_wp_error( $x ) ) {
				continue;
			}
			foreach ( (array) $x['adsets'] as $s ) {
				if ( 'ACTIVE' !== $s['effective'] ) {
					continue;
				}
				if ( null !== $s['frequency'] && $s['frequency'] >= self::FREQ && $s['spend'] > 0 ) {
					$out[] = self::make( 'fatigue', 'adset', $s, $camp, array( 'frequency' => $s['frequency'], 'spend' => $s['spend'], 'ctr' => $s['ctr'], 'currency' => $d['currency'] ), null );
				}
				$live = array_values( array_filter( (array) $s['ads'], static function ( $a ) { return 'ACTIVE' === $a['effective']; } ) );
				if ( count( $live ) < 2 ) {
					continue;
				}
				$clk = array_sum( array_column( $live, 'clicks' ) );
				$imp = array_sum( array_column( $live, 'impressions' ) );
				$avg = $imp > 0 ? 100 * $clk / $imp : 0;
				foreach ( $live as $a ) {
					if ( $avg > 0 && $a['impressions'] >= 1000 && $a['spend'] >= $min * 0.5 && null !== $a['ctr'] && $a['ctr'] < $avg * 0.5 ) {
						$out[] = self::make( 'pause_ad', 'ad', $a, $camp + array( 'adset' => $s['name'] ), array( 'ctr' => $a['ctr'], 'avg_ctr' => round( $avg, 2 ), 'spend' => $a['spend'], 'impressions' => $a['impressions'], 'currency' => $d['currency'] ), array( 'field' => 'status', 'to' => 'PAUSED' ) );
						break; // aynı sette bir turda en çok bir reklam
					}
				}
			}
		}
		return $out;
	}

	private static function make( $type, $level, array $o, array $camp, array $data, $action ) {
		return array(
			'sig'    => $type . ':' . $o['id'],
			'type'   => $type,
			'level'  => $level,
			'object' => (string) $o['id'],
			'name'   => (string) $o['name'],
			'data'   => $data + $camp,
			'action' => $action,
		);
	}

	private static function round_budget( $v ) {
		$v = (float) $v;
		return $v >= 100 ? round( $v / 10 ) * 10 : round( $v, 2 );
	}

	/** Öneri türleri (etiket). */
	public static function types() {
		return array(
			'pause'       => __( 'Pause campaign', 'flx-ads' ),
			'budget_down' => __( 'Lower budget', 'flx-ads' ),
			'budget_up'   => __( 'Raise budget', 'flx-ads' ),
			'pause_ad'    => __( 'Pause weak ad', 'flx-ads' ),
			'fatigue'     => __( 'New ad text', 'flx-ads' ),
		);
	}

	public static function statuses() {
		return array(
			'open'      => __( 'Waiting', 'flx-ads' ),
			'applied'   => __( 'Applied', 'flx-ads' ),
			'dismissed' => __( 'Dismissed', 'flx-ads' ),
			'gone'      => __( 'No longer valid', 'flx-ads' ),
		);
	}

	/** Gerekçe metni (sitenin dilinde). */
	public static function reason( array $s ) {
		$d   = (array) $s['data'];
		$cur = (string) ( $d['currency'] ?? '' );
		switch ( $s['type'] ) {
			case 'pause':
				/* translators: 1: spend, 2: target spend */
				return sprintf( __( 'Spent %1$s in the last 7 days and brought no payment on the site (judged after %2$s).', 'flx-ads' ), flxads_money( $d['spend'], $cur ), flxads_money( $d['min_spend'], $cur ) );
			case 'budget_down':
				/* translators: 1: ROAS, 2: target ROAS, 3: old budget, 4: new budget */
				return sprintf( __( 'Return on ad spend is %1$s, under half of your %2$s target. Lower the daily budget %3$s → %4$s (-20%%) and move money to better campaigns.', 'flx-ads' ), self::x( $d['roas'] ), self::x( $d['target'] ), flxads_money( $d['daily'], $cur ), flxads_money( $d['to'], $cur ) );
			case 'budget_up':
				/* translators: 1: ROAS, 2: payments, 3: old budget, 4: new budget */
				return sprintf( __( 'Return on ad spend is %1$s with %2$s payments, well over your target. Raise the daily budget %3$s → %4$s (+20%%, small enough that Meta keeps learning).', 'flx-ads' ), self::x( $d['roas'] ), number_format_i18n( (int) $d['payments'] ), flxads_money( $d['daily'], $cur ), flxads_money( $d['to'], $cur ) );
			case 'pause_ad':
				/* translators: 1: ad CTR, 2: ad set average CTR, 3: spend */
				return sprintf( __( 'Click rate %1$s%% while the other ads in this ad set average %2$s%%; it has spent %3$s. Pausing it moves the budget to the better ads.', 'flx-ads' ), number_format_i18n( (float) $d['ctr'], 2 ), number_format_i18n( (float) $d['avg_ctr'], 2 ), flxads_money( $d['spend'], $cur ) );
			case 'fatigue':
				/* translators: %s: frequency */
				return sprintf( __( 'People saw these ads %s times each in 7 days; results usually drop after 4. Write new text variants in Studio or widen the audience.', 'flx-ads' ), number_format_i18n( (float) $d['frequency'], 1 ) );
		}
		return '';
	}

	private static function x( $v ) {
		return null === $v ? '—' : number_format_i18n( (float) $v, 1 ) . '×';
	}

	/**
	 * Öneriyi uygular (Meta'ya gider, değişiklik geçmişine yazılır).
	 *
	 * @return array|WP_Error
	 */
	public static function apply( $id, $channel = 'panel' ) {
		$list = self::all();
		foreach ( $list as $i => $s ) {
			if ( $s['id'] !== (string) $id ) {
				continue;
			}
			if ( 'open' !== $s['status'] ) {
				return new WP_Error( 'flxads_opt', __( 'This suggestion is already closed.', 'flx-ads' ) );
			}
			if ( empty( $s['action'] ) ) {
				return new WP_Error( 'flxads_opt', __( 'This suggestion has nothing to change in Meta; open Studio or dismiss it.', 'flx-ads' ) );
			}
			$a = (array) $s['action'];
			$r = 'budget' === $a['field'] ? FLXADS_Manager::set_budget( $s['object'], (float) $a['to'], $channel ) : FLXADS_Manager::set_status( $s['object'], (string) $a['to'], $channel );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$list[ $i ]['status']  = 'applied';
			$list[ $i ]['decided'] = flxads_now();
			$list[ $i ]['user']    = get_current_user_id();
			$list[ $i ]['channel'] = (string) $channel;
			self::store( $list );
			return array( 'id' => $s['id'], 'applied' => true, 'meta' => $r );
		}
		return new WP_Error( 'flxads_opt', __( 'Suggestion not found.', 'flx-ads' ) );
	}

	/** @return true|WP_Error */
	public static function dismiss( $id, $channel = 'panel', $note = '' ) {
		$list = self::all();
		foreach ( $list as $i => $s ) {
			if ( $s['id'] === (string) $id && 'open' === $s['status'] ) {
				$list[ $i ]['status']  = 'dismissed';
				$list[ $i ]['decided'] = flxads_now();
				$list[ $i ]['user']    = get_current_user_id();
				$list[ $i ]['channel'] = (string) $channel;
				$list[ $i ]['result']  = mb_substr( sanitize_text_field( (string) $note ), 0, 200 );
				self::store( $list );
				return true;
			}
		}
		return new WP_Error( 'flxads_opt', __( 'Suggestion not found.', 'flx-ads' ) );
	}

	/** AI / MCP için düz biçim. */
	public static function export( array $s ) {
		return array(
			'id'       => $s['id'],
			'type'     => $s['type'],
			'title'    => self::types()[ $s['type'] ] ?? $s['type'],
			'level'    => $s['level'],
			'object'   => $s['object'],
			'name'     => $s['name'],
			'campaign' => $s['data']['campaign'] ?? '',
			'reason'   => self::reason( $s ),
			'action'   => $s['action'],
			'data'     => $s['data'],
			'status'   => $s['status'],
			'created'  => $s['created'],
			'decided'  => $s['decided'],
		);
	}
}
