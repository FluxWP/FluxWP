<?php
/**
 * Yapay zekâ araçları: Flux AI & MCP (flxseo_mcp_tools) üzerinden kaydolur; Flux AI sohbeti bunları
 * kendiliğinden alır. Hepsi salt okunur; flxp_profile_url yalnız kendi sitesine GET atar.
 *
 * @package flx-speed
 */

defined( 'ABSPATH' ) || exit;

/** Kaydın MCP'ye gidecek kısaltılmış hali. */
function flxp_mcp_request_view( $r, $sections ) {
	$d   = $r['data'];
	$out = array(
		'id'      => $r['id'],
		'created' => $r['created'],
		'flags'   => $r['flags'],
		'overview'=> $d['overview'] ?? array(),
	);
	$all = array( 'milestones', 'queries', 'dupes', 'components', 'php', 'wrong', 'http', 'logs', 'template', 'request', 'caps', 'lang', 'transients', 'hooks' );
	$sec = $sections ? array_intersect( $all, (array) $sections ) : array( 'milestones', 'queries', 'dupes', 'components', 'php', 'wrong', 'http', 'logs' );
	$lab = function ( $list ) {
		return array_map(
			function ( $it ) {
				if ( isset( $it['comp'] ) && is_array( $it['comp'] ) ) {
					$it['comp'] = flxp_component_label( $it['comp'] );
				}
				return $it;
			},
			(array) $list
		);
	};
	foreach ( $sec as $s ) {
		switch ( $s ) {
			case 'queries':
				$q = $d['db']['queries'] ?? array();
				usort(
					$q,
					function ( $a, $b ) {
						return $b['ms'] <=> $a['ms'];
					}
				);
				$out['queries'] = array(
					'total'    => $d['db']['total'] ?? 0,
					'ms'       => $d['db']['ms'] ?? 0,
					'slow'     => $d['db']['slow'] ?? 0,
					'errors'   => $d['db']['errors'] ?? 0,
					'detailed' => ! empty( $d['db']['detailed'] ),
					'by_type'  => $d['db']['by_type'] ?? array(),
					'slowest'  => $lab( array_slice( $q, 0, 25 ) ),
				);
				break;
			case 'dupes':
				$out['dupes'] = array_slice( $d['db']['dupes'] ?? array(), 0, 15 );
				break;
			case 'components':
				$out['components'] = $lab( $d['db']['by_component'] ?? array() );
				break;
			case 'php':
			case 'wrong':
			case 'http':
			case 'logs':
			case 'caps':
			case 'transients':
				$out[ $s ] = $lab( array_slice( $d[ $s ] ?? array(), 0, 50 ) );
				break;
			default:
				$out[ $s ] = $d[ $s ] ?? null;
		}
	}
	return $out;
}

