<?php
/**
 * Çöp kutusu, dosya sürümleri, geçici dosyalar ve Etkinlik kaydı.
 * Hepsi veri klasöründe (wp-content/flx-files-<rastgele>/), web erişimi kapalı.
 *
 * @package FLX_Files
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------- Etkinlik */

/**
 * Etkinlik kaydı: kim, ne zaman, nereden (panel / ai / mcp), ne yaptı. Son 1000 kayıt.
 *
 * @param string $action create|edit|mkdir|rename|move|copy|trash|restore|purge|chmod|zip|unzip|upload|revert|settings.
 */
function flxfm_log( $action, $path, $to = '' ) {
	$log = get_option( 'flxfm_log', array() );
	$log = is_array( $log ) ? $log : array();
	array_unshift(
		$log,
		array(
			't'   => time(),
			'u'   => get_current_user_id(),
			'via' => flxfm_via(),
			'a'   => (string) $action,
			'p'   => (string) $path,
			'to'  => (string) $to,
		)
	);
	update_option( 'flxfm_log', array_slice( $log, 0, (int) apply_filters( 'flxfm_log_size', 1000 ) ), false );
	do_action( 'flxfm_changed', $action, $path, $to, flxfm_via() );
}

/** Etkinlik listesi (okunur alanlarla). */
function flxfm_log_list( $args = array() ) {
	$log   = get_option( 'flxfm_log', array() );
	$log   = is_array( $log ) ? $log : array();
	$path  = isset( $args['path'] ) ? (string) $args['path'] : '';
	$via   = isset( $args['via'] ) ? (string) $args['via'] : '';
	$limit = max( 1, min( 500, (int) ( $args['limit'] ?? 100 ) ) );
	$page  = max( 1, (int) ( $args['page'] ?? 1 ) );
	$rows  = array();
	foreach ( $log as $e ) {
		if ( '' !== $path && $e['p'] !== $path && $e['to'] !== $path && 0 !== strpos( $e['p'], $path . '/' ) ) {
			continue;
		}
		if ( '' !== $via && $e['via'] !== $via ) {
			continue;
		}
		$rows[] = $e;
	}
	$total = count( $rows );
	$rows  = array_slice( $rows, ( $page - 1 ) * $limit, $limit );
	$names = array();
	foreach ( $rows as &$e ) {
		if ( ! isset( $names[ $e['u'] ] ) ) {
			$user              = $e['u'] ? get_userdata( $e['u'] ) : null;
			$names[ $e['u'] ] = $user ? $user->display_name : '—';
		}
		$e['user'] = $names[ $e['u'] ];
		$e['ago']  = flx_time_ago( $e['t'] );
		$e['date'] = flx_date( $e['t'], 'datetime' );
	}
	return array( 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => (int) ceil( $total / $limit ) );
}

/* ----------------------------------------------------------------- Sürüm */

