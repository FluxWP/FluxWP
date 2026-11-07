<?php
/**
 * Ekranlar: güncelleme sunucusu panelinin (/admin) WordPress içindeki karşılığı.
 * Veriler sunucunun yönetici API'sinden okunur; formlar FLXUM_Admin::handle() üzerinden sunucuya gider.
 *
 * @package FLX_Updater_Manager
 */

defined( 'ABSPATH' ) || exit;

final class FLXUM_Views {

	const ICONS = array(
		'sites'  => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
		'key'    => '<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 9.3-9.3M17 6l3 3M14 9l2 2"/>',
		'check'  => '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
		'down'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
		'data'   => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
		'globe'  => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20z"/>',
		'ext'    => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		'shield' => '<path d="M20 13c0 5-3.5 7.5-7.7 9a1 1 0 0 1-.6 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.2-2.7a1 1 0 0 1 1.6 0C14.5 3.8 17 5 19 5a1 1 0 0 1 1 1z"/>',
	);

	/** @var string */
	private static $tab = 'overview';

	/* ------------------------------------------------------------------ */
	/* Yardımcılar                                                         */
	/* ------------------------------------------------------------------ */

	private static function icon( $name, $size = 16 ) {
		return '<svg class="flxum-i" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . self::ICONS[ $name ] . '</svg>';
	}

	/** Form başı: işlem adı (op) + sekme + nonce. */
	private static function form( $op, $attrs = '', $tab = null ) {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" ' . $attrs . '>'
			. '<input type="hidden" name="action" value="flxum_do"><input type="hidden" name="op" value="' . esc_attr( $op ) . '">'
			. '<input type="hidden" name="tab" value="' . esc_attr( null === $tab ? self::$tab : $tab ) . '">'
			. wp_nonce_field( 'flxum_do', '_wpnonce', true, false );
	}

	private static function confirm( $msg ) {
		return 'onsubmit="return confirm(' . esc_attr( wp_json_encode( $msg ) ) . ')"';
	}

	private static function pill( $text, $tone = 'muted' ) {
		return '<span class="flxum-pill is-' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</span>';
	}

	private static function kpi( $label, $value, $delta, $sub, $tone, $icon ) {
		return '<div class="flxum-kpi is-' . esc_attr( $tone ) . '"><div class="flxum-kpi__top"><span class="flxum-kpi__ico">' . self::icon( $icon ) . '</span><span class="flxum-kpi__label">' . esc_html( $label ) . '</span></div>'
			. '<div class="flxum-kpi__row"><div class="flxum-kpi__value">' . esc_html( $value ) . '</div>' . $delta . '</div><div class="flxum-kpi__sub" title="' . esc_attr( $sub ) . '">' . esc_html( $sub ) . '</div></div>';
	}

	private static function delta( $now, $prev ) {
		if ( $prev <= 0 ) {
			return '';
		}
		$ch  = round( ( $now - $prev ) / $prev * 100, 1 );
		$cls = $ch > 0 ? 'up' : ( $ch < 0 ? 'down' : '' );
		return '<span class="flxum-delta ' . $cls . '" title="' . esc_attr__( 'Önceki eşit döneme göre', 'flx-updater-manager' ) . '">' . ( $ch > 0 ? '↑' : ( $ch < 0 ? '↓' : '' ) ) . esc_html( number_format_i18n( abs( $ch ), 1 ) ) . '%</span>';
	}

	private static function meter( $used, $total, $tone = '' ) {
		$pct = $total > 0 ? min( 100, $used / $total * 100 ) : 0;
		$cls = $tone ? $tone : ( $pct >= 90 ? 'bad' : ( $pct >= 75 ? 'warn' : 'ok' ) );
		return '<div class="flxum-meter is-' . $cls . '"><i style="width:' . esc_attr( round( $pct, 1 ) ) . '%"></i></div>';
	}

	private static function cc( $cc, $name ) {
		return ( '' !== $cc ? '<b class="flxum-cc">' . esc_html( $cc ) . '</b>' : '' ) . esc_html( $name );
	}

	private static function error_box( WP_Error $e ) {
		echo '<div class="flxum-callout is-bad">' . esc_html( $e->get_error_message() ) . '</div>';
	}

	/** Sekmeler arası veri: ayarlar (bir istekte bir kez). */
	private static function settings() {
		return FLXUM_Client::get( 'settings' );
	}

	/* ------------------------------------------------------------------ */
	/* Sayfa                                                               */
	/* ------------------------------------------------------------------ */

