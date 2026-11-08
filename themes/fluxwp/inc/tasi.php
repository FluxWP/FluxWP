<?php
/**
 * Eski temadan taşıma ("Temayı taşı"): eski bir tema (filtreyle verilir) etkinken bu temaya geçilince
 * - tema ayarları (theme_mods: logo, menü konumları, özel CSS) kopyalanır,
 * - Site Editor'de kaydedilmiş şablonlar, şablon parçaları ve global stiller bu temaya KOPYALANIR.
 * Eski temanın kayıtlarına dokunulmaz: eski temaya geri dönülürse her şey eskisi gibi durur.
 * Bu temada aynı adlı kayıt zaten varsa üzerine yazılmaz. Tekrar çalıştırmak güvenlidir.
 *
 * @package fluxwp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Taşınabilecek eski temalar (stylesheet adları). */
function flx_tasi_sources() {
	return (array) apply_filters( 'flx_tasi_sources', array() );
}

/** Taşınacak veri olan eski tema; yoksa ''. */
function flx_tasi_pending_source() {
	$done = (array) get_option( 'flx_tasi_done', array() );
	foreach ( flx_tasi_sources() as $src ) {
		if ( ! empty( $done[ $src ] ) || get_stylesheet() === $src ) {
			continue;
		}
		if ( false !== get_option( 'theme_mods_' . $src ) ) {
			return $src;
		}
	}
	return '';
}

/**
 * @param string $src Eski temanın stylesheet adı.
 * @return array<string> Yapılanların listesi.
 */
