<?php
defined( 'ABSPATH' ) || exit;

/**
 * Kayıtlar: sohbetten bağış (Flux Donate) ve sertifika (Flux Certificates) oluşturma, gelen dekontu kayıtla eşleştirme,
 * kayıtlarda arama. Eklentilerden biri yoksa ilgili bölüm görünmez; bu eklenti onlara bağımlı değildir.
 */

add_action( 'rest_api_init', 'flxc_register_records_rest' );
add_action( 'admin_post_flxc_receipt', 'flxc_receipt_download' );

function flxc_flxb_on() {
	return class_exists( 'FLXB_Donations' ) && class_exists( 'FLXB_Types' ) && class_exists( 'FLXB_Donors' ) && function_exists( 'flxb_get_types' );
}

function flxc_flxs_on() {
	return function_exists( 'flxs_create_certificate' ) && function_exists( 'flxs_upsert_person' ) && function_exists( 'flxs_list_templates' );
}

/** Temsilci bağış kaydı oluşturabilir mi (yöneticiler her zaman). */
function flxc_can_record_donation() {
	$ok = flxc_flxb_on() && flxc_can_use() && ( flxc_can_manage() || ( function_exists( 'flxb_can_manage' ) && flxb_can_manage() ) || flxc_setting( 'agents_donations' ) );
	return (bool) apply_filters( 'flxc_can_record_donation', $ok );
}

function flxc_can_record_certificate() {
	$ok = flxc_flxs_on() && flxc_can_use() && ( flxc_can_manage() || ( function_exists( 'flxs_can_use' ) && flxs_can_use() ) || flxc_setting( 'agents_certificates' ) );
	return (bool) apply_filters( 'flxc_can_record_certificate', $ok );
}

function flxc_register_records_rest() {
	$ns  = 'flx-chat/v1';
	$use = function () {
		return flxc_can_use();
	};
	$routes = array(
		array( '/records/search', 'GET', 'flxc_rest_records_search' ),
		array( '/records/chat/(?P<id>\d+)', 'GET', 'flxc_rest_records_for_chat' ),
		array( '/records/donations', 'POST', 'flxc_rest_create_donation' ),
		array( '/records/donations/(?P<id>\d+)', 'GET', 'flxc_rest_donation_detail' ),
		array( '/records/donations/(?P<id>\d+)/status', 'POST', 'flxc_rest_donation_status' ),
		array( '/records/certificates', 'POST', 'flxc_rest_create_certificate' ),
		array( '/records/certificates/(?P<id>\d+)', 'GET', 'flxc_rest_get_certificate' ),
		array( '/records/certificates/(?P<id>\d+)/send', 'POST', 'flxc_rest_send_certificate' ),
		array( '/messages/(?P<id>\d+)/receipt', 'POST', 'flxc_rest_attach_receipt' ),
		array( '/messages/(?P<id>\d+)/receipt', 'DELETE', 'flxc_rest_detach_receipt' ),
	);
	foreach ( $routes as $r ) {
		register_rest_route(
			$ns,
			$r[0],
			array(
				'methods'             => $r[1],
				'callback'            => $r[2],
				'permission_callback' => $use,
			)
		);
	}
}

/* ---------- arayüze giden veriler ---------- */

/** Bootstrap'a eklenen: hangi kayıt türleri açık, bağış türleri, sertifika şablonları, etkinlikler. */
function flxc_records_boot() {
	$out = array(
		'donations'    => flxc_can_record_donation(),
		'certificates' => flxc_can_record_certificate(),
		'search'       => flxc_flxb_on() || flxc_flxs_on(),
		'chips'        => (bool) flxc_setting( 'chips_bar' ),
	);
	if ( $out['donations'] ) {
		$types = array();
		foreach ( (array) flxb_get_types( array( 'status' => 'open', 'progress' => false ) ) as $t ) {
			$opts = array();
			foreach ( (array) ( $t['options'] ?? array() ) as $o ) {
				if ( isset( $o['active'] ) && ! $o['active'] ) {
					continue;
				}
				$opts[] = array(
					'key'   => (string) $o['key'],
					'label' => (string) $o['label'],
					'price' => null === ( $o['price'] ?? null ) ? null : (float) $o['price'],
					'unit'  => (string) ( $o['unit_label'] ?? '' ),
				);
			}
			$types[] = array(
				'id'       => (int) $t['id'],
				'title'    => (string) $t['title'],
				'currency' => (string) ( $t['currency'] ?? '' ),
				'free'     => ! empty( $t['allow_free'] ),
				'qty'      => ! empty( $t['allow_quantity'] ),
				'maxQty'   => (int) ( $t['max_quantity'] ?? 0 ),
				'proxy'    => ! empty( $t['requires_proxy'] ),
				'options'  => $opts,
			);
		}
		$out['types']    = $types;
		$out['currency'] = function_exists( 'flxb_default_currency' ) ? flxb_default_currency() : 'TRY';
		$out['symbol']   = function_exists( 'flxb_currency_symbol' ) ? flxb_currency_symbol( $out['currency'] ) : '';
		$out['methods']  = flxc_donation_methods();
		$out['msgPending'] = flxc_record_text( 'msg_donation_pending' );
		$out['msgDone']    = flxc_record_text( 'msg_donation_done' );
		$out['msgReceipt'] = flxc_record_text( 'msg_receipt' );
	}
	if ( $out['certificates'] ) {
		$tpls = array();
		foreach ( (array) flxs_list_templates( true ) as $t ) {
			$fields = array();
			foreach ( (array) ( $t['config']['fields'] ?? array() ) as $f ) {
				if ( in_array( $f['type'], array( 'static', 'qr', 'number_auto' ), true ) ) {
					continue;
				}
				$fields[] = array(
					'key'      => (string) $f['key'],
					'label'    => (string) $f['label'],
					'type'     => (string) $f['type'],
					'default'  => (string) $f['default'],
					'required' => ! empty( $f['required'] ),
					'options'  => (string) ( $f['options'] ?? '' ),
				);
			}
			$tpls[] = array(
				'id'     => (int) $t['id'],
				'name'   => (string) $t['name'],
				'fields' => $fields,
			);
		}
		$out['templates'] = $tpls;
		$out['events']    = function_exists( 'flxs_events_for_js' ) ? flxs_events_for_js( 'open' ) : array();
	}
	return $out;
}

function flxc_donation_methods() {
	return array(
		'bank_transfer' => __( 'Bank transfer', 'flx-chat' ),
		'cash'          => __( 'Cash', 'flx-chat' ),
		'other'         => __( 'Other', 'flx-chat' ),
	);
}

