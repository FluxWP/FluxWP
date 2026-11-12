<?php
/**
 * Plugin Name:       Flux Migrate
 * Description:       Eski paket adlarıyla kaydedilmiş site verilerini (ayarlar, tablolar, meta, içerik, tema, yükleme klasörleri) FluxWP adlarına taşır. Ad eşlemesi map.json'dadır.
 * Version:           0.1.10
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            FluxWP
 * License:           GPL-2.0-or-later
 * Text Domain:       flx-migrate
 * Domain Path:       /languages
 *
 * @package FlxMigrate
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/languages/locale-loader.php'; // Native locale fallback; no Flux Lang dependency.
require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

define( 'FLX_MIGRATE_VERSION', '0.1.10' );
define( 'FLX_MIGRATE_DIR', __DIR__ );

require_once __DIR__ . '/includes/class-mapper.php';
require_once __DIR__ . '/includes/class-migrator.php';
require_once __DIR__ . '/includes/class-secrets.php';
require_once __DIR__ . '/includes/class-cleanup.php';

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'flx-migrate', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	},
	1
);
add_filter(
	'load_textdomain_mofile',
	static function ( $mofile, $domain ) {
		if ( 'flx-migrate' === $domain && ! is_readable( $mofile ) && ! is_readable( str_replace( '.mo', '.l10n.php', $mofile ) ) && preg_match( '/-en_[A-Z]{2}\.mo$/', $mofile ) ) {
			return FLX_MIGRATE_DIR . '/languages/flx-migrate-en_US.mo';
		}
		return $mofile;
	},
	10,
	2
);

/** Eşleme dosyası (map.json; filtreyle değiştirilebilir). */
function flx_migrate_map(): array {
	$file = (string) apply_filters( 'flx_migrate_map_file', __DIR__ . '/map.json' );
	$map  = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore
	return is_array( $map ) ? $map : array();
}

function flx_migrate_migrator( bool $dry ): Flx_Migrate_Migrator {
	$map = flx_migrate_map();
	return new Flx_Migrate_Migrator( new Flx_Migrate_Mapper( $map ), $map, $dry );
}

function flx_migrate_cleanup(): Flx_Migrate_Cleanup {
	$map = flx_migrate_map();
	return new Flx_Migrate_Cleanup( new Flx_Migrate_Mapper( $map ), $map );
}

/** Eski kalıntılar bölümü (aktarımdan sonra). */
function flx_migrate_cleanup_section() {
	$items = flx_migrate_cleanup()->scan();
	$res   = get_transient( 'flx_migrate_cleanup_' . get_current_user_id() );
	$names = array(
		'table'   => __( 'Old tables', 'flx-migrate' ),
		'backup'  => __( 'Backup tables (sample data of the new plugins)', 'flx-migrate' ),
		'upload'  => __( 'Old upload folders', 'flx-migrate' ),
		'plugin'  => __( 'Old plugins (inactive)', 'flx-migrate' ),
		'theme'   => __( 'Old themes', 'flx-migrate' ),
		'options' => __( 'Old settings', 'flx-migrate' ),
	);
	?>
	<h2 style="margin-top:32px"><?php esc_html_e( 'Leftovers', 'flx-migrate' ); ?></h2>
	<?php if ( is_array( $res ) ) : ?>
		<?php delete_transient( 'flx_migrate_cleanup_' . get_current_user_id() ); ?>
		<div class="notice <?php echo $res[1] ? 'notice-warning' : 'notice-success'; ?> inline"><p>
			<?php
			/* translators: %d: sayı */
			echo esc_html( sprintf( __( '%d items deleted.', 'flx-migrate' ), (int) $res[0] ) );
			if ( $res[1] ) {
				echo ' ' . esc_html__( 'Could not delete:', 'flx-migrate' ) . ' ' . esc_html( implode( ', ', (array) $res[1] ) );
			}
			?>
		</p></div>
	<?php endif; ?>
	<?php if ( ! $items ) : ?>
		<p><?php esc_html_e( 'No tables, folders, plugins or settings with old names are left.', 'flx-migrate' ); ?></p>
		<?php
		return;
	endif;
	?>
	<p><?php esc_html_e( 'What is left under the old names after the migration. Delete it once you have checked that the site works with the new plugins; deleting cannot be undone, take a backup first.', 'flx-migrate' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-flx-confirm-danger data-flx-confirm="<?php echo esc_attr( __( 'The selected items will be deleted permanently. Did you take a backup?', 'flx-migrate' ) ); ?>">
		<input type="hidden" name="action" value="flx_migrate_cleanup">
		<?php wp_nonce_field( 'flx_migrate_cleanup' ); ?>
		<table class="widefat striped" style="max-width:720px">
			<thead><tr><td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.flx-migrate-c').forEach(function(c){c.checked=this.checked}.bind(this))"></td><th><?php esc_html_e( 'What', 'flx-migrate' ); ?></th><th><?php esc_html_e( 'Details', 'flx-migrate' ); ?></th></tr></thead>
			<tbody>
			<?php
			$last = '';
			foreach ( $items as $id => $it ) :
				if ( $it['group'] !== $last ) :
					$last = $it['group'];
					?>
					<tr><td></td><th colspan="2"><strong><?php echo esc_html( $names[ $last ] ?? $last ); ?></strong></th></tr>
				<?php endif; ?>
				<tr>
					<th scope="row" class="check-column"><input type="checkbox" class="flx-migrate-c" name="ids[]" value="<?php echo esc_attr( $id ); ?>" <?php checked( $it['checked'] ); ?>></th>
					<td><code><?php echo esc_html( $it['label'] ); ?></code></td>
					<td><?php echo esc_html( $it['detail'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p><button class="button button-link-delete"><?php esc_html_e( 'Delete selected', 'flx-migrate' ); ?></button></p>
	</form>
	<?php
}

add_action(
	'admin_post_flx_migrate_cleanup',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'flx_migrate_cleanup' );
		if ( ! get_option( 'flx_migrate_done' ) ) {
			wp_die( esc_html__( 'Finish the migration first.', 'flx-migrate' ) );
		}
		$ids = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['ids'] ?? array() ) );
		set_transient( 'flx_migrate_cleanup_' . get_current_user_id(), flx_migrate_cleanup()->delete( $ids ), 300 );
		wp_safe_redirect( admin_url( 'tools.php?page=flx-migrate' ) );
		exit;
	}
);

