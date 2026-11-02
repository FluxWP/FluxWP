<?php
/**
 * Yapay zekâ: 1.30.0 (Ahmet) itibarıyla tüm istekler ortak Flux AI eklentisi üzerinden gider (anahtarlar, model
 * kataloğu, harcama sınırı, kayıtlar tek yerde). Flux AI kurulu değilse yapay zekâ özellikleri kapalıdır; bu
 * eklentinin kendi OpenRouter/Anthropic bağlantısı yoktur. Model seçimleri, öneri, asistan, raporlar burada kalır.
 *
 * Model kimlikleri Flux AI biçiminde: OpenRouter modelleri olduğu gibi (ör. google/gemini-2.5-flash), Anthropic
 * modelleri "claude:" önekiyle (ör. claude:claude-sonnet-5-5).
 *
 * Başka bir öneri sağlayıcısı flxc_ai_providers filtresiyle eklenebilir.
 *
 * @package FLX_Cagri
 */

defined( 'ABSPATH' ) || exit;

/**
 * Claude prompt önbelleği: off | 5m | 1h. Test sohbeti ve benchmark kendi seçimini verebilir;
 * boş ya da geçersizse Flux AI'deki ayar.
 */
function flxc_ai_cache_mode( $override = '' ) {
	$ok = array( 'off', '5m', '1h' );
	if ( in_array( (string) $override, $ok, true ) ) {
		return (string) $override;
	}
	$s = function_exists( 'flxai_setting' ) ? (string) flxai_setting( 'cache', '5m' ) : '5m';
	return in_array( $s, $ok, true ) ? $s : '5m';
}

/** Önbellek modunun adı. */
function flxc_ai_cache_label( $mode ) {
	$l = array(
		'off' => __( 'Kapalı', 'flx-chat' ),
		'5m'  => __( 'Açık, 5 dakika', 'flx-chat' ),
		'1h'  => __( 'Açık, 1 saat', 'flx-chat' ),
	);
	return $l[ $mode ] ?? (string) $mode;
}

/** Flux AI eklentisi yüklü mü (gerekli API'leriyle)? */
function flxc_ai_hub() {
	return function_exists( 'flxai_chat' ) && function_exists( 'flxai_provider_key' ) && function_exists( 'flxai_parse_model' ) && function_exists( 'flxai_models' );
}

/** Modelin sağlayıcısı: anthropic | openrouter. */
function flxc_ai_model_provider( $model ) {
	if ( flxc_ai_hub() ) {
		return flxai_parse_model( (string) $model )['provider']; // "claude-…" öneksiz de olur
	}
	return 0 === strpos( (string) $model, 'claude:' ) ? 'anthropic' : 'openrouter';
}

/** Sağlayıcının anahtarı (Flux AI'de). Yalnız var/yok kontrolü için; hiçbir yere yazılmaz. */
function flxc_ai_provider_key( $provider ) {
	return flxc_ai_hub() ? (string) flxai_provider_key( $provider ) : '';
}

/**
 * Flux AI'ye istek: mesajlar aynı (OpenAI) biçimde. Sistem metni "## Bilinenler"den bölünür (sabit kısım önbellek
 * kırılma noktası). Claude düşünme blokları araç turlarında korunur (_raw ↔ tool_calls[0]._claude). Hata kodları
 * bu eklentinin kodlarına çevrilir (yeniden deneme / hesap hatası kararları aynı kalsın).
 */
