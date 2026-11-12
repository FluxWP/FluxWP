<?php
/**
 * Tablolar, yetkiler ve zamanlanmış işler.
 *
 * Kişiler ve kaynak bağlantıları ortak FluxWP tablosundadır (wp_flx_people, wp_flx_people_links; flx-brand people.php):
 * CRM kurulu olmasa da her paket aynı kişiyi kullanır. CRM bu tabloya yalnız kendi sütunlarını ekler (people_columns).
 * events         Zaman tüneli. `uniq` doluysa aynı olay ikinci kez yazılmaz (kaynaktan tekrar gelse de).
 * tags, contact_tags
 * segments       Kayıtlı dinamik segment kuralları.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Install {

	/** Kişi tablosunda CRM'e ait sütunlar (ortak tabloya `flx_people_columns` ile eklenir). */
	public static function people_columns( $cols ) {
		$cols = is_array( $cols ) ? $cols : array();
		return array_merge(
			$cols,
			array(
				'owner_id'         => 'bigint(20) unsigned NOT NULL DEFAULT 0',
				'utm_source'       => "varchar(100) NOT NULL DEFAULT ''",
				'utm_campaign'     => "varchar(100) NOT NULL DEFAULT ''",
				'ltv'              => 'decimal(18,2) NOT NULL DEFAULT 0',
				'donations'        => 'int(10) unsigned NOT NULL DEFAULT 0',
				'orders'           => 'int(10) unsigned NOT NULL DEFAULT 0',
				'paid_days'        => 'int(10) unsigned NOT NULL DEFAULT 0',
				'first_paid_at'    => 'datetime NULL DEFAULT NULL',
				'last_paid_at'     => 'datetime NULL DEFAULT NULL',
				'last_activity_at' => 'datetime NULL DEFAULT NULL',
				'avg_gap_days'     => 'int(10) unsigned NOT NULL DEFAULT 0',
				'r_score'          => 'tinyint(1) unsigned NOT NULL DEFAULT 0',
				'f_score'          => 'tinyint(1) unsigned NOT NULL DEFAULT 0',
				'm_score'          => 'tinyint(1) unsigned NOT NULL DEFAULT 0',
				'segment'          => "varchar(16) NOT NULL DEFAULT ''",
				'churn'            => 'tinyint(3) unsigned NOT NULL DEFAULT 0',
				'scored_at'        => 'datetime NULL DEFAULT NULL',
			)
		);
	}

	public static function people_keys( $keys ) {
		$keys = is_array( $keys ) ? $keys : array();
		return array_merge(
			$keys,
			array(
				'segment'          => 'segment',
				'last_activity_at' => 'last_activity_at',
				'last_paid_at'     => 'last_paid_at',
				'ltv'              => 'ltv',
			)
		);
	}

	/** Ortak kişi modülü (flx-brand 1.3.0+) bu istekte yüklü mü? Etkinleştirme anında eski bir kopya yüklenmiş olabilir. */
	public static function people_ready() {
		return function_exists( 'flx_people_ready' );
	}

	public static function maybe_upgrade() {
		if ( ! self::people_ready() ) {
			return;
		}
		// Tablolar ve CRM sütunları (gerekiyorsa eski flxcrm_contacts buraya taşınır: migrate_people).
		flx_people_ready();
		if ( (int) get_option( 'flxcrm_db_version' ) < FLXCRM_DB_VERSION ) {
			self::install();
		}
	}

	private static function table_exists( $t ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) ) === $t; // phpcs:ignore WordPress.DB
	}

	/**
	 * 0.2.x'in kendi kişi tablolarını (flxcrm_contacts, flxcrm_links) ortak tabloya taşır. `flx_people_before_install`.
	 * Ortak tablo yoksa ya da boşsa ad değiştirilir (kimlikler aynı kalır). Ortak tabloda başka paketin açtığı kişiler
	 * varsa satırlar e-posta / telefon / üye eşleşmesiyle birleştirilir ve CRM kayıtlarındaki kişi kimlikleri güncellenir.
	 */
	public static function migrate_people() {
		global $wpdb;
		$old   = $wpdb->prefix . 'flxcrm_contacts';
		$old_l = $wpdb->prefix . 'flxcrm_links';
		$new   = $wpdb->prefix . 'flx_people';
		$new_l = $wpdb->prefix . 'flx_people_links';
		if ( ! self::table_exists( $old ) ) {
			return;
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$has_new = self::table_exists( $new );
		if ( $has_new && ! (int) $wpdb->get_var( "SELECT COUNT(*) FROM $new" ) ) { // phpcs:ignore WordPress.DB
			$wpdb->query( "DROP TABLE $new" ); // phpcs:ignore WordPress.DB
			$has_new = false;
		}
		if ( ! $has_new ) {
			// Ad değiştirme: en hızlı yol, kimlikler aynı kalır. Veritabanı desteklemiyorsa aşağıdaki kopyalama yolu çalışır.
			$show = $wpdb->suppress_errors( true );
			$wpdb->query( "ALTER TABLE $old RENAME TO $new" ); // phpcs:ignore WordPress.DB
			if ( ! self::table_exists( $old ) && self::table_exists( $new ) && self::table_exists( $old_l ) ) {
				if ( self::table_exists( $new_l ) && ! (int) $wpdb->get_var( "SELECT COUNT(*) FROM $new_l" ) ) { // phpcs:ignore WordPress.DB
					$wpdb->query( "DROP TABLE $new_l" ); // phpcs:ignore WordPress.DB
				}
				if ( ! self::table_exists( $new_l ) ) {
					$wpdb->query( "ALTER TABLE $old_l RENAME TO $new_l" ); // phpcs:ignore WordPress.DB
				}
			}
			$wpdb->suppress_errors( $show );
			if ( ! self::table_exists( $old ) && ! self::table_exists( $old_l ) ) {
				return;
			}
		}

		/* Ortak tabloda kişi var (ya da ad değiştirilemedi): satırları kopyala / birleştir. */
		flx_people_install(); // Tablo ve CRM sütunları hazır olsun.
		$cols = array_keys( flx_people_columns() );
		$map  = array();
		$last = 0;
		$rows = array();
		do {
			if ( ! self::table_exists( $old ) ) {
				break;
			}
			$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $old WHERE id > %d ORDER BY id LIMIT 500", $last ), ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( $rows as $r ) {
				$last = (int) $r['id'];
				$id   = 0;
				if ( (int) $r['user_id'] ) {
					$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $new WHERE user_id = %d ORDER BY id LIMIT 1", (int) $r['user_id'] ) ); // phpcs:ignore WordPress.DB
				}
				if ( ! $id && '' !== (string) $r['email'] ) {
					$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $new WHERE email = %s ORDER BY id LIMIT 1", $r['email'] ) ); // phpcs:ignore WordPress.DB
				}
				if ( ! $id && '' !== (string) $r['phone'] ) {
					$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $new WHERE phone = %s ORDER BY id LIMIT 1", $r['phone'] ) ); // phpcs:ignore WordPress.DB
				}
				if ( $id ) {
					// Var olan kişi: CRM'in bildiği dolu alanlar boşları doldurur, CRM sütunları aktarılır.
					$cur = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $new WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
					$upd = array();
					foreach ( $cols as $c ) {
						if ( 'id' === $c || ! array_key_exists( $c, $r ) || null === $r[ $c ] ) {
							continue;
						}
						$empty = null === $cur[ $c ] || '' === (string) $cur[ $c ] || '0' === (string) $cur[ $c ] || '0.00' === (string) $cur[ $c ];
						if ( $empty && '' !== (string) $r[ $c ] ) {
							$upd[ $c ] = $r[ $c ];
						}
					}
					if ( '' !== (string) $r['roles'] ) {
						$upd['roles'] = flx_person_pack_roles( array_merge( flx_person_roles( $cur['roles'] ), flx_person_roles( $r['roles'] ) ) );
					}
					if ( (string) $r['created_at'] < (string) $cur['created_at'] ) {
						$upd['created_at'] = $r['created_at'];
					}
					if ( $upd ) {
						$wpdb->update( $new, $upd, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
					}
				} else {
					$row = array_intersect_key( $r, array_flip( $cols ) );
					// Kimlik boşsa aynı kalır (başka paketlerin tuttuğu kişi kimlikleri bozulmasın).
					if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $new WHERE id = %d", (int) $r['id'] ) ) ) { // phpcs:ignore WordPress.DB
						unset( $row['id'] );
					}
					foreach ( $row as $k => $v ) {
						if ( null === $v ) {
							unset( $row[ $k ] );
						}
					}
					$wpdb->insert( $new, $row ); // phpcs:ignore WordPress.DB
					$id = (int) $wpdb->insert_id;
				}
				if ( $id ) {
					$map[ (int) $r['id'] ] = $id;
				}
			}
		} while ( count( $rows ) === 500 );

		/* CRM kayıtlarındaki kişi kimlikleri: önce çakışmayan bir aralığa taşı, sonra yeni kimliğe yaz. */
		$off     = 1000000000;
		$changed = array();
		foreach ( $map as $from => $to ) {
			if ( $from !== $to ) {
				$changed[ $from ] = $to;
			}
		}
		foreach ( $changed ? array( 'events', 'contact_tags' ) : array() as $name ) {
			$t = $wpdb->prefix . 'flxcrm_' . $name;
			if ( ! self::table_exists( $t ) ) {
				continue;
			}
			$wpdb->query( $wpdb->prepare( "UPDATE $t SET contact_id = contact_id + %d WHERE contact_id > 0 AND contact_id < %d", $off, $off ) ); // phpcs:ignore WordPress.DB
			foreach ( $map as $from => $to ) {
				if ( 'contact_tags' === $name ) {
					// Aynı etiket birleşen kişide zaten varsa ikinci satır atlanır.
					$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE contact_id = %d AND tag_id IN (SELECT tag_id FROM (SELECT tag_id FROM $t WHERE contact_id = %d) x)", $from + $off, $to ) ); // phpcs:ignore WordPress.DB
				}
				$wpdb->query( $wpdb->prepare( "UPDATE $t SET contact_id = %d WHERE contact_id = %d", $to, $from + $off ) ); // phpcs:ignore WordPress.DB
			}
			// Kişisi kalmamış satır (0.2.x'te silinmiş kişi) atılır.
			$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE contact_id >= %d", $off ) ); // phpcs:ignore WordPress.DB
		}
		if ( self::table_exists( $old_l ) ) {
			foreach ( (array) $wpdb->get_results( "SELECT * FROM $old_l ORDER BY id", ARRAY_A ) as $l ) { // phpcs:ignore WordPress.DB
				$to = $map[ (int) $l['contact_id'] ] ?? (int) $l['contact_id'];
				if ( $to && ! flx_person_by_link( $l['source'], $l['source_id'] ) ) {
					$wpdb->insert( $new_l, array( 'contact_id' => $to, 'source' => $l['source'], 'source_id' => $l['source_id'], 'created_at' => $l['created_at'] ) ); // phpcs:ignore WordPress.DB
				}
			}
			$wpdb->query( "DROP TABLE $old_l" ); // phpcs:ignore WordPress.DB
		}
		if ( self::table_exists( $old ) ) {
			$wpdb->query( "DROP TABLE $old" ); // phpcs:ignore WordPress.DB
		}
		delete_transient( 'flxcrm_stats' );
		update_option( 'flxcrm_people_merged', count( $map ), false );
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();

		// Kişiler ve bağlantılar ortak tabloda. Ortak modül bu istekte yoksa (etkinleştirme anında eski kopya yüklü)
		// kurulum sonraki istekte maybe_upgrade ile tamamlanır.
		if ( ! self::people_ready() ) {
			self::caps();
			return;
		}
		flx_people_ready();
		self::migrate_people(); // Ortak tablo bu kurulumdan önce açıldıysa (kanca çalışmadıysa) 0.2.x kişileri şimdi taşınır.

		dbDelta(
			'CREATE TABLE ' . flxcrm_table( 'events' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(32) NOT NULL DEFAULT '',
			source varchar(24) NOT NULL DEFAULT '',
			uniq varchar(120) NULL DEFAULT NULL,
			title varchar(255) NOT NULL DEFAULT '',
			amount decimal(18,2) NOT NULL DEFAULT 0,
			currency varchar(8) NOT NULL DEFAULT '',
			base_amount decimal(18,2) NULL DEFAULT NULL,
			paid tinyint(1) NOT NULL DEFAULT 0,
			url varchar(500) NOT NULL DEFAULT '',
			data longtext NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uniq (uniq),
			KEY contact_at (contact_id,at),
			KEY type_at (type,at),
			KEY paid (paid)
			) $c;"
		);
		dbDelta(
			'CREATE TABLE ' . flxcrm_table( 'tags' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL DEFAULT '',
			slug varchar(100) NOT NULL DEFAULT '',
			color varchar(9) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
			) $c;"
		);
		dbDelta(
			'CREATE TABLE ' . flxcrm_table( 'contact_tags' ) . " (
			contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
			tag_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (contact_id,tag_id),
			KEY tag_id (tag_id)
			) $c;"
		);
		dbDelta(
			'CREATE TABLE ' . flxcrm_table( 'segments' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			description varchar(255) NOT NULL DEFAULT '',
			rules longtext NULL,
			count int(10) unsigned NOT NULL DEFAULT 0,
			counted_at datetime NULL DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id)
			) $c;"
		);

		dbDelta(
			'CREATE TABLE ' . flxcrm_table( 'journeys' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(190) NOT NULL DEFAULT '',
			status varchar(10) NOT NULL DEFAULT 'draft',
			trigger_def text NULL,
			steps longtext NULL,
			opts text NULL,
			stats text NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
			) $c;"
		);
		dbDelta(
			'CREATE TABLE ' . flxcrm_table( 'journey_runs' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			journey_id bigint(20) unsigned NOT NULL DEFAULT 0,
			contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
			step smallint(5) unsigned NOT NULL DEFAULT 0,
			status varchar(10) NOT NULL DEFAULT 'active',
			reason varchar(20) NOT NULL DEFAULT '',
			next_at datetime NOT NULL,
			started_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status_next (status,next_at),
			KEY journey_contact (journey_id,contact_id),
			KEY contact_id (contact_id)
			) $c;"
		);

		self::caps();
		update_option( 'flxcrm_db_version', FLXCRM_DB_VERSION, false );
		if ( ! get_option( 'flxcrm_installed_at' ) ) {
			update_option( 'flxcrm_installed_at', time(), false );
		}
		if ( ! wp_next_scheduled( 'flxcrm_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'flxcrm_daily' );
		}
	}

	/** Yönetici rolüne CRM yetkileri. Diğer roller Flux Roles'tan açılır. */
	public static function caps() {
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( FLXCRM_CAP_VIEW );
			$role->add_cap( FLXCRM_CAP_MANAGE );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'flxcrm_daily' );
		wp_clear_scheduled_hook( 'flxcrm_journeys_tick' );
	}
}
