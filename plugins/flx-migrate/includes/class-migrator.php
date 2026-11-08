<?php
/**
 * Veritabanı ve dosya aktarımı. Her adım parça parça çalışır (PHP süre sınırı) ve tekrar çalıştırılabilir.
 *
 * @package FlxMigrate
 */

defined( 'ABSPATH' ) || exit;

final class Flx_Migrate_Migrator {

	const BATCH  = 200;
	const BUDGET = 20; // sn.

	/** @var Flx_Migrate_Mapper */
	private $m;

	/** @var array */
	private $map;

	/** @var bool */
	private $dry;

	/** @var float */
	private $t0;

	public function __construct( Flx_Migrate_Mapper $m, array $map, bool $dry = false ) {
		$this->m   = $m;
		$this->map = $map;
		$this->dry = $dry;
		$this->t0  = microtime( true );
	}

	/** Adımlar, sırayla. */
	public static function steps(): array {
		return array(
			'tables'   => __( 'Tables', 'flx-migrate' ),
			'tabledata'=> __( 'Table contents', 'flx-migrate' ),
			'options'  => __( 'Settings (options)', 'flx-migrate' ),
			'theme'    => __( 'Theme', 'flx-migrate' ),
			'postmeta' => __( 'Post meta', 'flx-migrate' ),
			'usermeta' => __( 'User meta', 'flx-migrate' ),
			'termmeta' => __( 'Term meta', 'flx-migrate' ),
			'orders'   => __( 'WooCommerce orders', 'flx-migrate' ),
			'itemmeta' => __( 'Order items', 'flx-migrate' ),
			'posts'    => __( 'Posts and pages', 'flx-migrate' ),
			'terms'    => __( 'Taxonomies', 'flx-migrate' ),
			'uploads'  => __( 'Upload folders', 'flx-migrate' ),
			'secrets'  => __( 'Encrypted settings (passwords, keys)', 'flx-migrate' ),
			'finish'   => __( 'Finish', 'flx-migrate' ),
		);
	}

	/** Yeni eklentilerden kurulu olmayanlar (etkin eski eklentilere göre). */
	public function missing_plugins(): array {
		$out = array();
		foreach ( (array) get_option( 'active_plugins', array() ) as $p ) {
			$n = $this->m->name( $p );
			if ( $n !== $p && ! file_exists( WP_PLUGIN_DIR . '/' . $n ) ) {
				$out[ $p ] = $n;
			}
		}
		$ss = (string) get_option( 'stylesheet' );
		$nt = $this->m->theme( $ss );
		if ( $nt !== $ss && ! file_exists( get_theme_root() . '/' . $nt . '/style.css' ) ) {
			$out[ $ss ] = $nt;
		}
		return $out;
	}

	private function over_budget(): bool {
		return microtime( true ) - $this->t0 > self::BUDGET;
	}

	/**
	 * Bir adımı çalıştırır.
	 *
	 * @return array{done:bool,cursor:int,changed:int,notes:array}
	 */
	public function run( string $step, int $cursor = 0 ): array {
		$fn = 'step_' . $step;
		if ( ! method_exists( $this, $fn ) ) {
			return array( 'done' => true, 'cursor' => 0, 'changed' => 0, 'notes' => array() );
		}
		return $this->$fn( $cursor );
	}

	private function res( bool $done, int $cursor, int $changed, array $notes = array() ): array {
		return compact( 'done', 'cursor', 'changed', 'notes' );
	}

	private function old_like(): array {
		global $wpdb;
		$like = array();
		foreach ( array( 'lower', 'upper' ) as $k ) {
			$like[] = '%' . $wpdb->esc_like( $this->map['rules'][ $k ][0] ) . '%';
		}
		foreach ( array_keys( (array) $this->map['themes'] ) as $t ) {
			$like[] = '%' . $wpdb->esc_like( $t ) . '%';
		}
		return array_values( array_unique( $like ) );
	}

	private function where_like( string $col ): string {
		global $wpdb;
		$parts = array();
		foreach ( $this->old_like() as $l ) {
			$parts[] = $wpdb->prepare( "{$col} LIKE BINARY %s", $l ); // phpcs:ignore
		}
		return '(' . implode( ' OR ', $parts ) . ')';
	}

