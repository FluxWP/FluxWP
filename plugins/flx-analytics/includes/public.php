<?php
/**
 * Kısa kodlar ([flx_analiz_populer], [flx_analiz_sayac]), yönetim çubuğu sayısı, yazı listesi sütunu,
 * Flux Admin kutucuğu ve Flux AI sohbet önerileri.
 *
 * @package flx-analytics
 */

defined( 'ABSPATH' ) || exit;

/**
 * [flx_analiz_populer sayi="5" gun="30" tur="post"] — en çok okunanlar listesi.
 * İngilizce adlar da çalışır: number, days, type.
 */
add_shortcode(
	'flx_analiz_populer',
	function ( $atts ) {
		$a     = shortcode_atts( array( 'sayi' => '', 'number' => 5, 'gun' => '', 'days' => 30, 'tur' => '', 'type' => 'post', 'baslik' => '', 'title' => '' ), $atts );
		$num   = (int) ( '' !== $a['sayi'] ? $a['sayi'] : $a['number'] );
		$days  = (int) ( '' !== $a['gun'] ? $a['gun'] : $a['days'] );
		$types = explode( ',', '' !== $a['tur'] ? $a['tur'] : $a['type'] );
		$key   = 'flxan_pop_' . md5( wp_json_encode( array( $num, $days, $types ) ) );
		$rows  = get_transient( $key );
		if ( ! is_array( $rows ) ) {
			$rows = FLXAN_Stats::popular_posts( $days, $num, $types );
			set_transient( $key, $rows, 15 * MINUTE_IN_SECONDS );
		}
		if ( ! $rows ) {
			return '';
		}
		$title = '' !== $a['baslik'] ? $a['baslik'] : $a['title'];
		$out   = '<div class="flxan-popular">' . ( '' !== $title ? '<h3 class="flxan-popular__title">' . esc_html( $title ) . '</h3>' : '' ) . '<ol class="flxan-popular__list">';
		foreach ( $rows as $r ) {
			$out .= '<li><a href="' . esc_url( get_permalink( (int) $r['post_id'] ) ) . '">' . esc_html( get_the_title( (int) $r['post_id'] ) ) . '</a></li>';
		}
		return $out . '</ol></div>';
	}
);

/** [flx_analiz_sayac gun="0" id=""] — bu sayfanın (ya da verilen yazının) görüntülenme sayısı. */
add_shortcode(
	'flx_analiz_sayac',
	function ( $atts ) {
		$a    = shortcode_atts( array( 'gun' => '', 'days' => 0, 'id' => 0 ), $atts );
		$days = (int) ( '' !== $a['gun'] ? $a['gun'] : $a['days'] );
		$id   = (int) $a['id'] ? (int) $a['id'] : (int) get_queried_object_id();
		if ( ! $id ) {
			return '';
		}
		$key = 'flxan_cnt_' . $id . '_' . $days;
		$n   = get_transient( $key );
		if ( false === $n ) {
			$s = $days > 0 ? wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS ) : '1970-01-01';
			$v = FLXAN_Stats::post_views( array( $id ), $s, wp_date( 'Y-m-d' ) );
			$n = isset( $v[ $id ] ) ? $v[ $id ]['pageviews'] : 0;
			set_transient( $key, $n, 10 * MINUTE_IN_SECONDS );
		}
		return '<span class="flxan-counter">' . esc_html( number_format_i18n( (int) $n ) ) . '</span>';
	}
);

/** Ön yüzde yönetim çubuğu: bu sayfanın son 30 gün görüntülenmesi. */
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( is_admin() || ! flxan_settings( 'adminbar' ) || ! flxan_can_view() || ! is_singular() ) {
			return;
		}
		$id = (int) get_queried_object_id();
		$v  = FLXAN_Stats::post_views( array( $id ), wp_date( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ), wp_date( 'Y-m-d' ) );
		$n  = isset( $v[ $id ] ) ? $v[ $id ]['pageviews'] : 0;
		$bar->add_node(
			array(
				'id'    => 'flxan-views',
				/* translators: %s: görüntülenme sayısı */
				'title' => '<span class="ab-icon dashicons dashicons-chart-bar" style="top:2px"></span>' . esc_html( sprintf( __( '%s görüntülenme', 'flx-analytics' ), number_format_i18n( $n ) ) ),
				'href'  => admin_url( 'admin.php?page=flx-analytics#page=' . rawurlencode( (string) wp_parse_url( get_permalink( $id ), PHP_URL_PATH ) ) ),
				'meta'  => array( 'title' => __( 'Son 30 gün (Site Analitik)', 'flx-analytics' ) ),
			)
		);
	},
	90
);

