<?php
/**
 * Flux AI & MCP kaydı (SDK): güncelleme sunucusunun verisini okuyan ve ayarlarını değiştiren araçlar.
 * Okuma araçları onaysız; değiştiren her araç Flux AI'da onay kartında bekler. Yeni site anahtarı
 * modele hiç gönderilmez: Updater › Siteler ekranında bir kez gösterilir.
 * Yapay zekâya kapalı: yönetici anahtarları, sunucu paneli parolası, GitHub token'ı, webhook secret'ı.
 *
 * @package FLX_Updater_Manager
 */

defined( 'ABSPATH' ) || exit;

/** Sunucu yanıtı; hata olursa araç hatası. */
function flxum_ai_get( $route, $query = array() ) {
	$r = FLXUM_Client::get( $route, $query );
	if ( is_wp_error( $r ) ) {
		throw new Exception( $r->get_error_message() );
	}
	unset( $r['_status'] );
	return $r;
}

/** Panel işlemi; sonuç {ok, message} (+ ekran linki). */
function flxum_ai_action( $action, $fields = array(), $tab = '' ) {
	$long = in_array( $action, array( 'geo_update', 'flush', 'save_github', 'prune_zips' ), true );
	$r    = FLXUM_Client::action( $action, $fields, $long ? 120 : 30 );
	if ( is_wp_error( $r ) ) {
		throw new Exception( $r->get_error_message() );
	}
	$data = (array) ( $r['data'] ?? array() );
	$out  = array(
		'ok'      => ! empty( $r['ok'] ),
		'message' => '' !== (string) ( $r['message'] ?? '' ) ? wp_strip_all_tags( (string) $r['message'] ) : __( 'Kaydedildi.', 'flx-updater-manager' ),
	);
	if ( ! empty( $data['new_key'] ) ) {
		// Anahtar modele gitmez: ekranda bir kez gösterilmek üzere bu kullanıcıya saklanır.
		$data['new_key'] = flxum_seal( $data['new_key'] );
		set_transient( 'flxum_flash_' . get_current_user_id(), array( 'ok' => $out['ok'], 'message' => $out['message'], 'data' => $data ), 300 );
		$out['message'] = __( 'Anahtar üretildi. Güvenlik için burada gösterilmez: 5 dakika içinde Updater › Siteler ekranını açın, anahtar orada bir kez görünür.', 'flx-updater-manager' );
		$out['key_url'] = flxum_admin_url( 'sites' );
		$tab            = '';
	}
	if ( '' !== $tab ) {
		$out['url'] = flxum_admin_url( $tab );
	}
	return $out;
}

/** Kayıtlı (anahtarlı) site; bulunamazsa hata. */
function flxum_ai_site( $hash ) {
	$hash = strtolower( trim( (string) $hash ) );
	foreach ( (array) ( flxum_ai_get( 'settings' )['sites'] ?? array() ) as $s ) {
		if ( $hash === (string) $s['hash'] || ( strlen( $hash ) >= 8 && 0 === strpos( (string) $s['hash'], $hash ) ) ) {
			return $s;
		}
	}
	throw new Exception( __( 'Site bulunamadı. Önce anahtarlı siteleri listeleyin.', 'flx-updater-manager' ) );
}

/** Erişim alanları: scope all|selected + packages[] + repos[]. */
function flxum_ai_access_fields( array $a, array $fallback = array() ) {
	$scope = (string) ( $a['scope'] ?? ( $fallback['scope'] ?? 'all' ) );
	$f     = array( 'scope' => 'selected' === $scope ? 'selected' : 'all' );
	if ( 'selected' === $f['scope'] ) {
		$f['packages'] = array_values( array_filter( array_map( 'sanitize_key', (array) ( $a['packages'] ?? ( $fallback['packages'] ?? array() ) ) ) ) );
		$f['repos']    = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $a['repos'] ?? ( $fallback['repos'] ?? array() ) ) ) ) );
	}
	return $f;
}

/** Sunucunun aralıkları: 7, 30, 90 gün. */
function flxum_ai_days( array $a ) {
	$d = (int) ( $a['days'] ?? 30 );
	return $d <= 7 ? 7 : ( $d <= 30 ? 30 : 90 );
}

