<?php
/**
 * AI ve MCP araçları. Flux SEO'nun `flxseo_mcp_tools` filtresine eklenir; Flux AI sohbeti bu araçları
 * otomatik alır (yazan araçlar sohbette çalışmadan önce kullanıcı onayı ister).
 *
 * Dış MCP istemcisinden (flx-mcp/v1/mcp) gelen flxk_preset_patch_apply varsayılan olarak bekleyen yama oluşturur;
 * yönetici Görünüm › Ön ayarlar'dan ya da sohbetten onaylar. Ayarlardan "MCP doğrudan uygulasın" açılabilir.
 */

defined( 'ABSPATH' ) || exit;

/** Bu istek dış MCP uç noktasından mı geliyor? */
function flxk_is_external_mcp() {
	return ! empty( $GLOBALS['flxk_external_mcp'] );
}

add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		if ( 0 === strpos( (string) $request->get_route(), '/flx-mcp/v1/' ) ) {
			$GLOBALS['flxk_external_mcp'] = true;
		}
		return $result;
	},
	5,
	3
);

function flxk_settings() {
	$s = get_option( 'flxk_settings', array() );
	return wp_parse_args(
		is_array( $s ) ? $s : array(),
		array(
			'mcp_direct' => 0,
		)
	);
}

add_filter( 'flxseo_mcp_tools', 'flxk_mcp_tools' );
function flxk_mcp_tools( $tools ) {
	$patch = array(
		'type'        => 'object',
		'description' => 'flx-preset/1 parçası: yalnız değişecek alanlar. Biçim için flxk_preset_schema.',
	);
	$cap   = flxk_cap();

	$tools['flxk_preset_schema'] = array(
		'aliases'     => array( 'preset_schema' ), // Flux Setup 0.x öneksiz adı.
		'source'      => 'flx-setup',
		'title'       => 'Ön ayar biçimi',
		'description' => 'Site ön ayarı / yama biçimini (flx-preset/1 JSON Schema), içerik türlerini, profil alanlarını ve yazılabilir ayarları döner. Siteyi değiştirmeden önce bir kez çağır.',
		'readonly'    => true,
		'cap'         => $cap,
		'run'         => function () {
			$schema = json_decode( (string) file_get_contents( FLXK_DIR . 'schema/flx-preset.schema.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$types  = array();
			foreach ( flxk_importers() as $k => $t ) {
				$types[ $k ] = (string) ( $t['label'] ?? $k );
			}
			return array(
				'schema'         => $schema,
				'content_types'  => $types,
				'profile_fields' => flxk_profile_fields(),
				'options'        => flxk_export_options(),
				'settings'       => flxk_settings_schema(),
				'theme'          => get_stylesheet(),
				'guide'          => flxk_ai_guide(),
			);
		},
	);

	$tools['flxk_preset_list'] = array(
		'aliases'     => array( 'preset_list' ), // Flux Setup 0.x öneksiz adı.
		'source'      => 'flx-setup',
		'title'       => 'Ön ayarlar',
		'description' => 'Kurulu ön ayarlar, uygulanma durumu, bekleyen migration ve yamalar.',
		'readonly'    => true,
		'cap'         => $cap,
		'run'         => function () {
			$out = array();
			foreach ( flxk_presets() as $id => $p ) {
				$st         = flxk_state( $id );
				$out[ $id ] = array(
					'name'       => $p['name'],
					'version'    => $p['version'] ?? 1,
					'applied'    => (bool) $st,
					'applied_at' => $st['applied_at'] ?? '',
					'pending'    => wp_list_pluck( flxk_pending_migrations( $id ), 'title', 'id' ),
					'error'      => $p['error'] ?? '',
				);
			}
			$pend = array();
			foreach ( flxk_pending_patches() as $pid => $pp ) {
				$pend[ $pid ] = array(
					'note'   => $pp['note'],
					'source' => $pp['source'],
					'at'     => $pp['at'],
				);
			}
			return array(
				'presets'         => $out,
				'pending_patches' => $pend,
			);
		},
	);

	$tools['flxk_preset_export'] = array(
		'aliases'     => array( 'preset_export' ), // Flux Setup 0.x öneksiz adı.
		'source'      => 'flx-setup',
		'title'       => 'Siteyi ön ayar olarak oku',
		'description' => 'Mevcut siteyi flx-preset/1 JSON\'u olarak döner (gizli alanlar hariç). sections: profile, wp, settings, theme, content, media. Değiştirmeden önce mevcut hali görmek için kullan.',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'sections' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'pages'    => array( 'type' => 'boolean' ),
			),
		),
		'run'         => function ( $args ) {
			$a = array();
			if ( ! empty( $args['sections'] ) ) {
				$a['sections'] = array_map( 'sanitize_key', (array) $args['sections'] );
			}
			if ( isset( $args['pages'] ) ) {
				$a['pages'] = (bool) $args['pages'];
			}
			return flxk_export( $a );
		},
	);

	$tools['flxk_preset_patch_preview'] = array(
		'aliases'     => array( 'preset_patch_preview' ), // Flux Setup 0.x öneksiz adı.
		'source'      => 'flx-setup',
		'title'       => 'Yama önizle',
		'description' => 'Bir yamanın siteye etkisini gösterir, hiçbir şey yazmaz. status: add, change, same, keep, manual (elle değiştirilmiş, atlanır).',
		'readonly'    => true,
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array( 'patch' => $patch ),
			'required'   => array( 'patch' ),
		),
		'run'         => function ( $args ) {
			$r = flxk_patch_preview( $args['patch'] ?? array() );
			return is_wp_error( $r ) ? array( 'error' => $r->get_error_message() ) : $r;
		},
	);

	$tools['flxk_preset_patch_apply'] = array(
		'aliases'     => array( 'preset_patch_apply' ), // Flux Setup 0.x öneksiz adı.
		'source'      => 'flx-setup',
		'title'       => 'Yamayı uygula',
		'description' => 'Yamayı uygular. Önce flxk_preset_patch_preview ile kullanıcıya özet göster. note: kısa açıklama (geçmişte görünür). Dış MCP\'den çağrılınca yönetici onayına düşer.',
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array(
				'patch' => $patch,
				'note'  => array( 'type' => 'string' ),
			),
			'required'   => array( 'patch', 'note' ),
		),
		'run'         => function ( $args ) {
			$note = (string) ( $args['note'] ?? '' );
			if ( flxk_is_external_mcp() && ! flxk_settings()['mcp_direct'] ) {
				$id = flxk_patch_submit( $args['patch'] ?? array(), 'mcp', $note );
				return is_wp_error( $id ) ? array( 'error' => $id->get_error_message() ) : array(
					'pending' => $id,
					'message' => 'Yama yönetici onayına gönderildi (Görünüm › Ön ayarlar).',
					'url'     => flxk_admin_url() . '#yamalar',
				);
			}
			$r = flxk_patch_apply( $args['patch'] ?? array(), flxk_is_external_mcp() ? 'mcp' : 'ai', $note );
			return is_wp_error( $r ) ? array( 'error' => $r->get_error_message() ) : $r;
		},
	);

	$tools['flxk_preset_revert'] = array(
		'aliases'     => array( 'preset_revert' ), // Flux Setup 0.x öneksiz adı.
		'source'      => 'flx-setup',
		'title'       => 'Değişikliği geri al',
		'description' => 'flxk_preset_history\'deki bir çalıştırmayı (run) geri alır. Sonradan elle değiştirilen değerlere dokunmaz.',
		'cap'         => $cap,
		'schema'      => array(
			'type'       => 'object',
			'properties' => array( 'run' => array( 'type' => 'string' ) ),
			'required'   => array( 'run' ),
		),
		'run'         => function ( $args ) {
			$r = flxk_revert_run( sanitize_text_field( (string) ( $args['run'] ?? '' ) ) );
			return is_wp_error( $r ) ? array( 'error' => $r->get_error_message() ) : $r;
		},
	);

	$tools['flxk_preset_history'] = array(
		'aliases'     => array( 'preset_history' ), // Flux Setup 0.x öneksiz adı.
		'source'      => 'flx-setup',
		'title'       => 'Ön ayar geçmişi',
		'description' => 'Son uygulanan ön ayar, migration ve yamalar (run kimliğiyle).',
		'readonly'    => true,
		'cap'         => $cap,
		'run'         => function () {
			return array( 'history' => array_slice( flxk_history(), 0, 30 ) );
		},
	);

	return $tools;
}

