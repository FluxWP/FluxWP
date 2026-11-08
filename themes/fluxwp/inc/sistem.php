<?php
/**
 * Görünüm › Sistem Kontrolü: eklenti kurgusu, cron ve temel ayarların yeşil/sarı/kırmızı listesi.
 *
 * Ortak sözleşme: her eklenti kendi kontrollerini `flx_system_checks` filtresine ekler.
 * Kontrol: { id, source, group, label, status (ok|warning|error|info), message, action_url?, action_label? }.
 * Kontroller ucuz olmalı (dış HTTP yok); ekran yalnızca açıldığında çalışır.
 *
 * @package fluxwp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Cron'un son çalışma zamanı ---------- */

/**
 * WordPress cron'un en son ne zaman çalıştığını tutmaz; her cron isteğinde (en fazla dakikada bir) kaydedilir.
 */
function flx_sk_cron_mark() {
	if ( ! wp_doing_cron() ) {
		return;
	}
	$last = (int) get_option( 'flx_cron_last', 0 );
	if ( time() - $last >= 60 ) {
		update_option( 'flx_cron_last', time(), false );
	}
}
add_action( 'init', 'flx_sk_cron_mark', 1 );

/* ---------- Yardımcılar ---------- */

/** Sistem kontrolü ekranının adresi (Flux Speed kuruluysa Performans › Sistem Kontrolü). */
function flx_sk_url() {
	return function_exists( 'flxp_system_checks_url' ) ? flxp_system_checks_url() : admin_url( 'themes.php?page=flx-sistem' );
}

/** Kurulu eklentiler: klasör adı → { file, name, version, active }. */
function flx_sk_plugins() {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$out = array();
	foreach ( get_plugins() as $file => $data ) {
		$dir         = false !== strpos( $file, '/' ) ? dirname( $file ) : $file;
		$out[ $dir ] = array(
			'file'    => $file,
			'name'    => $data['Name'],
			'version' => $data['Version'],
			'active'  => is_plugin_active( $file ),
		);
	}
	return $out;
}

/**
 * Sitenin türleri: 'donate' (bağış) ve/veya 'store' (mağaza). Hiçbiri değilse boş dizi (ör. kurumsal site).
 * Kurulum sihirbazındaki "Neyle başlayalım?" seçimi ilgili paketi kurar; tür buradan anlaşılır:
 * Flux Donate kuruluysa ya da Tema Ayarları › Site türü "Bağış" ise bağış, Flux Commerce kuruluysa mağaza.
 */
function flx_sk_site_kinds() {
	$have  = flx_sk_plugins();
	$kinds = array();
	if ( ! empty( $have['flx-donate'] ) || ( function_exists( 'flx_donation_mode' ) && flx_donation_mode() ) ) {
		$kinds[] = 'donate';
	}
	if ( ! empty( $have['flx-commerce'] ) ) {
		$kinds[] = 'store';
	}
	/** Site türünü değiştir: array( 'donate', 'store' ) içinden. */
	return array_values( array_intersect( array( 'donate', 'store' ), (array) apply_filters( 'flx_sk_site_kinds', $kinds ) ) );
}

/** Bağış sitesi mi? Kontrol metinleri buna göre bağış/sertifika dilini kullanır. */
function flx_sk_is_donate() {
	return in_array( 'donate', flx_sk_site_kinds(), true );
}

/**
 * Bizim eklenti kurgumuz, site türüne göre. min = temanın beklediği en düşük sürüm; checks = kendi kontrollerini
 * Sistem Kontrolü'ne eklemeye başladığı sürüm. Bağış paketleri yalnız bağış sitesinde, Flux Commerce yalnız mağazada.
 */
function flx_sk_required_plugins() {
	$kinds  = flx_sk_site_kinds();
	$donate = in_array( 'donate', $kinds, true );
	$store  = in_array( 'store', $kinds, true );
	$list   = array(
		'woocommerce' => array( 'name' => 'WooCommerce', 'min' => '8.0', 'required' => $donate || $store, 'why' => __( 'My Account, cart and checkout pages', 'fluxwp' ) ),
	);
	if ( $store ) {
		$list['flx-commerce'] = array( 'name' => 'Flux Commerce', 'min' => '0.1.0', 'required' => true, 'why' => __( 'Store dashboard, customers, sales report and bank transfer with receipt', 'fluxwp' ) );
	}
	if ( $donate ) {
		$list['flx-donate']       = array( 'name' => 'Flux Donate', 'min' => '2.0.0', 'required' => true, 'checks' => '2.0.0', 'why' => __( 'Donation types, sapling packages, card donations and donors', 'fluxwp' ) );
		$list['flx-certificates'] = array( 'name' => 'Flux Certificates', 'min' => '1.2.4', 'required' => true, 'checks' => '1.2.4', 'why' => __( 'Sapling certificates and events', 'fluxwp' ) );
	}
	$list['flx-forms'] = array( 'name' => 'Flux Forms', 'min' => '2.0.8', 'required' => true, 'checks' => '2.0.8', 'why' => $donate ? __( 'Donation and contact forms', 'fluxwp' ) : __( 'Contact forms', 'fluxwp' ) );
	$list['flx-seo']   = array( 'name' => 'Flux SEO', 'min' => '1.0.9', 'required' => false, 'checks' => '1.0.8', 'why' => __( 'SEO, sitemap, schema and browser cache', 'fluxwp' ) );
	$list['flx-chat']  = array( 'name' => 'Flux Chat', 'min' => '1.3.0', 'required' => false, 'checks' => '1.3.0', 'why' => $donate ? __( 'Phone donations and call logs', 'fluxwp' ) : __( 'WhatsApp chats and call logs', 'fluxwp' ) );
	$list['flx-tasks'] = array( 'name' => 'Flux Tasks', 'min' => '0.1.4', 'required' => false, 'checks' => '0.1.4', 'why' => __( 'Team task tracking', 'fluxwp' ) );
	/** Başka bir site kurgusu listeyi değiştirebilir. */
	return apply_filters( 'flx_sk_required_plugins', $list );
}

/** Tek bir kontrol satırı. */
function flx_sk_item( $id, $group, $label, $status, $message, $action_url = '', $action_label = '' ) {
	$item = array(
		'id'      => $id,
		'source'  => 'theme',
		'group'   => $group,
		'label'   => $label,
		'status'  => $status,
		'message' => $message,
	);
	if ( $action_url ) {
		$item['action_url']   = $action_url;
		$item['action_label'] = $action_label ? $action_label : __( 'Open', 'fluxwp' );
	}
	return $item;
}

