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
			$out[ 'flxcrm_seg:' . (int) $s['id'] ] = sprintf( __( 'CRM segmenti: %1$s (%2$s)', 'flx-crm' ), $s['name'], number_format_i18n( (int) $s['count'] ) );
		}
		$stats = FLXCRM_Contacts::stats();
		foreach ( flxcrm_roles() as $k => $r ) {
			if ( ! empty( $stats['roles'][ $k ] ) ) {
				/* translators: 1: role name, 2: contact count */
				$out[ 'flxcrm_role:' . $k ] = sprintf( __( 'CRM rolü: %1$s (%2$s)', 'flx-crm' ), $r['label'], number_format_i18n( $stats['roles'][ $k ] ) );
			}
		}
		return $out;
	}
);

add_filter(
	'flxi_recipients_from_source',
	function ( $list, $src, $channel ) {
		$f = null;
		if ( 0 === strpos( (string) $src, 'flxcrm_seg:' ) ) {
			$s = FLXCRM_Segments::get( (int) substr( $src, 11 ) );
			if ( $s ) {
				$f = array( 'rules' => $s['rules'] );
			}
		} elseif ( 0 === strpos( (string) $src, 'flxcrm_role:' ) ) {
			$f = array( 'role' => sanitize_key( substr( $src, 12 ) ) );
		}
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
				FLXCRM_CAP_VIEW   => array( 'label' => __( 'Kişileri, segmentleri ve zaman tünelini görür', 'flx-crm' ), 'new' => true ),
				FLXCRM_CAP_MANAGE => array( 'label' => __( 'Kişi ekler, düzenler, siler; segment ve CRM ayarlarını yönetir', 'flx-crm' ), 'new' => true, 'warn' => true ),
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

add_filter(
	'flxai_tools',
	function ( $tools ) {
		$tools['flxcrm_find_contacts'] = array(
			'title'       => __( 'CRM kişisi ara', 'flx-crm' ),
			'description' => 'Searches CRM contacts (donors, customers, leads, volunteers) by name, email, phone, company or city. Optional filters: role (donor, customer, lead, volunteer, member, partner, beneficiary), segment (new, champion, loyal, promising, at_risk, hibernating, lost), risk (true = high churn risk). Returns id, name, roles, segment, lifetime value, churn risk and last activity.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'q'       => array( 'type' => 'string' ),
					'role'    => array( 'type' => 'string' ),
					'segment' => array( 'type' => 'string' ),
					'risk'    => array( 'type' => 'boolean' ),
					'limit'   => array( 'type' => 'integer' ),
				),
			),
			'readonly'    => true,
			'cap'         => FLXCRM_CAP_VIEW,
			'source'      => 'flx-crm',
			'run'         => function ( $args ) {
				$q = FLXCRM_Contacts::query(
					array(
						's'        => (string) ( $args['q'] ?? '' ),
						'role'     => sanitize_key( (string) ( $args['role'] ?? '' ) ),
						'segment'  => sanitize_key( (string) ( $args['segment'] ?? '' ) ),
						'risk'     => ! empty( $args['risk'] ),
						'per_page' => min( 50, max( 1, (int) ( $args['limit'] ?? 15 ) ) ),
						'orderby'  => ! empty( $args['risk'] ) ? 'churn' : 'last_activity_at',
					)
				);
				return array( 'total' => $q['total'], 'contacts' => array_map( array( 'FLXCRM_REST', 'shape' ), $q['rows'] ) );
			},
		);
		$tools['flxcrm_get_contact'] = array(
			'title'       => __( 'CRM kişi kartı', 'flx-crm' ),
			'description' => 'Full CRM profile of one contact by id: roles, consents, totals (donations, orders, lifetime value), RFM scores, churn risk, tags and the latest timeline events (donations, orders, forms, WhatsApp, notes).',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'id' => array( 'type' => 'integer' ) ),
				'required'   => array( 'id' ),
			),
			'readonly'    => true,
			'cap'         => FLXCRM_CAP_VIEW,
			'source'      => 'flx-crm',
			'run'         => function ( $args ) {
				$r = new WP_REST_Request( 'GET' );
				$r->set_url_params( array( 'id' => (int) ( $args['id'] ?? 0 ) ) );
				$r->set_param( 'limit', 30 );
				$out = FLXCRM_REST::get_contact( $r );
				if ( is_wp_error( $out ) ) {
					throw new Exception( $out->get_error_message() );
				}
				return $out;
			},
		);
		$tools['flxcrm_overview'] = array(
			'title'       => __( 'CRM özeti', 'flx-crm' ),
			'description' => 'CRM overview: contact count, new in 30 days, active, high churn risk, total lifetime value, counts per role, per RFM segment and per source, and saved segments with their sizes.',
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'readonly'    => true,
			'cap'         => FLXCRM_CAP_VIEW,
			'source'      => 'flx-crm',
			'run'         => function () {
				return array(
					'stats'    => FLXCRM_Contacts::stats(),
					'currency' => flxcrm_currency(),
					'segments' => FLXCRM_REST::segments(),
				);
			},
		);
		$tools['flxcrm_add_note'] = array(
			'title'       => __( 'CRM kişisine not ekle', 'flx-crm' ),
			'description' => 'Adds a note, call or meeting record to a CRM contact timeline. type: note | call | meeting.',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'id'   => array( 'type' => 'integer' ),
					'type' => array( 'type' => 'string', 'enum' => array( 'note', 'call', 'meeting' ) ),
					'text' => array( 'type' => 'string' ),
				),
				'required'   => array( 'id', 'text' ),
			),
			'readonly'    => false,
			'cap'         => FLXCRM_CAP_MANAGE,
			'source'      => 'flx-crm',
			'run'         => function ( $args ) {
				$id   = (int) ( $args['id'] ?? 0 );
				$text = sanitize_textarea_field( (string) ( $args['text'] ?? '' ) );
				if ( ! FLXCRM_Contacts::get( $id ) || '' === trim( $text ) ) {
					throw new Exception( 'Contact not found or empty text.' );
				}
				$type = in_array( $args['type'] ?? 'note', array( 'note', 'call', 'meeting' ), true ) ? $args['type'] : 'note';
				return array( 'event_id' => FLXCRM_REST::manual_event( $id, $type, $text ) );
			},
		);
		return $tools;
	}
);
