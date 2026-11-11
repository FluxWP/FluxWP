<?php
/**
 * Sistem kontrolü: kurulumun yerli yerinde olup olmadığını gösteren kontroller.
 *
 * Ortak sözleşme (tema ve diğer FluxWP eklentileriyle aynı): `flx_system_checks` filtresine
 * her kontrol { id, source, group, label, status ok|warning|error|info, message, action_url?, action_label? }
 * olarak eklenir. Kontroller ucuzdur: dış HTTP isteği yapılmaz, yalnız kayıtlı durumlar okunur
 * (canlı bağlantı denemesi Bağlantılar sekmesindeki "Bağlantıyı test et" düğmesindedir).
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'flx_system_checks', 'flxc_system_checks_filter' );
function flxc_system_checks_filter( $checks ) {
	return array_merge( (array) $checks, flxc_system_checks() );
}

/* Panelin son canlı güncelleme (polling) zamanı: en fazla dakikada bir yazılır. */
add_filter(
	'rest_request_after_callbacks',
	function ( $response, $handler, $request ) {
		if ( $request instanceof WP_REST_Request && '/flx-chat/v1/sync' === $request->get_route() && ! is_wp_error( $response ) ) {
			$last = (int) get_option( 'flxc_last_sync', 0 );
			if ( time() - $last > 60 ) {
				update_option( 'flxc_last_sync', time(), false );
			}
		}
		return $response;
	},
	10,
	3
);

/** "5 dk önce" gibi; hiç olmadıysa ''. */
function flxc_ago( $ts ) {
	$ts = (int) $ts;
	if ( ! $ts ) {
		return '';
	}
	/* translators: %s: human time diff */
	return flx_time_ago( $ts );
}

