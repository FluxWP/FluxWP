<?php
/**
 * Ortak sistem kontrolü satırları (flx_system_checks) ve yapay zekânın MCP ile uygulayabileceği düzeltmeler (flx_system_fixes).
 *
 * @package flx-mail
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'flx_system_checks', 'flxi_system_checks' );

function flxi_system_checks( $checks ) {
	global $wpdb;
	$src  = 'Flux Mail';
	$s    = flxi_settings();
	$site = flxi_site_domain();
	$set  = flxi_admin_url( 'flxi-ayarlar' );
	$add  = function ( $id, $group, $label, $status, $message, $url = '', $alabel = '' ) use ( &$checks, $src ) {
		$checks[] = array(
			'id'           => 'flxi.' . $id,
			'source'       => $src,
			'group'        => $group,
			'label'        => $label,
			'status'       => $status,
			'message'      => $message,
			'action_url'   => $url,
			'action_label' => $alabel,
		);
	};

	// Gönderen adresleri
	$from_dom = strtolower( substr( strrchr( (string) $s['from_email'], '@' ), 1 ) );
	if ( ! is_email( $s['from_email'] ) ) {
		$add( 'from', 'Email', __( 'Sender address', 'flx-mail' ), 'error', __( 'The sender email address is invalid. Enter your bilgi@ address in Communication › Settings.', 'flx-mail' ), $set, __( 'Settings', 'flx-mail' ) );
	} elseif ( $from_dom !== $site && substr( $from_dom, -strlen( '.' . $site ) ) !== '.' . $site ) {
		/* translators: 1: adres, 2: alan adı */
		$add( 'from', 'Email', __( 'Sender address', 'flx-mail' ), 'warning', sprintf( __( '%1$s is not on the site\'s domain (%2$s). Services like Gmail may mark emails from another domain as spam.', 'flx-mail' ), $s['from_email'], $site ), $set, __( 'Settings', 'flx-mail' ) );
	} else {
		/* translators: 1: ad, 2: adres, 3: no-reply adresi */
		$add( 'from', 'Email', __( 'Sender address', 'flx-mail' ), 'ok', sprintf( __( '%1$s <%2$s>; emails that don\'t expect a reply are sent from %3$s.', 'flx-mail' ), $s['from_name'], $s['from_email'], $s['noreply_email'] ) );
	}

	// Gönderim yöntemi
	$other = flxi_other_smtp_plugin();
	if ( $other ) {
		/* translators: %s: eklenti adı */
		$add( 'mailer', 'Email', __( 'Sending method', 'flx-mail' ), 'info', sprintf( __( 'Emails are sent by the %s plugin; the SMTP settings here are not used. The template, sender name and log still come from Flux Mail.', 'flx-mail' ), $other ) );
	} elseif ( 'smtp' === $s['mailer'] ) {
		if ( '' === trim( (string) $s['smtp_host'] ) || ( $s['smtp_auth'] && ( '' === (string) $s['smtp_user'] || '' === flxi_smtp_password() ) ) ) {
			$add( 'mailer', 'Email', __( 'Sending method', 'flx-mail' ), 'error', __( 'SMTP is selected but the server, username or password is missing. Emails may not be sent.', 'flx-mail' ), $set . '#smtp', __( 'SMTP settings', 'flx-mail' ) );
		} else {
			/* translators: 1: sunucu, 2: port */
			$add( 'mailer', 'Email', __( 'Sending method', 'flx-mail' ), 'ok', sprintf( __( 'SMTP: %1$s:%2$s', 'flx-mail' ), $s['smtp_host'], $s['smtp_port'] ) );
		}
	} else {
		$add( 'mailer', 'Email', __( 'Sending method', 'flx-mail' ), 'warning', __( 'Mail is sent with the server\'s PHP mail(). Gmail shows "via srv….main-hosting.eu" next to the sender and it is more likely to land in spam. Open a info@ mailbox on Hostinger and choose SMTP.', 'flx-mail' ), $set . '#smtp', __( 'SMTP settings', 'flx-mail' ) );
	}

	// SMTP hesapları (bilgi@ / no-reply@ girişi)
	foreach ( flxi_smtp_account_notes() as $acc => $note ) {
		if ( 'ok' !== $note['level'] ) {
			$add( 'smtp_' . $acc, 'Email', 'noreply' === $acc ? __( 'No-reply account', 'flx-mail' ) : __( 'SMTP account', 'flx-mail' ), $note['level'], $note['text'], $set . '#smtp', __( 'SMTP settings', 'flx-mail' ) );
		}
	}

	// Son test
	$test = get_option( 'flxi_last_test' );
	if ( is_array( $test ) ) {
		$when = flx_date( (int) $test['at'], 'datetime' );
		if ( $test['ok'] ) {
			/* translators: 1: adres, 2: tarih */
			$add( 'test', 'Email', __( 'Test email', 'flx-mail' ), 'ok', sprintf( __( 'Sent to %1$s (%2$s). Check that it reached the inbox.', 'flx-mail' ), $test['to'], $when ), $set . '#test', __( 'Test again', 'flx-mail' ) );
		} else {
			/* translators: 1: tarih, 2: hata */
			$add( 'test', 'Email', __( 'Test email', 'flx-mail' ), 'error', sprintf( __( 'Could not send (%1$s): %2$s', 'flx-mail' ), $when, $test['error'] ? $test['error'] : 'wp_mail false' ), $set . '#test', __( 'Test again', 'flx-mail' ) );
		}
	} else {
		$add( 'test', 'Email', __( 'Test email', 'flx-mail' ), 'info', __( 'Not tested yet.', 'flx-mail' ), $set . '#test', __( 'Send test email', 'flx-mail' ) );
	}

	// Son 24 saatte hatalar
	$table = flxi_table( 'log' );
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) { // phpcs:ignore
		$since  = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		$failed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = 'failed' AND created_at >= %s", $since ) ); // phpcs:ignore
		$sent   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE status = 'sent' AND created_at >= %s", $since ) ); // phpcs:ignore
		if ( $failed ) {
			$last = (string) $wpdb->get_var( $wpdb->prepare( "SELECT error FROM $table WHERE status = 'failed' AND created_at >= %s ORDER BY id DESC LIMIT 1", $since ) ); // phpcs:ignore
			/* translators: 1: hatalı, 2: gönderilen, 3: son hata */
			$add( 'failures', 'Email', __( 'Last 24 hours', 'flx-mail' ), 'error', sprintf( __( '%1$s sends failed, %2$s sent. Last error: %3$s', 'flx-mail' ), number_format_i18n( $failed ), number_format_i18n( $sent ), mb_substr( $last, 0, 200 ) ), flxi_admin_url( 'flxi-kayitlar', array( 'status' => 'failed' ) ), __( 'Log', 'flx-mail' ) );
		} else {
			/* translators: %s: sayı */
			$add( 'failures', 'Email', __( 'Last 24 hours', 'flx-mail' ), 'ok', sprintf( __( '%s sent, no errors.', 'flx-mail' ), number_format_i18n( $sent ) ), flxi_admin_url( 'flxi-kayitlar' ), __( 'Log', 'flx-mail' ) );
		}
		$stuck = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . flxi_table( 'queue' ) . " WHERE status = 'queued' AND scheduled_at < %s", gmdate( 'Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS ) ) ); // phpcs:ignore
		if ( $stuck ) {
			/* translators: %s: sayı */
			$add( 'queue', 'Cron', __( 'Bulk send queue', 'flx-mail' ), 'warning', sprintf( __( '%s messages have been waiting for more than 30 minutes. WP-Cron may not be running; keeping the Send page open advances the queue. A real cron is recommended on Hostinger.', 'flx-mail' ), number_format_i18n( $stuck ) ), flxi_admin_url( 'flxi-gonder' ), __( 'Sends', 'flx-mail' ) );
		}
	} else {
		$add( 'tables', 'Config', __( 'Database tables', 'flx-mail' ), 'error', __( 'Log tables are missing. Deactivate and reactivate the plugin.', 'flx-mail' ) );
	}

	// Kara liste (Flux Chat)
	if ( has_filter( 'flx_contact_blocked' ) ) {
		$add( 'blocklist', 'Email', __( 'Blocklist', 'flx-mail' ), 'ok', __( 'Active: people on the blocklist get no email, WhatsApp or SMS (except account security emails such as password reset).', 'flx-mail' ) );
	}

	// Posta kutusu (IMAP)
	if ( flxi_setting( 'mailbox_enabled' ) ) {
		$st = flxi_mailbox_state();
		if ( ! flxi_mailbox_enabled() ) {
			$add( 'mailbox', 'Email', __( 'Mailbox', 'flx-mail' ), 'warning', __( 'On, but the IMAP server, user or password is missing (Communication › Settings › Mailbox).', 'flx-mail' ) );
		} elseif ( $st['error'] ) {
			$add( 'mailbox', 'Email', __( 'Mailbox', 'flx-mail' ), 'error', $st['error'] );
		} elseif ( $st['last_ok'] && $st['last_ok'] < time() - 6 * HOUR_IN_SECONDS ) {
			/* translators: %s: süre */
			$add( 'mailbox', 'Email', __( 'Mailbox', 'flx-mail' ), 'warning', sprintf( __( 'Last sync %s. Scheduled tasks (WP-Cron) may not be running; syncing still happens while the Mailbox screen is open.', 'flx-mail' ), flx_time_ago( $st['last_ok'] ) ) );
		} elseif ( $st['last_ok'] ) {
			/* translators: %s: süre */
			$add( 'mailbox', 'Email', __( 'Mailbox', 'flx-mail' ), 'ok', sprintf( __( 'Syncing; last sync %s.', 'flx-mail' ), flx_time_ago( $st['last_ok'] ) ) . ( empty( $st['names']['sent'] ) ? ' ' . __( 'Sent folder not found: email sent from webmail will not appear.', 'flx-mail' ) : '' ) );
		}
		$add(
			'mailparse',
			'Email',
			__( 'Email parser', 'flx-mail' ),
			flxi_mime_has_mailparse() ? 'ok' : 'info',
			flxi_mime_has_mailparse()
				? __( 'PHP mailparse extension is on: headers and addresses of incoming email are read with it.', 'flx-mail' )
				: __( 'The PHP mailparse extension is missing; the plugin\'s own parser is used (it works). You can turn on "mailparse" in hPanel › PHP Configuration › PHP Extensions.', 'flx-mail' )
		);
	}

	// Posta kutusu günlük gönderim sınırı
	$lim = flxi_limits();
	if ( $lim['daily'] ) {
		$full = get_option( 'flxi_quota_full_days', array() );
		$full = is_array( $full ) ? count( array_filter( $full, function ( $x ) {
			return strtotime( $x ) > time() - 30 * DAY_IN_SECONDS;
		} ) ) : 0;
		foreach ( flxi_quota_summary() as $q ) {
			/* translators: 1: mailbox, 2: used, 3: limit */
			$msg = sprintf( __( '%1$s: %2$s / %3$s recipients in the last 24 hours.', 'flx-mail' ), $q['mailbox'], number_format_i18n( $q['used'] ), number_format_i18n( $q['limit'] ) );
			if ( $q['percent'] >= 100 ) {
				/* translators: %s: time */
				$add( 'quota_' . $q['type'], 'Email', __( 'Daily sending limit', 'flx-mail' ), 'error', $msg . ' ' . sprintf( __( 'Limit reached; emails from this address may not go out until around %s. If this happens often, upgrade to a higher email plan at Hostinger.', 'flx-mail' ), flx_date( $q['next'], 'time' ) . ', ' . flx_date( $q['next'], 'short' ) ), $set . '#sinirlar', __( 'Limits', 'flx-mail' ) );
			} elseif ( $q['percent'] >= 80 ) {
				$add( 'quota_' . $q['type'], 'Email', __( 'Daily sending limit', 'flx-mail' ), 'warning', $msg . ' ' . __( 'Close to the limit; bulk sending pauses and leaves room for individual emails.', 'flx-mail' ), $set . '#sinirlar', __( 'Limits', 'flx-mail' ) );
			} elseif ( $full >= 3 ) {
				/* translators: %s: days */
				$add( 'quota_' . $q['type'], 'Email', __( 'Daily sending limit', 'flx-mail' ), 'warning', $msg . ' ' . sprintf( __( 'The limit was hit on %s days in the last 30 days; consider upgrading to a higher email plan at Hostinger.', 'flx-mail' ), number_format_i18n( $full ) ), $set . '#sinirlar', __( 'Limits', 'flx-mail' ) );
			} else {
				$add( 'quota_' . $q['type'], 'Email', __( 'Daily sending limit', 'flx-mail' ), 'ok', $msg, $set . '#sinirlar', __( 'Limits', 'flx-mail' ) );
			}
		}
	}

	// DNS
	$dns = get_option( 'flxi_dns_check' );
	$dns_url = $set . '#dns';
	if ( is_array( $dns ) ) {
		$smtp_host = strtolower( (string) $s['smtp_host'] );
		if ( '' === $dns['spf'] ) {
			/* translators: %s: alan adı */
			$add( 'spf', 'Email', 'SPF', 'warning', sprintf( __( 'No SPF record was found for %s. Hostinger adds it to DNS when you open a mailbox; if the domain is elsewhere, add "v=spf1 include:_spf.mail.hostinger.com ~all".', 'flx-mail' ), $dns['domain'] ), $dns_url, __( 'Check again', 'flx-mail' ) );
		} elseif ( false !== strpos( $smtp_host, 'hostinger' ) && false === stripos( $dns['spf'], 'hostinger' ) ) {
			$add( 'spf', 'Email', 'SPF', 'warning', __( 'The SPF record does not include Hostinger: ', 'flx-mail' ) . $dns['spf'], $dns_url, __( 'Check again', 'flx-mail' ) );
		} else {
			$add( 'spf', 'Email', 'SPF', 'ok', $dns['spf'] );
		}
		$add( 'dmarc', 'Email', 'DMARC', $dns['dmarc'] ? 'ok' : 'info', $dns['dmarc'] ? $dns['dmarc'] : __( 'There is no DMARC record. Gmail and Yahoo require DMARC for bulk mail; add a "_dmarc" TXT record: "v=DMARC1; p=none; rua=mailto:info@…".', 'flx-mail' ), $dns_url, __( 'Check again', 'flx-mail' ) );
	} else {
		$add( 'spf', 'Email', __( 'SPF / DMARC (DNS)', 'flx-mail' ), 'info', __( 'Not checked.', 'flx-mail' ), $dns_url, __( 'Check DNS', 'flx-mail' ) );
	}

	// Google ile giriş
	$social = flxi_active_social_plugins();
	if ( $social ) {
		/* translators: %s: eklenti adları */
		$msg = sprintf( __( 'Google sign-in plugin: %s.', 'flx-mail' ), implode( ', ', $social ) ) . ' ';
		$msg .= $s['social_block_core'] ? __( 'People who sign up with Google do not receive the set-password email, and their emails are treated as verified.', 'flx-mail' ) : __( 'People who sign up with Google receive the set-password email (setting is off).', 'flx-mail' );
		$add( 'social', 'Plugins', __( 'Google sign-in', 'flx-mail' ), $s['social_block_core'] ? 'ok' : 'warning', $msg, $set . '#google', __( 'Setting', 'flx-mail' ) );
	} else {
		$add( 'social', 'Plugins', __( 'Google sign-in', 'flx-mail' ), 'info', __( 'No known Google sign-in plugin is active. If you use another plugin, a Google return is still detected and the password email is stopped.', 'flx-mail' ), $set . '#google', __( 'Setting', 'flx-mail' ) );
	}

	// Bağlantılar
	$tests = (array) get_option( 'flxi_conn_tests', array() );
	foreach ( array( 'whatsapp' => 'WhatsApp', 'sms' => 'SMS' ) as $ch => $label ) {
		$list = flxi_list_connections( $ch, true );
		if ( ! $list ) {
			/* translators: %s: kanal */
			$add( 'conn.' . $ch, 'Config', $label, 'info', sprintf( __( 'No %s connection.', 'flx-mail' ), $label ), flxi_admin_url( 'flxi-baglantilar' ), __( 'Connections', 'flx-mail' ) );
			continue;
		}
		foreach ( $list as $c ) {
			$t = $tests[ $c['id'] ] ?? null;
			if ( ! $t ) {
				$add( 'conn.' . $c['id'], 'Config', $label . ' · ' . $c['name'], 'info', __( 'Settings entered, not tested yet.', 'flx-mail' ), flxi_admin_url( 'flxi-baglantilar' ), __( 'Test', 'flx-mail' ) );
			} else {
				$when = flx_date( (int) $t['at'], 'datetime' );
				$add( 'conn.' . $c['id'], 'Config', $label . ' · ' . $c['name'], $t['ok'] ? 'ok' : 'error', $t['ok'] ? sprintf( /* translators: %s: tarih */ __( 'Test successful (%s).', 'flx-mail' ), $when ) : sprintf( /* translators: 1: tarih, 2: hata */ __( 'Test failed (%1$s): %2$s', 'flx-mail' ), $when, $t['error'] ), flxi_admin_url( 'flxi-baglantilar' ), __( 'Test', 'flx-mail' ) );
			}
		}
	}

	if ( flxi_flxs_active() ) {
		$add( 'flxs', 'Plugins', __( 'Flux Certificates', 'flx-mail' ), 'ok', __( 'Certificate emails use the corporate template; WhatsApp/SMS go through the connections here.', 'flx-mail' ) );
	}
	return $checks;
}

