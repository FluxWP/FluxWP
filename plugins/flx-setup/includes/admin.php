<?php
/**
 * Yönetim ekranı: Görünüm › Ön ayarlar (themes.php?page=flx-presets).
 * Tema kendi ayar ekranında sekme olarak göstermek isterse flxk_render_panel() çağırır.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		$hook = add_theme_page( __( 'Presets', 'flx-setup' ), __( 'Presets', 'flx-setup' ), flxk_cap(), 'flx-presets', 'flxk_render_page' );
		if ( flxk_panel_in_theme() ) {
			// Tema kendi ayar ekranında sekme olarak gösteriyor: ayrı menü öğesi yok, eski adres oraya yönlenir.
			remove_submenu_page( 'themes.php', 'flx-presets' );
			add_action(
				'load-' . $hook,
				function () {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$args = array_map( 'sanitize_text_field', wp_unslash( array_diff_key( $_GET, array( 'page' => 1 ) ) ) );
					wp_safe_redirect( flxk_admin_url( $args ) );
					exit;
				}
			);
		}
	},
	20
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( false === strpos( (string) $hook, 'flx-presets' ) && ! flxk_is_panel_screen() && ! apply_filters( 'flxk_enqueue_admin', false, $hook ) ) {
			return;
		}
		wp_enqueue_style( 'flxk-admin', FLXK_URL . 'assets/admin.css', array(), FLXK_VERSION );
		wp_enqueue_script( 'flxk-selection', FLXK_URL . 'assets/selection.js', array(), FLXK_VERSION, true );
	}
);

/* ---------- İlk kurulum ---------- */

/** Ön ayar eklentisi etkinleşince çağırır: boş sitede kendiliğinden uygulanır, doluysa bildirim çıkar. */
function flxk_preset_activated( $id ) {
	$q   = (array) get_option( 'flxk_install_queue', array() );
	$q[] = sanitize_key( $id );
	update_option( 'flxk_install_queue', array_values( array_unique( $q ) ), false );
}

/** Boş site: yalnız WordPress'in örnek sayfaları var, ön ayar uygulanmamış. */
function flxk_site_is_fresh() {
	if ( flxk_state() ) {
		return false;
	}
	$n = (int) wp_count_posts( 'page' )->publish + (int) wp_count_posts( 'post' )->publish;
	return $n <= 3;
}

add_action( 'admin_init', 'flxk_process_install_queue', 5 );
function flxk_process_install_queue() {
	$q = (array) get_option( 'flxk_install_queue', array() );
	if ( ! $q || ! current_user_can( flxk_cap() ) || wp_doing_ajax() ) {
		return;
	}
	$fresh = flxk_site_is_fresh();
	$left  = array();
	foreach ( $q as $id ) {
		if ( flxk_state( $id ) ) {
			continue;
		}
		if ( ! flxk_get_preset( $id ) ) {
			$left[] = $id; // Ön ayar henüz yüklenmedi (eklenti sırası); sonraki istekte.
			continue;
		}
		if ( $fresh ) {
			flxk_apply_preset( $id, 'apply' );
		} else {
			$left[] = $id;
		}
	}
	update_option( 'flxk_install_queue', $left, false );
	if ( $fresh && $q ) {
		set_transient( 'flxk_notice', array( 'ok', __( 'Preset applied to the empty site.', 'flx-setup' ) ), 60 );
	}
}

add_action(
	'admin_notices',
	function () {
		if ( ! current_user_can( flxk_cap() ) ) {
			return;
		}
		$n = get_transient( 'flxk_notice' );
		if ( $n ) {
			delete_transient( 'flxk_notice' );
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', 'ok' === $n[0] ? 'success' : 'error', wp_kses_post( $n[1] ) );
		}
		$screen = get_current_screen();
		if ( ( $screen && false !== strpos( (string) $screen->id, 'flx-presets' ) ) || flxk_is_panel_screen() ) {
			return;
		}
		$wait = array();
		foreach ( (array) get_option( 'flxk_install_queue', array() ) as $id ) {
			$p = flxk_get_preset( $id );
			if ( $p ) {
				$wait[] = $p['name'];
			}
		}
		$mig = 0;
		foreach ( array_keys( flxk_state() ) as $id ) {
			$mig += count( flxk_pending_migrations( $id ) );
		}
		$pat = count( flxk_pending_patches() );
		if ( $wait ) {
			/* translators: %s: ön ayar adları */
			$msg = sprintf( __( '%s preset is ready. It was not applied automatically because the site has content: preview and apply it.', 'flx-setup' ), implode( ', ', $wait ) );
		} elseif ( $mig || $pat ) {
			/* translators: 1: migration sayısı, 2: yama sayısı */
			$msg = sprintf( __( 'Presets: %1$d pending updates, %2$d patches awaiting approval.', 'flx-setup' ), $mig, $pat );
		} else {
			return;
		}
		printf( '<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>', esc_html( $msg ), esc_url( flxk_admin_url() ), esc_html__( 'Presets', 'flx-setup' ) );
	}
);

