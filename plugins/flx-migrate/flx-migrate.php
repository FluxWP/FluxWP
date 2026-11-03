<?php
/**
 * Plugin Name:       Flux Migrate
 * Description:       Eski paket adlarıyla kaydedilmiş site verilerini (ayarlar, tablolar, meta, içerik, tema, yükleme klasörleri) FluxWP adlarına taşır. Ad eşlemesi map.json'dadır.
 * Version:           0.1.5
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

require_once __DIR__ . '/includes/flx-brand/loader.php'; // FluxWP signature (tools/brand).

define( 'FLX_MIGRATE_VERSION', '0.1.5' );
define( 'FLX_MIGRATE_DIR', __DIR__ );

require_once __DIR__ . '/includes/class-mapper.php';
require_once __DIR__ . '/includes/class-migrator.php';

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'flx-migrate', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
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
		<p><?php esc_html_e( 'Eski adlarla kaydedilmiş verileri yeni adlara taşır. Önce yedek alın, sonra yeni eklentileri ve temayı yükleyin (etkinleştirmeden), ardından burada önce "Tara", sonra "Aktar" deyin.', 'flx-migrate' ); ?></p>
		<?php if ( $done ) : ?>
			<div class="notice notice-success inline"><p>
				<?php
				/* translators: %s: tarih */
				echo esc_html( sprintf( __( 'Aktarım %s tarihinde tamamlandı. Bu eklentiyi artık silebilirsiniz.', 'flx-migrate' ), wp_date( 'Y-m-d H:i', $done ) ) );
				?>
			</p></div>
		<?php endif; ?>
		<?php if ( $missing ) : ?>
			<div class="notice notice-warning inline">
				<p><strong><?php esc_html_e( 'Önce şu yeni paketleri yükleyin (etkinleştirmeyin):', 'flx-migrate' ); ?></strong></p>
				<ul style="list-style:disc;margin-left:20px">
					<?php foreach ( $missing as $old => $new ) : ?>
						<li><code><?php echo esc_html( $old ); ?></code> → <code><?php echo esc_html( $new ); ?></code></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<table class="widefat striped" style="max-width:720px;margin-top:16px" id="flx-migrate-table">
			<thead><tr><th><?php esc_html_e( 'Adım', 'flx-migrate' ); ?></th><th><?php esc_html_e( 'Değişecek', 'flx-migrate' ); ?></th><th><?php esc_html_e( 'Durum', 'flx-migrate' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $steps as $k => $label ) : ?>
					<tr data-step="<?php echo esc_attr( $k ); ?>"><td><?php echo esc_html( $label ); ?></td><td class="n">–</td><td class="s">–</td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p style="margin-top:16px">
			<button class="button" id="flx-migrate-scan"><?php esc_html_e( 'Tara', 'flx-migrate' ); ?></button>
			<button class="button button-primary" id="flx-migrate-run" <?php disabled( (bool) $missing ); ?>><?php esc_html_e( 'Aktar', 'flx-migrate' ); ?></button>
		</p>
		<div id="flx-migrate-notes"></div>
	</div>
	<script>
	(function () {
		var nonce = <?php echo wp_json_encode( wp_create_nonce( 'flx_migrate' ) ); ?>;
		var steps = <?php echo wp_json_encode( array_keys( $steps ) ); ?>;
		var L = <?php echo wp_json_encode( array( 'confirm' => __( 'Veriler yeni adlara taşınacak. Yedek aldınız mı?', 'flx-migrate' ), 'working' => __( 'Çalışıyor…', 'flx-migrate' ), 'ok' => __( 'Tamam', 'flx-migrate' ), 'err' => __( 'Hata', 'flx-migrate' ), 'done' => __( 'Aktarım bitti. Yeni eklentileri etkin listesinde kontrol edin, önbelleği temizleyin.', 'flx-migrate' ) ) ); ?>;
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
		document.getElementById('flx-migrate-run').addEventListener('click', function () { if (confirm(L.confirm)) go(false); });
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
