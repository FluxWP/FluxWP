<?php
/**
 * Zamanlanmış WhatsApp raporları (1.14.0, Ahmet; Flux Roles 1.1.0 yetkileri):
 * - flx_reports_manage: Çağrı Merkezi › Raporlar sayfasında zamanlamaları ve motivasyon metinlerini ayarlar.
 * - flx_reports_receive: kendisine (kendi referans bağışlarından) rapor gönderilebilir.
 * - flx_reports_view: genel rapor (toplamlar, personel bazında) alabilir.
 * Kurallar option flxc_reports'ta; numara Flux Roles'dan (Profil › WhatsApp numarası). Gönderim saatlik
 * tetik, panel/webhook dürtmesi ve kuralın saatine kurulan tek seferlik tetikle; her çalışmada en fazla
 * 25 kişi (Hostinger süre sınırı), kalanlar sonraki çalışmada.
 *
 * @package FLX_Cagri_Merkezi
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'flx_role_caps_supported',
	function ( $caps ) {
		return array_merge( (array) $caps, array( 'flx_reports_receive', 'flx_reports_view', 'flx_reports_manage' ) );
	}
);

function flxc_reports_can_manage() {
	return current_user_can( 'flx_reports_manage' ) || flxc_can_manage();
}

function flxc_reports_on() {
	return function_exists( 'flxr_user_phone' ) && function_exists( 'flxb_table' );
}

/** Kural varsayılanları. */
function flxc_report_defaults() {
	return array(
		'id'         => '',
		'title'      => '',
		'active'     => 1,
		'audience'   => 'receive', // receive | view | users
		'users'      => array(),
		'content'    => 'own',     // own | general
		'period'     => 'today',   // today | yesterday | week | month
		'freq'       => 'daily',   // daily | weekly | monthly
		'weekday'    => 1,
		'monthday'   => 1,
		'time'       => '18:00',
		'motivation' => '',
		'slot'       => '',        // son tamamlanan gönderim günü
		'sent'       => array(),   // bu gündeki gönderilenler (kullanıcı id)
	);
}

function flxc_reports() {
	$all = get_option( 'flxc_reports', null );
	if ( null === $all ) {
		// İlk kurulum: personele günlük kendi raporu (kapalı), idarecilere haftalık genel rapor (kapalı).
		$all = array(
			array(
				'id'         => 'r' . wp_generate_password( 6, false, false ),
				'title'      => __( 'Personel günlük raporu', 'flx-chat' ),
				'active'     => 0,
				'motivation' => implode( "\n", array( __( 'Bugünkü emeğin için teşekkürler, her fidan bir umut 🌱', 'flx-chat' ), __( 'Yarın da birlikte daha çok fidan dikelim 💪', 'flx-chat' ), __( 'Küçük adımlar büyük ormanlar büyütür 🌳', 'flx-chat' ) ) ),
			),
			array(
				'id'       => 'r' . wp_generate_password( 6, false, false ),
				'title'    => __( 'İdareci haftalık genel rapor', 'flx-chat' ),
				'active'   => 0,
				'audience' => 'view',
				'content'  => 'general',
				'period'   => 'week',
				'freq'     => 'weekly',
				'weekday'  => 1,
				'time'     => '09:00',
			),
		);
	}
	$out = array();
	foreach ( (array) $all as $r ) {
		if ( is_array( $r ) && ! empty( $r['id'] ) ) {
			$out[] = wp_parse_args( $r, flxc_report_defaults() );
		}
	}
	return $out;
}

function flxc_reports_save( array $list ) {
	update_option( 'flxc_reports', array_values( $list ), false );
	flxc_reports_schedule();
}

