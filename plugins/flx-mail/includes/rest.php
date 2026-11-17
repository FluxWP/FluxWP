<?php
/**
 * Yönetim ekranlarının REST uçları (yalnız yöneticiler).
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	function () {
		$ns   = 'flxi/v1';
		$perm = function () {
			return flxi_can_manage();
		};
		register_rest_route( $ns, '/preview', array( 'methods' => 'POST', 'callback' => 'flxi_rest_preview', 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/preview-pdf', array( 'methods' => 'POST', 'callback' => 'flxi_rest_preview_pdf', 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/test-email', array( 'methods' => 'POST', 'callback' => 'flxi_rest_test_email', 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/queue/run', array( 'methods' => 'POST', 'callback' => 'flxi_rest_queue_run', 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/recipients', array( 'methods' => 'POST', 'callback' => 'flxi_rest_recipients', 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/connection-test', array( 'methods' => 'POST', 'callback' => 'flxi_rest_connection_test', 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/log', array( 'methods' => 'GET', 'callback' => 'flxi_rest_log_list', 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/log/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => 'flxi_rest_log_item', 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/dns', array( 'methods' => 'POST', 'callback' => 'flxi_rest_dns', 'permission_callback' => $perm ) );
	}
);

/** Önizlemenin PDF'i (base64). Koyu mod seçiliyse e-postanın koyu kuralları uygulanır. */
function flxi_rest_preview_pdf( WP_REST_Request $r ) {
	return FLXI_Delivery_Service::preview_pdf(
		array(
			'key'    => $r['key'],
			'fields' => $r['fields'],
			'dark'   => $r['dark'],
		)
	);
}

/** Düzenleme formundaki (kaydedilmemiş) alanlarla önizleme. */
function flxi_rest_preview( WP_REST_Request $r ) {
	return FLXI_Delivery_Service::preview(
		array(
			'key'    => $r['key'],
			'fields' => $r['fields'],
		)
	);
}

/** Test e-postası: şablon (örnek değerlerle) ya da ayar testi. */
function flxi_rest_test_email( WP_REST_Request $r ) {
	return FLXI_Delivery_Service::test_email(
		array(
			'to'     => $r['to'],
			'key'    => $r['key'],
			'sender' => $r['sender'],
			'free'   => $r['free'],
		)
	);
}

function flxi_rest_queue_run( WP_REST_Request $r ) {
	$stats = flxi_run_queue( 15 );
	if ( ! flx_background_worker_active() ) { flxi_netgsm_poll(); }
	$cid   = (int) $r['campaign'];
	if ( $cid ) {
		$c              = flxi_get_campaign( $cid );
		$stats['campaign'] = $c ? array_intersect_key( $c, array_flip( array( 'id', 'status', 'total', 'sent', 'failed', 'skipped' ) ) ) : null;
	}
	$paused = (int) get_option( 'flxi_quota_paused' );
	if ( $paused > time() ) {
		$stats['paused_until'] = $paused;
		/* translators: %s: time */
		$stats['paused_text'] = sprintf( __( 'The mailbox\'s daily sending limit has been reached. Sending will resume automatically around %s.', 'flx-mail' ), flx_date( $paused, 'time' ) . ', ' . flx_date( $paused, 'short' ) );
	}
	return $stats;
}

function flxi_rest_recipients( WP_REST_Request $r ) {
	$report  = array();
	$channel = sanitize_key( (string) $r['channel'] );
	$list    = flxi_collect_recipients( array_map( 'sanitize_text_field', (array) $r['sources'] ), (string) $r['manual'], $channel, $report );
	$note    = '';
	if ( 'email' === (string) $r['channel'] && $list ) {
		$days = flxi_quota_days_for( count( $list ), 'noreply' === (string) $r['sender'] ? 'noreply' : 'info' );
		if ( $days ) {
			/* translators: 1: daily limit, 2: days */
			$note = sprintf( _n( 'Because of the daily limit of %1$s, sending all will take about %2$s day.', 'Because of the daily limit of %1$s, sending all will take about %2$s days.', $days, 'flx-mail' ), number_format_i18n( flxi_limits()['daily'] ), number_format_i18n( $days ) );
		}
	}
	return array(
		'quota_note' => $note,
		'count'  => count( $list ),
		'sample' => array_slice( $list, 0, 5 ),
		'why'    => flxi_recipient_report_lines( $report, $channel ),
	);
}

/**
 * Seçilen kaynaklardan neden kişi gelmediğini (ya da eksik geldiğini) satır satır anlatır:
 * adresi olmayanlar, kanala izin vermeyenler, başka listeden zaten eklenenler, abonelikten çıkanlar.
 *
 * @param array  $report  flxi_collect_recipients() dökümü.
 * @param string $channel email|whatsapp|sms
 * @return string[]
 */
