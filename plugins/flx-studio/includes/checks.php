<?php
/**
 * Sistem kontrolü (filter `flx_system_checks`). Dış HTTP isteği yapmaz.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_system_checks',
	function ( $checks ) {
		return array_merge( is_array( $checks ) ? $checks : array(), flxst_system_checks() );
	}
);

function flxst_system_checks() {
	global $wpdb;
	$out = array();
	$add = function ( $id, $label, $status, $message, $url = '', $url_label = '' ) use ( &$out ) {
		$c = array(
			'id'      => 'flx-studio/' . $id,
			'source'  => 'Flux Studio',
			'group'   => __( 'Stüdyo', 'flx-studio' ),
			'label'   => $label,
			'status'  => $status,
			'message' => $message,
		);
		if ( $url ) {
			$c['action_url']   = $url;
			$c['action_label'] = $url_label ? $url_label : __( 'Aç', 'flx-studio' );
		}
		$out[] = $c;
	};

	$missing = array();
	foreach ( array( 'items', 'folders', 'people', 'refs', 'templates' ) as $t ) {
		$name = flxst_table( $t );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $name ) ) !== $name ) { // phpcs:ignore WordPress.DB
			$missing[] = $name;
		}
	}
	/* translators: %s: table names */
	$add( 'tables', __( 'Veritabanı tabloları', 'flx-studio' ), $missing ? 'error' : 'ok', $missing ? sprintf( __( 'Eksik tablo: %s. Eklentiyi devre dışı bırakıp yeniden etkinleştirin.', 'flx-studio' ), implode( ', ', $missing ) ) : __( 'Tablolar hazır.', 'flx-studio' ) );

	$dir = flxst_upload_dir();
	$sub = flxst_ensure_dir( 'out' );
	$ok  = is_dir( $sub ) && wp_is_writable( $sub );
	$add( 'uploads', __( 'Dosya klasörü', 'flx-studio' ), $ok ? 'ok' : 'error', $ok ? __( 'wp-content/uploads/flx-studio yazılabilir.', 'flx-studio' ) : __( 'wp-content/uploads/flx-studio oluşturulamadı ya da yazılamıyor; referans ve küçük resimler kaydedilemez.', 'flx-studio' ) );

	$ai_url = admin_url( 'admin.php?page=flx-ai-settings' );
	if ( ! flxst_ai_ready() ) {
		$add( 'ai', __( 'Görsel ve video altyapısı', 'flx-studio' ), 'error', __( 'Flux AI 0.6.0 veya üstü gerekli (görsel/video üretimi Flux AI üzerinden yapılır).', 'flx-studio' ), admin_url( 'plugins.php' ), __( 'Eklentiler', 'flx-studio' ) );
	} else {
		$cat   = flxst_models();
		$ready = array_keys( array_filter( (array) ( $cat['providers'] ?? array() ), fn( $p ) => ! empty( $p['ready'] ) ) );
		$usable = array_filter( (array) ( $cat['models'] ?? array() ), fn( $m ) => ! empty( $m['providers'] ) );
		if ( ! $usable ) {
			$add( 'ai', __( 'Görsel ve video altyapısı', 'flx-studio' ), 'warning', __( 'Hiçbir model için anahtar yok. Flux AI & MCP › Ayarlar › Görsel ve video bölümünden fal.ai, EvoLink ya da başka bir anahtar girin.', 'flx-studio' ), $ai_url, __( 'Flux AI', 'flx-studio' ) );
		} else {
			/* translators: 1: model count, 2: providers */
			$add( 'ai', __( 'Görsel ve video altyapısı', 'flx-studio' ), 'ok', sprintf( __( '%1$d model kullanılabilir (%2$s).', 'flx-studio' ), count( $usable ), implode( ', ', $ready ) ), $ai_url, __( 'Flux AI', 'flx-studio' ) );
		}
	}
	$add( 'text', __( 'Geliştir ve Beyin fırtınası', 'flx-studio' ), flxst_text_ready() ? 'ok' : 'warning', flxst_text_ready() ? __( 'Flux AI metin modeli hazır.', 'flx-studio' ) : __( 'Flux AI metin modeli hazır değil; istem geliştirme ve fikir üretme çalışmaz, üretim çalışır.', 'flx-studio' ), $ai_url, __( 'Flux AI', 'flx-studio' ) );

	$add( 'https', __( 'Referans görselleri', 'flx-studio' ), is_ssl() || 0 === strpos( home_url(), 'https:' ) ? 'ok' : 'info', is_ssl() || 0 === strpos( home_url(), 'https:' ) ? __( 'Referanslar sağlayıcıya site adresiyle gönderilir.', 'flx-studio' ) : __( 'Site HTTPS değil; referanslar dosya içeriğiyle (base64) gönderilir, istek büyür.', 'flx-studio' ) );
	return $out;
}
