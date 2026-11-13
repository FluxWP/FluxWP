<?php
/**
 * Sistem kontrolü: kurulumun yerli yerinde olup olmadığını gösteren hızlı kontroller.
 *
 * Ortak sözleşme (FluxWP eklentileri + tema): 'flx_system_checks' filtresi. Her kontrol:
 * id, source, group, label, status (ok|warning|error|info), message, action_url?, action_label?
 * Kontroller ucuzdur ve dış HTTP isteği yapmaz. WhatsApp/SMS bağlantı testi Flux Mail'dedir.
 */
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'flx_system_checks_run' ) ) {
	/** Tüm FluxWP kontrollerini toplar ve biçimini düzeltir. */
	function flx_system_checks_run() {
		$out = array();
		foreach ( (array) apply_filters( 'flx_system_checks', array() ) as $c ) {
			if ( ! is_array( $c ) || empty( $c['label'] ) ) {
				continue;
			}
			$c = wp_parse_args( $c, array( 'id' => '', 'source' => '', 'group' => '', 'label' => '', 'status' => 'info', 'message' => '', 'action_url' => '', 'action_label' => '' ) );
			if ( ! in_array( $c['status'], array( 'ok', 'warning', 'error', 'info' ), true ) ) {
				$c['status'] = 'info';
			}
			$out[] = $c;
		}
		return $out;
	}
}

add_filter( 'flx_system_checks', 'flxs_add_system_checks', 20 );
function flxs_add_system_checks( $checks ) {
	if ( ! current_user_can( 'flxs_manage' ) && ! current_user_can( 'manage_options' ) ) {
		return $checks;
	}
	return array_merge( (array) $checks, flxs_system_checks() );
}

/** Kuyruk işleyicisinin son çalışma zamanı (kontrol için; en fazla dakikada bir yazılır) */
function flxs_mark_queue_run() {
	$last = (int) get_option( 'flxs_queue_last_run', 0 );
	if ( time() - $last >= 50 ) {
		update_option( 'flxs_queue_last_run', time(), false );
	}
}

function flxs_ago( $ts ) {
	/* translators: %s: human readable time difference, e.g. "5 mins" */
	return flx_time_ago( (int) $ts );
}