/** Çağrı Merkezi'nin kontrolleri. */
function flxc_system_checks() {
	$out  = array();
	$set  = admin_url( 'admin.php?page=flx-chat-ayarlar&tab=' );
	$add  = function ( $id, $group, $label, $status, $message, $tab = '', $action = '' ) use ( &$out, $set ) {
		$c = array(
			'id'      => 'flx-chat/' . $id,
			'source'  => 'flx-chat',
			'group'   => $group,
			'label'   => $label,
			'status'  => $status,
			'message' => $message,
		);
		if ( '' !== $tab ) {
			$c['action_url']   = $set . $tab;
			$c['action_label'] = '' !== $action ? $action : __( 'Open settings', 'flx-chat' );
		}
		$out[] = $c;
	};
	$g_base = __( 'Call Center · Setup', 'flx-chat' );
	$g_wa   = __( 'Call Center · WhatsApp', 'flx-chat' );
	$g_ai   = __( 'Call Center · AI', 'flx-chat' );

	/* --- Kurulum --- */
	global $wpdb;
	$db_ok = get_option( 'flxc_db_version' ) === FLXC_DB_VERSION;
	$miss  = array();
	foreach ( array( 'chats', 'messages', 'replies', 'receipts', 'bench_runs', 'bench_results' ) as $t ) {
		if ( flxc_table( $t ) !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', flxc_table( $t ) ) ) ) {
			$miss[] = $t;
		}
	}
	if ( $miss ) {
		/* translators: %s: table names */
		$add( 'db', $g_base, __( 'Database tables', 'flx-chat' ), 'error', sprintf( __( 'Missing table: %s. Deactivate and reactivate the plugin.', 'flx-chat' ), implode( ', ', $miss ) ) );
	} else {
		/* translators: %s: db version */
		$add( 'db', $g_base, __( 'Database tables', 'flx-chat' ), $db_ok ? 'ok' : 'warning', $db_ok ? sprintf( __( 'Tables ready (schema %s).', 'flx-chat' ), FLXC_DB_VERSION ) : __( 'Schema update pending; it completes when you open a page in the admin panel.', 'flx-chat' ) );
	}

	$up  = wp_upload_dir( null, false );
	$dir = trailingslashit( $up['basedir'] ) . 'flx-chat';
	if ( ! is_dir( $dir ) ? ! wp_is_writable( $up['basedir'] ) : ! wp_is_writable( $dir ) ) {
		$add( 'media', $g_base, __( 'Media folder', 'flx-chat' ), 'error', __( 'Cannot write to uploads/flx-chat; incoming photos, voice notes and documents cannot be saved. Check folder permissions.', 'flx-chat' ) );
	} elseif ( is_dir( $dir ) && ! file_exists( $dir . '/.htaccess' ) ) {
		$add( 'media', $g_base, __( 'Media folder', 'flx-chat' ), 'warning', __( 'Folder protection (.htaccess) missing; it is created with the first saved media.', 'flx-chat' ) );
	} else {
		$add( 'media', $g_base, __( 'Media folder', 'flx-chat' ), 'ok', __( 'Writable and closed to the public.', 'flx-chat' ) );
	}

	$add(
		'curl',
		$g_base,
		__( 'PHP cURL', 'flx-chat' ),
		function_exists( 'curl_multi_init' ) ? 'ok' : 'warning',
		function_exists( 'curl_multi_init' ) ? __( 'Available.', 'flx-chat' ) : __( 'Missing; while the assistant prepares a reply the "typing…" indicator shows only briefly.', 'flx-chat' )
	);

	$agents = count_users();
	$n_ag   = (int) ( $agents['avail_roles']['flxc_agent'] ?? 0 );
	/* translators: %d: count */
	$add( 'agents', $g_base, __( 'Agents', 'flx-chat' ), 'info', $n_ag ? sprintf( __( 'Users with the agent role: %d.', 'flx-chat' ), $n_ag ) : __( 'No user has the agent role; only administrators can use it for now.', 'flx-chat' ) );

	$last_sync = (int) get_option( 'flxc_last_sync', 0 );
	$add(
		'polling',
		$g_base,
		__( 'Live panel updates', 'flx-chat' ),
		$last_sync && time() - $last_sync < 15 * MINUTE_IN_SECONDS ? 'ok' : 'info',
		$last_sync ? sprintf(
			/* translators: 1: ago, 2: seconds */
			__( 'Last update request %1$s. While the panel is open it checks for new messages every %2$d s (3 s while a reply is streaming, up to 15 s when nothing changes) and once a minute in a background tab.', 'flx-chat' ),
			flxc_ago( $last_sync ),
			max( 5, (int) flxc_setting( 'poll_interval' ) )
		) : __( 'The chat screen has not been opened yet.', 'flx-chat' ),
		'genel'
	);

	/* --- WhatsApp hatları --- */
	$conns = flxc_connections();
	if ( ! $conns ) {
		$add( 'conn', $g_wa, __( 'WhatsApp line', 'flx-chat' ), 'error', __( 'No line added.', 'flx-chat' ), 'baglantilar', __( 'Add line', 'flx-chat' ) );
	}
	foreach ( $conns as $c ) {
		$name = '' !== trim( $c['label'] ) ? $c['label'] : $c['id'];
		$cfg  = flxc_connection_config( $c );
		/* translators: %s: line name */
		$lbl = sprintf( __( '%s: connection details', 'flx-chat' ), $name );
		if ( is_wp_error( $cfg ) ) {
			$add( 'conn-' . $c['id'], $g_wa, $lbl, 'error', $cfg->get_error_message(), 'baglantilar' );
			continue;
		}
		$origin = flxc_connection_origin( $c );
		if ( 'flxi' === $origin ) {
			$name_flxi = '';
			foreach ( flxc_flxi_whatsapp_choices() as $x ) {
				if ( (int) $x['id'] === (int) $c['provider_id'] ) {
					$name_flxi = $x['name'];
				}
			}
			/* translators: %s: connection name */
			$add( 'conn-' . $c['id'], $g_wa, $lbl, 'ok', sprintf( __( 'Using Flux Mail connection: %s.', 'flx-chat' ), '' !== $name_flxi ? $name_flxi : '#' . (int) $c['provider_id'] ) );
		} elseif ( 'flxs' === $origin ) {
			$add(
				'conn-' . $c['id'],
				$g_wa,
				$lbl,
				flxc_flxi_on() ? 'warning' : 'ok',
				flxc_flxi_on() ? __( 'Still using the old provider in Flux Certificates; move the connection to Flux Mail and select it here.', 'flx-chat' ) : __( 'Using the Evolution provider from Flux Certificates.', 'flx-chat' ),
				flxc_flxi_on() ? 'baglantilar' : ''
			);
		} else {
			$add( 'conn-' . $c['id'], $g_wa, $lbl, 'ok', __( 'API URL, key and instance are entered in this plugin.', 'flx-chat' ) );
		}

		$st = flxc_connection_state( $c['id'] );
		/* translators: %s: line name */
		$lbl = sprintf( __( '%s: line status', 'flx-chat' ), $name );
		if ( 'open' === $st['state'] ) {
			/* translators: %s: ago */
			$add( 'state-' . $c['id'], $g_wa, $lbl, 'ok', sprintf( __( 'Phone connected (last seen %s).', 'flx-chat' ), flxc_ago( $st['at'] ) ) );
		} elseif ( '' === $st['state'] ) {
			$add( 'state-' . $c['id'], $g_wa, $lbl, 'warning', __( 'Not tested yet. Press "Test connection".', 'flx-chat' ), 'baglantilar', __( 'Test', 'flx-chat' ) );
		} else {
			/* translators: 1: state, 2: ago */
			$add( 'state-' . $c['id'], $g_wa, $lbl, 'error', sprintf( __( 'Phone not connected (state: %1$s, %2$s). Reconnect with "Connect with QR".', 'flx-chat' ), $st['state'], flxc_ago( $st['at'] ) ), 'baglantilar' );
		}

		$ver = flxc_evo_version( $c['id'], false );
		if ( '' !== $ver && version_compare( $ver, '2.3', '<' ) ) {
			/* translators: %s: line name */
			$lbl = sprintf( __( '%s: Evolution API version', 'flx-chat' ), $name );
			/* translators: %s: Evolution API version */
			$add( 'evo-ver-' . $c['id'], $g_wa, $lbl, 'warning', sprintf( __( 'Evolution API %s is in use. Replies to hidden-number (@lid) contacts may fail; update the server to 2.3 or later.', 'flx-chat' ), $ver ), 'baglantilar' );
		}

		$wh  = (int) get_option( 'flxc_last_webhook_' . $c['id'] );
		/* translators: %s: line name */
		$lbl = sprintf( __( '%s: webhook (incoming messages)', 'flx-chat' ), $name );
		if ( ! $wh ) {
			$add( 'webhook-' . $c['id'], $g_wa, $lbl, 'error', __( 'No events have arrived from Evolution; incoming messages will not show in the panel. Press "Set up webhook".', 'flx-chat' ), 'baglantilar', __( 'Set up webhook', 'flx-chat' ) );
		} elseif ( time() - $wh > DAY_IN_SECONDS ) {
			/* translators: %s: ago */
			$add( 'webhook-' . $c['id'], $g_wa, $lbl, 'warning', sprintf( __( 'Last event arrived %s. The line may just be quiet; send yourself a message or set up the webhook again to be sure.', 'flx-chat' ), flxc_ago( $wh ) ), 'baglantilar' );
		} else {
			/* translators: %s: ago */
			$add( 'webhook-' . $c['id'], $g_wa, $lbl, 'ok', sprintf( __( 'Last event arrived %s.', 'flx-chat' ), flxc_ago( $wh ) ) );
		}
	}
	$add(
		'flxi',
		$g_wa,
		__( 'Flux Mail integration', 'flx-chat' ),
		flxc_flxi_on() ? 'ok' : 'info',
		flxc_flxi_on() ? __( 'Active: WhatsApp connections are taken from Communication › Connections.', 'flx-chat' ) : __( 'Flux Mail is not active; connections are taken from Flux Certificates or from this plugin.', 'flx-chat' )
	);
	$add(
		'flxs',
		$g_wa,
		__( 'Flux Certificates integration', 'flx-chat' ),
		function_exists( 'flxs_get_provider' ) ? 'ok' : 'info',
		function_exists( 'flxs_get_provider' ) ? __( 'Active: certificates can be created and sent from chats.', 'flx-chat' ) : __( 'Flux Certificates is not active; certificate actions in chats are off.', 'flx-chat' )
	);
	$add(
		'flxb',
		$g_wa,
		__( 'Flux Donate integration', 'flx-chat' ),
		flxc_flxb_on() ? 'ok' : 'info',
		flxc_flxb_on() ? __( 'Active: donation records, receipt matching and the assistant are available from chats.', 'flx-chat' ) : __( 'Flux Donate is not active; donation records, receipt matching and the donation assistant are off.', 'flx-chat' )
	);

	/* --- Yapay zekâ --- */
	$ai_label = 'Flux AI · ' . implode( ' + ', flxc_ai_provider_list() );
	if ( ! flxc_ai_hub() ) {
		$add( 'ai', $g_ai, __( 'AI connection', 'flx-chat' ), flxc_setting( 'bot_enabled' ) ? 'error' : 'warning', __( 'The Flux AI & MCP plugin is not installed; AI features (suggestions, assistant, receipt reading, voice messages, summaries) are off. Install Flux AI and enter the key there.', 'flx-chat' ), 'ai' );
	} elseif ( ! flxc_ai_has_key() ) {
		$add( 'ai', $g_ai, __( 'AI connection', 'flx-chat' ), 'info', __( 'No key has been entered in Flux AI; message suggestions and the assistant are off.', 'flx-chat' ), 'ai' );
	} elseif ( '' === flxc_ai_model() ) {
		$add( 'ai', $g_ai, $ai_label, 'warning', __( 'Key saved but no model selected.', 'flx-chat' ), 'ai' );
	} elseif ( ! flxc_ai_ready() ) {
		/* translators: %s: model id */
		$add( 'ai', $g_ai, $ai_label, 'error', sprintf( __( 'No provider key has been entered in Flux AI for the selected model (%s).', 'flx-chat' ), flxc_ai_model() ), 'ai' );
	} else {
		/* translators: %s: model id */
		$add( 'ai', $g_ai, $ai_label, 'ok', sprintf( __( 'Key saved, model: %s. Use "Test connection" for a live check.', 'flx-chat' ), flxc_ai_model() ), 'ai', __( 'Test', 'flx-chat' ) );
	}

	if ( ! flxc_setting( 'bot_enabled' ) ) {
		$add( 'bot', $g_ai, __( 'Donation assistant', 'flx-chat' ), 'info', __( 'Off.', 'flx-chat' ), 'ai' );
	} else {
		$problem = ! flxc_ai_ready() ? __( 'AI key or model is missing.', 'flx-chat' ) : flxc_ai_types_problem();
		if ( '' !== $problem ) {
			$add( 'bot', $g_ai, __( 'Donation assistant', 'flx-chat' ), 'error', __( 'On but not replying: ', 'flx-chat' ) . $problem, 'ai' );
		} else {
			$names = wp_list_pluck( flxc_ai_types(), 'title' );
			/* translators: %s: type names */
			$add( 'bot', $g_ai, __( 'Donation assistant', 'flx-chat' ), 'ok', sprintf( __( 'On. Donation types offered: %s.', 'flx-chat' ), implode( ', ', $names ) ), 'ai' );

			// Model araç (tools) destekliyor mu? Yalnız önbellekteki model listesine bakılır.
			$cache = get_transient( 'flxc_ai_models_all' );
			if ( is_array( $cache ) ) {
				$bm = flxc_ai_model( 'bot' );
				foreach ( $cache as $m ) {
					if ( $m['id'] === $bm && empty( $m['tools'] ) ) {
						/* translators: %s: model id */
						$add( 'bot-tools', $g_ai, __( 'Assistant model', 'flx-chat' ), 'error', sprintf( __( '%s does not support tools; choose a tool-capable model for the assistant.', 'flx-chat' ), $bm ), 'ai' );
					}
				}
			}
			if ( (int) flxc_setting( 'bot_receipt_ai' ) && is_array( $cache ) ) {
				$vm = flxc_rcheck_model();
				foreach ( $cache as $m ) {
					if ( $m['id'] === $vm && isset( $m['vision'] ) && ! $m['vision'] ) {
						/* translators: %s: model id */
						$add( 'bot-vision', $g_ai, __( 'Receipt reading model', 'flx-chat' ), 'warning', sprintf( __( '%s cannot read images; the receipt pre-check will not work. Choose an image-capable receipt reading model.', 'flx-chat' ), $vm ), 'ai' );
					}
				}
			}

			$acc = flxc_bot_bank_account();
			$add(
				'bot-bank',
				$g_ai,
				__( 'Assistant payment details', 'flx-chat' ),
				'' !== $acc['holder'] && '' !== $acc['iban'] ? 'ok' : 'error',
				'' !== $acc['holder'] && '' !== $acc['iban'] ? __( 'Recipient name and IBAN set.', 'flx-chat' ) : __( 'Recipient name or IBAN missing; bank details cannot be sent after confirmation.', 'flx-chat' ),
				'ai'
			);
			$add(
				'bot-link',
				$g_ai,
				__( 'Donation page link', 'flx-chat' ),
				'' !== flxc_donate_url() ? 'ok' : 'warning',
				'' !== flxc_donate_url() ? flxc_donate_url() : __( 'Donation page not found; the assistant will not send a link.', 'flx-chat' ),
				'ai'
			);
		}
	}
	// 1.20.0: arka plan isteği (sıradaki cevap, benchmark adımı) siteye ulaşabiliyor mu.
	$lb = get_option( 'flxc_loopback_err' );
	if ( is_array( $lb ) && (int) $lb['at'] > time() - DAY_IN_SECONDS && (int) $lb['at'] > (int) get_option( 'flxc_loopback_ok', 0 ) ) {
		/* translators: 1: time ago, 2: error */
		$add( 'loopback', $g_base, __( 'Background requests', 'flx-chat' ), 'warning', sprintf( __( 'The site could not send a background request to itself (%1$s: %2$s). Queued replies and the benchmark may move slowly; a security plugin or the server firewall may be blocking /wp-json/flx-chat/ requests.', 'flx-chat' ), flxc_ago( (int) $lb['at'] ), (string) $lb['msg'] ) );
	}
	return $out;
}

