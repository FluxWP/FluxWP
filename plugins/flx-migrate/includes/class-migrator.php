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
			'tables'   => __( 'Tablolar', 'flx-migrate' ),
			'tabledata'=> __( 'Tablo içerikleri', 'flx-migrate' ),
			'options'  => __( 'Ayarlar (options)', 'flx-migrate' ),
			'theme'    => __( 'Tema', 'flx-migrate' ),
			'postmeta' => __( 'Yazı meta verileri', 'flx-migrate' ),
			'usermeta' => __( 'Kullanıcı meta verileri', 'flx-migrate' ),
			'termmeta' => __( 'Terim meta verileri', 'flx-migrate' ),
			'posts'    => __( 'Yazılar ve sayfalar', 'flx-migrate' ),
			'terms'    => __( 'Taksonomiler', 'flx-migrate' ),
			'uploads'  => __( 'Yükleme klasörleri', 'flx-migrate' ),
			'finish'   => __( 'Bitiş', 'flx-migrate' ),
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
				if ( $rows > 0 ) {
					/* translators: 1: eski tablo, 2: yeni tablo */
					$notes[] = sprintf( __( '%1$s atlandı: %2$s dolu.', 'flx-migrate' ), $t, $target );
					continue;
				}
				if ( ! $this->dry ) {
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
				/* translators: %s: klasör */
				$notes[] = sprintf( __( '%s zaten var, klasör taşınmadı.', 'flx-migrate' ), 'uploads/' . $n );
				continue;
			}
			$changed++;
			if ( ! $this->dry ) {
				rename( $base . '/' . $d, $base . '/' . $n ); // phpcs:ignore
			}
		}
		return $this->res( true, 0, $changed, $notes );
	}

	private function step_finish( int $cursor ): array {
		if ( ! $this->dry ) {
			delete_option( 'flx_migrate_tables' );
			delete_option( 'rewrite_rules' );
			wp_cache_flush();
			update_option( 'flx_migrate_done', time(), false );
		}
		return $this->res( true, 0, 0 );
	}
}