/** Dosyanın sürüm klasörü. */
function flxfm_versions_dir( $rel, $create = false ) {
	$base = $create ? flxfm_data_dir( 'versions' ) : flxfm_data_dir() . '/versions';
	$dir  = $base . '/' . md5( $rel );
	if ( $create && ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	return $dir;
}

/** Üzerine yazmadan önce mevcut içeriği saklar (yalnız ≤ 3 MB metin dosyaları). */
function flxfm_version_save( $rel, $abs ) {
	$keep = (int) flxfm_opt( 'versions' );
	if ( $keep < 1 || ! is_file( $abs ) || filesize( $abs ) > FLXFM_FS::MAX_EDIT || FLXFM_FS::is_binary( $abs ) || flxfm_in_data( $rel ) ) {
		return false;
	}
	$dir = flxfm_versions_dir( $rel, true );
	file_put_contents( $dir . '/path.txt', $rel ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$id = gmdate( 'YmdHis' ) . '-' . get_current_user_id() . '-' . flxfm_via() . '-' . strtolower( wp_generate_password( 4, false, false ) );
	if ( ! @copy( $abs, $dir . '/' . $id . '.bak' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return false;
	}
	$all = glob( $dir . '/*.bak' );
	if ( $all && count( $all ) > $keep ) {
		sort( $all );
		foreach ( array_slice( $all, 0, count( $all ) - $keep ) as $old ) {
			@unlink( $old ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}
	return $id;
}

/** Bir dosyanın sürümleri (yeniden eskiye). */
function flxfm_versions( $rel ) {
	$dir = flxfm_versions_dir( $rel );
	$out = array();
	foreach ( (array) glob( $dir . '/*.bak' ) as $f ) {
		$id = basename( $f, '.bak' );
		if ( ! preg_match( '/^(\d{14})-(\d+)-([a-z]+)-[a-z0-9]+$/', $id, $m ) ) {
			continue;
		}
		$t     = strtotime( $m[1] . ' UTC' );
		$user  = get_userdata( (int) $m[2] );
		$out[] = array(
			'id'   => $id,
			't'    => $t,
			'ago'  => flx_time_ago( $t ),
			'date' => flx_date( $t, 'datetime' ),
			'user' => $user ? $user->display_name : '—',
			'via'  => $m[3],
			'size' => (int) filesize( $f ),
		);
	}
	usort(
		$out,
		static function ( $a, $b ) {
			return strcmp( $b['id'], $a['id'] );
		}
	);
	return $out;
}

/** Sürüm içeriği. */
function flxfm_version_read( $rel, $id ) {
	if ( ! preg_match( '/^\d{14}-\d+-[a-z]+-[a-z0-9]+$/', (string) $id ) ) {
		return FLXFM_FS::err( 'version', __( 'Version not found.', 'flx-files' ), 404 );
	}
	$f = flxfm_versions_dir( $rel ) . '/' . $id . '.bak';
	if ( ! is_file( $f ) ) {
		return FLXFM_FS::err( 'version', __( 'Version not found.', 'flx-files' ), 404 );
	}
	return (string) file_get_contents( $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions
}

/* ------------------------------------------------------------ Çöp kutusu */

/** Çöpteki öğeler (yeniden eskiye). */
function flxfm_trash_list() {
	$dir = flxfm_data_dir() . '/trash';
	$out = array();
	foreach ( (array) glob( $dir . '/*/meta.json' ) as $m ) {
		$meta = json_decode( (string) file_get_contents( $m ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! is_array( $meta ) || empty( $meta['id'] ) ) {
			continue;
		}
		$user          = ! empty( $meta['u'] ) ? get_userdata( (int) $meta['u'] ) : null;
		$meta['user']  = $user ? $user->display_name : '—';
		$meta['ago']   = flx_time_ago( (int) $meta['t'] );
		$meta['date']  = flx_date( (int) $meta['t'], 'datetime' );
		$meta['kind']  = flxfm_kind( $meta['name'], 'dir' === $meta['type'] );
		$days          = (int) flxfm_opt( 'trash_days' );
		$meta['until'] = $days ? flx_date( (int) $meta['t'] + $days * DAY_IN_SECONDS, 'date' ) : '';
		$out[]         = $meta;
	}
	usort(
		$out,
		static function ( $a, $b ) {
			return $b['t'] <=> $a['t'];
		}
	);
	return $out;
}

/** Çöp öğesinin klasörü (kimlik doğrulanır). */
function flxfm_trash_dir( $id ) {
	if ( ! preg_match( '/^\d{14}-[a-z0-9]{6}$/', (string) $id ) ) {
		return false;
	}
	$dir = flxfm_data_dir() . '/trash/' . $id;
	return is_dir( $dir ) ? $dir : false;
}

/**
 * Geri yükler. Asıl yer doluysa ada sayı eklenir.
 *
 * @return array|WP_Error Geri gelen öğe.
 */
function flxfm_trash_restore( $id ) {
	$dir = flxfm_trash_dir( $id );
	if ( ! $dir ) {
		return FLXFM_FS::err( 'trash', __( 'This item is no longer in the trash.', 'flx-files' ), 404 );
	}
	$meta = json_decode( (string) file_get_contents( $dir . '/meta.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! is_array( $meta ) || empty( $meta['path'] ) ) {
		return FLXFM_FS::err( 'trash', __( 'This item is no longer in the trash.', 'flx-files' ), 404 );
	}
	$rel = FLXFM_FS::unique( (string) $meta['path'] );
	$r   = FLXFM_FS::resolve( $rel, false );
	if ( is_wp_error( $r ) ) {
		// Üst klasör de silinmişse yeniden oluşturulur.
		$clean = flxfm_clean( $rel );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		wp_mkdir_p( flxfm_root() . '/' . dirname( $clean ) );
		$r = FLXFM_FS::resolve( $clean, false );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	if ( ! @rename( $dir . '/item', $r['abs'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		return FLXFM_FS::err( 'write', __( 'Could not restore (permissions).', 'flx-files' ), 500 );
	}
	@unlink( $dir . '/meta.json' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	flxfm_log( 'restore', $r['rel'] );
	return FLXFM_FS::item( $r['abs'], $r['rel'] );
}

/** Klasörü tamamen siler (yalnız veri klasörü içinde çağrılır). */
function flxfm_rmtree( $abs ) {
	$abs  = wp_normalize_path( $abs );
	$data = flxfm_data_dir();
	if ( 0 !== strpos( $abs . '/', $data . '/' ) || $abs === $data ) {
		return false;
	}
	if ( is_link( $abs ) || is_file( $abs ) ) {
		return @unlink( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	if ( ! is_dir( $abs ) ) {
		return true;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $abs, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $f ) {
		if ( $f->isDir() && ! $f->isLink() ) {
			@rmdir( $f->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		} else {
			@unlink( $f->getPathname() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}
	return @rmdir( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
}

/** Çöp öğesini kalıcı siler. */
function flxfm_trash_delete( $id ) {
	$dir = flxfm_trash_dir( $id );
	if ( ! $dir ) {
		return FLXFM_FS::err( 'trash', __( 'This item is no longer in the trash.', 'flx-files' ), 404 );
	}
	$meta = json_decode( (string) @file_get_contents( $dir . '/meta.json' ), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	flxfm_rmtree( $dir );
	flxfm_log( 'purge', is_array( $meta ) ? (string) ( $meta['path'] ?? '' ) : '' );
	return true;
}

/** Süresi dolan çöp öğelerini siler. */
function flxfm_trash_purge( $all = false ) {
	$days = (int) flxfm_opt( 'trash_days' );
	$n    = 0;
	foreach ( flxfm_trash_list() as $it ) {
		if ( $all || ( $days && (int) $it['t'] < time() - $days * DAY_IN_SECONDS ) ) {
			$dir = flxfm_trash_dir( $it['id'] );
			if ( $dir && flxfm_rmtree( $dir ) ) {
				++$n;
			}
		}
	}
	if ( $all && $n ) {
		/* translators: %d: number of items */
		flxfm_log( 'purge', sprintf( _n( '%d item', '%d items', $n, 'flx-files' ), $n ) );
	}
	return $n;
}

/** Bir günden eski geçici dosyalar (yarım yüklemeler, ZIP indirmeleri). */
function flxfm_tmp_purge() {
	foreach ( (array) glob( flxfm_data_dir() . '/tmp/*' ) as $f ) {
		if ( is_file( $f ) && filemtime( $f ) < time() - DAY_IN_SECONDS && 'index.html' !== basename( $f ) && '.htaccess' !== basename( $f ) ) {
			@unlink( $f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}
}

/* ---------------------------------------------------- Paylaşılan klasörler */

/** Paylaşılan klasör taşınınca/silinince listeyi günceller ('' = sil). */
function flxfm_shared_rename( $from, $to ) {
	$s       = flxfm_settings();
	$changed = false;
	$out     = array();
	foreach ( (array) $s['shared'] as $rel ) {
		if ( $rel === $from || 0 === strpos( $rel, $from . '/' ) ) {
			$changed = true;
			if ( '' === $to ) {
				continue;
			}
			$rel = $to . substr( $rel, strlen( $from ) );
		}
		$out[] = $rel;
	}
	if ( $changed ) {
		$s['shared'] = $out;
		update_option( 'flxfm_settings', $s, false );
	}
}