/** Ayarlardaki mesaj metni; boşsa (varsayılan) site diline göre çevrilen hazır metin. */
function flxc_record_text( $key ) {
	$v = trim( (string) flxc_setting( $key ) );
	if ( '' !== $v ) {
		return $v;
	}
	$def = array(
		'msg_donation_pending' => __( 'Hello {first_name}, we have created your donation record: {donation}, total {amount}.

{bank}

After paying, you can send the receipt here. Thank you.', 'flx-chat' ),
		'msg_donation_done'    => __( 'Hello {first_name}, your donation has been recorded: {donation}, total {amount}.

Thank you for your support.', 'flx-chat' ),
		'msg_receipt'          => __( 'Your receipt has reached us and your donation is confirmed ({donation}, {amount}). Thank you.', 'flx-chat' ),
	);
	return $def[ $key ] ?? '';
}

/** {bank}: ayardaki banka metni, yoksa flxc_bot_bank_account() (WooCommerce Havale, sonra Flux Donate hesapları). */
function flxc_bank_text() {
	$t = trim( (string) flxc_setting( 'bank_text' ) );
	if ( '' !== $t ) {
		return $t;
	}
	$acc = flxc_bot_bank_account();
	if ( '' === $acc['iban'] ) {
		return '';
	}
	return implode( "\n", array_filter( array( $acc['holder'], $acc['bank'], 'IBAN: ' . $acc['iban'] ), 'strlen' ) );
}

function flxc_money( $amount, $currency = '' ) {
	if ( function_exists( 'flxb_money' ) ) {
		return flxb_money( $amount, $currency );
	}
	return number_format_i18n( (float) $amount, abs( $amount - round( $amount ) ) < 0.005 ? 0 : 2 ) . ( $currency ? ' ' . $currency : '' );
}

function flxc_local_date( $utc ) {
	$ts = strtotime( (string) $utc . ' UTC' );
	return $ts ? flx_date( $ts, 'datetime' ) : '';
}

/** Bağış satırını arayüz için özetle. */
function flxc_donation_for_js( array $d ) {
	$donor = ! empty( $d['donor_id'] ) ? FLXB_Donors::get( (int) $d['donor_id'] ) : null;
	$type  = ! empty( $d['type_id'] ) ? FLXB_Types::get( (int) $d['type_id'] ) : null;
	// 1.39.0: "Receipt awaiting approval" / "Awaiting payment" instead of a bare "Pending".
	$st    = function_exists( 'flxb_donation_state' ) ? flxb_donation_state( $d ) : ( function_exists( 'flxb_status_label' ) ? flxb_status_label( $d['status'] ) : array( '', $d['status'] ) );
	$ref   = (int) ( $d['ref_user_id'] ?? 0 );
	$dref  = ! $ref && ! empty( $d['donor_id'] ) && method_exists( 'FLXB_Refs', 'donor_ref' ) ? FLXB_Refs::donor_ref( (int) $d['donor_id'] ) : 0;
	$what  = array_filter( array( $type ? $type['title'] : '' ) );
	$ol    = (string) $d['option_label'];
	if ( '' !== $ol && ( ! $what || false === mb_stripos( $what[0], $ol ) ) ) {
		$what[] = $ol;
	}
	$qty   = (int) $d['quantity'];
	return array(
		'kind'     => 'donation',
		'id'       => (int) $d['id'],
		'title'    => $what ? implode( ' · ', array_unique( $what ) ) : __( 'Donation', 'flx-chat' ),
		'qty'      => $qty,
		'amount'   => (float) $d['amount'],
		'money'    => flxc_money( $d['amount'], $d['currency'] ),
		'status'   => (string) $d['status'],
		'statusL'  => (string) $st[1],
		'tone'     => (string) $st[0],
		'method'   => function_exists( 'flxb_provider_label' ) ? flxb_provider_label( $d['provider'] ) : (string) $d['provider'],
		'name'     => $donor ? (string) $donor['name'] : '',
		'phone'    => $donor ? (string) $donor['phone'] : '',
		'date'     => flxc_local_date( $d['created_at'] ),
		'cert'     => (string) $d['certificate_code'],
		'receipts' => flxc_receipt_count( 'donation', (int) $d['id'] ),
		'admin'    => flxb_can_manage() ? admin_url( 'admin.php?page=flxb-donations&donation=' . (int) $d['id'] ) : '',
		'ref'      => $ref || $dref ? FLXB_Refs::name( $ref ? $ref : $dref ) : '',
		'refDonor' => ! $ref && $dref ? 1 : 0, // 1.39.0: the donor's earlier referral, this gift has none
	);
}

/**
 * 1.39.0: everything about one gift for the call centre's donation window: lines, receipts,
 * certificates, donor, referral and what the agent may do.
 */
function flxc_rest_donation_detail( WP_REST_Request $req ) {
	if ( ! flxc_flxb_on() ) {
		return new WP_Error( 'flxc_off', __( 'Flux Donate is not active.', 'flx-chat' ), array( 'status' => 404 ) );
	}
	$d = FLXB_Donations::get( (int) $req['id'] );
	if ( ! $d || 'trash' === $d['status'] ) {
		return new WP_Error( 'flxc_404', __( 'Donation not found.', 'flx-chat' ), array( 'status' => 404 ) );
	}
	$out   = flxc_donation_for_js( $d );
	$sibs  = FLXB_Donations::siblings( $d );
	$order = (int) $d['wc_order_id'] && function_exists( 'wc_get_order' ) ? wc_get_order( (int) $d['wc_order_id'] ) : null;
	$total = $order ? (float) $order->get_total() : array_sum( array_map( 'floatval', wp_list_pluck( $sibs, 'amount' ) ) );
	$out['total']   = flxc_money( $total, $order ? $order->get_currency() : $d['currency'] );
	$out['order']   = $order ? (string) $order->get_order_number() : '';
	$out['track']   = (string) $d['track'];
	$out['ago']     = function_exists( 'flx_time_ago' ) ? flx_time_ago( strtotime( $d['created_at'] . ' UTC' ) ) : '';
	$out['source']  = class_exists( 'FLXB_Page_Donations' ) ? FLXB_Page_Donations::source_label( $d['source'] ) : (string) $d['source'];
	$out['lines']   = array();
	foreach ( $sibs as $s ) {
		$items = class_exists( 'FLXB_Items' ) ? FLXB_Items::for_donation( (int) $s['id'] ) : array();
		$type  = (int) $s['type_id'] ? FLXB_Types::get( (int) $s['type_id'] ) : null;
		$names = array_values( array_filter( array_map( 'strval', wp_list_pluck( $items, 'beneficiary_name' ) ), 'strlen' ) );
		$out['lines'][] = array(
			'title' => '' !== (string) $s['option_label'] ? (string) $s['option_label'] : ( $type ? (string) $type['title'] : __( 'Donation', 'flx-chat' ) ),
			'type'  => $type ? (string) $type['title'] : '',
			'qty'   => max( 1, (int) $s['quantity'] ),
			'money' => flxc_money( $s['amount'], $s['currency'] ),
			'names' => $names,
		);
	}
	$out['receiptFiles'] = array();
	if ( ! class_exists( 'FLXB_Approval' ) && defined( 'FLXB_DIR' ) && is_readable( FLXB_DIR . 'includes/admin/class-flxb-approval.php' ) ) {
		require_once FLXB_DIR . 'includes/admin/class-flxb-approval.php';
	}
	if ( class_exists( 'FLXB_Approval' ) ) {
		foreach ( FLXB_Approval::receipts( $d, $order ) as $r ) {
			$out['receiptFiles'][] = array( 'name' => $r['name'], 'url' => $r['url'], 'kind' => $r['kind'], 'meta' => $r['meta'], 'warn' => $r['warn'] );
		}
	}
	$out['certs'] = array();
	$codes        = array_values( array_unique( array_filter( array_map( 'strval', array_merge( array( (string) $d['certificate_code'] ), (array) ( FLXB_Donations::meta( $d )['certificates'] ?? array() ) ) ) ) ) );
	foreach ( $codes as $code ) {
		$c = function_exists( 'flxs_get_certificate_by_code' ) ? flxs_get_certificate_by_code( $code ) : null;
		if ( $c ) {
			$out['certs'][] = array( 'number' => '' !== (string) $c['number'] ? (string) $c['number'] : (string) $c['code'], 'name' => (string) ( $c['data']['values']['ad_soyad'] ?? '' ), 'url' => function_exists( 'flxs_certificate_url' ) ? flxs_certificate_url( $c ) : '', 'revoked' => 'revoked' === ( $c['status'] ?? '' ) );
		}
	}
	$fmt          = function ( $p ) {
		$p = (string) $p;
		return '' !== $p && function_exists( 'flxb_format_phone' ) ? flxb_format_phone( $p ) : $p;
	};
	$donor        = (int) $d['donor_id'] ? FLXB_Donors::get( (int) $d['donor_id'] ) : null;
	$out['donor'] = $donor ? array(
		'name'  => (string) $donor['name'],
		'phone' => $fmt( $donor['phone'] ),
		'email' => (string) $donor['email'],
		'gifts' => (int) $donor['donation_count'],
		'total' => flxc_money( $donor['total_amount'], $d['currency'] ),
	) : null;
	$ref           = (int) ( $d['ref_user_id'] ?? 0 );
	$u             = $ref ? get_userdata( $ref ) : null;
	$out['refInfo'] = $u ? array( 'name' => (string) $u->display_name, 'phone' => $fmt( get_user_meta( $ref, 'billing_phone', true ) ) ) : null;
	$m             = FLXB_Donations::meta( $d );
	$by            = ! empty( $m['created_by'] ) ? get_userdata( (int) $m['created_by'] ) : null;
	$out['by']     = $by ? (string) $by->display_name : '';
	$out['note']   = (string) ( $m['note'] ?? '' );
	$out['canPay'] = 'pending' === $d['status'] && flxc_can_record_donation();
	return $out;
}

function flxc_certificate_for_js( array $c ) {
	$c      = isset( $c['data'] ) && is_array( $c['data'] ) ? $c : flxs_prepare_certificate( $c );
	$person = ! empty( $c['person_id'] ) ? flxs_get_person( (int) $c['person_id'] ) : null;
	$vals   = (array) ( $c['data']['values'] ?? array() );
	return array(
		'kind'     => 'certificate',
		'id'       => (int) $c['id'],
		'title'    => (string) $c['title'],
		'number'   => (string) $c['number'],
		'name'     => function_exists( 'flxs_certificate_name' ) ? flxs_certificate_name( $c, $person ) : ( $person ? $person['name'] : '' ),
		'phone'    => $person ? (string) $person['phone'] : '',
		'qty'      => isset( $vals['fidan_sayisi'] ) ? (int) $vals['fidan_sayisi'] : 0,
		'status'   => (string) $c['status'],
		'statusL'  => 'revoked' === $c['status'] ? __( 'Cancel', 'flx-chat' ) : '',
		'url'      => flxs_certificate_url( $c ),
		'files'    => '' !== (string) $c['pdf_file'] && '' !== (string) $c['image_file'] && '' !== (string) ( $c['data']['og'] ?? '' ),
		'event'    => function_exists( 'flxs_event_label' ) ? (string) flxs_event_label( $c['event_id'] ?? 0 ) : '',
		'date'     => flxc_local_date( $c['created_at'] ),
		'receipts' => flxc_receipt_count( 'certificate', (int) $c['id'] ),
		'admin'    => flxs_can_use() ? admin_url( 'admin.php?page=flxs&s=' . rawurlencode( (string) $c['number'] ) ) : '',
		// 1.46.0: satırda tıklamadan görünen özet (gönderim, görüntülenme, bağlı bağış).
		'views'    => (int) ( $c['views'] ?? 0 ),
		'sends'    => flxc_cert_sends( $c, true ),
		'donation' => flxc_cert_donation( $c ),
	);
}

/**
 * 1.46.0: sertifika gönderimleri (Flux Certificates kuyruğu + Çağrı Merkezi'nden gönderilenler), yeniden eskiye.
 * $summary true ise yalnız sayı: array( 'sent' => n, 'failed' => n, 'queued' => n ).
 */
function flxc_cert_sends( array $c, $summary = false ) {
	global $wpdb;
	$id  = (int) $c['id'];
	$out = array();
	if ( function_exists( 'flxs_table' ) ) {
		$mt   = flxs_table( 'messages' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT channel, recipient, recipient_name, status, sent_at, scheduled_at, created_at, last_error, source FROM $mt WHERE certificate_id = %d ORDER BY id DESC LIMIT 30", $id ), ARRAY_A ); // phpcs:ignore
		foreach ( (array) $rows as $r ) {
			$out[] = array(
				'channel' => (string) $r['channel'],
				'to'      => (string) ( '' !== (string) $r['recipient_name'] ? $r['recipient_name'] . ' · ' . $r['recipient'] : $r['recipient'] ),
				'status'  => (string) $r['status'],
				'at'      => (string) ( $r['sent_at'] ? $r['sent_at'] : ( $r['scheduled_at'] ? $r['scheduled_at'] : $r['created_at'] ) ),
				'error'   => (string) $r['last_error'],
				'via'     => 'certificates',
			);
		}
		$lt   = flxs_table( 'logs' );
		$logs = $wpdb->get_results( $wpdb->prepare( "SELECT created_at, user_id, context FROM $lt WHERE object_type = 'certificate' AND object_id = %d AND action = 'certificate_sent' ORDER BY id DESC LIMIT 30", $id ), ARRAY_A ); // phpcs:ignore
		foreach ( (array) $logs as $l ) {
			$ctx   = json_decode( (string) $l['context'], true );
			$u     = (int) $l['user_id'] ? get_userdata( (int) $l['user_id'] ) : null;
			$out[] = array(
				'channel' => (string) ( $ctx['kanal'] ?? 'whatsapp' ),
				'to'      => (string) ( $ctx['alici'] ?? '' ),
				'status'  => 'sent',
				'at'      => (string) $l['created_at'],
				'error'   => '',
				'via'     => 'chat',
				'by'      => $u ? (string) $u->display_name : '',
			);
		}
	}
	if ( $summary ) {
		$n = array( 'sent' => 0, 'failed' => 0, 'queued' => 0 );
		foreach ( $out as $o ) {
			$k = 'sent' === $o['status'] ? 'sent' : ( 'failed' === $o['status'] ? 'failed' : ( in_array( $o['status'], array( 'queued', 'processing' ), true ) ? 'queued' : '' ) );
			if ( $k ) {
				++$n[ $k ];
			}
		}
		return $n;
	}
	usort(
		$out,
		static function ( $a, $b ) {
			return strcmp( $b['at'], $a['at'] );
		}
	);
	$labels = function_exists( 'flxs_status_labels' ) ? flxs_status_labels() : array();
	foreach ( $out as &$o ) {
		$o['channelL'] = function_exists( 'flxs_channel_label' ) ? (string) flxs_channel_label( $o['channel'] ) : $o['channel'];
		$o['statusL']  = 'chat' === $o['via'] ? __( 'Sent from the chat', 'flx-chat' ) : (string) ( $labels[ $o['status'] ] ?? $o['status'] );
		$o['date']     = flxc_local_date( $o['at'] );
	}
	unset( $o );
	return $out;
}

/** 1.46.0: sertifikanın bağlı olduğu bağış (Flux Donate; certificate_code ya da çoklu sertifika listesi). */
function flxc_cert_donation( array $c ) {
	global $wpdb;
	if ( ! function_exists( 'flxc_flxb_on' ) || ! flxc_flxb_on() || ! function_exists( 'flxb_table' ) || '' === (string) $c['code'] ) {
		return null;
	}
	$t    = flxb_table( 'donations' );
	$code = (string) $c['code'];
	$id   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE certificate_code = %s AND status <> 'trash' ORDER BY id DESC LIMIT 1", $code ) ); // phpcs:ignore
	if ( ! $id ) {
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $t WHERE meta LIKE %s AND status <> 'trash' ORDER BY id DESC LIMIT 1", '%' . $wpdb->esc_like( '"' . $code . '"' ) . '%' ) ); // phpcs:ignore
	}
	$d = $id ? FLXB_Donations::get( $id ) : null;
	if ( ! $d ) {
		return null;
	}
	$j = flxc_donation_for_js( $d );
	return array_intersect_key( $j, array_flip( array( 'kind', 'id', 'title', 'qty', 'money', 'status', 'statusL', 'tone', 'method', 'name', 'phone', 'date', 'receipts', 'admin', 'ref', 'refDonor' ) ) );
}

/** 1.46.0: sertifika ayrıntısı (Bağış penceresi gibi): görsel, kişi, gönderimler, bağlı bağış, referans, değerler. */
function flxc_certificate_detail( array $c ) {
	$c      = isset( $c['data'] ) && is_array( $c['data'] ) ? $c : flxs_prepare_certificate( $c );
	$out    = flxc_certificate_for_js( $c );
	$person = ! empty( $c['person_id'] ) ? flxs_get_person( (int) $c['person_id'] ) : null;
	$fmt    = static function ( $p ) {
		$p = (string) $p;
		return '' !== $p && function_exists( 'flxb_format_phone' ) ? flxb_format_phone( $p ) : $p;
	};
	$ts             = strtotime( (string) $c['created_at'] . ' UTC' );
	$out['ago']     = $ts && function_exists( 'flx_time_ago' ) ? flx_time_ago( $ts ) : '';
	$out['code']    = (string) $c['code'];
	$out['image']   = function_exists( 'flxs_certificate_file_url' ) ? flxs_certificate_file_url( $c, 'image' ) : '';
	$out['pdf']     = function_exists( 'flxs_certificate_pdf_url' ) ? flxs_certificate_pdf_url( $c ) : '';
	$out['imageW']  = (int) ( $c['image_w'] ?? 0 );
	$out['imageH']  = (int) ( $c['image_h'] ?? 0 );
	$out['person']  = $person ? array(
		'name'  => (string) $person['name'],
		'phone' => $fmt( $person['phone'] ?? '' ),
		'email' => (string) ( $person['email'] ?? '' ),
	) : null;
	$by             = (int) ( $c['created_by'] ?? 0 ) ? get_userdata( (int) $c['created_by'] ) : null;
	$out['by']      = $by ? (string) $by->display_name : '';
	$ref            = (int) ( $c['ref_user_id'] ?? 0 ) ? get_userdata( (int) $c['ref_user_id'] ) : null;
	$out['refInfo'] = $ref ? array( 'name' => (string) $ref->display_name, 'phone' => $fmt( get_user_meta( $ref->ID, 'billing_phone', true ) ) ) : null;
	$out['sendList'] = flxc_cert_sends( $c );
	$skip           = array( 'ad_soyad' => 1, 'fidan_sayisi' => 1 );
	$out['values']  = array();
	foreach ( (array) ( $c['data']['values'] ?? array() ) as $k => $v ) {
		if ( isset( $skip[ $k ] ) || is_array( $v ) || '' === trim( (string) $v ) || mb_strlen( (string) $v ) > 200 ) {
			continue;
		}
		$out['values'][] = array( 'k' => ucfirst( str_replace( '_', ' ', (string) $k ) ), 'v' => (string) $v );
	}
	return $out;
}

/* ---------- arama ---------- */

function flxc_rest_records_search( WP_REST_Request $req ) {
	$kind = 'certificate' === $req->get_param( 'kind' ) ? 'certificate' : 'donation';
	$q    = trim( sanitize_text_field( (string) $req->get_param( 'q' ) ) );
	$page = max( 1, (int) $req->get_param( 'page' ) );
	$stat = sanitize_key( (string) $req->get_param( 'status' ) );
	$per  = 20;
	if ( 'donation' === $kind ) {
		if ( ! flxc_flxb_on() ) {
			return array( 'items' => array(), 'total' => 0 );
		}
		$args = array( 'per_page' => $per, 'page' => $page, 'status' => $stat );
		if ( '' !== $q ) {
			$digits         = preg_replace( '/\D+/', '', $q );
			$args['search'] = strlen( $digits ) >= 7 && strlen( $digits ) >= strlen( preg_replace( '/\s+/', '', $q ) ) - 2 ? ltrim( $digits, '0' ) : $q;
		}
		$res   = FLXB_Donations::query( $args );
		$rows  = (array) ( $res['items'] ?? array() );
		$items = array_map( 'flxc_donation_for_js', $rows );
		return array( 'items' => $items, 'total' => (int) ( $res['total'] ?? count( $items ) ), 'more' => $page * $per < (int) ( $res['total'] ?? 0 ) );
	}
	if ( ! flxc_flxs_on() ) {
		return array( 'items' => array(), 'total' => 0 );
	}
	$res   = flxs_query_certificates( array( 'search' => $q, 'per_page' => $per, 'page' => $page ) );
	$items = array_map( 'flxc_certificate_for_js', (array) ( $res['items'] ?? array() ) );
	return array( 'items' => $items, 'total' => (int) $res['total'], 'more' => $page * $per < (int) $res['total'] );
}

/** Sohbetteki kişinin bağış ve sertifikaları (telefon ile eşleşir). */
function flxc_records_for_phone( $phone ) {
	$out = array( 'donations' => array(), 'certificates' => array(), 'donor' => null );
	if ( '' === (string) $phone ) {
		return $out;
	}
	if ( flxc_flxb_on() ) {
		// 1.9.2: numara farklı yazılmış olabilir (+90…, 0…, boşluklu); tüm biçimler ve WooCommerce sipariş telefonu eşleştirilir.
		global $wpdb;
		$vars = flxc_phone_variants( $phone );
		$ph   = implode( ',', array_fill( 0, count( $vars ), '%s' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . flxb_table( 'donors' ) . " WHERE REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(','') IN ($ph) ORDER BY id ASC LIMIT 5", $vars ), ARRAY_A ); // phpcs:ignore
		$ids  = array();
		foreach ( (array) $rows as $donor ) {
			if ( ! $out['donor'] ) {
				$out['donor'] = array( 'id' => (int) $donor['id'], 'name' => (string) $donor['name'], 'email' => (string) $donor['email'] );
			}
			$res = FLXB_Donations::query( array( 'donor_id' => (int) $donor['id'], 'per_page' => 15 ) );
			foreach ( (array) ( $res['items'] ?? array() ) as $d ) {
				$ids[ (int) $d['id'] ] = $d;
			}
		}
		if ( function_exists( 'wc_get_orders' ) && function_exists( 'flxb_order_donation_ids' ) ) {
			foreach ( $vars as $v ) {
				foreach ( (array) wc_get_orders( array( 'billing_phone' => $v, 'limit' => 15, 'return' => 'objects' ) ) as $order ) {
					foreach ( flxb_order_donation_ids( $order ) as $did ) {
						if ( $did && ! isset( $ids[ $did ] ) ) {
							$d = FLXB_Donations::get( $did );
							if ( $d ) {
								$ids[ $did ] = $d;
							}
						}
					}
				}
			}
		}
		krsort( $ids );
		foreach ( array_slice( $ids, 0, 15 ) as $d ) {
			$out['donations'][] = flxc_donation_for_js( $d );
		}
	}
	if ( flxc_flxs_on() && function_exists( 'flxs_find_person_by_phone' ) ) {
		$p = flxs_find_person_by_phone( $phone );
		if ( $p ) {
			$res = flxs_query_certificates( array( 'person_id' => (int) $p['id'], 'per_page' => 15 ) );
			foreach ( (array) ( $res['items'] ?? array() ) as $c ) {
				$out['certificates'][] = flxc_certificate_for_js( $c );
			}
			if ( ! $out['donor'] ) {
				$out['donor'] = array( 'id' => 0, 'name' => (string) $p['name'], 'email' => (string) $p['email'] );
			}
		}
	}
	// Bağışın sertifikası kişinin sertifikası sayılır (sertifikadaki kişi kaydı farklı telefonla açılmış olabilir).
	if ( flxc_flxs_on() && function_exists( 'flxs_get_certificate_by_code' ) ) {
		$have = wp_list_pluck( $out['certificates'], 'id' );
		foreach ( $out['donations'] as $d ) {
			if ( '' !== $d['cert'] ) {
				$c = flxs_get_certificate_by_code( $d['cert'] );
				if ( $c && ! in_array( (int) $c['id'], $have, true ) ) {
					$out['certificates'][] = flxc_certificate_for_js( $c );
					$have[]                = (int) $c['id'];
				}
			}
		}
	}
	return $out;
}

/** Bir telefonun kayıtlarda geçebilecek yazılışları: 905321234567, +905321234567, 05321234567, 5321234567. */
function flxc_phone_variants( $phone ) {
	$d = preg_replace( '/\D+/', '', (string) $phone );
	if ( '' === $d ) {
		return array( '' );
	}
	$out = array( $d, '+' . $d );
	if ( 0 === strpos( $d, '90' ) && 12 === strlen( $d ) ) {
		$local = substr( $d, 2 );
		$out[] = '0' . $local;
		$out[] = $local;
	}
	return array_values( array_unique( $out ) );
}

function flxc_rest_records_for_chat( WP_REST_Request $req ) {
	$chat = flxc_rest_chat_or_error( $req['id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$out = flxc_records_for_phone( $chat['phone'] );
	// 1.15.0: gizli numarada (ya da başka numarayla açılmış) bu sohbette açılan bağış da listelenir.
	$state = function_exists( 'flxc_bot_state' ) ? flxc_bot_state( $chat ) : array();
	if ( ! empty( $state['donation'] ) && flxc_flxb_on() && ! in_array( (int) $state['donation'], wp_list_pluck( $out['donations'], 'id' ), true ) ) {
		$d = FLXB_Donations::get( (int) $state['donation'] );
		if ( $d ) {
			array_unshift( $out['donations'], flxc_donation_for_js( $d ) );
		}
	}
	return $out;
}

/* ---------- bağış oluşturma ---------- */

function flxc_rest_create_donation( WP_REST_Request $req ) {
	if ( ! flxc_can_record_donation() ) {
		return new WP_Error( 'flxc_forbidden', __( 'You are not allowed to create donation records.', 'flx-chat' ), array( 'status' => 403 ) );
	}
	$p    = (array) $req->get_json_params();
	$chat = ! empty( $p['chat'] ) ? flxc_rest_chat_or_error( (int) $p['chat'] ) : null;
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$p['user_id'] = get_current_user_id();
	return flxc_create_donation( $p, $chat );
}

/**
 * Bağış kaydı oluşturur (panel ve asistan ortak).
 *
 * @param array      $p    type_id, option_key, quantity, amount, status, method, name, phone, email, beneficiaries,
 *                         beneficiary_contacts, note, reference, receipt, certificate, message, user_id, source_detail
 * @param array|null $chat
 * @return array|WP_Error
 */
function flxc_create_donation( array $p, $chat = null ) {
	$uid  = (int) ( $p['user_id'] ?? 0 );
	$num  = function ( $v ) {
		return round( (float) str_replace( ',', '.', (string) $v ), 2 );
	};
	$type = ! empty( $p['type_id'] ) ? FLXB_Types::get( absint( $p['type_id'] ) ) : null;
	$key  = sanitize_key( (string) ( $p['option_key'] ?? '' ) );
	$opt  = $type && '' !== $key ? FLXB_Types::option( $type, $key ) : null;
	$qty  = max( 1, absint( $p['quantity'] ?? 1 ) );
	if ( '' !== $key && ! $opt ) {
		return new WP_Error( 'flxc_opt', __( 'Choose an option of the selected donation type.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	$currency = $type ? (string) $type['currency'] : ( function_exists( 'flxb_default_currency' ) ? flxb_default_currency() : 'TRY' );
	$method   = sanitize_key( (string) ( $p['method'] ?? 'bank_transfer' ) );
	$method   = isset( flxc_donation_methods()[ $method ] ) ? $method : 'other';
	$status   = 'pending' === ( $p['status'] ?? '' ) ? 'pending' : 'completed';
	$meta     = array( 'created_by' => $uid, 'method' => $method, 'source_detail' => sanitize_key( (string) ( $p['source_detail'] ?? 'flx-chat' ) ) );
	if ( ! empty( $p['beneficiary_contacts'] ) && is_array( $p['beneficiary_contacts'] ) ) {
		$meta['beneficiary_contacts'] = array_values( $p['beneficiary_contacts'] );
	}
	if ( $chat ) {
		$meta['flxc_chat'] = (int) $chat['id'];
	}
	if ( $opt ) {
		$unit = (float) $opt['price'];
		if ( $unit <= 0 ) {
			return new WP_Error( 'flxc_price', __( 'This option has no price. Enter a price under Donations › Donation types, or choose a custom amount.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		$amount = round( $unit * $qty, 2 );
		$lines  = array( array( 'key' => $opt['key'], 'label' => $opt['label'], 'units' => $qty, 'unit_price' => $unit, 'amount' => $amount, 'unit' => $opt['unit'] ?? '', 'units_per' => (int) ( $opt['units_per'] ?? 1 ) ) );
	} else {
		$amount = $num( $p['amount'] ?? 0 );
		$unit   = $amount;
		$qty    = 1;
		$lines  = array( array( 'key' => '', 'label' => __( 'Custom amount', 'flx-chat' ), 'units' => 1, 'unit_price' => $amount, 'amount' => $amount, 'unit' => '', 'units_per' => 1 ) );
	}
	if ( $amount <= 0 ) {
		return new WP_Error( 'flxc_amount', __( 'Enter an amount.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	$names = function_exists( 'flxb_split_names' ) ? flxb_split_names( (string) ( $p['beneficiaries'] ?? '' ) ) : array();
	if ( $type && FLXB_Types::requires_proxy( $type ) ) {
		$text = function_exists( 'flxb_proxy_text' ) ? flxb_proxy_text() : null;
		if ( ! $text || empty( $p['proxy_consent'] ) ) {
			return new WP_Error( 'flxc_proxy', __( 'This donation type requires the donor\'s proxy consent.', 'flx-chat' ), array( 'status' => 400 ) );
		}
		$meta['proxy_consent'] = array( 'version' => (int) $text['version'], 'accepted_at' => flxb_now(), 'source' => 'flx-chat' );
	}
	$ref = sanitize_text_field( (string) ( $p['reference'] ?? '' ) );
	if ( '' !== $ref ) {
		$meta['reference'] = $ref;
	}
	$note = sanitize_textarea_field( (string) ( $p['note'] ?? '' ) );
	if ( '' !== $note ) {
		$meta['note'] = $note;
	}
	$phone = (string) ( $p['phone'] ?? ( $chat ? $chat['phone'] : '' ) );
	$phone = '' !== $phone ? '+' . ltrim( flxc_normalize_phone( $phone ), '+' ) : '';
	$name  = sanitize_text_field( (string) ( $p['name'] ?? ( $chat ? flxc_chat_title( $chat ) : '' ) ) );
	$donor = array(
		'name'            => $name,
		'phone'           => '+' === $phone ? '' : $phone,
		'email'           => sanitize_email( (string) ( $p['email'] ?? '' ) ),
		'allow_anonymous' => true,
	);
	$id = FLXB_Donations::record(
		array(
			// Personel aracı (1.13.0) referansı açıkça verir; yoksa Flux Donate oluşturandan çözer.
			'ref_user_id'   => (int) ( $p['ref_user_id'] ?? 0 ),
			'uniq'          => 'flxc:' . wp_generate_uuid4(),
			'provider'      => $method,
			'provider_ref'  => $ref,
			'amount'        => $amount,
			'currency'      => $currency,
			'type'          => 'one_time',
			'status'        => $status,
			'donor_id'      => FLXB_Donors::upsert( $donor ),
			'type_id'       => $type ? (int) $type['id'] : 0,
			'option_key'    => $opt ? $opt['key'] : '',
			'option_label'  => $opt ? $opt['label'] : '',
			'quantity'      => $qty,
			'unit_price'    => $unit,
			'source'        => 'callcenter',
			'meta'          => $meta,
			'lines'         => $lines,
			'beneficiaries' => $names,
		)
	);
	if ( ! $id ) {
		return new WP_Error( 'flxc_save', __( 'The donation could not be saved.', 'flx-chat' ), array( 'status' => 500 ) );
	}
	$d   = FLXB_Donations::get( $id );
	$out = array( 'donation' => flxc_donation_for_js( $d ) );

	// Bu kayıtla birlikte gelen dekont.
	if ( ! empty( $p['receipt'] ) ) {
		$r = flxc_attach_receipt( (int) $p['receipt'], 'donation', $id, false );
		if ( ! is_wp_error( $r ) ) {
			$out['receipt'] = $r;
		}
	}
	// İstenirse sertifika da.
	if ( ! empty( $p['certificate'] ) && is_array( $p['certificate'] ) && flxc_can_record_certificate() ) {
		$c = flxc_make_certificate(
			array_merge(
				(array) $p['certificate'],
				array( 'name' => $name, 'phone' => $phone, 'email' => $donor['email'] )
			)
		);
		if ( is_wp_error( $c ) ) {
			$out['certError'] = $c->get_error_message();
		} else {
			FLXB_Donations::set_certificate( $id, $c['code'] );
			$out['certificate'] = flxc_certificate_for_js( $c );
			$out['donation']    = flxc_donation_for_js( FLXB_Donations::get( $id ) );
		}
	}
	if ( $chat && ! empty( $p['message'] ) ) {
		$text = flxc_fill_donation_vars( (string) $p['message'], $chat, $d );
		$sent = flxc_send( $chat, array( 'type' => 'text', 'body' => $text, 'source' => 'record', 'user_id' => $uid ) );
		if ( is_wp_error( $sent ) ) {
			$out['sendError'] = $sent->get_error_message();
		} else {
			$out['sent'] = flxc_message_for_js( $sent );
		}
	}
	if ( $chat ) {
		/* translators: 1: donation summary, 2: amount */
		flxc_system_message( $chat, sprintf( __( 'Donation record created: %1$s · %2$s', 'flx-chat' ), $out['donation']['title'], $out['donation']['money'] ) );
	}
	return $out;
}

function flxc_fill_donation_vars( $text, $chat, array $d ) {
	$js   = flxc_donation_for_js( $d );
	$what = $js['title'] . ( $js['qty'] > 1 ? ' × ' . number_format_i18n( $js['qty'] ) : '' );
	$text = strtr(
		(string) $text,
		array(
			'{donation}' => $what,
			'{amount}'   => $js['money'],
			'{quantity}' => number_format_i18n( $js['qty'] ),
			'{bank}'     => flxc_bank_text(),
			'{certificate_link}' => '' !== $js['cert'] && function_exists( 'flxs_certificate_url' ) ? flxs_certificate_url( $js['cert'] ) : '',
		)
	);
	return trim( preg_replace( "/\n{3,}/", "\n\n", flxc_fill_vars( $text, $chat ) ) );
}

/** Bekleyen bağışı onayla (dekont geldiğinde). */
function flxc_rest_donation_status( WP_REST_Request $req ) {
	if ( ! flxc_can_record_donation() ) {
		return new WP_Error( 'flxc_forbidden', __( 'You are not allowed to do this.', 'flx-chat' ), array( 'status' => 403 ) );
	}
	$d = FLXB_Donations::get( (int) $req['id'] );
	if ( ! $d ) {
		return new WP_Error( 'flxc_404', __( 'Donation not found.', 'flx-chat' ), array( 'status' => 404 ) );
	}
	$st = 'completed' === $req->get_param( 'status' ) ? 'completed' : '';
	if ( ! $st ) {
		return new WP_Error( 'flxc_status', 'Invalid status', array( 'status' => 400 ) );
	}
	if ( FLXB_Donations::set_status_by_uniq( $d['uniq'], $st ) ) {
		FLXB_Donations::add_audit( (int) $d['id'], 'status', array( 'status' => array( $d['status'], $st ), 'via' => 'flx-chat' ) );
	}
	return array( 'donation' => flxc_donation_for_js( FLXB_Donations::get( (int) $d['id'] ) ) );
}

/* ---------- sertifika ---------- */

/**
 * @param array $a template_id, values, event_id, name, phone, email
 * @return array|WP_Error sertifika
 */
function flxc_make_certificate( array $a ) {
	$tpl_id = (int) ( $a['template_id'] ?? 0 );
	if ( ! $tpl_id ) {
		$list   = flxs_list_templates( true );
		$tpl_id = $list ? (int) $list[0]['id'] : 0;
	}
	$tpl = $tpl_id ? flxs_get_template( $tpl_id ) : null;
	if ( ! $tpl ) {
		return new WP_Error( 'flxc_tpl', __( 'Certificate template not found.', 'flx-chat' ) );
	}
	$values = array();
	foreach ( (array) ( $a['values'] ?? array() ) as $k => $v ) {
		$values[ sanitize_key( $k ) ] = is_scalar( $v ) ? (string) $v : '';
	}
	$name = trim( sanitize_text_field( (string) ( $values['ad_soyad'] ?? ( $a['name'] ?? '' ) ) ) );
	if ( '' === $name ) {
		return new WP_Error( 'flxc_name', __( 'A full name is required for the certificate.', 'flx-chat' ) );
	}
	$values['ad_soyad'] = $name;
	$person             = flxs_upsert_person( $name, (string) ( $a['phone'] ?? '' ), (string) ( $a['email'] ?? '' ) );
	if ( is_wp_error( $person ) ) {
		return $person;
	}
	$cert = flxs_create_certificate( $tpl_id, (int) $person['person']['id'], $values, get_current_user_id() );
	if ( is_wp_error( $cert ) ) {
		return $cert;
	}
	if ( ! empty( $a['event_id'] ) && function_exists( 'flxs_set_certificate_event' ) ) {
		flxs_set_certificate_event( (int) $cert['id'], (int) $a['event_id'] );
		$cert = flxs_get_certificate( (int) $cert['id'] );
	}
	return $cert;
}

function flxc_rest_create_certificate( WP_REST_Request $req ) {
	if ( ! flxc_can_record_certificate() ) {
		return new WP_Error( 'flxc_forbidden', __( 'You are not allowed to create certificates.', 'flx-chat' ), array( 'status' => 403 ) );
	}
	$p    = (array) $req->get_json_params();
	$chat = ! empty( $p['chat'] ) ? flxc_rest_chat_or_error( (int) $p['chat'] ) : null;
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$phone = (string) ( $p['phone'] ?? ( $chat ? $chat['phone'] : '' ) );
	$cert  = flxc_make_certificate(
		array(
			'template_id' => (int) ( $p['template_id'] ?? 0 ),
			'values'      => (array) ( $p['values'] ?? array() ),
			'event_id'    => (int) ( $p['event_id'] ?? 0 ),
			'name'        => (string) ( $p['name'] ?? '' ),
			'phone'       => $phone,
			'email'       => (string) ( $p['email'] ?? '' ),
		)
	);
	if ( is_wp_error( $cert ) ) {
		$cert->add_data( array( 'status' => 400 ) );
		return $cert;
	}
	if ( ! empty( $p['donation_id'] ) && flxc_flxb_on() ) {
		FLXB_Donations::set_certificate( (int) $p['donation_id'], $cert['code'] );
	}
	if ( ! empty( $p['receipt'] ) ) {
		flxc_attach_receipt( (int) $p['receipt'], 'certificate', (int) $cert['id'], false );
	}
	if ( $chat ) {
		/* translators: %s: certificate number */
		flxc_system_message( $chat, sprintf( __( 'Certificate created: %s', 'flx-chat' ), $cert['number'] ) );
	}
	return array( 'certificate' => flxc_certificate_for_js( $cert ) );
}

function flxc_rest_get_certificate( WP_REST_Request $req ) {
	if ( ! flxc_flxs_on() ) {
		return new WP_Error( 'flxc_404', 'Not found', array( 'status' => 404 ) );
	}
	$c = flxs_get_certificate( (int) $req['id'] );
	if ( ! $c ) {
		return new WP_Error( 'flxc_404', 'Not found', array( 'status' => 404 ) );
	}
	$out = array( 'certificate' => $req->get_param( 'detail' ) ? flxc_certificate_detail( $c ) : flxc_certificate_for_js( $c ) );
	// İstenirse Flux Certificates'daki WhatsApp şablonuyla hazırlanmış mesaj (taslak olarak kutuya konur).
	if ( $req->get_param( 'message' ) ) {
		$chat = $req->get_param( 'chat' ) ? flxc_get_chat( (int) $req->get_param( 'chat' ) ) : null;
		$m    = flxs_build_certificate_message( $c, 'whatsapp', $chat && $chat['phone'] ? array( 'to' => $chat['phone'] ) : array() );
		$out['message'] = is_wp_error( $m ) ? flxs_certificate_url( $c ) : (string) $m['body'];
	}
	return $out;
}

/** Sertifika mesajını (Flux Certificates'daki WhatsApp metni) sohbete gönderir. */
function flxc_rest_send_certificate( WP_REST_Request $req ) {
	if ( ! flxc_flxs_on() ) {
		return new WP_Error( 'flxc_404', 'Not found', array( 'status' => 404 ) );
	}
	$c    = flxs_get_certificate( (int) $req['id'] );
	$chat = flxc_rest_chat_or_error( (int) $req->get_param( 'chat' ) );
	if ( ! $c ) {
		return new WP_Error( 'flxc_404', __( 'Certificate not found.', 'flx-chat' ), array( 'status' => 404 ) );
	}
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$text = trim( (string) $req->get_param( 'text' ) );
	if ( '' === $text ) {
		$m    = flxs_build_certificate_message( $c, 'whatsapp', $chat['phone'] ? array( 'to' => $chat['phone'] ) : array() );
		$text = is_wp_error( $m ) ? flxs_certificate_url( $c ) : $m['body'];
	}
	$sent = flxc_send( $chat, array( 'type' => 'text', 'body' => $text, 'source' => 'certificate', 'user_id' => get_current_user_id() ) );
	if ( is_wp_error( $sent ) ) {
		return $sent;
	}
	if ( function_exists( 'flxs_log' ) ) {
		flxs_log( 'certificate_sent', 'certificate', (int) $c['id'], $c['number'], array( 'kanal' => 'whatsapp', 'kaynak' => 'flx-chat', 'alici' => $chat['phone'] ) );
	}
	return array( 'message' => flxc_message_for_js( $sent ) );
}

/* ---------- dekontlar ---------- */

function flxc_receipt_count( $kind, $ref_id ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxc_table( 'receipts' ) . ' WHERE kind = %s AND ref_id = %d', $kind, $ref_id ) ); // phpcs:ignore
}

function flxc_receipt_url( $message_id ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=flxc_receipt&m=' . (int) $message_id ), 'flxc_receipt_' . (int) $message_id );
}

/**
 * Mesajdaki dosyayı (dekont) bir bağışa ya da sertifikaya bağlar.
 * @return array|WP_Error
 */
function flxc_attach_receipt( $message_id, $kind, $ref_id, $mark_paid = false, $note = '' ) {
	global $wpdb;
	$m = flxc_get_message( $message_id );
	if ( ! $m || ! in_array( $m['type'], array( 'image', 'document' ), true ) ) {
		return new WP_Error( 'flxc_receipt', __( 'Only a photo or a document can be matched as a receipt.', 'flx-chat' ), array( 'status' => 400 ) );
	}
	$kind = 'certificate' === $kind ? 'certificate' : 'donation';
	if ( 'donation' === $kind ) {
		$d = flxc_flxb_on() ? FLXB_Donations::get( $ref_id ) : null;
		if ( ! $d ) {
			return new WP_Error( 'flxc_404', __( 'Donation not found.', 'flx-chat' ), array( 'status' => 404 ) );
		}
		$label = flxc_donation_for_js( $d )['title'];
	} else {
		$c = flxc_flxs_on() ? flxs_get_certificate( $ref_id ) : null;
		if ( ! $c ) {
			return new WP_Error( 'flxc_404', __( 'Certificate not found.', 'flx-chat' ), array( 'status' => 404 ) );
		}
		$label = (string) $c['number'];
	}
	// Dosya yerelde olsun (sonradan Evolution'dan silinse de kayıtta kalsın).
	flxc_message_media_path( $m );
	$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . flxc_table( 'receipts' ) . ' WHERE message_id = %d AND kind = %s AND ref_id = %d', $m['id'], $kind, $ref_id ) ); // phpcs:ignore
	if ( ! $exists ) {
		$wpdb->insert(
			flxc_table( 'receipts' ),
			array(
				'message_id' => (int) $m['id'],
				'chat_id'    => (int) $m['chat_id'],
				'kind'       => $kind,
				'ref_id'     => (int) $ref_id,
				'note'       => sanitize_text_field( $note ),
				'user_id'    => get_current_user_id(),
				'created_at' => flxc_now(),
			)
		);
	}
	$meta  = flxc_decode( $m['meta'] );
	$list  = array_values( array_filter( (array) ( $meta['receipts'] ?? array() ), function ( $r ) use ( $kind, $ref_id ) {
		return ! ( ( $r['k'] ?? '' ) === $kind && (int) ( $r['id'] ?? 0 ) === (int) $ref_id );
	} ) );
	$list[]           = array( 'k' => $kind, 'id' => (int) $ref_id, 'l' => $label );
	$meta['receipts'] = $list;
	flxc_update_message( $m['id'], array( 'meta' => $meta ) );

	if ( 'donation' === $kind ) {
		$dm   = FLXB_Donations::meta( $d );
		$recs = (array) ( $dm['receipts'] ?? array() );
		$rec    = array( 'url' => flxc_receipt_link( $m['id'] ), 'file' => (string) $m['media_name'], 'mime' => strtolower( explode( ';', (string) $m['media_mime'] )[0] ), 'at' => flxb_now(), 'user' => get_current_user_id(), 'source' => 'whatsapp' );
		// 1.26.1: Flux Donate ödeme onayı kartı önizleme türünü ve dekont tekrarı uyarısını buradan gösterir.
		$dup = (array) ( $meta['rcheck']['dup'] ?? array() );
		if ( $dup && function_exists( 'flxc_dekont_dup_text' ) ) {
			$rec['warn'] = __( 'This receipt was', 'flx-chat' ) . ' ' . flxc_dekont_dup_text( array( 'created_at' => $dup['at'] ?? '', 'source' => $dup['source'] ?? '' ) ) . ( ! empty( $dup['donation'] ) ? ' (' . __( 'donation', 'flx-chat' ) . ' #' . (int) $dup['donation'] . ')' : '' );
		}
		$recs[] = $rec;
		FLXB_Donations::update_meta( (int) $d['id'], array( 'receipts' => $recs ) );
		FLXB_Donations::add_audit( (int) $d['id'], 'receipt', array( 'message' => (int) $m['id'], 'via' => 'flx-chat' ) );
		if ( $mark_paid && 'pending' === $d['status'] ) {
			if ( FLXB_Donations::set_status_by_uniq( $d['uniq'], 'completed' ) ) {
				FLXB_Donations::add_audit( (int) $d['id'], 'status', array( 'status' => array( 'pending', 'completed' ), 'via' => 'flx-chat' ) );
			}
		}
		$rec = flxc_donation_for_js( FLXB_Donations::get( (int) $d['id'] ) );
	} else {
		$rec = flxc_certificate_for_js( flxs_get_certificate( $ref_id ) );
		if ( function_exists( 'flxs_log' ) ) {
			flxs_log( 'certificate_receipt', 'certificate', (int) $ref_id, $label, array( 'kaynak' => 'flx-chat', 'mesaj' => (int) $m['id'] ) );
		}
	}
	return array(
		'message' => flxc_message_for_js( flxc_get_message( $m['id'] ) ),
		'record'  => $rec,
	);
}

/** Kalıcı link (nonce'suz): yetki kontrolü indirmede yapılır; Flux Donate'taki kayıtta durur. */
function flxc_receipt_link( $message_id ) {
	return admin_url( 'admin-post.php?action=flxc_receipt&m=' . (int) $message_id );
}

function flxc_rest_attach_receipt( WP_REST_Request $req ) {
	$m = flxc_get_message( (int) $req['id'] );
	if ( ! $m ) {
		return new WP_Error( 'flxc_404', 'Not found', array( 'status' => 404 ) );
	}
	$chat = flxc_rest_chat_or_error( $m['chat_id'] );
	if ( is_wp_error( $chat ) ) {
		return $chat;
	}
	$kind = 'certificate' === $req->get_param( 'kind' ) ? 'certificate' : 'donation';
	if ( ( 'donation' === $kind && ! flxc_can_record_donation() ) || ( 'certificate' === $kind && ! flxc_can_record_certificate() ) ) {
		return new WP_Error( 'flxc_forbidden', __( 'You are not allowed to do this.', 'flx-chat' ), array( 'status' => 403 ) );
	}
	$r = flxc_attach_receipt( (int) $m['id'], $kind, (int) $req->get_param( 'ref' ), (bool) $req->get_param( 'paid' ), (string) $req->get_param( 'note' ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	/* translators: 1: record title */
	flxc_system_message( $chat, sprintf( __( 'Receipt matched: %s', 'flx-chat' ), 'donation' === $kind ? $r['record']['title'] . ' · ' . $r['record']['money'] : $r['record']['number'] ) );
	$text = trim( (string) $req->get_param( 'reply' ) );
	if ( '' !== $text ) {
		$d    = 'donation' === $kind ? FLXB_Donations::get( (int) $req->get_param( 'ref' ) ) : null;
		$text = $d ? flxc_fill_donation_vars( $text, $chat, $d ) : flxc_fill_vars( $text, $chat );
		$sent = flxc_send( $chat, array( 'type' => 'text', 'body' => $text, 'source' => 'record', 'user_id' => get_current_user_id() ) );
		if ( ! is_wp_error( $sent ) ) {
			$r['sent'] = flxc_message_for_js( $sent );
		}
	}
	return $r;
}

function flxc_rest_detach_receipt( WP_REST_Request $req ) {
	global $wpdb;
	$m = flxc_get_message( (int) $req['id'] );
	if ( ! $m || is_wp_error( flxc_rest_chat_or_error( $m['chat_id'] ) ) ) {
		return new WP_Error( 'flxc_404', 'Not found', array( 'status' => 404 ) );
	}
	$kind = 'certificate' === $req->get_param( 'kind' ) ? 'certificate' : 'donation';
	$ref  = (int) $req->get_param( 'ref' );
	$wpdb->delete( flxc_table( 'receipts' ), array( 'message_id' => (int) $m['id'], 'kind' => $kind, 'ref_id' => $ref ) );
	$meta             = flxc_decode( $m['meta'] );
	$meta['receipts'] = array_values( array_filter( (array) ( $meta['receipts'] ?? array() ), function ( $r ) use ( $kind, $ref ) {
		return ! ( ( $r['k'] ?? '' ) === $kind && (int) ( $r['id'] ?? 0 ) === $ref );
	} ) );
	flxc_update_message( $m['id'], array( 'meta' => $meta ) );
	return array( 'message' => flxc_message_for_js( flxc_get_message( $m['id'] ) ) );
}

/** Dekont dosyası: Çağrı Merkezi ya da Flux Donate yetkisi olan kullanıcıya gösterilir. */
function flxc_receipt_download() {
	$id = isset( $_GET['m'] ) ? (int) $_GET['m'] : 0; // phpcs:ignore
	$m  = $id ? flxc_get_message( $id ) : null;
	$ok = flxc_can_use() || ( function_exists( 'flxb_can_view' ) && flxb_can_view() );
	if ( ! $m || ! $ok ) {
		wp_die( esc_html__( 'You are not allowed to view this file.', 'flx-chat' ), '', array( 'response' => 403 ) );
	}
	global $wpdb;
	$linked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxc_table( 'receipts' ) . ' WHERE message_id = %d', $id ) ); // phpcs:ignore
	if ( ! $linked && ! flxc_can_use() ) {
		wp_die( esc_html__( 'You are not allowed to view this file.', 'flx-chat' ), '', array( 'response' => 403 ) );
	}
	$path = flxc_message_media_path( $m );
	if ( is_wp_error( $path ) ) {
		wp_die( esc_html( $path->get_error_message() ), '', array( 'response' => 404 ) );
	}
	$m = flxc_get_message( $id );
	flxc_stream_file( $path, $m['media_mime'], $m['media_name'], false );
	exit;
}

/* ---------- kişi paneli ---------- */

add_filter( 'flxc_contact_sections', 'flxc_records_contact_sections', 5, 2 );
function flxc_records_contact_sections( $sections, $chat ) {
	if ( ! flxc_flxb_on() || '' === (string) $chat['phone'] ) {
		return $sections;
	}
	$recs  = flxc_records_for_phone( $chat['phone'] );
	$items = array();
	foreach ( array_slice( $recs['donations'], 0, 10 ) as $d ) {
		$items[] = array(
			'label' => $d['title'] . ( $d['qty'] > 1 ? ' × ' . number_format_i18n( $d['qty'] ) : '' ),
			'sub'   => trim( $d['money'] . ' · ' . $d['statusL'] . ' · ' . $d['date'], ' ·' ),
			'url'   => $d['admin'],
			'tone'  => $d['tone'],
		);
	}
	if ( $items ) {
		array_unshift(
			$sections,
			array(
				'id'    => 'flxb',
				'title' => __( 'Donations', 'flx-chat' ),
				'items' => $items,
			)
		);
	}
	return $sections;
}
