<?php
/**
 * Yönetim ekranları: Genel bakış (raporlar), Zamanlanmış görevler, Kayıtlar, Bağlı sistemler, Faturalandırma, Araçlar, MCP, Ayarlar.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'flxai_admin_menu' );

function flxai_admin_menu() {
	$waiting = current_user_can( 'manage_options' ) ? flxai_approvals_count() + count( flxai_tasks_unread() ) : 0;
	add_menu_page( 'Flux AI & MCP', 'Flux AI & MCP' . ( $waiting ? ' <span class="awaiting-mod count-' . (int) $waiting . '"><span class="pending-count">' . esc_html( number_format_i18n( $waiting ) ) . '</span></span>' : '' ), 'manage_options', 'flx-ai', 'flxai_page_overview', 'none', 59 );
	add_submenu_page( 'flx-ai', __( 'Overview', 'flx-ai' ), __( 'Overview', 'flx-ai' ), 'manage_options', 'flx-ai', 'flxai_page_overview' );
	add_submenu_page( 'flx-ai', __( 'Chat', 'flx-ai' ), __( 'Chat', 'flx-ai' ), 'manage_options', 'flx-ai-chat', 'flxai_page_chat' );
	$bubble = function ( $n ) {
		return $n ? ' <span class="awaiting-mod count-' . (int) $n . '"><span class="pending-count">' . esc_html( number_format_i18n( $n ) ) . '</span></span>' : '';
	};
	add_submenu_page( 'flx-ai', __( 'Scheduled tasks', 'flx-ai' ), __( 'Scheduled tasks', 'flx-ai' ) . $bubble( count( flxai_tasks_unread() ) ), 'manage_options', 'flx-ai-tasks', 'flxai_page_tasks' );
	add_submenu_page( 'flx-ai', __( 'Awaiting approval', 'flx-ai' ), __( 'Awaiting approval', 'flx-ai' ) . $bubble( flxai_approvals_count() ), 'manage_options', 'flx-ai-approvals', 'flxai_page_approvals' );
	add_submenu_page( 'flx-ai', __( 'Skills', 'flx-ai' ), __( 'Skills', 'flx-ai' ), 'manage_options', 'flx-ai-skills', 'flxai_page_skills' );
	add_submenu_page( 'flx-ai', __( 'Logs', 'flx-ai' ), __( 'Logs', 'flx-ai' ), 'manage_options', 'flx-ai-log', 'flxai_page_log' );
	add_submenu_page( 'flx-ai', __( 'Connected systems', 'flx-ai' ), __( 'Connected systems', 'flx-ai' ), 'manage_options', 'flx-ai-systems', 'flxai_page_systems' );
	add_submenu_page( 'flx-ai', __( 'Billing', 'flx-ai' ), __( 'Billing', 'flx-ai' ), 'manage_options', 'flx-ai-billing', 'flxai_page_billing' );
	add_submenu_page( 'flx-ai', __( 'Tools', 'flx-ai' ), __( 'Tools', 'flx-ai' ), 'manage_options', 'flx-ai-tools', 'flxai_page_tools' );
	add_submenu_page( 'flx-ai', 'MCP', 'MCP', 'manage_options', 'flx-ai-mcp', 'flxai_page_mcp' );
	add_submenu_page( 'flx-ai', __( 'Settings', 'flx-ai' ), __( 'Settings', 'flx-ai' ), 'manage_options', 'flx-ai-settings', 'flxai_page_settings' );
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

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( false === strpos( (string) $hook, 'flx-ai' ) ) {
			return;
		}
		wp_enqueue_style( 'flxai-admin', FLXAI_URL . 'assets/css/admin.css', array(), FLXAI_VERSION );
		wp_enqueue_script( 'flxai-admin', FLXAI_URL . 'assets/js/admin.js', array(), FLXAI_VERSION, true );
		wp_localize_script(
			'flxai-admin',
			'FLXAI_ADMIN',
			array(
				'rest'   => esc_url_raw( rest_url( 'flx-ai/v1/' ) ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'locale' => str_replace( '_', '-', determine_locale() ),
				'i18n'   => flxai_js_strings(),
			)
		);
		if ( false !== strpos( (string) $hook, 'flx-ai-settings' ) ) {
			flxai_enqueue_model_picker();
		}
	}
);

/** Uygulama ekranı listesine (Flux Admin) eklenmez; normal wp-admin sayfaları. */
function flxai_page_head( $title, $sub = '' ) {
	echo '<div class="wrap flxai"><div class="flxai-head"><img src="' . esc_url( FLXAI_URL . 'assets/icon.svg' ) . '" alt="" width="36" height="36"><div><h1>' . esc_html( $title ) . '</h1>';
	if ( '' !== $sub ) {
		echo '<p class="flxai-sub">' . esc_html( $sub ) . '</p>';
	}
	echo '</div></div>';
}

function flxai_kpi( $label, $value, $sub = '', $pct = null, $over = false ) {
	echo '<div class="flxai-kpi' . ( $over ? ' is-over' : '' ) . '"><span class="flxai-kpi-l">' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong>';
	if ( null !== $pct ) {
		echo '<span class="flxai-meter" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( min( 100, $pct ) ) . '"><span style="width:' . esc_attr( min( 100, $pct ) ) . '%"></span></span>';
	}
	if ( '' !== $sub ) {
		echo '<span class="flxai-kpi-s">' . esc_html( $sub ) . '</span>';
	}
	echo '</div>';
}

/** Sınır kartları (bugün, hafta, ay). */
function flxai_budget_cards() {
	echo '<div class="flxai-kpis">';
	foreach ( flxai_budget_status() as $row ) {
		$sub = $row['limit'] > 0
			/* translators: 1: limit, 2: reset date */
			? sprintf( __( 'Limit %1$s · resets %2$s', 'flx-ai' ), flxai_usd( $row['limit'], 2 ), flx_date( $row['resets'], 'j M H:i' ) )
			: __( 'No limit', 'flx-ai' );
		flxai_kpi( $row['label'], flxai_usd( $row['spent'] ), $sub, $row['limit'] > 0 ? $row['pct'] : null, $row['over'] );
	}
	echo '</div>';
}

/* ---------------------------------------------------------------------------
 * Genel bakış
 * ------------------------------------------------------------------------- */

function flxai_page_overview() {
	if ( 'log' === sanitize_key( $_GET['tab'] ?? '' ) ) { // phpcs:ignore -- eski bağlantılar
		flxai_page_log();
		return;
	}
	flxai_page_head( 'Flux AI', __( 'AI usage across all plugins: cost, time, cache and tools.', 'flx-ai' ) );
	flxai_home_composer();
	$days   = (int) ( $_GET['days'] ?? 30 ); // phpcs:ignore
	$days   = in_array( $days, array( 7, 14, 30, 90, 180 ), true ) ? $days : 30;
	$source = sanitize_key( $_GET['source'] ?? '' ); // phpcs:ignore
	$rep    = flxai_report( $days, $source );
	$t      = $rep['totals'];

	if ( ! flxai_providers_ready() ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'No API key entered yet.', 'flx-ai' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=flx-ai-settings' ) ) . '">' . esc_html__( 'Settings', 'flx-ai' ) . '</a></p></div>';
	}
	flxai_budget_cards();

	// Süzgeçler tek satırda.
	echo '<form class="flxai-filters" method="get"><input type="hidden" name="page" value="flx-ai"><div class="flxai-seg">';
	foreach ( array( 7, 30, 90, 180 ) as $d ) {
		/* translators: %d: days */
		printf( '<button type="submit" name="days" value="%1$d" class="%2$s">%3$s</button>', (int) $d, $d === $days ? 'is-on' : '', esc_html( sprintf( __( '%d days', 'flx-ai' ), $d ) ) );
	}
	echo '</div><label><span class="screen-reader-text">' . esc_html__( 'Source', 'flx-ai' ) . '</span><select name="source" onchange="this.form.submit()"><option value="">' . esc_html__( 'All plugins', 'flx-ai' ) . '</option>';
	foreach ( flxai_sources() as $id => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $id ), selected( $source, $id, false ), esc_html( $label ) );
	}
	echo '</select></label><input type="hidden" name="days" value="' . esc_attr( $days ) . '"></form>';

	echo '<div class="flxai-kpis">';
	flxai_kpi( __( 'Cost', 'flx-ai' ), flxai_usd( $t['cost'] ), sprintf( /* translators: %s: amount */ __( 'Cache savings %s', 'flx-ai' ), flxai_usd( $t['saved'] ) ) );
	flxai_kpi( __( 'Model requests', 'flx-ai' ), number_format_i18n( $t['calls'] ), sprintf( /* translators: %s: count */ __( '%s tool calls', 'flx-ai' ), number_format_i18n( $t['tools'] ) ) );
	flxai_kpi( __( 'Tokens', 'flx-ai' ), number_format_i18n( $t['in_tok'] + $t['out_tok'] ), sprintf( /* translators: 1: in, 2: out, 3: cached */ __( 'Input %1$s · output %2$s · from cache %3$s', 'flx-ai' ), number_format_i18n( $t['in_tok'] ), number_format_i18n( $t['out_tok'] ), number_format_i18n( $t['cr'] ) ) );
	$tot_ms = max( 1, $t['model_ms'] + $t['tool_ms'] );
	flxai_kpi( __( 'Total time', 'flx-ai' ), flxai_ms( $tot_ms ), sprintf( /* translators: 1: percent model, 2: percent tools */ __( 'Model/thinking %1$s%% · tools %2$s%%', 'flx-ai' ), number_format_i18n( $t['model_ms'] / $tot_ms * 100, 0 ), number_format_i18n( $t['tool_ms'] / $tot_ms * 100, 0 ) ) );
	flxai_kpi( __( 'Errors', 'flx-ai' ), number_format_i18n( $t['errors'] ), $t['calls'] ? sprintf( /* translators: %s: percent */ __( '%s%% of requests', 'flx-ai' ), number_format_i18n( $t['errors'] / max( 1, $t['calls'] + $t['tools'] ) * 100, 1 ) ) : '' );
	echo '</div>';

	$chart = array(
		'daily' => $rep['daily'],
		'tools' => array_map(
			function ( $r ) {
				return array( 'label' => $r['label'], 'avg' => (int) $r['avg_ms'], 'max' => (int) $r['max_ms'], 'n' => (int) $r['n'], 'err' => (int) $r['errors'] );
			},
			array_slice( $rep['by_tool'], 0, 16 )
		),
	);
	echo '<div class="flxai-grid">';
	echo '<section class="flxai-card flxai-span2"><h2>' . esc_html__( 'Daily cost (USD)', 'flx-ai' ) . '</h2><div class="flxai-chart" data-chart="cost"></div></section>';
	echo '<section class="flxai-card flxai-span2"><h2>' . esc_html__( 'Where time goes: model/thinking vs tools', 'flx-ai' ) . '</h2><div class="flxai-chart" data-chart="time"></div></section>';
	echo '<section class="flxai-card"><h2>' . esc_html__( 'Daily tokens', 'flx-ai' ) . '</h2><div class="flxai-chart" data-chart="tokens"></div></section>';
	echo '<section class="flxai-card"><h2>' . esc_html__( 'Average time per tool', 'flx-ai' ) . '</h2><div class="flxai-chart" data-chart="tools"></div></section>';
	echo '</div>';
	echo '<script type="application/json" id="flxai-chart-data">' . wp_json_encode( $chart ) . '</script>';

	echo '<div class="flxai-grid">';
	flxai_breakdown_table( __( 'By plugin', 'flx-ai' ), $rep['by_source'] );
	flxai_breakdown_table( __( 'By model', 'flx-ai' ), $rep['by_model'] );
	echo '</div>';
	flxai_breakdown_table( __( 'By purpose', 'flx-ai' ), $rep['by_purpose'] );
	echo '</div>';
}

