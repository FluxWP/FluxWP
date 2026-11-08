<?php
/**
 * Yardımcılar: bileşen (eklenti/tema/çekirdek) bulma, maskeleme, kısaltma.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dosya yolundan sorumlu bileşeni bulur.
 *
 * @param string $file Mutlak yol.
 * @return array{type:string,slug:string}
 */
function flxp_component_from_file( $file ) {
	static $cache = array();
	if ( isset( $cache[ $file ] ) ) {
		return $cache[ $file ];
	}
	$f = wp_normalize_path( (string) $file );
	// Sembolik bağlı eklentiler: WordPress gerçek yolu $wp_plugin_paths'te tutar.
	if ( ! empty( $GLOBALS['wp_plugin_paths'] ) ) {
		foreach ( (array) $GLOBALS['wp_plugin_paths'] as $dir => $real ) {
			$real = wp_normalize_path( $real );
			if ( 0 === strpos( $f, $real . '/' ) ) {
				$f = wp_normalize_path( $dir ) . substr( $f, strlen( $real ) );
				break;
			}
		}
	}
	$dirs = array(
		'plugin'    => wp_normalize_path( WP_PLUGIN_DIR ) . '/',
		'mu-plugin' => wp_normalize_path( WPMU_PLUGIN_DIR ) . '/',
		'theme'     => wp_normalize_path( get_theme_root() ) . '/',
	);
	$out = array( 'type' => 'core', 'slug' => 'core' );
	foreach ( $dirs as $type => $dir ) {
		if ( 0 === strpos( $f, $dir ) ) {
			$rest = substr( $f, strlen( $dir ) );
			$slug = strtok( $rest, '/' );
			$out  = array( 'type' => $type, 'slug' => preg_replace( '/\.php$/', '', (string) $slug ) );
			break;
		}
	}
	if ( 'core' === $out['type'] ) {
		$content = wp_normalize_path( WP_CONTENT_DIR ) . '/';
		if ( 0 === strpos( $f, $content ) ) {
			$out = array( 'type' => 'dropin', 'slug' => basename( $f, '.php' ) );
		} elseif ( '' === $f || 0 !== strpos( $f, wp_normalize_path( ABSPATH ) ) ) {
			$out = array( 'type' => 'other', 'slug' => 'other' );
		}
	}
	$cache[ $file ] = $out;
	return $out;
}

/** Bileşen için okunur ad: "Eklenti: flx-donate". */
function flxp_component_label( $c ) {
	if ( ! is_array( $c ) ) {
		return (string) $c;
	}
	switch ( $c['type'] ) {
		case 'plugin':
			/* translators: %s: eklenti klasörü */
			return sprintf( __( 'Plugin: %s', 'flx-speed' ), $c['slug'] );
		case 'mu-plugin':
			/* translators: %s: eklenti dosyası */
			return sprintf( __( 'MU plugin: %s', 'flx-speed' ), $c['slug'] );
		case 'theme':
			/* translators: %s: tema klasörü */
			return sprintf( __( 'Theme: %s', 'flx-speed' ), $c['slug'] );
		case 'dropin':
			/* translators: %s: drop-in dosyası */
			return sprintf( __( 'Drop-in: %s', 'flx-speed' ), $c['slug'] );
		case 'core':
			return __( 'WordPress core', 'flx-speed' );
	}
	return __( 'Other', 'flx-speed' );
}

/** Bileşen anahtarı: "plugin:flx-donate". */
function flxp_component_key( $c ) {
	return $c['type'] . ':' . $c['slug'];
}

/**
 * Çağrı yığınından ilk "sorumlu" kareyi ve bileşeni bulur.
 * Bu eklentinin ve wp-includes/wp-db gibi aracı dosyaların kareleri atlanır.
 *
 * @param array $trace debug_backtrace() çıktısı.
 * @return array{component:array,caller:string,file:string,line:int,stack:array}
 */
