<?php
/**
 * Sistem denetimi satırları (ortak `flx_system_checks` filtresi). Ucuz denetimler; dışarı istek yok.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks',
	static function ( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		$c      = static function ( $id, $label, $status, $message, $action = '' ) {
			return array(
				'id'           => 'flxpos.' . $id,
				'source'       => 'Flux POS',
				'group'        => 'Config',
				'label'        => $label,
				'status'       => $status,
				'message'      => $message,
				'action_url'   => '' !== $action ? $action : flxpos_admin_url(),
				'action_label' => __( 'POS hesapları', 'flx-pos' ),
			);
		};
		try {
			$acc = FLXPOS_Accounts::active();
			if ( ! $acc ) {
				$checks[] = $c( 'accounts', __( 'Sanal POS', 'flx-pos' ), 'warning', __( 'Etkin POS hesabı yok; kartla ödeme alınmıyor.', 'flx-pos' ) );
			} else {
				$test = array_filter( $acc, static fn( $a ) => ! empty( $a['test'] ) );
				if ( $test ) {
					/* translators: %s: hesap adları */
					$checks[] = $c( 'test', __( 'Sanal POS', 'flx-pos' ), 'warning', sprintf( __( 'Test modunda hesap var, gerçek para çekilmez: %s', 'flx-pos' ), implode( ', ', array_map( array( 'FLXPOS_Accounts', 'label' ), $test ) ) ) );
				} else {
					/* translators: %d: hesap sayısı */
					$checks[] = $c( 'accounts', __( 'Sanal POS', 'flx-pos' ), 'ok', sprintf( _n( '%d hesap canlı modda etkin.', '%d hesap canlı modda etkin.', count( $acc ), 'flx-pos' ), count( $acc ) ) );
				}
			}
			if ( ! is_ssl() && 0 !== strpos( home_url(), 'https://' ) ) {
				$checks[] = $c( 'ssl', 'HTTPS', 'error', __( 'Site HTTPS değil; bankalar dönüş adresine kart sonucu göndermez, kart formu güvensiz olur.', 'flx-pos' ) );
			}
			if ( ! function_exists( 'sodium_crypto_secretbox' ) && ! function_exists( 'openssl_encrypt' ) ) {
				$checks[] = $c( 'crypto', __( 'Anahtar şifreleme', 'flx-pos' ), 'error', __( 'PHP sodium ya da openssl yok; POS anahtarları şifrelenemiyor.', 'flx-pos' ) );
			}
			if ( ! defined( 'FLXPOS_KEY' ) ) {
				$checks[] = $c( 'key', __( 'Anahtar şifreleme', 'flx-pos' ), 'info', __( 'Anahtarlar WordPress tuzlarıyla şifreli. wp-config.php tuzları değişirse POS anahtarlarını yeniden girin.', 'flx-pos' ) );
			}
		} catch ( Throwable $e ) {
			/* translators: %s: hata */
			$checks[] = $c( 'broken', 'Flux POS', 'error', sprintf( __( 'Denetim çalışmadı: %s', 'flx-pos' ), $e->getMessage() ) );
		}
		return $checks;
	},
	12
);