function flxc_ai_hub_chat( array $messages, array $opts, $model ) {
	foreach ( $messages as $i => $m ) {
		if ( 'system' === ( $m['role'] ?? '' ) && is_string( $m['content'] ?? null ) ) {
			$cut = strpos( $m['content'], "\n\n## Bilinenler\n" );
			if ( false !== $cut ) {
				$messages[ $i ]['content'] = array(
					array( 'type' => 'text', 'text' => substr( $m['content'], 0, $cut ), 'cache' => true ),
					array( 'type' => 'text', 'text' => substr( $m['content'], $cut + 2 ) ),
				);
			}
		}
		$tc = $m['tool_calls'][0] ?? null;
		if ( is_array( $tc ) && isset( $tc['_claude'] ) ) {
			$messages[ $i ]['_raw']       = $tc['_claude'];
			$messages[ $i ]['_raw_model'] = (string) ( $tc['_claude_model'] ?? '' );
		}
	}
	$messages = flxc_ai_strip_claude( $messages );
	$o        = array(
		'source'      => 'flx-chat',
		'purpose'     => (string) ( $opts['purpose'] ?? 'suggest' ),
		'model'       => $model,
		'max_tokens'  => (int) ( $opts['max_tokens'] ?? 700 ),
		'temperature' => (float) ( $opts['temperature'] ?? flxc_setting( 'ai_temperature' ) ),
		'timeout'     => (int) ( $opts['timeout'] ?? 45 ),
		'cache'       => flxc_ai_cache_mode( (string) ( $opts['cache'] ?? '' ) ),
	);
	foreach ( array( 'tools', 'plugins', 'effort', 'chat_id' ) as $k ) {
		if ( ! empty( $opts[ $k ] ) ) {
			$o[ $k ] = $opts[ $k ];
		}
	}
	if ( 'bot' === $o['purpose'] || ! is_user_logged_in() ) {
		$o['user_id'] = 0; // WhatsApp asistanı panel kullanıcısı adına değil
	}
	$r = flxai_chat( $messages, $o );
	if ( is_wp_error( $r ) ) {
		$map  = array(
			'flxai_limit' => 'flxc_ai_api',
			'flxai_key'   => 'flxc_ai_key',
			'flxai_model' => 'flxc_ai_model',
			'flxai_http'  => 'flxc_ai_http',
			'flxai_empty' => 'flxc_ai_empty',
			'flxai_api'   => 'flxc_ai_api',
		);
		$code = $r->get_error_code();
		$data = (array) $r->get_error_data();
		if ( 'flxai_limit' === $code || flxai_is_account_error( $r ) ) {
			// Toplu işler (benchmark, özet) dursun: flxc_ai_is_account_error 402 görür.
			$data['http'] = in_array( (int) ( $data['http'] ?? 0 ), array( 401, 402, 429 ), true ) ? (int) $data['http'] : 402;
			$data['msg']  = (string) ( $data['msg'] ?? $r->get_error_message() );
		}
		return new WP_Error( $map[ $code ] ?? 'flxc_ai_api', $r->get_error_message(), $data );
	}
	if ( ! empty( $r['_raw'] ) && ! empty( $r['tool_calls'][0] ) ) {
		$r['tool_calls'][0]['_claude']       = $r['_raw'];
		$r['tool_calls'][0]['_claude_model'] = (string) ( $r['_raw_model'] ?? '' );
	}
	unset( $r['_raw'], $r['_raw_model'], $r['_log_id'] );
	$u           = (array) ( $r['_usage'] ?? array() );
	$used        = '' !== $model ? $model : ( function_exists( 'flxai_model_for' ) ? flxai_model_for( 'flx-chat' ) : '' );
	$r['_usage'] = array(
		'in'    => (int) ( $u['in'] ?? 0 ),
		'out'   => (int) ( $u['out'] ?? 0 ),
		'cost'  => (float) ( $u['cost'] ?? 0 ),
		'est'   => ! empty( $u['est'] ),
		'model' => (string) ( $u['model'] ?? $used ),
		'cr'    => (int) ( $u['cr'] ?? 0 ),
		'cw'    => (int) ( $u['cw'] ?? 0 ),
		'full'  => (float) ( $u['full'] ?? ( $u['cost'] ?? 0 ) ),
		'cache' => $o['cache'],
	);
	flxc_ai_track_usage( array( 'prompt_tokens' => $r['_usage']['in'], 'completion_tokens' => $r['_usage']['out'], 'total_tokens' => $r['_usage']['in'] + $r['_usage']['out'], 'cost' => $r['_usage']['cost'] ) );
	return $r;
}

/** Flux AI'de anahtarı girilmiş sağlayıcılar (hiçbiri yoksa OpenRouter: listesi anahtarsız da görülür). */
function flxc_ai_provider_list() {
	$on = flxc_ai_hub() && function_exists( 'flxai_providers_ready' ) ? (array) flxai_providers_ready() : array();
	return $on ? $on : array( 'openrouter' => 'OpenRouter' );
}

function flxc_ai_has_key() {
	return flxc_ai_hub() && function_exists( 'flxai_providers_ready' ) && (bool) flxai_providers_ready();
}