function flxi_recipient_report_lines( array $report, $channel ) {
	$labels = flxi_recipient_sources();
	$lines  = array();
	$n      = 'number_format_i18n';
	foreach ( (array) ( $report['sources'] ?? array() ) as $src => $s ) {
		if ( ! $s['found'] && 'manual' === $src ) {
			continue;
		}
		$missing = $s['found'] - $s['added'];
		if ( $s['found'] && ! $missing ) {
			continue;
		}
		$label = 'manual' === $src ? __( 'Typed list', 'flx-mail' ) : preg_replace( '/\s*\([\d.,\s]+\)\s*$/u', '', (string) ( $labels[ $src ] ?? $src ) );
		$why   = array();
		if ( $s['no_address'] ) {
			if ( 'email' === $channel ) {
				/* translators: %s: kişi sayısı */
				$why[] = sprintf( _n( '%s has no email address', '%s have no email address', $s['no_address'], 'flx-mail' ), $n( $s['no_address'] ) );
			} else {
				/* translators: %s: kişi sayısı */
				$why[] = sprintf( _n( '%s has no valid phone number', '%s have no valid phone number', $s['no_address'], 'flx-mail' ), $n( $s['no_address'] ) );
			}
		}
		if ( $s['no_consent'] ) {
			$names = array( 'email' => __( 'email', 'flx-mail' ), 'whatsapp' => 'WhatsApp', 'sms' => 'SMS' );
			/* translators: 1: kişi sayısı, 2: kanal (e-posta, WhatsApp, SMS) */
			$why[] = sprintf( _n( '%1$s has not given %2$s permission', '%1$s have not given %2$s permission', $s['no_consent'], 'flx-mail' ), $n( $s['no_consent'] ), $names[ $channel ] ?? $channel );
		}
		if ( $s['duplicate'] ) {
			/* translators: %s: kişi sayısı */
			$why[] = sprintf( _n( '%s is already on the list', '%s are already on the list', $s['duplicate'], 'flx-mail' ), $n( $s['duplicate'] ) );
		}
		if ( ! $s['found'] ) {
			$why[] = __( 'this list is empty', 'flx-mail' );
		}
		/* translators: 1: liste adı, 2: alınan kişi sayısı, 3: listedeki kişi sayısı, 4: nedenler */
		$lines[] = sprintf( __( '%1$s: %2$s of %3$s added (%4$s).', 'flx-mail' ), $label, $n( $s['added'] ), $n( $s['found'] ), implode( '; ', $why ) );
	}
	if ( ! empty( $report['unsubscribed'] ) ) {
		/* translators: %s: kişi sayısı */
		$lines[] = sprintf( _n( '%s person unsubscribed earlier and was left out.', '%s people unsubscribed earlier and were left out.', (int) $report['unsubscribed'], 'flx-mail' ), $n( (int) $report['unsubscribed'] ) );
	}
	return $lines;
}

function flxi_rest_connection_test( WP_REST_Request $r ) {
	return FLXI_Connection_Service::test_rest( $r );
}

/** Gönderim kaydı listesi. */
function flxi_rest_log_list( WP_REST_Request $r ) {
	$res = rest_ensure_response( FLXI_Log_Service::rest_list( $r ) );
	$res->header( 'Cache-Control', 'no-store' );
	return $res;
}

function flxi_rest_log_item( WP_REST_Request $r ) {
	$row = FLXI_Log_Service::rest_item( $r );
	if ( is_wp_error( $row ) ) {
		return $row;
	}
	$res = rest_ensure_response( $row );
	$res->header( 'Cache-Control', 'no-store' );
	return $res;
}

/** Alan adının SPF / DMARC / MX kayıtları (yalnız düğmeyle; sonuç saklanır). */
function flxi_rest_dns() {
	return FLXI_System_Service::check_dns();
}

function flxi_dns_check() {
	$domain = substr( strrchr( (string) flxi_setting( 'from_email' ), '@' ), 1 );
	$domain = $domain ? $domain : flxi_site_domain();
	$out    = array( 'domain' => $domain, 'at' => time(), 'spf' => '', 'dmarc' => '', 'mx' => array(), 'error' => '' );
	if ( ! function_exists( 'dns_get_record' ) ) {
		$out['error'] = 'dns_get_record yok';
		return $out;
	}
	$txt = @dns_get_record( $domain, DNS_TXT ); // phpcs:ignore
	foreach ( (array) $txt as $t ) {
		$v = $t['txt'] ?? '';
		if ( 0 === stripos( $v, 'v=spf1' ) ) {
			$out['spf'] = $v;
		}
	}
	$dm = @dns_get_record( '_dmarc.' . $domain, DNS_TXT ); // phpcs:ignore
	foreach ( (array) $dm as $t ) {
		if ( 0 === stripos( $t['txt'] ?? '', 'v=DMARC1' ) ) {
			$out['dmarc'] = $t['txt'];
		}
	}
	$mx = @dns_get_record( $domain, DNS_MX ); // phpcs:ignore
	foreach ( (array) $mx as $m ) {
		$out['mx'][] = $m['target'] ?? '';
	}
	return $out;
}
