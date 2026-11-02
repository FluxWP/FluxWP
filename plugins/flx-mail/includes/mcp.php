<?php
/**
 * Flux SEO & MCP sunucusuna araçlar (flxseo_mcp_tools). Yapay zekâ e-posta kayıtlarını görür, şablonları ve ayarları yönetir.
 * Gizli değerler (SMTP şifresi, API anahtarları) okunmaz ve yazılmaz.
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'flx_system_checks_versions', function ( $v ) {
	$v = (array) $v;
	$v['flx-mail'] = FLXI_VERSION;
	return $v;
} );

add_filter( 'flxseo_mcp_tools', 'flxi_mcp_tools' );

function flxi_mcp_public_settings() {
	return array( 'from_name', 'from_email', 'noreply_name', 'noreply_email', 'reply_to', 'force_from', 'mailer', 'smtp_host', 'smtp_port', 'smtp_secure', 'smtp_auth', 'smtp_user', 'smtp_nr_user', 'wrap_plain', 'wrap_html', 'email_images_fix', 'embed_images', 'footer_art', 'logo', 'logo_white', 'logo_width', 'color_primary', 'color_dark', 'color_bg', 'color_text', 'org_name', 'tagline', 'address', 'phone', 'contact_email', 'website', 'social', 'footer_note', 'log_enabled', 'log_days', 'social_block_core', 'social_block_wc', 'social_verified', 'bulk_per_run', 'bulk_unsubscribe', 'limit_daily', 'limit_recipients', 'limit_size_mb', 'limit_attach_mb', 'limit_storage_gb', 'limit_mailbox_emails', 'limit_aliases', 'limit_forwarders', 'limit_reserve', 'mailbox_enabled', 'imap_host', 'imap_port', 'imap_secure', 'imap_user', 'mailbox_days', 'mailbox_keep_days', 'mailbox_signature' );
}

function flxi_mcp_template_row( $key ) {
	$c = flxi_template( $key );
	$t = $c['type'];
	return array(
		'key'         => $key,
		'label'       => $t ? $t['label'] : $key,
		'group'       => $t ? $t['group'] : '',
		'kind'        => $c['kind'],
		'enabled'     => (bool) $c['enabled'],
		'editable'    => $t ? (bool) $t['editable'] : false,
		'sender'      => $c['sender'] ?? '',
		'wrap'        => (bool) ( $c['wrap'] ?? 1 ),
		'subject'     => $c['subject'] ?? '',
		'heading'     => $c['heading'] ?? '',
		'body'        => $c['body'] ?? '',
		'button_text' => $c['button_text'] ?? '',
		'button_url'  => $c['button_url'] ?? '',
		'vars'        => $t ? array_keys( (array) $t['vars'] ) : array(),
	);
}

function flxi_mcp_tools( $tools ) {
	$cap = 'manage_options';

	$tools['flxi_email_log'] = array(
		'title'       => 'Flux Mail: e-posta/mesaj kayıtları',
		'description' => 'Giden e-posta, WhatsApp ve SMS kayıtları (yeniden eskiye). status: sent | failed | blocked | sending; template: şablon anahtarı; search: alıcı/konu. include_body ile içerik (HTML) döner.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'status'       => array( 'type' => 'string', 'enum' => array( 'sent', 'failed', 'blocked', 'sending' ) ),
				'channel'      => array( 'type' => 'string', 'enum' => array( 'email', 'whatsapp', 'sms' ) ),
				'template'     => array( 'type' => 'string' ),
				'search'       => array( 'type' => 'string' ),
				'limit'        => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
				'id'           => array( 'type' => 'integer', 'description' => 'Tek kayıt' ),
				'include_body' => array( 'type' => 'boolean' ),
			),
		),
		'run'         => function ( $a ) {
			global $wpdb;
			$t     = flxi_table( 'log' );
			$where = array( '1=1' );
			$prm   = array();
			foreach ( array( 'status', 'channel', 'template' ) as $f ) {
				if ( ! empty( $a[ $f ] ) ) {
					$where[] = "$f = %s";
					$prm[]   = (string) $a[ $f ];
				}
			}
			if ( ! empty( $a['id'] ) ) {
				$where[] = 'id = %d';
				$prm[]   = (int) $a['id'];
			}
			if ( ! empty( $a['search'] ) ) {
				$like    = '%' . $wpdb->esc_like( (string) $a['search'] ) . '%';
				$where[] = '(recipient LIKE %s OR subject LIKE %s)';
				$prm[]   = $like;
				$prm[]   = $like;
			}
			$limit = max( 1, min( 100, (int) ( $a['limit'] ?? 20 ) ) );
			$cols  = 'id, created_at, channel, template, source, recipient, subject, status, error, mailer, sender, campaign_id' . ( ! empty( $a['include_body'] ) ? ', body' : '' );
			$sql   = "SELECT $cols FROM $t WHERE " . implode( ' AND ', $where ) . " ORDER BY id DESC LIMIT $limit";
			$rows  = $wpdb->get_results( $prm ? $wpdb->prepare( $sql, $prm ) : $sql, ARRAY_A ); // phpcs:ignore
			$sum   = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) n FROM $t WHERE created_at >= %s GROUP BY status", gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ), ARRAY_A ); // phpcs:ignore
			return array(
				'items'        => $rows,
				'last_7_days'  => wp_list_pluck( (array) $sum, 'n', 'status' ),
				'times_in'     => 'UTC',
			);
		},
	);

	$tools['flxi_templates'] = array(
		'title'       => 'Flux Mail: e-posta şablonları',
		'description' => 'Sitenin bütün e-postaları: WordPress, WooCommerce, FluxWP eklentileri ve otomatik bulunanlar. key ile tek şablonun içeriği ve değişkenleri döner.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array( 'key' => array( 'type' => 'string' ) ),
		),
		'run'         => function ( $a ) {
			if ( ! empty( $a['key'] ) ) {
				return flxi_mcp_template_row( (string) $a['key'] );
			}
			$out = array();
			foreach ( array_keys( flxi_email_types() ) as $k ) {
				$r     = flxi_mcp_template_row( $k );
				$out[] = array_intersect_key( $r, array_flip( array( 'key', 'label', 'group', 'kind', 'enabled', 'editable', 'sender', 'subject' ) ) );
			}
			foreach ( flxi_wc_emails() as $e ) {
				$out[] = array( 'key' => $e['key'], 'label' => $e['label'], 'group' => 'WooCommerce', 'kind' => 'woocommerce', 'enabled' => $e['enabled'], 'editable' => false );
			}
			foreach ( flxi_discovered() as $k => $d ) {
				$c     = flxi_template( $k );
				$out[] = array( 'key' => $k, 'label' => flxi_discovered_label( $k, $d ), 'group' => flxi_source_label( $d['source'] ), 'kind' => 'discovered', 'enabled' => (bool) $c['enabled'], 'editable' => false, 'sent' => (int) $d['count'] );
			}
			return array( 'templates' => $out );
		},
	);

	$tools['flxi_update_template'] = array(
		'title'       => 'Flux Mail: şablonu güncelle',
		'description' => 'Şablonu açar/kapatır ya da içeriğini değiştirir. Değişkenler {ad} biçiminde (flxi_templates ile key verip vars listesine bakın). body HTML. reset=true varsayılan içeriğe döndürür. WooCommerce e-postalarında yalnız enabled.',
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'required'   => array( 'key' ),
			'properties' => array(
				'key'         => array( 'type' => 'string' ),
				'enabled'     => array( 'type' => 'boolean' ),
				'sender'      => array( 'type' => 'string', 'enum' => array( 'info', 'noreply', '' ) ),
				'wrap'        => array( 'type' => 'boolean' ),
				'subject'     => array( 'type' => 'string' ),
				'heading'     => array( 'type' => 'string' ),
				'body'        => array( 'type' => 'string' ),
				'button_text' => array( 'type' => 'string' ),
				'button_url'  => array( 'type' => 'string' ),
				'reset'       => array( 'type' => 'boolean' ),
			),
		),
		'run'         => function ( $a ) {
			$key = (string) $a['key'];
			if ( 0 === strpos( $key, 'wc/' ) ) {
				if ( ! isset( $a['enabled'] ) ) {
					throw new Exception( 'WooCommerce e-postasında yalnız enabled değiştirilebilir.' );
				}
				flxi_wc_set_enabled( substr( $key, 3 ), ! empty( $a['enabled'] ) );
				return array( 'saved' => true, 'key' => $key, 'enabled' => (bool) $a['enabled'] );
			}
			$types = flxi_email_types();
			if ( ! isset( $types[ $key ] ) && ! isset( flxi_discovered()[ $key ] ) ) {
				throw new Exception( 'Bilinmeyen şablon: ' . $key );
			}
			if ( isset( $a['enabled'] ) && ! $a['enabled'] && isset( $types[ $key ] ) && ! $types[ $key ]['can_disable'] ) {
				throw new Exception( 'Bu e-posta güvenlik nedeniyle kapatılamaz.' );
			}
			if ( ! empty( $a['reset'] ) ) {
				flxi_reset_template( $key );
			}
			$data = array_intersect_key( $a, array_flip( array( 'enabled', 'sender', 'wrap' ) ) );
			if ( isset( $types[ $key ] ) && $types[ $key ]['editable'] ) {
				$data += array_intersect_key( $a, array_flip( array( 'subject', 'heading', 'body', 'button_text', 'button_url' ) ) );
			}
			flxi_save_template( $key, $data );
			return array( 'saved' => true, 'template' => flxi_mcp_template_row( $key ) );
		},
	);

	$tools['flxi_settings'] = array(
		'title'       => 'Flux Mail: ayarlar',
		'description' => 'Gönderen adı/adresi, no-reply, SMTP (şifre hariç), görünüm (logo, renkler, alt bilgi), kayıt, Google ile giriş ve toplu gönderim ayarları. set verilirse yalnız o alanlar kaydedilir. Şifre/anahtar yazılamaz ve okunamaz.',
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'set' => array( 'type' => 'object', 'description' => 'alan => değer' ),
			),
		),
		'run'         => function ( $a ) {
			$allowed = flxi_mcp_public_settings();
			if ( ! empty( $a['set'] ) && is_array( $a['set'] ) ) {
				$clean = array();
				foreach ( $a['set'] as $k => $v ) {
					if ( ! in_array( $k, $allowed, true ) ) {
						throw new Exception( 'Bu alan değiştirilemez: ' . $k );
					}
					$clean[ $k ] = flxi_sanitize_setting( $k, $v );
				}
				flxi_update_settings( $clean );
			}
			$s   = flxi_settings();
			$out = array_intersect_key( $s, array_flip( $allowed ) );
			$out['smtp_password_set'] = '' !== flxi_smtp_password();
			$out['smtp_ready']        = flxi_smtp_ready();
			$out['other_smtp_plugin'] = flxi_other_smtp_plugin();
			return $out;
		},
	);

	$tools['flxi_send_test'] = array(
		'title'       => 'Flux Mail: test e-postası',
		'description' => 'Test e-postası gönderir. key verilirse o şablonun örnek değerli hali gider. Sonuç ve hata döner; kayıtlara da düşer.',
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'to'     => array( 'type' => 'string' ),
				'key'    => array( 'type' => 'string' ),
				'sender' => array( 'type' => 'string', 'enum' => array( 'info', 'noreply' ) ),
			),
		),
		'run'         => function ( $a ) {
			$r = new WP_REST_Request( 'POST' );
			foreach ( array( 'to', 'key', 'sender' ) as $k ) {
				if ( isset( $a[ $k ] ) ) {
					$r->set_param( $k, $a[ $k ] );
				}
			}
			return flxi_rest_test_email( $r );
		},
	);

	$tools['flxi_mail_quota'] = array(
		'title'       => 'Flux Mail: posta kutusu sınırları ve kullanım',
		'description' => 'Hostinger posta kutusu sınırları (günlük gönderim, alıcı, boyut, depolama) ve son 24 saatte her posta kutusundan giden alıcı sayısı. Sınıra sık dayanılıyorsa üst pakete geçilmeli.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
		'run'         => function () {
			$full = get_option( 'flxi_quota_full_days', array() );
			return array(
				'limits'          => flxi_limits(),
				'usage'           => flxi_quota_summary(),
				'full_days_30d'   => is_array( $full ) ? array_values( $full ) : array(),
				'bulk_paused_until' => (int) get_option( 'flxi_quota_paused' ) > time() ? gmdate( 'c', (int) get_option( 'flxi_quota_paused' ) ) : null,
			);
		},
	);

	$tools['flxi_campaigns'] = array(
		'title'       => 'Flux Mail: toplu gönderimler',
		'description' => 'Son toplu/tekil gönderimler ve durumları (gönderilen, hatalı, bekleyen).',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array( 'type' => 'object', 'properties' => array( 'limit' => array( 'type' => 'integer' ) ) ),
		'run'         => function ( $a ) {
			global $wpdb;
			$limit = max( 1, min( 50, (int) ( $a['limit'] ?? 10 ) ) );
			return array( 'campaigns' => $wpdb->get_results( 'SELECT id, name, channel, status, total, sent, failed, skipped, created_at, finished_at FROM ' . flxi_table( 'campaigns' ) . " ORDER BY id DESC LIMIT $limit", ARRAY_A ) ); // phpcs:ignore
		},
	);

	return $tools;
}

/** Ayar değerini türüne göre temizler (panel ve MCP ortak). */
function flxi_sanitize_setting( $k, $v ) {
	$d = flxi_defaults();
	if ( in_array( $k, array( 'from_email', 'noreply_email', 'reply_to', 'contact_email' ), true ) ) {
		$v = sanitize_email( (string) $v );
		if ( '' !== $v && ! is_email( $v ) ) {
			throw new Exception( 'Geçersiz e-posta: ' . $k );
		}
		return $v;
	}
	if ( in_array( $k, array( 'color_primary', 'color_dark', 'color_bg', 'color_text' ), true ) ) {
		$c = sanitize_hex_color( (string) $v );
		return $c ? $c : $d[ $k ];
	}
	if ( in_array( $k, array( 'logo', 'logo_white', 'website' ), true ) ) {
		return esc_url_raw( (string) $v );
	}
	if ( 'social' === $k ) {
		$out = array();
		foreach ( (array) $v as $sk => $url ) {
			if ( isset( flxi_social_services()[ $sk ] ) && $url ) {
				$out[ $sk ] = esc_url_raw( (string) $url );
			}
		}
		return $out;
	}
	if ( 'footer_art' === $k ) {
		return isset( flxi_footer_arts()[ (string) $v ] ) ? (string) $v : 'none';
	}
	if ( 'mailer' === $k ) {
		return 'smtp' === $v ? 'smtp' : 'php';
	}
	if ( 'smtp_secure' === $k || 'imap_secure' === $k ) {
		return in_array( $v, array( 'ssl', 'tls' ), true ) ? $v : '';
	}
	if ( is_int( $d[ $k ] ?? null ) ) {
		return (int) ( is_bool( $v ) ? (int) $v : $v );
	}
	if ( 'footer_note' === $k || 'mailbox_signature' === $k ) {
		return sanitize_textarea_field( (string) $v );
	}
	return sanitize_text_field( (string) $v );
}
