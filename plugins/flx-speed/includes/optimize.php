<?php
/**
 * PageSpeed iyileştirmeleri (Performans › PageSpeed). Her biri panelden açılıp kapanır:
 *
 *  - Yakınlaştırma kilidini kaldır (viewport user-scalable=no / maximum-scale)      güvenli, varsayılan açık
 *  - WordPress emoji betiğini kaldır                                                 güvenli, varsayılan açık
 *  - Kullanılmayan CSS'i kırp + satır içine al: sayfadaki sınıf/id envanterine göre  önce deneyin, kapalı
 *    kırpılmış kopya üretilir (envanter imzası başına bir dosya). Kaynak değişince
 *    (yol + mtime + boyut imzası) kopyalar yenilenir; kopya sayısı sınırı aşılırsa
 *    tam dosyaya dönülür.
 *  - CSS / JS sıkıştırma: güncel .min kardeşi varsa o, yoksa üretilmiş kopya          kapalı
 *  - Head betiklerine defer (WordPress'in strateji denetimiyle)                       kapalı
 *  - Kullanılmayan theme.json yazı tiplerini çıkar, ilk ekran yazı tipini preload     kapalı
 *
 * Yönetim, REST, AJAX, cron, feed, önizleme ve ?flxp_off=1 isteklerinde hiçbir şey yapılmaz.
 * Ön ayarlar/temalar `flxp_pagespeed_enabled` filtresiyle bir özelliği kapatabilir.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/optimize-css.php';
require_once __DIR__ . '/optimize-js.php';

class FLXP_PageSpeed {

	/** Bir kaynak dosya için en fazla kırpılmış kopya; aşılırsa tam dosya. */
	const MAX_VARIANTS = 40;

	/** Her zaman korunan durum sınıfları (JS'in birleştirerek eklediği sınıflar için). */
	const SAFE_DEFAULT = array( 'is-*', 'has-*', 'screen-reader-text', 'wp-*', 'swiper-*' ); // swiper-*: Swiper sınıf adlarını JS'de birleştirerek üretir (swiper-pagination-bullet), kelime envanterinde çıkmaz.

	private static $seen = array();

	/** Ayar anahtarları ve varsayılanları (flxp_settings içinde). */
	public static function defaults() {
		return array(
			'ps_viewport'    => 1,
			'ps_emoji'       => 1,
			'ps_css'         => 0,
			'ps_css_scope'   => 'theme', // theme | local
			'ps_inline_kb'   => 60,
			'ps_safelist'    => '',
			'ps_min_css'     => 0,
			'ps_min_js'      => 0,
			'ps_defer'       => 0,
			'ps_fonts'       => 0,
			'ps_preload'     => 0,
			'ps_exclude'     => '',
			'ps_skip_urls'   => '',
		);
	}

	/** Açık/kapalı anahtarlar. */
	public static function toggles() {
		return array( 'ps_viewport', 'ps_emoji', 'ps_css', 'ps_min_css', 'ps_min_js', 'ps_defer', 'ps_fonts', 'ps_preload' );
	}

	public static function sanitize( $in, $out ) {
		foreach ( self::toggles() as $k ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		$out['ps_css_scope'] = in_array( $in['ps_css_scope'] ?? '', array( 'theme', 'local' ), true ) ? $in['ps_css_scope'] : 'theme';
		$out['ps_inline_kb'] = max( 0, min( 500, (int) ( $in['ps_inline_kb'] ?? 60 ) ) );
		foreach ( array( 'ps_safelist', 'ps_exclude', 'ps_skip_urls' ) as $k ) {
			$lines     = preg_split( '/[\r\n,]+/', (string) ( $in[ $k ] ?? '' ) );
			$lines     = array_filter( array_map( fn( $l ) => trim( sanitize_text_field( $l ) ), $lines ) );
			$out[ $k ] = implode( "\n", array_slice( array_unique( $lines ), 0, 100 ) );
		}
		return $out;
	}

	/** Özellik açık mı (ayar + filtre). */
	public static function on( $key ) {
		$on = ! empty( flxp_settings( $key ) );
		/**
		 * Bir PageSpeed özelliğini tema/ön ayar tarafında kapatır ya da açar.
		 *
		 * @param bool   $on  Ayar değeri.
		 * @param string $key ps_viewport, ps_emoji, ps_css, ps_min_css, ps_min_js, ps_defer, ps_fonts, ps_preload.
		 */
		return (bool) apply_filters( 'flxp_pagespeed_enabled', $on, $key );
	}

	private static function lines( $key ) {
		return array_values( array_filter( array_map( 'trim', explode( "\n", (string) flxp_settings( $key ) ) ) ) );
	}

	/* ------------------------------------------------------------ Kurulum */

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'init' ), 5 );
		// Eski kopyalar imza değişince kendiliğinden silinir; tema değişince hepsi + sayfa önbelleği.
		add_action(
			'switch_theme',
			function () {
				self::clear_cache();
				do_action( 'litespeed_purge_all' );
			}
		);
	}

	public static function init() {
		if ( ! self::front_request() ) {
			return;
		}
		if ( self::on( 'ps_emoji' ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
			add_filter( 'emoji_svg_url', '__return_false' );
		}
		if ( self::on( 'ps_defer' ) ) {
			add_action( 'wp_print_scripts', array( __CLASS__, 'defer_head_scripts' ), PHP_INT_MAX );
		}
		if ( self::on( 'ps_min_js' ) ) {
			add_filter( 'script_loader_src', array( __CLASS__, 'min_js_src' ), 20, 2 );
		}
		add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 9999 );
	}

	/** Ön yüz sayfa isteği mi? */
	public static function front_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
			return false;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			return false;
		}
		if ( isset( $_GET['flxp_off'] ) || isset( $_GET['customize_changeset_uuid'] ) || isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore
		if ( preg_match( '#/(?:wp-json|wp-admin|wp-login\.php|xmlrpc\.php|feed)(?:/|$|\?)#', $uri ) ) {
			return false;
		}
		foreach ( self::lines( 'ps_skip_urls' ) as $p ) {
			if ( '' !== $p && false !== strpos( $uri, $p ) ) {
				return false;
			}
		}
		return true;
	}

	public static function start_buffer() {
		if ( is_feed() || is_robots() || is_trackback() || is_embed() || is_preview() || is_customize_preview() || get_query_var( 'sitemap' ) ) {
			return;
		}
		$any = false;
		foreach ( array( 'ps_viewport', 'ps_css', 'ps_min_css', 'ps_fonts', 'ps_preload' ) as $k ) {
			$any = $any || self::on( $k );
		}
		$any = $any || self::seen_due();
		if ( $any ) {
			ob_start( array( __CLASS__, 'process' ) );
		}
	}

	/* ------------------------------------------------------------ Yollar ve önbellek */

	/** Önbellek klasörü (uploads/flx-speed). */
	public static function cache_dir( $sub = '' ) {
		$u = wp_upload_dir( null, false );
		return array(
			'path' => trailingslashit( $u['basedir'] ) . 'flx-speed/' . ( $sub ? $sub . '/' : '' ),
			'url'  => set_url_scheme( trailingslashit( $u['baseurl'] ) . 'flx-speed/' . ( $sub ? $sub . '/' : '' ) ),
		);
	}

	private static function ensure_dir( $sub ) {
		$d = self::cache_dir( $sub );
		if ( ! is_dir( $d['path'] ) ) {
			wp_mkdir_p( $d['path'] );
			@file_put_contents( dirname( $d['path'] ) . '/index.php', "<?php\n" ); // phpcs:ignore
			@file_put_contents( $d['path'] . 'index.php', "<?php\n" ); // phpcs:ignore
		}
		return is_writable( $d['path'] ) ? $d : null;
	}

	/** Önbelleği siler; silinen dosya sayısı. */
	public static function clear_cache() {
		$n = 0;
		foreach ( array( 'css', 'js', 'meta' ) as $sub ) {
			$d = self::cache_dir( $sub )['path'];
			foreach ( (array) glob( $d . '*.{css,js,json}', GLOB_BRACE ) as $f ) {
				if ( $f && @unlink( $f ) ) { // phpcs:ignore
					++$n;
				}
			}
		}
		return $n;
	}

	/** Önbellek özeti: dosya sayısı ve boyut. */
	public static function cache_stats() {
		$s = array(
			'files' => 0,
			'bytes' => 0,
			'css'   => 0,
			'js'    => 0,
		);
		foreach ( array( 'css', 'js' ) as $sub ) {
			foreach ( (array) glob( self::cache_dir( $sub )['path'] . '*.' . $sub ) as $f ) {
				if ( $f ) {
					++$s['files'];
					++$s[ $sub ];
					$s['bytes'] += (int) @filesize( $f ); // phpcs:ignore
				}
			}
		}
		return $s;
	}

	/**
	 * Site içi adresi diskteki dosyaya çevirir (yalnız wp-content ve WordPress kökü altı, .css/.js).
	 *
	 * @return string|null
	 */
	public static function local_path( $url ) {
		$url = html_entity_decode( (string) $url, ENT_QUOTES );
		$url = strtok( $url, '?#' );
		if ( ! $url || ! preg_match( '/\.(css|js)$/i', $url ) ) {
			return null;
		}
		if ( 0 === strpos( $url, '//' ) ) {
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		}
		$strip = function ( $u ) {
			return preg_replace( '#^https?:#i', '', untrailingslashit( $u ) );
		};
		$u    = preg_replace( '#^https?:#i', '', $url );
		$path = null;
		$map  = array(
			$strip( content_url() ) => WP_CONTENT_DIR,
			$strip( site_url() )    => untrailingslashit( ABSPATH ),
		);
		if ( '/' === $url[0] ) {
			$map = array(
				(string) wp_parse_url( content_url(), PHP_URL_PATH ) => WP_CONTENT_DIR,
				untrailingslashit( (string) wp_parse_url( site_url( '/' ), PHP_URL_PATH ) ) => untrailingslashit( ABSPATH ),
			);
		}
		foreach ( $map as $prefix => $dir ) {
			if ( '' !== $prefix && 0 === strpos( $u, $prefix . '/' ) ) {
				$path = $dir . substr( $u, strlen( $prefix ) );
				break;
			}
			if ( '' === $prefix && '/' === $u[0] ) {
				$path = $dir . $u;
				break;
			}
		}
		if ( ! $path ) {
			return null;
		}
		$real = realpath( rawurldecode( $path ) );
		if ( ! $real || ! is_file( $real ) ) {
			return null;
		}
		$roots = array( realpath( WP_CONTENT_DIR ), realpath( ABSPATH ) );
		foreach ( $roots as $r ) {
			if ( $r && 0 === strpos( $real, $r . DIRECTORY_SEPARATOR ) ) {
				return $real;
			}
		}
		return null;
	}

	/** Kaynak imzası: yol + değişiklik zamanı + boyut (+ ayarlar). Dosya değişince kopyalar yenilenir. */
	private static function src_key( $path, $extra = '' ) {
		return substr( md5( $path . '|' . @filemtime( $path ) . '|' . @filesize( $path ) . '|' . FLXP_VERSION . '|' . $extra ), 0, 10 ); // phpcs:ignore
	}

	private static function slug( $path ) {
		$base = preg_replace( '/\.(min\.)?(css|js)$/i', '', basename( $path ) );
		return substr( sanitize_key( $base ), 0, 40 ) . '-' . substr( md5( $path ), 0, 6 );
	}

	/** Atomik yazma. */
	private static function write( $file, $data ) {
		$tmp = $file . '.' . wp_rand() . '.tmp';
		if ( false === @file_put_contents( $tmp, $data ) ) { // phpcs:ignore
			return false;
		}
		return @rename( $tmp, $file ); // phpcs:ignore
	}

	/** Eski imzalı kopyaları siler. */
	private static function prune( $dir, $slug, $key, $ext ) {
		foreach ( (array) glob( $dir . $slug . '.*.' . $ext ) as $f ) {
			if ( $f && false === strpos( basename( $f ), $slug . '.' . $key . '.' ) ) {
				@unlink( $f ); // phpcs:ignore
			}
		}
	}

	private static function excluded( $handle_or_url ) {
		foreach ( self::lines( 'ps_exclude' ) as $p ) {
			if ( '' !== $p && false !== stripos( (string) $handle_or_url, $p ) ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------ Sıkıştırma */

	/**
	 * Sıkıştırılmış kopyanın adresi: güncel bir .min kardeşi varsa o, yoksa üretilen dosya.
	 * .min kardeşi kaynaktan eskiyse (kaynak sonra düzenlenmiş) kullanılmaz.
	 *
	 * @return string|null
	 */
	public static function minified_url( $path, $ext, $orig_url ) {
		if ( preg_match( '/[.\-]min\.' . $ext . '$/i', $path ) ) {
			return null;
		}
		$size = (int) @filesize( $path ); // phpcs:ignore
		if ( $size < 512 || $size > 3 * MB_IN_BYTES ) {
			return null;
		}
		$sib = preg_replace( '/\.' . $ext . '$/i', '.min.' . $ext, $path );
		if ( is_file( $sib ) && @filemtime( $sib ) >= @filemtime( $path ) ) { // phpcs:ignore
			return preg_replace( '/\.' . $ext . '(\?|$)/i', '.min.' . $ext . '$1', $orig_url );
		}
		$d = self::ensure_dir( $ext );
		if ( ! $d ) {
			return null;
		}
		$slug = self::slug( $path );
		$key  = self::src_key( $path );
		$file = $d['path'] . $slug . '.' . $key . '.min.' . $ext;
		$skip = $d['path'] . $slug . '.' . $key . '.skip.json';
		if ( is_file( $skip ) ) {
			return null;
		}
		if ( ! is_file( $file ) ) {
			$src = (string) file_get_contents( $path ); // phpcs:ignore
			if ( 'js' === $ext ) {
				// Kendi konumundan parça yükleyen paketler (webpack publicPath, import()) taşınamaz.
				$min = preg_match( '/__webpack_require__|document\.currentScript|import\s*\(|import\.meta|sourceMappingURL/', $src ) ? null : flxp_js_minify( $src );
			} else {
				$min = flxp_css_absolute_urls( flxp_css_minify( $src ), $orig_url );
				$min = strlen( $min ) < strlen( $src ) ? $min : null;
			}
			self::prune( $d['path'], $slug, $key, $ext );
			self::prune( $d['path'], $slug, $key, 'json' );
			if ( null === $min || ( $size - strlen( $min ) ) < 256 ) {
				self::write( $skip, '1' );
				return null;
			}
			if ( ! self::write( $file, $min ) ) {
				return null;
			}
		}
		return $d['url'] . basename( $file );
	}

	public static function min_js_src( $src, $handle ) {
		if ( ! $src || self::excluded( $handle ) || self::excluded( $src ) ) {
			return $src;
		}
		$ws = wp_scripts();
		// Çeviri dosyası olan betiklere dokunma (JSON çevirisi adresle eşleşir).
		if ( isset( $ws->registered[ $handle ] ) && ! empty( $ws->registered[ $handle ]->textdomain ) ) {
			return $src;
		}
		$path = self::local_path( $src );
		if ( ! $path || 0 === strpos( $path, realpath( ABSPATH . WPINC ) ) ) {
			return $src;
		}
		$min = self::minified_url( $path, 'js', $src );
		return $min ? $min : $src;
	}

	/* ------------------------------------------------------------ Defer */

	/** Head'deki engelleyen betiklere defer stratejisi; WordPress uygun değilse (bağımlı, after betiği) yine engelleyen yazar. */
	public static function defer_head_scripts() {
		$ws   = wp_scripts();
		$skip = array( 'jquery', 'jquery-core', 'jquery-migrate' );
		$todo = (array) $ws->queue;
		$seen = array();
		while ( $todo ) {
			$h = array_shift( $todo );
			if ( isset( $seen[ $h ] ) || ! isset( $ws->registered[ $h ] ) ) {
				continue;
			}
			$seen[ $h ] = true;
			$obj        = $ws->registered[ $h ];
			$todo       = array_merge( $todo, (array) $obj->deps );
			if ( ! $obj->src || in_array( $h, $skip, true ) || self::excluded( $h ) || self::excluded( $obj->src ) ) {
				continue;
			}
			if ( (int) $ws->get_data( $h, 'group' ) > 0 || $ws->get_data( $h, 'strategy' ) ) {
				continue;
			}
			$ws->add_data( $h, 'strategy', 'defer' );
		}
	}

	/* ------------------------------------------------------------ Sayfa işleme */

	/**
	 * Çıktı tamponu: viewport, CSS, yazı tipleri. Hata olursa sayfa olduğu gibi döner.
	 *
	 * @param string $html Sayfa.
	 * @return string
	 */
	public static function process( $html ) {
		if ( ! is_string( $html ) || strlen( $html ) < 200 || false === stripos( $html, '</head>' ) || ! preg_match( '/<html[\s>]/i', substr( $html, 0, 2000 ) ) ) {
			return $html;
		}
		foreach ( headers_list() as $h ) {
			if ( 0 === stripos( $h, 'content-type:' ) && false === stripos( $h, 'text/html' ) ) {
				return $html;
			}
		}
		try {
			$out = $html;
			if ( self::on( 'ps_viewport' ) ) {
				$out = self::fix_viewport( $out );
			}
			$out = self::process_styles( $out );
			if ( self::on( 'ps_fonts' ) || self::on( 'ps_preload' ) ) {
				$out = self::process_fonts( $out );
			}
			self::seen_save();
			return $out;
		} catch ( Throwable $e ) {
			return $html;
		}
	}

	/** user-scalable=no ve maximum-scale (<5) kaldırılır. */
	public static function fix_viewport( $html ) {
		return preg_replace_callback(
			'/<meta\b[^>]*name\s*=\s*["\']viewport["\'][^>]*>/i',
			function ( $m ) {
				return preg_replace_callback(
					'/(content\s*=\s*)(["\'])(.*?)\2/i',
					function ( $c ) {
						$parts = array_filter(
							array_map( 'trim', explode( ',', $c[3] ) ),
							function ( $p ) {
								if ( preg_match( '/^user-scalable\s*=\s*(no|0)$/i', $p ) ) {
									return false;
								}
								if ( preg_match( '/^maximum-scale\s*=\s*([\d.]+)$/i', $p, $x ) && (float) $x[1] < 5 ) {
									return false;
								}
								return '' !== $p;
							}
						);
						return $c[1] . $c[2] . implode( ', ', $parts ) . $c[2];
					},
					$m[0]
				);
			},
			$html
		);
	}

	/** <link …> özniteliklerini çözer. */
	private static function attrs( $tag ) {
		$a = array();
		if ( preg_match_all( '/([\w:\-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/', $tag, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				$a[ strtolower( $x[1] ) ] = html_entity_decode( $x[2] . ( $x[3] ?? '' ) . ( $x[4] ?? '' ), ENT_QUOTES );
			}
		}
		return $a;
	}

	/** Kırpma kapsamında mı (tema ya da tüm wp-content)? */
	private static function in_scope( $path ) {
		$dirs = array( get_stylesheet_directory(), get_template_directory() );
		if ( 'local' === flxp_settings( 'ps_css_scope' ) ) {
			$dirs = array( WP_CONTENT_DIR );
		}
		foreach ( array_unique( array_filter( array_map( 'realpath', $dirs ) ) ) as $d ) {
			if ( 0 === strpos( $path, $d . DIRECTORY_SEPARATOR ) ) {
				return true;
			}
		}
		return false;
	}

	/** Sayfadaki sınıf/kelime envanteri (+ yerel betiklerdeki kelimeler). */
	private static function page_words( $html ) {
		$words = array();
		$cls   = array();
		if ( preg_match_all( '/\b(?:class|id)\s*=\s*(["\'])(.*?)\1/is', $html, $m ) ) {
			foreach ( $m[2] as $v ) {
				foreach ( preg_split( '/\s+/', html_entity_decode( $v, ENT_QUOTES ) ) as $t ) {
					if ( '' !== $t ) {
						$cls[ $t ] = true;
					}
				}
			}
		}
		if ( preg_match_all( '/[A-Za-z_][\w\-]*/', $html, $m ) ) {
			$words = array_fill_keys( $m[0], true );
		}
		if ( preg_match_all( '/<script\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i', $html, $m ) ) {
			foreach ( array_slice( array_unique( $m[1] ), 0, 60 ) as $src ) {
				$path = self::local_path( $src );
				if ( $path ) {
					$words += self::file_words( $path );
				}
			}
		}
		return $words + $cls;
	}

	/** Bir betik dosyasındaki kelimeler (önbellekli). */
	private static function file_words( $path ) {
		$d = self::ensure_dir( 'meta' );
		$f = $d ? $d['path'] . 'w-' . self::src_key( $path ) . '.json' : '';
		if ( $f && is_file( $f ) ) {
			$w = json_decode( (string) file_get_contents( $f ), true ); // phpcs:ignore
			if ( is_array( $w ) ) {
				return array_fill_keys( $w, true );
			}
		}
		$src = (string) @file_get_contents( $path, false, null, 0, 3 * MB_IN_BYTES ); // phpcs:ignore
		preg_match_all( '/[A-Za-z_][\w\-]*/', $src, $m );
		$w = array_values( array_unique( $m[0] ) );
		if ( $f ) {
			self::write( $f, wp_json_encode( $w ) );
		}
		return array_fill_keys( $w, true );
	}

	/** CSS dosyasının belirteç envanteri (önbellekli). */
	private static function css_inventory( $path, $key ) {
		$d = self::ensure_dir( 'meta' );
		$f = $d ? $d['path'] . 'i-' . $key . '.json' : '';
		if ( $f && is_file( $f ) ) {
			$i = json_decode( (string) file_get_contents( $f ), true ); // phpcs:ignore
			if ( is_array( $i ) ) {
				return $i;
			}
		}
		$i = array_keys( flxp_css_inventory( (string) file_get_contents( $path ) ) ); // phpcs:ignore
		if ( $f ) {
			self::write( $f, wp_json_encode( $i ) );
		}
		return $i;
	}

	/** Güvenli liste (joker * destekli) eşleştirici. */
	private static function safe_matcher() {
		$pats = array_merge( self::SAFE_DEFAULT, self::lines( 'ps_safelist' ) );
		$re   = array();
		foreach ( $pats as $p ) {
			$p = ltrim( trim( $p ), '.#' );
			if ( '' !== $p ) {
				$re[] = str_replace( '\*', '.*', preg_quote( $p, '/' ) );
			}
		}
		$re = $re ? '/^[.#](?:' . implode( '|', $re ) . ')$/' : '';
		return function ( $t ) use ( $re ) {
			return $re && preg_match( $re, $t );
		};
	}

	/**
	 * Kırpılmış CSS: array( 'css' => …, 'file' => yol, 'url' => adres, 'full' => tam dosyaya dönüldü mü ).
	 *
	 * @return array|null
	 */
	private static function trimmed( $path, $url, $words, $safe ) {
		$d = self::ensure_dir( 'css' );
		if ( ! $d ) {
			return null;
		}
		$slug = self::slug( $path );
		$key  = self::src_key( $path, (string) flxp_settings( 'ps_safelist' ) );
		$inv  = self::css_inventory( $path, $key );
		$keep = array();
		foreach ( $inv as $t ) {
			$name = substr( $t, 1 );
			if ( isset( $words[ $name ] ) || $safe( $t ) ) {
				$keep[ $t ] = true;
			}
		}
		ksort( $keep );
		$ikey = substr( md5( implode( ' ', array_keys( $keep ) ) ), 0, 10 );
		$file = $d['path'] . $slug . '.' . $key . '.' . $ikey . '.css';
		$full = false;
		if ( ! is_file( $file ) ) {
			self::prune( $d['path'], $slug, $key, 'css' );
			$count = count( (array) glob( $d['path'] . $slug . '.' . $key . '.*.css' ) );
			if ( $count >= self::MAX_VARIANTS ) {
				// Çok fazla farklı sayfa envanteri: tüm sınıflarla tek (tam) kopya.
				$file = $d['path'] . $slug . '.' . $key . '.full.css';
				$full = true;
				if ( ! is_file( $file ) ) {
					$css = flxp_css_absolute_urls( flxp_css_minify( (string) file_get_contents( $path ) ), $url ); // phpcs:ignore
					if ( ! self::write( $file, $css ) ) {
						return null;
					}
				}
			} else {
				$css = flxp_css_absolute_urls( flxp_css_trim( (string) file_get_contents( $path ), $keep ), $url ); // phpcs:ignore
				if ( ! self::write( $file, $css ) ) {
					return null;
				}
			}
		}
		return array(
			'css'  => (string) file_get_contents( $file ), // phpcs:ignore
			'file' => $file,
			'url'  => $d['url'] . basename( $file ),
			'full' => $full,
		);
	}

	/** Stil dosyaları: kırp + satır içi / sıkıştırılmış kopya. */
	private static function process_styles( $html ) {
		$head_end = stripos( $html, '</head>' );
		if ( ! preg_match_all( '/<link\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE ) ) {
			return $html;
		}
		$css_on = self::on( 'ps_css' );
		$min_on = self::on( 'ps_min_css' );
		$budget = (int) flxp_settings( 'ps_inline_kb' ) * 1024;
		$words  = null;
		$safe   = null;
		$repl   = array();
		foreach ( $m[0] as $hit ) {
			$tag = $hit[0];
			$a   = self::attrs( $tag );
			if ( empty( $a['href'] ) || false === stripos( ' ' . ( $a['rel'] ?? '' ) . ' ', ' stylesheet ' ) ) {
				continue;
			}
			$path = self::local_path( $a['href'] );
			if ( ! $path ) {
				continue;
			}
			self::seen_add( $path );
			$id = $a['id'] ?? '';
			if ( ! $css_on && ! $min_on ) {
				continue;
			}
			if ( self::excluded( $id ) || self::excluded( $a['href'] ) || ! empty( $a['onload'] ) || ( isset( $a['media'] ) && 'print' === $a['media'] ) ) {
				continue;
			}
			$new = null;
			if ( $css_on && self::in_scope( $path ) ) {
				if ( null === $words ) {
					$words = self::page_words( $html );
					$safe  = self::safe_matcher();
				}
				$t = self::trimmed( $path, $a['href'], $words, $safe );
				if ( $t ) {
					$len   = strlen( $t['css'] );
					$media = isset( $a['media'] ) && 'all' !== $a['media'] ? ' media="' . esc_attr( $a['media'] ) . '"' : '';
					if ( $hit[1] < $head_end && $len <= $budget ) {
						$budget -= $len;
						$new     = '<style id="' . esc_attr( $id ? $id : 'flxp-' . self::slug( $path ) ) . '" data-flxp="inline"' . $media . '>' . str_ireplace( '</style', '<\/style', $t['css'] ) . '</style>';
					} else {
						$new = str_replace( self::href_raw( $tag ), esc_url( $t['url'] ), $tag );
					}
				}
			}
			if ( null === $new && $min_on ) {
				$u = self::minified_url( $path, 'css', $a['href'] );
				if ( $u ) {
					$new = str_replace( self::href_raw( $tag ), esc_url( $u ), $tag );
				}
			}
			if ( null !== $new && $new !== $tag ) {
				$repl[] = array( $hit[1], strlen( $tag ), $new );
			}
		}
		// Sondan başa değiştir (konumlar kaymasın).
		foreach ( array_reverse( $repl ) as $r ) {
			$html = substr_replace( $html, $r[2], $r[0], $r[1] );
		}
		return $html;
	}

	/** Etiketteki href değerinin ham (kodlanmış) hali. */
	private static function href_raw( $tag ) {
		return preg_match( '/\bhref\s*=\s*(["\'])(.*?)\1/i', $tag, $m ) ? $m[2] : '';
	}

	/* ------------------------------------------------------------ Yazı tipleri */

	/** Kullanılmayan theme.json @font-face bloklarını çıkarır, ilk ekran yazı tipini preload eder. */
	private static function process_fonts( $html ) {
		if ( ! preg_match( '/<style\b[^>]*class\s*=\s*["\']wp-fonts-local["\'][^>]*>(.*?)<\/style>/is', $html, $fm, PREG_OFFSET_CAPTURE ) ) {
			return $html;
		}
		$faces_css = $fm[1][0];
		if ( ! preg_match_all( '/@font-face\s*\{[^}]*\}/i', $faces_css, $faces ) ) {
			return $html;
		}
		// Sayfanın CSS'i: satır içi stiller + yerel stil dosyaları.
		$css = '';
		if ( preg_match_all( '/<style\b[^>]*>(.*?)<\/style>/is', $html, $sm ) ) {
			foreach ( $sm[1] as $i => $s ) {
				if ( false === strpos( $sm[0][ $i ], 'wp-fonts-local' ) ) {
					$css .= $s . "\n";
				}
			}
		}
		if ( preg_match_all( '/<link\b[^>]*rel\s*=\s*["\']stylesheet["\'][^>]*>/i', $html, $lm ) ) {
			foreach ( $lm[0] as $tag ) {
				$a    = self::attrs( $tag );
				$path = isset( $a['href'] ) ? self::local_path( $a['href'] ) : null;
				if ( $path && filesize( $path ) < 2 * MB_IN_BYTES ) {
					$css .= (string) file_get_contents( $path ) . "\n"; // phpcs:ignore
				}
			}
		}
		$used = self::used_families( $css, $html );
		$keep = array();
		$drop = 0;
		foreach ( $faces[0] as $face ) {
			$fam = preg_match( '/font-family\s*:\s*([^;}]+)/i', $face, $x ) ? strtolower( trim( $x[1], " \t\"'" ) ) : '';
			if ( $used && '' !== $fam && ! isset( $used[ $fam ] ) && self::on( 'ps_fonts' ) ) {
				++$drop;
				continue;
			}
			$keep[] = $face;
		}
		$new_style = $faces_css;
		if ( $drop ) {
			$new_style = implode( '', $keep );
		}
		$out = substr_replace( $html, $new_style, $fm[1][1], strlen( $faces_css ) );
		if ( self::on( 'ps_preload' ) && $used ) {
			$links = self::preload_links( $keep, $used, $out );
			if ( $links ) {
				$out = preg_replace( '/(<meta\b[^>]*charset[^>]*>)/i', '$1' . $links, $out, 1, $cnt );
				if ( ! $cnt ) {
					$out = preg_replace( '/(<head\b[^>]*>)/i', '$1' . $links, $out, 1 );
				}
			}
		}
		return $out;
	}

	/**
	 * Sayfada kullanılan yazı tipi aileleri (küçük harf). Boş dizi = belirlenemedi, hiçbir şey çıkarılmaz.
	 *
	 * @return array
	 */
	public static function used_families( $css, $html ) {
		$used  = array();
		$slugs = array();
		if ( preg_match_all( '/var\(\s*--wp--preset--font-family--([\w-]+)/i', $css . $html, $m ) ) {
			$slugs = array_fill_keys( $m[1], true );
		}
		if ( preg_match_all( '/\bhas-([\w-]+)-font-family\b/', $html, $m ) ) {
			$slugs += array_fill_keys( $m[1], true );
		}
		if ( preg_match_all( '/--wp--preset--font-family--([\w-]+)\s*:\s*([^;}]+)/i', $css, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				if ( isset( $slugs[ $x[1] ] ) ) {
					foreach ( explode( ',', $x[2] ) as $f ) {
						$used[ strtolower( trim( $f, " \t\"'" ) ) ] = true;
					}
				}
			}
		}
		// @font-face tanımları kullanım sayılmaz (aileyi tanımlar, kullanmaz).
		$src = preg_replace( '/@font-face\s*\{[^}]*\}/i', '', $css . "\n" . $html );
		if ( preg_match_all( '/(?<![\w-])font(-family)?\s*:\s*((?:[^;}"\'<>]|"[^"<>]*"|\'[^\'<>]*\')+)/i', $src, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				$v = preg_replace( '/var\([^)]*\)/', '', html_entity_decode( $x[2], ENT_QUOTES ) );
				foreach ( explode( ',', $v ) as $j => $f ) {
					$f = trim( $f );
					// font: kısaltmasında ilk parçanın sonundaki ad ailedir (700 1rem/1.2 Nunito).
					if ( empty( $x[1] ) && 0 === $j ) {
						$f = preg_replace( '/^.*\d[\w%.\/]*\s+/', '', $f );
					}
					$f = strtolower( trim( $f, " \t\"'" ) );
					if ( '' !== $f ) {
						$used[ $f ] = true;
					}
				}
			}
		}
		unset( $used['inherit'], $used['initial'], $used['unset'], $used['revert'] );
		return $used;
	}

	/** En fazla iki ailenin normal (400) yüzü için preload bağlantıları. */
	private static function preload_links( $faces, $used, $html ) {
		$pick = array();
		foreach ( $faces as $face ) {
			$fam = preg_match( '/font-family\s*:\s*([^;}]+)/i', $face, $x ) ? strtolower( trim( $x[1], " \t\"'" ) ) : '';
			if ( '' === $fam || ! isset( $used[ $fam ] ) || ! preg_match( '/url\(\s*["\']?([^"\')]+\.woff2[^"\')]*)/i', $face, $u ) ) {
				continue;
			}
			if ( preg_match( '/font-style\s*:\s*italic/i', $face ) ) {
				continue;
			}
			$w     = preg_match( '/font-weight\s*:\s*([^;}]+)/i', $face, $wm ) ? preg_split( '/\s+/', trim( $wm[1] ) ) : array( '400' );
			$lo    = (int) ( 'normal' === $w[0] ? 400 : ( 'bold' === $w[0] ? 700 : $w[0] ) );
			$hi    = isset( $w[1] ) ? (int) $w[1] : $lo;
			$score = ( $lo <= 400 && $hi >= 400 ) ? 0 : abs( $lo - 400 );
			// Latin dışı alt kümeleri (unicode-range U+0400 vb.) tercih etme.
			if ( preg_match( '/unicode-range\s*:\s*([^;}]+)/i', $face, $ur ) && false === stripos( $ur[1], 'U+0000' ) && false === stripos( $ur[1], 'U+0020' ) ) {
				$score += 1000;
			}
			if ( ! isset( $pick[ $fam ] ) || $score < $pick[ $fam ][0] ) {
				$pick[ $fam ] = array( $score, $u[1] );
			}
		}
		$out = '';
		foreach ( array_slice( $pick, 0, 2 ) as $p ) {
			$href = $p[1];
			if ( false !== strpos( $html, 'rel="preload"' ) && false !== strpos( $html, $href . '"' ) ) {
				continue;
			}
			$out .= '<link rel="preload" href="' . esc_url( $href ) . '" as="font" type="font/woff2" crossorigin>';
		}
		return $out;
	}

	/* ------------------------------------------------------------ Animasyon denetimi */

	/** Ön yüzde görülen yerel CSS dosyaları (animasyon denetimi için, 6 saatte bir yazılır). */
	private static function seen_due() {
		$s = get_option( 'flxp_ps_seen' );
		return ! is_array( $s ) || ( time() - (int) ( $s['t'] ?? 0 ) ) > 6 * HOUR_IN_SECONDS;
	}

	private static function seen_add( $path ) {
		self::$seen[ $path ] = true;
	}

	private static function seen_save() {
		if ( ! self::$seen || ! self::seen_due() ) {
			return;
		}
		$root  = wp_normalize_path( untrailingslashit( ABSPATH ) );
		$files = array();
		foreach ( array_keys( self::$seen ) as $p ) {
			$files[] = ltrim( str_replace( $root, '', wp_normalize_path( $p ) ), '/' );
		}
		update_option(
			'flxp_ps_seen',
			array(
				't'     => time(),
				'url'   => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/', // phpcs:ignore
				'files' => array_slice( $files, 0, 40 ),
			),
			true
		);
	}

	/**
	 * GPU'da çalışmayan animasyonlar: array( array( file, name, props ) ). Görülen dosya yoksa tema klasörü taranır.
	 *
	 * @return array
	 */
	public static function animation_report() {
		$cached = get_transient( 'flxp_ps_anim' );
		$seen   = get_option( 'flxp_ps_seen' );
		$sig    = md5( wp_json_encode( $seen ) . get_stylesheet() );
		if ( is_array( $cached ) && ( $cached['sig'] ?? '' ) === $sig ) {
			return $cached['items'];
		}
		$files = array();
		if ( is_array( $seen ) && ! empty( $seen['files'] ) ) {
			foreach ( $seen['files'] as $rel ) {
				$files[] = ABSPATH . $rel;
			}
		} else {
			foreach ( array_unique( array( get_stylesheet_directory(), get_template_directory() ) ) as $dir ) {
				foreach ( (array) glob( $dir . '/{,assets/,assets/css/,css/}*.css', GLOB_BRACE ) as $f ) {
					$files[] = $f;
				}
			}
		}
		$items = array();
		$done  = array();
		foreach ( array_slice( array_unique( $files ), 0, 60 ) as $f ) {
			if ( ! $f || ! is_file( $f ) || filesize( $f ) > 2 * MB_IN_BYTES ) {
				continue;
			}
			// Aynı dosyanın .min ve kaynak sürümü varsa bir kez say.
			$base = preg_replace( '/\.min\.css$/', '.css', $f );
			if ( isset( $done[ $base ] ) ) {
				continue;
			}
			$done[ $base ] = true;
			$css           = (string) file_get_contents( $f ); // phpcs:ignore
			$reduced       = (bool) preg_match( '/prefers-reduced-motion/i', $css );
			foreach ( flxp_css_noncomposited_keyframes( $css ) as $k ) {
				$items[] = array(
					'file'    => ltrim( str_replace( wp_normalize_path( untrailingslashit( ABSPATH ) ), '', wp_normalize_path( $f ) ), '/' ),
					'name'    => $k['name'],
					'props'   => $k['props'],
					'reduced' => $reduced,
				);
			}
		}
		set_transient(
			'flxp_ps_anim',
			array(
				'sig'   => $sig,
				'items' => $items,
			),
			DAY_IN_SECONDS
		);
		return $items;
	}

	/* ------------------------------------------------------------ Flux SEO / Sistem Kontrolü */

	/** Tek tık düzeltmeler: kimlik => [ayarlar, etiket, açıklama]. */
	public static function fix_map() {
		return array(
			'flxp_ps_css'      => array( array( 'ps_css' ), __( 'Flux Speed: kullanılmayan CSS\'i kırp ve satır içine al', 'flx-speed' ), __( 'Tema CSS\'inden sayfada kullanılmayan kuralları atar, kalanı <head> içine yazar; engelleyen CSS isteği kalkar. Önce birkaç sayfayı kontrol edin; sorun olursa kapatın ya da sınıfı güvenli listeye ekleyin.', 'flx-speed' ) ),
			'flxp_ps_minify'   => array( array( 'ps_min_css', 'ps_min_js' ), __( 'Flux Speed: CSS ve JS dosyalarını sıkıştır', 'flx-speed' ), __( 'Tema ve eklentilerin sıkıştırılmamış CSS/JS dosyaları için güncel .min sürümü ya da üretilmiş kopya kullanılır.', 'flx-speed' ) ),
			'flxp_ps_defer'    => array( array( 'ps_defer' ), __( 'Flux Speed: head betiklerini ertele (defer)', 'flx-speed' ), __( 'Sayfanın başındaki engelleyen betikler defer ile yüklenir. jQuery ve hariç tutulanlar dokunulmaz.', 'flx-speed' ) ),
			'flxp_ps_viewport' => array( array( 'ps_viewport' ), __( 'Flux Speed: yakınlaştırma kilidini kaldır', 'flx-speed' ), __( 'Viewport etiketinden user-scalable=no ve maximum-scale kaldırılır.', 'flx-speed' ) ),
			'flxp_ps_fonts'    => array( array( 'ps_fonts', 'ps_preload' ), __( 'Flux Speed: yazı tiplerini düzenle', 'flx-speed' ), __( 'Sayfada kullanılmayan theme.json yazı tipleri çıkarılır, ilk ekran yazı tipi önden yüklenir (preload).', 'flx-speed' ) ),
			'flxp_ps_emoji'    => array( array( 'ps_emoji' ), __( 'Flux Speed: emoji betiğini kaldır', 'flx-speed' ), __( 'WordPress\'in emoji algılama betiği ve stili yüklenmez; modern tarayıcılar emojiyi kendisi çizer.', 'flx-speed' ) ),
		);
	}

	public static function fix_is_on( $id ) {
		$keys = self::fix_map()[ $id ][0] ?? array();
		foreach ( $keys as $k ) {
			if ( empty( flxp_settings( $k ) ) ) {
				return false;
			}
		}
		return (bool) $keys;
	}

	/** Ayarları açar, önbelleği ve sayfa önbelleğini temizler. */
	public static function fix_apply( $id ) {
		$keys = self::fix_map()[ $id ][0] ?? null;
		if ( ! $keys ) {
			throw new Exception( __( 'Bilinmeyen düzeltme.', 'flx-speed' ) );
		}
		$s = get_option( 'flxp_settings', array() );
		$s = is_array( $s ) ? $s : array();
		foreach ( $keys as $k ) {
			$s[ $k ] = 1;
		}
		update_option( 'flxp_settings', $s, false );
		self::clear_cache();
		do_action( 'litespeed_purge_all' );
		return $keys;
	}

	/** Lighthouse denetim kimliği → Flux Speed düzeltmesi. */
	public static function audit_map() {
		return array(
			'render-blocking-insight'         => 'flxp_ps_css',
			'unused-css-rules'                => 'flxp_ps_css',
			'unminified-css'                  => 'flxp_ps_minify',
			'unminified-javascript'           => 'flxp_ps_minify',
			'meta-viewport'                   => 'flxp_ps_viewport',
			'font-display-insight'            => 'flxp_ps_fonts',
			'network-dependency-tree-insight' => 'flxp_ps_fonts',
			'emoji-script'                    => 'flxp_ps_emoji',
		);
	}

	/** Flux SEO site denetimi: bulgulara Flux Speed tek tık düzeltmesini bağlar. */
	public static function audit_findings( $findings ) {
		$map = self::audit_map();
		foreach ( (array) $findings as $i => $fd ) {
			$id = $fd['id'] ?? '';
			if ( 'render-blocking-insight' === $id && self::fix_is_on( 'flxp_ps_css' ) && ! self::fix_is_on( 'flxp_ps_defer' ) ) {
				$map[ $id ] = 'flxp_ps_defer';
			}
			if ( 'non-composited-animations' === $id ) {
				$findings[ $i ]['detail'] = trim( ( $fd['detail'] ?? '' ) . ' ' . __( 'Flux Speed › PageSpeed sekmesi hangi dosyada hangi animasyonun sorunlu olduğunu listeler.', 'flx-speed' ) );
				continue;
			}
			if ( ! isset( $map[ $id ] ) ) {
				continue;
			}
			$fix = $map[ $id ];
			if ( self::fix_is_on( $fix ) ) {
				$findings[ $i ]['detail'] = trim( ( $fd['detail'] ?? '' ) . ' ' . __( 'Flux Speed\'de ilgili ayar açık; önbelleği (LiteSpeed Purge All) temizleyip yeniden ölçün. Kalan kısım tema/ön ayarın işi.', 'flx-speed' ) );
				continue;
			}
			if ( empty( $fd['fix_id'] ) ) {
				$findings[ $i ]['fix_id'] = $fix;
				$findings[ $i ]['owner']  = 'flx-speed';
			}
		}
		return $findings;
	}

	public static function system_fixes( $fixes ) {
		$fixes = is_array( $fixes ) ? $fixes : array();
		foreach ( self::fix_map() as $id => $f ) {
			$fixes[ $id ] = array(
				'label'       => $f[1],
				'description' => $f[2],
				'checks'      => array( 'flxseo.audit', 'flxp.pagespeed' ),
				'source'      => 'Flux Speed',
				'value'       => null,
				'current'     => fn() => self::fix_is_on( $id ),
				'run'         => function () use ( $id ) {
					self::fix_apply( $id );
					return __( 'Açıldı. Sayfa önbelleği temizlendi; Site denetimini ya da PageSpeed ölçümünü yeniden çalıştırın.', 'flx-speed' );
				},
			);
		}
		return $fixes;
	}

	/** Sistem Kontrolü satırları. */
	public static function system_checks( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		$url    = admin_url( 'admin.php?page=flx-speed&tab=pagespeed' );
		$anim   = self::animation_report();
		$checks[] = array(
			'id'           => 'flxp.animations',
			'source'       => 'flx-speed',
			'group'        => 'Performance',
			'label'        => __( 'Animasyonlar (GPU)', 'flx-speed' ),
			'status'       => $anim ? 'warning' : 'ok',
			'message'      => $anim
				/* translators: %s: animasyon adları */
				? sprintf( __( 'GPU\'da çalışmayan animasyon: %s. transform/opacity ile yeniden yazılmalı (tema/ön ayar).', 'flx-speed' ), implode( ', ', array_slice( array_unique( wp_list_pluck( $anim, 'name' ) ), 0, 6 ) ) )
				: __( 'Görülen CSS dosyalarındaki animasyonlar transform/opacity kullanıyor.', 'flx-speed' ),
			'action_url'   => $url,
			'action_label' => __( 'PageSpeed', 'flx-speed' ),
		);
		if ( self::on( 'ps_css' ) || self::on( 'ps_min_css' ) || self::on( 'ps_min_js' ) ) {
			$ok       = (bool) self::ensure_dir( 'css' );
			$checks[] = array(
				'id'           => 'flxp.pagespeed',
				'source'       => 'flx-speed',
				'group'        => 'Performance',
				'label'        => __( 'PageSpeed önbelleği', 'flx-speed' ),
				'status'       => $ok ? 'ok' : 'error',
				'message'      => $ok ? __( 'uploads/flx-speed yazılabilir.', 'flx-speed' ) : __( 'uploads/flx-speed klasörü yazılamıyor: CSS kırpma ve sıkıştırma çalışmaz, sayfalar asıl dosyalarla açılır.', 'flx-speed' ),
				'action_url'   => $url,
				'action_label' => __( 'PageSpeed', 'flx-speed' ),
			);
		}
		return $checks;
	}

	/** Flux Setup ön ayar şeması: PageSpeed alanları (flxp_settings). */
	public static function schema( $s ) {
		$fields = array();
		foreach ( self::defaults() as $k => $v ) {
			$fields[ $k ] = is_int( $v ) && in_array( $k, self::toggles(), true ) ? array( 'type' => 'bool', 'default' => $v ) : ( is_int( $v ) ? array( 'type' => 'int', 'default' => $v ) : array( 'type' => 'text', 'default' => $v ) );
		}
		$fields['ps_css_scope'] = array(
			'type'    => 'enum',
			'enum'    => array( 'theme', 'local' ),
			'default' => 'theme',
		);
		$s['flxp_settings'] = array(
			'label'  => 'Flux Speed',
			'fields' => array_merge( (array) ( $s['flxp_settings']['fields'] ?? array() ), $fields ),
		);
		return $s;
	}
}

FLXP_PageSpeed::boot();
add_filter( 'flx_system_fixes', array( 'FLXP_PageSpeed', 'system_fixes' ) );
add_filter( 'flx_system_checks', array( 'FLXP_PageSpeed', 'system_checks' ) );
add_filter( 'flxseo_audit_findings', array( 'FLXP_PageSpeed', 'audit_findings' ) );
add_filter( 'flx_settings_schema', array( 'FLXP_PageSpeed', 'schema' ) );