function flxp_trace_info( $trace, $stack_size = 12 ) {
	$self      = wp_normalize_path( FLXP_DIR );
	$skip_fn   = array( 'flxp_trace_info', 'flxp_backtrace', 'call_user_func_array', 'call_user_func', 'apply_filters', 'do_action', 'apply_filters_ref_array', 'do_action_ref_array' );
	$skip_cls  = array( 'wpdb', 'WP_Hook', 'FLXP_Collector', 'WP_Http', 'Requests', 'WpOrg\\Requests\\Requests', 'WpOrg\\Requests\\Transport\\Curl' );
	$stack     = array();
	$component = null;
	$caller    = '';
	$file      = '';
	$line      = 0;
	foreach ( $trace as $fr ) {
		$fn   = isset( $fr['class'] ) ? $fr['class'] . $fr['type'] . $fr['function'] : ( $fr['function'] ?? '' );
		$frf  = isset( $fr['file'] ) ? wp_normalize_path( $fr['file'] ) : '';
		// Toplayıcının kendi kareleri atlanır; Flux Speed'nin kendi sorguları (ayar okuma vb.) yine kendisine yazılır.
		$mine = $frf && 0 === strpos( $frf, $self ) && isset( $fr['class'] ) && 'FLXP_Collector' === $fr['class'];
		// Aracı fonksiyonlar (wpdb, hook, HTTP) çağıran adı olmaz ama çağrıldıkları dosya sorumluyu gösterir.
		$skip = ( ! isset( $fr['class'] ) && in_array( $fr['function'] ?? '', $skip_fn, true ) )
			|| ( isset( $fr['class'] ) && in_array( $fr['class'], $skip_cls, true ) );
		if ( ! $mine && ! $skip && count( $stack ) < $stack_size ) {
			$stack[] = $fn . ( $frf ? ' — ' . flxp_short_path( $frf ) . ':' . (int) ( $fr['line'] ?? 0 ) : '' );
		}
		if ( ! $mine && ! $skip && '' === $caller ) {
			$caller = $fn;
		}
		if ( null === $component && $frf ) {
			$c = flxp_component_from_file( $frf );
			// Çekirdek dosyasından çağrıldıysa, yığında yukarıda bir eklenti/tema var mı bakmaya devam et.
			if ( 'core' !== $c['type'] ) {
				$component = $c;
				$file      = $frf;
				$line      = (int) ( $fr['line'] ?? 0 );
			} elseif ( '' === $file ) {
				$file = $frf;
				$line = (int) ( $fr['line'] ?? 0 );
			}
		}
	}
	return array(
		'component' => $component ? $component : array( 'type' => 'core', 'slug' => 'core' ),
		'caller'    => $caller,
		'file'      => $file ? flxp_short_path( $file ) : '',
		'line'      => $line,
		'stack'     => $stack,
	);
}

/** ABSPATH'i kısaltır. */
function flxp_short_path( $file ) {
	$f   = wp_normalize_path( (string) $file );
	$abs = wp_normalize_path( ABSPATH );
	return 0 === strpos( $f, $abs ) ? substr( $f, strlen( $abs ) ) : $f;
}

/**
 * Kişisel veri ve gizli değerleri maskeler: e-posta, 10+ hane, IBAN, anahtar/token görünümlü değerler.
 */
function flxp_mask( $text ) {
	$text = (string) $text;
	$text = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '***@***', $text );
	$text = preg_replace( '/\bTR\d{2}[\d ]{10,30}\b/i', 'TR**', $text );
	$text = preg_replace( '/(?<![\w.])\+?\d[\d ]{9,}\d(?![\w.])/', '**********', $text );
	$text = preg_replace( '/\b(sk|pk|rk|key|tok)[-_][A-Za-z0-9_\-]{12,}/', '$1_***', $text );
	$text = preg_replace( "/((?:user_pass|session_tokens|user_activation_key|password|secret|token|api_key)'?\s*(?:=|,|:)\s*')[^']*'/i", "$1***'", $text );
	return $text;
}

/** URL'deki gizli sorgu parametrelerini maskeler. */
function flxp_mask_url( $url ) {
	$url = (string) $url;
	$url = preg_replace( '/([?&](?:key|apikey|api_key|token|access_token|pass|password|secret|auth|signature|sig|_wpnonce|code)=)[^&#]*/i', '$1***', $url );
	$url = preg_replace( '#(//)[^/@]+@#', '$1***@', $url );
	return flxp_mask( $url );
}

/** Uzun metni kısaltır. */
function flxp_cut( $text, $max = 2000 ) {
	$text = (string) $text;
	return strlen( $text ) > $max ? substr( $text, 0, $max ) . '…' : $text;
}

/** Sorguyu gruplamak için değerleri soyutlar. */
function flxp_normalize_sql( $sql ) {
	$sql = preg_replace( "/'(?:[^'\\\\]|\\\\.)*'/", '?', (string) $sql );
	$sql = preg_replace( '/\b\d+\b/', '?', $sql );
	$sql = preg_replace( '/\(\s*\?(?:\s*,\s*\?)+\s*\)/', '(?…)', $sql );
	return trim( preg_replace( '/\s+/', ' ', $sql ) );
}

/** Sorgu türü: SELECT, UPDATE... */
function flxp_sql_type( $sql ) {
	$sql = ltrim( (string) $sql, " \t\n\r(" );
	$w   = strtoupper( (string) strtok( $sql, " \t\n\r(" ) );
	return preg_match( '/^[A-Z]{3,10}$/', $w ) ? $w : 'OTHER';
}

/** PHP hata seviyesi adı. */
function flxp_error_level( $no ) {
	$map = array(
		E_ERROR             => 'fatal',
		E_PARSE             => 'fatal',
		E_CORE_ERROR        => 'fatal',
		E_COMPILE_ERROR     => 'fatal',
		E_USER_ERROR        => 'fatal',
		E_RECOVERABLE_ERROR => 'fatal',
		E_WARNING           => 'warning',
		E_CORE_WARNING      => 'warning',
		E_COMPILE_WARNING   => 'warning',
		E_USER_WARNING      => 'warning',
		E_NOTICE            => 'notice',
		E_USER_NOTICE       => 'notice',
		E_DEPRECATED        => 'deprecated',
		E_USER_DEPRECATED   => 'deprecated',
	);
	if ( 2048 === $no ) { // E_STRICT (PHP 8.4'te sabit kullanımdan kalktı).
		return 'notice';
	}
	return isset( $map[ $no ] ) ? $map[ $no ] : 'notice';
}
