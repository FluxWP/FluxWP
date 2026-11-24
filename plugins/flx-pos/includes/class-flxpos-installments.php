<?php
/**
 * Taksit seçenekleri: hesabın elle girilen vade farkı tablosu; sağlayıcının API'si varsa karta (BIN) göre
 * sağlayıcıdan gelen tablo. Sonuç her zaman: taksit sayısı => {total, rate, monthly}.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Installments {

	/**
	 * @param array  $account  Hesap.
	 * @param float  $amount   Peşin tutar.
	 * @param string $currency Para birimi.
	 * @param string $bin      Kartın ilk 6-8 hanesi (yoksa elle tablo).
	 * @return array<int,array{total:float,rate:float,monthly:float}>
	 */
	public static function options( array $account, $amount, $currency, $bin = '' ) {
		$amount = round( (float) $amount, 2 );
		$out    = array(
			1 => array(
				'total'   => $amount,
				'rate'    => 0.0,
				'monthly' => $amount,
			),
		);
		if ( 'TRY' !== strtoupper( (string) $currency ) || $amount <= 0 || $amount < (float) $account['inst_min'] ) {
			return $out;
		}
		$table = array();
		foreach ( (array) $account['installments'] as $n => $rate ) {
			$table[ (int) $n ] = array(
				'rate'  => (float) $rate,
				'total' => round( $amount * ( 1 + (float) $rate / 100 ), 2 ),
			);
		}
		$bin = preg_replace( '/\D/', '', (string) $bin );
		if ( strlen( $bin ) >= 6 && $table ) {
			$key = 'flxpos_inst_' . md5( $account['id'] . '|' . substr( $bin, 0, 8 ) . '|' . $amount );
			$api = get_transient( $key );
			if ( false === $api ) {
				$drv = FLXPOS_Accounts::driver( $account['id'] );
				$api = is_wp_error( $drv ) ? null : $drv->installments( $bin, $amount, $currency );
				$api = is_wp_error( $api ) ? null : $api;
				set_transient( $key, null === $api ? 'none' : $api, HOUR_IN_SECONDS );
			}
			if ( is_array( $api ) && $api ) {
				/* Sağlayıcı bu kart için hangi taksitleri veriyorsa onlar; vade farkı elle tablodaki ya da sağlayıcınınki. */
				$table = array_intersect_key( $table, $api ) + array();
				foreach ( $table as $n => $row ) {
					if ( $row['rate'] <= 0 && ! empty( $api[ $n ]['rate'] ) ) {
						$table[ $n ] = array(
							'rate'  => (float) $api[ $n ]['rate'],
							'total' => round( (float) $api[ $n ]['total'], 2 ),
						);
					}
				}
			}
		}
		ksort( $table );
		foreach ( $table as $n => $row ) {
			if ( $n >= 2 ) {
				$out[ $n ] = array(
					'total'   => $row['total'],
					'rate'    => $row['rate'],
					'monthly' => round( $row['total'] / $n, 2 ),
				);
			}
		}
		return (array) apply_filters( 'flxpos_installments', $out, $account, $amount, $currency, $bin );
	}
}