/* ---------- İşlemler ---------- */

add_action( 'admin_post_flxk', 'flxk_handle_post' );
function flxk_handle_post() {
	if ( ! current_user_can( flxk_cap() ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'flx-setup' ), 403 );
	}
	check_admin_referer( 'flxk' );
	$do   = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
	$id   = sanitize_key( wp_unslash( $_POST['preset'] ?? '' ) );
	$mode = 'fill' === ( $_POST['mode'] ?? '' ) ? 'fill' : 'apply';
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$force = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['force'] ?? array() ) );
	$only = isset( $_POST['selection_present'] ) ? array_values( array_unique( array_merge( array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['selected'] ?? array() ) ), $force ) ) ) : null;
	$mig   = sanitize_text_field( wp_unslash( $_POST['migration'] ?? '' ) );
	$res   = null;
	$back  = flxk_admin_url();

	switch ( $do ) {
		case 'apply':
			$res = flxk_apply_preset( $id, $mode, $force, $only );
			$q   = array_diff( (array) get_option( 'flxk_install_queue', array() ), array( $id ) );
			update_option( 'flxk_install_queue', array_values( $q ), false );
			break;
		case 'migrate':
			$res = flxk_apply_migration(
				$id,
				$mig,
				array(
					'mode'  => 'apply',
					'force' => $force,
					'only'  => $only,
				)
			);
			break;
		case 'skip':
			flxk_skip_migration( $id, $mig );
			$res = array( 'ok' => true );
			break;
		case 'remove':
			$res = flxk_remove_preset( $id );
			break;
		case 'requirements':
			$p   = flxk_get_preset( $id );
			$out = flxk_install_plugins( $p ? flxk_inactive_plugins( (array) ( $p['requires'] ?? array() ) ) : array() );
			$bad = wp_list_pluck( wp_list_filter( $out['lines'], array( 'ok' => false ) ), 'text' );
			$res = $out['ok'] ? array( 'ok' => true ) : new WP_Error( 'requirements', $bad ? implode( ' ', $bad ) : __( 'The missing plugins could not be installed. Install them from FluxWP Panel › Packages.', 'flx-setup' ) );
			break;
		case 'revert':
			$res = flxk_revert_run( sanitize_text_field( wp_unslash( $_POST['run'] ?? '' ) ) );
			break;
		case 'patch_apply':
			$res = flxk_patch_apply( sanitize_key( wp_unslash( $_POST['patch'] ?? '' ) ), 'panel', '', array( 'force' => $force, 'only' => $only ) );
			break;
		case 'patch_reject':
			flxk_patch_reject( sanitize_key( wp_unslash( $_POST['patch'] ?? '' ) ) );
			$res = array( 'ok' => true );
			break;
		case 'import':
			$res = flxk_import_upload();
			break;
		case 'profile':
			flxk_save_profile_form();
			$res  = array( 'ok' => true );
			$back = flxk_admin_url( array( 'tab' => 'profil' ) );
			break;
		case 'settings':
			update_option( 'flxk_settings', array( 'mcp_direct' => empty( $_POST['mcp_direct'] ) ? 0 : 1 ), false );
			$res  = array( 'ok' => true );
			$back = flxk_admin_url( array( 'tab' => 'araclar' ) );
			break;
	}

	if ( is_wp_error( $res ) ) {
		set_transient( 'flxk_notice', array( 'error', $res->get_error_message() ), 60 );
	} elseif ( is_array( $res ) ) {
		$msg = __( 'Done.', 'flx-setup' );
		if ( isset( $res['written'] ) ) {
			/* translators: %d: sayı */
			$msg = sprintf( __( 'Applied: %d changes.', 'flx-setup' ), (int) $res['written'] );
			if ( ! empty( $res['counts']['manual'] ) ) {
				/* translators: %d: sayı */
				$msg .= ' ' . sprintf( __( '%d manually changed values were kept.', 'flx-setup' ), (int) $res['counts']['manual'] );
			}
			if ( ! empty( $res['errors'] ) ) {
				$msg .= '<br>' . esc_html( implode( ' · ', $res['errors'] ) );
			}
		} elseif ( isset( $res['restored'] ) ) {
			/* translators: 1: geri yüklenen, 2: atlanan */
			$msg = sprintf( __( 'Undone: %1$d. Left untouched because they changed later: %2$d.', 'flx-setup' ), (int) $res['restored'], count( (array) $res['skipped'] ) );
		} elseif ( isset( $res['imported'] ) ) {
			/* translators: %s: ön ayar adı */
			$msg = sprintf( __( '"%s" imported. You can preview and apply it.', 'flx-setup' ), $res['imported'] );
		}
		set_transient( 'flxk_notice', array( 'ok', $msg ), 60 );
	}
	wp_safe_redirect( $back );
	exit;
}

