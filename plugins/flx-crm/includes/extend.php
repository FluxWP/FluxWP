<?php
/**
 * Genişletme noktaları: CRM'i güncellemeden başka bir paket (FluxWP ya da üçüncü taraf) kendini buraya kaydeder.
 *
 * - Ortak kişi tablosu köprüsü: `flx_person_*` çağrıları CRM kuruluyken CRM'in kurallarıyla yürür (eşleştirme, rol,
 *   zaman tüneli, eylemler); CRM yokken ortak modülün sade hâli çalışır. Kişi mantığı tek yerde kalır.
 * - Kaynak kaydı: `flxcrm_register_sources` filtresi. Kaydolan kaynak Ayarlar'da aç/kapa ve rol satırı, "Tümünü eşitle"de
 *   adım, bağlantılarda ad olarak görünür.
 *
 * Diğer kayıt noktaları (hepsi filtre/eylem, API.md "Genişletme"): flxcrm_roles, flxcrm_event_types, flxcrm_sources,
 * flxcrm_sync_sources, flxcrm_tools, flxcrm_segment_fields, flxcrm_segment_compile, flxcrm_default_settings,
 * flxcrm_person_card_* eylemleri, flx_people_columns.
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------- */
/* Ortak kişi tablosu köprüsü                                        */
/* ---------------------------------------------------------------- */

add_filter(
	'flx_person_pre_find',
	static function ( $pre, $who ) {
		return null === $pre ? (int) FLXCRM_Contacts::find( (array) $who ) : $pre;
	},
	10,
	2
);

add_filter(
	'flx_person_pre_ensure',
	static function ( $pre, $d ) {
		return null === $pre ? (int) FLXCRM_Contacts::upsert( (array) $d ) : $pre;
	},
	10,
	2
);

add_filter(
	'flx_person_pre_update',
	static function ( $pre, $id, $d ) {
		return null === $pre ? FLXCRM_Contacts::update( (int) $id, (array) $d ) : $pre;
	},
	10,
	3
);

/* CRM'in kişi eylemleri ortak adlarıyla da duyurulur: CRM'e bağlı olmayan paketler yalnız flx_person_* dinler. */
add_action(
	'flxcrm_contact_created',
	static function ( $id, $row ) {
		do_action( 'flx_person_created', (int) $id, (array) $row );
	},
	10,
	2
);
add_action(
	'flxcrm_contact_updated',
	static function ( $id, $changes ) {
		if ( $changes ) {
			do_action( 'flx_person_updated', (int) $id, (array) $changes, null );
		}
	},
	10,
	2
);
add_action(
	'flxcrm_contact_merged',
	static function ( $primary_id, $dup_id ) {
		/** Paketler kendi kayıtlarındaki kişi kimliğini $dup_id'den $primary_id'ye çevirir. */
		do_action( 'flx_person_merged', (int) $primary_id, (int) $dup_id );
	},
	10,
	2
);
add_action(
	'flxcrm_contact_deleting',
	static function ( $id ) {
		do_action( 'flx_person_deleting', (int) $id );
	}
);

/* ---------------------------------------------------------------- */
/* Kaynak kaydı                                                      */
/* ---------------------------------------------------------------- */

/**
 * Kişi getiren kaynaklar.
 *
 * Kayıt:
 *     add_filter( 'flxcrm_register_sources', function ( $s ) {
 *         $s['booking'] = array(
 *             'label'       => 'My Booking',
 *             'description' => __( 'Guests and reservations', 'my-domain' ),
 *             'installed'   => true,
 *             'role'        => 'customer',                      // yeni kişiye verilen varsayılan rol; null = rol satırı yok
 *             'backfill'    => array( 'My_Booking_CRM', 'backfill' ), // ( $page, $per ) → [ done, scanned, total ]; isteğe bağlı
 *         );
 *         return $s;
 *     } );
 * Açık mı: flxcrm_source_on( 'booking' ); rol: flxcrm_source_role( 'booking' ).
 *
 * @return array key => [ label, description, installed, role, backfill, class ]
 */
