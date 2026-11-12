<?php
/**
 * Önbellek yönetimi: LiteSpeed Cache (sayfa + nesne önbelleği) ve SQLite Object Cache durumu, çakışma
 * kontrolü ve tek tık işlemler (kur, etkinleştir, geçiş, temizle, kalıcılık testi).
 *
 * Kural: sitede tek nesne önbelleği (wp-content/object-cache.php) olur. Flux Speed başka bir eklentinin
 * drop-in'ini yalnız sahibi o eklentiyse ve o eklentide nesne önbelleği kapalıysa siler.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

class FLXP_Cache {

	const TEST_OPTION = 'flxp_cache_test';
	const PROBE       = 'flxp_oc_probe';

	public static function init() {
		add_action( 'wp_ajax_nopriv_' . self::PROBE, array( __CLASS__, 'probe' ) );
		add_action( 'wp_ajax_' . self::PROBE, array( __CLASS__, 'probe' ) );
	}

	/**
	 * Yönetilen eklentiler. Anahtar → [wp.org slug, ana dosya, ad].
	 * Filtre: flxp_cache_plugins (ör. preset başka bir dosya yolu bildirir).
	 */
	public static function plugins() {
		return (array) apply_filters(
			'flxp_cache_plugins',
			array(
				'litespeed' => array(
					'slug' => 'litespeed-cache',
					'file' => 'litespeed-cache/litespeed-cache.php',
					'name' => 'LiteSpeed Cache',
				),
				'sqlite'    => array(
					'slug' => 'sqlite-object-cache',
					'file' => 'sqlite-object-cache/sqlite-object-cache.php',
					'name' => 'SQLite Object Cache',
				),
			)
		);
	}

	/** Eklenti durumu: installed, active, version. */
	public static function plugin_state( $key ) {
		$p = self::plugins()[ $key ] ?? null;
		if ( ! $p ) {
			return array( 'installed' => false, 'active' => false, 'version' => '' );
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all  = get_plugins();
		$file = $p['file'];
		if ( ! isset( $all[ $file ] ) ) {
			// Klasör adı farklı kurulmuş olabilir (ör. litespeed-cache-2): ana dosya adıyla ara.
			foreach ( $all as $f => $d ) {
				if ( basename( $f ) === basename( $file ) ) {
					$file = $f;
					break;
				}
			}
		}
		return array(
			'installed' => isset( $all[ $file ] ),
			'active'    => isset( $all[ $file ] ) && is_plugin_active( $file ),
			'version'   => isset( $all[ $file ] ) ? (string) $all[ $file ]['Version'] : '',
			'file'      => $file,
			'name'      => $p['name'],
			'slug'      => $p['slug'],
		);
	}

	/** LiteSpeed Cache ayarı (önce eklentinin kendi API'si, yoksa kayıtlı seçenek). */
	public static function ls_conf( $id ) {
		if ( defined( 'LSCWP_V' ) && has_filter( 'litespeed_conf' ) ) {
			return apply_filters( 'litespeed_conf', $id );
		}
		$v = get_option( 'litespeed.conf.' . $id, null );
		return $v;
	}

	/** wp-content içindeki drop-in'in sahibi: litespeed | sqlite | redis | memcached | other | ''. */
	public static function dropin_owner( $name = 'object-cache.php' ) {
		$path = WP_CONTENT_DIR . '/' . $name;
		if ( ! is_file( $path ) ) {
			return array( 'exists' => false, 'owner' => '', 'name' => '' );
		}
		$head = (string) @file_get_contents( $path, false, null, 0, 8192 ); // phpcs:ignore
		$low  = strtolower( $head );
		$hdr  = '';
		if ( preg_match( '/^[ \t\/*#@]*Plugin Name:(.*)$/mi', $head, $m ) ) {
			$hdr = trim( $m[1] );
		}
		$owner = 'other';
		// Önce başlıktaki eklenti adı; yoksa dosya içeriği.
		$lh = strtolower( $hdr );
		if ( '' !== $lh && false !== strpos( $lh, 'litespeed' ) ) {
			$owner = 'litespeed';
		} elseif ( '' !== $lh && false !== strpos( $lh, 'sqlite' ) ) {
			$owner = 'sqlite';
		} elseif ( '' !== $lh && false !== strpos( $lh, 'redis' ) ) {
			$owner = 'redis';
		} elseif ( false !== strpos( $low, 'sqlite' ) ) {
			$owner = 'sqlite';
		} elseif ( false !== strpos( $low, 'litespeed' ) ) {
			$owner = 'litespeed';
		} elseif ( false !== strpos( $low, 'redis' ) ) {
			$owner = 'redis';
		} elseif ( false !== strpos( $low, 'memcache' ) ) {
			$owner = 'memcached';
		}
		if ( 'advanced-cache.php' === $name ) {
			foreach ( array( 'wp rocket' => 'wp-rocket', 'w3 total' => 'w3tc', 'wp super cache' => 'wpsc', 'wp-super-cache' => 'wpsc', 'wp fastest' => 'wpfc', 'cache enabler' => 'cache-enabler', 'litespeed' => 'litespeed' ) as $needle => $id ) {
				if ( false !== strpos( $low, $needle ) ) {
					$owner = $id;
					break;
				}
			}
		}
		return array( 'exists' => true, 'owner' => $owner, 'name' => $hdr );
	}

	/** SQLite Object Cache veritabanı dosyası (varsa yol + boyut). */
	public static function sqlite_file() {
		$cands = array();
		if ( defined( 'WP_SQLITE_OBJECT_CACHE_DB_FILE' ) ) {
			$cands[] = (string) WP_SQLITE_OBJECT_CACHE_DB_FILE;
		}
		$cands[] = WP_CONTENT_DIR . '/.ht.object-cache.sqlite';
		$cands[] = WP_CONTENT_DIR . '/.ht.object_cache.sqlite';
		foreach ( $cands as $f ) {
			if ( is_file( $f ) ) {
				$size = (int) @filesize( $f ); // phpcs:ignore
				foreach ( array( '-wal', '-shm' ) as $sfx ) {
					if ( is_file( $f . $sfx ) ) {
						$size += (int) @filesize( $f . $sfx ); // phpcs:ignore
					}
				}
				return array( 'path' => $f, 'size' => $size );
			}
		}
		return array( 'path' => '', 'size' => 0 );
	}

	/** Sunucu: LiteSpeed web sunucusu mu? */
	public static function is_litespeed_server() {
		$sw = isset( $_SERVER['SERVER_SOFTWARE'] ) ? (string) $_SERVER['SERVER_SOFTWARE'] : ''; // phpcs:ignore
		return isset( $_SERVER['X-LSCACHE'] ) || isset( $_SERVER['LSWS_EDITION'] ) || false !== stripos( $sw, 'litespeed' ) || false !== stripos( $sw, 'openlitespeed' ); // phpcs:ignore
	}

	/** Tüm durum (ucuz: dosya başlığı, seçenekler, uzantılar; dış istek yok). */
	public static function status() {
		$ls   = self::plugin_state( 'litespeed' );
		$sq   = self::plugin_state( 'sqlite' );
		$drop = self::dropin_owner( 'object-cache.php' );
		$adv  = self::dropin_owner( 'advanced-cache.php' );

		$ls_page   = null;
		$ls_object = null;
		$ls_kind   = '';
		$ls_host   = '';
		$ls_port   = '';
		if ( $ls['installed'] ) {
			$ls_page   = (bool) self::ls_conf( 'cache' );
			$ls_object = (bool) self::ls_conf( 'object' );
			$ls_kind   = self::ls_conf( 'object-kind' ) ? 'redis' : 'memcached';
			$ls_host   = (string) self::ls_conf( 'object-host' );
			$ls_port   = (string) self::ls_conf( 'object-port' );
		}
		// Eklenti kapalıysa ayarı çalışmaz.
		$ls_object_live = $ls['active'] && $ls_object;

		$ext = array(
			'memcached' => class_exists( 'Memcached' ) || class_exists( 'Memcache' ),
			'redis'     => class_exists( 'Redis' ),
			'sqlite3'   => class_exists( 'SQLite3' ),
			'igbinary'  => function_exists( 'igbinary_serialize' ),
			'apcu'      => function_exists( 'apcu_fetch' ) && (bool) ini_get( 'apc.enabled' ),
		);

		$provider = 'none';
		if ( $drop['exists'] ) {
			$provider = $drop['owner'];
		}

		$conflicts = array();
		if ( $ls_object_live && $sq['active'] ) {
			$conflicts[] = 'both_on';
		}
		if ( $sq['active'] && 'sqlite' !== $drop['owner'] && ! in_array( 'both_on', $conflicts, true ) ) {
			$conflicts[] = $drop['exists'] ? 'sqlite_dropin_taken' : 'sqlite_dropin_missing';
		}
		if ( $ls_object_live && ! $sq['active'] ) {
			if ( $drop['exists'] && 'litespeed' !== $drop['owner'] ) {
				$conflicts[] = 'ls_dropin_taken';
			}
			$need = 'redis' === $ls_kind ? $ext['redis'] : $ext['memcached'];
			if ( ! $need ) {
				$conflicts[] = 'ls_no_backend';
			}
		}
		if ( ! $sq['active'] && 'sqlite' === $drop['owner'] ) {
			$conflicts[] = 'sqlite_orphan';
		}
		if ( $ls['active'] && $adv['exists'] && ! in_array( $adv['owner'], array( 'litespeed', 'other' ), true ) ) {
			$conflicts[] = 'two_page_caches';
		}

		$test = get_option( self::TEST_OPTION );

		return array(
			'server'     => array(
				'litespeed' => self::is_litespeed_server(),
				'software'  => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '', // phpcs:ignore
				'ext'       => $ext,
			),
			'litespeed'  => $ls + array(
				'page_cache'  => $ls_page,
				'object'      => $ls_object,
				'object_kind' => $ls_kind,
				'object_host' => $ls_host,
				'object_port' => $ls_port,
			),
			'sqlite'     => $sq + array( 'db' => self::sqlite_file() ),
			'dropin'     => $drop,
			'page_dropin'=> $adv,
			'persistent' => (bool) wp_using_ext_object_cache(),
			'provider'   => $provider,
			'conflicts'  => $conflicts,
			'test'       => is_array( $test ) ? $test : null,
		);
	}

	/** Kullanıcıya gösterilecek çakışma metni. */
	public static function conflict_text( $code, $st = null ) {
		$st = $st ? $st : self::status();
		switch ( $code ) {
			case 'both_on':
				return __( 'Two object caches are on at the same time: LiteSpeed Cache object cache and SQLite Object Cache. Only one can own object-cache.php; choose one below.', 'flx-speed' );
			case 'sqlite_dropin_taken':
				return __( 'SQLite Object Cache is active but object-cache.php belongs to another plugin, so SQLite is not used. Switch to SQLite below to fix it.', 'flx-speed' );
			case 'sqlite_dropin_missing':
				return __( 'SQLite Object Cache is active but object-cache.php is missing, so nothing is cached. Use "Repair" to recreate it.', 'flx-speed' );
			case 'ls_dropin_taken':
				return __( 'LiteSpeed Cache object cache is on but object-cache.php belongs to another plugin. Turn one of them off.', 'flx-speed' );
			case 'ls_no_backend':
				return 'redis' === ( $st['litespeed']['object_kind'] ?? '' )
					? __( 'LiteSpeed Cache object cache is set to Redis but the server has no Redis PHP extension; WordPress silently falls back to no persistent cache.', 'flx-speed' )
					: __( 'LiteSpeed Cache object cache is set to Memcached but the Memcached PHP extension is missing. On Hostinger enable it in hPanel (Advanced › PHP Configuration › Memcached), or use SQLite Object Cache instead.', 'flx-speed' );
			case 'sqlite_orphan':
				return __( 'object-cache.php from SQLite Object Cache is still in wp-content although the plugin is not active. Repair removes it or turns the plugin back on.', 'flx-speed' );
			case 'two_page_caches':
				/* translators: %s: diğer önbellek eklentisinin kısa adı */
				return sprintf( __( 'Another page cache plugin (%s) is installed next to LiteSpeed Cache (advanced-cache.php). Two page caches serve stale pages; keep only LiteSpeed Cache.', 'flx-speed' ), (string) ( $st['page_dropin']['owner'] ?? '' ) );
		}
		return $code;
	}

	/* ------------------------------------------------------------------ İşlemler */

	/** wp.org'dan kur (varsa atla). WP_Error ya da true. */
	public static function install( $key ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return new WP_Error( 'cap', __( 'You are not allowed to install plugins.', 'flx-speed' ) );
		}
		$st = self::plugin_state( $key );
		if ( $st['installed'] ) {
			return true;
		}
		if ( ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) ) {
			return new WP_Error( 'mods', __( 'Plugin installs are disabled on this site (DISALLOW_FILE_MODS). Install it from your hosting panel.', 'flx-speed' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$api = plugins_api( 'plugin_information', array( 'slug' => $st['slug'], 'fields' => array( 'sections' => false, 'short_description' => false ) ) );
		if ( is_wp_error( $api ) ) {
			return $api;
		}
		$skin = new WP_Ajax_Upgrader_Skin();
		$up   = new Plugin_Upgrader( $skin );
		ob_start();
		$res = $up->install( $api->download_link );
		ob_end_clean();
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( is_wp_error( $skin->result ) ) {
			return $skin->result;
		}
		if ( $skin->get_errors()->has_errors() ) {
			return $skin->get_errors();
		}
		if ( ! $res ) {
			return new WP_Error( 'fs', __( 'The plugin could not be written to disk (file permissions or FTP credentials). Install it from Plugins › Add New.', 'flx-speed' ) );
		}
		wp_clean_plugins_cache();
		return true;
	}

	/** Etkinleştir. */
	public static function activate( $key ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error( 'cap', __( 'You are not allowed to activate plugins.', 'flx-speed' ) );
		}
		$st = self::plugin_state( $key );
		if ( ! $st['installed'] ) {
			return new WP_Error( 'missing', __( 'The plugin is not installed.', 'flx-speed' ) );
		}
		if ( $st['active'] ) {
			return true;
		}
		ob_start();
		$r = activate_plugin( $st['file'], '', false, false );
		ob_end_clean();
		return is_wp_error( $r ) ? $r : true;
	}

	public static function deactivate( $key ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error( 'cap', __( 'You are not allowed to activate plugins.', 'flx-speed' ) );
		}
		$st = self::plugin_state( $key );
		if ( $st['active'] ) {
			ob_start();
			deactivate_plugins( $st['file'], false, false );
			ob_end_clean();
		}
		return true;
	}

	/** LiteSpeed Cache ayarlarını kalıcı yazar (eklentinin kendi API'si). */
	public static function ls_update( array $confs ) {
		if ( ! defined( 'LSCWP_V' ) || ! has_action( 'litespeed_update_confs' ) ) {
			return new WP_Error( 'ls', __( 'LiteSpeed Cache is not active.', 'flx-speed' ) );
		}
		do_action( 'litespeed_update_confs', $confs );
		return true;
	}

	/** Drop-in'i yalnız sahibi $owner ise siler. */
	private static function remove_dropin_if( $owner ) {
		$d = self::dropin_owner();
		if ( $d['exists'] && $owner === $d['owner'] ) {
			wp_delete_file( WP_CONTENT_DIR . '/object-cache.php' );
			return ! is_file( WP_CONTENT_DIR . '/object-cache.php' );
		}
		return false;
	}

	/**
	 * Nesne önbelleği SQLite Object Cache olsun: LiteSpeed nesne önbelleği kapanır, onun drop-in'i kalkar,
	 * SQLite kurulur/etkinleşir (etkinleşince kendi drop-in'ini yazar).
	 */
	public static function use_sqlite() {
		$st = self::status();
		if ( ! $st['server']['ext']['sqlite3'] ) {
			return new WP_Error( 'ext', __( 'The PHP sqlite3 extension is missing. Enable it in your hosting PHP settings first.', 'flx-speed' ) );
		}
		if ( $st['dropin']['exists'] && ! in_array( $st['dropin']['owner'], array( 'litespeed', 'sqlite' ), true ) ) {
			/* translators: %s: drop-in eklentisinin adı */
			return new WP_Error( 'other', sprintf( __( 'object-cache.php belongs to another plugin (%s). Turn that plugin\'s object cache off first.', 'flx-speed' ), $st['dropin']['name'] ? $st['dropin']['name'] : $st['dropin']['owner'] ) );
		}
		if ( $st['litespeed']['active'] && $st['litespeed']['object'] ) {
			$r = self::ls_update( array( 'object' => false ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		self::remove_dropin_if( 'litespeed' );
		$r = self::install( 'sqlite' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$sq = self::plugin_state( 'sqlite' );
		if ( $sq['active'] && 'sqlite' !== self::dropin_owner()['owner'] ) {
			// Etkin ama drop-in yok: etkinleştirme kancası drop-in'i yeniden yazsın.
			self::deactivate( 'sqlite' );
		}
		$r = self::activate( 'sqlite' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( 'sqlite' !== self::dropin_owner()['owner'] ) {
			return new WP_Error( 'dropin', __( 'SQLite Object Cache is active but it did not write object-cache.php (wp-content may not be writable). Open Settings › Object Cache to finish.', 'flx-speed' ) );
		}
		delete_option( self::TEST_OPTION );
		return true;
	}

	/** Nesne önbelleği LiteSpeed Cache (Memcached) olsun: SQLite kapanır, LiteSpeed nesne önbelleği açılır. */
	public static function use_litespeed() {
		$st = self::status();
		if ( ! $st['server']['ext']['memcached'] && ! $st['server']['ext']['redis'] ) {
			return new WP_Error( 'ext', __( 'There is no Memcached or Redis PHP extension. On Hostinger enable Memcached in hPanel first, then try again.', 'flx-speed' ) );
		}
		if ( $st['dropin']['exists'] && ! in_array( $st['dropin']['owner'], array( 'litespeed', 'sqlite' ), true ) ) {
			/* translators: %s: drop-in eklentisinin adı */
			return new WP_Error( 'other', sprintf( __( 'object-cache.php belongs to another plugin (%s). Turn that plugin\'s object cache off first.', 'flx-speed' ), $st['dropin']['name'] ? $st['dropin']['name'] : $st['dropin']['owner'] ) );
		}
		$r = self::install( 'litespeed' );
		if ( ! is_wp_error( $r ) ) {
			$r = self::activate( 'litespeed' );
		}
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		self::deactivate( 'sqlite' );
		self::remove_dropin_if( 'sqlite' );
		if ( ! defined( 'LSCWP_V' ) ) {
			// Bu istekte yeni etkinleşti; ayar bir sonraki sayfa açılışında yazılsın.
			update_option( 'flxp_cache_pending', 'litespeed_object', false );
			return 'pending';
		}
		return self::ls_enable_object();
	}

	/** LiteSpeed nesne önbelleğini açar (Memcached, Redis yalnız Memcached yoksa). */
	public static function ls_enable_object() {
		$memc  = class_exists( 'Memcached' ) || class_exists( 'Memcache' );
		$confs = array( 'object' => true );
		if ( $memc ) {
			$confs['object-kind'] = false;
			$port                 = (string) self::ls_conf( 'object-port' );
			if ( '' === trim( (string) self::ls_conf( 'object-host' ) ) || '6379' === $port ) {
				$confs['object-host'] = 'localhost';
				$confs['object-port'] = 11211;
			}
		} else {
			$confs['object-kind'] = true;
			if ( '11211' === (string) self::ls_conf( 'object-port' ) ) {
				$confs['object-port'] = 6379;
			}
		}
		$r = self::ls_update( $confs );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		delete_option( 'flxp_cache_pending' );
		delete_option( self::TEST_OPTION );
		if ( 'litespeed' !== self::dropin_owner()['owner'] ) {
			return new WP_Error( 'dropin', __( 'The setting was saved but LiteSpeed Cache did not write object-cache.php yet. Open LiteSpeed Cache › Cache › Object and press Save once.', 'flx-speed' ) );
		}
		return true;
	}

	/** Nesne önbelleğini tamamen kapatır (iki eklentide de). */
	public static function object_off() {
		$st = self::status();
		if ( $st['litespeed']['active'] && $st['litespeed']['object'] ) {
			self::ls_update( array( 'object' => false ) );
		}
		self::remove_dropin_if( 'litespeed' );
		self::deactivate( 'sqlite' );
		self::remove_dropin_if( 'sqlite' );
		delete_option( self::TEST_OPTION );
		return true;
	}

	/** Çakışmayı onarır: drop-in'i etkin sağlayıcıya göre düzeltir. */
	public static function repair() {
		$st = self::status();
		if ( $st['sqlite']['active'] ) {
			return self::use_sqlite();
		}
		if ( 'sqlite' === $st['dropin']['owner'] ) {
			self::remove_dropin_if( 'sqlite' );
		}
		if ( $st['litespeed']['active'] && $st['litespeed']['object'] ) {
			return self::ls_enable_object();
		}
		return true;
	}

	/** Sayfa önbelleği aç/kapa (LiteSpeed Cache). */
	public static function page_cache( $on ) {
		$r = self::ls_update( array( 'cache' => (bool) $on ) );
		if ( ! is_wp_error( $r ) ) {
			do_action( 'litespeed_purge_all', 'Flux Speed: page cache ' . ( $on ? 'on' : 'off' ) );
		}
		return $r;
	}

	/** Tümünü temizle: LiteSpeed (sayfa, CSS/JS, nesne) + WordPress nesne önbelleği. */
	public static function purge_all() {
		do_action( 'litespeed_purge_all', 'Flux Speed' );
		wp_cache_flush();
		return true;
	}

	/* ------------------------------------------------------------------ Kalıcılık testi */

	/**
	 * Gerçek test: bu istekte bir değer yazılır, ayrı bir istek (loopback) okur. Okuyabiliyorsa önbellek
	 * istekler arasında kalıcıdır. Ayrıca 200 yaz/oku süresi ölçülür.
	 */
	public static function run_test() {
		$token = wp_generate_password( 20, false );
		$id    = time();
		wp_cache_set( 'probe', $token, 'flxp', 300 );
		$t0 = microtime( true );
		for ( $i = 0; $i < 100; $i++ ) {
			wp_cache_set( 'bench' . $i, str_repeat( 'x', 512 ), 'flxp', 60 );
		}
		for ( $i = 0; $i < 100; $i++ ) {
			wp_cache_get( 'bench' . $i, 'flxp', true );
		}
		$ms   = round( ( microtime( true ) - $t0 ) * 1000, 1 );
		$res  = wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			array(
				'timeout'   => 15,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'body'      => array(
					'action' => self::PROBE,
					'id'     => $id,
					'sig'    => function_exists( 'flx_job_sig' ) ? flx_job_sig( self::PROBE, $id ) : '',
				),
			)
		);
		$out = array(
			't'          => time(),
			'persistent' => false,
			'ms'         => $ms,
			'provider'   => self::dropin_owner()['owner'],
			'error'      => '',
		);
		if ( is_wp_error( $res ) ) {
			$out['error'] = $res->get_error_message();
		} else {
			$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
			if ( is_array( $body ) && isset( $body['v'] ) ) {
				$out['persistent'] = hash_equals( $token, (string) $body['v'] );
			} else {
				/* translators: %d: HTTP durum kodu */
				$out['error'] = sprintf( __( 'The test request returned HTTP %d.', 'flx-speed' ), (int) wp_remote_retrieve_response_code( $res ) );
			}
		}
		wp_cache_delete( 'probe', 'flxp' );
		update_option( self::TEST_OPTION, $out, false );
		return $out;
	}

	/** Loopback ucu: imzalı istek önbellekteki test değerini döndürür. */
	public static function probe() {
		$ok = function_exists( 'flx_job_check' ) && flx_job_check( self::PROBE );
		if ( ! $ok ) {
			wp_send_json( array( 'error' => 'sig' ), 403 );
		}
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore
		if ( abs( time() - $id ) > 120 ) {
			wp_send_json( array( 'error' => 'old' ), 403 );
		}
		$v = wp_cache_get( 'probe', 'flxp', true );
		wp_send_json( array( 'v' => false === $v ? '' : (string) $v ) );
	}

	/** LiteSpeed yeni etkinleştiyse bekleyen nesne önbelleği ayarını tamamlar. */
	public static function maybe_finish_pending() {
		if ( 'litespeed_object' === get_option( 'flxp_cache_pending' ) && defined( 'LSCWP_V' ) && current_user_can( 'manage_options' ) ) {
			return self::ls_enable_object();
		}
		return null;
	}
}
FLXP_Cache::init();

/** Sistem Kontrolü satırları: nesne önbelleği ve sayfa önbelleği (ucuz, dış istek yok). */
add_filter(
	'flx_system_checks',
	function ( $checks ) {
		$checks = is_array( $checks ) ? $checks : array();
		$st     = FLXP_Cache::status();
		$src    = __( 'Flux Performance', 'flx-speed' );
		$url    = admin_url( 'admin.php?page=flx-speed&tab=cache' );
		if ( $st['conflicts'] ) {
			$hard = array_intersect( $st['conflicts'], array( 'both_on', 'sqlite_dropin_taken', 'ls_dropin_taken', 'sqlite_dropin_missing' ) );
			$checks[] = array(
				'id'           => 'flxp.cache_conflict',
				'source'       => $src,
				'group'        => 'Performance',
				'label'        => __( 'Cache conflict', 'flx-speed' ),
				'status'       => $hard ? 'error' : 'warning',
				'message'      => implode( ' ', array_map( function ( $c ) use ( $st ) {
					return FLXP_Cache::conflict_text( $c, $st );
				}, $st['conflicts'] ) ),
				'action_url'   => $url,
				'action_label' => __( 'Cache', 'flx-speed' ),
			);
		}
		$checks[] = array(
			'id'           => 'flxp.object_cache',
			'source'       => $src,
			'group'        => 'Performance',
			'label'        => __( 'Object cache', 'flx-speed' ),
			'status'       => $st['persistent'] ? 'ok' : 'info',
			'message'      => $st['persistent']
				/* translators: %s: sağlayıcı */
				? sprintf( __( 'Persistent object cache is on (%s).', 'flx-speed' ), 'sqlite' === $st['provider'] ? 'SQLite Object Cache' : ( 'litespeed' === $st['provider'] ? 'LiteSpeed Cache' : ( $st['dropin']['name'] ? $st['dropin']['name'] : $st['provider'] ) ) )
				: __( 'No persistent object cache: every request reads options and queries from the database again. Turn one on from Performance › Cache.', 'flx-speed' ),
			'action_url'   => $url,
			'action_label' => __( 'Cache', 'flx-speed' ),
		);
		if ( $st['server']['litespeed'] && ! ( $st['litespeed']['active'] && $st['litespeed']['page_cache'] ) ) {
			$checks[] = array(
				'id'           => 'flxp.page_cache',
				'source'       => $src,
				'group'        => 'Performance',
				'label'        => __( 'Page cache', 'flx-speed' ),
				'status'       => 'warning',
				'message'      => $st['litespeed']['active']
					? __( 'The server is LiteSpeed but the LiteSpeed Cache page cache is off.', 'flx-speed' )
					: __( 'The server is LiteSpeed but the LiteSpeed Cache plugin is not active, so pages are not cached.', 'flx-speed' ),
				'action_url'   => $url,
				'action_label' => __( 'Cache', 'flx-speed' ),
			);
		}
		return $checks;
	}
);
