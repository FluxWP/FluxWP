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
		register_rest_route( $ns, '/log/(?P<id>\d+)', array( 'methods' => 'GET', 'callback' => 'flxi_rest_log_item', 'permission_callback' => $perm ) );
		register_rest_route( $ns, '/dns', array( 'methods' => 'POST', 'callback' => 'flxi_rest_dns', 'permission_callback' => $perm ) );
	}
);

/** Önizlemenin PDF'i (base64). Koyu mod seçiliyse e-postanın koyu kuralları uygulanır. */
function flxi_rest_preview_pdf( WP_REST_Request $r ) {
	$p = flxi_rest_preview( $r );
	if ( is_wp_error( $p ) ) {
		return $p;
	}
	$html = (string) $p['html'];
	if ( ! empty( $r['dark'] ) ) {
		$html = preg_replace( '/@media\s*\(\s*prefers-color-scheme\s*:\s*dark\s*\)/i', '@media all', $html );
	}
	$pdf = flxi_render_pdf( $html );
	if ( is_wp_error( $pdf ) ) {
		return new WP_Error( $pdf->get_error_code(), $pdf->get_error_message(), array( 'status' => 502 ) );
	}
	return array(
		'name' => flxi_pdf_filename( $p['subject'] ?? '' ),
		'data' => base64_encode( $pdf ), // phpcs:ignore
	);
}

/** Düzenleme formundaki (kaydedilmemiş) alanlarla önizleme. */
function flxi_rest_preview( WP_REST_Request $r ) {
	$key = sanitize_text_field( (string) $r['key'] );
	$f   = (array) $r['fields'];
	if ( $key && isset( flxi_email_types()[ $key ] ) ) {
		$stored = get_option( 'flxi_templates', array() );
		add_filter(
			'pre_option_flxi_templates',
			function () use ( $key, $f, $stored ) {
				$all         = is_array( $stored ) ? $stored : array();
				$cur         = $all[ $key ] ?? array();
				$all[ $key ] = array_merge( $cur, array_intersect_key( $f, array_flip( array( 'subject', 'heading', 'body', 'button_text', 'button_url', 'sender' ) ) ) );
				return $all;
			}
		);
		$b = flxi_build_template( $key, array(), array( 'use_sample' => true ) );
		return array( 'subject' => $b['subject'], 'html' => $b['html'] );
	}
	// Serbest içerik (gönderim ekranı) ya da görünüm önizlemesi
	$vars = array( 'ad' => 'Ayşe', 'ad_soyad' => 'Ayşe Yılmaz', 'eposta' => 'ayse@example.com', 'telefon' => '905321234567' );
	$args = array(
		'subject'     => flxi_fill( (string) ( $f['subject'] ?? '' ), $vars ),
		'heading'     => flxi_fill( (string) ( $f['heading'] ?? '' ), $vars, true ),
		'body'        => flxi_fill( flxi_body_html( wp_kses_post( (string) ( $f['body'] ?? '' ) ) ), $vars, true ),
		'button_text' => (string) ( $f['button_text'] ?? '' ),
		'button_url'  => (string) ( $f['button_url'] ?? '' ),
		'sender'      => (string) ( $f['sender'] ?? 'info' ),
	);
	if ( ! empty( $f['unsubscribe'] ) ) {
		$args['unsubscribe_url'] = home_url( '/' );
	}
	if ( 'brand' === $key ) {
		// Kaydedilmemiş görünüm ayarlarıyla önizleme (yalnız görünüm alanları, gizli alan yok)
		$brand = (array) ( $f['brand'] ?? array() );
		$allow = array( 'logo', 'logo_white', 'logo_width', 'color_primary', 'color_dark', 'color_bg', 'color_text', 'org_name', 'tagline', 'address', 'phone', 'contact_email', 'website', 'social', 'footer_note', 'footer_art', 'from_name' );
		$tmp   = array();
		foreach ( array_intersect_key( $brand, array_flip( $allow ) ) as $bk => $bv ) {
			try {
				$tmp[ $bk ] = flxi_sanitize_setting( $bk, $bv );
			} catch ( Exception $e ) {
				continue;
			}
		}
		if ( $tmp ) {
			$saved = get_option( 'flxi_settings', array() );
			add_filter(
				'pre_option_flxi_settings',
				function () use ( $tmp, $saved ) {
					return array_merge( is_array( $saved ) ? $saved : array(), $tmp );
				}
			);
			flxi_settings( true );
		}
		$args = array(
			'subject'     => __( 'Örnek e-posta', 'flx-mail' ),
			'heading'     => __( 'Kurumsal e-posta görünümü', 'flx-mail' ),
			'body'        => __( '<p>Merhaba Ayşe,</p><p>Sitemizden giden bütün e-postalar bu düzende görünür: üstte logomuz, ortada içerik, altta kurum bilgilerimiz ve sosyal medya hesaplarımız.</p>', 'flx-mail' ),
			'button_text' => __( 'Siteyi ziyaret et', 'flx-mail' ),
			'button_url'  => home_url( '/' ),
			'sender'      => 'info',
		);
	}
	$html = flxi_render_email( $args );
	if ( 'brand' === $key ) {
		flxi_settings( true );
	}
	return array( 'subject' => $args['subject'], 'html' => $html );
}

