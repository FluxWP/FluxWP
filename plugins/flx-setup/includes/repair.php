<?php
/**
 * Bir kerelik onarım: Flux Setup 1.0.4'ten önce sihirbaz ve paket ekranı eklentileri
 * activate_plugin( ..., silent = true ) ile etkinleştiriyordu. Bu yüzden paketlerin
 * etkinleştirme kancaları (tablo oluşturma, varsayılan ayarlar) hiç çalışmadı.
 * Etkin her Flux paketinin etkinleştirme kancası bir kez çalıştırılır.
 *
 * @package FluxSetup
 */

defined( 'ABSPATH' ) || exit;

const FLXK_REPAIR_OPTION = 'flxk_activation_repaired';

/** Onarılacak paket mi? Varsayılan: flx- ile başlayan eklentiler. Filtre: flxk_repair_plugin. */
function flxk_repair_wants( string $file ): bool {
	$slug = dirname( $file );
	$want = 0 === strpos( $slug, 'flx-' ) && 'flx-setup' !== $slug;
	return (bool) apply_filters( 'flxk_repair_plugin', $want, $file );
}

/**
 * Etkin Flux paketlerinin etkinleştirme kancalarını bir kez çalıştırır.
 * Kancalar dbDelta / add_option gibi tekrar çalışmaya dayanıklı işler yapar; yine de her paket yalnız bir kez denenir.
 *
 * @return array<string,bool> dosya => başarılı mı
 */
function flxk_repair_activation_hooks(): array {
	$done = (array) get_option( FLXK_REPAIR_OPTION, array() );
	$out  = array();
	foreach ( (array) get_option( 'active_plugins', array() ) as $file ) {
		$file = (string) $file;
		if ( isset( $done[ $file ] ) || ! flxk_repair_wants( $file ) ) {
			continue;
		}
		$done[ $file ] = time();
		update_option( FLXK_REPAIR_OPTION, $done, false ); // Önce işaretle: kanca ölümcül hata verirse döngüye girmesin.
		$ok = true;
		ob_start();
		try {
			do_action( 'activate_' . $file, false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		} catch ( \Throwable $e ) {
			$ok = false;
		}
		ob_end_clean();
		$out[ $file ] = $ok;
	}
	return $out;
}

add_action(
	'admin_init',
	static function () {
		if ( wp_doing_ajax() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( version_compare( (string) get_option( 'flxk_repair_version', '0' ), '1.0.4', '>=' ) ) {
			return;
		}
		update_option( 'flxk_repair_version', FLXK_VERSION, false );
		flxk_repair_activation_hooks();
	}
);
