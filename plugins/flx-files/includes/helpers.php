<?php
/**
 * Ayarlar, yetki, veri klasörü ve koruma kuralları.
 *
 * @package FLX_Files
 */

defined( 'ABSPATH' ) || exit;

/** Varsayılan ayarlar. */
function flxfm_defaults() {
	return array(
		'show_hidden'  => 1,  // .htaccess gibi noktalı dosyalar görünür.
		'trash_days'   => 30, // Çöp kutusu bu kadar gün saklar (0 = elle boşaltılana kadar).
		'versions'     => 10, // Dosya başına saklanan önceki sürüm.
		'mcp_write'    => 0,  // Dış MCP istemcileri değişiklik yapabilir mi.
		'ai_code'      => 0,  // AI/MCP .php/.js gibi çalıştırılabilir dosya yazabilir mi.
		'quota_gb'     => 0,  // Hosting paketinin disk kotası (GB). 0: yalnız kullanılan gösterilir.
		'shared'       => array(), // Sitede [flx_files] ile yayınlanan klasörler (uploads altında, göreli yol).
	);
}

/** Ayarlar (eksikler varsayılanla). */
function flxfm_settings() {
	$s = get_option( 'flxfm_settings', array() );
	$s = is_array( $s ) ? $s : array();
	return array_merge( flxfm_defaults(), $s );
}

/** Tek ayar. */
function flxfm_opt( $key ) {
	$s = flxfm_settings();
	return $s[ $key ] ?? null;
}

