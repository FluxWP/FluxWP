<?php
/**
 * Yapay zekâ araçları (MCP: Flux AI & MCP, sohbet: Flux AI; Flux AI MCP araçlarını kendiliğinden alır).
 *
 * - Kendi araçları: flxl_languages, flxl_translations, flxl_save_translation, flxl_set_language,
 *   flxl_untranslated, flxl_image_language, flxl_term_translation, flxl_site_texts.
 * - Flux SEO çekirdek araçlarına "lang" eklenir: list_content (süzgeç), get_content (lang + translations),
 *   create_content (lang + translation_of), update_content (lang). Flux SEO koduna dokunulmaz.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

function flxl_tools() {
	$codes = flxl_language_codes();
	$lang  = array(
		'type'        => 'string',
		'enum'        => $codes,
		'description' => 'Dil kodu. Varsayılan (adreste öneksiz) dil: ' . flxl_default_language() . '.',
	);
	$seo   = array(
		'type'       => 'object',
		'properties' => array(
			'title'         => array( 'type' => 'string', 'description' => 'SEO başlığı (≤60 karakter).' ),
			'description'   => array( 'type' => 'string', 'description' => 'Meta açıklama (120–160 karakter).' ),
			'focus_keyword' => array( 'type' => 'string' ),
		),
	);
	return array(
		'flxl_languages'        => array(
			'title'       => 'Diller',
			'description' => 'Sitenin etkin dilleri (kod, ad, locale, adres öneki), varsayılan dil, çevrilen içerik türleri ve dil başına yayındaki içerik sayıları. Çok dilli içerik işine başlamadan önce çağırın.',
			'readonly'    => true,
			'cap'         => 'edit_posts',
			'schema'      => array( 'type' => 'object', 'properties' => new stdClass() ),
			'run'         => function () {
				$counts = array();
				foreach ( flxl_language_codes() as $c ) {
					$q            = new WP_Query(
						array(
							'post_type'      => flxl_post_types(),
							'post_status'    => 'publish',
							'posts_per_page' => 1,
							'fields'         => 'ids',
							'flxl_lang'       => $c,
							'no_found_rows'  => false,
						)
					);
					$counts[ $c ] = (int) $q->found_posts;
				}
				$langs = array();
				foreach ( flxl_languages() as $c => $l ) {
					$langs[] = array(
						'code'      => $c,
						'name'      => $l['name'],
						'locale'    => flxl_language_locale( $c ),
						'home_url'  => flxl_url_for_language( trailingslashit( flxl_root() ), $c ),
						'published' => $counts[ $c ] ?? 0,
					);
				}
				return array(
					'default'    => flxl_default_language(),
					'languages'  => $langs,
					'post_types' => flxl_post_types(),
					'fallback'   => flxl_fallback(),
					'notes'      => 'Her dil sürümü ayrı bir içeriktir (kendi ID, başlık, gövde, slug, öne çıkan görsel, SEO). Çeviri oluşturmak/güncellemek için flxl_save_translation. Kategoriler ortaktır; adlarını flxl_term_translation ile çevirin. Görselin dile özgü alt metni veya farklı görseli için flxl_image_language.',
				);
			},
		),
		'flxl_translations'     => array(
			'title'       => 'Çevirileri göster',
			'description' => 'Bir içeriğin dili ve tüm dil sürümleri (ID, başlık, durum, adres; olmayan dil null).',
			'readonly'    => true,
			'cap'         => 'edit_posts',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array( 'id' => array( 'type' => 'integer' ) ),
				'required'   => array( 'id' ),
			),
			'run'         => function ( $a ) {
				$p = get_post( (int) ( $a['id'] ?? 0 ) );
				if ( ! $p || ! current_user_can( 'edit_post', $p->ID ) ) {
					throw new Exception( 'İçerik bulunamadı.' );
				}
				return array(
					'id'           => $p->ID,
					'lang'         => flxl_post_language( $p ),
					'translatable' => flxl_is_translatable_type( $p->post_type ),
					'translations' => flxl_translations_brief( $p->ID ),
				);
			},
		),
		'flxl_untranslated'     => array(
			'title'       => 'Çevrilmemiş içerikler',
			'description' => 'Varsayılan dilde yayında olup verilen dilde (yayında) çevirisi olmayan içerikler. Çeviri iş listesi için.',
			'readonly'    => true,
			'cap'         => 'edit_posts',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'lang'      => $lang,
					'post_type' => array( 'type' => 'string', 'enum' => flxl_post_types() ),
					'limit'     => array( 'type' => 'integer', 'default' => 50, 'maximum' => 200 ),
				),
				'required'   => array( 'lang' ),
			),
			'run'         => function ( $a ) {
				$code = (string) ( $a['lang'] ?? '' );
				if ( ! flxl_is_language( $code ) || $code === flxl_default_language() ) {
					throw new Exception( 'Geçersiz dil.' );
				}
				$types = ! empty( $a['post_type'] ) && flxl_is_translatable_type( $a['post_type'] ) ? array( $a['post_type'] ) : flxl_post_types();
				$q     = new WP_Query(
					array(
						'post_type'      => $types,
						'post_status'    => 'publish',
						'posts_per_page' => 500,
						'flxl_lang'       => flxl_default_language(),
						'orderby'        => 'menu_order date',
						'order'          => 'ASC',
					)
				);
				$out   = array();
				foreach ( $q->posts as $p ) {
					if ( flxl_is_shared( $p->ID ) ) {
						continue;
					}
					$tr = flxl_get_translation( $p->ID, $code );
					if ( $tr && 'publish' === get_post_status( $tr ) ) {
						continue;
					}
					$out[] = array(
						'id'          => $p->ID,
						'type'        => $p->post_type,
						'title'       => get_the_title( $p ),
						'url'         => get_permalink( $p ),
						'draft_id'    => $tr ? $tr : null,
						'length'      => strlen( wp_strip_all_tags( $p->post_content ) ),
					);
					if ( count( $out ) >= min( 200, max( 1, (int) ( $a['limit'] ?? 50 ) ) ) ) {
						break;
					}
				}
				return array( 'lang' => $code, 'count' => count( $out ), 'items' => $out );
			},
		),
		'flxl_save_translation' => array(
			'title'       => 'Çeviri kaydet',
			'description' => 'source_id içeriğinin "lang" dilindeki sürümünü oluşturur ya da günceller. Yoksa kaynaktan kopyalanır (şablon, kategoriler, öne çıkan görsel, meta) ve verilen alanlarla değiştirilir; varsa yalnız verilen alanlar değişir. Gövde Gutenberg blok işaretlemesi olmalı (kaynağın blok yapısını koruyun, yalnız metinleri çevirin) ya da format=markdown/html. Yeni çeviri varsayılan taslak. Slug çeviri dilinde verin (ör. about-us).',
			'cap'         => 'edit_posts',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'source_id'          => array( 'type' => 'integer', 'description' => 'Kaynak içeriğin (genelde Türkçe) ID\'si.' ),
					'lang'               => $lang,
					'title'              => array( 'type' => 'string' ),
					'content'            => array( 'type' => 'string' ),
					'format'             => array( 'type' => 'string', 'enum' => array( 'auto', 'blocks', 'html', 'markdown' ), 'default' => 'auto' ),
					'excerpt'            => array( 'type' => 'string' ),
					'slug'               => array( 'type' => 'string' ),
					'status'             => array( 'type' => 'string', 'enum' => array( 'draft', 'publish', 'pending', 'private' ) ),
					'featured_image_id'  => array( 'type' => 'integer', 'description' => 'Bu dil sürümüne özel öne çıkan görsel (0 = kaldır).' ),
					'featured_image_url' => array( 'type' => 'string', 'description' => 'URL verilirse medya kütüphanesine indirilir ve bu sürümün öne çıkan görseli olur.' ),
					'seo'                => $seo,
					'ai_translate'       => array( 'type' => 'boolean', 'description' => 'true: alan vermeden kaynağı Flux AI ile otomatik çevir (yalnız kısa içerik; uzun sayfalar için metni kendiniz çevirip content verin).' ),
				),
				'required'   => array( 'source_id', 'lang' ),
			),
			'run'         => function ( $a ) {
				$src  = (int) ( $a['source_id'] ?? 0 );
				$code = (string) ( $a['lang'] ?? '' );
				if ( ! empty( $a['ai_translate'] ) ) {
					$job = flxl_ai_job_start( $src, $code );
					if ( is_wp_error( $job ) ) {
						throw new Exception( $job->get_error_message() );
					}
					if ( $job['steps'] > 3 ) {
						throw new Exception( 'İçerik uzun; parça parça kendiniz çevirip content ile gönderin ya da paneldeki "AI ile çevir" düğmesini kullanın. Taslak oluşturuldu: ID ' . $job['post_id'] );
					}
					for ( $i = 0; $i < $job['steps']; $i++ ) {
						$r = flxl_ai_job_step( $job['job'], $i );
						if ( is_wp_error( $r ) ) {
							throw new Exception( $r->get_error_message() );
						}
					}
				}
				$fields = array();
				foreach ( array( 'title', 'excerpt', 'slug', 'status' ) as $k ) {
					if ( isset( $a[ $k ] ) ) {
						$fields[ $k ] = (string) $a[ $k ];
					}
				}
				if ( isset( $a['content'] ) ) {
					$fields['content'] = function_exists( 'flxseo_mcp_to_blocks' ) ? flxseo_mcp_to_blocks( (string) $a['content'], (string) ( $a['format'] ?? '' ) ) : (string) $a['content'];
				}
				if ( isset( $fields['status'] ) && function_exists( 'flxseo_mcp_status' ) ) {
					$p = get_post( $src );
					$fields['status'] = flxseo_mcp_status( $fields['status'], $p ? $p->post_type : 'post' );
				}
				if ( isset( $a['featured_image_id'] ) ) {
					$fields['featured_image_id'] = (int) $a['featured_image_id'];
				}
				$id = flxl_save_translation( $src, $code, $fields );
				if ( is_wp_error( $id ) ) {
					throw new Exception( $id->get_error_message() );
				}
				if ( ! empty( $a['featured_image_url'] ) && function_exists( 'flxseo_mcp_sideload' ) ) {
					$aid = flxseo_mcp_sideload( (string) $a['featured_image_url'], $id, (string) ( $a['title'] ?? get_the_title( $id ) ) );
					if ( $aid && ! is_wp_error( $aid ) ) {
						set_post_thumbnail( $id, (int) $aid );
					}
				}
				if ( ! empty( $a['seo'] ) && is_array( $a['seo'] ) && function_exists( 'flxseo_mcp_save_seo' ) ) {
					flxseo_mcp_save_seo( $id, $a['seo'] );
				}
				return array(
					'saved'        => true,
					'translation'  => flxl_post_brief( $id ),
					'translations' => flxl_translations_brief( $id ),
				);
			},
		),
		'flxl_set_language'     => array(
			'title'       => 'İçeriğin dilini ayarla',
			'description' => 'Var olan bir içeriğin dilini ayarlar; translation_of verilirse o içeriğin çevirisi olarak bağlar (ör. elle yazılmış İngilizce sayfayı Türkçesine bağlamak). unlink=true çeviri bağını koparır.',
			'cap'         => 'edit_posts',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'id'             => array( 'type' => 'integer' ),
					'lang'           => $lang,
					'translation_of' => array( 'type' => 'integer' ),
					'unlink'         => array( 'type' => 'boolean' ),
				),
				'required'   => array( 'id' ),
			),
			'run'         => function ( $a ) {
				$p = get_post( (int) ( $a['id'] ?? 0 ) );
				if ( ! $p || ! current_user_can( 'edit_post', $p->ID ) || ! flxl_is_translatable_type( $p->post_type ) ) {
					throw new Exception( 'İçerik bulunamadı ya da bu tür çevrilmiyor.' );
				}
				if ( ! empty( $a['unlink'] ) ) {
					flxl_unlink_translation( $p->ID );
				}
				if ( ! empty( $a['lang'] ) ) {
					if ( ! flxl_is_language( $a['lang'] ) ) {
						throw new Exception( 'Geçersiz dil.' );
					}
					flxl_set_post_language( $p->ID, $a['lang'] );
				}
				if ( ! empty( $a['translation_of'] ) ) {
					$o = get_post( (int) $a['translation_of'] );
					if ( ! $o || $o->post_type !== $p->post_type ) {
						throw new Exception( 'translation_of aynı türde bir içerik olmalı.' );
					}
					$mine = flxl_post_language( $p );
					$map  = flxl_get_translations( $o->ID );
					if ( isset( $map[ $mine ] ) && (int) $map[ $mine ] !== $p->ID ) {
						throw new Exception( 'O içeriğin bu dilde zaten bir çevirisi var: ID ' . $map[ $mine ] );
					}
					$map[ flxl_post_language( $o ) ] = $o->ID;
					$map[ $mine ]                   = $p->ID;
					flxl_link_translations( $map );
				}
				return array( 'id' => $p->ID, 'lang' => flxl_post_language( $p ), 'translations' => flxl_translations_brief( $p->ID ) );
			},
		),
		'flxl_image_language'   => array(
			'title'       => 'Görselin dil ayarı',
			'description' => 'Bir görselin verilen dilde alt metni, açıklaması ve/veya o dilde yerine gösterilecek görsel (variant_id ya da variant_url ile indirilir). Örn. üzerinde Türkçe yazı olan afişin İngilizce sürümü. Şablon/pattern görselleri dahil sitenin her yerinde uygulanır.',
			'cap'         => 'upload_files',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'attachment_id' => array( 'type' => 'integer' ),
					'lang'          => $lang,
					'alt'           => array( 'type' => 'string' ),
					'caption'       => array( 'type' => 'string' ),
					'variant_id'    => array( 'type' => 'integer', 'description' => '0 = varyantı kaldır.' ),
					'variant_url'   => array( 'type' => 'string' ),
				),
				'required'   => array( 'attachment_id', 'lang' ),
			),
			'run'         => function ( $a ) {
				$id = (int) ( $a['attachment_id'] ?? 0 );
				if ( ! wp_attachment_is_image( $id ) || ! current_user_can( 'edit_post', $id ) ) {
					throw new Exception( 'Görsel bulunamadı.' );
				}
				$data = array_intersect_key( $a, array_flip( array( 'alt', 'caption', 'variant_id' ) ) );
				if ( ! empty( $a['variant_url'] ) ) {
					if ( ! function_exists( 'flxseo_mcp_sideload' ) ) {
						throw new Exception( 'URL\'den görsel indirmek için Flux SEO gerekli; variant_id verin.' );
					}
					$data['variant_id'] = (int) flxseo_mcp_sideload( (string) $a['variant_url'], 0, (string) ( $a['alt'] ?? '' ) );
				}
				if ( ! flxl_set_image_language_data( $id, (string) $a['lang'], $data ) ) {
					throw new Exception( 'Kaydedilemedi (varsayılan dil için alan yok; görselin kendi alanlarını kullanın).' );
				}
				$v = flxl_image_variant( $id, (string) $a['lang'] );
				return array(
					'attachment_id' => $id,
					'lang'          => $a['lang'],
					'alt'           => flxl_image_alt( $id, (string) $a['lang'] ),
					'variant_id'    => $v ? $v : null,
					'variant_url'   => $v ? wp_get_attachment_url( $v ) : null,
				);
			},
		),
		'flxl_term_translation' => array(
			'title'       => 'Kategori/etiket çevirisi',
			'description' => 'Bir terimin (kategori, etiket, proje durumu…) verilen dildeki adı ve açıklaması. Terimler dillerle ortaktır, adres aynı kalır.',
			'cap'         => 'manage_categories',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'term_id'     => array( 'type' => 'integer' ),
					'lang'        => $lang,
					'name'        => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
				),
				'required'   => array( 'term_id', 'lang' ),
			),
			'run'         => function ( $a ) {
				$ok = flxl_set_term_translation( (int) $a['term_id'], (string) $a['lang'], isset( $a['name'] ) ? (string) $a['name'] : null, isset( $a['description'] ) ? (string) $a['description'] : null );
				if ( ! $ok ) {
					throw new Exception( 'Terim ya da dil geçersiz.' );
				}
				return array(
					'term_id'     => (int) $a['term_id'],
					'lang'        => $a['lang'],
					'name'        => flxl_term_text( (int) $a['term_id'], 'name', (string) $a['lang'] ),
					'description' => flxl_term_text( (int) $a['term_id'], 'desc', (string) $a['lang'] ),
				);
			},
		),
		'flxl_site_texts'       => array(
			'title'       => 'Dile göre site metinleri',
			'description' => 'Site adı, slogan ve Flux SEO ana sayfa başlığı/açıklaması gibi metinlerin verilen dildeki karşılıkları. texts verilmezse mevcut değerleri ve anahtarları döner.',
			'cap'         => 'manage_options',
			'schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'lang'  => $lang,
					'texts' => array( 'type' => 'object', 'description' => 'anahtar => metin (boş = kaldır).' ),
				),
				'required'   => array( 'lang' ),
			),
			'run'         => function ( $a ) {
				$code = (string) $a['lang'];
				if ( ! flxl_is_language( $code ) || $code === flxl_default_language() ) {
					throw new Exception( 'Varsayılan dil dışında etkin bir dil verin.' );
				}
				$fields = flxl_text_fields();
				if ( ! empty( $a['texts'] ) && is_array( $a['texts'] ) ) {
					$s = (array) get_option( 'flxl_settings', array() );
					foreach ( $a['texts'] as $k => $v ) {
						if ( isset( $fields[ $k ] ) ) {
							$s['texts'][ $code ][ $k ] = empty( $fields[ $k ]['textarea'] ) ? sanitize_text_field( (string) $v ) : sanitize_textarea_field( (string) $v );
						}
					}
					update_option( 'flxl_settings', $s, true );
				}
				$out = array();
				foreach ( $fields as $k => $f ) {
					$out[ $k ] = array( 'label' => $f['label'], 'value' => flxl_text( $k, $code ) );
				}
				return array( 'lang' => $code, 'texts' => $out );
			},
		),
	);
}

/**
 * MCP: kendi araçlarımız + Flux SEO çekirdek araçlarına dil.
 */