/** Döküm tablosu (eklenti/model/amaç). */
function flxai_breakdown_table( $title, $rows, $raw_label = false ) {
	echo '<section class="flxai-card"><h2>' . esc_html( $title ) . '</h2>';
	if ( ! $rows ) {
		echo '<p class="flxai-empty">' . esc_html__( 'No records in this range.', 'flx-ai' ) . '</p></section>';
		return;
	}
	echo '<div class="flxai-tscroll"><table class="flxai-table"><thead><tr><th>' . esc_html__( 'Name', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Requests', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Tokens', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Model time', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Tool time', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Cost', 'flx-ai' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$label = $raw_label ? (string) $r['k'] : (string) ( $r['label'] ?? $r['k'] );
		printf(
			'<tr><td>%1$s%7$s</td><td class="num">%2$s</td><td class="num">%3$s</td><td class="num">%4$s</td><td class="num">%5$s</td><td class="num"><strong>%6$s</strong></td></tr>',
			esc_html( '' !== $label ? $label : '—' ),
			esc_html( number_format_i18n( (int) $r['calls'] ) . ( (int) $r['tools'] ? ' + ' . number_format_i18n( (int) $r['tools'] ) : '' ) ),
			esc_html( number_format_i18n( (int) $r['in_tok'] + (int) $r['out_tok'] ) ),
			esc_html( flxai_ms( (int) $r['model_ms'] ) ),
			esc_html( flxai_ms( (int) $r['tool_ms'] ) ),
			esc_html( flxai_usd( (float) $r['cost'] ) ),
			(int) $r['errors'] ? ' <span class="flxai-pill is-err">' . esc_html( sprintf( /* translators: %s: count */ __( '%s errors', 'flx-ai' ), number_format_i18n( (int) $r['errors'] ) ) ) . '</span>' : ''
		);
	}
	echo '</tbody></table></div></section>';
}

/* ---------------------------------------------------------------------------
 * Kayıtlar
 * ------------------------------------------------------------------------- */