/** Flux Certificates kontrolleri */
function flxs_system_checks() {
	global $wpdb;
	$src   = 'flx-certificates';
	$g_set = __( 'Certificates · Setup', 'flx-certificates' );
	$g_snd = __( 'Certificates · Sending and cron', 'flx-certificates' );
	$g_ste = __( 'Certificates · Site', 'flx-certificates' );
	$page  = function ( $slug ) {
		return admin_url( 'admin.php?page=' . $slug );
	};
	$c = array();
	$add = function ( $id, $group, $label, $status, $message, $url = '', $alabel = '' ) use ( &$c, $src ) {
		$c[] = array( 'id' => 'flxs_' . $id, 'source' => $src, 'group' => $group, 'label' => $label, 'status' => $status, 'message' => $message, 'action_url' => $url, 'action_label' => $alabel );
	};

	/* ---------- Kurulum ---------- */
	$tables  = array( 'people', 'templates', 'certificates', 'batches', 'messages', 'providers', 'logs', 'events' );
	$found   = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'flxs_' ) . '%' ) );
	$missing = array();
	foreach ( $tables as $t ) {
		if ( ! in_array( flxs_table( $t ), $found, true ) ) {
			$missing[] = $t;
		}
	}
	$dbv = (string) get_option( 'flxs_db_version' );
	if ( $missing ) {
		/* translators: %s: comma separated table names */
		$add( 'db', $g_set, __( 'Database tables', 'flx-certificates' ), 'error', sprintf( __( 'Missing table: %s. Deactivate and reactivate the plugin.', 'flx-certificates' ), implode( ', ', $missing ) ), admin_url( 'plugins.php' ), __( 'Plugins', 'flx-certificates' ) );
	} elseif ( FLXS_DB_VERSION !== $dbv ) {
		$add( 'db', $g_set, __( 'Database tables', 'flx-certificates' ), 'warning', __( 'A database update is pending; it completes when an admin page is opened.', 'flx-certificates' ) );
	} else {
		/* translators: 1: plugin version, 2: database version */
		$add( 'db', $g_set, __( 'Database tables', 'flx-certificates' ), 'ok', sprintf( __( 'All tables exist. Plugin %1$s, database version %2$s.', 'flx-certificates' ), FLXS_VERSION, $dbv ) );
	}

	$up = flxs_upload_dir();
	if ( is_dir( $up['dir'] ) && wp_is_writable( $up['dir'] ) ) {
		$add( 'uploads', $g_set, __( 'Files folder', 'flx-certificates' ), 'ok', __( 'The folder for certificate PDFs and images is writable.', 'flx-certificates' ) );
	} else {
		/* translators: %s: folder path relative to wp-content */
		$add( 'uploads', $g_set, __( 'Files folder', 'flx-certificates' ), 'error', sprintf( __( '%s is not writable; certificate files cannot be saved. Check folder permissions (755).', 'flx-certificates' ), str_replace( WP_CONTENT_DIR, 'wp-content', $up['dir'] ) ) );
	}

	$rules = (array) get_option( 'rewrite_rules', array() );
	$key   = '^' . flxs_slug() . '/([A-Za-z0-9]{6,40})/?$';
	$cert_url = home_url( '/' . flxs_slug() . '/' );
	if ( ! get_option( 'permalink_structure' ) ) {
		$add( 'permalinks', $g_set, __( 'Certificate page address', 'flx-certificates' ), 'warning', __( 'Permalinks are set to "Plain"; certificate links work as ?flxs_kod=. "Post name" is recommended.', 'flx-certificates' ), admin_url( 'options-permalink.php' ), __( 'Permalinks', 'flx-certificates' ) );
	} elseif ( ! isset( $rules[ $key ] ) ) {
		$add( 'permalinks', $g_set, __( 'Certificate page address', 'flx-certificates' ), 'error', __( 'The certificate URL rule is not registered; links may return 404. Press "Save Changes" on the Permalinks page.', 'flx-certificates' ), admin_url( 'options-permalink.php' ), __( 'Permalinks', 'flx-certificates' ) );
	} else {
		/* translators: %s: certificate base URL */
		$add( 'permalinks', $g_set, __( 'Certificate page address', 'flx-certificates' ), 'ok', sprintf( __( 'Certificates open at %s {code}.', 'flx-certificates' ), $cert_url ) );
	}

	$tpls = flxs_list_templates( true );
	if ( $tpls ) {
		/* translators: %d: number of templates */
		$add( 'templates', $g_set, __( 'Certificate templates', 'flx-certificates' ), 'ok', sprintf( _n( 'There is %d active template.', 'There are %d active templates.', count( $tpls ), 'flx-certificates' ), count( $tpls ) ) );
	} else {
		$add( 'templates', $g_set, __( 'Certificate templates', 'flx-certificates' ), 'error', __( 'No active template; certificates cannot be created.', 'flx-certificates' ), $page( 'flxs-sablonlar' ), __( 'Templates', 'flx-certificates' ) );
	}

	$files = array( 'assets/vendor/pdf-lib.min.js', 'assets/vendor/fontkit.umd.min.js', 'assets/vendor/pdf.min.js', 'assets/vendor/pdf.worker.min.js', 'assets/vendor/qrcode.js', 'assets/js/renderer.js' );
	foreach ( flxs_builtin_fonts() as $f ) {
		$files[] = 'assets/fonts/' . $f[1];
	}
	$lost = array_filter( $files, function ( $f ) {
		return ! is_readable( FLXS_DIR . $f );
	} );
	if ( $lost ) {
		/* translators: %s: comma separated file names */
		$add( 'files', $g_set, __( 'Plugin files', 'flx-certificates' ), 'error', sprintf( __( 'Missing file: %s. Upload the plugin zip again.', 'flx-certificates' ), implode( ', ', array_map( 'basename', $lost ) ) ) );
	} else {
		$add( 'files', $g_set, __( 'Plugin files', 'flx-certificates' ), 'ok', __( 'PDF renderer, QR and fonts are in place.', 'flx-certificates' ) );
	}

	if ( get_role( 'flxs_personel' ) ) {
		$add( 'role', $g_set, __( 'Staff role', 'flx-certificates' ), 'ok', __( 'The "Certificate Staff" role exists.', 'flx-certificates' ) );
	} else {
		$add( 'role', $g_set, __( 'Staff role', 'flx-certificates' ), 'warning', __( 'The "Certificate Staff" role is missing. Deactivate and reactivate the plugin.', 'flx-certificates' ), admin_url( 'plugins.php' ), __( 'Plugins', 'flx-certificates' ) );
	}

	/* ---------- Gönderim ve cron ---------- */
	$t       = flxs_table( 'messages' );
	$runner  = flxs_queue_runner_name();
	$last    = (int) get_option( 'flxs_queue_last_run', 0 );
	$waiting = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = 'queued' AND scheduled_at <= %s", gmdate( 'Y-m-d H:i:s', time() - 600 ) ) ); // phpcs:ignore
	$no_cron = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	$uses_as = function_exists( 'as_schedule_single_action' );
	if ( ! $uses_as && ! wp_next_scheduled( 'flxs_queue_tick' ) ) {
		$add( 'cron', $g_snd, __( 'Sending queue (cron)', 'flx-certificates' ), 'error', __( 'The per-minute queue task is not scheduled. Reload any admin page; if it persists, reactivate the plugin.', 'flx-certificates' ), $page( 'flxs-kuyruk' ), __( 'Queue', 'flx-certificates' ) );
	} elseif ( $waiting ) {
		/* translators: 1: number of messages, 2: queue runner name, 3: "x mins ago" or "never" */
		$add( 'cron', $g_snd, __( 'Sending queue (cron)', 'flx-certificates' ), 'error', sprintf( _n( '%1$d message has been waiting for more than 10 minutes; the queue may be stalled. Handler: %2$s, last run: %3$s.', '%1$d messages have been waiting for more than 10 minutes; the queue may be stalled. Handler: %2$s, last run: %3$s.', $waiting, 'flx-certificates' ), $waiting, $runner, $last ? flxs_ago( $last ) : __( 'never', 'flx-certificates' ) ) . ( $no_cron ? ' ' . __( 'WP-Cron is disabled (DISABLE_WP_CRON); a per-minute cron for wp-cron.php must be set in the hosting panel.', 'flx-certificates' ) : '' ), $page( 'flxs-kuyruk' ), __( 'Queue', 'flx-certificates' ) );
	} elseif ( $last && time() - $last > 3 * HOUR_IN_SECONDS ) {
		/* translators: 1: queue runner name, 2: "x hours ago" */
		$add( 'cron', $g_snd, __( 'Sending queue (cron)', 'flx-certificates' ), 'warning', sprintf( __( 'Runner: %1$s. Last run %2$s; cron fires rarely. No messages waiting. Without regular traffic, a per-minute cron for wp-cron.php in the hosting panel is recommended.', 'flx-certificates' ), $runner, flxs_ago( $last ) ) );
	} else {
		/* translators: 1: queue runner name, 2: "x mins ago" or "not yet" */
		$add( 'cron', $g_snd, __( 'Sending queue (cron)', 'flx-certificates' ), $last ? 'ok' : 'info', sprintf( __( 'Runner: %1$s. Last run: %2$s. No old messages waiting.', 'flx-certificates' ), $runner, $last ? flxs_ago( $last ) : __( 'not recorded yet', 'flx-certificates' ) ) );
	}

	$failed = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = 'failed' AND updated_at >= %s", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) ); // phpcs:ignore
	if ( $failed ) {
		/* translators: %d: number of failed messages */
		$add( 'failed', $g_snd, __( 'Sending in the last 24 hours', 'flx-certificates' ), 'warning', sprintf( _n( '%d message could not be sent.', '%d messages could not be sent.', $failed, 'flx-certificates' ), $failed ), add_query_arg( 'status', 'failed', $page( 'flxs-kuyruk' ) ), __( 'View failed', 'flx-certificates' ) );
	} else {
		$sent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE status = 'sent' AND sent_at >= %s", gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) ); // phpcs:ignore
		/* translators: %d: number of sent messages */
		$add( 'failed', $g_snd, __( 'Sending in the last 24 hours', 'flx-certificates' ), 'ok', sprintf( _n( '%d message sent without failures.', '%d messages sent without failures.', $sent, 'flx-certificates' ), $sent ) );
	}

	if ( ! wp_next_scheduled( 'flxs_daily' ) ) {
		$add( 'daily', $g_snd, __( 'Daily cleanup task', 'flx-certificates' ), 'warning', __( 'The daily task (old log cleanup) is not scheduled; reload an admin page.', 'flx-certificates' ) );
	}

	// WhatsApp/SMS/e-posta gönderimi Flux Mail üzerinden (v1.3.0). Bağlantı testleri İletişim › Bağlantılar'da.
	$labels = array( 'whatsapp' => 'WhatsApp', 'sms' => 'SMS', 'email' => __( 'Email', 'flx-certificates' ) );
	if ( ! flxs_iletisim_active() ) {
		$add( 'iletisim', $g_snd, __( 'Flux Mail', 'flx-certificates' ), 'error', __( 'Flux Mail is not active. Certificates cannot be sent by WhatsApp or SMS; email goes through WordPress (wp_mail). Install and activate Flux Mail.', 'flx-certificates' ), admin_url( 'plugins.php' ), __( 'Plugins', 'flx-certificates' ) );
	} else {
		flxs_sync_flxi_providers();
		foreach ( array( 'whatsapp', 'sms' ) as $ch ) {
			/* translators: %s: channel name (WhatsApp / SMS) */
			$label = sprintf( __( '%s sending', 'flx-certificates' ), $labels[ $ch ] );
			$conns = flxi_list_connections( $ch, true );
			if ( $conns ) {
				$ok_at = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(sent_at) FROM $t WHERE channel = %s AND status = 'sent'", $ch ) ); // phpcs:ignore
				/* translators: %s: comma separated connection names */
				$msg = sprintf( __( 'Sent through the Flux Mail connection: %s.', 'flx-certificates' ), implode( ', ', wp_list_pluck( $conns, 'name' ) ) );
				if ( $ok_at ) {
					/* translators: %s: "x mins ago" */
					$msg .= ' ' . sprintf( __( 'Last successful send %s.', 'flx-certificates' ), flxs_ago( strtotime( $ok_at . ' UTC' ) ) );
				}
				$add( 'provider_' . $ch, $g_snd, $label, 'ok', $msg, admin_url( 'admin.php?page=flxi-baglantilar' ), __( 'Connections', 'flx-certificates' ) );
			} elseif ( ! flxi_list_connections( $ch ) ) {
				// Kanal hiç kurulmamış: kullanılmıyor demektir, uyarı değil
				/* translators: %s: channel name */
				$add( 'provider_' . $ch, $g_snd, $label, 'info', sprintf( __( 'No %1$s connection is set up; certificates are not sent by %1$s.', 'flx-certificates' ), $labels[ $ch ] ), admin_url( 'admin.php?page=flxi-baglantilar' ), __( 'Connections', 'flx-certificates' ) );
			} else {
				/* translators: %s: channel name */
				$add( 'provider_' . $ch, $g_snd, $label, 'warning', sprintf( __( 'Flux Mail has no active %s connection; certificates cannot be sent on that channel.', 'flx-certificates' ), $labels[ $ch ] ), admin_url( 'admin.php?page=flxi-baglantilar' ), __( 'Connections', 'flx-certificates' ) );
			}
		}
		$add( 'provider_email', $g_snd, $labels['email'], 'ok', __( 'Certificate emails go through Flux Mail with the organization template (Communication › Emails).', 'flx-certificates' ), admin_url( 'admin.php?page=flxi' ), __( 'Emails', 'flx-certificates' ) );
	}

	/* ---------- Site ---------- */
	$theme = wp_get_theme();
	if ( current_theme_supports( 'flx-theme' ) ) {
		/* translators: %s: theme version */
		$add( 'theme', $g_ste, __( 'Theme compatibility', 'flx-certificates' ), 'ok', sprintf( __( 'The FluxWP theme (%s) is active; the events section and certificate page match the theme.', 'flx-certificates' ), $theme->get( 'Version' ) ) );
	} else {
		/* translators: %s: active theme name */
		$add( 'theme', $g_ste, __( 'Theme compatibility', 'flx-certificates' ), 'info', sprintf( __( 'Active theme: %s. The events section appears automatically only in the Örnek Vakfı theme; in other themes add it with a shortcode.', 'flx-certificates' ), $theme->get( 'Name' ) ) );
	}

	$open = flxs_get_events( 'open' );
	if ( ! $open ) {
		$add( 'events', $g_ste, __( 'Ongoing events', 'flx-certificates' ), 'info', __( 'No open events; the events section is hidden on the site.', 'flx-certificates' ), flxs_events_screen_url(), __( 'Events', 'flx-certificates' ) );
	} else {
		$off = array();
		foreach ( $open as $e ) {
			if ( ! (int) $e['post_id'] || 'publish' !== get_post_status( (int) $e['post_id'] ) ) {
				$off[] = $e['name'];
			}
		}
		if ( $off ) {
			/* translators: %s: comma separated event names */
			$add( 'events', $g_ste, __( 'Ongoing events', 'flx-certificates' ), 'warning', sprintf( __( 'Open event without a published page: %s. Fix it with "Publish on site" in the event edit screen.', 'flx-certificates' ), implode( ', ', $off ) ), flxs_events_screen_url(), __( 'Events', 'flx-certificates' ) );
		} else {
			/* translators: %d: number of open events */
			$add( 'events', $g_ste, __( 'Ongoing events', 'flx-certificates' ), 'ok', sprintf( _n( '%d open event has a published page.', '%d open events have published pages.', count( $open ), 'flx-certificates' ), count( $open ) ) );
		}
	}

	if ( flxs_setting( 'lookup_enabled' ) ) {
		$has = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE '%[flx\\_sertifika\\_sorgula%' LIMIT 1" ); // phpcs:ignore
		if ( $has ) {
			/* translators: %s: page URL */
			$add( 'lookup', $g_ste, __( 'Certificate lookup', 'flx-certificates' ), 'ok', sprintf( __( 'The lookup form is on %s.', 'flx-certificates' ), wp_make_link_relative( get_permalink( $has ) ) ) );
		} else {
			$add( 'lookup', $g_ste, __( 'Certificate lookup', 'flx-certificates' ), 'info', __( 'Lookup is on, but no published page has the [flx_sertifika_sorgula] shortcode.', 'flx-certificates' ) );
		}
	}

	if ( defined( 'FLXF_VERSION' ) || function_exists( 'flxf_submit_response' ) || class_exists( 'Emsfb' ) ) {
		$add( 'flxform', $g_ste, __( 'Flux Forms integration', 'flx-certificates' ), 'ok', __( 'Flux Forms is installed; form automations can create and send certificates.', 'flx-certificates' ) );
	}

	/* ---------- Sunucuda PDF ---------- */
	if ( ! flxs_render_available() ) {
		$add( 'render', $g_set, __( 'PDF generation on the server', 'flx-certificates' ), 'warning', __( 'PHP GD (FreeType) extension is missing: PDFs and images are only made in the browser, when the certificate page is first opened.', 'flx-certificates' ) );
	} else {
		$miss = flxs_certificates_missing_files();
		if ( $miss ) {
			/* translators: %d: number of certificates */
			$add( 'render', $g_set, __( 'PDF generation on the server', 'flx-certificates' ), 'warning', sprintf( __( '%d certificate is missing its PDF or image.', 'flx-certificates' ), $miss ), add_query_arg( array( 'action' => 'flxs_render_missing', '_wpnonce' => wp_create_nonce( 'flxs_render_missing' ) ), admin_url( 'admin-post.php' ) ), __( 'Create missing files', 'flx-certificates' ) );
		} else {
			$add( 'render', $g_set, __( 'PDF generation on the server', 'flx-certificates' ), 'ok', __( 'On. PDFs and images for new certificates are generated in the background on the server.', 'flx-certificates' ) );
		}
	}

	return $c;
}

