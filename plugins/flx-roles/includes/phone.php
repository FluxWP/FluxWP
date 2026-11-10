<?php
/**
 * Kullanıcı telefonu ve numaradan kişi/yetki bulma.
 *
 * WhatsApp asistanı (Flux Chat) yazan numarayı site kullanıcısıyla eşleştirip rolün yetkilerine göre araç açar.
 * Numara user meta flx_phone ve eşleştirme anahtarı flx_phone_key: ikisi de ülke kodlu tam rakam (ör. 905301234567). Son hanelerle eşleşmez.
 * Numarayı yalnız kullanıcı düzenleme yetkisi olan (promote_users) yazar; kişi kendi numarasını değiştiremez, çünkü numara kimliktir.
 */

defined( 'ABSPATH' ) || exit;

const FLXR_PHONE_META = 'flx_phone';
const FLXR_PHONE_KEY  = 'flx_phone_key';

/** Rakamlara indirger; Türkiye için 05xx / 5xx → 905xx. */
function flxr_normalize_phone( $raw ) {
	$raw = trim( (string) $raw );
	$d   = preg_replace( '/\D+/', '', $raw );
	if ( '' === $d ) {
		return '';
	}
	if ( 0 === strpos( $d, '00' ) ) {
		$d = substr( $d, 2 );
	} elseif ( 11 === strlen( $d ) && '0' === $d[0] ) {
		$d = '90' . substr( $d, 1 );
	} elseif ( 10 === strlen( $d ) && '5' === $d[0] ) {
		$d = '90' . $d;
	}
	return strlen( $d ) >= 10 && strlen( $d ) <= 15 ? $d : '';
}

/** Eşleştirme anahtarı: ülke kodlu tam numara. İki farklı tam numara eşit değildir. */
function flxr_phone_key( $raw ) {
	return flxr_normalize_phone( $raw );
}

function flxr_user_phone( $user_id ) {
	return (string) get_user_meta( (int) $user_id, FLXR_PHONE_META, true );
}

/** Numarayı kaydeder ('' siler). Aynı numara başka kullanıcıdaysa WP_Error. */
function flxr_set_user_phone( $user_id, $raw ) {
	$user_id = (int) $user_id;
	if ( '' === trim( (string) $raw ) ) {
		delete_user_meta( $user_id, FLXR_PHONE_META );
		delete_user_meta( $user_id, FLXR_PHONE_KEY );
		return true;
	}
	$d = flxr_normalize_phone( $raw );
	if ( '' === $d ) {
		return new WP_Error( 'flxr_phone', __( 'Invalid phone number. Write it with the country code or as 05xx.', 'flx-roles' ) );
	}
	$other = flxr_user_by_phone( $d );
	if ( $other && (int) $other->ID !== $user_id ) {
		return new WP_Error(
			'flxr_phone_taken',
			sprintf(
				/* translators: %s: user display name */
				__( 'This number belongs to another user: %s.', 'flx-roles' ),
				$other->display_name
			)
		);
	}
	update_user_meta( $user_id, FLXR_PHONE_META, $d );
	update_user_meta( $user_id, FLXR_PHONE_KEY, $d );
	return true;
}

/**
 * Numaradan kullanıcı (WP_User|null). Yalnız kayıtlı tam numara eşitse.
 * Filtre `flx_user_by_phone` başka kullanıcı döndürse de kayıtlı numarası bu tam numaraysa kabul edilir.
 */
function flxr_user_by_phone( $raw ) {
	$key  = flxr_phone_key( $raw );
	$user = null;
	if ( '' !== $key ) {
		$ids = get_users(
			array(
				'meta_key'   => FLXR_PHONE_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'     => 'ID',
				'number'     => 2,
			)
		);
		// Aynı tam numarada birden fazla kayıt varsa kimseye bağlama.
		if ( 1 === count( $ids ) ) {
			$user = get_userdata( (int) $ids[0] );
		}
	}
	$user = apply_filters( 'flx_user_by_phone', $user, (string) $raw );
	if ( '' === $key || ! $user instanceof WP_User ) {
		return null;
	}
	return flxr_normalize_phone( flxr_user_phone( $user->ID ) ) === $key ? $user : null;
}

