<?php
/**
 * Paketler ekranında FluxWP dışındaki her şey: 3. parti eklentiler, temalar, çeviriler ve WordPress çekirdeği.
 *
 * Güncelleme bilgisi WordPress'in kendi kayıtlarından (update_plugins / update_themes / update_core) okunur,
 * güncelleme WordPress'in kendi yükleyicileriyle (Plugin_Upgrader, Theme_Upgrader, Language_Pack_Upgrader)
 * yapılır. Çekirdek güncellemesi uzun sürdüğü ve bakım moduna girdiği için WordPress'in kendi ekranına bağlanır.
 * Satırlar FluxWP satırlarıyla aynı tabloda, sekme (data-group) ve arama (data-search) ile süzülür.
 */

defined( 'ABSPATH' ) || exit;

final class FLXU_WP {

	/** Sekmeler: anahtar => etiket. */
	public static function tabs(): array {
		return array(
			'all'     => __( 'All', 'flx-setup' ),
			'flux'    => __( 'FluxWP', 'flx-setup' ),
			'plugins' => __( 'Plugins', 'flx-setup' ),
			'themes'  => __( 'Themes', 'flx-setup' ),
			'core'    => __( 'WordPress & translations', 'flx-setup' ),
		);
	}

	/**
	 * FluxWP kataloğunda olmayan eklenti/tema satırları + çeviri ve çekirdek satırı.
	 *
	 * @return array[] {key, group, kind, id, name, sub, type_label, installed, latest, active, state, icon, ph, can}
	 */
	public static function rows( array $catalog ): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$rows = array();

		/* Eklentiler */
		$flux_files = array();
		$files      = FLXU_Updater::plugin_files();
		foreach ( $catalog as $slug => $p ) {
			if ( 'plugin' === ( $p['type'] ?? '' ) && isset( $files[ $slug ] ) ) {
				$flux_files[ $files[ $slug ] ] = true;
			}
		}
		$pu      = get_site_transient( 'update_plugins' );
		$presp   = is_object( $pu ) && ! empty( $pu->response ) ? (array) $pu->response : array();
		$can_pl  = current_user_can( 'update_plugins' );
		foreach ( get_plugins() as $file => $d ) {
			if ( isset( $flux_files[ $file ] ) || self::is_flux_dir( dirname( $file ) ) ) {
				continue;
			}
			$u      = isset( $presp[ $file ] ) ? (object) $presp[ $file ] : null;
			$latest = $u && ! empty( $u->new_version ) ? (string) $u->new_version : '';
			$update = '' !== $latest && version_compare( $latest, (string) $d['Version'], '>' );
			$icons  = $u && ! empty( $u->icons ) ? (array) $u->icons : array();
			$rows[] = array(
				'key'       => 'p:' . $file,
				'group'     => 'plugins',
				'kind'      => 'wp_plugin',
				'id'        => $file,
				'name'      => wp_strip_all_tags( (string) $d['Name'] ),
				'sub'       => '.' === dirname( $file ) ? $file : dirname( $file ),
				'author'    => wp_strip_all_tags( (string) $d['AuthorName'] ),
				'installed' => (string) $d['Version'],
				'latest'    => $update ? $latest : (string) $d['Version'],
				'active'    => is_plugin_active( $file ) || ( is_multisite() && is_plugin_active_for_network( $file ) ),
				'state'     => $update ? 'update' : 'current',
				'icon'      => (string) ( $icons['svg'] ?? $icons['2x'] ?? $icons['1x'] ?? '' ),
				'ph'        => 'dashicons-admin-plugins',
				'can'       => $can_pl && ( ! $u || ! empty( $u->package ) ),
			);
		}

