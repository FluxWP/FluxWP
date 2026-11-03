<?php
/**
 * Genel API: başka eklentiler (ör. FluxWP Panel) function_exists ile kontrol edip çağırır.
 * Belge: API.md.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'flxy_is_ready' ) ) {
	/**
	 * Yedek alınabilir mi: ZipArchive + zlib var, depolama klasörü yazılabilir, veritabanı geri yüklemesi sürmüyor.
	 */
	function flxy_is_ready(): bool {
		if ( ! class_exists( 'ZipArchive' ) || ! function_exists( 'gzopen' ) || ! FLXY_Storage::is_writable() ) {
			return false;
		}
		$job = FLXY_Runner::load_restore();
		return ! ( $job && ! in_array( $job['phase'], array( 'done', 'failed' ), true ) && ! empty( $job['db'] ) );
	}
}

if ( ! function_exists( 'flxy_backup_create' ) ) {
	/**
	 * Yedek oluşturur.
	 *
	 * @param array $args {
	 *     @type string     $type      full | db | files | pre_update | manual (varsayılan manual).
	 *     @type string     $label     Görünen ad (boşsa türden/bağlamdan üretilir).
	 *     @type bool       $db        Veritabanı dahil mi (varsayılan: türe göre).
	 *     @type string[]   $paths     ABSPATH'e göre yollar (ör. wp-content/plugins/flx-forms).
	 *     @type array      $context   Serbest bağlam; source, packages[{type, slug, name, from_version, to_version}].
	 *     @type int|null   $keep_days Gün sayısı; null = kilitli (asla otomatik silinmez); 0 = süresiz; anahtar yoksa varsayılan.
	 *     @type bool       $sync      true = bu istekte bitir; false = arka planda başlat (kayıt status=running döner).
	 * }
	 * @return array|WP_Error Kayıt.
	 */
	function flxy_backup_create( array $args ) {
		return FLXY_Backup::create( $args );
	}
}

if ( ! function_exists( 'flxy_backup_get' ) ) {
	function flxy_backup_get( string $id ): ?array {
		return FLXY_Storage::get( $id );
	}
}

if ( ! function_exists( 'flxy_backup_list' ) ) {
	/**
	 * Kayıtlar, yeniden eskiye.
	 *
	 * @param array $args type (string|string[]), status, context_slug (bağlamdaki herhangi bir paket slug'ı), limit.
	 */
	function flxy_backup_list( array $args = array() ): array {
		$out   = array();
		$types = isset( $args['type'] ) ? (array) $args['type'] : array();
		foreach ( FLXY_Storage::records() as $r ) {
			if ( $types && ! in_array( $r['type'], $types, true ) ) {
				continue;
			}
			if ( ! empty( $args['status'] ) && $r['status'] !== $args['status'] ) {
				continue;
			}
			if ( ! empty( $args['context_slug'] ) ) {
				$slugs = array();
				foreach ( (array) ( $r['context']['packages'] ?? array() ) as $p ) {
					$slugs[] = (string) ( $p['slug'] ?? '' );
				}
				if ( ! in_array( (string) $args['context_slug'], $slugs, true ) ) {
					continue;
				}
			}
			$out[] = $r;
			if ( ! empty( $args['limit'] ) && count( $out ) >= (int) $args['limit'] ) {
				break;
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'flxy_backup_delete' ) ) {
	function flxy_backup_delete( string $id ): bool {
		return FLXY_Storage::delete( $id );
	}
}

if ( ! function_exists( 'flxy_backup_set_keep' ) ) {
	/**
	 * Saklama süresini değiştirir.
	 *
	 * @param int|null $days null = kilitle; 0 = kilidi aç, süresiz; n = kilidi aç, bugünden n gün sakla.
	 */
	function flxy_backup_set_keep( string $id, $days ): bool {
		if ( ! FLXY_Storage::get( $id ) ) {
			return false;
		}
		if ( null === $days ) {
			return (bool) FLXY_Storage::patch( $id, array( 'locked' => true ) );
		}
		$days = (int) $days;
		return (bool) FLXY_Storage::patch(
			$id,
			array(
				'locked'     => false,
				'keep_until' => $days > 0 ? time() + $days * DAY_IN_SECONDS : null,
			)
		);
	}
}

if ( ! function_exists( 'flxy_pre_update_keep_days' ) ) {
	/** Güncelleme öncesi yedeklerin saklama süresi (gün). */
	function flxy_pre_update_keep_days(): int {
		return (int) FLXY_Settings::get( 'pre_keep_days' );
	}
}

if ( ! function_exists( 'flxy_set_pre_update_keep_days' ) ) {
	/**
	 * Güncelleme öncesi yedeklerin saklama süresini değiştirir; süreli, kilitsiz mevcut güncelleme
	 * yedeklerine de uygulanır (oluşturulma + gün) ve süresi dolanlar hemen silinir.
	 *
	 * @return array days (kaydedilen), deleted (silinen yedek sayısı)
	 */
	function flxy_set_pre_update_keep_days( int $days ): array {
		$saved = FLXY_Settings::save( array_merge( FLXY_Settings::all(), array( 'pre_keep_days' => $days ) ) );
		FLXY_Settings::apply_pre_keep_days();
		$deleted = FLXY_Cron::cleanup();
		return array(
			'days'    => (int) $saved['pre_keep_days'],
			'deleted' => count( $deleted ),
		);
	}
}

if ( ! function_exists( 'flxy_backup_restore' ) ) {
	/**
	 * Bu istekte geri yükler.
	 *
	 * @param array $parts db (bool), paths (ABSPATH'e göre; yedeğin yolları veya altları), safety (bool, isteğe bağlı).
	 * @return true|WP_Error
	 */
	function flxy_backup_restore( string $id, array $parts ) {
		return FLXY_Restore::run_sync( $id, $parts );
	}
}

if ( ! function_exists( 'flxy_date' ) ) {
	/** Zaman damgasını site saat diliminde, WP biçimiyle yazar (date_i18n). */
	function flxy_date( int $ts, string $format = '' ): string {
		if ( '' === $format ) {
			$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}
		$offset = wp_timezone()->getOffset( new DateTime( '@' . $ts ) );
		return date_i18n( $format, $ts + $offset );
	}
}