/** Araç tanımları (flxseo_mcp_tools biçiminde). */
function flxp_tools() {
	$tools = array();
	$cap   = 'flxp_view';
	$hours = array( 'type' => 'integer', 'description' => 'Kaç saat geriye bakılsın (varsayılan 24, en fazla 720).' );
	$h     = function ( $a ) {
		return max( 1, min( 720, (int) ( $a['hours'] ?? 24 ) ) );
	};

	$tools['flxp_summary'] = array(
		'title'       => __( 'Performance summary', 'flx-speed' ),
		'description' => 'Flux Speed: son X saatte kaydedilen isteklerin özeti: sayı, ortalama/p50/p95/en uzun süre, işaret sayıları (slow, error, fatal, 5xx, http_fail, slow_query), en yavaş adresler (admin-ajax action ile ayrılır) ve sorgu süresine göre en ağır eklenti/tema. by_type: tür başına (front = ziyaretçi sayfası; cron/ajax/rest = çoğunlukla arka plan) süreler. cron_hooks: en ağır zamanlanmış görevler. Yalnız yavaş/hatalı/örneklenen istekler kaydedilir ve önbellekten gelen sayfalar PHP\'ye hiç uğramaz: "kayıtların çoğu yavaş" sitenin çoğu yavaş demek değildir (note alanını oku). "Site neden yavaş / hata var mı" sorularına buradan başla.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array( 'type' => 'object', 'properties' => array( 'hours' => $hours ) ),
		'run'         => function ( $a ) use ( $h ) {
			$s                  = FLXP_Store::summary( $h( $a ) );
			$s['record_enabled'] = (bool) flxp_settings( 'record' );
			$s['total_rows']    = FLXP_Store::count();
			return $s;
		},
	);

	$tools['flxp_requests'] = array(
		'title'       => __( 'List recorded requests', 'flx-speed' ),
		'description' => 'Flux Speed kayıtları (ayrıntısız satırlar). Süz: flag (slow, error, fatal, 5xx, http_fail, slow_query, db_error, log_error, sample, profile), type (front, admin, ajax, rest, cron), url (adreste geçen metin), min_ms. order: new | slow. Ayrıntı için flxp_request.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'hours'  => $hours,
				'flag'   => array( 'type' => 'string', 'enum' => array_keys( FLXP_Store::flag_labels() ) ),
				'type'   => array( 'type' => 'string', 'enum' => array_keys( FLXP_Store::type_labels() ) ),
				'url'    => array( 'type' => 'string' ),
				'min_ms' => array( 'type' => 'integer' ),
				'order'  => array( 'type' => 'string', 'enum' => array( 'new', 'slow' ) ),
				'limit'  => array( 'type' => 'integer', 'description' => 'En fazla 100 (varsayılan 20).' ),
				'page'   => array( 'type' => 'integer' ),
			),
		),
		'run'         => function ( $a ) use ( $h ) {
			$res = FLXP_Store::query(
				array(
					'hours'    => $h( $a ),
					'flag'     => $a['flag'] ?? '',
					'type'     => $a['type'] ?? '',
					'q'        => $a['url'] ?? '',
					'min_ms'   => $a['min_ms'] ?? 0,
					'order'    => $a['order'] ?? 'new',
					'per_page' => max( 1, min( 100, (int) ( $a['limit'] ?? 20 ) ) ),
					'page'     => max( 1, (int) ( $a['page'] ?? 1 ) ),
				)
			);
			return array( 'total' => $res['total'], 'requests' => $res['items'] );
		},
	);

	$tools['flxp_request'] = array(
		'title'       => __( 'Record details', 'flx-speed' ),
		'description' => 'Tek kaydın ayrıntısı. sections boşsa: milestones, queries (en yavaş 25), dupes, components, php, wrong, http, logs. Diğerleri: template, request, caps, lang, transients, hooks.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'id'       => array( 'type' => 'integer' ),
				'sections' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			),
			'required'   => array( 'id' ),
		),
		'run'         => function ( $a ) {
			$r = FLXP_Store::get( (int) ( $a['id'] ?? 0 ) );
			if ( ! $r ) {
				throw new Exception( 'Kayıt bulunamadı.' );
			}
			return flxp_mcp_request_view( $r, $a['sections'] ?? array() );
		},
	);

	$tools['flxp_slow_queries'] = array(
		'title'       => __( 'Slow and failed queries', 'flx-speed' ),
		'description' => 'Son X saatin ayrıntılı kayıtlarından eşik üstü ve hatalı SQL sorguları; değerler soyutlanıp gruplanır (adet, ortalama, en uzun, bileşen, çağıran fonksiyon, örnek kayıt id).',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'hours' => $hours,
				'limit' => array( 'type' => 'integer' ),
			),
		),
		'run'         => function ( $a ) use ( $h ) {
			return array(
				'threshold_ms' => (int) flxp_settings( 'slow_query_ms' ),
				'queries'      => FLXP_Store::slow_queries( $h( $a ), max( 1, min( 50, (int) ( $a['limit'] ?? 20 ) ) ) ),
			);
		},
	);

	$tools['flxp_php_errors'] = array(
		'title'       => __( 'PHP errors', 'flx-speed' ),
		'description' => 'Son X saatte kaydedilen PHP hataları gruplanmış: seviye, mesaj, dosya:satır, sorumlu eklenti/tema, kaç istekte görüldü, çağrı yığını. min_level: fatal | warning (varsayılan) | notice | deprecated.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'hours'     => $hours,
				'min_level' => array( 'type' => 'string', 'enum' => array( 'fatal', 'warning', 'notice', 'deprecated' ) ),
				'limit'     => array( 'type' => 'integer' ),
			),
		),
		'run'         => function ( $a ) use ( $h ) {
			return array( 'errors' => FLXP_Store::php_errors( $h( $a ), max( 1, min( 100, (int) ( $a['limit'] ?? 30 ) ) ), $a['min_level'] ?? 'warning' ) );
		},
	);

	$tools['flxp_http_calls'] = array(
		'title'       => __( 'Outgoing HTTP requests', 'flx-speed' ),
		'description' => 'Sitenin dışarıya yaptığı istekler (Evolution, OpenRouter, Gotenberg, iyzico, güncelleme sunucusu...) uç başına gruplanmış: adet, başarısız, ortalama/en uzun süre, son hata. failed_only ile yalnız başarısızlar.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'hours'       => $hours,
				'failed_only' => array( 'type' => 'boolean' ),
				'limit'       => array( 'type' => 'integer' ),
			),
		),
		'run'         => function ( $a ) use ( $h ) {
			return array( 'endpoints' => FLXP_Store::http_calls( $h( $a ), ! empty( $a['failed_only'] ), max( 1, min( 100, (int) ( $a['limit'] ?? 30 ) ) ) ) );
		},
	);

	$tools['flxp_environment'] = array(
		'title'       => __( 'Server and WordPress environment', 'flx-speed' ),
		'description' => 'PHP sürümü/limitleri/uzantıları, veritabanı sürümü ve boyutu, autoload boyutu, WordPress sabitleri (WP_DEBUG, DISABLE_WP_CRON...), gecikmiş cron sayısı, etkin eklentiler ve sürümleri, sunucu yazılımı.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
		'run'         => function () {
			return flxp_environment();
		},
	);

	$tools['flxp_server'] = array(
		'title'       => __( 'Server status', 'flx-speed' ),
		'description' => 'Anlık sunucu ölçüleri: işlemci yükü (1/5/15 dk, çekirdek sayısı, yüzde), RAM kullanımı, disk doluluğu, çalışma süresi; PHP limitleri, OPcache (isabet oranı, bellek), önbellekler (nesne önbelleği, LiteSpeed, APCu, Redis), sunucu yazılımı/IP/port. live.scope: account = değerler hesabın cgroup sınırına göre; shared = paylaşımlı hosting, hesap sınırı okunamadı (CPU/RAM null; live.machine tüm hesapların ortak makinesidir, sitenin kullanımı DEĞİL, öyle raporlama); machine = sunucu siteye ait. live.disk_usage: hesap klasörü dosyaları + veritabanı (bayt), kota Ayarlar\'dan.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
		'run'         => function () {
			return flxp_server_stats();
		},
	);

	$tools['flxp_database'] = array(
		'title'       => __( 'Database status', 'flx-speed' ),
		'description' => 'MySQL/MariaDB değişkenleri (max_connections, buffer pool, max_allowed_packet...), durum (uptime, bağlantılar, yavaş sorgu sayısı, saniyede sorgu, buffer pool isabeti, diske düşen geçici tablo), toplam veri/indeks/boş alan, temizlik adayları (süresi dolmuş transient, revizyon, otomatik taslak, spam). with_tables=true ile tablo tablo boyutlar (büyükten küçüğe, en fazla 50).',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array( 'with_tables' => array( 'type' => 'boolean' ) ),
		),
		'run'         => function ( $a ) {
			$t  = ! empty( $a['with_tables'] );
			$db = flxp_database( $t );
			if ( $t ) {
				$db['tables'] = array_slice( $db['tables'], 0, 50 );
			} else {
				unset( $db['tables'] );
			}
			return $db;
		},
	);

	$tools['flxp_autoload'] = array(
		'title'       => __( 'Autoloaded options', 'flx-speed' ),
		'description' => 'Her istekte yüklenen (autoload) seçeneklerin toplam boyutu ve en büyükleri, tahmini sahibi (eklenti) ile. 800 KB üstü yavaşlatır.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array( 'limit' => array( 'type' => 'integer', 'description' => 'En fazla 100 (varsayılan 30).' ) ),
		),
		'run'         => function ( $a ) {
			return flxp_autoload( max( 1, min( 100, (int) ( $a['limit'] ?? 30 ) ) ) );
		},
	);

	$tools['flxp_cron'] = array(
		'title'       => __( 'Scheduled events', 'flx-speed' ),
		'description' => 'WordPress cron: DISABLE_WP_CRON, cron\'un en son ne zaman çalıştığı (last_seen, unix), görev sayısı, gecikmiş görevler ve görev listesi (hook, next unix, in_s saniye, schedule). overdue_only ile yalnız gecikenler. timings: wp-cron.php isteklerinde ölçülen kanca başına süre (bir günden eski özet bir sonraki çalışmada sıfırlanır; last_at unix; runs, avg_ms, max_ms, last_ms, total_ms, component = hangi eklenti, cut = süre dolup kesildi). "WP-Cron yavaş" denince hangi görevin suçlu olduğunu buradan bul.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array( 'overdue_only' => array( 'type' => 'boolean' ) ),
		),
		'run'         => function ( $a ) {
			$c = flxp_cron();
			if ( ! empty( $a['overdue_only'] ) ) {
				$c['items'] = array_values(
					array_filter(
						$c['items'],
						function ( $e ) {
							return $e['overdue'];
						}
					)
				);
			}
			$c['items']   = array_slice( $c['items'], 0, 200 );
			$c['timings'] = FLXP_Cron_Timing::stats();
			return $c;
		},
	);

	$tools['flxp_profile_url'] = array(
		'title'       => __( 'Profile page', 'flx-speed' ),
		'description' => 'Sitedeki bir adresi ziyaretçi gibi (önbelleksiz) açar, o isteği tüm ayrıntıyla (sorgular, HTTP, hatalar, zaman çizelgesi) kaydeder ve kaydı döndürür. Yalnız bu sitenin adresleri; yol ("/bagis/") ya da tam adres.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array( 'url' => array( 'type' => 'string' ) ),
		),
		'run'         => function ( $a ) {
			$res = flxp_profile_url( $a['url'] ?? '/' );
			if ( is_wp_error( $res ) ) {
				throw new Exception( $res->get_error_message() );
			}
			$r = FLXP_Store::get( $res['id'] );
			return flxp_mcp_request_view( $r, array() );
		},
	);

	$tools['flxp_cache_status'] = array(
		'title'       => __( 'Cache status', 'flx-speed' ),
		'description' => 'Flux Speed: sayfa önbelleği (LiteSpeed Cache: kurulu/etkin, cache açık mı, sunucu LiteSpeed mi) ve nesne önbelleği (object-cache.php sahibi: sqlite | litespeed | redis | other, kalıcı mı, SQLite dosya boyutu, LiteSpeed object kind/host/port), PHP uzantıları (memcached, redis, sqlite3, igbinary, apcu), çakışma kodları (both_on, sqlite_dropin_taken, sqlite_dropin_missing, ls_dropin_taken, ls_no_backend, sqlite_orphan, two_page_caches) ve son kalıcılık testi. Değişiklik yapmaz; geçiş/kurulum Performans › Önbellek ekranından yapılır.',
		'readonly'    => true,
		'cap'         => 'flxp_view',
		'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
		'run'         => function () {
			$st = FLXP_Cache::status();
			$st['conflict_text'] = array_map( array( 'FLXP_Cache', 'conflict_text' ), $st['conflicts'] );
			unset( $st['server']['software'] );
			if ( ! empty( $st['sqlite']['db']['path'] ) ) {
				$st['sqlite']['db']['path'] = basename( $st['sqlite']['db']['path'] );
			}
			return $st;
		},
	);

	return $tools;
}

add_filter(
	'flxseo_mcp_tools',
	function ( $tools ) {
		return array_merge( (array) $tools, flxp_tools() );
	}
);

/** Flux AI & MCP yoksa Flux AI sohbetine doğrudan. */
add_filter(
	'flxai_tools',
	function ( $tools ) {
		if ( function_exists( 'flxseo_mcp_tools' ) ) {
			return $tools;
		}
		foreach ( flxp_tools() as $name => $t ) {
			$t['source']      = 'flx-speed';
			$tools[ $name ] = $t;
		}
		return $tools;
	}
);