function flxc_ai_model( $purpose = 'suggest' ) {
	$m = 'bot' === $purpose ? trim( (string) flxc_setting( 'ai_model_bot' ) ) : '';
	$m = '' !== $m ? $m : trim( (string) flxc_setting( 'ai_model' ) );
	// Model seçilmemişse Flux AI'de bu eklentiye seçilen model ya da varsayılan.
	return '' === $m && flxc_ai_hub() && function_exists( 'flxai_model_for' ) ? (string) flxai_model_for( 'flx-chat' ) : $m;
}

function flxc_ai_ready() {
	$m = flxc_ai_model();
	return flxc_ai_hub() && '' !== $m && '' !== flxc_ai_provider_key( flxc_ai_model_provider( $m ) );
}

/* Yerleşik öneri sağlayıcısı. */
add_filter(
	'flxc_ai_providers',
	function ( $list ) {
		if ( flxc_ai_ready() ) {
			$m    = flxc_ai_model();
			$list = array(
				'openrouter' => array(
					'label'   => function_exists( 'flxai_model_label' ) ? 'Flux AI · ' . flxai_model_label( $m ) : 'Flux AI · ' . $m,
					'suggest' => 'flxc_ai_openrouter_suggest',
				),
			) + $list;
		}
		return $list;
	},
	5
);

/**
 * Sohbet tamamlama isteği (Flux AI üzerinden). Giriş ve çıkış OpenAI biçiminde.
 *
 * @param array $messages [{role, content, ...}]
 * @param array $opts model, tools, temperature, max_tokens, timeout, purpose, cache
 * @return array|WP_Error assistant mesajı (content, tool_calls, _usage)
 */
function flxc_ai_chat( array $messages, array $opts = array() ) {
	if ( ! flxc_ai_hub() ) {
		return new WP_Error( 'flxc_ai_key', __( 'Yapay zekâ için Flux AI eklentisi gerekli; kurup etkinleştirin ve Flux AI › Ayarlar\'a anahtarı girin.', 'flx-chat' ) );
	}
	$model = (string) ( $opts['model'] ?? flxc_ai_model( $opts['purpose'] ?? 'suggest' ) );
	if ( '' === $model ) {
		return new WP_Error( 'flxc_ai_model', __( 'Yapay zekâ modeli seçilmemiş.', 'flx-chat' ) );
	}
	if ( '' === flxc_ai_provider_key( flxc_ai_model_provider( $model ) ) ) {
		return new WP_Error( 'flxc_ai_key', 'anthropic' === flxc_ai_model_provider( $model ) ? __( 'Flux AI\'de Anthropic (Claude) anahtarı girilmemiş.', 'flx-chat' ) : __( 'Flux AI\'de OpenRouter anahtarı girilmemiş.', 'flx-chat' ) );
	}
	return flxc_ai_hub_chat( $messages, $opts, $model );
}

/** Araç turlarında saklanan Claude alanları (_claude, _claude_model) Flux AI'ye gitmeden önce silinir. */
function flxc_ai_strip_claude( array $messages ) {
	foreach ( $messages as $i => $m ) {
		if ( ! empty( $m['tool_calls'] ) && is_array( $m['tool_calls'] ) ) {
			foreach ( $m['tool_calls'] as $j => $c ) {
				unset( $messages[ $i ]['tool_calls'][ $j ]['_claude'], $messages[ $i ]['tool_calls'][ $j ]['_claude_model'] );
			}
		}
	}
	return $messages;
}

/** Aylık kullanım: istek, token ve (OpenRouter bildiriyorsa) maliyet. */
function flxc_ai_track_usage( $u ) {
	$month = gmdate( 'Y-m' );
	$s     = get_option( 'flxc_ai_usage', array() );
	if ( ! is_array( $s ) || ( $s['month'] ?? '' ) !== $month ) {
		$s = array( 'month' => $month, 'calls' => 0, 'tokens' => 0, 'in' => 0, 'out' => 0, 'cost' => 0.0 );
	}
	$s['calls']++;
	$s['tokens'] += (int) ( $u['total_tokens'] ?? 0 );
	$s['in']      = (int) ( $s['in'] ?? 0 ) + (int) ( $u['prompt_tokens'] ?? 0 );
	$s['out']     = (int) ( $s['out'] ?? 0 ) + (int) ( $u['completion_tokens'] ?? 0 );
	$s['cost']   += (float) ( $u['cost'] ?? 0 );
	update_option( 'flxc_ai_usage', $s, false );
}