/**
 * Numaranın kimliği: asistanın hangi araçları açacağına karar vermesi için.
 * Dönüş null (kayıtlı kullanıcı yok) ya da:
 * array( user_id, name, role, role_label, caps => [ katalogdaki açık yetkiler ], is_admin )
 */
function flxr_phone_identity( $raw ) {
	$u = flxr_user_by_phone( $raw );
	if ( ! $u ) {
		return null;
	}
	$role  = flxr_user_role( $u );
	$roles = flxr_roles();
	$caps  = array();
	foreach ( flxr_catalog() as $g ) {
		foreach ( array_keys( $g['caps'] ) as $cap ) {
			if ( user_can( $u, $cap ) ) {
				$caps[] = $cap;
			}
		}
	}
	return array(
		'user_id'    => (int) $u->ID,
		'name'       => (string) $u->display_name,
		'role'       => $role,
		'role_label' => $role && isset( $roles[ $role ] ) ? $roles[ $role ]['label'] : '',
		'caps'       => array_values( array_unique( $caps ) ),
		'is_admin'   => user_can( $u, 'manage_options' ),
	);
}

/** Numaranın sahibi bu yetkiye sahip mi? */
function flxr_phone_can( $raw, $cap ) {
	$u = flxr_user_by_phone( $raw );
	return $u ? user_can( $u, (string) $cap ) : false;
}

/* Profil: telefon alanı (yalnız kullanıcı düzenleyebilen yönetici yazar). */
function flxr_phone_profile_field( $user ) {
	if ( ! $user instanceof WP_User ) {
		return;
	}
	$can = current_user_can( 'promote_users' );
	$val = flxr_user_phone( $user->ID );
	if ( ! $can && '' === $val && ! flxr_user_role( $user ) ) {
		return;
	}
	echo '<h2 id="flx-phone">' . esc_html__( 'WhatsApp number', 'flx-roles' ) . '</h2><table class="form-table" role="presentation"><tr><th scope="row"><label for="flxr-phone">' . esc_html__( 'Phone', 'flx-roles' ) . '</label></th><td>';
	if ( $can ) {
		wp_nonce_field( 'flxr_phone_' . $user->ID, 'flxr_phone_nonce' );
		echo '<input type="tel" id="flxr-phone" name="flxr_phone" class="regular-text" value="' . esc_attr( '' !== $val ? '+' . $val : '' ) . '" placeholder="+90 5xx xxx xx xx" autocomplete="off">';
	} else {
		echo '<input type="text" id="flxr-phone" class="regular-text" value="' . esc_attr( '' !== $val ? '+' . $val : '' ) . '" readonly>';
	}
	echo '<p class="description">' . esc_html__( 'The WhatsApp assistant recognizes whoever writes from this number as this user and enables what their role allows (e.g. donations on behalf of others, their own donors). Only an administrator can change the number.', 'flx-roles' ) . '</p>';
	echo '</td></tr></table>';
}
add_action( 'show_user_profile', 'flxr_phone_profile_field', 9 );
add_action( 'edit_user_profile', 'flxr_phone_profile_field', 9 );

function flxr_phone_profile_save( $user_id ) {
	if ( ! isset( $_POST['flxr_phone_nonce'] ) || ! current_user_can( 'promote_users' ) || ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	check_admin_referer( 'flxr_phone_' . $user_id, 'flxr_phone_nonce' );
	$r = flxr_set_user_phone( $user_id, sanitize_text_field( wp_unslash( $_POST['flxr_phone'] ?? '' ) ) );
	if ( is_wp_error( $r ) ) {
		add_action(
			'user_profile_update_errors',
			function ( $errors ) use ( $r ) {
				$errors->add( $r->get_error_code(), $r->get_error_message() );
			}
		);
	}
}
add_action( 'personal_options_update', 'flxr_phone_profile_save' );
add_action( 'edit_user_profile_update', 'flxr_phone_profile_save' );
