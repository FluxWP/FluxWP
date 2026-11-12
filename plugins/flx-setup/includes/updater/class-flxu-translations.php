<?php
/**
 * Çeviri güncellemeleri: kurulmuş çeviri tekrar "güncelleme var" görünmesin.
 *
 * WordPress.org hangi çevirinin kurulu olduğunu yalnız .po dosyasının PO-Revision-Date başlığından anlar.
 * Paket yeni biçimle (.l10n.php / .mo) kurulup .po gelmediyse ya da tarih eşleşmediyse aynı çeviri her
 * kontrolde yeniden önerilir; "güncellendi" dense de satır hep "Güncelleme var" kalır.
 * Burada önerilen her çeviri, sitedeki dosyanın tarihiyle karşılaştırılır; kurulu olan aynı ya da
 * daha yeniyse öneri gizlenir. Gerçekten yeni olan çeviri dokunulmadan kalır.
 *
 * @package flx-setup
 */

defined( 'ABSPATH' ) || exit;

final class FLXU_Translations {

	/** @var array<string,array> Tür başına kurulu çeviri tarihleri (istek içinde önbellek). */
	private static $installed = array();

	public static function init(): void {
		foreach ( array( 'update_core', 'update_plugins', 'update_themes' ) as $t ) {
			add_filter( 'site_transient_' . $t, array( __CLASS__, 'filter' ), PHP_INT_MAX - 1 );
		}
		// Çeviri dosyaları değişince tarih önbelleği boşalsın.
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush' ), 1, 0 );
	}

	public static function flush(): void {
		self::$installed = array();
	}

	/**
	 * @param mixed $value Güncelleme verisi.
	 * @return mixed
	 */
	public static function filter( $value ) {
		if ( ! is_object( $value ) || empty( $value->translations ) || ! is_array( $value->translations ) ) {
			return $value;
		}
		$keep = array();
		foreach ( $value->translations as $t ) {
			if ( ! self::installed_is_current( (array) $t ) ) {
				$keep[] = $t;
			}
		}
		if ( count( $keep ) !== count( $value->translations ) ) {
			$value               = clone $value;
			$value->translations = $keep;
		}
		return $value;
	}

	/** Önerilen çeviri sitede aynı ya da daha yeni tarihle kurulu mu? */
	public static function installed_is_current( array $t ): bool {
		$type    = (string) ( $t['type'] ?? '' );
		$slug    = (string) ( $t['slug'] ?? '' );
		$lang    = (string) ( $t['language'] ?? '' );
		$updated = strtotime( (string) ( $t['updated'] ?? '' ) );
		if ( '' === $lang || ! $updated || ! in_array( $type, array( 'core', 'plugin', 'theme' ), true ) ) {
			return false;
		}
		if ( 'core' === $type ) {
			$slug = 'default';
		}
		$have = self::installed_time( $type, $slug, $lang );
		return $have && $have >= $updated;
	}

	/** Kurulu çevirinin tarihi (.po, yoksa .l10n.php başlığı); bulunamazsa 0. */
	public static function installed_time( string $type, string $slug, string $lang ): int {
		$dir = array(
			'core'   => '',
			'plugin' => 'plugins',
			'theme'  => 'themes',
		)[ $type ] ?? null;
		if ( null === $dir || '' === $slug || ! preg_match( '/^[A-Za-z0-9_@.-]+$/D', $lang . $slug ) ) {
			return 0;
		}
		if ( ! isset( self::$installed[ $type ] ) ) {
			self::$installed[ $type ] = function_exists( 'wp_get_installed_translations' ) ? (array) wp_get_installed_translations( 'core' === $type ? 'core' : $dir ) : array();
		}
		$po = (string) ( self::$installed[ $type ][ $slug ][ $lang ]['PO-Revision-Date'] ?? '' );
		$ts = '' !== $po ? (int) strtotime( $po ) : 0;
		if ( $ts ) {
			return $ts;
		}
		// .po yok: yeni biçim .l10n.php başlığına bak (WordPress bu dosyayı zaten include ile yükler).
		$base = WP_LANG_DIR . ( '' !== $dir ? '/' . $dir : '' ) . '/' . ( 'default' === $slug ? '' : $slug . '-' ) . $lang . '.l10n.php';
		if ( is_readable( $base ) ) {
			$data = include $base; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			if ( is_array( $data ) ) {
				foreach ( array( 'po-revision-date', 'translation-revision-date' ) as $k ) {
					if ( ! empty( $data[ $k ] ) && is_string( $data[ $k ] ) ) {
						$ts = (int) strtotime( $data[ $k ] );
						if ( $ts ) {
							return $ts;
						}
					}
				}
			}
		}
		return 0;
	}
}
