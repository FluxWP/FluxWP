<?php
/**
 * Diğer FluxWP paketleriyle bağlar:
 * - Flux Mail: CRM segmentleri ve rolleri toplu gönderimde alıcı kaynağı (yalnız ilgili kanala izin verenler).
 * - Flux Roles: yetki grubu.
 * - Flux AI: sohbet araçları ve bağlı sistem kaydı.
 * - Flux App: modül (REST).
 *
 * @package FLX_CRM
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------- */
/* Flux Mail                                                         */
/* ---------------------------------------------------------------- */

/** Kanalın izin alanı: email → consent_email, whatsapp → consent_whatsapp, sms → consent_sms. */
function flxcrm_channel_consent( $channel ) {
	$map = array( 'email' => 'email', 'whatsapp' => 'whatsapp', 'sms' => 'sms' );
	return $map[ $channel ] ?? 'email';
}

/**
 * Pazarlama gönderiminde izin şartı. Kapatılırsa (filtre false) segmentteki herkes alıcı olur;
 * yalnız işlem bildirimi gibi izin gerektirmeyen gönderimler için.
 */
function flxcrm_mail_requires_consent( $channel ) {
	return (bool) apply_filters( 'flxcrm_mail_requires_consent', true, $channel );
}

add_filter(
	'flxi_recipient_sources',
	function ( $out ) {
		if ( ! flxcrm_can() ) {
			return $out;
		}
		foreach ( FLXCRM_Segments::all() as $s ) {
			/* translators: 1: segment name, 2: contact count */
			$out[ 'flxcrm_seg:' . (int) $s['id'] ] = sprintf( __( 'CRM segment: %1$s (%2$s)', 'flx-crm' ), $s['name'], number_format_i18n( (int) $s['count'] ) );
		}
		$stats = FLXCRM_Contacts::stats();
		foreach ( flxcrm_roles() as $k => $r ) {
			if ( ! empty( $stats['roles'][ $k ] ) ) {
				/* translators: 1: role name, 2: contact count */
				$out[ 'flxcrm_role:' . $k ] = sprintf( __( 'CRM role: %1$s (%2$s)', 'flx-crm' ), $r['label'], number_format_i18n( $stats['roles'][ $k ] ) );
			}
		}
		return $out;
	}
);

add_filter(
	'flxi_recipients_from_source',
	function ( $list, $src, $channel ) {
		$f = flxcrm_mail_source_filter( $src );
		if ( null === $f ) {
			return $list;
		}
		if ( flxcrm_mail_requires_consent( $channel ) ) {
			$f['consent'] = flxcrm_channel_consent( $channel );
		}
		global $wpdb;
		$ids = FLXCRM_Contacts::ids( $f );
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$rows = $wpdb->get_results( 'SELECT name, email, phone FROM ' . flxcrm_table( 'contacts' ) . ' WHERE id IN (' . implode( ',', array_map( 'intval', $chunk ) ) . ')', ARRAY_A ); // phpcs:ignore WordPress.DB
			foreach ( (array) $rows as $r ) {
				$list[] = array( 'name' => $r['name'], 'email' => $r['email'], 'phone' => $r['phone'] );
			}
		}
		return $list;
	},
	10,
	3
);

/** CRM kaynağının süzgeci: segment kuralları ya da rol. CRM kaynağı değilse null. */
function flxcrm_mail_source_filter( $src ) {
	if ( 0 === strpos( (string) $src, 'flxcrm_seg:' ) ) {
		$s = FLXCRM_Segments::get( (int) substr( $src, 11 ) );
		return $s ? array( 'rules' => $s['rules'] ) : null;
	}
	if ( 0 === strpos( (string) $src, 'flxcrm_role:' ) ) {
		return array( 'role' => sanitize_key( substr( $src, 12 ) ) );
	}
	return null;
}

/**
 * Flux Mail'e dökümü bildirir: listede kaç kişi var, kaçı bu kanala izin vermediği için alınmadı.
 * Gönder ekranı "43 kişiden 0'ı eklendi, 43'ü e-posta izni vermemiş" diyebilsin diye.
 */
add_filter(
	'flxi_recipient_source_info',
	function ( $info, $src, $channel ) {
		$f = flxcrm_mail_source_filter( $src );
		if ( null === $f ) {
			return $info;
		}
		$total = FLXCRM_Contacts::count( $f );
		$ok    = $total;
		if ( flxcrm_mail_requires_consent( $channel ) ) {
			$ok = FLXCRM_Contacts::count( $f + array( 'consent' => flxcrm_channel_consent( $channel ) ) );
		}
		return array( 'total' => $total, 'no_consent' => max( 0, $total - $ok ) );
	},
	10,
	3
);

/** Flux Mail › Gönder ekranı (segment orada "Alıcılar" altında seçilir). */
function flxcrm_mail_send_url() {
	if ( ! function_exists( 'flxi_recipient_sources' ) ) {
		return '';
	}
	return admin_url( 'admin.php?page=flxi-gonder' );
}

/* ---------------------------------------------------------------- */
/* Flux Roles                                                        */
/* ---------------------------------------------------------------- */

add_filter(
	'flxr_catalog',
	function ( $groups ) {
		$groups['crm'] = array(
			'label'  => __( 'CRM', 'flx-crm' ),
			'plugin' => 'Flux CRM',
			'active' => true,
			'caps'   => array(
				FLXCRM_CAP_VIEW   => array( 'label' => __( 'Sees contacts, segments and the timeline', 'flx-crm' ), 'new' => true ),
				FLXCRM_CAP_MANAGE => array( 'label' => __( 'Adds, edits and deletes contacts; manages segments and CRM settings', 'flx-crm' ), 'new' => true, 'warn' => true ),
			),
		);
		return $groups;
	}
);

/* ---------------------------------------------------------------- */
/* Flux App                                                          */
/* ---------------------------------------------------------------- */

add_filter(
	'flxapp_modules',
	function ( $m ) {
		$m['crm'] = array(
			'title'   => __( 'CRM', 'flx-crm' ),
			'cap'     => FLXCRM_CAP_VIEW,
			'menu'    => 'flx-crm',
			'api'     => rest_url( FLXCRM_REST::NS ),
			'icon'    => 'users',
			'version' => FLXCRM_VERSION,
		);
		return $m;
	}
);

/* ---------------------------------------------------------------- */
/* Flux AI                                                           */
/* ---------------------------------------------------------------- */

add_filter(
	'flxai_systems',
	function ( $s ) {
		$s['flx-crm'] = array(
			'label'  => 'Flux CRM',
			'plugin' => plugin_basename( FLXCRM_FILE ),
			'page'   => 'admin.php?page=flx-crm',
		);
		return $s;
	}
);

/* Flux AI ve MCP araçları: includes/ai-tools.php. */