add_filter( 'flx_system_fixes', 'flxs_system_fixes' );
function flxs_system_fixes( $fixes ) {
	if ( ! flxs_render_available() ) {
		return $fixes;
	}
	$fixes['flxs.render_missing'] = array(
		'label'       => __( 'Create missing certificate files', 'flx-certificates' ),
		'description' => __( 'Generates files on the server for up to 25 certificates that are missing a PDF or image.', 'flx-certificates' ),
		'source'      => 'Flux Certificates',
		'checks'      => array( 'flxs_render' ),
		'current'     => function () {
			return flxs_certificates_missing_files();
		},
		'run'         => function () {
			list( $ok, $fail, $queued ) = flxs_render_missing( 25 );
			if ( $queued ) {
				return __( 'Queued', 'flx-certificates' ) . ': ' . number_format_i18n( $queued );
			}
			/* translators: 1: done count, 2: failed count */
			return sprintf( __( 'Files created for %1$d certificates, %2$d errors.', 'flx-certificates' ), $ok, $fail );
		},
	);
	return $fixes;
}

add_action( 'admin_post_flxs_render_missing', 'flxs_handle_render_missing' );
function flxs_handle_render_missing() {
	check_admin_referer( 'flxs_render_missing' );
	if ( ! current_user_can( 'flxs_manage' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed', 'flx-certificates' ) );
	}
	list( $ok, $fail, $queued ) = flxs_render_missing( 25 );
	$left = flxs_certificates_missing_files();
	/* translators: 1: done count, 2: failed count */
	$msg = sprintf( __( 'Files created for %1$d certificates, %2$d errors.', 'flx-certificates' ), $ok, $fail );
	if ( $queued ) {
		$msg = __( 'Queued', 'flx-certificates' ) . ': ' . number_format_i18n( $queued );
	}
	if ( $left && $ok ) {
		/* translators: %d: remaining count */
		$msg .= ' ' . sprintf( __( '%d certificates left, press again.', 'flx-certificates' ), $left );
	}
	wp_safe_redirect( add_query_arg( 'flxs_msg', rawurlencode( $msg ), wp_get_referer() ? wp_get_referer() : admin_url() ) );
	exit;
}