/** Kuralın bugünkü gönderim zamanı (yoksa 0): bugün gönderim günü değilse 0. */
function flxc_report_slot_time( array $r, $now = null ) {
	$now = $now ?? time();
	$tz  = wp_timezone();
	$d   = new DateTime( '@' . $now );
	$d->setTimezone( $tz );
	if ( 'weekly' === $r['freq'] && (int) $d->format( 'N' ) !== (int) $r['weekday'] ) {
		return 0;
	}
	if ( 'monthly' === $r['freq'] && (int) $d->format( 'j' ) !== min( (int) $r['monthday'], (int) $d->format( 't' ) ) ) {
		return 0;
	}
	list( $hh, $mm ) = array_map( 'intval', explode( ':', preg_match( '/^\d{1,2}:\d{2}$/', $r['time'] ) ? $r['time'] : '18:00' ) );
	$d->setTime( $hh, $mm );
	return $d->getTimestamp();
}

/** Alıcılar: numarası kayıtlı ve yetkili kullanıcılar. */
function flxc_report_recipients( array $r ) {
	if ( ! flxc_reports_on() ) {
		return array();
	}
	$cap  = 'view' === $r['audience'] ? 'flx_reports_view' : 'flx_reports_receive';
	$args = array( 'number' => 300, 'orderby' => 'ID' );
	if ( 'users' === $r['audience'] ) {
		$args['include'] = array_map( 'intval', (array) $r['users'] ) ?: array( 0 );
	} else {
		$args['capability__in'] = array( $cap );
	}
	$users = get_users( $args );
	if ( 'view' === $r['audience'] ) {
		// Site yöneticisi (administrator) genel raporu görür; rolünde yetki yazmasa da listeye girer.
		$users = array_merge( $users, get_users( array( 'role__in' => array( 'administrator' ), 'number' => 50 ) ) );
	}
	$out  = array();
	$seen = array();
	foreach ( $users as $u ) {
		if ( isset( $seen[ $u->ID ] ) ) {
			continue;
		}
		$seen[ $u->ID ] = 1;
		// Genel rapor yalnız flx_reports_view olana; kişisel rapor alanın flx_reports_receive yetkisi olmalı.
		$need = 'general' === $r['content'] ? 'flx_reports_view' : 'flx_reports_receive';
		if ( ! user_can( $u, $need ) && ! user_can( $u, 'manage_options' ) ) {
			continue;
		}
		$phone = (string) flxr_user_phone( $u->ID );
		if ( '' !== $phone ) {
			$out[] = array( 'id' => (int) $u->ID, 'name' => $u->display_name, 'phone' => $phone );
		}
	}
	return $out;
}

function flxc_report_period_label( $p ) {
	$l = array(
		'today'     => __( 'Bugün', 'flx-chat' ),
		'yesterday' => __( 'Dün', 'flx-chat' ),
		'week'      => __( 'Son 7 gün', 'flx-chat' ),
		'month'     => __( 'Bu ay', 'flx-chat' ),
	);
	return $l[ $p ] ?? $p;
}