/* ------------------------------------------------------------------ */
/* MCP ile uygulanabilen düzeltmeler                                   */
/* ------------------------------------------------------------------ */

add_filter( 'flx_system_fixes', 'flxi_system_fixes' );

function flxi_system_fixes( $fixes ) {
	$src    = 'Flux Mail';
	$string = function ( $key, $label, $desc, $checks, $validate ) use ( $src ) {
		return array(
			'label'       => $label,
			'description' => $desc,
			'source'      => $src,
			'checks'      => $checks,
			'value'       => array( 'type' => 'string' ),
			'current'     => function () use ( $key ) {
				return flxi_setting( $key );
			},
			'run'         => function ( $value ) use ( $key, $validate, $label ) {
				$value = call_user_func( $validate, $value );
				$old   = flxi_setting( $key );
				flxi_update_settings( array( $key => $value ) );
				/* translators: 1: alan, 2: eski, 3: yeni */
				return sprintf( __( '%1$s: "%2$s" → "%3$s"', 'flx-mail' ), $label, $old, $value );
			},
		);
	};
	$email = function ( $v ) {
		$v = sanitize_email( (string) $v );
		if ( ! is_email( $v ) ) {
			throw new Exception( __( 'Enter a valid email address.', 'flx-mail' ) );
		}
		return $v;
	};
	$text  = function ( $v ) {
		$v = sanitize_text_field( (string) $v );
		if ( '' === $v ) {
			throw new Exception( __( 'Cannot be empty.', 'flx-mail' ) );
		}
		return $v;
	};

	$fixes['flxi.from_email']    = $string( 'from_email', __( 'Sender email address', 'flx-mail' ), __( 'Address for emails that expect a reply (e.g. bilgi@yourdomain).', 'flx-mail' ), array( 'flxi.from' ), $email );
	$fixes['flxi.from_name']     = $string( 'from_name', __( 'Sender name', 'flx-mail' ), __( 'Name shown in the inbox.', 'flx-mail' ), array( 'flxi.from' ), $text );
	$fixes['flxi.noreply_email'] = $string( 'noreply_email', __( 'No-reply address', 'flx-mail' ), __( 'Address for emails that don\'t expect a reply (passwords, notifications).', 'flx-mail' ), array( 'flxi.from' ), $email );
	$fixes['flxi.reply_to']      = $string(
		'reply_to',
		__( 'Reply-to address (Reply-To)', 'flx-mail' ),
		__( 'Empty: replies go to the sender address.', 'flx-mail' ),
		array( 'flxi.from' ),
		function ( $v ) {
			$v = sanitize_email( (string) $v );
			return is_email( $v ) ? $v : '';
		}
	);
	$fixes['flxi.smtp_server']   = array(
		'label'       => __( 'SMTP server (excluding password)', 'flx-mail' ),
		'description' => __( 'The value is in the format "server:port:ssl|tls|none:user", e.g. "smtp.hostinger.com:465:ssl:bilgi@yourdomain". The password is entered by the admin in the dashboard.', 'flx-mail' ),
		'source'      => $src,
		'checks'      => array( 'flxi.mailer' ),
		'value'       => array( 'type' => 'string' ),
		'current'     => function () {
			$s = flxi_settings();
			return $s['smtp_host'] ? $s['smtp_host'] . ':' . $s['smtp_port'] . ':' . ( $s['smtp_secure'] ? $s['smtp_secure'] : 'yok' ) . ':' . $s['smtp_user'] : '';
		},
		'run'         => function ( $value ) {
			$p = explode( ':', (string) $value, 4 );
			if ( count( $p ) < 2 || '' === trim( $p[0] ) || ! is_numeric( $p[1] ) ) {
				throw new Exception( __( 'Format: server:port:ssl|tls|none:user', 'flx-mail' ) );
			}
			$sec = strtolower( $p[2] ?? 'ssl' );
			flxi_update_settings(
				array(
					'smtp_host'   => sanitize_text_field( $p[0] ),
					'smtp_port'   => (int) $p[1],
					'smtp_secure' => in_array( $sec, array( 'ssl', 'tls' ), true ) ? $sec : '',
					'smtp_user'   => sanitize_text_field( $p[3] ?? '' ),
					'smtp_auth'   => '' !== trim( $p[3] ?? '' ) ? 1 : 0,
				)
			);
			return __( 'SMTP server details saved. The method did not change; the admin must select "SMTP" after entering the password.', 'flx-mail' );
		},
	);
	$fixes['flxi.mailer']        = array(
		'label'       => __( 'Sending method', 'flx-mail' ),
		'description' => __( 'php: the server\'s mail() function; smtp: an SMTP account (the server and password must be entered).', 'flx-mail' ),
		'source'      => $src,
		'checks'      => array( 'flxi.mailer' ),
		'value'       => array( 'type' => 'string', 'enum' => array( 'php', 'smtp' ) ),
		'current'     => function () {
			return flxi_setting( 'mailer' );
		},
		'run'         => function ( $value ) {
			if ( 'smtp' === $value && ( '' === trim( (string) flxi_setting( 'smtp_host' ) ) || ( flxi_setting( 'smtp_auth' ) && '' === flxi_smtp_password() ) ) ) {
				throw new Exception( __( 'The SMTP server or password is missing; the admin must enter it in the dashboard first.', 'flx-mail' ) );
			}
			flxi_update_settings( array( 'mailer' => 'smtp' === $value ? 'smtp' : 'php' ) );
			return 'smtp' === $value ? __( 'SMTP selected.', 'flx-mail' ) : __( 'PHP mail() selected.', 'flx-mail' );
		},
	);
	$fixes['flxi.social_block']  = array(
		'label'       => __( 'Stop the password email on Google sign-up', 'flx-mail' ),
		'source'      => $src,
		'checks'      => array( 'flxi.social' ),
		'value'       => array( 'type' => 'string', 'enum' => array( 'on', 'off' ) ),
		'current'     => function () {
			return flxi_setting( 'social_block_core' ) ? 'on' : 'off';
		},
		'run'         => function ( $value ) {
			$on = 'on' === $value ? 1 : 0;
			flxi_update_settings( array( 'social_block_core' => $on, 'social_block_wc' => $on, 'social_verified' => $on ) );
			return $on ? __( 'Enabled.', 'flx-mail' ) : __( 'Disabled.', 'flx-mail' );
		},
	);
	$fixes['flxi.send_test']     = array(
		'label'       => __( 'Send test email', 'flx-mail' ),
		'description' => __( 'The value is the recipient address; if empty, the site admin\'s address.', 'flx-mail' ),
		'source'      => $src,
		'checks'      => array( 'flxi.test', 'flxi.mailer' ),
		'value'       => array( 'type' => 'string' ),
		'current'     => function () {
			$t = get_option( 'flxi_last_test' );
			return is_array( $t ) ? ( $t['ok'] ? 'ok ' : 'hata ' ) . wp_date( 'Y-m-d H:i', (int) $t['at'] ) : '';
		},
		'run'         => function ( $value ) {
			$req = new WP_REST_Request( 'POST' );
			$req->set_param( 'to', is_email( $value ) ? $value : get_option( 'admin_email' ) );
			$r = flxi_rest_test_email( $req );
			if ( ! $r['ok'] ) {
				throw new Exception( __( 'Could not send: ', 'flx-mail' ) . $r['error'] );
			}
			/* translators: %s: adres */
			return sprintf( __( 'Sent to %s.', 'flx-mail' ), $r['to'] );
		},
	);
	$fixes['flxi.dns_check']     = array(
		'label'   => __( 'Recheck SPF / DMARC records', 'flx-mail' ),
		'source'  => $src,
		'checks'  => array( 'flxi.spf', 'flxi.dmarc' ),
		'current' => function () {
			$d = get_option( 'flxi_dns_check' );
			return is_array( $d ) ? wp_date( 'Y-m-d H:i', (int) $d['at'] ) : '';
		},
		'run'     => function () {
			$d = flxi_rest_dns();
			return 'SPF: ' . ( $d['spf'] ? $d['spf'] : '-' ) . ' · DMARC: ' . ( $d['dmarc'] ? $d['dmarc'] : '-' );
		},
	);
	return $fixes;
}

/* Ekran metinleri: flx_system_checks_labels çevirisi (yalnız bu eklentinin ekranında). */
function flxi_system_checks_labels( $l ) {
	$tr = array(
		'title'       => __( 'System check', 'flx-mail' ),
		'intro'       => __( 'Each row shows something the site needs and whether it is in place. Fix errors first; also review the warnings.', 'flx-mail' ),
		'ok'          => __( 'OK', 'flx-mail' ),
		'warning'     => __( 'Warning', 'flx-mail' ),
		'error'       => __( 'Error', 'flx-mail' ),
		'info'        => __( 'Info', 'flx-mail' ),
		/* translators: 1: tamam, 2: uyarı, 3: hata */
		'summary'     => __( '%1$d OK · %2$d warnings · %3$d errors', 'flx-mail' ),
		'recheck'     => __( 'Check again', 'flx-mail' ),
		'filter_hint' => __( 'Show only these (click again to show all)', 'flx-mail' ),
		/* translators: %s: zaman */
		'checked'     => __( 'Check: %s', 'flx-mail' ),
		'col_status'  => __( 'Status', 'flx-mail' ),
	);
	return array_merge( (array) $l, $tr );
}