/** Yazı/sayfa listesinde "Görüntülenme" sütunu (son 30 gün). */
add_action(
	'admin_init',
	function () {
		if ( ! flxan_settings( 'posts_column' ) || ! flxan_can_view() ) {
			return;
		}
		foreach ( array( 'post', 'page' ) as $type ) {
			add_filter(
				"manage_{$type}_posts_columns",
				function ( $cols ) {
					$cols['flxan_views'] = '<span title="' . esc_attr__( 'Son 30 gün görüntülenme', 'flx-analytics' ) . '">' . esc_html__( 'Görüntülenme', 'flx-analytics' ) . '</span>';
					return $cols;
				}
			);
			add_action(
				"manage_{$type}_posts_custom_column",
				function ( $col, $post_id ) {
					static $cache = null;
					if ( 'flxan_views' !== $col ) {
						return;
					}
					if ( null === $cache ) {
						global $wp_query;
						$ids   = $wp_query && $wp_query->posts ? wp_list_pluck( $wp_query->posts, 'ID' ) : array( $post_id );
						$cache = FLXAN_Stats::post_views( $ids, wp_date( 'Y-m-d', time() - 29 * DAY_IN_SECONDS ), wp_date( 'Y-m-d' ) );
					}
					$n = isset( $cache[ $post_id ] ) ? $cache[ $post_id ]['pageviews'] : 0;
					echo '<a href="' . esc_url( admin_url( 'admin.php?page=flx-analytics#page=' . rawurlencode( (string) wp_parse_url( get_permalink( $post_id ), PHP_URL_PATH ) ) ) ) . '">' . esc_html( number_format_i18n( $n ) ) . '</a>';
				},
				10,
				2
			);
		}
		add_action(
			'admin_head-edit.php',
			function () {
				echo '<style>.column-flxan_views{width:8em}</style>';
			}
		);
	}
);

/** Flux Admin özet kutucuğu: bugünkü ziyaretçi. */
add_filter(
	'flxa_dashboard_stats',
	function ( $stats ) {
		if ( ! flxan_can_view() ) {
			return $stats;
		}
		$today = wp_date( 'Y-m-d' );
		$t     = FLXAN_Stats::totals( $today, $today );
		$stats = (array) $stats;
		$stats['flxan_visitors'] = array(
			'label' => __( 'Bugün ziyaretçi', 'flx-analytics' ),
			'value' => number_format_i18n( $t['visitors'] ),
			/* translators: %s: görüntülenme sayısı */
			'meta'  => sprintf( __( '%s görüntülenme', 'flx-analytics' ), number_format_i18n( $t['pageviews'] ) ),
			'url'   => admin_url( 'admin.php?page=flx-analytics' ),
			'tone'  => 'ok',
		);
		return $stats;
	}
);

/** Flux AI sohbeti: istatistik ekranında hazır sorular. */
add_filter(
	'flxai_chat_suggestions',
	function ( $s, $admin = true ) {
		if ( ! $admin || ! flxan_can_view() || ! function_exists( 'get_current_screen' ) ) {
			return $s;
		}
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'flx-analytics' ) ) {
			return $s;
		}
		return array_merge(
			array(
				__( 'Bu haftanın trafiğini geçen haftayla karşılaştır', 'flx-analytics' ),
				__( 'Ziyaretçiler en çok nereden geliyor?', 'flx-analytics' ),
				__( 'Bağış sayfası kaç kişiye ulaştı, kaç bağışa döndü?', 'flx-analytics' ),
			),
			(array) $s
		);
	},
	10,
	2
);
