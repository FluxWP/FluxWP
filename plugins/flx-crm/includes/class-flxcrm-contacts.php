<?php
/**
 * Kişi kartları: bul / oluştur (upsert), güncelle, rol, sorgu, sayaçlar.
 *
 * Eşleştirme sırası: kaynak bağlantısı → WordPress kullanıcısı → e-posta → telefon.
 * Kaynaktan gelen veri yalnız boş alanları doldurur; elle girilen bilgi ezilmez. İzinler kaynaktan yalnız açılır
 * (kapatma kişinin kendi isteğiyle, elle ya da `flxcrm_set_consent` ile).
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Contacts {

	const CONSENTS = array( 'email', 'sms', 'whatsapp', 'ads' );

	/** @var array id => satır (istek içi önbellek) */
	private static $cache = array();

	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( ! $id ) {
			return null;
		}
		if ( ! isset( self::$cache[ $id ] ) ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxcrm_table( 'contacts' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
			if ( ! $row ) {
				return null;
			}
			self::$cache[ $id ] = $row;
		}
		return self::$cache[ $id ];
	}

	public static function forget( $id ) {
		unset( self::$cache[ (int) $id ] );
	}

	public static function by_link( $source, $source_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT contact_id FROM ' . flxcrm_table( 'links' ) . ' WHERE source = %s AND source_id = %s', $source, (string) $source_id ) ); // phpcs:ignore WordPress.DB
	}

	public static function links( $contact_id ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT source, source_id, created_at FROM ' . flxcrm_table( 'links' ) . ' WHERE contact_id = %d ORDER BY id', (int) $contact_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	public static function link( $contact_id, $source, $source_id ) {
		global $wpdb;
		$source_id = (string) $source_id;
		if ( ! $contact_id || '' === $source || '' === $source_id ) {
			return;
		}
		$t   = flxcrm_table( 'links' );
		$cur = (int) $wpdb->get_var( $wpdb->prepare( "SELECT contact_id FROM $t WHERE source = %s AND source_id = %s", $source, $source_id ) ); // phpcs:ignore WordPress.DB
		if ( $cur === (int) $contact_id ) {
			return;
		}
		if ( $cur ) {
			$wpdb->update( $t, array( 'contact_id' => (int) $contact_id ), array( 'source' => $source, 'source_id' => $source_id ) ); // phpcs:ignore WordPress.DB
			return;
		}
		$wpdb->insert( $t, array( 'contact_id' => (int) $contact_id, 'source' => $source, 'source_id' => $source_id, 'created_at' => flxcrm_now() ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Eşleşen kişiyi bulur.
	 *
	 * @return int 0 = yok
	 */
	public static function find( array $d ) {
		global $wpdb;
		$t = flxcrm_table( 'contacts' );
		if ( ! empty( $d['link'] ) && is_array( $d['link'] ) ) {
			$id = self::by_link( $d['link'][0], $d['link'][1] );
			if ( $id && self::get( $id ) ) {
				return $id;
			}
		}
		if ( ! empty( $d['user_id'] ) ) {
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE user_id = %d ORDER BY id LIMIT 1", (int) $d['user_id'] ) ); // phpcs:ignore WordPress.DB
			if ( $id ) {
				return $id;
			}
		}
		$email = flxcrm_email( $d['email'] ?? '' );
		if ( '' !== $email ) {
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE email = %s ORDER BY id LIMIT 1", $email ) ); // phpcs:ignore WordPress.DB
			if ( $id ) {
				return $id;
			}
		}
		$phone = flxcrm_phone( $d['phone'] ?? '' );
		if ( '' !== $phone ) {
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE phone = %s ORDER BY id LIMIT 1", $phone ) ); // phpcs:ignore WordPress.DB
			if ( $id ) {
				return $id;
			}
		}
		return 0;
	}

	/**
	 * Bul ya da oluştur; boş alanları doldur, rolleri ekle, izinleri aç, kaynak bağlantısını yaz.
	 *
	 * @param array $d name, email, phone, user_id, roles[], source, link [source, id], city, company, country,
	 *                 utm_source, utm_campaign, consent_{email,sms,whatsapp,ads}, at (ilk temas, GMT), event (bool, "Kişi eklendi" olayı).
	 * @return int kişi kimliği (0 = e-posta/telefon/kullanıcı yok, kişi açılamadı)
	 */
	public static function upsert( array $d ) {
		global $wpdb;
		$t     = flxcrm_table( 'contacts' );
		$email = flxcrm_email( $d['email'] ?? '' );
		$phone = flxcrm_phone( $d['phone'] ?? '' );
		$uid   = (int) ( $d['user_id'] ?? 0 );
		$name  = trim( wp_strip_all_tags( (string) ( $d['name'] ?? '' ) ) );
		$id    = self::find( array_merge( $d, array( 'email' => $email, 'phone' => $phone ) ) );

		if ( ! $id ) {
			if ( '' === $email && '' === $phone && ! $uid && empty( $d['allow_empty'] ) ) {
				return 0;
			}
			$now = flxcrm_now();
			$at  = ! empty( $d['at'] ) ? (string) $d['at'] : $now;
			$row = array(
				'name'         => mb_substr( $name, 0, 190 ),
				'email'        => $email,
				'phone'        => $phone,
				'user_id'      => $uid,
				'roles'        => flxcrm_pack_roles( (array) ( $d['roles'] ?? array() ) ),
				'status'       => 'active',
				'owner_id'     => (int) ( $d['owner_id'] ?? 0 ),
				'company'      => mb_substr( sanitize_text_field( (string) ( $d['company'] ?? '' ) ), 0, 190 ),
				'city'         => mb_substr( sanitize_text_field( (string) ( $d['city'] ?? '' ) ), 0, 100 ),
				'country'      => strtoupper( substr( sanitize_key( (string) ( $d['country'] ?? '' ) ), 0, 2 ) ),
				'source'       => sanitize_key( (string) ( $d['source'] ?? 'manual' ) ),
				'utm_source'   => mb_substr( sanitize_text_field( (string) ( $d['utm_source'] ?? '' ) ), 0, 100 ),
				'utm_campaign' => mb_substr( sanitize_text_field( (string) ( $d['utm_campaign'] ?? '' ) ), 0, 100 ),
				'note'         => isset( $d['note'] ) ? sanitize_textarea_field( (string) $d['note'] ) : null,
				'created_at'   => $at < $now ? $at : $now,
				'updated_at'   => $now,
			);
			foreach ( self::CONSENTS as $k ) {
				$row[ 'consent_' . $k ] = empty( $d[ 'consent_' . $k ] ) ? 0 : 1;
			}
			if ( array_sum( array_intersect_key( $row, array_flip( array( 'consent_email', 'consent_sms', 'consent_whatsapp', 'consent_ads' ) ) ) ) ) {
				$row['consent_at'] = $now;
			}
			if ( ! $wpdb->insert( $t, $row ) ) { // phpcs:ignore WordPress.DB
				return 0;
			}
			$id = (int) $wpdb->insert_id;
			if ( ! empty( $d['link'] ) && is_array( $d['link'] ) ) {
				self::link( $id, $d['link'][0], $d['link'][1] );
			}
			if ( ! isset( $d['event'] ) || $d['event'] ) {
				FLXCRM_Events::add(
					$id,
					'created',
					array(
						'source' => $row['source'],
						'title'  => sprintf( /* translators: %s: source name */ __( 'Source: %s', 'flx-crm' ), flxcrm_source_label( $row['source'] ) ),
						'at'     => $row['created_at'],
						'uniq'   => 'created:' . $id,
					)
				);
			}
			do_action( 'flxcrm_contact_created', $id, $row );
			foreach ( flxcrm_unpack_roles( $row['roles'] ) as $r ) {
				do_action( 'flxcrm_role_added', $id, $r );
			}
			return $id;
		}

		/* Var olan kişi: yalnız boşları doldur. */
		$cur = self::get( $id );
		$upd = array();
		if ( '' === trim( (string) $cur['name'] ) && '' !== $name ) {
			$upd['name'] = mb_substr( $name, 0, 190 );
		}
		if ( '' === $cur['email'] && '' !== $email ) {
			$upd['email'] = $email;
		}
		if ( '' === $cur['phone'] && '' !== $phone ) {
			$upd['phone'] = $phone;
		}
		if ( ! (int) $cur['user_id'] && $uid ) {
			$upd['user_id'] = $uid;
		}
		foreach ( array( 'company' => 190, 'city' => 100, 'utm_source' => 100, 'utm_campaign' => 100 ) as $k => $len ) {
			if ( '' === (string) $cur[ $k ] && ! empty( $d[ $k ] ) ) {
				$upd[ $k ] = mb_substr( sanitize_text_field( (string) $d[ $k ] ), 0, $len );
			}
		}
		foreach ( self::CONSENTS as $k ) {
			if ( ! empty( $d[ 'consent_' . $k ] ) && ! (int) $cur[ 'consent_' . $k ] ) {
				$upd[ 'consent_' . $k ] = 1;
				$upd['consent_at']      = flxcrm_now();
			}
		}
		if ( ! empty( $d['at'] ) && (string) $d['at'] < (string) $cur['created_at'] ) {
			$upd['created_at'] = (string) $d['at'];
		}
		if ( $upd ) {
			$upd['updated_at'] = flxcrm_now();
			$wpdb->update( $t, $upd, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
			self::forget( $id );
		}
		if ( ! empty( $d['roles'] ) ) {
			self::add_roles( $id, (array) $d['roles'] );
		}
		if ( ! empty( $d['link'] ) && is_array( $d['link'] ) ) {
			self::link( $id, $d['link'][0], $d['link'][1] );
		}
		return $id;
	}

	/** Rolleri ekler; yeni eklenen her rol için olay ve `flxcrm_role_added` eylemi. */
	public static function add_roles( $id, array $roles, $silent = false ) {
		global $wpdb;
		$c = self::get( $id );
		if ( ! $c ) {
			return;
		}
		$have  = flxcrm_unpack_roles( $c['roles'] );
		$known = flxcrm_roles();
		$new   = array();
		foreach ( $roles as $r ) {
			$r = sanitize_key( $r );
			if ( '' !== $r && isset( $known[ $r ] ) && ! in_array( $r, $have, true ) ) {
				$new[] = $r;
			}
		}
		if ( ! $new ) {
			return;
		}
		/* "Aday" başka rol gelince düşer. */
		$all = array_merge( $have, $new );
		if ( count( $all ) > 1 ) {
			$all = array_values( array_diff( $all, array( 'lead' ) ) );
		}
		$new = array_values( array_intersect( $new, $all ) );
		if ( ! $new && count( $all ) === count( $have ) ) {
			return;
		}
		$wpdb->update( flxcrm_table( 'contacts' ), array( 'roles' => flxcrm_pack_roles( $all ), 'updated_at' => flxcrm_now() ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
		self::forget( $id );
		foreach ( $new as $r ) {
			if ( ! $silent ) {
				FLXCRM_Events::add( $id, 'role_added', array( 'title' => flxcrm_role_label( $r ), 'data' => array( 'role' => $r ) ) );
			}
			do_action( 'flxcrm_role_added', (int) $id, $r );
		}
	}

	public static function set_roles( $id, array $roles ) {
		global $wpdb;
		$c = self::get( $id );
		if ( ! $c ) {
			return;
		}
		$known = flxcrm_roles();
		$roles = array_values( array_filter( array_map( 'sanitize_key', $roles ), static function ( $r ) use ( $known ) {
			return isset( $known[ $r ] );
		} ) );
		$before = flxcrm_unpack_roles( $c['roles'] );
		$wpdb->update( flxcrm_table( 'contacts' ), array( 'roles' => flxcrm_pack_roles( $roles ), 'updated_at' => flxcrm_now() ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
		self::forget( $id );
		foreach ( array_diff( $roles, $before ) as $r ) {
			FLXCRM_Events::add( $id, 'role_added', array( 'title' => flxcrm_role_label( $r ), 'data' => array( 'role' => $r ), 'user_id' => get_current_user_id() ) );
			do_action( 'flxcrm_role_added', (int) $id, $r );
		}
	}

	/**
	 * Elle güncelleme (yönetim ekranı, REST). Boş değer alanı temizler.
	 *
	 * @return true|WP_Error
	 */
	public static function update( $id, array $d ) {
		global $wpdb;
		$c = self::get( $id );
		if ( ! $c ) {
			return new WP_Error( 'flxcrm_missing', __( 'Contact not found.', 'flx-crm' ) );
		}
		$upd = array();
		if ( array_key_exists( 'name', $d ) ) {
			$upd['name'] = mb_substr( sanitize_text_field( (string) $d['name'] ), 0, 190 );
		}
		if ( array_key_exists( 'email', $d ) ) {
			$raw = trim( (string) $d['email'] );
			$e   = flxcrm_email( $raw );
			if ( '' !== $raw && '' === $e ) {
				return new WP_Error( 'flxcrm_email', __( 'The e-mail address is not valid.', 'flx-crm' ) );
			}
			$upd['email'] = $e;
		}
		if ( array_key_exists( 'phone', $d ) ) {
			$raw = trim( (string) $d['phone'] );
			$p   = flxcrm_phone( $raw );
			if ( '' !== $raw && '' === $p ) {
				return new WP_Error( 'flxcrm_phone', __( 'The phone number is not valid.', 'flx-crm' ) );
			}
			$upd['phone'] = $p;
		}
		foreach ( array( 'company' => 190, 'city' => 100 ) as $k => $len ) {
			if ( array_key_exists( $k, $d ) ) {
				$upd[ $k ] = mb_substr( sanitize_text_field( (string) $d[ $k ] ), 0, $len );
			}
		}
		if ( array_key_exists( 'country', $d ) ) {
			$upd['country'] = strtoupper( substr( preg_replace( '/[^a-z]/i', '', (string) $d['country'] ), 0, 2 ) );
		}
		if ( array_key_exists( 'note', $d ) ) {
			$upd['note'] = sanitize_textarea_field( (string) $d['note'] );
		}
		if ( array_key_exists( 'owner_id', $d ) ) {
			$upd['owner_id'] = absint( $d['owner_id'] );
		}
		if ( array_key_exists( 'status', $d ) && in_array( $d['status'], array( 'active', 'archived' ), true ) ) {
			$upd['status'] = $d['status'];
		}
		$consent_changed = array();
		foreach ( self::CONSENTS as $k ) {
			if ( array_key_exists( 'consent_' . $k, $d ) ) {
				$v = empty( $d[ 'consent_' . $k ] ) ? 0 : 1;
				if ( $v !== (int) $c[ 'consent_' . $k ] ) {
					$upd[ 'consent_' . $k ] = $v;
					$consent_changed[ $k ] = $v;
				}
			}
		}
		if ( $consent_changed ) {
			$upd['consent_at'] = flxcrm_now();
		}
		if ( $upd ) {
			$upd['updated_at'] = flxcrm_now();
			$wpdb->update( flxcrm_table( 'contacts' ), $upd, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB
			self::forget( $id );
		}
		if ( $consent_changed ) {
			$labels = self::consent_labels();
			$parts  = array();
			foreach ( $consent_changed as $k => $v ) {
				$parts[] = $labels[ $k ] . ': ' . ( $v ? __( 'on', 'flx-crm' ) : __( 'off', 'flx-crm' ) );
			}
			FLXCRM_Events::add( $id, 'consent', array( 'title' => implode( ', ', $parts ), 'data' => $consent_changed, 'user_id' => get_current_user_id() ) );
			do_action( 'flxcrm_consent_changed', (int) $id, $consent_changed );
		}
		if ( array_key_exists( 'roles', $d ) && is_array( $d['roles'] ) ) {
			self::set_roles( $id, $d['roles'] );
		}
		do_action( 'flxcrm_contact_updated', (int) $id, $upd );
		return true;
	}

	public static function consent_labels() {
		return array(
			'email'    => __( 'E-mail', 'flx-crm' ),
			'sms'      => __( 'SMS', 'flx-crm' ),
			'whatsapp' => __( 'WhatsApp', 'flx-crm' ),
			'ads'      => __( 'Ad audiences', 'flx-crm' ),
		);
	}

	/** Kişiyi, olaylarını, etiket ve bağlantılarını siler. Kaynak kayıtlar (bağış, sipariş) kalır. */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( ! self::get( $id ) ) {
			return false;
		}
		do_action( 'flxcrm_contact_deleting', $id );
		foreach ( array( 'events', 'links', 'contact_tags' ) as $tb ) {
			$wpdb->delete( flxcrm_table( $tb ), array( 'contact_id' => $id ) ); // phpcs:ignore WordPress.DB
		}
		$wpdb->delete( flxcrm_table( 'contacts' ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		self::forget( $id );
		return true;
	}

	/**
	 * Mükerrer kişileri birincil karta birleştirir: olaylar, kaynak bağlantıları ve etiketler taşınır, boş alanlar
	 * doldurulur, roller birleşir, izin herhangi bir kartta açıksa açık kalır, sonra mükerrerler silinir.
	 *
	 * @return int|WP_Error birleştirilen kart sayısı
	 */
	public static function merge( $primary_id, array $dup_ids ) {
		global $wpdb;
		$primary_id = (int) $primary_id;
		$p          = self::get( $primary_id );
		if ( ! $p ) {
			return new WP_Error( 'flxcrm_missing', __( 'Contact not found.', 'flx-crm' ) );
		}
		$dup_ids = array_values( array_unique( array_diff( array_filter( array_map( 'intval', $dup_ids ) ), array( $primary_id ) ) ) );
		if ( ! $dup_ids ) {
			return new WP_Error( 'flxcrm_merge', __( 'Choose other contacts to merge.', 'flx-crm' ) );
		}
		if ( count( $dup_ids ) > 20 ) {
			return new WP_Error( 'flxcrm_merge', __( 'Up to 20 contacts can be merged at once.', 'flx-crm' ) );
		}
		$t     = flxcrm_table( 'contacts' );
		$roles = flxcrm_unpack_roles( $p['roles'] );
		$upd   = array();
		$names = array();
		foreach ( $dup_ids as $did ) {
			$d = self::get( $did );
			if ( ! $d ) {
				return new WP_Error( 'flxcrm_missing', __( 'Contact not found.', 'flx-crm' ) . ' #' . $did );
			}
			$names[] = flxcrm_display_name( $d );
			foreach ( array( 'name', 'email', 'phone', 'company', 'city', 'country', 'utm_source', 'utm_campaign' ) as $k ) {
				if ( '' === trim( (string) ( $upd[ $k ] ?? $p[ $k ] ) ) && '' !== trim( (string) $d[ $k ] ) ) {
					$upd[ $k ] = $d[ $k ];
				}
			}
			foreach ( array( 'user_id', 'owner_id' ) as $k ) {
				if ( ! (int) ( $upd[ $k ] ?? $p[ $k ] ) && (int) $d[ $k ] ) {
					$upd[ $k ] = (int) $d[ $k ];
				}
			}
			foreach ( self::CONSENTS as $k ) {
				if ( (int) $d[ 'consent_' . $k ] && ! (int) ( $upd[ 'consent_' . $k ] ?? $p[ 'consent_' . $k ] ) ) {
					$upd[ 'consent_' . $k ] = 1;
					$upd['consent_at']      = $d['consent_at'] ? $d['consent_at'] : flxcrm_now();
				}
			}
			if ( '' !== trim( (string) $d['note'] ) ) {
				$upd['note'] = trim( (string) ( $upd['note'] ?? $p['note'] ) . "\n" . $d['note'] );
			}
			if ( (string) $d['created_at'] < (string) ( $upd['created_at'] ?? $p['created_at'] ) ) {
				$upd['created_at'] = $d['created_at'];
				$upd['source']     = $d['source'];
			}
			$roles = array_merge( $roles, flxcrm_unpack_roles( $d['roles'] ) );
			foreach ( FLXCRM_Tags::of( $did ) as $tag ) {
				FLXCRM_Tags::attach( $primary_id, (int) $tag['id'] );
			}
			$wpdb->update( flxcrm_table( 'events' ), array( 'contact_id' => $primary_id ), array( 'contact_id' => $did ) ); // phpcs:ignore WordPress.DB
			$wpdb->update( flxcrm_table( 'links' ), array( 'contact_id' => $primary_id ), array( 'contact_id' => $did ) ); // phpcs:ignore WordPress.DB
			$wpdb->delete( flxcrm_table( 'contact_tags' ), array( 'contact_id' => $did ) ); // phpcs:ignore WordPress.DB
			$wpdb->delete( $t, array( 'id' => $did ) ); // phpcs:ignore WordPress.DB
			self::forget( $did );
			do_action( 'flxcrm_contact_merged', $primary_id, $did );
		}
		$roles = array_values( array_unique( $roles ) );
		if ( count( $roles ) > 1 ) {
			$roles = array_values( array_diff( $roles, array( 'lead' ) ) );
		}
		$upd['roles']      = flxcrm_pack_roles( $roles );
		$upd['updated_at'] = flxcrm_now();
		$wpdb->update( $t, $upd, array( 'id' => $primary_id ) ); // phpcs:ignore WordPress.DB
		self::forget( $primary_id );
		FLXCRM_Events::add(
			$primary_id,
			'merged',
			array(
				'source'  => 'manual',
				'title'   => implode( ', ', $names ),
				'data'    => array( 'ids' => $dup_ids ),
				'user_id' => get_current_user_id(),
			)
		);
		self::recalc( $primary_id );
		self::flush_stats();
		return count( $dup_ids );
	}

	/**
	 * Toplamları olaylardan yeniden hesaplar (değer, bağış/sipariş sayısı, ödeme günleri, son hareket).
	 * Ödeme günü site saat dilimine göre tekilleştirilir: aynı gün 3 bağış sıklığı şişirmez, tutarı değere yansır.
	 */
	public static function recalc( $id ) {
		global $wpdb;
		$id = (int) $id;
		$e  = flxcrm_table( 'events' );
		$s  = $wpdb->get_row( $wpdb->prepare( "SELECT SUM(CASE WHEN paid = 1 THEN COALESCE(base_amount,0) ELSE 0 END) AS ltv, SUM(CASE WHEN paid = 1 AND type = 'donation' THEN 1 ELSE 0 END) AS donations, SUM(CASE WHEN paid = 1 AND type = 'order' THEN 1 ELSE 0 END) AS orders, MIN(CASE WHEN paid = 1 THEN at END) AS first_paid, MAX(CASE WHEN paid = 1 THEN at END) AS last_paid, MAX(CASE WHEN type NOT IN ('segment_entered','role_added','consent','merged') THEN at END) AS last_act FROM $e WHERE contact_id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$paid_at = $wpdb->get_col( $wpdb->prepare( "SELECT at FROM $e WHERE contact_id = %d AND paid = 1 ORDER BY at", $id ) ); // phpcs:ignore WordPress.DB
		$days    = array();
		$tz      = wp_timezone();
		foreach ( $paid_at as $at ) {
			try {
				$d = new DateTime( $at, new DateTimeZone( 'UTC' ) );
				$d->setTimezone( $tz );
				$days[ $d->format( 'Y-m-d' ) ] = true;
			} catch ( Exception $ex ) {
				continue;
			}
		}
		$keys = array_keys( $days );
		$gap  = 0;
		if ( count( $keys ) > 1 ) {
			$span = ( strtotime( end( $keys ) ) - strtotime( $keys[0] ) ) / DAY_IN_SECONDS;
			$gap  = (int) round( $span / ( count( $keys ) - 1 ) );
		}
		$wpdb->update( // phpcs:ignore WordPress.DB
			flxcrm_table( 'contacts' ),
			array(
				'ltv'              => round( (float) ( $s['ltv'] ?? 0 ), 2 ),
				'donations'        => (int) ( $s['donations'] ?? 0 ),
				'orders'           => (int) ( $s['orders'] ?? 0 ),
				'paid_days'        => count( $keys ),
				'first_paid_at'    => $s['first_paid'] ? $s['first_paid'] : null,
				'last_paid_at'     => $s['last_paid'] ? $s['last_paid'] : null,
				'last_activity_at' => $s['last_act'] ? $s['last_act'] : null,
				'avg_gap_days'     => $gap,
			),
			array( 'id' => $id )
		);
		self::forget( $id );
	}

	/* ---------------------------------------------------------------- */
	/* Sorgu                                                             */
	/* ---------------------------------------------------------------- */

	/**
	 * Filtreden WHERE parçası.
	 *
	 * @param array $f s, role, segment (rfm), tag (id), status, consent, owner, risk (bool), rules (segment kuralları).
	 * @return array [ string $sql (" AND …"), array $params ]
	 */
	public static function where( array $f ) {
		global $wpdb;
		$sql    = '';
		$params = array();
		$status = $f['status'] ?? 'active';
		if ( 'any' !== $status ) {
			$sql     .= ' AND c.status = %s';
			$params[] = in_array( $status, array( 'active', 'archived' ), true ) ? $status : 'active';
		}
		if ( ! empty( $f['s'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $f['s'] ) . '%';
			$digits   = preg_replace( '/\D+/', '', (string) $f['s'] );
			$sql     .= ' AND (c.name LIKE %s OR c.email LIKE %s OR c.company LIKE %s OR c.city LIKE %s' . ( strlen( $digits ) >= 4 ? ' OR c.phone LIKE %s' : '' ) . ')';
			array_push( $params, $like, $like, $like, $like );
			if ( strlen( $digits ) >= 4 ) {
				$params[] = '%' . $wpdb->esc_like( ltrim( $digits, '0' ) ) . '%';
			}
		}
		if ( ! empty( $f['role'] ) ) {
			if ( 'none' === $f['role'] ) {
				$sql .= " AND c.roles = ''";
			} else {
				$sql     .= ' AND c.roles LIKE %s';
				$params[] = '%,' . $wpdb->esc_like( sanitize_key( $f['role'] ) ) . ',%';
			}
		}
		if ( isset( $f['segment'] ) && '' !== $f['segment'] ) {
			$sql     .= ' AND c.segment = %s';
			$params[] = 'none' === $f['segment'] ? '' : sanitize_key( $f['segment'] );
		}
		if ( ! empty( $f['tag'] ) ) {
			$sql     .= ' AND EXISTS (SELECT 1 FROM ' . flxcrm_table( 'contact_tags' ) . ' ct WHERE ct.contact_id = c.id AND ct.tag_id = %d)';
			$params[] = (int) $f['tag'];
		}
		if ( ! empty( $f['consent'] ) && in_array( $f['consent'], self::CONSENTS, true ) ) {
			$sql .= ' AND c.consent_' . $f['consent'] . ' = 1';
		}
		if ( ! empty( $f['owner'] ) ) {
			$sql     .= ' AND c.owner_id = %d';
			$params[] = (int) $f['owner'];
		}
		if ( ! empty( $f['risk'] ) ) {
			$sql     .= ' AND c.paid_days > 0 AND c.churn >= %d';
			$params[] = (int) flxcrm_setting( 'risk_threshold' );
		}
		if ( ! empty( $f['rules'] ) && is_array( $f['rules'] ) ) {
			list( $rs, $rp ) = FLXCRM_Segments::compile( $f['rules'] );
			$sql   .= $rs;
			$params = array_merge( $params, $rp );
		}
		return array( $sql, $params );
	}

	public static function sortable() {
		return array( 'name', 'ltv', 'last_activity_at', 'last_paid_at', 'created_at', 'churn' );
	}

	/**
	 * @return array rows, total, pages, paged
	 */
	public static function query( array $f ) {
		global $wpdb;
		$t        = flxcrm_table( 'contacts' );
		$per      = max( 1, min( 500, (int) ( $f['per_page'] ?? 25 ) ) );
		$paged    = max( 1, (int) ( $f['paged'] ?? 1 ) );
		$orderby  = in_array( $f['orderby'] ?? '', self::sortable(), true ) ? $f['orderby'] : 'last_activity_at';
		$order    = 'asc' === strtolower( (string) ( $f['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC';
		list( $w, $p ) = self::where( $f );
		$total    = (int) $wpdb->get_var( $p ? $wpdb->prepare( "SELECT COUNT(*) FROM $t c WHERE 1=1 $w", $p ) : "SELECT COUNT(*) FROM $t c WHERE 1=1 $w" ); // phpcs:ignore WordPress.DB
		$nulls    = 'DESC' === $order ? "c.$orderby IS NULL, " : '';
		$sql      = "SELECT c.* FROM $t c WHERE 1=1 $w ORDER BY {$nulls}c.$orderby $order, c.id DESC LIMIT %d OFFSET %d";
		$rows     = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $p, array( $per, ( $paged - 1 ) * $per ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return array(
			'rows'  => (array) $rows,
			'total' => $total,
			'pages' => (int) ceil( $total / $per ),
			'paged' => $paged,
		);
	}

	/** Filtreye uyan kişi kimlikleri (toplu işlem, CSV, Flux Mail). */
	public static function ids( array $f, $limit = 50000 ) {
		global $wpdb;
		$t = flxcrm_table( 'contacts' );
		list( $w, $p ) = self::where( $f );
		$p[] = (int) $limit;
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT c.id FROM $t c WHERE 1=1 $w ORDER BY c.id LIMIT %d", $p ) ) ); // phpcs:ignore WordPress.DB
	}

	public static function count( array $f ) {
		global $wpdb;
		$t = flxcrm_table( 'contacts' );
		list( $w, $p ) = self::where( $f );
		return (int) $wpdb->get_var( $p ? $wpdb->prepare( "SELECT COUNT(*) FROM $t c WHERE 1=1 $w", $p ) : "SELECT COUNT(*) FROM $t c WHERE 1=1 $w" ); // phpcs:ignore WordPress.DB
	}

	/** Genel bakış sayaçları (5 dk önbellek). */
	public static function stats( $fresh = false ) {
		$key = 'flxcrm_stats';
		$s   = $fresh ? false : get_transient( $key );
		if ( is_array( $s ) ) {
			return $s;
		}
		global $wpdb;
		$t      = flxcrm_table( 'contacts' );
		$active = gmdate( 'Y-m-d H:i:s', time() - (int) flxcrm_setting( 'active_days' ) * DAY_IN_SECONDS );
		$month  = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		$prev   = gmdate( 'Y-m-d H:i:s', time() - 60 * DAY_IN_SECONDS );
		$risk   = (int) flxcrm_setting( 'risk_threshold' );
		$row    = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, SUM(CASE WHEN created_at >= %s THEN 1 ELSE 0 END) AS new30, SUM(CASE WHEN created_at >= %s AND created_at < %s THEN 1 ELSE 0 END) AS new_prev, SUM(CASE WHEN last_activity_at >= %s THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN paid_days > 0 AND churn >= %d THEN 1 ELSE 0 END) AS risk, SUM(ltv) AS ltv, SUM(CASE WHEN paid_days > 0 THEN 1 ELSE 0 END) AS paying, SUM(CASE WHEN paid_days > 1 THEN 1 ELSE 0 END) AS repeat_ FROM $t WHERE status = 'active'", $month, $prev, $month, $active, $risk ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$s = array(
			'total'    => (int) $row['total'],
			'new30'    => (int) $row['new30'],
			'new_prev' => (int) $row['new_prev'],
			'active'   => (int) $row['active'],
			'risk'     => (int) $row['risk'],
			'ltv'      => (float) $row['ltv'],
			'paying'   => (int) $row['paying'],
			'repeat'   => (int) $row['repeat_'],
			'roles'    => array(),
			'segments' => array(),
			'sources'  => array(),
		);
		foreach ( array_keys( flxcrm_roles() ) as $r ) {
			$s['roles'][ $r ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = 'active' AND roles LIKE %s", '%,' . $wpdb->esc_like( $r ) . ',%' ) ); // phpcs:ignore WordPress.DB
		}
		foreach ( (array) $wpdb->get_results( "SELECT segment, COUNT(*) AS n, SUM(ltv) AS v FROM $t WHERE status = 'active' AND segment <> '' GROUP BY segment", ARRAY_A ) as $g ) { // phpcs:ignore WordPress.DB
			$s['segments'][ $g['segment'] ] = array( (int) $g['n'], (float) $g['v'] );
		}
		foreach ( (array) $wpdb->get_results( "SELECT source, COUNT(*) AS n FROM $t WHERE status = 'active' GROUP BY source ORDER BY n DESC", ARRAY_A ) as $g ) { // phpcs:ignore WordPress.DB
			$s['sources'][ $g['source'] ] = (int) $g['n'];
		}
		set_transient( $key, $s, 5 * MINUTE_IN_SECONDS );
		return $s;
	}

	public static function flush_stats() {
		delete_transient( 'flxcrm_stats' );
	}
}
