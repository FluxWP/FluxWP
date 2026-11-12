<?php
/**
 * Dosya sistemi işlemleri. Her yol köke göre göreli gelir, kök dışına (.. ya da sembolik bağla) çıkamaz.
 * Silme çöpe taşır, üzerine yazma önceki sürümü saklar, her değişiklik Etkinlik'e yazılır.
 * Panel, REST, Flux AI ve MCP hep bu sınıfı kullanır.
 *
 * @package FLX_Files
 */

defined( 'ABSPATH' ) || exit;

class FLXFM_FS {

	/** Editörde açılacak en büyük metin dosyası. */
	const MAX_EDIT = 3145728;

	/** Klasör kopyalama / arşivleme için en fazla dosya. */
	const MAX_FILES = 20000;

	/** Hata. */
	public static function err( $code, $msg, $status = 400 ) {
		return new WP_Error( 'flxfm_' . $code, $msg, array( 'status' => $status ) );
	}

	/**
	 * Göreli yolu mutlak yola çevirir ve kök içinde olduğunu doğrular.
	 *
	 * @param string $rel   Göreli yol.
	 * @param bool   $exist true: var olmalı; false: olmayabilir (üst klasör var olmalı).
	 * @return array|WP_Error array( rel, abs )
	 */
	public static function resolve( $rel, $exist = true ) {
		$rel = flxfm_clean( $rel );
		if ( is_wp_error( $rel ) ) {
			return $rel;
		}
		$root = flxfm_root();
		$abs  = '' === $rel ? $root : $root . '/' . $rel;
		if ( file_exists( $abs ) || is_link( $abs ) ) {
			// Sembolik bağ kök dışını gösteriyorsa reddedilir.
			$real = realpath( $abs );
			if ( false === $real || false === flxfm_rel_of( $real ) ) {
				return self::err( 'outside', __( 'This path points outside the site folder.', 'flx-files' ), 403 );
			}
			return array( 'rel' => $rel, 'abs' => $abs );
		}
		if ( $exist ) {
			return self::err( 'missing', __( 'File or folder not found.', 'flx-files' ), 404 );
		}
		if ( '' === $rel ) {
			return self::err( 'path', __( 'Invalid path.', 'flx-files' ) );
		}
		$name = basename( $rel );
		if ( ! flxfm_valid_name( $name ) ) {
			return self::err( 'name', __( 'Invalid name.', 'flx-files' ) );
		}
		$parent = realpath( dirname( $abs ) );
		if ( false === $parent || ! is_dir( $parent ) ) {
			return self::err( 'missing', __( 'The parent folder does not exist.', 'flx-files' ), 404 );
		}
		if ( false === flxfm_rel_of( $parent ) ) {
			return self::err( 'outside', __( 'This path points outside the site folder.', 'flx-files' ), 403 );
		}
		return array( 'rel' => $rel, 'abs' => $abs );
	}

	/** Bir öğenin bilgisi. */
	public static function item( $abs, $rel ) {
		$is_link = is_link( $abs );
		$is_dir  = is_dir( $abs );
		$name    = '' === $rel ? basename( flxfm_root() ) : basename( $rel );
		$st      = @stat( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$item    = array(
			'name'      => $name,
			'path'      => $rel,
			'type'      => $is_dir ? 'dir' : 'file',
			'kind'      => flxfm_kind( $name, $is_dir ),
			'ext'       => $is_dir ? '' : strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ),
			'size'      => ( $is_dir || ! $st ) ? null : (int) $st['size'],
			'mtime'     => $st ? (int) $st['mtime'] : 0,
			'ago'       => $st ? flx_time_ago( (int) $st['mtime'] ) : '',
			'date'      => $st ? flx_date( (int) $st['mtime'], 'datetime' ) : '',
			'perms'     => $st ? substr( sprintf( '%o', $st['mode'] ), -4 ) : '',
			'writable'  => wp_is_writable( $abs ),
			'link'      => $is_link,
			'locked'    => flxfm_is_locked( $rel ),
			'sensitive' => ! $is_dir && flxfm_is_sensitive( $rel ),
			'core'      => flxfm_is_core( $rel ),
		);
		if ( $is_dir ) {
			$item['shared'] = in_array( $rel, (array) flxfm_opt( 'shared' ), true );
		}
		return $item;
	}