/** Mesaj metni (kullanıcı için). */
function flxc_report_text( array $r, $user_id ) {
	$u = get_userdata( $user_id );
	if ( ! $u || ! function_exists( 'flxc_staff_report_data' ) ) {
		return '';
	}
	list( $from, $to ) = flxc_staff_period( $r['period'] );
	$cur  = function_exists( 'flxb_default_currency' ) ? flxb_default_currency() : 'TRY';
	$m    = function ( $v ) use ( $cur ) {
		return flxc_money( (float) $v, $cur );
	};
	$head = '📊 *' . ( '' !== $r['title'] ? $r['title'] : __( 'Bağış raporu', 'flx-chat' ) ) . '* · ' . flxc_report_period_label( $r['period'] ) . ( $from === $to ? ' (' . flx_date( strtotime( $from . ' 12:00' ), 'j F' ) . ')' : '' );
	$first = trim( strtok( (string) $u->display_name, ' ' ) );
	/* translators: %s: first name */
	$lines = array( $head, '', sprintf( __( 'Merhaba %s,', 'flx-chat' ), '' !== $first ? $first : $u->display_name ) );
	if ( 'general' === $r['content'] ) {
		$d = flxc_staff_report_data( $from, $to );
		$t = $d['total'];
		/* translators: 1: count, 2: amount, 3: units */
		$lines[] = sprintf( __( '✅ Ödenen: %1$d bağış · %2$s (%3$d adet)', 'flx-chat' ), $t['paid_n'], $m( $t['paid'] ), $t['paid_q'] );
		/* translators: 1: count, 2: amount */
		$lines[] = sprintf( __( '⏳ Ödeme bekleyen: %1$d · %2$s', 'flx-chat' ), $t['pending_n'], $m( $t['pending'] ) );
		/* translators: %d: count */
		$lines[] = sprintf( __( '❌ İptal: %d', 'flx-chat' ), $t['canceled_n'] );
		$top = array();
		foreach ( $d['by'] as $uid => $x ) {
			if ( ! $uid || ( ! $x['paid_n'] && ! $x['pending_n'] ) ) {
				continue;
			}
			$su    = get_userdata( $uid );
			/* translators: 1: name, 2: paid count, 3: amount, 4: pending */
			$top[] = sprintf( __( '• %1$s: %2$d ödenen (%3$s), %4$d bekleyen', 'flx-chat' ), $su ? $su->display_name : '#' . $uid, $x['paid_n'], $m( $x['paid'] ), $x['pending_n'] );
			if ( count( $top ) >= 10 ) {
				break;
			}
		}
		if ( $top ) {
			$lines[] = '';
			$lines[] = '*' . __( 'Personel', 'flx-chat' ) . '*';
			$lines   = array_merge( $lines, $top );
		}
	} else {
		$d = flxc_staff_report_data( $from, $to, $user_id );
		$t = $d['total'];
		if ( ! $t['paid_n'] && ! $t['pending_n'] && ! $t['canceled_n'] ) {
			$lines[] = __( 'Bu dönemde size bağlı bağış yok.', 'flx-chat' );
		} else {
			/* translators: 1: count, 2: amount, 3: units */
			$lines[] = sprintf( __( '✅ Ödenen: %1$d bağış · %2$s (%3$d adet)', 'flx-chat' ), $t['paid_n'], $m( $t['paid'] ), $t['paid_q'] );
			/* translators: 1: count, 2: amount */
			$lines[] = sprintf( __( '⏳ Ödeme bekleyen: %1$d · %2$s', 'flx-chat' ), $t['pending_n'], $m( $t['pending'] ) );
			if ( $t['canceled_n'] ) {
				/* translators: %d: count */
				$lines[] = sprintf( __( '❌ İptal: %d', 'flx-chat' ), $t['canceled_n'] );
			}
		}
		if ( 'month' !== $r['period'] ) {
			$mo = flxc_staff_report_data( wp_date( 'Y-m-01' ), wp_date( 'Y-m-d' ), $user_id )['total'];
			/* translators: 1: count, 2: amount */
			$lines[] = sprintf( __( '📅 Bu ay toplam: %1$d bağış · %2$s', 'flx-chat' ), $mo['paid_n'], $m( $mo['paid'] ) );
		}
	}
	$mot = array_values( array_filter( array_map( 'trim', preg_split( '/\R/u', (string) $r['motivation'] ) ) ) );
	if ( $mot ) {
		$lines[] = '';
		$lines[] = $mot[ wp_rand( 0, count( $mot ) - 1 ) ];
	}
	return (string) apply_filters( 'flxc_report_text', implode( "\n", $lines ), $r, $user_id );
}

/** Numaraya WhatsApp mesajı (sohbet yoksa açılır; kara listedeyse gitmez). */
function flxc_report_send( $phone, $name, $text ) {
	$c    = flxc_default_connection();
	$chat = $c ? flxc_ensure_chat( (string) $c['id'], $phone . '@s.whatsapp.net', array( 'name' => $name ) ) : null;
	if ( ! $chat || flxc_chat_blocked( $chat ) ) {
		return false;
	}
	$r = flxc_send( $chat, array( 'type' => 'text', 'body' => $text, 'source' => 'bot', 'user_id' => 0, 'preview' => false ) );
	return ! is_wp_error( $r );
}