		/* Temalar */
		$tu     = get_site_transient( 'update_themes' );
		$tresp  = is_object( $tu ) && ! empty( $tu->response ) ? (array) $tu->response : array();
		$can_th = current_user_can( 'update_themes' );
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			if ( isset( $catalog[ $stylesheet ] ) || self::is_flux_dir( $stylesheet ) ) {
				continue;
			}
			$u      = isset( $tresp[ $stylesheet ] ) ? (array) $tresp[ $stylesheet ] : null;
			$ver    = (string) $theme->get( 'Version' );
			$latest = $u && ! empty( $u['new_version'] ) ? (string) $u['new_version'] : '';
			$update = '' !== $latest && version_compare( $latest, $ver, '>' );
			$shot   = $theme->get_screenshot();
			$rows[] = array(
				'key'       => 't:' . $stylesheet,
				'group'     => 'themes',
				'kind'      => 'wp_theme',
				'id'        => $stylesheet,
				'name'      => wp_strip_all_tags( (string) $theme->display( 'Name', false ) ),
				'sub'       => $stylesheet,
				'author'    => wp_strip_all_tags( (string) $theme->get( 'Author' ) ),
				'installed' => $ver,
				'latest'    => $update ? $latest : $ver,
				'active'    => in_array( $stylesheet, array( get_stylesheet(), get_template() ), true ),
				'state'     => $update ? 'update' : 'current',
				'icon'      => $shot ? (string) $shot : '',
				'ph'        => 'dashicons-admin-appearance',
				'can'       => $can_th && ( ! $u || ! empty( $u['package'] ) ),
			);
		}

		/* Çeviriler (tek satır) */
		if ( ! function_exists( 'wp_get_translation_updates' ) ) {
			require_once ABSPATH . 'wp-includes/update.php';
		}
		$tr    = wp_get_translation_updates();
		$names = array();
		foreach ( $tr as $t ) {
			$names[] = self::translation_name( $t ) . ' (' . $t->language . ')';
		}
		$rows[] = array(
			'key'       => 'lang',
			'group'     => 'core',
			'kind'      => 'wp_lang',
			'id'        => '',
			'name'      => __( 'Translations', 'flx-setup' ),
			'sub'       => $tr
				/* translators: %s: comma separated list of items with new translations */
				? sprintf( __( 'New translations: %s', 'flx-setup' ), implode( ', ', array_slice( $names, 0, 6 ) ) . ( count( $names ) > 6 ? '…' : '' ) )
				: __( 'All translations are up to date.', 'flx-setup' ),
			'author'    => '',
			'installed' => '',
			'latest'    => $tr
				/* translators: %d: number of translation updates */
				? sprintf( _n( '%d update', '%d updates', count( $tr ), 'flx-setup' ), count( $tr ) )
				: '',
			'active'    => true,
			'state'     => $tr ? 'update' : 'current',
			'icon'      => '',
			'ph'        => 'dashicons-translation',
			'can'       => current_user_can( 'update_languages' ) || current_user_can( 'update_plugins' ),
		);

		/* WordPress çekirdeği (güncelleme WordPress'in kendi ekranında) */
		$wp_ver = (string) get_bloginfo( 'version' );
		$core   = '';
		if ( function_exists( 'get_core_updates' ) || is_readable( ABSPATH . 'wp-admin/includes/update.php' ) ) {
			if ( ! function_exists( 'get_core_updates' ) ) {
				require_once ABSPATH . 'wp-admin/includes/update.php';
			}
			foreach ( (array) get_core_updates( array( 'dismissed' => false ) ) as $cu ) {
				if ( isset( $cu->response ) && 'upgrade' === $cu->response && version_compare( (string) $cu->current, $wp_ver, '>' ) ) {
					$core = (string) $cu->current;
					break;
				}
			}
		}
		$rows[] = array(
			'key'       => 'core',
			'group'     => 'core',
			'kind'      => 'wp_core',
			'id'        => '',
			'name'      => 'WordPress',
			'sub'       => __( 'Core', 'flx-setup' ),
			'author'    => '',
			'installed' => $wp_ver,
			'latest'    => '' !== $core ? $core : $wp_ver,
			'active'    => true,
			'state'     => '' !== $core ? 'update' : 'current',
			'icon'      => '',
			'ph'        => 'dashicons-wordpress',
			'can'       => current_user_can( 'update_core' ),
		);

		usort(
			$rows,
			static function ( $a, $b ) {
				$g = array( 'core' => 0, 'plugins' => 1, 'themes' => 2 );
				return array( 'update' === $a['state'] ? 0 : 1, $g[ $a['group'] ], strtolower( $a['name'] ) ) <=> array( 'update' === $b['state'] ? 0 : 1, $g[ $b['group'] ], strtolower( $b['name'] ) );
			}
		);
		return $rows;
	}

	/** FluxWP paketi klasörü mü (katalog gelmese de 3. parti sayılmasın). */
	private static function is_flux_dir( string $dir ): bool {
		return 0 === strpos( $dir, 'flx-' ) || 'fluxwp' === $dir;
	}

	private static function translation_name( $t ): string {
		if ( 'core' === $t->type ) {
			return 'WordPress';
		}
		if ( 'theme' === $t->type ) {
			$th = wp_get_theme( $t->slug );
			return $th->exists() ? (string) $th->get( 'Name' ) : (string) $t->slug;
		}
		foreach ( get_plugins() as $file => $d ) {
			if ( dirname( $file ) === $t->slug || basename( $file, '.php' ) === $t->slug ) {
				return (string) $d['Name'];
			}
		}
		return (string) $t->slug;
	}

	/** Toplu güncellenebilir (çekirdek hariç) bekleyen satırlar. */
	public static function pending( array $rows ): array {
		return array_values(
			array_filter(
				$rows,
				static function ( $r ) {
					return 'update' === $r['state'] && 'wp_core' !== $r['kind'] && $r['can'];
				}
			)
		);
	}

	/** Satır adımı (JS kuyruğu için). */
	public static function step_of( array $r ): array {
		return array(
			'kind' => $r['kind'],
			'slug' => $r['key'],
			'id'   => $r['id'],
			'name' => $r['name'],
		);
	}

	/** Tablo satırı (FluxWP satırlarıyla aynı sütunlar). */
	public static function render_row( array $r ): void {
		$type = array(
			'wp_plugin' => __( 'Plugin', 'flx-setup' ),
			'wp_theme'  => __( 'Theme', 'flx-setup' ),
			'wp_lang'   => __( 'Translation', 'flx-setup' ),
			'wp_core'   => __( 'Core', 'flx-setup' ),
		);
		$search = strtolower( $r['name'] . ' ' . $r['sub'] . ' ' . $r['author'] );
		?>
		<tr class="flxu-row flxu-row--wp flxu-row--<?php echo esc_attr( $r['state'] ); ?>" data-slug="<?php echo esc_attr( $r['key'] ); ?>" data-group="<?php echo esc_attr( $r['group'] ); ?>" data-search="<?php echo esc_attr( $search ); ?>">
			<th class="check-column" scope="row">
				<?php if ( 'update' === $r['state'] && $r['can'] && 'wp_core' !== $r['kind'] ) : ?>
					<input type="checkbox" name="flxu_wp[]" value="<?php echo esc_attr( $r['key'] ); ?>" class="flxu-wpcheck" data-kind="<?php echo esc_attr( $r['kind'] ); ?>" data-id="<?php echo esc_attr( $r['id'] ); ?>" data-name="<?php echo esc_attr( $r['name'] ); ?>" aria-label="<?php echo esc_attr( $r['name'] ); ?>">
				<?php endif; ?>
			</th>
			<td class="flxu-pkg">
				<?php if ( '' !== $r['icon'] ) : ?>
					<img src="<?php echo esc_url( $r['icon'] ); ?>" width="40" height="40" alt="" loading="lazy" referrerpolicy="no-referrer" class="<?php echo 'wp_theme' === $r['kind'] ? 'flxu-pkg__shot' : ''; ?>" onerror="this.onerror=null;this.outerHTML='&lt;span class=&quot;flxu-pkg__ph dashicons <?php echo esc_attr( $r['ph'] ); ?>&quot;&gt;&lt;/span&gt;'">
				<?php else : ?>
					<span class="flxu-pkg__ph dashicons <?php echo esc_attr( $r['ph'] ); ?>"></span>
				<?php endif; ?>
				<span><strong><?php echo esc_html( $r['name'] ); ?></strong><br>
					<?php if ( in_array( $r['kind'], array( 'wp_plugin', 'wp_theme' ), true ) ) : ?>
						<code><?php echo esc_html( $r['sub'] ); ?></code>
					<?php else : ?>
						<small class="flxu-src"><?php echo esc_html( $r['sub'] ); ?></small>
					<?php endif; ?>
					<?php if ( '' !== $r['author'] ) : ?>
						<small class="flxu-src"><?php echo esc_html( $r['author'] ); ?></small>
					<?php endif; ?>
				</span>
			</td>
			<td class="flxu-c-type"><?php echo esc_html( $type[ $r['kind'] ] ?? '' ); ?></td>
			<td class="flxu-c-inst"><?php echo '' !== $r['installed'] ? esc_html( $r['installed'] ) : '—'; ?>
				<?php if ( ! $r['active'] ) : ?>
					<br><small class="flxu-off"><?php esc_html_e( 'Inactive', 'flx-setup' ); ?></small>
				<?php endif; ?>
			</td>
			<td class="flxu-c-latest"><?php echo esc_html( $r['latest'] ); ?></td>
			<td class="flxu-actions">
				<div class="flxu-run" hidden><span class="flxu-run__spin"></span><span class="flxu-run__text"></span><span class="flxu-run__bar"><span></span></span></div>
				<div class="flxu-done" hidden></div>
				<div class="flxu-idle">
				<?php if ( 'update' === $r['state'] ) : ?>
					<span class="flxu-badge flxu-badge--update"><?php esc_html_e( 'Update available', 'flx-setup' ); ?></span>
					<?php if ( 'wp_core' === $r['kind'] && $r['can'] ) : ?>
						<a class="button button-small" href="<?php echo esc_url( self_admin_url( 'update-core.php' ) ); ?>"><?php esc_html_e( 'Update', 'flx-setup' ); ?></a>
					<?php elseif ( $r['can'] ) : ?>
						<button type="submit" class="button button-small" name="flxu_do" value="wp" data-slug="<?php echo esc_attr( $r['key'] ); ?>" data-kind="<?php echo esc_attr( $r['kind'] ); ?>" data-id="<?php echo esc_attr( $r['id'] ); ?>" data-name="<?php echo esc_attr( $r['name'] ); ?>"><?php esc_html_e( 'Update', 'flx-setup' ); ?></button>
					<?php else : ?>
						<small class="flxu-off"><?php esc_html_e( 'No download available', 'flx-setup' ); ?></small>
					<?php endif; ?>
				<?php else : ?>
					<span class="flxu-badge flxu-badge--ok"><?php esc_html_e( 'Up to date', 'flx-setup' ); ?></span>
				<?php endif; ?>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Tek adım: wp_plugin (id = eklenti dosyası) | wp_theme (id = tema klasörü) | wp_lang.
	 *
	 * @return array {ok, lines: [{ok, text}]}
	 */
	public static function run_step( string $kind, string $id ): array {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/theme.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';
		$skin  = new WP_Ajax_Upgrader_Skin();
		$name  = $id;
		$res   = null;

		if ( 'wp_plugin' === $kind ) {
			$all = get_plugins();
			if ( ! current_user_can( 'update_plugins' ) ) {
				return self::fail( __( 'You are not allowed to do this.', 'flx-setup' ) );
			}
			if ( ! isset( $all[ $id ] ) || self::is_flux_dir( dirname( $id ) ) ) {
				return self::fail( __( 'Package not found.', 'flx-setup' ) );
			}
			$name     = (string) $all[ $id ]['Name'];
			$upgrader = new Plugin_Upgrader( $skin );
			$res      = $upgrader->bulk_upgrade( array( $id ) );
			$res      = is_array( $res ) ? ( $res[ $id ] ?? null ) : $res;
		} elseif ( 'wp_theme' === $kind ) {
			$theme = wp_get_theme( $id );
			if ( ! current_user_can( 'update_themes' ) ) {
				return self::fail( __( 'You are not allowed to do this.', 'flx-setup' ) );
			}
			if ( ! $theme->exists() || $theme->get_stylesheet() !== $id || self::is_flux_dir( $id ) ) {
				return self::fail( __( 'Package not found.', 'flx-setup' ) );
			}
			$name     = (string) $theme->get( 'Name' );
			$upgrader = new Theme_Upgrader( $skin );
			$res      = $upgrader->bulk_upgrade( array( $id ) );
			$res      = is_array( $res ) ? ( $res[ $id ] ?? null ) : $res;
		} elseif ( 'wp_lang' === $kind ) {
			if ( ! current_user_can( 'update_languages' ) && ! current_user_can( 'update_plugins' ) ) {
				return self::fail( __( 'You are not allowed to do this.', 'flx-setup' ) );
			}
			$name = __( 'Translations', 'flx-setup' );
			if ( ! wp_get_translation_updates() ) {
				return array(
					'ok'    => true,
					'lines' => array( array( 'ok' => true, 'text' => __( 'All translations are up to date.', 'flx-setup' ) ) ),
				);
			}
			$upgrader = new Language_Pack_Upgrader( $skin );
			$res      = $upgrader->bulk_upgrade();
			$res      = is_array( $res ) && array_filter( $res ) ? true : $res;
		} else {
			return self::fail( __( 'Unknown action.', 'flx-setup' ) );
		}

		$lines = array();
		foreach ( $skin->get_errors()->get_error_messages() as $m ) {
			$lines[] = array( 'ok' => false, 'text' => $name . ': ' . wp_strip_all_tags( $m ) );
		}
		if ( is_wp_error( $res ) ) {
			$lines[] = array( 'ok' => false, 'text' => $name . ': ' . $res->get_error_message() );
		} elseif ( ! $res && ! $lines ) {
			/* translators: %s: plugin/theme name */
			$lines[] = array( 'ok' => false, 'text' => sprintf( __( '%s was not updated: WordPress sees no update for it. Click "Check for updates" and try again.', 'flx-setup' ), $name ) );
		}
		if ( ! $lines ) {
			/* translators: %s: plugin/theme name */
			$lines[] = array( 'ok' => true, 'text' => sprintf( __( '%s updated.', 'flx-setup' ), $name ) );
		}
		return array(
			'ok'    => ! array_filter(
				$lines,
				static function ( $l ) {
					return ! $l['ok'];
				}
			),
			'lines' => $lines,
		);
	}

	private static function fail( string $text ): array {
		return array(
			'ok'    => false,
			'lines' => array( array( 'ok' => false, 'text' => $text ) ),
		);
	}
}