/** Bu ayın toplam yapay zekâ kullanımı (öneri, asistan, düzenleyici, test). */
function flxc_ai_month_usage() {
	$s = get_option( 'flxc_ai_usage', array() );
	if ( ! is_array( $s ) || ( $s['month'] ?? '' ) !== gmdate( 'Y-m' ) ) {
		$s = array();
	}
	return array(
		'in'    => (int) ( $s['in'] ?? 0 ),
		'out'   => (int) ( $s['out'] ?? 0 ),
		'cost'  => (float) ( $s['cost'] ?? 0 ),
		'calls' => (int) ( $s['calls'] ?? 0 ),
	);
}

/**
 * Flux AI'nin birleşik model kataloğu. Her model: id, name, family, provider, ctx, in/out (1M token başına USD),
 * tools, vision. Liste "flxc_ai_models_all" geçicisinde de tutulur (sistem kontrolü dış istek atmadan okur).
 *
 * @return array|WP_Error
 */
function flxc_ai_models( $fresh = false ) {
	if ( ! flxc_ai_hub() ) {
		return new WP_Error( 'flxc_ai_models', __( 'Model listesi için Flux AI eklentisi gerekli.', 'flx-chat' ) );
	}
	$l = flxai_models( $fresh );
	if ( is_array( $l ) && $l ) {
		set_transient( 'flxc_ai_models_all', $l, 12 * HOUR_IN_SECONDS );
	}
	return $l;
}

function flxc_ai_models_flush() {
	delete_transient( 'flxc_ai_models_all' );
	if ( function_exists( 'flxai_models_flush' ) ) {
		flxai_models_flush();
	}
}

/* ---------- 1.30.0: eski anahtarların Flux AI'ye aktarımı ---------- */

/**
 * Bu eklentide kalmış anahtarları (ai_key, ai_key_claude, ai_claude_workspace) Flux AI'ye taşır. Flux AI'de o
 * sağlayıcının anahtarı yoksa yazılır; aynısı varsa yalnız buradaki kopya silinir; farklıysa Flux AI'deki kalır,
 * buradaki dokunulmadan bırakılır (notice). Modeller: Flux AI'de bu eklentiye model seçilmemişse buradaki
 * "Model (öneriler için)" oraya da yazılır. Anahtarlar hiçbir yere loglanmaz.
 *
 * @return array { moved: [provider], same: [provider], conflict: [provider] }
 */
function flxc_ai_migrate() {
	$out = array( 'moved' => array(), 'same' => array(), 'conflict' => array() );
	if ( ! flxc_ai_hub() || ! function_exists( 'flxai_setting' ) || ( ! function_exists( 'flxai_import_config' ) && ! function_exists( 'flxai_update_settings' ) ) ) {
		return $out;
	}
	$raw   = get_option( 'flxc_settings', array() );
	$raw   = is_array( $raw ) ? $raw : array();
	$keys  = array(
		'ai_key'        => array( 'openrouter', 'key_openrouter' ),
		'ai_key_claude' => array( 'anthropic', 'key_anthropic' ),
	);
	$mine  = array();
	$empty = array();
	foreach ( $keys as $f => $to ) {
		$mine[ $f ]  = trim( (string) ( $raw[ $f ] ?? '' ) );
		$empty[ $f ] = '' === (string) flxai_setting( $to[1] );
	}
	$ws    = trim( (string) ( $raw['ai_claude_workspace'] ?? '' ) );
	$model = trim( (string) ( $raw['ai_model'] ?? '' ) );
	// Flux AI yalnız boş alanları doldurur, dolu olanlara dokunmaz.
	$pay = array_filter(
		array(
			'key_openrouter'      => $mine['ai_key'],
			'key_anthropic'       => $mine['ai_key_claude'],
			'anthropic_workspace' => $ws,
			'source_model'        => $model,
		),
		'strlen'
	);
	if ( $pay ) {
		if ( function_exists( 'flxai_import_config' ) ) {
			flxai_import_config( 'flx-chat', $pay );
		} else {
			$set = array();
			foreach ( array( 'key_openrouter', 'key_anthropic', 'anthropic_workspace' ) as $k ) {
				if ( isset( $pay[ $k ] ) && '' === (string) flxai_setting( $k, '' ) ) {
					$set[ $k ] = $pay[ $k ];
				}
			}
			$map = (array) flxai_setting( 'source_models', array() );
			if ( isset( $pay['source_model'] ) && empty( $map['flx-chat'] ) ) {
				$map['flx-chat'] = $pay['source_model'];
				$set['source_models']    = $map;
			}
			if ( $set ) {
				flxai_update_settings( $set );
			}
		}
		flxc_ai_models_flush();
	}
	// Sonucu Flux AI'den geri okuyarak sınıfla; Flux AI'de aynısı olan anahtarın buradaki kopyası silinir.
	$drop = array();
	foreach ( $keys as $f => $to ) {
		if ( '' === $mine[ $f ] ) {
			continue;
		}
		$now = (string) flxai_setting( $to[1] );
		if ( '' !== $now && hash_equals( $now, $mine[ $f ] ) ) {
			$out[ $empty[ $f ] ? 'moved' : 'same' ][] = $to[0];
			$drop[] = $f;
		} else {
			$out['conflict'][] = $to[0];
		}
	}
	if ( '' !== $ws && ( in_array( 'ai_key_claude', $drop, true ) || '' === $mine['ai_key_claude'] ) ) {
		$drop[] = 'ai_claude_workspace';
	}
	$drop[] = 'ai_cache'; // önbellek ayarı artık Flux AI'de
	foreach ( $drop as $f ) {
		unset( $raw[ $f ] );
	}
	update_option( 'flxc_settings', $raw, false );
	flxc_settings( true );
	update_option( 'flxc_ai_migrated', array( 'at' => time() ) + $out, false );
	return $out;
}