/** Zamanı gelen raporları gönderir (kilitli; adım başına en fazla 25 kişi / ~40 sn). */
function flxc_reports_run_due() {
	if ( ! flxc_reports_on() || get_transient( 'flxc_reports_lock' ) ) {
		return 0;
	}
	set_transient( 'flxc_reports_lock', 1, 90 );
	$start = time();
	$sent  = 0;
	$list  = flxc_reports();
	$today = wp_date( 'Y-m-d' );
	foreach ( $list as $i => $r ) {
		$at = $r['active'] ? flxc_report_slot_time( $r ) : 0;
		// Yalnız bugünün gönderimi; saatinden 6 saat sonra hâlâ gönderilmediyse atlanır (gece yarısı rapor gitmesin).
		if ( ! $at || time() < $at || time() > $at + 6 * HOUR_IN_SECONDS || $r['slot'] === $today ) {
			continue;
		}
		$done = array_map( 'intval', (array) $r['sent'] );
		$left = false;
		foreach ( flxc_report_recipients( $r ) as $p ) {
			if ( in_array( $p['id'], $done, true ) ) {
				continue;
			}
			if ( $sent >= 25 || time() - $start > 40 ) {
				$left = true;
				break;
			}
			$text = flxc_report_text( $r, $p['id'] );
			if ( '' !== $text ) {
				flxc_report_send( $p['phone'], $p['name'], $text );
				$sent++;
			}
			$done[] = $p['id'];
			$list[ $i ]['sent'] = $done;
			update_option( 'flxc_reports', $list, false ); // yarıda kesilirse aynı kişiye iki kez gitmesin
		}
		if ( ! $left ) {
			$list[ $i ]['slot'] = $today;
			$list[ $i ]['sent'] = array();
		}
	}
	update_option( 'flxc_reports', $list, false );
	delete_transient( 'flxc_reports_lock' );
	if ( $sent ) {
		flxc_bot_stat_add( array( 'reports' => $sent ) );
	}
	flxc_reports_schedule();
	return $sent;
}

/** En yakın rapor saatine tek seferlik tetik (saatlik tetiği beklemesin). */
function flxc_reports_schedule() {
	$next = 0;
	foreach ( flxc_reports() as $r ) {
		if ( ! $r['active'] ) {
			continue;
		}
		for ( $day = 0; $day < 32; $day++ ) {
			$at = flxc_report_slot_time( $r, time() + $day * DAY_IN_SECONDS );
			if ( $at && $at > time() && ( 0 !== $day || $r['slot'] !== wp_date( 'Y-m-d' ) ) ) {
				$next = $next ? min( $next, $at ) : $at;
				break;
			}
		}
	}
	$cur = wp_next_scheduled( 'flxc_reports_tick' );
	if ( $cur && ( ! $next || $cur > $next + 5 ) ) {
		wp_unschedule_event( $cur, 'flxc_reports_tick' );
		$cur = 0;
	}
	if ( $next && ! $cur ) {
		wp_schedule_single_event( $next + 5, 'flxc_reports_tick' );
	}
}

add_action( 'flxc_reports_tick', 'flxc_reports_run_due' );
add_action( 'flxc_jobs_tick', 'flxc_reports_run_due', 20 );
// Panel ve webhook istekleri de dürter (WP-Cron çalışmayan sunucuda): en çok 5 dk'da bir bakılır;
// gönderim bu istekte değil, arka planda (wp-cron.php) yapılır, webhook cevabı gecikmesin.
add_action(
	'flxc_jobs_poked',
	function () {
		if ( get_transient( 'flxc_reports_poked' ) ) {
			return;
		}
		set_transient( 'flxc_reports_poked', 1, 5 * MINUTE_IN_SECONDS );
		if ( flxc_reports_has_due() ) {
			wp_schedule_single_event( time() - 1, 'flxc_reports_tick' );
			spawn_cron();
		}
	}
);

