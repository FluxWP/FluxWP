<?php
/**
 * Uygulamanın ana sayfası (WordPress Başlangıç karşılığı): GET flxapp/v1/dashboard.
 *
 *   { cards: [ { id, title, value, sub?, icon, tone?, open?, url? } ],
 *     lists: [ { id, title, icon, open?, url?, items: [ { title, sub?, meta?, url? } ] } ] }
 *
 * `value` metindir (biçimlendirilmiş sayı/tutar). `open`: menü slug'ı ya da `mod:<modül>` (uygulama o ekranı açar),
 * `url`: panel sayfası (uygulama içinde web görünümünde açılır). `tone`: accent | warn.
 * WordPress çekirdeği ve WooCommerce kartları burada; paketler `flxapp_dashboard` filtresiyle kendi kart/listesini ekler:
 *
 *   add_filter( 'flxapp_dashboard', function ( $d, $user ) {
 *       if ( user_can( $user, 'flxb_manage' ) ) {
 *           $d['cards'][] = array( 'id' => 'flxb-today', 'title' => __( 'Bugünkü bağış', 'flx-donate' ), 'value' => '1.250 ₺', 'icon' => 'heart', 'open' => 'mod:flxb-review' );
 *       }
 *       return $d;
 *   }, 10, 2 );
 *
 * Kart ve liste sırası `order` (varsayılan 50) ile belirlenir. Her kart kullanıcının yetkisine göre eklenir.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'rest_api_init',
	function () {
		register_rest_route( 'flxapp/v1', '/dashboard', array( 'methods' => 'GET', 'callback' => 'flxapp_rest_dashboard', 'permission_callback' => 'flxapp_logged_in' ) );
	}
);

function flxapp_rest_dashboard() {
	$u = wp_get_current_user();
	$d = array(
		'cards' => array(),
		'lists' => array(),
	);
	$d = flxapp_dash_wp( $d, $u );
	$d = flxapp_dash_wc( $d, $u );
	$d = (array) apply_filters( 'flxapp_dashboard', $d, $u );
	foreach ( array( 'cards', 'lists' ) as $k ) {
		$rows = array_values( array_filter( (array) ( $d[ $k ] ?? array() ), 'is_array' ) );
		usort( $rows, fn( $a, $b ) => ( (int) ( $a['order'] ?? 50 ) ) <=> ( (int) ( $b['order'] ?? 50 ) ) );
		$d[ $k ] = $rows;
	}
	return $d;
}

function flxapp_dash_num( $n ) {
	return number_format_i18n( (float) $n );
}

/** Kısa tarih: bugün saat, yoksa gün ay. */
function flxapp_dash_when( $gmt ) {
	$ts = is_numeric( $gmt ) ? (int) $gmt : strtotime( $gmt . ' UTC' );
	if ( ! $ts ) {
		return '';
	}
	return wp_date( 'Y-m-d' ) === wp_date( 'Y-m-d', $ts ) ? flx_date( $ts, 'time' ) : flx_date( $ts, 'short' );
}

/* ───────── WordPress çekirdeği ───────── */