/* ---------- Temanın kontrolleri ---------- */

/** Eklentiler: kurulu mu, etkin mi, sürüm yeterli mi. */
function flx_sk_checks_plugins() {
	$g       = __( 'Plugins', 'fluxwp' );
	$have    = flx_sk_plugins();
	$plugins = admin_url( 'plugins.php' );
	$out     = array();
	foreach ( flx_sk_required_plugins() as $dir => $p ) {
		$bad = $p['required'] ? 'error' : 'warning';
		$tag = $p['required'] ? __( 'Required', 'fluxwp' ) : __( 'Optional', 'fluxwp' );
		$why = $tag . ': ' . $p['why'] . '.';
		if ( empty( $have[ $dir ] ) ) {
			$out[] = flx_sk_item( 'theme.plugin.' . $dir, $g, $p['name'], $bad, __( 'Not installed.', 'fluxwp' ) . ' ' . $why, admin_url( 'plugin-install.php?tab=upload' ), __( 'Upload plugin', 'fluxwp' ) );
			continue;
		}
		$v = $have[ $dir ]['version'];
		if ( ! $have[ $dir ]['active'] ) {
			/* translators: %s: plugin version */
			$out[] = flx_sk_item( 'theme.plugin.' . $dir, $g, $p['name'], $bad, sprintf( __( 'Installed (v%s) but not active.', 'fluxwp' ), $v ) . ' ' . $why, $plugins, __( 'Plugins', 'fluxwp' ) );
			continue;
		}
		if ( version_compare( $v, $p['min'], '<' ) ) {
			/* translators: 1: installed version, 2: minimum version */
			$out[] = flx_sk_item( 'theme.plugin.' . $dir, $g, $p['name'], 'warning', sprintf( __( 'Active, v%1$s. This theme was tested with v%2$s or later; please update.', 'fluxwp' ), $v, $p['min'] ), admin_url( 'plugin-install.php?tab=upload' ), __( 'Update', 'fluxwp' ) );
			continue;
		}
		/* translators: %s: plugin version */
		$msg = sprintf( __( 'Active, v%s.', 'fluxwp' ), $v );
		if ( ! empty( $p['checks'] ) && version_compare( $v, $p['checks'], '<' ) ) {
			/* translators: %s: plugin version */
			$msg .= ' ' . sprintf( __( 'v%s and later add their own checks to this screen.', 'fluxwp' ), $p['checks'] );
		}
		$out[] = flx_sk_item( 'theme.plugin.' . $dir, $g, $p['name'], 'ok', $msg );
	}

	// iyzico WooCommerce eklentisi isteğe bağlı: Flux Donate anahtarları ondan ya da kendi alanlarından okur.
	// Flux Donate 1.2.1+ bunu kendi satırında gösterir; iki kez yazılmasın.
	// Bağış sitesi değilse iyzico satırı da gereksiz.
	if ( class_exists( 'FLXB_System_Checks' ) || ! flx_sk_is_donate() ) {
		return $out;
	}
	$iyzico = null;
	foreach ( $have as $dir => $p ) {
		if ( false !== stripos( $p['name'], 'iyzico' ) ) {
			$iyzico = $p;
			if ( $p['active'] ) {
				break;
			}
		}
	}
	if ( ! $iyzico ) {
		$out[] = flx_sk_item( 'theme.plugin.iyzico', $g, 'iyzico', 'info', __( 'No iyzico plugin installed. That is fine: Flux Donate reads the iyzico keys from its own Payments settings.', 'fluxwp' ) );
	} elseif ( ! $iyzico['active'] ) {
		/* translators: %s: plugin name */
		$out[] = flx_sk_item( 'theme.plugin.iyzico', $g, 'iyzico', 'info', sprintf( __( '%s is installed but not active. Flux Donate reads the keys from its own Payments settings.', 'fluxwp' ), $iyzico['name'] ), $plugins, __( 'Plugins', 'fluxwp' ) );
	} else {
		/* translators: 1: plugin name, 2: version */
		$out[] = flx_sk_item( 'theme.plugin.iyzico', $g, 'iyzico', 'ok', sprintf( __( '%1$s is active, v%2$s. Flux Donate reads the keys from it.', 'fluxwp' ), $iyzico['name'], $iyzico['version'] ) );
	}
	return $out;
}

/** Zamanlanmış görevler (cron). */
function flx_sk_checks_cron() {
	$g        = __( 'Scheduled tasks (cron)', 'fluxwp' );
	$out      = array();
	$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	$last     = (int) get_option( 'flx_cron_last', 0 );
	$ago      = $last ? time() - $last : 0;
	$cmd      = '/usr/bin/php ' . ABSPATH . 'wp-cron.php >/dev/null 2>&1';

	if ( $disabled ) {
		$out[] = flx_sk_item( 'theme.cron.mode', $g, __( 'Server cron', 'fluxwp' ), 'ok', __( 'DISABLE_WP_CRON is on: tasks run from the server cron and do not slow down visitor requests.', 'fluxwp' ) );
	} else {
		/* translators: %s: cron command */
		$out[] = flx_sk_item( 'theme.cron.mode', $g, __( 'Server cron', 'fluxwp' ), 'warning', sprintf( flx_sk_is_donate() ? __( 'Tasks only run when visitors arrive (WP-Cron). With little traffic the certificate queue and recurring donation renewals are delayed. Add this command under Hostinger › Advanced › Cron Jobs, every 5 minutes: %s Then add define( \'DISABLE_WP_CRON\', true ); to wp-config.php.', 'fluxwp' ) : __( 'Tasks run only when visitors arrive (WP-Cron). With low traffic, emails and scheduled jobs are delayed. Add this command every 5 minutes under Hostinger › Advanced › Cron Jobs: %s Then add define( \'DISABLE_WP_CRON\', true ); to wp-config.php.', 'fluxwp' ), $cmd ) );
	}

	if ( ! $last ) {
		$out[] = flx_sk_item( 'theme.cron.last', $g, __( 'Last run', 'fluxwp' ), $disabled ? 'warning' : 'info', __( 'No record yet. It appears after the first cron run following the theme update; reload this page in a few minutes.', 'fluxwp' ) );
	} else {
		/* translators: 1: time ago, 2: date */
		$msg    = sprintf( __( '%1$s (%2$s).', 'fluxwp' ), flx_time_ago( $last ), wp_date( 'd.m.Y H:i', $last ) );
		$status = 'ok';
		if ( $ago > 3 * HOUR_IN_SECONDS ) {
			$status = 'error';
			$msg   .= ' ' . __( 'Cron has not run for hours. Check the cron job and command path in Hostinger.', 'fluxwp' );
		} elseif ( $ago > 20 * MINUTE_IN_SECONDS ) {
			$status = $disabled ? 'warning' : 'info';
			$msg   .= ' ' . ( $disabled ? __( 'Has not run for longer than expected (should run every 5 minutes).', 'fluxwp' ) : __( 'Has not run for a while because traffic is low.', 'fluxwp' ) );
		}
		$out[] = flx_sk_item( 'theme.cron.last', $g, __( 'Last run', 'fluxwp' ), $status, $msg );
	}

	// Vakti 15 dakikadan fazla geçmiş görevler.
	$late  = array();
	$crons = function_exists( '_get_cron_array' ) ? (array) _get_cron_array() : array();
	foreach ( $crons as $ts => $hooks ) {
		if ( $ts > time() - 15 * MINUTE_IN_SECONDS ) {
			break; // Dizi zamana göre sıralı.
		}
		foreach ( (array) $hooks as $hook => $events ) {
			$late[ $hook ] = ( $late[ $hook ] ?? 0 ) + count( (array) $events );
		}
	}
	if ( ! $late ) {
		$out[] = flx_sk_item( 'theme.cron.late', $g, __( 'Overdue tasks', 'fluxwp' ), 'ok', __( 'No overdue tasks.', 'fluxwp' ) );
	} else {
		$names = array_slice( array_keys( $late ), 0, 5 );
		/* translators: 1: count, 2: hook names */
		$out[] = flx_sk_item( 'theme.cron.late', $g, __( 'Overdue tasks', 'fluxwp' ), ( ! $last || $ago > HOUR_IN_SECONDS ) ? 'error' : 'warning', sprintf( __( '%1$d tasks are more than 15 minutes overdue: %2$s', 'fluxwp' ), array_sum( $late ), implode( ', ', $names ) ) );
	}
	return $out;
}

