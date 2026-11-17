<?php
/**
 * Yönetim menüsü: her sayfa ortak React ekranını açar (includes/admin-flux.php). Klasik PHP sayfası yoktur;
 * burada yalnız menü, logo, skill dışa aktarma/dosya indirme ve görev raporu yönlendirmesi kalır.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'flxai_admin_menu' );

function flxai_admin_menu() {
	$waiting = current_user_can( 'manage_options' ) ? flxai_approvals_count() + count( flxai_tasks_unread() ) : 0;
	add_menu_page( 'Flux AI & MCP', 'Flux AI & MCP' . ( $waiting ? ' <span class="awaiting-mod count-' . (int) $waiting . '"><span class="pending-count">' . esc_html( number_format_i18n( $waiting ) ) . '</span></span>' : '' ), 'manage_options', 'flx-ai', 'flxai_flux_render', 'none', 59 );
	add_submenu_page( 'flx-ai', __( 'Overview', 'flx-ai' ), __( 'Overview', 'flx-ai' ), 'manage_options', 'flx-ai', 'flxai_flux_render' );
	add_submenu_page( 'flx-ai', __( 'Chat', 'flx-ai' ), __( 'Chat', 'flx-ai' ), 'manage_options', 'flx-ai-chat', 'flxai_flux_render' );
	add_submenu_page( 'flx-ai', __( 'Chat history', 'flx-ai' ), __( 'Chat history', 'flx-ai' ), 'manage_options', 'flx-ai-history', 'flxai_flux_render' );
	$bubble = function ( $n ) {
		return $n ? ' <span class="awaiting-mod count-' . (int) $n . '"><span class="pending-count">' . esc_html( number_format_i18n( $n ) ) . '</span></span>' : '';
	};
	add_submenu_page( 'flx-ai', __( 'Scheduled tasks', 'flx-ai' ), __( 'Scheduled tasks', 'flx-ai' ) . $bubble( count( flxai_tasks_unread() ) ), 'manage_options', 'flx-ai-tasks', 'flxai_flux_render' );
	add_submenu_page( 'flx-ai', __( 'Awaiting approval', 'flx-ai' ), __( 'Awaiting approval', 'flx-ai' ) . $bubble( flxai_approvals_count() ), 'manage_options', 'flx-ai-approvals', 'flxai_flux_render' );
	add_submenu_page( 'flx-ai', __( 'Skills', 'flx-ai' ), __( 'Skills', 'flx-ai' ), 'manage_options', 'flx-ai-skills', 'flxai_flux_render' );
	add_submenu_page( 'flx-ai', __( 'Logs', 'flx-ai' ), __( 'Logs', 'flx-ai' ), 'manage_options', 'flx-ai-log', 'flxai_flux_render' );
	add_submenu_page( 'flx-ai', __( 'Connected systems', 'flx-ai' ), __( 'Connected systems', 'flx-ai' ), 'manage_options', 'flx-ai-systems', 'flxai_flux_render' );
	add_submenu_page( 'flx-ai', __( 'Billing', 'flx-ai' ), __( 'Billing', 'flx-ai' ), 'manage_options', 'flx-ai-billing', 'flxai_flux_render' );
	add_submenu_page( 'flx-ai', __( 'Tools', 'flx-ai' ), __( 'Tools', 'flx-ai' ), 'manage_options', 'flx-ai-tools', 'flxai_flux_render' );
	add_submenu_page( 'flx-ai', 'MCP', 'MCP', 'manage_options', 'flx-ai-mcp', 'flxai_flux_render' );
	add_submenu_page( 'flx-ai', __( 'Settings', 'flx-ai' ), __( 'Settings', 'flx-ai' ), 'manage_options', 'flx-ai-settings', 'flxai_flux_render' );
	add_submenu_page( 'flx-ai', __( 'Connections', 'flx-ai' ), __( 'Connections', 'flx-ai' ), 'manage_options', 'flx-ai-connections', 'flxai_flux_render' );
}

/** Flux Admin alt bar / komut paleti çizgi simgesi. */
add_filter(
	'flxa_menu_icons',
	function ( $m ) {
		$m['flx-ai'] = 'bolt';
		return $m;
	}
);