/** Test e-postası: şablon (örnek değerlerle) ya da ayar testi. */
function flxi_rest_test_email( WP_REST_Request $r ) {
	$to = sanitize_email( (string) $r['to'] );
	if ( ! is_email( $to ) ) {
		$to = wp_get_current_user()->user_email;
	}
	$key   = sanitize_text_field( (string) $r['key'] );
	$error = '';
	$catch = function ( $e ) use ( &$error ) {
		$error = $e->get_error_message();
		$data  = $e->get_error_data();
		if ( is_array( $data ) && ! empty( $data['phpmailer_exception_code'] ) ) {
			$error .= ' (' . $data['phpmailer_exception_code'] . ')';
		}
	};
	add_action( 'wp_mail_failed', $catch );
	// Test, seçilen gönderenin kendi hesabını dener (bilgi@'ye düşmez).
	$GLOBALS['flxi_strict_sender'] = true;
	if ( $key && isset( flxi_email_types()[ $key ] ) ) {
		$b  = flxi_build_template( $key, array(), array( 'use_sample' => true ) );
		$ok = wp_mail(
			$to,
			'[' . __( 'Test', 'flx-mail' ) . '] ' . $b['subject'],
			$b['html'],
			array( 'Content-Type: text/html; charset=UTF-8', 'X-FLXI-Template: flx-mail/test', 'X-FLXI-Rendered: 1', 'X-FLXI-Sender: ' . ( $b['sender'] ? $b['sender'] : 'info' ) )
		);
	} elseif ( is_array( $r['free'] ) ) {
		// Gönderim ekranındaki içerik, yalnız yöneticinin kendisine
		$f    = $r['free'];
		$vars = array( 'ad' => flxi_user_first_name( wp_get_current_user() ), 'ad_soyad' => wp_get_current_user()->display_name, 'eposta' => $to, 'telefon' => '' );
		$ok   = flxi_send_email(
			array(
				'to'          => $to,
				'template'    => 'flx-mail/test',
				'sender'      => 'noreply' === ( $f['sender'] ?? '' ) ? 'noreply' : 'info',
				'vars'        => $vars,
				'subject'     => '[' . __( 'Test', 'flx-mail' ) . '] ' . sanitize_text_field( (string) ( $f['subject'] ?? '' ) ),
				'heading'     => sanitize_text_field( (string) ( $f['heading'] ?? '' ) ),
				'body'        => wp_kses_post( (string) ( $f['body'] ?? '' ) ),
				'button_text' => sanitize_text_field( (string) ( $f['button_text'] ?? '' ) ),
				'button_url'  => esc_url_raw( (string) ( $f['button_url'] ?? '' ) ),
			)
		);
	} else {
		$sender = 'noreply' === $r['sender'] ? 'noreply' : 'info';
		$ok     = flxi_send_email(
			array(
				'to'          => $to,
				'template'    => 'flx-mail/test',
				'sender'      => $sender,
				'subject'     => __( 'Test e-postası', 'flx-mail' ),
				'heading'     => __( 'E-posta ayarlarınız çalışıyor 🎉', 'flx-mail' ),
				'body'        => sprintf(
					/* translators: 1: gönderen adresi, 2: yöntem */
					__( '<p>Bu e-postayı görüyorsanız sitenin e-posta gönderimi çalışıyor.</p><table class="flxi-kv"><tr><td>Gönderen</td><td><strong>%1$s</strong></td></tr><tr><td>Yöntem</td><td>%2$s</td></tr></table>', 'flx-mail' ),
					esc_html( flxi_sender_address( $sender )[0] ),
					esc_html( flxi_smtp_ready() ? 'SMTP · ' . flxi_setting( 'smtp_host' ) : ( flxi_other_smtp_plugin() ? flxi_other_smtp_plugin() : __( 'Sunucunun PHP mail()', 'flx-mail' ) ) )
				),
				'button_text' => __( 'Siteye git', 'flx-mail' ),
				'button_url'  => home_url( '/' ),
			)
		);
	}
	remove_action( 'wp_mail_failed', $catch );
	$GLOBALS['flxi_strict_sender'] = false;
	$res = array(
		'ok'    => (bool) $ok,
		'to'    => $to,
		'error' => $error,
		'at'    => time(),
	);
	if ( ! $key && ! is_array( $r['free'] ) ) {
		update_option( 'flxi_last_test', $res, false );
	}
	return $res;
}