/* ------------------------------------------------------------------ */
/* Yönetim ekranı: Araçlar › Flux Migrate                            */
/* ------------------------------------------------------------------ */

add_action(
	'admin_menu',
	static function () {
		add_management_page( 'Flux Migrate', 'Flux Migrate', 'manage_options', 'flx-migrate', 'flx_migrate_page' );
	}
);

function flx_migrate_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$mig     = flx_migrate_migrator( true );
	$missing = $mig->missing_plugins();
	$done    = (int) get_option( 'flx_migrate_done', 0 );
	$steps   = Flx_Migrate_Migrator::steps();
	?>
	<div class="wrap">
		<h1>Flux Migrate</h1>
		<p><?php esc_html_e( 'Moves data saved under the old names to the new names. Take a backup first, upload the new plugins and theme (do not activate them), then click "Scan" and after that "Migrate".', 'flx-migrate' ); ?></p>
		<?php if ( $done ) : ?>
			<div class="notice notice-success inline"><p>
				<?php
				/* translators: %s: tarih */
				echo esc_html( sprintf( __( 'Migration finished on %s. You can delete this plugin now.', 'flx-migrate' ), wp_date( 'Y-m-d H:i', $done ) ) );
				?>
			</p></div>
		<?php endif; ?>
		<?php if ( $missing ) : ?>
			<div class="notice notice-warning inline">
				<p><strong><?php esc_html_e( 'Upload these new packages first (do not activate them):', 'flx-migrate' ); ?></strong></p>
				<ul style="list-style:disc;margin-left:20px">
					<?php foreach ( $missing as $old => $new ) : ?>
						<li><code><?php echo esc_html( $old ); ?></code> → <code><?php echo esc_html( $new ); ?></code></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<table class="widefat striped" style="max-width:720px;margin-top:16px" id="flx-migrate-table">
			<thead><tr><th><?php esc_html_e( 'Step', 'flx-migrate' ); ?></th><th><?php esc_html_e( 'To change', 'flx-migrate' ); ?></th><th><?php esc_html_e( 'Status', 'flx-migrate' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $steps as $k => $label ) : ?>
					<tr data-step="<?php echo esc_attr( $k ); ?>"><td><?php echo esc_html( $label ); ?></td><td class="n">–</td><td class="s">–</td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p style="margin-top:16px">
			<button class="button" id="flx-migrate-scan"><?php esc_html_e( 'Scan', 'flx-migrate' ); ?></button>
			<button class="button button-primary" id="flx-migrate-run" <?php disabled( (bool) $missing ); ?>><?php esc_html_e( 'Migrate', 'flx-migrate' ); ?></button>
		</p>
		<div id="flx-migrate-notes"></div>
		<?php
		if ( $done ) {
			flx_migrate_cleanup_section();
		}
		?>
	</div>
	<script>
	(function () {
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'flx_migrate' ) ); ?>;
		var steps = <?php echo wp_json_encode( array_keys( $steps ) ); ?>;
		var L = <?php echo wp_json_encode( array( 'confirm' => __( 'Data will be moved to the new names. Did you take a backup?', 'flx-migrate' ), 'working' => __( 'Working…', 'flx-migrate' ), 'ok' => __( 'Done', 'flx-migrate' ), 'err' => __( 'Error', 'flx-migrate' ), 'done' => __( 'Migration finished. Check that the new plugins are active and clear the cache.', 'flx-migrate' ) ) ); ?>;
		function row(s) { return document.querySelector('#flx-migrate-table tr[data-step="' + s + '"]'); }
		function note(t) { var p = document.createElement('p'); p.textContent = t; document.getElementById('flx-migrate-notes').appendChild(p); }
		function call(step, cursor, dry) {
			var b = new FormData();
			b.append('action', 'flx_migrate_step'); b.append('_ajax_nonce', nonce);
			b.append('step', step); b.append('cursor', cursor); b.append('dry', dry ? 1 : 0);
			return fetch(ajaxurl, { method: 'POST', body: b, credentials: 'same-origin' }).then(function (r) { return r.json(); });
		}
		async function go(dry) {
			document.getElementById('flx-migrate-notes').innerHTML = '';
			for (var i = 0; i < steps.length; i++) {
				var s = steps[i], tr = row(s), total = 0, cursor = 0;
				tr.querySelector('.s').textContent = L.working;
				for (;;) {
					var r = await call(s, cursor, dry);
					if (!r || !r.success) { tr.querySelector('.s').textContent = L.err; note(s + ': ' + ((r && r.data) || L.err)); return; }
					total += r.data.changed; (r.data.notes || []).forEach(note);
					tr.querySelector('.n').textContent = total;
					if (r.data.done) break;
					cursor = r.data.cursor;
				}
				tr.querySelector('.s').textContent = L.ok;
			}
			if (!dry) note(L.done);
		}
		document.getElementById('flx-migrate-scan').addEventListener('click', function () { go(true); });
		document.getElementById('flx-migrate-run').addEventListener('click', function () { window.flxDialog.confirm({ message: L.confirm, danger: true }).then(function (yes) { if (yes) go(false); }); });
	})();
	</script>
	<?php
}

