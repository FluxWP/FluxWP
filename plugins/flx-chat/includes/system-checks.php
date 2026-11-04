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
			$c['action_label'] = '' !== $action ? $action : __( 'Ayarları aç', 'flx-chat' );
		}
		$out[] = $c;
	};
	$g_base = __( 'Çağrı Merkezi · Kurulum', 'flx-chat' );
	$g_wa   = __( 'Çağrı Merkezi · WhatsApp', 'flx-chat' );
	$g_ai   = __( 'Çağrı Merkezi · Yapay zekâ', 'flx-chat' );

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
		$add( 'db', $g_base, __( 'Veritabanı tabloları', 'flx-chat' ), 'error', sprintf( __( 'Eksik tablo: %s. Eklentiyi devre dışı bırakıp yeniden etkinleştirin.', 'flx-chat' ), implode( ', ', $miss ) ) );
	} else {
		/* translators: %s: db version */
		$add( 'db', $g_base, __( 'Veritabanı tabloları', 'flx-chat' ), $db_ok ? 'ok' : 'warning', $db_ok ? sprintf( __( 'Tablolar hazır (şema %s).', 'flx-chat' ), FLXC_DB_VERSION ) : __( 'Şema güncellemesi bekliyor; yönetim panelinde bir sayfa açınca tamamlanır.', 'flx-chat' ) );
	}

	$up  = wp_upload_dir( null, false );
	$dir = trailingslashit( $up['basedir'] ) . 'flx-chat';
	if ( ! is_dir( $dir ) ? ! wp_is_writable( $up['basedir'] ) : ! wp_is_writable( $dir ) ) {
		$add( 'media', $g_base, __( 'Medya klasörü', 'flx-chat' ), 'error', __( 'uploads/flx-chat klasörüne yazılamıyor; gelen fotoğraf, ses ve belgeler kaydedilemez. Klasör izinlerini kontrol edin.', 'flx-chat' ) );
	} elseif ( is_dir( $dir ) && ! file_exists( $dir . '/.htaccess' ) ) {
		$add( 'media', $g_base, __( 'Medya klasörü', 'flx-chat' ), 'warning', __( 'Klasör koruması (.htaccess) yok; ilk medya kaydında oluşturulur.', 'flx-chat' ) );
	} else {
		$add( 'media', $g_base, __( 'Medya klasörü', 'flx-chat' ), 'ok', __( 'Yazılabilir ve dışarıya kapalı.', 'flx-chat' ) );
	}

	$add(
		'curl',
		$g_base,
		__( 'PHP cURL', 'flx-chat' ),
		function_exists( 'curl_multi_init' ) ? 'ok' : 'warning',
		function_exists( 'curl_multi_init' ) ? __( 'Var.', 'flx-chat' ) : __( 'Yok; asistan cevap hazırlarken "yazıyor…" göstergesi yalnız kısa süre görünür.', 'flx-chat' )
	);

	$agents = count_users();
	$n_ag   = (int) ( $agents['avail_roles']['flxc_agent'] ?? 0 );
	/* translators: %d: count */
	$add( 'agents', $g_base, __( 'Temsilciler', 'flx-chat' ), 'info', $n_ag ? sprintf( __( 'Temsilci rolündeki kullanıcı sayısı: %d.', 'flx-chat' ), $n_ag ) : __( 'Temsilci rolünde kullanıcı yok; şimdilik yalnız yöneticiler kullanabilir.', 'flx-chat' ) );

	$last_sync = (int) get_option( 'flxc_last_sync', 0 );
	$add(
		'polling',
		$g_base,
		__( 'Panelin canlı güncellemesi', 'flx-chat' ),
		$last_sync && time() - $last_sync < 15 * MINUTE_IN_SECONDS ? 'ok' : 'info',
		$last_sync ? sprintf(
			/* translators: 1: ago, 2: seconds */
			__( 'Son güncelleme isteği %1$s. Panel açıkken %2$d sn\'de bir yeni mesaj sorulur (arka plandaki sekmede 60 sn).', 'flx-chat' ),
			flxc_ago( $last_sync ),
			max( 5, (int) flxc_setting( 'poll_interval' ) )
		) : __( 'Sohbet ekranı henüz hiç açılmadı.', 'flx-chat' ),
		'genel'
	);

	/* --- WhatsApp hatları --- */
	$conns = flxc_connections();
	if ( ! $conns ) {
		$add( 'conn', $g_wa, __( 'WhatsApp hattı', 'flx-chat' ), 'error', __( 'Hiç hat eklenmemiş.', 'flx-chat' ), 'baglantilar', __( 'Hat ekle', 'flx-chat' ) );
	}
	foreach ( $conns as $c ) {
		$name = '' !== trim( $c['label'] ) ? $c['label'] : $c['id'];
		$cfg  = flxc_connection_config( $c );
		/* translators: %s: line name */
		$lbl = sprintf( __( '%s: bağlantı bilgisi', 'flx-chat' ), $name );
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
			$add( 'conn-' . $c['id'], $g_wa, $lbl, 'ok', sprintf( __( 'Flux Mail bağlantısı kullanılıyor: %s.', 'flx-chat' ), '' !== $name_flxi ? $name_flxi : '#' . (int) $c['provider_id'] ) );
		} elseif ( 'flxs' === $origin ) {
			$add(
				'conn-' . $c['id'],
				$g_wa,
				$lbl,
				flxc_flxi_on() ? 'warning' : 'ok',
				flxc_flxi_on() ? __( 'Hâlâ Flux Certificates\'daki eski sağlayıcı kullanılıyor; bağlantıyı Flux Mail\'e taşıyıp burada seçin.', 'flx-chat' ) : __( 'Flux Certificates\'daki Evolution sağlayıcısı kullanılıyor.', 'flx-chat' ),
				flxc_flxi_on() ? 'baglantilar' : ''
			);
		} else {
			$add( 'conn-' . $c['id'], $g_wa, $lbl, 'ok', __( 'API adresi, key ve instance bu eklentide girilmiş.', 'flx-chat' ) );
		}

		$st = flxc_connection_state( $c['id'] );
		/* translators: %s: line name */
		$lbl = sprintf( __( '%s: hat durumu', 'flx-chat' ), $name );
		if ( 'open' === $st['state'] ) {
			/* translators: %s: ago */
			$add( 'state-' . $c['id'], $g_wa, $lbl, 'ok', sprintf( __( 'Telefon bağlı (son bilgi %s).', 'flx-chat' ), flxc_ago( $st['at'] ) ) );
		} elseif ( '' === $st['state'] ) {
			$add( 'state-' . $c['id'], $g_wa, $lbl, 'warning', __( 'Henüz denenmedi. "Bağlantıyı test et" düğmesine basın.', 'flx-chat' ), 'baglantilar', __( 'Test et', 'flx-chat' ) );
		} else {
			/* translators: 1: state, 2: ago */
			$add( 'state-' . $c['id'], $g_wa, $lbl, 'error', sprintf( __( 'Telefon bağlı değil (durum: %1$s, %2$s). "QR ile bağla" ile yeniden bağlayın.', 'flx-chat' ), $st['state'], flxc_ago( $st['at'] ) ), 'baglantilar' );
		}

		$ver = flxc_evo_version( $c['id'], false );
		if ( '' !== $ver && version_compare( $ver, '2.3', '<' ) ) {
			/* translators: %s: line name */
			$lbl = sprintf( __( '%s: Evolution API sürümü', 'flx-chat' ), $name );
			/* translators: %s: Evolution API version */
			$add( 'evo-ver-' . $c['id'], $g_wa, $lbl, 'warning', sprintf( __( 'Evolution API %s kullanılıyor. Gizli numaralı (@lid) kişilere cevap gitmeyebilir; sunucuyu 2.3 veya üstüne güncelleyin.', 'flx-chat' ), $ver ), 'baglantilar' );
		}

		$wh  = (int) get_option( 'flxc_last_webhook_' . $c['id'] );
		/* translators: %s: line name */
		$lbl = sprintf( __( '%s: webhook (gelen mesajlar)', 'flx-chat' ), $name );
		if ( ! $wh ) {
			$add( 'webhook-' . $c['id'], $g_wa, $lbl, 'error', __( 'Evolution\'dan hiç olay gelmedi; gelen mesajlar panele düşmez. "Webhook\'u kur" düğmesine basın.', 'flx-chat' ), 'baglantilar', __( 'Webhook\'u kur', 'flx-chat' ) );
		} elseif ( time() - $wh > DAY_IN_SECONDS ) {
			/* translators: %s: ago */
			$add( 'webhook-' . $c['id'], $g_wa, $lbl, 'warning', sprintf( __( 'Son olay %s geldi. Hat sessiz olabilir; emin olmak için kendinize mesaj atın ya da webhook\'u yeniden kurun.', 'flx-chat' ), flxc_ago( $wh ) ), 'baglantilar' );
		} else {
			/* translators: %s: ago */
			$add( 'webhook-' . $c['id'], $g_wa, $lbl, 'ok', sprintf( __( 'Son olay %s geldi.', 'flx-chat' ), flxc_ago( $wh ) ) );
		}
	}
	$add(
		'flxi',
		$g_wa,
		__( 'Flux Mail entegrasyonu', 'flx-chat' ),
		flxc_flxi_on() ? 'ok' : 'info',
		flxc_flxi_on() ? __( 'Etkin: WhatsApp bağlantıları İletişim › Bağlantılar\'dan alınır.', 'flx-chat' ) : __( 'Flux Mail etkin değil; bağlantılar Flux Certificates\'dan ya da bu eklentiden alınır.', 'flx-chat' )
	);
	$add(
		'flxs',
		$g_wa,
		__( 'Flux Certificates entegrasyonu', 'flx-chat' ),
		function_exists( 'flxs_get_provider' ) ? 'ok' : 'info',
		function_exists( 'flxs_get_provider' ) ? __( 'Etkin: sohbetten sertifika oluşturulup gönderilebilir.', 'flx-chat' ) : __( 'Flux Certificates etkin değil; sohbetten sertifika işlemleri kapalı.', 'flx-chat' )
	);
	$add(
		'flxb',
		$g_wa,
		__( 'Flux Donate entegrasyonu', 'flx-chat' ),
		flxc_flxb_on() ? 'ok' : 'info',
		flxc_flxb_on() ? __( 'Etkin: sohbetten bağış kaydı, dekont eşleştirme ve asistan kullanılabilir.', 'flx-chat' ) : __( 'Flux Donate etkin değil; bağış kaydı, dekont eşleştirme ve bağış asistanı kapalı.', 'flx-chat' )
	);

	/* --- Yapay zekâ --- */
	$ai_label = 'Flux AI · ' . implode( ' + ', flxc_ai_provider_list() );
	if ( ! flxc_ai_hub() ) {
		$add( 'ai', $g_ai, __( 'Yapay zekâ bağlantısı', 'flx-chat' ), flxc_setting( 'bot_enabled' ) ? 'error' : 'warning', __( 'Flux AI eklentisi kurulu değil; yapay zekâ özellikleri (öneri, asistan, dekont okuma, sesli mesaj, özet) kapalı. Flux AI\'yi kurup anahtarı oraya girin.', 'flx-chat' ), 'ai' );
	} elseif ( ! flxc_ai_has_key() ) {
		$add( 'ai', $g_ai, __( 'Yapay zekâ bağlantısı', 'flx-chat' ), 'info', __( 'Flux AI\'de anahtar girilmemiş; mesaj önerisi ve asistan kapalı.', 'flx-chat' ), 'ai' );
	} elseif ( '' === flxc_ai_model() ) {
		$add( 'ai', $g_ai, $ai_label, 'warning', __( 'Anahtar var ama model seçilmemiş.', 'flx-chat' ), 'ai' );
	} elseif ( ! flxc_ai_ready() ) {
		/* translators: %s: model id */
		$add( 'ai', $g_ai, $ai_label, 'error', sprintf( __( 'Seçili model (%s) için Flux AI\'de sağlayıcının anahtarı girilmemiş.', 'flx-chat' ), flxc_ai_model() ), 'ai' );
	} else {
		/* translators: %s: model id */
		$add( 'ai', $g_ai, $ai_label, 'ok', sprintf( __( 'Anahtar kayıtlı, model: %s. Canlı deneme için "Bağlantıyı test et".', 'flx-chat' ), flxc_ai_model() ), 'ai', __( 'Test et', 'flx-chat' ) );
	}

	if ( ! flxc_setting( 'bot_enabled' ) ) {
		$add( 'bot', $g_ai, __( 'Bağış asistanı', 'flx-chat' ), 'info', __( 'Kapalı.', 'flx-chat' ), 'ai' );
	} else {
		$problem = ! flxc_ai_ready() ? __( 'Yapay zekâ anahtarı veya modeli eksik.', 'flx-chat' ) : flxc_ai_types_problem();
		if ( '' !== $problem ) {
			$add( 'bot', $g_ai, __( 'Bağış asistanı', 'flx-chat' ), 'error', __( 'Açık ama konuşmuyor: ', 'flx-chat' ) . $problem, 'ai' );
		} else {
			$names = wp_list_pluck( flxc_ai_types(), 'title' );
			/* translators: %s: type names */
			$add( 'bot', $g_ai, __( 'Bağış asistanı', 'flx-chat' ), 'ok', sprintf( __( 'Açık. Sunulan bağış türleri: %s.', 'flx-chat' ), implode( ', ', $names ) ), 'ai' );

			// Model araç (tools) destekliyor mu? Yalnız önbellekteki model listesine bakılır.
			$cache = get_transient( 'flxc_ai_models_all' );
			if ( is_array( $cache ) ) {
				$bm = flxc_ai_model( 'bot' );
				foreach ( $cache as $m ) {
					if ( $m['id'] === $bm && empty( $m['tools'] ) ) {
						/* translators: %s: model id */
						$add( 'bot-tools', $g_ai, __( 'Asistan modeli', 'flx-chat' ), 'error', sprintf( __( '%s araç (tools) desteklemiyor; asistan için araç destekli bir model seçin.', 'flx-chat' ), $bm ), 'ai' );
					}
				}
			}
			if ( (int) flxc_setting( 'bot_receipt_ai' ) && is_array( $cache ) ) {
				$vm = flxc_rcheck_model();
				foreach ( $cache as $m ) {
					if ( $m['id'] === $vm && isset( $m['vision'] ) && ! $m['vision'] ) {
						/* translators: %s: model id */
						$add( 'bot-vision', $g_ai, __( 'Dekont okuma modeli', 'flx-chat' ), 'warning', sprintf( __( '%s görsel okuyamıyor; dekont ön kontrolü çalışmaz. Görsel destekli bir dekont okuma modeli seçin.', 'flx-chat' ), $vm ), 'ai' );
					}
				}
			}

			$acc = flxc_bot_bank_account();
			$add(
				'bot-bank',
				$g_ai,
				__( 'Asistanın ödeme bilgileri', 'flx-chat' ),
				'' !== $acc['holder'] && '' !== $acc['iban'] ? 'ok' : 'error',
				'' !== $acc['holder'] && '' !== $acc['iban'] ? __( 'Alıcı adı ve IBAN tanımlı.', 'flx-chat' ) : __( 'Alıcı adı veya IBAN eksik; onaydan sonra havale bilgisi gönderilemez.', 'flx-chat' ),
				'ai'
			);
			$add(
				'bot-link',
				$g_ai,
				__( 'Bağış sayfası linki', 'flx-chat' ),
				'' !== flxc_donate_url() ? 'ok' : 'warning',
				'' !== flxc_donate_url() ? flxc_donate_url() : __( 'Bağış sayfası bulunamadı; asistan link göndermez.', 'flx-chat' ),
				'ai'
			);
		}
	}
	// 1.20.0: arka plan isteği (sıradaki cevap, benchmark adımı) siteye ulaşabiliyor mu.
	$lb = get_option( 'flxc_loopback_err' );
	if ( is_array( $lb ) && (int) $lb['at'] > time() - DAY_IN_SECONDS && (int) $lb['at'] > (int) get_option( 'flxc_loopback_ok', 0 ) ) {
		/* translators: 1: time ago, 2: error */
		$add( 'loopback', $g_base, __( 'Arka plan istekleri', 'flx-chat' ), 'warning', sprintf( __( 'Site kendine arka plan isteği atamadı (%1$s: %2$s). Sıradaki cevaplar ve benchmark yavaş ilerleyebilir; güvenlik eklentisi ya da sunucu güvenlik duvarı /wp-json/flx-chat/ isteklerini engelliyor olabilir.', 'flx-chat' ), flxc_ago( (int) $lb['at'] ), (string) $lb['msg'] ) );
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
		<?php foreach ( array( 'error' => __( 'hata', 'flx-chat' ), 'warning' => __( 'uyarı', 'flx-chat' ), 'ok' => __( 'tamam', 'flx-chat' ) ) as $k => $l ) : ?>
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
						<a class="flxcs-btn flxcs-btn-sm" href="<?php echo esc_url( $c['action_url'] ); ?>"><?php echo esc_html( $c['action_label'] ?? __( 'Aç', 'flx-chat' ) ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endforeach; ?>
	<?php
}
