<?php
defined( 'ABSPATH' ) || exit;

function flxs_get_person( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxs_table( 'people' ) . ' WHERE id = %d', $id ), ARRAY_A );
}

function flxs_find_person_by_phone( $phone ) {
	global $wpdb;
	$phone = flxs_normalize_phone( $phone );
	if ( ! $phone ) {
		return null;
	}
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxs_table( 'people' ) . ' WHERE phone = %s ORDER BY id LIMIT 1', $phone ), ARRAY_A );
}

function flxs_find_person_by_email( $email ) {
	global $wpdb;
	$email = strtolower( sanitize_email( $email ) );
	if ( ! $email ) {
		return null;
	}
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxs_table( 'people' ) . ' WHERE email = %s ORDER BY id LIMIT 1', $email ), ARRAY_A );
}

/**
 * Kişiyi telefon (öncelikli) ya da e-postaya göre bulur, yoksa oluşturur.
 * Bulunan kişinin eksik alanlarını tamamlar; dolu alanların üzerine yazmaz (bilgi olarak döner).
 *
 * @return array|WP_Error [ 'person' => array, 'created' => bool, 'notes' => string[] ]
 */
function flxs_upsert_person( $name, $phone_raw, $email_raw, $person_id = 0 ) {
	global $wpdb;
	$name  = trim( sanitize_text_field( $name ) );
	$phone = flxs_normalize_phone( $phone_raw );
	$email = strtolower( sanitize_email( $email_raw ) );
	$notes = array();

	if ( '' !== trim( (string) $phone_raw ) && ! $phone ) {
		return new WP_Error( 'flxs_phone', 'Telefon numarası geçersiz. Örnek: 0532 123 45 67' );
	}
	if ( '' !== trim( (string) $email_raw ) && ! is_email( $email ) ) {
		return new WP_Error( 'flxs_email', 'E-posta adresi geçersiz.' );
	}
	if ( '' === $name ) {
		return new WP_Error( 'flxs_name', 'Ad soyad gerekli.' );
	}

	$person = $person_id ? flxs_get_person( $person_id ) : null;
	if ( ! $person && $phone ) {
		$person = flxs_find_person_by_phone( $phone );
	}
	if ( ! $person && $email ) {
		$person = flxs_find_person_by_email( $email );
	}

	if ( $person ) {
		$update = array();
		if ( $phone && $phone !== $person['phone'] ) {
			$other = flxs_find_person_by_phone( $phone );
			if ( $other && (int) $other['id'] !== (int) $person['id'] ) {
				$notes[] = 'Bu telefon başka bir kişide kayıtlı (#' . $other['id'] . '), kişiye eklenmedi.';
			} else {
				$update['phone'] = $phone;
			}
		}
		if ( $email && $email !== $person['email'] ) {
			$other = flxs_find_person_by_email( $email );
			if ( $other && (int) $other['id'] !== (int) $person['id'] ) {
				$notes[] = 'Bu e-posta başka bir kişide kayıtlı (#' . $other['id'] . '), kişiye eklenmedi.';
			} else {
				$update['email'] = $email;
			}
		}
		// Aynı telefon/e-posta ile farklı isimde sertifika (ör. aile üyeleri): kişi kaydının adı yalnızca
		// kişi açıkça seçildiyse güncellenir; sertifika kendi ismini values.ad_soyad'da tutar.
		if ( $person_id && $name !== $person['name'] ) {
			$update['name'] = $name;
		}
		if ( $update ) {
			$update['updated_at'] = flxs_now();
			$wpdb->update( flxs_table( 'people' ), $update, array( 'id' => $person['id'] ) );
			do_action( 'flx_person_updated', (int) $person['id'], $update, $person );
			flxs_log( 'person_updated', 'person', $person['id'], $name, array( 'before' => array_intersect_key( $person, $update ), 'after' => $update ) );
			$person = flxs_get_person( $person['id'] );
		}
		flxs_person_mark( (int) $person['id'] );
		return array( 'person' => $person, 'created' => false, 'notes' => $notes );
	}

	$id = 0;
	if ( function_exists( 'flx_person_ensure' ) ) {
		// Shared create: with Flux CRM active its matching, roles and scoring run here too.
		$id = (int) flx_person_ensure( array( 'name' => $name, 'phone' => $phone, 'email' => $email, 'source' => 'certificates', 'allow_empty' => true ) );
	}
	if ( ! $id ) {
		$wpdb->insert(
			flxs_table( 'people' ),
			array(
				'name'       => $name,
				'phone'      => $phone ? $phone : '',
				'email'      => $email ? $email : '',
				'source'     => 'certificates',
				'created_at' => flxs_now(),
				'updated_at' => flxs_now(),
			)
		);
		$id = (int) $wpdb->insert_id;
	}
	if ( ! $id ) {
		return new WP_Error( 'flxs_person_db', __( 'The person could not be saved.', 'flx-certificates' ) );
	}
	$wpdb->update( flxs_table( 'people' ), array( 'created_by' => get_current_user_id() ), array( 'id' => $id ) );
	flxs_person_mark( $id );
	flxs_log( 'person_created', 'person', $id, $name, array( 'phone' => $phone, 'email' => $email ) );
	return array( 'person' => flxs_get_person( $id ), 'created' => true, 'notes' => $notes );
}

