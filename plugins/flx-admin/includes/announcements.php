<?php
/**
 * Duyuru kartları: fluxwp.com güncelleme sunucusundaki /v1/announcements akışından (admin panelinde yönetilir)
 * Flux Admin ana sayfasında kapatılabilir kartlar. Kart kapatılınca kullanıcı başına hatırlanır.
 *
 * - Akış sayfa çizilirken çekilmez: önbellekten (transient) basılır; süresi geçtiyse yanıt gönderildikten sonra
 *   (shutdown) yenilenir. Dış istek flx_safe_remote_get ile, HTTPS, 64 KB sınır, şema doğrulamalı.
 * - Hedefleme (eklenti, site dili, tarih) burada uygulanır. Metin düz metindir, kaçışlanarak basılır.
 * - Filtreler: flxa_announcements_url (akış adresi; boş = kapalı), flxa_announcements (kartlar dizisi).
 */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_dashboard_setup', 'flxa_ann_maybe_refresh' );
add_action( 'wp_ajax_flxa_ann_dismiss', 'flxa_ajax_ann_dismiss' );

const FLXA_ANN_KEY   = 'flxa_ann_feed';
const FLXA_ANN_FRESH = 3600;      // 1 saat sonra yenilenir.
const FLXA_ANN_RETRY = 900;       // Başarısız denemeden sonra 15 dakika bekle.
const FLXA_ANN_META  = 'flxa_ann_dismissed';
const FLXA_ANN_SEEN  = 'flxa_ann_seen';
const FLXA_ANN_COUNT = 'flxa_ann_counts';

add_filter( 'flxk_telemetry_payload', 'flxa_ann_payload' );
add_action( 'flxk_telemetry_sent', 'flxa_ann_counts_reset' );

/** Anonymous counters (how many administrators saw / dismissed a card) are kept and sent only when the site agreed to usage data. */
function flxa_ann_counting() {
	return class_exists( 'FLXK_Telemetry' ) && FLXK_Telemetry::enabled();
}

function flxa_ann_count( $id, $event ) {
	if ( ! flxa_ann_counting() ) {
		return;
	}
	$c = (array) get_option( FLXA_ANN_COUNT, array() );
	if ( count( $c ) >= 40 && ! isset( $c[ $id ] ) ) {
		return;
	}
	$c[ $id ][ $event ] = (int) ( $c[ $id ][ $event ] ?? 0 ) + 1;
	update_option( FLXA_ANN_COUNT, $c, false );
}

function flxa_ann_payload( $p ) {
	$c = array_filter( (array) get_option( FLXA_ANN_COUNT, array() ), 'is_array' );
	if ( $c ) {
		$p['announcements'] = $c;
	}
	return $p;
}

function flxa_ann_counts_reset() {
	delete_option( FLXA_ANN_COUNT );
}

function flxa_ann_url() {
	return (string) apply_filters( 'flxa_announcements_url', 'https://updater.fluxwp.com/v1/announcements' );
}

/** Yalnız yöneticiler görür; yalnız Flux Admin kabuğu açıkken. */
function flxa_ann_visible() {
	return current_user_can( 'manage_options' ) && '' !== flxa_ann_url();
}