function flxai_page_log() {
	global $wpdb;
	flxai_page_head( __( 'Logs', 'flx-ai' ), __( 'Every model request and tool call: which system, for whom, which model, tokens, time and cost. Content is stored with personal data masked.', 'flx-ai' ) );
	$kind   = sanitize_key( $_GET['kind'] ?? '' ); // phpcs:ignore
	$source = sanitize_key( $_GET['source'] ?? '' ); // phpcs:ignore
	$errs   = ! empty( $_GET['errors'] ); // phpcs:ignore
	$days   = (int) ( $_GET['days'] ?? 7 ); // phpcs:ignore
	$days   = in_array( $days, array( 1, 7, 30, 90, 365 ), true ) ? $days : 7;
	$pg     = max( 1, (int) ( $_GET['pg'] ?? 1 ) ); // phpcs:ignore
	$lib    = function_exists( 'flxa_list_form_attrs' ) && function_exists( 'flxa_list_per_page' ) && function_exists( 'flxa_list_pagination' );
	$per    = $lib ? flxa_list_per_page( 'flxai-log', 50 ) : 100;
	$t      = flxai_table( 'log' );
	$b      = flxai_table( 'log_body' );
	$where  = $wpdb->prepare( 'l.ts >= %d', time() - $days * DAY_IN_SECONDS );
	if ( in_array( $kind, array( 'model', 'tool' ), true ) ) {
		$where .= $wpdb->prepare( ' AND l.kind = %s', $kind );
	}
	if ( '' !== $source ) {
		$where .= $wpdb->prepare( ' AND l.source = %s', $source );
	}
	if ( $errs ) {
		$where .= ' AND l.ok = 0';
	}
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} l WHERE {$where}" ); // phpcs:ignore
	$rows  = $wpdb->get_results( "SELECT l.*, (b.log_id IS NOT NULL) AS has_body FROM {$t} l LEFT JOIN {$b} b ON b.log_id = l.id WHERE {$where} ORDER BY l.id DESC LIMIT " . (int) $per . ' OFFSET ' . (int) ( ( $pg - 1 ) * $per ), ARRAY_A ); // phpcs:ignore

	$sources = flxai_sources();
	foreach ( flxai_systems() as $sys ) {
		$sources[ $sys['source'] ] = $sys['label'];
	}
	$kind_sel = '<select name="kind"%s><option value="">' . esc_html__( 'All', 'flx-ai' ) . '</option><option value="model"' . selected( $kind, 'model', false ) . '>' . esc_html__( 'Model requests', 'flx-ai' ) . '</option><option value="tool"' . selected( $kind, 'tool', false ) . '>' . esc_html__( 'Tool calls', 'flx-ai' ) . '</option></select>';
	$src_sel  = '<select name="source"%s><option value="">' . esc_html__( 'All systems', 'flx-ai' ) . '</option>';
	foreach ( $sources as $id => $label ) {
		$src_sel .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $id ), selected( $source, $id, false ), esc_html( $label ) );
	}
	$src_sel .= '</select>';
	$err_chk  = '<label class="flxai-check"%s><input type="checkbox" name="errors" value="1"' . checked( $errs, true, false ) . '%s> ' . esc_html__( 'Errors only', 'flx-ai' ) . '</label>';
	if ( $lib ) {
		// Flux Admin ortak liste çubuğu: dönem + tür görünür, diğerleri "Filtreler" panelinde.
		echo '<form class="flxai-filters flxai-filters-lib" method="get"' . flxa_list_form_attrs( array( 'key' => 'flxai-log' ) ) . '><input type="hidden" name="page" value="flx-ai-log">'; // phpcs:ignore
		echo '<select name="days" data-flxa-primary>';
		foreach ( array( 1, 7, 30, 90, 365 ) as $d ) {
			/* translators: %d: days */
			printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $d, selected( $days, $d, false ), esc_html( 1 === $d ? __( '24 hours', 'flx-ai' ) : sprintf( __( '%d days', 'flx-ai' ), $d ) ) );
		}
		echo '</select>';
		printf( $kind_sel, ' data-flxa-primary' ); // phpcs:ignore -- escaped above
		printf( $src_sel, '' ); // phpcs:ignore
		printf( $err_chk, '', '' ); // phpcs:ignore
		echo '<button class="button">' . esc_html__( 'Filter', 'flx-ai' ) . '</button></form>';
	} else {
		echo '<form class="flxai-filters" method="get"><input type="hidden" name="page" value="flx-ai-log"><div class="flxai-seg">';
		foreach ( array( 1, 7, 30, 90 ) as $d ) {
			/* translators: %d: days */
			printf( '<button type="submit" name="days" value="%1$d" class="%2$s">%3$s</button>', (int) $d, $d === $days ? 'is-on' : '', esc_html( 1 === $d ? __( '24 hours', 'flx-ai' ) : sprintf( __( '%d days', 'flx-ai' ), $d ) ) );
		}
		echo '</div><input type="hidden" name="days" value="' . esc_attr( $days ) . '">';
		printf( $kind_sel, ' onchange="this.form.submit()"' ); // phpcs:ignore
		printf( $src_sel, ' onchange="this.form.submit()"' ); // phpcs:ignore
		printf( $err_chk, '', ' onchange="this.form.submit()"' ); // phpcs:ignore
		echo '</form>';
	}
	if ( ! $lib ) {
		/* translators: %s: count */
		echo '<p class="flxai-note flxai-count">' . esc_html( sprintf( __( '%s records', 'flx-ai' ), number_format_i18n( $total ) ) ) . '</p>';
	}
	if ( ! $rows ) {
		echo '<p class="flxai-empty">' . esc_html__( 'No records.', 'flx-ai' ) . '</p></div>';
		return;
	}
	$users = array();
	echo '<div class="flxai-card"><div class="flxai-tscroll"><table class="flxai-table flxai-log"><thead><tr><th>' . esc_html__( 'Time', 'flx-ai' ) . '</th><th>' . esc_html__( 'Where', 'flx-ai' ) . '</th><th>' . esc_html__( 'Who', 'flx-ai' ) . '</th><th>' . esc_html__( 'Model / tool', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Input', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Output', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Cache', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Duration', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Cost', 'flx-ai' ) . '</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		$what = 'tool' === $r['kind'] ? '<span class="flxai-pill">' . esc_html__( 'tool', 'flx-ai' ) . '</span> ' . esc_html( $r['tool'] ) : esc_html( flxai_model_label( $r['model'] ) );
		if ( ! (int) $r['ok'] ) {
			$err   = 'limit' === $r['error'] ? __( 'Spending limit reached', 'flx-ai' ) : ( 'off' === $r['error'] ? __( 'Off (Connected systems)', 'flx-ai' ) : $r['error'] );
			$what .= '<div class="flxai-err">' . esc_html( $err ) . '</div>';
		}
		$uid = (int) $r['user_id'];
		if ( $uid && ! isset( $users[ $uid ] ) ) {
			$u             = get_userdata( $uid );
			$users[ $uid ] = $u ? $u->display_name : '#' . $uid;
		}
		$where_txt = ( $sources[ $r['source'] ] ?? flxai_source_label( $r['source'] ) ) . ( '' !== $r['purpose'] ? ' · ' . flxai_purpose_label( $r['purpose'] ) : '' ) . ( (int) $r['chat_id'] ? ' #' . (int) $r['chat_id'] : '' );
		printf(
			'<tr class="%10$s"><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td class="num">%5$s</td><td class="num">%6$s</td><td class="num">%7$s</td><td class="num">%8$s</td><td class="num">%9$s</td><td>%11$s</td></tr>',
			esc_html( flx_date( (int) $r['ts'], 'j M H:i:s' ) ),
			esc_html( $where_txt ),
			esc_html( $uid ? $users[ $uid ] : __( 'System', 'flx-ai' ) ),
			$what, // phpcs:ignore -- escaped above
			esc_html( number_format_i18n( (int) $r['in_tok'] ) ),
			esc_html( number_format_i18n( (int) $r['out_tok'] ) ),
			esc_html( (int) $r['cache_read'] || (int) $r['cache_write'] ? number_format_i18n( (int) $r['cache_read'] ) . ' / ' . number_format_i18n( (int) $r['cache_write'] ) : '—' ),
			esc_html( flxai_ms( (int) $r['ms'] ) ),
			esc_html( 'tool' === $r['kind'] ? '—' : flxai_usd( (float) $r['cost'], 5 ) . ( (int) $r['est'] ? ' ~' : '' ) ),
			(int) $r['ok'] ? '' : 'is-err',
			(int) $r['has_body'] ? '<button type="button" class="button button-small" data-flxai-body="' . (int) $r['id'] . '">' . esc_html__( 'Content', 'flx-ai' ) . '</button>' : ''
		);
	}
	echo '</tbody></table></div>';
	if ( $lib ) {
		flxa_list_pagination( $total, $per, $pg, array( 'page_arg' => 'pg' ) );
	} else {
		$pages = (int) ceil( $total / $per );
		if ( $pages > 1 ) {
			echo '<p class="flxai-pager">';
			$base = remove_query_arg( 'pg' );
			if ( $pg > 1 ) {
				echo '<a class="button" href="' . esc_url( add_query_arg( 'pg', $pg - 1, $base ) ) . '">‹</a> ';
			}
			/* translators: 1: page, 2: pages */
			echo '<span>' . esc_html( sprintf( __( 'Page %1$s / %2$s', 'flx-ai' ), number_format_i18n( $pg ), number_format_i18n( $pages ) ) ) . '</span>';
			if ( $pg < $pages ) {
				echo ' <a class="button" href="' . esc_url( add_query_arg( 'pg', $pg + 1, $base ) ) . '">›</a>';
			}
			echo '</p>';
		}
	}
	echo '<p class="flxai-note">' . esc_html__( 'Cache column: tokens read / written. "~" is estimated from the price list. Content: last outgoing messages and the incoming reply, shortened; e-mail, phone, IBAN and keys are masked.', 'flx-ai' ) . '</p></div></div>';
}

/* ---------------------------------------------------------------------------
 * Bağlı sistemler
 * ------------------------------------------------------------------------- */