add_action(
	'admin_init',
	function () {
		if ( ! flxc_ai_hub() || ! flxc_can_manage() ) {
			return;
		}
		$raw = get_option( 'flxc_settings', array() );
		if ( is_array( $raw ) && ( '' !== trim( (string) ( $raw['ai_key'] ?? '' ) ) || '' !== trim( (string) ( $raw['ai_key_claude'] ?? '' ) ) ) ) {
			$m = get_option( 'flxc_ai_migrated', array() );
			// Çakışma varsa her sayfada yeniden denemez; kullanıcı Flux AI'de düzeltince "Yeniden aktar" ile.
			if ( empty( $m['conflict'] ) ) {
				flxc_ai_migrate();
			}
		}
	}
);

add_action(
	'admin_notices',
	function () {
		if ( ! flxc_can_manage() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$ours   = $screen && false !== strpos( (string) $screen->id, 'flx-chat' );
		if ( ! flxc_ai_hub() ) {
			if ( $ours ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'Flux Chat: yapay zekâ özellikleri (öneri, asistan, dekont okuma, sesli mesaj, özet) için Flux AI eklentisi gerekli. Kurup etkinleştirin; Çağrı Merkezi\'nde kayıtlı anahtarlar oraya kendiliğinden aktarılır.', 'flx-chat' ) . '</p></div>';
			}
			return;
		}
		$m = get_option( 'flxc_ai_migrated', array() );
		if ( ! empty( $m['conflict'] ) && $ours ) {
			$names = array_map(
				function ( $p ) {
					return 'anthropic' === $p ? 'Anthropic (Claude)' : 'OpenRouter';
				},
				(array) $m['conflict']
			);
			/* translators: %s: provider names */
			echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( __( 'Flux Chat: %s anahtarı Flux AI\'de farklı bir anahtar olarak zaten kayıtlı; Flux AI\'deki kullanılıyor. Çağrı Merkezi\'ndeki eski anahtar aktarılmadı ve artık kullanılmıyor.', 'flx-chat' ), implode( ', ', $names ) ) ) . '</p></div>';
		} elseif ( ! empty( $m['moved'] ) && empty( $m['seen'] ) && $ours ) {
			$m['seen'] = 1;
			update_option( 'flxc_ai_migrated', $m, false );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Flux Chat: yapay zekâ anahtarları Flux AI\'ye aktarıldı. Bundan sonra anahtar, harcama sınırı ve kayıtlar Flux AI\'den yönetilir; model seçimleri Çağrı Merkezi\'nde kalır.', 'flx-chat' ) . '</p></div>';
		}
	}
);

/* ---------- Bağlam: kurum, bağış türleri, banka ---------- */

