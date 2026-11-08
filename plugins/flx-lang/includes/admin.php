<?php
/**
 * Yönetim: içerik listelerinde Diller sütunu ve dil süzgeci, düzenleme ekranında Dil kutusu,
 * "Çeviri oluştur" ve "AI ile çevir".
 *
 * @package flx-lang
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_init',
	function () {
		foreach ( flxl_post_types() as $type ) {
			add_filter( 'manage_' . $type . '_posts_columns', 'flxl_admin_columns' );
			add_action( 'manage_' . $type . '_posts_custom_column', 'flxl_admin_column', 10, 2 );
		}
	}
);

function flxl_admin_columns( $cols ) {
	if ( count( flxl_languages() ) < 2 ) {
		return $cols;
	}
	$out = array();
	foreach ( $cols as $k => $v ) {
		$out[ $k ] = $v;
		if ( 'title' === $k ) {
			$out['flxl_lang'] = '<span class="dashicons dashicons-translation" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Languages', 'flx-lang' ) . '</span>';
		}
	}
	return $out;
}

/**
 * Bir içeriğin dil rozetleri: kendi dili koyu, çevirisi olan bağlantı, olmayana "+".
 */
function flxl_admin_badges( $post_id ) {
	if ( flxl_is_shared( $post_id ) ) {
		return '<span class="flxl-badge is-shared" title="' . esc_attr__( 'Shared page: the same in every language, interface in the visitor\'s language.', 'flx-lang' ) . '">' . esc_html__( 'Shared', 'flx-lang' ) . '</span>';
	}
	$mine = flxl_post_language( $post_id );
	$tr   = flxl_get_translations( $post_id );
	$out  = '';
	foreach ( flxl_languages() as $code => $l ) {
		$label = strtoupper( $code );
		if ( $code === $mine ) {
			$out .= '<span class="flxl-badge is-self" title="' . esc_attr( $l['name'] ) . '">' . esc_html( $label ) . '</span>';
		} elseif ( isset( $tr[ $code ] ) ) {
			$st    = get_post_status( $tr[ $code ] );
			$out  .= '<a class="flxl-badge is-done' . ( 'publish' === $st ? '' : ' is-draft' ) . '" href="' . esc_url( (string) get_edit_post_link( $tr[ $code ] ) ) . '" title="'
				/* translators: 1: language name, 2: post status */
				. esc_attr( sprintf( __( '%1$s translation (%2$s)', 'flx-lang' ), $l['name'], get_post_status_object( $st ) ? get_post_status_object( $st )->label : $st ) ) . '">' . esc_html( $label ) . '</a>';
		} elseif ( current_user_can( 'edit_post', $post_id ) ) {
			$out .= '<a class="flxl-badge is-missing" href="' . esc_url( flxl_translate_link( $post_id, $code ) ) . '" data-flxl-src="' . (int) $post_id . '" data-flxl-lang="' . esc_attr( $code ) . '" title="'
				/* translators: %s: language name */
				. esc_attr( sprintf( __( 'Create %s translation', 'flx-lang' ), $l['name'] ) ) . '">+' . esc_html( $label ) . '</a>';
		}
	}
	return $out;
}

function flxl_admin_column( $col, $post_id ) {
	if ( 'flxl_lang' === $col ) {
		echo flxl_admin_badges( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- yukarıda kaçışlı.
	}
}

function flxl_translate_link( $post_id, $code ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=flxl_translate&post=' . (int) $post_id . '&lang=' . rawurlencode( $code ) ), 'flxl_translate_' . (int) $post_id );
}

/**
 * Elle çeviri: kopya taslak → düzenleyici.
 */
add_action(
	'admin_post_flxl_translate',
	function () {
		$id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$code = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : '';
		check_admin_referer( 'flxl_translate_' . $id );
		if ( ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'flx-lang' ) );
		}
		$new = flxl_save_translation( $id, $code );
		if ( is_wp_error( $new ) ) {
			wp_die( esc_html( $new->get_error_message() ) );
		}
		wp_safe_redirect( admin_url( 'post.php?post=' . (int) $new . '&action=edit' ) );
		exit;
	}
);

/**
 * Liste süzgeci: Tüm diller / Türkçe / English.
 */
