<?php
/**
 * Araç kayıt defteri: kaynak tespiti, ad çakışmaları ve eski adlar (alias).
 *
 * Araçlar üç yoldan gelir: Flux AI SDK (flx_ai_tool), `flx_mcp_tools` / `flxseo_mcp_tools` filtreleri (MCP + sohbet)
 * ve `flxai_tools` (yalnız sohbet). Filtreler geri çağrı geri çağrı uygulanır; böylece her aracı hangi eklentinin
 * eklediği bilinir ve aynı adı ikinci bir eklenti kaydettiğinde ilkinin üzerine yazılmaz:
 *  - İlk kaydeden açık kalır, sonraki "çakışan" olarak kapalı bekler.
 *  - Yönetici Flux AI & MCP › Araçlar'da hangisinin açık olacağını seçer (ayar tool_pick: ad => kaynak).
 *  - Aynı eklentinin aynı adı yeniden tanımlaması (ör. Flux CRM'in sohbet sürümü) ya da başka eklentinin mevcut
 *    aracı genişletmesi (açıklama/çalıştırıcı sarmalama; `source` korunur, ör. Flux Lang) çakışma sayılmaz.
 *
 * Eski adlar: araç tanımındaki 'aliases' => array( 'eski_ad' ). MCP çağrıları, anahtar kapsamları ve kapalı araç
 * listesi eski adla da çalışır; tools/list yalnız yeni adı gösterir.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

/** Geri çağrının tanımlandığı eklentinin klasörü ('' bilinmiyorsa). */
function flxai_callable_source( $fn ) {
	try {
		if ( is_array( $fn ) ) {
			$ref = new ReflectionMethod( $fn[0], $fn[1] );
		} elseif ( is_string( $fn ) && false !== strpos( $fn, '::' ) ) {
			$ref = new ReflectionMethod( $fn );
		} elseif ( is_object( $fn ) && ! $fn instanceof Closure ) {
			$ref = new ReflectionMethod( $fn, '__invoke' );
		} else {
			$ref = new ReflectionFunction( $fn );
		}
		$file = wp_normalize_path( (string) $ref->getFileName() );
	} catch ( Throwable $e ) {
		return '';
	}
	static $bases = null;
	if ( null === $bases ) {
		$bases = array(
			trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ),
			trailingslashit( wp_normalize_path( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins' ) ),
		);
	}
	foreach ( $bases as $base ) {
		if ( 0 === strpos( $file, $base ) ) {
			return (string) strtok( substr( $file, strlen( $base ) ), '/' );
		}
	}
	if ( 0 === strpos( $file, wp_normalize_path( get_theme_root() ) ) ) {
		return 'theme';
	}
	if ( preg_match( '#/plugins/([^/]+)/#', $file, $m ) ) {
		return $m[1]; // Sembolik bağlı eklenti klasörü.
	}
	return '';
}

/**
 * Filtreyi geri çağrı geri çağrı uygular (apply_filters ile aynı sıra), kaynakları işler, çakışmaları ayırır.
 *
 * @param string $tag   Filtre adı.
 * @param array  $tools Başlangıç araçları (her biri 'source' taşır).
 * @param array  $dupes Çakışan tanımlar: ad => kaynak => tanım (doldurulur).
 * @return array
 */
function flxai_tools_apply_filter( $tag, array $tools, array &$dupes ) {
	global $wp_filter;
	if ( empty( $wp_filter[ $tag ] ) || ! $wp_filter[ $tag ] instanceof WP_Hook ) {
		return $tools;
	}
	$cbs = $wp_filter[ $tag ]->callbacks;
	ksort( $cbs, SORT_NUMERIC );
	foreach ( $cbs as $list ) {
		foreach ( $list as $cb ) {
			$out = call_user_func( $cb['function'], $tools );
			if ( ! is_array( $out ) ) {
				continue; // Hatalı geri çağrı listeyi silmesin.
			}
			$src = null;
			foreach ( $out as $name => $t ) {
				if ( ! is_array( $t ) ) {
					continue;
				}
				$prev = $tools[ $name ] ?? null;
				if ( $prev === $t ) {
					continue;
				}
				if ( empty( $t['source'] ) ) {
					if ( null === $src ) {
						$src = flxai_callable_source( $cb['function'] );
					}
					$t['source'] = '' !== $src ? $src : flxai_callable_source( $t['run'] ?? null );
				}
				if ( is_array( $prev ) && ! empty( $prev['source'] ) && $prev['source'] !== $t['source'] ) {
					$dupes[ $name ][ $t['source'] ] = $t;
					$out[ $name ]                   = $prev;
					continue;
				}
				$out[ $name ] = $t;
			}
			$tools = $out;
		}
	}
	return $tools;
}

/** Aracı listeye ekler; ad doluysa ve kaynak farklıysa çakışan olarak ayırır. */
function flxai_tools_add( array &$tools, array &$dupes, $name, array $t ) {
	if ( isset( $tools[ $name ] ) && ( $tools[ $name ]['source'] ?? '' ) !== ( $t['source'] ?? '' ) ) {
		$dupes[ $name ][ (string) $t['source'] ] = $t;
		return;
	}
	$tools[ $name ] = $t;
}

/** Yöneticinin seçimine göre çakışan adlarda açık olanı değiştirir. */
function flxai_tools_apply_picks( array &$tools, array &$dupes ) {
	foreach ( (array) flxai_setting( 'tool_pick', array() ) as $name => $src ) {
		if ( ! isset( $tools[ $name ], $dupes[ $name ][ $src ] ) ) {
			continue;
		}
		$cur                                         = $tools[ $name ];
		$tools[ $name ]                              = $dupes[ $name ][ $src ];
		$dupes[ $name ][ (string) $cur['source'] ] = $cur;
		unset( $dupes[ $name ][ $src ] );
	}
}

/** Eski ad → yeni ad haritası (gerçek araç adlarıyla çakışan eski adlar yok sayılır). */
function flxai_tools_aliases( array $tools ) {
	$map = array();
	foreach ( $tools as $name => $t ) {
		foreach ( (array) ( $t['aliases'] ?? array() ) as $old ) {
			$old = (string) $old;
			if ( '' !== $old && ! isset( $tools[ $old ] ) && ! isset( $map[ $old ] ) ) {
				$map[ $old ] = $name;
			}
		}
	}
	return $map;
}

/** SDK ile kaydedilen araçlar ($want: mcp | chat). */
function flxai_sdk_tools( $want ) {
	if ( ! function_exists( 'flx_ai_registry' ) ) {
		return array();
	}
	$out = array();
	foreach ( (array) flx_ai_registry()['tools'] as $name => $by_src ) {
		foreach ( (array) $by_src as $t ) {
			if ( ! empty( $t[ $want ] ) ) {
				$out[] = array( $name, $t );
			}
		}
	}
	return $out;
}

/**
 * MCP araçları (çakışmalar ayrılmış, seçimler uygulanmış).
 *
 * @return array{tools: array, dupes: array, aliases: array}
 */
function flxai_mcp_registry( $fresh = false ) {
	static $cache = null;
	if ( null !== $cache && ! $fresh ) {
		return $cache;
	}
	$tools = array();
	$dupes = array();
	foreach ( flxai_sdk_tools( 'mcp' ) as list( $name, $t ) ) {
		flxai_tools_add( $tools, $dupes, $name, $t );
	}
	$tools = flxai_tools_apply_filter( 'flx_mcp_tools', $tools, $dupes );
	$tools = flxai_tools_apply_filter( 'flxseo_mcp_tools', $tools, $dupes );
	foreach ( $tools as $name => $t ) {
		if ( ! is_array( $t ) || empty( $t['run'] ) || ! is_callable( $t['run'] ) || ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', (string) $name ) ) {
			unset( $tools[ $name ] );
			continue;
		}
		$tools[ $name ] += array(
			'title'       => $name,
			'description' => '',
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
		);
	}
	flxai_tools_apply_picks( $tools, $dupes );
	$cache = array(
		'tools'   => $tools,
		'dupes'   => $dupes,
		'aliases' => flxai_tools_aliases( $tools ),
	);
	return $cache;
}

/**
 * Sohbet araçları: MCP araçları + SDK sohbet araçları + Flux AI'nin kendi araçları + `flxai_tools`.
 *
 * @return array{tools: array, dupes: array, aliases: array}
 */
function flxai_chat_registry( $fresh = false ) {
	static $cache = null;
	if ( null !== $cache && ! $fresh ) {
		return $cache;
	}
	$mcp   = flxai_mcp_registry( $fresh );
	$tools = array();
	$dupes = $mcp['dupes'];
	foreach ( $mcp['tools'] as $name => $t ) {
		$tools[ $name ] = array(
			'title'       => (string) ( $t['title'] ?? $name ),
			'description' => (string) ( $t['description'] ?? '' ),
			'schema'      => $t['schema'] ?? array( 'type' => 'object', 'properties' => new stdClass() ),
			'readonly'    => ! empty( $t['readonly'] ),
			'confirm'     => ! empty( $t['confirm'] ),
			'cap'         => (string) ( $t['cap'] ?? 'edit_posts' ),
			'source'      => (string) ( $t['source'] ?? '' ),
			'aliases'     => (array) ( $t['aliases'] ?? array() ),
			'mcp'         => true,
			'run'         => null,
		);
	}
	foreach ( flxai_sdk_tools( 'chat' ) as list( $name, $t ) ) {
		if ( isset( $tools[ $name ] ) && ( $tools[ $name ]['source'] ?? '' ) === $t['source'] ) {
			continue; // Aynı araç MCP üzerinden zaten geldi.
		}
		$t['mcp'] = false;
		flxai_tools_add( $tools, $dupes, $name, $t );
	}
	foreach ( flxai_core_tools() as $name => $t ) {
		flxai_tools_add( $tools, $dupes, $name, $t );
	}
	$tools = flxai_tools_apply_filter( 'flxai_tools', $tools, $dupes );
	foreach ( $tools as $name => $t ) {
		if ( ! is_array( $t ) || ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', (string) $name ) || ( empty( $t['mcp'] ) && ( empty( $t['run'] ) || ! is_callable( $t['run'] ) ) ) ) {
			unset( $tools[ $name ] );
			continue;
		}
		$tools[ $name ] += array(
			'title'       => $name,
			'description' => '',
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'readonly'    => false,
			'cap'         => 'read',
			'source'      => '',
			'confirm'     => false,
			'aliases'     => array(),
		);
	}
	flxai_tools_apply_picks( $tools, $dupes );
	$cache = array(
		'tools'   => $tools,
		'dupes'   => $dupes,
		'aliases' => array_merge( $mcp['aliases'], flxai_tools_aliases( $tools ) ),
	);
	return $cache;
}

/** Eski adı yeni ada çevirir (alias değilse aynen). */
function flxai_tool_resolve( $name ) {
	$name = (string) $name;
	if ( flxai_mcp_legacy() ) {
		return $name;
	}
	$a = flxai_chat_registry()['aliases'];
	return $a[ $name ] ?? $name;
}

/** Kapalı araçlar (eski adlar yeni adlara çevrilmiş). Sohbette ve MCP'de geçerlidir. */
function flxai_tools_off() {
	return array_values( array_unique( array_map( 'flxai_tool_resolve', array_map( 'strval', (array) flxai_setting( 'tools_off', array() ) ) ) ) );
}

/** Araç kapalı mı (Flux AI & MCP › Araçlar)? */
function flxai_tool_is_off( $name ) {
	return in_array( flxai_tool_resolve( $name ), flxai_tools_off(), true );
}

/**
 * Kaynak eklenti bilgisi: ad (eklenti başlığı) ve klasör. "Kaynak" sütunu ve süzgeç için.
 *
 * @return array{id: string, label: string}
 */
function flxai_source_info( $id ) {
	static $cache = array();
	$id = (string) $id;
	if ( isset( $cache[ $id ] ) ) {
		return $cache[ $id ];
	}
	$label = '';
	if ( function_exists( 'flx_ai_registry' ) ) {
		$label = (string) ( flx_ai_registry()['plugins'][ $id ]['label'] ?? '' );
	}
	if ( '' === $label && '' !== $id && 'theme' !== $id ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( get_plugins( '/' . $id ) as $data ) {
			$label = (string) ( $data['Name'] ?? '' );
			break;
		}
	}
	if ( '' === $label ) {
		$known = flxai_sources();
		$label = isset( $known[ $id ] ) ? (string) $known[ $id ] : '';
	}
	if ( 'theme' === $id ) {
		$label = (string) wp_get_theme()->get( 'Name' );
	}
	if ( '' === $label ) {
		$label = '' !== $id ? $id : __( 'Bilinmiyor', 'flx-ai' );
	}
	$cache[ $id ] = array(
		'id'    => $id,
		'label' => $label,
	);
	return $cache[ $id ];
}

/** Araç adı kaynağın önekiyle mi başlıyor? (öneksiz araçlar başka eklentilerle karışabilir) */
function flxai_tool_prefixed( $name, $source ) {
	$name = (string) $name;
	if ( function_exists( 'flx_ai_registry' ) ) {
		$p = (string) ( flx_ai_registry()['plugins'][ $source ]['prefix'] ?? '' );
		if ( '' !== $p ) {
			return 0 === strpos( $name, $p . '_' );
		}
	}
	// SDK kaydı yoksa: ad bir önekle (flx…_, ya da kaynağın tiresiz adıyla) başlıyor mu.
	$plain = str_replace( '-', '', (string) $source );
	return (bool) preg_match( '/^flx[a-z]*_/', $name ) || ( '' !== $plain && 0 === strpos( $name, $plain . '_' ) );
}

/* SDK ile kaydolan eklentiler kaynak listesine (raporlar, eklentiye özel model) ve Bağlı sistemler'e eklenir. */
add_filter(
	'flxai_sources',
	function ( $list ) {
		if ( function_exists( 'flx_ai_registry' ) && did_action( 'plugins_loaded' ) ) {
			foreach ( (array) flx_ai_registry()['plugins'] as $slug => $p ) {
				if ( ! isset( $list[ $slug ] ) ) {
					$list[ $slug ] = $p['label'];
				}
			}
		}
		return $list;
	},
	5
);

add_filter(
	'flxai_systems',
	function ( $list ) {
		if ( function_exists( 'flx_ai_registry' ) && did_action( 'plugins_loaded' ) ) {
			foreach ( (array) flx_ai_registry()['plugins'] as $slug => $p ) {
				$list[ $slug ] = array_merge(
					array(
						'label'  => $p['label'],
						'plugin' => '' !== $p['plugin'] ? $p['plugin'] : $slug . '/' . $slug . '.php',
						'page'   => $p['page'],
					),
					(array) ( $list[ $slug ] ?? array() )
				);
			}
		}
		return $list;
	},
	5
);
