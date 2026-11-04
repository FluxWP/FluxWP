<?php
/**
 * Etiketler.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

final class FLXCRM_Tags {

	const COLORS = array( '#0284c7', '#16a34a', '#ea580c', '#9333ea', '#db2777', '#0d9488', '#ca8a04', '#64748b' );

	/** @return array id => [id, name, slug, color, count] */
	public static function all( $with_counts = false ) {
		global $wpdb;
		$t = flxcrm_table( 'tags' );
		if ( $with_counts ) {
			$ct   = flxcrm_table( 'contact_tags' );
			$rows = $wpdb->get_results( "SELECT t.*, COUNT(ct.contact_id) AS count FROM $t t LEFT JOIN $ct ct ON ct.tag_id = t.id GROUP BY t.id, t.name, t.slug, t.color ORDER BY t.name", ARRAY_A ); // phpcs:ignore WordPress.DB
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM $t ORDER BY name", ARRAY_A ); // phpcs:ignore WordPress.DB
		}
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r['id'] ] = $r;
		}
		return $out;
	}

	/** Ada göre bul ya da oluştur. */
	public static function ensure( $name ) {
		global $wpdb;
		$name = mb_substr( trim( sanitize_text_field( (string) $name ) ), 0, 100 );
		if ( '' === $name ) {
			return 0;
		}
		$slug = sanitize_title( $name );
		if ( '' === $slug ) {
			$slug = 't' . substr( md5( $name ), 0, 10 );
		}
		$t  = flxcrm_table( 'tags' );
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE slug = %s", $slug ) ); // phpcs:ignore WordPress.DB
		if ( $id ) {
			return $id;
		}
		$n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" ); // phpcs:ignore WordPress.DB
		$wpdb->insert( $t, array( 'name' => $name, 'slug' => $slug, 'color' => self::COLORS[ $n % count( self::COLORS ) ] ) ); // phpcs:ignore WordPress.DB
		return (int) $wpdb->insert_id;
	}

	public static function of( $contact_id ) {
		global $wpdb;
		$t  = flxcrm_table( 'tags' );
		$ct = flxcrm_table( 'contact_tags' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT t.* FROM $t t INNER JOIN $ct ct ON ct.tag_id = t.id WHERE ct.contact_id = %d ORDER BY t.name", (int) $contact_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	/** Kişi listesindeki etiketleri tek sorguda getirir: contact_id => [tag…]. */
	public static function of_many( array $ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$t    = flxcrm_table( 'tags' );
		$ct   = flxcrm_table( 'contact_tags' );
		$rows = $wpdb->get_results( "SELECT ct.contact_id, t.id, t.name, t.color FROM $ct ct INNER JOIN $t t ON t.id = ct.tag_id WHERE ct.contact_id IN (" . implode( ',', $ids ) . ') ORDER BY t.name', ARRAY_A ); // phpcs:ignore WordPress.DB
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r['contact_id'] ][] = $r;
		}
		return $out;
	}

	public static function attach( $contact_id, $tag ) {
		global $wpdb;
		$tag_id = is_numeric( $tag ) ? (int) $tag : self::ensure( $tag );
		if ( ! $tag_id || ! $contact_id ) {
			return 0;
		}
		$ct = flxcrm_table( 'contact_tags' );
		if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM $ct WHERE contact_id = %d AND tag_id = %d", (int) $contact_id, $tag_id ) ) ) { // phpcs:ignore WordPress.DB
			$wpdb->insert( $ct, array( 'contact_id' => (int) $contact_id, 'tag_id' => $tag_id ) ); // phpcs:ignore WordPress.DB
			do_action( 'flxcrm_tag_added', (int) $contact_id, $tag_id );
		}
		return $tag_id;
	}

	public static function detach( $contact_id, $tag_id ) {
		global $wpdb;
		$wpdb->delete( flxcrm_table( 'contact_tags' ), array( 'contact_id' => (int) $contact_id, 'tag_id' => (int) $tag_id ) ); // phpcs:ignore WordPress.DB
		do_action( 'flxcrm_tag_removed', (int) $contact_id, (int) $tag_id );
	}

	/**
	 * Ad ve/veya renk değiştirir.
	 *
	 * @return true|WP_Error
	 */
	public static function update( $tag_id, array $d ) {
		global $wpdb;
		$t   = flxcrm_table( 'tags' );
		$all = self::all();
		if ( ! isset( $all[ (int) $tag_id ] ) ) {
			return new WP_Error( 'flxcrm_tag', __( 'Etiket bulunamadı.', 'flx-crm' ) );
		}
		$upd = array();
		if ( isset( $d['name'] ) ) {
			$name = mb_substr( trim( sanitize_text_field( (string) $d['name'] ) ), 0, 100 );
			if ( '' === $name ) {
				return new WP_Error( 'flxcrm_tag', __( 'Etiket adı boş olamaz.', 'flx-crm' ) );
			}
			$slug = sanitize_title( $name );
			$slug = '' !== $slug ? $slug : 't' . substr( md5( $name ), 0, 10 );
			$dup  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE slug = %s AND id <> %d", $slug, (int) $tag_id ) ); // phpcs:ignore WordPress.DB
			if ( $dup ) {
				return new WP_Error( 'flxcrm_tag', __( 'Bu adla başka bir etiket var.', 'flx-crm' ) );
			}
			$upd['name'] = $name;
			$upd['slug'] = $slug;
		}
		if ( isset( $d['color'] ) ) {
			$color = sanitize_hex_color( (string) $d['color'] );
			if ( ! $color ) {
				return new WP_Error( 'flxcrm_tag', __( 'Renk geçersiz (#16a34a gibi).', 'flx-crm' ) );
			}
			$upd['color'] = $color;
		}
		if ( $upd ) {
			$wpdb->update( $t, $upd, array( 'id' => (int) $tag_id ) ); // phpcs:ignore WordPress.DB
		}
		return true;
	}

	public static function delete( $tag_id ) {
		global $wpdb;
		$wpdb->delete( flxcrm_table( 'contact_tags' ), array( 'tag_id' => (int) $tag_id ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( flxcrm_table( 'tags' ), array( 'id' => (int) $tag_id ) ); // phpcs:ignore WordPress.DB
	}
}