/** Akıştaki bir kaydı doğrula ve temizle; geçersizse null. */
function flxa_ann_clean( $a ) {
	if ( ! is_array( $a ) || empty( $a['id'] ) || ! preg_match( '/^[A-Za-z0-9_-]{1,40}$/', (string) $a['id'] ) ) {
		return null;
	}
	$text = static function ( $v, $max ) {
		$out = array();
		foreach ( array( 'en', 'tr' ) as $l ) {
			$s         = is_array( $v ) ? (string) ( $v[ $l ] ?? '' ) : '';
			$out[ $l ] = mb_substr( trim( wp_strip_all_tags( $s ) ), 0, $max );
		}
		return $out;
	};
	$title = $text( $a['title'] ?? null, 120 );
	if ( '' === $title['en'] && '' === $title['tr'] ) {
		return null;
	}
	$url  = isset( $a['url'] ) ? (string) $a['url'] : '';
	$url  = 0 === strpos( $url, 'https://' ) ? esc_url_raw( $url, array( 'https' ) ) : '';
	$tone = isset( $a['tone'] ) && in_array( $a['tone'], array( 'info', 'success', 'warn', 'critical' ), true ) ? $a['tone'] : 'info';
	$list = static function ( $v ) {
		return array_values( array_filter( array_map( 'strtolower', array_map( 'strval', is_array( $v ) ? array_slice( $v, 0, 40 ) : array() ) ) ) );
	};
	return array(
		'id'      => (string) $a['id'],
		'tone'    => $tone,
		'title'   => $title,
		'body'    => $text( $a['body'] ?? null, 600 ),
		'cta'     => $text( $a['cta'] ?? null, 40 ),
		'url'     => $url,
		'starts'  => (int) ( $a['starts'] ?? 0 ),
		'ends'    => (int) ( $a['ends'] ?? 0 ),
		'plugins' => $list( $a['plugins'] ?? array() ),
		'langs'   => $list( $a['langs'] ?? array() ),
	);
}

/** Akışı çek ve doğrula; hata olursa null. Bloklar: yalnız arka planda çağrılır. */
function flxa_ann_fetch() {
	$r = flx_safe_remote_get(
		flxa_ann_url(),
		array(
			'timeout'             => 5,
			'limit_response_size' => 65536,
			'headers'             => array( 'Accept' => 'application/json' ),
		)
	);
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return null;
	}
	$d = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	if ( ! is_array( $d ) || empty( $d['ok'] ) || 1 !== (int) ( $d['v'] ?? 0 ) || ! is_array( $d['items'] ?? null ) ) {
		return null;
	}
	$items = array();
	foreach ( array_slice( $d['items'], 0, 20 ) as $a ) {
		$c = flxa_ann_clean( $a );
		if ( $c ) {
			$items[] = $c;
		}
	}
	return $items;
}

function flxa_ann_cache() {
	$c = get_option( FLXA_ANN_KEY );
	return is_array( $c ) && isset( $c['items'], $c['at'] ) ? $c : array( 'items' => array(), 'at' => 0, 'ok' => 0 );
}

/** Ana sayfa açılınca, süresi geçmişse yanıt gittikten sonra yenile. */
function flxa_ann_maybe_refresh() {
	if ( ! flxa_ann_visible() || ! function_exists( 'flx_safe_remote_get' ) ) {
		return;
	}
	$c   = flxa_ann_cache();
	$age = time() - (int) $c['at'];
	if ( $age < ( $c['ok'] ? FLXA_ANN_FRESH : FLXA_ANN_RETRY ) ) {
		return;
	}
	add_action(
		'shutdown',
		static function () {
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			}
			$c = flxa_ann_cache();
			if ( get_transient( 'flxa_ann_lock' ) ) {
				return;
			}
			set_transient( 'flxa_ann_lock', 1, 60 );
			$items = flxa_ann_fetch();
			// Hatada eski kartlar kalır; yalnız deneme zamanı güncellenir.
			update_option( FLXA_ANN_KEY, array( 'items' => null === $items ? $c['items'] : $items, 'at' => time(), 'ok' => null === $items ? 0 : 1 ), false );
			delete_transient( 'flxa_ann_lock' );
		},
		99
	);
}

function flxa_ann_slugs() {
	$slugs = array();
	$all   = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$all = array_merge( $all, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}
	foreach ( $all as $f ) {
		$slugs[ dirname( (string) $f ) ] = true;
	}
	return $slugs;
}