add_action(
	'admin_head',
	function () {
		// Flux Admin kuruluysa menü logosunu o verir (boyut ve Degrade/Pastel stili diğer paketlerle aynı).
		if ( function_exists( 'flxa_logo_file_url' ) ) {
			return;
		}
		$u = esc_url( FLXAI_URL . 'assets/icon.svg?ver=' . FLXAI_VERSION );
		echo '<style>#adminmenu #toplevel_page_flx-ai div.wp-menu-image,body #adminmenu li#toplevel_page_flx-ai.flxa-letter div.wp-menu-image{background:url(' . $u . ') center/20px 20px no-repeat!important}#adminmenu #toplevel_page_flx-ai div.wp-menu-image::before{content:none!important}</style>';
	}
);

/* Skill dışa aktarma (zip) ve klasör dosyası indirme: React ekranının exportUrl/indirme adresleri. */
add_action(
	'admin_init',
	function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'], $_GET['export'] ) && 'flx-ai-skills' === $_GET['page'] && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'flxai_skill_export' );
			$s = flxai_skill_get( sanitize_key( wp_unslash( $_GET['export'] ) ) );
			if ( $s ) {
				$zip = flxai_skill_zip( $s );
				nocache_headers();
				if ( is_wp_error( $zip ) ) {
					header( 'Content-Type: text/markdown; charset=utf-8' );
					header( 'Content-Disposition: attachment; filename="SKILL.md"' );
					echo flxai_skill_to_md( $s, null, null, true ); // phpcs:ignore WordPress.Security.EscapeOutput
					exit;
				}
				header( 'Content-Type: application/zip' );
				header( 'Content-Disposition: attachment; filename="' . $s['slug'] . '.zip"' );
				header( 'Content-Length: ' . filesize( $zip ) );
				readfile( $zip ); // phpcs:ignore
				wp_delete_file( $zip );
				exit;
			}
		}
		if ( isset( $_GET['page'], $_GET['skill'], $_GET['download'] ) && 'flx-ai-skills' === $_GET['page'] && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'flxai_skill_file' );
			$s = flxai_skill_get( sanitize_key( wp_unslash( $_GET['skill'] ) ) );
			$p = $s ? flxai_skill_clean_path( wp_unslash( $_GET['download'] ) ) : '';
			if ( $s && ! is_wp_error( $p ) && is_file( flxai_skill_abs_dir( $s ) . '/' . $p ) ) {
				nocache_headers();
				header( 'Content-Type: application/octet-stream' );
				header( 'Content-Disposition: attachment; filename="' . basename( $p ) . '"' );
				readfile( flxai_skill_abs_dir( $s ) . '/' . $p ); // phpcs:ignore
				exit;
			}
		}
		// phpcs:enable
	}
);

/* Görev raporu bağlantısı (bildirimler): okundu işaretler, görevin sohbetine yönlendirir. */
add_action(
	'admin_init',
	function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( 'flx-ai-tasks' !== ( $_GET['page'] ?? '' ) || ( empty( $_GET['open'] ) && empty( $_GET['history'] ) ) ) {
			return;
		}
		$t = flxai_task_get( (int) ( $_GET['open'] ?? $_GET['history'] ) );
		// phpcs:enable
		if ( ! $t || ( (int) $t['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=flx-ai-tasks' ) );
			exit;
		}
		flxai_task_mark_read( $t['id'] );
		wp_safe_redirect( $t['chat_id'] ? flxai_task_chat_url( $t ) : admin_url( 'admin.php?page=flx-ai-tasks' ) );
		exit;
	}
);

/** Görevin sohbetini tam ekran AI sohbetinde açan adres. */
function flxai_task_chat_url( array $t ) {
	return $t['chat_id'] ? add_query_arg( 'flxai_chat', (int) $t['chat_id'], admin_url( 'admin.php?page=flx-ai-chat' ) ) : '';
}

/** Görevin raporunu açan adres (okundu işaretler, sohbete yönlendirir). */
function flxai_task_open_url( array $t ) {
	return admin_url( 'admin.php?page=flx-ai-tasks&open=' . (int) $t['id'] );
}
