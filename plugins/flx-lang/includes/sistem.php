<?php
/**
 * Ortak sistem kontrolü (flx_system_checks). Dış HTTP yok.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks_versions',
	function ( $v ) {
		$v['flx-lang'] = FLXL_VERSION;
		return $v;
	}
);

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		$src    = __( 'Flux Lang', 'flx-lang' );
		$url    = admin_url( 'options-general.php?page=flx-lang' );
		$langs  = flxl_languages();

		$checks[] = array(
			'id'           => 'flxl.languages',
			'source'       => $src,
			'group'        => 'Config',
			'label'        => __( 'Diller', 'flx-lang' ),
			'status'       => count( $langs ) > 1 ? 'ok' : 'info',
			'message'      => count( $langs ) > 1
				? implode( ' · ', array_map( function ( $l ) {
					return $l['name'] . ' (' . ( $l['code'] === flxl_default_language() ? '/' : '/' . $l['code'] . '/' ) . ')';
				}, $langs ) )
				: __( 'Yalnız varsayılan dil etkin; site tek dilli.', 'flx-lang' ),
			'action_url'   => $url,
			'action_label' => __( 'Diller', 'flx-lang' ),
		);

		// Çekirdek dil paketleri: İngilizce dışındaki dillerde WordPress/WooCommerce çevirisi kurulu olmalı.
		$installed = function_exists( 'get_available_languages' ) ? get_available_languages() : array();
		$missing   = array();
		foreach ( $langs as $code => $l ) {
			$loc = flxl_language_locale( $code );
			if ( 0 !== strpos( $loc, 'en_' ) && ! in_array( $loc, $installed, true ) ) {
				$missing[] = $l['name'] . ' (' . $loc . ')';
			}
		}
		$checks[] = array(
			'id'           => 'flxl.core_lang',
			'source'       => $src,
			'group'        => 'Config',
			'label'        => __( 'WordPress dil paketleri', 'flx-lang' ),
			'status'       => $missing ? 'warning' : 'ok',
			'message'      => $missing
				? sprintf(
					/* translators: %s: language list */
					__( 'Kurulu değil: %s. Ayarlar › Genel › Site dili listesinden bir kez seçip kaydedin (paket iner), sonra eski dile dönün.', 'flx-lang' ),
					implode( ', ', $missing )
				)
				: __( 'Etkin dillerin hepsinin WordPress çevirisi var.', 'flx-lang' ),
			'action_url'   => admin_url( 'options-general.php' ),
			'action_label' => __( 'Genel ayarlar', 'flx-lang' ),
		);

		// Çeviri kapsamı (yayındaki varsayılan dil içeriği).
		foreach ( $langs as $code => $l ) {
			if ( $code === flxl_default_language() ) {
				continue;
			}
			$total = new WP_Query( array( 'post_type' => array( 'page' ), 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids', 'flxl_lang' => flxl_default_language() ) );
			$done  = count( array_filter( flxl_default_ids_translated_to( $code ), function ( $id ) {
				return 'page' === get_post_type( $id );
			} ) );
			$all   = max( 0, (int) $total->found_posts - count( flxl_shared_ids() ) );
			$checks[] = array(
				'id'           => 'flxl.coverage.' . $code,
				'source'       => $src,
				'group'        => 'Config',
				/* translators: %s: language name */
				'label'        => sprintf( __( 'Sayfa çevirileri: %s', 'flx-lang' ), $l['name'] ),
				'status'       => 'info',
				'message'      => sprintf(
					/* translators: 1: translated count, 2: total, 3: fallback info */
					__( '%1$d / %2$d sayfa çevrildi. %3$s', 'flx-lang' ),
					$done,
					$all,
					flxl_fallback() ? __( 'Çevrilmeyenler Türkçe gösteriliyor (kanonik adres Türkçe).', 'flx-lang' ) : __( 'Çevrilmeyenler bu dilde gizli.', 'flx-lang' )
				),
				'action_url'   => admin_url( 'edit.php?post_type=page&flxl_filter=' . flxl_default_language() ),
				'action_label' => __( 'Sayfalar', 'flx-lang' ),
			);
		}

		// Ülke bilgisi (ziyaretçi önerisi).
		if ( 'off' !== flxl_settings()['suggest'] ) {
			$seen = get_option( 'flxl_geo_seen' );
			$src2 = is_array( $seen ) ? (string) $seen['source'] : '';
			$checks[] = array(
				'id'           => 'flxl.geo',
				'source'       => $src,
				'group'        => 'Config',
				'label'        => __( 'Ziyaretçi ülkesi', 'flx-lang' ),
				'status'       => '' === $src2 ? 'info' : ( 'none' === $src2 ? 'info' : 'ok' ),
				'message'      => '' === $src2
					? __( 'Henüz yabancı dil önerisi için ziyaretçi kontrolü yapılmadı.', 'flx-lang' )
					: ( 'none' === $src2
						? __( 'Sunucu ülke bilgisi göndermiyor; tarayıcı saat dilimi kullanılıyor (Türkiye saati = Türkçe). Cloudflare açılırsa ülke kodu kendiliğinden kullanılır.', 'flx-lang' )
						/* translators: %s: header name */
						: sprintf( __( 'Ülke bilgisi %s başlığından geliyor.', 'flx-lang' ), $src2 ) ),
				'action_url'   => $url,
				'action_label' => __( 'Diller', 'flx-lang' ),
			);
		}

		if ( ! get_option( 'permalink_structure' ) ) {
			$checks[] = array(
				'id'           => 'flxl.permalinks',
				'source'       => $src,
				'group'        => 'Config',
				'label'        => __( 'Kalıcı bağlantılar', 'flx-lang' ),
				'status'       => 'warning',
				'message'      => __( 'Düz (?p=) bağlantılar kullanılıyor; dil adresleri için "Yazı adı" yapısını seçin.', 'flx-lang' ),
				'action_url'   => admin_url( 'options-permalink.php' ),
				'action_label' => __( 'Kalıcı bağlantılar', 'flx-lang' ),
			);
		}
		return $checks;
	}
);