/** Tarih alanı: zaman damgası → okunur tarih (boşsa ''). */
function flxum_ai_ts( $ts ) {
	$ts = (int) $ts;
	return $ts > 0 ? flxum_date( $ts ) : '';
}

add_action(
	'flx_ai_register',
	function () {
		$slug = 'flx-updater-manager';
		$cap  = 'manage_options';
		flx_ai_plugin(
			$slug,
			array(
				'label'       => 'Flux Updater Manager',
				'prefix'      => 'flxum',
				'plugin'      => plugin_basename( FLXUM_FILE ),
				'page'        => 'admin.php?page=flx-updater-manager',
				'description' => __( 'Güncelleme sunucusunun istatistikleri, siteleri, paketleri ve güvenlik ayarları.', 'flx-updater-manager' ),
			)
		);
		$none   = new stdClass();
		$access = array(
			'scope'    => array(
				'type'        => 'string',
				'enum'        => array( 'all', 'selected' ),
				'description' => 'all = every private package (incl. future ones); selected = only the listed packages/repos. Public packages are always open.',
			),
			'packages' => array(
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'description' => 'Package slugs (scope=selected). FluxWP Panel (flx-setup) is always added.',
			),
			'repos'    => array(
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'description' => 'Whole private repos owner/name incl. packages added later (scope=selected).',
			),
		);

		/* ---------------- Okuma ---------------- */

		flx_ai_tool(
			$slug,
			'overview',
			array(
				'title'       => __( 'Sunucu genel bakış', 'flx-updater-manager' ),
				'description' => 'Update server statistics for the last 7, 30 or 90 days: totals (active sites, update checks, downloads, transferred bytes) with the previous equal period, daily series, installs and downloads per package, countries, server status (emergency stop, keyless distribution). Use connected_sites for the site list.',
				'readonly'    => true,
				'cap'         => $cap,
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'days' => array( 'type' => 'integer', 'enum' => array( 7, 30, 90 ), 'description' => 'Default 30.' ),
					),
				),
				'run'         => function ( array $a ) {
					$days = flxum_ai_days( $a );
					$o    = flxum_ai_get( 'overview', array( 'r' => $days ) );
					$info = flxum_ai_get( 'info' );
					$geo  = array_slice( (array) ( $o['geo'] ?? array() ), 0, 25 );
					return array(
						'server'      => array(
							'url'        => (string) ( $info['base_url'] ?? '' ),
							'version'    => (string) ( $info['version'] ?? '' ),
							'lockdown'   => ! empty( $info['lockdown'] ),
							'public_off' => ! empty( $info['public_off'] ),
						),
						'days'        => $days,
						'totals'      => $o['totals'] ?? array(),
						'previous'    => $o['prev'] ?? array(),
						'series'      => $o['series'] ?? array(),
						'installs'    => $o['installs'] ?? array(),
						'downloads'   => $o['downloads'] ?? array(),
						'countries'   => array_slice( (array) ( $o['countries'] ?? array() ), 0, 25 ),
						'traffic'     => $geo,
						'sites_range' => count( (array) ( $o['clients'] ?? array() ) ),
						'sites_total' => (int) ( $o['all_clients'] ?? 0 ),
						'url'         => flxum_admin_url( 'overview' ),
					);
				},
			)
		);

		flx_ai_tool(
			$slug,
			'connected_sites',
			array(
				'title'       => __( 'Bağlanan siteler', 'flx-updater-manager' ),
				'description' => 'Sites that checked for updates in the last 7, 30 or 90 days: url, name, access (keyed/keyless), country, WordPress/PHP/FluxWP Panel versions (with usage consent), installed packages, check count, first/last seen. client id is used by issue_key.',
				'readonly'    => true,
				'cap'         => $cap,
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'days'   => array( 'type' => 'integer', 'enum' => array( 7, 30, 90 ) ),
						'search' => array( 'type' => 'string', 'description' => 'Part of url, name or package slug.' ),
						'keyed'  => array( 'type' => 'string', 'enum' => array( '', 'keyed', 'keyless' ) ),
						'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ),
					),
				),
				'run'         => function ( array $a ) {
					$o     = flxum_ai_get( 'overview', array( 'r' => flxum_ai_days( $a ) ) );
					$q     = strtolower( trim( (string) ( $a['search'] ?? '' ) ) );
					$keyed = (string) ( $a['keyed'] ?? '' );
					$rows  = array();
					foreach ( (array) ( $o['clients'] ?? array() ) as $c ) {
						$has = '' !== (string) $c['site'];
						if ( ( 'keyed' === $keyed && ! $has ) || ( 'keyless' === $keyed && $has ) ) {
							continue;
						}
						if ( '' !== $q && false === strpos( strtolower( $c['url'] . ' ' . $c['name'] . ' ' . implode( ' ', array_keys( (array) $c['packages'] ) ) ), $q ) ) {
							continue;
						}
						$rows[] = array(
							'client'    => (string) $c['id'],
							'url'       => (string) $c['url'],
							'name'      => (string) $c['name'],
							'access'    => (string) $c['access'],
							'site_hash' => (string) $c['site'],
							'country'   => (string) $c['country'],
							'wp'        => (string) $c['wp'],
							'php'       => (string) $c['php'],
							'panel'     => (string) $c['panel'],
							'packages'  => (array) $c['packages'],
							'checks'    => (int) $c['checks'],
							'first'     => flxum_ai_ts( $c['first'] ),
							'last'      => flxum_ai_ts( $c['last'] ),
						);
					}
					$limit = max( 1, min( 200, (int) ( $a['limit'] ?? 50 ) ) );
					return array(
						'total' => count( $rows ),
						'items' => array_slice( $rows, 0, $limit ),
						'url'   => flxum_admin_url( 'overview' ),
					);
				},
			)
		);

		flx_ai_tool(
			$slug,
			'keyed_sites',
			array(
				'title'       => __( 'Anahtarlı siteler', 'flx-updater-manager' ),
				'description' => 'Site keys on the update server: hash (id for update/toggle/delete), name, private access (packages "*" = all, or list; repos), channel, active, pending delivery, last seen, url, installed packages. Keys themselves are never readable.',
				'readonly'    => true,
				'cap'         => $cap,
				'schema'      => array( 'type' => 'object', 'properties' => $none ),
				'run'         => function () {
					$items = array();
					foreach ( (array) ( flxum_ai_get( 'settings' )['sites'] ?? array() ) as $s ) {
						$s['last_seen'] = flxum_ai_ts( $s['last_seen'] );
						$items[]        = $s;
					}
					return array( 'items' => $items, 'url' => flxum_admin_url( 'sites' ) );
				},
			)
		);

		flx_ai_tool(
			$slug,
			'packages',
			array(
				'title'       => __( 'Sunucudaki paketler', 'flx-updater-manager' ),
				'description' => 'Packages served by the update server: slug, name, type, repo, private/public, latest and stable version, publish date, size. Also GitHub connection state and repo lists.',
				'readonly'    => true,
				'cap'         => $cap,
				'schema'      => array( 'type' => 'object', 'properties' => $none ),
				'run'         => function () {
					$s  = flxum_ai_get( 'settings' );
					$gh = (array) ( $s['github'] ?? array() );
					unset( $gh['webhook_secret'], $gh['token_hint'] );
					$out = array(
						'packages' => $s['packages'] ?? array(),
						'github'   => $gh,
						'url'      => flxum_admin_url( 'packages' ),
					);
					if ( isset( $s['categories'] ) ) {
						$out['categories'] = $s['categories'];
						$out['pkg_meta']   = $s['pkg_meta'] ?? array();
					}
					return $out;
				},
			)
		);

		flx_ai_tool(
			$slug,
			'security',
			array(
				'title'       => __( 'Sunucu güvenliği', 'flx-updater-manager' ),
				'description' => 'Security settings: emergency stop (lockdown), keyless distribution off (public_off), country access rule (geo_mode off|deny|allow, countries, keyed sites exempt, monthly DB update), country DB status.',
				'readonly'    => true,
				'cap'         => $cap,
				'schema'      => array( 'type' => 'object', 'properties' => $none ),
				'run'         => function () {
					$sec = (array) ( flxum_ai_get( 'settings' )['security'] ?? array() );
					unset( $sec['countries'] );
					$sec['geo_db'] = flxum_ai_get( 'resources' )['geo_db'] ?? array();
					$sec['url']    = flxum_admin_url( 'security' );
					return $sec;
				},
			)
		);

		flx_ai_tool(
			$slug,
			'resources',
			array(
				'title'       => __( 'Sunucu kaynakları', 'flx-updater-manager' ),
				'description' => 'Server resources: disk, data size, PHP version and limits, load, stored zips, GitHub API quota, package cache, zip retention settings.',
				'readonly'    => true,
				'cap'         => $cap,
				'schema'      => array( 'type' => 'object', 'properties' => $none ),
				'run'         => function () {
					$r          = flxum_ai_get( 'resources' );
					$r['files'] = flxum_ai_get( 'settings' )['files'] ?? array();
					$r['url']   = flxum_admin_url( 'resources' );
					unset( $r['ok'] );
					return $r;
				},
			)
		);

		flx_ai_tool(
			$slug,
			'test_key',
			array(
				'title'       => __( 'Site anahtarını test et', 'flx-updater-manager' ),
				'description' => 'Checks a site key (flxu_…): which site it belongs to, active or not, and which packages it can see.',
				'readonly'    => true,
				'cap'         => $cap,
				'log'         => false,
				'schema'      => array(
					'type'       => 'object',
					'required'   => array( 'key' ),
					'properties' => array( 'key' => array( 'type' => 'string' ) ),
				),
				'run'         => function ( array $a ) {
					$r = FLXUM_Client::request( 'POST', 'test-key', array( 'key' => trim( (string) ( $a['key'] ?? '' ) ) ) );
					if ( is_wp_error( $r ) ) {
						throw new Exception( $r->get_error_message() );
					}
					unset( $r['_status'] );
					return $r;
				},
			)
		);

		/* ---------------- Değiştirme (onaylı) ---------------- */

		flx_ai_tool(
			$slug,
			'add_site',
			array(
				'title'       => __( 'Site ekle (anahtar üret)', 'flx-updater-manager' ),
				'description' => 'Creates a site key. The key is NOT returned to you; the admin sees it once on Updater › Sites. Default access: all private packages, stable channel.',
				'cap'         => $cap,
				'schema'      => array(
					'type'       => 'object',
					'required'   => array( 'name' ),
					'properties' => array(
						'name'    => array( 'type' => 'string' ),
						'channel' => array( 'type' => 'string', 'enum' => array( 'stable', 'beta' ) ),
					) + $access,
				),
				'run'         => function ( array $a ) {
					return flxum_ai_action(
						'add_site',
						array(
							'name'    => sanitize_text_field( (string) ( $a['name'] ?? '' ) ),
							'channel' => 'beta' === ( $a['channel'] ?? '' ) ? 'beta' : 'stable',
						) + flxum_ai_access_fields( $a ),
						'sites'
					);
				},
			)
		);

		flx_ai_tool(
			$slug,
			'update_site',
			array(
				'title'       => __( 'Site erişimini düzenle', 'flx-updater-manager' ),
				'description' => 'Changes a keyed site\'s name, channel or private access. Omitted fields keep their current value. hash from keyed_sites (first 8+ chars are enough).',
				'cap'         => $cap,
				'schema'      => array(
					'type'       => 'object',
					'required'   => array( 'hash' ),
					'properties' => array(
						'hash'    => array( 'type' => 'string' ),
						'name'    => array( 'type' => 'string' ),
						'channel' => array( 'type' => 'string', 'enum' => array( 'stable', 'beta' ) ),
					) + $access,
				),
				'run'         => function ( array $a ) {
					$s   = flxum_ai_site( $a['hash'] ?? '' );
					$cur = array(
						'scope'    => '*' === $s['packages'] ? 'all' : 'selected',
						'packages' => is_array( $s['packages'] ) ? $s['packages'] : array(),
						'repos'    => (array) $s['repos'],
					);
					return flxum_ai_action(
						'update_site',
						array(
							'hash'    => $s['hash'],
							'name'    => sanitize_text_field( (string) ( $a['name'] ?? $s['name'] ) ),
							'channel' => in_array( $a['channel'] ?? '', array( 'stable', 'beta' ), true ) ? $a['channel'] : $s['channel'],
						) + flxum_ai_access_fields( $a, $cur ),
						'sites'
					);
				},
			)
		);

		flx_ai_tool(
			$slug,
			'toggle_site',
			array(
				'title'       => __( 'Siteyi aktif/pasif yap', 'flx-updater-manager' ),
				'description' => 'Turns a site key on or off. An inactive key sees only public packages.',
				'cap'         => $cap,
				'schema'      => array(
					'type'       => 'object',
					'required'   => array( 'hash', 'on' ),
					'properties' => array(
						'hash' => array( 'type' => 'string' ),
						'on'   => array( 'type' => 'boolean' ),
					),
				),
				'run'         => function ( array $a ) {
					$s = flxum_ai_site( $a['hash'] ?? '' );
					return flxum_ai_action( 'toggle_site', array( 'hash' => $s['hash'], 'on' => ! empty( $a['on'] ) ? '1' : '' ), 'sites' );
				},
			)
		);

		flx_ai_tool(
			$slug,
			'delete_site',
			array(
				'title'       => __( 'Siteyi sil', 'flx-updater-manager' ),
				'description' => 'Deletes a site key; it stops working immediately. Cannot be undone.',
				'cap'         => $cap,
				'schema'      => array(
					'type'       => 'object',
					'required'   => array( 'hash' ),
					'properties' => array( 'hash' => array( 'type' => 'string' ) ),
				),
				'run'         => function ( array $a ) {
					$s = flxum_ai_site( $a['hash'] ?? '' );
					return flxum_ai_action( 'delete_site', array( 'hash' => $s['hash'] ), 'sites' );
				},
			)
		);

		flx_ai_tool(
			$slug,
			'issue_key',
			array(
				'title'       => __( 'Bağlanan siteye anahtar ver', 'flx-updater-manager' ),
				'description' => 'Issues a key to a keyless connected site (client id from connected_sites); the site picks it up on its next update check. Then set its access with update_site.',
				'cap'         => $cap,
				'schema'      => array(
					'type'       => 'object',
					'required'   => array( 'client' ),
					'properties' => array( 'client' => array( 'type' => 'string' ) ),
				),
				'run'         => function ( array $a ) {
					return flxum_ai_action( 'issue_key', array( 'client' => sanitize_key( (string) ( $a['client'] ?? '' ) ) ), 'sites' );
				},
			)
		);

		flx_ai_tool(
			$slug,
			'set_switch',
			array(
				'title'       => __( 'Acil durdurma / anahtarsız dağıtım', 'flx-updater-manager' ),
				'description' => 'lockdown on = every update check and download returns 503 (all sites). public_off on = keyless requests (public packages, download button, apps) get 401; keyed sites unaffected.',
				'cap'         => $cap,
				'schema'      => array(
					'type'       => 'object',
					'required'   => array( 'switch', 'on' ),
					'properties' => array(
						'switch' => array( 'type' => 'string', 'enum' => array( 'lockdown', 'public_off' ) ),
						'on'     => array( 'type' => 'boolean' ),
					),
				),
				'run'         => function ( array $a ) {
					$w = 'public_off' === ( $a['switch'] ?? '' ) ? 'public_off' : 'lockdown';
					return flxum_ai_action( 'save_switch', array( 'switch' => $w, 'on' => ! empty( $a['on'] ) ? '1' : '' ), 'security' );
				},
			)
		);

		flx_ai_tool(
			$slug,
			'set_country_access',
			array(
				'title'       => __( 'Ülke erişimi', 'flx-updater-manager' ),
				'description' => 'Country rule for update checks and downloads. mode off|deny (block listed)|allow (only listed). countries = ISO codes (TR, DE). Omitted fields keep current values.',
				'cap'         => $cap,
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'mode'         => array( 'type' => 'string', 'enum' => array( 'off', 'deny', 'allow' ) ),
						'countries'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'keyed_exempt' => array( 'type' => 'boolean', 'description' => 'Active keyed sites are exempt.' ),
						'auto_update'  => array( 'type' => 'boolean', 'description' => 'Update the country DB monthly.' ),
					),
				),
				'run'         => function ( array $a ) {
					$cur = (array) ( flxum_ai_get( 'settings' )['security'] ?? array() );
					$cc  = isset( $a['countries'] ) ? (array) $a['countries'] : (array) ( $cur['geo_countries'] ?? array() );
					$ex  = isset( $a['keyed_exempt'] ) ? ! empty( $a['keyed_exempt'] ) : ! empty( $cur['geo_keyed_exempt'] );
					$au  = isset( $a['auto_update'] ) ? ! empty( $a['auto_update'] ) : ! empty( $cur['geo_auto'] );
					return flxum_ai_action(
						'save_geo',
						array(
							'geo_mode'         => in_array( $a['mode'] ?? '', array( 'off', 'deny', 'allow' ), true ) ? $a['mode'] : (string) ( $cur['geo_mode'] ?? 'off' ),
							'geo_countries'    => strtoupper( implode( ',', array_map( 'sanitize_key', $cc ) ) ),
							'geo_keyed_exempt' => $ex ? '1' : '',
							'geo_auto'         => $au ? '1' : '',
						),
						'security'
					);
				},
			)
		);

		flx_ai_tool(
			$slug,
			'set_public_packages',
			array(
				'title'       => __( 'Herkese açık paketler', 'flx-updater-manager' ),
				'description' => 'Sets which packages from private repos are downloadable without a key (e.g. flx-setup for the website download button). Replaces the whole list; empty list = none. Other GitHub settings stay.',
				'cap'         => $cap,
				'schema'      => array(
					'type'       => 'object',
					'required'   => array( 'packages' ),
					'properties' => array( 'packages' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) ),
				),
				'run'         => function ( array $a ) {
					$gh = (array) ( flxum_ai_get( 'settings' )['github'] ?? array() );
					return flxum_ai_action(
						'save_github',
						array(
							'repo'                 => implode( ', ', (array) ( $gh['private_repos'] ?? array() ) ),
							'public_repos'         => implode( ', ', (array) ( $gh['public_repos'] ?? array() ) ),
							'app_repo'             => (string) ( $gh['app_repo'] ?? '' ),
							'fluxapp_repo'         => (string) ( $gh['fluxapp_repo'] ?? '' ),
							'public_packages_sent' => '1',
							'public_packages'      => array_values( array_filter( array_map( 'sanitize_key', (array) ( $a['packages'] ?? array() ) ) ) ),
						),
						'github'
					);
				},
			)
		);

		flx_ai_tool(
			$slug,
			'refresh_packages',
			array(
				'title'       => __( 'Paketleri yeniden oku', 'flx-updater-manager' ),
				'description' => 'Clears the server\'s GitHub cache so new releases appear now.',
				'cap'         => $cap,
				'schema'      => array( 'type' => 'object', 'properties' => $none ),
				'run'         => function () {
					return flxum_ai_action( 'flush', array(), 'packages' );
				},
			)
		);

		flx_ai_tool(
			$slug,
			'set_zip_retention',
			array(
				'title'       => __( 'Zip saklama', 'flx-updater-manager' ),
				'description' => 'Zip storage: keep = latest versions kept per package (1-50), temp_hours = hours an older re-downloaded version is kept (1-720). prune=true also deletes old zips now.',
				'cap'         => $cap,
				'schema'      => array(
					'type'       => 'object',
					'properties' => array(
						'keep'       => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50 ),
						'temp_hours' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 720 ),
						'prune'      => array( 'type' => 'boolean' ),
					),
				),
				'run'         => function ( array $a ) {
					$out = array();
					if ( isset( $a['keep'] ) || isset( $a['temp_hours'] ) ) {
						$f   = (array) ( flxum_ai_get( 'settings' )['files'] ?? array() );
						$out = flxum_ai_action(
							'save_zips',
							array(
								'zip_keep'       => (int) ( $a['keep'] ?? ( $f['zip_keep'] ?? 5 ) ),
								'zip_temp_hours' => (int) ( $a['temp_hours'] ?? ( $f['zip_temp_hours'] ?? 24 ) ),
							),
							'resources'
						);
					}
					if ( ! empty( $a['prune'] ) ) {
						$out = flxum_ai_action( 'prune_zips', array(), 'resources' );
					}
					return $out ? $out : array( 'ok' => true, 'message' => __( 'Değişiklik yok.', 'flx-updater-manager' ) );
				},
			)
		);

		flx_ai_tool(
			$slug,
			'update_country_db',
			array(
				'title'       => __( 'Ülke veritabanını güncelle', 'flx-updater-manager' ),
				'description' => 'Downloads the latest country database (DB-IP Lite) on the update server.',
				'cap'         => $cap,
				'schema'      => array( 'type' => 'object', 'properties' => $none ),
				'run'         => function () {
					return flxum_ai_action( 'geo_update', array(), 'security' );
				},
			)
		);
	}
);