/** Şu an gönderilmesi gereken bir rapor var mı? */
function flxc_reports_has_due() {
	if ( ! flxc_reports_on() ) {
		return false;
	}
	$today = wp_date( 'Y-m-d' );
	foreach ( flxc_reports() as $r ) {
		$at = $r['active'] ? flxc_report_slot_time( $r ) : 0;
		if ( $at && time() >= $at && time() <= $at + 6 * HOUR_IN_SECONDS && $r['slot'] !== $today ) {
			return true;
		}
	}
	return false;
}

/* ---------- Panel: Çağrı Merkezi › Raporlar ---------- */

add_action(
	'admin_menu',
	function () {
		$cap = current_user_can( 'flx_reports_manage' ) ? 'flx_reports_manage' : 'flxc_manage';
		add_submenu_page( 'flx-chat', __( 'WhatsApp raporları', 'flx-chat' ), __( 'Raporlar', 'flx-chat' ), $cap, 'flx-chat-raporlar', 'flxc_render_reports' );
	},
	20
);

add_action(
	'admin_enqueue_scripts',
	function () {
		if ( isset( $_GET['page'] ) && 'flx-chat-raporlar' === $_GET['page'] ) { // phpcs:ignore
			wp_enqueue_style( 'flxc-settings', FLXC_URL . 'assets/css/settings.css', array(), FLXC_VERSION );
		}
	}
);

add_action( 'admin_post_flxc_reports', 'flxc_reports_post' );

function flxc_reports_post() {
	if ( ! flxc_reports_can_manage() ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok.', 'flx-chat' ) );
	}
	check_admin_referer( 'flxc_reports' );
	$in   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$list = flxc_reports();
	$do   = sanitize_key( (string) ( $in['do'] ?? 'save' ) );
	$back = admin_url( 'admin.php?page=flx-chat-raporlar' );
	$byid = array();
	foreach ( $list as $i => $r ) {
		$byid[ $r['id'] ] = $i;
	}
	// Formdaki tüm kurallar kaydedilir (önizle/gönder düğmesi de önce kaydeder).
	foreach ( (array) ( $in['r'] ?? array() ) as $rid => $f ) {
		if ( ! isset( $byid[ $rid ] ) || ! is_array( $f ) ) {
			continue;
		}
		$r = $list[ $byid[ $rid ] ];
		$r['title']      = sanitize_text_field( (string) ( $f['title'] ?? '' ) );
		$r['active']     = empty( $f['active'] ) ? 0 : 1;
		$r['audience']   = in_array( $f['audience'] ?? '', array( 'receive', 'view', 'users' ), true ) ? $f['audience'] : 'receive';
		$r['users']      = array_values( array_filter( array_map( 'intval', (array) ( $f['users'] ?? array() ) ) ) );
		$r['content']    = 'general' === ( $f['content'] ?? '' ) ? 'general' : 'own';
		$r['period']     = in_array( $f['period'] ?? '', array( 'today', 'yesterday', 'week', 'month' ), true ) ? $f['period'] : 'today';
		$r['freq']       = in_array( $f['freq'] ?? '', array( 'daily', 'weekly', 'monthly' ), true ) ? $f['freq'] : 'daily';
		$r['weekday']    = min( 7, max( 1, (int) ( $f['weekday'] ?? 1 ) ) );
		$r['monthday']   = min( 28, max( 1, (int) ( $f['monthday'] ?? 1 ) ) );
		$r['time']       = preg_match( '/^\d{2}:\d{2}$/', (string) ( $f['time'] ?? '' ) ) ? $f['time'] : '18:00';
		$r['motivation'] = sanitize_textarea_field( (string) ( $f['motivation'] ?? '' ) );
		$list[ $byid[ $rid ] ] = $r;
	}
	$target = sanitize_key( (string) ( $in['rtarget'] ?? '' ) );
	if ( 'add' === $do ) {
		$list[] = wp_parse_args( array( 'id' => 'r' . wp_generate_password( 6, false, false ), 'title' => __( 'Yeni rapor', 'flx-chat' ), 'active' => 0 ), flxc_report_defaults() );
	} elseif ( 'delete' === $do && isset( $byid[ $target ] ) ) {
		array_splice( $list, $byid[ $target ], 1 );
	}
	flxc_reports_save( $list );
	if ( in_array( $do, array( 'test', 'sendnow' ), true ) && isset( $byid[ $target ] ) ) {
		$r = flxc_reports()[ $byid[ $target ] ];
		$n = 0;
		if ( 'test' === $do ) {
			$me = function_exists( 'flxr_user_phone' ) ? (string) flxr_user_phone( get_current_user_id() ) : '';
			if ( '' === $me ) {
				wp_safe_redirect( add_query_arg( 'err', rawurlencode( __( 'Profilinizde WhatsApp numarası yok (Kullanıcılar › Profil).', 'flx-chat' ) ), $back ) );
				exit;
			}
			$n = flxc_report_send( $me, wp_get_current_user()->display_name, flxc_report_text( $r, get_current_user_id() ) ) ? 1 : 0;
		} else {
			foreach ( array_slice( flxc_report_recipients( $r ), 0, 25 ) as $p ) {
				$n += flxc_report_send( $p['phone'], $p['name'], flxc_report_text( $r, $p['id'] ) ) ? 1 : 0;
			}
		}
		wp_safe_redirect( add_query_arg( 'sent', $n, $back ) . '#' . $target );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'saved', 1, $back ) . ( 'add' === $do ? '#' . end( $list )['id'] : '' ) );
	exit;
}