	/* ---------------------------------------------------------------- */

	private function step_tables( int $cursor ): array {
		global $wpdb;
		$notes   = array();
		$changed = 0;
		$pfx     = $wpdb->prefix;
		$tables  = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $pfx ) . '%' ) );
		foreach ( $tables as $t ) {
			$short = substr( $t, strlen( $pfx ) );
			$new   = $this->m->name( $short );
			if ( $new === $short ) {
				continue;
			}
			$target = $pfx . $new;
			if ( in_array( $target, $tables, true ) ) {
				$rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$target}`" ); // phpcs:ignore
				$old  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$t}`" ); // phpcs:ignore
				if ( $rows > 0 && 0 === $old ) {
					/* translators: 1: eski tablo, 2: yeni tablo */
					$notes[] = sprintf( __( '%1$s is empty, %2$s was left as it is.', 'flx-migrate' ), $t, $target );
					continue;
				}
				if ( $rows > 0 ) {
					// The new plugin was activated before the migration and wrote its
					// own rows (e.g. a welcome task). The old data wins; the new
					// table is kept under a backup name instead of being dropped.
					$bak = $this->backup_name( $target, $tables );
					/* translators: 1: yeni tablo, 2: yedek tablo, 3: eski tablo */
					$notes[] = sprintf( __( '%1$s was not empty: it was backed up as %2$s and replaced by %3$s.', 'flx-migrate' ), $target, $bak, $t );
					if ( ! $this->dry ) {
						$wpdb->query( "RENAME TABLE `{$target}` TO `{$bak}`" ); // phpcs:ignore
					}
				} elseif ( ! $this->dry ) {
					$wpdb->query( "DROP TABLE `{$target}`" ); // phpcs:ignore
				}
			}
			if ( ! $this->dry ) {
				$wpdb->query( "RENAME TABLE `{$t}` TO `{$target}`" ); // phpcs:ignore
			}
			$changed++;
		}
		return $this->res( true, 0, $changed, $notes );
	}

	/** Free backup table name (MySQL table names are limited to 64 characters). */
	private function backup_name( string $target, array $tables ): string {
		$base = substr( $target, 0, 52 ) . '_bak' . gmdate( 'md' );
		$name = $base;
		for ( $i = 2; in_array( $name, $tables, true ); $i++ ) {
			$name = $base . '_' . $i;
		}
		return $name;
	}

	/** Yeni adlı tabloların metin sütunlarında eski tanımlayıcılar. */
	private function step_tabledata( int $cursor ): array {
		global $wpdb;
		$changed = 0;
		$tables  = array_values( array_filter( (array) get_option( 'flx_migrate_tables', array() ) ) );
		if ( ! $tables ) {
			$all = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
			foreach ( $all as $t ) {
				$short = substr( $t, strlen( $wpdb->prefix ) );
				if ( 0 === strpos( $short, $this->map['rules']['lower'][1] ) || $this->m->is_old( $short ) ) {
					$tables[] = $t;
				}
			}
			if ( ! $this->dry ) {
				update_option( 'flx_migrate_tables', $tables, false );
			}
		}
		for ( $i = $cursor; $i < count( $tables ); $i++ ) {
			$t    = $tables[ $i ];
			$cols = $wpdb->get_results( "SHOW COLUMNS FROM `{$t}`", ARRAY_A ); // phpcs:ignore
			$pk   = '';
			$txt  = array();
			foreach ( (array) $cols as $c ) {
				if ( 'PRI' === $c['Key'] && '' === $pk ) {
					$pk = $c['Field'];
				}
				if ( preg_match( '/char|text|json/i', $c['Type'] ) ) {
					$txt[] = $c['Field'];
				}
			}
			if ( '' === $pk || ! $txt ) {
				continue;
			}
			foreach ( $txt as $col ) {
				$rows = $wpdb->get_results( "SELECT `{$pk}` AS id, `{$col}` AS v FROM `{$t}` WHERE " . $this->where_like( "`{$col}`" ), ARRAY_A ); // phpcs:ignore
				foreach ( (array) $rows as $r ) {
					$nv = $this->m->value( (string) $r['v'] );
					if ( $nv !== $r['v'] ) {
						$changed++;
						if ( ! $this->dry ) {
							$wpdb->update( $t, array( $col => $nv ), array( $pk => $r['id'] ) );
						}
					}
				}
			}
			if ( $this->over_budget() ) {
				return $this->res( false, $i + 1, $changed );
			}
		}
		return $this->res( true, 0, $changed );
	}

	private function step_options( int $cursor ): array {
		global $wpdb;
		$changed = 0;
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, option_name, option_value FROM {$wpdb->options} WHERE option_id > %d AND (" // phpcs:ignore
				. $this->where_like( 'option_name' ) . ' OR ' . $this->where_like( 'option_value' )
				. ') ORDER BY option_id LIMIT %d',
				$cursor,
				self::BATCH
			),
			ARRAY_A
		);
		foreach ( (array) $rows as $r ) {
			$cursor = (int) $r['option_id'];
			$name   = $r['option_name'];
			if ( preg_match( '/^_(site_)?transient_/', $name ) && $this->m->is_old( $name ) ) {
				$changed++;
				if ( ! $this->dry ) {
					$wpdb->delete( $wpdb->options, array( 'option_id' => $cursor ) );
				}
				continue;
			}
			if ( 0 === strpos( $name, 'flx_migrate_' ) ) {
				continue;
			}
			$nn = $this->m->name( $name );
			if ( 0 === strpos( $name, 'theme_mods_' ) ) {
				$nn = 'theme_mods_' . $this->m->theme( substr( $name, 11 ) );
			}
			$nv = $this->m->value( $r['option_value'] );
			if ( $nn === $name && $nv === $r['option_value'] ) {
				continue;
			}
			$changed++;
			if ( $this->dry ) {
				continue;
			}
			if ( $nn !== $name ) {
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $nn ) ); // phpcs:ignore
				if ( $exists ) {
					$wpdb->delete( $wpdb->options, array( 'option_id' => (int) $exists ) );
				}
			}
			$wpdb->update( $wpdb->options, array( 'option_name' => $nn, 'option_value' => $nv ), array( 'option_id' => $cursor ) );
		}
		$done = count( (array) $rows ) < self::BATCH;
		return $this->res( $done, $done ? 0 : $cursor, $changed );
	}

	private function step_theme( int $cursor ): array {
		$changed = 0;
		foreach ( array( 'stylesheet', 'template' ) as $k ) {
			$v = (string) get_option( $k );
			$n = $this->m->theme( $v );
			if ( $n !== $v && file_exists( get_theme_root() . '/' . $n . '/style.css' ) ) {
				$changed++;
				if ( ! $this->dry ) {
					update_option( $k, $n );
				}
			}
		}
		return $this->res( true, 0, $changed );
	}

	private function meta( string $table, string $id_col, int $cursor ): array {
		global $wpdb;
		$changed = 0;
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$id_col} AS id, meta_key, meta_value FROM {$table} WHERE {$id_col} > %d AND (" // phpcs:ignore
				. $this->where_like( 'meta_key' ) . ' OR ' . $this->where_like( 'meta_value' )
				. ") ORDER BY {$id_col} LIMIT %d",
				$cursor,
				self::BATCH
			),
			ARRAY_A
		);
		foreach ( (array) $rows as $r ) {
			$cursor = (int) $r['id'];
			$nk     = $this->m->name( (string) $r['meta_key'] );
			$nv     = $this->m->value( (string) $r['meta_value'] );
			if ( $nk === $r['meta_key'] && $nv === $r['meta_value'] ) {
				continue;
			}
			$changed++;
			if ( ! $this->dry ) {
				$wpdb->update( $table, array( 'meta_key' => $nk, 'meta_value' => $nv ), array( $id_col => $cursor ) );
			}
		}
		$done = count( (array) $rows ) < self::BATCH;
		return $this->res( $done, $done ? 0 : $cursor, $changed );
	}

	private function step_postmeta( int $c ): array {
		global $wpdb;
		return $this->meta( $wpdb->postmeta, 'meta_id', $c );
	}

	private function step_usermeta( int $c ): array {
		global $wpdb;
		return $this->meta( $wpdb->usermeta, 'umeta_id', $c );
	}

	private function step_termmeta( int $c ): array {
		global $wpdb;
		return $this->meta( $wpdb->termmeta, 'meta_id', $c );
	}

	/**
	 * WooCommerce HPOS: order meta and the payment method column
	 * (gateway ids such as ytb_havale). Classic orders live in postmeta.
	 */
	private function step_orders( int $c ): array {
		global $wpdb;
		$t = $wpdb->prefix . 'wc_orders';
		if ( ! $this->table_exists( $t . '_meta' ) ) {
			return $this->res( true, 0, 0 );
		}
		$changed = 0;
		if ( 0 === $c && $this->table_exists( $t ) ) {
			$rows = $wpdb->get_col( "SELECT DISTINCT payment_method FROM `{$t}` WHERE " . $this->where_like( 'payment_method' ) ); // phpcs:ignore
			foreach ( (array) $rows as $pm ) {
				$n = $this->m->name( (string) $pm );
				if ( $n === $pm ) {
					continue;
				}
				$changed += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t}` WHERE payment_method = %s", $pm ) ); // phpcs:ignore
				if ( ! $this->dry ) {
					$wpdb->update( $t, array( 'payment_method' => $n ), array( 'payment_method' => $pm ) );
				}
			}
		}
		$r             = $this->meta( $t . '_meta', 'id', $c );
		$r['changed'] += $changed;
		return $r;
	}

	private function step_itemmeta( int $c ): array {
		global $wpdb;
		$t = $wpdb->prefix . 'woocommerce_order_itemmeta';
		if ( ! $this->table_exists( $t ) ) {
			return $this->res( true, 0, 0 );
		}
		return $this->meta( $t, 'meta_id', $c );
	}

	private function table_exists( string $t ): bool {
		global $wpdb;
		return $t === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) );
	}

	/**
	 * Secrets sealed with a key derived from the old prefix are re-sealed
	 * with the new key. Runs after every rename step; safe to repeat.
	 * Cursor = index of the source (options, meta tables, Flux tables).
	 */
	private function step_secrets( int $cursor ): array {
		global $wpdb;
		if ( ! Flx_Migrate_Secrets::available() ) {
			return $this->res( true, 0, 0, array( __( 'Sodium is missing: encrypted settings could not be moved, enter them again in the panel.', 'flx-migrate' ) ) );
		}
		$like = array();
		foreach ( array( 'yti0:', 'yti1:', 'flxi0:', 'flxi1:', 'enc1:', 's1:', 'o1:' ) as $p ) {
			$like[] = '%' . $wpdb->esc_like( $p ) . '%';
		}
		$where = static function ( string $col ) use ( $wpdb, $like ): string {
			$parts = array();
			foreach ( $like as $l ) {
				$parts[] = $wpdb->prepare( "{$col} LIKE BINARY %s", $l ); // phpcs:ignore
			}
			return '(' . implode( ' OR ', $parts ) . ')';
		};
		$sources = array(
			array( $wpdb->options, 'option_id', 'option_value' ),
			array( $wpdb->postmeta, 'meta_id', 'meta_value' ),
			array( $wpdb->usermeta, 'umeta_id', 'meta_value' ),
		);
		if ( $this->table_exists( $wpdb->prefix . 'wc_orders_meta' ) ) {
			$sources[] = array( $wpdb->prefix . 'wc_orders_meta', 'id', 'meta_value' );
		}
		$new = $this->map['rules']['lower'][1];
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . $new ) . '%' ) ) as $t ) {
			$pk  = '';
			$txt = array();
			foreach ( (array) $wpdb->get_results( "SHOW COLUMNS FROM `{$t}`", ARRAY_A ) as $col ) { // phpcs:ignore
				if ( 'PRI' === $col['Key'] && '' === $pk ) {
					$pk = $col['Field'];
				}
				if ( preg_match( '/char|text|json/i', $col['Type'] ) ) {
					$txt[] = $col['Field'];
				}
			}
			foreach ( $pk ? $txt : array() as $col ) {
				$sources[] = array( $t, $pk, $col );
			}
		}
		$sec     = new Flx_Migrate_Secrets();
		$changed = 0;
		for ( $i = $cursor; $i < count( $sources ); $i++ ) {
			list( $t, $pk, $col ) = $sources[ $i ];
			$rows = $wpdb->get_results( "SELECT `{$pk}` AS id, `{$col}` AS v FROM `{$t}` WHERE " . $where( "`{$col}`" ), ARRAY_A ); // phpcs:ignore
			foreach ( (array) $rows as $r ) {
				$nv = $sec->convert( (string) $r['v'] );
				if ( null === $nv ) {
					continue;
				}
				$changed++;
				if ( ! $this->dry ) {
					$wpdb->update( $t, array( $col => $nv ), array( $pk => $r['id'] ) );
				}
			}
			if ( $this->over_budget() ) {
				return $this->res( false, $i + 1, $changed );
			}
		}
		$notes = array();
		if ( $sec->failed() ) {
			/* translators: %d: sayı */
			$notes[] = sprintf( __( '%d encrypted values could not be opened (the site keys may have changed); enter them again in the panel.', 'flx-migrate' ), $sec->failed() );
		}
		if ( ! $this->dry ) {
			wp_cache_flush();
		}
		return $this->res( true, 0, $changed, $notes );
	}

	private function step_posts( int $cursor ): array {
		global $wpdb;
		$changed = 0;
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_type, post_content, post_excerpt FROM {$wpdb->posts} WHERE ID > %d AND (" // phpcs:ignore
				. $this->where_like( 'post_type' ) . ' OR ' . $this->where_like( 'post_content' ) . ' OR ' . $this->where_like( 'post_excerpt' )
				. ') ORDER BY ID LIMIT %d',
				$cursor,
				self::BATCH
			),
			ARRAY_A
		);
		foreach ( (array) $rows as $r ) {
			$cursor = (int) $r['ID'];
			$set    = array();
			$pt     = $this->m->name( $r['post_type'] );
			if ( $pt !== $r['post_type'] ) {
				$set['post_type'] = $pt;
			}
			foreach ( array( 'post_content', 'post_excerpt' ) as $col ) {
				$n = $this->m->content( (string) $r[ $col ] );
				if ( $n !== $r[ $col ] ) {
					$set[ $col ] = $n;
				}
			}
			if ( ! $set ) {
				continue;
			}
			$changed++;
			if ( ! $this->dry ) {
				$wpdb->update( $wpdb->posts, $set, array( 'ID' => $cursor ) );
				clean_post_cache( $cursor );
			}
		}
		$done = count( (array) $rows ) < self::BATCH;
		return $this->res( $done, $done ? 0 : $cursor, $changed );
	}

	private function step_terms( int $cursor ): array {
		global $wpdb;
		$changed = 0;
		$rows    = $wpdb->get_results( "SELECT term_taxonomy_id, taxonomy FROM {$wpdb->term_taxonomy} WHERE " . $this->where_like( 'taxonomy' ), ARRAY_A ); // phpcs:ignore
		foreach ( (array) $rows as $r ) {
			$n = $this->m->name( $r['taxonomy'] );
			if ( $n !== $r['taxonomy'] ) {
				$changed++;
				if ( ! $this->dry ) {
					$wpdb->update( $wpdb->term_taxonomy, array( 'taxonomy' => $n ), array( 'term_taxonomy_id' => $r['term_taxonomy_id'] ) );
				}
			}
		}
		// Blok şablonları temaya wp_theme terimiyle bağlıdır.
		$terms = $wpdb->get_results( "SELECT t.term_id, t.slug, t.name FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'wp_theme'", ARRAY_A ); // phpcs:ignore
		foreach ( (array) $terms as $t ) {
			$n = $this->m->theme( $t['slug'] );
			if ( $n !== $t['slug'] ) {
				$changed++;
				if ( ! $this->dry ) {
					$wpdb->update( $wpdb->terms, array( 'slug' => $n, 'name' => $n ), array( 'term_id' => $t['term_id'] ) );
				}
			}
		}
		return $this->res( true, 0, $changed );
	}

	private function step_uploads( int $cursor ): array {
		$changed = 0;
		$notes   = array();
		$up      = wp_upload_dir( null, false );
		$base    = (string) ( $up['basedir'] ?? '' );
		if ( '' === $base || ! is_dir( $base ) ) {
			return $this->res( true, 0, 0 );
		}
		foreach ( (array) scandir( $base ) as $d ) {
			if ( '.' === $d[0] || ! is_dir( $base . '/' . $d ) ) {
				continue;
			}
			$n = $this->m->name( $d );
			if ( $n === $d ) {
				continue;
			}
			if ( file_exists( $base . '/' . $n ) ) {
				// The new plugin already created its folder (protection files,
				// seed data): move the old files in, keep what is already there.
				$kept  = 0;
				$moved = $this->merge_dir( $base . '/' . $d, $base . '/' . $n, $kept );
				if ( $moved ) {
					$changed++;
					/* translators: 1: eski klasör, 2: yeni klasör, 3: dosya sayısı */
					$notes[] = sprintf( __( '%3$d items from %1$s were moved into %2$s.', 'flx-migrate' ), 'uploads/' . $d, 'uploads/' . $n, $moved );
				}
				if ( $kept ) {
					/* translators: 1: eski klasör, 2: sayı */
					$notes[] = sprintf( __( '%2$d files in %1$s already existed in the new folder; the old copies were left in place.', 'flx-migrate' ), 'uploads/' . $d, $kept );
				}
				continue;
			}
			$changed++;
			if ( ! $this->dry ) {
				rename( $base . '/' . $d, $base . '/' . $n ); // phpcs:ignore
			}
		}
		return $this->res( true, 0, $changed, $notes );
	}

	/**
	 * Moves entries of $from into $to; existing files in $to win. Returns
	 * the number of moved entries; $kept counts files left in $from.
	 */
	private function merge_dir( string $from, string $to, int &$kept ): int {
		$moved = 0;
		foreach ( (array) scandir( $from ) as $e ) {
			if ( '.' === $e || '..' === $e ) {
				continue;
			}
			$src = $from . '/' . $e;
			$dst = $to . '/' . $e;
			if ( ! file_exists( $dst ) ) {
				$moved++;
				if ( ! $this->dry ) {
					rename( $src, $dst ); // phpcs:ignore
				}
			} elseif ( is_dir( $src ) && is_dir( $dst ) ) {
				$moved += $this->merge_dir( $src, $dst, $kept );
			} elseif ( is_file( $src ) && is_file( $dst ) && md5_file( $src ) === md5_file( $dst ) ) {
				if ( ! $this->dry ) {
					unlink( $src ); // phpcs:ignore
				}
			} else {
				$kept++;
			}
		}
		if ( ! $this->dry ) {
			@rmdir( $from ); // phpcs:ignore -- only succeeds when empty.
		}
		return $moved;
	}

	private function step_finish( int $cursor ): array {
		$notes   = array();
		$changed = $this->reset_update_server( $notes );
		if ( ! $this->dry ) {
			delete_option( 'flx_migrate_tables' );
			delete_option( 'rewrite_rules' );
			wp_cache_flush();
			update_option( 'flx_migrate_done', time(), false );
		}
		return $this->res( true, 0, $changed, $notes );
	}

	/**
	 * The old updater's settings (server address + site key) are carried into
	 * Flux Setup by the options step, but they point at the retired update
	 * server, whose key is not valid on the new one. Clear both so Flux Setup
	 * falls back to its default server and asks for a new key; drop the old
	 * catalog caches too.
	 */
	private function reset_update_server( array &$notes ): int {
		$s = get_site_option( 'flxu_settings', array() );
		if ( ! is_array( $s ) || empty( $s['server'] ) ) {
			return 0;
		}
		$host = (string) wp_parse_url( (string) $s['server'], PHP_URL_HOST );
		$old  = (array) apply_filters( 'flx_migrate_old_update_hosts', array( 'update.yesilturkiyeist.org', 'update.ustageliyor.org' ) );
		if ( ! in_array( strtolower( $host ), $old, true ) ) {
			return 0;
		}
		$notes[] = __( 'The old update-server address and site key were removed. Enter the new server\'s key under FluxWP Panel › Settings.', 'flx-migrate' );
		if ( $this->dry ) {
			return 1;
		}
		$s['server'] = '';
		$s['key']    = '';
		update_site_option( 'flxu_settings', $s );
		foreach ( array( 'flxu_status', 'flxu_catalog_last', 'flxu_known_slugs' ) as $o ) {
			delete_site_option( $o );
		}
		return 1;
	}
}