/** Bota ve öneriye verilen ortak kurum bilgisi. */
function flxc_ai_context_text() {
	$parts = array();
	$extra = trim( (string) flxc_setting( 'ai_instructions' ) );
	if ( '' !== $extra ) {
		$parts[] = "## Kurum hakkında / talimatlar\n" . $extra;
	}
	$types = flxc_ai_types_text();
	if ( '' !== $types ) {
		$parts[] = "## Bağış türleri ve fiyatlar\n" . $types;
	}
	$bank = flxc_bank_text();
	if ( '' !== $bank ) {
		$parts[] = "## Havale / EFT bilgisi\n" . $bank;
	}
	$url = flxc_donate_url();
	if ( '' !== $url ) {
		$parts[] = "## Bağış sayfası\n" . $url;
	}
	$parts[] = '## Şu an' . "\n" . wp_date( 'l j F Y H:i' ) . ( flxc_is_open_hours() ? '' : ' (mesai dışı)' );
	return implode( "\n\n", $parts );
}

/** Asistanın sunduğu bağış türleri (açık olanlar; ayarda seçilenlerle sınırlı). */
function flxc_ai_types() {
	static $cache = null;
	if ( ! function_exists( 'flxb_get_types' ) ) {
		return array();
	}
	// Sohbet listesinde her satır için sorulur; istek boyunca bir kez hesaplanır.
	if ( null !== $cache ) {
		return $cache;
	}
	$only = array_map( 'intval', (array) flxc_setting( 'bot_types' ) );
	$out  = array();
	foreach ( (array) flxb_get_types( array( 'status' => 'open', 'progress' => false ) ) as $t ) {
		if ( $only && ! in_array( (int) $t['id'], $only, true ) ) {
			continue;
		}
		$opts = array();
		foreach ( (array) ( $t['options'] ?? array() ) as $o ) {
			if ( ( isset( $o['active'] ) && ! $o['active'] ) || null === ( $o['price'] ?? null ) || (float) $o['price'] <= 0 ) {
				continue;
			}
			$opts[] = array(
				'key'    => (string) $o['key'],
				'label'  => (string) $o['label'],
				'price'  => (float) $o['price'],
				'unit'   => (string) ( $o['unit_label'] ?? '' ),
				'units'  => (string) ( $o['unit_label_plural'] ?? ( $o['unit_label'] ?? '' ) ),
			);
		}
		if ( ! $opts && empty( $t['allow_free'] ) ) {
			continue;
		}
		$out[] = array(
			'id'       => (int) $t['id'],
			'slug'     => (string) ( $t['slug'] ?? '' ),
			'title'    => (string) $t['title'],
			'desc'     => wp_trim_words( wp_strip_all_tags( (string) ( $t['description'] ?? '' ) ), 40, '…' ),
			'currency' => (string) ( $t['currency'] ?? '' ),
			'free'     => ! empty( $t['allow_free'] ),
			'min'      => (float) ( $t['min_amount'] ?? 0 ),
			'qty'      => ! empty( $t['allow_quantity'] ),
			'maxQty'   => (int) ( $t['max_quantity'] ?? 0 ),
			'proxy'    => ! empty( $t['requires_proxy'] ),
			'options'  => $opts,
		);
	}
	$cache = $out;
	return $out;
}

/** Asistanın sunabileceği bağış türü yoksa nedeni (ayarlarda gösterilir); varsa ''. */
function flxc_ai_types_problem() {
	if ( ! function_exists( 'flxb_get_types' ) ) {
		return __( 'Flux Donate eklentisi etkin değil; asistan bağış türlerini ve fiyatları oradan alır.', 'flx-chat' );
	}
	if ( flxc_ai_types() ) {
		return '';
	}
	$open = (array) flxb_get_types( array( 'status' => 'open', 'progress' => false ) );
	if ( ! $open ) {
		return __( 'Flux Donate\'ta açık bağış türü yok (Bağış türleri sayfasından bir türü açın).', 'flx-chat' );
	}
	if ( array_filter( (array) flxc_setting( 'bot_types' ) ) ) {
		return __( 'Asistan için seçilen bağış türleri kapalı ya da fiyatsız; açık bir tür seçin ya da seçimi boş bırakın.', 'flx-chat' );
	}
	return __( 'Açık bağış türlerinin hiçbirinde fiyat yok (Flux Donate › Bağış türleri › seçenek fiyatı).', 'flx-chat' );
}