/** Ayarları doğrulayıp kaydeder. */
function flxfm_save_settings( array $in ) {
	$s = flxfm_settings();
	foreach ( array( 'show_hidden', 'mcp_write', 'ai_code' ) as $k ) {
		if ( array_key_exists( $k, $in ) ) {
			$s[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
	}
	if ( array_key_exists( 'trash_days', $in ) ) {
		$s['trash_days'] = max( 0, min( 365, (int) $in['trash_days'] ) );
	}
	if ( array_key_exists( 'quota_gb', $in ) ) {
		$s['quota_gb'] = max( 0, min( 100000, round( (float) $in['quota_gb'], 1 ) ) );
	}
	if ( array_key_exists( 'versions', $in ) ) {
		$s['versions'] = max( 0, min( 50, (int) $in['versions'] ) );
	}
	if ( array_key_exists( 'shared', $in ) && is_array( $in['shared'] ) ) {
		$out = array();
		foreach ( $in['shared'] as $rel ) {
			$rel = flxfm_clean( (string) $rel );
			if ( ! is_wp_error( $rel ) && flxfm_shareable( $rel ) ) {
				$out[] = $rel;
			}
		}
		$s['shared'] = array_values( array_unique( $out ) );
	}
	update_option( 'flxfm_settings', $s, false );
	return $s;
}

/*
 * Yetki: `flxfm_manage`. Varsayılan yalnız yönetici (çoklu sitede ağ yöneticisi).
 * DISALLOW_FILE_MODS açıksa dosya yöneticisi salt okunur kalır.
 */
add_filter(
	'user_has_cap',
	static function ( $all, $caps, $args, $user ) {
		if ( ! in_array( 'flxfm_manage', (array) $caps, true ) ) {
			return $all;
		}
		$ok = is_multisite() ? is_super_admin( $user->ID ) : ! empty( $all['manage_options'] );
		$ok = (bool) apply_filters( 'flxfm_can_manage', $ok, $user );
		if ( $ok ) {
			$all['flxfm_manage'] = true;
		}
		return $all;
	},
	10,
	4
);

/** Değişiklik yapılabilir mi (DISALLOW_FILE_MODS kapalı). */
function flxfm_writable_mode() {
	return ! ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS );
}

/** Kök klasör (varsayılan WordPress kökü). Filtre `flxfm_root`. */
function flxfm_root() {
	static $root = null;
	if ( null === $root ) {
		$r    = (string) apply_filters( 'flxfm_root', ABSPATH );
		$real = realpath( $r );
		$root = untrailingslashit( wp_normalize_path( $real ? $real : $r ) );
	}
	return $root;
}

/**
 * Göreli yolu temizler: ters bölü → bölü, boş ve "." parçalar atılır. ".." ya da NUL içeren yol reddedilir.
 *
 * @return string|WP_Error
 */
function flxfm_clean( $rel ) {
	$rel = str_replace( '\\', '/', (string) $rel );
	if ( false !== strpos( $rel, "\0" ) ) {
		return new WP_Error( 'flxfm_path', __( 'Invalid path.', 'flx-files' ), array( 'status' => 400 ) );
	}
	$out = array();
	foreach ( explode( '/', $rel ) as $p ) {
		if ( '' === $p || '.' === $p ) {
			continue;
		}
		if ( '..' === $p ) {
			return new WP_Error( 'flxfm_path', __( 'Invalid path.', 'flx-files' ), array( 'status' => 400 ) );
		}
		$out[] = $p;
	}
	return implode( '/', $out );
}

/** Dosya/klasör adı geçerli mi (tek parça, kontrol karakteri yok, 1-255 bayt). */
function flxfm_valid_name( $name ) {
	$name = (string) $name;
	return '' !== $name && '.' !== $name && '..' !== $name && strlen( $name ) <= 255
		&& ! preg_match( '#[/\\\\\x00-\x1F\x7F]#', $name );
}

/** Mutlak yoldan köke göre göreli yol ('' = kök). Kök dışıysa false. */
function flxfm_rel_of( $abs ) {
	$abs  = untrailingslashit( wp_normalize_path( (string) $abs ) );
	$root = flxfm_root();
	if ( $abs === $root ) {
		return '';
	}
	if ( 0 === strpos( $abs, $root . '/' ) ) {
		return substr( $abs, strlen( $root ) + 1 );
	}
	return false;
}

/** Eklentinin özel veri klasörü (çöp, sürümler, geçici). Web erişimi kapalı; adı tahmin edilemez. */
function flxfm_data_dir( $sub = '' ) {
	$name = get_option( 'flxfm_data' );
	if ( ! is_string( $name ) || ! preg_match( '/^flx-files-[a-z0-9]{12}$/', $name ) ) {
		$name = 'flx-files-' . strtolower( wp_generate_password( 12, false, false ) );
		update_option( 'flxfm_data', $name, false );
	}
	$dir = wp_normalize_path( WP_CONTENT_DIR ) . '/' . $name;
	if ( ! is_dir( $dir ) ) {
		flx_private_dir( $dir );
	}
	if ( '' !== $sub ) {
		$dir .= '/' . $sub;
		if ( ! is_dir( $dir ) ) {
			flx_private_dir( $dir );
		}
	}
	return $dir;
}

/** Veri klasörünün köke göre yolu. */
function flxfm_data_rel() {
	$rel = flxfm_rel_of( flxfm_data_dir() );
	return false === $rel ? '' : $rel;
}

/** Yol veri klasörünün içinde mi. */
function flxfm_in_data( $rel ) {
	$d = flxfm_data_rel();
	return '' !== $d && ( $rel === $d || 0 === strpos( $rel . '/', $d . '/' ) );
}

/** Uploads klasörünün köke göre yolu. */
function flxfm_uploads_rel() {
	$u   = wp_get_upload_dir();
	$rel = flxfm_rel_of( realpath( $u['basedir'] ) ? realpath( $u['basedir'] ) : $u['basedir'] );
	return false === $rel ? 'wp-content/uploads' : $rel;
}

/** Klasör sitede kütüphane olarak yayınlanabilir mi (yalnız uploads altında). */
function flxfm_shareable( $rel ) {
	$up = flxfm_uploads_rel();
	return '' !== $rel && 0 === strpos( $rel . '/', $up . '/' ) && $rel !== $up && is_dir( flxfm_root() . '/' . $rel );
}

/**
 * Silinemeyen, taşınamayan, yeniden adlandırılamayan yollar: kök, çekirdek klasörler, wp-config ve eklentinin kendisi.
 * Filtre `flxfm_locked_paths`.
 */
function flxfm_locked_paths() {
	$plugin = flxfm_rel_of( untrailingslashit( FLXFM_DIR ) );
	$list   = array(
		'',
		'wp-admin',
		'wp-includes',
		'wp-content',
		'wp-content/plugins',
		'wp-content/themes',
		'wp-content/mu-plugins',
		flxfm_uploads_rel(),
		'wp-config.php',
		'index.php',
		'wp-load.php',
		'wp-settings.php',
		'wp-blog-header.php',
		'.htaccess',
		flxfm_data_rel(),
	);
	if ( false !== $plugin ) {
		$list[] = $plugin;
	}
	return array_values( array_unique( (array) apply_filters( 'flxfm_locked_paths', $list ) ) );
}

/** Yol kilitli mi. */
function flxfm_is_locked( $rel ) {
	return in_array( $rel, flxfm_locked_paths(), true ) || flxfm_in_data( $rel );
}

/**
 * Gizli bilgi taşıyan dosya: wp-config, .env, anahtar/parola dosyaları. AI ve MCP okuyamaz; panelde düzenleme ek onay ister.
 * Filtre `flxfm_is_sensitive`.
 */
function flxfm_is_sensitive( $rel ) {
	$base = strtolower( basename( (string) $rel ) );
	$hit  = in_array( $base, array( 'wp-config.php', 'wp-config-sample.php', '.htpasswd', '.user.ini', 'php.ini', 'auth.json', '.npmrc', 'id_rsa', 'id_ed25519', '.my.cnf' ), true )
		|| 0 === strpos( $base, '.env' )
		|| (bool) preg_match( '/\.(pem|key|p12|pfx|crt|keystore|jks|sqlite|db|sql|sql\.gz|wpress)$/', $base )
		|| ( false !== strpos( $base, 'wp-config' ) && '.php' === substr( $base, -4 ) )
		|| flxfm_in_data( (string) $rel );
	return (bool) apply_filters( 'flxfm_is_sensitive', $hit, $rel );
}

/** Çekirdek dosya (wp-admin, wp-includes, kökteki wp-*.php): düzenlemede uyarı, AI yazamaz. */
function flxfm_is_core( $rel ) {
	$rel = (string) $rel;
	return 0 === strpos( $rel . '/', 'wp-admin/' ) || 0 === strpos( $rel . '/', 'wp-includes/' )
		|| ( false === strpos( $rel, '/' ) && (bool) preg_match( '/^(wp-[a-z-]+\.php|index\.php|xmlrpc\.php)$/', $rel ) );
}

/** Sunucuda çalışabilen uzantılar (.php, .phtml …) ya da sunucu ayarı dosyası. */
function flxfm_is_executable( $rel ) {
	$base = strtolower( basename( (string) $rel ) );
	return (bool) preg_match( '/\.(php\d?|phtml|phar|phps|pht|shtml|cgi|pl|py|sh|asp|aspx|jsp)$/', $base )
		|| in_array( $base, array( '.htaccess', '.user.ini', 'php.ini', 'web.config', 'nginx.conf' ), true );
}

/** AI ya da MCP için kod sayılan dosyalar (.js, .css, .html dahil). */
function flxfm_is_code( $rel ) {
	return flxfm_is_executable( $rel ) || (bool) preg_match( '/\.(js|mjs|cjs|html?|svg|css|json|xml)$/i', (string) $rel );
}

/** DISALLOW_FILE_EDIT açıksa panel de .php gibi dosyaları yazamaz. */
function flxfm_code_edit_allowed() {
	return ! ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT );
}