function flxi_rest_queue_run( WP_REST_Request $r ) {
	$stats = flxi_run_queue( 15 );
	$cid   = (int) $r['campaign'];
	if ( $cid ) {
		$c              = flxi_get_campaign( $cid );
		$stats['campaign'] = $c ? array_intersect_key( $c, array_flip( array( 'id', 'status', 'total', 'sent', 'failed', 'skipped' ) ) ) : null;
	}
	$paused = (int) get_option( 'flxi_quota_paused' );
	if ( $paused > time() ) {
		$stats['paused_until'] = $paused;
		/* translators: %s: time */
		$stats['paused_text'] = sprintf( __( 'Posta kutusunun günlük gönderim sınırı doldu. Gönderim %s civarında kendiliğinden devam edecek.', 'flx-mail' ), wp_date( get_option( 'time_format' ) . ', j M', $paused ) );
	}
	return $stats;
}

function flxi_rest_recipients( WP_REST_Request $r ) {
	$list = flxi_collect_recipients( array_map( 'sanitize_text_field', (array) $r['sources'] ), (string) $r['manual'], sanitize_key( (string) $r['channel'] ) );
	$note = '';
	if ( 'email' === (string) $r['channel'] && $list ) {
		$days = flxi_quota_days_for( count( $list ), 'noreply' === (string) $r['sender'] ? 'noreply' : 'info' );
		if ( $days ) {
			/* translators: 1: daily limit, 2: days */
			$note = sprintf( _n( 'Günlük %1$s gönderim sınırı nedeniyle tamamı yaklaşık %2$s günde gider.', 'Günlük %1$s gönderim sınırı nedeniyle tamamı yaklaşık %2$s günde gider.', $days, 'flx-mail' ), number_format_i18n( flxi_limits()['daily'] ), number_format_i18n( $days ) );
		}
	}
	return array(
		'quota_note' => $note,
		'count'  => count( $list ),
		'sample' => array_slice( $list, 0, 5 ),
	);
}

function flxi_rest_connection_test( WP_REST_Request $r ) {
	$scenario = sanitize_key( (string) $r['scenario'] );
	if ( $scenario ) {
		$tpl = (array) $r['template'];
		return flxi_test_scenario(
			(int) $r['id'],
			$scenario,
			sanitize_text_field( (string) $r['to'] ),
			array(
				'text'     => sanitize_textarea_field( (string) $r['text'] ),
				'caption'  => sanitize_text_field( (string) $r['caption'] ),
				'template' => array(
					'name'   => sanitize_text_field( (string) ( $tpl['name'] ?? '' ) ),
					'lang'   => sanitize_text_field( (string) ( $tpl['lang'] ?? 'tr' ) ),
					'params' => array_map( 'sanitize_text_field', array_map( 'strval', (array) ( $tpl['params'] ?? array() ) ) ),
				),
			)
		);
	}
	return flxi_test_connection( (int) $r['id'], sanitize_text_field( (string) $r['to'] ) );
}

function flxi_rest_log_item( WP_REST_Request $r ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . flxi_table( 'log' ) . ' WHERE id = %d', (int) $r['id'] ), ARRAY_A ); // phpcs:ignore
	if ( ! $row ) {
		return new WP_Error( 'flxi_404', 'Not found', array( 'status' => 404 ) );
	}
	$body = (string) $row['body'];
	if ( 'email' !== $row['channel'] || ! preg_match( '~<(html|p|div|table|br)[\s>/]~i', $body ) ) {
		$body = '<pre style="white-space:pre-wrap;font:14px/1.5 -apple-system,Segoe UI,sans-serif;margin:16px">' . esc_html( $body ) . '</pre>';
	}
	$row['body'] = $body;
	return $row;
}

/** Alan adının SPF / DMARC / MX kayıtları (yalnız düğmeyle; sonuç saklanır). */
function flxi_rest_dns() {
	$res = flxi_dns_check();
	update_option( 'flxi_dns_check', $res, false );
	return $res;
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