/** Ayarlar › Sistem kontrolü sekmesinde gösterim (yalnız bu eklentinin kontrolleri). */
function flxc_render_system_checks( array $checks ) {
	$icons  = array( 'ok' => '✓', 'warning' => '!', 'error' => '×', 'info' => 'i' );
	$groups = array();
	foreach ( $checks as $c ) {
		$groups[ $c['group'] ][] = $c;
	}
	$count = array_count_values( wp_list_pluck( $checks, 'status' ) );
	?>
	<div class="flxcs-chk-sum">
		<?php foreach ( array( 'error' => __( 'error', 'flx-chat' ), 'warning' => __( 'warning', 'flx-chat' ), 'ok' => __( 'ok', 'flx-chat' ) ) as $k => $l ) : ?>
			<span class="flxcs-chk-pill is-<?php echo esc_attr( $k ); ?>"><?php echo (int) ( $count[ $k ] ?? 0 ) . ' ' . esc_html( $l ); ?></span>
		<?php endforeach; ?>
	</div>
	<?php foreach ( $groups as $g => $rows ) : ?>
		<h3 class="flxcs-h3"><?php echo esc_html( $g ); ?></h3>
		<ul class="flxcs-chk">
			<?php foreach ( $rows as $c ) : ?>
				<li class="flxcs-chk-row is-<?php echo esc_attr( $c['status'] ); ?>">
					<span class="flxcs-chk-ic" aria-hidden="true"><?php echo esc_html( $icons[ $c['status'] ] ?? 'i' ); ?></span>
					<div class="flxcs-chk-tx"><strong><?php echo esc_html( $c['label'] ); ?></strong><span><?php echo esc_html( $c['message'] ); ?></span></div>
					<?php if ( ! empty( $c['action_url'] ) ) : ?>
						<a class="flxcs-btn flxcs-btn-sm" href="<?php echo esc_url( $c['action_url'] ); ?>"><?php echo esc_html( $c['action_label'] ?? __( 'Open', 'flx-chat' ) ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endforeach; ?>
	<?php
}