add_filter(
	'flxseo_mcp_tools',
	function ( $tools ) {
		$tools = is_array( $tools ) ? $tools : array();
		$tools = array_merge( $tools, flxl_tools() );
		return flxl_wrap_seo_tools( $tools );
	},
	20
);

/**
 * Flux SEO yoksa araçlar doğrudan Flux AI sohbetine.
 */
add_filter(
	'flxai_tools',
	function ( $tools ) {
		if ( function_exists( 'flxseo_mcp_tools' ) ) {
			return $tools;
		}
		foreach ( flxl_tools() as $name => $t ) {
			$run            = $t['run'];
			$t['source']    = 'flx-lang';
			$t['readonly']  = ! empty( $t['readonly'] );
			$t['run']       = function ( $args ) use ( $run ) {
				return $run( (array) $args );
			};
			$tools[ $name ] = $t;
		}
		return $tools;
	}
);

function flxl_wrap_seo_tools( array $tools ) {
	$lang = array(
		'type'        => 'string',
		'enum'        => array_merge( flxl_language_codes(), array( 'all' ) ),
		'description' => 'Dil (Flux Lang). Varsayılan: ' . flxl_default_language() . '.',
	);
	if ( isset( $tools['list_content'] ) ) {
		$run = $tools['list_content']['run'];
		$tools['list_content']['schema']['properties']['lang'] = $lang + array( 'default' => 'all' );
		$tools['list_content']['description']                .= ' lang ile dil süzülür (all = tüm diller); her satırda lang.';
		$tools['list_content']['run']                          = function ( $a ) use ( $run ) {
			$l   = (string) ( $a['lang'] ?? 'all' );
			$add = function ( $q ) use ( $l ) {
				if ( ! $q->get( 'flxl_lang' ) ) {
					$q->set( 'flxl_lang', flxl_is_language( $l ) ? $l : 'all' );
				}
			};
			add_action( 'pre_get_posts', $add, 5 );
			try {
				$r = $run( $a );
			} finally {
				remove_action( 'pre_get_posts', $add, 5 );
			}
			if ( isset( $r['items'] ) && is_array( $r['items'] ) ) {
				foreach ( $r['items'] as $i => $it ) {
					if ( isset( $it['id'] ) ) {
						$r['items'][ $i ]['lang']         = flxl_post_language( $it['id'] );
						$r['items'][ $i ]['translations'] = (object) flxl_get_translations( $it['id'] );
					}
				}
			}
			return $r;
		};
	}
	if ( isset( $tools['get_content'] ) ) {
		$run                                  = $tools['get_content']['run'];
		$tools['get_content']['description'] .= ' Yanıtta lang ve translations (dil => ID).';
		$tools['get_content']['run']          = function ( $a ) use ( $run ) {
			$r = $run( $a );
			if ( is_array( $r ) && isset( $r['id'] ) ) {
				$r['lang']         = flxl_post_language( $r['id'] );
				$r['translations'] = (object) flxl_get_translations( $r['id'] );
			}
			return $r;
		};
	}
	if ( isset( $tools['create_content'] ) ) {
		$run = $tools['create_content']['run'];
		$tools['create_content']['schema']['properties']['lang']           = array_merge( $lang, array( 'enum' => flxl_language_codes() ) );
		$tools['create_content']['schema']['properties']['translation_of'] = array( 'type' => 'integer', 'description' => 'Bu içerik şu ID\'nin çevirisi (Flux Lang). Kopyalayarak çeviri için flxl_save_translation daha uygun.' );
		$tools['create_content']['description']                           .= ' lang ile dil seçilir (varsayılan ' . flxl_default_language() . '); translation_of ile başka içeriğin çevirisi olarak bağlanır.';
		$tools['create_content']['run'] = function ( $a ) use ( $run ) {
			$l = (string) ( $a['lang'] ?? '' );
			if ( '' !== $l && ! flxl_is_language( $l ) ) {
				throw new Exception( 'Geçersiz dil: ' . $l );
			}
			if ( ! empty( $a['translation_of'] ) ) {
				$o = get_post( (int) $a['translation_of'] );
				if ( ! $o || $o->post_type !== sanitize_key( $a['post_type'] ?? 'post' ) ) {
					throw new Exception( 'translation_of aynı türde (post_type) bir içerik olmalı.' );
				}
				$have = flxl_get_translations( $o->ID );
				if ( '' !== $l && isset( $have[ $l ] ) ) {
					throw new Exception( 'Bu dilde çevirisi zaten var: ID ' . $have[ $l ] . '. update_content ya da flxl_save_translation kullanın.' );
				}
			}
			$r = $run( $a );
			if ( is_array( $r ) && ! empty( $r['id'] ) ) {
				$id = (int) $r['id'];
				if ( '' !== $l ) {
					flxl_set_post_language( $id, $l );
				}
				if ( ! empty( $a['translation_of'] ) && get_post( (int) $a['translation_of'] ) ) {
					$map                                              = flxl_get_translations( (int) $a['translation_of'] );
					$map[ flxl_post_language( (int) $a['translation_of'] ) ] = (int) $a['translation_of'];
					$map[ flxl_post_language( $id ) ]                     = $id;
					flxl_link_translations( $map );
				}
				$r['lang']         = flxl_post_language( $id );
				$r['url']          = get_permalink( $id );
				$r['translations'] = (object) flxl_get_translations( $id );
			}
			return $r;
		};
	}
	if ( isset( $tools['update_content'] ) ) {
		$run = $tools['update_content']['run'];
		$tools['update_content']['schema']['properties']['lang'] = array_merge( $lang, array( 'enum' => flxl_language_codes() ) );
		$tools['update_content']['description']                 .= ' lang ile içeriğin dili değiştirilir (Flux Lang).';
		$tools['update_content']['run'] = function ( $a ) use ( $run ) {
			$r = $run( $a );
			if ( is_array( $r ) && ! empty( $r['id'] ) ) {
				if ( ! empty( $a['lang'] ) && flxl_is_language( $a['lang'] ) ) {
					flxl_set_post_language( (int) $r['id'], $a['lang'] );
				}
				$r['lang'] = flxl_post_language( (int) $r['id'] );
			}
			return $r;
		};
	}
	return $tools;
}
