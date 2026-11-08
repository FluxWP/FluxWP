<?php
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/page-certificates.php';
require_once __DIR__ . '/export.php';
require_once __DIR__ . '/ecards.php';
require_once __DIR__ . '/page-system.php';
require_once __DIR__ . '/page-create.php';
require_once __DIR__ . '/page-people.php';
require_once __DIR__ . '/page-dashboard.php';
require_once __DIR__ . '/page-events.php';
require_once __DIR__ . '/page-queue.php';
require_once __DIR__ . '/page-bulk.php';
require_once __DIR__ . '/page-templates.php';
require_once __DIR__ . '/page-settings.php';
require_once __DIR__ . '/page-logs.php';

add_action( 'admin_menu', 'flxs_admin_menu' );

function flxs_admin_menu() {
	$cap_use = flxs_can_manage() ? 'flxs_manage' : 'flxs_use';
	add_menu_page( 'Sertifikalar', 'Sertifikalar', $cap_use, 'flxs', 'flxs_page_certificates', 'none', 26 );
	add_submenu_page( 'flxs', 'Tüm Sertifikalar', 'Tüm Sertifikalar', $cap_use, 'flxs', 'flxs_page_certificates' );
	if ( flxs_can_dashboard() ) {
		add_submenu_page( 'flxs', 'Dashboard', 'Dashboard', flxs_can_manage() ? 'flxs_manage' : 'flxs_dashboard', 'flxs-dashboard', 'flxs_page_dashboard', 0 );
	}
	add_submenu_page( 'flxs', 'Etkinlikler', 'Etkinlikler', 'flxs_manage', 'flxs-etkinlikler', 'flxs_page_events', 1 );
	add_submenu_page( 'flxs', 'Yeni Sertifika', 'Yeni Sertifika', $cap_use, 'flxs-yeni', 'flxs_page_create' );
	add_submenu_page( 'flxs', 'Kişiler', 'Kişiler', $cap_use, 'flxs-kisiler', 'flxs_page_people' );
	add_submenu_page( 'flxs', 'Gönderim Kuyruğu', 'Gönderim Kuyruğu', $cap_use, 'flxs-kuyruk', 'flxs_page_queue' );
	if ( flxs_can_bulk() ) {
		add_submenu_page( 'flxs', 'Toplu Gönderim', 'Toplu Gönderim', $cap_use, 'flxs-toplu', 'flxs_page_bulk' );
	}
	add_submenu_page( 'flxs', 'Şablonlar', 'Şablonlar', 'flxs_manage', 'flxs-sablonlar', 'flxs_page_templates' );
	// Sağlayıcılar ekranı kaldırıldı (v1.3.0): WhatsApp/SMS/e-posta ayarları Flux Mail'de; eski adres (gizli sayfa) oraya yönlenir.
	$old = add_submenu_page( 'options.php', 'Sağlayıcılar', 'Sağlayıcılar', 'flxs_manage', 'flxs-saglayicilar', '__return_null' );
	add_action(
		'load-' . $old,
		function () {
			wp_safe_redirect( flxs_iletisim_active() ? admin_url( 'admin.php?page=flxi-baglantilar' ) : admin_url( 'admin.php?page=flxs-sistem' ) );
			exit;
		}
	);
	add_submenu_page( 'flxs', 'Kayıtlar (Log)', 'Kayıtlar (Log)', 'flxs_manage', 'flxs-kayitlar', 'flxs_page_logs' );
	add_submenu_page( 'flxs', 'Sertifika Ayarları', 'Ayarlar', 'flxs_manage', 'flxs-ayarlar', 'flxs_page_settings' );
	add_submenu_page( 'flxs', __( 'System check', 'flx-certificates' ), __( 'System check', 'flx-certificates' ), 'flxs_manage', 'flxs-sistem', 'flxs_page_system' );
}

/* Menü ikonu: renkli FluxWP logosu (WordPress menü ikonlarını tek renge boyadığı için CSS ile) */
add_action(
	'admin_head',
	function () {
		echo '<style>#toplevel_page_flxs .wp-menu-image{background:url(' . esc_url( FLXS_URL . 'assets/icon.svg' ) . ') center/20px 20px no-repeat}#toplevel_page_flxs .wp-menu-image:before{content:none}</style>';
	}
);