function flxc_render_reports() {
	if ( ! flxc_reports_can_manage() ) {
		wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok.', 'flx-chat' ) );
	}
	$list  = flxc_reports();
	$days  = array();
	for ( $n = 1; $n <= 7; $n++ ) {
		$days[ $n ] = flx_date( gmmktime( 12, 0, 0, 1, 4 + $n, 2026 ), 'l' ); // 5 Ocak 2026 Pazartesi
	}
	$people = function_exists( 'flxr_user_phone' ) ? get_users( array( 'capability__in' => array( 'flx_reports_receive', 'flx_reports_view' ), 'number' => 300, 'orderby' => 'display_name' ) ) : array();
	$sel    = function ( $name, $opts, $cur ) {
		$h = '<select name="' . esc_attr( $name ) . '">';
		foreach ( $opts as $k => $l ) {
			$h .= '<option value="' . esc_attr( $k ) . '"' . selected( (string) $cur, (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		return $h . '</select>';
	};
	?>
	<div class="wrap flxcs">
		<header class="flxcs-head">
			<div>
				<h1><?php esc_html_e( 'WhatsApp raporları', 'flx-chat' ); ?></h1>
				<p><?php esc_html_e( 'Personel ve idarecilere zamanlanmış bağış raporu; sonuna kısa motivasyon metni eklenebilir.', 'flx-chat' ); ?></p>
			</div>
		</header>
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore ?>
			<div class="flxcs-note flxcs-note-ok"><?php esc_html_e( 'Kaydedildi.', 'flx-chat' ); ?></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['sent'] ) ) : // phpcs:ignore ?>
			<?php /* translators: %d: count */ ?>
			<div class="flxcs-note flxcs-note-ok"><?php echo esc_html( sprintf( __( '%d mesaj gönderildi.', 'flx-chat' ), (int) $_GET['sent'] ) ); // phpcs:ignore ?></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['err'] ) ) : // phpcs:ignore ?>
			<div class="flxcs-note flxcs-note-err"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['err'] ) ) ); // phpcs:ignore ?></div>
		<?php endif; ?>
		<?php if ( ! flxc_reports_on() ) : ?>
			<div class="flxcs-note flxcs-note-err"><?php esc_html_e( 'Raporlar için Flux Roles 1.1.0 ve Flux Donate gerekli.', 'flx-chat' ); ?></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="flxc_reports">
			<?php wp_nonce_field( 'flxc_reports' ); ?>
			<?php foreach ( $list as $r ) : ?>
				<?php
				$k   = 'r[' . $r['id'] . ']';
				$rcp = flxc_report_recipients( $r );
				?>
				<section class="flxcs-card flxcs-report" id="<?php echo esc_attr( $r['id'] ); ?>">
					<div class="flxcs-report-head">
						<input type="text" class="flxcs-report-title" name="<?php echo esc_attr( $k ); ?>[title]" value="<?php echo esc_attr( $r['title'] ); ?>" aria-label="<?php esc_attr_e( 'Rapor adı', 'flx-chat' ); ?>">
						<label class="flxcs-check"><input type="checkbox" name="<?php echo esc_attr( $k ); ?>[active]" value="1" <?php checked( $r['active'] ); ?>> <?php esc_html_e( 'Etkin', 'flx-chat' ); ?></label>
					</div>
					<div class="flxcs-grid flxcs-grid-3">
						<label class="flxcs-field"><span><?php esc_html_e( 'Kime', 'flx-chat' ); ?></span><?php echo $sel( $k . '[audience]', array( 'receive' => __( 'Rapor alabilen herkes', 'flx-chat' ), 'view' => __( 'Genel raporu görebilenler (idareci)', 'flx-chat' ), 'users' => __( 'Seçilen kişiler', 'flx-chat' ) ), $r['audience'] ); // phpcs:ignore ?></label>
						<label class="flxcs-field"><span><?php esc_html_e( 'İçerik', 'flx-chat' ); ?></span><?php echo $sel( $k . '[content]', array( 'own' => __( 'Kişinin kendi bağışları', 'flx-chat' ), 'general' => __( 'Genel toplam + personel bazında', 'flx-chat' ) ), $r['content'] ); // phpcs:ignore ?></label>
						<label class="flxcs-field"><span><?php esc_html_e( 'Dönem', 'flx-chat' ); ?></span><?php echo $sel( $k . '[period]', array( 'today' => __( 'Bugün', 'flx-chat' ), 'yesterday' => __( 'Dün', 'flx-chat' ), 'week' => __( 'Son 7 gün', 'flx-chat' ), 'month' => __( 'Bu ay', 'flx-chat' ) ), $r['period'] ); // phpcs:ignore ?></label>
						<label class="flxcs-field"><span><?php esc_html_e( 'Sıklık', 'flx-chat' ); ?></span><?php echo $sel( $k . '[freq]', array( 'daily' => __( 'Her gün', 'flx-chat' ), 'weekly' => __( 'Her hafta', 'flx-chat' ), 'monthly' => __( 'Her ay', 'flx-chat' ) ), $r['freq'] ); // phpcs:ignore ?></label>
						<label class="flxcs-field"><span><?php esc_html_e( 'Haftanın günü', 'flx-chat' ); ?></span><?php echo $sel( $k . '[weekday]', $days, $r['weekday'] ); // phpcs:ignore ?></label>
						<label class="flxcs-field"><span><?php esc_html_e( 'Ayın günü', 'flx-chat' ); ?></span><input type="number" min="1" max="28" name="<?php echo esc_attr( $k ); ?>[monthday]" value="<?php echo (int) $r['monthday']; ?>"></label>
						<label class="flxcs-field"><span><?php esc_html_e( 'Saat', 'flx-chat' ); ?></span><input type="time" name="<?php echo esc_attr( $k ); ?>[time]" value="<?php echo esc_attr( $r['time'] ); ?>"></label>
					</div>
					<?php if ( $people ) : ?>
						<details class="flxcs-field" <?php echo 'users' === $r['audience'] ? 'open' : ''; ?>>
							<summary><?php esc_html_e( 'Seçilen kişiler', 'flx-chat' ); ?></summary>
							<div class="flxcs-report-users">
								<?php foreach ( $people as $u ) : ?>
									<label class="flxcs-check"><input type="checkbox" name="<?php echo esc_attr( $k ); ?>[users][]" value="<?php echo (int) $u->ID; ?>" <?php checked( in_array( (int) $u->ID, array_map( 'intval', $r['users'] ), true ) ); ?>> <?php echo esc_html( $u->display_name ); ?><?php echo '' === (string) flxr_user_phone( $u->ID ) ? ' <small>(' . esc_html__( 'numara yok', 'flx-chat' ) . ')</small>' : ''; ?></label>
								<?php endforeach; ?>
							</div>
						</details>
					<?php endif; ?>
					<label class="flxcs-field"><span><?php esc_html_e( 'Motivasyon metinleri (her satır bir metin; her raporda biri rastgele eklenir)', 'flx-chat' ); ?></span><textarea name="<?php echo esc_attr( $k ); ?>[motivation]" rows="3"><?php echo esc_textarea( $r['motivation'] ); ?></textarea></label>
					<p class="flxcs-help">
						<?php
						/* translators: %d: recipient count */
						echo esc_html( sprintf( __( '%d alıcı (numarası kayıtlı ve yetkili).', 'flx-chat' ), count( $rcp ) ) );
						if ( $r['slot'] ) {
							/* translators: %s: date */
							echo ' ' . esc_html( sprintf( __( 'Son gönderim: %s.', 'flx-chat' ), flx_date( strtotime( $r['slot'] . ' 12:00' ) ) ) );
						}
						?>
					</p>
					<details class="flxcs-field">
						<summary><?php esc_html_e( 'Önizleme (sizin için)', 'flx-chat' ); ?></summary>
						<pre class="flxcs-report-pre"><?php echo esc_html( flxc_reports_on() ? flxc_report_text( $r, get_current_user_id() ) : '' ); ?></pre>
					</details>
					<div class="flxcs-report-acts">
						<button class="flxcs-btn flxcs-btn-sm" name="do" value="test" onclick="this.form.elements.rtarget.value='<?php echo esc_attr( $r['id'] ); ?>'"><?php esc_html_e( 'Bana test gönder', 'flx-chat' ); ?></button>
						<button class="flxcs-btn flxcs-btn-sm" name="do" value="sendnow" onclick="this.form.elements.rtarget.value='<?php echo esc_attr( $r['id'] ); ?>'; return confirm('<?php echo esc_js( __( 'Rapor şimdi tüm alıcılara gönderilsin mi?', 'flx-chat' ) ); ?>');"><?php esc_html_e( 'Şimdi herkese gönder', 'flx-chat' ); ?></button>
						<button class="flxcs-btn flxcs-btn-sm flxcs-btn-danger" name="do" value="delete" onclick="this.form.elements.rtarget.value='<?php echo esc_attr( $r['id'] ); ?>'; return confirm('<?php echo esc_js( __( 'Bu rapor silinsin mi?', 'flx-chat' ) ); ?>');"><?php esc_html_e( 'Sil', 'flx-chat' ); ?></button>
					</div>
				</section>
			<?php endforeach; ?>
			<input type="hidden" name="rtarget" value="">
			<div class="flxcs-save">
				<button class="flxcs-btn" name="do" value="add"><?php esc_html_e( '+ Yeni rapor', 'flx-chat' ); ?></button>
				<button class="flxcs-btn flxcs-btn-primary" name="do" value="save"><?php esc_html_e( 'Kaydet', 'flx-chat' ); ?></button>
			</div>
			<p class="flxcs-help"><?php esc_html_e( 'Alıcının numarası Kullanıcılar › Profil › WhatsApp numarası alanından gelir; yetkiler Kullanıcılar › Roller ve erişim sayfasından (rapor alabilir, genel raporu görebilir, raporları yönetir). Rapor seçilen saatte gider; sunucu zamanlayıcısı yoksa sitenin ilk ziyaretinde ya da sohbet panelinde gönderilir.', 'flx-chat' ); ?></p>
		</form>
	</div>
	<?php
}