add_action(
	'restrict_manage_posts',
	function ( $type ) {
		if ( ! flxl_is_translatable_type( $type ) || count( flxl_languages() ) < 2 ) {
			return;
		}
		$cur = isset( $_GET['flxl_filter'] ) ? sanitize_key( wp_unslash( $_GET['flxl_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<label class="screen-reader-text" for="flxl-filter">' . esc_html__( 'Filter by language', 'flx-lang' ) . '</label><select name="flxl_filter" id="flxl-filter"><option value="">' . esc_html__( 'All languages', 'flx-lang' ) . '</option>';
		foreach ( flxl_languages() as $code => $l ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $code ), selected( $cur, $code, false ), esc_html( $l['name'] ) );
		}
		echo '</select>';
	}
);
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || empty( $_GET['flxl_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$l = sanitize_key( wp_unslash( $_GET['flxl_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( flxl_is_language( $l ) ) {
			$q->set( 'flxl_lang', $l );
		}
	},
	5
);

/* ---------------------------------------------------------------------------
 * Düzenleme ekranı: Dil kutusu
 * ------------------------------------------------------------------------- */

add_action(
	'add_meta_boxes',
	function ( $type, $post ) {
		if ( ! flxl_is_translatable_type( $type ) || count( flxl_languages() ) < 2 ) {
			return;
		}
		add_meta_box( 'flxl-box', __( 'Language', 'flx-lang' ), 'flxl_meta_box', $type, 'side', 'high' );
	},
	10,
	2
);

function flxl_meta_box( $post ) {
	wp_nonce_field( 'flxl_box', 'flxl_box_nonce' );
	if ( flxl_is_shared( $post->ID ) ) {
		echo '<p>' . esc_html__( 'Shared page: the same in every language, interface in the visitor\'s language.', 'flx-lang' ) . '</p>';
		return;
	}
	$is_new = 'auto-draft' === $post->post_status;
	$mine   = flxl_post_language( $post );
	if ( $is_new && isset( $_GET['flxl_lang'] ) && flxl_is_language( sanitize_key( wp_unslash( $_GET['flxl_lang'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$mine = sanitize_key( wp_unslash( $_GET['flxl_lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	echo '<p><label for="flxl-lang"><strong>' . esc_html__( 'Language of this content', 'flx-lang' ) . '</strong></label><br><select id="flxl-lang" name="flxl_lang" style="width:100%">';
	foreach ( flxl_languages() as $code => $l ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $code ), selected( $mine, $code, false ), esc_html( trim( $l['flag'] . ' ' . $l['name'] ) ) );
	}
	echo '</select></p>';
	if ( $is_new ) {
		echo '<p class="description">' . esc_html__( 'After saving you can create translations in other languages.', 'flx-lang' ) . '</p>';
		return;
	}
	$tr = flxl_get_translations( $post->ID );
	echo '<ul class="flxl-box-list">';
	foreach ( flxl_languages() as $code => $l ) {
		if ( $code === $mine ) {
			continue;
		}
		echo '<li><span class="flxl-box-name">' . esc_html( trim( $l['flag'] . ' ' . $l['name'] ) ) . '</span> ';
		if ( isset( $tr[ $code ] ) ) {
			$st = get_post_status( $tr[ $code ] );
			echo '<a href="' . esc_url( (string) get_edit_post_link( $tr[ $code ] ) ) . '">' . esc_html( get_the_title( $tr[ $code ] ) ? get_the_title( $tr[ $code ] ) : '#' . $tr[ $code ] ) . '</a>';
			echo ' <span class="flxl-st">(' . esc_html( get_post_status_object( $st ) ? get_post_status_object( $st )->label : $st ) . ')</span>';
			if ( flxl_ai_available() && current_user_can( 'edit_post', $tr[ $code ] ) ) {
				echo '<br><button type="button" class="button-link flxl-ai" data-flxl-src="' . (int) $post->ID . '" data-flxl-lang="' . esc_attr( $code ) . '" data-flxl-confirm="1">' . esc_html__( 'Re-translate with AI', 'flx-lang' ) . '</button>';
			}
		} else {
			echo '<a class="button button-small" href="' . esc_url( flxl_translate_link( $post->ID, $code ) ) . '">' . esc_html__( 'Copy and translate', 'flx-lang' ) . '</a>';
			if ( flxl_ai_available() ) {
				echo ' <button type="button" class="button button-small button-primary flxl-ai" data-flxl-src="' . (int) $post->ID . '" data-flxl-lang="' . esc_attr( $code ) . '">' . esc_html__( 'Translate with AI', 'flx-lang' ) . '</button>';
			}
		}
		echo '</li>';
	}
	echo '</ul>';
	if ( ! flxl_ai_available() ) {
		echo '<p class="description">' . esc_html__( 'With Flux AI installed, translation is one click; AI agents connected over MCP can also translate with the flxl_save_translation tool.', 'flx-lang' ) . '</p>';
	}
}

add_action(
	'save_post',
	function ( $post_id, $post ) {
		if ( ! isset( $_POST['flxl_box_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['flxl_box_nonce'] ), 'flxl_box' ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! flxl_is_translatable_type( $post->post_type ) ) {
			return;
		}
		$l = isset( $_POST['flxl_lang'] ) ? sanitize_key( wp_unslash( $_POST['flxl_lang'] ) ) : '';
		if ( ! flxl_is_language( $l ) || flxl_is_shared( $post_id ) ) {
			return;
		}
		$has_term = is_array( get_the_terms( $post_id, FLXL_TAX ) );
		if ( $l === flxl_post_language( $post_id ) && ( $has_term || $l === flxl_default_language() ) ) {
			return;
		}
		// Gruptaki başka içerik aynı dildeyse gruptan ayrılır.
		$tr = flxl_get_translations( $post_id );
		if ( isset( $tr[ $l ] ) && (int) $tr[ $l ] !== (int) $post_id ) {
			flxl_unlink_translation( $post_id );
		}
		flxl_set_post_language( $post_id, $l );
	},
	10,
	2
);

/* ---------------------------------------------------------------------------
 * AI ile çeviri (admin-ajax, adım adım)
 * ------------------------------------------------------------------------- */

add_action(
	'wp_ajax_flxl_ai',
	function () {
		check_ajax_referer( 'flxl_ai', 'nonce' );
		$job  = isset( $_POST['job'] ) ? sanitize_key( wp_unslash( $_POST['job'] ) ) : '';
		if ( '' === $job ) {
			$src  = isset( $_POST['src'] ) ? absint( $_POST['src'] ) : 0;
			$code = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';
			if ( ! current_user_can( 'edit_post', $src ) ) {
				wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'flx-lang' ) ), 403 );
			}
			$r = flxl_ai_job_start( $src, $code );
		} else {
			$r = flxl_ai_job_step( $job, isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 0 );
		}
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( array( 'message' => $r->get_error_message() ) );
		}
		wp_send_json_success( $r );
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, array( 'edit', 'post' ), true ) || ! flxl_is_translatable_type( $screen->post_type ) ) {
			return;
		}
		wp_enqueue_style( 'flxl-admin', FLXL_URL . 'assets/css/admin.css', array(), FLXL_VERSION );
		if ( flxl_ai_available() ) {
			wp_enqueue_script( 'flxl-admin', FLXL_URL . 'assets/js/admin.js', array(), FLXL_VERSION, true );
			wp_localize_script(
				'flxl-admin',
				'flxlAdmin',
				array(
					'ajax'  => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'flxl_ai' ),
					'ai'    => true,
					't'     => array(
						'start'   => __( 'Preparing translation…', 'flx-lang' ),
						/* translators: 1: step, 2: total steps */
						'step'    => __( 'AI is translating: %1$d / %2$d', 'flx-lang' ),
						'done'    => __( 'Translation ready, opening the editor…', 'flx-lang' ),
						'partial' => __( 'Some sections could not be translated and stayed in Turkish. Check them in the editor.', 'flx-lang' ),
						'error'   => __( 'Translation stopped:', 'flx-lang' ),
						'confirm' => __( 'Replace the title, excerpt and body of the existing translation with an AI translation?', 'flx-lang' ),
						'ask'     => __( 'Translate with AI? If you choose No, a draft copy is created for you to translate yourself.', 'flx-lang' ),
					),
				)
			);
		}
	}
);