function flx_tasi_run( $src ) {
	$src = sanitize_key( $src );
	$dst = get_stylesheet();
	$log = array();
	if ( '' === $src || $src === $dst ) {
		return $log;
	}

	// 1. Tema ayarları.
	$old = get_option( 'theme_mods_' . $src );
	if ( is_array( $old ) ) {
		$new = (array) get_option( 'theme_mods_' . $dst, array() );
		$merged = $new;
		foreach ( $old as $k => $v ) {
			if ( ! isset( $merged[ $k ] ) || '' === $merged[ $k ] || array() === $merged[ $k ] || false === $merged[ $k ] ) {
				$merged[ $k ] = $v;
			}
		}
		if ( $merged !== $new ) {
			update_option( 'theme_mods_' . $dst, $merged );
			/* translators: %d: count */
			$log[] = sprintf( __( 'Theme settings copied (%d fields).', 'fluxwp' ), count( $merged ) - count( $new ) );
		}
		// Özel CSS ayrı yazı olarak tutulur (post_name = tema adı).
		$css = wp_get_custom_css_post( $src );
		if ( $css && ! wp_get_custom_css_post( $dst ) && '' !== trim( $css->post_content ) ) {
			wp_update_custom_css_post( $css->post_content, array( 'stylesheet' => $dst ) );
			$log[] = __( 'Additional CSS copied.', 'fluxwp' );
		}
	}

	// 2. Site Editor kayıtları.
	$posts = get_posts(
		array(
			'post_type'      => array( 'wp_template', 'wp_template_part', 'wp_global_styles' ),
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
			'tax_query'      => array( array( 'taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => $src ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	$copied = 0;
	// Hedef temanın wp_theme terimi henüz yoksa tax_query yok sayılır (her şeyi bulur): o zaman hedefte kayıt da yoktur.
	$dst_term = term_exists( $dst, 'wp_theme' );
	foreach ( $posts as $p ) {
		$name = 'wp_global_styles' === $p->post_type ? 'wp-global-styles-' . urlencode( $dst ) : $p->post_name;
		$have = ! $dst_term ? array() : get_posts(
			array(
				'post_type'      => $p->post_type,
				'post_status'    => 'any',
				'name'           => $name,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'tax_query'      => array( array( 'taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => $dst ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		if ( $have ) {
			continue;
		}
		$content = str_replace( '"theme":"' . $src . '"', '"theme":"' . $dst . '"', $p->post_content );
		$id      = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => $p->post_type,
					'post_status'  => $p->post_status,
					'post_title'   => $p->post_title,
					'post_name'    => $name,
					'post_content' => $content,
					'post_excerpt' => $p->post_excerpt,
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			continue;
		}
		wp_set_post_terms( $id, array( $dst ), 'wp_theme' );
		$dst_term = true;
		$area = wp_get_post_terms( $p->ID, 'wp_template_part_area', array( 'fields' => 'names' ) );
		if ( $area && ! is_wp_error( $area ) ) {
			wp_set_post_terms( $id, $area, 'wp_template_part_area' );
		}
		foreach ( array( 'origin', 'is_wp_suggestion' ) as $mk ) {
			$mv = get_post_meta( $p->ID, $mk, true );
			if ( '' !== $mv ) {
				update_post_meta( $id, $mk, $mv );
			}
		}
		++$copied;
	}
	if ( $copied ) {
		/* translators: %d: count */
		$log[] = sprintf( __( 'Site Editor records copied (%d templates, parts or styles).', 'fluxwp' ), $copied );
	}

	$done         = (array) get_option( 'flx_tasi_done', array() );
	$done[ $src ] = time();
	update_option( 'flx_tasi_done', $done, false );
	if ( function_exists( 'flx_purge_page_cache' ) ) {
		flx_purge_page_cache();
	}
	return $log;
}

/** Tema değişince kendiliğinden (eski tema listedeyse). */
add_action(
	'after_switch_theme',
	function ( $old_name, $old_theme = null ) {
		$src = $old_theme instanceof WP_Theme ? $old_theme->get_stylesheet() : (string) get_option( 'theme_switched' );
		if ( in_array( $src, flx_tasi_sources(), true ) ) {
			$log = flx_tasi_run( $src );
			set_transient( 'flx_tasi_notice', $log ? $log : array( __( 'There was nothing new to migrate.', 'fluxwp' ) ), 600 );
		}
	},
	10,
	2
);

/** Elle: Tema Ayarları'ndaki düğme. */
add_action(
	'admin_post_flx_tasi',
	function () {
		if ( ! current_user_can( 'switch_themes' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'fluxwp' ) );
		}
		check_admin_referer( 'flx_tasi' );
		$src = sanitize_key( wp_unslash( $_POST['src'] ?? '' ) );
		if ( in_array( $src, flx_tasi_sources(), true ) ) {
			$done = (array) get_option( 'flx_tasi_done', array() );
			unset( $done[ $src ] );
			update_option( 'flx_tasi_done', $done, false );
			$log = flx_tasi_run( $src );
			set_transient( 'flx_tasi_notice', $log ? $log : array( __( 'There was nothing new to migrate.', 'fluxwp' ) ), 600 );
		}
		wp_safe_redirect( admin_url( 'themes.php?page=flx-ayarlar' ) );
		exit;
	}
);

add_action(
	'admin_notices',
	function () {
		$log = get_transient( 'flx_tasi_notice' );
		if ( ! $log || ! current_user_can( 'switch_themes' ) ) {
			return;
		}
		delete_transient( 'flx_tasi_notice' );
		echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Migrated from the previous theme.', 'fluxwp' ) . '</strong> ' . esc_html( implode( ' ', (array) $log ) ) . '</p></div>';
	}
);

/** Tema Ayarları › Genel'in altında: taşınacak eski tema varsa ya da tekrar taşımak için. */
function flx_tasi_box() {
	$sources = array_filter(
		flx_tasi_sources(),
		function ( $s ) {
			return get_stylesheet() !== $s && false !== get_option( 'theme_mods_' . $s );
		}
	);
	if ( ! $sources || ! current_user_can( 'switch_themes' ) ) {
		return;
	}
	$done = (array) get_option( 'flx_tasi_done', array() );
	echo '<h2 class="title">' . esc_html__( 'Migrate from the previous theme', 'fluxwp' ) . '</h2>';
	echo '<p>' . esc_html__( 'Copies the previous theme\'s settings (logo, menus, additional CSS) and the templates, parts and styles you saved in the Site Editor to this theme. The previous theme is left untouched; records with the same name in this theme are not overwritten.', 'fluxwp' ) . '</p>';
	foreach ( $sources as $src ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-right:8px">';
		echo '<input type="hidden" name="action" value="flx_tasi"><input type="hidden" name="src" value="' . esc_attr( $src ) . '">';
		wp_nonce_field( 'flx_tasi' );
		$label = empty( $done[ $src ] )
			/* translators: %s: theme */
			? sprintf( __( 'Migrate from %s', 'fluxwp' ), $src )
			/* translators: 1: theme, 2: date */
			: sprintf( __( 'Migrate from %1$s again (last: %2$s)', 'fluxwp' ), $src, wp_date( 'Y-m-d H:i', (int) $done[ $src ] ) );
		submit_button( $label, 'secondary', 'submit', false );
		echo '</form>';
	}
}
