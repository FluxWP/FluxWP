<?php
/**
 * Leftovers after the migration: old-named tables, backup tables of seeded
 * new tables, old upload folders, old plugins/themes and old options.
 * Listed on Tools › Flux Migrate; the admin picks what to delete.
 *
 * @package FlxMigrate
 */

defined( 'ABSPATH' ) || exit;

final class Flx_Migrate_Cleanup {

	/** @var Flx_Migrate_Mapper */
	private $m;

	/** @var array */
	private $map;

	public function __construct( Flx_Migrate_Mapper $m, array $map ) {
		$this->m   = $m;
		$this->map = $map;
	}

	/**
	 * Leftover items, id => [group, label, detail, default checked].
	 * Ids are recomputed on delete; only ids found here can be deleted.
	 */
	public function scan(): array {
		global $wpdb;
		$out = array();
		$pfx = $wpdb->prefix;
		$new = $this->map['rules']['lower'][1];

		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $pfx ) . '%' ) ) as $t ) {
			$short = substr( $t, strlen( $pfx ) );
			$bak   = 0 === strpos( $short, $new ) && preg_match( '/_bak\d{4}(?:_\d+)?$/', $short );
			if ( ! $bak && ! $this->m->is_old( $short ) ) {
				continue;
			}
			$rows                   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$t}`" ); // phpcs:ignore
			$out[ 'table:' . $t ]   = array(
				'group'   => $bak ? 'backup' : 'table',
				'label'   => $t,
				/* translators: %d: satır sayısı */
				'detail'  => sprintf( _n( '%d satır', '%d satır', $rows, 'flx-migrate' ), $rows ),
				'checked' => true,
			);
		}

		$up   = wp_upload_dir( null, false );
		$base = (string) ( $up['basedir'] ?? '' );
		if ( '' !== $base && is_dir( $base ) ) {
			foreach ( (array) scandir( $base ) as $d ) {
				if ( '.' === $d[0] || ! is_dir( $base . '/' . $d ) || ! $this->m->is_old( $d ) ) {
					continue;
				}
				list( $files, $bytes ) = $this->dir_size( $base . '/' . $d );
				$out[ 'upload:' . $d ] = array(
					'group'   => 'upload',
					'label'   => 'uploads/' . $d,
					/* translators: 1: dosya sayısı, 2: boyut */
					'detail'  => sprintf( __( '%1$d dosya, %2$s', 'flx-migrate' ), $files, size_format( $bytes ) ),
					'checked' => true,
				);
			}
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( get_plugins() as $file => $data ) {
			$dir = dirname( $file );
			if ( '.' === $dir || ! $this->m->is_old( $dir ) || is_plugin_active( $file ) ) {
				continue;
			}
			$out[ 'plugin:' . $file ] = array(
				'group'   => 'plugin',
				'label'   => $data['Name'] . ' (' . $dir . ')',
				'detail'  => (string) $data['Version'],
				'checked' => true,
			);
		}

		$active = array( get_option( 'stylesheet' ), get_option( 'template' ) );
		foreach ( array_keys( (array) ( $this->map['themes'] ?? array() ) ) as $th ) {
			if ( in_array( $th, $active, true ) || ! is_dir( get_theme_root() . '/' . $th ) ) {
				continue;
			}
			$out[ 'theme:' . $th ] = array(
				'group'   => 'theme',
				'label'   => $th,
				'detail'  => __( 'İçerikte bu temanın görsel adresleri kalmış olabilir; önce sayfaları kontrol edin.', 'flx-migrate' ),
				'checked' => false,
			);
		}

		$names = array();
		$like  = '%' . $wpdb->esc_like( $this->map['rules']['lower'][0] ) . '%';
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE BINARY %s", $like ) ) as $o ) {
			if ( $this->m->is_old( $o ) ) {
				$names[] = $o;
			}
		}
		if ( $names ) {
			$out['options:old'] = array(
				'group'   => 'options',
				/* translators: %d: ayar sayısı */
				'label'   => sprintf( _n( '%d eski ayar', '%d eski ayar', count( $names ), 'flx-migrate' ), count( $names ) ),
				'detail'  => implode( ', ', array_slice( $names, 0, 12 ) ) . ( count( $names ) > 12 ? ', …' : '' ),
				'checked' => true,
				'names'   => $names,
			);
		}
		return $out;
	}

	/**
	 * Deletes the selected ids that the scan still finds.
	 *
	 * @param string[] $ids Selected ids.
	 * @return array{0:int,1:string[]} [deleted count, errors]
	 */
	public function delete( array $ids ): array {
		global $wpdb;
		$items   = $this->scan();
		$deleted = 0;
		$errors  = array();
		$plugins = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $items[ $id ] ) ) {
				continue;
			}
			list( $type, $key ) = explode( ':', $id, 2 );
			switch ( $type ) {
				case 'table':
					if ( false !== $wpdb->query( "DROP TABLE `{$key}`" ) ) { // phpcs:ignore
						$deleted++;
					}
					break;
				case 'upload':
					$up   = wp_upload_dir( null, false );
					$base = realpath( (string) $up['basedir'] );
					$dir  = realpath( $up['basedir'] . '/' . $key );
					if ( $base && $dir && dirname( $dir ) === $base && $this->rmdir( $dir ) ) {
						$deleted++;
					} else {
						$errors[] = $items[ $id ]['label'];
					}
					break;
				case 'plugin':
					$plugins[] = $key;
					break;
				case 'theme':
					require_once ABSPATH . 'wp-admin/includes/theme.php';
					require_once ABSPATH . 'wp-admin/includes/file.php';
					$r = delete_theme( $key );
					if ( true === $r ) {
						$deleted++;
					} else {
						$errors[] = $items[ $id ]['label'];
					}
					break;
				case 'options':
					foreach ( (array) $items[ $id ]['names'] as $o ) {
						delete_option( $o );
					}
					$deleted++;
					break;
			}
		}
		if ( $plugins ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$r = delete_plugins( $plugins );
			if ( true === $r ) {
				$deleted += count( $plugins );
			} else {
				foreach ( $plugins as $p ) {
					$errors[] = $items[ 'plugin:' . $p ]['label'];
				}
			}
		}
		wp_cache_flush();
		return array( $deleted, $errors );
	}

	private function dir_size( string $dir ): array {
		$n = 0;
		$b = 0;
		try {
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $f ) {
				if ( $f->isFile() ) {
					$n++;
					$b += (int) $f->getSize();
				}
			}
		} catch ( Exception $e ) { // phpcs:ignore
			unset( $e );
		}
		return array( $n, $b );
	}

	private function rmdir( string $dir ): bool {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			$ok = $f->isDir() && ! $f->isLink() ? @rmdir( $f->getPathname() ) : @unlink( $f->getPathname() ); // phpcs:ignore
			if ( ! $ok ) {
				return false;
			}
		}
		return @rmdir( $dir ); // phpcs:ignore
	}
}