/** Site ve tema ayarları. */
function flx_sk_checks_site() {
	$g   = __( 'Site settings', 'fluxwp' );
	$out = array();

	$out[] = get_option( 'blog_public' )
		? flx_sk_item( 'theme.site.index', $g, __( 'Search engines', 'fluxwp' ), 'ok', __( 'The site is visible to Google.', 'fluxwp' ) )
		: flx_sk_item( 'theme.site.index', $g, __( 'Search engines', 'fluxwp' ), 'error', __( '"Discourage search engines from indexing this site" is checked. Uncheck it on the live site.', 'fluxwp' ), admin_url( 'options-reading.php' ), __( 'Reading settings', 'fluxwp' ) );

	$out[] = 0 === strpos( home_url(), 'https://' )
		? flx_sk_item( 'theme.site.https', $g, 'HTTPS', 'ok', __( 'Site address uses https.', 'fluxwp' ) )
		: flx_sk_item( 'theme.site.https', $g, 'HTTPS', 'warning', __( 'Site address uses http. Enable SSL and switch the address to https for payments and login.', 'fluxwp' ), admin_url( 'options-general.php' ), __( 'General settings', 'fluxwp' ) );

	if ( ! get_option( 'permalink_structure' ) ) {
		$out[] = flx_sk_item( 'theme.site.permalink', $g, __( 'Permalinks', 'fluxwp' ), 'error', __( 'Plain (?p=123) addresses are used. Choose "Post name".', 'fluxwp' ), admin_url( 'options-permalink.php' ), __( 'Permalinks', 'fluxwp' ) );
	} else {
		$rules = get_option( 'rewrite_rules' );
		$out[] = ! post_type_exists( 'proje' ) || ! function_exists( 'flx_kurulum_migrate_url' )
			? flx_sk_item( 'theme.site.permalink', $g, __( 'Permalinks', 'fluxwp' ), 'ok', __( 'Post name structure.', 'fluxwp' ) )
			: ( is_array( $rules ) && isset( $rules['projeler/?$'] )
			? flx_sk_item( 'theme.site.permalink', $g, __( 'Permalinks', 'fluxwp' ), 'ok', __( 'Post name structure; /projeler/ and /etkinlikler/ addresses are registered.', 'fluxwp' ) )
			: flx_sk_item( 'theme.site.permalink', $g, __( 'Permalinks', 'fluxwp' ), 'warning', __( 'The /projeler/ rewrite rule is missing and may return 404.', 'fluxwp' ), flx_kurulum_migrate_url( 'permalink' ), __( 'Refresh rules', 'fluxwp' ) ) );
	}

	$tz    = wp_timezone();
	$off   = $tz->getOffset( new DateTime( 'now', $tz ) );
	$out[] = 3 * HOUR_IN_SECONDS === $off
		? flx_sk_item( 'theme.site.tz', $g, __( 'Timezone', 'fluxwp' ), 'ok', wp_timezone_string() )
		: flx_sk_item( 'theme.site.tz', $g, __( 'Timezone', 'fluxwp' ), 'warning', sprintf( /* translators: %s: timezone */ flx_sk_is_donate() ? __( '%s. Choose Istanbul so certificate and donation dates are correct.', 'fluxwp' ) : __( '%s. Choose Istanbul so order, form and email times are correct.', 'fluxwp' ), wp_timezone_string() ), admin_url( 'options-general.php' ), __( 'General settings', 'fluxwp' ) );

	// E-posta: sertifika ve bağış e-postaları için SMTP önerilir.
	// Flux Mail kendi SMTP durumunu 'flxi.mailer' satırında raporlar; çift uyarı olmasın.
	if ( ! function_exists( 'flxi_settings' ) ) {
		$smtp = '';
		foreach ( flx_sk_plugins() as $p ) {
			if ( $p['active'] && preg_match( '/smtp|mailgun|sendgrid|brevo|sendinblue|postmark|mailersend/i', $p['name'] ) ) {
				$smtp = $p['name'];
				break;
			}
		}
		$out[] = $smtp
			? flx_sk_item( 'theme.site.smtp', $g, __( 'Email delivery', 'fluxwp' ), 'ok', sprintf( /* translators: %s: plugin name */ __( '%s is active.', 'fluxwp' ), $smtp ) )
			: flx_sk_item( 'theme.site.smtp', $g, __( 'Email delivery', 'fluxwp' ), 'warning', ( flx_sk_is_donate() ? __( 'No SMTP plugin. Certificate and donation emails may land in spam; connect the association mailbox with WP Mail SMTP or FluentSMTP.', 'fluxwp' ) : __( 'No SMTP plugin. Site emails may land in spam; connect your organization\'s email with WP Mail SMTP or FluentSMTP.', 'fluxwp' ) ), admin_url( 'plugin-install.php?s=smtp&tab=search&type=term' ), __( 'Search plugins', 'fluxwp' ) );
	}

	// Sayfa önbelleği.
	$have = flx_sk_plugins();
	$lsc  = ! empty( $have['litespeed-cache']['active'] );
	if ( $lsc ) {
		$lazy  = get_option( 'litespeed.conf.media-lazy' );
		$out[] = $lazy
			? flx_sk_item( 'theme.site.cache', $g, __( 'Page cache', 'fluxwp' ), 'warning', __( 'LiteSpeed Cache is active, but image lazy load is on. The theme already lazy-loads images; with both on, images appear blank on every reload. LiteSpeed Cache › Page Optimization › Media › Lazy Load: Off.', 'fluxwp' ), admin_url( 'admin.php?page=litespeed-page_optm#settings_media' ), 'LiteSpeed' )
			: flx_sk_item( 'theme.site.cache', $g, __( 'Page cache', 'fluxwp' ), 'ok', __( 'LiteSpeed Cache is active.', 'fluxwp' ) );
	} elseif ( defined( 'WP_CACHE' ) && WP_CACHE ) {
		$out[] = flx_sk_item( 'theme.site.cache', $g, __( 'Page cache', 'fluxwp' ), 'ok', __( 'A page cache is active (WP_CACHE).', 'fluxwp' ) );
	} else {
		$out[] = flx_sk_item( 'theme.site.cache', $g, __( 'Page cache', 'fluxwp' ), 'warning', __( 'No page cache. Install and enable LiteSpeed Cache on Hostinger; the site gets noticeably faster.', 'fluxwp' ), admin_url( 'plugin-install.php?s=litespeed+cache&tab=search&type=term' ), __( 'Search plugins', 'fluxwp' ) );
	}

	$out[] = version_compare( PHP_VERSION, '8.1', '>=' )
		? flx_sk_item( 'theme.site.php', $g, 'PHP', 'ok', PHP_VERSION )
		: flx_sk_item( 'theme.site.php', $g, 'PHP', 'warning', sprintf( /* translators: %s: PHP version */ __( '%s. Hostinger › PHP Yapılandırması\'ndan 8.2 veya üstünü seçin.', 'fluxwp' ), PHP_VERSION ) );
	return $out;
}