function flxs_search_people( $term, $limit = 10, $scope = false ) {
	global $wpdb;
	$t     = flxs_table( 'people' );
	$term  = trim( (string) $term );
	$sc    = $scope ? flxs_people_scope_sql( '' ) : '';
	if ( '' === $term ) {
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t" . ( $sc ? " WHERE $sc" : '' ) . ' ORDER BY id DESC LIMIT %d', $limit ), ARRAY_A ); // phpcs:ignore
	}
	$like  = '%' . $wpdb->esc_like( $term ) . '%';
	$digit = preg_replace( '/\D+/', '', $term );
	$phone = strlen( $digit ) >= 3 ? '%' . $wpdb->esc_like( ltrim( $digit, '0' ) ) . '%' : '__yok__';
	return $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM $t WHERE (name LIKE %s OR email LIKE %s OR phone LIKE %s)" . ( $sc ? " AND $sc" : '' ) . ' ORDER BY name LIMIT %d', $like, $like, $phone, $limit ), // phpcs:ignore
		ARRAY_A
	);
}

function flxs_person_public( $p ) {
	if ( ! $p ) {
		return null;
	}
	return array(
		'id'          => (int) $p['id'],
		'name'        => $p['name'],
		'phone'       => $p['phone'] ? $p['phone'] : '',
		'phone_label' => flxs_format_phone( $p['phone'] ),
		'email'       => $p['email'] ? $p['email'] : '',
	);
}

function flxs_delete_person( $id ) {
	global $wpdb;
	$certs = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxs_table( 'certificates' ) . ' WHERE person_id = %d', $id ) );
	if ( $certs ) {
		return new WP_Error( 'flxs_has_certs', 'Bu kişinin sertifikaları var; önce sertifikaları silin.' );
	}
	// Shared person: other packages may use the same row. Then only the Certificates tie is removed.
	$others = array();
	if ( function_exists( 'flx_person_links' ) ) {
		foreach ( flx_person_links( $id ) as $l ) {
			if ( 'certificates' !== $l['source'] ) {
				$others[] = $l['source'];
			}
		}
	}
	$p = flxs_get_person( $id );
	if ( $others || ( $p && (int) $p['user_id'] ) ) {
		$wpdb->delete( $wpdb->prefix . 'flx_people_links', array( 'contact_id' => (int) $id, 'source' => 'certificates' ) );
		flxs_log( 'person_unlinked', 'person', $id, $p ? $p['name'] : '' );
		return true;
	}
	do_action( 'flx_person_deleting', (int) $id );
	$wpdb->delete( $wpdb->prefix . 'flx_people_links', array( 'contact_id' => (int) $id ) );
	$wpdb->delete( flxs_table( 'people' ), array( 'id' => $id ) );
	flxs_log( 'person_deleted', 'person', $id, $p ? $p['name'] : '' );
	return true;
}