function flxapp_dash_wp( $d, $u ) {
	if ( user_can( $u, 'edit_posts' ) ) {
		$pc            = wp_count_posts( 'post' );
		$d['cards'][]  = array(
			'id'    => 'wp-posts',
			'title' => __( 'Yazılar', 'flx-app' ),
			'value' => flxapp_dash_num( $pc->publish ?? 0 ),
			/* translators: %s: taslak sayısı */
			'sub'   => (int) ( $pc->draft ?? 0 ) ? sprintf( __( '%s taslak', 'flx-app' ), flxapp_dash_num( $pc->draft ) ) : '',
			'icon'  => 'post',
			'url'   => admin_url( 'edit.php' ),
			'order' => 60,
		);
		$drafts = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'draft',
				'author'         => $u->ID,
				'posts_per_page' => 5,
				'orderby'        => 'modified',
				'no_found_rows'  => true,
			)
		);
		if ( $drafts ) {
			$d['lists'][] = array(
				'id'    => 'wp-drafts',
				'title' => __( 'Taslaklarım', 'flx-app' ),
				'icon'  => 'post',
				'url'   => admin_url( 'edit.php?post_status=draft&author=' . $u->ID ),
				'order' => 60,
				'items' => array_map(
					fn( $p ) => array(
						'title' => get_the_title( $p ) !== '' ? get_the_title( $p ) : __( '(başlıksız)', 'flx-app' ),
						'meta'  => flxapp_dash_when( $p->post_modified_gmt ),
						'url'   => get_edit_post_link( $p->ID, 'raw' ),
					),
					$drafts
				),
			);
		}
	}
	if ( user_can( $u, 'edit_pages' ) ) {
		$pg           = wp_count_posts( 'page' );
		$d['cards'][] = array(
			'id'    => 'wp-pages',
			'title' => __( 'Sayfalar', 'flx-app' ),
			'value' => flxapp_dash_num( $pg->publish ?? 0 ),
			'icon'  => 'page',
			'url'   => admin_url( 'edit.php?post_type=page' ),
			'order' => 62,
		);
	}
	if ( user_can( $u, 'moderate_comments' ) ) {
		$cc           = wp_count_comments();
		$wait         = (int) $cc->moderated;
		$d['cards'][] = array(
			'id'    => 'wp-comments',
			'title' => __( 'Onay bekleyen yorum', 'flx-app' ),
			'value' => flxapp_dash_num( $wait ),
			/* translators: %s: onaylı yorum sayısı */
			'sub'   => sprintf( __( '%s onaylı', 'flx-app' ), flxapp_dash_num( $cc->approved ) ),
			'icon'  => 'comment',
			'tone'  => $wait ? 'warn' : '',
			'url'   => admin_url( 'edit-comments.php' . ( $wait ? '?comment_status=moderated' : '' ) ),
			'order' => 40,
		);
		$recent = get_comments(
			array(
				'number' => 5,
				'status' => 'all',
				'type'   => 'comment',
			)
		);
		if ( $recent ) {
			$d['lists'][] = array(
				'id'    => 'wp-comments',
				'title' => __( 'Son yorumlar', 'flx-app' ),
				'icon'  => 'comment',
				'url'   => admin_url( 'edit-comments.php' ),
				'order' => 70,
				'items' => array_map(
					fn( $c ) => array(
						'title' => wp_trim_words( wp_strip_all_tags( $c->comment_content ), 12 ),
						'sub'   => $c->comment_author . ' · ' . get_the_title( $c->comment_post_ID ),
						'meta'  => '0' === $c->comment_approved ? __( 'Bekliyor', 'flx-app' ) : flxapp_dash_when( $c->comment_date_gmt ),
						'url'   => admin_url( 'comment.php?action=editcomment&c=' . $c->comment_ID ),
					),
					$recent
				),
			);
		}
	}
	if ( user_can( $u, 'upload_files' ) ) {
		$m            = wp_count_attachments();
		$total        = 0;
		foreach ( (array) $m as $k => $v ) {
			$total += 'trash' === $k ? 0 : (int) $v;
		}
		$d['cards'][] = array(
			'id'    => 'wp-media',
			'title' => __( 'Ortam', 'flx-app' ),
			'value' => flxapp_dash_num( $total ),
			'icon'  => 'media',
			'url'   => admin_url( 'upload.php' ),
			'order' => 64,
		);
	}
	if ( user_can( $u, 'list_users' ) ) {
		$uc           = count_users();
		$d['cards'][] = array(
			'id'    => 'wp-users',
			'title' => __( 'Kullanıcılar', 'flx-app' ),
			'value' => flxapp_dash_num( $uc['total_users'] ?? 0 ),
			'icon'  => 'users',
			'url'   => admin_url( 'users.php' ),
			'order' => 66,
		);
	}
	if ( user_can( $u, 'update_plugins' ) || user_can( $u, 'update_themes' ) || user_can( $u, 'update_core' ) ) {
		$up = wp_get_update_data();
		$n  = (int) ( $up['counts']['total'] ?? 0 );
		if ( $n ) {
			$d['cards'][] = array(
				'id'    => 'wp-updates',
				'title' => __( 'Güncelleme', 'flx-app' ),
				'value' => flxapp_dash_num( $n ),
				'icon'  => 'update',
				'tone'  => 'warn',
				'url'   => admin_url( 'update-core.php' ),
				'order' => 45,
			);
		}
	}
	return $d;
}