/* Personel: Başlangıç yerine sertifika ekranı; üst çubukta hızlı "Yeni sertifika" */
add_action(
	'admin_init',
	function () {
		global $pagenow;
		if ( 'index.php' === $pagenow && current_user_can( 'flxs_use' ) && ! current_user_can( 'edit_posts' ) && ! wp_doing_ajax() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=flxs-yeni' ) );
			exit;
		}
	}
);

add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! is_user_logged_in() || ! flxs_can_use() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'flxs-new',
				'title' => '<span class="ab-icon dashicons dashicons-awards" style="top:2px"></span><span class="ab-label">Sertifika</span>',
				'href'  => admin_url( 'admin.php?page=flxs-yeni' ),
			)
		);
	},
	80
);

/* Eklenti sayfaları için ortak CSS/JS */
add_action( 'admin_enqueue_scripts', 'flxs_admin_assets' );

function flxs_admin_assets( $hook ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore
	if ( 0 !== strpos( $page, 'flxs' ) ) {
		return;
	}
	wp_enqueue_style( 'flxs-admin', FLXS_URL . 'assets/css/admin.css', array(), FLXS_VERSION );
	wp_enqueue_script( 'flxs-admin', FLXS_URL . 'assets/js/admin.js', array(), FLXS_VERSION, true );
	wp_localize_script(
		'flxs-admin',
		'FLXS',
		array(
			'rest'      => esc_url_raw( rest_url( 'flx-certificates/v1/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'admin'     => admin_url( 'admin.php' ),
			'providers' => flxs_provider_options_for_js(),
			'connUrl'   => flxs_iletisim_active() ? admin_url( 'admin.php?page=flxi-baglantilar' ) : admin_url( 'plugins.php' ),
			'connNone'  => flxs_iletisim_active() ? __( 'No connection in Flux Mail', 'flx-certificates' ) : __( 'Flux Mail is not active', 'flx-certificates' ),
			'connAdd'   => flxs_iletisim_active() ? __( 'add', 'flx-certificates' ) : __( 'Plugins', 'flx-certificates' ),
			'channels'  => flxs_channels(),
			'statuses'  => flxs_status_labels(),
			'manage'    => flxs_can_manage(),
			'site'      => get_bloginfo( 'name' ),
			'refs'      => 'flxs' === $page ? array_map( function ( $u ) { return array( 'id' => (int) $u->ID, 'name' => $u->display_name ); }, flxs_users_with_cap() ) : array(),
		)
	);
	if ( in_array( $page, array( 'flxs-yeni', 'flxs-sablonlar' ), true ) ) {
		flxs_register_renderer();
		wp_enqueue_script( 'flxs-renderer' );
	}
	if ( 'flxs-etkinlikler' === $page && ( isset( $_GET['edit'] ) || isset( $_GET['add'] ) ) ) { // phpcs:ignore
		wp_enqueue_media();
	}
	if ( 'flxs-dashboard' === $page ) {
		wp_enqueue_script( 'flxs-dashboard', FLXS_URL . 'assets/js/dashboard.js', array( 'flxs-admin' ), FLXS_VERSION, true );
	}
	if ( 'flxs-yeni' === $page ) {
		wp_enqueue_script( 'flxs-create', FLXS_URL . 'assets/js/create.js', array( 'flxs-admin', 'flxs-renderer' ), FLXS_VERSION, true );
	}
	if ( 'flxs-sablonlar' === $page && ( isset( $_GET['edit'] ) || isset( $_GET['new'] ) ) ) { // phpcs:ignore
		wp_enqueue_media();
		wp_enqueue_script( 'flxs-template-editor', FLXS_URL . 'assets/js/template-editor.js', array( 'flxs-admin', 'flxs-renderer' ), FLXS_VERSION, true );
	}
}

/* ---------- Ortak görünüm yardımcıları ---------- */

function flxs_admin_header( $title, $actions = array(), $sub = '' ) {
	echo '<div class="wrap flxs-wrap">';
	echo '<div class="flxs-head"><div><h1 class="flxs-title">' . esc_html( $title ) . '</h1>';
	if ( $sub ) {
		echo '<p class="flxs-sub">' . wp_kses_post( $sub ) . '</p>';
	}
	echo '</div><div class="flxs-head__actions">';
	foreach ( $actions as $a ) {
		printf( '<a href="%s" class="button %s">%s</a> ', esc_url( $a['url'] ), esc_attr( $a['class'] ?? '' ), esc_html( $a['label'] ) );
	}
	echo '</div></div>';
	flxs_admin_notices();
}

function flxs_admin_footer() {
	echo '</div>';
}

/** admin-post işlemlerinden sonra gösterilecek mesajlar (?flxs_msg=...) */
function flxs_admin_notices() {
	$msg = isset( $_GET['flxs_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['flxs_msg'] ) ) : ''; // phpcs:ignore
	$err = isset( $_GET['flxs_err'] ) ? sanitize_text_field( wp_unslash( $_GET['flxs_err'] ) ) : ''; // phpcs:ignore
	if ( $msg ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
	if ( $err ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $err ) . '</p></div>';
	}
}

function flxs_redirect( $page, $args = array() ) {
	wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) ) );
	exit;
}

/* Liste ekranları: Flux Admin 1.2.0+ ortak liste lib'i (filtre çubuğu, sayfa başı, sayfalama); yoksa eski çıktı */
function flxs_list_form_attrs( $key ) {
	return function_exists( 'flxa_list_form_attrs' ) ? flxa_list_form_attrs( array( 'key' => $key ) ) : '';
}

function flxs_list_per_page( $key, $default ) {
	return function_exists( 'flxa_list_per_page' ) ? (int) flxa_list_per_page( $key, $default ) : $default;
}

function flxs_list_pages( $total, $per_page, $current ) {
	if ( function_exists( 'flxa_list_pagination' ) ) {
		flxa_list_pagination( $total, $per_page, $current );
	} else {
		flxs_pagination( $total, $per_page, $current );
	}
}

function flxs_pagination( $total, $per_page, $current ) {
	$pages = (int) ceil( $total / max( 1, $per_page ) );
	if ( $pages < 2 ) {
		return;
	}
	echo '<div class="flxs-pages">';
	echo paginate_links( // phpcs:ignore
		array(
			'base'      => add_query_arg( 'paged', '%#%' ),
			'format'    => '',
			'current'   => $current,
			'total'     => $pages,
			'prev_text' => '‹',
			'next_text' => '›',
		)
	);
	echo '<span class="flxs-pages__total">' . esc_html( sprintf( _n( '[[\'%dregistration\', \'tr\']]', '[[\'%dregistration\', \'tr\']]', (int) $total, 'flx-certificates' ), (int) $total ) ) . '</span></div>';
}

function flxs_status_badge( $status ) {
	$labels = flxs_status_labels() + array( 'active' => __( 'Valid', 'flx-certificates' ), 'revoked' => __( 'Cancelled', 'flx-certificates' ), 'inactive' => __( 'Inactive', 'flx-certificates' ) );
	return '<span class="flxs-pill flxs-pill--' . esc_attr( $status ) . '">' . esc_html( $labels[ $status ] ?? $status ) . '</span>';
}

function flxs_channel_icon( $channel ) {
	$icons = array(
		'email'    => 'dashicons-email-alt',
		'sms'      => 'dashicons-smartphone',
		'whatsapp' => 'dashicons-whatsapp',
	);
	return '<span class="dashicons ' . esc_attr( $icons[ $channel ] ?? 'dashicons-format-chat' ) . '" title="' . esc_attr( flxs_channel_label( $channel ) ) . '"></span>';
}

function flxs_get_param( $key, $default = '' ) {
	return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default; // phpcs:ignore
}

/** Kullanıcı açılır listesi (oluşturan filtresi) */

/* TTF/OTF yüklemeye izin ver (yalnızca sertifika yöneticisi; şablon fontları için) */
add_filter(
	'upload_mimes',
	function ( $m ) {
		if ( current_user_can( 'flxs_manage' ) ) {
			$m['ttf'] = 'font/ttf';
			$m['otf'] = 'font/otf';
		}
		return $m;
	}
);
add_filter(
	'wp_check_filetype_and_ext',
	function ( $data, $file, $filename ) {
		if ( current_user_can( 'flxs_manage' ) && preg_match( '/\.(ttf|otf)$/i', $filename, $m ) ) {
			$ext = strtolower( $m[1] );
			return array( 'ext' => $ext, 'type' => 'font/' . $ext, 'proper_filename' => false );
		}
		return $data;
	},
	10,
	3
);
