<?php
/**
 * Ayarlar: tek seçenek (flxy_settings), varsayılanlarla tamamlanır.
 */

defined( 'ABSPATH' ) || exit;

final class FLXY_Settings {

	const OPTION = 'flxy_settings';

	public static function defaults(): array {
		return array(
			'quota_gb'            => 10,
			'pre_scope'           => 'all',   // all | flx | off.
			'pre_keep_days'       => 7,
			'schedule'            => 'off',   // off | daily | weekly.
			'schedule_hour'       => 3,
			'schedule_day'        => 1,       // Haftalıkta gün: 0 = Pazar … 6 = Cumartesi.
			'schedule_keep'       => 4,
			'include_core'        => 0,
			'exclude'             => '',
			'safety_restore'      => 1,
			'delete_on_uninstall' => 0,
		);
	}

	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/** Gelen değerleri doğrular, kaydeder ve son hali döndürür. */
	public static function save( array $in ): array {
		$d   = self::defaults();
		$out = array(
			'quota_gb'            => max( 0.1, min( 10000, round( (float) str_replace( ',', '.', (string) ( $in['quota_gb'] ?? $d['quota_gb'] ) ), 2 ) ) ),
			'pre_scope'           => in_array( $in['pre_scope'] ?? '', array( 'all', 'flx', 'off' ), true ) ? $in['pre_scope'] : $d['pre_scope'],
			'pre_keep_days'       => max( 1, min( 365, (int) ( $in['pre_keep_days'] ?? $d['pre_keep_days'] ) ) ),
			'schedule'            => in_array( $in['schedule'] ?? '', array( 'off', 'daily', 'weekly' ), true ) ? $in['schedule'] : $d['schedule'],
			'schedule_hour'       => max( 0, min( 23, (int) ( $in['schedule_hour'] ?? $d['schedule_hour'] ) ) ),
			'schedule_day'        => max( 0, min( 6, (int) ( $in['schedule_day'] ?? $d['schedule_day'] ) ) ),
			'schedule_keep'       => max( 1, min( 100, (int) ( $in['schedule_keep'] ?? $d['schedule_keep'] ) ) ),
			'include_core'        => empty( $in['include_core'] ) ? 0 : 1,
			'exclude'             => self::clean_excludes( (string) ( $in['exclude'] ?? '' ) ),
			'safety_restore'      => empty( $in['safety_restore'] ) ? 0 : 1,
			'delete_on_uninstall' => empty( $in['delete_on_uninstall'] ) ? 0 : 1,
		);
		update_option( self::OPTION, $out, false );
		return $out;
	}

	/** Hariç tutma satırları: ABSPATH'e göre yol veya * içeren kalıp; "..", boş satır ve mutlak yollar atılır. */
	/**
	 * Saklama süresi değişince süreli, kilitsiz güncelleme öncesi yedeklerin bitiş tarihini yeniden hesaplar.
	 * Süresiz (keep_until = null) ve kilitli yedeklere dokunmaz.
	 */
	public static function apply_pre_keep_days(): int {
		$days = (int) self::get( 'pre_keep_days' );
		$n    = 0;
		foreach ( FLXY_Storage::records() as $r ) {
			if ( 'pre_update' !== $r['type'] || $r['locked'] || null === $r['keep_until'] ) {
				continue;
			}
			$until = (int) $r['created'] + $days * DAY_IN_SECONDS;
			if ( $until !== (int) $r['keep_until'] ) {
				FLXY_Storage::patch( $r['id'], array( 'keep_until' => $until ) );
				++$n;
			}
		}
		return $n;
	}

	public static function clean_excludes( string $text ): string {
		$lines = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( str_replace( '\\', '/', $line ) );
			$line = trim( $line, '/' );
			if ( '' === $line || false !== strpos( $line, '..' ) ) {
				continue;
			}
			$lines[] = sanitize_text_field( $line );
		}
		return implode( "\n", array_unique( $lines ) );
	}

	/** @return string[] */
	public static function exclude_list(): array {
		$text = (string) self::get( 'exclude' );
		return '' === $text ? array() : explode( "\n", $text );
	}

	public static function quota_bytes(): int {
		return (int) round( (float) self::get( 'quota_gb' ) * 1024 * 1024 * 1024 );
	}
}