/** Bu kullanıcının görmesi gereken kartlar (hedefleme + tarih + kapatılanlar elenmiş). */
function flxa_announcements() {
	if ( ! flxa_ann_visible() ) {
		return array();
	}
	$now   = time();
	$gone  = (array) get_user_meta( get_current_user_id(), FLXA_ANN_META, true );
	$slugs = flxa_ann_slugs();
	$loc   = strtolower( (string) determine_locale() );
	$lang  = substr( $loc, 0, 2 );
	$out   = array();
	foreach ( (array) apply_filters( 'flxa_announcements', flxa_ann_cache()['items'] ) as $a ) {
		if ( in_array( $a['id'], $gone, true ) || ( $a['starts'] && $now < $a['starts'] ) || ( $a['ends'] && $now >= $a['ends'] ) ) {
			continue;
		}
		if ( $a['plugins'] && ! array_intersect( $a['plugins'], array_keys( $slugs ) ) ) {
			continue;
		}
		if ( $a['langs'] && ! in_array( $lang, $a['langs'], true ) && ! in_array( $loc, $a['langs'], true ) ) {
			continue;
		}
		$pick = static function ( $v ) use ( $lang ) {
			$first = 'tr' === $lang ? 'tr' : 'en';
			$other = 'tr' === $first ? 'en' : 'tr';
			return '' !== $v[ $first ] ? $v[ $first ] : $v[ $other ];
		};
		$out[] = array(
			'id'    => $a['id'],
			'tone'  => $a['tone'],
			'title' => $pick( $a['title'] ),
			'body'  => $pick( $a['body'] ),
			'cta'   => $pick( $a['cta'] ),
			'url'   => $a['url'],
		);
	}
	return $out;
}

function flxa_ajax_ann_dismiss() {
	check_ajax_referer( 'flxa', 'nonce' );
	$id = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
	if ( ! current_user_can( 'manage_options' ) || ! preg_match( '/^[A-Za-z0-9_-]{1,40}$/', $id ) ) {
		wp_send_json_error( null, 400 );
	}
	$gone   = array_values( array_filter( (array) get_user_meta( get_current_user_id(), FLXA_ANN_META, true ) ) );
	$gone[] = $id;
	flxa_ann_count( $id, 'dismiss' );
	update_user_meta( get_current_user_id(), FLXA_ANN_META, array_slice( array_values( array_unique( $gone ) ), -200 ) );
	wp_send_json_success();
}

/** Kartları bas (ana sayfa başlığında). */
function flxa_ann_render() {
	$cards = flxa_announcements();
	if ( ! $cards ) {
		return;
	}
	$seen = array_filter( (array) get_user_meta( get_current_user_id(), FLXA_ANN_SEEN, true ) );
	$new  = array_diff( wp_list_pluck( $cards, 'id' ), $seen );
	if ( $new ) {
		foreach ( $new as $id ) {
			flxa_ann_count( $id, 'seen' );
		}
		update_user_meta( get_current_user_id(), FLXA_ANN_SEEN, array_slice( array_values( array_unique( array_merge( $seen, $new ) ) ), -200 ) );
	}
	?>
	<div class="flxa-anns" data-flxa-anns>
		<?php foreach ( $cards as $a ) : ?>
			<div class="flxa-ann is-<?php echo esc_attr( $a['tone'] ); ?>" data-flxa-ann="<?php echo esc_attr( $a['id'] ); ?>" role="status">
				<span class="flxa-ann-ico"><?php echo flxa_icon( 'critical' === $a['tone'] || 'warn' === $a['tone'] ? 'bolt' : 'comment', 18 ); // phpcs:ignore ?></span>
				<span class="flxa-ann-text">
					<strong><?php echo esc_html( $a['title'] ); ?></strong>
					<?php if ( '' !== $a['body'] ) : ?><span><?php echo nl2br( esc_html( $a['body'] ) ); ?></span><?php endif; ?>
				</span>
				<?php if ( '' !== $a['url'] ) : ?>
					<a class="flxa-btn" href="<?php echo esc_url( $a['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( '' !== $a['cta'] ? $a['cta'] : __( 'Learn more', 'flx-admin' ) ); ?></a>
				<?php endif; ?>
				<button type="button" class="flxa-icon-btn" data-flxa-ann-dismiss aria-label="<?php esc_attr_e( 'Dismiss', 'flx-admin' ); ?>"><?php echo flxa_icon( 'x', 16 ); // phpcs:ignore ?></button>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}