function flxai_page_systems() {
	if ( ! empty( $_GET['system'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		flxai_page_system_settings( sanitize_key( wp_unslash( $_GET['system'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$saved = false;
	if ( isset( $_POST['flxai_systems_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['flxai_systems_nonce'] ), 'flxai_systems' ) && flxai_can_manage() ) {
		$all = array_map( 'sanitize_key', (array) wp_unslash( $_POST['sys'] ?? array() ) ); // phpcs:ignore
		$on  = array_map( 'sanitize_key', (array) wp_unslash( $_POST['sys_on'] ?? array() ) ); // phpcs:ignore
		$off = array_values( array_diff( $all, $on ) );
		// Listede olmayan (ör. kaldırılmış) kapalı kaynaklar korunur.
		$keep = array_diff( (array) flxai_setting( 'sources_off', array() ), $all );
		flxai_update_settings( array( 'sources_off' => array_values( array_unique( array_merge( $keep, $off ) ) ) ) );
		$saved = true;
	}
	flxai_page_head( __( 'Connected systems', 'flx-ai' ), __( 'Plugins that use Flux AI. A system switched off here cannot send AI requests; its own settings stay as they are.', 'flx-ai' ) );
	if ( $saved ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'flx-ai' ) . '</p></div>';
	}
	$rows = flxai_systems();
	echo '<form method="post">';
	wp_nonce_field( 'flxai_systems', 'flxai_systems_nonce' );
	echo '<div class="flxai-card"><div class="flxai-tscroll"><table class="flxai-table flxai-systems"><thead><tr><th>' . esc_html__( 'On', 'flx-ai' ) . '</th><th>' . esc_html__( 'System', 'flx-ai' ) . '</th><th>' . esc_html__( 'Status', 'flx-ai' ) . '</th><th>' . esc_html__( 'Model', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Requests (30 days)', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Tokens', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Cost', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Errors', 'flx-ai' ) . '</th><th>' . esc_html__( 'Last used', 'flx-ai' ) . '</th><th></th></tr></thead><tbody>';
	foreach ( $rows as $r ) {
		if ( ! $r['installed'] ) {
			$state = '<span class="flxai-pill">' . esc_html__( 'not installed', 'flx-ai' ) . '</span>';
		} elseif ( ! $r['active'] ) {
			$state = '<span class="flxai-pill">' . esc_html__( 'plugin inactive', 'flx-ai' ) . '</span>';
		} elseif ( ! $r['on'] ) {
			$state = '<span class="flxai-pill is-err">' . esc_html__( 'AI off', 'flx-ai' ) . '</span>';
		} else {
			$state = '<span class="flxai-pill is-ok">' . esc_html__( 'working', 'flx-ai' ) . '</span>';
		}
		$links = '<a href="' . esc_url( admin_url( 'admin.php?page=flx-ai-log&days=30&source=' . rawurlencode( $r['source'] ) ) ) . '">' . esc_html__( 'Logs', 'flx-ai' ) . '</a>';
		if ( flxai_system_has_settings( $r['source'] ) ) {
			$links .= ' · <a href="' . esc_url( admin_url( 'admin.php?page=flx-ai-systems&system=' . rawurlencode( $r['source'] ) ) ) . '">' . esc_html__( 'AI settings', 'flx-ai' ) . '</a>';
		}
		if ( '' !== $r['page'] && $r['active'] ) {
			$links .= ' · <a href="' . esc_url( admin_url( $r['page'] ) ) . '">' . esc_html__( 'Its settings', 'flx-ai' ) . '</a>';
		}
		printf(
			'<tr><td><input type="hidden" name="sys[]" value="%1$s"><label class="flxai-switch"><input type="checkbox" name="sys_on[]" value="%1$s"%2$s><span class="screen-reader-text">%3$s</span></label></td><td><strong>%3$s</strong><div class="flxai-kpi-s"><code>%1$s</code></div></td><td>%4$s</td><td>%5$s</td><td class="num">%6$s</td><td class="num">%7$s</td><td class="num">%8$s</td><td class="num">%9$s</td><td>%10$s</td><td>%11$s</td></tr>',
			esc_attr( $r['source'] ),
			checked( $r['on'], true, false ),
			esc_html( $r['label'] ),
			$state, // phpcs:ignore -- escaped above
			esc_html( '' !== $r['model'] ? flxai_model_label( $r['model'] ) : '—' ),
			esc_html( number_format_i18n( $r['calls'] ) . ( $r['tools'] ? ' + ' . number_format_i18n( $r['tools'] ) : '' ) ),
			esc_html( number_format_i18n( $r['tok'] ) ),
			esc_html( flxai_usd( $r['cost'] ) ),
			esc_html( number_format_i18n( $r['errors'] ) ),
			esc_html( $r['last'] ? flx_date( $r['last'], 'j M H:i' ) : '—' ),
			$links // phpcs:ignore -- escaped above
		);
	}
	echo '</tbody></table></div>';
	echo '<p class="flxai-note">' . esc_html__( 'The Model column is the model that system uses. Change it under Settings › Per-plugin model. Requests to a disabled system are rejected and show as "Disabled" in the log.', 'flx-ai' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=flx-ai-settings' ) ) . '">' . esc_html__( 'Settings', 'flx-ai' ) . '</a></p></div>';
	echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save', 'flx-ai' ) . '</button></p></form></div>';
}

/* ---------------------------------------------------------------------------
 * Faturalandırma
 * ------------------------------------------------------------------------- */

function flxai_page_billing() {
	$ym   = sanitize_text_field( wp_unslash( $_GET['ym'] ?? wp_date( 'Y-m' ) ) ); // phpcs:ignore
	$bill = flxai_billing( $ym );
	$ym   = $bill['ym'];
	$t    = $bill['totals'];
	flxai_page_head( __( 'Billing', 'flx-ai' ), __( 'Monthly breakdown, spending limits and provider credit. Amounts in USD; OpenRouter reports actual cost, Anthropic is computed from the price list.', 'flx-ai' ) );
	flxai_budget_cards();

	echo '<div class="flxai-grid flxai-bill">';
	echo '<section class="flxai-card"><h2>' . esc_html__( 'Months', 'flx-ai' ) . '</h2><ul class="flxai-months">';
	foreach ( flxai_months( 12 ) as $m ) {
		printf(
			'<li><a href="%1$s" class="%2$s"><span>%3$s</span><strong>%4$s</strong></a></li>',
			esc_url( admin_url( 'admin.php?page=flx-ai-billing&ym=' . $m['ym'] ) ),
			$m['ym'] === $ym ? 'is-on' : '',
			esc_html( $m['label'] ),
			esc_html( flxai_usd( $m['cost'], $m['cost'] > 0 && $m['cost'] < 1 ? 4 : 2 ) )
		);
	}
	echo '</ul></section>';

	echo '<section class="flxai-card flxai-span2"><div class="flxai-bill-head"><h2>' . esc_html( flx_date( $bill['from'], 'F Y' ) ) . '</h2>';
	echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flxai_csv&ym=' . $ym ), 'flxai_csv' ) ) . '">' . esc_html__( 'Download CSV', 'flx-ai' ) . '</a></div>';
	echo '<div class="flxai-kpis">';
	flxai_kpi( __( 'Total', 'flx-ai' ), flxai_usd( $t['cost'], 4 ), sprintf( /* translators: %s: amount */ __( '%s without cache', 'flx-ai' ), flxai_usd( $t['full_cost'], 4 ) ) );
	flxai_kpi( __( 'Cache savings', 'flx-ai' ), flxai_usd( $t['saved'], 4 ), $t['full_cost'] > 0 ? sprintf( /* translators: %s: percent */ __( '%s%% less', 'flx-ai' ), number_format_i18n( $t['saved'] / $t['full_cost'] * 100, 1 ) ) : '' );
	flxai_kpi( __( 'Model requests', 'flx-ai' ), number_format_i18n( $t['calls'] ), '' );
	echo '</div><div class="flxai-chart" data-chart="cost"></div>';
	echo '<script type="application/json" id="flxai-chart-data">' . wp_json_encode( array( 'daily' => $bill['daily'], 'tools' => array() ) ) . '</script>';

	echo '<h3>' . esc_html__( 'By provider', 'flx-ai' ) . '</h3><div class="flxai-tscroll"><table class="flxai-table"><thead><tr><th>' . esc_html__( 'Provider', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Requests', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Input', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Output', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Without cache', 'flx-ai' ) . '</th><th class="num">' . esc_html__( 'Amount', 'flx-ai' ) . '</th></tr></thead><tbody>';
	foreach ( $bill['by_provider'] as $r ) {
		printf(
			'<tr><td>%1$s%7$s</td><td class="num">%2$s</td><td class="num">%3$s</td><td class="num">%4$s</td><td class="num">%5$s</td><td class="num"><strong>%6$s</strong></td></tr>',
			esc_html( $r['label'] ),
			esc_html( number_format_i18n( (int) $r['calls'] ) ),
			esc_html( number_format_i18n( (int) $r['in_tok'] ) ),
			esc_html( number_format_i18n( (int) $r['out_tok'] ) ),
			esc_html( flxai_usd( (float) $r['full_cost'], 4 ) ),
			esc_html( flxai_usd( (float) $r['cost'], 4 ) ),
			(int) $r['est'] ? ' <span class="flxai-pill">' . esc_html__( 'partly estimated', 'flx-ai' ) . '</span>' : ''
		);
	}
	if ( ! $bill['by_provider'] ) {
		echo '<tr><td colspan="6" class="flxai-empty">' . esc_html__( 'No records this month.', 'flx-ai' ) . '</td></tr>';
	}
	echo '</tbody></table></div></section></div>';

	echo '<div class="flxai-grid">';
	flxai_breakdown_table( __( 'By plugin', 'flx-ai' ), $bill['by_source'] );
	flxai_breakdown_table( __( 'By model', 'flx-ai' ), $bill['by_model'] );
	echo '</div>';

	$cr = get_option( 'flxai_or_credits', null );
	echo '<section class="flxai-card"><div class="flxai-bill-head"><h2>' . esc_html__( 'OpenRouter account', 'flx-ai' ) . '</h2><button type="button" class="button" data-flxai-credits>' . esc_html__( 'Refresh', 'flx-ai' ) . '</button></div><div id="flxai-credits">';
	if ( is_array( $cr ) ) {
		echo '<dl class="flxai-dl">';
		if ( null !== $cr['credits'] ) {
			echo '<dt>' . esc_html__( 'Remaining credit', 'flx-ai' ) . '</dt><dd>' . esc_html( flxai_usd( $cr['credits'], 2 ) ) . '</dd>';
		}
		if ( null !== $cr['usage'] ) {
			echo '<dt>' . esc_html__( 'Total usage of this key', 'flx-ai' ) . '</dt><dd>' . esc_html( flxai_usd( $cr['usage'], 2 ) ) . '</dd>';
		}
		if ( null !== $cr['limit'] ) {
			echo '<dt>' . esc_html__( 'Key limit / remaining', 'flx-ai' ) . '</dt><dd>' . esc_html( flxai_usd( $cr['limit'], 2 ) . ' / ' . flxai_usd( (float) $cr['remaining'], 2 ) ) . '</dd>';
		}
		echo '<dt>' . esc_html__( 'Checked', 'flx-ai' ) . '</dt><dd>' . esc_html( flx_date( (int) $cr['checked'], 'j M H:i' ) ) . '</dd></dl>';
	} else {
		echo '<p class="flxai-empty">' . esc_html__( 'Press Refresh to see remaining credit. (Anthropic has no balance API; see console.anthropic.com › Billing.)', 'flx-ai' ) . '</p>';
	}
	echo '</div></section></div>';
}

/* ---------------------------------------------------------------------------
 * Ayarlar
 * ------------------------------------------------------------------------- */

function flxai_save_settings() {
	if ( ! isset( $_POST['flxai_settings_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['flxai_settings_nonce'] ), 'flxai_settings' ) || ! flxai_can_manage() ) {
		return false;
	}
	$p   = wp_unslash( $_POST ); // phpcs:ignore
	$new = array();
	$flxai_keys = array( 'key_openrouter', 'key_anthropic', 'key_render', 'key_fal', 'key_evolink', 'key_openai', 'key_gemini', 'key_brave' );
	foreach ( flxai_web_providers() as $flxai_w ) { // eklentilerin eklediği arama sağlayıcılarının anahtarları
		if ( ! empty( $flxai_w['key'] ) && 0 === strpos( (string) $flxai_w['key'], 'key_' ) ) {
			$flxai_keys[] = sanitize_key( $flxai_w['key'] );
		}
	}
	foreach ( array_unique( $flxai_keys ) as $k ) {
		$v = trim( (string) ( $p[ $k ] ?? '' ) );
		if ( ! empty( $p[ $k . '_clear' ] ) ) {
			$new[ $k ] = '';
		} elseif ( '' !== $v ) {
			$new[ $k ] = sanitize_text_field( $v );
		}
	}
	$new['anthropic_workspace'] = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $p['anthropic_workspace'] ?? '' ) );
	foreach ( array( 'model', 'chat_model', 'title_model' ) as $k ) {
		$new[ $k ] = sanitize_text_field( (string) ( $p[ $k ] ?? '' ) );
	}
	$map = array();
	foreach ( (array) ( $p['source_models'] ?? array() ) as $src => $m ) {
		$m = sanitize_text_field( (string) $m );
		if ( '' !== $m ) {
			$map[ sanitize_key( $src ) ] = $m;
		}
	}
	$new['source_models']  = $map;
	$new['cache']          = in_array( $p['cache'] ?? '', array( 'off', '5m', '1h' ), true ) ? $p['cache'] : '5m';
	$new['effort']         = in_array( $p['effort'] ?? '', array( 'low', 'medium', 'high' ), true ) ? $p['effort'] : 'low';
	$new['thinking']       = empty( $p['thinking'] ) ? 0 : 1;
	foreach ( array( 'limit_day', 'limit_week', 'limit_month' ) as $k ) {
		$new[ $k ] = max( 0, round( (float) str_replace( ',', '.', (string) ( $p[ $k ] ?? 0 ) ), 2 ) );
	}
	$new['limit_action']   = 'warn' === ( $p['limit_action'] ?? '' ) ? 'warn' : 'block';
	$new['limit_warn_pct'] = max( 10, min( 99, (int) ( $p['limit_warn_pct'] ?? 80 ) ) );
	$new['limit_notify']   = empty( $p['limit_notify'] ) ? 0 : 1;
	$roles                 = array_map( 'sanitize_key', (array) ( $p['chat_roles'] ?? array() ) );
	$new['chat_roles']     = array_values( array_intersect( $roles, array_keys( wp_roles()->get_names() ) ) );
	$new['chat_admin']     = empty( $p['chat_admin'] ) ? 0 : 1;
	$new['chat_front']     = empty( $p['chat_front'] ) ? 0 : 1;
	$new['chat_mode']      = in_array( $p['chat_mode'] ?? '', array( 'dock', 'page' ), true ) ? $p['chat_mode'] : 'bubble';
	$new['chat_max_rounds'] = max( 2, min( 30, (int) ( $p['chat_max_rounds'] ?? 12 ) ) );
	$new['chat_history']   = max( 6, min( 200, (int) ( $p['chat_history'] ?? 40 ) ) );
	$new['chat_instructions'] = sanitize_textarea_field( (string) ( $p['chat_instructions'] ?? '' ) );
	$new['chat_widgets']   = empty( $p['chat_widgets'] ) ? 0 : 1;
	$new['render_url']     = esc_url_raw( trim( (string) ( $p['render_url'] ?? '' ) ), array( 'http', 'https' ) );
	$new['render_user']    = sanitize_text_field( (string) ( $p['render_user'] ?? '' ) );
	$flxai_cat = flxai_media_catalog();
	foreach ( array( 'media_image_model' => 'image', 'media_video_model' => 'video' ) as $k => $kind ) {
		$m = sanitize_text_field( (string) ( $p[ $k ] ?? '' ) );
		if ( isset( $flxai_cat[ $m ] ) && $kind === $flxai_cat[ $m ]['kind'] ) {
			$new[ $k ] = $m;
		}
	}
	$new['media_days']     = max( 7, min( 1825, (int) ( $p['media_days'] ?? 90 ) ) );
	$new['web_on']           = empty( $p['web_on'] ) ? 0 : 1;
	$new['web_fetch_on']     = empty( $p['web_fetch_on'] ) ? 0 : 1;
	$new['image_tool_on']    = empty( $p['image_tool_on'] ) ? 0 : 1;
	$new['shell_on']         = empty( $p['shell_on'] ) ? 0 : 1;
	$flxai_wp                = sanitize_key( (string) ( $p['web_provider'] ?? 'auto' ) );
	$new['web_provider']     = ( 'auto' === $flxai_wp || isset( flxai_web_providers()[ $flxai_wp ] ) ) ? $flxai_wp : 'auto';
	$new['web_search_month'] = max( 0, min( 1000000, (int) ( $p['web_search_month'] ?? 0 ) ) );
	$new['web_search_cost']  = max( 0, min( 1000, round( (float) str_replace( ',', '.', (string) ( $p['web_search_cost'] ?? 0 ) ), 4 ) ) );
	// Boş ya da 0 = sınırsız.
	$new['log_days']       = max( 0, min( 3650, (int) ( $p['log_days'] ?? 0 ) ) );
	$new['log_bodies']     = empty( $p['log_bodies'] ) ? 0 : 1;
	$new['log_body_days']  = max( 0, min( 3650, (int) ( $p['log_body_days'] ?? 0 ) ) );
	$new['delete_data']    = empty( $p['delete_data'] ) ? 0 : 1;
	$old_keys              = array( flxai_setting( 'key_openrouter' ), flxai_setting( 'key_anthropic' ), flxai_setting( 'anthropic_workspace' ) );
	flxai_update_settings( $new );
	if ( array( flxai_setting( 'key_openrouter' ), flxai_setting( 'key_anthropic' ), flxai_setting( 'anthropic_workspace' ) ) !== $old_keys ) {
		flxai_models_flush();
	}
	return true;
}

/** Model seçici alanı (ortak model-picker.js ile zenginleşir). */
function flxai_model_field( $name, $value, $placeholder = '' ) {
	printf(
		'<input type="text" class="regular-text flxai-model" data-flxai-model name="%1$s" value="%2$s" placeholder="%3$s" autocomplete="off">',
		esc_attr( $name ),
		esc_attr( $value ),
		esc_attr( $placeholder )
	);
}

function flxai_page_settings() {
	$saved = flxai_save_settings();
	flxai_page_head( __( 'Flux AI settings', 'flx-ai' ), __( 'Enter keys here once; all FluxWP plugins use this connection.', 'flx-ai' ) );
	if ( $saved ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'flx-ai' ) . '</p></div>';
	}
	$s   = flxai_settings();
	$or  = flxai_setting( 'key_openrouter' );
	$an  = flxai_setting( 'key_anthropic' );
	$def = flxai_model_for( '' );
	?>
	<form method="post" class="flxai-settings" autocomplete="off">
		<?php wp_nonce_field( 'flxai_settings', 'flxai_settings_nonce' ); ?>
		<section class="flxai-card">
			<h2><?php esc_html_e( 'Providers', 'flx-ai' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><label for="flxai-or"><?php esc_html_e( 'OpenRouter API key', 'flx-ai' ); ?></label></th><td>
					<input id="flxai-or" type="password" class="regular-text" name="key_openrouter" value="" placeholder="<?php echo esc_attr( $or ? flxai_mask( $or ) : 'sk-or-…' ); ?>" autocomplete="new-password">
					<?php if ( $or ) : ?><label class="flxai-check"><input type="checkbox" name="key_openrouter_clear" value="1"> <?php esc_html_e( 'Remove', 'flx-ai' ); ?></label><?php endif; ?>
					<p class="description"><?php esc_html_e( 'openrouter.ai › Keys. Hundreds of models with one key; actual cost is reported with every request.', 'flx-ai' ); ?></p></td></tr>
				<tr><th><label for="flxai-an"><?php esc_html_e( 'Anthropic (Claude) API key', 'flx-ai' ); ?></label></th><td>
					<input id="flxai-an" type="password" class="regular-text" name="key_anthropic" value="" placeholder="<?php echo esc_attr( $an ? flxai_mask( $an ) : 'sk-ant-…' ); ?>" autocomplete="new-password">
					<?php if ( $an ) : ?><label class="flxai-check"><input type="checkbox" name="key_anthropic_clear" value="1"> <?php esc_html_e( 'Remove', 'flx-ai' ); ?></label><?php endif; ?>
					<p class="description"><?php esc_html_e( 'console.anthropic.com › API Keys. Claude models are listed with the "claude:" prefix.', 'flx-ai' ); ?></p></td></tr>
				<tr><th><label for="flxai-ws"><?php esc_html_e( 'Anthropic workspace ID', 'flx-ai' ); ?></label></th><td>
					<input id="flxai-ws" type="text" class="regular-text" name="anthropic_workspace" value="<?php echo esc_attr( $s['anthropic_workspace'] ); ?>" placeholder="wrkspc_…">
					<p class="description"><?php esc_html_e( 'Only needed when the key is not tied to a workspace (if the error asks for "anthropic-workspace-id").', 'flx-ai' ); ?></p></td></tr>
			</table>
		</section>

		<section class="flxai-card">
			<h2><?php esc_html_e( 'Models', 'flx-ai' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Default model', 'flx-ai' ); ?></th><td><?php flxai_model_field( 'model', $s['model'], $def ); ?>
					<button type="button" class="button" data-flxai-test="model"><?php esc_html_e( 'Test', 'flx-ai' ); ?></button><span class="flxai-test-out"></span>
					<p class="description"><?php esc_html_e( 'Used when a plugin does not specify its own model.', 'flx-ai' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Shared chat model', 'flx-ai' ); ?></th><td><?php flxai_model_field( 'chat_model', $s['chat_model'], __( 'Default', 'flx-ai' ) ); ?>
					<p class="description"><?php esc_html_e( 'Choose a model that supports tools.', 'flx-ai' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Chat title model', 'flx-ai' ); ?></th><td><?php flxai_model_field( 'title_model', $s['title_model'], __( 'Chat model', 'flx-ai' ) ); ?>
					<p class="description"><?php esc_html_e( 'A cheap model is enough for automatic chat titles.', 'flx-ai' ); ?></p></td></tr>
			</table>
			<h3><?php esc_html_e( 'Per-plugin model', 'flx-ai' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Used when the plugin\'s request names no model; empty means the default.', 'flx-ai' ); ?></p>
			<table class="form-table" role="presentation">
				<?php foreach ( flxai_sources() as $src => $label ) : ?>
					<?php
					if ( 'flx-ai' === $src ) {
						continue;
					}
					?>
					<tr><th><?php echo esc_html( $label ); ?></th><td><?php flxai_model_field( 'source_models[' . $src . ']', (string) ( $s['source_models'][ $src ] ?? '' ), __( 'Default', 'flx-ai' ) ); ?></td></tr>
				<?php endforeach; ?>
			</table>
		</section>

		<section class="flxai-card">
			<h2><?php esc_html_e( 'Cache and thinking', 'flx-ai' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Claude prompt cache', 'flx-ai' ); ?></th><td>
					<select name="cache">
						<option value="off" <?php selected( $s['cache'], 'off' ); ?>><?php esc_html_e( 'Off', 'flx-ai' ); ?></option>
						<option value="5m" <?php selected( $s['cache'], '5m' ); ?>><?php esc_html_e( 'On, 5 minutes', 'flx-ai' ); ?></option>
						<option value="1h" <?php selected( $s['cache'], '1h' ); ?>><?php esc_html_e( 'On, 1 hour', 'flx-ai' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'When the same instructions and tools are sent again most of the input cost drops (read 0.1x; write 5 min 1.25x, 1 hour 2x). Plugins can override per request. OpenRouter caching is automatic at the provider.', 'flx-ai' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Claude effort', 'flx-ai' ); ?></th><td>
					<select name="effort">
						<option value="low" <?php selected( $s['effort'], 'low' ); ?>><?php esc_html_e( 'Low: fast and cheap', 'flx-ai' ); ?></option>
						<option value="medium" <?php selected( $s['effort'], 'medium' ); ?>><?php esc_html_e( 'Medium', 'flx-ai' ); ?></option>
						<option value="high" <?php selected( $s['effort'], 'high' ); ?>><?php esc_html_e( 'High: thinks deeper', 'flx-ai' ); ?></option>
					</select></td></tr>
				<tr><th><?php esc_html_e( 'Thinking steps', 'flx-ai' ); ?></th><td><label class="flxai-check"><input type="checkbox" name="thinking" value="1" <?php checked( $s['thinking'] ); ?>> <?php esc_html_e( 'Show the model\'s thinking summary in chat', 'flx-ai' ); ?></label></td></tr>
			</table>
		</section>

		<section class="flxai-card" id="limits">
			<h2><?php esc_html_e( 'Spending limit', 'flx-ai' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php foreach ( array( 'day' => __( 'Daily (resets every day at 00:00)', 'flx-ai' ), 'week' => __( 'Weekly (resets on Monday)', 'flx-ai' ), 'month' => __( 'Monthly (resets on the 1st)', 'flx-ai' ) ) as $p => $label ) : ?>
					<tr><th><?php echo esc_html( $label ); ?></th><td>$ <input type="number" step="0.01" min="0" class="small-text" name="limit_<?php echo esc_attr( $p ); ?>" value="<?php echo esc_attr( $s[ 'limit_' . $p ] ); ?>"> <span class="description"><?php esc_html_e( '0 = unlimited', 'flx-ai' ); ?></span></td></tr>
				<?php endforeach; ?>
				<tr><th><?php esc_html_e( 'When the limit is reached', 'flx-ai' ); ?></th><td>
					<label class="flxai-check"><input type="radio" name="limit_action" value="block" <?php checked( $s['limit_action'], 'block' ); ?>> <?php esc_html_e( 'Stop requests (plugins switch to their fallback behaviour)', 'flx-ai' ); ?></label><br>
					<label class="flxai-check"><input type="radio" name="limit_action" value="warn" <?php checked( $s['limit_action'], 'warn' ); ?>> <?php esc_html_e( 'Warn only', 'flx-ai' ); ?></label></td></tr>
				<tr><th><?php esc_html_e( 'Warning threshold', 'flx-ai' ); ?></th><td>% <input type="number" min="10" max="99" class="small-text" name="limit_warn_pct" value="<?php echo esc_attr( $s['limit_warn_pct'] ); ?>">
					<label class="flxai-check"><input type="checkbox" name="limit_notify" value="1" <?php checked( $s['limit_notify'] ); ?>> <?php esc_html_e( 'Email the admin at the threshold and when reached', 'flx-ai' ); ?></label></td></tr>
			</table>
		</section>

		<section class="flxai-card">
			<h2><?php esc_html_e( 'Shared chat', 'flx-ai' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Who can use it', 'flx-ai' ); ?></th><td>
					<?php foreach ( wp_roles()->get_names() as $role => $name ) : ?>
						<label class="flxai-check flxai-role"><input type="checkbox" name="chat_roles[]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, (array) $s['chat_roles'], true ) ); ?>> <?php echo esc_html( translate_user_role( $name ) ); ?></label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Everyone only uses tools their own capabilities allow. Individual users can also be given the "flxai_chat" capability.', 'flx-ai' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Where to show', 'flx-ai' ); ?></th><td>
					<label class="flxai-check"><input type="checkbox" name="chat_admin" value="1" <?php checked( $s['chat_admin'] ); ?>> <?php esc_html_e( 'Admin panel', 'flx-ai' ); ?></label>
					<label class="flxai-check"><input type="checkbox" name="chat_front" value="1" <?php checked( $s['chat_front'] ); ?>> <?php esc_html_e( 'Site (for logged-in permitted users)', 'flx-ai' ); ?></label></td></tr>
				<tr><th><?php esc_html_e( 'Default layout', 'flx-ai' ); ?></th><td>
					<select name="chat_mode">
						<option value="bubble" <?php selected( $s['chat_mode'], 'bubble' ); ?>><?php esc_html_e( 'Floating window', 'flx-ai' ); ?></option>
						<option value="dock" <?php selected( $s['chat_mode'], 'dock' ); ?>><?php esc_html_e( 'Docked right (pushes the page)', 'flx-ai' ); ?></option>
						<option value="page" <?php selected( $s['chat_mode'], 'page' ); ?>><?php esc_html_e( 'Full screen', 'flx-ai' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Users can switch from the panel; the choice stays in their browser.', 'flx-ai' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Max steps per turn', 'flx-ai' ); ?></th><td><input type="number" min="2" max="30" class="small-text" name="chat_max_rounds" value="<?php echo esc_attr( $s['chat_max_rounds'] ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Max messages sent to the model', 'flx-ai' ); ?></th><td><input type="number" min="6" max="200" class="small-text" name="chat_history" value="<?php echo esc_attr( $s['chat_history'] ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Extra instructions', 'flx-ai' ); ?></th><td><textarea name="chat_instructions" rows="5" class="large-text" placeholder="<?php esc_attr_e( 'E.g. Use a formal tone; always create new pages as drafts.', 'flx-ai' ); ?>"><?php echo esc_textarea( $s['chat_instructions'] ); ?></textarea></td></tr>
			</table>
		</section>

		<section class="flxai-card" id="media">
			<h2><?php esc_html_e( 'Image and video generation', 'flx-ai' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Plugins that generate images and video (Flux Studio etc.) use these keys. In automatic mode the first provider with a key for the model is used; a request that may have been charged is never resent to another provider. The spending limit and Logs apply here too.', 'flx-ai' ); ?></p>
			<table class="form-table" role="presentation">
				<?php
				$flxai_mkeys = array(
					'key_fal'     => array( 'fal.ai', 'fal.ai › Dashboard › API Keys', '…:…' ),
					'key_evolink' => array( 'EvoLink', 'evolink.ai › API Keys', 'sk-…' ),
					'key_openai'  => array( 'OpenAI', 'platform.openai.com › API keys', 'sk-…' ),
					'key_gemini'  => array( 'Google Gemini', 'aistudio.google.com › API keys', 'AIza…' ),
				);
				foreach ( $flxai_mkeys as $flxai_k => $flxai_m ) :
					$flxai_v = flxai_setting( $flxai_k );
					?>
					<tr><th><label for="flxai-<?php echo esc_attr( $flxai_k ); ?>">
						<?php
						/* translators: %s: provider */
						echo esc_html( sprintf( __( '%s API key', 'flx-ai' ), $flxai_m[0] ) );
						?>
					</label></th><td>
						<input id="flxai-<?php echo esc_attr( $flxai_k ); ?>" type="password" class="regular-text" name="<?php echo esc_attr( $flxai_k ); ?>" value="" placeholder="<?php echo esc_attr( $flxai_v ? flxai_mask( $flxai_v ) : $flxai_m[2] ); ?>" autocomplete="new-password">
						<?php if ( $flxai_v ) : ?><label class="flxai-check"><input type="checkbox" name="<?php echo esc_attr( $flxai_k ); ?>_clear" value="1"> <?php esc_html_e( 'Remove', 'flx-ai' ); ?></label><?php endif; ?>
						<p class="description"><?php echo esc_html( $flxai_m[1] ); ?></p></td></tr>
				<?php endforeach; ?>
				<tr><th><?php esc_html_e( 'OpenRouter', 'flx-ai' ); ?></th><td><p class="description"><?php esc_html_e( 'The OpenRouter key in Providers is also used for images and video.', 'flx-ai' ); ?></p></td></tr>
				<?php
				$flxai_mp = flxai_media_providers();
				foreach ( array( 'media_image_model' => array( 'image', __( 'Default image model', 'flx-ai' ) ), 'media_video_model' => array( 'video', __( 'Default video model', 'flx-ai' ) ) ) as $flxai_k => $flxai_d ) :
					?>
					<tr><th><?php echo esc_html( $flxai_d[1] ); ?></th><td><select name="<?php echo esc_attr( $flxai_k ); ?>">
						<?php foreach ( $flxai_mp['models'] as $flxai_id => $flxai_mm ) : ?>
							<?php
							if ( $flxai_d[0] !== $flxai_mm['kind'] ) {
								continue;
							}
							?>
							<option value="<?php echo esc_attr( $flxai_id ); ?>" <?php selected( $s[ $flxai_k ] ?? '', $flxai_id ); ?>><?php echo esc_html( $flxai_mm['label'] ); ?></option>
						<?php endforeach; ?>
					</select></td></tr>
				<?php endforeach; ?>
				<tr><th><?php esc_html_e( 'Image tool', 'flx-ai' ); ?></th><td><label class="flxai-check"><input type="checkbox" name="image_tool_on" value="1" <?php checked( ! empty( $s['image_tool_on'] ) ); ?>> <?php esc_html_e( 'Chat and agents may generate images with the default image model (flxai_image_generate; each image is charged and counted in the spending limit)', 'flx-ai' ); ?></label></td></tr>
				<tr><th><?php esc_html_e( 'Model › provider', 'flx-ai' ); ?></th><td>
					<table class="widefat striped flxai-media-routes"><thead><tr><th><?php esc_html_e( 'Model', 'flx-ai' ); ?></th><th><?php esc_html_e( 'Order', 'flx-ai' ); ?></th><th><?php esc_html_e( 'Automatic', 'flx-ai' ); ?></th></tr></thead><tbody>
					<?php foreach ( $flxai_mp['models'] as $flxai_mm ) : ?>
						<tr><td><?php echo esc_html( $flxai_mm['label'] . ( 'video' === $flxai_mm['kind'] ? ' · ' . __( 'video', 'flx-ai' ) : '' ) ); ?></td>
							<td><?php echo esc_html( implode( ' → ', array_map( fn( $x ) => $flxai_mp['providers'][ $x ]['label'] ?? $x, $flxai_mm['routes'] ) ) ); ?></td>
							<td><?php echo $flxai_mm['auto'] ? esc_html( $flxai_mp['providers'][ $flxai_mm['auto'] ]['label'] ) : '<span class="description">' . esc_html__( 'No key', 'flx-ai' ) . '</span>'; ?></td></tr>
					<?php endforeach; ?>
					</tbody></table></td></tr>
				<tr><th><?php esc_html_e( 'Job records', 'flx-ai' ); ?></th><td><input type="number" min="7" max="1825" class="small-text" name="media_days" value="<?php echo esc_attr( $s['media_days'] ?? 90 ); ?>"> <?php esc_html_e( 'days kept (generated files are not deleted)', 'flx-ai' ); ?></td></tr>
			</table>
		</section>

		<section class="flxai-card" id="web">
			<h2><?php esc_html_e( 'Web', 'flx-ai' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Chat, scheduled tasks and agents can search the web and read public pages. Page content is treated as data, never as instructions. Local and private network addresses cannot be read.', 'flx-ai' ); ?></p>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Web search', 'flx-ai' ); ?></th><td><label class="flxai-check"><input type="checkbox" name="web_on" value="1" <?php checked( ! empty( $s['web_on'] ) ); ?>> <?php esc_html_e( 'Allow the flxai_web_search tool', 'flx-ai' ); ?></label></td></tr>
				<tr><th><?php esc_html_e( 'Read web pages', 'flx-ai' ); ?></th><td><label class="flxai-check"><input type="checkbox" name="web_fetch_on" value="1" <?php checked( ! empty( $s['web_fetch_on'] ) ); ?>> <?php esc_html_e( 'Allow the flxai_web_fetch tool (no key needed)', 'flx-ai' ); ?></label></td></tr>
				<tr><th><?php esc_html_e( 'Virtual shell', 'flx-ai' ); ?></th><td><label class="flxai-check"><input type="checkbox" name="shell_on" value="1" <?php checked( ! empty( $s['shell_on'] ) ); ?>> <?php esc_html_e( 'Allow the flxai_sh tool: shell-like commands (ls, grep, sed, jq, curl…) on the workspace files. No real processes; the workspace rules apply.', 'flx-ai' ); ?></label></td></tr>
				<?php
				$flxai_wps = flxai_web_providers();
				foreach ( $flxai_wps as $flxai_wid => $flxai_w ) :
					if ( empty( $flxai_w['key'] ) ) {
						continue;
					}
					$flxai_k = $flxai_w['key'];
					$flxai_v = flxai_setting( $flxai_k );
					?>
					<tr><th><label for="flxai-<?php echo esc_attr( $flxai_k ); ?>">
						<?php
						/* translators: %s: provider */
						echo esc_html( sprintf( __( '%s API key', 'flx-ai' ), $flxai_w['label'] ) );
						?>
					</label></th><td>
						<input id="flxai-<?php echo esc_attr( $flxai_k ); ?>" type="password" class="regular-text" name="<?php echo esc_attr( $flxai_k ); ?>" value="" placeholder="<?php echo esc_attr( $flxai_v ? flxai_mask( $flxai_v ) : 'BSA…' ); ?>" autocomplete="new-password">
						<?php if ( $flxai_v ) : ?><label class="flxai-check"><input type="checkbox" name="<?php echo esc_attr( $flxai_k ); ?>_clear" value="1"> <?php esc_html_e( 'Remove', 'flx-ai' ); ?></label><?php endif; ?>
						<p class="description"><?php echo esc_html( (string) ( $flxai_w['help'] ?? '' ) ); ?></p></td></tr>
				<?php endforeach; ?>
				<tr><th><label for="flxai-web-provider"><?php esc_html_e( 'Search provider', 'flx-ai' ); ?></label></th><td><select id="flxai-web-provider" name="web_provider">
					<option value="auto" <?php selected( $s['web_provider'] ?? 'auto', 'auto' ); ?>><?php esc_html_e( 'Automatic (first provider with a key)', 'flx-ai' ); ?></option>
					<?php foreach ( $flxai_wps as $flxai_wid => $flxai_w ) : ?>
						<option value="<?php echo esc_attr( $flxai_wid ); ?>" <?php selected( $s['web_provider'] ?? 'auto', $flxai_wid ); ?>><?php echo esc_html( $flxai_w['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
					<p class="description"><?php esc_html_e( 'More providers can be added by plugins with the flxai_web_providers filter.', 'flx-ai' ); ?></p></td></tr>
				<tr><th><label for="flxai-web-month"><?php esc_html_e( 'Monthly search limit', 'flx-ai' ); ?></label></th><td><input id="flxai-web-month" type="number" min="0" class="small-text" name="web_search_month" value="<?php echo esc_attr( (int) ( $s['web_search_month'] ?? 0 ) ); ?>">
					<p class="description">
						<?php
						/* translators: %s: searches this month */
						echo esc_html( sprintf( __( '0: no limit. This month: %s searches.', 'flx-ai' ), number_format_i18n( flxai_web_month_count() ) ) );
						?>
					</p></td></tr>
				<tr><th><label for="flxai-web-cost"><?php esc_html_e( 'Cost per 1,000 searches (USD)', 'flx-ai' ); ?></label></th><td><input id="flxai-web-cost" type="number" min="0" step="0.01" class="small-text" name="web_search_cost" value="<?php echo esc_attr( (float) ( $s['web_search_cost'] ?? 0 ) ); ?>">
					<p class="description"><?php esc_html_e( 'Above 0, each search is written to Logs and counts toward the spending limit. 0 if your plan is free.', 'flx-ai' ); ?></p></td></tr>
			</table>
		</section>

		<section class="flxai-card" id="widgets">
			<h2><?php esc_html_e( 'Visuals', 'flx-ai' ); ?></h2>
			<?php
			$flxai_rnd = flxai_widget_renderer();
			$flxai_rk  = flxai_setting( 'key_render' );
			?>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Visuals in chat', 'flx-ai' ); ?></th><td><label class="flxai-check"><input type="checkbox" name="chat_widgets" value="1" <?php checked( $s['chat_widgets'] ?? 1 ); ?>> <?php esc_html_e( 'Let the model draw tables, cards, diagrams and charts as visuals (```widget and ```mermaid)', 'flx-ai' ); ?></label>
					<p class="description"><?php esc_html_e( 'Works with any model. Visuals are drawn in a separate, isolated frame with no external links, forms or access to the site.', 'flx-ai' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Server-side visual renderer', 'flx-ai' ); ?></th><td>
					<p><?php echo esc_html( 'gotenberg' === $flxai_rnd ? __( 'Gotenberg: HTML and SVG visuals can be sent as PNG.', 'flx-ai' ) : ( 'imagick' === $flxai_rnd ? __( 'Imagick: only SVG visuals can be sent as PNG.', 'flx-ai' ) : __( 'None: channels like WhatsApp get text instead of visuals.', 'flx-ai' ) ) ); ?></p>
					<input type="url" class="regular-text" name="render_url" value="<?php echo esc_attr( $s['render_url'] ?? '' ); ?>" placeholder="https://gotenberg.ornek.com">
					<p class="description"><?php esc_html_e( 'Optional. Channels like WhatsApp and email cannot show HTML; visuals are converted to PNG. Shared hosting has no browser, so HTML visuals are converted by Gotenberg on a Docker server (gotenberg/gotenberg:8).', 'flx-ai' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Gotenberg user / password', 'flx-ai' ); ?></th><td>
					<input type="text" class="regular-text" name="render_user" value="<?php echo esc_attr( $s['render_user'] ?? '' ); ?>" autocomplete="off" style="max-width:180px">
					<input type="password" class="regular-text" name="key_render" value="" placeholder="<?php echo esc_attr( $flxai_rk ? flxai_mask( $flxai_rk ) : '' ); ?>" autocomplete="new-password" style="max-width:220px">
					<?php if ( $flxai_rk ) : ?><label class="flxai-check"><input type="checkbox" name="key_render_clear" value="1"> <?php esc_html_e( 'Remove', 'flx-ai' ); ?></label><?php endif; ?>
					<p class="description"><?php esc_html_e( 'If Gotenberg is protected with basic authentication.', 'flx-ai' ); ?></p>
					<p><button type="button" class="button" data-flxai-render-test><?php esc_html_e( 'Test', 'flx-ai' ); ?></button><span class="flxai-test-out"></span></p>
					<div class="flxai-render-prev" hidden></div></td></tr>
			</table>
		</section>

		<section class="flxai-card">
			<h2><?php esc_html_e( 'Logs', 'flx-ai' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php $flxai_ld = (int) ( $s['log_days'] ?? 0 ); $flxai_bd = (int) ( $s['log_body_days'] ?? 0 ); ?>
				<tr><th><?php esc_html_e( 'Keep usage logs for', 'flx-ai' ); ?></th><td><input type="number" min="0" max="3650" class="small-text" name="log_days" value="<?php echo esc_attr( $flxai_ld > 0 ? $flxai_ld : '' ); ?>" placeholder="<?php esc_attr_e( 'Unlimited', 'flx-ai' ); ?>" style="width:90px"> <?php esc_html_e( 'days', 'flx-ai' ); ?>
					<p class="description"><?php esc_html_e( 'Leave empty to keep logs forever. If you enter days, older logs are deleted once a day.', 'flx-ai' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Content log', 'flx-ai' ); ?></th><td><label class="flxai-check"><input type="checkbox" name="log_bodies" value="1" <?php checked( $s['log_bodies'] ?? 1 ); ?>> <?php esc_html_e( 'Also store outgoing/incoming content (shortened; e-mail, phone, IBAN and keys masked)', 'flx-ai' ); ?></label>
					<p><input type="number" min="0" max="3650" class="small-text" name="log_body_days" value="<?php echo esc_attr( $flxai_bd > 0 ? $flxai_bd : '' ); ?>" placeholder="<?php esc_attr_e( 'Unlimited', 'flx-ai' ); ?>" style="width:90px"> <?php esc_html_e( 'days to keep', 'flx-ai' ); ?></p>
					<p class="description"><?php esc_html_e( 'Leave empty to keep content forever too.', 'flx-ai' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'On uninstall', 'flx-ai' ); ?></th><td><label class="flxai-check"><input type="checkbox" name="delete_data" value="1" <?php checked( $s['delete_data'] ); ?>> <?php esc_html_e( 'Delete logs and chats when the plugin is deleted', 'flx-ai' ); ?></label></td></tr>
			</table>
		</section>
		<p class="submit"><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save', 'flx-ai' ); ?></button></p>
	</form>
	</div>
	<?php
}

/**
 * Genel bakışın üstündeki sohbet kutusu: yaz, dosya ekle, sesle yaz, gönder. Gönderince ortak sohbet paneli
 * yeni sohbetle açılır ve konuşma orada sürer (chat.js window.flxaiChat.start).
 */
function flxai_home_composer() {
	if ( ! function_exists( 'flxai_dock_enabled' ) || ! flxai_dock_enabled() || ! flxai_can_chat() || ! flxai_providers_ready() ) {
		return;
	}
	$user = wp_get_current_user();
	$name = $user && $user->first_name ? $user->first_name : ( $user ? $user->display_name : '' );
	$svg  = function ( $d ) {
		return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
	};
	$chips = array();
	foreach ( array_slice( flxai_skills_all( true ), 0, 3 ) as $sk ) {
		$chips[] = array( $sk['name'], '/' . $sk['slug'] . ' ' );
	}
	$chips[] = array( __( 'Schedule a task', 'flx-ai' ), __( 'Create a scheduled task: every morning at 9, ', 'flx-ai' ) );
	$chips[] = array( __( 'New skill', 'flx-ai' ), __( 'Create a skill that ', 'flx-ai' ) );
	echo '<section class="flxai-home" data-flxai-home>';
	/* translators: %s: user first name */
	echo '<h2 class="flxai-home-hi">' . esc_html( '' !== $name ? sprintf( __( 'Hi %s, what shall we do today?', 'flx-ai' ), $name ) : __( 'What shall we do today?', 'flx-ai' ) ) . '</h2>';
	echo '<form class="flxai-home-box" data-flxai-home-form>';
	echo '<div class="flxai-home-files" hidden></div>';
	echo '<label class="screen-reader-text" for="flxai-home-ta">' . esc_html__( 'Message', 'flx-ai' ) . '</label>';
	echo '<textarea id="flxai-home-ta" rows="2" placeholder="' . esc_attr__( 'Ask anything or give the assistant a job…', 'flx-ai' ) . '"></textarea>';
	echo '<div class="flxai-home-bar">';
	echo '<button type="button" class="flxai-hb" data-attach title="' . esc_attr__( 'Attach file', 'flx-ai' ) . '" aria-label="' . esc_attr__( 'Attach file', 'flx-ai' ) . '">' . $svg( '<path d="m21 12-8.6 8.6a5 5 0 0 1-7-7L14 5a3.3 3.3 0 0 1 4.7 4.7l-8.6 8.6a1.7 1.7 0 0 1-2.4-2.4L15.5 8"/>' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<input type="file" multiple hidden data-file accept="' . esc_attr( '.' . implode( ',.', array_keys( flxai_attach_types() ) ) ) . '">';
	echo '<span class="flxai-home-tip">' . esc_html__( 'Enter to send · Shift+Enter for a new line', 'flx-ai' ) . '</span>';
	echo '<button type="button" class="flxai-hb" data-mic hidden title="' . esc_attr__( 'Dictate', 'flx-ai' ) . '" aria-label="' . esc_attr__( 'Dictate', 'flx-ai' ) . '">' . $svg( '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<button type="submit" class="flxai-home-send" disabled aria-label="' . esc_attr__( 'Send', 'flx-ai' ) . '">' . $svg( '<path d="M12 19V5M5 12l7-7 7 7"/>' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div></form>';
	echo '<div class="flxai-home-chips">';
	foreach ( $chips as $c ) {
		echo '<button type="button" class="flxai-chip" data-fill="' . esc_attr( $c[1] ) . '">' . esc_html( $c[0] ) . '</button>';
	}
	echo '</div></section>';
}

/** Tam sayfa sohbet (0.13.0): ortak sohbet paneli bu sayfada tam ekran açılır, sağda çalışma alanı. */
function flxai_page_chat() {
	echo '<div class="wrap flxai flxai-chat-page">';
	if ( ! flxai_dock_enabled() || ! flxai_can_chat() ) {
		echo '<h1>' . esc_html__( 'Chat', 'flx-ai' ) . '</h1><p>' . esc_html__( 'The AI chat is turned off or not set up yet. Check Flux AI & MCP › Settings.', 'flx-ai' ) . '</p>';
	} else {
		echo '<noscript><p>' . esc_html__( 'The chat needs JavaScript.', 'flx-ai' ) . '</p></noscript>';
	}
	echo '</div>';
}