/** Uzantıya göre tür grubu (simge ve önizleme için). */
function flxfm_kind( $name, $is_dir = false ) {
	if ( $is_dir ) {
		return 'dir';
	}
	$ext = strtolower( pathinfo( (string) $name, PATHINFO_EXTENSION ) );
	$map = array(
		'image'   => array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico', 'svg', 'heic' ),
		'video'   => array( 'mp4', 'webm', 'mov', 'm4v', 'ogv', 'mkv', 'avi' ),
		'audio'   => array( 'mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac', 'opus' ),
		'pdf'     => array( 'pdf' ),
		'archive' => array( 'zip', 'gz', 'tgz', 'tar', 'rar', '7z', 'bz2', 'xz', 'wpress' ),
		'code'    => array( 'php', 'phtml', 'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'css', 'scss', 'sass', 'less', 'html', 'htm', 'json', 'xml', 'yml', 'yaml', 'sql', 'sh', 'py', 'twig', 'vue', 'ini', 'conf', 'env', 'lock', 'map' ),
		'doc'     => array( 'doc', 'docx', 'odt', 'rtf', 'xls', 'xlsx', 'ods', 'csv', 'ppt', 'pptx', 'odp', 'pages', 'numbers', 'key' ),
		'text'    => array( 'txt', 'md', 'markdown', 'log', 'po', 'pot', 'htaccess', 'gitignore', 'distignore', 'editorconfig' ),
		'font'    => array( 'woff', 'woff2', 'ttf', 'otf', 'eot' ),
	);
	foreach ( $map as $kind => $exts ) {
		if ( in_array( $ext, $exts, true ) ) {
			return $kind;
		}
	}
	$base = strtolower( basename( (string) $name ) );
	if ( '' === $ext || '.' === $base[0] ) {
		return 'text';
	}
	return 'file';
}

/** İşlemin kaynağı: panel, ai (Flux AI sohbeti) ya da mcp (dış istemci). */
function flxfm_via() {
	if ( isset( $GLOBALS['flxfm_via'] ) ) {
		return (string) $GLOBALS['flxfm_via'];
	}
	return 'panel';
}

/** REST isteği MCP rotasından mı geliyor. */
function flxfm_is_mcp_request() {
	$route = '';
	if ( isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) && ! empty( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
		$route = (string) $GLOBALS['wp']->query_vars['rest_route'];
	} elseif ( isset( $_GET['rest_route'] ) && is_string( $_GET['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$route = sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	return 0 === strpos( ltrim( $route, '/' ), 'flx-mcp/' );
}

/**
 * Depolama ölçülen klasör: hosting hesabının ana klasörü (/home/<kullanıcı>, Hostinger'da tüm siteler ve
 * e-postalar dahil), okunamıyorsa site kökü. Filtre: flxfm_usage_root.
 */
function flxfm_usage_root() {
	$root = flxfm_root();
	if ( preg_match( '#^(/home/[^/]+)/#', $root . '/', $m ) && @is_readable( $m[1] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$root = $m[1];
	}
	return (string) apply_filters( 'flxfm_usage_root', $root );
}

/** Bayt → okunur boyut. */
function flxfm_size( $bytes ) {
	return size_format( max( 0, (float) $bytes ), $bytes >= 1048576 ? 1 : 0 );
}

/** Eski çöp öğelerini temizler (günlük). */
add_action(
	'flxfm_daily',
	static function () {
		flxfm_trash_purge();
		flxfm_tmp_purge();
		update_option( 'flxfm_daily_at', time(), false );
	}
);

/* WP-Cron çalışmıyorsa yönetim sayfası açılınca da günde bir temizlik yapılır. */
add_action(
	'admin_init',
	static function () {
		$at = (int) get_option( 'flxfm_daily_at', 0 );
		if ( $at < time() - DAY_IN_SECONDS && current_user_can( 'flxfm_manage' ) ) {
			do_action( 'flxfm_daily' );
		}
		if ( ! wp_next_scheduled( 'flxfm_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'flxfm_daily' );
		}
	}
);