add_action(
	'wp_ajax_flx_migrate_step',
	static function () {
		check_ajax_referer( 'flx_migrate' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}
		$step   = sanitize_key( wp_unslash( $_POST['step'] ?? '' ) );
		$cursor = (int) ( $_POST['cursor'] ?? 0 );
		$dry    = ! empty( $_POST['dry'] );
		if ( ! isset( Flx_Migrate_Migrator::steps()[ $step ] ) ) {
			wp_send_json_error( 'step' );
		}
		wp_send_json_success( flx_migrate_migrator( $dry )->run( $step, $cursor ) );
	}
);

/* ------------------------------------------------------------------ */
/* WP-CLI: wp flx-migrate scan | run                                    */
/* ------------------------------------------------------------------ */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	$flx_migrate_cli = static function ( bool $dry ) {
		$mig = flx_migrate_migrator( $dry );
		foreach ( $mig->missing_plugins() as $old => $new ) {
			WP_CLI::warning( "$old → $new" );
		}
		foreach ( Flx_Migrate_Migrator::steps() as $step => $label ) {
			$total  = 0;
			$cursor = 0;
			do {
				$mig = flx_migrate_migrator( $dry );
				$r   = $mig->run( $step, $cursor );
				$total += $r['changed'];
				foreach ( $r['notes'] as $n ) {
					WP_CLI::warning( $n );
				}
				$cursor = $r['cursor'];
			} while ( ! $r['done'] );
			WP_CLI::log( sprintf( '%-28s %d', $label, $total ) );
		}
		WP_CLI::success( $dry ? 'scan' : 'run' );
	};
	WP_CLI::add_command( 'flx-migrate scan', static function () use ( $flx_migrate_cli ) { $flx_migrate_cli( true ); } );
	WP_CLI::add_command( 'flx-migrate run', static function () use ( $flx_migrate_cli ) { $flx_migrate_cli( false ); } );
}
