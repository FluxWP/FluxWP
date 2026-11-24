<?php
/**
 * POS hesapları: bir sağlayıcı + anahtarlar + model + test/canlı.
 *
 * Option flxpos_accounts: id => hesap. Gizli alanlar (secret) flxpos_encrypt ile şifreli saklanır, ekrana
 * hiç geri yazılmaz (boş bırakılırsa eski değer kalır). Aynı sağlayıcıdan birden çok hesap olabilir
 * (ör. iki ayrı Akbank üye işyeri); her hesap kendi para birimi ve taksit tablosuyla çalışır.
 *
 * @package FLX_POS
 */

defined( 'ABSPATH' ) || exit;

final class FLXPOS_Accounts {

	const OPTION = 'flxpos_accounts';

	/** Hesap varsayılanları. */
	public static function defaults() {
		return array(
			'id'           => '',
			'provider'     => '',
			'label'        => '',
			'enabled'      => 1,
			'test'         => 1,
			'mode'         => '',
			'creds'        => array(),
			'currencies'   => array(),  // boş = sağlayıcının desteklediği hepsi
			'installments' => array(),  // taksit sayısı => vade farkı (%); boş = yalnız peşin
			'inst_min'     => 0,        // taksit için en düşük tutar
			'title'        => '',       // cüzdan/yönlendirmeli yöntemin ödeme sayfasındaki adı
			'display'      => '',       // gösterim (redirect | modal | inline); boş = genel ayar
			'created'      => '',
		);
	}

	/** Ham kayıtlar (şifreli). */
	public static function raw() {
		$a = get_option( self::OPTION, array() );
		return is_array( $a ) ? $a : array();
	}

	/** Tüm hesaplar (anahtarlar şifreli). */
	public static function all() {
		$out = array();
		foreach ( self::raw() as $id => $a ) {
			if ( is_array( $a ) ) {
				$out[ $id ] = array_merge( self::defaults(), $a, array( 'id' => (string) $id ) );
			}
		}
		return $out;
	}

	/**
	 * Panelde kayıtlı olmayan, başka bir eklentinin ayarlarından kurulan hesaplar (ör. EsnekPOS resmi modülü).
	 * Kart yöntemine girmez; yalnız kendi WooCommerce yöntemi ve işlemleri için kullanılır.
	 */
	public static function virtual() {
		$out = array();
		foreach ( (array) apply_filters( 'flxpos_virtual_accounts', array() ) as $id => $a ) {
			if ( is_array( $a ) ) {
				$out[ (string) $id ] = array_merge( self::defaults(), $a, array( 'id' => (string) $id, 'virtual' => 1 ) );
			}
		}
		return $out;
	}

	/** Etkin ve sürücüsü olan hesaplar. */
	public static function active() {
		return array_filter(
			self::all(),
			static function ( $a ) {
				$p = FLXPOS_Providers::get( $a['provider'] );
				return ! empty( $a['enabled'] ) && $p && '' !== $p['driver'];
			}
		);
	}

	/** Bir hesap; $decrypt true ise anahtarlar açılmış. */
	public static function get( $id, $decrypt = false ) {
		$all = self::all();
		if ( ! isset( $all[ $id ] ) ) {
			$v = self::virtual();
			return $v[ $id ] ?? null; // anahtarlar zaten açık
		}
		$a = $all[ $id ];
		if ( $decrypt ) {
			$p = FLXPOS_Providers::get( $a['provider'] );
			foreach ( (array) ( $p['fields'] ?? array() ) as $k => $f ) {
				if ( 'secret' === $f['type'] && isset( $a['creds'][ $k ] ) ) {
					$a['creds'][ $k ] = flxpos_decrypt( $a['creds'][ $k ] );
				}
			}
		}
		return $a;
	}