	public static function render() {
		if ( ! current_user_can( FLXUM_Admin::CAP ) ) {
			return;
		}
		$tabs = FLXUM_Admin::tabs();
		$tab  = sanitize_key( wp_unslash( $_GET['tab'] ?? 'overview' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'overview';
		$info = FLXUM_Client::configured() ? FLXUM_Client::get( 'info' ) : new WP_Error( 'flxum_no_key', '' );
		if ( is_wp_error( $info ) ) {
			$tab = 'connection';
		}
		self::$tab = $tab;
		$flash     = FLXUM_Admin::take_flash();
		?>
		<div class="wrap flxum">
			<div class="flxum-head">
				<div>
					<h1><?php esc_html_e( 'Updater Manager', 'flx-updater-manager' ); ?></h1>
					<p class="flxum-lead">
						<?php if ( is_wp_error( $info ) ) : ?>
							<?php esc_html_e( 'FluxWP güncelleme sunucusunu buradan yönetin: istatistikler, siteler, anahtarlar, paketler ve güvenlik.', 'flx-updater-manager' ); ?>
						<?php else : ?>
							<?php
							/* translators: 1: sunucu adresi, 2: sürüm */
							echo esc_html( sprintf( __( '%1$s · Flux Update Server %2$s', 'flx-updater-manager' ), (string) wp_parse_url( (string) $info['base_url'], PHP_URL_HOST ), (string) $info['version'] ) );
							?>
						<?php endif; ?>
					</p>
				</div>
				<?php if ( ! is_wp_error( $info ) ) : ?>
					<a class="button" href="<?php echo esc_url( (string) $info['admin_url'] ); ?>" target="_blank" rel="noopener"><?php echo self::icon( 'ext', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Sunucu paneli', 'flx-updater-manager' ); ?></a>
				<?php endif; ?>
			</div>

			<?php if ( ! is_wp_error( $info ) ) : ?>
			<nav class="flxum-tabs" aria-label="<?php esc_attr_e( 'Bölümler', 'flx-updater-manager' ); ?>">
				<?php foreach ( $tabs as $k => $label ) : ?>
					<a href="<?php echo esc_url( flxum_admin_url( $k ) ); ?>"<?php echo $k === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
				<?php if ( ! empty( $info['lockdown'] ) ) : ?>
					<div class="flxum-callout is-bad"><b><?php esc_html_e( 'Acil durdurma açık.', 'flx-updater-manager' ); ?></b> <?php esc_html_e( 'Hiçbir siteye güncelleme, paket ya da uygulama dağıtılmıyor (503).', 'flx-updater-manager' ); ?> <a href="<?php echo esc_url( flxum_admin_url( 'security' ) ); ?>"><?php esc_html_e( 'Güvenlik', 'flx-updater-manager' ); ?></a></div>
				<?php elseif ( ! empty( $info['public_off'] ) ) : ?>
					<div class="flxum-callout is-warn"><b><?php esc_html_e( 'Anahtarsız dağıtım kapalı.', 'flx-updater-manager' ); ?></b> <?php esc_html_e( 'Yalnız aktif site anahtarı olan siteler paket alıyor.', 'flx-updater-manager' ); ?> <a href="<?php echo esc_url( flxum_admin_url( 'security' ) ); ?>"><?php esc_html_e( 'Güvenlik', 'flx-updater-manager' ); ?></a></div>
				<?php endif; ?>
			<?php endif; ?>

			<?php self::flash( $flash ); ?>

			<?php
			switch ( $tab ) {
				case 'countries':
					self::tab_countries();
					break;
				case 'sites':
					self::tab_sites();
					break;
				case 'packages':
					self::tab_packages();
					break;
				case 'github':
					self::tab_github();
					break;
				case 'security':
					self::tab_security();
					break;
				case 'resources':
					self::tab_resources();
					break;
				case 'connection':
					self::tab_connection( $info );
					break;
				default:
					self::tab_overview();
			}
			?>
		</div>
		<?php
	}

	/** İşlem sonucu: bildirim, yeni anahtar (bir kez), anahtar testi, IP sorgusu. */
	private static function flash( array $f ) {
		if ( ! $f ) {
			return;
		}
		if ( '' !== (string) ( $f['message'] ?? '' ) ) {
			echo '<div class="flxum-notice ' . ( ! empty( $f['ok'] ) ? 'is-ok' : 'is-bad' ) . '">' . esc_html( $f['message'] ) . '</div>';
		}
		$d = (array) ( $f['data'] ?? array() );
		if ( ! empty( $d['new_key'] ) ) {
			$key = flxum_unseal( $d['new_key'] );
			?>
			<div class="flxum-card flxum-newkey">
				<h2><?php echo self::icon( 'key' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( sprintf( /* translators: %s: site adı */ __( 'Yeni site anahtarı: %s', 'flx-updater-manager' ), (string) ( $d['new_name'] ?? '' ) ) ); ?></h2>
				<?php if ( ! empty( $d['issued'] ) ) : ?>
					<p><?php esc_html_e( 'Site bu anahtarı bir sonraki güncelleme kontrolünde kendiliğinden alır (7 gün içinde; hemen almak için sitede FluxWP Panel › Ayarlar › "Bağlantıyı test et"). Elle girmeniz gerekmez. Şimdi Siteler sekmesinden erişimini seçin; seçene kadar site yalnız public paketleri görür.', 'flx-updater-manager' ); ?></p>
				<?php else : ?>
					<p><?php esc_html_e( 'Bu anahtar bir daha gösterilmez. Şimdi kopyalayın ve sitenin FluxWP Panel › Ayarlar ekranında "Site anahtarı" alanına yapıştırın.', 'flx-updater-manager' ); ?></p>
				<?php endif; ?>
				<div class="flxum-copy"><code id="flxum-newkey"><?php echo esc_html( $key ); ?></code><button type="button" class="button" data-copy="#flxum-newkey"><?php esc_html_e( 'Kopyala', 'flx-updater-manager' ); ?></button></div>
			</div>
			<?php
		}
		if ( isset( $d['geo_lookup'] ) && is_array( $d['geo_lookup'] ) ) {
			$cc = $d['geo_lookup'][1] ?? null;
			echo '<div class="flxum-notice is-info">' . esc_html( (string) $d['geo_lookup'][0] ) . ': ' . ( null === $cc ? esc_html__( 'Geçerli bir IP adresi girin.', 'flx-updater-manager' ) : ( '' !== $cc ? '<b class="flxum-cc">' . esc_html( $cc ) . '</b>' : esc_html__( 'Bulunamadı', 'flx-updater-manager' ) ) ) . '</div>';
		}
		if ( ! empty( $f['test'] ) ) {
			$t = $f['test'];
			echo '<div class="flxum-card"><h2>' . esc_html__( 'Anahtar testi', 'flx-updater-manager' ) . '</h2>';
			if ( empty( $t['valid'] ) ) {
				echo '<p>' . self::pill( __( 'Geçersiz', 'flx-updater-manager' ), 'bad' ) . ' ' . esc_html( (string) ( $t['message'] ?? '' ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
			} else {
				echo '<p>' . ( empty( $t['active'] ) ? self::pill( __( 'Pasif', 'flx-updater-manager' ), 'bad' ) . ' ' : '' ) . self::pill( __( 'Geçerli', 'flx-updater-manager' ), 'ok' ) . ' <b>' . esc_html( (string) $t['name'] ) . '</b> · ' . esc_html( 'beta' === $t['channel'] ? __( 'Beta dahil', 'flx-updater-manager' ) : __( 'Kararlı', 'flx-updater-manager' ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
				/* translators: %d: paket sayısı */
				echo '<p class="flxum-muted">' . esc_html( sprintf( __( 'Görebileceği paketler (%d):', 'flx-updater-manager' ), count( (array) $t['visible'] ) ) . ' ' . ( implode( ', ', (array) $t['visible'] ) ?: '—' ) ) . '</p>';
				/* translators: %s: tarih */
				echo '<p class="flxum-muted">' . esc_html( $t['last_seen'] ? sprintf( __( 'Site en son %s bağlandı.', 'flx-updater-manager' ), $t['last_seen'] ) : __( 'Site henüz bağlanmadı.', 'flx-updater-manager' ) ) . '</p>';
			}
			echo '</div>';
		}
	}

	/* ------------------------------------------------------------------ */
	/* Genel bakış                                                         */
	/* ------------------------------------------------------------------ */

	private static function range() {
		$r = absint( $_GET['r'] ?? 30 ); // phpcs:ignore WordPress.Security.NonceVerification
		return in_array( $r, array( 7, 30, 90 ), true ) ? $r : 30;
	}

	private static function range_seg() {
		echo '<div class="flxum-seg" role="tablist">';
		foreach ( array( 7, 30, 90 ) as $k ) {
			/* translators: %d: gün sayısı */
			echo '<a role="tab" href="' . esc_url( flxum_admin_url( self::$tab, array( 'r' => $k ) ) ) . '" aria-selected="' . ( self::range() === $k ? 'true' : 'false' ) . '">' . esc_html( sprintf( __( '%d gün', 'flx-updater-manager' ), $k ) ) . '</a>';
		}
		echo '</div>';
	}

	private static function pkg_name( array $O, $slug ) {
		return (string) ( $O['packages'][ $slug ]['name'] ?? $slug );
	}

	private static function tab_overview() {
		$O = FLXUM_Client::get( 'overview', array( 'r' => self::range() ) );
		if ( is_wp_error( $O ) ) {
			self::error_box( $O );
			return;
		}
		$T     = $O['totals'];
		$P     = $O['prev'];
		$known = 0;
		$unk   = 0;
		foreach ( $O['countries'] as $c ) {
			if ( '' === $c['cc'] ) {
				$unk = (int) $c['sites'];
			} else {
				++$known;
			}
		}
		?>
		<div class="flxum-sec-head"><h2><?php esc_html_e( 'Genel bakış', 'flx-updater-manager' ); ?></h2><?php self::range_seg(); ?></div>
		<div class="flxum-kpis">
			<?php
			// phpcs:disable WordPress.Security.EscapeOutput -- kpi() kaçışlı HTML döndürür.
			/* translators: %s: sayı */
			echo self::kpi( __( 'Aktif site', 'flx-updater-manager' ), flxum_num( $T['sites'] ), self::delta( $T['sites'], $P['sites'] ), sprintf( __( 'Önceki dönem: %s', 'flx-updater-manager' ), flxum_num( $P['sites'] ) ), 'green', 'sites' );
			/* translators: %s: sayı */
			echo self::kpi( __( 'Anahtarlı', 'flx-updater-manager' ), flxum_num( $T['keyed'] ), '', sprintf( __( '%s site anahtarsız (public)', 'flx-updater-manager' ), flxum_num( $T['sites'] - $T['keyed'] ) ), 'violet', 'key' );
			/* translators: %s: sayı */
			echo self::kpi( __( 'Kontrol isteği', 'flx-updater-manager' ), flxum_num( $T['checks'] ), self::delta( $T['checks'], $P['checks'] ), sprintf( __( '%s anahtarsız', 'flx-updater-manager' ), flxum_num( $T['anon'] ) ), 'blue', 'check' );
			/* translators: %s: sayı */
			echo self::kpi( __( 'İndirme', 'flx-updater-manager' ), flxum_num( $T['downloads'] ), self::delta( $T['downloads'], $P['downloads'] ), sprintf( __( 'Önceki dönem: %s', 'flx-updater-manager' ), flxum_num( $P['downloads'] ) ), 'amber', 'down' );
			echo self::kpi( __( 'Aktarılan', 'flx-updater-manager' ), flxum_size( $T['bytes'] ), '', __( 'Zip indirmeleri', 'flx-updater-manager' ), 'teal', 'data' );
			/* translators: %s: sayı */
			echo self::kpi( __( 'Ülke', 'flx-updater-manager' ), flxum_num( $known ), '', $unk ? sprintf( __( '%s sitenin ülkesi bilinmiyor', 'flx-updater-manager' ), flxum_num( $unk ) ) : __( 'İstekten, IP saklanmadan', 'flx-updater-manager' ), 'rose', 'globe' );
			// phpcs:enable
			?>
		</div>

		<div class="flxum-card">
			<div class="flxum-card__head">
				<h2><?php esc_html_e( 'Günlük kullanım', 'flx-updater-manager' ); ?></h2>
				<div class="flxum-legend"><span><i class="c"></i><?php esc_html_e( 'Kontrol', 'flx-updater-manager' ); ?></span><span><i class="s"></i><?php esc_html_e( 'Tekil site', 'flx-updater-manager' ); ?></span><span><i class="dl"></i><?php esc_html_e( 'İndirme', 'flx-updater-manager' ); ?></span></div>
			</div>
			<div class="flxum-chart flxum-tip-host"><?php echo flxum_kses_svg( $O['chart_svg'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?><div class="flxum-tip"></div></div>
		</div>

		<div class="flxum-grid2">
			<div class="flxum-card">
				<h2><?php esc_html_e( 'Paketler', 'flx-updater-manager' ); ?></h2>
				<?php
				$rows = array();
				foreach ( (array) $O['installs'] as $slug => $n ) {
					$rows[ $slug ] = array( (int) $n, (int) ( $O['downloads'][ $slug ] ?? 0 ) );
				}
				foreach ( (array) $O['downloads'] as $slug => $n ) {
					if ( ! isset( $rows[ $slug ] ) ) {
						$rows[ $slug ] = array( 0, (int) $n );
					}
				}
				$rows = array_slice( $rows, 0, 12, true );
				$pmax = 0;
				foreach ( $rows as $r ) {
					$pmax = max( $pmax, $r[0] );
				}
				?>
				<?php if ( ! $rows ) : ?>
					<p class="flxum-empty"><?php esc_html_e( 'Bu aralıkta kayıt yok.', 'flx-updater-manager' ); ?></p>
				<?php else : ?>
				<table class="flxum-bars">
					<tr><th><?php esc_html_e( 'Paket', 'flx-updater-manager' ); ?></th><th class="n"><?php esc_html_e( 'Kurulu site', 'flx-updater-manager' ); ?></th><th class="n"><?php esc_html_e( 'İndirme', 'flx-updater-manager' ); ?></th></tr>
					<?php foreach ( $rows as $slug => $r ) : ?>
						<tr>
							<td class="lbl"><span class="bar" style="width:<?php echo esc_attr( $pmax ? round( $r[0] / $pmax * 100, 1 ) : 0 ); ?>%"></span><span class="txt"><?php echo esc_html( self::pkg_name( $O, $slug ) ); ?><?php echo ! empty( $O['packages'][ $slug ]['public'] ) ? ' <small>public</small>' : ''; ?></span></td>
							<td class="n"><?php echo esc_html( flxum_num( $r[0] ) ); ?></td>
							<td class="n"><?php echo esc_html( flxum_num( $r[1] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php endif; ?>
			</div>
			<div class="flxum-card">
				<h2><?php esc_html_e( 'Ülkeler', 'flx-updater-manager' ); ?></h2>
				<div class="flxum-map flxum-tip-host"><?php echo flxum_kses_svg( $O['map_svg'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?><div class="flxum-tip"></div></div>
				<div class="flxum-map-legend"><span><?php esc_html_e( 'Az', 'flx-updater-manager' ); ?></span><i class="l1"></i><i class="l2"></i><i class="l3"></i><i class="l4"></i><i class="l5"></i><span><?php esc_html_e( 'Çok', 'flx-updater-manager' ); ?></span></div>
				<?php if ( $O['countries'] ) : ?>
					<?php $cmax = max( 1, (int) $O['countries'][0]['sites'] ); ?>
					<table class="flxum-bars">
						<?php foreach ( array_slice( $O['countries'], 0, 6 ) as $c ) : ?>
							<?php /* translators: %s: sayı */ ?>
							<tr><td class="lbl"><span class="bar" style="width:<?php echo esc_attr( round( $c['sites'] / $cmax * 100, 1 ) ); ?>%"></span><span class="txt"><?php echo self::cc( $c['cc'], $c['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></td><td class="n"><?php echo esc_html( sprintf( __( '%s site', 'flx-updater-manager' ), flxum_num( $c['sites'] ) ) ); ?></td></tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<div class="flxum-card" id="clients">
			<div class="flxum-card__head">
				<h2><?php esc_html_e( 'Bağlanan siteler', 'flx-updater-manager' ); ?></h2>
				<?php /* translators: 1: aralıktaki site, 2: toplam */ ?>
				<span class="flxum-muted"><?php echo esc_html( sprintf( __( '%1$s site bu aralıkta, toplam %2$s kayıtlı', 'flx-updater-manager' ), flxum_num( count( $O['clients'] ) ), flxum_num( $O['all_clients'] ) ) ); ?></span>
			</div>
			<?php if ( ! $O['clients'] ) : ?>
				<p class="flxum-empty"><?php esc_html_e( 'Bu aralıkta güncelleme denetleyen site yok.', 'flx-updater-manager' ); ?></p>
			<?php else : ?>
			<div class="flxum-table-wrap"><table class="flxum-table">
				<thead><tr><th><?php esc_html_e( 'Site', 'flx-updater-manager' ); ?></th><th><?php esc_html_e( 'Erişim', 'flx-updater-manager' ); ?></th><th><?php esc_html_e( 'Ülke', 'flx-updater-manager' ); ?></th><th class="hide-sm"><?php esc_html_e( 'Ortam', 'flx-updater-manager' ); ?></th><th class="n"><?php esc_html_e( 'Paket', 'flx-updater-manager' ); ?></th><th class="n hide-sm"><?php esc_html_e( 'İstek', 'flx-updater-manager' ); ?></th><th><?php esc_html_e( 'Son', 'flx-updater-manager' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $O['clients'] as $i => $c ) : ?>
					<?php
					$host = (string) wp_parse_url( $c['url'], PHP_URL_HOST );
					$path = rtrim( (string) wp_parse_url( $c['url'], PHP_URL_PATH ), '/' );
					?>
					<tr<?php echo $i >= 15 ? ' class="flxum-more" hidden' : ''; ?>>
						<td><b><?php echo esc_html( '' !== $host ? $host . $path : __( 'Adres bildirmedi', 'flx-updater-manager' ) ); ?></b><?php if ( '' !== $c['name'] ) : ?><br><span class="flxum-muted"><?php echo esc_html( $c['name'] ); ?></span><?php endif; ?></td>
						<td><?php self::client_access( $c ); ?></td>
						<td class="flxum-small"><?php echo '' !== $c['cc'] ? self::cc( $c['cc'], $c['country'] ) : '<span class="flxum-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td class="flxum-small flxum-muted hide-sm"><?php echo esc_html( trim( ( $c['wp'] ? 'WP ' . $c['wp'] : '' ) . ( $c['php'] ? ' · PHP ' . $c['php'] : '' ) ) ); ?><?php if ( '' !== $c['panel'] ) : ?><br>FluxWP Panel <?php echo esc_html( $c['panel'] ); ?><?php endif; ?></td>
						<td class="n" title="<?php echo esc_attr( implode( ', ', array_keys( (array) $c['packages'] ) ) ); ?>"><?php echo esc_html( flxum_num( count( (array) $c['packages'] ) ) ); ?></td>
						<td class="n hide-sm"><?php echo esc_html( flxum_num( $c['checks'] ) ); ?></td>
						<td class="flxum-small"><?php echo esc_html( flxum_ago( $c['last'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table></div>
				<?php if ( count( $O['clients'] ) > 15 ) : ?>
					<?php /* translators: %s: sayı */ ?>
					<p><button type="button" class="button" data-more="#clients"><?php echo esc_html( sprintf( __( 'Tümünü göster (%s)', 'flx-updater-manager' ), flxum_num( count( $O['clients'] ) ) ) ); ?></button></p>
				<?php endif; ?>
			<?php endif; ?>
			<p class="flxum-muted"><?php esc_html_e( 'Zorunlu kayıt yalnız site adresi ve ülkedir. IP saklanmaz: ülke, istek anında IP\'den bulunur ve yalnız ülke kodu tutulur. WordPress/PHP sürümü yalnız kullanım verisi izniyle.', 'flx-updater-manager' ); ?></p>
		</div>
		<?php
	}

	/** "Bağlanan siteler" erişim hücresi: anahtarlı/pasif/teslim bekliyor ya da "Anahtar ver". */
	private static function client_access( array $c ) {
		switch ( $c['access'] ) {
			case 'keyed':
				echo self::pill( __( 'Anahtarlı', 'flx-updater-manager' ), 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput
				return;
			case 'off':
				echo self::pill( __( 'Pasif', 'flx-updater-manager' ), 'bad' ); // phpcs:ignore WordPress.Security.EscapeOutput
				return;
			case 'pending':
				echo self::pill( __( 'Teslim bekliyor', 'flx-updater-manager' ), 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput
				return;
			case 'needs_setup':
				echo self::pill( 'Public' ) . '<br><span class="flxum-muted flxum-small">' . esc_html__( 'FluxWP Panel gerekli', 'flx-updater-manager' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
				return;
		}
		echo self::pill( 'Public' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo self::form( 'issue_key', 'class="flxum-inline-form"' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<input type="hidden" name="client" value="' . esc_attr( $c['id'] ) . '"><button class="button button-small">' . esc_html__( 'Anahtar ver', 'flx-updater-manager' ) . '</button></form>';
	}

	/* ------------------------------------------------------------------ */
	/* Ülkeler                                                             */
	/* ------------------------------------------------------------------ */

	private static function tab_countries() {
		$O = FLXUM_Client::get( 'overview', array( 'r' => self::range() ) );
		if ( is_wp_error( $O ) ) {
			self::error_box( $O );
			return;
		}
		$geo  = $O['geo'];
		$gmax = 1;
		$n    = 0;
		foreach ( $geo as $r ) {
			$gmax = max( $gmax, $r['checks'] + $r['downloads'] );
			$n   += '' !== $r['cc'] ? 1 : 0;
		}
		?>
		<div class="flxum-sec-head"><h2><?php esc_html_e( 'Ülke trafiği', 'flx-updater-manager' ); ?></h2><?php self::range_seg(); ?></div>
		<div class="flxum-card" id="geo">
			<?php /* translators: 1: ülke sayısı, 2: engellenen istek */ ?>
			<p class="flxum-muted"><?php echo esc_html( sprintf( __( '%1$s ülke · %2$s engellenen istek', 'flx-updater-manager' ), flxum_num( $n ), flxum_num( $O['totals']['blocked'] ) ) ); ?></p>
			<?php if ( empty( $O['geo_db'] ) ) : ?>
				<p><?php echo self::pill( __( 'Ülke veritabanı yok', 'flx-updater-manager' ), 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Cloudflare arkasında değilseniz ülkeler bilinmiyor görünür.', 'flx-updater-manager' ); ?> <a href="<?php echo esc_url( flxum_admin_url( 'security' ) ); ?>"><?php esc_html_e( 'Veritabanını indir', 'flx-updater-manager' ); ?></a></p>
			<?php endif; ?>
			<?php if ( ! $geo ) : ?>
				<p class="flxum-empty"><?php esc_html_e( 'Bu aralıkta kayıt yok.', 'flx-updater-manager' ); ?></p>
			<?php else : ?>
			<div class="flxum-table-wrap"><table class="flxum-bars flxum-geo">
				<tr><th><?php esc_html_e( 'Ülke', 'flx-updater-manager' ); ?></th><th class="n"><?php esc_html_e( 'Site', 'flx-updater-manager' ); ?></th><th class="n hide-sm"><?php esc_html_e( 'Kontrol', 'flx-updater-manager' ); ?></th><th class="n"><?php esc_html_e( 'İndirme', 'flx-updater-manager' ); ?></th><th class="n hide-sm"><?php esc_html_e( 'Veri', 'flx-updater-manager' ); ?></th><th class="n"><?php esc_html_e( 'Engellenen', 'flx-updater-manager' ); ?></th><th class="hide-sm"><?php esc_html_e( 'En çok inen', 'flx-updater-manager' ); ?></th></tr>
				<?php foreach ( $geo as $i => $r ) : ?>
					<?php
					$top = array();
					foreach ( array_slice( (array) $r['pk_dl'], 0, 3, true ) as $slug => $cnt ) {
						$top[] = self::pkg_name( $O, $slug ) . ' ' . flxum_num( $cnt );
					}
					$has = $r['pk_dl'] || $r['pk_inst'];
					$k   = '' !== $r['cc'] ? $r['cc'] : 'zz';
					?>
					<tr class="flxum-geo-row<?php echo $has ? ' has' : ''; ?>" data-k="<?php echo esc_attr( $k ); ?>"<?php echo $has ? ' tabindex="0" aria-expanded="false"' : ''; ?>>
						<td class="lbl"><span class="bar" style="width:<?php echo esc_attr( round( ( $r['checks'] + $r['downloads'] ) / $gmax * 100, 1 ) ); ?>%"></span><span class="txt"><?php echo self::cc( $r['cc'], $r['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo $has ? ' <i class="chev" aria-hidden="true">›</i>' : ''; ?></span></td>
						<td class="n"><?php echo esc_html( flxum_num( $r['sites'] ) ); ?></td>
						<td class="n hide-sm"><?php echo esc_html( flxum_num( $r['checks'] ) ); ?></td>
						<td class="n"><?php echo esc_html( flxum_num( $r['downloads'] ) ); ?></td>
						<td class="n hide-sm"><?php echo esc_html( flxum_size( $r['bytes'] ) ); ?></td>
						<td class="n"><?php echo $r['blocked'] ? '<span class="flxum-blk">' . esc_html( flxum_num( $r['blocked'] ) ) . '</span>' : '<span class="flxum-muted">0</span>'; ?></td>
						<td class="flxum-small flxum-muted hide-sm"><?php echo esc_html( implode( ' · ', $top ) ?: '—' ); ?></td>
					</tr>
					<?php if ( $has ) : ?>
					<tr class="flxum-geo-detail" hidden data-for="<?php echo esc_attr( $k ); ?>"><td colspan="7">
						<table class="flxum-mini">
							<tr><th><?php esc_html_e( 'Paket', 'flx-updater-manager' ); ?></th><th class="n"><?php esc_html_e( 'Kurulu site', 'flx-updater-manager' ); ?></th><th class="n"><?php esc_html_e( 'İndirme', 'flx-updater-manager' ); ?></th></tr>
							<?php foreach ( array_keys( (array) $r['pk_inst'] + (array) $r['pk_dl'] ) as $slug ) : ?>
								<tr><td><?php echo esc_html( self::pkg_name( $O, $slug ) ); ?> <span class="flxum-muted"><?php echo esc_html( $slug ); ?></span></td><td class="n"><?php echo esc_html( flxum_num( $r['pk_inst'][ $slug ] ?? 0 ) ); ?></td><td class="n"><?php echo esc_html( flxum_num( $r['pk_dl'][ $slug ] ?? 0 ) ); ?></td></tr>
							<?php endforeach; ?>
						</table>
					</td></tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</table></div>
			<?php endif; ?>
			<p class="flxum-muted"><?php esc_html_e( 'Kontrol ve indirmeler her istekte sayılır; kullanım verisi izni gerekmez. Satıra tıklayınca o ülkedeki paketler açılır.', 'flx-updater-manager' ); ?></p>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Siteler                                                             */
	/* ------------------------------------------------------------------ */

	/** Private erişim seçimi: "tümü" ya da depolar + paketler (sunucu panelindekiyle aynı alanlar). */
	private static function scope_field( array $S, $current, array $repos = array() ) {
		$all    = '*' === $current;
		$sel    = $all ? array() : (array) $current;
		$rsel   = array_map( 'strtolower', $repos );
		$groups = array();
		foreach ( (array) $S['github']['private_repos'] as $r ) {
			$groups[ strtolower( $r ) ] = array( 'repo' => $r, 'items' => array() );
		}
		foreach ( (array) $S['packages'] as $p ) {
			if ( empty( $p['private'] ) ) {
				continue;
			}
			$k = strtolower( $p['repo'] );
			if ( ! isset( $groups[ $k ] ) ) {
				$groups[ $k ] = array( 'repo' => $p['repo'], 'items' => array() );
			}
			$groups[ $k ]['items'][] = $p;
		}
		$upd = (array) $S['updater_slugs'];
		?>
		<label class="flxum-label"><?php esc_html_e( 'Private erişim', 'flx-updater-manager' ); ?> <span class="flxum-muted"><?php esc_html_e( '(public paketler her siteye açık)', 'flx-updater-manager' ); ?></span></label>
		<select name="scope" data-scope>
			<option value="all" <?php selected( $all ); ?>><?php esc_html_e( 'Tüm private paketler (yeni eklenenler dahil)', 'flx-updater-manager' ); ?></option>
			<option value="some" <?php selected( ! $all ); ?>><?php esc_html_e( 'Seçilen depolar ve paketler', 'flx-updater-manager' ); ?></option>
		</select>
		<div class="flxum-scope"<?php echo $all ? ' hidden' : ''; ?>>
			<?php if ( ! $groups ) : ?>
				<p class="flxum-muted"><?php esc_html_e( 'Private depo yok.', 'flx-updater-manager' ); ?></p>
			<?php endif; ?>
			<?php foreach ( $groups as $k => $g ) : ?>
				<?php $whole = in_array( $k, $rsel, true ); ?>
				<fieldset class="flxum-repo-grp<?php echo $whole ? ' whole' : ''; ?>">
					<?php /* translators: %d: paket sayısı */ ?>
					<label class="flxum-repo-all"><input type="checkbox" name="repos[]" value="<?php echo esc_attr( $g['repo'] ); ?>" <?php checked( $whole ); ?>> <b><?php echo esc_html( $g['repo'] ); ?></b> <span class="flxum-muted"><?php echo esc_html( sprintf( __( 'tümü, %d paket + sonradan eklenenler', 'flx-updater-manager' ), count( $g['items'] ) ) ); ?></span></label>
					<div class="flxum-checks">
						<?php foreach ( $g['items'] as $p ) : ?>
							<?php $lock = in_array( $p['slug'], $upd, true ); ?>
							<label><input type="checkbox" name="packages[]" value="<?php echo esc_attr( $p['slug'] ); ?>" <?php checked( $lock || in_array( $p['slug'], $sel, true ) ); ?> <?php disabled( $lock ); ?>> <?php echo esc_html( $p['name'] ); ?></label>
						<?php endforeach; ?>
					</div>
				</fieldset>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private static function scope_label( array $site, array $S ) {
		if ( '*' === $site['packages'] ) {
			return esc_html__( 'Tüm private paketler', 'flx-updater-manager' );
		}
		$names = array();
		foreach ( (array) $S['packages'] as $p ) {
			$names[ $p['slug'] ] = $p;
		}
		$parts = array();
		foreach ( (array) $site['repos'] as $r ) {
			$parts[] = '<b>' . esc_html( $r ) . '</b> ' . esc_html__( '(tümü)', 'flx-updater-manager' );
		}
		$in = array_map( 'strtolower', (array) $site['repos'] );
		foreach ( (array) $site['packages'] as $slug ) {
			$p = $names[ $slug ] ?? null;
			if ( ( ! $p && in_array( $slug, (array) $S['updater_slugs'], true ) ) || ( $p && in_array( strtolower( $p['repo'] ), $in, true ) ) ) {
				continue;
			}
			$parts[] = esc_html( $p['name'] ?? $slug );
		}
		return $parts ? implode( ', ', $parts ) : '<span class="flxum-muted">' . esc_html__( 'Yalnız public paketler', 'flx-updater-manager' ) . '</span>';
	}

	private static function tab_sites() {
		$S = self::settings();
		if ( is_wp_error( $S ) ) {
			self::error_box( $S );
			return;
		}
		$latest = array();
		foreach ( (array) $S['packages'] as $p ) {
			$latest[ $p['slug'] ] = $p['version'];
		}
		?>
		<div class="flxum-grid2">
			<div class="flxum-card">
				<h2><?php esc_html_e( 'Site ekle', 'flx-updater-manager' ); ?></h2>
				<?php echo self::form( 'add_site' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<label class="flxum-label" for="flxum-site-name"><?php esc_html_e( 'Site adı', 'flx-updater-manager' ); ?></label>
					<input type="text" id="flxum-site-name" name="name" class="regular-text" placeholder="<?php esc_attr_e( 'ör. Örnek Site', 'flx-updater-manager' ); ?>" required maxlength="80">
					<?php self::scope_field( $S, '*' ); ?>
					<label class="flxum-label"><?php esc_html_e( 'Kanal', 'flx-updater-manager' ); ?></label>
					<select name="channel"><option value="stable"><?php esc_html_e( 'Kararlı sürümler', 'flx-updater-manager' ); ?></option><option value="beta"><?php esc_html_e( 'Beta dahil', 'flx-updater-manager' ); ?></option></select>
					<p><button class="button button-primary"><?php esc_html_e( 'Anahtar üret', 'flx-updater-manager' ); ?></button></p>
				</form>
			</div>
			<div class="flxum-card">
				<h2><?php esc_html_e( 'Var olan bir anahtarı test et', 'flx-updater-manager' ); ?></h2>
				<p class="flxum-muted"><?php esc_html_e( 'Anahtarın hangi siteye ait olduğunu ve hangi paketleri görebildiğini gösterir.', 'flx-updater-manager' ); ?></p>
				<?php echo self::form( 'test_key', 'class="flxum-row" autocomplete="off"' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<input type="password" name="key" placeholder="flxu_…" required class="regular-text">
					<button class="button"><?php esc_html_e( 'Test et', 'flx-updater-manager' ); ?></button>
				</form>
			</div>
		</div>

		<div class="flxum-card" id="keys">
			<h2><?php esc_html_e( 'Anahtarlı siteler', 'flx-updater-manager' ); ?></h2>
			<?php if ( ! $S['sites'] ) : ?>
				<p class="flxum-empty"><?php esc_html_e( 'Henüz site yok. "Site ekle" ile ilk anahtarı üretin.', 'flx-updater-manager' ); ?></p>
			<?php else : ?>
			<div class="flxum-table-wrap"><table class="flxum-table">
				<thead><tr><th><?php esc_html_e( 'Site', 'flx-updater-manager' ); ?></th><th><?php esc_html_e( 'Durum', 'flx-updater-manager' ); ?></th><th class="hide-sm"><?php esc_html_e( 'Kurulu paketler', 'flx-updater-manager' ); ?></th><th><?php esc_html_e( 'İzin', 'flx-updater-manager' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $S['sites'] as $site ) : ?>
					<?php
					$ts  = (int) $site['last_seen'];
					$pkg = array();
					foreach ( (array) $site['installed'] as $slug => $ver ) {
						$new   = $latest[ $slug ] ?? '';
						$old   = $new && version_compare( (string) $ver, $new, '<' );
						$pkg[] = esc_html( $slug ) . ' <b class="' . ( $old ? 'flxum-old' : 'flxum-new' ) . '">' . esc_html( $ver ) . '</b>' . ( $old ? ' <span class="flxum-muted">→ ' . esc_html( $new ) . '</span>' : '' );
					}
					?>
					<tr<?php echo $site['active'] ? '' : ' class="is-off"'; ?>>
						<td><b><?php echo esc_html( $site['name'] ); ?></b><?php if ( '' !== $site['url'] ) : ?><br><span class="flxum-muted"><?php echo esc_html( $site['url'] ); ?></span><?php endif; ?>
							<?php if ( '' !== $site['wp'] ) : ?><br><span class="flxum-muted flxum-small">WP <?php echo esc_html( $site['wp'] ); ?> · PHP <?php echo esc_html( $site['php'] ); ?></span><?php endif; ?></td>
						<td>
							<?php
							// phpcs:disable WordPress.Security.EscapeOutput -- pill() kaçışlı.
							if ( ! $site['active'] ) {
								echo self::pill( __( 'Pasif', 'flx-updater-manager' ), 'bad' ) . '<br>';
							}
							if ( 'expired' === $site['pending'] ) {
								echo self::pill( __( 'Teslim süresi doldu', 'flx-updater-manager' ), 'bad' );
							} elseif ( 'waiting' === $site['pending'] ) {
								echo self::pill( __( 'Teslim bekliyor', 'flx-updater-manager' ), 'warn' );
							} elseif ( ! $ts ) {
								echo self::pill( __( 'Hiç bağlanmadı', 'flx-updater-manager' ) );
							} elseif ( time() - $ts > 2 * DAY_IN_SECONDS ) {
								/* translators: %d: gün */
								echo self::pill( sprintf( __( '%d gündür yok', 'flx-updater-manager' ), (int) floor( ( time() - $ts ) / DAY_IN_SECONDS ) ), 'warn' );
							} else {
								echo self::pill( __( 'Bağlı', 'flx-updater-manager' ), 'ok' );
							}
							// phpcs:enable
							?>
							<?php if ( $ts ) : ?><br><span class="flxum-muted flxum-small"><?php echo esc_html( flxum_ago( $ts ) ); ?></span><?php endif; ?>
						</td>
						<td class="flxum-small hide-sm"><?php echo $pkg ? implode( '<br>', $pkg ) : '<span class="flxum-muted">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td class="flxum-small"><?php echo self::scope_label( $site, $S ); // phpcs:ignore WordPress.Security.EscapeOutput ?><br><span class="flxum-muted"><?php echo esc_html( 'beta' === $site['channel'] ? __( 'Beta dahil', 'flx-updater-manager' ) : __( 'Kararlı', 'flx-updater-manager' ) ); ?></span></td>
						<td class="flxum-actions">
							<?php if ( $site['editable'] ) : ?>
								<?php echo self::form( 'toggle_site', 'class="flxum-inline-form"' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
									<input type="hidden" name="hash" value="<?php echo esc_attr( $site['hash'] ); ?>"><input type="hidden" name="on" value="<?php echo $site['active'] ? '' : '1'; ?>">
									<button class="button button-small"><?php echo esc_html( $site['active'] ? __( 'Pasif yap', 'flx-updater-manager' ) : __( 'Aktif yap', 'flx-updater-manager' ) ); ?></button>
								</form>
								<details<?php echo 'waiting' === $site['pending'] ? ' open' : ''; ?>><summary><?php esc_html_e( 'Erişimi düzenle', 'flx-updater-manager' ); ?></summary>
									<?php echo self::form( 'update_site', 'class="flxum-edit"' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
										<input type="hidden" name="hash" value="<?php echo esc_attr( $site['hash'] ); ?>">
										<label class="flxum-label"><?php esc_html_e( 'Ad', 'flx-updater-manager' ); ?></label><input type="text" name="name" value="<?php echo esc_attr( $site['name'] ); ?>" maxlength="80">
										<?php self::scope_field( $S, $site['packages'], (array) $site['repos'] ); ?>
										<label class="flxum-label"><?php esc_html_e( 'Kanal', 'flx-updater-manager' ); ?></label>
										<select name="channel"><option value="stable"><?php esc_html_e( 'Kararlı', 'flx-updater-manager' ); ?></option><option value="beta" <?php selected( 'beta', $site['channel'] ); ?>><?php esc_html_e( 'Beta dahil', 'flx-updater-manager' ); ?></option></select>
										<p><button class="button button-primary button-small"><?php esc_html_e( 'Kaydet', 'flx-updater-manager' ); ?></button></p>
									</form>
									<?php echo self::form( 'delete_site', self::confirm( __( 'Bu site silinsin mi? Anahtarı hemen geçersiz olur.', 'flx-updater-manager' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
										<input type="hidden" name="hash" value="<?php echo esc_attr( $site['hash'] ); ?>">
										<button class="button button-small flxum-danger"><?php esc_html_e( 'Siteyi sil', 'flx-updater-manager' ); ?></button>
									</form>
								</details>
							<?php else : ?>
								<span class="flxum-muted flxum-small"><?php esc_html_e( 'Sunucu dosyasından (config.php)', 'flx-updater-manager' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table></div>
			<?php endif; ?>
			<p class="flxum-muted"><?php esc_html_e( 'Sunucu anahtarın yalnız özetini (sha256) saklar. Kaybolan anahtar geri alınamaz: siteyi silip yeni anahtar üretin. Anahtarsız bağlanan bir siteye Genel bakış › Bağlanan siteler › "Anahtar ver" ile de anahtar verebilirsiniz.', 'flx-updater-manager' ); ?></p>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Paketler                                                            */
	/* ------------------------------------------------------------------ */

	private static function tab_packages() {
		$S = self::settings();
		if ( is_wp_error( $S ) ) {
			self::error_box( $S );
			return;
		}
		?>
		<div class="flxum-card">
			<div class="flxum-card__head">
				<h2><?php esc_html_e( 'Paketler', 'flx-updater-manager' ); ?></h2>
				<?php echo self::form( 'flush', 'class="flxum-inline-form"' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><button class="button"><?php esc_html_e( 'Paketleri yeniden oku', 'flx-updater-manager' ); ?></button></form>
			</div>
			<?php if ( ! $S['packages'] ) : ?>
				<p class="flxum-empty"><?php esc_html_e( 'Paket yok. GitHub sekmesinde depoları ve (private depo için) token\'ı girin; depoda release yoksa bir pakete sürüm çıkın.', 'flx-updater-manager' ); ?></p>
			<?php else : ?>
			<div class="flxum-table-wrap"><table class="flxum-table">
				<thead><tr><th><?php esc_html_e( 'Paket', 'flx-updater-manager' ); ?></th><th><?php esc_html_e( 'Erişim', 'flx-updater-manager' ); ?></th><th class="hide-sm"><?php esc_html_e( 'Tür', 'flx-updater-manager' ); ?></th><th><?php esc_html_e( 'Son sürüm', 'flx-updater-manager' ); ?></th><th class="hide-sm"><?php esc_html_e( 'Boyut', 'flx-updater-manager' ); ?></th><th><?php esc_html_e( 'Yayın', 'flx-updater-manager' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $S['packages'] as $p ) : ?>
					<tr>
						<td><b><?php echo esc_html( $p['name'] ); ?></b><br><span class="flxum-muted flxum-small"><?php echo esc_html( $p['slug'] ); ?></span></td>
						<td><?php echo $p['public'] ? self::pill( 'Public', 'ok' ) : self::pill( __( 'Anahtarla', 'flx-updater-manager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?><br><span class="flxum-muted flxum-small"><?php echo esc_html( $p['repo'] ); ?></span></td>
						<td class="hide-sm"><?php echo esc_html( 'theme' === $p['type'] ? __( 'Tema', 'flx-updater-manager' ) : __( 'Eklenti', 'flx-updater-manager' ) ); ?></td>
						<td><?php echo esc_html( $p['version'] ); ?><?php echo $p['stable'] === $p['version'] ? '' : ' ' . self::pill( 'beta', 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<td class="flxum-small hide-sm"><?php echo esc_html( flxum_size( $p['size'] ) ); ?></td>
						<td class="flxum-small"><?php echo esc_html( $p['published'] ? flxum_date( strtotime( $p['published'] ), 'short' ) : '—' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table></div>
			<?php endif; ?>
			<p class="flxum-muted"><?php esc_html_e( 'Yeni sürüm en geç 5 dakikada görünür; webhook ayarlıysa hemen. Private depodaki bir paketi anahtarsız açmak için GitHub sekmesinde "Herkese açık paketler"i işaretleyin.', 'flx-updater-manager' ); ?></p>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* GitHub                                                              */
	/* ------------------------------------------------------------------ */

	/** Depo seçici: etiketler + GitHub'dan gelen öneri listesi (admin.js). */
	private static function repo_chips( $name, array $list, $mode, $single = false ) {
		?>
		<div class="flxum-chips" data-mode="<?php echo esc_attr( $mode ); ?>"<?php echo $single ? ' data-single="1"' : ''; ?>>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( implode( ', ', $list ) ); ?>">
			<?php foreach ( $list as $r ) : ?>
				<span class="flxum-chip" data-v="<?php echo esc_attr( $r ); ?>"><?php echo esc_html( $r ); ?><button type="button" aria-label="<?php esc_attr_e( 'Kaldır', 'flx-updater-manager' ); ?>">×</button></span>
			<?php endforeach; ?>
			<input type="text" class="flxum-chip-in" autocomplete="off" placeholder="<?php echo esc_attr( $single ? __( 'Depo seçin', 'flx-updater-manager' ) : __( 'Depo ekle…', 'flx-updater-manager' ) ); ?>">
			<div class="flxum-chip-dd" hidden></div>
		</div>
		<?php
	}

	private static function tab_github() {
		$S = self::settings();
		if ( is_wp_error( $S ) ) {
			self::error_box( $S );
			return;
		}
		$G    = $S['github'];
		$priv = array_filter(
			(array) $S['packages'],
			static function ( $p ) {
				return ! empty( $p['private'] );
			}
		);
		$pub  = (array) $G['public_packages'];
		$n    = count( $S['packages'] );
		$npub = count( $S['packages'] ) - count( $priv );
		?>
		<div class="flxum-grid2">
			<div class="flxum-card">
				<h2>GitHub</h2>
				<p>
					<?php if ( ! $G['connected'] ) : ?>
						<?php echo self::pill( __( 'Bağlı değil', 'flx-updater-manager' ), 'bad' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span class="flxum-small"><?php echo esc_html( $G['error'] ); ?></span>
					<?php else : ?>
						<?php /* translators: 1: toplam, 2: private, 3: public */ ?>
						<?php echo self::pill( __( 'Bağlı', 'flx-updater-manager' ), 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span class="flxum-small"><?php echo esc_html( sprintf( __( '%1$d paket okundu (%2$d private, %3$d public)', 'flx-updater-manager' ), $n, $n - $npub, $npub ) ); ?></span>
					<?php endif; ?>
				</p>
				<?php if ( ! $G['token_set'] && $G['public_repos'] ) : ?>
					<p class="flxum-muted"><?php esc_html_e( 'Token yok: yalnız public depolar okunuyor.', 'flx-updater-manager' ); ?></p>
				<?php endif; ?>
				<?php foreach ( (array) $G['repo_errors'] as $repo => $err ) : ?>
					<p class="flxum-small"><?php echo self::pill( __( 'Okunamadı', 'flx-updater-manager' ), 'bad' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $repo . ': ' . $err ); ?></p>
				<?php endforeach; ?>
				<?php echo self::form( 'save_github', 'autocomplete="off"' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<label class="flxum-label"><?php esc_html_e( 'Private depolar', 'flx-updater-manager' ); ?> <span class="flxum-muted"><?php esc_html_e( '(paketleri yalnız site anahtarıyla)', 'flx-updater-manager' ); ?></span></label>
					<?php self::repo_chips( 'repo', (array) $G['private_repos'], 'private' ); ?>
					<label class="flxum-label"><?php esc_html_e( 'Public depolar', 'flx-updater-manager' ); ?> <span class="flxum-muted"><?php esc_html_e( '(paketleri herkese anahtarsız; boşsa public güncelleme kapalı)', 'flx-updater-manager' ); ?></span></label>
					<?php self::repo_chips( 'public_repos', (array) $G['public_repos'], 'public' ); ?>
					<?php if ( $priv || $pub ) : ?>
						<label class="flxum-label"><?php esc_html_e( 'Herkese açık paketler', 'flx-updater-manager' ); ?> <span class="flxum-muted"><?php esc_html_e( '(private depodaki bu paketler anahtarsız iner; ör. web sitesindeki indirme butonu için flx-setup)', 'flx-updater-manager' ); ?></span></label>
						<input type="hidden" name="public_packages_sent" value="1">
						<div class="flxum-checks">
							<?php foreach ( $priv as $p ) : ?>
								<label><input type="checkbox" name="public_packages[]" value="<?php echo esc_attr( $p['slug'] ); ?>" <?php checked( in_array( $p['slug'], $pub, true ) ); ?>> <?php echo esc_html( $p['name'] ); ?></label>
							<?php endforeach; ?>
							<?php foreach ( array_diff( $pub, wp_list_pluck( $priv, 'slug' ) ) as $slug ) : ?>
								<label><input type="checkbox" name="public_packages[]" value="<?php echo esc_attr( $slug ); ?>" checked> <?php echo esc_html( $slug ); ?></label>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<label class="flxum-label"><?php esc_html_e( 'Uygulama deposu', 'flx-updater-manager' ); ?> <span class="flxum-muted"><?php esc_html_e( '(masaüstü/mobil uygulama, tag v<sürüm>)', 'flx-updater-manager' ); ?></span></label>
					<?php self::repo_chips( 'app_repo', array_filter( array( $G['app_repo'] ) ), 'app', true ); ?>
					<label class="flxum-label"><?php esc_html_e( 'FluxWP masaüstü deposu', 'flx-updater-manager' ); ?> <span class="flxum-muted"><?php esc_html_e( '(fluxapp-v<sürüm> yayınları; boşsa ilk private depo)', 'flx-updater-manager' ); ?></span></label>
					<?php self::repo_chips( 'fluxapp_repo', array_filter( array( $G['fluxapp_repo'] ) ), 'app', true ); ?>
					<p class="flxum-muted"><?php esc_html_e( 'Liste GitHub\'dan gelir: token\'ın erişebildiği depolar ve bu sahiplerin public depoları. Listede olmayanı owner/repo yazıp Enter ile ekleyin.', 'flx-updater-manager' ); ?></p>
					<label class="flxum-label">Token <span class="flxum-muted"><?php esc_html_e( '(fine-grained, private depolar + uygulama deposu, Contents: Read-only)', 'flx-updater-manager' ); ?></span></label>
					<?php /* translators: %s: son 4 karakter */ ?>
					<input type="password" name="github_token" class="large-text" autocomplete="new-password" placeholder="<?php echo esc_attr( $G['token_set'] ? sprintf( __( 'Kayıtlı: …%s (değiştirmek için yenisini yazın)', 'flx-updater-manager' ), $G['token_hint'] ) : 'github_pat_…' ); ?>">
					<p class="flxum-muted"><?php esc_html_e( 'Token yalnız güncelleme sunucusunda saklanır; bu siteye geri okunmaz.', 'flx-updater-manager' ); ?></p>
					<p><button class="button button-primary"><?php esc_html_e( 'Kaydet ve test et', 'flx-updater-manager' ); ?></button></p>
				</form>
			</div>
			<div class="flxum-card">
				<h2><?php esc_html_e( 'Anında yayın (isteğe bağlı)', 'flx-updater-manager' ); ?></h2>
				<p class="flxum-muted">
					<?php
					/* translators: %s: sunucu adresi */
					echo wp_kses( sprintf( __( 'Yeni sürüm en geç 5 dakikada görünür. Anında görünsün isterseniz bu secret\'ı paket deposunun Settings › Secrets › Actions bölümüne <code>UPDATE_WEBHOOK_SECRET</code> adıyla ekleyin; repo değişkeni <code>UPDATE_SERVER</code> = <code>%s</code>.', 'flx-updater-manager' ), esc_html( $G['base_url'] ) ), array( 'code' => array() ) );
					?>
				</p>
				<?php if ( '' !== $G['webhook_secret'] ) : ?>
					<div class="flxum-copy"><code id="flxum-whs"><?php echo esc_html( $G['webhook_secret'] ); ?></code><button type="button" class="button" data-copy="#flxum-whs"><?php esc_html_e( 'Kopyala', 'flx-updater-manager' ); ?></button></div>
				<?php endif; ?>
				<?php echo self::form( 'gen_webhook', '' !== $G['webhook_secret'] ? self::confirm( __( 'Yeni secret üretilsin mi? Eskisi hemen çalışmaz; GitHub\'daki secret\'ı da değiştirmeniz gerekir.', 'flx-updater-manager' ) ) : '' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<p><button class="button"><?php echo esc_html( '' === $G['webhook_secret'] ? __( 'Secret üret', 'flx-updater-manager' ) : __( 'Yeni secret üret', 'flx-updater-manager' ) ); ?></button></p>
				</form>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Güvenlik                                                            */
	/* ------------------------------------------------------------------ */

	private static function tab_security() {
		$S = self::settings();
		$R = FLXUM_Client::get( 'resources' );
		if ( is_wp_error( $S ) || is_wp_error( $R ) ) {
			self::error_box( is_wp_error( $S ) ? $S : $R );
			return;
		}
		$X    = $S['security'];
		$st   = $R['geo_db'];
		$lock = $X['lockdown'];
		$poff = $X['public_off'];
		?>
		<div class="flxum-grid2">
			<div class="flxum-card">
				<h2><?php esc_html_e( 'Dağıtım anahtarları', 'flx-updater-manager' ); ?></h2>
				<div class="flxum-switch-row">
					<div>
						<b><?php esc_html_e( 'Acil durdurma', 'flx-updater-manager' ); ?></b> <?php echo $lock ? self::pill( __( 'Durduruldu', 'flx-updater-manager' ), 'bad' ) : self::pill( __( 'Dağıtım açık', 'flx-updater-manager' ), 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<p class="flxum-muted"><?php esc_html_e( 'Tüm güncelleme kontrolleri, paket ve uygulama indirmeleri 503 ile durur (anahtarlı siteler dahil). Sunucu paneli, webhook ve bu ekran çalışmaya devam eder.', 'flx-updater-manager' ); ?></p>
					</div>
					<?php echo self::form( 'save_switch', self::confirm( $lock ? __( 'Dağıtım yeniden açılsın mı?', 'flx-updater-manager' ) : __( 'Tüm dağıtım durdurulsun mu? Hiçbir site güncelleme alamaz.', 'flx-updater-manager' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<input type="hidden" name="switch" value="lockdown"><input type="hidden" name="on" value="<?php echo $lock ? '' : '1'; ?>">
						<button class="button <?php echo $lock ? 'button-primary' : 'flxum-danger'; ?>"><?php echo esc_html( $lock ? __( 'Dağıtımı aç', 'flx-updater-manager' ) : __( 'Tümünü durdur', 'flx-updater-manager' ) ); ?></button>
					</form>
				</div>
				<div class="flxum-switch-row">
					<div>
						<b><?php esc_html_e( 'Anahtarsız dağıtım', 'flx-updater-manager' ); ?></b> <?php echo $poff ? self::pill( __( 'Kapalı', 'flx-updater-manager' ), 'bad' ) : ( $X['has_public'] ? self::pill( __( 'Açık', 'flx-updater-manager' ), 'ok' ) : self::pill( __( 'Public depo yok', 'flx-updater-manager' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<p class="flxum-muted"><?php esc_html_e( 'Kapatınca anahtarsız ve pasif anahtarlı istekler (public paketler, latest.zip indirme butonu, masaüstü/mobil uygulama) 401 alır. Aktif anahtarlı siteler etkilenmez.', 'flx-updater-manager' ); ?></p>
					</div>
					<?php echo self::form( 'save_switch', self::confirm( $poff ? __( 'Anahtarsız dağıtım yeniden açılsın mı?', 'flx-updater-manager' ) : __( 'Anahtarsız dağıtım kapatılsın mı? Anahtarı olmayan siteler ve uygulamalar güncelleme alamaz.', 'flx-updater-manager' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<input type="hidden" name="switch" value="public_off"><input type="hidden" name="on" value="<?php echo $poff ? '' : '1'; ?>">
						<button class="button <?php echo $poff ? 'button-primary' : 'flxum-danger'; ?>"><?php echo esc_html( $poff ? __( 'Anahtarsız dağıtımı aç', 'flx-updater-manager' ) : __( 'Anahtarsızları kapat', 'flx-updater-manager' ) ); ?></button>
					</form>
				</div>
				<p class="flxum-muted"><?php esc_html_e( 'Her değişiklik sunucunun erişim günlüğüne yönetici anahtarının adıyla yazılır.', 'flx-updater-manager' ); ?></p>
			</div>

			<div class="flxum-card">
				<h2><?php esc_html_e( 'Ülke veritabanı', 'flx-updater-manager' ); ?></h2>
				<?php if ( $st['ok'] ) : ?>
					<p><?php echo self::pill( __( 'Kurulu', 'flx-updater-manager' ), 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span class="flxum-small"><?php echo esc_html( trim( $st['type'] . ' · ' . flxum_size( $st['size'] ) ) ); ?></span></p>
					<table class="flxum-mini">
						<tr><td><?php esc_html_e( 'Kaynak', 'flx-updater-manager' ); ?></td><td class="n"><?php echo esc_html( $st['source'] ? $st['source'] : '—' ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Veri tarihi', 'flx-updater-manager' ); ?></td><td class="n"><?php echo esc_html( flxum_date( $st['built'], 'date' ) ); ?></td></tr>
						<tr><td><?php esc_html_e( 'İndirildi', 'flx-updater-manager' ); ?></td><td class="n"><?php echo esc_html( flxum_ago( $st['at'] ) ); ?></td></tr>
					</table>
				<?php else : ?>
					<p><?php echo self::pill( __( 'Yok', 'flx-updater-manager' ), 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span class="flxum-small"><?php esc_html_e( 'İndirin ya da sunucu panelinden .mmdb dosyası yükleyin.', 'flx-updater-manager' ); ?></span></p>
				<?php endif; ?>
				<?php if ( '' !== (string) $st['error'] ) : ?>
					<p class="flxum-small"><?php echo self::pill( __( 'Son deneme başarısız', 'flx-updater-manager' ), 'bad' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span class="flxum-muted"><?php echo esc_html( $st['error'] ); ?></span></p>
				<?php endif; ?>
				<div class="flxum-row">
					<?php echo self::form( 'geo_update', 'class="flxum-inline-form" data-busy' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><button class="button"><?php echo esc_html( $st['ok'] ? __( 'Şimdi güncelle', 'flx-updater-manager' ) : __( 'Veritabanını indir', 'flx-updater-manager' ) ); ?></button></form>
					<?php if ( $st['ok'] ) : ?>
						<?php echo self::form( 'geo_delete', 'class="flxum-inline-form" ' . self::confirm( __( 'Ülke veritabanı silinsin mi?', 'flx-updater-manager' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?><button class="button"><?php esc_html_e( 'Sil', 'flx-updater-manager' ); ?></button></form>
					<?php endif; ?>
				</div>
				<?php echo self::form( 'geo_lookup', 'class="flxum-row" style="margin-top:10px"' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<input type="text" name="ip" placeholder="8.8.8.8" autocomplete="off">
					<button class="button"><?php esc_html_e( 'Ülkesini bul', 'flx-updater-manager' ); ?></button>
				</form>
				<p class="flxum-muted"><?php esc_html_e( 'IP harici bir servise gönderilmez; arama güncelleme sunucusunda yapılır. Veritabanı ayda bir kendiliğinden yenilenir.', 'flx-updater-manager' ); ?> <a href="https://db-ip.com" rel="noopener" target="_blank">IP Geolocation by DB-IP</a> (CC BY 4.0)</p>
			</div>
		</div>

		<div class="flxum-card">
			<h2><?php esc_html_e( 'Ülke erişimi', 'flx-updater-manager' ); ?></h2>
			<?php echo self::form( 'save_geo' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<div class="flxum-seg-radio">
					<?php foreach ( array( 'off' => __( 'Kapalı', 'flx-updater-manager' ), 'deny' => __( 'Seçilen ülkeleri engelle', 'flx-updater-manager' ), 'allow' => __( 'Yalnız seçilen ülkelere izin ver', 'flx-updater-manager' ) ) as $k => $l ) : ?>
						<label><input type="radio" name="geo_mode" value="<?php echo esc_attr( $k ); ?>" <?php checked( $X['geo_mode'], $k ); ?>> <?php echo esc_html( $l ); ?></label>
					<?php endforeach; ?>
				</div>
				<label class="flxum-label"><?php esc_html_e( 'Ülkeler', 'flx-updater-manager' ); ?></label>
				<div class="flxum-chips flxum-cc-pick">
					<input type="hidden" name="geo_countries" value="<?php echo esc_attr( implode( ',', (array) $X['geo_countries'] ) ); ?>">
					<?php foreach ( (array) $X['geo_countries'] as $cc ) : ?>
						<span class="flxum-chip" data-v="<?php echo esc_attr( $cc ); ?>"><b class="flxum-cc"><?php echo esc_html( $cc ); ?></b><?php echo esc_html( $X['countries'][ $cc ] ?? $cc ); ?><button type="button" aria-label="<?php esc_attr_e( 'Kaldır', 'flx-updater-manager' ); ?>">×</button></span>
					<?php endforeach; ?>
					<input type="text" class="flxum-chip-in" list="flxum-cc-list" autocomplete="off" placeholder="<?php esc_attr_e( 'Ülke ekle… (ad ya da TR gibi kod)', 'flx-updater-manager' ); ?>">
				</div>
				<datalist id="flxum-cc-list">
					<?php foreach ( (array) $X['countries'] as $cc => $name ) : ?>
						<option value="<?php echo esc_attr( $cc . ' · ' . $name ); ?>">
					<?php endforeach; ?>
				</datalist>
				<p><label><input type="checkbox" name="geo_keyed_exempt" value="1" <?php checked( $X['geo_keyed_exempt'] ); ?>> <?php esc_html_e( 'Aktif anahtarlı siteler muaf (yalnız anahtarsız istekler kurala tabi)', 'flx-updater-manager' ); ?></label></p>
				<p><label><input type="checkbox" name="geo_auto" value="1" <?php checked( $X['geo_auto'] ); ?>> <?php esc_html_e( 'Ülke veritabanını ayda bir kendiliğinden güncelle', 'flx-updater-manager' ); ?></label></p>
				<p class="flxum-muted"><?php esc_html_e( 'Engellenen istek 403 (region_blocked) alır ve yalnız ülke koduyla sayılır. Ülkesi bulunamayan istek "engelle" kipinde geçer, "yalnız izin ver" kipinde engellenir. Kural güncelleme kontrolü, paket ve uygulama indirmelerine uygulanır; panel ve bu ekran etkilenmez.', 'flx-updater-manager' ); ?></p>
				<p><button class="button button-primary"><?php esc_html_e( 'Kaydet', 'flx-updater-manager' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Kaynaklar                                                           */
	/* ------------------------------------------------------------------ */

	private static function tab_resources() {
		$S = self::settings();
		$R = FLXUM_Client::get( 'resources' );
		if ( is_wp_error( $S ) || is_wp_error( $R ) ) {
			self::error_box( is_wp_error( $S ) ? $S : $R );
			return;
		}
		$V      = $R['server'];
		$gh     = $R['github'];
		$used   = max( 0, $V['disk_total'] - $V['disk_free'] );
		$shared = $V['disk_total'] > 2 * pow( 1024, 4 );
		$parts  = array(
			'zips'  => array( __( 'Zip dosyaları', 'flx-updater-manager' ), '#2563eb' ),
			'cache' => array( __( 'GitHub önbelleği', 'flx-updater-manager' ), '#0d9488' ),
			'stats' => array( __( 'İstatistik', 'flx-updater-manager' ), '#7c3aed' ),
			'logs'  => array( __( 'Günlük', 'flx-updater-manager' ), '#d97706' ),
			'other' => array( __( 'Ayarlar ve diğer', 'flx-updater-manager' ), '#94a3b8' ),
		);
		$age    = $V['cache_at'] ? time() - $V['cache_at'] : 0;
		?>
		<div class="flxum-grid3">
			<div class="flxum-card">
				<h2><?php esc_html_e( 'Depolama', 'flx-updater-manager' ); ?></h2>
				<?php if ( $V['disk_total'] > 0 && $shared ) : ?>
					<?php /* translators: %s: boyut */ ?>
					<div class="flxum-res-line"><span><?php esc_html_e( 'Makine diski (paylaşımlı)', 'flx-updater-manager' ); ?></span><b><?php echo esc_html( sprintf( __( '%s boş', 'flx-updater-manager' ), flxum_size( $V['disk_free'] ) ) ); ?></b></div>
					<p class="flxum-muted"><?php esc_html_e( 'Paylaşımlı hosting\'de PHP sunucudaki tüm hesapların ortak diskini görür; bu sizin kotanız değil.', 'flx-updater-manager' ); ?></p>
				<?php elseif ( $V['disk_total'] > 0 ) : ?>
					<div class="flxum-res-line"><span><?php esc_html_e( 'Disk', 'flx-updater-manager' ); ?></span><b><?php echo esc_html( flxum_size( $used ) . ' / ' . flxum_size( $V['disk_total'] ) ); ?></b></div>
					<?php echo self::meter( $used, $V['disk_total'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php endif; ?>
				<div class="flxum-res-line"><span><?php esc_html_e( 'Sunucu verisi (data/)', 'flx-updater-manager' ); ?></span><b><?php echo esc_html( flxum_size( $V['data_total'] ) ); ?></b></div>
				<div class="flxum-stack">
					<?php foreach ( $parts as $k => $p ) : ?>
						<?php if ( $V['data_total'] > 0 && $V['data'][ $k ] > 0 ) : ?><i style="width:<?php echo esc_attr( max( 1, round( $V['data'][ $k ] / $V['data_total'] * 100, 1 ) ) ); ?>%;background:<?php echo esc_attr( $p[1] ); ?>" title="<?php echo esc_attr( $p[0] ); ?>"></i><?php endif; ?>
					<?php endforeach; ?>
				</div>
				<table class="flxum-mini">
					<?php foreach ( $parts as $k => $p ) : ?>
						<tr><td><i class="flxum-dot" style="background:<?php echo esc_attr( $p[1] ); ?>"></i><?php echo esc_html( $p[0] ); ?><?php echo 'zips' === $k ? ' <span class="flxum-muted">(' . (int) $R['zips']['files'] . ')</span>' : ''; ?></td><td class="n"><?php echo esc_html( flxum_size( $V['data'][ $k ] ) ); ?></td></tr>
					<?php endforeach; ?>
				</table>
				<div class="flxum-row">
					<?php echo self::form( 'prune_zips', 'class="flxum-inline-form"' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><button class="button button-small"><?php esc_html_e( 'Eski zipleri temizle', 'flx-updater-manager' ); ?></button></form>
					<?php echo self::form( 'clear_log', 'class="flxum-inline-form" ' . self::confirm( __( 'Erişim günlüğü silinsin mi?', 'flx-updater-manager' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?><button class="button button-small"><?php esc_html_e( 'Günlüğü temizle', 'flx-updater-manager' ); ?></button></form>
				</div>
			</div>
			<div class="flxum-card">
				<h2>GitHub API</h2>
				<?php if ( ! empty( $gh['limit'] ) ) : ?>
					<div class="flxum-res-line"><span><?php esc_html_e( 'API kotası (saatlik)', 'flx-updater-manager' ); ?></span><b><?php echo esc_html( flxum_num( $gh['remaining'] ) . ' / ' . flxum_num( $gh['limit'] ) ); ?></b></div>
					<?php echo self::meter( $gh['limit'] - $gh['remaining'], $gh['limit'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php /* translators: 1: kullanılan, 2: saat */ ?>
					<p class="flxum-muted"><?php echo esc_html( sprintf( __( 'Kullanılan %1$s · yenilenme %2$s', 'flx-updater-manager' ), flxum_num( $gh['limit'] - $gh['remaining'] ), flxum_date( (int) $gh['reset'], 'time' ) ) ); ?></p>
				<?php else : ?>
					<p class="flxum-small"><?php echo self::pill( __( 'Okunamadı', 'flx-updater-manager' ), 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( (string) ( $gh['error'] ?? '' ) ); ?></p>
				<?php endif; ?>
				<div class="flxum-res-line"><span><?php esc_html_e( 'Paket önbelleği', 'flx-updater-manager' ); ?></span><b><?php echo esc_html( $V['cache_at'] ? flxum_ago( $V['cache_at'] ) : __( 'boş', 'flx-updater-manager' ) ); ?></b></div>
				<?php echo self::meter( min( $age, $V['cache_ttl'] ), max( 1, $V['cache_ttl'] ), 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php /* translators: %d: dakika */ ?>
				<p class="flxum-muted"><?php echo esc_html( sprintf( __( 'Her %d dakikada bir GitHub\'dan yenilenir; webhook gelirse hemen.', 'flx-updater-manager' ), (int) round( $V['cache_ttl'] / 60 ) ) ); ?></p>
			</div>
			<div class="flxum-card">
				<h2>PHP</h2>
				<table class="flxum-mini">
					<tr><td><?php esc_html_e( 'Sürüm', 'flx-updater-manager' ); ?></td><td class="n"><?php echo esc_html( $V['php'] ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Bellek sınırı', 'flx-updater-manager' ); ?></td><td class="n"><?php echo esc_html( '-1' === $V['memory'] ? __( 'sınırsız', 'flx-updater-manager' ) : $V['memory'] ); ?></td></tr>
					<?php /* translators: %d: saniye */ ?>
					<tr><td><?php esc_html_e( 'Süre sınırı', 'flx-updater-manager' ); ?></td><td class="n"><?php echo esc_html( $V['max_time'] ? sprintf( __( '%d sn', 'flx-updater-manager' ), (int) $V['max_time'] ) : __( 'sınırsız', 'flx-updater-manager' ) ); ?></td></tr>
					<tr><td>cURL</td><td class="n"><?php echo esc_html( $V['curl'] ? $V['curl'] : __( 'yok', 'flx-updater-manager' ) ); ?></td></tr>
					<?php if ( is_array( $V['load'] ) ) : ?>
						<tr><td><?php esc_html_e( 'Sunucu yükü (1/5/15 dk)', 'flx-updater-manager' ); ?></td><td class="n"><?php echo esc_html( implode( ' / ', array_map( static function ( $x ) { return number_format_i18n( (float) $x, 2 ); }, $V['load'] ) ) ); ?></td></tr>
					<?php endif; ?>
					<tr><td><?php esc_html_e( 'intl (ülke adları)', 'flx-updater-manager' ); ?></td><td class="n"><?php echo esc_html( $R['intl'] ? __( 'var', 'flx-updater-manager' ) : __( 'yok', 'flx-updater-manager' ) ); ?></td></tr>
				</table>
			</div>
		</div>

		<div class="flxum-card">
			<h2><?php esc_html_e( 'Dosyalar', 'flx-updater-manager' ); ?></h2>
			<?php /* translators: 1: zip sayısı, 2: boyut */ ?>
			<p class="flxum-small"><?php echo esc_html( sprintf( __( '%1$d zip, %2$s', 'flx-updater-manager' ), (int) $R['zips']['files'], flxum_size( $R['zips']['bytes'] ) ) ); ?></p>
			<p class="flxum-muted"><?php esc_html_e( 'Her paketin son sürümleri sunucuda saklanır. Daha eski bir sürüm istenirse GitHub release\'ten yeniden indirilir ve son indirmeden sonra belirtilen süre dolunca silinir. Temizlik saatte bir kendiliğinden yapılır.', 'flx-updater-manager' ); ?></p>
			<?php echo self::form( 'save_zips', 'class="flxum-row"' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<label><?php esc_html_e( 'Paket başına saklanan sürüm', 'flx-updater-manager' ); ?> <input type="number" name="zip_keep" min="1" max="50" value="<?php echo (int) $S['files']['zip_keep']; ?>" class="small-text"></label>
				<label><?php esc_html_e( 'Eski sürüm kaç saat sonra silinsin', 'flx-updater-manager' ); ?> <input type="number" name="zip_temp_hours" min="1" max="720" value="<?php echo (int) $S['files']['zip_temp_hours']; ?>" class="small-text"></label>
				<button class="button"><?php esc_html_e( 'Kaydet', 'flx-updater-manager' ); ?></button>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/* Bağlantı                                                            */
	/* ------------------------------------------------------------------ */

	private static function tab_connection( $info ) {
		$ok     = ! is_wp_error( $info );
		$server = FLXUM_Client::server();
		$s      = flxum_settings();
		if ( ! $ok && FLXUM_Client::configured() && '' !== $info->get_error_message() ) {
			self::error_box( $info );
		}
		?>
		<div class="flxum-grid2">
			<div class="flxum-card">
				<h2><?php echo self::icon( 'shield' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Sunucu bağlantısı', 'flx-updater-manager' ); ?></h2>
				<p>
					<?php if ( $ok ) : ?>
						<?php echo self::pill( __( 'Bağlı', 'flx-updater-manager' ), 'ok' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span class="flxum-small"><?php /* translators: %s: anahtar adı */ echo esc_html( sprintf( __( 'Anahtar: %s', 'flx-updater-manager' ), (string) $info['key_name'] ) ); ?></span>
					<?php else : ?>
						<?php echo self::pill( __( 'Bağlı değil', 'flx-updater-manager' ), 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php endif; ?>
				</p>
				<?php echo self::form( 'connect', 'autocomplete="off"', 'connection' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<label class="flxum-label" for="flxum-server"><?php esc_html_e( 'Sunucu adresi', 'flx-updater-manager' ); ?></label>
					<input type="url" id="flxum-server" name="server" class="regular-text" value="<?php echo esc_attr( '' !== $s['server'] ? $s['server'] : $server ); ?>" placeholder="<?php echo esc_attr( FLXUM_DEFAULT_SERVER ); ?>" <?php disabled( defined( 'FLXUM_SERVER' ) && FLXUM_SERVER ); ?>>
					<label class="flxum-label" for="flxum-key"><?php esc_html_e( 'Yönetici anahtarı', 'flx-updater-manager' ); ?></label>
					<input type="password" id="flxum-key" name="key" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( FLXUM_Client::configured() ? __( 'Kayıtlı (değiştirmek için yenisini yazın)', 'flx-updater-manager' ) : 'flxm_…' ); ?>" <?php disabled( FLXUM_Client::key_from_config() ); ?>>
					<p class="flxum-muted"><?php esc_html_e( 'Anahtarı sunucu panelinde (/admin › Updater Manager) "Yönetici anahtarı üret" ile alın. Anahtar bu sitede şifreli saklanır ve bir daha gösterilmez.', 'flx-updater-manager' ); ?></p>
					<p><button class="button button-primary"><?php esc_html_e( 'Kaydet ve bağlan', 'flx-updater-manager' ); ?></button></p>
				</form>
				<?php if ( FLXUM_Client::configured() && ! FLXUM_Client::key_from_config() ) : ?>
					<?php echo self::form( 'disconnect', self::confirm( __( 'Yönetici anahtarı bu siteden silinsin mi?', 'flx-updater-manager' ) ), 'connection' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<button class="button flxum-danger"><?php esc_html_e( 'Bağlantıyı kaldır', 'flx-updater-manager' ); ?></button>
					</form>
				<?php endif; ?>
				<p class="flxum-muted"><?php echo wp_kses( __( 'wp-config.php\'de <code>FLXUM_SERVER</code> ve <code>FLXUM_KEY</code> sabitleri bu alanları ezer.', 'flx-updater-manager' ), array( 'code' => array() ) ); ?></p>
			</div>
			<?php if ( $ok ) : ?>
				<?php self::manager_keys(); ?>
			<?php else : ?>
			<div class="flxum-card">
				<h2><?php esc_html_e( 'Nasıl bağlanır?', 'flx-updater-manager' ); ?></h2>
				<ol class="flxum-steps">
					<li><?php esc_html_e( 'Güncelleme sunucusunu 1.12.0 ya da üstüne yükseltin.', 'flx-updater-manager' ); ?></li>
					<li><?php esc_html_e( 'Sunucu panelinde (/admin) en alttaki "Updater Manager" kartında bir ad yazıp "Yönetici anahtarı üret"e basın.', 'flx-updater-manager' ); ?></li>
					<li><?php esc_html_e( 'Anahtarı (flxm_…) buraya yapıştırıp "Kaydet ve bağlan"a basın.', 'flx-updater-manager' ); ?></li>
				</ol>
				<p class="flxum-muted"><?php esc_html_e( 'Anahtar sunucudaki tüm ayarlara ve istatistiklere erişir; yönetici anahtarı üretme ve ülke veritabanı dosyası yükleme sunucu panelinde kalır. Sunucu panelinden silinen anahtar hemen çalışmaz olur.', 'flx-updater-manager' ); ?></p>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Sunucudaki yönetici anahtarları (silme) + sunucu paneli parolası. */
	private static function manager_keys() {
		$S = self::settings();
		if ( is_wp_error( $S ) ) {
			self::error_box( $S );
			return;
		}
		?>
		<div class="flxum-card">
			<h2><?php echo self::icon( 'key' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Yönetici anahtarları', 'flx-updater-manager' ); ?></h2>
			<table class="flxum-mini">
				<?php foreach ( (array) $S['manager_keys'] as $k ) : ?>
					<tr>
						<td><b><?php echo esc_html( $k['name'] ); ?></b> <code>flxm_…<?php echo esc_html( $k['hint'] ); ?></code><?php echo $k['current'] ? ' ' . self::pill( __( 'Bu site', 'flx-updater-manager' ), 'ok' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?><br><span class="flxum-muted flxum-small"><?php /* translators: %s: zaman */ echo esc_html( sprintf( __( 'Son kullanım: %s', 'flx-updater-manager' ), flxum_ago( $k['last'] ) ) ); ?></span></td>
						<td class="n">
							<?php if ( ! $k['current'] ) : ?>
								<?php echo self::form( 'revoke_manager_key', 'class="flxum-inline-form" ' . self::confirm( __( 'Bu yönetici anahtarı silinsin mi?', 'flx-updater-manager' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?><input type="hidden" name="hash" value="<?php echo esc_attr( $k['hash'] ); ?>"><button class="button button-small flxum-danger"><?php esc_html_e( 'Sil', 'flx-updater-manager' ); ?></button></form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<p class="flxum-muted"><?php esc_html_e( 'Yeni anahtar yalnız sunucu panelinde üretilir. Bu sitenin anahtarını sunucu panelinden silebilirsiniz.', 'flx-updater-manager' ); ?></p>

			<h3 class="flxum-sub"><?php esc_html_e( 'Sunucu paneli parolası', 'flx-updater-manager' ); ?></h3>
			<?php echo self::form( 'change_password', 'autocomplete="off"' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<label class="flxum-label"><?php esc_html_e( 'Yeni parola', 'flx-updater-manager' ); ?></label><input type="password" name="password" minlength="10" required autocomplete="new-password" class="regular-text">
				<label class="flxum-label"><?php esc_html_e( 'Tekrar', 'flx-updater-manager' ); ?></label><input type="password" name="password2" minlength="10" required autocomplete="new-password" class="regular-text">
				<p><button class="button"><?php esc_html_e( 'Değiştir', 'flx-updater-manager' ); ?></button></p>
			</form>
		</div>
		<?php
	}
}