function flxcrm_registered_sources() {
	$builtin = array(
		'donate'   => array( 'label' => 'Flux Donate', 'installed' => function_exists( 'flxb_table' ), 'description' => __( 'Donors and donations', 'flx-crm' ), 'role' => 'donor', 'class' => 'FLXCRM_Adapter_Donate' ),
		'commerce' => array( 'label' => 'WooCommerce', 'installed' => class_exists( 'WooCommerce' ), 'description' => __( 'Customers and orders (donation orders excluded)', 'flx-crm' ), 'role' => 'customer', 'class' => 'FLXCRM_Adapter_Commerce' ),
		'chat'     => array( 'label' => 'Flux Chat', 'installed' => function_exists( 'flxc_table' ), 'description' => __( 'WhatsApp chats and every message', 'flx-crm' ), 'role' => 'lead', 'class' => 'FLXCRM_Adapter_Chat' ),
		'forms'    => array( 'label' => 'Flux Forms', 'installed' => defined( 'EMSFB_PLUGIN_DIRECTORY' ) || class_exists( 'FLXF_Automations' ), 'description' => __( 'Form submissions (with an e-mail or phone field)', 'flx-crm' ), 'role' => 'lead', 'class' => 'FLXCRM_Adapter_Forms' ),
		'users'    => array( 'label' => __( 'Site members', 'flx-crm' ), 'installed' => true, 'description' => __( 'WordPress users with the customer or subscriber role', 'flx-crm' ), 'role' => '', 'class' => 'FLXCRM_Adapter_Users' ),
		'mail'     => array( 'label' => 'Flux Mail', 'installed' => function_exists( 'flxi_table' ), 'description' => __( 'E-mails, SMS and WhatsApp notifications sent to the contact, and incoming e-mails (timeline only, no new contacts)', 'flx-crm' ), 'role' => null, 'class' => 'FLXCRM_Adapter_Mail' ),
	);
	$out = array();
	foreach ( $builtin as $k => $s ) {
		$out[ $k ] = $s + array( 'backfill' => null, 'builtin' => true );
	}
	foreach ( (array) apply_filters( 'flxcrm_register_sources', array() ) as $k => $s ) {
		$k = substr( sanitize_key( (string) $k ), 0, 24 );
		if ( '' === $k || isset( $builtin[ $k ] ) || ! is_array( $s ) || in_array( $k, array( 'manual', 'import', 'api' ), true ) ) {
			continue;
		}
		$out[ $k ] = array(
			'label'       => '' !== trim( (string) ( $s['label'] ?? '' ) ) ? wp_strip_all_tags( (string) $s['label'] ) : $k,
			'description' => wp_strip_all_tags( (string) ( $s['description'] ?? '' ) ),
			'installed'   => ! isset( $s['installed'] ) || (bool) $s['installed'],
			'role'        => array_key_exists( 'role', $s ) && null === $s['role'] ? null : sanitize_key( (string) ( $s['role'] ?? '' ) ),
			'backfill'    => isset( $s['backfill'] ) && is_callable( $s['backfill'] ) ? $s['backfill'] : null,
			'class'       => '',
			'builtin'     => false,
		);
	}
	return $out;
}

/** Kaynak açık mı (Ayarlar › Kaynaklar)? Kayıtlı ama hiç kaydedilmemiş kaynak açık sayılır. */
function flxcrm_source_on( $key ) {
	$v = flxcrm_setting( 'src_' . sanitize_key( (string) $key ) );
	return null === $v ? true : (bool) $v;
}

/** Kaynaktan gelen yeni kişiye verilecek rol ('' = rol yok). */
function flxcrm_source_role( $key ) {
	$key = sanitize_key( (string) $key );
	$v   = flxcrm_setting( 'role_' . $key );
	if ( null === $v ) {
		$s = flxcrm_registered_sources();
		$v = (string) ( $s[ $key ]['role'] ?? '' );
	}
	$known = flxcrm_roles();
	return isset( $known[ $v ] ) ? $v : '';
}

/* Kayıtlı kaynakların adı bağlantılarda ve "ilk kaynak"ta görünür. */
add_filter(
	'flxcrm_sources',
	static function ( $names ) {
		foreach ( flxcrm_registered_sources() as $k => $s ) {
			if ( empty( $s['builtin'] ) && ! isset( $names[ $k ] ) ) {
				$names[ $k ] = $s['label'];
			}
		}
		return $names;
	}
);

/* Kayıtlı kaynakların geçmiş veri adımı "Tümünü eşitle"ye eklenir. */
add_filter(
	'flxcrm_sync_sources',
	static function ( $out ) {
		foreach ( flxcrm_registered_sources() as $k => $s ) {
			if ( empty( $s['builtin'] ) && $s['backfill'] && $s['installed'] && flxcrm_source_on( $k ) && ! isset( $out[ $k ] ) ) {
				$out[ $k ] = array( $s['label'], $s['backfill'] );
			}
		}
		return $out;
	},
	5
);

/* Kayıtlı kaynakların aç/kapa ve rol ayarı CRM ayarlarının parçası olur (kaydedilir, varsayılanı vardır). */
add_filter(
	'flxcrm_default_settings',
	static function ( $d ) {
		foreach ( flxcrm_registered_sources() as $k => $s ) {
			if ( ! empty( $s['builtin'] ) ) {
				continue;
			}
			if ( ! isset( $d[ 'src_' . $k ] ) ) {
				$d[ 'src_' . $k ] = 1;
			}
			if ( null !== $s['role'] && ! isset( $d[ 'role_' . $k ] ) ) {
				$d[ 'role_' . $k ] = (string) $s['role'];
			}
		}
		return $d;
	}
);