/* ───────── WooCommerce ───────── */

/** Sipariş listesi adresi: HPOS açıksa wc-orders, değilse klasik shop_order listesi. */
function flxapp_dash_orders_url( $status = '' ) {
	$hpos = class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	$base = $hpos ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order';
	return admin_url( $base . ( $status ? '&' . ( $hpos ? 'status' : 'post_status' ) . '=' . $status : '' ) );
}

function flxapp_dash_wc( $d, $u ) {
	if ( ! function_exists( 'wc_get_orders' ) || ! ( user_can( $u, 'edit_shop_orders' ) || user_can( $u, 'manage_woocommerce' ) ) ) {
		return $d;
	}
	$paid  = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'processing', 'completed' );
	$start = ( new DateTimeImmutable( 'today', wp_timezone() ) )->getTimestamp();
	$today = wc_get_orders(
		array(
			'limit'        => 500,
			'status'       => array_map( fn( $s ) => 'wc-' . $s, $paid ),
			'date_created' => '>=' . $start,
			'return'       => 'objects',
		)
	);
	$sum   = 0.0;
	foreach ( $today as $o ) {
		$sum += (float) $o->get_total();
	}
	$cur          = get_woocommerce_currency();
	$d['cards'][] = array(
		'id'    => 'wc-today',
		'title' => __( 'Bugünkü siparişler', 'flx-app' ),
		'value' => html_entity_decode( wp_strip_all_tags( wc_price( $sum, array( 'currency' => $cur ) ) ), ENT_QUOTES, 'UTF-8' ),
		/* translators: %s: sipariş sayısı */
		'sub'   => sprintf( __( '%s sipariş', 'flx-app' ), flxapp_dash_num( count( $today ) ) ),
		'icon'  => 'cart',
		'tone'  => 'accent',
		'url'   => flxapp_dash_orders_url(),
		'order' => 10,
	);
	$waiting = wc_get_orders(
		array(
			'limit'  => -1,
			'status' => array( 'wc-on-hold', 'wc-pending' ),
			'return' => 'ids',
		)
	);
	$n       = count( $waiting );
	$d['cards'][] = array(
		'id'    => 'wc-waiting',
		'title' => __( 'Ödeme bekleyen', 'flx-app' ),
		'value' => flxapp_dash_num( $n ),
		'icon'  => 'clock',
		'tone'  => $n ? 'warn' : '',
		'url'   => flxapp_dash_orders_url( 'wc-on-hold' ),
		'order' => 20,
	);
	$recent = wc_get_orders(
		array(
			'limit'   => 6,
			'orderby' => 'date',
			'order'   => 'DESC',
			'type'    => 'shop_order',
			'return'  => 'objects',
		)
	);
	if ( $recent ) {
		$d['lists'][] = array(
			'id'    => 'wc-orders',
			'title' => __( 'Son siparişler', 'flx-app' ),
			'icon'  => 'cart',
			'url'   => flxapp_dash_orders_url(),
			'order' => 10,
			'items' => array_map(
				function ( $o ) {
					$name = trim( $o->get_formatted_billing_full_name() );
					return array(
						'title' => '#' . $o->get_order_number() . ( '' !== $name ? ' · ' . $name : '' ),
						'sub'   => wc_get_order_status_name( $o->get_status() ) . ' · ' . flxapp_dash_when( $o->get_date_created() ? $o->get_date_created()->getTimestamp() : 0 ),
						'meta'  => html_entity_decode( wp_strip_all_tags( $o->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ),
						'url'   => $o->get_edit_order_url(),
					);
				},
				$recent
			),
		);
	}
	return $d;
}