/** Sayfalar, WooCommerce ayarları ve tema ayarları. */
function flx_sk_checks_content() {
	$g   = __( 'Pages and theme', 'fluxwp' );
	$out = array();
	$kur = admin_url( 'themes.php?page=flx-setup' );

	$kurulum = function_exists( 'flx_kurulum_migrations' );
	if ( $kurulum ) {
	$out[] = get_option( 'flx_kurulum_done' )
		? flx_sk_item( 'theme.content.setup', $g, __( 'Site setup', 'fluxwp' ), 'ok', __( 'Basic setup has been run.', 'fluxwp' ) )
		: flx_sk_item( 'theme.content.setup', $g, __( 'Site setup', 'fluxwp' ), 'error', __( 'Basic setup was never run; pages and menu may be missing.', 'fluxwp' ), $kur, __( 'Site Setup', 'fluxwp' ) );

	}

	$pages = $kurulum ? array(
		'bagis'            => __( 'Donation page', 'fluxwp' ),
		'kvkk'             => __( 'KVKK Privacy Notice', 'fluxwp' ),
		'cerez-politikasi' => __( 'Cookie Policy', 'fluxwp' ),
	) : array();
	$missing = array();
	foreach ( $pages as $slug => $title ) {
		$p = get_page_by_path( $slug );
		if ( ! $p || 'publish' !== $p->post_status ) {
			$missing[] = $title;
		}
	}
	if ( function_exists( 'wc_get_page_id' ) ) {
		foreach ( array( 'myaccount' => __( 'My account', 'fluxwp' ), 'cart' => __( 'Cart', 'fluxwp' ), 'checkout' => __( 'Checkout', 'fluxwp' ) ) as $key => $title ) {
			$id = wc_get_page_id( $key );
			if ( $id <= 0 || 'publish' !== get_post_status( $id ) ) {
				$missing[] = $title;
			}
		}
	}
	$out[] = $missing
		? flx_sk_item( 'theme.content.pages', $g, __( 'Required pages', 'fluxwp' ), 'error', sprintf( /* translators: %s: page list */ __( 'Missing or not published: %s.', 'fluxwp' ), implode( ', ', $missing ) ), $kurulum ? $kur : admin_url( 'admin.php?page=wc-settings&tab=advanced' ), $kurulum ? __( 'Site Setup', 'fluxwp' ) : 'WooCommerce' )
		: flx_sk_item( 'theme.content.pages', $g, __( 'Required pages', 'fluxwp' ), 'ok', $kurulum ? __( 'Donation, KVKK, Cookie Policy, My Account, Cart and Checkout are published.', 'fluxwp' ) : __( 'My account, Cart and Checkout are published.', 'fluxwp' ) );

	if ( function_exists( 'get_woocommerce_currency' ) ) {
		$cur   = get_woocommerce_currency();
		$out[] = 'TRY' === $cur
			? flx_sk_item( 'theme.content.currency', $g, __( 'Currency', 'fluxwp' ), 'ok', 'TRY (₺)' )
			: flx_sk_item( 'theme.content.currency', $g, __( 'Currency', 'fluxwp' ), 'warning', sprintf( /* translators: %s: currency code */ __( 'WooCommerce currency is %s. Choose Turkish lira.', 'fluxwp' ), $cur ), admin_url( 'admin.php?page=wc-settings' ), 'WooCommerce' );

		$out[] = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' )
			? flx_sk_item( 'theme.content.register', $g, __( 'Account registration', 'fluxwp' ), 'ok', __( 'Registration is enabled on My Account.', 'fluxwp' ) )
			: flx_sk_item( 'theme.content.register', $g, __( 'Account registration', 'fluxwp' ), 'warning', __( 'Registration is disabled on My Account; only the Login tab is shown.', 'fluxwp' ), $kurulum ? flx_kurulum_migrate_url( 'hesap-kayit' ) : admin_url( 'admin.php?page=wc-settings&tab=account' ), $kurulum ? __( 'Enable registration', 'fluxwp' ) : 'WooCommerce' );
	}

	$out[] = flx_ayar( 'ga4_id' )
		? flx_sk_item( 'theme.content.ga4', $g, 'Google Analytics', 'ok', (string) flx_ayar( 'ga4_id' ) )
		: flx_sk_item( 'theme.content.ga4', $g, 'Google Analytics', 'info', __( 'No GA4 measurement ID entered.', 'fluxwp' ), admin_url( 'themes.php?page=flx-ayarlar' ), __( 'Theme Settings', 'fluxwp' ) );

	if ( flx_ayar( 'cookie_enabled' ) ) {
		$out[] = flx_ayar_policy_url()
			? flx_sk_item( 'theme.content.cookie', $g, __( 'Cookie banner', 'fluxwp' ), 'ok', __( 'On, linked to the Cookie Policy page.', 'fluxwp' ) )
			: flx_sk_item( 'theme.content.cookie', $g, __( 'Cookie banner', 'fluxwp' ), 'warning', __( 'On, but no Cookie Policy page is linked.', 'fluxwp' ), admin_url( 'themes.php?page=flx-ayarlar' ), __( 'Theme Settings', 'fluxwp' ) );
	} else {
		$out[] = flx_sk_item( 'theme.content.cookie', $g, __( 'Cookie banner', 'fluxwp' ), flx_ayar( 'ga4_id' ) ? 'warning' : 'info', __( 'Off. If analytics cookies are used, KVKK requires it to be on.', 'fluxwp' ), admin_url( 'themes.php?page=flx-ayarlar' ), __( 'Theme Settings', 'fluxwp' ) );
	}

	if ( ! $kurulum ) {
		return $out;
	}
	// Hazır güncellemeler: hiç uygulanmamış olanlar.
	$done    = (array) get_option( 'flx_migrations', array() );
	$pending = array();
	foreach ( flx_kurulum_migrations() as $id => $m ) {
		if ( empty( $done[ $id ] ) && ! in_array( $id, array( 'permalink', 'onbellek' ), true ) ) {
			$pending[] = $m['title'];
		}
	}
	$out[] = $pending
		? flx_sk_item( 'theme.content.migrations', $g, __( 'Ready-made updates', 'fluxwp' ), 'info', sprintf( /* translators: 1: count, 2: list */ __( '%1$d updates have never been applied. A fresh install may not need them; apply one if the related page looks outdated. List: %2$s', 'fluxwp' ), count( $pending ), implode( ', ', array_slice( $pending, 0, 6 ) ) . ( count( $pending ) > 6 ? ' …' : '' ) ), $kur . '#guncellemeler', __( 'Ready-made updates', 'fluxwp' ) )
		: flx_sk_item( 'theme.content.migrations', $g, __( 'Ready-made updates', 'fluxwp' ), 'ok', __( 'All have been applied at least once.', 'fluxwp' ) );
	return $out;
}