function flxc_ai_types_text() {
	$lines = array();
	foreach ( flxc_ai_types() as $t ) {
		$l = '- ' . $t['title'] . ' (id ' . $t['id'] . ')';
		if ( '' !== $t['desc'] ) {
			$l .= ': ' . $t['desc'];
		}
		foreach ( $t['options'] as $o ) {
			$l .= "\n  - " . $o['label'] . ' (seçenek "' . $o['key'] . '"): ' . flxc_money( $o['price'], $t['currency'] ) . ( '' !== $o['unit'] ? ' / ' . $o['unit'] : '' );
		}
		if ( $t['free'] ) {
			$l .= "\n  - " . __( 'Serbest tutar', 'flx-chat' ) . ( $t['min'] > 0 ? ' (en az ' . flxc_money( $t['min'], $t['currency'] ) . ')' : '' );
		}
		if ( $t['proxy'] ) {
			$l .= "\n  - Vekâlet onayı gerekir: bu tür sohbetten kaydedilemez, temsilciye devret.";
		}
		$lines[] = $l;
	}
	return implode( "\n", $lines );
}

/** Bağış sayfası adresi: ayardaki, yoksa sitede /bagis/ sayfası varsa o. */
function flxc_donate_url( $type = null, $option = '', $qty = 0 ) {
	$url = trim( (string) flxc_setting( 'donate_url' ) );
	if ( '' === $url ) {
		$page = get_page_by_path( 'bagis' );
		$url  = $page ? get_permalink( $page ) : '';
	}
	if ( '' === $url ) {
		return '';
	}
	if ( '' !== $option ) {
		$url = add_query_arg( array( 'flxb_opt' => $option, 'flxb_qty' => max( 1, (int) $qty ) ), $url );
	}
	return $url;
}

/* ---------- Cevap önerisi ---------- */

/** Konuşmayı modele düz metin olarak verir; her modelde aynı çalışır. */
function flxc_ai_transcript( $chat, $limit = 30 ) {
	$who   = flxc_chat_title( $chat );
	$lines = array();
	foreach ( flxc_chat_messages( $chat['id'], 0, $limit ) as $m ) {
		if ( 'deleted' === $m['type'] ) {
			continue; // Silinen mesajlar yapay zekâya verilmez.
		}
		if ( 'note' === $m['direction'] ) {
			$lines[] = '(Ekip içi not: ' . flxc_preview_text( $m ) . ')';
			continue;
		}
		if ( 'system' === $m['direction'] ) {
			$lines[] = '(' . flxc_preview_text( $m ) . ')';
			continue;
		}
		$text = flxc_preview_text( $m );
		if ( 'text' !== $m['type'] && '' !== trim( (string) $m['body'] ) && $text !== $m['body'] ) {
			$text .= ': ' . $m['body'];
		}
		$lines[] = ( 'in' === $m['direction'] ? $who : __( 'Biz', 'flx-chat' ) ) . ' [' . wp_date( 'j M H:i', (int) ( $m['ts'] / 1000 ) ) . ']: ' . $text;
	}
	return implode( "\n", $lines );
}

function flxc_ai_suggest_messages( $chat, $hint = '' ) {
	$brand  = flxc_brand_name();
	$system = "Sen {$brand} adına WhatsApp'tan yazışan deneyimli bir temsilcisin. Görevin, temsilcinin karşı tarafa göndereceği SIRADAKİ mesajı yazmak.\n"
		. "- Yalnız mesaj metnini yaz; tırnak, başlık, açıklama ya da seçenek listesi ekleme.\n"
		. "- Kısa, sıcak ve doğal yaz. WhatsApp biçimi kullanabilirsin (*kalın*). Emojiyi az kullan.\n"
		. "- Karşı taraf hangi dilde yazıyorsa o dilde yaz.\n"
		. "- Konuşmada ya da aşağıdaki bilgilerde olmayan bir şeyi (fiyat, tarih, adres, söz) uydurma. Emin olmadığın yere [köşeli parantez] içinde temsilcinin dolduracağı yer bırak.\n"
		. "- Kişinin adı biliniyorsa adıyla hitap et.\n\n"
		. flxc_ai_context_text();
	$replies = flxc_ai_replies_text();
	if ( '' !== $replies ) {
		$system .= "\n\n## Ekibin hazır cevapları (üslup örneği)\n" . $replies;
	}
	$user = "Kişi: " . flxc_chat_title( $chat ) . ' (' . flxc_format_phone( $chat['phone'] ) . ")\n\nKonuşma (eskiden yeniye):\n" . flxc_ai_transcript( $chat ) . "\n\nSıradaki mesajımızı yaz.";
	if ( '' !== $hint ) {
		$user .= "\nTemsilcinin isteği: " . $hint;
	}
	return array(
		array( 'role' => 'system', 'content' => $system ),
		array( 'role' => 'user', 'content' => $user ),
	);
}