/** AI için kısa kullanım kılavuzu. */
function flxk_ai_guide() {
	return implode(
		"\n",
		array(
			'Siteyi değiştirmek için yama yaz: yalnız değişecek alanları içeren flx-preset/1 nesnesi ("format": "flx-preset/1").',
			'Önce flxk_preset_export ile ilgili bölümü oku, sonra flxk_preset_patch_preview ile önizle, kullanıcıya kısa özet ver, onay alınca flxk_preset_patch_apply.',
			'Bölümler: profile (kurum kimliği), wp (çekirdek ayarlar), settings (option => alanlar, derin birleşir), theme.styles (theme.json), theme.templates / theme.parts (header, footer… blok markup), content (type+key ile sayfa, menü, ürün, paket türleri), media.',
			'Sayfa gövdesi blok markup ya da blok ağacıdır: [{"name":"core/heading","attrs":{"level":2},"html":"<h2 class=\"wp-block-heading\">Başlık</h2>"}, {"name":"core/group","html":"<div class=\"wp-block-group\">{{inner}}</div>","inner":[...]}].',
			'Yer tutucular: {profile.name}, {domain}, {home}, {year}, "@page:key" (ID), {url:@page:key}, {url:@media:logo}.',
			'Ayar alanlarının adı, türü ve varsayılanı flxk_preset_schema çıktısındaki "settings" şemasında (option => fields). Ör. çerez banner\'ı: settings.flx_ayarlar.cookie_enabled = 1.',
			'manual durumundaki değerleri kullanıcı elle değiştirmiştir; özellikle istemedikçe zorlamaya çalışma.',
			'Şifre, API key ve token yazılamaz; kullanıcıya ilgili panelden girmesini söyle.',
		)
	);
}