/** Temanın kontrolleri ortak filtreye en başta eklenir. */
function flx_sk_theme_checks( $checks ) {
	return array_merge( flx_sk_checks_plugins(), flx_sk_checks_cron(), flx_sk_checks_site(), flx_sk_checks_content(), (array) $checks );
}
add_filter( 'flx_system_checks', 'flx_sk_theme_checks', 1 );

if ( ! function_exists( 'flx_system_checks_run' ) ) {
	/** Ortak yardımcı; Flux Donate veya Flux Certificates tanımladıysa onlarınki kullanılır. */
	function flx_system_checks_run() {
		return (array) apply_filters( 'flx_system_checks', array() );
	}
}

/**
 * Tüm kontrolleri toplar ve doğrular. Bir eklentinin hatası ekranı bozmasın diye geçersiz satırlar atlanır.
 */
function flx_sk_collect() {
	$valid = array( 'ok', 'warning', 'error', 'info' );
	$out   = array();
	$seen  = array();
	foreach ( (array) flx_system_checks_run() as $c ) {
		if ( ! is_array( $c ) || empty( $c['label'] ) ) {
			continue;
		}
		$id = isset( $c['id'] ) ? (string) $c['id'] : md5( wp_json_encode( $c ) );
		if ( isset( $seen[ $id ] ) ) {
			continue;
		}
		$seen[ $id ] = 1;
		$out[]       = array(
			'id'           => $id,
			'source'       => isset( $c['source'] ) ? (string) $c['source'] : '',
			'group'        => flx_sk_group_label( isset( $c['group'] ) ? (string) $c['group'] : '' ),
			'label'        => (string) $c['label'],
			'status'       => isset( $c['status'] ) && in_array( $c['status'], $valid, true ) ? $c['status'] : 'info',
			'message'      => isset( $c['message'] ) ? (string) $c['message'] : '',
			'action_url'   => isset( $c['action_url'] ) ? (string) $c['action_url'] : '',
			'action_label' => isset( $c['action_label'] ) ? (string) $c['action_label'] : '',
		);
	}
	return $out;
}

/**
 * Ortak sözleşmenin bilinen grup anahtarları temanın grup başlıklarına eşlenir; eklenti satırları
 * temanın aynı konudaki grubuna düşer. Bilinmeyen gruplar olduğu gibi gösterilir.
 */
function flx_sk_group_label( $group ) {
	$map = array(
		'Cron'     => __( 'Scheduled tasks (cron)', 'fluxwp' ),
		'Payments' => __( 'Payments', 'fluxwp' ),
		'Plugins'  => __( 'Plugins', 'fluxwp' ),
		'Config'   => __( 'Site settings', 'fluxwp' ),
		'Email'    => __( 'Email', 'fluxwp' ),
		'Security' => __( 'Security', 'fluxwp' ),
		'Forms'    => __( 'Forms', 'fluxwp' ),
		'Other'    => __( 'Other', 'fluxwp' ),
		''         => __( 'Other', 'fluxwp' ),
	);
	return $map[ $group ] ?? $group;
}

/** Kaynak kodu → okunur ad. */
function flx_sk_source_label( $source ) {
	$map = array(
		'theme'        => __( 'Theme', 'fluxwp' ),
		'flx-donate'     => 'Flux Donate',
		'flxb'          => 'Flux Donate',
		'flx-forms'      => 'Flux Forms',
		'flxf'          => 'Flux Forms',
		'flx-certificates' => 'Flux Certificates',
		'flxs'          => 'Flux Certificates',
		'flx-seo'       => 'SEO & MCP',
		'flxseo'        => 'SEO & MCP',
		'flx-chat'     => 'Flux Chat',
		'flx-tasks'     => 'Flux Tasks',
		'flx-chat' => 'Flux Chat',
	);
	return $map[ $source ] ?? $source;
}

/* ---------- Ekran ---------- */

