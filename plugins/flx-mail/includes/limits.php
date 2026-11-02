<?php
/**
 * Posta kutusu sınırları (Hostinger): günlük gönderim sayacı, toplu gönderimde kota, uyarılar.
 *
 * Sayaç kayıttan bağımsızdır (kayıt kapalı olsa da çalışır). Her başarılı gönderimde
 * alıcı sayısı (Kime + Bilgi + Gizli) gönderen posta kutusuna yazılır; son 24 saat toplanır.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

/** Paket sınırları (ayarlardan). */
function flxi_limits() {
	$s = flxi_settings();
	return array(
		'daily'      => max( 0, (int) $s['limit_daily'] ),
		'recipients' => max( 0, (int) $s['limit_recipients'] ),
		'size_mb'    => max( 0, (int) $s['limit_size_mb'] ),
		'attach_mb'  => max( 0, (int) $s['limit_attach_mb'] ),
		'storage_gb' => max( 0, (float) $s['limit_storage_gb'] ),
		'emails'     => max( 0, (int) $s['limit_mailbox_emails'] ),
		'aliases'    => max( 0, (int) $s['limit_aliases'] ),
		'forwarders' => max( 0, (int) $s['limit_forwarders'] ),
		'reserve'    => max( 0, (int) $s['limit_reserve'] ),
	);
}

/**
 * Gönderimin hangi posta kutusundan çıktığı (sınır posta kutusu başınadır).
 * SMTP'de oturum açılan hesap; değilse gönderen adresi.
 */
function flxi_quota_mailbox( $sender = 'info' ) {
	$s = flxi_settings();
	if ( flxi_smtp_ready() ) {
		$box = ( 'noreply' === $sender && '' !== trim( (string) $s['smtp_nr_user'] ) ) ? $s['smtp_nr_user'] : $s['smtp_user'];
		if ( '' !== trim( (string) $box ) ) {
			return strtolower( trim( $box ) );
		}
	}
	list( $email ) = flxi_sender_address( $sender );
	return strtolower( (string) $email );
}

/** Takip edilen posta kutuları: bilgi@ ve (ayrı hesapsa) no-reply@. */
function flxi_quota_mailboxes() {
	$out = array();
	foreach ( array( 'info', 'noreply' ) as $type ) {
		$box = flxi_quota_mailbox( $type );
		if ( $box && ! isset( $out[ $box ] ) ) {
			$out[ $box ] = $type;
		}
	}
	return $out;
}

function flxi_quota_data() {
	$d = get_option( 'flxi_quota', array() );
	return is_array( $d ) ? $d : array();
}

/** Son 24 saatte bu posta kutusundan giden alıcı sayısı. */
function flxi_quota_used( $box ) {
	$d    = flxi_quota_data();
	$from = time() - DAY_IN_SECONDS;
	$n    = 0;
	foreach ( (array) ( $d[ $box ] ?? array() ) as $hit ) {
		if ( (int) $hit[0] > $from ) {
			$n += (int) $hit[1];
		}
	}
	return $n;
}

/** Kalan hak (sınır 0 ise sınırsız: null). */
function flxi_quota_left( $box ) {
	$l = flxi_limits();
	if ( ! $l['daily'] ) {
		return null;
	}
	return max( 0, $l['daily'] - flxi_quota_used( $box ) );
}

/** En eski sayılan gönderimin 24 saati dolunca bir hak açılır. */
function flxi_quota_next_free( $box ) {
	$d    = flxi_quota_data();
	$from = time() - DAY_IN_SECONDS;
	$min  = 0;
	foreach ( (array) ( $d[ $box ] ?? array() ) as $hit ) {
		if ( (int) $hit[0] > $from && ( ! $min || (int) $hit[0] < $min ) ) {
			$min = (int) $hit[0];
		}
	}
	return $min ? $min + DAY_IN_SECONDS : time();
}

function flxi_quota_add( $box, $n ) {
	if ( ! $box || $n < 1 ) {
		return;
	}
	$d    = flxi_quota_data();
	$from = time() - DAY_IN_SECONDS;
	foreach ( $d as $k => $hits ) {
		$d[ $k ] = array_values(
			array_filter(
				(array) $hits,
				function ( $h ) use ( $from ) {
					return (int) $h[0] > $from;
				}
			)
		);
		if ( ! $d[ $k ] ) {
			unset( $d[ $k ] );
		}
	}
	$d[ $box ][] = array( time(), (int) $n );
	update_option( 'flxi_quota', $d, false );

	// Sınıra dayanılan günler (son 30 gün): üst pakete geçme önerisi için.
	$l = flxi_limits();
	if ( $l['daily'] && flxi_quota_used( $box ) >= $l['daily'] ) {
		$days = get_option( 'flxi_quota_full_days', array() );
		$days = is_array( $days ) ? $days : array();
		$day  = wp_date( 'Y-m-d' );
		if ( ! in_array( $day, $days, true ) ) {
			$days[] = $day;
			$days   = array_slice( array_values( array_filter( $days, function ( $x ) {
				return strtotime( $x ) > time() - 30 * DAY_IN_SECONDS;
			} ) ), -30 );
			update_option( 'flxi_quota_full_days', $days, false );
		}
	}
}