	/**
	 * Klasör içeriği.
	 *
	 * @return array|WP_Error array( path, items[], parent )
	 */
	public static function list_dir( $rel, $args = array() ) {
		$r = self::resolve( $rel );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ! is_dir( $r['abs'] ) ) {
			return self::err( 'notdir', __( 'This is not a folder.', 'flx-files' ) );
		}
		$names = @scandir( $r['abs'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false === $names ) {
			return self::err( 'read', __( 'The folder could not be read (permissions).', 'flx-files' ), 403 );
		}
		$hidden = ! empty( $args['hidden'] );
		$items  = array();
		foreach ( $names as $n ) {
			if ( '.' === $n || '..' === $n ) {
				continue;
			}
			if ( ! $hidden && '.' === $n[0] ) {
				continue;
			}
			$crel = '' === $r['rel'] ? $n : $r['rel'] . '/' . $n;
			$abs  = $r['abs'] . '/' . $n;
			$it   = self::item( $abs, $crel );
			if ( 'dir' === $it['type'] && empty( $args['no_count'] ) ) {
				$sub         = @scandir( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$it['count'] = false === $sub ? null : count( $sub ) - 2;
			}
			$items[] = $it;
		}
		usort(
			$items,
			static function ( $a, $b ) {
				if ( $a['type'] !== $b['type'] ) {
					return 'dir' === $a['type'] ? -1 : 1;
				}
				return strnatcasecmp( $a['name'], $b['name'] );
			}
		);
		return array(
			'path'   => $r['rel'],
			'dir'    => self::item( $r['abs'], $r['rel'] ),
			'items'  => $items,
			'parent' => '' === $r['rel'] ? null : ( '.' === dirname( $r['rel'] ) ? '' : dirname( $r['rel'] ) ),
		);
	}

	/** Dosya ikili mi (ilk 8 KB'de NUL bayt). */
	public static function is_binary( $abs ) {
		$h = @fopen( $abs, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		if ( ! $h ) {
			return false;
		}
		$chunk = (string) fread( $h, 8192 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fclose( $h ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return false !== strpos( $chunk, "\0" );
	}

	/**
	 * Metin dosyasını okur.
	 *
	 * @return array|WP_Error array( path, content, size, mtime, hash, … )
	 */
	public static function read( $rel, $max = self::MAX_EDIT ) {
		$r = self::resolve( $rel );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ! is_file( $r['abs'] ) ) {
			return self::err( 'notfile', __( 'This is not a file.', 'flx-files' ) );
		}
		if ( ! is_readable( $r['abs'] ) ) {
			return self::err( 'read', __( 'The file could not be read (permissions).', 'flx-files' ), 403 );
		}
		$size = (int) filesize( $r['abs'] );
		if ( $size > $max ) {
			/* translators: %s: size limit, e.g. 3 MB */
			return self::err( 'big', sprintf( __( 'The file is larger than %s and cannot be opened in the editor. Download it instead.', 'flx-files' ), flxfm_size( $max ) ), 413 );
		}
		if ( self::is_binary( $r['abs'] ) ) {
			return self::err( 'binary', __( 'This is a binary file and cannot be opened in the editor.', 'flx-files' ), 415 );
		}
		$content = (string) file_get_contents( $r['abs'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return array_merge(
			self::item( $r['abs'], $r['rel'] ),
			array(
				'content' => $content,
				'hash'    => md5( $content ),
			)
		);
	}

	/** Yazma öncesi ortak kontroller. */
	protected static function can_write( $rel, $opts ) {
		if ( ! flxfm_writable_mode() ) {
			return self::err( 'readonly', __( 'File changes are disabled on this site (DISALLOW_FILE_MODS).', 'flx-files' ), 403 );
		}
		if ( flxfm_in_data( $rel ) ) {
			return self::err( 'locked', __( 'This folder is managed by Flux Files.', 'flx-files' ), 403 );
		}
		if ( flxfm_is_executable( $rel ) && ! flxfm_code_edit_allowed() ) {
			return self::err( 'code', __( 'Editing code files is disabled on this site (DISALLOW_FILE_EDIT).', 'flx-files' ), 403 );
		}
		$via = flxfm_via();
		if ( 'panel' !== $via ) {
			if ( flxfm_is_sensitive( $rel ) || flxfm_is_core( $rel ) ) {
				return self::err( 'ai_protected', __( 'AI cannot change protected or WordPress core files.', 'flx-files' ), 403 );
			}
			if ( flxfm_is_code( $rel ) && ! flxfm_opt( 'ai_code' ) ) {
				return self::err( 'ai_code', __( 'AI is not allowed to write code files. Enable it in Flux Files › Settings.', 'flx-files' ), 403 );
			}
		}
		return true;
	}

	/**
	 * Dosya yazar (yoksa oluşturur). Var olan dosya önce sürümlere kopyalanır.
	 *
	 * @param array $opts expect_hash (çakışma kontrolü), create_only.
	 * @return array|WP_Error Yeni öğe bilgisi.
	 */
	public static function write( $rel, $content, $opts = array() ) {
		$r = self::resolve( $rel, false );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$ok = self::can_write( $r['rel'], $opts );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$exists = file_exists( $r['abs'] );
		if ( $exists && is_dir( $r['abs'] ) ) {
			return self::err( 'isdir', __( 'A folder with this name already exists.', 'flx-files' ), 409 );
		}
		if ( $exists && ! empty( $opts['create_only'] ) ) {
			return self::err( 'exists', __( 'A file with this name already exists.', 'flx-files' ), 409 );
		}
		if ( $exists && ! empty( $opts['expect_hash'] ) ) {
			$cur = md5( (string) file_get_contents( $r['abs'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( $cur !== $opts['expect_hash'] ) {
				return self::err( 'conflict', __( 'The file changed on the server since you opened it. Reload it or save a copy.', 'flx-files' ), 409 );
			}
		}
		if ( $exists && ! wp_is_writable( $r['abs'] ) ) {
			return self::err( 'perm', __( 'The file is not writable (permissions).', 'flx-files' ), 403 );
		}
		if ( $exists ) {
			flxfm_version_save( $r['rel'], $r['abs'] );
		}
		$tmp = $r['abs'] . '.flxfm-' . wp_generate_password( 6, false, false ) . '.tmp';
		$n   = @file_put_contents( $tmp, (string) $content, LOCK_EX ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		if ( false === $n ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return self::err( 'write', __( 'The file could not be written (permissions or disk space).', 'flx-files' ), 500 );
		}
		$mode = $exists ? ( fileperms( $r['abs'] ) & 0777 ) : ( defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644 );
		if ( ! @rename( $tmp, $r['abs'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return self::err( 'write', __( 'The file could not be written (permissions or disk space).', 'flx-files' ), 500 );
		}
		@chmod( $r['abs'], $mode ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		clearstatcache( true, $r['abs'] );
		if ( function_exists( 'opcache_invalidate' ) && preg_match( '/\.php\d?$/i', $r['abs'] ) ) {
			@opcache_invalidate( $r['abs'], true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		flxfm_log( $exists ? 'edit' : 'create', $r['rel'] );
		return array_merge( self::item( $r['abs'], $r['rel'] ), array( 'hash' => md5( (string) $content ) ) );
	}

	/** Klasör oluşturur. */
	public static function mkdir( $rel ) {
		$r = self::resolve( $rel, false );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$ok = self::can_write( $r['rel'] . '/x', array() );
		if ( is_wp_error( $ok ) && 'flxfm_ai_code' !== $ok->get_error_code() ) {
			return $ok;
		}
		if ( file_exists( $r['abs'] ) ) {
			return self::err( 'exists', __( 'A file or folder with this name already exists.', 'flx-files' ), 409 );
		}
		if ( ! @mkdir( $r['abs'], defined( 'FS_CHMOD_DIR' ) ? FS_CHMOD_DIR : 0755 ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			return self::err( 'write', __( 'The folder could not be created (permissions).', 'flx-files' ), 500 );
		}
		flxfm_log( 'mkdir', $r['rel'] );
		return self::item( $r['abs'], $r['rel'] );
	}

	/** Taşıma/yeniden adlandırma öncesi kontroller. */
	protected static function check_move_source( $r ) {
		if ( flxfm_is_locked( $r['rel'] ) ) {
			return self::err( 'locked', __( 'This file or folder is protected and cannot be moved, renamed or deleted.', 'flx-files' ), 403 );
		}
		if ( 'panel' !== flxfm_via() && ( flxfm_is_core( $r['rel'] ) || flxfm_is_sensitive( $r['rel'] ) ) ) {
			return self::err( 'ai_protected', __( 'AI cannot change protected or WordPress core files.', 'flx-files' ), 403 );
		}
		if ( ! flxfm_writable_mode() ) {
			return self::err( 'readonly', __( 'File changes are disabled on this site (DISALLOW_FILE_MODS).', 'flx-files' ), 403 );
		}
		return true;
	}

	/** Hedef kaynağın kendi içinde mi. */
	protected static function inside( $child, $parent ) {
		return $child === $parent || 0 === strpos( $child . '/', $parent . '/' );
	}

	/**
	 * Taşır ya da yeniden adlandırır.
	 *
	 * @return array|WP_Error Yeni öğe.
	 */
	public static function move( $from, $to, $overwrite = false ) {
		$a = self::resolve( $from );
		if ( is_wp_error( $a ) ) {
			return $a;
		}
		$ok = self::check_move_source( $a );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$b = self::resolve( $to, false );
		if ( is_wp_error( $b ) ) {
			return $b;
		}
		if ( $a['rel'] === $b['rel'] ) {
			return self::item( $a['abs'], $a['rel'] );
		}
		if ( is_dir( $a['abs'] ) && self::inside( $b['rel'], $a['rel'] ) ) {
			return self::err( 'self', __( 'A folder cannot be moved into itself.', 'flx-files' ) );
		}
		$ok = self::can_write( is_dir( $a['abs'] ) ? $b['rel'] . '/x' : $b['rel'], array() );
		if ( is_wp_error( $ok ) && ! ( is_dir( $a['abs'] ) && 'flxfm_ai_code' === $ok->get_error_code() ) ) {
			return $ok;
		}
		if ( 'panel' !== flxfm_via() && ! is_dir( $a['abs'] ) ) {
			$ok = self::can_write( $a['rel'], array() );
			if ( is_wp_error( $ok ) ) {
				return $ok;
			}
		}
		$case_only = strtolower( $a['rel'] ) === strtolower( $b['rel'] );
		if ( file_exists( $b['abs'] ) && ! $case_only ) {
			if ( ! $overwrite || is_dir( $b['abs'] ) || is_dir( $a['abs'] ) ) {
				return self::err( 'exists', __( 'A file or folder with this name already exists.', 'flx-files' ), 409 );
			}
			$t = self::trash( $b['rel'], true );
			if ( is_wp_error( $t ) ) {
				return $t;
			}
		}
		if ( ! @rename( $a['abs'], $b['abs'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			return self::err( 'write', __( 'Could not move (permissions).', 'flx-files' ), 500 );
		}
		flxfm_shared_rename( $a['rel'], $b['rel'] );
		flxfm_log( dirname( $a['rel'] ) === dirname( $b['rel'] ) ? 'rename' : 'move', $a['rel'], $b['rel'] );
		return self::item( $b['abs'], $b['rel'] );
	}

	/** Klasördeki dosyaları sayar (sınır aşılırsa false). */
	protected static function count_tree( $abs, $limit ) {
		if ( ! is_dir( $abs ) ) {
			return 1;
		}
		$n  = 0;
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $abs, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
		foreach ( $it as $f ) {
			if ( ++$n > $limit ) {
				return false;
			}
		}
		return $n;
	}

	/** Recursive kopya. */
	protected static function copy_tree( $src, $dst ) {
		if ( is_link( $src ) ) {
			$real = realpath( $src );
			if ( false === $real || false === flxfm_rel_of( $real ) ) {
				return true; // Kök dışını gösteren bağ atlanır.
			}
		}
		if ( ! is_dir( $src ) ) {
			return @copy( $src, $dst ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		if ( ! is_dir( $dst ) && ! @mkdir( $dst, defined( 'FS_CHMOD_DIR' ) ? FS_CHMOD_DIR : 0755 ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			return false;
		}
		foreach ( (array) @scandir( $src ) as $n ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( '.' === $n || '..' === $n || '' === $n ) {
				continue;
			}
			if ( ! self::copy_tree( $src . '/' . $n, $dst . '/' . $n ) ) {
				return false;
			}
		}
		return true;
	}

	/** Var olan ada " (kopya)" / " 2" ekleyerek boş ad bulur. */
	public static function unique( $rel ) {
		$root = flxfm_root();
		if ( ! file_exists( $root . '/' . $rel ) ) {
			return $rel;
		}
		$dir  = dirname( $rel );
		$dir  = '.' === $dir ? '' : $dir . '/';
		$base = basename( $rel );
		$ext  = '';
		if ( preg_match( '/^(.+?)(\.[A-Za-z0-9]{1,8}(?:\.gz)?)$/', $base, $m ) && ! is_dir( $root . '/' . $rel ) ) {
			$base = $m[1];
			$ext  = $m[2];
		}
		for ( $i = 2; $i < 1000; $i++ ) {
			$try = $dir . $base . ' (' . $i . ')' . $ext;
			if ( ! file_exists( $root . '/' . $try ) ) {
				return $try;
			}
		}
		return $dir . $base . ' (' . time() . ')' . $ext;
	}

	/**
	 * Kopyalar. Hedef varsa ve $overwrite yoksa ada sayı eklenir.
	 *
	 * @return array|WP_Error Yeni öğe.
	 */
	public static function copy( $from, $to, $overwrite = false ) {
		$a = self::resolve( $from );
		if ( is_wp_error( $a ) ) {
			return $a;
		}
		if ( flxfm_in_data( $a['rel'] ) ) {
			return self::err( 'locked', __( 'This folder is managed by Flux Files.', 'flx-files' ), 403 );
		}
		if ( 'panel' !== flxfm_via() && flxfm_is_sensitive( $a['rel'] ) ) {
			return self::err( 'ai_protected', __( 'AI cannot read or copy protected files.', 'flx-files' ), 403 );
		}
		$to = flxfm_clean( $to );
		if ( is_wp_error( $to ) ) {
			return $to;
		}
		if ( ! $overwrite ) {
			$to = self::unique( $to );
		}
		$b = self::resolve( $to, false );
		if ( is_wp_error( $b ) ) {
			return $b;
		}
		$is_dir = is_dir( $a['abs'] );
		if ( $is_dir && self::inside( $b['rel'], $a['rel'] ) ) {
			return self::err( 'self', __( 'A folder cannot be copied into itself.', 'flx-files' ) );
		}
		$ok = self::can_write( $is_dir ? $b['rel'] . '/x' : $b['rel'], array() );
		if ( is_wp_error( $ok ) && ! ( $is_dir && 'flxfm_ai_code' === $ok->get_error_code() ) ) {
			return $ok;
		}
		if ( $is_dir && false === self::count_tree( $a['abs'], self::MAX_FILES ) ) {
			/* translators: %s: number of files */
			return self::err( 'big', sprintf( __( 'The folder has more than %s files; copy it with SSH or a backup tool.', 'flx-files' ), number_format_i18n( self::MAX_FILES ) ), 413 );
		}
		if ( file_exists( $b['abs'] ) ) {
			if ( is_dir( $b['abs'] ) || $is_dir ) {
				return self::err( 'exists', __( 'A file or folder with this name already exists.', 'flx-files' ), 409 );
			}
			flxfm_version_save( $b['rel'], $b['abs'] );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		if ( ! self::copy_tree( $a['abs'], $b['abs'] ) ) {
			return self::err( 'write', __( 'Could not copy (permissions or disk space).', 'flx-files' ), 500 );
		}
		flxfm_log( 'copy', $a['rel'], $b['rel'] );
		return self::item( $b['abs'], $b['rel'] );
	}

	/**
	 * Çöpe taşır (geri alınabilir).
	 *
	 * @param bool $quiet Etkinliğe yazma (üzerine yazma sırasında).
	 * @return array|WP_Error array( trash_id )
	 */
	public static function trash( $rel, $quiet = false ) {
		$r = self::resolve( $rel );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$ok = self::check_move_source( $r );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		if ( 'panel' !== flxfm_via() && ! is_dir( $r['abs'] ) ) {
			$ok = self::can_write( $r['rel'], array() );
			if ( is_wp_error( $ok ) ) {
				return $ok;
			}
		}
		if ( 'panel' !== flxfm_via() && is_dir( $r['abs'] ) && false === self::count_tree( $r['abs'], 2000 ) ) {
			return self::err( 'big', __( 'AI can only delete folders with fewer than 2,000 files.', 'flx-files' ), 403 );
		}
		$id  = gmdate( 'YmdHis' ) . '-' . strtolower( wp_generate_password( 6, false, false ) );
		$dir = flxfm_data_dir( 'trash' ) . '/' . $id;
		if ( ! @mkdir( $dir, 0755 ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			return self::err( 'write', __( 'Could not move to the trash (permissions).', 'flx-files' ), 500 );
		}
		$is_dir = is_dir( $r['abs'] );
		$size   = $is_dir ? null : (int) filesize( $r['abs'] );
		if ( ! @rename( $r['abs'], $dir . '/item' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			return self::err( 'write', __( 'Could not move to the trash (permissions).', 'flx-files' ), 500 );
		}
		$meta = array(
			'id'   => $id,
			'path' => $r['rel'],
			'name' => basename( $r['rel'] ),
			'type' => $is_dir ? 'dir' : 'file',
			'size' => $size,
			't'    => time(),
			'u'    => get_current_user_id(),
			'via'  => flxfm_via(),
		);
		file_put_contents( $dir . '/meta.json', wp_json_encode( $meta ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		flxfm_shared_rename( $r['rel'], '' );
		if ( ! $quiet ) {
			flxfm_log( 'trash', $r['rel'] );
		}
		return array( 'trash_id' => $id, 'path' => $r['rel'] );
	}

	/** İzinleri değiştirir (sekizlik, ör. 0644). */
	public static function chmod( $rel, $mode ) {
		$r = self::resolve( $rel );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ! flxfm_writable_mode() || 'panel' !== flxfm_via() ) {
			return self::err( 'readonly', __( 'Permissions can only be changed from the Flux Files screen.', 'flx-files' ), 403 );
		}
		if ( ! preg_match( '/^0?[0-7]{3}$/', (string) $mode ) ) {
			return self::err( 'mode', __( 'Enter permissions as three digits, e.g. 644 or 755.', 'flx-files' ) );
		}
		$m = octdec( (string) $mode );
		if ( ( $m & 0002 ) ) {
			return self::err( 'mode', __( 'World-writable permissions (e.g. 777) are not allowed.', 'flx-files' ) );
		}
		if ( ! @chmod( $r['abs'], $m ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			return self::err( 'write', __( 'Permissions could not be changed.', 'flx-files' ), 500 );
		}
		clearstatcache( true, $r['abs'] );
		flxfm_log( 'chmod', $r['rel'], sprintf( '%04o', $m ) );
		return self::item( $r['abs'], $r['rel'] );
	}

	/** Klasörün toplam boyutu ve dosya sayısı (en fazla 8 sn). */
	public static function dir_size( $rel ) {
		$r = self::resolve( $rel );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ! is_dir( $r['abs'] ) ) {
			return array( 'size' => (int) filesize( $r['abs'] ), 'files' => 1, 'dirs' => 0, 'partial' => false );
		}
		$size  = 0;
		$files = 0;
		$dirs  = 0;
		$t0    = microtime( true );
		$part  = false;
		try {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $r['abs'], FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD );
			foreach ( $it as $f ) {
				if ( $f->isDir() ) {
					++$dirs;
				} else {
					++$files;
					$size += (int) $f->getSize();
				}
				if ( 0 === ( $files % 500 ) && microtime( true ) - $t0 > 8 ) {
					$part = true;
					break;
				}
			}
		} catch ( Exception $e ) {
			$part = true;
		}
		return array( 'size' => $size, 'files' => $files, 'dirs' => $dirs, 'partial' => $part );
	}

	/**
	 * Ad (ve isteğe bağlı içerik) araması.
	 *
	 * @param array $args content (bool), limit, hidden.
	 * @return array|WP_Error array( results[], partial )
	 */
	public static function search( $rel, $q, $args = array() ) {
		$r = self::resolve( $rel );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$q = trim( (string) $q );
		if ( mb_strlen( $q ) < 2 ) {
			return self::err( 'query', __( 'Type at least 2 characters.', 'flx-files' ) );
		}
		$content = ! empty( $args['content'] );
		$limit   = max( 1, min( 500, (int) ( $args['limit'] ?? 200 ) ) );
		$hidden  = ! empty( $args['hidden'] );
		$ai      = 'panel' !== flxfm_via();
		$ql      = mb_strtolower( $q );
		$out     = array();
		$seen    = 0;
		$t0      = microtime( true );
		$part    = false;
		$data    = flxfm_data_dir();
		try {
			$dir_it = new RecursiveDirectoryIterator( $r['abs'], FilesystemIterator::SKIP_DOTS );
			$filter = new RecursiveCallbackFilterIterator(
				$dir_it,
				static function ( $f ) use ( $hidden, $data ) {
					$n = $f->getFilename();
					if ( ! $hidden && '.' === $n[0] ) {
						return false;
					}
					if ( $f->isDir() ) {
						$p = wp_normalize_path( $f->getPathname() );
						return $p !== $data && ! in_array( $n, array( 'node_modules', '.git', 'cache' ), true );
					}
					return true;
				}
			);
			$it = new RecursiveIteratorIterator( $filter, RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD );
			foreach ( $it as $f ) {
				if ( ++$seen > 60000 || microtime( true ) - $t0 > 10 ) {
					$part = true;
					break;
				}
				$abs  = wp_normalize_path( $f->getPathname() );
				$crel = flxfm_rel_of( $abs );
				if ( false === $crel ) {
					continue;
				}
				$name  = $f->getFilename();
				$match = false !== mb_strpos( mb_strtolower( $name ), $ql );
				$lines = array();
				if ( $content && $f->isFile() && $f->getSize() < 2097152 && ! ( $ai && flxfm_is_sensitive( $crel ) ) ) {
					$k = flxfm_kind( $name );
					if ( in_array( $k, array( 'code', 'text' ), true ) ) {
						$txt = (string) @file_get_contents( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
						if ( '' !== $txt && false !== stripos( $txt, $q ) ) {
							foreach ( preg_split( '/\R/', $txt ) as $i => $line ) {
								if ( false !== stripos( $line, $q ) ) {
									$lines[] = array( 'line' => $i + 1, 'text' => mb_substr( trim( $line ), 0, 200 ) );
									if ( count( $lines ) >= 3 ) {
										break;
									}
								}
							}
						}
					}
				}
				if ( $match || $lines ) {
					$it2 = self::item( $abs, $crel );
					if ( $lines ) {
						$it2['matches'] = $lines;
					}
					$out[] = $it2;
					if ( count( $out ) >= $limit ) {
						$part = true;
						break;
					}
				}
			}
		} catch ( Exception $e ) {
			$part = true;
		}
		return array( 'results' => $out, 'partial' => $part, 'query' => $q );
	}

	/**
	 * Seçilenleri ZIP'e koyar.
	 *
	 * @param string[] $rels   Öğeler.
	 * @param string   $target Yazılacak zip (göreli). Boşsa veri klasöründe geçici dosya.
	 * @return array|WP_Error array( abs, rel?, name )
	 */
	public static function zip( array $rels, $target = '' ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return self::err( 'nozip', __( 'The PHP zip extension is not available on this server.', 'flx-files' ), 501 );
		}
		$items = array();
		foreach ( $rels as $rel ) {
			$r = self::resolve( $rel );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			if ( flxfm_in_data( $r['rel'] ) ) {
				return self::err( 'locked', __( 'This folder is managed by Flux Files.', 'flx-files' ), 403 );
			}
			if ( 'panel' !== flxfm_via() && flxfm_is_sensitive( $r['rel'] ) ) {
				return self::err( 'ai_protected', __( 'AI cannot read or copy protected files.', 'flx-files' ), 403 );
			}
			$items[] = $r;
		}
		if ( ! $items ) {
			return self::err( 'empty', __( 'Nothing selected.', 'flx-files' ) );
		}
		if ( '' !== $target ) {
			$t = self::resolve( self::unique( flxfm_clean( $target ) ), false );
			if ( is_wp_error( $t ) ) {
				return $t;
			}
			$ok = self::can_write( $t['rel'], array() );
			if ( is_wp_error( $ok ) ) {
				return $ok;
			}
			$abs = $t['abs'];
		} else {
			$abs = flxfm_data_dir( 'tmp' ) . '/' . wp_generate_password( 16, false, false ) . '.zip';
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $abs, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return self::err( 'write', __( 'The archive could not be created.', 'flx-files' ), 500 );
		}
		$n    = 0;
		$data = flxfm_data_dir();
		foreach ( $items as $r ) {
			$base = basename( '' === $r['rel'] ? flxfm_root() : $r['rel'] );
			if ( is_dir( $r['abs'] ) ) {
				$zip->addEmptyDir( $base );
				$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $r['abs'], FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD );
				foreach ( $it as $f ) {
					$p = wp_normalize_path( $f->getPathname() );
					if ( 0 === strpos( $p . '/', $data . '/' ) || $p === $abs ) {
						continue;
					}
					$local = $base . '/' . substr( $p, strlen( $r['abs'] ) + 1 );
					if ( $f->isDir() ) {
						$zip->addEmptyDir( $local );
					} elseif ( $f->isReadable() ) {
						$zip->addFile( $p, $local );
					}
					if ( ++$n > self::MAX_FILES * 5 ) {
						$zip->close();
						@unlink( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
						return self::err( 'big', __( 'Too many files for one archive.', 'flx-files' ), 413 );
					}
				}
			} else {
				$zip->addFile( $r['abs'], $base );
			}
		}
		if ( ! $zip->close() ) {
			@unlink( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return self::err( 'write', __( 'The archive could not be created.', 'flx-files' ), 500 );
		}
		$name = 1 === count( $items ) ? basename( '' === $items[0]['rel'] ? flxfm_root() : $items[0]['rel'] ) . '.zip' : 'files.zip';
		if ( '' !== $target ) {
			$rel = flxfm_rel_of( $abs );
			flxfm_log( 'zip', $rel, implode( ', ', array_column( $items, 'rel' ) ) );
			return array( 'abs' => $abs, 'rel' => $rel, 'name' => basename( $abs ), 'item' => self::item( $abs, $rel ) );
		}
		return array( 'abs' => $abs, 'name' => $name );
	}

	/**
	 * ZIP'i açar. Varsayılan hedef: zip ile aynı adda yeni klasör.
	 *
	 * @return array|WP_Error array( path, files )
	 */
	public static function unzip( $rel, $dest = null ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return self::err( 'nozip', __( 'The PHP zip extension is not available on this server.', 'flx-files' ), 501 );
		}
		$r = self::resolve( $rel );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( null === $dest || '' === $dest ) {
			$dir  = dirname( $r['rel'] );
			$dest = ( '.' === $dir ? '' : $dir . '/' ) . preg_replace( '/\.zip$/i', '', basename( $r['rel'] ) );
			$dest = self::unique( $dest );
		}
		$d = self::resolve( $dest, false );
		if ( is_wp_error( $d ) ) {
			return $d;
		}
		if ( flxfm_in_data( $d['rel'] ) ) {
			return self::err( 'locked', __( 'This folder is managed by Flux Files.', 'flx-files' ), 403 );
		}
		if ( file_exists( $d['abs'] ) && ! is_dir( $d['abs'] ) ) {
			return self::err( 'exists', __( 'A file or folder with this name already exists.', 'flx-files' ), 409 );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $r['abs'] ) ) {
			return self::err( 'zip', __( 'This archive could not be opened.', 'flx-files' ) );
		}
		if ( $zip->numFiles > self::MAX_FILES * 5 ) {
			$zip->close();
			return self::err( 'big', __( 'Too many files in this archive.', 'flx-files' ), 413 );
		}
		// Her girdinin adı önce doğrulanır (zip slip, mutlak yol, ..).
		$entries = array();
		$total   = 0;
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$st = $zip->statIndex( $i );
			if ( ! $st ) {
				continue;
			}
			$name = str_replace( '\\', '/', (string) $st['name'] );
			if ( '' === $name || '/' === $name[0] || preg_match( '#(^|/)\.\.(/|$)#', $name ) || preg_match( '#^[A-Za-z]:#', $name ) || false !== strpos( $name, "\0" ) ) {
				$zip->close();
				return self::err( 'zip', __( 'The archive contains unsafe paths and was not extracted.', 'flx-files' ) );
			}
			$clean = flxfm_clean( $name );
			if ( is_wp_error( $clean ) || '' === $clean ) {
				continue;
			}
			$target = $d['rel'] . '/' . $clean;
			if ( 'panel' !== flxfm_via() || ! flxfm_code_edit_allowed() ) {
				$ok = self::can_write( $target, array() );
				if ( is_wp_error( $ok ) && '/' !== substr( $name, -1 ) ) {
					$zip->close();
					return $ok;
				}
			}
			$total    += (int) $st['size'];
			$entries[] = array( $i, $clean, '/' === substr( $name, -1 ) );
		}
		$free = @disk_free_space( flxfm_root() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( $free && $total > $free ) {
			$zip->close();
			return self::err( 'space', __( 'Not enough disk space to extract this archive.', 'flx-files' ), 507 );
		}
		if ( ! flxfm_writable_mode() ) {
			$zip->close();
			return self::err( 'readonly', __( 'File changes are disabled on this site (DISALLOW_FILE_MODS).', 'flx-files' ), 403 );
		}
		if ( ! is_dir( $d['abs'] ) && ! wp_mkdir_p( $d['abs'] ) ) {
			$zip->close();
			return self::err( 'write', __( 'The folder could not be created (permissions).', 'flx-files' ), 500 );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$files = 0;
		foreach ( $entries as $e ) {
			$abs = $d['abs'] . '/' . $e[1];
			if ( $e[2] ) {
				wp_mkdir_p( $abs );
				continue;
			}
			wp_mkdir_p( dirname( $abs ) );
			if ( file_exists( $abs ) ) {
				$crel = flxfm_rel_of( $abs );
				if ( false !== $crel ) {
					flxfm_version_save( $crel, $abs );
				}
			}
			$in = $zip->getStream( $zip->getNameIndex( $e[0] ) );
			if ( ! $in ) {
				continue;
			}
			$out = @fopen( $abs, 'wb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			if ( $out ) {
				stream_copy_to_stream( $in, $out );
				fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				++$files;
			}
			fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		$zip->close();
		flxfm_log( 'unzip', $r['rel'], $d['rel'] );
		return array( 'path' => $d['rel'], 'files' => $files );
	}

	/** Disk kullanımı (sunucu izin veriyorsa). */
	/**
	 * Depolama: hosting hesabının kullandığı alan (hesap klasörü taranır) ve Ayarlar'daki kota.
	 * Sunucu diskinin tamamı (paylaşımlı hostingde TB'lar) gösterilmez. Tarama parça parça ilerler
	 * (istek başına ~2 sn), sonuç 12 saat saklanır.
	 *
	 * @param bool $force Yeniden ölç.
	 */
	public static function disk( $force = false ) {
		$root = flxfm_usage_root();
		$u    = get_option( 'flxfm_usage', array() );
		$u    = is_array( $u ) && ( $u['root'] ?? '' ) === $root ? $u : array( 'root' => $root );
		$stale = empty( $u['t'] ) || $u['t'] < time() - 12 * HOUR_IN_SECONDS;
		if ( empty( $u['scan'] ) && ( $force || $stale ) ) {
			$u['scan'] = array( 'stack' => array( $root ), 'bytes' => 0, 'files' => 0 );
		}
		if ( ! empty( $u['scan'] ) ) {
			$sc    = $u['scan'];
			$until = microtime( true ) + 2;
			while ( $sc['stack'] && microtime( true ) < $until ) {
				$dir = array_pop( $sc['stack'] );
				$h   = @opendir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( ! $h ) {
					continue;
				}
				while ( false !== ( $e = readdir( $h ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
					if ( '.' === $e || '..' === $e ) {
						continue;
					}
					$f = $dir . '/' . $e;
					if ( is_link( $f ) ) {
						continue;
					}
					if ( is_dir( $f ) ) {
						$sc['stack'][] = $f;
					} else {
						$sc['bytes'] += (float) @filesize( $f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
						++$sc['files'];
					}
				}
				closedir( $h );
			}
			if ( $sc['stack'] ) {
				$u['scan'] = $sc;
			} else {
				unset( $u['scan'] );
				$u['used']  = $sc['bytes'];
				$u['files'] = $sc['files'];
				$u['t']     = time();
			}
			update_option( 'flxfm_usage', $u, false );
		}
		$quota = (float) flxfm_opt( 'quota_gb' ) * 1073741824;
		return array(
			'used'     => isset( $u['used'] ) ? (float) $u['used'] : null,
			'total'    => $quota > 0 ? $quota : null,
			'scanning' => ! empty( $u['scan'] ),
			'files'    => isset( $u['files'] ) ? (int) $u['files'] : null,
			'ago'      => empty( $u['t'] ) ? '' : flx_time_ago( (int) $u['t'] ),
		);
	}
}