/* Eklentilerin ortak uyarısı (Başlangıç + üst çubuk) temanın ekranına bağlansın; tema sürümü değişince gizlenen uyarı yeniden çıksın. */
add_filter( 'flx_system_checks_page_url', 'flx_sk_url', 20 );
add_filter(
	'flx_system_checks_versions',
	function ( $v ) {
		$v = (array) $v;
		$v['fluxwp'] = FLX_VERSION;
		return $v;
	}
);

function flx_sk_menu() {
	add_theme_page( __( 'System Check', 'fluxwp' ), __( 'System Check', 'fluxwp' ), 'manage_options', 'flx-sistem', 'flx_sk_page' );
	// Flux Speed ekranı devraldı: Görünüm altındaki kopya menüden kalkar, eski adres oraya yönlenir.
	if ( function_exists( 'flxp_system_checks_url' ) ) {
		remove_submenu_page( 'themes.php', 'flx-sistem' );
	}
}
add_action( 'admin_menu', 'flx_sk_menu' );
add_action(
	'load-appearance_page_flx-sistem',
	function () {
		if ( function_exists( 'flxp_system_checks_url' ) ) {
			wp_safe_redirect( flxp_system_checks_url() );
			exit;
		}
	}
);

function flx_sk_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$checks = flx_sk_collect();
	$count  = array_fill_keys( array( 'error', 'warning', 'ok', 'info' ), 0 );
	$groups = array();
	foreach ( $checks as $c ) {
		++$count[ $c['status'] ];
		$groups[ $c['group'] ][] = $c;
	}
	// Grup içinde önce sorunlar.
	$order = array( 'error' => 0, 'warning' => 1, 'info' => 2, 'ok' => 3 );
	foreach ( $groups as &$list ) {
		usort(
			$list,
			function ( $a, $b ) use ( $order ) {
				return $order[ $a['status'] ] <=> $order[ $b['status'] ];
			}
		);
	}
	unset( $list );
	$names = array(
		'error'   => __( 'Error', 'fluxwp' ),
		'warning' => __( 'Warning', 'fluxwp' ),
		'ok'      => __( 'OK', 'fluxwp' ),
		'info'    => __( 'Info', 'fluxwp' ),
	);
	?>
	<div class="wrap flx-sk">
		<style>
			.flx-sk{max-width:1000px}
			.flx-sk-sum{display:flex;flex-wrap:wrap;gap:.6em;margin:1em 0 1.4em;align-items:center}
			.flx-sk-pill{display:inline-flex;align-items:center;gap:.45em;padding:.35em .8em;border-radius:999px;background:#fff;border:1px solid #dcdcde;font-weight:600;font-size:13px;color:#1d2327;cursor:pointer;font-family:inherit}
			.flx-sk-pill:hover:not(:disabled){border-color:#8c8f94}
			.flx-sk-pill:disabled{opacity:.5;cursor:default}
			.flx-sk-pill[aria-pressed="true"]{border-color:#1d2327;box-shadow:inset 0 0 0 1px #1d2327}
			.flx-sk-hidden{display:none!important}
			.flx-sk-time{color:#646970}
			.flx-sk-dot{width:.7em;height:.7em;border-radius:50%;flex:none;display:inline-block}
			.flx-sk-ok .flx-sk-dot,.flx-sk-dot.ok{background:#0E7A56}
			.flx-sk-warning .flx-sk-dot,.flx-sk-dot.warning{background:#D99A00}
			.flx-sk-error .flx-sk-dot,.flx-sk-dot.error{background:#D63638}
			.flx-sk-info .flx-sk-dot,.flx-sk-dot.info{background:#8C8F94}
			.flx-sk-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;margin:0 0 1.2em;overflow:hidden}
			.flx-sk-card h2{margin:0;padding:.85em 1.2em;font-size:15px;border-bottom:1px solid #f0f0f1;display:flex;justify-content:space-between;align-items:center;gap:1em}
			.flx-sk-card h2 small{font-weight:400;color:#646970}
			.flx-sk-row{display:grid;grid-template-columns:1.1em minmax(150px,220px) 1fr auto;gap:.5em 1em;padding:.75em 1.2em;border-top:1px solid #f0f0f1;align-items:start}
			.flx-sk-row:first-of-type{border-top:0}
			.flx-sk-row .flx-sk-dot{margin-top:.35em}
			.flx-sk-row strong{display:block}
			.flx-sk-src{display:block;color:#8C8F94;font-size:11px;margin-top:.15em}
			.flx-sk-msg{color:#3c434a;word-break:break-word}
			.flx-sk-error{background:#FCF0F1}
			.flx-sk-warning{background:#FEF8EE}
			@media (max-width:782px){.flx-sk-row{grid-template-columns:1.1em 1fr}.flx-sk-row .flx-sk-msg,.flx-sk-row .flx-sk-act{grid-column:2}}
		</style>
		<h1><?php esc_html_e( 'System Check', 'fluxwp' ); ?></h1>
		<p><?php esc_html_e( 'Plugins, scheduled tasks and key settings. Red rows mean part of the site is not working; yellow rows are recommended fixes. The page re-checks every time it opens.', 'fluxwp' ); ?></p>
		<div class="flx-sk-sum">
			<?php foreach ( $count as $st => $n ) : ?>
				<button type="button" class="flx-sk-pill" data-filter="<?php echo esc_attr( $st ); ?>" aria-pressed="false" <?php disabled( 0, $n ); ?> title="<?php esc_attr_e( 'Show only these (click again for all)', 'fluxwp' ); ?>"><span class="flx-sk-dot <?php echo esc_attr( $st ); ?>"></span><?php echo esc_html( $n . ' ' . $names[ $st ] ); ?></button>
			<?php endforeach; ?>
			<a class="button" href="<?php echo esc_url( flx_sk_url() ); ?>"><?php esc_html_e( 'Check again', 'fluxwp' ); ?></a>
			<span class="flx-sk-time">
				<?php
				// Türkçe sitede 24 saat (11:16); WordPress'in saat biçimi kurulum dilinden "g:i a" kalmış olabilir.
				$tf = 0 === strpos( determine_locale(), 'tr' ) ? 'H:i' : get_option( 'time_format', 'H:i' );
				/* translators: %s: time */
				echo esc_html( sprintf( __( 'Checked: %s', 'fluxwp' ), wp_date( $tf ) ) );
				?>
			</span>
		</div>
		<?php foreach ( $groups as $group => $list ) : ?>
			<?php
			$bad = 0;
			foreach ( $list as $c ) {
				$bad += in_array( $c['status'], array( 'error', 'warning' ), true ) ? 1 : 0;
			}
			?>
			<div class="flx-sk-card" data-group>
				<h2><?php echo esc_html( $group ); ?> <small><?php echo $bad ? esc_html( sprintf( /* translators: %d: count */ __( '%d issues', 'fluxwp' ), $bad ) ) : esc_html__( 'All good', 'fluxwp' ); ?></small></h2>
				<?php foreach ( $list as $c ) : ?>
					<div class="flx-sk-row flx-sk-<?php echo esc_attr( $c['status'] ); ?>">
						<span class="flx-sk-dot" title="<?php echo esc_attr( $names[ $c['status'] ] ); ?>"></span>
						<div><strong><?php echo esc_html( $c['label'] ); ?></strong><?php if ( $c['source'] && 'theme' !== $c['source'] ) : ?><span class="flx-sk-src"><?php echo esc_html( flx_sk_source_label( $c['source'] ) ); ?></span><?php endif; ?></div>
						<div class="flx-sk-msg"><?php echo esc_html( $c['message'] ); ?></div>
						<div class="flx-sk-act"><?php if ( $c['action_url'] ) : ?><a class="button button-small" href="<?php echo esc_url( $c['action_url'] ); ?>"><?php echo esc_html( $c['action_label'] ? $c['action_label'] : __( 'Open', 'fluxwp' ) ); ?></a><?php endif; ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
		<?php flx_sk_fix_log_html(); ?>
		<script>
		( function () {
			var pills = document.querySelectorAll( '.flx-sk-pill[data-filter]' );
			function apply( st ) {
				pills.forEach( function ( b ) { b.setAttribute( 'aria-pressed', b.dataset.filter === st ? 'true' : 'false' ); } );
				document.querySelectorAll( '.flx-sk-card[data-group]' ).forEach( function ( card ) {
					var shown = 0;
					card.querySelectorAll( '.flx-sk-row' ).forEach( function ( row ) {
						var on = ! st || row.classList.contains( 'flx-sk-' + st );
						row.classList.toggle( 'flx-sk-hidden', ! on );
						shown += on ? 1 : 0;
					} );
					card.classList.toggle( 'flx-sk-hidden', ! shown );
				} );
				try { history.replaceState( null, '', st ? '#' + st : location.pathname + location.search ); } catch ( e ) {}
			}
			pills.forEach( function ( b ) {
				b.addEventListener( 'click', function () { apply( 'true' === b.getAttribute( 'aria-pressed' ) ? '' : b.dataset.filter ); } );
			} );
			var h = location.hash.slice( 1 );
			if ( /^(ok|warning|error|info)$/.test( h ) ) { apply( h ); }
		} )();
		</script>
	</div>
	<?php
}

/** Site Kurulumu ve Tema Ayarları sayfalarının üstünde kısa özet; yalnızca hata varsa sayı gösterir. */
function flx_sk_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! in_array( $screen->id, array( 'appearance_page_flx-setup', 'appearance_page_flx-ayarlar' ), true ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$err = 0;
	foreach ( flx_sk_collect() as $c ) {
		$err += 'error' === $c['status'] ? 1 : 0;
	}
	$link = '<a href="' . esc_url( flx_sk_url() ) . '">' . esc_html__( 'System Check', 'fluxwp' ) . '</a>';
	if ( $err ) {
		/* translators: 1: count, 2: link */
		echo '<div class="notice notice-error"><p>' . wp_kses_post( sprintf( __( '%1$d critical issues: %2$s', 'fluxwp' ), $err, $link ) ) . '</p></div>';
	} else {
		/* translators: %s: link */
		echo '<div class="notice notice-info"><p>' . wp_kses_post( sprintf( __( 'See the status of plugins, cron and settings on the %s screen.', 'fluxwp' ), $link ) ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'flx_sk_notice' );

/* ---------- Yapay zekâ (MCP) ile uygulanabilen düzeltmeler ---------- */

/**
 * SEO & MCP eklentisinin system_fix aracı yalnızca bu listedekileri çalıştırır; her çalıştırma
 * önceki/sonraki değeriyle kaydedilir. Kimlikler kontrol satırlarına `checks` ile bağlanır.
 */
function flx_sk_fixes( $fixes ) {
	$src = __( 'Theme', 'fluxwp' );

	$fixes['site.timezone'] = array(
		'label'   => 'Saat dilimini İstanbul yap',
		'checks'  => array( 'theme.site.tz' ),
		'source'  => $src,
		'current' => function () {
			return wp_timezone_string();
		},
		'run'     => function () {
			update_option( 'timezone_string', 'Europe/Istanbul' );
			update_option( 'gmt_offset', '' );
			return 'Saat dilimi Europe/Istanbul.';
		},
	);

	$fixes['site.search_visible'] = array(
		'label'   => 'Siteyi arama motorlarına aç',
		'checks'  => array( 'theme.site.index' ),
		'source'  => $src,
		'current' => function () {
			return (bool) get_option( 'blog_public' );
		},
		'run'     => function () {
			update_option( 'blog_public', '1' );
			return 'Arama motorları siteyi dizine ekleyebilir.';
		},
	);

	$fixes['site.permalinks'] = array(
		'label'   => 'Kalıcı bağlantıları "Yazı adı" yapıp kuralları yenile',
		'checks'  => array( 'theme.site.permalink' ),
		'source'  => $src,
		'current' => function () {
			return (string) get_option( 'permalink_structure' );
		},
		'run'     => function () {
			if ( ! get_option( 'permalink_structure' ) ) {
				update_option( 'permalink_structure', '/%postname%/' );
			}
			flush_rewrite_rules();
			return 'Kalıcı bağlantı kuralları yenilendi.';
		},
	);

	$fixes['theme.purge_cache'] = array(
		'label'  => 'Sayfa önbelleğini temizle',
		'source' => $src,
		'run'    => function () {
			flx_purge_page_cache();
			return 'Sayfa önbelleği temizlendi.';
		},
	);

	$migs = function_exists( 'flx_kurulum_migrations' ) ? flx_kurulum_migrations() : array();
	if ( $migs ) {
	$fixes['theme.migration'] = array(
		'label'       => 'Hazır güncellemeyi uygula (Site Kurulumu)',
		'description' => 'value: güncelleme kimliği. ' . implode( '; ', array_map( function ( $id, $m ) { return $id . ' = ' . $m['title']; }, array_keys( $migs ), $migs ) ),
		'checks'      => array( 'theme.content.migrations', 'theme.content.register' ),
		'source'      => $src,
		'value'       => array( 'type' => 'string', 'enum' => array_keys( $migs ) ),
		'current'     => function ( $id ) {
			$done = (array) get_option( 'flx_migrations', array() );
			if ( $id ) {
				return empty( $done[ $id ] ) ? 'uygulanmadı' : 'uygulandı ' . wp_date( 'Y-m-d H:i', $done[ $id ] );
			}
			return array_keys( array_filter( $done ) );
		},
		'run'         => function ( $id ) {
			foreach ( array( 'file', 'media', 'image', 'post' ) as $inc ) {
				require_once ABSPATH . 'wp-admin/includes/' . $inc . '.php';
			}
			$msg = flx_kurulum_apply_migration( (string) $id );
			if ( '' === $msg ) {
				throw new Exception( 'Güncelleme çalışmadı: ' . $id );
			}
			return $msg;
		},
	);

	}
	if ( function_exists( 'WC' ) ) {
		$fixes['wc.currency'] = array(
			'label'   => 'WooCommerce para birimini TRY yap',
			'checks'  => array( 'theme.content.currency' ),
			'source'  => $src,
			'current' => function () {
				return get_option( 'woocommerce_currency' );
			},
			'run'     => function () {
				update_option( 'woocommerce_currency', 'TRY' );
				return 'Para birimi TRY.';
			},
		);
	}

	// Tema Ayarları: yalnızca bu alanlar (kod ekleme alanı hariç).
	$keys = array(
		'ga4_id'         => array( 'type' => 'string', 'description' => 'G-XXXXXXX' ),
		'ga_mode'        => array( 'type' => 'string', 'enum' => array( 'consent', 'after' ) ),
		'cookie_enabled' => array( 'type' => 'boolean' ),
		'animasyon'      => array( 'type' => 'boolean' ),
		'dark_mode'      => array( 'type' => 'string', 'enum' => array( 'auto', 'off' ) ),
		'sayfalama'      => array( 'type' => 'string', 'enum' => array( 'sonsuz', 'numara' ) ),
		'bank_holder'    => array( 'type' => 'string' ),
		'bank_iban'      => array( 'type' => 'string', 'description' => 'TR + 24 rakam' ),
	);
	$fixes['theme.setting'] = array(
		'label'       => 'Tema Ayarları alanı',
		'description' => 'value: { "key": "' . implode( '|', array_keys( $keys ) ) . '", "value": … }',
		'checks'      => array( 'theme.content.ga4', 'theme.content.cookie', 'flxb.cfg.bank' ),
		'source'      => $src,
		'value'       => array(
			'type'       => 'object',
			'required'   => array( 'key', 'value' ),
			'properties' => array(
				'key'   => array( 'type' => 'string', 'enum' => array_keys( $keys ) ),
				'value' => array( 'description' => 'Alan türüne göre metin veya true/false.' ),
			),
		),
		'current'     => function ( $v ) use ( $keys ) {
			if ( is_array( $v ) && isset( $keys[ $v['key'] ?? '' ] ) ) {
				return flx_ayar( $v['key'] );
			}
			$o = array();
			foreach ( array_keys( $keys ) as $k ) {
				$o[ $k ] = flx_ayar( $k );
			}
			return $o;
		},
		'run'         => function ( $v ) use ( $keys ) {
			if ( ! is_array( $v ) || ! isset( $keys[ $v['key'] ?? '' ] ) ) {
				throw new Exception( 'key şunlardan biri olmalı: ' . implode( ', ', array_keys( $keys ) ) );
			}
			$k   = $v['key'];
			$val = $v['value'] ?? '';
			$in  = wp_parse_args( (array) get_option( 'flx_ayarlar', array() ), flx_ayar_defaults() );
			unset( $in['consent_scripts'], $in['cookie_reset'] );
			if ( 'boolean' === $keys[ $k ]['type'] ) {
				$val = filter_var( $val, FILTER_VALIDATE_BOOLEAN ) ? 1 : 0;
			} elseif ( 'animasyon' === $k ) {
				$val = $val ? 1 : 0;
			}
			// Sanitize onay kutularını "boş = kapalı" sayar; mevcut değerleri koru.
			$in['animasyon']      = 'off' === $in['animasyon'] ? 0 : 1;
			$in[ $k ]             = $val;
			$out = flx_ayar_sanitize( $in );
			if ( in_array( $k, array( 'ga4_id', 'bank_iban' ), true ) && '' !== (string) $val && '' === (string) $out[ $k ] ) {
				throw new Exception( 'ga4_id' === $k ? 'GA4 kimliği G-XXXXXXX biçiminde olmalı.' : 'IBAN TR ile başlayan 26 karakter olmalı.' );
			}
			update_option( 'flx_ayarlar', $out );
			return 'Tema Ayarları › ' . $k . ' güncellendi.';
		},
	);
	return $fixes;
}
add_filter( 'flx_system_fixes', 'flx_sk_fixes' );

/** Sistem Kontrolü ekranının altında son düzeltmeler (SEO & MCP etkinse). */
function flx_sk_fix_log_html() {
	if ( ! function_exists( 'flxseo_system_fix_log' ) ) {
		return;
	}
	$log = flxseo_system_fix_log( 20 );
	echo '<div class="flx-sk-card"><h2>' . esc_html__( 'Recent fixes (AI / MCP)', 'fluxwp' ) . '</h2>';
	if ( ! $log ) {
		echo '<p style="padding:0 1.2em 1em;color:#646970">' . esc_html__( 'No fixes have been made through MCP yet.', 'fluxwp' ) . '</p></div>';
		return;
	}
	echo '<table class="widefat striped" style="border:0"><thead><tr><th>' . esc_html__( 'Time', 'fluxwp' ) . '</th><th>' . esc_html__( 'User', 'fluxwp' ) . '</th><th>' . esc_html__( 'Fix', 'fluxwp' ) . '</th><th>' . esc_html__( 'Before → After', 'fluxwp' ) . '</th><th>' . esc_html__( 'Result', 'fluxwp' ) . '</th></tr></thead><tbody>';
	foreach ( $log as $r ) {
		$u = get_userdata( (int) $r['u'] );
		echo '<tr><td>' . esc_html( wp_date( 'd.m H:i', (int) $r['t'] ) ) . '</td><td>' . esc_html( $u ? $u->user_login : '?' ) . '</td><td>' . esc_html( $r['label'] ) . ( '' !== $r['value'] ? ' <code>' . esc_html( $r['value'] ) . '</code>' : '' ) . '</td><td>' . esc_html( $r['before'] . ' → ' . $r['after'] ) . '</td><td>' . ( $r['ok'] ? '✓ ' : '✗ ' ) . esc_html( $r['msg'] ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}