/* PHPMailer hazırken alıcı sayısını ve posta kutusunu not al; başarılı olursa say. */
add_action(
	'phpmailer_init',
	function ( $mailer ) {
		$cur = $GLOBALS['flxi_current'] ?? null;
		$n   = count( $mailer->getToAddresses() ) + count( $mailer->getCcAddresses() ) + count( $mailer->getBccAddresses() );
		$GLOBALS['flxi_quota_pending'] = array( flxi_quota_mailbox( $cur ? $cur['sender'] : 'info' ), $n );
	},
	99
);
add_action(
	'wp_mail_succeeded',
	function () {
		if ( ! empty( $GLOBALS['flxi_quota_pending'] ) ) {
			flxi_quota_add( $GLOBALS['flxi_quota_pending'][0], $GLOBALS['flxi_quota_pending'][1] );
		}
		$GLOBALS['flxi_quota_pending'] = null;
	},
	5
);
add_action(
	'wp_mail_failed',
	function () {
		$GLOBALS['flxi_quota_pending'] = null;
	},
	5
);

/**
 * Toplu gönderim bu posta kutusundan şimdi gidebilir mi?
 * Yedek pay (limit_reserve) parola sıfırlama, bağış makbuzu gibi tekil e-postalara bırakılır.
 */
function flxi_quota_bulk_allowed( $sender = 'info' ) {
	$box  = flxi_quota_mailbox( $sender );
	$left = flxi_quota_left( $box );
	if ( null === $left ) {
		return true;
	}
	return $left > flxi_limits()['reserve'];
}

/** Kullanım özeti (ekran, MCP, sistem kontrolü). */
function flxi_quota_summary() {
	$l   = flxi_limits();
	$out = array();
	foreach ( flxi_quota_mailboxes() as $box => $type ) {
		$used  = flxi_quota_used( $box );
		$out[] = array(
			'mailbox' => $box,
			'type'    => $type,
			'used'    => $used,
			'limit'   => $l['daily'],
			'left'    => $l['daily'] ? max( 0, $l['daily'] - $used ) : null,
			'percent' => $l['daily'] ? min( 100, (int) round( $used * 100 / $l['daily'] ) ) : 0,
			'next'    => $used ? flxi_quota_next_free( $box ) : 0,
		);
	}
	return $out;
}

/** Toplu gönderimin kaç günde biteceği tahmini. */
function flxi_quota_days_for( $count, $sender = 'info' ) {
	$l = flxi_limits();
	if ( ! $l['daily'] || $count < 1 ) {
		return 0;
	}
	$per_day = max( 1, $l['daily'] - $l['reserve'] );
	$left    = max( 0, (int) flxi_quota_left( flxi_quota_mailbox( $sender ) ) - $l['reserve'] );
	if ( $count <= $left ) {
		return 0;
	}
	return (int) ceil( ( $count - $left ) / $per_day );
}

/** Kullanım çubukları (Ayarlar ve Gönder ekranı). */
function flxi_quota_meter_html() {
	$l = flxi_limits();
	if ( ! $l['daily'] ) {
		return '';
	}
	$h = '<div class="flxi-quota">';
	foreach ( flxi_quota_summary() as $q ) {
		$cls = $q['percent'] >= 100 ? 'is-full' : ( $q['percent'] >= 80 ? 'is-high' : '' );
		$h  .= '<div class="flxi-quota__row ' . esc_attr( $cls ) . '">';
		$h  .= '<div class="flxi-quota__head"><strong>' . esc_html( $q['mailbox'] ) . '</strong><span>';
		/* translators: 1: used, 2: daily limit */
		$h .= esc_html( sprintf( __( 'Son 24 saat: %1$s / %2$s', 'flx-mail' ), number_format_i18n( $q['used'] ), number_format_i18n( $q['limit'] ) ) );
		$h .= '</span></div><div class="flxi-bar"><span style="width:' . (int) $q['percent'] . '%"></span></div>';
		if ( $q['left'] < $q['limit'] && $q['next'] ) {
			/* translators: %s: time */
			$h .= '<small>' . esc_html( sprintf( __( 'Sonraki hak açılışı: %s', 'flx-mail' ), wp_date( get_option( 'time_format' ) . ', j M', $q['next'] ) ) ) . '</small>';
		}
		$h .= '</div>';
	}
	return $h . '</div>';
}