	/**
	 * Kaydet (ekle/güncelle). Gizli alan boş gelirse eski değer kalır.
	 *
	 * @return string|WP_Error Hesap kimliği.
	 */
	public static function save( array $in ) {
		$p = FLXPOS_Providers::get( (string) ( $in['provider'] ?? '' ) );
		if ( ! $p ) {
			return new WP_Error( 'flxpos_provider', __( 'Sağlayıcı seçin.', 'flx-pos' ) );
		}
		$raw = self::raw();
		$id  = sanitize_key( (string) ( $in['id'] ?? '' ) );
		$old = ( '' !== $id && isset( $raw[ $id ] ) ) ? (array) $raw[ $id ] : array();
		if ( '' === $id || ! isset( $raw[ $id ] ) ) {
			$id = $p['id'] . '_' . strtolower( wp_generate_password( 4, false ) );
		}
		$creds = array();
		foreach ( (array) $p['fields'] as $k => $f ) {
			$v = isset( $in['creds'][ $k ] ) ? trim( (string) $in['creds'][ $k ] ) : '';
			if ( 'secret' === $f['type'] ) {
				$creds[ $k ] = '' !== $v ? flxpos_encrypt( $v ) : (string) ( $old['creds'][ $k ] ?? '' );
			} else {
				$creds[ $k ] = sanitize_text_field( '' !== $v ? $v : (string) $f['default'] );
			}
		}
		foreach ( array_keys( (array) ( $p['endpoints']['live'] ?? array() ) ) as $ep ) {
			$k = 'url_' . $ep;
			if ( ! empty( $in['creds'][ $k ] ) ) {
				$creds[ $k ] = esc_url_raw( trim( (string) $in['creds'][ $k ] ), array( 'https' ) );
			}
		}
		$mode = (string) ( $in['mode'] ?? '' );
		if ( ! isset( $p['modes'][ $mode ] ) ) {
			$mode = (string) key( $p['modes'] );
		}
		$inst = array();
		foreach ( (array) ( $in['installments'] ?? array() ) as $n => $rate ) {
			$n = (int) $n;
			if ( $n >= 2 && $n <= 36 && '' !== trim( (string) $rate ) ) {
				$inst[ $n ] = round( max( 0, (float) str_replace( ',', '.', (string) $rate ) ), 2 );
			}
		}
		ksort( $inst );
		$curs = array_values( array_intersect( array_map( 'strtoupper', (array) ( $in['currencies'] ?? array() ) ), (array) $p['currencies'] ) );
		$a    = array(
			'provider'     => $p['id'],
			'label'        => sanitize_text_field( (string) ( $in['label'] ?? '' ) ),
			'enabled'      => empty( $in['enabled'] ) ? 0 : 1,
			'test'         => empty( $in['test'] ) ? 0 : 1,
			'mode'         => $mode,
			'creds'        => $creds,
			'currencies'   => $curs,
			'installments' => $inst,
			'inst_min'     => max( 0, (float) ( $in['inst_min'] ?? 0 ) ),
			'title'        => sanitize_text_field( (string) ( $in['title'] ?? '' ) ),
			'display'      => isset( flxpos_display_modes()[ (string) ( $in['display'] ?? '' ) ] ) ? (string) $in['display'] : '',
			'created'      => (string) ( $old['created'] ?? gmdate( 'c' ) ),
		);
		$raw[ $id ] = $a;
		update_option( self::OPTION, $raw, false );
		do_action( 'flxpos_account_saved', $id, $a );
		return $id;
	}

	public static function delete( $id ) {
		$raw = self::raw();
		unset( $raw[ $id ] );
		update_option( self::OPTION, $raw, false );
	}

	/** Görünen ad. */
	public static function label( array $a ) {
		$p    = FLXPOS_Providers::get( $a['provider'] );
		$name = '' !== $a['label'] ? $a['label'] : ( $p ? $p['name'] : $a['provider'] );
		return $name . ( ! empty( $a['test'] ) ? ' · ' . __( 'test', 'flx-pos' ) : '' );
	}

	/** Hesabın sürücüsü. */
	public static function driver( $id ) {
		$a = is_array( $id ) ? $id : self::get( $id, true );
		if ( ! $a ) {
			return new WP_Error( 'flxpos_account', __( 'POS hesabı bulunamadı.', 'flx-pos' ) );
		}
		$p = FLXPOS_Providers::get( $a['provider'] );
		if ( ! $p || '' === $p['driver'] ) {
			return new WP_Error( 'flxpos_driver', __( 'Bu sağlayıcı için henüz sürücü yok.', 'flx-pos' ) );
		}
		$cls = FLXPOS_Providers::driver_class( $p['driver'] );
		if ( '' === $cls ) {
			return new WP_Error( 'flxpos_driver', __( 'Bu sağlayıcı için henüz sürücü yok.', 'flx-pos' ) );
		}
		return new $cls( $p, $a );
	}

	/** Kartı sitede alan hesaplar mı? (kart yöntemi için) */
	public static function takes_card( array $a ) {
		$p = FLXPOS_Providers::get( $a['provider'] );
		return $p && FLXPOS_Providers::needs_card( $p, $a['mode'] ? $a['mode'] : (string) key( $p['modes'] ) );
	}

	/** Bu para birimini kabul eden hesap mı? */
	public static function accepts( array $a, $currency ) {
		$p    = FLXPOS_Providers::get( $a['provider'] );
		$curs = $a['currencies'] ? $a['currencies'] : (array) ( $p['currencies'] ?? array() );
		return in_array( strtoupper( (string) $currency ), $curs, true );
	}

	/**
	 * Kart yöntemini işleyecek hesap: para birimi yönlendirmesi, seçili kart hesabı, yoksa ilk uygun kart hesabı.
	 *
	 * @return array|null Hesap (şifreli).
	 */
	public static function for_card( $currency ) {
		$s      = flxpos_settings();
		$all    = self::active();
		$routes = (array) $s['currency_routes'];
		$cur    = strtoupper( (string) $currency );
		$pick   = array();
		if ( ! empty( $routes[ $cur ] ) && isset( $all[ $routes[ $cur ] ] ) ) {
			$pick[] = $all[ $routes[ $cur ] ];
		}
		if ( '' !== (string) $s['card_account'] && isset( $all[ $s['card_account'] ] ) ) {
			$pick[] = $all[ $s['card_account'] ];
		}
		foreach ( $all as $a ) {
			$p = FLXPOS_Providers::get( $a['provider'] );
			if ( $p && 'wallet' !== $p['group'] ) {
				$pick[] = $a;
			}
		}
		foreach ( $pick as $a ) {
			if ( self::accepts( $a, $cur ) ) {
				return $a;
			}
		}
		return null;
	}

	/** Cüzdan/ayrı yöntem olarak ödeme sayfasında görünen hesaplar. */
	public static function wallets() {
		return array_filter(
			self::active(),
			static function ( $a ) {
				$p = FLXPOS_Providers::get( $a['provider'] );
				return $p && 'wallet' === $p['group'];
			}
		);
	}
}