/** Şablon çıkar: JSON indir. */
add_action( 'admin_post_flxk_export', 'flxk_handle_export' );
function flxk_handle_export() {
	if ( ! current_user_can( flxk_cap() ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'flx-setup' ), 403 );
	}
	check_admin_referer( 'flxk_export' );
	$sections = array_map( 'sanitize_key', (array) wp_unslash( $_POST['sections'] ?? array() ) );
	$data     = flxk_export(
		array(
			'sections' => $sections ? $sections : array( 'profile', 'wp', 'settings', 'theme', 'content', 'media' ),
			'pages'    => ! empty( $_POST['pages'] ),
		)
	);
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $data['id'] . '-preset.json"' );
	echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}

/** JSON içe aktarımı: uploads/flx-setup/<id>/preset.json. Dosya referansları ($file) desteklenmez. */
function flxk_import_upload() {
	$raw = '';
	if ( ! empty( $_FILES['preset']['tmp_name'] ) && is_uploaded_file( $_FILES['preset']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$raw = (string) file_get_contents( $_FILES['preset']['tmp_name'] ); // phpcs:ignore
	} elseif ( ! empty( $_POST['json'] ) ) {
		$raw = (string) wp_unslash( $_POST['json'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}
	$data = flxk_load_json( $raw );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	if ( false !== strpos( $raw, '"$file"' ) ) {
		return new WP_Error( 'file', __( 'An imported preset cannot contain "$file" references; write bodies inline or install the preset as a plugin.', 'flx-setup' ) );
	}
	$ok = flxk_validate( $data );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$dir = flxk_upload_dir() . $data['id'] . '/';
	wp_mkdir_p( $dir );
	file_put_contents( $dir . 'preset.json', wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); // phpcs:ignore
	if ( ! file_exists( flxk_upload_dir() . 'index.php' ) ) {
		file_put_contents( flxk_upload_dir() . 'index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
	}
	flx_private_dir( flxk_upload_dir() );
	return array( 'imported' => $data['name'] );
}

function flxk_save_profile_form() {
	$in  = (array) wp_unslash( $_POST['profile'] ?? array() ); // phpcs:ignore WordPress.Security
	$cur = flxk_profile_all();
	foreach ( flxk_profile_fields() as $k => $label ) {
		if ( ! array_key_exists( $k, $in ) ) {
			continue;
		}
		if ( in_array( $k, array( 'colors', 'social' ), true ) ) {
			$map = array();
			foreach ( preg_split( '/\r\n|\n/', (string) $in[ $k ] ) as $line ) {
				$pair = array_map( 'trim', explode( '=', $line, 2 ) );
				if ( 2 === count( $pair ) && '' !== $pair[0] ) {
					$map[ sanitize_key( $pair[0] ) ] = 'colors' === $k ? sanitize_hex_color( $pair[1] ) : esc_url_raw( $pair[1] );
				}
			}
			$cur[ $k ] = array_filter( $map );
		} elseif ( in_array( $k, array( 'logo', 'logo_light' ), true ) ) {
			$cur[ $k ] = absint( $in[ $k ] );
		} elseif ( 'about' === $k || 'address' === $k ) {
			$cur[ $k ] = sanitize_textarea_field( $in[ $k ] );
		} else {
			$cur[ $k ] = sanitize_text_field( $in[ $k ] );
		}
	}
	update_option( 'flx_profile', $cur );
	flxk_log(
		array(
			'preset' => FLXK_SITE,
			'kind'   => 'profile',
			'title'  => __( 'Organisation profile edited in the admin', 'flx-setup' ),
		)
	);
}

/* ---------- Görünüm ---------- */

function flxk_render_page() {
	echo '<div class="wrap flxk">';
	echo '<h1>' . esc_html__( 'Presets', 'flx-setup' ) . '</h1>';
	echo '<p class="flxk-lead">' . esc_html__( 'A preset sets up the site in one step: organisation profile, plugin settings, colours, header/footer, templates, pages and menus. Changes you make in the admin are never overwritten; updates are applied below one by one with your approval.', 'flx-setup' ) . '</p>';
	flxk_render_panel();
	echo '</div>';
}

function flxk_render_panel() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$tab = sanitize_key( $_GET['tab'] ?? 'onayarlar' );
	if ( ! empty( $_GET['preview'] ) ) {
		flxk_render_preview( sanitize_key( $_GET['preview'] ), 'fill' === ( $_GET['mode'] ?? '' ) ? 'fill' : 'apply', sanitize_text_field( wp_unslash( $_GET['migration'] ?? '' ) ), '' );
		return;
	}
	if ( ! empty( $_GET['patch'] ) ) {
		flxk_render_preview( '', 'apply', '', sanitize_key( $_GET['patch'] ) );
		return;
	}
	// phpcs:enable
	$tabs = array(
		'onayarlar' => __( 'Presets', 'flx-setup' ),
		'profil'    => __( 'Organisation profile', 'flx-setup' ),
		'gecmis'    => __( 'History', 'flx-setup' ),
		'araclar'   => __( 'Export / import', 'flx-setup' ),
	);
	echo '<nav class="nav-tab-wrapper flxk-tabs">';
	foreach ( $tabs as $k => $l ) {
		printf( '<a class="nav-tab%1$s" href="%2$s">%3$s</a>', $k === $tab ? ' nav-tab-active' : '', esc_url( flxk_admin_url( array( 'tab' => $k ) ) ), esc_html( $l ) );
	}
	echo '</nav>';
	switch ( $tab ) {
		case 'profil':
			flxk_render_profile();
			break;
		case 'gecmis':
			flxk_render_history();
			break;
		case 'araclar':
			flxk_render_tools();
			break;
		default:
			flxk_render_presets();
	}
}

function flxk_form_open( $do, array $hidden = array() ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="flxk-inline">';
	echo '<input type="hidden" name="action" value="flxk"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">';
	foreach ( $hidden as $k => $v ) {
		echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	wp_nonce_field( 'flxk' );
}

function flxk_render_presets() {
	$presets = flxk_presets();
	if ( ! $presets ) {
		echo '<div class="flxk-card"><p>' . esc_html__( 'No presets installed. Install a preset plugin (e.g. Örnek Preset) or import JSON from the "Export / import" tab.', 'flx-setup' ) . '</p></div>';
	}
	foreach ( $presets as $id => $p ) {
		$st = flxk_state( $id );
		echo '<section class="flxk-card">';
		echo '<header class="flxk-card__head"><h2>' . esc_html( $p['name'] ) . '</h2>';
		if ( ! empty( $p['error'] ) ) {
			echo '<span class="flxk-badge flxk-badge--err">' . esc_html__( 'Invalid', 'flx-setup' ) . '</span></header><pre class="flxk-err">' . esc_html( $p['error'] ) . '</pre></section>';
			continue;
		}
		if ( $st ) {
			/* translators: %s: tarih */
			echo '<span class="flxk-badge flxk-badge--ok">' . esc_html( sprintf( __( 'Applied · %s', 'flx-setup' ), ( empty( $st['applied_at'] ) ? '' : flx_date( get_gmt_from_date( $st['applied_at'] ) ) ) ) ) . '</span>';
		} else {
			echo '<span class="flxk-badge">' . esc_html__( 'Not applied', 'flx-setup' ) . '</span>';
		}
		echo '</header>';
		if ( ! empty( $p['description'] ) ) {
			echo '<p>' . esc_html( $p['description'] ) . '</p>';
		}
		$req = flxk_requirements_missing( $p );
		if ( $req ) {
			/* translators: %s: eksikler */
			echo '<p class="flxk-warn">' . esc_html( sprintf( __( 'Missing: %s. These parts are skipped.', 'flx-setup' ), implode( ', ', $req ) ) ) . '</p>';
			if ( flxk_inactive_plugins( (array) ( $p['requires'] ?? array() ) ) && current_user_can( 'install_plugins' ) ) {
				echo '<div class="flxk-actions">';
				flxk_form_open( 'requirements', array( 'preset' => $id ) );
				echo '<button class="button">' . esc_html__( 'Install and activate missing plugins', 'flx-setup' ) . '</button></form></div>';
			}
		}
		echo '<div class="flxk-actions">';
		if ( ! $st ) {
			printf( '<a class="button button-primary" href="%1$s">%2$s</a> ', esc_url( flxk_admin_url( array( 'preview' => $id ) ) ), esc_html__( 'Preview and apply', 'flx-setup' ) );
			printf( '<a class="button" href="%1$s">%2$s</a>', esc_url( flxk_admin_url( array( 'preview' => $id, 'mode' => 'fill' ) ) ), esc_html__( 'Fill empty values only', 'flx-setup' ) );
		} else {
			printf( '<a class="button" href="%1$s">%2$s</a> ', esc_url( flxk_admin_url( array( 'preview' => $id ) ) ), esc_html__( 'Compare again', 'flx-setup' ) );
			flxk_form_open( 'remove', array( 'preset' => $id ) );
			echo '<button class="button-link flxk-danger" data-flx-confirm-danger data-flx-confirm="' . esc_attr( __( 'Remove the preset? Settings it wrote go back to their old values (your manual changes stay), pages it created become drafts.', 'flx-setup' ) ) . '">' . esc_html__( 'Remove preset', 'flx-setup' ) . '</button></form>';
		}
		echo '</div>';

		$pending = flxk_pending_migrations( $id );
		if ( $st && $pending ) {
			echo '<h3>' . esc_html__( 'Pending updates', 'flx-setup' ) . '</h3><table class="widefat striped flxk-table"><tbody>';
			foreach ( $pending as $m ) {
				echo '<tr><td><strong>' . esc_html( $m['title'] ?? $m['id'] ) . '</strong>' . ( ! empty( $m['desc'] ) ? '<br><span class="flxk-muted">' . esc_html( $m['desc'] ) . '</span>' : '' ) . '</td><td class="flxk-right">';
				printf( '<a class="button button-small" href="%1$s">%2$s</a> ', esc_url( flxk_admin_url( array( 'preview' => $id, 'migration' => $m['id'] ) ) ), esc_html__( 'Preview', 'flx-setup' ) );
				flxk_form_open(
					'skip',
					array(
						'preset'    => $id,
						'migration' => $m['id'],
					)
				);
				echo '<button class="button-link">' . esc_html__( 'Skip', 'flx-setup' ) . '</button></form></td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</section>';
	}

	$patches = flxk_pending_patches();
	echo '<section class="flxk-card" id="yamalar"><header class="flxk-card__head"><h2>' . esc_html__( 'Patches awaiting approval', 'flx-setup' ) . '</h2></header>';
	if ( ! $patches ) {
		echo '<p class="flxk-muted">' . esc_html__( 'Change proposals from the Flux AI chat or an external MCP client wait here for approval.', 'flx-setup' ) . '</p>';
	} else {
		echo '<table class="widefat striped flxk-table"><tbody>';
		foreach ( $patches as $pid => $pp ) {
			$u = get_userdata( (int) $pp['user'] );
			echo '<tr><td><strong>' . esc_html( $pp['note'] ? $pp['note'] : $pid ) . '</strong><br><span class="flxk-muted">' . esc_html( strtoupper( $pp['source'] ) . ' · ' . ( $u ? $u->display_name : '' ) . ' · ' . $pp['at'] ) . '</span></td><td class="flxk-right">';
			printf( '<a class="button button-small button-primary" href="%1$s">%2$s</a> ', esc_url( flxk_admin_url( array( 'patch' => $pid ) ) ), esc_html__( 'Preview', 'flx-setup' ) );
			flxk_form_open( 'patch_reject', array( 'patch' => $pid ) );
			echo '<button class="button-link flxk-danger">' . esc_html__( 'Reject', 'flx-setup' ) . '</button></form></td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</section>';
}

/** Ön ayarın görünen adı. */
function flxk_preset_label( $id ) {
	if ( FLXK_SITE === $id ) {
		return __( 'Site patches', 'flx-setup' );
	}
	$p = flxk_get_preset( $id );
	return $p ? (string) $p['name'] : (string) $id;
}

/** Kurulu olmayan gerekli tema/eklentiler. */
function flxk_requirements_missing( array $p ) {
	$miss = array();
	$req  = (array) ( $p['requires'] ?? array() );
	foreach ( (array) ( $req['plugins'] ?? array() ) as $r ) {
		$slug   = preg_replace( '/[<>=].*$/', '', (string) $r );
		$active = false;
		foreach ( (array) get_option( 'active_plugins', array() ) as $b ) {
			if ( dirname( $b ) === $slug ) {
				$active = true;
			}
		}
		if ( ! $active ) {
			$miss[] = $slug;
		}
	}
	if ( ! empty( $req['theme'] ) && get_stylesheet() !== $req['theme'] && get_template() !== $req['theme'] ) {
		/* translators: %s: tema */
		$miss[] = sprintf( __( 'theme %s', 'flx-setup' ), $req['theme'] );
	}
	return $miss;
}

function flxk_status_label( $s ) {
	$l = array(
		'add'    => __( 'Will be added', 'flx-setup' ),
		'change' => __( 'Will change', 'flx-setup' ),
		'same'   => __( 'Same', 'flx-setup' ),
		'keep'   => __( 'Filled, kept', 'flx-setup' ),
		'manual' => __( 'Changed manually, skipped', 'flx-setup' ),
	);
	return $l[ $s ] ?? $s;
}

function flxk_section_label( $s ) {
	$l = array(
		'profile'  => __( 'Organisation profile', 'flx-setup' ),
		'media'    => __( 'Media', 'flx-setup' ),
		'content'  => __( 'Content and theme', 'flx-setup' ),
		'wp'       => __( 'WordPress settings', 'flx-setup' ),
		'settings' => __( 'Plugin settings', 'flx-setup' ),
	);
	return $l[ $s ] ?? $s;
}

/** Önizleme ekranı: ön ayar, migration ya da yama. */
function flxk_render_preview( $id, $mode, $mig, $patch_id ) {
	if ( $patch_id ) {
		$pp = flxk_patches()[ $patch_id ] ?? null;
		if ( ! $pp ) {
			echo '<p>' . esc_html__( 'Patch not found.', 'flx-setup' ) . '</p>';
			return;
		}
		$title = $pp['note'] ? $pp['note'] : __( 'Patch', 'flx-setup' );
		$plan  = flxk_plan( FLXK_SITE, $pp['data'], array( 'mode' => 'apply' ) );
	} else {
		$p = flxk_get_preset( $id );
		if ( ! $p ) {
			echo '<p>' . esc_html__( 'Preset not found.', 'flx-setup' ) . '</p>';
			return;
		}
		if ( $mig ) {
			$m     = flxk_migration( $id, $mig );
			$title = $p['name'] . ' · ' . ( $m['title'] ?? $mig );
			$plan  = $m ? flxk_plan( $id, $m, array( 'mode' => 'apply' ) ) : array(
				'ops'      => array(),
				'warnings' => array(),
			);
		} else {
			$title = $p['name'];
			$plan  = flxk_plan( $id, flxk_base_of( $p ), array( 'mode' => $mode ) );
		}
	}
	$counts = flxk_plan_counts( $plan );
	printf( '<p><a href="%1$s">← %2$s</a></p>', esc_url( flxk_admin_url() ), esc_html__( 'Presets', 'flx-setup' ) );
	echo '<section class="flxk-card"><header class="flxk-card__head"><h2>' . esc_html( $title ) . '</h2>';
	if ( ! $patch_id && ! $mig ) {
		echo '<span class="flxk-badge">' . esc_html( 'fill' === $mode ? __( 'Fill empty values only', 'flx-setup' ) : __( 'Apply', 'flx-setup' ) ) . '</span>';
	}
	echo '</header><p class="flxk-counts">';
	foreach ( array( 'add', 'change', 'manual', 'keep', 'same' ) as $s ) {
		if ( ! empty( $counts[ $s ] ) ) {
			echo '<span class="flxk-st flxk-st--' . esc_attr( $s ) . '">' . esc_html( flxk_status_label( $s ) ) . ': <b>' . (int) $counts[ $s ] . '</b></span> ';
		}
	}
	echo '</p>';
	foreach ( $plan['warnings'] as $w ) {
		echo '<p class="flxk-warn">' . esc_html( $w ) . '</p>';
	}

	if ( $patch_id ) {
		flxk_form_open( 'patch_apply', array( 'patch' => $patch_id ) );
	} elseif ( $mig ) {
		flxk_form_open(
			'migrate',
			array(
				'preset'    => $id,
				'migration' => $mig,
			)
		);
	} else {
		flxk_form_open(
			'apply',
			array(
				'preset' => $id,
				'mode'   => $mode,
			)
		);
	}
	$n = (int) ( $counts['add'] ?? 0 ) + (int) ( $counts['change'] ?? 0 );
	echo '<input type="hidden" name="selection_present" value="1"><div class="flxk-selection">';
	echo '<label><input type="checkbox" data-flxk-all="selected" checked> ' . esc_html__( 'Select all changes', 'flx-setup' ) . '</label>';
	if ( ! empty( $counts['manual'] ) || ! empty( $counts['keep'] ) ) {
		echo '<label><input type="checkbox" data-flxk-all="force"> ' . esc_html__( 'Select all protected values (overwrite)', 'flx-setup' ) . '</label>';
	}
	flxk_selection_button( $n );
	echo '</div>';
	$by = array();
	foreach ( $plan['ops'] as $op ) {
		$by[ $op['section'] ][] = $op;
	}
	$show_same = ! empty( $_GET['same'] ); // phpcs:ignore WordPress.Security.NonceVerification
	foreach ( $by as $sec => $ops ) {
		$visible = array_filter(
			$ops,
			function ( $o ) use ( $show_same ) {
				return $show_same || 'same' !== $o['status'];
			}
		);
		if ( ! $visible ) {
			continue;
		}
		echo '<h3>' . esc_html( flxk_section_label( $sec ) ) . '</h3><table class="widefat striped flxk-table flxk-diff"><thead><tr><th>' . esc_html__( 'What', 'flx-setup' ) . '</th><th>' . esc_html__( 'Now', 'flx-setup' ) . '</th><th>' . esc_html( $patch_id ? __( 'Patch', 'flx-setup' ) : __( 'Preset', 'flx-setup' ) ) . '</th><th>' . esc_html__( 'Status', 'flx-setup' ) . '</th></tr></thead><tbody>';
		foreach ( $visible as $op ) {
			$what = 'content' === $sec ? $op['label'] . ': ' . $op['key'] : preg_replace( '/^(profile|wp|option|media):/', '', $op['target'] );
			$old  = 'content' === $sec ? ( $op['id'] ? '#' . $op['id'] : '—' ) : flxk_preview_value( $op['old'] );
			$new  = 'content' === $sec ? flxk_preview_value( $op['new']['title'] ?? ( $op['new']['name'] ?? '' ), 80 ) : flxk_preview_value( $op['new'] );
			echo '<tr><td>';
			if ( in_array( $op['status'], array( 'add', 'change' ), true ) ) {
				/* translators: %s: preset target */
				echo '<input type="checkbox" name="selected[]" data-flxk-select="selected" checked value="' . esc_attr( $op['target'] ) . '" aria-label="' . esc_attr( sprintf( __( 'Select %s', 'flx-setup' ), $what ) ) . '"> ';
			}
			echo '<code>' . esc_html( $what ) . '</code></td><td data-label="' . esc_attr__( 'Now', 'flx-setup' ) . '">' . esc_html( $old ) . '</td><td data-label="' . esc_attr( $patch_id ? __( 'Patch', 'flx-setup' ) : __( 'Preset', 'flx-setup' ) ) . '">' . esc_html( $new ) . '</td><td><span class="flxk-st flxk-st--' . esc_attr( $op['status'] ) . '">' . esc_html( flxk_status_label( $op['status'] ) ) . '</span>';
			if ( ! empty( $op['conflict'] ) ) {
				$other = flxk_preset_label( $op['conflict'] );
				if ( $patch_id ) {
					/* translators: %s: ön ayar adı */
					$msg = sprintf( __( 'Value of the %s preset. Once applied, that preset\'s updates will not overwrite it.', 'flx-setup' ), $other );
				} else {
					/* translators: %s: ön ayar adı */
					$msg = sprintf( __( 'Conflict: %s also writes this value; the last one applied wins.', 'flx-setup' ), $other );
				}
				echo '<br><span class="flxk-muted">' . esc_html( $msg ) . '</span>';
			}
			if ( in_array( $op['status'], array( 'manual', 'keep' ), true ) ) {
				echo '<br><label class="flxk-muted"><input type="checkbox" name="force[]" data-flxk-select="force" value="' . esc_attr( $op['target'] ) . '"> ' . esc_html__( 'Apply anyway', 'flx-setup' ) . '</label>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}
	$n = (int) ( $counts['add'] ?? 0 ) + (int) ( $counts['change'] ?? 0 );
	echo '<p class="flxk-actions">';
	/* translators: %d: sayı */
	flxk_selection_button( $n );
	if ( ! $show_same ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		printf( '<a href="%1$s">%2$s</a>', esc_url( add_query_arg( 'same', 1 ) ), esc_html__( 'Show unchanged rows too', 'flx-setup' ) );
	}
	echo '</p></form></section>';
}

function flxk_selection_button( $count ) {
	/* translators: %d: selected change count */
	$label = __( 'Apply selected changes (%d)', 'flx-setup' );
	echo '<button class="button button-primary" data-flxk-apply data-label="' . esc_attr( $label ) . '" ' . disabled( 0, $count, false ) . '>' . esc_html( sprintf( $label, $count ) ) . '</button> ';
}

function flxk_render_profile() {
	$p = flxk_profile_all();
	echo '<section class="flxk-card"><p class="flxk-muted">' . esc_html__( 'Organisation identity. Plugins read the name, signature, WhatsApp, IBAN and similar details from here. The preset fills it on first install; preset updates never overwrite values you change here.', 'flx-setup' ) . '</p>';
	flxk_form_open( 'profile' );
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( flxk_profile_fields() as $k => $label ) {
		$v = $p[ $k ] ?? '';
		echo '<tr><th><label for="flxk-p-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( in_array( $k, array( 'colors', 'social' ), true ) ) {
			$lines = array();
			foreach ( (array) $v as $a => $b ) {
				$lines[] = $a . '=' . $b;
			}
			echo '<textarea id="flxk-p-' . esc_attr( $k ) . '" name="profile[' . esc_attr( $k ) . ']" rows="3" class="large-text code" placeholder="' . esc_attr( 'colors' === $k ? 'primary=#1F2937' : 'instagram=https://instagram.com/…' ) . '">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
		} elseif ( in_array( $k, array( 'about', 'address' ), true ) ) {
			echo '<textarea id="flxk-p-' . esc_attr( $k ) . '" name="profile[' . esc_attr( $k ) . ']" rows="2" class="large-text">' . esc_textarea( (string) $v ) . '</textarea>';
		} else {
			echo '<input id="flxk-p-' . esc_attr( $k ) . '" type="text" class="regular-text" name="profile[' . esc_attr( $k ) . ']" value="' . esc_attr( is_scalar( $v ) ? (string) $v : '' ) . '">';
			if ( in_array( $k, array( 'logo', 'logo_light' ), true ) && $v && wp_get_attachment_url( (int) $v ) ) {
				echo ' <img src="' . esc_url( wp_get_attachment_url( (int) $v ) ) . '" alt="" class="flxk-logo">';
			}
		}
		echo '</td></tr>';
	}
	echo '</tbody></table><p><button class="button button-primary">' . esc_html__( 'Save', 'flx-setup' ) . '</button></p></form></section>';
}

function flxk_render_history() {
	$h = flxk_history();
	echo '<section class="flxk-card">';
	if ( ! $h ) {
		echo '<p class="flxk-muted">' . esc_html__( 'Nothing applied yet.', 'flx-setup' ) . '</p></section>';
		return;
	}
	$kinds = array(
		'base'      => __( 'Preset', 'flx-setup' ),
		'migration' => __( 'Update', 'flx-setup' ),
		'sync'      => __( 'Sync', 'flx-setup' ),
		'patch'     => __( 'Patch', 'flx-setup' ),
		'revert'    => __( 'Undo', 'flx-setup' ),
		'remove'    => __( 'Removal', 'flx-setup' ),
		'skip'      => __( 'Skipped', 'flx-setup' ),
		'profile'   => __( 'Profile', 'flx-setup' ),
	);
	echo '<table class="widefat striped flxk-table"><thead><tr><th>' . esc_html__( 'Time', 'flx-setup' ) . '</th><th>' . esc_html__( 'Action', 'flx-setup' ) . '</th><th>' . esc_html__( 'Source', 'flx-setup' ) . '</th><th>' . esc_html__( 'Result', 'flx-setup' ) . '</th><th></th></tr></thead><tbody>';
	foreach ( array_slice( $h, 0, 100 ) as $e ) {
		$u   = get_userdata( (int) ( $e['user'] ?? 0 ) );
		$res = '';
		foreach ( (array) ( $e['counts'] ?? array() ) as $k => $n ) {
			$res .= flxk_status_label( $k ) . ' ' . (int) $n . ' · ';
		}
		echo '<tr><td>' . esc_html( $e['at'] ) . '</td><td><strong>' . esc_html( $kinds[ $e['kind'] ] ?? $e['kind'] ) . '</strong> ' . esc_html( $e['title'] ?? '' ) . '<br><span class="flxk-muted">' . esc_html( flxk_preset_label( $e['preset'] ?? '' ) ) . '</span></td><td>' . esc_html( strtoupper( $e['source'] ?? 'panel' ) . ( $u ? ' · ' . $u->display_name : '' ) ) . '</td><td>' . esc_html( rtrim( $res, ' ·' ) ) . '</td><td class="flxk-right">';
		if ( ! empty( $e['run'] ) && in_array( $e['kind'], array( 'base', 'migration', 'sync', 'patch' ), true ) && flxk_run( $e['run'] ) ) {
			flxk_form_open( 'revert', array( 'run' => $e['run'] ) );
			echo '<button class="button button-small" data-flx-confirm="' . esc_attr( __( 'Undo this change?', 'flx-setup' ) ) . '">' . esc_html__( 'Undo', 'flx-setup' ) . '</button></form>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table></section>';
}

function flxk_render_tools() {
	$s = flxk_settings();
	echo '<section class="flxk-card"><header class="flxk-card__head"><h2>' . esc_html__( 'Export this site as a preset', 'flx-setup' ) . '</h2></header>';
	echo '<p class="flxk-muted">' . esc_html__( 'Downloads the site\'s current state as a flx-preset/1 JSON file. Secrets such as passwords, API keys and tokens are never written to the file.', 'flx-setup' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxk_export">';
	wp_nonce_field( 'flxk_export' );
	foreach ( array(
		'profile'  => __( 'Organisation profile', 'flx-setup' ),
		'wp'       => __( 'WordPress settings', 'flx-setup' ),
		'settings' => __( 'Plugin settings', 'flx-setup' ),
		'theme'    => __( 'Styles, templates and parts', 'flx-setup' ),
		'content'  => __( 'Menus', 'flx-setup' ),
		'media'    => __( 'Logo and site icon', 'flx-setup' ),
	) as $k => $l ) {
		echo '<label class="flxk-check"><input type="checkbox" name="sections[]" value="' . esc_attr( $k ) . '" checked> ' . esc_html( $l ) . '</label> ';
	}
	echo '<label class="flxk-check"><input type="checkbox" name="pages" value="1"> ' . esc_html__( 'Pages', 'flx-setup' ) . '</label>';
	echo '<p><button class="button button-primary">' . esc_html__( 'Download JSON', 'flx-setup' ) . '</button></p></form></section>';

	echo '<section class="flxk-card"><header class="flxk-card__head"><h2>' . esc_html__( 'Import preset', 'flx-setup' ) . '</h2></header>';
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="flxk"><input type="hidden" name="do" value="import">';
	wp_nonce_field( 'flxk' );
	echo '<p><input type="file" name="preset" accept=".json,application/json"></p>';
	echo '<p><textarea name="json" rows="5" class="large-text code" placeholder="{ &quot;format&quot;: &quot;flx-preset/1&quot;, &quot;id&quot;: &quot;…&quot;, … }"></textarea></p>';
	echo '<p><button class="button">' . esc_html__( 'Import', 'flx-setup' ) . '</button> <span class="flxk-muted">' . esc_html__( 'An imported preset is not applied; preview and apply it in the Presets tab.', 'flx-setup' ) . '</span></p></form></section>';

	echo '<section class="flxk-card"><header class="flxk-card__head"><h2>' . esc_html__( 'AI and MCP', 'flx-setup' ) . '</h2></header>';
	echo '<p class="flxk-muted">' . esc_html__( 'The Flux AI chat and Flux MCP tools (preset_schema, preset_export, preset_patch_preview, preset_patch_apply, preset_revert, preset_history) edit the site through patches. In the chat every patch asks for approval before it is applied.', 'flx-setup' ) . '</p>';
	flxk_form_open( 'settings' );
	echo '<label class="flxk-check"><input type="checkbox" name="mcp_direct" value="1"' . checked( $s['mcp_direct'], 1, false ) . '> ' . esc_html__( 'Apply patches from external MCP clients without waiting for approval', 'flx-setup' ) . '</label>';
	echo '<p><button class="button">' . esc_html__( 'Save', 'flx-setup' ) . '</button></p></form></section>';
}
