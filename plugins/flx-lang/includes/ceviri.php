<?php
/**
 * Çeviri oluşturma ve kaydetme (panel, MCP, Flux AI ortak kodu) + Flux AI ile otomatik çeviri.
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

/**
 * Kopyalanmayacak meta anahtarları (dile özgü olanlar).
 */
function flxl_copy_meta_skip() {
	return (array) apply_filters(
		'flxl_copy_meta_skip',
		array( '_edit_lock', '_edit_last', '_flxl_group', '_wp_old_slug', '_wp_old_date', '_flxseo_title', '_flxseo_desc', '_flxseo_focus', '_flxseo_og_title', '_flxseo_og_desc', '_wp_trash_meta_status', '_wp_trash_meta_time', '_encloseme', '_pingme' )
	);
}

/**
 * Bir içeriğin verilen dilde çevirisini oluşturur ya da günceller.
 *
 * @param int    $source_id Kaynak içerik.
 * @param string $code      Hedef dil.
 * @param array  $fields    title, content (blok işaretlemesi), excerpt, slug, status, featured_image_id, meta (dizi).
 *                          Verilmeyen alan oluşturmada kaynaktan kopyalanır, güncellemede değişmez.
 * @return int|WP_Error Çevirinin ID'si.
 */
function flxl_save_translation( $source_id, $code, array $fields = array() ) {
	$src = get_post( (int) $source_id );
	if ( ! $src || ! flxl_is_translatable_type( $src->post_type ) ) {
		return new WP_Error( 'flxl_source', __( 'Source content not found or this type is not translated.', 'flx-lang' ) );
	}
	if ( ! flxl_is_language( $code ) ) {
		return new WP_Error( 'flxl_lang', __( 'Language is not active.', 'flx-lang' ) );
	}
	if ( flxl_post_language( $src ) === $code ) {
		return new WP_Error( 'flxl_same', __( 'The content is already in this language.', 'flx-lang' ) );
	}
	$existing = flxl_get_translation( $src->ID, $code );
	$data     = array();
	foreach ( array( 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt', 'slug' => 'post_name', 'status' => 'post_status' ) as $k => $col ) {
		if ( isset( $fields[ $k ] ) ) {
			$data[ $col ] = $fields[ $k ];
		}
	}
	if ( isset( $data['post_title'] ) ) {
		$data['post_title'] = sanitize_text_field( $data['post_title'] );
	}
	if ( isset( $data['post_name'] ) ) {
		$data['post_name'] = sanitize_title( $data['post_name'] );
	}
	if ( isset( $data['post_excerpt'] ) ) {
		$data['post_excerpt'] = sanitize_textarea_field( $data['post_excerpt'] );
	}
	if ( isset( $data['post_status'] ) && ! in_array( $data['post_status'], array( 'draft', 'publish', 'pending', 'private', 'future' ), true ) ) {
		unset( $data['post_status'] );
	}

	if ( $existing ) {
		if ( ! current_user_can( 'edit_post', $existing ) ) {
			return new WP_Error( 'flxl_cap', __( 'You are not allowed to do this.', 'flx-lang' ) );
		}
		if ( $data ) {
			$data['ID'] = $existing;
			$r          = wp_update_post( wp_slash( $data ), true );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		$id = $existing;
	} else {
		$pto = get_post_type_object( $src->post_type );
		if ( ! $pto || ! current_user_can( $pto->cap->create_posts ) ) {
			return new WP_Error( 'flxl_cap', __( 'You are not allowed to do this.', 'flx-lang' ) );
		}
		$parent = 0;
		if ( $src->post_parent ) {
			$parent = flxl_get_translation( $src->post_parent, $code );
		}
		$data += array(
			'post_type'      => $src->post_type,
			'post_title'     => $src->post_title,
			'post_content'   => $src->post_content,
			'post_excerpt'   => $src->post_excerpt,
			'post_status'    => 'draft',
			'post_name'      => $src->post_name ? $src->post_name . '-' . $code : '',
			'post_parent'    => $parent,
			'menu_order'     => $src->menu_order,
			'comment_status' => $src->comment_status,
			'ping_status'    => $src->ping_status,
			'post_password'  => $src->post_password,
			'post_author'    => get_current_user_id() ? get_current_user_id() : $src->post_author,
		);
		if ( 'future' === $data['post_status'] && empty( $fields['date'] ) ) {
			$data['post_date'] = $src->post_date;
		}
		$id = wp_insert_post( wp_slash( $data ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		// Terimler (kategori, etiket, proje durumu…) ortak; dil terimi hariç.
		foreach ( get_object_taxonomies( $src->post_type ) as $tax ) {
			if ( FLXL_TAX === $tax ) {
				continue;
			}
			$terms = wp_get_object_terms( $src->ID, $tax, array( 'fields' => 'ids' ) );
			if ( $terms && ! is_wp_error( $terms ) ) {
				wp_set_object_terms( $id, $terms, $tax );
			}
		}
		$skip = flxl_copy_meta_skip();
		foreach ( get_post_meta( $src->ID ) as $k => $vals ) {
			if ( in_array( $k, $skip, true ) || 0 === strpos( $k, '_flxl_' ) ) {
				continue;
			}
			foreach ( $vals as $v ) {
				add_post_meta( $id, $k, maybe_unserialize( $v ) );
			}
		}
		$src_lang = flxl_post_language( $src );
		flxl_set_post_language( $id, $code );
		$map            = flxl_get_translations( $src->ID );
		$map[ $src_lang ] = $src->ID;
		$map[ $code ]   = $id;
		flxl_link_translations( $map );
	}

	if ( isset( $fields['featured_image_id'] ) ) {
		$fid = absint( $fields['featured_image_id'] );
		if ( $fid ) {
			set_post_thumbnail( $id, $fid );
		} else {
			delete_post_thumbnail( $id );
		}
	}
	foreach ( (array) ( $fields['meta'] ?? array() ) as $k => $v ) {
		if ( is_string( $k ) && '' !== $k && ! is_protected_meta( $k, 'post' ) ) {
			update_post_meta( $id, $k, $v );
		}
	}
	do_action( 'flxl_translation_saved', $id, $src->ID, $code, $fields );
	return (int) $id;
}

/**
 * Bir içeriğin özeti (araçlar ve REST için).
 */
function flxl_post_brief( $id ) {
	$p = get_post( $id );
	if ( ! $p ) {
		return null;
	}
	return array(
		'id'       => $p->ID,
		'lang'     => flxl_post_language( $p ),
		'title'    => get_the_title( $p ),
		'status'   => $p->post_status,
		'slug'     => $p->post_name,
		'url'      => flxl_strict_links(
			function () use ( $p ) {
				return get_permalink( $p );
			}
		),
		'edit_url' => get_edit_post_link( $p->ID, 'raw' ),
		'modified' => get_post_modified_time( 'c', true, $p ),
	);
}

/**
 * Bir içeriğin tüm dil sürümleri: dil => özet | null (yok).
 */
function flxl_translations_brief( $id ) {
	$tr  = flxl_get_translations( $id );
	$out = array();
	foreach ( flxl_language_codes() as $code ) {
		$out[ $code ] = isset( $tr[ $code ] ) ? flxl_post_brief( $tr[ $code ] ) : null;
	}
	return $out;
}

/* ---------------------------------------------------------------------------
 * Flux AI ile çeviri (parça parça; her istek kısa, Hostinger 60 sn sınırına takılmaz)
 * ------------------------------------------------------------------------- */

function flxl_ai_available() {
	return function_exists( 'flxai_chat' ) && function_exists( 'flxai_ready' ) && flxai_ready( 'flx-lang' )
		&& ( ! function_exists( 'flxai_source_enabled' ) || flxai_source_enabled( 'flx-lang' ) );
}

add_filter(
	'flxai_systems',
	function ( $s ) {
		$s['flx-lang'] = array(
			'label'  => __( 'Flux Language (translation)', 'flx-lang' ),
			'plugin' => plugin_basename( FLXL_FILE ),
			'page'   => 'options-general.php?page=flx-lang',
		);
		return $s;
	}
);
add_filter(
	'flxai_sources',
	function ( $s ) {
		$s['flx-lang'] = __( 'Flux Language', 'flx-lang' );
		return $s;
	}
);

/**
 * Blok işaretlemesini ~5000 karakterlik parçalara böler (üst düzey blok sınırından).
 *
 * @return string[]
 */
function flxl_split_content( $content, $max = 5000 ) {
	$content = (string) $content;
	if ( strlen( $content ) <= $max ) {
		return array( $content );
	}
	$blocks = has_blocks( $content ) ? parse_blocks( $content ) : array();
	$parts  = array();
	$buf    = '';
	if ( $blocks ) {
		foreach ( $blocks as $b ) {
			$s = serialize_block( $b );
			if ( '' !== $buf && strlen( $buf ) + strlen( $s ) > $max ) {
				$parts[] = $buf;
				$buf     = '';
			}
			$buf .= $s;
		}
	} else {
		foreach ( preg_split( '/(\n\s*\n)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE ) as $s ) {
			if ( '' !== $buf && strlen( $buf ) + strlen( $s ) > $max ) {
				$parts[] = $buf;
				$buf     = '';
			}
			$buf .= $s;
		}
	}
	if ( '' !== $buf ) {
		$parts[] = $buf;
	}
	return $parts;
}

function flxl_ai_system( $from, $to ) {
	return sprintf(
		'You are a professional translator for this website. Translate from %1$s to %2$s. '
		. 'Rules: Translate only human-readable text (text nodes, alt, title, aria-label, placeholder attributes and "content"/"label"/"text" values inside block comment JSON). '
		. 'Keep every WordPress block comment (<!-- wp:... -->), HTML tag, attribute name, class, id, URL, shortcode and number exactly as they are, in the same order. '
		. 'Do not add, remove or reorder blocks. Keep proper names (organisation, people and place names) and the IBAN/phone numbers unchanged. '
		. 'Use a warm, clear, natural tone. Output only the translated markup, no explanations, no code fences.',
		flxl_language_name( $from ),
		flxl_language_name( $to )
	);
}

/**
 * Tek metni/işaretlemeyi çevirir.
 *
 * @return string|WP_Error
 */
function flxl_ai_translate_text( $text, $from, $to, $purpose = 'translate' ) {
	if ( '' === trim( (string) $text ) ) {
		return (string) $text;
	}
	if ( ! flxl_ai_available() ) {
		return new WP_Error( 'flxl_ai', __( 'Flux AI & MCP is not installed or not ready.', 'flx-lang' ) );
	}
	$r = flxai_chat(
		array(
			array( 'role' => 'system', 'content' => array( array( 'type' => 'text', 'text' => flxl_ai_system( $from, $to ), 'cache' => true ) ) ),
			array( 'role' => 'user', 'content' => (string) $text ),
		),
		array(
			'source'     => 'flx-lang',
			'purpose'    => $purpose,
			'max_tokens' => 8000,
			'timeout'    => 50,
		)
	);
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$out = trim( (string) ( $r['content'] ?? '' ) );
	$out = preg_replace( '/^```[a-z]*\s*|\s*```$/i', '', $out );
	if ( '' === $out ) {
		return new WP_Error( 'flxl_ai_empty', __( 'AI returned an empty answer.', 'flx-lang' ) );
	}
	if ( 'length' === ( $r['_finish'] ?? '' ) ) {
		return new WP_Error( 'flxl_ai_length', __( 'The AI answer was cut off.', 'flx-lang' ) );
	}
	return $out;
}

/**
 * Çeviri işi başlatır: çeviri taslağı (kaynağın kopyası) + parça listesi. İş transient'te.
 *
 * @return array|WP_Error {job, post_id, steps}
 */
function flxl_ai_job_start( $source_id, $code ) {
	$src = get_post( (int) $source_id );
	if ( ! $src ) {
		return new WP_Error( 'flxl_source', __( 'Source content not found or this type is not translated.', 'flx-lang' ) );
	}
	$id = flxl_get_translation( $src->ID, $code );
	if ( ! $id ) {
		$id = flxl_save_translation( $src->ID, $code );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
	} elseif ( ! current_user_can( 'edit_post', $id ) ) {
		return new WP_Error( 'flxl_cap', __( 'You are not allowed to do this.', 'flx-lang' ) );
	}
	$job = array(
		'src'   => $src->ID,
		'post'  => (int) $id,
		'from'  => flxl_post_language( $src ),
		'to'    => $code,
		'head'  => wp_json_encode(
			array(
				'title'   => $src->post_title,
				'excerpt' => $src->post_excerpt,
				'seo_title' => (string) get_post_meta( $src->ID, '_flxseo_title', true ),
				'seo_desc'  => (string) get_post_meta( $src->ID, '_flxseo_desc', true ),
			),
			JSON_UNESCAPED_UNICODE
		),
		'parts' => flxl_split_content( $src->post_content ),
		'done'  => array(),
		'user'  => get_current_user_id(),
		'errors' => array(),
	);
	$key = wp_generate_password( 12, false );
	set_transient( 'flxl_job_' . $key, $job, HOUR_IN_SECONDS );
	return array( 'job' => $key, 'post_id' => (int) $id, 'steps' => count( $job['parts'] ) + 1 );
}

/**
 * İşin bir adımı. Adım 0 başlık/özet/SEO; sonra gövde parçaları; son adımda gövde kaydedilir.
 *
 * @return array|WP_Error {step, steps, finished, edit_url}
 */
function flxl_ai_job_step( $key, $step ) {
	$job = get_transient( 'flxl_job_' . $key );
	if ( ! is_array( $job ) || (int) $job['user'] !== get_current_user_id() ) {
		return new WP_Error( 'flxl_job', __( 'Translation job not found or expired.', 'flx-lang' ) );
	}
	$steps = count( $job['parts'] ) + 1;
	$step  = (int) $step;
	if ( 0 === $step ) {
		$prompt = "Translate the values of this JSON object. Return only valid JSON with the same keys. Empty values stay empty.\n" . $job['head'];
		$t      = flxl_ai_translate_text( $prompt, $job['from'], $job['to'], 'translate_meta' );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$j = json_decode( preg_replace( '/^[^{]*|[^}]*$/', '', $t ), true );
		if ( is_array( $j ) ) {
			$fields = array();
			if ( ! empty( $j['title'] ) ) {
				$fields['title'] = $j['title'];
				$fields['slug']  = sanitize_title( remove_accents( $j['title'] ) );
			}
			if ( isset( $j['excerpt'] ) ) {
				$fields['excerpt'] = $j['excerpt'];
			}
			flxl_save_translation( $job['src'], $job['to'], $fields );
			foreach ( array( 'seo_title' => '_flxseo_title', 'seo_desc' => '_flxseo_desc' ) as $k => $mk ) {
				if ( ! empty( $j[ $k ] ) ) {
					update_post_meta( $job['post'], $mk, sanitize_text_field( $j[ $k ] ) );
				}
			}
		} else {
			$job['errors'][] = 'meta';
		}
	} else {
		$i   = $step - 1;
		$src = (string) ( $job['parts'][ $i ] ?? '' );
		$t   = flxl_ai_translate_text( $src, $job['from'], $job['to'] );
		if ( is_wp_error( $t ) ) {
			if ( function_exists( 'flxai_is_account_error' ) && flxai_is_account_error( $t ) ) {
				return $t;
			}
			$t               = $src; // Bu parça Türkçe kalır, iş sürer.
			$job['errors'][] = $step;
		} elseif ( substr_count( $t, '<!-- wp:' ) !== substr_count( $src, '<!-- wp:' ) ) {
			$t               = $src; // Blok yapısı bozuldu: güvenli taraf.
			$job['errors'][] = $step;
		}
		$job['done'][ $i ] = $t;
	}
	$finished = $step >= $steps - 1;
	if ( $finished ) {
		ksort( $job['done'] );
		$body = '';
		foreach ( $job['parts'] as $i => $p ) {
			$body .= $job['done'][ $i ] ?? $p;
		}
		flxl_save_translation( $job['src'], $job['to'], array( 'content' => $body ) );
		delete_transient( 'flxl_job_' . $key );
	} else {
		set_transient( 'flxl_job_' . $key, $job, HOUR_IN_SECONDS );
	}
	return array(
		'step'     => $step,
		'steps'    => $steps,
		'finished' => $finished,
		'errors'   => $job['errors'],
		'edit_url' => get_edit_post_link( $job['post'], 'raw' ),
	);
}