function flxc_ai_replies_text() {
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT title, body FROM ' . flxc_table( 'replies' ) . ' ORDER BY uses DESC, sort, id LIMIT 12', ARRAY_A );
	$out  = array();
	foreach ( (array) $rows as $r ) {
		$out[] = '- ' . $r['title'] . ': ' . preg_replace( '/\s+/u', ' ', $r['body'] );
	}
	return implode( "\n", $out );
}

/** flxc_ai_providers için OpenRouter öneri fonksiyonu. */
function flxc_ai_openrouter_suggest( $chat, $messages = array(), $context = array() ) {
	$hint = (string) ( $context['hint'] ?? '' );
	$r    = flxc_ai_chat( flxc_ai_suggest_messages( $chat, $hint ), array( 'purpose' => 'suggest', 'max_tokens' => 500 ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	return flxc_ai_clean_text( (string) ( $r['content'] ?? '' ) );
}

/** Modelin bazen eklediği tırnak/önek kalıntılarını temizler. */
function flxc_ai_clean_text( $t ) {
	$t = trim( preg_replace( '/^<think>.*?<\/think>/s', '', (string) $t ) );
	// Bazı modeller tırnakları &#34; / &quot; gibi HTML varlığı olarak yazıyor; WhatsApp düz metin ister.
	$t = html_entity_decode( $t, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$t = preg_replace( '/^(Biz|Temsilci|Mesaj)\s*(\[[^\]]*\])?\s*:\s*/u', '', $t );
	if ( preg_match( '/^"(.*)"$/su', $t, $m ) || preg_match( '/^“(.*)”$/su', $t, $m ) ) {
		$t = $m[1];
	}
	return trim( $t );
}

/* ---------- Ayarlar sekmesi için REST ---------- */

add_action(
	'rest_api_init',
	function () {
		$mng = function () {
			return flxc_can_manage();
		};
		register_rest_route(
			'flx-chat/v1',
			'/admin/ai/models',
			array(
				'methods'             => 'GET',
				'permission_callback' => $mng,
				'callback'            => function ( WP_REST_Request $req ) {
					$m = flxc_ai_models( (bool) $req->get_param( 'fresh' ) );
					if ( is_wp_error( $m ) ) {
						$m->add_data( array( 'status' => 502 ) );
						return $m;
					}
					$prov = array();
					foreach ( flxc_ai_provider_list() as $id => $label ) {
						$prov[] = array( 'id' => $id, 'label' => $label );
					}
					return array( 'models' => $m, 'providers' => $prov );
				},
			)
		);
		register_rest_route(
			'flx-chat/v1',
			'/admin/ai/test',
			array(
				'methods'             => 'POST',
				'permission_callback' => $mng,
				'callback'            => function () {
					$t = microtime( true );
					$r = flxc_ai_chat(
						array(
							array( 'role' => 'system', 'content' => 'Kısa cevap ver.' ),
							array( 'role' => 'user', 'content' => 'Merhaba! Tek cümleyle kendini tanıt.' ),
						),
						array( 'max_tokens' => 60, 'timeout' => 30 )
					);
					if ( is_wp_error( $r ) ) {
						$r->add_data( array( 'status' => 400 ) );
						return $r;
					}
					return array(
						'text' => flxc_ai_clean_text( (string) ( $r['content'] ?? '' ) ),
						'ms'   => (int) ( ( microtime( true ) - $t ) * 1000 ),
					);
				},
			)
		);
	}
);

/**
 * Hesap düzeyinde hata mı (anahtar, kredi, OpenRouter bütçesi, hız sınırı)? Bu hatalarda sonraki istekler de
 * aynı şekilde reddedilir; benchmark senaryoyu hatalı saymak yerine durur.
 */
function flxc_ai_is_account_error( $e ) {
	if ( ! is_wp_error( $e ) ) {
		return false;
	}
	$d    = (array) $e->get_error_data();
	$code = (int) ( $d['http'] ?? 0 );
	if ( in_array( $code, array( 401, 402, 429 ), true ) ) {
		return true;
	}
	if ( 400 === $code && preg_match( '/credit balance|billing/i', (string) ( $d['msg'] ?? '' ) ) ) {
		return true; // Anthropic: kredi bitti
	}
	return 403 === $code && preg_match( '/budget|credit|limit|quota|exceeded/i', (string) ( $d['msg'] ?? '' ) );
}
